<?php
/**
 * Plugin Name:       Listeo Data Importer
 * Description:       Use it responsibly - heavy or improper use could lead to API limits or account suspension. We do not take responsibility for any issues that come up.
 * Version:           3.0.3
 * License:           GPL-2.0+
 * Text Domain:       listeo-data-scraper
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * The main plugin class.
 *
 * This class is responsible for initializing the plugin, loading dependencies,
 * and setting up all hooks. It follows the Singleton pattern to ensure that
 * it is loaded only once.
 *
 * @since 1.0.0
 */
final class Listeo_Data_Scraper
{
    /**
     * The single instance of the class.
     *
     * @var Listeo_Data_Scraper|null
     */
    private static $instance = null;

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = "3.0.3";

    /**
     * Ensures only one instance of the class is loaded.
     *
     * @return Listeo_Data_Scraper - The single instance.
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     *
     * Sets up the plugin's core functionality.
     */
    private function __construct()
    {
        $this->define_constants();
        $this->setup_autoloader();
        $this->init_hooks();
    }

    /**
     * Define plugin constants.
     */
    private function define_constants()
    {
        define("LDS_VERSION", $this->version);
        define("LDS_PLUGIN_DIR", plugin_dir_path(__FILE__));
        define("LDS_PLUGIN_URL", plugin_dir_url(__FILE__));
    }

