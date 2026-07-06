<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * WPSight_Meta_Boxes class
 */
class Listeo_Core_Meta_Boxes {
	/**
	 * Constructor
	 */
	public function __construct() {

		// Add custom meta boxes
		add_action( 'cmb2_admin_init', array( $this, 'add_meta_boxes' ) );
		add_filter( 'cmb2_render_listeomenu', array( $this,'cmb2_render_listeomenu_field_callback'), 10, 5 );
		add_filter( 'cmb2_sanitize_listeomenu', array( $this,'cmb2_sanitize_listeomenu_field'), 10, 5 );
		add_filter( 'cmb2_sanitize_listeomenu', array( $this,'cmb2_split_listeomenu_values'), 12, 4 );
		add_filter( 'cmb2_types_esc_listeomenu', array( $this,'cmb2_types_esc_listeomenu_field'), 10, 4 );
		
		add_action( 'cmb2_render_datetime', array( $this,'cmb2_render_callback_for_datetime'), 10, 5 );
		
		add_action( 'cmb2_render_listeo_package', array( $this,'cmb2_render_callback_for_listeo_package'), 10, 5 );

		// multicheck split
	
		add_filter( 'cmb2_render_opening_hours_listeo', array( $this,'cmb2_render_opening_hours_listeo_field_callback'), 10, 5 );

		// add_action( 'listing_category_add_form_fields', array( $this,'listeo_listing_category_add_new_meta_field'), 10, 2 );
		// add_action( 'listing_category_edit_form_fields', array( $this,'listeo_listing_category_edit_meta_field'), 10, 2 );

		// add_action( 'edited_listing_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );  
		// add_action( 'created_listing_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );

		// add_action( 'region_add_form_fields', array( $this,'listeo_listing_category_add_new_meta_field'), 10, 2 );
		// add_action( 'region_edit_form_fields', array( $this,'listeo_listing_category_edit_meta_field'), 10, 2 );

		// add_action( 'edited_region', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );  
		// add_action( 'created_region', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );



		// add_action( 'listing_feature_add_form_fields', array( $this,'listeo_listing_category_add_new_meta_field'), 10, 2 );
		// add_action( 'listing_feature_edit_form_fields', array( $this,'listeo_listing_category_edit_meta_field'), 10, 2 );

		// add_action( 'edited_listing_feature', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );  
		// add_action( 'created_listing_feature', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );


		// add_action( 'event_category_add_form_fields', array( $this,'listeo_listing_category_add_new_meta_field'), 10, 2 );
		// add_action( 'event_category_edit_form_fields', array( $this,'listeo_listing_category_edit_meta_field'), 10, 2 );

		// add_action( 'edited_event_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );  
		// add_action( 'created_event_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );

		// add_action( 'service_category_add_form_fields', array( $this,'listeo_listing_category_add_new_meta_field'), 10, 2 );
		// add_action( 'service_category_edit_form_fields', array( $this,'listeo_listing_category_edit_meta_field'), 10, 2 );

		// add_action( 'edited_service_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );  
		// add_action( 'created_service_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );	

		// add_action( 'rental_category_add_form_fields', array( $this,'listeo_listing_category_add_new_meta_field'), 10, 2 );
		// add_action( 'rental_category_edit_form_fields', array( $this,'listeo_listing_category_edit_meta_field'), 10, 2 );

		// add_action( 'edited_rental_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );  
		// add_action( 'created_rental_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );


		// add_action('classifieds_category_add_form_fields', array( $this,'listeo_listing_category_add_new_meta_field'), 10, 2 );
		// add_action('classifieds_category_edit_form_fields', array( $this,'listeo_listing_category_edit_meta_field'), 10, 2 );

		// add_action('edited_classifieds_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );  
		// add_action('created_classifieds_category', array( $this,'listeo_save_taxonomy_custom_meta'), 10, 2 );

		// Register term meta hooks after all CPTs/Taxonomies are registered.
		add_action('init', [$this, 'register_listing_term_meta_hooks'], 100);

		add_action( 'cmb2_admin_init', array( $this,'listeo_register_taxonomy_metabox' ) );
		add_filter( 'cmb2_sanitize_checkbox', array( $this, 'sanitize_checkbox'), 10, 2 );

		//add_action('save_post_listing', array($this, 'save_availability_calendar'), 10, 3);
	}

	/**
	 * Attach add/edit/save hooks for all taxonomies registered to the listing CPT.
	 * If you want to support multiple CPTs, see the commented $post_types example.
	 */
	public function register_listing_term_meta_hooks()
	{
		if (! is_admin()) {
			return;
		}

		// If you later want to cover more CPTs, set e.g.:
		// $post_types = [ 'listing', 'event', 'service', 'rental', 'classifieds' ];
		// foreach ( $post_types as $pt ) { $this->attach_term_meta_hooks_for_post_type( $pt ); }
		$this->attach_term_meta_hooks_for_post_type('listing');
	}

	/**
	 * Attach hooks for each taxonomy of a given post type.
	 */
	private function attach_term_meta_hooks_for_post_type($post_type)
	{
		$tax_objects = get_object_taxonomies($post_type, 'objects');
		if (empty($tax_objects)) {
			return;
		}

		foreach ($tax_objects as $tax_obj) {
			$tax = $tax_obj->name;

			// Skip hidden/adminless taxonomies if you only want those visible in the UI
			if (isset($tax_obj->show_ui) && ! $tax_obj->show_ui) {
				continue;
			}

			// Add/Edit term form fields (admin)
			// add_form_fields passes 1 arg: $taxonomy
			add_action("{$tax}_add_form_fields",  [$this, 'listeo_listing_category_add_new_meta_field'], 10, 1);

			// edit_form_fields passes 2 args: $term, $taxonomy
			add_action("{$tax}_edit_form_fields", [$this, 'listeo_listing_category_edit_meta_field'], 10, 2);

			// Save term meta on create/edit
			// created_{$taxonomy} passes 2 args: $term_id, $tt_id
			add_action("created_{$tax}", [$this, 'listeo_save_taxonomy_custom_meta'], 10, 2);

			// edited_{$taxonomy} passes 2 args in newer WP ($term_id, $tt_id); older WP sometimes 1.
			add_action("edited_{$tax}",  [$this, 'listeo_save_taxonomy_custom_meta'], 10, 2);
		}
	}

	function sanitize_checkbox( $override_value, $value ) {
	    // Return 0 instead of false if null value given. This hack for
	    // checkbox or checkbox-like can be setting true as default value.
	
	    return is_null( $value ) ? '0' : $value;
	}


	public function save_availability_calendar($post_id, $post, $update)
	{
		// Verify if this is an auto save routine
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// Check permissions
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		// Check if our nonce is set
		if (!isset($_POST['listeo_core_meta_nonce'])) {
			return;
		}

		// // Verify that the nonce is valid
		// if (!wp_verify_nonce($_POST['listeo_core_meta_nonce'], 'listeo_core_save_meta')) {
		// 	return;
		// }

		// Make sure we have the availability data
		if (isset($_POST['_availability'])) {
			// Get the availability data
			$days = sanitize_text_field($_POST['_availability']);

			// Validate format and clean the data
			$days_array = array_filter(explode('|', $days));
			$clean_days = implode('|', $days_array);

			// Save the cleaned availability data
			update_post_meta($post_id, '_availability', $clean_days);
		}
	}

	public function add_meta_boxes( ) {
		
		$listing_admin_options = array(
				'id'           => 'listeo_core_listing_admin_metaboxes',
				'title'        => __( 'Listing admin data', 'listeo_core' ),
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				'show_in_rest' => WP_REST_Server::READABLE,
		);
		$cmb_listing_admin = new_cmb2_box( $listing_admin_options );

		$cmb_listing_admin->add_field( array(
			'name' => __( 'Expiration date', 'listeo_core' ),
			'desc' => '',
			'id'   => '_listing_expires',
			'type' => 'text_date_timestamp',
			
		) );

	// Rejection Reason meta box
	$rejection_reason_options = array(
		'id'           => 'listeo_core_rejection_reason_metabox',
		'title'        => __( 'Rejection Information', 'listeo_core' ),
		'object_types' => array( 'listing' ),
		'show_names'   => true,
		'show_in_rest' => WP_REST_Server::READABLE,
		'show_on_cb'   => function() {
			global $post;
			return $post && $post->post_status === 'rejected';
		}
	);
	$cmb_rejection = new_cmb2_box( $rejection_reason_options );

	$cmb_rejection->add_field( array(
		'name' => __( 'Rejection Reason', 'listeo_core' ),
		'desc' => __( 'Enter or update the reason why this listing was rejected. This message will be visible to the listing owner.', 'listeo_core' ),
		'id'   => '_listing_rejection_reason',
		'type' => 'textarea',
		'attributes' => array(
			'rows' => 4,
		),
	) );
		

		// Listing type meta
		$listing_type_options = array(
				'id'           => 'listing_type',
				'title'        => __( 'Listing type', 'listeo_core' ),
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				'show_in_rest' => WP_REST_Server::READABLE,
		);
  		$cmb_listing_type = new_cmb2_box( $listing_type_options );
  		$cmb_listing_type->add_field( array(
			'name' => __( 'Listing Type', 'listeo_core' ),
			'id'   => '_listing_type',
			'type' => 'select',
			'desc' => __(
				'Determines booking options and available features',
				'listeo_core'
			),
			'options_cb' => array( $this, 'get_dynamic_listing_type_options' ),
		));  

		$cmb_listing_type->add_field(array(
			'name' => __( 'Listing Logo', 'listeo_core' ),
			'id'   => '_listing_logo',
			'type' => 'file',
			'desc' => __(
				'Upload a logo for this listing',
				'listeo_core'
			),
		));
		
		// EOF Listing type meta

		$cmb_keywords_options = array(
				'id'           => 'listing_keywords',
				'title'        => __( 'Listing keywords', 'listeo_core' ),
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				'priority'   => 'default',
				'show_in_rest' => WP_REST_Server::READABLE,
		);
  		$cmb_keywords_options = new_cmb2_box( $cmb_keywords_options );
		$cmb_keywords_options->add_field( array(
			'name' => __( 'Keywords', 'listeo_core' ),
			'id'   => 'keywords',
			'type' => 'text',
			'desc' => __(
				'Optional keywords used in search',
				'listeo_core'
			),
			
		));  

		$tabs_box_options = array(
				'id'           => 'listeo_tabbed_metaboxes',
				'title'        => __( 'Listing fields', 'listeo_core' ),
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				'show_in_rest' => WP_REST_Server::READABLE,
			);

		// Setup meta box
		$cmb_tabs = new_cmb2_box( $tabs_box_options );

		// setting tabs
		$tabs_setting  = array(
			'config' => $tabs_box_options,
			'layout' => 'vertical', // Default : horizontal
			'tabs'   => array()
		);
		
		$tabs_setting['tabs'] = array(
			 
			 // $this->meta_boxes_main_details(),
			   $this->meta_boxes_location(),
			   $this->meta_boxes_gallery(),
			   $this->meta_boxes_contact(),
			   $this->meta_boxes_event(),
			   $this->meta_boxes_service(),
			  $this->meta_boxes_rental(),
			  $this->meta_boxes_prices(),
			  $this->meta_boxes_classifieds(),
			  $this->meta_boxes_video(),
			  $this->meta_boxes_custom(),
			 // $this->meta_boxes_details(),
			 
		);

		// Add custom listing type tabs dynamically
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$listing_types = $custom_types_manager->get_listing_types(false, true);

			foreach ($listing_types as $type) {
				if ($type->is_active && !$type->is_default) {
					// Check if this custom listing type has fields defined in the editor
					$custom_fields = apply_filters("listeo_{$type->slug}_fields", array());

					if (!empty($custom_fields) && isset($custom_fields['fields']) && !empty($custom_fields['fields'])) {
						$tab_title = sprintf(__('%s Fields', 'listeo_core'), $type->name);

						// Add show_on_cb to each field for conditional display
						$fields_with_callback = array();
						foreach ($custom_fields['fields'] as $field) {
							$field['show_on_cb'] = array($this, 'listeo_show_on_custom_type_' . $type->slug);
							$fields_with_callback[] = $field;
						}

						$tabs_setting['tabs'][] = array(
							'id'     => "custom_type_{$type->slug}_fields",
							'title'  => $tab_title,
							'fields' => $fields_with_callback,
						);
					}
				}
			}
		}

