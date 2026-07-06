/**
 * AI Chat - Frontend JavaScript
 */
(function($) {
    'use strict';

    /**
     * Debug logging helper - only logs when debug mode is enabled
     */
    const debugLog = function(...args) {
        if (typeof listeoAiSearch !== 'undefined' && listeoAiSearch.debugMode) {
            console.log('[AI Chat Search]', ...args);
        }
    };

    const debugError = function(...args) {
        if (typeof listeoAiSearch !== 'undefined' && listeoAiSearch.debugMode) {
            console.error('[AI Chat Search ERROR]', ...args);
        }
    };

    let searchTimeout;
    let currentRequest;
    
    $(document).ready(function() {
        initializeAISearch();
    });
    
    function initializeAISearch() {
        // Initialize each search container
        $('.ai-chat-search-container').each(function() {
            const $container = $(this);
            setupSearchForm($container);
            setupSuggestions($container);
            updateContainerWidths($container);
        });
        
        // Setup window resize handler
        $(window).on('resize', debounce(function() {
            $('.ai-chat-search-container').each(function() {
                updateContainerWidths($(this));
            });
        }, 250));

        // Listen for enable-filters-button clicks to recalculate widths
        // Use setTimeout to wait for sidebar animation to complete
        $(document).on('click', '.enable-filters-button', function() {
            setTimeout(function() {
                $('.ai-chat-search-container').each(function() {
                    updateContainerWidths($(this));
                });
            }, 350); // Wait for sidebar animation (usually 300ms) plus small buffer
        });
    }
    
    function updateContainerWidths($container) {
        const $formWrapper = $container.find('.ai-search-form-wrapper');
        
        if ($formWrapper.length > 0) {
            const formWidth = $formWrapper.outerWidth();
            
            // Update all dropdown containers to match form wrapper width
            const containers = [
                '.search-results-container',
                '.no-results',
                '.search-error',
                '.search-suggestions',
                '.ai-processing-status'
            ];
            
            containers.forEach(selector => {
                $container.find(selector).css('width', formWidth + 'px');
            });
        }
    }
    
    // Debounce function to limit resize event frequency
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function setupSearchForm($container) {
      const $form = $container.find(".ai-chat-search-form");
      const $input = $container.find(".ai-search-input");
      const $button = $container.find(".ai-search-button");

      // Button click handler for quick_picks and other button actions
      // Use mousedown instead of click to prevent blur event from interfering
      $button.on("mousedown", function (e) {
        const buttonAction = $(this).data("action");
        const query = $input.val().trim();

        // Always prevent form submission and handle with AJAX for AI search
        e.preventDefault();
        e.stopPropagation();

        if (query.length === 0) {
          showValidationPopup($container, listeoAiSearch.strings.type_keywords_first);
          return;
        }

        if (query.length < 2) {
          showError($container, listeoAiSearch.strings.error);
          return;
        }

        performSearch($container, query);
      });

      // Form submission (for Enter key)
      $form.on("submit", function (e) {
        const buttonAction = $button.data("action");
        const query = $input.val().trim();

        // Always prevent form submission and handle with AJAX for AI search
        e.preventDefault();

        if (query.length === 0) {
          showValidationPopup($container, listeoAiSearch.strings.type_keywords_first);
          return;
        }

        if (query.length < 2) {
          showError($container, listeoAiSearch.strings.error);
          return;
        }

        performSearch($container, query);
      });

      // Real-time search suggestions (optional)
      $input.on("input", function () {
        const query = $(this).val().trim();

        clearTimeout(searchTimeout);

        // Always hide results when user starts typing a new query
        hideSearchResults($container);
        hideError($container);

        if (query.length > 0) {
          // Hide popular searches when user has typed anything
          hideSearchSuggestions($container);
        } else {
          // Only show popular searches when input is completely empty
          hideSearchSuggestions($container);
          // Also hide results when input is cleared/empty
          hideSearchResults($container);
          // Reset search tracking when input is cleared
          $container.removeData('lastSearchedQuery');
        }
      });

      // Focus/blur events
      $input.on("focus", function () {
        const query = $(this).val().trim();
        const $resultsContainer = $container.find(".search-results-container");

        // Only show existing results if they exist and match current query
        if (
          $resultsContainer.find(".results-list-ai").children().length > 0 &&
          $container.data("lastSearchedQuery") === query &&
          query.length > 0
        ) {
          fadeInWithSlide($resultsContainer, 300);
        } else if (query.length === 0) {
          // Only show popular searches when input is completely empty
          showSearchSuggestions($container, query);
        }
        // Do nothing if there's text in the input - keep popular searches hidden
      });

      // Blur events - handle Tab and Enter key exits
      $input.on("blur", function () {
        const query = $(this).val().trim();

        // Hide suggestions when losing focus
        hideSearchSuggestions($container);

        // Only trigger search if there's a query AND we're on an AJAX page
        if (query.length > 0) {
          var target = $("div#listeo-listings-container");
          if (target.length > 0) {
            // Check if this query was already searched
            const lastSearchedQuery = $container.data('lastSearchedQuery');
            if (query !== lastSearchedQuery) {
              // AJAX page - show loading and trigger search
              showLoading($container);
              triggerUpdateResults($container, query);
              // Update tracking after search is initiated
              $container.data('lastSearchedQuery', query);
            }
          }
          // Non-AJAX page - do nothing, let user click search manually
        }
      });

      $(document).on("click", function (e) {
        // Improved click outside detection
        if (!$container.is(e.target) && $container.has(e.target).length === 0) {
          hideSearchSuggestions($container);
          hideSearchResults($container);
          hideError($container);
        }
      });

      // Escape key to close results and suggestions
      $input.on("keydown", function (e) {
           const query = $(this).val().trim();

        if (e.key === "Tab" || e.key === "Enter") {
          // Hide suggestions immediately
          hideSearchSuggestions($container);

          // Check if we're on a non-homepage AJAX search form
          const $ajaxForm = $container.closest('form.ajax-search').filter(function() {
                    const classList = ($(this).attr('class') || '').split(/\s+/);
                    // Must have 'ajax-search' and none of the classes can include 'home'
                    debugLog('Class list:', classList);
                    return classList.includes('ajax-search') && !classList.some(cls => cls.includes('home'));
                });

          // Check if we're on homepage by looking at body class
          const isHomepage = $('body').hasClass('home');

          if (e.key === "Enter") {
            // On homepage: let the form submit normally (redirect to search results)
            if (isHomepage) {
              debugLog('Homepage detected (body.home) - allowing normal form submission');
              // Don't prevent default - let the form submit normally
              // Just hide suggestions and let it go
              return;
            }

            // Not on homepage: prevent default and handle with AJAX
            e.preventDefault();

            if (query.length === 0) {

                if ($ajaxForm.length > 0) {
                    showLoading($container);
                    triggerUpdateResults($container);
                }
                showValidationPopup($container, listeoAiSearch.strings.type_keywords_first);
              return;

            }

            if (query.length < 2) {

            if ($ajaxForm.length > 0) {
              showLoading($container);
            }
              showError($container, listeoAiSearch.strings.error);
              return;
            }

            // Perform search for Enter - same as blur behavior
            // Check if this query was already searched
            const lastSearchedQuery = $container.data('lastSearchedQuery');
            if (query !== lastSearchedQuery) {
              showLoading($container);
              triggerUpdateResults($container, query);
              // Update tracking after search is initiated
              $container.data('lastSearchedQuery', query);
            }
          } else if (e.key === "Tab" ) {
            // For Tab key, trigger update_results action
            triggerUpdateResults($container);
          }
        } else if (e.key === "Escape") {
          hideSearchSuggestions($container);
          hideSearchResults($container);
          hideError($container);
          $(this).blur(); // Remove focus
        }
      });

      // Search submit button click handler (for universal shortcode)
      $container.find('.ai-search-submit-btn').on('click', function() {
        const query = $input.val().trim();

        hideSearchSuggestions($container);

        if (query.length < 2) {
          showValidationPopup($container, listeoAiSearch.strings.type_keywords_first);
          return;
        }

        // Perform search
        const lastSearchedQuery = $container.data('lastSearchedQuery');
        if (query !== lastSearchedQuery) {
          showLoading($container);
          triggerUpdateResults($container, query);
          $container.data('lastSearchedQuery', query);
        }
      });
    }

    function triggerUpdateResults($container, query) {
        var target = $("div#listeo-listings-container");
        var isUniversal = $container.data('universal') === true;

        // Universal shortcode: always perform inline search, never redirect
        if (isUniversal) {
            debugLog('Universal shortcode - performing inline search');
            if (query && query.length >= 2) {
                performSearch($container, query);
            }
            return;
        }

        // Check if we're on an AJAX-enabled page
        if (target.length === 0) {
            // No AJAX container found - redirect to search page
            debugLog('No AJAX container found, redirecting to search page');

            // Hide loading since we're redirecting
            hideLoading($container);

            // Get the site's search URL and redirect with the query
            var searchUrl = listeoAiSearch.search_url || '/listings/';
            var separator = searchUrl.includes('?') ? '&' : '?';

            // Build proper AI search URL parameters
            var params = 'location_search=&ai_search_input=' + encodeURIComponent(query || '') + '&action=listeo_get_listings';
            window.location.href = searchUrl + separator + params;
            return;
        }

        // AJAX container exists - proceed with normal AJAX behavior
        target.triggerHandler("update_results", [1, false]);

        // wait for ajax call to finish
        // Listen for the AJAX completion of the 'listeo_get_listings' action
        $(document).on('ajaxComplete', function(event, xhr, settings) {
            if (settings && settings.data && settings.data.indexOf('action=listeo_get_listings') !== -1) {
            hideLoading($container);
            }
        });
    }


    
    function setupSuggestions($container) {
        // Click on suggestion tags
        $container.on('click', '.suggestion-tag', function() {
            const suggestion = $(this).text();
            const buttonAction = $container.data('button-action') || 'quick_picks';
            const suggestionBehavior = $container.find('.search-suggestions').data('behavior') || buttonAction;
            
            // Set the search input value
            $container.find('.ai-search-input').val(suggestion);
            
            // Hide search suggestions since user made their choice
            hideSearchSuggestions($container);
            
            // Behavior based on the button_action setting
            if (suggestionBehavior === 'popular_searches') {
                // For popular_searches mode: check if we're on an AJAX-capable page
                const isAjaxPage = $('div#listeo-listings-container').length > 0;
                
                if (isAjaxPage) {
                    // On AJAX pages: trigger search like Enter key
                    // Check if this suggestion was already searched
                    const lastSearchedQuery = $container.data('lastSearchedQuery');
                    if (suggestion !== lastSearchedQuery) {
                        debugLog('Popular search selected on AJAX page:', suggestion);
                        showLoading($container);
                        triggerUpdateResults($container, suggestion);
                        // Update tracking after search is initiated
                        $container.data('lastSearchedQuery', suggestion);
                    } else {
                        debugLog('Popular search skipped (already searched):', suggestion);
                    }
                } else {
                    // On non-AJAX pages: just fill input and let user decide when to search
                    // Input is already filled above, no automatic search triggered
                }
            } else if (suggestionBehavior === 'quick_picks') {
                // For quick_picks mode: trigger AI search
                const $form = $container.closest('form.ajax-search').filter(function() {
                    const classList = ($(this).attr('class') || '').split(/\s+/);
                    debugLog('Class list:', classList);
                    return classList.includes('ajax-search') && !classList.some(cls => cls.includes('home'));
                });
                
                if ($form.length > 0) {
                    showLoading($container);
                    triggerUpdateResults($container, suggestion);
                } else {
                    // Perform AI search
                    performSearch($container, suggestion);
                }
            }
        });
    }

    function performSearch($container, query, offset = 0) {
        debugLog('performSearch called with:', {query, offset});
        
        // Cancel previous request
        if (currentRequest) {
            currentRequest.abort();
        }
        
        showLoading($container);
        hideError($container);
        
        const useAI = listeoAiSearch.ai_enabled || false; // Use AI only if enabled in admin settings
        const maxResults = parseInt(listeoAiSearch.max_results) || 10; // Use admin setting for max results
        const listingTypes = $container.data('types') || 'all';
        const debugMode = $container.data('debug') === 'true';
        
        debugLog('Search params:', {useAI, maxResults, listingTypes, debugMode});
        debugLog('Raw debug data attribute:', $container.data('debug'));
        debugLog('Debug mode enabled:', debugMode);
        
        // Update debug panel if enabled (server-side logging only)
        if (debugMode) {
            debugLog('Debug mode enabled, updating panel');
            // Debug info is now logged server-side only
        }
        
        const data = {
            action: 'listeo_ai_search',
            nonce: listeoAiSearch.nonce,
            query: query,
            use_ai: useAI,
            limit: maxResults, // Use admin setting instead of container data
            offset: offset,
            listing_types: listingTypes,
            debug: debugMode,
            ai_search_hp: $container.find('input[name="ai_search_hp"]').val() || ''
        };
        
        debugLog('AJAX data:', data);
        
        currentRequest = $.ajax({
            url: listeoAiSearch.ajax_url,
            type: 'POST',
            data: data,
            timeout: 30000
        })
        .done(function(response) {
            debugLog('AJAX response:', response);
            hideLoading($container);
            
            if (response.success) {
                displayResults($container, response.data, offset === 0);
                
                // Debug panel removed - debug info is logged server-side only
                
                if (response.data.is_fallback) {
                    showFallbackNotice($container, response.data.fallback_reason);
                }
            } else {
                debugError('Search failed:', response);
                showError($container, response.data || listeoAiSearch.strings.error);
            }
        })
        .fail(function(xhr, status, error) {
            debugError('AJAX failed:', {xhr, status, error});
            hideLoading($container);
            
            if (status !== 'abort') {
                showError($container, listeoAiSearch.strings.error);
            }
        })
        .always(function() {
            currentRequest = null;
        });
    }
    
    function displayResults($container, data, clearPrevious = true) {
        const $resultsContainer = $container.find('.search-results-container');
        const $resultsList = $container.find('.results-list-ai');
        const $resultsCountText = $container.find('.results-count-text');
        const $queryHighlight = $container.find('.query-highlight');
        const $noResults = $container.find('.no-results');
        
        // Track the search query for this container
        $container.data('lastSearchedQuery', data.query || '');
        
        // Hide no results using animation
        fadeOutWithSlide($noResults, 300);
        
        // Always hide search suggestions when showing results
        hideSearchSuggestions($container);
        
        if (clearPrevious) {
            $resultsList.empty();
        }
        
        if (data.listings && data.listings.length > 0) {
            // Log the displayed listing IDs for debugging
            const displayedIds = data.listings.map(listing => listing.id);
            debugLog(`FRONTEND DISPLAY: Showing ${data.listings.length} results`);
            debugLog(`FRONTEND DISPLAY: Displayed listing IDs: [${displayedIds.join(', ')}]`);
            
            // Update header with count and query
            const count = data.listings.length;
            const queryText = data.query || 'your search';
            
            // Use proper singular/plural localized strings
            let countText;
            if (count === 1) {
                countText = listeoAiSearch.strings.top_listing_singular;
            } else {
                countText = listeoAiSearch.strings.top_listings_plural.replace('%d', count);
            }
            $resultsCountText.text(countText);
            $queryHighlight.text(`"${queryText}"`);
            
            // Add listings
            data.listings.forEach((listing, index) => {
                $resultsList.append(createListingCard(listing, data.search_type, index));
            });
            
            fadeInWithSlide($resultsContainer, 300);
            
            // Auto-scroll removed - let user stay at current position
            
        } else {
            fadeInWithSlide($noResults, 300);
            fadeOutWithSlide($resultsContainer, 300);
            // Also hide suggestions when showing no results
            hideSearchSuggestions($container);
            
            // Auto-hide no results after 3 seconds
            setTimeout(() => {
                fadeOutWithSlide($noResults, 300);
            }, 3000);
        }
        
        hideSearchSuggestions($container);
    }
    
    function createListingCard(listing, searchType, index = 0) {
        const thumbnail = listing.thumbnail || listeoAiSearch.default_thumbnail || '';
        const rating = formatRating(listing.rating);

        // Create match badge only for top 3 results
        let matchBadge = '';
        if (listing.best_match && index < 3) {
            matchBadge = `<div class="match-badge best">${listeoAiSearch.strings.best_match}</div>`;
        }

        // Check if this is a WooCommerce product
        if (listing.post_type === 'product') {
            return createProductCard(listing, matchBadge, thumbnail, rating);
        }

        // Default: Listeo listing card (unchanged)
        const price = formatPrice(listing.price_min, listing.price_max);

        // Only render thumbnail div if we have a valid thumbnail URL
        const thumbnailHtml = thumbnail ? `
                <div class="listing-thumbnail-ai">
                    <img src="${thumbnail}" alt="${listing.title}" loading="lazy">
                </div>` : '';

        return `
            <a href="${listing.permalink}" class="listing-item-ai${!thumbnail ? ' no-thumbnail' : ''}" data-listing-id="${listing.id}">
                ${thumbnailHtml}
                <div class="listing-details-ai">
                    <div class="listing-main-ai">
                        <h3 class="listing-title-ai">
                            ${listing.title}
                            ${matchBadge ? `<div class="match-badge best compact">${listeoAiSearch.strings.best_match}</div>` : ''}
                        </h3>
                        <p class="listing-excerpt-ai">${listing.excerpt}</p>
                        <div class="listing-meta-ai">
                            ${listing.address ? `<span class="address"><i class="fa fa-map-marker"></i> ${listing.address}</span>` : ''}
                            ${rating ? `<span class="rating-ai"><svg class="rating-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> ${parseFloat(rating).toFixed(1)}</span>` : ''}
                        </div>
                    </div>
                    <div class="listing-sidebar-ai">
                        ${matchBadge}
                        ${price ? `<div class="price">${price}</div>` : ''}
                    </div>
                </div>
            </a>
        `;
    }

    /**
     * Create WooCommerce product card for search results
     * Uses same HTML structure as chatbot for consistent styling
     */
    function createProductCard(product, matchBadge, thumbnail, rating) {
        // Price display (with sale price handling) - same as chatbot
        let priceHtml = '';
        if (product.on_sale && product.sale_price_formatted) {
            priceHtml = `<span class="product-price"><span class="regular-price">${product.regular_price_formatted}</span> <span class="sale-price">${product.sale_price_formatted}</span></span>`;
        } else if (product.price_formatted) {
            priceHtml = `<span class="product-price">${product.price_formatted}</span>`;
        }

        // Stock status - same as chatbot
        let stockHtml = '';
        if (product.stock_status === 'instock') {
            stockHtml = `<span class="stock-status in-stock"><svg class="stock-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> ${listeoAiSearch.strings.inStock || 'In Stock'}</span>`;
        } else if (product.stock_status === 'outofstock') {
            stockHtml = `<span class="stock-status out-of-stock"><svg class="stock-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg> ${listeoAiSearch.strings.outOfStock || 'Out of Stock'}</span>`;
        }

        // Only render thumbnail div if we have a valid thumbnail URL
        const thumbnailHtml = thumbnail ? `
                <div class="listeo-ai-listing-thumbnail">
                    <img src="${thumbnail}" alt="${product.title}" loading="lazy">
                </div>` : '';

        // Use same HTML structure as chatbot (listeo-ai-listing-* classes)
        return `
            <a href="${product.permalink}" class="listeo-ai-listing-item${!thumbnail ? ' no-thumbnail' : ''}" data-listing-id="${product.id}">
                ${thumbnailHtml}
                <div class="listeo-ai-listing-details">
                    <div class="listeo-ai-listing-main">
                        <h3 class="listeo-ai-listing-title">
                            ${product.title}
                            ${matchBadge ? `<div class="match-badge best">${listeoAiSearch.strings.best_match}</div>` : ''}
                        </h3>
                        <p class="listeo-ai-listing-excerpt">${product.excerpt || ''}</p>
                        <div class="listeo-ai-listing-meta">
                            ${priceHtml}
                            ${stockHtml}
                            ${rating ? `<span class="listeo-ai-listing-rating"><svg class="rating-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> ${parseFloat(rating).toFixed(1)}</span>` : ''}
                        </div>
                    </div>
                </div>
            </a>
        `;
    }
    
    function showLoading($container) {
        // Keep button text static - don't change it to "AI analyzing..."
        $container.find('.ai-search-button').prop('disabled', true).addClass('loading-ai');
        
        // Hide search suggestions when search starts
        hideSearchSuggestions($container);
        
        // Hide search results when new search starts
        hideSearchResults($container);
        
        // Show processing status with animation
        const $status = $container.find('.ai-processing-status');
        fadeInWithSlide($status, 300);
        
        startProcessingAnimation($container);
    }
    
    function hideLoading($container) {
        // Button text stays static - no need to restore it
        $container.find('.ai-search-button').prop('disabled', false).removeClass('loading-ai');
        
        // Hide processing status with animation
        const $status = $container.find('.ai-processing-status');
        fadeOutWithSlide($status, 300);
        
        resetProcessingSteps($container);
    }
    
    function startProcessingAnimation($container) {
        const $steps = $container.find('.processing-step');
        let currentStep = 0;
        $steps.removeClass('active completed');
        $steps.eq(0).addClass('active');

        function nextStep() {
            if (currentStep < $steps.length - 1) {
                $steps.eq(currentStep).removeClass('active').addClass('completed');
                currentStep++;
                $steps.eq(currentStep).addClass('active');
            }
        }

        // Step 1: 1.5s
        setTimeout(function() {
            nextStep();
            // Step 2: 1.5s
            setTimeout(function() {
                nextStep();
                // Step 3: leave active until search completes
            }, 1500);
        }, 1500);

        $container.data('step-interval', 'custom-timing');
    }
    
    function resetProcessingSteps($container) {
        // Clear any running animation
        const stepInterval = $container.data('step-interval');
        if (stepInterval) {
            clearInterval(stepInterval);
            $container.removeData('step-interval');
        }
        
        // Reset all steps
        $container.find('.processing-step').removeClass('active completed');
    }
    
    function showError($container, message) {
        const $errorDiv = $container.find('.search-error');
        
        // Determine error type and provide helpful message
        let errorMessage = message;
        let errorType = 'error';
        
        if (typeof message === 'object' && message.message) {
            errorMessage = message.message;
        } else if (typeof message === 'string') {
            // Provide more specific error messages
            if (message.includes('Rate limit')) {
                errorMessage = listeoAiSearch.strings.rateLimitError;
                errorType = 'warning';
            } else if (message.includes('API')) {
                errorMessage = listeoAiSearch.strings.apiUnavailable;
                errorType = 'info';
            } else if (message.includes('Security')) {
                errorMessage = listeoAiSearch.strings.sessionExpired;
                errorType = 'error';
            } else if (message.includes('characters')) {
                errorType = 'validation';
            }
        }
        
        const errorIcon = errorType === 'warning' ? 'fa-exclamation-triangle' : 
                         errorType === 'info' ? 'fa-info-circle' : 'fa-times-circle';
        
        if ($errorDiv.length === 0) {
            $container.append(`
                <div class="search-error search-error--${errorType}">
                    <i class="fa ${errorIcon}"></i>
                    <span class="error-message">${errorMessage}</span>
                </div>
            `);
        } else {
            $errorDiv.removeClass('search-error--error search-error--warning search-error--info search-error--validation')
                   .addClass(`search-error--${errorType}`)
                   .html(`
                       <i class="fa ${errorIcon}"></i>
                       <span class="error-message">${errorMessage}</span>
                   `);
        }
        
        // Use animated fade-in for error messages
        fadeInWithSlide($container.find('.search-error'), 300);
        
        // Auto-hide after delay (longer for validation errors)
        const hideDelay = errorType === 'validation' ? 3000 : 5000;
        setTimeout(() => hideError($container), hideDelay);
    }
    
    function hideError($container) {
        const $errorDiv = $container.find('.search-error');
        fadeOutWithSlide($errorDiv, 300);
    }
    
    function showFallbackNotice($container, reason) {
        const notice = `
            <div class="fallback-notice alert alert-info">
                <i class="fa fa-info-circle"></i>
                ${reason} - ${listeoAiSearch.strings.fallbackNotice}
            </div>
        `;

        $container.find('.results-header').prepend(notice);

        setTimeout(() => {
            $container.find('.fallback-notice').fadeOut();
        }, 8000);
    }
    
    function showSearchSuggestions($container, query) {
        // This could be enhanced with actual AI-powered suggestions
        const $suggestions = $container.find('.search-suggestions');
        fadeInWithSlide($suggestions, 300);
    }
    
    function hideSearchSuggestions($container) {
        const $suggestions = $container.find('.search-suggestions');
        fadeOutWithSlide($suggestions, 300);
    }
    
    function hideSearchResults($container) {
        const $results = $container.find('.search-results-container');
        const $noResults = $container.find('.no-results');
        fadeOutWithSlide($results, 300);
        fadeOutWithSlide($noResults, 300);
    }
    
    // CSS transition-based animation functions (no jQuery animations)
    function fadeInWithSlide($element, duration = 300) {
        // Stop any running jQuery animations first
        $element.stop(true, false);
        
        // Reset to initial state and show element
        $element.removeClass('ai-search-visible').css('display', 'block');
        
        // Force reflow to ensure display:block is applied before transition
        $element[0].offsetHeight;
        
        // Add visible class to trigger CSS transition
        $element.addClass('ai-search-visible');
    }
    
    function fadeOutWithSlide($element, duration = 300) {
        if (!$element.is(':visible')) return; // Don't animate if already hidden
        
        // Stop any running jQuery animations first
        $element.stop(true, false);
        
        // Remove visible class to trigger CSS transition
        $element.removeClass('ai-search-visible');
        
        // Hide element after transition completes
        setTimeout(() => {
            if (!$element.hasClass('ai-search-visible')) {
                $element.css('display', 'none');
            }
        }, duration);
    }
    
    function formatPrice(minPrice, maxPrice) {
        if (!minPrice && !maxPrice) return '';
        
        if (minPrice && maxPrice && minPrice !== maxPrice) {
            return `$${minPrice} - $${maxPrice}`;
        } else if (minPrice) {
            return `From $${minPrice}`;
        } else if (maxPrice) {
            return `Up to $${maxPrice}`;
        }
        
        return '';
    }
    
    function formatRating(rating) {
        if (!rating || rating === '0') return '';
        
        const stars = Math.round(parseFloat(rating));
        return `${rating} (${stars}★)`;
    }
    
    function showValidationPopup($container, message) {
        // Remove any existing popup
        $container.find('.validation-popup').remove();
        
        // Create popup element
        const $popup = $(`
            <div class="validation-popup">
                <span>${message}</span>
            </div>
        `);
        
        // Position it in the button container
        const $button = $container.find('.ai-search-button');
        const $btnContainer = $container.find('.ai-btn-container');
        $btnContainer.append($popup);
        
        // Show with animation and hide button
        setTimeout(() => {
            $popup.addClass('show');
            $button.addClass('tooltip-visible');
            $btnContainer.addClass('ai-btn-error');
        }, 10);
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            $popup.removeClass('show');
            $button.removeClass('tooltip-visible');
            $btnContainer.removeClass('ai-btn-error');
            setTimeout(() => {
                $popup.remove();
            }, 300);
        }, 3000);
        
        // Also hide when user starts typing
        const $input = $container.find('.ai-search-input');
        const hidePopup = () => {
            $popup.removeClass('show');
            $button.removeClass('tooltip-visible');
            $btnContainer.removeClass('ai-btn-error');
            setTimeout(() => {
                $popup.remove();
            }, 300);
            $input.off('input.validation');
        };
        
        $input.on('input.validation', hidePopup);
    }

})(jQuery);
