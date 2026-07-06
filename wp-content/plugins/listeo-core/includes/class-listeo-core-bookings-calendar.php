<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Listeo_Core_Bookings class.
 */
class Listeo_Core_Bookings_Calendar {

    public function __construct() {

        // for booking widget
        add_action('wp_ajax_check_avaliabity', array($this, 'ajax_check_avaliabity'));
        add_action('wp_ajax_nopriv_check_avaliabity', array($this, 'ajax_check_avaliabity'));  

        add_action('wp_ajax_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_calculate_price', array($this, 'ajax_calculate_price'));

        add_action('wp_ajax_listeo_validate_coupon', array($this, 'ajax_validate_coupon'));
        add_action('wp_ajax_nopriv_listeo_validate_coupon', array($this, 'ajax_validate_coupon'));
      
        add_action('wp_ajax_listeo_get_booking_states', array($this, 'ajax_get_states'));
        add_action('wp_ajax_nopriv_listeo_get_booking_states', array($this, 'ajax_get_states'));
        
        add_action('wp_ajax_listeo_calculate_booking_form_price', array($this, 'ajax_calculate_booking_form_price'));
        add_action('wp_ajax_nopriv_listeo_calculate_booking_form_price', array($this, 'ajax_calculate_booking_form_price'));

        add_action('wp_ajax_get_available_hours', array($this, 'ajax_get_available_hours'));
        add_action('wp_ajax_nopriv_get_available_hours', array($this, 'ajax_get_available_hours'));

        // add_action('wp_ajax_check_date_range_availability', array($this, 'ajax_check_date_range_availability'));
        // add_action('wp_ajax_nopriv_check_date_range_availability', array($this, 'ajax_check_date_range_availability'));

        add_action('wp_ajax_update_slots', array($this, 'ajax_update_slots'));
        add_action('wp_ajax_nopriv_update_slots', array($this, 'ajax_update_slots'));

        add_action('wp_ajax_get_carousel_slots_availability', array($this, 'ajax_get_carousel_slots_availability'));
        add_action('wp_ajax_nopriv_get_carousel_slots_availability', array($this, 'ajax_get_carousel_slots_availability'));

        add_action('wp_ajax_get_booked_hours', array($this, 'get_booked_hours'));
        add_action('wp_ajax_nopriv_get_booked_hours', array($this, 'get_booked_hours'));
        
       // add_action('wp_ajax_listeo_apply_coupon', array($this, 'ajax_widget_apply_coupon'));
       // add_action('wp_ajax_nopriv_listeo_apply_coupon', array($this, 'ajax_widget_apply_coupon'));  

        // for bookings dashboard
        add_action('wp_ajax_listeo_bookings_manage', array($this, 'ajax_listeo_bookings_manage'));
        add_action('wp_ajax_listeo_bookings_renew_booking', array($this, 'ajax_listeo_bookings_renew_booking'));

        // booking page shortcode and post handling
        add_shortcode( 'listeo_booking_confirmation', array( $this, 'listeo_core_booking' ) );
        add_shortcode( 'listeo_bookings', array( $this, 'listeo_core_dashboard_bookings' ) );
        add_shortcode( 'listeo_my_bookings', array( $this, 'listeo_core_dashboard_my_bookings' ) );

        // when woocoommerce is paid trigger function to change booking status
        add_action( 'woocommerce_order_status_completed', array( $this, 'booking_paid' ), 9, 3 );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'booking_refund' ), 9, 3 );
        // remove listeo booking products from shop
        add_action( 'woocommerce_product_query', array($this,'listeo_wc_pre_get_posts_query' ));

        // Validate booking expiration before allowing payment
        add_action( 'before_woocommerce_pay', array( $this, 'validate_booking_before_payment' ) );
        add_filter( 'woocommerce_order_needs_payment', array( $this, 'check_booking_expiration_for_payment' ), 10, 2 );

        // Fix PayPal Payments plugin compatibility on order-pay page
        // PPCP checks 'view_order' capability which is stricter than 'pay_for_order'
        // Scoped to order-pay page only to avoid running on every capability check site-wide
       // add_action( 'wp', array( $this, 'maybe_hook_view_order_cap' ) );

        add_action( 'listeo_core_check_for_expired_bookings', array( $this, 'check_for_expired_booking' ) );
        add_action( 'listeo_core_check_for_upcoming_booking', array( $this, 'check_for_upcoming_booking' ) );
        add_action( 'listeo_core_check_for_past_booking', array( $this, 'check_for_past_booking' ) );
        add_action( 'listeo_core_check_for_upcoming_payments', array( $this, 'check_for_upcoming_payments' ) );

        add_action('wp_ajax_listeo_core_booking_author_suggest', array( $this, 'listeo_core_booking_author_suggest'));
        add_action('wp_ajax_nopriv_listeo_core_booking_author_suggest', array( $this, 'listeo_core_booking_author_suggest'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_woocommerce_scripts'));

        // Ensure WooCommerce session exists on order-pay page for PayPal Payments compatibility
       // add_action( 'woocommerce_init', array( $this, 'ensure_session_on_order_pay' ) );
    }

    function ajax_get_states() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_states_nonce')) {
            wp_send_json_error('Invalid nonce');
        }


        $country = sanitize_text_field($_POST['country']);
        if (empty($country)) {
            wp_send_json_error('No country provided');
        }

        $states = WC()->countries->get_states( $country );
        wp_send_json_success( $states );
    }


    // Add this method to enqueue WooCommerce scripts on booking pages
    public function enqueue_woocommerce_scripts()
    {
        if (is_page(get_option('listeo_booking_confirmation_page'))) {
            // Check if this is a booking page
            global $post;


            // Enqueue WooCommerce country select script
            wp_enqueue_script('wc-country-select');
            wp_enqueue_script('wc-address-i18n');

            // Add WooCommerce frontend scripts
            $inline_js = "
                jQuery(function($) {
                    // Remove the WooCommerce event and use direct change handler
                    $('body').off('country_to_state_changed');

                    // Custom handler for country change
                    $(document).on('change', 'select[name=\"billing_country\"]', function() {
                        var country = $(this).val();
                        var stateWrapper = $('[name=\"billing_state\"]').closest('.input-without-icon');

                        if (!country) {
                            return;
                        }

                        // AJAX call to get states
                        $.ajax({
                            url: listeo_core.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'listeo_get_booking_states',
                                country: country,
                                nonce: '" . wp_create_nonce('listeo_states_nonce') . "'
                            },
                            success: function(response) {
                                $('.state-loading').remove();

                                if (response.success && response.data) {
                                    var states = response.data;
                                    var stateField = $('[name=\"billing_state\"]');

                                    if (Object.keys(states).length > 0) {
                                        // Country has states - create select
                                        var selectHtml = '<select name=\"billing_state\" id=\"billing_state\" class=\"address-field\" required>';
                                        selectHtml += '<option value=\"\">" . esc_js(__('Select a state…', 'listeo_core')) . "</option>';

                                        $.each(states, function(code, name) {
                                            selectHtml += '<option value=\"' + code + '\">' + name + '</option>';
                                        });
                                        selectHtml += '</select>';

                                        stateField.replaceWith(selectHtml);
                                    } else {
                                        // No states - create text input
                                        var inputHtml = '<input type=\"text\" name=\"billing_state\" id=\"billing_state\" class=\"input-text\" value=\"\" placeholder=\"" . esc_js(__('State', 'listeo_core')) . "\">';
                                        stateField.replaceWith(inputHtml);
                                    }
                                }
                            },
                            error: function() {
                                $('.state-loading').remove();
                            }
                        });
                    });

                    // Trigger on page load
                    $('select[name=\"billing_country\"]').trigger('change');
                });
            ";
            wp_add_inline_script('wc-country-select', $inline_js);
        }
    }


    static function listeo_core_booking_author_suggest() {

        // Security: Sanitize search term
        $search_term = isset($_REQUEST['term']) ? sanitize_text_field($_REQUEST['term']) : '';

        $suggestions = array();
        $posts = get_posts(array(
            's' => $search_term,
            'post_type' => 'listing',
            'posts_per_page' => 10, // Limit results
        ));
        global $post;
        $results = array();
        foreach ($posts as $post) {
            setup_postdata($post);
            $suggestion = array();
            $suggestion['label'] = html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8');
            $suggestion['link'] = get_permalink($post->ID);

            $suggestions[] = $suggestion;
        }

        // Security: Validate JSONP callback parameter
        // Only allow valid JavaScript function names (alphanumeric, underscore, dots)
        $callback = '';
        if (isset($_GET['callback'])) {
            $callback = $_GET['callback'];
            // Validate callback: only allow [a-zA-Z0-9_.$] characters
            if (!preg_match('/^[a-zA-Z_$][a-zA-Z0-9_.$]*$/', $callback)) {
                // Invalid callback - return regular JSON instead
                wp_send_json($suggestions);
                exit;
            }
            // Additional length check to prevent abuse
            if (strlen($callback) > 100) {
                wp_send_json($suggestions);
                exit;
            }
        }

        // Set proper Content-Type header
        header('Content-Type: application/javascript; charset=UTF-8');

        // Output JSONP or JSON
        if (!empty($callback)) {
            // JSONP response with validated callback
            echo esc_js($callback) . '(' . wp_json_encode($suggestions) . ');';
        } else {
            // Regular JSON response
            wp_send_json($suggestions);
        }

        exit;
    }
     /**
     * WP Kraken #w785816
     */
    public static function wpk_change_booking_hours( $date_start, $date_end ) {

        $start_date_time = new DateTime( $date_start );
        $end_date_time = new DateTime( $date_end );

        $is_the_same_date = $start_date_time->format( 'Y-m-d' ) == $end_date_time->format( 'Y-m-d' );

        // single day bookings are not alowed, this is owner reservation
        // set end of this date as the next day
        if ( $is_the_same_date ) {
            $end_date_time->add( DateInterval::createfromdatestring('+1 day') );
        }
        $end_date_time->add( DateInterval::createfromdatestring('-1 day') );
        $start_date_time->setTime( 12, 0 );
        $end_date_time->setTime( 11, 59, 59 );

        return array(
            'date_start'    => $start_date_time->format( 'Y-m-d H:i:s' ),
            'date_end'      => $end_date_time->format( 'Y-m-d H:i:s' )
        );

    }
     

    /**
    * Get bookings between dates filtred by arguments
    *
    * @param  date $date_start in format YYYY-MM-DD
    * @param  date $date_end in format YYYY-MM-DD
    * @param  array $args fot where [index] - name of column and value of index is value
    *
    * @return array all records informations between two dates
    */
    public static function get_bookings( $date_start, $date_end, $args = '', $by = 'booking_date', $limit = '', $offset = '' ,$all = '', $listing_type = '')  {

        global $wpdb;
        $result = false;
        // if(strlen($date_start)<10){
        //     if($date_start) { $date_start = $date_start.' 00:00:00'; }
        //     if($date_end) { $date_end = $date_end.' 23:59:59'; }
        // }

        // setting dates to MySQL style
        
        $date_start = esc_sql ( date( "Y-m-d H:i:s", strtotime( $wpdb->esc_like( $date_start ) ) ) );
        $date_end = esc_sql ( date( "Y-m-d H:i:s", strtotime( $wpdb->esc_like( $date_end ) ) ) );
    
        //TODO to powinno byc tylko dla rentals!!
          // WP Kraken
        if($listing_type == 'rental'){   
            $booking_hours = self::wpk_change_booking_hours( $date_start, $date_end );
           
            $date_start = $booking_hours[ 'date_start' ];
            $date_end = $booking_hours[ 'date_end' ];
        }
  
        
        // filter by parameters from args
        $WHERE = '';
        $FILTER_CANCELLED = "AND NOT status='cancelled' AND NOT status='expired' ";

        if ( is_array ($args) )
        {
            foreach ( $args as $index => $value ) 
            {

                $index = esc_sql( $index );
                $value = esc_sql( $value );

                if ( $value == 'approved' ){ 
                    $WHERE .= " AND status IN ('confirmed','paid','approved')";
                } elseif ( $value == 'icalimports' ) { 

                } else {
                    $WHERE .= " AND (`$index` = '$value')";  
                } 
                if( $value == 'cancelled' || $value == 'special_price'){
                    $FILTER_CANCELLED = '';
                }
                if( $value == 'icalimports'){
                    $FILTER_CANCELLED = "AND NOT status='icalimports'";
                }
            
            }
        }

        if($all == 'users'){
            $FILTER = "AND NOT comment='owner reservations'";
        } else if( $all == 'owner') {
            $FILTER = "AND comment='owner reservations'";
        } else {
            $FILTER = '';
        }
        

        if ( $limit != '' ) $limit = " LIMIT " . esc_sql($limit);
        
        if ( is_numeric($offset)) $offset = " OFFSET " . esc_sql($offset);

        // switch ($by)
        // {

        //     case 'booking_date' :
        //         $result  = $wpdb -> get_results( "SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` WHERE ((' $date_start' >= `date_start` AND ' $date_start' <= `date_end`) OR ('$date_end' >= `date_start` AND '$date_end' <= `date_end`) OR (`date_start` >= ' $date_start' AND `date_end` <= '$date_end')) $WHERE $FILTER $FILTER_CANCELLED $limit $offset", "ARRAY_A" );
        //         listeo_write_log("SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` WHERE ((' $date_start' >= `date_start` AND ' $date_start' <= `date_end`) OR ('$date_end' >= `date_start` AND '$date_end' <= `date_end`) OR (`date_start` >= ' $date_start' AND `date_end` <= '$date_end')) $WHERE $FILTER $FILTER_CANCELLED $limit $offset");
        //      break;


        //     case 'created_date' :
        //         // when we searching by created date automaticly we looking where status is not null because we using it for dashboard booking
        //         $result  = $wpdb -> get_results( "SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` WHERE (' $date_start' <= `created` AND ' $date_end' >= `created`) AND (`status` IS NOT NULL)  $WHERE $FILTER_CANCELLED $limit $offset", "ARRAY_A" );
        //         break;

        // }
        switch ($by) {
            // case 'booking_date' :
            //     $result  = $wpdb -> get_results( "SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` WHERE ((' $date_start' >= `date_start` AND ' $date_start' <= `date_end`) OR ('$date_end' >= `date_start` AND '$date_end' <= `date_end`) OR (`date_start` >= ' $date_start' AND `date_end` <= '$date_end')) $WHERE $FILTER $FILTER_CANCELLED $limit $offset", "ARRAY_A" );
               
            //  break;
            case 'booking_date':
                // Modified WHERE clause to properly detect overlapping time periods
                $result = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` 
                    WHERE (
                        (date_start <= %s AND date_end >= %s) OR  /* booking spans over the searched start time */
                        (date_start <= %s AND date_end >= %s) OR  /* booking spans over the searched end time */
                        (date_start >= %s AND date_end <= %s) OR  /* booking is within the searched period */
                        (date_start = %s AND date_end = %s)       /* exact match */
                    ) 
                    $WHERE $FILTER $FILTER_CANCELLED $limit $offset",
                        $date_end,    // First pair
                        $date_start,
                        $date_start,  // Second pair
                        $date_start,
                        $date_start,  // Third pair
                        $date_end,
                        $date_start,  // Fourth pair
                        $date_end
                    ),
                    "ARRAY_A"
                );
                break;

            case 'created_date':
                // Prepare base SQL with placeholders
                $sql = "
                    SELECT * FROM `{$wpdb->prefix}bookings_calendar`
                    WHERE (%s <= `created` AND %s >= `created`)
                    AND (`status` IS NOT NULL)
                    {$WHERE} {$FILTER_CANCELLED} {$limit} {$offset}
                ";

                // Run query with $wpdb->prepare for $date_start and $date_end
                $result = $wpdb->get_results(
                    $wpdb->prepare($sql, $date_start, $date_end),
                    "ARRAY_A"
                );
                break;
        }

        // Allow plugins to filter bookings results (e.g., filter by resource)
        $result = apply_filters( 'listeo_get_bookings_results', $result, $date_start, $date_end, $args, $by );

        return $result;

    }


    public static function get_first_available_hour($listing_id, $date)
    {
        global $wpdb;

        // Convert date to start of day and end of day
        $date_start = date('Y-m-d 00:00:00', strtotime($date));
        $date_end = date('Y-m-d 23:59:59', strtotime($date));

        // Get the latest booking end time for this day
        $latest_booking = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(date_end) 
        FROM {$wpdb->prefix}bookings_calendar 
        WHERE listing_id = %d 
        AND DATE(date_start) = DATE(%s)
        AND type = 'reservation'
        AND status NOT IN ('cancelled', 'expired')",
            $listing_id,
            $date_start
        ));

        if ($latest_booking) {
            // Add 15 minutes to the last booking end time
            $next_available = new DateTime($latest_booking);
            $next_available->add(new DateInterval('PT15M'));

            // Return the formatted time
            return $next_available->format('Y-m-d H:i:s');
        }

        // If no bookings found for this day, return the start of the day
        return $date_start;
    }
    public function ajax_check_date_range_availability()
    {
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        global $wpdb;

        // Check if dates are valid
        if (!$listing_id || !$start_date || !$end_date) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
            return;
        }

        // Calculate requested duration
        $start_datetime = new DateTime($start_date);
        $end_datetime = new DateTime($end_date);
        $requested_duration = $end_datetime->diff($start_datetime);

        // Check minimum stay requirement
  
        $min_days = intval(trim(get_post_meta($listing_id, '_min_days', true)));

 

        if (!empty($min_days) && is_numeric($min_days)) {
            $requested_days = $requested_duration->days;
            $requested_days++;
            // Debug: Log the actual values and types
    
            if ($requested_days < intval($min_days)) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Minimum stay for this listing is %d day(s). You selected %d day(s).', 'listeo_core'),
                        intval($min_days),
                        $requested_days
                    ),
                    'min_days_required' => intval($min_days),
                    'days_selected' => $requested_days
                ));
                return;
            }
        }

        // Get all future bookings for this listing
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT date_start, date_end 
        FROM {$wpdb->prefix}bookings_calendar 
        WHERE listing_id = %d
        AND date_end >= %s
        AND type = 'reservation'
        AND status NOT IN ('cancelled', 'expired')
        ORDER BY date_start ASC",
            $listing_id,
            $start_date
        ));

        // Check if requested dates are available
        $is_conflict = false;
        foreach ($existing_bookings as $booking) {
            if (
                (strtotime($start_date) <= strtotime($booking->date_end) &&
                    strtotime($end_date) >= strtotime($booking->date_start))
            ) {
                $is_conflict = true;
                break;
            }
        }

        if (!$is_conflict) {
            wp_send_json_success(array(
                'available' => true
            ));
            return;
        }

        // Find the next available slot
        $next_start = new DateTime($start_date);
        $duration_interval = new DateInterval(
            sprintf(
                'P%dDT%dH%dM',
                $requested_duration->d,
                $requested_duration->h,
                $requested_duration->i
            )
        );

        $max_attempts = 365; // Limit search to 1 year
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $is_slot_available = true;
            $potential_end = clone $next_start;
            $potential_end->add($duration_interval);

            // Check this slot against all bookings
            foreach ($existing_bookings as $booking) {
                $booking_start = new DateTime($booking->date_start);
                $booking_end = new DateTime($booking->date_end);

                if (
                    ($next_start <= $booking_end && $potential_end >= $booking_start)
                ) {
                    // Conflict found - move start date to after this booking
                    $next_start = clone $booking_end;
                    // Add 15 minutes buffer after the end of the booking
                    $next_start->modify('+15 minutes');

                    // Round to nearest 15 minutes if needed
                    $minutes = $next_start->format('i');
                    $round_to = ceil($minutes / 15) * 15;
                    $next_start->setTime(
                        $next_start->format('H'),
                        $round_to,
                        0
                    );

                    $is_slot_available = false;
                    break;
                }
            }

            if ($is_slot_available) {
                // Calculate the end date based on the new start date
                $suggested_end = clone $next_start;
                $suggested_end->add($duration_interval);

                // Found an available slot
                wp_send_json_success(array(
                    'available' => false,
                    'next_available' => array(
                        'start' => $next_start->format('Y-m-d H:i:s'),
                        'end' => $suggested_end->format('Y-m-d H:i:s')
                    )
                ));
                return;
            }

            $attempt++;
        }

        // If we get here, no slot was found
        wp_send_json_success(array(
            'available' => false,
            'message' => 'No suitable availability found within the next year'
        ));
    }

    public static function get_available_hours_between_bookings($listing_id, $date)
    {
        global $wpdb;

        // Convert date to start of day and end of day
        $date_start = date('Y-m-d 00:00:00', strtotime($date));
        $date_end = date('Y-m-d 23:59:59', strtotime($date));

        // Get all bookings for this day, ordered by start time
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT date_start, date_end 
        FROM {$wpdb->prefix}bookings_calendar 
        WHERE listing_id = %d 
        AND DATE(date_start) = DATE(%s)
        AND type = 'reservation'
        AND status NOT IN ('cancelled', 'expired')
        ORDER BY date_start ASC",
            $listing_id,
            $date_start
        ));

        // Get business hours for this day
        $day_of_week = strtolower(date('l', strtotime($date)));
        $opening_hours = get_post_meta($listing_id, "_{$day_of_week}_opening_hour", true);
        $closing_hours = get_post_meta($listing_id, "_{$day_of_week}_closing_hour", true);
        
        if(is_array($opening_hours)) {
          
            if(is_array($opening_hours) && (empty($opening_hours) || (count($opening_hours) === 1 && empty($opening_hours[0])))) {
                
                $opening_hours = array('00:00');
            }
           
        } else {
            $opening_hours = $opening_hours ? array($opening_hours) : array('00:00');
        }

        if(is_array($closing_hours)) {
      
            // Check if we got an array with empty string
            if(is_array($closing_hours) && (empty($closing_hours) || (count($closing_hours) === 1 && empty($closing_hours[0])))) {
                $closing_hours = array('23:59');
            }
        } else {
            $closing_hours = $closing_hours ? array($closing_hours) : array('23:59');
    }


        $available_slots = array();

        // Process each business hours period
        for ($i = 0; $i < count($opening_hours); $i++) {
            // Skip if either opening or closing hour is empty
            if (empty($opening_hours[$i]) || empty($closing_hours[$i])) {
                continue;
            }

            $period_start = new DateTime($date . ' ' . $opening_hours[$i]);
            $period_end = new DateTime($date . ' ' . $closing_hours[$i]);

            if (empty($bookings)) {
                // If no bookings, entire period is available
                $available_slots[] = array(
                    'start' => $period_start->format('Y-m-d H:i:s'),
                    'end' => $period_end->format('Y-m-d H:i:s')
                );
                continue;
            }

            // Create an array of busy periods
            $busy_periods = array();
            foreach ($bookings as $booking) {
                $booking_start = new DateTime($booking->date_start);
                $booking_end = new DateTime($booking->date_end);

                // Only consider bookings that overlap with this period
                if ($booking_end >= $period_start && $booking_start <= $period_end) {
                    $busy_periods[] = array(
                        'start' => $booking_start,
                        'end' => $booking_end
                    );
                }
            }

            // Sort busy periods by start time
            usort($busy_periods, function ($a, $b) {
                return $a['start'] <=> $b['start'];
            });

            $current_time = clone $period_start;

            // Find gaps between bookings
            foreach ($busy_periods as $busy_period) {
                if ($current_time < $busy_period['start']) {
                    // Round current_time up to next 15 minutes
                    $minutes = (int) $current_time->format('i');
                    $minutes = ceil($minutes / 15) * 15;
                    $current_time->setTime($current_time->format('H'), $minutes);

                    if ($current_time < $busy_period['start']) {
                        $available_slots[] = array(
                            'start' => $current_time->format('Y-m-d H:i:s'),
                            'end' => $busy_period['start']->format('Y-m-d H:i:s')
                        );
                    }
                }
                $current_time = clone $busy_period['end'];
            }

            // Check for available time after last booking
            if ($current_time < $period_end) {
                // Round current_time up to next 15 minutes
                $minutes = (int) $current_time->format('i');
                $minutes = ceil($minutes / 15) * 15;
                $current_time->setTime($current_time->format('H'), $minutes);

                if ($current_time < $period_end) {
                    $available_slots[] = array(
                        'start' => $current_time->format('Y-m-d H:i:s'),
                        'end' => $period_end->format('Y-m-d H:i:s')
                    );
                }
            }
        }

        return $available_slots;
    }


    public function ajax_get_available_hours()
    {
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (!$listing_id || !$date) {
            wp_send_json_error();
        }

        $available_slots = self::get_available_hours_between_bookings($listing_id, $date);
        wp_send_json_success($available_slots);
    }


    public static function get_slots_bookings( $date_start, $date_end, $args = '', $by = 'booking_date', $limit = '', $offset = '' ,$all = '')  {

        global $wpdb;
        
        // if(strlen($date_start)<10){
        //     if($date_start) { $date_start = $date_start.' 00:00:00'; }
        //     if($date_end) { $date_end = $date_end.' 23:59:59'; }
        // }
        
        // setting dates to MySQL style
        $date_start = esc_sql ( date( "Y-m-d H:i:s", strtotime( $wpdb->esc_like( $date_start ) ) ) );
        $date_end = esc_sql ( date( "Y-m-d H:i:s", strtotime( $wpdb->esc_like( $date_end ) ) ) );
        
        // filter by parameters from args
        $WHERE = '';
        $FILTER_CANCELLED = "AND NOT status='cancelled' ";
        if ( is_array ($args) )
        {
            foreach ( $args as $index => $value ) 
            {

                $index = esc_sql( $index );
                $value = esc_sql( $value );

                if ( $value == 'approved' ){ 
                    $WHERE .= " AND ( (`$index` = 'confirmed') OR (`$index` = 'paid') )";
                } else {
                  $WHERE .= " AND (`$index` = '$value')";  
                } 
                if( $value == 'cancelled' ){
                    $FILTER_CANCELLED = '';
                }
            
            }
        }
        if($all == 'users'){
            $FILTER = "AND NOT comment='owner reservations'";
        } else {
            $FILTER = '';
        }

        if ( $limit != '' ) $limit = " LIMIT " . esc_sql($limit);
        
        if ( is_numeric($offset)) $offset = " OFFSET " . esc_sql($offset);
        switch ($by)
        {

            case 'booking_date':
                $sql = "
                    SELECT * FROM `{$wpdb->prefix}bookings_calendar`
                    WHERE ((%s = `date_start` AND %s = `date_end`))
                    {$WHERE} {$FILTER} {$FILTER_CANCELLED} {$limit} {$offset}
                ";
                $result = $wpdb->get_results(
                    $wpdb->prepare($sql, $date_start, $date_end),
                    "ARRAY_A"
                );
                break;

            case 'created_date':
                $sql = "
                    SELECT * FROM `{$wpdb->prefix}bookings_calendar`
                    WHERE (%s = `created` AND %s = `created`)
                    AND (`status` IS NOT NULL)
                    {$WHERE} {$FILTER_CANCELLED} {$limit} {$offset}
                ";
                $result = $wpdb->get_results(
                    $wpdb->prepare($sql, $date_start, $date_end),
                    "ARRAY_A"
                );
                break;
            
        }
        
        
        return $result;

    }


    public function get_booked_hours()
    {
        if (!isset($_POST['date']) || !isset($_POST['listing_id'])) {
            wp_send_json_error();
        }

        $date = sanitize_text_field($_POST['date']);
        $listing_id = intval($_POST['listing_id']);

        $bookings = $this->get_bookings(
            $date . ' 00:00:00',
            $date . ' 23:59:59',
            array(
                'listing_id' => $listing_id,
                'type' => 'reservation'
            )
        );

        $hours = array();
        foreach ($bookings as $booking) {
            $hours[] = array(
                'start' => date('H:i', strtotime($booking['date_start'])),
                'end' => date('H:i', strtotime($booking['date_end']))
            );
        }

        wp_send_json_success($hours);
    }
    /**
    * Get maximum number of bookings between dates filtred by arguments, used for pagination
    *
    * @param  date $date_start in format YYYY-MM-DD
    * @param  date $date_end in format YYYY-MM-DD
    * @param  array $args fot where [index] - name of column and value of index is value
    *
    * @return array all records informations between two dates
    */
    public static function get_bookings_max( $date_start, $date_end, $args = '', $by = 'booking_date' )  {

        global $wpdb;

        // setting dates to MySQL style
        $date_start = esc_sql ( date( "Y-m-d H:i:s", strtotime( $wpdb->esc_like( $date_start ) ) ) );
        $date_end = esc_sql ( date( "Y-m-d H:i:s", strtotime( $wpdb->esc_like( $date_end ) ) ) );

        // filter by parameters from args
        $WHERE = '';
        $FILTER_CANCELLED = "AND NOT status='cancelled' ";
        
        if ( is_array ($args) )
        {
            foreach ( $args as $index => $value ) 
            {

                $index = esc_sql( $index );
                $value = esc_sql( $value );

                if ( $value == 'approved' ){ 
                    $WHERE .= " AND ((`$index` = 'confirmed') OR (`$index` = 'paid'))";
                } else {
                  $WHERE .= " AND (`$index` = '$value')";  
                } 
                if( $value == 'cancelled' ){
                    $FILTER_CANCELLED = '';
                }
            
            }
        }
        
        switch ($by)
        {

            case 'booking_date':
                $sql = "
                    SELECT * FROM `{$wpdb->prefix}bookings_calendar`
                    WHERE (
                        (%s >= `date_start` AND %s <= `date_end`)
                        OR (%s >= `date_start` AND %s <= `date_end`)
                        OR (`date_start` >= %s AND `date_end` <= %s)
                    )
                    AND NOT comment = 'owner reservations'
                    {$WHERE} {$FILTER_CANCELLED}
                ";
                $result = $wpdb->get_results(
                    $wpdb->prepare($sql, $date_start, $date_start, $date_end, $date_end, $date_start, $date_end),
                    "ARRAY_A"
                );
                break;


            case 'created_date':
                $sql = "
                        SELECT * FROM `{$wpdb->prefix}bookings_calendar`
                        WHERE (%s <= `created` AND %s >= `created`)
                        AND (`status` IS NOT NULL)
                        AND NOT comment = 'owner reservations'
                        {$WHERE} {$FILTER_CANCELLED}
                    ";
                $result = $wpdb->get_results(
                    $wpdb->prepare($sql, $date_start, $date_end),
                    "ARRAY_A"
                );
                break;
            
        }
        
        
        return $wpdb->num_rows;

    }

    /**
    * Get latest bookings number of bookings between dates filtred by arguments, used for pagination
    *
    * @param  date $date_start in format YYYY-MM-DD
    * @param  date $date_end in format YYYY-MM-DD
    * @param  array $args fot where [index] - name of column and value of index is value
    *
    * @return array all records informations between two dates
    */
    public static function get_newest_bookings( $args = '', $limit = 5, $offset = 0 )  {

        global $wpdb;

        // setting dates to MySQL style
       
        // filter by parameters from args
        $WHERE = '';

        if ( is_array ($args) )
        {
            foreach ( $args as $index => $value ) 
            {

                $index = esc_sql( $index );
                $value = esc_sql( $value );

                if ( $value == 'approved' ){ 
                    $WHERE .= " AND status IN ('confirmed','paid','approved')";
                   
                } else 
                if ( $value == 'waiting' ){ 
                    $WHERE .= " AND status IN ('waiting','pay_to_confirm')";
                    
                } else {
                  $WHERE .= " AND (`$index` = '$value')";  
                } 
            
            
            }
        }
        
        
        if ( $limit != '' ) $limit = " LIMIT " . esc_sql($limit);
        //if(isset($args['status']) && $args['status'])
        $offset = " OFFSET " . esc_sql($offset);

        // when we searching by created date automaticly we looking where status is not null because we using it for dashboard booking
        $sql = "
        SELECT * FROM `{$wpdb->prefix}bookings_calendar`
        WHERE NOT comment = 'owner reservations'
        {$WHERE}
        ORDER BY `{$wpdb->prefix}bookings_calendar`.`created` DESC
        {$limit} {$offset}
    ";
        $result = $wpdb->get_results($sql, "ARRAY_A");
        
        return $result;

    }

    /**
    * Check gow may free places we have
    *
    * @param  date $date_start in format YYYY-MM-DD
    * @param  date $date_end in format YYYY-MM-DD
    * @param  array $args
    *
    * @return number $free_places that we have this time
    */
    /**
     * Check if selected dates are blocked (owner reservations, disabled dates, etc.)
     * This is SERVER-SIDE validation to prevent bookings on blocked dates
     *
     * @param int $listing_id Listing ID
     * @param string $date_start Start date (Y-m-d format)
     * @param string $date_end End date (Y-m-d format)
     * @return bool|string False if dates are available, error message if blocked
     */
    public static function is_date_blocked($listing_id, $date_start, $date_end) {
        // Don't validate if overbooking is allowed
        if (apply_filters('listeo_allow_overbooking', false)) {
            return false;
        }

        $listing_type = listeo_get_booking_type($listing_id);

        // Only validate for date_range and single_day types
        if ($listing_type != 'date_range' && $listing_type != 'single_day' && $listing_type != 'rental' && $listing_type != 'service') {
            return false;
        }

        // Get all bookings including owner reservations and external calendars
        $records = self::get_bookings(
            date('Y-m-d H:i:s', strtotime($date_start . ' -1 year')),
            date('Y-m-d H:i:s', strtotime($date_end . ' +1 year')),
            array('listing_id' => $listing_id, 'type' => 'reservation'),
            'booking_date',
            '',
            '',
            '',
            ($listing_type == 'date_range' || $listing_type == 'rental') ? 'rental' : ''
        );

        if (empty($records)) {
            return false;
        }

        // Convert requested dates to DateTime for comparison (use Immutable to prevent accidental modification)
        $request_start = new DateTimeImmutable($date_start);
        $request_end = new DateTimeImmutable($date_end);

        // Check each record for conflicts
        foreach ($records as $record) {
            $booking_start = new DateTimeImmutable(date('Y-m-d', strtotime($record['date_start'])));
            $booking_end = new DateTimeImmutable(date('Y-m-d', strtotime($record['date_end'])));

            // Check for owner reservations (blocked dates)
            if ($record['status'] == 'owner_reservations') {
                // Check if requested dates overlap with blocked dates
                if ($listing_type == 'date_range' || $listing_type == 'rental') {
                    // For date range bookings, use simple overlap check:
                    // Two ranges overlap if: start1 <= end2 AND start2 <= end1
                    if ($request_start <= $booking_end && $booking_start <= $request_end) {
                        return __('Selected dates include blocked/unavailable dates. Please choose different dates.', 'listeo_core');
                    }
                } else {
                    // For single day bookings
                    if ($request_start->format('Y-m-d') == $booking_start->format('Y-m-d')) {
                        return __('Selected date is blocked/unavailable. Please choose a different date.', 'listeo_core');
                    }
                }
            }

            // Check for external calendar imports (iCal blocked dates)
            if (strpos($record['status'], 'external') === 0) {
                if (apply_filters('listeo_disable_slots_external_dates', false)) {
                    // Use simple overlap check for external bookings
                    if ($request_start <= $booking_end && $booking_start <= $request_end) {
                        return __('Selected dates conflict with external calendar bookings. Please choose different dates.', 'listeo_core');
                    }
                }
            }
        }

        return false;
    }

    public static function count_free_places( $listing_id, $date_start, $date_end, $slot = 0 )  {

         // get slots
         $_slots = self :: get_slots_from_meta ( $listing_id );
         $slots_status = get_post_meta ( $listing_id, '_slots_status', true );

         if(isset($slots_status) && !empty($slots_status)) {
            $_slots = self :: get_slots_from_meta ( $listing_id );
         } else {
            $_slots = false;
         }
        // get listing type
        $listing_type = get_post_meta ( $listing_id, '_listing_type', true );
     

         // default we have one free place
         $free_places = 1;

         // check if this is service/single_day type of listing and slots are added, then checking slots
         // Support both modern 'single_day' and legacy 'service' types
         if ((listeo_get_booking_type($listing_id) == 'single_day' || listeo_get_booking_type($listing_id) == 'service') && $_slots ) 
         {
             $slot = json_decode( wp_unslash($slot) );
 
             // converent hours to mysql format
             $hours = explode( ' - ', $slot[0] );
             $hour_start = date( "H:i:s", strtotime( $hours[0] ) );
             $hour_end = date( "H:i:s", strtotime( $hours[1] ) );
 
             // add hours to dates
             $date_start .= ' ' . $hour_start;
             $date_end .= ' ' . $hour_end;
 
             // get day and number of slot
             $day_and_number = explode( '|', $slot[1] );
             $slot_day = $day_and_number[0];
             $slot_number =  $day_and_number[1];

             // get amount of slots
            $slots_amount = explode( '|', $_slots[$slot_day][$slot_number] );
       
            $slots_amount = $slots_amount[1];
    
            $free_places = $slots_amount;


         } else if ((listeo_get_booking_type($listing_id) == 'single_day' || listeo_get_booking_type($listing_id) == 'service') && ! $_slots )  {

             // if there are no slots then always is free place and owner manage himself

            // check for imported icals
            $result = self :: get_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'reservation' ) );
            if(!empty($result)) {
                return 0; 
            } else {
                return 1;
            }


         }

         // TICKETS booking type (events with fixed event dates)
         // Support both modern 'tickets' and legacy 'event' types
         if (listeo_get_booking_type($listing_id) == 'tickets' || listeo_get_booking_type($listing_id) == 'event' ) {

            /**
             * Allow plugins (e.g. Booking Plus multi-tier ticket types) to
             * override the available tickets count. Return null to fall back
             * to the legacy _event_tickets / _event_tickets_sold meta.
             */
            $bp_available = apply_filters( 'listeo_event_tickets_available', null, $listing_id );
            if ( null !== $bp_available ) {
                return (int) $bp_available;
            }

            // Calculate available tickets
            $ticket_number = (int)get_post_meta($listing_id, '_event_tickets', true);
            $ticket_number_sold = (int)get_post_meta($listing_id, '_event_tickets_sold', true);
            return ($ticket_number - $ticket_number_sold);


         }
 
         // get reservations to this slot and calculace amount
         // Support both modern 'date_range' and legacy 'rental' types
         if(listeo_get_booking_type($listing_id) == 'date_range' || listeo_get_booking_type($listing_id) == 'rental' ) {
            $minspan = (int) get_post_meta($listing_id, '_min_days', true);
            if(get_post_meta($listing_id, '_rental_timepicker', true)){
                $listing_type = 'rentaltimepicker';
            } else { 
                $listing_type = 'rental';
            }
            $date_start_time  = strtotime($date_start);
            $date_start_raw = new DateTime("@$date_start_time");

            $date_end_time = strtotime($date_end);
            $date_end_raw = new DateTime("@$date_end_time");
           
            $date_diff = $date_end_raw->diff($date_start_raw)->format("%a");
            $last_day_count = get_option('listeo_count_last_day_booking', 'off');
            if ($last_day_count == 'on') {
                $date_diff++;
            }
            if($date_diff < ($minspan-1)) {
                return 0;
            } else {
             
                            $result = self::get_bookings(
                                $date_start,
                                $date_end,
                                array('listing_id' => $listing_id, 'type' => 'reservation'),
                                $by = 'booking_date',
                                $limit = '',
                                $offset = '',
                                $all = '',
                                $listing_type
                            );
                         
            }
        
          
         } else {
                if((listeo_get_booking_type($listing_id) == 'single_day' || listeo_get_booking_type($listing_id) == 'service') && $_slots ){
                    $result = self ::  get_slots_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'reservation' ) );
                } else {
                    $result = self :: get_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'reservation' ), $by = 'booking_date', $limit = '', $offset = '',$all = '', $listing_type = 'service' );   
                }
             
         }
         

        // count how many reservations we have already for this slot
        if ( ! empty( $result ) ) {
            $booking_type = listeo_get_booking_type( $listing_id );
            $count_last_day = get_option( 'listeo_count_last_day_booking', 'off' );

            if ( 'on' !== $count_last_day && in_array( $booking_type, array( 'rental', 'date_range' ), true ) ) {
                $rental_timepicker = get_post_meta( $listing_id, '_rental_timepicker', true );
                $requested_start_ts = strtotime( $date_start );

                $requested_end_ts = strtotime( $date_end );

                $result = array_filter( $result, function ( $booking ) use ( $requested_start_ts, $requested_end_ts, $rental_timepicker ) {
                    if ( empty( $booking['date_end'] ) ) {
                        return true;
                    }

                    $booking_end_ts = strtotime( $booking['date_end'] );
                    $booking_start_ts = isset( $booking['date_start'] ) ? strtotime( $booking['date_start'] ) : null;

                    if ( 'on' === $rental_timepicker ) {
                        if ( $booking_start_ts && $booking_start_ts >= $requested_end_ts ) {
                            // Next guest arrives on/after our checkout time – allow it
                            return false;
                        }

                        // With explicit hours we only block if the previous stay overlaps the new check-in time
                        return $booking_end_ts > $requested_start_ts;
                    }

                    $booking_end_date   = date( 'Y-m-d', $booking_end_ts );
                    $booking_start_date = $booking_start_ts ? date( 'Y-m-d', $booking_start_ts ) : null;
                    $request_start_date = date( 'Y-m-d', $requested_start_ts );
                    $request_end_date   = date( 'Y-m-d', $requested_end_ts );

                    if ( $booking_start_date && $booking_start_date === $request_end_date ) {
                        // Next stay starts the day we check out – allowed
                        return false;
                    }

                    // Day-based rentals treat the checkout day as available for new check-ins
                    return $booking_end_date !== $request_start_date;
                } );

                $result = array_values( $result );
            }
        }

        $reservetions_amount = count( $result );

         // minus temp reservations for this time
         // $free_places -= self :: temp_reservation_aval( array( 'listing_id' => $listing_id,
         // 'date_start' => $date_start, 'date_end' => $date_end) );

        // minus reservations from database
        $free_places -= $reservetions_amount;

        /**
         * Allow plugins (e.g. Booking Plus multi-slot span) to override the
         * resulting free-places count. Used when a booking spans several
         * slots: Core only considers the starting slot's capacity; the
         * plugin needs to reduce to the minimum across every slot in the
         * span.
         *
         * @param int    $free_places  Core's computed value.
         * @param int    $listing_id
         * @param string $date_start
         * @param string $date_end
         * @param mixed  $slot
         */
        return apply_filters( 'listeo_count_free_places', $free_places, $listing_id, $date_start, $date_end, $slot );

    }

    /**
    * Ajax check avaliabity
    *
    * @return number $ajax_out['free_places'] amount or zero if not
    * 
    * @return number $ajax_out['price'] calculated from database prices
    *
    */
    public static function ajax_check_avaliabity(  )  {

        // check if it's event by checking post type
     
        if(!isset($_POST['slot'])){
            $slot = false;
        } else {
            $slot = sanitize_text_field($_POST['slot']);
        }
        if(isset($_POST['hour'])){


            $_opening_hours_status = get_post_meta($_POST['listing_id'], '_opening_hours_status',true);
            // make $_opening_hours_status filterable by other plugins
            $_opening_hours_status = apply_filters('listeo_opening_hours_status', $_opening_hours_status);
            
            $ajax_out['free_places'] = 1;

            // check if theres a booking between these hours on that date
            //check opening times
            if($_opening_hours_status){
                $currentTime = $_POST['hour'];
                $date = $_POST['date_start'];
                $timestamp = strtotime($date);
                $day = strtolower(date('l', $timestamp));
                //get opening hours for this day
                

                if(!empty($currentTime) && is_numeric(substr($currentTime, 0, 1)) ) {
                    if(substr($currentTime, -1)=='M'){
                        $currentTime = DateTime::createFromFormat('h:i A', $currentTime);
                        if($currentTime){
                            $currentTime = $currentTime->format('Hi');            
                        }

                        //
                    } else {
                        $currentTime = DateTime::createFromFormat('H:i', $currentTime);
                        if($currentTime){
                            $currentTime = $currentTime->format('Hi');
                        }
                    }
                    
                } 

                $opening_hours = get_post_meta( $_POST['listing_id'], '_'.$day.'_opening_hour', true);
                $closing_hours = get_post_meta( $_POST['listing_id'], '_'.$day.'_closing_hour', true);
                $ajax_out['free_places'] = 0;
                if(empty($opening_hours) && empty($closing_hours)){
                    $ajax_out['free_places'] = 0;
                } else {
                    $storeSchedule = array(
                        'opens' => $opening_hours,
                        'closes' => $closing_hours
                    );
                    
                    $startTime = $storeSchedule['opens'];
                    $endTime = $storeSchedule['closes'];
                    if(is_array($storeSchedule['opens'])){
                            foreach ($storeSchedule['opens'] as $key => $start_time) {
                                # code...
                                $end_time = $endTime[$key];
                               
                                if(!empty($start_time) && is_numeric(substr($start_time, 0, 1)) ) {
                                    if(substr($start_time, -1)=='M'){
                                        $start_time = DateTime::createFromFormat('h:i A', $start_time);
                                        if($start_time){
                                            $start_time = $start_time->format('Hi');            
                                        }
     
                                        //
                                    } else {
                                        $start_time = DateTime::createFromFormat('H:i', $start_time);
                                        if($start_time){
                                            $start_time = $start_time->format('Hi');
                                        }
                                    }
                                    
                                } 
                                   //create time objects from start/end times and format as string (24hr AM/PM)
                                if(!empty($end_time)  && is_numeric(substr($end_time, 0, 1))){
                                    if(substr($end_time, -1)=='M'){
                                        $end_time = DateTime::createFromFormat('h:i A', $end_time);         
                                        if($end_time){
                                            $end_time = $end_time->format('Hi');
                                        }
                                    } else {
                                        $end_time = DateTime::createFromFormat('H:i', $end_time);
                                        if($end_time){
                                            $end_time = $end_time->format('Hi');
                                        }
                                    }
                                } 
                               
                                if($end_time == '0000'){
                                    $end_time = 2400;
                                }

                                if((int)$start_time > (int)$end_time ) {
                                    // midnight situation
                                    $end_time = 2400 + (int)$end_time;
                                }

                               
                                // check if current time is within the range
                                if (((int)$start_time <= (int)$currentTime) && ((int)$currentTime <= (int)$end_time)) {
                                     $ajax_out['free_places'] = 1;
                                } 
                                
                            }
                    } else {
                         if(!empty($startTime) && is_numeric(substr($startTime, 0, 1)) ) {
                                    if(substr($startTime, -1)=='M'){
                                        $start_time = DateTime::createFromFormat('h:i A', $startTime);
                                        if($start_time){
                                            $start_time = $start_time->format('Hi');            
                                        }
     
                                        //
                                    } else {
                                        $start_time = DateTime::createFromFormat('H:i', $startTime);
                                        if($start_time){
                                            $start_time = $start_time->format('Hi');
                                        }
                                    }
                                    
                                } 
                                   //create time objects from start/end times and format as string (24hr AM/PM)
                                if(!empty($endTime)  && is_numeric(substr($endTime, 0, 1))){
                                    if(substr($endTime, -1)=='M'){
                                        $end_time = DateTime::createFromFormat('h:i A', $endTime);         
                                        if($end_time){
                                            $end_time = $end_time->format('Hi');
                                        }
                                    } else {
                                        $end_time = DateTime::createFromFormat('H:i', $endTime);
                                        if($end_time){
                                            $end_time = $end_time->format('Hi');
                                        }
                                    }
                                } 
                        if ($end_time == '0000') {
                            $end_time = 2400;
                        }
                        if((int)$start_time > (int)$end_time ) {
                            // midnight situation
                            $end_time = 2400 + (int)$end_time;
                        }
                          // check if current time is within the range
                        if (((int)$start_time <= (int)$currentTime) && ((int)$currentTime <= (int)$end_time)) {
                                $ajax_out['free_places'] = 1;
                        } else {
                            $ajax_out['free_places'] = 0;
                        }
                    }   
                } 
            }
            
            
            
          
        /// end (if hour)
        } else {
            // if not hour it means it's rental
           // Check minimum stay requirement for rentals
           $min_days = intval(
                trim(get_post_meta($_POST['listing_id'], '_min_days', true))
           );
           if (!empty($min_days) && is_numeric($min_days)) {
               $start_datetime = new DateTime($_POST['date_start']);
               $end_datetime = new DateTime($_POST['date_end']);
               $requested_duration = $end_datetime->diff($start_datetime);
               $requested_days = $requested_duration->days;
                $requested_days++; // Include the end day
      
       

                if ($requested_days < intval($min_days)) {
                   $ajax_out['free_places'] = 0;
                   $ajax_out['error'] = sprintf(
                       __('Minimum stay for this listing is %d day(s). You selected %d day(s).', 'listeo_core'),
                       intval($min_days),
                       $requested_days
                   );
                   echo json_encode($ajax_out);
                   die();
               }
           }

           if(apply_filters('listeo_allow_overbooking', false)){
                $ajax_out['free_places'] = 1;
           } else{
                $ajax_out['free_places'] = self::count_free_places($_POST['listing_id'], $_POST['date_start'], $_POST['date_end'], $slot);
           }
            

        }

        // calculate prices now

        $multiply = 1;
        if(isset($_POST['adults'])) $multiply = $_POST['adults'];
        if(isset($_POST['tickets'])) $multiply = $_POST['tickets'];

        $children = isset($_POST['children']) ? (int) $_POST['children'] : 0;
        $animals = isset($_POST['animals']) ? (int) $_POST['animals'] : 0;
        
        $coupon = (isset($_POST['coupon'])) ? $_POST['coupon'] : false ;
        $services = (isset($_POST['services'])) ? $_POST['services'] : false ;
        // calculate price for all
        $decimals = get_option('listeo_number_decimals',2);
        $hour_start = (isset($_POST['hour'])) ? $_POST['hour']: false;
        $hour_end = (isset($_POST['end_hour'])) ? $_POST['end_hour']: false;

        if($slot && get_post_meta($_POST['listing_id'], '_count_by_hour', true) ){

            $slot = json_decode(wp_unslash($slot));
            //get hours and date to check reservation
            $hours = explode(' - ', $slot[0]);
            $hour_start = date("H:i", strtotime($hours[0]));
            $hour_end = date("H:i", strtotime($hours[1]));
       
        }
        
        if ($hour_end && $hour_start &&  get_post_meta($_POST['listing_id'], '_count_by_hour',true)) {
            if(!$slot){
                $start = $_POST['hour'];
                $end = $_POST['end_hour'];
                if (!empty($start) && is_numeric(substr($start, 0, 1))) {
                    if (substr($start, -1) == 'M') {
                        $start = DateTime::createFromFormat('h:i A', $start);
                        if ($start) {
                            $hour_start = $start->format('H:i');
                        }

                        //
                    } else {
                        $start = DateTime::createFromFormat('H:i', $start);
                        if ($start) {
                            $hour_start = $start->format('H:i');
                        }
                    }
                }
                if (!empty($end) && is_numeric(substr($end, 0, 1))) {
                    if (substr($end, -1) == 'M') {
                        $end = DateTime::createFromFormat('h:i A', $end);
                        if ($end) {
                            $hour_end = $end->format('H:i');
                        }

                        //
                    } else {
                        $end = DateTime::createFromFormat('H:i', $end);
                        if ($end) {
                            $hour_end = $end->format('H:i');
                        }
                    }
                } 
            }

          
            $price = self::calculate_price_per_hour($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $hour_start, $hour_end, $multiply,$children, $animals, $services, '');
            $ajax_out['price'] = number_format_i18n($price, $decimals);
            // Itemized breakdown of the SAME total — surfaced as
            // $ajax_out['breakdown']. The float-returning `calculate_*`
            // path stays the source of truth for the price; the
            // breakdown is a sibling computation that reuses the same
            // inputs so consumers can render "$X × N hours + fees..."
            // instead of just the lump sum.
            $breakdown = self::calculate_price_per_hour_breakdown($_POST['listing_id'], $_POST['date_start'], $_POST['date_end'], $hour_start, $hour_end, $multiply, $children, $animals, $services, $coupon);
            if (!empty($coupon)) {
                $price_discount = self::calculate_price_per_hour($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $hour_start, $hour_end, $multiply, $children, $animals, $services, $coupon);
                $ajax_out['price_discount'] = number_format_i18n($price_discount, $decimals);
            }
        } else {
            // rental type price
            if (get_post_meta($_POST['listing_id'], '_rental_timepicker', true)) {
                //calculate number of hours between start and end
                if(get_post_meta($_POST['listing_id'], '_count_by_hour', true)){
                    $date_start = strtotime($_POST['date_start']);
                    $date_end = strtotime($_POST['date_end']);
                    $hours = ($date_end - $date_start) / 3600;

                    $price = self::calculate_price_by_hours($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $hours, $multiply, $children, $animals, $services, '');
                    $breakdown = self::calculate_price_by_hours_breakdown($_POST['listing_id'], $_POST['date_start'], $_POST['date_end'], $hours, $multiply, $children, $animals, $services, $coupon);

                    if (!empty($coupon)) {
                        $price_discount = self::calculate_price_by_hours($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $hours, $multiply, $children, $animals, $services, $coupon);
                        $ajax_out['price_discount'] = number_format_i18n($price_discount, $decimals);
                    }
                } else {
                    $price = self::calculate_price($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $multiply, $children, $animals,  $services, '');
                    $breakdown = self::calculate_price_breakdown($_POST['listing_id'], $_POST['date_start'], $_POST['date_end'], $multiply, $children, $animals, $services, $coupon);
                    if (!empty($coupon)) {
                        $price_discount = self::calculate_price($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $multiply, $children, $animals,  $services, $coupon);
                        $ajax_out['price_discount'] = number_format_i18n($price_discount, $decimals);
                    }
                }

            } else {

                $price = self::calculate_price($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $multiply,$children, $animals, $services, '');
                $breakdown = self::calculate_price_breakdown($_POST['listing_id'], $_POST['date_start'], $_POST['date_end'], $multiply, $children, $animals, $services, $coupon);
                if (!empty($coupon)) {
                    $price_discount = self::calculate_price($_POST['listing_id'],  $_POST['date_start'], $_POST['date_end'], $multiply, $children, $animals,  $services, $coupon);
                    $ajax_out['price_discount'] = number_format_i18n($price_discount, $decimals);
                }
            }


            $ajax_out['price'] = number_format_i18n($price, $decimals);


        }

        // Add commission to price if calculation method is 'add'
        $listing_id = $_POST['listing_id'];
        $owner_id = get_post_field('post_author', $listing_id);
        $commission_settings = self::get_commission_settings($owner_id);

        // If calculation method is 'add', add commission to the price
        if ($commission_settings['calculation_method'] == 'add') {
            // Calculate commission amount
            $commission_amount = self::calculate_commission_amount($price, $commission_settings['commission_type'], $commission_settings['commission_value']);

            // Add commission to the final price
            $price = $price + $commission_amount;
            $ajax_out['price'] = number_format_i18n($price, $decimals);

            // Also add commission to discounted price if coupon was applied
            if (!empty($coupon) && isset($ajax_out['price_discount'])) {
                // Get the numeric discount price value
                $price_discount_numeric = str_replace(',', '', $ajax_out['price_discount']);
                $price_discount_numeric = (float) $price_discount_numeric;

                // Calculate commission on discounted price
                $commission_amount_discount = self::calculate_commission_amount($price_discount_numeric, $commission_settings['commission_type'], $commission_settings['commission_value']);

                $price_discount_numeric = $price_discount_numeric + $commission_amount_discount;
                $ajax_out['price_discount'] = number_format_i18n($price_discount_numeric, $decimals);
            }

            // Store commission info in response for transparency
            $ajax_out['commission_added'] = true;
            $ajax_out['commission_amount'] = number_format_i18n($commission_amount, $decimals);

            // Mirror commission onto the breakdown as its own line so
            // consumers don't need to re-add it. The breakdown's
            // `total` matches the final user-visible price including
            // commission. Only emit when the breakdown was actually
            // populated (defensive — earlier code paths may exit early
            // and never set $breakdown).
            if ( isset( $breakdown ) && is_array( $breakdown ) ) {
                $breakdown['lines'][] = self::_breakdown_line(
                    'commission',
                    __( 'Site fee', 'listeo_core' ),
                    (float) $commission_amount,
                    self::_breakdown_currency_args()
                );
                $breakdown['subtotal']          = (float) $breakdown['subtotal'] + (float) $commission_amount;
                $breakdown['subtotal_formatted'] = self::_breakdown_format( $breakdown['subtotal'], self::_breakdown_currency_args() );
                $breakdown['total']             = (float) $price;
                $breakdown['total_formatted']   = self::_breakdown_format( $price, self::_breakdown_currency_args() );
            }
        }

        // Surface the itemized breakdown when available. Older clients
        // ignore unknown keys, so older sites keep working unchanged.
        if ( isset( $breakdown ) && is_array( $breakdown ) ) {
            $ajax_out['breakdown'] = $breakdown;
        }

        wp_send_json_success( $ajax_out );

    }


    public function check_if_coupon_exists($coupon){
            global $wpdb;
            $title = sanitize_text_field($coupon);
            $sql = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1;", $title );
            //check if coupon with that code exits
            $coupon_id = $wpdb->get_var( $sql );
            
            return ($coupon_id) ? true : false ;
    }

    public function ajax_validate_coupon()
    {
        // CSRF guard. Anonymous users still get a valid nonce via the localized
        // listeo.coupon_nonce, so this works on both wp_ajax and wp_ajax_nopriv.
        check_ajax_referer('listeo_booking_coupon_nonce', 'nonce');

        $listing_id = isset($_POST['listing_id']) ? absint($_POST['listing_id']) : 0;
        $coupon     = isset($_POST['coupon']) ? sanitize_text_field(wp_unslash($_POST['coupon'])) : '';
        $coupons    = isset($_POST['coupons']) ? sanitize_text_field(wp_unslash($_POST['coupons'])) : false;
        $price      = isset($_POST['price']) ? sanitize_text_field(wp_unslash($_POST['price'])) : false;

        //if $coupons not empty, explode it
        if ($coupons) {
            $coupons = array_map('sanitize_text_field', explode(',', $coupons));
        }

        if ($price) {
            $price = str_replace(',', '', $price); // Remove thousands separators

            $price = (float) filter_var($price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        } else {
            $price = 0;
        }

        if (empty($coupon)) {
            $ajax_out['error'] = true;
            $ajax_out['error_type'] = 'no_coupon';
            $ajax_out['message'] = esc_html__('Coupon was not provided', 'listeo_core');
            wp_send_json($ajax_out);
        }

        if (! self::check_if_coupon_exists($coupon)) {
            $ajax_out['error'] = true;
            $ajax_out['error_type'] = 'no_coupon_exists';
            $ajax_out['message'] = esc_html__('This coupon does not exist', 'listeo_core');
            wp_send_json($ajax_out);
        }

        $wc_coupon = new WC_Coupon($coupon);


        // FIX: Improved individual use coupon validation
        // 1. If the current coupon is individual use and there are other coupons already selected
        if ($wc_coupon->get_individual_use() && isset($coupons) && is_array($coupons) && count($coupons) >= 1) {
           
            $ajax_out['error'] = true;
            $ajax_out['error_type'] = 'coupon_used_once';
            $ajax_out['message'] = __('This coupon cannot be used with others.', 'listeo_core');
            wp_send_json($ajax_out);
        }

        // 2. If there are already other coupons selected, check if any of them are individual use
        if (isset($coupons) && is_array($coupons) && count($coupons) > 0) {
            
            foreach ($coupons as $existing_coupon_code) {
                // Skip the current coupon we're validating
               if ($existing_coupon_code === $coupon) continue;

                if (self::check_if_coupon_exists($existing_coupon_code)) {
                    $existing_wc_coupon = new WC_Coupon($existing_coupon_code);
                    if ($existing_wc_coupon->get_individual_use()) {
                        $ajax_out['error'] = true;
                        $ajax_out['error_type'] = 'other_coupon_individual';
                        $ajax_out['message'] = __('Cannot add this coupon. You already have an individual-use coupon applied.', 'listeo_core');
                        wp_send_json($ajax_out);
                    }
                }
            }
        }

        if ($wc_coupon->get_minimum_amount() > 0 && $wc_coupon->get_minimum_amount() >= $price) {
            $ajax_out['error'] = true;
            $ajax_out['error_type'] = 'coupon_minimum_spend';
            $ajax_out['message'] = sprintf(__('The minimum spend for this coupon is %s.', 'listeo_core'), wc_price($wc_coupon->get_minimum_amount()));
            wp_send_json($ajax_out);
        }

        if ($wc_coupon->get_maximum_amount() > 0 && $wc_coupon->get_maximum_amount() < $price) {
            $ajax_out['error'] = true;
            $ajax_out['error_type'] = 'coupon_maximum_spend';
            $ajax_out['message'] = sprintf(__('The maximum spend for this coupon is %s.', 'listeo_core'), wc_price($wc_coupon->get_maximum_amount()));
            wp_send_json($ajax_out);
        }

        // Validate coupon user usage limit
        $user_id = get_current_user_id();
        if ($wc_coupon->get_usage_limit_per_user() && $user_id) {
            $data_store = $wc_coupon->get_data_store();
            $usage_count = $data_store->get_usage_by_user_id($wc_coupon, $user_id);

            if ($usage_count >= $wc_coupon->get_usage_limit_per_user()) {
                $ajax_out['error'] = true;
                $ajax_out['error_type'] = 'coupon_limit_used';
                $ajax_out['message'] = __('Coupon usage limit has been reached', 'listeo_core');
                wp_send_json($ajax_out);
            }
        }

        // Validate coupon email restrictions
        $email_restrictions = $wc_coupon->get_email_restrictions();
        if (!empty($email_restrictions) && $user_id) {
            $user_email = wp_get_current_user()->user_email;
            
            // Check if user email matches any of the allowed emails
            $email_allowed = false;
            foreach ($email_restrictions as $allowed_email) {
                // Support wildcard matching (e.g., *@example.com)
                if (fnmatch($allowed_email, $user_email)) {
                    $email_allowed = true;
                    break;
                }
            }
            
            if (!$email_allowed) {
                $ajax_out['error'] = true;
                $ajax_out['error_type'] = 'coupon_email_restricted';
                $ajax_out['message'] = __('This coupon is not valid for your email address', 'listeo_core');
                wp_send_json($ajax_out);
            }
        } elseif (!empty($email_restrictions) && !$user_id) {
            // User not logged in but coupon has email restrictions
            $ajax_out['error'] = true;
            $ajax_out['error_type'] = 'coupon_login_required';
            $ajax_out['message'] = __('You must be logged in to use this coupon', 'listeo_core');
            wp_send_json($ajax_out);
        }

        if ($wc_coupon->get_date_expires() && time() > $wc_coupon->get_date_expires()->getTimestamp()) {
            $ajax_out['error'] = true;
            $ajax_out['error_type'] = 'coupon_expired';
            $ajax_out['message'] = __('This coupon has expired.', 'listeo_core');
            wp_send_json($ajax_out);
        }

        // Check author of coupon, check if they are admin
        $author_ID = get_post_field('post_author', $wc_coupon->get_ID());
        $authorData = get_userdata($author_ID);
        if (in_array('administrator', $authorData->roles)):
            $admins_coupon = true;
        else:
            $admins_coupon = false;
        endif;

        if ($wc_coupon->get_usage_limit() > 0) {
            $usage_left = $wc_coupon->get_usage_limit() - $wc_coupon->get_usage_count();

            if ($usage_left > 0) {
                if ($admins_coupon) {
                    $ajax_out['success'] = true;
                    $ajax_out['coupon'] = $coupon;
                    wp_send_json($ajax_out);
                } else {
                    $available_listings = $wc_coupon->get_meta('listing_ids');
                    $available_listings_array = explode(',', $available_listings);
                    if (in_array($listing_id, $available_listings_array)) {
                        $ajax_out['success'] = true;
                        $ajax_out['coupon'] = $coupon;
                        wp_send_json($ajax_out);
                    } else {
                        $ajax_out['error'] = true;
                        $ajax_out['error_type'] = 'coupon_wrong_listing';
                        $ajax_out['message'] = esc_html__('This coupon is not applicable for this listing', 'listeo_core');
                        wp_send_json($ajax_out);
                    }
                }
            } else {
                $ajax_out['error'] = true;
                $ajax_out['error_type'] = 'coupon_limit_used';
                $ajax_out['message'] = esc_html__('Coupon usage limit has been reached', 'listeo_core');
                wp_send_json($ajax_out);
            }
        } else {
            if ($admins_coupon) {
                $ajax_out['success'] = true;
                $ajax_out['coupon'] = $coupon;
                wp_send_json($ajax_out);
            } else {
                $available_listings = $wc_coupon->get_meta('listing_ids');
                $available_listings_array = explode(',', $available_listings);
                if (in_array($listing_id, $available_listings_array)) {
                    $ajax_out['success'] = true;
                    $ajax_out['coupon'] = $coupon;
                    wp_send_json($ajax_out);
                } else {
                    $ajax_out['error'] = true;
                    $ajax_out['error_type'] = 'coupon_wrong_listing';
                    $ajax_out['message'] = esc_html__('This coupon is not applicable for this listing', 'listeo_core');
                    wp_send_json($ajax_out);
                }
            }
        }
    }


    public static function ajax_calculate_booking_form_price(){

        // Same CSRF guard as ajax_validate_coupon — this endpoint also reaches
        // apply_coupon_to_price() and is callable by anonymous users.
        check_ajax_referer('listeo_booking_coupon_nonce', 'nonce');

        $raw_price      = isset($_POST['price']) ? sanitize_text_field(wp_unslash($_POST['price'])) : '';
        $price          = $raw_price;
        $coupon         = isset($_POST['coupon']) ? sanitize_text_field(wp_unslash($_POST['coupon'])) : '';

        if(!empty($coupon)) {
            $coupons = array_map('sanitize_text_field', explode(',', $coupon));
            foreach ($coupons as $key => $new_coupon) {
                $price = self::apply_coupon_to_price($price,$new_coupon);
            }
        }

        if($price != $raw_price){
            $ajax_out['price'] = $price;
            wp_send_json( $ajax_out );
        } else {
            wp_send_json_success();
        }
    }

    public static function ajax_calculate_price( ) {
        $listing_id = $_POST['listing_id'];
        $tickets = isset($_POST['tickets']) ? $_POST['tickets'] : 1 ;

        /**
         * Allow plugins (e.g. Booking Plus multi-tier ticket types) to fully
         * override the AJAX-calculated total. Return an array with at least a
         * `price` key (already number-formatted) to short-circuit the legacy
         * `_normal_price * tickets` calculation, or null to fall through.
         */
        $bp_ajax_out = apply_filters( 'listeo_event_ajax_calculate_price', null, $listing_id, $_POST );
        if ( is_array( $bp_ajax_out ) && isset( $bp_ajax_out['price'] ) ) {
            wp_send_json_success( $bp_ajax_out );
        }

        $normal_price       = (float) get_post_meta ( $listing_id, '_normal_price', true);
        $reservation_price  =  (float) get_post_meta ( $listing_id, '_reservation_price', true);
        $services_price     = 0;
        // Repeatable fees engine (see class-listeo-core-fees.php). Backward
        // compatible: rows with no `type`/`frequency` default to flat /
        // per_stay, which reproduces the legacy summed-once-per-booking total.
        $services_price += listeo_sum_listing_fees( $listing_id, array(
            'tickets'      => max( 1, (int) $tickets ),
            'guests'       => max( 1, (int) $tickets ),
            'subtotal'     => ( $normal_price * (int) $tickets ) + $reservation_price,
            'listing_type' => get_post_meta( $listing_id, '_listing_type', true ),
        ) );

        if(isset($_POST['services'])){
            $services = $_POST['services'];
        
            if(isset($services) && !empty($services)){

                $bookable_services = listeo_get_bookable_services($listing_id);
                $countable = array_column($services,'value');
        
                $i = 0;
                foreach ($bookable_services as $key => $service) {
                    
                    if(in_array(sanitize_title($service['name']),array_column($services,'service'))) { 
                        //$services_price += (float) preg_replace("/[^0-9\.]/", '', $service['price']);
                        $services_price +=  listeo_calculate_service_price($service, $tickets, 1, 0,0,$countable[$i] );
                       
                       $i++;
                    }
                   
                
                } 
            }
          
        }
        $total_price = ($normal_price * $tickets) + $reservation_price + $services_price;
        $decimals = get_option('listeo_number_decimals',2);
        $ajax_out['price'] = number_format_i18n($total_price,$decimals);
        //check if there's coupon
        $coupon = (isset($_POST['coupon'])) ? $_POST['coupon'] : false ;
        if($coupon) {
            $sale_price = $total_price;
            $coupons = explode(',',$coupon);
            foreach ($coupons as $key => $new_coupon) {
                $total_price = self::apply_coupon_to_price($total_price,$new_coupon);
            }
            $ajax_out['price_discount'] = number_format_i18n($total_price,$decimals);
        }

        // Add commission to price if calculation method is 'add'
        $owner_id = get_post_field('post_author', $listing_id);
        $commission_settings = self::get_commission_settings($owner_id);

        // If calculation method is 'add', add commission to the price
        if ($commission_settings['calculation_method'] == 'add') {
            // Get the base price (without coupon)
            $base_price = ($normal_price * $tickets) + $reservation_price + $services_price;

            // Calculate commission amount
            $commission_amount = self::calculate_commission_amount($base_price, $commission_settings['commission_type'], $commission_settings['commission_value']);

            // Add commission to the prices
            $base_price_with_commission = $base_price + $commission_amount;
            $ajax_out['price'] = number_format_i18n($base_price_with_commission, $decimals);

            // Also add commission to discounted price if coupon was applied
            if (!empty($coupon) && isset($ajax_out['price_discount'])) {
                $total_price_with_commission = $total_price + $commission_amount;
                $ajax_out['price_discount'] = number_format_i18n($total_price_with_commission, $decimals);
            }

            // Store commission info in response for transparency
            $ajax_out['commission_added'] = true;
            $ajax_out['commission_amount'] = number_format_i18n($commission_amount, $decimals);
        }


        wp_send_json_success( $ajax_out );
    }


    /**
     * Get commission settings for a listing owner
     *
     * @param int $owner_id The owner user ID
     * @return array Array with commission_type, commission_value, calculation_method
     */
    public static function get_commission_settings($owner_id) {
        // Get commission type (per-user or global)
        $commission_type = get_user_meta($owner_id, 'listeo_commission_type', true);
        if(empty($commission_type)){
            $commission_type = get_option('listeo_commission_type', 'percentage');
        }

        // Get commission value (per-user or global)
        $commission_value = get_user_meta($owner_id, 'listeo_commission_rate', true);
        if(empty($commission_value)){
            $commission_value = get_option('listeo_commission_rate', 10);
        }

        // Get calculation method (per-user or global)
        $calculation_method = get_user_meta($owner_id, 'listeo_commission_calculation_method', true);
        if(empty($calculation_method)){
            $calculation_method = get_option('listeo_commission_calculation_method', 'deduct');
        }

        return array(
            'commission_type' => $commission_type,
            'commission_value' => $commission_value,
            'calculation_method' => $calculation_method
        );
    }

    /**
     * Calculate commission amount based on price and commission settings
     *
     * @param float $price The base price
     * @param string $commission_type The commission type (percentage or fixed)
     * @param float $commission_value The commission value
     * @return float The commission amount
     */
    public static function calculate_commission_amount($price, $commission_type, $commission_value) {
        if ($commission_type == 'fixed') {
            return (float) $commission_value;
        } else {
            return ($commission_value / 100) * $price;
        }
    }

    public static function apply_coupon_to_price($price, $coupon_code){

            if($price == 0) {
                return 0;
            }
            if(!$coupon_code) {
                return $price;
            }


        // Sanitize coupon code.
            $coupon_code = wc_format_coupon_code( $coupon_code );

            // Get the coupon.
            $the_coupon = new WC_Coupon( $coupon_code );
            if($the_coupon) {

                $amount = $the_coupon->get_amount();
                if($the_coupon->get_discount_type() == 'fixed_product'){
                    $discounted = $price - $amount;
                    return ($discounted < 0 ) ? 0 : $discounted ;
                } else {
                    return $price - ($price *  ($amount / 100) ) ;
                }    
            } else {
                return $price;
            }
            

    }

    public static function ajax_update_slots( ) {
           // get slots
        
            $listing_id = $_POST['listing_id'];
            $date_end = $_POST['date_start'];
            $date_start = $_POST['date_end'];
            
            $dayofweek = date('w', strtotime($date_start));
            
            $un_slots = get_post_meta( $listing_id, '_slots', true );
            
            $_slots = self :: get_slots_from_meta ( $listing_id );
            
            if(!$_slots){
                $_slots = $un_slots;
            }
            //sloty na dany dzien:
            if($dayofweek == 0){
                $actual_day = 6;    
            } else {
                $actual_day = $dayofweek-1;    
            }
            
           if(is_array($_slots) && !empty($_slots)){
            $_slots_for_day = $_slots[$actual_day];
            } else {
                $_slots_for_day = false;
            }
            $ajax_out = false;
            $new_slots = array();

            // Get current date and time using WordPress timezone for consistent comparison
            if (function_exists('wp_timezone')) {
                $timezone = wp_timezone();
            } else {
                $timezone = new DateTimeZone(wp_timezone_string());
            }

            $datetime = new DateTime('now', $timezone);
            $today = $datetime->format('Y-m-d');
            $hour_now = $datetime->format('Hi');
        
        // make it one hour before
        
        
            $pres_start_date = $date_end;
        //MRJ - END            if(is_array($_slots_for_day) && !empty($_slots_for_day)){
            if (is_array($_slots_for_day) && !empty($_slots_for_day)) {
                foreach ($_slots_for_day as $key => $slot) {
                    //$slot = json_decode( wp_unslash($slot) );
                    
                    $places = explode( '|', $slot );
                    $free_places = $places[1];


                    //get hours and date to check reservation
                    $hours = explode( ' - ', $places[0] );
                    $hour_start = date( "H:i:s", strtotime( $hours[0] ) );
                    $hour_end = date( "H:i:s", strtotime( $hours[1] ) );

                     // add hours to dates
                    $date_start = $_POST['date_start']. ' ' . $hour_start;
                    $date_end = $_POST['date_end']. ' ' . $hour_end;
  

                    $result = self ::  get_slots_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'reservation' ) );
                    $reservations_amount = count( $result );  
                  

                    // $free_places -= self :: temp_reservation_aval( array( 'listing_id' => $listing_id, 'date_start' => $date_start, 'date_end' => $date_end) );

                    $free_places -= $reservations_amount;
                    if($free_places>0){
                        // MRJ - For each slot found for the current day, checks to see if the hour that the slot starts
                        //     - at is earlier than the current hour, and if so ignores it. Otherwise, deals with adding 
                        //     - the slot
                        $hour_bit_of_slot = date( "Hi", strtotime( $hours[0] ) );
             
                        
                        if( $today == $pres_start_date ) {
                            if ( $hour_now < $hour_bit_of_slot) {
                                $new_slots[] = $places[0].'|'.$free_places;
                            }
                        } else {
                            $new_slots[] = $places[0].'|'.$free_places;
                        }                   
                    }
                }
                
                
                ?>

                <?php 
                $days_list = array(
                        0   => __('Monday','listeo_core'),
                        1   => __('Tuesday','listeo_core'),
                        2   => __('Wednesday','listeo_core'),
                        3   => __('Thursday','listeo_core'),
                        4   => __('Friday','listeo_core'),
                        5   => __('Saturday','listeo_core'),
                        6   => __('Sunday','listeo_core'),
                ); 
                ob_start();?><input id="slot" type="hidden" name="slot" value="" />
                <input id="listing_id" type="hidden" name="listing_id" value="<?php echo $listing_id; ?>" 
                <?php
                   
                foreach( $new_slots as $number => $slot) { 
                    
                    $slot = explode('|' , $slot); ?>
                    <!-- Time Slot -->
                    <div class="time-slot" day="<?php echo $actual_day; ?>">
                        <input type="radio" name="time-slot" id="<?php echo $actual_day.'|'.$number; ?>" value="<?php echo $actual_day.'|'.$number; ?>">
                        <label for="<?php echo $actual_day.'|'.$number; ?>">
                            <p class="day"><?php //echo $days_list[$day]; ?></p>
                            <strong><?php echo $slot[0]; ?></strong>
                            <span><?php 
                            $available_count = (int)$slot[1];
                            echo sprintf(
                                _n(
                                    '%d slot available',
                                    '%d slots available',
                                    $available_count,
                                    'listeo_core'
                                ),
                                $available_count
                            );
                            ?></span>
                        </label>
                    </div>
                    <?php } 
                $ajax_out = ob_get_clean();
            } else {
                //no slots for today
            }
            wp_send_json_success( $ajax_out );
            
    }

    /**
     * AJAX endpoint to get carousel slots with real-time availability calculation
     * Calculates availability for a date range (e.g., 14-30 days) for carousel lazy loading
     */
    public static function ajax_get_carousel_slots_availability() {
        // Validate and sanitize inputs
        if (!isset($_POST['listing_id']) || !isset($_POST['date_start']) || !isset($_POST['date_end'])) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }

        $listing_id = intval($_POST['listing_id']);
        $date_start = sanitize_text_field($_POST['date_start']); // YYYY-MM-DD format
        $date_end = sanitize_text_field($_POST['date_end']);     // YYYY-MM-DD format

        // Get slots configuration from meta
        $_slots = self::get_slots_from_meta($listing_id);

        if (!$_slots) {
            $_slots = get_post_meta($listing_id, '_slots', true);
            if (!$_slots) {
                wp_send_json_error(array('message' => 'No slots configured'));
                return;
            }
        }

        // Get current date and time using WordPress timezone
        if (function_exists('wp_timezone')) {
            $timezone = wp_timezone();
        } else {
            $timezone = new DateTimeZone(wp_timezone_string());
        }

        $datetime = new DateTime('now', $timezone);
        $today = $datetime->format('Y-m-d');
        $hour_now = $datetime->format('Hi');

        // Get hide booked slots setting
        $hide_booked_slots = get_option('listeo_hide_booked_slots', 'on');

        // Get all reservations to check for disabled dates
        $records = self::get_bookings(
            $datetime->format('Y-m-d H:i:s'),
            (clone $datetime)->modify('+3 years')->format('Y-m-d H:i:s'),
            array('listing_id' => $listing_id, 'type' => 'reservation'),
            'booking_date',
            '',
            '',
            ''
        );

        // Build disabled dates array from reservations (owner blocks, iCal, etc.)
        $disabled_dates = array();
        if (!empty($records)) {
            foreach ($records as $record) {
                $date_start_rec = date('Y-m-d', strtotime($record['date_start']));
                $date_end_rec = date('Y-m-d', strtotime($record['date_end']));

                // For multi-day bookings, disable all days in the range
                if ($date_start_rec != $date_end_rec) {
                    $period = new DatePeriod(
                        new DateTime($date_start_rec),
                        new DateInterval('P1D'),
                        new DateTime($date_end_rec . ' +1 day')
                    );
                    foreach ($period as $day) {
                        $disabled_dates[] = $day->format('Y-m-d');
                    }
                }
                // For single-day bookings with full day coverage (00:00-23:59), disable the day
                else {
                    $time_start = date('H:i', strtotime($record['date_start']));
                    $time_end = date('H:i', strtotime($record['date_end']));
                    if ($time_start == '00:00' && $time_end == '23:59') {
                        $disabled_dates[] = $date_start_rec;
                    }
                }
            }
        }
        $disabled_dates = array_unique($disabled_dates);

        // Result array indexed by date
        $availability_by_date = array();

        // Iterate through each day in the range
        $current_date = new DateTime($date_start);
        $end_date = new DateTime($date_end);

        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            $day_of_week = (int)$current_date->format('N') - 1; // 0=Monday, 6=Sunday
            if ($day_of_week == -1) $day_of_week = 6; // Sunday adjustment

            // Check if this date is disabled (blocked by owner, iCal, etc.)
            if (in_array($date_str, $disabled_dates)) {
                $availability_by_date[$date_str] = array(); // No slots available for blocked days
                $current_date->modify('+1 day');
                continue;
            }

            // Get slots configured for this day of week
            $slots_for_day = isset($_slots[$day_of_week]) ? $_slots[$day_of_week] : array();

            $available_slots = array();

            if (is_array($slots_for_day) && !empty($slots_for_day)) {
                foreach ($slots_for_day as $key => $slot) {
                    $places = explode('|', $slot);
                    if (count($places) < 2) continue;

                    $time_range = $places[0];
                    $free_places = intval($places[1]); // Configured capacity

                    // Parse time range
                    $hours = explode('-', $time_range);
                    if (count($hours) < 2) continue;

                    $hour_start = date("H:i:s", strtotime(trim($hours[0])));
                    $hour_end = date("H:i:s", strtotime(trim($hours[1])));

                    // Build full datetime strings for query
                    $booking_date_start = $date_str . ' ' . $hour_start;
                    $booking_date_end = $date_str . ' ' . $hour_end;

                    // Query existing bookings for this slot
                    $result = self::get_slots_bookings(
                        $booking_date_start,
                        $booking_date_end,
                        array('listing_id' => $listing_id, 'type' => 'reservation')
                    );
                    $reservations_amount = count($result);

                    // Calculate available spaces
                    $free_places -= $reservations_amount;

                    // Check if slot should be included based on settings
                    $is_booked = ($free_places <= 0);
                    $should_include = ($hide_booked_slots === 'on') ? !$is_booked : true;

                    if ($should_include) {
                        // Check if slot is in the past (for today only)
                        $hour_bit_of_slot = date("Hi", strtotime(trim($hours[0])));

                        if ($today == $date_str) {
                            // Skip past slots on current day
                            if ($hour_now >= $hour_bit_of_slot) {
                                continue;
                            }
                        }

                        $available_slots[] = array(
                            'time' => $time_range,
                            'available' => $free_places,
                            'slot_key' => $key,
                            'day_of_week' => $day_of_week,
                            'is_booked' => $is_booked
                        );
                    }
                }
            }

            // Store availability for this date
            $availability_by_date[$date_str] = $available_slots;

            // Move to next day
            $current_date->modify('+1 day');
        }

        wp_send_json_success($availability_by_date);
    }


    public static function ajax_listeo_bookings_renew_booking() {
        
        //check if booking can be renewed
        $booking_data =  self :: get_booking(sanitize_text_field($_POST['booking_id']));

      
        if($booking_data['status'] == 'expired') {
            $listing_type = get_post_meta ( $booking_data['listing_id'], '_listing_type', true );
            if(listeo_get_booking_type( $booking_data['listing_id']) == 'date_range' || listeo_get_booking_type( $booking_data['listing_id']) == 'rental'){
                $has_free = self :: count_free_places( $booking_data['listing_id'], $booking_data['date_start'], $booking_data['date_end'] );

                if($has_free <= 1){
                     wp_send_json_success( self :: set_booking_status( sanitize_text_field($_POST['booking_id']), 'confirmed') );             
                } else {
                    wp_send_json_error( );
                }
            } else {

                  $result = self :: get_bookings( $booking_data['date_start'], $booking_data['date_end'], array( 'listing_id' => $booking_data['listing_id'], 'type' => 'reservation' ) );
                  if(!empty($result)){
                    wp_send_json_error( );
                } else {
                    wp_send_json_success( self :: set_booking_status( sanitize_text_field($_POST['booking_id']), 'confirmed') );  
                }
                    
            } 

        }
                
            
    }
    /**
    * Ajax bookings dashboard
    *
    *
    */
    public static function ajax_listeo_bookings_manage(  )  {
        $current_user_id = get_current_user_id();
        // when we only changing status
        if ( isset( $_POST['status']) ) {
            // changing status only for owner and admin
            //if ( $current_user_id != $owner_id && ! is_admin() ) return;
            wp_send_json_success( self :: set_booking_status( sanitize_text_field($_POST['booking_id']), sanitize_text_field($_POST['status'])) );              
           
        }

        $args = array (
            'owner_id' => get_current_user_id(),
            'type' => 'reservation'
        );
        
        $offset = ( absint( $_POST['page'] ) - 1 ) * absint( get_option('posts_per_page') );
        $limit =  get_option('posts_per_page');

        if ( isset($_POST['listing_id']) &&  $_POST['listing_id'] != 'show_all'  ) $args['listing_id'] = $_POST['listing_id'];
        if ( isset($_POST['listing_status']) && $_POST['listing_status'] != 'show_all'  ) $args['status'] = $_POST['listing_status'];
        if ( isset($_POST['booking_author']) && $_POST['booking_author'] != 'show_all'  ) $args['bookings_author'] = $_POST['booking_author'];


        if (  $_POST['dashboard_type'] != 'user' ){
            
            if($_POST['date_start']==''){
                $ajax_out = self :: get_newest_bookings( $args, $limit, $offset ); 
                $bookings_max_number = listeo_count_bookings(get_current_user_id(),$args['status'], $args['bookings_author']);    
            } else {
                
                $ajax_out = self :: get_bookings( $_POST['date_start'], $_POST['date_end'], $args, 'booking_date', $limit, $offset,'users' );    
                $bookings_max_number = self :: get_bookings_max( $_POST['date_start'], $_POST['date_end'], $args, 'booking_date');

            }
        }
           

//        if user dont have listings show his reservations
        if ( isset( $_POST['dashboard_type']) && $_POST['dashboard_type'] == 'user' ) {
            unset( $args['owner_id'] );
            unset($args['status']);
            unset($args['listing_id']);
            
            $args['bookings_author'] = get_current_user_id();
            if($_POST['date_start']==''){
                $ajax_out = self :: get_newest_bookings( $args, $limit, $offset ); 
                $bookings_max_number = listeo_count_my_bookings(get_current_user_id(),$args['status']);    
            } else {
                $ajax_out = self :: get_bookings( $_POST['date_start'], $_POST['date_end'], $args, 'booking_date', $limit, $offset, 'users' );    
                $bookings_max_number = self :: get_bookings_max( $_POST['date_start'], $_POST['date_end'], $args, 'booking_date');
            }

        }
        $result = array();
        $template_loader = new Listeo_Core_Template_Loader;
        $max_number_pages = ceil($bookings_max_number/$limit);
        
        ob_start();
        if($ajax_out){
        
            foreach ($ajax_out as $key => $value) {
                if ( isset($_POST['dashboard_type']) && $_POST['dashboard_type'] == 'user' ) {
                    $template_loader->set_template_data( $value )->get_template_part( 'booking/content-user-booking' );      
                } else {
                    $template_loader->set_template_data( $value )->get_template_part( 'booking/content-booking' );      
                }
                
            }
        } 
      
        $result['pagination'] = listeo_core_ajax_pagination( $max_number_pages, absint( $_POST['page'] ) );
        $result['html'] = ob_get_clean();
        wp_send_json_success( $result );

    }


    /**
    * Bulk insert owner reservations for performance (avoids N individual INSERT queries)
    *
    * @param  int    $listing_id  Post ID of the listing
    * @param  array  $dates       Array of date strings
    * @param  int    $date_now    Timestamp threshold for filtering past dates
    *
    * @return void
    */
    private static function bulk_insert_reservations($listing_id, $dates, $date_now) {
        global $wpdb;

        $owner_id = get_current_user_id();
        $created  = current_time('mysql');

        $values       = array();
        $placeholders = array();

        foreach ($dates as $date) {
            $date_timestamp = strtotime($date);
            if (false === $date_timestamp || $date_timestamp < $date_now) {
                continue;
            }
            $date_start = date('Y-m-d H:i:s', $date_timestamp);
            $date_end   = date('Y-m-d H:i:s', $date_timestamp + (23 * HOUR_IN_SECONDS) + (59 * MINUTE_IN_SECONDS) + 59);

            $placeholders[] = '(%d, %d, %d, %s, %s, %s, %s, %s, %s)';
            $values[] = $owner_id;             // bookings_author
            $values[] = $owner_id;             // owner_id
            $values[] = $listing_id;           // listing_id
            $values[] = $date_start;           // date_start
            $values[] = $date_end;             // date_end
            $values[] = 'owner reservations';  // comment
            $values[] = 'reservation';         // type
            $values[] = $created;              // created
            $values[] = 'owner_reservations';  // status
        }

        if (empty($placeholders)) return;

        // Insert in batches of 100 to avoid query size limits
        $batch_size   = 100;
        $vals_per_row = 9;
        $chunks       = array_chunk($placeholders, $batch_size);
        $value_chunks = array_chunk($values, $batch_size * $vals_per_row);

        foreach ($chunks as $i => $chunk) {
            $sql = "INSERT INTO {$wpdb->prefix}bookings_calendar
                    (bookings_author, owner_id, listing_id, date_start, date_end, comment, type, created, status)
                    VALUES " . implode(', ', $chunk);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are hardcoded above
            $result = $wpdb->query($wpdb->prepare($sql, $value_chunks[$i]));
            if (false === $result) {
                error_log('Listeo bulk_insert_reservations error: ' . $wpdb->last_error);
            }
        }
    }

    /**
    * Bulk insert special prices for performance (avoids N individual INSERT queries)
    *
    * @param  int    $listing_id  Post ID of the listing
    * @param  array  $prices      Associative array of date => price
    *
    * @return void
    */
    private static function bulk_insert_special_prices($listing_id, $prices) {
        global $wpdb;

        $owner_id = get_current_user_id();
        $created  = current_time('mysql');

        $values       = array();
        $placeholders = array();

        foreach ($prices as $date => $price) {
            $date_timestamp = strtotime($date);
            if (false === $date_timestamp || $date_timestamp <= 0) {
                continue;
            }
            if (!is_numeric($price)) {
                continue;
            }
            $price = round((float) $price, 2);
            $date_formatted = date('Y-m-d H:i:s', $date_timestamp);

            $placeholders[] = '(%d, %d, %d, %s, %s, %s, %s, %s)';
            $values[] = $owner_id;        // bookings_author
            $values[] = $owner_id;        // owner_id
            $values[] = $listing_id;      // listing_id
            $values[] = $date_formatted;  // date_start
            $values[] = $date_formatted;  // date_end
            $values[] = (string) $price;  // comment (stores the price)
            $values[] = 'special_price';  // type
            $values[] = $created;         // created
        }

        if (empty($placeholders)) return;

        // Insert in batches of 100 to avoid query size limits
        $batch_size   = 100;
        $vals_per_row = 8;
        $chunks       = array_chunk($placeholders, $batch_size);
        $value_chunks = array_chunk($values, $batch_size * $vals_per_row);

        foreach ($chunks as $i => $chunk) {
            $sql = "INSERT INTO {$wpdb->prefix}bookings_calendar
                    (bookings_author, owner_id, listing_id, date_start, date_end, comment, type, created)
                    VALUES " . implode(', ', $chunk);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are hardcoded above
            $result = $wpdb->query($wpdb->prepare($sql, $value_chunks[$i]));
            if (false === $result) {
                error_log('Listeo bulk_insert_special_prices error: ' . $wpdb->last_error);
            }
        }
    }

    /**
    * Insert booking with args
    *
    * @param  array $args list of parameters
    *
    */
    public static function insert_booking( $args )  {

        global $wpdb;

        // Allow plugins to modify booking data before insertion
        $args = apply_filters( 'listeo_before_insert_booking_data', $args );

        // Parse and validate booking dates
        $date_start_ts = strtotime( $args['date_start'] );
        $date_end_ts = strtotime( $args['date_end'] );

        // Fallback: try to parse event date from listing meta if date parsing failed
        if ( $date_start_ts === false || $date_start_ts <= 0 ) {
            $listing_id = isset($args['listing_id']) ? absint($args['listing_id']) : 0;
            if ( $listing_id > 0 ) {
                $event_ts = get_post_meta($listing_id, '_event_date_timestamp', true);
                if ( !empty($event_ts) && intval($event_ts) > 0 ) {
                    $date_start_ts = intval($event_ts);
                    $safe_date = isset($args['date_start']) ? sanitize_text_field($args['date_start']) : '';
                    error_log("Listeo Booking: date_start parse failed for '{$safe_date}', using _event_date_timestamp for listing {$listing_id}");
                }
            }
        }
        if ( $date_end_ts === false || $date_end_ts <= 0 ) {
            $date_end_ts = $date_start_ts; // fallback to start date
        }

        // Final safety: do not store dates before year 2000
        if ( $date_start_ts === false || $date_start_ts < 946684800 ) {
            $safe_date = isset($args['date_start']) ? sanitize_text_field($args['date_start']) : '';
            $safe_listing = isset($args['listing_id']) ? absint($args['listing_id']) : 'unknown';
            error_log("Listeo Booking: Invalid date_start '{$safe_date}' for listing {$safe_listing}. Booking not created.");
            return false;
        }
        if ( $date_end_ts === false || $date_end_ts < 946684800 ) {
            $date_end_ts = $date_start_ts;
        }

        // Zero-length interval guard. A date-only booking (no time
        // component) parses to YYYY-MM-DD 00:00:00 on both ends, which
        // would store a zero-length row. Strict-overlap SQL (e.g.
        // LBP's resource conflict check uses `<` / `>`) can't see
        // zero-length rows and would let a second booking through for
        // the same date / resource — the customer-reported double-
        // booking. Expand `date_end` to end-of-day when both ends are
        // midnight AND end <= start so the row carries a real range.
        // Single-day bookings with a real time slot (e.g. 14:00–14:30)
        // are unaffected because `date_end > date_start`.
        if ( $date_end_ts <= $date_start_ts
            && '00:00:00' === date( 'H:i:s', $date_start_ts ) ) {
            $date_end_ts = strtotime( date( 'Y-m-d 23:59:59', $date_start_ts ) );
        }

        $insert_data = array(
            'bookings_author' => $args['bookings_author'] ?? get_current_user_id(),
            'owner_id' => $args['owner_id'],
            'listing_id' => $args['listing_id'],
            'date_start' => date( "Y-m-d H:i:s", $date_start_ts ),
            'date_end' => date( "Y-m-d H:i:s", $date_end_ts ),
            'comment' =>  $args['comment'],
            'type' =>  $args['type'],
            'created' => current_time('mysql')
        );

        if ( isset( $args['order_id'] ) ) $insert_data['order_id'] = $args['order_id'];
        if ( isset( $args['expiring'] ) ) $insert_data['expiring'] = $args['expiring'];
        if ( isset( $args['status'] ) ) $insert_data['status'] = $args['status'];
        if ( isset( $args['price'] ) ) $insert_data['price'] = $args['price'];

        $wpdb -> insert( $wpdb->prefix . 'bookings_calendar', $insert_data );

        $booking_id = $wpdb -> insert_id;

        // Allow plugins to perform actions after booking is created
        do_action( 'listeo_after_insert_booking', $booking_id, $args );

        return $booking_id;

    }

    /**
    * Set booking status - we changing booking status only by this function
    *
    * @param  array $args list of parameters
    *
    * @return number of deleted records
    */
    public static function set_booking_status( $booking_id, $status ) {

        global $wpdb;

        $booking_id = absint($booking_id);
        $status = sanitize_text_field($status);
        $booking_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}bookings_calendar` WHERE `id` = %d", $booking_id ), 'ARRAY_A' );
        if(!$booking_data){
            return;
        }

        $user_id = $booking_data['bookings_author']; 
        $owner_id = $booking_data['owner_id'];
        $current_user_id = get_current_user_id();

        // get information about users
        $user_info = get_userdata( $user_id );
        
        $owner_info = get_userdata( $owner_id );
        $comment = json_decode($booking_data['comment']);
        $payment_option = get_post_meta($booking_data['listing_id'], '_payment_option', true);

        // Extract email from booking form with fallback to WordPress user profile
        $user_email = !empty($comment->email)
            ? $comment->email                       // Priority: email from booking form
            : ($user_info ? $user_info->user_email  // Fallback: WordPress user profile email
            : '');

        // only one time clicking blocking
        if ( $booking_data['status'] == $status ) return;
        

        switch ( $status ) 
        {

            // this is status when listing waiting for approval by owner
            case 'waiting' :

                $update_values['status'] = 'waiting';

                // mail for user
                $mail_to_user_args = array(
                    'email' => $user_email,
                    'booking'  => $booking_data,
                );
                do_action('listeo_mail_to_user_waiting_approval',$mail_to_user_args);
                // wp_mail( $user_info->user_email, __( 'Welcome traveler', 'listeo_core' ), __( 'Your reservation waiting for be approved by owner!', 'listeo_core' ) );
                
                // mail for owner
                $mail_to_owner_args = array(
                    'email'     => $owner_info->user_email,
                    'booking'  => $booking_data,
                );
                
                do_action('listeo_mail_to_owner_new_reservation',$mail_to_owner_args);
                // wp_mail( $owner_info->user_email, __( 'Welcome owner', 'listeo_core' ), __( 'In your panel waiting new reservation to be accepted!', 'listeo_core' ) );

            break;

            // this is status when listing is confirmed by owner and waiting to payment
            
            case 'pay_to_confirm' :
            case 'confirmed' :

                // get woocommerce product id
                $product_id = get_post_meta( $booking_data['listing_id'], 'product_id', true);

                // calculate when listing will be expired when will bo not pays
                $expired_after = get_post_meta( $booking_data['listing_id'], '_expired_after', true);
               
                $default_booking_expiration_time = get_option('listeo_default_booking_expiration_time');

                if(empty($expired_after)) {
                    $expired_after = $default_booking_expiration_time;
                }

                // Only set expiring date if payment is required (not free and not cash payment)
                if(!empty($expired_after) && $expired_after > 0 && $booking_data['price'] > 0 && $payment_option != 'pay_cash'){
                    // define( 'MY_TIMEZONE', (get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : date_default_timezone_get() ) );
                    // date_default_timezone_set( MY_TIMEZONE );
                    $expiring_date = wp_date("Y-m-d H:i:s", strtotime('+' . $expired_after . ' hours'));
                }
               

              
                $instant_booking = apply_filters('listeo_instant_booking', get_post_meta( $booking_data['listing_id'], '_instant_booking', true));

                if($instant_booking) {

                    // Only send "booking confirmed" email if payment is NOT required.
                    // When payment is required (pay_to_confirm), the confirmation email
                    // will be sent after payment completes (status changes to 'paid').
                    if ( $status !== 'pay_to_confirm' ) {
                        $mail_to_user_args = array(
                            'email' => $user_email,
                            'booking'  => $booking_data,
                        );
                        do_action('listeo_mail_to_user_instant_approval', $mail_to_user_args);
                    }

                    // Always notify the owner about the new reservation
                    $mail_to_owner_args = array(
                        'email'     => $owner_info->user_email,
                        'booking'  => $booking_data,
                    );

                    do_action('listeo_mail_to_owner_new_instant_reservation', $mail_to_owner_args);

                }
                if($payment_option == 'pay_cash') {
                    // mail for user
                    //wp_mail( $user_info->user_email, __( 'Welcome traveler', 'listeo_core' ), __( 'Your is paid!', 'listeo_core' ) );


                    $mail_args = array(
                        'email'     => $user_email,
                        'booking'  => $booking_data,
                    );
                    do_action('listeo_mail_to_user_pay_cash_confirmed', $mail_args);                
                    $update_values['expiring'] = '';
                    
                }
                
                // for free listings
                if ( $booking_data['price'] == 0 )
                {

                    // check if booking_data has coupon
                    $coupon = (isset($comment->coupon) && !empty($comment->coupon)) ? $comment->coupon : false;
                    // this is woocommerce coupon, check if it has usage limits
                    if ($coupon) {
                        $coupons = explode(',', $coupon);
                        foreach ($coupons as $key => $new_coupon) {
                            $coupon = new WC_Coupon($new_coupon);
                            $coupon->increase_usage_count();
                        }
                    }
                    // mail for user
                    //wp_mail( $user_info->user_email, __( 'Welcome traveler', 'listeo_core' ), __( 'Your is paid!', 'listeo_core' ) );
                    $mail_args = array(
                    'email'     => $user_email,
                    'booking'  => $booking_data,
                    );
                    do_action('listeo_mail_to_user_free_confirmed',$mail_args);

                    $update_values['status'] = 'paid';
                    $update_values['expiring'] = '';

                    break;
                    
                }



                $first_name = (isset($comment->first_name) && !empty($comment->first_name)) ? $comment->first_name : get_user_meta( $user_id, "billing_first_name", true) ;
                
                $last_name = (isset($comment->last_name) && !empty($comment->last_name)) ? $comment->last_name : get_user_meta( $user_id, "billing_last_name", true) ;


                if (empty($first_name) && $user_info) {
                    $first_name = $user_info->first_name ?: $user_info->display_name;
                }
                if (empty($last_name)  && $user_info) {
                    $last_name  = $user_info->last_name  ?: '-';
                } 
                // always provide something

                $phone = (isset($comment->phone) && !empty($comment->phone)) ? $comment->phone : get_user_meta( $user_id, "billing_phone", true) ;

                $billing_address_1 = (isset($comment->billing_address_1) && !empty($comment->billing_address_1)) ? $comment->billing_address_1 : '';
                
                $billing_city = (isset($comment->billing_city) && !empty($comment->billing_city)) ? $comment->billing_city : '';
                
                $billing_postcode = (isset($comment->billing_postcode) && !empty($comment->billing_postcode)) ? $comment->billing_postcode : '';
                $billing_state = (isset($comment->billing_state) && !empty($comment->billing_state)) ? $comment->billing_state : '';
                
                $billing_country = (isset($comment->billing_country) && !empty($comment->billing_country)) ? $comment->billing_country : ''; 

                $coupon = (isset($comment->coupon) && !empty($comment->coupon)) ? $comment->coupon : false;


                $billing_data = self::ensure_user_billing_data($user_id, $comment, $user_info);

                // $address = array(
                //     'first_name' => $first_name,
                //     'last_name'  => $last_name,
                //     'address_1'  => $billing_address_1,

                //     'city'       => $billing_city,
                //     'state'     => $billing_state,
                //     'postcode'  => $billing_postcode,
                //     'country'   => $billing_country,

                // );
                $address = array(
                    'first_name' => $billing_data['first_name'],
                    'last_name'  => $billing_data['last_name'],
                    'address_1'  => $billing_data['address_1'],
                    'city'       => $billing_data['city'],
                    'state'      => $billing_data['state'],
                    'postcode'   => $billing_data['postcode'],
                    'country'    => $billing_data['country'],
                );
                // Determine if we need to create a new order:
                // - No order_id yet (first time)
                // - Renewal: order_id exists but the old order was cancelled when booking expired
                $payment_url = '';
                $needs_new_order = empty($booking_data['order_id']);

                if ( ! $needs_new_order ) {
                    // Check if existing order was cancelled (happens on expiration)
                    $existing_order = wc_get_order( $booking_data['order_id'] );
                    if ( ! $existing_order || $existing_order->has_status( 'cancelled' ) ) {
                        $needs_new_order = true;
                    } else {
                        // Existing order is still valid, just get payment URL from it
                        $payment_url = $existing_order->get_checkout_payment_url();
                    }
                }

                if( $needs_new_order ){

                    if (empty($product_id) || FALSE === get_post_status($product_id)) {
                        //check if post with post_id exists

                        //we need to create product
                        $product_id = listeo_create_product($booking_data['listing_id']);
                    }
                // creating woocommerce order
                    $order = wc_create_order();

                    $price_before_coupons = (isset($comment->price) && !empty($comment->price)) ? $comment->price : $booking_data['price'];

                    $comment = json_decode($booking_data['comment']);
                    $product = wc_get_product($product_id);
                    $product->set_price($price_before_coupons);
                    $order->add_product($product, 1 );


                    $order->set_address( $address, 'billing' );
                    $order->set_address( $address, 'shipping' );
                    $order->set_billing_first_name($first_name);
                    $order->set_billing_last_name($last_name);
                    $order->set_billing_phone( $phone );
                    $order->set_customer_id($user_id);
                    $order->set_billing_email( $user_email );



                    $note = listeo_get_extra_services_html($comment->service);

                    // Add the note if not empty
                    if(!empty($note)){
                    $order->add_order_note( $note );
                    }

                   $custom_fields = array(
                        'billing_vat',
                        '_vat_id',
                   );
                   foreach ($custom_fields as $key) {
                        $value = get_booking_meta($booking_id, $key);

                        if(!empty($value)){
                            $order->update_meta_data($key, $value);
                        }
                   }

                    $order->set_prices_include_tax('yes');
                    if ($coupon) {

                        $coupons = explode(',', $coupon);
                        foreach ($coupons as $key => $new_coupon) {

                            $order->apply_coupon(sanitize_text_field($new_coupon));
                        }
                    }

                    $payment_url = $order->get_checkout_payment_url();


                    $order->calculate_totals();
                    $order->save();

                    $order->update_meta_data('booking_id', $booking_id);
                    $order->update_meta_data('owner_id', $owner_id);
                    $order->update_meta_data('listing_id', $booking_data['listing_id']);
                    if(isset($comment->service)){

                        $order->update_meta_data('listeo_services', $comment->service);
                    }

                    // Store expiration date on the order for payment validation
                    if ( isset( $expiring_date ) && ! empty( $expiring_date ) ) {
                        $order->update_meta_data( '_listeo_booking_expiration', $expiring_date );
                    }

                    $order->save_meta_data();



                    $update_values['order_id'] = $order->get_order_number();

                }


                if(isset($expiring_date)){
                    $update_values['expiring'] = $expiring_date;
                } else {
                    $expiring_date = false;
                }
                
                $update_values['status'] = $status;
                
                 // mail for user
                 //wp_mail( $user_info->user_email, __( 'Welcome traveler', 'listeo_core' ), sprintf( __( 'Your reservation waiting for payment! Ple ase do it before %s hours. Here is link: %s', 'listeo_core' ), $expired_after, $payment_url  ) );
                 $mail_args = array(
                    'email'         => $user_email,
                    'booking'       => $booking_data,
                    'expiration'    => $expiring_date,
                    'payment_url'   => $payment_url
                    );

                if ($payment_option != 'pay_cash') {
                do_action('listeo_mail_to_user_pay',$mail_args);
                }

             //end confirmed/ paid to confirm                  
            break;





            // this is status when listing is confirmed by owner and already paid
            case 'paid' :

                // mail for owner
                //wp_mail( $owner_info->user_email, __( 'Welcome owner', 'listeo_core' ), __( 'Your client paid!', 'listeo_core' ) );
                $mail_to_owner_args = array(
                    'email'     => $owner_info->user_email,
                    'booking'  => $booking_data,
                );


                do_action('listeo_mail_to_owner_paid',$mail_to_owner_args);

                $mail_to_user_args = array(
                    'email'     => $user_email,
                    'booking'   => $booking_data,
                );


                do_action('listeo_mail_to_user_paid',$mail_to_user_args);
                 // mail for user
                // wp_mail( $user_info->user_email, __( 'Welcome traveler', 'listeo_core' ), __( 'Your is paid!', 'listeo_core' ) );

                 $update_values['status'] = 'paid';
                 $update_values['expiring'] = '';                               
                

            break;

            // this is status when listing is confirmed by owner and already paid
            case 'cancelled' :

                // mail for user
                //wp_mail( $user_info->user_email, __( 'Welcome traveler', 'listeo_core' ), __( 'Your reservation was cancelled by owner', 'listeo_core' ) );
                $mail_to_user_args = array(
                    'email'     => $user_email,
                    'booking'  => $booking_data,
                );
                do_action('listeo_mail_to_user_canceled',$mail_to_user_args);

                $mail_to_owner_args = array(
                    'email'     => $owner_info->user_email,
                    'booking'  => $booking_data,
                );

                do_action('listeo_mail_to_owner_canceled', $mail_to_owner_args);

                // Hook for additional cancellation actions (e.g., delete Zoom meetings)
                do_action('listeo_booking_cancelled', $booking_id, $booking_data);

                // delete order if exist
                if ( $booking_data['order_id'] )
                {
                    $order = wc_get_order( $booking_data['order_id'] );
                    $order->update_status( 'cancelled', __( 'Order is cancelled.', 'listeo_core' ) );
                }
                $comment = json_decode($booking_data['comment']);
                if(isset( $comment->tickets )){
                       $tickets_from_order = $comment->tickets;

                    $bp_handled = apply_filters( 'listeo_event_tickets_sold_update', false, array_merge( (array) $booking_data, array( 'tickets' => -(int) $tickets_from_order, 'release' => true ) ), $booking_id );
                    if ( ! $bp_handled ) {
                        $sold_tickets = (int) get_post_meta( $booking_data['listing_id'],"_event_tickets_sold",true);
                        update_post_meta( $booking_data['listing_id'],"_event_tickets_sold",$sold_tickets-(int)$tickets_from_order);
                    }
                }
             
                $update_values['status'] = 'cancelled';
                $update_values['expiring'] = '';  

            break;
             // this is status when listing is confirmed by owner and already paid
            case 'deleted' :


               if($owner_id == $current_user_id || $user_id == $current_user_id  ){


                    if ( $booking_data['order_id'] )
                    {
                        $order = wc_get_order( $booking_data['order_id'] );
                        //$order->update_status( 'cancelled', __( 'Order is cancelled.', 'listeo_core' ) );
                    }

                    // Release tickets back to availability for event listings
                    $comment = json_decode($booking_data['comment']);
                    if(isset( $comment->tickets )){
                        $tickets_from_order = $comment->tickets;
                        $bp_handled = apply_filters( 'listeo_event_tickets_sold_update', false, array_merge( (array) $booking_data, array( 'tickets' => -(int) $tickets_from_order, 'release' => true ) ), $booking_id );
                        if ( ! $bp_handled ) {
                            $sold_tickets = (int) get_post_meta( $booking_data['listing_id'],"_event_tickets_sold",true);
                            update_post_meta( $booking_data['listing_id'],"_event_tickets_sold",$sold_tickets-(int)$tickets_from_order);
                        }
                    }

                    return $wpdb -> delete( $wpdb->prefix . 'bookings_calendar', array( 'id' => $booking_id ) );
                }

            break;

             case 'expired' :

                // Release tickets back to availability for event listings
                $comment = json_decode($booking_data['comment']);
                if(isset( $comment->tickets )){
                    $tickets_from_order = $comment->tickets;
                    $bp_handled = apply_filters( 'listeo_event_tickets_sold_update', false, array_merge( (array) $booking_data, array( 'tickets' => -(int) $tickets_from_order, 'release' => true ) ), $booking_id );
                    if ( ! $bp_handled ) {
                        $sold_tickets = (int) get_post_meta( $booking_data['listing_id'],"_event_tickets_sold",true);
                        update_post_meta( $booking_data['listing_id'],"_event_tickets_sold",$sold_tickets-(int)$tickets_from_order);
                    }
                }

                $update_values['status'] = 'expired';
                delete_post_meta($booking_data['listing_id'], "_listing_expires");


            break;

            case 'refund' :

                // Release tickets back to availability for event listings
                $comment = json_decode($booking_data['comment']);
                if(isset( $comment->tickets )){
                    $tickets_from_order = $comment->tickets;
                    $bp_handled = apply_filters( 'listeo_event_tickets_sold_update', false, array_merge( (array) $booking_data, array( 'tickets' => -(int) $tickets_from_order, 'release' => true ) ), $booking_id );
                    if ( ! $bp_handled ) {
                        $sold_tickets = (int) get_post_meta( $booking_data['listing_id'],"_event_tickets_sold",true);
                        update_post_meta( $booking_data['listing_id'],"_event_tickets_sold",$sold_tickets-(int)$tickets_from_order);
                    }
                }

                // Notify user and owner about refund
                $mail_to_user_args = array(
                    'email'     => $user_email,
                    'booking'  => $booking_data,
                );
                do_action('listeo_mail_to_user_refund',$mail_to_user_args);

                $mail_to_owner_args = array(
                    'email'     => $owner_info->user_email,
                    'booking'  => $booking_data,
                );
                do_action('listeo_mail_to_owner_refund', $mail_to_owner_args);

                $update_values['status'] = 'refund';
                $update_values['expiring'] = '';

            break;
        }

        return $wpdb -> update( $wpdb->prefix . 'bookings_calendar', $update_values, array( 'id' => $booking_id ) );

    }
    /**
     * Update user billing information from booking comment data
     * Only updates fields that are currently empty in user meta
     * 
     * @param int $user_id
     * @param object $comment Booking comment data
     * @param object $user_info WP User object
     * @return array Updated billing data
     */
    private static function ensure_user_billing_data($user_id, $comment, $user_info)
    {
        $billing_mapping = array(
            'first_name' => $comment->first_name ?? '',
            'last_name' => $comment->last_name ?? '',
            'phone' => $comment->phone ?? '',
            'email' => $comment->email ?? '',
            'address_1' => $comment->billing_address_1 ?? '',
            'city' => $comment->billing_city ?? '',
            'postcode' => $comment->billing_postcode ?? '',
            'state' => $comment->billing_state ?? '',
            'country' => $comment->billing_country ?? ''
        );

        $final_billing_data = array();

        foreach ($billing_mapping as $field_name => $comment_value) {
            $final_billing_data[$field_name] = self::get_user_billing_field(
                $user_id,
                $field_name,
                $comment_value,
                $user_info
            );
        }

        return $final_billing_data;
    }


    /**
     * Get user billing field with fallback priority and auto-update user meta
     * 
     * Priority: 
     * 1. Existing user meta (if not empty)
     * 2. Form data from booking comment (if not empty) - saves to user meta
     * 3. User profile data (if available) - saves to user meta
     * 4. Empty string
     * 
     * @param int $user_id
     * @param string $field_name
     * @param string $form_value Value from booking comment
     * @param object $user_info Optional user info object
     * @return string
     */
    private static function get_user_billing_field($user_id, $field_name, $form_value = '', $user_info = null)
    {
        $billing_field_name = "billing_{$field_name}";

        // First check if user already has this billing field filled
        $existing_value = get_user_meta($user_id, $billing_field_name, true);

        // If existing value exists and is not empty, use it
        if (!empty($existing_value)) {
            return $existing_value;
        }

        // If form provided a value and it's not empty, use it and update user meta
        if (!empty($form_value)) {
            update_user_meta($user_id, $billing_field_name, $form_value);
            return $form_value;
        }

        // Special handling for specific fields using user profile data
        $profile_value = '';
        switch ($field_name) {
            case 'first_name':
                if ($user_info && !empty($user_info->first_name)) {
                    $profile_value = $user_info->first_name;
                } elseif ($user_info && !empty($user_info->display_name)) {
                    $profile_value = $user_info->display_name;
                }
                break;

            case 'last_name':
                if ($user_info && !empty($user_info->last_name)) {
                    $profile_value = $user_info->last_name;
                }
                break;

            case 'email':
                if ($user_info && !empty($user_info->user_email)) {
                    $profile_value = $user_info->user_email;
                }
                break;

            case 'phone':
                // Check if user has phone in their profile
                $profile_phone = get_user_meta($user_id, 'phone', true);
                if (!empty($profile_phone)) {
                    $profile_value = $profile_phone;
                }
                break;
        }

        // Update user meta if we found a profile value
        if (!empty($profile_value)) {
            update_user_meta($user_id, $billing_field_name, $profile_value);
            return $profile_value;
        }

        return '';
    }

    /**
    * Delete all booking wih parameters
    *
    * @param  array $args list of parameters
    *
    * @return number of deleted records
    */
    public static function delete_bookings( $args )  {

        global $wpdb;

        // Validate $args is an array and not empty
        if (!is_array($args) || empty($args)) {
            // If $args is invalid or empty, return false
            return false;
        }

        // Before deleting, retrieve bookings that match the criteria to check for tickets
        $where_clauses = array();
        $where_values = array();

        foreach ($args as $key => $value) {
            // Sanitize column name to prevent SQL injection
            $safe_key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            $where_clauses[] = "`$safe_key` = %s";
            $where_values[] = $value;
        }

        // If no valid where clauses were built, return false
        if (empty($where_clauses)) {
            return false;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Properly prepare the query with placeholders
        $placeholders = implode(', ', array_fill(0, count($where_values), '%s'));
        $query = "SELECT * FROM {$wpdb->prefix}bookings_calendar WHERE $where_sql";

        $bookings_to_delete = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                $where_values
            ),
            ARRAY_A
        );

        // Release tickets for each booking that has them
        if ($bookings_to_delete) {
            foreach ($bookings_to_delete as $booking) {
                $comment = json_decode($booking['comment']);
                if (isset($comment->tickets) && $comment->tickets > 0) {
                    $bp_handled = apply_filters( 'listeo_event_tickets_sold_update', false, array_merge( (array) $booking, array( 'tickets' => -(int) $comment->tickets, 'release' => true ) ), isset( $booking['id'] ) ? $booking['id'] : 0 );
                    if ( ! $bp_handled ) {
                        $sold_tickets = (int) get_post_meta($booking['listing_id'], "_event_tickets_sold", true);
                        update_post_meta($booking['listing_id'], "_event_tickets_sold", $sold_tickets - $comment->tickets);
                    }
                }
            }
        }

        return $wpdb -> delete( $wpdb->prefix . 'bookings_calendar', $args );

    }

    /**
    * Update owner reservation list by deleting old ones and adding new ones
    *
    * @param  number $listing_id post id of current listing
    * @param  array $dates Array of individual dates
    * @param  array $ranges Array of date ranges (optional)
    *
    * @return void
    */
    public static function update_reservations($listing_id, $dates = array(), $ranges = array()) {
        // Delete old reservations
        self::delete_bookings(array(
            'listing_id' => $listing_id,  
            'owner_id' => get_current_user_id(),
            'type' => 'reservation',
            'comment' => 'owner reservations'
        ));

        $date_now = strtotime("-1 days");
        
        // Handle individual dates — batch insert for performance
        if (!empty($dates)) {
            self::bulk_insert_reservations($listing_id, $dates, $date_now);
        }
        
        // Handle date ranges (new format)
        if (!empty($ranges)) {
            $ranges_array = json_decode($ranges, true);
            
            if (is_array($ranges_array)) {
                foreach ($ranges_array as $range) {
                    if (isset($range['start']) && isset($range['end'])) {
                        $start_date = strtotime($range['start']);
                        $end_date = strtotime($range['end']);
                        
                        // Skip ranges that are in the past
                        if ($end_date < $date_now) {
                            continue;
                        }
                        
                        // Adjust start date if it's in the past
                        if ($start_date < $date_now) {
                            $start_date = $date_now;
                        }
                        
                        // Create a single booking entry for the entire range
                        self::insert_booking(array(
                            'listing_id' => $listing_id,  
                            'type' => 'reservation',
                            'owner_id' => get_current_user_id(),
                            'date_start' => date('Y-m-d 00:00:00', $start_date),
                            'date_end' => date('Y-m-d 23:59:59', $end_date),
                            'comment' => 'owner reservations',
                            'order_id' => NULL,
                            'status' => 'owner_reservations'
                        ));
                    }
                }
            }
        }
    }

    /**
    * Update listing special prices
    *
    * @param  number $listing_id post id of current listing
    * @param  array $prices with dates and prices
    *
    * @return string $prices array with special prices
    */
    public static function update_special_prices( $listing_id, $prices ) {

        // delecting old special prices
        self :: delete_bookings ( array(
            'listing_id' => $listing_id,  
            'owner_id' => get_current_user_id(),
            'type' => 'special_price') );

        // Batch insert special prices for performance
        if (!empty($prices)) {
            self::bulk_insert_special_prices($listing_id, $prices);
        }

    }


    /**
    * Calculate price
    *
    * @param  number $listing_id post id of current listing
    * @param  date  $date_start since we checking
    * @param  date  $date_end to we checking
    *
    * @return number $price of all booking at all
    */
    public static function calculate_price( $listing_id, $date_start, $date_end, $multiply = 1, $children_count = 0, $animals_count = 0, $services = false, $coupon= false ) {
        
        
        // get all special prices between two dates from listeo settings special prices
        $special_prices_results = self :: get_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'special_price' ) );

        $listing_type = get_post_meta( $listing_id, '_listing_type', true);

        // prepare special prices to nice array
        foreach ($special_prices_results as $result) 
        {
            $special_prices[ $result['date_start'] ] = $result['comment'];
        }

        // get normal prices from listeo listing settings
        $normal_price = (float) get_post_meta ( $listing_id, '_normal_price', true);
        $weekend_price = (float)  get_post_meta ( $listing_id, '_weekday_price', true);
        
        if(empty($weekend_price)){
            $weekend_price = $normal_price;
        }
        
        $reservation_price = (float) get_post_meta ( $listing_id, '_reservation_price', true);
        $_count_per_guest = get_post_meta ( $listing_id, '_count_per_guest', true);
        $services_price = 0;

        // Get children discount percentage
        $children_discount = (float) get_post_meta($listing_id, '_children_price', true);
        $child_rate  = 0;
        // Get pet fees
        $animal_fee = (float) get_post_meta($listing_id, '_animal_fee', true);
        $animal_fee_type = get_post_meta($listing_id, '_animal_fee_type', true);

        // Repeatable fees engine — defer summing until services_price is
        // built up below, so percent-type fees see the right subtotal.
        // For the event/tickets path the multiplier is "tickets" rather
        // than "nights", which is why this site uses the tickets context
        // key instead of nights.
        $services_price += listeo_sum_listing_fees( $listing_id, array(
            'tickets'      => max( 1, (int) $multiply ),
            'guests'       => max( 1, (int) $multiply ),
            'subtotal'     => ( $normal_price * (int) $multiply ) + $reservation_price,
            'date_start'   => $date_start,
            'listing_type' => $listing_type,
        ) );

        // Check for both 'event' (old) and 'tickets' (new) booking types
        if(listeo_get_booking_type($listing_id) == 'event' || listeo_get_booking_type($listing_id) == 'tickets'){
            if(isset($services) && !empty($services)){
                $bookable_services = listeo_get_bookable_services($listing_id);
                $countable = array_column($services,'value');

                $i = 0;
                foreach ($bookable_services as $key => $service) {

                    if(in_array(sanitize_title($service['name']),array_column($services,'service'))) {
                        //$services_price += (float) preg_replace("/[^0-9\.]/", '', $service['price']);
                        $services_price +=  listeo_calculate_service_price($service, $multiply, 1, 0, 0, $countable[$i] );

                       $i++;
                    }


                }
            }
            $price = $services_price+$reservation_price+$normal_price*$multiply;
            //coupon
            if(isset($coupon) && !empty($coupon)){
                $wc_coupon = new WC_Coupon($coupon);

                $coupons = explode(',',$coupon);
                foreach ($coupons as $key => $new_coupon) {

                    $price = self::apply_coupon_to_price($price,$new_coupon);
                }

            }
            return $price;
        }
        // prepare dates for loop
        // TODO CHECK THIS
        // $format = "d/m/Y  H:i:s";
        //     $firstDay =  DateTime::createFromFormat($format, $date_start. '00:00:01' );
        //     $lastDay =  DateTime::createFromFormat($format, $date_end. '23:59:59')
        //     ;
        //


        if (listeo_get_booking_type($listing_id) != 'date_range') {
            $firstDay = new DateTime( $date_start );

            $lastDay = new DateTime( $date_end . '23:59:59') ;

        } else {
            $firstDay = new DateTime( $date_start );
            $lastDay = new DateTime( $date_end );
            if(get_option('listeo_count_last_day_booking')){
                $lastDay = $lastDay->modify('+1 day');
            } else {
                // For same-day bookings, ensure at least one day is counted
                if($date_start == $date_end) {
                    $lastDay = $lastDay->modify('+1 day');
                }
            }

        }
        $days_between = $lastDay->diff($firstDay)->format("%a");
        $days_count = ($days_between == 0) ? 1 : $days_between ;
        //fix for not calculating last day of leaving
        //if ( $date_start != $date_end ) $lastDay -> modify('-1 day');
        
        $interval = DateInterval::createFromDateString('1 day');
        
        $period = new DatePeriod( $firstDay, $interval, $lastDay );

        // at start we have reservation price
         $price = 0;
      
        foreach ( $period as $current_day ) {

            // get current date in sql format
            $date = $current_day->format("Y-m-d 00:00:00");
            $day = $current_day->format("N");

            if ( isset( $special_prices[$date] ) ) 
            {
                $price += $special_prices[$date];
            }
            else {
                $start_of_week = intval( get_option( 'start_of_week' ) ); // 0 - sunday, 1- monday
                // when we have weekends
                if($start_of_week == 0 ) {
                    if ( isset( $weekend_price ) && $day == 5 || $day == 6) {
                        $price += $weekend_price;
                    }  else { $price += $normal_price; }
                } else {
                    if ( isset( $weekend_price ) && $day == 6 || $day == 7) {
                        $price += $weekend_price;
                     }  else { $price += $normal_price; }
                } 

            }

        }
        if($_count_per_guest) {
            // Split multiply into adults and children
            $adults = isset($_POST['adults']) ? (int) $_POST['adults'] : $multiply;
            $children = isset($_POST['children']) ? (int) $_POST['children'] : $children_count;
            
            // Calculate base price for adults
            $adults_price = $price * $adults;
            
            // Calculate price for children with discount
            $children_price = 0;
            if($children > 0 && !empty($children_discount)) {
                // Apply the percentage discount for each child
                $child_rate = $price * (1 - ($children_discount/100));
                $children_price = $child_rate * $children;
            }
            
            // Total price is sum of adults and children prices
            $price = $adults_price + $children_price;
        }
        $services_price = 0;
        // Repeatable fees engine. At this point $price holds the base
        // accommodation total (weekend/special days × adult/child split).
        // That's the right subtotal for percent-type fees.
        $_guests_for_fees = isset( $adults )
            ? (int) $adults + (int) ( isset( $children ) ? $children : 0 )
            : (int) $multiply;
        $services_price += listeo_sum_listing_fees( $listing_id, array(
            'nights'       => max( 1, (int) $days_count ),
            'guests'       => max( 1, $_guests_for_fees ),
            'subtotal'     => (float) $price,
            'date_start'   => $date_start,
            'listing_type' => $listing_type,
        ) );

        if(isset($services) && !empty($services)){
            $bookable_services = listeo_get_bookable_services($listing_id);
            $countable = array_column($services,'value');
            if (!isset($children)) {
                $children = 0;
            }

            $i = 0;
            foreach ($bookable_services as $key => $service) {
                
                if(in_array(sanitize_title($service['name']),array_column($services,'service'))) {
                    //$services_price += (float) preg_replace("/[^0-9\.]/", '', $service['price']);
                    if (!isset($adults)) {
                        $adults = $multiply;
                    }
                    $services_price +=  listeo_calculate_service_price($service, $adults, $children, $children_discount, $days_count, $countable[$i] );
                    
                   $i++;
                }
               
            
            } 
        }
        
        // Add base price
        $price += $reservation_price + $services_price;

        // Add pet fees if applicable
        if(!empty($animals_count) && !empty($animal_fee)) {
         
            if($animal_fee_type == 'per_night') {
                // Per night fee - multiply by number of nights and animals
                $price += ($animal_fee * $days_count * $animals_count);
            } else {
                // One time fee per pet
                $price += ($animal_fee * $animals_count);
            }
        }


        //coupon
        if(isset($coupon) && !empty($coupon)){
            $wc_coupon = new WC_Coupon($coupon);
            
            $coupons = explode(',',$coupon);
            foreach ($coupons as $key => $new_coupon) {
                
                $price = self::apply_coupon_to_price($price,$new_coupon);
            }
            
        }

        
        
       // $endprice = round($price,2);

        $decimals = get_option('listeo_number_decimals',2);
        $endprice = number_format_i18n($price,$decimals);

        return apply_filters('listeo_booking_price_calc',$price, $listing_id, $date_start, $date_end, $multiply , $services);

    }

    // calculate price by hours
    public static function calculate_price_by_hours($listing_id, $date_start, $date_end, $hours, $multiply = 1, $children = 0, $animals = 0, $services = false, $coupon = false)
    {
 

        // Get base pricing - these are already hourly rates
        $normal_price = (float) get_post_meta($listing_id, '_normal_price', true);
        $weekend_price = (float) get_post_meta($listing_id, '_weekday_price', true);
        $reservation_price = (float) get_post_meta($listing_id, '_reservation_price', true);
        $_count_per_guest = get_post_meta($listing_id, '_count_per_guest', true);

        // Get pet fees
        // Get children discount percentage
        $children_discount = (float) get_post_meta($listing_id, '_children_price', true);
        $child_rate  = 0;
        // Get pet fees
        $animal_fee = (float) get_post_meta($listing_id, '_animal_fee', true);
        $animal_fee_type = get_post_meta($listing_id, '_animal_fee_type', true);

        if (empty($weekend_price)) {
            $weekend_price = $normal_price;
        }

        // Get special prices
        $special_prices_results = self::get_bookings(
            $date_start,
            $date_end,
            array('listing_id' => $listing_id, 'type' => 'special_price')
        );

        // Process special prices into array
        $special_prices = array();
        foreach ($special_prices_results as $result) {
            $special_prices[$result['date_start']] = $result['comment'];
        }

        // Setup date handling
        $firstDay = new DateTime($date_start);
        $date = $firstDay->format("Y-m-d 00:00:00");
        $day = (int) $firstDay->format("N"); // 1-7 (Monday-Sunday)

        // Get the hourly price based on weekday/weekend or special price
        if (isset($special_prices[$date])) {
            $price = $special_prices[$date];
        } else {
            $start_of_week = intval(get_option('start_of_week'));
            if ($start_of_week == 0) { // Sunday start
                $is_weekend = ($day == 5 || $day == 6); // Friday or Saturday
            } else { // Monday start
                $is_weekend = ($day == 6 || $day == 7); // Saturday or Sunday
            }
            $price = $is_weekend ? $weekend_price : $normal_price;
        }

        // Multiply by number of hours
        $price = $price * $hours;
        
        // Apply guest multiplier if enabled
        if ($_count_per_guest) {
            // Split multiply into adults and children
            $adults = isset($_POST['adults']) ? (int) $_POST['adults'] : $multiply;
            $children = isset($_POST['children']) ? (int) $_POST['children'] : $children;
          
            // Calculate base price for adults
            $adults_price = $price * max(1, (int) $adults);

            // Calculate price for children with discount
            $children_price = 0;
            
            if ($children > 0 && !empty($children_discount)) {
                // Apply the percentage discount for each child
                $child_rate = $price * (1 - ($children_discount / 100));
                $children_price = $child_rate * $children;
            }
         
            // Total price is sum of adults and children prices
            $price = $adults_price + $children_price;
            
        }

        // Add services pricing
        $services_price = 0;

        // Repeatable fees engine. $price already reflects hourly base ×
        // adult/child split, so use it as the subtotal for percent fees.
        $_guests_for_fees = isset( $adults )
            ? (int) $adults + (int) ( isset( $children ) ? $children : 0 )
            : (int) $multiply;
        $services_price += listeo_sum_listing_fees( $listing_id, array(
            'hours'        => max( 1, (int) $hours ),
            'guests'       => max( 1, $_guests_for_fees ),
            'nights'       => 1, // single-day hourly bookings count as 1 night
            'subtotal'     => (float) $price,
            'date_start'   => $date_start,
            'listing_type' => get_post_meta( $listing_id, '_listing_type', true ),
        ) );

        // Calculate optional services
        if (!empty($services)) {
            $bookable_services = listeo_get_bookable_services($listing_id);
            $countable = array_column($services, 'value');

            $i = 0;
            foreach ($bookable_services as $service) {
                if (in_array(sanitize_title($service['name']), array_column($services, 'service'))) {
                    $services_price += listeo_calculate_service_price(
                        $service,
                        $multiply,
                        $children,
                        $children_discount,
                        $hours, // Use hours directly here
                        isset($countable[$i]) ? $countable[$i] : 1
                    );
                    $i++;
                }
            }
        }

        // Add reservation fee and services
        $price += $reservation_price + $services_price;
        // Add pet fees if applicable
        if (!empty($animals_count) && !empty($animal_fee)) {

            if ($animal_fee_type == 'per_night') {
                // Per night fee - multiply by number of nights and animals
                $price += ($animal_fee * $hours * $animals_count);
            } else {
                // One time fee per pet
                $price += ($animal_fee * $animals_count);
            }
        }
        // Apply coupons
        if (!empty($coupon)) {
     
            $coupons = explode(',', $coupon);
            foreach ($coupons as $new_coupon) {
                $price = self::apply_coupon_to_price($price, $new_coupon);
            }
        }

        return apply_filters(
            'listeo_booking_price_calc',
            $price,
            $listing_id,
            $date_start,
            $date_end,
            $multiply,
            $services
        );
    }

    /**
    * Calculate price
    *
    * @param  number $listing_id post id of current listing
    * @param  date  $date_start since we checking
    * @param  date  $date_end to we checking
    *
    * @return number $price of all booking at all
    */
    public static function calculate_price_per_hour( $listing_id, $date_start, $date_end, $start_hour, $end_hour, $multiply = 1, $children = false, $animals = false, $services = false, $coupon= false ) {

        
        // get all special prices between two dates from listeo settings special prices
        $special_prices_results = self :: get_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'special_price' ) );

        $listing_type = get_post_meta( $listing_id, '_listing_type', true);

        // prepare special prices to nice array
        foreach ($special_prices_results as $result) 
        {
            $special_prices[ $result['date_start'] ] = $result['comment'];
        }


        // get normal prices from listeo listing settings
        $normal_price = (float) get_post_meta ( $listing_id, '_normal_price', true);
        $weekend_price = (float)  get_post_meta ( $listing_id, '_weekday_price', true);
        // Get pet fees
        // Get children discount percentage
        $children_discount = (float) get_post_meta($listing_id, '_children_price', true);
        $child_rate  = 0;
        // Get pet fees
        $animal_fee = (float) get_post_meta($listing_id, '_animal_fee', true);
        $animal_fee_type = get_post_meta($listing_id, '_animal_fee_type', true);

        if(empty($weekend_price)){
            
            $weekend_price = $normal_price;
        }
        $time1 = strtotime($start_hour);
        $time2 = strtotime($end_hour);
        //count difference in hours if 2nd day
        // if($date_start != $date_end){
        //     $difference = round(abs($time2 - $time1) / 3600, 2) + 24;
        // } else {
        //     $difference = round(abs($time2 - $time1) / 3600, 2);
        // }
        if ($time2 <= $time1) {
            $time2 += 24 * 60 * 60; 
        }
        $difference = ($time2 - $time1) / (60 * 60);

        
       // $difference = round(abs($time2 - $time1) / 3600, 2);
        
        $reservation_price  =  (float) get_post_meta ( $listing_id, '_reservation_price', true);
        $_count_per_guest  = get_post_meta ( $listing_id, '_count_per_guest', true);
        $services_price = 0;
        
     
    
        $firstDay = new DateTime( $date_start );
        
        $lastDay = new DateTime( $date_end . '23:59:59') ;
        
       
        $days_between = $lastDay->diff($firstDay)->format("%a");
        $days_count = ($days_between == 0) ? 1 : $days_between ;
        //fix for not calculating last day of leaving
        //if ( $date_start != $date_end ) $lastDay -> modify('-1 day');
        
        $interval = DateInterval::createFromDateString('1 day');
        
        $period = new DatePeriod( $firstDay, $interval, $lastDay );

        // at start we have reservation price
         $price = 0;
   
        foreach ( $period as $current_day ) {

            // get current date in sql format
            $date = $current_day->format("Y-m-d 00:00:00");
            $day = $current_day->format("N");

            if ( isset( $special_prices[$date] ) ) 
            {
                $price += $special_prices[$date] * $difference;
            }
            else {
                $start_of_week = intval( get_option( 'start_of_week' ) ); // 0 - sunday, 1- monday
                // when we have weekends
                if($start_of_week == 0 ) {
                    if ( isset( $weekend_price ) && $day == 5 || $day == 6) {
                        $price += $weekend_price*$difference;
                    }  else { $price += $normal_price * $difference; }
                } else {
                    if ( isset( $weekend_price ) && $day == 6 || $day == 7) {
                        $price += $weekend_price * $difference;
                     }  else { $price += $normal_price * $difference; }
                } 

            }

        }
        if($_count_per_guest){
            $adults = isset($_POST['adults']) ? (int) $_POST['adults'] : $multiply;
            $children = isset($_POST['children']) ? (int) $_POST['children'] : $children;

            // Calculate base price for adults
            $adults_price = $price * max(1, (int) $adults) ;

            // Calculate price for children with discount
            $children_price = 0;
            if ($children > 0 && !empty($children_discount)) {
                // Apply the percentage discount for each child
                $child_rate = $price * (1 - ($children_discount / 100));
                $children_price = $child_rate * $children ;
            }

            // Total price is sum of adults and children prices
            $price = $adults_price + $children_price;
        }
        $services_price = 0;

        // Repeatable fees engine. $difference holds the requested duration
        // in hours; $days_count is span in days for multi-day hourly
        // listings. $price is already the post-adult/child base subtotal.
        $_guests_for_fees = isset( $adults )
            ? (int) $adults + (int) ( isset( $children ) && is_numeric( $children ) ? $children : 0 )
            : (int) $multiply;
        $services_price += listeo_sum_listing_fees( $listing_id, array(
            'hours'        => max( 1, (int) $difference ),
            'nights'       => max( 1, (int) $days_count ),
            'guests'       => max( 1, $_guests_for_fees ),
            'subtotal'     => (float) $price,
            'date_start'   => $date_start,
            'listing_type' => $listing_type,
        ) );

        if(isset($services) && !empty($services)){

            $bookable_services = listeo_get_bookable_services($listing_id);
            $countable = array_column($services,'value');
          
            $i = 0;
            foreach ($bookable_services as $key => $service) {
                
                
                if(in_array(sanitize_title($service['name']),array_column($services,'service'))) { 
                    //$services_price += (float) preg_replace("/[^0-9\.]/", '', $service['price']);
                    $services_price +=  listeo_calculate_service_price($service, $multiply, $children, $children_discount, $days_count, $countable[$i] );
                    
                   $i++;
                }
               
            
            } 
        }
        
        $price += $reservation_price + $services_price;
        if (!empty($animals_count) && !empty($animal_fee)) {

            if ($animal_fee_type == 'per_night') {
                // Per night fee - multiply by number of nights and animals
                $price += ($animal_fee * $difference * $animals_count);
            } else {
                // One time fee per pet
                $price += ($animal_fee * $animals_count);
            }
        }

        //coupon
        if(isset($coupon) && !empty($coupon)){
            $wc_coupon = new WC_Coupon($coupon);
            
            $coupons = explode(',',$coupon);
            foreach ($coupons as $key => $new_coupon) {
                
                $price = self::apply_coupon_to_price($price,$new_coupon);
            }
            
        }

        
        
       // $endprice = round($price,2);

        $decimals = get_option('listeo_number_decimals',2);
        $endprice = number_format_i18n($price,$decimals);

        return apply_filters('listeo_booking_price_calc',$price, $listing_id, $date_start, $date_end, $multiply , $services);

    }

    /* =====================================================================
     * Price-breakdown methods
     * ---------------------------------------------------------------------
     * Three parallel public methods (`calculate_price_breakdown`,
     * `calculate_price_by_hours_breakdown`, `calculate_price_per_hour_breakdown`)
     * that return a structured array describing how the total was assembled:
     *
     *     array(
     *         'booking_type'    => 'date_range' | 'tickets' | 'hours' | 'per_hour',
     *         'units'           => int,
     *         'units_label'     => '3 nights',
     *         'guests'          => int,
     *         'children'        => int,
     *         'lines'           => array[ array('key','label','amount',...), ... ],
     *         'subtotal'        => float,   // sum of lines before coupon
     *         'coupon'          => array|null,
     *         'total'           => float,   // post-coupon, post-filter
     *         'currency_symbol' => string,
     *         'currency_position' => 'before' | 'after',
     *         'decimals'        => int,
     *     )
     *
     * The existing float-returning methods (`calculate_price`, etc.) are
     * intentionally NOT modified — they keep their signatures and 30+ call
     * sites work unchanged. The breakdown methods re-implement the same
     * math and call the same `listeo_booking_price_calc` filter at the
     * same point so LBP add-ons that hook the filter still apply (the
     * delta is surfaced as a synthetic "Adjustment" line).
     *
     * Add-ons can post-process the breakdown via the
     * `listeo_booking_price_breakdown` filter, which receives the array
     * plus the same args the float filter does.
     * ===================================================================== */

    /**
     * Build the currency context once so each line item can format itself
     * without re-reading options. Returned shape matches what
     * `listeo_format_fee_line()` accepts.
     */
    private static function _breakdown_currency_args() {
        $currency_abbr   = get_option( 'listeo_currency' );
        $currency_symbol = class_exists( 'Listeo_Core_Listing' )
            ? Listeo_Core_Listing::get_currency_symbol( $currency_abbr )
            : '';
        // `get_currency_symbol()` returns HTML entities (`&#36;` for `$`,
        // `&euro;` for `€`, etc.). Inside breakdown labels the symbol
        // is concatenated into strings that later get HTML-escaped
        // client-side (XSS guard on user-controlled fee titles), which
        // would turn `&#36;` into `&amp;#36;` and render as literal
        // `&#36;`. Decode once at the source so every downstream
        // consumer (labels, amount_formatted, JSON) carries the raw
        // glyph and escaping rules apply correctly.
        if ( $currency_symbol && false !== strpos( $currency_symbol, '&' ) ) {
            $currency_symbol = html_entity_decode( $currency_symbol, ENT_QUOTES, 'UTF-8' );
        }
        return array(
            'symbol'   => $currency_symbol,
            'position' => (string) get_option( 'listeo_currency_postion', 'before' ),
            'decimals' => (int) get_option( 'listeo_number_decimals', 2 ),
        );
    }

    /**
     * Format a single monetary amount using the site's currency settings.
     */
    private static function _breakdown_format( $amount, $currency_args ) {
        $formatted = number_format_i18n( (float) $amount, (int) $currency_args['decimals'] );
        return ( 'after' === $currency_args['position'] )
            ? $formatted . ' ' . $currency_args['symbol']
            : $currency_args['symbol'] . $formatted;
    }

    /**
     * Build a single line item with both raw and formatted amount.
     *
     * `label` is the friendly title (e.g. "Nightly rate"). Pass a
     * `sublabel` via $extra to show a second dim line under the title
     * (e.g. "$25.00 × 2 nights"). Matches the Airbnb-style two-line
     * layout the Core + LBP breakdown lists render.
     */
    private static function _breakdown_line( $key, $label, $amount, $currency_args, $extra = array() ) {
        $line = array(
            'key'              => $key,
            'label'            => $label,
            'sublabel'         => null,
            'amount'           => (float) $amount,
            'amount_formatted' => self::_breakdown_format( $amount, $currency_args ),
            'is_discount'      => false,
            'service_slug'     => null,
            'fee_id'           => null,
        );
        if ( is_array( $extra ) ) {
            foreach ( $extra as $k => $v ) {
                $line[ $k ] = $v;
            }
        }
        return $line;
    }

    /**
     * Reconcile a breakdown against the filtered total. After we apply
     * `listeo_booking_price_calc`, add-ons may return a different price.
     * Surface the delta as an "Adjustment" line so the lines + total
     * still reconcile. No-op when the filter is the identity.
     */
    private static function _breakdown_reconcile( &$lines, &$subtotal, $coupon, $expected_pre_coupon_total, $filtered_total, $currency_args ) {
        // Expected post-coupon total = expected_pre_coupon - coupon_amount.
        $coupon_amt = ( is_array( $coupon ) && isset( $coupon['amount'] ) ) ? (float) $coupon['amount'] : 0.0;
        $expected_post = $expected_pre_coupon_total + $coupon_amt; // coupon_amt is negative
        $delta = (float) $filtered_total - $expected_post;
        if ( abs( $delta ) < 0.005 ) {
            return; // No meaningful change.
        }
        // Adjustment is added before coupon, so it scales correctly when
        // the coupon is a percentage. The simpler choice is to add a
        // bare "Adjustment" line at the end of `lines` and bump subtotal
        // by the raw delta.
        $lines[]   = self::_breakdown_line( 'adjustment', __( 'Adjustment', 'listeo_core' ), $delta, $currency_args );
        $subtotal += $delta;
    }

    /**
     * Apply coupons (comma-separated) to a price using the same path the
     * float-returning methods use. Returns an array describing the
     * discount, or null when nothing applied.
     */
    private static function _breakdown_apply_coupon( $coupon, $pre_coupon_total, $currency_args ) {
        if ( empty( $coupon ) ) {
            return null;
        }
        $codes = array_filter( array_map( 'trim', explode( ',', (string) $coupon ) ) );
        if ( empty( $codes ) ) {
            return null;
        }
        $post_coupon = (float) $pre_coupon_total;
        foreach ( $codes as $code ) {
            $post_coupon = self::apply_coupon_to_price( $post_coupon, $code );
        }
        $discount = (float) $post_coupon - (float) $pre_coupon_total;
        if ( abs( $discount ) < 0.005 ) {
            return null;
        }
        return array(
            'code'             => implode( ', ', $codes ),
            'amount'           => $discount,
            'amount_formatted' => self::_breakdown_format( $discount, $currency_args ),
            'label'            => sprintf(
                /* translators: %s: coupon code(s) */
                __( 'Coupon (%s)', 'listeo_core' ),
                implode( ', ', $codes )
            ),
        );
    }

    /**
     * Localized "N nights" / "N hours" / etc. helpers — keeps the
     * units_label consistent across breakdown methods.
     */
    private static function _breakdown_units_label( $count, $unit ) {
        $count = (int) $count;
        switch ( $unit ) {
            case 'night':
                /* translators: %d: number of nights */
                return sprintf( _n( '%d night', '%d nights', $count, 'listeo_core' ), $count );
            case 'hour':
                /* translators: %d: number of hours */
                return sprintf( _n( '%d hour', '%d hours', $count, 'listeo_core' ), $count );
            case 'ticket':
                /* translators: %d: number of tickets */
                return sprintf( _n( '%d ticket', '%d tickets', $count, 'listeo_core' ), $count );
        }
        return (string) $count;
    }

    /**
     * Date-range breakdown — covers rental + event/tickets paths since
     * `calculate_price()` does both. Same arguments as `calculate_price`.
     */
    public static function calculate_price_breakdown( $listing_id, $date_start, $date_end, $multiply = 1, $children_count = 0, $animals_count = 0, $services = false, $coupon = false ) {
        $currency_args = self::_breakdown_currency_args();
        $booking_type  = listeo_get_booking_type( $listing_id );
        $listing_type  = get_post_meta( $listing_id, '_listing_type', true );

        $normal_price      = (float) get_post_meta( $listing_id, '_normal_price', true );
        $weekend_price     = (float) get_post_meta( $listing_id, '_weekday_price', true );
        if ( empty( $weekend_price ) ) {
            $weekend_price = $normal_price;
        }
        $reservation_price = (float) get_post_meta( $listing_id, '_reservation_price', true );
        $_count_per_guest  = get_post_meta( $listing_id, '_count_per_guest', true );
        $children_discount = (float) get_post_meta( $listing_id, '_children_price', true );
        $animal_fee        = (float) get_post_meta( $listing_id, '_animal_fee', true );
        $animal_fee_type   = get_post_meta( $listing_id, '_animal_fee_type', true );

        $lines = array();

        // -------- Event / Tickets path (early return) --------------------
        if ( 'event' === $booking_type || 'tickets' === $booking_type ) {
            $tickets = max( 1, (int) $multiply );

            // Accommodation = ticket × tickets
            $accommodation = $normal_price * $tickets;
            $lines[] = self::_breakdown_line(
                'accommodation',
                __( 'Ticket price', 'listeo_core' ),
                $accommodation,
                $currency_args,
                array(
                    'sublabel' => sprintf(
                        /* translators: 1: per-ticket price, 2: localized "N tickets" */
                        __( '%1$s × %2$s', 'listeo_core' ),
                        self::_breakdown_format( $normal_price, $currency_args ),
                        self::_breakdown_units_label( $tickets, 'ticket' )
                    ),
                )
            );

            if ( $reservation_price > 0 ) {
                $lines[] = self::_breakdown_line(
                'reservation_fee',
                __( 'Reservation fee', 'listeo_core' ),
                $reservation_price,
                $currency_args,
                array( 'sublabel' => __( 'One-time charge', 'listeo_core' ) )
            );
            }

            // Services
            $services_sum = 0.0;
            if ( ! empty( $services ) && is_array( $services ) ) {
                $bookable_services = listeo_get_bookable_services( $listing_id );
                $countable         = array_column( $services, 'value' );
                $i = 0;
                foreach ( $bookable_services as $service ) {
                    if ( in_array( sanitize_title( $service['name'] ), array_column( $services, 'service' ), true ) ) {
                        $qty = isset( $countable[ $i ] ) ? (int) $countable[ $i ] : 1;
                        $amount = (float) listeo_calculate_service_price( $service, $tickets, 1, 0, 0, $qty );
                        $lines[] = self::_breakdown_line(
                            'service',
                            $service['name'],
                            $amount,
                            $currency_args,
                            array(
                                'service_slug' => sanitize_title( $service['name'] ),
                                'sublabel'     => $qty > 1 ? '×' . $qty : null,
                            )
                        );
                        $services_sum += $amount;
                        $i++;
                    }
                }
            }

            // Mandatory fees — itemized via the engine. Subtotal context
            // matches the legacy calculate_price() event branch.
            $fee_context = array(
                'tickets'      => $tickets,
                'guests'       => $tickets,
                'subtotal'     => ( $normal_price * $tickets ) + $reservation_price,
                'date_start'   => $date_start,
                'listing_type' => $listing_type,
            );
            $fees_sum = 0.0;
            if ( function_exists( 'listeo_get_applicable_listing_fees' ) ) {
                foreach ( listeo_get_applicable_listing_fees( $listing_id, $fee_context ) as $fee ) {
                    $line = listeo_format_fee_line( $fee, $fee_context, $currency_args );
                    $sublabel = '';
                    if ( isset( $line['label'] ) && isset( $line['title'] ) && $line['label'] !== $line['title'] ) {
                        $sublabel = trim( str_replace( $line['title'], '', $line['label'] ) );
                        $sublabel = trim( $sublabel, ' ()' );
                    }
                    $lines[] = self::_breakdown_line(
                        'mandatory_fee',
                        isset( $line['title'] ) ? $line['title'] : $line['label'],
                        $line['amount'],
                        $currency_args,
                        array(
                            'fee_id'   => $fee['id'],
                            'sublabel' => $sublabel ?: null,
                        )
                    );
                    $fees_sum += (float) $line['amount'];
                }
            }

            $pre_coupon_total = $accommodation + $reservation_price + $services_sum + $fees_sum;
            $subtotal         = $pre_coupon_total;

            $coupon_line = self::_breakdown_apply_coupon( $coupon, $pre_coupon_total, $currency_args );
            $total       = $pre_coupon_total + ( $coupon_line ? (float) $coupon_line['amount'] : 0.0 );

            $filtered = (float) apply_filters( 'listeo_booking_price_calc', $total, $listing_id, $date_start, $date_end, $multiply, $services );
            self::_breakdown_reconcile( $lines, $subtotal, $coupon_line, $pre_coupon_total, $filtered, $currency_args );

            return apply_filters( 'listeo_booking_price_breakdown', array(
                'booking_type'      => 'tickets',
                'units'             => $tickets,
                'units_label'       => self::_breakdown_units_label( $tickets, 'ticket' ),
                'guests'            => $tickets,
                'children'          => 0,
                'lines'             => $lines,
                'subtotal'          => (float) $subtotal,
                'subtotal_formatted'=> self::_breakdown_format( $subtotal, $currency_args ),
                'coupon'            => $coupon_line,
                'total'             => (float) $filtered,
                'total_formatted'   => self::_breakdown_format( $filtered, $currency_args ),
                'currency_symbol'   => $currency_args['symbol'],
                'currency_position' => $currency_args['position'],
                'decimals'          => $currency_args['decimals'],
            ), $listing_id, $date_start, $date_end, $multiply, $services, $coupon );
        }

        // -------- Date-range / single-day path ---------------------------
        // Mirrors calculate_price()'s date iteration (lines 3490–3545).
        if ( 'date_range' !== $booking_type ) {
            $firstDay = new DateTime( $date_start );
            $lastDay  = new DateTime( $date_end . '23:59:59' );
        } else {
            $firstDay = new DateTime( $date_start );
            $lastDay  = new DateTime( $date_end );
            if ( get_option( 'listeo_count_last_day_booking' ) ) {
                $lastDay = $lastDay->modify( '+1 day' );
            } elseif ( $date_start === $date_end ) {
                $lastDay = $lastDay->modify( '+1 day' );
            }
        }
        $days_between = $lastDay->diff( $firstDay )->format( '%a' );
        $days_count   = ( 0 == $days_between ) ? 1 : (int) $days_between;

        // Special prices override per day
        $special_prices = array();
        foreach ( self::get_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'special_price' ) ) as $sp ) {
            $special_prices[ $sp['date_start'] ] = (float) $sp['comment'];
        }

        // Sum accommodation per day, tracking whether rates are uniform
        // for the label ("$X × N nights" only when every day has the
        // same rate; otherwise just "N nights" with the computed subtotal).
        $period          = new DatePeriod( $firstDay, DateInterval::createFromDateString( '1 day' ), $lastDay );
        $base_accom      = 0.0;
        $observed_rates  = array();
        $start_of_week   = (int) get_option( 'start_of_week' );
        foreach ( $period as $current_day ) {
            $day_key = $current_day->format( 'Y-m-d 00:00:00' );
            $dow     = (int) $current_day->format( 'N' );
            if ( isset( $special_prices[ $day_key ] ) ) {
                $rate = (float) $special_prices[ $day_key ];
            } else {
                if ( 0 === $start_of_week ) {
                    $rate = ( $dow === 5 || $dow === 6 ) ? $weekend_price : $normal_price;
                } else {
                    $rate = ( $dow === 6 || $dow === 7 ) ? $weekend_price : $normal_price;
                }
            }
            $base_accom    += $rate;
            $observed_rates[ (string) $rate ] = true;
        }

        $accommodation = $base_accom;
        $child_rate    = 0.0;
        $adults_count  = (int) $multiply;
        $children_eff  = (int) $children_count;

        if ( $_count_per_guest ) {
            $adults_count = isset( $_POST['adults'] )   ? (int) $_POST['adults']   : (int) $multiply;
            $children_eff = isset( $_POST['children'] ) ? (int) $_POST['children'] : (int) $children_count;
            $adults_part  = $base_accom * $adults_count;
            $children_part = 0.0;
            if ( $children_eff > 0 && $children_discount > 0 ) {
                $child_rate    = $base_accom * ( 1 - ( $children_discount / 100 ) );
                $children_part = $child_rate * $children_eff;
            }
            $accommodation = $adults_part + $children_part;

            // Label vocabulary depends on booking type. Multi-night
            // rentals use "Nightly rate" + "× N nights"; single-day
            // services/appointments use "Service rate" without the
            // misleading "× 1 night" multiplier.
            $is_rental    = in_array( $booking_type, array( 'date_range', 'rental' ), true );
            $accom_label  = $is_rental ? __( 'Nightly rate', 'listeo_core' ) : __( 'Service rate', 'listeo_core' );
            $unit_segment = $is_rental ? ( ' × ' . self::_breakdown_units_label( $days_count, 'night' ) ) : '';

            // Two-line presentation when children with discount exist.
            if ( $children_eff > 0 && $children_discount > 0 ) {
                $lines[] = self::_breakdown_line(
                    'accommodation',
                    $accom_label,
                    $adults_part,
                    $currency_args,
                    array(
                        'sublabel' => sprintf(
                            /* translators: 1: per-unit rate, 2: optional " × N nights", 3: adult count */
                            __( '%1$s%2$s × %3$d adults', 'listeo_core' ),
                            self::_breakdown_format( $base_accom / max( 1, $days_count ), $currency_args ),
                            $unit_segment,
                            $adults_count
                        ),
                    )
                );
                $lines[] = self::_breakdown_line(
                    'accommodation_children',
                    __( 'Child rate', 'listeo_core' ),
                    $children_part,
                    $currency_args,
                    array(
                        'sublabel' => sprintf(
                            /* translators: 1: per-child-per-unit rate, 2: optional " × N nights", 3: child count */
                            __( '%1$s%2$s × %3$d children', 'listeo_core' ),
                            self::_breakdown_format( $child_rate / max( 1, $days_count ), $currency_args ),
                            $unit_segment,
                            $children_eff
                        ),
                    )
                );
            } else {
                $lines[] = self::_breakdown_line(
                    'accommodation',
                    $accom_label,
                    $accommodation,
                    $currency_args,
                    array(
                        'sublabel' => sprintf(
                            /* translators: 1: per-unit rate, 2: optional " × N nights", 3: guest count */
                            __( '%1$s%2$s × %3$d guests', 'listeo_core' ),
                            self::_breakdown_format( $base_accom / max( 1, $days_count ), $currency_args ),
                            $unit_segment,
                            $adults_count
                        ),
                    )
                );
            }
        } else {
            // No per-guest multiplier. Single accommodation line.
            $is_rental   = in_array( $booking_type, array( 'date_range', 'rental' ), true );
            $accom_label = $is_rental ? __( 'Nightly rate', 'listeo_core' ) : __( 'Service rate', 'listeo_core' );

            // Per-day rate actually charged. When rates are uniform
            // (count($observed_rates) === 1) this equals the single rate;
            // deriving it from $accommodation rather than the listing's base
            // $normal_price keeps the sublabel in sync with the line amount
            // for weekend / special / resource-overridden prices (otherwise
            // the sublabel showed e.g. "$28.00" while the line charged $15).
            $per_day_rate = $accommodation / max( 1, $days_count );

            if ( $is_rental ) {
                $sublabel = count( $observed_rates ) === 1
                    ? sprintf(
                        /* translators: 1: rate, 2: "N nights" */
                        __( '%1$s × %2$s', 'listeo_core' ),
                        self::_breakdown_format( $per_day_rate, $currency_args ),
                        self::_breakdown_units_label( $days_count, 'night' )
                    )
                    : self::_breakdown_units_label( $days_count, 'night' );
            } else {
                // Service/single_day — no nights multiplier. For a single day
                // the per-day rate equals the line amount on the right, so a
                // bare "$50.00" sublabel just duplicates it — omit it. Only
                // show the per-day rate when it actually differs from the
                // total (multi-day) and the rate is uniform.
                $sublabel = ( count( $observed_rates ) === 1 && $days_count > 1 )
                    ? self::_breakdown_format( $per_day_rate, $currency_args )
                    : null;
            }

            $lines[] = self::_breakdown_line(
                'accommodation',
                $accom_label,
                $accommodation,
                $currency_args,
                array( 'sublabel' => $sublabel )
            );
        }

        // Reservation fee
        if ( $reservation_price > 0 ) {
            $lines[] = self::_breakdown_line(
                'reservation_fee',
                __( 'Reservation fee', 'listeo_core' ),
                $reservation_price,
                $currency_args,
                array( 'sublabel' => __( 'One-time charge', 'listeo_core' ) )
            );
        }

        // Mandatory fees — same context the float method uses (subtotal
        // is the post-adult/child accommodation total, NOT including
        // services or reservation).
        $fee_context = array(
            'nights'       => max( 1, (int) $days_count ),
            'guests'       => max( 1, $adults_count + $children_eff ),
            'subtotal'     => (float) $accommodation,
            'date_start'   => $date_start,
            'listing_type' => $listing_type,
        );
        $fees_sum = 0.0;
        if ( function_exists( 'listeo_get_applicable_listing_fees' ) ) {
            foreach ( listeo_get_applicable_listing_fees( $listing_id, $fee_context ) as $fee ) {
                $line = listeo_format_fee_line( $fee, $fee_context, $currency_args );
                // `listeo_format_fee_line` returns `title` (clean fee
                // name) plus `label` (title + frequency hint). Use the
                // title as the breakdown line label, the frequency
                // hint as sublabel.
                $sublabel = '';
                if ( isset( $line['label'] ) && isset( $line['title'] ) && $line['label'] !== $line['title'] ) {
                    // Extract the trailing "(per night)" portion.
                    $sublabel = trim( str_replace( $line['title'], '', $line['label'] ) );
                    $sublabel = trim( $sublabel, ' ()' );
                }
                $lines[] = self::_breakdown_line(
                    'mandatory_fee',
                    isset( $line['title'] ) ? $line['title'] : $line['label'],
                    $line['amount'],
                    $currency_args,
                    array(
                        'fee_id'   => $fee['id'],
                        'sublabel' => $sublabel ?: null,
                    )
                );
                $fees_sum += (float) $line['amount'];
            }
        }

        // Services (after fees so the order matches "stay → fees → extras")
        $services_sum = 0.0;
        if ( ! empty( $services ) && is_array( $services ) ) {
            $bookable_services = listeo_get_bookable_services( $listing_id );
            $countable         = array_column( $services, 'value' );
            $i = 0;
            foreach ( $bookable_services as $service ) {
                if ( in_array( sanitize_title( $service['name'] ), array_column( $services, 'service' ), true ) ) {
                    $qty    = isset( $countable[ $i ] ) ? (int) $countable[ $i ] : 1;
                    $amount = (float) listeo_calculate_service_price( $service, $adults_count, $children_eff, $children_discount, $days_count, $qty );
                    $lines[] = self::_breakdown_line(
                        'service',
                        $service['name'],
                        $amount,
                        $currency_args,
                        array(
                            'service_slug' => sanitize_title( $service['name'] ),
                            'sublabel'     => $qty > 1 ? '×' . $qty : null,
                        )
                    );
                    $services_sum += $amount;
                    $i++;
                }
            }
        }

        // Pet fees
        $pet_amount = 0.0;
        if ( ! empty( $animals_count ) && ! empty( $animal_fee ) ) {
            if ( 'per_night' === $animal_fee_type ) {
                $pet_amount = $animal_fee * $days_count * $animals_count;
                $pet_sub    = sprintf(
                    /* translators: 1: fee per night/pet, 2: "N nights", 3: pet count */
                    __( '%1$s × %2$s × %3$d pets', 'listeo_core' ),
                    self::_breakdown_format( $animal_fee, $currency_args ),
                    self::_breakdown_units_label( $days_count, 'night' ),
                    (int) $animals_count
                );
            } else {
                $pet_amount = $animal_fee * $animals_count;
                $pet_sub    = sprintf(
                    /* translators: 1: fee per pet, 2: pet count */
                    __( '%1$s × %2$d pets', 'listeo_core' ),
                    self::_breakdown_format( $animal_fee, $currency_args ),
                    (int) $animals_count
                );
            }
            $lines[] = self::_breakdown_line(
                'animal_fee',
                __( 'Pet fee', 'listeo_core' ),
                $pet_amount,
                $currency_args,
                array( 'sublabel' => $pet_sub )
            );
        }

        $pre_coupon_total = $accommodation + $reservation_price + $fees_sum + $services_sum + $pet_amount;
        $subtotal         = $pre_coupon_total;

        $coupon_line = self::_breakdown_apply_coupon( $coupon, $pre_coupon_total, $currency_args );
        $total       = $pre_coupon_total + ( $coupon_line ? (float) $coupon_line['amount'] : 0.0 );

        $filtered = (float) apply_filters( 'listeo_booking_price_calc', $total, $listing_id, $date_start, $date_end, $multiply, $services );
        self::_breakdown_reconcile( $lines, $subtotal, $coupon_line, $pre_coupon_total, $filtered, $currency_args );

        return apply_filters( 'listeo_booking_price_breakdown', array(
            'booking_type'      => $booking_type ? $booking_type : 'date_range',
            'units'             => (int) $days_count,
            'units_label'       => self::_breakdown_units_label( $days_count, 'night' ),
            'guests'            => $adults_count + $children_eff,
            'children'          => $children_eff,
            'lines'             => $lines,
            'subtotal'          => (float) $subtotal,
            'subtotal_formatted'=> self::_breakdown_format( $subtotal, $currency_args ),
            'coupon'            => $coupon_line,
            'total'             => (float) $filtered,
            'total_formatted'   => self::_breakdown_format( $filtered, $currency_args ),
            'currency_symbol'   => $currency_args['symbol'],
            'currency_position' => $currency_args['position'],
            'decimals'          => $currency_args['decimals'],
        ), $listing_id, $date_start, $date_end, $multiply, $services, $coupon );
    }

    /**
     * Hourly breakdown — mirrors `calculate_price_by_hours()`. Hour rates
     * are already per-hour; multiplied by hours, then adult/child split,
     * then fees + services.
     */
    public static function calculate_price_by_hours_breakdown( $listing_id, $date_start, $date_end, $hours, $multiply = 1, $children = 0, $animals = 0, $services = false, $coupon = false ) {
        $currency_args = self::_breakdown_currency_args();
        $listing_type  = get_post_meta( $listing_id, '_listing_type', true );

        $normal_price      = (float) get_post_meta( $listing_id, '_normal_price', true );
        $weekend_price     = (float) get_post_meta( $listing_id, '_weekday_price', true );
        if ( empty( $weekend_price ) ) {
            $weekend_price = $normal_price;
        }
        $reservation_price = (float) get_post_meta( $listing_id, '_reservation_price', true );
        $_count_per_guest  = get_post_meta( $listing_id, '_count_per_guest', true );
        $children_discount = (float) get_post_meta( $listing_id, '_children_price', true );
        $animal_fee        = (float) get_post_meta( $listing_id, '_animal_fee', true );
        $animal_fee_type   = get_post_meta( $listing_id, '_animal_fee_type', true );

        // Resolve the day's hourly rate (special > weekend > normal).
        $special_prices = array();
        foreach ( self::get_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'special_price' ) ) as $sp ) {
            $special_prices[ $sp['date_start'] ] = (float) $sp['comment'];
        }
        $firstDay  = new DateTime( $date_start );
        $day_key   = $firstDay->format( 'Y-m-d 00:00:00' );
        $dow       = (int) $firstDay->format( 'N' );
        if ( isset( $special_prices[ $day_key ] ) ) {
            $hourly_rate = (float) $special_prices[ $day_key ];
        } else {
            $start_of_week = (int) get_option( 'start_of_week' );
            $is_weekend = ( 0 === $start_of_week )
                ? ( $dow === 5 || $dow === 6 )
                : ( $dow === 6 || $dow === 7 );
            $hourly_rate = $is_weekend ? $weekend_price : $normal_price;
        }

        $base_accom    = $hourly_rate * (int) $hours;
        $adults_count  = (int) $multiply;
        $children_eff  = (int) $children;
        $accommodation = $base_accom;
        $child_rate    = 0.0;
        $lines         = array();

        if ( $_count_per_guest ) {
            $adults_count = isset( $_POST['adults'] )   ? (int) $_POST['adults']   : (int) $multiply;
            $children_eff = isset( $_POST['children'] ) ? (int) $_POST['children'] : (int) $children;
            $adults_part  = $base_accom * max( 1, $adults_count );
            $children_part = 0.0;
            if ( $children_eff > 0 && $children_discount > 0 ) {
                $child_rate    = $base_accom * ( 1 - ( $children_discount / 100 ) );
                $children_part = $child_rate * $children_eff;
            }
            $accommodation = $adults_part + $children_part;
            if ( $children_eff > 0 && $children_discount > 0 ) {
                $lines[] = self::_breakdown_line(
                    'accommodation',
                    __( 'Hourly rate', 'listeo_core' ),
                    $adults_part,
                    $currency_args,
                    array(
                        'sublabel' => sprintf(
                            __( '%1$s × %2$s × %3$d adults', 'listeo_core' ),
                            self::_breakdown_format( $hourly_rate, $currency_args ),
                            self::_breakdown_units_label( (int) $hours, 'hour' ),
                            $adults_count
                        ),
                    )
                );
                $lines[] = self::_breakdown_line(
                    'accommodation_children',
                    __( 'Child rate', 'listeo_core' ),
                    $children_part,
                    $currency_args,
                    array(
                        'sublabel' => sprintf(
                            __( '%1$s × %2$s × %3$d children', 'listeo_core' ),
                            self::_breakdown_format( $child_rate / max( 1, (int) $hours ), $currency_args ),
                            self::_breakdown_units_label( (int) $hours, 'hour' ),
                            $children_eff
                        ),
                    )
                );
            } else {
                $lines[] = self::_breakdown_line(
                    'accommodation',
                    __( 'Hourly rate', 'listeo_core' ),
                    $accommodation,
                    $currency_args,
                    array(
                        'sublabel' => sprintf(
                            __( '%1$s × %2$s × %3$d guests', 'listeo_core' ),
                            self::_breakdown_format( $hourly_rate, $currency_args ),
                            self::_breakdown_units_label( (int) $hours, 'hour' ),
                            $adults_count
                        ),
                    )
                );
            }
        } else {
            $lines[] = self::_breakdown_line(
                'accommodation',
                __( 'Hourly rate', 'listeo_core' ),
                $accommodation,
                $currency_args,
                array(
                    'sublabel' => sprintf(
                        __( '%1$s × %2$s', 'listeo_core' ),
                        self::_breakdown_format( $hourly_rate, $currency_args ),
                        self::_breakdown_units_label( (int) $hours, 'hour' )
                    ),
                )
            );
        }

        if ( $reservation_price > 0 ) {
            $lines[] = self::_breakdown_line(
                'reservation_fee',
                __( 'Reservation fee', 'listeo_core' ),
                $reservation_price,
                $currency_args,
                array( 'sublabel' => __( 'One-time charge', 'listeo_core' ) )
            );
        }

        // Fees (against accommodation subtotal)
        $fee_context = array(
            'hours'        => max( 1, (int) $hours ),
            'guests'       => max( 1, $adults_count + $children_eff ),
            'nights'       => 1,
            'subtotal'     => (float) $accommodation,
            'date_start'   => $date_start,
            'listing_type' => $listing_type,
        );
        $fees_sum = 0.0;
        if ( function_exists( 'listeo_get_applicable_listing_fees' ) ) {
            foreach ( listeo_get_applicable_listing_fees( $listing_id, $fee_context ) as $fee ) {
                $line     = listeo_format_fee_line( $fee, $fee_context, $currency_args );
                $sublabel = '';
                if ( isset( $line['label'] ) && isset( $line['title'] ) && $line['label'] !== $line['title'] ) {
                    $sublabel = trim( str_replace( $line['title'], '', $line['label'] ) );
                    $sublabel = trim( $sublabel, ' ()' );
                }
                $lines[]  = self::_breakdown_line(
                    'mandatory_fee',
                    isset( $line['title'] ) ? $line['title'] : $line['label'],
                    $line['amount'],
                    $currency_args,
                    array(
                        'fee_id'   => $fee['id'],
                        'sublabel' => $sublabel ?: null,
                    )
                );
                $fees_sum += (float) $line['amount'];
            }
        }

        // Services
        $services_sum = 0.0;
        if ( ! empty( $services ) && is_array( $services ) ) {
            $bookable_services = listeo_get_bookable_services( $listing_id );
            $countable         = array_column( $services, 'value' );
            $i = 0;
            foreach ( $bookable_services as $service ) {
                if ( in_array( sanitize_title( $service['name'] ), array_column( $services, 'service' ), true ) ) {
                    $qty    = isset( $countable[ $i ] ) ? (int) $countable[ $i ] : 1;
                    $amount = (float) listeo_calculate_service_price( $service, $multiply, $children, $children_discount, $hours, $qty );
                    $lines[] = self::_breakdown_line(
                        'service',
                        $service['name'],
                        $amount,
                        $currency_args,
                        array(
                            'service_slug' => sanitize_title( $service['name'] ),
                            'sublabel'     => $qty > 1 ? '×' . $qty : null,
                        )
                    );
                    $services_sum += $amount;
                    $i++;
                }
            }
        }

        // Pet fees — `calculate_price_by_hours` uses `$hours` as the
        // multiplier when `per_night` is set, even though that's a
        // misnomer; we preserve the exact math.
        $pet_amount = 0.0;
        if ( ! empty( $animals ) && ! empty( $animal_fee ) ) {
            if ( 'per_night' === $animal_fee_type ) {
                $pet_amount = $animal_fee * (int) $hours * (int) $animals;
                $pet_sub    = sprintf(
                    __( '%1$s × %2$s × %3$d pets', 'listeo_core' ),
                    self::_breakdown_format( $animal_fee, $currency_args ),
                    self::_breakdown_units_label( (int) $hours, 'hour' ),
                    (int) $animals
                );
            } else {
                $pet_amount = $animal_fee * (int) $animals;
                $pet_sub    = sprintf(
                    __( '%1$s × %2$d pets', 'listeo_core' ),
                    self::_breakdown_format( $animal_fee, $currency_args ),
                    (int) $animals
                );
            }
            $lines[] = self::_breakdown_line(
                'animal_fee',
                __( 'Pet fee', 'listeo_core' ),
                $pet_amount,
                $currency_args,
                array( 'sublabel' => $pet_sub )
            );
        }

        $pre_coupon_total = $accommodation + $reservation_price + $fees_sum + $services_sum + $pet_amount;
        $subtotal         = $pre_coupon_total;

        $coupon_line = self::_breakdown_apply_coupon( $coupon, $pre_coupon_total, $currency_args );
        $total       = $pre_coupon_total + ( $coupon_line ? (float) $coupon_line['amount'] : 0.0 );

        $filtered = (float) apply_filters( 'listeo_booking_price_calc', $total, $listing_id, $date_start, $date_end, $multiply, $services );
        self::_breakdown_reconcile( $lines, $subtotal, $coupon_line, $pre_coupon_total, $filtered, $currency_args );

        return apply_filters( 'listeo_booking_price_breakdown', array(
            'booking_type'      => 'hours',
            'units'             => (int) $hours,
            'units_label'       => self::_breakdown_units_label( (int) $hours, 'hour' ),
            'guests'            => $adults_count + $children_eff,
            'children'          => $children_eff,
            'lines'             => $lines,
            'subtotal'          => (float) $subtotal,
            'subtotal_formatted'=> self::_breakdown_format( $subtotal, $currency_args ),
            'coupon'            => $coupon_line,
            'total'             => (float) $filtered,
            'total_formatted'   => self::_breakdown_format( $filtered, $currency_args ),
            'currency_symbol'   => $currency_args['symbol'],
            'currency_position' => $currency_args['position'],
            'decimals'          => $currency_args['decimals'],
        ), $listing_id, $date_start, $date_end, $multiply, $services, $coupon );
    }

    /**
     * Per-hour breakdown — mirrors `calculate_price_per_hour()`. Multi-day
     * hourly bookings sum each day's hourly rate × duration.
     */
    public static function calculate_price_per_hour_breakdown( $listing_id, $date_start, $date_end, $start_hour, $end_hour, $multiply = 1, $children = false, $animals = false, $services = false, $coupon = false ) {
        $currency_args = self::_breakdown_currency_args();
        $listing_type  = get_post_meta( $listing_id, '_listing_type', true );

        $normal_price      = (float) get_post_meta( $listing_id, '_normal_price', true );
        $weekend_price     = (float) get_post_meta( $listing_id, '_weekday_price', true );
        if ( empty( $weekend_price ) ) {
            $weekend_price = $normal_price;
        }
        $reservation_price = (float) get_post_meta( $listing_id, '_reservation_price', true );
        $_count_per_guest  = get_post_meta( $listing_id, '_count_per_guest', true );
        $children_discount = (float) get_post_meta( $listing_id, '_children_price', true );
        $animal_fee        = (float) get_post_meta( $listing_id, '_animal_fee', true );
        $animal_fee_type   = get_post_meta( $listing_id, '_animal_fee_type', true );

        $time1 = strtotime( $start_hour );
        $time2 = strtotime( $end_hour );
        if ( $time2 <= $time1 ) {
            $time2 += 24 * 60 * 60;
        }
        $difference = (int) ( ( $time2 - $time1 ) / 3600 );

        $special_prices = array();
        foreach ( self::get_bookings( $date_start, $date_end, array( 'listing_id' => $listing_id, 'type' => 'special_price' ) ) as $sp ) {
            $special_prices[ $sp['date_start'] ] = (float) $sp['comment'];
        }

        $firstDay = new DateTime( $date_start );
        $lastDay  = new DateTime( $date_end . '23:59:59' );
        $days_count = max( 1, (int) $lastDay->diff( $firstDay )->format( '%a' ) );

        $period = new DatePeriod( $firstDay, DateInterval::createFromDateString( '1 day' ), $lastDay );
        $base_accom     = 0.0;
        $observed_rates = array();
        $start_of_week  = (int) get_option( 'start_of_week' );
        foreach ( $period as $current_day ) {
            $day_key = $current_day->format( 'Y-m-d 00:00:00' );
            $dow     = (int) $current_day->format( 'N' );
            if ( isset( $special_prices[ $day_key ] ) ) {
                $rate = (float) $special_prices[ $day_key ];
            } else {
                if ( 0 === $start_of_week ) {
                    $rate = ( $dow === 5 || $dow === 6 ) ? $weekend_price : $normal_price;
                } else {
                    $rate = ( $dow === 6 || $dow === 7 ) ? $weekend_price : $normal_price;
                }
            }
            $base_accom    += $rate * $difference;
            $observed_rates[ (string) $rate ] = true;
        }

        $accommodation = $base_accom;
        $adults_count  = (int) $multiply;
        $children_eff  = is_numeric( $children ) ? (int) $children : 0;
        $child_rate    = 0.0;
        $lines         = array();

        // Labels depend on booking type:
        //   - service / single_day → "Service rate", no "× N hours"
        //     in the sublabel since the rate is per booking, not per
        //     hour (the function gets called because the user picked
        //     start/end times, but `_count_by_hour` being off means
        //     hours don't multiply the price).
        //   - rental hourly / count_by_hour on → "Hourly rate" with
        //     "× N hours" (× N days when multi-day, single line when
        //     same day).
        $booking_type = listeo_get_booking_type( $listing_id );
        $count_by_hour = (bool) get_post_meta( $listing_id, '_count_by_hour', true );
        $is_hourly_priced = $count_by_hour;
        $accom_label = $is_hourly_priced ? __( 'Hourly rate', 'listeo_core' ) : __( 'Service rate', 'listeo_core' );

        // Build the math segment shown in the sublabel (the bit that
        // describes *what the rate is multiplied by*). Empty when the
        // price doesn't scale by hours / days.
        $math_segment = '';
        if ( $is_hourly_priced ) {
            $math_segment = ' × ' . self::_breakdown_units_label( $difference, 'hour' );
            if ( $days_count > 1 ) {
                $math_segment .= ' × ' . self::_breakdown_units_label( $days_count, 'night' );
            }
        }

        if ( $_count_per_guest ) {
            $adults_count = isset( $_POST['adults'] )   ? (int) $_POST['adults']   : (int) $multiply;
            $children_eff = isset( $_POST['children'] ) ? (int) $_POST['children'] : ( is_numeric( $children ) ? (int) $children : 0 );
            $adults_part  = $base_accom * max( 1, $adults_count );
            $children_part = 0.0;
            if ( $children_eff > 0 && $children_discount > 0 ) {
                $child_rate    = $base_accom * ( 1 - ( $children_discount / 100 ) );
                $children_part = $child_rate * $children_eff;
            }
            $accommodation = $adults_part + $children_part;
            // Per-unit rate for the sublabel — divide out the multipliers we'll display.
            $effective_rate = $is_hourly_priced
                ? $base_accom / max( 1, $difference ) / max( 1, $days_count )
                : ( count( $observed_rates ) === 1 ? (float) array_keys( $observed_rates )[0] : $base_accom );
            $lines[] = self::_breakdown_line(
                'accommodation',
                $accom_label,
                $adults_part,
                $currency_args,
                array(
                    'sublabel' => sprintf(
                        /* translators: 1: per-unit rate, 2: optional " × N hours [× N nights]", 3: guest count */
                        __( '%1$s%2$s × %3$d guests', 'listeo_core' ),
                        self::_breakdown_format( $effective_rate, $currency_args ),
                        $math_segment,
                        $adults_count
                    ),
                )
            );
            if ( $children_eff > 0 && $children_discount > 0 ) {
                $lines[] = self::_breakdown_line(
                    'accommodation_children',
                    __( 'Child rate', 'listeo_core' ),
                    $children_part,
                    $currency_args,
                    array(
                        'sublabel' => sprintf(
                            /* translators: %d: child count */
                            __( '× %d children', 'listeo_core' ),
                            $children_eff
                        ),
                    )
                );
            }
        } else {
            // No per-guest multiplier. Single accommodation line.
            $sublabel = null;
            if ( count( $observed_rates ) === 1 ) {
                $rate = (float) array_keys( $observed_rates )[0];
                $sublabel = self::_breakdown_format( $rate, $currency_args ) . $math_segment;
            } elseif ( $is_hourly_priced ) {
                // Mixed rates across days — drop the per-unit rate
                // since there isn't a single one to show.
                $sublabel = ltrim( $math_segment, ' ×' );
                $sublabel = trim( $sublabel );
            }
            $lines[] = self::_breakdown_line(
                'accommodation',
                $accom_label,
                $accommodation,
                $currency_args,
                array( 'sublabel' => $sublabel )
            );
        }

        if ( $reservation_price > 0 ) {
            $lines[] = self::_breakdown_line(
                'reservation_fee',
                __( 'Reservation fee', 'listeo_core' ),
                $reservation_price,
                $currency_args,
                array( 'sublabel' => __( 'One-time charge', 'listeo_core' ) )
            );
        }

        $fee_context = array(
            'hours'        => max( 1, $difference ),
            'nights'       => max( 1, $days_count ),
            'guests'       => max( 1, $adults_count + $children_eff ),
            'subtotal'     => (float) $accommodation,
            'date_start'   => $date_start,
            'listing_type' => $listing_type,
        );
        $fees_sum = 0.0;
        if ( function_exists( 'listeo_get_applicable_listing_fees' ) ) {
            foreach ( listeo_get_applicable_listing_fees( $listing_id, $fee_context ) as $fee ) {
                $line     = listeo_format_fee_line( $fee, $fee_context, $currency_args );
                $sublabel = '';
                if ( isset( $line['label'] ) && isset( $line['title'] ) && $line['label'] !== $line['title'] ) {
                    $sublabel = trim( str_replace( $line['title'], '', $line['label'] ) );
                    $sublabel = trim( $sublabel, ' ()' );
                }
                $lines[]  = self::_breakdown_line(
                    'mandatory_fee',
                    isset( $line['title'] ) ? $line['title'] : $line['label'],
                    $line['amount'],
                    $currency_args,
                    array(
                        'fee_id'   => $fee['id'],
                        'sublabel' => $sublabel ?: null,
                    )
                );
                $fees_sum += (float) $line['amount'];
            }
        }

        $services_sum = 0.0;
        if ( ! empty( $services ) && is_array( $services ) ) {
            $bookable_services = listeo_get_bookable_services( $listing_id );
            $countable         = array_column( $services, 'value' );
            $i = 0;
            foreach ( $bookable_services as $service ) {
                if ( in_array( sanitize_title( $service['name'] ), array_column( $services, 'service' ), true ) ) {
                    $qty    = isset( $countable[ $i ] ) ? (int) $countable[ $i ] : 1;
                    $amount = (float) listeo_calculate_service_price( $service, $multiply, $children, $children_discount, $days_count, $qty );
                    $lines[] = self::_breakdown_line(
                        'service',
                        $service['name'],
                        $amount,
                        $currency_args,
                        array(
                            'service_slug' => sanitize_title( $service['name'] ),
                            'sublabel'     => $qty > 1 ? '×' . $qty : null,
                        )
                    );
                    $services_sum += $amount;
                    $i++;
                }
            }
        }

        $pet_amount = 0.0;
        if ( ! empty( $animals ) && ! empty( $animal_fee ) ) {
            if ( 'per_night' === $animal_fee_type ) {
                $pet_amount = $animal_fee * $difference * (int) $animals;
                $pet_sub    = sprintf(
                    __( '%1$s × %2$s × %3$d pets', 'listeo_core' ),
                    self::_breakdown_format( $animal_fee, $currency_args ),
                    self::_breakdown_units_label( $difference, 'hour' ),
                    (int) $animals
                );
            } else {
                $pet_amount = $animal_fee * (int) $animals;
                $pet_sub    = sprintf(
                    __( '%1$s × %2$d pets', 'listeo_core' ),
                    self::_breakdown_format( $animal_fee, $currency_args ),
                    (int) $animals
                );
            }
            $lines[] = self::_breakdown_line(
                'animal_fee',
                __( 'Pet fee', 'listeo_core' ),
                $pet_amount,
                $currency_args,
                array( 'sublabel' => $pet_sub )
            );
        }

        $pre_coupon_total = $accommodation + $reservation_price + $fees_sum + $services_sum + $pet_amount;
        $subtotal         = $pre_coupon_total;

        $coupon_line = self::_breakdown_apply_coupon( $coupon, $pre_coupon_total, $currency_args );
        $total       = $pre_coupon_total + ( $coupon_line ? (float) $coupon_line['amount'] : 0.0 );

        $filtered = (float) apply_filters( 'listeo_booking_price_calc', $total, $listing_id, $date_start, $date_end, $multiply, $services );
        self::_breakdown_reconcile( $lines, $subtotal, $coupon_line, $pre_coupon_total, $filtered, $currency_args );

        return apply_filters( 'listeo_booking_price_breakdown', array(
            'booking_type'      => 'per_hour',
            'units'             => $difference,
            'units_label'       => self::_breakdown_units_label( $difference, 'hour' ),
            'guests'            => $adults_count + $children_eff,
            'children'          => $children_eff,
            'lines'             => $lines,
            'subtotal'          => (float) $subtotal,
            'subtotal_formatted'=> self::_breakdown_format( $subtotal, $currency_args ),
            'coupon'            => $coupon_line,
            'total'             => (float) $filtered,
            'total_formatted'   => self::_breakdown_format( $filtered, $currency_args ),
            'currency_symbol'   => $currency_args['symbol'],
            'currency_position' => $currency_args['position'],
            'decimals'          => $currency_args['decimals'],
        ), $listing_id, $date_start, $date_end, $multiply, $services, $coupon );
    }

    /**
    * Get all reservation of one listing
    *
    * @param  number $listing_id post id of current listing
    * @param  array $dates 
    *
    */
    public static function get_reservations( $listing_id, $dates ) {

        // delecting old reservations
        self :: delete_bookings ( array(
            'listing_id' => $listing_id,  
            'owner_id' => get_current_user_id(),
            'type' => 'reservation') );

        // update by new one reservations
        foreach ( $dates as $date) {

            self :: insert_booking( array(
                'listing_id' => $listing_id,  
                'type' => 'reservation',
                'owner_id' => get_current_user_id(),
                'date_start' => $date,
                'date_end' => $date,
                'comment' =>  'owner reservations',
                'order_id' => NULL,
                'status' => NULL
            ));

        }

    }

    public static function get_slots_from_meta( $listing_id ) {

        $_slots = get_post_meta( $listing_id, '_slots', true );

        if (!is_string($_slots)) {
            return false;
        }

        if (get_option('listeo_skip_hyphen_check')){
            $_slots = json_decode($_slots);
            return $_slots;
        }

        // Check for hyphen, en dash, or em dash
        $containsHyphen = strpos($_slots, '-') !== false;
        $containsEnDash = strpos($_slots, '–') !== false; // en dash
        $containsEmDash = strpos($_slots, '—') !== false; // em dash

        // When we don't have any type of dash
        if (!$containsHyphen && !$containsEnDash && !$containsEmDash) return false;
        // when we have slots
        $_slots = json_decode( $_slots );
        return $_slots;
    }

    /**
     * User booking shortcode
    * 
    * 
     */
    public  function listeo_core_booking( ) {
        
        ob_start();
        if(!isset($_POST['value'])){
            esc_html_e("You shouldn't be here :)",'listeo_core');
            return ob_get_clean();
        }
        $template_loader = new Listeo_Core_Template_Loader;
        
        // here we adding booking into database
        if ( isset($_POST['confirmed']) )
        {

            $new_user_with_booking = false;
            if (!is_user_logged_in()) :
                $email_required = true;
                $booking_without_login = get_option('listeo_booking_without_login', 'off');
                
                if($booking_without_login){
                    $email = $_POST['email'];

                    $registration_errors = array();
                    if (!get_option('users_can_register')) {
                        // Registration closed, display error
                        $registration_errors[] = "registration_closed";
                    }
                    if (get_option('listeo_registration_hide_username')) {
                        $email_arr = explode('@', $email);
                        $user_login = sanitize_user(trim($email_arr[0]), true);
                    } else {
                        $user_login = sanitize_user(trim($_POST['username']));
                    }
                    $role =  (isset($_POST['user_role'])) ? sanitize_text_field($_POST['user_role']) : get_option('default_role');
                    //$role = sanitize_text_field($_POST['role']);
                    if (!in_array($role, array('owner', 'guest', 'seller'))) {
                        $role = get_option('default_role');
                    }
                    $password = (!empty($_POST['password'])) ? sanitize_text_field($_POST['password']) : false;
                    $first_name = (isset($_POST['firstname'])) ? sanitize_text_field($_POST['firstname']) : '';
                    $last_name = (isset($_POST['lastname'])) ? sanitize_text_field($_POST['lastname']) : '';
                    $privacy_policy_status = get_option('listeo_privacy_policy');

                    $privacy_policy_pass = true;
                    if ($privacy_policy_status) {
                        $privacy_policy_pass = false;
                        if (isset($_POST['privacy_policy']) && !empty($_POST['privacy_policy'])) :
                            $privacy_policy_pass = true;
                        else :
                            $registration_errors[] = "policy-fail";

                        endif;
                    }


                    $terms_and_conditions_status =  get_option('listeo_terms_and_conditions_req');
                    $terms_and_conditions_pass = true;
                    if ($terms_and_conditions_status) {
                        $terms_and_conditions_pass = false;
                        if (isset($_POST['terms_and_conditions']) && !empty($_POST['terms_and_conditions'])) :
                            $terms_and_conditions_pass = true;
                        else :
                            $registration_errors[] = "terms-fail";

                        endif;
                    }


                    $recaptcha_status = get_option('listeo_recaptcha');
                    $recaptcha_version = get_option('listeo_recaptcha_version');
	    
                   
                    if ($recaptcha_status) {

                        if ($recaptcha_status && $recaptcha_version == "v2") {
                            if ($recaptcha_version == "v2" && isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) :
                                $secret = get_option('listeo_recaptcha_secretkey');
                                //get verify response data

                                $verifyResponse = wp_remote_get('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret . '&response=' . $_POST['g-recaptcha-response']);
                                $responseData = json_decode($verifyResponse['body']);
                                if ($responseData->success) :
                                    //passed captcha, proceed to register
                                
                                else :
                                    $registration_errors[] = 'captcha-fail';
                                endif;
                            else :
                                $registration_errors[] = 'captcha-no';
                            endif;
                        }


                        if ($recaptcha_status && $recaptcha_version == "v3") {
                            if ($recaptcha_version == "v3" && isset($_POST['token']) && !empty($_POST['token'])) :
                                //your site secret key
                                $secret = get_option('listeo_recaptcha_secretkey3');
                                //get verify response data
                                $verifyResponse = wp_remote_get('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret . '&response=' . $_POST['token']);
                                $responseData_w = wp_remote_retrieve_body($verifyResponse);
                                $responseData = json_decode($responseData_w);

                                if ($responseData->success == '1' && $responseData->action == 'login' && $responseData->score >= 0.5) :
                                    //passed captcha, proceed to register
                                    
                                else :
                                    $registration_errors[] = 'captcha-fail';
                                endif;
                            else :
                                $registration_errors[] = 'captcha-no';
                            endif;
                        }

                        if ($recaptcha_version == "hcaptcha") {
                            if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) :
                                $secret = get_option('listeo_hcaptcha_secretkey');
                                //get verify response data
                                $verifyResponse = wp_remote_post('https://hcaptcha.com/siteverify', array(
                                    'body' => array(
                                        'secret' => $secret,
                                        'response' => $_POST['h-captcha-response']
                                    )
                                ));
                                $responseData = json_decode(wp_remote_retrieve_body($verifyResponse));
                                if ($responseData->success) :
                                //passed captcha, proceed to register

                                else :
                                    $registration_errors[] = 'captcha-fail';
                                endif;
                            else :
                                $registration_errors[] = 'captcha-no';
                            endif;
                        }

                        if ($recaptcha_version == "turnstile") {
                            if (isset($_POST['cf-turnstile-response']) && !empty($_POST['cf-turnstile-response'])) :
                                $secret = get_option('listeo_turnstile_secretkey');
                                //get verify response data
                                $verifyResponse = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                                    'body' => array(
                                        'secret' => $secret,
                                        'response' => $_POST['cf-turnstile-response']
                                    )
                                ));
                                $responseData = json_decode(wp_remote_retrieve_body($verifyResponse));
                                if ($responseData->success) :
                                //passed captcha, proceed to register

                                else :
                                    $registration_errors[] = 'captcha-fail';
                                endif;
                            else :
                                $registration_errors[] = 'captcha-no';
                            endif;
                        }
                       
                    }

                    $custom_registration_fields = array();
                    // if all above ok, we can register user
                    if(empty($registration_errors)){
                        $user_class = new Listeo_Core_Users;
                        $phone = false;
                        $_user_id = $user_class->register_user($email, $user_login, $first_name, $last_name, $role, $phone, $password, $custom_registration_fields);
                        if (!is_wp_error($_user_id)) {
                            
                            $new_user_with_booking = true;
                        } else {

                            $registration_errors[] = $_user_id->get_error_code();
                            $data = json_decode(wp_unslash(htmlspecialchars_decode(wp_unslash($_POST['value']))), true);
                            
                            $this->booking_confirmation_form($data, $registration_errors);
                            return;
                        }
                    } else {
                        $data = json_decode(wp_unslash(htmlspecialchars_decode(wp_unslash($_POST['value']))), true);
                        
                        $this->booking_confirmation_form($data, $registration_errors);
                        return;
                    }
                    
                }
                
              //  $template_loader->set_template_data($data)->get_template_part('booking'); 
                // we have to register new user
                //what about recatpcha
                // check all required data, create user, and set the login further
                //if data is wrong or user exist, redirect back and show error
            
            endif;
         
           // $data = json_decode(wp_unslash(htmlspecialchars_decode(wp_unslash($_POST['value']))), true);

            if(is_user_logged_in()){
                $_user_id = get_current_user_id();
            }
///?
            $data = json_decode(wp_unslash($_POST['value']), true);
            $error = false;
            $listing_id = $data['listing_id'];
            $listing_type =  get_post_meta ( $data['listing_id'], '_listing_type', true );
            
            $services = (isset($data['services'])) ? $data['services'] : false ;
            $comment_services = false;


            if(!empty($services)){
                $currency_abbr = get_option( 'listeo_currency' );
                $currency_postion = get_option( 'listeo_currency_postion' );
                $currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
                //$comment_services = '<ul>';
                $comment_services = array();
                $bookable_services = listeo_get_bookable_services( $data['listing_id'] );

                if (listeo_core_listing_type_supports($listing_type, 'date_range')) {
                    $_rental_timepicker = get_post_meta( $data['listing_id'], '_rental_timepicker', true );
                    $firstDay = new DateTime( $data['date_start'] );
                    if($_rental_timepicker){
                        $lastDay = new DateTime($data['date_end']);
                    } else {
                        $lastDay = new DateTime( $data['date_end'] . '23:59:59') ;
                    }
                    $days_between = $lastDay->diff($firstDay)->format("%a");
                    $days_count = ($days_between == 0) ? 1 : $days_between ;
                    
                } else {
                    
                    $days_count = 1;
                
                }
                
                //since 1.3 change comment_service to json
                $countable = array_column($services,'value');
                if(isset($data['adults'])){
                    $guests = $data['adults'];
                } else if(isset($data['tickets'])){
                    $guests = $data['tickets'];
                } else {
                    $guests = 1;
                }
                if(isset($data['children'])){
                    $children = $data['children'];
                } else {
                    $children = 0;
                }
            
                $children_discount = get_post_meta( $data['listing_id'], '_children_discount', true );
          
                $i = 0;
                foreach ($bookable_services as $key => $service) {
                    
                    if(in_array(sanitize_title($service['name']),array_column($services,'service'))) { 
                     
                   
                        $comment_services[] =  array(
                            'service' => $service, 
                            'guests' => $guests, 
                            'days' => $days_count, 
                            'countable' =>  $countable[$i],
                            'price' => listeo_calculate_service_price($service, $guests, $children, $children_discount, $days_count, $countable[$i] ) 
                        );
                        
                       $i++;
                    
                    }
                   
                
                }                  
            } //eof if services

            $listing_meta = get_post_meta ( $data['listing_id'], '', true );
            // detect if website was refreshed
            $instant_booking = get_post_meta(  $data['listing_id'], '_instant_booking', true );
            $payment_option = get_post_meta(  $data['listing_id'], '_payment_option', true );
            
            if(get_option('listeo_block_bookings_period')){
                if ( get_transient('listeo_last_booking'.$_user_id) == $data['listing_id'] . ' ' . $data['date_start']. ' ' . $data['date_end'] )
                {
                 
                
                    $template_loader->set_template_data( 
                        array( 
                            'error' => true,
                            'message' => __('Sorry, it looks like you\'ve already made that reservation', 'listeo_core')
                        ) )->get_template_part( 'booking-success' ); 
                    
                    return;
                }
                set_transient('listeo_last_booking' . $_user_id, $data['listing_id'] . ' ' . $data['date_start'] . ' ' . $data['date_end'], 60 * 15);
            }

            
            
            // because we have to be sure about listing type
            $listing_meta = get_post_meta ( $data['listing_id'], '', true );

            $listing_owner = get_post_field( 'post_author', $data['listing_id'] );

            $billing_address_1 = (isset($_POST['billing_address_1'])) ? sanitize_text_field($_POST['billing_address_1']) : false ;
            $billing_postcode = (isset($_POST['billing_postcode'])) ? sanitize_text_field($_POST['billing_postcode']) : false ;
            $billing_city = (isset($_POST['billing_city'])) ? sanitize_text_field($_POST['billing_city']) : false ;
            $billing_country = (isset($_POST['billing_country'])) ? sanitize_text_field($_POST['billing_country']) : false ;
            $billing_state = (isset($_POST['billing_state'])) ? sanitize_text_field($_POST['billing_state']) : false ;
            $coupon = (isset($_POST['coupon_code'])) ? sanitize_text_field($_POST['coupon_code']) : false ;


            // Get custom listing type slug for booking fields
            if (function_exists('listeo_core_custom_listing_types')) {
                $custom_types = listeo_core_custom_listing_types();
                $type_obj = $custom_types->get_listing_type_by_slug($listing_type);
                $type_slug = $type_obj ? $type_obj->slug : $listing_type;
            } else {
                $type_slug = $listing_type;
            }
            $fields = get_option("listeo_{$type_slug}_booking_fields");


            $custom_booking_fields = array();

            if (!empty($fields)) {
                //get fields for booking

                foreach ($fields as $key => $field) {

                    // Use $field['id'] for POST lookup to match the form input name,
                    // since the array key is sanitize_title'd but the form uses the original ID
                    $post_key = isset($field['id']) ? $field['id'] : $key;

                    $field_type = str_replace('-', '_', $field['type']);

                    if (
                        $handler = apply_filters("listeo_core_get_posted_{$field_type}_field", false)
                    ) {

                        $value = call_user_func($handler, $post_key, $field);
                    } elseif (method_exists('Listeo_Core_Bookings_Calendar', "get_posted_{$field_type}_field")) {

                        $value = call_user_func(array('Listeo_Core_Bookings_Calendar', "get_posted_{$field_type}_field"), $post_key, $field);
                    } else {

                        $value = (new Listeo_Core_Bookings_Calendar())->get_posted_field($post_key, $field);
                    }

                    // Set fields value

                    $field['value'] = $value;

                    $custom_booking_fields[] = $field;
                 
                  
                }
            }
		

            switch (listeo_get_booking_type( $data['listing_id'] )) 
            {
                case 'tickets' :
                case 'event' :
                    /**
                     * Filter to override event ticket price calculation.
                     * Booking Plus returns a sum across selected multi-tier ticket types.
                     *
                     * @param float|null $price    Override price, or null to use legacy calculation.
                     * @param array      $data     Booking data.
                     * @param array      $services Selected services.
                     * @param string     $coupon   Coupon code.
                     */
                    $bp_price = apply_filters( 'listeo_event_booking_price', null, $data, $services, $coupon );
                    $bp_price_before = apply_filters( 'listeo_event_booking_price', null, $data, $services, '' );

                    if ( $bp_price !== null ) {
                        $price = (float) $bp_price;
                        $price_before_coupons = (float) $bp_price_before;
                    } else {
                    $price = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], $data['tickets'], 1, 1, $services, $coupon );
                    $price_before_coupons = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], $data['tickets'], 1, 1, $services, '' );
                    }

                    // Add commission to price if calculation method is 'add'
                    $commission_settings = self::get_commission_settings($listing_owner);
                    if ($commission_settings['calculation_method'] == 'add') {
                        $commission_amount = self::calculate_commission_amount($price, $commission_settings['commission_type'], $commission_settings['commission_value']);
                        $price = $price + $commission_amount;

                        $commission_amount_before_coupon = self::calculate_commission_amount($price_before_coupons, $commission_settings['commission_type'], $commission_settings['commission_value']);
                        $price_before_coupons = $price_before_coupons + $commission_amount_before_coupon;
                    }

                    $comment= array( 
                        'first_name'    => sanitize_text_field($_POST['firstname']),
                        'last_name'     => sanitize_text_field($_POST['lastname']),
                        'email'         => sanitize_email($_POST['email']),
                        'phone'         => sanitize_text_field($_POST['phone']),
                        'message'       => sanitize_textarea_field($_POST['message']),
                        'tickets'       => sanitize_text_field($data['tickets']),
                        'service'       => $comment_services,
                        'billing_address_1' => $billing_address_1,
                        'billing_postcode'  => $billing_postcode,
                        'billing_city'      => $billing_city,
                        'billing_country'   => $billing_country,
                        'billing_state'   => $billing_state,
                        'coupon'        => $coupon,
                        'price'         => $price_before_coupons
                    );

                    /**
                     * Filter to modify the booking comment array before it is JSON-encoded.
                     * Booking Plus uses this to inject the multi-tier `lbp_tickets` selection
                     * and the chosen `occurrence_id` for recurring events.
                     *
                     * @param array $comment Booking comment array.
                     * @param array $data    Booking data.
                     * @param array $post    Raw $_POST.
                     */
                    $comment = apply_filters( 'listeo_event_booking_comment', $comment, $data, $_POST );

                    $booking_id = self :: insert_booking ( array (
                        'bookings_author'      => $_user_id,
                        'owner_id'      => $listing_owner,
                        'listing_id'    => $data['listing_id'],
                        'date_start'    => $data['date_start'],
                        'date_end'      => $data['date_start'],
                        'comment'       =>  json_encode ( $comment ),
                        'type'          =>  'reservation',
                        'price'         => $price,
                    ));

                    /**
                     * Filter to short-circuit the legacy `_event_tickets_sold` meta increment.
                     * Booking Plus increments per-tier `qty_sold` and returns true to bypass the legacy update.
                     *
                     * @param bool  $handled    Default false.
                     * @param array $data       Booking data.
                     * @param int   $booking_id Newly inserted booking ID.
                     */
                    $bp_handled = apply_filters( 'listeo_event_tickets_sold_update', false, $data, $booking_id );
                    if ( ! $bp_handled ) {
                    $already_sold_tickets = (int) get_post_meta($data['listing_id'],'_event_tickets_sold',true);
                    $sold_now = $already_sold_tickets + $data['tickets'];
                    update_post_meta($data['listing_id'],'_event_tickets_sold',$sold_now);
                    }

                    $status = apply_filters( 'listeo_event_default_status', 'waiting');
                    if($instant_booking == 'check_on' || $instant_booking == 'on' ) {
                        $status = 'confirmed';
                        if(get_option('listeo_instant_booking_require_payment') && $price > 0 ){
                            $status = "pay_to_confirm";
                        }
                    }
                    
                    $changed_status = self :: set_booking_status ( $booking_id, $status );

                break;

                case 'date_range' :
                case 'rental' :

                    // get default status
                    $status = apply_filters( 'listeo_rental_default_status', 'waiting');

                    $booking_hours = self::wpk_change_booking_hours(  $data['date_start'], $data['date_end'] );
                    $date_start = $booking_hours[ 'date_start' ];
                    $date_end = $booking_hours[ 'date_end' ];

                    $multiply = 1;
                    $children_count = 0;
                    $animals_count = 0;
                    $infants_count = 0;

                    if (isset($data['adults'])) $multiply = $data['adults'];
                    if (isset($data['children'])) $children_count = $data['children'];
                    if (isset($data['animals'])) $animals_count = $data['animals'];
                    if (isset($data['infants'])) $infants_count = $data['infants'];

                    // SERVER-SIDE VALIDATION: Check if dates are blocked
                    $blocked_error = self::is_date_blocked($data['listing_id'], $data['date_start'], $data['date_end']);
                    if ($blocked_error !== false) {
                        $template_loader->set_template_data(
                            array(
                                'error' => true,
                                'message' => $blocked_error
                            )
                        )->get_template_part('booking-success');
                        return;
                    }

                    // count free places
                    if(apply_filters('listeo_allow_overbooking', false)) {
                        $free_places = 1;
                    } else {
                        $free_places = self :: count_free_places( $data['listing_id'], $data['date_start'], $data['date_end'] );
                    }
                    if ( $free_places > 0 ) 
                    {
                        $count_by_hour = get_post_meta($data['listing_id'], "_count_by_hour", true);
                       
                            $count_per_guest = get_post_meta($data['listing_id'], "_count_per_guest" , true );
                            $count_by_hour = get_post_meta($data['listing_id'], "_count_by_hour", true);
                        
                        //check count_per_guest


                        

                        if ($count_by_hour) {
                            $date_start = strtotime($data['date_start']);
                            $date_end = strtotime($data['date_end']);
                            $hours = ($date_end - $date_start) / 3600;

                            $price = self::calculate_price_by_hours($data['listing_id'],  $data['date_start'], $data['date_end'], $hours, $multiply,  $children_count, $animals_count, $services, $coupon);
                            $price_before_coupons = self::calculate_price_by_hours($data['listing_id'],  $data['date_start'], $data['date_end'], $hours, $multiply, $children_count, $animals_count, $services, '');
                        } else {

                            $price = self :: calculate_price( $data['listing_id'],  $data['date_start'], $data['date_end'], $multiply, $children_count, $animals_count, $services, $coupon   );
                            $price_before_coupons = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], $multiply, $children_count, $animals_count, $services, ''   );
                        }

                        // Add commission to price if calculation method is 'add'
                        $commission_settings = self::get_commission_settings($listing_owner);
                        if ($commission_settings['calculation_method'] == 'add') {
                            $commission_amount = self::calculate_commission_amount($price, $commission_settings['commission_type'], $commission_settings['commission_value']);
                            $price = $price + $commission_amount;

                            $commission_amount_before_coupon = self::calculate_commission_amount($price_before_coupons, $commission_settings['commission_type'], $commission_settings['commission_value']);
                            $price_before_coupons = $price_before_coupons + $commission_amount_before_coupon;
                        }

                        $booking_id = self :: insert_booking ( array (
                            'bookings_author'      => $_user_id,
                            'owner_id' => $listing_owner,
                            'listing_id' => $data['listing_id'],
                            'date_start' => $data['date_start'],
                            'date_end' => $data['date_end'],
                            'comment' =>  json_encode ( array( 
                                'first_name'    => sanitize_text_field($_POST['firstname']),
                                'last_name'     => sanitize_text_field($_POST['lastname']),
                                'email'         => sanitize_email($_POST['email']),
                                'phone'         => sanitize_text_field($_POST['phone']),
                                'message'       => sanitize_textarea_field($_POST['message']),
                                //'children' => $data['children'],
                                'adults'            => sanitize_text_field($data['adults']),
                                'children'          => sanitize_text_field($children_count),
                                'infants'           => sanitize_text_field($infants_count),
                                'animals'           => sanitize_text_field($animals_count),
                                'service'           => $comment_services,
                                'billing_address_1' => $billing_address_1,
                                'billing_postcode'  => $billing_postcode,
                                'billing_city'      => $billing_city,
                                'billing_country'   => $billing_country,
                                'billing_state'     => $billing_state,
                                'coupon'            => $coupon,
                                'price'             => $price_before_coupons,
                               // 'tickets' => $data['tickets']
                            )),
                            'type' =>  'reservation',
                            'price' => $price,
                        ));
    
                        $status = apply_filters( 'listeo_event_default_status', 'waiting');
                        if($instant_booking == 'check_on' || $instant_booking == 'on') { $status = 'confirmed'; 
                        if(get_option('listeo_instant_booking_require_payment') && $price > 0 ){
                            $status = "pay_to_confirm";
                        }}
                        $changed_status = self :: set_booking_status ( $booking_id, $status );
                        
                    } else
                    {

                        $error = true;
                        $message = __('Unfortunately those dates are not available anymore.', 'listeo_core');

                    }

                    break;

                case 'single_day' :
                case 'service' :

                    // SERVER-SIDE VALIDATION: Check if dates are blocked
                    $blocked_error = self::is_date_blocked($data['listing_id'], $data['date_start'], $data['date_end']);
                    if ($blocked_error !== false) {
                        $template_loader->set_template_data(
                            array(
                                'error' => true,
                                'message' => $blocked_error
                            )
                        )->get_template_part('booking-success');
                        return;
                    }

                    $status = apply_filters( 'listeo_service_default_status', 'waiting');
                    if($instant_booking == 'check_on' || $instant_booking == 'on') {
                        $status = 'confirmed';
                        if(get_option('listeo_instant_booking_require_payment') && $price > 0 ){
                            $status = "pay_to_confirm";
                        }
                    }
                    $multiply = 1;
                    $children_count = 0;
                    $animals_count = 0;
                    $infants_count = 0;

                    if (isset($data['children'])) $children_count = $data['children'];
                    if (isset($data['animals'])) $animals_count = $data['animals'];
                    if (isset($data['infants'])) $infants_count = $data['infants'];

                    // time picker booking
                    if ( ! isset( $data['slot'] ) ) 
                    {
                       
                        $count_per_guest = get_post_meta($data['listing_id'], "_count_per_guest" , true );
                        $count_by_hour = get_post_meta($data['listing_id'], "_count_by_hour" , true );
                        //check count_per_guest
                        $hour_start = (isset($data['_hour']) && !empty($data['_hour'])) ? $data['_hour'] : $data['_hour'];
                        $hour_end = (isset($data['_hour_end']) && !empty($data['_hour_end'])) ? $data['_hour_end'] : $data['_hour'];


                        if($count_per_guest){
                            if (isset($data['adults'])) $multiply = $data['adults'];
                           
                            $price = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], $multiply , $children_count, $animals_count, $services, $coupon  );
                            $price_before_coupons = self :: calculate_price( $data['listing_id'],  $data['date_start'], $data['date_end'], $multiply, $children_count, $animals_count, $services, ''   );
                            if ($count_by_hour) {

                                $price = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, $multiply,$children_count, $animals_count, $services, $coupon);
                                $price_before_coupons = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, $multiply,  $children_count, $animals_count,  $services, '');
                            }
                        } else {
                            $price = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'] ,1,1,1,  $services, $coupon );
                            $price_before_coupons = self :: calculate_price( $data['listing_id'],  $data['date_start'], $data['date_end'], 1,1,1, $services, ''   );
                            if ($count_by_hour) {

                                $price = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, 1, 1,1,$services, $coupon);
                                $price_before_coupons = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, 1, 1,1, $services, '');
                            }
                        }

                        // Add commission to price if calculation method is 'add'
                        $commission_settings = self::get_commission_settings($listing_owner);
                        if ($commission_settings['calculation_method'] == 'add') {
                            $commission_amount = self::calculate_commission_amount($price, $commission_settings['commission_type'], $commission_settings['commission_value']);
                            $price = $price + $commission_amount;

                            $commission_amount_before_coupon = self::calculate_commission_amount($price_before_coupons, $commission_settings['commission_type'], $commission_settings['commission_value']);
                            $price_before_coupons = $price_before_coupons + $commission_amount_before_coupon;
                        }

                        $booking_id = self :: insert_booking ( array (
                            'owner_id' => $listing_owner,
                            'bookings_author'      => $_user_id,
                            'listing_id' => $data['listing_id'],
                            'date_start' => $data['date_start'] . ' ' . $data['_hour'] . ':00',
                            'date_end' => $data['date_end'] . ' ' . $hour_end . ':00',
                            'comment' =>  json_encode ( array( 
                                'first_name'    => sanitize_text_field($_POST['firstname']),
                                'last_name'     => sanitize_text_field($_POST['lastname']),
                                'email'         => sanitize_email($_POST['email']),
                                'phone'         => sanitize_text_field($_POST['phone']),
                                'message'       => sanitize_text_field($_POST['message']),
                                'adults'        => sanitize_text_field($data['adults']),
                                'children'      => sanitize_text_field($children_count),
                                'animals'       => sanitize_text_field($animals_count),
                                'infants'       => sanitize_text_field($infants_count),
                                'message'       => sanitize_textarea_field($_POST['message']),
                                'service'       => $comment_services,
                                'billing_address_1' => $billing_address_1,
                                'billing_postcode'  => $billing_postcode,
                                'billing_state'  => $billing_state,
                                'billing_city'      => $billing_city,
                                'billing_country'   => $billing_country,
                                'coupon'   => $coupon,
                                'price'         => $price_before_coupons
                               
                            )),
                            'type' =>  'reservation',
                            'price' => $price,
                        ));
                        
                        $changed_status = self :: set_booking_status ( $booking_id, $status );

                    } else {

                        // here when we have enabled slots

                        $free_places = self :: count_free_places( $data['listing_id'], $data['date_start'], $data['date_end'], $data['slot'] );
                       
                        if ( $free_places > 0 ) 
                        {

                            $slot = json_decode( wp_unslash($data['slot']) );

                            $multiply = 1;
                            $children_count = 0;
                            $animals_count = 0;
                            $infants_count = 0;
                            if (isset($data['adults'])) $multiply = $data['adults'];
                            if (isset($data['children'])) $children_count = $data['children'];
                            if (isset($data['animals'])) $animals_count = $data['animals'];
                            if (isset($data['infants'])) $infants_count = $data['infants'];
                            // converent hours to mysql format
                            $hours = explode( ' - ', $slot[0] );
                            $hour_start = date( "H:i:s", strtotime( $hours[0] ) );
                            $hour_end = date( "H:i:s", strtotime( $hours[1] ) );

                            $count_per_guest = get_post_meta($data['listing_id'], "_count_per_guest" , true );
                            $count_by_hour = get_post_meta($data['listing_id'], "_count_by_hour" , true ); 
                            //check count_per_guest
                            $services = (isset($data['services'])) ? $data['services'] : false ;
                            
                            if($count_per_guest){


                                $price = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], $multiply, $children_count, $animals_count, $services, $coupon  );
                                $price_before_coupons = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], $multiply, $children_count, $animals_count,  $services, ''  );
                                if($count_by_hour){
                                    $price = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, $multiply,$children_count, $animals_count, $services, $coupon);
                                    $price_before_coupons = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, $multiply, $children_count, $animals_count, $services, '');
                                }
                            } else {
                                $price = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], 1, 1,1,$services,  $coupon );
                                $price_before_coupons = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], 1, 1, 1, $services, ''  );
                                if ($count_by_hour) {
                                    $price = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, 1, $children_count, $animals_count, $services, $coupon);
                                    $price_before_coupons = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, 1, $children_count, $animals_count, $services, '');
                                }
                            }

                            // Add commission to price if calculation method is 'add'
                            $commission_settings = self::get_commission_settings($listing_owner);
                            if ($commission_settings['calculation_method'] == 'add') {
                                $commission_amount = self::calculate_commission_amount($price, $commission_settings['commission_type'], $commission_settings['commission_value']);
                                $price = $price + $commission_amount;

                                $commission_amount_before_coupon = self::calculate_commission_amount($price_before_coupons, $commission_settings['commission_type'], $commission_settings['commission_value']);
                                $price_before_coupons = $price_before_coupons + $commission_amount_before_coupon;
                            }

                            $booking_id = self :: insert_booking ( array (
                                'owner_id' => $listing_owner,
                                'bookings_author'      => $_user_id,
                                'listing_id' => $data['listing_id'],
                                'date_start' => $data['date_start'] . ' ' . $hour_start,
                                'date_end' => $data['date_end'] . ' ' . $hour_end,
                                'comment' =>  json_encode ( array( 'first_name' => $_POST['firstname'],
                                    'last_name'     => sanitize_text_field($_POST['lastname']),
                                    'email'         => sanitize_email($_POST['email']),
                                    'phone'         => sanitize_text_field($_POST['phone']),
                                    'adults'        => sanitize_text_field($multiply),
                                    'children'      => sanitize_text_field($children_count),
                                    'infants'      => sanitize_text_field($infants_count),
                                    'animals'      => sanitize_text_field($animals_count),
                                    'message'       => sanitize_textarea_field($_POST['message']),
                                    'service'       => $comment_services,
                                    'billing_address_1' => $billing_address_1,
                                    'billing_postcode'  => $billing_postcode,
                                    'billing_state'  => $billing_state,
                                    'billing_city'      => $billing_city,
                                    'billing_country'   => $billing_country,
                                    'coupon'   => $coupon,
                                    'price'         => $price_before_coupons
                                   
                                )),
                                'type' =>  'reservation',
                                'price' => $price,
                            ));

      
                            $status = apply_filters( 'listeo_service_slots_default_status', 'waiting');
                            if($instant_booking == 'check_on' || $instant_booking == 'on') { $status = 'confirmed'; 
                         if(get_option('listeo_instant_booking_require_payment') && $price > 0 ){
                            $status = "pay_to_confirm";
                        }}
                            
                            $changed_status = self :: set_booking_status ( $booking_id, $status );

                        } else
                        {
    
                            $error = true;
                            $message = __('Those dates are not available.', 'listeo_core');
    
                        }

                    }
                    
                break;
            }
            
            $current_user_id = get_current_user_id();
            foreach ($custom_booking_fields as $field) {
                if(!empty($field['value'])){
                    add_booking_meta($booking_id, $field['id'], $field['value']);

                    // Persist value to user meta so the booking form pre-fills
                    // it on the next booking. Skip file uploads (the URL would
                    // point at a previous upload and confuse the file input).
                    if ( $current_user_id && ! empty( $field['id'] ) && ( ! isset( $field['type'] ) || $field['type'] !== 'file' ) ) {
                        update_user_meta(
                            $current_user_id,
                            'listeo_booking_field_' . sanitize_key( $field['id'] ),
                            $field['value']
                        );
                    }
                }

            }
            // when we have database problem with statuses
            if ( ! isset($changed_status) )
            {
                $message = __( 'We have some technical problem, please try again later or contact administrator.', 'listeo_core' );
                $error = true;
            }               
        
            switch ( $status )  {

                case 'waiting' :

                    $message = esc_html__( 'Your booking is waiting for confirmation.', 'listeo_core' );

                    break;

                case 'confirmed' :
                    if($price > 0){
                        switch ($payment_option) {
                            
                            case 'pay_cash':
                                $message = esc_html__('See you soon!', 'listeo_core');
                                break;
                            case 'pay_maybe':
                                $message = esc_html__('Pay now or in cash. See you soon!', 'listeo_core');
                                break;
                            
                            default:
                                $message = esc_html__('We are waiting for your payment.', 'listeo_core');
                                break;
                        }
                    
                    } else {
                        $message = '';
                    }
                    

                    break;

               

                case 'cancelled' :

                    $message = esc_html__( 'Your booking was cancelled', 'listeo_core' );

                    break;
            }



            
            
            if(isset($booking_id)){
                $booking_data =  self :: get_booking($booking_id);
                $order_id = $booking_data['order_id'];
                $order_id = (isset($booking_data['order_id'])) ? $booking_data['order_id'] : false ;
            }
            $template_loader->set_template_data( 
                array( 
                    'status' => $status,
                    'message' => (isset($message)) ? $message : 0,
                    'error' => $error,
                    'new_user_with_booking' => $new_user_with_booking,
                    'booking_id' => (isset($booking_id)) ? $booking_id : 0,
                    'order_id' => (isset($order_id)) ? $order_id : 0,
                    'listing_id' => (isset($listing_id)) ? $listing_id : 0,
                ) )->get_template_part( 'booking-success' ); 
            $content = ob_get_clean();
            return $content;
        } 

        // not confirmed yet

        $values = false;
        $this->booking_confirmation_form($values);
 
        // if slots are sended change them into good form
        if ( isset( $data['slot'] ) ) {

             // converent hours to mysql format
             $hours = explode( ' - ', $slot[0] );
             $hour_start = date( "H:i:s", strtotime( $hours[0] ) );
             $hour_end = date( "H:i:s", strtotime( $hours[1] ) );
 
             // add hours to dates
             $data['date_start'] .= ' ' . $hour_start;
             $data['date_end'] .= ' ' . $hour_end;
        

        } else if ( isset( $data['_hour'] ) ) {

            // when we dealing with normal hour from input we have to add second to make it real date format
            $hour_start = date( "H:i:s", strtotime( $hour ) );
            $data['date_start'] .= ' ' . $hour . ':00';
            $data['date_end'] .= ' ' . $hour . ':00';

        }

        // make temp reservation for short time
        //self :: save_temp_reservation( $data );

    }
    public function booking_confirmation_form($values, $registration_errors = null) {
       
        if(isset($values)&& !empty($values)){
            $data = $values;
        } else {
            $data = json_decode(wp_unslash($_POST['value']), true);
        }

        
        $template_loader = new Listeo_Core_Template_Loader;
        if(!$data){
            $template_loader->set_template_data(
                array(
                    'error' => true,
                    'message' => __('Please try again', 'listeo_core')
                )
            )->get_template_part('booking-success');

            return;
        
        }
        

        if (isset($registration_errors) && !empty($registration_errors)) {
            $data['registration_errors'] = $registration_errors;
         
        }
        if (isset($data['services'])) {
            $services =  $data['services'];
        } else {
            $services = false;
        }

        // for slots get hours
        if (isset($data['slot'])) {
            $slot = json_decode(wp_unslash($data['slot']));
            $hour = $slot[0];
        } else if (isset($data['_hour'])) {
            $hour = $data['_hour'];
            if (isset($data['_hour_end'])) {
                $hour_end = $data['_hour_end'];
            }
        } else {
            $hour = false;
            $hour_end = false;
        }

        if (isset($data['coupon']) && !empty($data['coupon'])) {
            $coupon = $data['coupon'];
        } else {
            $coupon = false;
        }


        // prepare some data to template
        $data['submitteddata'] = htmlspecialchars(stripslashes($_POST['value']));

        //check listin type
        $count_per_guest = get_post_meta($data['listing_id'], "_count_per_guest", true);
        //check count_per_guest

        //  if($count_per_guest || $data['listing_type'] == 'event' ){

        $multiply = 1;
        
        if (isset($data['adults'])) $multiply = $data['adults'];
        if (isset($data['tickets'])) $multiply = $data['tickets'];
        if (isset($data['children']))  { $children_count = $data['children']; } else { $children_count = 0; }
        if (isset($data['animals'])) { $animals_count = $data['animals']; } else { $animals_count = 0; }


        if (get_post_meta($data['listing_id'], '_count_by_hour', true)) {
            
            if (get_post_meta($data['listing_id'], '_rental_timepicker', true)) {
                
                $date_start = strtotime($data['date_start']);
                $date_end = strtotime($data['date_end']);
                $hours = ($date_end - $date_start) / 3600;
                
                $data['price'] = self::calculate_price_by_hours($data['listing_id'],  $data['date_start'], $data['date_end'], $hours, $multiply, $children_count, $animals_count, $services, '');
                
                if (!empty($coupon)) {
                    $data['price_sale'] = self::calculate_price_by_hours($data['listing_id'],  $data['date_start'], $data['date_end'], $hours, $multiply, $children_count, $animals_count, $services, $coupon);
                }
            } else {
             
                if (isset($data['slot'])) {
                    $hours = explode(' - ', $slot[0]);
					
                    $hour_start = date("H:i", strtotime($hours[0]));
                    $hour_end = date("H:i", strtotime($hours[1]));
					$hour = $hour_start;
                } else {
                    $hour_start = $hour;
                }
                $data['price'] = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, $multiply,$children_count, $animals_count, $services, '');
                if (!empty($coupon)) {
                    $data['price_sale'] = self::calculate_price_per_hour($data['listing_id'],  $data['date_start'], $data['date_end'], $hour_start, $hour_end, $multiply,$children_count, $animals_count, $services, $coupon);
                }
            }
        } else {
            
            $data['price'] = self::calculate_price($data['listing_id'], $data['date_start'], $data['date_end'], $multiply, $children_count, $animals_count, $services, '');
            if (!empty($coupon)) {
                $data['price_sale'] = self::calculate_price($data['listing_id'], $data['date_start'], $data['date_end'], $multiply, $children_count, $animals_count, $services, $coupon);
            }
        }

        // } else {

        //     $data['price'] = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], 1, $services  );
        // }

        if (isset($hour)) {
            $data['_hour'] = $hour;
        }
        if (isset($hour_end)) {
            $data['_hour_end'] = $hour_end;
        }


        //  if($count_per_guest || $data['listing_type'] == 'event' ){

      
        // } else {

        //     $data['price'] = self :: calculate_price( $data['listing_id'], $data['date_start'], $data['date_end'], 1, $services  );
        // }

        if (isset($hour)) {
            $data['_hour'] = $hour;
        }
        if (isset($hour_end)) {
            $data['_hour_end'] = $hour_end;
        }

        // Add commission to price if calculation method is 'add'
        $owner_id = get_post_field('post_author', $data['listing_id']);
        $commission_settings = self::get_commission_settings($owner_id);

        if ($commission_settings['calculation_method'] == 'add') {
            // Calculate commission on base price
            $base_price = $data['price'];
            $commission_amount = self::calculate_commission_amount($base_price, $commission_settings['commission_type'], $commission_settings['commission_value']);

            // Add commission to price
            $data['price'] = $base_price + $commission_amount;

            // Also add commission to discounted price if coupon applied
            if (isset($data['price_sale']) && !empty($data['price_sale'])) {
                $base_price_sale = $data['price_sale'];
                $commission_amount_sale = self::calculate_commission_amount($base_price_sale, $commission_settings['commission_type'], $commission_settings['commission_value']);
                $data['price_sale'] = $base_price_sale + $commission_amount_sale;
            }

            // Store commission info for template display
            $data['commission_added'] = true;
            $data['commission_amount'] = $commission_amount;
            $data['commission_type'] = $commission_settings['commission_type'];
            $data['commission_value'] = $commission_settings['commission_value'];
        }

        $template_loader->set_template_data($data)->get_template_part('booking'); 
 
    }
    /**
     * Save temp reservation
     * 
     * @param array $atts with 'date_start', 'date_end' and 'listing_id'
     * 
     * @return array $temp_reservations with all reservations for this id, also expired if will be
     * 
     */
    public static function save_temp_reservation( $atts ) {

        // get temp reservations for current listing
        $temp_reservations = get_transient( 'listeo_temp_booking_' . $atts['listing_id'] );

        // get current date + time setted as temp booking time
        $expired_date = date( 'Y-m-d H:i:s', strtotime( '+' . apply_filters( 'listeo_expiration_booking_minutes', 15) . ' minutes', time() ) );

        // set array for current temp reservations
        $reservation_data = array(
            'user_id' => get_current_user_id(),
            'date_start' => $atts['date_start'],
            'date_end' => $atts['date_end'],
            'expired_date' => $expired_date
        );

        // add reservation to end of array with all reservations for this listing
        $temp_reservations[] = $reservation_data;

        // set transistence on time setted as temp booking time
        set_transient( 'listeo_temp_booking_' . $atts['listing_id'], $temp_reservations, apply_filters( 'listeo_expiration_minutes', 15) * 60 );

        // return all temp reservations for this id
        return $temp_reservations;

    }

    /**
     * Temp reservation aval
     * 
     * @param array $atts with 'date_start', 'date_end' and 'listing_id'
     *
     * @return number $reservation_amount of all temp reservations form tranistenc fittid this id and time
     * 
     */
    public static function temp_reservation_aval( $args ) {

        // get temp reservations for current listing
        $temp_reservations = get_transient( 'listeo_temp_booking_' . $args['listing_id'] );

        // loop where we will count only reservations fitting to time and user id
        $reservation_amount = 0;

        if ( is_array($temp_reservations) ) 
        {
            foreach ( $temp_reservations as $reservation) {
            
                // if user id is this same then not count
                if ( $reservation['user_id'] == get_current_user_id() ) 
                {
                    continue;
                }

                // when its too old and expired also not count, it will be deleted automaticly with wordpress transistend
                if ( date( 'Y-m-d H:i:s', strtotime( $reservation['expired_date'] ) ) < date( 'Y-m-d H:i:s', time() ) ) 
                {
                    continue;
                }

                // now we converenting strings into dates
                $args['date_start'] = date( 'Y-m-d H:i:s', strtotime( $args['date_start']  ) );
                $args['date_end'] = date( 'Y-m-d H:i:s', strtotime( $args['date_end']  ) );
                $reservations['date_start'] = date( 'Y-m-d H:i:s', strtotime( $reservations['date_start']  ) );
                $reservations['date_end'] = date( 'Y-m-d H:i:s', strtotime( $reservations['date_end']  ) );

                // and compating dates
                if ( ! ( ($args['date_start'] >= $reservation['date_start'] AND $args['date_start'] <= $reservation['date_end']) 
                OR ($args['date_end'] >= $reservation['date_start'] AND $args['date_end'] <= $reservation['date_end']) 
                OR ($reservation['date_start'] >= $args['date_start'] AND $reservation['date_end'] <= $args['date_end']) ) )
                {
                    continue; 
                } 
    
                $reservation_amount++;

            }
        }

        return $reservation_amount;

    }


    /**
     * Owner booking menage shortcode
    * 
    * 
     */
    public static function listeo_core_dashboard_bookings( ) {
    
          
        $users = new Listeo_Core_Users;
        
        $listings = $users->get_agent_listings('',0,-1);
        $args = array (
            'owner_id' => get_current_user_id(),
            'type' => 'reservation',
            
        );

        $limit =  get_option('posts_per_page');
        // make sure the limit is always and even number
        if($limit % 2 != 0) {
            $limit++;
        }
        $pages = '';
        if(isset($_GET['status']) ){
            $booking_max = listeo_count_bookings(get_current_user_id(),$_GET['status']); 
            $pages = ceil($booking_max/$limit);
            $args['status'] = $_GET['status'];
        }
        $bookings = self :: get_newest_bookings($args,$limit );
        ob_start();
        $template_loader = new Listeo_Core_Template_Loader;
        $template_loader->set_template_data( 
            array( 
                'message' => '',
                'bookings' => $bookings,
                'pages' => $pages,
                'listings' => $listings->posts,
            ) )->get_template_part( 'dashboard-bookings' ); 
        $content = ob_get_clean();
        return $content;
 
    }

    public static function listeo_core_dashboard_my_bookings( ) {
    
        ob_start();
        $users = new Listeo_Core_Users;
        $args_default = array (
            'bookings_author' => get_current_user_id(),
            'type' => 'reservation'
        );
        $args =  apply_filters( 'listeo_core_my_bookings_args', $args_default);

     
        $limit =  get_option('posts_per_page');
        if ($limit % 2 != 0) {
            $limit++;
        }

        $bookings = self :: get_newest_bookings($args,$limit );
        $booking_max = listeo_count_my_bookings(get_current_user_id());
        $pages = ceil($booking_max/$limit);
        $template_loader = new Listeo_Core_Template_Loader;
        $template_loader->set_template_data( 
            array( 
                'message' => '',
                'type'    => 'user_booking',
                'bookings' => $bookings,
                'pages' => $pages,
            ) )->get_template_part( 'dashboard-bookings' ); 
        $content = ob_get_clean();
        return $content;
        
    }

    /**
     * Booking Paid
     *
     * Validates that the booking hasn't expired before marking it as paid.
     * If the booking has expired, the order will be refunded/cancelled.
     *
     * @param int $order_id The WooCommerce order ID.
     */
    public static function booking_paid( $order_id ) {

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $booking_id = $order->get_meta( 'booking_id' );
        if ( ! $booking_id ) {
            return;
        }

        // Get the booking data to check its status
        $booking_data = self::get_booking( $booking_id );

        // Check if booking exists and hasn't expired
        if ( ! $booking_data ) {
            $order->add_order_note( __( 'Payment received but booking no longer exists. Manual review required.', 'listeo_core' ) );
            return;
        }

        // Check if the booking has already expired
        if ( $booking_data['status'] === 'expired' ) {
            $order->add_order_note(
                __( 'Payment received after booking expired. The reservation time limit had passed. Order requires manual review or refund.', 'listeo_core' )
            );

            // Optionally update order status to on-hold for manual review
            $order->update_status(
                'on-hold',
                __( 'Booking had expired before payment was received. Manual review required.', 'listeo_core' )
            );

            // Trigger action for custom handling (e.g., auto-refund)
            do_action( 'listeo_payment_received_for_expired_booking', $order_id, $booking_id, $booking_data );
            return;
        }

        // Also check expiration date directly in case cron hasn't run yet
        if ( ! empty( $booking_data['expiring'] ) && $booking_data['expiring'] !== '0000-00-00 00:00:00' ) {
            $expiring_timestamp = strtotime( $booking_data['expiring'] );
            $current_timestamp = current_time( 'timestamp' );

            if ( $current_timestamp > $expiring_timestamp ) {
                // Booking has expired but status wasn't updated yet
                self::set_booking_status( $booking_id, 'expired' );

                $order->add_order_note(
                    __( 'Payment received after booking expiration time. The reservation time limit had passed. Order requires manual review or refund.', 'listeo_core' )
                );

                $order->update_status(
                    'on-hold',
                    __( 'Booking expired before payment was received. Manual review required.', 'listeo_core' )
                );

                do_action( 'listeo_payment_received_for_expired_booking', $order_id, $booking_id, $booking_data );
                return;
            }
        }

        // Booking is valid, mark as paid
        self::set_booking_status( $booking_id, 'paid' );
    }

    /**
    * Booking refund
    *
    * @param number $order_id with id of order
    *
     */
    public static function booking_refund( $order_id ) {

        $order = wc_get_order( $order_id );

        $booking_id = get_post_meta( $order_id, 'booking_id', true );
        if($booking_id){
                self :: set_booking_status( $booking_id, 'refund' );
        }
    }

    /**
     * Validate booking expiration before showing the payment page.
     *
     * Displays an error message and prevents payment if the booking has expired.
     * Hooked to 'before_woocommerce_pay'.
     *
     * @since 2.5.0
     */
    public function validate_booking_before_payment() {
        global $wp;

        if ( ! isset( $wp->query_vars['order-pay'] ) ) {
            return;
        }

        $order_id = absint( $wp->query_vars['order-pay'] );
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $booking_id = $order->get_meta( 'booking_id' );
        if ( ! $booking_id ) {
            return;
        }

        // Check if booking has expired
        if ( $this->is_booking_expired( $booking_id ) ) {
            // Cancel the order
            if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                $order->update_status(
                    'cancelled',
                    __( 'Order cancelled because the booking reservation expired.', 'listeo_core' )
                );
            }

            // Show error message
            wc_add_notice(
                __( 'This booking reservation has expired. The payment time limit has passed and the dates are no longer reserved for you. Please make a new booking.', 'listeo_core' ),
                'error'
            );

            // Redirect to a relevant page
            $redirect_url = apply_filters( 'listeo_expired_booking_redirect_url', wc_get_page_permalink( 'myaccount' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Ensure WooCommerce session cookie is set on the order-pay page.
     *
     * When a customer (especially a guest) opens the payment link from an email,
     * WooCommerce may not start a session. PayPal Payments plugin requires an active
     * session to store the PayPal order reference. Without it, payment fails with
     * "No PayPal order found in the current WooCommerce session".
     *
     * @since 2.5.0
     */
    public function ensure_session_on_order_pay() {
        if ( ! is_admin() && WC()->session && ! WC()->session->has_session() ) {
            if ( ! empty( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
                WC()->session->set_customer_session_cookie( true );
            }
        }
    }

    /**
     * Only hook the view_order capability filter on the order-pay page.
     *
     * This avoids running the filter on every capability check site-wide,
     * following the principle of least privilege.
     *
     * @since 2.5.0
     */
    public function maybe_hook_view_order_cap() {
        global $wp;
        if ( isset( $wp->query_vars['order-pay'] ) ) {
            add_filter( 'user_has_cap', array( $this, 'grant_view_order_for_booking_payment' ), 20, 4 );
        }
    }

    /**
     * Grant 'view_order' capability for booking orders that need payment.
     *
     * The WooCommerce PayPal Payments (PPCP) plugin checks 'view_order' capability
     * in its CreateOrderEndpoint before making any PayPal API call. This is stricter
     * than WooCommerce's own 'pay_for_order' check:
     * - 'view_order' requires exact user ID match with order owner
     * - 'pay_for_order' also allows guest orders (no user assigned)
     *
     * Without this fix, PPCP returns "Invalid request" on the order-pay page
     * without ever contacting PayPal's API.
     *
     * This filter is only registered on the order-pay page via maybe_hook_view_order_cap().
     *
     * @since 2.5.0
     * @param array   $allcaps All capabilities of the user.
     * @param array   $caps    Required capabilities for the check.
     * @param array   $args    Arguments: [0] = capability, [1] = user_id, [2] = object_id.
     * @param WP_User $user    The user object.
     * @return array
     */
    public function grant_view_order_for_booking_payment( $allcaps, $caps, $args, $user ) {
        if ( ! isset( $args[0] ) || 'view_order' !== $args[0] || ! isset( $args[2] ) ) {
            return $allcaps;
        }

        // Already has the capability — skip
        if ( ! empty( $allcaps['view_order'] ) ) {
            return $allcaps;
        }

        $order_id = absint( $args[2] );
        if ( ! $order_id ) {
            return $allcaps;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return $allcaps;
        }

        // Only apply to booking orders
        $booking_id = $order->get_meta( 'booking_id' );
        if ( ! $booking_id ) {
            return $allcaps;
        }

        // Only for orders that still need payment
        if ( ! $order->needs_payment() ) {
            return $allcaps;
        }

        // Grant view_order if user matches order owner OR order has no owner (guest)
        $user_id       = isset( $args[1] ) ? intval( $args[1] ) : 0;
        $order_user_id = $order->get_user_id();

        if ( $user_id === $order_user_id || 0 === $order_user_id ) {
            $allcaps['view_order'] = true;
        }

        return $allcaps;
    }

    /**
     * Filter to check if order needs payment based on booking expiration.
     *
     * Returns false if the booking has expired, preventing payment processing.
     * Hooked to 'woocommerce_order_needs_payment'.
     *
     * @since 2.5.0
     * @param bool     $needs_payment Whether the order needs payment.
     * @param WC_Order $order         The order object.
     * @return bool
     */
    public function check_booking_expiration_for_payment( $needs_payment, $order ) {
        if ( ! $needs_payment ) {
            return $needs_payment;
        }

        $booking_id = $order->get_meta( 'booking_id' );
        if ( ! $booking_id ) {
            return $needs_payment;
        }

        // If booking has expired, order doesn't need payment (it should be cancelled)
        if ( $this->is_booking_expired( $booking_id ) ) {
            // Cancel the order if not already cancelled
            if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                $order->update_status(
                    'cancelled',
                    __( 'Order cancelled because the booking reservation expired.', 'listeo_core' )
                );
            }
            return false;
        }

        return $needs_payment;
    }

    /**
     * Check if a booking has expired.
     *
     * @since 2.5.0
     * @param int $booking_id The booking ID.
     * @return bool True if expired, false otherwise.
     */
    private function is_booking_expired( $booking_id ) {
        $booking_data = self::get_booking( $booking_id );

        if ( ! $booking_data ) {
            return true; // No booking found, treat as expired
        }

        // Check if status is already expired
        if ( $booking_data['status'] === 'expired' ) {
            return true;
        }

        // Check expiration date directly
        if ( ! empty( $booking_data['expiring'] ) && $booking_data['expiring'] !== '0000-00-00 00:00:00' ) {
            $expiring_timestamp = strtotime( $booking_data['expiring'] );
            $current_timestamp = current_time( 'timestamp' );

            if ( $current_timestamp > $expiring_timestamp ) {
                // Mark as expired for consistency
                self::set_booking_status( $booking_id, 'expired' );
                return true;
            }
        }

        return false;
    }

    public function listeo_wc_pre_get_posts_query( $q ) {

        $tax_query = (array) $q->get( 'tax_query' );

        $tax_query[] = array(
               'taxonomy' => 'product_type',
               'field' => 'slug',
               'terms' => array( 'listing_booking' ), // 
               'operator' => 'NOT IN'
        );


        $q->set( 'tax_query', $tax_query );

    }

    public static function get_booking($id){
        global $wpdb;
        $id = (int) $id;
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `' . $wpdb->prefix . 'bookings_calendar` WHERE `id` = %d',
                $id
            ),
            'ARRAY_A'
        );
       
    }
    public static function is_booking_external( $booking_status ): bool {
        $external = false;
        if($booking_status){
            if ( 0 === strpos( $booking_status, 'external' ) ) {
                $external = true;
            }
        }

        return $external;
    }


    public function check_for_expired_booking(){

        global $wpdb;
        $date_format = 'Y-m-d H:i:s';
        // Change status to expired
        $table_name = $wpdb->prefix . 'bookings_calendar';
        $bookings = $wpdb->get_results( $wpdb->prepare( "
            SELECT ID, order_id FROM {$table_name}
            WHERE status not in ('paid','owner_reservations','icalimports','cancelled')
            AND expiring > '0000-00-00 00:00:00'
            AND expiring < %s

        ", date( $date_format, current_time( 'timestamp' ) ) ));

        if ( $bookings ) {
            foreach ( $bookings as $booking ) {
                // Mark booking as expired
                self::set_booking_status( $booking->ID, 'expired' );
                do_action( 'listeo_expire_booking', $booking->ID );

                // Cancel the associated WooCommerce order if it exists and is still pending
                if ( ! empty( $booking->order_id ) && function_exists( 'wc_get_order' ) ) {
                    $order = wc_get_order( $booking->order_id );
                    if ( $order && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                        $order->update_status(
                            'cancelled',
                            __( 'Order cancelled automatically because the booking reservation expired.', 'listeo_core' )
                        );
                    }
                }
            }
        }
    }

    public function check_for_expiring_booking()
    {

        global $wpdb;
        $date_format = 'Y-m-d H:i:s';
        // Change status to expired
        $table_name = $wpdb->prefix . 'bookings_calendar';
        $bookings_ids = $wpdb->get_col($wpdb->prepare("
            SELECT ID FROM {$table_name}
            WHERE status not in ('paid','owner_reservations','icalimports','cancelled')
            AND expiring > '0000-00-00 00:00:00'      
            AND expiring < %s
            
       ", date($date_format, strtotime('+1 hour', current_time('timestamp')))));

        if ($bookings_ids) {
            foreach ($bookings_ids as $booking) {
                // delecting old reservations
                self::set_booking_status($booking, 'expired');
                do_action('listeo_expiring_booking', $booking);
            }
        }
    }

    public function check_for_upcoming_booking(){

        global $wpdb;
        $table_name = $wpdb->prefix . 'bookings_calendar';
        $meta_table = $wpdb->prefix . 'bookings_meta';
        $tomorrow_ts = strtotime('+1 day', current_time('timestamp'));
        $day_after_ts = strtotime('+2 days', current_time('timestamp'));

        // Non-event bookings: use booking date_start
        $bookings_ids = $wpdb->get_col(
            "
            SELECT b.ID FROM {$table_name} b
            LEFT JOIN {$meta_table} bm ON b.ID = bm.booking_id AND bm.meta_key = 'user_notification_upcoming_booking'
            LEFT JOIN {$wpdb->postmeta} pm ON b.listing_id = pm.post_id AND pm.meta_key = '_event_date_timestamp'
            WHERE b.status IN ('paid')
            AND b.date_start > DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND b.date_start < DATE_ADD(CURDATE(), INTERVAL 2 DAY)
            AND b.date_start > '2000-01-01'
            AND bm.meta_id IS NULL
            AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        // Event/ticket bookings: use listing's _event_date_timestamp
        $event_bookings_ids = $wpdb->get_col( $wpdb->prepare(
            "
            SELECT b.ID FROM {$table_name} b
            LEFT JOIN {$meta_table} bm ON b.ID = bm.booking_id AND bm.meta_key = 'user_notification_upcoming_booking'
            INNER JOIN {$wpdb->postmeta} pm ON b.listing_id = pm.post_id AND pm.meta_key = '_event_date_timestamp'
            WHERE b.status IN ('paid')
            AND pm.meta_value != ''
            AND CAST(pm.meta_value AS UNSIGNED) > %d
            AND CAST(pm.meta_value AS UNSIGNED) < %d
            AND bm.meta_id IS NULL",
            $tomorrow_ts,
            $day_after_ts
        ));

        $bookings_ids = array_unique( array_merge(
            $bookings_ids ? $bookings_ids : array(),
            $event_bookings_ids ? $event_bookings_ids : array()
        ));

        if ( $bookings_ids ) {
            foreach ( $bookings_ids as $booking ) {
                // delecting old reservations
                $booking_data =  self::get_booking($booking);
                
                do_action('listeo_mail_to_user_upcoming_booking', $booking_data);
                do_action('listeo_upcoming_booking',$booking);
            }
        }
    }
    public function check_for_past_booking(){

        global $wpdb;

        $table_name = $wpdb->prefix . 'bookings_calendar';
        $meta_table = $wpdb->prefix . 'bookings_meta';
        $yesterday_ts = strtotime('-1 day', current_time('timestamp'));

        // 1. Non-event bookings: use booking date_end as before
        $bookings_ids = $wpdb->get_col(
            "
            SELECT b.ID FROM {$table_name} b
            LEFT JOIN {$meta_table} bm ON b.ID = bm.booking_id AND bm.meta_key = 'user_review_reminder'
            LEFT JOIN {$wpdb->postmeta} pm ON b.listing_id = pm.post_id AND pm.meta_key = '_event_date_timestamp'
            WHERE b.status IN ('paid')
            AND b.date_end < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND b.date_end > '2000-01-01'
            AND bm.meta_id IS NULL
            AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        // 2. Event/ticket bookings: use listing's _event_date_timestamp instead of booking date_end
        $event_bookings_ids = $wpdb->get_col( $wpdb->prepare(
            "
            SELECT b.ID FROM {$table_name} b
            LEFT JOIN {$meta_table} bm ON b.ID = bm.booking_id AND bm.meta_key = 'user_review_reminder'
            INNER JOIN {$wpdb->postmeta} pm ON b.listing_id = pm.post_id AND pm.meta_key = '_event_date_timestamp'
            WHERE b.status IN ('paid')
            AND pm.meta_value != ''
            AND CAST(pm.meta_value AS UNSIGNED) > 0
            AND CAST(pm.meta_value AS UNSIGNED) < %d
            AND bm.meta_id IS NULL",
            $yesterday_ts
        ));

        $all_booking_ids = array_unique( array_merge(
            $bookings_ids ? $bookings_ids : array(),
            $event_bookings_ids ? $event_bookings_ids : array()
        ));

        if ( $all_booking_ids ) {
            foreach ( $all_booking_ids as $booking ) {
                $booking_data = self::get_booking($booking);

                do_action('listeo_mail_to_user_past_booking', $booking_data);
                do_action('listeo_past_booking', $booking);
            }
        }
    }

    public function check_for_upcoming_payments(){
        global $wpdb;
        $date_format = 'Y-m-d H:i:s';
        // Change status to expired
        $now = current_time('mysql'); 
        $table_name = $wpdb->prefix . 'bookings_calendar';
        $bookings_ids = $wpdb->get_col($wpdb->prepare("
            SELECT ID FROM {$table_name}
            WHERE status not in ('paid','owner_reservations','icalimports','cancelled')      
            AND expiring > %s
			AND expiring < %s
            
        ", 
		date($date_format, strtotime($now)), 
		date($date_format, strtotime($now) + 3600 )
        ));

        if ($bookings_ids) {
            foreach ($bookings_ids as $booking) {
                // delecting old reservations
                $booking_data =  self::get_booking($booking);
              do_action('listeo_upcoming_payment', $booking_data);
            }
        }
        
    }


    protected  function get_posted_field($key, $field)
    {

        return isset($_POST[$key]) ? $this->sanitize_posted_field($_POST[$key]) : '';
    }


    protected function get_posted_file_field($key, $field)
    {

        $file = $this->upload_file($key, $field);

        if (!$file) {
            $file = $this->get_posted_field('current_' . $key, $field);
        } elseif (is_array($file)) {
            $file = array_filter(array_merge($file, (array) $this->get_posted_field('current_' . $key, $field)));
        }

        return $file;
    }
    /**
     * Handles the uploading of files.
     *
     * @param string $field_key
     * @param array  $field
     * @throws Exception When file upload failed
     * @return  string|array
     */
    protected function upload_file($field_key, $field)
    {
        if (isset($_FILES[$field_key]) && !empty($_FILES[$field_key]) && !empty($_FILES[$field_key]['name'])) {
            if (!empty($field['allowed_mime_types'])) {
                $allowed_mime_types = $field['allowed_mime_types'];
            } else {
                $allowed_mime_types = listeo_get_allowed_mime_types();
            }

            $file_urls       = array();
            $files_to_upload = listeo_prepare_uploaded_files($_FILES[$field_key]);

            foreach ($files_to_upload as $file_to_upload) {
                $uploaded_file = listeo_upload_file($file_to_upload, array(
                    'file_key'           => $field_key,
                    'allowed_mime_types' => $allowed_mime_types,
                ));

                if (is_wp_error($uploaded_file)) {
                    throw new Exception($uploaded_file->get_error_message());
                } else {
                    $file_urls[] = $uploaded_file->url;
                }
            }

            if (!empty($field['multiple'])) {
                return $file_urls;
            } else {
                return current($file_urls);
            }
        }
    }
    /**
     * Navigates through an array and sanitizes the field.
     *
     * @param array|string $value The array or string to be sanitized.
     * @return array|string $value The sanitized array (or string from the callback).
     */
    protected function sanitize_posted_field($value)
    {
        // Santize value
        $value = is_array($value) ? array_map(array($this, 'sanitize_posted_field'), $value) : sanitize_text_field(stripslashes(trim($value)));

        return $value;
    }

    /**
     * Gets the value of a posted textarea field.
     *
     * @param  string $key
     * @param  array  $field
     * @return string
     */
    protected  function get_posted_textarea_field($key, $field)
    {
        return isset($_POST[$key]) ? wp_kses_post(trim(stripslashes($_POST[$key]))) : '';
    }

    /**
     * Gets the value of a posted textarea field.
     *
     * @param  string $key
     * @param  array  $field
     * @return string
     */
    function  get_posted_wp_editor_field($key, $field)
    {
        return $this->get_posted_textarea_field($key, $field);
    }

    protected function create_attachment($attachment_url)
    {
        include_once(ABSPATH . 'wp-admin/includes/image.php');
        include_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload_dir     = wp_upload_dir();
        $attachment_url = str_replace(array($upload_dir['baseurl'], WP_CONTENT_URL, site_url('/')), array($upload_dir['basedir'], WP_CONTENT_DIR, ABSPATH), $attachment_url);

        if (empty($attachment_url) || !is_string($attachment_url)) {
            return 0;
        }

        $attachment     = array(
            'post_title'   =>  wp_generate_password(8, false),
            'post_content' => '',
            'post_status'  => 'inherit',
            'guid'         => $attachment_url
        );

        if ($info = wp_check_filetype($attachment_url)) {
            $attachment['post_mime_type'] = $info['type'];
        }

        $attachment_id = wp_insert_attachment($attachment, $attachment_url);

        if (!is_wp_error($attachment_id)) {
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $attachment_url));
            return $attachment_id;
        }

        return 0;
    }


}

?>