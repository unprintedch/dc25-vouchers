/**
 * Script AJAX pour l'encaissement des bons cadeaux
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const $form = $('#dc25-redeem-form');
		const $message = $('#dc25-redeem-message');
		const $button = $form.find('button[type="submit"]');
		const $cashierName = $('#dc25_cashier_name');
		const $receiptFile = $('#dc25_receipt_file');
		const $cptSelect = $('#dc25_selected_cpt');

		if (!$form.length) {
			return; // Formulaire non présent sur la page
		}

		// Vérifier que les données AJAX sont disponibles
		if (typeof dc25Redeem === 'undefined') {
			console.error('DC25 Redeem: Configuration AJAX non disponible');
			return;
		}

		// Initialiser Select2 pour la recherche de CPT
		if ($cptSelect.length && typeof $.fn.select2 !== 'undefined' && dc25Redeem.allowed_post_types && dc25Redeem.allowed_post_types.length > 0) {
			$cptSelect.select2({
				ajax: {
					url: dc25Redeem.ajax_url,
					type: 'POST',
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'dc25_search_cpt',
							search: params.term || '',
							page: params.page || 1,
							post_type: dc25Redeem.allowed_post_types
						};
					},
					processResults: function(data) {
						if (data && data.success && data.data && data.data.results) {
							return {
								results: data.data.results,
								pagination: {
									more: data.data.pagination && data.data.pagination.more === true
								}
							};
						}
						return {
							results: [],
							pagination: { more: false }
						};
					},
					cache: true
				},
				placeholder: dc25Redeem.i18n.select_cpt_placeholder || 'Rechercher un producteur/vigneron...',
				minimumInputLength: 2,
				language: {
					inputTooShort: function() {
						return 'Tapez au moins 2 caractères...';
					},
					noResults: function() {
						return dc25Redeem.i18n.no_results || 'Aucun résultat trouvé';
					},
					searching: function() {
						return dc25Redeem.i18n.searching || 'Recherche en cours...';
					}
				}
			});
		}

		$form.on('submit', function(e) {
			e.preventDefault();

			// Validation côté client
			if (!$cashierName.val().trim()) {
				showMessage(dc25Redeem.i18n.error || 'Le nom est requis.', 'error');
				$cashierName.focus();
				return;
			}

			// Préparer FormData pour l'upload de fichier
			const formData = new FormData();
			formData.append('action', 'dc25_redeem_coupon');
			formData.append('nonce', dc25Redeem.nonce);
			formData.append('dc25_coupon_code', dc25Redeem.coupon_code);
			formData.append('dc25_cashier_name', $cashierName.val().trim());

			// Ajouter le CPT sélectionné si présent
			if ($cptSelect.length && $cptSelect.val()) {
				formData.append('dc25_selected_cpt', $cptSelect.val());
			}

			// Ajouter le fichier si présent
			if ($receiptFile[0].files.length > 0) {
				formData.append('dc25_receipt_file', $receiptFile[0].files[0]);
			}

			// Désactiver le bouton et afficher le chargement
			$button.prop('disabled', true);
			const originalButtonText = $button.html();
			$button.html((dc25Redeem.i18n.uploading || 'Traitement en cours...') + '<span class="dc25-form-loading"></span>');
			hideMessage();

			// Envoyer la requête AJAX
			$.ajax({
				url: dc25Redeem.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						showMessage(response.data.message || dc25Redeem.i18n.success, 'success');
						$form[0].reset();
						$button.prop('disabled', true);
						
						// Recharger la page après 2 secondes pour afficher le statut mis à jour
						setTimeout(function() {
							window.location.reload();
						}, 2000);
					} else {
						showMessage(response.data.message || dc25Redeem.i18n.error, 'error');
						$button.prop('disabled', false);
						$button.html(originalButtonText);
					}
				},
				error: function(xhr, status, error) {
					console.error('DC25 Redeem AJAX Error:', error);
					showMessage(dc25Redeem.i18n.error || 'Une erreur est survenue. Veuillez réessayer.', 'error');
					$button.prop('disabled', false);
					$button.html(originalButtonText);
				}
			});
		});

		/**
		 * Afficher un message
		 */
		function showMessage(message, type) {
			$message
				.removeClass('success error')
				.addClass(type)
				.text(message)
				.slideDown();
		}

		/**
		 * Masquer le message
		 */
		function hideMessage() {
			$message.slideUp();
		}
	});
})(jQuery);

