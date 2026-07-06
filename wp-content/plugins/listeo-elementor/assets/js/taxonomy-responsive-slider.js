/**
 * Taxonomy Responsive Slider - Slick Style Navigation
 * Works with existing category grid structure, moves 1 item at a time
 * Activates only below 992px with Slick-style dots navigation
 */

document.addEventListener("DOMContentLoaded", function () {
    // Only run on screens smaller than 992px
    function initTaxonomySliders() {
        if (window.innerWidth >= 992) {
            return; // Don't initialize on desktop
        }

        const wrappers = document.querySelectorAll('.taxonomy-slider-wrapper:not(.no-mobile-carousel)');

        wrappers.forEach(function(wrapper) {
            // Skip if wrapper is hidden by Elementor responsive settings
            if (wrapper.offsetParent === null || wrapper.offsetWidth === 0 || wrapper.offsetHeight === 0) {
                return;
            }

            const slider = wrapper.querySelector('.taxonomy-responsive-slider');
            const controlsContainer = wrapper.querySelector('.taxonomy-slider-controls-container');

            if (!slider || !controlsContainer) {
                return;
            }

            const prevButton = controlsContainer.querySelector('.taxonomy-slide-prev');
            const nextButton = controlsContainer.querySelector('.taxonomy-slide-next');
            const dotsContainer = controlsContainer.querySelector('.taxonomy-dots');
            const items = slider.querySelectorAll('.category-small-box, .category-small-box-alt');

            if (!prevButton || !nextButton || !dotsContainer || items.length === 0) {
                return;
            }

            let currentPosition = 0;

            // Calculate actual item width dynamically (responsive width + gap)
            function getItemWidth() {
                if (items.length > 0) {
                    const itemRect = items[0].getBoundingClientRect();
                    const itemStyle = window.getComputedStyle(items[0]);
                    const marginRight = parseFloat(itemStyle.marginRight) || 0;
                    return itemRect.width + marginRight + 20; // Adding gap
                }
                // Fallback calculation based on screen width
                if (window.innerWidth <= 480) {
                    return (window.innerWidth / 2 - 10) + 20; // 2 items per screen + gap
                }
                return (window.innerWidth / 3 - 20) + 20; // 3 items per screen + gap
            }

            // Calculate maximum allowed movement
            function getMaxTranslateX() {
                const itemWidth = getItemWidth();
                const totalContentWidth = items.length * itemWidth;
                const visibleWidth = wrapper.clientWidth;

                if (totalContentWidth <= visibleWidth) {
                    return 0;
                }

                return Math.max(0, totalContentWidth - visibleWidth - 20);
            }

            // Calculate visible items
            function calculateVisibleItems() {
                const containerWidth = wrapper.clientWidth;
                const itemWidth = getItemWidth();
                return Math.floor(containerWidth / itemWidth);
            }

            // Calculate total number of slides (pages)
            function getTotalSlides() {
                const visibleItems = calculateVisibleItems();
                return Math.ceil(items.length / visibleItems);
            }

            // Generate dots
            function generateDots() {
                const totalSlides = getTotalSlides();
                dotsContainer.innerHTML = '';

                for (let i = 0; i < totalSlides; i++) {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'presentation');
                    li.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
                    li.setAttribute('aria-controls', `navigation${i}`);
                    li.setAttribute('id', `slick-slide${i}`);
                    if (i === 0) {
                        li.classList.add('active');
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.setAttribute('data-role', 'none');
                    button.setAttribute('role', 'button');
                    button.setAttribute('tabindex', '0');
                    button.textContent = (i + 1).toString();

                    li.appendChild(button);
                    dotsContainer.appendChild(li);

                    // Add click event to dot
                    li.addEventListener('click', function() {
                        goToSlide(i);
                    });
                }
            }

            // Go to specific slide
            function goToSlide(slideIndex) {
                const visibleItems = calculateVisibleItems();
                const targetPosition = slideIndex * visibleItems;
                
                // Ensure we don't go beyond the last item
                currentPosition = Math.min(targetPosition, items.length - visibleItems);
                currentPosition = Math.max(0, currentPosition);
                
                updateSliderPosition();
                updateDots();
            }

            // Update dots active state
            function updateDots() {
                const dots = dotsContainer.querySelectorAll('li');
                const visibleItems = calculateVisibleItems();
                const currentSlide = Math.floor(currentPosition / visibleItems);

                dots.forEach((dot, index) => {
                    dot.classList.remove('active');
                    dot.setAttribute('aria-selected', 'false');
                    if (index === currentSlide) {
                        dot.classList.add('active');
                        dot.setAttribute('aria-selected', 'true');
                    }
                });
            }

            function updateSliderPosition() {
                const itemWidth = getItemWidth();
                const requestedTranslateX = currentPosition * itemWidth;
                const maxTranslateX = getMaxTranslateX();
                const actualTranslateX = Math.min(requestedTranslateX, maxTranslateX);

                slider.style.transform = `translateX(-${actualTranslateX}px)`;
                updateNavigationButtons();
                updateDots();
            }

            function updateNavigationButtons() {
                // Hide prev button if at the beginning
                if (currentPosition <= 0) {
                    prevButton.classList.add('slick-disabled');
                } else {
                    prevButton.classList.remove('slick-disabled');
                }

                // Hide next button when the NEXT move would exceed the max translation
                const itemWidth = getItemWidth();
                const nextPositionTranslateX = (currentPosition + 1) * itemWidth;
                const maxTranslateX = getMaxTranslateX();

                if (nextPositionTranslateX > maxTranslateX) {
                    nextButton.classList.add('slick-disabled');
                } else {
                    nextButton.classList.remove('slick-disabled');
                }
            }

            // Previous button click - move by 1 item
            prevButton.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (currentPosition > 0) {
                    currentPosition--;
                    updateSliderPosition();
                }
            });

            // Next button click - simple increment with boundary check
            nextButton.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Check if we can move more (not at max translation)
                const itemWidth = getItemWidth();
                const requestedTranslateX = (currentPosition + 1) * itemWidth;
                const maxTranslateX = getMaxTranslateX();

                if (requestedTranslateX <= maxTranslateX) {
                    currentPosition++;
                    updateSliderPosition();
                }
            });

            // Handle window resize
            function handleResize() {
                if (window.innerWidth >= 992) {
                    slider.style.transform = 'none';
                    return;
                }

                const visibleItems = calculateVisibleItems();
                currentPosition = Math.min(
                    currentPosition,
                    Math.max(0, items.length - visibleItems)
                );
                
                generateDots(); // Regenerate dots on resize
                updateSliderPosition();
            }

            window.addEventListener("resize", handleResize);

            // Touch support
            let startX = 0;
            let endX = 0;
            let touchStartTime = 0;

            slider.addEventListener("touchstart", function (e) {
                startX = e.touches[0].clientX;
                touchStartTime = Date.now();
            });

            slider.addEventListener("touchmove", function (e) {
                endX = e.touches[0].clientX;
            });

            slider.addEventListener("touchend", function (e) {
                const deltaX = endX - startX;
                const touchDuration = Date.now() - touchStartTime;

                // Only treat as swipe if movement is significant and quick
                if (Math.abs(deltaX) > 50 && touchDuration < 300) {
                    if (deltaX > 0) {
                        // Swipe right - go to previous
                        if (currentPosition > 0) {
                            currentPosition--;
                            updateSliderPosition();
                        }
                    } else {
                        // Swipe left - go to next
                        const itemWidth = getItemWidth();
                        const requestedTranslateX = (currentPosition + 1) * itemWidth;
                        const maxTranslateX = getMaxTranslateX();

                        if (requestedTranslateX <= maxTranslateX) {
                            currentPosition++;
                            updateSliderPosition();
                        }
                    }
                }
            });

            // Initialize slider
            generateDots();
            updateSliderPosition();
        });
    }

    // Initialize sliders
    initTaxonomySliders();

    // Re-initialize on window resize (in case screen orientation changes)
    let resizeTimeout;
    window.addEventListener("resize", function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // Re-initialize if we're now on mobile
            if (window.innerWidth < 992) {
                initTaxonomySliders();
            }
        }, 250);
    });
});