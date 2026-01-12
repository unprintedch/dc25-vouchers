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
		add_action( 'admin_post_dc25_save_pdf_preview', [ $this, 'handle_save_pdf_preview' ] );
		add_action( 'admin_post_dc25_preview_pdf', [ $this, 'handle_preview_pdf' ] );
		add_action( 'admin_post_dc25_preview_html', [ $this, 'handle_preview_html' ] );
		add_action( 'admin_post_dc25_save_verification_settings', [ $this, 'handle_save_verification_settings' ] );
		add_action( 'admin_post_dc25_save_email_settings', [ $this, 'handle_save_email_settings' ] );

		// Debug temporaire: afficher la valeur courante du contenu d'email sur la page Bons cadeaux.
		add_action(
			'admin_notices',
			static function () {
				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					return;
				}

				if ( ! isset( $_GET['page'] ) || 'dc25-vouchers' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return;
				}

				$val = get_option( 'dc25_gv_recipient_email_content', '(vide)' );

				// Surligner si valeur par défaut (pour aider au debug).
				$is_default = false !== strpos(
					$val,
					'Bonjour {name},<br><br>Vous avez reçu un bon cadeau d\'un montant de {amount}.<br><br>Code: {coupon_code}<br><br>Message: {message}<br><br>Le PDF de votre bon cadeau est joint à cet email.<br><br>{download_link}<br><br>Cordialement,<br>{site_name}'
				);
				$extra = $is_default ? '<em style="color:#cc0000;">(valeur par défaut détectée)</em><br>' : '';

				echo '<div class="notice notice-info"><p><strong>recipient_email_content :</strong><br>' . $extra . wp_kses_post( $val ) . '</p></div>';
			}
		);
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

		wp_enqueue_media();

		// Charger l'éditeur WordPress pour l'onglet email
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'list';
		if ( 'email' === $active_tab ) {
			wp_enqueue_editor();
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
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'list';

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
				case 'appearance_saved':
					echo '<div class="notice notice-success is-dismissible"><p>';
					esc_html_e( 'Apparence du PDF enregistrée.', 'dc25-vouchers' );
					echo '</p></div>';
					break;
				case 'verification_saved':
					echo '<div class="notice notice-success is-dismissible"><p>';
					esc_html_e( 'Réglages de vérification enregistrés.', 'dc25-vouchers' );
					echo '</p></div>';
					break;
				case 'email_saved':
					echo '<div class="notice notice-success is-dismissible"><p>';
					esc_html_e( 'Réglages email enregistrés.', 'dc25-vouchers' );
					echo '</p></div>';
					break;
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bons cadeaux', 'dc25-vouchers' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dc25-vouchers&tab=list' ) ); ?>" class="nav-tab <?php echo 'list' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bons cadeaux', 'dc25-vouchers' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dc25-vouchers&tab=pdf-preview' ) ); ?>" class="nav-tab <?php echo 'pdf-preview' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Aperçu PDF', 'dc25-vouchers' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dc25-vouchers&tab=verification' ) ); ?>" class="nav-tab <?php echo 'verification' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Réglages vérification', 'dc25-vouchers' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dc25-vouchers&tab=email' ) ); ?>" class="nav-tab <?php echo 'email' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Réglages email', 'dc25-vouchers' ); ?></a>
			</h2>

			<div class="dc25-vouchers-admin">
				<?php
				if ( 'pdf-preview' === $active_tab ) {
					$this->render_pdf_preview_tab();
				} elseif ( 'verification' === $active_tab ) {
					$this->render_verification_tab();
				} elseif ( 'email' === $active_tab ) {
					$this->render_email_tab();
				} else {
					$this->render_list_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Afficher l'onglet liste
	 */
	private function render_list_tab(): void {
		$vouchers = $this->get_all_vouchers();
		if ( empty( $vouchers ) ) : ?>
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
							<td colspan="8">
								<div class="dc25-details-content">
									<h3><?php esc_html_e( 'Détails du bon cadeau', 'dc25-vouchers' ); ?></h3>
									<div class="dc25-details-grid">
										<div class="dc25-detail-item">
											<span class="dc25-detail-label"><?php esc_html_e( 'Code coupon:', 'dc25-vouchers' ); ?></span>
											<span class="dc25-detail-value">
												<?php if ( ! empty( $voucher['coupon_edit_url'] ) ) : ?>
													<a href="<?php echo esc_url( $voucher['coupon_edit_url'] ); ?>" target="_blank">
														<code><?php echo esc_html( $voucher['coupon_code'] ); ?></code>
													</a>
												<?php else : ?>
													<code><?php echo esc_html( $voucher['coupon_code'] ); ?></code>
												<?php endif; ?>
											</span>
										</div>
										<div class="dc25-detail-item">
											<span class="dc25-detail-label"><?php esc_html_e( 'Montant:', 'dc25-vouchers' ); ?></span>
											<span class="dc25-detail-value"><?php echo wp_kses_post( wc_price( $voucher['amount'] ) ); ?></span>
										</div>
										<?php if ( ! empty( $voucher['message'] ) ) : ?>
											<div class="dc25-detail-item">
												<span class="dc25-detail-label"><?php esc_html_e( 'Message:', 'dc25-vouchers' ); ?></span>
												<span class="dc25-detail-value"><?php echo esc_html( $voucher['message'] ); ?></span>
											</div>
										<?php endif; ?>
										<div class="dc25-detail-item">
											<span class="dc25-detail-label"><?php esc_html_e( 'Date de commande:', 'dc25-vouchers' ); ?></span>
											<span class="dc25-detail-value"><?php echo esc_html( $voucher['order_date'] ); ?></span>
										</div>
										<div class="dc25-detail-item">
											<span class="dc25-detail-label"><?php esc_html_e( 'Date d\'expiration:', 'dc25-vouchers' ); ?></span>
											<span class="dc25-detail-value"><?php echo esc_html( $voucher['expiry_date'] ); ?></span>
										</div>
										<div class="dc25-detail-item">
											<span class="dc25-detail-label"><?php esc_html_e( 'Statut:', 'dc25-vouchers' ); ?></span>
											<span class="dc25-detail-value">
												<span class="dc25-status dc25-status-<?php echo esc_attr( $voucher['status'] ); ?>" title="<?php esc_attr_e( 'Statut du coupon WooCommerce', 'dc25-vouchers' ); ?>">
													<?php echo esc_html( $voucher['status_label'] ); ?>
												</span>
											</span>
										</div>
										<?php if ( ! empty( $voucher['recipient_name'] ) ) : ?>
											<div class="dc25-detail-item">
												<span class="dc25-detail-label"><?php esc_html_e( 'Destinataire:', 'dc25-vouchers' ); ?></span>
												<span class="dc25-detail-value">
													<?php echo esc_html( $voucher['recipient_name'] ); ?>
													<?php if ( ! empty( $voucher['recipient_email'] ) ) : ?>
														<br><small><?php echo esc_html( $voucher['recipient_email'] ); ?></small>
													<?php endif; ?>
												</span>
											</div>
										<?php endif; ?>
										<?php if ( ! empty( $voucher['physical'] ) && 'yes' === $voucher['physical'] ) : ?>
											<div class="dc25-detail-item">
												<span class="dc25-detail-label"><?php esc_html_e( 'Envoi physique:', 'dc25-vouchers' ); ?></span>
												<span class="dc25-detail-value"><?php esc_html_e( 'Oui (+ 5 CHF)', 'dc25-vouchers' ); ?></span>
											</div>
										<?php endif; ?>
										<?php if ( ! empty( $voucher['redeemed_by'] ) ) : ?>
											<div class="dc25-detail-item">
												<span class="dc25-detail-label"><?php esc_html_e( 'Encaissé par:', 'dc25-vouchers' ); ?></span>
												<span class="dc25-detail-value">
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
												</span>
											</div>
										<?php endif; ?>
										<?php if ( ! empty( $voucher['partner_label'] ) ) : ?>
											<div class="dc25-detail-item">
												<span class="dc25-detail-label"><?php esc_html_e( 'Partenaire:', 'dc25-vouchers' ); ?></span>
												<span class="dc25-detail-value">
													<?php if ( ! empty( $voucher['partner_edit_url'] ) ) : ?>
														<a href="<?php echo esc_url( $voucher['partner_edit_url'] ); ?>" target="_blank">
															<?php echo esc_html( $voucher['partner_label'] ); ?>
														</a>
													<?php else : ?>
														<?php echo esc_html( $voucher['partner_label'] ); ?>
													<?php endif; ?>
													<?php if ( ! empty( $voucher['partner_type_label'] ) ) : ?>
														<br><small><?php echo esc_html( $voucher['partner_type_label'] ); ?></small>
													<?php endif; ?>
												</span>
											</div>
										<?php endif; ?>
										<?php if ( ! empty( $voucher['receipt_file'] ) ) : ?>
											<div class="dc25-detail-item">
												<span class="dc25-detail-label"><?php esc_html_e( 'Justificatif:', 'dc25-vouchers' ); ?></span>
												<span class="dc25-detail-value">
													<a href="<?php echo esc_url( $voucher['receipt_file'] ); ?>" target="_blank" class="button button-small">
														<?php esc_html_e( 'Voir le fichier', 'dc25-vouchers' ); ?>
													</a>
												</span>
											</div>
										<?php endif; ?>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<script>
			jQuery(document).ready(function($) {
				$('.dc25-view-details').on('click', function() {
					var voucherId = $(this).data('voucher-id');
					$('#dc25-details-' + voucherId).toggle();
				});
			});
			</script>
		<?php endif;
	}

	/**
	 * Afficher l'onglet d'aperçu PDF
	 */
	private function render_pdf_preview_tab(): void {
		$settings = DC25_Settings::get_instance();
		?>
		<div class="dc25-pdf-preview">
			<p><?php esc_html_e( 'Ajustez l’apparence du PDF et générez un aperçu statique.', 'dc25-vouchers' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 860px;">
				<?php wp_nonce_field( 'dc25_save_pdf_preview' ); ?>
				<input type="hidden" name="action" value="dc25_save_pdf_preview" />
				<table class="form-table">
					<tr>
						<th><label for="dc25_gv_pdf_background_url"><?php esc_html_e( 'Image de fond', 'dc25-vouchers' ); ?></label></th>
						<td>
							<input type="text" id="dc25_gv_pdf_background_url" name="dc25_gv_pdf_background_url" class="regular-text" value="<?php echo esc_attr( $settings->get_pdf_background_url() ); ?>" placeholder="https://..." />
							<button type="button" class="button dc25-media-picker" data-target="dc25_gv_pdf_background_url"><?php esc_html_e( 'Choisir dans la bibliothèque', 'dc25-vouchers' ); ?></button>
							<p class="description"><?php esc_html_e( 'Utilisez une image large, idéalement 1748x1240 pour A6 horizontal.', 'dc25-vouchers' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="dc25_gv_pdf_logo_url"><?php esc_html_e( 'Logo PDF', 'dc25-vouchers' ); ?></label></th>
						<td>
							<input type="text" id="dc25_gv_pdf_logo_url" name="dc25_gv_pdf_logo_url" class="regular-text" value="<?php echo esc_attr( $settings->get_logo_url() ); ?>" placeholder="https://..." />
							<button type="button" class="button dc25-media-picker" data-target="dc25_gv_pdf_logo_url"><?php esc_html_e( 'Choisir dans la bibliothèque', 'dc25-vouchers' ); ?></button>
							<?php
							$logo_url = $settings->get_logo_url();
							if ( ! empty( $logo_url ) ) :
								?>
								<div style="margin-top: 10px;">
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo preview" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #fff;" />
									<p class="description" style="margin-top: 5px;">
										<?php esc_html_e( 'Note: Les formats PNG et JPG sont recommandés. Le SVG peut ne pas s\'afficher correctement dans le PDF.', 'dc25-vouchers' ); ?>
									</p>
								</div>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="dc25_gv_pdf_layout_preset"><?php esc_html_e( 'Preset', 'dc25-vouchers' ); ?></label></th>
						<td>
							<select name="dc25_gv_pdf_layout_preset" id="dc25_gv_pdf_layout_preset">
								<option value="classic" <?php selected( $settings->get_pdf_layout_preset(), 'classic' ); ?>><?php esc_html_e( 'Classique', 'dc25-vouchers' ); ?></option>
								<option value="bordered" <?php selected( $settings->get_pdf_layout_preset(), 'bordered' ); ?>><?php esc_html_e( 'Encadré', 'dc25-vouchers' ); ?></option>
								<option value="photo" <?php selected( $settings->get_pdf_layout_preset(), 'photo' ); ?>><?php esc_html_e( 'Carte photo', 'dc25-vouchers' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="dc25_gv_pdf_font_family"><?php esc_html_e( 'Police', 'dc25-vouchers' ); ?></label></th>
						<td>
							<select name="dc25_gv_pdf_font_family" id="dc25_gv_pdf_font_family">
								<option value="sans" <?php selected( $settings->get_pdf_font_family(), 'sans' ); ?>><?php esc_html_e( 'Sans-serif moderne', 'dc25-vouchers' ); ?></option>
								<option value="serif" <?php selected( $settings->get_pdf_font_family(), 'serif' ); ?>><?php esc_html_e( 'Serif élégant', 'dc25-vouchers' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Couleurs', 'dc25-vouchers' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Principale', 'dc25-vouchers' ); ?> <input type="color" name="dc25_gv_pdf_theme_color" value="<?php echo esc_attr( $settings->get_theme_color() ); ?>" /></label>
							<label style="margin-left:12px;"><?php esc_html_e( 'Accent', 'dc25-vouchers' ); ?> <input type="color" name="dc25_gv_pdf_accent_color" value="<?php echo esc_attr( $settings->get_accent_color() ); ?>" /></label>
							<label style="margin-left:12px;"><?php esc_html_e( 'Texte', 'dc25-vouchers' ); ?> <input type="color" name="dc25_gv_pdf_text_color" value="<?php echo esc_attr( $settings->get_text_color() ); ?>" /></label>
						</td>
					</tr>
					<tr>
						<th><label for="dc25_gv_pdf_title_text"><?php esc_html_e( 'Titre', 'dc25-vouchers' ); ?></label></th>
						<td><input type="text" id="dc25_gv_pdf_title_text" name="dc25_gv_pdf_title_text" class="regular-text" value="<?php echo esc_attr( $settings->get_pdf_title_text() ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="dc25_gv_pdf_subtitle_text"><?php esc_html_e( 'Sous-titre', 'dc25-vouchers' ); ?></label></th>
						<td><input type="text" id="dc25_gv_pdf_subtitle_text" name="dc25_gv_pdf_subtitle_text" class="regular-text" value="<?php echo esc_attr( $settings->get_pdf_subtitle_text() ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="dc25_gv_pdf_footer_text"><?php esc_html_e( 'Pied de page', 'dc25-vouchers' ); ?></label></th>
						<td><input type="text" id="dc25_gv_pdf_footer_text" name="dc25_gv_pdf_footer_text" class="regular-text" value="<?php echo esc_attr( $settings->get_pdf_footer_text() ); ?>" /></td>
					</tr>
				</table>
				<p>
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Enregistrer l’apparence', 'dc25-vouchers' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dc25_preview_html' ), 'dc25_preview_html' ) ); ?>" target="_blank" style="background: #2271b1; color: #fff; border-color: #2271b1;"><?php esc_html_e( 'Aperçu HTML (débogage)', 'dc25-vouchers' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dc25_preview_pdf' ), 'dc25_preview_pdf' ) ); ?>" target="_blank"><?php esc_html_e( 'Ouvrir un aperçu PDF', 'dc25-vouchers' ); ?></a>
				</p>
				<p class="description"><?php esc_html_e( 'L’aperçu utilise des données fictives et n’affecte pas les vouchers existants.', 'dc25-vouchers' ); ?></p>
			</form>
			<script>
			jQuery(function($){
				$('.dc25-media-picker').on('click', function(e){
					e.preventDefault();
					var target = $(this).data('target');
					var frame = wp.media({
						title: '<?php echo esc_js( __( 'Sélectionner un média', 'dc25-vouchers' ) ); ?>',
						button: { text: '<?php echo esc_js( __( 'Utiliser ce fichier', 'dc25-vouchers' ) ); ?>' },
						multiple: false,
						library: { type: 'image' }
					});
					frame.on('select', function(){
						var attachment = frame.state().get('selection').first().toJSON();
						$('#' + target).val(attachment.url);
						
						// Mettre à jour la prévisualisation si c'est le logo
						if (target === 'dc25_gv_pdf_logo_url') {
							var previewHtml = '<div style="margin-top: 10px;"><img src="' + attachment.url + '" alt="Logo preview" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #fff;" /><p class="description" style="margin-top: 5px;"><?php echo esc_js( __( 'Note: Les formats PNG et JPG sont recommandés. Le SVG peut ne pas s\'afficher correctement dans le PDF.', 'dc25-vouchers' ) ); ?></p></div>';
							$('#' + target).closest('td').find('div[style*="margin-top: 10px"]').remove();
							$('#' + target).closest('td').append(previewHtml);
						}
					});
					frame.open();
				});
			});
			</script>
		</div>
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
			$partner_cpt_id = (int) $order_item->get_meta( '_dc25_gv_selected_cpt_id' );
			$partner_cpt_type = $order_item->get_meta( '_dc25_gv_selected_cpt_type' );

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
			$partner_edit_url = '';
			$partner_label = '';
			$partner_type_label = '';
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
					// Si le coupon stocke aussi le partenaire, prioriser cette valeur
					$partner_cpt_id = $partner_cpt_id ?: (int) $coupon_obj->get_meta( '_dc25_redeemed_at_cpt_id' );
					$partner_cpt_type = $partner_cpt_type ?: $coupon_obj->get_meta( '_dc25_redeemed_at_cpt_type' );
				}
			}

			// Convertir les informations CPT partenaire en libellé + lien
			if ( $partner_cpt_id > 0 ) {
				$partner_post = get_post( $partner_cpt_id );
				if ( $partner_post && 'trash' !== $partner_post->post_status ) {
					$partner_label = get_the_title( $partner_post );
					$partner_edit_url = get_edit_post_link( $partner_post, '' );
					if ( ! empty( $partner_cpt_type ) ) {
						$type_obj = get_post_type_object( $partner_cpt_type );
						if ( $type_obj ) {
							$partner_type_label = $type_obj->labels->singular_name ?? $type_obj->label ?? $partner_cpt_type;
						} else {
							$partner_type_label = $partner_cpt_type;
						}
					}
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
				'partner_label'  => $partner_label,
				'partner_edit_url' => $partner_edit_url,
				'partner_type_label' => $partner_type_label,
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
			'from_name'      => $item->get_meta( '_dc25_gv_from_name' ) ?: trim( $order->get_formatted_billing_full_name() ),
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

	/**
	 * Sauvegarder les réglages d'apparence depuis l'onglet d'aperçu
	 */
	public function handle_save_pdf_preview(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Vous n\'avez pas les permissions nécessaires.', 'dc25-vouchers' ) );
		}

		check_admin_referer( 'dc25_save_pdf_preview' );

		$map = [
			'dc25_gv_pdf_background_url' => 'esc_url_raw',
			'dc25_gv_pdf_logo_url'       => 'esc_url_raw',
			'dc25_gv_pdf_title_text'     => 'sanitize_text_field',
			'dc25_gv_pdf_subtitle_text'  => 'sanitize_text_field',
			'dc25_gv_pdf_footer_text'    => 'sanitize_text_field',
			'dc25_gv_pdf_theme_color'    => 'sanitize_hex_color',
			'dc25_gv_pdf_accent_color'   => 'sanitize_hex_color',
			'dc25_gv_pdf_text_color'     => 'sanitize_hex_color',
			'dc25_gv_pdf_layout_preset'  => 'sanitize_text_field',
			'dc25_gv_pdf_font_family'    => 'sanitize_text_field',
		];

		foreach ( $map as $field => $callback ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = call_user_func( $callback, wp_unslash( $_POST[ $field ] ) );
				update_option( $field, $value );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=dc25-vouchers&tab=pdf-preview&message=appearance_saved' ) );
		exit;
	}

	/**
	 * Générer un PDF d'aperçu statique
	 */
	public function handle_preview_pdf(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Vous n\'avez pas les permissions nécessaires.', 'dc25-vouchers' ) );
		}

		check_admin_referer( 'dc25_preview_pdf' );

		$sample_data = [
			'coupon_code'    => 'NVT-APERCU-001',
			'amount'         => 120,
			'expiry_date'    => gmdate( 'Y-m-d', strtotime( '+12 months' ) ),
			'message'        => __( 'Offert avec plaisir pour célébrer cette occasion.', 'dc25-vouchers' ),
			'recipient_name' => __( 'Invité(e)', 'dc25-vouchers' ),
			'from_name'      => __( 'Offrant', 'dc25-vouchers' ),
		];

		$pdf_content = DC25_PDF_Service::generate_pdf_content( $sample_data, true );

		if ( is_wp_error( $pdf_content ) ) {
			wp_die( sprintf( __( 'Erreur lors de la génération du PDF: %s', 'dc25-vouchers' ), $pdf_content->get_error_message() ) );
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="aperçu-bon-cadeau.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_content ) );
		echo $pdf_content;
		exit;
	}

	/**
	 * Générer un aperçu HTML pour débogage
	 */
	public function handle_preview_html(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Vous n\'avez pas les permissions nécessaires.', 'dc25-vouchers' ) );
		}

		check_admin_referer( 'dc25_preview_html' );

		$sample_data = [
			'coupon_code'    => 'NVT-APERCU-001',
			'amount'         => 120,
			'expiry_date'    => gmdate( 'Y-m-d', strtotime( '+12 months' ) ),
			'message'        => __( 'Offert avec plaisir pour célébrer cette occasion.', 'dc25-vouchers' ),
			'recipient_name' => __( 'Invité(e)', 'dc25-vouchers' ),
			'from_name'      => __( 'Offrant', 'dc25-vouchers' ),
		];

		// Générer le HTML directement sans passer par DomPDF
		$html = DC25_PDF_Service::load_template_for_preview( $sample_data );

		if ( is_wp_error( $html ) ) {
			wp_die( sprintf( __( 'Erreur lors de la génération du HTML: %s', 'dc25-vouchers' ), $html->get_error_message() ) );
		}

		// Afficher le HTML directement
		echo $html;
		exit;
	}

	/**
	 * Afficher l'onglet Réglages vérification (CPT disponibles)
	 */
	private function render_verification_tab(): void {
		if ( ! class_exists( 'DC25_Settings' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Réglages indisponibles.', 'dc25-vouchers' ) . '</p></div>';
			return;
		}

		$settings             = DC25_Settings::get_instance();
		$allowed_cpt          = $settings->get_allowed_cpt_for_verification();
		$available_post_types = $this->get_available_post_types();
		?>
		<div class="dc25-verification-settings">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'dc25_save_verification_settings', 'dc25_verification_nonce' ); ?>
				<input type="hidden" name="action" value="dc25_save_verification_settings" />

				<h2><?php esc_html_e( 'Types de contenus disponibles', 'dc25-vouchers' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Cochez les types de contenus (CPT) qui apparaîtront dans la liste déroulante lors de la vérification d\'un bon cadeau.', 'dc25-vouchers' ); ?></p>

				<table class="form-table">
					<tbody>
					<?php if ( empty( $available_post_types ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Aucun CPT disponible', 'dc25-vouchers' ); ?></th>
							<td><?php esc_html_e( 'Aucun type de contenu public trouvé.', 'dc25-vouchers' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $available_post_types as $slug => $label ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="dc25_gv_cpt[<?php echo esc_attr( $slug ); ?>]" value="yes" <?php checked( in_array( $slug, $allowed_cpt, true ), true ); ?> />
										<?php esc_html_e( 'Activer dans la liste de vérification', 'dc25-vouchers' ); ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Enregistrer', 'dc25-vouchers' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handler pour sauvegarder les réglages de vérification
	 */
	public function handle_save_verification_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'dc25-vouchers' ) );
		}

		check_admin_referer( 'dc25_save_verification_settings', 'dc25_verification_nonce' );

		$available_post_types = $this->get_available_post_types();
		$selected             = isset( $_POST['dc25_gv_cpt'] ) && is_array( $_POST['dc25_gv_cpt'] ) ? array_keys( $_POST['dc25_gv_cpt'] ) : [];

		// Sanitize & filter
		$selected = array_filter(
			array_map(
				function( $slug ) use ( $available_post_types ) {
					$slug = sanitize_key( $slug );
					return array_key_exists( $slug, $available_post_types ) ? $slug : '';
				},
				$selected
			)
		);

		// Si vide, par défaut producer + group si présents
		if ( empty( $selected ) ) {
			foreach ( [ 'producer', 'group' ] as $default_slug ) {
				if ( isset( $available_post_types[ $default_slug ] ) ) {
					$selected[] = $default_slug;
				}
			}
		}

		update_option( 'dc25_gv_allowed_cpt_verification', $selected );

		wp_safe_redirect( admin_url( 'admin.php?page=dc25-vouchers&tab=verification&message=verification_saved' ) );
		exit;
	}

	/**
	 * CPT publics disponibles (utilitaire pour l'admin)
	 *
	 * @return array slug => label
	 */
	private function get_available_post_types(): array {
		$post_types = get_post_types( [ 'public' => true, 'show_ui' => true ], 'objects' );
		$options    = [];

		foreach ( $post_types as $post_type ) {
			// Exclure types WP par défaut
			if ( in_array( $post_type->name, [ 'attachment', 'revision', 'nav_menu_item', 'page', 'post' ], true ) ) {
				continue;
			}
			$options[ $post_type->name ] = $post_type->label . ' (' . $post_type->name . ')';
		}

		return $options;
	}

	/**
	 * Afficher l'onglet Réglages email
	 */
	private function render_email_tab(): void {
		if ( ! class_exists( 'DC25_Settings' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Réglages indisponibles.', 'dc25-vouchers' ) . '</p></div>';
			return;
		}

		$settings = DC25_Settings::get_instance();
		?>
		<div class="dc25-email-settings">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'dc25_save_email_settings', 'dc25_email_nonce' ); ?>
				<input type="hidden" name="action" value="dc25_save_email_settings" />

				<h2><?php esc_html_e( 'Configuration de l\'email de confirmation', 'dc25-vouchers' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configurez le contenu de l\'email envoyé au destinataire du bon cadeau.', 'dc25-vouchers' ); ?></p>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dc25_gv_recipient_email_enabled"><?php esc_html_e( 'Activer email destinataire', 'dc25-vouchers' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="dc25_gv_recipient_email_enabled" id="dc25_gv_recipient_email_enabled" value="yes" <?php checked( $settings->is_recipient_email_enabled(), true ); ?> />
									<?php esc_html_e( 'Envoyer un email au destinataire si son adresse est renseignée.', 'dc25-vouchers' ); ?>
								</label>
							</td>
						</tr>
						<?php
						// Obtenir la langue courante de l'admin
						$current_language = 'fr';
						if ( function_exists( 'wpml_get_current_language' ) ) {
							$current_language = wpml_get_current_language();
						} elseif ( function_exists( 'icl_get_current_language' ) ) {
							$current_language = icl_get_current_language();
						} elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
							$current_language = ICL_LANGUAGE_CODE;
						}
						
						// Obtenir la langue par défaut
						$default_language = 'fr';
						if ( function_exists( 'wpml_get_default_language' ) ) {
							$default_language = wpml_get_default_language();
						} elseif ( function_exists( 'icl_get_default_language' ) ) {
							$default_language = icl_get_default_language();
						}
						
						// Obtenir le nom de la langue
						$lang_name = 'Français';
						if ( function_exists( 'wpml_get_language_details' ) ) {
							$lang_details = wpml_get_language_details( $current_language );
							$lang_name = $lang_details['native_name'] ?? $current_language;
						} elseif ( function_exists( 'icl_get_languages' ) ) {
							$languages = icl_get_languages( 'skip_missing=0' );
							if ( isset( $languages[ $current_language ] ) ) {
								$lang_name = $languages[ $current_language ]['native_name'] ?? $current_language;
							}
						}
						
						// Construire le suffixe selon la langue
						$is_default = ( $current_language === $default_language );
						$lang_suffix = $is_default ? '' : '_' . $current_language;
						
						// Récupérer les valeurs pour la langue courante
						$email_subject = get_option( 'dc25_gv_recipient_email_subject' . $lang_suffix, '' );
						$email_content = get_option( 'dc25_gv_recipient_email_content' . $lang_suffix, '' );
						
						// Si vide, utiliser les valeurs par défaut
						if ( empty( $email_subject ) ) {
							$email_subject = $settings->get_recipient_email_subject();
						}
						if ( empty( $email_content ) ) {
							$email_content = $settings->get_recipient_email_content();
						}
						?>
						<tr>
							<th scope="row" colspan="2">
								<p style="margin: 10px 0; color: #666;">
									<?php 
									printf(
										/* translators: %s: nom de la langue */
										esc_html__( 'Vous éditez actuellement les emails pour la langue : %s', 'dc25-vouchers' ),
										'<strong>' . esc_html( $lang_name ) . '</strong>'
									);
									?>
									<?php if ( $is_default ) : ?>
										<span style="font-size: 12px; color: #666;">(<?php esc_html_e( 'Langue par défaut', 'dc25-vouchers' ); ?>)</span>
									<?php endif; ?>
								</p>
								<p class="description" style="margin: 5px 0;">
									<?php esc_html_e( 'Pour éditer les emails dans une autre langue, changez la langue de l\'interface WordPress dans votre profil utilisateur ou utilisez le sélecteur de langue WPML.', 'dc25-vouchers' ); ?>
								</p>
							</th>
						</tr>
						<tr>
							<th scope="row">
								<label for="dc25_gv_recipient_email_subject">
									<?php esc_html_e( 'Sujet de l\'email', 'dc25-vouchers' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									name="dc25_gv_recipient_email_subject" 
									id="dc25_gv_recipient_email_subject" 
									value="<?php echo esc_attr( $email_subject ); ?>" 
									class="regular-text" 
								/>
								<input type="hidden" name="dc25_gv_current_language" value="<?php echo esc_attr( $current_language ); ?>" />
								<input type="hidden" name="dc25_gv_default_language" value="<?php echo esc_attr( $default_language ); ?>" />
								<p class="description">
									<?php esc_html_e( 'Placeholders disponibles: {name}, {coupon_code}, {amount}, {message}, {site_name}, {download_url}', 'dc25-vouchers' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dc25_gv_recipient_email_content">
									<?php esc_html_e( 'Contenu de l\'email', 'dc25-vouchers' ); ?>
								</label>
							</th>
							<td>
								<?php
								wp_editor(
									$email_content,
									'dc25_gv_recipient_email_content',
									[
										'textarea_name' => 'dc25_gv_recipient_email_content',
										'textarea_rows' => 15,
										'media_buttons' => false,
										'teeny'         => false,
									]
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Placeholders disponibles: {name}, {coupon_code}, {amount}, {message}, {site_name}, {download_link} (bouton HTML), {download_url} (URL seule). Le PDF est aussi joint à l\'email.', 'dc25-vouchers' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Enregistrer', 'dc25-vouchers' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handler pour sauvegarder les réglages email
	 */
	public function handle_save_email_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'dc25-vouchers' ) );
		}

		check_admin_referer( 'dc25_save_email_settings', 'dc25_email_nonce' );

		// Sauvegarder les réglages (les IDs doivent correspondre à ceux utilisés dans DC25_Settings)
		$enabled = isset( $_POST['dc25_gv_recipient_email_enabled'] ) && 'yes' === $_POST['dc25_gv_recipient_email_enabled'] ? 'yes' : 'no';
		update_option( 'dc25_gv_recipient_email_enabled', $enabled );

		// Obtenir la langue courante depuis le formulaire ou détecter
		$current_language = isset( $_POST['dc25_gv_current_language'] ) ? sanitize_text_field( wp_unslash( $_POST['dc25_gv_current_language'] ) ) : 'fr';
		$default_language = isset( $_POST['dc25_gv_default_language'] ) ? sanitize_text_field( wp_unslash( $_POST['dc25_gv_default_language'] ) ) : 'fr';
		
		// Si pas défini, détecter la langue courante
		if ( empty( $current_language ) || $current_language === 'fr' ) {
			if ( function_exists( 'wpml_get_current_language' ) ) {
				$current_language = wpml_get_current_language();
			} elseif ( function_exists( 'icl_get_current_language' ) ) {
				$current_language = icl_get_current_language();
			} elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
				$current_language = ICL_LANGUAGE_CODE;
			}
		}
		
		// Construire le suffixe selon la langue
		$is_default = ( $current_language === $default_language );
		$lang_suffix = $is_default ? '' : '_' . $current_language;
		
		// Sauvegarder pour la langue courante
		$subject_key = 'dc25_gv_recipient_email_subject' . $lang_suffix;
		$content_key = 'dc25_gv_recipient_email_content' . $lang_suffix;
		
		$subject = isset( $_POST['dc25_gv_recipient_email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['dc25_gv_recipient_email_subject'] ) ) : '';
		if ( empty( $subject ) && $is_default ) {
			$subject = __( 'Vous avez reçu un bon cadeau !', 'dc25-vouchers' );
		}
		update_option( $subject_key, $subject );

		$content = isset( $_POST['dc25_gv_recipient_email_content'] ) ? wp_kses_post( wp_unslash( $_POST['dc25_gv_recipient_email_content'] ) ) : '';
		if ( empty( $content ) && $is_default ) {
			$content = __( 'Bonjour {name},<br><br>Vous avez reçu un bon cadeau d\'un montant de {amount}.<br><br>Code: {coupon_code}<br><br>Message: {message}<br><br>Le PDF de votre bon cadeau est joint à cet email.<br><br>{download_link}<br><br>Cordialement,<br>{site_name}', 'dc25-vouchers' );
		}
		update_option( $content_key, $content );

		wp_safe_redirect( admin_url( 'admin.php?page=dc25-vouchers&tab=email&message=email_saved' ) );
		exit;
	}
}

