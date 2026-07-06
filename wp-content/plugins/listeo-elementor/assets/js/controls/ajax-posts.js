(function ($) {
	'use strict';

	var AjaxPostsControl = elementor.modules.controls.BaseData.extend({
		onReady: function () {
			elementor.modules.controls.BaseData.prototype.onReady.apply(this, arguments);

			this.select = this.$el.find('select.listeo-ajax-posts-control');
			if (!this.select.length) {
				return;
			}

			this.multiple = !!this.select.data('multiple');
			this.queryAction = this.select.data('query-action') || this.select.data('queryAction');
			this.itemsAction = this.select.data('items-action') || this.select.data('itemsAction');
			this.placeholder = this.select.data('placeholder') || '';

			this.initSelect2();
		},

		getCurrentIds: function () {
			var value = this.getControlValue();
			if ($.isArray(value)) {
				return value;
			}
			if (value && value.length) {
				return [value];
			}
			return [];
		},

		initSelect2: function () {
			var self = this;
			var selectedIds = this.getCurrentIds();

			this.select.select2({
				placeholder: this.placeholder,
				allowClear: true,
				multiple: this.multiple,
				width: '100%',
				minimumInputLength: 1,
				templateResult: function (result) {
					return result.text;
				},
				templateSelection: function (selection) {
					return selection.text || selection.id;
				},
				ajax: {
					url: ListeoElementorControlPosts.ajax_url,
					dataType: 'json',
					delay: 250,
					cache: true,
					data: function (params) {
						return {
							action: self.queryAction,
							nonce: ListeoElementorControlPosts.nonce,
							q: params.term || '',
							page: params.page || 1
						};
					},
					processResults: function (data, params) {
						data = data || {};

						return {
							results: data.results || [],
							pagination: {
								more: data.pagination && data.pagination.more ? true : false
							}
						};
					}
				},
				language: {
					noResults: function () {
						return ListeoElementorControlPosts.l10n.no_results;
					}
				}
			}).on('change', function (event, data) {
				if (data && data.silent) {
					return;
				}
				var value = $(this).val() || [];
				if (!self.multiple) {
					value = value.length ? value[0] : '';
				}
				self.setValue(value);
			});

			if (selectedIds.length) {
				this.populateSelected(selectedIds);
			}
		},

		populateSelected: function (ids) {
			var self = this;
			$.ajax({
				type: 'GET',
				url: ListeoElementorControlPosts.ajax_url,
				dataType: 'json',
				data: {
					action: self.itemsAction,
					nonce: ListeoElementorControlPosts.nonce,
					ids: ids
				}
			}).done(function (response) {
				if (!response || !response.results) {
					return;
				}

				self.select.find('option').remove();

				response.results.forEach(function (item) {
					var option = new Option(item.text, item.id, true, true);
					self.select.append(option);
				});

				self.select.trigger('change', { silent: true });
			});
		},

		onBeforeDestroy: function () {
			if (this.select && this.select.data('select2')) {
				this.select.select2('destroy');
			}
		}
	});

	var registerControl = function () {
		if (!window.elementor || !elementor.addControlView) {
			return;
		}

		elementor.addControlView('listeo_ajax_posts', AjaxPostsControl);
	};

	// Try immediate registration
	if (window.elementor) {
		registerControl();
	} else {
		$(window).on('elementor:init', function() {
			registerControl();
		});
	}

})(jQuery);
