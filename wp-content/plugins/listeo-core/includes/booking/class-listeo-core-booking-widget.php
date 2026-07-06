<?php


/**
 * Listeo Core Widget base
 */
class Listeo_Core_BWidget extends WP_Widget
{
    /**
     * Widget CSS class
     *
     * @access public
     * @var string
     */
    public $widget_cssclass;

    /**
     * Widget description
     *
     * @access public
     * @var string
     */
    public $widget_description;

    /**
     * Widget id
     *
     * @access public
     * @var string
     */
    public $widget_id;

    /**
     * Widget name
     *
     * @access public
     * @var string
     */
    public $widget_name;

    /**
     * Widget settings
     *
     * @access public
     * @var array
     */
    public $settings;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->register();
    }


    /**
     * Register Widget
     */
    public function register()
    {
        $widget_ops = array(
            'classname'   => $this->widget_cssclass,
            'description' => $this->widget_description
        );

        parent::__construct($this->widget_id, $this->widget_name, $widget_ops);

        add_action('save_post', array($this, 'flush_widget_cache'));
        add_action('deleted_post', array($this, 'flush_widget_cache'));
        add_action('switch_theme', array($this, 'flush_widget_cache'));
    }



    /**
     * get_cached_widget function.
     */
    public function get_cached_widget($args)
    {

        return false;

        $cache = wp_cache_get($this->widget_id, 'widget');

        if (!is_array($cache))
            $cache = array();

        if (isset($cache[$args['widget_id']])) {
            echo $cache[$args['widget_id']];
            return true;
        }

        return false;
    }

    /**
     * Cache the widget
     */
    public function cache_widget($args, $content)
    {
        $cache[$args['widget_id']] = $content;

        wp_cache_set($this->widget_id, $cache, 'widget');
    }

    /**
     * Flush the cache
     * @return [type]
     */
    public function flush_widget_cache()
    {
        wp_cache_delete($this->widget_id, 'widget');
    }

    /**
     * update function.
     *
     * @see WP_Widget->update
     * @access public
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;

        if (!$this->settings)
            return $instance;

        foreach ($this->settings as $key => $setting) {
            $instance[$key] = sanitize_text_field($new_instance[$key]);
        }

        $this->flush_widget_cache();

        return $instance;
    }

    /**
     * form function.
     *
     * @see WP_Widget->form
     * @access public
     * @param array $instance
     * @return void
     */
    function form($instance)
    {

        if (!$this->settings)
            return;

        foreach ($this->settings as $key => $setting) {

            $value = isset($instance[$key]) ? $instance[$key] : $setting['std'];

            switch ($setting['type']) {
                case 'text':
?>
                    <p>
                        <label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
                        <input class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>" type="text" value="<?php echo esc_attr($value); ?>" />
                    </p>
                <?php
                    break;
                case 'checkbox':
                ?>
                    <p>
                        <label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
                        <input class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>" type="checkbox" <?php checked(esc_attr($value), 'on'); ?> />
                    </p>
                <?php
                    break;
                case 'number':
                ?>
                    <p>
                        <label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
                        <input class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>" type="number" step="<?php echo esc_attr($setting['step']); ?>" min="<?php echo esc_attr($setting['min']); ?>" max="<?php echo esc_attr($setting['max']); ?>" value="<?php echo esc_attr($value); ?>" />
                    </p>
                <?php
                    break;
                case 'dropdown':
                ?>
                    <p>
                        <label for="<?php echo $this->get_field_id($key); ?>"><?php echo $setting['label']; ?></label>
                        <select class="widefat" id="<?php echo esc_attr($this->get_field_id($key)); ?>" name="<?php echo $this->get_field_name($key); ?>">

                            <?php foreach ($setting['options'] as $key => $option_value) { ?>
                                <option <?php selected($value, $key); ?> value="<?php echo esc_attr($key); ?>"><?php echo esc_attr($option_value); ?></option>
                            <?php } ?>
                        </select>

                    </p>
                <?php
                    break;
            }
        }
    }

    /**
     * widget function.
     *
     * @see    WP_Widget
     * @access public
     *
     * @param array $args
     * @param array $instance
     *
     * @return void
     */
    public function widget($args, $instance) {}
}
/**
 * Booking Widget
 */
class Listeo_Core_Booking_Widget extends Listeo_Core_BWidget
{

    /**
     * Constructor
     */
    public function __construct()
    {

        // create object responsible for bookings
        $this->bookings = new Listeo_Core_Bookings_Calendar;

        $this->widget_cssclass    = 'listeo_core boxed-widget booking-widget margin-bottom-35';
        $this->widget_description = __('Shows Booking Form.', 'listeo_core');
        $this->widget_id          = 'widget_booking_listings';
        $this->widget_name        =  __('Listeo Booking Form', 'listeo_core');
        $this->settings           = array(
            'title' => array(
                'type'  => 'text',
                'std'   => __('Booking', 'listeo_core'),
                'label' => __('Title', 'listeo_core')
            ),
            'show_price' => array(
                'type'  => 'checkbox',
                'std'   => '1',
                'label' => __('Show Price label', 'listeo_core')
            ),
            'show_tickets_left' => array(
                'type'  => 'checkbox',
                'std'   => 'on',
                'label' => __('Show tickets remaining for events', 'listeo_core')
            ),




        );
        $this->register();
    }

