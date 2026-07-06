<?php
/**
 * Listeo AI Floating Chat Widget
 *
 * Floating chat button and popup that appears on all pages
 *
 * @package Listeo_AI_Search
 * @since 1.0.0
 */

if (!defined("ABSPATH")) {
    exit();
}

class Listeo_AI_Search_Floating_Chat_Widget
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action("wp_footer", [$this, "render_floating_widget"]);
        add_action("wp_enqueue_scripts", [$this, "enqueue_widget_assets"]);
        add_filter("script_loader_tag", [$this, "add_defer_to_floating_widget"], 10, 2);
    }

    /**
     * Add defer attribute to the floating widget script (lazy mode only).
     */
    public function add_defer_to_floating_widget($tag, $handle)
    {
        if ($handle === "listeo-ai-floating-chat" && get_option("listeo_ai_chat_lazy_load", 0)) {
            if (strpos($tag, " defer") === false) {
                $tag = str_replace(" src=", " defer src=", $tag);
            }
        }
        return $tag;
    }

    /**
     * Enqueue widget assets
     */
    public function enqueue_widget_assets()
    {
        // Only load if widget is enabled
        if (!get_option("listeo_ai_floating_chat_enabled", 0)) {
            return;
        }

        // Check if login is required and user is not logged in
        if (
            get_option("listeo_ai_chat_require_login", 0) &&
            !is_user_logged_in()
        ) {
            return;
        }

        // Check if current page is in the exclusion list
        $excluded_pages = get_option("listeo_ai_floating_excluded_pages", []);
        if (!empty($excluded_pages) && is_array($excluded_pages) && is_singular()) {
            $current_page_id = get_queried_object_id();
            if (in_array($current_page_id, $excluded_pages)) {
                return;
            }
        }

        // Check if current IP is blocked (PRO feature)
        if (apply_filters('listeo_ai_chat_should_block_ip', false)) {
            return;
        }

        // Enqueue chat styles (reuse from shortcode)
        wp_enqueue_style(
            "listeo-ai-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/chatbot.css",
            [],
            LISTEO_AI_SEARCH_VERSION,
        );

        // Enqueue dark mode styles
        wp_enqueue_style(
            "listeo-ai-chat-dark-mode",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/chatbot-dark-mode.css",
            ["listeo-ai-chat"],
            LISTEO_AI_SEARCH_VERSION,
        );

        // Enqueue dark mode JS only for auto mode
        if (get_option('listeo_ai_color_scheme', 'light') === 'auto') {
            wp_enqueue_script(
                "listeo-ai-chat-dark-mode",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/chatbot-dark-mode.js",
                [],
                LISTEO_AI_SEARCH_VERSION,
                false // Load in head for immediate execution
            );
        }

        // Enqueue floating widget styles
        wp_enqueue_style(
            "listeo-ai-floating-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/floating-chat.css",
            ["listeo-ai-chat"],
            LISTEO_AI_SEARCH_VERSION,
        );

        // User-defined custom CSS from Developer & Debug Options.
        // Stripped of HTML tags as defense-in-depth (also sanitized on save).
        $user_custom_css = trim((string) get_option("listeo_ai_chat_custom_css", ""));
        if ($user_custom_css !== "") {
            wp_add_inline_style("listeo-ai-chat", wp_strip_all_tags($user_custom_css));
        }

        // Lazy load mode: defer chatbot scripts until user opens the widget
        $lazy_load = get_option('listeo_ai_chat_lazy_load', 0);
        $pro_plugin_file = 'ai-chat-search-pro/ai-chat-search-pro.php';
        $active_plugins = (array) get_option('active_plugins', array());
        $network_active_plugins = is_multisite() ? (array) get_site_option('active_sitewide_plugins', array()) : array();
        $pro_plugin_active =
            in_array($pro_plugin_file, $active_plugins, true) ||
            isset($network_active_plugins[$pro_plugin_file]);
        $trial_expires_at = (int) get_option('ai_chat_search_pro_trial_expires_at', 0);
        $trial_expired =
            get_option('ai_chat_search_pro_is_trial', false) &&
            $trial_expires_at <= time();
        $whitelabel_enabled =
            $pro_plugin_active &&
            get_option('listeo_ai_chat_whitelabel_enabled', 0) &&
            get_option('ai_chat_search_pro_license_instance_id', '') !== '' &&
            !$trial_expired;
        $needs_silk_wave = get_option('listeo_ai_floating_header_style', 'simple') === 'animated';

        if (!$lazy_load) {
            // Standard mode: load all scripts upfront
            wp_enqueue_script(
                "listeo-ai-chat",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/purio-ai-scripts.js",
                ["jquery"],
                LISTEO_AI_SEARCH_VERSION,
                true,
            );

            if ($needs_silk_wave) {
                wp_enqueue_script(
                    "listeo-silk-wave-bg",
                    LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/silk-wave-bg.js",
                    [],
                    LISTEO_AI_SEARCH_VERSION,
                    true,
                );
            }
        }

        // Lazy: head + defer, no deps (vanilla JS). Non-lazy: footer + original deps.
        if ($lazy_load) {
            wp_enqueue_script(
                "listeo-ai-floating-chat",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/ai-floating-chat-widget.js",
                [], // no deps — vanilla JS
                LISTEO_AI_SEARCH_VERSION,
                false, // load in <head> (paired with defer filter)
            );
        } else {
            wp_enqueue_script(
                "listeo-ai-floating-chat",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/ai-floating-chat-widget.js",
                ["jquery", "listeo-ai-chat"],
                LISTEO_AI_SEARCH_VERSION,
                true,
            );
        }

        // Load UI utilities only when badge is visible (whitelabel not enabled)
        if (!$lazy_load && !$whitelabel_enabled) {
            wp_enqueue_script(
                "listeo-ai-chat-ui-utils",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/purio-chat-ui-utils.js",
                ["jquery", "listeo-ai-chat"],
                LISTEO_AI_SEARCH_VERSION,
                true,
            );
        }

        // Localize chat config on the script that's guaranteed to be enqueued
        $config_handle = $lazy_load ? "listeo-ai-floating-chat" : "listeo-ai-chat";
        listeo_ai_localize_chat_config($config_handle);

        // Get welcome bubble message for floating widget
        $welcome_bubble_message = get_option(
            "listeo_ai_floating_welcome_bubble",
            __("Hi! How can I help you?", "ai-chat-search"),
        );

        // Build lazy load script URLs when enabled
        $lazy_scripts = [];
        if ($lazy_load) {
            // silk-wave must load before chatbot-core (which calls ListeoSilkWave.init in showWelcome)
            if ($needs_silk_wave) {
                $lazy_scripts[] = LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/silk-wave-bg.js";
            }
            $lazy_scripts[] = LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/purio-ai-scripts.js";
            if (!$whitelabel_enabled) {
                $lazy_scripts[] = LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/purio-chat-ui-utils.js";
            }
            if (AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_speech', 0)) {
                $lazy_scripts[] = defined('AI_CHAT_SEARCH_PRO_URL') ? AI_CHAT_SEARCH_PRO_URL . "assets/js/speech-to-text.js" : '';
            }
        }

        // Localize script for floating widget
        $floating_config = [
            "welcomeBubbleMessage" => $welcome_bubble_message,
            "buttonIcon" => get_option(
                "listeo_ai_floating_button_icon",
                "fa-robot",
            ),
            "keepChatOpened" => get_option("listeo_ai_floating_keep_chat_opened", 0) ? true : false,
            "strings" => [
                "openChat" => __("Open chat", "ai-chat-search"),
                "closeChat" => __("Close chat", "ai-chat-search"),
            ],
        ];
        if (!empty($lazy_scripts)) {
            $floating_config["lazyScripts"] = $lazy_scripts;
            $floating_config["scriptVersion"] = LISTEO_AI_SEARCH_VERSION;
        }
        wp_localize_script(
            "listeo-ai-floating-chat",
            "listeoAiFloatingChatConfig",
            $floating_config,
        );

        // Speech-to-text assets (PRO feature)
        if (AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_speech', 0)) {
            if ($lazy_load) {
                // JS is deferred via lazyScripts, but CSS can load now (style handle resolves fine)
                if (defined('AI_CHAT_SEARCH_PRO_URL')) {
                    wp_enqueue_style(
                        'ai-chat-search-pro-speech',
                        AI_CHAT_SEARCH_PRO_URL . 'assets/css/speech-to-text.css',
                        ['listeo-ai-chat'],
                        defined('AI_CHAT_SEARCH_PRO_VERSION') ? AI_CHAT_SEARCH_PRO_VERSION : LISTEO_AI_SEARCH_VERSION
                    );
                }
            } else {
                do_action('listeo_ai_chat_enqueue_speech_assets');
            }
        }
    }

    /**
     * Get placeholder image URL
     */
    private function get_placeholder_image()
    {
        $placeholder_url = "";

        // Try listeo-core function
        if (function_exists("get_listeo_core_placeholder_image")) {
            $placeholder = get_listeo_core_placeholder_image();
            if (is_numeric($placeholder)) {
                $placeholder_img = wp_get_attachment_image_src(
                    $placeholder,
                    "medium",
                );
                if ($placeholder_img && isset($placeholder_img[0])) {
                    $placeholder_url = $placeholder_img[0];
                }
            } else {
                $placeholder_url = $placeholder;
            }
        }

        // Fallback to theme customizer
        if (empty($placeholder_url)) {
            $placeholder_id = get_theme_mod("listeo_placeholder_id");
            if ($placeholder_id) {
                $placeholder_img = wp_get_attachment_image_src(
                    $placeholder_id,
                    "medium",
                );
                if ($placeholder_img && isset($placeholder_img[0])) {
                    $placeholder_url = $placeholder_img[0];
                }
            }
        }

        return $placeholder_url;
    }

    /**
     * Render floating widget HTML
     */
    public function render_floating_widget()
    {
        // Only render if widget is enabled
        if (!get_option("listeo_ai_floating_chat_enabled", 0)) {
            return;
        }

        // Check if chat is enabled
        if (!get_option("listeo_ai_chat_enabled", 0)) {
            return;
        }

        // Check if login is required and user is not logged in
        if (
            get_option("listeo_ai_chat_require_login", 0) &&
            !is_user_logged_in()
        ) {
            return;
        }

        // Check if current page is in the exclusion list
        $excluded_pages = get_option("listeo_ai_floating_excluded_pages", []);
        if (!empty($excluded_pages) && is_array($excluded_pages) && is_singular()) {
            $current_page_id = get_queried_object_id();
            if (in_array($current_page_id, $excluded_pages)) {
                return;
            }
        }

        // Check if current IP is blocked (PRO feature)
        if (apply_filters('listeo_ai_chat_should_block_ip', false)) {
            return;
        }

        // Get settings
        $chat_title = get_option(
            "listeo_ai_chat_name",
            __("AI Assistant", "ai-chat-search"),
        );
        $placeholder = __("Type a message", "ai-chat-search");
        $custom_icon_id = intval(
            get_option("listeo_ai_floating_custom_icon", 0),
        );

        // Get chat avatar
        $chat_avatar_id = intval(get_option("listeo_ai_chat_avatar", 0));
        $chat_avatar_url = $chat_avatar_id
            ? wp_get_attachment_image_url($chat_avatar_id, "thumbnail")
            : "";
        $welcome_bubble = get_option(
            "listeo_ai_floating_welcome_bubble",
            __("Hi! How can I help you?", "ai-chat-search"),
        );
        $popup_width = intval(
            get_option("listeo_ai_floating_popup_width", 390),
        );
        $popup_height = intval(
            get_option("listeo_ai_floating_popup_height", 600),
        );
        $hide_images = intval(get_option("listeo_ai_chat_hide_images", 1));
        $button_color = sanitize_hex_color(
            get_option("listeo_ai_floating_button_color", "#222222"),
        );
        if (empty($button_color)) {
            $button_color = "#222222"; // Fallback
        }

        // Validate dimensions
        $popup_width = max(320, min(800, $popup_width));
        $popup_height = max(400, min(900, $popup_height));

        // Get color scheme for dark mode
        $color_scheme = get_option('listeo_ai_color_scheme', 'light');

        // Get widget position
        $widget_position = get_option('listeo_ai_floating_position', 'right');

        // Get header style settings
        $header_style = get_option('listeo_ai_floating_header_style', 'simple');
        $header_bg_id = intval(get_option('listeo_ai_floating_header_bg', 0));
        $header_bg_url = $header_bg_id ? wp_get_attachment_image_url($header_bg_id, 'medium') : '';
        $use_image_header = ($header_style === 'image' || $header_style === 'animated'); // Both use expanded header
        $use_animated_header = ($header_style === 'animated');
        $has_header_bg_image = !empty($header_bg_url) && !$use_animated_header;
        $use_header_overlay = $use_animated_header || ($header_style === 'image' && get_option('listeo_ai_floating_header_overlay', 0));
        $animated_bg_color = sanitize_hex_color(get_option('listeo_ai_animated_bg_color', '#1560d0'));
        if (empty($animated_bg_color)) $animated_bg_color = '#1560d0';

        // Get custom icon URL if set
        $custom_icon_url = $custom_icon_id
            ? wp_get_attachment_image_url($custom_icon_id, "full")
            : "";
        $use_custom_icon = !empty($custom_icon_url);
        $custom_icon_size = absint(get_option('listeo_ai_floating_custom_icon_size', 32));
        if ($custom_icon_size < 1) {
            $custom_icon_size = 32;
        }

        // Get primary color from settings
        $primary_color = sanitize_hex_color(
            get_option("listeo_ai_primary_color", "#0073ee"),
        );
        if (empty($primary_color)) {
            $primary_color = "#0073ee"; // Fallback
        }

        // Convert hex to RGB for light variant
        $primary_rgb = sscanf($primary_color, "#%02x%02x%02x");
        $primary_color_light = sprintf(
            "rgba(%d, %d, %d, 0.1)",
            $primary_rgb[0],
            $primary_rgb[1],
            $primary_rgb[2],
        );

        // Offset settings
        $offset_desktop_h = absint(get_option('listeo_ai_floating_offset_desktop_h', 20));
        $offset_desktop_v = absint(get_option('listeo_ai_floating_offset_desktop_v', 20));
        $offset_mobile_h = absint(get_option('listeo_ai_floating_offset_mobile_h', 20));
        $offset_mobile_v = absint(get_option('listeo_ai_floating_offset_mobile_v', 20));
        ?>
        <!-- Custom Button Color Styles -->
        <style>
            .listeo-floating-chat-button,
            .listeo-ai-chat-send-btn,
            .listeo-ai-load-listing-btn {
                background: <?php echo esc_attr($button_color); ?> !important;
            }

            <?php if ($use_custom_icon) : ?>
            /* Custom icon size override */
            .listeo-floating-custom-icon {
                width: <?php echo esc_attr($custom_icon_size); ?>px;
                height: <?php echo esc_attr($custom_icon_size); ?>px;
                max-width: <?php echo esc_attr($custom_icon_size); ?>px;
                max-height: <?php echo esc_attr($custom_icon_size); ?>px;
            }
            <?php endif; ?>

            /* AI Chat Primary Color Variables */
            :root {
                --ai-chat-primary-color: <?php echo esc_attr(
                    $primary_color,
                ); ?>;
                --ai-chat-primary-color-light: <?php echo esc_attr(
                    $primary_color_light,
                ); ?>;
            }

            /* Floating widget offset - desktop */
            @media (min-width: 769px) {
                .listeo-floating-chat-widget<?php echo $widget_position === 'left' ? '.position-left' : ''; ?> {
                    bottom: <?php echo esc_attr($offset_desktop_v); ?>px;
                    <?php echo $widget_position === 'left' ? 'left' : 'right'; ?>: <?php echo esc_attr($offset_desktop_h); ?>px;
                }
            }
            /* Floating widget offset - mobile (horizontal applies to button only) */
            @media (max-width: 768px) {
                .listeo-floating-chat-widget<?php echo $widget_position === 'left' ? '.position-left' : ''; ?> .listeo-floating-chat-button {
                    bottom: <?php echo esc_attr($offset_mobile_v); ?>px;
                    <?php echo $widget_position === 'left' ? 'left' : 'right'; ?>: <?php echo esc_attr($offset_mobile_h); ?>px;
                }
                .listeo-floating-chat-widget .listeo-floating-chat-popup {
                    bottom: <?php echo esc_attr($offset_mobile_v + 60); ?>px !important;
                }
            }
        </style>

        <!-- Floating Chat Widget -->
        <div class="listeo-floating-chat-widget<?php echo $color_scheme === 'dark' ? ' dark-mode' : ''; ?><?php echo $widget_position === 'left' ? ' position-left' : ''; ?>" id="listeo-floating-chat-widget">
        <?php if ($color_scheme === 'auto'): ?>
        <script>if(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches){document.getElementById('listeo-floating-chat-widget').classList.add('dark-mode');}</script>
        <?php endif; ?>
        <?php if (get_option('listeo_ai_color_scheme_switcher')): ?>
        <script>(function(){var s=localStorage.getItem('listeo_ai_chat_dark_mode'),e=document.getElementById('listeo-floating-chat-widget');if(s==='dark')e.classList.add('dark-mode');else if(s==='light')e.classList.remove('dark-mode');})();</script>
        <?php endif; ?>

            <?php if (!empty(trim($welcome_bubble))) : ?>
            <!-- Welcome Bubble (shows on first visit only) -->
            <div class="listeo-floating-welcome-bubble hidden" id="listeo-floating-welcome-bubble">
                <div class="listeo-floating-welcome-bubble-content">
                    <?php echo wp_kses_post($welcome_bubble); ?>
                </div>
                <div class="listeo-floating-welcome-bubble-arrow"></div>
            </div>
            <!-- Check localStorage immediately to prevent flash -->
            <script>
                (function() {
                    var bubble = document.getElementById('listeo-floating-welcome-bubble');
                    var dismissed = localStorage.getItem('listeo_floating_chat_bubble_dismissed');
                    if (dismissed !== 'true' && bubble) {
                        bubble.classList.remove('hidden');
                    }
                })();
            </script>
            <?php endif; ?>

            <!-- Floating Button -->
            <button
                class="listeo-floating-chat-button <?php echo $use_custom_icon
                    ? "has-custom-icon"
                    : ""; ?>"
                id="listeo-floating-chat-button"
                aria-label="<?php esc_attr_e("Open chat", "ai-chat-search"); ?>"
            >
                <?php if ($use_custom_icon): ?>
                    <img src="<?php echo esc_url(
                        $custom_icon_url,
                    ); ?>" alt="Chat" class="listeo-floating-custom-icon listeo-floating-icon-open" />
                <?php else: ?>
                    <img src="<?php echo esc_url(
                        LISTEO_AI_SEARCH_PLUGIN_URL . "assets/icons/chat.svg",
                    ); ?>" alt="Chat" class="listeo-floating-icon-open" width="28" height="28" />
                <?php endif; ?>
                <img src="<?php echo esc_url(
                    LISTEO_AI_SEARCH_PLUGIN_URL . "assets/icons/close.svg",
                ); ?>" alt="Close" class="listeo-floating-icon-close" style="display: none;" width="18" height="18" />
            </button>

            <!-- Chat Popup (reuses exact shortcode HTML structure) -->
            <div class="listeo-floating-chat-popup<?php echo $use_image_header ? ' chat-image-header' : ''; ?><?php echo $use_animated_header ? ' chat-animated-header' : ''; ?><?php echo $use_header_overlay ? ' chat-image-header-overlay' : ''; ?>" id="listeo-floating-chat-popup" style="display: none; width: <?php echo esc_attr(
                $popup_width,
            ); ?>px; height: <?php echo esc_attr($popup_height); ?>px;<?php if ($use_image_header && !$use_animated_header) { echo $has_header_bg_image ? ' --header-bg-image: url(' . esc_url($header_bg_url) . ');' : ' --header-bg-color: ' . esc_attr($primary_color) . ';'; } ?>">
                <div class="listeo-ai-chat-wrapper<?php echo $color_scheme === 'dark' ? ' dark-mode' : ''; ?>" id="listeo-floating-chat-instance" data-hide-images="<?php echo esc_attr(
                    $hide_images,
                ); ?>"><?php if ($color_scheme === 'auto'): ?><script>if(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches){document.getElementById('listeo-floating-chat-instance').classList.add('dark-mode');}</script><?php endif; ?><?php if (get_option('listeo_ai_color_scheme_switcher')): ?><script>(function(){var s=localStorage.getItem('listeo_ai_chat_dark_mode'),e=document.getElementById('listeo-floating-chat-instance');if(s==='dark')e.classList.add('dark-mode');else if(s==='light')e.classList.remove('dark-mode');})();</script><?php endif; ?>
                    <div class="listeo-ai-chat-container">
                        <div class="listeo-ai-chat-header">
                            <div class="listeo-ai-chat-header-left">
                                <?php if ($chat_avatar_url): ?>
                                    <div class="listeo-ai-chat-avatar-wrapper">
                                        <img src="<?php echo esc_url(
                                            $chat_avatar_url,
                                        ); ?>" alt="<?php echo esc_attr(
    $chat_title,
); ?>" class="listeo-ai-chat-avatar" />
                                        <span class="listeo-ai-chat-status-dot"></span>
                                    </div>
                                <?php endif; ?>
                                <div class="listeo-ai-chat-title"><?php echo esc_html(
                                    $chat_title,
                                ); ?></div>
                            </div>
                            <div class="listeo-ai-chat-menu">
                                <?php if (class_exists('WooCommerce') && get_option('listeo_ai_chat_woo_cart_enabled', 0)): ?>
                                <div class="listeo-ai-chat-cart-toggle" role="button" tabindex="0" aria-label="<?php esc_attr_e('Shopping cart', 'ai-chat-search'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                    <span class="listeo-ai-cart-badge" style="display: none;">0</span>
                                </div>
                                <?php endif; ?>
                                <?php if (get_option('listeo_ai_color_scheme_switcher')): ?>
                                <div class="listeo-ai-chat-darkmode-toggle" role="button" tabindex="0" aria-label="<?php esc_attr_e('Toggle dark mode', 'ai-chat-search'); ?>">
                                    <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                                    <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                                </div>
                                <?php endif; ?>
                                <div class="listeo-ai-chat-menu-trigger" role="button" tabindex="0" aria-haspopup="menu" aria-expanded="false">
                                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 6.5C2.17 6.5 1.5 7.17 1.5 8C1.5 8.83 2.17 9.5 3 9.5C3.83 9.5 4.5 8.83 4.5 8C4.5 7.17 3.83 6.5 3 6.5ZM8 6.5C7.17 6.5 6.5 7.17 6.5 8C6.5 8.83 7.17 9.5 8 9.5C8.83 9.5 9.5 8.83 9.5 8C9.5 7.17 8.83 6.5 8 6.5ZM13 6.5C12.17 6.5 11.5 7.17 11.5 8C11.5 8.83 12.17 9.5 13 9.5C13.83 9.5 14.5 8.83 14.5 8C14.5 7.17 13.83 6.5 13 6.5Z" fill="currentColor"/>
                                    </svg>
                                </div>
                                <div class="listeo-ai-chat-menu-dropdown" role="menu" data-state="closed">
                                    <div class="listeo-ai-chat-menu-item listeo-ai-chat-expand-btn" role="menuitem" tabindex="-1">
                                        <svg class="icon-expand" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <polyline points="9 21 3 21 3 15"></polyline>
                                            <line x1="21" y1="3" x2="14" y2="10"></line>
                                            <line x1="3" y1="21" x2="10" y2="14"></line>
                                        </svg>
                                        <svg class="icon-collapse" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="4 14 10 14 10 20"></polyline>
                                            <polyline points="20 10 14 10 14 4"></polyline>
                                            <line x1="14" y1="10" x2="21" y2="3"></line>
                                            <line x1="3" y1="21" x2="10" y2="14"></line>
                                        </svg>
                                        <span class="text-expand"><?php esc_html_e("Expand chat", "ai-chat-search"); ?></span>
                                        <span class="text-collapse"><?php esc_html_e("Collapse chat", "ai-chat-search"); ?></span>
                                    </div>
                                    <div class="listeo-ai-chat-menu-item listeo-ai-chat-clear-btn" role="menuitem" tabindex="-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                            <line x1="12" y1="7" x2="12" y2="13"></line>
                                            <line x1="9" y1="10" x2="15" y2="10"></line>
                                        </svg>
                                        <?php esc_html_e("Start a new chat", "ai-chat-search"); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="listeo-ai-chat-messages" id="listeo-floating-chat-instance-messages">
                            <!-- Welcome message added by JavaScript -->
                        </div>

                        <?php
                        // Quick Action Buttons (PRO feature - code in Pro plugin)
                        do_action('listeo_ai_chat_quick_buttons');
                        ?>

                        <?php $image_input_enabled = AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_image_input', 0); ?>
                        <div class="listeo-ai-chat-input-wrapper">
                            <?php if ($image_input_enabled): ?>
                            <div
                                class="listeo-ai-chat-image-btn"
                                data-chat-tooltip="<?php esc_attr_e('Attach Image', 'ai-chat-search'); ?>"
                                role="button"
                                tabindex="0"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span class="image-count-badge">1</span>
                            </div>
                            <input type="file" class="listeo-ai-chat-image-input" accept="image/jpeg,image/jpg,.jpg,.jpeg,image/png,image/gif,image/webp" style="display: none;" />
                            <?php endif; ?>
                            <textarea
                                id="listeo-floating-chat-instance-input"
                                class="listeo-ai-chat-input<?php echo $image_input_enabled ? ' has-image-input' : ''; ?>"
                                placeholder="<?php echo esc_attr(
                                    $placeholder,
                                ); ?>"
                                rows="2"
                                maxlength="1000"
                            ></textarea>
                            <?php
                            // Speech-to-text mic button (PRO feature)
                            if (AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_speech', 0)) {
                                do_action('listeo_ai_chat_mic_button');
                            }
                            ?>
                            <button
                                id="listeo-floating-chat-instance-send"
                                class="listeo-ai-chat-send-btn"
                            >
                                <img src="<?php echo esc_url(
                                    LISTEO_AI_SEARCH_PLUGIN_URL .
                                        "assets/icons/arrow-up.svg",
                                ); ?>" alt="Send" width="16" height="16" />
                            </button>
                        </div>

                        <?php if (
                            get_option("listeo_ai_chat_terms_notice_enabled", 0)
                        ): ?>
                            <div class="listeo-ai-chat-terms-notice">
                                <?php echo wp_kses_post(
                                    get_option(
                                        "listeo_ai_chat_terms_notice_text",
                                        'By using this chat, you agree to our <a href="/terms-of-use" target="_blank">Terms of Use</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>',
                                    ),
                                ); ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Show "Powered by PurioChat" badge unless whitelabel is fully enabled.
                        $pro_plugin_file = "ai-chat-search-pro/ai-chat-search-pro.php";
                        $active_plugins = (array) get_option("active_plugins", array());
                        $network_active_plugins = is_multisite() ? (array) get_site_option("active_sitewide_plugins", array()) : array();
                        $pro_plugin_active =
                            in_array($pro_plugin_file, $active_plugins, true) ||
                            isset($network_active_plugins[$pro_plugin_file]);
                        $trial_expires_at = (int) get_option("ai_chat_search_pro_trial_expires_at", 0);
                        $trial_expired =
                            get_option("ai_chat_search_pro_is_trial", false) &&
                            $trial_expires_at <= time();
                        $whitelabel_enabled_widget =
                            $pro_plugin_active &&
                            get_option("listeo_ai_chat_whitelabel_enabled", 0) &&
                            get_option("ai_chat_search_pro_license_instance_id", "") !== "" &&
                            !$trial_expired;
                        if (!$whitelabel_enabled_widget): ?>
                            <div class="listeo-ai-chat-powered-by" id="listeo-ai-chat-powered-by-floating" data-required="true">
                                Powered by <a href="https://purethemes.net/ai-chatbot-for-wordpress/?utm_source=chatbot-widget&utm_medium=powered-by&utm_campaign=branding" target="_blank" rel="noopener" style="--ai-chat-primary-color: #111;"><img class="listeo-ai-chat-powered-by-logo" src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . "assets/icons/purio.svg"); ?>" alt="" aria-hidden="true" /><span class="listeo-ai-chat-powered-by-name" style="font-weight: 600 !important;">PurioChat</span></a>
                            </div>
                        <?php endif;
                        ?>

<?php
                        // Pre-Chat Required Fields Form (PRO feature - rendered by Pro plugin)
                        do_action('listeo_ai_chat_pre_chat_form');
                        ?>

<?php
                        // Contact Form Overlay (PRO feature - rendered by Pro plugin when quick buttons enabled)
                        do_action('listeo_ai_chat_contact_form_overlay');
                        ?>

                        <?php
                        // Cart popup overlay (Pro feature — rendered by Pro plugin via hook)
                        do_action('listeo_ai_chat_cart_popup');
                        ?>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }
}

// Initialize floating widget
new Listeo_AI_Search_Floating_Chat_Widget();
