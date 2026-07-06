<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Listeo  class.
 */
class Listeo_Core_Post_Types {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26
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
		// PRIORITY 1: Protect WordPress core, WooCommerce, and Dokan URLs before adding Listeo rewrite rules
		add_filter( 'rewrite_rules_array', array( $this, 'protect_core_urls' ), 1 );
		add_action( 'init', array( $this, 'enable_custom_permalink_settings' ), 0 );
		
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		add_action( 'manage_listing_posts_custom_column', array( $this, 'custom_columns' ), 12 );
		add_filter( 'manage_edit-listing_columns', array( $this, 'columns' ) );
		add_filter('manage_edit-listing_sortable_columns', array($this, 'sortable_columns'));
		add_action('pre_get_posts', array($this, 'sort_columns_query'));

		add_action( 'pending_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'pending_payment_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'preview_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'draft_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'auto-draft_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'expired_to_publish', array( $this, 'set_expiry' ) );

		add_filter( 'wp_insert_post_data', array( $this, 'default_comments_on' ) );
		add_action( 'save_post', array( $this,'save_availibilty_calendar'), 20, 3 );
		//add_action( 'save_post', array( $this,'save_as_product'), 10, 3 );
		add_action( 'save_post', array( $this,'save_event_timestamp'), 10, 3 );
		
		add_action('admin_footer-edit.php',array( $this, 'listeo_status_into_inline_edit'));
		add_filter( 'display_post_states', array( $this, 'listeo_display_status_label' ),10, 2);

		//featured default value

		add_action('save_post_listing', array( $this, 'set_default_featured'));
		// Note: Google reviews transient is only cleared when Place ID changes (handled in class-listeo-core-submit.php and class-listeo-core-admin.php)



		add_action( 'listeo_core_check_for_expired_listings', array( $this, 'check_for_expired' ) );
		add_action( 'listeo_core_check_for_expiring_listings', array( $this, 'check_for_expiring' ) );

		add_action( 'admin_init', array( $this, 'approve_listing' ) );
	add_action( 'admin_action_listeo_reject_listing', array( $this, 'reject_listing' ) );
		add_action( 'admin_notices', array( $this, 'action_notices' ) );

		add_action( 'bulk_actions-edit-listing', array( $this, 'add_bulk_actions' ) );
		add_action( 'handle_bulk_actions-edit-listing', array( $this, 'do_bulk_actions' ), 10, 3 );

		add_filter( 'manage_edit-listing_category_columns', array( $this, 'add_icon_column' ) );
		add_filter( 'manage_listing_category_custom_column', array( $this, 'add_icon_column_content' ), 10, 3 );

		add_filter( 'manage_edit-listing_category_columns', array( $this, 'add_assigned_features_column' ) );
		add_filter( 'manage_listing_category_custom_column', array( $this, 'add_assigned_features_content' ), 10, 3 );

		add_action( 'wp_insert_post', array( $this, 'set_default_avg_rating_new_post')) ;
		add_action( 'before_delete_post', array($this, 'remove_product_on_listing_remove' ));
		//add_action( 'before_delete_post', array($this, 'remove_gallery_on_listing_remove' ));
		

		// Only enable legacy region permalinks if custom permalinks are disabled
		// This prevents the legacy system from interfering when custom permalinks are not enabled
		if(get_option('listeo_region_in_links' ) && !get_option('listeo_enable_custom_permalinks', false)) {

			add_action( 'wp_loaded', array( $this, 'add_listings_permastructure' ) );
			add_filter( 'post_type_link', array( $this,'listing_permalinks' ), 10, 2 );
			add_filter( 'term_link', array( $this,'add_term_parents_to_permalinks'), 10, 2 );

		}

		// Combined taxonomy URLs functionality
		if(get_option('listeo_combined_taxonomy_urls')) {
			add_action('init', array($this, 'add_combined_taxonomy_rewrite_rules'));
			add_filter('query_vars', array($this, 'add_combined_taxonomy_query_vars'));
			add_action('pre_get_posts', array($this, 'modify_combined_taxonomy_query'));
			//add_filter('template_include', array($this, 'combined_taxonomy_template_include'), 20);
			
			// Title filters
			add_filter('document_title_parts', array($this, 'combined_taxonomy_document_title'));
			add_filter('get_the_archive_title', array($this, 'combined_taxonomy_archive_title'));
			add_filter('wp_title', array($this, 'combined_taxonomy_wp_title'), 10, 3);
		}
		add_filter('add_menu_classes', array( $this,'show_pending_number'));

		add_action('wp_head', array($this, 'add_local_business_schema'), 20);

	}
	function listeo_status_into_inline_edit() { // ultra-simple example

		echo "<script>
		jQuery(document).ready( function() {
			jQuery( 'select[name=\"_status\"]' ).append( '<option value=\"preview\">Preview</option>' );
			jQuery( 'select[name=\"_status\"]' ).append( '<option value=\"expired\">Expired</option>' );
			jQuery( 'select[name=\"_status\"]' ).append( '<option value=\"pending_payment\">Pending Payment</option>' );
		});
		</script>";
	}

	function listeo_display_status_label( $statuses, $post ) {

		// Bail if WordPress called the filter without a valid post (PHP 8 null-safety).
		if ( ! $post instanceof WP_Post ) {
			return $statuses;
		}

		$post_type = get_post_type( $post );

		if ( 'listing' === $post_type ) {

			if ( get_query_var( 'post_status' ) !== 'pending_payment' && $post->post_status === 'pending_payment' ) {
				return array( 'Pending Payment' );
			}
			if ( get_query_var( 'post_status' ) !== 'expired' && $post->post_status === 'expired' ) {
				return array( 'Expired' );
			}
			if ( get_query_var( 'post_status' ) !== 'preview' && $post->post_status === 'preview' ) {
				return array( 'Preview' );
			}
			if ( get_query_var( 'post_status' ) !== 'rejected' && $post->post_status === 'rejected' ) {
				return array( 'Rejected' );
			}
		}

		if ( 'page' === $post_type && function_exists( 'listeo_core_get_dashboard_pages_list' ) ) {
			$listeo_pages = listeo_core_get_dashboard_pages_list();

			foreach ( $listeo_pages as $page_key => $page_data ) {
				$page_id = get_option( $page_data['option'] );
				if ( $page_id && (int) $post->ID === (int) $page_id ) {
					$statuses[ 'listeo_' . $page_key ] = 'Listeo: ' . $page_data['title'];
					break;
				}
			}
		}

		return $statuses;
	}
	 

	function set_default_featured($post_id) {
	   add_post_meta($post_id, '_featured', '0', true);
	}

	function delete_google_reviews($post_id) {
	   delete_transient( 'listeo_reviews_'.$post_id );
	}

	function show_pending_number($menu) {
	    $types = array("listing");
	    $status = "pending";
	    foreach($types as $type) {
	        $num_posts = wp_count_posts($type, 'readable');
	        $pending_count = 0;
	        if (!empty($num_posts->$status)) $pending_count = $num_posts->$status;
	 
	        if ($type == 'post') {
	            $menu_str = 'edit.php';
	        } else {
	            $menu_str = 'edit.php?post_type=' . $type;
	        }
	 
	        foreach( $menu as $menu_key => $menu_data ) {
	            if( $menu_str != $menu_data[2] )
	                continue;
	            $menu[$menu_key][0] .= " <span class='update-plugins count-$pending_count'><span class='plugin-count'>"
	                . number_format_i18n($pending_count)
	                . '</span></span>';
	        }
	    }
	    return $menu;
	}
	/**
	 * Get the permalink settings directly from the option.
	 *
	 * @return array Permalink settings option.
	 */
	public static function get_raw_permalink_settings() {
		/**
		 * Option `wpjm_permalinks` was renamed to match other options in 1.32.0.
		 *
		 * Reference to the old option and support for non-standard plugin updates will be removed in 1.34.0.
		 */
		$legacy_permalink_settings = '[]';
		if ( false !== get_option( 'listeo_permalinks', false ) ) {
			$legacy_permalink_settings = wp_json_encode( get_option( 'listeo_permalinks', array() ) );
			delete_option( 'listeo_permalinks' );
		}

		return (array) json_decode( get_option( 'listeo_core_permalinks', $legacy_permalink_settings ), true );
	}

	/**
	 * Resolve a translated rewrite slug using the SITE locale, not the
	 * current user's profile locale.
	 *
	 * Rewrite rules are persisted in wp_options.rewrite_rules and must be
	 * deterministic across contexts. If they are flushed from admin while
	 * the admin user's profile language differs from the site language,
	 * the cached rules end up using the user's slug while frontend term
	 * URLs are built from the site's slug, producing 404s.
	 *
	 * Always go through this helper when building taxonomy / post-type
	 * rewrite slugs from a `_x()` string.
	 *
	 * @param string $text    Untranslated source string.
	 * @param string $context Translation context.
	 * @param string $domain  Text domain.
	 * @return string
	 */
	public static function get_site_locale_slug( $text, $context, $domain = 'listeo_core' ) {
		$switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( get_locale() ) : false;
		$slug = _x( $text, $context, $domain );
		if ( $switched ) {
			restore_previous_locale();
		}
		return $slug;
	}

	/**
	 * Retrieves permalink settings.
	 *
	 * @see https://github.com/woocommerce/woocommerce/blob/3.0.8/includes/wc-core-functions.php#L1573
	 * @since 1.28.0
	 * @return array
	 */
	public static function get_permalink_structure() {
		// Switch to the site's default locale, bypassing the active user's locale.
		// Must trigger from `init` onwards (not just admin_init) so taxonomies
		// register with site-locale slugs that match flushed rewrite rules.
		$switched_locale = false;
		if ( function_exists( 'switch_to_locale' ) && is_admin() ) {
			$switched_locale = switch_to_locale( get_locale() );
		}

		$permalink_settings = self::get_raw_permalink_settings();

		// First-time activations will get this cleared on activation.
		if ( ! array_key_exists( 'listings_archive', $permalink_settings ) ) {
			// Create entry to prevent future checks.
			$permalink_settings['listings_archive'] = '';
			
				// This isn't the first activation and the theme supports it. Set the default to legacy value.
			$permalink_settings['listings_archive'] = _x( 'listings', 'Post type archive slug - resave permalinks after changing this', 'listeo_core' );
			
			update_option( 'listeo_core_permalinks', wp_json_encode( $permalink_settings ) );
		}

		$permalinks         = wp_parse_args(
			$permalink_settings,
			array(
				'listing_base'      => '',
				'category_base' => '',
				'listings_archive'  => '',
			)
		);

		// Ensure rewrite slugs are set. Use legacy translation options if not.
		$permalinks['listing_rewrite_slug']          = untrailingslashit( empty( $permalinks['listing_base'] ) ? _x( 'listing', 'Job permalink - resave permalinks after changing this', 'listeo_core' ) : $permalinks['listing_base'] );
		$permalinks['category_rewrite_slug']     = untrailingslashit( empty( $permalinks['category_base'] ) ? _x( 'listing-category', 'Listing category slug - resave permalinks after changing this', 'listeo_core' ) : $permalinks['category_base'] );
		
		$permalinks['listings_archive_rewrite_slug'] = untrailingslashit( empty( $permalinks['listings_archive'] ) ? 'listings' : $permalinks['listings_archive'] );

		// Restore the original locale.
		if ( $switched_locale && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}
		return $permalinks;
	}


	public function remove_product_on_listing_remove($postid) {
		$product_id = get_post_meta($postid,'product_id',true);
		
		wp_delete_post($product_id, true);
	}


	public function remove_gallery_on_listing_remove($postid) {
		$gallery = get_post_meta( $postid, '_gallery', true );

		if(!empty($gallery)) : 
			foreach ( (array) $gallery as $attachment_id => $attachment_url ) {
				wp_delete_attachment($attachment_id);
			}
		endif;
		
	}

	function save_availibilty_calendar( $post_ID, $post, $update ) {


		// Verify if this is an auto save routine
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
			$bookings = new Listeo_Core_Bookings_Calendar;
			
			// set array only with dates when listing is not avalible
			$avaliabity = get_post_meta($post_ID, '_availability', true);
			

			if($avaliabity) {
			 	
					
				$dates = array_filter( explode( "|", $avaliabity['dates'] ) );
				
				if ( ! empty( $dates ) ) $bookings :: update_reservations( $post_ID, $dates );

			// set array only with dates when we have special prices for booking
				$special_prices = json_decode( $avaliabity['price'], true );
		
				if ( ! empty( $special_prices ) ) $bookings :: update_special_prices( $post_ID, $special_prices );
			}
	
	}
	
