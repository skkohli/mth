<?php
/**
 * Google Reviews API Gateway with Smart Rate Limiting
 *
 * Optional gateway system for Google Places API calls to prevent excessive charges
 * Features intelligent caching, rate limiting, and usage monitoring
 *
 * @package Listeo_Core
 * @since 1.9.51
 */

if (!defined('ABSPATH')) {
    exit;
}

class Listeo_Core_Google_Reviews_Gateway {

    /**
     * Singleton instance
     */
    private static $_instance = null;

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Gateway enabled flag
     */
    private $enabled = false;

    /**
     * Default rate limits
     */
    const DEFAULT_LIMITS = array(
        'per_hour' => 20,
        'per_day' => 500,
        'burst_protection' => 5, // Max API calls in 3 seconds (not cache, not blocked)
    );

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'listeo_google_api_calls';

        // Check if gateway is enabled (enabled by default)
        $enabled_option = get_option('listeo_google_reviews_gateway_enabled');
        if ($enabled_option === false) {
            // Option not set, enable by default
            $this->enabled = true;
            update_option('listeo_google_reviews_gateway_enabled', 'on');
        } else {
            $this->enabled = ($enabled_option === 'on' || $enabled_option === true || $enabled_option === '1');
        }

        if ($this->enabled) {
            $this->init();
        }

        // Always ensure table exists even if gateway is disabled
        add_action('init', array($this, 'maybe_create_table'));

        // Force table creation immediately if it doesn't exist
        $this->maybe_create_table();

        // Set default rate limits if not set
        if (get_option('listeo_google_limit_per_hour') === false) {
            update_option('listeo_google_limit_per_hour', '20');
        }
        if (get_option('listeo_google_limit_per_day') === false) {
            update_option('listeo_google_limit_per_day', '500');
        }

        // Set default bot protection setting (enabled by default)
        if (get_option('listeo_google_bot_protection_enabled') === false) {
            update_option('listeo_google_bot_protection_enabled', 'on');
        }

