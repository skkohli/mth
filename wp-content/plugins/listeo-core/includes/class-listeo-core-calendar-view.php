<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;
/**
 * Listeo_Core_Listing class
 */
class Listeo_Core_Calendar_View
{

    /**
     * The single instance of the class.
     *
     * @var self
     * @since  1.26
     */
    private static $_instance = null;

    /**
     * Allows for accessing single instance of class. Class should only be constructed once per call.
     *
     * @since  1.26
     * @static
     * @return self Main instance.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {

        add_action('wp_enqueue_scripts', array($this, 'listeo_calendar_view_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'listeo_calendar_view_style'));
        add_shortcode('listeo_calendar_view', array($this, 'calendar_view'));
        add_shortcode('listeo_user_calendar_view', array($this, 'user_calendar_view'));
        add_action("wp_ajax_listeo_get_calendar_view_events", array($this, 'ajax_get_events'));
        add_action("wp_ajax_listeo_get_calendar_view_user_events", array($this, 'ajax_get_user_events'));
        
        add_action("wp_ajax_listeo_get_calendar_view_single_events", array($this, 'ajax_get_single_events'));
        add_action("wp_ajax_nopriv_listeo_get_calendar_view_single_events", array($this, 'ajax_get_single_events'));
        add_action("wp_ajax_listeo_get_calendar_view_event_details", array($this, 'ajax_get_event_details'));
        add_action("wp_ajax_listeo_get_calendar_view_user_event_details", array($this, 'ajax_get_user_event_details'));
        
        // Add AJAX action for getting daily prices
        add_action("wp_ajax_listeo_get_calendar_daily_prices", array($this, 'ajax_get_daily_prices'));
        add_action("wp_ajax_nopriv_listeo_get_calendar_daily_prices", array($this, 'ajax_get_daily_prices'));
        
        // Add cache invalidation hooks
        add_action('save_post', array($this, 'clear_cache_on_listing_update'), 10, 1);
        add_action('listeo_booking_confirmed', array($this, 'clear_cache_on_booking_change'), 10, 1);
        add_action('listeo_booking_cancelled', array($this, 'clear_cache_on_booking_change'), 10, 1);
        add_action('updated_post_meta', array($this, 'clear_cache_on_meta_update'), 10, 4);
        
        // Add test action for cache verification
        add_action('wp_ajax_test_listeo_cache', array($this, 'test_cache_functionality'));
       
    }



    /**
     * Stats Script
     *
     * Load Combined Stats JS if Debug is Disabled.
     *
     * @since 2.7.0
     */
    function listeo_calendar_view_scripts()
    {
        $bookings_calendar_page = get_option('listeo_bookings_calendar_page');
        $bookings_user_calendar_page = get_option('listeo_bookings_user_calendar_page');
        global $post;
        // Single JS to track listings.
        
         if ((isset($post) && $post->ID == $bookings_calendar_page) || (isset($post) && $post->ID == $bookings_user_calendar_page)  || is_singular('listing')) {
            $language = get_option('listeo_calendar_view_lang','en');
            wp_enqueue_script('listeo-core-fullcalendar', LISTEO_CORE_URL . 'assets/js/fullcalendar.min.js', array('jquery'), 1.0, true);
           
            // Add currency information for price display
            $currency_abbr = get_option('listeo_currency');
            $currency_position = get_option('listeo_currency_postion');
            $currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
            
            $data = array(
                'language'   => $language,
                'currency_symbol' => $currency_symbol,
                'currency_position' => $currency_position
            );
            if($post->ID == $bookings_calendar_page){
                wp_enqueue_script('listeo-core-fullcalendar-view', LISTEO_CORE_URL . 'assets/js/listeo.fullcalendar.js', array('jquery'), 1.0, true);
                wp_localize_script('listeo-core-fullcalendar-view', 'listeoCal', $data); 
            } else if($post->ID == $bookings_user_calendar_page){
                wp_enqueue_script('listeo-core-fullcalendar-user-view', LISTEO_CORE_URL . 'assets/js/listeo.fullcalendar.user.js', array('jquery'), 1.0, true);
                wp_localize_script('listeo-core-fullcalendar-user-view', 'listeoCal', $data); 
            }  else {
                wp_enqueue_script('listeo-core-fullcalendar-single-view', LISTEO_CORE_URL . 'assets/js/listeo.fullcalendar.single.js', array('jquery'), 1.0, true);
                wp_localize_script('listeo-core-fullcalendar-single-view', 'listeoCal', $data); 
            }
            if ($language != 'en') {
                wp_enqueue_script('listeo-core-fullcalendar-lang', LISTEO_CORE_URL . 'assets/js/locales/' . $language . '.js', array('jquery', 'listeo-core-fullcalendar'), 2.0, true);
            }
        }
       

    }

