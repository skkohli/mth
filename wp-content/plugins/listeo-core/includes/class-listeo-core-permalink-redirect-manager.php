<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Redirect Manager for Listeo Custom Permalinks
 * 
 * Handles URL redirects when permalink structures change
 * Maintains SEO and prevents broken links
 *
 * @package listeo-core
 * @since 1.9.51
 */
class Listeo_Core_Permalink_Redirect_Manager {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since 1.9.51
	 */
	private static $_instance = null;

	/**
	 * Database table name for redirects
	 *
	 * @var string
	 */
	private $table_name = 'listeo_core_permalink_redirects';

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
		// Create table on activation
		add_action( 'init', array( $this, 'maybe_create_table' ) );

		// Handle redirects on parse_request (before WordPress processes the URL)
		add_action( 'parse_request', array( $this, 'handle_redirects_early' ), 1 );

		// Also handle redirects on template_redirect as fallback
		add_action( 'template_redirect', array( $this, 'handle_redirects' ), 1 );

		// Listen for structure changes
		add_action( 'listeo_custom_permalink_structure_changed', array( $this, 'on_structure_changed' ), 10, 2 );

		// Cleanup old redirects (weekly)
		add_action( 'listeo_cleanup_old_redirects', array( $this, 'cleanup_old_redirects' ) );
		if ( ! wp_next_scheduled( 'listeo_cleanup_old_redirects' ) ) {
			wp_schedule_event( time(), 'weekly', 'listeo_cleanup_old_redirects' );
		}

