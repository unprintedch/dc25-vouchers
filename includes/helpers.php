<?php
/**
 * Fonctions helper
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vérifier si un produit est un bon cadeau
 *
 * @param WC_Product|int $product Produit ou ID.
 * @return bool
 */
function dc25_is_gift_voucher( $product ): bool {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( $product );
	}

	if ( ! $product ) {
		return false;
	}

	return 'gift_voucher' === $product->get_type();
}

/**
 * Obtenir les réglages du plugin
 *
 * @return DC25_Settings
 */
function dc25_get_settings(): DC25_Settings {
	return DC25_Settings::get_instance();
}

/**
 * Fonction helper pour afficher les champs produit (appelable depuis le template)
 */
function dc25_display_product_fields(): void {
	if ( ! class_exists( 'DC25_Checkout_Fields' ) ) {
		return;
	}

	global $product;
	if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
		return;
	}

	// Créer une instance temporaire juste pour appeler la méthode
	$instance = new DC25_Checkout_Fields();
	if ( method_exists( $instance, 'add_product_page_fields' ) ) {
		$instance->add_product_page_fields( true ); // Force l'affichage
	}
}

