<?php
// Exit if accessed directly
if (! defined('ABSPATH'))
	exit;
/**
 * listeo_core_listing class
 */
class Listeo_Core_Emails
{

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.0
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.0
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

	public function __construct()
	{

		add_action('listeo_core_listing_submitted', array($this, 'new_listing_email'));
		add_action('listeo_core_listing_submitted', array($this, 'new_listing_email_admin'));
		add_action('listeo_core_listing_edited', 	 array($this, 'new_listing_email_admin'));
		add_action('listeo_core_expired_listing', 	 array($this, 'expired_listing_email'));
		add_action('listeo_core_expiring_soon_listing', array($this, 'expiring_soon_listing_email'));

		add_action('pending_to_publish', array($this, 'published_listing_email'));
		add_action('pending_payment_to_publish', array($this, 'published_listing_email'));
		add_action('preview_to_publish', array($this, 'published_listing_email'));
		// add_action( 'draft_to_publish', array( $this, 'published_listing_email' ) );
		// add_action( 'auto-draft_to_publish', array( $this, 'published_listing_email' ) );
		// add_action( 'expired_to_publish', array( $this, 'published_listing_email' ) );

	add_action('pending_to_rejected', array($this, 'rejected_listing_email'));
	add_action('publish_to_rejected', array($this, 'rejected_listing_email'));
	add_action('expired_to_rejected', array($this, 'rejected_listing_email'));
	add_action('preview_to_rejected', array($this, 'rejected_listing_email'));
	add_action('pending_payment_to_rejected', array($this, 'rejected_listing_email'));

		add_action('comment_post', array($this, 'notify_owner_review_email'));

		add_action('listeo_welcome_mail', array($this, 'welcome_mail'));

		//booking emails
		add_action('listeo_mail_to_user_waiting_approval', array($this, 'mail_to_user_waiting_approval'));
		add_action('listeo_mail_to_user_instant_approval', array($this, 'mail_to_user_instant_approval'));
		add_action('listeo_mail_to_user_free_confirmed', array($this, 'mail_to_user_free_confirmed'));
		add_action('listeo_mail_to_user_pay_cash_confirmed', array($this, 'mail_to_user_pay_cash_confirmed'));

		add_action('listeo_mail_to_owner_new_reservation', array($this, 'mail_to_owner_new_reservation'));
		add_action('listeo_mail_to_owner_new_instant_reservation', array($this, 'mail_to_owner_new_instant_reservation'));

		add_action('listeo_mail_to_user_canceled', array($this, 'mail_to_user_canceled'));
		add_action('listeo_mail_to_owner_canceled', array($this, 'mail_to_owner_canceled'));

		add_action('listeo_mail_to_user_pay', array($this, 'mail_to_user_pay'));
		add_action('listeo_mail_to_owner_paid', array($this, 'mail_to_owner_paid'));
		add_action('listeo_mail_to_user_paid', array($this, 'mail_to_user_paid'));

		add_action('listeo_mail_zoom_meeting_invitation', array($this, 'mail_zoom_meeting_invitation'));

		add_action('listeo_mail_to_user_upcoming_booking', array($this, 'mail_to_user_upcoming_booking'));

		add_action('listeo_mail_to_user_new_conversation', array($this, 'new_conversation_mail'));
		add_action('listeo_mail_to_user_new_message', array($this, 'new_message_mail'));

		add_action('listeo_mail_to_user_past_booking', array($this, 'mail_to_user_review_reminder'));

		add_action('listeo_mail_to_user_claim_approved', array($this, 'mail_to_user_claim_approved'));
		add_action('listeo_mail_to_user_claim_rejected', array($this, 'mail_to_user_claim_rejected'));
		add_action('listeo_mail_to_user_claim_pending', array($this, 'mail_to_user_claim_pending'));
		add_action('listeo_mail_to_user_claim_completed', array($this, 'mail_to_owner_claim_completed'));

		add_action('listeo_mail_to_admin_claim_request', array($this, 'mail_to_admin_claim_request'));

		add_action('listeo_otp_mail', array($this, 'otp_mail'));
	}

	function otp_mail($args)
	{

		$email = $args['email'];

		// Generate secure 4-digit OTP (stronger than 4 digits)
		$otp = wp_rand(1000, 9999);
		

		$transient_key = 'otp_' . $email; // Create a unique transient key
		delete_transient($transient_key); // Delete any existing transients
		set_transient($transient_key, $otp, 5 * MINUTE_IN_SECONDS); // Store OTP for 5 minutes

		$args = array(

			'otp' 	=> $otp,
		);

		$subject 	 = get_option('listeo_otp_email_subject', 'Authenticate Your Email Address');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 =  get_option('listeo_otp_email_content', "Hi {user_name},<br>
                    Your OTP code is {otp}.<br>
                    <br>
                    Thank you.
                    <br>");
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body);
	}

