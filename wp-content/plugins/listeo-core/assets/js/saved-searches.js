/**
 * Listeo Saved Searches JavaScript
 *
 * @package Listeo_Core
 * @since 2.0.23
 */

(function($) {
    'use strict';

    var ListeoSavedSearches = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Save search button on search results page
            $(document).on('click', '.listeo-save-search-btn', this.handleSaveSearch);

            // Toggle alerts button in dashboard
            $(document).on('click', '.listeo-toggle-alerts', this.handleToggleAlerts);

            // Delete saved search button in dashboard
            $(document).on('click', '.listeo-delete-saved-search', this.handleDeleteSearch);

            // Modal cancel button
            $(document).on('click', '.save-search-cancel', this.closeModal);

            // Modal save button
            $(document).on('click', '.save-search-submit', this.handleModalSave);

            // Handle enter key in modal input
            $(document).on('keypress', '#save-search-name', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    ListeoSavedSearches.handleModalSave();
                }
            });
        },

        /**
         * Collect all search criteria from the search form and URL
         */
        collectSearchCriteria: function() {
            var criteria = {};
            var $form = $('#listeo_core-search-form');

            // Params that should only be captured from visible form fields, not URL
            var formOnlyParams = ['_listing_type', 'listing_type'];

            // First, get criteria from URL parameters (for non-AJAX searches)
            var urlParams = new URLSearchParams(window.location.search);
            urlParams.forEach(function(value, key) {
                if (value && value.trim() !== '' && formOnlyParams.indexOf(key) === -1) {
                    criteria[key] = value;
                }
            });

            // Then, collect from form fields (overrides URL params for current state)
            if ($form.length) {
                // Get all form inputs including disabled ones (some sliders use disabled when inactive)
                $form.find('input, select, textarea').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    var value = '';

                    if (!name || name === 'action') {
                        return; // Skip fields without name or action field
                    }

                    // Skip hidden _listing_type fields (pre-set by page, not user selected)
                    if (name === '_listing_type' && $field.attr('type') === 'hidden') {
                        return;
                    }

                    // Skip disabled fields unless they're special cases
                    var isDisabled = $field.prop('disabled');
                    var isSpecialDisabled = $field.hasClass('bootstrap-range-slider') ||
                                            $field.hasClass('drilldown-values');

                    if (isDisabled && !isSpecialDisabled) {
                        return;
                    }

                    // Handle different input types
                    if ($field.is(':checkbox')) {
                        if ($field.is(':checked')) {
                            value = $field.val();
                            // Handle array-style names like tax-listing_feature[slug]
                            if (name.indexOf('[') > -1) {
                                var baseName = name.replace(/\[.*\]/, '');
                                if (!criteria[baseName]) {
                                    criteria[baseName] = [];
                                }
                                if (Array.isArray(criteria[baseName])) {
                                    criteria[baseName].push(value);
                                }
                                return;
                            }
                        } else {
                            return; // Skip unchecked checkboxes
                        }
                    } else if ($field.is(':radio')) {
                        if ($field.is(':checked')) {
                            value = $field.val();
                            // Skip "any" values for rating filter
                            if (value === 'any') {
                                return;
                            }
                        } else {
                            return; // Skip unchecked radios
                        }
                    } else if ($field.is('select')) {
                        value = $field.val();
                        // Handle multi-select
                        if (Array.isArray(value)) {
                            value = value.filter(function(v) { return v && v.trim() !== ''; });
                            if (value.length === 0) {
                                return;
                            }
                        }
                    } else {
                        value = $field.val();
                    }

                    // Handle range slider fields (value like "5,899")
                    // These have names like _price_range, _bedrooms_range, etc.
                    if (name && name.indexOf('_range') > -1 && value && value.indexOf(',') > -1) {
                        var rangeValues = value.split(',');
                        if (rangeValues.length === 2) {
                            var minVal = parseFloat(rangeValues[0]);
                            var maxVal = parseFloat(rangeValues[1]);
                            var sliderMin = parseFloat($field.data('slider-min'));
                            var sliderMax = parseFloat($field.data('slider-max'));

                            // Only save if values differ from slider defaults
                            if (!isNaN(minVal) && !isNaN(maxVal) && (minVal !== sliderMin || maxVal !== sliderMax)) {
                                // For _price_range, store as price_min/price_max
                                if (name === '_price_range') {
                                    criteria['price_min'] = minVal;
                                    criteria['price_max'] = maxVal;
                                } else {
                                    // For other range sliders, store the base name with min/max suffix
                                    var baseName = name.replace('_range', '');
                                    criteria[baseName + '_min'] = minVal;
                                    criteria[baseName + '_max'] = maxVal;
                                }
                            }
                        }
                        return; // Don't save raw range value
                    }

                    // Handle drilldown hidden inputs with array notation
                    if ($field.hasClass('drilldown-values') || (name.indexOf('[]') > -1 && $field.attr('type') === 'hidden')) {
                        if (value && value.trim() !== '') {
                            var baseName = name.replace(/\[\]$/, '');
                            if (!criteria[baseName]) {
                                criteria[baseName] = [];
                            }
                            if (Array.isArray(criteria[baseName])) {
                                criteria[baseName].push(value);
                            } else {
                                criteria[baseName] = [criteria[baseName], value];
                            }
                        }
                        return;
                    }

                    // Only add non-empty values
                    if (value && (typeof value === 'string' ? value.trim() !== '' : value.length > 0)) {
                        // Clean up name (remove brackets for simple values)
                        var cleanName = name.replace(/\[\]$/, '');
                        criteria[cleanName] = value;
                    }
                });

                // Special handling for drilldown menus - get selected values from data
                $form.find('.drilldown-menu').each(function() {
                    var $menu = $(this);
                    var menuName = $menu.data('name');
                    var $selectedInputs = $menu.find('.drilldown-values');
                    var values = [];

                    $selectedInputs.each(function() {
                        var val = $(this).val();
                        if (val && val.trim() !== '') {
                            values.push(val);
                        }
                    });

                    if (values.length > 0 && menuName) {
                        criteria[menuName] = values.join(',');
                    }
                });
            }

            // Also check sidebar search form if exists
            var $sidebarForm = $('.listeo-sidebar-search-form, .search-sidebar form');
            if ($sidebarForm.length && $sidebarForm.attr('id') !== 'listeo_core-search-form') {
                $sidebarForm.find('input, select').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    var value = '';

                    if (!name) return;

                    if ($field.is(':checkbox') && $field.is(':checked')) {
                        value = $field.val();
                    } else if ($field.is(':radio') && $field.is(':checked')) {
                        value = $field.val();
                        if (value === 'any') return;
                    } else if ($field.is('select')) {
                        value = $field.val();
                    } else if (!$field.is(':checkbox') && !$field.is(':radio')) {
                        value = $field.val();
                    }

                    if (value && value.trim && value.trim() !== '') {
                        criteria[name] = value;
                    }
                });
            }

            // Convert array values to comma-separated strings for storage
            for (var key in criteria) {
                if (Array.isArray(criteria[key])) {
                    criteria[key] = criteria[key].join(',');
                }
            }

            // Parameters to exclude (internal/map/temporary)
            var excludeParams = [
                'action',
                'page',
                'paged',
                'map_bounds',
                'search_by_map_move',
                'search_lat',
                'search_lng',
                'listeo_core_order',
                '_price_range' // Exclude raw range values
            ];

            // Remove empty, undefined values and excluded params
            var cleanCriteria = {};
            for (var k in criteria) {
                // Skip excluded params
                if (excludeParams.indexOf(k) > -1) {
                    continue;
                }
                // Skip map_bounds array keys
                if (k.indexOf('map_bounds') > -1) {
                    continue;
                }
                // Skip range slider raw values
                if (k.indexOf('_range') > -1) {
                    continue;
                }
                if (criteria[k] && criteria[k] !== '' && criteria[k] !== 'undefined') {
                    cleanCriteria[k] = criteria[k];
                }
            }

            return cleanCriteria;
        },

        /**
         * Build search URL from criteria
         */
        buildSearchUrl: function(criteria) {
            // Use the listings archive URL to prevent 404s on custom pages
            var baseUrl = listeoSavedSearches.listings_url || (window.location.origin + window.location.pathname);
            var params = new URLSearchParams();

            // If there's a location search, force search_radius=0 to use text-based search
            // This prevents issues with radius/geocoding on direct URL visits
            var hasLocation = criteria.location_search || criteria.search_location;

            for (var key in criteria) {
                if (criteria.hasOwnProperty(key) && criteria[key]) {
                    // Skip search_radius if we have location (we'll add it as 0)
                    if (key === 'search_radius' && hasLocation) {
                        continue;
                    }
                    params.append(key, criteria[key]);
                }
            }

            // Add search_radius=0 if location search is present
            if (hasLocation) {
                params.append('search_radius', '0');
            }

            var queryString = params.toString();
            return queryString ? baseUrl + '?' + queryString : baseUrl;
        },

        /**
         * Store current search data for modal
         */
        currentSearchData: null,

        /**
         * Handle save search button click
         */
        handleSaveSearch: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var isLoggedIn = $btn.data('logged-in') === 1 || $btn.data('logged-in') === '1';

            // If not logged in, trigger login popup
            if (!isLoggedIn) {
                // Try to open login popup using Magnific Popup
                if (typeof $.magnificPopup !== 'undefined' && $('#sign-in-dialog').length) {
                    $.magnificPopup.open({
                        items: {
                            src: '#sign-in-dialog',
                            type: 'inline'
                        },
                        fixedContentPos: true,
                        fixedBgPos: true,
                        overflowY: 'auto',
                        closeBtnInside: true,
                        preloader: false,
                        midClick: true,
                        removalDelay: 300,
                        mainClass: 'my-mfp-zoom-in'
                    });
                } else {
                    // Fallback: redirect to login page
                    var loginUrl = listeoSavedSearches.login_url || '/wp-login.php';
                    window.location.href = loginUrl + '?redirect_to=' + encodeURIComponent(window.location.href);
                }
                return;
            }

            // Check if already processing
            if ($btn.hasClass('processing')) {
                return;
            }

            // Collect current search criteria from form and URL
            var searchCriteria = ListeoSavedSearches.collectSearchCriteria();
            var searchUrl = ListeoSavedSearches.buildSearchUrl(searchCriteria);

            // If no meaningful criteria, use fallback from button data
            if (Object.keys(searchCriteria).length === 0) {
                searchCriteria = $btn.data('criteria') || {};
                searchUrl = $btn.data('url') || window.location.href;
            }

            // Store data for modal submission
            ListeoSavedSearches.currentSearchData = {
                criteria: searchCriteria,
                url: searchUrl,
                $btn: $btn
            };

            // Clear previous input
            $('#save-search-name').val('');

            // Open modal using Magnific Popup
            if (typeof $.magnificPopup !== 'undefined') {
                $.magnificPopup.open({
                    items: {
                        src: '#save-search-dialog',
                        type: 'inline'
                    },
                    fixedContentPos: true,
                    fixedBgPos: true,
                    overflowY: 'auto',
                    closeBtnInside: true,
                    preloader: false,
                    midClick: true,
                    removalDelay: 300,
                    mainClass: 'my-mfp-zoom-in',
                    callbacks: {
                        open: function() {
                            // Focus input after modal opens
                            setTimeout(function() {
                                $('#save-search-name').focus();
                            }, 100);
                        }
                    }
                });
            }
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e) e.preventDefault();
            if (typeof $.magnificPopup !== 'undefined') {
                $.magnificPopup.close();
            }
        },

        /**
         * Handle modal save button click
         */
        handleModalSave: function(e) {
            if (e) e.preventDefault();

            var searchName = $('#save-search-name').val().trim();
            var i18n = listeoSavedSearches.i18n;
            var data = ListeoSavedSearches.currentSearchData;

            if (!searchName) {
                $('#save-search-name').addClass('input-error').focus();
                return;
            }

            $('#save-search-name').removeClass('input-error');

            if (!data) {
                ListeoSavedSearches.closeModal();
                return;
            }

            var $submitBtn = $('.save-search-submit');
            var originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text(i18n.saving || 'Saving...');

            $.ajax({
                url: listeoSavedSearches.ajaxurl,
                type: 'POST',
                data: {
                    action: 'listeo_save_search',
                    nonce: listeoSavedSearches.nonce,
                    search_name: searchName,
                    search_url: data.url,
                    search_criteria: JSON.stringify(data.criteria)
                },
                success: function(response) {
                    if (response.success) {
                        // Update button state
                        if (data.$btn) {
                            var $icon = data.$btn.find('i');
                            var $text = data.$btn.find('span');
                            $icon.removeClass('sl-icon-bell').addClass('sl-icon-check');
                            $text.text(i18n.saved || 'Saved!');
                            setTimeout(function() {
                                $icon.removeClass('sl-icon-check').addClass('sl-icon-bell');
                                $text.text(i18n.save_search || 'Save Search');
                            }, 2000);
                        }
                        ListeoSavedSearches.closeModal();
                    } else {
                        alert(response.data.message || i18n.error);
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert(i18n.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                },
                complete: function() {
                    ListeoSavedSearches.currentSearchData = null;
                }
            });
        },

        /**
         * Handle toggle alerts button click
         */
        handleToggleAlerts: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var searchId = $btn.data('search-id');
            var currentEnabled = $btn.data('enabled') === 1 || $btn.data('enabled') === '1';
            var newEnabled = !currentEnabled;

            $btn.prop('disabled', true);

            $.ajax({
                url: listeoSavedSearches.ajaxurl,
                type: 'POST',
                data: {
                    action: 'listeo_toggle_search_alerts',
                    nonce: listeoSavedSearches.nonce,
                    search_id: searchId,
                    enabled: newEnabled ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        // Update button state
                        $btn.data('enabled', newEnabled ? 1 : 0);

                        if (newEnabled) {
                            $btn.removeClass('alerts-disabled').addClass('alerts-enabled');
                            $btn.find('i').removeClass('fa-bell-slash').addClass('fa-bell');
                            $btn.attr('title', listeoSavedSearches.i18n.alerts_enabled || 'Email alerts enabled - click to disable');
                        } else {
                            $btn.removeClass('alerts-enabled').addClass('alerts-disabled');
                            $btn.find('i').removeClass('fa-bell').addClass('fa-bell-slash');
                            $btn.attr('title', listeoSavedSearches.i18n.alerts_disabled || 'Email alerts disabled - click to enable');
                        }
                    } else {
                        alert(response.data.message || 'Error updating alerts setting');
                    }
                    $btn.prop('disabled', false);
                },
                error: function() {
                    alert('Error updating alerts setting');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Handle delete saved search button click
         */
        handleDeleteSearch: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var searchId = $btn.data('search-id');
            var $item = $btn.closest('.saved-search-item');

            if (!confirm(listeoSavedSearches.i18n.delete_confirm)) {
                return;
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: listeoSavedSearches.ajaxurl,
                type: 'POST',
                data: {
                    action: 'listeo_delete_saved_search',
                    nonce: listeoSavedSearches.nonce,
                    search_id: searchId
                },
                success: function(response) {
                    if (response.success) {
                        // Animate and remove the item
                        $item.slideUp(300, function() {
                            $item.remove();

                            // Update count
                            var $count = $('.saved-searches-count');
                            if ($count.length) {
                                var countText = $count.text();
                                var match = countText.match(/\((\d+)\/(\d+)\)/);
                                if (match) {
                                    var current = parseInt(match[1]) - 1;
                                    var max = match[2];
                                    $count.text('(' + current + '/' + max + ')');
                                }
                            }

                            // Show empty state if no more searches
                            if ($('.saved-search-item').length === 0) {
                                $('.saved-searches-list').replaceWith(
                                    '<div class="notification notice">' +
                                    '<p><span>' + (listeoSavedSearches.i18n.no_searches || 'No saved searches!') + '</span> ' +
                                    (listeoSavedSearches.i18n.no_searches_desc || 'You haven\'t saved any searches yet.') +
                                    '</p></div>'
                                );
                            }
                        });
                    } else {
                        alert(response.data.message || 'Error deleting search');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error deleting search');
                    $btn.prop('disabled', false);
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ListeoSavedSearches.init();
    });

})(jQuery);
