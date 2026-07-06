/**
 * Chat Theme Switcher
 * Handles dark/light mode toggle in the chat widget.
 *
 * @package AI Chat by Purethemes
 */
(function ($) {
  "use strict";

  var STORAGE_KEY = "listeo_ai_chat_dark_mode";

  /**
   * Apply dark mode state to all chat wrappers and floating widget.
   */
  function applyDarkMode(enabled) {
    var $widget = $(".listeo-floating-chat-widget");

    $(".listeo-ai-chat-wrapper").each(function () {
      if (enabled) {
        $(this).addClass("dark-mode");
      } else {
        $(this).removeClass("dark-mode");
      }
    });

    if (enabled) {
      $widget.addClass("dark-mode");
    } else {
      $widget.removeClass("dark-mode");
    }

    // Update animated wave background colors if present
    if (typeof ListeoSilkWave !== "undefined") {
      ListeoSilkWave.setDarkMode(enabled);
    }
  }

  /**
   * Restore saved preference from localStorage on page load.
   */
  function restorePreference() {
    var saved = localStorage.getItem(STORAGE_KEY);
    if (!saved) return;

    applyDarkMode(saved === "dark");
  }

  /**
   * Handle toggle click.
   */
  function onToggleClick() {
    var $wrapper = $(this).closest(".listeo-ai-chat-wrapper");
    if (!$wrapper.length) {
      $wrapper = $(this)
        .closest(".listeo-ai-chat-container")
        .closest(".listeo-ai-chat-wrapper");
    }
    if (!$wrapper.length) return;

    var isDark = $wrapper.hasClass("dark-mode");
    var newMode = !isDark;

    applyDarkMode(newMode);
    localStorage.setItem(STORAGE_KEY, newMode ? "dark" : "light");
  }

  $(function () {
    restorePreference();

    $(document).on("click", ".listeo-ai-chat-darkmode-toggle", onToggleClick);
  });
})(jQuery);
