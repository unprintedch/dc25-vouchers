<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DC25_Voucher_CPT {

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_filter( 'manage_dc25_voucher_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_dc25_voucher_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
    }

    public function register_cpt() {
        register_post_type( 'dc25_voucher', [
            'label'       => 'Bons cadeaux',
            'public'      => false,
            'show_ui'     => true,
            'supports'    => [ 'title' ],
            'menu_icon'   => 'dashicons-tickets-alt',
        ] );
    }

    public function columns( $cols ) {
        $cols['amount']  = 'Montant';
        $cols['status']  = 'Statut';
        $cols['used']    = 'Utilisé par / le';
        $cols['order']   = 'Commande';
        return $cols;
    }

    public function column_content( $col, $id ) {
        switch ( $col ) {
            case 'amount':
                echo esc_html( wc_price( get_post_meta( $id, '_dc25_amount', true ) ) );
                break;
            case 'status':
                $s = get_post_meta( $id, '_dc25_status', true );
                echo $s === 'used' ? '🔴 Utilisé' : '🟢 Actif';
                break;
            case 'used':
                $by = get_post_meta( $id, '_dc25_used_by', true );
                $at = get_post_meta( $id, '_dc25_used_at', true );
                echo $by ? esc_html( "$by – $at" ) : '—';
                break;
            case 'order':
                $order_id = get_post_meta( $id, '_dc25_order', true );
                echo $order_id ? '<a href="' . esc_url( get_edit_post_link( $order_id ) ) . '">#' . $order_id . '</a>' : '—';
                break;
        }
    }
}