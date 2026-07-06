<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listeo_Core_Environment_Sync {

	const OPTION_NAME = 'listeo_core_remote_state';
	const CRON_HOOK = 'listeo_core_environment_sync';
	const LOCK_TRANSIENT = 'listeo_core_environment_sync_lock';
	const RATE_TRANSIENT = 'listeo_core_environment_sync_rate';
	const PRODUCT_ID = '2';
	const REMOTE_TOKEN = 'A9E7638C-CBACD8B0-3E565346-2A3D607A';
	const ENDPOINT = 'https://purethe.me/wp-json/licensor/license/view';
	const FALLBACK_ENDPOINT = 'https://www.vasterad.com/listeo-license-bg-proxy.php';
	const MAX_REMOTE_ATTEMPTS_PER_DAY = 3;
	const NETWORK_RETRY_INTERVAL = 28800;

	private static $initialized = false;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_sync' ), 10, 1 );
		add_action( 'admin_init', array( __CLASS__, 'block_settings_save' ), 0 );
		add_action( 'admin_init', array( __CLASS__, 'block_admin_request' ), 1 );
		add_action( 'current_screen', array( __CLASS__, 'block_current_screen' ), 0 );
		add_action( 'updated_option', array( __CLASS__, 'maybe_clear_state_on_option_change' ), 10, 3 );
		add_action( 'added_option', array( __CLASS__, 'maybe_clear_state_on_option_add' ), 10, 2 );
		add_action( 'deleted_option', array( __CLASS__, 'maybe_clear_state_on_option_delete' ), 10, 1 );

		self::maybe_schedule();
	}

	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'every_week', self::CRON_HOOK );
		}
	}

	public static function run_scheduled_sync( $source = 'scheduled' ) {
		if ( self::is_staging_environment() ) {
			return self::staging_state();
		}
		self::refresh();
	}

	public static function revalidate_now() {
		if ( self::is_staging_environment() ) {
			return self::staging_state();
		}

		$force_lock_key = self::RATE_TRANSIENT . '_force_' . self::current_signature();
		if ( self::get_timed_option( $force_lock_key ) ) {
			return self::get_state();
		}

		if ( ! self::set_timed_option( $force_lock_key, 1, MINUTE_IN_SECONDS ) && ! self::get_timed_option( $force_lock_key ) ) {
			return self::get_state();
		}

		self::delete_timed_option( self::LOCK_TRANSIENT );

		return self::refresh( true );
	}

	public static function is_available() {
		if ( self::is_staging_environment() ) {
			return true;
		}

		$state = self::get_state();

		return self::state_allows_access( $state );
	}

	private static function is_staging_environment() {
		if ( function_exists( 'listeo_is_staging_environment' ) ) {
			return (bool) listeo_is_staging_environment();
		}

		return false;
	}

	private static function staging_state() {
		$now = time();
		$state = self::build_state( 'valid', 'staging', array(
			'checked_at'    => $now,
			'next_check_at' => $now + 7 * DAY_IN_SECONDS,
			'last_valid_at' => $now,
			'message'       => 'Staging environment - remote validation bypassed',
		) );
		self::save_state( $state );

		return $state;
	}

	public static function get_state() {
		$state = get_option( self::OPTION_NAME, array() );

		return is_array( $state ) ? $state : array();
	}

	public static function block_settings_save() {
		if ( self::is_ajax_request() ) {
			return;
		}

		if ( empty( $_POST['option_page'] ) || 'listeo_settings' !== sanitize_key( wp_unslash( $_POST['option_page'] ) ) ) {
			return;
		}

		if ( self::is_available() ) {
			return;
		}

		wp_die(
			esc_html__( 'Settings are temporarily unavailable. Please activate your Listeo license to continue.', 'listeo_core' ),
			esc_html__( 'Listeo Core', 'listeo_core' ),
			array( 'response' => 403 )
		);
	}

	public static function block_admin_request() {
		if ( self::is_ajax_request() ) {
			return;
		}

		if ( self::is_allowed_admin_request() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

		if ( self::is_core_admin_page( $page, $pagenow, $action ) && ! self::is_available() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=listeo_license&tab=license' ) );
			exit;
		}
	}

	public static function block_current_screen( $screen ) {
		if ( empty( $screen ) || self::is_allowed_admin_request() ) {
			return;
		}

		$is_listing_screen = ! empty( $screen->post_type ) && 'listing' === $screen->post_type;
		$is_listing_taxonomy = ! empty( $screen->taxonomy ) && self::is_listing_taxonomy( $screen->taxonomy );

		if ( ( $is_listing_screen || $is_listing_taxonomy ) && ! self::is_available() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=listeo_license&tab=license' ) );
			exit;
		}
	}

	public static function maybe_clear_state_on_option_change( $option, $old_value, $value ) {
		if ( self::is_tracked_option( $option ) && $old_value !== $value ) {
			self::clear_state();
		}
	}

	public static function maybe_clear_state_on_option_add( $option, $value ) {
		if ( self::is_tracked_option( $option ) ) {
			self::clear_state();
		}
	}

	public static function maybe_clear_state_on_option_delete( $option ) {
		if ( self::is_tracked_option( $option ) ) {
			self::clear_state();
		}
	}

	private static function refresh( $force = false ) {
		$previous_state = self::get_state();

		if ( ! $force && self::get_timed_option( self::LOCK_TRANSIENT ) ) {
			return $previous_state;
		}

		if ( ! self::set_timed_option( self::LOCK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS ) && ! self::get_timed_option( self::LOCK_TRANSIENT ) ) {
			return $previous_state;
		}

		$credentials = self::get_credentials();
		$now = time();

		if ( empty( $credentials['key'] ) ) {
			$state = self::build_state( 'locked', 'missing_key', array(
				'checked_at' => $now,
				'next_check_at' => $now + 7 * DAY_IN_SECONDS,
			) );
			self::save_state( $state );
			self::delete_timed_option( self::LOCK_TRANSIENT );
			return $state;
		}

		if ( self::is_placeholder_key( $credentials['key'] ) ) {
			$state = self::build_state( 'locked', 'placeholder', array(
				'checked_at' => $now,
				'next_check_at' => $now + 7 * DAY_IN_SECONDS,
			) );
			self::save_state( $state );
			self::delete_timed_option( self::LOCK_TRANSIENT );
			return $state;
		}

		if ( ! $force ) {
			$expired_state = self::build_expired_network_state( $previous_state );
			if ( $expired_state ) {
				self::save_state( $expired_state );
				self::delete_timed_option( self::LOCK_TRANSIENT );
				return $expired_state;
			}
		}

		if ( ! $force && ! self::consume_daily_remote_attempt() ) {
			$state = self::build_rate_limited_state( $previous_state );
			if ( ! empty( $state ) ) {
				self::save_state( $state );
				self::schedule_retry( $state );
			}
			self::delete_timed_option( self::LOCK_TRANSIENT );
			return $state ? $state : $previous_state;
		}

		$result = self::request_remote_state( $credentials );

		if ( 'valid' === $result['type'] ) {
			$state = self::build_state( 'valid', 'remote', array(
				'checked_at' => $now,
				'next_check_at' => $now + 7 * DAY_IN_SECONDS,
				'last_valid_at' => $now,
				'message' => $result['message'],
			) );
			self::save_state( $state );
			self::delete_timed_option( self::LOCK_TRANSIENT );
			return $state;
		}

		if ( 'network' === $result['type'] ) {
			$state = self::build_network_state( $previous_state, $result['message'] );
			self::save_state( $state );
			self::schedule_retry( $state );
			self::delete_timed_option( self::LOCK_TRANSIENT );
			return $state;
		}

		$state = self::build_state( 'locked', $result['reason'], array(
			'checked_at' => $now,
			'next_check_at' => $now + 7 * DAY_IN_SECONDS,
			'last_error' => $result['message'],
		) );
		self::save_state( $state );
		self::delete_timed_option( self::LOCK_TRANSIENT );
		return $state;
	}

	private static function build_network_state( $previous_state, $message ) {
		$now = time();
		$first_failed_at = ! empty( $previous_state['first_failed_at'] ) && self::state_matches_current_credentials( $previous_state )
			? absint( $previous_state['first_failed_at'] )
			: $now;
		$grace_until = $first_failed_at + DAY_IN_SECONDS;
		$last_valid_at = ! empty( $previous_state['last_valid_at'] ) && self::state_matches_current_credentials( $previous_state )
			? absint( $previous_state['last_valid_at'] )
			: 0;

		$status = ( $grace_until > $now ) ? 'grace' : 'locked';

			return self::build_state( $status, 'network_failure', array(
				'checked_at' => $now,
				'next_check_at' => $now + 7 * DAY_IN_SECONDS,
				'first_failed_at' => $first_failed_at,
				'grace_until' => $grace_until,
				'retry_after' => 'grace' === $status ? min( $now + self::NETWORK_RETRY_INTERVAL, $grace_until ) : 0,
				'last_valid_at' => $last_valid_at,
				'last_error' => $message,
			) );
		}

		private static function build_expired_network_state( $previous_state ) {
			if ( empty( $previous_state['reason'] ) || 'network_failure' !== $previous_state['reason'] || ! self::state_matches_current_credentials( $previous_state ) ) {
				return null;
			}

			$now = time();
			$grace_until = ! empty( $previous_state['grace_until'] ) ? absint( $previous_state['grace_until'] ) : 0;
			if ( ! $grace_until || $grace_until > $now ) {
				return null;
			}

			return self::build_state( 'locked', 'network_failure', array(
				'checked_at' => $now,
				'next_check_at' => $now + 7 * DAY_IN_SECONDS,
				'first_failed_at' => ! empty( $previous_state['first_failed_at'] ) ? absint( $previous_state['first_failed_at'] ) : $now - DAY_IN_SECONDS,
				'grace_until' => $grace_until,
				'retry_after' => 0,
				'last_valid_at' => ! empty( $previous_state['last_valid_at'] ) ? absint( $previous_state['last_valid_at'] ) : 0,
				'last_error' => ! empty( $previous_state['last_error'] ) ? $previous_state['last_error'] : 'Remote validation retry window expired',
			) );
		}

		private static function build_rate_limited_state( $previous_state ) {
			if ( empty( $previous_state ) || ! self::state_matches_current_credentials( $previous_state ) ) {
				return $previous_state;
			}

			$now = time();
			$retry_after = self::get_rate_limit_retry_after();
			$state = $previous_state;
			$state['checked_at'] = $now;
			$state['retry_after'] = $retry_after;
			$state['next_check_at'] = $retry_after ? $retry_after : $now + DAY_IN_SECONDS;

			if ( ! empty( $state['reason'] ) && 'network_failure' === $state['reason'] && ! empty( $state['grace_until'] ) ) {
				$state['retry_after'] = min( $state['retry_after'], absint( $state['grace_until'] ) );
				$state['next_check_at'] = $state['retry_after'];
			}

			if ( empty( $state['last_error'] ) ) {
				$state['last_error'] = 'Remote validation rate limit reached';
			}

			return $state;
		}

	private static function request_remote_state( $credentials ) {
		$token = self::get_remote_token();

		if ( '' === $token ) {
			return self::result( 'network', 'network_failure', 'Remote validation key is not configured' );
		}

		$body = array(
			'api_key' => $token,
			'license_code' => $credentials['key'],
		);
		$result = self::request_direct_state( $body, $credentials['key'] );

		if ( empty( $result['try_fallback'] ) ) {
			return $result['result'];
		}

		return self::request_fallback_state( $body, $credentials['key'], $result['result']['message'] );
	}

	private static function request_direct_state( $body, $license_key ) {
		$response = wp_remote_post( self::ENDPOINT, array(
			'method' => 'POST',
			'timeout' => 30,
			'redirection' => 3,
			'sslverify' => false,
			'body' => $body,
			'cookies' => array(),
		) );

		if ( is_wp_error( $response ) ) {
			return self::remote_attempt( self::result( 'network', 'network_failure', $response->get_error_message() ), true );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return self::remote_attempt( self::result( 'network', 'network_failure', 'HTTP ' . (int) $code ), self::should_try_fallback_for_http_code( $code ) );
		}

		$response_body = wp_remote_retrieve_body( $response );
		if ( '' === trim( $response_body ) ) {
			return self::remote_attempt( self::result( 'network', 'network_failure', 'Empty response' ), true );
		}

		$decoded = self::decode_remote_body( $response_body );
		if ( ! is_object( $decoded ) ) {
			return self::remote_attempt( self::result( 'network', 'network_failure', 'Malformed response' ), true );
		}

		return self::remote_attempt( self::parse_remote_response( $decoded, $license_key ), false );
	}

	private static function request_fallback_state( $body, $license_key, $direct_error = '' ) {
		$last_error = $direct_error ? $direct_error : 'Direct request failed';

		foreach ( self::get_fallback_endpoints() as $fallback_endpoint ) {
			$response = wp_remote_post( $fallback_endpoint, array(
				'method' => 'POST',
				'timeout' => 20,
				'redirection' => 3,
				'sslverify' => false,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-License-Proxy' => 'Listeo-Core',
				),
				'body' => wp_json_encode( array(
					'target_url' => self::ENDPOINT,
					'method' => 'POST',
					'body' => $body,
					'product_id' => self::PRODUCT_ID,
					'domain' => site_url(),
				) ),
				'cookies' => array(),
			) );

			if ( is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== (int) $code ) {
				$last_error = 'Fallback HTTP ' . (int) $code;
				continue;
			}

			$decoded = self::decode_remote_body( wp_remote_retrieve_body( $response ) );
			if ( ! is_object( $decoded ) ) {
				$last_error = 'Malformed fallback response';
				continue;
			}

			update_option( 'listeo_core_remote_fallback', array(
				'time' => current_time( 'mysql' ),
				'proxy' => $fallback_endpoint,
				'success' => true,
			), false );

			return self::parse_remote_response( $decoded, $license_key );
		}

		update_option( 'listeo_core_remote_fallback', array(
			'time' => current_time( 'mysql' ),
			'proxy' => 'all_failed',
			'success' => false,
			'message' => $last_error,
		), false );

		return self::result( 'network', 'network_failure', $last_error );
	}

	private static function remote_attempt( $result, $try_fallback ) {
		return array(
			'result' => $result,
			'try_fallback' => (bool) $try_fallback,
		);
	}

	private static function should_try_fallback_for_http_code( $code ) {
		$code = (int) $code;

		return 401 === $code || 403 === $code || 408 === $code || 429 === $code || $code >= 500;
	}

	private static function decode_remote_body( $body ) {
		$decoded = json_decode( $body );
		if ( is_object( $decoded ) && ! empty( $decoded->body ) && is_string( $decoded->body ) ) {
			$proxied = json_decode( $decoded->body );
			if ( is_object( $proxied ) ) {
				return $proxied;
			}
		}

		if ( is_object( $decoded ) && ! empty( $decoded->licensor_response ) && is_object( $decoded->licensor_response ) ) {
			return $decoded->licensor_response;
		}

		return $decoded;
	}

	private static function get_fallback_endpoints() {
		return array_filter( array_map( 'trim', (array) apply_filters(
			'listeo_core_environment_sync_fallback_endpoints',
			array( self::FALLBACK_ENDPOINT )
		) ) );
	}

	private static function get_remote_token() {
		$token = defined( 'LISTEO_CORE_ENVIRONMENT_TOKEN' ) ? LISTEO_CORE_ENVIRONMENT_TOKEN : self::REMOTE_TOKEN;
		$token = apply_filters( 'listeo_core_environment_sync_token', $token );

		return trim( (string) $token );
	}

	private static function parse_remote_response( $decoded, $license_key ) {
		$message = self::read_response_message( $decoded );
		$status = property_exists( $decoded, 'status' ) ? $decoded->status : null;

		if ( ! self::is_truthy_response_value( $status ) ) {
			if ( self::is_remote_auth_error( $message ) ) {
				return self::result( 'network', 'network_failure', $message );
			}

			return self::result( 'invalid', 'invalid', $message ? $message : 'License was not found' );
		}

		if ( ! property_exists( $decoded, 'data' ) || empty( $decoded->data ) ) {
			return self::result( 'invalid', 'malformed', 'Missing response data' );
		}

		$license = self::normalize_license_payload( $decoded->data );
		if ( empty( $license ) ) {
			return self::result( 'invalid', 'malformed', 'Missing license data' );
		}

		if ( ! self::license_payload_matches_key( $license, $license_key ) ) {
			return self::result( 'invalid', 'invalid', 'License data mismatch' );
		}

		if ( ! self::license_payload_matches_product( $license ) ) {
			return self::result( 'invalid', 'invalid', 'License product mismatch' );
		}

		return self::result( 'valid', 'valid', $message ? $message : 'OK' );
	}

	private static function read_response_message( $decoded ) {
		foreach ( array( 'msg', 'message', 'error' ) as $field ) {
			if ( property_exists( $decoded, $field ) && is_scalar( $decoded->{$field} ) ) {
				return trim( (string) $decoded->{$field} );
			}
		}

		return '';
	}

	private static function normalize_license_payload( $data ) {
		if ( is_object( $data ) ) {
			return $data;
		}

		if ( is_array( $data ) ) {
			if ( self::is_list_array( $data ) ) {
				return ! empty( $data[0] ) && is_object( $data[0] ) ? $data[0] : null;
			}

			return (object) $data;
		}

		return null;
	}

	private static function is_truthy_response_value( $value ) {
		if ( true === $value || 1 === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'success', 'ok', 'valid' ), true );
		}

		return false;
	}

	private static function is_remote_auth_error( $message ) {
		$message = strtolower( $message );

		return false !== strpos( $message, 'api key' )
			|| false !== strpos( $message, 'permission' )
			|| false !== strpos( $message, 'unauthorized' )
			|| false !== strpos( $message, 'forbidden' );
	}

	private static function license_payload_matches_key( $license, $license_key ) {
		foreach ( array( 'purchase_key', 'license_key', 'license_code', 'lic_key', 'key' ) as $field ) {
			if ( property_exists( $license, $field ) && is_scalar( $license->{$field} ) ) {
				return trim( (string) $license->{$field} ) === $license_key;
			}
		}

		return true;
	}

	private static function license_payload_matches_product( $license ) {
		foreach ( array( 'product_id', 'product' ) as $field ) {
			if ( property_exists( $license, $field ) && is_scalar( $license->{$field} ) ) {
				return (string) self::PRODUCT_ID === (string) $license->{$field};
			}
		}

		return true;
	}

	private static function is_list_array( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function result( $type, $reason, $message ) {
		return array(
			'type' => $type,
			'reason' => $reason,
			'message' => is_string( $message ) ? $message : '',
		);
	}

	private static function build_state( $status, $reason, $args = array() ) {
		$defaults = array(
			'status' => $status,
			'reason' => $reason,
			'checked_at' => time(),
			'next_check_at' => time() + 7 * DAY_IN_SECONDS,
			'license_hash' => self::current_signature(),
			'first_failed_at' => 0,
			'grace_until' => 0,
			'retry_after' => 0,
			'last_valid_at' => 0,
			'last_error' => '',
			'message' => '',
		);

		return array_merge( $defaults, $args );
	}

	private static function save_state( $state ) {
		update_option( self::OPTION_NAME, $state, false );
	}

	private static function clear_state() {
		delete_option( self::OPTION_NAME );
		self::delete_timed_option( self::LOCK_TRANSIENT );
		self::delete_timed_option( self::get_rate_transient_key() );
		self::schedule_retry( array( 'retry_after' => time() + MINUTE_IN_SECONDS, 'grace_until' => time() + DAY_IN_SECONDS ) );
	}

		private static function schedule_retry( $state ) {
			if ( empty( $state['retry_after'] ) || ( ! empty( $state['grace_until'] ) && $state['retry_after'] > $state['grace_until'] ) ) {
				return;
			}

			$retry_after = absint( $state['retry_after'] );
			$existing = wp_next_scheduled( self::CRON_HOOK, array( 'retry' ) );

			if ( $existing && $existing <= $retry_after ) {
				return;
			}

			if ( $existing ) {
				wp_unschedule_event( $existing, self::CRON_HOOK, array( 'retry' ) );
			}

			wp_schedule_single_event( $retry_after, self::CRON_HOOK, array( 'retry' ) );
		}

	private static function state_allows_access( $state ) {
		if ( empty( $state ) ) {
			return true;
		}

		if ( ! self::state_matches_current_credentials( $state ) ) {
			return true;
		}

		if ( ! empty( $state['status'] ) && 'valid' === $state['status'] ) {
			return true;
		}

		if ( ! empty( $state['status'] ) && 'grace' === $state['status'] && ! empty( $state['grace_until'] ) && $state['grace_until'] > time() ) {
			return true;
		}

		return false;
	}

	private static function state_matches_current_credentials( $state ) {
		return is_array( $state ) && ! empty( $state['license_hash'] ) && self::current_signature() === $state['license_hash'];
	}

	private static function current_signature() {
		$credentials = self::get_credentials();

		return md5( $credentials['key'] . '|' . $credentials['email'] . '|' . site_url() );
	}

	private static function get_rate_transient_key() {
		return self::RATE_TRANSIENT . '_' . self::current_signature();
	}

	private static function get_timed_option( $key ) {
		$data = get_option( $key, false );

		if ( false === $data ) {
			return false;
		}

		if ( ! is_array( $data ) || empty( $data['expires_at'] ) ) {
			delete_option( $key );
			return false;
		}

		if ( absint( $data['expires_at'] ) <= time() ) {
			delete_option( $key );
			return false;
		}

		return array_key_exists( 'value', $data ) ? $data['value'] : true;
	}

	private static function set_timed_option( $key, $value, $ttl ) {
		return update_option( $key, array(
			'value' => $value,
			'expires_at' => time() + max( 1, absint( $ttl ) ),
		), false );
	}

	private static function delete_timed_option( $key ) {
		delete_option( $key );
		delete_transient( $key );
	}

	private static function consume_daily_remote_attempt() {
		$key = self::get_rate_transient_key();
		$state = self::get_timed_option( $key );
		$now = time();

		if ( ! is_array( $state ) || empty( $state['window_started_at'] ) || empty( $state['attempts'] ) || $state['window_started_at'] + DAY_IN_SECONDS <= $now ) {
			return self::set_timed_option( $key, array(
				'window_started_at' => $now,
				'attempts' => 1,
			), DAY_IN_SECONDS );
		}

		if ( absint( $state['attempts'] ) >= self::MAX_REMOTE_ATTEMPTS_PER_DAY ) {
			return false;
		}

		$state['attempts'] = absint( $state['attempts'] ) + 1;
		return self::set_timed_option( $key, $state, max( 1, $state['window_started_at'] + DAY_IN_SECONDS - $now ) );
	}

	private static function get_rate_limit_retry_after() {
		$state = self::get_timed_option( self::get_rate_transient_key() );
		if ( ! is_array( $state ) || empty( $state['window_started_at'] ) ) {
			return time() + DAY_IN_SECONDS;
		}

		return absint( $state['window_started_at'] ) + DAY_IN_SECONDS;
	}

	private static function get_credentials() {
		return array(
			'key' => trim( (string) get_option( 'Listeo_lic_Key', '' ) ),
			'email' => trim( (string) get_option( 'Listeo_lic_email', '' ) ),
		);
	}

	private static function is_placeholder_key( $key ) {
		return in_array( strtoupper( trim( $key ) ), array( 'OFFLINE-ACTIVATION' ), true );
	}

	private static function is_ajax_request() {
		return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	private static function is_tracked_option( $option ) {
		return in_array( $option, array( 'Listeo_lic_Key', 'Listeo_lic_email' ), true );
	}

	private static function is_allowed_admin_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 'listeo_license' === $page ) {
			return true;
		}

		return in_array( $action, array(
			'Listeo_el_activate_license',
			'Listeo_el_deactivate_license',
			'listeo_el_activate_license',
			'listeo_el_deactivate_license',
			'listeo_deactivate_license',
			'listeo_reset_license_data',
			'listeo_deactivate_license_ajax',
			'listeo_revalidate_license_ajax',
			'listeo_remove_license_ajax',
		), true );
	}

	private static function is_core_admin_page( $page, $pagenow, $action ) {
		if ( self::is_core_admin_action( $action ) ) {
			return true;
		}

		if ( $page && preg_match( '/^listeo([_-]|$)/', $page ) ) {
			return true;
		}

		if ( $page && in_array( $page, array( 'listeo-reports', 'listeo-analytics', 'listeo-listing-types' ), true ) ) {
			return true;
		}

		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php', 'post.php', 'edit-tags.php' ), true ) ) {
			$post_type = self::get_admin_post_type_from_request();
			$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

			return 'listing' === $post_type || self::is_listing_taxonomy( $taxonomy );
		}

		return false;
	}

	private static function is_core_admin_action( $action ) {
		if ( ! $action || self::is_allowed_admin_request() ) {
			return false;
		}

		if ( preg_match( '/^listeo([_-]|$)/', $action ) || preg_match( '/^listeoajax/', $action ) ) {
			return true;
		}

		return in_array( $action, array(
			'check_avaliabity',
			'calculate_price',
			'get_available_hours',
			'update_slots',
			'get_carousel_slots_availability',
			'get_booked_hours',
			'reload_reviews',
			'reply_to_review',
			'edit_reply_to_review',
			'edit_review',
			'get_comment_review_details',
			'remove_activity',
			'remove_all_activities',
			'verify_ticket',
			'get_ticket',
			'post_title_autocomplete',
			'listingAutocompleteSearch',
			'listingautocompletesearch',
			'handle_dropped_media',
			'handle_delete_media',
			'create_express_stripe_account',
			'get_express_stripe_account_link',
			'get_logged_header',
			'get_logged_claim',
			'get_booking_button',
			'get_booking_form',
			'add_new_listing_ical',
			'add_remove_listing_ical',
			'refresh_listing_import_ical',
			'track_ad_view',
			'track_ad_click',
			'test_listeo_cache',
		), true );
	}

	private static function get_listing_taxonomies() {
		$taxonomies = array( 'listing_category', 'region', 'listing_feature' );

		if ( function_exists( 'listeo_core_custom_listing_types' ) ) {
			$manager = listeo_core_custom_listing_types();
			if ( $manager && method_exists( $manager, 'get_listing_types' ) ) {
				$types = $manager->get_listing_types( true );
				foreach ( $types as $type ) {
					if ( ! empty( $type->slug ) ) {
						$taxonomies[] = $type->slug . '_category';
					}
				}
			}
		}

		return array_unique( apply_filters( 'listeo_core_environment_sync_taxonomies', $taxonomies ) );
	}

	private static function is_listing_taxonomy( $taxonomy ) {
		return $taxonomy && in_array( $taxonomy, self::get_listing_taxonomies(), true );
	}

	private static function get_admin_post_type_from_request() {
		if ( isset( $_GET['post_type'] ) ) {
			return sanitize_key( wp_unslash( $_GET['post_type'] ) );
		}

		$post_id = 0;
		if ( isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
		} elseif ( isset( $_POST['post_ID'] ) ) {
			$post_id = absint( $_POST['post_ID'] );
		}

		return $post_id ? get_post_type( $post_id ) : '';
	}
}
