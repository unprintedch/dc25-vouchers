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
			margin: 20mm;
		}
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		body {
			font-family: 'DejaVu Sans', Arial, sans-serif;
			color: #000;
			background: #fff;
			padding: 20px;
			line-height: 1.6;
			font-size: 12px;
		}
		.voucher-container {
			width: 100%;
			height: 100%;
		}
		.title {
			font-size: 24px;
			font-weight: bold;
			margin-bottom: 30px;
			text-align: center;
		}
		.data-section {
			margin: 20px 0;
		}
		.data-row {
			margin: 10px 0;
			font-size: 14px;
		}
		.data-label {
			font-weight: bold;
			display: inline-block;
			width: 120px;
		}
		.data-value {
			display: inline-block;
		}
		.code {
			font-family: monospace;
			font-size: 16px;
			font-weight: bold;
			letter-spacing: 1px;
		}
		.qr-section {
			text-align: center;
			margin: 30px 0;
		}
		.qr-code {
			max-width: 150px;
			height: auto;
		}
		.verify-url {
			margin-top: 15px;
			font-size: 10px;
			color: #666;
			word-break: break-all;
		}
		.footer {
			position: absolute;
			bottom: 10mm;
			left: 20mm;
			right: 20mm;
			text-align: center;
			font-size: 8px;
			color: #999;
			border-top: 1px solid #ddd;
			padding-top: 10px;
		}
	</style>
</head>
<body>
	<div class="voucher-container">
		<h1 class="title">Bon cadeau</h1>
		
		<div class="data-section">
			<div class="data-row">
				<span class="data-label">Code:</span>
				<span class="data-value code"><?php echo esc_html( $coupon_code ); ?></span>
			</div>
			
			<div class="data-row">
				<span class="data-label">Montant:</span>
				<span class="data-value"><?php echo esc_html( number_format( $amount, 2, '.', "'" ) ); ?> <?php echo esc_html( $currency ); ?></span>
			</div>
			
			<div class="data-row">
				<span class="data-label">Date d'expiration:</span>
				<span class="data-value"><?php echo esc_html( $expiry_date ); ?></span>
			</div>
			
			<?php if ( ! empty( $recipient_name ) ) : ?>
				<div class="data-row">
					<span class="data-label">Destinataire:</span>
					<span class="data-value"><?php echo esc_html( $recipient_name ); ?></span>
				</div>
			<?php endif; ?>
			
			<?php if ( ! empty( $message ) ) : ?>
				<div class="data-row">
					<span class="data-label">Message:</span>
					<span class="data-value"><?php echo esc_html( $message ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		
		<?php if ( ! empty( $qr_code ) && ! is_wp_error( $qr_code ) ) : ?>
			<div class="qr-section">
				<img src="<?php echo esc_attr( $qr_code ); ?>" alt="QR Code" class="qr-code" />
				<?php if ( ! empty( $verify_url ) ) : ?>
					<div class="verify-url">
						<?php echo esc_html( $verify_url ); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<?php if ( ! empty( $generation_date ) ) : ?>
			<div class="footer">
				<?php printf( esc_html__( 'Généré le %s', 'dc25-vouchers' ), esc_html( $generation_date ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</body>
</html>
