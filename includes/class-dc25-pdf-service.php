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

		// Date de génération du PDF : utiliser la date de création du coupon si disponible, sinon la date actuelle
		$generation_timestamp = null;
		if ( ! empty( $data['coupon_code'] ) && function_exists( 'wc_get_coupon_id_by_code' ) ) {
			$coupon_id = wc_get_coupon_id_by_code( $data['coupon_code'] );
			if ( $coupon_id ) {
				$coupon = new WC_Coupon( $coupon_id );
				$date_created = $coupon->get_date_created();
				if ( $date_created ) {
					$generation_timestamp = $date_created->getTimestamp();
				}
			}
		}
		// Si pas de date de création trouvée, utiliser la date actuelle
		if ( ! $generation_timestamp ) {
			$generation_timestamp = current_time( 'timestamp' );
		}
		// Formater la date (sera reformatée dans le template)
		$generation_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $generation_timestamp );

		// Convertir le logo en base64 pour éviter les problèmes de chargement DomPDF
		$logo_url = $settings->get_logo_url();
		$logo_base64 = '';
		if ( ! empty( $logo_url ) ) {
			$logo_base64 = self::image_to_base64( $logo_url );
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
			'verify_url'   => $verify_url,
			'generation_date' => $generation_date,
			'generation_timestamp' => $generation_timestamp,
			'logo_url'      => $logo_base64 ?: $logo_url,
			'logo_base64'   => $logo_base64,
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
			$options->set( 'isPhpEnabled', false );

			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $html, get_bloginfo( 'charset' ) );

			// Format configuré - A4 portrait avec recto et verso sur une seule page
			// Les marges sont définies dans le CSS @page du template
			$paper_size = 'A4';
			$orientation = 'portrait';

			$dompdf->setPaper( $paper_size, $orientation );

			$dompdf->render();

			// Forcer le nombre de pages - éviter les pages supplémentaires
			$canvas = $dompdf->getCanvas();
			$pages = $canvas->get_page_count();

			// Retourner le contenu du PDF (sans sauvegarder)
			return $dompdf->output();
		} catch ( Exception $e ) {
			return new WP_Error( 'pdf_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Charger le template pour prévisualisation HTML (méthode publique)
	 *
	 * @param array $data Données du bon cadeau.
	 * @return string|WP_Error HTML ou erreur.
	 */
	public static function load_template_for_preview( array $data ) {
		$settings = DC25_Settings::get_instance();

		// Générer le QR code en base64
		$qr_code = DC25_QR_Service::generate_qr_code_base64(
			$data['coupon_code'],
			$data['amount'],
			$data['expiry_date'],
			200
		);

		if ( is_wp_error( $qr_code ) ) {
			$qr_code = '';
		}

		// Générer l'URL de vérification
		$verify_url = add_query_arg( 'dc25_gv_verify', $data['coupon_code'], home_url() );

		// Date de génération (pour preview, utiliser la date actuelle)
		$generation_timestamp = current_time( 'timestamp' );
		$generation_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $generation_timestamp );

		// Convertir le logo en base64
		$logo_url = $settings->get_logo_url();
		$logo_base64 = '';
		if ( ! empty( $logo_url ) ) {
			$logo_base64 = self::image_to_base64( $logo_url );
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
			'verify_url'   => $verify_url,
			'generation_date' => $generation_date,
			'generation_timestamp' => $generation_timestamp,
			'logo_url'      => $logo_base64 ?: $logo_url,
			'logo_base64'   => $logo_base64,
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
			'is_preview'    => true,
		];

		return self::load_template( $template_data );
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
	 * Convertir une image en base64 pour inclusion dans le PDF
	 *
	 * @param string $url URL de l'image.
	 * @return string Data URI base64 ou chaîne vide en cas d'erreur.
	 */
	private static function image_to_base64( string $url ): string {
		if ( empty( $url ) ) {
			return '';
		}

		try {
			// Si c'est déjà une data URI, la retourner telle quelle
			if ( strpos( $url, 'data:image' ) === 0 ) {
				return $url;
			}

			// Convertir l'URL en chemin de fichier local si possible
			$file_path = '';
			
			// Si c'est une URL WordPress (wp-content/uploads)
			if ( function_exists( 'content_url' ) && strpos( $url, content_url() ) !== false ) {
				$file_path = str_replace( content_url(), WP_CONTENT_DIR, $url );
			} elseif ( function_exists( 'site_url' ) && strpos( $url, site_url() ) !== false ) {
				// URL relative au site
				$file_path = str_replace( site_url(), ABSPATH, $url );
			} elseif ( ! preg_match( '/^https?:\/\//', $url ) ) {
				// URL relative, essayer depuis la racine WordPress
				$file_path = ABSPATH . ltrim( $url, '/' );
			}

			// Essayer de lire le fichier local
			if ( ! empty( $file_path ) && file_exists( $file_path ) && is_readable( $file_path ) ) {
				$image_data = @file_get_contents( $file_path );
				if ( $image_data !== false && ! empty( $image_data ) ) {
					// Déterminer le type MIME
					$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
					$mime = 'image/png'; // Par défaut
					if ( in_array( $ext, [ 'jpg', 'jpeg' ], true ) ) {
						$mime = 'image/jpeg';
					} elseif ( $ext === 'png' ) {
						$mime = 'image/png';
					} elseif ( $ext === 'gif' ) {
						$mime = 'image/gif';
					}
					return 'data:' . $mime . ';base64,' . base64_encode( $image_data );
				}
			}

			// Si fichier local échoue, essayer de télécharger depuis l'URL
			if ( function_exists( 'wp_remote_get' ) ) {
				$response = @wp_remote_get( $url, [
					'timeout' => 10,
					'sslverify' => false,
				] );

				if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
					$image_data = wp_remote_retrieve_body( $response );
					if ( ! empty( $image_data ) ) {
						$mime_type = wp_remote_retrieve_header( $response, 'content-type' );
						if ( empty( $mime_type ) || strpos( $mime_type, 'image/' ) !== 0 ) {
							// Deviner le type depuis l'extension
							$parsed_url = parse_url( $url, PHP_URL_PATH );
							$ext = $parsed_url ? strtolower( pathinfo( $parsed_url, PATHINFO_EXTENSION ) ) : '';
							$mime_type = 'image/png';
							if ( in_array( $ext, [ 'jpg', 'jpeg' ], true ) ) {
								$mime_type = 'image/jpeg';
							} elseif ( $ext === 'png' ) {
								$mime_type = 'image/png';
							} elseif ( $ext === 'gif' ) {
								$mime_type = 'image/gif';
							}
						}
						return 'data:' . $mime_type . ';base64,' . base64_encode( $image_data );
					}
				}
			}
		} catch ( Exception $e ) {
			// En cas d'erreur, retourner vide pour utiliser l'URL originale
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning(
					sprintf( 'Erreur conversion logo base64: %s', $e->getMessage() ),
					[ 'source' => 'dc25-vouchers' ]
				);
			}
		}

		// En cas d'échec, retourner vide (le template utilisera l'URL originale)
		return '';
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

