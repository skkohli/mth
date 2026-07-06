(function($) {
	'use strict';

	var ListeoPackageManager = {
		init: function() {
			this.bindEvents();
			this.initSelectAllCheckboxes();
		},

		bindEvents: function() {
			// Card view events
			$(document).on('click', '.listeo-edit-types-btn', this.showEditMode);
			$(document).on('click', '.listeo-cancel-types-btn', this.hideEditMode);
			$(document).on('click', '.listeo-save-types-btn', this.saveListingTypes);
			$(document).on('change', '.listeo-select-all-types', this.handleSelectAll);
			$(document).on('change', '.listeo-listing-types-edit input[name="allowed_types[]"]', this.handleTypeCheckbox);

			// Inline table view events
			$(document).on('click', '.listeo-edit-types-inline-btn', this.showEditModeInline);
			$(document).on('click', '.listeo-cancel-types-inline-btn', this.hideEditModeInline);
			$(document).on('click', '.listeo-save-types-inline-btn', this.saveListingTypesInline);
			$(document).on('change', '.listeo-select-all-types-inline', this.handleSelectAllInline);
			$(document).on('change', '.listeo-listing-types-edit-inline input[name="allowed_types[]"]', this.handleTypeCheckboxInline);
		},

		showEditMode: function(e) {
			e.preventDefault();
			var $card = $(this).closest('.listeo-package-card');
			$card.find('.listeo-listing-types-display').hide();
			$card.find('.listeo-listing-types-edit').slideDown(200);
			$(this).hide();
		},

		hideEditMode: function(e) {
			e.preventDefault();
			var $card = $(this).closest('.listeo-package-card');
			$card.find('.listeo-listing-types-edit').slideUp(200, function() {
				$card.find('.listeo-listing-types-display').show();
				$card.find('.listeo-edit-types-btn').show();
			});
		},

		saveListingTypes: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $card = $btn.closest('.listeo-package-card');
			var packageId = $card.data('package-id');
			var $checkboxes = $card.find('input[name="allowed_types[]"]');
			var $selectAll = $card.find('.listeo-select-all-types');
			var allowedTypes = [];

			// If "select all" is checked, send empty array (no restrictions)
			if ($selectAll.is(':checked')) {
				allowedTypes = [];
			} else {
				$checkboxes.filter(':checked').each(function() {
					allowedTypes.push($(this).val());
				});
			}

			// Disable button and show loading
			$btn.prop('disabled', true).text(listeoPackageManager.saving || 'Saving...');

			$.ajax({
				url: listeoPackageManager.ajax_url,
				type: 'POST',
				data: {
					action: 'listeo_save_package_listing_types',
					nonce: listeoPackageManager.nonce,
					package_id: packageId,
					allowed_types: allowedTypes
				},
				success: function(response) {
					if (response.success) {
						ListeoPackageManager.updateDisplayedTypes($card, response.data.types);
						ListeoPackageManager.hideEditMode.call($btn);
						ListeoPackageManager.showNotice('success', response.data.message);
					} else {
						ListeoPackageManager.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ListeoPackageManager.showNotice('error', 'An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text(listeoPackageManager.save || 'Save Changes');
				}
			});
		},

		updateDisplayedTypes: function($card, types) {
			var $display = $card.find('.listeo-listing-types-display');
			$display.empty();

			if (types.length === 0 || (types.length === 1 && types[0].is_all)) {
				$display.append('<span class="listeo-type-badge listeo-type-all">All Types</span>');
			} else {
				types.forEach(function(type) {
					$display.append(
						'<span class="listeo-type-badge" data-type="' + type.slug + '">' +
						type.name +
						'</span>'
					);
				});
			}
		},

		showNotice: function(type, message) {
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.listeo-package-products-manager h1').after($notice);

			// Auto-dismiss after 3 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 3000);
		},

		initSelectAllCheckboxes: function() {
			// Card view
			$('.listeo-package-card').each(function() {
				var $card = $(this);
				var $selectAll = $card.find('.listeo-select-all-types');
				var $typeCheckboxes = $card.find('input[name="allowed_types[]"]');

				// Set initial state of "Select All" checkbox
				if ($typeCheckboxes.filter(':checked').length === 0) {
					$selectAll.prop('checked', true);
					$typeCheckboxes.prop('disabled', true);
				}
			});

			// Inline table view
			$('.listeo-package-listing-types-inline').each(function() {
				var $container = $(this);
				var $selectAll = $container.find('.listeo-select-all-types-inline');
				var $typeCheckboxes = $container.find('input[name="allowed_types[]"]');

				// Set initial state of "Select All" checkbox
				if ($typeCheckboxes.filter(':checked').length === 0) {
					$selectAll.prop('checked', true);
					$typeCheckboxes.prop('disabled', true);
				}
			});
		},

		handleSelectAll: function() {
			var $selectAll = $(this);
			var $card = $selectAll.closest('.listeo-package-card');
			var $typeCheckboxes = $card.find('input[name="allowed_types[]"]');

			if ($selectAll.is(':checked')) {
				$typeCheckboxes.prop('checked', false).prop('disabled', true);
			} else {
				$typeCheckboxes.prop('disabled', false);
			}
		},

		handleTypeCheckbox: function() {
			var $checkbox = $(this);
			var $card = $checkbox.closest('.listeo-package-card');
			var $selectAll = $card.find('.listeo-select-all-types');
			var $typeCheckboxes = $card.find('input[name="allowed_types[]"]');

			// If any type checkbox is checked, uncheck "Select All"
			if ($typeCheckboxes.filter(':checked').length > 0) {
				$selectAll.prop('checked', false);
			}
		},

		// Inline table view methods
		showEditModeInline: function(e) {
			e.preventDefault();
			var $container = $(this).closest('.listeo-package-listing-types-inline');
			$container.find('.listeo-listing-types-display-inline').hide();
			$container.find('.listeo-listing-types-edit-inline').slideDown(200);
		},

		hideEditModeInline: function(e) {
			e.preventDefault();
			var $container = $(this).closest('.listeo-package-listing-types-inline');
			$container.find('.listeo-listing-types-edit-inline').slideUp(200, function() {
				$container.find('.listeo-listing-types-display-inline').show();
			});
		},

		saveListingTypesInline: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $container = $btn.closest('.listeo-package-listing-types-inline');
			var productId = $container.data('product-id');
			var $checkboxes = $container.find('input[name="allowed_types[]"]');
			var $selectAll = $container.find('.listeo-select-all-types-inline');
			var allowedTypes = [];

			// If "select all" is checked, send empty array (no restrictions)
			if ($selectAll.is(':checked')) {
				allowedTypes = [];
			} else {
				$checkboxes.filter(':checked').each(function() {
					allowedTypes.push($(this).val());
				});
			}

			// Disable button and show loading
			$btn.prop('disabled', true).text(listeoPackageManager.saving || 'Saving...');

			$.ajax({
				url: listeoPackageManager.ajax_url,
				type: 'POST',
				data: {
					action: 'listeo_save_package_listing_types',
					nonce: listeoPackageManager.nonce,
					package_id: productId,
					allowed_types: allowedTypes
				},
				success: function(response) {
					if (response.success) {
						ListeoPackageManager.updateDisplayedTypesInline($container, response.data.types);
						ListeoPackageManager.hideEditModeInline.call($btn);
						ListeoPackageManager.showNotice('success', response.data.message);
					} else {
						ListeoPackageManager.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ListeoPackageManager.showNotice('error', 'An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text(listeoPackageManager.save || 'Save');
				}
			});
		},

		updateDisplayedTypesInline: function($container, types) {
			var $display = $container.find('.listeo-listing-types-display-inline');
			// Keep the edit button
			var $editBtn = $display.find('.listeo-edit-types-inline-btn');
			$display.empty();

			if (types.length === 0 || (types.length === 1 && types[0].is_all)) {
				$display.append('<span class="listeo-type-badge listeo-type-all">All Types</span>');
			} else {
				types.forEach(function(type) {
					$display.append(
						'<span class="listeo-type-badge" data-type="' + type.slug + '">' +
						type.name +
						'</span>'
					);
				});
			}

			// Re-append the edit button
			$display.append($editBtn);
		},

		handleSelectAllInline: function() {
			var $selectAll = $(this);
			var $container = $selectAll.closest('.listeo-package-listing-types-inline');
			var $typeCheckboxes = $container.find('input[name="allowed_types[]"]');

			if ($selectAll.is(':checked')) {
				$typeCheckboxes.prop('checked', false).prop('disabled', true);
			} else {
				$typeCheckboxes.prop('disabled', false);
			}
		},

		handleTypeCheckboxInline: function() {
			var $checkbox = $(this);
			var $container = $checkbox.closest('.listeo-package-listing-types-inline');
			var $selectAll = $container.find('.listeo-select-all-types-inline');
			var $typeCheckboxes = $container.find('input[name="allowed_types[]"]');

			// If any type checkbox is checked, uncheck "Select All"
			if ($typeCheckboxes.filter(':checked').length > 0) {
				$selectAll.prop('checked', false);
			}
		}
	};

	$(document).ready(function() {
		ListeoPackageManager.init();
	});

})(jQuery);
