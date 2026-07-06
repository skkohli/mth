document.addEventListener("DOMContentLoaded", function () {
  // Category data with icons (SVG paths)
  const categories = sliderData.categories || [];

  const categorySlider = document.getElementById("categorySlider");
  const prevButton = document.querySelector(".nav-button.prev");
  const nextButton = document.querySelector(".nav-button.next");
  let currentIndex = 0;

  // Check URL for _listing_type parameter
  const urlParams = new URLSearchParams(window.location.search);
  const listingTypeFromUrl = urlParams.get('_listing_type');

  if (listingTypeFromUrl) {
    // Find the listing type in categories that matches the URL parameter
    const matchingListingType = categories.find(
      (cat) => cat.type === 'listing_type' && cat.slug === listingTypeFromUrl
    );
    if (matchingListingType) {
      currentIndex = categories.indexOf(matchingListingType);
    }
  } else if (sliderData.currentCategory) {
    // Fallback to currentCategory if no URL parameter
    const currentCategory = categories.find(
      (cat) => cat.slug === sliderData.currentCategory
    );
    if (currentCategory) {
      currentIndex = categories.indexOf(currentCategory);
    }
  }
  

  // Create category items
  categories.forEach((category, index) => {
    const categoryItem = document.createElement("div");
    categoryItem.className = "category-item" + (index === currentIndex ? " active" : "");
    categoryItem.setAttribute("data-id", category.id);
    categoryItem.setAttribute("data-slug", category.slug);
    
    // Add data attribute for listing type if present
    if (category.type === 'listing_type') {
      categoryItem.setAttribute("data-type", "listing_type");
    }
    
    categoryItem.innerHTML = `
        <div class="icon-container">${category.icon}</div>
        <div class="category-name">${category.name}</div>
    `;
    // If current category matches, set it as active
    if (index === currentIndex) {
      categoryItem.classList.add("active");
    } else {
      categoryItem.classList.remove("active");
    }
    categoryItem.addEventListener("click", function (e) {
      // Prevent default behavior and stop propagation
      e.preventDefault();
      e.stopPropagation();

      // Remove active class from all items
      document.querySelectorAll(".category-item").forEach((item) => {
        item.classList.remove("active");
      });

      // Add active class to clicked item
      this.classList.add("active");
      
      const label = this.querySelector(".category-name");
      if (label) {
        const pageTitle = document.querySelector(".page-title");
        if (pageTitle) {
          pageTitle.textContent = label.textContent;
        }
      }

      const categoryId = this.getAttribute("data-id");
      const categorySlug = this.getAttribute("data-slug");

      // Check if this is a listing type (has data attribute 'data-type')
      const isListingType = this.getAttribute("data-type") === 'listing_type';
      
      // Check if this is the "All" option
      const isAllOption = categorySlug === 'all';
      
      // Check if this is a mixed taxonomy format (contains colon)
      const isMixedTaxonomy = categorySlug && categorySlug.includes(':');
      
      // Determine if this is primarily a listing types slider or categories slider
      const hasListingTypes = categories.some(cat => cat.type === 'listing_type');
      const shouldHandleAsListingType = isListingType || (isAllOption && hasListingTypes) || isMixedTaxonomy;

      if (shouldHandleAsListingType) {
        // Handle listing type selection, "All" option, or mixed taxonomy
        console.log('Taking listing types path for:', categorySlug, {isListingType, isAllOption, hasListingTypes, isMixedTaxonomy});

        // FIRST: Check if there's a listing types drilldown and clear its array inputs
        var drilldownId = "listeo-drilldown-listing-types";
        if (window.ListeoDrilldown && window.ListeoDrilldown[drilldownId]) {
          // Remove drilldown's hidden inputs (array format)
          document.querySelectorAll('#listeo_core-search-form input[name="drilldown-listing-types[]"]').forEach(function(input) {
            input.remove();
          });
          // Reset drilldown visual state
          var drilldown = window.ListeoDrilldown[drilldownId];
          if (drilldown.selectedItems) {
            drilldown.selectedItems = [];
          }
          document.querySelectorAll("#" + drilldownId + " .menu-item.selected").forEach(function(item) {
            item.classList.remove("selected");
          });
        }

        let listingTypeSelect = document.getElementById("listing_type") || 
                               document.getElementById("_listing_type") ||
                               document.querySelector("select[name='_listing_type']") ||
                               document.querySelector("select[name='listing_type']");
        
        if (listingTypeSelect) {
          // Found a visible listing type field - use it
          // For "All" option, set empty value to show all types
          listingTypeSelect.value = isAllOption ? '' : categorySlug;
          
          // Trigger change event for listing type
          const event = new Event("change", { bubbles: true });
          listingTypeSelect.dispatchEvent(event);
          
          // Also refresh Bootstrap Select if it's being used
          if (typeof jQuery !== "undefined" && typeof jQuery.fn.selectpicker === "function") {
            jQuery(listingTypeSelect).selectpicker("refresh");
          }
        } else {
          // No visible listing type field - check if page has AJAX search capability
          const resultsContainer =
            document.querySelector(".listeo-listings") ||
            document.querySelector("#listeo-listings-container") ||
            document.querySelector(".listings-container") ||
            document.querySelector("[data-results-container]") ||
            document.querySelector(".search-results");
          
          if (resultsContainer && typeof jQuery !== 'undefined') {
            // Page appears to support AJAX - try to trigger it
            const form = document.querySelector('#listeo_core-search-form');
            
            if (form) {
              let hiddenInput;
              let fieldName;
              
              // Determine the correct field name based on the type of selection
              if (isMixedTaxonomy) {
                // Mixed taxonomy format - use drilldown-listing-types
                fieldName = 'drilldown-listing-types[]';
                hiddenInput = form.querySelector('input[name="drilldown-listing-types[]"]');
              } else {
                // Regular listing type - use _listing_type
                fieldName = '_listing_type';
                hiddenInput = form.querySelector('input[name="_listing_type"]');
              }
           
              if (!hiddenInput) {
                hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = fieldName;
                form.appendChild(hiddenInput);
              }
              // make sure it's enabled even if an existing one had disabled="disabled"
              hiddenInput.disabled = false; // clears the DOM property
              hiddenInput.removeAttribute("disabled"); // extra safety if the attribute is set
              // For "All" option, set empty value to show all types
              hiddenInput.value = isAllOption ? '' : categorySlug;

              var target = jQuery("#listeo-listings-container");
              target.triggerHandler("update_results", [1, false]);
             
            } 
          } 
        }
        
        // Check for listing type drilldown
        if (document.getElementById("listeo-drilldown-listing-types")) {

          const drilldown = window.ListeoDrilldown["listeo-drilldown-listing-types"];

          if (drilldown) {
            if (isAllOption) {
              // For "All" option, reset the drilldown selection
              console.log('Resetting listing type drilldown for All option');
              if (typeof drilldown.reset === 'function') {
                drilldown.reset();
              } else if (typeof drilldown.selectListingType === 'function') {
                // If no reset method, try to select with empty value
                drilldown.selectListingType('');
              }
            } else if (categorySlug) {
              // For listing types, we want to select the type and show its categories
              drilldown.selectListingType(categorySlug);
            }
          }
        }
        
        // Add listener to remove active class when listing type changes
        if (listingTypeSelect) {
          listingTypeSelect.addEventListener("change", function () {
            // Remove `.active` class from all slider items
            const sliderItems = document.querySelectorAll(
              ".category-item.active"
            );
            sliderItems.forEach((item) => item.classList.remove("active"));
          });
        }
      } else {
        // Handle category selection (original logic)

        // FIRST: Check if there's a drilldown for this taxonomy and reset it
        var drilldownId = "listeo-drilldown-tax-listing_category";
        if (window.ListeoDrilldown && window.ListeoDrilldown[drilldownId]) {
          var drilldown = window.ListeoDrilldown[drilldownId];
          // Remove drilldown's hidden inputs
          document.querySelectorAll('#listeo_core-search-form input[name="tax-listing_category[]"]').forEach(function(input) {
            input.remove();
          });
          // Reset drilldown visual state (but don't call resetSelections as it triggers update_results)
          if (drilldown.selectedItems) {
            drilldown.selectedItems = [];
          }
          document.querySelectorAll(".drilldown-menu#" + drilldownId + " .menu-item.selected").forEach(function(item) {
            item.classList.remove("selected");
          });
        }

        const select = document.getElementById("tax-listing_category");
        if (select) {
          // For "All" option, set empty value to show all categories
          select.value = isAllOption ? '' : categorySlug;

          // Refresh Bootstrap Select
          if (
            typeof bootstrap !== "undefined" &&
            typeof bootstrap.Select !== "undefined"
          ) {
            bootstrap.Select.refresh();
          } else if (
            typeof jQuery !== "undefined" &&
            typeof jQuery.fn.selectpicker === "function"
          ) {
            jQuery(select).selectpicker("refresh");
          }

          // Trigger change event
          const event = new Event("change", { bubbles: true });
          select.dispatchEvent(event);

          select.addEventListener("change", function () {
            // Remove `.active` class from all slider items
            const sliderItems = document.querySelectorAll(
              ".category-item.active"
            );
            sliderItems.forEach((item) => item.classList.remove("active"));
          });
        } else {
          // No visible category field - check if page has AJAX search capability
          const resultsContainer =
            document.querySelector(".listeo-listings") ||
            document.querySelector("#listeo-listings-container") ||
            document.querySelector(".listings-container") ||
            document.querySelector("[data-results-container]") ||
            document.querySelector(".search-results");
          
          if (resultsContainer && typeof jQuery !== 'undefined') {
            // Page appears to support AJAX - try to trigger it
            const form = document.querySelector('#listeo_core-search-form');
            
            if (form) {
              let hiddenInput;
              let fieldName;
              
              // Determine the correct field name based on category format
              if (categorySlug && categorySlug.includes(':')) {
                // This is a mixed taxonomy format - use drilldown-listing-types
                fieldName = 'drilldown-listing-types[]';
                hiddenInput = form.querySelector('input[name="drilldown-listing-types[]"]');
              } else {
                // This is a regular category - use tax-listing_category
                fieldName = 'tax-listing_category';
                hiddenInput = form.querySelector('input[name="tax-listing_category"]');
              }
              
              if (!hiddenInput) {
                hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = fieldName;
                form.appendChild(hiddenInput);
              }
              
              // make sure it's enabled even if an existing one had disabled="disabled"
              hiddenInput.disabled = false; // clears the DOM property
              hiddenInput.removeAttribute("disabled"); // extra safety if the attribute is set
              // For "All" option, set empty value to show all categories
              hiddenInput.value = isAllOption ? '' : categorySlug;

              var target = jQuery("#listeo-listings-container");
              target.triggerHandler("update_results", [1, false]);
             
            } 
          } 
        }
        
        // Check for single taxonomy drilldowns - only for regular taxonomies, not mixed taxonomies
        if ((categorySlug && !categorySlug.includes(':')) || isAllOption) {
          // This is a regular taxonomy term (no prefix) or the "All" option, check for specific drilldowns
          // We need to determine which taxonomy this term belongs to
          // First, try common taxonomy patterns
          const possibleTaxonomies = ['listing_category', 'service_category', 'event_category', 'rental_category', 'classifieds_category', 'region', 'listing_feature'];

          for (const taxonomy of possibleTaxonomies) {
            const drilldownId = `listeo-drilldown-tax-${taxonomy}`;
            const drilldownElement = document.getElementById(drilldownId);

            if (drilldownElement && window.ListeoDrilldown && window.ListeoDrilldown[drilldownId]) {
              const drilldown = window.ListeoDrilldown[drilldownId];

              if (isAllOption) {
                // For "All" option, reset the drilldown selection
                console.log('Resetting category drilldown for All option:', drilldownId);
                if (typeof drilldown.reset === 'function') {
                  drilldown.reset();
                } else if (typeof drilldown.selectById === 'function') {
                  drilldown.selectById('');
                }
              } else if (categoryId) {
                // For regular taxonomy terms, use the term ID
                drilldown.selectById(categoryId);
              }

              // Continue to reset all drilldowns if this is "All" option
              // Don't break early for "All" to ensure all category drilldowns are reset
              if (!isAllOption) {
                break;
              }
            }
          }
        }
      }
    });

    // Add touch event handlers to prevent slider movement only for taps (not swipes)
    let touchStartTime = 0;
    let touchStartX = 0;
    let touchStartY = 0;

    categoryItem.addEventListener("touchstart", function (e) {
      touchStartTime = Date.now();
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
    });

    categoryItem.addEventListener("touchend", function (e) {
      const touchEndTime = Date.now();
      const touchDuration = touchEndTime - touchStartTime;
      const touchEndX = e.changedTouches[0].clientX;
      const touchEndY = e.changedTouches[0].clientY;

      const deltaX = Math.abs(touchEndX - touchStartX);
      const deltaY = Math.abs(touchEndY - touchStartY);

      // If it's a quick tap with minimal movement, treat as category selection
      if (touchDuration < 300 && deltaX < 10 && deltaY < 10) {
        e.stopPropagation();
        // The click event will handle the category selection
      }
      // Otherwise, let it bubble up for potential swipe handling
    });


    categorySlider.appendChild(categoryItem);
  });

  // Navigation functionality with dynamic width calculation
  let currentScrollPosition = 0;
  let maxScrollPosition = 0;
  let containerWidth = 0;
  let totalContentWidth = 0;
  let scrollStep = 0;

  // Calculate dimensions and scroll limits
  function calculateDimensions() {
    const items = categorySlider.querySelectorAll('.category-item');
    if (items.length === 0) return;

    // Get container width (subtract padding for navigation buttons)
    containerWidth = categorySlider.parentElement.clientWidth - 80;

    // Calculate total content width by summing all item widths
    totalContentWidth = 0;
    items.forEach(item => {
      const itemRect = item.getBoundingClientRect();
      const itemStyle = window.getComputedStyle(item);
      const marginLeft = parseFloat(itemStyle.marginLeft);
      const marginRight = parseFloat(itemStyle.marginRight);
      totalContentWidth += itemRect.width + marginLeft + marginRight;
    });

    // Calculate maximum scroll position to ensure last item is fully visible
    maxScrollPosition = Math.max(0, totalContentWidth - containerWidth);

    // Set scroll step to about 60% of container width for smoother navigation
    scrollStep = containerWidth * 0.6;
  }

  function updateSliderPosition() {
    categorySlider.style.transform = `translateX(-${currentScrollPosition}px)`;
    updateNavigationButtons();
  }

  function updateNavigationButtons() {
    // Hide prev button if at the beginning
    if (currentScrollPosition <= 0) {
      prevButton.classList.add("hidden");
    } else {
      prevButton.classList.remove("hidden");
    }

    // Check if last item is fully visible by calculating its position
    const items = categorySlider.querySelectorAll('.category-item');
    if (items.length > 0) {
      const lastItem = items[items.length - 1];
      const sliderRect = categorySlider.parentElement.getBoundingClientRect();
      const lastItemRect = lastItem.getBoundingClientRect();

      // Calculate distance from last item's right edge to container's right edge
      // Account for the navigation button space (40px on right side)
      const distanceToEnd = lastItemRect.right - (sliderRect.right - 40);

      // Hide next button if last item is fully visible (within 5px tolerance)
      if (distanceToEnd <= 5) {
        nextButton.classList.add("hidden");
      } else {
        nextButton.classList.remove("hidden");
      }
    } else {
      if (currentScrollPosition >= maxScrollPosition) {
        nextButton.classList.add("hidden");
      } else {
        nextButton.classList.remove("hidden");
      }
    }
  }

  prevButton.addEventListener("click", function () {
    if (currentScrollPosition > 0) {
      currentScrollPosition = Math.max(0, currentScrollPosition - scrollStep);
      updateSliderPosition();
      // Override: we scrolled back so there are hidden items to the right.
      // getBoundingClientRect() is unreliable here due to the CSS transition.
      nextButton.classList.remove("hidden");
    }
  });

  nextButton.addEventListener("click", function () {
    if (currentScrollPosition < maxScrollPosition) {
      currentScrollPosition = Math.min(maxScrollPosition, currentScrollPosition + scrollStep);
      updateSliderPosition();
      // Override: if we reached the end, hide next button deterministically
      if (currentScrollPosition >= maxScrollPosition) {
        nextButton.classList.add("hidden");
      }
    }
  });

  // Handle window resize
  window.addEventListener("resize", function () {
    calculateDimensions();
    currentScrollPosition = Math.min(currentScrollPosition, maxScrollPosition);
    updateSliderPosition();
  });

  // Initialize dimensions and slider position
  calculateDimensions();
  updateSliderPosition();

  // Touch support
  let startX = 0;
  let endX = 0;

  categorySlider.addEventListener("touchstart", function (e) {
    startX = e.touches[0].clientX;
  });

  categorySlider.addEventListener("touchmove", function (e) {
    endX = e.touches[0].clientX;
  });

  categorySlider.addEventListener("touchend", function (e) {
    const deltaX = endX - startX;

    // Swipe right (scroll back)
    if (deltaX > 50 && currentScrollPosition > 0) {
      currentScrollPosition = Math.max(0, currentScrollPosition - scrollStep);
      updateSliderPosition();
      nextButton.classList.remove("hidden");
    }

    // Swipe left (scroll forward)
    if (deltaX < -50 && currentScrollPosition < maxScrollPosition) {
      currentScrollPosition = Math.min(maxScrollPosition, currentScrollPosition + scrollStep);
      updateSliderPosition();
      if (currentScrollPosition >= maxScrollPosition) {
        nextButton.classList.add("hidden");
      }
    }

    // Reset values
    startX = 0;
    endX = 0;
  });
});