	function save_event_timestamp( $post_ID, $post, $update ) {
		// Only process listing posts
		if ( $post->post_type !== 'listing' ) {
			return;
		}

		// Check if this is an event listing (check both booking type and listing type)
		$booking_type = listeo_get_booking_type($post_ID);
		$listing_type = get_post_meta($post_ID, '_listing_type', true);
		
		if ( $booking_type !== 'event' && $listing_type !== 'event' ) {
			return;
		}

		// Process event start date
		$event_date = get_post_meta($post_ID, '_event_date', true);
		if ( !empty($event_date) ) {
			$this->create_event_timestamp($post_ID, $event_date, '_event_date_timestamp');
		}

		// Process event end date
		$event_date_end = get_post_meta($post_ID, '_event_date_end', true);
		if ( !empty($event_date_end) ) {
			$this->create_event_timestamp($post_ID, $event_date_end, '_event_date_end_timestamp');
		}
	}

	/**
	 * Helper function to create event timestamps with proper error handling
	 */
	private function create_event_timestamp( $post_ID, $date_string, $timestamp_meta_key ) {
		if ( empty($date_string) ) {
			error_log("Listeo Event Timestamp: Empty date string for post {$post_ID}, field {$timestamp_meta_key}");
			return false;
		}

		$timestamp = self::parse_event_date_to_timestamp($date_string);

		if ($timestamp === false) {
			error_log("Listeo Event Timestamp: Unable to convert date '{$date_string}' for post {$post_ID} (field {$timestamp_meta_key}) into timestamp");
			return false;
		}

		$result = update_post_meta($post_ID, $timestamp_meta_key, $timestamp);

		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log("Listeo Event Timestamp: Stored {$timestamp_meta_key} = {$timestamp} for post {$post_ID}");
		}

