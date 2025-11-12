<?php
/**
 * Gestion des champs checkout personnalisés
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour les champs checkout
 */
class DC25_Checkout_Fields {

	/**
	 * Constructeur
	 */
	public function __construct() {
		// Ajouter les champs sur la page produit (avant le bouton "Ajouter au panier")
		// Utiliser plusieurs hooks pour être sûr que ça fonctionne
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_product_page_fields' ], 5 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'add_product_page_fields' ], 25 ); // Après le prix, avant le bouton
		add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'add_product_page_fields' ], 10 ); // Avant le formulaire
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'add_product_page_fields' ], 5 ); // Après le résumé (fallback)

		// Valider les champs avant l'ajout au panier
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_product_page_fields' ], 10, 3 );

		// Sauvegarder les données dans le panier
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );

		// Afficher les données dans le panier
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 10, 2 );

		// Ajouter les champs checkout
		add_filter( 'woocommerce_checkout_fields', [ $this, 'add_checkout_fields' ] );

		// Validation des champs
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_checkout_fields' ] );

		// Sauvegarder les meta dans les items de commande
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item_meta' ], 10, 4 );

		// Afficher les meta dans l'admin
		add_action( 'woocommerce_after_order_itemmeta', [ $this, 'display_order_item_meta' ], 10, 3 );

		// Gérer le prix libre
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_custom_price' ], 10, 1 );
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_price_from_checkout' ] );

		// Scripts checkout et page produit
		// Utiliser une priorité plus basse pour s'assurer que WooCommerce est chargé
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_scripts' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_product_page_scripts' ], 20 );
		add_action( 'wp_head', [ $this, 'add_product_page_styles' ] );
	}

	/**
	 * Vérifier si le panier contient un bon cadeau
	 *
	 * @return bool
	 */
	private function cart_has_gift_voucher(): bool {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product && 'gift_voucher' === $product->get_type() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Obtenir le produit gift_voucher du panier
	 *
	 * @return WC_Product_Gift_Voucher|null
	 */
	private function get_cart_gift_voucher_product(): ?WC_Product_Gift_Voucher {
		if ( ! WC()->cart ) {
			return null;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product && 'gift_voucher' === $product->get_type() ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Ajouter les champs checkout
	 *
	 * @param array $fields Champs checkout existants.
	 * @return array
	 */
	public function add_checkout_fields( array $fields ): array {
		if ( ! $this->cart_has_gift_voucher() ) {
			return $fields;
		}

		$product = $this->get_cart_gift_voucher_product();
		if ( ! $product ) {
			return $fields;
		}

		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();
		$default_amount = $product->get_default_amount();
		$physical_enabled = $product->is_physical_enabled();

		// Section dédiée aux bons cadeaux
		$fields['dc25_gift_voucher'] = [
			'dc25_gv_amount' => [
				'type'              => 'number',
				'label'             => __( 'Montant du bon cadeau (CHF)', 'dc25-vouchers' ),
				'required'          => true,
				'class'             => [ 'form-row-wide' ],
				'custom_attributes' => [
					'step' => '0.01',
					'min'  => $min_amount,
					'max'  => $max_amount,
					'data-min' => $min_amount,
					'data-max' => $max_amount,
				],
				'default'           => $default_amount,
				'priority'          => 10,
				'description'       => sprintf(
					/* translators: 1: montant minimum, 2: montant maximum */
					__( '💝 Vous choisissez librement le montant de votre bon cadeau entre %1$s CHF et %2$s CHF.', 'dc25-vouchers' ),
					number_format_i18n( $min_amount, 2 ),
					number_format_i18n( $max_amount, 2 )
				),
			],
			'dc25_gv_message' => [
				'type'        => 'textarea',
				'label'       => __( 'Message personnalisé', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-wide' ],
				'placeholder' => __( 'Votre message pour le destinataire...', 'dc25-vouchers' ),
				'priority'    => 20,
			],
			'dc25_gv_recipient_name' => [
				'type'        => 'text',
				'label'       => __( 'Nom du destinataire', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-first' ],
				'placeholder' => __( 'Nom du destinataire (optionnel)', 'dc25-vouchers' ),
				'priority'    => 30,
			],
			'dc25_gv_recipient_email' => [
				'type'        => 'email',
				'label'       => __( 'Email du destinataire', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-last' ],
				'placeholder' => __( 'email@example.com (optionnel)', 'dc25-vouchers' ),
				'priority'    => 40,
			],
		];

		// Champs pour l'envoi physique
		if ( $physical_enabled ) {
			$fields['dc25_gift_voucher']['dc25_gv_physical'] = [
				'type'        => 'checkbox',
				'label'       => __( 'Je souhaite recevoir le bon cadeau par courrier', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-wide' ],
				'priority'    => 50,
			];

			$fields['dc25_gift_voucher']['dc25_gv_ship_to'] = [
				'type'        => 'radio',
				'label'       => __( 'Envoyer à', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-wide', 'dc25-ship-to-radio' ],
				'options'     => [
					'billing'   => __( 'Mon adresse de facturation', 'dc25-vouchers' ),
					'recipient' => __( 'L\'adresse du destinataire', 'dc25-vouchers' ),
				],
				'default'     => 'billing',
				'priority'    => 60,
			];

			// Champs d'adresse destinataire (affichés conditionnellement)
			$fields['dc25_gift_voucher']['dc25_gv_recipient_address_1'] = [
				'type'        => 'text',
				'label'       => __( 'Adresse du destinataire', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-wide', 'dc25-recipient-address' ],
				'priority'    => 70,
			];

			$fields['dc25_gift_voucher']['dc25_gv_recipient_address_2'] = [
				'type'        => 'text',
				'label'       => __( 'Complément d\'adresse', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-wide', 'dc25-recipient-address' ],
				'priority'    => 80,
			];

			$fields['dc25_gift_voucher']['dc25_gv_recipient_city'] = [
				'type'        => 'text',
				'label'       => __( 'Ville', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-first', 'dc25-recipient-address' ],
				'priority'    => 90,
			];

			$fields['dc25_gift_voucher']['dc25_gv_recipient_postcode'] = [
				'type'        => 'text',
				'label'       => __( 'Code postal', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-last', 'dc25-recipient-address' ],
				'priority'    => 100,
			];

			$fields['dc25_gift_voucher']['dc25_gv_recipient_country'] = [
				'type'        => 'country',
				'label'       => __( 'Pays', 'dc25-vouchers' ),
				'required'    => false,
				'class'       => [ 'form-row-wide', 'dc25-recipient-address' ],
				'priority'    => 110,
			];
		}

		return $fields;
	}

	/**
	 * Valider les champs checkout
	 */
	public function validate_checkout_fields(): void {
		if ( ! $this->cart_has_gift_voucher() ) {
			return;
		}

		$product = $this->get_cart_gift_voucher_product();
		if ( ! $product ) {
			return;
		}

		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();

		// Validation du montant
		if ( empty( $_POST['dc25_gv_amount'] ) ) {
			wc_add_notice( __( 'Veuillez saisir un montant pour le bon cadeau.', 'dc25-vouchers' ), 'error' );
			return;
		}

		$amount = floatval( $_POST['dc25_gv_amount'] );

		if ( $amount < $min_amount || $amount > $max_amount ) {
			wc_add_notice(
				sprintf(
					/* translators: 1: montant minimum, 2: montant maximum */
					__( 'Le montant doit être compris entre %1$s CHF et %2$s CHF.', 'dc25-vouchers' ),
					number_format_i18n( $min_amount, 2 ),
					number_format_i18n( $max_amount, 2 )
				),
				'error'
			);
		}

		// Validation email destinataire
		if ( ! empty( $_POST['dc25_gv_recipient_email'] ) && ! is_email( $_POST['dc25_gv_recipient_email'] ) ) {
			wc_add_notice( __( 'L\'adresse email du destinataire n\'est pas valide.', 'dc25-vouchers' ), 'error' );
		}

		// Validation adresse destinataire si envoi physique + destinataire
		if ( ! empty( $_POST['dc25_gv_physical'] ) && 'recipient' === $_POST['dc25_gv_ship_to'] ) {
			if ( empty( $_POST['dc25_gv_recipient_address_1'] ) || empty( $_POST['dc25_gv_recipient_city'] ) || empty( $_POST['dc25_gv_recipient_postcode'] ) ) {
				wc_add_notice( __( 'Veuillez compléter l\'adresse du destinataire pour l\'envoi physique.', 'dc25-vouchers' ), 'error' );
			}
		}
	}

	/**
	 * Sauvegarder les meta dans les items de commande
	 *
	 * @param WC_Order_Item_Product $item Item de commande.
	 * @param string                $cart_item_key Clé du panier.
	 * @param array                 $values Valeurs du panier.
	 * @param WC_Order              $order Commande.
	 */
	public function save_order_item_meta( $item, string $cart_item_key, array $values, $order ): void {
		$product = $item->get_product();
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		// Montant
		if ( ! empty( $_POST['dc25_gv_amount'] ) ) {
			$item->update_meta_data( '_dc25_gv_amount', floatval( $_POST['dc25_gv_amount'] ) );
		}

		// Message
		if ( ! empty( $_POST['dc25_gv_message'] ) ) {
			$item->update_meta_data( '_dc25_gv_message', sanitize_textarea_field( $_POST['dc25_gv_message'] ) );
		}

		// Destinataire
		if ( ! empty( $_POST['dc25_gv_recipient_name'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_name', sanitize_text_field( $_POST['dc25_gv_recipient_name'] ) );
		}

		if ( ! empty( $_POST['dc25_gv_recipient_email'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_email', sanitize_email( $_POST['dc25_gv_recipient_email'] ) );
		}

		// Envoi physique
		$item->update_meta_data( '_dc25_gv_physical', ! empty( $_POST['dc25_gv_physical'] ) ? 'yes' : 'no' );

		// Destination envoi
		if ( ! empty( $_POST['dc25_gv_ship_to'] ) ) {
			$item->update_meta_data( '_dc25_gv_ship_to', sanitize_text_field( $_POST['dc25_gv_ship_to'] ) );
		}

		// Adresse destinataire
		if ( ! empty( $_POST['dc25_gv_recipient_address_1'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_address_1', sanitize_text_field( $_POST['dc25_gv_recipient_address_1'] ) );
		}

		if ( ! empty( $_POST['dc25_gv_recipient_address_2'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_address_2', sanitize_text_field( $_POST['dc25_gv_recipient_address_2'] ) );
		}

		if ( ! empty( $_POST['dc25_gv_recipient_city'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_address_city', sanitize_text_field( $_POST['dc25_gv_recipient_city'] ) );
		}

		if ( ! empty( $_POST['dc25_gv_recipient_postcode'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_address_postcode', sanitize_text_field( $_POST['dc25_gv_recipient_postcode'] ) );
		}

		if ( ! empty( $_POST['dc25_gv_recipient_country'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_address_country', sanitize_text_field( $_POST['dc25_gv_recipient_country'] ) );
		}
	}

	/**
	 * Afficher les meta dans l'admin
	 *
	 * @param int                   $item_id ID de l'item.
	 * @param array                 $item Données de l'item.
	 * @param WC_Product|null       $product Produit.
	 */
	public function display_order_item_meta( int $item_id, array $item, $product ): void {
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		$amount = wc_get_order_item_meta( $item_id, '_dc25_gv_amount', true );
		$message = wc_get_order_item_meta( $item_id, '_dc25_gv_message', true );
		$recipient_name = wc_get_order_item_meta( $item_id, '_dc25_gv_recipient_name', true );
		$recipient_email = wc_get_order_item_meta( $item_id, '_dc25_gv_recipient_email', true );
		$physical = wc_get_order_item_meta( $item_id, '_dc25_gv_physical', true );
		$coupon_code = wc_get_order_item_meta( $item_id, '_dc25_gv_coupon_code', true );
		$pdf_path = wc_get_order_item_meta( $item_id, '_dc25_gv_pdf_path', true );

		?>
		<div class="dc25-voucher-meta" style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px;">
			<h4><?php esc_html_e( 'Détails du bon cadeau', 'dc25-vouchers' ); ?></h4>
			<?php if ( $amount ) : ?>
				<p><strong><?php esc_html_e( 'Montant:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( wc_price( $amount ) ); ?></p>
			<?php endif; ?>
			<?php if ( $coupon_code ) : ?>
				<p><strong><?php esc_html_e( 'Code coupon:', 'dc25-vouchers' ); ?></strong> <code><?php echo esc_html( $coupon_code ); ?></code></p>
			<?php endif; ?>
			<?php if ( $message ) : ?>
				<p><strong><?php esc_html_e( 'Message:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
			<?php if ( $recipient_name ) : ?>
				<p><strong><?php esc_html_e( 'Destinataire:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( $recipient_name ); ?></p>
			<?php endif; ?>
			<?php if ( $recipient_email ) : ?>
				<p><strong><?php esc_html_e( 'Email destinataire:', 'dc25-vouchers' ); ?></strong> <?php echo esc_html( $recipient_email ); ?></p>
			<?php endif; ?>
			<?php if ( 'yes' === $physical ) : ?>
				<p><strong><?php esc_html_e( 'Envoi physique:', 'dc25-vouchers' ); ?></strong> <?php esc_html_e( 'Oui', 'dc25-vouchers' ); ?></p>
			<?php endif; ?>
			<?php if ( $pdf_path ) : ?>
				<p><strong><?php esc_html_e( 'PDF:', 'dc25-vouchers' ); ?></strong> <a href="<?php echo esc_url( wp_get_attachment_url( $pdf_path ) ); ?>" target="_blank"><?php esc_html_e( 'Télécharger', 'dc25-vouchers' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Appliquer le prix libre au produit
	 *
	 * @param WC_Cart $cart Panier.
	 */
	public function apply_custom_price( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			if ( $product && 'gift_voucher' === $product->get_type() ) {
				// Récupérer le montant depuis les données du panier (priorité)
				$amount = null;
				if ( isset( $cart_item['dc25_gv_amount'] ) ) {
					$amount = floatval( $cart_item['dc25_gv_amount'] );
				} elseif ( isset( $_POST['dc25_gv_amount'] ) && ! empty( $_POST['dc25_gv_amount'] ) ) {
					$amount = floatval( $_POST['dc25_gv_amount'] );
					// Sauvegarder en session pour les requêtes AJAX
					if ( WC()->session ) {
						WC()->session->set( 'dc25_gv_amount', $amount );
					}
				} elseif ( WC()->session && WC()->session->get( 'dc25_gv_amount' ) ) {
					$amount = floatval( WC()->session->get( 'dc25_gv_amount' ) );
				}

				if ( null !== $amount && $amount > 0 ) {
					$cart->cart_contents[ $cart_item_key ]['data']->set_price( $amount );
				}
			}
		}
	}

	/**
	 * Mettre à jour le prix depuis le checkout (AJAX)
	 *
	 * @param string $post_data Données POST.
	 */
	public function update_price_from_checkout( string $post_data ): void {
		parse_str( $post_data, $posted );
		if ( isset( $posted['dc25_gv_amount'] ) && ! empty( $posted['dc25_gv_amount'] ) ) {
			$amount = floatval( $posted['dc25_gv_amount'] );
			if ( WC()->session ) {
				WC()->session->set( 'dc25_gv_amount', $amount );
			}
		}
	}

	/**
	 * Enqueue scripts pour les champs conditionnels
	 */
	public function enqueue_checkout_scripts(): void {
		if ( ! is_checkout() || ! $this->cart_has_gift_voucher() ) {
			return;
		}

		$product = $this->get_cart_gift_voucher_product();
		if ( ! $product ) {
			return;
		}

		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();

		// Enqueue le script JavaScript
		$script_url = DC25_URL . 'assets/js/gift-voucher-checkout.js';
		$script_version = file_exists( DC25_PATH . 'assets/js/gift-voucher-checkout.js' ) 
			? filemtime( DC25_PATH . 'assets/js/gift-voucher-checkout.js' ) 
			: DC25_VERSION;

		wp_enqueue_script(
			'dc25-gift-voucher-checkout',
			$script_url,
			[ 'wc-checkout' ], // Dépendance: WooCommerce checkout
			$script_version,
			true // Dans le footer
		);

		// Passer les données au JavaScript
		wp_localize_script(
			'dc25-gift-voucher-checkout',
			'dc25GiftVoucherCheckout',
			[
				'minAmount' => $min_amount,
				'maxAmount' => $max_amount,
			]
		);
	}


	/**
	 * Ajouter les champs sur la page produit
	 * 
	 * @param bool $force Forcer l'affichage même si déjà affiché (pour appel direct).
	 */
	public function add_product_page_fields( bool $force = false ): void {
		// Éviter les doublons - utiliser un flag statique (sauf si forcé)
		static $fields_added = false;
		if ( $fields_added && ! $force ) {
			return;
		}

		// Récupérer le produit de différentes manières
		global $product;
		if ( ! $product ) {
			$product = wc_get_product( get_the_ID() );
		}
		
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}
		
		// Marquer comme ajouté
		$fields_added = true;

		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();
		// Utiliser le prix officiel du produit comme valeur par défaut
		$default_amount = $product->get_price();
		// Si le prix est vide ou 0, utiliser le montant par défaut configuré
		if ( empty( $default_amount ) || $default_amount <= 0 ) {
			$default_amount = $product->get_default_amount();
		}
		$physical_enabled = $product->is_physical_enabled();

		?>
		<!-- DC25 Gift Voucher Fields - Product Type: <?php echo esc_attr( $product->get_type() ); ?> - Product ID: <?php echo esc_attr( $product->get_id() ); ?> -->
		<div class="dc25-gift-voucher-fields" style="margin: 20px 0; padding: 20px; border: 2px solid #2271b1; border-radius: 4px; background: #f9f9f9;">
			<h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Personnalisez votre bon cadeau', 'dc25-vouchers' ); ?></h3>
			
			<script type="text/javascript">
			// Script qui s'exécute immédiatement
			(function() {
				console.log('✅ DC25 Gift Voucher: Fields HTML loaded!');
				console.log('Product type: <?php echo esc_js( $product->get_type() ); ?>');
				console.log('Default amount: <?php echo esc_js( $default_amount ); ?>');
				console.log('Min: <?php echo esc_js( $min_amount ); ?>, Max: <?php echo esc_js( $max_amount ); ?>');
				
				// Vérifier si le bouton existe dans le DOM
				document.addEventListener('DOMContentLoaded', function() {
					setTimeout(function() {
						var btn = document.querySelector('button.single_add_to_cart_button, input.single_add_to_cart_button, .single_add_to_cart_button');
						if (btn) {
							console.log('✅ Add to cart button found in DOM');
							console.log('Button visible:', btn.offsetParent !== null);
							console.log('Button display:', window.getComputedStyle(btn).display);
						} else {
							console.error('❌ Add to cart button NOT found in DOM');
							// Chercher le formulaire add to cart
							var form = document.querySelector('form.cart, .cart');
							if (form) {
								console.log('Cart form found:', form);
							} else {
								console.error('❌ Cart form NOT found');
							}
						}
					}, 200);
				});
				
				// Ne pas désactiver le bouton ici - laisser le script externe le faire
				// Juste vérifier si le script externe se chargera
				document.addEventListener('DOMContentLoaded', function() {
					console.log('DC25 Gift Voucher: DOM loaded, checking for external script...');
					setTimeout(function() {
						if (typeof dc25GiftVoucher === 'undefined') {
							console.error('❌ DC25 Gift Voucher: External script not loaded!');
							console.error('Trying to load script manually...');
							
							// Définir les données AVANT de charger le script
							window.dc25GiftVoucher = {
								minAmount: <?php echo esc_js( $min_amount ); ?>,
								maxAmount: <?php echo esc_js( $max_amount ); ?>,
								defaultAmount: <?php echo esc_js( $default_amount ); ?>
							};
							
							// Essayer de charger le script manuellement
							var script = document.createElement('script');
							script.src = '<?php echo esc_js( DC25_URL . 'assets/js/gift-voucher-product.js' ); ?>?ver=<?php echo esc_js( DC25_VERSION ); ?>';
							script.onload = function() {
								console.log('✅ Script loaded manually with data!', window.dc25GiftVoucher);
							};
							script.onerror = function() {
								console.error('❌ Failed to load script manually');
							};
							document.body.appendChild(script);
						} else {
							console.log('✅ DC25 Gift Voucher: External script loaded!', dc25GiftVoucher);
						}
					}, 500);
				});
			})();
			</script>

			<p class="form-row form-row-wide">
				<label for="dc25_gv_amount">
					<?php esc_html_e( 'Montant du bon cadeau (CHF)', 'dc25-vouchers' ); ?>
					<span class="required">*</span>
				</label>
				<input 
					type="number" 
					id="dc25_gv_amount" 
					name="dc25_gv_amount" 
					class="input-text" 
					step="0.01" 
					min="<?php echo esc_attr( $min_amount ); ?>" 
					max="<?php echo esc_attr( $max_amount ); ?>"
					value="<?php echo esc_attr( $default_amount ); ?>"
					required
					placeholder="<?php printf( esc_attr__( 'Entre %s et %s CHF', 'dc25-vouchers' ), number_format_i18n( $min_amount, 2 ), number_format_i18n( $max_amount, 2 ) ); ?>"
				/>
				<span class="description">
					<?php
					printf(
						/* translators: 1: montant minimum, 2: montant maximum */
						esc_html__( '💝 Vous choisissez librement le montant entre %1$s CHF et %2$s CHF.', 'dc25-vouchers' ),
						number_format_i18n( $min_amount, 2 ),
						number_format_i18n( $max_amount, 2 )
					);
					?>
				</span>
			</p>

			<p class="form-row form-row-wide">
				<label for="dc25_gv_message">
					<?php esc_html_e( 'Message personnalisé', 'dc25-vouchers' ); ?>
				</label>
				<textarea 
					id="dc25_gv_message" 
					name="dc25_gv_message" 
					class="input-text" 
					rows="4" 
					placeholder="<?php esc_attr_e( 'Votre message pour le destinataire...', 'dc25-vouchers' ); ?>"
				></textarea>
			</p>

			<p class="form-row form-row-first">
				<label for="dc25_gv_recipient_name">
					<?php esc_html_e( 'Nom du destinataire', 'dc25-vouchers' ); ?>
				</label>
				<input 
					type="text" 
					id="dc25_gv_recipient_name" 
					name="dc25_gv_recipient_name" 
					class="input-text" 
					placeholder="<?php esc_attr_e( 'Nom du destinataire (optionnel)', 'dc25-vouchers' ); ?>"
				/>
			</p>

			<p class="form-row form-row-last">
				<label for="dc25_gv_recipient_email">
					<?php esc_html_e( 'Email du destinataire', 'dc25-vouchers' ); ?>
				</label>
				<input 
					type="email" 
					id="dc25_gv_recipient_email" 
					name="dc25_gv_recipient_email" 
					class="input-text" 
					placeholder="<?php esc_attr_e( 'email@example.com (optionnel)', 'dc25-vouchers' ); ?>"
				/>
			</p>

			<?php if ( $physical_enabled ) : ?>
				<p class="form-row form-row-wide">
					<label>
						<input 
							type="checkbox" 
							id="dc25_gv_physical" 
							name="dc25_gv_physical" 
							value="1"
						/>
						<?php esc_html_e( 'Je souhaite recevoir le bon cadeau par courrier', 'dc25-vouchers' ); ?>
					</label>
				</p>

				<div class="dc25-ship-to-wrapper" style="display: none;">
					<p class="form-row form-row-wide">
						<label><?php esc_html_e( 'Envoyer à', 'dc25-vouchers' ); ?></label>
						<label style="display: block; margin: 5px 0;">
							<input type="radio" name="dc25_gv_ship_to" value="billing" checked />
							<?php esc_html_e( 'Mon adresse de facturation', 'dc25-vouchers' ); ?>
						</label>
						<label style="display: block; margin: 5px 0;">
							<input type="radio" name="dc25_gv_ship_to" value="recipient" />
							<?php esc_html_e( 'L\'adresse du destinataire', 'dc25-vouchers' ); ?>
						</label>
					</p>

					<div class="dc25-recipient-address-fields" style="display: none;">
						<p class="form-row form-row-wide">
							<label for="dc25_gv_recipient_address_1">
								<?php esc_html_e( 'Adresse du destinataire', 'dc25-vouchers' ); ?>
							</label>
							<input 
								type="text" 
								id="dc25_gv_recipient_address_1" 
								name="dc25_gv_recipient_address_1" 
								class="input-text"
							/>
						</p>

						<p class="form-row form-row-wide">
							<label for="dc25_gv_recipient_address_2">
								<?php esc_html_e( 'Complément d\'adresse', 'dc25-vouchers' ); ?>
							</label>
							<input 
								type="text" 
								id="dc25_gv_recipient_address_2" 
								name="dc25_gv_recipient_address_2" 
								class="input-text"
							/>
						</p>

						<p class="form-row form-row-first">
							<label for="dc25_gv_recipient_city">
								<?php esc_html_e( 'Ville', 'dc25-vouchers' ); ?>
							</label>
							<input 
								type="text" 
								id="dc25_gv_recipient_city" 
								name="dc25_gv_recipient_city" 
								class="input-text"
							/>
						</p>

						<p class="form-row form-row-last">
							<label for="dc25_gv_recipient_postcode">
								<?php esc_html_e( 'Code postal', 'dc25-vouchers' ); ?>
							</label>
							<input 
								type="text" 
								id="dc25_gv_recipient_postcode" 
								name="dc25_gv_recipient_postcode" 
								class="input-text"
							/>
						</p>

						<p class="form-row form-row-wide">
							<label for="dc25_gv_recipient_country">
								<?php esc_html_e( 'Pays', 'dc25-vouchers' ); ?>
							</label>
							<select 
								id="dc25_gv_recipient_country" 
								name="dc25_gv_recipient_country" 
								class="input-text"
							>
								<?php
								$countries = WC()->countries->get_countries();
								foreach ( $countries as $code => $name ) {
									printf(
										'<option value="%s">%s</option>',
										esc_attr( $code ),
										esc_html( $name )
									);
								}
								?>
							</select>
						</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Valider les champs de la page produit avant l'ajout au panier
	 *
	 * @param bool  $passed Validation passée.
	 * @param int   $product_id ID du produit.
	 * @param int   $quantity Quantité.
	 * @return bool
	 */
	public function validate_product_page_fields( bool $passed, int $product_id, int $quantity ): bool {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return $passed;
		}

		// Validation du montant
		if ( empty( $_POST['dc25_gv_amount'] ) ) {
			wc_add_notice( __( 'Veuillez saisir un montant pour le bon cadeau.', 'dc25-vouchers' ), 'error' );
			return false;
		}

		$amount = floatval( $_POST['dc25_gv_amount'] );
		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();

		if ( $amount < $min_amount || $amount > $max_amount ) {
			wc_add_notice(
				sprintf(
					/* translators: 1: montant minimum, 2: montant maximum */
					__( 'Le montant doit être compris entre %1$s CHF et %2$s CHF.', 'dc25-vouchers' ),
					number_format_i18n( $min_amount, 2 ),
					number_format_i18n( $max_amount, 2 )
				),
				'error'
			);
			return false;
		}

		// Validation email destinataire
		if ( ! empty( $_POST['dc25_gv_recipient_email'] ) && ! is_email( $_POST['dc25_gv_recipient_email'] ) ) {
			wc_add_notice( __( 'L\'adresse email du destinataire n\'est pas valide.', 'dc25-vouchers' ), 'error' );
			return false;
		}

		// Validation adresse destinataire si envoi physique + destinataire
		if ( ! empty( $_POST['dc25_gv_physical'] ) && isset( $_POST['dc25_gv_ship_to'] ) && 'recipient' === $_POST['dc25_gv_ship_to'] ) {
			if ( empty( $_POST['dc25_gv_recipient_address_1'] ) || empty( $_POST['dc25_gv_recipient_city'] ) || empty( $_POST['dc25_gv_recipient_postcode'] ) ) {
				wc_add_notice( __( 'Veuillez compléter l\'adresse du destinataire pour l\'envoi physique.', 'dc25-vouchers' ), 'error' );
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Ajouter les données personnalisées au panier
	 *
	 * @param array $cart_item_data Données du panier.
	 * @param int   $product_id ID du produit.
	 * @param int   $variation_id ID de la variation.
	 * @return array
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return $cart_item_data;
		}

		// Montant
		if ( isset( $_POST['dc25_gv_amount'] ) ) {
			$cart_item_data['dc25_gv_amount'] = floatval( $_POST['dc25_gv_amount'] );
		}

		// Message
		if ( isset( $_POST['dc25_gv_message'] ) ) {
			$cart_item_data['dc25_gv_message'] = sanitize_textarea_field( $_POST['dc25_gv_message'] );
		}

		// Destinataire
		if ( isset( $_POST['dc25_gv_recipient_name'] ) ) {
			$cart_item_data['dc25_gv_recipient_name'] = sanitize_text_field( $_POST['dc25_gv_recipient_name'] );
		}

		if ( isset( $_POST['dc25_gv_recipient_email'] ) ) {
			$cart_item_data['dc25_gv_recipient_email'] = sanitize_email( $_POST['dc25_gv_recipient_email'] );
		}

		// Envoi physique
		if ( isset( $_POST['dc25_gv_physical'] ) ) {
			$cart_item_data['dc25_gv_physical'] = 'yes';
		}

		if ( isset( $_POST['dc25_gv_ship_to'] ) ) {
			$cart_item_data['dc25_gv_ship_to'] = sanitize_text_field( $_POST['dc25_gv_ship_to'] );
		}

		// Adresse destinataire
		if ( isset( $_POST['dc25_gv_recipient_address_1'] ) ) {
			$cart_item_data['dc25_gv_recipient_address_1'] = sanitize_text_field( $_POST['dc25_gv_recipient_address_1'] );
		}

		if ( isset( $_POST['dc25_gv_recipient_address_2'] ) ) {
			$cart_item_data['dc25_gv_recipient_address_2'] = sanitize_text_field( $_POST['dc25_gv_recipient_address_2'] );
		}

		if ( isset( $_POST['dc25_gv_recipient_city'] ) ) {
			$cart_item_data['dc25_gv_recipient_city'] = sanitize_text_field( $_POST['dc25_gv_recipient_city'] );
		}

		if ( isset( $_POST['dc25_gv_recipient_postcode'] ) ) {
			$cart_item_data['dc25_gv_recipient_postcode'] = sanitize_text_field( $_POST['dc25_gv_recipient_postcode'] );
		}

		if ( isset( $_POST['dc25_gv_recipient_country'] ) ) {
			$cart_item_data['dc25_gv_recipient_country'] = sanitize_text_field( $_POST['dc25_gv_recipient_country'] );
		}

		return $cart_item_data;
	}

	/**
	 * Afficher les données personnalisées dans le panier
	 *
	 * @param array $item_data Données de l'item.
	 * @param array $cart_item Item du panier.
	 * @return array
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( ! isset( $cart_item['dc25_gv_amount'] ) ) {
			return $item_data;
		}

		// Montant
		$item_data[] = [
			'name'    => __( 'Montant', 'dc25-vouchers' ),
			'display' => number_format_i18n( $cart_item['dc25_gv_amount'], 2 ) . ' CHF',
		];

		// Message
		if ( ! empty( $cart_item['dc25_gv_message'] ) ) {
			$item_data[] = [
				'name'    => __( 'Message', 'dc25-vouchers' ),
				'display' => wp_kses_post( $cart_item['dc25_gv_message'] ),
			];
		}

		// Destinataire
		if ( ! empty( $cart_item['dc25_gv_recipient_name'] ) ) {
			$item_data[] = [
				'name'    => __( 'Destinataire', 'dc25-vouchers' ),
				'display' => esc_html( $cart_item['dc25_gv_recipient_name'] ),
			];
		}

		if ( ! empty( $cart_item['dc25_gv_recipient_email'] ) ) {
			$item_data[] = [
				'name'    => __( 'Email destinataire', 'dc25-vouchers' ),
				'display' => esc_html( $cart_item['dc25_gv_recipient_email'] ),
			];
		}

		// Envoi physique
		if ( ! empty( $cart_item['dc25_gv_physical'] ) ) {
			$item_data[] = [
				'name'    => __( 'Envoi physique', 'dc25-vouchers' ),
				'display' => __( 'Oui', 'dc25-vouchers' ),
			];
		}

		return $item_data;
	}

	/**
	 * Enqueue scripts pour la page produit
	 */
	public function enqueue_product_page_scripts(): void {
		// Debug: vérifier si on est sur une page produit
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		// Debug: vérifier le type de produit
		if ( 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();
		// Utiliser le prix officiel du produit comme valeur par défaut
		$default_amount = $product->get_price();
		// Si le prix est vide ou 0, utiliser le montant par défaut configuré
		if ( empty( $default_amount ) || $default_amount <= 0 ) {
			$default_amount = $product->get_default_amount();
		}

		// Vérifier que le fichier existe
		$script_path = DC25_PATH . 'assets/js/gift-voucher-product.js';
		if ( ! file_exists( $script_path ) ) {
			return;
		}

		// Enqueue le script JavaScript
		$script_url = DC25_URL . 'assets/js/gift-voucher-product.js';
		$script_version = filemtime( $script_path );

		// Enqueue le script avec dépendance jQuery pour être sûr qu'il se charge
		$enqueued = wp_enqueue_script(
			'dc25-gift-voucher-product',
			$script_url,
			[ 'jquery' ], // Ajouter jQuery comme dépendance pour être sûr que le script se charge
			$script_version,
			true // Dans le footer
		);

		// Toujours définir les données dans le footer au cas où le script se charge manuellement
		add_action( 'wp_footer', function() use ( $min_amount, $max_amount, $default_amount ) {
			if ( ! is_product() ) {
				return;
			}
			global $product;
			if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
				return;
			}
			?>
			<script type="text/javascript">
			// Définir les données au cas où le script se charge manuellement
			if (typeof window.dc25GiftVoucher === 'undefined') {
				window.dc25GiftVoucher = {
					minAmount: <?php echo esc_js( $min_amount ); ?>,
					maxAmount: <?php echo esc_js( $max_amount ); ?>,
					defaultAmount: <?php echo esc_js( $default_amount ); ?>
				};
			}
			</script>
			<?php
		}, 998 );

		// Passer les données au JavaScript
		wp_localize_script(
			'dc25-gift-voucher-product',
			'dc25GiftVoucher',
			[
				'minAmount'    => $min_amount,
				'maxAmount'    => $max_amount,
				'defaultAmount' => $default_amount,
			]
		);

		// Script inline de vérification immédiate (avant le script principal)
		$check_script = "
		console.log('DC25 Gift Voucher: Enqueue check - Script should be loading...');
		";
		wp_add_inline_script( 'dc25-gift-voucher-product', $check_script, 'before' );

		// Script inline de fallback pour vérifier le chargement
		$fallback_script = "
		(function() {
			setTimeout(function() {
				// Vérifier que le script principal s'est chargé
				if (typeof dc25GiftVoucher === 'undefined') {
					console.error('DC25 Gift Voucher: Script not loaded. Check if the file exists and is enqueued correctly.');
					console.error('DC25 Gift Voucher: Expected script handle: dc25-gift-voucher-product');
				} else {
					console.log('DC25 Gift Voucher: Script loaded successfully', dc25GiftVoucher);
				}
			}, 100);
		})();
		";
		wp_add_inline_script( 'dc25-gift-voucher-product', $fallback_script, 'after' );
	}


	/**
	 * Ajouter les styles CSS pour la page produit
	 */
	public function add_product_page_styles(): void {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		?>
		<style type="text/css">
			/* Style pour le bouton désactivé */
			.single_add_to_cart_button.disabled,
			.single_add_to_cart_button:disabled {
				opacity: 0.5 !important;
				cursor: not-allowed !important;
				pointer-events: none;
			}

			/* Style pour le champ montant avec erreur */
			#dc25_gv_amount.error {
				border-color: #dc3232 !important;
				box-shadow: 0 0 2px rgba(220, 50, 50, 0.8) !important;
			}

			/* Message d'erreur */
			.error-message {
				color: #dc3232;
				font-size: 0.9em;
				margin-top: 5px;
				display: block;
			}

			/* Améliorer la visibilité du champ montant */
			#dc25_gv_amount {
				font-size: 1.1em;
				font-weight: 600;
				padding: 10px;
			}
		</style>
		<?php
	}
}

