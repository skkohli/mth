<?php
/**
 * Listeo Core Saved Searches
 *
 * Handles saved searches functionality with email alerts
 *
 * @package Listeo_Core
 * @since 2.0.23
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Listeo_Core_Saved_Searches class
 */
class Listeo_Core_Saved_Searches {

    /**
     * The single instance of the class.
     *
     * @var self
     * @since 2.0.23
     */
    private static $_instance = null;

    /**
     * Table name for saved searches
     *
     * @var string
     */
    private $table_searches;

    /**
     * Table name for notifications
     *
     * @var string
     */
    private $table_notifications;

    /**
     * Main instance
     *
     * @return self
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
    public function __construct() {
        global $wpdb;

        $this->table_searches = $wpdb->prefix . 'listeo_core_saved_searches';
        $this->table_notifications = $wpdb->prefix . 'listeo_core_saved_search_notifications';

        // AJAX handlers
        add_action('wp_ajax_listeo_save_search', array($this, 'ajax_save_search'));
        add_action('wp_ajax_listeo_delete_saved_search', array($this, 'ajax_delete_saved_search'));
        add_action('wp_ajax_listeo_toggle_search_alerts', array($this, 'ajax_toggle_alerts'));
        add_action('wp_ajax_listeo_get_saved_searches', array($this, 'ajax_get_saved_searches'));
        add_action('wp_ajax_listeo_debug_saved_search_alerts', array($this, 'ajax_debug_alerts'));

        // Shortcode for dashboard
        add_shortcode('listeo_saved_searches', array($this, 'saved_searches_shortcode'));

        // Cron handler for email alerts
        add_action('listeo_core_check_saved_search_alerts', array($this, 'process_email_alerts'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add save search button after search form and panel wrapper
        add_action('listeo_after_search_form_fields', array($this, 'output_save_search_button'));
        add_action('listeo_after_search_panel_wrapper', array($this, 'output_save_search_button'));

        // Output modal in footer to ensure it's not inside any container
        add_action('wp_footer', array($this, 'output_save_search_modal'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on archive/search pages or dashboard (for logged-in users)
        $is_search_page = is_post_type_archive('listing') || is_tax();
        $is_dashboard = is_user_logged_in() && $this->is_dashboard_page();

        // Exclude homepage templates
        if (!$is_search_page && is_page()) {
            $template = get_page_template_slug();
            $homepage_templates = array(
                'template-home-search-map.php',
                'template-search.php',
            );
            if (in_array($template, $homepage_templates)) {
                return;
            }
            // Any other page might have listings sidebar, so allow it
            $is_search_page = true;
        }

        if (!$is_search_page && !$is_dashboard) {
            return;
        }

        wp_enqueue_script(
            'listeo-saved-searches',
            LISTEO_CORE_URL . 'assets/js/saved-searches.js',
            array('jquery'),
            '1.0.1',
            true
        );

        // Get login page URL
        $login_page = get_option('listeo_login_page');
        $login_url = $login_page ? get_permalink($login_page) : wp_login_url();

        wp_localize_script('listeo-saved-searches', 'listeoSavedSearches', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('listeo_saved_searches_nonce'),
            'login_url' => $login_url,
            'listings_url' => get_post_type_archive_link('listing'),
            'i18n' => array(
                'save_search' => __('Save Search', 'listeo_core'),
                'saving' => __('Saving...', 'listeo_core'),
                'saved' => __('Saved!', 'listeo_core'),
                'error' => __('Error saving search', 'listeo_core'),
                'delete_confirm' => __('Are you sure you want to delete this saved search?', 'listeo_core'),
                'enter_name' => __('Enter a name for this search:', 'listeo_core'),
                'name_required' => __('Please enter a name for your search.', 'listeo_core'),
                'max_reached' => __('You have reached the maximum number of saved searches.', 'listeo_core'),
            ),
        ));
    }

    /**
     * Check if current page is a dashboard page
     *
     * @return bool
     */
    private function is_dashboard_page() {
        global $post;

        if (!$post) {
            return false;
        }

        $dashboard_pages = array(
            get_option('listeo_dashboard_page'),
            get_option('listeo_saved_searches_page'),
        );

        return in_array($post->ID, array_filter($dashboard_pages));
    }

