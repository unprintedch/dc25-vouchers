# DC25 Vouchers - Plugin WooCommerce

Plugin WooCommerce pour la vente de bons cadeaux avec prix libre, génération automatique de PDF, QR codes et validation publique.

## Fonctionnalités

### 1. Type de produit personnalisé `gift_voucher`
- Hérite de `WC_Product_Simple`
- Champs admin configurables :
  - Montant minimum/maximum (défaut: 20-200 CHF)
  - Montant par défaut
  - Durée de validité (défaut: 365 jours)
  - Préfixe coupon (défaut: GV-)
  - Option envoi physique
  - Taux de TVA (défaut: 0%)

### 2. Champs checkout personnalisés
Affichés uniquement si le panier contient un bon cadeau :
- **Montant** : Champ numérique avec validation min/max
- **Message personnalisé** : Texte pour le destinataire
- **Destinataire** : Nom et email (optionnels)
- **Envoi physique** : Checkbox pour demander un envoi postal
- **Destination** : Radio (facturation / destinataire)
- **Adresse destinataire** : Champs conditionnels si envoi physique + destinataire

### 3. Génération automatique après paiement
Lorsque la commande passe au statut "Complétée" :
1. **Création d'un coupon WooCommerce** :
   - Type: `fixed_cart`
   - Code unique: PREFIX + 8 caractères aléatoires
   - Usage unique
   - Date d'expiration configurable

2. **Génération d'un PDF** :
   - Template surchargeable via le thème
   - QR code intégré avec données JSON
   - Logo et couleurs personnalisables
   - Format A5 paysage (configurable)

3. **Envoi d'emails** :
   - Email à l'acheteur avec PDF en pièce jointe
   - Email au destinataire (si activé et renseigné)

### 4. QR Code
Le QR code contient un JSON avec :
```json
{
  "type": "gift_voucher",
  "code": "GV-7T4Q9C2K",
  "amount": 150,
  "currency": "CHF",
  "expires": "2026-11-12",
  "verify_url": "https://site.tld/?dc25_gv_verify=GV-7T4Q9C2K"
}
```

### 5. Endpoint de vérification publique
URL : `?dc25_gv_verify=CODE_COUPON`

Affiche le statut du bon :
- ✅ **Valide** : Bon utilisable
- ⏰ **Expiré** : Date d'expiration dépassée
- ❌ **Utilisé** : Déjà utilisé
- ❓ **Invalide** : Code non trouvé

### 6. Page de réglages
WooCommerce > Réglages > Bons cadeaux

Options disponibles :
- Préfixe coupon par défaut
- Validité par défaut (jours)
- Logo PDF
- Couleur thème PDF
- Taille et orientation du PDF
- Texte des conditions
- Activation email destinataire
- Sujet et contenu email destinataire

## Installation

1. Copier le dossier `dc25-vouchers` dans `wp-content/plugins/`
2. Installer les dépendances Composer :
   ```bash
   cd wp-content/plugins/dc25-vouchers
   composer install
   ```
3. Activer le plugin dans WordPress
4. Configurer les réglages dans WooCommerce > Réglages > Bons cadeaux

## Structure du plugin

```
dc25-vouchers/
├── dc25-vouchers.php          # Fichier principal
├── includes/
│   ├── class-dc25-gift-product-type.php    # Type de produit
│   ├── class-dc25-checkout-fields.php      # Champs checkout
│   ├── class-dc25-order-handler.php        # Gestionnaire de commandes
│   ├── class-dc25-coupon-service.php       # Service coupons
│   ├── class-dc25-pdf-service.php         # Service PDF
│   ├── class-dc25-qr-service.php          # Service QR code
│   ├── class-dc25-settings.php             # Réglages admin
│   ├── class-dc25-verify-endpoint.php      # Endpoint vérification
│   └── helpers.php                         # Fonctions helper
├── templates/
│   └── voucher-pdf.php                      # Template PDF (surchargeable)
├── vendor/                                 # Dépendances Composer
└── composer.json
```

## Surcharge du template PDF

Pour personnaliser le template PDF, créez le fichier suivant dans votre thème :

```
wp-content/themes/votre-theme/dc25-vouchers/voucher-pdf.php
```

Variables disponibles dans le template :
- `$coupon_code` : Code du coupon
- `$amount` : Montant
- `$currency` : Devise
- `$expiry_date` : Date d'expiration (Y-m-d)
- `$message` : Message personnalisé
- `$recipient_name` : Nom du destinataire
- `$qr_code` : QR code en base64
- `$logo_url` : URL du logo
- `$theme_color` : Couleur thème
- `$conditions` : Texte des conditions

## Hooks disponibles

### Actions
- `dc25_voucher_generated` : Après génération d'un bon (coupon + PDF)
  - Paramètres : `$order_id`, `$item_id`, `$coupon_code`, `$pdf_path`

### Filtres
- `dc25_voucher_pdf_data` : Modifier les données avant génération PDF
- `dc25_voucher_email_content` : Modifier le contenu de l'email
- `woocommerce_dc25_vouchers_settings` : Modifier les réglages admin

## Compatibilité

- WordPress : 6.0+
- WooCommerce : 8.0+
- PHP : 8.0+
- Testé jusqu'à WooCommerce 9.0

## Dépendances

- `dompdf/dompdf` : ^2.0 (génération PDF)
- `endroid/qr-code` : ^5.0 (génération QR code)

## Support

Pour toute question ou problème, contactez : support@unprinted.ch

## Licence

Propriétaire - Unprinted

