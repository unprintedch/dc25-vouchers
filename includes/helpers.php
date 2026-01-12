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
	if ( ! class_exists( 'DC25_Single_Product_Fields' ) ) {
		return;
	}

	global $product;
	if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
		return;
	}

	// Créer une instance temporaire juste pour appeler la méthode
	$instance = new DC25_Single_Product_Fields();
	if ( method_exists( $instance, 'add_product_page_fields' ) ) {
		$instance->add_product_page_fields();
	}
}

/**
 * Fonction helper pour traduire avec WPML si disponible
 *
 * @param string $string Chaîne à traduire.
 * @param string $context Contexte (text domain).
 * @param string $name Nom de la chaîne pour WPML.
 * @return string
 */
function dc25_translate_string( string $string, string $context = 'dc25-vouchers', string $name = '' ): string {
	// Si WPML est disponible, utiliser sa fonction de traduction
	if ( function_exists( 'wpml_translate_string' ) ) {
		if ( empty( $name ) ) {
			$name = sanitize_key( str_replace( [ ' ', '%', '$', '(', ')', '.', ',', ':', '💝' ], [ '_', '', '', '', '', '', '', '', '' ], $string ) );
		}
		return wpml_translate_string( $string, $name, $context );
	} elseif ( function_exists( 'icl_t' ) ) {
		if ( empty( $name ) ) {
			$name = sanitize_key( str_replace( [ ' ', '%', '$', '(', ')', '.', ',', ':', '💝' ], [ '_', '', '', '', '', '', '', '', '' ], $string ) );
		}
		return icl_t( $context, $name, $string );
	}
	
	// Sinon, utiliser la fonction WordPress standard
	return __( $string, $context );
}

/**
 * Récupérer la langue de la commande WooCommerce
 *
 * @param WC_Order $order Commande WooCommerce.
 * @return string Code de langue (ex: 'fr', 'de').
 */
function dc25_get_order_language( $order ): string {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		// Langue par défaut si pas de commande
		$default_language = 'fr';
		if ( function_exists( 'wpml_get_default_language' ) ) {
			$default_language = wpml_get_default_language();
		} elseif ( function_exists( 'icl_get_default_language' ) ) {
			$default_language = icl_get_default_language();
		}
		return $default_language;
	}

	$order_id = $order->get_id();
	$language = 'fr';

	// Méthode 1: Via wpml_get_language_for_element (WPML moderne)
	if ( function_exists( 'wpml_get_language_for_element' ) ) {
		$language = wpml_get_language_for_element( $order_id, 'post_shop_order' );
		if ( $language ) {
			return $language;
		}
	}

	// Méthode 2: Via les métadonnées de la commande
	$wpml_language = $order->get_meta( 'wpml_language' );
	if ( ! empty( $wpml_language ) ) {
		return $wpml_language;
	}

	// Méthode 3: Via _wpml_language (autre format possible)
	$wpml_language_alt = $order->get_meta( '_wpml_language' );
	if ( ! empty( $wpml_language_alt ) ) {
		return $wpml_language_alt;
	}

	// Méthode 4: Via wpml_order_language (format WooCommerce Multilingual)
	$order_language = $order->get_meta( 'wpml_order_language' );
	if ( ! empty( $order_language ) ) {
		return $order_language;
	}

	// Méthode 5: Via get_post_meta directement (fallback)
	$post_language = get_post_meta( $order_id, 'wpml_language', true );
	if ( ! empty( $post_language ) ) {
		return $post_language;
	}

	// Fallback: Langue par défaut
	$default_language = 'fr';
	if ( function_exists( 'wpml_get_default_language' ) ) {
		$default_language = wpml_get_default_language();
	} elseif ( function_exists( 'icl_get_default_language' ) ) {
		$default_language = icl_get_default_language();
	}

	return $default_language;
}