    function listeo_calendar_view_style()
    {

        wp_register_style('listeo-core-fullcalendar', LISTEO_CORE_URL . 'assets/css/fullcalendar.min.css', array(), '1.0');
        wp_enqueue_style('listeo-core-fullcalendar');
        // Single JS to track listings.


    }

    function calendar_view()
    {
        ob_start();
        $users = new Listeo_Core_Users;
        $listings = $users->get_agent_listings('', 0, -1);
        $template_loader = new Listeo_Core_Template_Loader;
        $template_loader->set_template_data(
            array(
                'message' => '',
                'listings' => $listings->posts,
            )
        )->get_template_part('account/calendar-view');
        $html = ob_get_clean();
        return $html;
    }
    

    function user_calendar_view()
    {
        ob_start();
        $users = new Listeo_Core_Users;
        $listings = $users->get_agent_listings('', 0, -1);
        $template_loader = new Listeo_Core_Template_Loader;
        $template_loader->set_template_data(
            array(
                'message' => '',
                'listings' => $listings->posts,
            )
        )->get_template_part('account/user-calendar-view');
        $html = ob_get_clean();
        return $html;
    }
    

    function ajax_get_events()
    {
        $users = new Listeo_Core_Users;

        $listings = $users->get_agent_listings('', 0, -1);
        $args = array(
            'owner_id' => get_current_user_id(),
            'type' => 'reservation',

        );

        $dates_args = $_POST['dates'];
        $date_start = $dates_args['startStr'];
        $date_end = $dates_args['endStr'];
        if (isset($_POST['listing_id']) &&  $_POST['listing_id'] != 'show_all') $args['listing_id'] = $_POST['listing_id'];
        if (isset($_POST['listing_status']) && $_POST['listing_status'] != 'show_all') $args['status'] = $_POST['listing_status'];
        if (isset($_POST['booking_author']) && $_POST['booking_author'] != 'show_all') $args['bookings_author'] = $_POST['booking_author'];



        if (isset($_GET['status'])) {

            $args['status'] = $_GET['status'];
        }
        
        $bookings = new Listeo_Core_Bookings_Calendar;
        $data = $bookings->get_bookings(
            $date_start,
            $date_end,
            $args,
            'booking_date',
            $limit = ''
        );
      

        $events = array();
        if ($data) {

            //parse booking for fullcalendar
            foreach ($data as $key => $booking) {

                $details = json_decode($booking['comment']);
                // title start
                $title = array();
                if (isset($details->first_name)) $title[] = esc_html(stripslashes($details->first_name));
                if (isset($details->last_name)) $title[] = esc_html(stripslashes($details->last_name));
                $title[] = ' - ';
                $title[] = get_the_title($booking['listing_id']);
              //  $title[] = ' ('.$booking['status'].')';

                $event_title = implode(' ', $title);
                // title end

                //status color

                $booking_status = $booking['status'];
                if (
                    $booking_status != 'paid' && isset($booking['order_id']) && !empty($booking['order_id']) && $booking_status == 'confirmed'
                ) {
                    $order = wc_get_order($booking['order_id']);
                    if ($order) {
                        $payment_url = $order->get_checkout_payment_url();

                        $order_data = $order->get_data();

                        $order_status = $order_data['status'];
                    }
                    if (new DateTime() > new DateTime($booking['expiring'])) {
                        $booking_status = 'expired';
                    }
                }
                switch ($booking_status) {
                    case 'paid':
                        $bgcolor = '#64bc36';
                        break;
                    case 'pay_to_confirm':
                    case 'confirmed':
                        $bgcolor = '#ECBE1F';
                        break;
                    case 'waiting':
                        $bgcolor = '#61b2db';
                        break;

                    case 'expired':
                        $bgcolor = '#ee3535';
                        break;

                    default:
                        $bgcolor = '#aaa';
                        break;
                }

                $args = array(
                    'id'        => $booking['ID'],
                    'title'     => $event_title,
                    'start'     => $booking['date_start'],
                    'end'       => $booking['date_end'],
                    'description'       => $booking['price'],
                    'backgroundColor' => $bgcolor,
                    'borderColor' => $bgcolor,

                );
                if ($booking_status == 'owner_reservations') {
                    $args["allDay"] = true;
                    $args["display"] = 'background';
                }
                $events[] = $args;
            }
        }
        // $data[] = array(
        //     'id'   => 1,
        //     'title'   => 'test',
        //     'start'   => '2022-08-08',
        //     'end'   => '2022-08-18',
        // );

        echo json_encode($events, JSON_UNESCAPED_UNICODE);
        wp_die();
    }

