<?php
// Exit if accessed directly

if ( ! defined( 'ABSPATH' ) )
	exit;
/**
 * Listeo_Core_Listing class
 */
class Listeo_Core_Users {

	/**
	 * Dashboard message.
	 *
	 * @access private
	 * @var string
	 */
	private $dashboard_message = '';

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;
	/**
	 * Constructor
	 */
	public function __construct() {

		//$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		
		//add_action( 'user_contactmethods', array( $this, 'modify_contact_methods' ), 10 );
		add_action( 'init', array( $this, 'submit_my_account_form' ), 10 );
		add_action( 'init', array( $this, 'submit_change_password_form' ), 10 );
		add_action( 'init',  array( $this, 'remove_filter_lostpassword' ), 10 );

		add_action( 'register_form', array( $this, 'listeo_register_form' ) );

		add_action( 'wp', array( $this, 'dashboard_action_handler' ) );
		add_filter( 'pre_get_posts',  array( $this,  'author_archive_listings' ));
 
		add_action( 'listeo_login_form', array( $this, 'show_login_form'));
		add_shortcode( 'listeo_login_form', array( $this, 'show_login_form'));

		add_action( 'show_user_profile', array( $this, 'extra_profile_fields' ), 10 );
		add_action( 'edit_user_profile', array( $this, 'extra_profile_fields' ), 10 );
		add_action( 'personal_options_update', array( $this, 'save_extra_profile_fields' ));
		add_action( 'edit_user_profile_update', array( $this, 'save_extra_profile_fields' ));
		add_action( 'delete_attachment', array( $this, 'cleanup_avatar_on_attachment_delete' ) );
		 
		add_filter( 'wpua_is_author_or_above', '__return_true' ); /*fix to apply for agents only*/

		add_shortcode( 'listeo_my_listings', array( $this, 'listeo_core_my_listings' ) );
		// add_shortcode( 'listeo_core_my_packages', array( $this, 'listeo_core_my_packages' ) );
		add_shortcode( 'listeo_my_account', array( $this, 'my_account' ) );
		add_shortcode( 'listeo_dashboard', array( $this, 'listeo_dashboard' ) );
		add_shortcode( 'listeo_change_password', array( $this, 'change_password' ) );
		add_shortcode( 'listeo_lost_password', array( $this, 'lost_password' ) );
		add_shortcode( 'listeo_reset_password', array( $this, 'reset_password' ) );
		
		add_shortcode('listeo_login', array($this, 'listeo_login') );
		add_shortcode('listeo_registration', array($this, 'listeo_registration') );

		add_shortcode( 'listeo_my_orders', array( $this, 'my_orders' ) );


		

		$front_login = get_option('listeo_front_end_login' );

		if($front_login == 'on') {

			add_filter( 'woocommerce_login_redirect', array( $this, 'redirect_woocommerce' ) ,10, 2);
			add_action( 'login_form_login', array( $this, 'redirect_to_custom_login' ) );
			add_filter( 'login_redirect', array( $this, 'redirect_after_login' ), 10, 3 );
			
			add_action( 'login_form_lostpassword', array( $this, 'redirect_to_custom_lostpassword' ) );

			add_action( 'login_form_rp', array( $this, 'redirect_to_custom_password_reset' ) );
			add_action( 'login_form_resetpass', array( $this, 'redirect_to_custom_password_reset' ) );

			add_action( 'login_form_register', array( $this, 'redirect_to_custom_register' ) );

			add_action( 'login_form_rp', array( $this, 'do_password_reset' ) );
			add_action( 'login_form_resetpass', array( $this, 'do_password_reset' ) );
			add_action( 'login_form_lostpassword', array( $this, 'do_password_lost' ) );

		}
		
		add_action( 'login_form_register', array( $this, 'do_register_user' ) );
	
		$popup_login = get_option( 'listeo_popup_login','ajax' ); 
		
		
		add_filter( 'authenticate', array( $this, 'maybe_redirect_at_authenticate' ), 101, 3 );	
		
		
		// Use pre_get_avatar_data to properly handle custom avatars while respecting WordPress default settings
		add_filter( 'pre_get_avatar_data', array( $this, 'listeo_core_pre_avatar_data' ), 10, 2 );

		// Keep legacy filter for backwards compatibility with themes/plugins that might depend on it
		add_filter( 'get_avatar', array( $this, 'listeo_core_gravatar_filter' ), 10, 6 );
	
		

		// Ajax login
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
		add_action( 'wp_ajax_nopriv_listeoajaxlogin', array( $this, 'ajax_login' ) );
		// Ajax registration
		add_action( 'wp_ajax_nopriv_listeoajaxregister', array( $this, 'ajax_register' ) );
		

		add_action( 'wp_ajax_nopriv_get_logged_header', array( $this, 'ajax_get_header_part' ) );
		add_action( 'wp_ajax_get_logged_header', array( $this, 'ajax_get_header_part' ) );
		add_action( 'wp_ajax_get_logged_claim', array( $this, 'ajax_get_claim_button_logged' ) );
	
		add_action( 'wp_ajax_nopriv_get_booking_button', array( $this, 'ajax_get_booking_button' ) );
		add_action( 'wp_ajax_get_booking_button', array( $this, 'ajax_get_booking_button' ) );

		add_action('wp_ajax_nopriv_get_booking_form', array($this, 'ajax_get_booking_form'));
		add_action('wp_ajax_get_booking_form', array($this, 'ajax_get_booking_form'));
	

		add_filter( 'registration_errors',array( $this,  'listeo_wp_admin_registration_errors'), 10, 3 );
		add_action( 'user_register', array( $this, 'listeo_wp_admin_user_register') );


		add_action('wp_ajax_nopriv_listeoajaxsendotpemail', array($this, 'send_email_otp'));

		add_action('wp_ajax_listeo_get_custom_registration_fields', array($this, 'get_custom_registration_fields'));
		add_action('wp_ajax_nopriv_listeo_get_custom_registration_fields', array($this, 'get_custom_registration_fields'));

		add_action('wp_ajax_create_express_stripe_account', array($this, 'create_express_stripe_account'));
		add_action('wp_ajax_get_express_stripe_account_link', array($this, 'get_express_stripe_account_link'));
		add_action( 'wp_body_open',array($this, 'listeo_login_form'));

		add_action('admin_init',  array($this,'check_listeo_stripe_enabled'), 10 );
		//add_action( 'admin_init', array($this,'wpse66094_no_admin_access'), 100 );
	}

	function check_listeo_stripe_enabled() {
    // Check if Listeo theme is active and Stripe is enabled
    $listeo_stripe_enabled = get_option('listeo_stripe_connect_activation');
    
    if ($listeo_stripe_enabled == 'yes' || $listeo_stripe_enabled == '1' || $listeo_stripe_enabled === true) {
        // Add the filter to modify columns
        add_filter('manage_users_columns', array($this,'add_stripe_connection_column'));
        // Add the action to display the column content
        add_action('manage_users_custom_column', array($this,'display_stripe_connection_status'), 10, 3);
        // Add sortable column support
        add_filter('manage_users_sortable_columns', array($this,'make_stripe_column_sortable'));
        // Handle sorting logic
        add_action('pre_user_query', array($this,'stripe_column_orderby'));
    }
	}

	function add_stripe_connection_column($columns)
	{
		$columns['stripe_connection'] = __('Stripe Connection', 'listeo');
		return $columns;
	}

	/**
	 * Display the Stripe connection status for each user
	 */
	function display_stripe_connection_status($value, $column_name, $user_id)
	{
		if ($column_name !== 'stripe_connection') {
			return $value;
		}

		// Get user's Stripe connection status from user meta
		$stripe_connected = get_user_meta($user_id, 'vendor_connected', true);
		$stripe_customer_id = get_user_meta($user_id, 'stripe_user_id', true);
		$stripe_connect_id = get_user_meta($user_id, 'listeo_stripe_connect_id', true);

		// Check if user is connected to Stripe
		if (!empty($stripe_connected) ) {
			return '<span class="stripe-status connected" style="color: green;"><span class="dashicons dashicons-yes"></span> ' . __('Connected', 'listeo') . '</span>';
		} elseif (!empty($stripe_customer_id) || !empty($stripe_connect_id)) {
			return '<span class="stripe-status partial" style="color: orange;"><span class="dashicons dashicons-marker"></span> ' . __('Partial', 'listeo') . '</span>';
		} else {
			return '<span class="stripe-status not-connected" style="color: red;"><span class="dashicons dashicons-no"></span> ' . __('Not Connected', 'listeo') . '</span>';
		}
	}

	/**
	 * Make the Stripe connection column sortable
	 */
	function make_stripe_column_sortable($columns)
	{
		$columns['stripe_connection'] = 'stripe_connection';
		return $columns;
	}

	/**
	 * Add custom sorting logic for the Stripe connection column
	 */
	function stripe_column_orderby($query)
	{
		if (!is_admin()) {
			return;
		}

		$orderby = $query->get('orderby');

		if ('stripe_connection' == $orderby) {
			$query->set('meta_key', 'vendor_connected');
			$query->set('orderby', 'meta_value');
		}
	}


	public function send_email_otp()
	{
		// Security: Verify nonce for nopriv requests
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo-otp-email-nonce')) {
			wp_send_json_error(array('message' => __('Security verification failed. Please refresh the page and try again.', 'listeo_core')));
		}

		// Sanitize and validate email
		$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

		if (empty($email) || !is_email($email)) {
			wp_send_json_error(array('message' => __('Invalid email address.', 'listeo_core')));
		}

		// Rate limiting: Check if OTP was recently sent to this email
		$email_throttle_key = 'otp_throttle_email_' . md5($email);
		if (get_transient($email_throttle_key)) {
			$remaining = get_transient($email_throttle_key);
			wp_send_json_error(array(
				'message' => sprintf(
					__('Please wait %d seconds before requesting another OTP.', 'listeo_core'),
					$remaining
				)
			));
		}

		// Rate limiting: Check IP-based throttling to prevent spam
		$user_ip = $this->get_user_ip();
		$ip_throttle_key = 'otp_throttle_ip_' . md5($user_ip);
		$ip_attempts = get_transient($ip_throttle_key);

		if ($ip_attempts && $ip_attempts >= 5) {
			wp_send_json_error(array(
				'message' => __('Too many OTP requests from your IP address. Please try again later.', 'listeo_core')
			));
		}

		// Check if OTP already exists and is still valid
		$existing_otp_key = 'otp_' . $email;
		if (get_transient($existing_otp_key)) {
			wp_send_json_error(array(
				'message' => __('An OTP has already been sent to this email. Please check your inbox or wait 5 minutes to request a new one.', 'listeo_core')
			));
		}

		// Increment IP attempt counter (expires after 15 minutes)
		$new_ip_attempts = $ip_attempts ? $ip_attempts + 1 : 1;
		set_transient($ip_throttle_key, $new_ip_attempts, 15 * MINUTE_IN_SECONDS);

		// Set email throttle (60 seconds cooldown between requests)
		set_transient($email_throttle_key, 60, 60);

		// Send OTP email
		$mail_args = array(
			'email' => $email,
		);
		do_action('listeo_otp_mail', $mail_args);

		wp_send_json_success(array(
			'message' => __('OTP sent successfully. Please check your email.', 'listeo_core')
		));
	}

	/**
	 * Get user IP address (handles proxies and Cloudflare)
	 */
	private function get_user_ip()
	{
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy
			'REMOTE_ADDR'            // Direct connection
		);

		foreach ($ip_keys as $key) {
			if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
				return $_SERVER[$key];
			}
		}

		return '0.0.0.0'; // Fallback
	}


	function create_express_stripe_account(){
		check_ajax_referer('listeo_stripe_express', 'nonce');

		$current_user = wp_get_current_user();


		$stripe_connect_activation = get_option('listeo_stripe_connect_activation');

		if ($stripe_connect_activation) {
			$stripe_mode = get_option('listeo_stripe_connect_mode');

			$testmode       = (!empty($stripe_mode) && 'test' === $stripe_mode) ? true : false;

			if ($testmode) {
				$client_id = get_option('listeo_stripe_connect_test_client_id');
			} else {
				$client_id = get_option('listeo_stripe_connect_live_client_id');
			}
			$secret_key    = 'listeo_stripe_connect_' . ($testmode ? 'test_' : 'live_') . 'secret_key';
			$secret        = !empty(get_option($secret_key)) ? get_option($secret_key) : false;
		}

		if (empty($secret)) {
			wp_send_json_error('Stripe secret key is not configured.');
			return;
		}

		try {
			$stripe = new \Stripe\StripeClient($secret);
			$account = $stripe->accounts->create(['type' => 'express']);
		} catch (\Throwable $e) {
			// log error
			error_log($e->getMessage());
		
			wp_send_json_error('Error creating account: '. $e->getMessage());
		}
		
		update_user_meta($current_user->ID, 'listeo_stripe_express_account_id', $account->id);
		wp_send_json_success();
	}


	function get_express_stripe_account_link() {
		check_ajax_referer('listeo_stripe_express', 'nonce');

		$current_user = wp_get_current_user();


		$stripe_connect_activation = get_option('listeo_stripe_connect_activation');

		if ($stripe_connect_activation) {
			$stripe_mode = get_option('listeo_stripe_connect_mode');

			$testmode       = (!empty($stripe_mode) && 'test' === $stripe_mode) ? true : false;

			if ($testmode) {
				$client_id = get_option('listeo_stripe_connect_test_client_id');
			} else {
				$client_id = get_option('listeo_stripe_connect_live_client_id');
			}
			$secret_key    = 'listeo_stripe_connect_' . ($testmode ? 'test_' : 'live_') . 'secret_key';
			$secret        = !empty(get_option($secret_key)) ? get_option($secret_key) : false;
		}

		if (empty($secret)) {
			wp_send_json_error('Stripe secret key is not configured.');
			return;
		}

		$stripe = new \Stripe\StripeClient($secret);
		$account_id = get_user_meta($current_user->ID, 'listeo_stripe_express_account_id', true);

		// Always generate a fresh account link instead of caching
		// Stripe account links are single-use and short-lived by design
		// Caching them can cause issues when users abort the onboarding process
		try {
			// Generate a return nonce to protect the setup callback from CSRF
			$return_nonce = wp_create_nonce('listeo_stripe_express_return_' . $current_user->ID);
			update_user_meta($current_user->ID, 'listeo_stripe_express_return_nonce', $return_nonce);

			$link = $stripe->accountLinks->create([
				'account' => $account_id,
				'refresh_url' => add_query_arg(array('stripe-express-reauth' => 'yes'), get_permalink(get_option('listeo_wallet_page'))),
				'return_url' => add_query_arg(array('stripe-express-setup' => 'yes', '_nonce' => $return_nonce), get_permalink(get_option('listeo_wallet_page'))),
				'type' => 'account_onboarding',
			]);

			// Store the link for reference (optional - mainly for debugging)
			update_user_meta($current_user->ID, 'listeo_stripe_express_account_url', $link->url);
			update_user_meta($current_user->ID, 'listeo_stripe_express_account_url_expiration', $link->expires_at);

			wp_send_json_success($link->url);
		} catch (\Throwable $e) {
			// Log error and send error response
			error_log('Listeo Stripe Express: Error creating account link - ' . $e->getMessage());
			wp_send_json_error('Error creating account link: ' . $e->getMessage());
		}


	}


