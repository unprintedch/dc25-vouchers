<?php
/**
 * Endpoint public de vérification des bons cadeaux
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour l'endpoint de vérification
 */
class DC25_Verify_Endpoint {

	/**
	 * Constructeur
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_verify_request' ], 1 ); // Priorité haute pour intercepter avant le template
		add_action( 'template_redirect', [ $this, 'handle_download_pdf' ], 1 ); // Endpoint public pour télécharger le PDF
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_dc25_redeem_coupon', [ $this, 'handle_ajax_redeem' ] );
		add_action( 'wp_ajax_nopriv_dc25_redeem_coupon', [ $this, 'handle_ajax_redeem' ] ); // Public endpoint
		add_action( 'wp_ajax_dc25_search_cpt', [ $this, 'handle_ajax_search_cpt' ] );
		add_action( 'wp_ajax_nopriv_dc25_search_cpt', [ $this, 'handle_ajax_search_cpt' ] ); // Public endpoint
	}

	/**
	 * Ajouter la règle de réécriture
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^dc25-voucher-verify/?$',
			'index.php?dc25_gv_verify=1',
			'top'
		);
		add_rewrite_rule(
			'^dc25-voucher-download/?$',
			'index.php?dc25_gv_download=1',
			'top'
		);
	}

	/**
	 * Ajouter les variables de requête
	 *
	 * @param array $vars Variables existantes.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'dc25_gv_verify';
		$vars[] = 'dc25_gv_download';
		return $vars;
	}

	/**
	 * Gérer la requête de vérification (endpoint public, pas d'authentification requise)
	 */
	public function handle_verify_request(): void {
		// Vérifier d'abord via paramètre GET direct (plus fiable)
		$verify = '';
		if ( isset( $_GET['dc25_gv_verify'] ) && ! empty( $_GET['dc25_gv_verify'] ) ) {
			$verify = sanitize_text_field( wp_unslash( $_GET['dc25_gv_verify'] ) );
		} elseif ( get_query_var( 'dc25_gv_verify' ) ) {
			$verify = get_query_var( 'dc25_gv_verify' );
		}

		// Si pas de code ou code invalide, ne rien faire (laisser WordPress gérer normalement)
		if ( empty( $verify ) || '1' === $verify ) {
			return;
		}

		$coupon_code = sanitize_text_field( $verify );
		
		// Charger le service si nécessaire (endpoint public, doit fonctionner même si WooCommerce n'est pas complètement chargé)
		if ( ! class_exists( 'DC25_Coupon_Service' ) ) {
			$coupon_service_file = DC25_PATH . 'includes/class-dc25-coupon-service.php';
			if ( file_exists( $coupon_service_file ) ) {
				require_once $coupon_service_file;
			}
		}
		
		// Vérifier que le service existe
		if ( ! class_exists( 'DC25_Coupon_Service' ) ) {
			// Logger l'erreur
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'DC25_Coupon_Service class not found for verification',
					[ 'source' => 'dc25-vouchers' ]
				);
			}
			// Afficher une page d'erreur
			status_header( 500 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			wp_die( esc_html__( 'Service de vérification non disponible.', 'dc25-vouchers' ), esc_html__( 'Erreur', 'dc25-vouchers' ), [ 'response' => 500 ] );
		}

		$status = DC25_Coupon_Service::get_coupon_status( $coupon_code );

		// Headers pour éviter le cache et permettre l'accès public
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' ); // Ne pas indexer les pages de vérification

