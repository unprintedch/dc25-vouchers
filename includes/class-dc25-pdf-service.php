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
	 * Générer un PDF pour un bon cadeau
	 *
	 * @param array $data Données du bon cadeau.
	 * @return string|WP_Error Chemin du fichier PDF ou erreur.
	 */
	public static function generate_pdf( array $data ) {
		$settings = DC25_Settings::get_instance();

		// Données requises
		$required = [ 'coupon_code', 'amount', 'expiry_date' ];
		foreach ( $required as $key ) {
			if ( empty( $data[ $key ] ) ) {
				return new WP_Error( 'missing_data', sprintf( __( 'Donnée manquante: %s', 'dc25-vouchers' ), $key ) );
			}
		}

		// Créer le répertoire si nécessaire
		$upload_dir = wp_upload_dir();
		$year = date( 'Y' );
		$month = date( 'm' );
		$pdf_dir = trailingslashit( $upload_dir['basedir'] ) . "dc25-vouchers/{$year}/{$month}";
		if ( ! file_exists( $pdf_dir ) ) {
			wp_mkdir_p( $pdf_dir );
		}

		// Générer le QR code en base64
		$qr_code = DC25_QR_Service::generate_qr_code_base64(
			$data['coupon_code'],
			$data['amount'],
			$data['expiry_date'],
			200
		);

		if ( is_wp_error( $qr_code ) ) {
			return $qr_code;
		}

		// Préparer les données pour le template
		$template_data = [
			'coupon_code'   => $data['coupon_code'],
			'amount'        => $data['amount'],
			'currency'      => get_woocommerce_currency(),
			'expiry_date'   => $data['expiry_date'],
			'message'       => $data['message'] ?? '',
			'recipient_name' => $data['recipient_name'] ?? '',
			'qr_code'       => $qr_code,
			'logo_url'      => $settings->get_logo_url(),
			'theme_color'   => $settings->get_theme_color(),
			'conditions'    => $settings->get_conditions_text(),
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

			// Format A5 paysage
			$paper_size = $settings->get_pdf_paper_size();
			$paper_orientation = $settings->get_pdf_orientation();
			$dompdf->setPaper( $paper_size, $paper_orientation );

			$dompdf->render();

			// Sauvegarder le fichier
			$filename = 'voucher-' . sanitize_file_name( $data['coupon_code'] ) . '.pdf';
			$filepath = trailingslashit( $pdf_dir ) . $filename;
			file_put_contents( $filepath, $dompdf->output() );

			return $filepath;
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
	 * Obtenir l'URL publique d'un PDF
	 *
	 * @param string $filepath Chemin du fichier.
	 * @return string
	 */
	public static function get_pdf_url( string $filepath ): string {
		$upload_dir = wp_upload_dir();
		$relative_path = str_replace( $upload_dir['basedir'], '', $filepath );
		return trailingslashit( $upload_dir['baseurl'] ) . ltrim( $relative_path, '/' );
	}
}

