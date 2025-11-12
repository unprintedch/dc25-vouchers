<?php
/**
 * Page de réglages admin
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour les réglages
 */
class DC25_Settings {

	/**
	 * Instance unique
	 */
	private static ?DC25_Settings $instance = null;

	/**
	 * Obtenir l'instance
	 */
	public static function get_instance(): DC25_Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur
	 */
	private function __construct() {
		// Ajouter le filtre seulement si WC_Settings_Page existe
		if ( class_exists( 'WC_Settings_Page' ) ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'add_settings_page' ] );
		} else {
			// Sinon, attendre que WooCommerce soit chargé
			add_action( 'plugins_loaded', [ $this, 'init_settings_page' ], 20 );
		}
	}

	/**
	 * Initialiser la page de réglages après WooCommerce
	 */
	public function init_settings_page(): void {
		if ( class_exists( 'WC_Settings_Page' ) ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'add_settings_page' ] );
		}
	}

	/**
	 * Ajouter la page de réglages
	 *
	 * @param array $settings Pages de réglages.
	 * @return array
	 */
	public function add_settings_page( array $settings ): array {
		if ( class_exists( 'DC25_Settings_Page' ) ) {
			$settings[] = new DC25_Settings_Page();
		}
		return $settings;
	}

	/**
	 * Obtenir une option
	 *
	 * @param string $key Clé de l'option.
	 * @param mixed  $default Valeur par défaut.
	 * @return mixed
	 */
	public function get_option( string $key, $default = '' ) {
		return get_option( 'dc25_gv_' . $key, $default );
	}

	/**
	 * Obtenir le préfixe coupon par défaut
	 *
	 * @return string
	 */
	public function get_coupon_prefix(): string {
		return $this->get_option( 'coupon_prefix', 'GV-' );
	}

	/**
	 * Obtenir la validité par défaut
	 *
	 * @return int
	 */
	public function get_default_validity_days(): int {
		return absint( $this->get_option( 'default_validity_days', 365 ) );
	}

	/**
	 * Obtenir l'URL du logo PDF
	 *
	 * @return string
	 */
	public function get_logo_url(): string {
		return $this->get_option( 'pdf_logo_url', '' );
	}

	/**
	 * Obtenir la couleur thème PDF
	 *
	 * @return string
	 */
	public function get_theme_color(): string {
		return $this->get_option( 'pdf_theme_color', '#000000' );
	}

	/**
	 * Obtenir le texte des conditions
	 *
	 * @return string
	 */
	public function get_conditions_text(): string {
		return $this->get_option( 'conditions_text', __( 'Ce bon cadeau est valable une seule fois et non remboursable.', 'dc25-vouchers' ) );
	}

	/**
	 * Vérifier si l'email destinataire est activé
	 *
	 * @return bool
	 */
	public function is_recipient_email_enabled(): bool {
		return 'yes' === $this->get_option( 'recipient_email_enabled', 'no' );
	}

	/**
	 * Obtenir le sujet de l'email destinataire
	 *
	 * @return string
	 */
	public function get_recipient_email_subject(): string {
		return $this->get_option( 'recipient_email_subject', __( 'Vous avez reçu un bon cadeau !', 'dc25-vouchers' ) );
	}

	/**
	 * Obtenir le contenu de l'email destinataire
	 *
	 * @return string
	 */
	public function get_recipient_email_content(): string {
		return $this->get_option(
			'recipient_email_content',
			__( 'Bonjour {name},<br><br>Vous avez reçu un bon cadeau d\'un montant de {amount}.<br><br>Code: {coupon_code}<br><br>Message: {message}<br><br>Cordialement,<br>{site_name}', 'dc25-vouchers' )
		);
	}

	/**
	 * Obtenir la taille de papier PDF
	 *
	 * @return string
	 */
	public function get_pdf_paper_size(): string {
		return $this->get_option( 'pdf_paper_size', 'a5' );
	}

	/**
	 * Obtenir l'orientation PDF
	 *
	 * @return string
	 */
	public function get_pdf_orientation(): string {
		return $this->get_option( 'pdf_orientation', 'landscape' );
	}
}

/**
 * Page de réglages WooCommerce
 * 
 * Cette classe n'est définie que si WC_Settings_Page existe
 */
