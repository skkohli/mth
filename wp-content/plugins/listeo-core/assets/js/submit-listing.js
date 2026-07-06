jQuery(document).ready(function ($) {
  // prevent multiple

  // // get custom fields from the term
  // $(".add-listing-section #listing_category,.add-listing-section #tax-listing_category").on("change", function (e) {

  //   if ($(this).prop("multiple")) {
  //     var cat_ids;
  //     cat_ids = $(this).val();
  //   } else {
  //     var cat_ids = [];
  //     cat_ids.push($(this).val());
  //   }
  //   var term = $(this).data('taxonomy');

  //   $.ajax({
  //     type: "POST",
  //     dataType: "json",
  //     url: listeo.ajaxurl,
  //     data: {
  //       action: "listeo_get_custom_fields_from_term",
  //       cat_ids: cat_ids,
  //       term: term,
  //       panel: false,
  //       //'nonce': nonce
  //     },
  //     success: function (data) {
  //       $(
  //         ".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature"
  //       ).removeClass("loading");
  //       $(".custom-term-features-content")
  //         .html(data["output"])
  //         .removeClass("loading");
  //     },
  //   });
  // });

  //  $(document).on(
  //   "drilldown-updated",
  //   ".submit-page .drilldown-menu",
  //   function (e) {

  //     var cat_ids = [];
  //     $(".drilldown-generated").each(function () {
  //       cat_ids.push($(this).val());
  //     });
  //     var term = $(this).data('taxonomy');

  //     $.ajax({
  //       type: "POST",
  //       dataType: "json",
  //       url: listeo.ajaxurl,
  //       data: {
  //         action: "listeo_get_custom_fields_from_term",
  //         cat_ids: cat_ids,
  //         term: term,
  //         panel: false,
  //         //'nonce': nonce
  //       },
  //       success: function (data) {
  //         $(
  //           ".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature"
  //         ).removeClass("loading");
  //         $(".custom-term-features-content")
  //           .html(data["output"])
  //           .removeClass("loading");
  //       },
  //     });
  //   }
  // );

  var timers = {};
  var activeRequests = {}; // Track active AJAX requests
  var termFields = {}; // Cache HTML for each individual term: termFields['taxonomy-termid'] = 'html'
  var loadedTerms = {}; // Track which terms are currently loaded: loadedTerms['taxonomy'] = ['term1', 'term2']
  var sectionTerms = {}; // Track which terms belong to which section: sectionTerms['sectionId'] = {tax: [terms]}
  var listingId =
    $('input[name="listing_id"]').val() ||
    (window.listeo && window.listeo.listing_id) ||
    null;

  // 1) find all the distinct taxonomy pickers on the page:
  function getTaxonomies() {
    var seen = {};
    $("[data-taxonomy]").each(function () {
      seen[$(this).data("taxonomy")] = true;
    });
    return Object.keys(seen);
  }

  // 2) for a given taxonomy, gather all selected term IDs
  function getTermIdsFor(tax) {
    var ids = [];

    // any <select> or Select2
    $('[data-taxonomy="' + tax + '"]').each(function () {
      var $el = $(this);
      if ($el.is("select")) {
        var val = $el.val() || [];
        ids = ids.concat(Array.isArray(val) ? val : [val]);
      }
    });

    // any checkboxes (input[type=checkbox][data-taxonomy=…])
    $('input[type=checkbox][data-taxonomy="' + tax + '"]:checked').each(
      function () {
        ids.push($(this).val());
      }
    );

    // your drilldown widget: .drilldown-generated inputs
    $('.drilldown-menu[data-taxonomy="' + tax + '"] .drilldown-generated').each(
      function () {
        ids.push($(this).val());
      }
    );

    // de-duplicate & strip empties
    ids = $.grep(ids, function (v, i) {
      return v && $.inArray(v, ids) === i;
    });

    return ids;
  }

  // 3) find which section a taxonomy belongs to and generate a section ID
  function getSectionForTaxonomy(tax) {
    var $taxonomyElement = $('[data-taxonomy="' + tax + '"]').first();

    if ($taxonomyElement.length) {
      // Look for section-level containers
      var $sectionContainer = $taxonomyElement.closest(
        ".submit-page-form-row, .section, .form-section, .taxonomy-section, .step-content, .tab-content, .add-listing-section"
      );

      if ($sectionContainer.length) {
        // Generate a unique section ID based on its position or existing ID
        var sectionId =
          $sectionContainer.attr("id") ||
          "section-" + $sectionContainer.index();
        return {
          id: sectionId,
          container: $sectionContainer,
        };
      }
    }

    return null;
  }

  // 4) get term names for display in section title
  function getTermNamesFor(tax, termIds) {
    var names = [];

    termIds.forEach(function (termId) {
      // Try to get term name from select options
      var $option = $(
        '[data-taxonomy="' + tax + '"] option[value="' + termId + '"]'
      );
      if ($option.length) {
        names.push($option.text());
        return;
      }

      // Try to get from checkbox labels
      var $checkbox = $(
        'input[type=checkbox][data-taxonomy="' +
          tax +
          '"][value="' +
          termId +
          '"]'
      );
      if ($checkbox.length) {
        var $label = $('label[for="' + $checkbox.attr("id") + '"]');
        if ($label.length) {
          names.push($label.text());
          return;
        }
      }

      // Try to get from drilldown generated inputs (look for data attributes or nearby text)
      var $drilldownInput = $(
        '.drilldown-menu[data-taxonomy="' +
          tax +
          '"] .drilldown-generated[value="' +
          termId +
          '"]'
      );
      if ($drilldownInput.length) {
        // Try to find associated text/label
        var name =
          $drilldownInput.data("label") ||
          $drilldownInput.val() ||
          $drilldownInput.data("term-name") ||
          $drilldownInput.attr("data-name") ||
          listeo_core.selectedTerm;
        names.push('"' + name + '"');
        return;
      }

      // Fallback
      names.push("Term " + termId);
    });

    return names;
  }

  // 5) create or get custom fields section for a form section
  function getCustomFieldsSection(sectionInfo) {
    var customSectionId = "custom-fields-" + sectionInfo.id;
    var $existingSection = $("#" + customSectionId);

    if ($existingSection.length) {
      return $existingSection;
    }

    // if there's a hidden input with id listeo_form_steps_json, it holds steps for the form, we need to add this newly created section to currently displayed step

    // Create new custom fields section
    var sectionHtml =
      '<div id="' +
      customSectionId +
      '" class="add-listing-section row custom-term-features active" style="display: none;">' +
      '<div class="add-listing-headline">' +
      '<h3 class="custom-fields-title">' +
      listeo_core.customField +
      "</h3>" +
      "</div>" +
      '<div class="custom-term-features-content"></div>' +
      "</div>";

    // Insert after the form section
    sectionInfo.container.after(sectionHtml);

    var $newSection = $("#" + customSectionId);

    // Check if multi-step form is enabled and add section to current step
    var $submitPage = $(".submit-page");
    var isMultiStepEnabled = $submitPage.hasClass("multi-step-form");

    if (isMultiStepEnabled) {
      addSectionToCurrentStep($newSection[0]);
    }

    return $newSection;
  }

  // Function to add a section to the current step in the JSON configuration
  function addSectionToCurrentStep(newSection) {
    var $stepsInput = $('#listeo_form_steps_json');
    
    if (!$stepsInput.length) {
      console.log("Steps JSON input not found");
      return;
    }

    try {
      // Get the current steps configuration
      var stepsJson = $stepsInput.val();
      if (!stepsJson) {
        console.log("No steps configuration found");
        return;
      }

      // Decode HTML entities
      var textarea = document.createElement("textarea");
      textarea.innerHTML = stepsJson;
      var decodedSteps = textarea.value;

      var stepConfiguration = JSON.parse(decodedSteps);
      
      if (!Array.isArray(stepConfiguration)) {
        console.log("Invalid step configuration format");
        return;
      }

      // Find the current step by checking which step has active sections
      var currentStepIndex = getCurrentStepIndex();
      
      if (currentStepIndex === -1) {
        console.log("Could not determine current step, defaulting to step 0");
        currentStepIndex = 0;
      }

      // Make sure we have a valid step to add to
      if (currentStepIndex >= stepConfiguration.length) {
        console.log("Current step index exceeds available steps");
        return;
      }

      // Use the class selector instead of ID for consistency with other selectors
      var newSelector = '.custom-term-features';

      // Add the new selector to the current step
      if (!stepConfiguration[currentStepIndex].selectors) {
        stepConfiguration[currentStepIndex].selectors = [];
      }

      // Check if selector already exists
      if (stepConfiguration[currentStepIndex].selectors.indexOf(newSelector) === -1) {
        stepConfiguration[currentStepIndex].selectors.push(newSelector);
        
        // Update the hidden input with the new configuration
        var updatedJson = JSON.stringify(stepConfiguration);
        $stepsInput.val(updatedJson);
        
        console.log("Added section to step", currentStepIndex, "with selector:", newSelector);
        
        // Refresh the steps configuration in the step system
        if (typeof window.refreshStepsConfiguration === 'function') {
          window.refreshStepsConfiguration();
        }
        
        // If this is the current step, make sure the section is visible
        if (isCurrentStep(currentStepIndex)) {
          newSection.classList.add('active');
          newSection.style.display = '';
        }
      } else {
        console.log("Selector already exists in step", currentStepIndex);
        // Even if selector exists, make sure the section is visible if it's the current step
        if (isCurrentStep(currentStepIndex)) {
          newSection.classList.add('active');
          newSection.style.display = '';
        }
      }

    } catch (e) {
      console.error("Error updating step configuration:", e);
    }
  }

  // Function to determine the current step index
  function getCurrentStepIndex() {
    // Look for the progress step that has the 'active' class
    var $activeStep = $(".form-progress-step.active");
    if ($activeStep.length) {
      return $(".form-progress-step").index($activeStep);
    }

    // Fallback: look for sections that are currently visible/active
    var $activeSections = $(".add-listing-section.active:visible");
    if ($activeSections.length === 0) {
      return -1;
    }

    // Try to match visible sections with step configuration
    var $stepsInput = $("#listeo_form_steps_json");
    if ($stepsInput.length) {
      try {
        var textarea = document.createElement("textarea");
        textarea.innerHTML = $stepsInput.val();
        var stepConfiguration = JSON.parse(textarea.value);

        // Check each step to see which one contains the most active sections
        for (var i = 0; i < stepConfiguration.length; i++) {
          var step = stepConfiguration[i];
          var matchCount = 0;

          if (step.selectors) {
            step.selectors.forEach(function (selector) {
              var $matchingSections = $(selector);
              $matchingSections.each(function () {
                if ($(this).hasClass("active") && $(this).is(":visible")) {
                  matchCount++;
                }
              });
            });
          }

          // If this step has active sections, it's likely the current step
          if (matchCount > 0) {
            return i;
          }
        }
      } catch (e) {
        console.error(
          "Error parsing step configuration for current step detection:",
          e
        );
      }
    }

    return 0; // Default to first step
  }

  // Function to check if a given step index is the current step
  function isCurrentStep(stepIndex) {
    var currentIndex = getCurrentStepIndex();
    return currentIndex === stepIndex;
  }

  
  // 6) update section title based on terms
  function updateSectionTitle($section, sectionId) {
    var $title = $section.find(".custom-fields-title");
    var allNames = [];

    // Collect all term names for this section
    if (sectionTerms[sectionId]) {
      Object.keys(sectionTerms[sectionId]).forEach(function (tax) {
        var termIds = sectionTerms[sectionId][tax];
        if (termIds.length > 0) {
          var names = getTermNamesFor(tax, termIds);
          allNames = allNames.concat(names);
        }
      });
    }

    if (allNames.length > 0) {
      $title.text(listeo_core.customFieldsFor + " " + allNames.join(" & "));
    } else {
      $title.text(listeo_core.customFields);
    }
  }

  // 7) fetch custom fields for a specific term
  function fetchCustomFieldsForTerm(tax, termId, callback) {
    var requestKey = tax + "-" + termId;

    // Cancel any existing request for this term
    if (activeRequests[requestKey]) {
      activeRequests[requestKey].abort();
    }

    activeRequests[requestKey] = $.ajax({
      type: "POST",
      url: listeo.ajaxurl,
      dataType: "json",
      data: {
        action: "listeo_get_custom_fields_from_term",
        cat_ids: [termId], // Only this specific term
        listing_id: listingId,
        term: tax,
        panel: false,
        nonce: listeo.nonce_get_custom_fields,
      },
      success: function (data) {
        var html = data.output || "";
        
        // Only proceed if there's actual content to display
        // Check if html contains actual form fields, not just empty content
        if (html && html.trim().length > 0 && !isEmptyContent(html)) {
          termFields[requestKey] = html; // Cache the result for this specific term
          callback && callback(tax, termId, html);
          
          // Debug: Log when we're about to initialize Select2 for custom fields
          console.log("About to initialize Select2 after loading custom term fields for:", tax, termId);
          
          // Add a small delay to ensure DOM is ready
          setTimeout(function() {
            // Only destroy Select2 instances that are within newly loaded dynamic content
            // Don't destroy existing instances that were properly initialized by the theme
            var $newlyLoadedContainer = $('[data-term-fields]').filter(function() {
              return $(this).data('recently-loaded') === true;
            });
            
            if ($newlyLoadedContainer.length > 0) {
              var existingSelect2InNewContent = $newlyLoadedContainer.find('select.select2-hidden-accessible');
              if (existingSelect2InNewContent.length > 0) {
                console.log("Found Select2 instances in newly loaded term fields:", existingSelect2InNewContent.length);
                existingSelect2InNewContent.select2('destroy');
              }
            }
            
            // Initialize Select2 and DateRangePicker only for the newly loaded content
            var $newlyLoadedContent = $('[data-recently-loaded="true"]');
            if ($newlyLoadedContent.length > 0) {
              // Initialize Select2 directly on the newly loaded elements only
              initializeSelect2ForNewContent($newlyLoadedContent);
              initializeDateRangePicker($newlyLoadedContent);
            }
            console.log("Select2 initialization completed for custom term fields");
            
            // Remove the "recently-loaded" flag after initialization to prevent future conflicts
            setTimeout(function() {
              $('[data-recently-loaded="true"]').removeAttr('data-recently-loaded');
            }, 100);
            
            // Watch for any other scripts that might re-initialize Select2
            setTimeout(function() {
              var $termFieldSelects = $('[data-term-fields] select.select2-single');
              $termFieldSelects.each(function() {
                var $select = $(this);
                var $container = $select.next('.select2-container');
                var $rendered = $container.find('.select2-selection__rendered');
                if ($rendered.text() && $rendered.text() !== $select.data('placeholder') && $select.val() === '') {
                  console.log("WARNING: Select2 placeholder may have been overridden by another script");
                }
              });
            }, 500);
          }, 100);
        } else {
          // No custom fields for this term, store empty and don't create section
          termFields[requestKey] = "";
          // Don't call callback to avoid creating empty sections
          console.log("No custom fields found for", tax, termId);
        }
      },
      error: function (xhr, status, error) {
        if (status !== "abort") {
          termFields[requestKey] = "";
          // Don't call callback on error to avoid creating empty sections
        }
      },
      complete: function () {
        delete activeRequests[requestKey];
      },
    });
  }

  // Helper function to check if HTML content is actually empty or just whitespace/divs
  function isEmptyContent(html) {
    if (!html || html.trim() === '') {
      return true;
    }
    
    // Create a temporary element to parse the HTML
    var temp = $('<div>').html(html.trim());
    
    // Remove empty elements and whitespace
    temp.find('*').each(function() {
      if ($(this).is(':empty') && $(this).text().trim() === '') {
        $(this).remove();
      }
    });
    
    // Check if there are any actual form elements
    var hasFormElements = temp.find('input, select, textarea, button').length > 0;
    var hasTextContent = temp.text().trim().length > 0;
    
    return !hasFormElements && !hasTextContent;
  }

  // New function specifically for initializing Select2 on newly loaded content
  function initializeSelect2ForNewContent($context) {
    // Only process select elements within the provided context (newly loaded content)
    $context.find(".select2-multiple").each(function () {
      // Prevent double init
      if (!$(this).hasClass("select2-hidden-accessible")) {
        $(this).select2({
          dropdownPosition: "below",
          width: "100%",
          placeholder: $(this).data("placeholder"),
          language: {
            noResults: function () {
              return listeo_core.no_results_text;
            },
          },
        });
      }
    });
    
    $context.find(".select2-single").each(function () {
      // Prevent double init
      if (!$(this).hasClass("select2-hidden-accessible")) {
        var $select = $(this);
        var placeholder = $select.data("placeholder") || $select.attr("data-placeholder");
        
        var select2Config = {
          dropdownPosition: "below",
          minimumResultsForSearch: 20,
          width: "100%",
          language: {
            noResults: function (term) {
              return listeo_core.no_results_text;
            },
          },
        };
        
        // Add placeholder if available
        if (placeholder) {
          select2Config.placeholder = placeholder;
          select2Config.escapeMarkup = function(markup) { return markup; };
        }
        
        $select.select2(select2Config);
      }
    });
  }

  // Original function kept for backward compatibility but with added safety
  function initializeSelect2($context = $(document)) {
    // Only initialize Select2 for elements that aren't already initialized
    $context.find(".select2-multiple").each(function () {
      // Prevent double init
      if (!$(this).hasClass("select2-hidden-accessible")) {
        $(this).select2({
          dropdownPosition: "below",
          width: "100%",
          placeholder: $(this).data("placeholder"),
          language: {
            noResults: function () {
              return listeo_core.no_results_text;
            },
          },
        });
      }
    });
    
    // For single selects, be more careful about which ones to initialize
    $context.find(".select2-single").each(function () {
      // Prevent double init
      if (!$(this).hasClass("select2-hidden-accessible")) {
        var $select = $(this);
        var placeholder = $select.data("placeholder") || $select.attr("data-placeholder");
        
        // Debug logging for custom term field selects
        if ($select.closest('[data-term-fields]').length > 0) {
          console.log("Initializing Select2 for custom term field select:", {
            element: $select[0],
            placeholder: placeholder,
            hasEmptyOption: $select.find('option[value=""]').length > 0,
            firstOption: $select.find('option:first').text(),
            firstOptionValue: $select.find('option:first').val()
          });
        }
        
        var select2Config = {
          dropdownPosition: "below",
          minimumResultsForSearch: 20,
          width: "100%",
          language: {
            noResults: function (term) {
              return listeo_core.no_results_text;
            },
          },
        };
        
        // Add placeholder if available - simplified approach without allowClear
        if (placeholder) {
          select2Config.placeholder = placeholder;
          select2Config.escapeMarkup = function(markup) { return markup; }; // Allow HTML in options
        }
        
        $select.select2(select2Config);
        
        // Debug: Check if Select2 was initialized correctly
        if ($select.closest('[data-term-fields]').length > 0) {
          console.log("Select2 initialized. Placeholder working:", $select.hasClass("select2-hidden-accessible"));
          
          // Additional debugging - check what Select2 actually created
          setTimeout(function() {
            var $select2Container = $select.next('.select2-container');
            var $select2Selection = $select2Container.find('.select2-selection__rendered');
            var currentText = $select2Selection.text() || $select2Selection.attr('title');
            
            console.log("Post-initialization Select2 debug:", {
              containerExists: $select2Container.length > 0,
              currentDisplayText: currentText,
              hasPlaceholderClass: $select2Selection.hasClass('select2-selection__placeholder'),
              select2Config: select2Config,
              selectedValue: $select.val(),
              select2Data: $select.select2('data')
            });
            
            // Force trigger change to see if it helps
            if (currentText !== placeholder && $select.val() === '') {
              console.log("Forcing Select2 to show placeholder...");
              $select.val('').trigger('change');
            }
          }, 50);
        }
      }
    });
  }

  // 8) update the display for a specific term in its section - ENHANCED WITH DEBUGGING
  function updateTermDisplay(tax, termId, html) {
    var termKey = tax + "-" + termId;
    var $existingTermDiv = $('[data-term-fields="' + termKey + '"]');

    console.log("updateTermDisplay called for", termKey, "with HTML length:", html ? html.length : 0);

    // Find which section this taxonomy belongs to
    var sectionInfo = getSectionForTaxonomy(tax);
    if (!sectionInfo) {
      console.warn("Could not find section for taxonomy:", tax);
      return;
    }

    console.log("Found section info for", tax, ":", sectionInfo.id);

    // Only create/get custom section if we have actual content to display
    if (html && html.trim().length > 0 && !isEmptyContent(html)) {
      console.log("Creating/getting custom section for", termKey);
      
      var $customSection = getCustomFieldsSection(sectionInfo);
      var $content = $customSection.find(".custom-term-features-content");

      console.log("Custom section found/created:", $customSection.length > 0);
      console.log("Content container found:", $content.length > 0);

      var wrappedHtml =
        '<div data-term-fields="' +
        termKey +
        '" data-taxonomy="' +
        tax +
        '" data-term="' +
        termId +
        '" data-recently-loaded="true">' +
        html +
        "</div>";

      if ($existingTermDiv.length) {
        console.log("Replacing existing term fields for", termKey);
        // Replace existing term fields
        $existingTermDiv.replaceWith(wrappedHtml);
      } else {
        console.log("Adding new term fields for", termKey);
        // Add new term fields to the section
        $content.append(wrappedHtml);
      }

      // Update section terms tracking
      if (!sectionTerms[sectionInfo.id]) {
        sectionTerms[sectionInfo.id] = {};
      }
      if (!sectionTerms[sectionInfo.id][tax]) {
        sectionTerms[sectionInfo.id][tax] = [];
      }
      if (sectionTerms[sectionInfo.id][tax].indexOf(termId) === -1) {
        sectionTerms[sectionInfo.id][tax].push(termId);
      }

      // Update section title and show section
      updateSectionTitle($customSection, sectionInfo.id);
      
      // Handle visibility based on whether multi-step is enabled
      var $submitPage = $(".submit-page");
      var isMultiStepEnabled = $submitPage.hasClass("multi-step-form");
      
      console.log("Multi-step enabled:", isMultiStepEnabled);
      
      if (isMultiStepEnabled) {
        // In multi-step mode, check if we're in the current step
        var currentStepIndex = getCurrentStepIndex();
        console.log("Current step index:", currentStepIndex);
        
        if (isCurrentStep(currentStepIndex)) {
          console.log("Showing custom section for current step");
          $customSection.addClass('active');
          $customSection.show();
        } else {
          console.log("Hiding custom section (not current step)");
          $customSection.removeClass('active');
          $customSection.hide();
        }
      } else {
        // In regular mode (no steps), show the section normally
        console.log("Showing custom section (no multi-step)");
        $customSection.addClass('active');
        $customSection.show();
      }
      
    } else {
      console.log("No HTML or empty content for", termKey, "- removing/hiding");
      
      // Remove term fields if they exist (empty content or removal)
      if ($existingTermDiv.length) {
        $existingTermDiv.remove();
      }

      // Update section terms tracking
      if (sectionTerms[sectionInfo.id] && sectionTerms[sectionInfo.id][tax]) {
        var index = sectionTerms[sectionInfo.id][tax].indexOf(termId);
        if (index > -1) {
          sectionTerms[sectionInfo.id][tax].splice(index, 1);
        }

        // Clean up empty arrays
        if (sectionTerms[sectionInfo.id][tax].length === 0) {
          delete sectionTerms[sectionInfo.id][tax];
        }
      }

      // Check if we have a custom section and if it should be hidden
      var customSectionId = "custom-fields-" + sectionInfo.id;
      var $customSection = $("#" + customSectionId);
      
      if ($customSection.length) {
        var $content = $customSection.find(".custom-term-features-content");
        
        // Hide section if no more fields
        if ($content.children().length === 0) {
          console.log("Hiding empty custom section");
          $customSection.hide();
          $customSection.removeClass('active');
        } else {
          // Update title for remaining terms
          updateSectionTitle($customSection, sectionInfo.id);
        }
      }
    }
  }

  // 9) compare current terms with loaded terms and update only what changed
  function syncTaxonomyTerms(tax) {
    var currentTerms = getTermIdsFor(tax);
    var previousTerms = loadedTerms[tax] || [];

    console.log("Syncing taxonomy", tax, "- Current:", currentTerms, "Previous:", previousTerms);

    // Find newly added terms (need to fetch)
    var newTerms = currentTerms.filter(function (termId) {
      return previousTerms.indexOf(termId) === -1;
    });

    // Find removed terms (need to remove from display)
    var removedTerms = previousTerms.filter(function (termId) {
      return currentTerms.indexOf(termId) === -1;
    });

    console.log("New terms:", newTerms, "Removed terms:", removedTerms);

    // Remove fields for deselected terms
    removedTerms.forEach(function (termId) {
      var termKey = tax + "-" + termId;
      console.log("Removing term display for", termKey);
      updateTermDisplay(tax, termId, ""); // This will remove the term
      delete termFields[termKey]; // Clear from cache
    });

    // Update loaded terms list BEFORE fetching new ones
    loadedTerms[tax] = currentTerms.slice(); // Clone array

    // If no new terms to fetch, just finish
    if (newTerms.length === 0) {
      console.log("No new terms to fetch for", tax);
      return;
    }

    // Fetch fields for newly added terms
    newTerms.forEach(function (termId) {
      console.log("Fetching custom fields for new term:", tax, termId);
      fetchCustomFieldsForTerm(tax, termId, function (taxonomy, term, html) {
        console.log("New term callback for", taxonomy, term, "with HTML length:", html ? html.length : 0);
        updateTermDisplay(taxonomy, term, html);
      });
    });
  }

  // 10) initialize display for all terms (initial page load) - FIXED VERSION
  function initializeAllTerms() {
    var taxes = getTaxonomies();

    if (taxes.length === 0) {
      return;
    }

    // Clear any existing state first
    termFields = {};
    loadedTerms = {};
    sectionTerms = {};

    // Fetch all initial terms
    taxes.forEach(function (tax) {
      var terms = getTermIdsFor(tax);
      
      // Only proceed if there are actually terms selected
      if (terms.length > 0) {
        console.log("Initializing terms for", tax, ":", terms);
        
        // Initialize loaded terms array for this taxonomy
        loadedTerms[tax] = [];

        terms.forEach(function (termId) {
          fetchCustomFieldsForTerm(tax, termId, function (taxonomy, term, html) {
            console.log("Initial term callback for", taxonomy, term, "with HTML length:", html ? html.length : 0);
            updateTermDisplay(taxonomy, term, html);
            
            // Add to loaded terms after successful display
            if (!loadedTerms[taxonomy]) {
              loadedTerms[taxonomy] = [];
            }
            if (loadedTerms[taxonomy].indexOf(term) === -1) {
              loadedTerms[taxonomy].push(term);
            }
          });
        });
      } else {
        // No terms selected for this taxonomy, initialize empty
        loadedTerms[tax] = [];
      }
    });
  }

  // Enhanced debounce function with better logging
  function debounceSyncTaxonomy(tax) {
    console.log("Debouncing sync for taxonomy:", tax);
    clearTimeout(timers[tax]);
    timers[tax] = setTimeout(function () {
      console.log("Executing delayed sync for taxonomy:", tax);
      syncTaxonomyTerms(tax);
    }, 150);
  }

  // Enhanced debounce initialization with better logging
  function debounceInitialize() {
    console.log("Debouncing initialization");
    // Clear all existing timers
    for (var tax in timers) {
      clearTimeout(timers[tax]);
    }

    timers["init"] = setTimeout(function () {
      console.log("Executing delayed initialization");
      initializeAllTerms();
    }, 300); // Increased delay slightly to ensure DOM is ready
  }

  // 13) hook it all up
  $(function () {
    // initial load - fetch all terms
    debounceInitialize();
  });

  // on any change of those widgets, or custom drilldown event:
  $(document)
    .on("change", "[data-taxonomy]", function () {
      var changedTaxonomy = $(this).data("taxonomy");
      // Only sync the taxonomy that changed (add/remove terms as needed)
      debounceSyncTaxonomy(changedTaxonomy);
    })
    .on("drilldown-updated", ".drilldown-menu", function () {
      var changedTaxonomy = $(this).data("taxonomy");
      // Only sync the taxonomy that changed (add/remove terms as needed)
      debounceSyncTaxonomy(changedTaxonomy);
    });

  // Target the form on the preview page
  var $previewForm = $("form#listing_preview");

  if ($previewForm.length) {
    $previewForm.on("submit", function () {
      // Find the continue button (adjust the selector if needed)
      var $continueButton = $(this).find('input[name="continue"]');
      var $editButton = $(this).find('input[name="edit_listing"]');

      // If the continue button was clicked
      if ($continueButton.is(":focus")) {
        // Disable it to prevent multiple submissions
        $continueButton
          .prop("disabled", true)
          .css("opacity", "0.5")
          .val("Processing...");
      } else if ($editButton.is(":focus")) {
        // If edit button was clicked, disable that instead
        $editButton
          .prop("disabled", true)
          .css("opacity", "0.5")
          .val("Processing...");
      }

      // The form continues submission normally
      return true;
    });
  }

  // Also add similar protection to the main submit listing form
  var $submitForm = $("form#submit-listing-form");

  if ($submitForm.length) {
    $submitForm.on("submit", function (e) {
      var isFormValid = true;
      var firstInvalidField = null;

      function markInvalid($el) {
        isFormValid = false;
        $el.addClass('msf-input-error');
        if (!firstInvalidField) {
          firstInvalidField = $el;
        }
      }

      // Validate required Select2 fields
      $(this).find('select[required].select2-hidden-accessible').each(function() {
        var $select = $(this);
        var value = $select.val();
        var $container = $select.next('.select2-container');

        if (!value || value === '-1' || value === '') {
          $select.addClass('msf-input-error');
          if ($container.length) {
            $container.addClass('msf-input-error');
          }
          isFormValid = false;
          if (!firstInvalidField) {
            firstInvalidField = $container.length ? $container : $select;
          }
        } else {
          $select.removeClass('msf-input-error');
          if ($container.length) {
            $container.removeClass('msf-input-error');
          }
        }
      });

      // Validate required input and textarea fields
      $(this).find('input[required], textarea[required]').each(function() {
        var $field = $(this);
        // Skip hidden inputs and Select2-managed selects
        if ($field.attr('type') === 'hidden') {
          return;
        }
        var value = $.trim($field.val());
        if (!value) {
          markInvalid($field);
        } else {
          $field.removeClass('msf-input-error');
        }
      });

      // Validate required wp-editor/TinyMCE fields
      // Required wp-editor fields have a label with <i>*</i> indicator
      $(this).find('.wp-editor-wrap').each(function() {
        var $wrap = $(this);
        var $textarea = $wrap.find('textarea.wp-editor-area');
        if (!$textarea.length) {
          return;
        }
        var editorId = $textarea.attr('id');
        // Check if this field is required by looking at its label
        var $container = $wrap.closest('.col-md-12, .col-md-6, .col-md-4, .col-md-3, [class*="col-md-"]');
        var $label = $container.find('label').first();
        var isRequired = $label.find('i').length > 0 && $label.find('i').not('.tip').length > 0;

        if (!isRequired) {
          return;
        }

        // Get content from TinyMCE if active, otherwise from textarea
        var content = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
          content = tinyMCE.get(editorId).getContent({ format: 'text' });
        } else {
          content = $textarea.val();
        }

        if (!$.trim(content)) {
          markInvalid($wrap);
        } else {
          $wrap.removeClass('msf-input-error');
        }
      });

      // If validation failed, prevent submission and focus on first invalid field
      if (!isFormValid) {
        e.preventDefault();

        if (firstInvalidField) {
          $('html, body').animate({
            scrollTop: firstInvalidField.offset().top - 100
          }, 500);

          // Try to open Select2 dropdown if applicable
          var $select = firstInvalidField.prev('select');
          if ($select.length && $select.hasClass('select2-hidden-accessible')) {
            setTimeout(function() {
              $select.select2('open');
            }, 600);
          }
        }

        return false;
      }

      // If validation passed, proceed with form submission
      var $submitButton = $(this).find('input[type="submit"], button[type="submit"]');

      // Preserve submit button name/value as hidden input before disabling,
      // because disabled buttons are excluded from form serialization
      $submitButton.each(function() {
        var name = $(this).attr('name');
        var value = $(this).val();
        if (name) {
          $(this).after('<input type="hidden" name="' + name + '" value="' + value + '">');
        }
      });

      $submitButton
        .prop("disabled", true)
        .css("opacity", "0.5");

      return true;
    });
  }

  // Create buttons for each time input but hide them initially
  $(".listeo-flatpickr").each(function () {
    var copyButton = $("<button>", {
      text: listeo_core.copytoalldays,
      class: "copy-time-button",
      css: {
        marginTop: "5px",
        display: "none", // Hide buttons by default
      },
    });

    $(this).after(copyButton);
  });

  // Handle hover events on day rows
  $(".opening-day").each(function () {
    $(this).hover(
      function () {
        // On hover in - show only this day's buttons
        $(this).find(".copy-time-button").show();
      },
      function () {
        // On hover out - hide this day's buttons
        $(this).find(".copy-time-button").hide();
      }
    );
  });

  // Handle button clicks
  $(".copy-time-button").on("click", function (e) {
    e.preventDefault();

    var input = $(this).prev(".listeo-flatpickr");
    var timeValue = input.val();

    if (!timeValue) {
      alert(listeo_core.selectimefirst);
      return;
    }

    // Determine if this is an opening or closing time input
    var isOpeningTime = input.attr("name").includes("opening");

    // Extract the day token via regex so this works regardless of the
    // field's name prefix. Core renders inputs as `_DAY_opening_hour[]`
    // but LBP uses `_lbp_DAY_opening_hour[]` — splitting on "_" and
    // grabbing index [1] returns "lbp" for every LBP input, which made
    // the targetDay !== currentDay guard reject every copy.
    var dayPattern = /_([a-z]+)_(?:opening|closing)_hour/i;
    var currentMatch = input.attr("name").match(dayPattern);
    var currentDay = currentMatch ? currentMatch[1] : null;

    // Get all time inputs of the same type (opening or closing)
    var selector =
      '.listeo-flatpickr[name*="' +
      (isOpeningTime ? "opening" : "closing") +
      '_hour"]';
    var allInputs = $(selector);

    // Copy the time to all other days
    allInputs.each(function () {
      var targetInput = $(this);
      var targetMatch = targetInput.attr("name").match(dayPattern);
      var targetDay = targetMatch ? targetMatch[1] : null;

      if (targetDay && targetDay !== currentDay) {
        targetInput.val(timeValue);

        // Trigger change event to ensure any linked functionality updates
        targetInput.trigger("change");

        // If using flatpickr, update its instance
        if (targetInput[0]._flatpickr) {
          targetInput[0]._flatpickr.setDate(timeValue, true);
        }
      }
    });
  });

  // FullCalendar Initialization for Availability Calendar
  if ($("#fullcalendar").length) {
    var calendarEl = document.getElementById("fullcalendar");

    // Parse existing blocked dates (format: DD-MM-YYYY|DD-MM-YYYY|...)
    var blockedDatesInput = $("#fullcalendar-blocked-dates").val();
    var blockedDates = new Set();

    if (blockedDatesInput) {
      blockedDatesInput.split("|").forEach(function (dateStr) {
        if (dateStr.trim() !== "") {
          // Convert from DD-MM-YYYY to YYYY-MM-DD for FullCalendar
          var parts = dateStr.split("-");
          if (parts.length === 3) {
            blockedDates.add(parts[2] + "-" + parts[1] + "-" + parts[0]);
          }
        }
      });
    }

    // Parse existing price data (format: {"DD-MM-YYYY":"price",...})
    var priceDataInput = $("#fullcalendar-price-data").val();
    var priceData = {};

    if (priceDataInput) {
      try {
        var parsedPrices = JSON.parse(priceDataInput);
        // Convert keys from DD-MM-YYYY to YYYY-MM-DD for FullCalendar
        Object.keys(parsedPrices).forEach(function (dateStr) {
          if (dateStr.match(/^\d{2}-\d{2}-\d{4}$/)) {
            var parts = dateStr.split("-");
            priceData[parts[2] + "-" + parts[1] + "-" + parts[0]] =
              parsedPrices[dateStr];
          }
        });
      } catch (e) {
        console.error("Error parsing price data:", e);
      }
    }

    // Track currently selected dates
    var selectedDates = [];

    // Track clicks for double-click detection
    var lastClickTime = 0;
    var lastClickDate = null;

    // Track the tooltip position and state
    var tooltipVisible = false;
    var lastSelectionEnd = null;

    // Tooltip positioned near the clicked date with smart positioning
    function showTooltipForDate(dateStr) {
      // Remove any existing tooltips first
      $("#selection-tooltip, #single-date-tooltip").remove();
      $("body").removeClass("has-date-tooltip");

      if (!dateStr) {
        tooltipVisible = false;
        return;
      }

      // Find the cell for the clicked date
      var dayCell = $(
        ".fc-day[data-date='" +
          dateStr +
          "'], .fc-daygrid-day[data-date='" +
          dateStr +
          "']"
      );

      if (!dayCell.length) {
        console.error("Could not find calendar cell for date:", dateStr);
        return;
      }

      // Get position of the calendar and day cell
      var calendar = $("#fullcalendar");
      var calendarOffset = calendar.offset();
      var cellOffset = dayCell.offset();

      // Add class to body
      $("body").addClass("has-date-tooltip");

      // Create tooltip - CSS is now in the theme's style.css file
      var tooltip = $("<div>", {
        id: "single-date-tooltip",
        class: "selection-tooltip",
      });

      // Add count of selected dates (just 1 in this case)
      var selectionText = $("<div>", {
        text: listeo_core.one_date_selected,
        css: {
          marginRight: "10px",
          alignSelf: "center",
          fontWeight: "bold",
        },
      });

      // Add buttons - styles are now in the theme's style.css file
      var blockBtn = $("<button>", {
        text: listeo_core.block,
        type: "button",
        class: "tooltip-btn block-btn",
      }).on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Add to selection then trigger block
        selectedDates = [dateStr];
        $("#block-dates-btn").trigger("click");

        return false;
      });

      var priceBtn = $("<button>", {
        text: listeo_core.setprice,
        type: "button",
        class: "tooltip-btn price-btn",
      }).on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Add to selection then trigger set price
        selectedDates = [dateStr];
        $("#set-price-btn").trigger("click");

        return false;
      });

      var clearBtn = $("<button>", {
        text: listeo_core.unblock,
        type: "button",
        class: "tooltip-btn clear-btn",
      }).on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Add to selection then trigger unblock
        selectedDates = [dateStr];
        $("#clear-selection-btn").trigger("click");

        return false;
      });

      // Add buttons directly to tooltip for horizontal layout
      tooltip.append(selectionText, blockBtn, priceBtn, clearBtn);

      // Add tooltip to the calendar
      $("#fullcalendar").append(tooltip);
      tooltipVisible = true;

      // Position the tooltip - smart positioning to avoid window edge
      positionTooltip(tooltip, dayCell);

      // Prevent clicks on the tooltip from bubbling to document
      tooltip.on("click", function (e) {
        e.stopPropagation();
      });
    }

    // Original tooltip function (still used for multi-select)
    function showSelectionTooltip() {
      // Skip if there are no selected dates
      if (selectedDates.length === 0) {
        tooltipVisible = false;
        return;
      }

      // If it's a single date selection, use the standalone tooltip
      if (selectedDates.length === 1) {
        showTooltipForDate(selectedDates[0]);
        return;
      }

      // For multiple dates, use the original tooltip
      // Remove any existing tooltips
      $("#selection-tooltip, #single-date-tooltip").remove();
      $("body").removeClass("has-date-tooltip");

      // Mark document body with tooltip-active class
      $("body").addClass("has-date-tooltip");

      // Create tooltip element - using CSS from style.css
      var tooltip = $("<div>", {
        id: "selection-tooltip",
        class: "selection-tooltip",
      });

      // Add buttons - using CSS from style.css
      var blockBtn = $("<button>", {
        text: listeo_core.block,
        type: "button",
        class: "tooltip-btn block-btn",
      }).on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $("#block-dates-btn").trigger("click");
        return false;
      });

      var priceBtn = $("<button>", {
        text: listeo_core.setprice,
        type: "button",
        class: "tooltip-btn price-btn",
      }).on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $("#set-price-btn").trigger("click");
        return false;
      });

      var clearBtn = $("<button>", {
        text: listeo_core.unblock,
        type: "button",
        class: "tooltip-btn clear-btn",
      }).on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $("#clear-selection-btn").trigger("click");
        return false;
      });

      // Add count of selected dates
      var selectionText = $("<div>", {
        text: selectedDates.length + listeo_core.dates_selected,
        css: {
          marginRight: "10px",
          alignSelf: "center",
          fontWeight: "bold",
        },
      });

      // Add buttons to tooltip
      tooltip.append(selectionText, blockBtn, priceBtn, clearBtn);

      // Add to calendar container first (needed for width calculation)
      $("#fullcalendar").after(tooltip);
      tooltipVisible = true;

      // Find the last selected date's cell for smart positioning
      if (lastSelectionEnd) {
        var dateCell = $(
          `.fc-day[data-date="${lastSelectionEnd}"], .fc-daygrid-day[data-date="${lastSelectionEnd}"]`
        );
        if (dateCell.length) {
          // We have a valid cell, use smart positioning
          positionTooltip(tooltip, dateCell);
        } else {
          // Fallback to old positioning
          var position = getTooltipPosition();
          tooltip.css({
            top: position.top + "px",
            left: position.left + "px",
          });
        }
      } else {
        // Fallback to calendar position
        var position = getTooltipPosition();
        tooltip.css({
          top: position.top + "px",
          left: position.left + "px",
        });
      }
    }

    // Position tooltip with smart boundary detection
    function positionTooltip(tooltip, targetCell) {
      // Get the necessary dimensions and positions
      var calendar = $("#fullcalendar");
      var calendarOffset = calendar.offset();
      var cellOffset = targetCell.offset();
      var windowWidth = $(window).width();

      // First position the tooltip for measurement
      tooltip.css({
        top:
          cellOffset.top -
          calendarOffset.top +
          targetCell.outerHeight() +
          5 +
          "px",
        left: cellOffset.left - calendarOffset.left + "px",
      });

      // Now check if it would overflow the right edge of the window
      var tooltipWidth = tooltip.outerWidth();
      var tooltipRight = cellOffset.left + tooltipWidth;
      var isOverflowing = tooltipRight > windowWidth - 20; // 20px margin

      if (isOverflowing) {
        // Position to the left side of the cell instead
        var newLeft = Math.max(0, cellOffset.left - tooltipWidth);
        tooltip.css({
          left: newLeft - calendarOffset.left + "px",
        });
      }
    }

    // Get position for the tooltip (used by multi-select tooltip)
    function getTooltipPosition() {
      var position = { top: 0, left: 0 };

      // If we have a selection end date, try to position near it
      if (lastSelectionEnd) {
        var dateCell = $(`.fc-day[data-date="${lastSelectionEnd}"]`);
        if (dateCell.length) {
          var rect = dateCell[0].getBoundingClientRect();
          var calendarRect = $("#fullcalendar")[0].getBoundingClientRect();

          position.top = rect.bottom - calendarRect.top + 10; // 10px below the cell
          position.left = rect.left - calendarRect.left + rect.width / 2; // Center horizontally

          // Check for right edge overflow
          var tooltipWidth = 300; // Approximate width of tooltip
          var rightEdge = position.left + tooltipWidth;

          if (rightEdge > calendarWidth) {
            // Move tooltip to left of cell instead
            position.left = rect.left - calendarRect.left - tooltipWidth;
            if (position.left < 0) {
              // If that would be off-screen left, position at left edge
              position.left = 10;
            }
          }
        } else {
          // Fallback to calendar position
          position.top = $("#fullcalendar").height() / 2;
          position.left = $("#fullcalendar").width() / 2;
        }
      } else {
        // Fallback to calendar position
        position.top = $("#fullcalendar").height() / 2;
        position.left = $("#fullcalendar").width() / 2;
      }

      return position;
    }

    // Create events for blocked dates and price data
    function generateEvents() {
      var events = [];

      // Add blocked dates
      blockedDates.forEach(function (dateStr) {
        events.push({
          start: dateStr,
          display: "background",
          backgroundColor: "rgba(255, 0, 0, 0.2)",
          className: "blocked-date",
          allDay: true,
        });
      });

      // Add price data
      Object.keys(priceData).forEach(function (dateStr) {
        events.push({
          start: dateStr,
          title: (listeo_core.currency_symbol || "$") + priceData[dateStr],
          className: "has-price",
          allDay: true,
        });
      });

      // Add selected dates
      selectedDates.forEach(function (dateStr) {
        events.push({
          start: dateStr,
          display: "background",
          backgroundColor: "rgba(0, 120, 215, 0.2)",
          className: "selected-date",
          allDay: true,
        });
      });

      return events;
    }

    // Initialize calendar
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: "dayGridMonth",
      locale: listeoCal.language,
      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth",
      },
      selectable: true,
      selectMirror: true,
      unselectAuto: false, // Prevent automatic unselection
      unselect: function (info) {
        // Prevent unselect behavior if tooltip is visible
        if (tooltipVisible) {
          return false;
        }
      },
      unselectCancel: ".selection-tooltip", // Prevent unselection when clicking tooltip
      events: generateEvents(),

      // Handle range selection (drag)
      select: function (info) {
        // Clear previous selection
        selectedDates = [];

        // Log selection info for debugging
        console.log("Selection info:", {
          start: info.start,
          end: info.end,
          startStr: info.startStr,
          endStr: info.endStr,
        });

        // Note: FullCalendar's end date is exclusive (the day after the last selected day)
        // So we need to adjust it to match what's visually highlighted

        // For a single day selection
        if (
          info.startStr === info.endStr ||
          new Date(info.endStr) - new Date(info.startStr) === 86400000
        ) {
          // 1 day in ms
          // This is a single day selection
          // Add regardless of whether it's blocked or not - we want to select everything
          selectedDates.push(info.startStr);
        } else {
          // This is a multi-day selection
          var start = new Date(info.startStr);
          var end = new Date(info.endStr);

          // Loop through each day from start to end-1 (since end is exclusive)
          var current = new Date(start);
          while (current < end) {
            var dateStr = current.toISOString().split("T")[0];
            console.log("Processing date:", dateStr);

            // Add all dates to selection, even if blocked
            selectedDates.push(dateStr);

            // Move to next day
            current.setDate(current.getDate() + 1);
          }
        }

        console.log("Final selected dates:", selectedDates);

        // Always store the last selected date for tooltip positioning
        lastSelectionEnd =
          selectedDates.length > 0
            ? selectedDates[selectedDates.length - 1]
            : info.startStr;

        // Highlight the selected dates
        calendar.removeAllEvents();
        calendar.addEventSource(generateEvents());

        // Show the tooltip with action buttons
        showSelectionTooltip();
      },

      // Handle clicking on dates
      dateClick: function (info) {
        var dateStr = info.dateStr;
        var now = new Date().getTime();

        // Check for double-click (within 300ms on the same date)
        if (lastClickDate === dateStr && now - lastClickTime < 300) {
          // This is a double-click - toggle blocked status

          // If already blocked, unblock it
          if (blockedDates.has(dateStr)) {
            blockedDates.delete(dateStr);

            // Update hidden input
            updateBlockedDatesInput();

            // Incremental update: remove only the blocked-date event
            var evts = calendar.getEvents();
            for (var i = 0; i < evts.length; i++) {
              if (evts[i].startStr === dateStr && evts[i].classNames.indexOf("blocked-date") !== -1) {
                evts[i].remove();
                break;
              }
            }
          } else {
            // Not blocked, so block it
            blockedDates.add(dateStr);

            // Update hidden input
            updateBlockedDatesInput();

            // Incremental update: add only the new blocked-date event
            calendar.addEvent({
              start: dateStr,
              display: "background",
              backgroundColor: "rgba(255, 0, 0, 0.2)",
              className: "blocked-date",
              allDay: true,
            });
          }

          // Reset tracking variables to prevent triple-click issues
          lastClickTime = 0;
          lastClickDate = null;

          // Hide any tooltip
          $("#selection-tooltip, #single-date-tooltip").remove();
          $("body").removeClass("has-date-tooltip");
          tooltipVisible = false;

          return;
        }

        // Not a double-click, update tracking variables
        lastClickTime = now;
        lastClickDate = dateStr;

        // Store in selection array
        selectedDates = [dateStr];

        // Prevent default calendar click behavior
        if (info.jsEvent) {
          info.jsEvent.preventDefault();
          info.jsEvent.stopPropagation();
        }

        // Show the standalone tooltip directly - completely bypass FullCalendar selection
        showTooltipForDate(dateStr);

        // Refresh calendar display
        calendar.removeAllEvents();
        calendar.addEventSource(generateEvents());
      },

      // Handle clicking on events
      eventClick: function (info) {
        var dateStr = info.event.startStr;
        console.log("Event clicked on date:", dateStr, "Event:", info.event);

        // Handle blocked dates
        if (info.event.classNames.includes("blocked-date")) {
          console.log("Clicked on blocked date:", dateStr);

          // Confirm unblocking
          if (confirm("Unblock this date?")) {
            if (blockedDates.has(dateStr)) {
              blockedDates.delete(dateStr);

              // Update hidden input
              updateBlockedDatesInput();

              // Incremental update: remove only the blocked-date event
              var evts = calendar.getEvents();
              for (var i = 0; i < evts.length; i++) {
                if (evts[i].startStr === dateStr && evts[i].classNames.indexOf("blocked-date") !== -1) {
                  evts[i].remove();
                  break;
                }
              }
            }
          }
          return;
        }

        // Handle price events
        if (info.event.classNames.includes("has-price")) {
          var currentPrice = priceData[dateStr] || "";

          var price = prompt(
            listeo_core.enterPrice +
              " " +
              dateStr +
              "\n" +
              listeo_core.leaveBlank,
            currentPrice
          );

          if (price !== null) {
            if (price === "" || isNaN(parseFloat(price))) {
              delete priceData[dateStr];
            } else {
              priceData[dateStr] = parseFloat(price).toFixed(2);
            }

            // Update hidden input
            updatePriceDataInput();

            // Refresh display
            calendar.removeAllEvents();
            calendar.addEventSource(generateEvents());
          }
        }
      },

      locale: listeoCal.language,
      firstDay: parseInt(listeo_core.firstDay || "1"),
    });

    // Convert YYYY-MM-DD to DD-MM-YYYY
    function formatDateForStorage(dateStr) {
      var parts = dateStr.split("-");
      return parts[2] + "-" + parts[1] + "-" + parts[0];
    }

    // Update the hidden inputs
    function updateBlockedDatesInput() {
      var today = new Date().toISOString().split("T")[0];
      // Filter out past dates from the Set
      var currentDates = Array.from(blockedDates).filter(function (d) {
        return d >= today;
      });
      blockedDates = new Set(currentDates);
      var formattedDates = currentDates.map(formatDateForStorage);
      $("#fullcalendar-blocked-dates").val(
        formattedDates.length > 0 ? formattedDates.join("|") + "|" : ""
      );
    }

    function updatePriceDataInput() {
      var formattedPrices = {};
      Object.keys(priceData).forEach(function (dateStr) {
        formattedPrices[formatDateForStorage(dateStr)] = priceData[dateStr];
      });
      $("#fullcalendar-price-data").val(JSON.stringify(formattedPrices));
    }

    // Reset properties of currently selected dates
    function clearSelection() {
      if (selectedDates.length === 0) {
        alert("Please select dates to modify first");
        return;
      }

      // Confirm action
      if (
        !confirm(
          "This will unblock selected dates and remove any custom prices. Continue?"
        )
      ) {
        return;
      }

      console.log("Clearing properties for these dates:", selectedDates);

      // Remove selected dates from blocked dates Set
      selectedDates.forEach(function (dateStr) {
        if (blockedDates.has(dateStr)) {
          console.log("Unblocking date:", dateStr);
          blockedDates.delete(dateStr);
        }
      });

      // Remove prices for selected dates
      selectedDates.forEach(function (dateStr) {
        if (dateStr in priceData) {
          console.log("Removing price for date:", dateStr);
          delete priceData[dateStr];
        }
      });

      // Update hidden inputs
      updateBlockedDatesInput();
      updatePriceDataInput();

      // Clear selection
      selectedDates = [];

      // Visually unselect any selected dates in the calendar
      calendar.unselect();

      // Refresh the calendar display
      calendar.removeAllEvents();
      calendar.addEventSource(generateEvents());

      // Hide the tooltip
      $("#selection-tooltip").remove();
      tooltipVisible = false;
    }

    // Handle Block Dates button
    $("#block-dates-btn").on("click", function (e) {
      e.preventDefault();

      if (selectedDates.length === 0) {
        alert("Please select dates to block first");
        return;
      }

      // Add selected dates to blocked dates
      selectedDates.forEach(function (dateStr) {
        blockedDates.add(dateStr);
      });

      // Update hidden input
      updateBlockedDatesInput();

      // Just clear the selection array and refresh calendar without running clearSelection()
      var tempSelection = selectedDates;
      selectedDates = [];

      // Visually unselect any selected dates in the calendar
      calendar.unselect();

      // Refresh display
      calendar.removeAllEvents();
      calendar.addEventSource(generateEvents());

      // Hide the tooltip
      $("#selection-tooltip").remove();
      tooltipVisible = false;

      //alert(tempSelection.length + " date(s) have been blocked");
    });

    // Set up price dialog handlers once, outside the click event
    // Handle price confirmation
    $("#price-confirm")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        var price = $("#price-input").val();

        if (price === "" || isNaN(parseFloat(price))) {
          alert("Please enter a valid price");
          return false;
        }

        // Format price
        price = parseFloat(price).toFixed(2);

        // Set price for all selected dates
        selectedDates.forEach(function (dateStr) {
          priceData[dateStr] = price;
        });

        // Update hidden input
        updatePriceDataInput();

        // Hide dialog
        $("#price-dialog").hide();
        $("#price-input").val("");

        // Just clear the selection array and refresh calendar without running clearSelection()
        var tempSelection = selectedDates;
        selectedDates = [];

        // Visually unselect any selected dates in the calendar
        calendar.unselect();

        // Refresh display
        calendar.removeAllEvents();
        calendar.addEventSource(generateEvents());

        // Hide the tooltip
        $("#selection-tooltip").remove();
        tooltipVisible = false;

        return false;
      });

    // Handle price cancellation
    $("#price-cancel")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $("#price-dialog").hide();
        $("#price-input").val("");
        return false;
      });

    // Handle Set Price button
    $("#set-price-btn").on("click", function (e) {
      e.preventDefault();
      e.stopPropagation(); // Stop event bubbling

      if (selectedDates.length === 0) {
        alert("Please select dates to set price for first");
        return false;
      }

      // Show price dialog
      $("#price-dialog").show();
      return false; // Ensure no form submission
    });

    // Handle Clear/Unblock Selection button
    $("#clear-selection-btn").on("click", function (e) {
      e.preventDefault();
      clearSelection();
    });

    // Update button label to be clearer
    $("#clear-selection-btn").text("Unblock/Clear Selected Dates");

    // Handle the booking status checkbox state change to refresh calendar when it becomes visible
    $('input[name="_booking_status"]').on("change", function () {
      // Check if the checkbox is checked (calendar section is visible)
      if ($(this).is(":checked")) {
        // Small delay to ensure the container is fully visible before refreshing
        setTimeout(function () {
          if (window.listeoCalendar) {
            // Trigger window resize to make FullCalendar recalculate dimensions
            window.dispatchEvent(new Event("resize"));

            // For more stubborn cases, explicitly call the calendar's render method again
            window.listeoCalendar.render();

            console.log("Calendar refreshed after becoming visible");
          }
        }, 100); // 100ms delay
      }
    });

    // Add document click handler to help manage tooltip
    $(document).on("click", function (e) {
      // Only handle clicks outside the tooltips and calendar
      if (
        tooltipVisible &&
        !$(e.target).closest(".selection-tooltip, #single-date-tooltip")
          .length &&
        !$(e.target).closest(".fc-day, .fc-daygrid-day").length
      ) {
        // Hide tooltips when clicking elsewhere
        $("#selection-tooltip, #single-date-tooltip").remove();
        $("body").removeClass("has-date-tooltip");
        tooltipVisible = false;
      }
    });

    // Render calendar
    calendar.render();

    // Expose calendar for debugging
    window.listeoCalendar = calendar;

    // if it's admin page (body has class wp-admin) try to refresh the calendar on load
    if ($("body").hasClass("wp-admin")) {
      setTimeout(function () {
        if (window.listeoCalendar) {
          window.dispatchEvent(new Event("resize"));
          window.listeoCalendar.render();
          console.log("Admin calendar refreshed on load");
        }
      }, 1000); // 1 second delay to ensure everything is loaded
    }
  }

  // ===================================================================
  // LocalStorage Form Draft System - Prevent Data Loss on Validation Errors
  // ===================================================================

  /**
   * Save form data to localStorage before submission
   * This prevents data loss when validation fails and page reloads
   */
  function saveFormDraft() {
    try {
      var listingId = $('input[name="listing_id"]').val() || 'new';
      var draftKey = 'listeo_form_draft_' + listingId;

      // Serialize all form data
      var formData = {};

      // Get all form fields (inputs, textareas, selects)
      $('#submit-listing-form :input').each(function() {
        var $field = $(this);
        var name = $field.attr('name');

        // Skip fields without names or submit buttons
        if (!name || $field.is(':submit') || $field.is(':button')) {
          return;
        }

        // Handle different field types
        if ($field.is(':checkbox')) {
          if (!formData[name]) {
            formData[name] = [];
          }
          if ($field.is(':checked')) {
            formData[name].push($field.val());
          }
        } else if ($field.is(':radio')) {
          if ($field.is(':checked')) {
            formData[name] = $field.val();
          }
        } else if ($field.is('select[multiple]')) {
          formData[name] = $field.val() || [];
        } else {
          formData[name] = $field.val();
        }
      });

      // Save to localStorage
      localStorage.setItem(draftKey, JSON.stringify(formData));
      console.log('Form draft saved to localStorage');

    } catch (e) {
      console.error('Error saving form draft:', e);
    }
  }

  /**
   * Restore form data from localStorage
   * Called when validation errors are present on page load
   */
  function restoreFormDraft() {
    try {
      var listingId = $('input[name="listing_id"]').val() || 'new';
      var draftKey = 'listeo_form_draft_' + listingId;

      // Check if draft exists
      var draftData = localStorage.getItem(draftKey);
      if (!draftData) {
        return;
      }

      var formData = JSON.parse(draftData);
      console.log('Restoring form draft from localStorage');

      // Restore each field
      $.each(formData, function(name, value) {
        var $field = $('#submit-listing-form [name="' + name + '"]');

        if ($field.length === 0) {
          return;
        }

        // Handle different field types
        if ($field.is(':checkbox')) {
          $field.prop('checked', false); // Uncheck all first
          if (Array.isArray(value)) {
            value.forEach(function(val) {
              $field.filter('[value="' + val + '"]').prop('checked', true);
            });
          }
        } else if ($field.is(':radio')) {
          $field.filter('[value="' + value + '"]').prop('checked', true);
        } else if ($field.is('select')) {
          $field.val(value);

          // Trigger change for select2 or other enhanced selects
          if ($field.hasClass('select2-hidden-accessible')) {
            $field.trigger('change.select2');
          } else {
            $field.trigger('change');
          }
        } else if ($field.is('textarea') || $field.is('input')) {
          $field.val(value);
          $field.trigger('change');
        }
      });

      console.log('Form draft restored successfully');

    } catch (e) {
      console.error('Error restoring form draft:', e);
    }
  }

  /**
   * Clear form draft from localStorage
   * Called after successful submission
   */
  function clearFormDraft() {
    try {
      var listingId = $('input[name="listing_id"]').val() || 'new';
      var draftKey = 'listeo_form_draft_' + listingId;

      localStorage.removeItem(draftKey);
      console.log('Form draft cleared from localStorage');

    } catch (e) {
      console.error('Error clearing form draft:', e);
    }
  }

  // Save form draft before submission
  $('#submit-listing-form').on('submit', function() {
    saveFormDraft();
  });

  // Restore form data if validation errors exist
  if ($('.notification.error, .listeo-notification.error, .submit-page .error').length > 0) {
    // Validation errors detected - restore the form draft
    restoreFormDraft();
  }

  // Clear draft on successful submission (when on the "done" or success page)
  if ($('.listing-submitted, .submit-done, body.step-done').length > 0) {
    clearFormDraft();
  }

  // Also clear draft if user explicitly clicks "new listing" or similar
  $('a[href*="?new"], a[href*="&new"]').on('click', function() {
    clearFormDraft();
  });

  // Remove error styling when user selects a value in Select2 fields
  $(document).on('change', 'select[required].select2-hidden-accessible', function() {
    var $select = $(this);
    var value = $select.val();
    var $container = $select.next('.select2-container');

    if (value && value !== '-1' && value !== '') {
      $select.removeClass('msf-input-error');
      if ($container.length) {
        $container.removeClass('msf-input-error');
      }
    }
  });

  // Remove error styling when user types in required input/textarea fields
  $(document).on('input', 'input.msf-input-error, textarea.msf-input-error', function() {
    if ($.trim($(this).val())) {
      $(this).removeClass('msf-input-error');
    }
  });

  // Remove error styling when user types in TinyMCE editors
  $(document).on('tinymce-editor-init', function(event, editor) {
    editor.on('input keyup', function() {
      var content = editor.getContent({ format: 'text' });
      if ($.trim(content)) {
        $('#wp-' + editor.id + '-wrap').removeClass('msf-input-error');
      }
    });
  });

});
