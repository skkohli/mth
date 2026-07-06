<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Safety Manager for Listeo Custom Permalinks
 * 
 * Handles safety mechanisms, fallbacks, and error recovery
 *
 * @package listeo-core
 * @since 1.9.51
 */
class Listeo_Core_Permalink_Safety_Manager {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since 1.9.51
	 */
	private static $_instance = null;

	/**
	 * Error log for tracking issues
	 *
	 * @var array
	 */
	private $error_log = array();

	/**
	 * Maximum errors before auto-disable
	 *
	 * @var int
	 */
	private $max_errors = 10;

	/**
	 * Allows for accessing single instance of class.
	 *
	 * @since 1.9.51
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Validation on init
		add_action( 'init', array( $this, 'validate_configuration' ), 15 );

		// Safe permalink generation
		add_filter( 'post_type_link', array( $this, 'safe_permalink_generation' ), 5, 2 );

		// Emergency disable check
		add_action( 'init', array( $this, 'check_emergency_disable' ) );

		// Auto-flush rewrite rules when needed
		add_action( 'wp_loaded', array( $this, 'auto_flush_rewrite_rules' ) );

		// Monitor for PHP errors in our code
		//register_shutdown_function( array( $this, 'handle_fatal_errors' ) );

		// Admin notices for configuration issues
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );

		// Health check integration
		add_filter( 'site_status_tests', array( $this, 'add_site_health_tests' ) );
	}

	/**
	 * Validate configuration on init
	 */
	public function validate_configuration() {
		// Only validate if custom permalinks are enabled
		if ( ! $this->is_custom_permalinks_enabled() ) {
			return;
		}

		$settings = $this->get_custom_permalink_settings();
		$structure = ! empty( $settings['custom_structure'] ) ? $settings['custom_structure'] : '';

		if ( empty( $structure ) ) {
			$this->log_error( 'Empty custom permalink structure detected' );
			$this->disable_custom_permalinks( 'Empty structure' );
			return;
		}

		// Validate structure
		if ( class_exists( 'Listeo_Core_Permalink_Validator' ) ) {
			$validator = new Listeo_Core_Permalink_Validator();
			$validation_result = $validator->validate_structure( $structure );

			if ( true !== $validation_result ) {
				$errors = is_array( $validation_result ) ? implode( ', ', $validation_result ) : $validation_result;
				$this->log_error( 'Invalid custom permalink structure: ' . $errors );
				$this->disable_custom_permalinks( 'Invalid structure: ' . $errors );
				return;
			}
		}

		// Check for potential conflicts
		$this->check_for_conflicts( $structure );
	}

	/**
	 * Safe permalink generation with comprehensive error handling
	 *
	 * @param string  $permalink The original permalink
	 * @param WP_Post $post      The post object
	 * @return string Modified or original permalink
	 */
	public function safe_permalink_generation( $permalink, $post ) {
		// Only handle listing post type
		if ( ! isset( $post->post_type ) || 'listing' !== $post->post_type ) {
			return $permalink;
		}

		// Skip if custom permalinks are disabled
		if ( ! $this->is_custom_permalinks_enabled() ) {
			return $permalink;
		}

		// Skip if post is not in a publishable state
		if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return $permalink;
		}

