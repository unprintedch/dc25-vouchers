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
		return $this->get_option( 'coupon_prefix', 'NVT-' );
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
	 * Obtenir la couleur d'accent du PDF
	 */
	public function get_accent_color(): string {
		return $this->get_option( 'pdf_accent_color', '#c49b3f' );
	}

	/**
	 * Obtenir la couleur de texte du PDF
	 */
	public function get_text_color(): string {
		return $this->get_option( 'pdf_text_color', '#111111' );
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
			__( 'Bonjour {name},<br><br>Vous avez reçu un bon cadeau d\'un montant de {amount}.<br><br>Code: {coupon_code}<br><br>Message: {message}<br><br>Le PDF de votre bon cadeau est joint à cet email.<br><br>{download_link}<br><br>Cordialement,<br>{site_name}', 'dc25-vouchers' )
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

	/**
	 * Obtenir le fond du PDF
	 */
	public function get_pdf_background_url(): string {
		return $this->get_option( 'pdf_background_url', '' );
	}

	/**
	 * Obtenir la famille de police
	 */
	public function get_pdf_font_family(): string {
		return $this->get_option( 'pdf_font_family', 'sans' );
	}

	/**
	 * Obtenir le preset de mise en page
	 */
	public function get_pdf_layout_preset(): string {
		return $this->get_option( 'pdf_layout_preset', 'classic' );
	}

	/**
	 * Obtenir le titre du PDF
	 */
	public function get_pdf_title_text(): string {
		return $this->get_option( 'pdf_title_text', __( 'Bon cadeau', 'dc25-vouchers' ) );
	}

	/**
	 * Obtenir le sous-titre du PDF
	 */
	public function get_pdf_subtitle_text(): string {
		return $this->get_option( 'pdf_subtitle_text', __( 'Pour célébrer une occasion spéciale', 'dc25-vouchers' ) );
	}

	/**
	 * Obtenir le texte de pied de page
	 */
	public function get_pdf_footer_text(): string {
		return $this->get_option( 'pdf_footer_text', __( 'Présentez ce bon lors de votre visite pour le valider.', 'dc25-vouchers' ) );
	}

	public function get_pdf_from_label(): string {
		return $this->get_option( 'pdf_from_label', __( 'De la part de', 'dc25-vouchers' ) );
	}

	public function get_pdf_for_label(): string {
		return $this->get_option( 'pdf_for_label', __( 'à', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_title(): string {
		return $this->get_option( 'pdf_back_title', __( 'Comment utiliser ce bon?', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_partner_title(): string {
		return $this->get_option( 'pdf_back_partner_title', __( 'Chez nos partenaires', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_partner_body(): string {
		return $this->get_option( 'pdf_back_partner_body', __( 'Ce bon est valable chez tous les encaveurs, producteurs et partenaires NVT.', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_partner_link_label(): string {
		return $this->get_option( 'pdf_back_partner_link_label', 'neuchatel-vins-terroir.ch/bon-cadeau-du-terroir/' );
	}

	public function get_pdf_back_partner_link_url(): string {
		return $this->get_option( 'pdf_back_partner_link_url', 'https://neuchatel-vins-terroir.ch/bon-cadeau-du-terroir/' );
	}

	public function get_pdf_back_online_title(): string {
		return $this->get_option( 'pdf_back_online_title', __( 'En ligne', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_online_body(): string {
		return $this->get_option( 'pdf_back_online_body', __( 'Sur notre site', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_online_link_label(): string {
		return $this->get_option( 'pdf_back_online_link_label', 'neuchatel-vins-terroir.ch' );
	}

	public function get_pdf_back_online_link_url(): string {
		return $this->get_option( 'pdf_back_online_link_url', 'https://neuchatel-vins-terroir.ch' );
	}

	public function get_pdf_back_online_code_label(): string {
		return $this->get_option( 'pdf_back_online_code_label', __( 'Avec le code', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_validity_notice(): string {
		return $this->get_option( 'pdf_back_validity_notice', __( 'Valable 12 mois dès la date d\'émission, non cumulable avec d’autres bons. Ne peut être échangé contre espèces.', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_banner_title(): string {
		return $this->get_option( 'pdf_back_banner_title', __( 'Partenaire', 'dc25-vouchers' ) );
	}

	public function get_pdf_back_banner_text(): string {
		return $this->get_option( 'pdf_back_banner_text', __( 'Scanner ce QR code afin de valider l’utilisation du bon et transmettre le ticket pour remboursement', 'dc25-vouchers' ) );
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
				'default'  => 'NVT-',
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
				'title'    => __( 'Couleur d\'accent', 'dc25-vouchers' ),
				'desc'     => __( 'Utilisée pour les séparateurs/boutons.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_accent_color',
				'type'     => 'color',
				'default'  => '#c49b3f',
			],
			[
				'title'    => __( 'Couleur du texte', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_text_color',
				'type'     => 'color',
				'default'  => '#111111',
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
				'title'    => __( 'Fond (URL ou image)', 'dc25-vouchers' ),
				'desc'     => __( 'URL d\'une image de fond pleine page.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_background_url',
				'type'     => 'text',
				'default'  => '',
			],
			[
				'title'    => __( 'Police', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_font_family',
				'type'     => 'select',
				'default'  => 'sans',
				'options'  => [
					'sans'  => __( 'Sans-serif moderne', 'dc25-vouchers' ),
					'serif' => __( 'Serif élégant', 'dc25-vouchers' ),
				],
			],
			[
				'title'    => __( 'Preset de mise en page', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_layout_preset',
				'type'     => 'select',
				'default'  => 'classic',
				'options'  => [
					'classic'  => __( 'Classique', 'dc25-vouchers' ),
					'bordered' => __( 'Encadré', 'dc25-vouchers' ),
					'photo'    => __( 'Carte photo', 'dc25-vouchers' ),
				],
			],
			[
				'title'    => __( 'Titre', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_title_text',
				'type'     => 'text',
				'default'  => __( 'Bon cadeau', 'dc25-vouchers' ),
			],
			[
				'title'    => __( 'Sous-titre', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_subtitle_text',
				'type'     => 'text',
				'default'  => __( 'Pour célébrer une occasion spéciale', 'dc25-vouchers' ),
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
				'title'    => __( 'Pied de page', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_pdf_footer_text',
				'type'     => 'text',
				'default'  => __( 'Présentez ce bon lors de votre visite pour le valider.', 'dc25-vouchers' ),
			],
			[
				'title' => __( 'Libellés face avant', 'dc25-vouchers' ),
				'type'  => 'title',
				'id'    => 'dc25_gv_pdf_front_labels',
			],
			[
				'title'   => __( 'Libellé expéditeur', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_from_label',
				'type'    => 'text',
				'default' => __( 'De la part de', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Libellé destinataire', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_for_label',
				'type'    => 'text',
				'default' => __( 'à', 'dc25-vouchers' ),
			],
			[
				'type' => 'sectionend',
				'id'   => 'dc25_gv_pdf_front_labels',
			],
			[
				'title' => __( 'Face arrière', 'dc25-vouchers' ),
				'type'  => 'title',
				'id'    => 'dc25_gv_pdf_back',
			],
			[
				'title'   => __( 'Titre', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_title',
				'type'    => 'text',
				'default' => __( 'Comment utiliser ce bon?', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Bloc partenaires - titre', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_partner_title',
				'type'    => 'text',
				'default' => __( 'Chez nos partenaires', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Bloc partenaires - texte', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_partner_body',
				'type'    => 'textarea',
				'default' => __( 'Ce bon est valable chez tous les encaveurs, producteurs et partenaires NVT.', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Bloc partenaires - lien (libellé)', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_partner_link_label',
				'type'    => 'text',
				'default' => 'neuchatel-vins-terroir.ch/bon-cadeau-du-terroir/',
			],
			[
				'title'   => __( 'Bloc partenaires - lien (URL)', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_partner_link_url',
				'type'    => 'text',
				'default' => 'https://neuchatel-vins-terroir.ch/bon-cadeau-du-terroir/',
			],
			[
				'title'   => __( 'Bloc en ligne - titre', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_online_title',
				'type'    => 'text',
				'default' => __( 'En ligne', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Bloc en ligne - texte', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_online_body',
				'type'    => 'textarea',
				'default' => __( 'Sur notre site', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Bloc en ligne - lien (libellé)', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_online_link_label',
				'type'    => 'text',
				'default' => 'neuchatel-vins-terroir.ch',
			],
			[
				'title'   => __( 'Bloc en ligne - lien (URL)', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_online_link_url',
				'type'    => 'text',
				'default' => 'https://neuchatel-vins-terroir.ch',
			],
			[
				'title'   => __( 'Bloc en ligne - libellé code', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_online_code_label',
				'type'    => 'text',
				'default' => __( 'Avec le code', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Mention de validité', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_validity_notice',
				'type'    => 'text',
				'default' => __( 'Valable 12 mois dès la date d\'émission, non cumulable avec d’autres bons. Ne peut être échangé contre espèces.', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Bandeau QR - titre', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_banner_title',
				'type'    => 'text',
				'default' => __( 'Partenaire', 'dc25-vouchers' ),
			],
			[
				'title'   => __( 'Bandeau QR - texte', 'dc25-vouchers' ),
				'id'      => 'dc25_gv_pdf_back_banner_text',
				'type'    => 'textarea',
				'default' => __( 'Scanner ce QR code afin de valider l’utilisation du bon et transmettre le ticket pour remboursement', 'dc25-vouchers' ),
			],
			[
				'type' => 'sectionend',
				'id'   => 'dc25_gv_pdf_back',
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
				'desc'     => __( 'Placeholders: {name}, {coupon_code}, {amount}, {message}, {site_name}, {download_url}', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_recipient_email_subject',
				'type'     => 'text',
				'default'  => __( 'Vous avez reçu un bon cadeau !', 'dc25-vouchers' ),
			],
			[
				'title'    => __( 'Contenu email destinataire', 'dc25-vouchers' ),
				'desc'     => __( 'Placeholders: {name}, {coupon_code}, {amount}, {message}, {site_name}, {download_link} (bouton HTML), {download_url} (URL seule). Le PDF est aussi joint à l\'email.', 'dc25-vouchers' ),
				'id'       => 'dc25_gv_recipient_email_content',
				'type'     => 'textarea',
				'default'  => __( 'Bonjour {name},<br><br>Vous avez reçu un bon cadeau d\'un montant de {amount}.<br><br>Code: {coupon_code}<br><br>Message: {message}<br><br>Le PDF de votre bon cadeau est joint à cet email.<br><br>{download_link}<br><br>Cordialement,<br>{site_name}', 'dc25-vouchers' ),
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

