/**
 * Listeo Elementor Editor Scripts
 * Handles AJAX-based listing search for SELECT2 controls
 *
 * @since 2.0.11
 * @updated 2.0.12 - Optimized to only run for Listeo widgets
 * @updated 2.0.13 - Read saved values from model so previously stored listings render
 * @updated 2.0.14 - Rebuild options on title fetch so chips show real titles, not just IDs
 */
(function($) {
    'use strict';

    // Track initialized elements to prevent re-initialization
    var initializedElements = new WeakMap();

    // Debounce timer
    var initTimer = null;

    // Wait for Elementor editor to be ready
    $(window).on('elementor:init', function() {

        // Hook into control rendering - only for Listeo widgets
        elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view) {
            // Only process Listeo widgets
            var widgetType = model.get('widgetType') || '';
            if (widgetType.indexOf('listeo-') !== 0) {
                return;
            }

            // Debounce to prevent multiple rapid calls
            clearTimeout(initTimer);
            initTimer = setTimeout(function() {
                initAjaxSelect2();
            }, 150);
        });

        // Also init when section is opened - only if we're in a Listeo widget
        elementor.channels.editor.on('section:activated', function(sectionName, editor) {
            // Check if current widget is a Listeo widget
            var currentElement = elementor.getPanelView().getCurrentPageView();
            if (!currentElement || !currentElement.model) {
                return;
            }

            var widgetType = currentElement.model.get('widgetType') || '';
            if (widgetType.indexOf('listeo-') !== 0) {
                return;
            }

            // Debounce to prevent multiple rapid calls
            clearTimeout(initTimer);
            initTimer = setTimeout(function() {
                initAjaxSelect2();
            }, 150);
        });
    });

    function getCurrentSettings() {
        if (typeof elementor === 'undefined' || !elementor.getPanelView) {
            return null;
        }
        var panel = elementor.getPanelView();
        if (!panel || !panel.getCurrentPageView) {
            return null;
        }
        var view = panel.getCurrentPageView();
        if (!view || !view.model || !view.model.get) {
            return null;
        }
        return view.model.get('settings');
    }

    function getControlName($select) {
        var name = $select.attr('data-setting');
        if (name) {
            return name;
        }
        var $ctrl = $select.closest('[class*="elementor-control-"]');
        var match = ($ctrl.attr('class') || '').match(/elementor-control-([\w-]+)/);
        return match ? match[1] : null;
    }

    function buildSelect2($select) {
        $select.select2({
            ajax: {
                url: listeoElementor.ajaxUrl,
                dataType: 'json',
                delay: 250,
                method: 'POST',
                data: function(params) {
                    return {
                        action: 'listeo_elementor_search_listings',
                        q: params.term || '',
                        page: params.page || 1,
                        nonce: listeoElementor.nonce
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results,
                        pagination: {
                            more: data.pagination && data.pagination.more
                        }
                    };
                },
                cache: true
            },
            minimumInputLength: 0,
            placeholder: 'Search listings...',
            allowClear: true,
            multiple: true,
            width: '100%'
        });
    }

    function initAjaxSelect2() {
        // Find the include_posts and exclude_posts SELECT2 elements that haven't been initialized
        var $selects = $('.elementor-control-include_posts select.elementor-select2, .elementor-control-exclude_posts select.elementor-select2');

        $selects.each(function() {
            var $select = $(this);
            var element = this;

            // Skip if already initialized with AJAX using WeakMap
            if (initializedElements.has(element)) {
                return;
            }

            // Mark as initialized immediately to prevent race conditions
            initializedElements.set(element, true);

            // Saved values live on the Elementor model. The underlying <select> has no
            // <option> tags for them (control was registered with empty options), so
            // $select.val() returns []. Read from the model instead.
            var settingKey = getControlName($select);
            var settings = getCurrentSettings();
            var currentValues = [];

            if (settings && settingKey && typeof settings.get === 'function') {
                var modelVal = settings.get(settingKey);
                if (modelVal) {
                    currentValues = $.isArray(modelVal) ? modelVal.slice() : [modelVal];
                }
            }

            if (currentValues.length === 0) {
                currentValues = $select.val() || [];
            }

            // Normalize to strings for consistent comparisons
            currentValues = currentValues.map(function(v) { return String(v); });

            // Destroy existing select2 instance and any leftover <option> tags
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            $select.find('option').remove();

            // Pre-populate placeholder <option> tags so select2 has something to render
            // for saved IDs while titles load. Real titles replace these on AJAX success.
            currentValues.forEach(function(val) {
                $select.append(new Option('#' + val, val, true, true));
            });

            buildSelect2($select);

            // Fetch real titles for the saved IDs and replace the placeholder options.
            // We rebuild the <option> tags rather than mutating .text(): select2 caches
            // chip text on the option element itself (via Utils.StoreData), so changing
            // .text() in place leaves the chip stuck at the placeholder. Removing the
            // node clears the cache; re-adding with fresh text + reinit re-renders chips.
            if (currentValues.length > 0) {
                $.ajax({
                    url: listeoElementor.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'listeo_elementor_get_listing_titles',
                        ids: currentValues,
                        nonce: listeoElementor.nonce
                    },
                    success: function(response) {
                        if (!response || !response.results || response.results.length === 0) {
                            return;
                        }
                        var titleMap = {};
                        response.results.forEach(function(item) {
                            titleMap[String(item.id)] = item.text;
                        });

                        // Rebuild options + select2 so chips pick up real titles
                        $select.select2('destroy');
                        $select.find('option').remove();
                        currentValues.forEach(function(val) {
                            var text = titleMap[val] || ('#' + val);
                            $select.append(new Option(text, val, true, true));
                        });
                        buildSelect2($select);
                    }
                });
            }
        });
    }

})(jQuery);
