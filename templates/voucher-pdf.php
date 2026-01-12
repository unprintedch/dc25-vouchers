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

// Préparation des couleurs
$black_values = array( '#000000', '#000', '000000', '000' );

$theme_color_val  = isset( $theme_color ) ? trim( strtolower( (string) $theme_color ) ) : '';
$accent_color_val = isset( $accent_color ) ? trim( strtolower( (string) $accent_color ) ) : '';
$text_color_val   = isset( $text_color ) ? trim( strtolower( (string) $text_color ) ) : '';

// Couleur primaire : éviter le noir / vide, préférer accent_color
if ( empty( $theme_color_val ) || in_array( $theme_color_val, $black_values, true ) ) {
	if ( ! empty( $accent_color_val ) && ! in_array( $accent_color_val, $black_values, true ) ) {
		$primary = $accent_color_val;
	} else {
		$primary = '#c49b3f'; // Fallback doré
	}
} else {
	$primary = $theme_color_val;
}

// Couleur de texte : éviter le noir pur
if ( empty( $text_color_val ) || in_array( $text_color_val, $black_values, true ) ) {
	$base_color = '#333333';
} else {
	$base_color = $text_color_val;
}

// Formatage des données
$issue_date = '';
if ( ! empty( $generation_timestamp ) && is_numeric( $generation_timestamp ) ) {
	$issue_date = date_i18n( 'j.n.Y', $generation_timestamp );
} elseif ( ! empty( $generation_date ) ) {
	$timestamp  = strtotime( $generation_date );
	$issue_date = $timestamp ? date_i18n( 'j.n.Y', $timestamp ) : $generation_date;
}

