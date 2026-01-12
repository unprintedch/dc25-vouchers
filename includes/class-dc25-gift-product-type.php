<?php
/**
 * Gestion du type de produit gift_voucher
 * Version conforme à la documentation officielle WooCommerce
 *
 * @package DC25_Vouchers
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe produit WooCommerce pour gift_voucher
 * Étend WC_Product directement (méthode officielle)
 */
if ( class_exists( 'WC_Product' ) && ! class_exists( 'WC_Product_Gift_Voucher' ) ) {
	class WC_Product_Gift_Voucher extends WC_Product {

		/**
		 * Type de produit
		 *
		 * @var string
		 */
		protected $product_type = 'gift_voucher';

		/**
		 * Constructeur
		 *
		 * @param mixed $product Produit.
		 */
		public function __construct( $product = 0 ) {
			$this->product_type = 'gift_voucher';
			parent::__construct( $product );
			
			// Définir un prix par défaut (montant minimum) pour permettre l'ajout au panier
			// Le prix sera remplacé par le montant choisi par le client
			if ( $this->get_id() > 0 ) {
				$current_price = $this->get_price();
				if ( empty( $current_price ) || $current_price <= 0 ) {
					$min_amount = $this->get_min_amount();
					// Définir à la fois regular_price et price
					$this->set_regular_price( $min_amount );
					$this->set_price( $min_amount );
					$this->set_sale_price( '' ); // Pas de prix de vente
				}
			}
		}
		
		/**
		 * Le produit est toujours achetable (même sans prix)
		 *
		 * @return bool
		 */
		public function is_purchasable(): bool {
			return true;
		}

		/**
		 * Obtenir le montant minimum
		 *
		 * @return float
		 */
		public function get_min_amount(): float {
			return (float) $this->get_meta( '_dc25_gv_min_amount' ) ?: 20.0;
		}

		/**
		 * Obtenir le montant maximum
		 *
		 * @return float
		 */
		public function get_max_amount(): float {
			return (float) $this->get_meta( '_dc25_gv_max_amount' ) ?: 200.0;
		}

		/**
		 * Obtenir le montant par défaut
		 *
		 * @return float
		 */
		public function get_default_amount(): float {
			return (float) $this->get_meta( '_dc25_gv_default_amount' ) ?: 20.0;
		}

		/**
		 * Obtenir la durée de validité en jours
		 *
		 * @return int
		 */
		public function get_validity_days(): int {
			return absint( $this->get_meta( '_dc25_gv_validity_days' ) ) ?: 365;
		}

		/**
		 * Obtenir le préfixe coupon
		 *
		 * @return string
		 */
		public function get_coupon_prefix(): string {
			return $this->get_meta( '_dc25_gv_coupon_prefix' ) ?: 'NVT-';
		}

		/**
		 * Vérifier si l'envoi physique est activé
		 *
		 * @return bool
		 */
		public function is_physical_enabled(): bool {
			return 'yes' === $this->get_meta( '_dc25_gv_physical_enabled' );
		}

		/**
		 * Obtenir le taux de TVA
		 *
		 * @return float
		 */
		public function get_tax_rate(): float {
			return (float) $this->get_meta( '_dc25_gv_tax_rate' ) ?: 0.0;
		}
	}
}

/**
 * Classe pour le type de produit gift_voucher
 */
class DC25_Gift_Product_Type {

