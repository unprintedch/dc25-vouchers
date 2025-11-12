<?php
use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if ( ! defined( 'ABSPATH' ) ) exit;

class DC25_PDF {

    public static function generate( $id ) {

        $upload = wp_upload_dir();
        $dir = trailingslashit( $upload['basedir'] ) . 'dc25-vouchers/';
        if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );

        $code  = get_post_meta( $id, '_dc25_code', true );
        $token = get_post_meta( $id, '_dc25_token', true );
        $amount= get_post_meta( $id, '_dc25_amount', true );
        $exp   = get_post_meta( $id, '_dc25_expire_at', true );
        $url   = home_url( '/redeem/' . $token );

        // QR Code
        $qr_path = $dir . 'voucher-' . $id . '.png';
        $writer = new PngWriter();
        $qr = QrCode::create( $url )->setSize(200)->setMargin(10);
        $writer->write($qr)->saveToFile($qr_path);

        // HTML du PDF
        ob_start(); ?>
        <html>
        <head>
            <style>
                body {font-family: system-ui; padding:40px;}
                .card {border:1px solid #ccc; border-radius:12px; padding:30px;}
                h1 {margin:0 0 20px;}
                .amount {font-size:22px; font-weight:bold;}
                .qr {margin-top:20px;}
                .conditions {margin-top:30px; font-size:11px; color:#666;}
            </style>
        </head>
        <body>
            <div class="card">
                <h1>Bon cadeau</h1>
                <p class="amount"><?= esc_html( wc_price( $amount ) ) ?></p>
                <p><strong>Code :</strong> <?= esc_html( $code ) ?></p>
                <?php if ( $exp ) : ?>
                    <p>Valable jusqu’au <?= esc_html( date_i18n( 'd.m.Y', strtotime( $exp ) ) ) ?></p>
                <?php endif; ?>
                <div class="qr">
                    <img src="<?= esc_attr( $qr_path ) ?>" width="120" height="120">
                    <p style="font-size:10px;">Scanne ou visite : <?= esc_html( $url ) ?></p>
                </div>
                <div class="conditions">
                    <p><strong>Conditions :</strong></p>
                    <ul>
                        <li>Utilisable une seule fois.</li>
                        <li>Non remboursable.</li>
                        <li>Valable 12 mois.</li>
                    </ul>
                </div>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // PDF
        $file = $dir . 'voucher-' . $id . '.pdf';
        $dompdf = new Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        file_put_contents( $file, $dompdf->output() );
        return $file;
    }
}