if ( class_exists( 'WC_Settings_Page' ) ) {
	class DC25_Settings_Page extends WC_Settings_Page {

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->id    = 'dc25-vouchers';
		$this->label = __( 'Bons cadeaux', 'dc25-vouchers' );

		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_page' ], 20 );
		add_action( 'woocommerce_settings_' . $this->id, [ $this, 'output' ] );
		add_action( 'woocommerce_settings_save_' . $this->id, [ $this, 'save' ] );
	}

	/**
	 * Obtenir les réglages
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return apply_filters( 'woocommerce_dc25_vouchers_settings', [
			[
				'title' => __( 'Réglages généraux', 'dc25-vouchers' ),
				'type'  => 'title',
				'id'    => 'dc25_gv_general',
			],
			[
				'title'    => __( 'Préfixe coupon par défaut', 'dc25-vouchers' ),
				'desc'     => __( 'Préfixe utilisé pour les codes de coupon générés.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_coupon_prefix',
				'default'  => 'GV-',
				'type'     => 'text',
			],
			[
				'title'    => __( 'Validité par défaut (jours)', 'dc25-vouchers' ),
				'desc'     => __( 'Durée de validité par défaut des bons cadeaux en jours.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_default_validity_days',
				'default'  => '365',
				'type'     => 'number',
				'custom_attributes' => [
					'min' => '1',
					'step' => '1',
				],
			],
			[
				'type' => 'sectionend',
				'id'   => 'dc25_gv_general',
			],
			[
				'title' => __( 'Réglages PDF', 'dc25-vouchers' ),
				'type'  => 'title',
				'id'    => 'dc25_gv_pdf',
			],
			[
				'title'    => __( 'Logo PDF', 'dc25-vouchers' ),
				'desc'     => __( 'URL du logo à afficher sur le PDF.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_logo_url',
				'type'     => 'text',
				'default'  => '',
			],
			[
				'title'    => __( 'Couleur thème PDF', 'dc25-vouchers' ),
				'desc'     => __( 'Couleur principale utilisée dans le design du PDF.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_theme_color',
				'type'     => 'color',
				'default'  => '#000000',
			],
			[
				'title'    => __( 'Taille de papier', 'dc25-vouchers' ),
				'desc'     => __( 'Taille de papier pour le PDF.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_paper_size',
				'type'     => 'select',
				'default'  => 'a5',
				'options'  => [
					'a4' => 'A4',
					'a5' => 'A5',
					'letter' => 'Letter',
				],
			],
			[
				'title'    => __( 'Orientation', 'dc25-vouchers' ),
				'desc'     => __( 'Orientation du PDF.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_orientation',
				'type'     => 'select',
				'default'  => 'landscape',
				'options'  => [
					'portrait'  => __( 'Portrait', 'dc25-vouchers' ),
					'landscape' => __( 'Paysage', 'dc25-vouchers' ),
				],
			],
			[
				'title'    => __( 'Texte conditions', 'dc25-vouchers' ),
				'desc'     => __( 'Texte des conditions générales affiché sur le PDF.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_conditions_text',
				'type'     => 'textarea',
				'default'  => __( 'Ce bon cadeau est valable une seule fois et non remboursable.', 'dc25-vouchers' ),
				'css'      => 'min-height: 100px;',
			],
			[
				'type' => 'sectionend',
				'id'   => 'dc25_gv_pdf',
			],
			[
				'title' => __( 'Réglages email', 'dc25-vouchers' ),
				'type'  => 'title',
				'id'    => 'dc25_gv_email',
			],
			[
				'title'    => __( 'Activer email destinataire', 'dc25-vouchers' ),
				'desc'     => __( 'Envoyer un email au destinataire si son adresse est renseignée.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_recipient_email_enabled',
				'type'     => 'checkbox',
				'default'  => 'no',
			],
			[
				'title'    => __( 'Sujet email destinataire', 'dc25-vouchers' ),
				'desc'     => __( 'Placeholders: {name}, {coupon_code}, {amount}, {message}, {site_name}', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_recipient_email_subject',
				'type'     => 'text',
				'default'  => __( 'Vous avez reçu un bon cadeau !', 'dc25-vouchers' ),
			],
			[
				'title'    => __( 'Contenu email destinataire', 'dc25-vouchers' ),
				'desc'     => __( 'Placeholders: {name}, {coupon_code}, {amount}, {message}, {site_name}', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_recipient_email_content',
				'type'     => 'textarea',
				'default'  => __( 'Bonjour {name},<br><br>Vous avez reçu un bon cadeau d\'un montant de {amount}.<br><br>Code: {coupon_code}<br><br>Message: {message}<br><br>Cordialement,<br>{site_name}', 'dc25-vouchers' ),
				'css'      => 'min-height: 200px;',
			],
			[
				'type' => 'sectionend',
				'id'   => 'dc25_gv_email',
			],
		] );
	}
	} // Fin de la classe DC25_Settings_Page
} // Fin de la condition class_exists('WC_Settings_Page')

