<?php
/**
 * Service de génération de PDF
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Classe pour la génération de PDF
 */
class DC25_PDF_Service {

	/**
	 * Générer le contenu PDF en mémoire (sans sauvegarder)
	 *
	 * @param array $data Données du bon cadeau.
	 * @param bool  $is_preview Indique s'il s'agit d'un aperçu générique.
	 * @return string|WP_Error Contenu du PDF ou erreur.
	 */
	public static function generate_pdf_content( array $data, bool $is_preview = false ) {
		$settings = DC25_Settings::get_instance();

		// Données requises
		$required = [ 'coupon_code', 'amount', 'expiry_date' ];
		if ( ! $is_preview ) {
			foreach ( $required as $key ) {
				if ( empty( $data[ $key ] ) ) {
					return new WP_Error( 'missing_data', sprintf( __( 'Donnée manquante: %s', 'dc25-vouchers' ), $key ) );
				}
			}
		} else {
			foreach ( $required as $key ) {
				if ( empty( $data[ $key ] ) ) {
					$data[ $key ] = $key === 'amount' ? 100 : __( 'EXEMPLE', 'dc25-vouchers' );
				}
			}
		}

		// Générer le QR code en base64
		$qr_code = DC25_QR_Service::generate_qr_code_base64(
			$data['coupon_code'],
			$data['amount'],
			$data['expiry_date'],
			200
		);

		// Si erreur, logger mais continuer sans QR code
		if ( is_wp_error( $qr_code ) ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning(
					sprintf( 'Erreur génération QR code: %s', $qr_code->get_error_message() ),
					[ 'source' => 'dc25-vouchers' ]
				);
			}
			$qr_code = ''; // Continuer sans QR code plutôt que d'échouer
		}

		// Générer l'URL de vérification
		$verify_url = add_query_arg( 'dc25_gv_verify', $data['coupon_code'], home_url() );

		// Date de génération du PDF (utiliser current_time pour respecter le fuseau horaire WordPress)
		$generation_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) );

		// Préparer les données pour le template
		$template_data = [
			'coupon_code'   => $data['coupon_code'],
			'amount'        => $data['amount'],
			'currency'      => get_woocommerce_currency(),
			'expiry_date'   => $data['expiry_date'],
			'message'       => $data['message'] ?? '',
			'recipient_name' => $data['recipient_name'] ?? '',
			'qr_code'       => $qr_code,
			'verify_url'   => $verify_url,
			'generation_date' => $generation_date,
			'logo_url'      => $settings->get_logo_url(),
			'theme_color'   => $settings->get_theme_color(),
			'accent_color'  => $settings->get_accent_color(),
			'text_color'    => $settings->get_text_color(),
			'conditions'    => $settings->get_conditions_text(),
			'background_url' => $settings->get_pdf_background_url(),
			'font_family'   => $settings->get_pdf_font_family(),
			'layout_preset' => $settings->get_pdf_layout_preset(),
			'title_text'    => $settings->get_pdf_title_text(),
			'subtitle_text' => $settings->get_pdf_subtitle_text(),
			'footer_text'   => $settings->get_pdf_footer_text(),
			'from_label'    => $settings->get_pdf_from_label(),
			'for_label'     => $settings->get_pdf_for_label(),
			'from_name'     => $data['from_name'] ?? '',
			'back_title'    => $settings->get_pdf_back_title(),
			'back_partner_title' => $settings->get_pdf_back_partner_title(),
			'back_partner_body'  => $settings->get_pdf_back_partner_body(),
			'back_partner_link_label' => $settings->get_pdf_back_partner_link_label(),
			'back_partner_link_url'   => $settings->get_pdf_back_partner_link_url(),
			'back_online_title'       => $settings->get_pdf_back_online_title(),
			'back_online_body'        => $settings->get_pdf_back_online_body(),
			'back_online_link_label'  => $settings->get_pdf_back_online_link_label(),
			'back_online_link_url'    => $settings->get_pdf_back_online_link_url(),
			'back_online_code_label'  => $settings->get_pdf_back_online_code_label(),
			'back_validity_notice'    => $settings->get_pdf_back_validity_notice(),
			'back_banner_title'       => $settings->get_pdf_back_banner_title(),
			'back_banner_text'        => $settings->get_pdf_back_banner_text(),
			'is_preview'    => $is_preview,
		];

		// Charger le template
		$html = self::load_template( $template_data );

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		// Générer le PDF
		try {
			$options = new Options();
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'defaultFont', 'DejaVu Sans' );

			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $html, get_bloginfo( 'charset' ) );

			// Format configuré
			$paper_size = strtoupper( $settings->get_pdf_paper_size() );
			$orientation = $settings->get_pdf_orientation();

			$dompdf->setPaper( $paper_size, $orientation );

			$dompdf->render();

			// Retourner le contenu du PDF (sans sauvegarder)
			return $dompdf->output();
		} catch ( Exception $e ) {
			return new WP_Error( 'pdf_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Charger le template PDF
	 *
	 * @param array $data Données pour le template.
	 * @return string|WP_Error HTML ou erreur.
	 */
	private static function load_template( array $data ): string {
		// Chercher le template dans le thème
		$theme_template = locate_template( 'dc25-vouchers/voucher-pdf.php' );

		if ( $theme_template ) {
			$template_path = $theme_template;
		} else {
			// Template par défaut du plugin
			$template_path = DC25_PATH . 'templates/voucher-pdf.php';
		}

		if ( ! file_exists( $template_path ) ) {
			return new WP_Error( 'template_not_found', __( 'Template PDF introuvable', 'dc25-vouchers' ) );
		}

		// Extraire les variables pour le template
		extract( $data, EXTR_SKIP );

		ob_start();
		include $template_path;
		$html = ob_get_clean();

		return $html;
	}

	/**
	 * Générer un PDF pour un bon cadeau (méthode de compatibilité - dépréciée)
	 * 
	 * @deprecated Utiliser generate_pdf_content() à la place
	 * @param array $data Données du bon cadeau.
	 * @return string|WP_Error Contenu du PDF ou erreur.
	 */
	public static function generate_pdf( array $data ) {
		return self::generate_pdf_content( $data, false );
	}
}