    private function get_rental_availability($post_info, $post_meta, $records)
    {
        
        if (apply_filters('listeo_allow_overbooking', false)) {
            return array();
        }
        $post_id = $post_info->ID;
        $wpk_start_dates = array();
        $wpk_end_dates = array();
        $disabled_dates = array();
        $partial_booked_dates = array();

        if (!empty($records)) {
            if (get_post_meta($post_info->ID, '_rental_timepicker', true) == 'on') {
                foreach ($records as $record) {

                    // Get the dates and times
                    $date_start = date('Y-m-d', strtotime($record['date_start']));
                    $date_end = date('Y-m-d', strtotime($record['date_end']));
                    $time_start = date('H:i', strtotime($record['date_start']));
                    $time_end = date('H:i', strtotime($record['date_end']));

                    // Single day booking
                    if ($date_start == $date_end) {
                        if (!isset($partial_booked_dates[$date_start])) {
                            $partial_booked_dates[$date_start] = array();
                        }
                        $partial_booked_dates[$date_start][] = array(
                            'start' => $time_start,
                            'end' => $time_end
                        );

                        // If the entire day is booked (e.g., 00:00-23:59), add to disabled dates
                        if ($time_start == '00:00' && $time_end == '23:59') {
                            $disabled_dates[] = $date_start;
                        }
                    } else {
                        // Multi-day booking
                        $wpk_start_dates[] = $date_start;
                        $wpk_end_dates[] = $date_end;

                        // First day partial booking
                        if (!isset($partial_booked_dates[$date_start])) {
                            $partial_booked_dates[$date_start] = array();
                        }
                        $partial_booked_dates[$date_start][] = array(
                            'start' => $time_start,
                            'end' => '23:59'
                        );

                        // Last day partial booking
                        if (!isset($partial_booked_dates[$date_end])) {
                            $partial_booked_dates[$date_end] = array();
                        }
                        $partial_booked_dates[$date_end][] = array(
                            'start' => '00:00',
                            'end' => $time_end
                        );

                        // Days in between are fully booked
                        $period = new DatePeriod(
                            new DateTime($date_start . ' +1 day'),
                            new DateInterval('P1D'),
                            new DateTime($date_end)
                        );

                        foreach ($period as $day) {
                            $disabled_dates[] = $day->format('Y-m-d');
                        }
                    }
                }
            } else {
                $listing_type = listeo_get_booking_type($post_id);

                if ($listing_type == 'date_range') {
                    foreach ($records as $record) {


                        // Regular rental booking (non-timepicker)
                        if ($record['date_start'] == $record['date_end']) {
                            $wpk_start_dates[] = date('Y-m-d', strtotime($record['date_start']));

                            $wpk_end_dates[] = date('Y-m-d', strtotime($record['date_start'] . ' + 1 day'));
                        } else {
                            // get full dates:
                            // $start_date = date('Y-m-d', strtotime($record['date_start']));
                            // $end_date = date('Y-m-d', strtotime($record['date_end']));

                            // $disabled_dates[] = $start_date;
                            // $disabled_dates[] = $end_date;

                            // $wpk_start_dates[] = $start_date;
                            // $wpk_end_dates[] = $end_date;
                            //
                            $wpk_start_dates[] = date('Y-m-d', strtotime($record['date_start']));
                            $wpk_end_dates[] = date('Y-m-d', strtotime($record['date_end']));

                            $period = new DatePeriod(
                                new DateTime(date('Y-m-d', strtotime($record['date_start']))),
                                new DateInterval('P1D'),
                                new DateTime(date('Y-m-d', strtotime($record['date_end'] . ' + 1 day')))
                            );

                            foreach ($period as $day) {

                                //$disabled_dates[] = $day->format('Y-m-d');
                                $formatted_day = $day->format('Y-m-d');
                                if (!in_array($formatted_day, $wpk_start_dates) && !in_array($formatted_day, $wpk_end_dates)) {
                                    $disabled_dates[] = $formatted_day;
                                }
                            }
                        }
                    }
                } else {
                    
                    foreach ($records as $record) {
                        $start_date = date('Y-m-d', strtotime($record['date_start']));
                     
                        if ($record['status'] == 'owner_reservations') {
                            $disabled_dates[] = $start_date;
                        }
                        // if record status starts with "external_" it means it's an iCal import
                      
                        if (strpos($record['status'], 'external') === 0) {
                            // Handle iCal import logic here
                            // disable each date from start to end

                            $start_date = date('Y-m-d', strtotime($record['date_start']));
                            $end_date = date('Y-m-d', strtotime($record['date_end']));

                            $period = new DatePeriod(
                                new DateTime($start_date),
                                new DateInterval('P1D'),
                                new DateTime($end_date . ' +1 day')
                            );


                            foreach ($period as $day) {
                                // disable dates only if filter allows it
                                if (apply_filters('listeo_disable_slots_external_dates', false)) {
                                    $disabled_dates[] = $day->format('Y-m-d');
                                }
                            }
                          
                        }
                    }
                }
            }
            return array(
                'wpk_start_dates' => array_unique($wpk_start_dates),
                'wpk_end_dates' => array_unique($wpk_end_dates),
                'disabled_dates' => array_unique($disabled_dates),
                'partial_booked_dates' => $partial_booked_dates
            );
        } else {
            return array();
        }
    }
    /**
     * widget function.
     *
     * @see WP_Widget
     * @access public
     * @param array $args
     * @param array $instance
     * @return void
     */
    public function widget($args, $instance)
    {


        

        extract($args);
        $title  = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
        $show_price = isset($instance['show_price']) ? $instance['show_price'] : false;
        $show_tickets_left = isset($instance['show_tickets_left']) ? $instance['show_tickets_left'] : 'on';

        $queried_object = get_queried_object();
        $packages_disabled_modules = get_option('listeo_listing_packages_options', array());
        if (empty($packages_disabled_modules)) {
            $packages_disabled_modules = array();
        }
        if ($queried_object) {
            $post_id = $queried_object->ID;



            if (empty($packages_disabled_modules)) {
                $packages_disabled_modules = array();
            }

            $user_package = get_post_meta($post_id, '_user_package_id', true);
            if ($user_package) {
                $package = listeo_core_get_user_package($user_package);
            }

           
        }

        if(listeo_listing_supports_booking() === false){
            return;
        }

        // get booking config
        $booking_config = listeo_get_booking_config();
     

        if (in_array('option_booking', $packages_disabled_modules)) {

            if (isset($package) && $package->has_listing_booking() != 1) {
                return;
            }
        }

        if ($queried_object) {
            $post_id = $queried_object->ID;
            $_booking_status = get_post_meta($post_id, '_booking_status', true); {
                if (!$_booking_status) {
                    return;
                }
            }
            if (get_post_status($post_id) == 'expired') {
                return;
            }
        }
        ob_start();

        echo $before_widget;

        $price_type = get_post_meta($post_id, '_count_by_hour', true) ? esc_html__('per hour', 'listeo_core') : esc_html__('per day', 'listeo_core');
        // if booking_features has tickets
        if (listeo_listing_supports_feature('tickets', $post_id)) {
            $price_type = '';
        }

        if ($title) {
            echo "<div class='booking-widget-title-wrap'>";
            echo $before_title . '<i class="fa fa-calendar-check"></i> ' . $title;
            echo $after_title;
            if ($show_price) {
                if (get_the_listing_price_range()) : ?>
                    <span class="booking-pricing-tag"><?php echo get_the_listing_price_range(); ?> <?php echo $price_type; ?>
                    </span>
                    <?php else:
                    if (get_post_meta($post_id, '_normal_price', true)) : ?>
                        <span class="booking-pricing-tag"><?php echo esc_html__('Starts from', 'listeo_core');
                                                            echo ' ' . listeo_output_price(get_post_meta($post_id, '_normal_price', true)); ?> <?php echo $price_type; ?></span>
                <?php endif;
                endif;
            }

            echo "</div>";
        }
        do_action('listeo_before_booking_form', $post_id);

        $days_list = array(
            0    => __('Monday', 'listeo_core'),
            1     => __('Tuesday', 'listeo_core'),
            2    => __('Wednesday', 'listeo_core'),
            3     => __('Thursday', 'listeo_core'),
            4     => __('Friday', 'listeo_core'),
            5     => __('Saturday', 'listeo_core'),
            6     => __('Sunday', 'listeo_core'),
        );

        // get post meta and save slots to var
        $post_info = get_queried_object();
        if ($post_info) {
            $post_meta = get_post_meta($post_info->ID);
        } else {
            $content = ob_get_clean();
            return false;
        }
    // get slots and check if not empty
    // check if listing type feature supports time slots
        $slots = false;
        if (listeo_listing_supports_feature('time_slots', $post_info->ID)) {
            if (isset($post_meta['_slots_status'][0]) && !empty($post_meta['_slots_status'][0])) {
                if (isset($post_meta['_slots'][0])) {

                    // Normalize dash variants unconditionally before decoding
                    $correctedSlotsString = strtr((string) $post_meta['_slots'][0], [
                        "\xE2\x80\x93" => '-', // en dash
                        "\xE2\x80\x94" => '-', // em dash
                        "\xE2\x88\x92" => '-', // minus sign
                    ]);

                    $slots = json_decode($correctedSlotsString);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $slots = false;
                    }

                    if (!get_option('listeo_skip_hyphen_check')) {
                        if (strpos($correctedSlotsString, '-') === false) {
                            $slots = false;
                        }
                    }
                } else {
                    $slots = false;
                }
            } else {
                $slots = false;
            }
        }       
        // get opening hours
        if (isset($post_meta['_opening_hours'][0])) {
            $opening_hours = json_decode($post_meta['_opening_hours'][0], true);
        }

