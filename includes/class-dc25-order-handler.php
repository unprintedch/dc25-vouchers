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
		// Génération après paiement - plusieurs hooks pour couvrir tous les cas
		// Priorité basse (30) pour s'assurer que WooCommerce a terminé ses traitements et que les métadonnées sont sauvegardées
		add_action( 'woocommerce_order_status_completed', [ $this, 'process_order' ], 30, 1 );
		add_action( 'woocommerce_order_status_processing', [ $this, 'process_order' ], 30, 1 );
		add_action( 'woocommerce_payment_complete', [ $this, 'process_order' ], 30, 1 );
		
		// Hook de changement de statut (très fiable) - priorité élevée pour capturer les changements
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_order_status_changed' ], 30, 4 );
		
		// Hook de sauvegarde de commande - priorité basse pour que les métadonnées soient sauvegardées d'abord
		// woocommerce_checkout_create_order_line_item s'exécute généralement avant ce hook
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'on_checkout_order_processed' ], 30, 1 );
		
		// Hook pour les commandes créées via admin
		add_action( 'woocommerce_new_order', [ $this, 'on_checkout_order_processed' ], 30, 1 );
		
		// Action planifiée pour traiter la commande après un court délai (backup)
		add_action( 'dc25_process_order_delayed', [ $this, 'process_order' ], 10, 1 );
		
		// Logger pour confirmer que les hooks sont enregistrés (toujours)
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				'DC25: DC25_Order_Handler initialisé, hooks enregistrés',
				[ 'source' => 'dc25-vouchers' ]
			);
		}

		// Annulation de commande
		add_action( 'woocommerce_order_status_cancelled', [ $this, 'cancel_vouchers' ], 10, 1 );

		// Attacher les PDF aux emails
		add_filter( 'woocommerce_email_attachments', [ $this, 'attach_pdfs_to_email' ], 10, 3 );

		// Ajouter le lien de téléchargement dans l'email
		add_action( 'woocommerce_email_order_details', [ $this, 'add_pdf_download_link_to_email' ], 20, 4 );
	}

	/**
	 * Gérer le changement de statut de commande
	 *
	 * @param int    $order_id ID de la commande.
	 * @param string $old_status Ancien statut.
	 * @param string $new_status Nouveau statut.
	 * @param WC_Order $order Objet commande.
	 */
	public function on_order_status_changed( int $order_id, string $old_status, string $new_status, $order ): void {
		// Traiter uniquement si la commande passe à un statut payé
		if ( in_array( $new_status, [ 'processing', 'completed', 'on-hold' ], true ) ) {
			// Traitement immédiat
			$this->process_order( $order_id );
			
			// Planifier aussi un traitement différé au cas où
			$this->schedule_order_processing( $order_id );
		}
	}

	/**
	 * Gérer la commande après checkout
	 *
	 * @param int $order_id ID de la commande.
	 */
	public function on_checkout_order_processed( int $order_id ): void {
		// Logger
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf( 'DC25: on_checkout_order_processed appelé pour commande %d', $order_id ),
				[ 'source' => 'dc25-vouchers' ]
			);
		}
		
		// Planifier un traitement différé pour s'assurer que les métadonnées sont sauvegardées
		// Les métadonnées sont sauvegardées via woocommerce_checkout_create_order_line_item
		// qui s'exécute généralement avant ce hook, mais on planifie quand même un délai pour sécurité
		$this->schedule_order_processing( $order_id );
		
		// Aussi traiter immédiatement (au cas où les métadonnées sont déjà disponibles)
		// mais seulement si la commande est payée
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order_status = $order->get_status();
			// Ne traiter immédiatement que si la commande est déjà dans un statut payé
			if ( in_array( $order_status, [ 'processing', 'completed', 'on-hold' ], true ) ) {
				$this->process_order( $order_id );
			}
		}
	}

	/**
	 * Planifier le traitement de la commande avec un délai
	 * Permet de s'assurer que toutes les métadonnées sont sauvegardées
	 *
	 * @param int $order_id ID de la commande.
	 */
	public function schedule_order_processing( int $order_id ): void {
		// Planifier le traitement après 5 secondes pour laisser le temps aux métadonnées d'être sauvegardées
		// et au statut de la commande d'être mis à jour
		wp_schedule_single_event( time() + 5, 'dc25_process_order_delayed', [ $order_id ] );
		
		// Logger
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf( 'DC25: Traitement de la commande %d planifié (délai 5s)', $order_id ),
				[ 'source' => 'dc25-vouchers' ]
			);
		}
	}

	/**
	 * Traiter la commande et générer les bons
	 *
	 * @param int $order_id ID de la commande.
	 */
	public function process_order( int $order_id ): void {
		// Logger pour déboguer (toujours, pas seulement en debug)
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf( 'DC25: process_order appelé pour commande %d', $order_id ),
				[ 'source' => 'dc25-vouchers' ]
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->log_warning( sprintf( 'DC25: Commande %d introuvable', $order_id ) );
			return;
		}

		// Logger le statut de la commande
		$order_status = $order->get_status();
		$date_paid = $order->get_date_paid();
		$is_paid_method = $order->is_paid();
		
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf(
					'DC25: Commande %d - Statut: %s, is_paid(): %s, date_paid: %s',
					$order_id,
					$order_status,
					$is_paid_method ? 'true' : 'false',
					$date_paid ? $date_paid->date( 'Y-m-d H:i:s' ) : 'null'
				),
				[ 'source' => 'dc25-vouchers' ]
			);
		}

		// Vérifier si la commande est payée
		// Statuts qui indiquent que la commande est payée ou en cours de traitement
		$paid_statuses = [ 'processing', 'completed', 'on-hold' ];
		
		// Vérifier si la commande est payée via différentes méthodes
		$is_paid = false;
		
		// 1. Vérifier le statut de la commande
		if ( in_array( $order_status, $paid_statuses, true ) ) {
			$is_paid = true;
		}
		
		// 2. Vérifier si le paiement est complété (méthode WooCommerce)
		if ( ! $is_paid && $is_paid_method ) {
			$is_paid = true;
		}
		
		// 3. Vérifier si une date de paiement existe
		if ( ! $is_paid && $date_paid ) {
			$is_paid = true;
		}
		
		// 4. Pour "pending", vérifier également si le total est payé (certains gateways)
		if ( ! $is_paid && 'pending' === $order_status ) {
			$total = $order->get_total();
			$paid_total = $order->get_total_paid();
			if ( $paid_total > 0 && $paid_total >= $total ) {
				$is_paid = true;
			}
		}
		
		// Ne pas générer si la commande n'est clairement pas payée
		if ( ! $is_paid ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->info(
					sprintf( 'DC25: Commande %d non payée (statut: %s), génération différée', $order_id, $order_status ),
					[ 'source' => 'dc25-vouchers' ]
				);
			}
			return;
		}

		// Protection contre les appels multiples simultanés (race condition)
		$lock_key = 'dc25_process_order_' . $order_id;
		$lock = get_transient( $lock_key );
		if ( $lock ) {
			// Un traitement est déjà en cours pour cette commande
			$this->log_debug( sprintf( 'DC25: Commande %d déjà en cours de traitement', $order_id ) );
			return;
		}

		// Créer un verrou (expire après 60 secondes)
		set_transient( $lock_key, time(), 60 );

		$has_gift_voucher = false;
		foreach ( $order->get_items() as $item_id => $item ) {
			// Récupérer le produit via l'ID stocké dans les métadonnées si get_product() échoue
			$product = $item->get_product();
			if ( ! $product ) {
				$product_id = $item->get_product_id();
				if ( $product_id > 0 ) {
					$product = wc_get_product( $product_id );
				}
			}
			
			if ( ! $product ) {
				$this->log_warning( sprintf( 'DC25: Produit introuvable pour item %d dans commande %d', $item_id, $order_id ) );
				continue;
			}
			
			$product_type = $product->get_type();
			if ( 'gift_voucher' !== $product_type ) {
				// Log uniquement en mode debug
				$this->log_debug( sprintf( 'DC25: Item %d dans commande %d n\'est pas un bon cadeau (type: %s)', $item_id, $order_id, $product_type ) );
				continue;
			}

			$has_gift_voucher = true;
			$this->log_debug( sprintf( 'DC25: Bon cadeau trouvé dans commande %d, item %d', $order_id, $item_id ) );

			// Récupérer les meta de l'item
			$amount = (float) $item->get_meta( '_dc25_gv_amount' );
			
			// Logger les métadonnées pour déboguer
			if ( function_exists( 'wc_get_logger' ) ) {
				$all_meta = $item->get_meta_data();
				$meta_keys = array_map( function( $meta ) {
					return $meta->get_data()['key'];
				}, $all_meta );
				wc_get_logger()->info(
					sprintf(
						'DC25: Item %d dans commande %d - Montant: %f, Métadonnées: %s',
						$item_id,
						$order_id,
						$amount,
						implode( ', ', $meta_keys )
					),
					[ 'source' => 'dc25-vouchers' ]
				);
			}
			
			if ( $amount <= 0 ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->warning(
						sprintf( 'DC25: Montant invalide pour item %d dans commande %d: %f', $item_id, $order_id, $amount ),
						[ 'source' => 'dc25-vouchers' ]
					);
				}
				// Si le montant n'est pas trouvé, essayer de le récupérer depuis le prix de l'item
				$item_total = $item->get_total();
				if ( $item_total > 0 ) {
					$amount = (float) $item_total;
					// Sauvegarder le montant si on le trouve
					$item->update_meta_data( '_dc25_gv_amount', $amount );
					$item->save();
					if ( function_exists( 'wc_get_logger' ) ) {
						wc_get_logger()->info(
							sprintf( 'DC25: Montant récupéré depuis item total: %f', $amount ),
							[ 'source' => 'dc25-vouchers' ]
						);
					}
				} else {
					continue; // Montant invalide et impossible à récupérer
				}
			}

			// Vérifier si déjà traité (double vérification après verrou)
			$coupon_code = $item->get_meta( '_dc25_gv_coupon_code' );
			if ( ! empty( $coupon_code ) ) {
				// Vérifier que le coupon existe vraiment dans WooCommerce
				/** @var WC_Coupon $coupon */
				$coupon = new WC_Coupon( $coupon_code );
				if ( $coupon->get_id() > 0 ) {
					continue; // Déjà traité et coupon valide
				}
				// Si le coupon n'existe pas, on continue pour le régénérer
			}

			// Générer le coupon
			try {
				$prefix = $product->get_coupon_prefix();
				$validity_days = $product->get_validity_days();
				$coupon = DC25_Coupon_Service::create_coupon( $amount, $prefix, $validity_days );

				if ( is_wp_error( $coupon ) ) {
					// Logger l'erreur
					if ( function_exists( 'wc_get_logger' ) ) {
						wc_get_logger()->error(
							sprintf( 'Erreur génération coupon pour commande %d: %s', $order_id, $coupon->get_error_message() ),
							[ 'source' => 'dc25-vouchers' ]
						);
					}
					continue;
				}
			} catch ( Exception $e ) {
				// Logger l'exception
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error(
						sprintf( 'Exception lors de la génération du coupon pour commande %d: %s', $order_id, $e->getMessage() ),
						[ 'source' => 'dc25-vouchers' ]
					);
				}
				continue;
			}

			$coupon_code = $coupon->get_code();
			$expiry_date_obj = $coupon->get_date_expires();
			if ( $expiry_date_obj ) {
				$expiry_date = $expiry_date_obj->date( 'Y-m-d' );
			} else {
				$expiry_timestamp = current_time( 'timestamp' ) + ( $validity_days * DAY_IN_SECONDS );
				$expiry_date = gmdate( 'Y-m-d', $expiry_timestamp );
			}

			// Sauvegarder le code coupon dans l'item
			$item->update_meta_data( '_dc25_gv_coupon_code', $coupon_code );
			$item->save();

			// Récupérer la langue de la commande pour le PDF
			$order_language = dc25_get_order_language( $order );
			
			// Obtenir la locale correspondante à la langue
			$locale_to_switch = null;
			if ( function_exists( 'wpml_get_language_locale' ) ) {
				$locale_to_switch = wpml_get_language_locale( $order_language );
			} elseif ( function_exists( 'icl_get_language_locale' ) ) {
				$locale_to_switch = icl_get_language_locale( $order_language );
			}
			
			// Si pas de locale trouvée, utiliser la locale par défaut de la langue
			if ( empty( $locale_to_switch ) ) {
				// Mapping simple des langues courantes
				$locale_map = [
					'fr' => 'fr_FR',
					'de' => 'de_DE',
					'en' => 'en_US',
					'it' => 'it_IT',
				];
				$locale_to_switch = $locale_map[ $order_language ] ?? get_locale();
			}

			// Générer le PDF
			$pdf_data = [
				'coupon_code'    => $coupon_code,
				'amount'         => $amount,
				'expiry_date'    => $expiry_date,
				'message'        => $item->get_meta( '_dc25_gv_message' ),
				'recipient_name' => $item->get_meta( '_dc25_gv_recipient_name' ),
				'from_name'      => $item->get_meta( '_dc25_gv_from_name' ) ?: trim( $order->get_formatted_billing_full_name() ),
			];

			// Changer temporairement la locale pour générer le PDF dans la bonne langue
			$previous_locale = null;
			if ( function_exists( 'switch_to_locale' ) && ! empty( $locale_to_switch ) ) {
				$previous_locale = switch_to_locale( $locale_to_switch );
			}

			try {
				$pdf_content = DC25_PDF_Service::generate_pdf_content( $pdf_data );

				if ( is_wp_error( $pdf_content ) ) {
					if ( function_exists( 'wc_get_logger' ) ) {
						wc_get_logger()->error(
							sprintf( 'Erreur génération PDF pour commande %d: %s', $order_id, $pdf_content->get_error_message() ),
							[ 'source' => 'dc25-vouchers' ]
						);
					}
					$pdf_content = null;
				}
			} catch ( Exception $e ) {
				// Logger l'exception
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error(
						sprintf( 'Exception lors de la génération du PDF pour commande %d: %s', $order_id, $e->getMessage() ),
						[ 'source' => 'dc25-vouchers' ]
					);
				}
				$pdf_content = null;
			} finally {
				// Restaurer la locale précédente
				if ( $previous_locale !== null && function_exists( 'restore_previous_locale' ) ) {
					restore_previous_locale();
				} elseif ( function_exists( 'restore_current_locale' ) ) {
					restore_current_locale();
				}
			}

			// Envoyer les emails
			$this->send_voucher_emails( $order, $item, $pdf_content );

			// Logger le succès (toujours, pas seulement en debug)
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->info(
					sprintf( 'DC25: Coupon %s généré avec succès pour commande %d', $coupon_code, $order_id ),
					[ 'source' => 'dc25-vouchers' ]
				);
			}
		}

		if ( ! $has_gift_voucher ) {
			$this->log_debug( sprintf( 'DC25: Aucun bon cadeau trouvé dans commande %d', $order_id ) );
		}

		// Libérer le verrou après traitement
		delete_transient( $lock_key );
	}

	/**
	 * Logger un message de débogage (uniquement si WP_DEBUG est activé)
	 *
	 * @param string $message Message à logger.
	 */
	private function log_debug( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			wc_get_logger()->debug( $message, [ 'source' => 'dc25-vouchers' ] );
		}
	}

	/**
	 * Logger un avertissement
	 *
	 * @param string $message Message à logger.
	 */
	private function log_warning( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, [ 'source' => 'dc25-vouchers' ] );
		}
	}

	/**
	 * Envoyer les emails avec le PDF
	 *
	 * @param WC_Order              $order Commande.
	 * @param WC_Order_Item_Product $item Item.
	 * @param string|null           $pdf_content Contenu du PDF.
	 */
	private function send_voucher_emails( $order, $item, ?string $pdf_content ): void {
		$settings = DC25_Settings::get_instance();

		// Récupérer la langue de la commande
		$order_language = dc25_get_order_language( $order );

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

		// Envoyer à l'acheteur avec la langue de la commande
		$this->send_email( $buyer_email, $buyer_name, $email_data, $pdf_content, false, $order_language );

		// Envoyer au destinataire si activé et renseigné
		if ( $settings->is_recipient_email_enabled() && ! empty( $recipient_email ) ) {
			$this->send_email( $recipient_email, $recipient_name ?: __( 'Destinataire', 'dc25-vouchers' ), $email_data, $pdf_content, true, $order_language );
		}
	}

	/**
	 * Envoyer un email
	 *
	 * @param string      $to Email destinataire.
	 * @param string      $name Nom destinataire.
	 * @param array       $data Données du bon.
	 * @param string|null $pdf_content Contenu du PDF.
	 * @param bool        $is_recipient Si c'est le destinataire.
	 * @param string|null $language Code de langue pour l'email (ex: 'fr', 'de').
	 */
	private function send_email( string $to, string $name, array $data, ?string $pdf_content, bool $is_recipient, ?string $language = null ): void {
		$settings = DC25_Settings::get_instance();

		// Utiliser le contenu configuré même pour l'acheteur (le mail destinataire a été désactivé).
		// Passer la langue de la commande pour récupérer le bon contenu traduit
		$subject = $settings->get_recipient_email_subject( $language );
		$message = $settings->get_recipient_email_content( $language );

		// Générer le lien de téléchargement
		$download_url = add_query_arg( 'dc25_gv_download', $data['coupon_code'], home_url() );
		
		// Remplacer les placeholders
		$replacements = [
			'{name}'         => $name,
			'{coupon_code}'  => $data['coupon_code'],
			'{amount}'       => wc_price( $data['amount'] ),
			'{message}'      => nl2br( esc_html( $data['message'] ?? '' ) ),
			'{site_name}'    => get_bloginfo( 'name' ),
			'{download_link}' => '<a href="' . esc_url( $download_url ) . '" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">' . esc_html__( 'Télécharger le PDF', 'dc25-vouchers' ) . '</a>',
			'{download_url}' => esc_url( $download_url ),
		];

		foreach ( $replacements as $placeholder => $value ) {
			$message = str_replace( $placeholder, $value, $message );
			$subject = str_replace( $placeholder, $value, $subject );
		}

		// Mise en forme en paragraphes pour éviter un affichage sur une seule ligne
		$message = wpautop( $message );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];

		// Attacher le PDF (créer un fichier temporaire)
		$attachments = [];
		if ( ! empty( $pdf_content ) && ! is_wp_error( $pdf_content ) ) {
			$temp_file = wp_tempnam( 'voucher-' . $data['coupon_code'] . '.pdf' );
			if ( $temp_file ) {
				file_put_contents( $temp_file, $pdf_content );
				$attachments[] = $temp_file;
			}
		}

		wp_mail( $to, $subject, $message, $headers, $attachments );

		// Nettoyer le fichier temporaire après envoi
		if ( ! empty( $attachments ) && file_exists( $attachments[0] ) ) {
			@unlink( $attachments[0] );
		}
	}

	/**
	 * Contenu email pour l'acheteur
	 *
	 * @param array $data Données.
	 * @return string
	 */
	private function get_buyer_email_content( array $data ): string {
		// Générer le lien de téléchargement
		$download_url = add_query_arg( 'dc25_gv_download', $data['coupon_code'], home_url() );
		
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
			<p style="margin: 20px 0;">
				<a href="<?php echo esc_url( $download_url ); ?>" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">
					<?php esc_html_e( 'Télécharger le PDF', 'dc25-vouchers' ); ?>
				</a>
			</p>
			<p style="font-size: 0.9em; color: #666;">
				<?php esc_html_e( 'Vous pouvez également télécharger le PDF à tout moment en utilisant le lien ci-dessus.', 'dc25-vouchers' ); ?>
			</p>
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
			$product = $item->get_product();
			if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
				continue;
			}

			$coupon_code = $item->get_meta( '_dc25_gv_coupon_code' );
			if ( empty( $coupon_code ) ) {
				continue;
			}

			// Générer le PDF à la volée
			$amount = (float) $item->get_meta( '_dc25_gv_amount' );
			if ( $amount <= 0 ) {
				continue;
			}

			// Récupérer la date d'expiration
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

			if ( empty( $expiry_date ) ) {
				$validity_days = $product->get_validity_days();
				$expiry_date = date( 'Y-m-d', strtotime( "+{$validity_days} days" ) );
			}

			$pdf_data = [
				'coupon_code'    => $coupon_code,
				'amount'         => $amount,
				'expiry_date'    => $expiry_date,
				'message'        => $item->get_meta( '_dc25_gv_message' ),
				'recipient_name' => $item->get_meta( '_dc25_gv_recipient_name' ),
			];

			// Récupérer la langue de la commande pour le PDF
			$order_language = dc25_get_order_language( $order );
			
			// Obtenir la locale correspondante à la langue
			$locale_to_switch = null;
			if ( function_exists( 'wpml_get_language_locale' ) ) {
				$locale_to_switch = wpml_get_language_locale( $order_language );
			} elseif ( function_exists( 'icl_get_language_locale' ) ) {
				$locale_to_switch = icl_get_language_locale( $order_language );
			}
			
			// Si pas de locale trouvée, utiliser la locale par défaut de la langue
			if ( empty( $locale_to_switch ) ) {
				// Mapping simple des langues courantes
				$locale_map = [
					'fr' => 'fr_FR',
					'de' => 'de_DE',
					'en' => 'en_US',
					'it' => 'it_IT',
				];
				$locale_to_switch = $locale_map[ $order_language ] ?? get_locale();
			}

			// Changer temporairement la locale pour générer le PDF dans la bonne langue
			$previous_locale = null;
			if ( function_exists( 'switch_to_locale' ) && ! empty( $locale_to_switch ) ) {
				$previous_locale = switch_to_locale( $locale_to_switch );
			}

			try {
				$pdf_content = DC25_PDF_Service::generate_pdf_content( $pdf_data );
				if ( ! is_wp_error( $pdf_content ) && ! empty( $pdf_content ) ) {
					// Créer un fichier temporaire pour l'attachement
					$temp_file = wp_tempnam( 'voucher-' . $coupon_code . '.pdf' );
					if ( $temp_file ) {
						file_put_contents( $temp_file, $pdf_content );
						$attachments[] = $temp_file;
					}
				}
			} finally {
				// Restaurer la locale précédente
				if ( $previous_locale !== null && function_exists( 'restore_previous_locale' ) ) {
					restore_previous_locale();
				} elseif ( function_exists( 'restore_current_locale' ) ) {
					restore_current_locale();
				}
			}
		}

		return $attachments;
	}

	/**
	 * Ajouter le lien de téléchargement du PDF dans l'email de confirmation
	 *
	 * @param WC_Order    $order Commande.
	 * @param bool         $sent_to_admin Si l'email est envoyé à l'admin.
	 * @param bool         $plain_text Si l'email est en texte brut.
	 * @param WC_Email     $email Objet email.
	 */
	public function add_pdf_download_link_to_email( $order, $sent_to_admin, $plain_text, $email ): void {
		// Uniquement pour l'email client de commande complétée
		if ( 'customer_completed_order' !== $email->id ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$voucher_items = [];
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
				continue;
			}

			$coupon_code = $item->get_meta( '_dc25_gv_coupon_code' );
			if ( empty( $coupon_code ) ) {
				continue;
			}

			$voucher_items[] = [
				'coupon_code' => $coupon_code,
				'item_name'   => $item->get_name(),
			];
		}

		if ( empty( $voucher_items ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n\n" . __( 'Bons cadeaux:', 'dc25-vouchers' ) . "\n";
			foreach ( $voucher_items as $voucher ) {
				$download_url = add_query_arg( 'dc25_gv_download', $voucher['coupon_code'], home_url() );
				printf(
					// translators: %1$s: nom du bon cadeau, %2$s: code coupon, %3$s: URL de téléchargement
					__( '%1$s (Code: %2$s) - Télécharger le PDF: %3$s', 'dc25-vouchers' ) . "\n",
					esc_html( $voucher['item_name'] ),
					esc_html( $voucher['coupon_code'] ),
					esc_url( $download_url )
				);
			}
		} else {
			echo '<div style="margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-left: 4px solid #0073aa;">';
			echo '<h3 style="margin-top: 0;">' . esc_html__( 'Bons cadeaux', 'dc25-vouchers' ) . '</h3>';
			foreach ( $voucher_items as $voucher ) {
				$download_url = add_query_arg( 'dc25_gv_download', $voucher['coupon_code'], home_url() );
				echo '<p>';
				echo '<strong>' . esc_html( $voucher['item_name'] ) . '</strong><br>';
				printf(
					// translators: %1$s: code coupon, %2$s: URL de téléchargement
					esc_html__( 'Code: %1$s', 'dc25-vouchers' ) . '<br>',
					'<code>' . esc_html( $voucher['coupon_code'] ) . '</code>'
				);
				echo '<a href="' . esc_url( $download_url ) . '" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 3px;">';
				echo esc_html__( 'Télécharger le PDF', 'dc25-vouchers' );
				echo '</a>';
				echo '</p>';
			}
			echo '</div>';
		}
	}
}