		$custom_terms = get_option('listeo_custom_term_fields', array());

		if (!empty($custom_terms) && is_array($custom_terms)) {
			foreach ($custom_terms as $taxonomy => $terms) {
				foreach ($terms as $term_id => $unused) {
					$term = get_term($term_id, $taxonomy);
					if (is_wp_error($term) || !$term) {
						continue;
					}

					$tab_title = $term->name . ' Fields';

					$tabs_setting['tabs'][] = array(
						'id'     => "term_{$taxonomy}_{$term_id}_fields",
						'title'  => $tab_title,
						'fields' => $this->get_term_custom_fields($taxonomy, $term_id),
					);
				}
			}
		}
		// set tabs
		$cmb_tabs->add_field( array(
			'id'   => '_tabs',
			'type' => 'tabs',
			'tabs' => $tabs_setting
		) );
  


		// Pricing 
		$cmb_menu = new_cmb2_box( array(
            'id'            => '_menu_metabox',
            'title'         => __( 'Menu (Pricing)', 'listeo_core' ),
            'object_types' => array( 'listing' ), // post type
            'context'       => 'normal',
            'priority'      => 'core',
            'show_names'    => true,
			'show_in_rest' => WP_REST_Server::READABLE,
        ) );
		$cmb_menu->add_field( array(
			'name' => __( 'Pricing Status', 'listeo_core' ),
			'id'   => '_menu_status',
			'type' => 'checkbox',
		));
		$cmb_menu->add_field( array(
			'name' => __( 'Hide pricing table on listing page but show bookable services in booking widget', 'listeo_core' ),
			'id'   => '_hide_pricing_if_bookable',
			'type' => 'checkbox',
		));
        // Repeatable group
	        $menu_group = $cmb_menu->add_field( array(
	            'id'          => '_menu',
	            'type'        => 'group',
	            'options'     => array(
	                'group_title'   => __( 'Menu', 'listeo_core' ) . ' {#}', // {#} gets replaced by row number
	                'add_button'    => __( 'Add another Menu', 'listeo_core' ),
	                'remove_button' => __( 'Remove Menu', 'listeo_core' ),
	                'sortable'      => true, // beta
	            ),
	        ) );


	        $cmb_menu->add_group_field( $menu_group, array(
	            'name'    => __( 'Menu Title', 'listeo_core' ),
	            'id'      => 'menu_title',
	            'type'    => 'text',
	        
	        ) );  
	        $cmb_menu->add_group_field( $menu_group, array(
	            'name'    => __( 'Menu Items', 'listeo_core' ),
	            'id'      => 'menu_elements',
	            'type'    => 'listeomenu',
	            'repeatable' => true,
	        ) );  

        // EOF Pricing
  		
		// EOF Gallery


  		//  Opening hours
		$opening_hours_options = array(
				'id'           => 'listeo_core_opening_metaboxes',
				'title'        => __( 'Opening Hours (set here in 24:00 format)', 'listeo_core' ),
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				'show_in_rest' => WP_REST_Server::READABLE,

		);


		$cmb_opening = new_cmb2_box( $opening_hours_options );

		$cmb_opening->add_field( array(
			'name' => __('Time zone','listeo_core'),
			'id'   => '_listing_timezone',
			'type' => 'select_timezone',
		) );
		$cmb_opening->add_field( array(
			'name' => __( 'Opening Hours Status', 'listeo_core' ),
			'id'   => '_opening_hours_status',
			'type' => 'checkbox',
			'desc' => __( 'Enable to show Opening Hours widget online', 'listeo_core' ),
		));
		


		$cmb_opening->add_field( array(
			'name' => __( 'Opening Hours', 'listeo_core' ),
			'id'   => '_opening_hours',
			'type' => 'opening_hours',
			'desc' => 'Set Opening Hours',
		));
		$days = listeo_get_days();
		foreach ($days as $key => $value) {
			
				$cmb_opening->add_field( array(
					'name' => $value . __( ' Opening', 'listeo_core' ),
					'desc' => '',
					'id'   => '_'.$key.'_opening_hour',
					'type' => 'opening_hours_listeo',
					'attributes' => array(
						'data-timepicker' => json_encode( array(
							'timeFormat' => 'HH:mm',
						) ),
					),
					'time_format' => 'H:i',
					'after_field'  => '</div><button class="button button-secondary button-large add-time-picker">'.esc_html__('Add time','listeo_core').'</button><div>',
					'before_row'      => '<div class="opening_hours_column">',
				
				) );
				$cmb_opening->add_field( array(
					'name' => $value . __( ' Closing', 'listeo_core' ),
					'desc' => '',
					'id'   => '_'.$key.'_closing_hour',
					'type' => 'opening_hours_listeo',
					'attributes' => array(
						'data-timepicker' => json_encode( array(
							'timeFormat' => 'HH:mm',
						) ),
					),
					'time_format' => 'H:i',
					
					'after_row'      => '</div>',
				) );
			
				
			
		}
		//  EOF Opening hours

		// Verified 
		$verified_box_options = array(
				'id'           => 'listeo_core_verified_metabox',
				'title'        => __( 'Verified Listing', 'listeo_core' ),
				'context'	   => 'side',
				'priority'     => 'core', 
				'object_types' => array( 'listing' ),
				'show_names'   => false,
				'show_in_rest' => WP_REST_Server::READABLE,

		);

		// Setup meta box
		$cmb_verified = new_cmb2_box( $verified_box_options );

		$cmb_verified->add_field( array(
			'name' => __( 'Verified', 'listeo_core' ),
			'id'   => '_verified',
			'type' => 'checkbox',
			'desc' => __( 'Tick the checkbox to mark it as Verified', 'listeo_core' ),
		));
		// EOF Verified


		$featured_box_options = array(
				'id'           => 'listeo_core_featured_metabox',
				'title'        => __( 'Featured Listing', 'listeo_core' ),
				'context'	   => 'side',
				'priority'     => 'core', 
				'object_types' => array( 'listing' ),
				'show_names'   => false,
				'show_in_rest' => WP_REST_Server::READABLE,

		);

		// Setup meta box
		$cmb_featured = new_cmb2_box( $featured_box_options );

		$cmb_featured->add_field( array(
			'name' => __( 'Featured', 'listeo_core' ),
			'id'   => '_featured',
			'type' => 'checkbox',
			'desc' => __( 'Tick the checkbox to make it Featured', 'listeo_core' ),
		));
		

		$advanced_box_options = array(
				'id'           => 'listeo_core_advanced_metabox',
				'title'        => __( 'Advanced meta data Listing', 'listeo_core' ),
				'priority'     => 'core', 
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				'show_in_rest' => WP_REST_Server::READABLE,
		);

		// Setup meta box
		$cmb_advanced = new_cmb2_box( $advanced_box_options );

		$cmb_advanced->add_field( array(
			'name' => __( 'WooCommerce Product ID', 'listeo_core' ),
			'id'   => 'product_id',
			'type' => 'text',
			'desc' => __( 'WooCommerce Product ID. Don\'t change it unless you know what you are doing:)', 'listeo_core' ),
		));



		$coupon_box_options = array(
				'id'           => 'listeo_core_coupon_metabox',
				'title'        => __( 'Coupons Settings for Listing', 'listeo_core' ),
				'priority'     => 'core', 
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				'show_in_rest' => WP_REST_Server::READABLE,
		);

		// Setup meta box
		$cmb_coupon = new_cmb2_box($coupon_box_options);

		$cmb_coupon->add_field( array(
			'name' => __('Coupon Section status', 'listeo_core' ),
			'id'   => '_coupon_section_status',
			'type' => 'checkbox',
		));

		if (
			function_exists('is_woocommerce_activated') &&
			is_woocommerce_activated()
		) {
			$args = array(
			//	'author'        	=>  $current_user->ID,
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'asc',
				'post_type'        => 'shop_coupon',
				'post_status'      => 'publish',
			);
			$coupon_options = array();
			$coupons = get_posts($args);
			if ($coupons) {
				$coupon_options[0] = esc_html__('Select coupon', 'listeo_core');
			}
			foreach ($coupons as $coupon) {
				$coupon_options[$coupon->ID] = $coupon->post_title;
			}
			$cmb_coupon->add_field(array(
				'name' => __('Coupons to display', 'listeo_core'),
				'id'   => '_coupon_for_widget',
				'type' => 'select',
				'options' => $coupon_options,
			));
		}

		///////////////

		$otherlistings_box_options = array(
			'id'           => 'listeo_core_otherlistings_metabox',
			'title'        => __('My Other Listings Settings', 'listeo_core'),
			'priority'     => 'core',
			'object_types' => array('listing'),
			'show_names'   => true,
			'show_in_rest' => WP_REST_Server::READABLE,
		);

		// Setup meta box
		$cmb_otherlistings = new_cmb2_box($otherlistings_box_options);

		$cmb_otherlistings->add_field(array(
			'name' => __('My Other Listings Section status', 'listeo_core'),
			'id'   => '_my_listings_section_status',
			'type' => 'checkbox',
		));

		
		$listings_options = array();
		// 

		// in wp-admin in post edit page,  get post author  (not currently logged user) from the post the meta box is displayed on

		$current_post_id = get_the_ID();
		if (isset($_GET['post'])) {
			$post_author_id = get_post_field('post_author', $_GET['post']);
		} 
		
		$args = array(
			'post_type' => 'listing',
			'ignore_sticky_posts' => 1,
		//	'orderby' => 'rand',
			'posts_per_page' => 100,
			'post_status' => 'publish',
			
		);
		if (isset($post_author_id) && !empty($post_author_id)) {
			$args['author'] = $post_author_id;
		}
		
