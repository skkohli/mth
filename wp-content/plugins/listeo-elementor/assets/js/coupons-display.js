/*!
 * Listeo Coupons Display Widget JavaScript
 * Handles carousel initialization and coupon interactions
 */

(function($) {
    'use strict';



    // New Carousel Nav With Arrows - only add if not already added
    $(".listeo-coupons-carousel").not('.listeo-controls-added').each(function() {
        $(this).addClass('listeo-controls-added').append(
            "" +
              "<div class='slider-controls-container'>" +
              "<div class='slider-controls'>" +
              "<button type='button' class='slide-m-prev'></button>" +
              "<div class='slide-m-dots'></div>" +
              "<button type='button' class='slide-m-next'></button>" +
              "</div>" +
              "</div>"
          );
    });

    // Coupons Carousel - only initialize if not already initialized
    $(".listeo-coupons-carousel").not('.slick-initialized').each(function () {
        var slides = $(this).data("slides") || 4;

        $(this).slick({
          infinite: true,
          slidesToShow: slides,
          slidesToScroll: slides,
          dots: $(this).hasClass('dots-nav'),
          arrows: $(this).hasClass('arrows-nav'),
          slide: ".fw-carousel-item",
          appendDots: $(this).find(".slide-m-dots"),
          prevArrow: $(this).find(".slide-m-prev"),
          nextArrow: $(this).find(".slide-m-next"),
          autoplay: $(this).data('autoplay') === 'yes',
          autoplaySpeed: $(this).data('autoplay-speed') || 3000,
          responsive: [
            {
              breakpoint: 1200,
              settings: {
                slidesToShow: Math.min(slides, 3),
                slidesToScroll: Math.min(slides, 3)
              }
            },
            {
              breakpoint: 992,
              settings: {
                slidesToShow: Math.min(slides, 2),
                slidesToScroll: Math.min(slides, 2)
              }
            },
            {
              breakpoint: 768,
              settings: {
                slidesToShow: 1,
                slidesToScroll: 1
              }
            }
          ]
        });
      });
    /**
     * Listeo Coupons Widget Handler
     */
    var ListeoCouponsWidget = {
        
        /**
         * Initialize the widget
         */
        init: function() {
            this.initCarousels();
            this.bindEvents();
            this.addAccessibility();
        },
        
        /**
         * Initialize carousel functionality
         * Theme handles carousel initialization via custom.js
         */
        initCarousels: function() {
            // Carousel initialization is now handled by theme's custom.js
            // This ensures consistency with other theme carousels
        },
        
        /**
         * Bind event listeners
         */
        bindEvents: function() {
            var self = this;

            // Prevent duplicate binding
            if (this._eventsBound) {
                return;
            }
            this._eventsBound = true;

            // Track coupon card clicks for analytics - use namespaced event
            $(document).off('click.listeoCoupons').on('click.listeoCoupons', '.listeo-coupon-card', function(e) {
                var listingUrl = $(this).attr('href');
                var couponId = $(this).data('coupon-id');

                // Track event if analytics available
                self.trackCouponInteraction('view', couponId);
            });

            // Reinitialize on Elementor preview refresh - ONLY for coupons widget
            if (typeof elementor !== 'undefined' && !this._elementorHookAdded) {
                this._elementorHookAdded = true;
                elementor.hooks.addAction('panel/open_editor/widget/listeo-coupons-display', function() {
                    setTimeout(function() {
                        self.initCarousels();
                    }, 100);
                });
            }

            // Handle window resize for carousel - use namespaced event
            $(window).off('resize.listeoCoupons').on('resize.listeoCoupons', $.debounce(250, function() {
                $('.listeo-coupons-carousel.slick-initialized').slick('refresh');
            }));
        },
        

        
        /**
         * Track coupon interaction for analytics
         */
        trackCouponInteraction: function(action, couponCode) {
            // Google Analytics 4
            if (typeof gtag === 'function') {
                gtag('event', 'coupon_interaction', {
                    'action': action,
                    'coupon_code': couponCode,
                    'event_category': 'Coupons'
                });
            }
            
            // Universal Analytics fallback
            if (typeof ga === 'function') {
                ga('send', 'event', 'Coupons', action, couponCode);
            }
            
            // Custom event for other tracking
            $(document).trigger('listeo_coupon_interaction', {
                action: action,
                couponCode: couponCode
            });
        },
        
        /**
         * Add accessibility features
         */
        addAccessibility: function() {
            // Add ARIA labels for the card links
            $('.listeo-coupon-card').attr({
                'aria-label': function() {
                    var companyName = $(this).find('.coupon-company-name').text();
                    var discount = $(this).find('.coupon-discount-badge').text();
                    return 'View coupon for ' + companyName + ': ' + discount;
                }
            });
        },
        
        /**
         * Refresh carousels (for dynamic content)
         */
        refreshCarousels: function() {
            $('.listeo-coupons-carousel.slick-initialized').each(function() {
                $(this).slick('refresh');
            });
        },
        
        /**
         * Destroy and reinitialize carousels
         * NOTE: Theme handles initialization, so we just trigger theme's carousel init
         */
        reinitializeCarousels: function() {
            $('.listeo-coupons-carousel.slick-initialized').each(function() {
                $(this).slick('unslick').removeClass('listeo-carousel-initialized');
            });
            
            // Let theme reinitialize the carousels
            setTimeout(function() {
                if (typeof window.listeoInitCarousels === 'function') {
                    window.listeoInitCarousels();
                }
            }, 100);
        }
    };
    
    /**
     * Utility: Debounce function
     */
    $.debounce = function(threshold, func, execAsap) {
        var timeout;
        return function debounced() {
            var obj = this, args = arguments;
            function delayed() {
                if (!execAsap) func.apply(obj, args);
                timeout = null;
            }
            if (timeout) {
                clearTimeout(timeout);
            } else if (execAsap) {
                func.apply(obj, args);
            }
            timeout = setTimeout(delayed, threshold || 100);
        };
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ListeoCouponsWidget.init();
    });
    
    /**
     * Elementor integration
     */
    $(window).on('elementor/frontend/init', function() {
        // Initialize on Elementor preview
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-coupons-display.default', function($scope) {
            ListeoCouponsWidget.init();
            // Also initialize tabs if present
            CouponsTabsWidget($scope, $);
        });
        
        // Handle Elementor editor events
        if (elementorFrontend.isEditMode()) {
            elementor.hooks.addAction('panel/open_editor/widget/listeo-coupons-display', function(panel, model, view) {
                setTimeout(function() {
                    ListeoCouponsWidget.reinitializeCarousels();
                    // Also reinitialize tabs for editor
                    $('[data-id="' + model.id + '"] .coupons-container').each(function() {
                        CouponsTabsWidget($(this), $);
                    });
                }, 100);
            });
            
            // Handle display type changes in editor
            elementor.hooks.addAction('panel/editor/change/listeo-coupons-display', function(panel, model, view) {
                var changedAttributes = model.changedAttributes();
                if (changedAttributes.display_type) {
                    setTimeout(function() {
                        var $scope = $('[data-id="' + model.id + '"]');
                        // Reinitialize appropriate widget based on new display type
                        if (changedAttributes.display_type === 'tabs') {
                            CouponsTabsWidget($scope, $);
                        } else if (changedAttributes.display_type === 'carousel') {
                            ListeoCouponsWidget.reinitializeCarousels();
                        }
                    }, 200);
                }
            });
        }
    });
    
    /**
     * Re-initialize on AJAX complete (for compatibility with other plugins)
     */
    $(document).ajaxComplete(function(event, xhr, settings) {
        // Check if the response likely contains coupon widgets
        if (settings.url && (
            settings.url.indexOf('listeo') !== -1 || 
            settings.url.indexOf('coupon') !== -1 ||
            settings.url.indexOf('elementor') !== -1
        )) {
            setTimeout(function() {
                ListeoCouponsWidget.init();
                // Reinitialize tabs for any new content
                $('.coupons-container').each(function() {
                    CouponsTabsWidget($(this), $);
                });
            }, 100);
        }
    });
    
    /**
     * Handle page visibility change (pause/resume autoplay)
     */
    $(document).on('visibilitychange', function() {
        var isHidden = document.hidden;
        $('.listeo-coupons-carousel.slick-initialized').each(function() {
            var $carousel = $(this);
            if (isHidden) {
                $carousel.slick('slickPause');
            } else if ($carousel.slick('slickCurrentSlide') !== undefined) {
                $carousel.slick('slickPlay');
            }
        });
    });
    
    /**
     * Expose widget instance for external access
     */
    window.ListeoCouponsWidget = ListeoCouponsWidget;
    
    /**
     * Custom event handlers for developers
     */
    $(document).on('listeo_coupons_refresh', function() {
        ListeoCouponsWidget.refreshCarousels();
    });
    
    $(document).on('listeo_coupons_reinit', function() {
        ListeoCouponsWidget.reinitializeCarousels();
    });
    
    /**
     * Coupons Tabs Widget Handler
     * Adapted from taxonomy-tabs.js for coupons-specific functionality
     */
    var CouponsTabsWidget = function($scope, $) {
        var $container = $scope.find('.coupons-container');
        
        if ($container.length === 0) {
            return;
        }

        var $navItems = $container.find('.coupons-nav-item');
        var $tabContents = $container.find('.coupons-tab-content');

        // Initialize
        init();

        function init() {
            applyMobileHiding();
            bindEvents();
            
            // Check if we're in Elementor editor mode
            var isEditorMode = typeof elementorFrontend !== 'undefined' && elementorFrontend.isEditMode();
            
            if (isEditorMode) {
                // Use longer delays for editor to ensure DOM is ready
                setTimeout(function() {
                    updateContentAreaHeight();
                    // Remove loading state after height calculation
                    setTimeout(function() {
                        $container.removeClass('coupons-loading');
                    }, 300);
                }, 200);
            } else {
                // Regular frontend timing
                setTimeout(function() {
                    updateContentAreaHeight();
                    // Remove loading state after height calculation
                    setTimeout(function() {
                        $container.removeClass('coupons-loading');
                    }, 200);
                }, 100);
            }
        }
        
        function applyMobileHiding() {
            var isMobile = window.innerWidth <= 992;
            
            $tabContents.each(function() {
                var $tabContent = $(this);
                var $allCoupons = $tabContent.find('.coupon-grid-item');
                var $showMoreBtn = $tabContent.find('.coupons-show-more-btn');
                
                // Store original PHP state on first run
                if (!$tabContent.data('original-state-saved')) {
                    $allCoupons.each(function() {
                        var $coupon = $(this);
                        $coupon.data('original-hidden', $coupon.hasClass('coupon-hidden'));
                    });
                    $tabContent.data('original-state-saved', true);
                }
                
                if (isMobile) {
                    // On mobile, hide all coupons except the first one
                    $allCoupons.each(function(index) {
                        var $coupon = $(this);
                        if (index > 0) { // Hide all except first (index 0)
                            $coupon.addClass('coupon-hidden');
                        } else {
                            $coupon.removeClass('coupon-hidden');
                        }
                    });
                    
                    // Show the "Show More" button if there are 2+ coupons
                    if ($allCoupons.length >= 2 && $showMoreBtn.length) {
                        $showMoreBtn.show();
                    }
                } else {
                    // Desktop: restore original PHP hiding state
                    $allCoupons.each(function() {
                        var $coupon = $(this);
                        var originallyHidden = $coupon.data('original-hidden');
                        
                        if (originallyHidden) {
                            $coupon.addClass('coupon-hidden');
                        } else {
                            $coupon.removeClass('coupon-hidden');
                        }
                    });
                    
                    // Restore original button visibility
                    var hasHiddenCoupons = $tabContent.find('.coupon-grid-item.coupon-hidden').length > 0;
                    if (hasHiddenCoupons && $showMoreBtn.length) {
                        $showMoreBtn.show();
                    } else if ($showMoreBtn.length) {
                        $showMoreBtn.hide();
                    }
                }
            });
        }

        function bindEvents() {
            $navItems.on('click', function(e) {
                e.preventDefault();
                var $this = $(this);
                var categoryId = $this.data('category');
                
                switchTab(categoryId, $this);
            });

            // Keyboard navigation
            $navItems.on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            // Touch support for mobile
            if ('ontouchstart' in window) {
                $navItems.on('touchstart', function() {
                    $(this).addClass('touch-active');
                });

                $navItems.on('touchend', function() {
                    $(this).removeClass('touch-active');
                });
            }
            
            // Handle "Show More" button clicks
            $container.on('click', '.coupons-show-more-btn', function(e) {
                e.preventDefault();
                var $button = $(this);
                var $tabContent = $button.closest('.coupons-tab-content');
                var increment = parseInt($button.data('increment')) || 4;
                var showMoreText = $button.data('show-more-text') || 'Show More';
                
                // Get currently hidden coupons (unified single hiding system)
                var $hiddenCoupons = $tabContent.find('.coupon-grid-item.coupon-hidden');
                var $couponsToShow = $hiddenCoupons.slice(0, increment);
                
                if ($couponsToShow.length === 0) {
                    return; // No coupons to show
                }
                
                // Check if this will be the last batch - hide button immediately if so
                var remainingCount = $hiddenCoupons.length - $couponsToShow.length;
                if (remainingCount <= 0) {
                    $button.hide(); // Hide instantly, no animation
                }
                
                // Pre-calculate the new height by temporarily showing coupons
                $couponsToShow.css('visibility', 'hidden').removeClass('coupon-hidden');
                
                // Update content area height first (before making coupons visible)
                setTimeout(function() {
                    updateContentAreaHeight();
                    
                    // Then make the coupons visible with a small delay to allow height animation
                    setTimeout(function() {
                        $couponsToShow.css('visibility', 'visible');
                    }, 50);
                }, 10);
                
                // Update button text if there are still more coupons
                if (remainingCount > 0) {
                    $button.html(showMoreText + ' <i class="fas fa-chevron-down"></i>');
                }
                
                // Track show more interaction for analytics
                if (typeof gtag === 'function') {
                    gtag('event', 'coupon_show_more', {
                        'tab_category': $tabContent.attr('id'),
                        'coupons_shown': $couponsToShow.length,
                        'event_category': 'Coupons'
                    });
                }
            });

            // Handle window resize
            var resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    applyMobileHiding();
                    updateContentAreaHeight();
                }, 250);
            });
        }

        function switchTab(categoryId, $activeNav) {
            // Prevent multiple rapid clicks
            if ($container.hasClass('switching')) {
                return;
            }
            
            $container.addClass('switching');
            
            // Remove active class from all nav items and tab contents
            $navItems.removeClass('active');
            $tabContents.removeClass('active');
            
            // Add active class to clicked nav item
            $activeNav.addClass('active');
            
            // Get new tab content
            var $newTab = $container.find('#coupons-category-' + categoryId);
            
            if ($newTab.length === 0) {
                $container.removeClass('switching');
                return;
            }
            
            // Add active class to new tab (CSS handles all animation)
            $newTab.addClass('active');
            
            // Update content area height after content animation starts
            setTimeout(function() {
                updateContentAreaHeight();
            }, 200);
            
            // Remove switching class after animation is complete
            setTimeout(function() {
                $container.removeClass('switching');
            }, 400);
            
            // Trigger custom event
            $container.trigger('coupons-tabs:tab-changed', [categoryId, $activeNav]);
            
            // Track tab switch for analytics
            if (typeof gtag === 'function') {
                gtag('event', 'coupon_tab_switch', {
                    'category_id': categoryId,
                    'event_category': 'Coupons'
                });
            }
        }

        function updateContentAreaHeight() {
            var $contentArea = $container.find('.coupons-content-area');
            var $activeTab = $tabContents.filter('.active');
            
            if ($activeTab.length) {
                // Wait for the tab to be fully rendered and animated
                setTimeout(function() {
                    // Temporarily remove absolute positioning to get natural height
                    var originalPosition = $activeTab.css('position');
                    var originalTop = $activeTab.css('top');
                    var originalLeft = $activeTab.css('left');
                    var originalRight = $activeTab.css('right');
                    
                    $activeTab.css({
                        'position': 'relative',
                        'top': 'auto',
                        'left': 'auto',
                        'right': 'auto'
                    });
                    
                    // Force a reflow
                    $activeTab[0].offsetHeight;
                    
                    // Get the natural height
                    var naturalHeight = $activeTab.outerHeight(true);
                    
                    // Restore original positioning
                    $activeTab.css({
                        'position': originalPosition,
                        'top': originalTop,
                        'left': originalLeft,
                        'right': originalRight
                    });
                    
                    // Set CSS custom property for smooth transition
                    var newHeight = naturalHeight + 20;
                    $contentArea[0].style.setProperty('--content-height', newHeight + 'px');
                }, 50);
            }
        }

        // Expose public methods
        $container.data('coupons-tabs', {
            switchTab: switchTab,
            updateHeight: updateContentAreaHeight
        });
    };

    // Initialize tabs on document ready for non-Elementor pages
    $(document).ready(function() {
        $('.coupons-container').each(function() {
            CouponsTabsWidget($(this), $);
        });
    });

    // Expose CouponsTabsWidget globally
    window.CouponsTabsWidget = CouponsTabsWidget;
    
})(jQuery);

/**
 * Vanilla JavaScript fallback for basic functionality
 * (in case jQuery is not available)
 */
if (typeof jQuery === 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        // Basic tracking for coupon card clicks
        var cards = document.querySelectorAll('.listeo-coupon-card');
        
        cards.forEach(function(card) {
            card.addEventListener('click', function(e) {
                var couponId = this.getAttribute('data-coupon-id');
                
                // Basic analytics tracking if available
                if (typeof gtag === 'function') {
                    gtag('event', 'coupon_view', {
                        'coupon_id': couponId,
                        'event_category': 'Coupons'
                    });
                }
            });
        });
    });
}