    /**
     * Set up the autoloader for our classes.
     *
     * This function automatically loads class files when they are needed,
     * following a specific naming convention: class-lds-*.php
     */
    private function setup_autoloader()
    {
        // Manually load licensing classes first (required by other classes)
        // Order matters: Proxy Manager → Pro Manager → License
        require_once LDS_PLUGIN_DIR .
            "includes/class-lds-proxy-license-manager.php";
        require_once LDS_PLUGIN_DIR . "includes/class-lds-pro-manager.php";
        require_once LDS_PLUGIN_DIR . "includes/class-lds-license.php";

        // Plugin updater (self-hosted updates)
        require_once LDS_PLUGIN_DIR . "includes/class-lds-updater.php";

        // API integration classes
        require_once LDS_PLUGIN_DIR . "includes/class-lds-outscraper-api.php";

        spl_autoload_register(function ($class_name) {
            // Only autoload our plugin's classes.
            if (strpos($class_name, "LDS_") !== 0) {
                return;
            }

            // Convert class name from CamelCase to kebab-case for the filename.
            // Example: LDS_Admin_Menu -> class-lds-admin-menu.php
            $file_name =
                "class-" .
                str_replace("_", "-", strtolower($class_name)) .
                ".php";
            $file_path = LDS_PLUGIN_DIR . "includes/" . $file_name;

            if (file_exists($file_path)) {
                require_once $file_path;
            }
        });
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks()
    {
        // Hook to initialize plugin classes.
        add_action("plugins_loaded", [$this, "init_plugin"]);

        // Register activation and deactivation hooks.
        register_activation_hook(__FILE__, [$this, "activate"]);
        register_deactivation_hook(__FILE__, [$this, "deactivate"]);
    }

    /**
     * Instantiate plugin classes.
     *
     * This runs on the 'plugins_loaded' hook to ensure all dependent plugins are loaded.
     */
    public function init_plugin()
    {
        // Initialize proxy license manager (sets up cron job for weekly validation)
        LDS_Proxy_License_Manager::get_instance();

        // The autoloader will find and include these class files automatically.
        new LDS_Admin_Menu();
        new LDS_Settings();
        new LDS_Ajax_Handler();
        new LDS_License(); // Initialize license management
        new LDS_Photo_Regeneration(); // Initialize photo regeneration tool
        new LDS_AI_Description_Regeneration(); // Initialize AI description regeneration tool
        new LDS_Scheduler(); // Initialize scheduled imports (Pro)
    }

    /**
     * Plugin activation hook.
     *
     * This runs once when the plugin is activated.
     */
    public function activate()
    {
        // Placeholder for activation tasks, e.g., flushing rewrite rules.
        // flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook.
     *
     * This runs once when the plugin is deactivated.
     */
    public function deactivate()
    {
        // Clear any pending scheduled-import cron events (one-shot events keyed per task id).
        // wp_unschedule_hook() removes every event for the hook regardless of its args.
        require_once LDS_PLUGIN_DIR . "includes/class-lds-scheduler.php";
        wp_unschedule_hook(LDS_Scheduler::CRON_HOOK);
    }

    /**
     * Cloning is forbidden.
     */
    private function __clone() {}

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * kicking off the plugin from this point is all that is needed.
 *
 * @return Listeo_Data_Scraper The single instance of the plugin.
 */
function listeo_data_scraper_run()
{
    return Listeo_Data_Scraper::instance();
}

/**
 * Get inline SVG icon markup.
 *
 * @param string $icon Icon name.
 * @param string $class Optional extra CSS classes.
 * @return string
 */
function lds_get_svg_icon_raw($icon)
{
    $icons = [
        "check" =>
            '<path d="M20 6 9 17l-5-5"></path>',
        "loader" =>
            '<path d="M21 12a9 9 0 1 1-6.219-8.56"></path>',
        "refresh" =>
            '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path>',
        "unlock" =>
            '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path>',
        "lock" =>
            '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        "x" =>
            '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
        "alert" =>
            '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
        "info" =>
            '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>',
        "lightbulb" =>
            '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"></path><path d="M9 18h6"></path><path d="M10 22h4"></path>',
        "map" =>
            '<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path><path d="M15 5.764v15"></path><path d="M9 3.236v15"></path>',
        "map-pin" =>
            '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle>',
        "settings" =>
            '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle>',
        "wrench" =>
            '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>',
        "camera" =>
            '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path><circle cx="12" cy="13" r="3"></circle>',
        "clock" =>
            '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        "globe" =>
            '<circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path>',
        "phone" =>
            '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>',
        "star" =>
            '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>',
        "zap" =>
            '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>',
        "bot" =>
            '<path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path>',
        "chart" =>
            '<path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path>',
        "rocket" =>
            '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path>',
        "search" =>
            '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
        "pencil" =>
            '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path>',
        "dollar" =>
            '<line x1="12" x2="12" y1="2" y2="22"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
        "download" =>
            '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line>',
        "activity" =>
            '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>',
    ];

    return $icons[$icon] ?? "";
}

function lds_get_inline_svg_icon($icon, $class = "")
{
    $raw = lds_get_svg_icon_raw($icon);
    if ($raw === "") {
        return "";
    }

    $class = trim("lds-inline-icon " . $class);

    return '<span class="' .
        esc_attr($class) .
        '" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' .
        $raw .
        "</svg></span>";
}

/**
 * Render a unified notice/callout box with a leading SVG icon.
 *
 * @param string $content HTML content (already escaped/trusted by caller).
 * @param string $type    One of: info, info-subtle, success, warning, danger, neutral.
 * @param string $icon    Icon name from lds_get_svg_icon_raw(). Defaults per type when empty.
 * @param string $class   Optional extra CSS classes.
 * @return string
 */
function lds_render_notice($content, $type = "info", $icon = "", $class = "")
{
    $default_icons = [
        "info" => "info",
        "info-subtle" => "info",
        "success" => "check",
        "warning" => "alert",
        "danger" => "alert",
        "neutral" => "info",
    ];

    if ($icon === "") {
        $icon = $default_icons[$type] ?? "info";
    }

    $raw = lds_get_svg_icon_raw($icon);
    $classes = trim("lds-callout lds-callout--" . $type . " " . $class);

    return '<div class="' .
        esc_attr($classes) .
        '"><span class="lds-callout__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">' .
        $raw .
        '</svg></span><div class="lds-callout__content">' .
        $content .
        "</div></div>";
}

// Let's get this party started!
listeo_data_scraper_run();

/**
 * Helper function for logging debug information to a file.
 * This function will only write to the log if Debug Mode is enabled in the plugin settings.
 *
 * @param mixed $data The data to log. Can be a string, array, or object.
 * @param string $context A descriptive label for the log entry.
 * @param string $level The severity level of the log.
 */
function lds_log($data, $context = "GENERAL", $level = "INFO")
{
    // --- THE CORE OF THE UPGRADE IS HERE ---
    // First, check if our debug mode setting is enabled. If not, stop immediately.
    $debug_mode_enabled = (bool) get_option("lds_enable_debug_mode", 0);
    if (!$debug_mode_enabled) {
        return; // Exit the function silently.
    }
    // Also, respect the main WordPress debugging constant as a fallback.
    if (!defined("WP_DEBUG") || !WP_DEBUG) {
        return;
    }

    // --- The rest of the function is the same as before ---
    $log_entry = [
        "timestamp" => current_time("Y-m-d H:i:s"),
        "context" => $context,
        "level" => $level,
        "memory_usage" => size_format(memory_get_usage(true)),
        "data" => $data,
    ];

    if (is_array($data) || is_object($data)) {
        $data_string = print_r($data, true);
    } else {
        $data_string = (string) $data;
    }

    $log_message = sprintf(
        "[%s] [%s] [%s] Memory: %s\n%s\n%s\n",
        $log_entry["timestamp"],
        $log_entry["level"],
        $log_entry["context"],
        $log_entry["memory_usage"],
        $data_string,
        str_repeat("-", 80),
    );

    // Write to the debug log file
    error_log($log_message, 3, WP_CONTENT_DIR . "/debug-lds.log");
}

/**
 * Get gallery data for a listing, preferring local gallery over Google
 *
 * @param int $post_id The listing ID
 * @return array Gallery data with source information
 */
function lds_get_listing_gallery($post_id)
{
    // First, check for standard WordPress gallery
    $gallery = get_post_meta($post_id, "_gallery", true);

    if (!empty($gallery) && is_array($gallery)) {
        // Return standard gallery data
        $gallery_data = [];
        foreach ($gallery as $attachment_id => $url) {
            // Use attachment ID to get proper image URL instead of stored URL
            $image_url = wp_get_attachment_image_url(
                $attachment_id,
                "listeo-gallery",
            );
            if ($image_url) {
                $gallery_data[] = [
                    "url" => $image_url, // Use fresh URL from attachment ID
                    "id" => $attachment_id,
                    "source" => "wordpress",
                    "attribution" => "",
                ];
            }
        }
        return $gallery_data;
    }

    // If no standard gallery, check for Google gallery
    $google_gallery = get_post_meta($post_id, "_gallery_google", true);

    if (!empty($google_gallery) && is_array($google_gallery)) {
        $gallery_data = [];
        foreach ($google_gallery as $photo) {
            $gallery_data[] = [
                "url" => $photo["url"],
                "id" => null,
                "source" => "google",
                "attribution" => $photo["attribution"],
            ];
        }
        return $gallery_data;
    }

    // No gallery found
    return [];
}

/**
 * Check if listing is using Google photos
 */
function lds_is_using_google_photos($post_id)
{
    $gallery = get_post_meta($post_id, "_gallery", true);
    if (!empty($gallery)) {
        return false;
    }

    $google_gallery = get_post_meta($post_id, "_gallery_google", true);
    return !empty($google_gallery);
}
