# Guide de débogage - DC25 Vouchers

## Vérifier que le script JavaScript se charge

### 1. Vérification dans le navigateur

1. Ouvrez la page produit d'un bon cadeau
2. Ouvrez les outils de développement (F12)
3. Allez dans l'onglet **Console**
4. Vérifiez qu'il n'y a pas d'erreurs liées à `dc25-gift-voucher-product`
5. Tapez dans la console : `typeof dc25GiftVoucher`
   - Devrait retourner : `"object"`
   - Si retourne `"undefined"`, le script n'est pas chargé

### 2. Vérification dans le code source

1. Ouvrez la page produit
2. Faites clic droit > "Afficher le code source"
3. Recherchez : `gift-voucher-product.js`
4. Le script devrait apparaître dans le `<footer>` avec une URL correcte

### 3. Vérification via WordPress

Activez `WP_DEBUG` dans `wp-config.php` :
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Puis vérifiez les logs dans `/wp-content/debug.log` pour les messages :
- `DC25 Vouchers: Script file not found`
- `DC25 Vouchers: Failed to enqueue script`

## Solution native WooCommerce pour les prix personnalisés

**Réponse courte : Non, il n'y a pas de solution native.**

WooCommerce ne fournit pas de fonctionnalité native pour permettre aux clients de définir un prix libre. La méthode utilisée dans ce plugin est la **méthode standard recommandée** :

1. **Hook `woocommerce_before_calculate_totals`** : Permet de modifier le prix avant le calcul des totaux
2. **Méthode `set_price()`** : Définit le prix du produit dans le panier
3. **Cart item data** : Stocke les données personnalisées avec `woocommerce_add_cart_item_data`

Cette approche est utilisée par de nombreuses extensions WooCommerce commerciales (comme Product Add-Ons, etc.).

## Alternatives

Si vous préférez une solution sans code, vous pouvez utiliser :
- **WooCommerce Product Add-Ons** (extension payante)
- **WooCommerce Custom Product Addons** (extension gratuite/payante)

Mais pour un cas d'usage spécifique comme les bons cadeaux, une solution sur mesure est généralement préférable.