    /**
     * Output save search button after search form
     *
     * @param string $form_type The type of search form (sidebar, split, etc.)
     */
    public function output_save_search_button($form_type = '') {

     
        if(is_front_page()) {
            return;
        }
        static $already_output = false;

        // Prevent duplicate output on same page
        if ($already_output) {
            return;
        }

        if (!get_option('listeo_saved_search_alerts_enabled', true)) {
            return;
        }

        $is_logged_in = is_user_logged_in();
        $max_reached = false;

        if ($is_logged_in) {
            // Check if user has reached max saved searches
            $max_searches = get_option('listeo_max_saved_searches', 10);
            $user_search_count = $this->get_user_search_count(get_current_user_id());
            $max_reached = ($user_search_count >= $max_searches);
        }

        if ($max_reached) {
            return;
        }

        // Get current search URL and parameters
        $current_url = $this->get_current_search_url();
        $criteria = $this->get_current_search_criteria();

        $already_output = true;
        ?>
        <div class="listeo-save-search-wrapper">
            <div class="listeo-save-search-btn<?php echo !$is_logged_in ? ' requires-login' : ''; ?>"
                 data-url="<?php echo esc_attr($current_url); ?>"
                 data-criteria="<?php echo esc_attr(wp_json_encode($criteria)); ?>"
                 data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>"
                 title="<?php esc_attr_e('Save Search & Get Alerts', 'listeo_core'); ?>">
                <i class="sl sl-icon-bell"></i> <span><?php esc_html_e('Save Search', 'listeo_core'); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Output save search modal in footer
     */
    public function output_save_search_modal() {
        // Only output for logged-in users on search pages
        if (!is_user_logged_in()) {
            return;
        }

        if (!get_option('listeo_saved_search_alerts_enabled', true)) {
            return;
        }

        // Check if we're on a valid search page
        $is_search_page = is_post_type_archive('listing') || is_tax();

        // Exclude homepage templates, allow other pages (might have listings sidebar)
        if (!$is_search_page && is_page()) {
            $template = get_page_template_slug();
            $homepage_templates = array(
                'template-home-search-map.php',
                'template-search.php',
            );
            if (in_array($template, $homepage_templates)) {
                return;
            }
            // Any other page might have listings sidebar, so allow it
            $is_search_page = true;
        }

        if (!$is_search_page) {
            return;
        }

        // Get saved searches dashboard page URL
        $saved_searches_page = get_option('listeo_saved_searches_page');
        $saved_searches_url = $saved_searches_page ? get_permalink($saved_searches_page) : '#';
        ?>
        <!-- Save Search Modal -->
        <div id="save-search-dialog" class="zoom-anim-dialog mfp-hide">
            <div class="small-dialog-header">
                <h3><?php esc_html_e('Save Search', 'listeo_core'); ?></h3>
            </div>
            <div class="save-search-form">
                <p class="save-search-description">
                    <?php
                    printf(
                        /* translators: %s: link to saved searches page */
                        esc_html__('Add this search to your %s and get daily email alerts when new matching listings are published.', 'listeo_core'),
                        '<a href="' . esc_url($saved_searches_url) . '">' . esc_html__('Saved Searches', 'listeo_core') . '</a>'
                    );
                    ?>
                </p>
                <div class="form-group">
                    <label for="save-search-name"><?php esc_html_e('Search Name', 'listeo_core'); ?></label>
                    <input type="text" id="save-search-name" class="form-control" placeholder="<?php esc_attr_e('e.g. Apartments in Downtown', 'listeo_core'); ?>">
                </div>
                <div class="save-search-buttons">
                    <button type="button" class="button save-search-cancel"><?php esc_html_e('Cancel', 'listeo_core'); ?></button>
                    <button type="button" class="button save-search-submit"><?php esc_html_e('Save Search', 'listeo_core'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get current search URL
     *
     * @return string
     */
    private function get_current_search_url() {
        // Always use the listing archive URL as base to prevent 404s on custom pages
        $base_url = get_post_type_archive_link('listing');

        // Build clean URL from criteria (excludes internal params)
        $criteria = $this->get_current_search_criteria();

        if (!empty($criteria)) {
            // If there's a location search, force search_radius=0 to use text-based search
            // This prevents issues with radius/geocoding on direct URL visits
            if (!empty($criteria['location_search']) || !empty($criteria['search_location'])) {
                $criteria['search_radius'] = '0';
            }

            $base_url = add_query_arg($criteria, $base_url);
        }

        return $base_url;
    }

    /**
     * Get current search criteria from URL parameters
     *
     * @return array
     */
    private function get_current_search_criteria() {
        $criteria = array();

        // Parameters to exclude (internal/map/temporary)
        $exclude_params = array(
            'action',
            'page',
            'paged',
            'map_bounds',
            'search_by_map_move',
            'search_lat',
            'search_lng',
            'listeo_core_order',
        );

        // Common search parameters to include
        $search_params = array(
            'keyword_search', 'search_keywords', 's', 'ai_search_input',
            'location_search', 'search_location', 'search_region',
            'listing_category', 'listing_feature', 'region',
            'listing_type', '_listing_type',
            'price_min', 'price_max',
            'date_range', 'date_start', 'date_end',
            'search_radius',
            'rating-filter',
        );

        // Get all listing type categories dynamically
        $listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));
        if (is_array($listing_types)) {
            foreach ($listing_types as $type) {
                $search_params[] = $type . '_category';
            }
        }

        // Capture allowed search parameters
        foreach ($search_params as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $criteria[$param] = sanitize_text_field($_GET[$param]);
            }
        }

        // Also capture any taxonomy filters (tax-prefixed)
        $taxonomies = get_object_taxonomies('listing', 'names');
        foreach ($taxonomies as $tax) {
            // Direct taxonomy param
            if (isset($_GET[$tax]) && !empty($_GET[$tax])) {
                $criteria[$tax] = sanitize_text_field($_GET[$tax]);
            }
            // tax- prefixed param
            $tax_key = 'tax-' . $tax;
            if (isset($_GET[$tax_key]) && !empty($_GET[$tax_key])) {
                $criteria[$tax_key] = sanitize_text_field($_GET[$tax_key]);
            }
        }

        // Capture custom meta fields in search (starting with _) but exclude internal ones
        foreach ($_GET as $key => $value) {
            if (strpos($key, '_') === 0 && !empty($value) && !in_array($key, $exclude_params)) {
                // Skip range values that are just slider defaults
                if (strpos($key, '_range') !== false) {
                    continue;
                }
                $criteria[$key] = sanitize_text_field($value);
            }
        }

        return $criteria;
    }

