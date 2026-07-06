<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Listeo_Core {

	/**
	 * The single instance of Listeo_Core.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	public $post_types;
	public $meta_boxes;
	public $listing;
	public $reviews;
	public $submit;
	public $search;
	public $users;
	public $bookmarks;
	public $activites_log;
	public $messages;
	public $calendar;
	public $calendar_view;
	public $emails;
	public $commissions;
	public $payouts;
	public $ical;
	public $coupons;
	public $stripe;
	public $sitehealth;
	public $stats;
	public $chart;
	public $claims;
	public $ads;
	public $qr;
	public $reports;
	public $saved_searches;
	//public $claims;
		
	public $localize_array;
	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $file = '', $version = '1.9.14' ) {
		$this->_version = $version;
		
		$this->_token = 'listeo_core';

		// Load plugin environment variables
		$this->file = $file;
		
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		//$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$this->script_suffix =  '.min';
		register_activation_hook( $this->file, array( $this, 'install' ) );


		define( 'LISTEO_CORE_ASSETS_DIR', trailingslashit( $this->dir ) . 'assets' );
		define( 'LISTEO_CORE_ASSETS_URL', esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) ) );

		// Add translation loading to plugins_loaded hook (before admin_notices)
		add_action('plugins_loaded', array($this, 'load_plugin_textdomain'), 10);
		add_action('init', array($this, 'load_localisation'), 1);

		// Run review criteria migration (v2.0 - per-type/per-taxonomy criteria)
		add_action('plugins_loaded', array($this, 'maybe_migrate_reviews_criteria'), 15);
		
		require_once( 'class-listeo-core-post-types.php' );
		require_once( 'class-listeo-core-meta-boxes.php' );
		require_once( 'class-listeo-core-listing.php' );
		require_once( 'class-listeo-core-reviews.php' );
		require_once( 'class-listeo-core-reviews-migration.php' );
		require_once( 'class-listeo-core-submit.php' );
		require_once( 'class-listeo-core-shortcodes.php' );
		require_once( 'class-listeo-core-search.php' );
		require_once( 'class-listeo-core-users.php' );
		require_once( 'class-listeo-core-bookmarks.php' );
		require_once( 'class-listeo-core-coupons.php' );
		require_once( 'class-listeo-core-activities-log.php' );
		require_once( 'class-listeo-core-calendar.php' );
		require_once( 'class-listeo-core-emails.php' );
		require_once( 'class-listeo-core-messages.php' );
		require_once( 'class-listeo-core-bookings-calendar.php' );
		require_once( 'class-listeo-core-calendar-view.php' );
		require_once( 'class-listeo-core-commissions.php' );
		require_once( 'class-listeo-core-payouts.php' );
		require_once( 'class-listeo-core-claim-listings.php' );
		require_once( 'class-listeo-core-ads.php' );
		require_once( 'class-listeo-core-bookings-admin.php' );
		require_once( 'class-listeo-core-stats.php' );
		require_once( 'class-listeo-core-chart.php' );
		require_once( 'class-listeo-stripe-connect.php' );
		require_once( 'class-listeo-core-site-health.php' );
		require_once( 'class-listeo-core-qr.php' );
		require_once( 'class-listeo-core-report-listing.php' );
		require_once( 'class-listeo-core-details-handler.php' );
		require_once( 'class-listeo-core-saved-searches.php' );
		//require_once( 'class-icalreader.php' );
		require_once( 'ical/listeo-core-ical.php' );
		require_once( 'class-listeo-core-ical.php' );
		// include( 'class-listeo-core-compare.php' );
		
		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		add_action( 'wp_ajax_handle_dropped_media', array( $this, 'listeo_core_handle_dropped_media' ));
		// Removed wp_ajax_nopriv_ hooks for security - unauthenticated users cannot upload/delete media
		add_action( 'wp_ajax_handle_delete_media',  array( $this, 'listeo_core_handle_delete_media' ));

		add_filter('cron_schedules',array( $this, 'listeo_cron_schedules'));
		if ( class_exists( 'Listeo_Core_Environment_Sync' ) ) {
			Listeo_Core_Environment_Sync::init();
		}

		add_action('wp_ajax_listingAutocompleteSearch',array( $this, 'listing_autocomplete_search')); 
		// Load API for generic admin functions
		// if ( is_admin() ) {
		// 	$this->admin = new Listeo_Core_Admin_API();
		// }
		
		$this->post_types 	= Listeo_Core_Post_Types::instance();
		$this->meta_boxes 	= new Listeo_Core_Meta_Boxes();
		$this->listing 		= new Listeo_Core_Listing();
		$this->reviews 		= new Listeo_Core_Reviews();
		//$this->submit 		= Listeo_Core_Submit::instance();
		
		$this->search 		= new Listeo_Core_Search();
		$this->users 		= new Listeo_Core_Users();
		$this->bookmarks 	= new Listeo_Core_Bookmarks();
		$this->activites_log = new Listeo_Core_Activities_Log();
		$this->messages 	= new Listeo_Core_Messages();
		$this->calendar 	= Listeo_Core_Calendar::instance();
		$this->calendar_view 	= Listeo_Core_Calendar_View::instance();
		$this->emails 		= Listeo_Core_Emails::instance();
		$this->commissions 	= Listeo_Core_Commissions::instance();
		$this->payouts 		= Listeo_Core_Payouts::instance();
		$this->ical 		= Listeo_Core_iCal::instance();
		$this->coupons 		= new Listeo_Core_Coupons();
		$this->stripe 		= new ListeoStripeConnect();
		$this->sitehealth 		= new Listeo_Core_Site_Health();
		$this->claims 		= new Listeo_Core_Claim_Listings();
		$this->ads 		= new Listeo_Core_Ads();
		$this->qr 		= new Listeo_Core_QR();
		$this->reports = Listeo_Core_Report_Feature::get_instance();
		$this->saved_searches = Listeo_Core_Saved_Searches::instance();

		if(get_option('listeo_stats_status')) {
			$this->stats 		= new Listeo_Core_Stats();
			$this->chart 		= new Listeo_Core_Chart();
		}

		// Initialize localize_array as empty - will be populated later when text domain is loaded
		$this->localize_array = array();

		// Handle localisation
		// $this->load_plugin_textdomain();
		// add_action( 'init', array( $this, 'load_localisation' ), 0 );
		add_action( 'init', array( $this, 'image_size' ) );
		add_action( 'init', array( $this, 'register_sidebar' ) );
		
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		
		add_action( 'after_setup_theme', array( $this, 'include_template_functions' ), 1 );

		add_filter( 'template_include', array( $this, 'listing_templates' ) );

		add_action('init', array( $this, 'init_plugin' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_1_3_2' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_1_5_18' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_1_5_19' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_1_5_20' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_1_9_51' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_2_2' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_2_5' ), 13 );
		add_action( 'plugins_loaded', array( $this, 'listeo_core_update_db_2_5_1' ), 13 );

		add_action( 'admin_notices', array( $this, 'google_api_notice' ));


		add_action('wp_head',  array( $this, 'listeo_og_image' ));

		// Schedule cron jobs
		self::maybe_schedule_cron_jobs();

		// Setup cache invalidation hooks
		add_action('save_post', array($this, 'clear_price_cache_on_listing_update'), 10, 3);
		add_action('delete_post', array($this, 'clear_price_cache_on_listing_delete'), 10, 1);
		add_action('updated_post_meta', array($this, 'clear_price_cache_on_meta_update'), 10, 4);


	} // End __construct ()
	  
	/**
	 * Widgets init
	 */
	public function widgets_init() {
		// Load new booking system BEFORE widgets
		if (file_exists(plugin_dir_path(__FILE__) . 'booking/class-listeo-core-booking-autoloader.php')) {
			
			include_once( 'booking/class-listeo-core-booking-autoloader.php' );
			Listeo_Core_Booking_Autoloader::init();
		}
		
		include( 'class-listeo-core-widgets.php' );
	}



	public function include_template_functions() {
		include( LISTEO_PLUGIN_DIR.'/listeo-core-template-functions.php' );
		include( LISTEO_PLUGIN_DIR.'/includes/paid-listings/listeo-core-paid-listings-functions.php' );
		
		
	}

	/**
	 * Get all registered listing taxonomies dynamically (static version).
	 * Compatible with Listing Type Editor.
	 *
	 * @access private
	 * @since  1.9.52
	 * @return array Array of taxonomy slugs
	 */
	private static function get_listing_taxonomies_static() {
		$taxonomies = array('listing_category', 'region', 'listing_feature');

		// Get listing types from Custom Types Manager if available
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$listing_types = $custom_types_manager->get_listing_types(true); // Get active types only

			foreach ($listing_types as $type) {
				// Add taxonomy for this listing type if it should be registered
				if (isset($type->register_taxonomy) && $type->register_taxonomy) {
					$taxonomies[] = $type->slug . '_category';
				}
			}
		} else {
			// Fallback to default listing types if Custom Types Manager not available
			$listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));

			if (is_array($listing_types)) {
				foreach ($listing_types as $type_slug) {
					$taxonomies[] = $type_slug . '_category';
				}
			}
		}

		return apply_filters('listeo_core_listing_taxonomies', array_unique($taxonomies));
	}

	/* handles single listing and archive listing view */
	public static function listing_templates( $template ) {
		$post_type = get_post_type();
		$custom_post_types = array( 'listing' );

		$template_loader = new Listeo_Core_Template_Loader;
		if ( in_array( $post_type, $custom_post_types ) ) {

			if ( is_archive() && !is_author() ) {

				$template = $template_loader->locate_template('archive-' . $post_type . '.php');

				return $template;
			}

			if ( is_single() ) {
				$gallery_type = get_option('listeo_gallery_type','grid');
				if($gallery_type == 'grid'){
					$template = $template_loader->locate_template('single-' . $post_type . '-gallery-grid.php');
				} else {
					$template = $template_loader->locate_template('single-' . $post_type . '.php');
				}


				return $template;
			}
		}

		// Check if we're viewing any listing taxonomy (dynamic)
		$listing_taxonomies = self::get_listing_taxonomies_static();
		$is_listing_tax = false;
		foreach ($listing_taxonomies as $taxonomy) {
			if (is_tax($taxonomy)) {
				$is_listing_tax = true;
				break;
			}
		}

		if ($is_listing_tax) {
			$template = $template_loader->locate_template('archive-listing.php');
		}

		if( is_post_type_archive( 'listing' ) ){

			$template = $template_loader->locate_template('archive-listing.php');

		}


		return $template;
	}

	/**
	 * Initialize localize array with translated strings.
	 * This method should be called after text domain is loaded to prevent translation warnings.
	 *
	 * @access private
	 * @since  1.0.0
	 * @return void
	 */
	private function init_localize_array() {
		// Only calculate prices on pages that actually need them
		if ($this->page_needs_price_ranges()) {
			$this->init_localize_array_with_prices();
		} else {
			$this->init_basic_localize_array();
		}
	}

	/**
	 * Initialize basic localize array without expensive price calculations.
	 * Used for pages that don't need global price ranges.
	 *
	 * @access private
	 * @since  1.9.51
	 * @return void
	 */
	private function init_basic_localize_array() {
		$ajax_url = admin_url('admin-ajax.php', 'relative');
		$currency = get_option('listeo_currency');
		$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency, false);
		
		$this->localize_array = array(
			'ajax_url'                	=> $ajax_url,
			'payout_not_valid_email_msg'  => esc_html__('The email address is not valid. Please add a valid email address.', 'listeo_core'),
			'is_rtl'                  	=> is_rtl() ? 1 : 0,
			'lang'                    	=> defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : '', // WPML workaround until this is standardized
			'wp_locale'               	=> str_replace('_', '-', strtolower(get_locale())), // WordPress locale for Moment.js
			'currency'		      		=> apply_filters('listeo_core_currency_symbol', get_option('listeo_currency')),
			'currency_position'		    => get_option('listeo_currency_postion'),
			'currency_symbol'		    => apply_filters('listeo_core_currency_symbol', esc_attr($currency_symbol)),
			'submitCenterPoint'		    => get_option('listeo_submit_center_point', '52.2296756,21.012228700000037'),
			'centerPoint'		      	=> get_option('listeo_map_center_point', '29.577712,-45.629483'),
			'country'		      		=> get_option('listeo_maps_limit_country'),
			'upload'					=> admin_url('admin-ajax.php?action=handle_dropped_media'),
			'delete'					=> admin_url('admin-ajax.php?action=handle_delete_media'),
			'upload_nonce'				=> wp_create_nonce('listeo_core_upload_nonce'),
			'color'						=> get_option('pp_main_color', '#274abb'),
			'dictDefaultMessage'		=> esc_html__("Drop files here to upload", "listeo_core"),
			'dictFallbackMessage' 		=> esc_html__("Your browser does not support drag'n'drop file uploads.", "listeo_core"),
			'dictFallbackText' 			=> esc_html__("Please use the fallback form below to upload your files like in the olden days.", "listeo_core"),
			'dictFileTooBig' 			=> esc_html__("File is too big ({{filesize}}MiB). Max filesize: {{maxFilesize}}MiB.", "listeo_core"),
			'dictInvalidFileType' 		=> esc_html__("You can't upload files of this type.", "listeo_core"),
			'dictResponseError'		 	=> esc_html__("Server responded with {{statusCode}} code.", "listeo_core"),
			'dictCancelUpload' 			=> esc_html__("Cancel upload", "listeo_core"),
			'dictCancelUploadConfirmation' => esc_html__("Are you sure you want to cancel this upload?", "listeo_core"),
			'dictRemoveFile' 			=> esc_html__("Remove file", "listeo_core"),
			'dictMaxFilesExceeded' 		=> esc_html__("You can not upload any more files.", "listeo_core"),
			'areyousure' 				=> esc_html__("Are you sure?", "listeo_core"),
			'stripe_express_nonce'		=> wp_create_nonce('listeo_stripe_express'),
			'maxFiles' 					=> get_option('listeo_max_files', 10),
			'maxFilesize' 				=> get_option('listeo_max_filesize', 2),
			'clockformat' 				=> (get_option('listeo_clock_format', '12') == '24') ? true : false,
			'prompt_price'				=> esc_html__('Set price for this date', 'listeo_core'),
			'menu_price'				=> esc_html__('Price (optional)', 'listeo_core'),
			'menu_desc'					=> esc_html__('Description', 'listeo_core'),
			'menu_title'				=> esc_html__('Title', 'listeo_core'),
			'pricing_label_price'		=> esc_html__('Price', 'listeo_core'),
			'pricing_label_charge_type'	=> esc_html__('Charge Type', 'listeo_core'),
			"applyLabel"				=> esc_html__("Apply", 'listeo_core'),
			"cancelLabel" 				=> esc_html__("Cancel", 'listeo_core'),
			"clearLabel" 				=> esc_html__("Clear", 'listeo_core'),
			"fromLabel"					=> esc_html__("From", 'listeo_core'),
			"toLabel" 					=> esc_html__("To", 'listeo_core'),
			"customRangeLabel" 			=> esc_html__("Custom", 'listeo_core'),
			"next_page_listings_text"	=> esc_html__("Show next %d listings", 'listeo_core'),
			"infinite_scroll" 			=> get_option('listeo_listeo_infinite_scroll', 'off'),
			"mmenuTitle" 				=> esc_html__("Menu", 'listeo_core'),
			"pricingTooltip" 			=> esc_html__("Click to make this item bookable in booking widget", 'listeo_core'),
			"today" 					=> esc_html__("Today", 'listeo_core'),
			"tomorrow" 					=> esc_html__("Tomorrow", 'listeo_core'),
			"yesterday" 				=> esc_html__("Yesterday", 'listeo_core'),
			"last_7_days" 				=> esc_html__("Last 7 Days", 'listeo_core'),
			"last_30_days" 				=> esc_html__("Last 30 Days", 'listeo_core'),
			"this_month" 				=> esc_html__("This Month", 'listeo_core'),
			"last_month" 				=> esc_html__("Last Month", 'listeo_core'),
			"show_more_slots" 			=> esc_html__("Show %d more", 'listeo_core'),
			"map_provider" 				=> get_option('listeo_map_provider', 'osm'),
			"address_provider" 			=> get_option('listeo_map_address_provider', 'osm'),
			"mapbox_access_token" 		=> get_option('listeo_mapbox_access_token'),
			"mapbox_retina" 			=> get_option('listeo_mapbox_retina'),
			"mapbox_style_url" 			=> get_option('listeo_mapbox_style_url') ? get_option('listeo_mapbox_style_url') : 'https://api.mapbox.com/styles/v1/mapbox/streets-v11/tiles/{z}/{x}/{y}@2x?access_token=',
			"bing_maps_key" 			=> get_option('listeo_bing_maps_key'),
			"thunderforest_api_key" 	=> get_option('listeo_thunderforest_api_key'),
			"here_app_id" 				=> get_option('listeo_here_app_id'),
			"here_app_code" 			=> get_option('listeo_here_app_code'),
			"maps_reviews_text" 		=> esc_html__('reviews', 'listeo_core'),
			"maps_noreviews_text" 		=> esc_html__('Not rated yet', 'listeo_core'),
			'map_bounds_search' => get_option('listeo_map_bounds_search', 'on'),
			"category_title" 			=> esc_html__('Category Title', 'listeo_core'),
			"day_short_su" => esc_html_x("Su", 'Short for Sunday', 'listeo_core'),
			"day_short_mo" => esc_html_x("Mo", 'Short for Monday', 'listeo_core'),
			"day_short_tu" => esc_html_x("Tu", 'Short for Tuesday', 'listeo_core'),
			"day_short_we" => esc_html_x("We", 'Short for Wednesday', 'listeo_core'),
			"day_short_th" => esc_html_x("Th", 'Short for Thursday', 'listeo_core'),
			"day_short_fr" => esc_html_x("Fr", 'Short for Friday', 'listeo_core'),
			"day_short_sa" => esc_html_x("Sa", 'Short for Saturday', 'listeo_core'),
			"radius_state" => get_option('listeo_radius_state'),
			"maps_autofit" => get_option('listeo_map_autofit', 'on'),
			"maps_autolocate" 	=> get_option('listeo_map_autolocate'),
			"maps_zoom" 		=> (!empty(get_option('listeo_map_zoom_global'))) ? get_option('listeo_map_zoom_global') : 9,
			"maps_single_zoom" 	=> (!empty(get_option('listeo_map_zoom_single'))) ? get_option('listeo_map_zoom_single') : 9,
			"autologin" 	=> get_option('listeo_autologin'),
			'required_fields' 	=> esc_html__('Please fill all required  fields', 'listeo_core'),
			'exceed_guests_limit' => esc_html__('The total number of adults and children cannot exceed the maximum guest limit', 'listeo_core'),
			"no_results_text" 	=> esc_html__('No results match', 'listeo_core'),
			"no_results_found_text" 	=> esc_html__('No results found', 'listeo_core'),
			"placeholder_text_single" 	=> esc_html__('Select an Option', 'listeo_core'),
			"placeholder_text_multiple" => esc_html__('Select Some Options ', 'listeo_core'),
			"january" => esc_html__("January", 'listeo_core'),
			"february" => esc_html__("February", 'listeo_core'),
			"march" => esc_html__("March", 'listeo_core'),
			"april" => esc_html__("April", 'listeo_core'),
			"may" => esc_html__("May", 'listeo_core'),
			"june" => esc_html__("June", 'listeo_core'),
			"july" => esc_html__("July", 'listeo_core'),
			"august" => esc_html__("August", 'listeo_core'),
			"september" => esc_html__("September", 'listeo_core'),
			"october" => esc_html__("October", 'listeo_core'),
			"november" => esc_html__("November", 'listeo_core'),
			"december" => esc_html__("December", 'listeo_core'),
			"month_abbrev_jan" => esc_html_x("Jan", 'January abbreviation', 'listeo_core'),
			"month_abbrev_feb" => esc_html_x("Feb", 'February abbreviation', 'listeo_core'),
			"month_abbrev_mar" => esc_html_x("Mar", 'March abbreviation', 'listeo_core'),
			"month_abbrev_apr" => esc_html_x("Apr", 'April abbreviation', 'listeo_core'),
			"month_abbrev_may" => esc_html_x("May", 'May abbreviation', 'listeo_core'),
			"month_abbrev_jun" => esc_html_x("Jun", 'June abbreviation', 'listeo_core'),
			"month_abbrev_jul" => esc_html_x("Jul", 'July abbreviation', 'listeo_core'),
			"month_abbrev_aug" => esc_html_x("Aug", 'August abbreviation', 'listeo_core'),
			"month_abbrev_sep" => esc_html_x("Sep", 'September abbreviation', 'listeo_core'),
			"month_abbrev_oct" => esc_html_x("Oct", 'October abbreviation', 'listeo_core'),
			"month_abbrev_nov" => esc_html_x("Nov", 'November abbreviation', 'listeo_core'),
			"month_abbrev_dec" => esc_html_x("Dec", 'December abbreviation', 'listeo_core'),
			"opening_time" => esc_html__("Opening Time", 'listeo_core'),
			"closing_time" => esc_html__("Closing Time", 'listeo_core'),
			"remove" => esc_html__("Remove", 'listeo_core'),
			"extra_services_options_type" => get_option('listeo_extra_services_options_type', array()),
			"onetimefee" => esc_html__("One time fee", 'listeo_core'),
			"bookable_quantity_max" => esc_html__("Max quantity", 'listeo_core'),
			"multiguest" => esc_html__("Multiply by guests", 'listeo_core'),
			"multidays" => esc_html__("Multiply by days", 'listeo_core'),
			"multiguestdays" => esc_html__("Multiply by guest & days", 'listeo_core'),
			"quantitybuttons" => esc_html__("Allow quantity", 'listeo_core'),
			"booked_dates" => esc_html__("Those dates are already booked", 'listeo_core'),
			"replied" => esc_html__("Replied", 'listeo_core'),
			'hcaptcha_sitekey'      => trim(get_option('listeo_hcaptcha_sitekey')),
			'turnstile_sitekey'     => trim(get_option('listeo_turnstile_sitekey')),
			"elementor_single_gallery" => esc_html__("Gallery", 'listeo_core'),
			"elementor_single_overview" => esc_html__("Overview", 'listeo_core'),
			"elementor_single_details" => esc_html__("Details", 'listeo_core'),
			"elementor_single_pricing" => esc_html__("Pricing", 'listeo_core'),
			"elementor_single_store" => esc_html__("Store", 'listeo_core'),
			"elementor_single_video" => esc_html__("Video", 'listeo_core'),
			"elementor_single_location" => esc_html__("Location", 'listeo_core'),
			"elementor_single_faq" => esc_html__("FAQ", 'listeo_core'),
			"elementor_single_reviews" => esc_html__("Reviews", 'listeo_core'),
			"elementor_single_map" => esc_html__("Location", 'listeo_core'),
			"otp_status" => get_option('listeo_otp_status', 'on'),
			'start_time_label' => esc_html__('Start Time', 'listeo_core'),
			'end_time_label' => esc_html__('End Time', 'listeo_core'),
			'back' => esc_html__('Back', 'listeo_core'),
			'search' => esc_html__('Search', 'listeo_core'),
			'copytoalldays' => esc_html__('Copy to all days', 'listeo_core'),
			'selectimefirst' => esc_html__('Please select time first', 'listeo_core'),
			'unblock' => esc_html__('Unblock', 'listeo_core'),
			'block' => esc_html__('Block', 'listeo_core'),
			'setprice' => esc_html__('Set Price', 'listeo_core'),
			'one_date_selected' => esc_html__('1 date selected', 'listeo_core'),
			'dates_selected' => esc_html__(' date(s) selected', 'listeo_core'),
			'enterPrice' => __('Enter price for', 'listeo_core'),
			'leaveBlank' => __('Leave blank to remove price', 'listeo_core'),
			'selectedTerm' => __('Selected Term', 'listeo_core'),
			'customField' => __('Custom Field', 'listeo_core'),
			'customFields' => __('Custom Fields', 'listeo_core'),
			'customFieldsFor' => __('Custom fields for', 'listeo_core'),
			'next' => __('Next', 'listeo_core'),
			'prev' => __('Previous', 'listeo_core'),
			'radius_unit' => get_option('listeo_radius_unit', 'km'),
			'user_location_text' => esc_html__('Your Search Location', 'listeo_core'),
			'radius_text' => esc_html__('Search Radius', 'listeo_core'),
			'google_maps_id' => get_option('listeo_google_maps_id', ''),
			'dateParseError' => esc_html__('Could not determine event date. Please contact support.', 'listeo_core')
		);
	}

	/**
	 * Initialize full localize array with price calculations.
	 * Used only for pages that need global price ranges.
	 *
	 * @access private
	 * @since  1.9.51
	 * @return void
	 */
	private function init_localize_array_with_prices() {
		// Get cached price data or calculate if needed
		$price_data = $this->get_cached_price_ranges();

		// Initialize basic array first
		$this->init_basic_localize_array();

		// Add price data
		$this->localize_array['_price_min'] = $price_data['_price_min'];
		$this->localize_array['_price_max'] = $price_data['_price_max'];
	}

	/**
	 * Get cached price ranges or calculate if cache is empty.
	 * Uses WordPress transients for caching.
	 *
	 * @access private
	 * @since  1.9.51
	 * @return array
	 */
	private function get_cached_price_ranges() {
		$cache_key = 'listeo_price_ranges';
		$price_data = wp_cache_get($cache_key, 'listeo_core');

		if ($price_data === false) {
			$price_data = array(
				'_price_min' => $this->get_min_all_listing_price(''),
				'_price_max' => $this->get_max_all_listing_price('')
			);
			wp_cache_set($cache_key, $price_data, 'listeo_core', HOUR_IN_SECONDS);
		}

		return $price_data;
	}

	/**
	 * Get all registered listing taxonomies dynamically.
	 * Compatible with Listing Type Editor.
	 *
	 * @access private
	 * @since  1.9.52
	 * @return array Array of taxonomy slugs
	 */
	private function get_listing_taxonomies() {
		$taxonomies = array('listing_category', 'region', 'listing_feature');

		// Get listing types from Custom Types Manager if available
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$listing_types = $custom_types_manager->get_listing_types(true); // Get active types only

			foreach ($listing_types as $type) {
				// Add taxonomy for this listing type if it should be registered
				if (isset($type->register_taxonomy) && $type->register_taxonomy) {
					$taxonomies[] = $type->slug . '_category';
				}
			}
		} else {
			// Fallback to default listing types if Custom Types Manager not available
			$listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));

			if (is_array($listing_types)) {
				foreach ($listing_types as $type_slug) {
					$taxonomies[] = $type_slug . '_category';
				}
			}
		}

		return apply_filters('listeo_core_listing_taxonomies', array_unique($taxonomies));
	}

	/**
	 * Check if current page needs global price ranges.
	 * Only pages with search forms need expensive price calculations.
	 *
	 * @access private
	 * @since  1.9.51
	 * @return bool
	 */
	private function page_needs_price_ranges() {
		// Skip price calculations in admin area entirely
		if (is_admin()) {
			return false;
		}

		// Only specific frontend pages need global price ranges
		return (
			is_post_type_archive('listing') ||           // Listings archive
			is_tax($this->get_listing_taxonomies()) ||   // Category pages (dynamic)
			is_home() ||                                  // Homepage
			is_front_page() ||                           // Front page
			is_page(get_option('listeo_search_page')) || // Search page
			$this->page_has_search_shortcode()           // Custom pages with search
		);
	}

	/**
	 * Check if current page contains search-related shortcodes.
	 *
	 * @access private
	 * @since  1.9.51
	 * @return bool
	 */
	private function page_has_search_shortcode() {
		global $post;
		if (!$post || !$post->post_content) return false;

		// Check for search-related shortcodes
		return (
			has_shortcode($post->post_content, 'listeo_search_form') ||
			has_shortcode($post->post_content, 'listeo_listings') ||
			has_shortcode($post->post_content, 'listeo_homepage_search') ||
			strpos($post->post_content, 'listeo-search') !== false
		);
	}

	/**
	 * Initialize lightweight localize array for admin area.
	 * Admin pages don't need expensive price calculations.
	 *
	 * @access private
	 * @since  1.9.51
	 * @return void
	 */
	private function init_admin_localize_array() {
		$ajax_url = admin_url('admin-ajax.php', 'relative');
		$currency = get_option('listeo_currency');
		$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency, false);

		$this->localize_array = array(
			'ajax_url'                	=> $ajax_url,
			'is_rtl'                  	=> is_rtl() ? 1 : 0,
			'lang'                    	=> defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : '',
			'currency'		      		=> apply_filters('listeo_core_currency_symbol', get_option('listeo_currency')),
			'currency_position'		    => get_option('listeo_currency_postion'),
			'currency_symbol'		    => apply_filters('listeo_core_currency_symbol', esc_attr($currency_symbol)),
			'clockformat' 				=> (get_option('listeo_clock_format', '12') == '24') ? true : false,
			// Add essential admin-specific strings without expensive calculations
			'areyousure' 				=> esc_html__("Are you sure?", "listeo_core"),
			'required_fields' 			=> esc_html__('Please fill all required fields', 'listeo_core'),
			'no_results_text' 			=> esc_html__('No results match', 'listeo_core'),
			'placeholder_text_single' 	=> esc_html__('Select an Option', 'listeo_core'),
			'placeholder_text_multiple' => esc_html__('Select Some Options ', 'listeo_core'),
		);
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );

		if (get_option('listeo_otp_status') && !is_user_logged_in() && get_option('users_can_register')) {
			wp_register_style( $this->_token . '-intltelinput.css', esc_url( $this->assets_url ) . 'css/intltelinput.css', array(), $this->_version );
			wp_enqueue_style( $this->_token . '-intltelinput.css' );
		}


	} // End enqueue_styles ()



	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts() {
		
		// wp_register_script(	'dropzone', esc_url( $this->assets_url ) . 'js/dropzone.js', array( 'jquery' ), $this->_version, true );
		wp_register_script(	'uploads', esc_url( $this->assets_url ) . 'js/uploads.min.js', array( 'jquery' ), $this->_version, true );
		wp_register_script(	'ajaxsearch', esc_url( $this->assets_url ) . 'js/ajax.search.min.js', array( 'jquery' ), $this->_version, true );
		//wp_register_script('intlTelInput', esc_url( $this->assets_url ) . 'js/intlTelInput.min.js', array( 'jquery' ), $this->_version, true );
		
		wp_register_script( $this->_token . '-leaflet-markercluster', esc_url( $this->assets_url ) . 'js/leaflet.markercluster.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-leaflet-geocoder', esc_url( $this->assets_url ) . 'js/control.geocoder.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-leaflet-search', esc_url( $this->assets_url ) . 'js/leaflet-search.src.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-leaflet-bing-layer', esc_url( $this->assets_url ) . 'js/leaflet-bing-layer.min.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-leaflet-google-maps', esc_url( $this->assets_url ) . 'js/leaflet-googlemutant.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-leaflet-tilelayer-here', esc_url( $this->assets_url ) . 'js/leaflet-tilelayer-here.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-leaflet-gesture-handling', esc_url( $this->assets_url ) . 'js/leaflet-gesture-handling.min.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-leaflet', esc_url( $this->assets_url ) . 'js/listeo.leaflet.js', array( 'jquery' ), $this->_version );

		wp_register_script( $this->_token . '-recaptchav3', esc_url( $this->assets_url ) . 'js/recaptchav3.js', array( 'jquery' ), $this->_version );
		
		wp_register_script( $this->_token . '-google-autocomplete', esc_url( $this->assets_url ) . 'js/listeo.google.autocomplete.js', array( 'jquery' ), $this->_version );
		wp_register_script($this->_token . '-chart-min', esc_url($this->assets_url) . '/js/chart.min.js', array('jquery'), $this->_version);
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-bookings', esc_url( $this->assets_url ) . 'js/bookings.js', array( 'jquery' ), $this->_version );
		wp_localize_script( $this->_token . '-bookings', 'listeoBookings', array(
			'states_nonce' => wp_create_nonce('listeo_states_nonce'),
			'select_state_text' => esc_html__('Select a state…', 'listeo_core'),
			'state_placeholder' => esc_html__('State', 'listeo_core')
		));
		wp_register_script( $this->_token . '-drilldown', esc_url( $this->assets_url ) . 'js/drilldown.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-submit-listing', esc_url( $this->assets_url ) . 'js/submit-listing.js', array( 'jquery' ), $this->_version );
		wp_register_script( $this->_token . '-submit-listing-steps', esc_url( $this->assets_url ) . 'js/submit-listing.steps.js', array( 'jquery' ), $this->_version );
		// localize script -submit-listing
	
		wp_register_script( $this->_token . '-categories-split-slider', esc_url( $this->assets_url ) . 'js/categories.split.slider.js', array( 'jquery' ), $this->_version );		
 
		wp_register_script( $this->_token . '-pwstrength-bootstrap-min', esc_url( $this->assets_url ) . 'js/pwstrength-bootstrap.min.js', array( 'jquery' ), $this->_version );

		// Register My Listings filters script
		wp_register_script( $this->_token . '-my-listings-filters', esc_url( $this->assets_url ) . 'js/my-listings-filters.js', array( 'jquery' ), $this->_version );

		wp_register_script(	'markerclusterer', esc_url( $this->assets_url )  . '/js/markerclusterer.js', array( 'jquery' ), $this->_version );
		wp_register_script( 'infobox-min', esc_url( $this->assets_url )  . '/js/infobox.min.js', array( 'jquery' ), $this->_version  );
		wp_register_script( 'jquery-geocomplete-min',esc_url( $this->assets_url )  . '/js/jquery.geocomplete.min.js', array( 'jquery','maps' ), $this->_version  );
		wp_register_script( 'maps', esc_url( $this->assets_url )  . '/js/maps.js', array( 'jquery','listeo-custom','markerclusterer' ), $this->_version  );



		$map_provider = get_option( 'listeo_map_provider');
		$maps_api_key = get_option( 'listeo_maps_api' );


		if($map_provider != "none"):
			
			wp_enqueue_script( 'leaflet.js', esc_url( $this->assets_url ) . 'js/leaflet.js');

			if( $map_provider == 'bing'){
				
				wp_enqueue_script($this->_token . '-leaflet-bing-layer');
				
			}
			
			if( $map_provider == 'here' ){
				wp_enqueue_script($this->_token . '-leaflet-tilelayer-here');
			}
			
			if( $map_provider == 'google' ){
				$google_maps_url = $this->build_google_maps_url( $maps_api_key );
				wp_enqueue_script( 'google-maps', $google_maps_url, array(), null, false );
			}

			wp_enqueue_script( $this->_token . '-leaflet-google-maps');
			wp_enqueue_script( $this->_token . '-leaflet-geocoder' );
			wp_enqueue_script( $this->_token . '-leaflet-markercluster' );
			wp_enqueue_script( $this->_token . '-leaflet-gesture-handling' );
			wp_enqueue_script( $this->_token . '-leaflet' );

			if( get_option('listeo_map_address_provider') == 'google') {
				$google_maps_url = $this->build_google_maps_url( $maps_api_key );
				wp_enqueue_script( 'google-maps', $google_maps_url, array(), null, false );
				wp_enqueue_script( $this->_token . '-google-autocomplete' );
			};

		else:
			wp_localize_script(  $this->_token . '-frontend' , 'listeomap',
				    array(
				    	'address_provider'	=> 'off',
				        )
				    );
		endif;




		$recaptcha_status = get_option('listeo_recaptcha');
		$recaptcha_version = get_option('listeo_recaptcha_version');

		$recaptcha_sitekey3 = get_option('listeo_recaptcha_sitekey3');
		if(is_user_logged_in()){
			$recaptcha_status = false;

		}

		$this->localize_array["recaptcha_status"] 			= $recaptcha_status;
		$this->localize_array["recaptcha_version"]			= $recaptcha_version;
		$this->localize_array["recaptcha_sitekey3"] 		= trim($recaptcha_sitekey3);
		

		if(!empty($recaptcha_status) && $recaptcha_version == 'v3' && !empty($recaptcha_sitekey3)){
			wp_enqueue_script( 'google-recaptcha-listeo', 'https://www.google.com/recaptcha/api.js?render='.trim($recaptcha_sitekey3));	
			wp_enqueue_script( $this->_token . '-recaptchav3' );
		}
		if(!empty($recaptcha_status) && $recaptcha_version == 'v2'){
			wp_enqueue_script( 'google-recaptcha-listeo', 'https://www.google.com/recaptcha/api.js' );
		}
		if(!empty($recaptcha_status) && $recaptcha_version == 'hcaptcha'){
			$hcaptcha_sitekey = get_option('listeo_hcaptcha_sitekey');
			if (!empty($hcaptcha_sitekey)) {
				wp_enqueue_script('hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true);
			}
		}
		if(!empty($recaptcha_status) && $recaptcha_version == 'turnstile'){
			$turnstile_sitekey = get_option('listeo_turnstile_sitekey');
			if (!empty($turnstile_sitekey)) {
				wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true);
			}
		}
		if(!is_user_logged_in()){
		 	wp_enqueue_script(  $this->_token . '-pwstrength-bootstrap-min' );
		}

		// Initialize localize array with smart price loading
		$this->init_localize_array();

		// Add dynamic listing type configuration for JavaScript
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$available_types = $custom_types_manager->get_listing_types(true);
			$type_config = array();
			foreach ($available_types as $type) {
				$booking_features = json_decode($type->booking_features, true);
				if (!is_array($booking_features)) $booking_features = array();

				$type_config[$type->slug] = array(
					'booking_enabled' => ($type->booking_type && $type->booking_type !== 'none'),
					'booking_type' => $type->booking_type,
					'booking_features' => $booking_features,
					'supports_opening_hours' => (bool) $type->supports_opening_hours,
					// Backward compatibility - derived from booking_features
					'supports_pricing' => in_array('pricing', $booking_features),
					'supports_time_slots' => in_array('time_slots', $booking_features),
					'supports_calendar' => in_array('calendar', $booking_features),
					'supports_guests' => in_array('guests', $booking_features),
					'supports_services' => in_array('services', $booking_features)
				);
			}
			$this->localize_array["listing_types_config"] = $type_config;

			// Add custom taxonomy list for AJAX search (only non-default types)
			$custom_taxonomies = array();
			foreach ($available_types as $type) {
				// Skip default types as they are already hardcoded in JavaScript
				// if (in_array($type->slug, array('service', 'rental', 'event', 'classifieds'))) {
				// 	continue;
				// }
				$custom_taxonomies[] = $type->slug . '_category';
			}
			$this->localize_array["custom_taxonomies"] = $custom_taxonomies;
		}
		
		$criteria_fields = listeo_get_reviews_criteria();
		
		$loc_critera = array();
		foreach ($criteria_fields as $key => $value) {
			$loc_critera[] = $key;
		};
		if(!empty($loc_critera)){
			$this->localize_array['review_criteria'] = implode(',',$loc_critera);	
		}
		
		wp_localize_script(  $this->_token . '-frontend', 'listeo_core', $this->localize_array);

		wp_enqueue_script( 'jquery-ui-core' );
		
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'uploads' );
		if(get_option('listeo_ajax_browsing','on') == 'on'){
			wp_enqueue_script( 'ajaxsearch' );	
		}
		
		
		wp_enqueue_script( $this->_token . '-frontend' );
		wp_enqueue_script( $this->_token . '-bookings' );
		wp_enqueue_script( $this->_token . '-drilldown' );

		// Enqueue My Listings filters script on dashboard pages
		$dashboard_page = get_option('listeo_dashboard_page');
		if($dashboard_page && is_page($dashboard_page)){
			wp_enqueue_script( $this->_token . '-my-listings-filters' );
		}

		$submitpage = get_option('listeo_submit_page');
		// if current page is submit page, enqueue submit-listing.js
		
		if($submitpage && is_page($submitpage)){
			
			wp_enqueue_script( $this->_token . '-submit-listing' );
			
			wp_enqueue_script( $this->_token . '-submit-listing-steps' );
			
			// Enqueue FullCalendar core
			wp_enqueue_script(
				'fullcalendar-core',
				'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js',
				array('jquery'),
				'5.11.3',
				true
			);

			// Enqueue FullCalendar styles
			wp_enqueue_style(
				'fullcalendar-style',
				'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css',
				array(),
				'5.11.3'
			);
			$language = get_option('listeo_calendar_view_lang', 'en');

			if ($language != 'en') {
				wp_enqueue_script('listeo-core-fullcalendar-lang', LISTEO_CORE_URL . 'assets/js/locales/' . $language . '.js', array('jquery' ), 1.0, true);
			}
			$data = array(
				'language'   => $language,
			);
			wp_localize_script($this->_token . '-submit-listing', 'listeoCal', $data);
		}
	
		
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	// Enqueue rejection modal CSS on listing pages
	global $pagenow, $typenow;
	if ( ( $pagenow === 'edit.php' && $typenow === 'listing' ) || ( $pagenow === 'post.php' && isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'listing' ) ) {
		wp_register_style( $this->_token . '-reject-modal', esc_url( $this->assets_url ) . 'css/listeo-reject-modal.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-reject-modal' );
	}
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		
		// Settings admin JS uses the WordPress-bundled color picker
		// (`wp-color-picker`) for any `color` field type registered via
		// `listeo_settings_fields`. The picker pulls in `iris` + jQuery
		// automatically. We also explicitly enqueue its stylesheet so
		// the swatch/palette UI styles load on the settings page.
		wp_enqueue_style( 'wp-color-picker' );
		wp_register_script( $this->_token . '-settings', esc_url( $this->assets_url ) . 'js/settings' . $this->script_suffix . '.js', array( 'jquery', 'wp-color-picker' ), $this->_version );
		wp_enqueue_script( $this->_token . '-settings' );
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin.min.js', array( 'jquery', 'jquery-ui-autocomplete',  'jquery-ui-dialog'), $this->_version );
		wp_enqueue_script( $this->_token . '-admin' );

	// Enqueue rejection modal script and styles on listing edit page
	global $pagenow, $typenow;
	if ( ( $pagenow === 'edit.php' && $typenow === 'listing' ) || ( $pagenow === 'post.php' && isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'listing' ) ) {
		wp_register_script( $this->_token . '-reject-modal', esc_url( $this->assets_url ) . 'js/listeo-reject-modal.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-reject-modal' );
		
		// Localize rejection modal script
		wp_localize_script( $this->_token . '-reject-modal', 'listeo_reject_i18n', array(
			'modal_title'       => __( 'Reject Listing', 'listeo_core' ),
			'modal_description' => __( 'Please provide a reason for rejecting this listing. This will be sent to the listing owner.', 'listeo_core' ),
			'placeholder'       => __( 'Enter rejection reason...', 'listeo_core' ),
			'confirm_button'    => __( 'Reject Listing', 'listeo_core' ),
			'cancel_button'     => __( 'Cancel', 'listeo_core' ),
			'nonce'             => wp_create_nonce( 'listeo_reject_listing' ),
			'admin_url'         => admin_url( 'admin.php' )
		) );
	}
		

		$map_provider = get_option( 'listeo_map_provider');
		$maps_api_key = get_option( 'listeo_maps_api' );
		if( get_option('listeo_map_address_provider') == 'google') {
			if($maps_api_key) {
				$google_maps_url = $this->build_google_maps_url( $maps_api_key, false );
				wp_enqueue_script( 'google-maps', $google_maps_url, array(), null, false );
				wp_register_script( $this->_token . '-admin-maps', esc_url( $this->assets_url ) . 'js/admin.maps' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
				wp_enqueue_script( $this->_token . '-admin-maps' );

			}
		} else {
			wp_enqueue_script( 'leaflet.js', esc_url( $this->assets_url ) . 'js/leaflet.js');
			wp_enqueue_script( 'leaflet-geocoder',esc_url( $this->assets_url ) . 'js/control.geocoder.js');
			wp_register_script( $this->_token . '-admin-leaflet', esc_url( $this->assets_url ) . 'js/admin.leaflet' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
			wp_enqueue_script( $this->_token . '-admin-leaflet' );
			
		}
		wp_enqueue_script('jquery-ui-datepicker');
		if(function_exists('listeo_date_time_wp_format')) {
			$convertedData = listeo_date_time_wp_format();
	        // add converented format date to javascript
	        wp_localize_script(  $this->_token . '-admin', 'wordpress_date_format', $convertedData );
        }
		wp_register_script($this->_token . '-submit-listing', esc_url($this->assets_url) . 'js/submit-listing.js', array('jquery'), $this->_version);
		wp_enqueue_script($this->_token . '-submit-listing');
		// Enqueue FullCalendar core
		wp_enqueue_script(
			'fullcalendar-core',
			'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js',
			array('jquery'),
			'5.11.3',
			true
		);

		// Enqueue FullCalendar styles
		wp_enqueue_style(
			'fullcalendar-style',
			'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css',
			array(),
			'5.11.3'
		);

         wp_localize_script(  $this->_token . '-admin', 'listeo_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce('autocompleteSearchNonce'),
            'pp_cancel_payout_confirmation_msg' => esc_html__('Are you sure to cancel the automatic commission that was sent previously by using PayPal Payout?', 'listeo')
        ] );
		$language = get_option('listeo_calendar_view_lang', 'en');

		if ($language != 'en') {
			wp_enqueue_script('listeo-core-fullcalendar-lang', LISTEO_CORE_URL . 'assets/js/locales/' . $language . '.js', array('jquery'), 1.0, true);
		}
		$data = array(
			'language'   => $language,
		);
		wp_localize_script($this->_token . '-submit-listing', 'listeoCal', $data);
		
		// Initialize basic localize array for admin (no price calculations)
		$this->init_admin_localize_array();

		wp_localize_script($this->_token . '-submit-listing', 'listeo_core', $this->localize_array);
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'listeo_core', false, dirname( plugin_basename( $this->file ) ) . '/languages/' );

	} // End load_localisation ()

	//subscription
	public function init_plugin() {


		$this->submit 		= new Listeo_Core_Submit();
		if ( class_exists( 'WC_Product_Subscription' ) ) {
		include( 'paid-listings/class-listeo-core-paid-subscriptions.php' );			
			include_once( 'paid-listings/class-listeo-core-paid-subscriptions-product.php' );
			include_once( 'paid-listings/class-wc-product-listing-package-subscription.php' );
			

		}


	}

	/**
	 * Adds image sizes
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function image_size () {
		add_image_size('listeo-gallery', 1200, 0, true);
		add_image_size('listeo-listing-grid', 520, 397, true);
		add_image_size('listeo_core-avatar', 590, 590, true);
		add_image_size('listeo_core-preview', 200, 200, true);

	} // End load_localisation ()

	public function register_sidebar () {

		register_sidebar( array(
			'name'          => esc_html__( 'Single listing sidebar', 'listeo_core' ),
			'id'            => 'sidebar-listing',
			'description'   => esc_html__( 'Add widgets here.', 'listeo_core' ),
			'before_widget' => '<div id="%1$s" class="listing-widget widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title margin-bottom-35">',
			'after_title'   => '</h3>',
		) );

		register_sidebar( array(
			'name'          => esc_html__( 'Listings sidebar', 'listeo_core' ),
			'id'            => 'sidebar-listings',
			'description'   => esc_html__( 'Add widgets here.', 'listeo_core' ),
			'before_widget' => '<div id="%1$s" class="listing-widget widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title margin-bottom-35">',
			'after_title'   => '</h3>',
		) );		



	} // End load_localisation ()


	function get_min_listing_price($type) {
		global $wpdb;
		$result = $wpdb->get_var(
	    $wpdb->prepare("
	            SELECT min(m2.meta_value + 0)
	            FROM $wpdb->posts AS p
	            INNER JOIN $wpdb->postmeta AS m1 ON ( p.ID = m1.post_id )
				INNER JOIN $wpdb->postmeta AS m2  ON ( p.ID = m2.post_id )
				WHERE
				p.post_type = 'listing'
				AND p.post_status = 'publish'
				AND ( m1.meta_key = '_offer_type' AND m1.meta_value = %s )
				AND ( m2.meta_key = '_price'  ) AND m2.meta_value != ''
	        ", $type )
	    ) ;

	    return $result;
	}	

	function get_max_listing_price($type) {
		global $wpdb;
		$result = $wpdb->get_var(
	    $wpdb->prepare("
	            SELECT max(m2.meta_value + 0)
	            FROM $wpdb->posts AS p
	            INNER JOIN $wpdb->postmeta AS m1 ON ( p.ID = m1.post_id )
				INNER JOIN $wpdb->postmeta AS m2  ON ( p.ID = m2.post_id )
				WHERE
				p.post_type = 'listing'
				AND p.post_status = 'publish'
				AND ( m1.meta_key = '_offer_type' AND m1.meta_value = %s )
				AND ( m2.meta_key = '_price'  ) AND m2.meta_value != ''
	        ", $type )
	    ) ;
	   

	    return $result;
	}	

	function get_min_all_listing_price() {
		global $wpdb;
		$result = $wpdb->get_var(
	    "	SELECT min(m2.meta_value + 0)
	            FROM $wpdb->posts AS p
	            INNER JOIN $wpdb->postmeta AS m1 ON ( p.ID = m1.post_id )
				INNER JOIN $wpdb->postmeta AS m2  ON ( p.ID = m2.post_id )
				WHERE
				p.post_type = 'listing'
				AND p.post_status = 'publish'
				AND ( m2.meta_key = '_price'  ) AND m2.meta_value != ''
	        "
	    ) ;

	    return $result;
	}	

	function get_max_all_listing_price() {
		global $wpdb;
		$result = $wpdb->get_var(
	   "
	            SELECT max(m2.meta_value + 0)
	            FROM $wpdb->posts AS p
	            INNER JOIN $wpdb->postmeta AS m1 ON ( p.ID = m1.post_id )
				INNER JOIN $wpdb->postmeta AS m2  ON ( p.ID = m2.post_id )
				WHERE
				p.post_type = 'listing'
				AND p.post_status = 'publish'
				AND ( m2.meta_key = '_price'  ) AND m2.meta_value != ''
	        "
	    ) ;
	   

	    return $result;
	}




	function listeo_core_handle_delete_media(){
	    // Verify nonce for CSRF protection
	    $nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : '';
	    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'listeo_core_upload_nonce' ) ) {
	        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'listeo_core' ) ) );
	    }

	    // Check if user is logged in and has delete capability
	    if ( ! is_user_logged_in() || ! current_user_can( 'delete_posts' ) ) {
	        wp_send_json_error( array( 'message' => __( 'You do not have permission to delete files.', 'listeo_core' ) ) );
	    }

	    if( isset($_REQUEST['media_id']) ){
	        $post_id = absint( $_REQUEST['media_id'] );

	        // Verify the user owns this attachment or has the capability to delete others' posts
	        $attachment = get_post( $post_id );
	        if ( ! $attachment || ( $attachment->post_author != get_current_user_id() && ! current_user_can( 'delete_others_posts' ) ) ) {
	            wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this file.', 'listeo_core' ) ) );
	        }

	       // $status = wp_delete_attachment($post_id, true);
		   $status = true;
	        if( $status )
	            echo json_encode(array('status' => 'OK'));
	        else
	            echo json_encode(array('status' => 'FAILED'));
	    }
	    wp_die();
	}


	function listeo_core_handle_dropped_media() {
	    // Verify nonce for CSRF protection
	    $nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : '';
	    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'listeo_core_upload_nonce' ) ) {
	        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'listeo_core' ) ) );
	    }

	    // Check if user is logged in
	    if ( ! is_user_logged_in() ) {
	        wp_send_json_error( array( 'message' => __( 'You must be logged in to upload files.', 'listeo_core' ) ) );
	    }

	    // Check if user has upload capability
	    // Note: Guest role should have upload_files capability (added in v2.5)
	    if ( ! current_user_can( 'upload_files' ) ) {
	        wp_send_json_error( array( 'message' => __( 'You do not have permission to upload files. Please contact the site administrator.', 'listeo_core' ) ) );
	    }

	    status_header(200);

	    $upload_dir = wp_upload_dir();
	    $upload_path = $upload_dir['path'] . DIRECTORY_SEPARATOR;
//	    $num_files = count($_FILES['file']['tmp_name']);

	    $newupload = 0;

	    if ( !empty($_FILES) ) {
	        $files = $_FILES;
	        foreach($files as $file) {
	            $newfile = array (
	                    'name' => $file['name'],
	                    'type' => $file['type'],
	                    'tmp_name' => $file['tmp_name'],
	                    'error' => $file['error'],
	                    'size' => $file['size']
	            );

	            $_FILES = array('upload'=>$newfile);
	            foreach($_FILES as $file => $array) {
	                $newupload = media_handle_upload( $file, 0 );
	            }
	        }
	    }

	    echo $newupload;
	    wp_die();
	}

		
		function google_api_notice() {
		
		$map_provider = get_option( 'listeo_map_provider');
		$maps_api_key = get_option( 'listeo_maps_api' );
		if($map_provider == 'google') {

			if(empty($maps_api_key)) {
			    ?>
			    <div class="error notice">
					<p><?php echo esc_html_e('Please configure Google Maps API key to use all Listeo features.') ?> <a href="http://www.docs.purethemes.net/listeo/knowledge-base/getting-google-maps-api-key/"><?php esc_html_e('Check here how to do it.','listeo_core') ?></a></p>
			    	
			        
			    </div>
			    <?php
			}
		}
	}

	function listeo_og_image(){
	    if( is_singular('listing') ) {
	    	
	    	global $post;
	    	
	    	$gallery = (array) get_post_meta( $post->ID, '_gallery', true );
			
			if(!empty($gallery)){
				$ids = array_keys($gallery);
				if(!empty($ids[0])){ 
					$image =  wp_get_attachment_image_url($ids[0],'listeo-listing-grid'); 
				}	
			} else { 
				$image = get_listeo_core_placeholder_image(); 
			}
			if(empty($image)){
				$image = get_the_post_thumbnail_url(get_the_ID(),'full') ;
			}
	       
	        echo '<meta property="og:image" content="'. $image .'" />';
	    }
	}
	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
		// $domain = 'listeo_core';

		// $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		// $loaded = load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		// if(!$loaded) {
		// 	load_textdomain($domain, WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo');
		// }

		// load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/languages/' );

		$domain = 'listeo_core';
		$locale = apply_filters('plugin_locale', determine_locale(), $domain);

		unload_textdomain($domain);

		// Try to load from the languages directory first
		if (load_textdomain($domain, WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo')) {
			return true;
		}

		// Load from plugin languages folder
		return load_plugin_textdomain(
			$domain,
			false,
			dirname(plugin_basename($this->file)) . '/languages/'
		);
	} // End load_plugin_textdomain ()

	/**
	 * Maybe migrate reviews criteria to v2.0 (per-type/per-taxonomy system)
	 *
	 * @since 1.9.25
	 * @return void
	 */
	public function maybe_migrate_reviews_criteria() {
		Listeo_Core_Reviews_Migration::migrate_to_advanced_criteria();
	} // End maybe_migrate_reviews_criteria ()

	/**
	 * Main Listeo_Core Instance
	 *
	 * Ensures only one instance of Listeo_Core is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Listeo_Core()
	 * @return Main Listeo_Core instance
	 */
	public static function instance ( $file = '', $version = '1.2.1' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?','listeo_core' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?','listeo_core' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
		$this->init_user_roles();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

	/**
	* Schedule cron jobs for Listeo_Core events.
	*/
	public static function maybe_schedule_cron_jobs() {
		
		if ( ! wp_next_scheduled( 'listeo_core_check_for_expired_listings' ) ) {
			wp_schedule_event( time(), 'hourly', 'listeo_core_check_for_expired_listings' );
		}

		if ( ! wp_next_scheduled( 'listeo_core_check_for_expiring_listings' ) ) {
			wp_schedule_event( time(), 'hourly', 'listeo_core_check_for_expiring_listings' );
		}

		if ( ! wp_next_scheduled( 'listeo_core_check_for_expired_bookings' ) ) {
			wp_schedule_event( time(), '5min', 'listeo_core_check_for_expired_bookings' );
		}


		if ( ! wp_next_scheduled( 'listeo_core_check_for_new_messages' ) ) {
			wp_schedule_event( time(), '30min', 'listeo_core_check_for_new_messages' );
		}

		if ( ! wp_next_scheduled( 'listeo_core_check_for_upcoming_payments' ) ) {
			wp_schedule_event( time(), '5min', 'listeo_core_check_for_upcoming_payments' );
		}
		if ( ! wp_next_scheduled( 'listeo_core_check_for_upcoming_booking' ) ) {
			wp_schedule_event( time(), 'hourly', 'listeo_core_check_for_upcoming_booking' );
		}
		if ( ! wp_next_scheduled( 'listeo_core_check_for_past_booking' ) ) {
			wp_schedule_event( time(), 'hourly', 'listeo_core_check_for_past_booking' );
		}

		// Saved Search Email Alerts - daily
		if ( ! wp_next_scheduled( 'listeo_core_check_saved_search_alerts' ) ) {
			wp_schedule_event( time(), 'daily', 'listeo_core_check_saved_search_alerts' );
		}

		// if (!wp_next_scheduled('cleanup_ad_stats_hook')) {
		// 	wp_schedule_event(time(), 'daily', 'cleanup_ad_stats_hook');
		// }

		//wp_clear_scheduled_hook('cleanup_ad_stats_hook');
		
	}

	function listeo_cron_schedules($schedules){
	    if(!isset($schedules["5min"])){
	        $schedules["5min"] = array(
	            'interval' => 5*60,
	            'display' => __('Once every 5 minutes'));
	    }
	    if(!isset($schedules["30min"])){
	        $schedules["30min"] = array(
	            'interval' => 30*60,
	            'display' => __('Once every 30 minutes'));
	    }
	    if(!isset($schedules["every_week"])){
		    $schedules['every_week'] = array(
	            'interval'  => 604800, //604800 seconds in 1 week
	            'display'   => esc_html__( 'Every Week', 'listeo_core' )
	    	);
	 	}
	    return $schedules;
	}

	function init_user_roles(){
		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
 
		if ( is_object( $wp_roles ) ) {
				remove_role( 'owner' );
				add_role( 'owner', __( 'Owner', 'listeo_core' ), array(
					'read'                 => true,
					'upload_files'         => true,
					'edit_listing'         => true,
					//'edit_posts'         => true,
					'read_listing'         => true,
					'delete_listing'       => true,
					'edit_listings'        => true,
					'delete_listings'      => true,
					'edit_listings'        => true,
					'assign_listing_terms' => true,
					'dokandar'                  => true,
				'edit_shop_orders'          => true,
				'edit_product'              => true,
				'read_product'              => true,
				'delete_product'            => true,
				'edit_products'             => true,
				'publish_products'          => true,
				'read_private_products'     => true,
				'delete_products'           => true,
				'delete_products'           => true,
				'delete_private_products'   => true,
				'delete_published_products' => true,
				'delete_published_products' => true,
				'edit_private_products'     => true,
				'edit_published_products'   => true,
				'manage_product_terms'      => true,
				'delete_product_terms'      => true,
				'assign_product_terms'      => true,
			) );

			if (class_exists('WeDevs_Dokan')) :

				$capabilities = [];
				$all_cap      = dokan_get_all_caps();

				foreach ($all_cap as $key => $cap) {
					$capabilities = array_merge($capabilities, array_keys($cap));
				}

				foreach ($capabilities as $key => $capability) {
					$wp_roles->add_cap('owner', $capability);
				}
				
			endif;
			$capabilities = array(
				'core' => array(
					'manage_listings'
				),
				'listing' => array(
					"edit_listing",
					"read_listing",
					"delete_listing",
					"edit_listings",
					"edit_others_listings",
					"publish_listings",
					"read_private_listings",
					"delete_listings",
					"delete_private_listings",
					"delete_published_listings",
					"delete_others_listings",
					"edit_private_listings",
					"edit_published_listings",
					"manage_listing_terms",
					"edit_listing_terms",
					"delete_listing_terms",
					"assign_listing_terms"
				));

				add_role( 'guest', _x( 'Guest', 'User role', 'listeo_core' ), array(
						'read'  => true,
						'upload_files' => true, // Allow avatar and file uploads
				) );

			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$wp_roles->add_cap( 'administrator', $cap );
				}
			}
		}

	}
	
	//Add support1.3.1
	function listeo_core_update_db_1_3_2() {
		$db_option = get_option( 'listeo_core_db_version', '1.3.1' );
		if ( ! $db_option ) {
			$db_option = '1.3.1';
		}
		if ( version_compare( $db_option, '1.3.2', '<' ) ) {
			global $wpdb;

			$sql = "ALTER TABLE `{$wpdb->prefix}listeo_core_conversations` ADD `notification` VARCHAR(10) DEFAULT 'sent' AFTER `last_update`";
			$wpdb->query( $sql );

			update_option( 'listeo_core_db_version', '1.3.2' );
		}
	}

	function listeo_core_update_db_1_5_18() {
		$db_option = get_option( 'listeo_core_db_version', '1.3.2' );
		if ( ! $db_option ) {
			$db_option = '1.3.2';
		}
		if ( version_compare( $db_option, '1.5.18', '<' ) ) {
			global $wpdb;

			$sql = "ALTER TABLE `{$wpdb->prefix}listeo_core_user_packages` 
			ADD   package_option_booking int(1) NULL,
			ADD	  package_option_reviews int(1) NULL,
			ADD	  package_option_gallery int(1) NULL,
			ADD	  package_option_gallery_limit bigint(20) NULL,
			ADD	  package_option_social_links int(1) NULL,
			ADD	  package_option_opening_hours int(1) NULL,
			ADD	  package_option_pricing_menu int(1) NULL,
			ADD	  package_option_video int(1) NULL,
			ADD	  package_option_coupons int(1) NULL";
			$wpdb->query( $sql );

			update_option( 'listeo_core_db_version', '1.5.18' );
		}
	}

	function listeo_core_update_db_1_5_19() {
		$db_option = get_option( 'listeo_core_db_version', '1.5.18' );
		if ( ! $db_option ) {
			$db_option = '1.5.18';
		}
		if ( version_compare( $db_option, '1.5.19', '<' ) ) {
			global $wpdb;

			$sql = "ALTER TABLE `{$wpdb->prefix}listeo_core_user_packages` 
			ADD	  package_option_pricing_menu int(1) NULL";
			$wpdb->query( $sql );

			update_option( 'listeo_core_db_version', '1.5.19' );
		}
	}

	function listeo_core_update_db_1_5_20() {
		$db_option = get_option( 'listeo_core_db_version', '1.5.19' );
		if ( ! $db_option ) {
			$db_option = '1.5.19';
		}
		if ( version_compare( $db_option, '1.5.20', '<' ) ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'listeo_core_conversations';
			$column_user_1 = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'deleted_user_1' ) );
			$column_user_2 = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'deleted_user_2' ) );

			if ( null === $column_user_1 ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD `deleted_user_1` tinyint(1) NOT NULL DEFAULT 0 AFTER `notification`" );
			}

			if ( null === $column_user_2 ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD `deleted_user_2` tinyint(1) NOT NULL DEFAULT 0 AFTER `deleted_user_1`" );
			}

			update_option( 'listeo_core_db_version', '1.5.20' );
		}
	}

	/**
	 * Database update for Dokan store access feature
	 * Adds package_option_dokan_store and dokan_store_expires columns
	 *
	 * @since 1.9.51
	 */
	function listeo_core_update_db_1_9_51() {
		$db_option = get_option( 'listeo_core_db_version', '1.5.20' );
		if ( ! $db_option ) {
			$db_option = '1.5.20';
		}
		if ( version_compare( $db_option, '1.9.51', '<' ) ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'listeo_core_user_packages';

			// Check if columns already exist
			$column_dokan = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'package_option_dokan_store' ) );
			$column_expires = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'dokan_store_expires' ) );

			// Add package_option_dokan_store column
			if ( null === $column_dokan ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD `package_option_dokan_store` int(1) NULL" );
			}

			// Add dokan_store_expires column
			if ( null === $column_expires ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD `dokan_store_expires` datetime NULL" );
			}

			// Grandfather existing Dokan vendors
			$this->listeo_grandfather_existing_dokan_vendors();

			update_option( 'listeo_core_db_version', '1.9.51' );
		}
	}

	/**
	 * Database update for tickets and ad stats tables
	 * Creates listeo_core_tickets and listeo_core_ad_stats tables
	 *
	 * @since 2.2
	 */
	function listeo_core_update_db_2_2() {
		$db_option = get_option( 'listeo_core_db_version', '1.9.51' );
		if ( ! $db_option ) {
			$db_option = '1.9.51';
		}
		if ( version_compare( $db_option, '2.2', '<' ) ) {
			// Create tickets table
			if ( function_exists( 'listeo_core_tickets_db' ) ) {
				listeo_core_tickets_db();
			}

			// Create ad stats table
			if ( function_exists( 'listeo_core_ad_stats_db' ) ) {
				listeo_core_ad_stats_db();
			}

			update_option( 'listeo_core_db_version', '2.2' );
		}
	}

	/**
	 * Database update 2.5 - Add upload_files capability to Guest role
	 *
	 * Fixes avatar and file uploads for Guest users by granting upload_files capability.
	 * This will automatically apply to existing sites when they update.
	 *
	 * @since 2.5.0
	 */
	function listeo_core_update_db_2_5() {
		$db_option = get_option( 'listeo_core_db_version', '2.2' );
		if ( ! $db_option ) {
			$db_option = '2.2';
		}
		if ( version_compare( $db_option, '2.5', '<' ) ) {
			// Add upload_files capability to existing Guest role
			$guest_role = get_role( 'guest' );
			if ( $guest_role && ! $guest_role->has_cap( 'upload_files' ) ) {
				$guest_role->add_cap( 'upload_files' );
			}

			update_option( 'listeo_core_db_version', '2.5' );
		}
	}

	/**
	 * Backfill _listing_views_count for existing listings that don't have it.
	 * Without this meta key, listings are excluded from "Most viewed" sort order
	 * because WP_Query with meta_key performs an INNER JOIN.
	 *
	 * @since 2.5.1
	 */
	function listeo_core_update_db_2_5_1() {
		$db_option = get_option( 'listeo_core_db_version', '2.5' );
		if ( ! $db_option ) {
			$db_option = '2.5';
		}
		if ( version_compare( $db_option, '2.5.1', '<' ) ) {
			global $wpdb;

			// Insert _listing_views_count = 0 for all listings that don't have it yet
			$wpdb->query(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				SELECT p.ID, '_listing_views_count', '0'
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_listing_views_count'
				WHERE p.post_type = 'listing'
				AND p.post_status IN ('publish', 'pending', 'draft')
				AND pm.meta_id IS NULL"
			);

			update_option( 'listeo_core_db_version', '2.5.1' );
		}
	}

	/**
	 * Mark existing Dokan vendors for grandfathering
	 * Run this during plugin upgrade to protect existing vendors
	 *
	 * @since 1.9.51
	 */
	public function listeo_grandfather_existing_dokan_vendors() {
		// Only run once
		if ( get_option( 'listeo_dokan_vendors_grandfathered' ) ) {
			return;
		}

		// Check if Dokan is active
		if ( ! class_exists( 'WeDevs_Dokan' ) ) {
			return;
		}

		// Get all users with seller/vendor capability
		$vendors = get_users( array(
			'role__in' => array( 'seller', 'vendor' ),
			'fields' => 'ID'
		) );

		if ( ! empty( $vendors ) ) {
			foreach ( $vendors as $vendor_id ) {
				// Mark as grandfathered vendor
				update_user_meta( $vendor_id, '_was_dokan_vendor_before_restriction', '1' );
			}
		}

		// Mark as completed
		update_option( 'listeo_dokan_vendors_grandfathered', '1' );
	}

	function listing_autocomplete_search()
	{
		check_ajax_referer('autocompleteSearchNonce', 'security');
		$search_term = $_REQUEST['term'];
		if (!isset($_REQUEST['term'])) {
			echo json_encode([]);
		}
		$suggestions = [];
		$query = new WP_Query([
			's' => $search_term,
			'posts_per_page' => -1,
			'post_type' => 'listing',
		]);
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$suggestions[] = [
					'id' => get_the_ID(),
					'label' => get_the_title(),
					'link' => get_the_permalink()
				];
			}
			wp_reset_postdata();
		}
		echo json_encode($suggestions);
		wp_die();
	}

	/**
	 * Clear price cache when listings are updated.
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 * @param bool $update
	 */
	public function clear_price_cache_on_listing_update($post_id, $post, $update) {
		if ($post->post_type === 'listing') {
			wp_cache_delete('listeo_price_ranges', 'listeo_core');
		}
	}

	/**
	 * Clear price cache when listings are deleted.
	 *
	 * @param int $post_id
	 */
	public function clear_price_cache_on_listing_delete($post_id) {
		if (get_post_type($post_id) === 'listing') {
			wp_cache_delete('listeo_price_ranges', 'listeo_core');
		}
	}

	/**
	 * Clear price cache when listing price meta is updated.
	 *
	 * @param int $meta_id
	 * @param int $post_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 */
	public function clear_price_cache_on_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
		if (get_post_type($post_id) === 'listing' && in_array($meta_key, array('_price', '_normal_price', '_weekday_price'))) {
			wp_cache_delete('listeo_price_ranges', 'listeo_core');
		}
	}

	/**
	 * Build Google Maps API URL with map_id support.
	 *
	 * @access public
	 * @since  2.0.19
	 * @param  string $api_key Google Maps API key
	 * @param  bool   $include_callback Whether to include callback parameter (default: true)
	 * @return string Filtered Google Maps API URL
	 */
	public function build_google_maps_url( $api_key, $include_callback = true ) {
		// Base parameters
		$params = array(
			'key'       => $api_key,
			'libraries' => 'places',
		);

		// Add callback for frontend (not needed in admin)
		if ( $include_callback ) {
			$params['callback'] = 'Function.prototype';
		}

		// Check for Google Map ID (Cloud Styled Maps)
		$map_id = get_option( 'listeo_google_maps_id', '' );
		if ( ! empty( $map_id ) ) {
			$params['map_ids'] = trim( $map_id );
		}

		// Optional: Allow specifying API version (defaults to weekly if not set)
		$api_version = get_option( 'listeo_google_maps_version', 'weekly' );
		if ( ! empty( $api_version ) && $api_version !== 'default' ) {
			$params['v'] = $api_version;
		}

		// Build URL
		$google_maps_url = add_query_arg( $params, 'https://maps.googleapis.com/maps/api/js' );

		/**
		 * Filter the Google Maps API URL.
		 *
		 * Allows developers to modify the Google Maps API URL parameters.
		 *
		 * @since 2.0.19
		 *
		 * @param string $google_maps_url The complete Google Maps API URL
		 * @param array  $params          Array of URL parameters
		 * @param string $api_key         Google Maps API key
		 */
		return apply_filters( 'listeo_google_maps_api_url', $google_maps_url, $params, $api_key );
	}

}
