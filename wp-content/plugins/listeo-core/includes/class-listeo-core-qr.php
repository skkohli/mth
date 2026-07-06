<?php
// Exit if accessed directly
if (! defined('ABSPATH'))
    exit;


require_once LISTEO_PLUGIN_DIR . 'lib/phpqrcode/qrlib.php';

/**
 * WP_listing_Manager_Content class.
 */
class Listeo_Core_QR
{

    /**
     * Dashboard message.
     *
     * @access private
     * @var string
     */

    private $ticket_table;
    private $booking_table;

    public function __construct()
    {
        global $wpdb;
        $this->ticket_table = $wpdb->prefix . 'listeo_core_tickets';
        $this->booking_table = $wpdb->prefix . 'bookings_calendar';

        add_action('wp_ajax_verify_ticket', array($this, 'verify_ticket'));
        add_action('wp_ajax_nopriv_verify_ticket', array($this, 'verify_ticket'));
        add_action('wp_ajax_get_ticket', array($this, 'get_ticket'));
        add_action('wp_ajax_nopriv_get_ticket', array($this, 'get_ticket'));

        add_shortcode('listeo_qr_check', array($this, 'ticket_verification_shortcode'));
        register_activation_hook(__FILE__, array($this, 'flush_rewrite_rules'));
        add_action('wp_loaded', array($this, 'custom_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));

        add_action('parse_request', array($this, 'handle_custom_endpoint'));
        //  add_action('template_redirect', array($this, 'handle_ticket_request'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts()
    {
        if (is_page(get_option('listeo_ticket_check_page'))) {
            wp_enqueue_script('qr-scanner', LISTEO_CORE_URL . 'assets/js/qr-scanner.js', array('jquery'), '1.0', true);
            wp_enqueue_script('jsqr', 'https://cdn.jsdelivr.net/npm/jsqr@1.3.1/dist/jsQR.min.js', array(), '1.3.1', true);
        }
    }

    public function ticket_verification_shortcode()
    {

        $ticket_code = isset($_GET['verify']) ? sanitize_text_field($_GET['verify']) : '';
        $verification_result = $this->verify_ticket($ticket_code);

        ob_start();
?>
        <div class="ticket-verification-container">

            <div id="verification-result" class="ticket-verification-result">
                <?php
                if (isset($verification_result['status'])) {
                    $this->display_verification_result($verification_result);
                }
                ?>
            </div>
            <div id="scanner-container" style="display: none;">
                <video id="qr-video" style="width: 100%; max-width: 500px;"></video>
                <canvas id="qr-canvas" style="display: none;"></canvas>
            </div>
            <a href="#" class="qr-code-scan-btn" id="start-scanner">
                <img src="<?php echo LISTEO_CORE_URL; ?>assets/images/listeo_qr.svg" />
                <span><?php esc_html_e('Click to Open QR Code Scanner', 'listeo_core'); ?></span>
            </a>

            <div id="debug-info" style="display:none; margin-top: 20px; padding: 10px; background-color: #f0f0f0;"></div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/jsqr@1.3.1/dist/jsQR.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const video = document.getElementById('qr-video');
                const canvas = document.getElementById('qr-canvas');
                const ctx = canvas.getContext('2d');
                const startButton = document.getElementById('start-scanner');
                const scannerContainer = document.getElementById('scanner-container');
                const resultDiv = document.getElementById('verification-result');

                const debugInfo = document.getElementById('debug-info');
                let scanning = false;
                let stream = null;

                function log(message) {
                    console.log(message);
                    debugInfo.innerHTML += message + '<br>';
                }

                startButton.addEventListener('click', function() {
                    if (scanning) {
                        stopScanner();
                    } else {
                        startScanner();
                    }
                });

                function startScanner() {
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        log('Sorry, your browser doesn\'t support accessing the camera.');
                        return;
                    }

                    navigator.mediaDevices.getUserMedia({
                            video: {
                                facingMode: "environment"
                            }
                        })
                        .then(function(mediaStream) {
                            log('Camera access granted');
                            scanning = true;
                            stream = mediaStream;
                            startButton.textContent = 'Stop Scanner';
                            scannerContainer.style.display = 'block';
                            video.srcObject = stream;
                            video.setAttribute("playsinline", true);
                            video.play();
                            requestAnimationFrame(tick);
                        })
                        .catch(function(err) {
                            log('Error accessing the camera: ' + err.message);
                        });
                }

                function stopScanner() {
                    log('Stopping scanner');
                    scanning = false;
                    startButton.textContent = 'Start Scanner';
                    scannerContainer.style.display = 'none';
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                        stream = null;
                    }
                    video.srcObject = null;
                }

                function tick() {
                    if (scanning) {
                        if (video.readyState === video.HAVE_ENOUGH_DATA) {
                            canvas.height = video.videoHeight;
                            canvas.width = video.videoWidth;
                            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                            var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                            var code = jsQR(imageData.data, imageData.width, imageData.height, {
                                inversionAttempts: "dontInvert",
                            });
                            if (code) {
                                log("Found QR code: " + code.data);
                                stopScanner();
                                window.location.href = code.data; // Automatically redirect to the URL in the QR code
                            }
                        }
                        requestAnimationFrame(tick);
                    }
                }

                function verifyTicket(url) {
                    const verifyParam = new URL(url).searchParams.get('verify');
                    if (verifyParam) {
                        log('Verifying ticket: ' + verifyParam);
                        window.location.href = url; // Redirect to the verification URL
                    } else {
                        resultDiv.innerHTML = '<p>Invalid QR code. Please try again.</p>';
                    }
                }

                window.addEventListener('unload', stopScanner);
            });
        </script>
        <?php
        return ob_get_clean();
    }

    private function display_verification_result($verification_result)
    {
        if ($verification_result['status'] === 'valid') { ?>
            <div class="listing-added-notice listeo-valid-ticket">
                <div class="booking-confirmation-page">
                    <i class="fa fa-check-circle"></i>
                    <h2 class="valid-ticket"><?php esc_html_e('Valid Ticket', 'listeo_core'); ?></h2>
                    <p><?php esc_html_e('Booking ID: ', 'listeo_core'); ?><?php echo  esc_html($verification_result['booking_id']) ?></p>
                    <p><?php esc_html_e('This ticket has now been marked as used.', 'listeo_core'); ?></p>
                    <?php if (isset($verification_result['mark_as_paid'])) :
                        // we need button here that will mark booking as paid
                    ?>

                        <button id="mark-paid-btn" data-booking="<?php echo esc_attr($verification_result['booking_id']) ?>" data-success="<?php esc_html_e('Done!', 'listeo_core'); ?>" class="mark-paid-button button"><i class="fas fa-spinner fa-spin"></i> <?php esc_html_e('Mark as Paid', 'listeo_core'); ?></button>

                    <?php
                    endif; ?>
                </div>
            </div>

        <?php } elseif ($verification_result['status'] === 'used') { ?>
            <div class="listing-added-notice listeo-used-ticket">
                <div class="booking-confirmation-page">
                    <i class="fa fa-triangle-exclamation"></i>
                    <h2 class="used-ticket"><?php esc_html_e('Ticket Already Used', 'listeo_core'); ?></h2>
                    <p><?php esc_html_e('This ticket was used on:', 'listeo_core'); ?> <?php echo esc_html($verification_result['used_at']) ?></p>
                    <p><?php esc_html_e('Used by:', 'listeo_core'); ?> <?php echo esc_html($verification_result['used_by']) ?></p>

                </div>
            </div>


        <?php } elseif ($verification_result['status'] === 'error') { ?>
            <div class="listing-added-notice listeo-error-ticket">
                <div class="booking-confirmation-page">
                    <i class="fa fa-circle-exclamation"></i>
                    <h2 class="error-ticket"><?php esc_html_e('Error', 'listeo_core'); ?></h2>
                    <p><?php echo esc_html($verification_result['message']) ?></p>
                </div>
            </div>
<?php
        }
    }


    public function custom_rewrite_rules()
    {

        add_rewrite_rule(
            '^get-ticket/([0-9]+)/?$',
            'index.php?get_ticket=1&booking_id=$matches[1]',
            'top'
        );
    }

    public function add_query_vars($query_vars)
    {
        $query_vars[] = 'get_ticket';
        $query_vars[] = 'booking_id';
        return $query_vars;
    }

    public function flush_rewrite_rules()
    {
        $this->custom_rewrite_rules();
        flush_rewrite_rules();
    }

    public function handle_ticket_request()
    {
        $get_ticket = get_query_var('get_ticket');
        $booking_id = get_query_var('booking_id');

        if ($get_ticket == '1' && !empty($booking_id)) {
            $this->get_ticket(intval($booking_id));
            exit;
        }
    }

    public function handle_custom_endpoint($wp)
    {

        if (isset($wp->query_vars['get_ticket']) && $wp->query_vars['get_ticket'] == '1') {
            $booking_id = isset($wp->query_vars['booking_id']) ? intval($wp->query_vars['booking_id']) : 0;
            $this->get_ticket($booking_id);
            exit;
        }
    }


    public function generate_qr_code($ticket_code)
    {
        try {
            // Generate QR code for verification URL
            $verify_url = add_query_arg(
                array('verify' => $ticket_code),
                get_permalink(get_option('listeo_ticket_check_page'))
            );

            // Generate QR code and return as base64 encoded PNG
            // Clean output buffer to prevent any warnings/errors from being captured
            ob_start();
            // Suppress any potential warnings during QR code generation
            @QRcode::png($verify_url, null, 'M', 6, 2);
            $qr_image = ob_get_clean();

            // Ensure we got valid PNG data (starts with PNG signature)
            if (empty($qr_image) || substr($qr_image, 0, 4) !== "\x89PNG") {
                error_log('QR Code Generation Error: Invalid or empty PNG data');
                return false;
            }

            return base64_encode($qr_image);
        } catch (Exception $e) {
            error_log('QR Code Generation Error: ' . $e->getMessage());
            return false;
        }
    }


    public function generate_ticket_code($booking_id)
    {
        $ticket_code = md5($booking_id . time() . wp_rand());

        global $wpdb;
        $wpdb->insert(
            $this->ticket_table,
            array(
                'booking_id' => $booking_id,
                'ticket_code' => $ticket_code,
                'status' => 'valid',
                'used_at' => null
            )
        );

        return $ticket_code;
    }

    public function get_or_create_ticket($booking_id)
    {
        global $wpdb;

        // Check if a ticket already exists for this booking
        $existing_ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->ticket_table} WHERE booking_id = %d",
            $booking_id
        ));

        if ($existing_ticket) {
            return $existing_ticket->ticket_code;
        }

        // If no ticket exists, create a new one
        $ticket_code = $this->generate_ticket_code($booking_id);
        return $ticket_code;
    }


    public function generate_html_ticket($booking_id, $event_details)
    {
        try {
            // Generate ticket code
            $ticket_code = $this->get_or_create_ticket($booking_id);
            if (!$ticket_code) {
                throw new Exception('Failed to generate ticket code');
            }
            // Get QR code as base64 encoded string
            $qr_base64 = $this->generate_qr_code($ticket_code);
            if (!$qr_base64) {
                throw new Exception('Failed to generate QR code');
            }
            // Generate HTML
            $html = $this->get_ticket_template($booking_id,$event_details, $ticket_code, $qr_base64);


            return $html;
        } catch (Exception $e) {
            error_log('HTML Ticket Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    private function get_ticket_template($booking_id, $event_details, $ticket_code, $qr_base64)
    {
        ob_start();
        $template_loader = new Listeo_Core_Template_Loader;
        $data = array(
            'booking_id' => $booking_id,
            'event_details' => $event_details,
            'ticket_code' => $ticket_code,
            'qr_base64' => $qr_base64
        );
        $template_loader->set_template_data($data)->get_template_part('booking/ticket');
        return ob_get_clean();
    }


    public function verify_ticket($ticket_code)
    {
        global $wpdb;

        if (empty($ticket_code)) {
            return array('status' => 'invalid', 'message' => 'No ticket code provided.');
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return array('status' => 'error', 'message' => 'You must be logged in to verify tickets.');
        }

        /**
         * Allow add-ons to handle ticket verification for codes stored in
         * their own tables (e.g. Booking Plus per-individual ticket codes).
         *
         * Return a non-null array to short-circuit Core's lookup.
         * Expected keys: status (valid|used|error), message, booking_id, etc.
         *
         * @param array|null $result          Null by default (Core handles it).
         * @param string     $ticket_code     The code being verified.
         * @param int        $current_user_id The logged-in user performing the check.
         */
        $external_result = apply_filters( 'listeo_verify_ticket', null, $ticket_code, $current_user_id );
        if ( is_array( $external_result ) ) {
            return $external_result;
        }

        $ticket_info = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, b.owner_id, b.id as booking_id, b.status as booking_status, b.listing_id as listing_id
            FROM {$this->ticket_table} t
            LEFT JOIN {$this->booking_table} b ON t.booking_id = b.id
            WHERE t.ticket_code = %s",
            $ticket_code
        ));

        if (!$ticket_info) {
            return array('status' => 'error', 'message' => 'Invalid ticket code.');
        }

        if (!$ticket_info->booking_id) {
            return array('status' => 'error', 'message' => 'The booking associated with this ticket no longer exists.');
        }
        if ($ticket_info->booking_status === 'cancelled') {
            return array('status' => 'error', 'message' => 'This booking is not valid.');
        }
        $_payment_option = get_post_meta($ticket_info->listing_id, '_payment_option', true);
        if ($_payment_option == "pay_cash") {
        } else {

            if ($ticket_info->booking_status != 'paid') {
                return array('status' => 'error', 'message' => 'This booking is not paid.');
            }
        }

        if ($ticket_info->owner_id != $current_user_id) {
            return array('status' => 'error', 'message' => 'You are not authorized to verify this ticket.');
        }


        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->ticket_table} WHERE ticket_code = %s",
            $ticket_code
        ));

        if (!$ticket) {
            return array('status' => 'invalid');
        }

        if ($ticket->status === 'used') {
            // get booking_author by booking_id
            $booking_author = $wpdb->get_var($wpdb->prepare(
                "SELECT bookings_author FROM {$this->booking_table} WHERE id = %d",
                $ticket->booking_id
            ));
            return array(
                'status' => 'used',
                'used_at' => $ticket->used_at,
                'used_by' => $this->get_user_name($booking_author)
               
            );
        }

        // Mark the ticket as used
        $wpdb->update(
            $this->ticket_table,
            array(
                'status' => 'used',
                'used_at' => current_time('mysql'),
                'used_by' => get_current_user_id()
            ),
            array('id' => $ticket->id)
        );

        if ($_payment_option == "pay_cash") {
            return array(
                'status' => 'valid',
                'booking_id' => $ticket->booking_id,
                'mark_as_paid' => 'yes'
            );
        } else {

            return array(
                'status' => 'valid',
                'booking_id' => $ticket->booking_id
            );
        }
    }

    private function get_user_name($user_id)
    {
        $user = get_userdata($user_id);
        return $user ? $user->display_name : 'Unknown User';
    }

    public function get_ticket($booking_id)
    {

        if (!$booking_id) {
            wp_die('Invalid booking ID');
        }

        if (!$this->user_can_access_booking($booking_id)) {
            wp_die('You are not authorized to access this ticket');
        }

        $event_details = $this->get_event_details($booking_id);

        /**
         * Allow add-ons (e.g. Booking Plus) to replace the entire ticket HTML
         * for a booking. Return non-empty string to override Core's single-ticket template.
         *
         * The returned HTML is echoed directly as a full document. Callers are
         * responsible for sanitizing any user-controlled content within the markup.
         *
         * @param string $html          Empty by default.
         * @param int    $booking_id    Booking ID.
         * @param array  $event_details Event details array built by Core.
         */
        $html = apply_filters( 'listeo_get_ticket_html', '', $booking_id, $event_details );

        if ( empty( $html ) ) {
            $html = $this->generate_html_ticket($booking_id, $event_details);
        }

        if (!$html) {
            wp_die('Error generating ticket. Please try again.');
        }

        echo $html;
        exit;
    }


    private function user_can_access_booking($booking_id)
    {
        global $wpdb;

        $current_user_id = get_current_user_id();

        if ($current_user_id === 0) {
            return false;
        }

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->booking_table} WHERE id = %d AND bookings_author = %d",
            $booking_id,
            $current_user_id
        ));

        return $booking !== null;
    }


    public static function get_booking($id)
    {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}bookings_calendar` WHERE `id` = %d", absint($id) ), 'ARRAY_A' );
    }

    private function get_event_details($booking_id)
    {


        $data = $this->get_booking($booking_id);

        $details = json_decode($data['comment']);

        $listing_id = $data['listing_id'];
        $booking_type = listeo_get_booking_type($listing_id);

        // Initialize date variable to prevent undefined variable warning
        $date = '';

        // Get dates based on booking type (using modern custom listing types)
        // Support both new booking types and legacy types for backward compatibility

        // DATE RANGE booking type (rentals, accommodations with check-in/check-out)
        if ($booking_type == 'date_range' || $booking_type == 'rental') {
            $date = date_i18n(get_option('date_format'), strtotime($data['date_start'])) . ' - ' . date_i18n(get_option('date_format'), strtotime($data['date_end']));
        }

        // SINGLE DAY booking type (services, appointments with time slots)
        else if ($booking_type == 'single_day' || $booking_type == 'service') {
            $start_ts = strtotime($data['date_start']);
            $end_ts   = strtotime($data['date_end']);

            $date = date_i18n(get_option('date_format'), $start_ts);

            // Only append the time portion when the booking actually
            // carries one. A date-only booking stores 00:00:00, which
            // `date_i18n('g:i a')` happily formats as "12:00 am". The
            // old truthiness check on the *formatted* string was always
            // true, so we'd print "May 30, 2026 at 12:00 am" even when
            // the customer never picked a time. Treat midnight on both
            // ends as "no time" — listings whose customers genuinely
            // book at midnight invariably have a non-midnight end time.
            $start_hm = date('H:i', $start_ts);
            $end_hm   = date('H:i', $end_ts);
            $has_time = ('00:00' !== $start_hm) || ('00:00' !== $end_hm);

            if ($has_time) {
                $time_start = date_i18n(get_option('time_format'), $start_ts);
                $time_end   = date_i18n(get_option('time_format'), $end_ts);
                $date .= ' ' . esc_html__('at', 'listeo_core') . ' ' . esc_html($time_start);
                if ($time_start != $time_end && $time_end) {
                    $date .= ' - ' . esc_html($time_end);
                }
            }
        }

        // TICKETS booking type (events with fixed event dates)
        else if ($booking_type == 'tickets' || $booking_type == 'event') {
            // Get event date from listing meta (events have fixed dates)
            $meta_value = get_post_meta($listing_id, '_event_date', true);
            $meta_value_timestamp = get_post_meta($listing_id, '_event_date_timestamp', true);

            if ($meta_value_timestamp) {
                $meta_value_date = explode(' ', $meta_value, 2);
                $meta_value_date[0] = str_replace('/', '-', $meta_value_date[0]);
                $date = date_i18n(get_option('date_format'), $meta_value_timestamp);

                // Add event start time if available
                if (isset($meta_value_date[1])) {
                    $time = str_replace('-', '', $meta_value_date[1]);
                    $date .= esc_html__(' at ', 'listeo_core');
                    $date .= date_i18n(get_option('time_format'), strtotime($time));
                }

                // Add event end date/time if available
                $meta_value_end = get_post_meta($listing_id, '_event_date_end', true);
                $meta_value_end_timestamp = get_post_meta($listing_id, '_event_date_end_timestamp', true);

                if (!empty($meta_value_end) && !empty($meta_value_end_timestamp)) {
                    $meta_value_end_date = explode(' ', $meta_value_end, 2);
                    $meta_value_end_date[0] = str_replace('/', '-', $meta_value_end_date[0]);
                    $end_date = date_i18n(get_option('date_format'), $meta_value_end_timestamp);

                    // Add end time if available
                    if (isset($meta_value_end_date[1])) {
                        $time_end = str_replace('-', '', $meta_value_end_date[1]);
                        $end_date .= esc_html__(' at ', 'listeo_core');
                        $end_date .= date_i18n(get_option('time_format'), strtotime($time_end));
                    }

                    $date .= ' - ' . $end_date;
                }
            } else {
                // Fallback to booking dates if event dates not set
                $date = date_i18n(get_option('date_format'), strtotime($data['date_start']));
                if ($data['date_end'] && $data['date_end'] != $data['date_start']) {
                    $date .= ' - ' . date_i18n(get_option('date_format'), strtotime($data['date_end']));
                }
            }
        }

        // Fallback for any other booking type or custom types
        else {
            $date = date_i18n(get_option('date_format'), strtotime($data['date_start']));
            if ($data['date_end'] && $data['date_end'] != $data['date_start']) {
                $date .= ' - ' . date_i18n(get_option('date_format'), strtotime($data['date_end']));
            }
        }

        $name = array();

        if (isset($details->first_name) || isset($details->last_name)) :
            if (isset($details->first_name))
                $name[] = esc_html(stripslashes($details->first_name));
            if (isset($details->last_name))
                $name[] = esc_html(stripslashes($details->last_name));
        endif;

        $name = implode(' ', $name);
        $children = '';
        $guests = '';
        $tickets = '';
        if (isset($details->children) && $details->children > 0) :
            $children = sprintf(_n('%d Child', '%s Children', $details->children, 'listeo_core'), $details->children);
        endif;
        // Only surface a "Guests" line when the party size is actually
        // greater than one. With a single adult, the Attendee row
        // already conveys "1 person"; printing "Guests: 1 Guest" reads
        // as if there's an additional guest accompanying the attendee
        // and customers consistently flag it as confusing.
        if (isset($details->adults)  && $details->adults > 1) :
            $guests = sprintf(_n('%d Guest', '%s Guests', $details->adults, 'listeo_core'), $details->adults);
        endif;
        if (isset($details->tickets)  && $details->tickets > 0) :
            $tickets = sprintf(_n('%d Ticket', '%s Tickets', $details->tickets, 'listeo_core'), $details->tickets);
        endif;

        if ($data['price'] > 0) :
            $price = listeo_output_price($data['price']);
        else :
            $price = esc_html__('Free', 'listeo_core');
        endif;

        if (isset($details->service)) {
            $extra_services = $details->service;
        } else {
            $extra_services = '';
        }


        /**
         * Allow add-ons to override the resolved event date for ticket display.
         *
         * @param string $date       Resolved date string.
         * @param int    $booking_id Booking ID.
         * @param array  $data       Raw booking row from wp_bookings_calendar.
         * @param object $details    Decoded comment JSON.
         */
        $date = apply_filters( 'listeo_ticket_event_date', $date, $booking_id, $data, $details );

        $owner_email = get_the_author_meta('user_email', $data['owner_id']);
        $owner_phone = get_the_author_meta('phone', $data['owner_id']);
        // Implement this method according to your booking system
        return array(
            'title'     => get_the_title($listing_id),
            'listing_id' => $listing_id,
            'date'      => $date,
            'address'  => get_post_meta($listing_id, '_address', true),
            'latitude'  => get_post_meta($listing_id, '_geolocation_lat', true),
            'longitude' => get_post_meta($listing_id, '_geolocation_long', true),
            'phone'     => get_post_meta($listing_id, '_phone', true),
            'email'     => get_post_meta($listing_id, '_email', true),
            'name'      => $name,
            'children' => $children,
            'guests'    => $guests,
            'tickets'   => $tickets,
            'id'        => $booking_id,
            'price'     => $price,
            'owner_email' => $owner_email,
            'owner_phone' => $owner_phone,
            'order_id'  => $data['order_id'],
            'extra_services' => $extra_services
        );
    }
}
