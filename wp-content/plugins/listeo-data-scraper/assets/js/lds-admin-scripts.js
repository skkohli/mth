// --- Inline SVG icon helper (mirrors lds_get_inline_svg_icon in PHP).
//     Defined at file scope so every jQuery(document).ready() block can use it. ---
var LDS_ICON_PATHS = {
    check: '<path d="M20 6 9 17l-5-5"></path>',
    x: '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
    star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>',
    phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>',
    globe: '<circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path>',
    bot: '<path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path>',
    chart: '<path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>',
    info: '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>',
    alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>'
};
function ldsIcon(name, extraClass) {
    var raw = LDS_ICON_PATHS[name];
    if (!raw) { return ''; }
    var cls = 'lds-inline-icon' + (extraClass ? ' ' + extraClass : '');
    return '<span class="' + cls + '" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">' + raw + '</svg></span>';
}

jQuery(document).ready(function($) {
    // --- Global variables for the import process ---
    let placeIdQueue = [];
    let googleDataQueue = [];
    let jobQueue = [];
    let totalPlaces = 0;
    let processedPlaces = 0;
    let totalJobs = 0;
    let processedJobs = 0;
    let $resultsDiv = $('#lds-import-results');
    let selectedCategoryIds = []; // Category multi-select
    let selectedCategoryTaxonomy = ''; // NEW - store category taxonomy
    let selectedRegionIds = []; // Region multi-select
    let selectedRegionTaxonomy = ''; // NEW
    let selectedListingType = ''; // NEW
	    let selectedFeatureIds = []; // Features multi-select
	    let currentPhase = '';
	    let manualSelectionEnabled = false; // NEW
	    let searchSummaryHtml = '';

    // --- Auto-scroll that follows import progress but yields to the user ---
    // `autoFollow` stays true only while the view is at the bottom of the results
    // card. Genuine user gestures (wheel / touch / scroll keys) re-evaluate it, so
    // once the user scrolls away the script stops following; scrolling back to the
    // bottom re-engages it. Our own programmatic scrolls never flip it.
    let autoFollow = true;

    function importCardBottomGap() {
        const el = document.getElementById('lds-import-results');
        if (!el) { return null; }
        const rect = el.getBoundingClientRect();
        if (rect.height <= 0) { return null; }
        return (rect.bottom + window.pageYOffset) - (window.pageYOffset + window.innerHeight);
    }

    function scrollToCurrentStep(smooth) {
        if (!autoFollow) { return; }
        const gap = importCardBottomGap();
        if (gap === null) { return; }
        window.scrollTo({
            top: Math.max(0, window.pageYOffset + gap + 24),
            behavior: smooth ? 'smooth' : 'auto'
        });
    }

    function reevaluateAutoFollow() {
        const gap = importCardBottomGap();
        if (gap !== null) { autoFollow = gap < 100; }
    }
    ['wheel', 'touchmove'].forEach(function(evt) {
        window.addEventListener(evt, function() { setTimeout(reevaluateAutoFollow, 60); }, { passive: true });
    });
    window.addEventListener('keydown', function(e) {
        if (['PageUp', 'PageDown', 'ArrowUp', 'ArrowDown', 'Home', 'End', ' ', 'Spacebar'].indexOf(e.key) !== -1) {
            setTimeout(reevaluateAutoFollow, 60);
        }
    }, { passive: true });

    // Continuously tail-follow new content (phases, log lines, progress) while engaged,
    // throttled to one scroll per frame.
    (function setupImportAutoScroll() {
        const container = document.getElementById('lds-import-results');
        if (!container || typeof MutationObserver === 'undefined') { return; }
        let queued = false;
        new MutationObserver(function() {
            if (queued) { return; }
            queued = true;
            requestAnimationFrame(function() {
                queued = false;
                scrollToCurrentStep(false);
            });
        }).observe(container, { childList: true, subtree: true });
    })();

    // Custom searchable category dropdown needs JS validation; native required can block hidden selects.
	    function handleCategoryValidation() {
	        const categorySelect = $('#lds_category');
	        if (categorySelect.length) {
	            categorySelect.removeAttr('required');
	        }
	    }

	    function getSelectedCategoryIds() {
	        const value = $('#lds_category').val();

	        if (Array.isArray(value)) {
	            return value.filter(function(item) {
	                return item !== null && String(item).trim() !== '';
	            });
	        }

	        return value ? [value] : [];
	    }

	    function categorySelectionRequired() {
	        const categorySelect = $('#lds_category');
	        if (!categorySelect.length) {
	            return false;
	        }

	        return categorySelect.find('option').filter(function() {
	            return String($(this).val()).trim() !== '';
	        }).length > 0;
	    }

	    function showCategoryRequiredMessage() {
	        const $button = $('#lds-proceed-selected:visible').length
	            ? $('#lds-proceed-selected:visible')
	            : $('#lds-import-form .lds-submit-button[type="submit"]');
	        const $submitArea = $button.closest('.lds-submit-area');
	        const $target = $submitArea.length ? $submitArea : $('#lds-import-form');
	        let $message = $target.find('.lds-category-required-message');

	        if ($message.length === 0) {
	            $message = $('<div class="lds-category-required-message" role="alert" style="color: #d63638; font-size: 13px; margin-top: 8px; font-weight: 600;"></div>');
	            $target.append($message);
	        }

	        clearTimeout($message.data('hideTimer'));
	        $message.stop(true, true).text(ldsText('missing_category', 'Category is required')).show();
	        $message.data('hideTimer', setTimeout(function() {
	            $message.fadeOut(200, function() {
	                $(this).remove();
	            });
	        }, 5000));
	    }

	    function validateCategorySelection() {
	        if (!categorySelectionRequired() || getSelectedCategoryIds().length > 0) {
	            return true;
	        }

	        showCategoryRequiredMessage();
	        $('#lds_category').trigger('focus');
	        return false;
	    }
	
		    // Initialize category validation
		    handleCategoryValidation();

	    function ldsText(key, fallback) {
	        if (typeof lds_admin_vars !== 'undefined' && lds_admin_vars.i18n && lds_admin_vars.i18n[key]) {
	            return lds_admin_vars.i18n[key];
	        }

	        return fallback;
	    }

	    function getApiErrorDataFromXhr(xhr) {
	        if (!xhr || !xhr.responseText) {
	            return null;
	        }

	        try {
	            const response = JSON.parse(xhr.responseText);
	            return response.data || null;
	        } catch (e) {
	            return null;
	        }
	    }

	    function buildApiDiagnosticsHtml(diagnostics) {
	        if (!Array.isArray(diagnostics) || diagnostics.length === 0) {
	            return '';
	        }

	        let html = '<div class="lds-error-solution" style="margin-top: 8px;">';
	        html += '<strong>' + escapeHtml(ldsText('details', 'Details')) + ':</strong>';
	        html += '<ul style="margin: 6px 0 0 18px;">';

	        diagnostics.forEach(function(item) {
	            if (!item || !item.label || !item.value) {
	                return;
	            }

	            html += '<li><strong>' + escapeHtml(item.label) + ':</strong> ' + escapeHtml(item.value) + '</li>';
	        });

	        html += '</ul></div>';
	        return html;
	    }

	    function buildSearchSummaryHtml(summary) {
	        if (!Array.isArray(summary) || summary.length === 0) {
	            return '';
	        }

	        const readyItem = summary.find(function(item) {
	            return item && item.key === 'ready_to_import';
	        });
	        const secondaryItems = summary.filter(function(item) {
	            return item && item.key !== 'ready_to_import';
	        });

	        let html = '<div class="lds-search-summary" style="margin: 8px 0 12px 0; padding: 12px 14px; background: #f5f5f5; border: none; border-radius: 8px;">';

	        if (readyItem && readyItem.value !== undefined && readyItem.value !== null) {
	            html += '<div style="font-size: 14px;"><strong>' + escapeHtml(String(readyItem.value)) + ' ' + escapeHtml(String(readyItem.label)) + '</strong></div>';
	        }

	        if (secondaryItems.length > 0) {
	            html += '<div style="margin-top: 4px; font-size: 12px; color: #646970;">';
	        }

	        secondaryItems.forEach(function(item, index) {
	            if (!item || item.label === undefined || item.label === null || item.value === undefined || item.value === null) {
	                return;
	            }

	            if (index > 0) {
	                html += ' · ';
	            }

	            if (item.key === 'import_limit') {
	                html += escapeHtml(String(item.label)) + ': ' + escapeHtml(String(item.value));
	            } else {
	                html += escapeHtml(String(item.value)) + ' ' + escapeHtml(String(item.label));
	            }
	        });

	        if (secondaryItems.length > 0) {
	            html += '</div>';
	        }

	        html += '</div>';
	        return html;
	    }

	    function buildApiTechnicalDetailsHtml(data, labelKey) {
	        const hasDiagnostics = Array.isArray(data?.diagnostics) && data.diagnostics.length > 0;
	        if (!hasDiagnostics && !data?.technical_message && !data?.detailed_message) {
	            return '';
	        }

	        let html = '<details class="lds-api-technical-details" style="margin-top: 8px;">';
	        html += '<summary style="cursor: pointer;">' + escapeHtml(ldsText('show_technical_details', 'Show technical details')) + '</summary>';

	        if (data?.technical_message) {
	            html += '<p style="margin: 8px 0 0 0;"><strong>' + escapeHtml(ldsText('technical_message', 'Technical message')) + ':</strong> ' + escapeHtml(data.technical_message) + '</p>';
	        }

	        html += buildApiDiagnosticsHtml(data?.diagnostics);

	        if (data?.detailed_message) {
	            html += '<div class="lds-error-solution"><strong>' + escapeHtml(ldsText(labelKey || 'troubleshooting', 'Troubleshooting')) + ':</strong> ' + escapeHtml(data.detailed_message) + '</div>';
	        }

	        html += '</details>';
	        return html;
	    }

	    function buildApiActionLinkHtml(data) {
	        if (!data?.action_url) {
	            return '';
	        }

	        let actionUrl = '';
	        try {
	            const parsedUrl = new URL(data.action_url, window.location.origin);
	            if (parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:') {
	                actionUrl = parsedUrl.href;
	            }
	        } catch (e) {
	            actionUrl = '';
	        }

	        if (!actionUrl) {
	            return '';
	        }

	        const actionLabel = data.action_label || data.action_url;
	        return '<p style="margin: 8px 0 0 0;"><a class="button button-secondary" target="_blank" rel="noopener" href="' + escapeHtml(actionUrl) + '">' + escapeHtml(actionLabel) + '</a></p>';
	    }

	    function buildApiWarningNotice(data) {
	        const message = data?.message || '';
	        let warningHtml = '<div class="notice notice-warning is-dismissible">';
	        warningHtml += '<p style="margin: 0 0 10px 0;"><strong>' + escapeHtml(ldsText('warning', 'Warning')) + ':</strong> ' + escapeHtml(message) + '</p>';
	        warningHtml += buildApiActionLinkHtml(data);
	        warningHtml += buildSearchSummaryHtml(data?.summary);
	        warningHtml += buildApiTechnicalDetailsHtml(data, 'troubleshooting');
	        warningHtml += '</div>';
	        return warningHtml;
	    }
	
	    function buildApiErrorNotice(data, fallbackMessage) {
	        const apiName = data?.api_name || 'Google';
	        const message = data?.message || fallbackMessage;
	        const isApiKeyError = data?.type === 'api_key_error';
	        let errorHtml = '<div class="notice notice-error is-dismissible' + (isApiKeyError ? ' lds-api-key-error' : '') + '">';

	        if (isApiKeyError) {
	            errorHtml += '<h4>' + escapeHtml(apiName + ' ' + ldsText('api_key_problem_detected', 'API Key Problem Detected')) + '</h4>';
	        }

	        errorHtml += '<p style="margin: 0 0 10px 0;"><strong>' + escapeHtml(isApiKeyError ? ldsText('error', 'Error') : apiName + ' ' + ldsText('api_error', 'API Error')) + ':</strong> ' + escapeHtml(message) + '</p>';
	        errorHtml += buildApiActionLinkHtml(data);
	        errorHtml += buildApiTechnicalDetailsHtml(data, isApiKeyError ? 'how_to_fix' : 'troubleshooting');

	        if (isApiKeyError) {
	            errorHtml += '<div class="lds-error-tip"><strong>' + escapeHtml(ldsText('tip', 'Tip')) + ':</strong> ' + escapeHtml(ldsText('tip_test_api_key', 'Use the "Test API Key" button in Settings to verify your key is working correctly.')) + '</div>';
	        }

	        errorHtml += '</div>';
	        return errorHtml;
	    }

	    function buildApiErrorLogItem(data, fallbackMessage, critical) {
	        const message = data?.message || fallbackMessage;
	        let errorHtml = '<li' + (critical ? ' class="lds-critical-error"' : ' style="background: #f8d7da; color: #721c24;"') + '>';

	        if (critical) {
	            const apiName = data?.api_name || 'Google';
	            errorHtml += '<strong>' + escapeHtml(apiName + ' ' + ldsText('api_key_problem_detected', 'API Key Problem Detected')) + '</strong><br>';
	        }

	        errorHtml += '<span style="font-size: 12px;">' + escapeHtml(message) + '</span>';
	        errorHtml += buildApiTechnicalDetailsHtml(data, critical ? 'how_to_fix' : 'troubleshooting');

	        if (critical) {
	            errorHtml += '<br><br><em>' + escapeHtml(ldsText('import_stopped', 'Import stopped. Please fix your API key and try again.')) + '</em>';
	        }

	        errorHtml += '</li>';
	        return errorHtml;
	    }
	
	    // --- Enhanced AJAX function with better error handling ---
	    function makeAjaxRequest(action, data, successCallback, errorCallback) {
        const requestData = {
            action: action,
            nonce: $('#lds_nonce').val(),
            ...data
        };

        console.log('Making AJAX request:', action, requestData);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            dataType: 'json',
            timeout: 120000, // 120 second timeout
            success: function(response) {
                console.log('AJAX Success:', action, response);
                if (successCallback) successCallback(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    action: action,
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                let errorMessage = 'Server error occurred';
                if (status === 'timeout') {
                    errorMessage = 'The request timed out. Please try again.';
                } else if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMessage = errorData.data?.message || errorData.error || errorMessage;
                    } catch (e) {
                        if (xhr.responseText.includes('Fatal error')) {
                           errorMessage = 'A fatal PHP error occurred on the server. Check the server error logs.';
                        } else {
                           errorMessage = xhr.responseText.substring(0, 200) + '...';
                        }
                    }
                }
                
                if (errorCallback) {
                    errorCallback(errorMessage, xhr);
                } else {
                    $('#lds-results-body').append('<div class="notice notice-error"><p>Error in ' + action + ': ' + errorMessage + '</p></div>');
                }
            }
        });
    }

    // --- Main form submission handler ---
    $('#lds-import-form').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $spinner = $form.find('.lds-spinner');
        const $submitButton = $form.find('.lds-submit-button');

        const businessType = $('#lds_query').val().trim();
        const location = $('#lds_location').val().trim();
	        const categoryIds = getSelectedCategoryIds(); // Category multi-select - array of term IDs
        const categoryTaxonomy = $('#lds_category_taxonomy').val() || 'listing_category'; // NEW - get category taxonomy
        const regionIds = $('#lds_region').val() || []; // Region multi-select - array of term IDs
        const regionTaxonomy = $('#lds_region_taxonomy').val() || 'region'; // NEW - get detected taxonomy, default back to region
        const listingType = $('#lds_listing_type').val(); // NEW
        const featureIds = $('#lds_features').val() || []; // Features multi-select - array of term IDs
        manualSelectionEnabled = $('#lds_manual_selection').is(':checked'); // NEW

        // Get search mode and map data
        const searchMode = $('#lds-search-mode').val() || 'text';
        const lat = $('#lds-lat').val();
        const lng = $('#lds-lng').val();

        // Validation based on search mode
        if (searchMode === 'map') {
            if (!businessType || !listingType || !lat || !lng) {
                alert('Please fill out all required fields and select a location on the map.');
                return;
            }
	            if (!validateCategorySelection()) {
	                return;
	            }
	        } else {
            if (!businessType || !location || !listingType) {
                alert('Please fill out all required fields: Business Type, Location, and Listing Type.');
                return;
            }
	            if (!validateCategorySelection()) {
	                return;
	            }
	        }

        const combinedQuery = businessType + (searchMode === 'map' ? ' near ' : ' in ') + location;

        // Reset state for a new import
        placeIdQueue = [], googleDataQueue = [], jobQueue = [], totalPlaces = 0, processedPlaces = 0, totalJobs = 0, processedJobs = 0;
        searchSummaryHtml = '';
        selectedCategoryIds = categoryIds; // Category multi-select
        selectedCategoryTaxonomy = categoryTaxonomy; // NEW - store category taxonomy
        selectedRegionIds = regionIds; // Region multi-select
        selectedRegionTaxonomy = regionTaxonomy; // NEW
        selectedListingType = listingType; // NEW
        selectedFeatureIds = featureIds; // Features multi-select

        $submitButton.find('.button-text').text('Run Import');
        $spinner.addClass('is-active');
        $submitButton.prop('disabled', true);

        // --- NEW: Create the main results card structure ---
        // Add manual-check class if manual selection is enabled
        const manualCheckClass = manualSelectionEnabled ? ' manual-check' : '';
        $resultsDiv.html(
            '<div class="lds-results-card' + manualCheckClass + '">' +
                '<h3 class="lds-results-header">' + ldsIcon('activity') + 'Import Progress</h3>' +
                '<div class="lds-card-body" id="lds-results-body">' +
                    '<h4 class="lds-step-header">Searching for new listings of "' + escapeHtml(businessType) + '"...</h4>' +
                '</div>' +
            '</div>'
        );
        const $resultsBody = $('#lds-results-body');

        // AJAX Call 1: Get unique, new Place IDs
        const requestData = {
            query: combinedQuery,
            lds_search_mode: searchMode
        };

        // Add map-specific data if in map mode
        if (searchMode === 'map') {
            requestData.lds_lat = lat;
            requestData.lds_lng = lng;
            requestData.lds_location = location; // Add location for backend address handling
        }

        makeAjaxRequest('lds_get_place_ids', requestData, function(response) {
		            if (response.success && response.data.place_ids && response.data.place_ids.length > 0) {
		                placeIdQueue = response.data.place_ids;
		                totalPlaces = placeIdQueue.length;
			                const warningHtml = response.data.warning ? buildApiWarningNotice(response.data.warning) : '';
			                const summaryHtml = !response.data.warning && response.data.summary ? buildSearchSummaryHtml(response.data.summary) : '';
			                searchSummaryHtml = warningHtml + summaryHtml;
			                
			                $resultsBody.html(
			                    searchSummaryHtml +
			                    '<h4 class="lds-step-header">Found ' + totalPlaces + ' listings. Fetching details...</h4>' +
	                    '<div class="lds-progress-wrapper"><div id="lds-fetch-progress" style="background-color: #2196F3;">0%</div></div>' +
	                    '<ul id="lds-google-list" class="lds-log-area" style="max-height: 250px;"></ul>'
                );
                
                scrollToCurrentStep(true);
                processNextGoogleFetch();
	            } else {
	                let message = response.data?.message || 'No new places found. Try a different search term or location.';
	                let errorType = response.data?.type || '';
	                
	                // Enhanced error display for API issues
	                if (errorType === 'api_key_error' || errorType === 'api_error') {
	                    let errorHtml = buildApiErrorNotice(response.data, message);
	                    $resultsBody.html(errorHtml);
	                } else {
	                    let warningHtml = '<div class="notice notice-warning is-dismissible">';
	                    warningHtml += '<p>' + escapeHtml(message) + '</p>';

	                    if (response.data?.warning) {
	                        warningHtml += buildApiActionLinkHtml(response.data.warning);
	                        warningHtml += buildSearchSummaryHtml(response.data.warning.summary);
	                        warningHtml += buildApiTechnicalDetailsHtml(response.data.warning, 'troubleshooting');
	                    } else if (response.data?.summary) {
	                        warningHtml += buildSearchSummaryHtml(response.data.summary);
	                    }
	                    
	                    // Add suggestion for no_results type
	                    if (response.data?.type === 'no_results' && response.data?.suggestion) {
	                        warningHtml += '<p style="font-size: 13px; color: #666; margin-top: 8px; padding: 8px; background: #f9f9f9; border-radius: 3px;">';
	                        warningHtml += '<strong>' + escapeHtml(ldsText('troubleshooting', 'Troubleshooting')) + ':</strong> ' + escapeHtml(response.data.suggestion);
	                        warningHtml += '</p>';
	                    }
                    
                    warningHtml += '</div>';
                    $resultsBody.html(warningHtml);
                }
                $spinner.removeClass('is-active');
                $submitButton.prop('disabled', false);
            }
	        }, function(errorMessage, xhr) {
	            // Check if this is an API error for the initial search
	            let errorHtml = '';
	            let isApiKeyError = false;
	            let apiErrorData = getApiErrorDataFromXhr(xhr);
	            
	            // Try to parse the response to check for structured API error data
	            if (apiErrorData && (apiErrorData.type === 'api_key_error' || apiErrorData.type === 'api_error')) {
	                isApiKeyError = apiErrorData.type === 'api_key_error';
	                errorMessage = apiErrorData.message || errorMessage;
	            } else if (errorMessage.includes('API key not valid') || 
	                errorMessage.includes('REQUEST_DENIED') || 
	                errorMessage.includes('restricted') ||
	                errorMessage.includes('OVER_QUERY_LIMIT') ||
	                errorMessage.includes('expired')) {
	                isApiKeyError = true;
	            }
	            
	            if (apiErrorData && (apiErrorData.type === 'api_key_error' || apiErrorData.type === 'api_error')) {
	                errorHtml = buildApiErrorNotice(apiErrorData, errorMessage);
	            } else if (isApiKeyError) {
	                errorHtml = buildApiErrorNotice({
	                    type: 'api_key_error',
	                    api_name: 'Google',
	                    message: errorMessage
	                }, errorMessage);
	            } else {
	                errorHtml = '<div class="notice notice-error is-dismissible"><p>' + escapeHtml(ldsText('failed', 'Failed')) + ': ' + escapeHtml(errorMessage) + '</p></div>';
	            }
            
            $resultsBody.html(errorHtml);
            $spinner.removeClass('is-active');
            $submitButton.prop('disabled', false);
        });
    });

    function processNextGoogleFetch() {
        if (placeIdQueue.length === 0) {
            if (googleDataQueue.length === 0) {
                $('#lds-results-body').append('<div class="notice notice-warning is-dismissible"><p>No valid places were found after fetching from Google.</p></div>');
                $('.lds-spinner').removeClass('is-active'); $('.lds-submit-button').prop('disabled', false);
            } else {
                // NEW: Check if manual selection is enabled
                if (manualSelectionEnabled) {
                    showManualSelectionInterface();
                } else {
                    startProcessingPhase();
                }
            }
            return;
        }

        const placeId = placeIdQueue.shift();
        processedPlaces++;

        makeAjaxRequest('lds_fetch_place_details', {
            place_id: placeId
        }, function(response) {
            if (response.success && response.data.place_data) {
                googleDataQueue.push(response.data.place_data);
                const displayInfo = response.data.display_info;
                let itemContent = '<strong>' + escapeHtml(displayInfo.name) + '</strong><br>';
                itemContent += '<span style="color: #666; font-size: 12px;">' + escapeHtml(displayInfo.address) + '</span>';
                if (displayInfo.rating && displayInfo.rating > 0) { itemContent += ' <span style="color: #ff9800;">' + ldsIcon('star') + ' ' +displayInfo.rating + '</span>'; }
                if (displayInfo.phone) { itemContent += '<br><span style="color: #666; font-size: 11px;">' + ldsIcon('phone') + ' ' + escapeHtml(displayInfo.phone) + '</span>'; }
                if (displayInfo.website) { itemContent += ' <a href="' + escapeHtml(displayInfo.website) + '" target="_blank" rel="nofollow" style="color: #0073aa; font-size: 11px; text-decoration: none;">' + ldsIcon('globe') + ' ' + escapeHtml(displayInfo.website) + '</a>'; }
                $('#lds-google-list').append('<li>' + itemContent + '</li>');
	            } else {
	                const apiErrorData = response.data || {};
	                if (apiErrorData.type === 'api_key_error' || apiErrorData.type === 'api_error') {
	                    const critical = apiErrorData.type === 'api_key_error';
	                    $('#lds-google-list').append(buildApiErrorLogItem(apiErrorData, response.data?.message || 'Failed to fetch place', critical));

	                    if (critical) {
	                        $('.lds-spinner').removeClass('is-active');
	                        $('.lds-submit-button').prop('disabled', false);
	                        return;
	                    }
	                } else {
	                    $('#lds-google-list').append('<li style="background: #fff3cd; color: #856404;">SKIPPED: ' + escapeHtml(response.data?.message || 'Failed to fetch place') + '</li>');
	                }
	            }
	            updateAndContinueFetch();
	        }, function(errorMessage, xhr) {
	            // Check if this is an API error based on the error message or response data
	            let isApiKeyError = false;
	            let apiErrorData = getApiErrorDataFromXhr(xhr);
	            
	            // Try to parse the response to check for structured API error data
	            if (apiErrorData && (apiErrorData.type === 'api_key_error' || apiErrorData.type === 'api_error')) {
	                isApiKeyError = apiErrorData.type === 'api_key_error';
	                errorMessage = apiErrorData.message || errorMessage;
	            } else if (errorMessage.includes('API key not valid') || 
	                errorMessage.includes('REQUEST_DENIED') || 
	                errorMessage.includes('restricted') ||
	                errorMessage.includes('OVER_QUERY_LIMIT') ||
	                errorMessage.includes('expired')) {
	                isApiKeyError = true;
	            }
	            
	            if (apiErrorData && (apiErrorData.type === 'api_key_error' || apiErrorData.type === 'api_error')) {
	                $('#lds-google-list').append(buildApiErrorLogItem(apiErrorData, errorMessage, isApiKeyError));
	            } else if (isApiKeyError) {
	                // Show API key error prominently and stop the import process
	                $('#lds-google-list').append(buildApiErrorLogItem({
	                    type: 'api_key_error',
	                    api_name: 'Google',
	                    message: errorMessage
	                }, errorMessage, true));
	            } else {
	                $('#lds-google-list').append('<li style="background: #f8d7da; color: #721c24;">ERROR: ' + escapeHtml(errorMessage) + '</li>');
	            }

	            if (isApiKeyError) {
	                
	                // Stop the import process
	                $('.lds-spinner').removeClass('is-active');
	                $('.lds-submit-button').prop('disabled', false);
	                return; // Don't continue fetching
	            }
	            updateAndContinueFetch();
	        });
    }

    // NEW: Function to show manual selection interface
    function showManualSelectionInterface() {
        const $resultsBody = $('#lds-results-body');
        
	        let selectionHTML = searchSummaryHtml;
	        selectionHTML += '<h4 class="lds-step-header">Manual Selection: Choose which places to import</h4>';
        selectionHTML += '<div class="lds-selection-controls" style="margin: 15px 0;">';
        selectionHTML += '<button type="button" class="lds-selection-button lds-select-all" id="lds-select-all">Select All</button> ';
        selectionHTML += '<button type="button" class="lds-selection-button lds-deselect-all" id="lds-deselect-all">Deselect All</button>';
        selectionHTML += '</div>';
        selectionHTML += '<ul class="lds-selection-list" style="list-style: none; margin: 0; padding: 0; max-height: 400px; overflow-y: auto; border: 1px solid #e1e5e9; border-radius: 4px; background: #f8f9fa;">';
        
        googleDataQueue.forEach(function(place, index) {
            selectionHTML += '<li style="padding: 12px; border-bottom: 1px solid #e1e5e9; background: white; margin: 0;">';
            selectionHTML += '<label style="display: flex; align-items: flex-start; cursor: pointer;">';
            selectionHTML += '<input type="checkbox" class="lds-place-checkbox" data-index="' + index + '" checked style="margin-right: 10px; margin-top: 2px;">';
            selectionHTML += '<div style="flex: 1;">';
            selectionHTML += '<strong>' + escapeHtml(place.name) + '</strong><br>';
            selectionHTML += '<span style="color: #666; font-size: 12px;">' + escapeHtml(place.address) + '</span>';
            if (place.rating && place.rating > 0) {
                selectionHTML += ' <span style="color: #ff9800;">' + ldsIcon('star') + ' ' +place.rating + '</span>';
            }
            if (place.phone_number) {
                selectionHTML += '<br><span style="color: #666; font-size: 11px;">' + ldsIcon('phone') + ' ' + escapeHtml(place.phone_number) + '</span>';
            }
            if (place.website) {
                selectionHTML += ' <a href="' + escapeHtml(place.website) + '" target="_blank" rel="nofollow" style="color: #0073aa; font-size: 11px; text-decoration: none;">' + ldsIcon('globe') + ' ' + escapeHtml(place.website) + '</a>';
            }
            selectionHTML += '</div>';
            selectionHTML += '</label>';
            selectionHTML += '</li>';
        });
        
        selectionHTML += '</ul>';
        selectionHTML += '<div class="lds-submit-area" style="margin-top: 20px;">';
        selectionHTML += '<button type="button" class="lds-submit-button" id="lds-proceed-selected">';
        selectionHTML += '<span class="button-text">Proceed with Selected</span>';
        selectionHTML += '</button>';
        selectionHTML += '</div>';
        
        $resultsBody.html(selectionHTML);
        
        // Bind events for selection controls
        $('#lds-select-all').on('click', function() {
            $('.lds-place-checkbox').prop('checked', true);
        });
        
        $('#lds-deselect-all').on('click', function() {
            $('.lds-place-checkbox').prop('checked', false);
        });
        
	        $('#lds-proceed-selected').on('click', function() {
	            if (!validateCategorySelection()) {
	                return;
	            }

	            selectedCategoryIds = getSelectedCategoryIds();
	            selectedCategoryTaxonomy = $('#lds_category_taxonomy').val() || 'listing_category';
	
	            const selectedPlaces = [];
	            $('.lds-place-checkbox:checked').each(function() {
	                const index = $(this).data('index');
                selectedPlaces.push(googleDataQueue[index]);
            });
            
            if (selectedPlaces.length === 0) {
                alert('Please select at least one place to import.');
                return;
            }
            
            // Replace googleDataQueue with only selected places
            googleDataQueue = selectedPlaces;
            startProcessingPhase();
        });
    }

	    function startProcessingPhase() {
	        if (!validateCategorySelection()) {
	            $('.lds-spinner').removeClass('is-active');
	            $('.lds-submit-button').prop('disabled', false);
	            return;
	        }

	        selectedCategoryIds = getSelectedCategoryIds();
	        selectedCategoryTaxonomy = $('#lds_category_taxonomy').val() || 'listing_category';
	
	        const batchSize = 5;
        const batches = [];
        for (let i = 0; i < googleDataQueue.length; i += batchSize) {
            batches.push(googleDataQueue.slice(i, i + batchSize));
        }

        // Add class to hide AI text if AI is disabled
        const aiTextClass = (typeof lds_admin_vars !== 'undefined' && lds_admin_vars.ai_enabled) ? '' : ' lds-hide-ai-text';

        $('#lds-results-body').append(
            '<h4 class="lds-step-header' + aiTextClass + '" id="lds-processing-text">Processing ' + googleDataQueue.length + ' places with AI...</h4>' +
            '<div class="lds-progress-wrapper"><div id="lds-ai-progress" style="background-color: #FF9800;">0%</div></div>' +
            '<ul id="lds-ai-log" class="lds-log-area"></ul>'
        );
        scrollToCurrentStep(true);
        processBatchSequentially(batches, 0);
    }

    function processBatchSequentially(batches, batchIndex) {
        if (batchIndex >= batches.length) {
            startImportPhase();
            return;
        }

        const currentBatch = batches[batchIndex];
        const cleanBatch = currentBatch.map(item => {
            return {
                name: item.name || '', place_id: item.place_id || '', address: item.address || '',
                lat: item.lat || 0, lng: item.lng || 0, website: item.website || '',
                phone_number: item.phone_number || '', rating: item.rating || 0, opening_hours: item.opening_hours || [],
                photo_urls: item.photo_urls || [], reviews: item.reviews || [], types: item.types || [], user_ratings_total: item.user_ratings_total || 0,
            };
        });

        const $aiProgress = $('#lds-ai-progress');
        const $processingText = $('#lds-processing-text');
        
        $processingText.html(ldsIcon('bot') + ' Contacting AI to generate descriptions - batch ' + (batchIndex + 1) + ' of ' + batches.length + '. Please wait, this can take a minute...');
        $aiProgress.css('width', '100%').text('Processing...').addClass('lds-progress-pulsing');

        makeAjaxRequest('lds_process_batch_ai', {
            places_data: cleanBatch
        }, function(response) {
            $aiProgress.removeClass('lds-progress-pulsing');
            if (response.success && response.data.listings) {
                jobQueue = jobQueue.concat(response.data.listings);
                response.data.listings.forEach(function(listing) {
                    $('#lds-ai-log').prepend('<li><span style="color: green;">' + ldsIcon('check') + '</span>PROCESSED: ' + escapeHtml(listing.name) + '</li>');
                });
            } else {
                $('#lds-ai-log').prepend('<li style="background: #f8d7da; color: #721c24;">' + ldsIcon('x') + 'BATCH FAILED: ' + (response.data?.message || 'Unknown error') + '</li>');
            }
            const processingProgress = ((batchIndex + 1) / batches.length) * 100;
            $aiProgress.css('width', processingProgress + '%').text(Math.round(processingProgress) + '%');
            setTimeout(function() { processBatchSequentially(batches, batchIndex + 1); }, 500);
        }, function(errorMessage, xhr) {
            $aiProgress.removeClass('lds-progress-pulsing');
            $('#lds-ai-log').prepend('<li style="background: #f8d7da; color: #721c24;">' + ldsIcon('x') + 'BATCH ERROR: ' + escapeHtml(errorMessage) + '</li>');
            const processingProgress = ((batchIndex + 1) / batches.length) * 100;
            $aiProgress.css('width', processingProgress + '%').text(Math.round(processingProgress) + '%');
            setTimeout(function() { processBatchSequentially(batches, batchIndex + 1); }, 500);
        });
    }

    function startImportPhase() {
        if (jobQueue.length === 0) {
            $('#lds-results-body').append('<div class="notice notice-error"><p>Processing complete, but no valid listings were generated for import.</p></div>');
            $('.lds-spinner').removeClass('is-active');
            $('.lds-submit-button').prop('disabled', false);
            return;
        }

        totalJobs = jobQueue.length;
        processedJobs = 0;
        
        $('#lds-results-body').append(
            '<h4 class="lds-step-header">Importing ' + totalJobs + ' listings to Listeo database...</h4>' +
            '<div class="lds-progress-wrapper"><div id="lds-import-progress" style="background-color: #4CAF50;">0%</div></div>' +
            '<ul id="lds-import-log" class="lds-log-area"></ul>'
        );
        scrollToCurrentStep(true);
        processNextImportJob();
    }

    // Reset the form after a completed import so the user can run another one
    // straight away - the fields keep their values, so only the keyword needs adjusting.
    function finishImport() {
        // Drop the stale "Proceed with Selected" button so the places that were
        // just imported can't be submitted a second time.
        $('#lds-proceed-selected').closest('.lds-submit-area').remove();

        const $mainButton = $('#lds-import-form .lds-submit-button[type="submit"]');
        $mainButton.find('.button-text').text('Run Import Again');
        $mainButton.prop('disabled', false);
        $('.lds-spinner').removeClass('is-active');
    }

    function processNextImportJob() {
        if (jobQueue.length === 0) {
            $('#lds-import-progress').css('width', '100%').text('100% - Complete!');
            $('#lds-import-log').append('<li style="font-weight: bold; color: #155724; background-color: #d4edda;">' + ldsIcon('check') + ' Import finished! All new listings have been added.</li>');
            finishImport();
            return;
        }

        const job = jobQueue.shift();
        processedJobs++;

        // --- NEW: Include region, listing type, category taxonomy, and import preferences in the AJAX request ---
        const importPreferences = {
            import_phone: $('#lds_import_phone').is(':checked') ? 1 : 0,
            import_website: $('#lds_import_website').is(':checked') ? 1 : 0,
            import_socials: $('#lds_import_socials').is(':checked') ? 1 : 0,
            import_hours: $('#lds_import_hours').is(':checked') ? 1 : 0,
            import_place_id: $('#lds_import_place_id').is(':checked') ? 1 : 0,
            import_photos: $('#lds_import_photos').is(':checked') ? 1 : 0
        };

        makeAjaxRequest('lds_process_single_job', {
            listing_data: job,
            category_ids: selectedCategoryIds, // Category multi-select
            category_taxonomy: selectedCategoryTaxonomy, // NEW
            region_ids: selectedRegionIds, // Region multi-select
            region_taxonomy: selectedRegionTaxonomy, // NEW
            listing_type: selectedListingType, // NEW
            feature_ids: selectedFeatureIds, // Features multi-select
            import_prefs: importPreferences // NEW - selective data import
        }, function(response) {
            if (response.success) {
                $('#lds-import-log').prepend('<li><span style="color: green;">' + ldsIcon('check') + '</span>SUCCESS: Imported "' + escapeHtml(response.data.post_title) + '" (ID: ' + response.data.post_id + ')</li>');
            } else {
                $('#lds-import-log').prepend('<li style="background: #f8d7da; color: #721c24;">' + ldsIcon('x') + 'FAILED: ' + escapeHtml(response.data.message) + '</li>');
            }
            const importProgress = (processedJobs / totalJobs) * 100;
            $('#lds-import-progress').css('width', importProgress + '%').text(Math.round(importProgress) + '%');
            setTimeout(processNextImportJob, 200);
        }, function(errorMessage, xhr) {
            $('#lds-import-log').prepend('<li style="background: #f8d7da; color: #721c24;">' + ldsIcon('x') + 'ERROR: ' + escapeHtml(errorMessage) + '</li>');
            const importProgress = (processedJobs / totalJobs) * 100;
            $('#lds-import-progress').css('width', importProgress + '%').text(Math.round(importProgress) + '%');
            setTimeout(processNextImportJob, 200);
        });
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function updateAndContinueFetch() {
        const fetchProgress = (processedPlaces / totalPlaces) * 100;
        $('#lds-fetch-progress').css('width', fetchProgress + '%').text(Math.round(fetchProgress) + '%');
        const googleResults = $('#lds-google-list')[0];
        googleResults.scrollTop = googleResults.scrollHeight;
        setTimeout(processNextGoogleFetch, 100);
    }

    

    /* =====================================================================
     * Scheduled Imports (Pro)
     * ===================================================================== */
    (function initScheduledImports() {
        var $btn = $('#lds-schedule-import-btn');
        if (!$btn.length) { return; }

        var $modal      = $('#lds-schedule-modal');
        var $summary    = $('#lds-schedule-summary');
        var $intro      = $('#lds-schedule-intro');
        var $amount     = $('#lds-schedule-amount');
        var $unit       = $('#lds-schedule-unit');
        var $hint       = $('#lds-schedule-hint');
        var $error      = $('#lds-schedule-error');
        var $saveBtn    = $('#lds-schedule-save');
        var $saveText   = $saveBtn.find('.button-text');
        var $runNow     = $('#lds-schedule-run-now');
        var $list       = $('#lds-schedule-list');
        var $pager      = $('#lds-schedule-pagination');

        var minMinutes  = (typeof lds_admin_vars !== 'undefined' && lds_admin_vars.min_schedule_minutes) ? parseInt(lds_admin_vars.min_schedule_minutes, 10) : 15;
        var maxMinutes  = (typeof lds_admin_vars !== 'undefined' && lds_admin_vars.max_schedule_minutes) ? parseInt(lds_admin_vars.max_schedule_minutes, 10) : 43200;
        var pageSize    = (typeof lds_admin_vars !== 'undefined' && lds_admin_vars.schedule_page_size) ? parseInt(lds_admin_vars.schedule_page_size, 10) : 5;
        var currentPage = 1;

        // Show only the current page of rows and (re)build the 1,2,3 pager.
        function renderPagination() {
            var $rows = $list.find('.lds-schedule-row');
            var total = $rows.length;
            var pages = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage > pages) { currentPage = pages; }
            if (currentPage < 1) { currentPage = 1; }

            $rows.each(function(i) {
                var page = Math.floor(i / pageSize) + 1;
                $(this).toggle(page === currentPage);
            });

            $pager.empty();
            if (pages <= 1) { return; }

            for (var p = 1; p <= pages; p++) {
                var $b = $('<button type="button" class="lds-page-btn"></button>').text(p).attr('data-page', p);
                if (p === currentPage) { $b.addClass('is-active'); }
                $pager.append($b);
            }
        }

        $pager.on('click', '.lds-page-btn', function() {
            var p = parseInt($(this).data('page'), 10);
            if (!isNaN(p)) { currentPage = p; renderPagination(); }
        });

        var editingId    = null;    // set when editing an existing schedule (time only)
        var pendingSettings = null; // captured form settings for a new schedule

        function prefVal(id) {
            var $el = $('#' + id);
            if (!$el.length) { return 1; } // absent control => enabled (matches server default)
            return $el.is(':checked') ? 1 : 0;
        }

        function collectImportSettings() {
            return {
                query:             $('#lds_query').val() ? $('#lds_query').val().trim() : '',
                location:          $('#lds_location').val() ? $('#lds_location').val().trim() : '',
                search_mode:       $('#lds-search-mode').val() || 'text',
                lat:               $('#lds-lat').val() || '',
                lng:               $('#lds-lng').val() || '',
                category_ids:      getSelectedCategoryIds(),
                category_taxonomy: $('#lds_category_taxonomy').val() || 'listing_category',
                region_ids:        $('#lds_region').val() || [],
                region_taxonomy:   $('#lds_region_taxonomy').val() || 'region',
                listing_type:      $('#lds_listing_type').val() || '',
                feature_ids:       $('#lds_features').val() || [],
                import_prefs: {
                    import_phone:    prefVal('lds_import_phone'),
                    import_website:  prefVal('lds_import_website'),
                    import_socials:  prefVal('lds_import_socials'),
                    import_hours:    prefVal('lds_import_hours'),
                    import_place_id: prefVal('lds_import_place_id'),
                    import_photos:   prefVal('lds_import_photos')
                }
            };
        }

        function validateImportForm() {
            var businessType = $('#lds_query').val() ? $('#lds_query').val().trim() : '';
            var listingType  = $('#lds_listing_type').val();
            var searchMode   = $('#lds-search-mode').val() || 'text';
            var location     = $('#lds_location').val() ? $('#lds_location').val().trim() : '';
            var lat          = $('#lds-lat').val();
            var lng          = $('#lds-lng').val();

            if (!businessType || !listingType) { return false; }
            if (searchMode === 'map') {
                if (!lat || !lng) { return false; }
            } else if (!location) {
                return false;
            }
            // Reuses the page's own category validator (also surfaces its inline message).
            if (typeof validateCategorySelection === 'function' && !validateCategorySelection()) {
                return false;
            }
            return true;
        }

        var selectedWord = ldsText('sched_selected', 'selected');

        function buildSummaryHtml(settings) {
            var rows = [];
            var listingTypeText = $('#lds_listing_type option:selected').text() || settings.listing_type;
            var where = settings.search_mode === 'map'
                ? (settings.location || ldsText('sched_map_location', 'Map location'))
                : settings.location;

            function row(label, value) {
                return '<div class="lds-schedule-summary__row"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(String(value)) + '</strong></div>';
            }

            rows.push(row(ldsText('sched_business', 'Business type'), settings.query));
            rows.push(row(ldsText('sched_location', 'Location'), where));
            rows.push(row(ldsText('sched_listing_type', 'Listing type'), listingTypeText));
            rows.push(row(ldsText('sched_categories', 'Categories'), settings.category_ids.length + ' ' + selectedWord));
            if (settings.region_ids.length) { rows.push(row(ldsText('sched_regions', 'Regions'), settings.region_ids.length + ' ' + selectedWord)); }
            if (settings.feature_ids.length) { rows.push(row(ldsText('sched_features', 'Features'), settings.feature_ids.length + ' ' + selectedWord)); }
            rows.push(row(ldsText('sched_photos', 'Photos'), settings.import_prefs.import_photos ? ldsText('sched_yes', 'Yes') : ldsText('sched_no', 'No')));

            return rows.join('');
        }

        function setHint() {
            var maxDays = Math.round(maxMinutes / 1440);
            var tpl = ldsText('schedule_hint', 'Up to {max} days from now. Runs once via WP-Cron when your site receives traffic at/after that time.');
            $hint.text(tpl.replace('{max}', maxDays));
        }

        function showError(msg) {
            $error.text(msg).show();
        }

        function clearError() {
            $error.hide().text('');
        }

        function openModal() {
            clearError();
            setHint();
            $modal.css('display', 'flex').attr('aria-hidden', 'false');
        }

        function closeModal() {
            $modal.css('display', 'none').attr('aria-hidden', 'true');
            editingId = null;
            pendingSettings = null;
        }

        function getDelayMinutes() {
            var amount = parseInt($amount.val(), 10);
            if (isNaN(amount) || amount < 1) { return null; }
            var unit = $unit.val();
            return unit === 'hours' ? amount * 60 : amount;
        }

        function setSaving(saving) {
            $saveBtn.prop('disabled', saving);
            $saveBtn.find('.lds-spinner').toggleClass('is-active', saving);
        }

        // --- Open for a brand new schedule ---
        $btn.on('click', function() {
            if (typeof lds_admin_vars !== 'undefined' && lds_admin_vars.schedule_locked) {
                if (lds_admin_vars.upgrade_url_schedule) {
                    window.open(lds_admin_vars.upgrade_url_schedule, '_blank');
                }
                return;
            }

            if (!validateImportForm()) {
                alert(ldsText('schedule_fill_form', 'Please fill out the import form (business type, location, listing type and category) before scheduling.'));
                return;
            }

            editingId = null;
            pendingSettings = collectImportSettings();
            $intro.text(ldsText('schedule_create_intro', 'This import will run automatically with the settings below.'));
            $summary.html(buildSummaryHtml(pendingSettings)).show();
            $amount.val(1);
            $unit.val('hours');
            $saveText.text(ldsText('schedule_btn_create', 'Schedule Import'));
            $runNow.hide();
            openModal();
        });

        // --- Open for editing an existing schedule (time only) ---
        $list.on('click', '.lds-schedule-edit', function() {
            editingId = $(this).data('schedule-id');
            pendingSettings = null;
            var label = $(this).data('label') || '';
            $intro.text(ldsText('schedule_edit_intro', 'Change when "{label}" runs.').replace('{label}', label));
            $summary.hide().empty();

            // Prefill with the delay originally chosen (whole hours -> hours, else minutes).
            var delay = parseInt($(this).data('delay'), 10);
            if (isNaN(delay) || delay < 1) { delay = 60; }
            if (delay % 60 === 0) {
                $amount.val(delay / 60);
                $unit.val('hours');
            } else {
                $amount.val(delay);
                $unit.val('minutes');
            }

            $saveText.text(ldsText('schedule_btn_save', 'Save'));
            $runNow.show();
            openModal();
        });

        // Insert or replace a row returned by the server.
        function upsertRow(id, rowHtml) {
            $list.find('.lds-schedule-empty').remove();
            var $existing = $list.find('[data-schedule-id="' + id + '"]');
            if ($existing.length) {
                $existing.replaceWith(rowHtml); // edit / run-now: keep its place
            } else {
                $list.prepend(rowHtml);         // new schedule: newest-first (top)
                currentPage = 1;
            }
            renderPagination();
        }

        // --- Run now (edit mode only) ---
        // Fire-and-forget: close the modal immediately, flip the badge to "Processing",
        // and let the import finish in the background (the row updates when it returns,
        // and reflects the real status on any page reload).
        $runNow.on('click', function() {
            if (!editingId) { return; }
            if (!window.confirm(ldsText('schedule_runnow_confirm', 'Run this import now?'))) { return; }

            var id = editingId;

            // Optimistically show "Processing" on the row.
            $list.find('[data-schedule-id="' + id + '"]').find('.lds-sched-status')
                .attr('class', 'lds-sched-status lds-sched-status--running')
                .text(ldsText('sched_status_processing', 'Processing'));

            closeModal();

            makeAjaxRequest('lds_run_schedule_now', { schedule_id: id }, function(response) {
                if (response.success && response.data && response.data.row_html) {
                    upsertRow(response.data.id, response.data.row_html);
                }
                // On error/timeout we keep the "Processing" badge; the row's real status
                // shows on the next page load.
            }, function() {});
        });

        // --- Rerun a finished import (completed / failed) ---
        $list.on('click', '.lds-schedule-rerun', function() {
            var id = $(this).data('schedule-id');
            if (!window.confirm(ldsText('schedule_rerun_confirm', 'Run this import again now?'))) { return; }

            $list.find('[data-schedule-id="' + id + '"]').find('.lds-sched-status')
                .attr('class', 'lds-sched-status lds-sched-status--running')
                .text(ldsText('sched_status_processing', 'Processing'));

            makeAjaxRequest('lds_rerun_schedule', { schedule_id: id }, function(response) {
                if (response.success && response.data && response.data.row_html) {
                    upsertRow(response.data.id, response.data.row_html);
                }
            }, function() {});
        });

        // --- Delete ---
        $list.on('click', '.lds-schedule-delete', function() {
            var id = $(this).data('schedule-id');
            if (!window.confirm(ldsText('schedule_delete_confirm', 'Delete this scheduled import?'))) { return; }

            var $row = $('[data-schedule-id="' + id + '"]');
            makeAjaxRequest('lds_delete_schedule', { schedule_id: id }, function(response) {
                if (response.success) {
                    $row.remove();
                    if ($list.find('.lds-schedule-row').length === 0) {
                        $list.append('<li class="lds-schedule-empty">' + escapeHtml(ldsText('schedule_none', 'No scheduled imports yet.')) + '</li>');
                    }
                    renderPagination();
                } else {
                    alert(response.data && response.data.message ? response.data.message : ldsText('failed', 'Failed'));
                }
            }, function(errorMessage) {
                alert(errorMessage);
            });
        });

        // --- Cancel / close ---
        $('#lds-schedule-cancel').on('click', closeModal);
        $modal.on('click', function(e) {
            if (e.target === this) { closeModal(); } // click on backdrop
        });
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) { closeModal(); }
        });

        // --- Save ---
        $saveBtn.on('click', function() {
            clearError();

            var delayMinutes = getDelayMinutes();
            if (delayMinutes === null) {
                showError(ldsText('schedule_invalid_time', 'Please enter a valid time.'));
                return;
            }
            if (delayMinutes < minMinutes) {
                showError(ldsText('schedule_min_error', 'Please choose at least {min} minutes from now.').replace('{min}', minMinutes));
                return;
            }
            if (delayMinutes > maxMinutes) {
                showError(ldsText('schedule_max_error', 'Please choose a time within {max} days from now.').replace('{max}', Math.round(maxMinutes / 1440)));
                return;
            }

            var payload = { delay_minutes: delayMinutes };
            if (editingId) {
                payload.schedule_id = editingId;
            } else {
                if (!pendingSettings) {
                    showError(ldsText('schedule_save_failed', 'Could not save the schedule.'));
                    return;
                }
                payload.settings = JSON.stringify(pendingSettings);
            }

            setSaving(true);
            makeAjaxRequest('lds_save_schedule', payload, function(response) {
                setSaving(false);
                if (response.success && response.data && response.data.row_html) {
                    upsertRow(response.data.id, response.data.row_html);
                    closeModal();
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : ldsText('schedule_save_failed', 'Could not save the schedule.');
                    showError(msg);
                }
            }, function(errorMessage) {
                setSaving(false);
                showError(errorMessage);
            });
        });

        // Paginate whatever was rendered on page load.
        renderPagination();
    })();
});