    function ajax_get_user_events()
    {
        $users = new Listeo_Core_Users;

       
        $args = array(
            'bookings_author' => get_current_user_id(),
            'type' => 'reservation',

        );

        $dates_args = $_POST['dates'];
        $date_start = $dates_args['startStr'];
        $date_end = $dates_args['endStr'];
        if (isset($_POST['listing_id']) &&  $_POST['listing_id'] != 'show_all') $args['listing_id'] = $_POST['listing_id'];
        if (isset($_POST['listing_status']) && $_POST['listing_status'] != 'show_all') $args['status'] = $_POST['listing_status'];
 


        if (isset($_GET['status'])) {

            $args['status'] = $_GET['status'];
        }
        $bookings = new Listeo_Core_Bookings_Calendar;
        $data = $bookings->get_bookings(
            $date_start,
            $date_end,
            $args,
            'booking_date',
            $limit = ''
        );
        // return 

        $events = array();
        if ($data) {

            //parse booking for fullcalendar
            foreach ($data as $key => $booking) {

                $details = json_decode($booking['comment']);
                // title start
                $title = array();
                if (isset($details->first_name)) $title[] = esc_html(stripslashes($details->first_name));
                if (isset($details->last_name)) $title[] = esc_html(stripslashes($details->last_name));
                $title[] = ' - ';
                $title[] = get_the_title($booking['listing_id']);
              //  $title[] = ' ('.$booking['status'].')';

                $event_title = implode(' ', $title);
                // title end

                //status color

                $booking_status = $booking['status'];
                if (
                    $booking_status != 'paid' && isset($booking['order_id']) && !empty($booking['order_id']) && $booking_status == 'confirmed'
                ) {
                    $order = wc_get_order($booking['order_id']);
                    if ($order) {
                        $payment_url = $order->get_checkout_payment_url();

                        $order_data = $order->get_data();

                        $order_status = $order_data['status'];
                    }
                    if (new DateTime() > new DateTime($booking['expiring'])) {
                        $booking_status = 'expired';
                    }
                }
                switch ($booking_status) {
                    case 'paid':
                        $bgcolor = '#64bc36';
                        break;
                    case 'pay_to_confirm':
                    case 'confirmed':
                        $bgcolor = '#ECBE1F';
                        break;
                    case 'waiting':
                        $bgcolor = '#61b2db';
                        break;

                    case 'expired':
                        $bgcolor = '#ee3535';
                        break;

                    default:
                        $bgcolor = '#aaa';
                        break;
                }

                $args = array(
                    'id'        => $booking['ID'],
                    'title'     => $event_title,
                    'start'     => $booking['date_start'],
                    'end'       => $booking['date_end'],
                    'description'       => $booking['price'],
                    'backgroundColor' => $bgcolor,
                    'borderColor' => $bgcolor,

                );
                if ($booking_status == 'owner_reservations') {
                    $args["allDay"] = true;
                    $args["display"] = 'background';
                }
                $events[] = $args;
            }
        }
        // $data[] = array(
        //     'id'   => 1,
        //     'title'   => 'test',
        //     'start'   => '2022-08-08',
        //     'end'   => '2022-08-18',
        // );

        echo json_encode($events, JSON_UNESCAPED_UNICODE);
        wp_die();
    }

