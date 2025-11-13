<?php
/**
 * Plugin Name: DC25 Vouchers
 * Plugin URI: https://unprinted.ch
 * Description: Bons cadeaux WooCommerce avec prix libre, PDF personnalisé, QR code et validation publique.
 * Version: 1.0.0
 * Author: David Corradini / Unprinted
 * Author URI: https://unprinted.ch
 * Text Domain: dc25-vouchers
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Définitions de constantes
define( 'DC25_VERSION', '1.0.0' );
define( 'DC25_PATH', plugin_dir_path( __FILE__ ) );
define( 'DC25_URL', plugin_dir_url( __FILE__ ) );
define( 'DC25_PLUGIN_FILE', __FILE__ );

// Vérification WooCommerce
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="error"><p><strong>' . esc_html__( 'DC25 Vouchers', 'dc25-vouchers' ) . '</strong> ' . esc_html__( 'nécessite WooCommerce pour fonctionner.', 'dc25-vouchers' ) . '</p></div>';
	} );
	return;
}

// Chargement de l'autoload Composer
$autoload = DC25_PATH . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

// Inclusion des classes (sauf celles qui nécessitent WooCommerce)
// Note: DC25_Order_Handler et DC25_Single_Product_Fields sont chargés via le hook woocommerce_loaded
// pour garantir que WooCommerce est complètement chargé
require_once DC25_PATH . 'includes/class-dc25-coupon-service.php';
require_once DC25_PATH . 'includes/class-dc25-pdf-service.php';
require_once DC25_PATH . 'includes/class-dc25-qr-service.php';
require_once DC25_PATH . 'includes/class-dc25-settings.php'; // DC25_Settings peut être chargée tôt, DC25_Settings_Page sera définie conditionnellement
require_once DC25_PATH . 'includes/class-dc25-verify-endpoint.php';
require_once DC25_PATH . 'includes/helpers.php';

/**
 * Classe principale du plugin
 */
class DC25_Vouchers {

	/**
	 * Instance unique du plugin
	 */
	private static ?DC25_Vouchers $instance = null;

	/**
	 * Obtenir l'instance unique
	 */
	public static function get_instance(): DC25_Vouchers {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur
	 */
	private function __construct() {
		$this->init_hooks();
		// Charger l'endpoint de vérification immédiatement (ne dépend pas de WooCommerce)
		if ( ! class_exists( 'DC25_Verify_Endpoint' ) ) {
			require_once DC25_PATH . 'includes/class-dc25-verify-endpoint.php';
		}
		new DC25_Verify_Endpoint();
		
		$this->load_classes();
	}

	/**
	 * Initialisation des hooks
	 */
	private function init_hooks(): void {
		// Internationalisation
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Charger les classes après WooCommerce (hook officiel WooCommerce)
		add_action( 'woocommerce_loaded', [ $this, 'init_woocommerce_features' ] );

		// Activation / Désactivation
		register_activation_hook( DC25_PLUGIN_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( DC25_PLUGIN_FILE, [ $this, 'deactivate' ] );
	}

	/**
	 * Initialiser les fonctionnalités WooCommerce
	 * Hook officiel : woocommerce_loaded
	 */
	public function init_woocommerce_features(): void {
		// Vérifier que WooCommerce est complètement chargé
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			return;
		}

		// Inclure et initialiser le type de produit personnalisé
		if ( ! class_exists( 'DC25_Gift_Product_Type' ) ) {
			require_once DC25_PATH . 'includes/class-dc25-gift-product-type.php';
		}
		new DC25_Gift_Product_Type();

		// Initialiser les settings
		DC25_Settings::get_instance();
		
		// Initialiser les champs de la page produit (doit être fait après que WooCommerce soit chargé)
		if ( ! class_exists( 'DC25_Single_Product_Fields' ) ) {
			require_once DC25_PATH . 'includes/class-dc25-single-product-fields.php';
		}
		new DC25_Single_Product_Fields();
		
		// Initialiser le gestionnaire de commandes (DOIT être fait après que WooCommerce soit complètement chargé)
		if ( ! class_exists( 'DC25_Order_Handler' ) ) {
			require_once DC25_PATH . 'includes/class-dc25-order-handler.php';
		}
		new DC25_Order_Handler();
		
		// Initialiser l'interface d'administration
		if ( ! class_exists( 'DC25_Vouchers_Admin' ) ) {
			require_once DC25_PATH . 'includes/class-dc25-vouchers-admin.php';
		}
		new DC25_Vouchers_Admin();
	}

	/**
	 * Chargement des classes
	 */
	private function load_classes(): void {
		// Note: DC25_Gift_Product_Type, DC25_Settings, DC25_Single_Product_Fields et DC25_Order_Handler
		// sont maintenant chargés via le hook woocommerce_loaded dans init_woocommerce_features()
		// pour garantir que WooCommerce est complètement chargé
		// Note: DC25_Verify_Endpoint est déjà chargé dans le constructeur (ne dépend pas de WooCommerce)
	}

	/**
	 * Chargement des traductions
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'dc25-vouchers',
			false,
			dirname( plugin_basename( DC25_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Activation du plugin
	 */
	public function activate(): void {
		// Création du répertoire d'upload
		$upload_dir = wp_upload_dir();
		$voucher_dir = trailingslashit( $upload_dir['basedir'] ) . 'dc25-vouchers';
		if ( ! file_exists( $voucher_dir ) ) {
			wp_mkdir_p( $voucher_dir );
		}

		// Protection .htaccess
		$htaccess_file = trailingslashit( $voucher_dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, 'deny from all' );
		}

		// S'assurer que le type de produit gift_voucher existe dans la taxonomie
		if ( taxonomy_exists( 'product_type' ) ) {
			if ( ! term_exists( 'gift_voucher', 'product_type' ) ) {
				wp_insert_term( 'gift_voucher', 'product_type' );
			}
		}

		// Flush rewrite rules pour l'endpoint de vérification
		flush_rewrite_rules();
	}

	/**
	 * Désactivation du plugin
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}
}

// Initialisation du plugin
DC25_Vouchers::get_instance();

