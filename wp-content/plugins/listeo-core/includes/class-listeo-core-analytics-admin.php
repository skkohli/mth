<?php
/**
 * Listeo Core Analytics Admin
 *
 * Handles admin page, toolbar link, and settings
 *
 * @package Listeo_Core
 * @subpackage Analytics
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Core_Analytics_Admin {

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
        // Add admin bar link
        add_action('admin_bar_menu', array($this, 'add_admin_bar_link'), 100);

        // Add admin menu page (but not in Listeo Core submenu, separate top-level)
        add_action('admin_menu', array($this, 'add_admin_page'));

        // Handle settings save
        add_action('admin_post_listeo_save_analytics_settings', array($this, 'save_settings'));

        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        // AJAX handler for top listings
        add_action('wp_ajax_listeo_get_top_listings', array($this, 'ajax_get_top_listings'));

        // AJAX handler for conversation details
        add_action('wp_ajax_listeo_get_conversation_detail', array($this, 'ajax_get_conversation_detail'));
    }

    /**
     * Add link to admin bar
     */
    public function add_admin_bar_link($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id' => 'listeo-analytics',
            'title' => '<span class="ab-icon dashicons dashicons-chart-line"></span><span class="ab-label">' . __('Listeo Analytics', 'listeo_core') . '</span>',
            'href' => admin_url('admin.php?page=listeo-analytics'),
            'meta' => array('class' => 'listeo-analytics-link')
        ));
    }

    /**
     * Add admin page (as standalone page, not in submenu)
     */
    public function add_admin_page() {
        add_menu_page(
            __('Listeo Analytics', 'listeo_core'),
            __('Listeo Analytics', 'listeo_core'),
            'manage_options',
            'listeo-analytics',
            array($this, 'render_analytics_page'),
            'dashicons-chart-line',
            30
        );
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_styles($hook) {
        if ($hook !== 'toplevel_page_listeo-analytics') {
            return;
        }

        // Enqueue Font Awesome (from Listeo theme)
        $theme_dir = get_template_directory_uri();
        wp_enqueue_style('font-awesome-5', $theme_dir . '/css/all.css', array(), '5.0');
        wp_enqueue_style('font-awesome-5-shims', $theme_dir . '/css/v4-shims.min.css', array('font-awesome-5'), '5.0');

        // Enqueue Select2 from CDN (WordPress core doesn't include it reliably)
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

        // Enqueue CSS
        wp_enqueue_style('listeo-analytics-admin', LISTEO_CORE_URL . 'assets/css/analytics-admin.css', array('font-awesome-5', 'select2-css'), '1.0.1');

        // Enqueue Chart.js
        wp_enqueue_script('chart-js', LISTEO_CORE_URL . 'assets/js/chart.min.js', array(), '4.4.0', true);

        // Enqueue dashboard JavaScript
        wp_enqueue_script('listeo-analytics-dashboard', LISTEO_CORE_URL . 'assets/js/listeo-analytics-dashboard.js', array('jquery', 'chart-js', 'select2-js'), '1.0.4', true);

        // Pass data to JS
        wp_localize_script('listeo-analytics-dashboard', 'listeoAnalyticsDashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'selected_listing_id' => isset($_GET['listing_id']) ? absint($_GET['listing_id']) : 0,
            'nonce' => wp_create_nonce('listeo_analytics_dashboard'),
            // Translatable strings for JavaScript
            'i18n' => array(
                // General
                'loading' => __('Loading...', 'listeo_core'),
                'errorLoading' => __('Error loading data. Please try again.', 'listeo_core'),

                // Filters
                'searchListings' => __('Search listings...', 'listeo_core'),

                // Chart labels
                'totalViews' => __('Total Views', 'listeo_core'),
                'uniqueVisitors' => __('Unique Visitors', 'listeo_core'),
                'uniqueViews' => __('Unique Views', 'listeo_core'),
                'totalBookings' => __('Total Bookings', 'listeo_core'),
                'confirmedBookings' => __('Confirmed Bookings', 'listeo_core'),
                'paidBookings' => __('Paid Bookings', 'listeo_core'),
                'totalMessages' => __('Total Messages', 'listeo_core'),
                'activeConversations' => __('Active Conversations', 'listeo_core'),
                'conversations' => __('Conversations', 'listeo_core'),
                'searchCount' => __('Search Count', 'listeo_core'),

                // Tooltip labels
                'views' => __('Views', 'listeo_core'),
                'searches' => __('Searches', 'listeo_core'),

                // Contact methods
                'sendMessageButton' => __('Send Message button', 'listeo_core'),

                // No data messages
                'noViewData' => __('No view data available', 'listeo_core'),
                'noListingData' => __('No listing data available', 'listeo_core'),
                'noContactData' => __('No contact data available', 'listeo_core'),
                'noSocialData' => __('No social media data available', 'listeo_core'),
                'noBookingData' => __('No booking data available', 'listeo_core'),
                'noBookingStatusData' => __('No booking status data available', 'listeo_core'),
                'noMessageData' => __('No message data available', 'listeo_core'),
                'noSearchQueryData' => __('No search query data available', 'listeo_core'),
                'noConversationSourceData' => __('No conversation source data available', 'listeo_core'),

                // Dynamic titles
                'topAISearchQueries' => __('Top %d AI Search Queries', 'listeo_core'),

                // Error messages
                'failedToLoadConversation' => __('Failed to load conversation.', 'listeo_core'),
                'conversationLoadError' => __('An error occurred while loading the conversation.', 'listeo_core'),
            )
        ));
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'listeo_core'));
        }

        // Include conversations table class
        require_once LISTEO_PLUGIN_DIR . 'includes/class-listeo-core-analytics-conversations-table.php';

        $queries = Listeo_Core_Analytics_Queries::instance();
        $db = Listeo_Core_Analytics_DB::instance();

        // Get current settings
        $enabled = get_option('listeo_analytics_enabled', 'yes') === 'yes';
        $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
        $selected_listing_id = isset($_GET['listing_id']) ? absint($_GET['listing_id']) : 0;
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

        // Get all listings for the dropdown (always get all to populate the filter)
        $all_listings = $queries->get_all_listings_performance($days, 0);

        // Get analytics data (filtered by listing if selected)
        $overview = $selected_listing_id > 0 ? $queries->get_overview_stats_for_listing($selected_listing_id, $days) : $queries->get_overview_stats($days);
        $comparison = $selected_listing_id > 0 ? $queries->get_comparison_data_for_listing($selected_listing_id, $days) : $queries->get_comparison_data($days);
        $views_over_time = $queries->get_views_over_time($selected_listing_id, $days);
        $engagement_breakdown = $selected_listing_id > 0 ? $queries->get_engagement_breakdown_for_listing($selected_listing_id, $days) : $queries->get_engagement_breakdown($days);

        // Top listings (only show when viewing all listings, not when filtering by specific listing)
        $top_listings = $selected_listing_id > 0 ? array() : $queries->get_top_listings(10, $days);

        // Contact and social data
        $contact_clicks = $queries->get_contact_clicks($selected_listing_id, $days);
        $social_stats = $queries->get_social_media_stats($selected_listing_id, $days);

        // AI Chat Search stats (if plugin is active)
        $ai_search_active = class_exists('Listeo_AI_Search_Analytics');
        $ai_search_stats = null;
        if ($ai_search_active) {
            $ai_search_stats = Listeo_AI_Search_Analytics::get_analytics($days);
        }

        // Booking & Revenue stats
        $booking_stats = $queries->get_booking_stats($selected_listing_id, $days);
        $booking_comparison = $queries->get_booking_comparison($selected_listing_id, $days);
        $revenue_stats = $queries->get_revenue_stats($selected_listing_id, $days);
        $revenue_comparison = $queries->get_revenue_comparison($selected_listing_id, $days);
        $bookings_over_time = $queries->get_bookings_over_time($selected_listing_id, $days);
        $booking_status_breakdown = $queries->get_booking_status_breakdown($selected_listing_id, $days);
        $booking_conversion = $queries->get_booking_conversion_rate($selected_listing_id, $days);

        // Message stats
        $message_stats = $queries->get_message_stats($days);
        $message_comparison = $queries->get_message_comparison($days);

        // Conversations table (always initialize since all tabs load at once)
        $conversations_table = new Listeo_Core_Analytics_Conversations_Table();
        $conversations_table->prepare_items();

        // Include template
        include LISTEO_PLUGIN_DIR . 'templates/admin/analytics-dashboard.php';
    }

    /**
     * Save settings
     */
    public function save_settings() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'listeo_core'));
        }

        // Verify nonce
        check_admin_referer('listeo_analytics_settings', 'listeo_analytics_nonce');

        // Get enabled setting (store as 'yes'/'no' for better stability)
        $enabled = isset($_POST['listeo_analytics_enabled']) ? 'yes' : 'no';
        update_option('listeo_analytics_enabled', $enabled);

        // Clear cache
        $queries = Listeo_Core_Analytics_Queries::instance();
        $queries->clear_cache();

        // Redirect back
        wp_safe_redirect(add_query_arg(array(
            'page' => 'listeo-analytics',
            'settings-updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * AJAX handler for top listings
     */
    public function ajax_get_top_listings() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'listeo_core')));
            return;
        }

        $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 10;
        $days = isset($_GET['days']) ? absint($_GET['days']) : 30;

        $queries = Listeo_Core_Analytics_Queries::instance();
        $top_listings = $queries->get_top_listings($limit, $days);

        wp_send_json_success(array(
            'listings' => $top_listings
        ));
    }

    /**
     * AJAX handler for conversation detail
     */
    public function ajax_get_conversation_detail() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'listeo_analytics_dashboard')) {
            wp_send_json_error(array('message' => __('Invalid nonce', 'listeo_core')));
            return;
        }

        $conversation_id = isset($_GET['conversation_id']) ? absint($_GET['conversation_id']) : 0;

        if (!$conversation_id) {
            wp_send_json_error(array('message' => __('Invalid conversation ID', 'listeo_core')));
            return;
        }

        // Get conversation data
        $messages_obj = new Listeo_Core_Messages();
        $conversation = $messages_obj->get_conversation($conversation_id);

        if (!$conversation || empty($conversation)) {
            wp_send_json_error(array('message' => __('Conversation not found', 'listeo_core')));
            return;
        }

        $user1 = get_userdata($conversation[0]->user_1);
        $user2 = get_userdata($conversation[0]->user_2);

        // Get user names
        $name1 = $user1 ? ((!empty($user1->first_name) && !empty($user1->last_name))
            ? $user1->first_name . ' ' . $user1->last_name
            : $user1->user_nicename) : __('Deleted User', 'listeo_core');

        $name2 = $user2 ? ((!empty($user2->first_name) && !empty($user2->last_name))
            ? $user2->first_name . ' ' . $user2->last_name
            : $user2->user_nicename) : __('Deleted User', 'listeo_core');

        $referral = $messages_obj->get_conversation_referral($conversation[0]->referral);

        // Build HTML
        ob_start();
        ?>
        <div class="conversation-meta-info">
            <p>
                <strong><?php esc_html_e('Between:', 'listeo_core'); ?></strong>
                <?php if ($user1): ?>
                    <a href="<?php echo esc_url(get_author_posts_url($user1->ID)); ?>" target="_blank">
                        <?php echo esc_html($name1); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html($name1); ?>
                <?php endif; ?>
                <?php esc_html_e('and', 'listeo_core'); ?>
                <?php if ($user2): ?>
                    <a href="<?php echo esc_url(get_author_posts_url($user2->ID)); ?>" target="_blank">
                        <?php echo esc_html($name2); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html($name2); ?>
                <?php endif; ?>
            </p>
            <?php if ($referral): ?>
                <p>
                    <strong><?php esc_html_e('Source:', 'listeo_core'); ?></strong>
                    <span><?php echo wp_kses_post($referral); ?></span>
                </p>
            <?php endif; ?>
        </div>

        <div class="conversation-messages-list">
            <?php
            $messages = $messages_obj->get_single_conversation('1', $conversation_id);
            if ($messages && count($messages) > 0):
                foreach ($messages as $message):
                    $is_user1 = ($message->sender_id == ($user1 ? $user1->ID : 0));
                    ?>
                    <div class="message-bubble <?php echo $is_user1 ? 'user-1' : 'user-2'; ?>">
                        <div class="message-avatar">
                            <a href="<?php echo esc_url(get_author_posts_url($message->sender_id)); ?>" target="_blank">
                                <?php echo get_avatar($message->sender_id, 50); ?>
                            </a>
                        </div>
                        <div class="message-content">
                            <div class="message-text"><?php echo wp_kses_post(wpautop($message->message)); ?></div>
                            <?php if (!empty($message->attachment_url)): ?>
                                <div class="message-attachment">
                                    <i class="fa fa-paperclip"></i>
                                    <a href="<?php echo esc_url($message->attachment_url); ?>" target="_blank">
                                        <?php echo esc_html($message->attachment_name); ?>
                                    </a>
                                    <span class="attachment-size">(<?php echo size_format($message->attachment_size); ?>)</span>
                                </div>
                            <?php endif; ?>
                            <div class="message-time">
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $message->created_at); ?>
                            </div>
                        </div>
                    </div>
                <?php
                endforeach;
            else:
                ?>
                <p class="no-messages"><?php esc_html_e('No messages in this conversation yet.', 'listeo_core'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }
}

// Initialize
Listeo_Core_Analytics_Admin::instance();