	/**
	 * Constructeur
	 */
	public function __construct() {
		// S'assurer que WC_Product existe
		if ( ! class_exists( 'WC_Product' ) ) {
			return;
		}

		// 1. Ajouter le type dans le sélecteur
		add_filter( 'product_type_selector', [ $this, 'add_product_type' ], 10, 1 );

		// 2. Mapper la classe produit
		add_filter( 'woocommerce_product_class', [ $this, 'product_class' ], 10, 2 );

		// 3. Sauvegarder le type - utiliser les deux hooks pour être sûr
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_type' ], 20 );
		add_action( 'save_post_product', [ $this, 'save_product_type' ], 99 ); // Priorité basse pour s'exécuter après WooCommerce

		// 4. Onglet Prix personnalisé
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_price_tab' ], 10, 1 );
		add_action( 'woocommerce_product_data_panels', [ $this, 'add_price_tab_content' ] );

		// 5. Masquer les champs de prix et TVA standard pour gift_voucher (priorité haute pour s'exécuter en premier)
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'hide_standard_price_fields' ], 5 );

		// 6. Champs admin dans l'onglet Général
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_product_fields' ], 20 );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_fields' ] );

		// 7. CSS pour masquer les onglets et champs standard dès le chargement (dans le head)
		add_action( 'admin_head', [ $this, 'admin_css' ] );

		// 8. Script pour gérer l'affichage des onglets sans flash
		add_action( 'admin_footer', [ $this, 'admin_script' ] );

		// 8bis. Rendre visible le champ "Sold individually" pour gift_voucher
		add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'show_sold_individually_field' ], 99 );

		// 9. Permettre l'ajout au panier même sans prix (pour prix libre)
		add_filter( 'woocommerce_is_purchasable', [ $this, 'make_purchasable' ], 10, 2 );
		
		// 10. Définir le prix par défaut lors de la sauvegarde
		add_action( 'woocommerce_process_product_meta', [ $this, 'set_default_price' ], 30 );

		// 11. Afficher le prix dynamique sur la page produit
		// add_filter( 'woocommerce_get_price_html', [ $this, 'display_dynamic_price' ], 10, 2 );
		
		// 11bis. Afficher toujours la fourchette min/max pour les bons cadeaux (front + blocs)
		add_filter( 'woocommerce_get_price_html', [ $this, 'display_price_range_everywhere' ], 9, 2 );
		
		// 12. Forcer l'affichage du formulaire d'ajout au panier
		add_action( 'woocommerce_single_product_summary', [ $this, 'force_add_to_cart_form' ], 29 );
	}

	/**
	 * Ajouter le type gift_voucher au sélecteur
	 *
	 * @param array $types Types de produits existants.
	 * @return array
	 */
	public function add_product_type( array $types ): array {
		$types['gift_voucher'] = __( 'Bon cadeau', 'dc25-vouchers' );
		return $types;
	}

	/**
	 * Mapper la classe produit
	 *
	 * @param string $class_name Nom de la classe.
	 * @param string $product_type Type de produit.
	 * @return string
	 */
	public function product_class( string $class_name, string $product_type ): string {
		if ( 'gift_voucher' === $product_type ) {
			return 'WC_Product_Gift_Voucher';
		}
		return $class_name;
	}

	/**
	 * Sauvegarder le type de produit
	 * Méthode conforme aux exemples officiels WooCommerce
	 *
	 * @param int $post_id ID du produit.
	 */
	public function save_product_type( int $post_id ): void {
		// Éviter les sauvegardes automatiques
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Vérifier les permissions
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		// Obtenir le produit
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		// Vérifier le type dans $_POST ou utiliser le type actuel
		$product_type = '';
		if ( isset( $_POST['product-type'] ) ) {
			$product_type = sanitize_text_field( $_POST['product-type'] );
		} else {
			// Si pas dans $_POST, vérifier le type actuel
			$product_type = $product->get_type();
		}

		// Sauvegarder le type dans la taxonomie (méthode standard WooCommerce)
		if ( 'gift_voucher' === $product_type ) {
			// S'assurer que le terme existe
			if ( ! term_exists( 'gift_voucher', 'product_type' ) ) {
				wp_insert_term( 'gift_voucher', 'product_type' );
			}
			
			// Sauvegarder dans la taxonomie (remplacer les autres types)
			wp_set_object_terms( $post_id, 'gift_voucher', 'product_type', false );
			
			// Nettoyer les caches
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'posts' );
		}
	}

	/**
	 * Ajouter l'onglet Prix personnalisé
	 *
	 * @param array $tabs Onglets existants.
	 * @return array
	 */
	public function add_price_tab( array $tabs ): array {
		$tabs['dc25_gift_voucher_price'] = [
			'label'    => __( 'Prix', 'dc25-vouchers' ),
			'target'   => 'dc25_gift_voucher_price_data',
			'class'    => [ 'show_if_gift_voucher', 'dc25_gift_voucher_price_tab' ],
			'priority' => 11, // Après l'onglet Général (10)
		];
		return $tabs;
	}

	/**
	 * Contenu de l'onglet Prix
	 */
	public function add_price_tab_content(): void {
		global $post;

		$product = null;
		if ( isset( $post->ID ) && $post->ID > 0 ) {
			$product = wc_get_product( $post->ID );
		}
		?>
		<div id="dc25_gift_voucher_price_data" class="panel woocommerce_options_panel" style="display: none;">
			<div class="options_group">
				<div class="dc25-price-info" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 15px; margin: 0 0 20px 0; border-radius: 4px;">
					<p style="margin: 0; font-size: 14px; line-height: 1.6;">
						<strong style="color: #2271b1;"><?php esc_html_e( '💡 Prix libre défini par l\'utilisateur/trice', 'dc25-vouchers' ); ?></strong><br>
						<?php esc_html_e( 'Pour ce bon cadeau, le prix est défini librement par votre client lors de la commande. Définissez ci-dessous les limites minimum et maximum autorisées.', 'dc25-vouchers' ); ?>
					</p>
				</div>

				<?php
				// Montant minimum
				woocommerce_wp_text_input( [
					'id'                => '_dc25_gv_min_amount',
					'label'             => __( 'Montant minimum (CHF)', 'dc25-vouchers' ),
					'placeholder'       => '20',
					'description'       => __( 'Montant minimum que le client peut choisir pour ce bon cadeau.', 'dc25-vouchers' ),
					'type'              => 'number',
					'custom_attributes' => [
						'step' => '0.01',
						'min'  => '0',
					],
					'value'             => $product ? ( $product->get_meta( '_dc25_gv_min_amount' ) ?: '20' ) : '20',
				] );

				// Montant maximum
				woocommerce_wp_text_input( [
					'id'                => '_dc25_gv_max_amount',
					'label'             => __( 'Montant maximum (CHF)', 'dc25-vouchers' ),
					'placeholder'       => '200',
					'description'       => __( 'Montant maximum que le client peut choisir pour ce bon cadeau.', 'dc25-vouchers' ),
					'type'              => 'number',
					'custom_attributes' => [
						'step' => '0.01',
						'min'  => '0',
					],
					'value'             => $product ? ( $product->get_meta( '_dc25_gv_max_amount' ) ?: '200' ) : '200',
				] );

				// Montant par défaut
				woocommerce_wp_text_input( [
					'id'                => '_dc25_gv_default_amount',
					'label'             => __( 'Montant par défaut (CHF)', 'dc25-vouchers' ),
					'placeholder'       => '20',
					'description'       => __( 'Montant suggéré dans le formulaire de commande. Le client peut le modifier.', 'dc25-vouchers' ),
					'type'              => 'number',
					'custom_attributes' => [
						'step' => '0.01',
						'min'  => '0',
					],
					'value'             => $product ? ( $product->get_meta( '_dc25_gv_default_amount' ) ?: '20' ) : '20',
				] );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Masquer les champs de prix et TVA standard pour gift_voucher
	 */
	public function hide_standard_price_fields(): void {
		global $post;

		$product = wc_get_product( $post->ID );
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		// Masquer les champs de prix et TVA standard via CSS
		?>
		<style type="text/css">
		/* Masquer les champs de prix standard pour gift_voucher */
		#general_product_data ._regular_price_field,
		#general_product_data ._sale_price_field,
		#general_product_data ._tax_status_field,
		#general_product_data ._tax_class_field {
			display: none !important;
		}
		</style>
		<?php
	}

	/**
	 * Ajouter les champs admin dans l'onglet Général
	 */
	public function add_product_fields(): void {
		global $post;

		$product = wc_get_product( $post->ID );
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		echo '<div class="options_group show_if_gift_voucher">';

		// Durée de validité
		woocommerce_wp_text_input( [
			'id'                => '_dc25_gv_validity_days',
			'label'             => __( 'Durée de validité (jours)', 'dc25-vouchers' ),
			'placeholder'       => '365',
			'description'       => __( 'Nombre de jours avant expiration du bon.', 'dc25-vouchers' ),
			'type'              => 'number',
			'custom_attributes' => [
				'step' => '1',
				'min'  => '1',
			],
			'value'             => $product->get_meta( '_dc25_gv_validity_days' ) ?: '365',
		] );

		// Préfixe coupon
		woocommerce_wp_text_input( [
			'id'          => '_dc25_gv_coupon_prefix',
			'label'       => __( 'Préfixe coupon', 'dc25-vouchers' ),
			'placeholder' => 'NVT-',
			'description' => __( 'Préfixe pour les codes de coupon générés.', 'dc25-vouchers' ),
			'value'       => $product->get_meta( '_dc25_gv_coupon_prefix' ) ?: 'NVT-',
		] );

		// Option envoi physique
		woocommerce_wp_checkbox( [
			'id'          => '_dc25_gv_physical_enabled',
			'label'       => __( 'Activer l\'envoi physique', 'dc25-vouchers' ),
			'description' => __( 'Permet aux clients de choisir un envoi physique du bon.', 'dc25-vouchers' ),
			'value'       => $product->get_meta( '_dc25_gv_physical_enabled' ) ? 'yes' : 'no',
		] );

		// Option TVA
		woocommerce_wp_text_input( [
			'id'                => '_dc25_gv_tax_rate',
			'label'             => __( 'Taux de TVA (%)', 'dc25-vouchers' ),
			'placeholder'       => '0',
			'description'       => __( 'Taux de TVA appliqué (0% par défaut).', 'dc25-vouchers' ),
			'type'              => 'number',
			'custom_attributes' => [
				'step' => '0.1',
				'min'  => '0',
				'max'  => '100',
			],
			'value'             => $product->get_meta( '_dc25_gv_tax_rate' ) ?: '0',
		] );

		echo '</div>';
	}

	/**
	 * Sauvegarder les champs admin
	 *
	 * @param int $post_id ID du produit.
	 */
	public function save_product_fields( int $post_id ): void {
		$product_type = isset( $_POST['product-type'] ) ? sanitize_text_field( $_POST['product-type'] ) : '';

		if ( 'gift_voucher' !== $product_type ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		// Montant minimum
		if ( isset( $_POST['_dc25_gv_min_amount'] ) ) {
			$product->update_meta_data( '_dc25_gv_min_amount', sanitize_text_field( $_POST['_dc25_gv_min_amount'] ) );
		}

		// Montant maximum
		if ( isset( $_POST['_dc25_gv_max_amount'] ) ) {
			$product->update_meta_data( '_dc25_gv_max_amount', sanitize_text_field( $_POST['_dc25_gv_max_amount'] ) );
		}

		// Montant par défaut
		if ( isset( $_POST['_dc25_gv_default_amount'] ) ) {
			$product->update_meta_data( '_dc25_gv_default_amount', sanitize_text_field( $_POST['_dc25_gv_default_amount'] ) );
		}

		// Durée de validité
		if ( isset( $_POST['_dc25_gv_validity_days'] ) ) {
			$product->update_meta_data( '_dc25_gv_validity_days', absint( $_POST['_dc25_gv_validity_days'] ) );
		}

		// Préfixe coupon
		if ( isset( $_POST['_dc25_gv_coupon_prefix'] ) ) {
			$product->update_meta_data( '_dc25_gv_coupon_prefix', sanitize_text_field( $_POST['_dc25_gv_coupon_prefix'] ) );
		}

		// Envoi physique
		$product->update_meta_data( '_dc25_gv_physical_enabled', isset( $_POST['_dc25_gv_physical_enabled'] ) ? 'yes' : 'no' );

		// Taux TVA
		if ( isset( $_POST['_dc25_gv_tax_rate'] ) ) {
			$product->update_meta_data( '_dc25_gv_tax_rate', floatval( $_POST['_dc25_gv_tax_rate'] ) );
		}

		// Sauvegarder "Sold individually" si présent
		if ( isset( $_POST['_sold_individually'] ) ) {
			$product->update_meta_data( '_sold_individually', 'yes' );
		} else {
			$product->update_meta_data( '_sold_individually', 'no' );
		}

		$product->save();
	}

	/**
	 * Rendre le produit achetable même sans prix (pour prix libre)
	 *
	 * @param bool       $purchasable Est achetable.
	 * @param WC_Product $product Produit.
	 * @return bool
	 */
	public function make_purchasable( bool $purchasable, $product ): bool {
		if ( $product && 'gift_voucher' === $product->get_type() ) {
			return true; // Toujours achetable pour les bons cadeaux
		}
		return $purchasable;
	}

	/**
	 * Définir le prix par défaut lors de la sauvegarde
	 *
	 * @param int $post_id ID du produit.
	 */
	public function set_default_price( int $post_id ): void {
		$product = wc_get_product( $post_id );
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}

		// Si le prix est vide ou 0, définir le montant minimum comme prix par défaut
		$current_price = $product->get_price();
		if ( empty( $current_price ) || $current_price <= 0 ) {
			$min_amount = $product->get_min_amount();
			$product->set_regular_price( $min_amount );
			$product->set_price( $min_amount );
			$product->save();
		}
	}

	/**
	 * Afficher le prix dynamique sur la page produit
	 *
	 * @param string      $price Prix HTML.
	 * @param WC_Product  $product Produit.
	 * @return string
	 */
	public function display_dynamic_price( string $price, $product ): string {
		// Ne pas modifier le prix en admin
		if ( is_admin() ) {
			return $price;
		}

		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return $price;
		}

		// Utiliser le prix officiel du produit comme valeur par défaut
		$default_amount = $product->get_price();
		// Si le prix est vide ou 0, utiliser le montant par défaut configuré
		if ( empty( $default_amount ) || $default_amount <= 0 ) {
			$default_amount = $product->get_default_amount();
		}
		$min_amount = $product->get_min_amount();
		$max_amount = $product->get_max_amount();

		return sprintf(
			/* translators: 1: montant par défaut, 2: montant minimum, 3: montant maximum */
			'<p class="price">
				<span class="woocommerce-Price-amount amount dc25-dynamic-price" data-default="%s">%s</span>
				<span class="woocommerce-Price-currencySymbol"> CHF</span>
			</p>
			<small class="dc25-price-range" style="display: block; margin-top: 5px; color: #666;">(%s - %s CHF)</small>',
			esc_attr( $default_amount ),
			number_format_i18n( $default_amount, 2 ),
			number_format_i18n( $min_amount, 2 ),
			number_format_i18n( $max_amount, 2 )
		);
	}

	/**
	 * Afficher partout la plage de prix min/max pour les gift_voucher
	 *
	 * @param string     $price_html Prix HTML actuel.
	 * @param WC_Product $product    Produit courant.
	 * @return string
	 */
	public function display_price_range_everywhere( string $price_html, $product ): string {
		// Ne pas modifier l'admin (sauf AJAX) pour éviter de casser l'édition
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price_html;
		}

		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return $price_html;
		}

		$min = (float) $product->get_min_amount();
		$max = (float) $product->get_max_amount();

		// Si aucune valeur valable, garder le HTML par défaut
		if ( $min <= 0 && $max <= 0 ) {
			return $price_html;
		}

		// Normaliser si un seul montant est défini
		if ( $min <= 0 ) {
			$min = $max;
		}
		if ( $max <= 0 ) {
			$max = $min;
		}

		$formatted = ( $min === $max )
			? wc_price( $min )
			: sprintf( '%s – %s', wc_price( $min ), wc_price( $max ) );

		return sprintf( '<span class="price price-range">%s</span>', $formatted );
	}
	
	/**
	 * Forcer l'affichage du formulaire d'ajout au panier pour les bons cadeaux
	 * 
	 * @return void
	 */
	public function force_add_to_cart_form(): void {
		global $product;
		
		if ( ! $product || 'gift_voucher' !== $product->get_type() ) {
			return;
		}
		
		// Vérifier si le formulaire existe déjà dans le DOM
		$form_exists = did_action( 'woocommerce_template_single_add_to_cart' );
		
		// Si le formulaire n'existe pas, le créer manuellement
		if ( ! $form_exists ) {
			// S'assurer que le produit est purchasable
			$product->set_price( $product->get_min_amount() );
			$product->set_regular_price( $product->get_min_amount() );
			
			// Afficher le formulaire avec une structure HTML correcte
			?>
			<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
				<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
				
				<div class="single_variation_wrap">
					<div class="woocommerce-variation single_variation"></div>
					<div class="woocommerce-variation-add-to-cart variations_button">
						<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>">
							<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
						</button>
					</div>
				</div>
				
				<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
			</form>
			<?php
		}
	}

	/**
	 * Rendre visible le champ "Sold individually" pour gift_voucher
	 */
	public function show_sold_individually_field(): void {
		global $post;

		$product = null;
		$current_type = 'simple';
		if ( isset( $post->ID ) && $post->ID > 0 ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$current_type = $product->get_type();
			}
		}

		// Si c'est un gift_voucher, créer le champ manuellement s'il n'existe pas
		if ( 'gift_voucher' === $current_type && $product ) {
			$sold_individually = $product->get_meta( '_sold_individually' );
			?>
			<div class="options_group show_if_gift_voucher" style="display: block;">
				<?php
				woocommerce_wp_checkbox(
					[
						'id'            => '_sold_individually',
						'wrapper_class' => 'show_if_gift_voucher',
						'label'         => __( 'Vendre individuellement', 'woocommerce' ),
						'description'   => __( 'Limiter les achats à 1 article par commande', 'woocommerce' ),
						'value'         => $sold_individually ? 'yes' : 'no',
					]
				);
				?>
			</div>
			<?php
		}

		// Toujours ajouter le script pour gérer le changement de type de produit
		?>
		<script type="text/javascript">
		(function($) {
			function showSoldIndividuallyField() {
				var productType = $('#product-type').val() || '<?php echo esc_js( $current_type ); ?>';
				var field = $('#inventory_product_data ._sold_individually_field');
				
				console.log('DC25: Checking sold individually field, product type:', productType, 'field found:', field.length);
				
				if (field.length) {
					if (productType === 'gift_voucher') {
						// Ajouter la classe show_if_gift_voucher et retirer les classes qui masquent
						field
							.addClass('show_if_gift_voucher')
							.removeClass('hide_if_grouped hide_if_external hide_if_variable hide_if_variable-subscription')
							.css({
								'display': 'block',
								'visibility': 'visible',
								'opacity': '1'
							})
							.show();
						
						// Forcer aussi le parent form-field
						var parentField = field.closest('.form-field');
						if (parentField.length) {
							parentField
								.addClass('show_if_gift_voucher')
								.removeClass('hide_if_grouped hide_if_external hide_if_variable hide_if_variable-subscription')
								.css({
									'display': 'block',
									'visibility': 'visible'
								})
								.show();
						}
						
						console.log('DC25: Field shown for gift_voucher');
					}
				} else {
					console.log('DC25: Sold individually field not found in DOM');
				}
			}

			// Exécuter après le chargement du DOM
			$(document).ready(function() {
				// Attendre que WooCommerce ait initialisé
				setTimeout(function() {
					showSoldIndividuallyField();
					
					// Écouter les changements de type de produit
					$('#product-type').on('change', function() {
						setTimeout(showSoldIndividuallyField, 100);
					});
					
					// Écouter les événements WooCommerce
					$(document.body).on('woocommerce-product-type-change', function(e, productType) {
						console.log('DC25: Product type changed to:', productType);
						if (productType === 'gift_voucher') {
							setTimeout(showSoldIndividuallyField, 100);
						}
					});
					
					// Observer les mutations du DOM pour détecter quand WooCommerce modifie les champs
					if (typeof MutationObserver !== 'undefined') {
						var observer = new MutationObserver(function(mutations) {
							var productType = $('#product-type').val();
							if (productType === 'gift_voucher') {
								showSoldIndividuallyField();
							}
						});
						
						var inventoryPanel = document.getElementById('inventory_product_data');
						if (inventoryPanel) {
							observer.observe(inventoryPanel, {
								childList: true,
								subtree: true,
								attributes: true,
								attributeFilter: ['class', 'style']
							});
						}
					}
				}, 300);
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * CSS admin pour masquer les onglets dès le chargement
	 */
	public function admin_css(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		// Obtenir le type de produit actuel
		$current_type = 'simple';
		if ( isset( $_GET['post'] ) ) {
			$product = wc_get_product( absint( $_GET['post'] ) );
			if ( $product ) {
				$current_type = $product->get_type();
			}
		}

		// Si c'est un gift_voucher, ajouter un style CSS pour masquer les onglets dès le début
		if ( 'gift_voucher' === $current_type ) {
			?>
			<style type="text/css">
			/* Masquer les onglets non pertinents pour gift_voucher dès le chargement */
			.product_data_tabs li.linked_product_tab,
			.product_data_tabs li.attribute_tab,
			.product_data_tabs li.advanced_tab {
				display: none !important;
			}
			/* S'assurer que l'onglet Inventaire et Shipping sont visibles pour gift_voucher */
			.product_data_tabs li.inventory_tab.show_if_gift_voucher,
			.product_data_tabs li.shipping_tab.show_if_gift_voucher {
				display: block !important;
			}
			/* Masquer les champs de prix et TVA standard pour gift_voucher */
			#general_product_data ._regular_price_field,
			#general_product_data ._sale_price_field,
			#general_product_data ._tax_status_field,
			#general_product_data ._tax_class_field {
				display: none !important;
			}
			/* S'assurer que le contenu de l'onglet Prix n'est pas dans l'onglet Général */
			#general_product_data #dc25_gift_voucher_price_data,
			#general_product_data .dc25-price-info,
			#general_product_data input[id*="_dc25_gv_min_amount"],
			#general_product_data input[id*="_dc25_gv_max_amount"],
			#general_product_data input[id*="_dc25_gv_default_amount"] {
				display: none !important;
			}
			/* Rendre visible le champ "Sold individually" pour gift_voucher */
			body.post-type-product #inventory_product_data ._sold_individually_field,
			body.post-type-product #inventory_product_data ._sold_individually_field.show_if_gift_voucher,
			body.post-type-product #inventory_product_data ._sold_individually_field.hide_if_grouped,
			body.post-type-product #inventory_product_data ._sold_individually_field.hide_if_external,
			body.post-type-product #inventory_product_data ._sold_individually_field.hide_if_variable {
				display: block !important;
				visibility: visible !important;
				opacity: 1 !important;
			}
			/* S'assurer que le parent form-field est aussi visible */
			body.post-type-product #inventory_product_data ._sold_individually_field.form-field {
				display: block !important;
			}
			</style>
			<?php
		}
	}

	/**
	 * Script admin pour gérer l'affichage des onglets sans flash
	 */
	public function admin_script(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		// Obtenir le type de produit actuel
		$current_type = 'simple';
		if ( isset( $_GET['post'] ) ) {
			$product = wc_get_product( absint( $_GET['post'] ) );
			if ( $product ) {
				$current_type = $product->get_type();
			}
		}
		?>
		<script type="text/javascript">
		(function($) {
			// Fonction pour gérer l'affichage des onglets selon le type
			function toggleProductTabs() {
				var productType = $('#product-type').val() || '<?php echo esc_js( $current_type ); ?>';
				
				if (productType === 'gift_voucher') {
					// Afficher l'onglet Prix
					$('a[href="#dc25_gift_voucher_price_data"]').parent('li').show();
					
					// S'assurer que le contenu de l'onglet Prix n'est PAS dans l'onglet Général
					var priceContent = $('#dc25_gift_voucher_price_data');
					if (priceContent.length) {
						// Vérifier si le contenu est dans l'onglet Général ou dans un mauvais endroit
						var generalPanel = $('#general_product_data');
						var isInGeneral = priceContent.closest('#general_product_data').length > 0 || 
						                  generalPanel.find('#dc25_gift_voucher_price_data').length > 0;
						
						if (isInGeneral) {
							// Déplacer le contenu hors de l'onglet Général
							// Le placer dans le conteneur principal des panneaux (après #general_product_data)
							var productDataWrapper = $('#product_data');
							if (productDataWrapper.length) {
								priceContent.detach();
								// Insérer après l'onglet Général
								generalPanel.after(priceContent);
							} else {
								// Fallback : placer après tous les panneaux existants
								$('.woocommerce_options_panel').last().after(priceContent.detach());
							}
						}
						
						// Masquer le contenu par défaut (sera affiché quand on clique sur l'onglet)
						priceContent.css('display', 'none');
						
						// S'assurer que le contenu n'est pas visible dans l'onglet Général
						generalPanel.find('#dc25_gift_voucher_price_data').remove();
					}
					
					// Ajouter la classe show_if_gift_voucher aux onglets que nous voulons afficher
					$('.product_data_tabs .general_tab').addClass('show_if_gift_voucher');
					$('.product_data_tabs .inventory_tab').addClass('show_if_gift_voucher');
					$('.product_data_tabs .shipping_tab').addClass('show_if_gift_voucher');
					
					// Masquer les onglets non pertinents pour gift_voucher
					$('.product_data_tabs .linked_product_tab').hide();
					$('.product_data_tabs .attribute_tab').hide();
					$('.product_data_tabs .advanced_tab').hide();
					
					// Masquer les champs de prix et TVA standard
					$('#general_product_data ._regular_price_field').hide();
					$('#general_product_data ._sale_price_field').hide();
					$('#general_product_data ._tax_status_field').hide();
					$('#general_product_data ._tax_class_field').hide();
					
					// Rendre visible le champ "Sold individually" pour gift_voucher
					var soldIndividuallyField = $('#inventory_product_data ._sold_individually_field');
					if (soldIndividuallyField.length) {
						soldIndividuallyField
							.addClass('show_if_gift_voucher')
							.removeClass('hide_if_grouped hide_if_external hide_if_variable hide_if_variable-subscription')
							.css({
								'display': 'block !important',
								'visibility': 'visible !important'
							})
							.show();
						
						// Forcer aussi le parent si nécessaire
						soldIndividuallyField.closest('.form-field').show();
					}
					
					// Déclencher l'événement WooCommerce pour mettre à jour l'affichage
					$(document.body).trigger('woocommerce-product-type-change', ['gift_voucher']);
				} else {
					// Masquer l'onglet Prix si ce n'est pas un gift_voucher
					$('a[href="#dc25_gift_voucher_price_data"]').parent('li').hide();
					$('#dc25_gift_voucher_price_data').hide();
				}
			}

			// Exécuter immédiatement avec une priorité très haute
			(function() {
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', function() {
						// Utiliser requestAnimationFrame pour s'exécuter avant le rendu
						requestAnimationFrame(function() {
							toggleProductTabs();
						});
					});
				} else {
					requestAnimationFrame(function() {
						toggleProductTabs();
					});
				}
			})();

			// Exécuter aussi après le chargement complet de jQuery et WooCommerce
			$(document).ready(function() {
				// Attendre que WooCommerce ait initialisé
				setTimeout(function() {
					toggleProductTabs();
					
					// Écouter les changements de type
					$('#product-type').on('change', toggleProductTabs);
					
					// Écouter les événements WooCommerce
					$(document.body).on('woocommerce-product-type-change', toggleProductTabs);
				}, 10);
			});
		})(jQuery);
		</script>
		<?php
	}
}
