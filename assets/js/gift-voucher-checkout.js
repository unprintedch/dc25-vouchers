/**
 * Gestion des champs checkout pour les bons cadeaux
 *
 * @package DC25_Vouchers
 */

(function() {
	'use strict';

	// Vérifier que les données sont disponibles
	if (typeof dc25GiftVoucherCheckout === 'undefined') {
		return;
	}

	const { minAmount, maxAmount } = dc25GiftVoucherCheckout;

	// Éléments DOM
	const amountInput = document.getElementById('dc25_gv_amount');
	const physicalCheckbox = document.getElementById('dc25_gv_physical');
	const shipToRadios = document.querySelectorAll('input[name="dc25_gv_ship_to"]');
	const recipientAddressFields = document.querySelectorAll('.dc25-recipient-address');

	if (!amountInput) {
		return;
	}

	/**
	 * Masquer les champs d'adresse destinataire
	 */
	function hideRecipientAddressFields() {
		recipientAddressFields.forEach(field => {
			const formRow = field.closest('.form-row');
			if (formRow) {
				formRow.style.display = 'none';
			}
			field.removeAttribute('required');
		});
	}

	/**
	 * Afficher les champs d'adresse destinataire
	 */
	function showRecipientAddressFields() {
		recipientAddressFields.forEach(field => {
			const formRow = field.closest('.form-row');
			if (formRow) {
				formRow.style.display = 'block';
			}
			field.setAttribute('required', 'required');
		});
	}

	/**
	 * Gérer l'affichage des champs selon l'envoi physique et la destination
	 */
	function toggleRecipientAddress() {
		const isPhysical = physicalCheckbox && physicalCheckbox.checked;
		const selectedShipTo = document.querySelector('input[name="dc25_gv_ship_to"]:checked');
		const shipTo = selectedShipTo ? selectedShipTo.value : 'billing';

		if (isPhysical && shipTo === 'recipient') {
			showRecipientAddressFields();
		} else {
			hideRecipientAddressFields();
		}
	}

	/**
	 * Valider le montant
	 */
	function validateAmount() {
		const amount = parseFloat(amountInput.value);

		if (isNaN(amount) || amount < minAmount || amount > maxAmount) {
			amountInput.classList.add('woocommerce-invalid');
			return false;
		}

		amountInput.classList.remove('woocommerce-invalid');
		return true;
	}

	/**
	 * Mettre à jour le checkout
	 */
	function updateCheckout() {
		if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
			jQuery('body').trigger('update_checkout');
		} else {
			// Fallback si jQuery n'est pas disponible
			const event = new CustomEvent('update_checkout');
			document.body.dispatchEvent(event);
		}
	}

	/**
	 * Initialisation
	 */
	function init() {
		// Masquer les champs d'adresse destinataire par défaut
		hideRecipientAddressFields();

		// Écouter les changements de l'envoi physique
		if (physicalCheckbox) {
			physicalCheckbox.addEventListener('change', toggleRecipientAddress);
		}

		// Écouter les changements de destination
		shipToRadios.forEach(radio => {
			radio.addEventListener('change', toggleRecipientAddress);
		});

		// Validation du montant
		amountInput.addEventListener('change', () => {
			validateAmount();
			if (validateAmount()) {
				updateCheckout();
			}
		});

		amountInput.addEventListener('blur', validateAmount);
	}

	// Initialiser quand le DOM est prêt
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();

