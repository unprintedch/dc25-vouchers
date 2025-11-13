<?php
/**
 * Interface d'administration pour les bons cadeaux
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour l'interface d'administration des bons cadeaux
 */
class DC25_Vouchers_Admin {

	/**
	 * Constructeur
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'admin_post_dc25_download_pdf', [ $this, 'handle_download_pdf' ] );
		add_action( 'admin_post_dc25_generate_coupon', [ $this, 'handle_generate_coupon' ] );
	}

	/**
	 * Ajouter le menu dans l'admin WooCommerce
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Bons cadeaux', 'dc25-vouchers' ),
			__( 'Bons cadeaux', 'dc25-vouchers' ),
			'manage_woocommerce',
			'dc25-vouchers',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue scripts et styles admin
	 *
	 * @param string $hook_suffix Hook suffix.
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		if ( 'woocommerce_page_dc25-vouchers' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'dc25-vouchers-admin',
			DC25_URL . 'assets/css/admin.css',
			[],
			DC25_VERSION
		);
	}

	/**
	 * Rendre la page d'administration
	 */
	public function render_admin_page(): void {
		// Afficher les messages de retour
		if ( isset( $_GET['message'] ) ) {
			switch ( $_GET['message'] ) {
				case 'coupon_generated':
					$code = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
					echo '<div class="notice notice-success is-dismissible"><p>';
					printf( esc_html__( 'Coupon généré avec succès : %s', 'dc25-vouchers' ), '<strong>' . esc_html( $code ) . '</strong>' );
					echo '</p></div>';
					break;
				case 'coupon_exists':
					echo '<div class="notice notice-warning is-dismissible"><p>';
					esc_html_e( 'Ce coupon existe déjà.', 'dc25-vouchers' );
					echo '</p></div>';
					break;
				case 'coupon_error':
					$error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
					echo '<div class="notice notice-error is-dismissible"><p>';
					printf( esc_html__( 'Erreur lors de la génération du coupon : %s', 'dc25-vouchers' ), esc_html( $error ) );
					echo '</p></div>';
					break;
			}
		}

		$vouchers = $this->get_all_vouchers();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bons cadeaux', 'dc25-vouchers' ); ?></h1>
			
			<div class="dc25-vouchers-admin">
				<?php if ( empty( $vouchers ) ) : ?>
					<div class="notice notice-info">
						<p><?php esc_html_e( 'Aucun bon cadeau trouvé.', 'dc25-vouchers' ); ?></p>
						<p><small><?php esc_html_e( 'Les bons cadeaux apparaîtront ici une fois qu\'une commande contenant un bon cadeau aura été créée.', 'dc25-vouchers' ); ?></small></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Code coupon', 'dc25-vouchers' ); ?></th>
								<th><?php esc_html_e( 'Montant', 'dc25-vouchers' ); ?></th>
								<th><?php esc_html_e( 'Commande', 'dc25-vouchers' ); ?></th>
								<th><?php esc_html_e( 'Date', 'dc25-vouchers' ); ?></th>
								<th><?php esc_html_e( 'Expiration', 'dc25-vouchers' ); ?></th>
								<th><?php esc_html_e( 'Statut', 'dc25-vouchers' ); ?></th>
								<th><?php esc_html_e( 'Destinataire', 'dc25-vouchers' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'dc25-vouchers' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $vouchers as $voucher ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $voucher['coupon_edit_url'] ) ) : ?>
											<a href="<?php echo esc_url( $voucher['coupon_edit_url'] ); ?>" target="_blank">
												<strong><?php echo esc_html( $voucher['coupon_code'] ); ?></strong>
											</a>
										<?php else : ?>
											<strong><?php echo esc_html( $voucher['coupon_code'] ); ?></strong>
										<?php endif; ?>
									</td>
									<td>
										<?php echo wp_kses_post( wc_price( $voucher['amount'] ) ); ?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $voucher['order_id'] . '&action=edit' ) ); ?>">
											#<?php echo esc_html( $voucher['order_id'] ); ?>
										</a>
									</td>
									<td>
										<?php echo esc_html( $voucher['order_date'] ); ?>
									</td>
									<td>
										<?php echo esc_html( $voucher['expiry_date'] ); ?>
									</td>
									<td>
										<span class="dc25-status dc25-status-<?php echo esc_attr( $voucher['status'] ); ?>" title="<?php esc_attr_e( 'Statut du coupon WooCommerce', 'dc25-vouchers' ); ?>">
											<?php echo esc_html( $voucher['status_label'] ); ?>
										</span>
									</td>
									<td>
										<?php if ( ! empty( $voucher['recipient_name'] ) ) : ?>
											<?php echo esc_html( $voucher['recipient_name'] ); ?>
											<?php if ( ! empty( $voucher['recipient_email'] ) ) : ?>
												<br><small><?php echo esc_html( $voucher['recipient_email'] ); ?></small>
											<?php endif; ?>
										<?php else : ?>
											<span class="description"><?php esc_html_e( 'Non renseigné', 'dc25-vouchers' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( empty( $voucher['coupon_code'] ) || __( 'Non généré', 'dc25-vouchers' ) === $voucher['coupon_code'] ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dc25_generate_coupon&voucher_id=' . $voucher['item_id'] ), 'dc25_generate_coupon' ) ); ?>" class="button button-primary button-small">
												<?php esc_html_e( 'Générer le coupon', 'dc25-vouchers' ); ?>
											</a>
										<?php else : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dc25_download_pdf&voucher_id=' . $voucher['item_id'] ), 'dc25_download_pdf' ) ); ?>" class="button button-small">
												<?php esc_html_e( 'Télécharger PDF', 'dc25-vouchers' ); ?>
											</a>
										<?php endif; ?>
										<button type="button" class="button button-small dc25-view-details" data-voucher-id="<?php echo esc_attr( $voucher['item_id'] ); ?>">
											<?php esc_html_e( 'Détails', 'dc25-vouchers' ); ?>
										</button>
									</td>
								</tr>
								<tr class="dc25-voucher-details" id="dc25-details-<?php echo esc_attr( $voucher['item_id'] ); ?>" style="display: none;">
									<td colspan="9">
										<div class="dc25-details-content">
											<h3><?php esc_html_e( 'Détails du bon cadeau', 'dc25-vouchers' ); ?></h3>
											<table class="form-table">
												<tr>
													<th><?php esc_html_e( 'Code coupon', 'dc25-vouchers' ); ?></th>
													<td>
														<?php if ( ! empty( $voucher['coupon_edit_url'] ) ) : ?>
															<a href="<?php echo esc_url( $voucher['coupon_edit_url'] ); ?>" target="_blank">
																<code><?php echo esc_html( $voucher['coupon_code'] ); ?></code>
															</a>
														<?php else : ?>
															<code><?php echo esc_html( $voucher['coupon_code'] ); ?></code>
														<?php endif; ?>
													</td>
												</tr>
												<tr>
													<th><?php esc_html_e( 'Montant', 'dc25-vouchers' ); ?></th>
													<td><?php echo wp_kses_post( wc_price( $voucher['amount'] ) ); ?></td>
												</tr>
												<?php if ( ! empty( $voucher['message'] ) ) : ?>
													<tr>
														<th><?php esc_html_e( 'Message', 'dc25-vouchers' ); ?></th>
														<td><?php echo esc_html( $voucher['message'] ); ?></td>
													</tr>
												<?php endif; ?>
												<tr>
													<th><?php esc_html_e( 'Date de commande', 'dc25-vouchers' ); ?></th>
													<td><?php echo esc_html( $voucher['order_date'] ); ?></td>
												</tr>
												<tr>
													<th><?php esc_html_e( 'Date d\'expiration', 'dc25-vouchers' ); ?></th>
													<td><?php echo esc_html( $voucher['expiry_date'] ); ?></td>
												</tr>
												<tr>
													<th><?php esc_html_e( 'Statut', 'dc25-vouchers' ); ?></th>
													<td>
														<span class="dc25-status dc25-status-<?php echo esc_attr( $voucher['status'] ); ?>" title="<?php esc_attr_e( 'Statut du coupon WooCommerce', 'dc25-vouchers' ); ?>">
															<?php echo esc_html( $voucher['status_label'] ); ?>
														</span>
													</td>
												</tr>
												<?php if ( ! empty( $voucher['recipient_name'] ) ) : ?>
													<tr>
														<th><?php esc_html_e( 'Destinataire', 'dc25-vouchers' ); ?></th>
														<td>
															<?php echo esc_html( $voucher['recipient_name'] ); ?>
															<?php if ( ! empty( $voucher['recipient_email'] ) ) : ?>
																<br><small><?php echo esc_html( $voucher['recipient_email'] ); ?></small>
															<?php endif; ?>
														</td>
													</tr>
												<?php endif; ?>
												<?php if ( ! empty( $voucher['physical'] ) && 'yes' === $voucher['physical'] ) : ?>
													<tr>
														<th><?php esc_html_e( 'Envoi physique', 'dc25-vouchers' ); ?></th>
														<td><?php esc_html_e( 'Oui (+ 5 CHF)', 'dc25-vouchers' ); ?></td>
													</tr>
												<?php endif; ?>
												<?php if ( ! empty( $voucher['redeemed_by'] ) ) : ?>
													<tr>
														<th><?php esc_html_e( 'Encaissé par', 'dc25-vouchers' ); ?></th>
														<td>
															<?php echo esc_html( $voucher['redeemed_by'] ); ?>
															<?php if ( ! empty( $voucher['redeemed_at'] ) ) : ?>
																<br><small><?php 
																	$redeemed_date = date_i18n( 
																		get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), 
																		strtotime( $voucher['redeemed_at'] ) 
																	);
																	printf( esc_html__( 'Le %s', 'dc25-vouchers' ), esc_html( $redeemed_date ) );
																?></small>
															<?php endif; ?>
														</td>
													</tr>
												<?php endif; ?>
												<?php if ( ! empty( $voucher['receipt_file'] ) ) : ?>
													<tr>
														<th><?php esc_html_e( 'Justificatif', 'dc25-vouchers' ); ?></th>
														<td>
															<a href="<?php echo esc_url( $voucher['receipt_file'] ); ?>" target="_blank" class="button button-small">
																<?php esc_html_e( 'Voir le fichier', 'dc25-vouchers' ); ?>
															</a>
														</td>
													</tr>
												<?php endif; ?>
											</table>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.dc25-view-details').on('click', function() {
				var voucherId = $(this).data('voucher-id');
				$('#dc25-details-' + voucherId).toggle();
			});
		});
		</script>
		<?php
	}

	/**
	 * Récupérer tous les bons cadeaux
	 *
	 * @return array
	 */
	private function get_all_vouchers(): array {
		global $wpdb;

		// Récupérer tous les items de commande qui sont des bons cadeaux
		// Méthode optimisée : utiliser prepare() pour la sécurité
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT
					oi.order_item_id,
					oi.order_id,
					oi.order_item_name,
					oim_product.meta_value as product_id
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_product ON oi.order_item_id = oim_product.order_item_id AND oim_product.meta_key = %s
				ORDER BY oi.order_id DESC
				LIMIT %d",
				'_product_id',
				500
			)
		);

		$vouchers = [];

		foreach ( $order_items as $item ) {
			// Convertir en array si objet
			$item_data = is_object( $item ) ? (array) $item : $item;
			
			// Vérifier que c'est bien un produit gift_voucher
			$product_id = isset( $item_data['product_id'] ) ? intval( $item_data['product_id'] ) : 0;
			if ( $product_id > 0 ) {
				$product = wc_get_product( $product_id );
				if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
					continue; // Pas un bon cadeau
				}
			}
			
			$order = wc_get_order( $item_data['order_id'] );
			if ( ! $order ) {
				continue;
			}

			$order_item = $order->get_item( $item_data['order_item_id'] );
			if ( ! $order_item ) {
				continue;
			}

			// Ne pas filtrer les bons sans coupon - on les affiche aussi
			$coupon_code = $order_item->get_meta( '_dc25_gv_coupon_code' );
			// Si pas de coupon, on affiche quand même le bon (peut-être pas encore généré)

			$amount = (float) $order_item->get_meta( '_dc25_gv_amount' );
			$message = $order_item->get_meta( '_dc25_gv_message' );
			$recipient_name = $order_item->get_meta( '_dc25_gv_recipient_name' );
			$recipient_email = $order_item->get_meta( '_dc25_gv_recipient_email' );
			$physical = $order_item->get_meta( '_dc25_gv_physical' );

			// Déterminer le statut du bon cadeau
			$order_status = $order->get_status();
			$status = 'pending';
			$status_labels = [
				'valid'   => __( 'Valide', 'dc25-vouchers' ),
				'expired' => __( 'Expiré', 'dc25-vouchers' ),
				'used'    => __( 'Utilisé', 'dc25-vouchers' ),
				'invalid' => __( 'Invalide', 'dc25-vouchers' ),
				'pending' => __( 'En attente de paiement', 'dc25-vouchers' ),
				'processing' => __( 'En cours de traitement', 'dc25-vouchers' ),
			];

			$expiry_date_str = __( 'Non définie', 'dc25-vouchers' );
			
			// Si pas de coupon généré, le statut dépend de l'état de la commande
			if ( empty( $coupon_code ) ) {
				if ( 'completed' === $order_status ) {
					// Commande complétée mais coupon pas encore généré (erreur)
					$status = 'pending';
					$status_labels['pending'] = __( 'Erreur: coupon non généré', 'dc25-vouchers' );
				} elseif ( 'processing' === $order_status || 'on-hold' === $order_status ) {
					$status = 'processing';
				} else {
					// En attente de paiement
					$status = 'pending';
				}
			} else {
				// Coupon généré, vérifier son statut
				$coupon_status = DC25_Coupon_Service::get_coupon_status( $coupon_code );
				$status = $coupon_status['status'];
				
				// Récupérer la date d'expiration
				if ( class_exists( 'WC_Coupon' ) ) {
					/** @var WC_Coupon $coupon */
					$coupon = new WC_Coupon( $coupon_code );
					$expiry_date = $coupon->get_date_expires();
					if ( $expiry_date ) {
						$expiry_date_str = $expiry_date->date_i18n( get_option( 'date_format' ) );
					}
				}
			}

			// Récupérer l'ID du coupon pour créer un lien vers l'édition
			$coupon_id = 0;
			$coupon_edit_url = '';
			$redeemed_by = '';
			$redeemed_at = '';
			$receipt_file = '';
			if ( ! empty( $coupon_code ) && class_exists( 'WC_Coupon' ) ) {
				/** @var WC_Coupon $coupon_obj */
				$coupon_obj = new WC_Coupon( $coupon_code );
				if ( $coupon_obj->get_id() > 0 ) {
					$coupon_id = $coupon_obj->get_id();
					$coupon_edit_url = admin_url( 'post.php?post=' . $coupon_id . '&action=edit' );
					
					// Récupérer les métadonnées d'encaissement
					$redeemed_by = $coupon_obj->get_meta( '_dc25_redeemed_by' );
					$redeemed_at = $coupon_obj->get_meta( '_dc25_redeemed_at' );
					$receipt_file = $coupon_obj->get_meta( '_dc25_receipt_file' );
				}
			}

			// Récupérer la date de création de la commande (jour uniquement, sans heure)
			$order_date = $order->get_date_created();
			$order_date_str = $order_date ? $order_date->date_i18n( get_option( 'date_format' ) ) : __( 'N/A', 'dc25-vouchers' );

			$vouchers[] = [
				'item_id'        => $item_data['order_item_id'],
				'order_id'       => $item_data['order_id'],
				'coupon_code'    => $coupon_code ?: __( 'Non généré', 'dc25-vouchers' ),
				'coupon_id'      => $coupon_id,
				'coupon_edit_url' => $coupon_edit_url,
				'amount'         => $amount,
				'message'        => $message,
				'recipient_name' => $recipient_name,
				'recipient_email' => $recipient_email,
				'physical'       => $physical,
				'order_date'     => $order_date_str,
				'expiry_date'    => $expiry_date_str,
				'status'         => $status,
				'status_label'   => $status_labels[ $status ] ?? __( 'Inconnu', 'dc25-vouchers' ),
				'redeemed_by'    => $redeemed_by,
				'redeemed_at'    => $redeemed_at,
				'receipt_file'   => $receipt_file,
			];
		}

		return $vouchers;
	}

	/**
	 * Gérer le téléchargement du PDF
	 */
	public function handle_download_pdf(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Vous n\'avez pas les permissions nécessaires.', 'dc25-vouchers' ) );
		}

		check_admin_referer( 'dc25_download_pdf' );

		$voucher_id = isset( $_GET['voucher_id'] ) ? intval( $_GET['voucher_id'] ) : 0;
		if ( ! $voucher_id ) {
			wp_die( __( 'ID de bon cadeau invalide.', 'dc25-vouchers' ) );
		}

		// Récupérer l'item de commande
		global $wpdb;
		$order_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
				$voucher_id
			)
		);

		if ( ! $order_item ) {
			wp_die( __( 'Bon cadeau introuvable.', 'dc25-vouchers' ) );
		}

		$order = wc_get_order( $order_item->order_id );
		if ( ! $order ) {
			wp_die( __( 'Commande introuvable.', 'dc25-vouchers' ) );
		}

		$item = $order->get_item( $voucher_id );
		if ( ! $item ) {
			wp_die( __( 'Item introuvable.', 'dc25-vouchers' ) );
		}

		$coupon_code = $item->get_meta( '_dc25_gv_coupon_code' );
		if ( empty( $coupon_code ) ) {
			wp_die( __( 'Coupon non généré pour ce bon cadeau.', 'dc25-vouchers' ) );
		}

		$product = $item->get_product();
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			wp_die( __( 'Ce n\'est pas un bon cadeau.', 'dc25-vouchers' ) );
		}

		$amount = (float) $item->get_meta( '_dc25_gv_amount' );
		if ( $amount <= 0 ) {
			wp_die( __( 'Montant invalide.', 'dc25-vouchers' ) );
		}

		// Récupérer la date d'expiration du coupon
		$expiry_date = '';
		if ( class_exists( 'WC_Coupon' ) ) {
			/** @var WC_Coupon $coupon */
			$coupon = new WC_Coupon( $coupon_code );
			if ( $coupon->get_id() > 0 ) {
				$expiry_date_obj = $coupon->get_date_expires();
				if ( $expiry_date_obj ) {
					$expiry_date = $expiry_date_obj->date( 'Y-m-d' );
				}
			}
		}

		// Si pas de date d'expiration, utiliser la validité du produit
		if ( empty( $expiry_date ) ) {
			$validity_days = $product->get_validity_days();
			$expiry_timestamp = current_time( 'timestamp' ) + ( $validity_days * DAY_IN_SECONDS );
			$expiry_date = gmdate( 'Y-m-d', $expiry_timestamp );
		}

		// Générer le PDF à la volée
		$pdf_data = [
			'coupon_code'    => $coupon_code,
			'amount'         => $amount,
			'expiry_date'    => $expiry_date,
			'message'        => $item->get_meta( '_dc25_gv_message' ),
			'recipient_name' => $item->get_meta( '_dc25_gv_recipient_name' ),
		];

		$pdf_content = DC25_PDF_Service::generate_pdf_content( $pdf_data );
		
		if ( is_wp_error( $pdf_content ) ) {
			wp_die( sprintf( __( 'Erreur lors de la génération du PDF: %s', 'dc25-vouchers' ), $pdf_content->get_error_message() ) );
		}

		// Servir le PDF directement
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="bon-cadeau-' . esc_attr( $coupon_code ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_content ) );
		echo $pdf_content;
		exit;
	}

	/**
	 * Gérer la génération manuelle du coupon
	 */
	public function handle_generate_coupon(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Vous n\'avez pas les permissions nécessaires.', 'dc25-vouchers' ) );
		}

		check_admin_referer( 'dc25_generate_coupon' );

		$voucher_id = isset( $_GET['voucher_id'] ) ? intval( $_GET['voucher_id'] ) : 0;
		if ( ! $voucher_id ) {
			wp_die( __( 'ID de bon cadeau invalide.', 'dc25-vouchers' ) );
		}

		// Récupérer l'item de commande
		global $wpdb;
		$order_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
				$voucher_id
			)
		);

		if ( ! $order_item ) {
			wp_die( __( 'Bon cadeau introuvable.', 'dc25-vouchers' ) );
		}

		$order = wc_get_order( $order_item->order_id );
		if ( ! $order ) {
			wp_die( __( 'Commande introuvable.', 'dc25-vouchers' ) );
		}

		$item = $order->get_item( $voucher_id );
		if ( ! $item ) {
			wp_die( __( 'Item introuvable.', 'dc25-vouchers' ) );
		}

		// Vérifier si le coupon existe déjà
		$existing_coupon = $item->get_meta( '_dc25_gv_coupon_code' );
		if ( ! empty( $existing_coupon ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dc25-vouchers&message=coupon_exists' ) );
			exit;
		}

		// Générer le coupon en utilisant la même logique que process_order
		$product = $item->get_product();
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			wp_die( __( 'Ce n\'est pas un bon cadeau.', 'dc25-vouchers' ) );
		}

		$amount = (float) $item->get_meta( '_dc25_gv_amount' );
		if ( $amount <= 0 ) {
			wp_die( __( 'Montant invalide.', 'dc25-vouchers' ) );
		}

		$prefix = $product->get_coupon_prefix();
		$validity_days = $product->get_validity_days();
		$coupon = DC25_Coupon_Service::create_coupon( $amount, $prefix, $validity_days );

		if ( is_wp_error( $coupon ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dc25-vouchers&message=coupon_error&error=' . urlencode( $coupon->get_error_message() ) ) );
			exit;
		}

		/** @var WC_Coupon $coupon */
		$coupon_code = $coupon->get_code();
		$expiry_date_obj = $coupon->get_date_expires();
		if ( $expiry_date_obj ) {
			$expiry_date = $expiry_date_obj->date( 'Y-m-d' );
		} else {
			$expiry_timestamp = current_time( 'timestamp' ) + ( $validity_days * DAY_IN_SECONDS );
			$expiry_date = gmdate( 'Y-m-d', $expiry_timestamp );
		}

		// Sauvegarder le code coupon
		$item->update_meta_data( '_dc25_gv_coupon_code', $coupon_code );
		$item->save();

		// Générer le PDF
		$pdf_data = [
			'coupon_code'    => $coupon_code,
			'amount'         => $amount,
			'expiry_date'    => $expiry_date,
			'message'        => $item->get_meta( '_dc25_gv_message' ),
			'recipient_name' => $item->get_meta( '_dc25_gv_recipient_name' ),
		];

		// Le PDF sera généré à la volée lors du téléchargement

		// Rediriger avec un message de succès
		wp_safe_redirect( admin_url( 'admin.php?page=dc25-vouchers&message=coupon_generated&code=' . urlencode( $coupon_code ) ) );
		exit;
	}
}