		// Afficher la page de vérification
		$this->display_verify_page( $coupon_code, $status );
		exit;
	}

	/**
	 * Gérer le téléchargement public du PDF
	 */
	public function handle_download_pdf(): void {
		// Vérifier le paramètre
		$download = '';
		if ( isset( $_GET['dc25_gv_download'] ) && ! empty( $_GET['dc25_gv_download'] ) ) {
			$download = sanitize_text_field( wp_unslash( $_GET['dc25_gv_download'] ) );
		} elseif ( get_query_var( 'dc25_gv_download' ) ) {
			$download = get_query_var( 'dc25_gv_download' );
		}

		// Si pas de code ou code invalide, ne rien faire
		if ( empty( $download ) || '1' === $download ) {
			return;
		}

		$coupon_code = sanitize_text_field( $download );

		// Charger le service si nécessaire
		if ( ! class_exists( 'DC25_Coupon_Service' ) ) {
			$coupon_service_file = DC25_PATH . 'includes/class-dc25-coupon-service.php';
			if ( file_exists( $coupon_service_file ) ) {
				require_once $coupon_service_file;
			}
		}

		if ( ! class_exists( 'DC25_Coupon_Service' ) || ! class_exists( 'DC25_PDF_Service' ) ) {
			status_header( 500 );
			nocache_headers();
			wp_die( esc_html__( 'Service non disponible.', 'dc25-vouchers' ), esc_html__( 'Erreur', 'dc25-vouchers' ), [ 'response' => 500 ] );
		}

		// Vérifier que le coupon existe
		$coupon = new WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Coupon introuvable.', 'dc25-vouchers' ), esc_html__( 'Erreur', 'dc25-vouchers' ), [ 'response' => 404 ] );
		}

		// Récupérer les informations du bon cadeau depuis la commande
		// Chercher dans toutes les commandes pour trouver l'item correspondant
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$order_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT oi.order_id, oi.order_item_id
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
				WHERE oim.meta_key = %s AND oim.meta_value = %s
				LIMIT 1",
				'_dc25_gv_coupon_code',
				$coupon_code
			)
		);

		if ( ! $order_item ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Bon cadeau introuvable.', 'dc25-vouchers' ), esc_html__( 'Erreur', 'dc25-vouchers' ), [ 'response' => 404 ] );
		}

		$order = wc_get_order( $order_item->order_id );
		if ( ! $order ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Commande introuvable.', 'dc25-vouchers' ), esc_html__( 'Erreur', 'dc25-vouchers' ), [ 'response' => 404 ] );
		}

		$item = $order->get_item( $order_item->order_item_id );
		if ( ! $item ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Item introuvable.', 'dc25-vouchers' ), esc_html__( 'Erreur', 'dc25-vouchers' ), [ 'response' => 404 ] );
		}

		$amount = (float) $item->get_meta( '_dc25_gv_amount' );
		if ( $amount <= 0 ) {
			status_header( 400 );
			nocache_headers();
			wp_die( esc_html__( 'Montant invalide.', 'dc25-vouchers' ), esc_html__( 'Erreur', 'dc25-vouchers' ), [ 'response' => 400 ] );
		}

		// Récupérer la date d'expiration
		$expiry_date = '';
		$expiry_date_obj = $coupon->get_date_expires();
		if ( $expiry_date_obj ) {
			$expiry_date = $expiry_date_obj->date( 'Y-m-d' );
		}

		if ( empty( $expiry_date ) ) {
			$product = $item->get_product();
			if ( $product ) {
				$validity_days = $product->get_validity_days();
				$expiry_date = gmdate( 'Y-m-d', strtotime( "+{$validity_days} days" ) );
			}
		}

		// Générer le PDF
		$pdf_data = [
			'coupon_code'    => $coupon_code,
			'amount'         => $amount,
			'expiry_date'    => $expiry_date,
			'message'        => $item->get_meta( '_dc25_gv_message' ),
			'recipient_name' => $item->get_meta( '_dc25_gv_recipient_name' ),
			'from_name'      => $item->get_meta( '_dc25_gv_from_name' ),
		];

		$pdf_content = DC25_PDF_Service::generate_pdf_content( $pdf_data );

		if ( is_wp_error( $pdf_content ) ) {
			status_header( 500 );
			nocache_headers();
			wp_die(
				esc_html( sprintf( __( 'Erreur lors de la génération du PDF: %s', 'dc25-vouchers' ), $pdf_content->get_error_message() ) ),
				esc_html__( 'Erreur', 'dc25-vouchers' ),
				[ 'response' => 500 ]
			);
		}

		// Servir le PDF
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="bon-cadeau-' . esc_attr( $coupon_code ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_content ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		echo $pdf_content;
		exit;
	}

	/**
	 * Enregistrer les scripts pour la page de vérification
	 */
	public function enqueue_scripts(): void {
		// Vérifier si on est sur la page de vérification
		$verify = '';
		if ( isset( $_GET['dc25_gv_verify'] ) && ! empty( $_GET['dc25_gv_verify'] ) ) {
			$verify = sanitize_text_field( wp_unslash( $_GET['dc25_gv_verify'] ) );
		} elseif ( get_query_var( 'dc25_gv_verify' ) ) {
			$verify = get_query_var( 'dc25_gv_verify' );
		}

		if ( empty( $verify ) || '1' === $verify ) {
			return;
		}

		$coupon_code = sanitize_text_field( $verify );

		// Récupérer les CPT autorisés pour la recherche
		$allowed_post_types = [];
		if ( class_exists( 'DC25_Settings' ) ) {
			$settings = DC25_Settings::get_instance();
			$allowed_post_types = $settings->get_allowed_cpt_for_verification();
		}

		// Enregistrer Select2 (utiliser la version de WooCommerce si disponible, sinon CDN)
		if ( class_exists( 'WooCommerce' ) && wp_script_is( 'select2', 'registered' ) ) {
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );
		} else {
			// Utiliser Select2 depuis CDN
			wp_enqueue_script(
				'select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
				[ 'jquery' ],
				'4.1.0',
				true
			);
			wp_enqueue_style(
				'select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
				[],
				'4.1.0'
			);
		}

		// Enregistrer le script
		$script_path = DC25_PATH . 'assets/js/redeem-coupon.js';
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'dc25-redeem-coupon',
				DC25_URL . 'assets/js/redeem-coupon.js',
				[ 'jquery', 'select2' ],
				filemtime( $script_path ),
				true
			);

			// Localiser le script avec les données nécessaires
			wp_localize_script(
				'dc25-redeem-coupon',
				'dc25Redeem',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'dc25_redeem_ajax_' . $coupon_code ),
					'coupon_code' => $coupon_code,
					'allowed_post_types' => $allowed_post_types,
					'i18n' => [
						'success' => __( 'Le bon cadeau a été encaissé avec succès.', 'dc25-vouchers' ),
						'error' => __( 'Une erreur est survenue. Veuillez réessayer.', 'dc25-vouchers' ),
						'uploading' => __( 'Traitement en cours...', 'dc25-vouchers' ),
						'select_cpt_placeholder' => __( 'Rechercher un producteur/vigneron...', 'dc25-vouchers' ),
						'no_results' => __( 'Aucun résultat trouvé', 'dc25-vouchers' ),
						'searching' => __( 'Recherche en cours...', 'dc25-vouchers' ),
					],
				]
			);
		}

		// Enregistrer le style pour Select2 sur la page de vérification
		wp_add_inline_style( 'select2', '
			.dc25-verify-container .select2-container {
				width: 100% !important;
			}
			.dc25-verify-container .select2-container--default .select2-selection--single {
				height: auto;
				padding: 12px;
				border: 1px solid #ddd;
				border-radius: 4px;
			}
			.dc25-verify-container .select2-container--default .select2-selection--single:focus {
				border-color: #007bff;
				box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
			}
		' );
	}

	/**
	 * Gérer la recherche AJAX de CPT pour Select2
	 */
	public function handle_ajax_search_cpt(): void {
		// Vérifier que les settings sont disponibles
		if ( ! class_exists( 'DC25_Settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Service non disponible.', 'dc25-vouchers' ) ] );
			return;
		}

		$settings = DC25_Settings::get_instance();
		$allowed_post_types = $settings->get_allowed_cpt_for_verification();

		if ( empty( $allowed_post_types ) ) {
			wp_send_json_success( [
				'results' => [],
				'pagination' => [ 'more' => false ],
			] );
			return;
		}

		// Récupérer les paramètres de recherche (Select2 envoie via GET ou POST selon la version)
		$search = '';
		if ( isset( $_POST['search'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_POST['search'] ) );
		} elseif ( isset( $_GET['search'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_GET['search'] ) );
		}
		
		$page = 1;
		if ( isset( $_POST['page'] ) ) {
			$page = absint( $_POST['page'] );
		} elseif ( isset( $_GET['page'] ) ) {
			$page = absint( $_GET['page'] );
		}
		
		$posts_per_page = 20;

		// Construire la requête WP_Query
		$args = [
			'post_type'      => $allowed_post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );

		// Formater les résultats pour Select2
		$results = [];
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$post_type_obj = get_post_type_object( $post->post_type );
				$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;

				$results[] = [
					'id'   => $post->ID,
					'text' => $post->post_title . ' (' . $post_type_label . ')',
					'data' => [
						'post_type' => $post->post_type,
						'post_id'   => $post->ID,
					],
				];
			}
			wp_reset_postdata();
		}

		// Calculer s'il y a plus de résultats
		$has_more = $query->max_num_pages > $page;

		wp_send_json_success( [
			'results'    => $results,
			'pagination' => [
				'more' => $has_more,
			],
		] );
	}

	/**
	 * Gérer la soumission AJAX du formulaire d'encaissement
	 */
	public function handle_ajax_redeem(): void {
		// Vérifier le nonce AJAX
		$coupon_code = isset( $_POST['dc25_coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['dc25_coupon_code'] ) ) : '';
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( empty( $coupon_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Code coupon manquant.', 'dc25-vouchers' ) ] );
			return;
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'dc25_redeem_ajax_' . $coupon_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Erreur de sécurité. Veuillez réessayer.', 'dc25-vouchers' ) ] );
			return;
		}

		$cashier_name = isset( $_POST['dc25_cashier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dc25_cashier_name'] ) ) : '';

		if ( empty( $cashier_name ) ) {
			wp_send_json_error( [ 'message' => __( 'Le nom de la personne qui encaisse est requis.', 'dc25-vouchers' ) ] );
			return;
		}

		// Charger le service si nécessaire
		if ( ! class_exists( 'DC25_Coupon_Service' ) ) {
			$coupon_service_file = DC25_PATH . 'includes/class-dc25-coupon-service.php';
			if ( file_exists( $coupon_service_file ) ) {
				require_once $coupon_service_file;
			}
		}

		if ( ! class_exists( 'DC25_Coupon_Service' ) ) {
			wp_send_json_error( [ 'message' => __( 'Service non disponible.', 'dc25-vouchers' ) ] );
			return;
		}

		// Vérifier que le coupon est valide avant de procéder
		$status = DC25_Coupon_Service::get_coupon_status( $coupon_code );
		if ( 'valid' !== $status['status'] ) {
			wp_send_json_error( [ 'message' => __( 'Ce coupon n\'est plus valide.', 'dc25-vouchers' ) ] );
			return;
		}

		// Charger le coupon
		$coupon = new WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			wp_send_json_error( [ 'message' => __( 'Coupon introuvable.', 'dc25-vouchers' ) ] );
			return;
		}

		// Gérer l'upload du fichier
		$uploaded_file = null;
		if ( ! empty( $_FILES['dc25_receipt_file']['name'] ) ) {
			// Vérifier la taille du fichier (max 5MB)
			$max_size = 5 * 1024 * 1024; // 5MB en bytes
			if ( $_FILES['dc25_receipt_file']['size'] > $max_size ) {
				wp_send_json_error( [ 'message' => __( 'Le fichier est trop volumineux. Taille maximale: 5MB', 'dc25-vouchers' ) ] );
				return;
			}

			// Configuration de l'upload
			$upload_overrides = [
				'test_form' => false,
				'mimes' => [
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif' => 'image/gif',
					'png' => 'image/png',
					'pdf' => 'application/pdf',
				],
			];

			if ( ! defined( 'ABSPATH' ) ) {
				wp_send_json_error( [ 'message' => __( 'Erreur système.', 'dc25-vouchers' ) ] );
				return;
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			$uploaded_file = wp_handle_upload( $_FILES['dc25_receipt_file'], $upload_overrides );

			if ( isset( $uploaded_file['error'] ) ) {
				wp_send_json_error( [ 'message' => esc_html( $uploaded_file['error'] ) ] );
				return;
			}
		}

		// Récupérer le CPT sélectionné (optionnel)
		$selected_cpt_id = isset( $_POST['dc25_selected_cpt'] ) ? absint( $_POST['dc25_selected_cpt'] ) : 0;
		$selected_cpt_type = '';
		if ( $selected_cpt_id > 0 ) {
			$selected_post = get_post( $selected_cpt_id );
			if ( $selected_post && 'publish' === $selected_post->post_status ) {
				$selected_cpt_type = $selected_post->post_type;
			} else {
				$selected_cpt_id = 0; // Invalider si le post n'existe pas ou n'est pas publié
			}
		}

		// Trouver l'order item associé au coupon pour sauvegarder les métadonnées
		global $wpdb;
		$order_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT oi.order_id, oi.order_item_id
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
				WHERE oim.meta_key = %s AND oim.meta_value = %s
				LIMIT 1",
				'_dc25_gv_coupon_code',
				$coupon_code
			)
		);

		// Sauvegarder les métadonnées sur le coupon
		if ( ! empty( $cashier_name ) ) {
			$coupon->update_meta_data( '_dc25_redeemed_by', $cashier_name );
		}
		$coupon->update_meta_data( '_dc25_redeemed_at', current_time( 'mysql' ) );
		if ( $uploaded_file && isset( $uploaded_file['url'] ) ) {
			$coupon->update_meta_data( '_dc25_receipt_file', $uploaded_file['url'] );
			$coupon->update_meta_data( '_dc25_receipt_path', $uploaded_file['file'] );
		}
		if ( $selected_cpt_id > 0 && ! empty( $selected_cpt_type ) ) {
			$coupon->update_meta_data( '_dc25_redeemed_at_cpt_id', $selected_cpt_id );
			$coupon->update_meta_data( '_dc25_redeemed_at_cpt_type', $selected_cpt_type );
		}

		// Sauvegarder aussi sur l'order item si trouvé
		if ( $order_item ) {
			$order = wc_get_order( $order_item->order_id );
			if ( $order ) {
				$item = $order->get_item( $order_item->order_item_id );
				if ( $item ) {
					if ( ! empty( $cashier_name ) ) {
						$item->update_meta_data( '_dc25_redeemed_by', $cashier_name );
					}
					$item->update_meta_data( '_dc25_redeemed_at', current_time( 'mysql' ) );
					if ( $uploaded_file && isset( $uploaded_file['url'] ) ) {
						$item->update_meta_data( '_dc25_receipt_file', $uploaded_file['url'] );
						$item->update_meta_data( '_dc25_receipt_path', $uploaded_file['file'] );
					}
					if ( $selected_cpt_id > 0 && ! empty( $selected_cpt_type ) ) {
						$item->update_meta_data( '_dc25_gv_selected_cpt_id', $selected_cpt_id );
						$item->update_meta_data( '_dc25_gv_selected_cpt_type', $selected_cpt_type );
					}
					$item->save();
					$order->save();
				}
			}
		}

		// Invalider le coupon (marquer comme utilisé)
		$coupon->set_usage_count( $coupon->get_usage_limit() );
		$coupon->save();

		// Logger l'action
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf( 'Coupon %s encaissé par %s', $coupon_code, $cashier_name ),
				[ 'source' => 'dc25-vouchers' ]
			);
		}

		// Retourner une réponse JSON de succès
		wp_send_json_success( [
			'message' => __( 'Le bon cadeau a été encaissé avec succès.', 'dc25-vouchers' ),
			'coupon_code' => $coupon_code,
		] );
	}

	/**
	 * Afficher la page de vérification
	 *
	 * @param string $coupon_code Code du coupon.
	 * @param array  $status Statut du coupon.
	 */
	private function display_verify_page( string $coupon_code, array $status ): void {
		/** @var WC_Coupon $coupon */
		$coupon = new WC_Coupon( $coupon_code );
		$amount = $coupon->get_id() ? $coupon->get_amount() : 0;

		// Chercher un template dans le thème
		$theme_template = locate_template( 'dc25-vouchers/verify-voucher.php' );
		
		if ( $theme_template ) {
			// Utiliser le template du thème
			$coupon_code_var = $coupon_code;
			$status_var = $status;
			$amount_var = $amount;
			include $theme_template;
		} else {
			// Template par défaut avec header/footer du thème
			get_header();
			?>
			<div class="dc25-verify-container">
				<div class="dc25-verify-content">
					<h1><?php esc_html_e( 'Vérification du bon cadeau', 'dc25-vouchers' ); ?></h1>

					<?php if ( isset( $_GET['redeemed'] ) && '1' === $_GET['redeemed'] ) : ?>
						<div class="dc25-success-message">
							<?php esc_html_e( 'Le bon cadeau a été encaissé avec succès.', 'dc25-vouchers' ); ?>
						</div>
					<?php endif; ?>

					<?php if ( 'valid' === $status['status'] ) : ?>
						<div class="dc25-status-icon dc25-status-valid">✅</div>
						<div class="dc25-status-message dc25-status-valid"><?php echo esc_html( $status['message'] ); ?></div>
						<div class="dc25-details">
							<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="dc25-code"><?php echo esc_html( $coupon_code ); ?></span></p>
							<p><strong><?php esc_html_e( 'Montant:', 'dc25-vouchers' ); ?></strong> <?php echo wp_kses_post( wc_price( $status['amount'] ) ); ?></p>
							<?php if ( ! empty( $status['expiry_date'] ) ) : ?>
								<p><strong><?php esc_html_e( 'Valable jusqu\'au:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $status['expiry_date'] ) ) ); ?></p>
							<?php endif; ?>
						</div>

						<!-- Formulaire d'encaissement -->
						<div class="dc25-redeem-form">
							<h2><?php esc_html_e( 'Encaisser le bon cadeau', 'dc25-vouchers' ); ?></h2>
							<div id="dc25-redeem-message" class="dc25-message" style="display: none;"></div>
							<form id="dc25-redeem-form" method="post" enctype="multipart/form-data">
								<input type="hidden" name="dc25_coupon_code" value="<?php echo esc_attr( $coupon_code ); ?>" />

								<div class="dc25-form-group">
									<label for="dc25_cashier_name">
										<?php esc_html_e( 'Nom de la personne qui encaisse', 'dc25-vouchers' ); ?>
										<span class="dc25-required">*</span>
									</label>
									<input 
										type="text" 
										id="dc25_cashier_name" 
										name="dc25_cashier_name" 
										required 
										class="dc25-form-input"
										placeholder="<?php esc_attr_e( 'Votre nom', 'dc25-vouchers' ); ?>"
									/>
								</div>

								<?php
								// Afficher le champ de sélection CPT seulement si des CPT sont configurés
								$allowed_post_types = [];
								if ( class_exists( 'DC25_Settings' ) ) {
									$settings = DC25_Settings::get_instance();
									$allowed_post_types = $settings->get_allowed_cpt_for_verification();
								}
								if ( ! empty( $allowed_post_types ) ) :
								?>
								<div class="dc25-form-group">
									<label for="dc25_selected_cpt">
										<?php esc_html_e( 'Producteur/Vigneron', 'dc25-vouchers' ); ?>
									</label>
									<select 
										id="dc25_selected_cpt" 
										name="dc25_selected_cpt" 
										class="dc25-form-input dc25-cpt-select"
									>
										<option value=""><?php esc_html_e( 'Sélectionner un producteur/vigneron...', 'dc25-vouchers' ); ?></option>
									</select>
									<small class="dc25-form-help">
										<?php esc_html_e( 'Recherchez et sélectionnez le producteur ou vigneron concerné (optionnel)', 'dc25-vouchers' ); ?>
									</small>
								</div>
								<?php endif; ?>

								<div class="dc25-form-group">
									<label for="dc25_receipt_file">
										<?php esc_html_e( 'Photo du ticket ou PDF de la facture', 'dc25-vouchers' ); ?>
									</label>
									<input 
										type="file" 
										id="dc25_receipt_file" 
										name="dc25_receipt_file" 
										accept="image/*,.pdf"
										class="dc25-form-input"
									/>
									<small class="dc25-form-help">
										<?php esc_html_e( 'Formats acceptés: JPG, PNG, GIF, PDF (max 5MB)', 'dc25-vouchers' ); ?>
									</small>
								</div>

								<button type="submit" class="dc25-redeem-button">
									<?php esc_html_e( 'Procéder à l\'encaissement', 'dc25-vouchers' ); ?>
								</button>
							</form>
						</div>
					<?php elseif ( 'expired' === $status['status'] ) : ?>
						<div class="dc25-status-icon dc25-status-expired">⏰</div>
						<div class="dc25-status-message dc25-status-expired"><?php echo esc_html( $status['message'] ); ?></div>
						<div class="dc25-details">
							<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="dc25-code"><?php echo esc_html( $coupon_code ); ?></span></p>
							<?php if ( ! empty( $status['expiry_date'] ) ) : ?>
								<p><strong><?php esc_html_e( 'Date d\'expiration:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $status['expiry_date'] ) ) ); ?></p>
							<?php endif; ?>
						</div>
					<?php elseif ( 'used' === $status['status'] ) : ?>
						<div class="dc25-status-icon dc25-status-used">❌</div>
						<div class="dc25-status-message dc25-status-used"><?php echo esc_html( $status['message'] ); ?></div>
						<div class="dc25-details">
							<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="dc25-code"><?php echo esc_html( $coupon_code ); ?></span></p>
						</div>
					<?php else : ?>
						<div class="dc25-status-icon dc25-status-invalid">❓</div>
						<div class="dc25-status-message dc25-status-invalid"><?php echo esc_html( $status['message'] ); ?></div>
						<div class="dc25-details">
							<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="dc25-code"><?php echo esc_html( $coupon_code ); ?></span></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<style>
				.dc25-verify-container {
					max-width: 800px;
					margin: 40px auto;
					padding: 20px;
				}
				.dc25-verify-content {
					background: #fff;
					border-radius: 8px;
					padding: 40px;
					text-align: center;
					box-shadow: 0 2px 10px rgba(0,0,0,0.1);
				}
				.dc25-verify-content h1 {
					font-size: 28px;
					margin-bottom: 20px;
				}
				.dc25-status-icon {
					font-size: 64px;
					margin: 20px 0;
				}
				.dc25-status-valid { color: #28a745; }
				.dc25-status-expired { color: #ffc107; }
				.dc25-status-used { color: #dc3545; }
				.dc25-status-invalid { color: #6c757d; }
				.dc25-status-message {
					font-size: 18px;
					font-weight: 600;
					margin: 20px 0;
				}
				.dc25-details {
					background: #f8f9fa;
					border-radius: 8px;
					padding: 20px;
					margin: 20px 0;
					text-align: left;
				}
				.dc25-details p {
					margin: 10px 0;
				}
				.dc25-code {
					font-family: monospace;
					font-size: 20px;
					font-weight: bold;
					color: #007bff;
					letter-spacing: 2px;
					margin-left: 10px;
				}
				.dc25-success-message {
					background: #d4edda;
					border: 1px solid #c3e6cb;
					color: #155724;
					padding: 15px;
					border-radius: 8px;
					margin-bottom: 20px;
					text-align: center;
					font-weight: 600;
				}
				.dc25-redeem-form {
					margin-top: 30px;
					padding-top: 30px;
					border-top: 2px solid #e9ecef;
					text-align: left;
				}
				.dc25-redeem-form h2 {
					font-size: 22px;
					margin-bottom: 20px;
					text-align: center;
				}
				.dc25-form-group {
					margin-bottom: 20px;
				}
				.dc25-form-group label {
					display: block;
					margin-bottom: 8px;
					font-weight: 600;
					color: #333;
				}
				.dc25-required {
					color: #dc3545;
				}
				.dc25-form-input {
					width: 100%;
					padding: 12px;
					border: 1px solid #ddd;
					border-radius: 4px;
					font-size: 16px;
					box-sizing: border-box;
				}
				.dc25-form-input:focus {
					outline: none;
					border-color: #007bff;
					box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
				}
				.dc25-form-help {
					display: block;
					margin-top: 5px;
					font-size: 13px;
					color: #6c757d;
				}
				.dc25-redeem-button {
					width: 100%;
					background: #dc3545;
					color: #fff;
					border: none;
					padding: 15px 30px;
					font-size: 18px;
					font-weight: 600;
					border-radius: 4px;
					cursor: pointer;
					transition: background 0.3s ease;
					margin-top: 10px;
				}
				.dc25-redeem-button:hover {
					background: #c82333;
				}
				.dc25-redeem-button:active {
					background: #bd2130;
				}
				.dc25-redeem-button:disabled {
					background: #6c757d;
					cursor: not-allowed;
					opacity: 0.6;
				}
				.dc25-message {
					padding: 15px;
					border-radius: 8px;
					margin-bottom: 20px;
					font-weight: 600;
				}
				.dc25-message.success {
					background: #d4edda;
					border: 1px solid #c3e6cb;
					color: #155724;
				}
				.dc25-message.error {
					background: #f8d7da;
					border: 1px solid #f5c6cb;
					color: #721c24;
				}
				.dc25-form-loading {
					display: inline-block;
					margin-left: 10px;
					width: 16px;
					height: 16px;
					border: 2px solid #fff;
					border-top-color: transparent;
					border-radius: 50%;
					animation: dc25-spin 0.6s linear infinite;
				}
				@keyframes dc25-spin {
					to { transform: rotate(360deg); }
				}
			</style>
			<?php
			get_footer();
		}
	}
}

