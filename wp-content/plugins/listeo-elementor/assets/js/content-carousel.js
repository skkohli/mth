/**
 * Listeo Content Carousel JavaScript
 * Handles carousel interactions, tab navigation, and responsive behavior
 */

(function($) {
    'use strict';

    class ListeoContentCarousel {
        constructor(element) {
            this.$widget = $(element);
            
            // Prevent duplicate initialization
            if (this.$widget.data('carousel-initialized')) {
                return;
            }
            this.$widget.data('carousel-initialized', true);
            
            this.$track = this.$widget.find('.carousel-track');
            this.$tabs = this.$widget.find('.carousel-tab');
            this.$prevBtn = this.$widget.find('.nav-prev');
            this.$nextBtn = this.$widget.find('.nav-next');
            this.$wrapper = this.$widget.find('.carousel-wrapper');
            
            this.currentPosition = 0;
            this.cardWidth = 0;
            this.gap = 24; // Will be calculated from CSS
            this.maxScrollPosition = 0;
            this.visibleCards = 0;
            this.totalCards = this.$track.find('.carousel-card').length;
            this.autoplayTimer = null;
            this.isMobile = window.innerWidth <= 992; // Mobile detection updated to 992px
            
            this.settings = {
                autoplay: this.$widget.data('autoplay') === 'yes',
                autoplaySpeed: this.$widget.data('autoplay-speed') || 5000,
                animationSpeed: this.$widget.data('animation-speed') || 600
            };

            this.init();
        }

        init() {
            this.calculateDimensions();
            this.bindEvents();
            this.updateNavigation();
            
            if (this.settings.autoplay) {
                this.startAutoplay();
            }

            // Handle window resize
            $(window).on('resize.carousel', $.proxy(this.handleResize, this));
        }

        calculateDimensions() {
            const $firstCard = this.$track.find('.carousel-card').first();
            if ($firstCard.length) {
                // Update mobile detection
                this.isMobile = window.innerWidth <= 992;
                
                // Get the actual card width without margins (standard card width)
                this.cardWidth = $firstCard.outerWidth(false);
                
                // Get the gap from CSS (--carousel-spacing is 24px)
                const computedStyle = getComputedStyle(this.$track[0]);
                this.gap = parseInt(computedStyle.gap) || (this.isMobile ? 16 : 24);
                
                // Calculate visible cards based on wrapper width and average card size
                const wrapperWidth = this.$wrapper.width();
                
                // Calculate max scroll position by getting the natural scroll limit
                const trackWidth = this.$track[0].scrollWidth;
                this.maxScrollPosition = Math.max(0, trackWidth - wrapperWidth);
                
                // Mobile-specific calculation for max position
                if (this.isMobile) {
                    // On mobile, all cards should be the same width, so simple calculation
                    this.maxPosition = Math.max(0, this.totalCards - Math.floor(wrapperWidth / (this.cardWidth + this.gap)));
                } else {
                    // Desktop: More accurate max position calculation for mixed card widths
                    const $allCards = this.$track.find('.carousel-card');
                    let maxPos = 0;
                    let scrollFromStart = 0;
                    
                    // Find the last position where scrolling makes sense
                    for (let i = 0; i < $allCards.length; i++) {
                        const $card = $allCards.eq(i);
                        const cardWidth = $card.outerWidth(false);
                        
                        // Check if this card position can show meaningful content
                        const scrollToThisCard = scrollFromStart;
                        
                        // If this scroll position is still within reasonable bounds
                        if (scrollToThisCard <= this.maxScrollPosition + 50) {
                            maxPos = i;
                        } else {
                            break; // No point going further
                        }
                        
                        scrollFromStart += cardWidth + this.gap;
                    }
                    
                    this.maxPosition = Math.max(0, maxPos);
                }
                
                // Calculate rough visible cards (for reference)
                this.visibleCards = Math.floor(wrapperWidth / (this.cardWidth + this.gap));
            }
        }

        bindEvents() {
            // Tab navigation
            this.$tabs.on('click.carousel', $.proxy(this.handleTabClick, this));
            
            // Arrow navigation
            this.$prevBtn.on('click.carousel', $.proxy(this.goToPrevious, this));
            this.$nextBtn.on('click.carousel', $.proxy(this.goToNext, this));
            
            // Touch/swipe support for mobile
            this.bindTouchEvents();
            
            // Keyboard navigation
            this.$widget.on('keydown.carousel', $.proxy(this.handleKeyboard, this));
            
            // Pause autoplay on hover
            this.$widget.on('mouseenter.carousel', $.proxy(this.pauseAutoplay, this));
            this.$widget.on('mouseleave.carousel', $.proxy(this.resumeAutoplay, this));
            
            // Add scroll event listener for real-time tab updates
            let scrollTimeout;
            this.$wrapper.on('scroll.carousel', () => {
                // Debounce scroll events to avoid excessive updates
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    this.updatePositionFromScroll();
                }, 100);
            });
        }

        bindTouchEvents() {
            let startX = 0;
            let startY = 0;
            let isDown = false;
            let hasMoved = false;
            let startTime = 0;

            this.$wrapper.on('mousedown.carousel touchstart.carousel', (e) => {
                isDown = true;
                hasMoved = false;
                startTime = Date.now();
                
                const touch = e.originalEvent.touches ? e.originalEvent.touches[0] : e.originalEvent;
                startX = touch.pageX;
                startY = touch.pageY;
                
                this.$wrapper.css('cursor', 'grabbing');
                this.pauseAutoplay();
                
                // Don't prevent default here - let links work normally
                // We'll prevent default only when we detect actual swipe movement
            });

            this.$wrapper.on('mouseleave.carousel mouseup.carousel touchend.carousel', (e) => {
                if (!isDown) return;
                
                isDown = false;
                this.$wrapper.css('cursor', 'grab');
                this.resumeAutoplay();
                
                // If we had a swipe gesture, handle it and prevent click
                if (hasMoved) {
                    const endTime = Date.now();
                    const timeDiff = endTime - startTime;
                    const touch = e.originalEvent.changedTouches ? e.originalEvent.changedTouches[0] : e.originalEvent;
                    const endX = touch.pageX || e.originalEvent.pageX;
                    const endY = touch.pageY || e.originalEvent.pageY;
                    
                    const deltaX = endX - startX;
                    const deltaY = endY - startY;
                    const absDeltaX = Math.abs(deltaX);
                    const absDeltaY = Math.abs(deltaY);
                    
                    // Check if this is a horizontal swipe (not vertical scroll)
                    if (absDeltaX > absDeltaY && absDeltaX > 50 && timeDiff < 500) {
                        // Valid horizontal swipe detected - prevent any clicks
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if (deltaX > 0) {
                            // Swipe right - go to previous
                            this.goToPrevious();
                        } else {
                            // Swipe left - go to next
                            this.goToNext();
                        }
                    } else {
                        // Not a valid swipe, snap back to current position
                        this.goToPosition(this.currentPosition);
                    }
                }
                // If no movement (hasMoved = false), let the click proceed normally
            });

            this.$wrapper.on('mousemove.carousel touchmove.carousel', (e) => {
                if (!isDown) return;
                
                const touch = e.originalEvent.touches ? e.originalEvent.touches[0] : e.originalEvent;
                const currentX = touch.pageX || e.originalEvent.pageX;
                const currentY = touch.pageY || e.originalEvent.pageY;
                
                const deltaX = Math.abs(currentX - startX);
                const deltaY = Math.abs(currentY - startY);
                
                // Mark as moved if we've moved more than a threshold
                if (deltaX > 10 || deltaY > 10) {
                    hasMoved = true;
                }
                
                // Only prevent default for horizontal swipes AND when movement is significant
                if (hasMoved && deltaX > deltaY && deltaX > 20) {
                    e.preventDefault();
                }
            });
        }

        handleTabClick(e) {
            const $tab = $(e.currentTarget);
            const targetPosition = parseInt($tab.data('position'), 10) || 0;
            
            // Update active tab
            this.$tabs.removeClass('active');
            $tab.addClass('active');
            
            // Scroll to target position
            this.goToPosition(targetPosition);
        }

        goToPosition(position) {
            // Ensure position is within bounds
            position = Math.max(0, Math.min(position, this.maxPosition));
            
            // Calculate scroll position based on actual card widths and positions
            let scrollPosition = 0;
            
            if (position === 0) {
                scrollPosition = 0;
            } else if (position >= this.maxPosition) {
                // For the last position, use the natural max scroll position
                scrollPosition = this.maxScrollPosition;
            } else {
                // Calculate based on actual card positions
                scrollPosition = this.calculateScrollPositionForCard(position);
            }
            
            // Use instant scrolling for immediate response
            this.$wrapper.scrollLeft(scrollPosition);
            
            this.currentPosition = position;
            this.updateNavigation();
        }

        calculateScrollPositionForCard(targetPosition) {
            const $cards = this.$track.find('.carousel-card');
            let scrollPosition = 0;
            
            // On mobile, use simple calculation since all cards are same width
            if (this.isMobile) {
                scrollPosition = targetPosition * (this.cardWidth + this.gap);
            } else {
                // Desktop: Sum up the widths of cards before the target position
                for (let i = 0; i < targetPosition && i < $cards.length; i++) {
                    const $card = $cards.eq(i);
                    const cardWidth = $card.outerWidth(false);
                    scrollPosition += cardWidth + this.gap;
                }
            }
            
            return scrollPosition;
        }

        goToNext() {
            if (this.currentPosition < this.maxPosition) {
                this.goToPosition(this.currentPosition + 1);
            } else if (this.settings.autoplay) {
                // Loop back to start for autoplay
                this.goToPosition(0);
            }
        }

        goToPrevious() {
            // For backward navigation, be more direct and reliable
            if (this.currentPosition <= 0) {
                return; // Already at the beginning
            }
            
            // Calculate the target position
            const targetPosition = this.currentPosition - 1;
            
            // For backward navigation, always go exactly one position back
            // regardless of card widths - this ensures consistent behavior
            this.goToPosition(targetPosition);
        }

        updateNavigation() {
            // Update arrow buttons
            this.$prevBtn.prop('disabled', this.currentPosition <= 0);
            this.$nextBtn.prop('disabled', this.currentPosition >= this.maxPosition);
            
            // Update tab highlighting based on scroll position
            this.updateActiveTab();
        }

        updateActiveTab() {
            if (this.$tabs.length === 0) return;
            
            // Simple approach: find the closest tab to current position
            let activeTabIndex = 0;
            let closestDistance = Infinity;
            
            this.$tabs.each((index, tab) => {
                const tabPosition = parseInt($(tab).data('position'), 10) || 0;
                const distance = Math.abs(this.currentPosition - tabPosition);
                
                if (distance < closestDistance) {
                    closestDistance = distance;
                    activeTabIndex = index;
                }
            });
            
            this.$tabs.removeClass('active');
            this.$tabs.eq(activeTabIndex).addClass('active');
        }

        updatePositionFromScroll() {
            // Get current scroll position
            const scrollPosition = this.$wrapper.scrollLeft();
            
            // Calculate what position we should be at based on scroll
            let newPosition = 0;
            
            if (scrollPosition >= this.maxScrollPosition - 10) {
                // Near the end - set to last meaningful position
                newPosition = this.maxPosition;
            } else if (scrollPosition <= 10) {
                // Near the beginning
                newPosition = 0;
            } else {
                // Find the closest card position based on actual card positions
                newPosition = this.findClosestCardPosition(scrollPosition);
                newPosition = Math.max(0, Math.min(newPosition, this.maxPosition));
            }
            
            // Only update if position actually changed to avoid infinite loops
            if (newPosition !== this.currentPosition) {
                this.currentPosition = newPosition;
                this.updateNavigation();
            }
        }

        findClosestCardPosition(scrollPosition) {
            const $cards = this.$track.find('.carousel-card');
            
            // On mobile, use simple calculation since all cards are same width
            if (this.isMobile) {
                const cardAndGapWidth = this.cardWidth + this.gap;
                const position = Math.round(scrollPosition / cardAndGapWidth);
                return Math.max(0, Math.min(position, this.totalCards - 1));
            }
            
            // Desktop: Complex calculation for mixed card widths
            let currentScroll = 0;
            let closestPosition = 0;
            let closestDistance = Infinity;
            
            // Check each card position
            for (let i = 0; i < $cards.length; i++) {
                const distance = Math.abs(scrollPosition - currentScroll);
                
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestPosition = i;
                }
                
                // Add this card's width + gap for next iteration
                const $card = $cards.eq(i);
                const cardWidth = $card.outerWidth(false);
                currentScroll += cardWidth + this.gap;
            }
            
            // Improved end position handling for wide cards (desktop only)
            const wrapperWidth = this.$wrapper.width();
            if (scrollPosition >= this.maxScrollPosition - 20) {
                // When at the very end, find the rightmost position that makes sense
                let totalWidthFromEnd = 0;
                let bestEndPosition = $cards.length - 1;
                
                // Work backwards from the end to find what fits well
                for (let i = $cards.length - 1; i >= 0; i--) {
                    const $card = $cards.eq(i);
                    const cardWidth = $card.outerWidth(false);
                    
                    // Calculate cumulative width from this position to the end
                    totalWidthFromEnd = 0;
                    for (let j = i; j < $cards.length; j++) {
                        const $cardInner = $cards.eq(j);
                        totalWidthFromEnd += $cardInner.outerWidth(false);
                        if (j < $cards.length - 1) totalWidthFromEnd += this.gap;
                    }
                    
                    // If this position shows a reasonable amount of content, use it
                    if (totalWidthFromEnd <= wrapperWidth * 1.1) { // Allow 10% overflow
                        bestEndPosition = i;
                        break;
                    }
                }
                
                return Math.max(0, bestEndPosition);
            }
            
            return Math.max(0, Math.min(closestPosition, this.totalCards - 1));
        }

        handleKeyboard(e) {
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.goToPrevious();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.goToNext();
                    break;
                case ' ': // Spacebar
                    e.preventDefault();
                    if (this.settings.autoplay) {
                        this.toggleAutoplay();
                    }
                    break;
            }
        }

        startAutoplay() {
            if (!this.settings.autoplay) return;
            
            this.autoplayTimer = setInterval(() => {
                this.goToNext();
            }, this.settings.autoplaySpeed);
        }

        pauseAutoplay() {
            if (this.autoplayTimer) {
                clearInterval(this.autoplayTimer);
                this.autoplayTimer = null;
            }
        }

        resumeAutoplay() {
            if (this.settings.autoplay && !this.autoplayTimer) {
                this.startAutoplay();
            }
        }

        toggleAutoplay() {
            this.settings.autoplay = !this.settings.autoplay;
            if (this.settings.autoplay) {
                this.startAutoplay();
            } else {
                this.pauseAutoplay();
            }
        }

        handleResize() {
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(() => {
                this.calculateDimensions();
                this.updateNavigation();
            }, 250);
        }

        destroy() {
            // Clean up event listeners with namespaced events
            $(window).off('resize.carousel');
            this.$tabs.off('.carousel');
            this.$prevBtn.off('.carousel');
            this.$nextBtn.off('.carousel');
            this.$widget.off('.carousel');
            this.$wrapper.off('.carousel');
            
            // Clear timers
            this.pauseAutoplay();
            if (this.resizeTimer) {
                clearTimeout(this.resizeTimer);
            }
        }
    }

    // Custom easing function
    if (!$.easing.easeInOutCubic) {
        $.easing.easeInOutCubic = function (x, t, b, c, d) {
            if ((t/=d/2) < 1) return c/2*t*t*t + b;
            return c/2*((t-=2)*t*t + 2) + b;
        };
    }

    // Initialize carousels when Elementor frontend is ready
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-content-carousel.default', function($scope) {
            const $carousel = $scope.find('.listeo-carousel-widget');
            if ($carousel.length) {
                // Add longer delay for Elementor frontend to ensure all elements are properly loaded
                setTimeout(() => {
                    new ListeoContentCarousel($carousel[0]);
                }, 200);
            }
        });
    });

    // Initialize for non-Elementor pages (like frontend)
    $(document).ready(function() {
        $('.listeo-carousel-widget').each(function() {
            // Add delay for frontend to ensure styles are loaded
            setTimeout(() => {
                new ListeoContentCarousel(this);
            }, 300);
        });
    });

    // Additional initialization for widgets loaded via AJAX or dynamic content
    $(document).on('elementor/popup/show', function() {
        setTimeout(() => {
            $('.listeo-carousel-widget').each(function() {
                if (!$(this).data('carousel-initialized')) {
                    $(this).data('carousel-initialized', true);
                    new ListeoContentCarousel(this);
                }
            });
        }, 500);
    });

    // Accessibility enhancements
    $(document).ready(function() {
        // Add ARIA labels and roles
        $('.carousel-track').attr('role', 'region').attr('aria-label', 'Content carousel');
        $('.carousel-tabs').attr('role', 'tablist');
        $('.carousel-tab').attr('role', 'tab').attr('tabindex', '0');
        $('.nav-arrow').attr('aria-label', function() {
            return $(this).hasClass('nav-prev') ? 'Previous slide' : 'Next slide';
        });

        // Handle tab keyboard navigation
        $('.carousel-tab').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
    });

    // Export for potential external use
    window.ListeoContentCarousel = ListeoContentCarousel;

})(jQuery);

