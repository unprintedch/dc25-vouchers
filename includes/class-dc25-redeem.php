<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DC25_Redeem {

    public function __construct() {
        add_action( 'init', [ $this, 'rewrite' ] );
        add_filter( 'query_vars', [ $this, 'vars' ] );
        add_action( 'template_redirect', [ $this, 'template' ] );
    }

    public function rewrite() {
        add_rewrite_rule( '^redeem/([^/]+)/?$', 'index.php?dc25_token=$matches[1]', 'top' );
    }

    public function vars( $vars ) {
        $vars[] = 'dc25_token';
        return $vars;
    }

    public function template() {
        $token = get_query_var( 'dc25_token' );
        if ( ! $token ) return;

        $voucher = get_posts([
            'post_type' => 'dc25_voucher',
            'meta_key'  => '_dc25_token',
            'meta_value'=> $token,
            'numberposts'=>1,
        ]);

        status_header(200);
        nocache_headers();

        if ( empty( $voucher ) ) wp_die( 'Bon invalide.' );

        $id = $voucher[0]->ID;
        $status = get_post_meta( $id, '_dc25_status', true );
        $code   = get_post_meta( $id, '_dc25_code', true );
        $amount = get_post_meta( $id, '_dc25_amount', true );

        // Traitement POST
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['dc25_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['dc25_nonce'], 'dc25_redeem_' . $id ) && $status !== 'used' ) {
                $by = sanitize_text_field( $_POST['vendor'] ?? '' );
                update_post_meta( $id, '_dc25_status', 'used' );
                update_post_meta( $id, '_dc25_used_by', $by );
                update_post_meta( $id, '_dc25_used_at', current_time( 'mysql' ) );
                $status = 'used';
            }
        }

        $used_by = get_post_meta( $id, '_dc25_used_by', true );
        $used_at = get_post_meta( $id, '_dc25_used_at', true );

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Validation du bon</title>
            <style>
                body{font-family:system-ui;background:#f7f7f7;margin:0;padding:40px;}
                main{max-width:460px;margin:auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,.08);}
                input,button{width:100%;padding:10px;margin-top:10px;border-radius:6px;border:1px solid #ccc;}
                button{background:#111;color:#fff;font-weight:bold;cursor:pointer;}
                .used{color:#c00;font-weight:bold;}
                .active{color:#080;font-weight:bold;}
            </style>
        </head>
        <body>
        <main>
            <h1>Bon cadeau</h1>
            <p><strong>Code :</strong> <?= esc_html( $code ) ?></p>
            <p><strong>Montant :</strong> <?= esc_html( wc_price( $amount ) ) ?></p>

            <?php if ( $status === 'used' ) : ?>
                <p class="used">Ce bon a déjà été utilisé.</p>
                <p><strong>Utilisé par :</strong> <?= esc_html( $used_by ) ?><br>
                   <strong>Le :</strong> <?= esc_html( $used_at ) ?></p>
            <?php else : ?>
                <p class="active">Bon valide ✅</p>
                <form method="post">
                    <label>Nom du prestataire</label>
                    <input type="text" name="vendor" required>
                    <?php wp_nonce_field( 'dc25_redeem_' . $id, 'dc25_nonce' ); ?>
                    <button type="submit">Marquer comme utilisé</button>
                </form>
            <?php endif; ?>
        </main>
        </body>
        </html>
        <?php
        exit;
    }
}