		return $result;
	}

	public static function parse_event_date_to_timestamp( $date_string ) {
		$date_string = trim((string) $date_string);
		if ($date_string === '') {
			return false;
		}

		$formats_to_try = self::build_event_date_formats($date_string);

		foreach ($formats_to_try as $format) {
			$date_obj = DateTime::createFromFormat($format, $date_string);
			if ($date_obj instanceof DateTime) {
				return $date_obj->getTimestamp();
			}
		}

		// Final fallback using strtotime with normalised separators
		$normalised_string = str_replace(array('.', '/', '\\'), '-', $date_string);
		$timestamp = strtotime($normalised_string);
		if ($timestamp !== false) {
			return $timestamp;
		}

		$timestamp = strtotime($date_string);
		return $timestamp !== false ? $timestamp : false;
	}

	private static function build_event_date_formats( $date_string ) {
		$formats = array();

		if (function_exists('listeo_date_time_wp_format_php')) {
			$wp_format = listeo_date_time_wp_format_php();
			if (!empty($wp_format)) {
				$normalised_format = self::apply_separator_to_format($wp_format, $date_string);
				$formats[] = $normalised_format;
				$formats[] = $normalised_format . ' H:i';
				$formats[] = $normalised_format . ' H:i:s';
			}
		}

		$formats = array_merge($formats, self::generate_generic_event_formats());

		return array_values(array_unique(array_filter($formats)));
	}

	private static function apply_separator_to_format( $format, $date_string ) {
		$date_parts = preg_split('/\s+/', trim($date_string));
		$date_only = $date_parts[0];
		$separator = self::detect_date_separator($date_only);

		if (!$separator) {
			return $format;
		}

		return str_replace(array('-', '/', '.'), $separator, $format);
	}

	private static function detect_date_separator( $date_only ) {
		foreach (array('.', '/', '-') as $sep) {
			if (strpos($date_only, $sep) !== false) {
				return $sep;
			}
		}

		return null;
	}

	private static function generate_generic_event_formats() {
		$base_patterns = array('Y-m-d', 'd-m-Y', 'm-d-Y');
		$separators   = array('-', '/', '.');
		$time_suffixes = array('', ' H:i', ' H:i:s');
		$formats = array();

		foreach ($base_patterns as $pattern) {
			foreach ($separators as $separator) {
				$base = str_replace('-', $separator, $pattern);
				foreach ($time_suffixes as $suffix) {
					$formats[] = $base . $suffix;
				}
			}
		}

		return $formats;
	}

	/**
	 * register_post_types function.
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_types() {
	/*
		if ( post_type_exists( "listing" ) )
			return;*/

		// Custom admin capability
		$admin_capability = 'edit_listings';
		$permalink_structure = self::get_permalink_structure();
		
		// Get custom types settings to check if default taxonomies should be registered
		$should_register_default_taxonomies = $this->get_default_taxonomy_settings();
				
	
		// Set labels and localize them
	
		$listing_name		= apply_filters( 'listeo_core_taxonomy_listing_name', __( 'Listings', 'listeo_core' ) );
		$listing_singular	= apply_filters( 'listeo_core_taxonomy_listing_singular', __( 'Listing', 'listeo_core' ) );
	
		register_post_type( "listing",
			apply_filters( "register_post_type_listing", array(
				'labels' => array(
					'name'					=> $listing_name,
					'singular_name' 		=> $listing_singular,
					'menu_name'             => esc_html__( 'Listings', 'listeo_core' ),
					'all_items'             => sprintf( esc_html__( 'All %s', 'listeo_core' ), $listing_name ),
					'add_new' 				=> esc_html__( 'Add New', 'listeo_core' ),
					'add_new_item' 			=> sprintf( esc_html__( 'Add %s', 'listeo_core' ), $listing_singular ),
					'edit' 					=> esc_html__( 'Edit', 'listeo_core' ),
					'edit_item' 			=> sprintf( esc_html__( 'Edit %s', 'listeo_core' ), $listing_singular ),
					'new_item' 				=> sprintf( esc_html__( 'New %s', 'listeo_core' ), $listing_singular ),
					'view' 					=> sprintf( esc_html__( 'View %s', 'listeo_core' ), $listing_singular ),
					'view_item' 			=> sprintf( esc_html__( 'View %s', 'listeo_core' ), $listing_singular ),
					'search_items' 			=> sprintf( esc_html__( 'Search %s', 'listeo_core' ), $listing_name ),
					'not_found' 			=> sprintf( esc_html__( 'No %s found', 'listeo_core' ), $listing_name ),
					'not_found_in_trash' 	=> sprintf( esc_html__( 'No %s found in trash', 'listeo_core' ), $listing_name ),
					'parent' 				=> sprintf( esc_html__( 'Parent %s', 'listeo_core' ), $listing_singular ),
				),
				'description' => sprintf( esc_html__( 'This is where you can create and manage %s.', 'listeo_core' ), $listing_name ),
				'public' 				=> true,
				'show_ui' 				=> true,
				'show_in_rest' 			=> true,
				'capability_type' 		=> array( 'listing', 'listings' ),
				'map_meta_cap'          => true,
				'publicly_queryable' 	=> true,
				'exclude_from_search' 	=> false,
				'hierarchical' 			=> false,
				'menu_icon'           => 'dashicons-admin-multisite',
				'rewrite' 				=> array(
						'slug'       => $permalink_structure['listing_rewrite_slug'],
						'with_front' => true,
						'feeds'      => true,
						'pages'      => true
					),
				'query_var' 			=> true,
				'supports' 				=> array( 'title', 'author','editor', 'custom-fields', 'publicize', 'thumbnail','comments' ),
				'has_archive' 			=> $permalink_structure['listings_archive_rewrite_slug'],
				'show_in_nav_menus' 	=> true
			) )
		);


		register_post_status( 'preview', array(
			'label'                     => _x( 'Preview', 'post status', 'listeo_core' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Preview <span class="count">(%s)</span>', 'Preview <span class="count">(%s)</span>', 'listeo_core' ),
		) );

		register_post_status( 'expired', array(
			'label'                     => _x( 'Expired', 'post status', 'listeo_core' ),
			'public'                    => false,
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'listeo_core' ),
		) );

		register_post_status( 'pending_payment', array(
			'label'                     => _x( 'Pending Payment', 'post status', 'listeo_core' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Pending Payment <span class="count">(%s)</span>', 'Pending Payment <span class="count">(%s)</span>', 'listeo_core' ),
		) );

	register_post_status( 'rejected', array(
		'label'                     => _x( 'Rejected', 'post status', 'listeo_core' ),
		'public'                    => false,
		'exclude_from_search'       => true,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'listeo_core' ),
	) );


		
		// Register taxonomy "Listing Categry"
		$singular  = __( 'Category', 'listeo_core' );
		$plural    = __( 'Categories', 'listeo_core' );	
		$rewrite   = array(
			'slug'         => $permalink_structure['category_rewrite_slug'],
			'with_front'   => false,
			'hierarchical' => true
		);
		$public    = true;
		register_taxonomy( "listing_category",
			apply_filters( 'register_taxonomy_listing_category_object_type', array( 'listing' ) ),
       	 	apply_filters( 'register_taxonomy_listing_category_args', array(
	            'hierarchical' 			=> true,
	            /*'update_count_callback' => '_update_post_term_count',*/
	            'label' 				=> $plural,
	            'show_in_rest' => true,
	            'labels' => array(
					'name'              => $plural,
					'singular_name'     => $singular,
					'menu_name'         => ucwords( $plural ),
					'search_items'      => sprintf( __( 'Search %s', 'listeo_core' ), $plural ),
					'all_items'         => sprintf( __( 'All %s', 'listeo_core' ), $plural ),
					'parent_item'       => sprintf( __( 'Parent %s', 'listeo_core' ), $singular ),
					'parent_item_colon' => sprintf( __( 'Parent %s:', 'listeo_core' ), $singular ),
					'edit_item'         => sprintf( __( 'Edit %s', 'listeo_core' ), $singular ),
					'update_item'       => sprintf( __( 'Update %s', 'listeo_core' ), $singular ),
					'add_new_item'      => sprintf( __( 'Add New %s', 'listeo_core' ), $singular ),
					'new_item_name'     => sprintf( __( 'New %s Name', 'listeo_core' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'show_tagcloud'			=> false,
	            'public' 	     		=> $public,
	            /*'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),*/
	            'rewrite' 				=> $rewrite,
	        ) )
	    );	


// Register taxonomy "Events Categry"
		if ($should_register_default_taxonomies['event']) {
			$singular  = __( 'Event Category', 'listeo_core' );
			$plural    = __( 'Events Categories', 'listeo_core' );	
			$rewrite   = array(
				'slug'         => self::get_site_locale_slug( 'events-category', 'Event Category slug - resave permalinks after changing this' ),
				'with_front'   => false,
				'hierarchical' => false
			);
			$public    = true;
			register_taxonomy( "event_category",
				apply_filters( 'register_taxonomy_event_category_object_type', array( 'listing' ) ),
	       	 	apply_filters( 'register_taxonomy_event_category_args', array(
		            'hierarchical' 			=> true,
		            /*'update_count_callback' => '_update_post_term_count',*/
		            'label' 				=> $plural,
		            'labels' => array(
						'name'              => $plural,
						'singular_name'     => $singular,
						'menu_name'         => ucwords( $plural ),
						'search_items'      => sprintf( __( 'Search %s', 'listeo_core' ), $plural ),
						'all_items'         => sprintf( __( 'All %s', 'listeo_core' ), $plural ),
						'parent_item'       => sprintf( __( 'Parent %s', 'listeo_core' ), $singular ),
						'parent_item_colon' => sprintf( __( 'Parent %s:', 'listeo_core' ), $singular ),
						'edit_item'         => sprintf( __( 'Edit %s', 'listeo_core' ), $singular ),
						'update_item'       => sprintf( __( 'Update %s', 'listeo_core' ), $singular ),
						'add_new_item'      => sprintf( __( 'Add New %s', 'listeo_core' ), $singular ),
						'new_item_name'     => sprintf( __( 'New %s Name', 'listeo_core' ),  $singular )
	            	),
		            'show_ui' 				=> true,
		            'show_in_rest' => true,
		            'show_tagcloud'			=> false,
		            'public' 	     		=> $public,
		            /*'capabilities'			=> array(
		            	'manage_terms' 		=> $admin_capability,
		            	'edit_terms' 		=> $admin_capability,
		            	'delete_terms' 		=> $admin_capability,
		            	'assign_terms' 		=> $admin_capability,
		            ),*/
		            'rewrite' 				=> $rewrite,
		        ) )
		    );
		}	
// Register taxonomy "Service Categry"
		if ($should_register_default_taxonomies['service']) {
			$singular  = __( 'Service Category', 'listeo_core' );
			$plural    = __( 'Service Categories', 'listeo_core' );	
			$rewrite   = array(
				'slug'         => self::get_site_locale_slug( 'service-category', 'Service Category slug - resave permalinks after changing this' ),
				'with_front'   => false,
				'hierarchical' => false
			);
			$public    = true;
			register_taxonomy( "service_category",
			apply_filters( 'register_taxonomy_service_category_object_type', array( 'listing' ) ),
       	 	apply_filters( 'register_taxonomy_service_category_args', array(
	            'hierarchical' 			=> true,
	            /*'update_count_callback' => '_update_post_term_count',*/
	            'label' 				=> $plural,
	            'labels' => array(
					'name'              => $plural,
					'singular_name'     => $singular,
					'menu_name'         => ucwords( $plural ),
					'search_items'      => sprintf( __( 'Search %s', 'listeo_core' ), $plural ),
					'all_items'         => sprintf( __( 'All %s', 'listeo_core' ), $plural ),
					'parent_item'       => sprintf( __( 'Parent %s', 'listeo_core' ), $singular ),
					'parent_item_colon' => sprintf( __( 'Parent %s:', 'listeo_core' ), $singular ),
					'edit_item'         => sprintf( __( 'Edit %s', 'listeo_core' ), $singular ),
					'update_item'       => sprintf( __( 'Update %s', 'listeo_core' ), $singular ),
					'add_new_item'      => sprintf( __( 'Add New %s', 'listeo_core' ), $singular ),
					'new_item_name'     => sprintf( __( 'New %s Name', 'listeo_core' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'show_in_rest' => true,
	            'show_tagcloud'			=> false,
	            'public' 	     		=> $public,
	            /*'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),*/
	            'rewrite' 				=> $rewrite,
	        ) )
	    );	
		}
// Register taxonomy "Rental Categry"
		if ($should_register_default_taxonomies['rental']) {
			$singular  = __( 'Rental Category', 'listeo_core' );
			$plural    = __( 'Rentals Categories', 'listeo_core' );	
			$rewrite   = array(
				'slug'         => self::get_site_locale_slug( 'rental-category', 'Rental Category slug - resave permalinks after changing this' ),
				'with_front'   => false,
				'hierarchical' => false
		);
		$public    = true;
		register_taxonomy( "rental_category",
			apply_filters( 'register_taxonomy_rental_category_object_type', array( 'listing' ) ),
       	 	apply_filters( 'register_taxonomy_rental_category_args', array(
	            'hierarchical' 			=> true,
	            /*'update_count_callback' => '_update_post_term_count',*/
	            'label' 				=> $plural,
	            'labels' => array(
					'name'              => $plural,
					'singular_name'     => $singular,
					'menu_name'         => ucwords( $plural ),
					'search_items'      => sprintf( __( 'Search %s', 'listeo_core' ), $plural ),
					'all_items'         => sprintf( __( 'All %s', 'listeo_core' ), $plural ),
					'parent_item'       => sprintf( __( 'Parent %s', 'listeo_core' ), $singular ),
					'parent_item_colon' => sprintf( __( 'Parent %s:', 'listeo_core' ), $singular ),
					'edit_item'         => sprintf( __( 'Edit %s', 'listeo_core' ), $singular ),
					'update_item'       => sprintf( __( 'Update %s', 'listeo_core' ), $singular ),
					'add_new_item'      => sprintf( __( 'Add New %s', 'listeo_core' ), $singular ),
					'new_item_name'     => sprintf( __( 'New %s Name', 'listeo_core' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'show_in_rest' => true,
	            'show_tagcloud'			=> false,
	            'public' 	     		=> $public,
	            /*'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),*/
	            'rewrite' 				=> $rewrite,
	        ) )
	    );	
		}
// Register taxonomy "classifieds Categry"
		if ($should_register_default_taxonomies['classifieds']) {
			$singular  = __( 'Classifieds Category', 'listeo_core' );
			$plural    = __( 'Classifieds Categories', 'listeo_core' );	
			$rewrite   = array(
				'slug'         => self::get_site_locale_slug( 'classifieds-category', 'Classifieds Category slug - resave permalinks after changing this' ),
				'with_front'   => false,
				'hierarchical' => false
			);
			$public    = true;
		register_taxonomy( "classifieds_category",
			apply_filters( 'register_taxonomy_classifieds_category_object_type', array( 'listing' ) ),
       	 	apply_filters( 'register_taxonomy_classifieds_category_args', array(
	            'hierarchical' 			=> true,
	            /*'update_count_callback' => '_update_post_term_count',*/
	            'label' 				=> $plural,
	            'labels' => array(
					'name'              => $plural,
					'singular_name'     => $singular,
					'menu_name'         => ucwords( $plural ),
					'search_items'      => sprintf( __( 'Search %s', 'listeo_core' ), $plural ),
					'all_items'         => sprintf( __( 'All %s', 'listeo_core' ), $plural ),
					'parent_item'       => sprintf( __( 'Parent %s', 'listeo_core' ), $singular ),
					'parent_item_colon' => sprintf( __( 'Parent %s:', 'listeo_core' ), $singular ),
					'edit_item'         => sprintf( __( 'Edit %s', 'listeo_core' ), $singular ),
					'update_item'       => sprintf( __( 'Update %s', 'listeo_core' ), $singular ),
					'add_new_item'      => sprintf( __( 'Add New %s', 'listeo_core' ), $singular ),
					'new_item_name'     => sprintf( __( 'New %s Name', 'listeo_core' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'show_in_rest' => true,
	            'show_tagcloud'			=> false,
	            'public' 	     		=> $public,
	            /*'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),*/
	            'rewrite' 				=> $rewrite,
	        ) )
	    );	
		}

	    // Register taxonomy "Features"
		$singular  = __( 'Feature', 'listeo_core' );
		$plural    = __( 'Features', 'listeo_core' );	
		$rewrite   = array(
			'slug'         => self::get_site_locale_slug( 'listing-feature', 'Feature slug - resave permalinks after changing this' ),
			'with_front'   => false,
			'hierarchical' => false
		);
		$public    = true;
		register_taxonomy( "listing_feature",
			apply_filters( 'register_taxonomy_listing_features_object_type', array( 'listing' ) ),
       	 	apply_filters( 'register_taxonomy_listing_features_args', array(
	            'hierarchical' 			=> true,
	            /*'update_count_callback' => '_update_post_term_count',*/
	            'label' 				=> $plural,
	            'labels' => array(
					'name'              => $plural,
					'singular_name'     => $singular,
					'menu_name'         => ucwords( $plural ),
					'search_items'      => sprintf( __( 'Search %s', 'listeo_core' ), $plural ),
					'all_items'         => sprintf( __( 'All %s', 'listeo_core' ), $plural ),
					'parent_item'       => sprintf( __( 'Parent %s', 'listeo_core' ), $singular ),
					'parent_item_colon' => sprintf( __( 'Parent %s:', 'listeo_core' ), $singular ),
					'edit_item'         => sprintf( __( 'Edit %s', 'listeo_core' ), $singular ),
					'update_item'       => sprintf( __( 'Update %s', 'listeo_core' ), $singular ),
					'add_new_item'      => sprintf( __( 'Add New %s', 'listeo_core' ), $singular ),
					'new_item_name'     => sprintf( __( 'New %s Name', 'listeo_core' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'show_in_rest' => true,
	            'show_tagcloud'			=> false,
	            'public' 	     		=> $public,
	            /*'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),*/
	            'rewrite' 				=> $rewrite,
	        ) )
	    );		

	    // Register taxonomy "Region"
		$singular  = __( 'Region', 'listeo_core' );
		$plural    = __( 'Regions', 'listeo_core' );
		$rewrite   = array(
			'slug'         => self::get_site_locale_slug( 'region', 'Region slug - resave permalinks after changing this' ),
			'with_front'   => true,
			'hierarchical' => (bool) get_option('listeo_region_hierarchical_permalinks', false)
		);
		$public    = true;
		register_taxonomy( "region",
			apply_filters( 'register_taxonomy_region_object_type', array( 'listing' ) ),
       	 	apply_filters( 'register_taxonomy_region_args', array(
	            'hierarchical' 			=> true,
	            'update_count_callback' => '_update_post_term_count',
	            'label' 				=> $plural,
	            'labels' => array(
					'name'              => $plural,
					'singular_name'     => $singular,
					'menu_name'         => ucwords( $plural ),
					'search_items'      => sprintf( __( 'Search %s', 'listeo_core' ), $plural ),
					'all_items'         => sprintf( __( 'All %s', 'listeo_core' ), $plural ),
					'parent_item'       => sprintf( __( 'Parent %s', 'listeo_core' ), $singular ),
					'parent_item_colon' => sprintf( __( 'Parent %s:', 'listeo_core' ), $singular ),
					'edit_item'         => sprintf( __( 'Edit %s', 'listeo_core' ), $singular ),
					'update_item'       => sprintf( __( 'Update %s', 'listeo_core' ), $singular ),
					'add_new_item'      => sprintf( __( 'Add New %s', 'listeo_core' ), $singular ),
					'new_item_name'     => sprintf( __( 'New %s Name', 'listeo_core' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'show_in_rest' => true,
	            'show_tagcloud'			=> false,
	            'public' 	     		=> $public,
	           /* 'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),*/
	            'rewrite' 				=> $rewrite,
	        ) )
	    );
		
		// Register dynamic taxonomies for custom listing types
		$this->register_dynamic_listing_type_taxonomies();
				
		
	} /* eof register*/

	/**
	 * Register taxonomies for custom listing types
	 */
	private function register_dynamic_listing_type_taxonomies() {
		// Only register if custom types manager is available
		if (!function_exists('listeo_core_custom_listing_types')) {
			return;
		}
		
		$custom_types_manager = listeo_core_custom_listing_types();
		$listing_types = $custom_types_manager->get_listing_types(true); // Get active types only
		
		// Debug logging
		// if (defined('WP_DEBUG') && WP_DEBUG) {
		//		error_log('Listeo: Attempting to register taxonomies for ' . count($listing_types) . ' listing types');
		// }
		
		foreach ($listing_types as $type) {
			// Skip if taxonomy registration is disabled for this type
			if (!isset($type->register_taxonomy) || !$type->register_taxonomy) {
				
				continue;
			}
			
			// For default types, we need to check if they should be registered or not
			// but we can't unregister already registered taxonomies, only skip registration
			if (in_array($type->slug, array('service', 'rental', 'event', 'classifieds'))) {
				
				continue;
			}
			
			// WordPress has a 32-character limit for taxonomy slugs
		// If slug + '_category' exceeds this, truncate the base slug
		$suffix = '_category';
		$max_length = 32;
		$base_slug = $type->slug;

		if (strlen($base_slug . $suffix) > $max_length) {
			$max_base_length = $max_length - strlen($suffix);
			$base_slug = substr($type->slug, 0, $max_base_length);

			// Log warning about truncation
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(sprintf(
					'Listeo: Taxonomy slug truncated for listing type "%s". Original: "%s", Truncated: "%s"',
					$type->name,
					$type->slug . $suffix,
					$base_slug . $suffix
				));
			}
		}

		$taxonomy_slug = $base_slug . $suffix;
			$singular = sprintf(__('%s Category', 'listeo_core'), $type->name);
			$plural = sprintf(__('%s Categories', 'listeo_core'), $type->plural_name);

			// Get translated slug based on current language
			$rewrite_slug = $type->slug . '-category'; // Default slug

			if (isset($type->slug_translations) && !empty($type->slug_translations)) {
				$translations = json_decode($type->slug_translations, true);
				if (is_array($translations) && !empty($translations)) {
					$current_lang = $this->get_current_language();
					if (!empty($current_lang) && isset($translations[$current_lang])) {
						$rewrite_slug = $translations[$current_lang];
					}
				}
			}

			$rewrite = array(
				'slug' => apply_filters('listeo_taxonomy_rewrite_slug', $rewrite_slug, $type->slug, $taxonomy_slug),
				'with_front' => false,
				'hierarchical' => false
			);

			// Debug logging
			// if (defined('WP_DEBUG') && WP_DEBUG) {
			//		error_log('Listeo: Registering taxonomy ' . $taxonomy_slug . ' for type ' . $type->slug);
			// }
			
			register_taxonomy($taxonomy_slug,
				apply_filters('register_taxonomy_' . $taxonomy_slug . '_object_type', array('listing')),
				apply_filters('register_taxonomy_' . $taxonomy_slug . '_args', array(
					'hierarchical' => true,
					'label' => $plural,
					'labels' => array(
						'name' => $plural,
						'singular_name' => $singular,
						'menu_name' => ucwords($plural),
						'search_items' => sprintf(__('Search %s', 'listeo_core'), $plural),
						'all_items' => sprintf(__('All %s', 'listeo_core'), $plural),
						'parent_item' => sprintf(__('Parent %s', 'listeo_core'), $singular),
						'parent_item_colon' => sprintf(__('Parent %s:', 'listeo_core'), $singular),
						'edit_item' => sprintf(__('Edit %s', 'listeo_core'), $singular),
						'update_item' => sprintf(__('Update %s', 'listeo_core'), $singular),
						'add_new_item' => sprintf(__('Add New %s', 'listeo_core'), $singular),
						'new_item_name' => sprintf(__('New %s Name', 'listeo_core'), $singular)
					),
					'show_ui' => true,
					'show_in_rest' => true,
					'show_tagcloud' => false,
					'public' => true,
					'rewrite' => $rewrite,
				))
			);
		}
	}

	/**
	 * Get taxonomy registration settings for default types
	 */
	private function get_default_taxonomy_settings() {
		$settings = array(
			'service' => true,
			'rental' => true, 
			'event' => true,
			'classifieds' => true
		);
		
		// Check custom types manager for override settings
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$listing_types = $custom_types_manager->get_listing_types(true);
			
			foreach ($listing_types as $type) {
				if (in_array($type->slug, array('service', 'rental', 'event', 'classifieds'))) {
					$settings[$type->slug] = isset($type->register_taxonomy) && $type->register_taxonomy;
				}
			}
		}
		
		return $settings;
	}

	/**
	 * Force refresh taxonomies (called when listing types are updated)
	 */
	public static function refresh_dynamic_taxonomies() {
		$post_types = new self();
		$post_types->register_dynamic_listing_type_taxonomies();
		
		// Flush rewrite rules to ensure new taxonomies work properly
		flush_rewrite_rules();
	}

	/**
	 * Adds columns to admin listing of listing Listings.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}
		
		
		$columns["listing_type"]     	 	= __( "Type", 'listeo_core');
		$columns["listing_region"]      	= __( "Region", 'listeo_core');
		$columns["listing_address"]      	= __( "Address", 'listeo_core');
		$columns["listing_posted"]          = __( "Posted", 'listeo_core');
		$columns["expires"]           		= __( "Expires", 'listeo_core');
		if(get_option('listeo_new_listing_requires_purchase') ) {
			$columns['listing_package']         = __( "Package", 'listeo_core');
		}
		$columns['featured_listing']        = '<span class="tips" data-tip="' . __( "Featured?", 'listeo_core') . '">' . __( "Featured?", 'listeo_core') . '</span>';
		$columns['listing_actions']         = __( "Actions", 'listeo_core');
		return $columns;
	}

	/**
	 * Make listing columns sortable
	 *
	 * @access public
	 * @param mixed $columns
	 * @return array
	 */
	public function sortable_columns($columns)
	{
		$custom = array(
			'listing_type'     => '_listing_type',
			'listing_region'   => 'listing_region',
			'listing_address'  => '_address',
			'listing_posted'   => 'date',
			'expires'          => '_listing_expires',
			'featured_listing' => '_featured'
		);
		return wp_parse_args($custom, $columns);
	}

	/**
	 * Listing columns orderby
	 *
	 * @access public
	 * @param mixed $query
	 * @return void
	 */
	public function sort_columns_query($query)
	{
		if (! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== 'listing') {
			return;
		}

		$orderby = $query->get('orderby');

		switch ($orderby) {
			case '_listing_type':
				$query->set('meta_key', '_listing_type');
				$query->set('orderby', 'meta_value');
				break;

			case '_address':
				$query->set('meta_key', '_address');
				$query->set('orderby', 'meta_value');
				break;

			case '_listing_expires':
				$query->set('meta_key', '_listing_expires');
				$query->set('orderby', 'meta_value_num');
				break;

			case '_featured':
				$query->set('meta_key', '_featured');
				$query->set('orderby', 'meta_value');
				break;

			case 'listing_region':
				$query->set('orderby', 'title');
				break;
		}
	}


	/**
	 * Displays the content for each custom column on the admin list for listing Listings.
	 *
	 * @param mixed $column
	 */
	public function custom_columns( $column ) {
		global $post;

		switch ( $column ) {
	
			case "listing_type" :
				$type = get_post_meta($post->ID, '_listing_type', true);

				switch ($type) {
					case 'service':
						echo esc_html_e('Service','listeo_core');
						break;
					case 'rental':
						echo esc_html_e('Rental','listeo_core');
						break;
					case 'event':
						echo esc_html_e('Event','listeo_core');
						break;
					case 'classifieds':
						echo esc_html_e('Classifieds','listeo_core');
						break;

					default:
						// Handle custom listing types
						if (!empty($type) && function_exists('listeo_core_custom_listing_types')) {
							$custom_types_manager = listeo_core_custom_listing_types();
							$type_obj = $custom_types_manager->get_listing_type_by_slug($type);

							if ($type_obj && $type_obj->is_active) {
								echo esc_html($type_obj->name);
							} else {
								// Fallback: capitalize the type slug
								echo esc_html(ucfirst(str_replace('_', ' ', $type)));
							}
						} else {
							// Fallback: capitalize the type slug for non-custom types
							echo esc_html(ucfirst(str_replace('_', ' ', $type)));
						}
						break;
				}
			break;
			
			case "listing_address" :
				the_listing_address( $post );
			break;
			case "listing_region" :
				if ( ! $terms = get_the_term_list( $post->ID, 'region', '', ', ', '' ) ) echo '<span class="na">&ndash;</span>'; else echo $terms;
			break;

			case "expires" :
				$expires = get_post_meta($post->ID,'_listing_expires',true);
				if(( is_numeric($expires) && (int)$expires == $expires && (int)$expires>0)){ 
					echo date_i18n( get_option( 'date_format' ), $expires);
				} else {
					echo $expires;
				}

			break;

			case "listing_package" :
				
				$user_package = get_post_meta($post->ID, '_user_package_id', true);
				
				//echo $user_package;
				//$user_packages = listeo_core_available_packages($post_author_id,$user_package);
				if ($user_package) {
					$package = listeo_core_get_package_by_id($user_package);
					
					
					if ($package && $package->product_id) {
						echo get_the_title($package->product_id);
						
					}

					//return $package->get_title();
				} else {
					echo __('None','listeo_core');
				}
				$edit_url = esc_url(add_query_arg(
					array(
						'action' => 'edit',
						'listing_id' => $post->ID,
						'package_id' => $user_package,
					),
					admin_url('admin.php?page=listeo_core_paid_listings_package_editor')
				));
				echo ' <a href="' . $edit_url . '">(edit)</a>';
			break;

			case "featured_listing" :
				if ( listeo_core_is_featured( $post->ID ) ) echo '&#10004;'; else echo '&ndash;';
			break;
			case "listing_posted" :
				echo '<strong>' . date_i18n( __( 'M j, Y', 'listeo_core'), strtotime( $post->post_date ) ) . '</strong><span>';
				echo ( empty( $post->post_author ) ? __( 'by a guest', 'listeo_core') : sprintf( __( 'by %s', 'listeo_core'), '<a href="' . esc_url( add_query_arg( 'author', $post->post_author ) ) . '">' . get_the_author() . '</a>' ) ) . '</span>';
			break;
			
			case "listing_actions" :
				// Get the view count
				
				echo '<div class="actions">';

				$admin_actions = apply_filters( 'listeo_core_post_row_actions', array(), $post );

				if ( in_array( $post->post_status, array( 'pending', 'preview', 'pending_payment' ) ) && current_user_can ( 'publish_post', $post->ID ) ) {
					$admin_actions['approve']   = array(
						'action'  => 'approve',
						'name'    => __( 'Approve', 'listeo_core'),
						'url'     =>  wp_nonce_url( add_query_arg( 'approve_listing', $post->ID ), 'approve_listing' )
					);
				}
			if ( $post->post_status != 'rejected' && current_user_can ( 'publish_posts' ) ) {
				$admin_actions['reject']   = '<a class="button button-icon tips icon-reject listeo-reject-listing-button" href="#" data-listing-id="' . esc_attr( $post->ID ) . '" data-tip="' . esc_attr__( 'Reject', 'listeo_core') . '">' . esc_html__( 'Reject', 'listeo_core') . '</a>';
			}
/*				if ( $post->post_status !== 'trash' ) {
					if ( current_user_can( 'read_post', $post->ID ) ) {
						$admin_actions['view']   = array(
							'action'  => 'view',
							'name'    => __( 'View', 'listeo_core'),
							'url'     => get_permalink( $post->ID )
						);
					}
					if ( current_user_can( 'edit_post', $post->ID ) ) {
						$admin_actions['edit']   = array(
							'action'  => 'edit',
							'name'    => __( 'Edit', 'listeo_core'),
							'url'     => get_edit_post_link( $post->ID )
						);
					}
					if ( current_user_can( 'delete_post', $post->ID ) ) {
						$admin_actions['delete'] = array(
							'action'  => 'delete',
							'name'    => __( 'Delete', 'listeo_core'),
							'url'     => get_delete_post_link( $post->ID )
						);
					}
				}*/

				$admin_actions = apply_filters( 'listing_manager_admin_actions', $admin_actions, $post );

				foreach ( $admin_actions as $action ) {
					if ( is_array( $action ) ) {
						printf( '<a class="button button-icon tips icon-%1$s" href="%2$s" data-tip="%3$s">%4$s</a>', $action['action'], esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_html( $action['name'] ) );
					} else {
						echo str_replace( 'class="', 'class="button ', $action );
					}
				}

				echo '</div>';
				$views = get_post_meta($post->ID,
					'_listing_views_count',
					true
				);
				$views = $views ? $views : '0';

				// Display title and views

				echo '<br><small>' . sprintf(__('Views: %s', 'listeo_core'), $views) . '</small>';

			break;
		}
	}


	/**
	 * Sets expiry date when status changes.
	 *
	 * @param WP_Post $post
	 */
	public function set_expiry( $post ) {
		if ( $post->post_type !== 'listing' ) {
			return;
		}
		$expires =  get_post_meta( $post->ID, '_listing_expires', true );

		// Don't process subscription listings - they use a sentinel value ' ' to prevent expiration
		if ( $expires === ' ' ) {
			return;
		}

		// See if it is already set
		if ( $expires ) {
			
			
			if(( is_numeric($expires) && (int)$expires == $expires && (int)$expires>0)){
				
			} else {
				$expires = CMB2_Utils::get_timestamp_from_value( $expires, 'm/d/Y' ); 
				if ( $expires && $expires < current_time( 'timestamp' ) ) {
					update_post_meta( $post->ID, '_listing_expires', '' );
				} else {
					
					//update_post_meta( $post->ID, '_listing_expires', $expires );
				}
			}		
			
			
		
		}
		

		// See if the user has set the expiry manually:
		if ( ! empty( $_POST[ '_listing_expires' ] ) ) {
			$expires = $_POST[ '_listing_expires' ];
			if(( is_numeric($expires) && (int)$expires == $expires && (int)$expires>0)){
				//
			} else {
				$expires = CMB2_Utils::get_timestamp_from_value( $expires, 'm/d/Y' ); 
			
			}		
			update_post_meta( $post->ID, '_listing_expires',  $expires );
		
		// No manual setting? Lets generate a date if there isn't already one
		} elseif (!$expires ) {
			$expires = calculate_listing_expiry( $post->ID );
			update_post_meta( $post->ID, '_listing_expires', $expires );

			// In case we are saving a post, ensure post data is updated so the field is not overridden
			if ( isset( $_POST[ '_listing_expires' ] ) ) {
				$expires = $_POST[ '_listing_expires' ];
				if(( is_numeric($expires) && (int)$expires == $expires && (int)$expires>0)){
					//
				} else {
					$expires = CMB2_Utils::get_timestamp_from_value( $expires, 'm/d/Y' ); 
				
				}	
				$_POST[ '_listing_expires' ] = $expires;
			}
		}
	}


	/**
	 * Maintenance task to expire listings.
	 */
	public function check_for_expired() {
		global $wpdb;
		//$date_format = get_option('date_format');
		$date_format = 'm/d/Y';
		$current_time = current_time('timestamp');
		// Change status to expired
		$listing_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT postmeta.post_id FROM {$wpdb->postmeta} as postmeta
			LEFT JOIN {$wpdb->posts} as posts ON postmeta.post_id = posts.ID
			WHERE postmeta.meta_key = '_listing_expires'
			AND postmeta.meta_value > 0
			AND postmeta.meta_value < %s
			AND posts.post_status = 'publish'
			AND posts.post_type = 'listing'
		", $current_time));

		if ( $listing_ids ) {
			foreach ( $listing_ids as $listing_id ) {
		
				$listing_data       = array();
				$listing_data['ID'] = $listing_id;
				$listing_data['post_status'] = 'expired';
				wp_update_post( $listing_data );
				do_action('listeo_core_expired_listing',$listing_id);
			}
		}

		// Event listings expiry check
		$event_listing_ids = array();
		if ( get_option( 'listeo_expire_after_event' ) ) {

			// Collect every listing-type slug whose booking_type is 'tickets' (the canonical
			// name; 'event' is the legacy alias). Custom listing types created post-2.x can use
			// any slug, so we can't hardcode 'event' anymore. Always include the legacy 'event'
			// slug for back-compat with sites whose listings were tagged before custom types.
			$ticket_type_slugs = array( 'event' );
			if ( function_exists( 'listeo_core_custom_listing_types' ) ) {
				$manager = listeo_core_custom_listing_types();
				if ( $manager && method_exists( $manager, 'get_listing_types' ) ) {
					foreach ( (array) $manager->get_listing_types( false ) as $type ) {
						if ( ! empty( $type->slug ) && isset( $type->booking_type )
							&& in_array( $type->booking_type, array( 'tickets', 'event' ), true ) ) {
							$ticket_type_slugs[] = $type->slug;
						}
					}
				}
			}
			$ticket_type_slugs = array_values( array_unique( array_filter( $ticket_type_slugs ) ) );

			$ticket_type_slugs = apply_filters( 'listeo_core_expire_after_event_listing_types', $ticket_type_slugs );

			if ( ! empty( $ticket_type_slugs ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $ticket_type_slugs ), '%s' ) );
				$sql = "
					SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = '_listing_type'
					LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_event_date_end_timestamp'
					LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_event_date_timestamp'
					WHERE p.post_type = 'listing'
					AND p.post_status = 'publish'
					AND pm_type.meta_value IN ($placeholders)
					AND (
						(pm_end.meta_value IS NOT NULL AND pm_end.meta_value < %d)
						OR
						(pm_end.meta_value IS NULL AND pm_start.meta_value < %d)
					)
				";
				$query_args = array_merge( $ticket_type_slugs, array( $current_time, $current_time ) );
				$event_listing_ids = $wpdb->get_col( $wpdb->prepare( $sql, $query_args ) );
			}
		}
		$all_expired_ids = array_merge($listing_ids, $event_listing_ids);
		// Notifie expiring in 5 days
		$listing_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT postmeta.post_id FROM {$wpdb->postmeta} as postmeta
			LEFT JOIN {$wpdb->posts} as posts ON postmeta.post_id = posts.ID
			WHERE postmeta.meta_key = '_listing_expires'
			AND postmeta.meta_value > 0
			AND postmeta.meta_value < %s
			AND posts.post_status = 'publish'
			AND posts.post_type = 'listing'
		", strtotime( date( $date_format, strtotime('+5 days') ) ) ) );

		if ($all_expired_ids) {
			foreach ($all_expired_ids as $listing_id) {
				$listing_data = array(
					'ID' => $listing_id,
					'post_status' => 'expired'
				);
				wp_update_post($listing_data);
				do_action('listeo_core_expired_listing', $listing_id);
			}
		}
		// Delete old expired listings
		if ( apply_filters( 'listeo_core_delete_expired_listings', false ) ) {
			$all_expired_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT posts.ID FROM {$wpdb->posts} as posts
				WHERE posts.post_type = 'listing'
				AND posts.post_modified < %s
				AND posts.post_status = 'expired'
			", strtotime( date( $date_format, strtotime( '-' . apply_filters( 'listeo_delete_expired_listings_days', 30 ) . ' days', current_time( 'timestamp' ) ) ) ) ) );

			if ($all_expired_ids ) {
				foreach ($all_expired_ids as $listing_id ) {
					wp_trash_post( $listing_id );
				}
			}
		}
	}

	public function check_for_expiring() {
		global $wpdb;

		$current_time = current_time('timestamp');
		$reminder_time = $current_time + (5 * 24 * 60 * 60); // 5 days from now

		// Get listings that expire in 5 days and haven't been reminded yet
		$listing_ids = $wpdb->get_col($wpdb->prepare("
		SELECT postmeta.post_id FROM {$wpdb->postmeta} as postmeta
		LEFT JOIN {$wpdb->posts} as posts ON postmeta.post_id = posts.ID
		LEFT JOIN {$wpdb->postmeta} as reminder_meta ON (postmeta.post_id = reminder_meta.post_id AND reminder_meta.meta_key = '_expiration_reminder_sent')
		WHERE postmeta.meta_key = '_listing_expires'
		AND postmeta.meta_value > 0
		AND postmeta.meta_value <= %s
		AND postmeta.meta_value > %s
		AND posts.post_status = 'publish'
		AND posts.post_type = 'listing'
		AND (reminder_meta.meta_value IS NULL OR reminder_meta.meta_value != '1')
	", $reminder_time, $current_time));

		if ($listing_ids) {
			foreach ($listing_ids as $listing_id) {
				do_action('listeo_core_expiring_soon_listing', $listing_id);
			}
		}
	}

	/**
	 * Adds bulk actions to drop downs on Job Listing admin page.
	 *
	 * @param array $bulk_actions
	 * @return array
	 */
	public function add_bulk_actions( $bulk_actions ) {
		global $wp_post_types;

		foreach ( $this->get_bulk_actions() as $key => $bulk_action ) {
			if ( isset( $bulk_action['label'] ) ) {
				$bulk_actions[ $key ] = sprintf( $bulk_action['label'], $wp_post_types['listing']->labels->name );
			}
		}
		return $bulk_actions;
	}


	/**
	 * Performs bulk actions on Job Listing admin page.
	 *
	 * @since 1.27.0
	 *
	 * @param string $redirect_url The redirect URL.
	 * @param string $action       The action being taken.
	 * @param array  $post_ids     The posts to take the action on.
	 */
	public function do_bulk_actions( $redirect_url, $action, $post_ids ) {
		$actions_handled = $this->get_bulk_actions();
		if ( isset ( $actions_handled[ $action ] ) && isset ( $actions_handled[ $action ]['handler'] ) ) {
			$handled_jobs = array();
			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $post_id ) {
					if ( 'listing' === get_post_type( $post_id )
					     && call_user_func( $actions_handled[ $action ]['handler'], $post_id ) ) {
						$handled_jobs[] = $post_id;
					}
				}
				wp_redirect( add_query_arg( 'handled_jobs', $handled_jobs, add_query_arg( 'action_performed', $action, $redirect_url ) ) );
				exit;
			}
		}
	}

	/**
	 * Returns the list of bulk actions that can be performed on job listings.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions_handled = array();
		$actions_handled['approve_listings'] = array(
			'label' => __( 'Approve %s', 'listeo_core' ),
			'notice' => __( '%s approved', 'listeo_core' ),
			'handler' => array( $this, 'bulk_action_handle_approve_listing' ),
		);
		$actions_handled['expire_listings'] = array(
			'label' => __( 'Expire %s', 'listeo_core' ),
			'notice' => __( '%s expired', 'listeo_core' ),
			'handler' => array( $this, 'bulk_action_handle_expire_listing' ),
		);
	$actions_handled['reject_listings'] = array(
		'label' => __( 'Reject %s', 'listeo_core' ),
		'notice' => __( '%s rejected', 'listeo_core' ),
		'handler' => array( $this, 'bulk_action_handle_reject_listing' ),
	);
	

		return apply_filters( 'listeo_core_bulk_actions', $actions_handled );
	}

	/**
	 * Performs bulk action to approve a single job listing.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function bulk_action_handle_approve_listing( $post_id ) {
		$job_data = array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		);
		if ( in_array( get_post_status( $post_id ), array( 'pending', 'pending_payment' ) )
		     && current_user_can( 'publish_post', $post_id )
		     && wp_update_post( $job_data )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Performs bulk action to expire a single job listing.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function bulk_action_handle_expire_listing( $post_id ) {
		$job_data = array(
			'ID'          => $post_id,
			'post_status' => 'expired',
		);
		if ( current_user_can( 'manage_listings', $post_id )
		     && wp_update_post( $job_data )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Performs bulk action to reject a single job listing.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function bulk_action_handle_reject_listing( $post_id ) {
		// Default rejection reason for bulk actions
		$rejection_reason = __( 'Listing rejected by administrator', 'listeo_core' );
		update_post_meta( $post_id, '_listing_rejection_reason', $rejection_reason );
		
		$job_data = array(
			'ID'          => $post_id,
			'post_status' => 'rejected',
		);
		if ( current_user_can( 'publish_posts' )
		     && wp_update_post( $job_data )
		) {
			return true;
		}
		return false;
	}


	/**
	 * Approves a single listing.
	 */
	public function approve_listing() {
		if ( ! empty( $_GET['approve_listing'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'approve_listing' ) && current_user_can( 'publish_post', $_GET['approve_listing'] ) ) {
			$post_id = absint( $_GET['approve_listing'] );
			$listing_data = array(
				'ID'          => $post_id,
				'post_status' => 'publish'
			);
			wp_update_post( $listing_data );
			wp_redirect( remove_query_arg( 'approve_listing', add_query_arg( 'handled_listings', $post_id, add_query_arg( 'action_performed', 'approve_listings', admin_url( 'edit.php?post_type=listing' ) ) ) ) );
			exit;
		}
	}

	/**
	 * Rejects a single listing.
	 */
	public function reject_listing() {
		if ( ! empty( $_GET['post'] ) && isset( $_GET['action'] ) && $_GET['action'] === 'listeo_reject_listing' && isset( $_GET['reject_nonce'] ) && wp_verify_nonce( $_GET['reject_nonce'], 'listeo_reject_listing' ) && current_user_can( 'publish_posts' ) ) {
			$post_id = absint( $_GET['post'] );
			
			// Get rejection reason
			$rejection_reason = isset( $_GET['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_GET['rejection_reason'] ) ) : '';
			
			// Save rejection reason
			if ( ! empty( $rejection_reason ) ) {
				update_post_meta( $post_id, '_listing_rejection_reason', $rejection_reason );
			}
			
			// Update post status
			$listing_data = array(
				'ID'          => $post_id,
				'post_status' => 'rejected'
			);
			wp_update_post( $listing_data );
			
			// Redirect with notice
			wp_redirect( remove_query_arg( array( 'reject_nonce', 'rejection_reason', 'action' ), add_query_arg( array( 'handled_listings' => $post_id, 'action_performed' => 'reject_listings' ), admin_url( 'edit.php?post_type=listing' ) ) ) );
			exit;
		}
	}

	/**
	 * Shows a notice if we did a bulk action.
	 */
	public function action_notices() {
		global $post_type, $pagenow;

		$handled_jobs = isset ( $_REQUEST['handled_listings'] ) ? $_REQUEST['handled_listings'] : false;
		$action = isset ( $_REQUEST['action_performed'] ) ? $_REQUEST['action_performed'] : false;
		$actions_handled = $this->get_bulk_actions();

		if ( $pagenow == 'edit.php'
			 && $post_type == 'listing'
			 && $action
			 && ! empty( $handled_jobs )
			 && isset ( $actions_handled[ $action ] )
			 && isset ( $actions_handled[ $action ]['notice'] )
		) {
			if ( is_array( $handled_jobs ) ) {
				$handled_jobs = array_map( 'absint', $handled_jobs );
				$titles       = array();
				foreach ( $handled_jobs as $job_id ) {
					$titles[] = listeo_core_get_the_listing_title( $job_id );
				}
				echo '<div class="updated"><p>' . sprintf( $actions_handled[ $action ]['notice'], '&quot;' . implode( '&quot;, &quot;', $titles ) . '&quot;' ) . '</p></div>';
			} else {
				
				echo '<div class="updated"><p>' . sprintf( $actions_handled[ $action ]['notice'], '&quot;' . listeo_core_get_the_listing_title(absint( $handled_jobs )) . '&quot;' ) . '</p></div>';
			}
		}
	}

	
	public function add_icon_column( $columns ) {
		
		$columns['icon'] = __( 'Icon', 'listeo_core' );
		return $columns;
	}


	/**
	 * Adds the Employment Type column content when listing job type terms in WP Admin.
	 *
	 * @param string $content
	 * @param string $column_name
	 * @param int    $term_id
	 * @return string
	 */
	public function add_icon_column_content( $content, $column_name, $term_id ) {
		
		if( 'icon' !== $column_name ){
			return $content;
		}
		
		$term_id = absint( $term_id );
		$icon = get_term_meta($term_id,'icon',true);
		
		if($icon) {
			$content .= '<i style="font-size:30px;" class="'.$icon.'"></i>';	
		}

		return $content;
	}

	public function add_assigned_features_column( $columns ) {
		
		$columns['features'] = __( 'Features', 'listeo_core' );
		return $columns;
	}

	public function add_assigned_features_content( $content, $column_name, $term_id ) {
		if( 'features' !== $column_name ){
			return $content;
		}
		
		$term_id = absint( $term_id );
		$term_meta = get_term_meta($term_id,'listeo_taxonomy_multicheck',true);
		if($term_meta){
			foreach ($term_meta as $feature) {
				$feature_obj = get_term_by('slug', $feature, 'listing_feature'); 
				
				if($feature_obj ){
					$term_link = get_term_link( $feature_obj );
					$content .= '<a href="'. esc_url( $term_link ).'">'.$feature_obj->name.'</a>, ';
				}
				
			}
			$content  = substr($content , 0, -2);
		}
		return $content;
	}

	public function set_default_avg_rating_new_post($post_ID){
		if (wp_is_post_revision($post_ID) || get_post_type($post_ID) !== 'listing') {
			return $post_ID;
		}

		$current_field_value = get_post_meta($post_ID,'listeo-avg-rating',true);
		if ($current_field_value == ''){
		    add_post_meta($post_ID,'listeo-avg-rating','0',true);
		}

		// Ensure views count exists so "Most viewed" sorting includes new listings
		$views_count = get_post_meta($post_ID, '_listing_views_count', true);
		if ($views_count === '') {
			add_post_meta($post_ID, '_listing_views_count', '0', true);
		}

		return $post_ID;
	}



	function add_listings_permastructure() {
		global $wp_rewrite;

		$standard_slug = apply_filters( 'listeo_rewrite_listing_slug', 'listing' );
		$permalinks = Listeo_Core_Post_Types::get_permalink_structure();
		$slug = (isset($permalinks['listing_base']) && !empty($permalinks['listing_base'])) ? $permalinks['listing_base'] : $standard_slug ;
		

		//add_permastruct( 'region', $slug.'/%region%', false );
		//add_permastruct( 'listing_category', $slug.'/%listing_category%', false );
		add_permastruct( 'listing', $slug.'/%region%/%listing_category%/%listing%', false );
	}

	function listing_permalinks( $permalink, $post ) {
		if ( $post->post_type !== 'listing' )
			return $permalink;
		
		$regions = get_the_terms( $post->ID, 'region' );
		if ( ! $regions ) {
			$permalink = str_replace( '%region%/', '-/', $permalink );
		} else {

		$post_regions = array();
		foreach ( $regions as $region )
			$post_regions[] = $region->slug;

		$permalink = str_replace( '%region%', implode( ',', $post_regions ) , $permalink );
		}

		$categories = get_the_terms( $post->ID, 'listing_category' );
		if ( ! $categories ) {
			$permalink = str_replace( '%listing_category%/', '-/', $permalink );
		} else {



		$post_categories = array();
		foreach ( $categories as $category )
			$post_categories[] = $category->slug;
		
		$permalink = str_replace( '%listing_category%', implode( '-', $post_categories ) , $permalink );
		}


		return $permalink;
	}

	// Make sure that all term links include their parents in the permalinks

	function add_term_parents_to_permalinks( $permalink, $term ) {
		// Only apply to Listeo taxonomies
		if ( ! in_array( $term->taxonomy, array( 'listing_category', 'region', 'service_category', 'rental_category', 'event_category', 'classifieds_category' ) ) ) {
			return $permalink;
		}

		// Check if the taxonomy supports hierarchical permalinks
		$taxonomy_obj = get_taxonomy( $term->taxonomy );
		if ( ! $taxonomy_obj || ! $taxonomy_obj->hierarchical ) {
			return $permalink;
		}

		// For regions, check if hierarchical permalinks are enabled
		if ( $term->taxonomy === 'region' ) {
			$hierarchical_enabled = (bool) get_option( 'listeo_region_hierarchical_permalinks', false );
			if ( ! $hierarchical_enabled ) {
				return $permalink;
			}
		}

		// Build hierarchical path with slashes (modern approach)
		if ( $term->parent ) {
			$hierarchy = array();
			$current_term = $term;

			// Walk up the hierarchy
			while ( $current_term && ! is_wp_error( $current_term ) ) {
				array_unshift( $hierarchy, $current_term->slug );
				if ( $current_term->parent ) {
					$current_term = get_term( $current_term->parent, $term->taxonomy );
				} else {
					break;
				}
			}

			// Build the hierarchical slug path
			$hierarchical_path = implode( '/', $hierarchy );

			// Replace the term slug with the full hierarchical path
			$permalink = str_replace( '/' . $term->slug . '/', '/' . $hierarchical_path . '/', $permalink );
		}

		return $permalink;
	}

	function get_term_parents( $term, &$parents = array() ) {
		$parent = get_term( $term->parent, $term->taxonomy );

		if ( is_wp_error( $parent ) )
			return $parents;

		$parents[] = $parent;
		if ( $parent->parent )
			self::get_term_parents( $parent, $parents );
	    return $parents;
	}

	public function default_comments_on( $data ) {
	    if( $data['post_type'] == 'listing' ) {
	        $data['comment_status'] = 'open';
	    }

	    return $data;
	}


	function save_as_product( $post_ID, $post, $update ){
		if(!is_admin()){

			return;
		}
		if(!is_woocommerce_activated()){
			return;
		}

		if ($post->post_type == 'listing') {

			
			$product_id = get_post_meta($post_ID, 'product_id', true);
			$listing_id = $post->ID;
			$listing_url = get_permalink($listing_id);

			// basic listing informations will be added to listing
			$product = array (
				'post_author' => get_current_user_id(),
				'post_content' => $post->post_content,
				'post_status' => 'publish',
				'post_title' => $post->post_title,
				'post_parent' => '',
				'post_type' => 'product',
			);

				// add product if not exist
			if ( ! $product_id ||  get_post_type( $product_id ) != 'product') {
				
				// insert listing as WooCommerce product
				$product_id = wp_insert_post( $product );
				wp_set_object_terms( $product_id, 'listing_booking', 'product_type' );

			} else {

				// update existing product
				$product['ID'] = $product_id;
				wp_update_post ( $product );

			}

		
		// set product category
			$term = get_term_by( 'name', apply_filters( 'listeo_default_product_category', 'Listeo booking'), 'product_cat', ARRAY_A );

			if ( ! $term ) $term = wp_insert_term(
				apply_filters( 'listeo_default_product_category', 'Listeo booking'),
				'product_cat',
				array(
				  'description'=> __( 'Listings category', 'listeo-core' ),
				  'slug' => str_replace( ' ', '-', apply_filters( 'listeo_default_product_category', 'Listeo booking') )
				)
			  );
		  
			wp_set_object_terms( $product_id, $term['term_id'], 'product_cat');

			update_post_meta($post_ID, 'product_id', $product_id);
			update_post_meta($post_ID, 'listing_url', $product_id);
		}
	
	}


	function add_local_business_schema()
	{
		global $post;

		if (!$post) return;
		// check if post is listing
		if (get_post_type($post->ID) != 'listing') {
			return;
		}
		// Allow disabling Listeo's auto-generated JSON-LD schema from
		// Listeo Core -> General settings (e.g. when using a custom/SEO-plugin schema).
		if (get_option('listeo_disable_schema')) {
			return;
		}
		// Get the business name (page title)
		$business_name = esc_attr(get_the_title($post->ID));
 
		// Get the price range
		$price_range = get_the_listing_price_range();
		if (empty($price_range)) {
			$price_range = esc_html__('Not available', 'listeo');  // Make string translatable
		}

		// Get the business address
		$business_address = get_the_listing_address();
		if (empty($business_address)) {
			$business_address = esc_html__('Address not available', 'listeo');
		}

		// Try to parse address components
		$address_components = [
			'street' => '',
			'city' => '',
			'region' => '',
			'postalCode' => '',
			'country' => ''
		];

		if (!empty($business_address) && $business_address !== esc_html__('Address not available', 'listeo')) {
			// Parse address working backwards from the end for better accuracy
			$address_parts = array_map('trim', explode(',', $business_address));
			$count = count($address_parts);

			if ($count >= 2) {
				// Work backwards from the end
				$address_components['country'] = $address_parts[$count - 1];  // Last part

				if ($count >= 3) {
					// Check if second-to-last contains state/region and optional postal code
					$potential_region = $address_parts[$count - 2];

					// Extract postal code if present - supports international formats (US: 78701, EU: 20-114, UK: SW1A 1AA)
					if (preg_match('/^(.+?)\s+([A-Z\d]{2,10}(?:[\s\-][A-Z\d]{2,4})?)$/i', $potential_region, $matches)) {
						$address_components['region'] = trim($matches[1]);
						$address_components['postalCode'] = trim($matches[2]);
					} else {
						$address_components['region'] = $potential_region;
					}

					if ($count >= 4) {
						// Third from end is city
						$address_components['city'] = $address_parts[$count - 3];
						// Everything before that is street address
						$address_components['street'] = implode(', ', array_slice($address_parts, 0, $count - 3));
					} else {
						// Only 3 parts: assume "City, State, Country"
						$address_components['city'] = $address_parts[0];
					}
				} else {
					// Only 2 parts: assume "City, Country"
					$address_components['city'] = $address_parts[0];
				}
			}
		}
		// Get the service category using listing_category
		$category_terms = get_the_terms($post->ID, 'listing_category');
		$business_category = '';
		if ($category_terms && !is_wp_error($category_terms)) {
			// We use the first term found
			$business_category = $category_terms[0]->name;
		}

		// Get latitude and longitude coordinates
		$latitude = get_post_meta($post->ID, '_geolocation_lat', true);
		$longitude = get_post_meta($post->ID, '_geolocation_lng', true);

		// Get reviews and rating (if available)
		$rating = null;
		$review_count = null;
		$reviews = [];
		if (!get_option('listeo_disable_reviews')) {
			// Use the new combined rating display function
			$rating_data = listeo_get_rating_display($post->ID);
			$rating = $rating_data['rating'];
			$review_count = $rating_data['count'];

			if (!$rating && get_option('listeo_google_reviews_instead')) {
				$reviews = listeo_get_google_reviews($post);
				if (!empty($reviews['result']['reviews'])) {
					$rating = number_format_i18n($reviews['result']['rating'], 1);
					$rating = str_replace(',', '.', $rating);  // Format the rating
					$review_count = count($reviews['result']['reviews']);
				}
			}
		}

		// Get additional review details (such as author and comment)
		$review_data = [];
		if ($reviews && !empty($reviews['result']['reviews'])) {
			foreach ($reviews['result']['reviews'] as $review) {
				$review_data[] = [
					"@type" => "Review",
					"author" => [
						"@type" => "Person",
						"name" => $review['author_name']
					],
					"datePublished" => date("c", $review['time']),
					"reviewBody" => $review['text'],
					"reviewRating" => [
						"@type" => "Rating",
						"ratingValue" => $review['rating'],
						"bestRating" => "5",
					]
				];
			}
		}

		// Get business hours from Listeo
		$opening_hours = get_post_meta($post->ID, '_opening_hours', true);
// status if are enabled
		$opening_hours_status = get_post_meta($post->ID, '_opening_hours_status', true);
		// Get business phone from Listeo
		$phone = get_post_meta($post->ID, '_phone', true);

		// Get business email from Listeo
		$email = get_post_meta($post->ID, '_email', true);

		// Get business website from Listeo
		$website = get_post_meta($post->ID, '_website', true);

		// Get business image (featured image with fallback to gallery/placeholder)
		$image_url = listeo_core_get_listing_image($post->ID);
		// Handle case where placeholder returns ID instead of URL
		if (is_numeric($image_url)) {
			$image_url = wp_get_attachment_image_url($image_url, 'full');
		}

		// Get business description
		$description = get_post_meta($post->ID, '_listing_description', true);
		if (empty($description)) {
			$description = get_the_excerpt($post->ID);
		}

		// Get social media links from Listeo
		$facebook = get_post_meta($post->ID, '_facebook', true);
		$twitter = get_post_meta($post->ID, '_twitter', true);
		$instagram = get_post_meta($post->ID, '_instagram', true);

		// Get menu link if it's a restaurant
		$menu_link = get_post_meta($post->ID, '_menu_link', true);

		// Define the JSON-LD schema
		$schema_data = [
			"@context" => "https://schema.org",
			"@type" => ["LocalBusiness", "Product"],
			"name" => $business_name,
			"priceRange" => $price_range,
			"address" => [
				"@type" => "PostalAddress",
				"streetAddress" => $address_components['street'] ?: $business_address,
				"addressLocality" => $address_components['city'],
				"addressRegion" => $address_components['region'],
				"postalCode" => $address_components['postalCode'],
				"addressCountry" => $address_components['country']
			],
			//"category" => $business_category,
			"image" => $image_url,
			"telephone" => $phone,
			"email" => $email,
			"url" => $website ? $website : get_permalink($post->ID),
			"description" => $description,
			"sameAs" => array_filter([
				$facebook,
				$twitter,
				$instagram
			])
		];

		// Remove empty email from schema if not set
		if (empty($schema_data['email'])) {
			unset($schema_data['email']);
		}

		// Get currency (try Listeo settings first, then WooCommerce).
		// Must be resolved BEFORE the offers block below, which uses it for
		// priceCurrency - otherwise priceCurrency always falls back to "USD".
		$currency = get_option('listeo_currency');
		if (empty($currency) && function_exists('get_woocommerce_currency')) {
			$currency = get_woocommerce_currency();
		}

		// Add rating if available
		if ($rating && $review_count) {
			$schema_data["aggregateRating"] = [
				"@type" => "AggregateRating",
				"ratingValue" => $rating,
				"reviewCount" => $review_count,
				"bestRating" => "5",
				"worstRating" => "1"
			];
			// Add offers data for Product schema compatibility
			$schema_data["offers"] = [
				"@type" => "Offer",
				"priceCurrency" => !empty($currency) ? $currency : "USD",
				"availability" => "https://schema.org/InStock"
			];

			// If we have price data, add it to the offers
			$price_min = get_post_meta($post->ID, '_price_min', true);
			if (!empty($price_min)) {
					// Normalize price format for JSON-LD (must use dot as decimal separator)
				// Remove thousand separators and replace comma with dot
				$price_normalized = str_replace([' ', ','], ['', '.'], $price_min);
				// Handle European format with dots as thousand separators (e.g., "1.250,50")
				if (strpos($price_min, ',') !== false && strpos($price_min, '.') !== false) {
					// Format is likely European (1.250,50), remove dots first
					$price_normalized = str_replace('.', '', $price_min);
					$price_normalized = str_replace(',', '.', $price_normalized);
				}
				// Convert to float and format with dot notation for schema.org compliance
				$price_normalized = floatval($price_normalized);
				$schema_data["offers"]["price"] = number_format($price_normalized, 2, '.', '');
			} else {
				// Add a default price to satisfy Product schema requirements
				$schema_data["offers"]["price"] = "0";
			}
		} else {
			// If no rating, we can still add offers data
			$schema_data["offers"] = [
				"@type" => "Offer",
				"priceCurrency" => !empty($currency) ? $currency : "USD",
				"availability" => "https://schema.org/InStock",
				"price" => "0" // Default price if no rating
			];
		}

		// Add individual reviews if available
		if (!empty($review_data)) {
			$schema_data["review"] = $review_data;
		}

		// Add coordinates if available
		if ($latitude && $longitude) {
			$schema_data["geo"] = [
				"@type" => "GeoCoordinates",
				"latitude" => $latitude,
				"longitude" => $longitude
			];
		}

		// Add opening hours if available
		if ($opening_hours_status && !empty($opening_hours) && is_array($opening_hours)) {
			$schema_data["openingHoursSpecification"] = array_map(function ($day, $hours) {
				return [
					"@type" => "OpeningHoursSpecification",
					"dayOfWeek" => $day,
					"opens" => $hours['open'] ?? '',
					"closes" => $hours['close'] ?? ''
				];
			}, array_keys($opening_hours), $opening_hours);
		}

		// Add payment methods accepted if available
		$payment_methods = get_post_meta($post->ID, '_payment_methods', true);
		if (!empty($payment_methods)) {
			$schema_data["paymentAccepted"] = $payment_methods;
		}
		// $currency is resolved earlier (before the offers block); reuse it here.
		if (!empty($currency)) {
			$schema_data["currenciesAccepted"] = $currency;
		}
		

		// Add business type more specifically based on Listeo category
		if ($business_category) {
			$type_mapping = [
				'Restaurants' => 'Restaurant',
				'Hotels' => 'Hotel',
				'Bars' => 'BarOrPub',
				'Fitness' => 'GymOrExerciseFacility',
				'Beauty' => 'BeautySalon',
				'Shopping' => 'Store',
				// Add more mappings as needed
			];

			if (isset($type_mapping[$business_category])) {
				$schema_data["@type"] = [$type_mapping[$business_category], "LocalBusiness"];
			}
		}

		// Add menu for restaurants
		if (!empty($menu_link) && $schema_data["@type"][0] === 'Restaurant') {
			$schema_data["hasMenu"] = $menu_link;
		}

		// Apply filter to allow customization of schema data
		$schema_data = apply_filters('listeo_schema_data', $schema_data, $post->ID);

		// Allow complete override of schema output (return false to prevent output)
		if ($schema_data === false) {
			return;
		}

		// Print the JSON-LD in the head
		echo '<script type="application/ld+json">' . json_encode($schema_data) . '</script>';
	}

	/**
	 * Combined Taxonomy URL Methods
	 */

	/**
	 * Add rewrite rules for combined taxonomy URLs
	 */
	public function add_combined_taxonomy_rewrite_rules() {
		// Get all region and listing feature terms to create specific rules
		$regions = get_terms(array(
			'taxonomy' => 'region',
			'hide_empty' => false,
			'fields' => 'slugs'
		));
		
		$features = get_terms(array(
			'taxonomy' => 'listing_feature', 
			'hide_empty' => false,
			'fields' => 'slugs'
		));
		
		if (!empty($regions) && !empty($features)) {
			// Create a regex pattern that only matches valid region/feature combinations
			$region_pattern = '(' . implode('|', array_map('preg_quote', $regions)) . ')';
			$feature_pattern = '(' . implode('|', array_map('preg_quote', $features)) . ')';
			
			add_rewrite_rule(
				'^' . $region_pattern . '/' . $feature_pattern . '/?$',
				'index.php?region_slug=$matches[1]&listing_feature_slug=$matches[2]',
				'top'
			);
		}
	}

	/**
	 * Add query vars for combined taxonomy URLs
	 */
	public function add_combined_taxonomy_query_vars($vars) {
		if ( get_option( 'listeo_combined_taxonomy_urls' ) ) {
			$vars[] = 'region_slug';
			$vars[] = 'listing_feature_slug';
		}
		return $vars;
	}

	/**
	 * Modify query for combined taxonomy pages
	 */
	public function modify_combined_taxonomy_query($query) {
		if (!is_admin() && $query->is_main_query()) {
			$region_slug = get_query_var('region_slug');
			$listing_feature_slug = get_query_var('listing_feature_slug');

			// IMPORTANT: Only modify if both slugs are present (combined taxonomy view)
			// This prevents breaking other taxonomy pages (blog categories, etc.)
			if (!$region_slug || !$listing_feature_slug) {
				return; // Exit early - don't modify other queries!
			}

			$region_term = get_term_by('slug', $region_slug, 'region');
			$listing_feature_term = get_term_by('slug', $listing_feature_slug, 'listing_feature');

			if ($region_term && $listing_feature_term) {
				  $query->set('post_type', 'listing');
            $query->set('post_status', 'publish');
            
            // Clear any existing taxonomy queries
            $query->set('tax_query', array(
                'relation' => 'AND',
                array(
                    'taxonomy' => 'region',
                    'field'    => 'term_id',
                    'terms'    => $region_term->term_id,
                ),
                array(
                    'taxonomy' => 'listing_feature',
                    'field'    => 'term_id',
                    'terms'    => $listing_feature_term->term_id,
                ),
            ));

            // Mark this as an archive page
            $query->is_archive = true;
            $query->is_home = false;
            $query->is_singular = false;
            $query->is_404 = false;
			}
		}
		return $query;
	}

	/**
	 * Include proper template for combined taxonomy pages
	 */
	public function combined_taxonomy_template_include($template) {
		$region_slug = get_query_var('region_slug');
		$listing_feature_slug = get_query_var('listing_feature_slug');

		if ($region_slug && $listing_feature_slug) {
			$new_template = locate_template(array('archive-listing.php'));
			if (!empty($new_template)) {
				return $new_template;
			}

			$listeo_template_loader = new Listeo_Core_Template_Loader;
			return $listeo_template_loader->get_template_part('archive', 'listing');
		}

		return $template;
	}

	/**
	 * Custom title for combined taxonomy pages
	 */
	public function combined_taxonomy_document_title($title) {
		$region_slug = get_query_var('region_slug');
		$listing_feature_slug = get_query_var('listing_feature_slug');

		if ($region_slug && $listing_feature_slug) {
			$region_term = get_term_by('slug', $region_slug, 'region');
			$listing_feature_term = get_term_by('slug', $listing_feature_slug, 'listing_feature');

			if ($region_term && $listing_feature_term) {
				$title['title'] = sprintf(__('%s in %s', 'listeo_core'), $listing_feature_term->name, $region_term->name);
			}
		}

		return $title;
	}

	/**
	 * Custom archive title for combined taxonomy pages
	 */
	public function combined_taxonomy_archive_title($title) {
		$region_slug = get_query_var('region_slug');
		$listing_feature_slug = get_query_var('listing_feature_slug');

		if ($region_slug && $listing_feature_slug) {
			$region_term = get_term_by('slug', $region_slug, 'region');
			$listing_feature_term = get_term_by('slug', $listing_feature_slug, 'listing_feature');

			if ($region_term && $listing_feature_term) {
				return sprintf(__('%s in %s', 'listeo_core'), $listing_feature_term->name, $region_term->name);
			}
		}

		return $title;
	}

	/**
	 * Custom wp_title for combined taxonomy pages
	 */
	public function combined_taxonomy_wp_title($title, $sep, $seplocation) {
		$region_slug = get_query_var('region_slug');
		$listing_feature_slug = get_query_var('listing_feature_slug');

		if ($region_slug && $listing_feature_slug) {
			$region_term = get_term_by('slug', $region_slug, 'region');
			$listing_feature_term = get_term_by('slug', $listing_feature_slug, 'listing_feature');

			if ($region_term && $listing_feature_term) {
				$new_title = sprintf(__('%s in %s', 'listeo_core'), $listing_feature_term->name, $region_term->name);
				
				if ($seplocation == 'right') {
					$title = $new_title . " $sep ";
				} else {
					$title = " $sep " . $new_title;
				}
			}
		}

		return $title;
	}

	/**
	 * Check if current page is a combined taxonomy page
	 */
	public static function is_combined_taxonomy_page() {
		$region_slug = get_query_var('region_slug');
		$listing_feature_slug = get_query_var('listing_feature_slug');
		
		return !empty($region_slug) && !empty($listing_feature_slug);
	}

	/**
	 * Get current combined taxonomy terms
	 */
	public static function get_combined_taxonomy_terms() {
		$region_slug = get_query_var('region_slug');
		$listing_feature_slug = get_query_var('listing_feature_slug');

		if ($region_slug && $listing_feature_slug) {
			$region_term = get_term_by('slug', $region_slug, 'region');
			$listing_feature_term = get_term_by('slug', $listing_feature_slug, 'listing_feature');

			if ($region_term && $listing_feature_term) {
				return array(
					'region' => $region_term,
					'listing_feature' => $listing_feature_term
				);
			}
		}

		return false;
	}

	/**
	 * Get combined taxonomy page title
	 */
	public static function get_combined_taxonomy_page_title() {
		$terms = self::get_combined_taxonomy_terms();
		
		if ($terms) {
			return sprintf(__('%s in %s', 'listeo_core'), $terms['listing_feature']->name, $terms['region']->name);
		}
		
		return '';
	}

	/**
	 * Protect WordPress core, WooCommerce, and Dokan URLs from Listeo rewrite rule conflicts
	 * This method ensures other plugins' URLs work while allowing Listeo custom permalinks
	 *
	 * @param array $rules Existing rewrite rules
	 * @return array Modified rewrite rules with protection
	 */
	public function protect_core_urls( $rules ) {
		$protected_rules = array();
		
		// PRIORITY 1: Protect WooCommerce URLs (these must come first) - FULLY DYNAMIC
		if ( class_exists( 'WooCommerce' ) ) {
			// Get WooCommerce permalink settings dynamically
			$wc_permalinks = wc_get_permalink_structure();
			
			// Product base (could be 'product', 'produkt', 'producto', etc.)
			$product_base = trim( $wc_permalinks['product_base'], '/' );
			$protected_rules['^' . $product_base . '/([^/]+)/?$'] = 'index.php?product=$matches[1]';
			$protected_rules['^' . $product_base . '/([^/]+)/([^/]+)/?$'] = 'index.php?product=$matches[1]&$matches[2]=$matches[2]';
			
			// Product category base (could be 'product-category', 'producto-categoria', etc.)
			$product_cat_base = trim( $wc_permalinks['category_base'], '/' );
			// Paginated rule MUST come before non-paginated (same reason as blog category above)
			$protected_rules['^' . $product_cat_base . '/(.+?)/page/?([0-9]{1,})/?$'] = 'index.php?product_cat=$matches[1]&paged=$matches[2]';
			$protected_rules['^' . $product_cat_base . '/(.+?)/?$'] = 'index.php?product_cat=$matches[1]';
			
			// Shop page base (could be 'shop', 'tienda', 'boutique', etc.)
			$shop_page_id = wc_get_page_id( 'shop' );
			if ( $shop_page_id > 0 ) {
				$shop_page = get_post( $shop_page_id );
				if ( $shop_page ) {
					$shop_base = $shop_page->post_name;
					$protected_rules['^' . $shop_base . '/?$'] = 'index.php?post_type=product';
					$protected_rules['^' . $shop_base . '/page/?([0-9]{1,})/?$'] = 'index.php?post_type=product&paged=$matches[1]';
				}
			}
		}
		
		// PRIORITY 2: Protect Dokan URLs - FULLY DYNAMIC
		if ( function_exists( 'dokan' ) ) {
			// Get Dokan settings dynamically
			$dokan_settings = get_option( 'dokan_pages' );
			
			// Store base (could be 'store', 'tienda', 'boutique', etc.)
			if ( isset( $dokan_settings['store_listing'] ) && $dokan_settings['store_listing'] > 0 ) {
				$store_page = get_post( $dokan_settings['store_listing'] );
				if ( $store_page ) {
					$store_base = $store_page->post_name;
					$protected_rules['^' . $store_base . '/([^/]+)/?$'] = 'index.php?store=$matches[1]';
					$protected_rules['^' . $store_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?store=$matches[1]&paged=$matches[2]';
				}
			} else {
				// Fallback to default if no custom page set
				$protected_rules['^store/([^/]+)/?$'] = 'index.php?store=$matches[1]';
				$protected_rules['^store/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?store=$matches[1]&paged=$matches[2]';
			}
			
			// Dashboard base (could be 'dashboard', 'panel', 'tablero', etc.)
			if ( isset( $dokan_settings['dashboard'] ) && $dokan_settings['dashboard'] > 0 ) {
				$dashboard_page = get_post( $dokan_settings['dashboard'] );
				if ( $dashboard_page ) {
					$dashboard_base = $dashboard_page->post_name;
					$protected_rules['^' . $dashboard_base . '/?$'] = 'index.php?pagename=' . $dashboard_base;
					$protected_rules['^' . $dashboard_base . '/([^/]+)/?$'] = 'index.php?pagename=' . $dashboard_base . '&dokan=$matches[1]';
				}
			} else {
				// Fallback to default if no custom page set
				$protected_rules['^dashboard/?$'] = 'index.php?pagename=dashboard';
				$protected_rules['^dashboard/([^/]+)/?$'] = 'index.php?pagename=dashboard&dokan=$matches[1]';
			}
		}
		
		// PRIORITY 3: Protect WordPress core URLs
		$category_base = get_option( 'category_base' ) ?: 'category';
		$tag_base = get_option( 'tag_base' ) ?: 'tag';

		// Paginated rule MUST come before non-paginated: (.+?) matches slashes, so /category/tips/page/2/
		// would match the non-paginated rule first if it were listed first.
		$protected_rules['^' . $category_base . '/(.+?)/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$protected_rules['^' . $category_base . '/(.+?)/?$'] = 'index.php?category_name=$matches[1]';
		$protected_rules['^' . $tag_base . '/([^/]+)/?$'] = 'index.php?tag=$matches[1]';
		$protected_rules['^' . $tag_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?tag=$matches[1]&paged=$matches[2]';
		
		// PRIORITY 4: Protect Listeo taxonomy URLs (get bases dynamically)
		$listeo_permalinks = self::get_permalink_structure();

		// Listing category base (can be translated like 'kategoria')
		if ( isset( $listeo_permalinks['category_rewrite_slug'] ) && ! empty( $listeo_permalinks['category_rewrite_slug'] ) ) {
			$listing_category_base = $listeo_permalinks['category_rewrite_slug'];
			$protected_rules['^' . $listing_category_base . '/([^/]+)/?$'] = 'index.php?listing_category=$matches[1]';
			$protected_rules['^' . $listing_category_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?listing_category=$matches[1]&paged=$matches[2]';
		}

		// Region + other Listeo taxonomies (translatable via _x()). Resolve
		// against the SITE locale so cached rewrite rules match the slugs
		// used by `register_taxonomy()` and `get_term_link()` on frontend.
		$region_base = self::get_site_locale_slug( 'region', 'Region slug - resave permalinks after changing this' );
		$protected_rules['^' . $region_base . '/([^/]+)/?$'] = 'index.php?region=$matches[1]';
		$protected_rules['^' . $region_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?region=$matches[1]&paged=$matches[2]';

		$event_category_base = self::get_site_locale_slug( 'events-category', 'Event Category slug - resave permalinks after changing this' );
		$protected_rules['^' . $event_category_base . '/([^/]+)/?$'] = 'index.php?event_category=$matches[1]';
		$protected_rules['^' . $event_category_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?event_category=$matches[1]&paged=$matches[2]';

		$service_category_base = self::get_site_locale_slug( 'service-category', 'Service Category slug - resave permalinks after changing this' );
		$protected_rules['^' . $service_category_base . '/([^/]+)/?$'] = 'index.php?service_category=$matches[1]';
		$protected_rules['^' . $service_category_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?service_category=$matches[1]&paged=$matches[2]';

		$rental_category_base = self::get_site_locale_slug( 'rental-category', 'Rental Category slug - resave permalinks after changing this' );
		$protected_rules['^' . $rental_category_base . '/([^/]+)/?$'] = 'index.php?rental_category=$matches[1]';
		$protected_rules['^' . $rental_category_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?rental_category=$matches[1]&paged=$matches[2]';

		$classifieds_category_base = self::get_site_locale_slug( 'classifieds-category', 'Classifieds Category slug - resave permalinks after changing this' );
		$protected_rules['^' . $classifieds_category_base . '/([^/]+)/?$'] = 'index.php?classifieds_category=$matches[1]';
		$protected_rules['^' . $classifieds_category_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?classifieds_category=$matches[1]&paged=$matches[2]';

		$listing_feature_base = self::get_site_locale_slug( 'listing-feature', 'Feature slug - resave permalinks after changing this' );
		$protected_rules['^' . $listing_feature_base . '/([^/]+)/?$'] = 'index.php?listing_feature=$matches[1]';
		$protected_rules['^' . $listing_feature_base . '/([^/]+)/page/?([0-9]{1,})/?$'] = 'index.php?listing_feature=$matches[1]&paged=$matches[2]';
		
		// PRIORITY 5: Handle Combined Taxonomy URLs (region + feature combinations) - DYNAMIC
		if ( get_option( 'listeo_combined_taxonomy_urls' ) ) {
			// Get all actual region and feature slugs dynamically
			// $regions = get_terms( array(
			// 	'taxonomy' => 'region',
			// 	'hide_empty' => false,
			// 	'fields' => 'slugs'
			// ) );
			
			// $features = get_terms( array(
			// 	'taxonomy' => 'listing_feature', 
			// 	'hide_empty' => false,
			// 	'fields' => 'slugs'
			// ) );
			
			// if ( ! empty( $regions ) && ! empty( $features ) ) {
			// 	// Create specific patterns for each region+feature combination to avoid conflicts
			// 	foreach ( $regions as $region_slug ) {
			// 		foreach ( $features as $feature_slug ) {
			// 			// Very specific pattern: exact region + exact feature
			// 			$protected_rules['^' . preg_quote( $region_slug ) . '/' . preg_quote( $feature_slug ) . '/?$'] = 
			// 				'index.php?region_slug=' . $region_slug . '&listing_feature_slug=' . $feature_slug;
						
			// 			// With pagination
			// 			$protected_rules['^' . preg_quote( $region_slug ) . '/' . preg_quote( $feature_slug ) . '/page/?([0-9]{1,})/?$'] = 
			// 				'index.php?region_slug=' . $region_slug . '&listing_feature_slug=' . $feature_slug . '&paged=$matches[1]';
			// 		}
			// 	}
			// }
		}
		
		// Add protected rules at the beginning, then existing rules
		// This ensures core/WooCommerce/Dokan URLs work while allowing Listeo custom permalinks for everything else
		return array_merge( $protected_rules, $rules );
	}


	/**
	 * Initialize custom permalink settings on first installation
	 * Sets safe defaults to prevent conflicts
	 */
	public function enable_custom_permalink_settings() {
		$settings = get_option( 'listeo_core_permalinks', '{}' );
		$settings_array = json_decode( $settings, true );
		if ( ! is_array( $settings_array ) ) {
			$settings_array = array();
		}

		// Only set defaults on first installation (when the setting doesn't exist at all)
		if ( ! isset( $settings_array['custom_permalinks_enabled'] ) ) {
			// First time setup - DISABLE custom permalinks by default for safety
			// Users can enable them manually if needed
			$settings_array['custom_permalinks_enabled'] = '0';  // Disabled by default
			$settings_array['custom_structure'] = '%listing%';  // Simple structure as default
			$settings_array['permalink_safe_mode'] = '1';  // Enable safe mode by default
			$settings_array['enable_redirects'] = '0';  // Disable redirects by default
			update_option( 'listeo_core_permalinks', json_encode( $settings_array ) );
		}
		// If custom_permalinks_enabled exists, respect the user's choice

		// Enable region links for custom permalink structures that need them
		// BUT only if the new custom permalinks toggle is not being used
		if ( !get_option('listeo_enable_custom_permalinks', false) && is_array( $settings_array ) && isset( $settings_array['custom_permalinks_enabled'] ) && $settings_array['custom_permalinks_enabled'] === '1' ) {
			$custom_structure = isset( $settings_array['custom_structure'] ) ? $settings_array['custom_structure'] : '';
			// Enable region links if the custom structure uses regions
			if ( strpos( $custom_structure, '%region%' ) !== false ) {
				update_option( 'listeo_region_in_links', true );
			}
		} else if ( get_option('listeo_enable_custom_permalinks', false) === false ) {
			// If custom permalinks are disabled, also disable region in links
			// This prevents the legacy region system from interfering
			update_option( 'listeo_region_in_links', false );
		}
	}

	/**
	 * Get current language code from multilingual plugins
	 *
	 * Supports WPML, Polylang, TranslatePress, and falls back to WordPress locale
	 *
	 * @return string Language code (e.g., 'en', 'de', 'fr')
	 */
	private function get_current_language() {
		// Check for WPML
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			return ICL_LANGUAGE_CODE;
		}

		// Check for Polylang
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language( 'slug' );
			if ( $lang ) {
				return $lang;
			}
		}

		// Check for TranslatePress
		global $TRP_LANGUAGE;
		if ( ! empty( $TRP_LANGUAGE ) ) {
			// TranslatePress uses full locale (e.g., 'en_US'), extract language code
			$lang_parts = explode( '_', $TRP_LANGUAGE );
			return $lang_parts[0];
		}

		// Fallback to WordPress locale
		$locale = get_locale();
		$lang_parts = explode( '_', $locale );
		return $lang_parts[0];
	}


}
