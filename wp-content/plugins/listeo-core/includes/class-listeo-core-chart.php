<?php

if (!defined('ABSPATH')) exit;

class Listeo_Core_Chart
{


    public $post_types = array('listing');
    public $stats = array('visits', 'unique', 'booking_click');

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


        add_shortcode('listeo_stats', array($this, 'display_chart'));
        add_shortcode('listeo_stats_full', array($this, 'display_chart_full'));

        add_action('wp_ajax_listeo_chart_refresh', array($this, 'ajax_listeo_chart_refresh'));
    }





    public function get_labels()
    {

        $days = absint(get_option('listeo_stats_default_stat_days', 5)) + 1;
        $dates = array();
        $date_from  = strtotime(date_i18n('Y-m-d', strtotime('-' . $days . 'days')));
        $date_to    = strtotime(date_i18n('Y-m-d'));

        while ($date_from <= $date_to) {
            $key = date_i18n('Y-m-d', $date_from);
            $dates[$key] = date_i18n('Y-m-d', $date_from); // Use Y-m-d format like stats page
            $date_from = strtotime('+1 day', $date_from);
        }

        return $dates;
    }




    /**
     * Chart Post IDs
     */
    public function post_ids()
    {
        $args = array(
            'post_type'       => array('listing'),
            'author'          => get_current_user_id(),
            'posts_per_page'  => 10,
        );
        $args = apply_filters('listeo_core_chart_loop_args', $args);
        $get_posts = get_posts($args);
        if (
            !$get_posts
        ) {
            return array();
        }
        $post_ids = array();
        foreach ($get_posts as $get_post) {
            $post_ids[] = $get_post->ID;
        }
        return $post_ids;
    }


    /** @return mixed  */

    function get_data()
    {

        $user_id = get_current_user_id();


        $days = absint(get_option('listeo_stats_default_stat_days', 5)) + 1;
        $args = array(
            'date_from'  => (date_i18n('Y-m-d', strtotime('-' . $days . 'days'))),
            'date_to'    => (date_i18n('Y-m-d')),
            'post_ids'       => $this->post_ids(),
        );

        $data = $this->get_raw_stats($args);
        return $data;
    }

    /**
     * Get Chart Datasets
     */
    public function get_posts_datasets($stats, $labels)
    {

        if (!is_array($stats)) {
            $stats    = $this->get_data();
        }
        if (empty($labels)) {
            $dates    = $this->get_labels();
        } else {
            $dates = $labels;
        }

        $datasets = array();

        /* Add post_id as key */
        $stat_datas = array();

        foreach ($stats as $stat) {
            $stat_datas[$stat->post_id][] = $stat;
        }


        /* Loop each post */
        foreach ($stat_datas as $post_id => $stats) {
            $title = get_the_title($post_id);

            if (!$title) {
                continue;
            }

            /* Add dataset */
            $datasets[$post_id] = array(
                'label' => "#{$post_id} {$title}",
                'data'  => array(),
            );

            /* Add each date to the dataset */
            foreach ($dates as $date => $date_label) {

                $datasets[$post_id]['data'][$date] = 0;
            }

            /* Fill in stats for existing dates */
            foreach ($stats as $stat) {

                if (isset($datasets[$post_id]['data'][$stat->stat_date])) {
                    $datasets[$post_id]['data'][$stat->stat_date] = $stat->stat_value;
                }
            }

            $datasets[$post_id]['data'] = array_values($datasets[$post_id]['data']);
        }


        return $datasets;
    }



 
/**
 * Query Stats Data From Database in Simple Array - FIXED VERSION
 */