/**
 * Elementor Panel Functionality for Dynamic Taxonomy Controls
 */
(function($) {
    'use strict';

    // Elementor panel specific functionality
    if (typeof elementor !== 'undefined' && elementor.modules && elementor.modules.controls) {
        
        $(document).ready(function() {
            
            // Listen for Elementor panel changes
            elementor.hooks.addAction('panel/open_editor/widget/listeo-content-carousel', function(panel, model, view) {
                
                // Add listener for taxonomy type changes in repeater controls
                setTimeout(function() {
                    setupTaxonomyFiltering();
                }, 500);
                
            });
            
            function setupTaxonomyFiltering() {
                // Find all taxonomy type controls in repeater
                $('[data-setting$="_taxonomy_type"]').off('change.taxonomyFilter').on('change.taxonomyFilter', function() {
                    const $this = $(this);
                    const selectedTaxonomy = $this.val();
                    
                    // Find the corresponding term control
                    const settingName = $this.data('setting');
                    const termSettingName = settingName.replace('_taxonomy_type', '_taxonomy_term');
                    const $termControl = $('[data-setting="' + termSettingName + '"]');
                    
                    if ($termControl.length) {
                        filterTermOptions($termControl, selectedTaxonomy);
                    }
                });
                
                // Initial filtering for existing controls
                $('[data-setting$="_taxonomy_type"]').each(function() {
                    const $this = $(this);
                    const selectedTaxonomy = $this.val();
                    const settingName = $this.data('setting');
                    const termSettingName = settingName.replace('_taxonomy_type', '_taxonomy_term');
                    const $termControl = $('[data-setting="' + termSettingName + '"]');
                    
                    if ($termControl.length && selectedTaxonomy) {
                        filterTermOptions($termControl, selectedTaxonomy);
                    }
                });
            }
            
            function filterTermOptions($termControl, taxonomyType) {
                // Get the Select2 instance
                const $select = $termControl.find('select');
                if (!$select.length) return;
                
                // Store original options if not already stored
                if (!$select.data('original-options')) {
                    const originalOptions = [];
                    $select.find('option').each(function() {
                        originalOptions.push({
                            value: $(this).val(),
                            text: $(this).text()
                        });
                    });
                    $select.data('original-options', originalOptions);
                }
                
                // Get original options
                const originalOptions = $select.data('original-options');
                
                // Clear current options
                $select.empty();
                
                // Filter and add relevant options
                const filterPrefix = taxonomyType === 'listing_category' ? 'category_' : 'region_';
                
                originalOptions.forEach(function(option) {
                    if (!option.value || option.value.startsWith(filterPrefix)) {
                        $select.append($('<option>', {
                            value: option.value,
                            text: option.text
                        }));
                    }
                });
                
                // Trigger Select2 update
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy').select2();
                }
                
                // Clear current selection if it doesn't match the new taxonomy
                const currentValue = $select.val();
                if (currentValue && !currentValue.startsWith(filterPrefix)) {
                    $select.val('').trigger('change');
                }
            }
            
            // Handle repeater item additions - only within content-carousel widget panel
            // Use namespaced event and check if we're in the right widget context
            $(document).off('click.listeoContentCarousel').on('click.listeoContentCarousel', '.elementor-repeater-add', function() {
                // Check if we're editing the content-carousel widget
                var currentElement = elementor.getPanelView().getCurrentPageView();
                if (!currentElement || !currentElement.model) {
                    return;
                }
                var widgetType = currentElement.model.get('widgetType') || '';
                if (widgetType !== 'listeo-content-carousel') {
                    return;
                }

                setTimeout(function() {
                    setupTaxonomyFiltering();
                }, 100);
            });
            
        });
    }

})(jQuery);
