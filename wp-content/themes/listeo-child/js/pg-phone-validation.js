(function () {
  "use strict";

  const FORM_SELECTOR = "#submit-listing-form";

  const FIELD_SELECTORS = {
    website: 'input[name="_website"], input#_website',
    email: 'input[name="_email"], input#_email',
    whatsapp: 'input[name="_whatsapp"], input#_whatsapp',
    phone: 'input[name="_phone"], input#_phone'
  };

  function cleanPhone(value) {
    return value.replace(/[\s\-()]/g, "");
  }

  function isValidIndianMobile(value) {
    return /^(?:\+91|91|0)?[6-9]\d{9}$/.test(cleanPhone(value));
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim());
  }

  function isValidUrl(value) {
    try {
      const url = new URL(value.trim());
      return (
        (url.protocol === "http:" || url.protocol === "https:") &&
        url.hostname.includes(".")
      );
    } catch (e) {
      return false;
    }
  }

  function validateField(input) {
    const name = input.name;
    const value = input.value.trim();

    if (name === "_website") {
      if (!value) return input.setCustomValidity(""), true;
      if (!isValidUrl(value)) {
        input.setCustomValidity("Enter a valid website URL starting with http:// or https://");
        return false;
      }
    }

    if (name === "_email") {
      if (!value || !isValidEmail(value)) {
        input.setCustomValidity("Enter a valid email address.");
        return false;
      }
    }

    if (name === "_phone") {
      if (!value || !isValidIndianMobile(value)) {
        input.setCustomValidity("Enter a valid 10 digit Indian mobile number.");
        return false;
      }
    }

    if (name === "_whatsapp") {
      if (!value) return input.setCustomValidity(""), true;
      if (!isValidIndianMobile(value)) {
        input.setCustomValidity("Enter a valid WhatsApp mobile number.");
        return false;
      }
    }

    input.setCustomValidity("");
    return true;
  }

  function enhanceField(input) {
    if (!input || input.dataset.contactValidation === "1") return;

    input.dataset.contactValidation = "1";

    if (input.name === "_website") {
      input.type = "url";
      input.placeholder = "https://example.com";
      input.autocomplete = "url";
    }

    if (input.name === "_email") {
      input.type = "email";
      input.placeholder = "name@example.com";
      input.autocomplete = "email";
    }

    if (input.name === "_phone" || input.name === "_whatsapp") {
      input.type = "tel";
      input.inputMode = "numeric";
      input.maxLength = 20;
      input.autocomplete = "tel";
      input.placeholder = "10 digit mobile number";

      input.addEventListener("input", function () {
        input.value = cleanPhone(input.value);
        validateField(input);
      });
    } else {
      input.addEventListener("input", function () {
        validateField(input);
      });
    }

    input.addEventListener("blur", function () {
      validateField(input);
    });
  }

  function initContactValidation() {
    Object.values(FIELD_SELECTORS).forEach(function (selector) {
      document.querySelectorAll(selector).forEach(enhanceField);
    });

    const form = document.querySelector(FORM_SELECTOR);
    if (!form || form.dataset.contactSubmitValidation === "1") return;

    form.dataset.contactSubmitValidation = "1";

    form.addEventListener("submit", function (event) {
      const fields = Object.values(FIELD_SELECTORS)
        .map(selector => form.querySelector(selector))
        .filter(Boolean);

      const invalidField = fields.find(field => !validateField(field));

      if (invalidField) {
        event.preventDefault();
        event.stopImmediatePropagation();
        invalidField.reportValidity();
        invalidField.focus();
      }
    }, true);
  }

  document.addEventListener("DOMContentLoaded", initContactValidation);

  new MutationObserver(initContactValidation).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
})();