<?php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Custom Permalink Manager for Listeo Core
 * 
 * Manages custom permalink structures for listings with token-based URLs
 * Completely optional system that preserves existing permalink functionality
 *
 * @package listeo-core
 * @since 1.9.51
 */
class Listeo_Core_Custom_Permalink_Manager
{
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since 1.9.51
	 */
	private static $_instance = null;

	/**
	 * Token parser instance
	 *
	 * @var Listeo_Core_Permalink_Token_Parser
	 */
	private $token_parser;

	/**
	 * Validator instance
	 *
	 * @var Listeo_Core_Permalink_Validator
	 */
	private $validator;

	/**
	 * Redirect manager instance
	 *
	 * @var Listeo_Core_Permalink_Redirect_Manager
	 */
	private $redirect_manager;

	/**
	 * Safety manager instance
	 *
	 * @var Listeo_Core_Permalink_Safety_Manager
	 */
	private $safety_manager;

	/**
	 * Allows for accessing single instance of class.
	 *
	 * @since 1.9.51
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

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Only initialize if the required classes exist
		if ($this->dependencies_exist()) {
			$this->init_components();
			$this->setup_hooks();
		}
	}

	/**
	 * Check if all dependencies exist
	 *
	 * @return bool
	 */
	private function dependencies_exist()
	{
		return class_exists('Listeo_Core_Permalink_Token_Parser') &&
			class_exists('Listeo_Core_Permalink_Validator') &&
			class_exists('Listeo_Core_Permalink_Redirect_Manager') &&
			class_exists('Listeo_Core_Permalink_Safety_Manager');
	}