$from_name_display = ! empty( $from_name ) ? wp_unslash( $from_name ) : '';
$to_name_display   = ! empty( $recipient_name ) ? wp_unslash( $recipient_name ) : '';
$message_display   = isset( $message ) && '' !== $message ? wp_unslash( $message ) : '';
$amount_display    = isset( $amount ) ? number_format( (float) $amount, 0, '.', "'" ) : '';
$currency_display  = ! empty( $currency ) ? $currency : 'fr.';
$code_display      = ! empty( $coupon_code ) ? $coupon_code : '';
$qr_src            = ( ! empty( $qr_code ) && ! is_wp_error( $qr_code ) ) ? $qr_code : '';
$verify_display    = ! empty( $verify_url ) ? $verify_url : '';
$logo_base64       = isset( $logo_base64 ) ? $logo_base64 : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<title>Bon cadeau</title>
	<style>
		@import url('https://fonts.googleapis.com/css2?family=PT+Serif:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap');

		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		@page {
			size: A4 portrait;
			margin: 20mm;
		}

		html, body {
			margin: 0;
			padding: 0;
		}

		body {
			font-family: 'Open Sans', Arial, sans-serif;
			font-size: 7pt;
			color: <?php echo esc_html( $base_color ); ?>;
			background: #ffffff;
		}

		/* Conteneur principal : 160mm de large */
		.page {
			width: 160mm;
			margin: 0 auto;
		}

		/* Sections recto et verso : 128mm chacune pour répartition 50/50 */
		.recto, .verso {
			width: 100%;
			height: 128mm;
			text-align: center;
		}

		.recto {
			border: 1px solid <?php echo esc_html( $primary ); ?>;
			margin-bottom: 15mm;
			margin-top: 8mm;
		}

		/* Logo */
		.logo-container {
			text-align: center;
			margin-bottom: 10mm;
			margin-top: -6mm;
		}

		.logo-container img {
			max-width: 35mm;
			max-height: 24mm;
			width: auto;
			height: auto;
			display: block;
			margin: 0 auto;
			background: white;

		}

		.logo-text {
			font-family: 'Open Sans', Arial, sans-serif;
			font-size: 6pt;
			font-weight: 700;
			color: <?php echo esc_html( $base_color ); ?>;
			text-transform: uppercase;
		}

		.logo-subtext {
			font-family: 'Open Sans', Arial, sans-serif;
			font-size: 5pt;
			color: <?php echo esc_html( $base_color ); ?>;
			text-transform: uppercase;
		}

		/* Titre */
		.voucher-title {
			font-family: 'PT Serif', serif;
			font-size: 24pt;
			font-weight: 700;
			color: <?php echo esc_html( $primary ); ?>;
			text-align: center;
			text-transform: uppercase;
			margin-bottom: 3mm;
		}

		/* Contenu recto */
		.recto-content {
			margin-top: 5mm;
		}

		.recto-content-inner {
			margin: 0 auto;
			text-align: center;
		}

		.from-to-line {
			font-size: 9pt;
			line-height: 1.3;
			margin-bottom: 3mm;
		}

		.personal-message {
			font-family: 'PT Serif', serif;
			font-size: 16pt;
			font-weight: 700;
			color: <?php echo esc_html( $primary ); ?>;
			line-height: 1.2;
			margin-bottom: 3mm;
			max-width: 70%;
			margin-left: auto;
			margin-right: auto;
		}

		.voucher-value {
			font-size: 20pt;
			font-weight: 700;
			margin-bottom: 1mm;
		}

		.issue-date {
			font-size: 8pt;
		}

		/* Contenu verso */
		.verso {
			font-size: 7pt;
			line-height: 1.4;
			text-align: left;
		}

		.how-to {
			font-size: 9pt;
			line-height: 1.3;
			margin-bottom: 3mm;
		}

		.how-to h2 {
			margin-top: 2mm;
			font-size: 12pt;
			font-weight: 700;
			text-transform: uppercase;
			margin-bottom: 1mm;
			text-align: center;
		}

		.how-to h3 {
			font-size: 10pt;
			font-weight: 700;
			margin-top: 1mm;
			margin-bottom: 0.5mm;
		}

		.how-to p {
			font-size: 8pt;
			line-height: 1.3;
			margin-bottom: 0.8mm;
		}

		.conditions {
			font-size: 6pt;
			color: #777777;
			line-height: 1.3;
			margin: 2mm 0 1mm;
			text-align: left;
		}

		/* QR Code layout */
		.qr-layout {
			height: 30mm;
			display: table;
			width: 100%;
			margin: 0;
			padding-top: 3mm;
			border-top: 1px solid <?php echo esc_html( $primary ); ?>;
		}

		.qr-box {
			width: 24mm;
			height: 24mm;
			display: table-cell;
			vertical-align: middle;
			border: 1px solid #dddddd;
			padding: 1mm;
		}

		.qr-box img {
			max-width: 100%;
			max-height: 100%;
			display: block;
		}

		.qr-text {
			display: table-cell;
			vertical-align: top;
			padding-left: 3mm;
			text-align: left;
		}

		.qr-text-title {
			font-weight: 700;
			text-transform: uppercase;
			margin-bottom: 0.5mm;
			margin-top: 2mm;
			font-size: 9pt;
		}

		.qr-text div {
			font-size: 9pt;
			line-height: 1.3;
		}

		a {
			color: <?php echo esc_html( $primary ); ?>;
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div class="page">
		<div class="recto">
			<div class="logo-container">
				<?php if ( ! empty( $logo_base64 ) ) : ?>
					<img src="<?php echo esc_attr( $logo_base64 ); ?>" alt="Logo">
				<?php elseif ( ! empty( $logo_url ) ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo">
				<?php else : ?>
					<div class="logo-text">NEUCHÂTEL</div>
					<div class="logo-subtext">VINS • TERROIR</div>
				<?php endif; ?>
			</div>

			<div class="voucher-title">BON CADEAU</div>

			<div class="recto-content">
				<div class="recto-content-inner">
					<div class="from-to-line">
						<?php esc_html_e( 'De la part de', 'dc25-vouchers' ); ?>: <?php echo esc_html( $from_name_display ); ?>
						<?php if ( $to_name_display ) : ?>
							<?php esc_html_e( 'à', 'dc25-vouchers' ); ?> <?php echo esc_html( $to_name_display ); ?>
						<?php endif; ?>
					</div>

					<?php if ( $message_display ) : ?>
						<div class="personal-message">
							<?php echo nl2br( esc_html( $message_display ) ); ?>
						</div>
					<?php endif; ?>

					<div class="voucher-value">
						<?php echo esc_html( $amount_display ); ?> <?php echo esc_html( $currency_display ); ?>
					</div>

					<div class="issue-date">
						<?php esc_html_e( "Date d'émission", 'dc25-vouchers' ); ?>: <?php echo esc_html( $issue_date ); ?>
					</div>
				</div>
			</div>
		</div>

		<div class="verso">
			<div class="how-to">
				<h2><?php esc_html_e( 'Comment utiliser ce bon ?', 'dc25-vouchers' ); ?></h2>

				<h3><?php esc_html_e( 'Chez nos partenaires', 'dc25-vouchers' ); ?></h3>
				<p>
					<?php esc_html_e( 'Ce bon est valable chez tous les encaveurs, producteurs et partenaires NVT, liste complète sur :', 'dc25-vouchers' ); ?><br>
					<a href="https://neuchatel-vins-terroir.ch/bon-cadeau-du-terroir" target="_blank">neuchatel-vins-terroir.ch/bon-cadeau-du-terroir</a>
				</p>

				<br>

				<h3><?php esc_html_e( 'En ligne', 'dc25-vouchers' ); ?></h3>
				<p>
					<?php esc_html_e( 'Sur notre site', 'dc25-vouchers' ); ?> <a href="https://neuchatel-vins-terroir.ch" target="_blank">neuchatel-vins-terroir.ch</a><br>
					<?php esc_html_e( 'avec le code', 'dc25-vouchers' ); ?> <strong><?php echo esc_html( $code_display ); ?></strong>
				</p>
			</div>

			<div class="conditions">
				<?php esc_html_e( "Valable 12 mois dès la date d'émission, non cumulable avec d'autres bons. Ne peut être échangé contre espèces. Utilisation partielle possible selon conditions du partenaire.", 'dc25-vouchers' ); ?>
			</div>

			<div class="qr-layout">
				<div class="qr-box">
					<?php if ( $qr_src ) : ?>
						<img src="<?php echo esc_attr( $qr_src ); ?>" alt="QR code">
					<?php else : ?>
						<span style="font-size: 5pt;">QR</span>
					<?php endif; ?>
				</div>
				<div class="qr-text">
					<div class="qr-text-title"><?php esc_html_e( 'ZONE PARTENAIRE - Validation du bon', 'dc25-vouchers' ); ?></div>
					<div>
						<?php esc_html_e( "Scanner ce QR code afin de valider l'utilisation du bon et transmettre le ticket pour remboursement.", 'dc25-vouchers' ); ?>
						<br>
						<?php esc_html_e( 'Ou rendez-vous sur :', 'dc25-vouchers' ); ?><br>
						<strong style="word-break: break-all;"><?php echo esc_html( $verify_display ? $verify_display : '' ); ?></strong>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
