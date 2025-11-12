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
		add_action( 'template_redirect', [ $this, 'handle_verify_request' ] );
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
	}

	/**
	 * Ajouter les variables de requête
	 *
	 * @param array $vars Variables existantes.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'dc25_gv_verify';
		return $vars;
	}

	/**
	 * Gérer la requête de vérification
	 */
	public function handle_verify_request(): void {
		// Vérifier via query var ou paramètre GET
		$verify = get_query_var( 'dc25_gv_verify' );
		if ( empty( $verify ) && isset( $_GET['dc25_gv_verify'] ) ) {
			$verify = sanitize_text_field( $_GET['dc25_gv_verify'] );
		}

		if ( empty( $verify ) || '1' === $verify ) {
			return; // Pas de code fourni
		}

		$coupon_code = sanitize_text_field( $verify );
		$status = DC25_Coupon_Service::get_coupon_status( $coupon_code );

		// Headers
		status_header( 200 );
		nocache_headers();

		// Afficher la page de vérification
		$this->display_verify_page( $coupon_code, $status );
		exit;
	}

	/**
	 * Afficher la page de vérification
	 *
	 * @param string $coupon_code Code du coupon.
	 * @param array  $status Statut du coupon.
	 */
	private function display_verify_page( string $coupon_code, array $status ): void {
		$coupon = new WC_Coupon( $coupon_code );
		$amount = $coupon->get_id() ? $coupon->get_amount() : 0;

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'Vérification du bon cadeau', 'dc25-vouchers' ); ?> - <?php bloginfo( 'name' ); ?></title>
			<style>
				* {
					margin: 0;
					padding: 0;
					box-sizing: border-box;
				}
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					background: #f5f5f5;
					color: #333;
					line-height: 1.6;
					padding: 20px;
				}
				.container {
					max-width: 600px;
					margin: 50px auto;
					background: #fff;
					border-radius: 12px;
					box-shadow: 0 2px 10px rgba(0,0,0,0.1);
					padding: 40px;
					text-align: center;
				}
				h1 {
					font-size: 28px;
					margin-bottom: 20px;
					color: #333;
				}
				.status-icon {
					font-size: 64px;
					margin: 20px 0;
				}
				.status-valid { color: #28a745; }
				.status-expired { color: #ffc107; }
				.status-used { color: #dc3545; }
				.status-invalid { color: #6c757d; }
				.status-message {
					font-size: 18px;
					font-weight: 600;
					margin: 20px 0;
				}
				.details {
					background: #f8f9fa;
					border-radius: 8px;
					padding: 20px;
					margin: 20px 0;
					text-align: left;
				}
				.details p {
					margin: 10px 0;
				}
				.details strong {
					display: inline-block;
					width: 150px;
				}
				.code {
					font-family: monospace;
					font-size: 20px;
					font-weight: bold;
					color: #007bff;
					letter-spacing: 2px;
					margin: 10px 0;
				}
			</style>
		</head>
		<body>
			<div class="container">
				<h1><?php esc_html_e( 'Vérification du bon cadeau', 'dc25-vouchers' ); ?></h1>

				<?php if ( 'valid' === $status['status'] ) : ?>
					<div class="status-icon status-valid">✅</div>
					<div class="status-message status-valid"><?php echo esc_html( $status['message'] ); ?></div>
					<div class="details">
						<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="code"><?php echo esc_html( $coupon_code ); ?></span></p>
						<p><strong><?php esc_html_e( 'Montant:', 'dc25-vouchers' ); ?></strong> <?php echo wp_kses_post( wc_price( $status['amount'] ) ); ?></p>
						<?php if ( ! empty( $status['expiry_date'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Valable jusqu\'au:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $status['expiry_date'] ) ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php elseif ( 'expired' === $status['status'] ) : ?>
					<div class="status-icon status-expired">⏰</div>
					<div class="status-message status-expired"><?php echo esc_html( $status['message'] ); ?></div>
					<div class="details">
						<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="code"><?php echo esc_html( $coupon_code ); ?></span></p>
						<?php if ( ! empty( $status['expiry_date'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Date d\'expiration:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $status['expiry_date'] ) ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php elseif ( 'used' === $status['status'] ) : ?>
					<div class="status-icon status-used">❌</div>
					<div class="status-message status-used"><?php echo esc_html( $status['message'] ); ?></div>
					<div class="details">
						<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="code"><?php echo esc_html( $coupon_code ); ?></span></p>
					</div>
				<?php else : ?>
					<div class="status-icon status-invalid">❓</div>
					<div class="status-message status-invalid"><?php echo esc_html( $status['message'] ); ?></div>
					<div class="details">
						<p><strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <span class="code"><?php echo esc_html( $coupon_code ); ?></span></p>
					</div>
				<?php endif; ?>

				<p style="margin-top: 30px; color: #6c757d; font-size: 14px;">
					<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
				</p>
			</div>
		</body>
		</html>
		<?php
	}
}

