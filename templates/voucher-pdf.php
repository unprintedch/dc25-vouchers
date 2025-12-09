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
		<?php
		$primary     = ! empty( $theme_color ) ? $theme_color : '#c49b3f';
		$accent      = ! empty( $accent_color ) ? $accent_color : $primary;
		$base_color  = ! empty( $text_color ) ? $text_color : '#111111';
		$has_bg      = ! empty( $background_url );
		?>
		@import url('https://fonts.googleapis.com/css2?family=PT+Serif:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap');

		@page {
			margin: 10mm;
		}
		html, body {
			width: 100%;
			height: 100%;
			margin: 0;
			padding: 0;
		}
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: 'Open Sans', Arial, sans-serif;
			color: <?php echo esc_html( $base_color ); ?>;
			background: #fff;
			font-size: 11.5px;
		}
		.page {
			width: 100%;
			min-height: 100%;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
		}
		.frame {
			width: 100%;
			height: 100%;
			padding: 12mm;
			border: 1mm solid <?php echo esc_html( $primary ); ?>;
			display: flex;
			flex-direction: column;
			align-items: center;
			text-align: center;
			gap: 10px;
			position: relative;
			<?php if ( $has_bg ) : ?>
			background-image: url('<?php echo esc_url( $background_url ); ?>');
			background-size: cover;
			background-position: center;
			background-repeat: no-repeat;
			<?php endif; ?>
		}
		.logo {
			max-height: 56px;
			max-width: 200px;
		}
		.title {
			font-family: 'PT Serif', 'Times New Roman', serif;
			font-size: 24px;
			font-weight: 700;
			color: <?php echo esc_html( $primary ); ?>;
			letter-spacing: 0.6px;
			margin-top: 4px;
		}
		.from-line {
			font-size: 12px;
			margin-top: 6px;
		}
		.message {
			font-family: 'PT Serif', 'Times New Roman', serif;
			font-size: 16px;
			font-weight: 700;
			color: <?php echo esc_html( $primary ); ?>;
			line-height: 1.45;
			margin: 18px 0 12px;
		}
		.amount {
			font-size: 15px;
			font-weight: 600;
			margin-top: 6px;
		}
		.date {
			font-size: 11.5px;
			margin-top: 12px;
		}
		.page-break { page-break-after: always; }

		/* Back page */
		.back-title {
			font-family: 'PT Serif', 'Times New Roman', serif;
			font-size: 22px;
			font-weight: 700;
			color: <?php echo esc_html( $primary ); ?>;
			margin: 6px 0 10px;
		}
		.cols {
			display: flex;
			justify-content: center;
			gap: 18px;
			margin: 8px 0 6px;
			text-align: left;
			width: 100%;
		}
		.col {
			flex: 1;
			min-width: 160px;
			font-size: 11.5px;
			line-height: 1.35;
		}
		.col-title {
			font-weight: 700;
			margin-bottom: 6px;
		}
		.link {
			color: <?php echo esc_html( $primary ); ?>;
			text-decoration: underline;
		}
		.validity {
			font-size: 9.5px;
			margin-top: 10px;
			text-align: center;
		}
		.banner {
			width: 100%;
			margin-top: 10px;
			display: grid;
			grid-template-columns: 1.5fr 0.7fr;
			align-items: stretch;
			gap: 6px;
			color: #fff;
			font-size: 10.5px;
		}
		.banner-left {
			background: <?php echo esc_html( $primary ); ?>;
			padding: 10px 12px;
			display: flex;
			flex-direction: column;
			gap: 4px;
			justify-content: center;
		}
		.banner-title {
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.3px;
		}
		.banner-text {
			line-height: 1.35;
		}
		.banner-link {
			font-size: 9px;
			color: #fff;
			word-break: break-all;
		}
		.banner-right {
			background: <?php echo esc_html( $accent ); ?>;
			padding: 10px;
			text-align: center;
			display: flex;
			flex-direction: column;
			justify-content: center;
			gap: 6px;
		}
		.banner-right .qr-label {
			font-weight: 700;
			margin-bottom: 0;
			font-size: 11px;
		}
		.banner-right img {
			max-width: 80px;
			height: auto;
			margin: 0 auto;
		}
	</style>