function listeo_login_form() {
if(is_user_logged_in()) {
	return;
}
if( get_option('listeo_popup_login') == 'ajax' && !is_page_template( 'template-dashboard.php' ) ) : ?>
	<!-- Sign In Popup -->
	<div id="sign-in-dialog" class="zoom-anim-dialog mfp-hide">

		<div class="small-dialog-header">
			<h3><?php esc_html_e('Sign In','listeo'); ?></h3>
		</div>
		<!--Tabs -->
		<div class="sign-in-form style-1"> 
			<?php do_action('listeo_login_form'); ?>
		</div>
	</div>
	<!-- Sign In Popup / End -->
<?php endif; ?>
<div class="clearfix"></div>
<?php } 


	function listeo_wp_admin_registration_errors( $errors, $sanitized_user_login, $user_email ) {
		$role_status  = get_option('listeo_registration_hide_role');
		
		if(!$role_status) {
		    if ( empty( $_POST['role'] ) || ! empty( $_POST['role'] ) && trim( $_POST['role'] ) == '' ) {
		         $errors->add( 'role_error', __( '<strong>ERROR</strong>: You must include a role.', 'listeo_core' ) );
		    }
		}

	    return $errors;
	}

	function get_custom_registration_fields(){
		$role = $_REQUEST['role'];
		
		$result['type'] = 'error';
		$result['output'] = '';
		if($role){
			$result['type'] = 'success';
			$result['output'] = listeo_get_extra_registration_fields($role);	
		}
		
		wp_send_json($result);
		die();
		
	}

	//3. Finally, save our extra registration user meta.
	
	function listeo_wp_admin_user_register( $user_id ) {

		// Detect if this is from WordPress admin user creation screen
		$is_wp_admin_user_creation = (
			is_admin() &&
			isset($_POST['action']) &&
			$_POST['action'] === 'createuser' &&
			current_user_can('create_users')
		);

		// If this is WordPress admin user creation, don't interfere with role assignment
		if ($is_wp_admin_user_creation) {
			// Just add level_1 capability and exit
			$user = new WP_User( $user_id );
			$user->add_cap( 'level_1' );
			return;
		}

		// Only process Listeo registrations - check if this is from a Listeo form
		// by looking for Listeo-specific POST parameters (but NOT the WP admin 'role' field)
		$is_listeo_registration = isset($_POST['user_role']) || (isset($_POST['role']) && !$is_wp_admin_user_creation);

		// Security check for non-Listeo registrations
		if (!$is_listeo_registration) {
			// Get the newly created user to check their assigned role
			$user = new WP_User( $user_id );
			$user_roles = $user->roles;

			// Define allowed roles for non-Listeo registrations (safe roles only)
			$allowed_roles = array(
				'subscriber',    // WordPress default
				'customer',      // WooCommerce
				'guest',         // Listeo
				'owner',         // Listeo
				'seller',        // Listeo/Dokan
				'vendor',        // Dokan
				'wcfm_vendor',   // WCFM
				'dc_vendor',     // Dokan
			);

			// Check if user has any unsafe elevated roles
			$has_unsafe_role = false;
			foreach ($user_roles as $role) {
				if (!in_array($role, $allowed_roles)) {
					// User has a role that's not in our whitelist
					// This is only acceptable if done by an admin in WP backend
					if (!is_admin() || !current_user_can('create_users')) {
						$has_unsafe_role = true;
						break;
					}
				}
			}

			// If unsafe role detected and not from admin, force to default role
			if ($has_unsafe_role) {
				$default_role = get_option('default_role');
				wp_update_user(array('ID' => $user_id, 'role' => $default_role));
			}

			// Still add level_1 capability for all users
			$user->add_cap( 'level_1' );
			return;
		}

		// This is a Listeo registration - handle the role assignment
		if (isset($_POST['user_role'])) {
			$role = sanitize_text_field($_POST['user_role']);
			if (!in_array($role, array('owner', 'seller', 'guest'))) {
				$role = get_option('default_role');
			}
		} elseif (isset($_POST['role'])) {
			// Handle WordPress default registration form with role field
			$role = sanitize_text_field($_POST['role']);
			if (!in_array($role, array('owner', 'seller', 'guest'))) {
				$role = get_option('default_role');
			}
		} else {
			$role = get_option('default_role');
		}

		$user_id = wp_update_user(array('ID' => $user_id, 'role' => $role));


	   //fix owners dropdown

		//give this user with $user_id capabilities of a level_1
		$user = new WP_User( $user_id );
		$user->add_cap( 'level_1' );


		// $ownerusers = get_users(array('role__in' => array('owner', 'seller')));
		// foreach ( $ownerusers as $user ) {
		// 	$user->add_cap('level_1');
		// }
	}

	function listeo_register_form() {
		$role_status  = get_option('listeo_registration_hide_role');
		
		if(!$role_status) {
		    global $wp_roles;
		    echo '<label for="role">'.esc_html__('I want to register as','listeo_core').'</label>';
		    echo '<select name="role" id="role" class="input chosen-select">';
		    
			    echo '<option value="guest">'.esc_html_x("Guest", "User role", "listeo_core").'</option>';
			    
			    echo '<option value="owner">'.esc_html__("Owner","listeo_core").'</option>';
			    
		    echo '</select>';
	    }
	}


	function wpse66094_no_admin_access() {
	    $redirect = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : home_url( '/' );
	    global $current_user;
	    $user_roles = $current_user->roles;
	    $user_role = array_shift($user_roles);
	    
	    if( in_array($user_role,array('owner','guest')) ){
	        exit( wp_redirect( $redirect ) );
	    }
	 }

	function show_login_form(){
		
			$template_loader = new Listeo_Core_Template_Loader;
			$template_loader->get_template_part( 'account/login-form' ); 
	}
	function enqueue_scripts(){
		if (!is_user_logged_in()) {
			
			$popup_login = get_option( 'listeo_popup_login','ajax' ); 
			
			if($popup_login == 'ajax') {
				if(get_option('listeo_otp_status') || get_option('listeo_email_otp_verification')){
					wp_register_script( 'listeo_core-ajax-login', esc_url( LISTEO_CORE_ASSETS_URL ) . 'js/ajax-login-script-otp.js', array('jquery'), '1.0.1'  );
		  			wp_enqueue_script('listeo_core-ajax-login');
		  			wp_localize_script( 'listeo_core-ajax-login', 'listeo_login', array(
				        'ajaxurl' => admin_url( 'admin-ajax.php' ),
				        'redirecturl' => home_url(),
				        'loadingmessage' => __('Sending user info, please wait...','listeo_core'),
				        'otp_nonce' => wp_create_nonce('listeo-otp-email-nonce')
				    ));
				} else {
					wp_register_script('listeo_core-ajax-login', esc_url(LISTEO_CORE_ASSETS_URL) . 'js/ajax-login-script.js', array('jquery'), '1.0.1');
					wp_enqueue_script('listeo_core-ajax-login');
					wp_localize_script('listeo_core-ajax-login', 'listeo_login', array(
						'ajaxurl' => admin_url('admin-ajax.php'),
						'redirecturl' => home_url(),
						'loadingmessage' => __('Sending user info, please wait...', 'listeo_core')
					));
				}

	  		} else {
				wp_register_script('listeo_core-static-login', esc_url(LISTEO_CORE_ASSETS_URL) . 'js/static-login-script-otp.js', array('jquery'), '1.0');
				wp_enqueue_script('listeo_core-static-login');
				wp_localize_script('listeo_core-static-login', 'listeo_login', array(
					'ajaxurl' => admin_url('admin-ajax.php'),
					'redirecturl' => home_url(),
					'loadingmessage' => __('Sending user info, please wait...', 'listeo_core'),
					'otp_nonce' => wp_create_nonce('listeo-otp-email-nonce')
				));
			}
		}
	}

	function ajax_login(){

	    // First check the nonce, if it fails the function will break
	    //check_ajax_referer( 'ajax-login-nonce', 'security' );
		if(!get_option('listeo_login_nonce_skip')){
			if (!check_ajax_referer('listeo-ajax-login-nonce', 'login_security', false)) :
				echo json_encode(
					array(
						'loggedin' => false,
						'message' => __('Session token has expired, please reload the page and try again', 'listeo_core')
					)
				);
				die();
			endif;
		}
		
	    // Nonce is checked, get the POST data and sign user on
	    $info = array();
	    $info['user_login'] = sanitize_text_field(trim($_POST['username']));
	    $info['user_password'] = sanitize_text_field(trim($_POST['password']));
	    $info['remember'] = isset($_POST['rememberme']) ? true : false;

	    if(empty($info['user_login'])) {
	    	 echo json_encode(
	    	 	array(
	    	 		'loggedin'=>false, 
	    	 		'message'=> esc_html__( 'You do have an email address, right?', 'listeo_core' )
	    	 	)
	    	 );
	    	 die();
	    } 
	    if(empty($info['user_password'])) {
	    	 echo json_encode(array('loggedin'=>false, 'message'=> esc_html__( 'You need to enter a password to login.', 'listeo_core' )));
	    	 die();
	    }

	    $user_signon = wp_signon( $info, is_ssl() );
	    if ( is_wp_error($user_signon) ){
	    	
	        echo json_encode(
	        	array(
	        		'loggedin'=>false, 
	        		'message'=>esc_html__('Wrong username or password.','listeo_core')
	        	)
	        );

	    } else {
	    	wp_clear_auth_cookie();
          //  do_action('wp_login', $user_signon->ID);
            wp_set_current_user($user_signon->ID);
            wp_set_auth_cookie($user_signon->ID, true);

			// Get redirect URL based on priority:
			// 1. redirect_to parameter (e.g., from wp-admin access attempts)
			// 2. Role-based redirects (if enabled in settings)
			// 3. Fallback to profile page
			$redirect_url = '';

			// First priority: Check for redirect_to parameter
			if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
				$requested_redirect = esc_url_raw($_POST['redirect_to']);

				// Handle admin redirects
				if (user_can($user_signon, 'manage_options')) {
					// Admins can be redirected anywhere, including wp-admin
					$redirect_url = $requested_redirect;
				} else {
					// Non-admins: only allow front-end redirects (security measure)
					if (strpos($requested_redirect, 'wp-admin') === false &&
					    strpos($requested_redirect, 'wp-login') === false) {
						$redirect_url = $requested_redirect;
					}
				}
			}

			// Second priority: Role-based redirects (if no redirect_to was provided or allowed)
			if (empty($redirect_url)) {
				$enable_ajax_redirect = get_option('listeo_ajax_login_redirect_enable', false);

				if ($enable_ajax_redirect) {
					$user_data = get_userdata($user_signon->ID);
					$roles = $user_data->roles;
					$role = array_shift($roles);

					if ($role == 'owner' || $role == 'seller') {
						$redirect_page_id = get_option('listeo_owner_login_redirect');
						if ($redirect_page_id) {
							$redirect_url = get_permalink($redirect_page_id);
						}
					} else if ($role == 'guest') {
						$redirect_page_id = get_option('listeo_guest_login_redirect');
						if ($redirect_page_id) {
							$redirect_url = get_permalink($redirect_page_id);
						}
					}

					// Fallback to profile page if no role-specific redirect set
					if (empty($redirect_url)) {
						$profile_page = get_option('listeo_profile_page');
						if ($profile_page) {
							$redirect_url = get_permalink($profile_page);
						}
					}
				}
			}

	        echo json_encode(
	        	array(
	        		'loggedin'	=>	true,
	        		'message'	=>	esc_html__('Login successful, redirecting...','listeo_core'),
					'redirect'	=>	$redirect_url,
	        	)
	        );

	    }

	    die();
	}

	function ajax_get_booking_form(){
		$current_user = wp_get_current_user();
		
		
	
		$output = array();
		$output['email'] = $current_user->user_email;
		$output['first_name'] =  $current_user->first_name;
		$output['last_name'] =  $current_user->last_name;
		$output['phone'] =  get_user_meta($current_user->ID, 'billing_phone ', true);
		$output['billing_address_1'] =  get_user_meta($current_user->ID, 'billing_address_1', true);
		$output['billing_postcode'] =  get_user_meta($current_user->ID, 'billing_postcode ', true);
		$output['billing_city'] =  get_user_meta($current_user->ID, 'billing_city', true);
		$output['billing_state'] =  get_user_meta($current_user->ID, 'billing_state', true);
		
		wp_send_json_success($output);
		die();
	}


	function ajax_get_claim_button_logged()
	{

		ob_start();

		$template_loader = new Listeo_Core_Template_Loader;
		$template_loader->get_template_part('single-partials/single-listing', 'claim'); 

		$output = ob_get_clean();
		wp_send_json_success(
			array(
				'output' 	=> 	$output
			)
		);
		die();
	}
	function ajax_get_header_part(){
		
			ob_start();

			$template_loader = new Listeo_Core_Template_Loader;		
			$template_loader->get_template_part( 'account/logged_section' ); 

			$output = ob_get_clean();
			wp_send_json_success(
	        	array(
	        		'output' 	=> 	$output
	        	)
	        );
	        die();

	}
	function ajax_get_booking_button(){
			$post_id = $_POST['post_id'];
			$owner_widget_id = $_POST['owner_widget_id'];
			$freeplaces = $_POST['freeplaces'];
			
			$_listing_type = listeo_get_booking_type($post_id);
			ob_start(); ?>
			<a href="#" data-freeplaces="<?php echo esc_attr($freeplaces); ?>" class="button book-now fullwidth margin-top-5">
				<div class="loadingspinner"></div>
				<span class="book-now-text">
					<?php if ($_listing_type == 'event') { 
						esc_html_e('Make a Reservation','listeo_core'); 
					} else { 
						if(get_post_meta($post_id,'_instant_booking', true)){
							esc_html_e('Book Now','listeo_core'); 	
						} else {
							esc_html_e('Request Booking','listeo_core'); 	
						}
					}  ?>
				</span>			
			</a>
			<?php

			$booking_btn = ob_get_clean();

			ob_start(); 

				$nonce = wp_create_nonce("listeo_core_bookmark_this_nonce");
		
				$classObj = new Listeo_Core_Bookmarks;
				
				if( $classObj->check_if_added($post_id) ) { ?>
					<button onclick="window.location.href='<?php echo get_permalink( get_option( 'listeo_bookmarks_page' ))?>'" class="like-button save liked" ><span class="like-icon liked"></span> <?php esc_html_e('Bookmarked','listeo_core') ?></button> 
				<?php } else {  ?>
					
						<button class="like-button listeo_core-bookmark-it"
							data-post_id="<?php echo esc_attr($post_id); ?>" 
							data-confirm="<?php esc_html_e('Bookmarked!','listeo_core'); ?>"
							data-nonce="<?php echo esc_attr($nonce); ?>" 
							><span class="like-icon"></span> <?php esc_html_e('Bookmark this listing','listeo_core') ?></button> 
				<?php }

			$bookmark_btn = ob_get_clean();
			

			ob_start(); 

			if($owner_widget_id){

				$widget_id = explode('-',$owner_widget_id);
				$widget_instances = get_option('widget_widget_listing_owner');
				$widget_options = $widget_instances[$widget_id[1]];
				
				$show_email = (isset($widget_options['email']) && !empty($widget_options['email'])) ? true : false ;
				$show_phone = (isset($widget_options['phone']) && !empty($widget_options['phone'])) ? true : false ;

				$owner_id = get_post_field ('post_author', $post_id);
			
				if(!$owner_id) {
					return;
				}
				$owner_data = get_userdata( $owner_id );
				if(  $show_email || $show_phone ) {  ?>
					<ul class="listing-details-sidebar">
						<?php if($show_phone) {  ?>
							<?php if(isset($owner_data->phone) && !empty($owner_data->phone)): ?>
								<li><i class="sl sl-icon-phone"></i> <?php echo esc_html($owner_data->phone); ?></li>
							<?php endif; 
						} 
						if($show_email) { 	
							if(isset($owner_data->user_email)): $email = $owner_data->user_email; ?>
								<li><i class="fa fa-envelope-o"></i><a href="mailto:<?php echo esc_attr($email);?>"><?php echo esc_html($email);?></a></li>
							<?php endif; ?>
						<?php } ?>
						
					</ul>
				<?php }
			}
			$owner_data = ob_get_clean();




			wp_send_json_success(
	        	array(
	        		'booking_btn' 	=> 	$booking_btn,
	        		'bookmark_btn' 	=> $bookmark_btn,
	        		'owner_data' 	=> $owner_data
	        	)
	        );
	        die();

	}

	function ajax_register(){
		
		if ( !get_option('users_can_register') ) :
	            echo json_encode(
				array(
					'registered'=>false, 
					'message'=> esc_html__( 'Registration is disabled', 'listeo_core' ),
				)
			);
    		die();
	    endif;
		if (!get_option('listeo_login_nonce_skip')) {
  		if( !check_ajax_referer( 'listeo-ajax-login-nonce', 'register_security', false) ) :
            echo json_encode(
            	array(
            		'registered'=>false, 
            		'message'=> __('Session token has expired, please reload the page and try again', 'listeo_core')
            	)
            );
            die();
        endif;
		}
        //get email
        $email = sanitize_email($_POST['email']);
		if ( !$email ) {
 			echo json_encode(
            	array(
            		'registered'=>false, 
            		'message'=> __('Please fill email address', 'listeo_core')
            	)
            );
            die();
        }		

        if ( !is_email($email)  ) {
 			echo json_encode(
            	array(
            		'registered'=>false, 
            		'message'=> __('This is not valid email address', 'listeo_core')
            	)
            );
            die();
        }

		// // check if emails is from gmail
		// $domain = substr(strrchr($email, "@"), 1);
		// $allowed_domains = get_option('listeo_allowed_domains');
		// //this is text string with domeins separated by comma
		// $allowed_domains = explode(',', $allowed_domains);
	
		// if ($allowed_domains && !in_array($domain, $allowed_domains)) {
		// 	echo json_encode(
		// 		array(
		// 			'registered'=>false, 
		// 			'message'=> __('Please use email address from gmail', 'listeo_core')
		// 		)
		// 	);
		// 	die();
		// }



        $user_login = false;
        // get/create username

	    if ( get_option('listeo_registration_hide_username') ) {
			if(get_option('listeo_registration_hide_username_use_email')) {
				$user_login = sanitize_user(trim($email));
			} else {
  				$email_arr = explode('@', $email);
            	$user_login = sanitize_user(trim($email_arr[0]), true);
			}
        } else {
        	
 			$user_login = sanitize_user(trim($_POST['username']));
 			
        }

        if(empty($user_login)) {
			echo json_encode(
				array(
					'registered'=>false, 
					'message'=> esc_html__( 'Please provide your username', 'listeo_core' )
				)
			);
    		die();
    	}   	  	

	
        if (get_option('listeo_display_first_last_name') ) {

            $first_name = sanitize_text_field( $_POST['first_name'] );
            $last_name  = ! empty($_POST['last_name']) ? sanitize_text_field( $_POST['last_name'] ) : '';

        } else {

        	$first_name = '';
        	$last_name = '';

        }

           if ( get_option('listeo_display_password_field') ) :
	    	$password = sanitize_text_field(trim($_POST['password']));
			if(empty($password)) {
				echo json_encode(
					array(
						'registered'=>false, 
						'message'=> esc_html__( 'Please provide password', 'listeo_core' )
					)
				);
				die();
			} 
			if(get_option('listeo_strong_password')){
				$uppercase = preg_match('@[A-Z]@', $password);
				$lowercase = preg_match('@[a-z]@', $password);
				$number    = preg_match('@[0-9]@', $password);
				$specialChars = preg_match('@[^\w]@', $password);

				if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
				    
				    echo json_encode(
					array(
						'registered'=>false, 
						'message'=> esc_html__( 'Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character.', 'listeo_core' )
					)
				);
				die();
				}
			}
		endif; 

        if ( get_option('listeo_privacy_policy') ) :
	    	$privacy_policy = $_POST['privacy_policy'];
			if($privacy_policy !="on") {
				echo json_encode(
					array(
						'registered'=>false, 
						'message'=> esc_html__( 'Please accept Privacy Policy', 'listeo_core' )
					)
				);
				die();
			} 
		endif; 	       
		if ( get_option('listeo_terms_and_conditions_req') ) :
	    	$terms_and_conditions = $_POST['terms_and_conditions'];
		
			if($terms_and_conditions != "on") {
				echo json_encode(
					array(
						'registered'=>false, 
						'message'=> esc_html__( 'Please accept Terms and Conditions', 'listeo_core' )
					)
				);
				die();
			} 
		endif; 	

		if(isset($_POST['user_role'])){
			$role = sanitize_text_field( $_POST['user_role'] );
			if (!in_array($role, array('owner', 'seller', 'guest'))) {
				$role = get_option('default_role');
			}
		} else {
			$role = get_option('default_role');
		}

    	
    	if (!in_array($role, array('owner', 'seller', 'guest'))) {
    		$role = get_option('default_role');
    	}

    	if($role=='owner' || $role == 'seller') {
			$fields = get_option('listeo_owner_registration_form');
        } else {
        	$fields = get_option('listeo_guest_registration_form');
        }
        	
       	 $custom_registration_fields = array();
            if(!empty($fields)){
            	//get fields for registration

            	foreach ($fields as $key => $field) {

            		
            		$field_type = str_replace( '-', '_', $field['type'] );
		
					if ( $handler = apply_filters( "listeo_core_get_posted_{$field_type}_field", false ) ) {
						
						$value = call_user_func( $handler, $key, $field );
					} elseif ( method_exists( $this, "get_posted_{$field_type}_field" ) ) {
						
						$value = call_user_func( array( $this, "get_posted_{$field_type}_field" ), $key, $field );
					} else {
						
						$value = $this->get_posted_field( $key, $field );
					}

					
					
					// Set fields value

					


            		if(isset($field['required']) && !empty($field['required'])) {
            		
            			if(!$value){
            				$redirect_url = add_query_arg( 'register-errors', 'required-field', $redirect_url );
            				wp_redirect( $redirect_url );
    						exit;
            			} else {
            				$field['value'] = $value; 
            				$custom_registration_fields[] = $field;
            			}

            		} else {
            			
            			$field['value'] = $value;
            			
            			$custom_registration_fields[] = $field;
            		}
				

            	}
            }


		//otp
			$otp_pass = true;

			
			
			$phone = false;
			if (get_option('listeo_otp_status') || get_option('listeo_email_otp_verification')) {

				$sms_otp = get_option('listeo_otp_status');
				$email_otp = get_option('listeo_email_otp_verification');
				if ($sms_otp || $email_otp) {
					$otp = true;
					if ($sms_otp) {
						$otp_type = 'sms';
					} else {
						$otp_type = 'email';
					}
				} else {
					$otp = false;
					$otp_type = false;
				}
				//verify otp
				$phone = sanitize_text_field(trim($_POST['full_phone']));
				$email = sanitize_text_field(trim($_POST['email']));
				
				if($otp_type == 'sms') {
					$transient_key = 'otp_' . $phone;
				} else {
					$transient_key = 'otp_' . $email;
				}
			
				$orignal_token = get_transient($transient_key);
				if (isset($_POST['listeo_otp']['token'])) {
					$token = $_POST['listeo_otp']['token'];

					$letter_1 = $token['code_1'];
					$letter_2 = $token['code_2'];
					$letter_3 = $token['code_3'];
					$letter_4 = $token['code_4'];
					// glue all the letters togheter
					$token = $letter_1 . $letter_2 . $letter_3 . $letter_4;
					// get user phone
					
					// check if the token is correct

					if ($orignal_token != $token) {
					echo json_encode(
						array(
							'registered' => false,
							'message' => esc_html__('Wrong OTP code, please try again', 'listeo_core')
						)
					);
					die();
					}
				} else {
					
					echo json_encode(
						array(
							'registered' => false,
							'message' => esc_html__('Missing OTP code, please try again', 'listeo_core')
						)
					);
					die();
				}
				//remove the transient
				
			}
		
		$recaptcha_status = get_option('listeo_recaptcha');
		$recaptcha_version = get_option('listeo_recaptcha_version');
	            
        if($recaptcha_status && $recaptcha_version=="v2") {
        	if(isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])):
		        //your site secret key
		        $secret = get_option('listeo_recaptcha_secretkey');
		        //get verify response data
		        //$verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);
		        $verifyResponse = wp_remote_get('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);
				$responseData_w = wp_remote_retrieve_body( $verifyResponse );
		        $responseData = json_decode($responseData_w);
				if( $responseData->success ):
					//passed captcha, proceed to register
		            // 
	        	else:
	        		echo json_encode(
						array(
							'registered'=>false, 
							'message'=> esc_html__( 'Wrong reCAPTCHA', 'listeo_core' )
						)
					);
					die();
        		endif;
        	else:
        		echo json_encode(
					array(
						'registered'=>false, 
						'message'=> esc_html__( 'You forgot about reCAPTCHA', 'listeo_core' )
					)
				);die();
    		endif;
        } 

        if($recaptcha_status && $recaptcha_version=="v3") {
	        	if(isset($_POST['token']) && !empty($_POST['token'])):
			        //your site secret key
			        $secret = get_option('listeo_recaptcha_secretkey3');
			        //get verify response data
			        $verifyResponse = wp_remote_get('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['token']);
			        $responseData_w = wp_remote_retrieve_body( $verifyResponse );
		        	$responseData = json_decode($responseData_w);
		        	
					if($responseData->success == '1' && $responseData->action == 'login' && $responseData->score >= 0.5) :

						//passed captcha, proceed to register
			            // 
		        	else:
		        		echo json_encode(
							array(
								'registered'=>false, 
								'message'=> esc_html__( 'reCAPTCHA was not validated', 'listeo_core' )
							)
						);
						die();
	        		endif;
	        	else:
	        		echo json_encode(
						array(
							'registered'=>false, 
							'message'=> esc_html__( 'You forgot about reCAPTCHA', 'listeo_core' )
						)
					);die();
	    		endif;
	        }

		if ($recaptcha_status && $recaptcha_version == "hcaptcha") {
			if (!isset($_POST['h-captcha-response']) || empty($_POST['h-captcha-response'])) {
				echo json_encode(
					array(
						'registered' => false,
						'message' => esc_html__('hCaptcha was not validated', 'listeo_core')
					)
				);
				die();
			} else {
				$hcaptcha_response = $_POST['h-captcha-response'];
				if (!$this->verify_hcaptcha($hcaptcha_response)) {
					//passed captcha, proceed to register
				}
			}
		}

		if ($recaptcha_status && $recaptcha_version == "turnstile") {
			if (!isset($_POST['cf-turnstile-response']) || empty($_POST['cf-turnstile-response'])) {
				echo json_encode(
					array(
						'registered' => false,
						'message' => esc_html__('Turnstile was not validated', 'listeo_core')
					)
				);
				die();
			} else {
				$turnstile_response = $_POST['cf-turnstile-response'];
				if (!$this->verify_turnstile($turnstile_response)) {
					//passed captcha, proceed to register
				}
			}
		}
		
    	if ( get_option('listeo_display_password_field') ) :
	    	$result = $this->register_user( $email, $user_login, $first_name, $last_name, $role, $phone, $password,$custom_registration_fields  );
	    else :
    		$result = $this->register_user( $email, $user_login, $first_name, $last_name, $role, $phone, null,$custom_registration_fields );
    	endif;


		if ( is_wp_error($result) ){
			  echo json_encode(array('registered'=>false, 'message'=> $result->get_error_message()));
	    } else {

	    	if ( get_option('listeo_autologin') ) {
	    		if ( get_option('listeo_display_password_field') ) :
		        	echo json_encode(array('registered'=>true, 'message'=>esc_html__('You have been successfully registered, you will be logged in a moment.','listeo_core')));
		        else :
		        	echo json_encode(array('registered'=>true, 'message'=>esc_html__('You have been successfully registered,  you will be logged in a moment. Please check your email for the password.','listeo_core')));
		        endif;	
	    	} else {
		    	if ( get_option('listeo_display_password_field') ) :
		        	echo json_encode(array('registered'=>true, 'message'=>esc_html__('You have been successfully registered, you can login now.','listeo_core')));
		        else :
		        	echo json_encode(array('registered'=>true, 'message'=>esc_html__('You have been successfully registered. Please check your email for the password.','listeo_core')));
		        endif;	
	    	}
	    	
	    }
		die();
	}


	function verify_hcaptcha($hcaptcha_response)
	{
		$secret = get_option('listeo_hcaptcha_secretkey');

		$verify_url = 'https://hcaptcha.com/siteverify';
		$data = array(
			'secret' => $secret,
			'response' => $hcaptcha_response
		);

		$verify = wp_remote_post($verify_url, array('body' => $data));

		if (is_wp_error($verify)) {
			return false;
		}

		$verify = json_decode(wp_remote_retrieve_body($verify));

		return $verify->success;
	}

	function verify_turnstile($turnstile_response)
	{
		$secret = get_option('listeo_turnstile_secretkey');

		$verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
		$data = array(
			'secret' => $secret,
			'response' => $turnstile_response
		);

		$verify = wp_remote_post($verify_url, array('body' => $data));

		if (is_wp_error($verify)) {
			return false;
		}

		$verify = json_decode(wp_remote_retrieve_body($verify));

		return $verify->success;
	}

	/**
	 * Pre-filter avatar data to use custom uploaded avatars.
	 *
	 * This runs BEFORE WordPress constructs the Gravatar URL, allowing us to:
	 * 1. Use custom uploaded avatars when available
	 * 2. Respect WordPress Discussion settings for default avatars when no custom avatar exists
	 *
	 * @since 2.5.0
	 * @param array $args        Arguments passed to get_avatar_data().
	 * @param mixed $id_or_email The avatar identifier (user ID, email, WP_User, WP_Post, or WP_Comment).
	 * @return array Modified arguments with custom avatar URL if available.
	 */
	public function listeo_core_pre_avatar_data( $args, $id_or_email ) {
		// Try to get the user from various input types
		$user = $this->listeo_core_get_user_from_avatar_id( $id_or_email );

		if ( ! $user ) {
			// No user found, let WordPress handle it with its default settings
			return $args;
		}

		// Check if user has a custom uploaded avatar
		$custom_avatar_id = get_user_meta( $user->ID, 'listeo_core_avatar_id', true );

		if ( empty( $custom_avatar_id ) ) {
			// No custom avatar, let WordPress use its configured default (respects Discussion settings)
			return $args;
		}

		// Get the custom avatar URL at the requested size
		$size = isset( $args['size'] ) ? (int) $args['size'] : 96;
		$custom_avatar = wp_get_attachment_image_src( $custom_avatar_id, array( $size, $size ) );

		// Fall back to 'listeo-avatar' size if exact size not available
		if ( ! $custom_avatar ) {
			$custom_avatar = wp_get_attachment_image_src( $custom_avatar_id, 'listeo-avatar' );
		}

		if ( $custom_avatar && ! empty( $custom_avatar[0] ) ) {
			// Set the custom avatar URL - this short-circuits Gravatar
			$args['url'] = $custom_avatar[0];
			$args['found_avatar'] = true;
		}

		return $args;
	}

	/**
	 * Helper function to resolve a user from various avatar identifier types.
	 *
	 * @since 2.5.0
	 * @param mixed $id_or_email The avatar identifier.
	 * @return WP_User|false The user object or false if not found.
	 */
	private function listeo_core_get_user_from_avatar_id( $id_or_email ) {
		$user = false;

		if ( is_object( $id_or_email ) ) {
			if ( $id_or_email instanceof WP_User ) {
				// Already a WP_User object
				$user = $id_or_email;
			} elseif ( $id_or_email instanceof WP_Post ) {
				// Get the post author
				$user = get_user_by( 'id', (int) $id_or_email->post_author );
			} elseif ( $id_or_email instanceof WP_Comment ) {
				// Get comment author
				if ( ! empty( $id_or_email->user_id ) ) {
					$user = get_user_by( 'id', (int) $id_or_email->user_id );
				} elseif ( ! empty( $id_or_email->comment_author_email ) ) {
					$user = get_user_by( 'email', $id_or_email->comment_author_email );
				}
			} elseif ( isset( $id_or_email->user_id ) && $id_or_email->user_id != 0 ) {
				// Generic object with user_id property
				$user = get_user_by( 'id', (int) $id_or_email->user_id );
			} elseif ( isset( $id_or_email->comment_author_email ) && ! empty( $id_or_email->comment_author_email ) ) {
				// Generic object with comment_author_email property
				$user = get_user_by( 'email', $id_or_email->comment_author_email );
			}
		} elseif ( is_numeric( $id_or_email ) ) {
			// User ID
			$user = get_user_by( 'id', (int) $id_or_email );
		} elseif ( is_string( $id_or_email ) ) {
			if ( is_email( $id_or_email ) ) {
				// Email address
				$user = get_user_by( 'email', $id_or_email );
			} elseif ( strpos( $id_or_email, '@' ) === false && strlen( $id_or_email ) <= 32 ) {
				// Might be a Gravatar hash, can't resolve to user
				$user = false;
			}
		}

		// Fallback: try to get author from current context
		if ( ! $user && empty( $id_or_email ) ) {
			if ( is_author() ) {
				$author_name = get_query_var( 'author_name' );
				if ( $author_name ) {
					$user = get_user_by( 'slug', $author_name );
				}
			} else {
				$user_id = get_the_author_meta( 'ID' );
				if ( $user_id ) {
					$user = get_user_by( 'id', $user_id );
				}
			}
		}

		return $user;
	}

	/**
	 * Legacy filter for get_avatar.
	 *
	 * Kept for backwards compatibility. The main avatar handling is now done
	 * via pre_get_avatar_data filter which properly respects WordPress default settings.
	 *
	 * @deprecated 2.5.0 Use listeo_core_pre_avatar_data instead.
	 */
	function listeo_core_gravatar_filter( $avatar, $id_or_email, $size, $default, $alt, $args ) {
		// The pre_get_avatar_data filter already handles custom avatars.
		// This filter is kept for backwards compatibility with themes/plugins
		// that might hook into it or expect specific behavior.

		// If pre_get_avatar_data already set a custom URL, the $avatar will contain it.
		// We just need to verify and pass through.
		return $avatar;
	}
	
	/**
	 * Actions in dashboard
	 */
	public function dashboard_action_handler() {
		global $post;

		if (isset($post->ID) && $post->ID == (get_option('listeo_listings_page'))) {
			
			if ( ! empty( $_REQUEST['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'listeo_core_my_listings_actions' ) ) {

			$action = sanitize_title( $_REQUEST['action'] );
			$listing_id = absint( $_REQUEST['listing_id'] );
			$current_user = wp_get_current_user();
    		
			$listing         = get_post( $listing_id );

			try {
				// Get Job
				$listing    = get_post( $listing_id );
				$listing_data = get_post( $listing );
				if ( ! $listing_data || 'listing' !== $listing_data->post_type ) {
					$title = false;
				} else {
					$title = esc_html( get_the_title( $listing_data ) );	
				}



				
				switch ( $action ) {
					
					case 'delete' :
						// Trash it
						if($current_user->ID == $listing->post_author && 'listing' == $listing_data->post_type ){
							wp_trash_post( $listing_id );

						// Message
							$this->dashboard_message =  '<div class="notification closeable success"><p>' . sprintf( __( '%s has been deleted', 'listeo_core' ), $title ) . '</p><a class="close" href="#"></a></div>';
	
						} else {
							$this->dashboard_message =  '<div class="notification closeable error"><p>' . __( 'You are trying to remove not your listing', 'listeo_core' ). '</p><a class="close" href="#"></a></div>';
						}
						
						break;
					case 'unpublish':
// Messages
						if($current_user->ID == $listing->post_author && 'listing' == $listing_data->post_type ){
							 wp_update_post(array(
						        'ID'    =>  $listing_id,
						        'post_status'   =>  'draft'
					        ));
							$this->dashboard_message =  '<div class="notification closeable success"><p>' . sprintf( __( '%s has been unpublished', 'listeo_core' ), $title ) . '</p><a class="close" href="#"></a></div>';
						}
					break;
					case 'renew':
							if(!get_option('listeo_new_listing_requires_purchase')){
							if($current_user->ID == $listing->post_author && 'listing' == $listing_data->post_type ){
								wp_update_post(array(
							        'ID'    =>  $listing_id,
							        'post_status'   =>  'publish'
						        ));
									delete_post_meta($listing_id, "_listing_expires");   
						        	$post_types_expiry = new Listeo_Core_Post_Types;
									$post_types_expiry->set_expiry(get_post($listing_id));
								$this->dashboard_message =  '<div class="notification closeable success"><p>' . sprintf( __( '%s has been renewed', 'listeo_core' ), $title ) . '</p><a class="close" href="#"></a></div>';
								}
							}
					break;
					default :
						do_action( 'listeo_core_dashboard_do_action_' . $action );
						break;
				}

				do_action( 'listeo_core_my_listing_do_action', $action, $listing_id );

			} catch ( Exception $e ) {
				$this->dashboard_message = '<div class="notification closeable error">' . $e->getMessage() . '</div>';
			}
		}
		}
	}


	function author_archive_listings( $query ) {
	  
		if ($query->is_main_query() && $query->is_author() && $query->is_archive() ) {
			
	        $query->set( 'post_type', array('listing') );
	    }
		 
		return $query;

	}

	function modify_contact_methods($profile_fields) {

		// Add new fields
		$profile_fields['phone'] 	= esc_html__('Phone','listeo_core');
		$profile_fields['twitter'] 	= esc_html__('Twitter ','listeo_core');
		$profile_fields['facebook'] = esc_html__('Facebook URL','listeo_core');
		
		$profile_fields['linkedin'] = esc_html__('Linkedin','listeo_core');
		$profile_fields['instagram'] = esc_html__('Instagram','listeo_core');
		$profile_fields['youtube'] = esc_html__('YouTube','listeo_core');
		$profile_fields['skype'] = esc_html__('Skype','listeo_core');
		$profile_fields['whatsapp'] = esc_html__('WhatsApp','listeo_core');

		return $profile_fields;
	}
	
	protected function get_posted_field( $key, $field ) {
		
		return isset( $_POST[ $key ] ) ? $this->sanitize_posted_field( $_POST[ $key ] ) : '';
	}

	
	protected function get_posted_file_field( $key, $field ) {

		$file = $this->upload_file( $key, $field );
		
		if ( ! $file ) {
			$file = $this->get_posted_field( 'current_' . $key, $field );
		} elseif ( is_array( $file ) ) {
			$file = array_filter( array_merge( $file, (array) $this->get_posted_field( 'current_' . $key, $field ) ) );
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
	protected function upload_file( $field_key, $field ) {
		if ( isset( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ]['name'] ) ) {
			if ( ! empty( $field['allowed_mime_types'] ) ) {
				$allowed_mime_types = $field['allowed_mime_types'];
			} else {
				$allowed_mime_types = listeo_get_allowed_mime_types();
			}

			$file_urls       = array();
			$files_to_upload = listeo_prepare_uploaded_files( $_FILES[ $field_key ] );

			foreach ( $files_to_upload as $file_to_upload ) {
				$uploaded_file = listeo_upload_file( $file_to_upload, array(
					'file_key'           => $field_key,
					'allowed_mime_types' => $allowed_mime_types,
					) );

				if ( is_wp_error( $uploaded_file ) ) {
					throw new Exception( $uploaded_file->get_error_message() );
				} else {
					$file_urls[] = $uploaded_file->url;
					// Get the ID of the current user
					$user_id = get_current_user_id();

					// Get the ID of the attachment
					$attachment_id = attachment_url_to_postid($uploaded_file->url);

					// Update the 'post_author' of the attachment
					wp_update_post(
						array(
							'ID'            => $attachment_id, // ID of the post to update
							'post_author'   => $user_id, // ID of the new post author
						)
					);
				}
			}

			if ( ! empty( $field['multiple'] ) ) {
				return $file_urls;
			} else {
				return current( $file_urls );
			}
		}
	}
	/**
	 * Navigates through an array and sanitizes the field.
	 *
	 * @param array|string $value The array or string to be sanitized.
	 * @return array|string $value The sanitized array (or string from the callback).
	 */
	protected function sanitize_posted_field( $value ) {
		// Santize value
		$value = is_array( $value ) ? array_map( array( $this, 'sanitize_posted_field' ), $value ) : sanitize_text_field( stripslashes( trim( $value ) ) );

		return $value;
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_textarea_field( $key, $field ) {
		return isset( $_POST[ $key ] ) ? wp_kses_post( trim( stripslashes( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_wp_editor_field( $key, $field ) {
		return $this->get_posted_textarea_field( $key, $field );
	}

	protected function create_attachment( $attachment_url ) {
		include_once( ABSPATH . 'wp-admin/includes/image.php' );
		include_once( ABSPATH . 'wp-admin/includes/media.php' );

		$upload_dir     = wp_upload_dir();
		$attachment_url = str_replace( array( $upload_dir['baseurl'], WP_CONTENT_URL, site_url( '/' ) ), array( $upload_dir['basedir'], WP_CONTENT_DIR, ABSPATH ), $attachment_url );

		if ( empty( $attachment_url ) || ! is_string( $attachment_url ) ) {
			return 0;
		}

		$attachment     = array(
			'post_title'   =>  wp_generate_password( 8, false ),
			'post_content' => '',
			'post_status'  => 'inherit',
			'guid'         => $attachment_url
		);

		if ( $info = wp_check_filetype( $attachment_url ) ) {
			$attachment['post_mime_type'] = $info['type'];
		}

		$attachment_id = wp_insert_attachment( $attachment, $attachment_url );

		if ( ! is_wp_error( $attachment_id ) ) {
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $attachment_url ) );
			return $attachment_id;
		}

		return 0;
	}

	function submit_my_account_form() {
		global $blog_id, $wpdb;
		if ( isset( $_POST['my-account-submission'] ) && '1' == $_POST['my-account-submission'] ) {

			// Verify nonce from either the wallet form or the my-account form
			$nonce_valid = false;
			if ( isset( $_POST['wallet_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wallet_nonce'] ) ), 'listeo_wallet_form' ) ) {
				$nonce_valid = true;
			} elseif ( isset( $_POST['my_account_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['my_account_nonce'] ) ), 'listeo_my_account_form' ) ) {
				$nonce_valid = true;
			}
			if ( ! $nonce_valid ) {
				return;
			}

			$current_user = wp_get_current_user();
			$error = array();

			if ( !empty( $_POST['url'] ) ) {
		       	wp_update_user( array ('ID' => $current_user->ID, 'user_url' => esc_attr( $_POST['url'] )));
			}
			
			if ( isset( $_POST['role'] ) && get_option('listeo_profile_allow_role_change') ) {
				$allowed_roles = array( 'owner', 'seller', 'guest' );
				$requested_role = sanitize_text_field( wp_unslash( $_POST['role'] ) );
				if ( in_array( $requested_role, $allowed_roles, true ) ) {
					$current_user->set_role( $requested_role );
				}
			}

		    if ( isset( $_POST['email'] ) ){

		        if (!is_email(esc_attr( $_POST['email'] ))) {
		            $error = 'error_1'; // __('The Email you entered is not valid.  please try again.', 'profile');
		        	
		        } else {
		        	if(email_exists(esc_attr( $_POST['email'] ) ) ) {
		        		if(email_exists(esc_attr( $_POST['email'] ) ) != $current_user->ID) {
		        			$error = 'error_2'; // __('This email is already used by another user.  try a different one.', 'profile');	
		        		}
		            	
		        	} else {
		            $user_id = wp_update_user( 
		            	array (
		            		'ID' => $current_user->ID, 
		            		'user_email' => esc_attr( $_POST['email'] )
		            	)
		            );
		            }
		        }
		    }

		    if ( isset( $_POST['first-name'] ) ) {
				delete_user_meta($current_user->ID, 'first_name'); 
		        update_user_meta( $current_user->ID, 'first_name', esc_attr( $_POST['first-name'] ) );
		    }
		    
		    if ( isset( $_POST['last-name'] ) ){
				delete_user_meta($current_user->ID, 'last_name'); 
		        update_user_meta($current_user->ID, 'last_name', esc_attr( $_POST['last-name'] ) );
		    }		    

		    if ( isset( $_POST['phone'] ) ){
				delete_user_meta($current_user->ID, 'phone'); 
		        update_user_meta($current_user->ID, 'phone', esc_attr( $_POST['phone'] ) );
		    }		     				    

		    if ( isset( $_POST['sms_notifications']) &&  $_POST['sms_notifications'] == 'on' ){
				delete_user_meta($current_user->ID, 'sms_notifications'); 
		        update_user_meta($current_user->ID, 'sms_notifications', esc_attr( $_POST['sms_notifications'] ) );
		    } else {
				delete_user_meta($current_user->ID, 'sms_notifications'); 
			}     				    
		    if ( isset( $_POST['email_notifications'] )  && $_POST['email_notifications'] == 'on'){
				
				delete_user_meta($current_user->ID, 'email_notifications'); 
		        update_user_meta($current_user->ID, 'email_notifications', esc_attr( $_POST['email_notifications'] ) );
		    } else {
				delete_user_meta($current_user->ID, 'email_notifications');
			}		     				    



	    	
		    
		    if ( isset( $_POST['display_name'] ) ) {
		        wp_update_user(array('ID' => $current_user->ID, 'display_name' => esc_attr( $_POST['display_name'] )));
		     	update_user_meta($current_user->ID, 'display_name' , esc_attr( $_POST['display_name'] ));
		    }
		    if (isset( $_POST['description']) ) {
				delete_user_meta($current_user->ID, 'description'); 
		        update_user_meta( $current_user->ID, 'description', sanitize_textarea_field( $_POST['description'] ) );
		    }

		    if ( isset( $_POST['listeo_core_avatar_id'] ) ) {
		        $new_avatar_id = absint( $_POST['listeo_core_avatar_id'] );
		        $old_avatar_id = absint( get_user_meta( $current_user->ID, 'listeo_core_avatar_id', true ) );

		        // Delete old avatar attachment if it's being replaced or cleared
		        if ( $old_avatar_id && $old_avatar_id !== $new_avatar_id ) {
		            wp_delete_attachment( $old_avatar_id, true );
		        }

		        update_user_meta( $current_user->ID, 'listeo_core_avatar_id', $new_avatar_id );
		    }

		    
		   //bank details
		    if ( isset( $_POST['payment_type'] ) ) {
		        update_user_meta( $current_user->ID, 'listeo_core_payment_type', esc_attr( $_POST['payment_type'] ) );
		    }
		    if ( isset( $_POST['ppemail'] ) ) {
		        update_user_meta( $current_user->ID, 'listeo_core_ppemail', esc_attr( $_POST['ppemail'] ) );
		    }
		    if ( isset( $_POST['bank_details'] ) ) {
		        update_user_meta( $current_user->ID, 'listeo_core_bank_details', esc_attr( $_POST['bank_details'] ) );
		    }
		    if ( isset( $_POST['listeo_paypal_payout_email'] ) ) {
                update_user_meta( $current_user->ID, 'listeo_paypal_payout_email', esc_attr( $_POST['listeo_paypal_payout_email'] ) );
            }

			
		    $roles = $current_user->roles;
			$role = array_shift( $roles ); 
			switch ($role) {
	          	case 'seller':
	          	case 'owner':
	          		$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
	          		break;
	          	case 'guest':
	          		$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_guest();
	          		break;              	
	          	default:
	          		$fields = $fields = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
	          	break;
	          }
				        
			// foreach ( $fields as $key => $field ) : 
				
			// 	if ( isset($_POST[$field['id']]) && !empty( $_POST[$field['id']] ) ){

			//         update_user_meta($current_user->ID, $field['id'], esc_attr( $_POST[$field['id']] ) );
			//     }
			// endforeach;



			$values = array();

		
			foreach ( $fields as $key => $field ) {
				// Get the value
				$field_type = str_replace( '-', '_', $field['type'] );
				
				if ( $handler = apply_filters( "listeo_core_get_posted_{$field_type}_field", false ) ) {
					
					$values[ $key ] = call_user_func( $handler, $key, $field );
				} elseif ( method_exists( $this, "get_posted_{$field_type}_field" ) ) {
					
					$values[ $key ] = call_user_func( array( $this, "get_posted_{$field_type}_field" ), $key, $field );
				} else {
					
					$values[ $key ] = $this->get_posted_field( $key, $field );
				}
				
				// Set fields value

				$fields[ $key ]['value'] = $values[ $key ];

			}
			
			
			foreach ( $fields as $key => $field ) : 
			
				if( $field['type'] == 'select_multiple' || $field['type'] == 'multicheck_split') {
					
					delete_user_meta($current_user->ID, $key); 
					
					if ( is_array( $values[ $key ] ) ) {
					
							update_user_meta($current_user->ID, $key, $values[ $key ] );	
					}
				} else {
					delete_user_meta($current_user->ID, $key); 
					update_user_meta( $current_user->ID, $key, $values[$key ] );
				}

				//update_post_meta( $this->listing_id, $key, $values[ $group_key ][ $key ] );	
				
			
				// // Handle attachments
				if ( 'file' === $field['type'] ) {
				
					//$attachment_id = is_numeric( $values[ $key ] ) ? $values[ $key ]  : $this->create_attachment( $values[ $key ] );
					$attachment_id = $this->create_attachment( $values[ $key ] );
			
					update_user_meta( $current_user->ID, $key.'_id', $attachment_id  );
				
				}
				
			endforeach;





			if ( (is_array($error) && count($error) == 0) || empty($error) ) {
		        //action hook for plugins and extra fields saving
		        //do_action('edit_user_profile_update', $current_user->ID);
		        wp_redirect( get_permalink().'?updated=true' ); 
		        exit;
		    } else {
				wp_redirect( get_permalink().'?user_err_pass='.$error ); 
				exit;
				 
			} 
		} // end if

	} // end 
	
	public function submit_change_password_form(){
		$error = false;
		if ( isset( $_POST['listeo_core-password-change'] ) && '1' == $_POST['listeo_core-password-change'] ) {
			$current_user = wp_get_current_user();
			if ( !empty($_POST['current_pass']) && !empty($_POST['pass1'] ) && !empty( $_POST['pass2'] ) ) {

				if ( !wp_check_password( $_POST['current_pass'], $current_user->user_pass, $current_user->ID) ) {
					/*$error = 'Your current password does not match. Please retry.';*/
					$error = 'error_1';
				} elseif ( $_POST['pass1'] != $_POST['pass2'] ) {
					/*$error = 'The passwords do not match. Please retry.';*/
					$error = 'error_2';
				} elseif ( strlen($_POST['pass1']) < 4 ) {
					/*$error = 'A bit short as a password, don\'t you think?';*/
					$error = 'error_3';
				} elseif ( false !== strpos( wp_unslash($_POST['pass1']), "\\" ) ) {
					/*$error = 'Password may not contain the character "\\" (backslash).';*/
					$error = 'error_4';
				} else {
					$user_id  = wp_update_user( array( 'ID' => $current_user->ID, 'user_pass' => esc_attr( $_POST['pass1'] ) ) );
					
					if ( is_wp_error( $user_id ) ) {
						/*$error = 'An error occurred while updating your profile. Please retry.';*/
						$error = 'error_5';
					} else {
						$error = false;
						//do_action('edit_user_profile_update', $current_user->ID);
				        wp_redirect( get_permalink().'?updated_pass=true' ); 
				        exit;
					}
				}
			
				if ( !$error ) {
					//do_action('edit_user_profile_update', $current_user->ID);
			        wp_redirect( get_permalink().'?updated_pass=true' ); 
			        exit;
				} else {
					wp_redirect( get_permalink().'?err_pass='.$error ); 
					exit;
					 
				}
				
			} else {
				$error = 'error_6';
				wp_redirect( get_permalink().'?err_pass='.$error ); 
					exit;
			}
		} // end if
	}

	public function  extra_profile_fields( $user ) { ?>
		 
		<h3><?php esc_html_e('Listeo Fields' , 'listeo_core' ); ?></h3>
		 <?php wp_enqueue_media(); ?>
		<table class="form-table">

		 
		<tr>
		<th><label for="image">User Avatar</label></th>
		 
		<td>
			<?php 
				$custom_avatar_id = get_the_author_meta( 'listeo_core_avatar_id', $user->ID ) ;
				$custom_avatar = wp_get_attachment_image_src($custom_avatar_id,'listeo-avatar');
				if ($custom_avatar)  {
					echo '<img src="'.$custom_avatar[0].'" style="width:100px;height: auto;"/><br>';
				} 
			?>
		<input type="text" name="listeo_core_avatar_id" id="agent-avatar" value="<?php echo esc_attr( get_the_author_meta( 'listeo_core_avatar_id', $user->ID ) ); ?>" class="regular-text" />
		<input type='button' class="realteo-additional-user-image button-primary" value="<?php _e( 'Upload Image','listeo_core' ); ?>" id="uploadimage"/><br />
		<span class="description"><?php esc_html_e('This avatar will be displayed instead of default one','listeo_core'); ?></span>
		</td>
		</tr>
		 		<tr>
		<th><label for="image">Verified user</label></th>
		 
		<td>
			<?php 
			$value = get_the_author_meta('listeo_verified_user', $user->ID);?>
			<input type="checkbox" name="listeo_verified_user"  value="on" <?php if( isset( $value)&& !empty($value ) ){ echo "checked"; }  ?>>
						
		
		<span class="description"><?php esc_html_e('If you set the user as verified it will mark all his listing as verified','listeo_core'); ?></span>
		</td>
		</tr>
		 		<tr>
		<th><label for="listeo_commission_type"><?php esc_html_e('Commission Type', 'listeo_core'); ?></label></th>
		<td>
			<?php $commission_type = get_the_author_meta('listeo_commission_type', $user->ID); ?>
			<select name="listeo_commission_type">
				<option value=""><?php esc_html_e('Use global setting', 'listeo_core'); ?></option>
				<option value="percentage" <?php selected($commission_type, 'percentage'); ?>><?php esc_html_e('Percentage (%)', 'listeo_core'); ?></option>
				<option value="fixed" <?php selected($commission_type, 'fixed'); ?>><?php esc_html_e('Fixed Amount', 'listeo_core'); ?></option>
			</select>
			<br>
			<span class="description"><?php esc_html_e('Override global commission type for this user. Leave empty to use global setting.', 'listeo_core'); ?></span>
		</td>
		</tr>

		<tr>
		<th><label for="listeo_commission_rate"><?php esc_html_e('Commission Value', 'listeo_core'); ?></label></th>
		<td>
			<?php $value = get_the_author_meta('listeo_commission_rate', $user->ID); ?>
			<input type="number" name="listeo_commission_rate" value="<?php echo $value; ?>" step="0.01">
			<br>
			<span class="description"><?php esc_html_e('Custom commission value (percentage or fixed amount based on type). Leave empty to use global setting.', 'listeo_core'); ?></span>
		</td>
		</tr>

		<tr>
		<th><label for="listeo_commission_calculation_method"><?php esc_html_e('Commission Calculation Method', 'listeo_core'); ?></label></th>
		<td>
			<?php $calculation_method = get_the_author_meta('listeo_commission_calculation_method', $user->ID); ?>
			<select name="listeo_commission_calculation_method">
				<option value=""><?php esc_html_e('Use global setting', 'listeo_core'); ?></option>
				<option value="deduct" <?php selected($calculation_method, 'deduct'); ?>><?php esc_html_e('Deduct from booking total', 'listeo_core'); ?></option>
				<option value="add" <?php selected($calculation_method, 'add'); ?>><?php esc_html_e('Add to booking total', 'listeo_core'); ?></option>
			</select>
			<br>
			<span class="description"><?php esc_html_e('Override how commission is calculated for this user. Leave empty to use global setting.', 'listeo_core'); ?></span>
		</td>
		</tr>

		<?php
		// Only show Zoom section if OAuth is configured
		$zoom_client_id = get_option('listeo_zoom_oauth_client_id' );
		
		if ( ! empty( $zoom_client_id ) ) :
		?>
		<tr>
		<th><label><?php esc_html_e('Zoom Integration', 'listeo_core'); ?></label></th>
		<td>
			<?php
			// Get Zoom Integration instance
			$zoom_integration = new Listeo_Zoom_Integration();
			$is_connected = $zoom_integration->is_zoom_connected( $user->ID );
			$zoom_user_email = get_the_author_meta('zoom_user_email', $user->ID);
			?>

			<p><strong><?php esc_html_e('Connect your Zoom account to automatically create meetings for service bookings.', 'listeo_core'); ?></strong></p>

			<?php if ( $is_connected ) : ?>
				<div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;">
					<p style="margin: 0; color: #155724;">
						<span class="dashicons dashicons-yes-alt" style="color: #28a745;"></span>
						<strong><?php esc_html_e('Zoom Connected', 'listeo_core'); ?></strong>
						<?php if ( $zoom_user_email ) : ?>
							<br><span style="font-size: 13px;"><?php echo esc_html( $zoom_user_email ); ?></span>
						<?php endif; ?>
					</p>
				</div>
				<p>
					<a href="javascript:void(0);" id="listeo-zoom-disconnect-btn" class="button button-secondary">
						<?php esc_html_e('Disconnect Zoom', 'listeo_core'); ?>
					</a>
				</p>
				<script>
				jQuery(document).ready(function($) {
					$('#listeo-zoom-disconnect-btn').on('click', function() {
						if (!confirm('<?php esc_html_e('Are you sure you want to disconnect your Zoom account?', 'listeo_core'); ?>')) {
							return;
						}

						var $btn = $(this);
						$btn.prop('disabled', true).text('<?php esc_html_e('Disconnecting...', 'listeo_core'); ?>');

						$.post(ajaxurl, {
							action: 'listeo_zoom_disconnect',
							security: '<?php echo wp_create_nonce('listeo-zoom-disconnect'); ?>'
						}, function(response) {
							if (response.success) {
								location.reload();
							} else {
								alert(response.data.message || '<?php esc_html_e('Failed to disconnect', 'listeo_core'); ?>');
								$btn.prop('disabled', false).text('<?php esc_html_e('Disconnect Zoom', 'listeo_core'); ?>');
							}
						});
					});
				});
				</script>
			<?php else : ?>
				<?php
				$connect_url = $zoom_integration->get_authorization_url( $user->ID );
				if ( $connect_url ) : ?>
					<div style="padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin: 10px 0;">
						<p style="margin: 0; color: #856404;">
							<span class="dashicons dashicons-warning" style="color: #ffc107;"></span>
							<?php esc_html_e('Zoom not connected. Click below to authorize access.', 'listeo_core'); ?>
						</p>
					</div>
					<p>
						<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary">
							<?php esc_html_e('Connect Zoom Account', 'listeo_core'); ?>
						</a>
					</p>
				<?php else : ?>
					<div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">
						<p style="margin: 0; color: #721c24;">
							<span class="dashicons dashicons-dismiss" style="color: #dc3545;"></span>
							<?php esc_html_e('Zoom OAuth is not configured. Please contact the site administrator to configure Zoom OAuth settings.', 'listeo_core'); ?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e('Once connected, you can enable Zoom meetings for individual service listings. Meetings will be automatically created when bookings are confirmed.', 'listeo_core'); ?>
			</p>
		</td>
		</tr>
		<?php endif; // End Zoom OAuth check ?>

		</table>

		<h3><?php esc_html_e('Extra profile information' , 'listeo_core' ); ?></h3>
		 
		<table class="form-table">

		 


		<?php 
		
				//get user role based on user id
		$roles = $user->roles;

		
		$role = array_shift( $roles ); 
		switch ($role) {
          	case 'seller':
          	case 'owner':
          		$fields_user = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
          		break;
          	case 'guest':
          		$fields_user = Listeo_Core_Meta_Boxes::meta_boxes_user_guest();
          		break;              	
          	default:
          		$fields_user = $fields = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
          	break;
      	}

			$fields_user['stripe_user_id'] = array(
				'name' => 'Stripe Connect user ID',
				'label' => 'Stripe Connect user ID',
				'id' => 'stripe_user_id',
				'type' => 'text',
				'invert' => false,
				'desc' => '',
				'options_source' => NULL,
				'options_cb' => NULL,
				'options' => NULL
			
			);
			$fields_user['full_phone'] = array(
				'name' => 'Phone number',
				'name' => 'Phone number',
				'id' => 'full_phone',
				'type' => 'text',
				'invert' => false,
				'desc' => '',
				'options_source' => NULL,
				'options_cb' => NULL,
				'options' => NULL
			
			);
			foreach ( $fields_user as $key => $field ) : ?>
		<tr>
			<th><label for="image"><?php echo $field['name']; ?></label></th>	 
			<td>

				<?php
				
				 switch ($field['type']) {
					case 'text':
						?>
						<input type="text" name="<?php echo $field['id']; ?>" id="<?php echo $field['id']; ?>" value="<?php echo esc_attr( get_the_author_meta( $field['id'], $user->ID ) ); ?>" class="regular-text" />
				
						<?php
						break;
					case 'radio':
					?>
					<div class="listeo-radios in-row margin-bottom-20">
						<?php $value = get_the_author_meta( $field['id'], $user->ID ); ?>
						<?php foreach ( $field['options'] as $slug => $name ) : ?>
						<div>
							<input id="<?php echo esc_html($slug) ?>" type="radio" name="<?php echo $key; ?>"
							<?php  checked($value,$slug) ?> value="<?php echo esc_html($slug); ?>"
							>
							<label for="<?php echo esc_html($slug) ?>"><?php echo esc_html($name) ?></label>
						</div>
						<?php endforeach; ?>

					</div>
					<?php
					break;
					case 'wp-editor':

							$editor = apply_filters( 'listeo_core_user_data_wp_editor_args', array(
								'textarea_name' => isset( $field['id'] ) ? $field['id'] : '',
								'media_buttons' => false,
								'textarea_rows' => 8,
								'quicktags'     => false,
								'tinymce'       => array(
									'plugins'                       => 'lists,paste,tabfocus,wplink,wordpress',
									'paste_as_text'                 => true,
									'paste_auto_cleanup_on_paste'   => true,
									'paste_remove_spans'            => true,
									'paste_remove_styles'           => true,
									'paste_remove_styles_if_webkit' => true,
									'paste_strip_class_attributes'  => true,
									'toolbar1'                      => 'bold,italic,|,bullist,numlist,|,link,unlink,|,undo,redo',
									'toolbar2'                      => '',
									'toolbar3'                      => '',
									'toolbar4'                      => ''
								),
							) );
							$field['value'] = get_the_author_meta( $field['id'], $user->ID );
							wp_editor( isset( $field['value'] ) ? wp_kses_post( $field['value'] ) : '', $key, $editor );
					break;
					case 'select':
						$field['value'] = get_the_author_meta( $field['id'], $user->ID );
						?>
						<select name="<?php echo esc_attr( isset( $field['id'] ) ? $field['id'] : $key ); ?>" id="<?php echo esc_attr( $key ); ?>"><?php if(isset($field['placeholder']) && !empty($field['placeholder'])) : ?>
								<option value=""><?php echo esc_attr($field['placeholder']);?></option>
							<?php endif ?>
							<?php foreach ( $field['options'] as $key => $value ) : ?>	
							<option value="<?php echo esc_attr( $key ); ?>" <?php 
								if ( isset( $field['value'] ) || isset( $field['default'] ) ) 
									if(isset( $field['value']) && is_array($field['value'])){
										if( in_array($key,$field['value']) ) {
											echo "selected='selected'";
										}
									} else {
										selected( isset( $field['value'] ) ? $field['value'] : $field['default'], $key );
									}
									 ?> >
								<?php echo esc_html( $value ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php 
					break;

					case 'select_multiple':
					$field['value'] = get_the_author_meta( $field['id'], $user->ID );
					if(isset( $field['options_cb'] ) && !empty($field['options_cb'])){
							switch ($field['options_cb']) {
								case 'realteo_get_offer_types_flat':
									$field['options'] = realteo_get_offer_types_flat(false);
									break;

								case 'realteo_get_property_types':
									$field['options'] = realteo_get_property_types();
									break;

								case 'realteo_get_rental_period':
									$field['options'] = realteo_get_rental_period();
									break;
								
								default:
									# code...
									break;
							}	
						} ?>
							<select multiple name="<?php echo esc_attr($field['id']);?>[]" id="<?php echo esc_attr( $key ); ?>"><?php if(isset($field['placeholder']) && !empty($field['placeholder'])) : ?>
									<option value=""><?php echo esc_attr($field['placeholder']);?></option>
								<?php endif ?>
								<?php foreach ( $field['options'] as $key => $value ) : ?>	
								<option value="<?php echo esc_attr( $key ); ?>" <?php 
									if ( isset( $field['value'] ) || isset( $field['default'] ) ) 
										if(isset( $field['value']) && is_array($field['value'])){
											if( in_array($key,$field['value']) ) {
												echo "selected='selected'";
											}
										} else {
											selected( isset( $field['value'] ) ? $field['value'] : $field['default'], $key );
										}
										 ?> >
									<?php echo esc_html( $value ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php 
					break;

					case 'checkbox':
					$field['value'] = get_the_author_meta( $field['id'], $user->ID );?>

						<input type="checkbox" name="<?php echo $field['id'] ?>"  value="on" <?php if( isset( $field['value'])&& !empty($field['value'] ) ){ echo "checked"; }  ?>>
						
						
					<?php
					break;
					case 'file':
					$field['value'] = get_the_author_meta( $field['id'], $user->ID );?>

						<ul>
							<li>Attachment ID: <input type="text" name="<?php echo $field['id']; ?>" id="<?php echo $field['id']; ?>" value="<?php echo esc_attr( get_the_author_meta( $field['id'], $user->ID ) ); ?>" class="regular-text" /></li>
							<?php if($field['value']): ?><li><a href="<?php echo wp_get_attachment_url($field['value']);?>" target="_blank">Download this file &darr;</a></li><?php endif; ?>
						</ul>
				
						
					<?php
					break;

					case 'multicheck_split':
						$value = get_the_author_meta( $field['id'], $user->ID );
						
						foreach ( $field['options'] as $slug => $name ) : ?>

							<input id="<?php echo esc_html($slug) ?>" type="checkbox" name="<?php echo $key.'[]'; ?>"
							<?php  if(is_array($value) && in_array($slug,$value))  : ?> checked="checked" <?php endif; ?> value="<?php echo esc_html($slug); ?>"
							>
							<label for="<?php echo esc_html($slug) ?>"><?php echo esc_html($name) ?></label>
						
						<?php endforeach;
					break;

					default:
					break;
				} ?>
				
			</td>
		</tr>
			<?php endforeach;
		?>
		 
		</table>
	<?php }


	function save_extra_profile_fields( $user_id ) {

		if ( !current_user_can( 'edit_user', $user_id ) )
		return false;


		if ( isset( $_POST['listeo_core_avatar_id'] ) ) {
			$new_avatar_id = absint( $_POST['listeo_core_avatar_id'] );
			$old_avatar_id = absint( get_user_meta( $user_id, 'listeo_core_avatar_id', true ) );

			// Delete old avatar attachment if it's being replaced or cleared
			if ( $old_avatar_id && $old_avatar_id !== $new_avatar_id ) {
				wp_delete_attachment( $old_avatar_id, true );
			}

			update_user_meta( $user_id, 'listeo_core_avatar_id', $new_avatar_id );
		}
		if(isset($_POST['listeo_verified_user']) && !empty($_POST['listeo_verified_user'])) {
			update_user_meta( $user_id, 'listeo_verified_user', $_POST['listeo_verified_user'] );	
		} else {
			update_user_meta($user_id, 'listeo_verified_user', '');	
		}
		if (isset($_POST['listeo_commission_rate']) && !empty($_POST['listeo_commission_rate'])) {
			update_user_meta($user_id, 'listeo_commission_rate', $_POST['listeo_commission_rate']);
		} else {
			delete_user_meta($user_id, 'listeo_commission_rate');
		}

		if (isset($_POST['listeo_commission_type']) && !empty($_POST['listeo_commission_type'])) {
			update_user_meta($user_id, 'listeo_commission_type', $_POST['listeo_commission_type']);
		} else {
			delete_user_meta($user_id, 'listeo_commission_type');
		}

		if (isset($_POST['listeo_commission_calculation_method']) && !empty($_POST['listeo_commission_calculation_method'])) {
			update_user_meta($user_id, 'listeo_commission_calculation_method', $_POST['listeo_commission_calculation_method']);
		} else {
			delete_user_meta($user_id, 'listeo_commission_calculation_method');
		}

		$current_user = wp_get_current_user();
			
		
		    $roles = $current_user->roles;
			$role = array_shift( $roles ); 
			switch ($role) {
	          	case 'seller':
	          	case 'owner':
	          		$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
	          		break;
	          	case 'guest':
	          		$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_guest();
	          		break;              	
	          	default:
	          		$fields = Listeo_Core_Meta_Boxes::meta_boxes_user_owner();
	          	break;
	          }
		$fields['stripe_user_id'] = array(
			'name' => 'Stripe Connect user ID',
			'label' => 'Stripe Connect user ID',
			'id' => 'stripe_user_id',
			'type' => 'text',
			'invert' => false,
			'desc' => '',
			'options_source' => NULL,
			'options_cb' => NULL,
			'options' => NULL

		);	        
			// foreach ( $fields as $key => $field ) : 
				
			// 	if ( isset($_POST[$field['id']]) && !empty( $_POST[$field['id']] ) ){

			//         update_user_meta($current_user->ID, $field['id'], esc_attr( $_POST[$field['id']] ) );
			//     }
			// endforeach;



			$values = array();

		
			foreach ( $fields as $key => $field ) {
				// Get the value
				$field_type = str_replace( '-', '_', $field['type'] );
				
				if($field_type == 'file') { $field_type = 'text'; } 

				if ( $handler = apply_filters( "listeo_core_get_posted_{$field_type}_field", false ) ) {
					
					$values[ $key ] = call_user_func( $handler, $key, $field );
				} elseif ( method_exists( $this, "get_posted_{$field_type}_field" ) ) {
					
					$values[ $key ] = call_user_func( array( $this, "get_posted_{$field_type}_field" ), $key, $field );
				} else {
					
					$values[ $key ] = $this->get_posted_field( $key, $field );
				}
				
				// Set fields value

				$fields[ $key ]['value'] = $values[ $key ];

			}


			
			foreach ( $fields as $key => $field ) : 
			
				if( $field['type'] == 'select_multiple' || $field['type'] == 'multicheck_split') {
					
					delete_user_meta($current_user->ID, $key); 
					
					if ( is_array( $values[ $key ] ) ) {
					
						update_user_meta($user_id, $key, $values[ $key ] );	
					}
				} else {
					
					update_user_meta( $user_id, $key, $values[$key ] );
				}

				//update_post_meta( $this->listing_id, $key, $values[ $group_key ][ $key ] );	
				
			
				// // Handle attachments
				// if ( 'file' === $field['type'] ) {
				
				// 	//$attachment_id = is_numeric( $values[ $key ] ) ? $values[ $key ]  : $this->create_attachment( $values[ $key ] );
				// 	$attachment_id = $this->create_attachment( $values[ $key ] );
			
				// 	update_user_meta( $user_id, $key.'_id', $attachment_id  );
				
				// }
				
			endforeach;
			



	}

	public function listeo_login( $atts = array() ) {
		$template_loader = new Listeo_Core_Template_Loader;
		ob_start();
		$template_loader->get_template_part( 'account/login' ); 
		return ob_get_clean();
	}

	public function listeo_registration( $atts = array() ) {
		$template_loader = new Listeo_Core_Template_Loader;
		ob_start();
		$template_loader->get_template_part( 'account/registration' ); 
		return ob_get_clean();
	}

	public function my_account( $atts = array() ) {
		$template_loader = new Listeo_Core_Template_Loader;
		ob_start();
		if ( is_user_logged_in() ) : 
		$template_loader->get_template_part( 'my-account' ); 
		else :
		$template_loader->get_template_part( 'account/login-form' ); 
		endif;
		return ob_get_clean();
	}	


	public function change_password( $atts = array() ) {
		$template_loader = new Listeo_Core_Template_Loader;
		ob_start();
		$template_loader->set_template_data( array( 'current' => 'password' ) )->get_template_part( 'account/navigation' );
		$template_loader->get_template_part( 'account/change_password' ); 
		return ob_get_clean();
	}	

	public function lost_password( $atts = array() ) {
		$template_loader = new Listeo_Core_Template_Loader;
		$errors = array();
		if ( isset( $_REQUEST['errors'] ) ) {
			$error_codes = explode( ',', $_REQUEST['errors'] );
			foreach ( $error_codes as $error_code ) {
				$errors[]= $this->get_error_message( $error_code );
			}
		} 
		ob_start();
		$template_loader->set_template_data( array( 'errors' => $errors ) )->get_template_part( 'account/lost_password' ); 
		return ob_get_clean();
	}


	public function reset_password( $atts = array() ) {
		$template_loader = new Listeo_Core_Template_Loader;
		$attributes = array();
		if ( is_user_logged_in() ) {
			return '<div class="notification success closeable">
							<p>'. __( 'You are already signed in.', 'listeo_core' ).'</p>
					</div>';
			
		} else {
			if ( isset( $_REQUEST['login'] ) && isset( $_REQUEST['key'] ) ) {
				$attributes['login'] = $_REQUEST['login'];
				$attributes['key'] = $_REQUEST['key'];
				// Error messages
				$errors = array();
				if ( isset( $_REQUEST['error'] ) ) {
					$error_codes = explode( ',', $_REQUEST['error'] );
					foreach ( $error_codes as $code ) {
						$errors []= $this->get_error_message( $code );
					}
				}
				$attributes['errors'] = $errors;
				ob_start();
				$template_loader->set_template_data( array( 'attributes' => $attributes ) )->get_template_part( 'account/reset_password' ); 
				return ob_get_clean();
			} else if(isset( $_GET['password'] ) ) {
				return '<div class="notification success closeable">
							'. __( 'Password has been changed.', 'listeo_core' ).'
						</div>';
				
			} else if(isset( $_GET['checkemail'] ) ) {

				return '<div class="notification success closeable">'
							.__( 'A confirmation link has been sent to your email address.', 'listeo_core' ).'
						</div>';

			} else {
				return '<div class="notification error closeable">'
							.__( 'Invalid password reset link.', 'listeo_core' ).'
						</div>';
			}
		}
		
	}

	/**
	 * User dashboard
	 */
	public function listeo_dashboard( $atts ) {

		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to access your listings.', 'listeo_core' );
		}

		extract( shortcode_atts( array(
			//'posts_per_page' => '25',
		), $atts ) );

		ob_start();

		$template_loader = new Listeo_Core_Template_Loader;		
		$template_loader->set_template_data( 
			array( 
				'message' => $this->dashboard_message, 

			) )->get_template_part( 'account/dashboard' ); 


		return ob_get_clean();
	}	

	/**
	 * User listings shortcode
	 */
	public function listeo_core_my_listings( $atts ) {

		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to manage your listings.', 'listeo_core' );
		}


		$page = (isset($_GET['listings_paged'])) ? $_GET['listings_paged'] : 1;

		if(isset($_REQUEST['status']) && !empty($_REQUEST['status'])) {
			$status = $_REQUEST['status'];
		} else {
			$status = '';
		}
		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;

		$status = isset($_GET['status']) ? $_GET['status'] : '' ;
		$search = isset($_GET['search']) ? $_GET['search'] : '' ;
		$category = isset($_GET['category']) ? $_GET['category'] : '' ;

		$template_loader->set_template_data(
			array(
				'message' => $this->dashboard_message,
				'ids' => $this->get_agent_listings($status,$page,10,$search,$category),
				'status' => $status,
				'category' => $category,
			) )->get_template_part( 'account/my_listings' );


		return ob_get_clean();
	}	

	/**
	 * User listings shortcode
	 */
	public function listeo_core_my_packages( $atts ) {
		
		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to manage your packages.', 'listeo_core' );
		}

		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );

		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;

		$template_loader->set_template_data( array( 'current' => 'my_packages' ) )->get_template_part( 'account/navigation' ); 
		$template_loader->get_template_part( 'account/my_packages' ); 


		return ob_get_clean();
	}


	public function my_orders(){
		wc_get_template( 'myaccount/my-orders.php' );
	}


	/**
	 * Function to get ids added by the user/agent
	 * @return array array of listing ids
	 */
	public function get_agent_listings($status,$page,$per_page,$search = false, $category = false){
		$current_user = wp_get_current_user();

		switch ($status) {
			case 'pending':
				$post_status = array('pending_payment','draft','pending','preview');
				break;

			case 'active':
				$post_status = array('publish');
				break;

			case 'expired':
				$post_status = array('expired');
				break;
				
			case 'rejected':
				$post_status = array('rejected');
				break;

			default:
				$post_status = array('publish','pending_payment','expired','draft','pending');
				break;
		}

		$args = array(
			'author'        	=>  $current_user->ID,
		    'fields'          	=> 'ids', // Only get post IDs
		    'posts_per_page'  	=> $per_page,
		    'post_type'		  	=> 'listing',
		    'paged'				=> $page,
		    's'					=> $search,
		    'post_status'	  	=> $post_status,
		);

		// Add category filter if provided
		if ($category && $category !== '') {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'listing_category',
					'field'    => 'term_id',
					'terms'    => $category,
				),
			);
		}

		$q = new WP_Query($args);

		return $q;
	}

	/**
	 * Redirects the user to the correct page depending on whether he / she
	 * is an admin or not.
	 *
	 * @param string $redirect_to   An optional redirect_to URL for admin users
	 */
	private function redirect_logged_in_user( $redirect_to = null ) {
	    $user = wp_get_current_user();
	    if ( user_can( $user, 'manage_options' ) ) {
	        if ( $redirect_to ) {
	            wp_safe_redirect( $redirect_to );
	        } else {
	            wp_redirect( admin_url() );
	        }
	    } else {
			
			if($redirect_to) {
				wp_safe_redirect( $redirect_to );
			} else {
				wp_redirect(home_url(get_permalink(get_option('listeo_profile_page'))));
			}
	        
	    }
	}

	public function redirect_woocommerce( $redirect_to, $user ) {
	
		// $redirect = isset( $_GET['redirect_to'] ) ? $_GET['redirect_to'] : wp_get_raw_referer();
		//$redirect_to = get_permalink(get_option( 'listeo_profile_page' ));
	   	// $redirect_to = wp_get_raw_referer()() ? wp_get_raw_referer()() : home_url();
		// 
		$role = $user->roles[0];

		if($role == 'owner' || $role == 'seller') {
						                	
				$redirect_page_id = get_option('listeo_owner_login_redirect');
				if($redirect_page_id){
					$redirect_to = get_permalink($redirect_page_id);
				} else {
						$org_ref = wp_get_referer();
				        if($org_ref){
				        	$redirect_to = $org_ref;
				        } else {
				        	$redirect_to = get_permalink(get_option( 'listeo_profile_page' ));
				        }
				}
				
			} else if($role == 'guest') {

				$redirect_page_id = get_option('listeo_guest_login_redirect');
				if($redirect_page_id){
					$redirect_to = get_permalink($redirect_page_id);
				} else {
					  	$org_ref = wp_get_referer();
				        if($org_ref){
				        	$redirect_to = $org_ref;
				        } else {
				        	$redirect_to = get_permalink(get_option( 'listeo_profile_page' ));
				        }
				}

			} else {
				$org_ref = wp_get_referer();
		        if($org_ref){
		        	$redirect_to = $org_ref;
		        } else {
		        	$redirect_to = get_permalink(get_option( 'listeo_profile_page' ));
		        }
			}
    	return $redirect_to;
	}
	
	/**
	 * Redirect the user to the custom login page instead of wp-login.php.
	 */
	function redirect_to_custom_login() {
	    if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
	        $redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : null;
	     
	        if ( is_user_logged_in() ) {
	            $this->redirect_logged_in_user( $redirect_to );
	            exit;
	        }
	 
	        // The rest are redirected to the login page
	        $login_url = get_permalink(get_option( 'listeo_profile_page' ));
	        if ( ! empty( $redirect_to ) ) {
	            $login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
	        }
	 
	        wp_redirect( $login_url );
	        exit;
	    }
	}

	/**
	 * Redirects the user to the custom "Forgot your password?" page instead of
	 * wp-login.php?action=lostpassword.
	 */
	public function redirect_to_custom_lostpassword() {

	    if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
	        if ( is_user_logged_in() ) {
	            $this->redirect_logged_in_user();
	            exit;
	        }
	 
	 		$lost_password_page = get_option( 'listeo_lost_password_page' );
	 		if(!empty($lost_password_page)) {
	 			wp_redirect(get_permalink($lost_password_page ));	
	 		} else {
	 			esc_html_e("Please set a Lost Password Page in Listeo_Core Options -> Pages",'listeo_core');
	 		}
	        
	        exit;
	    }
	}

	/**
	 * Initiates password reset.
	 */
	public function do_password_lost() {
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			$errors = retrieve_password();
			if ( is_wp_error( $errors ) ) {
				// Errors found — redirect to the LOST password page (whose handler renders ?errors=...).
				// Previously redirected to the RESET password page, which only handles login+key / checkemail / password
				// and falls through to a generic "Invalid password reset link" message, masking the real error code.
				$redirect_url = get_permalink(get_option( 'listeo_lost_password_page' ));
				$redirect_url = add_query_arg( 'errors', join( ',', $errors->get_error_codes() ), $redirect_url );
			} else {
				// Email sent
				$redirect_url = get_permalink(get_option( 'listeo_reset_password_page' ));
				$redirect_url = add_query_arg( 'checkemail', 'confirm', $redirect_url );
				if ( ! empty( $_REQUEST['redirect_to'] ) ) {
					$redirect_url = $_REQUEST['redirect_to'];
				}
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Redirects to the custom password reset page, or the login page
	 * if there are errors.
	 */
	public function redirect_to_custom_password_reset() {
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
			// Verify key / login combo
			$user = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );
			if ( ! $user || is_wp_error( $user ) ) {
				if ( $user && $user->get_error_code() === 'expired_key' ) {
					wp_redirect( get_permalink(get_option( 'listeo_lost_password_page' )).'?login=expiredkey' );
				} else {
					wp_redirect( get_permalink(get_option( 'listeo_lost_password_page' )).'?login=invalidkey');
				}
				exit;
			}
			$redirect_url = get_permalink(get_option( 'listeo_reset_password_page' ));
			$redirect_url = add_query_arg( 'login', esc_attr( $_REQUEST['login'] ), $redirect_url );
			$redirect_url = add_query_arg( 'key', esc_attr( $_REQUEST['key'] ), $redirect_url );
			wp_redirect( $redirect_url );
			exit;
		}
	}


	/**
	 * Redirects the user to the custom registration page instead
	 * of wp-login.php?action=register.
	 */
	public function redirect_to_custom_register() {
	    if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
	        if ( is_user_logged_in() ) {
	            $this->redirect_logged_in_user();
	        } else {
	            wp_redirect( get_permalink(get_option( 'listeo_profile_page' )) );
	        }
	        exit;
	    }
	}

	/**
	 * Redirect the user after authentication if there were any errors.
	 *
	 * @param Wp_User|Wp_Error  $user       The signed in user, or the errors that have occurred during login.
	 * @param string            $username   The user name used to log in.
	 * @param string            $password   The password used to log in.
	 *
	 * @return Wp_User|Wp_Error The logged in user, or error information if there were errors.
	 */
	function maybe_redirect_at_authenticate( $user, $username, $password ) {
	    // Check if the earlier authenticate filter (most likely, 
	    // the default WordPress authentication) functions have found errors
	    
	    if( isset($_POST['action']) && $_POST['action'] == 'listeoajaxlogin')  {
			return $user;
	    }
	    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	        if ( is_wp_error( $user ) ) {
	            $error_codes = join( ',', $user->get_error_codes() );
	 
	            	$login_url = get_permalink(get_option( 'listeo_dashboard_page' ));
	            	$login_url = add_query_arg( 'login', $error_codes, $login_url );
	 
	            wp_redirect( $login_url );
	            exit;
	        }
	    }
	 
	    return $user;
	}


	/**
	 * Finds and returns a matching error message for the given error code.
	 *
	 * @param string $error_code    The error code to look up.
	 *
	 * @return string               An error message.
	 */
	private function get_error_message( $error_code ) {
	    switch ( $error_code ) {
	        case 'email_exists':
	            return __( 'This email is already registered', 'listeo_core' );
	  		break;
	  		case 'username_exists':
	            return __( 'This username already exists', 'listeo_core' );
	 		break;
	 		case 'empty_username':
	            return __( 'You do have an email address, right?', 'listeo_core' );
	 		break;
	        case 'empty_password':
	            return __( 'You need to enter a password to login.', 'listeo_core' );
	 		break;
	        case 'strong_password':
	            return __( 'You password is too weak.', 'listeo_core' );
	 		break;
	        case 'invalid_username':
	            return __(
	                "We don't have any users with that email address. Maybe you used a different one when signing up?", 'listeo_core' );
	 		break;
	        case 'incorrect_password':
	            $err = __(
	                "The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
	                'listeo_core'
	            );
	            return sprintf( $err, wp_lostpassword_url() );
	 		break;
	        default:
	            break;
	    }
	     
	    return __( 'An unknown error occurred. Please try again later.', 'listeo_core' );
	}


	/**
	 * Returns the URL to which the user should be redirected after the (successful) login.
	 *
	 * @param string           $redirect_to           The redirect destination URL.
	 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
	 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
	 *
	 * @return string Redirect URL
	 */
	public function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
	    $redirect_url = home_url();

	    if ( ! isset( $user->ID ) ) {
	        return $redirect_url;
	    }
	 
	    if ( user_can( $user, 'manage_options' ) ) {
	        // Use the redirect_to parameter if one is set, otherwise redirect to admin dashboard.
	        if ( $requested_redirect_to == '' ) {
	            $redirect_url = admin_url();
	        } else {
	            $redirect_url = $requested_redirect_to;
	        }
	    } else {
	        // Non-admin users: check for redirect_to parameter first
	        if ( ! empty( $requested_redirect_to ) ) {
	            // Only allow front-end redirects for non-admins (security measure)
	            if ( strpos( $requested_redirect_to, 'wp-admin' ) === false &&
	                 strpos( $requested_redirect_to, 'wp-login' ) === false ) {
	                $redirect_url = $requested_redirect_to;
	            }
	        }

	        // If no valid redirect_to, use role-based redirects
	        if ( empty( $redirect_url ) ) {
	            $user_data = get_userdata( $user->ID  );
	            $roles = $user_data->roles;

	            $role = array_shift( $roles );

	            if($role == 'owner' || $role == 'seller') {

	                $redirect_page_id = get_option('listeo_owner_login_redirect');
	                if($redirect_page_id){
	                    $redirect_url = get_permalink($redirect_page_id);
	                } else {
	                        $org_ref = wp_get_referer();
	                        if($org_ref){
	                            $redirect_url = $org_ref;
	                        } else {
	                            $redirect_url = get_permalink(get_option( 'listeo_profile_page' ));
	                        }
	                }

	            } else if($role == 'guest') {

	                $redirect_page_id = get_option('listeo_guest_login_redirect');
	                if($redirect_page_id){
	                    $redirect_url = get_permalink($redirect_page_id);
	                } else {
	                        $org_ref = wp_get_referer();
	                        if($org_ref){
	                            $redirect_url = $org_ref;
	                        } else {
	                            $redirect_url = get_permalink(get_option( 'listeo_profile_page' ));
	                        }
	                }

	            } else {
	                $org_ref = wp_get_referer();
	                if($org_ref){
	                    $redirect_url = $org_ref;
	                } else {
	                    $redirect_url = get_permalink(get_option( 'listeo_profile_page' ));
	                }
	            }
	        }
			
			$referer = wp_get_referer();
			// if $referer has '/my-profile/?redirect_to='
			if (strpos($referer, '?redirect_to=') !== false) {
				// remove from url query string '/my-profile/?redirect_to='
				// get the link to my profile page
				$my_profile_page_id = get_option('listeo_profile_page');
				// get slug of page with id $my_profile_page_id
				$my_profile_page_slug = get_post_field('post_name', $my_profile_page_id);
				// 
				$redirect_url = str_replace('/'.$my_profile_page_slug.'/?redirect_to=', '', $referer);

				if ($redirect_url) {
					$redirect_url = urldecode($redirect_url);
				}
			}
	
	    }
	 
	    return wp_validate_redirect( $redirect_url, home_url() );
	}

	/**
	 * Validates and then completes the new user signup process if all went well.
	 *
	 * @param string $email         The new user's email address
	 * @param string $first_name    The new user's first name
	 * @param string $last_name     The new user's last name
	 *
	 * @return int|WP_Error         The id of the user that was created, or error if failed.
	 */
	public function register_user( $email, $user_login, $first_name, $last_name, $role, $phone, $password, $custom_registration_fields ) {
	    $errors = new WP_Error();
	 
	    // Email address is used as both username and email. It is also the only
	    // parameter we need to validate
	    if ( ! is_email( $email ) ) {
	        $errors->add( 'email', $this->get_error_message( 'email' ) );
	        return $errors;
	    }
	 
	    if ( email_exists( $email ) ) {
	        $errors->add( 'email_exists', $this->get_error_message( 'email_exists') );
	        return $errors;
	    }

	    if ( username_exists( $user_login ) ) {
	        $errors->add( 'username_exists', $this->get_error_message( 'username_exists') );
	        return $errors;
	    }
		$password_generated = false;
	    // Generate the password so that the subscriber will have to check email...
	    if(!$password) {  
		    $password = wp_generate_password( 12, true );
			$password_generated = true;

		}
		if (get_option('listeo_strong_password') && !$password_generated) {
			$uppercase = preg_match('@[A-Z]@', $password);
			$lowercase = preg_match('@[a-z]@', $password);
			$number    = preg_match('@[0-9]@', $password);
			$specialChars = preg_match('@[^\w]@', $password);

			if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {

				$errors->add('strong_password', $this->get_error_message('strong_password'));
				return $errors;
			}
		}

		if (!in_array($role, array('owner', 'guest', 'seller'))) {
			$role = get_option('default_role');
		}

	    $user_data = array(
	        'user_login'    => $user_login,
	        'user_email'    => $email,
	        'user_pass'     => $password,
	        'first_name'    => $first_name,
	        'last_name'     => $last_name,
	        'nickname'      => $first_name,
	        'role'			=> $role
	    );
	 
	    $user_id = wp_insert_user( $user_data );
	    
		if($phone) {
			update_user_meta( $user_id, 'phone', $phone );
			update_user_meta( $user_id, 'full_phone', $phone );
		}
		foreach ( $custom_registration_fields as $key => $field ) : 
			
				if( $field['type'] == 'select_multiple' || $field['type'] == 'multicheck_split') {
					
					if ( is_array( $field[ 'value' ] ) ) {
					
							update_user_meta( $user_id, $field['name'], $field[ 'value' ] );	
					}
				} else {
					if( $field['type'] == 'checkbox' ){

						if(!empty($field['value'])) {
							update_user_meta( $user_id, $field['name'], $field[ 'value' ] );	
						}

					} else {

						update_user_meta( $user_id, $field['name'], $field[ 'value' ] );	
					
					}
					
				}

				//update_post_meta( $this->listing_id, $key, $values[ $group_key ][ $key ] );	
				
			
				// // Handle attachments
				if ( 'file' === $field['type'] ) {
				
					//$attachment_id = is_numeric( $values[ $key ] ) ? $values[ $key ]  : $this->create_attachment( $values[ $key ] );
					$attachment_id = $this->create_attachment( $field[ 'value' ] );
			
					update_user_meta(  $user_id,$field['name'], $attachment_id  );
				
				}
				
			endforeach;
			


	    if ( ! is_wp_error( $user_id ) ) {
			wp_new_user_notification( $user_id, $password,'both' );
			if(get_option('listeo_autologin')){
				wp_set_current_user($user_id); // set the current wp user
	    		wp_set_auth_cookie($user_id); 	
			}
			
		}
	    
	 
	    return $user_id;
	}


	/**
	 * Handles the registration of a new user.
	 *
	 * Used through the action hook "login_form_register" activated on wp-login.php
	 * when accessed through the registration action.
	 */
	public function do_register_user() {

	    if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
	        $redirect_url = get_permalink(get_option( 'listeo_profile_page' )).'#tab2';
	 		
	        if ( ! get_option( 'users_can_register' ) ) {
	            // Registration closed, display error
	            $redirect_url = add_query_arg( 'register-errors', 'closed', $redirect_url );
	        } else {
	            $email = $_POST['email'];
	            $first_name = (isset($_POST['first_name'])) ? sanitize_text_field( $_POST['first_name'] ) : '' ;
	            $last_name = (isset($_POST['last_name'])) ? sanitize_text_field( $_POST['last_name'] ) : '' ;
	            // get/create username

	

				if (get_option('listeo_registration_hide_username')) {
					if (get_option('listeo_registration_hide_username_use_email')) {
						$user_login = sanitize_user(trim($email));
					} else {
						$email_arr = explode('@', $email);
						$user_login = sanitize_user(trim($email_arr[0]), true);
					}
				} else {

					$user_login = sanitize_user(trim($_POST['username']));
				}
		        // check if email is from gmail, otherwise show error
				
					// $email_arr = explode('@', $email);
					// if($email_arr[1] != 'gmail.com') {
					// 	$redirect_url = add_query_arg( 'register-errors', 'gmail-only', $redirect_url );
					// 	wp_redirect( $redirect_url );
					// 	exit;
					// }
				


	            $role =  (isset($_POST['user_role'])) ? sanitize_text_field( $_POST['user_role'] ) : get_option('default_role');
		        //$role = sanitize_text_field($_POST['role']);
		        if (!in_array($role, array('owner', 'guest','seller'))) {
					$role = get_option('default_role');
				}

	            $password = (!empty($_POST['password'])) ? sanitize_text_field( $_POST['password'] ) : false ;
	            if($role=='owner' || $role == 'seller') {
					$fields = get_option('listeo_owner_registration_form');
	            } else {
	            	$fields = get_option('listeo_guest_registration_form');
	            }
	            	
	           	 $custom_registration_fields = array();
		            if(!empty($fields)){
		            	//get fields for registration

		            	foreach ($fields as $key => $field) {
							
		            	
		            		$field_type = str_replace( '-', '_', $field['type'] );
				
							if ( $handler = apply_filters( "listeo_core_get_posted_{$field_type}_field", false ) ) {
								
								$value = call_user_func( $handler, $key, $field );
							} elseif ( method_exists( $this, "get_posted_{$field_type}_field" ) ) {
								
								$value = call_user_func( array( $this, "get_posted_{$field_type}_field" ), $key, $field );
							} else {
								
								$value = $this->get_posted_field( $key, $field );
							}
					


		            		if(isset($field['required']) && !empty($field['required'])) {
		            		
		            			if(!$value){
		            				$redirect_url = add_query_arg( 'register-errors', 'required-field', $redirect_url );
		            				wp_redirect( $redirect_url );
	        						exit;
		            			} else {
		            				$field['value'] = $value;
		            				$custom_registration_fields[] = $field;
		            			}

		            		} else {
		            			
		            			$field['value'] = $value;
		            			
		            			$custom_registration_fields[] = $field;
		            		}
						

		            	}
		            }
				
	            $recaptcha_status = get_option('listeo_recaptcha');
	             
		        $recaptcha_version = get_option('listeo_recaptcha_version');

	            
	             if(get_option('listeo_display_password_field')) {
	            	if(!$password) {
	            		
	            		$redirect_url = add_query_arg( 'register-errors', 'password-no', $redirect_url );
	            		wp_redirect( $redirect_url );
	        			exit;
	            	}
	            }

            	// get custom field
        		switch ($role) {
	              	case 'seller':
	              	case 'owner':
	              		$fields = get_option('listeo_owner_registration_form');
	              		break;
	              	case 'guest':
	              		$fields = get_option('listeo_guest_registration_form');
	              		break;              	
	            }


	            $recaptcha_pass = true;
	            if($recaptcha_status) {
	            	$recaptcha_pass = false;

	            	if($recaptcha_version=="v2" && isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])):
						$secret = get_option('listeo_recaptcha_secretkey');
				        //get verify response data
				
				        $verifyResponse = wp_remote_get('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);
				        $responseData = json_decode($verifyResponse['body']);
						if( $responseData->success ):
							//passed captcha, proceed to register
				            $recaptcha_pass = true;
			        	else:
			        		$redirect_url = add_query_arg( 'register-errors', 'captcha-fail', $redirect_url );
		        		endif;
		        	else:
		        		$redirect_url = add_query_arg( 'register-errors', 'captcha-no', $redirect_url );
	        		endif;



					if($recaptcha_version=="v3" && isset($_POST['token']) && !empty($_POST['token'])):
				        //your site secret key
				        $secret = get_option('listeo_recaptcha_secretkey3');
				        //get verify response data
				        $verifyResponse = wp_remote_get('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['token']);
						$responseData_w = wp_remote_retrieve_body( $verifyResponse );
				        $responseData = json_decode($responseData_w);

						if($responseData->success == '1' && $responseData->action == 'login' && $responseData->score >= 0.5) :
							//passed captcha, proceed to register
				             $recaptcha_pass = true;
			        	else:
			        		$redirect_url = add_query_arg( 'register-errors', 'captcha-fail', $redirect_url );
		        		endif;
		        	else:
		        		$redirect_url = add_query_arg( 'register-errors', 'captcha-fail', $redirect_url );
	        		endif;

					if($recaptcha_version=="hcaptcha" ){
						if (!isset($_POST['h-captcha-response']) || empty($_POST['h-captcha-response'])) {
							$redirect_url = add_query_arg('register-errors', 'captcha-fail', $redirect_url);
						} else {
							$hcaptcha_response = $_POST['h-captcha-response'];
							if (!$this->verify_hcaptcha($hcaptcha_response)) {
								$recaptcha_pass = true;
							}
						}
					}

					if($recaptcha_version=="turnstile" ){
						if (!isset($_POST['cf-turnstile-response']) || empty($_POST['cf-turnstile-response'])) {
							$redirect_url = add_query_arg('register-errors', 'captcha-fail', $redirect_url);
						} else {
							$turnstile_response = $_POST['cf-turnstile-response'];
							if ($this->verify_turnstile($turnstile_response)) {
								$recaptcha_pass = true;
							}
						}
					}


	        		if($recaptcha_pass == false){
	        			wp_redirect( $redirect_url );
	        		}
	            }

	            $privacy_policy_status = get_option('listeo_privacy_policy');
	            $privacy_policy_pass = true;
	            if($privacy_policy_status) {
	            	$privacy_policy_pass = false;
	            	if(isset($_POST['privacy_policy']) && !empty($_POST['privacy_policy'])):
	            		$privacy_policy_pass = true;
	            	else :
	            		$redirect_url = add_query_arg( 'register-errors', 'policy-fail', $redirect_url );
	            	endif;
	            }

	    	
	    		$terms_and_conditions_status =  get_option('listeo_terms_and_conditions_req');
	            $terms_and_conditions_pass = true;
	            if($terms_and_conditions_status) {
	            	$terms_and_conditions_pass = false;
	            	if(isset($_POST['terms_and_conditions']) && !empty($_POST['terms_and_conditions'])):
	            		$terms_and_conditions_pass = true;
	            	else :
	            		$redirect_url = add_query_arg( 'register-errors', 'terms-fail', $redirect_url );
	            	endif;
	            }
				$otp_pass = true;
				$phone = false;
				if(get_option('listeo_otp_status') || get_option('listeo_email_otp_verification')) {
					//verify otp
					
					$email = sanitize_text_field(trim($_POST['email']));
					$sms_otp = get_option('listeo_otp_status');
					$email_otp = get_option('listeo_email_otp_verification');
					if ($sms_otp || $email_otp) {
						$otp = true;
						if ($sms_otp) {
							$otp_type = 'sms';
						} else {
							$otp_type = 'email';
						}
					} else {
						$otp = false;
						$otp_type = false;
					}
					//verify otp
					$phone = sanitize_text_field(trim($_POST['full_phone']));
					

					if ($otp_type == 'sms') {
						$transient_key = 'otp_' . $phone;
					} else {
						$transient_key = 'otp_' . $email;
					}
					
					$orignal_token = get_transient($transient_key);
					if (isset($_POST['listeo_otp']['token'])) {
						$token = $_POST['listeo_otp']['token'];

						$letter_1 = $token['code_1'];
						$letter_2 = $token['code_2'];
						$letter_3 = $token['code_3'];
						$letter_4 = $token['code_4'];
						// glue all the letters togheter
						$token = $letter_1 . $letter_2 . $letter_3 . $letter_4;
						// get user phone
						
						// check if the token is correct
					
						if($orignal_token != $token){
							$otp_pass = false;
							$redirect_url = add_query_arg( 'register-errors', 'otp-fail', $redirect_url );
							wp_redirect( $redirect_url );
							exit;
						}
					} else {
						$otp_pass = false;
						$redirect_url = add_query_arg( 'register-errors', 'otp-fail', $redirect_url );
						wp_redirect( $redirect_url );
						exit;

					}
					//remove the transient
					delete_transient($transient_key);
				}

	            if($recaptcha_pass && $privacy_policy_pass && $terms_and_conditions_pass && $otp_pass){
					
		            $result = $this->register_user( $email, $user_login, $first_name, $last_name, $role, $phone, $password, $custom_registration_fields );
						 
		            if ( is_wp_error( $result ) ) {
		                // Parse errors into a string and append as parameter to redirect
		                $errors = join( ',', $result->get_error_codes() );
		                $redirect_url = add_query_arg( 'register-errors', $errors, $redirect_url );
		            } else {
		                // Success, redirect to login page.
		                
		                if($role == 'owner' || $role=='seller') {
			                	
			                	$redirect_page_id = get_option('listeo_owner_registration_redirect');
			                	
			                	if($redirect_page_id){
								
									$redirect_url = get_permalink($redirect_page_id);
			                	
			                	} else {
			                	
			                		$redirect_url = get_permalink(get_option( 'listeo_profile_page' ));
			                	
			                	}
			                	
			                } else if($role == 'guest') {

			                	$redirect_page_id = get_option('listeo_guest_registration_redirect');
			                	if($redirect_page_id){
									$redirect_url = get_permalink($redirect_page_id);
			                	} else {
			                		$redirect_url = get_permalink(get_option( 'listeo_profile_page' ));
			                	}

			                } else {
			                	$redirect_url = get_permalink(get_option( 'listeo_profile_page' ));	
			                }
		                $redirect_url = add_query_arg( 'registered', $email, $redirect_url );
		            }
					$referer = wp_get_referer();
					// if $referer has '/my-profile/?redirect_to='
					if (strpos($referer, '?redirect_to=') !== false) {
						// remove from url query string '/my-profile/?redirect_to='
						// get the link to my profile page
						$my_profile_page_id = get_option('listeo_profile_page');
						// get slug of page with id $my_profile_page_id
						$my_profile_page_slug = get_post_field('post_name', $my_profile_page_id);
						// 
						$redirect_url = str_replace('/' . $my_profile_page_slug . '/?redirect_to=', '', $referer);

						if ($redirect_url) {
							$redirect_url = urldecode($redirect_url);
						}
					}

				}
	 		}
	        wp_redirect( $redirect_url );
	        exit;
	    }
	}


	// generate one time password for user that I will send via sms
	
	/**
	 * Resets the user's password if the password reset form was submitted.
	 */
	public function do_password_reset() {
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			$rp_key = $_REQUEST['rp_key'];
			$rp_login = $_REQUEST['rp_login'];
			$user = check_password_reset_key( $rp_key, $rp_login );
			if ( ! $user || is_wp_error( $user ) ) {
				if ( $user && $user->get_error_code() === 'expired_key' ) {
					wp_redirect( home_url( 'member-login?login=expiredkey' ) );
				} else {
					wp_redirect( home_url( 'member-login?login=invalidkey' ) );
				}
				exit;
			}
			if ( isset( $_POST['pass1'] ) ) {
				if ( $_POST['pass1'] != $_POST['pass2'] ) {
					// Passwords don't match
					$redirect_url = get_permalink(get_option( 'listeo_reset_password_page' ));
					$redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
					$redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
					$redirect_url = add_query_arg( 'error', 'password_reset_mismatch', $redirect_url );
					wp_redirect( $redirect_url );
					exit;
				}
				if ( empty( $_POST['pass1'] ) ) {
					// Password is empty
					$redirect_url = get_permalink(get_option( 'listeo_reset_password_page' ));
					$redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
					$redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
					$redirect_url = add_query_arg( 'error', 'password_reset_empty', $redirect_url );
					wp_redirect( $redirect_url );
					exit;
				}
				// Parameter checks OK, reset password
				reset_password( $user, $_POST['pass1'] );
				$redirect_url = get_permalink(get_option( 'listeo_reset_password_page' ));
				$redirect_url = add_query_arg( 'password', 'changed', $redirect_url );
				wp_redirect(  $redirect_url );
			} else {
				echo "Invalid request.";
			}
			exit;
		}
	}

	function remove_filter_lostpassword() {
	  remove_filter( 'lostpassword_url', 'wc_lostpassword_url', 10 );
	}

	/**
	 * Clean up avatar usermeta when an attachment is deleted externally.
	 *
	 * @param int $attachment_id The attachment being deleted.
	 */
	public function cleanup_avatar_on_attachment_delete( $attachment_id ) {
		global $wpdb;
		$wpdb->delete(
			$wpdb->usermeta,
			array(
				'meta_key'   => 'listeo_core_avatar_id',
				'meta_value' => $attachment_id,
			)
		);
	}

}