class SearchableDropdown {
    constructor(selectElement, options = {}) {
        this.originalSelect = selectElement;
        this.options = {
            placeholder: options.placeholder || 'Search and select...',
            noResultsText: options.noResultsText || 'No results found',
            required: options.required || false,
            multiSelect: options.multiSelect || false,
            ...options
        };

        // Single select mode
        this.selectedValue = '';
        this.selectedText = '';
        // Multi-select mode
        this.selectedValues = [];
        this.isOpen = false;

        this.init();
    }
    
    init() {
        // Hide the original select
        this.originalSelect.style.display = 'none';
        
        // Create the searchable dropdown structure
        this.createDropdownHTML();
        this.bindEvents();
        
        // Set initial value if the original select has one
        const initialValue = this.originalSelect.value;
        if (initialValue) {
            const option = this.originalSelect.querySelector(`option[value="${initialValue}"]`);
            if (option) {
                this.selectOption(initialValue, option.textContent);
            }
        }
    }
    
    createDropdownHTML() {
        // Create wrapper
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'lds-searchable-dropdown';
        if (this.options.multiSelect) {
            this.wrapper.classList.add('lds-multiselect');
        }

        // Create tags container for multi-select
        if (this.options.multiSelect) {
            this.tagsContainer = document.createElement('div');
            this.tagsContainer.className = 'lds-tags-container';
        }

        // Create search input
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'lds-searchable-input';
        this.searchInput.placeholder = this.options.placeholder;
        this.searchInput.autocomplete = 'off';

        // Create dropdown arrow
        this.arrow = document.createElement('div');
        this.arrow.className = 'lds-dropdown-arrow';
        this.arrow.innerHTML = '▼';

        // Create input wrapper
        this.inputWrapper = document.createElement('div');
        this.inputWrapper.className = 'lds-input-wrapper';
        this.inputWrapper.appendChild(this.searchInput);
        this.inputWrapper.appendChild(this.arrow);

        // Create dropdown list
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'lds-dropdown-list';

        // Create options list
        this.optionsList = document.createElement('ul');
        this.optionsList.className = 'lds-options-list';

        this.dropdown.appendChild(this.optionsList);

        // Assemble the component
        this.wrapper.appendChild(this.inputWrapper);
        this.wrapper.appendChild(this.dropdown);
        if (this.options.multiSelect) {
            this.wrapper.appendChild(this.tagsContainer);
        }

        // Insert after the original select
        this.originalSelect.parentNode.insertBefore(this.wrapper, this.originalSelect.nextSibling);

        // Populate options
        this.populateOptions();
    }
    