		$listings = get_posts($args);
		$listings_options = array();
		if ($listings) {
			$listings_options[0] = esc_html__('Select listings', 'listeo_core');
		}
		foreach ($listings as $listing) {
			$listings_options[$listing->ID] = $listing->post_title;
		}
		$cmb_otherlistings->add_field(array(
			'name' => __('Section title', 'listeo_core'),
			'id'   => '_my_listings_title',
			'type' => 'text',
			
		));
		$cmb_otherlistings->add_field(array(
			'name' => __('User listings to display', 'listeo_core'),
			'id'   => '_my_listings',
			'type' => 'select_multiple',
			'options' => $listings_options,
		));
		

		///////////////

		$store_box_options = array(
			'id'           => 'listeo_core_store_metabox',
			'title'        => __('Store Settings for Listing', 'listeo_core'),
			'priority'     => 'core',
			'object_types' => array('listing'),
			'show_names'   => true,
			'show_in_rest' => WP_REST_Server::READABLE,
		);

		// Setup meta box
		$cmb_store = new_cmb2_box($store_box_options );

		$cmb_store->add_field( array(
			'name' => __('Store Section status', 'listeo_core' ),
			'id'   => '_store_section_status',
			'type' => 'checkbox',
		));

		$cmb_store->add_field( array(
			'name' => __('Show store card widget on listing sidebar', 'listeo_core' ),
			'id'   => '_store_widget_status',
			'type' => 'checkbox',
		));
		$product_options = array();

		$args['exclude_listing_booking'] = 'true';
		$args['tax_query'][] = array(
			'taxonomy' => 'product_cat',
			'field' => 'slug',
			'terms' => array('listeo-booking'), // Don't display products in the clothing category on the shop page.
			'operator' => 'NOT IN'
		);
		$args['tax_query'][] = array(
			'taxonomy' => 'product_type',
			'field' => 'slug',
			'terms' => array('listing_package'), // Don't display products in the clothing category on the shop page.
			'operator' => 'NOT IN'
		);
		if (
			function_exists('is_woocommerce_activated') &&
			is_woocommerce_activated()
		) {
			$products = wc_get_products($args);
			foreach ($products as $product) {
				$product_options[$product->get_id()] = $product->get_title();
			}
			if ($products) {
				$product_options[0] = esc_html__('Select product', 'listeo_core');
			}
			$cmb_store->add_field(array(
				'name' => __('Store Products to display', 'listeo_core'),
				'id'   => '_store_products',
				'type' => 'select_multiple',
			//	'multiple' => true,
				'options' => $product_options,
			));
		}

		$faq_box_options = array(
			'id'     => 'faq_tab',
			'title'  => __( 'FAQ', 'listeo_core' ),
			'priority' => 'core',
			'object_types' => array( 'listing' ),
		);

		$cmb_faq = new_cmb2_box( $faq_box_options );

// faq_status checkbox
		$cmb_faq->add_field( array(
			'name' => __( 'FAQ Status', 'listeo_core' ),
			'id'   => '_faq_status',
			'type' => 'checkbox',
			'desc' => __( 'Enable to show FAQ section', 'listeo_core' ),
		));

		$faq_group_field_id = $cmb_faq->add_field( array(
		
			'name' => __( 'Add new FAQ', 'listeo_core' ),
			'id'   => '_faq_list',
			'type' => 'group',
			'options'     => array(
				'group_title'       => __('FAQ {#}', 'cmb2'), // since version 1.1.4, {#} gets replaced by row number
				'add_button'        => __('Add Another FAQ', 'cmb2'),
				'remove_button'     => __('Remove FAQ', 'cmb2'),
				'sortable'          => true,
				// 'closed'         => true, // true to have the groups closed by default
				// 'remove_confirm' => esc_html__( 'Are you sure you want to remove?', 'cmb2' ), // Performs confirmation before removing group.
			),
			
				
		) );
		$cmb_faq->add_group_field($faq_group_field_id, array(
			'name' => __( 'Question', 'listeo_core' ),
			'id'   => 'question',
			'type' => 'text',
		));
		$cmb_faq->add_group_field($faq_group_field_id, array(
			'name' => __( 'Answer', 'listeo_core' ),
			'id'   => 'answer',
			'type' => 'textarea',
		));

		$booking_box_options = array(
				'id'           => 'listeo_core_booking_metabox',
				'title'        => __( 'Booking options', 'listeo_core' ),
				'priority'     => 'core', 
				'object_types' => array( 'listing' ),
				'priority'     => 'core', 
				'object_types' => array( 'listing' ),
				'show_names'   => true,
				
		);

		// Setup meta box
		$cmb_booking = new_cmb2_box( $booking_box_options );


		// Mandatory fees — backed by the repeatable fees engine
		// (`class-listeo-core-fees.php`). Each row carries an id, type
		// (flat/percent), frequency (per_stay/per_night/etc.), optional
		// flag, and a small conditions block. Pre-existing rows that
		// only carry title/price/description are upgraded transparently
		// at read time by `listeo_normalize_fee()`; the sanitizer below
		// persists the new fields on next save.
		$group_field_id = $cmb_booking->add_field(array(
			'id'          => '_mandatory_fees',
			'type'        => 'group',
			'description' => __('Mandatory fees (like cleaning fee etc)', 'cmb2'),
			'options'     => array(
				'group_title'       => __('Fee {#}', 'cmb2'),
				'add_button'        => __('Add Another Fee', 'cmb2'),
				'remove_button'     => __('Remove Fee', 'cmb2'),
				'sortable'          => true,
			),
			'sanitization_cb' => function( $value ) {
				if ( ! is_array( $value ) ) {
					return $value;
				}
				$out = array();
				foreach ( array_values( $value ) as $i => $fee ) {
					if ( ! is_array( $fee ) || empty( $fee['title'] ) || ! isset( $fee['price'] ) || '' === $fee['price'] ) {
						continue;
					}
					// Assign deterministic, stable id on save so add-on
					// plugins (Booking Plus) can target a specific row
					// for per-resource overrides. Existing rows that
					// already carry an `id` keep it.
					if ( empty( $fee['id'] ) ) {
						$fee['id'] = 'fee_' . uniqid();
					}
					// Whitelist enum values via the engine's vocabulary.
					if ( function_exists( 'listeo_fee_types' ) ) {
						$types = array_keys( listeo_fee_types() );
						if ( empty( $fee['type'] ) || ! in_array( $fee['type'], $types, true ) ) {
							$fee['type'] = 'flat';
						}
					}
					if ( function_exists( 'listeo_fee_frequencies' ) ) {
						$freqs = array_keys( listeo_fee_frequencies() );
						if ( empty( $fee['frequency'] ) || ! in_array( $fee['frequency'], $freqs, true ) ) {
							$fee['frequency'] = 'per_stay';
						}
					}
					$fee['optional'] = ! empty( $fee['optional'] ) ? 1 : 0;
					// Conditions — CMB2 groups don't support nested-array
					// sub-field ids, so the field-side uses flat ids
					// (`cond_min_guests` etc.). On save we keep BOTH
					// shapes in storage: the flat keys so CMB2 can
					// repopulate them on edit, and the canonical nested
					// `conditions` array so the engine reads them the
					// same way as the LBP frontend.
					$nested = array();
					foreach ( array( 'min_guests', 'max_guests', 'min_nights', 'max_nights' ) as $k ) {
						$flat = 'cond_' . $k;
						if ( isset( $fee[ $flat ] ) && '' !== $fee[ $flat ] ) {
							$nested[ $k ] = (int) $fee[ $flat ];
							$fee[ $flat ] = (int) $fee[ $flat ];
						} else {
							$fee[ $flat ] = '';
						}
					}
					foreach ( array( 'date_from', 'date_to' ) as $k ) {
						$flat = 'cond_' . $k;
						if ( ! empty( $fee[ $flat ] ) ) {
							$fee[ $flat ] = sanitize_text_field( $fee[ $flat ] );
							$nested[ $k ] = $fee[ $flat ];
						} else {
							$fee[ $flat ] = '';
						}
					}
					if ( ! empty( $fee['cond_weekends_only'] ) ) {
						$nested['weekends_only'] = 1;
						$fee['cond_weekends_only'] = 1;
					} else {
						$fee['cond_weekends_only'] = 0;
					}
					$fee['conditions'] = $nested;
					$out[] = $fee;
				}
				return $out;
			},
		));

		// Always-on baseline: title + price + description. This is the
		// legacy UX that's enough for Listeo Core-only sites.
		$cmb_booking->add_group_field($group_field_id, array(
			'name' => __( 'Fee Title', 'listeo_core' ),
			'id'   => 'title',
			'type' => 'text',
		));

		$_fees_advanced = function_exists( 'listeo_fees_advanced_ui_enabled' ) && listeo_fees_advanced_ui_enabled();

		if ( $_fees_advanced ) {
			// LBP active → expose the full engine schema (type / freq /
			// conditions). The simpler price field below adapts its
			// description for percent rates.
			$cmb_booking->add_group_field($group_field_id, array(
				'name'    => __( 'Type', 'listeo_core' ),
				'id'      => 'type',
				'type'    => 'select',
				'options' => function_exists( 'listeo_fee_types' ) ? listeo_fee_types() : array(
					'flat'    => __( 'Flat amount', 'listeo_core' ),
					'percent' => __( 'Percentage', 'listeo_core' ),
				),
				'default' => 'flat',
			));
		}

		$cmb_booking->add_group_field($group_field_id, array(
			'name' => __( 'Fee Price', 'listeo_core' ),
			'desc' => $_fees_advanced
				? __( 'For percentage type, enter the percent (e.g. 10 for 10%).', 'listeo_core' )
				: '',
			'id'   => 'price',
			'type' => 'text',
		));

		if ( $_fees_advanced ) {
			$cmb_booking->add_group_field($group_field_id, array(
				'name'    => __( 'Frequency', 'listeo_core' ),
				'desc'    => __( 'How often this fee is applied within the booking.', 'listeo_core' ),
				'id'      => 'frequency',
				'type'    => 'select',
				'options' => function_exists( 'listeo_fee_frequencies' ) ? listeo_fee_frequencies() : array(
					'per_stay'            => __( 'Per booking', 'listeo_core' ),
					'per_night'           => __( 'Per night', 'listeo_core' ),
					'per_guest'           => __( 'Per guest', 'listeo_core' ),
					'per_guest_per_night' => __( 'Per guest, per night', 'listeo_core' ),
					'per_hour'            => __( 'Per hour', 'listeo_core' ),
					'per_ticket'          => __( 'Per ticket', 'listeo_core' ),
				),
				'default' => 'per_stay',
			));
		}

		$cmb_booking->add_group_field($group_field_id, array(
			'name'        => __( 'Description', 'listeo_core' ),
			'description' => __( 'Short description shown next to the fee.', 'listeo_core' ),
			'id'          => 'description',
			'type'        => 'textarea_small',
		));

