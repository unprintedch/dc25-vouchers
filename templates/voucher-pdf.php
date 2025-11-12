<?php
/**
 * Template PDF pour les bons cadeaux
 * 
 * Ce template peut être surchargé dans le thème :
 * wp-content/themes/votre-theme/dc25-vouchers/voucher-pdf.php
 *
 * @package DC25_Vouchers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables disponibles :
// $coupon_code, $amount, $currency, $expiry_date, $message, $recipient_name
// $qr_code (base64), $logo_url, $theme_color, $conditions
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		@page {
			margin: 0;
		}
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		body {
			font-family: 'DejaVu Sans', Arial, sans-serif;
			color: #333;
			background: #fff;
			padding: 40px;
			line-height: 1.6;
		}
		.voucher-container {
			width: 100%;
			height: 100%;
			border: 3px solid <?php echo esc_attr( $theme_color ?: '#000000' ); ?>;
			border-radius: 12px;
			padding: 40px;
			position: relative;
		}
		.header {
			text-align: center;
			margin-bottom: 30px;
		}
		.logo {
			max-width: 200px;
			max-height: 80px;
			margin-bottom: 20px;
		}
		.title {
			font-size: 32px;
			font-weight: bold;
			color: <?php echo esc_attr( $theme_color ?: '#000000' ); ?>;
			margin-bottom: 10px;
		}
		.amount-section {
			text-align: center;
			margin: 40px 0;
			padding: 30px;
			background: #f8f9fa;
			border-radius: 8px;
		}
		.amount-label {
			font-size: 16px;
			color: #666;
			margin-bottom: 10px;
		}
		.amount-value {
			font-size: 48px;
			font-weight: bold;
			color: <?php echo esc_attr( $theme_color ?: '#000000' ); ?>;
		}
		.details {
			margin: 30px 0;
		}
		.detail-row {
			margin: 15px 0;
			font-size: 14px;
		}
		.detail-label {
			font-weight: bold;
			display: inline-block;
			width: 150px;
		}
		.code {
			font-family: monospace;
			font-size: 20px;
			font-weight: bold;
			letter-spacing: 2px;
			color: <?php echo esc_attr( $theme_color ?: '#000000' ); ?>;
		}
		.message-section {
			margin: 30px 0;
			padding: 20px;
			background: #fff;
			border-left: 4px solid <?php echo esc_attr( $theme_color ?: '#000000' ); ?>;
			font-style: italic;
		}
		.qr-section {
			text-align: center;
			margin: 40px 0;
		}
		.qr-code {
			display: inline-block;
			padding: 20px;
			background: #fff;
			border: 2px solid #ddd;
			border-radius: 8px;
		}
		.qr-code img {
			width: 150px;
			height: 150px;
		}
		.conditions {
			margin-top: 40px;
			padding-top: 20px;
			border-top: 1px solid #ddd;
			font-size: 11px;
			color: #666;
			line-height: 1.5;
		}
		.footer {
			position: absolute;
			bottom: 20px;
			left: 40px;
			right: 40px;
			text-align: center;
			font-size: 10px;
			color: #999;
		}
	</style>
</head>
<body>
	<div class="voucher-container">
		<div class="header">
			<?php if ( ! empty( $logo_url ) ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" class="logo">
			<?php endif; ?>
			<h1 class="title"><?php esc_html_e( 'Bon cadeau', 'dc25-vouchers' ); ?></h1>
		</div>

		<div class="amount-section">
			<div class="amount-label"><?php esc_html_e( 'Valeur', 'dc25-vouchers' ); ?></div>
			<div class="amount-value"><?php echo esc_html( wc_price( $amount, [ 'currency' => $currency ] ) ); ?></div>
		</div>

		<div class="details">
			<div class="detail-row">
				<span class="detail-label"><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></span>
				<span class="code"><?php echo esc_html( $coupon_code ); ?></span>
			</div>
			<?php if ( ! empty( $expiry_date ) ) : ?>
				<div class="detail-row">
					<span class="detail-label"><?php esc_html_e( 'Valable jusqu\'au:', 'dc25-vouchers' ); ?></span>
					<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ) ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $recipient_name ) ) : ?>
				<div class="detail-row">
					<span class="detail-label"><?php esc_html_e( 'Destinataire:', 'dc25-vouchers' ); ?></span>
					<span><?php echo esc_html( $recipient_name ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $message ) ) : ?>
			<div class="message-section">
				<?php echo esc_html( $message ); ?>
			</div>
		<?php endif; ?>

		<div class="qr-section">
			<div class="qr-code">
				<?php if ( ! empty( $qr_code ) && ! is_wp_error( $qr_code ) ) : ?>
					<img src="<?php echo esc_attr( $qr_code ); ?>" alt="QR Code">
				<?php endif; ?>
			</div>
			<p style="margin-top: 15px; font-size: 12px; color: #666;">
				<?php esc_html_e( 'Scannez ce code pour vérifier le bon', 'dc25-vouchers' ); ?>
			</p>
		</div>

		<div class="conditions">
			<p><strong><?php esc_html_e( 'Conditions d\'utilisation:', 'dc25-vouchers' ); ?></strong></p>
			<p><?php echo esc_html( $conditions ?: __( 'Ce bon cadeau est valable une seule fois et non remboursable.', 'dc25-vouchers' ) ); ?></p>
		</div>

		<div class="footer">
			<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - <?php echo esc_url( home_url() ); ?></p>
		</div>
	</div>
</body>
</html>

