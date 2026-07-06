/**
 * Services Showcase Widget JavaScript
 */
(function($) {
    'use strict';

    var ServicesShowcase = function($scope, $) {
        var $container = $scope.find('.services-container');
        
        if ($container.length === 0) {
            return;
        }

        var $navItems = $container.find('.nav-item');
        var $tabContents = $container.find('.tab-content');

        // Initialize
        init();

        function init() {
            bindEvents();
            setupIntersectionObserver();
            
            // Set initial height after a short delay to ensure content is rendered
            setTimeout(function() {
                updateContentAreaHeight();
            }, 100);
        }

        function bindEvents() {
            $navItems.on('click', function(e) {
                e.preventDefault();
                var $this = $(this);
                var tabId = $this.data('tab');
                var bgColor = $this.data('bg-color');
                
                switchTab(tabId, $this, bgColor);
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
            
            // Handle window resize
            var resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    updateContentAreaHeight();
                }, 250);
            });
        }

        function switchTab(tabId, $activeNav, bgColor) {
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
            var $newTab = $container.find('#' + tabId);
            
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
            $container.trigger('services-showcase:tab-changed', [tabId, $activeNav]);
        }

        function setupIntersectionObserver() {
            // Pure CSS animations - no JavaScript manipulation needed
        }

        // Auto-cycle through tabs (optional)
        function startAutoCycle(interval) {
            if (interval && interval > 0) {
                var currentIndex = 0;
                setInterval(function() {
                    currentIndex = (currentIndex + 1) % $navItems.length;
                    $navItems.eq(currentIndex).trigger('click');
                }, interval);
            }
        }

        function updateContentAreaHeight() {
            var $contentArea = $container.find('.content-area');
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
                    var newHeight = Math.max(naturalHeight + 20, 500);
                    $contentArea[0].style.setProperty('--content-height', newHeight + 'px');
                }, 50);
            }
        }

        // Expose public methods
        $container.data('services-showcase', {
            switchTab: switchTab,
            startAutoCycle: startAutoCycle
        });
    };

    // Run when Elementor frontend is loaded
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-taxonomy-tabs.default', ServicesShowcase);
    });

    // Run on document ready for non-Elementor pages
    $(document).ready(function() {
        $('.services-container').each(function() {
            ServicesShowcase($(this), $);
        });
    });

})(jQuery);