public function get_raw_stats($args)
{
    global $wpdb;

    $where = array();
    $current_user_id = get_current_user_id();

    // Validate stat_id
    if (isset($args['stat_id']) && is_scalar($args['stat_id'])) {
        $where[] = $wpdb->prepare('AND s.stat_id = %d', (int) $args['stat_id']);
    }

    // Validate post_ids as array of integers AND ensure they belong to current user
    if (!empty($args['post_ids']) && is_array($args['post_ids'])) {
        $post_ids = array_map('intval', $args['post_ids']);
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $where[] = $wpdb->prepare("AND s.post_id IN ($placeholders)", ...$post_ids);
    }

    // Safe date range
    if (!empty($args['date_from']) && !empty($args['date_to'])) {
        $where[] = $wpdb->prepare('AND s.stat_date BETWEEN %s AND %s', $args['date_from'], $args['date_to']);
    }

    $where_sql = implode(' ', $where);

    // JOIN with posts table to ensure only current user's posts are included
    $sql = "SELECT s.* FROM {$wpdb->prefix}listeo_core_stats s 
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID 
            WHERE p.post_author = %d 
            AND p.post_type = 'listing' 
            {$where_sql}";
    
    $data = $wpdb->get_results($wpdb->prepare($sql, $current_user_id));

    return apply_filters('listeo_stats_data_raw_stats', $data, $args);
}



    /**
     * Nice Color Schemes
     */
    public function chart_colors()
    {
        $colors = array(
            '26, 188, 156',
            '46, 204, 113',
            '52, 152, 219',
            '155, 89, 182',
            '52, 73, 94',
            '241, 196, 15',
            '230, 126, 34',
            '231, 76, 60',
            '236, 240, 241',
            '149, 165, 166',
            '255, 204, 188',
            '206, 160, 228',
            '199, 44, 28',
            '255, 140, 200',
            '41, 197, 255',
            '255, 194, 155',
            '255, 124, 108',
            '94, 252, 161',
            '46, 204, 113',
            '140, 154, 169',
            '255, 207, 75',
            '255, 146, 107',
            '255, 108, 168',
            '18, 151, 224',
            '155, 89, 182',
            '80, 80, 80',
            '231, 76, 60',
        );
        return $colors;
    }


    function display_chart()
    {
        ob_start();
        wp_enqueue_script('listeo_core-chart-min'); // script

        // Get aggregated data for total views and unique visitors
        $days = absint(get_option('listeo_stats_default_stat_days', 5)) + 1;
        $args = array(
            'date_from'  => (date_i18n('Y-m-d', strtotime('-' . $days . 'days'))),
            'date_to'    => (date_i18n('Y-m-d')),
            'post_ids'   => $this->post_ids(),
        );

        $raw_stats = $this->get_raw_stats($args);
        $dates = $this->get_labels();
        $labels = json_encode(array_values($dates));

        // Aggregate data by date
        $aggregated_data = array();
        foreach ($dates as $date => $date_label) {
            $aggregated_data[$date] = array(
                'visits' => 0,
                'unique' => 0
            );
        }

        // Sum up all stats by date
        foreach ($raw_stats as $stat) {
            if (isset($aggregated_data[$stat->stat_date])) {
                if ($stat->stat_id === 'visits') {
                    $aggregated_data[$stat->stat_date]['visits'] += (int) $stat->stat_value;
                } elseif ($stat->stat_id === 'unique') {
                    $aggregated_data[$stat->stat_date]['unique'] += (int) $stat->stat_value;
                }
            }
        }

        // Extract arrays for chart
        $total_views = array_values(array_map(function($data) {
            return $data['visits'];
        }, $aggregated_data));

        $unique_visitors = array_values(array_map(function($data) {
            return $data['unique'];
        }, $aggregated_data));

?>
        <div class="content chart-box-content">
            <!-- Chart -->
            <div class="chart chart-container">
                <canvas id="chart" width="100" height="45"></canvas>
            </div>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Chart.js v4 defaults
                Chart.defaults.color = '#888';
                Chart.defaults.font.size = 14;
                var ctx = document.getElementById('chart').getContext('2d');

                window.chart = new Chart(ctx, {
                    type: 'line',
                    // The data for our dataset
                    data: {
                        labels: <?php echo $labels; ?>,
                        // Information about the dataset
                        datasets: [
                            {
                                label: '<?php echo esc_html__('Total Views', 'listeo_core'); ?>',
                                backgroundColor: 'rgba(46, 204, 113, 0.08)',
                                borderColor: 'rgba(46, 204, 113, 1)',
                                borderWidth: 3,
                                data: <?php echo json_encode($total_views); ?>,
                                tension: 0.4,
                                borderCapStyle: 'round',
                                borderJoinStyle: 'round',
                                pointRadius: 5,
                                pointHoverRadius: 6,
                                pointHitRadius: 10,
                                pointBackgroundColor: "#fff",
                                pointHoverBackgroundColor: "#fff",
                                pointBorderWidth: 2,
                                pointBorderColor: 'rgba(46, 204, 113, 1)',
                                fill: true,
                            },
                            {
                                label: '<?php echo esc_html__('Unique Visitors', 'listeo_core'); ?>',
                                backgroundColor: 'rgba(52, 152, 219, 0.08)',
                                borderColor: 'rgba(52, 152, 219, 1)',
                                borderWidth: 3,
                                data: <?php echo json_encode($unique_visitors); ?>,
                                tension: 0.4,
                                borderCapStyle: 'round',
                                borderJoinStyle: 'round',
                                pointRadius: 5,
                                pointHoverRadius: 6,
                                pointHitRadius: 10,
                                pointBackgroundColor: "#fff",
                                pointHoverBackgroundColor: "#fff",
                                pointBorderWidth: 2,
                                pointBorderColor: 'rgba(52, 152, 219, 1)',
                                fill: true,
                            }
                        ],
                    },

                    // Configuration options
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,

                        layout: {
                            padding: 10,
                        },

                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            title: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(51, 51, 51, 0.9)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                titleFont: {
                                    size: 13,
                                    weight: '600'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                displayColors: false,
                                padding: 10,
                                intersect: false,
                                mode: 'index',
                                cornerRadius: 6,
                                caretSize: 6,
                                caretPadding: 8
                            }
                        },

                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },

                        scales: {
                            y: {
                                display: true,
                                grid: {
                                    borderDash: [6, 10],
                                    color: "rgba(216, 216, 216, 0.5)",
                                    lineWidth: 1,
                                    drawBorder: false
                                },
                                min: 0,
                                ticks: {
                                    precision: 0,
                                    padding: 10
                                },
                                beginAtZero: true
                            },
                            x: {
                                display: true,
                                grid: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    precision: 0,
                                    padding: 5
                                }
                            }
                        },

                        elements: {
                            line: {
                                borderCapStyle: 'round',
                                borderJoinStyle: 'round'
                            },
                            point: {
                                hoverBorderWidth: 3
                            }
                        }
                    }
                })


            });
        </script>


    <?php
        $html = ob_get_clean();
        return $html;
    }


    function get_listings_ids()
    {

        $current_user = wp_get_current_user();
        $post_status = array('publish', 'pending_payment', 'expired', 'draft', 'pending');
        $listings = new WP_Query(
            array(
                'author'              => $current_user->ID,
                'fields'              => 'ids',
                'no_found_rows'       => true,
                'posts_per_page'      => -1,
                'post_type'           => 'listing',
                'post_status'         => $post_status,
            )
        );
        return $listings;
    }

    function ajax_listeo_chart_refresh()
{ // Add this at the beginning of ajax_listeo_chart_refresh()
      
    // Add security check
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    $date_start = sanitize_text_field($_POST['date_start']);
    $date_end = sanitize_text_field($_POST['date_end']);
    $type = sanitize_text_field($_POST['stat_type']);
    $listing = (isset($_POST['listing'])) ? sanitize_text_field($_POST['listing']) : false;

    // Always ensure we only get current user's posts
    $current_user_post_ids = $this->post_ids(); // This already filters by current user

    if (!empty($listing)) {
        if ($listing == 'show_all') {
            $post_ids = $current_user_post_ids;
        } else {
            $requested_ids = explode(" ", $listing);
            $requested_ids = array_map('intval', $requested_ids);
            // Only allow post IDs that belong to current user
            $post_ids = array_intersect($requested_ids, $current_user_post_ids);
        }
    } else {
        $post_ids = $current_user_post_ids;
    }

    // If no valid post IDs, return empty result
    if (empty($post_ids)) {
        wp_send_json(array('data' => array(), 'labels' => array()));
        die();
    }

    global $wpdb;

    // setting dates to MySQL style
    $date_start = esc_sql(date("Y-m-d H:i:s", strtotime($date_start)));
    $date_end = esc_sql(date("Y-m-d H:i:s", strtotime($date_end)));

    $args = array(
        'date_from'  => $date_start,
        'date_to'    => $date_end,
        'post_ids'   => $post_ids,
        'stat_id'    => $type
    );

    $data = $this->get_raw_stats($args);

    $dates = array();
    $date_from  = strtotime($date_start);
    $date_to    = strtotime($date_end);

    while ($date_from <= $date_to) {
        $key = date_i18n('Y-m-d', $date_from);
        $dates[$key] = date_i18n(get_option('date_format'), $date_from);
        $date_from = strtotime('+1 day', $date_from);
    }

    $labels = $dates;

    $postdata_raw = $this->get_posts_datasets($data, $labels);
    $postdata = array();
    $i = 0;
    foreach ($postdata_raw as $key => $dataset) {
        /* Colors */
        $colors = $this->chart_colors();
        $i++;
        $color = isset($colors[$i]) ? $colors[$i] : mt_rand(0, 255) . ',' . mt_rand(0, 255) . ',' . mt_rand(0, 255);
        $dataset['backgroundColor'] = 'rgba(' . $color . ',0.08)';
        $dataset['borderColor'] = 'rgba(' . $color . ',1)';
        $postdata[] = $dataset;
    }

    $result = array(
        'data' => $postdata,
        'labels' => $labels,
    );
    wp_send_json($result);
    die();
}




    function display_chart_full()
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your analytics.', 'listeo_core') . '</p>';
        }

        // Get current user ID
        $current_user_id = get_current_user_id();

        // Check if analytics is enabled
        $enabled = get_option('listeo_analytics_enabled', 'yes') === 'yes';
        if (!$enabled) {
            return '<div class="notification notice">' .
                   esc_html__('Analytics tracking is currently disabled. Please contact the site administrator.', 'listeo_core') .
                   '</div>';
        }

        // Enqueue required assets
        $theme_dir = get_template_directory_uri();
        wp_enqueue_style('font-awesome-5', $theme_dir . '/css/all.css', array(), '5.0');
        wp_enqueue_style('font-awesome-5-shims', $theme_dir . '/css/v4-shims.min.css', array('font-awesome-5'), '5.0');
        wp_enqueue_style('listeo-analytics-admin', LISTEO_CORE_URL . 'assets/css/analytics-admin.css', array('font-awesome-5'), '1.0.1');
        wp_enqueue_script('chart-js', LISTEO_CORE_URL . 'assets/js/chart.min.js', array(), '4.4.0', true);
        wp_enqueue_script('listeo-analytics-dashboard', LISTEO_CORE_URL . 'assets/js/listeo-analytics-dashboard.js', array('jquery', 'chart-js'), '1.0.4', true);

        // Get queries instance
        $queries = Listeo_Core_Analytics_Queries::instance();

        // Get filters from URL parameters
        $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
        $selected_listing_id = isset($_GET['listing_id']) ? absint($_GET['listing_id']) : 0;

        // Get all user's listings for the dropdown
        $all_listings = $queries->get_all_listings_performance($days, 0, $current_user_id);

        // Get analytics data (filtered by user and optionally by listing)
        $overview = $queries->get_overview_stats($days, $selected_listing_id, $current_user_id);
        $comparison = $queries->get_comparison_data($days, $selected_listing_id, $current_user_id);
        $views_over_time = $queries->get_views_over_time($selected_listing_id, $days, $current_user_id);

        // Top listings (only show when viewing all listings)
        $top_listings = $selected_listing_id > 0 ? array() : $queries->get_top_listings(10, $days, $current_user_id);

        // Contact and social data
        $contact_clicks = $queries->get_contact_clicks($selected_listing_id, $days, $current_user_id);
        $social_stats = $queries->get_social_media_stats($selected_listing_id, $days, $current_user_id);

        // AI Chat Search stats (if plugin is active)
        $ai_search_active = class_exists('Listeo_AI_Search_Analytics');
        $ai_search_stats = null;
        if ($ai_search_active) {
            $ai_search_stats = Listeo_AI_Search_Analytics::get_analytics($days);
        }

        // Booking & Revenue stats
        $booking_stats = $queries->get_booking_stats($selected_listing_id, $days, $current_user_id);
        $booking_comparison = $queries->get_booking_comparison($selected_listing_id, $days, $current_user_id);
        $revenue_stats = $queries->get_revenue_stats($selected_listing_id, $days, $current_user_id);
        $revenue_comparison = $queries->get_revenue_comparison($selected_listing_id, $days, $current_user_id);
        $bookings_over_time = $queries->get_bookings_over_time($selected_listing_id, $days, $current_user_id);
        $booking_status_breakdown = $queries->get_booking_status_breakdown($selected_listing_id, $days, $current_user_id);
        $booking_conversion = $queries->get_booking_conversion_rate($selected_listing_id, $days, $current_user_id);

        // Localize script data
        wp_localize_script('listeo-analytics-dashboard', 'listeoAnalyticsDashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'selected_listing_id' => $selected_listing_id,
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

        // Start output buffering
        ob_start();

        // Include the frontend analytics template (without admin elements)
        include LISTEO_PLUGIN_DIR . 'templates/account/analytics-frontend.php';

        $html = ob_get_clean();
        return $html;
    }
}