    /**
     * AJAX handler to save a search
     */
    public function ajax_save_search() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo_saved_searches_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'listeo_core')));
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to save searches.', 'listeo_core')));
        }

        $user_id = get_current_user_id();

        // Check max searches limit
        $max_searches = get_option('listeo_max_saved_searches', 10);
        $user_search_count = $this->get_user_search_count($user_id);

        if ($user_search_count >= $max_searches) {
            wp_send_json_error(array('message' => __('You have reached the maximum number of saved searches.', 'listeo_core')));
        }

        // Get and validate data
        $search_name = isset($_POST['search_name']) ? sanitize_text_field($_POST['search_name']) : '';
        $search_url = isset($_POST['search_url']) ? esc_url_raw($_POST['search_url']) : '';
        $search_criteria = isset($_POST['search_criteria']) ? $_POST['search_criteria'] : '';

        if (empty($search_name)) {
            wp_send_json_error(array('message' => __('Please enter a name for your search.', 'listeo_core')));
        }

        if (empty($search_url)) {
            wp_send_json_error(array('message' => __('Invalid search URL.', 'listeo_core')));
        }

        // Decode and sanitize criteria
        if (is_string($search_criteria)) {
            $search_criteria = json_decode(stripslashes($search_criteria), true);
        }

        if (!is_array($search_criteria)) {
            $search_criteria = array();
        }

        // Sanitize criteria (including viewport arrays)
        $sanitized_criteria = array();
        foreach ($search_criteria as $key => $value) {
            $sanitized_key = sanitize_key($key);

            // Handle viewport arrays specially
            if ($sanitized_key === 'place_viewport' && is_array($value)) {
                $sanitized_criteria[$sanitized_key] = array_map('sanitize_text_field', $value);
            } elseif (is_array($value)) {
                // Handle other arrays
                $sanitized_criteria[$sanitized_key] = array_map('sanitize_text_field', $value);
            } else {
                // Handle scalar values
                $sanitized_criteria[$sanitized_key] = sanitize_text_field($value);
            }
        }

        // Save to database
        $search_id = $this->save_search($user_id, $search_name, $sanitized_criteria, $search_url);

        if ($search_id) {
            wp_send_json_success(array(
                'message' => __('Search saved successfully!', 'listeo_core'),
                'search_id' => $search_id,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save search. Please try again.', 'listeo_core')));
        }
    }

    /**
     * Save a search to database
     *
     * @param int    $user_id User ID
     * @param string $name Search name
     * @param array  $criteria Search criteria
     * @param string $url Search URL
     * @return int|false Insert ID or false on failure
     */
    public function save_search($user_id, $name, $criteria, $url) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_searches,
            array(
                'user_id' => absint($user_id),
                'search_name' => sanitize_text_field($name),
                'search_criteria' => wp_json_encode($criteria),
                'search_url' => esc_url_raw($url),
                'email_alerts_enabled' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * AJAX handler to delete a saved search
     */
    public function ajax_delete_saved_search() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo_saved_searches_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'listeo_core')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'listeo_core')));
        }

        $search_id = isset($_POST['search_id']) ? absint($_POST['search_id']) : 0;
        $user_id = get_current_user_id();

        if (!$search_id) {
            wp_send_json_error(array('message' => __('Invalid search ID.', 'listeo_core')));
        }

        // Delete the search (with ownership check)
        $result = $this->delete_search($search_id, $user_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Search deleted successfully.', 'listeo_core')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete search.', 'listeo_core')));
        }
    }

    /**
     * Delete a saved search
     *
     * @param int $search_id Search ID
     * @param int $user_id User ID (for ownership verification)
     * @return bool
     */
    public function delete_search($search_id, $user_id) {
        global $wpdb;

        // First, delete related notifications
        $wpdb->delete(
            $this->table_notifications,
            array('saved_search_id' => $search_id),
            array('%d')
        );

        // Then delete the search (with ownership check)
        $result = $wpdb->delete(
            $this->table_searches,
            array(
                'id' => $search_id,
                'user_id' => $user_id,
            ),
            array('%d', '%d')
        );

        return $result !== false;
    }

    /**
     * AJAX handler to toggle email alerts
     */
    public function ajax_toggle_alerts() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo_saved_searches_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'listeo_core')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'listeo_core')));
        }

        $search_id = isset($_POST['search_id']) ? absint($_POST['search_id']) : 0;
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        $user_id = get_current_user_id();

        if (!$search_id) {
            wp_send_json_error(array('message' => __('Invalid search ID.', 'listeo_core')));
        }

        $result = $this->toggle_alerts($search_id, $user_id, $enabled);

        if ($result) {
            $message = $enabled
                ? __('Email alerts enabled.', 'listeo_core')
                : __('Email alerts disabled.', 'listeo_core');
            wp_send_json_success(array('message' => $message, 'enabled' => $enabled));
        } else {
            wp_send_json_error(array('message' => __('Failed to update alerts setting.', 'listeo_core')));
        }
    }

    /**
     * Toggle email alerts for a saved search
     *
     * @param int  $search_id Search ID
     * @param int  $user_id User ID
     * @param bool $enabled Whether alerts should be enabled
     * @return bool
     */
    public function toggle_alerts($search_id, $user_id, $enabled) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_searches,
            array(
                'email_alerts_enabled' => $enabled ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ),
            array(
                'id' => $search_id,
                'user_id' => $user_id,
            ),
            array('%d', '%s'),
            array('%d', '%d')
        );

        return $result !== false;
    }

    /**
     * Get user's saved searches
     *
     * @param int $user_id User ID
     * @return array
     */
    public function get_user_searches($user_id) {
        global $wpdb;

        $searches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_searches} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        if (!$searches) {
            return array();
        }

        // Decode criteria for each search
        foreach ($searches as &$search) {
            $search['search_criteria'] = json_decode($search['search_criteria'], true);
        }

        return $searches;
    }

    /**
     * Get user's saved search count
     *
     * @param int $user_id User ID
     * @return int
     */
    public function get_user_search_count($user_id) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_searches} WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * AJAX handler to get user's saved searches
     */
    public function ajax_get_saved_searches() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo_saved_searches_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'listeo_core')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'listeo_core')));
        }

        $searches = $this->get_user_searches(get_current_user_id());
        wp_send_json_success(array('searches' => $searches));
    }

    /**
     * Debug AJAX handler to test saved search alerts
     * Call via: /wp-admin/admin-ajax.php?action=listeo_debug_saved_search_alerts
     */
    public function ajax_debug_alerts() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Admin only');
        }

        global $wpdb;
        $debug = array();

        // Check if feature enabled
        $debug['feature_enabled'] = get_option('listeo_saved_search_alerts_enabled', true);

        // Get all saved searches
        $searches = $wpdb->get_results(
            "SELECT ss.*, u.user_email, u.display_name
             FROM {$this->table_searches} ss
             JOIN {$wpdb->users} u ON ss.user_id = u.ID
             WHERE ss.email_alerts_enabled = 1",
            ARRAY_A
        );
        $debug['searches_count'] = count($searches);
        $debug['searches'] = $searches;

        // For each search, check for matches
        foreach ($searches as $search) {
            $search_debug = array(
                'id' => $search['id'],
                'name' => $search['search_name'],
                'criteria' => json_decode($search['search_criteria'], true),
                'created_at' => $search['created_at'],
                'last_email_sent' => $search['last_email_sent'],
            );

            // Check user notification setting
            $user_id = $search['user_id'];
            $email_off = get_user_meta($user_id, 'email_notifications', true);
            $search_debug['user_email_notifications_off'] = $email_off;

            // Find matches
            $matches = $this->find_new_matches($search, true); // Pass debug flag
            $search_debug['matches'] = $matches['ids'] ?? $matches;
            $search_debug['match_count'] = is_array($matches['ids'] ?? $matches) ? count($matches['ids'] ?? $matches) : 0;
            $search_debug['query_args'] = $matches['query_args'] ?? null;

            // Also check: any listings after the date without filters?
            $check_from = $search['last_email_sent'] ? $search['last_email_sent'] : $search['created_at'];
            $any_listings = get_posts(array(
                'post_type' => 'listing',
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'fields' => 'ids',
                'date_query' => array(array('after' => $check_from, 'inclusive' => false)),
            ));
            $search_debug['any_listings_after_date'] = $any_listings;
            $search_debug['check_from_date'] = $check_from;

            $debug['search_results'][] = $search_debug;
        }

        wp_send_json_success($debug);
    }

    /**
     * Saved searches shortcode for dashboard
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function saved_searches_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You need to be signed in to manage your saved searches.', 'listeo_core') . '</p>';
        }

        ob_start();

        $template_loader = new Listeo_Core_Template_Loader;
        $searches = $this->get_user_searches(get_current_user_id());
        $max_searches = get_option('listeo_max_saved_searches', 10);

        $template_loader->set_template_data(array(
            'searches' => $searches,
            'max_searches' => $max_searches,
        ))->get_template_part('account/saved-searches');

        return ob_get_clean();
    }

    /**
     * Process email alerts (cron job handler)
     */
    public function process_email_alerts() {
        global $wpdb;

        // Check if feature is enabled
        if (!get_option('listeo_saved_search_alerts_enabled', true)) {
            return;
        }

        // Get all searches with alerts enabled
        $searches = $wpdb->get_results(
            "SELECT ss.*, u.user_email, u.display_name
             FROM {$this->table_searches} ss
             JOIN {$wpdb->users} u ON ss.user_id = u.ID
             WHERE ss.email_alerts_enabled = 1
             ORDER BY ss.user_id ASC",
            ARRAY_A
        );

        if (empty($searches)) {
            return;
        }

        // Group searches by user for digest emails
        $user_searches = array();
        foreach ($searches as $search) {
            $user_id = $search['user_id'];
            if (!isset($user_searches[$user_id])) {
                $user_searches[$user_id] = array(
                    'user_email' => $search['user_email'],
                    'display_name' => $search['display_name'],
                    'searches' => array(),
                );
            }
            $user_searches[$user_id]['searches'][] = $search;
        }

        // Process each user's searches
        foreach ($user_searches as $user_id => $user_data) {
            // Check if user has opted out of email notifications
            if (get_user_meta($user_id, 'email_notifications', true) === 'on') {
                continue;
            }

            $matches_by_search = array();

            foreach ($user_data['searches'] as $search) {
                $new_matches = $this->find_new_matches($search);

                if (!empty($new_matches)) {
                    $matches_by_search[$search['id']] = array(
                        'search_name' => $search['search_name'],
                        'search_url' => $search['search_url'],
                        'listings' => $new_matches,
                    );
                }
            }

            // Send digest email if there are matches
            if (!empty($matches_by_search)) {
                $this->send_user_digest($user_id, $user_data, $matches_by_search);
            }
        }
    }

    /**
     * Find new listings matching a saved search
     *
     * @param array $search Saved search data
     * @param bool $debug Return debug info with query args
     * @return array Array of matching listing IDs (or debug array if $debug=true)
     */
    public function find_new_matches($search, $debug = false) {
        global $wpdb;

        $search_id = $search['id'];
        $criteria = is_string($search['search_criteria'])
            ? json_decode($search['search_criteria'], true)
            : $search['search_criteria'];

        if (!is_array($criteria)) {
            $criteria = array();
        }

        // Normalize criteria - handle both direct and tax- prefixed keys
        $criteria = $this->normalize_criteria($criteria);

        // Determine the date to check from
        $last_email = $search['last_email_sent'];
        $check_from = $last_email ? $last_email : $search['created_at'];

        // Build query args
        $args = array(
            'post_type' => 'listing',
            'post_status' => 'publish',
            'posts_per_page' => 50, // Limit to 50 new listings per search
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'after' => $check_from,
                    'inclusive' => false,
                ),
            ),
        );

        // Apply taxonomy filters from criteria
        $tax_query = array();

        // Standard taxonomy params and their tax- prefixed versions
        $taxonomy_mappings = array(
            'listing_category' => array('listing_category', 'tax-listing_category'),
            'listing_feature' => array('listing_feature', 'tax-listing_feature'),
            'region' => array('region', 'tax-region', 'search_region'),
            'service_category' => array('service_category', 'tax-service_category'),
            'rental_category' => array('rental_category', 'tax-rental_category'),
            'event_category' => array('event_category', 'tax-event_category'),
            'classifieds_category' => array('classifieds_category', 'tax-classifieds_category'),
        );

        foreach ($taxonomy_mappings as $taxonomy => $possible_keys) {
            foreach ($possible_keys as $key) {
                if (!empty($criteria[$key])) {
                    $terms = $criteria[$key];
                    // Handle comma-separated values
                    if (is_string($terms) && strpos($terms, ',') !== false) {
                        $terms = array_map('trim', explode(',', $terms));
                    }
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => is_numeric(is_array($terms) ? $terms[0] : $terms) ? 'term_id' : 'slug',
                        'terms' => $terms,
                    );
                    break; // Only add one query per taxonomy
                }
            }
        }

        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        // Apply meta filters
        $meta_query = array();

        // Listing type
        $listing_type_keys = array('_listing_type', 'listing_type');
        foreach ($listing_type_keys as $key) {
            if (!empty($criteria[$key])) {
                $meta_query[] = array(
                    'key' => '_listing_type',
                    'value' => $criteria[$key],
                );
                break;
            }
        }

        // Price range
        $price_min_keys = array('price_min', '_price_min');
        $price_max_keys = array('price_max', '_price_max');

        foreach ($price_min_keys as $key) {
            if (!empty($criteria[$key])) {
                $meta_query[] = array(
                    'key' => '_price',
                    'value' => floatval($criteria[$key]),
                    'type' => 'NUMERIC',
                    'compare' => '>=',
                );
                break;
            }
        }

        foreach ($price_max_keys as $key) {
            if (!empty($criteria[$key])) {
                $meta_query[] = array(
                    'key' => '_price',
                    'value' => floatval($criteria[$key]),
                    'type' => 'NUMERIC',
                    'compare' => '<=',
                );
                break;
            }
        }

        // Handle custom range sliders (e.g., _bedrooms_min, _bedrooms_max)
        // These are stored as {field}_min and {field}_max
        $processed_ranges = array('price'); // Already processed above

        foreach ($criteria as $key => $value) {
            if (empty($value)) {
                continue;
            }

            // Check for _min suffix
            if (preg_match('/^(_?[a-zA-Z0-9_]+)_min$/', $key, $matches)) {
                $base_field = $matches[1];

                if (in_array($base_field, $processed_ranges)) {
                    continue;
                }

                $meta_query[] = array(
                    'key' => $base_field,
                    'value' => floatval($value),
                    'type' => 'NUMERIC',
                    'compare' => '>=',
                );

                $processed_ranges[] = $base_field;
            }

            // Check for _max suffix
            if (preg_match('/^(_?[a-zA-Z0-9_]+)_max$/', $key, $matches)) {
                $base_field = $matches[1];

                if (in_array($base_field, $processed_ranges)) {
                    // Already added min, just add max
                    $meta_query[] = array(
                        'key' => $base_field,
                        'value' => floatval($value),
                        'type' => 'NUMERIC',
                        'compare' => '<=',
                    );
                } else {
                    $meta_query[] = array(
                        'key' => $base_field,
                        'value' => floatval($value),
                        'type' => 'NUMERIC',
                        'compare' => '<=',
                    );
                    $processed_ranges[] = $base_field;
                }
            }
        }

        // Handle custom meta fields from Listeo Editor
        // Get all available search fields dynamically
        $available_fields = $this->get_available_custom_fields();

        // Fields that are search PARAMETERS, not listing meta fields
        $skip_fields = array(
            // Already handled or internal
            '_listing_type', '_price_min', '_price_max', 'price_min', 'price_max', 'action', 'page',
            // Keyword search - handled by WP 's' parameter
            'keyword_search', 'search_keywords', 's',
            // Location search - not a meta field
            'location_search', 'search_location', 'search_region',
            // Radius - exclude from saved URLs (causes issues on direct page load)
            'search_radius',
            'search_lat', 'search_lng',
            // Rating - handled separately with _rating key
            'rating-filter', 'rating_filter',
            // Date params
            'date_range', 'date_start', 'date_end',
        );

        foreach ($criteria as $key => $value) {
            if (in_array($key, $skip_fields) || empty($value)) {
                continue;
            }

            // Skip taxonomy fields (already handled by tax_query)
            if (strpos($key, 'tax-') === 0) {
                continue;
            }

            // Check if this is a known custom field or starts with _
            $is_custom_field = in_array($key, $available_fields) || strpos($key, '_') === 0;

            if ($is_custom_field) {
                // Handle array values (from multi-checkbox fields)
                if (is_array($value) || (is_string($value) && strpos($value, ',') !== false)) {
                    $values = is_array($value) ? $value : array_map('trim', explode(',', $value));
                    $values = array_filter($values);

                    if (!empty($values)) {
                        // For multi-value fields, use OR relation
                        $multi_query = array('relation' => 'OR');
                        foreach ($values as $v) {
                            $multi_query[] = array(
                                'key' => $key,
                                'value' => $v,
                                'compare' => 'LIKE',
                            );
                        }
                        $meta_query[] = $multi_query;
                    }
                } else {
                    // Single value comparison
                    // Check if it's a numeric field for proper comparison
                    if (is_numeric($value)) {
                        $meta_query[] = array(
                            'key' => $key,
                            'value' => $value,
                            'type' => 'NUMERIC',
                            'compare' => '=',
                        );
                    } else {
                        $meta_query[] = array(
                            'key' => $key,
                            'value' => $value,
                            'compare' => 'LIKE',
                        );
                    }
                }
            }
        }

        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $args['meta_query'] = $meta_query;
        }

        // Keyword search
        $keyword_keys = array('keyword_search', 'search_keywords', 's');
        foreach ($keyword_keys as $key) {
            if (!empty($criteria[$key])) {
                $args['s'] = $criteria[$key];
                break;
            }
        }

        // Location search - search in _address and _friendly_address fields (broad method)
        $location_keys = array('location_search', 'search_location');
        foreach ($location_keys as $key) {
            if (!empty($criteria[$key])) {
                $location = $criteria[$key];
                // Split location by comma and search for each part
                $location_parts = array_map('trim', explode(',', $location));

                // Build OR query for location fields
                $location_meta = array('relation' => 'OR');

                foreach ($location_parts as $part) {
                    if (strlen($part) > 2) {
                        // Search in _address
                        $location_meta[] = array(
                            'key' => '_address',
                            'value' => $part,
                            'compare' => 'LIKE',
                        );
                        // Search in _friendly_address
                        $location_meta[] = array(
                            'key' => '_friendly_address',
                            'value' => $part,
                            'compare' => 'LIKE',
                        );
                    }
                }

                if (count($location_meta) > 1) {
                    $meta_query[] = $location_meta;
                }
                break;
            }
        }

        // Rating filter
        $rating_keys = array('rating-filter', 'rating_filter', '_rating');
        foreach ($rating_keys as $key) {
            if (!empty($criteria[$key]) && $criteria[$key] !== 'any') {
                $meta_query[] = array(
                    'key' => '_rating',
                    'value' => floatval($criteria[$key]),
                    'type' => 'DECIMAL',
                    'compare' => '>=',
                );
                break;
            }
        }

        // Date range for events
        if (!empty($criteria['date_range'])) {
            // Date range is typically in format "MM/DD/YYYY - MM/DD/YYYY"
            $dates = explode(' - ', $criteria['date_range']);
            if (count($dates) === 2) {
                $start_date = date('Y-m-d', strtotime(trim($dates[0])));
                $end_date = date('Y-m-d', strtotime(trim($dates[1])));

                if ($start_date && $end_date) {
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_event_date',
                            'value' => array($start_date, $end_date),
                            'type' => 'DATE',
                            'compare' => 'BETWEEN',
                        ),
                        array(
                            'key' => '_date',
                            'value' => array($start_date, $end_date),
                            'type' => 'DATE',
                            'compare' => 'BETWEEN',
                        ),
                    );
                }
            }
        }

        // Re-add meta query if we added rating or date filters
        if (!empty($meta_query) && !isset($args['meta_query'])) {
            $meta_query['relation'] = 'AND';
            $args['meta_query'] = $meta_query;
        } elseif (!empty($meta_query)) {
            // Merge with existing meta query
            foreach ($meta_query as $key => $value) {
                if ($key !== 'relation') {
                    $args['meta_query'][] = $value;
                }
            }
        }

        // Run the query
        $query = new WP_Query($args);
        $post_ids = $query->posts;

        if (empty($post_ids)) {
            if ($debug) {
                return array('ids' => array(), 'query_args' => $args);
            }
            return array();
        }

        // Filter out already notified listings
        $already_notified = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT listing_id FROM {$this->table_notifications} WHERE saved_search_id = %d",
                $search_id
            )
        );

        $new_ids = array_diff($post_ids, $already_notified);

        if ($debug) {
            return array('ids' => array_values($new_ids), 'query_args' => $args, 'already_notified' => $already_notified);
        }

        return array_values($new_ids);
    }

    /**
     * Get all available custom fields from Listeo Editor
     *
     * @return array Array of field IDs
     */
    private function get_available_custom_fields() {
        static $cached_fields = null;

        if ($cached_fields !== null) {
            return $cached_fields;
        }

        $field_ids = array();

        // Get meta box fields
        $meta_box_methods = array(
            'meta_boxes_service',
            'meta_boxes_location',
            'meta_boxes_event',
            'meta_boxes_prices',
            'meta_boxes_contact',
            'meta_boxes_rental',
            'meta_boxes_classifieds',
            'meta_boxes_custom'
        );

        foreach ($meta_box_methods as $method) {
            if (class_exists('Listeo_Core_Meta_Boxes') && method_exists('Listeo_Core_Meta_Boxes', $method)) {
                $meta_box = call_user_func(array('Listeo_Core_Meta_Boxes', $method));
                if (isset($meta_box['fields']) && is_array($meta_box['fields'])) {
                    foreach ($meta_box['fields'] as $field) {
                        if (isset($field['id']) && !empty($field['id'])) {
                            $field_ids[] = $field['id'];
                        }
                    }
                }
            }
        }

        // Get fields from Listeo Editor (custom listing type fields)
        $listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));

        if (is_array($listing_types)) {
            foreach ($listing_types as $type_slug) {
                // Get fields from fields builder
                $tab_fields = get_option("listeo_{$type_slug}_tab_fields", array());

                if (is_array($tab_fields) && !empty($tab_fields)) {
                    foreach ($tab_fields as $field) {
                        if (isset($field['id']) && !empty($field['id'])) {
                            $field_ids[] = $field['id'];
                        }
                    }
                }

                // Also check submit form fields
                $submit_fields = get_option("listeo_{$type_slug}_submit_fields", array());

                if (is_array($submit_fields) && !empty($submit_fields)) {
                    foreach ($submit_fields as $field) {
                        if (isset($field['id']) && !empty($field['id'])) {
                            $field_ids[] = $field['id'];
                        }
                    }
                }
            }
        }

        // Check for custom listing types manager
        if (function_exists('listeo_core_custom_listing_types')) {
            $custom_types_manager = listeo_core_custom_listing_types();
            $custom_types = $custom_types_manager->get_listing_types(true);

            if (!empty($custom_types)) {
                foreach ($custom_types as $type) {
                    $tab_fields = get_option("listeo_{$type->slug}_tab_fields", array());

                    if (is_array($tab_fields) && !empty($tab_fields)) {
                        foreach ($tab_fields as $field) {
                            if (isset($field['id']) && !empty($field['id'])) {
                                $field_ids[] = $field['id'];
                            }
                        }
                    }
                }
            }
        }

        // Add standard search vars
        $standard_vars = array(
            '_price_range',
            '_listing_type',
            '_price',
            '_max_guests',
            '_min_guests',
            '_instant_booking',
            '_bedrooms',
            '_bathrooms',
            '_sqft',
            'rating-filter',
            'date_range',
            'search_radius',
            'location_search',
            'keyword_search',
        );

        $field_ids = array_merge($field_ids, $standard_vars);

        $cached_fields = array_values(array_unique(array_filter($field_ids)));
        return $cached_fields;
    }

    /**
     * Normalize search criteria to handle different field name formats
     *
     * @param array $criteria Raw criteria
     * @return array Normalized criteria
     */
    private function normalize_criteria($criteria) {
        $normalized = array();

        foreach ($criteria as $key => $value) {
            if (empty($value)) {
                continue;
            }

            // Remove tax- prefix for storage but keep original
            $normalized[$key] = $value;

            // Also store without tax- prefix for easier lookup
            if (strpos($key, 'tax-') === 0) {
                $without_prefix = substr($key, 4);
                if (!isset($normalized[$without_prefix])) {
                    $normalized[$without_prefix] = $value;
                }
            }
        }

        return $normalized;
    }

    /**
     * Send digest email to a user
     *
     * @param int   $user_id User ID
     * @param array $user_data User data (email, name)
     * @param array $matches_by_search Matches grouped by search
     */
    public function send_user_digest($user_id, $user_data, $matches_by_search) {
        global $wpdb;

        $total_matches = 0;
        $listings_html = '';

        foreach ($matches_by_search as $search_id => $search_data) {
            $listings_html .= '<h3>' . esc_html($search_data['search_name']) . '</h3>';
            $listings_html .= '<p><a href="' . esc_url($search_data['search_url']) . '">' .
                              esc_html__('View all results', 'listeo_core') . '</a></p>';
            $listings_html .= '<div style="margin-bottom: 20px;">';

            foreach ($search_data['listings'] as $listing_id) {
                $listing = get_post($listing_id);
                if (!$listing) {
                    continue;
                }

                $total_matches++;

                // Get listing details
                $thumbnail = get_the_post_thumbnail_url($listing_id, 'thumbnail');
                $address = get_post_meta($listing_id, '_address', true);
                $price = get_post_meta($listing_id, '_price', true);

                $listings_html .= '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #eee; border-radius: 5px;">';

                if ($thumbnail) {
                    $listings_html .= '<img src="' . esc_url($thumbnail) . '" alt="" style="width: 80px; height: 60px; object-fit: cover; float: left; margin-right: 15px; border-radius: 3px;">';
                }

                $listings_html .= '<div style="overflow: hidden;">';
                $listings_html .= '<strong><a href="' . esc_url(get_permalink($listing_id)) . '">' . esc_html($listing->post_title) . '</a></strong><br>';

                if ($address) {
                    $listings_html .= '<small style="color: #666;">' . esc_html($address) . '</small><br>';
                }

                if ($price) {
                    $currency_symbol = Listeo_Core_Listing::get_currency_symbol(get_option('listeo_currency'), false);
                    $listings_html .= '<span style="color: #f91942;">' . esc_html($currency_symbol . $price) . '</span>';
                }

                $listings_html .= '</div>';
                $listings_html .= '<div style="clear: both;"></div>';
                $listings_html .= '</div>';

                // Record notification
                $wpdb->insert(
                    $this->table_notifications,
                    array(
                        'saved_search_id' => $search_id,
                        'listing_id' => $listing_id,
                        'notified_at' => current_time('mysql'),
                    ),
                    array('%d', '%d', '%s')
                );
            }

            $listings_html .= '</div>';

            // Update last email sent
            $wpdb->update(
                $this->table_searches,
                array('last_email_sent' => current_time('mysql')),
                array('id' => $search_id),
                array('%s'),
                array('%d')
            );
        }

        // Prepare email
        $manage_url = get_permalink(get_option('listeo_saved_searches_page'));
        if (!$manage_url) {
            $manage_url = get_permalink(get_option('listeo_dashboard_page'));
        }

        $search_count = count($matches_by_search);

        $args = array(
            'user_name' => $user_data['display_name'],
            'match_count' => $total_matches,
            'search_count' => $search_count,
            'listings' => $listings_html,
            'manage_url' => $manage_url,
        );

        $subject = get_option(
            'listeo_saved_search_email_subject',
            __('New listings matching your saved searches!', 'listeo_core')
        );
        $subject = $this->replace_shortcodes($args, $subject);

        $body = get_option(
            'listeo_saved_search_email_content',
            $this->get_default_email_content()
        );
        $body = $this->replace_shortcodes($args, $body);

        // Send email
        Listeo_Core_Emails::send($user_data['user_email'], $subject, $body);
    }

    /**
     * Replace shortcodes in email content
     *
     * @param array  $args Replacement values
     * @param string $content Content to process
     * @return string
     */
    private function replace_shortcodes($args, $content) {
        $replacements = array(
            '{user_name}' => isset($args['user_name']) ? $args['user_name'] : '',
            '{match_count}' => isset($args['match_count']) ? $args['match_count'] : '',
            '{search_count}' => isset($args['search_count']) ? $args['search_count'] : '',
            '{listings}' => isset($args['listings']) ? $args['listings'] : '',
            '{manage_url}' => isset($args['manage_url']) ? $args['manage_url'] : '',
            '{site_name}' => get_bloginfo('name'),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Get default email content
     *
     * @return string
     */
    private function get_default_email_content() {
        return 'Hi {user_name},

We found {match_count} new listing(s) matching your saved searches!

{listings}

<a href="{manage_url}">Manage your saved searches</a>

Best regards,
{site_name}';
    }

    /**
     * Generate human-readable criteria summary
     *
     * @param array $criteria Search criteria
     * @return string
     */
    public function get_criteria_summary($criteria) {
        if (!is_array($criteria) || empty($criteria)) {
            return __('All listings', 'listeo_core');
        }

        $parts = array();

        // Keywords
        $keyword = $criteria['keyword_search'] ?? $criteria['search_keywords'] ?? $criteria['s'] ?? '';
        if (!empty($keyword)) {
            $parts[] = sprintf(__('Keywords: %s', 'listeo_core'), esc_html($keyword));
        }

        // Location
        $location = $criteria['location_search'] ?? $criteria['search_location'] ?? '';
        if (!empty($location)) {
            $parts[] = sprintf(__('Location: %s', 'listeo_core'), esc_html($location));
        }

        // Category - check both direct and tax- prefixed
        $category = $criteria['listing_category'] ?? $criteria['tax-listing_category'] ?? '';
        if (!empty($category)) {
            // Handle comma-separated values
            $cat_slugs = is_string($category) ? explode(',', $category) : (array) $category;
            $cat_names = array();
            foreach ($cat_slugs as $cat_slug) {
                $cat_slug = trim($cat_slug);
                if (empty($cat_slug)) continue;
                $term = get_term_by(is_numeric($cat_slug) ? 'id' : 'slug', $cat_slug, 'listing_category');
                if ($term) {
                    $cat_names[] = $term->name;
                }
            }
            if (!empty($cat_names)) {
                $parts[] = sprintf(__('Category: %s', 'listeo_core'), esc_html(implode(', ', $cat_names)));
            }
        }

        // Region - check both direct and tax- prefixed
        $region = $criteria['region'] ?? $criteria['tax-region'] ?? $criteria['search_region'] ?? '';
        if (!empty($region)) {
            $term = get_term_by(is_numeric($region) ? 'id' : 'slug', $region, 'region');
            if ($term) {
                $parts[] = sprintf(__('Region: %s', 'listeo_core'), esc_html($term->name));
            }
        }

        // Features - check both direct and tax- prefixed
        $features = $criteria['listing_feature'] ?? $criteria['tax-listing_feature'] ?? '';
        if (!empty($features)) {
            $feature_slugs = is_string($features) ? explode(',', $features) : (array) $features;
            $feature_names = array();
            foreach ($feature_slugs as $feature_slug) {
                $feature_slug = trim($feature_slug);
                if (empty($feature_slug)) continue;
                $term = get_term_by(is_numeric($feature_slug) ? 'id' : 'slug', $feature_slug, 'listing_feature');
                if ($term) {
                    $feature_names[] = $term->name;
                }
            }
            if (!empty($feature_names)) {
                $parts[] = sprintf(__('Features: %s', 'listeo_core'), esc_html(implode(', ', $feature_names)));
            }
        }

        // Listing type specific categories (service_category, rental_category, event_category, classifieds_category)
        $listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));
        if (is_array($listing_types)) {
            foreach ($listing_types as $type) {
                $taxonomy = $type . '_category';
                $tax_key = 'tax-' . $taxonomy;

                // Check both direct and tax- prefixed versions
                $category_value = $criteria[$taxonomy] ?? $criteria[$tax_key] ?? '';

                if (!empty($category_value)) {
                    $cat_slugs = is_string($category_value) ? explode(',', $category_value) : (array) $category_value;
                    $cat_names = array();

                    foreach ($cat_slugs as $cat_slug) {
                        $cat_slug = trim($cat_slug);
                        if (empty($cat_slug)) continue;

                        // Check if taxonomy exists
                        if (!taxonomy_exists($taxonomy)) continue;

                        $term = get_term_by(is_numeric($cat_slug) ? 'id' : 'slug', $cat_slug, $taxonomy);
                        if ($term) {
                            $cat_names[] = $term->name;
                        }
                    }

                    if (!empty($cat_names)) {
                        $type_label = ucfirst($type);
                        $parts[] = sprintf(__('%s Category: %s', 'listeo_core'), esc_html($type_label), esc_html(implode(', ', $cat_names)));
                    }
                }
            }
        }

        // Listing type
        $listing_type = $criteria['_listing_type'] ?? $criteria['listing_type'] ?? '';
        if (!empty($listing_type)) {
            $parts[] = sprintf(__('Type: %s', 'listeo_core'), esc_html(ucfirst($listing_type)));
        }

        // Price range
        $price_min = $criteria['price_min'] ?? '';
        $price_max = $criteria['price_max'] ?? '';
        if (!empty($price_min) || !empty($price_max)) {
            $currency = Listeo_Core_Listing::get_currency_symbol(get_option('listeo_currency'), false);
            if (!empty($price_min) && !empty($price_max)) {
                $parts[] = sprintf(__('Price: %s - %s', 'listeo_core'), $currency . $price_min, $currency . $price_max);
            } elseif (!empty($price_min)) {
                $parts[] = sprintf(__('Price: from %s', 'listeo_core'), $currency . $price_min);
            } else {
                $parts[] = sprintf(__('Price: up to %s', 'listeo_core'), $currency . $price_max);
            }
        }

        // Rating
        $rating = $criteria['rating-filter'] ?? $criteria['rating_filter'] ?? '';
        if (!empty($rating) && $rating !== 'any') {
            $parts[] = sprintf(__('Rating: %s+', 'listeo_core'), esc_html($rating));
        }

        // Date range
        $date_range = $criteria['date_range'] ?? '';
        if (!empty($date_range)) {
            $parts[] = sprintf(__('Dates: %s', 'listeo_core'), esc_html($date_range));
        }

        // Radius
        $radius = $criteria['search_radius'] ?? '';
        if (!empty($radius)) {
            $unit = get_option('listeo_radius_unit', 'km');
            $parts[] = sprintf(__('Radius: %s %s', 'listeo_core'), esc_html($radius), esc_html($unit));
        }

        // Handle custom fields from Listeo Editor
        $available_fields = $this->get_available_custom_fields();

        // Build list of already displayed keys (including listing type specific categories)
        $displayed_keys = array(
            'keyword_search', 'search_keywords', 's', 'location_search', 'search_location',
            'listing_category', 'tax-listing_category', 'region', 'tax-region', 'search_region',
            'listing_feature', 'tax-listing_feature', '_listing_type', 'listing_type',
            'price_min', 'price_max', 'rating-filter', 'rating_filter', 'date_range', 'search_radius',
            'action', 'page', '_price_range'
        );

        // Add listing type specific categories to displayed keys
        $listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));
        if (is_array($listing_types)) {
            foreach ($listing_types as $type) {
                $displayed_keys[] = $type . '_category';
                $displayed_keys[] = 'tax-' . $type . '_category';
            }
        }

        // Track processed range fields to avoid duplicates
        $processed_range_fields = array();

        foreach ($criteria as $key => $value) {
            if (in_array($key, $displayed_keys) || empty($value)) {
                continue;
            }

            // Check for range fields (e.g., _bedrooms_min, _bedrooms_max)
            if (preg_match('/^(_?[a-zA-Z0-9_]+)_(min|max)$/', $key, $matches)) {
                $base_field = $matches[1];
                $suffix = $matches[2];

                if (in_array($base_field, $processed_range_fields)) {
                    continue;
                }

                $min_val = $criteria[$base_field . '_min'] ?? '';
                $max_val = $criteria[$base_field . '_max'] ?? '';

                if (!empty($min_val) || !empty($max_val)) {
                    $label = $this->get_field_label($base_field);

                    if (!empty($min_val) && !empty($max_val)) {
                        $parts[] = sprintf('%s: %s - %s', esc_html($label), esc_html($min_val), esc_html($max_val));
                    } elseif (!empty($min_val)) {
                        $parts[] = sprintf(__('%s: from %s', 'listeo_core'), esc_html($label), esc_html($min_val));
                    } else {
                        $parts[] = sprintf(__('%s: up to %s', 'listeo_core'), esc_html($label), esc_html($max_val));
                    }

                    $processed_range_fields[] = $base_field;
                }
                continue;
            }

            // Only show custom fields
            if (in_array($key, $available_fields) || strpos($key, '_') === 0) {
                // Try to get a human-readable label for the field
                $label = $this->get_field_label($key);

                // Format the value
                $display_value = $value;
                if (is_string($value) && strpos($value, ',') !== false) {
                    // Multiple values
                    $display_value = str_replace(',', ', ', $value);
                }

                $parts[] = sprintf('%s: %s', esc_html($label), esc_html($display_value));
            }
        }

        if (empty($parts)) {
            return __('All listings', 'listeo_core');
        }

        return implode(' | ', $parts);
    }

    /**
     * Get human-readable label for a field
     *
     * @param string $field_id Field ID
     * @return string
     */
    private function get_field_label($field_id) {
        // Try to find the field in Listeo Editor settings
        $listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));

        foreach ($listing_types as $type_slug) {
            $tab_fields = get_option("listeo_{$type_slug}_tab_fields", array());

            if (is_array($tab_fields)) {
                foreach ($tab_fields as $field) {
                    if (isset($field['id']) && $field['id'] === $field_id) {
                        if (isset($field['name']) && !empty($field['name'])) {
                            return $field['name'];
                        }
                    }
                }
            }

            $submit_fields = get_option("listeo_{$type_slug}_submit_fields", array());

            if (is_array($submit_fields)) {
                foreach ($submit_fields as $field) {
                    if (isset($field['id']) && $field['id'] === $field_id) {
                        if (isset($field['name']) && !empty($field['name'])) {
                            return $field['name'];
                        }
                    }
                }
            }
        }

        // Fallback: convert field ID to readable format
        // Remove leading underscore and convert underscores/hyphens to spaces
        $label = ltrim($field_id, '_');
        $label = str_replace(array('_', '-'), ' ', $label);
        $label = ucwords($label);

        return $label;
    }
}
