<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Listeo_Submit_Editor
{
	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 * @var Listeo_Submit The single instance of the class
	 */
	protected static $_instance = null;

	protected $fields = array();
	/**
	 * Returns static instance of class.
	 *
	 * @return self
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct($version = '1.0.0')
	{

		add_action('admin_menu', array($this, 'add_options_page')); //create tab pages
		add_filter('submit_listing_form_fields', array($this, 'add_listeo_submit_listing_form_fields_form_editor'), 10, 2);

		add_action('wp_ajax_editor_load_field', array($this, 'editor_load_field'));
		add_action('wp_ajax_listeo_editor_save_field', array($this, 'editor_save_field'));
		add_action('wp_ajax_listeo_editor_get_items', array($this, 'editor_get_items'));
		//add_action('wp_ajax_listeo_editor_delete_field', array($this, 'editor_delete_field'));

		// Run one-time cleanup of existing forms
		//add_action('admin_init', array($this, 'cleanup_existing_forms'));

	}

	function add_listeo_submit_listing_form_fields_form_editor($r, $type)
	{
		// Migration logic: Check for legacy 'events' option and migrate to 'event'
		// if ($type == 'event') {
		// 	$legacy_fields = get_option("listeo_submit_events_form_fields");
		// 	if (!empty($legacy_fields)) {
		// 		// Migrate legacy events option to new event option
		// 		update_option("listeo_submit_event_form_fields", $legacy_fields);
		// 		delete_option("listeo_submit_events_form_fields");
		// 	}
		// }

		// fix for wrong name, too late to change it now
		if ($type == 'event') {
			$type = 'events';
		}
		$fields =  get_option("listeo_submit_{$type}_form_fields");

		if (empty($fields)) {
			$fields =  get_option("listeo_submit_form_fields");
		}
		if (!empty($fields)) {
			$r = $fields;
		}

		$r = $this->inject_dynamic_taxonomy_fields($r, $type);

		return $r;
	}

	/**
	 * Inject booking sections for the Form Editor based on listing type configuration.
	 * Uses Core's canonical section definitions, applies saved overrides, and marks as preconfigured.
	 */
	private function inject_booking_sections_for_editor($fields, $listing_type)
	{
		if (!function_exists('listeo_core_custom_listing_types')) {
			return $fields;
		}

		$custom_types_manager = listeo_core_custom_listing_types();
		$type_config = $custom_types_manager->get_listing_type_by_slug($listing_type);

		if (!$type_config) {
			return $fields;
		}

		$booking_features = isset($type_config->booking_features) ? json_decode($type_config->booking_features, true) : array();
		$booking_type = isset($type_config->booking_type) ? $type_config->booking_type : 'disabled';
		$supports_opening_hours = isset($type_config->supports_opening_hours) ? (bool) $type_config->supports_opening_hours : false;

		// If booking is completely disabled, only inject opening_hours if supported
		if ($booking_type === 'none' || $booking_type === 'disabled') {
			if ($supports_opening_hours) {
				$submit_form = new Listeo_Core_Submit();
				$oh_data = $submit_form->get_opening_hours_section();
				$section_data = isset($oh_data['opening_hours']) ? $oh_data['opening_hours'] : $oh_data;
				$section_data['preconfigured'] = true;
				$fields['opening_hours'] = $section_data;
			}
			return $fields;
		}

		$submit_form = new Listeo_Core_Submit();

		// Map section keys to their getter methods and conditions
		$section_map = array(
			'booking' => array(
				'method' => 'get_booking_section',
				'condition' => true, // always when booking enabled
				'extract_key' => 'booking',
			),
			'event' => array(
				'method' => 'get_event_section',
				'condition' => ($booking_type === 'tickets'),
				'extract_key' => 'event',
			),
			'slots' => array(
				'method' => 'get_slot_section',
				'condition' => in_array('time_slots', $booking_features),
				'extract_key' => 'slots',
			),
			'menu' => array(
				'method' => 'get_menu_section',
				'condition' => in_array('services', $booking_features),
				'extract_key' => 'menu',
			),
			'availability_calendar' => array(
				'method' => 'get_availability_section',
				'condition' => in_array('calendar', $booking_features),
				'extract_key' => 'availability_calendar',
			),
			'opening_hours' => array(
				'method' => 'get_opening_hours_section',
				'condition' => $supports_opening_hours,
				'extract_key' => 'opening_hours',
			),
		);

		// Load saved overrides for this listing type
		$saved_overrides = get_option("listeo_booking_section_overrides_{$listing_type}", array());

		foreach ($section_map as $section_key => $config) {
			if (!$config['condition']) {
				continue;
			}

			// Get canonical section definition from Core
			$raw = call_user_func(array($submit_form, $config['method']));
			$extract = $config['extract_key'];
			$section_data = isset($raw[$extract]) ? $raw[$extract] : $raw;

			// Mark as preconfigured
			$section_data['preconfigured'] = true;

			// Apply saved overrides (title, icon, onoff, onoff_state, class)
			if (isset($saved_overrides[$section_key])) {
				$override_props = array('title', 'icon', 'onoff', 'onoff_state', 'class');
				foreach ($override_props as $prop) {
					if (isset($saved_overrides[$section_key][$prop])) {
						$section_data[$prop] = $saved_overrides[$section_key][$prop];
					}
				}
			}

			$fields[$section_key] = $section_data;
		}

		return $fields;
	}

	/**
	 * Inject dynamic taxonomy fields based on listing type configuration
	 */
	private function inject_dynamic_taxonomy_fields($fields, $listing_type)
	{
		// Get listing type configuration
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$type_config = $custom_types_manager->get_listing_type_by_slug($listing_type);

			// Get the taxonomy name for this listing type
			$taxonomy_name = $listing_type . '_category';

			// Check if taxonomy is disabled in listing type settings
			if (!$type_config || !isset($type_config->register_taxonomy) || !$type_config->register_taxonomy) {
				// Taxonomy is disabled - always remove the field (whether auto-injected or manually added)
				$fields = $this->remove_taxonomy_field($fields, $taxonomy_name);
				return $fields;
			}

			// Taxonomy is enabled - check if field already exists and is user-configured
			if ($this->is_taxonomy_field_user_configured($fields, $taxonomy_name)) {
				// Field exists and is user-configured - skip injection and use user's configuration
				return $fields;
			}

			// Either field doesn't exist or is auto-injected - inject/replace it
			if (taxonomy_exists($taxonomy_name)) {
				$taxonomy_obj = get_taxonomy($taxonomy_name);

				// Create the taxonomy field using drilldown-taxonomy instead of term-select
				// This ensures consistency with listing_category and proper custom fields loading
				$taxonomy_field = array(
					'label'       => $taxonomy_obj->label,
					'type'        => 'drilldown-taxonomy',
					'placeholder' => $taxonomy_obj->label,
					'name'        => 'tax-' . $taxonomy_name,
					'taxonomy'    => $taxonomy_name,
					'tooltip'     => sprintf(__('Select %s category', 'listeo_core'), $taxonomy_obj->labels->singular_name),
					'priority'    => 11, // Right after listing_category (priority 10)
					'default'     => '',
					'render_row_col' => '4',
					'multi'       => false,
					'required'    => false,
					'css_class'   => 'dynamic-taxonomy-field', // Mark as dynamic field
				);

				// First remove any existing auto-injected field
				$fields = $this->remove_taxonomy_field($fields, $taxonomy_name, true); // Only remove auto-injected

				// Find the group containing listing_category and inject the taxonomy field there
				$inserted = false;

				foreach ($fields as $group_key => $group_data) {
					if (isset($group_data['fields']) && isset($group_data['fields']['listing_category'])) {
						// Found the group with listing_category, inject our field right after it
						$group_fields = $group_data['fields'];
						$new_group_fields = array();

						foreach ($group_fields as $field_key => $field_data) {
							$new_group_fields[$field_key] = $field_data;

							// Insert right after listing_category
							if ($field_key === 'listing_category') {
								$taxonomy_field_key = 'tax-' . $taxonomy_name;
								$new_group_fields[$taxonomy_field_key] = $taxonomy_field;
								$inserted = true;
							}
						}

						$fields[$group_key]['fields'] = $new_group_fields;
						break; // Found and inserted, exit the loop
					}
				}

				// Fallback: If listing_category wasn't found, add to end of basic_info group or create one
				if (!$inserted) {
					if (!isset($fields['basic_info'])) {
						$fields['basic_info'] = array(
							'title' => __('Basic Information', 'listeo_core'),
							'icon' => 'sl sl-icon-docs',
							'fields' => array()
						);
					}

					// Add the taxonomy field as the last item in basic_info
					$taxonomy_field_key = 'tax-' . $taxonomy_name;
					$fields['basic_info']['fields'][$taxonomy_field_key] = $taxonomy_field;
				}
			}
		}

		return $fields;
	}

	/**
	 * Check if a taxonomy field exists and is user-configured (not auto-injected)
	 */
	private function is_taxonomy_field_user_configured($fields, $taxonomy_name)
	{
		$taxonomy_field_key = 'tax-' . $taxonomy_name;

		// Check in grouped structure
		foreach ($fields as $group_data) {
			if (isset($group_data['fields']) && is_array($group_data['fields'])) {
				foreach ($group_data['fields'] as $field_key => $field_data) {
					// Check if this is our taxonomy field
					if (
						$field_key === $taxonomy_field_key ||
						(isset($field_data['taxonomy']) && $field_data['taxonomy'] === $taxonomy_name)
					) {

						// Check if it's marked as dynamic (auto-injected)
						// If NOT marked as dynamic, it's user-configured
						if (
							!isset($field_data['css_class']) ||
							strpos($field_data['css_class'], 'dynamic-taxonomy-field') === false
						) {
							return true; // User-configured field found
						}
					}
				}
			}
		}

		return false; // No user-configured field found
	}

	/**
	 * Remove a taxonomy field from the fields array
	 *
	 * @param array $fields The fields array
	 * @param string $taxonomy_name The taxonomy name (e.g., 'service_category')
	 * @param bool $only_auto_injected If true, only remove auto-injected fields
	 * @return array Modified fields array
	 */
	private function remove_taxonomy_field($fields, $taxonomy_name, $only_auto_injected = false)
	{
		$taxonomy_field_key = 'tax-' . $taxonomy_name;

		// Handle both flat field structure and grouped structure
		foreach ($fields as $key => $field_or_group) {
			// Check if this is a grouped structure (has 'fields' sub-array)
			if (is_array($field_or_group) && isset($field_or_group['fields'])) {
				// This is a group - clean up fields within it
				foreach ($field_or_group['fields'] as $field_key => $field_data) {
					$should_remove = false;

					// Check if this is our taxonomy field
					if (
						$field_key === $taxonomy_field_key ||
						(isset($field_data['taxonomy']) && $field_data['taxonomy'] === $taxonomy_name)
					) {

						if ($only_auto_injected) {
							// Only remove if it's marked as dynamic (auto-injected)
							if (
								isset($field_data['css_class']) &&
								strpos($field_data['css_class'], 'dynamic-taxonomy-field') !== false
							) {
								$should_remove = true;
							}
						} else {
							// Remove regardless of whether it's auto-injected or user-configured
							$should_remove = true;
						}
					}

					if ($should_remove) {
						unset($fields[$key]['fields'][$field_key]);
					}
				}
			} else {
				// This is a flat field structure - clean up directly
				$should_remove = false;

				// Check if this is our taxonomy field
				if (
					$key === $taxonomy_field_key ||
					(isset($field_or_group['taxonomy']) && $field_or_group['taxonomy'] === $taxonomy_name)
				) {

					if ($only_auto_injected) {
						// Only remove if it's marked as dynamic (auto-injected)
						if (
							isset($field_or_group['css_class']) &&
							strpos($field_or_group['css_class'], 'dynamic-taxonomy-field') !== false
						) {
							$should_remove = true;
						}
					} else {
						// Remove regardless of whether it's auto-injected or user-configured
						$should_remove = true;
					}
				}

				if ($should_remove) {
					unset($fields[$key]);
				}
			}
		}

		return $fields;
	}

	/**
	 * Clean up manually added taxonomy fields from existing forms
	 */
	private function clean_up_manual_taxonomy_fields($fields)
	{
		$listing_type_taxonomies = array('service_category', 'rental_category', 'event_category', 'classifieds_category');

		// Handle both flat field structure (forms editor) and grouped structure (submit forms)
		foreach ($fields as $key => $field_or_group) {

			// Check if this is a grouped structure (has 'fields' sub-array)
			if (is_array($field_or_group) && isset($field_or_group['fields'])) {
				// This is a group - clean up fields within it
				foreach ($field_or_group['fields'] as $field_key => $field_data) {
					// Remove fields that are listing type taxonomies (but keep listing_category and region)
					if (isset($field_data['taxonomy']) && in_array($field_data['taxonomy'], $listing_type_taxonomies)) {
						unset($fields[$key]['fields'][$field_key]);
					}
					// Also remove by key pattern
					elseif (strpos($field_key, 'tax-') === 0) {
						$taxonomy_name = str_replace('tax-', '', $field_key);
						if (in_array($taxonomy_name, $listing_type_taxonomies)) {
							unset($fields[$key]['fields'][$field_key]);
						}
					}
				}
			} else {
				// This is a flat field structure - clean up directly
				// Remove fields that are listing type taxonomies (but keep listing_category and region)
				if (isset($field_or_group['taxonomy']) && in_array($field_or_group['taxonomy'], $listing_type_taxonomies)) {
					unset($fields[$key]);
				}
				// Also remove by key pattern
				elseif (strpos($key, 'tax-') === 0) {
					$taxonomy_name = str_replace('tax-', '', $key);
					if (in_array($taxonomy_name, $listing_type_taxonomies)) {
						unset($fields[$key]);
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * One-time cleanup of existing forms to remove manually added taxonomy fields
	 */
	public function cleanup_existing_forms()
	{
		// Check if cleanup has already been run
		$cleanup_done = get_option('listeo_taxonomy_fields_cleanup_done', false);
		if ($cleanup_done) {
			return;
		}

		// List of form types to clean up
		$form_types = array('service', 'rental', 'event', 'classifieds', ''); // Empty string for default form

		foreach ($form_types as $type) {
			$option_key = $type ? "listeo_submit_{$type}_form_fields" : "listeo_submit_form_fields";
			$fields = get_option($option_key);

			if (!empty($fields)) {
				$cleaned_fields = $this->clean_up_manual_taxonomy_fields($fields);

				// Only update if there were changes
				if ($fields !== $cleaned_fields) {
					update_option($option_key, $cleaned_fields);
				}
			}
		}

		// Mark cleanup as done
		update_option('listeo_taxonomy_fields_cleanup_done', true);
	}

	function init_fields()
	{
		if ($this->fields) {
			return;
		}

		$scale = get_option('listeo_scale', 'sq ft');
		$currency = get_option('listeo_currency');

		$this->fields = array(
			'basic_info' => array(
				'title' 	=> __('Basic Information', 'listeo_core'),
				'class' 	=> '',
				'icon' 		=> 'sl sl-icon-doc',
				'fields' 	=> array(
					'listing_title' => array(
						'label'       => __('Listing Title', 'listeo_core'),
						'type'        => 'text',
						'name'       => 'listing_title',
						'tooltip'	  => __('Type title that also contains a unique feature of your listing (e.g. renovated, air conditioned)', 'listeo_core'),
						'required'    => true,
						'placeholder' => '',
						'class'		  => '',
						'priority'    => 1,
						// 'for_type'	  => '',
						'render_row_col' => '6',
					),
					'_listing_logo' => array(
						'label'       => __('Listing Logo', 'listeo_core'),
						'type'        => 'file',
						'name'       => '_listing_logo',
						'tooltip'	  => __('Upload optional logo for listing', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'class'		  => '',
						'priority'    => 1,
						'render_row_col' => '6',

					),
					'listing_category' => array(
						'label'       => __('Category', 'listeo_core'),
						'type'        => 'drilldown-taxonomy',
						'placeholder' => '',
						'name'        => 'listing_category',
						'taxonomy'	  => 'listing_category',
						'tooltip'	  => __('This is main listings category', 'listeo_core'),
						'priority'    => 10,
						'default'	  => '',
						'render_row_col' => '4',
						'required'    => false,
						'multi'    => true,
						// 'for_type'	  => ''
					),

					'keywords' => array(
						'label'       => __('Keywords', 'listeo_core'),
						'type'        => 'text',
						'tooltip'	  => __('Maximum of 15 keywords related to your business, separated by commas', 'listeo_core'),
						'placeholder' => '',
						'name'        => 'keywords',

						'priority'    => 10,

						'default'	  => '',
						'render_row_col' => '4',
						'required'    => false,
						// 'for_type'	  => ''
					),

					'listing_feature' => array(
						'label'       	=> __('Other Features', 'listeo_core'),
						'type'        	=> 'term-checkboxes',
						'taxonomy'		=> 'listing_feature',
						'name'			=> 'listing_feature',
						'class'		  	 => 'chosen-select-no-single',
						'default'    	 => '',
						'priority'    	 => 2,
						'required'    => false,
						// 'for_type'	  => ''
					),

				),
			),

			'location' =>  array(
				'title' 	=> __('Location', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'icon' 		=> 'sl sl-icon-location',
				'fields' 	=> array(

					'_address' => array(
						'label'       => __('Address', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'name'        => '_address',
						'placeholder' => '',
						'class'		  => '',
						'tooltip'	  => '',
						'priority'    => 7,
						'render_row_col' => '6',
						// 'for_type'	  => ''
					),
					'_friendly_address' => array(
						'label'       => __('Friendly Address', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'name'        => '_friendly_address',
						'placeholder' => '',
						'tooltip'	  => __('Human readable address, if not set, the Google address will be used', 'listeo_core'),
						'class'		  => '',

						'priority'    => 8,
						'render_row_col' => '6',
						// 'for_type'	  => ''
					),
					'region' => array(
						'label'       => __('Region', 'listeo_core'),
						'type'        => 'term-select',
						'required'    => false,
						'name'        => 'region',
						'taxonomy'        => 'region',
						'placeholder' => '',
						'class'		  => '',
						'tooltip'	  => '',
						'multi'    => false,
						'priority'    => 8,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_place_id' => array(
						'label'       => __('Google Maps Place ID', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_place_id',
						'class'		  => '',
						'tooltip'	=> 'Provide your Google Place ID to show Google Reviews',
						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_geolocation_long' => array(
						'label'       => __('Longitude', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_geolocation_long',
						'class'		  => '',
						'tooltip'	  => '',
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_geolocation_lat' => array(
						'label'       => __('Latitude', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_geolocation_lat',
						'class'		  => '',
						'priority'    => 10,
						'tooltip'	  => '',
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
				),
			),
			'gallery' => array(
				'title' 	=> __('Gallery', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'icon' 		=> 'sl sl-icon-picture',
				'fields' 	=> array(
					'_gallery' => array(
						'label'       => __('Gallery', 'listeo_core'),
						'name'       => '_gallery',
						'type'        => 'files',
						'description' => __('By selecting (clicking on a photo) one of the uploaded photos you will set it as Featured Image for this listing (marked by icon with star). Drag and drop thumbnails to re-order images in gallery.', 'listeo_core'),
						'placeholder' => 'Upload images',
						'class'		  => '',
						'priority'    => 1,
						'required'    => false,
						//						'for_type'	  => ''
					),

				),
			),
			'details' => array(
				'title' 	=> __('Details', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'icon' 		=> 'sl sl-icon-docs',
				'fields' 	=> array(
					'listing_description' => array(
						'label'       => __('Description', 'listeo_core'),
						'name'       => 'listing_description',
						'type'        => 'wp-editor',
						'description' => '',
						'placeholder' => 'Upload images',
						'class'		  => '',
						'tooltip'	  => '',
						'priority'    => 1,
						'required'    => true,
						//						'for_type'	  => ''
					),
					'_video' => array(
						'label'       => __('Video', 'listeo_core'),
						'type'        => 'text',
						'name'        => '_video',
						'required'    => false,
						'placeholder' => __('URL to oEmbed supported service', 'listeo_core'),
						'class'		  => '',
						'tooltip'	  => '',
						'priority'    => 5,
						//						'for_type'	  => ''
					),

					'_phone' => array(
						'label'       => __('Phone', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_phone',
						'class'		  => '',
						'priority'    => 9,
						'tooltip'	  => '',
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_website' => array(
						'label'       => __('Website', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_website',
						'class'		  => '',
						'tooltip'	  => '',
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_email' => array(
						'label'       => __('E-mail', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_email',
						'tooltip'	  => '',
						'class'		  => '',
						'priority'    => 10,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_email_contact_widget' => array(
						'label'       => __('Enable Contact Widget', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled listing will display Contact Form Widget that will send emails to this address', 'listeo_core'),
						'required'    => false,
						'value'		  => 'on',
						'placeholder' => '',
						'name'        => '_email_contact_widget',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),

					'_facebook' => array(
						'label'       => __('<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_facebook',
						'class'		  => 'fb-input',
						'tooltip'	  => '',
						'priority'    => 9,
						'render_row_col' => '4',
						//						'for_type'	  => ''
					),
					'_twitter' => array(
						'label'       => __('<i class="fa-brands fa-x-twitter"></i> Twitter', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_twitter',
						'class'		  => 'twitter-input',
						'tooltip'	  => '',
						'priority'    => 9,
						'render_row_col' => '4',
						//						'for_type'	  => ''
					),
					'_youtube' => array(
						'label'       => __('<i class="fa fa-youtube-square"></i> YouTube', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_youtube',
						'class'		  => 'youtube-input',
						'tooltip'	  => '',
						'priority'    => 9,
						'render_row_col' => '4',
						//						'for_type'	  => ''
					),
					'_instagram' => array(
						'label'       => __('<i class="fa fa-instagram"></i> Instagram', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_instagram',
						'class'		  => 'instagram-input',
						'priority'    => 10,
						'tooltip'	  => '',
						'render_row_col' => '4',
						//						'for_type'	  => ''
					),
					'_whatsapp' => array(
						'label'       => __('<i class="fa fa-whatsapp"></i> WhatsApp', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'tooltip'	  => '',
						'placeholder' => '',
						'name'        => '_whatsapp',
						'class'		  => 'whatsapp-input',
						'priority'    => 10,
						'render_row_col' => '4',
						//						'for_type'	  => ''
					),
					'_skype' => array(
						'label'       => __('<i class="fa fa-skype"></i> Skype', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'tooltip'	  => '',
						'name'        => '_skype',
						'class'		  => 'skype-input',
						'priority'    => 10,
						'render_row_col' => '4',
						//						'for_type'	  => ''
					),

					'_price_min' => array(
						'label'       => __('Minimum Price Range', 'listeo_core'),
						'type'        => 'number',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_price_min',
						'tooltip'	  => __('Set only minimum price to show "Prices start from " instead of a range', 'listeo_core'),
						'class'		  => '',
						'priority'    => 9,
						'render_row_col' => '6',
						'atts' => array(
							'step' => 0.1,
							'min'  => 0,
						),
						//						'for_type'	  => ''
					),
					'_price_max' => array(
						'label'       => __('Maximum Price Range', 'listeo_core'),
						'type'        => 'number',
						'required'    => false,
						'placeholder' => '',
						'tooltip'	  => __('Set the maximum price for your service, used on filters in search form', 'listeo_core'),
						'name'        => '_price_max',
						'class'		  => '',
						'priority'    => 9,
						'render_row_col' => '6',
						'atts' => array(
							'step' => 0.1,
							'min'  => 0,
						),
						//						'for_type'	  => ''
					),

				),
			),

			// 'opening_hours' => array(
			// 	'title' 	=> __('Opening Hours', 'listeo_core'),
			// 	//'class' 	=> 'margin-top-40',
			// 	'onoff'		=> true,
			// 	'icon' 		=> 'sl sl-icon-clock',
			// 	'fields' 	=> array(
			// 		'_opening_hours_status' => array(
			// 			'label'       => __('Opening Hours status', 'listeo_core'),
			// 			'type'        => 'skipped',
			// 			'required'    => false,
			// 			'name'        => '_opening_hours_status',
			// 		),
			// 		'_opening_hours' => array(
			// 			'label'       => __('Opening Hours', 'listeo_core'),
			// 			'name'       => '_opening_hours',
			// 			'type'        => 'hours',
			// 			'placeholder' => '',
			// 			'class'		  => '',
			// 			'priority'    => 1,
			// 			'required'    => false,
			// 			//						'for_type'	  => ''
			// 		),
			// 		'_listing_timezone' => array(
			// 			'label'       => __('Listing Timezone', 'listeo_core'),
			// 			'type'        => 'timezone',
			// 			'required'    => false,
			// 			'name'        => '_listing_timezone',
			// 		),

			// 	),
			// ),
			'event' => array(
				'title'		=> __('Event Date', 'listeo_core'),
				//'class'		=> 'margin-top-40',
				'icon'		=> 'fa fa-money',
				'fields'	=> array(
					'_event_date' => array(
						'label'       => __('Event Date', 'listeo_core'),
						'tooltip'	  => __('Select a date when an event will start', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'name'        => '_event_date',
						'class'		  => 'input-datetime',
						'placeholder' => '',
						'priority'    => 9,

						'render_row_col' => '6',
						//						'for_type'	  => ''
					),
					'_event_date_end' => array(
						'label'       => __('Event Date End', 'listeo_core'),
						'tooltip'	  => __('Select a date when an event will end', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'name'        => '_event_date_end',
						'class'		  => 'input-datetime',
						'placeholder' => '',
						'priority'    => 9,
						'render_row_col' => '6',
						//						'for_type'	  => ''
					),

				)
			),
			'classifieds' => array(
				'title'		=> __('Classifieds', 'listeo_core'),
				//'class'		=> 'margin-top-40',
				'icon'		=> 'fa fa-bullhorn',
				'fields'	=> array(
					'_classifieds_price' => array(
						'label'       => __('Price ', 'listeo_core'),
						'tooltip'	  => __('Select condition of item for sale', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'name'        => '_classifieds_price',
						'class'		  => '',
						'placeholder' => '',

						'priority'    => 9,
						'render_row_col' => '6'
					),
					'_classifieds_condition' => array(
						'label'       => __('Condition ', 'listeo_core'),
						'tooltip'	  => __('Select condition of item for sale', 'listeo_core'),
						'type'        => 'select',
						'required'    => false,
						'name'        => '_classifieds_condition',
						'class'		  => '',
						'placeholder' => '',
						'options'   => array(
							'new' => __('New', 'listeo_core'),
							'used' => __('Used', 'listeo_core'),

						),
						'priority'    => 9,
						'render_row_col' => '6'
					),



				)
			),
			// 'menu' => array(
			// 	'title' 	=> __('Pricing & Bookable Services', 'listeo_core'),
			// 	//'class' 	=> 'margin-top-40',
			// 	'onoff'		=> true,
			// 	'icon' 		=> 'sl sl-icon-book-open',
			// 	'fields' 	=> array(
			// 		'_menu_status' => array(
			// 			'label'       => __('Menu status', 'listeo_core'),
			// 			'type'        => 'skipped',
			// 			'required'    => false,
			// 			'name'        => '_menu_status',
			// 			//						'for_type'	  => ''
			// 		),
			// 		'_menu' => array(
			// 			'label'       => __('Pricing', 'listeo_core'),
			// 			'name'       => '_menu',
			// 			'type'        => 'pricing',
			// 			'placeholder' => '',
			// 			'class'		  => '',
			// 			'priority'    => 1,
			// 			'required'    => false,
			// 			//						'for_type'	  => ''
			// 		),


			// 	),
			// ),
			//'booking' => array(
			// 	'title' 	=> __('Booking', 'listeo_core'),
			// 	'class' 	=> 'margin-top-4000 booking-enable',
			// 	'onoff'		=> true,
			// 	//'onoff_state' => 'on',
			// 	'icon' 		=> 'fa fa-calendar-check',
			// 	'fields' 	=> array(
			// 		'_booking_status' => array(
			// 			'label'       => __('Booking status', 'listeo_core'),
			// 			'type'        => 'skipped',
			// 			'required'    => false,
			// 			'name'        => '_booking_status',
			// 			//						'for_type'	  => ''

			// 		),
			// 	)
			// ),
			// 'slots' => array(
			// 	'title' 	=> __('Availability', 'listeo_core'),
			// 	//'class' 	=> 'margin-top-40',
			// 	'onoff'		=> true,
			// 	'icon' 		=> 'fa fa-calendar-check',
			// 	'fields' 	=> array(
			// 		'_slots_status' => array(
			// 			'label'       => __('Booking status', 'listeo_core'),
			// 			'type'        => 'skipped',
			// 			'required'    => false,
			// 			'name'        => '_slots_status',
			// 			//						'for_type'	  => '',
			// 			'tooltip'	  => '',
			// 		),
			// 		'_slots' => array(
			// 			'label'       => __('Availability Calendar', 'listeo_core'),
			// 			'name'       => '_slots',
			// 			'type'        => 'slots',
			// 			'placeholder' => '',
			// 			'class'		  => '',
			// 			'priority'    => 1,
			// 			'required'    => false,
			// 			'tooltip'	  => '',
			// 			//						'for_type'	  => ''
			// 		),

			// 	),
			// ),


			'basic_prices' => array(
				'title'		=> __('Booking prices and settings', 'listeo_core'),
				//'class'		=> 'margin-top-40',
				'icon'		=> 'fa fa-money',
				'fields'	=> array(

					'_event_tickets' => array(
						'label'       => __('Available Tickets', 'listeo_core'),
						'tooltip'	  => __('How many ticekts you have to offer', 'listeo_core'),
						'type'        => 'number',
						'required'    => false,
						'name'        => '_event_tickets',
						'class'		  => '',
						'placeholder' => '',
						'priority'    => 9,
						'render_row_col' => '6',
						//						'for_type'	  => ''
					),

					'_max_tickets_per_booking' => array(
						'label'       => __('Max Tickets Per Booking', 'listeo_core'),
						'tooltip'	  => __('Maximum number of tickets a user can book at once (leave empty for no limit)', 'listeo_core'),
						'type'        => 'number',
						'required'    => false,
						'name'        => '_max_tickets_per_booking',
						'class'		  => '',
						'placeholder' => '',
						'priority'    => 9.5,
						'render_row_col' => '6',
						//						'for_type'	  => ''
					),

					'_normal_price' => array(
						'label'       => __('Regular Price', 'listeo_core'),
						'type'        => 'number',
						'tooltip'	  => __('Default price for booking on Monday - Friday', 'listeo_core'),
						'required'    => false,
						'default'           => '0',
						'placeholder' => '',
						'unit'		  => $currency,
						'name'        => '_normal_price',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6',
						//						'for_type'	  => ''

					),

					'_weekday_price' => array(
						'label'       => __('Weekend Price', 'listeo_core'),
						'type'        => 'number',
						'required'    => false,
						'tooltip'	  => __('Default price for booking on weekend', 'listeo_core'),
						'placeholder' => '',
						'name'        => '_weekday_price',
						'unit'		  => $currency,
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6',
						//						'for_type'	  => ''
					),
					'_reservation_price' => array(
						'label'       => __('Reservation Fee', 'listeo_core'),
						'type'        => 'number',
						'required'    => false,
						'name'        => '_reservation_price',
						'tooltip'	  => __('One time fee for booking', 'listeo_core'),
						'placeholder' => '',
						'unit'		  => $currency,
						'default'           => '0',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6',
						//						'for_type'	  => ''

					),
					'_expired_after' => array(
						'label'       => __('Reservation expires after', 'listeo_core'),
						'tooltip'	  => __('How many hours you can wait for clients payment', 'listeo_core'),
						'type'        => 'number',
						'default'     => '48',
						'required'    => false,
						'name'        => '_expired_after',
						'placeholder' => '',
						'class'		  => '',
						'unit'		  => 'hours',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6',
						//						'for_type'	  => ''
					),

					'_instant_booking' => array(
						'label'       => __('Enable Instant Booking', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled booking request will be immediately approved ', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_instant_booking',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),

					'_mandatory_fees' => array(
						'label'       => __('Mandatory Fees', 'listeo_core'),
						'type'        => 'repeatable',
						'tooltip'	  => __('Set mandatory fees that will always be added to total cost', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_mandatory_fees',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6',
						'options'   => array(
							'title' => __('Title', 'listeo_core'),
							'price' => __('Price', 'listeo_core'),
						),
					),
					'_min_days' => array(
						'label'       => __('Minimum  stay', 'listeo_core'),
						'type'        => 'number',
						'tooltip'	  => __('Set minimum number of days for reservation', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_min_days',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => 'rental'
					),
					'_max_guests' => array(
						'label'       => __('Maximum number of guests', 'listeo_core'),
						'type'        => 'number',
						'tooltip'	  => __('Set maximum number of guests per reservation', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_max_guests',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_min_guests' => array(
						'label'       => __('Minimum number of guests', 'listeo_core'),
						'type'        => 'number',
						'tooltip'	  => __('Set minimum number of guests per reservation', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_min_guests',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_zoom_booking_enabled' => array(
						'label'       => __('Enable Zoom meetings for bookings', 'listeo_core'),
						'type'        => 'checkbox',
						'name'        => '_zoom_booking_enabled',
						'priority'    => 6,
						'default'     => '',
						'render_row_col' => '3',
						'placeholder' => '',
						'description' => __('Automatically create Zoom meetings when bookings are confirmed. You must connect your Zoom account in your profile first.', 'listeo_core'),
					),
					'_count_per_guest' => array(
						'label'       => __('Enable Price per Guest', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled regular price and weekend price will be multiplied by number of guests to estimate total cost', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_count_per_guest',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_end_hour' => array(
						'label'       => __('Enable End Hour time-picker', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('If you are not using slots, you can allow guest to set end time for booking by enabling that option ', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_end_hour',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => 'service'
					),
					'_rental_timepicker' => array(
						'label'       => __('Enable Rental Time Picker', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('If you are not using slots, you can allow guest to set end time for booking by enabling that option ', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_rental_timepicker',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						'for_type'	  => 'rental'
					),
					'_time_increment' => array(
						'label'       => __('Time Increment for time picker', 'listeo_core'),
						'type'        => 'select',
						'tooltip'	  => __('Set time increment for time picker', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_time_increment',
						'class'		  => '',
						'priority'    => 10,
						'render_row_col' => '3',
						'options'   => array(
							'5' => __('5 minutes', 'listeo_core'),
							'10' => __('10 minutes', 'listeo_core'),
							'15' => __('15 minutes', 'listeo_core'),
							'30' => __('30 minutes', 'listeo_core'),
							'60' => __('1 hour', 'listeo_core'),
						),
					),
					'_count_by_hour' => array(
						'label'       => __('Enable Price per Hour', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled regular price and weekend price will be multiplied by hours booked, requires End Hour field to be ON', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_count_by_hour',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3',
						//						'for_type'	  => ''
					),
					'_email_message' => array(
						'label'       => __('Informations for Email Message', 'listeo_core'),
						'type'        => 'textarea',
						'tooltip'	  => __('Informations you add below can be send via email to user after booking, you can put here details like providing key, pin code, exact address etc.', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_email_message',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6'
					),

				),
			),

			// 'availability_calendar' => array(
			// 	'title' 	=> __('Availability Calendar', 'listeo_core'),
			// 	//'class' 	=> 'margin-top-40',
			// 	//'onoff'		=> true,
			// 	'icon' 		=> 'fa fa-calendar-check',
			// 	'fields' 	=> array(
			// 		'_availability' => array(
			// 			'label'       => __('Click day in calendar to mark it as unavailable', 'listeo_core'),
			// 			'tooltip'	  => '',
			// 			'name'       => '_availability',
			// 			'type'        => 'calendar',
			// 			'placeholder' => '',
			// 			'class'		  => '',
			// 			'priority'    => 1,
			// 			'required'    => false,
			// 			//						'for_type'	  => ''
			// 		),

			// 	),
			// ),


		);
	}

	/**
	 * Add menu options page
	 * @since 0.1.0
	 */
	public function add_options_page()
	{
		add_submenu_page('listeo-fields-and-form', 'Add Listing Form', 'Add Listing Form', 'manage_options', 'listeo-submit-builder', array($this, 'output'));
	}

	/**
	 * Get tabs for all available listing types (both default and custom)
	 */
	private function get_listing_type_tabs()
	{
		$tabs = array();

		// Get all listing types (custom and default)
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$listing_types = $custom_types_manager->get_listing_types(false, true);

			foreach ($listing_types as $type) {
				if ($type->is_active) {
					$tab_key = $type->slug . '_tab';
					$tab_label = sprintf(__('Submit %s Form', 'listeo-fafe'), $type->name);
					$tabs[$tab_key] = $tab_label;
				}
			}
		} else {
			// Fallback to hardcoded default types if custom types not available
			$tabs = array(
				'service_tab'      => __('Submit Service Form', 'listeo-fafe'),
				'rental_tab'       => __('Submit Rental Form', 'listeo-fafe'),
				'event_tab'        => __('Submit Event Form', 'listeo-fafe'),
				'classifieds_tab'  => __('Submit Classifieds Form', 'listeo-fafe'),
			);
		}

		return apply_filters( 'listeo_submit_builder_tabs', $tabs );
	}


	public function output()
	{
		// Add custom CSS for preconfigured sections
?>
		<style>
			.preconfigured-section {
				background-color: #f9f9f9;
				border-left: 4px solid #e5e428;
				position: relative;
			}

			.preconfigured-section .listeo-fafe-section {
				opacity: 0.8;
			}

			.preconfigured-notice {
				font-size: 12px;
				color: #00a0d2;
				font-weight: bold;
				margin-left: 10px;
			}

			.preconfigured-notice-box {
				background-color: #e7f7ff;
				border: 1px solid #00a0d2;
				border-radius: 4px;
				padding: 15px;
				margin: 10px 0;
			}

			.preconfigured-notice-box p {
				margin: 5px 0;
				color: #0073aa;
			}

			/* .preconfigured-field {
			background-color: #f5f5f5;
			border-left: 3px solid #00a0d2;
		} */
			.preconfigured-notice-small {
				color: #00a0d2;
				font-size: 16px;
				cursor: help;
			}

			.preconfigured-add-disabled {
				background-color: #f5f5f5;
				border: 2px dashed #ccc;
				margin: 10px 0;
				border-radius: 4px;
			}
		</style>
		<?php

		// Get dynamic tabs from custom listing types
		$tabs = $this->get_listing_type_tabs();
		$default_tab = !empty($tabs) ? array_key_first($tabs) : 'service_tab';
		$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $default_tab;
		if (!empty($_GET['reset-fields']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'reset')) {
			$tab_slug = substr($tab, 0, -4);

			delete_option("listeo_submit_{$tab_slug}form_fields");
			delete_option("listeo_submit_{$tab_slug}_form_fields");
			delete_option("listeo_submit_form_fields");
			echo '<div class="updated"><p>' . __('The fields were successfully reset.', 'listeo') . '</p></div>';
		}
		// based on tab get the fields using the function from Listeo_Core_Submit_Form class



		if (!empty($_POST)) {
			// Verify nonce for security
			if (!wp_verify_nonce($_POST['_wpnonce'], 'save-fields') && !wp_verify_nonce($_POST['_wpnonce'], 'save')) {
				wp_die(__('Security check failed. Please try again.', 'listeo-fafe'));
			}

			//remove "_tab" from the end of the tab name
			$tab_slug = substr($tab, 0, -4);

			echo $this->form_editor_save($tab_slug);
		}

		$saved_fields = get_option("listeo_submit_form_fields");

		// Get listing type slug from tab (remove '_tab' suffix)
		$listing_type = substr($tab, 0, -4);

		// Check if this is a resource tab (injected by add-ons like Booking Plus)
		$is_resource_tab = (strpos($listing_type, 'resource_') === 0);
		$active_tab_key = $tab;

		if ($is_resource_tab) {
			// Resource tabs: load defaults from filter, saved fields from option
			$default_fields = apply_filters('listeo_submit_resource_default_fields', array(), $listing_type);
			$saved_fields = get_option("listeo_submit_{$listing_type}_form_fields");
			$submit_fields = (!empty($saved_fields)) ? $saved_fields : $default_fields;

			// Apply saved section ordering
			$saved_order = get_option("listeo_section_order_{$listing_type}", array());
			if (!empty($saved_order)) {
				$ordered = array();
				foreach ($saved_order as $sk) {
					if (isset($submit_fields[$sk])) {
						$ordered[$sk] = $submit_fields[$sk];
					}
				}
				foreach ($submit_fields as $sk => $sd) {
					if (!isset($ordered[$sk])) {
						$ordered[$sk] = $sd;
					}
				}
				$submit_fields = $ordered;
			}
		} else {
			// Load the Listeo_Core_Submit class to get default fields
			$submit_form = new Listeo_Core_Submit();

			// Get fields for this listing type
			$default_fields = $submit_form->get_fields_for_listing_type($listing_type);
			$saved_fields = get_option("listeo_submit_{$listing_type}_form_fields");

			if (empty($saved_fields)) {
				$saved_fields = get_option("listeo_submit_form_fields");
			}

			$submit_fields = (!empty($saved_fields)) ? $saved_fields : $default_fields;

			// Remove stale booking sections from saved data (will be re-injected fresh)
			$booking_section_keys = array('booking', 'slots', 'menu', 'availability_calendar', 'opening_hours', 'event');
			foreach ($booking_section_keys as $bsk) {
				unset($submit_fields[$bsk]);
			}

			// Inject booking sections from Core's canonical definitions, with saved overrides
			$submit_fields = $this->inject_booking_sections_for_editor($submit_fields, $listing_type);

			// Apply saved section ordering
			$saved_order = get_option("listeo_section_order_{$listing_type}", array());
			if (!empty($saved_order)) {
				$ordered = array();
				foreach ($saved_order as $sk) {
					if (isset($submit_fields[$sk])) {
						$ordered[$sk] = $submit_fields[$sk];
					}
				}
				foreach ($submit_fields as $sk => $sd) {
					if (!isset($ordered[$sk])) {
						$ordered[$sk] = $sd;
					}
				}
				$submit_fields = $ordered;
			}
		}

		//         ini_set('xdebug.var_display_max_depth', '10');
		// ini_set('xdebug.var_display_max_children', '256');
		// ini_set('xdebug.var_display_max_data', '1024');

		//         ini_set('xdebug.var_display_max_depth', '10');
		// ini_set('xdebug.var_display_max_children', '256');
		// ini_set('xdebug.var_display_max_data', '1024');

		?>

		<div class="listeo-editor-modal">
			<div class="listeo-editor-modal-content">
				<form id="listeo-core-fafe-submit">
					<div class="listeo-editor-modal-header">
						<h3 class="listeo-editor-modal-title">Edit Field</h3>
						<a title="Close" href="#" class="listeo-modal-close">
							<span class="dashicons dashicons-no"></span>
						</a>
					</div>

					<div class="listeo-modal-form">
					</div>

					<div class="listeo-editor-modal-footer">
						<button class="button button-large listeo-modal-close " id="listeo-cancel">Cancel</button>
						<button class="button button-primary button-large " id="listeo-save-field">Save Field</button>
					</div>
				</form>
			</div>
		</div>

		<h2>Add Listing Form Editor</h2>
		<div class="listeo-editor-wrap">
			<div class="nav-tab-container">
				<h2 class="nav-tab-wrapper  form-builder">
					<?php
					// Separate listing type tabs from resource tabs
					$listing_tabs = array();
					$resource_tabs_list = array();
					foreach ($tabs as $key => $value) {
						if (strpos($key, 'resource_') === 0) {
							$resource_tabs_list[$key] = $value;
						} else {
							$listing_tabs[$key] = $value;
						}
					}

					// Output listing type tabs
					foreach ($listing_tabs as $key => $value) {
						$active = ($key == $tab) ? 'nav-tab-active' : '';
						echo '<a class="nav-tab ' . $active . '" href="' . admin_url('admin.php?page=listeo-submit-builder&tab=' . esc_attr($key)) . '">' . esc_html($value) . '</a>';
					}

					// Output resource tabs with subtitle
					if (!empty($resource_tabs_list)) {
						echo '<span class="nav-tab-subtitle">' . esc_html__('Resource Forms', 'listeo-fafe') . '</span>';
						foreach ($resource_tabs_list as $key => $value) {
							$active = ($key == $tab) ? 'nav-tab-active' : '';
							echo '<a class="nav-tab ' . $active . '" href="' . admin_url('admin.php?page=listeo-submit-builder&tab=' . esc_attr($key)) . '">' . esc_html($value) . '</a>';
						}
					}
					?>
				</h2>
			</div>
			<!-- <div class="listeo-admin-notice"> You can configure fields for selected listing type (Edit field and use "For Type" option). Here you can filter fields to see how form will look for each type of listing.
		</div> -->
			<!-- <ul class="listeo-editor-listing-types">
			<li><a href="#" class="show-fields-type-all active">Show all fields</a></li>
			<li><a href="#" class="show-fields-type-service">Show fields for Services</a></li>
			<li><a href="#" class="show-fields-type-rentals">Show fields for Rentals</a></li>
			<li><a href="#" class="show-fields-type-event">Show fields for Events</a></li>
			<li><a href="#" class="show-fields-type-classifieds">Show fields for Classifieds</a></li>
		</ul> -->
			<div class="modal micromodal-slide" id="listeo-add-step-modal" aria-hidden="true">
				<div class="modal__overlay" tabindex="-1" data-micromodal-close>
					<div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="add-step-title">
						<header class="modal__header">
							<h2 class="modal__title" id="add-step-title">Add New Step</h2>
							<button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
						</header>
						<main class="modal__content">
							<form id="listeo-add-step-form">
								<label for="new-step-title">Step title</label>
								<input type="text" id="new-step-title" name="new-step-title" required minlength="2" style="width: 100%; padding: 6px;">
								<div style="margin-top: 1rem;">
									<button type="submit" class="button button-primary">Add Step</button>
								</div>
							</form>
						</main>
					</div>
				</div>
			</div>
			<div class="wrap add-listing-form-builder listeo-forms-builder clearfix">
				<?php
				$tab_slug = substr($tab, 0, -4);
				$steps = get_option("listeo_submit_{$tab_slug}form_steps");

				// Get booking type configuration for this listing type
				$booking_type = '';
				if (function_exists('listeo_core_custom_listing_types')) {
					$custom_types_manager = listeo_core_custom_listing_types();
					$type_config = $custom_types_manager->get_listing_type_by_slug($tab_slug);
					if ($type_config && isset($type_config->booking_type)) {
						$booking_type = $type_config->booking_type;
					}
				}
				?>
				<script>
					var savedStepConfiguration = <?php echo json_encode($steps ?: []); ?>;
					var currentListingTypeBookingType = <?php echo json_encode($booking_type); ?>;
				</script>

				<form method="post" id="mainform" data-tab="<?php echo esc_attr($tab); ?>" action="admin.php?page=listeo-submit-builder&amp;tab=<?php echo esc_attr($tab); ?>">
					<h3 class="listeo-editor-form-header">
						<?php
						foreach ($tabs as $key => $value) {
							if ($active = ($key == $tab)) {
								echo esc_html__($value);
							}
						} ?>
						<input name="save_changes" type="submit" class="button-primary" value="Save Changes">
					</h3>
					<?php $steps_status = get_option("listeo_enable_{$tab_slug}form_steps", 'off'); ?>
					<div id="listeo-form-steps-editor">
						<div class="form-steps-header">
							<h3>Form Multi Steps</h3>
							<div class="form-steps-toggle">
								<label class="toggle-switch">
									<input type="checkbox" name="listeo_enable_<?php echo $tab_slug; ?>form_steps" id="listeo-enable-form-steps" <?php checked($steps_status, 'on'); ?>
										value="on">
									<span class="toggle-slider"></span>
								</label>
							</div>
						</div>
						<div class="form-step-blocks-wrapper" <?php if ($steps_status != 'on') echo 'style="display: none;"'; ?>>
							<div class=" form-step-blocks row-container">
								<?php if (empty($steps)) : ?>
									<div class="no-steps-message" style="padding: 20px; color: #888;">
										<?php _e('No steps have been added yet. Click "Add New Step" to create your first step.', 'listeo'); ?>
									</div>
								<?php endif; ?>

							</div>
							<div class="buttons-wrapper">
								<button id="add-step" class="button button-primary">Add New Step</button>
							</div>
						</div>
					</div>
					<div id="listeo-step-block-template-wrapper" style="display:none;">
						<div id="listeo-step-block-template" style="display:none;">
							<div class="editor-block block-width-4 block-{step_id}" data-step-id="{step_id}">
								<h5 class="step-display-title">{step_title}</h5>
								<div class="editor-block-tools">
									<ul>
										<li class="block-edit"><a href="#" class="button button-primary"></a></li>
										<li class="block-delete"><a href="#" class=""></a></li>
									</ul>
								</div>
								<div class="editor-block-form-fields" style="display:block;">
									<table class="form-table">
										<tr valign="top" class="field-edit-type">
											<th scope="row">
												<label for="field_meta_key">Step Title</label>
											</th>
											<td>
												<input type="text" class="step-title" placeholder="Step Title" value="{step_title}">

											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="field_meta_key">Select sections</label>
											</th>
											<td>
												<div class="step-selectors checkbox-group" data-step-id="{step_id}">
													<!-- checkboxes will be populated dynamically -->
												</div>

											</td>
										</tr>

									</table>
								</div>
							</div>
						</div>
					</div>

					<div class="form-editor-container" id="listeo-fafe-forms-editor" data-section="<?php echo esc_html('<div class="listeo-fafe-section">
	<h3>
		<input type="text" value="{section_org}" name="{section}[title]">
		<a href="#" class="listeo-fafe-section-edit button"></a>
		<a href="#" class="listeo-fafe-section-remove-section button"></a>
		<ul class="listeo-fafe-section-move">
    		<li><a class="listeo-fafe-section-move-up button" href="#"></a></li>
    		<li><a class="listeo-fafe-section-move-down button" href="#"></a></li>
    	</ul>
	</h3>
	<div class="section_options">
		<table class="form-table">
    		<tr>
    			<td>Custom class <span class="dashicons dashicons-editor-help" title="Option custom class for this section container"></span></td>
    			<td><input type="text" value="" name="{section}[class]"></td>
    		</tr>
    		<tr>
    			<td>Make it switchable <span class="dashicons dashicons-editor-help" title="If this is enabled, the section will be \'turned off\' with the swith button in right corner"></span></td>
    			<td>
    				<input name="{section}[onoff]" class="widefat" type="checkbox" value="" >
    			</td>
    		</tr>
    		<tr>
    			<td>Enabled by default <span class="dashicons dashicons-editor-help" title="If this is enabled, the section will be \'turned off\' with the swith button in right corner"></span></td>
    			<td>
    				<input name="{section}[onoff_state]" class="widefat" type="checkbox" value="" >
    			</td>
    		</tr>
    		<tr>
    			<td>Icon class <span class="dashicons dashicons-editor-help" title="Class used to display optional icon"></span></td>
    			<td><input type="text" value="" name="{section}[icon]"> Available icons list <a href="http://www.vasterad.com/themes/listeo_082019/pages-icons.html">listeo.pro/icons</a></td>

    		</tr>
    	</table>
	</div>
</div>
<div data-section="{section}" class="row-container row-{section}">
<div class="block-add-new"><a href="#" data-section="{section}" class="button primary">Add new field</a></div>
</div>') ?>">
						<?php
						$index = 0;

						foreach ($submit_fields as $field_key => $field) {

							$section  = (!empty($field_key)) ? $field_key : 'section';

							$title = isset($field['title']) ? $field['title'] : "Title";
							$is_preconfigured = isset($field['preconfigured']) && $field['preconfigured'];
							$preconfigured_class = $is_preconfigured ? ' preconfigured-section' : '';
						?>
							<div class="listeo-fafe-row-section<?php echo $preconfigured_class; ?>" data-selector=".<?php echo esc_attr($section); ?>">
								<div class="listeo-fafe-section">
									<h3>
										<input class="section-label" type="text" value="<?php echo stripslashes($title); ?>" name="<?php echo $section ?>[title]">
										<!-- <?php if ($is_preconfigured) : ?>
											<span class="preconfigured-notice" title="<?php esc_attr_e('This section is preconfigured based on listing type features', 'listeo-forms-fields-editor'); ?>"><span class="dashicons dashicons-lock"></span> </span>
										<?php endif; ?> -->
										<a href="#" class="listeo-fafe-section-edit button"></a>

										<?php if ($field_key != 'basic_info' && !$is_preconfigured) { ?><a href="#" class="listeo-fafe-section-remove-section button"></a><?php } ?>
										<ul class="listeo-fafe-section-move">
											<li><a class="listeo-fafe-section-move-up button" href="#"></a></li>
											<li><a class="listeo-fafe-section-move-down button" href="#"></a></li>
										</ul>
									</h3>
									<div class="section_options">
										<?php if ($is_preconfigured) : ?>
											<table class="form-table">
												<tr>
													<td><?php _e('Make it switchable', 'listeo-forms-fields-editor'); ?> <span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('If this is enabled, the section will be turned off with the switch button in right corner', 'listeo-forms-fields-editor'); ?>"></span></td>
													<td>
														<?php
														$value = (isset($field['onoff']) && !empty($field['onoff'])) ? true : false;
														?>
														<input name="<?php echo $section ?>[onoff]" <?php checked(1, $value) ?> class="widefat" type="checkbox" value="<?php echo $value; ?>">
													</td>
												</tr>
												<tr>
													<td><?php _e('Enabled by default', 'listeo-forms-fields-editor'); ?> <span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('If previous option is enabled this will make section enabled by default', 'listeo-forms-fields-editor'); ?>"></span></td>
													<td>
														<?php
														$value = (isset($field['onoff_state']) && !empty($field['onoff_state'])) ? true : false;
														?>
														<input name="<?php echo $section ?>[onoff_state]" <?php checked(1, $value) ?> class="widefat" type="checkbox" value="<?php echo $value; ?>">
													</td>
												</tr>
												<tr>
													<td><?php _e('Icon class', 'listeo-forms-fields-editor'); ?> <span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('Class used to display optional icon', 'listeo-forms-fields-editor'); ?>"></span></td>
													<td><input type="text" value="<?php echo isset($field['icon']) ? esc_attr($field['icon']) : ''; ?>" name="<?php echo $section ?>[icon]"> <?php _e('Available icons list', 'listeo-forms-fields-editor'); ?> <a href="http://www.vasterad.com/themes/listeo_082019/pages-icons.html">listeo.pro/icons</a></td>
												</tr>
											</table>
										<?php else : ?>
											<table class="form-table">
												<tr>
													<td>Custom class <span class="dashicons dashicons-editor-help" title="Option custom class for this section container"></span></td>
													<td><input type="text" value="<?php echo isset($field['class']) ? $field['class'] : "";; ?>" name="<?php echo $section ?>[class]"></td>
												</tr>
												<tr>
													<td>Make it switchable <span class="dashicons dashicons-editor-help" title="If this is enabled, the section will be \'turned off\' with the swith button in right corner"></span></td>
													<td>
														<?php
														$value = (isset($field['onoff']) && !empty($field['onoff'])) ? true : false;
														?>
														<input name="<?php echo $section ?>[onoff]" <?php checked(1, $value) ?> class="widefat" type="checkbox" value="<?php echo $value; ?>">
													</td>
												</tr>
												<tr>
													<td>Enabled by default <span class="dashicons dashicons-editor-help" title="If previous option is enabled this will make section enabled by default"></span></td>
													<td>
														<?php
														$value = (isset($field['onoff_state']) && !empty($field['onoff_state'])) ? true : false;
														?>
														<input name="<?php echo $section ?>[onoff_state]" <?php checked(1, $value) ?> class="widefat" type="checkbox" value="<?php echo $value; ?>">
													</td>
												</tr>
												<tr>
													<td>Icon class <span class="dashicons dashicons-editor-help" title="Class used to display optional icon"></span></td>
													<td><input type="text" value="<?php echo isset($field['icon']) ? $field['icon'] : "";; ?>" name="<?php echo $section ?>[icon]"> Available icons list <a href="http://www.vasterad.com/themes/listeo_082019/pages-icons.html">listeo.pro/icons</a></td>
												</tr>
											</table>
										<?php endif; ?>
									</div>

								</div>



								<div data-section="<?php echo esc_attr($section); ?>" class="row-container row-<?php echo esc_attr($section); ?><?php if (isset($field['title'])) {
																																					sanitize_title($field['title']);
																																				} ?>">

									<?php
									foreach ($field['fields'] as $key => $field) {

										if (in_array($key, array('_monday_opening_hour', '_monday_closing_hour', '_tuesday_opening_hour', '_tuesday_closing_hour', '_wednesday_opening_hour', '_wednesday_closing_hour', '_thursday_opening_hour', '_thursday_closing_hour', '_friday_opening_hour', '_friday_closing_hour', '_saturday_opening_hour', '_saturday_closing_hour', '_sunday_opening_hour', '_sunday_closing_hour'))) {
											continue;
										}

										if (!array_key_exists('class', $field)) {
											$field['class'] = '';
										}

										$width = $this->get_box_width($field);
										$label = (isset($field['label'])) ? $field['label'] : 'Missing Label';
										//	var_dump($field);
									?>

										<div class='editor-block block-width-<?php echo $width; ?> block-<?php echo sanitize_title($key) ?><?php echo $is_preconfigured ? ' preconfigured-field' : ''; ?>'>
											<h5><?php echo stripslashes($label); ?></h5>
											<div class="editor-block-tools">
												<input type="text" class="block-width-input" name="<?php echo $section; ?>[<?php echo $key; ?>][render_row_col]" value="<?php echo $width; ?>" <?php echo $is_preconfigured ? 'readonly' : ''; ?>>
												<input type="hidden" name="section[]" value="<?php echo $section; ?>">
												<input type="hidden" name="field[]" value="<?php echo $key; ?>">

												<ul>
													<?php if ($is_preconfigured) : ?>
														<li class="preconfigured-notice-small" title="Field structure is preconfigured"><span class="dashicons dashicons-lock"></span></li>
													<?php else : ?>
														<li class="block-edit"><a data-section="<?php echo $section; ?>" data-id="<?php echo $key; ?>" href="#" class="button button-primary"></a></li>
														<li class="block-narrower"><a href="#"></a></li>
														<li class="block-wider"><a href="#"></a></li>
														<?php if (!in_array($key, array('_geolocation_long', '_geolocation_lat'))) { ?>
															<li class="block-delete"><a href="#"></a></li>
														<?php } ?>
													<?php endif; ?>
												</ul>

											</div>
											<div class="editor-block-form-fields">
												<?php

												//'tooltip'	  => '',
												if (!empty($section) && !empty($field)) {
													$this->init_fields();
													//$options = get_option("listeo_submit_form_fields");
													$submit_fields = (!empty($saved_fields)) ? $saved_fields : $this->fields;

													$field_data = isset($submit_fields[$section]['fields'][$key]) ? $submit_fields[$section]['fields'][$key] : $field;
													$form = $this->generate_form_fields($field_data, $key, $section, $active_tab_key);
													echo $form;
												}
												?>
											</div>
										</div>

									<?php } ?>
									<?php if (!$is_preconfigured) : ?>
										<div class="block-add-new"><a href="#" data-section="<?php echo esc_attr($section); ?>" class="button primary">Add new field</a></div>
									<?php endif; ?>
								</div>
							</div>

						<?php }  ?>
						<div class="droppable-helper"></div>

					</div>
					<div>
						<a href="#" class="listeo-fafe-new-section button-secondary"><?php _e('Add new section', 'listeo'); ?></a>
					</div>
					<div class="buttons-wrapper">
						<input type="hidden" id="listeo_form_steps_json" name="listeo_form_steps_json" />
						<input type="submit" name="save_changes" class="save-fields button-primary" value="<?php _e('Save Changes', 'listeo'); ?>" />

						<a href="<?php echo wp_nonce_url(add_query_arg('reset-fields', 1), 'reset'); ?>" class="reset button-secondary"><?php _e('Reset to defaults', 'listeo'); ?></a>
					</div>
					<?php wp_nonce_field('save-fields'); ?>
					<?php wp_nonce_field('save'); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Save the form fields
	 */
	private function form_editor_save($tab)
	{

		if (isset($_POST['save_changes'])) {

			$options = get_option('listeo_submit_form_fields_temp', array());

			$new_fields = array();
			$booking_section_keys = array('booking', 'slots', 'menu', 'availability_calendar', 'opening_hours', 'event');
			$booking_overrides = array();
			$section_order = array();

			$field_name      = !empty($_POST['field']) ? array_map('sanitize_text_field', $_POST['field'])   : array();
			$field_section   = !empty($_POST['section']) ? array_map('sanitize_text_field', $_POST['section'])   : array();
			$field_width 	 = !empty($_POST['render_row_col']) ? array_map('sanitize_text_field', $_POST['render_row_col'])                  : array();
			$field_type 	 = !empty($_POST['type']) ? array_map('sanitize_text_field', $_POST['type']) : array();

			$sections = array_unique($field_section);

			foreach ($sections as $key => $section) {
				// Track section order for all sections
				$section_order[] = $section;

				if (isset($_POST[$section])) {

					// For booking sections: collect overrides only, skip field processing
					if (in_array($section, $booking_section_keys)) {
						$booking_overrides[$section] = array(
							'title'       => isset($_POST[$section]['title']) ? sanitize_text_field(stripslashes_deep($_POST[$section]['title'])) : '',
							'class'       => isset($_POST[$section]['class']) ? sanitize_text_field($_POST[$section]['class']) : '',
							'onoff'       => isset($_POST[$section]['onoff']) ? true : false,
							'onoff_state' => isset($_POST[$section]['onoff_state']) ? 'on' : false,
							'icon'        => isset($_POST[$section]['icon']) ? sanitize_text_field($_POST[$section]['icon']) : '',
						);
						continue; // Don't add to $new_fields
					}

					$section_title = isset($_POST[$section]['title']) ? sanitize_text_field(stripslashes_deep($_POST[$section]['title'])) : '';

					$new_fields[$section]['title'] = $section_title;

					$section_class = isset($_POST[$section]['class']) ? sanitize_text_field($_POST[$section]['class']) : '';
					$new_fields[$section]['class'] = $section_class;

					$section_onoff = isset($_POST[$section]['onoff']) ? true : false;
					$new_fields[$section]['onoff'] = $section_onoff;

					$section_onoff_state = isset($_POST[$section]['onoff_state']) ? 'on' : false;
					$new_fields[$section]['onoff_state'] = $section_onoff_state;

					$section_icon = isset($_POST[$section]['icon']) ? sanitize_text_field($_POST[$section]['icon']) : '';
					$new_fields[$section]['icon'] = $section_icon;


					foreach ($_POST[$section] as $key => $value) {
						if (in_array($key, array('title', 'class', 'onoff', 'onoff_state', 'icon'))) {
							continue;
						};
						$value = $this->sanitize_array($value);

						$new_fields[$section]['fields'][$key] = $value;
						if (!isset($value['required'])) {
							$new_fields[$section]['fields'][$key]['required'] = 0;
						}
						if (isset($value['type']) && $value['type'] == "term-select" && !isset($value['multi'])) {
							$new_fields[$section]['fields'][$key]['multi'] = 0;
						}
					}
				}
			}

			// Save booking section overrides and section ordering
			update_option("listeo_booking_section_overrides_{$tab}", $booking_overrides);
			update_option("listeo_section_order_{$tab}", $section_order);

			$result = update_option("listeo_submit_{$tab}_form_fields", $new_fields);
			if (isset($_POST['listeo_form_steps_json'])) {
				$steps = json_decode(stripslashes($_POST['listeo_form_steps_json']), true);
				update_option("listeo_submit_{$tab}form_steps", $steps);
			}
			// save steps status option
			$steps_status = isset($_POST['listeo_enable_' . $tab . 'form_steps']) ? 'on' : 'off';
			update_option("listeo_enable_{$tab}form_steps", $steps_status);


			return '<div class="updated"><p>' . __('The fields were successfully saved.', 'listeo-fafe') . '</p></div>';
		}

		return '';
	}

	public function get_box_width($field)
	{

		if (isset($field['render_row_col'])) {
			return $field['render_row_col'];
		} else {
			return '12';
		}
	}

	/**
	 * Sanitize a 2d array
	 * @param  array $array
	 * @return array
	 */
	private function sanitize_array($input)
	{
		if (is_array($input)) {
			foreach ($input as $k => $v) {
				$input[$k] = $this->sanitize_array($v);
			}
			return $input;
		} else {
			return sanitize_text_field($input);
		}
	}

	public function editor_load_field()
	{

		$ajax_out = false;

		$field =  $_POST['field'];
		$section =  $_POST['section'];
		if (!empty($section) && !empty($field)) {
			$this->init_fields();
			$options = get_option("listeo_submit_form_fields");
			$submit_fields = (!empty($options)) ? get_option("listeo_submit_form_fields") : $this->fields;

			$form = $this->generate_form_fields($submit_fields[$section]['fields'][$field], $field, $section);

			$ajax_out = $form;
		}

		wp_send_json_success($ajax_out);
	}

	function editor_get_items()
	{
		$section = $_POST['section'];
		$tab = $_POST['tab'];
		$currency = get_option('listeo_currency');

		// Resource tabs: show resource-specific default fields (booking/pricing) plus
		// any custom fields defined in the Fields Builder for this resource type.
		$tab_slug = str_replace('_tab', '', $tab);
		if ( strpos( $tab_slug, 'resource_' ) === 0 ) {
			$visual_fields = array();

			// Default resource form sections (basic info, gallery, booking prices, etc.)
			// provided by Booking Plus via listeo_submit_resource_default_fields.
			$default_sections = apply_filters( 'listeo_submit_resource_default_fields', array(), $tab_slug );
			if ( ! empty( $default_sections ) && is_array( $default_sections ) ) {
				foreach ( $default_sections as $section_key => $section_def ) {
					if ( empty( $section_def['fields'] ) || ! is_array( $section_def['fields'] ) ) {
						continue;
					}
					foreach ( $section_def['fields'] as $field_key => $field_def ) {
						if ( ! isset( $field_def['type'] ) || $field_def['type'] === 'skipped' ) {
							continue;
						}
						$field_name = isset( $field_def['name'] ) ? $field_def['name'] : $field_key;
						if ( isset( $visual_fields[ $field_name ] ) ) {
							continue;
						}
						$visual_fields[ $field_name ] = array_merge(
							array(
								'label'          => isset( $field_def['label'] ) ? $field_def['label'] : $field_name,
								'type'           => $field_def['type'],
								'name'           => $field_name,
								'placeholder'    => isset( $field_def['placeholder'] ) ? $field_def['placeholder'] : '',
								'tooltip'        => isset( $field_def['tooltip'] ) ? $field_def['tooltip'] : '',
								'priority'       => 10,
								'default'        => isset( $field_def['default'] ) ? $field_def['default'] : '',
								'render_row_col' => isset( $field_def['render_row_col'] ) ? $field_def['render_row_col'] : '12',
								'multi'          => false,
								'required'       => ! empty( $field_def['required'] ),
							),
							isset( $field_def['options'] ) ? array( 'options' => $field_def['options'] ) : array()
						);
					}
				}
			}

			// Custom fields defined in Fields Builder for this resource type.
			$resource_fields = get_option( "listeo_{$tab_slug}_tab_fields", array() );
			if ( ! empty( $resource_fields ) && is_array( $resource_fields ) ) {
				foreach ( $resource_fields as $field_id => $field_def ) {
					if ( ! isset( $field_def['type'] ) || $field_def['type'] === 'headline' ) {
						continue;
					}
					if ( isset( $visual_fields[ $field_id ] ) ) {
						continue;
					}
					$entry = array(
						'label'          => isset( $field_def['name'] ) ? $field_def['name'] : $field_id,
						'type'           => $field_def['type'],
						'name'           => $field_id,
						'placeholder'    => isset( $field_def['name'] ) ? $field_def['name'] : '',
						'tooltip'        => isset( $field_def['desc'] ) ? $field_def['desc'] : '',
						'priority'       => 10,
						'default'        => '',
						'render_row_col' => '4',
						'multi'          => false,
						'required'       => ! empty( $field_def['required'] ),
					);
					if ( in_array( $field_def['type'], array( 'select', 'multicheck_split', 'multicheck', 'select_multiple' ), true ) && ! empty( $field_def['options'] ) ) {
						$entry['options'] = $field_def['options'];
					}
					$visual_fields[ $field_id ] = $entry;
				}
			}

			// Parent listing-type Fields Builder fields — e.g. resource_rental
			// inherits whatever was defined for `rental` so admins don't have
			// to redefine the same field twice. Labels are suffixed with the
			// listing-type name so they're distinguishable from resource-only
			// fields in the picker.
			$parent_slug = preg_replace( '/^resource_/', '', $tab_slug );
			if ( $parent_slug && $parent_slug !== $tab_slug ) {
				$parent_fields = get_option( "listeo_{$parent_slug}_tab_fields", array() );
				if ( ! empty( $parent_fields ) && is_array( $parent_fields ) ) {
					$parent_label = ucfirst( $parent_slug );
					if ( function_exists( 'listeo_core_custom_listing_types' ) ) {
						$mgr = listeo_core_custom_listing_types();
						if ( $mgr && method_exists( $mgr, 'get_listing_type_by_slug' ) ) {
							$lt = $mgr->get_listing_type_by_slug( $parent_slug );
							if ( $lt && ! empty( $lt->name ) ) {
								$parent_label = $lt->name;
							}
						}
					}
					foreach ( $parent_fields as $field_id => $field_def ) {
						if ( ! isset( $field_def['type'] ) || $field_def['type'] === 'headline' ) {
							continue;
						}
						if ( isset( $visual_fields[ $field_id ] ) ) {
							continue; // Resource-level definition wins.
						}
						$display_name = isset( $field_def['name'] ) ? $field_def['name'] : $field_id;
						$entry = array(
							'label'          => sprintf(
								/* translators: 1: field name, 2: parent listing-type name (e.g. "Rental") */
								__( '%1$s (from %2$s)', 'listeo-fafe' ),
								$display_name,
								$parent_label
							),
							'type'           => $field_def['type'],
							'name'           => $field_id,
							'placeholder'    => $display_name,
							'tooltip'        => isset( $field_def['desc'] ) ? $field_def['desc'] : '',
							'priority'       => 10,
							'default'        => '',
							'render_row_col' => '4',
							'multi'          => false,
							'required'       => ! empty( $field_def['required'] ),
						);
						if ( in_array( $field_def['type'], array( 'select', 'multicheck_split', 'multicheck', 'select_multiple' ), true ) && ! empty( $field_def['options'] ) ) {
							$entry['options'] = $field_def['options'];
						}
						$visual_fields[ $field_id ] = $entry;
					}
				}
			}

			// Taxonomies registered for `lbp_resource` (e.g. `listing_feature`
			// after Booking Plus shares Core's amenities taxonomy with
			// resources). Iterating `get_object_taxonomies` keeps this future-
			// proof — any plugin that later attaches a taxonomy to
			// `lbp_resource` shows up here automatically.
			$resource_taxonomies = get_object_taxonomies( 'lbp_resource', 'objects' );
			foreach ( $resource_taxonomies as $tax_name => $tax_obj ) {
				$field_key = 'tax-' . $tax_name;
				if ( isset( $visual_fields[ $field_key ] ) ) {
					continue;
				}
				$visual_fields[ $field_key ] = array(
					'label'          => $tax_obj->label,
					'type'           => 'term-select',
					'placeholder'    => $tax_obj->label,
					'name'           => $field_key,
					'taxonomy'       => $tax_name,
					'tooltip'        => '',
					'priority'       => 10,
					'default'        => '',
					'render_row_col' => '4',
					'multi'          => false,
					'required'       => false,
				);
			}

			/**
			 * Same extension hook as the listing-tab path so plugins can
			 * append resource-specific picks (e.g. Booking Plus's resource
			 * label override fields if those ever apply here).
			 */
			$visual_fields = apply_filters( 'listeo_submit_editor_available_fields', $visual_fields, $section, $tab_slug );

			$form = $this->generate_new_form_fields( $visual_fields, $section );
			wp_send_json_success( array( 'items' => $form ) );
		}

		$visual_fields = array(
			'listing_title' => array(
				'label'       => __('Listing Title', 'listeo_core'),
				'type'        => 'text',
				'name'       => 'listing_title',
				'tooltip'	  => __('Type title that also contains a unique feature of your listing (e.g. renovated, air conditioned)', 'listeo_core'),
				'required'    => true,
				'placeholder' => '',
				'class'		  => '',
				'priority'    => 1,

			),
			'_listing_logo' => array(
				'label'       => __('Listing Logo', 'listeo_core'),
				'type'        => 'file',
				'name'       => '_listing_logo',
				'tooltip'	  => __('Upload optional logo for listing', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'class'		  => '',
				'priority'    => 1,


			),
			'keywords' => array(
				'label'       => __('Keywords', 'listeo_core'),
				'type'        => 'text',
				'tooltip'	  => __('Maximum of 15 keywords related to your business, separated by commas', 'listeo_core'),
				'placeholder' => '',
				'name'        => 'keywords',
				'after_row'   => '</div>',
				'priority'    => 10,
				'before_row'  => '',
				'default'	  => '',
				'render_row_col' => '4',
				'required'    => false,
			),
			'listing_description' => array(
				'label'       => __('Description', 'listeo_core'),
				'name'       => 'listing_description',
				'type'        => 'wp-editor',
				'description' => __('By selecting (clicking on a photo) one of the uploaded photos you will set it as Featured Image for this listing (marked by icon with star). Drag and drop thumbnails to re-order images in gallery.', 'listeo_core'),
				'placeholder' => 'Upload images',
				'class'		  => '',
				'priority'    => 1,
				'required'    => true,
			),
			'_booking_status' => array(
				'label'       => __('Booking status', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_booking_status',
				//				'for_type'	  => ''

			),

			'_booking_link' => array(
				'label'       => __('External Booking link', 'listeo_core'),
				'tooltip'	  => __('Add Link to 3rd party site where the booking is made', 'listeo_core'),
				'type'        => 'text',
				'default'     => '',
				'required'    => false,
				'name'        => '_booking_link',
				'placeholder' => '',
				'class'		  => '',

				'render_row_col' => '6',
				//				'for_type'	  => ''
			),
			'_opening_hours_status' => array(
				'label'       => __('Opening Hours status', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_opening_hours_status',
			),
			'_opening_hours' => array(
				'label'       => __('Opening Hours', 'listeo_core'),
				'name'       => '_opening_hours',
				'type'        => 'hours',
				'placeholder' => '',
				'class'		  => '',
				'priority'    => 1,
				'required'    => false,
			),

			'_listing_timezone' => array(
				'label'       => __('Listing Timezone', 'listeo_core'),
				'type'        => 'timezone',
				'required'    => false,
				'name'        => '_listing_timezone',
				//				'for_type'	  => ''
			),
			'_slots_status' => array(
				'label'       => __('Booking status', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_slots_status',
				//				'for_type'	  => ''
			),
			'_slots' => array(
				'label'       => __('Time Slots', 'listeo_core'),
				'name'       => '_slots',
				'type'        => 'slots',
				'placeholder' => '',
				'class'		  => '',
				'priority'    => 1,
				'required'    => false,
				//				'for_type'	  => ''
			),

			'_availability' => array(
				'label'       => __('Click day in calendar to mark it as unavailable', 'listeo_core'),

				'name'       => '_availability',
				'type'        => 'calendar',
				'placeholder' => '',
				'class'		  => '',
				'priority'    => 1,
				'required'    => false,
				//				'for_type'	  => ''
			),
			//nowe
			'_gallery' => array(
				'label'       => __('Gallery', 'listeo_core'),
				'name'       => '_gallery',
				'type'        => 'files',
				'description' => __('By selecting (clicking on a photo) one of the uploaded photos you will set it as Featured Image for this listing (marked by icon with star). Drag and drop thumbnails to re-order images in gallery.', 'listeo_core'),
				'placeholder' => 'Upload images',
				'class'		  => '',
				'priority'    => 1,
				'required'    => false,
				//				'for_type'	  => ''
			),
			'_menu_status' => array(
				'label'       => __('Menu status', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_menu_status',
				//				'for_type'	  => ''
			),
			'_menu' => array(
				'label'       => __('Pricing', 'listeo_core'),
				'name'       => '_menu',
				'type'        => 'pricing',
				'placeholder' => '',
				'class'		  => '',
				'priority'    => 1,
				'required'    => false,
				//				'for_type'	  => ''
			),
			'_slots_status' => array(
				'label'       => __('Slots status', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_slots_status',
				//				'for_type'	  => ''
			),
			'_slots' => array(
				'label'       => __('Availability Calendar', 'listeo_core'),
				'name'       => '_slots',
				'type'        => 'slots',
				'placeholder' => '',
				'class'		  => '',
				'priority'    => 1,
				'required'    => false,
				//				'for_type'	  => ''
			),
			'_event_tickets' => array(
				'label'       => __('Available Tickets', 'listeo_core'),
				'tooltip'	  => __('How many ticekts you have to offer', 'listeo_core'),
				'type'        => 'number',
				'required'    => false,
				'name'        => '_event_tickets',
				'class'		  => '',
				'placeholder' => '',
				'priority'    => 9,
				'render_row_col' => '6',
				//				'for_type'	  => ''
			),

			'_max_tickets_per_booking' => array(
				'label'       => __('Max Tickets Per Booking', 'listeo_core'),
				'tooltip'	  => __('Maximum number of tickets a user can book at once (leave empty for no limit)', 'listeo_core'),
				'type'        => 'number',
				'required'    => false,
				'name'        => '_max_tickets_per_booking',
				'class'		  => '',
				'placeholder' => '',
				'priority'    => 9.5,
				'render_row_col' => '6',
				//				'for_type'	  => ''
			),

			'_normal_price' => array(
				'label'       => __('Regular Price', 'listeo_core'),
				'type'        => 'number',
				'tooltip'	  => __('Default price for booking on Monday - Friday', 'listeo_core'),
				'required'    => false,
				'default'           => '0',
				'placeholder' => '',
				'unit'		  => $currency,
				'name'        => '_normal_price',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '6',
				//				'for_type'	  => ''

			),

			'_weekday_price' => array(
				'label'       => __('Weekend Price', 'listeo_core'),
				'type'        => 'number',
				'required'    => false,
				'tooltip'	  => __('Default price for booking on weekend', 'listeo_core'),
				'placeholder' => '',
				'name'        => '_weekday_price',
				'unit'		  => $currency,
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '6',
				//				'for_type'	  => ''
			),
			'_reservation_price' => array(
				'label'       => __('Reservation Fee', 'listeo_core'),
				'type'        => 'number',
				'required'    => false,
				'name'        => '_reservation_price',
				'tooltip'	  => __('One time fee for booking', 'listeo_core'),
				'placeholder' => '',
				'unit'		  => $currency,
				'default'           => '0',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '6',
				//				'for_type'	  => ''

			),
			'_expired_after' => array(
				'label'       => __('Reservation expires after', 'listeo_core'),
				'tooltip'	  => __('How many hours you can wait for clients payment', 'listeo_core'),
				'type'        => 'number',
				'default'     => '48',
				'required'    => false,
				'name'        => '_expired_after',
				'placeholder' => '',
				'class'		  => '',
				'unit'		  => 'hours',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '6',
				//				'for_type'	  => ''
			),
			'_zoom_booking_enabled' => array(
				'label'       => __('Enable Zoom meetings for bookings', 'listeo_core'),
				'type'        => 'checkbox',
				'name'        => '_zoom_booking_enabled',
				'priority'    => 6,
				'default'     => '',
				'render_row_col' => '3',
				'placeholder' => '',
				'value'    	  => 'on',
				'description' => __('Automatically create Zoom meetings when bookings are confirmed. You must connect your Zoom account in your profile first.', 'listeo_core'),
			),
			'_instant_booking' => array(
				'label'       => __('Enable Instant Booking', 'listeo_core'),
				'type'        => 'checkbox',
				'tooltip'	  => __('With this option enabled booking request will be immediately approved ', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_instant_booking',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'value'    	  => 'on',
				'render_row_col' => '6',
				//				'for_type'	  => ''
			),
			'_mandatory_fees' => array(
				'label'       => __('Mandatory Fees', 'listeo_core'),
				'type'        => 'repeatable',
				'tooltip'	  => __('Set mandatory fees that will always be added to total cost', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_mandatory_fees',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '6',
				'options'   => array(
					'title' => __('Title', 'listeo_core'),
					'price' => __('Price', 'listeo_core'),
				),
			),
			'_payment_option' => array(
				'label'       => __('Payment options', 'listeo_core'),
				'type'        => 'select',
				'tooltip'	  => __('Select which payment type you require for a booking', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_payment_option',
				'class'		  => '',
				'priority'    => 10,
				'render_row_col' => '3',
				'options'   => array(
					'pay_now' => __('Require online payment', 'listeo_core'),
					'pay_maybe' => __('Allow online payment', 'listeo_core'),
					'pay_cash' => __('Require only cash payment', 'listeo_core'),

				),
			),
			'_max_guests' => array(
				'label'       => __('Maximum number of guests', 'listeo_core'),
				'type'        => 'number',
				'tooltip'	  => __('Set maximum number of guests per reservation', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_max_guests',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3',
				//				'for_type'	  => ''
			),
			'_min_guests' => array(
				'label'       => __('Minimum number of guests', 'listeo_core'),
				'type'        => 'number',
				'tooltip'	  => __('Set minimum number of guests per reservation', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_min_guests',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3',
				//				'for_type'	  => ''
			),
			'_min_days' => array(
				'label'       => __('Minimum stay (in days)', 'listeo_core'),
				'type'        => 'number',
				'tooltip'	  => __('Set minimum number of days to book', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_min_days',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3',
				//				'for_type'	  => ''
			),
			'_count_per_guest' => array(
				'label'       => __('Enable Price per Guest', 'listeo_core'),
				'type'        => 'checkbox',
				'tooltip'	  => __('With this option enabled regular price and weekend price will be multiplied by number of guests to estimate total cost', 'listeo_core'),
				'required'    => false,

				'placeholder' => '',
				'name'        => '_count_per_guest',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3',
				//				'for_type'	  => ''
			),
			'_children' => array(
				'label'       => __('Enable Children', 'listeo_core'),
				'type'        => 'checkbox',
				'tooltip'	  => __('With this option enabled you can set price for children', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_children',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3'
			),
			'_max_children' => array(
				'label'       => __('Maximum number of children', 'listeo_core'),
				'type'        => 'number',
				'tooltip'	  => __('Set maximum number of children per reservation', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_max_children',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3'
			),
			'_children_price' => array(
				'label'       => __('Children Price', 'listeo_core'),
				'type'        => 'number',
				'tooltip'	  => __('Enter percentage discount (e.g. 50 for 50% off). Leave empty for no discount', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_children_price',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3'
			),
			//enable animals
			'_animals' => array(
				'label'       => __('Enable Animals', 'listeo_core'),
				'type'        => 'checkbox',
				'tooltip'	  => __('With this option enabled you can set price for animals', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_animals',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3'
			),
			'_animal_fee_type' => array(
				'label'       => __('Animal Fee Type', 'listeo_core'),
				'type'        => 'select',
				'tooltip'	  => __('Select how you want to charge for animals', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_animal_fee_type',
				'class'		  => '',
				'priority'    => 10,
				'render_row_col' => '3',
				'options'          => array(
					'none'     => __('No pet fee', 'listeo_core'),
					'one_time' => __('One-time fee per pet', 'listeo_core'),
					'per_night' => __('Per night fee per pet', 'listeo_core'),
				),
			),
			'_animal_fee' => array(
				'label'       => __('Animal Fee', 'listeo_core'),
				'type'        => 'number',
				'tooltip'	  => __('Set price for animals', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_animal_fee',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3'
			),
			'_rental_timepicker' => array(
				'label'       => __('Enable Rental Time Picker', 'listeo_core'),
				'type'        => 'checkbox',
				'tooltip'	  => __('If you are not using slots, you can allow guest to set end time for booking by enabling that option ', 'listeo_core'),
				'required'    => false,

				'placeholder' => '',
				'name'        => '_rental_timepicker',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3',
				'for_type'	  => 'rental'
			),
			'_time_increment' => array(
				'label'       => __('Time Increment for time picker', 'listeo_core'),
				'type'        => 'select',
				'tooltip'	  => __('Set time increment for time picker', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_time_increment',
				'class'		  => '',
				'priority'    => 10,
				'render_row_col' => '3',
				'options'   => array(
					'5' => __('5 minutes', 'listeo_core'),
					'10' => __('10 minutes', 'listeo_core'),
					'15' => __('15 minutes', 'listeo_core'),
					'30' => __('30 minutes', 'listeo_core'),
					'60' => __('1 hour', 'listeo_core'),
				),
			),
			'_count_by_hour' => array(
				'label'       => __('Enable Price per Hour', 'listeo_core'),
				'type'        => 'checkbox',
				'tooltip'	  => __('With this option enabled regular price and weekend price will be multiplied by hours booked, requires End Hour field to be ON', 'listeo_core'),
				'required'    => false,

				'placeholder' => '',
				'name'        => '_count_by_hour',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3',
				//				'for_type'	  => ''
			),
			'_email_message' => array(
				'label'       => __('Informations for Email Message', 'listeo_core'),
				'type'        => 'textarea',
				'tooltip'	  => __('Informations you add below can be send via email to user after booking, you can put here details like providing key, pin code, exact address etc.', 'listeo_core'),
				'required'    => false,
				'placeholder' => '',
				'name'        => '_email_message',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '6'
			),
			'_end_hour' => array(
				'label'       => __('Enable End Hour time-picker', 'listeo_core'),
				'type'        => 'checkbox',
				'tooltip'	  => __('If you are not using slots, you can allow guest to set end time for booking by enabling that option ', 'listeo_core'),
				'required'    => false,

				'placeholder' => '',
				'name'        => '_end_hour',
				'class'		  => '',
				'priority'    => 10,
				'priority'    => 9,
				'render_row_col' => '3',
				//				'for_type'	  => 'service'
			),


		);


		$price_fields = Listeo_Core_Meta_Boxes::meta_boxes_prices();
		$meta_fields = array(
			Listeo_Core_Meta_Boxes::meta_boxes_prices(),
			Listeo_Core_Meta_Boxes::meta_boxes_location(),
			Listeo_Core_Meta_Boxes::meta_boxes_contact(),
			Listeo_Core_Meta_Boxes::meta_boxes_event(),
			Listeo_Core_Meta_Boxes::meta_boxes_service(),
			Listeo_Core_Meta_Boxes::meta_boxes_rental(),
			Listeo_Core_Meta_Boxes::meta_boxes_classifieds(),
			Listeo_Core_Meta_Boxes::meta_boxes_video(),
			Listeo_Core_Meta_Boxes::meta_boxes_custom(),
		);


		// Add custom listing type fields from options
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$custom_types = $custom_types_manager->get_listing_types(true); // true = only active types

			foreach ($custom_types as $type_slug => $type_obj) {
				// Skip built-in types as they're already included above
				if (in_array($type_obj->slug, ['service', 'rental', 'event', 'classifieds'])) {
					continue;
				}

				// Get custom fields for this listing type from options
				$option_name = "listeo_{$type_obj->slug}_tab_fields";
				$custom_fields = get_option($option_name, array());

				if (!empty($custom_fields) && is_array($custom_fields)) {
					// Format the fields array to match the expected structure
					$formatted_fields = array(
						'id' => "listeo_{$type_obj->slug}_fields",
						'title' => ucfirst($type_obj->slug) . ' Fields',
						'fields' => $custom_fields
					);

					$meta_fields[] = $formatted_fields;
				}
			}
		}

		foreach ($meta_fields as $key) {
			foreach ($key['fields'] as $key => $field) {

				if (in_array($field['type'], array('select', 'repeatable', 'group', 'select_multiple', 'multicheck_split', 'multicheck'))) {
					$visual_fields[] = array(
						'label'       => $field['name'],
						'type'        => $field['type'],
						'placeholder' => $field['name'],
						'name'        => $field['id'],
						'tooltip'	  => '',
						'priority'    => 10,
						'default'	  => '',
						'render_row_col' => '4',
						'multi'    	  => false,
						'required'    => false,
						'options' => $field['options'],
						//						'for_type'	  => ''
					);
				} else {
					$visual_fields[] = array(
						'label'       => $field['name'],
						'type'        => $field['type'],
						'placeholder' => $field['name'],
						'name'        => $field['id'],
						'tooltip'	  => '',
						'priority'    => 10,
						'default'	  => '',
						'render_row_col' => '4',
						'multi'    	  => false,
						'required'    => false,
						//						'for_type'	  => ''
					);
				}
			}
		}


		// Get all taxonomies registered for 'listing' post type
		$taxonomy_objects = get_object_taxonomies('listing', 'objects');

		// Build list of listing type taxonomies dynamically from custom listing types
		$listing_type_taxonomies = array();
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$listing_types = $custom_types_manager->get_listing_types();

			foreach ($listing_types as $type) {
				// Add taxonomy name for this listing type (e.g., 'service_category')
				$listing_type_taxonomies[] = $type->slug . '_category';
			}
		}

		// remove _tab from end of $tab
		$tab = str_replace('_tab', '', $tab);
		// Determine which listing type taxonomy to include based on current tab
		// If $tab is not set or empty, set to null to exclude all listing type taxonomies
		$current_type_taxonomy = (!empty($tab)) ? $tab . '_category' : null;

		// Add all taxonomies except listing type taxonomies (unless it matches the current tab)
		foreach ($taxonomy_objects as $tax_name => $tax) {
			// Check if this is a listing type taxonomy
			if (in_array($tax_name, $listing_type_taxonomies)) {
				// Only include if it matches the current tab's taxonomy
				if ($tax_name !== $current_type_taxonomy) {
					continue; // Skip this taxonomy
				}
			}

			// Add the taxonomy to visual fields
			$visual_fields[] = array(
				'label'       => $tax->label,
				'type'        => 'term-select',
				'placeholder' => $tax->label,
				'name'        => 'tax-' . $tax->name,
				'taxonomy'	  => $tax->name,
				'tooltip'	  => '',
				'priority'    => 10,
				'default'	  => '',
				'render_row_col' => '4',
				'multi'    	  => false,
				'required'    => false,
			);
		}

		/**
		 * Plugins can append entries to the "Add new field" modal here.
		 * Each entry is an array shaped like the others above:
		 *   array(
		 *       'label'          => 'Display label in the modal',
		 *       'type'           => 'text',  // any registered field type
		 *       'name'           => 'meta_key_for_post_meta',
		 *       'tooltip'        => '',
		 *       'placeholder'    => '',
		 *       'priority'       => 10,
		 *       'render_row_col' => '6',
		 *       'required'       => false,
		 *       // optional 'default', 'options', etc.
		 *   )
		 *
		 * `$section` is the section the user is adding the field into;
		 * `$tab` is the listing-type tab (e.g. 'service_tab'). Use these
		 * to scope which fields show up.
		 */
		$visual_fields = apply_filters( 'listeo_submit_editor_available_fields', $visual_fields, $section, $tab );

		$form = $this->generate_new_form_fields($visual_fields, $section);



		$response = array(
			'items'	=> $form
		);

		wp_send_json_success($response);
	}

	function editor_save_field()
	{


		$field =  $_POST['field'];
		$section =  $_POST['section'];
		$fields = json_decode(wp_unslash($_POST['fields']));
		$field_name = '';
		$field_section = '';
		$option = get_option('listeo_submit_form_fields_temp', array());

		foreach ($fields as $key => $value) {

			if ($value->name == 'field') {
				$field_name = $value->value;
			} else if ($value->name == 'section') {
				$field_section = $value->value;
			} else {
				$option[$field_section]['fields'][$field_name][$value->name] = $value->value;
			}
		}

		update_option('listeo_submit_form_fields_temp', $option);

		$response = array(
			'message' => 'Saved'
		);

		wp_send_json_success($response);
	}

	// function editor_delete_field(){

	// 	$field =  $_POST['field'];
	// 	$section =  $_POST['section'];
	// 	$fields_to_delete = get_option('listeo_submit_form_fields_delete_temp',awrray());
	// 	$fields_to_delete[$section] = $field;

	// 	update_option('listeo_submit_form_fields_delete_temp',$fields_to_delete);

	// 	$response = array(
	//      'message' => 'Removed'
	//  );

	// 	wp_send_json_success(  $response );
	// }

	/**
	 * Return the list of options for the field-type dropdown, plus a filter
	 * hook so plugins (LBP, AI Chat Search, etc.) can register their custom
	 * field types instead of having them silently degrade to "WYSIWYG Editor"
	 * (the first option) when the type isn't in the hardcoded list.
	 *
	 * If `$current` is given and isn't in the list, it's appended as a
	 * "Custom (X)" option so the saved value stays selected — saving the
	 * field then preserves the type instead of overwriting it.
	 *
	 * @param string $current Currently saved type value (kept selected).
	 * @return array<string,string> map of type_value => human label.
	 */
	public function get_field_type_options( $current = '' ) {
		$types = array(
			'wp-editor'           => __( 'WYSIWYG Editor', 'listeo-fafe' ),
			'text'                => __( 'Text input', 'listeo-fafe' ),
			'textarea'            => __( 'Textarea', 'listeo-fafe' ),
			'repeatable'          => __( 'Repeatable', 'listeo-fafe' ),
			'select'              => __( 'Select dropdown', 'listeo-fafe' ),
			'checkboxes'          => __( 'Checkboxes list', 'listeo-fafe' ),
			'checkbox'            => __( 'Single On/Off checkbox', 'listeo-fafe' ),
			'number'              => __( 'Number input', 'listeo-fafe' ),
			'term-select'         => __( 'Select taxonomy', 'listeo-fafe' ),
			'term-checkboxes'     => __( 'Taxonomy checkboxes', 'listeo-fafe' ),
			'drilldown-taxonomy'  => __( 'Taxonomy drilldown', 'listeo-fafe' ),
			'slots'               => __( 'Time slots (booking)', 'listeo-fafe' ),
			'calendar'            => __( 'Availability Calendar', 'listeo-fafe' ),
			'hours'               => __( 'Opening hours', 'listeo-fafe' ),
			'skipped'             => __( 'Skipped input', 'listeo-fafe' ),
			'pricing'             => __( 'Pricing (menu)', 'listeo-fafe' ),
			'hidden'              => __( 'Hidden input', 'listeo-fafe' ),
			'files'               => __( 'Files (Gallery)', 'listeo-fafe' ),
			'file'                => __( 'Single File Upload', 'listeo-fafe' ),
			'timezone'            => __( 'Timezone field', 'listeo-fafe' ),
		);

		/**
		 * Allow plugins to register their custom submit-form field types.
		 * Returned array shape: type_value => human-readable label.
		 *
		 * @since 1.0
		 * @param array  $types   Map of type_value => label.
		 * @param string $current Currently saved type for the field.
		 */
		$types = apply_filters( 'listeo_submit_editor_field_types', $types, $current );

		// Preserve any saved-but-unregistered type so it doesn't silently
		// flip to "WYSIWYG Editor" (the first option) on save.
		if ( $current !== '' && ! isset( $types[ $current ] ) ) {
			$types[ $current ] = sprintf(
				/* translators: %s: raw field type slug */
				__( 'Custom (%s)', 'listeo-fafe' ),
				$current
			);
		}

		return $types;
	}

	public function get_label_nicename($label)
	{
		switch ($label) {
			case 'label':
				$label = __('Label <span class="dashicons dashicons-editor-help" title="Text that is displayed before/next to the input field"></span>', 'listeo-fafe');
				break;

			case 'name':
				$label = __('Name <span class="dashicons dashicons-editor-help" title="Name attribute on the input field, do not edit if you don\'t know what you are doing :)"></span>', 'listeo-fafe');
				break;

			case 'type':
				$label = __('Type', 'listeo-fafe');
				break;
			case 'required':
				$label = __('Required <span class="dashicons dashicons-editor-help" title="Tick the checkbox to make this field required in submit form"></span>', 'listeo-fafe');
				break;
			case 'placeholder':
				$label = __('Placeholder <span class="dashicons dashicons-editor-help" title="Text that is displayed before/next to the input field"></span>', 'listeo-fafe');
				break;
			case 'taxonomy':
				$label = __('Taxonomy', 'listeo-fafe');
				break;
			case 'priority':
				$label = __('Priority ', 'listeo-fafe');
				break;
			case 'value':
				$label = __('Default value ', 'listeo-fafe');
				break;
			case 'default':
				$label = __('Default value', 'listeo-fafe');
				break;
			case 'render_row_col':
				$label = __('Width in form row', 'listeo-fafe');
				break;
			case 'description':
				$label = __('Description <span class="dashicons dashicons-editor-help" title="Additional option description for the field"></span>', 'listeo-fafe');
				break;
			case 'tooltip':
				$label = __('Tooltip', 'listeo-fafe');
				break;
			case 'class':
				$label = __('CSS class', 'listeo-fafe');
				break;
			case 'atts':
				$label = __('Attributes', 'listeo-fafe');
				break;
			case 'multi':
				$label = __('Multiple choice', 'listeo-fafe');
				break;
			case 'hide_all':
				$label = __('Hide "All" option <span class="dashicons dashicons-editor-help" title="Remove the &quot;All in...&quot; option from drilldown"></span>', 'listeo-fafe');
				break;
			case 'options':
				$label = __('Options', 'listeo-fafe');
				break;
			// case 'for_type':
			// 	$label = __('Field for type <span class="dashicons dashicons-editor-help" title="Select for which listing type this fields should be displayed"></span>', 'listeo-fafe');
			// 	break;

			default:
				return $label;
				break;
		}
		return $label;
	}


	public function generate_form_fields($fields, $field, $section, $active_tab_key)
	{

		if ($fields) :
			$tab_slug = substr($active_tab_key, 0, -4);

			$saved_fields = get_option("listeo_submit_{$tab_slug}form_fields");

			$submit_fields = (empty($saved_fields)) ? get_option("listeo_submit_form_fields") : $saved_fields;
			if (isset($submit_fields[$section]['fields'][$field])) {



				$fields = $submit_fields[$section]['fields'][$field];
				if (!isset($fields['tooltip'])) {
					$fields['tooltip'] = '';
				}
				if (!isset($fields['value'])) {
					$fields['value'] = '';
				}

				if (!isset($fields['class'])) {
					$fields['class'] = '';
				}
			}
			// Add hide_all option for drilldown field types
			if (isset($fields['type']) && in_array($fields['type'], array('drilldown-taxonomy', 'drilldown-listing-types'))) {
				if (!isset($fields['hide_all'])) {
					$fields['hide_all'] = 0;
				}
			}
			ob_start();

		?>

			<table class="listeo-modal-table form-table">
				<tbody>

					<?php

					foreach ($fields as $key => $value) {


						if (isset($fields_temp[$key])) {
							$value = $fields_temp[$key];
						}


						if (in_array($key, array('render_row_col', 'priority'))) {
							continue;
						}

					?>
						<tr valign="top" class="field-edit-<?php echo $key; ?>" <?php if (in_array($key, array('taxonomy', 'name'))) { ?>style="display: none;" <?php } ?>>
							<th scope="row">
								<label for="field_meta_key">
									<?php echo $this->get_label_nicename($key); ?></label>
							</th>

							<td>
								<?php

								switch ($key) {

									case 'type':

								?>
										<select name="<?php echo $section . '[' . $field . '][' . $key . ']'; ?>" class="widefat" type="text" ref="config" id="field_<?php echo $key; ?>">
											<?php foreach ( $this->get_field_type_options( $value ) as $type_val => $type_label ) : ?>
												<option <?php selected( $type_val, $value ); ?> value="<?php echo esc_attr( $type_val ); ?>"><?php echo esc_html( $type_label ); ?></option>
											<?php endforeach; ?>
										</select>

									<?php
										break;
									case 'multi':
									case 'required':
									case 'hide_all': ?>
										<input name="<?php echo $section . '[' . $field . '][' . $key . ']'; ?>" <?php checked(1, $value) ?> class="widefat" type="checkbox" id="field_<?php echo $key; ?>" value="1">
										<?php
										break;

									case 'atts':
									case 'options':

										echo '<table>';
										foreach ($value as $option_key => $value) { ?>
						<tr>
							<td><?php echo stripslashes($option_key) ?></td>
							<td><input name="<?php echo $section . '[' . $field . '][' . $key . '][' . $option_key . ']'; ?>" class="widefat" type="text" ref="config" id="field_<?php echo $option_key; ?>" value="<?php echo esc_attr(stripslashes($value)); ?>"></td>
						</tr>
					<?php }
										echo '</table>';
										break;

									default:
					?>
					<input name="<?php echo $section . '[' . $field . '][' . $key . ']'; ?>" class="widefat" type="text" ref="config" id="field_<?php echo $key; ?>" value="<?php echo stripslashes(esc_attr($value)); ?>">
			<?php
										break;
								} ?>


			</td>
			</tr>
		<?php } ?>
				</tbody>
			</table>
			<?php
			return ob_get_clean();
		endif;
	}

	public function generate_new_form_fields($fields, $section = 'new_field_section')
	{
		if ($fields) :

			ob_start();
			foreach ($fields as $field_key => $field) {

				if ($field['type'] == 'group') {

					$field['type'] = 'repeatable';
				}


			?>
				<div class="listeo-fafe-forms-editor-new-elements-container">
					<a href="#" data-section="<?php echo $section; ?>" class="insert-field button"><?php echo $field['label']; ?></a>
					<div style="display:none;" class='editor-block block-width-12 block-<?php echo sanitize_title($field['name']) ?>'>

						<h5><?php echo $field['label']; ?></h5>
						<div class="editor-block-tools">
							<input type="text" class="block-width-input" name="<?php echo $section; ?>[<?php echo $field['name']; ?>][render_row_col]" value="12">
							<input type="hidden" name="section[]" value="<?php echo $section; ?>">
							<input type="hidden" name="field[]" value="<?php echo $field['name']; ?>">

							<ul>
								<li class="block-edit"><a data-section="<?php echo $section; ?>" data-id="<?php echo $field['name']; ?>" href="#" class="button button-primary"></a></li>
								<li class="block-narrower"><a href="#"></a></li>
								<li class="block-wider"><a href="#"> </a></li>
								<li class="block-delete"><a href="#"></a></li>
							</ul>


						</div>
						<div class="editor-block-form-fields">
							<table class="editor-field-preset listeo-modal-table form-table">
								<tbody>
									<?php
									foreach ($field as $key => $value) { ?>

										<tr valign="top">
											<th scope="row">
												<label for="field_meta_key">
													<?php echo $this->get_label_nicename($key); ?></label>
											</th>

											<td>
												<?php

												switch ($key) {

													case 'type':
														if ($value == 'datetime') {
															$value = 'text'; ?>
															<input name="<?php echo $section . '[' . $field['name'] . '][class]'; ?>" class="widefat" type="text" ref="config" id="field_class" value="input-datetime">
														<?php }
														if ($value == 'multicheck') {
															$value = 'checkboxes';
														}
														if ($value == 'select_multiple') {
															$value = 'select';
														}
														?>
														<select name="<?php echo $section . '[' . $field['name'] . '][' . $key . ']'; ?>" class="widefat" type="text" ref="config" id="field_<?php echo $key; ?>">
															<?php foreach ( $this->get_field_type_options( $value ) as $type_val => $type_label ) : ?>
																<option <?php selected( $type_val, $value ); ?> value="<?php echo esc_attr( $type_val ); ?>"><?php echo esc_html( $type_label ); ?></option>
															<?php endforeach; ?>
														</select>

													<?php
														break;
													case 'required': ?>
														<input name="<?php echo $section . '[' . $field['name'] . '][' . $key . ']'; ?>" <?php checked(1, $value) ?> class="widefat" type="checkbox" id="field_<?php echo $key; ?>" value="<?php echo $value; ?>">
														<?php
														break;

													case 'atts':
													case 'options':

														echo '<table>';
														foreach ($value as $temp_key => $value) { ?>
										<tr>
											<td><?php echo $temp_key ?></td>
											<td><input name="<?php echo $section . '[' . $field['name'] . '][' . $key . '][' . $temp_key . ']'; ?>" class="widefat" type="text" ref="config" id="field_<?php echo $temp_key; ?>" value="<?php echo esc_attr($value); ?>"></td>
										</tr>
									<?php }
														echo '</table>';
														break;


													default:
									?>
									<input name="<?php echo $section . '[' . $field['name'] . '][' . $key . ']'; ?>" class="widefat" type="text" ref="config" id="field_<?php echo $key; ?>" value="<?php echo (stripslashes($value)); ?>">
							<?php
														break;
												} ?>


							</td>
							</tr>

						<?php
									} ?>
								</tbody>
							</table>

						</div>
					</div>
				</div>

			<?php } ?>

<?php
			return ob_get_clean();
		endif;
	}
}
