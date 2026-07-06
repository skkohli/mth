/**
 * Chat UI Utilities
 *
 * DOM synchronization and layout helpers for chat widget.
 *
 * @package AI_Chat_Search
 * @since 1.2.0
 */

(function ($) {
  "use strict";

  // Prevent double initialization
  if (window._aiChatUiInit) return;
  window._aiChatUiInit = true;

  /**
   * Debug logging helper - only logs when debug mode is enabled
   */
  const debugLog = function (...args) {
    if (
      typeof listeoAiChatConfig !== "undefined" &&
      listeoAiChatConfig.debugMode
    ) {
      console.log("[AI Chat]", ...args);
    }
  };

  // Generate random string for class obfuscation
  function _genRnd(len) {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < len; i++) {
      result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
  }

  // Session-unique class suffix (regenerated on each page load)
  const _bSfx = _genRnd(6);

  // Expected values (obfuscated)
  const _0x = {
    d: atob("cHVyZXRoZW1lcy5uZXQ="),
    n: atob("UHVyZXRoZW1lcw=="),
    p: atob("cG93ZXJlZCBieQ=="),
    u: atob("aHR0cHM6Ly9wdXJldGhlbWVzLm5ldC9haS1jaGF0Ym90LWZvci13b3JkcHJlc3MvP3V0bV9zb3VyY2U9Y2hhdGJvdC13aWRnZXQmdXRtX21lZGl1bT1wb3dlcmVkLWJ5JnV0bV9jYW1wYWlnbj1icmFuZGluZw=="),
  };

  // Critical inline styles
  const _criticalStyles = {
    display: 'block',
    visibility: 'visible',
    opacity: '1',
    position: 'relative',
    transform: 'none',
    width: 'auto',
    height: 'auto',
    overflow: 'visible',
    clip: 'auto',
    clipPath: 'none',
    fontSize: '12px',
    lineHeight: '27px',
    color: '#888',
    textAlign: 'center',
    pointerEvents: 'auto',
    zIndex: 'auto',
    filter: 'none',
    margin: '-10px 0 10px 0',
    padding: '0'
  };

  function _applyBadgeStyles(badge, uniqueClass) {
    badge.classList.add(uniqueClass);
    badge.classList.add('listeo-ai-chat-powered-by');

    Object.keys(_criticalStyles).forEach(function(prop) {
      badge.style.setProperty(prop, _criticalStyles[prop], 'important');
    });

    const link = badge.querySelector('a');
    if (link) {
      link.style.setProperty('color', 'var(--ai-chat-primary-color, #0073ee)', 'important');
      link.style.setProperty('text-decoration', 'none', 'important');
      link.style.setProperty('font-weight', '600', 'important');
      link.style.setProperty('pointer-events', 'auto', 'important');
      link.style.setProperty('display', 'inline', 'important');
      link.style.setProperty('visibility', 'visible', 'important');
      link.style.setProperty('opacity', '1', 'important');
    }
  }

  function _createBadge(uniqueClass) {
    const badge = document.createElement('div');
    badge.className = 'listeo-ai-chat-powered-by ' + uniqueClass;
    badge.setAttribute('data-required', 'true');
    badge.setAttribute('data-v', _bSfx);

    badge.appendChild(document.createTextNode('Powered by '));

    const link = document.createElement('a');
    link.href = atob(_0x.u);
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = _0x.n;
    badge.appendChild(link);

    _applyBadgeStyles(badge, uniqueClass);

    return badge;
  }

  function _injectDynamicCSS(uniqueClass) {
    if (document.getElementById('_pb_' + _bSfx)) return;

    const style = document.createElement('style');
    style.id = '_pb_' + _bSfx;
    style.textContent = `
      .${uniqueClass} {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: relative !important;
        transform: none !important;
        height: auto !important;
        width: auto !important;
        overflow: visible !important;
        pointer-events: auto !important;
        font-size: 12px !important;
        color: #888 !important;
        text-align: center !important;
        clip: auto !important;
        clip-path: none !important;
        filter: none !important;
      }
      .${uniqueClass} a {
        color: var(--ai-chat-primary-color, #0073ee) !important;
        display: inline !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
        font-weight: 600 !important;
      }
    `;
    document.head.appendChild(style);
  }

  function initLayoutSync() {
    const uniqueClass = '_pb_' + _bSfx;

    _injectDynamicCSS(uniqueClass);

    const chatWrappers = document.querySelectorAll('.listeo-ai-chat-wrapper');

    if (chatWrappers.length === 0) {
      debugLog("[UI Utils] No chat wrappers found");
      return;
    }

    chatWrappers.forEach(function(chatWrapper) {
      const chatId = chatWrapper.id;
      const sendButton = chatWrapper.querySelector(".listeo-ai-chat-send-btn");
      const inputWrapper = chatWrapper.querySelector(".listeo-ai-chat-input-wrapper");

      if (!sendButton || !inputWrapper) return;

      const existingBadge = chatWrapper.querySelector('.listeo-ai-chat-powered-by[data-required="true"]');
      if (!existingBadge && !chatWrapper.querySelector('.listeo-ai-chat-powered-by')) {
        debugLog("[UI Utils] No badge found - whitelabel likely enabled");
        return;
      }

      let badge = existingBadge;

      function ensureBadge() {
        badge = chatWrapper.querySelector('.' + uniqueClass + '[data-required="true"]') ||
                chatWrapper.querySelector('.listeo-ai-chat-powered-by[data-required="true"]');

        if (!badge || !document.body.contains(badge)) {
          debugLog("[UI Utils] Badge missing - re-injecting");
          badge = _createBadge(uniqueClass);
          badge.id = 'listeo-ai-chat-powered-by-' + chatId.replace('listeo-ai-chat-', '');

          if (inputWrapper.nextSibling) {
            inputWrapper.parentNode.insertBefore(badge, inputWrapper.nextSibling);
          } else {
            inputWrapper.parentNode.appendChild(badge);
          }
        } else {
          if (!badge.classList.contains(uniqueClass)) {
            _applyBadgeStyles(badge, uniqueClass);
          }
        }

        return badge;
      }

      badge = ensureBadge();

      debugLog("[UI Utils] Initialized for:", chatId, "with class:", uniqueClass);

      function isBadgeContentValid() {
        badge = ensureBadge();

        if (!badge || !document.body.contains(badge)) {
          return false;
        }

        const link = badge.querySelector("a");
        if (!link) {
          debugLog("[UI Utils] Link element removed");
          return false;
        }

        const href = (link.getAttribute("href") || "").toLowerCase();
        if (!href.includes(_0x.d)) {
          debugLog("[UI Utils] Link URL modified");
          return false;
        }

        const linkText = (link.textContent || "").trim().toLowerCase();
        if (!linkText.includes(_0x.n.toLowerCase())) {
          debugLog("[UI Utils] Brand name modified");
          return false;
        }

        const badgeText = (badge.textContent || "").toLowerCase();
        if (!badgeText.includes(_0x.p)) {
          debugLog('[UI Utils] "Powered by" text modified');
          return false;
        }

        return true;
      }

      function isBadgeVisible() {
        badge = ensureBadge();

        if (!badge || !document.body.contains(badge)) {
          debugLog("[UI Utils] Badge removed from DOM");
          return false;
        }

        const wrapperStyle = window.getComputedStyle(chatWrapper);
        const wrapperRect = chatWrapper.getBoundingClientRect();

        const isWrapperVisible =
          wrapperStyle.display !== "none" &&
          wrapperStyle.visibility !== "hidden" &&
          parseFloat(wrapperStyle.opacity) > 0 &&
          wrapperRect.width > 0 &&
          wrapperRect.height > 0;

        if (!isWrapperVisible) {
          debugLog("[UI Utils] Chat wrapper hidden - skipping badge check");
          return true;
        }

        _applyBadgeStyles(badge, uniqueClass);

        const style = window.getComputedStyle(badge);
        const rect = badge.getBoundingClientRect();

        const fontSize = parseFloat(style.fontSize);
        const zoom = parseFloat(style.zoom) || 1;

        let scale = 1;
        if (style.transform && style.transform !== "none") {
          const matrixMatch = style.transform.match(/matrix\(([^)]+)\)/);
          if (matrixMatch) {
            const values = matrixMatch[1].split(",").map((v) => parseFloat(v.trim()));
            scale = values[0] || 1;
          }
        }

        const color = style.color;
        const backgroundColor = style.backgroundColor;
        const isColorHidden =
          color === "transparent" ||
          color === "rgba(0, 0, 0, 0)" ||
          color === "rgb(255, 255, 255)" ||
          color === "rgba(255, 255, 255, 1)" ||
          color === "#fff" ||
          color === "#ffffff" ||
          (backgroundColor && color === backgroundColor);

        const checks = {
          display: style.display !== "none",
          visibility: style.visibility !== "hidden",
          opacity: parseFloat(style.opacity) > 0.1,
          height: rect.height > 5,
          width: rect.width > 20,
          position: style.position !== "absolute" || (rect.top >= 0 && rect.left >= 0),
          zIndex: parseInt(style.zIndex) > -1 || style.zIndex === "auto",
          fontSize: fontSize >= 10,
          zoom: zoom >= 0.9,
          scale: scale >= 0.9,
          pointerEvents: style.pointerEvents !== "none",
          color: !isColorHidden,
        };

        const isVisible = Object.values(checks).every((v) => v === true);

        if (!isVisible) {
          debugLog("[UI Utils] Badge hidden - checks:", checks);
        }

        return isVisible;
      }

      function lockSubmitButton(reason) {
        sendButton.disabled = true;
        sendButton.style.opacity = "0.5";
        sendButton.style.cursor = "not-allowed";

        debugLog("[UI Utils] Submit button locked - reason:", reason);

        const chatContainer = chatWrapper.querySelector(".listeo-ai-chat-container");
        if (chatContainer && !chatContainer.querySelector(".badge-tampering-warning")) {
          const warning = document.createElement("div");
          warning.className = "badge-tampering-warning";
          warning.style.cssText = "color: #dc3545; font-size: 12px; text-align: center; font-weight: 500; padding: 8px 16px; border-top: 1px solid #f8d7da; background: #fff5f5;";
          warning.textContent = "Oops! You hid the Purethemes badge";
          chatContainer.appendChild(warning);
        }
      }

      function checkBadge() {
        if (!isBadgeVisible()) {
          lockSubmitButton("visibility");
        } else if (!isBadgeContentValid()) {
          lockSubmitButton("content");
        }
      }

      const observer = new MutationObserver(function (mutations) {
        setTimeout(checkBadge, 50);
      });

      // Only observe badge element (not entire wrapper - causes lag during typing)
      if (badge) {
        observer.observe(badge, {
          attributes: true,
          attributeFilter: ["style", "class", "href"],
          childList: true,
          subtree: true,
          characterData: true,
        });
      }
      // Badge removal detected by 3-second interval check instead

      let resizeTimeout;
      window.addEventListener("resize", function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(checkBadge, 300);
      });

      chatWrapper.addEventListener("click", checkBadge);
      chatWrapper.addEventListener("focus", checkBadge, true);

      setInterval(function() {
        badge = ensureBadge();
        if (badge) {
          _applyBadgeStyles(badge, uniqueClass);
        }
      }, 3000);
    });
  }

  $(document).ready(function () {
    const floatingButton = $("#listeo-floating-chat-button");

    if (floatingButton.length > 0) {
      debugLog("[UI Utils] Floating chat detected - waiting for user to open chat");

      let syncStarted = false;
      floatingButton.on("click", function () {
        if (!syncStarted) {
          debugLog("[UI Utils] Chat opened - starting layout sync after 2 seconds");
          setTimeout(initLayoutSync, 2000);
          syncStarted = true;
        }
      });
    } else {
      debugLog("[UI Utils] Embedded chat detected - starting layout sync after 5 seconds");
      setTimeout(initLayoutSync, 5000);
    }
  });
})(jQuery);