        if (listeo_get_booking_type($post_id) == 'date_range' || listeo_get_booking_type($post_id) == 'single_day' ) {

            // get reservations for next 10 years to make unable to set it in datapicker
            if (listeo_get_booking_type($post_id) == 'date_range') {
                $records = $this->bookings->get_bookings(
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s', strtotime('+3 years')),
                    array('listing_id' => $post_info->ID, 'type' => 'reservation'),
                    $by = 'booking_date',
                    $limit = '',
                    $offset = '',
                    $all = '',
                    $listing_type = 'rental'
                );
            } else {

                $records = $this->bookings->get_bookings(
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s', strtotime('+3 years')),
                    array('listing_id' => $post_info->ID, 'type' => 'reservation'),
                    'booking_date',
                    $limit = '',
                    $offset = '',
                    ''
                );
            }

            
            if (listeo_get_booking_type($post_id) == 'date_range' || listeo_get_booking_type($post_id) == 'single_day' ) {



                $availability = $this->get_rental_availability($post_info, $post_meta, $records);
                
                $_opening_hours_status = get_post_meta($post_id, '_opening_hours_status', true);
                $output_opening_hours = get_post_meta($post_id, '_opening_hours', true);

                // if opening_hours are not set, thee might be a individual opening hours for each day, in such case combine them all to one array as in the opening_hours
                if (!$output_opening_hours) {

                    $output_opening_hours = [];
                    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

                    foreach ($days as $day) {
                        // Get the serialized meta values
                        $opening_meta = get_post_meta(get_the_ID(), '_' . $day . '_opening_hour', true);
                        $closing_meta = get_post_meta(get_the_ID(), '_' . $day . '_closing_hour', true);

                        // Initialize default arrays
                        $opening_hours = [''];
                        $closing_hours = [''];

                        // Parse opening hours
                        if (!empty($opening_meta)) {

                            // Check if it's already unserialized
                            if (is_string($opening_meta) && strpos($opening_meta, 'a:') === 0) {
                                $unserialized_opening = unserialize($opening_meta);
                                if ($unserialized_opening !== false) {
                                    // Extract values while preserving array structure
                                    $opening_hours = [];
                                    foreach ($unserialized_opening as $time) {
                                        $opening_hours[] = $time;
                                    }
                                }
                            } elseif (is_array($opening_meta)) {
                                $opening_hours = array_values($opening_meta);
                            }
                        }

                        // Parse closing hours
                        if (!empty($closing_meta)) {
                            // Check if it's already unserialized
                            if (is_string($closing_meta) && strpos($closing_meta, 'a:') === 0) {
                                $unserialized_closing = unserialize($closing_meta);
                                if ($unserialized_closing !== false) {
                                    // Extract values while preserving array structure
                                    $closing_hours = [];
                                    foreach ($unserialized_closing as $time) {
                                        $closing_hours[] = $time;
                                    }
                                }
                            } elseif (is_array($closing_meta)) {
                                $closing_hours = array_values($closing_meta);
                            }
                        }

                        // Ensure we have at least empty strings in arrays
                        if (empty($opening_hours)) {
                            $opening_hours = [''];
                        }
                        if (empty($closing_hours)) {
                            $closing_hours = [''];
                        }


                        // Add to formatted array
                        $output_opening_hours[] = [
                            'opening' => $opening_hours,
                            'closing' => $closing_hours
                        ];
                    }
                    $output_opening_hours = json_encode($output_opening_hours, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                ?>
                <script>
                    var availableDays = <?php echo json_encode($output_opening_hours, true);
                                        ?>;
                </script>
                <?php if (!empty($availability['wpk_start_dates'])) {
                ?>
                    <script>
                        var wpkStartDates = <?php echo json_encode($availability['wpk_start_dates']); ?>;
                        var wpkEndDates = <?php echo json_encode($availability['wpk_end_dates']); ?>;
                    </script>
                <?php
                }

                if (!empty($availability['disabled_dates'])) {
                ?>
                    <script>
                        var disabledDates = <?php echo json_encode(array_values($availability['disabled_dates'])); ?>;
                    </script>
                <?php
                }

                if (!empty($availability['partial_booked_dates'])) {
                ?>
                    <script>
                        var partialBookedDates = <?php echo json_encode($availability['partial_booked_dates']); ?>;
                    </script>
                <?php
                } else {
                ?>
                    <script>
                        var partialBookedDates = [];
                    </script>
                <?php }
            }
          


        } // end if rental/service


        if (listeo_get_booking_type($post_id) == 'tickets' ) {
            $max_tickets = (int) get_post_meta($post_info->ID, "_event_tickets", true);
            $sold_tickets = (int) get_post_meta($post_info->ID, "_event_tickets_sold", true);
            $av_tickets = $max_tickets - $sold_tickets;

            /**
             * Filter to override the "tickets remaining" calculation.
             * Booking Plus uses this to sum availability across multi-tier ticket types.
             *
             * @param int|null $available  Override value, or null to use legacy meta calculation.
             * @param int      $post_id    Listing ID.
             */
            $bp_available = apply_filters( 'listeo_event_tickets_available', null, $post_info->ID );
            if ( $bp_available !== null ) {
                $av_tickets = (int) $bp_available;
            }

            // Debug ticket info
          //  error_log("Max tickets: $max_tickets, Sold tickets: $sold_tickets, Available: $av_tickets");

            $event_date = (int) get_post_meta($post_info->ID, "_event_date_timestamp", true);
            $event_date_end = (int) get_post_meta($post_info->ID, "_event_date_end_timestamp", true);

            // Fallback for custom listing types that don't have timestamp fields
            if (empty($event_date)) {
                $event_date_raw = get_post_meta($post_info->ID, "_event_date", true);
                if (!empty($event_date_raw)) {
                    $event_date = Listeo_Core_Post_Types::parse_event_date_to_timestamp($event_date_raw);
                }
            }

            if (empty($event_date_end)) {
                $event_date_end_raw = get_post_meta($post_info->ID, "_event_date_end", true);
                if (!empty($event_date_end_raw)) {
                    $event_date_end = Listeo_Core_Post_Types::parse_event_date_to_timestamp($event_date_end_raw);
                } else {
                    // If no end date, use start date
                    $event_date_end = $event_date;
                }
            }

            $current_date = time();

          
            // check if event date is in the past

            if ($event_date_end < $current_date) {
                ?>
                <p id="sold-out"><?php esc_html_e('The event has passed', 'listeo_core') ?></p>
                </div>
            <?php
                $content = ob_get_clean();
                echo $content;
                return;
            }
            // Only check ticket availability if tickets are actually configured
            if ($max_tickets > 0 && $av_tickets <= 0) { ?>
                <p id="sold-out"><?php esc_html_e('The tickets have sold out', 'listeo_core') ?></p>
                </div>
        <?php
                $content = ob_get_clean();
                echo $content;
                return;
            }
        }
        ?>

        <div class="row with-forms  margin-top-0" id="booking-widget-anchor">
            <form ​ autocomplete="off" id="form-booking" data-post_id="<?php echo $post_info->ID; ?>" class="form-booking-<?php echo listeo_get_booking_type($post_info->ID); ?>" action="<?php echo esc_url(get_permalink(get_option('listeo_booking_confirmation_page'))); ?>" method="post">

                <?php
                // Allow plugins to add content after form opens (e.g., resource selector)
                do_action( 'listeo_booking_form_after_open', $post_info->ID );
                ?>
                <?php
                
                $timepickerIncremental = get_post_meta($post_info->ID, '_time_increment', true);

                if ($timepickerIncremental) {
                    $timepickerIncremental = (int) $timepickerIncremental;
                } else {
                    $timepickerIncremental = 15;
                }

                if ($timepickerIncremental == 60) {
                    // add style to hide minutes
                    echo '<style>.calendar-time select.minuteselect { display: none !important; }</style>';
                }
                if (listeo_get_booking_type($post_id) != 'tickets') {
                    $minspan = get_post_meta($post_info->ID, '_min_days', true);
                    //WP Kraken
                    // If minimub booking days are not set, set to 2 by default
                    if (!$minspan && listeo_get_booking_type($post_info->ID) == 'date_range') {
                        $minspan = 2;
                    }
                    $minspan = apply_filters('listeo_core_min_booking_days', $minspan, $post_info->ID);

                    if (listeo_get_booking_type($post_info->ID) == 'date_range') {
                        $_rental_timepicker = get_post_meta($post_info->ID, '_rental_timepicker', true);
                        $minspan = get_post_meta($post_info->ID, '_min_days', true);
                        if (!$minspan) {
                            $minspan = 0;
                        }
                    } else {
                        $_rental_timepicker = false;
                    }
                    // if booking type doesn't have hourly_pikcer feature, disable timepicker
                    if (!listeo_listing_supports_feature('hourly_picker', $post_info->ID)) {
                        $_rental_timepicker = false;
                    }

                    // Check time slot layout setting
                    $time_slot_layout = get_option('listeo_time_slot_layout', 'dropdown');
                    $hide_date_picker_class = (listeo_get_booking_type($post_id) == 'single_day' && is_array($slots) && $time_slot_layout === 'grid') ? ' slot-carousel-mode-hidden' : '';
                ?>
                    <!-- Date Range Picker - docs: http://www.daterangepicker.com/ -->
                    <div class="col-lg-12<?php echo $hide_date_picker_class; ?>">
                        <input type="text" data-time-increment="<?php echo $timepickerIncremental; ?>" <?php if ($_rental_timepicker) {
                                                                                                            echo 'data-rental-timepicker="true"';
                                                                                                        } ?> data-minspan="<?php echo ($minspan) ? $minspan : '0'; ?>" id="date-picker" readonly="readonly" class="date-picker-listing-<?php echo listeo_get_booking_type($post_id); ?>" autocomplete="off" placeholder="<?php esc_attr_e('Select Dates', 'listeo_core'); ?>" value="" data-listing_type="<?php echo listeo_get_booking_type($post_id); ?>" />
                    </div>
                    <div class="booking-notice-message"> </div>


                    <!-- Panel Dropdown / Time Slots Grid -->
                    <?php if (listeo_get_booking_type($post_id) == 'single_day' &&   is_array($slots)) {
                        $slot_days_array = array();
                        $availability = $this->get_rental_availability($post_info, $post_meta, $records);
                        
                        foreach ($slots as $day => $day_slots) {
                            if (empty($day_slots)) continue;
                            // pon wt srod czwartek piatek sobota niedzial
                            // 0   1   2   3         4      5      6
                            // 1   2   3   4         5      6      0
                            $day++;
                            if ($day == 7) {
                                $day = 0;
                            }

                            $slot_days_array[] = $day;
                        }

                        if ($time_slot_layout === 'grid') {
                            // CAROUSEL LAYOUT - Calculate availability for initial batch of days

                            // Get hide booked slots setting
                            $hide_booked_slots = get_option('listeo_hide_booked_slots', 'on');

                            // Calculate availability for next 30 days
                            $initial_days = 30;
                            $availability_by_date = array();

                            // Get timezone - use WordPress's wp_timezone() for proper handling of both
                            // timezone strings (e.g., "America/New_York") and manual UTC offsets (e.g., -4)
                            if (function_exists('wp_timezone')) {
                                // WordPress 5.3+ - recommended approach
                                $timezone = wp_timezone();
                            } else {
                                // Fallback for older WordPress versions
                                $timezone = new DateTimeZone(wp_timezone_string());
                            }

                            $datetime_now = new DateTime('now', $timezone);
                            $today = $datetime_now->format('Y-m-d');
                            $hour_now = $datetime_now->format('Hi');

                            // Calculate availability for each day
                            for ($day_offset = 0; $day_offset < $initial_days; $day_offset++) {
                                $current_date = clone $datetime_now;
                                $current_date->modify("+{$day_offset} days");
                                $date_str = $current_date->format('Y-m-d');
                                $day_of_week = (int)$current_date->format('N') - 1;
                                if ($day_of_week == -1) $day_of_week = 6;

                                // Check if this date is disabled (blocked by owner, iCal, etc.)
                                if (isset($availability['disabled_dates']) && in_array($date_str, $availability['disabled_dates'])) {
                                    $availability_by_date[$date_str] = array(); // No slots available for blocked days
                                    continue;
                                }

                                $slots_for_day = isset($slots[$day_of_week]) ? $slots[$day_of_week] : array();
                                $available_slots = array();

                                if (is_array($slots_for_day) && !empty($slots_for_day)) {
                                    foreach ($slots_for_day as $key => $slot) {
                                        $places = explode('|', $slot);
                                        if (count($places) < 2) continue;

                                        $time_range = $places[0];
                                        $free_places = intval($places[1]);

                                        $hours = explode('-', $time_range);
                                        if (count($hours) < 2) continue;

                                        $hour_start = date("H:i:s", strtotime(trim($hours[0])));
                                        $hour_end = date("H:i:s", strtotime(trim($hours[1])));

                                        $booking_date_start = $date_str . ' ' . $hour_start;
                                        $booking_date_end = $date_str . ' ' . $hour_end;

                                        // Query bookings for this slot
                                        $result = $this->bookings->get_slots_bookings(
                                            $booking_date_start,
                                            $booking_date_end,
                                            array('listing_id' => $post_info->ID, 'type' => 'reservation')
                                        );
                                        $reservations_amount = count($result);
                                        $free_places -= $reservations_amount;

                                        // Check if slot should be included
                                        $is_booked = ($free_places <= 0);
                                        $should_include = ($hide_booked_slots === 'on') ? !$is_booked : true;

                                        if ($should_include) {
                                            $hour_bit_of_slot = date("Hi", strtotime(trim($hours[0])));

                                            // Skip past time slots for today
                                            if ($today == $date_str) {
                                                if ($hour_now >= $hour_bit_of_slot) {
                                                    continue;
                                                }
                                            }

                                            $available_slots[] = array(
                                                'time' => $time_range,
                                                'available' => $free_places,
                                                'slot_key' => $key,
                                                'is_booked' => $is_booked
                                            );
                                        }
                                    }
                                }

                                $availability_by_date[$date_str] = $available_slots;
                            }

                            $last_loaded_date = (clone $datetime_now)->modify('+' . ($initial_days - 1) . ' days')->format('Y-m-d');
                            ?>
                            <div class="col-lg-12">
                                <div class="time-slots-carousel-container"
                                     data-slots-days="<?php echo implode(',', $slot_days_array); ?>"
                                     data-availability-loaded-until="<?php echo esc_attr($last_loaded_date); ?>"
                                     data-hide-booked-slots="<?php echo esc_attr($hide_booked_slots); ?>">
                                    <input id="slot" type="hidden" name="slot" value="" />
                                    <input id="listing_id" type="hidden" name="listing_id" value="<?php echo $post_info->ID; ?>" />

                                    <!-- Loading Indicator (absolute positioned) -->
                                    <div class="slot-carousel-loader">
                                        <div class="slot-carousel-loader-spinner"></div>
                                    </div>

                                    <!-- Carousel Content -->
                                    <div class="slot-carousel-content">
                                        <!-- Carousel Navigation -->
                                        <div class="slot-carousel-navigation">
                                        <button type="button" class="slot-carousel-nav-btn prev-day" disabled>
                                            <i class="fa fa-chevron-left"></i>
                                        </button>
                                        <button type="button" class="slot-carousel-nav-btn next-day">
                                            <i class="fa fa-chevron-right"></i>
                                        </button>
                                    </div>

                                    <!-- Carousel Viewport -->
                                    <div class="slot-carousel-viewport">
                                        <div class="slot-carousel-track">
                                            <?php
                                            // Display first 21 days from calculated availability
                                            for ($day_offset = 0; $day_offset < 21; $day_offset++) {
                                                $current_date = clone $datetime_now;
                                                $current_date->modify("+{$day_offset} days");
                                                $date_str = $current_date->format('Y-m-d');
                                                $day_of_week = (int)$current_date->format('N') - 1;
                                                if ($day_of_week == -1) $day_of_week = 6;

                                                $day_name = '';
                                                if ($day_offset === 0) {
                                                    $day_name = __('Today', 'listeo_core');
                                                } elseif ($day_offset === 1) {
                                                    $day_name = __('Tomorrow', 'listeo_core');
                                                } else {
                                                    // Use date_i18n for translatable day names
                                                    $day_name = date_i18n('D', $current_date->getTimestamp()) . ',';
                                                }

                                                // Use date_i18n for translatable month names
                                                $date_short = date_i18n('j M', $current_date->getTimestamp());

                                                // Get calculated available slots for this day
                                                $day_available_slots = isset($availability_by_date[$date_str]) ? $availability_by_date[$date_str] : array();
                                                $has_slots = !empty($day_available_slots);
                                                ?>
                                                <div class="slot-carousel-day-column" data-day="<?php echo $day_of_week; ?>" data-date="<?php echo $date_str; ?>">
                                                    <div class="calendar-day <?php echo $has_slots ? 'day-available' : 'day-placeholder'; ?>">
                                                        <div class="calendar-day-date">
                                                            <p class="day-name"><?php echo esc_html($day_name); ?></p>
                                                            <p class="day-date"><?php echo esc_html($date_short); ?></p>
                                                        </div>
                                                        <div class="calendar-day-slots">
                                                            <ul class="calendar-day-slots-list">
                                                                <?php
                                                                if ($has_slots) {
                                                                    $total_slots = count($day_available_slots);
                                                                    $max_visible = 10;
                                                                    $slot_index = 0;

                                                                    foreach ($day_available_slots as $slot) {
                                                                        $time_range = $slot['time'];
                                                                        $available = $slot['available'];
                                                                        $slot_key = $slot['slot_key'];
                                                                        $is_booked = isset($slot['is_booked']) ? $slot['is_booked'] : false;

                                                                        // Extract start time only
                                                                        $time_parts = explode('-', $time_range);
                                                                        $start_time = trim($time_parts[0]);

                                                                        // Hide slots after the 10th
                                                                        $hidden_class = ($slot_index >= $max_visible) ? ' hidden-slot' : '';

                                                                        // Determine slot class and attributes
                                                                        $slot_class = $is_booked ? 'calendar-slot calendar-slot-booked' : 'calendar-slot calendar-slot-available';
                                                                        $slot_disabled = $is_booked ? ' disabled' : '';
                                                                        ?>
                                                                        <li class="calendar-day-slots-list-item<?php echo $hidden_class; ?>">
                                                                            <button type="button"
                                                                                    class="<?php echo $slot_class; ?>"
                                                                                    data-slot-value="<?php echo $day_of_week . '|' . $slot_key; ?>"
                                                                                    data-slot-time="<?php echo esc_attr($time_range); ?>"<?php echo $slot_disabled; ?>>
                                                                                <?php echo esc_html($start_time); ?>
                                                                            </button>
                                                                        </li>
                                                                    <?php
                                                                        $slot_index++;
                                                                    }

                                                                    // Add "Show More" button if there are more than 10 slots
                                                                    if ($total_slots > $max_visible) {
                                                                        ?>
                                                                        <li class="calendar-day-slots-list-item show-more-item">
                                                                            <button type="button" class="calendar-slot-show-more">
                                                                                <?php echo sprintf(__('Show %d more', 'listeo_core'), $total_slots - $max_visible); ?>
                                                                            </button>
                                                                        </li>
                                                                    <?php }
                                                                } else {
                                                                    // Show placeholder slots
                                                                    for ($i = 0; $i < 6; $i++) {
                                                                        ?>
                                                                        <li class="calendar-day-slots-list-item">
                                                                            <button type="button" class="calendar-slot calendar-slot-placeholder" disabled tabindex="-1">
                                                                                -
                                                                            </button>
                                                                        </li>
                                                                    <?php }
                                                                }
                                                                ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    </div><!-- .slot-carousel-content -->
                                </div>
                            </div>
                        <?php } else {
                            // DROPDOWN LAYOUT (DEFAULT)
                            ?>
                            <div class="col-lg-12">
                                <div class="panel-dropdown time-slots-dropdown" data-slots-days=<?php echo implode(',', $slot_days_array); ?>>
                                    <a href="#" placeholder="<?php esc_html_e('Time Slots', 'listeo_core') ?>"><?php esc_html_e('Time Slots', 'listeo_core') ?></a>

                                    <div class="panel-dropdown-content timeslot-panel padding-reset">
                                        <div class="no-slots-information"><?php esc_html_e('No slots for this day', 'listeo_core') ?></div>
                                        <div class="panel-dropdown-scrollable">
                                            <input id="slot" type="hidden" name="slot" value="" />
                                            <input id="listing_id" type="hidden" name="listing_id" value="<?php echo $post_info->ID; ?>" />
                                            <?php foreach ($slots as $day => $day_slots) {
                                                if (empty($day_slots)) continue;


                                                foreach ($day_slots as $number => $slot) {
                                                    $slot = explode('|', $slot); ?>
                                                    <!-- Time Slot -->
                                                    <div class="time-slot" day="<?php echo $day; ?>">
                                                        <input type="radio" name="time-slot" id="<?php echo $day . '|' . $number; ?>" value="<?php echo $day . '|' . $number; ?>">
                                                        <label for="<?php echo $day . '|' . $number; ?>">
                                                            <p class="day"><?php echo $days_list[$day]; ?></p>
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
                                                <?php } ?>

                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else if (listeo_get_booking_type($post_id) == 'single_day') {
                    
                        $time_value = '';
                    ?>
                        <div class="col-lg-12 listeo-service-timepicker">
                            <input type="text" data-time-increment="<?php echo $timepickerIncremental; ?>" class="time-picker flatpickr-input active" value="<?php echo apply_filters('listeo_core_service_timepicker_value', $time_value); ?>" placeholder="<?php esc_html_e('Time', 'listeo_core') ?>" id="_hour" name="_hour" readonly="readonly">
                        </div>
                        <?php 
                        
                        if (listeo_listing_supports_feature('hourly_picker') && get_post_meta($post_id, '_end_hour', true)) : ?>
                            <div class="col-lg-12 listeo-service-timepicker">
                                <input type="text" class="time-picker time-picker-end-hour flatpickr-input active" placeholder="<?php esc_html_e('End Time', 'listeo_core') ?>" id="_hour_end" name="_hour_end" readonly="readonly">
                            </div>
                        <?php
                        endif;
                        $_opening_hours_status = get_post_meta($post_id, '_opening_hours_status', true);
                        $opening_hours = get_post_meta($post_id, '_opening_hours', true);

                        $availability = $this->get_rental_availability($post_info, $post_meta, $records);

                        ?>
                        <script>
                            var availableDays = <?php if ($_opening_hours_status) {
                                                    echo json_encode($opening_hours, true);
                                                } else {
                                                    echo json_encode('', true);
                                                } ?>;
                        </script>

                    <?php }

                    if (!empty($availability['disabled_dates'])) {
                    ?>
                        <script>
                            var disabledDates = <?php echo json_encode(array_values($availability['disabled_dates'])); ?>;
                        </script>
                    <?php
                    } ?>

                    <?php
                    if(listeo_listing_supports_feature('services', $post_id)): 
                        $bookable_services = listeo_get_bookable_services($post_info->ID);

                        if (!empty($bookable_services)) : ?>

                            <!-- Panel Dropdown -->
                            <div class="col-lg-12">
                                <div class="panel-dropdown booking-services">
                                    <a href="#"><?php esc_html_e('Extra Services', 'listeo_core'); ?> <span class="services-counter">0</span></a>
                                    <div class="panel-dropdown-content padding-reset">
                                        <div class="panel-dropdown-scrollable">

                                            <!-- Bookable Services -->
                                            <div class="bookable-services">
                                                <?php
                                                $i = 0;
                                                $currency_abbr = get_option('listeo_currency');
                                                $currency_postion = get_option('listeo_currency_postion');
                                                $currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
                                                foreach ($bookable_services as $key => $service) {
                                                    $i++; ?>
                                                    <div class="single-service <?php if (isset($service['bookable_quantity'])) : ?>with-qty-btns<?php endif; ?>">

                                                        <input type="checkbox" autocomplete="off" class="bookable-service-checkbox" name="_service[<?php echo sanitize_title($service['name']); ?>]" value="<?php echo sanitize_title($service['name']); ?>" id="tag<?php echo esc_attr($i); ?>" />

                                                        <label for="tag<?php echo esc_attr($i); ?>">
                                                            <h5><?php echo esc_html($service['name']); ?></h5>
                                                            <span class="single-service-price"> <?php
                                                                                                if (empty($service['price']) || $service['price'] == 0) {
                                                                                                    esc_html_e('Free', 'listeo_core');
                                                                                                } else {
                                                                                                    if ($currency_postion == 'before') {
                                                                                                        echo $currency_symbol . ' ';
                                                                                                    }
                                                                                                    $price = $service['price'];
                                                                                                    if (is_numeric($price)) {
                                                                                                        $decimals = get_option('listeo_number_decimals', 2);
                                                                                                        echo number_format_i18n($price, $decimals);
                                                                                                    } else {
                                                                                                        echo esc_html($price);
                                                                                                    }
                                                                                                    if ($currency_postion == 'after') {
                                                                                                        echo ' ' . $currency_symbol;
                                                                                                    }
                                                                                                }
                                                                                                ?></span>
                                                        </label>

                                                        <?php if (isset($service['bookable_quantity'])) : ?>
                                                            <div class="qtyButtons">
                                                                <input type="text" data-min="1" <?php if (isset($service['bookable_quantity_max']) && !empty($service['bookable_quantity_max'])) {
                                                                                                    echo 'data-max="' . $service['bookable_quantity_max'] . '"';
                                                                                                } ?> class="bookable-service-quantity" name="_service_qty[<?php echo sanitize_title($service['name']); ?>]" value="1">
                                                            </div>
                                                        <?php else : ?>
                                                            <input type="hidden" class="bookable-service-quantity" name="_service_qty[<?php echo sanitize_title($service['name']); ?>]" value="1">
                                                        <?php endif; ?>

                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="clearfix"></div>
                                            <!-- Bookable Services -->


                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Panel Dropdown / End -->
                        <?php
                        endif;
                    endif;
                
                    $max_guests = get_post_meta($post_info->ID, "_max_guests", true);
                    $min_guests = get_post_meta($post_info->ID, "_min_guests", true);
                    $children = get_post_meta($post_info->ID, "_children", true);
                    $max_children = get_post_meta($post_info->ID, "_max_children", true);
                    $animals = get_post_meta($post_info->ID, "_animals", true);
                    if (empty($min_guests)) {
                        $min_guests = 1;
                    }
                    $count_per_guest = get_post_meta($post_info->ID, "_count_per_guest", true);
                    if (get_option('listeo_remove_guests')) {
                        $max_guests = 1;
                    }
                    ?>
                    <!-- Panel Dropdown -->
                    <div class="col-lg-12" <?php if ($max_guests == 1) {
                                                echo 'style="display:none;"';
                                            } ?>>
                        <div data-maxguests="<?php echo esc_attr($max_guests); ?>" class="panel-dropdown panel-guests-dropdown">
                            <a href="#"><?php esc_html_e('Guests', 'listeo_core') ?> <span class="qtyTotal" name="qtyTotal">1</span></a>
                            <div class="panel-dropdown-content <?php if ($children || $animals) : ?>panel-with-children<?php endif; ?>" style="width: 269px;">
                                <!-- Quantity Buttons -->
                                <?php if (!$children) : ?>
                                    <div class="qtyButtons">
                                        <div class="qtyTitle"><?php esc_html_e('Guests', 'listeo_core') ?></div>
                                        <input type="text" name="qtyInput" data-max="<?php echo esc_attr($max_guests); ?>" data-min="<?php echo esc_attr($min_guests); ?>" class="adults <?php if ($count_per_guest) echo 'count_per_guest'; ?>" value="<?php echo $min_guests; ?>">
                                    </div>
                                <?php endif; ?>

                                <!-- Children Options -->
                                <?php if ($children) : ?>
                                    <div class="qtyButtons">
                                        <div class="qtyTitle"><?php esc_html_e('Adults', 'listeo_core') ?></div>
                                        <input type="text" name="qtyInput" data-max="<?php echo esc_attr($max_guests); ?>" data-min="<?php echo esc_attr($min_guests); ?>" class="adults <?php if ($count_per_guest) echo 'count_per_guest'; ?>" value="<?php echo $min_guests; ?>">
                                    </div>
                                    <div class="qtyButtons children-options">
                                        <div class="qtyTitle">
                                            <?php esc_html_e('Children', 'listeo_core') ?>
                                            <span><?php esc_html_e('Ages 2–12', 'listeo_core') ?></span>
                                        </div>
                                        <input type="text" name="childrenQtyInput" data-max="<?php echo esc_attr($max_children); ?>" data-min="0" class="children" value="0">
                                    </div>
                                    <div class="qtyButtons infants-options">

                                        <div class="qtyTitle"><?php esc_html_e('Infants', 'listeo_core') ?>
                                            <span><?php esc_html_e('Under 2', 'listeo_core') ?></span>
                                        </div>
                                        <input type="text" name="infantsQtyInput" data-max="5" data-min="0" class="infants" value="0">
                                    </div>
                                <?php endif; ?>

                                <!-- Animals Options -->
                                <?php if ($animals) : ?>
                                    <div class="qtyButtons animals-options">
                                        <div class="qtyTitle"><?php esc_html_e('Animals', 'listeo_core') ?></div>
                                        <input type="text" name="animalsQtyInput" data-max="<?php echo esc_attr($animals); ?>" data-min="0" class="animals" value="0">
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                    <!-- Panel Dropdown / End -->

                <?php } //eof !if event 
                ?>

                <?php if (listeo_get_booking_type($post_id) == 'tickets') {
                    /**
                     * Filter to replace the entire ticket-input HTML in the booking widget.
                     * Booking Plus returns a multi-tier ticket selector when ticket types are configured.
                     *
                     * @param string $html      Override HTML. Empty string to use legacy single-ticket UI.
                     * @param int    $post_id   Listing ID.
                     * @param object $post_info Listing post object.
                     */
                    $bp_widget_html = apply_filters( 'listeo_event_booking_widget_html', '', $post_info->ID, $post_info );
                    if ( ! empty( $bp_widget_html ) ) {
                        echo $bp_widget_html; // already escaped by callee
                    } else {
                    $max_guests     = (int) get_post_meta($post_info->ID, "_max_guests", true);
                    $max_tickets     = (int) get_post_meta($post_info->ID, "_event_tickets", true);
                    $sold_tickets     = (int) get_post_meta($post_info->ID, "_event_tickets_sold", true);
                    $av_tickets     = $max_tickets - $sold_tickets;
                    if ($av_tickets > $max_guests && $max_guests > 0) {
                        $av_tickets = $max_guests;
                    }

                    // Apply max tickets per booking limit from listing meta if set
                    $max_tickets_per_booking = (int) get_post_meta($post_info->ID, "_max_tickets_per_booking", true);
                    $final_max_tickets = $av_tickets;
                    if ($max_tickets_per_booking > 0 && ($final_max_tickets > $max_tickets_per_booking || $final_max_tickets == 0)) {
                        $final_max_tickets = $max_tickets_per_booking;
                    }

                ?><input type="hidden" id="date-picker" readonly="readonly" class="date-picker-listing-<?php echo listeo_get_booking_type($post_id); ?>" autocomplete="off" placeholder="<?php esc_attr_e('Date', 'listeo_core'); ?>" value="<?php echo $post_meta['_event_date'][0]; ?>" listing_type="<?php echo listeo_get_booking_type($post_id); ?>" />

                    <?php if ($show_tickets_left == 'on' && $max_tickets > 0) : ?>
                        <div class="tickets-remaining-notice">
                                <?php echo sprintf(
                                    _n(
                                        '%d ticket remaining',
                                        '%d tickets remaining',
                                        $av_tickets,
                                        'listeo_core'
                                    ),
                                    $av_tickets
                                ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="col-lg-12 tickets-panel-dropdown">
                        <div class="panel-dropdown">
                            <a href="#"><?php esc_html_e('Tickets', 'listeo_core') ?> <span class="qtyTotal" name="qtyTotal">1</span></a>
                            <div class="panel-dropdown-content" style="width: 269px;">
                                <!-- Quantity Buttons -->
                                <div class="qtyButtons">
                                    <div class="qtyTitle"><?php esc_html_e('Tickets', 'listeo_core') ?></div>
                                    <input type="text" name="qtyInput" <?php if ($max_tickets > 0) { ?>data-max="<?php echo esc_attr($final_max_tickets); ?>" <?php } ?> id="tickets" value="1">
                                </div>

                            </div>
                        </div>
                    </div>
                    <?php $bookable_services = listeo_get_bookable_services($post_info->ID);

                    if (!empty($bookable_services)) : ?>

                        <!-- Panel Dropdown -->
                        <div class="col-lg-12">
                            <div class="panel-dropdown booking-services">
                                <a href="#"><?php esc_html_e('Extra Services', 'listeo_core'); ?> <span class="services-counter">0</span></a>
                                <div class="panel-dropdown-content padding-reset">
                                    <div class="panel-dropdown-scrollable">

                                        <!-- Bookable Services -->
                                        <div class="bookable-services">
                                            <?php
                                            $i = 0;
                                            $currency_abbr = get_option('listeo_currency');
                                            $currency_postion = get_option('listeo_currency_postion');
                                            $currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
                                            foreach ($bookable_services as $key => $service) {
                                                $i++; ?>
                                                <div class="single-service">
                                                    <input type="checkbox" class="bookable-service-checkbox" name="_service[<?php echo sanitize_title($service['name']); ?>]" value="<?php echo sanitize_title($service['name']); ?>" id="tag<?php echo esc_attr($i); ?>" />

                                                    <label for="tag<?php echo esc_attr($i); ?>">
                                                        <h5><?php echo esc_html($service['name']); ?></h5>
                                                        <span class="single-service-price"> <?php
                                                                                            if (empty($service['price']) || $service['price'] == 0) {
                                                                                                esc_html_e('Free', 'listeo_core');
                                                                                            } else {
                                                                                                if ($currency_postion == 'before') {
                                                                                                    echo $currency_symbol . ' ';
                                                                                                }
                                                                                                echo esc_html($service['price']);
                                                                                                if ($currency_postion == 'after') {
                                                                                                    echo ' ' . $currency_symbol;
                                                                                                }
                                                                                            }
                                                                                            ?></span>
                                                    </label>

                                                    <?php if (isset($service['bookable_quantity'])) : ?>
                                                        <div class="qtyButtons">
                                                            <input type="text" class="bookable-service-quantity" name="_service_qty[<?php echo sanitize_title($service['name']); ?>]" data-max="" class="" value="1">
                                                        </div>
                                                    <?php else : ?>
                                                        <input type="hidden" class="bookable-service-quantity" name="_service_qty[<?php echo sanitize_title($service['name']); ?>]" data-max="" class="" value="1">
                                                    <?php endif; ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                        <div class="clearfix"></div>
                                        <!-- Bookable Services -->


                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Panel Dropdown / End -->
                    <?php
                    endif; ?>
                    <!-- Panel Dropdown / End -->
                <?php } // end else (legacy single-ticket UI)
                } // end if tickets ?>

                <?php if (!get_option('listeo_remove_coupons')) : ?>
                    <div class="col-lg-12 coupon-widget-wrapper">
                        <a id="listeo-coupon-link" href="#"><?php esc_html_e('Have a coupon?', 'listeo_core'); ?></a>
                        <div class="coupon-form">

                            <input type="text" name="apply_new_coupon" class="input-text" id="apply_new_coupon" value="" placeholder="<?php esc_html_e('Coupon code', 'listeo_core'); ?>">
                            <a href="#" class="button listeo-booking-widget-apply_new_coupon">
                                <div class="loadingspinner"></div><span class="apply-coupon-text"><?php esc_html_e('Apply', 'listeo_core'); ?></span>
                            </a>

                        </div>
                        <div id="coupon-widget-wrapper-output">
                            <div class="notification error closeable"></div>
                            <div class="notification success closeable" id="coupon_added"><?php esc_html_e('This coupon was added', 'listeo_core'); ?></div>
                        </div>
                        <div id="coupon-widget-wrapper-applied-coupons">

                        </div>
                    </div>

                    <input type="hidden" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_html_e('Coupon code', 'listeo_core'); ?>">
                <?php endif; ?>
        </div>

        <?php
        // Allow plugins to add content before submit button (e.g., additional fields)
        do_action( 'listeo_booking_form_before_submit', $post_info->ID );
        ?>

        <!-- Book Now -->
        <input type="hidden" id="listing_type" value="<?php echo listeo_get_booking_type($post_info->ID); ?>" />
        <input type="hidden" id="listing_id" value="<?php echo $post_info->ID; ?>" />
        <input id="booking" type="hidden" name="value" value="booking_form" />
        <?php
        if (listeo_get_booking_type($post_info->ID) == 'tickets') {
            $book_btn = esc_html__('Make a Reservation', 'listeo_core');
        } else {
            if (get_post_meta($post_info->ID, '_instant_booking', true)) {
                $book_btn = esc_html__('Book Now', 'listeo_core');
            } else {
                $book_btn = esc_html__('Request Booking', 'listeo_core');
            }
        }
        if (is_user_logged_in()) :



            $post_id = $queried_object->ID;
            $author_id = get_post_field('post_author', $post_id);
            $current_user = wp_get_current_user();
            $user_id = get_current_user_id();
            $roles = $current_user->roles;
            $role = array_shift($roles);
            if (get_option('listeo_owners_can_book') != 'on' && in_array($role, array('owner', 'seller'))) { ?>
                <a href="#" class="button fullwidth white margin-top-5"><span class="book-now-text"><?php echo esc_html__("Please use guest account.", 'listeo_core');  ?></span></a>
            <?php } else {  ?>
                <a href="#" class="button book-now fullwidth margin-top-5">
                    <div class="loadingspinner"></div><span class="book-now-text"><?php echo $book_btn; ?></span>
                </a>

            <?php } ?>




            <?php else :

            $booking_without_login = get_option('listeo_booking_without_login', 'off');

            if ($booking_without_login == 'on') { ?>
                <a href="#" class="button book-now fullwidth margin-top-5">
                    <div class="loadingspinner"></div><span class="book-now-text"><?php echo $book_btn; ?></span>
                </a>
                <?php } else {
                $popup_login = get_option('listeo_popup_login', 'ajax');
                if ($popup_login == 'ajax') { ?>

                    <a href="#sign-in-dialog" class="button fullwidth margin-top-5 popup-with-zoom-anim book-now-notloggedin">
                        <div class="loadingspinner"></div><span class="book-now-text"><?php esc_html_e('Login to Book', 'listeo_core') ?></span>
                    </a>

                <?php } else {

                    $login_page = get_option('listeo_profile_page');
                    $current_listing_url = get_permalink();
                    $login_url = add_query_arg('redirect_to', urlencode($current_listing_url), get_permalink($login_page));
                    ?>
                    <a href="<?php echo esc_url($login_url); ?>" class="button fullwidth margin-top-5 book-now-notloggedin">
                        <div class="loadingspinner"></div><span class="book-now-text"><?php esc_html_e('Login To Book', 'listeo_core') ?></span>
                    </a>
            <?php }
            } ?>


        <?php endif; ?>

        <?php if (listeo_get_booking_type($post_info->ID) == 'tickets' && isset($post_meta['_event_date'][0])) { ?>
            <div class="booking-event-date">
                <strong><?php esc_html_e('Event date', 'listeo_core'); ?></strong>
                <?php
                    $_event_datetime = $post_meta['_event_date'][0];
                    $_event_date = list($_event_datetime) = explode(' -', $_event_datetime);

                    // Get reliable ISO date from timestamp or parse the raw date
                    $_event_timestamp = get_post_meta($post_info->ID, '_event_date_timestamp', true);
                    if ( empty($_event_timestamp) ) {
                        $_event_timestamp = Listeo_Core_Post_Types::parse_event_date_to_timestamp($_event_date[0]);
                    }
                    $_event_iso = $_event_timestamp ? date('Y-m-d', $_event_timestamp) : '';
                ?>
                <span data-date-iso="<?php echo esc_attr($_event_iso); ?>"><?php echo esc_html($_event_date[0]); ?></span>
            </div>
        <?php } ?>

        <?php
        $currency_abbr = get_option('listeo_currency');
        $currency_postion = get_option('listeo_currency_postion');
        $currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr, false);
        ?>
        <div class="booking-estimated-cost" <?php if (listeo_get_booking_type($post_info->ID) != 'tickets') { ?>style="display: none;" <?php } ?>>
            <?php if (listeo_get_booking_type($post_info->ID) == 'tickets') {
                $reservation_fee = (float) get_post_meta($post_info->ID, '_reservation_price', true);
                $normal_price = (float) get_post_meta($post_info->ID, '_normal_price', true);

                $event_default_price = $reservation_fee + $normal_price;
            }  ?>
            <?php
            // Repeatable fees engine — render an itemized list with
            // scaled amounts (per_ticket, percent of base, etc.) and a
            // checkbox for any row flagged `optional`. The Step 1 widget
            // shows the listing's default total (no tickets selected
            // yet); per-ticket fees here use a default of 1 ticket.
            $fee_context = array(
                'listing_type' => get_post_meta( $post_info->ID, '_listing_type', true ),
                'tickets'      => 1,
                'guests'       => 1,
                'subtotal'     => isset( $event_default_price ) ? (float) $event_default_price : 0.0,
            );
            $_all_optional_ids = array();
            if ( function_exists( 'listeo_get_listing_fees' ) ) {
                foreach ( listeo_get_listing_fees( $post_info->ID ) as $_f ) {
                    if ( ! empty( $_f['optional'] ) ) {
                        $_all_optional_ids[] = $_f['id'];
                    }
                }
            }
            $fee_context['accepted_optional_fees'] = $_all_optional_ids;

            $applicable_fees = function_exists( 'listeo_get_applicable_listing_fees' )
                ? listeo_get_applicable_listing_fees( $post_info->ID, $fee_context )
                : array();

            $fees_total = 0.0;
            if ( ! empty( $applicable_fees ) ) {
                $currency_abbr    = get_option( 'listeo_currency' );
                $currency_postion = get_option( 'listeo_currency_postion' );
                $currency_symbol  = Listeo_Core_Listing::get_currency_symbol( $currency_abbr );
                $decimals         = (int) get_option( 'listeo_number_decimals', 2 );
                $currency_args    = array(
                    'symbol'   => $currency_symbol,
                    'position' => $currency_postion,
                    'decimals' => $decimals,
                );
                echo "<ul id='booking-mandatory-fees'>";
                foreach ( $applicable_fees as $fee ) {
                    $line        = listeo_format_fee_line( $fee, $fee_context, $currency_args );
                    $is_optional = ! empty( $fee['optional'] );
                    $fees_total += (float) $line['amount'];
                    ?>
                    <li class="listeo-fee-row<?php echo $is_optional ? ' listeo-fee-row-optional' : ''; ?>" data-fee-id="<?php echo esc_attr( $fee['id'] ); ?>" data-fee-amount="<?php echo esc_attr( $line['amount'] ); ?>">
                        <p>
                            <?php if ( $is_optional ) : ?>
                                <label class="listeo-optional-fee-toggle">
                                    <input type="checkbox" class="listeo-optional-fee-checkbox" name="optional_fees[]" value="<?php echo esc_attr( $fee['id'] ); ?>" checked>
                                    <?php echo esc_html( $line['label'] ); ?>
                                </label>
                            <?php else : ?>
                                <?php echo esc_html( $line['label'] ); ?>
                            <?php endif; ?>
                        </p>
                        <strong><?php echo esc_html( $line['amount_formatted'] ); ?></strong>
                    </li>
                    <?php
                }
                echo "</ul>";
            }

            // Roll fees into the visible total so the displayed amount
            // matches the itemized list above. The data-price attr is
            // the source of truth for the JS opt-out handler below.
            if ( isset( $event_default_price ) ) {
                $event_default_price += $fees_total;
            }
            ?>

            <!--
                Itemized breakdown placeholder. Server-side initial
                render uses the existing `#booking-mandatory-fees` ul
                above (static, fees-only). Once the customer interacts
                and the AJAX recalculation kicks in,
                `bookings.js::renderBookingBreakdown()` populates this
                ul with the full breakdown (accommodation, reservation
                fee, fees, services, pets, coupon) and hides the static
                mandatory-fees ul. Empty until then.
            -->
            <ul class="booking-price-breakdown" id="booking-price-breakdown" style="display:none;"></ul>

            <strong><?php esc_html_e('Total Cost', 'listeo_core'); ?></strong>
            <span class="listeo-widget-total-cost" data-price="<?php if (isset($event_default_price)) {
                                    echo esc_attr($event_default_price);
                                } ?>">
                <?php if ($currency_postion == 'before') {
                    echo $currency_symbol;
                } ?>
                <?php
                if (listeo_get_booking_type($post_info->ID) == 'tickets') {

                    echo isset( $event_default_price ) ? number_format_i18n( $event_default_price, (int) get_option( 'listeo_number_decimals', 2 ) ) : '0';
                } else echo '0'; ?>
                <?php if ($currency_postion == 'after') {
                    echo $currency_symbol;
                } ?>
            </span>
        </div>

        <div class="booking-estimated-discount-cost" style="display: none;">

            <strong><?php esc_html_e('Final Cost', 'listeo_core'); ?></strong>
            <span>
                <?php if ($currency_postion == 'before') {
                    echo $currency_symbol;
                } ?>

                <?php if ($currency_postion == 'after') {
                    echo $currency_symbol;
                } ?>
            </span>
        </div>
        <div class="booking-notice-message"> </div>
        <div class="booking-error-message" style="display: none;" data-default-message="<?php esc_html_e('Unfortunately this request can\'t be processed. Try different dates please.', 'listeo_core'); ?>">
            <?php if (listeo_get_booking_type($post_info->ID) == 'single_day' && !$slots) {
                esc_html_e('Unfortunately we are closed at selected hours. Try different please.', 'listeo_core');
            } else {
                esc_html_e('Unfortunately this request can\'t be processed. Try different dates please.', 'listeo_core');
            } ?>
        </div>
        <?php do_action('listeo_after_booking_form', $post_id); ?>
        </form>
<?php


        echo $after_widget;

        $content = ob_get_clean();

        echo $content;

        //		$this->cache_widget($args, $content);
    }
}

register_widget('Listeo_Core_Booking_Widget');
