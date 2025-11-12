/**
 * Gestion du formulaire de bon cadeau sur la page produit
 * 
 * Ce script gère :
 * - La validation du montant saisi par le client
 * - L'activation/désactivation du bouton "Ajouter au panier"
 * - La mise à jour dynamique du prix affiché
 * - L'affichage conditionnel des champs d'adresse destinataire
 *
 * @package DC25_Vouchers
 */

(function() {
	'use strict';

	// Vérifier que les données sont disponibles
	if (typeof dc25GiftVoucher === 'undefined') {
		// Log en mode debug
		if (window.console && console.warn) {
			console.warn('DC25 Gift Voucher: Configuration data not found. Script may not be loaded correctly.');
		}
		return;
	}

	const { minAmount, maxAmount, defaultAmount } = dc25GiftVoucher;

	// Éléments DOM
	const amountInput = document.getElementById('dc25_gv_amount');
	const addToCartButton = document.querySelector('button.single_add_to_cart_button, input.single_add_to_cart_button');
	const physicalCheckbox = document.getElementById('dc25_gv_physical');
	const shipToRadios = document.querySelectorAll('input[name="dc25_gv_ship_to"]');
	const recipientAddressFields = document.querySelector('.dc25-recipient-address-fields');
	const shipToWrapper = document.querySelector('.dc25-ship-to-wrapper');

	// Vérifier que les éléments essentiels existent
	if (!amountInput) {
		if (window.console && console.warn) {
			console.warn('DC25 Gift Voucher: Amount input field not found.');
		}
		return;
	}

	if (!addToCartButton) {
		if (window.console && console.warn) {
			console.warn('DC25 Gift Voucher: Add to cart button not found.');
		}
		// On continue quand même car le champ montant peut exister sans le bouton (cas edge)
	}

	/**
	 * Mettre à jour l'affichage du prix
	 */
	function updatePriceDisplay(amount) {
		const formattedAmount = amount.toFixed(2);
		
		// Sélecteurs possibles pour le prix
		const selectors = [
			'.dc25-dynamic-price',
			'.price .woocommerce-Price-amount',
			'.price .amount',
			'.woocommerce-Price-amount.amount',
			'.summary .price .woocommerce-Price-amount',
			'.entry-summary .price .woocommerce-Price-amount'
		];

		for (const selector of selectors) {
			const priceElement = document.querySelector(selector);
			if (priceElement) {
				priceElement.textContent = formattedAmount;
				return true;
			}
		}

		// Si aucun sélecteur ne fonctionne, chercher dans .price
		const priceContainer = document.querySelector('p.price, .price');
		if (priceContainer) {
			let amountEl = priceContainer.querySelector('.woocommerce-Price-amount, .amount');
			if (amountEl) {
				amountEl.textContent = formattedAmount;
			} else {
				priceContainer.innerHTML = `
					<span class="woocommerce-Price-amount amount">${formattedAmount}</span>
					<span class="woocommerce-Price-currencySymbol"> CHF</span>
				`;
			}
			return true;
		}

		return false;
	}

	/**
	 * Désactiver le bouton "Ajouter au panier"
	 */
	function disableAddToCartButton() {
		if (!addToCartButton) {
			return;
		}
		addToCartButton.disabled = true;
		addToCartButton.classList.add('disabled');
		addToCartButton.style.opacity = '0.5';
		addToCartButton.style.cursor = 'not-allowed';
	}

	/**
	 * Activer le bouton "Ajouter au panier"
	 */
	function enableAddToCartButton() {
		if (!addToCartButton) {
			return;
		}
		addToCartButton.disabled = false;
		addToCartButton.classList.remove('disabled');
		addToCartButton.style.opacity = '1';
		addToCartButton.style.cursor = 'pointer';
	}

	/**
	 * Afficher un message d'erreur
	 */
	function showError(message) {
		// Supprimer les messages d'erreur existants
		removeError();

		amountInput.classList.add('error');
		const errorElement = document.createElement('span');
		errorElement.className = 'error-message';
		errorElement.style.cssText = 'color: #dc3232; display: block; margin-top: 5px;';
		errorElement.textContent = message;
		amountInput.parentNode.insertBefore(errorElement, amountInput.nextSibling);
	}

	/**
	 * Supprimer le message d'erreur
	 */
	function removeError() {
		amountInput.classList.remove('error');
		const errorMessage = amountInput.parentNode.querySelector('.error-message');
		if (errorMessage) {
			errorMessage.remove();
		}
	}

	/**
	 * Valider le montant
	 */
	function validateAmount() {
		const amount = parseFloat(amountInput.value);

		removeError();

		// Vérifier si le montant est valide
		if (isNaN(amount) || amount <= 0) {
			disableAddToCartButton();
			return false;
		}

		// Vérifier les limites
		if (amount < minAmount || amount > maxAmount) {
			showError(`Le montant doit être entre ${minAmount.toFixed(2)} et ${maxAmount.toFixed(2)} CHF`);
			disableAddToCartButton();
			return false;
		}

		// Montant valide
		enableAddToCartButton();
		updatePriceDisplay(amount);
		return true;
	}

	/**
	 * Gérer l'affichage des champs d'adresse destinataire
	 */
	function handlePhysicalDelivery() {
		if (!physicalCheckbox || !shipToWrapper) {
			return;
		}

		if (physicalCheckbox.checked) {
			shipToWrapper.style.display = 'block';
		} else {
			shipToWrapper.style.display = 'none';
			if (recipientAddressFields) {
				recipientAddressFields.style.display = 'none';
			}
		}
	}

	/**
	 * Gérer le choix de destination d'envoi
	 */
	function handleShipTo() {
		if (!recipientAddressFields) {
			return;
		}

		const selectedShipTo = document.querySelector('input[name="dc25_gv_ship_to"]:checked');
		if (!selectedShipTo) {
			return;
		}

		if (selectedShipTo.value === 'recipient') {
			recipientAddressFields.style.display = 'block';
			const requiredFields = recipientAddressFields.querySelectorAll('input, select');
			requiredFields.forEach(field => {
				field.setAttribute('required', 'required');
			});
		} else {
			recipientAddressFields.style.display = 'none';
			const requiredFields = recipientAddressFields.querySelectorAll('input, select');
			requiredFields.forEach(field => {
				field.removeAttribute('required');
			});
		}
	}

	/**
	 * Initialisation
	 */
	function init() {
		// Attendre que le bouton soit disponible avant de le désactiver
		if (!addToCartButton) {
			// Chercher le bouton avec un délai
			setTimeout(function() {
				addToCartButton = document.querySelector('button.single_add_to_cart_button, input.single_add_to_cart_button');
				if (addToCartButton) {
					// Désactiver le bouton maintenant qu'il est trouvé
					disableAddToCartButton();
					// Valider le montant par défaut
					if (amountInput.value && parseFloat(amountInput.value) >= minAmount && parseFloat(amountInput.value) <= maxAmount) {
						setTimeout(validateAmount, 100);
					}
				}
			}, 100);
		} else {
			// Désactiver le bouton par défaut (si présent)
			disableAddToCartButton();
		}

		// Masquer les champs d'adresse destinataire par défaut
		if (recipientAddressFields) {
			recipientAddressFields.style.display = 'none';
		}

		// Écouter les changements du montant
		amountInput.addEventListener('input', () => {
			const amount = parseFloat(amountInput.value);
			if (!isNaN(amount) && amount > 0) {
				updatePriceDisplay(amount);
			}
		});

		amountInput.addEventListener('change', validateAmount);
		amountInput.addEventListener('blur', validateAmount);

		// Gérer l'envoi physique
		if (physicalCheckbox) {
			physicalCheckbox.addEventListener('change', handlePhysicalDelivery);
		}

		// Gérer le choix de destination
		shipToRadios.forEach(radio => {
			radio.addEventListener('change', handleShipTo);
		});

		// Valider au chargement si le montant par défaut est valide
		if (amountInput.value && parseFloat(amountInput.value) >= minAmount && parseFloat(amountInput.value) <= maxAmount) {
			setTimeout(validateAmount, 100);
		}
	}

	// Initialiser quand le DOM est prêt
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();