    function ajax_get_single_events(){
        $users = new Listeo_Core_Users;

        global $post;
        
      
        $args = array(
            
            'type' => 'reservation',
        );
        $args['listing_id'] = $_POST['listing_id'];
        //  $args['owner_id'] =get_post_field('post_author', $_POST['listing_id']);


        // get listing type
        $listing_type = get_post_meta($args['listing_id'], '_listing_type', true);
        $dates_args = $_POST['dates'];
        $date_start = $dates_args['startStr'];
      
        $date_end = $dates_args['endStr'];
        
        $type = get_option('listeo_show_calendar_single_type','owner');
        $bookings = new Listeo_Core_Bookings_Calendar;
        
        $data = $bookings->get_bookings(
            $date_start,
            $date_end,
            $args,
            'booking_date',
            $limit = '',
            $offset = '',
            $type
        );

        // Fetch iCal imported events if enabled
        $show_ical = get_option('listeo_show_calendar_single_ical');
        if ($show_ical) {
            global $wpdb;
            $ical_listing_id = absint($args['listing_id']);
            $date_start_sql = date("Y-m-d H:i:s", strtotime($date_start));
            $date_end_sql = date("Y-m-d H:i:s", strtotime($date_end));

            $ical_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$wpdb->prefix}bookings_calendar`
                    WHERE listing_id = %d
                    AND status LIKE 'external%%'
                    AND type = 'reservation'
                    AND date_start < %s
                    AND date_end > %s",
                    $ical_listing_id,
                    $date_end_sql,
                    $date_start_sql
                ),
                "ARRAY_A"
            );

            if ($ical_data) {
                $data = is_array($data) ? array_merge($data, $ical_data) : $ical_data;
            }
        }

        $events = array();
        if ($data) {

            //parse booking for fullcalendar
            foreach ($data as $key => $booking) {

                $details = json_decode($booking['comment']);
                $booking_status = $booking['status'];
                $is_external = Listeo_Core_Bookings_Calendar::is_booking_external($booking_status);

                // Build event title
                if ($is_external && $details) {
                    $event_title = !empty($details->summary) ? esc_html($details->summary) : esc_html__('iCal Import', 'listeo_core');
                } else {
                    $title = array();
                    $event_title = implode(' ', $title);
                }

                // Determine background color
                if ($is_external) {
                    $bgcolor = '#8e44ad';
                } else {
                    if (
                        $booking_status != 'paid' && isset($booking['order_id']) && !empty($booking['order_id']) && $booking_status == 'confirmed'
                    ) {
                        $order = wc_get_order($booking['order_id']);
                        if ($order) {
                            $payment_url = $order->get_checkout_payment_url();
                            $order_data = $order->get_data();
                            $order_status = $order_data['status'];
                        }
                        if (new DateTime() > new DateTime($booking['expiring'])) {
                            $booking_status = 'expired';
                        }
                    }
                    switch ($booking_status) {
                        case 'paid':
                            $bgcolor = '#64bc36';
                            break;
                        case 'pay_to_confirm':
                        case 'confirmed':
                            $bgcolor = '#ECBE1F';
                            break;
                        case 'waiting':
                            $bgcolor = '#61b2db';
                            break;
                        case 'owner_reservations':
                        case 'expired':
                            $bgcolor = '#ee3535';
                            break;
                        default:
                            $bgcolor = '#aaa';
                            break;
                    }
                }

                $event_args = array(
                    'id'        => $booking['ID'],
                    'title'     => $event_title,
                    'start'     => $booking['date_start'],
                    'end'       => $booking['date_end'],
                    'description'       => $is_external ? '' : $booking['price'],
                    'backgroundColor' => $bgcolor,
                    'borderColor' => $bgcolor,
                );
                if ($is_external) {
                    $event_args['allDay'] = true;
                    $event_args['display'] = 'background';
                } elseif (listeo_get_booking_type($booking['listing_id']) == 'rental' || $booking_status == 'owner_reservations') {
                    $event_args['allDay'] = true;
                    $event_args['display'] = 'background';
                }

                $events[] = $event_args;

            }
        }
        // $events[] = array(
        //     'id'   => 1,
        //     'title'   => 'test',
        //     'start'   => '2023-01-30',
        //     'end'   => '2023-01-32',
        // );

        echo json_encode($events, JSON_UNESCAPED_UNICODE);
        wp_die();
    }

    function ajax_get_event_details()
    {
        $booking_id = $_POST['id'];
        // sanitize the booking id
        $booking_id = intval($booking_id);
        if (!$booking_id) {
            wp_send_json_error(array('message' => esc_html__('Invalid booking ID', 'listeo_core')));
            return;
        }

        $template_loader = new Listeo_Core_Template_Loader;
        $bookings = new Listeo_Core_Bookings_Calendar;
        $booking_data = $bookings->get_booking($booking_id);
        ob_start();
        $template_loader->set_template_data($booking_data)->get_template_part('booking/content-booking-calendar');
        $result['html'] = ob_get_clean();
        wp_send_json_success($result);
    }

    function ajax_get_user_event_details()
    {
        $booking_id = $_POST['id'];

        $template_loader = new Listeo_Core_Template_Loader;
        $bookings = new Listeo_Core_Bookings_Calendar;
        $booking_data = $bookings->get_booking($booking_id);
        ob_start();
        $template_loader->set_template_data($booking_data)->get_template_part('booking/content-user-booking-calendar');
        $result['html'] = ob_get_clean();
        wp_send_json_success($result);
    }

    /**
     * AJAX function to get daily prices for calendar dates
     */
    function ajax_get_daily_prices()
    {
        if (!isset($_POST['listing_id']) || !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        $listing_id = intval($_POST['listing_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Check cache first
        $cache_key = "listeo_daily_prices_{$listing_id}_{$start_date}_{$end_date}";
        $cached_prices = get_transient($cache_key);
        
        if ($cached_prices !== false) {
            wp_send_json_success($cached_prices);
            return;
        }

        // Get listing type to determine which price fields to use
        $listing_type = get_post_meta($listing_id, '_listing_type', true);

        // Get listing pricing information - try different price fields
        $normal_price = (float) get_post_meta($listing_id, '_normal_price', true);
        $weekend_price = (float) get_post_meta($listing_id, '_weekday_price', true);
        
        // If no booking prices, try basic price
        if (empty($normal_price)) {
            $basic_price = (float) get_post_meta($listing_id, '_price', true);
            if (!empty($basic_price)) {
                $normal_price = $basic_price;
                $weekend_price = $basic_price;
            }
        }
        
        // Try price range fields as fallback
        if (empty($normal_price)) {
            $price_min = (float) get_post_meta($listing_id, '_price_min', true);
            if (!empty($price_min)) {
                $normal_price = $price_min;
                $weekend_price = $price_min;
            }
        }
        
        // Debug: Log the retrieved prices
        
        if (empty($weekend_price)) {
            $weekend_price = $normal_price;
        }

        // If no prices are set, return empty array
        if (empty($normal_price)) {
            wp_send_json_success(array());
            return;
        }

        // Get special prices for the listing
        $special_prices_results = Listeo_Core_Bookings_Calendar::get_bookings(
            $start_date, 
            $end_date, 
            array('listing_id' => $listing_id, 'type' => 'special_price')
        );

        $special_prices = array();
        foreach ($special_prices_results as $result) {
            $special_prices[$result['date_start']] = $result['comment'];
        }

        // Generate daily prices for the date range
        $daily_prices = array();
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($start, $interval, $end);

        foreach ($period as $current_day) {
            $date = $current_day->format("Y-m-d");
            $sql_date = $current_day->format("Y-m-d 00:00:00");
            $day_of_week = $current_day->format("N"); // 1 = Monday, 7 = Sunday

            if (isset($special_prices[$sql_date])) {
                $price = $special_prices[$sql_date];
            } else {
                $start_of_week = intval(get_option('start_of_week')); // 0 = Sunday, 1 = Monday
                
                // Check if it's weekend
                $is_weekend = false;
                if ($start_of_week == 0) {
                    // Sunday start: Friday (5) and Saturday (6) are weekend
                    $is_weekend = ($day_of_week == 5 || $day_of_week == 6);
                } else {
                    // Monday start: Saturday (6) and Sunday (7) are weekend
                    $is_weekend = ($day_of_week == 6 || $day_of_week == 7);
                }

                $price = $is_weekend ? $weekend_price : $normal_price;
            }

            $daily_prices[$date] = $price;
        }

        // Cache the results with smart duration based on content
        // Shorter cache for dynamic pricing, longer for static pricing
        $cache_duration = $this->get_optimal_cache_duration($listing_id, $special_prices);
        set_transient($cache_key, $daily_prices, $cache_duration);

        wp_send_json_success($daily_prices);
    }

    /**
     * Get optimal cache duration - keeping it simple at 6 hours
     */
    private function get_optimal_cache_duration($listing_id, $special_prices) {
        // Always return 6 hours (21600 seconds)
        // This provides good balance between performance and data freshness
        return 6 * HOUR_IN_SECONDS;
    }

    /**
     * Test function to verify cache is working - you can call this via AJAX
     * URL: /wp-admin/admin-ajax.php?action=test_listeo_cache&listing_id=123
     */
    public function test_cache_functionality() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
        if (!$listing_id) {
            wp_die('Please provide listing_id parameter');
        }
        
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+30 days'));
        $cache_key = "listeo_daily_prices_{$listing_id}_{$start_date}_{$end_date}";
        
        echo "<h2>Cache Test for Listing #{$listing_id}</h2>";
        echo "<p><strong>Cache Key:</strong> {$cache_key}</p>";
        
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            echo "<p style='color: green;'>✅ <strong>CACHE HIT!</strong> Data found in cache.</p>";
            echo "<p>Cache contains " . count($cached_data) . " days of pricing data.</p>";
        } else {
            echo "<p style='color: red;'>❌ <strong>CACHE MISS!</strong> No data in cache.</p>";
        }
        
        // Show all cache entries for this listing
        global $wpdb;
        $cache_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
            '_transient_listeo_daily_prices_' . $listing_id . '_%'
        ));
        
        echo "<h3>All Cache Entries for Listing #{$listing_id}:</h3>";
        if ($cache_entries) {
            echo "<ul>";
            foreach ($cache_entries as $entry) {
                $timeout_key = str_replace('_transient_', '_transient_timeout_', $entry->option_name);
                $timeout = get_option($timeout_key);
                $expires = $timeout ? date('Y-m-d H:i:s', $timeout) : 'Never';
                echo "<li><strong>{$entry->option_name}</strong> (expires: {$expires})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No cache entries found for this listing.</p>";
        }
        
        wp_die();
    }

    /**
     * Clear daily prices cache when listing or booking data changes
     */
    public static function clear_daily_prices_cache($listing_id = null) {
        global $wpdb;
        
        if ($listing_id) {
            // Clear cache for specific listing
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_listeo_daily_prices_' . $listing_id . '_%',
                '_transient_timeout_listeo_daily_prices_' . $listing_id . '_%'
            ));
        } else {
            // Clear all daily prices cache
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_daily_prices_%' OR option_name LIKE '_transient_timeout_listeo_daily_prices_%'"
            );
        }
    }

    /**
     * Clear cache when listing is updated
     */
    public function clear_cache_on_listing_update($post_id) {
        $post_type = get_post_type($post_id);
        if ($post_type === 'listing') {
            self::clear_daily_prices_cache($post_id);
        }
    }

    /**
     * Clear cache when booking changes
     */
    public function clear_cache_on_booking_change($booking_data) {
        if (isset($booking_data['listing_id'])) {
            self::clear_daily_prices_cache($booking_data['listing_id']);
        }
    }

    /**
     * Clear cache when pricing meta fields are updated
     */
    public function clear_cache_on_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Price-related meta keys that should trigger cache clearing
        $price_meta_keys = array(
            '_normal_price',
            '_weekday_price', 
            '_price',
            '_price_min',
            '_price_max'
        );
        
        if (in_array($meta_key, $price_meta_keys) && get_post_type($post_id) === 'listing') {
            self::clear_daily_prices_cache($post_id);
        }
    }
}