		try {
			// Let the custom permalink manager handle this
			// This filter runs early to catch any issues before the main processing
			return $permalink;

		} catch ( Exception $e ) {
			$this->log_error( 'Error in permalink generation: ' . $e->getMessage() . ' for post ID: ' . $post->ID );
			
			// Check if we've hit the error threshold
			$this->check_error_threshold();

			// Return original permalink as fallback
			return $permalink;
		}
	}

	/**
	 * Check for emergency disable constant
	 */
	public function check_emergency_disable() {
		if ( defined( 'LISTEO_DISABLE_CUSTOM_PERMALINKS' ) && LISTEO_DISABLE_CUSTOM_PERMALINKS ) {
			if ( $this->is_custom_permalinks_enabled() ) {
				$this->disable_custom_permalinks( 'Emergency disable constant activated' );
				add_action( 'admin_notices', array( $this, 'emergency_disable_notice' ) );
			}
		}
	}

	/**
	 * Auto-flush rewrite rules when structure changes
	 */
	public function auto_flush_rewrite_rules() {
		if ( ! $this->is_custom_permalinks_enabled() ) {
			return;
		}

		$settings = $this->get_custom_permalink_settings();
		$current_structure = ! empty( $settings['custom_structure'] ) ? $settings['custom_structure'] : '';
		$last_structure = get_transient( 'listeo_custom_permalink_structure' );

		if ( $current_structure !== $last_structure ) {
			// Structure changed, flush rewrite rules
			flush_rewrite_rules( false );
			set_transient( 'listeo_custom_permalink_structure', $current_structure, DAY_IN_SECONDS );
			
			$this->log_info( 'Rewrite rules flushed due to structure change' );
		}
	}

	/**
	 * Handle fatal PHP errors
	 */
	public function handle_fatal_errors() {
		$error = error_get_last();
		
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			// Check if the error is related to our custom permalink code
			if ( strpos( $error['message'], 'listeo' ) !== false || 
			     strpos( $error['file'], 'custom-permalink' ) !== false ) {
				
				$this->log_error( 'Fatal error in custom permalink code: ' . $error['message'] );
				
				// Attempt emergency disable
				$this->emergency_disable();
			}
		}
	}

	/**
	 * Emergency disable of custom permalinks
	 */
	private function emergency_disable() {
		try {
			$settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
			$settings['custom_permalinks_enabled'] = '0';
			update_option( 'listeo_core_permalinks', wp_json_encode( $settings ) );
			
			// Clear any cached rewrite rules
			delete_option( 'rewrite_rules' );
			
			$this->log_error( 'Emergency disable activated due to fatal error' );
		} catch ( Exception $e ) {
			// Last resort - log to PHP error log
			// Silent failure - emergency disable failed
		}
	}

	/**
	 * Show admin notices for configuration issues
	 */
	public function show_admin_notices() {
		$notices = get_transient( 'listeo_custom_permalink_notices' );
		
		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$class = $notice['type'] === 'error' ? 'notice-error' : 'notice-warning';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">';
			echo '<p><strong>Listeo Custom Permalinks:</strong> ' . esc_html( $notice['message'] ) . '</p>';
			echo '</div>';
		}

		// Clear notices after displaying
		delete_transient( 'listeo_custom_permalink_notices' );
	}

	/**
	 * Emergency disable notice
	 */
	public function emergency_disable_notice() {
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>' . esc_html__( 'Listeo Custom Permalinks', 'listeo-core' ) . ':</strong> ';
		echo esc_html__( 'Custom permalinks have been disabled due to the LISTEO_DISABLE_CUSTOM_PERMALINKS constant.', 'listeo-core' );
		echo '</p></div>';
	}

	/**
	 * Check for configuration conflicts
	 *
	 * @param string $structure The permalink structure
	 */
	private function check_for_conflicts( $structure ) {
		$warnings = array();

		// Check for conflict with existing Listeo permalink features
		if ( get_option( 'listeo_region_in_links' ) && strpos( $structure, '%region%' ) === false ) {
			$warnings[] = 'Region in Links setting is enabled but custom structure doesn\'t include %region%';
		}

		if ( get_option( 'listeo_combined_taxonomy_urls' ) ) {
			$warnings[] = 'Combined Taxonomy URLs setting may conflict with custom permalinks';
		}

		// Check for potential WordPress conflicts
		$permalink_settings = Listeo_Core_Post_Types::get_permalink_structure();
		$listing_base = ! empty( $permalink_settings['listing_base'] ) ? $permalink_settings['listing_base'] : 'listing';
		
		if ( strpos( $structure, $listing_base ) === 0 ) {
			$warnings[] = 'Custom structure starts with listing base "' . $listing_base . '" which may cause conflicts';
		}

		// Log warnings
		foreach ( $warnings as $warning ) {
			$this->log_warning( $warning );
		}
	}

	/**
	 * Add site health tests
	 *
	 * @param array $tests Existing tests
	 * @return array Modified tests
	 */
	public function add_site_health_tests( $tests ) {
		$tests['direct']['listeo_custom_permalinks'] = array(
			'label' => __( 'Listeo Custom Permalinks', 'listeo-core' ),
			'test'  => array( $this, 'site_health_test' ),
		);

		return $tests;
	}

	/**
	 * Site health test for custom permalinks
	 *
	 * @return array Test result
	 */
	public function site_health_test() {
		$result = array(
			'label'       => __( 'Listeo Custom Permalinks are working correctly', 'listeo-core' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Listeo', 'listeo-core' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Custom permalinks are configured properly and working as expected.', 'listeo-core' )
			),
			'test'        => 'listeo_custom_permalinks',
		);

		if ( ! $this->is_custom_permalinks_enabled() ) {
			$result['label'] = __( 'Listeo Custom Permalinks are disabled', 'listeo-core' );
			$result['status'] = 'recommended';
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'Custom permalinks are disabled. This is the default safe state.', 'listeo-core' )
			);
			return $result;
		}

		// Check for issues
		$health_status = $this->get_health_status();

		if ( $health_status['status'] !== 'healthy' ) {
			$result['status'] = 'critical';
			$result['label'] = __( 'Listeo Custom Permalinks have issues', 'listeo-core' );
			$result['description'] = sprintf(
				'<p>%s: %s</p>',
				__( 'Custom permalinks are enabled but have configuration issues', 'listeo-core' ),
				$health_status['message']
			);
		}

		return $result;
	}

	/**
	 * Get health status
	 *
	 * @return array Health status
	 */
	public function get_health_status() {
		if ( ! $this->is_custom_permalinks_enabled() ) {
			return array(
				'status'  => 'disabled',
				'message' => __( 'Custom permalinks are disabled', 'listeo-core' ),
			);
		}

		$settings = $this->get_custom_permalink_settings();
		$structure = ! empty( $settings['custom_structure'] ) ? $settings['custom_structure'] : '';

		if ( empty( $structure ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Custom permalink structure is empty', 'listeo-core' ),
			);
		}

		// Check validation
		if ( class_exists( 'Listeo_Core_Permalink_Validator' ) ) {
			$validator = new Listeo_Core_Permalink_Validator();
			$validation = $validator->validate_structure( $structure );

			if ( true !== $validation ) {
				return array(
					'status'  => 'error',
					'message' => is_array( $validation ) ? implode( ', ', $validation ) : $validation,
				);
			}
		}

		// Check error count
		$error_count = $this->get_error_count();
		if ( $error_count > 5 ) {
			return array(
				'status'  => 'warning',
				'message' => sprintf( __( 'High error count: %d recent errors', 'listeo-core' ), $error_count ),
			);
		}

		return array(
			'status'  => 'healthy',
			'message' => __( 'Custom permalinks are working correctly', 'listeo-core' ),
		);
	}

	/**
	 * Log an error
	 *
	 * @param string $message Error message
	 */
	private function log_error( $message ) {
		$this->error_log[] = array(
			'type'      => 'error',
			'message'   => $message,
			'timestamp' => time(),
		);

		// Also log to WordPress debug log

		// Store in database for persistence
		$this->store_log_entry( 'error', $message );
	}

	/**
	 * Log a warning
	 *
	 * @param string $message Warning message
	 */
	private function log_warning( $message ) {
		$this->error_log[] = array(
			'type'      => 'warning',
			'message'   => $message,
			'timestamp' => time(),
		);

		$this->store_log_entry( 'warning', $message );
	}

	/**
	 * Log informational message
	 *
	 * @param string $message Info message
	 */
	private function log_info( $message ) {
		$this->store_log_entry( 'info', $message );
	}

	/**
	 * Store log entry in database
	 *
	 * @param string $type    Log type
	 * @param string $message Log message
	 */
	private function store_log_entry( $type, $message ) {
		$log = get_option( 'listeo_custom_permalink_log', array() );
		
		// Ensure $log is always an array to prevent "[] operator not supported for strings" error
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		
		$log[] = array(
			'type'      => $type,
			'message'   => $message,
			'timestamp' => time(),
		);

		// Keep only last 100 entries
		$log = array_slice( $log, -100 );
		
		update_option( 'listeo_custom_permalink_log', $log );
	}

	/**
	 * Get error count from recent log entries
	 *
	 * @param int $hours Number of hours to check
	 * @return int Error count
	 */
	private function get_error_count( $hours = 24 ) {
		$log = get_option( 'listeo_custom_permalink_log', array() );
		
		// Ensure $log is always an array
		if ( ! is_array( $log ) ) {
			return 0;
		}
		
		$cutoff = time() - ( $hours * HOUR_IN_SECONDS );
		$error_count = 0;

		foreach ( $log as $entry ) {
			if ( $entry['timestamp'] > $cutoff && $entry['type'] === 'error' ) {
				$error_count++;
			}
		}

		return $error_count;
	}

	/**
	 * Check error threshold and auto-disable if needed
	 */
	private function check_error_threshold() {
		$error_count = $this->get_error_count( 1 ); // Errors in the last hour

		if ( $error_count >= $this->max_errors ) {
			$this->disable_custom_permalinks( 'Too many errors: ' . $error_count . ' in the last hour' );
			
			// Add admin notice
			$this->add_admin_notice( 
				'error', 
				'Custom permalinks have been automatically disabled due to repeated errors. Check the error log and fix configuration issues before re-enabling.' 
			);
		}
	}

	/**
	 * Disable custom permalinks
	 *
	 * @param string $reason Reason for disabling
	 */
	private function disable_custom_permalinks( $reason ) {
		try {
			$settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
			$settings['custom_permalinks_enabled'] = '0';
			update_option( 'listeo_core_permalinks', wp_json_encode( $settings ) );
			
			flush_rewrite_rules( false );
			
			$this->log_error( 'Custom permalinks disabled: ' . $reason );
			
			$this->add_admin_notice( 'warning', 'Custom permalinks have been disabled: ' . $reason );
		} catch ( Exception $e ) {
			// Silent failure - could not disable
		}
	}

	/**
	 * Add admin notice
	 *
	 * @param string $type    Notice type
	 * @param string $message Notice message
	 */
	private function add_admin_notice( $type, $message ) {
		$notices = get_transient( 'listeo_custom_permalink_notices' );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'type'    => $type,
			'message' => $message,
		);

		set_transient( 'listeo_custom_permalink_notices', $notices, HOUR_IN_SECONDS );
	}

	/**
	 * Check if custom permalinks are enabled
	 *
	 * @return bool
	 */
	private function is_custom_permalinks_enabled() {
		if ( defined( 'LISTEO_DISABLE_CUSTOM_PERMALINKS' ) && LISTEO_DISABLE_CUSTOM_PERMALINKS ) {
			return false;
		}

		$settings = $this->get_custom_permalink_settings();
		return isset( $settings['custom_permalinks_enabled'] ) && $settings['custom_permalinks_enabled'] === '1';
	}

	/**
	 * Get custom permalink settings
	 *
	 * @return array
	 */
	private function get_custom_permalink_settings() {
		$raw_settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
		
		$defaults = array(
			'custom_permalinks_enabled' => '0',
			'custom_structure'          => '%listing_category%/%listing%',
			'enable_redirects'          => '0',
		);

		return wp_parse_args( $raw_settings, $defaults );
	}
}