		if ( $_fees_advanced ) {
			// Conditions — optional gating per fee. CMB2 groups only support
			// flat sub-field ids, so we name these `cond_*` and re-nest into
			// `$fee['conditions']` in the sanitizer above. They render as
			// flat fields inside the group; on the engine read side they
			// arrive as the nested shape the engine expects.
			$cmb_booking->add_group_field($group_field_id, array(
				'name'       => __( 'Min guests', 'listeo_core' ),
				'desc'       => __( 'Apply only when guests ≥ this number. Leave blank for no minimum.', 'listeo_core' ),
				'id'         => 'cond_min_guests',
				'type'       => 'text_small',
				'attributes' => array( 'type' => 'number', 'min' => '0' ),
			));
			$cmb_booking->add_group_field($group_field_id, array(
				'name'       => __( 'Max guests', 'listeo_core' ),
				'desc'       => __( 'Apply only when guests ≤ this number.', 'listeo_core' ),
				'id'         => 'cond_max_guests',
				'type'       => 'text_small',
				'attributes' => array( 'type' => 'number', 'min' => '0' ),
			));
			$cmb_booking->add_group_field($group_field_id, array(
				'name'       => __( 'Min nights', 'listeo_core' ),
				'desc'       => __( 'Apply only when stay ≥ this many nights.', 'listeo_core' ),
				'id'         => 'cond_min_nights',
				'type'       => 'text_small',
				'attributes' => array( 'type' => 'number', 'min' => '0' ),
			));
			$cmb_booking->add_group_field($group_field_id, array(
				'name'       => __( 'Max nights', 'listeo_core' ),
				'desc'       => __( 'Apply only when stay ≤ this many nights.', 'listeo_core' ),
				'id'         => 'cond_max_nights',
				'type'       => 'text_small',
				'attributes' => array( 'type' => 'number', 'min' => '0' ),
			));
			$cmb_booking->add_group_field($group_field_id, array(
				'name'      => __( 'Active from', 'listeo_core' ),
				'desc'      => __( 'Only apply on or after this date.', 'listeo_core' ),
				'id'        => 'cond_date_from',
				'type'      => 'text_date',
			));
			$cmb_booking->add_group_field($group_field_id, array(
				'name'      => __( 'Active until', 'listeo_core' ),
				'desc'      => __( 'Only apply on or before this date.', 'listeo_core' ),
				'id'        => 'cond_date_to',
				'type'      => 'text_date',
			));
			$cmb_booking->add_group_field($group_field_id, array(
				'name'      => __( 'Weekends only', 'listeo_core' ),
				'desc'      => __( 'Apply only when the check-in date falls on Saturday or Sunday.', 'listeo_core' ),
				'id'        => 'cond_weekends_only',
				'type'      => 'checkbox',
			));
		}

		$cmb_booking->add_field( array(
			'name' => __( 'External Booking link', 'listeo_core' ),
			'desc' => __( 'Use only if you want to redirect users to 3rd party site upon clicking Book now button. Does not require Booking Status to be on' , 'listeo_core' ),
			'id'   => '_booking_link',
			'type' => 'text',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Booking Status', 'listeo_core' ),
			'id'   => '_booking_status',
			'type' => 'checkbox',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Slots Status', 'listeo_core' ),
			'id'   => '_slots_status',
			'type' => 'checkbox',
		));

		$cmb_booking->add_field( array(
			'name' => __( 'Slots', 'listeo_core' ),
			'id'   => '_slots',
			'type' => 'slots',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Event Available Tickets', 'listeo_core' ),
			'id'   => '_event_tickets',
			'type' => 'text',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Max Tickets Per Booking', 'listeo_core' ),
			'desc' => __( 'Maximum number of tickets a user can book at once (leave empty for no limit)', 'listeo_core' ),
			'id'   => '_max_tickets_per_booking',
			'type' => 'text',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Regular Price', 'listeo_core' ),
			'id'   => '_normal_price',
			'type' => 'text',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Weekend Price', 'listeo_core' ),
			'id'   => '_weekday_price', //should be weekend (facepalm)
			'type' => 'text',
		));

		// percentage discount for children field
		$cmb_booking->add_field(
			array(
				'name' => __( 'Children - Percentage off regular price', 'listeo_core' ),
				'id'   => '_children_price',
				'type' => 'text',
				'description' => __( 'Enter percentage discount (e.g. 50 for 50% off). Leave empty for no discount.', 'listeo_core' ),
			)	
		);

		// Animal fee options
		$cmb_booking->add_field( array(
			'name'             => __( 'Pet Fee Type', 'listeo_core' ),
			'id'               => '_animal_fee_type',
			'type'             => 'select',
			'show_option_none' => false,
			'options'          => array(
				'none'     => __( 'No pet fee', 'listeo_core' ),
				'one_time' => __( 'One-time fee per pet', 'listeo_core' ),
				'per_night' => __( 'Per night fee per pet', 'listeo_core' ),
			),
			'default'          => 'none',
		));

		$cmb_booking->add_field( array(
			'name' => __( 'Pet Fee Amount', 'listeo_core' ),
			'id'   => '_animal_fee',
			'type' => 'text',
			'description' => __( 'Amount to charge per pet (one-time or per night, based on selected type)', 'listeo_core' ),
		));
		// price per anmial:
	
		$cmb_booking->add_field( array(
			'name' => __( 'Reservation Fee', 'listeo_core' ),
			'id'   => '_reservation_price',
			'type' => 'text',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Reservation expires after', 'listeo_core' ),
			'id'   => '_expired_after',
			'type' => 'text',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Enable Price per Guest', 'listeo_core' ),
			'id'   => '_count_per_guest',
			'type' => 'checkbox',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Enable Price per Hour', 'listeo_core' ),
			'id'   => '_count_by_hour',
			'type' => 'checkbox',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Enable Stard/End Hours for rental', 'listeo_core' ),
			'id'   => '_rental_timepicker',
			'type' => 'checkbox',
		));
		// time increment option with dropdown sele
		$cmb_booking->add_field( array(
			'name' => __( 'Time increment', 'listeo_core' ),
			'id'   => '_time_increment',
			'type' => 'select',
			'options' => array(
				'5' => '5 minutes',
				'10' => '10 minutes',
				'15' => '15 minutes',
				'30' => '30 minutes',
				'60' => '1 hour',
			),
			'default' => '15',
		));

		$cmb_booking->add_field( array(
			'name' => __( 'Enable End Hour in timepicker', 'listeo_core' ),
			'id'   => '_end_hour',
			'type' => 'checkbox',
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Enable Instant Booking', 'listeo_core' ),
			'id'   => '_instant_booking',
			'type' => 'checkbox',
		));
		$cmb_booking->add_field(array(
			'name' => __('Payment options', 'listeo_core'),
			'id'   => '_payment_option',
			'type' => 'select',
			'desc' => __('Select which payment type you require for a booking', 'listeo_core'),
			'options'   => array(
				'pay_now' => __('Require online payment', 'listeo_core'),
				'pay_maybe' => __('Online or cash payment', 'listeo_core'),
				'pay_cash' => __('Require only cash payment', 'listeo_core'),
			),
		));
		
		$cmb_booking->add_field( array(
			'name' => __( 'Maximum number of guests', 'listeo_core' ),
			'id'   => '_max_guests',
			'type' => 'text',
			'attributes' => array(
				'type' => 'number',
				'pattern' => '\d*',
			),
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Minimum number of guests', 'listeo_core' ),
			'id'   => '_min_guests',
			'type' => 'text',
			'attributes' => array(
				'type' => 'number',
				'pattern' => '\d*',
			),
		));
		// add field for enable children and for max children
		$cmb_booking->add_field(array(
			'name' => __('Enable children option', 'listeo_core'),
			'id'   => '_children',
			'type' => 'checkbox',
		));
		
		$cmb_booking->add_field( array(
			'name' => __( 'Maximum number of children', 'listeo_core' ),
			'id'   => '_max_children',
			'type' => 'text',
			'attributes' => array(
				'type' => 'number',
				'pattern' => '\d*',
			),
		));

		$cmb_booking->add_field(array(
			'name' => __('Enable animals', 'listeo_core'),
			'id'   => '_animals',
			'type' => 'checkbox',
		));

		$cmb_booking->add_field( array(
			'name' => __( 'Minimum stay (in days)', 'listeo_core' ),
			'id'   => '_min_days',
			'type' => 'text',
			'attributes' => array(
				'type' => 'number',
				'pattern' => '\d*',
			),
		));
		$cmb_booking->add_field( array(
			'name' => __( 'Availability Calendar', 'listeo_core' ),
			'id'   => '_availability',
			'type' => 'listeo_calendar',
		));


