<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Listeo_Core_Listing class
 */
class Listeo_Core_iCal
{

    private static $_instance = null;
    private static $bookings = null;


    /**
     * Allows for accessing single instance of class. Class should only be constructed once per call.
     *
     * @return self Main instance.
     * @since  1.26
     * @static
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct()
    {

        Listeo_Core_iCal::$bookings = new Listeo_Core_Bookings_Calendar;

        add_action('wp_ajax_add_new_listing_ical', array($this, 'add_new_listing_ical'));
        add_action('wp_ajax_add_remove_listing_ical', array($this, 'add_remove_listing_ical'));
        add_action('wp_ajax_refresh_listing_import_ical', array($this, 'refresh_listing_import_ical'));

        // set schedules to generate ical files
        if (! wp_next_scheduled('listeo_update_booking_icals')) {
            wp_schedule_event(time(), '30min', 'listeo_update_booking_icals');
        }

        add_action('listeo_update_booking_icals', array($this, 'listeo_update_booking_icals'));
    }


    function add_new_listing_ical()
    {
        // Security: Check nonce and user capabilities
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_ical_nonce')) {
        //     wp_die(__('Security check failed', 'listeo_core'));
        // }

        // if (!current_user_can('edit_posts')) {
        //     wp_die(__('Insufficient permissions', 'listeo_core'));
        // }

        // Sanitize input data
        $listing_id   = absint($_POST['listing_id'] ?? 0);
        $name         = sanitize_text_field($_POST['name'] ?? '');
        $url          = esc_url_raw($_POST['url'] ?? '');
        $force_update = sanitize_text_field($_POST['force_update'] ?? 'false');

        if (empty($name) || empty($url) || !$listing_id) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__("Please fill the form fields", "listeo_core");
            wp_send_json($result);
            die();
        }

        // // Security: Verify user can edit this specific listing
        // if (!current_user_can('edit_post', $listing_id)) {
        //     $result['type']         = 'error';
        //     $result['notification'] = esc_html__('You do not have permission to edit this listing', 'listeo_core');
        //     wp_send_json($result);
        //     die();
        // }

        $extension = pathinfo($url, PATHINFO_EXTENSION);
        $extension = explode('?', $extension);
        $name = sanitize_title($name);

        // Enhanced URL validation and SSRF protection
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__("Please provide valid URL", "listeo_core");
            wp_send_json($result);
            die();
        }

        // SSRF Protection: Check for allowed domains and protocols
        $parsed_url = parse_url($url);
        if (!$parsed_url || !in_array($parsed_url['scheme'], ['http', 'https'])) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__('Only HTTP and HTTPS URLs are allowed', 'listeo_core');
            wp_send_json($result);
            die();
        }

        // Block internal/private IPs
        $host = $parsed_url['host'];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $result['type']         = 'error';
                $result['notification'] = esc_html__('Private/internal IP addresses are not allowed', 'listeo_core');
                wp_send_json($result);
                die();
            }
        }

        // Block localhost and common internal domains
        $blocked_hosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'metadata.google.internal'];
        if (in_array(strtolower($host), $blocked_hosts)) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__('This URL is not allowed', 'listeo_core');
            wp_send_json($result);
            die();
        }

        // Enhanced file type validation
        $url_lower = strtolower($url);
        $allowed_extensions = ['ical', 'ics', 'ifb', 'icalendar'];
        $allowed_domains = apply_filters('listeo_ical_allowed_domains', [
            'calendar.google.com',
            'outlook.live.com',
            'outlook.office365.com',
            'ical.mac.com',
            'calendars.icloud.com',
            'airbnb.com',
            'airbnb.nl',
            'airbnb.co.uk',
            'airbnb.ca',
            'airbnb.fr',
            'airbnb.de',
            'booking.com',
            'airtable.com',
            'vrbo.com',
            'homeaway.com'
        ]);

        $extension_valid = in_array($extension[0], $allowed_extensions);
        $domain_valid = false;
        foreach ($allowed_domains as $domain) {
            if (strpos($host, $domain) !== false) {
                $domain_valid = true;
                break;
            }
        }
        $content_valid = strpos($url_lower, 'calendar') !== false ||
                        strpos($url_lower, 'ical') !== false;

        if (!$extension_valid && !$domain_valid && !$content_valid) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__('URL does not appear to be a valid iCal source', 'listeo_core');
            wp_send_json($result);
            die();
        }


        $icals_array = array();
        $temp_array  = array();

        $new_ical = array(
            'name'         => $name,
            'url'          => $url,
            'force_update' => $force_update,
        );

        $temp_array['url']             = esc_url_raw($url);
        $temp_array['name']            = esc_html($name);
        $temp_array['force_update']    = esc_html($force_update);
        $temp_array['bookings_author'] = get_current_user_id();

        $icals_array[] = $temp_array;
        $current_icals = get_post_meta($listing_id, 'listeo_ical_imports', true);

        if (is_array($current_icals)) {
            //todo check if the same link was already added
            if (in_array($name, array_column($current_icals, 'name'))) {
                $result['type']         = 'error';
                $result['notification'] = esc_html__("It look's like you've already calendar with that name", "listeo_core");
                wp_send_json($result);

                die();
            } else if (in_array($url, array_column($current_icals, 'url'))) {

                $result['type']         = 'error';
                $result['notification'] = esc_html__("It look's like you've already added that calendar URL", "listeo_core");
                wp_send_json($result);

                die();
            } else {
                $current_icals = array_merge($current_icals, $icals_array);
            }
        } else {
            $current_icals = $icals_array;
        }

        $action = update_post_meta($listing_id, 'listeo_ical_imports', $current_icals);

        if ($action) {
            $output   = $this->get_saved_icals($listing_id);
            $imported = $this->import_bookings_from_ical($temp_array, $listing_id);
            /**
             * $imported = [
             *      imported'               => (int)
             *      skipped_already_booked  => (int)
             *      skipped_missing_slot    => (int)
             *      skipped_server_error    => (int)
             *      skipped_past            => (int)
             */

            if (0 < $imported['imported']) {
                //$imported_info = sprintf( __( "We've successfully imported %s events", 'listeo_core' ), $imported );
                $imported_info = sprintf(_n("We've successfully imported %s event", "We've successfully imported %s events", $imported['imported'], 'listeo_core'), $imported['imported']);
            } else {
                $imported_info = esc_html__("No events imported", "listeo_core");
            }

