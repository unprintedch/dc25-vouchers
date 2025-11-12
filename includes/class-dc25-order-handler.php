<?php
/**
 * Gestionnaire de commandes - Génération des bons après paiement
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour la gestion des commandes
 */
class DC25_Order_Handler {

	/**
	 * Constructeur
	 */
	public function __construct() {
		// Génération après paiement
		add_action( 'woocommerce_order_status_completed', [ $this, 'process_order' ], 10, 1 );

		// Annulation de commande
		add_action( 'woocommerce_order_status_cancelled', [ $this, 'cancel_vouchers' ], 10, 1 );

		// Attacher les PDF aux emails
		add_filter( 'woocommerce_email_attachments', [ $this, 'attach_pdfs_to_email' ], 10, 3 );
	}

	/**
	 * Traiter la commande et générer les bons
	 *
	 * @param int $order_id ID de la commande.
	 */
	public function process_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
				continue;
			}

			// Récupérer les meta de l'item
			$amount = (float) $item->get_meta( '_dc25_gv_amount' );
			if ( $amount <= 0 ) {
				continue; // Montant invalide
			}

			// Vérifier si déjà traité
			$coupon_code = $item->get_meta( '_dc25_gv_coupon_code' );
			if ( ! empty( $coupon_code ) ) {
				continue; // Déjà traité
			}

			// Générer le coupon
			$prefix = $product->get_coupon_prefix();
			$validity_days = $product->get_validity_days();
			$coupon = DC25_Coupon_Service::create_coupon( $amount, $prefix, $validity_days );

			if ( is_wp_error( $coupon ) ) {
				// Logger l'erreur
				wc_get_logger()->error(
					sprintf( 'Erreur génération coupon pour commande %d: %s', $order_id, $coupon->get_error_message() ),
					[ 'source' => 'dc25-vouchers' ]
				);
				continue;
			}

			$coupon_code = $coupon->get_code();
			$expiry_date = $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : date( 'Y-m-d', strtotime( "+{$validity_days} days" ) );

			// Sauvegarder le code coupon dans l'item
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

			$pdf_path = DC25_PDF_Service::generate_pdf( $pdf_data );

			if ( ! is_wp_error( $pdf_path ) ) {
				$item->update_meta_data( '_dc25_gv_pdf_path', $pdf_path );
				$item->save();
			} else {
				wc_get_logger()->error(
					sprintf( 'Erreur génération PDF pour commande %d: %s', $order_id, $pdf_path->get_error_message() ),
					[ 'source' => 'dc25-vouchers' ]
				);
			}

			// Envoyer les emails
			$this->send_voucher_emails( $order, $item, $pdf_path );
		}
	}

	/**
	 * Envoyer les emails avec le PDF
	 *
	 * @param WC_Order              $order Commande.
	 * @param WC_Order_Item_Product $item Item.
	 * @param string                $pdf_path Chemin du PDF.
	 */
	private function send_voucher_emails( $order, $item, string $pdf_path ): void {
		$settings = DC25_Settings::get_instance();

		// Email à l'acheteur
		$buyer_email = $order->get_billing_email();
		$buyer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

		// Email au destinataire (si renseigné)
		$recipient_email = $item->get_meta( '_dc25_gv_recipient_email' );
		$recipient_name = $item->get_meta( '_dc25_gv_recipient_name' );

		// Préparer les données pour l'email
		$email_data = [
			'order'          => $order,
			'coupon_code'    => $item->get_meta( '_dc25_gv_coupon_code' ),
			'amount'         => $item->get_meta( '_dc25_gv_amount' ),
			'message'        => $item->get_meta( '_dc25_gv_message' ),
			'recipient_name' => $recipient_name,
		];

		// Envoyer à l'acheteur
		$this->send_email( $buyer_email, $buyer_name, $email_data, $pdf_path, false );

		// Envoyer au destinataire si activé et renseigné
		if ( $settings->is_recipient_email_enabled() && ! empty( $recipient_email ) ) {
			$this->send_email( $recipient_email, $recipient_name ?: __( 'Destinataire', 'dc25-vouchers' ), $email_data, $pdf_path, true );
		}
	}

	/**
	 * Envoyer un email
	 *
	 * @param string $to Email destinataire.
	 * @param string $name Nom destinataire.
	 * @param array  $data Données du bon.
	 * @param string $pdf_path Chemin du PDF.
	 * @param bool   $is_recipient Si c'est le destinataire.
	 */
	private function send_email( string $to, string $name, array $data, string $pdf_path, bool $is_recipient ): void {
		$settings = DC25_Settings::get_instance();

		$subject = $is_recipient
			? $settings->get_recipient_email_subject()
			: __( 'Votre bon cadeau', 'dc25-vouchers' );

		$message = $is_recipient
			? $settings->get_recipient_email_content()
			: $this->get_buyer_email_content( $data );

		// Remplacer les placeholders
		$replacements = [
			'{name}'        => $name,
			'{coupon_code}' => $data['coupon_code'],
			'{amount}'      => wc_price( $data['amount'] ),
			'{message}'     => $data['message'] ?? '',
			'{site_name}'   => get_bloginfo( 'name' ),
		];

		foreach ( $replacements as $placeholder => $value ) {
			$message = str_replace( $placeholder, $value, $message );
			$subject = str_replace( $placeholder, $value, $subject );
		}

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];

		// Attacher le PDF
		$attachments = [];
		if ( ! is_wp_error( $pdf_path ) && file_exists( $pdf_path ) ) {
			$attachments[] = $pdf_path;
		}

		wp_mail( $to, $subject, $message, $headers, $attachments );
	}

	/**
	 * Contenu email pour l'acheteur
	 *
	 * @param array $data Données.
	 * @return string
	 */
	private function get_buyer_email_content( array $data ): string {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<h2><?php esc_html_e( 'Votre bon cadeau', 'dc25-vouchers' ); ?></h2>
			<p><?php esc_html_e( 'Bonjour,', 'dc25-vouchers' ); ?></p>
			<p><?php esc_html_e( 'Votre bon cadeau a été généré avec succès.', 'dc25-vouchers' ); ?></p>
			<p>
				<strong><?php esc_html_e( 'Code:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( $data['coupon_code'] ); ?><br>
				<strong><?php esc_html_e( 'Montant:', 'dc25-vouchers' ); ?></strong> <?php echo wp_kses_post( wc_price( $data['amount'] ) ); ?>
			</p>
			<?php if ( ! empty( $data['message'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Message:', 'dc25-vouchers' ); ?></strong><br><?php echo esc_html( $data['message'] ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'Le PDF de votre bon cadeau est joint à cet email.', 'dc25-vouchers' ); ?></p>
			<p><?php esc_html_e( 'Cordialement,', 'dc25-vouchers' ); ?><br><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Annuler les bons (invalider les coupons)
	 *
	 * @param int $order_id ID de la commande.
	 */
	public function cancel_vouchers( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$coupon_code = $item->get_meta( '_dc25_gv_coupon_code' );
			if ( ! empty( $coupon_code ) ) {
				DC25_Coupon_Service::invalidate_coupon( $coupon_code );
			}
		}
	}

	/**
	 * Attacher les PDF aux emails WooCommerce
	 *
	 * @param array    $attachments Pièces jointes.
	 * @param string   $email_id ID de l'email.
	 * @param WC_Order $order Commande.
	 * @return array
	 */
	public function attach_pdfs_to_email( array $attachments, string $email_id, $order ): array {
		// Attacher uniquement pour l'email de commande complétée
		if ( 'customer_completed_order' !== $email_id ) {
			return $attachments;
		}

		if ( ! $order instanceof WC_Order ) {
			return $attachments;
		}

		foreach ( $order->get_items() as $item ) {
			$pdf_path = $item->get_meta( '_dc25_gv_pdf_path' );
			if ( ! empty( $pdf_path ) && file_exists( $pdf_path ) ) {
				$attachments[] = $pdf_path;
			}
		}

		return $attachments;
	}
}