		// Listing type meta
		$cmb2_ad_type_options = array(
			'id'           => 'listing_ad_type',
			'title'        => __('Ad Campaign Settings', 'listeo_core'),
			'object_types' => array('listing'),
			'show_names'   => true,
			'show_in_rest' => WP_REST_Server::READABLE,
		);
		$cmb2_ad_type_options = new_cmb2_box($cmb2_ad_type_options);
		$cmb2_ad_type_options->add_field(array(
			'name' => __('Ad ID', 'listeo_core'),
			'id'   => 'ad_id',
			'type' => 'text',
			'desc' => __('Enter your ad ID', 'listeo_core'),
		));  
		$cmb2_ad_type_options->add_field(array(
			'name' => __('Ad Campaign Status', 'listeo_core'),
			'id'   => 'ad_status',
			'type' => 'select',
			'desc' => __(
				'Determines ad options',
				'listeo_core'
			),
			'options'   => array(
				'active' => __('Active', 'listeo_core'),
				'paused' => __('Paused', 'listeo_core'),
				'expired' => __('Expired', 'listeo_core'),
				
			),
		));  
		$cmb2_ad_type_options->add_field(array(
			'name' => __('Ad Type', 'listeo_core'),
			'id'   => 'ad_type',
			'type' => 'select',
			'desc' => __(
				'Determines ad options',
				'listeo_core'
			),
			'options'   => array(
				'ppc' => __('Click', 'listeo_core'),
				'ppv' => __('View', 'listeo_core'),
				
			),
		));  
		$cmb2_ad_type_options->add_field(array(
			'name' => __('Ad Placement', 'listeo_core'),
			'id'   => 'ad_placement',
			
			'desc' => __(
				'Determines placement of ad',
				'listeo_core'
			),
			'type'    => 'multicheck',
			'options' => array(
				'home' => 'Home',
				'search' => 'Search',
				'sidebar' => 'Sidebar',
			),
		));  
	}


	private function get_term_custom_fields($taxonomy, $term_id)
	{
		$option_key = "listeo_tax-{$taxonomy}_term_{$term_id}_fields";
		$fields_data = get_option($option_key, array());

		$fields = array();

		if (!empty($fields_data)) {
			foreach ($fields_data as $field) {
				
				// if field type is header, skip it
				if ($field['type'] === 'header') {
					continue;
				}
				// You may need to adjust mapping from your saved format to CMB2 field array
				$fields[] = array(
					'name' => $field['name'] ?? ucfirst($field['id']),
					'id'   => $field['id'],
					'type' => $field['type'], // text, textarea, checkbox, etc.
					'desc' => $field['description'] ?? '',
					'options' => $field['options'] ?? array(),
					'attributes' => isset($field['attributes']) ? $field['attributes'] : array(),
					'default' => $field['default'] ?? '',
					'repeatable' => isset($field['repeatable']) ? $field['repeatable'] : false,
					'after_field' => isset($field['after_field']) ? $field['after_field'] : '',
					'before_row' => isset($field['before_row']) ? $field['before_row'] : '',
					'after_row' => isset($field['after_row']) ? $field['after_row'] : '',
				);
			}
		}
		if (is_array($fields)) {
			$fields = array_map(array($this, 'listeo_fields_for_cmb2'), $fields);
		}
		return $fields;
	}

	function listeo_fields_for_cmb2($value)
	{
			if ($value['type'] == 'select') {
			$value['show_option_none'] = true;
		}
		
		if (is_admin()) {
			if ($value['type'] == 'repeatable') {
				$value['type'] = 'group';
				$value['group_title'] = $value['name'];
				$value['add_button'] = __('Add', 'cmb2');
				$value['remove_button'] = __('Remove', 'cmb2');
				$value['sortable'] = false;
				$x = 0;
				$value['fields'] = array();
				foreach ($value['options'] as $key => $option) {
					$value['fields'][$x]['name'] = $option;
					$value['fields'][$x]['id'] = $key;
					$value['fields'][$x]['type'] = 'text';
					$x++;
				}
			}
		}
		return $value;
	}

	public static function meta_boxes_location() {
		
		$fields = array(
			'id'     => 'locations_tab',
			'title'  => __( 'Location', 'listeo_core' ),
			'fields' => array(
				array(
					'name' => __( 'Address', 'listeo_core' ),
					'id'   => '_friendly_address',
					'type' => 'text',
					'desc' =>  __(
					'Human readable address', 'listeo_core'),
				),			
				array(
					'name' => __( 'Google Maps Address', 'listeo_core' ),
					'id'   => '_address',
					'type' => 'text',
					'desc' => __(
						'Used for geolocation and links', 'listeo_core' ),
				),				
				array(
					'name' => __( 'Latitude', 'listeo_core' ),
					'desc' => __( 'Type Google Maps Address and hit enter - lat/long will be populated automatically', 'listeo_core' ),
					'id'   => '_geolocation_lat',
					'type' => 'text',
					'attributes' => array(
						'type'  => 'number',
						'step'  => 'any',        // Allows decimal numbers
						'min'   => '-90',        // Minimum latitude value
						'max'   => '90',         // Maximum latitude value
						'placeholder' => __('Enter latitude (-90 to 90)', 'listeo_core'),
					),
				),				
				array(
					'name' => __( 'Longitude', 'listeo_core' ),
					'id'   => '_geolocation_long',
					'desc' => __('Type Google Maps Address and hit enter - lat/long will be populated automatically', 'listeo_core'),
					'type' => 'text',
					'attributes' => array(
						'type'  => 'number',
						'step'  => 'any',        // Allows decimal numbers
						'min'   => '-180',       // Minimum longitude value
						'max'   => '180',        // Maximum longitude value
						'placeholder' => __('Enter longitude (-180 to 180)', 'listeo_core'),
					),
				),
				array(
					'name' => __( 'Google Maps Place ID', 'listeo_core' ),
					'id'   => '_place_id',
					'type' => 'text',
					'desc' => sprintf(
						__('Find Place ID on %s', 'listeo_core'),
						'<a href="https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder" target="_blank">Google Places API</a>'
					),
				),
			)
		);

		// Set meta box
		return apply_filters( 'listeo_location_fields', $fields );
	}

	public static function meta_boxes_event() {
		
		$fields = array(
			'id'     => 'event_tab',
			'title'  => __( 'Event fields', 'listeo_core' ),
			'fields' => array(
				array(
					'name' 	=> __( 'Event date:', 'listeo_core' ),
					'id'   	=> '_event_date',
					'type' 	=> 'datetime',
					'invert' => true
				),	
				array(
					'name' 	=> __( 'Event end:', 'listeo_core' ),
					'id'   	=> '_event_date_end',
					'type' 	=> 'datetime',
					'invert' => true
				),	
							
				array(
					'name' 	=> __( 'Size', 'listeo_core' ),
					'id'   	=> '_size',
					'type' 	=> 'text',
					'invert' => true
				),				
				
			)
		);

		// Set meta box
		return apply_filters( 'listeo_event_fields', $fields );
	}
	public static function meta_boxes_prices() {
		
		$fields = array(
			'id'     => 'prices_tab',
			'title'  => __( 'Prices fields', 'listeo_core' ),
			'fields' => array(
				array(
					'name' 	=> __( 'Minimum Price Range:', 'listeo_core' ),
					'id'   	=> '_price_min',
					'type' 	=> 'text',					
				),	
				array(
					'name' 	=> __( 'Maximum Price Range:', 'listeo_core' ),
					'id'   	=> '_price_max',
					'type' 	=> 'text',
				),					
				
			)
		);

		// Set meta box
		return apply_filters( 'listeo_prices_fields', $fields );
	}

	public static function meta_boxes_gallery() {
		
		$fields = array(
			'id'     => 'gallery_tab',
			'title'  => __( 'Gallery', 'listeo_core' ),
			'fields' => array(
				array(
					'name' => __( 'Gallery display layout', 'listeo_core' ),
					'desc' => '',
					'id'   => '_gallery_style',
					'type' => 'select',
					'default' => get_option('listeo_gallery_type','top'),
					'options'   => array(
						'top' => __( 'Gallery on top', 'listeo_core' ),
		    			'content' => __( 'Gallery in content', 'listeo_core' ),
		    			
					)
				),
				array(
					'name' => __( 'Listing gallery', 'listeo_core' ),
					'desc' => '',
					'id'   => '_gallery',
					'type' => 'file_list',
					// 'preview_size' => array( 100, 100 ), // Default: array( 50, 50 )
				    'query_args' => array( 'type' => 'image' ), // Only images attachment
					// Optional, override default text strings
					'text' => array(
						'add_upload_files_text' => __('Add or Upload Images', 'listeo_core' ),
					),
				)
			)
		);

		// Set meta box
		return apply_filters( 'listeo_gallery_fields', $fields );
	}

	public static function meta_boxes_contact() {
	
		$fields = array(
			'id'     => 'contact_tab',
			'title'  => __( 'Contact details', 'listeo_core' ),
			'fields' => array(
				array(
					'name' => __( 'Phone number', 'listeo_core' ),
					'id'   => '_phone',
					'type' => 'text',
				),			
				array(
					'name' => __( 'E-mail', 'listeo_core' ),
					'id'   => '_email',
					'type' => 'text',
				),	array(
					'name' => __( 'Contact Owner Widget  (uses email above) - Add Listeo Contact Widget to Sidebar', 'listeo_core' ),
					'id'   => '_email_contact_widget',
					'type' => 'checkbox',
				),				
				array(
					'name' => __( 'Website', 'listeo_core' ),
					'id'   => '_website',
					'type' => 'text',
				),				
				array(
					'name' => __( 'Facebook', 'listeo_core' ),
					'id'   => '_facebook',
					'type' => 'text',
				),
				array(
					'name' => __( 'x.com', 'listeo_core' ),
					'id'   => '_twitter',
					'type' => 'text',
				),	
				array(
					'name' => __( 'YouTube', 'listeo_core' ),
					'id'   => '_youtube',
					'type' => 'text',
				),
				array(
					'name' => __( 'Instagram', 'listeo_core' ),
					'id'   => '_instagram',
					'type' => 'text',
				),
				array(
					'name' => __( 'TikTok', 'listeo_core' ),
					'id'   => '_tiktok',
					'type' => 'text',
				),
				array(
					'name' => __( 'WhatsApp', 'listeo_core' ),
					'id'   => '_whatsapp',
					'type' => 'text',
					
				),
			)
		);

		// Set meta box
		return apply_filters( 'listeo_contact_fields', $fields );
	}


	public static function meta_boxes_service() {

		$fields = array(
			'id'     => 'service_tab',
			'title'  => __( 'Service fields', 'listeo_core' ),
			'fields' => array(
				
			)
		);

		// Set meta box
		return apply_filters( 'listeo_service_fields', $fields );
	}

	public static function meta_boxes_rental() {
		$fields = array(
			'id'     => 'rental_tab',
			'title'  => __( 'Rental fields', 'listeo_core' ),
			'fields' => array(
				// array(
				// 	'name' 	=> __( 'Area', 'listeo_core' ),
				// 	'id'   	=> '_area',
				// 	'type' 	=> 'text',
				// 	'invert' => true
				// ),					
				// array(
				// 	'name' 	=> __( 'Rooms', 'listeo_core' ),
				// 	'id'   	=> '_rooms',
				// 	'type' 	=> 'text',
				// 	'invert' => false
				// ),			
			)
		);


		// Set meta box
		return apply_filters( 'listeo_rental_fields', $fields );
	}	

	public static function meta_boxes_classifieds() {
		$fields = array(
			'id'     => 'classifieds_tab',
			'title'  => __( 'Classifieds fields', 'listeo_core' ),
			'fields' => array(
				array(
					'name' 	=> __( 'Condition', 'listeo_core' ),
					'id'   	=> '_classifieds_condition',
					'type' 	=> 'select',
					'show_option_none' => true,
					'invert' => true,
					'options'   => array(
						'new' => __( 'New', 'listeo_core' ),
		    			'used' => __( 'Used', 'listeo_core' ),
		    			
					)
				),	array(
					'name' 	=> __( 'Price', 'listeo_core' ),
					'id'   	=> '_classifieds_price',
					'type' 	=> 'text',
					'invert' => true,
				
				),
						
			
			)
		);


		// Set meta box
		return apply_filters( 'listeo_classifieds_fields', $fields );
	}


	public static function meta_boxes_video() {
		
		$fields = array(
			'id'     => 'video_tab',
			'title'  => __( 'Video', 'listeo_core' ),
			'fields' => array(
				'video' => array(
					'name' => __( 'Video', 'listeo_core' ),
					'id'   => '_video',
					'type' => 'textarea',
					'desc'      => __( 'URL to oEmbed supported service','listeo_core' ),
				),
			
			)
		);
		$fields = apply_filters( 'listeo_video_fields', $fields );
		
		// Set meta box
		return $fields;
	}

	public static function meta_boxes_custom() {
		
		$fields = array(
			'id'     => 'custom_tab',
			'title'  => __( 'Custom fields', 'listeo_core' ),
			'fields' => array(
				'video' => array(
					'name' => __( 'Example field', 'listeo_core' ),
					'id'   => '_example',
					'type' => 'text',
					'desc'      => __( 'Example field description','listeo_core' ),
				),
			
			)
		);
		$fields = apply_filters( 'listeo_custom_fields', $fields );
		
		// Set meta box
		return $fields;
	}

		
	function cmb2_render_opening_hours_listeo_field_callback( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		
		if(is_array($escaped_value)){
			foreach ($escaped_value as $key => $time) {
				echo $field_type_object->input( 
					array( 
						'type' => 'text_time', 
						
						'value' => $time,
						'name'  => $field_type_object->_name( '[]' ),
						
						'placeholder' => __('use only 24:00 hour format','listeo_core'),
						'time_format' => 'H:i',
					) );
					echo "<br>";	
			}
		} else {
			echo $field_type_object->input( 
				array( 
					'type' => 'text', 
					
					'class' => 'input', 
					'placeholder' => __('use only 24:00 hour format', 'listeo_core'),
					'name'  => $field_type_object->_name( '[]' ),

				) );	
		}
		
	}
			

	
	/**
	 * Render ListeoMenu Field
	 */
	function cmb2_render_listeomenu_field_callback( $field, $value, $object_id, $object_type, $field_type ) {

		// make sure we specify each part of the value we need.
		$value = wp_parse_args( $value, array(
			'name' => '',
			'cover' => '',
			'description' => '',
			'price'      => '',
			'bookable'      => '',
			'bookable_options'      => '',
			'bookable_quantity'      => '',
		) );

		?>
		<div class="alignleft"><p><label for="<?php echo $field_type->_id( '_name' ); ?>'"><?php echo esc_html( $field_type->_text( 'listeomenu_name_text', 'Name' ) ); ?></label></p>
			<?php echo $field_type->input( array(
				'class' => '',
				'name'  => $field_type->_name( '[name]' ),
				'id'    => $field_type->_id( '_name' ),
				'value' => $value['name'],
				'desc'  => '',
			) ); ?>
		</div>
		<div class="alignleft"><p><label for="<?php echo $field_type->_id( '_cover' ); ?>'"><?php echo esc_html( $field_type->_text( 'listeomenu_cover_text', 'Cover (Media ID)' ) ); ?></label></p>
			<?php echo $field_type->input( array(
				'class' => '',
				'name'  => $field_type->_name( '[cover]' ),
				'id'    => $field_type->_id( '_cover' ),
				'value' => $value['cover'],
				'desc'  => '',
			) ); ?>
		</div>

		
		<div class="alignleft"><p><label for="<?php echo $field_type->_id( '_price' ); ?>'"><?php echo esc_html( $field_type->_text( 'listeomenu_price_text', __('Price','listeo_core') ) ); ?></label></p>
			<?php echo $field_type->input( array(
				'class' => '',
				'name'  => $field_type->_name( '[price]' ),
				'id'    => $field_type->_id( '_price' ),
				'value' => $value['price'],
				'type'  => 'text',
				'desc'  => '',
			) ); ?>
		</div>
		<div class="alignleft"><p><label for="<?php echo $field_type->_id( '_bookable' ); ?>'"><?php echo esc_html( $field_type->_text( 'listeomenu_bookable_text', __('Bookable','listeo_core') ) ); ?></label></p>
			<?php echo $field_type->input( array(
				'class' => '',
				'name'  => $field_type->_name( '[bookable]' ),
				'id'    => $field_type->_id( '_bookable' ),
				'value' => 'on',
				'type'  => 'checkbox',
				'checked'  => ($value['bookable'] == 'on') ? 'checked' : false,

				'desc'  => '',
			) ); ?>
		</div>
			<div class="alignleft"><p><label for="<?php echo $field_type->_id( '_bookable_options' ); ?>'"><?php echo esc_html( $field_type->_text( 'listeomenu_bookable_options_text',__('Bookable Options', 'listeo_core')) ); ?></label></p>
			<?php echo $field_type->select( array(
				'name'  => $field_type->_name( '[bookable_options]' ),
				'id'    => $field_type->_id( '_bookable_options' ),
				'value' => $value['bookable_options'],
				'desc'  => '',
				'options'          => '<option '.selected('onetime',$value['bookable_options'],false).' value="onetime">'.esc_html__('One time fee','listeo_core').'</option>
							<option '.selected('byguest',$value['bookable_options'],false).' value="byguest">'.esc_html__('Multiply by guests','listeo_core').'</option>
							<option '.selected('bydays',$value['bookable_options'],false).' value="bydays">'.esc_html__('Multiply by days','listeo_core').'</option>
							<option '.selected('byguestanddays',$value['bookable_options'],false).' value="byguestanddays">'.esc_html__('Multiply by guests & days ','listeo_core').'</option>'
				
			) ); ?>
		</div>
		<div class="alignleft"><p><label for="<?php echo $field_type->_id( '_bookable_quantity' ); ?>'"><?php echo esc_html( $field_type->_text( 'listeomenu_bookable_quantity_text', __('Bookable Quantity', 'listeo_core') ) ); ?></label></p>
			<?php echo $field_type->input( array(
				'class' => '',
				'name'  => $field_type->_name( '[bookable_quantity]' ),
				'id'    => $field_type->_id( '_bookable_quantity' ),
				'value' => 'on',
				'type'  => 'checkbox',
				'checked'  => ($value['bookable_quantity'] == 'on') ? 'checked' : false,

				'desc'  => '',
			) ); ?>
		</div>
		<div class="alignleft"><p><label for="<?php echo $field_type->_id('_bookable_quantity_max' ); ?>'"><?php echo esc_html( $field_type->_text('listeomenu_bookable_quantity_max_text', __('Bookable Quantity Max', 'listeo_core') ) ); ?></label></p>
			<?php echo $field_type->input( array(
				'class' => '',
				'name'  => $field_type->_name('[bookable_quantity_max]' ),
				'id'    => $field_type->_id('_bookable_quantity_max' ),
				'value' => (isset($value['bookable_quantity_max'])) ? $value['bookable_quantity_max'] : 1,
				'type'  => 'text',

				'desc'  => '',
			) ); ?>
		</div>
		<?php
		/**
		 * Allow plugins (Booking Plus: per-service duration) to render
		 * additional fields inside the admin CMB2 menu-item row. Names
		 * should be generated via $field_type->_name('[your_key]') so
		 * they round-trip through the CMB2 save flow cleanly — the
		 * sanitize callback preserves unknown keys in the saved array.
		 */
		do_action( 'listeo_menu_element_extra_fields_admin', $field, $value, $object_id, $object_type, $field_type );
		?>
		<br class="clear">
		<div><p><label for="<?php echo $field_type->_id( '_description' ); ?>'"><?php echo esc_html( $field_type->_text( 'listeomenu_description_text', __('Description','listeo_core') ) ); ?></label></p>
			<?php echo $field_type->textarea( array(
				'name'  => $field_type->_name( '[description]' ),
				'id'    => $field_type->_id( '_description' ),
				'value' => $value['description'],
				'desc'  => '',
			) ); ?>
		</div>
		<!-- bookable_options
			bookable_quantity -->

			
	
		<?php
		echo $field_type->_desc( true );

	}


	/**
	 * Optionally save the Address values into separate fields
	 */
	function cmb2_split_listeomenu_values( $override_value, $value, $object_id, $field_args ) {
		if ( ! isset( $field_args['split_values'] ) || ! $field_args['split_values'] ) {
			// Don't do the override
			return $override_value;
		}

		$_keys = array(  'name', 'description', 'price','bookable' );

		foreach ( $_keys as $key ) {
			if ( ! empty( $value[ $key ] ) ) {
				update_post_meta( $object_id, $field_args['id'] . 'listing_menu_items_'. $key, $value[ $key ] );
			}
		}

		// Tell CMB2 we already did the update
		return true;
	}
	

	/**
	 * The following snippets are required for allowing the address field
	 * to work as a repeatable field, or in a repeatable group
	 */

	function cmb2_sanitize_listeomenu_field( $check, $meta_value, $object_id, $field_args, $sanitize_object ) {

		// if not repeatable, bail out.
		if ( ! is_array( $meta_value ) || ! $field_args['repeatable'] ) {
			return $check;
		}

		foreach ( $meta_value as $key => $val ) {

			if ( '' == $val['name'] ) {
	            unset( $meta_value[$key] );
	        } else {
				// if($key == 'bookable'){
				// 	$meta_value['bookable'] = 'on';
				// } else {
					if(isset($val['booking'])){
						$val['booking'] = 'on';
					}
					//$meta_value[ $key ] = array_map( 'sanitize_text_field', $val );
					$meta_value[ $key ] = $val;
				//}
			}
			
		}

		return $meta_value;
	}

	function cmb2_types_esc_listeomenu_field( $check, $meta_value, $field_args, $field_object ) {
		// if not repeatable, bail out.
		if ( ! is_array( $meta_value ) || ! $field_args['repeatable'] ) {
			return $check;
		}

		foreach ( $meta_value as $key => $val ) {
			$meta_value[ $key ] = array_map( 'esc_attr', $val );
		}

		return $meta_value;
	}


	function listeo_register_taxonomy_metabox() {
		$prefix = 'listeo_';
		
		// Get all taxonomies registered for the listing post type
		$listing_taxonomies = get_object_taxonomies('listing');
		
		// Filter out taxonomies that shouldn't have metaboxes (if any)
		$excluded_taxonomies = array('region'); // Add any taxonomies to exclude here if needed
		$listing_taxonomies = array_diff($listing_taxonomies, $excluded_taxonomies);
		
	/**
	 * Metabox to add fields to categories and tags
	 */
	$cmb_term = new_cmb2_box( array(
		'id'               => $prefix . 'edit',
		'title'            => esc_html__( 'Listing Taxonomy Meta', 'listeo_core' ), // Doesn't output for term boxes
		'object_types'     => array( 'term' ), // Tells CMB2 to use term_meta vs post_meta
		'taxonomies'       => $listing_taxonomies, // Apply to all listing taxonomies
			// 'new_term_section' => true, // Will display in the "Add New Category" section
		'show_in_rest' => WP_REST_Server::READABLE,
	) );

		$top_layout = get_option('pp_listings_top_layout', 'map');


		switch ($top_layout) {
			case 'search':
			case 'map_searchform':
				$search_forms = listeo_get_search_forms_dropdown('fullwidth');
				break;

			case 'halfsidebar':
			case 'half':
				$search_forms = listeo_get_search_forms_dropdown('split');
				break;

			default:
				$search_forms = listeo_get_search_forms_dropdown('all');
				break;
		}

		// select search form
		$cmb_term->add_field(array(
			'name'           => 'Search Form',
			'desc'           => 'Select which search form will be used for this category',
			'id'             =>  $prefix . 'taxonomy_search_form',
			'type'           => 'select',
			// 'default'        => 'default',
			// 'show_option_none' => true,
			//'options'     => listeo_get_search_forms_dropdown('all')
			'options_cb' => 'listeo_get_search_form_metabox_cb',
			'priority'    => 10,
		));




		// add layout options for category
		$cmb_term->add_field(array(
			'name'           => 'Category Archive Layout',
			'desc'           => 'Choose the general archive layout for this category',
			'id'             =>  $prefix . 'taxonomy_top_layout',
			'type'           => 'select',
			'default'        => get_option('pp_listings_top_layout', 'map'),
			'show_option_none' => false,
			'options'     => array(
				'titlebar' 		=> esc_attr__('Standard titlebar', 'listeo_core'),
				'search' 		=> esc_attr__('Full width search form', 'listeo_core'),
				'map_searchform' => esc_attr__('Map with search form', 'listeo_core'),
				'map' 			=> esc_attr__('Map on top', 'listeo_core'),
				'half' 			=> esc_attr__('Split Map/Content', 'listeo_core'),
				'halfsidebar' 	=> esc_attr__('Split Map/Content with sidebar', 'listeo_core'),
				'disable' 		=> esc_attr__('Disable titlebar', 'listeo_core'),


			),
			'priority'    => 10,

		));


		$cmb_term->add_field(array(
			'name'           => 'Category content layout',
			'desc'           => 'Choose the general archive layout for this category',
			'id'             =>  $prefix . 'taxonomy_content_layout',
			'type'           => 'select',
			'default_cb'       => function () {
				$val = get_option('pp_listings_layout', 'list');
				$allowed = array('list', 'grid', 'compact', 'list_old', 'grid_old');
				return in_array($val, $allowed, true) ? $val : 'list';
			},
			'show_option_none' => false,
			'options'     => array(
				'list' 		=> esc_attr__('List', 'listeo_core'),
				'grid' 		=> esc_attr__('Grid', 'listeo_core'),
				'compact' 	=> esc_attr__('Grid Compact', 'listeo_core'),
				'list_old' 		=> esc_attr__('List Classic', 'listeo_core'),
				'grid_old' 		=> esc_attr__('Grid Classic', 'listeo_core'),
			),

		));

	// Create separate metabox for "Assign Features" field - exclude listing_feature taxonomy
	$taxonomies_with_features = array_diff($listing_taxonomies, array('listing_feature'));

	if (!empty($taxonomies_with_features)) {
		$cmb_term_features = new_cmb2_box( array(
			'id'               => $prefix . 'edit_features',
			'title'            => esc_html__( 'Category Features', 'listeo_core' ),
			'object_types'     => array( 'term' ),
			'taxonomies'       => $taxonomies_with_features, // Apply to category taxonomies only, not listing_feature
			'show_in_rest' => WP_REST_Server::READABLE,
		) );

		// Assign Features for this Category
		$cmb_term_features->add_field( array(
			'name'           => 'Assign Features for this Category',
			'desc'           => 'Features can be created in Listings -> Features',
			'id'             =>  $prefix . 'taxonomy_multicheck',
			'taxonomy'       => 'listing_feature', //Enter Taxonomy Slug
			'type'           => 'taxonomy_multicheck_hierarchical',
			// Optional :
			'text'           => array(
				'no_terms_text' => 'Sorry, no terms could be found.' // Change default text. Default: "No terms"
			),
			'remove_default' => 'true' // Removes the default metabox provided by WP core. Pending release as of Aug-10-1
		) );

			// Get custom listing types dynamically
			$listing_type_options = array();
			if (function_exists('listeo_core_custom_listing_types')) {
				$custom_types_manager = listeo_core_custom_listing_types();
				$listing_types = $custom_types_manager->get_listing_types(true); // Get only active types

				foreach ($listing_types as $type) {
					// Use plural_name or fallback to name with " categories" suffix
					$label = !empty($type->plural_name) ? $type->plural_name : $type->name . ' ' . __('categories', 'listeo_core');
					$listing_type_options[$type->slug] = $label;
				}
			} else {
				// Fallback to hardcoded values if custom listing types not available
				$listing_type_options = array(
					'service'			=> __('Service categories', 'listeo_core'),
					'rental'   			=> __('Rental categories', 'listeo_core'),
					'event'     		=> __('Event categories', 'listeo_core'),
					'classifieds'     	=> __('Classifieds categories', 'listeo_core'),
				);
			}

			$cmb_term->add_field(array(
				'name'           => 'Assign Listing Type category for this Category',
				'desc'           => 'Choose which listing type taxonomy will be assigned to that category',
				'id'             =>  $prefix . 'taxonomy_type',
				'type'           => 'multicheck_inline',
				'options'        => $listing_type_options,

			));
	}

	}

	/**
	 * Callback to determine if the "Assign Features" field should be shown
	 * Only show for category taxonomies, not for listing_feature taxonomy itself
	 */
	function listeo_show_features_field_callback( $field_args, $field ) {
		// Get current taxonomy from multiple sources
		$taxonomy = '';

		// Check URL parameter first
		if ( isset( $_GET['taxonomy'] ) ) {
			$taxonomy = sanitize_text_field( $_GET['taxonomy'] );
		}

		// Check if editing a specific term
		if ( empty( $taxonomy ) && isset( $_GET['tag_ID'] ) && function_exists( 'get_term' ) ) {
			$term = get_term( intval( $_GET['tag_ID'] ) );
			if ( $term && ! is_wp_error( $term ) ) {
				$taxonomy = $term->taxonomy;
			}
		}

		// Check current screen as fallback
		if ( empty( $taxonomy ) && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && isset( $screen->taxonomy ) ) {
				$taxonomy = $screen->taxonomy;
			}
		}

		// Don't show for listing_feature taxonomy itself
		if ( $taxonomy === 'listing_feature' ) {
			return false;
		}

		// Show for all other listing taxonomies (categories, etc.)
		return true;
	}
	
	/*
	 * Custom Icon field for Job Categories taxonomy 
	 **/

	// Add term page
	function listeo_listing_category_add_new_meta_field() {
		
		?>
		<div class="form-field">
	
			<label for="icon"><?php esc_html_e( 'Icon', 'listeo_core' ); ?></label>
				<select class="listeo-icon-select" name="icon" id="icon">
				<option value="empty">Empty</option>	
				<?php 

				 	// $faicons = listeo_fa_icons_list();
				 	
				  //  	foreach ($faicons as $key => $value) {

				  //  		echo '<option value="fa fa-'.$key.'" ';
				  //  		echo '>'.$value.'</option>';
				  //  	}
			   		$faicons = listeo_fa_icons_list();
				 	
				   	foreach ($faicons as $key => $value) {
				   		if($key){
					   		echo '<option value="'.$key.'" ';
					   		
					   		echo '>'.$value.'</option>';	
				   		}
				   		
				   	}

				   	if(!get_option('listeo_iconsmind')!='hide'){
				   		$imicons = vc_iconpicker_type_iconsmind(array());
				   		
					   	foreach ($imicons as $key => $icon_array ) {
					   		$key = key($icon_array);
					   		$value = $icon_array[$key];
					   		echo '<option value="'.$key.'" ';
					   			if(isset($icon) && $icon == $key) { echo ' selected="selected"';}
					   		echo '>'.$value.'</option>';
					   	}
					}
				   ?>

				</select>
			<p class="description"><?php esc_html_e( 'Icon will be displayed in categories grid view','listeo_core' ); ?></p>
		</div>
		<?php wp_enqueue_media(); ?>
		<div class="form-field">
			<label for="_cover"><?php esc_html_e( 'Custom Icon (SVG files only)', 'listeo_core' ); ?></label>
			
				
				<input style="width:100px" type="text" name="_icon_svg" id="_icon_svg" value="">
				<input type='button' class="listeo-custom-image-upload button-primary" value="<?php _e( 'Upload SVG Icon', 'listeo_core' ); ?>" id="uploadimage"/><br />
				<p class="listeo-admin-test-api">We recommend using outline  or monocolor icons from <a href="https://www.svgrepo.com/">https://www.svgrepo.com/</a></p>
		</div>
		<div class="form-field">
			<label for="_cover"><?php esc_html_e( 'Category Cover', 'listeo_core' ); ?></label>
			<input style="width:100px" type="text" name="_cover" id="_cover" value="">
				<input type='button' class="listeo-custom-image-upload button-primary" value="<?php _e( 'Upload Image', 'listeo_core' ); ?>" id="uploadimage"/><br />
			<p class="description"><?php esc_html_e( 'Similar to the single jobs you can add image to the category header. It should be 1920px wide','listeo_core' ); ?></p>
		</div>

		
			
	<?php
	}
	

	// Edit term page
	function listeo_listing_category_edit_meta_field($term) {
	 
		// put the term ID into a variable
		$t_id = $term->term_id;
	 
		// retrieve the existing value(s) for this meta field. This returns an array
		
		?>		
		<tr class="form-field">
			<th scope="row" valign="top">

				<label for="icon"><?php esc_html_e( 'Icon', 'listeo_core' ); ?></label>

			<td>
				<select class="listeo-icon-select" name="icon" id="icon">
					<option value="empty">Empty</option>
				<?php 
					$icon = get_term_meta( $t_id, 'icon', true );
 
				 	$faicons = listeo_fa_icons_list();
				 	
				   	foreach ($faicons as $key => $value) {
				   		if($key){
					   		echo '<option value="'.$key.'" ';
					   		if ($icon == $key) { echo ' selected="selected"';}
					   		echo '>'.$value.'</option>';	
				   		}
				   		
				   	}

				   	if(get_option('listeo_iconsmind')!='hide'){
				   		$imicons = vc_iconpicker_type_iconsmind(array());
				   		
					   	foreach ($imicons as $key => $icon_array ) {
					   		$key = key($icon_array);
					   		$value = $icon_array[$key];
					   		echo '<option value="'.$key.'" ';
					   			if(isset($icon) && $icon == $key) { echo ' selected="selected"';}
					   		echo '>'.$value.'</option>';
					   	}
					}
				   ?>

				</select>
				<p class="description"><?php esc_html_e( 'Icon will be displayed in categories grid view','listeo_core' ); ?></p>
			</td>
		</tr>
		<?php wp_enqueue_media(); ?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="_cover"><?php esc_html_e( 'Custom Icon (SVG files only)', 'listeo_core' ); ?></label></th>
			<td>
				<?php 
				$_icon_svg = get_term_meta( $t_id, '_icon_svg', true );
				
				if($_icon_svg) :
					$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
					
					if ($_icon_svg_image)  {
						echo '<img src="'.$_icon_svg_image[0].'" style="width:300px;height: auto;"/><br>';
					} 
				endif;
				?>
				<input style="width:100px" type="text" name="_icon_svg" id="_icon_svg" value="<?php echo $_icon_svg; ?>">
				<input type='button' class="listeo-custom-image-upload button-primary" value="<?php _e( 'Upload SVG Icon', 'listeo_core' ); ?>" id="uploadimage"/><br />
				<p class="listeo-admin-test-api">We recommend using outline  or monocolor icons from <a href="https://www.svgrepo.com/">https://www.svgrepo.com/</a></p>
			</td>
		</tr>	

		<tr class="form-field">
			<th scope="row" valign="top"><label for="_cover"><?php esc_html_e( 'Category Cover', 'listeo_core' ); ?></label></th>
			<td>
				<?php 
				$cover = get_term_meta( $t_id, '_cover', true );
				
				if($cover) :
					$cover_image = wp_get_attachment_image_src($cover,'medium');
					
					if ($cover_image)  {
						echo '<img src="'.$cover_image[0].'" style="width:300px;height: auto;"/><br>';
					} 
				endif;
				?>
				<input style="width:100px" type="text" name="_cover" id="_cover" value="<?php echo $cover; ?>">
				<input type='button' class="listeo-custom-image-upload button-primary" value="<?php _e( 'Upload Image', 'listeo_core' ); ?>" id="uploadimage"/><br />
			</td>
		</tr>
	<?php
	}


	// Save extra taxonomy fields callback function.
	function listeo_save_taxonomy_custom_meta( $term_id, $tt_id ) {
		$cache_cleared = false;

		if( isset( $_POST['icon'] ) && '' !== $_POST['icon'] ){
	        $icon = $_POST['icon'];
	        update_term_meta( $term_id, 'icon', $icon );
	        $cache_cleared = true;
	    }

	    if( isset( $_POST['_cover'] ) && '' !== $_POST['_cover'] ){
	        $cover = sanitize_title( $_POST['_cover'] );
	        update_term_meta( $term_id, '_cover', $cover );
	    }

	    if( isset( $_POST['_icon_svg'] ) ){
	        $_icon_svg = sanitize_title( $_POST['_icon_svg'] );
	        update_term_meta( $term_id, '_icon_svg', $_icon_svg );
	        $cache_cleared = true;
	    }

	    // Clear icon cache if icon or SVG was updated
	    if ($cache_cleared) {
	    	global $wpdb;
	    	$result = $wpdb->query(
	    		"DELETE FROM {$wpdb->options}
	    		 WHERE option_name LIKE '_transient_listeo_icon_%'
	    		 OR option_name LIKE '_transient_timeout_listeo_icon_%'"
	    	);

	    	// Set transient to show admin notice
	    	if ($result > 0) {
	    		set_transient('listeo_icon_cache_just_cleared', $result, 60);
	    	}
	    }

	}  

	function cmb2_render_callback_for_datetime( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		echo $field_type_object->input( array( 'type' => 'text', 'class' => 'input-datetime' ) );
	}

	function cmb2_render_callback_for_listeo_package($field, $escaped_value, $object_id, $object_type, $field_type_object ){
		    
		    $post_id = get_the_ID();
		    $post_author_id = get_post_field( 'post_author', $post_id );
		    
			if ( version_compare( CMB2_VERSION, '2.2.2', '>=' ) ) {
				$field_type_object->type = new CMB2_Type_Select( $field_type_object );
			}
			echo $field_type_object->select( 
				array(
				'class'     => 'pw_select2 pw_select',
			    'options'   => listeo_core_available_packages($post_author_id,$escaped_value),
			) );

		
			// echo $field_type_object->input( 
			// 	array(
			// 	'type'            => 'checkbox',
			// 	'value' => 'on',
			// 	'name'  => 'package_decrease',
			// 	'id'    => $field_type_object->_id( '_decrease' ),
			//     //'checked'  => ($value['bookable_quantity'] == 'on') ? 'checked' : false,
			// ) );
	}


	function changed_user_package($meta_id, $post_id, $meta_key, $meta_value ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { 
			// this goes when it's the first pass by the new block editor, does NOT occur when Classic Editor is activated
  			// $_POST is not available here
		} else {
			if($meta_key== '_user_package_id'){

				$post_author_id = get_post_field( 'post_author', $post_id );

				
			}
		}
		
   //  if ( "key_1" == $meta_key ) {
 		// print_r( get_post( $post_id ) );
   //  } 
    	return; 
	}

	function check_user_package( $null, $post_id, $meta_key, $meta_value, $prev_value ){
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { 
		} 

		else {
			if(is_admin() && $meta_key== '_user_package_id'){
				
					$decrease = get_post_meta($post_id,'_user_package_decrease',true);
					$post_author_id = get_post_field( 'post_author', $post_id );
					$prev_package = get_post_meta($post_id,'_user_package_id',true);
					$new_package = $meta_value;

				
					listeo_core_increase_package_count($post_author_id, $new_package);
					if($decrease == 'on') {
						listeo_core_decrease_package_count($post_author_id, $prev_package);
					}
					}
			}
	}


	public static function meta_boxes_user_owner(){

		$fields = array(
				'phone' => array(
					'id'                => 'phone',
					'name'              => __( 'Phone', 'listeo_core' ),
					'label'             => __( 'Phone', 'listeo_core' ),
					'type'              => 'text',
					
				),
				'header_social' => array(
					'label'       => __( 'Social', 'listeo_core' ),
					'type'        => 'header',
					'id'          => 'header_social',
					'name'        => __( 'Social', 'listeo_core' ),
				),
				'twitter' => array(
					'id'                => 'twitter',
					'name'              => __('<i class="fa-brands fa-x-twitter"></i> x.com', 'listeo_core' ),
					'label'             => __( '<i class="fa-brands fa-x-twitter"></i> x.com', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://x.com/username',
				),
				'facebook' => array(
					'id'                => 'facebook',
					'name'              => __( '<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://facebook.com/username',
				),

				'linkedin' => array(
					'id'                => 'linkedin',
					'name'              => __( '<i class="fa fa-linkedin"></i> Linkedin', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-linkedin"></i> Linkedin', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://linkedin.com/in/username',
				),
				'instagram' => array(
					'id'                => 'instagram',
					'name'              => __( '<i class="fa fa-instagram"></i> Instagram', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-instagram"></i> Instagram', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://instagram.com/username',
				),				
				'youtube' => array(
					'id'                => 'youtube',
					'name'              => __( '<i class="fa fa-youtube"></i> YouTube', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-youtube"></i> YouTube', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://youtube.com/@channel',
				),
				'tiktok' => array(
					'id'                => 'tiktok',
					'name'              => __( '<i class="fa-brands fa-tiktok"></i> TikTok', 'listeo_core' ),
					'label'             => __( '<i class="fa-brands fa-tiktok"></i> TikTok', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://tiktok.com/@username',
				),
				'whatsapp' => array(
					'id'                => 'whatsapp',
					'name'              => __( '<i class="fa fa-whatsapp"></i> Whatsapp', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-whatsapp"></i> Whatsapp', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://wa.me/1234567890',
				),
			);
		$fields = apply_filters( 'listeo_user_owner_fields', $fields );
		
		// Set meta box
		return $fields;
	}

	public static function meta_boxes_user_guest(){

		$fields = array(
				'phone' => array(
					'id'                => 'phone',
					'name'              => __( 'Phone', 'listeo_core' ),
					'label'             => __( 'Phone', 'listeo_core' ),
					'type'              => 'text',
					
				),
				'header_social' => array(
					'label'       => __( 'Social', 'listeo_core' ),
					'type'        => 'header',
					'id'          => 'header_social',
					'name'        => __( 'Social', 'listeo_core' ),
				),
				'twitter' => array(
					'id'                => 'twitter',
					'name'              => __( '<i class="fa-brands fa-x-twitter"></i> x.com', 'listeo_core' ),
					'label'             => __( '<i class="fa-brands fa-x-twitter"></i> x.com', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://x.com/username',
				),
				'facebook' => array(
					'id'                => 'facebook',
					'name'              => __( '<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://facebook.com/username',
				),

				'linkedin' => array(
					'id'                => 'linkedin',
					'name'              => __( '<i class="fa fa-linkedin"></i> Linkedin', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-linkedin"></i> Linkedin', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://linkedin.com/in/username',
				),
				'instagram' => array(
					'id'                => 'instagram',
					'name'              => __( '<i class="fa fa-instagram"></i> Instagram', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-instagram"></i> Instagram', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://instagram.com/username',
				),				
				'youtube' => array(
					'id'                => 'youtube',
					'name'              => __( '<i class="fa fa-youtube"></i> YouTube', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-youtube"></i> YouTube', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://youtube.com/@channel',
				),
				'tiktok' => array(
					'id'                => 'tiktok',
					'name'              => __( '<i class="fa-brands fa-tiktok"></i> TikTok', 'listeo_core' ),
					'label'             => __( '<i class="fa-brands fa-tiktok"></i> TikTok', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://tiktok.com/@username',
				),
				'whatsapp' => array(
					'id'                => 'whatsapp',
					'name'              => __( '<i class="fa fa-whatsapp"></i> Whatsapp', 'listeo_core' ),
					'label'             => __( '<i class="fa fa-whatsapp"></i> Whatsapp', 'listeo_core' ),
					'type'              => 'text',
					'placeholder'       => 'https://wa.me/1234567890',
				),
			);
		$fields = apply_filters( 'listeo_user_guest_fields', $fields );
		
		// Set meta box
		return $fields;
	}

	/**
	 * Get dynamic listing type options for admin meta boxes
	 */
	public function get_dynamic_listing_type_options() {
		$options = array();
		
		// Check if custom listing types system is available
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$types = $custom_types_manager->get_listing_types(true); // Get active types only
			
			if (!empty($types)) {
				foreach ($types as $type) {
					$options[$type->slug] = __($type->name, 'listeo_core');
				}
			}
		}
		
		// Fallback to default types if custom system not available or no types found
		if (empty($options)) {
			$options = array(
				'service' => __( 'Service', 'listeo_core' ),
				'rental' => __( 'Rental', 'listeo_core' ),
				'event' => __( 'Event', 'listeo_core' ),
				'classifieds' => __( 'Classifieds', 'listeo_core' ),
			);
		}
		
		return $options;
	}

	/**
	 * Magic method to handle dynamic show_on callbacks for custom listing types
	 */
	public function __call($method_name, $arguments) {
		// Handle dynamic show_on callbacks for custom listing types
		if (strpos($method_name, 'listeo_show_on_custom_type_') === 0) {
			$type_slug = str_replace('listeo_show_on_custom_type_', '', $method_name);
			return $this->show_on_custom_listing_type($type_slug, $arguments[0] ?? null);
		}
		
		// For other dynamic methods, trigger a PHP error
		trigger_error("Call to undefined method " . __CLASS__ . "::{$method_name}()", E_USER_ERROR);
	}

	/**
	 * Generic callback to show fields only for specific custom listing type
	 */
	public function show_on_custom_listing_type($type_slug, $cmb) {
		if (!$cmb) {
			return false;
		}
		
		$post_id = $cmb->object_id();
		if (!$post_id) {
			return false;
		}
		
		$listing_type = get_post_meta($post_id, '_listing_type', true);
		return ($listing_type === $type_slug);
	}


}
