<?php
/**
 * Listeo Core Analytics Tracker
 *
 * Handles frontend tracking and AJAX batch processing
 *
 * @package Listeo_Core
 * @subpackage Analytics
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Core_Analytics_Tracker {

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers (always available for existing queued data)
        add_action('wp_ajax_listeo_track_batch', array($this, 'track_batch'));
        add_action('wp_ajax_nopriv_listeo_track_batch', array($this, 'track_batch'));

        // Only enqueue scripts if analytics is enabled
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_analytics_script'));
    }

    /**
     * Check if analytics is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        // Default is enabled (analytics on by default)
        return get_option('listeo_analytics_enabled', 'yes') === 'yes';
    }

    /**
     * Enqueue analytics script only if enabled and on single listing page
     *
     * NOTE: This function is DISABLED because the old stats system (class-listeo-core-stats.php)
     * now handles loading listeo.analytics.js. We don't want to load it twice!
     * The new analytics system uses a different tracking method (batch events)
     * which is not currently implemented.
     */
    public function maybe_enqueue_analytics_script() {
        // DISABLED - Script is loaded by class-listeo-core-stats.php instead
        return;

        // KILL SWITCH: Don't load anything if analytics is disabled
        if (!self::is_enabled()) {
            return;
        }

        // Only on single listing pages
        if (!is_singular('listing')) {
            return;
        }

        // Enqueue script (requires wp-util for wp.ajax)
        wp_enqueue_script(
            'listeo-analytics',
            LISTEO_CORE_URL . 'assets/js/listeo.analytics.js',
            array('jquery', 'wp-util'),
            '1.0.0',
            true
        );

        // Localize script
        wp_localize_script('listeo-analytics', 'listeoAnalytics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('listeo_analytics_nonce'),
            'listing_id' => get_the_ID(),
            'user_id' => get_current_user_id(),
            'batch_interval' => absint(get_option('listeo_analytics_batch_interval', 30)) * 1000, // Convert to milliseconds
            'enabled' => true
        ));
    }

    /**
     * Handle batch event tracking
     * Receives multiple events in one request
     */
    public function track_batch() {
        // Bot protection: Only allow legitimate browsers
        if (!$this->is_legitimate_browser()) {
            wp_send_json_error(array('message' => __('Stats tracking is only available for web browsers', 'listeo_core')));
            return;
        }

        // Check if analytics is enabled
        if (!self::is_enabled()) {
            wp_send_json_error(array('message' => __('Analytics is disabled', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!check_ajax_referer('listeo_analytics_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Invalid nonce', 'listeo_core')));
            return;
        }

        // Get events from request
        $events_json = isset($_POST['events']) ? $_POST['events'] : '';

        if (empty($events_json)) {
            wp_send_json_error(array('message' => __('No events provided', 'listeo_core')));
            return;
        }

        // Decode events
        $events = json_decode(stripslashes($events_json), true);

        if (!is_array($events) || empty($events)) {
            wp_send_json_error(array('message' => __('Invalid events data', 'listeo_core')));
            return;
        }

        // Limit batch size for safety (prevent abuse)
        $events = array_slice($events, 0, 100);

        // Process batch
        $inserted = $this->insert_events_batch($events);

        if ($inserted !== false) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d events tracked', 'listeo_core'), $inserted),
                'count' => $inserted
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to insert events', 'listeo_core')));
        }
    }

    /**
     * Bulk insert events (MUCH faster than individual inserts)
     *
     * @param array $events Array of events to insert
     * @return int|false Number of events inserted or false on failure
     */
    private function insert_events_batch($events) {
        global $wpdb;

        // Prepare values for bulk insert
        $values = array();
        $placeholders = array();

        $current_user_id = get_current_user_id();
        $session_id = $this->get_session_id();
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 500) : '';

        foreach ($events as $event) {
            // Sanitize and validate
            $listing_id = isset($event['listing_id']) ? absint($event['listing_id']) : 0;
            $event_type = isset($event['event_type']) ? sanitize_text_field($event['event_type']) : '';
            $event_category = isset($event['event_category']) ? sanitize_text_field($event['event_category']) : '';
            $event_label = isset($event['event_label']) ? sanitize_text_field($event['event_label']) : '';
            $event_value = isset($event['event_value']) ? floatval($event['event_value']) : 0;
            $referrer = isset($event['referrer']) ? esc_url_raw($event['referrer']) : '';

            // Skip invalid events
            if (empty($listing_id) || empty($event_type)) {
                continue;
            }

            // Add to values array
            array_push(
                $values,
                $listing_id,
                $event_type,
                $event_category,
                $event_label,
                $event_value,
                $current_user_id,
                $session_id,
                $ip_address,
                $user_agent,
                $referrer,
                current_time('mysql')
            );

            // Add placeholder
            $placeholders[] = "(%d, %s, %s, %s, %f, %d, %s, %s, %s, %s, %s)";
        }

        if (empty($values)) {
            return 0;
        }

        $table_name = Listeo_Core_Analytics_DB::get_table_name();

        // Build bulk INSERT query
        $query = "INSERT INTO {$table_name}
                  (listing_id, event_type, event_category, event_label, event_value,
                   user_id, session_id, ip_address, user_agent, referrer, created_at)
                  VALUES " . implode(', ', $placeholders);

        // Prepare and execute
        $prepared = $wpdb->prepare($query, $values);
        $result = $wpdb->query($prepared);

        return $result !== false ? count($placeholders) : false;
    }

    /**
     * Check if request is from a legitimate browser (whitelist approach)
     *
     * Only allows tracking from real web browsers to prevent bot inflation.
     * Uses whitelist approach: only known browser user agents are allowed.
     *
     * @since 2.0.19
     * @return bool True if legitimate browser, false otherwise
     */
    private function is_legitimate_browser() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        // Empty UA = not a browser
        if (empty($user_agent)) {
            return false;
        }

        // Too short to be a real browser (real browser UAs are typically 50+ chars)
        if (strlen($user_agent) < 40) {
            return false;
        }

        // Must start with Mozilla/5.0 or Mozilla/4.0 (nearly universal for all modern browsers)
        if (!preg_match('/^Mozilla\/[45]\.0/', $user_agent)) {
            return false;
        }

        // Must contain at least ONE of these legitimate browser/engine patterns
        $required_patterns = array(
            'AppleWebKit',   // Safari, Chrome, Edge (Chromium-based)
            'Gecko',         // Firefox
            'Chrome',        // Chrome, Edge, Opera (Chromium-based)
            'Safari',        // Safari, Chrome, Edge
            'Firefox',       // Firefox
            'Edg',           // Microsoft Edge (new)
            'Edge',          // Microsoft Edge (legacy)
            'OPR',           // Opera (new)
            'Opera',         // Opera (legacy)
        );

        $has_browser_pattern = false;
        foreach ($required_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                $has_browser_pattern = true;
                break;
            }
        }

        if (!$has_browser_pattern) {
            return false;
        }

        // Must contain platform/OS info in parentheses
        // Real browsers always have: Mozilla/5.0 (Windows NT 10.0; ...) AppleWebKit/...
        if (!preg_match('/\([^)]+\)/', $user_agent)) {
            return false;
        }

        // Block common bot keywords (even if they try to spoof browser UA)
        $bot_keywords = array('bot', 'crawl', 'spider', 'scrape', 'curl', 'wget', 'python', 'java');
        foreach ($bot_keywords as $keyword) {
            if (stripos($user_agent, $keyword) !== false) {
                return false;
            }
        }

        // Passed all checks - this is a legitimate browser!
        return true;
    }

    /**
     * Get or create session ID
     *
     * @return string
     */
    private function get_session_id() {
        if (isset($_COOKIE['listeo_session_id'])) {
            return sanitize_text_field($_COOKIE['listeo_session_id']);
        }

        // Generate new session ID
        $session_id = wp_generate_password(32, false);

        // Set cookie for 30 minutes
        setcookie('listeo_session_id', $session_id, time() + 1800, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

        return $session_id;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Handle multiple IPs (take first one)
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // Validate IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);

        return $ip ? substr($ip, 0, 45) : '0.0.0.0';
    }
}

// Initialize
Listeo_Core_Analytics_Tracker::instance();
