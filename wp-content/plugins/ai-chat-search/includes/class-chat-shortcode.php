<?php
/**
 * Listeo AI Chat Shortcode
 *
 * Provides shortcode for embedding AI chat interface
 *
 * @package Listeo_AI_Search
 * @since 1.0.0
 */

if (!defined("ABSPATH")) {
    exit();
}

class Listeo_AI_Search_Chat_Shortcode
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register shortcodes (both old and new for backward compatibility)
        add_shortcode("listeo_ai_chat", [$this, "render_chat"]); // Legacy shortcode
        add_shortcode("ai_chat", [$this, "render_chat"]); // New shortcode
        // Assets are enqueued in render_chat() only when shortcode is actually used
    }

    /**
     * Enqueue chat assets
     */
    public function enqueue_chat_assets()
    {
        // Enqueue chat styles
        wp_enqueue_style(
            "listeo-ai-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/chatbot.css",
            [],
            LISTEO_AI_SEARCH_VERSION
        );

        // Enqueue dark mode styles
        wp_enqueue_style(
            "listeo-ai-chat-dark-mode",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/chatbot-dark-mode.css",
            ["listeo-ai-chat"],
            LISTEO_AI_SEARCH_VERSION
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

        // Add inline CSS for primary color variables
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

        $custom_css = sprintf(
            ":root { --ai-chat-primary-color: %s; --ai-chat-primary-color-light: %s; }",
            esc_attr($primary_color),
            esc_attr($primary_color_light),
        );
        wp_add_inline_style("listeo-ai-chat", $custom_css);

        // User-defined custom CSS from Developer & Debug Options.
        // Stripped of HTML tags as defense-in-depth (also sanitized on save).
        $user_custom_css = trim((string) get_option("listeo_ai_chat_custom_css", ""));
        if ($user_custom_css !== "") {
            wp_add_inline_style("listeo-ai-chat", wp_strip_all_tags($user_custom_css));
        }

        // Enqueue chat script
        wp_enqueue_script(
            "listeo-ai-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/purio-ai-scripts.js",
            ["jquery"],
            LISTEO_AI_SEARCH_VERSION,
            true,
        );

        // Load UI utilities only when the badge is visible.
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
        if (!$whitelabel_enabled) {
            wp_enqueue_script(
                "listeo-ai-chat-ui-utils",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/purio-chat-ui-utils.js",
                ["jquery", "listeo-ai-chat"],
                LISTEO_AI_SEARCH_VERSION,
                true,
            );
        }

        // Speech-to-text assets hook (PRO feature)
        if (AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_speech', 0)) {
            do_action('listeo_ai_chat_enqueue_speech_assets');
        }

        // Use shared function for chat config (eliminates duplication with floating widget)
        listeo_ai_localize_chat_config("listeo-ai-chat");
    }

    /**
     * Render chat shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_chat($atts)
    {
        // Enqueue assets only when shortcode is actually used on the page
        $this->enqueue_chat_assets();

        // Parse attributes (use 'ai_chat' as the tag name for shortcode_atts)
        $atts = shortcode_atts(
            [
                "height" => "600px",
                "pictures" => "", // 'enabled', 'disabled', or empty (use global setting)
                "show_popular_searches" => "no", // 'yes' or 'no'
                "popular_searches_limit" => 5, // Number of popular searches to display
                "popular_searches_title" => "", // Custom title text (empty = use default translation)
                "style" => "1", // '1' = default style, '2' = alternative style (same as Elementor Style 2)
            ],
            $atts,
            "ai_chat",
        );

        // Get title, placeholder, welcome message, and avatar from settings (not shortcode attributes)
        $chat_title = get_option(
            "listeo_ai_chat_name",
            __("AI Assistant", "ai-chat-search"),
        );
        $placeholder = __("Type a message", "ai-chat-search");
        $welcome_message = get_option(
            "listeo_ai_chat_welcome_message",
            __("Hello! How can I help you today?", "ai-chat-search"),
        );

        // Get chat avatar
        $chat_avatar_id = intval(get_option("listeo_ai_chat_avatar", 0));
        $chat_avatar_url = $chat_avatar_id
            ? wp_get_attachment_image_url($chat_avatar_id, "thumbnail")
            : "";

        // Determine hideImages setting for this instance
        $global_hide_images = get_option("listeo_ai_chat_hide_images", 1);
        if ($atts["pictures"] === "enabled") {
            $hide_images = 0; // Show images
        } elseif ($atts["pictures"] === "disabled") {
            $hide_images = 1; // Hide images
        } else {
            $hide_images = $global_hide_images; // Use global setting
        }

        // Check if chat is enabled
        if (!get_option("listeo_ai_chat_enabled", 0)) {
            return '<div class="listeo-ai-chat-disabled">' .
                "<p>" .
                __("AI Chat is currently disabled.", "ai-chat-search") .
                "</p>" .
                "</div>";
        }

        // Check if login is required
        if (
            get_option("listeo_ai_chat_require_login", 0) &&
            !is_user_logged_in()
        ) {
            return '<div class="listeo-ai-chat-disabled">' .
                "<p>" .
                __("Please log in to use AI Chat.", "ai-chat-search") .
                "</p>" .
                "</div>";
        }

        // Check if current IP is blocked (PRO feature)
        if (apply_filters('listeo_ai_chat_should_block_ip', false)) {
            return ''; // Silently hide the chat for blocked IPs
        }

        // Generate consistent ID for this chat instance (based on page ID for localStorage persistence)
        // Use post/page ID if available, otherwise use a hash of the URL
        global $post;
        if ($post && $post->ID) {
            $chat_id = "listeo-ai-chat-" . $post->ID;
        } else {
            // Fallback: use hash of current URL for consistent ID across page loads
            $chat_id = "listeo-ai-chat-" . md5($_SERVER["REQUEST_URI"]);
        }

        // Determine if Style 2 should be used
        $use_style2 = $atts["style"] === "2";

        // Get color scheme for dark mode
        $color_scheme = get_option('listeo_ai_color_scheme', 'light');

        ob_start();

        // Wrap in style class if Style 2 is selected
        if ($use_style2) {
            echo '<div class="elementor-chat-style">';
        }
        ?>
        <div class="listeo-ai-chat-wrapper<?php echo $color_scheme === 'dark' ? ' dark-mode' : ''; ?>" id="<?php echo esc_attr(
            $chat_id,
        ); ?>" style="height: <?php echo esc_attr($atts["height"]); ?>" data-hide-images="<?php echo esc_attr($hide_images); ?>"><?php if ($color_scheme === 'auto'): ?><script>if(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches){document.getElementById('<?php echo esc_js($chat_id); ?>').classList.add('dark-mode');}</script><?php endif; ?><?php if (get_option('listeo_ai_color_scheme_switcher')): ?><script>(function(){var s=localStorage.getItem('listeo_ai_chat_dark_mode'),e=document.getElementById('<?php echo esc_js($chat_id); ?>');if(s==='dark')e.classList.add('dark-mode');else if(s==='light')e.classList.remove('dark-mode');})();</script><?php endif; ?>
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
                </div>
                <style>
                /* Shortcode menu positioned absolutely in container */
                .listeo-ai-chat-menu-shortcode { position: absolute !important; top: 15px; right: 15px; z-index: 112; }
                .listeo-ai-chat-menu-shortcode .listeo-ai-chat-menu-dropdown { right: 0; left: auto; }
                /* Style 2: position menu on left like old clear btn */
                .elementor-chat-style .listeo-ai-chat-menu-shortcode { top: 15px; left: 15px; right: auto; }
                .elementor-chat-style .listeo-ai-chat-menu-shortcode .listeo-ai-chat-menu-dropdown { left: 0; right: auto; }
                /* Style 2: hide menu when collapsed (no messages) */
                .elementor-chat-style .listeo-ai-chat-wrapper:not(.expanded) .listeo-ai-chat-menu-shortcode { display: none; }
                </style>
                <div class="listeo-ai-chat-menu listeo-ai-chat-menu-shortcode">
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
                <div class="listeo-ai-chat-messages" id="<?php echo esc_attr(
                    $chat_id,
                ); ?>-messages">
                    <!-- Welcome message added by JavaScript to avoid flash of wrong content -->
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
                        id="<?php echo esc_attr($chat_id); ?>-input"
                        class="listeo-ai-chat-input<?php echo $image_input_enabled ? ' has-image-input' : ''; ?>"
                        placeholder="<?php echo esc_attr($placeholder); ?>"
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
                        id="<?php echo esc_attr($chat_id); ?>-send"
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
                $whitelabel_enabled =
                    $pro_plugin_active &&
                    get_option("listeo_ai_chat_whitelabel_enabled", 0) &&
                    get_option("ai_chat_search_pro_license_instance_id", "") !== "" &&
                    !$trial_expired;
                if (!$whitelabel_enabled): ?>
                    <div class="listeo-ai-chat-powered-by" id="listeo-ai-chat-powered-by-<?php echo esc_attr(
                        $chat_id,
                    ); ?>" data-required="true">
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
        <?php
        // Add popular searches section if enabled
        if ($atts["show_popular_searches"] === "yes") {
            echo $this->render_popular_searches(
                $atts["popular_searches_limit"],
                $chat_id,
                $atts["popular_searches_title"],
            );
        }

        // Close style wrapper if Style 2
        if ($use_style2) {
            echo "</div>";
        }

        return ob_get_clean();
    }

    /**
     * Render popular searches tags
     *
     * @param int $limit Number of popular searches to display
     * @param string $chat_id Chat container ID for JavaScript targeting
     * @param string $title Custom title text (empty = use default translation)
     * @return string
     */
    private function render_popular_searches($limit, $chat_id, $title = "")
    {
        // Check if search suggestions are enabled globally
        if (!get_option("listeo_ai_search_suggestions_enabled", true)) {
            return "";
        }

        // Get the suggestions source setting from plugin
        $suggestions_source = get_option(
            "listeo_ai_search_suggestions_source",
            "top_searches",
        );
        $suggestions = [];

        if ($suggestions_source === "custom") {
            // Use custom suggestions from plugin settings
            $custom_suggestions = get_option(
                "listeo_ai_search_custom_suggestions",
                "",
            );
            if (!empty($custom_suggestions)) {
                $suggestions_array = array_map(
                    "trim",
                    explode(",", $custom_suggestions),
                );
                $suggestions_array = array_filter($suggestions_array); // Remove empty items

                // Limit to the requested number
                $suggestions = array_slice(
                    $suggestions_array,
                    0,
                    intval($limit),
                );
            }
        } elseif ($suggestions_source === "top_searches_10") {
            // Get top 10 searches from analytics
            if (class_exists("Listeo_AI_Search_Analytics")) {
                $analytics = Listeo_AI_Search_Analytics::get_analytics(30);
                if (
                    !empty($analytics["popular_queries"]) &&
                    is_array($analytics["popular_queries"])
                ) {
                    // Get top 10, but limit to the requested limit
                    $top_queries = array_slice(
                        $analytics["popular_queries"],
                        0,
                        min(10, intval($limit)),
                        true,
                    );
                    $suggestions = array_keys($top_queries);
                }
            }
        } else {
            // Default: top 5 searches from analytics
            if (class_exists("Listeo_AI_Search_Analytics")) {
                $analytics = Listeo_AI_Search_Analytics::get_analytics(30);
                if (
                    !empty($analytics["popular_queries"]) &&
                    is_array($analytics["popular_queries"])
                ) {
                    // Get top 5, but limit to the requested limit
                    $top_queries = array_slice(
                        $analytics["popular_queries"],
                        0,
                        min(5, intval($limit)),
                        true,
                    );
                    $suggestions = array_keys($top_queries);
                }
            }
        }

        // If no suggestions found, return empty
        if (empty($suggestions)) {
            return "";
        }

        // Use custom title if provided, otherwise use default translation
        $header_text = !empty($title)
            ? $title
            : __("Popular Searches:", "ai-chat-search");

        ob_start();
        ?>
        <div class="listeo-ai-popular-searches" data-chat-id="<?php echo esc_attr(
            $chat_id,
        ); ?>">
            <div class="popular-searches-header">
                <?php echo esc_html($header_text); ?>
            </div>
            <div class="popular-searches-tags">
                <?php foreach ($suggestions as $suggestion): ?>
                    <button class="popular-search-tag" data-query="<?php echo esc_attr(
                        $suggestion,
                    ); ?>">
                        <?php echo esc_html(ucfirst($suggestion)); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}

// Initialize shortcode
new Listeo_AI_Search_Chat_Shortcode();