        // Add admin hooks for notifications and dashboard widget
        if (is_admin()) {
            add_action('admin_notices', array($this, 'display_rate_limit_notices'));
            if ($this->enabled) {
                add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
            }
        }
    }

    /**
     * Initialize gateway
     */
    private function init() {
        // Create database table
        add_action('init', array($this, 'maybe_create_table'));

        // Schedule cleanup
        if (!wp_next_scheduled('listeo_cleanup_api_logs')) {
            wp_schedule_event(time(), 'daily', 'listeo_cleanup_api_logs');
        }
        add_action('listeo_cleanup_api_logs', array($this, 'cleanup_old_logs'));

        // Add admin notices for rate limit warnings
        add_action('admin_notices', array($this, 'display_rate_limit_notices'));
    }

    /**
     * Create database table for tracking
     */
    public function maybe_create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            listing_id bigint(20) DEFAULT NULL,
            place_id varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            call_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cached tinyint(1) DEFAULT 0,
            blocked tinyint(1) DEFAULT 0,
            response_status varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_call_time (call_time),
            KEY idx_listing_id (listing_id),
            KEY idx_ip_address (ip_address),
            KEY idx_response_status (response_status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        // Store version for future migrations
        update_option('listeo_google_gateway_db_version', '1.0');
    }

    /**
     * Main gateway method - checks if API call should proceed
     */
    public function should_allow_api_call($listing_id = null, $place_id = null) {
        if (!$this->enabled) {
            return true; // Gateway disabled, allow all calls
        }

        // DEBUG MODE
        $debug = (defined('LISTEO_GOOGLE_DEBUG') && LISTEO_GOOGLE_DEBUG) || isset($_GET['google_debug']);

        // Bot protection check (fastest check first)
        if ($this->is_bot_protection_enabled() && $this->is_bot_request()) {
            if ($debug) error_log("  Gateway: Blocked by BOT protection");
            $this->log_blocked_call($listing_id, $place_id, 'bot_blocked');
            return false;
        }

        // Check various rate limits
        if ($this->is_rate_limited()) {
            if ($debug) error_log("  Gateway: Blocked by RATE LIMIT (hourly/daily)");
            $this->log_blocked_call($listing_id, $place_id, 'rate_limit');
            return false;
        }

        // Check IP-based limits for non-logged-in users
        if (!is_user_logged_in() && $this->is_ip_limited()) {
            if ($debug) error_log("  Gateway: Blocked by IP LIMIT (non-logged user)");
            $this->log_blocked_call($listing_id, $place_id, 'ip_limit');
            return false;
        }

        return true;
    }

    /**
     * Log successful API call
     */
    public function log_api_call($listing_id = null, $place_id = null, $cached = false, $status = 'success') {
        if (!$this->enabled) {
            return;
        }

        global $wpdb;

        $wpdb->insert(
            $this->table_name,
            array(
                'listing_id' => $listing_id,
                'place_id' => $place_id,
                'ip_address' => $this->get_client_ip(),
                'user_id' => get_current_user_id() ?: null,
                'call_time' => current_time('mysql'),
                'cached' => $cached ? 1 : 0,
                'blocked' => 0,
                'response_status' => $status,
            ),
            array('%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
        );
    }

    /**
     * Log blocked API call
     */
    private function log_blocked_call($listing_id, $place_id, $reason) {
        // For bot blocks, we don't need to store detailed data - just silently block
        if ($reason === 'bot_blocked') {
            // Trigger action for potential logging by other systems if needed
            do_action('listeo_google_api_blocked', $reason, $listing_id);
            return;
        }

        // Only log rate limiting blocks (not bot blocks) for statistics
        global $wpdb;

        $wpdb->insert(
            $this->table_name,
            array(
                'listing_id' => $listing_id,
                'place_id' => $place_id,
                'ip_address' => $this->get_client_ip(),
                'user_id' => get_current_user_id() ?: null,
                'call_time' => current_time('mysql'),
                'cached' => 0,
                'blocked' => 1,
                'response_status' => 'blocked_' . $reason,
            ),
            array('%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
        );

        // Trigger action for notifications
        do_action('listeo_google_api_blocked', $reason, $listing_id);
    }

    /**
     * Check if rate limited
     */
    private function is_rate_limited() {
        global $wpdb;

        // DEBUG MODE
        $debug = (defined('LISTEO_GOOGLE_DEBUG') && LISTEO_GOOGLE_DEBUG) || isset($_GET['google_debug']);

        $limits = $this->get_rate_limits();

        // Check hourly limit
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $hour_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE call_time > %s AND blocked = 0 AND cached = 0",
            $hour_ago
        ));

        if ($debug) error_log("  Rate check - Hourly: {$hour_count}/{$limits['per_hour']}");

        if ($hour_count >= $limits['per_hour']) {
            $this->maybe_send_alert('per_hour', $hour_count, $limits['per_hour']);
            return true;
        }

        // Check daily limit
        $day_ago = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $day_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE call_time > %s AND blocked = 0 AND cached = 0",
            $day_ago
        ));

        if ($debug) error_log("  Rate check - Daily: {$day_count}/{$limits['per_day']}");

        if ($day_count >= $limits['per_day']) {
            $this->maybe_send_alert('per_day', $day_count, $limits['per_day']);
            return true;
        }

        return false;
    }

    /**
     * Check burst protection
     */
    private function is_burst_limited() {
        global $wpdb;

        // DEBUG MODE
        $debug = (defined('LISTEO_GOOGLE_DEBUG') && LISTEO_GOOGLE_DEBUG) || isset($_GET['google_debug']);

        $burst_limit = get_option('listeo_google_burst_limit', self::DEFAULT_LIMITS['burst_protection']);
        $three_seconds_ago = date('Y-m-d H:i:s', strtotime('-3 seconds'));

        // Only count actual API calls (not cache hits, not blocked calls)
        $burst_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE call_time > %s AND blocked = 0 AND cached = 0",
            $three_seconds_ago
        ));

        if ($debug) error_log("  Burst check: {$burst_count}/{$burst_limit} API calls in last 3 seconds");

        return $burst_count >= $burst_limit;
    }

    /**
     * Check IP-based limits for non-logged users
     */
    private function is_ip_limited() {
        global $wpdb;

        $ip = $this->get_client_ip();
        $ip_limit_per_hour = get_option('listeo_google_ip_limit_per_hour', 20);

        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $ip_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE call_time > %s AND ip_address = %s AND blocked = 0",
            $hour_ago,
            $ip
        ));

        return $ip_count >= $ip_limit_per_hour;
    }

    /**
     * Get configured rate limits
     */
    private function get_rate_limits() {
        return array(
            'per_hour' => get_option('listeo_google_limit_per_hour', self::DEFAULT_LIMITS['per_hour']),
            'per_day' => get_option('listeo_google_limit_per_day', self::DEFAULT_LIMITS['per_day']),
        );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Check if bot protection is enabled
     */
    private function is_bot_protection_enabled() {
        return get_option('listeo_google_bot_protection_enabled') === 'on';
    }

    /**
     * Comprehensive bot detection method
     * Checks user agent against extensive list of known bots and crawlers
     */
    private function is_bot_request() {
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Return early if no user agent (treat empty UA as bot)
        if (empty($user_agent)) {
            return true;
        }

        // Comprehensive list of bot patterns to detect
        $bot_patterns = array(
            // Search Engine Crawlers
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'applebot', 'petalbot', 'bytespider',

            // SEO & Marketing Bots
            'semrushbot', 'ahrefsbot', 'mj12bot', 'dotbot', 'rogerbot',
            'screaming frog', 'seobilitybot', 'sistrixcrawler',
            'serpstatbot', 'dataforseobots', 'backlinko',

            // Monitoring & Analytics Bots
            'uptimerobot', 'pingdom', 'gtmetrix', 'site24x7', 'newrelicpinger',
            'monitis', 'keynote', 'alertsite', 'gomezagent',

            // AI & Language Model Bots
            'barkrowlerbot', 'ccbot', 'anthropic', 'gptbot', 'chatgpt',
            'claudebot', 'openaibot', 'aibot',

            // Archive & Research Bots
            'waybackmachine', 'ia_archiver', 'archive.org_bot',
            'heritrix', 'nutch', 'commoncrawl',

            // Security & Vulnerability Scanners
            'netsparker', 'acunetix', 'nikto', 'sqlmap', 'nmap',
            'w3af', 'skipfish', 'wapiti', 'burp',

            // Content Scrapers
            'scrapy', 'beautifulsoup', 'mechanize', 'wget', 'curl',
            'httrack', 'teleport', 'webcopier',

            // Social Media Crawlers
            'whatsapp', 'telegram', 'skypeuripreview', 'viberbot',
            'slack', 'discord', 'pinterest',

            // Generic Bot Patterns
            'bot', 'crawler', 'spider', 'scraper', 'parser',
            'fetcher', 'extractor', 'indexer', 'harvester'
        );

        // Check each pattern against user agent
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }

        // Additional checks for suspicious patterns
        if (
            // Very short user agents (less than 10 characters)
            strlen($user_agent) < 10 ||
            // User agents containing only version numbers
            preg_match('/^[\d\.\s]+$/', $user_agent) ||
            // Common bot keywords in different languages
            strpos($user_agent, 'robot') !== false ||
            strpos($user_agent, 'auto') !== false ||
            strpos($user_agent, 'crawl') !== false
        ) {
            return true;
        }

        return false;
    }

    /**
     * Send alert when limits are reached
     */
    private function maybe_send_alert($limit_type, $current_count, $limit) {
        // Store alert in transient for admin notice
        $alerts = get_transient('listeo_google_api_alerts') ?: array();
        $alerts[] = array(
            'type' => $limit_type,
            'count' => $current_count,
            'limit' => $limit,
            'time' => current_time('mysql'),
        );

        // Keep only last 5 alerts
        $alerts = array_slice($alerts, -5);
        set_transient('listeo_google_api_alerts', $alerts, DAY_IN_SECONDS);

        // Trigger action for email notifications
        do_action('listeo_google_api_limit_reached', $limit_type, $current_count, $limit);
    }

    /**
     * Display admin notices for rate limits
     */
    public function display_rate_limit_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $alerts = get_transient('listeo_google_api_alerts');
        if (!$alerts) {
            return;
        }

        foreach ($alerts as $alert) {
            $message = sprintf(
                __('Google Reviews API Rate Limit Warning: %s limit reached (%d/%d calls) at %s', 'listeo_core'),
                str_replace('_', ' ', $alert['type']),
                $alert['count'],
                $alert['limit'],
                $alert['time']
            );

            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }

        // Clear alerts after displaying
        delete_transient('listeo_google_api_alerts');
    }

    /**
     * Get usage statistics
     */
    public function get_usage_stats($period = 'day') {
        global $wpdb;

        $periods = array(
            'hour' => '-1 hour',
            'day' => '-24 hours',
            'week' => '-7 days',
            'month' => '-30 days',
        );

        $since = date('Y-m-d H:i:s', strtotime($periods[$period] ?? '-24 hours'));

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_calls,
                SUM(CASE WHEN cached = 1 THEN 1 ELSE 0 END) as cached_calls,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_calls,
                COUNT(DISTINCT listing_id) as unique_listings,
                COUNT(DISTINCT ip_address) as unique_ips
             FROM {$this->table_name}
             WHERE call_time > %s",
            $since
        ));

        // Calculate estimated cost (Google Places Details API: $0.017 per call)
        if ($stats) {
            $actual_api_calls = $stats->total_calls - $stats->cached_calls - $stats->blocked_calls;
            $stats->estimated_cost = $actual_api_calls * 0.017;
            $stats->cache_hit_rate = $stats->total_calls > 0
                ? round(($stats->cached_calls / $stats->total_calls) * 100, 2)
                : 0;
            $stats->block_rate = $stats->total_calls > 0
                ? round(($stats->blocked_calls / $stats->total_calls) * 100, 2)
                : 0;
        }

        return $stats;
    }


    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $retention_days = get_option('listeo_google_log_retention_days', 30);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE call_time < %s",
            $cutoff
        ));
    }

    /**
     * Get smart cache duration based on listing activity
     */
    public function get_smart_cache_duration($listing_id) {
        if (!$this->enabled) {
            // Return existing setting if gateway is disabled
            return get_option('listeo_google_reviews_cache_days', 1);
        }

        // Get listing metrics
        $views = (int) get_post_meta($listing_id, '_listing_views_count', true);
        $is_featured = get_post_meta($listing_id, '_featured', true);
        $is_verified = get_post_meta($listing_id, '_verified', true);
        $last_updated = get_post_meta($listing_id, '_google_last_updated', true);

        // Use existing cache setting as base
        $base_days = get_option('listeo_google_reviews_cache_days', 1);

        // Only extend cache for low-traffic listings to save API calls
        if ($is_featured || $is_verified || $views > 1000) {
            // Important/popular listings: use normal cache duration
            $cache_days = $base_days;
        } elseif ($views < 100) {
            // Low traffic: extend cache significantly
            $cache_days = $base_days * 3;
        } else {
            // Moderate traffic: slightly extend cache
            $cache_days = $base_days * 2;
        }

        // If data is very stale (>30 days), reduce cache to force refresh
        if ($last_updated && strtotime($last_updated) < strtotime('-30 days')) {
            $cache_days = max(1, min($cache_days, 3));
        }

        return max(1, $cache_days); // Minimum 1 day
    }

    /**
     * Check if gateway is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Reset all rate limit counters (admin action)
     */
    public function reset_counters() {
        global $wpdb;

        // Clear current period data
        $one_day_ago = date('Y-m-d H:i:s', strtotime('-1 day'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE call_time > %s",
            $one_day_ago
        ));

        // Clear alerts
        delete_transient('listeo_google_api_alerts');

        return true;
    }


    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if ($this->enabled) {
            wp_add_dashboard_widget(
                'listeo_google_api_widget',
                __('Google API Usage', 'listeo_core'),
                array($this, 'dashboard_widget_content')
            );
        }
    }

    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        $stats = $this->get_usage_stats('day');

        if (!$stats) {
            echo '<p>' . __('No data yet.', 'listeo_core') . '</p>';
            return;
        }

        $api_calls = $stats->total_calls - $stats->cached_calls - $stats->blocked_calls;

        ?>
        <table class="widefat">
            <tr>
                <td><?php _e('API Calls Today:', 'listeo_core'); ?></td>
                <td><strong><?php echo intval($api_calls); ?></strong></td>
            </tr>
            <tr>
                <td><?php _e('Cache Hits:', 'listeo_core'); ?></td>
                <td><strong style="color: #00a32a;"><?php echo intval($stats->cached_calls); ?></strong></td>
            </tr>
            <tr>
                <td><?php _e('Blocked:', 'listeo_core'); ?></td>
                <td><strong style="color: #d63638;"><?php echo intval($stats->blocked_calls); ?></strong></td>
            </tr>
        </table>

        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 11px; color: #666;">
            <?php _e('Bot protection:', 'listeo_core'); ?>
            <?php if ($this->is_bot_protection_enabled()): ?>
                <span style="color: #00a32a; font-weight: bold;"><?php _e('ACTIVE', 'listeo_core'); ?></span>
            <?php else: ?>
                <span style="color: #d63638; font-weight: bold;"><?php _e('DISABLED', 'listeo_core'); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Helper function to get instance
function listeo_google_reviews_gateway() {
    return Listeo_Core_Google_Reviews_Gateway::instance();
}