		// Emergency cleanup on admin init (runs once per session)
		add_action( 'admin_init', array( $this, 'maybe_emergency_cleanup' ) );
	}

	/**
	 * Create redirects table if it doesn't exist
	 */
	public function maybe_create_table() {
		// Check if we've already created the table
		if ( get_option( 'listeo_redirect_table_created' ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;

		// Check if table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare( 
			"SHOW TABLES LIKE %s", 
			$table_name 
		) );

		if ( $table_exists !== $table_name ) {
			$this->create_table();
		}
		
		// Mark table as created (this flag persists forever)
		update_option( 'listeo_redirect_table_created', true );
	}

	/**
	 * Create the redirects table
	 */
	private function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			old_url varchar(255) NOT NULL,
			new_url varchar(255) NOT NULL,
			redirect_type varchar(10) DEFAULT '301',
			created_date datetime DEFAULT CURRENT_TIMESTAMP,
			hit_count int(11) DEFAULT 0,
			last_hit datetime NULL,
			PRIMARY KEY (id),
			INDEX idx_old_url (old_url),
			INDEX idx_post_id (post_id),
			INDEX idx_created_date (created_date)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Handle incoming requests early in parse_request phase
	 * This catches URLs before WordPress processes them through its routing
	 *
	 * @param WP $wp WordPress environment setup class
	 */
	public function handle_redirects_early( $wp ) {
		// Only handle if redirects are enabled
		if ( ! $this->are_redirects_enabled() ) {
			return;
		}

		// Don't redirect admin pages, feeds, or non-GET requests
		if ( is_admin() || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
			return;
		}

		// Get the request path
		$request_uri = $_SERVER['REQUEST_URI'];
		$request_path = parse_url( $request_uri, PHP_URL_PATH );
		
		// Don't process if it's not a potential listing URL
		if ( ! $this->looks_like_listing_url( $request_path ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;

		// Get the current URL
		$current_url = home_url( $request_path );
		$current_url_clean = rtrim( $current_url, '/' );

		// Look for a redirect
		$redirect = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE old_url = %s OR old_url = %s",
			$current_url_clean,
			$current_url_clean . '/'
		) );

		if ( $redirect ) {
			// Validate the redirect target URL before redirecting
			$target_url = $redirect->new_url;
			
			// Check for malformed URLs that contain placeholders or invalid patterns
			if ( strpos( $target_url, '/-/' ) !== false || 
				 strpos( $target_url, '/admin/' ) !== false ||
				 strpos( $target_url, '--' ) !== false ||
				 empty( parse_url( $target_url, PHP_URL_PATH ) ) ) {
				
				
				// Delete this bad redirect from database
				$wpdb->delete( $table_name, array( 'id' => $redirect->id ), array( '%d' ) );
				
				return; // Don't redirect, let WordPress handle normally
			}

			// Update hit statistics
			$wpdb->update(
				$table_name,
				array(
					'hit_count' => $redirect->hit_count + 1,
					'last_hit'  => current_time( 'mysql' ),
				),
				array( 'id' => $redirect->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);


			// Perform the redirect
			$redirect_code = intval( $redirect->redirect_type );
			wp_redirect( $target_url, $redirect_code );
			exit;
		}
	}

	/**
	 * Check if a URL path looks like it could be a listing URL
	 *
	 * @param string $path URL path
	 * @return bool
	 */
	private function looks_like_listing_url( $path ) {
		// Get settings
		$settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
		$listing_base = $settings['listing_base'] ?? 'listing';
		
		$path = trim( $path, '/' );
		$parts = explode( '/', $path );
		
		// Should have at least 2 parts for any listing URL
		if ( count( $parts ) < 2 ) {
			return false;
		}
		
		// Check if it starts with the listing base (default WordPress structure)
		if ( $parts[0] === $listing_base ) {
			return true;
		}
		
		// If custom permalinks are disabled but we have a previous custom structure,
		// check if this might be an old custom URL that needs redirecting
		if ( ! $this->are_custom_permalinks_enabled() && ! empty( $settings['custom_structure'] ) ) {
			// For custom structures, the URL could start with anything
			// We'll let the database lookup determine if it's a valid redirect
			return true;
		}
		
		// If custom permalinks are enabled, check against the custom structure
		if ( $this->are_custom_permalinks_enabled() && ! empty( $settings['custom_structure'] ) ) {
			// Custom structures can start with any token, so we need to be more permissive
			// The actual validation happens in the database lookup
			return true;
		}
		
		return false;
	}

	/**
	 * Handle incoming requests and check for redirects (fallback method)
	 */
	public function handle_redirects() {
		// Only handle if redirects are enabled
		if ( ! $this->are_redirects_enabled() ) {
			return;
		}

		// Don't redirect admin pages, feeds, or non-GET requests
		if ( is_admin() || is_feed() || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;

		// Get the current request URI
		$current_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$current_url = home_url( $current_path );

		// Remove trailing slash for comparison
		$current_url = rtrim( $current_url, '/' );

		// Look for a redirect
		$redirect = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE old_url = %s OR old_url = %s",
			$current_url,
			$current_url . '/'
		) );

		if ( $redirect ) {
			// Update hit statistics
			$wpdb->update(
				$table_name,
				array(
					'hit_count' => $redirect->hit_count + 1,
					'last_hit'  => current_time( 'mysql' ),
				),
				array( 'id' => $redirect->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			// Perform the redirect
			$redirect_code = intval( $redirect->redirect_type );
			wp_redirect( $redirect->new_url, $redirect_code );
			exit;
		}
	}

	/**
	 * Handle permalink structure changes
	 *
	 * @param string $old_structure Previous structure
	 * @param string $new_structure New structure
	 */
	public function on_structure_changed( $old_structure, $new_structure ) {
		if ( ! $this->are_redirects_enabled() ) {
			return;
		}

		// Don't create redirects if structures are the same
		if ( $old_structure === $new_structure ) {
			return;
		}

		// Generate redirects for all published listings
		$this->generate_redirects_for_structure_change( $old_structure, $new_structure );
	}

	/**
	 * Generate redirects when permalink structure changes
	 *
	 * @param string $old_structure Previous structure
	 * @param string $new_structure New structure
	 */
	private function generate_redirects_for_structure_change( $old_structure, $new_structure ) {
		// Get all published listings
		$listings = get_posts( array(
			'post_type'      => 'listing',
			'post_status'    => array( 'publish', 'private' ),
			'numberposts'    => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $listings ) ) {
			return;
		}

		// Initialize token parser
		if ( ! class_exists( 'Listeo_Core_Permalink_Token_Parser' ) ) {
			return;
		}

		$token_parser = new Listeo_Core_Permalink_Token_Parser();
		$redirects_created = 0;

		foreach ( $listings as $post_id ) {
			// Generate old URL
			$old_path = '';
			if ( ! empty( $old_structure ) ) {
				$old_path = $token_parser->parse_structure( $post_id, $old_structure );
			}

			// Generate new URL  
			$new_path = $token_parser->parse_structure( $post_id, $new_structure );

			if ( empty( $old_path ) || empty( $new_path ) || $old_path === $new_path ) {
				continue;
			}

			$old_url = home_url( user_trailingslashit( $old_path ) );
			$new_url = home_url( user_trailingslashit( $new_path ) );

			// Add redirect
			if ( $this->add_redirect( $post_id, $old_url, $new_url ) ) {
				$redirects_created++;
			}

			// Prevent timeout on large sites
			if ( $redirects_created % 100 === 0 ) {
				// Brief pause every 100 redirects
				usleep( 100000 ); // 0.1 seconds
			}
		}

	}

	/**
	 * Add a new redirect
	 *
	 * @param int    $post_id   Post ID
	 * @param string $old_url   Old URL
	 * @param string $new_url   New URL
	 * @param string $type      Redirect type (301, 302)
	 * @return bool Success
	 */
	public function add_redirect( $post_id, $old_url, $new_url, $type = '301' ) {
		global $wpdb;

		// Validate URLs
		if ( empty( $old_url ) || empty( $new_url ) || $old_url === $new_url ) {
			return false;
		}

		$table_name = $wpdb->prefix . $this->table_name;

		// Check if redirect already exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE old_url = %s",
			rtrim( $old_url, '/' )
		) );

		if ( $existing ) {
			// Update existing redirect
			return $wpdb->update(
				$table_name,
				array(
					'new_url'      => $new_url,
					'redirect_type' => $type,
					'post_id'      => $post_id,
				),
				array( 'id' => $existing ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			) !== false;
		} else {
			// Insert new redirect
			return $wpdb->insert(
				$table_name,
				array(
					'post_id'       => $post_id,
					'old_url'       => rtrim( $old_url, '/' ),
					'new_url'       => $new_url,
					'redirect_type' => $type,
				),
				array( '%d', '%s', '%s', '%s' )
			) !== false;
		}
	}

	/**
	 * Remove redirects for a specific post
	 *
	 * @param int $post_id Post ID
	 * @return bool Success
	 */
	public function remove_post_redirects( $post_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;

		return $wpdb->delete(
			$table_name,
			array( 'post_id' => $post_id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Clean up old and unused redirects
	 *
	 * @param int $days_old     Remove unused redirects older than this many days
	 * @param int $max_unused   Maximum number of unused redirects to keep
	 */
	public function cleanup_old_redirects( $days_old = 90, $max_unused = 1000 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;

		// Remove redirects older than X days with no hits
		$removed_old = $wpdb->query( $wpdb->prepare(
			"DELETE FROM $table_name 
			 WHERE hit_count = 0 
			 AND created_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days_old
		) );

		// Remove redirects for deleted posts
		$removed_orphaned = $wpdb->query(
			"DELETE r FROM $table_name r
			 LEFT JOIN {$wpdb->posts} p ON r.post_id = p.ID
			 WHERE p.ID IS NULL"
		);

		// If we still have too many unused redirects, remove the oldest
		$unused_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM $table_name WHERE hit_count = 0"
		);

		$removed_excess = 0;
		if ( $unused_count > $max_unused ) {
			$excess = $unused_count - $max_unused;
			$removed_excess = $wpdb->query( $wpdb->prepare(
				"DELETE FROM $table_name 
				 WHERE hit_count = 0 
				 ORDER BY created_date ASC 
				 LIMIT %d",
				$excess
			) );
		}


		return array(
			'old'      => $removed_old,
			'orphaned' => $removed_orphaned,
			'excess'   => $removed_excess,
		);
	}

	/**
	 * Get redirect statistics
	 *
	 * @return array Statistics
	 */
	public function get_redirect_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;

		$stats = array(
			'total'     => 0,
			'used'      => 0,
			'unused'    => 0,
			'this_week' => 0,
		);

		// Total redirects
		$stats['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		if ( $stats['total'] > 0 ) {
			// Used vs unused
			$stats['used'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE hit_count > 0" );
			$stats['unused'] = $stats['total'] - $stats['used'];

			// Hits this week
			$stats['this_week'] = $wpdb->get_var(
				"SELECT COUNT(*) FROM $table_name 
				 WHERE last_hit >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
			);
		}

		return $stats;
	}

	/**
	 * Maybe run emergency cleanup (only once per day to avoid performance issues)
	 */
	public function maybe_emergency_cleanup() {
		$last_cleanup = get_option( 'listeo_last_emergency_cleanup', 0 );
		$now = time();
		
		// Only run once per day
		if ( $now - $last_cleanup > DAY_IN_SECONDS ) {
			$this->emergency_cleanup_malformed_redirects();
			update_option( 'listeo_last_emergency_cleanup', $now );
		}
	}

	/**
	 * Generate redirects from default WordPress structure to custom structure
	 * This handles redirects like /listing/burger-house/ to /123/burger-house/restaurants/
	 */
	public function generate_redirects_from_default_structure() {
		if ( ! $this->are_redirects_enabled() ) {
			return array( 'success' => false, 'message' => 'Redirects not enabled' );
		}

		// Get current custom permalink settings
		$settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
		
		if ( empty( $settings['custom_permalinks_enabled'] ) || $settings['custom_permalinks_enabled'] !== '1' ) {
			return array( 'success' => false, 'message' => 'Custom permalinks not enabled' );
		}

		if ( empty( $settings['custom_structure'] ) ) {
			return array( 'success' => false, 'message' => 'No custom structure defined' );
		}

		$custom_structure = $settings['custom_structure'];

		// Get all published listings
		$listings = get_posts( array(
			'post_type'      => 'listing',
			'post_status'    => array( 'publish', 'private' ),
			'numberposts'    => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $listings ) ) {
			return array( 'success' => false, 'message' => 'No listings found' );
		}

		// Initialize token parser
		if ( ! class_exists( 'Listeo_Core_Permalink_Token_Parser' ) ) {
			return array( 'success' => false, 'message' => 'Token parser not available' );
		}

		$token_parser = new Listeo_Core_Permalink_Token_Parser();
		$redirects_created = 0;
		$redirects_skipped = 0;

		foreach ( $listings as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Generate default WordPress URL (e.g., /listing/burger-house/)
			$listing_base = $settings['listing_base'] ?? 'listing';
			$default_url = home_url( user_trailingslashit( $listing_base . '/' . $post->post_name ) );

			// Generate new custom URL
			$custom_path = $token_parser->parse_structure( $post_id, $custom_structure );
			if ( empty( $custom_path ) ) {
				$redirects_skipped++;
				continue;
			}

			$custom_url = home_url( user_trailingslashit( $custom_path ) );

			// Skip if URLs are the same
			if ( $default_url === $custom_url ) {
				$redirects_skipped++;
				continue;
			}

			// Add redirect from default to custom
			if ( $this->add_redirect( $post_id, $default_url, $custom_url ) ) {
				$redirects_created++;
			} else {
				$redirects_skipped++;
			}

			// Prevent timeout on large sites
			if ( ( $redirects_created + $redirects_skipped ) % 50 === 0 ) {
				usleep( 50000 ); // 0.05 seconds pause
			}
		}


		return array(
			'success' => true,
			'created' => $redirects_created,
			'skipped' => $redirects_skipped,
			'message' => sprintf( 'Created %d redirects from default WordPress structure', $redirects_created )
		);
	}

	/**
	 * Generate reverse redirects from custom structure to default WordPress structure
	 * This handles redirects when custom permalinks are disabled
	 * e.g., /burger-house/restaurants/123/ → /ogloszenie/burger-house/
	 */
	public function generate_reverse_redirects_to_default() {
		if ( ! $this->are_redirects_enabled() ) {
			return array( 'success' => false, 'message' => 'Redirects not enabled' );
		}

		// This should only run when custom permalinks are DISABLED
		if ( $this->are_custom_permalinks_enabled() ) {
			return array( 'success' => false, 'message' => 'Custom permalinks are still enabled' );
		}

		// Get current settings to determine default structure
		$settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
		
		// Try to get the old custom structure from transient (stored when permalinks were disabled)
		$previous_custom_structure = get_transient( 'listeo_old_custom_structure_for_redirects' );
		
		// If no transient, try to get it from current settings
		if ( empty( $previous_custom_structure ) ) {
			$previous_custom_structure = $settings['custom_structure'] ?? '';
		}
		
		if ( empty( $previous_custom_structure ) ) {
			return array( 'success' => false, 'message' => 'No previous custom structure found' );
		}

		$listing_base = $settings['listing_base'] ?? 'listing';

		// Get all published listings
		$listings = get_posts( array(
			'post_type'      => 'listing',
			'post_status'    => array( 'publish', 'private' ),
			'numberposts'    => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $listings ) ) {
			return array( 'success' => false, 'message' => 'No listings found' );
		}

		// Initialize token parser
		if ( ! class_exists( 'Listeo_Core_Permalink_Token_Parser' ) ) {
			return array( 'success' => false, 'message' => 'Token parser not available' );
		}

		$token_parser = new Listeo_Core_Permalink_Token_Parser();
		$redirects_created = 0;
		$redirects_skipped = 0;

		foreach ( $listings as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Generate what the custom URL WAS when custom permalinks were enabled
			$custom_path = $token_parser->parse_structure( $post_id, $previous_custom_structure );
			if ( empty( $custom_path ) ) {
				$redirects_skipped++;
				continue;
			}

			$custom_url = home_url( user_trailingslashit( $custom_path ) );

			// Generate default WordPress URL (where it should redirect TO now)
			$default_url = home_url( user_trailingslashit( $listing_base . '/' . $post->post_name ) );

			// Skip if URLs are the same
			if ( $custom_url === $default_url ) {
				$redirects_skipped++;
				continue;
			}

			// Add reverse redirect: custom → default
			if ( $this->add_redirect( $post_id, $custom_url, $default_url ) ) {
				$redirects_created++;
			} else {
				$redirects_skipped++;
			}

			// Prevent timeout on large sites
			if ( ( $redirects_created + $redirects_skipped ) % 50 === 0 ) {
				usleep( 50000 ); // 0.05 seconds pause
			}
		}

		// Clean up the transient
		delete_transient( 'listeo_old_custom_structure_for_redirects' );


		return array(
			'success' => true,
			'created' => $redirects_created,
			'skipped' => $redirects_skipped,
			'message' => sprintf( 'Created %d reverse redirects from custom to default structure', $redirects_created )
		);
	}

	/**
	 * Emergency cleanup of malformed redirects
	 * Removes redirects with invalid patterns like /-/ or /admin/
	 * 
	 * @return array Cleanup results
	 */
	public function emergency_cleanup_malformed_redirects() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
		
		// Check if table exists
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
		
		if ( ! $table_exists ) {
			return array( 'success' => false, 'message' => 'Redirect table does not exist' );
		}
		
		// Count malformed redirects before deletion
		$malformed_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM $table_name 
			 WHERE new_url LIKE '%/-/%' 
				OR new_url LIKE '%/admin/%' 
				OR new_url LIKE '%--%'
				OR new_url = ''"
		);
		
		// Delete malformed redirects
		$deleted_malformed = $wpdb->query(
			"DELETE FROM $table_name 
			 WHERE new_url LIKE '%/-/%' 
				OR new_url LIKE '%/admin/%' 
				OR new_url LIKE '%--%'
				OR new_url = ''"
		);
		
		// If custom permalinks are disabled, also remove incorrect forward redirects
		$deleted_incorrect = 0;
		if ( ! $this->are_custom_permalinks_enabled() ) {
			$deleted_incorrect = $wpdb->query(
				"DELETE FROM $table_name 
				 WHERE old_url LIKE '%ogloszenie/%' 
					AND new_url NOT LIKE '%ogloszenie/%'"
			);
		}
		
		$total_deleted = $deleted_malformed + $deleted_incorrect;
		
		
		return array(
			'success' => true,
			'malformed_found' => $malformed_count,
			'malformed_deleted' => $deleted_malformed,
			'incorrect_deleted' => $deleted_incorrect,
			'total_deleted' => $total_deleted,
			'message' => sprintf( 'Cleaned up %d problematic redirects', $total_deleted )
		);
	}

	/**
	 * Check if redirects are enabled in settings
	 *
	 * @return bool
	 */
	private function are_redirects_enabled() {
		$settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
		
		// Always enable redirects if the checkbox is checked
		// This allows for "reverse redirects" when custom permalinks are disabled
		return isset( $settings['enable_redirects'] ) && $settings['enable_redirects'] === '1';
	}

	/**
	 * Check if custom permalinks are currently enabled
	 *
	 * @return bool
	 */
	private function are_custom_permalinks_enabled() {
		$settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
		return isset( $settings['custom_permalinks_enabled'] ) && $settings['custom_permalinks_enabled'] === '1';
	}

	/**
	 * Get recent redirect activity
	 *
	 * @param int $limit Number of recent redirects to get
	 * @return array Recent redirects
	 */
	public function get_recent_redirects( $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name 
			 WHERE hit_count > 0 
			 ORDER BY last_hit DESC 
			 LIMIT %d",
			$limit
		) );
	}

	/**
	 * Test a redirect URL
	 *
	 * @param string $url URL to test
	 * @return array|false Redirect info or false if no redirect
	 */
	public function test_redirect( $url ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;
		$clean_url = rtrim( $url, '/' );

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE old_url = %s OR old_url = %s",
			$clean_url,
			$clean_url . '/'
		) );
	}
}