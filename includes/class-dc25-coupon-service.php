<?php
/**
 * Service de génération de coupons WooCommerce
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour la gestion des coupons
 */
class DC25_Coupon_Service {

	/**
	 * Générer un coupon WooCommerce
	 *
	 * @param float  $amount Montant du coupon.
	 * @param string $prefix Préfixe du code.
	 * @param int    $validity_days Durée de validité en jours.
	 * @return WC_Coupon|WP_Error
	 */
	public static function create_coupon( float $amount, string $prefix = 'GV-', int $validity_days = 365 ) {
		// Générer un code unique
		$code = self::generate_unique_code( $prefix );

		// Créer le coupon
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( $amount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_limit_usage_to_x_items( null );

		// Date d'expiration
		$expiry_date = date( 'Y-m-d', strtotime( "+{$validity_days} days" ) );
		$coupon->set_date_expires( strtotime( $expiry_date ) );

		// Métadonnées
		$coupon->update_meta_data( '_dc25_gift_voucher', 'yes' );
		$coupon->update_meta_data( '_dc25_original_amount', $amount );

		// Sauvegarder
		$coupon_id = $coupon->save();

		if ( is_wp_error( $coupon_id ) ) {
			return $coupon_id;
		}

		return $coupon;
	}

	/**
	 * Générer un code unique
	 *
	 * @param string $prefix Préfixe.
	 * @return string
	 */
	private static function generate_unique_code( string $prefix ): string {
		$max_attempts = 100;
		$attempt = 0;

		do {
			$random = strtoupper( wp_generate_password( 8, false, false ) );
			$code = $prefix . $random;
			$attempt++;

			// Vérifier l'unicité
			$coupon = new WC_Coupon( $code );
			if ( ! $coupon->get_id() ) {
				return $code;
			}
		} while ( $attempt < $max_attempts );

		// Fallback avec timestamp si échec
		return $prefix . strtoupper( wp_generate_password( 6, false, false ) ) . time();
	}

	/**
	 * Obtenir le statut d'un coupon
	 *
	 * @param string $coupon_code Code du coupon.
	 * @return array Statut avec 'valid', 'expired', 'used'.
	 */
	public static function get_coupon_status( string $coupon_code ): array {
		$coupon = new WC_Coupon( $coupon_code );

		if ( ! $coupon->get_id() ) {
			return [
				'status' => 'invalid',
				'message' => __( 'Coupon invalide', 'dc25-vouchers' ),
			];
		}

		// Vérifier expiration
		$expiry_date = $coupon->get_date_expires();
		if ( $expiry_date && $expiry_date->getTimestamp() < time() ) {
			return [
				'status' => 'expired',
				'message' => __( 'Coupon expiré', 'dc25-vouchers' ),
				'expiry_date' => $expiry_date->date( 'Y-m-d' ),
			];
		}

		// Vérifier usage
		$usage_count = $coupon->get_usage_count();
		$usage_limit = $coupon->get_usage_limit();

		if ( $usage_limit > 0 && $usage_count >= $usage_limit ) {
			return [
				'status' => 'used',
				'message' => __( 'Coupon déjà utilisé', 'dc25-vouchers' ),
				'usage_count' => $usage_count,
			];
		}

		// Valide
		return [
			'status' => 'valid',
			'message' => __( 'Coupon valide', 'dc25-vouchers' ),
			'amount' => $coupon->get_amount(),
			'expiry_date' => $expiry_date ? $expiry_date->date( 'Y-m-d' ) : null,
		];
	}

	/**
	 * Invalider un coupon (annulation de commande)
	 *
	 * @param string $coupon_code Code du coupon.
	 * @return bool
	 */
	public static function invalidate_coupon( string $coupon_code ): bool {
		$coupon = new WC_Coupon( $coupon_code );

		if ( ! $coupon->get_id() ) {
			return false;
		}

		// Marquer comme utilisé pour l'invalider
		$coupon->set_usage_count( $coupon->get_usage_limit() );
		$coupon->save();

		return true;
	}
}

