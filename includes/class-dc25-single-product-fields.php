<?php
/**
 * Gestion des champs personnalisés sur la page produit
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour les champs de la page produit
 */
class DC25_Single_Product_Fields {

	/**
	 * Constructeur
	 */
	public function __construct() {
		// Ajouter les champs DANS le formulaire d'ajout au panier (priorité 5 pour être avant le bouton)
		// C'est important que les champs soient dans le formulaire pour être envoyés dans POST
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_product_page_fields' ], 5 );

		// Valider les champs avant l'ajout au panier
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_product_page_fields' ], 10, 3 );

		// Sauvegarder les données dans le panier
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );

		// Afficher les données dans le panier
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 10, 2 );

		// Sauvegarder les données dans la commande lors du checkout
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item_meta' ], 10, 4 );

		// Appliquer le prix personnalisé dans le panier (hook standard WooCommerce)
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_custom_price' ], 10, 1 );

		// Ajouter le supplément de 5 CHF pour l'envoi physique
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_physical_shipping_fee' ] );

		// Formater l'affichage des métadonnées dans les commandes
		add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'format_order_item_meta_key' ], 10, 1 );
		add_filter( 'woocommerce_order_item_display_meta_value', [ $this, 'format_order_item_meta_value' ], 10, 3 );
		
		// S'assurer que nos métadonnées sont visibles (elles commencent par _ donc masquées par défaut)
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'show_voucher_meta' ], 10, 1 );

		// Scripts et styles pour la page produit
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_product_page_scripts' ], 20 );
		add_action( 'wp_head', [ $this, 'add_product_page_styles' ] );
	}

	/**
	 * Ajouter les champs sur la page produit
	 * 
	 * @param bool $force Forcer l'affichage même si déjà affiché (pour appel direct).
	 */
	public function add_product_page_fields(): void {
		// Éviter les doublons
		static $fields_added = false;
		if ( $fields_added ) {
			return;
		}

		// Récupérer le produit de manière fiable
		global $product;
		
		// Si $product n'est pas disponible dans le global, le récupérer
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			$product_id = get_the_ID();
			if ( ! $product_id ) {
				return;
			}
			$product = wc_get_product( $product_id );
		}
		
		// Vérifier que c'est bien un produit gift_voucher
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}
		
		// Marquer comme ajouté pour éviter les doublons
		$fields_added = true;

		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();
		// Utiliser le montant par défaut configuré dans le produit
		$default_amount = $product->get_default_amount();
		// Si le montant par défaut n'est pas défini ou invalide, utiliser le minimum
		if ( empty( $default_amount ) || $default_amount <= 0 ) {
			$default_amount = $min_amount;
		}
		$physical_enabled = $product->is_physical_enabled();

		?>
		<!-- DC25 Gift Voucher Fields - Product Type: <?php echo esc_attr( $product->get_type() ); ?> - Product ID: <?php echo esc_attr( $product->get_id() ); ?> -->
		<div class="dc25-gift-voucher-fields">
			
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
						} else {
							console.error('❌ Add to cart button NOT found in DOM');
						}
					}, 200);
				});
				
				// Vérifier si le script externe se chargera
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

				// Compteur de caractères en temps réel pour le message (120 max)
				document.addEventListener('DOMContentLoaded', function() {
					var textarea = document.getElementById('dc25_gv_message');
					var counter = document.getElementById('dc25_gv_message_counter');
					if (!textarea || !counter) {
						return;
					}
					var max = 120;
					var updateCount = function() {
						var val = textarea.value || '';
						if (val.length > max) {
							textarea.value = val.substring(0, max);
						}
						counter.textContent = textarea.value.length + ' / ' + max + ' ' + '<?php echo esc_js( __( 'caractères', 'dc25-vouchers' ) ); ?>';
					};
					textarea.addEventListener('input', updateCount);
					updateCount();
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
					maxlength="120"
					placeholder="<?php esc_attr_e( 'Votre message pour le destinataire...', 'dc25-vouchers' ); ?>"
				></textarea>
				<small id="dc25_gv_message_counter"><?php esc_html_e( '0 / 120 caractères', 'dc25-vouchers' ); ?></small>
			</p>

			<p class="form-row form-row-wide">
				<label for="dc25_gv_from_name">
					<?php esc_html_e( 'De la part de', 'dc25-vouchers' ); ?>
					<span class="required">*</span>
				</label>
				<input 
					type="text" 
					id="dc25_gv_from_name" 
					name="dc25_gv_from_name" 
					class="input-text" 
					required
					placeholder="<?php esc_attr_e( 'Nom de l\'offrant', 'dc25-vouchers' ); ?>"
				/>
			</p>

			<p class="form-row form-row-wide">
				<label for="dc25_gv_recipient_name">
					<?php esc_html_e( 'Nom du destinataire', 'dc25-vouchers' ); ?>
					<span class="required">*</span>
				</label>
				<input 
					type="text" 
					id="dc25_gv_recipient_name" 
					name="dc25_gv_recipient_name" 
					class="input-text" 
					required
					minlength="2"
					placeholder="<?php esc_attr_e( 'Nom du destinataire', 'dc25-vouchers' ); ?>"
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
						<strong> (+ 5 CHF)</strong>
					</label>
				</p>
			<?php endif; ?>
		</div><!-- .dc25-gift-voucher-fields -->
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
		// Vérifier si le champ est présent dans POST
		if ( ! isset( $_POST['dc25_gv_amount'] ) || empty( $_POST['dc25_gv_amount'] ) ) {
			wc_add_notice( __( 'Veuillez saisir un montant pour le bon cadeau.', 'dc25-vouchers' ), 'error' );
			return false;
		}

		$amount = floatval( sanitize_text_field( $_POST['dc25_gv_amount'] ) );
		
		// Vérifier que le montant est valide (supérieur à 0)
		if ( $amount <= 0 ) {
			wc_add_notice( __( 'Le montant doit être supérieur à 0.', 'dc25-vouchers' ), 'error' );
			return false;
		}
		
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

		// Validation du champ "De la part de"
		if ( empty( $_POST['dc25_gv_from_name'] ) ) {
			wc_add_notice( __( 'Veuillez saisir le nom de l\'offrant.', 'dc25-vouchers' ), 'error' );
			return false;
		}
		$from_name = sanitize_text_field( wp_unslash( $_POST['dc25_gv_from_name'] ) );
		if ( strlen( $from_name ) < 2 ) {
			wc_add_notice( __( 'Le nom de l\'offrant doit contenir au moins 2 caractères.', 'dc25-vouchers' ), 'error' );
			return false;
		}

		// Validation du champ "Destinataire" (obligatoire)
		if ( empty( $_POST['dc25_gv_recipient_name'] ) ) {
			wc_add_notice( __( 'Veuillez saisir le nom du destinataire.', 'dc25-vouchers' ), 'error' );
			return false;
		}
		$recipient_name = sanitize_text_field( wp_unslash( $_POST['dc25_gv_recipient_name'] ) );
		if ( strlen( $recipient_name ) < 2 ) {
			wc_add_notice( __( 'Le nom du destinataire doit contenir au moins 2 caractères.', 'dc25-vouchers' ), 'error' );
			return false;
		}

		// Limiter le message à 120 caractères
		if ( isset( $_POST['dc25_gv_message'] ) ) {
			$message = trim( wp_unslash( $_POST['dc25_gv_message'] ) );
			if ( strlen( $message ) > 120 ) {
				wc_add_notice( __( 'Le message ne peut pas dépasser 120 caractères.', 'dc25-vouchers' ), 'error' );
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

		// De la part de
		if ( isset( $_POST['dc25_gv_from_name'] ) ) {
			$cart_item_data['dc25_gv_from_name'] = sanitize_text_field( wp_unslash( $_POST['dc25_gv_from_name'] ) );
		}

		// Message
		if ( isset( $_POST['dc25_gv_message'] ) ) {
			$msg = sanitize_textarea_field( wp_unslash( $_POST['dc25_gv_message'] ) );
			if ( strlen( $msg ) > 120 ) {
				$msg = substr( $msg, 0, 120 );
			}
			$cart_item_data['dc25_gv_message'] = $msg;
		}

		// Destinataire
		if ( isset( $_POST['dc25_gv_recipient_name'] ) ) {
			$cart_item_data['dc25_gv_recipient_name'] = sanitize_text_field( wp_unslash( $_POST['dc25_gv_recipient_name'] ) );
		}

		// Envoi physique
		if ( isset( $_POST['dc25_gv_physical'] ) ) {
			$cart_item_data['dc25_gv_physical'] = 'yes';
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

		// De la part de
		if ( ! empty( $cart_item['dc25_gv_from_name'] ) ) {
			$item_data[] = [
				'name'    => __( 'De la part de', 'dc25-vouchers' ),
				'display' => esc_html( $cart_item['dc25_gv_from_name'] ),
			];
		}

		// Destinataire
		if ( ! empty( $cart_item['dc25_gv_recipient_name'] ) ) {
			$item_data[] = [
				'name'    => __( 'Destinataire', 'dc25-vouchers' ),
				'display' => esc_html( $cart_item['dc25_gv_recipient_name'] ),
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
	 * Sauvegarder les données personnalisées dans la commande
	 *
	 * @param WC_Order_Item_Product $item Item de commande.
	 * @param string                $cart_item_key Clé de l'item dans le panier.
	 * @param array                 $values Valeurs de l'item.
	 * @param WC_Order              $order Commande.
	 */
	public function save_order_item_meta( $item, string $cart_item_key, array $values, $order ): void {
		if ( ! isset( $values['dc25_gv_amount'] ) ) {
			return;
		}

		// Sauvegarder toutes les données personnalisées dans les meta de l'item
		if ( isset( $values['dc25_gv_amount'] ) ) {
			$item->update_meta_data( '_dc25_gv_amount', floatval( $values['dc25_gv_amount'] ) );
		}

		if ( isset( $values['dc25_gv_from_name'] ) ) {
			$item->update_meta_data( '_dc25_gv_from_name', sanitize_text_field( $values['dc25_gv_from_name'] ) );
		}

		if ( isset( $values['dc25_gv_message'] ) ) {
			$item->update_meta_data( '_dc25_gv_message', sanitize_textarea_field( $values['dc25_gv_message'] ) );
		}

		if ( isset( $values['dc25_gv_recipient_name'] ) ) {
			$item->update_meta_data( '_dc25_gv_recipient_name', sanitize_text_field( $values['dc25_gv_recipient_name'] ) );
		}

		if ( isset( $values['dc25_gv_physical'] ) ) {
			$item->update_meta_data( '_dc25_gv_physical', sanitize_text_field( $values['dc25_gv_physical'] ) );
		}
	}

	/**
	 * Appliquer le prix personnalisé dans le panier
	 *
	 * @param WC_Cart $cart Panier.
	 */
	public function apply_custom_price( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['dc25_gv_amount'] ) ) {
				$cart_item['data']->set_price( floatval( $cart_item['dc25_gv_amount'] ) );
			}
		}
	}

	/**
	 * Ajouter le supplément de 5 CHF pour l'envoi physique
	 *
	 * @param WC_Cart $cart Panier.
	 */
	public function add_physical_shipping_fee( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Vérifier si le panier contient un bon cadeau avec envoi physique
		$has_physical = false;
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['dc25_gv_physical'] ) && 'yes' === $cart_item['dc25_gv_physical'] ) {
				$has_physical = true;
				break;
			}
		}

		if ( $has_physical ) {
			$cart->add_fee(
				__( 'Envoi physique du bon cadeau', 'dc25-vouchers' ),
				5.00
			);
		}
	}

	/**
	 * Afficher les métadonnées des bons cadeaux (normalement masquées car elles commencent par _)
	 *
	 * @param array $hidden_meta Liste des meta masquées.
	 * @return array
	 */
	public function show_voucher_meta( array $hidden_meta ): array {
		// Retirer nos métadonnées de la liste des meta masquées
		$voucher_meta = [
			'_dc25_gv_amount',
			'_dc25_gv_message',
			'_dc25_gv_recipient_name',
			'_dc25_gv_physical',
			'_dc25_gv_coupon_code',
		];
		
		return array_diff( $hidden_meta, $voucher_meta );
	}

	/**
	 * Formater les clés de métadonnées pour l'affichage dans les commandes
	 *
	 * @param string $display_key Clé d'affichage.
	 * @return string
	 */
	public function format_order_item_meta_key( string $display_key ): string {
		$labels = [
			'_dc25_gv_amount'         => __( 'Montant', 'dc25-vouchers' ),
			'_dc25_gv_message'        => __( 'Message', 'dc25-vouchers' ),
			'_dc25_gv_recipient_name' => __( 'Destinataire', 'dc25-vouchers' ),
			'_dc25_gv_physical'       => __( 'Envoi physique', 'dc25-vouchers' ),
			'_dc25_gv_coupon_code'    => __( 'Code coupon', 'dc25-vouchers' ),
		];

		return $labels[ $display_key ] ?? $display_key;
	}

	/**
	 * Formater les valeurs de métadonnées pour l'affichage dans les commandes
	 *
	 * @param string      $display_value Valeur d'affichage.
	 * @param object|null $meta Meta object.
	 * @param object      $order_item Item de commande.
	 * @return string
	 */
	public function format_order_item_meta_value( string $display_value, $meta, $order_item ): string {
		if ( ! $meta || ! isset( $meta->key ) ) {
			return $display_value;
		}

		switch ( $meta->key ) {
			case '_dc25_gv_amount':
				return wc_price( floatval( $display_value ) );

			case '_dc25_gv_physical':
				return 'yes' === $display_value ? __( 'Oui (+ 5 CHF)', 'dc25-vouchers' ) : __( 'Non', 'dc25-vouchers' );

			case '_dc25_gv_message':
				// Limiter la longueur du message pour l'affichage
				if ( strlen( $display_value ) > 100 ) {
					return esc_html( substr( $display_value, 0, 100 ) ) . '...';
				}
				return esc_html( $display_value );

			default:
				return $display_value;
		}
	}

	/**
	 * Enqueue scripts pour la page produit
	 */
	public function enqueue_product_page_scripts(): void {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		
		// Vérifier que $product est bien un objet WC_Product
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			$product_id = get_the_ID();
			if ( ! $product_id ) {
				return;
			}
			$product = wc_get_product( $product_id );
		}
		
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();
		// Utiliser le montant par défaut configuré dans le produit
		$default_amount = $product->get_default_amount();
		// Si le montant par défaut n'est pas défini ou invalide, utiliser le minimum
		if ( empty( $default_amount ) || $default_amount <= 0 ) {
			$default_amount = $min_amount;
		}

		// Vérifier que le fichier existe
		$script_path = DC25_PATH . 'assets/js/gift-voucher-product.js';
		if ( ! file_exists( $script_path ) ) {
			return;
		}

		// Enqueue le script JavaScript
		$script_url = DC25_URL . 'assets/js/gift-voucher-product.js';
		$script_version = filemtime( $script_path );

		wp_enqueue_script(
			'dc25-gift-voucher-product',
			$script_url,
			[ 'jquery' ],
			$script_version,
			true
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
			.single_add_to_cart_button.disabled,
			.single_add_to_cart_button:disabled {
				opacity: 0.5 !important;
				cursor: not-allowed !important;
				pointer-events: none;
			}

			#dc25_gv_amount.error {
				border-color: #dc3232 !important;
				box-shadow: 0 0 2px rgba(220, 50, 50, 0.8) !important;
			}

			.error-message {
				color: #dc3232;
				font-size: 0.9em;
				margin-top: 5px;
				display: block;
			}

			#dc25_gv_amount {
				font-size: 1.1em;
				font-weight: 600;
				padding: 10px;
			}
		</style>
		<?php
	}
}

