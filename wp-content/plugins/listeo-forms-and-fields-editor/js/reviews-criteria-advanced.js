(function ($) {
	'use strict';

	/**
	 * Per-Type Criteria Management
	 */
	var TypeCriteriaManager = {
		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			// Type selector change
			$('#listeo-type-selector').on('change', function () {
				var type = $(this).val();
				if (type) {
					self.loadTypeCriteria(type);
					$('#listeo-copy-criteria-btn').prop('disabled', false);
				} else {
					$('#listeo-criteria-editor').hide();
					$('#listeo-copy-criteria-btn').prop('disabled', true);
				}
			});

			// Add new criteria row
			$(document).on('click', '.add-new-main-option', function (e) {
				e.preventDefault();
				self.addCriteriaRow($('#listeo-criteria-rows'));
			});

			// Remove criteria row
			$(document).on('click', '#listeo-criteria-rows .remove-row', function (e) {
				e.preventDefault();
				if (confirm(listeoReviewsCriteriaL10n.confirm_remove)) {
					$(this).closest('tr').remove();
				}
			});

			// Save form
			$('#listeo-type-criteria-form').on('submit', function (e) {
				e.preventDefault();
				self.saveTypeCriteria();
			});

			// Reset type criteria
			$('#listeo-reset-type-criteria').on('click', function (e) {
				e.preventDefault();
				if (confirm(listeoReviewsCriteriaL10n.confirm_remove)) {
					self.resetTypeCriteria();
				}
			});

			// Copy criteria
			$('#listeo-copy-criteria-btn').on('click', function (e) {
				e.preventDefault();
				$('#listeo-copy-criteria-modal').show();
			});

			$('#listeo-copy-cancel').on('click', function () {
				$('#listeo-copy-criteria-modal').hide();
			});

			$('#listeo-copy-confirm').on('click', function () {
				var source = $('input[name="copy_source"]:checked').val();
				self.copyCriteria(source);
			});
		},

		loadTypeCriteria: function (type) {
			var self = this;
			$('#listeo-loading').show();
			$('#listeo-criteria-editor').hide();

			$.ajax({
				url: listeoReviewsCriteriaL10n.ajax_url,
				type: 'POST',
				data: {
					action: 'listeo_load_type_criteria',
					nonce: listeoReviewsCriteriaL10n.nonce,
					listing_type: type
				},
				success: function (response) {
					$('#listeo-loading').hide();
					if (response.success) {
						$('#listeo-selected-type').val(type);
						self.renderCriteria(response.data.criteria, $('#listeo-criteria-rows'));
						$('#listeo-criteria-editor').show();
					} else {
						alert(response.data.message || listeoReviewsCriteriaL10n.error_occurred);
					}
				},
				error: function () {
					$('#listeo-loading').hide();
					alert(listeoReviewsCriteriaL10n.error_occurred);
				}
			});
		},

		saveTypeCriteria: function () {
			var formData = $('#listeo-type-criteria-form').serialize();

			$.ajax({
				url: listeoReviewsCriteriaL10n.ajax_url,
				type: 'POST',
				data: formData + '&action=listeo_save_type_criteria&nonce=' + listeoReviewsCriteriaL10n.nonce,
				success: function (response) {
					if (response.success) {
						alert(listeoReviewsCriteriaL10n.saved_successfully);
					} else {
						alert(response.data.message || listeoReviewsCriteriaL10n.error_occurred);
					}
				},
				error: function () {
					alert(listeoReviewsCriteriaL10n.error_occurred);
				}
			});
		},

		resetTypeCriteria: function () {
			var type = $('#listeo-selected-type').val();
			if (!type) {
				return;
			}

			// Clear criteria rows and reload global defaults
			$('#listeo-criteria-rows').empty();
			this.loadTypeCriteria(type);
		},

		copyCriteria: function (source) {
			var self = this;

			$.ajax({
				url: listeoReviewsCriteriaL10n.ajax_url,
				type: 'POST',
				data: {
					action: 'listeo_copy_criteria',
					nonce: listeoReviewsCriteriaL10n.nonce,
					source: source
				},
				success: function (response) {
					if (response.success) {
						self.renderCriteria(response.data.criteria, $('#listeo-criteria-rows'));
						$('#listeo-copy-criteria-modal').hide();
					} else {
						alert(response.data.message || listeoReviewsCriteriaL10n.error_occurred);
					}
				},
				error: function () {
					alert(listeoReviewsCriteriaL10n.error_occurred);
				}
			});
		},

		renderCriteria: function (criteria, $container) {
			$container.empty();

			var index = 0;
			$.each(criteria, function (key, value) {
				var row = '<tr>' +
					'<td><input type="text" value="' + self.escapeHtml(value.label) + '" class="input-text options" name="label[' + index + ']" /></td>' +
					'<td><textarea name="tooltip[' + index + ']" rows="5">' + self.escapeHtml(value.tooltip || '') + '</textarea></td>' +
					'<td><a class="remove-row button" href="#">Remove</a></td>' +
					'</tr>';
				$container.append(row);
				index++;
			});
		},

		addCriteriaRow: function ($container) {
			var index = $container.find('tr').length;
			var row = '<tr>' +
				'<td><input type="text" class="input-text options" name="label[' + index + ']" /></td>' +
				'<td><textarea name="tooltip[' + index + ']" rows="5"></textarea></td>' +
				'<td><a class="remove-row button" href="#">Remove</a></td>' +
				'</tr>';
			$container.append(row);
		}
	};

	/**
	 * Per-Taxonomy Criteria Management
	 */
	var TaxonomyCriteriaManager = {
		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			// Taxonomy selector change
			$('#listeo-taxonomy-selector').on('change', function () {
				var taxonomy = $(this).val();
				if (taxonomy) {
					self.loadTaxonomyTerms(taxonomy);
					$('#listeo-term-selector').prop('disabled', false);
				} else {
					$('#listeo-term-selector').html('<option value="">-- First select taxonomy --</option>').prop('disabled', true);
					$('#listeo-criteria-editor-tax').hide();
					$('#listeo-copy-criteria-tax-btn').prop('disabled', true);
				}
			});

			// Term selector change
			$('#listeo-term-selector').on('change', function () {
				var taxonomy = $('#listeo-taxonomy-selector').val();
				var term_id = $(this).val();
				if (taxonomy && term_id) {
					self.loadTaxonomyCriteria(taxonomy, term_id);
					$('#listeo-copy-criteria-tax-btn').prop('disabled', false);
				} else {
					$('#listeo-criteria-editor-tax').hide();
					$('#listeo-copy-criteria-tax-btn').prop('disabled', true);
				}
			});

			// Add new criteria row
			$(document).on('click', '.add-new-main-option-tax', function (e) {
				e.preventDefault();
				self.addCriteriaRow($('#listeo-criteria-rows-tax'));
			});

			// Remove criteria row
			$(document).on('click', '#listeo-criteria-rows-tax .remove-row', function (e) {
				e.preventDefault();
				if (confirm(listeoReviewsCriteriaL10n.confirm_remove)) {
					$(this).closest('tr').remove();
				}
			});

			// Save form
			$('#listeo-taxonomy-criteria-form').on('submit', function (e) {
				e.preventDefault();
				self.saveTaxonomyCriteria();
			});

			// Reset taxonomy criteria
			$('#listeo-reset-taxonomy-criteria').on('click', function (e) {
				e.preventDefault();
				if (confirm(listeoReviewsCriteriaL10n.confirm_remove)) {
					self.resetTaxonomyCriteria();
				}
			});
		},

		loadTaxonomyTerms: function (taxonomy) {
			var self = this;

			$.ajax({
				url: listeoReviewsCriteriaL10n.ajax_url,
				type: 'POST',
				data: {
					action: 'listeo_get_taxonomy_terms',
					nonce: listeoReviewsCriteriaL10n.nonce,
					taxonomy: taxonomy
				},
				success: function (response) {
					if (response.success) {
						var $select = $('#listeo-term-selector');
						$select.html('<option value="">-- Choose Term --</option>');

						$.each(response.data.terms, function (index, term) {
							var indent = '';
							if (term.parent > 0) {
								indent = '&nbsp;&nbsp;&mdash;&nbsp;';
							}
							$select.append('<option value="' + term.id + '">' + indent + term.name + '</option>');
						});
					} else {
						alert(response.data.message || listeoReviewsCriteriaL10n.error_occurred);
					}
				},
				error: function () {
					alert(listeoReviewsCriteriaL10n.error_occurred);
				}
			});
		},

		loadTaxonomyCriteria: function (taxonomy, term_id) {
			var self = this;
			$('#listeo-loading-tax').show();
			$('#listeo-criteria-editor-tax').hide();

			$.ajax({
				url: listeoReviewsCriteriaL10n.ajax_url,
				type: 'POST',
				data: {
					action: 'listeo_load_taxonomy_criteria',
					nonce: listeoReviewsCriteriaL10n.nonce,
					taxonomy: taxonomy,
					term_id: term_id
				},
				success: function (response) {
					$('#listeo-loading-tax').hide();
					if (response.success) {
						$('#listeo-selected-taxonomy').val(taxonomy);
						$('#listeo-selected-term').val(term_id);
						self.renderCriteria(response.data.criteria, $('#listeo-criteria-rows-tax'));

						// Show hierarchy
						if (response.data.hierarchy_path) {
							$('#listeo-hierarchy-path').html(response.data.hierarchy_path);
							$('#listeo-hierarchy-context').show();
						}

						$('#listeo-criteria-editor-tax').show();
					} else {
						alert(response.data.message || listeoReviewsCriteriaL10n.error_occurred);
					}
				},
				error: function () {
					$('#listeo-loading-tax').hide();
					alert(listeoReviewsCriteriaL10n.error_occurred);
				}
			});
		},

		saveTaxonomyCriteria: function () {
			var formData = $('#listeo-taxonomy-criteria-form').serialize();

			$.ajax({
				url: listeoReviewsCriteriaL10n.ajax_url,
				type: 'POST',
				data: formData + '&action=listeo_save_taxonomy_criteria&nonce=' + listeoReviewsCriteriaL10n.nonce,
				success: function (response) {
					if (response.success) {
						alert(listeoReviewsCriteriaL10n.saved_successfully);
					} else {
						alert(response.data.message || listeoReviewsCriteriaL10n.error_occurred);
					}
				},
				error: function () {
					alert(listeoReviewsCriteriaL10n.error_occurred);
				}
			});
		},

		resetTaxonomyCriteria: function () {
			var taxonomy = $('#listeo-selected-taxonomy').val();
			var term_id = $('#listeo-selected-term').val();
			if (!taxonomy || !term_id) {
				return;
			}

			// Clear criteria rows and reload defaults
			$('#listeo-criteria-rows-tax').empty();
			this.loadTaxonomyCriteria(taxonomy, term_id);
		},

		renderCriteria: function (criteria, $container) {
			$container.empty();

			var index = 0;
			$.each(criteria, function (key, value) {
				var row = '<tr>' +
					'<td><input type="text" value="' + self.escapeHtml(value.label) + '" class="input-text options" name="label[' + index + ']" /></td>' +
					'<td><textarea name="tooltip[' + index + ']" rows="5">' + self.escapeHtml(value.tooltip || '') + '</textarea></td>' +
					'<td><a class="remove-row button" href="#">Remove</a></td>' +
					'</tr>';
				$container.append(row);
				index++;
			});
		},

		addCriteriaRow: function ($container) {
			var index = $container.find('tr').length;
			var row = '<tr>' +
				'<td><input type="text" class="input-text options" name="label[' + index + ']" /></td>' +
				'<td><textarea name="tooltip[' + index + ']" rows="5"></textarea></td>' +
				'<td><a class="remove-row button" href="#">Remove</a></td>' +
				'</tr>';
			$container.append(row);
		}
	};

	/**
	 * Helper: Escape HTML
	 */
	var self = {
		escapeHtml: function (text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text ? text.replace(/[&<>"']/g, function (m) { return map[m]; }) : '';
		}
	};

	// Initialize on document ready
	$(document).ready(function () {
		if ($('.listeo-reviews-criteria-advanced').length) {
			if ($('#listeo-type-selector').length) {
				TypeCriteriaManager.init();
			}
			if ($('#listeo-taxonomy-selector').length) {
				TaxonomyCriteriaManager.init();
			}
		}
	});

})(jQuery);