/* TODO: move it to the class*/
function listeo_core_avatar_filter() {

  // Add to edit_user_avatar hook
  add_action('edit_user_avatar', array('wp_user_avatar', 'wpua_action_show_user_profile'));
  add_action('edit_user_avatar', array('wp_user_avatar', 'wpua_media_upload_scripts'));
}

// Loads only outside of administration panel
if(!is_admin()) {
  add_action('init','listeo_core_avatar_filter');
}

// Redefine user notification function
if ( !function_exists('wp_new_user_notification') ) {
    function wp_new_user_notification( $user_id, $plaintext_pass = '', $notify = '' ) {
        $user = new WP_User($user_id);

        $user_login = stripslashes($user->user_login);
        $user_email = stripslashes($user->user_email);
		$user_data = get_userdata( $user_id );
		$user_roles = is_array( $user_data->roles ) ? $user_data->roles : array();

		$user_role = '';
		if ( ! empty( $user_roles ) ) {
			$wp_roles_obj = wp_roles();
			$labels = array();
			foreach ( $user_roles as $role_key ) {
				if ( isset( $wp_roles_obj->roles[ $role_key ]['name'] ) ) {
					$labels[] = translate_user_role( $wp_roles_obj->roles[ $role_key ]['name'] );
				} else {
					$labels[] = $role_key;
				}
			}
			$user_role = implode( ', ', $labels );
		}

        if ( 'user' !== $notify ) {
            $message  = sprintf(__('New user registration on your site %s:','listeo_core'), get_option('blogname')) . "\r\n\r\n";
            $message .= sprintf(__('Username: %s','listeo_core'), $user_login) . "\r\n\r\n";
            $message .= sprintf(__('E-mail: %s','listeo_core'), $user_email) . "\r\n";
            $message .= sprintf(__('Role: %s','listeo_core'), $user_role) . "\r\n";

            @wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Registration','listeo_core'), get_option('blogname')), $message);
        }

        if ( 'admin' === $notify || empty($plaintext_pass) )
            return;


		if( function_exists('listeo_core_get_option') && get_option('listeo_profile_page',true) ) {
		 	$login_url = get_permalink( listeo_core_get_option( 'listeo_profile_page' ) );
		} else {
		 	$login_url = wp_login_url();
		}

 		$user = get_user_by( 'id', $user_id );
 		$mail_args = array(
	        'email'         => $user_email,
	        'login'         => $user_login,
	        'password'      => $plaintext_pass,
	        'first_name' 	=> $user->first_name,
	        'last_name' 	=> $user->last_name,
	        'display_name' 	=> $user->display_name,
	        'login_url' 	=> $login_url,
	        );
	    do_action('listeo_welcome_mail',$mail_args);

       // wp_mail($user_email, sprintf(__('[%s] Your username and password','listeo_core'), get_option('blogname')), $message);

    }

}