	/**
	 * Initialize component instances
	 */
	private function init_components()
	{
		$this->token_parser = new Listeo_Core_Permalink_Token_Parser();
		$this->validator = new Listeo_Core_Permalink_Validator();
		$this->redirect_manager = new Listeo_Core_Permalink_Redirect_Manager();
		$this->safety_manager = new Listeo_Core_Permalink_Safety_Manager();
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks()
	{
		// Add request filter to handle URL conflicts (always active)
		add_filter('request', array($this, 'resolve_request_conflicts'), 10);

		// Only hook if custom permalinks are enabled
		if ($this->is_enabled()) {
			add_filter('post_type_link', array($this, 'generate_custom_permalink'), 15, 2);
			// Use priority 15 to run AFTER WordPress core rules (priority 10)
			add_action('init', array($this, 'add_rewrite_rules'), 15);
			add_action('wp_loaded', array($this, 'flush_rewrite_rules_if_needed'));
			// Add Dokan compatibility
			add_action('init', array($this, 'ensure_dokan_compatibility'), 25);
		}

		// ALWAYS hook Dokan compatibility - regardless of custom permalink status
		// This ensures Dokan works whether custom permalinks are enabled or disabled
		add_filter('dokan_dashboard_shortcode_query_vars', array($this, 'preserve_dokan_query_vars'), 5);

		// Always hook structure change detection and redirect generation
		add_action('update_option_listeo_core_permalinks', array($this, 'on_permalink_settings_changed'), 10, 2);
		add_action('listeo_generate_default_redirects', array($this, 'generate_default_redirects_callback'));

		// Hook into WordPress permalink changes to ensure Dokan compatibility
		add_action('update_option_rewrite_rules', array($this, 'on_rewrite_rules_updated'));

		// Emergency disable constant check
		add_action('init', array($this, 'check_emergency_disable'));

		// Check for problematic structures and migrate if needed
		add_action('init', array($this, 'migrate_problematic_structures'), 1);
	}

	/**
	 * Resolve URL conflicts in the request phase
	 * This runs after WordPress has parsed the URL and determined query vars
	 *
	 * @param array $query_vars Current query variables
	 * @return array Modified query variables
	 */
	public function resolve_request_conflicts($query_vars) {
		// If WordPress detected this as an author page, don't interfere
		if (isset($query_vars['author_name']) || isset($query_vars['author'])) {
			// Clear any listing-related query vars that might conflict
			unset($query_vars['listing']);
			unset($query_vars['name']);
			return $query_vars;
		}

		// Protect blog category and tag URLs from being hijacked by listing rewrite rules
		global $wp;
		$request_path = trim($wp->request ?? '', '/');

		if ( ! empty( $request_path ) ) {
			$category_base = get_option( 'category_base' ) ?: 'category';
			$tag_base      = get_option( 'tag_base' ) ?: 'tag';

			// If URL starts with blog category base, ensure it's treated as a blog category
			if ( strpos( $request_path, $category_base . '/' ) === 0 ) {
				// Remove any listing-related query vars that don't belong
				unset( $query_vars['listing'] );
				unset( $query_vars['listing_category'] );
				unset( $query_vars['name'] );

				// Parse the category and pagination from the URL
				$category_path = substr( $request_path, strlen( $category_base ) + 1 );
				if ( preg_match( '#^(.+?)/page/?([0-9]+)/?$#', $category_path, $matches ) ) {
					$query_vars['category_name'] = $matches[1];
					$query_vars['paged']         = (int) $matches[2];
				} elseif ( ! isset( $query_vars['category_name'] ) ) {
					$query_vars['category_name'] = rtrim( $category_path, '/' );
				}

				return $query_vars;
			}

			// If URL starts with blog tag base, ensure it's treated as a blog tag
			if ( strpos( $request_path, $tag_base . '/' ) === 0 ) {
				unset( $query_vars['listing'] );
				unset( $query_vars['name'] );

				$tag_path = substr( $request_path, strlen( $tag_base ) + 1 );
				if ( preg_match( '#^([^/]+)/page/?([0-9]+)/?$#', $tag_path, $matches ) ) {
					$query_vars['tag']   = $matches[1];
					$query_vars['paged'] = (int) $matches[2];
				} elseif ( ! isset( $query_vars['tag'] ) ) {
					$query_vars['tag'] = rtrim( $tag_path, '/' );
				}

				return $query_vars;
			}
		}

		// If we have a listing query, check for conflicts with core WordPress pages
		if (isset($query_vars['listing'])) {
			// Check if this request is actually for core WordPress content
			if ($this->is_wordpress_core_request($request_path)) {
				unset($query_vars['listing']);
			}
		}

		return $query_vars;
	}

	/**
	 * Check if the request path is for WordPress core functionality
	 *
	 * @param string $path The request path
	 * @return bool True if this is a core WordPress request
	 */
	private function is_wordpress_core_request($path) {
		if (empty($path)) {
			return false;
		}
		
		global $wp_rewrite;
		
		// Check against WordPress author base
		$author_base = $wp_rewrite->author_base ?: 'author';
		if (strpos($path, $author_base . '/') === 0) {
			return true;
		}
		
		// Check against blog category and tag bases
		$category_base = get_option( 'category_base' ) ?: 'category';
		$tag_base      = get_option( 'tag_base' ) ?: 'tag';
		if ( strpos( $path, $category_base . '/' ) === 0 ) {
			return true;
		}
		if ( strpos( $path, $tag_base . '/' ) === 0 ) {
			return true;
		}

		// Check against other core WordPress patterns
		$core_patterns = array(
			'wp-admin/', 'wp-content/', 'wp-includes/', 'wp-json/',
			'feed/', 'rdf/', 'rss/', 'rss2/', 'atom/',
			'search/', 'page/', 'comments/', 'trackback/',
			'xmlrpc.php', 'sitemap'
		);
		
		foreach ($core_patterns as $pattern) {
			if (strpos($path, $pattern) === 0) {
				return true;
			}
		}
		
		// Check against custom WordPress bases if set
		if (!empty($wp_rewrite->search_base) && strpos($path, $wp_rewrite->search_base . '/') === 0) {
			return true;
		}
		
		if (!empty($wp_rewrite->comments_base) && strpos($path, $wp_rewrite->comments_base . '/') === 0) {
			return true;
		}
		
		return false;
	}

	/**
	 * Check if custom permalinks are enabled
	 *
	 * @return bool
	 */
	public function is_enabled()
	{
		if (defined('LISTEO_DISABLE_CUSTOM_PERMALINKS') && LISTEO_DISABLE_CUSTOM_PERMALINKS) {
			return false;
		}

		$settings = $this->get_custom_permalink_settings();
		return isset($settings['custom_permalinks_enabled']) && $settings['custom_permalinks_enabled'] === '1';
	}

	/**
	 * Get custom permalink settings
	 *
	 * @return array
	 */
	public function get_custom_permalink_settings()
	{
		$raw_settings = Listeo_Core_Post_Types::get_raw_permalink_settings();

		// Add default custom permalink settings if they don't exist
		$defaults = array(
			'custom_permalinks_enabled' => '0',
			'custom_structure' => '%listing_category%/%listing%',
			'enable_redirects' => '0',
			'permalink_safe_mode' => '0',
		);

		return wp_parse_args($raw_settings, $defaults);
	}

	/**
	 * Check if Safe Mode is enabled
	 *
	 * @return bool
	 */
	public function is_safe_mode_enabled()
	{
		if (!$this->is_enabled()) {
			return false;
		}

		$settings = $this->get_custom_permalink_settings();
		return isset($settings['permalink_safe_mode']) && $settings['permalink_safe_mode'] === '1';
	}

	/**
	 * Get the listing base slug (without hardcoding)
	 *
	 * @return string
	 */
	private function get_listing_base()
	{
		$permalink_structure = Listeo_Core_Post_Types::get_permalink_structure();
		return !empty($permalink_structure['listing_rewrite_slug']) ? $permalink_structure['listing_rewrite_slug'] : 'listing';
	}

	/**
	 * Generate custom permalink for listing
	 *
	 * @param string  $permalink The original permalink
	 * @param WP_Post $post      The post object
	 * @return string Modified permalink or original if not applicable
	 */
	public function generate_custom_permalink($permalink, $post)
	{
		// Only process listing post type
		if (! isset($post->post_type) || 'listing' !== $post->post_type) {
			return $permalink;
		}

		// Skip if post is not published
		if ('publish' !== $post->post_status && 'private' !== $post->post_status) {
			return $permalink;
		}

		try {
			$settings = $this->get_custom_permalink_settings();
			$structure = ! empty($settings['custom_structure']) ? $settings['custom_structure'] : '%listing_category%/%listing%';

			// Safety check: Prevent the problematic "%listing%" structure
			if ($structure === '%listing%') {
				$structure = '%listing_category%/%listing%';
			}

			// Validate structure
			$validation_result = $this->validator->validate_structure($structure);
			if (true !== $validation_result) {
				throw new Exception('Invalid custom structure: ' . implode(', ', $validation_result));
			}

			// Parse tokens and generate URL
			$custom_path = $this->token_parser->parse_structure($post->ID, $structure);

			if (empty($custom_path) || strpos($custom_path, '%') !== false) {
				throw new Exception('Failed to parse custom permalink structure');
			}

			// Apply Safe Mode: Prepend listing base if enabled
			if ($this->is_safe_mode_enabled()) {
				$listing_base = $this->get_listing_base();
				// Only prepend if the path doesn't already start with the listing base
				if (strpos($custom_path, $listing_base . '/') !== 0) {
					$custom_path = $listing_base . '/' . $custom_path;
				}
			}

			return home_url(user_trailingslashit($custom_path));
		} catch (Exception $e) {
			// Return original permalink on error
			return $permalink;
		}
	}

	/**
	 * Add custom rewrite rules
	 */
	public function add_rewrite_rules()
	{
		if (! $this->is_enabled()) {
			return;
		}

		$settings = $this->get_custom_permalink_settings();
		$structure = ! empty($settings['custom_structure']) ? $settings['custom_structure'] : '%listing%';

		// Check if structure contains %listing% token (required for URL resolution)
		if (strpos($structure, '%listing%') === false) {
			return;
		}

		// Apply Safe Mode: Prepend listing base to the structure for rewrite rules
		if ($this->is_safe_mode_enabled()) {
			$listing_base = $this->get_listing_base();
			// Only prepend if the structure doesn't already start with the listing base
			if (strpos($structure, $listing_base . '/') !== 0) {
				$structure = $listing_base . '/' . $structure;
			}
		}

		// Convert structure to regex pattern and get listing group info
		$pattern_info = $this->structure_to_regex_pattern($structure);

		if (! empty($pattern_info['pattern']) && $pattern_info['listing_group'] > 0) {
			$listing_group = $pattern_info['listing_group'];

			// If structure contains both %listing% and %listing_id%, we need special handling
			if (strpos($structure, '%listing_id%') !== false) {
				// Add multiple rewrite rules to handle both slug and ID resolution
				$id_group = $pattern_info['listing_id_group'];

				// Rule 1: Try to match by listing slug first
				add_rewrite_rule(
					$pattern_info['pattern'],
					"index.php?listing=\$matches[$listing_group]",
					'top'
				);

				// Rule 2: If slug doesn't work, try by listing ID
				if ($id_group > 0) {
					add_rewrite_rule(
						$pattern_info['pattern'],
						"index.php?post_type=listing&p=\$matches[$id_group]",
						'top'
					);
				}
			} else {
				// Standard rule for slug-based URLs
				add_rewrite_rule(
					$pattern_info['pattern'],
					"index.php?listing=\$matches[$listing_group]",
					'top'
				);
			}
		}
	}

	/**
	 * Convert structure to regex pattern for rewrite rules
	 *
	 * @param string $structure The permalink structure
	 * @return array Array with 'pattern', 'listing_group', and 'listing_id_group' keys
	 */
	private function structure_to_regex_pattern($structure)
	{
		// Start with the structure
		$pattern = $structure;

		// Define token patterns - order matters for specificity
		$token_patterns = array(
			'%listing_category%' => '([^/]+)',
			'%region%'          => '([^/]+)',
			'%listing_type%'    => '([^/]+)',
			'%author%'          => '([^/]+)',
			'%year%'            => '([0-9]{4})',
			'%monthnum%'        => '([0-9]{1,2})',
			'%listing_id%'      => '([0-9]+)',
			'%listing%'         => '([^/]+)',  // Must be last for correct matching
		);

		// Track which capture groups correspond to %listing% and %listing_id%
		$listing_group = 0;
		$listing_id_group = 0;
		$group_count = 1;

		// Replace tokens with regex patterns
		foreach ($token_patterns as $token => $regex) {
			if (strpos($pattern, $token) !== false) {
				if ($token === '%listing%') {
					$listing_group = $group_count;
				} elseif ($token === '%listing_id%') {
					$listing_id_group = $group_count;
				}
				$pattern = str_replace($token, $regex, $pattern);
				$group_count++;
			}
		}

		// Add negative lookahead to prevent matching WordPress core URLs
		global $wp_rewrite;
		$author_base = $wp_rewrite->author_base ?: 'author';
		
		// Exclude author URLs and other core WordPress patterns
		$pattern = '^(?!' . preg_quote($author_base, '/') . '/)' . $pattern . '/?$';

		return array(
			'pattern' => $pattern,
			'listing_group' => $listing_group,
			'listing_id_group' => $listing_id_group
		);
	}

	/**
	 * Flush rewrite rules if needed
	 */
	public function flush_rewrite_rules_if_needed()
	{
		if (! $this->is_enabled()) {
			return;
		}

		$settings = $this->get_custom_permalink_settings();
		$current_structure = ! empty($settings['custom_structure']) ? $settings['custom_structure'] : '';
		$last_structure = get_transient('listeo_custom_permalink_structure');

		if ($current_structure !== $last_structure) {
			flush_rewrite_rules(false);
			set_transient('listeo_custom_permalink_structure', $current_structure, DAY_IN_SECONDS);

			// Ensure Dokan rewrite rules are refreshed if Dokan is active
			if (class_exists('WeDevs_Dokan')) {
				update_option('dokan_rewrite_rules_needs_flashing', 'yes');
			}

			// Trigger structure change hook for redirects
			do_action('listeo_custom_permalink_structure_changed', $last_structure, $current_structure);
		}
	}

	/**
	 * Handle permalink settings changes
	 *
	 * @param mixed $old_value Previous settings
	 * @param mixed $value     New settings
	 */
	public function on_permalink_settings_changed($old_value, $value)
	{
		// Decode JSON values
		$old_settings = is_string($old_value) ? json_decode($old_value, true) : $old_value;
		$new_settings = is_string($value) ? json_decode($value, true) : $value;

		// Check if custom permalink settings changed
		$old_custom = isset($old_settings['custom_structure']) ? $old_settings['custom_structure'] : '';
		$new_custom = isset($new_settings['custom_structure']) ? $new_settings['custom_structure'] : '';

		// Safety check: Prevent the problematic "%listing%" structure that conflicts with pages
		if ($new_custom === '%listing%') {
			// Force fallback to category-based structure to avoid conflicts
			$new_custom = '%listing_category%/%listing%';
			$new_settings['custom_structure'] = $new_custom;

			// Update the option with the safe structure
			update_option('listeo_core_permalinks', wp_json_encode($new_settings));

			// Add admin notice about the change
			add_action('admin_notices', function () {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				echo esc_html__('Listeo: Simple permalink structure has been changed to Category + Name structure to prevent conflicts with pages.', 'listeo-core');
				echo '</p></div>';
			});
		}

		// Check if custom permalinks were just enabled/disabled
		$old_enabled = isset($old_settings['custom_permalinks_enabled']) && $old_settings['custom_permalinks_enabled'] === '1';
		$new_enabled = isset($new_settings['custom_permalinks_enabled']) && $new_settings['custom_permalinks_enabled'] === '1';

		if ($old_custom !== $new_custom) {
			// Clear cached structure
			delete_transient('listeo_custom_permalink_structure');

			// Trigger structure change hook for redirects
			do_action('listeo_custom_permalink_structure_changed', $old_custom, $new_custom);

			// Schedule rewrite rules flush
			wp_schedule_single_event(time() + 10, 'listeo_flush_rewrite_rules');
		}

		// If custom permalinks were just enabled (not just structure changed)
		if (! $old_enabled && $new_enabled && ! empty($new_custom)) {
			// Generate redirects from default WordPress structure to custom structure
			if ($this->redirect_manager) {
				// Schedule redirect generation to avoid timeout during settings save
				wp_schedule_single_event(time() + 5, 'listeo_generate_default_redirects');

				// Hook the scheduled event
				if (! has_action('listeo_generate_default_redirects')) {
					add_action('listeo_generate_default_redirects', array($this, 'generate_default_redirects_callback'));
				}
			}
		}

		// If custom permalinks were just disabled (but there was a custom structure)
		if ($old_enabled && ! $new_enabled && ! empty($old_custom)) {
			// Store the old custom structure temporarily for reverse redirect generation
			set_transient('listeo_old_custom_structure_for_redirects', $old_custom, HOUR_IN_SECONDS);

			// Generate reverse redirects from custom structure back to default WordPress structure
			if ($this->redirect_manager) {
				// Schedule reverse redirect generation
				wp_schedule_single_event(time() + 5, 'listeo_generate_reverse_redirects');

				// Hook the scheduled event
				if (! has_action('listeo_generate_reverse_redirects')) {
					add_action('listeo_generate_reverse_redirects', array($this, 'generate_reverse_redirects_callback'));
				}
			}
		}
	}

	/**
	 * Callback for scheduled redirect generation
	 */
	public function generate_default_redirects_callback()
	{
		if ($this->redirect_manager) {
			$result = $this->redirect_manager->generate_redirects_from_default_structure();
		}
	}

	/**
	 * Callback for scheduled reverse redirect generation
	 */
	public function generate_reverse_redirects_callback()
	{
		if ($this->redirect_manager) {
			$result = $this->redirect_manager->generate_reverse_redirects_to_default();
		}
	}

	/**
	 * Check for emergency disable constant
	 */
	public function check_emergency_disable()
	{
		if (defined('LISTEO_DISABLE_CUSTOM_PERMALINKS') && LISTEO_DISABLE_CUSTOM_PERMALINKS) {
			$settings = $this->get_custom_permalink_settings();
			if (! empty($settings['custom_permalinks_enabled'])) {
				// Disable custom permalinks
				$raw_settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
				$raw_settings['custom_permalinks_enabled'] = false;
				update_option('listeo_core_permalinks', wp_json_encode($raw_settings));

				// Flush rewrite rules
				flush_rewrite_rules(false);

				// Add admin notice
				add_action('admin_notices', array($this, 'emergency_disable_notice'));
			}
		}
	}

	/**
	 * Show emergency disable notice
	 */
	public function emergency_disable_notice()
	{
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__('Listeo Custom Permalinks have been disabled via the LISTEO_DISABLE_CUSTOM_PERMALINKS constant.', 'listeo-core');
		echo '</p></div>';
	}

	/**
	 * Migrate problematic permalink structures that conflict with pages
	 */
	public function migrate_problematic_structures()
	{
		$settings = $this->get_custom_permalink_settings();
		$structure = ! empty($settings['custom_structure']) ? $settings['custom_structure'] : '';

		// Check if using the problematic "%listing%" structure
		if ($structure === '%listing%' && ! get_option('listeo_migrated_simple_permalink', false)) {
			// Update to safe structure
			$raw_settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
			$raw_settings['custom_structure'] = '%listing_category%/%listing%';
			update_option('listeo_core_permalinks', wp_json_encode($raw_settings));

			// Mark as migrated to prevent repeated migrations
			update_option('listeo_migrated_simple_permalink', true);

			// Flush rewrite rules to apply new structure
			flush_rewrite_rules(false);

			// Add admin notice about the migration
			add_action('admin_notices', function () {
				echo '<div class="notice notice-info is-dismissible"><p>';
				echo esc_html__('Listeo: Your Simple permalink structure has been automatically upgraded to Category + Name structure to prevent conflicts with pages. All existing URLs will redirect properly.', 'listeo-core');
				echo '</p></div>';
			});
		}
	}

	/**
	 * Get health status of custom permalinks
	 *
	 * @return array Status information
	 */
	public function get_health_status()
	{
		if (! $this->is_enabled()) {
			return array(
				'status' => 'disabled',
				'message' => __('Custom permalinks are disabled.', 'listeo-core'),
			);
		}

		$settings = $this->get_custom_permalink_settings();
		$structure = ! empty($settings['custom_structure']) ? $settings['custom_structure'] : '';

		if (empty($structure)) {
			return array(
				'status' => 'error',
				'message' => __('Custom permalink structure is empty.', 'listeo-core'),
			);
		}

		$validation = $this->validator->validate_structure($structure);

		if (true === $validation) {
			return array(
				'status' => 'healthy',
				'message' => __('Custom permalinks are working correctly.', 'listeo-core'),
			);
		} else {
			return array(
				'status' => 'error',
				'message' => implode(', ', $validation),
			);
		}
	}

	/**
	 * Ensure Dokan compatibility by forcing rewrite rules refresh
	 *
	 * @since 1.9.51
	 */
	public function ensure_dokan_compatibility()
	{
		// Check if Dokan is active
		if (class_exists('WeDevs_Dokan')) {
			// Force Dokan to re-register its rewrite rules
			update_option('dokan_rewrite_rules_needs_flashing', 'yes');

			// One-time cleanup: if this is the first time custom permalinks are enabled with Dokan
			$cleanup_done = get_option('listeo_dokan_permalink_cleanup_done', false);
			if (! $cleanup_done) {
				// Force a complete rewrite rules refresh
				delete_option('rewrite_rules');
				flush_rewrite_rules(true);
				update_option('listeo_dokan_permalink_cleanup_done', true);
			}
		}
	}

	/**
	 * Preserve Dokan query variables to ensure dashboard pages work correctly
	 * This hooks into dokan_dashboard_shortcode_query_vars filter
	 *
	 * @param array $query_vars The query vars from $wp->query_vars
	 * @return array
	 */
	public function preserve_dokan_query_vars($query_vars)
	{
		// Only process if on dashboard page
		if (! function_exists('dokan_is_seller_dashboard') || ! dokan_is_seller_dashboard()) {
			return $query_vars;
		}

		// Get the current request path
		global $wp;
		$request_path = trim($wp->request, '/');

		// Get Dokan's registered query vars (this includes both Lite and Pro pages)
		$dokan_pages = array();
		if (class_exists('WeDevs\Dokan\Rewrites')) {
			// Use Dokan's own query var system for maximum compatibility
			$dokan_pages = apply_filters('dokan_query_var_filter', array(
				'products',
				'new-product',
				'orders',
				'withdraw',
				'withdraw-requests',
				'reverse-withdrawal',
				'settings',
				'edit-account',
				'account-migration'
			));
		} else {
			// Fallback for older versions or if class doesn't exist
			$dokan_pages = array(
				'products',
				'orders',
				'withdraw',
				'settings',
				'reports',
				'reviews',
				'tools',
				'new-product',
				'edit',
				'booking',
				'support',
				'coupons',
				'staff',
				'followers',
				'withdraw-requests',
				'reverse-withdrawal',
				'edit-account',
				'account-migration'
			);
		}

		// Parse the request path to find sub-pages
		$path_parts = explode('/', $request_path);
		$found_subpage = false;

		// Handle query-string driven product edit pages explicitly
		if ( isset( $_GET['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$found_subpage         = true;
			$query_vars['products'] = true;
			$query_vars['edit']     = true;
		}

		// Look for Dokan sub-page identifiers in the URL path
		foreach ($path_parts as $part) {
			$clean_part = sanitize_key($part);
			if (in_array($clean_part, $dokan_pages)) {
				$found_subpage = true;
				$query_vars[$clean_part] = true;
			}
		}

		// Handle edit parameter for products (special case)
		if (in_array('edit', $path_parts)) {
			$found_subpage = true;
			$query_vars['edit'] = true;
		}

		// CRITICAL: For main dashboard page (no sub-pages), set 'page' query var
		// This tells Dokan to load the main dashboard template with analytics
		if (! $found_subpage) {
			$query_vars['page'] = '1'; // Any non-empty value works
		}

		return $query_vars;
	}

	/**
	 * Handle WordPress rewrite rules updates to ensure Dokan compatibility
	 */
	public function on_rewrite_rules_updated()
	{
		if (class_exists('WeDevs_Dokan') && $this->is_enabled()) {
			// Ensure Dokan gets a chance to re-register its rules after any rewrite changes
			update_option('dokan_rewrite_rules_needs_flashing', 'yes');
		}
	}

	/**
	 * Generate example URL with Safe Mode consideration
	 *
	 * @param string $base_example The base example without Safe Mode
	 * @return string Example URL with Safe Mode prefix if enabled
	 */
	private function generate_structure_example($base_example)
	{
		if ($this->is_safe_mode_enabled()) {
			$listing_base = $this->get_listing_base();
			if (strpos($base_example, $listing_base . '/') !== 0) {
				return $listing_base . '/' . $base_example;
			}
		}
		return $base_example;
	}

	/**
	 * Get available predefined structures
	 *
	 * @return array
	 */
	public function get_predefined_structures()
	{
		// Get the listing base for the default structure (use the proper method)
		$listing_base = $this->get_listing_base();

		return array(
			'default' => array(
				'label' => __('Default WordPress', 'listeo-core'),
				'description' => sprintf(__('Standard WordPress structure using %s base', 'listeo-core'), $listing_base),
				'example' => $listing_base . '/amazing-restaurant',
				'is_default' => true,
			),

			'%listing_category%/%listing%' => array(
				'label' => __('Category + Name', 'listeo-core'),
				'description' => __('Category followed by listing name', 'listeo-core'),
				'example' => $this->generate_structure_example('restaurants/amazing-restaurant'),
			),
			'%region%/%listing%' => array(
				'label' => __('Region + Name', 'listeo-core'),
				'description' => __('Region followed by listing name', 'listeo-core'),
				'example' => $this->generate_structure_example('new-york/amazing-restaurant'),
			),
			'%listing_category%/%region%/%listing%' => array(
				'label' => __('Category + Region + Name', 'listeo-core'),
				'description' => __('Category, region, then listing name', 'listeo-core'),
				'example' => $this->generate_structure_example('restaurants/new-york/amazing-restaurant'),
			),
			'%year%/%monthnum%/%listing%' => array(
				'label' => __('Date-based', 'listeo-core'),
				'description' => __('Year and month, then listing name', 'listeo-core'),
				'example' => $this->generate_structure_example('2025/01/amazing-restaurant'),
			),
			'%listing_type%/%listing%' => array(
				'label' => __('Type + Name', 'listeo-core'),
				'description' => __('Listing type followed by name', 'listeo-core'),
				'example' => $this->generate_structure_example('service/amazing-restaurant'),
			),
			'%author%/%listing%' => array(
				'label' => __('Author + Name', 'listeo-core'),
				'description' => __('Author name followed by listing', 'listeo-core'),
				'example' => $this->generate_structure_example('john-smith/amazing-restaurant'),
			),
			'%listing_id%/%listing%' => array(
				'label' => __('ID-based', 'listeo-core'),
				'description' => __('Listing ID followed by name', 'listeo-core'),
				'example' => $this->generate_structure_example('123/amazing-restaurant'),
			),
		);
	}
}

// Initialize the custom permalink manager
add_action('init', function () {
	// Only initialize if not disabled and classes exist
	if (! defined('LISTEO_DISABLE_CUSTOM_PERMALINKS') || ! LISTEO_DISABLE_CUSTOM_PERMALINKS) {
		Listeo_Core_Custom_Permalink_Manager::instance();
	}
}, 5);