	function mail_to_user_review_reminder($args)
	{
		if (!get_option('listeo_listing_remind_review_mail')) {
			return;
		}

		$booking_data = $this->get_booking_data_emails($args);

		$booking = $args;
		if (get_booking_meta($booking['ID'], 'user_review_reminder')) {
			return;
		}
		$email = get_the_author_meta('user_email', $booking['bookings_author']);
		// check if user has opt out from emails
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['user_message'])) ? $booking_data['user_message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_listing_remind_review_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 =  get_option('listeo_listing_remind_review_email_content');
		$body 	 = $this->replace_shortcode($args, $body);

		self::send($email, $subject, $body, 'listeo_listing_remind_review_mail_send_admin_copy');
		add_booking_meta($booking['ID'], 'user_review_reminder', 'sent');
	}
	function mail_to_user_upcoming_booking($args)
	{
		if (!get_option('listeo_user_booking_reminder_status')) {
			return;
		}

		$booking_data = $this->get_booking_data_emails($args);

		$booking = $args;
		if (get_booking_meta($booking['ID'], 'user_notification_upcoming_booking')) {
			return;
		}
		$email = get_the_author_meta('user_email', $booking['bookings_author']);
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'email_message'  => get_post_meta($booking['listing_id'], '_email_message', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['user_message'])) ? $booking_data['user_message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_user_booking_reminder_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 =  get_option('listeo_user_booking_reminder_email_content');
		$body 	 = $this->replace_shortcode($args, $body);

		self::send($email, $subject, $body, 'listeo_user_booking_reminder_status_send_admin_copy');
		add_booking_meta($booking['ID'], 'user_notification_upcoming_booking', 'sent');
	}

	function notify_owner_review_email($comment_id)
	{

		$comment = get_comment($comment_id);

		$post_id = $comment->comment_post_ID;

		$post = get_post($post_id);
		if ($post->post_type !== 'listing') {
			return;
		}


		if (!get_option('listeo_listing_new_review_mail')) {
			return;
		}

		$author   	= 	get_userdata($post->post_author);
		// user id
		$email 		=  $author->data->user_email;
		if (get_the_author_meta('email_notifications', $post->post_author) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> $author->display_name,
			'user_mail' 	=> $email,
			'listing_date' => $post->post_date,
			'listing_name' => $post->post_title,
			'listing_url'  => get_permalink($post->ID),
		);

		$subject 	 = get_option('listeo_listing_new_review_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_listing_new_review_email_content');
		$body 	 = $this->replace_shortcode($args, $body);

		self::send($email, $subject, $body);
	}


	function new_listing_email($post_id)
	{
		$post = get_post($post_id);
		if ($post->post_type !== 'listing') {
			return;
		}


		if (!get_option('listeo_listing_new_email')) {
			return;
		}


		$is_send = get_post_meta($post->ID, 'new_listing_email_notification', true);
		if ($is_send) {
			return;
		}

		$author   	= 	get_userdata($post->post_author);
		$email 		=  $author->data->user_email;
		if (get_the_author_meta('email_notifications', $post->post_author) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> $author->display_name,
			'user_mail' 	=> $email,
			'listing_date' => $post->post_date,
			'listing_name' => $post->post_title,
			'listing_url'  => get_permalink($post->ID),
		);

		$subject 	 = get_option('listeo_listing_new_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_listing_new_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		update_post_meta($post->ID, 'new_listing_email_notification', 'sent');
		self::send($email, $subject, $body);
	}

	function new_listing_email_admin($post_id)
	{
		$post = get_post($post_id);

		if ($post->post_type !== 'listing') {
			return;
		}
		if ($post->post_status !== 'pending') {
			return;
		}

		if (!get_option('listeo_new_listing_admin_notification')) {
			return;
		}

		// Get last notification timestamp
		$last_notification = get_post_meta($post_id, '_last_admin_notification', true);
		$current_time = current_time('timestamp');

		// If last notification was less than 2 hours ago, skip
		if ($last_notification && ($current_time - $last_notification < 2 * HOUR_IN_SECONDS)) {
			return;
		}

		$email = get_option('admin_email');
		$email = apply_filters('listeo_new_listing_email_recipient', $email);

		$args = array(
			'user_mail'     => $email,
			'listing_name'  => $post->post_title,
		);

		$subject = esc_html__('There is new listing waiting for approval', 'listeo_core');
		$subject = $this->replace_shortcode($args, $subject);

		$body = esc_html__('There is listing waiting for your approval "{listing_name}"', 'listeo_core');
		$body = $this->replace_shortcode($args, $body);

		// Update the last notification timestamp
		update_post_meta($post_id, '_last_admin_notification', $current_time);

		self::send($email, $subject, $body);
	}

	function published_listing_email($post)
	{
		if ($post->post_type != 'listing') {
			return;
		}

		if (!get_option('listeo_listing_published_email')) {
			return;
		}
		if (get_post_meta($post->ID, 'listeo_published_mail_send', true) == "sent") {
			return;
		}
		$author   	= 	get_userdata($post->post_author);
		$email 		=  $author->data->user_email;
		if (get_the_author_meta('email_notifications', $post->post_author) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> $author->display_name,
			'user_mail' 	=> $email,
			'listing_date' => $post->post_date,
			'listing_name' => $post->post_title,
			'listing_url'  => get_permalink($post->ID),
		);

		$subject 	 = get_option('listeo_listing_published_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_listing_published_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		update_post_meta($post->ID, 'listeo_published_mail_send', 'sent');
		self::send($email, $subject, $body);
	}

	/**
	 * Send email notification when listing is rejected
	 */
	function rejected_listing_email($post)
	{
		// Debug logging
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('Listeo: rejected_listing_email triggered for post ID: ' . $post->ID);
		}

		if ($post->post_type != 'listing') {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Listeo: Email skipped - not a listing post type');
			}
			return;
		}

		// Check if email is enabled
		if (!get_option('listeo_listing_rejected_email')) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Listeo: Email skipped - setting not enabled. Go to Listeo Settings > Email Settings and enable "Listing Rejected Email"');
			}
			return;
		}

		// Get rejection reason
		$rejection_reason = get_post_meta($post->ID, '_listing_rejection_reason', true);
		if (empty($rejection_reason)) {
			$rejection_reason = __('No reason provided', 'listeo_core');
		}

		// Get listing author
		$author = get_userdata($post->post_author);
		if (!$author) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Listeo: Email skipped - author not found');
			}
			return;
		}

		$email = $author->data->user_email;

		// Check if user has email notifications disabled
		if (get_the_author_meta('email_notifications', $post->post_author) == 'on') {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Listeo: Email skipped - user has email notifications disabled');
			}
			return;
		}

		// Prevent duplicate emails (check transient)
		$transient_key = 'listeo_rejected_email_sent_' . $post->ID;
		if (get_transient($transient_key)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Listeo: Email skipped - already sent (transient active). Wait 1 hour or delete transient: ' . $transient_key);
			}
			return;
		}
		set_transient($transient_key, true, HOUR_IN_SECONDS);

		// Get dashboard page URL
		$dashboard_page_id = get_option('listeo_dashboard_page');
		$listing_dashboard_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url();

		// Build email args
		$args = array(
			'user_name'             => $author->display_name,
			'user_mail'             => $email,
			'listing_date'          => $post->post_date,
			'listing_name'          => $post->post_title,
			'listing_url'           => get_permalink($post->ID),
			'listing_dashboard_url' => $listing_dashboard_url,
			'rejection_reason'      => $rejection_reason
		);

		$subject = get_option('listeo_listing_rejected_email_subject', __('Your listing has been rejected', 'listeo_core'));
		$subject = $this->replace_shortcode($args, $subject);

		$body = get_option('listeo_listing_rejected_email_content', 
			__('Hi {user_name},<br><br>Your listing "{listing_name}" has been rejected.<br><br><strong>Reason:</strong> {rejection_reason}<br><br>Please review the feedback and resubmit your listing if appropriate.<br><br>View your listings: {listing_dashboard_url}', 'listeo_core')
		);
		$body = $this->replace_shortcode($args, $body);

		self::send($email, $subject, $body);
	}

	function expired_listing_email($post_id)
	{
		$post = get_post($post_id);
		if ($post->post_type !== 'listing') {
			return;
		}

		if (!get_option('listeo_listing_expired_email')) {
			return;
		}

		$author   	= 	get_userdata($post->post_author);
		$email 		=  $author->data->user_email;
		if (get_the_author_meta('email_notifications', $post->post_author) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> $author->display_name,
			'user_mail' 	=> $email,
			'listing_date' => $post->post_date,
			'listing_name' => $post->post_title,
			'listing_url'  => get_permalink($post->ID),
		);

		$subject 	 = get_option('listeo_listing_expired_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_listing_expired_email_content');
		$body 	 = $this->replace_shortcode($args, $body);

		self::send($email, $subject, $body);
	}

	function expiring_soon_listing_email($post_id)
	{
		$post = get_post($post_id);
		if ($post->post_type !== 'listing') {
			return;
		}
		// check post status
		if ($post->post_status !== 'publish') {
			return;
		}
		$already_sent = get_post_meta($post_id, 'notification_email_sent', true);
		if ($already_sent) {
			return;
		}

		if (!get_option('listeo_listing_expiring_soon_email')) {
			return;
		}

		$author   	= 	get_userdata($post->post_author);
		$email 		=  $author->data->user_email;
		if (get_the_author_meta('email_notifications', $post->post_author) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> $author->display_name,
			'user_mail' 	=> $email,
			'listing_date' => $post->post_date,
			'listing_name' => $post->post_title,
			'listing_url'  => get_permalink($post->ID),
		);

		$subject 	 = get_option('listeo_listing_expiring_soon_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_listing_expiring_soon_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		add_post_meta($post_id, 'notification_email_sent', true);
		self::send($email, $subject, $body);
	}

	// booking emails
	// [email] => admin@listeo.com
	//   [booking] => Array
	//       (
	//           [ID] => 4
	//           [bookings_author] => 1
	//           [owner_id] => 1
	//           [listing_id] => 604
	//           [date_start] => 2019-01-16 09:00:00
	//           [date_end] => 2019-01-16 11:00:00
	//           [comment] => {"first_name":"\u0141ukasz asdas","last_name":"Girek asdas d","email":"admin@listeo.com","phone":"+48 0500389009","children":"0","adults":"2"}
	//           [order_id] => 
	//           [status] => 
	//           [type] => reservation
	//           [created] => 2019-01-02
	//           [expiring] => 
	//           [price] => 75
	//       )

	function mail_to_user_waiting_approval($args)
	{

		if (!get_option('listeo_booking_user_waiting_approval_email')) {
			return;
		}

		$email 		=  $args['email'];

		$booking_data = $this->get_booking_data_emails($args['booking']);

		$booking = $args['booking'];
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['user_message'])) ? $booking_data['user_message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_booking_user_waiting_approval_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 =  get_option('listeo_booking_user_waiting_approval_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_booking_user_waiting_approval_email_send_admin_copy');
	}


	function mail_to_user_instant_approval($args)
	{
		if (!get_option('listeo_instant_booking_user_waiting_approval_email')) {
			return;
		}
		$email 		=  $args['email'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		$booking = $args['booking'];
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'email_message'  => get_post_meta($booking['listing_id'], '_email_message', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_instant_booking_user_waiting_approval_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 =  get_option('listeo_instant_booking_user_waiting_approval_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_instant_booking_user_waiting_approval_email_send_admin_copy');
	}


	function mail_to_owner_new_instant_reservation($args)
	{

		if (!get_option('listeo_booking_instant_owner_new_booking_email')) {
			return;
		}
		$email 		=  $args['email'];
		$booking = $args['booking'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		if (get_the_author_meta('email_notifications', $booking['owner_id']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['owner_id']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'email_message'  => get_post_meta($booking['listing_id'], '_email_message', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_booking_instant_owner_new_booking_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_booking_instant_owner_new_booking_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_booking_instant_owner_new_booking_email_send_admin_copy');
	}

	function mail_to_owner_new_reservation($args)
	{
		if (!get_option('listeo_booking_owner_new_booking_email')) {
			return;
		}
		$email 		=  $args['email'];
		$booking = $args['booking'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		if (get_the_author_meta('email_notifications', $booking['owner_id']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['owner_id']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_booking_owner_new_booking_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_booking_owner_new_booking_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_booking_owner_new_booking_email_send_admin_copy');
	}

	function mail_to_user_canceled($args)
	{
		if (!get_option('listeo_booking_user_cancallation_email')) {
			return;
		}
		$email 		=  $args['email'];
		$booking = $args['booking'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_booking_user_cancellation_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_booking_user_cancellation_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_booking_user_cancallation_email_send_admin_copy');
	}
	function mail_to_owner_canceled($args)
	{
		if (!get_option('listeo_booking_owner_cancallation_email')) {
			return;
		}
		$email 		=  $args['email'];
		$booking = $args['booking'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['owner_id']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_booking_owner_cancellation_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_booking_owner_cancellation_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_booking_owner_cancallation_email_send_admin_copy');
	}

	function mail_to_user_free_confirmed($args)
	{
		if (!get_option('listeo_free_booking_confirmation')) {
			return;
		}

		$email 		=  $args['email'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		$booking = $args['booking'];
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'email_message'  => get_post_meta($booking['listing_id'], '_email_message', true),
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_free_booking_confirmation_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_free_booking_confirmation_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_free_booking_confirmation_send_admin_copy');
	}

	function mail_to_user_pay_cash_confirmed($args)
	{
		if (!get_option('listeo_mail_to_user_pay_cash_confirmed')) {
			return;
		}

		$email 		=  $args['email'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		$booking = $args['booking'];
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'email_message'  => get_post_meta($booking['listing_id'], '_email_message', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_mail_to_user_pay_cash_confirmed_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_mail_to_user_pay_cash_confirmed_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_mail_to_user_pay_cash_confirmed_send_admin_copy');
	}

	function mail_to_user_pay($args)
	{
		if (!get_option('listeo_pay_booking_confirmation_user')) {
			return;
		}
		$email 		=  $args['email'];
		$booking_data = $this->get_booking_data_emails($args['booking']);

		$booking = $args['booking'];
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'payment_url'  => $args['payment_url'],
			'expiration'  => $args['expiration'],
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_pay_booking_confirmation_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_pay_booking_confirmation_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_pay_booking_confirmation_user_send_admin_copy');
	}

	function mail_to_owner_paid($args)
	{
		if (!get_option('listeo_paid_booking_confirmation')) {
			return;
		}
		$email 		=  $args['email'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		$booking = $args['booking'];
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['owner_id']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'email_message'  => get_post_meta($booking['listing_id'], '_email_message', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'order_id' => (isset($booking['order_id'])) ? $booking['order_id'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_paid_booking_confirmation_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_paid_booking_confirmation_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_paid_booking_confirmation_send_admin_copy');
	}


	function mail_to_user_paid($args)
	{
		if (!get_option('listeo_user_paid_booking_confirmation')) {
			return;
		}
		$email 		=  $args['email'];
		$booking_data = $this->get_booking_data_emails($args['booking']);
		$booking = $args['booking'];
		if (get_the_author_meta('email_notifications', $booking['bookings_author']) == 'on') {
			return;
		}
		$args = array(
			'user_name' 	=> get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail' 	=> $email,
			'booking_date' => $booking['created'],
			'listing_name' => get_the_title($booking['listing_id']),
			'listing_url'  => get_permalink($booking['listing_id']),
			'listing_address'  => get_post_meta($booking['listing_id'], '_address', true),
			'listing_latitude'  => get_post_meta($booking['listing_id'], '_geolocation_lat', true),
			'listing_longitude'  => get_post_meta($booking['listing_id'], '_geolocation_long', true),
			'listing_phone'  => get_post_meta($booking['listing_id'], '_phone', true),
			'listing_email'  => get_post_meta($booking['listing_id'], '_email', true),
			'email_message'  => get_post_meta($booking['listing_id'], '_email_message', true),
			'dates' => (isset($booking_data['dates'])) ? $booking_data['dates'] : '',
			'details' => (isset($booking_data['details'])) ? $booking_data['details'] : '',
			'service' => (isset($booking_data['service'])) ? $booking_data['service'] : '',
			'tickets' => (isset($booking_data['tickets'])) ? $booking_data['tickets'] : '',
			'adults' => (isset($booking_data['adults'])) ? $booking_data['adults'] : '',
			'children' => (isset($booking_data['children'])) ? $booking_data['children'] : '',
			'user_message' => (isset($booking_data['message'])) ? $booking_data['message'] : '',
			'client_first_name' => (isset($booking_data['client_first_name'])) ? $booking_data['client_first_name'] : '',
			'client_last_name' => (isset($booking_data['client_last_name'])) ? $booking_data['client_last_name'] : '',
			'client_email' => (isset($booking_data['client_email'])) ? $booking_data['client_email'] : '',
			'client_phone' => (isset($booking_data['client_phone'])) ? $booking_data['client_phone'] : '',
			'billing_address' => (isset($booking_data['billing_address'])) ? $booking_data['billing_address'] : '',
			'billing_postcode' => (isset($booking_data['billing_postcode'])) ? $booking_data['billing_postcode'] : '',
			'billing_city' => (isset($booking_data['billing_city'])) ? $booking_data['billing_city'] : '',
			'billing_country' => (isset($booking_data['billing_country'])) ? $booking_data['billing_country'] : '',
			'price' => (isset($booking['price'])) ? $booking['price'] : '',
			'expiring' => (isset($booking['expiring'])) ? $booking['expiring'] : '',
			'order_id' => (isset($booking['order_id'])) ? $booking['order_id'] : '',
			'booking_id' => (isset($booking['ID'])) ? (int) $booking['ID'] : '',
			'listing_id' => (isset($booking['listing_id'])) ? (int) $booking['listing_id'] : '',
		);

		$subject 	 = get_option('listeo_user_paid_booking_confirmation_email_subject');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_user_paid_booking_confirmation_email_content');
		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body, 'listeo_user_paid_booking_confirmation_send_admin_copy');
	}

	/**
	 * Send Zoom meeting invitation email
	 *
	 * @param array $args Email arguments with booking and zoom_meeting data
	 */
	function mail_zoom_meeting_invitation($args)
	{
		// Debug logging helper
		$debug_log = function($message, $context = array()) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				$log_message = 'Zoom Invitation Email: ' . $message;
				if (!empty($context)) {
					$log_message .= ' | Context: ' . wp_json_encode($context);
				}
				error_log($log_message);
			}
		};

		$debug_log('Email handler called');

		if (!get_option('listeo_zoom_meeting_invitation_email_enabled', true)) {
			$debug_log('Email disabled via option listeo_zoom_meeting_invitation_email_enabled');
			return;
		}

		if (!isset($args['email']) || !isset($args['booking']) || !isset($args['zoom_meeting'])) {
			$debug_log('Missing required arguments', array(
				'has_email' => isset($args['email']),
				'has_booking' => isset($args['booking']),
				'has_zoom_meeting' => isset($args['zoom_meeting']),
			));
			return;
		}

		$email = $args['email'];
		$booking = $args['booking'];
		$zoom_meeting = $args['zoom_meeting'];

		$debug_log('Processing email', array(
			'recipient' => $email,
			'booking_id' => $booking['ID'],
			'zoom_meeting_id' => $zoom_meeting['id'],
		));

		// Don't send if user has notifications disabled
		$email_notifications = get_the_author_meta('email_notifications', $booking['bookings_author']);
		if ($email_notifications == 'on') {
			$debug_log('Email notifications disabled for user', array(
				'user_id' => $booking['bookings_author'],
				'email_notifications' => $email_notifications,
			));
			return;
		}

		$booking_data = $this->get_booking_data_emails($booking);

		// Format meeting start time
		$meeting_date_obj = new DateTime($booking['date_start'], wp_timezone());
		$meeting_date = $meeting_date_obj->format(get_option('date_format') . ' ' . get_option('time_format'));

		$email_args = array(
			'user_name'          => get_the_author_meta('display_name', $booking['bookings_author']),
			'user_mail'          => $email,
			'listing_name'       => get_the_title($booking['listing_id']),
			'listing_url'        => get_permalink($booking['listing_id']),
			'zoom_join_url'      => $zoom_meeting['join_url'],
			'zoom_meeting_id'    => $zoom_meeting['id'],
			'zoom_password'      => isset($zoom_meeting['password']) ? $zoom_meeting['password'] : '',
			'zoom_start_url'     => isset($zoom_meeting['start_url']) ? $zoom_meeting['start_url'] : '',
			'meeting_date'       => $meeting_date,
			'meeting_topic'      => $zoom_meeting['topic'],
			'booking_id'         => $booking['ID'],
			'client_first_name'  => isset($booking_data['client_first_name']) ? $booking_data['client_first_name'] : '',
			'client_last_name'   => isset($booking_data['client_last_name']) ? $booking_data['client_last_name'] : '',
			'dates'              => isset($booking_data['dates']) ? $booking_data['dates'] : '',
		);

		$subject = get_option('listeo_zoom_meeting_invitation_email_subject', __('Your Zoom Meeting for {listing_name}', 'listeo_core'));
		$subject = $this->replace_shortcode($email_args, $subject);

		$default_body = $this->get_default_zoom_invitation_email_body();
		$body = get_option('listeo_zoom_meeting_invitation_email_content', $default_body);
		$body = $this->replace_shortcode($email_args, $body);

		$debug_log('Sending email', array(
			'to' => $email,
			'subject' => $subject,
			'join_url' => $zoom_meeting['join_url'],
		));

		$result = self::send($email, $subject, $body);

		$debug_log('Email send result', array(
			'success' => $result,
			'to' => $email,
		));
	}

	/**
	 * Get default Zoom invitation email body
	 *
	 * @return string Default email body
	 */
	private function get_default_zoom_invitation_email_body()
	{
		$body = '<p>' . __('Hi {client_first_name},', 'listeo_core') . '</p>';
		$body .= '<p>' . __('Great news! Your Zoom meeting for <strong>{listing_name}</strong> is ready.', 'listeo_core') . '</p>';
		$body .= '<h3>' . __('Meeting Details', 'listeo_core') . '</h3>';
		$body .= '<p><strong>' . __('Date & Time:', 'listeo_core') . '</strong> {meeting_date}</p>';
		$body .= '<p><strong>' . __('Topic:', 'listeo_core') . '</strong> {meeting_topic}</p>';
		$body .= '<p><strong>' . __('Meeting ID:', 'listeo_core') . '</strong> {zoom_meeting_id}</p>';
		$body .= '<p><strong>' . __('Password:', 'listeo_core') . '</strong> {zoom_password}</p>';
		$body .= '<div style="margin: 30px 0; text-align: center;">';
		$body .= '<a href="{zoom_join_url}" style="background-color: #2997F8; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">' . __('Join Zoom Meeting', 'listeo_core') . '</a>';
		$body .= '</div>';
		$body .= '<p style="color: #666; font-size: 12px;">' . __('Or copy and paste this URL into your browser:', 'listeo_core') . '<br>{zoom_join_url}</p>';
		$body .= '<hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">';
		$body .= '<h4>' . __('First time using Zoom?', 'listeo_core') . '</h4>';
		$body .= '<ol>';
		$body .= '<li>' . __('Click the "Join Zoom Meeting" button above 5-10 minutes before your scheduled time', 'listeo_core') . '</li>';
		$body .= '<li>' . __('If prompted, download and install the Zoom app', 'listeo_core') . '</li>';
		$body .= '<li>' . __('Enter the meeting password if required', 'listeo_core') . '</li>';
		$body .= '<li>' . __('Allow Zoom to access your camera and microphone', 'listeo_core') . '</li>';
		$body .= '</ol>';
		$body .= '<p>' . __('Looking forward to meeting you!', 'listeo_core') . '</p>';
		$body .= '<p>' . __('Best regards,<br>The {listing_name} Team', 'listeo_core') . '</p>';

		return $body;
	}

	function welcome_mail($args)
	{
		if (get_option('listeo_welcome_email_disable')) {
			return;
		}

		$email 		=  $args['email'];

		// Check if email was already sent
		// we dont have user_id here, can we get it by user_email?
		$user = get_user_by('email', $email);
		$user_id = $user ? $user->ID : 0;

		$email_sent = get_user_meta($user_id, 'welcome_email_sent', true);
		if ($email_sent) {
			return;
		}

		$args = array(
			'email'         => $email,
			'login'         => $args['login'],
			'password'      => $args['password'],
			'first_name' 	=> $args['first_name'],
			'last_name' 	=> $args['last_name'],
			'user_name' 	=> $args['display_name'],
			'user_mail' 	=> $email,
			'login_url' 	=> $args['login_url'],

		);
		$subject 	 = get_option('listeo_listing_welcome_email_subject', 'Welcome to {site_name}!');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_listing_welcome_email_content', 'Welcome to {site_name}! You can log in {login_url}, your username: "{login}", and password: "{password}".');

		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body);

		// Set email sent flag
		update_user_meta($user_id, 'welcome_email_sent', true);
	}


	/**
	 * @param mixed $args 
	 * @return void 
	 */
	function new_conversation_mail($args)
	{


		if (!get_option('listeo_new_conversation_notification')) {
			return;
		}
		$conversation_id = $args['conversation_id'];
		//{user_mail},{user_name},{listing_name},{listing_url},{site_name},{site_url}
		global $wpdb;

		$conversation_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}listeo_core_conversations` WHERE id = %d",
				(int)$conversation_id
			)
		);

		$read_user_1 = $conversation_data[0]->read_user_1;
		if ($read_user_1 == 0) {
			$user_who_send = $conversation_data[0]->user_2;
			$user_to_notify = $conversation_data[0]->user_1;
		}
		$read_user_2 = $conversation_data[0]->read_user_2;
		if ($read_user_2 == 0) {
			$user_who_send = $conversation_data[0]->user_1;
			$user_to_notify = $conversation_data[0]->user_2;
		}


		$user_to_notify_data   	= 	get_userdata($user_to_notify);
		$email 		=  $user_to_notify_data->user_email;
		if (get_the_author_meta('email_notifications', $user_to_notify) == 'on') {
			return;
		}
		$user_who_send_data = get_userdata($user_who_send);
		$sender = $user_who_send_data->first_name;
		if (empty($sender)) {
			$sender = $user_who_send_data->nickname;
		}
		// ["id"]=> string(2) "36" ["timestamp"]=> string(10) "1573163130" ["user_1"]=> string(1) "1" ["user_2"]=> string(2) "14" ["referral"]=> string(14) "author_archive" ["read_user_1"]=> string(1) "1" ["read_user_2"]=> string(1) "0" ["last_update"]=> string(10) "1573172773"

		$args = array(
			'user_mail'     => $email,
			'user_name' 	=> $user_to_notify_data->first_name,
			'conversation_url' => get_permalink(get_option('listeo_messages_page')) . '?action=view&conv_id=' . $conversation_id,
			'sender'		=> $sender,
		);
		$subject 	 = get_option('listeo_new_conversation_notification_email_subject', 'You got new conversation!');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_new_conversation_notification_email_content', "Hi {user_name},<br>
                    There's a new conversation from {sender} waiting for your on {site_name}.<br> Check it  <a href='{conversation_url}'>here</a>
                    <br>Thank you");

		$body 	 = $this->replace_shortcode($args, $body);
		self::send($email, $subject, $body);
	}


	function mail_to_user_claim_approved($args)
	{

		$user_to_notify_data   	= 	get_userdata($args['user_id']);
		$email 		=  $user_to_notify_data->user_email;


		$email_args = array(
			'user_name' 	=> get_the_author_meta('display_name', $args['claim_id']),
			'user_email' 	=> $email,
			'first_name' 	=> get_post_meta($args['claim_id'], 'firstname', true),
			'last_name' 	=> get_post_meta($args['claim_id'], 'lastname', true),
			'listing_name' 	=> get_the_title($args['listing_id']),
			'listing_url'  	=> get_permalink($args['listing_id']),
			'order_id' 		=> $args['order_id'],
			'payment_url'  	=> $args['payment_url'],
		);

		$subject 	 = get_option('listeo_claim_approved_notification_email_subject', 'Your claim was approved');
		$subject 	 = $this->replace_shortcode($email_args, $subject);

		$body 	 = get_option('listeo_claim_approved_notification_email_content', "Hi {user_name},<br>
                     Your claim for '{listing_name}' was approved. Please pay for the listing to manage it {payment_url}.
                    <br>Thank you");

		$body 	 = $this->replace_shortcode($email_args, $body);
		self::send($email, $subject, $body, 'listeo_claim_approved_notification_send_admin_copy');
	}

	function mail_to_user_claim_rejected($args)
	{

		$user_to_notify_data   	= 	get_userdata($args['user_id']);
		$email 		=  $user_to_notify_data->user_email;


		$email_args = array(
			'user_name' 	=> get_the_author_meta('display_name', $args['claim_id']),
			'user_email' 	=> $email,
			'first_name' 	=> get_post_meta($args['claim_id'], 'firstname', true),
			'last_name' 	=> get_post_meta($args['claim_id'], 'lastname', true),
			'listing_name' 	=> get_the_title($args['listing_id']),
			'listing_url'  	=> get_permalink($args['listing_id']),

		);

		$subject 	 = get_option('listeo_claim_rejected_notification_email_subject', 'Your claim was rejected');
		$subject 	 = $this->replace_shortcode($email_args, $subject);

		$body 	 = get_option('listeo_claim_rejected_notification_email_content', "Hi {user_name},<br>
					 Your claim for '{listing_name}' was rejected. Please contact us for more information.
					<br>Thank you");

		$body 	 = $this->replace_shortcode($email_args, $body);
		self::send($email, $subject, $body, 'listeo_claim_rejected_notification_send_admin_copy');
	}

	function mail_to_user_claim_pending($args)
	{

		$user_to_notify_data   	= 	get_userdata($args['user_id']);
		$email 		=  $user_to_notify_data->user_email;


		$email_args = array(
			'user_name' 	=> get_the_author_meta('display_name', $args['claim_id']),
			'user_email' 	=> $email,
			'first_name' 	=> get_post_meta($args['claim_id'], 'firstname', true),
			'last_name' 	=> get_post_meta($args['claim_id'], 'lastname', true),
			'listing_name' 	=> get_the_title($args['listing_id']),
			'listing_url'  	=> get_permalink($args['listing_id']),
			//'claim_url'  	=> get_edit_post_link($args['claim_id']),
		);

		$subject 	 = get_option('listeo_claim_pending_notification_email_subject', 'Your claim is pending');
		$subject 	 = $this->replace_shortcode($email_args, $subject);

		$body 	 = get_option('listeo_claim_pending_notification_email_content', "Hi {user_name},<br>
					 Your claim for '{listing_name}' is pending. We will notify you once it's approved.
					<br>Thank you");

		$body 	 = $this->replace_shortcode($email_args, $body);
		self::send($email, $subject, $body, 'listeo_claim_pending_notification_send_admin_copy');
	}

	function mail_to_owner_claim_completed($args)
	{

		$user_to_notify_data   	= 	get_userdata($args['user_id']);
		$email 		=  $user_to_notify_data->user_email;
		$email_args = array(
			'user_name' 	=> get_the_author_meta('display_name', $args['claim_id']),
			'user_email' 	=> $email,
			'first_name' 	=> get_post_meta($args['claim_id'], 'firstname', true),
			'last_name' 	=> get_post_meta($args['claim_id'], 'lastname', true),
			'listing_name' 	=> get_the_title($args['listing_id']),
			'listing_url'  	=> get_permalink($args['listing_id']),
			//'claim_url'  	=> get_edit_post_link($args['claim_id']),
		);

		$subject 	 = get_option('listeo_claim_completed_notification_email_subject', 'Your claim is pending');
		$subject 	 = $this->replace_shortcode($email_args, $subject);

		$body 	 = get_option('listeo_claim_completed_notification_email_content', "Hi {user_name},<br>
					 Your claim for '{listing_name}' is completed. You can now manage this listing.
					<br>Thank you");

		$body 	 = $this->replace_shortcode($email_args, $body);
		self::send($email, $subject, $body, 'listeo_claim_completed_notification_send_admin_copy');
	}

	function new_message_mail($id)
	{
		if (!get_option('listeo_new_message_notification')) {
			return;
		}

		$conversation_id = (int) $id;
		//{user_mail},{user_name},{listing_name},{listing_url},{site_name},{site_url}
		global $wpdb;

		$conversation_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}listeo_core_conversations` WHERE id = %d",
				$conversation_id
			)
		);

		if (empty($conversation_data)) {
			return;
		}

		$read_user_1 = $conversation_data[0]->read_user_1;
		if ($read_user_1 == 0) {
			$user_who_send = $conversation_data[0]->user_2;
			$user_to_notify = $conversation_data[0]->user_1;
		}
		$read_user_2 = $conversation_data[0]->read_user_2;
		if ($read_user_2 == 0) {
			$user_who_send = $conversation_data[0]->user_1;
			$user_to_notify = $conversation_data[0]->user_2;
		}


		$user_to_notify_data   	= 	get_userdata($user_to_notify);
		$email 		=  $user_to_notify_data->user_email;

		$user_who_send_data = get_userdata($user_who_send);
		$sender = $user_who_send_data->first_name;
		if (empty($sender)) {
			$sender = $user_who_send_data->nickname;
		}
		if (get_the_author_meta('email_notifications', $user_to_notify) == 'on') {
			return;
		}

		$args = array(
			'user_mail'     => $email,
			'user_name' 	=> $user_to_notify_data->first_name,
			'sender'		=> $sender,
			'conversation_url' => get_permalink(get_option('listeo_messages_page')) . '?action=view&conv_id=' . $conversation_id,
		);
		$subject 	 = get_option('listeo_new_message_notification_email_subject', 'You got new conversation!');
		$subject 	 = $this->replace_shortcode($args, $subject);

		$body 	 = get_option('listeo_new_message_notification_email_content', "Hi {user_name},<br>
                    There's a new message from {sender} waiting for your on {site_name}.<br> Check it  <a href='{conversation_url}'>here</a>
                    <br>Thank you");

		$body 	 = $this->replace_shortcode($args, $body);

		$result  = $wpdb->update(
			$wpdb->prefix . 'listeo_core_conversations',
			array('notification'  => 'sent'),
			array('id' => $conversation_id)
		);

		if ($result) {
			self::send($email, $subject, $body);
		}

	}

	function mail_to_admin_claim_request($args)
	{
		$email 		=  get_option('admin_email');
		$email_args = array(
			'user_name' 	=> get_the_author_meta('display_name', $args['claim_id']),
			'user_email' 	=> $email,
			'first_name' 	=> get_post_meta($args['claim_id'], 'firstname', true),
			'last_name' 	=> get_post_meta($args['claim_id'], 'lastname', true),
			'listing_name' 	=> get_the_title($args['listing_id']),
			'listing_url'  	=> get_permalink($args['listing_id']),
			//'claim_url'  	=> get_edit_post_link($args['claim_id']),
		);
		$subject 	 = get_option('listeo_claim_request_notification_email_subject', 'New claim request');
		$subject 	 = $this->replace_shortcode($email_args, $subject);

		$body 	 = get_option('listeo_claim_request_notification_email_content', "Hi Admin,<br>
					 There's a new claim request for '{listing_name}' from {first_name} {last_name}. You will be notified when the claim is approved or rejected. 
					<br>Thank you");

		$body 	 = $this->replace_shortcode($email_args, $body);
		self::send($email, $subject, $body);
	}


	function get_booking_data_emails($args)
	{

		$booking_type = listeo_get_booking_type($args['listing_id']);
		$listing_type = get_post_meta($args['listing_id'], '_listing_type', true); // For filter compatibility
		$booking_data = array();

		// Debug logging for troubleshooting date parsing issues
		if (defined('LISTEO_DEBUG') && LISTEO_DEBUG) {
			error_log('Listeo Emails - get_booking_data_emails: Processing booking for listing ID: ' . ($args['listing_id'] ?? 'not set'));
			error_log('Listeo Emails - Booking type: ' . ($booking_type ?? 'null'));
			error_log('Listeo Emails - Date start: ' . ($args['date_start'] ?? 'not set'));
			error_log('Listeo Emails - Date end: ' . ($args['date_end'] ?? 'not set'));
		}

		switch ($booking_type) {
			case 'date_range':

				$start_date = $args['date_start'];
				$end_date = $args['date_end'];

				// Create DateTime objects from the strings
				$start_time = new DateTime($start_date);
				$end_time = new DateTime($end_date);

				// Set the timezone to the WordPress timezone
				$wp_timezone = new DateTimeZone(wp_timezone_string());
				$start_time->setTimezone($wp_timezone);
				$end_time->setTimezone($wp_timezone);

				// Format the dates using date_i18n with the timestamps from the DateTime objects
				$booking_data['dates'] = date_i18n(get_option('date_format'), $start_time->getTimestamp()) . ' - ' . date_i18n(get_option('date_format'), $end_time->getTimestamp());

				//$booking_data['dates'] = date_i18n(get_option( 'date_format' ), strtotime($args['date_start'])) .' - '. date_i18n(get_option( 'date_format' ), strtotime($args['date_end']));
				// $booking_data['dates'] =
				// date_i18n(get_option('date_format'), strtotime($args['date_start']) + 86400) .
				// ' - ' .
				// date_i18n(get_option('date_format'), strtotime($args['date_end']) + 86400);
				break;
			case 'single_day':

				$meta_value_date = explode(' ', $args['date_start'], 2);
				// if(!in_array($date_format,array('F j, Y','Y-m-d','m/d/Y','d/m/Y'))) {
				// 	$meta_value_date[0] = str_replace('/','-',$meta_value_date[0]);
				// }
				$date_format = get_option('date_format');

				//$meta_value = date_i18n(get_option( 'date_format' ), strtotime($meta_value_date[0]));
				try {
					$meta_value_stamp_obj = DateTime::createFromFormat('Y-m-d', $meta_value_date[0]);
					if ($meta_value_stamp_obj) {
						$meta_value_stamp = $meta_value_stamp_obj->getTimestamp();
						$meta_value = date_i18n(get_option('date_format'), $meta_value_stamp);
					} else {
						// Fallback if format parsing fails - try strtotime
						$fallback_stamp = strtotime($meta_value_date[0]);
						if ($fallback_stamp !== false) {
							$meta_value = date_i18n(get_option('date_format'), $fallback_stamp);
						} else {
							// Last resort - use raw value
							$meta_value = esc_html($meta_value_date[0]);
						}
					}
				} catch (Exception $e) {
					// Ultimate fallback on exception
					$meta_value = esc_html($args['date_start']);
				}

				//echo strtotime(end($meta_value_date));
				//echo date( get_option( 'time_format' ), strtotime(end($meta_value_date)));
				if (isset($meta_value_date[1])) {
					$time = str_replace('-', '', $meta_value_date[1]);
					$meta_value .= esc_html__(' at ', 'listeo_core');
					$meta_value .= date(get_option('time_format'), strtotime($time));
				}

				$booking_data['dates'] = $meta_value;
				break;
			case 'tickets':
				//$booking_data['dates'] = date(get_option( 'date_format' ), strtotime($args['date_start'])).' '.esc_html__(' at ','listeo_core').' '.date(get_option( 'time_format' ), strtotime($args['date_start']));
				$meta_value = get_post_meta($args['listing_id'], '_event_date', true);
				$meta_value_timestamp = get_post_meta($args['listing_id'], '_event_date_timestamp', true);

				$meta_value_date = explode(' ', $meta_value, 2);

				if (!empty($meta_value_timestamp)) {
					$meta_value = date_i18n(get_option('date_format'), $meta_value_timestamp);

					if (isset($meta_value_date[1])) {
						$time = str_replace('-', '', $meta_value_date[1]);
						$meta_value .= esc_html__(' at ', 'listeo_core');
						$meta_value .= date_i18n(get_option('time_format'), strtotime($time));
					}
					$booking_data['dates'] = $meta_value;
				} else {

					$meta_value_ = DateTime::createFromFormat(listeo_date_time_wp_format_php(), $meta_value_date[0]);

					if ($meta_value_ && !is_string($meta_value_)) {
						$meta_value_stamp = $meta_value_->getTimestamp();
						$meta_value = date_i18n(get_option('date_format'), $meta_value_stamp);
					} else {
						// Fallback - try to parse with strtotime
						$fallback_stamp = strtotime($meta_value_date[0]);
						if ($fallback_stamp !== false) {
							$meta_value = date_i18n(get_option('date_format'), $fallback_stamp);
						} else {
							// Last resort - use raw value
							$meta_value = esc_html($meta_value_date[0]);
						}
					}

					//echo strtotime(end($meta_value_date));
					//echo date( get_option( 'time_format' ), strtotime(end($meta_value_date)));
					if (isset($meta_value_date[1])) {
						$time = str_replace('-', '', $meta_value_date[1]);
						$meta_value .= esc_html__(' at ', 'listeo_core');
						$meta_value .= date_i18n(get_option('time_format'), strtotime($time));
					}
					$booking_data['dates'] = $meta_value;
				}
				break;

			default:
				// Generic fallback for bookings without specific type
				// or for classifieds/other listing types
				if (!empty($args['date_start'])) {
					try {
						$start_date = $args['date_start'];
						$end_date = isset($args['date_end']) ? $args['date_end'] : $start_date;

						// Create DateTime objects
						$start_time = new DateTime($start_date);
						$end_time = new DateTime($end_date);

						// Set WordPress timezone
						$wp_timezone = new DateTimeZone(wp_timezone_string());
						$start_time->setTimezone($wp_timezone);
						$end_time->setTimezone($wp_timezone);

						// Format dates
						if ($start_date === $end_date) {
							// Single date
							$booking_data['dates'] = date_i18n(get_option('date_format'), $start_time->getTimestamp());
						} else {
							// Date range
							$booking_data['dates'] = date_i18n(get_option('date_format'), $start_time->getTimestamp()) .
								' - ' .
								date_i18n(get_option('date_format'), $end_time->getTimestamp());
						}
					} catch (Exception $e) {
						// Fallback to raw dates if parsing fails
						$booking_data['dates'] = esc_html($args['date_start']);
						if (isset($args['date_end']) && $args['date_end'] !== $args['date_start']) {
							$booking_data['dates'] .= ' - ' . esc_html($args['date_end']);
						}
					}
				} else {
					// No dates available - provide a placeholder
					$booking_data['dates'] = esc_html__('Date not specified', 'listeo_core');
				}
				break;
		}

		if (isset($args['expiring'])) {
			$booking_data['expiring'] = $args['expiring'];
		}
		$booking_details = '';
		$details = json_decode($args['comment']);
		if (isset($details->children) && $details->children > 0) {
			$booking_data['children'] = sprintf(_n('%d Child', '%s Children', $details->children, 'listeo_core'), $details->children);
			$booking_details .= $booking_data['children'];
		}
		if (isset($details->adults) && $details->adults > 0) {
			$booking_data['adults'] = sprintf(_n('%d Guest', '%s Guests', $details->adults, 'listeo_core'), $details->adults);
			$booking_details .= $booking_data['adults'];
		}
		if (isset($details->tickets) && $details->tickets > 0) {
			$booking_data['tickets'] = sprintf(_n('%d Ticket', '%s Tickets', $details->tickets, 'listeo_core'), $details->tickets);
			$booking_details .= $booking_data['tickets'];
		}

		if (isset($details->service)) {
			$booking_data['service'] = listeo_get_extra_services_html($details->service);
		}

		//client data
		if (isset($details->first_name)) {
			$booking_data['client_first_name'] = $details->first_name;
		}
		if (isset($details->last_name)) {
			$booking_data['client_last_name'] = $details->last_name;
		}
		if (isset($details->email)) {
			$booking_data['client_email'] = $details->email;
		}
		if (isset($details->phone)) {
			$booking_data['client_phone'] = $details->phone;
		}


		if (isset($details->billing_address_1)) {
			$booking_data['billing_address'] = $details->billing_address_1;
		}
		if (isset($details->billing_postcode)) {
			$booking_data['billing_postcode'] = $details->billing_postcode;
		}
		if (isset($details->billing_city)) {
			$booking_data['billing_city'] = $details->billing_city;
		}
		if (isset($details->billing_country)) {
			$booking_data['billing_country'] = $details->billing_country;
		}

		if (isset($details->message)) {
			$booking_data['user_message'] = $details->message;
			$booking_data['message'] = $details->message;
		}


		if (isset($details->price)) {
			$booking_data['price'] = $details->price;
		}



		$booking_data['details'] = $booking_details;

		// Apply filter to allow developers to customize date formatting
		if (isset($booking_data['dates'])) {
			$booking_data['dates'] = apply_filters(
				'listeo_email_booking_dates',
				$booking_data['dates'],
				$args,
				$listing_type
			);
		}

		// Debug logging for final dates value
		if (defined('LISTEO_DEBUG') && LISTEO_DEBUG) {
			error_log('Listeo Emails - Final dates value: ' . ($booking_data['dates'] ?? 'NOT SET'));
		}

		return $booking_data;
	}



	/**
	 * general function to send email to agent with specify subject, body content
	 */
	public static function send($emailto, $subject, $body, $send_admin_copy_option = null)
	{

		$from_name 	= get_option('listeo_emails_name', get_bloginfo('name'));
		$from_email = get_option('listeo_emails_from_email', get_bloginfo('admin_email'));
		$headers 	= sprintf("From: %s <%s>\r\nContent-Type: text/html; charset=UTF-8", $from_name, $from_email);

		// Check if admin copy should be sent (BCC)
		if ($send_admin_copy_option && get_option($send_admin_copy_option)) {
			$admin_email = get_option('admin_email');
			// Only add BCC if admin email is different from recipient
			if ($admin_email && $admin_email !== $emailto) {
				$headers .= "\r\nBcc: " . $admin_email;
			}
		}

		if (empty($emailto) || empty($subject) || empty($body)) {
			return;
		}
		// Strip HTML tags from subject line (e.g., from {service} placeholder)
		$subject = wp_strip_all_tags($subject);
		$subject = html_entity_decode($subject);
		$template_loader = new listeo_core_Template_Loader;
		ob_start();

		$template_loader->get_template_part('emails/header'); ?>
		<tr>
			<td align="left" valign="top" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 25px; padding-right: 25px; padding-bottom: 28px; width: 87.5%; font-size: 16px; font-weight: 400; 
				padding-top: 28px; 
				color: #666;
				font-family: sans-serif;" class="paragraph">
				<?php echo $body; ?>
			</td>
		</tr>
<?php
		$template_loader->get_template_part('emails/footer');
		$content = ob_get_clean();

		wp_mail(@$emailto, @$subject, @$content, $headers);
	}

	public function replace_shortcode($args, $body)
	{

		$tags =  array(
			'user_mail' 	=> "",
			'user_name' 	=> "",
			'booking_date' => "",
			'listing_name' => "",
			'listing_url' => '',
			'listing_address' => '',
			'listing_latitude'  => '',
			'listing_longitude'  => '',
			'listing_phone' => '',
			'listing_email' => '',
			'email_message' => '',
			'site_name' => '',
			'site_url'	=> '',
			'payment_url'	=> '',
			'expiration'	=> '',
			'dates'	=> '',
			'children'	=> '',
			'adults'	=> '',
			'user_message'	=> '',
			'tickets'	=> '',
			'service'	=> '',
			'details'	=> '',
			'login'	=> '',
			'password'	=> '',
			'first_name'	=> '',
			'last_name'	=> '',
			'login_url'	=> '',
			'sender'	=> '',
			'claim_url'	=> '',
			'conversation_url'	=> '',
			'client_first_name' => '',
			'client_last_name' => '',
			'client_email' => '',
			'client_phone' => '',
			'billing_address' => '',
			'billing_postcode' => '',
			'billing_city' => '',
			'billing_country' => '',
			'price' => '',
			'expiring' => '',
			'order_id' => '',
			'otp' => '',
			'meeting_date' => '',
			'meeting_topic' => '',
			'zoom_meeting_id' => '',
			'zoom_password' => '',
			'zoom_join_url' => '',
			'zoom_start_url' => '',
			'booking_id' => '',
		'rejection_reason' => '',
		'listing_dashboard_url' => '',
		'listing_id' => '',
		// Resource-aware tags surfaced by add-on plugins (e.g. Listeo
		// Booking Plus). Empty defaults so the tokens don't render as
		// literal `{resource_title}` strings when no add-on is active.
		'resource_id' => '',
		'resource_title' => '',
		'resource_subtitle' => '',
		'resource_image' => '',
		);
		/**
		 * Allow extensions to populate / extend the email tag values.
		 *
		 * The filter runs once per `replace_shortcode()` call (i.e. once
		 * per subject + once per body), so it's the natural seam for
		 * derived values that need to be computed off the booking row
		 * — e.g. resource info from the assigned-resource id stored on
		 * the booking comment.
		 *
		 * @param array  $args  Tag → value map about to be applied.
		 * @param string $body  The raw template string (lets extensions
		 *                      no-op when a tag they care about isn't
		 *                      referenced).
		 */
		$args = apply_filters( 'listeo_email_replace_args', $args, $body );

		$tags = array_merge($tags, $args);

		extract($tags);

		$tags 	= array(
			'{user_mail}',
			'{user_name}',
			'{booking_date}',
			'{listing_name}',
			'{listing_url}',
			'{listing_address}',
			'{listing_latitude}',
			'{listing_longitude}',
			'{listing_phone}',
			'{listing_email}',
			'{email_message}',
			'{site_name}',
			'{site_url}',
			'{payment_url}',
			'{expiration}',
			'{dates}',
			'{children}',
			'{adults}',
			'{user_message}',
			'{tickets}',
			'{service}',
			'{details}',
			'{login}',
			'{password}',
			'{first_name}',
			'{last_name}',
			'{login_url}',
			'{sender}',
			'{claim_url}',
			'{conversation_url}',
			'{client_first_name}',
			'{client_last_name}',
			'{client_email}',
			'{client_phone}',
			'{billing_address}',
			'{billing_postcode}',
			'{billing_city}',
			'{billing_country}',
			'{price}',
			'{expiring}',
			'{order_id}',
			'{otp}',
			'{meeting_date}',
			'{meeting_topic}',
			'{zoom_meeting_id}',
			'{zoom_password}',
			'{zoom_join_url}',
			'{zoom_start_url}',
			'{booking_id}',
		'{rejection_reason}',
		'{listing_dashboard_url}',
		'{listing_id}',
		'{resource_id}',
		'{resource_title}',
		'{resource_subtitle}',
		'{resource_image}',
		);

		$values  = array(
			$user_mail,
			$user_name,
			$booking_date,
			$listing_name,
			$listing_url,
			$listing_address,
			$listing_latitude,
			$listing_longitude,
			$listing_phone,
			$listing_email,
			$email_message,
			get_bloginfo('name'),
			get_home_url(),
			$payment_url,
			$expiration,
			$dates,
			$children,
			$adults,
			$user_message,
			$tickets,
			$service,
			$details,
			$login,
			$password,
			$first_name,
			$last_name,
			$login_url,
			$sender,
			$claim_url,
			$conversation_url,
			$client_first_name,
			$client_last_name,
			$client_email,
			$client_phone,
			$billing_address,
			$billing_postcode,
			$billing_city,
			$billing_country,
			$price,
			$expiring,
			$order_id,
			$otp,
			$meeting_date,
			$meeting_topic,
			$zoom_meeting_id,
			$zoom_password,
			$zoom_join_url,
			$zoom_start_url,
			$booking_id,
		$rejection_reason,
		$listing_dashboard_url,
		$listing_id,
		$resource_id,
		$resource_title,
		$resource_subtitle,
		$resource_image,
		);

		$message = str_replace($tags, $values, $body);

		if (isset($args['listing_id'])) {
			$custom_field_pattern = '/{custom_field_([^}]+)}/';
			preg_match_all($custom_field_pattern, $message, $matches);
			if (!empty($matches[1])) {
				foreach ($matches[1] as $index => $field_name) {
					$custom_value = get_post_meta($args['listing_id'], '_' . $field_name, true);
					$message = str_replace($matches[0][$index], $custom_value, $message);
				}
			}
		}
		$message = nl2br($message);
		$message = htmlspecialchars_decode($message, ENT_QUOTES);

		return $message;
	}
}
?>