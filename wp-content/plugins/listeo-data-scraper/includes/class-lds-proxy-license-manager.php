<?php
/**
 * Proxy License Manager for Listeo Data Scraper
 *
 * Communicates with purethemes.net proxy instead of directly with DodoPayments
 * Provides auto-deactivation and better error handling
 *
 * @package Listeo_Data_Scraper
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LDS_Proxy_License_Manager {

    /**
     * Proxy endpoint URL (primary)
     */
    const PROXY_URL = 'https://purethemes.net/wp-json/purethemes-license-proxy/v1/proxy';

    /**
     * Fallback proxy endpoint URL (used when primary is blocked by CloudFlare/WAF)
     */
    const PROXY_URL_FALLBACK = 'https://vasterad.com/plugins-licenser-proxy.php';

    /**
     * Shared secret key for request signing
     * IMPORTANT: Must match the one in proxy config.php
     */
    const SHARED_SECRET_KEY = '21727d78f2ff78a2a4e2fa85ca342c03';

    /**
     * Option names for storing license data
     */
    const OPTION_LICENSE_KEY = 'lds_license_key';
    const OPTION_LICENSE_STATUS = 'lds_license_status';
    const OPTION_LICENSE_DATA = 'lds_license_data';
    const OPTION_LICENSE_INSTANCE_ID = 'lds_license_instance_id';
    const OPTION_LAST_CHECK = 'lds_license_last_check';

    /**
     * Transient for caching license validation
     */
    const TRANSIENT_LICENSE_VALID = 'lds_license_valid';

    /**
     * Cache duration for license validation (in seconds)
     * Set to 1 week (604800 seconds = 7 days)
     */
    const CACHE_DURATION = WEEK_IN_SECONDS; // 7 days

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Clear old daily cron job if it exists (migration from daily to weekly)
        if (wp_next_scheduled('lds_daily_license_check')) {
            wp_clear_scheduled_hook('lds_daily_license_check');
        }

        // Schedule weekly license validation check
        add_action('lds_weekly_license_check', array($this, 'weekly_license_check'));

        if (!wp_next_scheduled('lds_weekly_license_check')) {
            wp_schedule_event(time(), 'weekly', 'lds_weekly_license_check');
        }

        // Validate license ONLY on plugin settings page load
        add_action('load-toplevel_page_listeo-data-scraper', array($this, 'validate_on_plugin_page_load'));

        // Add admin notices for license issues (disabled in free version)
        // add_action('admin_notices', array($this, 'license_admin_notices'));
    }

    /**
     * Activate license key via proxy
     *
     * @param string $license_key License key to activate
     * @return array Result with 'success' and 'message' keys
     */
    public function activate_license($license_key) {
        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('License key is required.', 'listeo-data-scraper')
            );
        }

        // Prepare site name
        $site_name = get_bloginfo('name') . ' (' . home_url() . ')';

        // Call proxy
        $result = $this->call_proxy('activate', array(
            'license_key' => $license_key,
            'site_name' => $site_name,
            'product_slug' => 'scraper' // Product identifier for validation
        ));

        if (isset($result['error'])) {
            $error_message = $this->get_user_friendly_license_error($result);

            // Clear any existing license data on failed activation
            // This ensures status goes back to "inactive" instead of staying "invalid"
            $this->clear_license_data();

            return array(
                'success' => false,
                'message' => $error_message
            );
        }

        if (isset($result['success']) && $result['success']) {
            // Store license data locally
            update_option(self::OPTION_LICENSE_KEY, $license_key);
            update_option(self::OPTION_LICENSE_STATUS, 'valid');
            update_option(self::OPTION_LICENSE_INSTANCE_ID, $result['instance_id']);
            update_option(self::OPTION_LAST_CHECK, time());

            // Clear validation cache and set cache
            delete_transient(self::TRANSIENT_LICENSE_VALID);
            set_transient(self::TRANSIENT_LICENSE_VALID, 1, self::CACHE_DURATION);

            return array(
                'success' => true,
                'message' => $result['message'] ?? __('License activated successfully!', 'listeo-data-scraper')
            );
        }

        return array(
            'success' => false,
            'message' => isset($result['message']) ? $result['message'] : __('Activation failed. Please try again.', 'listeo-data-scraper')
        );
    }

    /**
     * Convert proxy/license API errors into customer-facing messages.
     *
     * @param array $result Proxy result data.
     * @return string
     */
    private function get_user_friendly_license_error($result) {
        $error_code = isset($result['data']['code'])
            ? sanitize_key($result['data']['code'])
            : sanitize_key($result['error'] ?? '');

        if (defined('WP_DEBUG') && WP_DEBUG && !empty($result['debug_info'])) {
            error_log('LDS license activation debug: ' . $result['debug_info']);
        }

        if ($error_code === 'license_not_found' || $error_code === 'product_mismatch') {
            return __("Invalid license key. You can't use Listeo license for this plugin.", 'listeo-data-scraper');
        }

        if ($error_code === 'activation_limit_reached') {
            return __('Activation limit reached. Deactivate this license on another site first.', 'listeo-data-scraper');
        }

        if ($error_code === 'license_inactive') {
            return __('This license is inactive or expired. Please check your purchase status.', 'listeo-data-scraper');
        }

        $error_message = $result['message'] ?? $result['error'] ?? __('License activation failed. Please try again.', 'listeo-data-scraper');

        if (isset($result['data']['message'])) {
            $error_message = $result['data']['message'];
        }

        if (!empty($result['guidance'])) {
            $error_message .= ' — ' . $result['guidance'];
        }

        return $error_message;
    }

    /**
     * Deactivate license key via proxy
     *
     * @return array Result with 'success' and 'message' keys
     */
    public function deactivate_license() {
        $license_key = get_option(self::OPTION_LICENSE_KEY);
        $instance_id = get_option(self::OPTION_LICENSE_INSTANCE_ID);

        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('No license key found to deactivate.', 'listeo-data-scraper')
            );
        }

        if (empty($instance_id)) {
            // If no instance ID, just clear local data
            $this->clear_license_data();
            return array(
                'success' => true,
                'message' => __('License data cleared locally.', 'listeo-data-scraper')
            );
        }

        // Prepare site name for proper domain tracking on deactivation
        $site_name = get_bloginfo('name') . ' (' . home_url() . ')';

        // Call proxy
        $result = $this->call_proxy('deactivate', array(
            'license_key' => $license_key,
            'instance_id' => $instance_id,
            'site_name' => $site_name, // Required for proper domain-based deactivation tracking
            'product_slug' => 'scraper' // Product identifier for validation
        ));

        // Clear local data regardless of result
        $this->clear_license_data();

        if (isset($result['success']) && $result['success']) {
            return array(
                'success' => true,
                'message' => $result['message'] ?? __('License deactivated successfully.', 'listeo-data-scraper')
            );
        }

        // If there's an error, show it
        if (isset($result['error'])) {
            $error_message = $result['message'] ?? $result['error'];
            if (isset($result['data']['message'])) {
                $error_message = $result['data']['message'];
            }

            return array(
                'success' => true, // Still success because local data is cleared
                'message' => __('License deactivated locally. ', 'listeo-data-scraper') . $error_message
            );
        }

        return array(
            'success' => true, // Still success because local data is cleared
            'message' => __('License deactivated locally.', 'listeo-data-scraper')
        );
    }

    /**
     * Validate license key via proxy
     *
     * @param bool $force Force validation even if cached
     * @return bool True if valid, false otherwise
     */
    public function validate_license($force = false) {
        // Check cache first unless forced
        if (!$force) {
            $cached = get_transient(self::TRANSIENT_LICENSE_VALID);
            if ($cached !== false) {
                return (bool) $cached;
            }

            // Rebuild transient from wp_options if Redis/object cache dropped it.
            // This prevents repeated license server calls while the last check is fresh.
            $last_check = (int) get_option(self::OPTION_LAST_CHECK, 0);
            if ($last_check > 0 && (time() - $last_check) < self::CACHE_DURATION) {
                $is_valid = (get_option(self::OPTION_LICENSE_STATUS, 'invalid') === 'valid');
                set_transient(self::TRANSIENT_LICENSE_VALID, $is_valid ? 1 : 0, self::CACHE_DURATION);
                return $is_valid;
            }
        }

        $license_key = get_option(self::OPTION_LICENSE_KEY);
        $instance_id = get_option(self::OPTION_LICENSE_INSTANCE_ID);

        if (empty($license_key) || empty($instance_id)) {
            set_transient(self::TRANSIENT_LICENSE_VALID, 0, self::CACHE_DURATION);
            return false;
        }

        // Call proxy
        $result = $this->call_proxy('validate', array(
            'license_key' => $license_key,
            'instance_id' => $instance_id,
            'product_slug' => 'scraper' // Product identifier for validation
        ));

        // Update last check time
        update_option(self::OPTION_LAST_CHECK, time());

        // If there was an error (network, firewall, 403, 500, etc.), keep the existing license status
        // Don't punish users for temporary network/server issues
        if (isset($result['error'])) {
            $current_status = get_option(self::OPTION_LICENSE_STATUS, 'invalid');
            $current_valid = ($current_status === 'valid');

            // Keep existing cache if license was valid
            if ($current_valid) {
                set_transient(self::TRANSIENT_LICENSE_VALID, 1, self::CACHE_DURATION);
            } else {
                set_transient(self::TRANSIENT_LICENSE_VALID, 0, self::CACHE_DURATION);
            }

            return $current_valid;
        }

        // We got a successful response from the API
        // Check validation result from the 'valid' field
        $is_valid = isset($result['valid']) && $result['valid'] === true;

        // Update local status based on actual validation result
        update_option(self::OPTION_LICENSE_STATUS, $is_valid ? 'valid' : 'invalid');

        // Cache result
        set_transient(self::TRANSIENT_LICENSE_VALID, $is_valid ? 1 : 0, self::CACHE_DURATION);

        return $is_valid;
    }

    /**
     * Check if license is currently valid
     *
     * @return bool True if valid
     */
    public function is_license_valid() {
        return $this->validate_license(false);
    }

    /**
     * Call proxy endpoint with automatic fallback
     *
     * @param string $action Action to perform (activate, validate, deactivate)
     * @param array $data Data to send
     * @return array Response data
     */
    private function call_proxy($action, $data) {
        $debug_mode = defined('WP_DEBUG') && WP_DEBUG;

        // Try primary endpoint first
        if ($debug_mode) {
            error_log('=== LDS LICENSE PROXY: Trying primary endpoint ===');
        }

        $result = $this->make_proxy_request(self::PROXY_URL, $action, $data);

        // Check if we should try fallback (403 with HTML = CloudFlare block)
        if ($this->should_use_fallback($result)) {
            if ($debug_mode) {
                error_log('=== LDS LICENSE PROXY: Primary blocked, trying fallback ===');
            }

            $fallback_result = $this->make_proxy_request(self::PROXY_URL_FALLBACK, $action, $data);

            // If fallback succeeded, return it
            if (!isset($fallback_result['error'])) {
                if ($debug_mode) {
                    error_log('=== LDS LICENSE PROXY: Fallback succeeded ===');
                }
                return $fallback_result;
            }

            // If fallback also failed, return the original error with note about fallback
            if ($debug_mode) {
                error_log('=== LDS LICENSE PROXY: Fallback also failed ===');
            }
            $result['message'] .= ' ' . __('(Fallback proxy also failed)', 'listeo-data-scraper');
        }

        return $result;
    }

    /**
     * Check if we should try the fallback endpoint
     *
     * @param array $result Result from primary endpoint
     * @return bool True if fallback should be attempted
     */
    private function should_use_fallback($result) {
        // No error = success, no need for fallback
        if (!isset($result['error'])) {
            return false;
        }

        // 403 with HTML response = CloudFlare/WAF block
        if (isset($result['http_code']) && $result['http_code'] === 403) {
            if (isset($result['guidance']) && strpos($result['guidance'], 'HTML') !== false) {
                return true;
            }
        }

        // Connection errors might also benefit from fallback
        if (isset($result['error']) && $result['error'] === 'connection_error') {
            return true;
        }

        return false;
    }

    /**
     * Make actual HTTP request to proxy endpoint
     *
     * @param string $url Endpoint URL
     * @param string $action Action to perform
     * @param array $data Data to send
     * @return array Response data
     */
    private function make_proxy_request($url, $action, $data) {
        $timestamp = time();
        $debug_mode = defined('WP_DEBUG') && WP_DEBUG;

        $payload = wp_json_encode(array(
            'action' => $action,
            'data' => $data,
            'timestamp' => $timestamp,
            'site_url' => home_url()
        ));

        // Generate HMAC signature
        $signature = hash_hmac('sha256', $payload, self::SHARED_SECRET_KEY);

        if ($debug_mode) {
            error_log('=== LDS LICENSE PROXY REQUEST ===');
            error_log('Action: ' . $action);
            error_log('URL: ' . $url);
            error_log('Site URL: ' . home_url());
        }

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp
            ),
            'body' => $payload,
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();

            if ($debug_mode) {
                error_log('=== LDS LICENSE PROXY WP_ERROR ===');
                error_log('Error Code: ' . $error_code);
                error_log('Error Message: ' . $error_message);
            }

            // Build user-friendly error message with diagnostic info
            $user_message = $error_message;
            $guidance = '';

            // Add specific guidance based on error type
            if (strpos($error_code, 'ssl') !== false || stripos($error_message, 'SSL') !== false || stripos($error_message, 'certificate') !== false) {
                $guidance = __('SSL/Certificate issue - your server may not trust our certificate or has outdated CA certificates.', 'listeo-data-scraper');
            } elseif (strpos($error_code, 'timeout') !== false || stripos($error_message, 'timed out') !== false) {
                $guidance = __('Connection timeout - our server may be temporarily unavailable or your firewall is blocking the connection.', 'listeo-data-scraper');
            } elseif (stripos($error_message, 'resolve') !== false || stripos($error_message, 'getaddrinfo') !== false) {
                $guidance = __('DNS resolution failed - your server cannot resolve the license server domain.', 'listeo-data-scraper');
            } elseif (stripos($error_message, 'Connection refused') !== false) {
                $guidance = __('Connection refused - outgoing connections may be blocked by your hosting firewall.', 'listeo-data-scraper');
            } elseif (stripos($error_message, 'cURL') !== false) {
                $guidance = __('cURL error - check your server connectivity and PHP cURL extension.', 'listeo-data-scraper');
            }

            return array(
                'error' => 'connection_error',
                'error_code' => $error_code,
                'message' => $user_message,
                'guidance' => $guidance,
                'debug_info' => sprintf('WP_Error [%s]: %s | URL: %s | Site: %s', $error_code, $error_message, $url, home_url()),
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_message = wp_remote_retrieve_response_message($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $response_data = json_decode($body, true);
        $json_error = json_last_error();

        if ($debug_mode) {
            error_log('=== LDS LICENSE PROXY RESPONSE ===');
            error_log('HTTP Code: ' . $response_code . ' ' . $response_message);
            error_log('Response Body (first 500 chars): ' . substr($body, 0, 500));
            if ($json_error !== JSON_ERROR_NONE) {
                error_log('JSON Parse Error: ' . json_last_error_msg());
            }
        }

        if ($response_code !== 200) {
            // Build detailed error message
            $error_message = sprintf('HTTP %d %s', $response_code, $response_message);

            // Add more specific info from response if available
            if (isset($response_data['message'])) {
                $error_message = $response_data['message'];
            } elseif (isset($response_data['error'])) {
                $error_message = $response_data['error'];
            }

            // Add guidance based on HTTP code
            $guidance = '';
            if ($response_code === 403) {
                $guidance = __('Access denied - signature verification may have failed or request was blocked.', 'listeo-data-scraper');
            } elseif ($response_code === 404) {
                $guidance = __('Endpoint not found - license server may be misconfigured.', 'listeo-data-scraper');
            } elseif ($response_code === 500) {
                $guidance = __('Server error on license server - please try again later.', 'listeo-data-scraper');
            } elseif ($response_code === 502 || $response_code === 503 || $response_code === 504) {
                $guidance = __('License server temporarily unavailable - please try again in a few minutes.', 'listeo-data-scraper');
            } elseif ($response_code === 429) {
                $guidance = __('Too many requests - please wait a moment and try again.', 'listeo-data-scraper');
            }

            // Check if response is HTML (possible WAF/firewall block)
            $content_type = isset($headers['content-type']) ? $headers['content-type'] : '';
            if (is_array($content_type)) {
                $content_type = implode(', ', $content_type);
            }
            if (strpos($content_type, 'text/html') !== false || strpos($body, '<html') !== false || strpos($body, '<!DOCTYPE') !== false) {
                $guidance = __('Received HTML instead of JSON - request may be blocked by a firewall, WAF, or security plugin.', 'listeo-data-scraper');
            }

            return array(
                'error' => $response_data['code'] ?? 'request_failed',
                'message' => $error_message,
                'guidance' => $guidance,
                'data' => $response_data,
                'http_code' => $response_code,
                'debug_info' => sprintf('HTTP %d %s | URL: %s | Body: %s | Site: %s', $response_code, $response_message, $url, substr($body, 0, 100), home_url()),
            );
        }

        // Check for JSON parse errors even on 200 response
        if ($json_error !== JSON_ERROR_NONE) {
            return array(
                'error' => 'json_parse_error',
                'message' => __('Invalid JSON response from license server', 'listeo-data-scraper'),
                'guidance' => __('The server returned a response that could not be parsed. This may indicate a server-side issue.', 'listeo-data-scraper'),
                'debug_info' => sprintf('JSON error: %s | URL: %s | Body: %s', json_last_error_msg(), $url, substr($body, 0, 150)),
            );
        }

        return $response_data ?: array('error' => 'invalid_response', 'message' => 'Invalid response from proxy');
    }

    /**
     * Get license key (unmasked - for internal use)
     *
     * @return string License key or empty string
     */
    public function get_license_key() {
        return get_option(self::OPTION_LICENSE_KEY, '');
    }

    /**
     * Get instance ID
     *
     * @return string Instance ID or empty string
     */
    public function get_instance_id() {
        return get_option(self::OPTION_LICENSE_INSTANCE_ID, '');
    }

    /**
     * Get license key (masked)
     *
     * @return string Masked license key or empty string
     */
    public function get_license_key_masked() {
        $license_key = get_option(self::OPTION_LICENSE_KEY);

        if (empty($license_key)) {
            return '';
        }

        if (strlen($license_key) > 12) {
            return substr($license_key, 0, 8) . str_repeat('*', 8) . substr($license_key, -4);
        }

        return substr($license_key, 0, 4) . str_repeat('*', strlen($license_key) - 4);
    }

    /**
     * Get license status
     *
     * @return string Status: 'valid', 'invalid', or 'inactive'
     */
    public function get_license_status() {
        $license_key = get_option(self::OPTION_LICENSE_KEY);

        if (empty($license_key)) {
            return 'inactive';
        }

        return get_option(self::OPTION_LICENSE_STATUS, 'invalid');
    }

    /**
     * Get last check timestamp
     *
     * @return int Timestamp or 0 if never checked
     */
    public function get_last_check_time() {
        return (int) get_option(self::OPTION_LAST_CHECK, 0);
    }

    /**
     * Clear all license data
     */
    private function clear_license_data() {
        delete_option(self::OPTION_LICENSE_KEY);
        delete_option(self::OPTION_LICENSE_STATUS);
        delete_option(self::OPTION_LICENSE_DATA);
        delete_option(self::OPTION_LICENSE_INSTANCE_ID);
        delete_transient(self::TRANSIENT_LICENSE_VALID);
    }

    /**
     * Weekly license check (cron job)
     */
    public function weekly_license_check() {
        $this->validate_license(true);
    }

    /**
     * Validate license on plugin page load
     * This runs ONLY when the plugin's admin page is accessed
     * Uses cached validation to avoid performance impact
     */
    public function validate_on_plugin_page_load() {
        // Only validate if we have a license key
        $license_key = get_option(self::OPTION_LICENSE_KEY);
        if (empty($license_key)) {
            return;
        }

        // Use cached validation (respects CACHE_DURATION constant - 7 days)
        $this->validate_license(false);
    }

    /**
     * Admin notices for license issues
     */
    public function license_admin_notices() {
        // Only show on plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'listeo-data-scraper') === false) {
            return;
        }

        $status = $this->get_license_status();

        if ($status === 'inactive') {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Listeo Data Scraper Pro:', 'listeo-data-scraper'); ?></strong>
                    <?php _e('Please activate your license key to unlock Pro features.', 'listeo-data-scraper'); ?>
                    <a href="<?php echo admin_url('admin.php?page=listeo-data-scraper&tab=license'); ?>">
                        <?php _e('Activate License', 'listeo-data-scraper'); ?>
                    </a>
                </p>
            </div>
            <?php
        } elseif ($status === 'invalid') {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Listeo Data Scraper Pro:', 'listeo-data-scraper'); ?></strong>
                    <?php _e('Your license is invalid or has expired. Please check your license status.', 'listeo-data-scraper'); ?>
                    <a href="<?php echo admin_url('admin.php?page=listeo-data-scraper&tab=license'); ?>">
                        <?php _e('Check License', 'listeo-data-scraper'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Get license data (minimal - most data is on proxy/DodoPayments)
     *
     * @return array License data
     */
    public function get_license_data() {
        return array(
            'product' => array('name' => 'Listeo Data Scraper Pro'),
            'customer' => array('email' => ''), // Not stored locally
            'created_at' => '' // Not stored locally
        );
    }
}