</head>
<body>
	<!-- Face avant -->
	<div class="page">
		<div class="frame">
			<?php if ( ! empty( $logo_url ) ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" class="logo" />
			<?php endif; ?>
			<div class="title"><?php echo esc_html( $title_text ?? __( 'BON CADEAU', 'dc25-vouchers' ) ); ?></div>
			<div class="from-line">
				<?php echo esc_html( $from_label ?? __( 'De la part de', 'dc25-vouchers' ) ); ?>:
				<?php echo esc_html( $from_name ?: __( 'Nom de l’offrant', 'dc25-vouchers' ) ); ?>
				<?php echo esc_html( $for_label ?? __( 'à', 'dc25-vouchers' ) ); ?>
				<?php echo esc_html( $recipient_name ?: __( 'Nom du bénéficiaire', 'dc25-vouchers' ) ); ?>
			</div>
			<div class="message">
				<?php
				echo esc_html(
					! empty( $message )
						? $message
						: __( '[Message personnalisé]', 'dc25-vouchers' )
				);
				?>
			</div>
			<div class="amount">
				<?php echo esc_html( '[' . number_format( (float) $amount, 2, '.', "'" ) . ' ' . $currency . ']' ); ?>
			</div>
			<div class="date">
				<?php
				if ( ! empty( $generation_date ) ) {
					printf( esc_html__( 'Date d’émission : %s', 'dc25-vouchers' ), esc_html( $generation_date ) );
				}
				?>
			</div>
		</div>
	</div>

	<div class="page-break"></div>

	<!-- Face arrière -->
	<div class="page">
		<div class="frame">
			<?php if ( ! empty( $logo_url ) ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" class="logo" />
			<?php endif; ?>
			<div class="back-title"><?php echo esc_html( $back_title ); ?></div>

			<div class="cols">
				<div class="col">
					<div class="col-title"><?php echo esc_html( $back_partner_title ); ?></div>
					<div><?php echo esc_html( $back_partner_body ); ?></div>
					<?php if ( ! empty( $back_partner_link_url ) ) : ?>
						<div style="margin-top:6px;">
							<a class="link" href="<?php echo esc_url( $back_partner_link_url ); ?>"><?php echo esc_html( $back_partner_link_label ); ?></a>
						</div>
					<?php endif; ?>
				</div>
				<div class="col">
					<div class="col-title"><?php echo esc_html( $back_online_title ); ?></div>
					<div><?php echo esc_html( $back_online_body ); ?></div>
					<?php if ( ! empty( $back_online_link_url ) ) : ?>
						<div style="margin-top:6px;">
							<a class="link" href="<?php echo esc_url( $back_online_link_url ); ?>"><?php echo esc_html( $back_online_link_label ); ?></a>
						</div>
					<?php endif; ?>
					<div style="margin-top:6px;">
						<?php echo esc_html( $back_online_code_label ); ?> : <strong><?php echo esc_html( $coupon_code ); ?></strong>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $back_validity_notice ) ) : ?>
				<div class="validity"><?php echo esc_html( $back_validity_notice ); ?></div>
			<?php endif; ?>

			<div class="banner">
				<div class="banner-left">
					<div class="banner-title"><?php echo esc_html( $back_banner_title ); ?></div>
					<div class="banner-text"><?php echo esc_html( $back_banner_text ); ?></div>
					<?php if ( ! empty( $verify_url ) ) : ?>
						<div class="banner-link"><?php echo esc_html( $verify_url ); ?></div>
					<?php endif; ?>
				</div>
				<div class="banner-right">
					<div class="qr-label"><?php esc_html_e( 'QR code', 'dc25-vouchers' ); ?></div>
					<?php if ( ! empty( $qr_code ) && ! is_wp_error( $qr_code ) ) : ?>
						<img src="<?php echo esc_attr( $qr_code ); ?>" alt="QR Code" />
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
