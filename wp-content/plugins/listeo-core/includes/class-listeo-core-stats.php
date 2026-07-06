<?php

if (!defined('ABSPATH')) exit;

class Listeo_Core_Stats
{


    public $post_types = array('listing');
    public $stats = array(
        'visits',
        'unique',
        'booking_click',
        'external_booking_click',
        'contact_click',
        'whatsapp_click',
        'phone_click',
        'email_click',
        'website_click',
        'facebook_click',
        'instagram_click',
        'twitter_click',
        'linkedin_click',
        'youtube_click',
        'telegram_click',
        'skype_click',
        'viber_click',
        'tiktok_click',
        'snapchat_click',
        'pinterest_click'
    );
    public $cookie_name = 'listings_visited';
    /**
     * Cookie ID
     *
     * @var string $cookie_id Cookie ID.
     * @since 2.0.0
     */
    public $cookie_id = 'listeo_stats';

    /**
     * Returns the instance.
     *
     * @since 2.0.0
     */
    public static function get_instance()
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new self;
        }
        return $instance;
    }


    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {

        $stats_type = get_option('listeo_stats_type',array( 'unique', 'booking_click'));

        if(empty($stats_type)){
            $stats_type = array('visits');
        } else {
            $stats_type[] = 'visits';
        }

        foreach ($this->stats as $stat_id) {
            if(in_array($stat_id,$stats_type)){

                add_action("wp_ajax_listeo_stat_{$stat_id}", array($this, 'update_stat_ajax'));
                add_action("wp_ajax_nopriv_listeo_stat_{$stat_id}", array($this, 'update_stat_ajax'));
            }
        }
        add_action('wp_enqueue_scripts', array($this, 'listeo_stats_scripts'));

        // Auto-enable all stat types when stats are first enabled
        add_action('update_option_listeo_stats_status', array($this, 'auto_enable_all_stats'), 10, 2);
        add_action('update_option_listeo_analytics_enabled', array($this, 'auto_enable_on_analytics_toggle'), 10, 2);

        // One-time migration: If stats are already enabled but stat types are missing, fix it
        add_action('admin_init', array($this, 'migrate_stat_types_once'));

        // Auto-check and enable missing stats when visiting analytics dashboard (once per day)
        add_action('admin_init', array($this, 'auto_check_and_enable_stats_on_dashboard'));

        // Admin notice when stats are auto-enabled
        add_action('admin_notices', array($this, 'show_auto_enable_notice'));

        // AJAX endpoint for listing search (for Select2 dropdown in analytics dashboard)
        add_action('wp_ajax_listeo_search_listings', array($this, 'ajax_search_listings'));

        // AJAX endpoint for AI search queries (for configurable chart)
        add_action('wp_ajax_listeo_get_ai_search_queries', array($this, 'ajax_get_ai_search_queries'));

        // AJAX endpoint for dismissing stats auto-enable notice
        add_action('wp_ajax_listeo_dismiss_stats_notice', array($this, 'ajax_dismiss_stats_notice'));

    }

    /**
     * One-time migration to enable all stat types for existing installations
     * Runs only once per installation
     *
     * @since 2.0.13
     */
    public function migrate_stat_types_once()
    {
        // Check if migration already ran
        if (get_option('listeo_stats_migration_v2_done')) {
            return;
        }

        // Only migrate if stats are already enabled
        $stats_enabled = get_option('listeo_stats_status');
        if ($stats_enabled === 'on') {
            $current_stats = get_option('listeo_stats_type', array());

            // If only old defaults exist, upgrade to all stat types
            if (empty($current_stats) || count($current_stats) <= 3) {
                $this->ensure_all_stats_enabled();
            }
        }

        // Mark migration as complete
        update_option('listeo_stats_migration_v2_done', true);
    }

    /**
     * Auto-enable all stat types when old stats are enabled
     *
     * @param mixed $old_value Previous value
     * @param mixed $new_value New value
     * @since 2.0.13
     */
    public function auto_enable_all_stats($old_value, $new_value)
    {
        // Only run when stats are being enabled (turning ON)
        if ($new_value === 'on' && $old_value !== 'on') {
            // Enable all stats from the stats array (excluding 'visits')
            $this->ensure_all_stats_enabled();
        }
    }

    /**
     * Auto-enable all stat types when analytics toggle is switched on
     *
     * @param mixed $old_value Previous value
     * @param mixed $new_value New value
     * @since 2.0.14
     */
    public function auto_enable_on_analytics_toggle($old_value, $new_value)
    {
        // Only run when analytics are being enabled (turning ON)
        if ($new_value && !$old_value) {
            // Enable all stats from the stats array (excluding 'visits')
            $this->ensure_all_stats_enabled();
        }
    }

    /**
     * Auto-check and enable missing stats when visiting analytics dashboard
     * Runs once per day to avoid performance impact
     *
     * @since 2.0.14
     */
    public function auto_check_and_enable_stats_on_dashboard()
    {
        // Only run on analytics dashboard page
        if (!isset($_GET['page']) || $_GET['page'] !== 'listeo-analytics') {
            return;
        }

        // Check if we've already run today (transient expires in 24 hours)
        $transient_key = 'listeo_stats_checked';
        if (get_transient($transient_key)) {
            return; // Already checked today
        }

        // Run the check
        $enabled = $this->ensure_all_stats_enabled();

        // Set transient for 24 hours
        set_transient($transient_key, true, DAY_IN_SECONDS);

        // If stats were enabled, set a flag for admin notice
        if ($enabled > 0) {
            set_transient('listeo_stats_auto_enabled', $enabled, HOUR_IN_SECONDS);
        }
    }

    /**
     * Ensure all stat types from $this->stats are enabled
     * Returns number of stats that were newly enabled
     *
     * @return int Number of stats that were enabled
     * @since 2.0.14
     */
    private function ensure_all_stats_enabled()
    {
        $current_stats = get_option('listeo_stats_type', array());

        // Get all available stats except 'visits' (visits is always tracked)
        $available_stats = array_diff($this->stats, array('visits'));

        // Find missing stats
        $missing_stats = array_diff($available_stats, $current_stats);

        // If any stats are missing, enable them
        if (!empty($missing_stats)) {
            $updated_stats = array_unique(array_merge($current_stats, $missing_stats));
            update_option('listeo_stats_type', $updated_stats);
            return count($missing_stats);
        }

        return 0;
    }

    /**
     * Show admin notice when stats are auto-enabled
     *
     * @since 2.0.14
     */
    public function show_auto_enable_notice()
    {
        $enabled_count = get_transient('listeo_stats_auto_enabled');

        if (!$enabled_count) {
            return;
        }

        // Check if user dismissed it
        if (get_user_meta(get_current_user_id(), 'listeo_stats_notice_dismissed', true)) {
            return;
        }

        ?>
        <div class="notice notice-success is-dismissible" data-notice="listeo-stats-enabled">
            <p>
                <strong><?php esc_html_e('Listeo Analytics:', 'listeo_core'); ?></strong>
                <?php
                printf(
                    _n(
                        '%d new stat type has been automatically enabled for tracking.',
                        '%d new stat types have been automatically enabled for tracking.',
                        $enabled_count,
                        'listeo_core'
                    ),
                    $enabled_count
                );
                ?>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '[data-notice="listeo-stats-enabled"] .notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'listeo_dismiss_stats_notice',
                    nonce: '<?php echo wp_create_nonce('listeo_dismiss_stats_notice'); ?>'
                });
            });
        });
        </script>
        <?php

        // Clear the transient after showing
        delete_transient('listeo_stats_auto_enabled');
    }




    /**
     * Stats Script
     *
     * Load Combined Stats JS if Debug is Disabled.
     *
     * @since 2.7.0
     */
    function listeo_stats_scripts()
    {
     
        // Only load in singular listing pages.
        if (is_singular($this->post_types)) {
             // Only pass page-load stats to the old stats script (visits, unique, booking_click, contact_click)
            // All click-based stats (social, phone, email, etc.) are handled by listeo.analytics.js
            $stats_type_all = get_option('listeo_stats_type',array( 'unique', 'booking_click'));
            $stats_for_pageload = array('visits'); // Always track visits

            // Only add stats that should track on page load (not click events)
            $pageload_stats = array('unique', 'booking_click', 'contact_click');
            foreach ($pageload_stats as $stat) {
                if (in_array($stat, $stats_type_all)) {
                    $stats_for_pageload[] = $stat;
                }
            }

            // Single JS to track listings.
            wp_enqueue_script('listeo-stats', LISTEO_CORE_URL . 'assets/js/listeo.stats.min.js', array('wp-util', 'jquery'), 1.0, true);
            $data = array(
                'post_id' => intval(get_queried_object_id()),
                'stats'   => $stats_for_pageload, // Only page-load stats, NOT click stats
            );
            wp_localize_script('listeo-stats', 'listeoStats', $data);

            // Also load enhanced analytics script for social/contact tracking
            wp_enqueue_script(
                'listeo-analytics',
                LISTEO_CORE_URL . 'assets/js/listeo.analytics.js',
                array('jquery', 'wp-util'),
                '2.0.18', // Updated version - restored WhatsApp button styling, added listeo-track-whatsapp class
                true
            );

            wp_localize_script('listeo-analytics', 'listeoAnalytics', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'listing_id' => get_the_ID(),
                'user_id' => get_current_user_id(),
                'enabled' => true,
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ));
        }
    }


    /**
     * Check if tracking is needed for a post.
     */
    public function check($post_id, $log_author = false, $check_cookie = false, $stat = null)
    {
        // Get post, if not valid, bail.
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Check post type.
        if (!in_array($post->post_type, $this->post_types, true)) {
            return false;
        }

        // Do not track listing author.
        if (!$log_author && is_user_logged_in() && $post->post_author && get_current_user_id() === $post->post_author) {
            return false;
        }

        // If log by cookie.
        if ($check_cookie) {

            // Bail, already logged (pass stat name for per-stat cookie checking).
            if (in_array($post_id, $this->get_cookie($stat))) {
                return false;
            }
        }

        return true;
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
     * AJAX Callback.
     *
     * @since 2.7.0
     */
    public function update_stat_ajax()
    {
        // Bot protection: Only allow legitimate browsers
        if (!$this->is_legitimate_browser()) {
            wp_send_json_error(array(
                'result' => 'bot_blocked',
                'message' => __('Stats tracking is only available for web browsers', 'listeo_core')
            ));
            return;
        }

        $request = stripslashes_deep($_POST);

        // Get Post ID.
        $post_id        = intval($request['post_id']);
        $stat           = $request['stat'];
        $is_ajax        = false;
        $check_cookie   = false;
        $log_author     = true;

        switch ($stat) {
            case 'visits':
                $stat_label   = __('Visits', 'listeo_core');
                $is_ajax        = true;
                $check_cookie   = false;
                $log_author     = true;
                break;
            case 'unique':
                $stat_label   = __('Unique Visit', 'listeo_core');
                $is_ajax        = true;
                $check_cookie   = true;
                $log_author     = true;
                break;
            case 'booking_click':
            case 'external_booking_click':
            case 'contact_click':
            case 'whatsapp_click':
            case 'phone_click':
            case 'email_click':
            case 'website_click':
            case 'facebook_click':
            case 'instagram_click':
            case 'twitter_click':
            case 'linkedin_click':
            case 'youtube_click':
            case 'telegram_click':
            case 'skype_click':
            case 'viber_click':
            case 'tiktok_click':
            case 'snapchat_click':
            case 'pinterest_click':
                $stat_label   = ucfirst(str_replace('_', ' ', $stat));
                $is_ajax        = true;
                $check_cookie   = true; // Only count once per day
                $log_author     = true;
                break;

            default:
                # code...
                break;
        }
 
        // Check if tracking needed (pass stat name for cookie checking).
        if ($this->check($post_id,$log_author, $check_cookie, $stat)) {

            // Update stat.
            $updated = $this->update_stat_value($post_id, $stat, $check_cookie);
            if ($updated) {

                // Success.
                $data = array(
                    'stat'    => $stat,
                    'post_id' => $post_id,
                    'result'  => 'stat_updated',
                );
                if ($check_cookie) {
                    $data['cookie'] = $this->get_cookie($stat);
                }
                wp_send_json_success($data);
            }
        }

        // Fail.
        $data = array(
            'stat'   => $stat,
            'post_id' => $post_id,
            'result' => 'stat_update_fail',
        );
        if ($check_cookie) {
            $data['cookie'] = $this->get_cookie($stat);
        }
        wp_send_json_error($data);
    }


    public function update_stat_value($post_id, $stat, $check_cookie)
    {
        $updated = $this->listeo_update_stat_value($post_id, $stat);

        // Success.
        if ($updated) {

            // Update cookie if needed.
            if ($check_cookie) {
                $this->add_cookie($post_id,$stat);
            }

            // Update total.
            $this->update_post_stat_total($post_id, $stat);
        }

        return $updated;
    }

    /**
     * Update stat.
     *
     * Update a statistic in the database. This is based on the Listing post ID,
     * the date, and statistic ID. When that combination does not exist, a new value will
     * be created.
     *
     * @since 1.0.0
     *
     * @param   int    $post_id   ID of the listing post.
     * @param   string $stat_id   ID of the statistic. E.g 'views' or 'unique_views'.
     * @param   string $date      Date of the statistic.
     * @param   int    $value     Value of the statistic.
     * @return  mixed             The number of rows updated, or false on error.
     */
    public function listeo_update_stat_value($post_id, $stat_id, $date = false, $value = false)
    {
        global $wpdb;

        /* Check previous value */
        $old_value = $this->listeo_get_stat_value($post_id, $stat_id, $date);

        /* Previous value don't exist, add it. */
        if (
            !$old_value
        ) {
            return
                $this->listeo_add_stat_value($post_id, $stat_id, $date, $value);
        }

        /* Default */
        $date = (false === $date) ? date_i18n('Y-m-d') : $date;
        $value = (false === $value) ? $old_value + 1 : $value;

        /* Update database */
        $data = array(
            'stat_value' => intval($value),
        );
        $where = array(
            'post_id'    => absint($post_id),
            'stat_id'    => sanitize_title($stat_id),
            'stat_date'  => date_i18n('Y-m-d', strtotime($date)),
        );
        $result = $wpdb->update($wpdb->prefix . 'listeo_core_stats', $data, $where);
        return $result;
    }

    /**
     * Get Stat.
     *
     * Get a statistic from the database. This is based on the Listing post ID,
     * the date, and statistic ID. When that combination does not exist, null will
     * be returned.
     *
     * @since 1.0.0
     *
     * @param    int    $post_id   ID of the listing post.
     * @param    string $date      Date of the statistic.
     * @param    string $stat_id   ID of the statistic. E.g 'views' or 'unique_views'.
     * @param    int    $value     Value of the statistic.
     * @return   int                  Returns the stat value when exists, 0 when it doesn't exist.
     */
    function listeo_get_stat_value($post_id, $stat_id, $date = false)
    {
        global $wpdb;

        /* Default */
        $date = (false === $date) ? date_i18n('Y-m-d') : $date;

        /* Get row data */
        $row = $wpdb->get_row($wpdb->prepare("SELECT stat_value FROM {$wpdb->prefix}listeo_core_stats WHERE post_id = %s AND stat_date = %s AND stat_id = %s LIMIT 1", absint($post_id), date_i18n('Y-m-d', strtotime($date)), sanitize_title($stat_id)));

        if (
            is_object($row) && isset($row->stat_value)
        ) {
            return intval($row->stat_value);
        }
        return 0; // default
    }


    /**
     * Add stat.
     *
     * Add a statistic in the database. This is based on the Listing post ID,
     * the date, and statistic ID. When that combination does exist, the existing value
     * will be updated.
     *
     * @since 1.0.0
     *
     * @param   int    $post_id   ID of the listing post.
     * @param   string $date      Date of the statistic, recommended format YYYY-MM-DD.
     * @param   int    $stat_id   ID of the statistic. E.g 'views' or 'unique_views'.
     * @param   mixed  $value     Value of the statistic. False to auto increment from previous value.
     * @return  mixed               Returns row effected when successfully added, or false when failed.
     */
    public function listeo_add_stat_value($post_id, $stat_id, $date = false, $value = false)
    {
        global $wpdb;

        /* Check previous value */
        $old_value =
            $this->listeo_get_stat_value($post_id, $stat_id, $date);

        /* Previous value exist, use update function. */
        if (
            $old_value
        ) {
            return
                $this->listeo_update_stat_value($post_id, $stat_id, $date, $value);
        }

        /* Default */
        $date = (false === $date) ? date_i18n('Y-m-d') : $date;
        $value = (false === $value) ? 1 : $value;

        /* Insert database row */
        $result = $wpdb->query($wpdb->prepare("INSERT INTO `{$wpdb->prefix}listeo_core_stats` (`post_id`, `stat_date`, `stat_id`, `stat_value`) VALUES (%s, %s, %s, %s)", absint($post_id), date_i18n('Y-m-d', strtotime($date)), sanitize_title($stat_id), intval($value)));

        return $result;
    }


    /**
     * Update Post Stats Total Data.
     * This data is only updated on daily basis.
     * Data is useful for posts query based on stats data.
     *
     * @since 2.4.0
     *
     * @param int $post_id Post ID.
     */
    public function update_post_stat_total($post_id, $stat_id)
    {
        // Get today's date.
        $today = intval(date('Ymd')); // YYYYMMDD.

        // Last updated stat value.
        $last_updated = intval(get_post_meta($post_id, '_listeo_' . $stat_id . '_last_updated', true));

        // If not yet updated today, update it.
        if ($today !== $last_updated) {

            // Add updated day.
            update_post_meta($post_id, '_listeo_' . $stat_id . '_last_updated', intval($today));

            // Get stats total, and add it in post meta.
            $total = $this->listeo_get_stats_total($post_id, $stat_id);
            if ($total) {
                update_post_meta($post_id, '_listeo_' . $stat_id . '_total', intval($total));
            }
        }
    }


    /**
     * Get stats total of a stat in a post.
     *
     * @since 2.4.0
     *
     * @param int    $post_id Post ID.
     * @param string $stat_id Stat ID.
     * @return int
     */
    function listeo_get_stats_total($post_id, $stat_id)
    {
        global $wpdb;
        $total = $wpdb->get_results($wpdb->prepare("SELECT SUM(stat_value) stat_value FROM {$wpdb->prefix}listeo_core_stats WHERE post_id = %s AND stat_id = %s", absint($post_id), sanitize_title($stat_id)), 'ARRAY_A');
        if (
            isset($total[0]['stat_value'])
        ) {
            return intval($total[0]['stat_value']);
        }
        return 0;
    }



    /**
     * Get Cookie
     * this will return array of post ids of set cookie.
     *
     * @return array
     */
    public function get_cookie($stat = null)
    {
        $cookie_id = $this->cookie_id;
        // Use stat-specific cookie name for per-stat tracking, fallback to default
        $cookie_name = $stat ? $stat : ($this->cookie_name ? $this->cookie_name : 'default');
        $cookie_value = array();
        if (isset($_COOKIE[$cookie_id]) && !empty($_COOKIE[$cookie_id])) {
            $stats_cookie_value = json_decode(stripslashes($_COOKIE[$cookie_id]), true);
            if (isset($stats_cookie_value[$cookie_name]) && is_array($stats_cookie_value[$cookie_name])) {
                $cookie_value = $stats_cookie_value[$cookie_name];
            }
        }
        return $cookie_value;
    }


    /**
     * Add Post ID in Stat Cookie.
     *
     * @since 2.0.0
     *
     * @param int $post_id Post ID.
     */
    public function add_cookie($post_id,$stat)
    {
        $post_id = intval($post_id);
        $expiration  = intval(apply_filters($stat . '_cookie_expiration', DAY_IN_SECONDS));
        $cookie_id = $this->cookie_id;
        // Use stat name as cookie key for per-stat deduplication
        $cookie_name = $stat;
        $stats_cookie_value = array();
        if (isset($_COOKIE[$cookie_id]) && !empty($_COOKIE[$cookie_id])) {
            $stats_cookie_value = json_decode(stripslashes($_COOKIE[$cookie_id]), true);
        }
        $stats_cookie_value[$cookie_name][$post_id] = $post_id;
        setcookie($cookie_id, json_encode($stats_cookie_value), time() + $expiration);
    }


    /**
     * AJAX handler for listing search (used by Select2 in analytics dashboard)
     *
     * @since 2.0.18
     */
    public function ajax_search_listings() {
        // Check permissions
     

        // Get search term
        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;

        // Query args
        $args = array(
            'post_type' => 'listing',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids' // Only get IDs for performance
        );

        // Add search if provided
        if (!empty($search)) {
            $args['s'] = $search;
        }

        // Execute query
        $query = new WP_Query($args);

        // Format results for Select2
        $results = array();
        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $results[] = array(
                    'id' => $post_id,
                    'text' => get_the_title($post_id)
                );
            }
        }

        // Return Select2-compatible response
        wp_send_json(array(
            'results' => $results,
            'pagination' => array(
                'more' => ($page * 20) < $query->found_posts
            )
        ));
    }

    /**
     * AJAX handler for AI search queries (with configurable limit)
     *
     * @since 2.0.18
     */
    public function ajax_get_ai_search_queries() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
        }

        // Check if AI Chat Search plugin is active
        if (!class_exists('Listeo_AI_Search_Analytics')) {
            wp_send_json_error(array('message' => __('AI Chat Search plugin not active', 'listeo_core')));
        }

        // Get parameters
        $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
        $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 10;

        // Validate limit
        $allowed_limits = array(10, 20, 50, 100);
        if (!in_array($limit, $allowed_limits)) {
            $limit = 10;
        }

        // Get full analytics data
        $ai_stats = Listeo_AI_Search_Analytics::get_analytics($days);

        // Get all query counts (before the slice in get_analytics)
        $logs = get_option('listeo_ai_search_logs', array());
        $cutoff_time = current_time('timestamp') - ($days * DAY_IN_SECONDS);

        // Filter logs to specified time period
        $recent_logs = array_filter($logs, function($log) use ($cutoff_time) {
            return $log['timestamp'] > $cutoff_time;
        });

        // Get all query counts
        $query_counts = array();
        foreach ($recent_logs as $log) {
            $query = strtolower(trim($log['query']));
            if (strlen($query) > 2) {
                $query_counts[$query] = ($query_counts[$query] ?? 0) + 1;
            }
        }
        arsort($query_counts);

        // Apply custom limit
        $popular_queries = array_slice($query_counts, 0, $limit, true);

        wp_send_json_success(array(
            'queries' => $popular_queries,
            'total_unique_queries' => count($query_counts),
            'limit' => $limit
        ));
    }

    /**
     * AJAX handler for dismissing stats auto-enable notice
     *
     * @since 2.0.14
     */
    public function ajax_dismiss_stats_notice() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo_dismiss_stats_notice')) {
            wp_send_json_error(array('message' => __('Invalid nonce', 'listeo_core')));
        }

        // Mark notice as dismissed for this user
        update_user_meta(get_current_user_id(), 'listeo_stats_notice_dismissed', true);

        wp_send_json_success();
    }

}
