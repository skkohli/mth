document.addEventListener("DOMContentLoaded", function () {

  function showLoader() {
    const loader = document.createElement("div");
    loader.className = "msf-loader-overlay";

    loader.innerHTML = `
    <svg class="msf-loader-spinner" viewBox="0 0 50 50">
      <circle cx="25" cy="25" r="20"></circle>
    </svg>
  `;

    document.body.appendChild(loader);
  }



  const form = document.querySelector(".listing-manager-form");
  if (!form) return;

  const allSections = Array.from(form.querySelectorAll(".add-listing-section"));

const submitPageDiv = document.querySelector(".submit-page");

if (!submitPageDiv || !submitPageDiv.classList.contains("multi-step-form")) {
  return; // or throw new Error('Required class not found');
}

  
  const submitButton = form.querySelector('button[name="submit_listing"]');

  if (!submitButton || allSections.length <= 1) {
    return;
  }

  // --- Start of Your Custom Configuration ---
  let stepConfiguration = [];

  try {
    const rawStepsEncoded = document.getElementById(
      "listeo_form_steps_json"
    )?.value;

    if (rawStepsEncoded) {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = rawStepsEncoded;
      const rawSteps = textarea.value;

      stepConfiguration = JSON.parse(rawSteps);
    }
  } catch (e) {
    console.error("Failed to parse step configuration", e);
  }

  // if stepConfiguration is empty or not an array, disable multi-step
  if (!Array.isArray(stepConfiguration) || stepConfiguration.length === 0) {
    return;
  }

  allSections.forEach((section) => {
    section.removeAttribute("style");
  });
  // const stepConfiguration = [
  //   {
  //     title: "Listing Essentials",
  //     selectors: [".basic_info", ".location", ".gallery", ".details"],
  //   },
  //   {
  //     title: "Offerings & Schedule",
  //     selectors: [
  //       ".opening_hours",
  //       ".menu",
  //       ".booking",
  //       ".slots",
  //       ".basic_prices",
  //       ".availability_calendar",
  //     ],
  //   },
  //   {
  //     title: "Final Details",
  //     selectors: [".faq", ".my_listings_section"],
  //   },
  // ];
  // --- End of Your Custom Configuration ---

  let currentStep = 0;
  let highestStepReached = 0;
  const steps = [];

  // Reworked Step Creation Logic
  stepConfiguration.forEach((groupInfo) => {
    const groupSections = [];
    groupInfo.selectors.forEach((selector) => {
      const foundSections = form.querySelectorAll(selector);
      foundSections.forEach((section) => {
        // Ensure we only add sections that actually exist in the form
        if (allSections.includes(section)) {
          groupSections.push(section);
        }
      });
    });
    
    // Also check for custom fields sections using both ID and class selectors
    groupInfo.selectors.forEach((selector) => {
      if (selector.startsWith('#custom-fields-') || selector === '.custom-term-features') {
        // For ID selectors, look for specific custom section
        if (selector.startsWith('#custom-fields-')) {
          const customSection = document.querySelector(selector);
          if (customSection && !groupSections.includes(customSection)) {
            groupSections.push(customSection);
          }
        } 
        // For class selector, get all custom sections
        else if (selector === '.custom-term-features') {
          const customSections = document.querySelectorAll(selector);
          customSections.forEach((customSection) => {
            if (!groupSections.includes(customSection)) {
              groupSections.push(customSection);
            }
          });
        }
      }
    });
    
    if (groupSections.length > 0) {
      steps.push(groupSections);
    }
  });

  const progressContainer = document.createElement("div");
  progressContainer.className = "form-progress-container";
  const navContainer = document.createElement("div");
  navContainer.className = "form-navigation";
  const prevButton = document.createElement("button");
  prevButton.type = "button";
  prevButton.className = "btn-prev button";
  prevButton.textContent =  listeo_core.prev;
  const nextButton = document.createElement("button");
  nextButton.type = "button";
  nextButton.className = "btn-next button";
  nextButton.textContent =  listeo_core.next;
  form.insertBefore(progressContainer, form.firstChild);
  const lastSection = allSections[allSections.length - 1];
  lastSection.parentNode.insertBefore(navContainer, lastSection.nextSibling);
  navContainer.appendChild(prevButton);
  navContainer.appendChild(nextButton);
  navContainer.appendChild(submitButton);
  if (submitButton.parentElement.nodeName === "P") {
    submitButton.parentElement.style.display = "block";
    submitButton.parentElement.style.margin = "0";
    submitButton.parentElement.classList.add("form-navigation-submit");
  }

  // Reworked Progress Bar Creation
  steps.forEach((stepSections, index) => {
    const stepElement = document.createElement("div");
    stepElement.className = "form-progress-step";
    const icon = document.createElement("div");
    icon.className = "progress-step-icon";
    const titleElement = document.createElement("div");
    titleElement.className = "progress-step-title";

    // Use the title from the new configuration
    titleElement.textContent =
      stepConfiguration[index].title || `Step ${index + 1}`;

    stepElement.appendChild(icon);
    stepElement.appendChild(titleElement);
    progressContainer.appendChild(stepElement);
  });
  const progressSteps = progressContainer.querySelectorAll(
    ".form-progress-step"
  );

  function smoothScrollToTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function isFieldRequired(label) {
    if (!label) return false;
    const iTags = label.querySelectorAll("i");
    for (const iTag of iTags) {
      if (iTag.textContent.trim() === "*") return true;
    }
    return false;
  }

  function validateStep(stepIndex) {
    let isStepValid = true;
    const sectionsToValidate = steps[stepIndex];
    for (const section of sectionsToValidate) {
      const standardFields = section.querySelectorAll(
        'input[required]:not([type="hidden"]), select[required], textarea[required]'
      );
      for (const field of standardFields) {
        // Special handling for Select2 fields
        if (field.tagName === 'SELECT' && field.classList.contains('select2-hidden-accessible')) {
          const isValid = field.value && field.value !== '-1' && field.value !== '';
          const select2Container = field.nextElementSibling;

          if (!isValid) {
            isStepValid = false;
            field.classList.add("msf-input-error");
            if (select2Container && select2Container.classList.contains('select2-container')) {
              select2Container.classList.add("msf-input-error");
            }
          } else {
            field.classList.remove("msf-input-error");
            if (select2Container && select2Container.classList.contains('select2-container')) {
              select2Container.classList.remove("msf-input-error");
            }
          }
        } else {
          // Standard HTML5 validation for non-Select2 fields
          if (!field.checkValidity()) {
            isStepValid = false;
            field.classList.add("msf-input-error");
          } else {
            field.classList.remove("msf-input-error");
          }
        }
      }
      const drilldownMenus = section.querySelectorAll("div.drilldown-menu");
      for (const menu of drilldownMenus) {
        const name = menu.getAttribute("data-name");
        if (!name) continue;
        const label = section.querySelector(
          `label[for="${name}"], .label-${name}`
        );
        if (!isFieldRequired(label)) continue;
        const hasValue =
          menu.querySelector("input.drilldown-generated") !== null;
        const menuToggle = menu.querySelector(".menu-toggle");
        if (menuToggle) {
          if (!hasValue) {
            isStepValid = false;
            menuToggle.classList.add("msf-input-error");
          } else {
            menuToggle.classList.remove("msf-input-error");
          }
        }
      }
      const galleryUploader = section.querySelector("#media-uploader");
      if (
        galleryUploader &&
        galleryUploader.getAttribute("data-required") === "required"
      ) {
        const hasImages =
          galleryUploader.querySelector(".dz-image-preview") !== null;
        const existingError = galleryUploader.nextElementSibling;
        if (!hasImages) {
          isStepValid = false;
          if (
            !existingError ||
            !existingError.classList.contains("msf-gallery-error-message")
          ) {
            const errorDiv = document.querySelector(
              ".msf-gallery-error-message"
            );
            if (errorDiv) {
              errorDiv.style.display = "block";
            }
          }
        } else {
          if (
            existingError &&
            existingError.classList.contains("msf-gallery-error-message")
          ) {
            existingError.remove();
          }
        }
      }
      if (typeof tinymce !== "undefined") {
        const textareas = section.querySelectorAll("textarea");
        for (const textarea of textareas) {
          const editorId = textarea.id;
          if (!editorId) continue;
          const editor = tinymce.get(editorId);
          if (!editor) continue;
          const label = section.querySelector(`label[for="${editorId}"]`);
          if (!isFieldRequired(label)) continue;
          const content = editor.getContent({ format: "text" }).trim();
          const editorContainer = editor.getContainer();
          if (content === "") {
            isStepValid = false;
            if (editorContainer)
              editorContainer.classList.add("msf-input-error");
          } else {
            if (editorContainer)
              editorContainer.classList.remove("msf-input-error");
          }
        }
      }
      const fileInputs = section.querySelectorAll('input[type="file"]');
      for (const fileInput of fileInputs) {
        const inputId = fileInput.id;
        if (!inputId) continue;
        const label = section.querySelector(`label[for="${inputId}"]`);
        if (!isFieldRequired(label)) continue;
        const fieldContainer = fileInput.closest(
          ".form-field-container-type-file"
        );
        const uploadWrapper = fileInput.closest(".uploadButton");
        if (uploadWrapper && fieldContainer) {
          const uploadButtonLabel = uploadWrapper.querySelector(
            ".uploadButton-button"
          );
          const hasExistingPreview =
            fieldContainer.querySelector(".listeo-uploaded-file-preview") !==
            null;
          const hasNewFile = fileInput.files.length > 0;
          const isFieldValid = hasNewFile || hasExistingPreview;
          if (uploadButtonLabel) {
            if (!isFieldValid) {
              isStepValid = false;
              uploadButtonLabel.classList.add("msf-input-error");
            } else {
              uploadButtonLabel.classList.remove("msf-input-error");
            }
          }
        }
      }
    }
    return isStepValid;
  }

  function updateStepStatus(stepIndex) {
    const isStepValid = validateStep(stepIndex);
    const stepElement = progressSteps[stepIndex];
    if (isStepValid) {
      stepElement.classList.remove("step-warning");
    } else {
      stepElement.classList.add("step-warning");
    }
  }

  // Unified visibility controller
  function shouldSectionBeVisible(section) {
    // Check if section belongs to current step
    const isInCurrentStep = steps[currentStep] && steps[currentStep].includes(section);
    
    // Check if section is booking-dependent
    const isBookingDependent = section.classList.contains('availability_calendar') ||
                              section.classList.contains('slots') ||
                              section.classList.contains('basic_prices');
    
    // Check if booking is enabled
    const bookingInput = form.querySelector(
      '.booking input[name*="booking_status"], .booking select[name*="booking_status"]'
    );
    let isBookingEnabled = false;
    
    if (bookingInput) {
      if (bookingInput.type === "checkbox" || bookingInput.type === "radio") {
        isBookingEnabled = bookingInput.checked || bookingInput.value === "enabled";
      } else if (bookingInput.tagName === "SELECT") {
        isBookingEnabled = bookingInput.value === "enabled";
      }
    }

    // Apply unified visibility logic
    if (isBookingDependent) {
      return isInCurrentStep && isBookingEnabled;
    }
    return isInCurrentStep;
  }

  function updateBookingDependencies() {
    const bookingInput = form.querySelector(
      '.booking input[name*="booking_status"], .booking select[name*="booking_status"]'
    );
    if (!bookingInput) return;

    let isEnabled = false;

    if (bookingInput.type === "checkbox" || bookingInput.type === "radio") {
      isEnabled = bookingInput.checked || bookingInput.value === "enabled";
    } else if (bookingInput.tagName === "SELECT") {
      isEnabled = bookingInput.value === "enabled";
    }

    // Update the booking switcher visual state
    const bookingSection = form.querySelector('.booking');
    if (bookingSection) {
      if (isEnabled) {
        bookingSection.classList.add("switcher-on");
      } else {
        bookingSection.classList.remove("switcher-on");
      }
    }

    // Trigger form UI update to apply unified visibility logic
    updateFormUI();
  }

  // Set up initial state and watch for changes
  updateBookingDependencies();
  const bookingInput = form.querySelector(
    '.booking input[name*="booking_status"]'
  );
  if (bookingInput) {
    bookingInput.addEventListener("change", updateBookingDependencies);
  }

  // Modify the updateFormUI function to use unified visibility logic
  function updateFormUI() {
    if (typeof window.listeoCurrentStep !== "undefined") {
      window.listeoCurrentStep = currentStep;
    }

    // Hide all sections first
    allSections.forEach((section) => {
      section.classList.remove("active");
      section.style.display = "none";
    });

    // Hide all custom fields sections
    const customTermFeatures = document.querySelectorAll(".custom-term-features");
    customTermFeatures.forEach(function (element) {
      element.classList.remove("active");
      element.style.display = "none";
    });

    // Clean up empty custom sections before showing the current step
    const emptyCustomSections = document.querySelectorAll(".custom-term-features");
    emptyCustomSections.forEach(function (element) {
      const content = element.querySelector('.custom-term-features-content');
      if (content && content.children.length === 0) {
        console.log("Removing empty custom section from updateFormUI:", element.id);
        element.remove();
      }
    });

    if (steps[currentStep]) {
      const activeSections = steps[currentStep];
      let needsResize = false;

      // Show sections using unified visibility logic
      activeSections.forEach((section) => {
        // Check if the section still exists in the DOM (might have been removed as empty)
        if (!document.contains(section)) {
          return;
        }

        // Use unified visibility controller
        if (shouldSectionBeVisible(section)) {
          section.classList.add("active");
          section.style.display = "";

          if (
            section.querySelector("#submit_map") ||
            section.querySelector(".fullCalendar, .fc")
          ) {
            needsResize = true;
          }
        }
      });

      if (needsResize) {
        setTimeout(() => {
          window.dispatchEvent(new Event("resize"));
        }, 50);
      }
    }

    // FIXED: Check custom sections based on current step configuration
    const customSections = document.querySelectorAll(".custom-term-features");
    customSections.forEach(function (element) {
      var sectionId = element.id;
      var shouldBeVisible = false;

      // Check if this section's selector is in current step configuration
      if (stepConfiguration[currentStep] && stepConfiguration[currentStep].selectors) {
        // Check for both ID selector and class selector
        var idSelector = "#" + sectionId;
        var classSelector = ".custom-term-features";
        
        shouldBeVisible = 
          stepConfiguration[currentStep].selectors.indexOf(idSelector) !== -1 ||
          stepConfiguration[currentStep].selectors.indexOf(classSelector) !== -1;
      }

      // Only show sections that have content
      const content = element.querySelector('.custom-term-features-content');
      const hasContent = content && content.children.length > 0;

      if (shouldBeVisible && hasContent) {
        element.classList.add("active");
        element.style.display = "";
        console.log("Showing custom section:", sectionId, "in step:", currentStep);
      } else {
        console.log("Hiding custom section:", sectionId, "in step:", currentStep, "hasContent:", hasContent);
      }
    });

    progressSteps.forEach((step, index) => {
      const icon = step.querySelector(".progress-step-icon");
      step.classList.remove("active", "completed");
      if (
        index <= highestStepReached &&
        !step.classList.contains("step-warning")
      ) {
        step.classList.add("completed");
      }
      if (index === currentStep) {
        step.classList.add("active");
        step.classList.remove("completed");
      }
      if (
        step.classList.contains("completed") &&
        !step.classList.contains("active")
      ) {
        icon.textContent = "";
      } else {
        icon.textContent = index + 1;
      }
    });
    prevButton.style.display = currentStep === 0 ? "none" : "inline-block";
    nextButton.style.display =
      currentStep === steps.length - 1 ? "none" : "inline-block";
    submitButton.style.display =
      currentStep === steps.length - 1 ? "inline-block" : "none";
}

// Add this function after the step creation logic (around line 120)
// Add a function to refresh steps when custom sections are added
window.refreshStepsConfiguration = function() {
  // Get the updated step configuration from the hidden input
  const stepsInput = document.getElementById('listeo_form_steps_json');
  if (!stepsInput) return;
  
  try {
    const textarea = document.createElement("textarea");
    textarea.innerHTML = stepsInput.value;
    const updatedStepConfiguration = JSON.parse(textarea.value);
    
    // Rebuild the steps array
    steps.length = 0; // Clear existing steps
    
    updatedStepConfiguration.forEach((groupInfo) => {
      const groupSections = [];
      groupInfo.selectors.forEach((selector) => {
        // Use querySelectorAll on the entire document for custom sections
        const foundSections = document.querySelectorAll(selector);
        foundSections.forEach((section) => {
          // Check if it's in allSections or if it's a custom section
          if (allSections.includes(section) || section.classList.contains('custom-term-features')) {
            groupSections.push(section);
          }
        });
      });
      
      if (groupSections.length > 0) {
        steps.push(groupSections);
      }
    });
    
    // Update the step configuration reference
    stepConfiguration = updatedStepConfiguration;
    
    console.log('Steps configuration refreshed:', steps);
    
    // DON'T force update the UI here - let it happen naturally
    // This was causing the section to be removed before content was added
    // updateFormUI();
    
  } catch (e) {
    console.error('Error refreshing step configuration:', e);
  }
};

// Also add debugging to the step navigation to see what's happening
nextButton.addEventListener("click", () => {
  updateStepStatus(currentStep);
  if (currentStep < steps.length - 1) {
    currentStep++;
    highestStepReached = Math.max(highestStepReached, currentStep);
    console.log("Moving to step:", currentStep, "with sections:", steps[currentStep]);
    updateFormUI();
    smoothScrollToTop();
  }
});

prevButton.addEventListener("click", () => {
  updateStepStatus(currentStep);
  if (currentStep > 0) {
    currentStep--;
    console.log("Moving to step:", currentStep, "with sections:", steps[currentStep]);
    updateFormUI();
    smoothScrollToTop();
  }
});

progressSteps.forEach((step, index) => {
  step.addEventListener("click", () => {
    if (index === currentStep) return;
    if (index > currentStep) {
      for (let i = currentStep; i < index; i++) {
        updateStepStatus(i);
      }
    } else {
      updateStepStatus(currentStep);
    }
    currentStep = index;
    highestStepReached = Math.max(highestStepReached, currentStep);
    console.log("Clicking to step:", currentStep, "with sections:", steps[currentStep]);
    updateFormUI();
    smoothScrollToTop();
  });
});
  submitButton.addEventListener("click", (e) => {
    const isPreview =
      e.target.name === "submit_listing" ||
      e.target.value.toLowerCase().includes("preview") ||
      e.target.textContent.toLowerCase().includes("preview");

    // Always validate steps
    for (let i = 0; i < steps.length; i++) {
      updateStepStatus(i);
    }

    let firstInvalidStep = -1;
    progressSteps.forEach((step, index) => {
      if (step.classList.contains("step-warning") && firstInvalidStep === -1) {
        firstInvalidStep = index;
      }
    });

    if (firstInvalidStep !== -1) {
      e.preventDefault(); // prevent even preview from submitting
      currentStep = firstInvalidStep;
      highestStepReached = Math.max(highestStepReached, currentStep);
      updateFormUI();
      smoothScrollToTop();

      const firstInvalidField = steps[firstInvalidStep][0].querySelector(
        ".msf-input-error, .msf-gallery-error-message"
      );
      if (firstInvalidField) firstInvalidField.focus({ preventScroll: true });
      // alert(
      //   "Please correct the errors on the highlighted step before continuing."
      // );
      return;
    }

    // If valid
    if (isPreview) {
       showLoader();
       allSections.forEach((section) => {
         section.classList.add("active", "msf-preview-hidden");
       });

       progressContainer.style.display = "none";
       navContainer.style.display = "none";
        
      console.log("Preview button clicked - showing all sections");
      return;
    }

    e.preventDefault();
    console.log("Form is valid. Submitting...");
    form.submit();
  });


  const previewButton = form.querySelector(
    'button[name="preview_listing"], input[name="preview_listing"]'
  );
  if (previewButton) {
    previewButton.addEventListener("click", (e) => {
      // Don't prevent default - let preview work normally

      // Temporarily show all sections for preview
      allSections.forEach((section) => {
        section.classList.add("active");
        section.style.display = "block";
      });

      // Let the preview submission proceed without validation
      console.log("Preview button clicked - showing all sections");
    });
  }

  // Alternative: If preview button is identified differently, try this selector
  const previewButtons = form.querySelectorAll(
    'button[value*="preview"], input[value*="preview"], [name*="preview"]'
  );
  previewButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      allSections.forEach((section) => {
        section.classList.add("active");
        section.style.display = "block";
      });
      console.log("Preview button clicked - showing all sections");
    });
  });

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("action") === "edit") {
    console.log("Edit mode detected. All steps will be marked as completed.");
    highestStepReached = steps.length - 1;
  }
  updateBookingDependencies();
  updateFormUI();

  // Remove error styling when user selects a value in Select2 fields
  document.addEventListener('change', function(e) {
    const target = e.target;

    // Check if the changed element is a required select with Select2
    if (target.tagName === 'SELECT' &&
        target.hasAttribute('required') &&
        target.classList.contains('select2-hidden-accessible')) {

      const value = target.value;
      const container = target.nextElementSibling;

      // If a valid value is selected, remove error styling
      if (value && value !== '-1' && value !== '') {
        target.classList.remove('msf-input-error');
        if (container && container.classList.contains('select2-container')) {
          container.classList.remove('msf-input-error');
        }
      }
    }
  });

  // Expose updateFormUI globally for coordination with frontend.js
  window.listeoUpdateFormUI = updateFormUI;
});