            if ($output) {
                $result['type']         = 'success';
                $result['output']       = $output;
                $result['notification'] = $imported_info;
            } else {
                $result['type']         = 'error';
                $result['output']       = $output;
                $result['notification'] = $imported_info;
            }
        } else {
            $result['type']         = 'error';
            $result['notification'] = esc_html__('There was problem updating the field.', 'listeo_core');
        }

        wp_send_json($result);

        die();
    }

    function add_remove_listing_ical()
    {
        // Security: Check nonce and user capabilities
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_ical_nonce')) {
        //     wp_die(__('Security check failed', 'listeo_core'));
        // }

        // if (!current_user_can('edit_posts')) {
        //     wp_die(__('Insufficient permissions', 'listeo_core'));
        // }

        // Sanitize input data
        $listing_id = absint($_POST['listing_id'] ?? 0);
        $index      = absint($_POST['index'] ?? 0);

        if (!$listing_id) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__('Invalid listing ID', 'listeo_core');
            wp_send_json($result);
            die();
        }

        // Security: Verify user can edit this specific listing
        // if (!current_user_can('edit_post', $listing_id)) {
        //     $result['type']         = 'error';
        //     $result['notification'] = esc_html__('You do not have permission to edit this listing', 'listeo_core');
        //     wp_send_json($result);
        //     die();
        // }

        $current_icals = get_post_meta($listing_id, 'listeo_ical_imports', true);

        $removed_ical = $current_icals[$index];

        unset($current_icals[$index]);

        $action = update_post_meta($listing_id, 'listeo_ical_imports', $current_icals);

        $output  = $this->get_saved_icals($listing_id);
        $removed = $this->remove_from_ical($removed_ical, $listing_id); // false or int (number of removed)

        if ($removed) {

            $removed_info = sprintf(_n("We've successfully removed this calendar with %s event", "We've successfully removed this calendar with %s events", $removed, 'listeo_core'), $removed);
        } else {

            $removed_info = esc_html__("Calendar was removed, no events deleted", "listeo_core");
        }
        if ($action) {

            $result['type']         = 'success';
            $result['output']       = $output;
            $result['notification'] = $removed_info;
        } else {
            $result['type']         = 'error';
            $result['output']       = $output;
            $result['notification'] = $removed_info;
        }

        wp_send_json($result);

        die();
    }

    function refresh_listing_import_ical()
    {
        // Security: Check nonce and user capabilities
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_ical_nonce')) {
        //     wp_die(__('Security check failed', 'listeo_core'));
        // }

        // if (!current_user_can('edit_posts')) {
        //     wp_die(__('Insufficient permissions', 'listeo_core'));
        // }

        // Sanitize input data
        $listing_id = absint($_POST['listing_id'] ?? 0);

        if (!$listing_id) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__('Invalid listing ID', 'listeo_core');
            wp_send_json($result);
            die();
        }

        // Security: Verify user can edit this specific listing
        // if (!current_user_can('edit_post', $listing_id)) {
        //     $result['type']         = 'error';
        //     $result['notification'] = esc_html__('You do not have permission to edit this listing', 'listeo_core');
        //     wp_send_json($result);
        //     die();
        // }

        try {
            // A manual refresh from the owner panel must bypass the ETag/304 HTTP cache.
            // import_events() reuses each feed's saved force_update flag (default 'false'),
            // which makes import_bookings_from_ical() honor a remote "304 Not Modified" and
            // skip the import entirely — so a non-forced manual refresh silently does nothing
            // whenever the calendar provider reports no change. The iCal Manager admin tool
            // already forces the update; an explicit owner-side "refresh" click should behave
            // the same way. Cron (listeo_update_booking_icals) intentionally stays non-forced
            // so the 30-min auto-sync still benefits from ETag caching.
            $icals_list = get_post_meta($listing_id, 'listeo_ical_imports', true);
            if (is_array($icals_list) && !empty($icals_list)) {
                foreach ($icals_list as $feed) {
                    $feed['force_update'] = 'true';
                    $this->import_bookings_from_ical($feed, $listing_id);
                }
            }
            $result['type']         = 'success';
            $result['notification'] = esc_html__('Events from calendars were imported', 'listeo_core');
        } catch (Exception $e) {
            $result['type']         = 'error';
            $result['notification'] = esc_html__('There was error with the import, please try again', 'listeo_core');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Listeo iCal import error: ' . $e->getMessage());
            }
        }

        wp_send_json($result);
        die();
    }

    public static function get_saved_icals($listing_id)
    {

        $icals_list = get_post_meta($listing_id, 'listeo_ical_imports', true);

        ob_start();

        if (! empty($icals_list)) : ?>
            <h4><?php esc_html_e('Imported Calendars', 'listeo_core'); ?></h4>
            <ul>
                <?php
                $i = 0;
                foreach ($icals_list as $key => $value) { ?>
                    <li><span><?php echo esc_html($value['name']); ?></span>
                        <small><?php echo url_shorten($value['url']); ?></small>
                        <a href="#" data-listing-id="<?php echo esc_attr($listing_id); ?>"
                            data-remove="<?php echo esc_attr($key) ?>"
                            class="ical-remove"><?php esc_html_e('Remove', 'listeo_core'); ?></a>
                    </li>
                <?php $i++;
                } ?>
            </ul>
            <a href="#" data-listing-id="<?php echo esc_attr($listing_id); ?>"
                class="update-all-icals"><?php esc_html_e('Import manually all calendars now', 'listeo_core'); ?><i
                    class="tip"
                    data-tip-content="<?php esc_html_e('All calendars are automaticaly refreshed every 30 minutes', 'listeo_core'); ?>"></i></a>
<?php
        endif;
        $list = ob_get_contents();
        ob_end_clean();

        return $list;
    }


    public static function get_ical_export_url($id)
    {

        $ical_page = get_option('listeo_ical_page');

        if ($ical_page) {

            $url  = get_permalink($ical_page);
            $slug = get_post_field('post_name', $id);
            $hash = bin2hex($id . '|' . $slug);

            return esc_url_raw(add_query_arg('calendar', $hash, $url));
        } else {
            return false;
        }
    }


    public static function generate_event($value)
    {

        $details = json_decode($value['comment']);
        $comment = '';
        $id      = $value['listing_id'];
        if (isset($details->first_name) || isset($details->last_name)) :
            $comment .= esc_html__('Name: ');
            if (isset($details->first_name)) {
                $comment .= $details->first_name . ' ';
            }
            if (isset($details->last_name)) {
                $comment .= $details->last_name . ' ';
            }
            $comment .= ' ';
        endif;
        if (isset($details->email)) : $comment .= esc_html__('Email: ') . $details->email . ' ';
        endif;
        if (isset($details->phone)) : $comment .= esc_html__('Phone: ') . $details->phone . ' ';
        endif;

        $start_date = $value['date_start'];
        $end_date   = $value['date_end'];

        if (get_option('listeo_ical_timezone')) {

            $timestamp = date_i18n('Ymd\THis', time(), true);

            if ($start_date != '') {
                $start_date = strtotime($start_date);
                $start_date = date("Ymd\THis", $start_date);
            }

            if ($end_date != '') {
                $end_date = strtotime($end_date);
                $end_date = date('Ymd\THis', $end_date);
            } else {
                $end_date = date("Ymd\THis", $start_date + (1 * 60 * 60)); // 1 hour after
            }
        } else {
            //create a UTC equivalent time for all events irrespective of timezone


            $timestamp = date_i18n('Ymd\THis\Z', time(), true);

            if ($start_date != '') {
                $start_date = strtotime($start_date);
                $start_date = date("Ymd\THis\Z", $start_date);
            }

            if ($end_date != '') {
                $end_date = strtotime($end_date);
                $end_date = date('Ymd\THis\Z', $end_date);
            } else {
                $end_date = date("Ymd\THis\Z", $start_date + (1 * 60 * 60)); // 1 hour after
            }
        }


        $event = "BEGIN:VEVENT
SUMMARY:" . get_the_title($id) . "
DESCRIPTION:" . listeo_escape_string($comment) . "
DTSTART:" . $start_date . "
DTEND:" . $end_date . "
UID:" . md5(uniqid(mt_rand(), true)) . "@" . $_SERVER['HTTP_HOST'] . "
DTSTAMP:" . $timestamp . "
END:VEVENT
";

        // $event = 0;
        return $event;
    }


    public static function get_ical_events($id)
    {

        $ical         = false;
        $listing_type = listeo_get_booking_type($id);
        // Check for both new booking types and old listing type names (backward compatibility)
        $allowed_types = array('rental', 'service', 'single_day', 'date_range', 'tickets', 'event');
        if (in_array($listing_type, $allowed_types)) {

            $eol  = "\r\n";
            $post = get_post($id);

            $booking = array();

            // get reservations for next 10 years to make unable to set it in datapicker
            // Check for both 'rental' (old) and 'date_range' (new)
            if ($listing_type == 'rental' || $listing_type == 'date_range') {
                $records = self::$bookings->get_bookings(
                    date('Y-m-d H:i:s', strtotime('-1 year')),
                    date('Y-m-d H:i:s', strtotime('+2 years')),
                    array(
                        'listing_id' => $id,
                        'type'       => 'reservation',
                        'status'     => 'icalimports', //filter out other imports
                    ),
                    $by = 'booking_date',
                    $limit = '',
                    $offset = '',
                    $all = '',
                    $listing_type = 'rental' // Keep as 'rental' for get_bookings() parameter
                );
            } else {
                $records = self::$bookings->get_bookings(
                    date('Y-m-d H:i:s', strtotime('-1 year')),
                    date('Y-m-d H:i:s', strtotime('+2 years')),
                    array(
                        'listing_id' => $id,
                        'type'       => 'reservation',
                        'status'     => 'icalimports', //filter out other imports
                    ),
                    'booking_date',
                    $limit = '',
                    $offset = ''
                );
            }

            ob_start();
            foreach ($records as $key => $value) {

                echo self::generate_event($value);
            }
            $ical = ob_get_contents();
            ob_end_clean();
        }

        return $ical;
    }


    function listeo_update_booking_icals()
    {
        // Get batch size from options (default 10)
        $batch_size = get_option('listeo_ical_batch_size', 10);
        $processed_option = 'listeo_ical_last_processed_batch';
        $last_processed = get_option($processed_option, 0);

        $args = array(
            'post_type'      => 'listing',
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $last_processed,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'listeo_ical_imports',
                    'compare' => 'EXISTS'
                )
            ),
            'orderby'        => 'ID',
            'order'          => 'ASC'
        );

        $query = new WP_Query($args);
        $posts = $query->get_posts();

        if (empty($posts)) {
            // Reset to beginning if no more posts
            update_option($processed_option, 0);
            return;
        }

        $processed_count = 0;
        $max_execution_time = 25; // Leave 5 seconds buffer for 30-second cron
        $start_time = time();

        foreach ($posts as $post_id) {
            // Check execution time limit
            if ((time() - $start_time) >= $max_execution_time) {
                break;
            }

            try {
                $this->import_events($post_id);
                $processed_count++;

                // Add small delay to prevent overwhelming the server
                usleep(100000); // 0.1 second

            } catch (Exception $e) {
                // Log error but continue with other imports
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Listeo iCal cron error for listing {$post_id}: " . $e->getMessage());
                }
            }
        }

        // Update the processed count
        $new_offset = $last_processed + $processed_count;
        update_option($processed_option, $new_offset);

        // Log progress
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Listeo iCal cron: processed {$processed_count} listings, offset now: {$new_offset}");
        }
    }

    function import_events($listing_id)
    {
        // Memory management
        $memory_limit = ini_get('memory_limit');
        $memory_usage_before = memory_get_usage();

        $icals_list = get_post_meta($listing_id, 'listeo_ical_imports', true);

        if (!empty($icals_list)) {
            $import_count = 0;
            $max_imports_per_run = 5; // Limit imports per listing to prevent timeout

            foreach ($icals_list as $key => $value) {
                if ($import_count >= $max_imports_per_run) {
                    break;
                }

                // Rate limiting - add delay between imports
                if ($import_count > 0) {
                    usleep(500000); // 0.5 second delay
                }

                try {
                    $result = $this->import_bookings_from_ical($value, $listing_id);
                    
                    $import_count++;

                    // Memory check
                    $memory_usage = memory_get_usage();
                    $memory_increase = $memory_usage - $memory_usage_before;

                    if ($memory_increase > 50 * 1024 * 1024) { // 50MB increase
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Listeo iCal: High memory usage detected, stopping imports for listing {$listing_id}");
                        }
                        break;
                    }

                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Listeo iCal import error for listing {$listing_id}: " . $e->getMessage());
                    }
                }
            }
        }

        // Clean up memory
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    function remove_from_ical($arr, $listing_id)
    {

        $url  = $arr['url'];
        $name = $arr['name'];
        $id   = $listing_id;

        //instead of just name, safer approach is to use hash, as it has standard length
        $external_status = sprintf('external-%s-%d', md5($name), $id);

        //comment unique name
        $comment_calendar_name = sprintf('%s%dicalimport', $name, $id);

        //remove previously added bookings for given external status
        $removed = self::$bookings->delete_bookings(array(
            'listing_id' => $listing_id,
            'type'       => 'reservation',
            'status'     => $this->generate_external_status_name($listing_id, $name),
        ));

        //legacy delete
        $removed += self::$bookings->delete_bookings(array(
            'listing_id' => $listing_id,
            'type'       => 'reservation',
            'comment'    => $comment_calendar_name,
        ));

        return $removed;
    }


    /**
     * @param $arr
     * @param $listing_id
     *
     * $arr elements:
     * - url (url to iCal file)
     * - name (user-defined name)
     * - force_update (user-defined force_update field)
     * - bookings_author (wp user ID to be associated with import)
     *
     * @return array $response = [
     *      imported                    => (int) number of imported iCal Event imports.
     *      skipped_already_booked      => (int) number of skipped iCal Event imports due to lack of availability in given time slot.
     *      skipped_missing_slot        => (int) number of skipped iCal Event imports due to missing time slot.
     *      skipped_server_error        => (int) number of skipped iCal Event imports due to problem with constructing dates.
     *      skipped_past                => (int) number of skipped iCal Event imports due to import being in the past.
     * ]
     */
    function import_bookings_from_ical($arr, $listing_id): array
    {
        $url        = $arr['url'];
        $local_name = $arr['name'];
        //should update be forced, false string by default for backward compatibility
        $force_update = $arr['force_update'] ?? 'false';
        $force_update = filter_var($force_update, FILTER_VALIDATE_BOOLEAN);
        //bookings author ID - 0 by default for backward compatibility
        $bookings_author = $arr['bookings_author'] ?? 0;

        $listing_type = listeo_get_booking_type($listing_id);
        
        // Add cache/change detection to avoid unnecessary processing
        $cache_key = 'listeo_ical_' . md5($url . $listing_id);
        $etag_key = $cache_key . '_etag';
        $last_modified_key = $cache_key . '_modified';

        // Get ETag and Last-Modified from remote server to check for changes
        $head_request = wp_remote_head($url, array(
            'timeout' => 10,
            'headers' => array(
                'If-None-Match' => get_transient($etag_key),
                'If-Modified-Since' => get_transient($last_modified_key)
            )
        ));
        

        // If server returns 304 Not Modified, skip import unless forced
        if (!is_wp_error($head_request) && !$force_update) {
            $response_code = wp_remote_retrieve_response_code($head_request);
            if ($response_code === 304) {
                return array(
                    'imported'               => 0,
                    'skipped_already_booked' => 0,
                    'skipped_missing_slot'   => 0,
                    'skipped_server_error'   => 0,
                    'skipped_past'           => 0,
                    'message'                => 'No changes detected'
                );
            }
        }

        try {
            $ical = new Listeo_Core_iCal_Reader(
                [
                    $url,
                ]
            );
        } catch (Exception $exception) {
            // Enhanced error handling and logging
            $error_message = sprintf(
                'iCal fetch failed for listing %d, URL: %s, Error: %s',
                $listing_id,
                $url,
                $exception->getMessage()
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($error_message);
            }

            // Return early with error status
            return array(
                'imported'               => 0,
                'skipped_already_booked' => 0,
                'skipped_missing_slot'   => 0,
                'skipped_server_error'   => 1,
                'skipped_past'           => 0,
                'error'                  => $exception->getMessage()
            );
        }

        $import_response = array(
            'imported'               => 0,
            'skipped_already_booked' => 0,
            'skipped_missing_slot'   => 0,
            'skipped_server_error'   => 0,
            'skipped_past'           => 0,
        );

        if ($ical->has_events()) {

            // Pre-scan the feed to count events that would actually be imported (non-past, parsable).
            // If the new feed has nothing to insert, we must NOT clear previously-imported bookings —
            // otherwise valid blocked dates get wiped when the upstream feed contains only past events
            // or fails to parse, leaving the listing with no imported availability data.
            $importable_count = 0;
            foreach ($ical->events() as $event) {
                try {
                    $event_dates = $this->parse_event_dates($event);
                } catch (Exception $exception) {
                    continue;
                }
                if ($event_dates['current']['local'] > $event_dates['date_end']['local']) {
                    continue;
                }
                $importable_count++;
            }

            if ($importable_count === 0) {
                // Nothing importable in the new feed — leave previously imported dates intact.
                return $import_response;
            }

            // Snapshot IDs of bookings previously imported for THIS calendar so we can purge
            // them *after* a successful import, not before. The old "remove then re-insert"
            // order wiped valid blocked dates whenever the new run inserted nothing (e.g. a
            // non-forced cron sync on a slot-based service listing, where all-day external
            // events match no time slot and are all skipped). We now only delete the old
            // bookings once at least one new booking has actually been inserted.
            global $wpdb;
            $external_status      = $this->generate_external_status_name($listing_id, $local_name);
            $legacy_comment       = sprintf('%s%dicalimport', $local_name, $listing_id);
            $previous_booking_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}bookings_calendar WHERE listing_id = %d AND ( status = %s OR comment = %s )",
                    $listing_id,
                    $external_status,
                    $legacy_comment
                )
            );

            foreach ($ical->events() as $event) {
          
                try {
                    $event_dates = $this->parse_event_dates($event);
                  
                } catch (Exception $exception) {
                    if (true === WP_DEBUG) {
                        error_log($exception->getMessage(), $exception->getCode(), $exception->getFile());
                    }

                    //if any of the dates can't be retrieved skip this insert
                    $import_response['skipped_server_error']++;
                    continue;
                }

                if ($event_dates['current']['local'] > $event_dates['date_end']['local']) {
                    //skip events from the past
                    $import_response['skipped_past']++;
                    continue;
                }

                switch ($listing_type) {
                    case 'single_day':
                        // Service bookings with time slots
                        $booking_insert_response = $this->update_service_booking_for_ical_event($listing_id, $local_name, $bookings_author, $event, $force_update);
                        break;
                    case 'service':
                        // Backward compatibility: old listing type name for services
                        $booking_insert_response = $this->update_service_booking_for_ical_event($listing_id, $local_name, $bookings_author, $event, $force_update);
                        break;
                    case 'date_range':
                        // Rental bookings with date range selection
                        $booking_insert_response = $this->update_rental_booking_for_ical_event($listing_id, $local_name, $bookings_author, $event);
                        break;
                    case 'rental':
                        // Backward compatibility: old listing type name for rentals
                        $booking_insert_response = $this->update_rental_booking_for_ical_event($listing_id, $local_name, $bookings_author, $event);
                        break;
                    case 'tickets':
                        // Event bookings with ticket system
                        $booking_insert_response = $this->update_event_booking_for_ical_event($listing_id, $local_name, $bookings_author, $event);
                        break;
                    case 'event':
                        // Backward compatibility: old listing type name for events
                        $booking_insert_response = $this->update_event_booking_for_ical_event($listing_id, $local_name, $bookings_author, $event);
                        break;
                    default:
                        // No booking type or 'none' - skip import
                        $booking_insert_response = array(
                            'imported'               => 0,
                            'skipped_already_booked' => 0,
                            'skipped_missing_slot'   => 0,
                            'booking_ids'            => array(),
                        );
                }

                $import_response['imported']               += $booking_insert_response['imported'];
                $import_response['skipped_already_booked'] += $booking_insert_response['skipped_already_booked'];
                $import_response['skipped_missing_slot']   += $booking_insert_response['skipped_missing_slot'];
            }

            // Purge the previously-imported bookings only if the new import actually added
            // something. If nothing was imported (all events skipped), keep the existing
            // blocked dates intact instead of wiping them.
            if ($import_response['imported'] > 0 && !empty($previous_booking_ids)) {
                foreach ($previous_booking_ids as $old_id) {
                    self::$bookings->delete_bookings(array('id' => (int) $old_id));
                }
            }

            // Store cache headers for next request (cache for 30 minutes)
            if (!is_wp_error($head_request)) {
                $etag = wp_remote_retrieve_header($head_request, 'etag');
                $last_modified = wp_remote_retrieve_header($head_request, 'last-modified');

                if ($etag) {
                    set_transient($etag_key, $etag, 30 * MINUTE_IN_SECONDS);
                }
                if ($last_modified) {
                    set_transient($last_modified_key, $last_modified, 30 * MINUTE_IN_SECONDS);
                }
            }
        }

        return $import_response;
    }

    /**
     * @param int $listing_id
     * @param string $local_name
     * @param int $bookings_author
     * @param mixed $ical_event
     * @param false $force_update
     *
     * @return array $import_response = [
     *      imported                    => (int) Number of imported Bookings
     *      skipped_already_booked      => (int) Number of skipped imports due to lack of availability for slot
     *      skipped_missing_slot        => (int) Number of skipped imports due to missing slot
     *      booking_ids                 => (int[]) Array of booking IDs added
     * ]
     *
     * @throws Exception
     */
    public function update_service_booking_for_ical_event(int $listing_id, string $local_name, int $bookings_author, $ical_event, $force_update = false): array
    {
        $import_response = array(
            'imported'               => 0,
            'skipped_already_booked' => 0,
            'skipped_missing_slot'   => 0,
            'booking_ids'            => array(),
        );

        try {
            $event_date = $this->parse_event_dates($ical_event);
        } catch (Exception $exception) {
            if (true === WP_DEBUG) {
                error_log($exception->getMessage(), $exception->getCode(), $exception->getFile());
            }

            return $import_response;
        }


        $listing_slots = Listeo_Core_Bookings_Calendar::get_slots_from_meta($listing_id);
        $day_of_week   = $this->get_day_of_the_week_for_date($event_date['date_start']['local']);

        $listing_slots_for_day = array();
        if (true === isset($listing_slots[$day_of_week])) {
            $listing_slots_for_day = $listing_slots[$day_of_week];
        }

        //by default presume that given slot does not exist
        $slot_exists = false;
        //by default presume that given slot is occupied
        $slot_already_booked = true;
        //by default presume that import has failed
        $slot_imported = false;

        array_walk(
            $listing_slots_for_day,
            function (&$slot_data, $key) {
                $slot_details = explode('|', $slot_data);
                $slot_data    = $slot_details[0];
                $slot_times   = explode('-', $slot_data);

                /**
                 * timezone is not contained in string, and it would be treated as Zulu/UTC timezone.
                 * For this reason it is needed to say that we are looking for UTC timezone to avoid default
                 * WordPress timezone to make problem when importing.
                 *
                 * This might seems as mistake, but it will return actual time defined in slot.
                 * If default (local wp install) timezone would be used, it would cause offset of X hours depending
                 * on timezone that was selected.
                 *
                 */
                $slot_data = [
                    'time_start'    => wp_date('H:i:s', strtotime(trim($slot_times[0])), new DateTimeZone('UTC')),
                    'time_end'      => wp_date('H:i:s', strtotime(trim($slot_times[1])), new DateTimeZone('UTC')),
                    'max_occupancy' => intval($slot_details[1]),
                ];
            }
        );

        $slot_max_occupancy = 0;

        /**
         * When force update is FALSE match actual slots for insert and verify slot availability
         * It will only import if slot exists with given times and if it is available
         *
         * When force update is TRUE data would always be imported. But it will occupy as many slots as it is needed
         * To make sure that all data are entered, and slots would be displayed as booked.
         */

        foreach ($listing_slots_for_day as $slot_data) {
            /**
             * Simple way to match if time portion of datetime equals given time is to use strpos.
             * For ex. $date_start = 2021-02-28 12:00:00 and time_start is 12:00:00
             * expected strpos here is 11. And match will always produce 11 as result of standard sizes
             * of date string.
             *
             */
            if (
                11 === strpos($event_date['date_start']['local'], $slot_data['time_start'])
                && 11 === strpos($event_date['date_end']['local'], $slot_data['time_end'])
            ) {
                $slot_exists        = true;
                $slot_max_occupancy = $slot_data['max_occupancy'];
            }
        }

        if (true === $slot_exists) {
            /**
             * Matching slot found for given times.
             * If update IS FORCED go to insert regardless of is something occupying that slot already.
             * If update IS NOT FORCED check for other bookings on give date and time to determine can slot be occupied.
             */
            if (true === $force_update) {
                $booking_id = $this->create_booking_from_event($listing_id, $local_name, $bookings_author, $ical_event);

                if (0 < $booking_id) {
                    $import_response['imported']++;
                    $import_response['booking_ids'][] = $booking_id;
                }
            } else {
                $slot_bookings = Listeo_Core_Bookings_Calendar::get_slots_bookings(
                    $event_date['date_start']['local'],
                    $event_date['date_end']['local'],
                    array(
                        'listing_id' => $listing_id,
                        'type'       => 'reservation'
                    )
                );

                $slot_occupancy = count($slot_bookings);

                if ($slot_occupancy < $slot_max_occupancy) {
                    $slot_already_booked = false;
                } else {
                    $import_response['skipped_already_booked']++;
                }
            }
        } else {
            /**
             * Slot does not exist.
             * If update IS FORCED there is series of checks to make sure what to do with this event
             * If update IS NOT FORCED skip and increment skipped_missing_slot value.
             */
            if (true === $force_update) {
                /**
                 * There are 2 use-cases when update IS FORCED and no slots match
                 * If there ARE slots for the day, where we need to calculate slot occupancy for given time
                 * If there ARE NO slots for the day, in which case we can simply import, as there is nothing to check
                 */

                if (0 < count($listing_slots_for_day)) {
                    /**
                     * Slots defined for this day. But times defined do not match slots.
                     * Goal is to detect slots based on times, so that event will occupy one or more slots
                     * Resulting in occupied slots to be subtracted properly when generating slots dropdown on frontend.
                     */

                    /**
                     * Timezone is not relevant but important to avoid problems in calculation.
                     * Usage of local datetime is confusing but necessary as gmt datetime will have offset
                     */
                    $event_start_time = wp_date('His', strtotime($event_date['date_start']['local']), $event_date['zulu_timezone']);
                    $event_end_time   = wp_date('His', strtotime($event_date['date_end']['local']), $event_date['zulu_timezone']);

                    $event_slots = array_map(
                        function ($slot_data) use ($event_start_time, $event_end_time, $event_date) {
                            $slot_start_time = wp_date('His', strtotime($slot_data['time_start']), $event_date['zulu_timezone']);
                            $slot_end_time   = wp_date('His', strtotime($slot_data['time_end']), $event_date['zulu_timezone']);

                            /**
                             * Make sure that slot times occipied are correct when event start before first slot start
                             * and when it ends after last slot end time.
                             */
                            if ($slot_start_time < $event_end_time && $slot_end_time > $event_start_time) {
                                // if use <= and >= it will block the previous slot, i.e. event 1pm-5pm, it will block 12pm-1pm and also 5pm-6pm
                                if ($slot_start_time <= $event_start_time && $slot_end_time >= $event_end_time) {
                                    /**
                                     * Matches events that are fitting in one slot
                                     * [start]    |--------slot---------|    [end]
                                     * [start]        |----event----|       [end]
                                     */

                                    return $slot_data;
                                } elseif ($slot_start_time <= $event_start_time && $slot_end_time < $event_end_time) {
                                    /**
                                     * Matches events that are starting in one and ending in one of the upcoming slots
                                     * [start]    |--------slot 1--------|--------slot 2--------|    [end]
                                     * [start]        |------------event------------|       [end]
                                     */

                                    return $slot_data;
                                } elseif ($slot_start_time > $event_start_time && $slot_end_time >= $event_end_time) {
                                    return $slot_data;
                                    // should block last slot
                                } elseif ($slot_start_time > $event_start_time && $slot_end_time < $event_end_time) {
                                    // in-between slots, i.e. event 1pm-5pm, should block 1h slots from 2pm-4pm
                                    return $slot_data;
                                }
                            }

                            /**
                             * Return null by default; it will get removed by array_filter afterwards
                             */
                            return null;
                        },
                        $listing_slots_for_day
                    );

                    //remove items with empty value
                    $event_slots = array_filter($event_slots);

                    foreach ($event_slots as $event_slot) {

                        $date = wp_date('Y-m-d', strtotime($event_date['date_start']['local']), $event_date['zulu_timezone']);

                        $slot_date_start_local = $date . ' ' . $event_slot['time_start'];
                        $slot_date_end_local   = $date . ' ' . $event_slot['time_end'];

                        $slot_date_start_local_datetime = new DateTime($slot_date_start_local, $event_date['local_timezone']);
                        $slot_date_start_local_datetime->setTimezone($event_date['zulu_timezone']);
                        $slot_date_start_gmt = $slot_date_start_local_datetime->format('Y-m-d H:i:s');

                        $slot_date_end_local_datetime = new DateTime($slot_date_end_local, $event_date['local_timezone']);
                        $slot_date_end_local_datetime->setTimezone($event_date['zulu_timezone']);
                        $slot_date_end_gmt = $slot_date_end_local_datetime->format('Y-m-d H:i:s');

                        $custom_date = [
                            'date_start' => [
                                'gmt'   => $slot_date_start_gmt,
                                'local' => $slot_date_start_local,
                            ],
                            'date_end'   => [
                                'gmt'   => $slot_date_end_gmt,
                                'local' => $slot_date_end_local,
                            ],
                        ];

                        $booking_id = $this->create_booking_from_event($listing_id, $local_name, $bookings_author, $ical_event, $custom_date);

                        if (0 < $booking_id) {
                            $import_response['imported']++;
                            $import_response['booking_ids'][] = $booking_id;
                        }
                    }

                    /**
                     * If event is can't be placed in slots might be out of range.
                     * Just move it to calendar when update is forced
                     */
                    if (true === empty($event_slots)) {
                        $booking_id = $this->create_booking_from_event($listing_id, $local_name, $bookings_author, $ical_event);

                        if (0 < $booking_id) {
                            $import_response['imported']++;
                            $import_response['booking_ids'][] = $booking_id;
                        }
                    }
                } else {
                    /**
                     * No slots for the day - run insert immediately
                     */
                    $booking_id = $this->create_booking_from_event($listing_id, $local_name, $bookings_author, $ical_event);

                    if (0 < $booking_id) {
                        $import_response['imported']++;
                        $import_response['booking_ids'][] = $booking_id;
                    }
                }
            } else {
                $import_response['skipped_missing_slot']++;
            }
        }

        if (false === $slot_already_booked) {
            $booking_id = $this->create_booking_from_event($listing_id, $local_name, $bookings_author, $ical_event);

            if (0 < $booking_id) {
                $import_response['imported']++;
                $import_response['booking_ids'][] = $booking_id;
            }
        }

        return $import_response;
    }

    /**
     * @param int $listing_id
     * @param string $local_name
     * @param int $bookings_author
     * @param mixed $ical_event
     *
     * @return array $import_response = [
     *      imported                    => (int) Number of imported Bookings
     *      skipped_already_booked      => (int) Number of skipped imports due to lack of availability for slot
     *      skipped_missing_slot        => (int) Number of skipped imports due to missing slot
     *      booking_ids                 => (int[]) Array of booking IDs added
     * ]
     *
     * @throws Exception
     */
    public function update_rental_booking_for_ical_event(int $listing_id, string $local_name, int $bookings_author, $ical_event): array
    {
        $import_response = array(
            'imported'               => 0,
            'skipped_already_booked' => 0,
            'skipped_missing_slot'   => 0,
            'booking_ids'            => array(),
        );

        try {
            $event_date = $this->parse_event_dates($ical_event);
        } catch (Exception $exception) {
            if (true === WP_DEBUG) {
                error_log($exception->getMessage(), $exception->getCode(), $exception->getFile());
            }

            return $import_response;
        }

        if (true === $ical_event->all_day_event) {
            /**
             * If it is set as all-day event by standard time portion is not included.
             * This will make problems as DateTime as mutable value will vary based on timezone.
             * Ignore timezone by using event_days from Event object
             *
             */

            $event_days                = $ical_event->event_days;
            $event_date_start_datetime = new DateTimeImmutable(current($event_days));
            $event_date_end_datetime   = new DateTimeImmutable(end($event_days));
        } else {
            /**
             * If multi-day event is set there is different problem.
             * Now we have timezone included which is fine, but end-result should be the same.
             * Booking should take 00:00:00 on first day and 23:59:59 on last day of booking.
             *
             * To achieve this some datetime manipulation is required to avoid timezone confusion again, and date-switching
             * due to timezone discrepancy.
             *
             * Staring with GMT/UTC timezone it is possible to have correct date in local (WordPress) timezone.
             * Stripping the time part of date time string is easy, and effective in this case, as end result is date only.
             * For both, start and end time same logic is applied to convert datetime to proper date in local timezone.
             *
             * Same logic is then applied as for all-day event, where local and gmt time will match.
             */

            $date_start = preg_replace('/(\d{4}-\d{2}-\d{2})\s([\d{2}:?]+)/', '$1', $event_date['date_start']['local']);
            $date_end   = preg_replace('/(\d{4}-\d{2}-\d{2})\s([\d{2}:?]+)/', '$1', $event_date['date_end']['local']);

            $event_date_start_datetime = new DateTimeImmutable($date_start);
            $event_date_end_datetime   = new DateTimeImmutable($date_end);
        }

        $event_date_start = $event_date_start_datetime->format('Y-m-d H:i:s');
        $event_date_end   = $event_date_end_datetime->format('Y-m-d H:i:s');

        /**
         * Force event to use all day (00:00 until 23:59) always
         */
        $custom_date = [
            'date_start' => [
                'gmt'   => $event_date_start,
                'local' => $event_date_start,
            ],
            'date_end'   => [
                'gmt'   => $event_date_end,
                'local' => $event_date_end,
            ],
        ];

        $booking_id = $this->create_booking_from_event($listing_id, $local_name, $bookings_author, $ical_event, $custom_date);

        if (0 < $booking_id) {
            $import_response['imported']++;
            $import_response['booking_ids'][] = $booking_id;
        }

        return $import_response;
    }

    /**
     * @param int $listing_id
     * @param string $local_name
     * @param int $bookings_author
     * @param mixed $ical_event
     *
     * @return array $import_response = [
     *      imported                    => (int) Number of imported Bookings
     *      skipped_already_booked      => (int) Number of skipped imports due to lack of availability for slot
     *      skipped_missing_slot        => (int) Number of skipped imports due to missing slot
     *      booking_ids                 => (int[]) Array of booking IDs added
     * ]
     *
     * @throws Exception
     */
    public function update_event_booking_for_ical_event(int $listing_id, string $local_name, int $bookings_author, $ical_event): array
    {
        $import_response = array(
            'imported'               => 0,
            'skipped_already_booked' => 0,
            'skipped_missing_slot'   => 0,
            'booking_ids'            => array(),
        );

        /**
         * TODO: add option to read attendees to mark number of registered people for event
         */

        $booking_id = $this->create_booking_from_event($listing_id, $local_name, $bookings_author, $ical_event);

        if (0 < $booking_id) {
            $import_response['imported']++;
            $import_response['booking_ids'][] = $booking_id;
        }

        return $import_response;
    }

    /**
     * Create booking from ical event.
     * Dates in event can be customized using $customize_date array
     * It has same format as format returned by parse_event_dates
     *
     * @param int $listing_id
     * @param string $local_name
     * @param int $bookings_author
     * @param object|mixed $ical_event
     * @param array $customize_date
     *
     * @return int
     * @throws Exception
     * @see Listeo_Core_iCal::parse_event_dates() for format
     *
     */
    public function create_booking_from_event(int $listing_id, string $local_name, int $bookings_author, $ical_event, $customize_date = []): int
    {
        $event_date = $this->parse_event_dates($ical_event);

        if (false === empty($customize_date)) {
            $event_date = array_merge($event_date, $customize_date);
        }

        $booking_args = array(
            'listing_id'      => $listing_id,
            'type'            => 'reservation',
            'bookings_author' => $bookings_author,
            'owner_id'        => 0,
            'date_start'      => $event_date['date_start']['local'],
            'date_start_gmt'  => $event_date['date_start']['gmt'],
            'date_end'        => $event_date['date_end']['local'],
            'date_end_gmt'    => $event_date['date_end']['gmt'],
            'comment'         => $this->sanitize_ical_event_for_storage($ical_event),
            'order_id'        => null,
            'status'          => $this->generate_external_status_name($listing_id, $local_name),
            'price'           => 0,
        );

        /**
         * Allow extensions to modify the booking args just before insert.
         *
         * Lets add-on plugins (e.g. Listeo Booking Plus' resource iCal)
         * stamp extra metadata into the `comment` JSON or rewrite the
         * status without forking the entire import pipeline. The filter
         * runs once per imported event, between the iCal parser and the
         * actual DB write, so consumers can rely on Core's date + base
         * comment work already being done.
         *
         * @param array  $booking_args  Args passed to insert_booking().
         * @param object $ical_event    The parsed iCal event (Listeo_Core_iCal_Event).
         * @param string $local_name    The saved-calendar name in the importer config.
         * @param int    $listing_id    Parent listing id.
         */
        $booking_args = apply_filters(
            'listeo_ical_create_booking_args',
            $booking_args,
            $ical_event,
            $local_name,
            $listing_id
        );

        $booking_id = self::$bookings->insert_booking( $booking_args );

        return $booking_id;
    }

    /**
     * Parse dates and timezones from iCal event
     * Calendar timezone is irelevant due to usage of Zulu (\Z) timedate string from iCal event.
     * This way we have standard time that can be converted to any timezone using DateTime class and setTimezone method.
     *
     * @param mixed|object $ical_event
     *
     * @return array $dates = [
     *      'date_start' => [
     *          'gmt'           => (string) Start Datetime in GMT/UTC/Zulu Timezone
     *          'local'         => (string) Start Datetime in Local (WP) Timezone
     *      ],
     *      'date_end'=> [
     *          'gmt'           => (string) End Datetime in GMT/UTC/Zulu Timezone
     *          'local'         => (string) End Datetime in Local (WP) Timezone
     *      ],
     *      'current'=> [
     *          'gmt'           => (string) Current Datetime in GMT/UTC/Zulu Timezone
     *          'local'         => (string) Current Datetime in Local (WP) Timezone
     *      ],
     *      'local_timezone'    => (DateTimeZone) DateTimeZone object of Local (WP) Timezone
     *      'zulu_timezone'     => (DateTimeZone) DateTimeZone object of GMT/UTC/Zulu Timezone
     * ]
     * @throws Exception
     */
    public function parse_event_dates($ical_event): array
    {
        //zulu/gmt/utc time zone - Zulu is still represented in iCal file using Z at the end of the date string
        $zulu_timezone = new DateTimeZone('UTC');
        //local timezone (WP install timezone)
        $local_timezone = new DateTimeZone(wp_timezone_string());

        //start date in Etc/Zulu (UTC/GMT+0) time zone
        $date_start_gmt = wp_date('Y-m-d H:i:s', strtotime($ical_event->dtstart), $zulu_timezone);
        //start date in WordPress default time zone (defined in Settings)
        $date_start_datetime = new DateTime($date_start_gmt, $zulu_timezone);
        $date_start_datetime->setTimezone($local_timezone);
        $date_start = $date_start_datetime->format('Y-m-d H:i:s');

        //end date in Etc/Zulu (UTC/GMT+0) time zone
        $date_end_gmt = wp_date('Y-m-d H:i:s', strtotime($ical_event->dtend), $zulu_timezone);
        //end date in WordPress default time zone (defined in Settings)
        $date_end_datetime = new DateTime($date_end_gmt, $zulu_timezone);
        $date_end_datetime->setTimezone($local_timezone);
        $date_end = $date_end_datetime->format('Y-m-d H:i:s');
        return array(
            'date_start'     => [
                'gmt'   => $date_start_gmt,
                'local' => $date_start,
            ],
            'date_end'       => [
                'gmt'   => $date_end_gmt,
                'local' => $date_end,
            ],
            'current'        => [
                'gmt'   => wp_date('Y-m-d H:i:s', time(), $zulu_timezone),
                'local' => wp_date('Y-m-d H:i:s', time(), $local_timezone),
            ],
            'local_timezone' => $local_timezone,
            'zulu_timezone'  => $zulu_timezone,
        );
    }

    /**
     * Sanitize iCal event data before storing in database
     *
     * @param mixed $ical_event
     * @return string
     */
    private function sanitize_ical_event_for_storage($ical_event): string
    {
        // Extract only safe data from iCal event
        $safe_data = array(
            'summary'     => isset($ical_event->summary) ? sanitize_text_field($ical_event->summary) : '',
            'description' => isset($ical_event->description) ? sanitize_textarea_field($ical_event->description) : '',
            'dtstart'     => isset($ical_event->dtstart) ? sanitize_text_field($ical_event->dtstart) : '',
            'dtend'       => isset($ical_event->dtend) ? sanitize_text_field($ical_event->dtend) : '',
            'uid'         => isset($ical_event->uid) ? sanitize_text_field($ical_event->uid) : '',
            'location'    => isset($ical_event->location) ? sanitize_text_field($ical_event->location) : '',
        );

        // Remove empty values
        $safe_data = array_filter($safe_data);

        return wp_json_encode($safe_data);
    }

    /**
     * Generates unique booking status string.
     *
     * @param int $listing_id
     * @param string $local_name
     *
     * @return string
     */
    public function generate_external_status_name(int $listing_id, string $local_name): string
    {
        //instead of just name, safer approach is to use hash, as it has standard length
        return sprintf('external-%s-%d', md5($local_name), $listing_id);
    }

    /**
     * Return day of the week for given date
     *
     * @param string $date
     *
     * @return false|int|string
     */
    private function get_day_of_the_week_for_date(string $date)
    {
        $day_of_week = date('w', strtotime($date));
        if (0 === $day_of_week) {
            $day_of_week = 6;
        } else {
            $day_of_week = $day_of_week - 1;
        }

        return $day_of_week;
    }

    public function has_slot_for_date($listing_id, $date_start, $date_end)
    {
        $has_slot      = false;
        $listing_slots = Listeo_Core_Bookings_Calendar::get_slots_from_meta($listing_id);
    }

    /**
     * Get available slots for Listing typed Service for given start and end date
     *
     * @param $listing_id
     * @param $date_start
     * @param $date_end
     */
    public function get_available_service_listing_slots($listing_id, $date_start, $date_end)
    {
        $listing_slots = Listeo_Core_Bookings_Calendar::get_slots_from_meta($listing_id);
        $day_of_week   = date('w', strtotime($date_start));
        if (0 === $day_of_week) {
            $day_of_week = 6;
        } else {
            $day_of_week = $day_of_week - 1;
        }

        $listing_slots_for_day = array();
        if (true === isset($listing_slots[$day_of_week])) {
            $listing_slots_for_day = $listing_slots[$day_of_week];
        }

        foreach ($listing_slots_for_day as $key => $slot) {
            $slot_details = explode('|', $slot);
            $free_places  = $slot_details[1];

            $hours_range     = explode(' - ', $slot_details[0]);
            $slot_hour_start = date("H:i:s", strtotime($hours_range[0]));
            $slot_hour_end   = date("H:i:s", strtotime($hours_range[1]));

            $slot_date_start = $date_start . ' ' . $slot_hour_start;
            $slot_date_end   = $date_end . ' ' . $slot_hour_end;

            $result = Listeo_Core_Bookings_Calendar::get_slots_bookings($date_start, $date_end, array(
                'listing_id' => $listing_id,
                'type'       => 'reservation'
            ));
        }

        die;
    }
}