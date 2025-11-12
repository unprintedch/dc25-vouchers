<?php
/**
 * Service de génération de QR codes
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Classe pour la gestion des QR codes
 */
class DC25_QR_Service {

	/**
	 * Générer un QR code pour un bon cadeau
	 *
	 * @param string $coupon_code Code du coupon.
	 * @param float  $amount Montant.
	 * @param string $expiry_date Date d'expiration (Y-m-d).
	 * @param int    $size Taille du QR code.
	 * @return string|WP_Error Chemin du fichier ou erreur.
	 */
	public static function generate_qr_code( string $coupon_code, float $amount, string $expiry_date, int $size = 200 ) {
		$site_url = home_url();

		// Données JSON pour le QR code
		$data = [
			'type'        => 'gift_voucher',
			'code'        => $coupon_code,
			'amount'      => $amount,
			'currency'    => get_woocommerce_currency(),
			'expires'     => $expiry_date,
			'verify_url' => add_query_arg( 'dc25_gv_verify', $coupon_code, $site_url ),
		];

		$json_data = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// Créer le répertoire si nécessaire
		$upload_dir = wp_upload_dir();
		$qr_dir = trailingslashit( $upload_dir['basedir'] ) . 'dc25-vouchers/qr-codes';
		if ( ! file_exists( $qr_dir ) ) {
			wp_mkdir_p( $qr_dir );
		}

		// Générer le QR code
		try {
			$qr_code = QrCode::create( $json_data )
				->setSize( $size )
				->setMargin( 10 );

			$writer = new PngWriter();
			$result = $writer->write( $qr_code );

			// Sauvegarder le fichier
			$filename = 'qr-' . sanitize_file_name( $coupon_code ) . '.png';
			$filepath = trailingslashit( $qr_dir ) . $filename;
			$result->saveToFile( $filepath );

			return $filepath;
		} catch ( Exception $e ) {
			return new WP_Error( 'qr_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Obtenir l'URL publique d'un QR code
	 *
	 * @param string $coupon_code Code du coupon.
	 * @return string
	 */
	public static function get_qr_code_url( string $coupon_code ): string {
		$upload_dir = wp_upload_dir();
		$filename = 'qr-' . sanitize_file_name( $coupon_code ) . '.png';
		$filepath = trailingslashit( $upload_dir['basedir'] ) . 'dc25-vouchers/qr-codes/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			return '';
		}

		return trailingslashit( $upload_dir['baseurl'] ) . 'dc25-vouchers/qr-codes/' . $filename;
	}

	/**
	 * Générer un QR code en base64 pour inclusion directe dans le PDF
	 *
	 * @param string $coupon_code Code du coupon.
	 * @param float  $amount Montant.
	 * @param string $expiry_date Date d'expiration.
	 * @param int    $size Taille.
	 * @return string|WP_Error Base64 ou erreur.
	 */
	public static function generate_qr_code_base64( string $coupon_code, float $amount, string $expiry_date, int $size = 200 ) {
		$site_url = home_url();

		$data = [
			'type'        => 'gift_voucher',
			'code'        => $coupon_code,
			'amount'      => $amount,
			'currency'    => get_woocommerce_currency(),
			'expires'     => $expiry_date,
			'verify_url'  => add_query_arg( 'dc25_gv_verify', $coupon_code, $site_url ),
		];

		$json_data = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		try {
			$qr_code = QrCode::create( $json_data )
				->setSize( $size )
				->setMargin( 10 );

			$writer = new PngWriter();
			$result = $writer->write( $qr_code );

			// Convertir en base64
			$data_uri = $result->getDataUri();

			return $data_uri;
		} catch ( Exception $e ) {
			return new WP_Error( 'qr_generation_failed', $e->getMessage() );
		}
	}
}

