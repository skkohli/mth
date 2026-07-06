<?php
/**
 * Listeo Zoom Integration (OAuth 2.0)
 *
 * Handles Zoom meeting creation for service-type bookings using OAuth 2.0 Authorization Code Flow
 *
 * @package Listeo Core
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Listeo_Zoom_Integration class
 */
class Listeo_Zoom_Integration {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into paid booking status (after WooCommerce payment completion)
		add_action( 'listeo_mail_to_user_paid', array( $this, 'create_zoom_meeting_on_payment' ), 5 );

		// Hook into booking cancellation
		add_action( 'listeo_booking_cancelled', array( $this, 'delete_zoom_meeting_on_cancel' ), 10, 2 );

		// Add Zoom enable checkbox to listing submission form
		//add_filter( 'submit_listing_form_fields', array( $this, 'add_zoom_checkbox_field' ), 20, 2 );

		// Add Zoom settings to admin panel
		add_filter( 'listeo_settings_fields', array( $this, 'add_zoom_settings' ) );

		// OAuth callback handler - register rewrite rule and handle callback
		add_action( 'init', array( $this, 'register_oauth_rewrite_rule' ) );
		add_action( 'init', array( $this, 'handle_oauth_callback' ) );
		add_filter( 'query_vars', array( $this, 'add_oauth_query_vars' ) );

