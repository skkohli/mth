// WordPress Chat Content Filter - Blocks emails, phones, and contact info
(function () {
  "use strict";

  // Patterns to detect contact information
  const patterns = {
    // Email patterns (various formats)
    email: [
      /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/g,
      /\b[A-Za-z0-9._%+-]+\s*@\s*[A-Za-z0-9.-]+\s*\.\s*[A-Z|a-z]{2,}\b/g,
      /\b[A-Za-z0-9._%+-]+\s*\[\s*at\s*\]\s*[A-Za-z0-9.-]+\s*\[\s*dot\s*\]\s*[A-Z|a-z]{2,}\b/gi,
      /\b[A-Za-z0-9._%+-]+\s*(at|AT)\s*[A-Za-z0-9.-]+\s*(dot|DOT)\s*[A-Z|a-z]{2,}\b/g,
    ],

    // Phone number patterns (international formats)
    phone: [
      /(\+?\d{1,4}[\s\-\(\)]?)?\(?\d{3,4}\)?[\s\-\.]?\d{3,4}[\s\-\.]?\d{3,6}/g,
      /(\+?\d{1,4}[\s\-]?)?\d{3,4}[\s\-\.]\d{3,4}[\s\-\.]\d{3,6}/g,
      /\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/g,
      /\b\d{10,15}\b/g,
    ],

    // Social media handles and usernames
    social: [
      /@[A-Za-z0-9_]{3,}/g,
      /\b(instagram|insta|ig|facebook|fb|twitter|telegram|whatsapp|snapchat|tiktok|discord)\s*[:=]?\s*[A-Za-z0-9_.]{3,}/gi,
    ],

    // Website URLs and spaced domains
    urls: [
      /https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)/g,
      /www\.[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)/g,
      /\b[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})*\.([a-zA-Z]{2,6})\b/g,
      // Spaced out domains (like "google com", "facebook com", etc.) - more specific
      /\b[a-zA-Z]{3,20}\s+(com|net|org|edu|gov|mil|info|biz)\b/gi,
      // Spaced with dot (like "facebook . com")
      /\b[a-zA-Z]{3,20}\s*\.\s*(com|net|org|edu|gov|mil|info|biz)\b/gi,
    ],

    // Skype, WhatsApp numbers, etc.
    messaging: [
      /\b(skype|whatsapp|telegram|signal)\s*[:=]?\s*[A-Za-z0-9_.+\-]{3,}/gi,
    ],
  };

  // Replacement characters for censoring
  const censorChar = "*";

  // Warning message
  const warningMessage =
    "Contact information is not allowed. Please keep all conversations on the platform.";

  // Function to detect and censor contact information
  function detectAndCensorContent(text) {
    let violations = [];
    let censoredText = text;

    // Check each pattern category
    Object.keys(patterns).forEach((category) => {
      patterns[category].forEach((pattern) => {
        if (pattern.test(text)) {
          violations.push(category);
          // Replace matches with asterisks
          censoredText = censoredText.replace(pattern, (match) => {
            return censorChar.repeat(Math.max(3, match.length));
          });
        }
      });
    });

    return {
      hasViolations: violations.length > 0,
      violations: [...new Set(violations)], // Remove duplicates
      censoredText: censoredText,
      originalText: text,
    };
  }

  // Function to show warning message
  function showWarning() {
    // Create or update warning element
    let warningEl = document.getElementById("chat-content-warning");
    if (!warningEl) {
      warningEl = document.createElement("div");
      warningEl.id = "chat-content-warning";
      warningEl.style.cssText = `
                background: #ff6b6b;
                color: white;
                padding: 10px;
                margin: 10px 0;
                border-radius: 5px;
                font-size: 14px;
                display: none;
            `;

      // Insert before the form
      const form = document.querySelector("#send-message-from-chat");
      if (form && form.parentNode) {
        form.parentNode.insertBefore(warningEl, form);
      }
    }

    warningEl.textContent = warningMessage;
    warningEl.style.display = "block";

    // Hide warning after 5 seconds
    setTimeout(() => {
      warningEl.style.display = "none";
    }, 5000);
  }

  // Function to handle form submission
  function handleFormSubmission(e) {
    const textarea = document.querySelector("#contact-message");
    if (!textarea) return;

    const messageText = textarea.value.trim();
    if (!messageText) return;

    const result = detectAndCensorContent(messageText);

    if (result.hasViolations) {
      e.preventDefault();
      e.stopPropagation();

      // Show warning
      showWarning();

      // Optional: Replace textarea content with censored version
      // textarea.value = result.censoredText;

      return false;
    }

    return true;
  }

  // Function to handle real-time input filtering
  function handleRealTimeInput(e) {
    const textarea = e.target;
    const messageText = textarea.value;

    if (!messageText) return;

    const result = detectAndCensorContent(messageText);

    if (result.hasViolations) {
      // Option 1: Prevent typing by reverting to previous value
      // textarea.value = textarea.dataset.lastValid || '';

      // Option 2: Replace with censored version in real-time
      textarea.value = result.censoredText;

      // Show visual indicator
      textarea.style.borderColor = "#ff6b6b";
      textarea.style.backgroundColor = "#fff5f5";

      // Show warning
      showWarning();
    } else {
      // Store valid content
      textarea.dataset.lastValid = messageText;

      // Reset visual indicators
      textarea.style.borderColor = "";
      textarea.style.backgroundColor = "";
    }
  }

  // Function to filter existing messages (if you want to censor already posted content)
  function filterExistingMessages() {
    // Single conversation view: full message bubbles
    const messages = document.querySelectorAll(".message-text");
    messages.forEach((messageEl) => {
      // Skip messages that contain attachments to preserve attachment links
      if (messageEl.querySelector('.message-attachment')) {
        return;
      }

      const originalText = messageEl.textContent;
      const result = detectAndCensorContent(originalText);

      if (result.hasViolations) {
        messageEl.textContent = result.censoredText;
        messageEl.title =
          "This message contained contact information and was filtered";
      }
    });

    // Inbox list view: last message previews
    const previews = document.querySelectorAll(".message-by p");
    previews.forEach((previewEl) => {
      const originalText = previewEl.textContent;
      const result = detectAndCensorContent(originalText);

      if (result.hasViolations) {
        // Preserve the reply/forward icon if present
        const icon = previewEl.querySelector("i");
        previewEl.textContent = result.censoredText;
        if (icon) {
          previewEl.prepend(icon);
          previewEl.insertBefore(document.createTextNode(" "), icon.nextSibling);
        }
      }
    });
  }

  // Initialize the content filter
  function initContentFilter() {
    // Prevent form submission with violations
    const form = document.querySelector("#send-message-from-chat");
    const sendButton = document.querySelector("#send-message-from-chat button");

    if (form) {
      form.addEventListener("submit", handleFormSubmission);
    }

    if (sendButton) {
      sendButton.addEventListener("click", handleFormSubmission);
    }

    // Add real-time input filtering
    const textarea = document.querySelector("#contact-message");
    if (textarea) {
      // Option 1: Filter on input (real-time)
      textarea.addEventListener("input", handleRealTimeInput);

      // Option 2: Filter on blur (when user leaves field)
      // textarea.addEventListener('blur', handleRealTimeInput);

      // Store initial valid state
      textarea.dataset.lastValid = textarea.value || "";
    }

    // Filter existing messages on page load
    filterExistingMessages();

    // Re-filter new messages that appear (for AJAX-loaded content)
    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.addedNodes.length) {
          filterExistingMessages();
        }
      });
    });

    // Observe the chat container for new messages
    const chatContainer = form
      ? form.closest(".chat-container") || document.body
      : document.querySelector(".messages-inbox") || document.body;
    observer.observe(chatContainer, {
      childList: true,
      subtree: true,
    });
  }

  // Start when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initContentFilter);
  } else {
    initContentFilter();
  }

  // Also initialize on window load as backup
  window.addEventListener("load", initContentFilter);
})();