    populateOptions() {
        this.optionsList.innerHTML = '';
        const options = Array.from(this.originalSelect.querySelectorAll('option'));

        options.forEach(option => {
            if (option.value === '') return; // Skip empty option

            const li = document.createElement('li');
            li.className = 'lds-option-item';
            li.dataset.value = option.value;

            // Handle indentation for hierarchical options
            const text = option.textContent;
            const indentLevel = (text.match(/^(\s|&nbsp;)*/)[0].length) / 4; // Count indentation
            const cleanText = text.replace(/^(\s|&nbsp;)*/, '').trim();

            if (this.options.multiSelect) {
                // Multi-select: add checkbox (no label to avoid click conflicts)
                const isSelected = this.selectedValues.includes(option.value);
                li.innerHTML = `
                    <div class="lds-checkbox-row" style="padding-left: ${indentLevel * 10}px">
                        <input type="checkbox" class="lds-option-checkbox" ${isSelected ? 'checked' : ''} />
                        <span class="lds-option-text">${this.escapeHtml(cleanText)}</span>
                    </div>`;
            } else {
                li.innerHTML = `<span class="lds-option-text" style="padding-left: ${indentLevel * 10}px">${this.escapeHtml(cleanText)}</span>`;
            }

            if (indentLevel > 0) {
                li.classList.add('lds-option-child');
            } else {
                li.classList.add('lds-option-parent');
            }

            this.optionsList.appendChild(li);
        });
    }
    