		// AJAX handlers for connect/disconnect
		add_action( 'wp_ajax_listeo_zoom_disconnect', array( $this, 'ajax_disconnect_zoom' ) );
	}

	/**
	 * Register rewrite rule for OAuth callback endpoint
	 */
	public function register_oauth_rewrite_rule() {
		add_rewrite_rule( '^zoom-oauth-callback/?$', 'index.php?listeo_zoom_oauth=1', 'top' );
	}

	/**
	 * Add query vars for OAuth callback
	 *
	 * @param array $vars Existing query vars
	 * @return array Modified query vars
	 */
	public function add_oauth_query_vars( $vars ) {
		$vars[] = 'listeo_zoom_oauth';
		return $vars;
	}

	/**
	 * Add Zoom settings to admin panel
	 *
	 * @param array $settings Existing settings
	 * @return array Modified settings
	 */
	public function add_zoom_settings( $settings ) {
		$redirect_uri = home_url( '/zoom-oauth-callback/' );

		$settings['zoom'] = array(
			'title'       => __( 'Zoom Integration', 'listeo_core' ),
			//'description' => __( 'Configure Zoom OAuth 2.0 integration for automatic meeting creation', 'listeo_core' ),
			'fields'      => array(
				// Block Title (creates lc-settings-block wrapper)
				array(
					'id'          => 'zoom_oauth_settings_block',
					'label'       => __( '<i class="fa fa-video-camera"></i> Zoom OAuth Configuration', 'listeo_core' ),
					'description' => sprintf(
						/* translators: %1$s: Zoom Marketplace URL, %2$s: Redirect URI */
						__( 'Create an OAuth app at %1$s and enter your credentials below. Users will authorize the app to create meetings on their behalf.<br><br><strong>Important:</strong> Add this redirect URI to your Zoom app settings:<br><code style="background: #f5f5f5; padding: 5px 10px; display: inline-block; margin-top: 5px;">%2$s</code><br><br><strong>Required Zoom Scopes:</strong> <code>user:read:user</code>, <code>meeting:write:meeting</code>, <code>meeting:read:meeting</code>', 'listeo_core' ),
						'<a href="https://marketplace.zoom.us/develop/create" target="_blank">Zoom Marketplace</a>',
						esc_url( $redirect_uri )
					),
					'type'        => 'title',
				),
				array(
					'id'          => 'zoom_oauth_client_id',
					'label'       => __( 'Zoom OAuth Client ID', 'listeo_core' ),
					'description' => __( 'Enter your Zoom OAuth app Client ID from the Zoom Marketplace.', 'listeo_core' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( 'Enter Client ID', 'listeo_core' ),
				),
				array(
					'id'          => 'zoom_oauth_client_secret',
					'label'       => __( 'Zoom OAuth Client Secret', 'listeo_core' ),
					'description' => __( 'Enter your Zoom OAuth app Client Secret. This will be stored securely.', 'listeo_core' ),
					'type'        => 'password',
					'default'     => '',
					'placeholder' => __( 'Enter Client Secret', 'listeo_core' ),
				),
			),
		);

		return $settings;
	}

	/**
	 * Add Zoom booking enabled checkbox to listing form
	 *
	 * @param array  $fields       Form fields
	 * @param string $listing_type Listing type slug
	 * @return array Modified fields
	 */
	public function add_zoom_checkbox_field( $fields, $listing_type = '' ) {
		// Only add for service-type listings or if no specific type
		$show_zoom_field = false;

		// Check if this is a service type listing
		if ( ! empty( $listing_type ) ) {
			$booking_type = listeo_get_booking_type( $listing_type );
			if ( $booking_type === 'single_day' || $booking_type === 'service' ) {
				$show_zoom_field = true;
			}
		} else {
			// If no listing type specified, show for all (user can choose)
			$show_zoom_field = true;
		}

		if ( $show_zoom_field && isset( $fields['booking'] ) && isset( $fields['booking']['fields'] ) ) {
			// Add after instant booking field
			$fields['booking']['fields']['_zoom_booking_enabled'] = array(
				'label'       => __( 'Enable Zoom meetings for bookings', 'listeo_core' ),
				'type'        => 'checkbox',
				'priority'    => 6,
				'default'     => '',
				'placeholder' => '',
				'description' => __( 'Automatically create Zoom meetings when bookings are confirmed. You must connect your Zoom account in your profile first.', 'listeo_core' ),
			);
		}

		return $fields;
	}

	/**
	 * Get Zoom authorization URL for user to connect their account
	 *
	 * @param int $user_id User ID
	 * @return string|false Authorization URL or false if credentials not set
	 */
	public function get_authorization_url( $user_id ) {
		$client_id = get_option( 'listeo_zoom_oauth_client_id' );

		if ( empty( $client_id ) ) {
			return false;
		}

		$redirect_uri = home_url( '/zoom-oauth-callback/' );
		$state        = wp_create_nonce( 'zoom_oauth_' . $user_id ) . '|' . $user_id;

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
			'scope'         => 'user:read:user meeting:write:meeting meeting:read:meeting',
		);

		return 'https://zoom.us/oauth/authorize?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Handle OAuth callback from Zoom
	 */
	public function handle_oauth_callback() {
		// Check if this is the OAuth callback via registered query var
		if ( ! get_query_var( 'listeo_zoom_oauth' ) ) {
			return;
		}

		$this->debug_log( 'OAuth callback received' );

		// Check for error
		if ( isset( $_GET['error'] ) ) {
			$error_description = isset( $_GET['error_description'] ) ? sanitize_text_field( $_GET['error_description'] ) : __( 'Unknown error', 'listeo_core' );

			$this->debug_log( 'OAuth callback error', array(
				'error'             => sanitize_text_field( $_GET['error'] ),
				'error_description' => $error_description,
			) );

			wp_die(
				esc_html( sprintf(
					/* translators: %s: error description */
					__( 'Zoom authorization failed: %s', 'listeo_core' ),
					$error_description
				) ),
				__( 'Zoom Authorization Error', 'listeo_core' )
			);
		}

		// Verify we have required parameters
		if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
			$this->debug_log( 'OAuth callback missing required parameters' );
			wp_die( esc_html__( 'Invalid OAuth callback parameters', 'listeo_core' ) );
		}

		$code  = sanitize_text_field( $_GET['code'] );
		$state = sanitize_text_field( $_GET['state'] );

		// Parse state to get user ID and verify nonce
		$state_parts = explode( '|', $state );
		if ( count( $state_parts ) !== 2 ) {
			$this->debug_log( 'OAuth callback invalid state parameter' );
			wp_die( esc_html__( 'Invalid state parameter', 'listeo_core' ) );
		}

		list( $nonce, $user_id ) = $state_parts;
		$user_id = (int) $user_id;

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, 'zoom_oauth_' . $user_id ) ) {
			$this->debug_log( 'OAuth callback nonce verification failed', array( 'user_id' => $user_id ) );
			wp_die( esc_html__( 'Security verification failed', 'listeo_core' ) );
		}

		$this->debug_log( 'OAuth callback validated, exchanging code for tokens', array( 'user_id' => $user_id ) );

		// Exchange code for tokens
		$tokens = $this->exchange_code_for_tokens( $code );

		if ( ! $tokens ) {
			$this->debug_log( 'Failed to exchange authorization code for tokens', array( 'user_id' => $user_id ) );
			wp_die( esc_html__( 'Failed to exchange authorization code for tokens', 'listeo_core' ) );
		}

		// Store tokens for user
		$expires_at = time() + $tokens['expires_in'];
		update_user_meta( $user_id, 'zoom_access_token', $this->encrypt_token( $tokens['access_token'] ) );
		update_user_meta( $user_id, 'zoom_refresh_token', $this->encrypt_token( $tokens['refresh_token'] ) );
		update_user_meta( $user_id, 'zoom_token_expires_at', $expires_at );
		update_user_meta( $user_id, 'zoom_connected', true );

		$this->debug_log( 'Tokens stored successfully', array(
			'user_id'    => $user_id,
			'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
		) );

		// Get Zoom user info for display
		$zoom_user = $this->get_zoom_user_info( $tokens['access_token'] );
		if ( $zoom_user && isset( $zoom_user['id'] ) ) {
			update_user_meta( $user_id, 'zoom_user_id', $zoom_user['id'] );
			update_user_meta( $user_id, 'zoom_user_email', isset( $zoom_user['email'] ) ? $zoom_user['email'] : '' );

			$this->debug_log( 'Zoom user info retrieved', array(
				'user_id'    => $user_id,
				'zoom_email' => isset( $zoom_user['email'] ) ? $zoom_user['email'] : '',
			) );
		}

		// Redirect to profile with success message
		$profile_url = get_option( 'listeo_profile_page' );
		if ( $profile_url ) {
			wp_safe_redirect( add_query_arg( 'zoom_connected', '1', get_permalink( $profile_url ) ) );
		} else {
			wp_safe_redirect( admin_url( 'profile.php?zoom_connected=1' ) );
		}
		exit;
	}

	/**
	 * Exchange authorization code for access and refresh tokens
	 *
	 * @param string $code Authorization code
	 * @return array|false Token data or false on failure
	 */
	private function exchange_code_for_tokens( $code ) {
		$client_id     = get_option( 'listeo_zoom_oauth_client_id' );
		$client_secret = get_option( 'listeo_zoom_oauth_client_secret' );
		$redirect_uri  = home_url( '/zoom-oauth-callback/' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$this->debug_log( 'Missing OAuth credentials for token exchange' );
			return false;
		}

		$this->debug_log( 'Exchanging authorization code for tokens', array(
			'redirect_uri' => $redirect_uri,
		) );

		$response = wp_remote_post(
			'https://zoom.us/oauth/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'   => 'authorization_code',
					'code'         => $code,
					'redirect_uri' => $redirect_uri,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'OAuth token exchange HTTP error', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) && isset( $body['refresh_token'] ) ) {
			$this->debug_log( 'Token exchange successful', array(
				'expires_in' => isset( $body['expires_in'] ) ? $body['expires_in'] : 'N/A',
			) );
			return $body;
		}

		$this->debug_log( 'Failed to exchange code for tokens', array(
			'response_code' => $response_code,
			'response_body' => $body,
		) );
		return false;
	}

	/**
	 * Get Zoom user info
	 *
	 * @param string $access_token Access token
	 * @return array|false User data or false on failure
	 */
	private function get_zoom_user_info( $access_token ) {
		$response = wp_remote_get(
			'https://api.zoom.us/v2/users/me',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['id'] ) ? $body : false;
	}

	/**
	 * Refresh access token using refresh token
	 *
	 * @param int $user_id User ID
	 * @return bool True on success, false on failure
	 */
	private function refresh_access_token( $user_id ) {
		$this->debug_log( 'Attempting to refresh access token', array( 'user_id' => $user_id ) );

		$refresh_token = $this->decrypt_token( get_user_meta( $user_id, 'zoom_refresh_token', true ) );

		if ( empty( $refresh_token ) ) {
			$this->debug_log( 'No refresh token found', array( 'user_id' => $user_id ) );
			return false;
		}

		$client_id     = get_option( 'listeo_zoom_oauth_client_id' );
		$client_secret = get_option( 'listeo_zoom_oauth_client_secret' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$this->debug_log( 'Missing Zoom OAuth credentials', array( 'user_id' => $user_id ) );
			return false;
		}

		$response = wp_remote_post(
			'https://zoom.us/oauth/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'Token refresh HTTP error', array(
				'user_id' => $user_id,
				'error'   => $response->get_error_message(),
			) );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) && isset( $body['refresh_token'] ) ) {
			$expires_at = time() + $body['expires_in'];
			update_user_meta( $user_id, 'zoom_access_token', $this->encrypt_token( $body['access_token'] ) );
			update_user_meta( $user_id, 'zoom_refresh_token', $this->encrypt_token( $body['refresh_token'] ) );
			update_user_meta( $user_id, 'zoom_token_expires_at', $expires_at );

			$this->debug_log( 'Token refreshed successfully', array(
				'user_id'    => $user_id,
				'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
			) );
			return true;
		}

		$this->debug_log( 'Token refresh failed', array(
			'user_id'       => $user_id,
			'response_code' => $response_code,
			'response_body' => $body,
		) );
		return false;
	}

	/**
	 * Get valid access token for user (refreshes if needed)
	 *
	 * @param int $user_id User ID
	 * @return string|false Access token or false if not available
	 */
	private function get_user_access_token( $user_id ) {
		$access_token = $this->decrypt_token( get_user_meta( $user_id, 'zoom_access_token', true ) );
		$expires_at   = (int) get_user_meta( $user_id, 'zoom_token_expires_at', true );

		if ( empty( $access_token ) ) {
			return false;
		}

		// Check if token is expired or will expire in next 5 minutes
		if ( $expires_at < ( time() + 300 ) ) {
			// Try to refresh
			if ( $this->refresh_access_token( $user_id ) ) {
				$access_token = $this->decrypt_token( get_user_meta( $user_id, 'zoom_access_token', true ) );
			} else {
				return false;
			}
		}

		return $access_token;
	}

	/**
	 * Simple token encryption (uses WordPress salts)
	 *
	 * @param string $token Token to encrypt
	 * @return string Encrypted token
	 */
	private function encrypt_token( $token ) {
		if ( empty( $token ) ) {
			return '';
		}
		// Simple XOR encryption with WordPress salts
		$key = wp_salt( 'auth' );
		return base64_encode( $token ^ substr( str_repeat( $key, ceil( strlen( $token ) / strlen( $key ) ) ), 0, strlen( $token ) ) );
	}

	/**
	 * Simple token decryption
	 *
	 * @param string $encrypted_token Encrypted token
	 * @return string Decrypted token
	 */
	private function decrypt_token( $encrypted_token ) {
		if ( empty( $encrypted_token ) ) {
			return '';
		}
		$key   = wp_salt( 'auth' );
		$token = base64_decode( $encrypted_token );
		return $token ^ substr( str_repeat( $key, ceil( strlen( $token ) / strlen( $key ) ) ), 0, strlen( $token ) );
	}

	/**
	 * Disconnect Zoom account for user
	 *
	 * @param int $user_id User ID
	 */
	public function disconnect_zoom( $user_id ) {
		delete_user_meta( $user_id, 'zoom_access_token' );
		delete_user_meta( $user_id, 'zoom_refresh_token' );
		delete_user_meta( $user_id, 'zoom_token_expires_at' );
		delete_user_meta( $user_id, 'zoom_user_id' );
		delete_user_meta( $user_id, 'zoom_user_email' );
		delete_user_meta( $user_id, 'zoom_connected' );
	}

	/**
	 * AJAX handler for disconnecting Zoom
	 */
	public function ajax_disconnect_zoom() {
		check_ajax_referer( 'listeo-zoom-disconnect', 'security' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'listeo_core' ) ) );
		}

		$this->disconnect_zoom( $user_id );

		wp_send_json_success( array( 'message' => __( 'Zoom account disconnected successfully', 'listeo_core' ) ) );
	}

	/**
	 * Check if user has Zoom connected
	 *
	 * @param int $user_id User ID
	 * @return bool
	 */
	public function is_zoom_connected( $user_id ) {
		return (bool) get_user_meta( $user_id, 'zoom_connected', true );
	}

	/**
	 * Log message if WP_DEBUG is enabled
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 */
	private function debug_log( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_message = 'Zoom Integration: ' . $message;
		if ( ! empty( $context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message );
	}

	/**
	 * Create Zoom meeting when booking is paid
	 *
	 * @param array $args Email arguments containing booking data
	 */
	public function create_zoom_meeting_on_payment( $args ) {
		if ( ! isset( $args['booking'] ) ) {
			$this->debug_log( 'No booking data in args' );
			return;
		}

		$booking    = $args['booking'];
		$listing_id = $booking['listing_id'];
		$booking_id = $booking['ID'];

		$this->debug_log( 'Processing Zoom meeting creation on payment', array(
			'booking_id' => $booking_id,
			'listing_id' => $listing_id,
		) );

		// Check if this is service/single_day type
		$booking_type = listeo_get_booking_type( $listing_id );
		if ( $booking_type !== 'single_day' && $booking_type !== 'service' ) {
			$this->debug_log( 'Skipping non-service booking', array(
				'booking_type' => $booking_type,
				'listing_id'   => $listing_id,
			) );
			return; // Not a service booking
		}

		// Check if Zoom is enabled for this listing
		if ( ! get_post_meta( $listing_id, '_zoom_booking_enabled', true ) ) {
			$this->debug_log( 'Zoom not enabled for listing', array( 'listing_id' => $listing_id ) );
			return; // Zoom not enabled
		}

		// Get owner ID and check if Zoom is connected
		$owner_id = $booking['owner_id'];
		if ( ! $this->is_zoom_connected( $owner_id ) ) {
			$this->debug_log( 'Owner has not connected Zoom account', array( 'owner_id' => $owner_id ) );
			return;
		}

		// Get valid access token
		$access_token = $this->get_user_access_token( $owner_id );
		if ( ! $access_token ) {
			$this->debug_log( 'Failed to get access token', array( 'owner_id' => $owner_id ) );
			return;
		}

		// Get booking details
		$comment    = json_decode( $booking['comment'], true );
		$date_start = $booking['date_start'];
		$date_end   = $booking['date_end'];

		// Get customer details
		$client_email = isset( $comment['email'] ) ? $comment['email'] : '';
		$first_name   = isset( $comment['first_name'] ) ? $comment['first_name'] : '';
		$last_name    = isset( $comment['last_name'] ) ? $comment['last_name'] : '';
		$client_name  = trim( $first_name . ' ' . $last_name );

		if ( empty( $client_email ) ) {
			$this->debug_log( 'No client email found', array( 'booking_id' => $booking_id ) );
			return;
		}

		// Get listing title
		$listing_title = get_the_title( $listing_id );

		// Create meeting topic
		$meeting_topic = sprintf(
			/* translators: %1$s: listing title, %2$s: booking ID */
			__( '%1$s - Booking #%2$s', 'listeo_core' ),
			$listing_title,
			$booking_id
		);

		$this->debug_log( 'Creating Zoom meeting', array(
			'topic'        => $meeting_topic,
			'client_email' => $client_email,
			'date_start'   => $date_start,
			'date_end'     => $date_end,
		) );

		// Create Zoom meeting
		$zoom_meeting = $this->create_zoom_api_meeting(
			$access_token,
			$date_start,
			$date_end,
			$meeting_topic,
			$client_email,
			$client_name
		);

		if ( $zoom_meeting && isset( $zoom_meeting['id'] ) && isset( $zoom_meeting['join_url'] ) ) {
			// Store meeting details with booking
			add_booking_meta( $booking_id, 'zoom_meeting_id', $zoom_meeting['id'] );
			add_booking_meta( $booking_id, 'zoom_join_url', $zoom_meeting['join_url'] );
			add_booking_meta( $booking_id, 'zoom_start_url', $zoom_meeting['start_url'] );
			add_booking_meta( $booking_id, 'zoom_password', isset( $zoom_meeting['password'] ) ? $zoom_meeting['password'] : '' );
			add_booking_meta( $booking_id, 'zoom_created_at', current_time( 'mysql' ) );

			$this->debug_log( 'Zoom meeting created successfully', array(
				'zoom_meeting_id' => $zoom_meeting['id'],
				'booking_id'      => $booking_id,
				'join_url'        => $zoom_meeting['join_url'],
			) );

			// Send Zoom invitation email
			$this->send_zoom_invitation_email( $booking, $zoom_meeting );
		} else {
			$this->debug_log( 'Failed to create Zoom meeting', array( 'booking_id' => $booking_id ) );
			add_booking_meta( $booking_id, 'zoom_error', __( 'Zoom meeting creation failed. The listing owner may need to reconnect their Zoom account.', 'listeo_core' ) );
		}
	}

	/**
	 * Delete Zoom meeting when booking is cancelled
	 *
	 * @param int   $booking_id   Booking ID
	 * @param array $booking_data Booking data
	 */
	public function delete_zoom_meeting_on_cancel( $booking_id, $booking_data = array() ) {
		// Get Zoom meeting ID from booking meta
		$zoom_meeting_id = get_booking_meta( $booking_id, 'zoom_meeting_id', true );

		if ( empty( $zoom_meeting_id ) ) {
			$this->debug_log( 'No Zoom meeting associated with booking', array( 'booking_id' => $booking_id ) );
			return; // No Zoom meeting associated
		}

		$this->debug_log( 'Processing Zoom meeting deletion on booking cancellation', array(
			'booking_id'      => $booking_id,
			'zoom_meeting_id' => $zoom_meeting_id,
		) );

		// Get owner ID (from booking data or database)
		if ( isset( $booking_data['owner_id'] ) ) {
			$owner_id = $booking_data['owner_id'];
		} else {
			global $wpdb;
			$owner_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT owner_id FROM {$wpdb->prefix}bookings_calendar WHERE ID = %d",
				$booking_id
			) );
		}

		if ( ! $owner_id ) {
			$this->debug_log( 'Cannot delete meeting - owner ID not found', array( 'booking_id' => $booking_id ) );
			return;
		}

		// Get valid access token
		$access_token = $this->get_user_access_token( $owner_id );
		if ( ! $access_token ) {
			$this->debug_log( 'Failed to get access token for deleting meeting', array(
				'owner_id'        => $owner_id,
				'zoom_meeting_id' => $zoom_meeting_id,
			) );
			return;
		}

		// Delete meeting via API
		$deleted = $this->delete_zoom_api_meeting( $access_token, $zoom_meeting_id );

		if ( $deleted ) {
			// Clear Zoom metadata
			delete_booking_meta( $booking_id, 'zoom_meeting_id' );
			delete_booking_meta( $booking_id, 'zoom_join_url' );
			delete_booking_meta( $booking_id, 'zoom_start_url' );
			delete_booking_meta( $booking_id, 'zoom_password' );
			delete_booking_meta( $booking_id, 'zoom_created_at' );

			$this->debug_log( 'Zoom meeting deleted successfully', array(
				'zoom_meeting_id' => $zoom_meeting_id,
				'booking_id'      => $booking_id,
			) );
		}
	}

	/**
	 * Create Zoom meeting via API
	 *
	 * @param string $access_token  OAuth access token
	 * @param string $start_time    Booking start time
	 * @param string $end_time      Booking end time
	 * @param string $topic         Meeting topic
	 * @param string $attendee_email Attendee email address
	 * @param string $attendee_name  Attendee name
	 * @return array|false Meeting data or false on failure
	 */
	private function create_zoom_api_meeting( $access_token, $start_time, $end_time, $topic, $attendee_email, $attendee_name ) {
		// Calculate duration in minutes
		$start    = new DateTime( $start_time, wp_timezone() );
		$end      = new DateTime( $end_time, wp_timezone() );
		$duration = ( $end->getTimestamp() - $start->getTimestamp() ) / 60;

		// Prepare meeting data
		$meeting_data = array(
			'topic'      => $topic,
			'type'       => 2, // Scheduled meeting
			'start_time' => $start->format( 'Y-m-d\TH:i:s' ),
			'duration'   => (int) $duration,
			'timezone'   => wp_timezone_string(),
			'settings'   => array(
				'host_video'        => true,
				'participant_video' => true,
				'join_before_host'  => false,
				'mute_upon_entry'   => true,
				'approval_type'     => 2, // No registration required
				'waiting_room'      => false,
				'auto_recording'    => 'none',
			),
		);

		$this->debug_log( 'Calling Zoom API to create meeting', array(
			'topic'      => $topic,
			'start_time' => $start->format( 'Y-m-d\TH:i:s' ),
			'duration'   => $duration . ' minutes',
			'timezone'   => wp_timezone_string(),
		) );

		// Create meeting via Zoom API
		$response = wp_remote_post(
			'https://api.zoom.us/v2/users/me/meetings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $meeting_data ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'Zoom API HTTP error', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code === 201 && isset( $body['id'] ) ) {
			$this->debug_log( 'Zoom meeting created via API', array(
				'meeting_id' => $body['id'],
				'join_url'   => isset( $body['join_url'] ) ? $body['join_url'] : 'N/A',
			) );
			return $body;
		}

		$error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
		$this->debug_log( 'Zoom API meeting creation failed', array(
			'response_code' => $response_code,
			'error_message' => $error_message,
			'response_body' => $body,
		) );
		return false;
	}

	/**
	 * Delete Zoom meeting via API
	 *
	 * @param string $access_token OAuth access token
	 * @param string $meeting_id   Zoom meeting ID
	 * @return bool True on success, false on failure
	 */
	private function delete_zoom_api_meeting( $access_token, $meeting_id ) {
		$this->debug_log( 'Calling Zoom API to delete meeting', array( 'meeting_id' => $meeting_id ) );

		$response = wp_remote_request(
			'https://api.zoom.us/v2/meetings/' . $meeting_id,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'Zoom API HTTP error (delete meeting)', array(
				'meeting_id' => $meeting_id,
				'error'      => $response->get_error_message(),
			) );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// 204 = success, 404 = already deleted
		if ( $response_code === 204 || $response_code === 404 ) {
			$this->debug_log( 'Zoom meeting deleted via API', array(
				'meeting_id'    => $meeting_id,
				'response_code' => $response_code,
			) );
			return true;
		}

		$this->debug_log( 'Zoom API meeting deletion failed', array(
			'meeting_id'    => $meeting_id,
			'response_code' => $response_code,
		) );
		return false;
	}

	/**
	 * Send Zoom invitation email to customer
	 *
	 * @param array $booking      Booking data
	 * @param array $zoom_meeting Zoom meeting data
	 */
	private function send_zoom_invitation_email( $booking, $zoom_meeting ) {
		$comment      = json_decode( $booking['comment'], true );
		$client_email = isset( $comment['email'] ) ? $comment['email'] : '';

		if ( empty( $client_email ) ) {
			$this->debug_log( 'Cannot send Zoom invitation - no client email', array(
				'booking_id' => $booking['ID'],
			) );
			return;
		}

		$mail_args = array(
			'email'        => $client_email,
			'booking'      => $booking,
			'zoom_meeting' => $zoom_meeting,
		);

		$this->debug_log( 'Triggering Zoom invitation email', array(
			'booking_id'      => $booking['ID'],
			'client_email'    => $client_email,
			'zoom_meeting_id' => $zoom_meeting['id'],
			'join_url'        => $zoom_meeting['join_url'],
			'action'          => 'listeo_mail_zoom_meeting_invitation',
			'hooks_attached'  => has_action( 'listeo_mail_zoom_meeting_invitation' ),
		) );

		do_action( 'listeo_mail_zoom_meeting_invitation', $mail_args );

		$this->debug_log( 'Zoom invitation email action triggered', array(
			'booking_id' => $booking['ID'],
			'note'       => 'Email sending depends on email handler being attached to listeo_mail_zoom_meeting_invitation hook',
		) );
	}
}

// Initialize the class
new Listeo_Zoom_Integration();
