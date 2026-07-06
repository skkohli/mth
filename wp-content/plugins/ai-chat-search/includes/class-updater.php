<?php
/**
 * Plugin Updater Class
 *
 * Handles self-hosted plugin updates for AI Chat & Search (Free Version)
 * Checks for updates every 24 hours from purethemes.net
 *
 * @package Listeo_AI_Search
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Updater {

    /**
     * Update server URL (JSON manifest)
     */
    private $update_url = 'https://purethemes.net/license/plugins/ai-chat-search.json';

    /**
     * Plugin slug
     */
    private $plugin_slug = 'ai-chat-search';

    /**
     * Plugin file (relative path)
     */
    private $plugin_file = 'ai-chat-search/ai-chat-search.php';

    /**
     * Cache key for update check
     */
    private $cache_key = 'ai_chat_search_update_data';

    /**
     * Cache interval (24 hours)
     */
    private $cache_interval = DAY_IN_SECONDS;

    /**
     * Current plugin version
     */
    private $current_version;

    /**
     * Constructor
     */
    public function __construct() {
        $this->current_version = LISTEO_AI_SEARCH_VERSION;

        // Allow filtering the update URL (for testing)
        $this->update_url = apply_filters('ai_chat_search_update_url', $this->update_url);

        // Allow filtering the check interval
        $this->cache_interval = apply_filters('ai_chat_search_update_check_interval', $this->cache_interval);

        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);

        // Add action link to view update details
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);

        // Manual update check (bypass cache)
        add_action('wp_ajax_ai_chat_search_check_update_now', array($this, 'manual_update_check'));
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Check cache first
        $update_data = get_transient($this->cache_key);

        if ($update_data === false) {
            // Cache expired or doesn't exist - fetch fresh data
            $update_data = $this->fetch_update_info();

            if ($update_data !== false) {
                // Cache the result for 24 hours
                set_transient($this->cache_key, $update_data, $this->cache_interval);
            }
        }

        // If update data exists and version is newer
        if ($update_data && isset($update_data->new_version) && version_compare($this->current_version, $update_data->new_version, '<')) {
            $transient->response[$this->plugin_file] = $update_data;
        } else {
            // Explicitly mark as no update available
            $transient->no_update[$this->plugin_file] = $update_data ?: $this->get_no_update_object();
        }

        return $transient;
    }

    /**
     * Fetch update information from server
     *
     * @return object|false Update data object or false on failure
     */
    private function fetch_update_info() {
        $response = wp_remote_get($this->update_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            // Log error if debug mode enabled
            if (get_option('listeo_ai_search_debug_mode', false)) {
                Listeo_AI_Search::debug_log('Update check failed: ' . $response->get_error_message(), 'error');
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || !isset($data->version)) {
            return false;
        }

        // Build update object
        $update_data = (object) array(
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_file,
            'new_version' => $data->version,
            'url' => isset($data->homepage) ? $data->homepage : 'https://purethemes.net/ai-chat-search/',
            'package' => $data->download_url,
            'tested' => isset($data->tested) ? $data->tested : '',
            'requires_php' => isset($data->requires_php) ? $data->requires_php : '7.4',
            'requires' => isset($data->requires) ? $data->requires : '5.0',
            'last_updated' => isset($data->last_updated) ? $data->last_updated : '',
            'sections' => array(
                'description' => isset($data->sections->description) ? $data->sections->description : '',
                'changelog' => isset($data->sections->changelog) ? $data->sections->changelog : ''
            ),
            'banners' => array(
                'low' => isset($data->banners->low) ? $data->banners->low : '',
                'high' => isset($data->banners->high) ? $data->banners->high : ''
            ),
            'icons' => array(
                '1x' => isset($data->icons->{'1x'}) ? $data->icons->{'1x'} : '',
                '2x' => isset($data->icons->{'2x'}) ? $data->icons->{'2x'} : ''
            )
        );

        return $update_data;
    }

    /**
     * Get "no update" object for current version
     *
     * @return object No update object
     */
    private function get_no_update_object() {
        return (object) array(
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_file,
            'new_version' => $this->current_version,
            'url' => 'https://purethemes.net/ai-chat-search/',
            'package' => '',
            'tested' => '',
            'requires_php' => '7.4',
            'requires' => '5.0'
        );
    }

    /**
     * Provide plugin information for "View Details" link
     *
     * @param false|object|array $result The result object or array
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return false|object Modified result
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // Get cached update data
        $update_data = get_transient($this->cache_key);

        if ($update_data === false) {
            $update_data = $this->fetch_update_info();
        }

        if ($update_data) {
            return (object) array(
                'slug' => $this->plugin_slug,
                'name' => 'PurioChat',
                'version' => $update_data->new_version,
                'author' => '<a href="https://purethemes.net">PureThemes</a>',
                'homepage' => $update_data->url,
                'requires' => $update_data->requires,
                'tested' => $update_data->tested,
                'requires_php' => $update_data->requires_php,
                'download_link' => $update_data->package,
                'sections' => $update_data->sections,
                'banners' => $update_data->banners,
                'icons' => $update_data->icons,
                'last_updated' => $update_data->last_updated
            );
        }

        return $result;
    }

    /**
     * Add plugin row meta links
     *
     * @param array $links Existing links
     * @param string $file Plugin file
     * @return array Modified links
     */
    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_file) {
            $links[] = '<a href="https://purethemes.net/ai-chat-search/" target="_blank">' . __('View Details', 'ai-chat-search') . '</a>';
        }
        return $links;
    }

    /**
     * Manual update check (AJAX handler)
     * Bypasses cache and forces fresh check
     */
    public function manual_update_check() {
        check_ajax_referer('listeo_ai_search_nonce', 'nonce');

        if (!current_user_can('update_plugins')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-chat-search')));
        }

        // Delete cache to force fresh check
        delete_transient($this->cache_key);

        // Clear WordPress plugin cache
        wp_clean_plugins_cache();

        // Fetch fresh update info
        $update_data = $this->fetch_update_info();

        if ($update_data === false) {
            wp_send_json_error(array('message' => __('Failed to check for updates. Please try again later.', 'ai-chat-search')));
        }

        // Cache the fresh data
        set_transient($this->cache_key, $update_data, $this->cache_interval);

        // Check if update is available
        $update_available = version_compare($this->current_version, $update_data->new_version, '<');

        wp_send_json_success(array(
            'update_available' => $update_available,
            'current_version' => $this->current_version,
            'latest_version' => $update_data->new_version,
            'message' => $update_available
                ? sprintf(__('Update available: %s', 'ai-chat-search'), $update_data->new_version)
                : __('You have the latest version!', 'ai-chat-search')
        ));
    }

    /**
     * Force update check (for external use)
     *
     * @return array Update status
     */
    public static function force_check() {
        delete_transient('ai_chat_search_update_data');
        wp_clean_plugins_cache();

        $updater = new self();
        $update_data = $updater->fetch_update_info();

        if ($update_data) {
            set_transient('ai_chat_search_update_data', $update_data, DAY_IN_SECONDS);
            return array(
                'success' => true,
                'update_available' => version_compare(LISTEO_AI_SEARCH_VERSION, $update_data->new_version, '<'),
                'latest_version' => $update_data->new_version
            );
        }

        return array('success' => false, 'message' => 'Update check failed');
    }
}

// Initialize updater
new Listeo_AI_Search_Updater();
