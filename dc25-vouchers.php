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
		
		// Enregistrer les chaînes avec WPML String Translation
		add_action( 'wpml_loaded', [ $this, 'register_wpml_strings' ] );
		add_action( 'init', [ $this, 'register_wpml_strings' ], 20 ); // Fallback si WPML n'est pas encore chargé
		
		// Forcer la langue source en français pour les chaînes du plugin
		add_filter( 'wpml_string_language', [ $this, 'set_string_language_to_french' ], 10, 3 );

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
	 * Enregistrer les chaînes avec WPML String Translation
	 */
	public function register_wpml_strings(): void {
		// Vérifier si WPML String Translation est disponible
		if ( ! function_exists( 'icl_register_string' ) && ! function_exists( 'wpml_register_string' ) ) {
			return;
		}

		// Obtenir la langue par défaut du site (français)
		$default_language = 'fr';
		if ( function_exists( 'wpml_get_default_language' ) ) {
			$default_language = wpml_get_default_language();
		} elseif ( function_exists( 'icl_get_default_language' ) ) {
			$default_language = icl_get_default_language();
		} elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			// Fallback : utiliser la constante WPML si disponible
			$default_language = get_option( 'WPLANG', 'fr' );
		}

		$context = 'dc25-vouchers';
		$strings = [
			// Champs de la page produit
			'Montant du bon cadeau (CHF)' => 'Montant du bon cadeau (CHF)',
			'Entre %s et %s CHF' => 'Entre %s et %s CHF',
			'Vous choisissez librement le montant' => '💝 Vous choisissez librement le montant entre %1$s CHF et %2$s CHF.',
			'Message personnalisé' => 'Message personnalisé',
			'Votre message pour le destinataire' => 'Votre message pour le destinataire...',
			'0 / 120 caractères' => '0 / 120 caractères',
			'De la part de' => 'De la part de',
			'Nom de l\'offrant' => 'Nom de l\'offrant',
			'Nom du destinataire' => 'Nom du destinataire',
			'Je souhaite recevoir le bon cadeau par courrier' => 'Je souhaite recevoir le bon cadeau par courrier',
			'caractères' => 'caractères',
			// Messages de validation
			'Veuillez saisir un montant pour le bon cadeau' => 'Veuillez saisir un montant pour le bon cadeau.',
			'Le montant doit être supérieur à 0' => 'Le montant doit être supérieur à 0.',
			'Le montant doit être compris entre' => 'Le montant doit être compris entre %1$s CHF et %2$s CHF.',
			'Veuillez saisir le nom de l\'offrant' => 'Veuillez saisir le nom de l\'offrant.',
			'Le nom de l\'offrant doit contenir au moins 2 caractères' => 'Le nom de l\'offrant doit contenir au moins 2 caractères.',
			'Veuillez saisir le nom du destinataire' => 'Veuillez saisir le nom du destinataire.',
			'Le nom du destinataire doit contenir au moins 2 caractères' => 'Le nom du destinataire doit contenir au moins 2 caractères.',
			'Le message ne peut pas dépasser 120 caractères' => 'Le message ne peut pas dépasser 120 caractères.',
			// Métadonnées
			'Montant' => 'Montant',
			'Message' => 'Message',
			'Destinataire' => 'Destinataire',
			'Envoi physique' => 'Envoi physique',
			'Oui' => 'Oui',
			'Non' => 'Non',
			'Oui (+ 5 CHF)' => 'Oui (+ 5 CHF)',
			'Envoi physique du bon cadeau' => 'Envoi physique du bon cadeau',
			'Code coupon' => 'Code coupon',
			// Chaînes statiques des emails (pas les contenus configurables)
			'Télécharger le PDF' => 'Télécharger le PDF',
			'Bons cadeaux' => 'Bons cadeaux',
			'Le PDF de votre bon cadeau est joint à cet email.' => 'Le PDF de votre bon cadeau est joint à cet email.',
			'Sujet de l\'email' => 'Sujet de l\'email',
			'Contenu de l\'email' => 'Contenu de l\'email',
			'Langue par défaut' => 'Langue par défaut',
		];

		foreach ( $strings as $name => $string ) {
			$string_name = sanitize_key( str_replace( [ ' ', '%', '$', '(', ')', '.', ',', ':', '💝', '\'' ], [ '_', '', '', '', '', '', '', '', '', '' ], $name ) );
			
			// Utiliser la fonction WPML moderne si disponible
			if ( function_exists( 'wpml_register_string' ) ) {
				// WPML moderne : enregistrer avec la langue française comme langue source
				wpml_register_string( $context, $string_name, $string, false, 'fr' );
			} elseif ( function_exists( 'icl_register_string' ) ) {
				// Ancienne version WPML
				icl_register_string( $context, $string_name, $string );
			}
		}
	}

	/**
	 * Forcer la langue source en français pour les chaînes du plugin
	 *
	 * @param string $language Langue actuelle.
	 * @param string $context Contexte de la chaîne.
	 * @param string $name Nom de la chaîne.
	 * @return string
	 */
	public function set_string_language_to_french( string $language, string $context, string $name ): string {
		// Si c'est une chaîne de notre plugin, forcer le français
		if ( 'dc25-vouchers' === $context ) {
			return 'fr';
		}
		return $language;
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