    bindEvents() {
        // Search input events
        this.searchInput.addEventListener('input', (e) => {
            this.filterOptions(e.target.value);
        });
        
        this.searchInput.addEventListener('focus', () => {
            this.openDropdown();
        });
        
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeydown(e);
        });
        
        // Arrow click
        this.arrow.addEventListener('click', () => {
            if (this.isOpen) {
                this.closeDropdown();
            } else {
                this.openDropdown();
                this.searchInput.focus();
            }
        });
        
        // Option clicks
        this.optionsList.addEventListener('click', (e) => {
            const optionItem = e.target.closest('.lds-option-item');
            if (optionItem) {
                const value = optionItem.dataset.value;
                const text = optionItem.querySelector('.lds-option-text').textContent;
                if (this.options.multiSelect) {
                    this.toggleOption(value, text, optionItem);
                } else {
                    this.selectOption(value, text);
                }
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.closeDropdown();
            }
        });
    }
    
    filterOptions(searchTerm) {
        const options = this.optionsList.querySelectorAll('.lds-option-item');
        let hasResults = false;
        
        options.forEach(option => {
            const text = option.querySelector('.lds-option-text').textContent.toLowerCase();
            const matches = text.includes(searchTerm.toLowerCase());
            
            if (matches || searchTerm === '') {
                option.style.display = 'block';
                hasResults = true;
            } else {
                option.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        this.showNoResults(!hasResults && searchTerm !== '');
    }
    
    showNoResults(show) {
        let noResultsItem = this.optionsList.querySelector('.lds-no-results');
        
        if (show && !noResultsItem) {
            noResultsItem = document.createElement('li');
            noResultsItem.className = 'lds-no-results';
            noResultsItem.innerHTML = `<span class="lds-option-text">${this.options.noResultsText}</span>`;
            this.optionsList.appendChild(noResultsItem);
        } else if (!show && noResultsItem) {
            noResultsItem.remove();
        }
    }
    
    selectOption(value, text) {
        this.selectedValue = value;
        this.selectedText = text;

        // Update the original select
        this.originalSelect.value = value;

        // Update the search input
        this.searchInput.value = text;

        // Trigger change event on original select
        const changeEvent = new Event('change', { bubbles: true });
        this.originalSelect.dispatchEvent(changeEvent);

        this.closeDropdown();
    }

    // Multi-select: toggle an option on/off
    toggleOption(value, text, optionItem) {
        const checkbox = optionItem.querySelector('.lds-option-checkbox');
        const index = this.selectedValues.indexOf(value);

        if (index > -1) {
            // Remove from selection
            this.selectedValues.splice(index, 1);
            this.removeTag(value);
            if (checkbox) checkbox.checked = false;
        } else {
            // Add to selection
            this.selectedValues.push(value);
            this.addTag(value, text);
            if (checkbox) checkbox.checked = true;
        }

        // Update the original select element
        this.updateOriginalSelect();

        // Clear search input after selection
        this.searchInput.value = '';
        this.filterOptions('');

        // Trigger change event
        const changeEvent = new Event('change', { bubbles: true });
        this.originalSelect.dispatchEvent(changeEvent);
    }

    // Add a tag to the tags container
    addTag(value, text) {
        if (!this.tagsContainer) return;

        const tag = document.createElement('span');
        tag.className = 'lds-tag';
        tag.dataset.value = value;
        tag.innerHTML = `${this.escapeHtml(text)} <span class="lds-tag-remove">×</span>`;

        // Handle tag removal
        tag.querySelector('.lds-tag-remove').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleOption(value, text, this.optionsList.querySelector(`[data-value="${value}"]`));
        });

        this.tagsContainer.appendChild(tag);
    }

    // Remove a tag from the tags container
    removeTag(value) {
        if (!this.tagsContainer) return;
        const tag = this.tagsContainer.querySelector(`[data-value="${value}"]`);
        if (tag) tag.remove();
    }

    // Update the original select element with selected values
    updateOriginalSelect() {
        const options = this.originalSelect.querySelectorAll('option');
        options.forEach(option => {
            option.selected = this.selectedValues.includes(option.value);
        });
    }

    // Get selected values (for external access)
    getValues() {
        return this.options.multiSelect ? this.selectedValues : [this.selectedValue];
    }

    openDropdown() {
        this.isOpen = true;
        this.dropdown.style.display = 'block';
        this.wrapper.classList.add('lds-dropdown-open');
        this.arrow.innerHTML = '▲';
        
        // Reset filter
        this.filterOptions('');
    }
    
    closeDropdown() {
        this.isOpen = false;
        this.dropdown.style.display = 'none';
        this.wrapper.classList.remove('lds-dropdown-open');
        this.arrow.innerHTML = '▼';

        if (this.options.multiSelect) {
            // Multi-select: just clear the search input
            this.searchInput.value = '';
        } else {
            // Single select: restore selected text or clear
            if (!this.selectedValue && this.searchInput.value) {
                this.searchInput.value = '';
            } else if (this.selectedValue) {
                this.searchInput.value = this.selectedText;
            }
        }
    }
    
    handleKeydown(e) {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.navigateOptions('down');
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.navigateOptions('up');
                break;
            case 'Enter':
                e.preventDefault();
                this.selectHighlightedOption();
                break;
            case 'Escape':
                this.closeDropdown();
                break;
        }
    }
    
    navigateOptions(direction) {
        const visibleOptions = Array.from(this.optionsList.querySelectorAll('.lds-option-item:not([style*="display: none"])'));
        const currentHighlighted = this.optionsList.querySelector('.lds-option-highlighted');
        
        let newIndex = 0;
        
        if (currentHighlighted) {
            const currentIndex = visibleOptions.indexOf(currentHighlighted);
            newIndex = direction === 'down' 
                ? Math.min(currentIndex + 1, visibleOptions.length - 1)
                : Math.max(currentIndex - 1, 0);
        }
        
        // Remove previous highlight
        if (currentHighlighted) {
            currentHighlighted.classList.remove('lds-option-highlighted');
        }
        
        // Add new highlight
        if (visibleOptions[newIndex]) {
            visibleOptions[newIndex].classList.add('lds-option-highlighted');
            visibleOptions[newIndex].scrollIntoView({ block: 'nearest' });
        }
    }
    
    selectHighlightedOption() {
        const highlighted = this.optionsList.querySelector('.lds-option-highlighted');
        if (highlighted) {
            const value = highlighted.dataset.value;
            const text = highlighted.querySelector('.lds-option-text').textContent;
            this.selectOption(value, text);
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
// Global references to SearchableDropdown instances
let categoryDropdownInstance = null;
let regionDropdownInstance = null;
let listingTypeDropdownInstance = null;
let featuresDropdownInstance = null;

// Initialize searchable dropdowns when the document is ready
jQuery(document).ready(function($) {
    // Initialize category dropdown (multi-select)
    const categorySelect = document.getElementById('lds_category');
    if (categorySelect) {
        categoryDropdownInstance = new SearchableDropdown(categorySelect, {
            placeholder: 'Search categories...',
            required: true,
            multiSelect: true
        });
    }

    // Initialize region dropdown (multi-select)
    const regionSelect = document.getElementById('lds_region');
    if (regionSelect && !regionSelect.disabled) {
        regionDropdownInstance = new SearchableDropdown(regionSelect, {
            placeholder: 'Search regions... (optional)',
            required: false,
            multiSelect: true
        });
    }

    // Initialize listing type dropdown
    const listingTypeSelect = document.getElementById('lds_listing_type');
    if (listingTypeSelect) {
        listingTypeDropdownInstance = new SearchableDropdown(listingTypeSelect, {
            placeholder: 'Select listing type...',
            required: true
        });
    }

    // Initialize features dropdown (multi-select)
    const featuresSelect = document.getElementById('lds_features');
    if (featuresSelect && !featuresSelect.disabled) {
        featuresDropdownInstance = new SearchableDropdown(featuresSelect, {
            placeholder: 'Search features... (optional)',
            required: false,
            multiSelect: true
        });
    }

    // Function to map category taxonomy to listing type
    function getListingTypeFromTaxonomy(taxonomy) {
        // Direct mapping for standard taxonomies
        const taxonomyToTypeMapping = {
            'service_category': 'service',
            'rental_category': 'rental',
            'event_category': 'event',
            'classifieds_category': 'classifieds'
        };

        // Check direct mapping first
        if (taxonomyToTypeMapping[taxonomy]) {
            return taxonomyToTypeMapping[taxonomy];
        }

        // Handle custom listing types (e.g., 'restaurant_category' -> 'restaurant')
        if (taxonomy && taxonomy.endsWith('_category') && taxonomy !== 'listing_category') {
            const potentialType = taxonomy.replace('_category', '');

            // Check if this type exists in the listing type dropdown
            const $listingTypeSelect = $('#lds_listing_type');
            if ($listingTypeSelect.find('option[value="' + potentialType + '"]').length > 0) {
                return potentialType;
            }
        }

        // Return null for general categories or unrecognized taxonomies
        return null;
    }

    // Function to update listing type dropdown with visual feedback
    function updateListingTypeFromCategory(targetType, isAutoSelected = false) {
        const $listingTypeSelect = $('#lds_listing_type');
        const $listingTypeWrapper = $listingTypeSelect.closest('.lds-form-group');

        if (targetType && $listingTypeSelect.find('option[value="' + targetType + '"]').length > 0) {
            const typeName = $listingTypeSelect.find('option[value="' + targetType + '"]').text();

            // Update the SearchableDropdown instance properly if it exists
            if (listingTypeDropdownInstance) {
                listingTypeDropdownInstance.selectOption(targetType, typeName);
            } else {
                // Fallback for regular select
                $listingTypeSelect.val(targetType);
                $listingTypeSelect.trigger('change');
            }

            // Add simple notification for auto-selection
            if (isAutoSelected) {
                const $searchableWrapper = $listingTypeWrapper.find('.lds-searchable-dropdown');

                // Show temporary feedback message
                let $feedback = $listingTypeWrapper.find('.auto-select-feedback');
                if ($feedback.length === 0) {
                    $feedback = $('<div class="auto-select-feedback"></div>');
                    // Insert after the searchable dropdown if it exists, otherwise after the description
                    if ($searchableWrapper.length > 0) {
                        $searchableWrapper.after($feedback);
                    } else {
                        $listingTypeWrapper.find('.lds-form-description').after($feedback);
                    }
                }

                $feedback.html(ldsIcon('check') + ' Auto-selected "' + typeName + '" based on category').show();

                // Remove feedback after 3 seconds
                setTimeout(function() {
                    $feedback.fadeOut(400);
                }, 3000);
            }
        }
    }

    // Handle category selection to update hidden taxonomy field AND listing type (multi-select)
    // Enforces rule: can mix listing_category with ONE type-specific taxonomy, but NOT multiple type-specific taxonomies
    $('#lds_category').on('change', function() {
        const $select = $(this);
        const selectedOptions = $select.find('option:selected');

        // Collect taxonomies from all selected options
        let generalCategories = []; // listing_category
        let typeSpecificCategories = {}; // grouped by taxonomy

        selectedOptions.each(function() {
            const $option = $(this);
            const value = $option.val();
            if (!value) return; // Skip empty options

            // Get taxonomy from option or parent optgroup
            let optionTaxonomy = $option.data('taxonomy');
            if (!optionTaxonomy) {
                const optgroup = $option.closest('optgroup');
                if (optgroup.length > 0) {
                    optionTaxonomy = optgroup.data('taxonomy');
                }
            }
            optionTaxonomy = optionTaxonomy || 'listing_category';

            if (optionTaxonomy === 'listing_category') {
                generalCategories.push(value);
            } else {
                if (!typeSpecificCategories[optionTaxonomy]) {
                    typeSpecificCategories[optionTaxonomy] = [];
                }
                typeSpecificCategories[optionTaxonomy].push(value);
            }
        });

        // Check for conflicts: multiple type-specific taxonomies
        const typeSpecificTaxonomies = Object.keys(typeSpecificCategories);

        if (typeSpecificTaxonomies.length > 1) {
            // Conflict! Keep only the most recent type-specific taxonomy (last one)
            const keepTaxonomy = typeSpecificTaxonomies[typeSpecificTaxonomies.length - 1];
            const removeTaxonomies = typeSpecificTaxonomies.filter(t => t !== keepTaxonomy);

            // Show warning above the category label
            const $formGroup = $select.closest('.lds-form-group');
            let $warning = $formGroup.find('.lds-taxonomy-conflict-warning');
            if ($warning.length === 0) {
                $warning = $('<div class="lds-taxonomy-conflict-warning" style="background: #fff3de; color: #885c0e; padding: 12px 14px; border-radius: 8px; margin-bottom: 10px; font-size: 13px;"></div>');
                $formGroup.prepend($warning);
            }
            $warning.html('<strong>' + ldsIcon('alert') + ' Conflict:</strong> You cannot mix categories from different listing types. Categories from other types have been deselected.').show();

            // Auto-hide warning after 5 seconds
            setTimeout(function() {
                $warning.fadeOut(400);
            }, 5000);

            // Deselect conflicting options and update SearchableDropdown
            removeTaxonomies.forEach(function(taxToRemove) {
                typeSpecificCategories[taxToRemove].forEach(function(valueToRemove) {
                    // Deselect in original select
                    $select.find('option[value="' + valueToRemove + '"]').prop('selected', false);

                    // Remove from SearchableDropdown if instance exists
                    if (categoryDropdownInstance) {
                        const idx = categoryDropdownInstance.selectedValues.indexOf(valueToRemove);
                        if (idx > -1) {
                            categoryDropdownInstance.selectedValues.splice(idx, 1);
                            categoryDropdownInstance.removeTag(valueToRemove);
                            // Uncheck the checkbox in the dropdown
                            const optionItem = categoryDropdownInstance.optionsList.querySelector('[data-value="' + valueToRemove + '"]');
                            if (optionItem) {
                                const checkbox = optionItem.querySelector('.lds-option-checkbox');
                                if (checkbox) checkbox.checked = false;
                            }
                        }
                    }
                });
            });

            // Update typeSpecificCategories to only have the kept taxonomy
            typeSpecificCategories = { [keepTaxonomy]: typeSpecificCategories[keepTaxonomy] };
        }

        // Determine the primary taxonomy for the hidden field
        let primaryTaxonomy = 'listing_category';
        const remainingTypeSpecific = Object.keys(typeSpecificCategories);
        if (remainingTypeSpecific.length > 0) {
            primaryTaxonomy = remainingTypeSpecific[0];
        }

        $('#lds_category_taxonomy').val(primaryTaxonomy);

        // Show/hide info about selected category's listing type
        const $formGroup = $select.closest('.lds-form-group');
        let $typeInfo = $formGroup.find('.lds-category-type-info');

        // Auto-update listing type based on selected category taxonomy
        const targetListingType = getListingTypeFromTaxonomy(primaryTaxonomy);

        if (targetListingType) {
            // Category is type-specific, auto-select the corresponding listing type
            updateListingTypeFromCategory(targetListingType, true);

            // Get the listing type display name
            const typeName = $('#lds_listing_type option[value="' + targetListingType + '"]').text() || targetListingType;

            // Show info message about the selected category's listing type
            if ($typeInfo.length === 0) {
                $typeInfo = $('<div class="lds-category-type-info" style="background: #dcedff; color: #0073ee; padding: 12px 14px; border-radius: 8px; margin-top: 10px; font-size: 13px;"></div>');
                $formGroup.find('.lds-form-description').after($typeInfo);
            }
            $typeInfo.html('<strong>' + ldsIcon('info') + ' Info:</strong> Selected category belongs to <strong>' + typeName + '</strong> listing type. The listing type has been auto-selected.').show();
        } else if (primaryTaxonomy === 'listing_category') {
            // General categories selected - hide info and remove listing type notification
            if ($typeInfo.length > 0) {
                $typeInfo.hide();
            }
            const $listingTypeWrapper = $('#lds_listing_type').closest('.lds-form-group');
            $listingTypeWrapper.find('.auto-select-feedback').fadeOut(300);
        }

        // Hide info if no categories selected
        if (selectedOptions.length === 0 || (selectedOptions.length === 1 && !selectedOptions.first().val())) {
            if ($typeInfo.length > 0) {
                $typeInfo.hide();
            }
        }
    });

    // =======================
    // LICENSE MANAGEMENT
    // =======================

    // License activation/deactivation handler
    $('#lds_license_action_btn').on('click', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $spinner = $('#lds_license_spinner');
        const $licenseKey = $('#lds_license_key');
        const $statusDiv = $('#lds_license_status');
        const action = $button.data('action');

        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.addClass('is-active');

        // Check if lds_admin_vars is defined
        if (typeof lds_admin_vars === 'undefined') {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
            $statusDiv.html('<p style="color: #dc3545;"><strong>Error:</strong> Configuration not loaded. Please refresh the page.</p>');
            return;
        }

        const ajaxAction = action === 'activate' ? 'lds_activate_license' : 'lds_deactivate_license';
        const ajaxData = {
            action: ajaxAction,
            nonce: lds_admin_vars.nonce,
            license_key: $licenseKey.val()
        };

        $.ajax({
            url: lds_admin_vars.ajax_url,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update UI based on action
                    if (action === 'activate') {
                        $button.text('Deactivate');
                        $button.data('action', 'deactivate');
                        $licenseKey.prop('readonly', true);
                        $statusDiv.html('<p style="color: #28a745;"><strong>' + ldsIcon('check') + ' License Active</strong> - All Pro features unlocked!</p>');

                        // Show success notice
                        const $notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('.wrap h1').after($notice);

                        // Reload page after 2 seconds to reflect changes
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $button.text('Activate');
                        $button.data('action', 'activate');
                        $licenseKey.prop('readonly', false).val('');
                        $statusDiv.html('<p class="description">License deactivated. Click "Activate" to validate your license.</p>');

                        // Show success notice
                        const $notice = $('<div class="notice notice-info is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('.wrap h1').after($notice);

                        // Reload page after 2 seconds to reflect changes
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    // Show error
                    $statusDiv.html('<p style="color: #dc3545;"><strong>' + ldsIcon('x') + ' Error:</strong> ' + response.data.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $statusDiv.html('<p style="color: #dc3545;"><strong>' + ldsIcon('x') + ' Error:</strong> Failed to communicate with server. Please try again.</p>');
            },
            complete: function() {
                // Re-enable button and hide spinner
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // --- Select Data to Import Toggle ---
    $('#lds_select_data_toggle').on('change', function() {
        const $dataFields = $('#lds_data_fields');
        if ($(this).is(':checked')) {
            $dataFields.slideDown(300);
        } else {
            $dataFields.slideUp(300);
        }
    });

    // Handle clicks on map lock overlay upgrade button
    $(document).on('click', '.lds-lock-overlay .button-hero', function(e) {
        // Let the link work normally - it's already configured to open in new tab
    });

    // Prevent interaction with blurred map content
    $(document).on('click', '.lds-map-preview-blurred', function(e) {
        e.preventDefault();
        e.stopPropagation();
        // Clicking blurred content does nothing - the overlay button is the CTA
        return false;
    });

    // PHOTOS IMPORT & REVIEWS INFO: Update info based on API source
    function updatePhotosInfo() {
        const apiSource = $('#lds_api_source_value').val() || 'google';
        const $photosInfo = $('#lds_photos_info');
        const $photosInfoText = $('#lds_photos_info_text');
        const $reviewsSpeedInfo = $('#lds_reviews_speed_info');
        const $openingHoursInfo = $('#lds_opening_hours_info');
        const $dataFields = $('#lds_data_fields');

        console.log('updatePhotosInfo called - API Source:', apiSource);
        console.log('Data fields visible:', $dataFields.is(':visible'));
        console.log('Elements found - Photos info:', $photosInfo.length, 'Reviews info:', $reviewsSpeedInfo.length, 'Opening hours info:', $openingHoursInfo.length);

        // Show info when data fields are visible
        if ($dataFields.is(':visible')) {
            console.log('Data fields are visible, showing disclaimers');

            // Photos info
            if (apiSource === 'outscraper') {
                console.log('Outscraper API - showing all disclaimers');
                $photosInfoText.html('When using <strong>Outscraper API</strong>, only <strong>1 main photo</strong> will be imported per listing (the primary business photo from Google Maps).');
                $photosInfo.css('display', 'flex');

                // Show reviews speed tip for Outscraper only
                $reviewsSpeedInfo.css('display', 'flex');

                // Show opening hours warning for Outscraper only
                $openingHoursInfo.css('display', 'flex');
            } else {
                console.log('Google API - showing only photos disclaimer');
                $photosInfoText.html('When using <strong>Google Places API</strong>, you can import multiple photos per listing (configured in Settings).');
                $photosInfo.css('display', 'flex');

                // Hide Outscraper-specific warnings for Google API
                $reviewsSpeedInfo.css('display', 'none');
                $openingHoursInfo.css('display', 'none');
            }
        } else {
            console.log('Data fields are hidden, hiding disclaimers');
            $photosInfo.css('display', 'none');
            $reviewsSpeedInfo.css('display', 'none');
            $openingHoursInfo.css('display', 'none');
        }
    }

    // Update photos info when select data toggle changes
    $('#lds_select_data_toggle').on('change', function() {
        console.log('Select data toggle changed, state:', $(this).is(':checked'));
        setTimeout(updatePhotosInfo, 350); // Wait for slideDown animation
    });

    // Initial update on page load - try multiple times to catch different load states
    setTimeout(function() {
        console.log('Initial updatePhotosInfo attempt 1 (100ms)');
        updatePhotosInfo();
    }, 100);

    setTimeout(function() {
        console.log('Initial updatePhotosInfo attempt 2 (500ms)');
        updatePhotosInfo();
    }, 500);

    // Also trigger when document is fully ready
    $(document).ready(function() {
        console.log('Document ready - calling updatePhotosInfo');
        updatePhotosInfo();
    });

});
