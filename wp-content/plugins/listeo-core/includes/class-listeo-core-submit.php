<?php
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}


class Listeo_Core_Submit
{

	/**
	 * Form name.
	 *
	 * @var string
	 */
	public $form_name = 'submit-listing';

	public $listing_edit;
	/**
	 * Listing ID.
	 *
	 * @access protected
	 * @var int
	 */
	protected $listing_id;


	/**
	 * Listing Type
	 *
	 * @var string
	 */
	protected $listing_type;


	/**
	 * Form fields.
	 *
	 * @access protected
	 * @var array
	 */
	protected $fields = array();


	/**
	 * Form errors.
	 *
	 * @access protected
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Form steps.
	 *
	 * @access protected
	 * @var array
	 */
	protected $steps = array();

	/**
	 * Current form step.
	 *
	 * @access protected
	 * @var int
	 */
	protected $step = 0;


	/**
	 * Form action.
	 *
	 * @access protected
	 * @var string
	 */
	protected $action = '';

	/**
	 * Form form_action.
	 *
	 * @access protected
	 * @var string
	 */
	protected $form_action = '';

	private static $package_id      = 0;
	private static $is_user_package = false;

	/**
	 * Store old place ID before meta updates
	 * @var string
	 */
	private $old_place_id = '';

	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 * @var Listeo_Core_Submit The single instance of the class
	 */
	protected static $_instance = null;

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


	/**
	 * Constructor
	 */
	public function __construct()
	{

		add_shortcode('listeo_submit_listing', array($this, 'get_form'));
		//add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media' ) );

		//add_filter( 'the_title', array( $this, 'change_page_title' ), 10, 2 );
		add_filter('submit_listing_steps', array($this, 'enable_paid_listings'), 30);

		add_action('wp', array($this, 'process'));

		$this->steps  = (array) apply_filters('submit_listing_steps', array(

			'type' => array(
				'name'     => __('Choose Type ', 'listeo_core'),
				'view'     => array($this, 'type'),
				'handler'  => array($this, 'type_handler'),
				'priority' => 5
			),
			'submit' => array(
				'name'     => __('Submit Details', 'listeo_core'),
				'view'     => array($this, 'submit'),
				'handler'  => array($this, 'submit_handler'),
				'priority' => 10
			),
			'preview' => array(
				'name'     => __('Preview', 'listeo_core'),
				'view'     => array($this, 'preview'),
				'handler'  => array($this, 'preview_handler'),
				'priority' => 20
			),
			'done' => array(
				'name'     => __('Done', 'listeo_core'),
				'view'     => array($this, 'done'),
				'priority' => 30
			)
		));
		// if(get_option('listeo_new_listing_preview' )) {
		// 	unset($this->steps['preview']);
		// }

		// Get dynamic listing types
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$available_types = $custom_types_manager->get_listing_types(true); // Get active types only
			$listing_types = array();
			foreach ($available_types as $type) {
				$listing_types[] = $type->slug;
			}
		} else {
			// Fallback to old system if custom types manager not available
			$listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));
		}

		if (empty($listing_types)) {
			unset($this->steps['type']);
		}
		if (is_array($listing_types) && sizeof($listing_types) == 1) {
			unset($this->steps['type']);
		}
		uasort($this->steps, array($this, 'sort_by_priority'));


		if (! empty($_POST['package'])) {

			if (is_numeric($_POST['package'])) {

				self::$package_id      = absint($_POST['package']);
				self::$is_user_package = false;
			} else {

				self::$package_id      = absint(substr($_POST['package'], 5));
				self::$is_user_package = true;
			}
		} else {
			// Try the cookie next, but only if the package it points at still exists and is usable.
			// User-package row ids can be recreated by the subscription self-heal in
			// class-listeo-core-paid-subscriptions.php; an old cookie would otherwise resolve to a
			// missing row and surface as "Invalid Package" at validate_package().
			if (! empty($_COOKIE['chosen_package_id'])) {
				$cookie_pkg     = absint($_COOKIE['chosen_package_id']);
				$cookie_is_user = absint(isset($_COOKIE['chosen_package_is_user_package']) ? $_COOKIE['chosen_package_is_user_package'] : 0) === 1;
				$cookie_valid   = false;

				if ($cookie_pkg) {
					if ($cookie_is_user) {
						if (function_exists('listeo_core_package_is_valid')) {
							$cookie_valid = listeo_core_package_is_valid(get_current_user_id(), $cookie_pkg);
						}
					} else {
						// Verify the product is a listing package via the product_type
						// taxonomy directly. We can't use wc_get_product()->is_type() here
						// because WC_Product_Listing_Package_Subscription is included by
						// Listeo_Core::init_plugin() at init priority 13 — AFTER this
						// constructor runs — so for subscription packages WC's factory
						// would fall back to WC_Product_Simple and is_type() would falsely
						// return false, wiping a perfectly good cookie.
						$type_terms = ('product' === get_post_type($cookie_pkg))
							? wp_get_object_terms($cookie_pkg, 'product_type', array('fields' => 'slugs'))
							: array();
						$cookie_valid = is_array($type_terms) && (in_array('listing_package', $type_terms, true) || in_array('listing_package_subscription', $type_terms, true));
					}
				}

				if ($cookie_valid) {
					self::$package_id      = $cookie_pkg;
					self::$is_user_package = $cookie_is_user;
				} else {
					// Stale cookie — clear it so subsequent submissions don't keep failing,
					// and let the skip-option fallback below run as if no cookie was present.
					wc_setcookie('chosen_package_id', '', time() - HOUR_IN_SECONDS);
					wc_setcookie('chosen_package_is_user_package', '', time() - HOUR_IN_SECONDS);
					unset($_COOKIE['chosen_package_id'], $_COOKIE['chosen_package_is_user_package']);
				}
			}

			if (! self::$package_id && get_option('listeo_new_listing_requires_purchase') && get_option('listeo_skip_package_if_user_has_one')) {
				//check
				if (is_user_logged_in()) {
					global $wpdb;


					$package_type = array('listing_package');

					$user_id = get_current_user_id();
					$packages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE user_id = %d AND ( package_count < package_limit OR package_limit = 0 );", $user_id), OBJECT_K);


					if (sizeof($packages) >= 1) {
						//get first package
						$packages = array_values($packages);

						self::$package_id = $packages[0]->id;
						self::$is_user_package = true;
					}
				}
			}
		}

		// Get step/listing
		if (isset($_POST['step'])) {
			$this->step = is_numeric($_POST['step']) ? max(absint($_POST['step']), 0) : array_search($_POST['step'], array_keys($this->steps));
		} elseif (! empty($_GET['step'])) {
			$this->step = is_numeric($_GET['step']) ? max(absint($_GET['step']), 0) : array_search($_GET['step'], array_keys($this->steps));
		}

		$this->listing_id = ! empty($_REQUEST['listing_id']) ? absint($_REQUEST['listing_id']) : 0;
		$this->listing_type = ! empty($_REQUEST['_listing_type']) ?  $_REQUEST['_listing_type']  : false;

		if (is_array($listing_types) && sizeof($listing_types) == 1) {
			$this->listing_type = $listing_types[0];
		}

		if (!is_admin() && isset($_GET["action"]) && isset($_GET['listing_id'])  && $_GET["action"] == 'edit') {
			$this->form_action = "editing";
			unset($this->steps['type']);
			$this->listing_id = ! empty($_GET['listing_id']) ? absint($_GET['listing_id']) : 0;

			if (self::$package_id == 0) {
				self::$package_id = get_post_meta($_GET['listing_id'], '_package_id', true);
				if (get_post_meta($_GET['listing_id'], '_user_package_id', true)) {
					self::$is_user_package = true;
				}
				//self::$is_user_package = get_post_meta($_GET[ 'listing_id' ],'_user_package_id',true);

			}
		}

		if (isset($_GET["action"]) && $_GET["action"] == 'renew') {
			$this->form_action = "renew";
			unset($this->steps['type']);
			$this->listing_id = ! empty($_GET['listing_id']) ? absint($_GET['listing_id']) : 0;
			if (self::$package_id == 0) {
				self::$package_id = get_post_meta($_GET['listing_id'], '_package_id', true);
				if (get_post_meta($_GET['listing_id'], '_user_package_id', true)) {
					self::$is_user_package = true;
				}
				//self::$is_user_package = get_post_meta($_GET[ 'listing_id' ],'_user_package_id',true);

			}
		}

		// Handle "continue" action for incomplete submissions (preview, pending_payment status)
		if (isset($_GET["action"]) && $_GET["action"] == 'continue') {
			$this->form_action = "continue";
			unset($this->steps['type']);
			$this->listing_id = ! empty($_GET['listing_id']) ? absint($_GET['listing_id']) : 0;

			// Get the listing to check its status
			$listing = get_post($this->listing_id);

			if ($listing && $listing->post_status == 'pending_payment') {
				// For pending_payment, we need to restore the WooCommerce cart and redirect to checkout
				$package_id = get_post_meta($this->listing_id, '_package_id', true);
				$user_package_id = get_post_meta($this->listing_id, '_user_package_id', true);

				if ($user_package_id) {
					// User package - complete immediately without payment
					self::$package_id = $user_package_id;
					self::$is_user_package = true;

					// Process the user package and complete
					if (self::process_package($user_package_id, true, $this->listing_id)) {
						// Redirect to success/done page
						wp_redirect(add_query_arg(array('step' => 'done'), get_permalink()));
						exit;
					}
				} elseif ($package_id && is_woocommerce_activated() && function_exists('WC')) {
					// Purchasable package - restore cart and redirect to checkout
					self::$package_id = $package_id;
					self::$is_user_package = false;

					// Clear any existing cart
					WC()->cart->empty_cart();

					// Re-add the package to cart with listing info
					WC()->cart->add_to_cart($package_id, 1, '', '', array(
						'listing_id' => $this->listing_id,
					));

					// Redirect to checkout
					wp_redirect(get_permalink(wc_get_page_id('checkout')));
					exit;
				} else {
					// No package info found - take user back to package selection step
					// Change listing status back to preview so they can select a package
					wp_update_post(array(
						'ID' => $this->listing_id,
						'post_status' => 'preview'
					));

					// Set step to package selection (first step after type)
					$this->step = array_search('package', array_keys($this->steps));
					if ($this->step === false) {
						// If no package step found, go to first step
						$this->step = 0;
					}
				}
			}

			// Get package info for other cases
			if (self::$package_id == 0) {
				self::$package_id = get_post_meta($this->listing_id, '_package_id', true);
				if (get_post_meta($this->listing_id, '_user_package_id', true)) {
					self::$is_user_package = true;
				}
			}

			// Check if we have package info, if not, go to package selection
			if (!self::$package_id && get_option('listeo_new_listing_requires_purchase')) {
				// No package selected - take user to package selection step
				$this->step = array_search('package', array_keys($this->steps));
				if ($this->step === false) {
					// If no package step found, go to first step
					$this->step = 0;
				}
			} else {
				// For preview status or fallback, start from preview step
				$this->step = array_search('preview', array_keys($this->steps));
			}
		}

		// if(get_post_meta($this->listing_id, '_listing_type', true)) {
		// 	unset($this->steps['type']);
		// }

		$this->listing_edit = false;
		if (! isset($_GET['new']) && (! $this->listing_id) && ! empty($_COOKIE['listeo-submitting-listing-id']) && ! empty($_COOKIE['listeo-submitting-listing-key'])) {
			$listing_id     = absint($_COOKIE['listeo-submitting-listing-id']);
			$listing_status = get_post_status($listing_id);

			if (('preview' === $listing_status || 'pending_payment' === $listing_status) && get_post_meta($listing_id, '_submitting_key', true) === $_COOKIE['listeo-submitting-listing-key']) {
				$this->listing_id = $listing_id;
				$this->listing_edit = get_post_meta($listing_id, '_submitting_key', true);
			}
		}
		// Load listing details
		/*		if ( $this->listing_id ) {
			$listing_status = get_post_status( $this->listing_id );
			//whats that for?
			if ( ! in_array( $listing_status, apply_filters( 'listeo_core_valid_submit_listing_statuses', array( 'preview','pending_payment' ) ) ) ) {
				$this->listing_id = 0;
				$this->step   = 0;
			}
		}*/
		// We should make sure new jobs are pending payment and not published or pending.
		add_filter('submit_listing_post_status', array($this, 'submit_listing_post_status'), 10, 2);
		add_action('wp_ajax_listeo_get_custom_fields_from_term', array($this, 'ajax_get_custom_fields_from_term'));
	}

	function ajax_get_custom_fields_from_term()
	{
		$term = isset($_REQUEST['term']) ? sanitize_text_field($_REQUEST['term']) : false;
		$categories  = isset($_REQUEST['cat_ids']) ? $_REQUEST['cat_ids'] : false;
		$listing_id = isset($_REQUEST['listing_id']) ? intval($_REQUEST['listing_id']) : 0;
		if (!$term || !$categories || !is_array($categories) || empty($categories)) {
			wp_send_json_error(array('message' => esc_html__('Invalid request', 'listeo_core')));
			return;
		}
		$success = true;
		ob_start();
		foreach ($categories as $key => $category) {
			// get custom fields for term
			if (get_option("listeo_tax-{$term}_term_{$category}_fields")) {

				$term_fields[] = get_option("listeo_tax-{$term}_term_{$category}_fields");
			}
		}

		if (empty($term_fields) || !is_array($term_fields)) {
			// Return empty result - no custom fields found
			wp_send_json(array(
				'output' => '',
				'success' => true
			));
			return;
		}

		// render custom fields
		if (! empty($term_fields) && is_array($term_fields)) {
			foreach ($term_fields as  $fields) {
				foreach ($fields as $key => $field) {
					//prepare field


					$field['label'] = $field['name'];
					$field['render_row_col'] = 3;

					if (isset($field['type']) && $field['type'] == "skipped") {
						continue;
					}

					// if field type is select, add placeholder and empty option
					if ($field['type'] == 'select') {
						$placeholder_text = esc_html__('Select an option', 'listeo_core');
						$field['placeholder'] = $placeholder_text;

						// Add a user-friendly "None" option at the beginning of options
						// Use 'none' as value instead of empty string since Select2 ignores empty options
						if (isset($field['options']) && is_array($field['options'])) {
							$none_text = esc_html__('Select an option', 'listeo_core');
							$field['options'] = array('none' => $none_text) + $field['options'];
						}

						// Add CSS class for Select2
						$field['class'] = isset($field['class']) ? $field['class'] . ' select2-single' : 'select2-single';
					}
					// if field type is multiche
					if ($field['type'] == 'select_multiple') {

						$field['type'] = 'select';
						$field['multi'] = 'on';
						$field['placeholder'] = '';
					}

					if ($field['type'] == 'datetime') {
						$field['class'] = 'input-datetime';
					}

					if ($field['type'] == 'multicheck_split') {

						$field['type'] = 'checkboxes';
					}
					if ($field['type'] == 'wp-editor') {
						$field['type'] = 'textarea';
					}
					if (isset($field['id'])) {
						$field['name'] = $field['id'];
					}
					$saved = '';

					// For multi-value fields (select_multiple, multicheck_split), get all values
					if (isset($field['type']) && in_array($field['type'], array('select', 'checkboxes'))) {
						// Get all values (returns array) - these are now stored as separate records
						$saved = get_post_meta($listing_id, $field['name'], false);
						// Remove empty values
						if (!empty($saved)) {
							$saved = array_filter($saved, function ($value) {
								return !empty($value);
							});
						}
					} else {
						// For single-value fields, get single value
						$saved = get_post_meta($listing_id, $field['name'], true);
					}


					$field['value'] = $saved;
					// if( isset($field['before_row']) ) : 
					// 	echo $field['before_row'].' <!-- before row '.$field['label'].' -->';
					// endif; 
?>
					<?php
					if (isset($field['render_row_col']) && !empty($field['render_row_col']) && $field['type'] != 'header'):
						listeo_core_render_column($field['render_row_col'], $field['name'], $field['type']);
					else:
						listeo_core_render_column(12, $field['name'], $field['type']);
					endif;
					?>
					<?php
					// if type header display as h4 else display:
					if (isset($field['type']) && $field['type'] == 'header') : ?>
						<h4 class="form-title"><?php echo esc_html($field['label']); ?></h4>
					<?php elseif (isset($field['type']) && $field['type'] != 'hidden') : ?>

						<label class="label-<?php echo esc_attr($key); ?>" for="<?php echo esc_attr($key); ?>">
							<?php echo stripslashes($field['label']) . apply_filters('submit_listing_form_required_label', (isset($field['required']) && !empty($field['required'])) ? '<i>*</i>' : ' <small>' . esc_html__('(optional)', 'listeo_core') . '</small>', $field); ?>
							<?php if (isset($field['tooltip']) && !empty($field['tooltip'])) { ?>
								<i class="tip" data-tip-content="<?php (esc_attr_e(stripslashes($field['tooltip']))); ?>"></i>
							<?php } ?>
						</label>
					<?php endif; ?>

					<?php

					$template_loader = new Listeo_Core_Template_Loader;

					$template_loader->set_template_data(array('key' => $key, 'field' => $field,))->get_template_part('form-fields/' . $field['type']);
					?>
					</div>


<?php
				}
			}
		} else {
			echo esc_html__('No custom fields found for this term.', 'listeo_core');
		}





		$result['output'] = ob_get_clean();
		$result['success'] = $success;
		wp_send_json($result);
	}



	/**
	 * Processes the form result and can also change view if step is complete.
	 */
	public function process()
	{

		// reset cookie
		if (
			isset($_GET['new']) &&
			isset($_COOKIE['listeo-submitting-listing-id']) &&
			isset($_COOKIE['listeo-submitting-listing-key']) &&
			get_post_meta($_COOKIE['listeo-submitting-listing-id'], '_submitting_key', true) == $_COOKIE['listeo-submitting-listing-key']
		) {
			delete_post_meta($_COOKIE['listeo-submitting-listing-id'], '_submitting_key');
			setcookie('listeo-submitting-listing-id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false);
			setcookie('listeo-submitting-listing-key', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false);

			wp_redirect(remove_query_arg(array('new', 'key'), $_SERVER['REQUEST_URI']));
		}

		$step_key = $this->get_step_key($this->step);

		if (isset($_POST['listeo_core_form'])) {


			if ($step_key && isset($this->steps[$step_key]['handler']) && is_callable($this->steps[$step_key]['handler'])) {
				call_user_func($this->steps[$step_key]['handler']);
			}
		}
		$next_step_key = $this->get_step_key($this->step);

		// if the step changed, but the next step has no 'view', call the next handler in sequence.
		if ($next_step_key && $step_key !== $next_step_key && ! is_callable($this->steps[$next_step_key]['view'])) {
			$this->process();
		}
	}





	/**
	 * Initializes the fields used in the form.
	 */
	public function init_fields()
	{

		if ($this->fields) {
			return;
		}

		$scale = get_option('listeo_scale', 'sq ft');

		$currency_abbr = get_option('listeo_currency');

		$currency = Listeo_Core_Listing::get_currency_symbol($currency_abbr);


		if (!$this->listing_type) {
			$listing_type_field = get_post_meta($this->listing_id, '_listing_type', true);
			if ($listing_type_field) {
				$this->listing_type = $listing_type_field;
			} else {
				$this->listing_type = 'service';
			}
		}
		// Get fields dynamically based on listing type
		$this->fields = apply_filters('submit_listing_form_fields', $this->fields, $this->listing_type);

		$this->fields = $this->get_fields_for_listing_type($this->listing_type);


		// — Inject term‐based custom fields for *any* listing taxonomy —
		$taxonomies = get_object_taxonomies('listing', 'names');

		if (! empty($taxonomies)) {
			foreach ($taxonomies as $taxonomy) {
				// figure out which term IDs were actually submitted
				$term_ids = array();

				// Check for direct taxonomy key in POST or REQUEST
				if (! empty($_POST[$taxonomy])) {
					$term_ids = (array) $_POST[$taxonomy];
				} elseif (! empty($_REQUEST[$taxonomy])) {
					$term_ids = (array) $_REQUEST[$taxonomy];
				}
				// Fallback: check for prefixed key like tax-{$taxonomy}
				elseif (! empty($_POST['tax-' . $taxonomy])) {
					$term_ids = (array) $_POST['tax-' . $taxonomy];
				} elseif (! empty($_REQUEST['tax-' . $taxonomy])) {
					$term_ids = (array) $_REQUEST['tax-' . $taxonomy];
				}

				if (empty($term_ids)) {
					continue;
				}


				// load each term's custom fields
				$custom_fields = array();
				foreach ($term_ids as $term_id) {
					$opt_name = sprintf('listeo_tax-%s_term_%d_fields', $taxonomy, intval($term_id));

					$fields_for_term = get_option($opt_name, array());

					if (is_array($fields_for_term) && ! empty($fields_for_term)) {
						// Apply the same field type conversion as in ajax_get_custom_fields_from_term
						foreach ($fields_for_term as $field_key => $field) {
							$field['label'] = $field['name'];
							// Convert select_multiple to select with multi=on
							if (isset($field['type']) && $field['type'] == 'select_multiple') {
								$field['type'] = 'select';
								$field['multi'] = 'on';
								$field['placeholder'] = '';
							}

							// Convert multicheck_split to checkboxes
							if (isset($field['type']) && $field['type'] == 'multicheck_split') {
								$field['type'] = 'checkboxes';
							}

							$fields_for_term[$field_key] = $field;
						}

						$custom_fields = array_merge($custom_fields, $fields_for_term);
					}
				}

				if (empty($custom_fields)) {
					continue;
				}

				// inject right after the taxonomy selector in basic_info panel, if present
				if (isset($this->fields['basic_info']['fields'][$taxonomy])) {
					$spliced = array();
					foreach ($this->fields['basic_info']['fields'] as $key => $cfg) {
						$spliced[$key] = $cfg;
						if ($key === $taxonomy) {
							// immediately after the <select> for this taxonomy
							foreach ($custom_fields as $fkey => $fargs) {
								$spliced[$fkey] = $fargs;
							}
						}
					}
					$this->fields['basic_info']['fields'] = $spliced;
				} else {
					// fallback: append into a “Custom Fields” panel
					if (! isset($this->fields['custom_fields'])) {
						$this->fields['custom_fields'] = array(
							'title'  => __('Custom Fields', 'listeo'),
							'class'  => '',
							'icon'   => 'sl sl-icon-star',
							'fields' => array(),
						);
					}
					$this->fields['custom_fields']['fields'] =
						array_merge($this->fields['custom_fields']['fields'], $custom_fields);
				}
			}
		}
		// — end dynamic taxonomy injection —


		// disable opening hours everywhere outside services

		// disable slots everywhere outside services

		// disable availability calendar outside rent
		if ($this->listing_type == 'event' && apply_filters('disable_availability_calendar', true))
			unset($this->fields['availability_calendar']);


		if ($this->listing_type != 'classifieds')
			unset($this->fields['classifieds']);

		// disable event date calendar outside events
		if ($this->listing_type != 'event') {
			//unset( $this->fields['event']);
			//unset( $this->fields['basic_prices']['fields']['_event_tickets'] );
		} else {
			// disable fields for events
			//unset( $this->fields['basic_prices']['fields']['_normal_price'] );
			unset($this->fields['basic_prices']['fields']['_weekday_price']);
			unset($this->fields['basic_prices']['fields']['_count_per_guest']);
			unset($this->fields['basic_prices']['fields']['_max_guests']);
			unset($this->fields['basic_prices']['fields']['_min_guests']);

			$this->fields['basic_prices']['fields']['_event_tickets']['render_row_col'] = 3;
			$this->fields['basic_prices']['fields']['_normal_price']['render_row_col'] = 3;
			$this->fields['basic_prices']['fields']['_normal_price']['label'] = esc_html__('Ticket Price', 'listeo_core');
			$this->fields['basic_prices']['fields']['_reservation_price']['render_row_col'] = 3;
			$this->fields['basic_prices']['fields']['_expired_after']['render_row_col'] = 3;
		}

		//
		if (isset($this->fields['menu']['fields']['_menu'])) {
			$this->fields['menu']['fields']['_hide_pricing_if_bookable'] = array(
				'type'        => 'checkboxes',
				'required'    => false,
				'placeholder' => '',
				'name'        => '_hide_pricing_if_bookable',
				'label'       => '',
				'placeholder' => '',
				'class'		  => '',
				'options'	=> array(
					'hide' => __('Hide pricing table on listing page but show bookable services in booking widget', 'listeo_core')
				),
			);
		}
		//add coupon fields
		$modules_state = get_option('listeo_submit_form_modules_disabled', array());
		if ($modules_state == '') {
			$modules_state = array();
		}

		if (!in_array('faq', $modules_state)) {


			// faq section
			$this->fields['faq'] = array(
				'title' 	=> __('FAQ', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'onoff'		=> true,
				'icon' 		=> 'fa fa-clipboard-question',
				'fields' 	=> array(
					'_faq_status' => array(
						'label'       => __('FAQ Widget status', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_faq_status',
					),
					'_faq_list' => array(
						'label'       => __('Add FAQ section', 'listeo_core'),
						'name'       => '_faq_list',
						'type'        => 'repeatable',
						'placeholder' => '',
						'class'		  => '',
						'priority'    => 1,

						'options'	 => array(
							'question' => __('Question', 'listeo_core'),
							'answer' => __('Answer', 'listeo_core'),
						),
						'required'    => false,
						'render_row_col' => '12'
					),

				),
			);
		}


		if (!get_option('listeo_remove_coupons')) {

			//get user coupons

			$current_user = wp_get_current_user();


			$args = array(
				'author'        	=>  $current_user->ID,
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


			if ($coupons) {
				$this->fields['coupon_section'] = array(
					'title' 	=> __('Coupon Widget Settings', 'listeo_core'),
					//'class' 	=> 'margin-top-40',
					'onoff'		=> true,
					'icon' 		=> 'fa fa-barcode',
					'fields' 	=> array(
						'_coupon_section_status' => array(
							'label'       => __('Coupon Widget status', 'listeo_core'),
							'type'        => 'skipped',
							'required'    => false,
							'name'        => '_coupon_section_status',
						),
						// 'coupon_bg-uploader-id' => array(
						// 			'label'       => __( 'Coupon Background status', 'listeo_core' ),
						// 			'type'        => 'file',
						// 			'required'    => false,
						// 			'name'        => '_coupon_bg-uploader-id',
						// 	),
						'_coupon_for_widget' => array(
							'label'       => __('Select one of your coupons to display in sidebar in this listing view', 'listeo_core'),
							'name'       => '_coupon_for_widget',
							'type'        => 'select',
							'placeholder' => '',
							'class'		  => '',
							'priority'    => 1,
							'multi'    => 1,
							'options'	 => $coupon_options,
							'required'    => false,
						),

					),
				);
			}
		}

		$current_user = wp_get_current_user();

		if (class_exists('WeDevs_Dokan')) :
			$args = array(
				'author'        	=>  $current_user->ID,
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'asc',
				'post_type'        => 'product',
				'post_status'      => 'publish',
			);
			$args['tax_query'] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms' => array('listing_booking', 'listing_package'), // 
				'operator' => 'NOT IN'
			);

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
			$products = wc_get_products($args);
			if ($products) {
				$product_options[0] = esc_html__('Select product', 'listeo_core');
			}
			foreach ($products as $product) {
				$product_options[$product->get_id()] = $product->get_title();
			}
			// dokan section
			if ($products) {
				$this->fields['store_section'] = array(
					'title' 	=> __('Store Settings', 'listeo_core'),
					//'class' 	=> 'margin-top-40',
					'onoff'		=> true,
					'icon' 		=> 'fa fa-store-alt',
					'fields' 	=> array(
						'_store_section_status' => array(
							'label'       => __('Store Section status', 'listeo_core'),
							'type'        => 'skipped',
							'required'    => false,
							'name'        => '_store_section_status',
						),
						'_store_products' => array(
							'label'       => __('Select some of your products to display in this listing view', 'listeo_core'),
							'name'       => '_store_products',
							'type'        => 'select',
							'placeholder' => '',
							'class'		  => '',
							'priority'    => 1,
							'multi'    => 'on',
							'options'	 => $product_options,
							'required'    => false,
						),
						'_store_widget_status' => array(
							'type'        => 'checkboxes',
							'required'    => false,
							'placeholder' => '',
							'name'        => '_store_widget_status',
							'label'       => '',
							'placeholder' => '',
							'class'		  => '',
							'options'	=> array(
								'show' => __('Show store card widget on listing sidebar', 'listeo_core')
							),
						),

					),
				);
			}



		endif; //dokan

		if (!in_array('other_listings', $modules_state)) {
			$user_listings_count = count_user_posts(get_current_user_id(), 'listing', true);

			if ($user_listings_count > 1) {

				$listing_options = array();
				$listing_options[0] = esc_html__('Select listing', 'listeo_core');

				$args = array(
					'author'        	=>  $current_user->ID,
					'posts_per_page'   => 99,
					'orderby'          => 'title',
					'order'            => 'asc',
					'post_type'        => 'listing',
					'post_status'      => 'publish',
				);
				$user_listings = new WP_Query($args);
				$listings = $user_listings->get_posts();
				foreach ($listings as $listing) {
					$listing_options[$listing->ID] = get_the_title($listing);
				}
				// dokan section
				if ($listings) {
					$this->fields['my_listings_section'] = array(
						'title' 	=> __('My Listings', 'listeo_core'),
						//'class' 	=> 'margin-top-40',
						'onoff'		=> true,
						'icon' 		=> 'fa fa-network-wired',
						'fields' 	=> array(
							'_my_listings_section_status' => array(
								'label'       => __('My Listings Section status', 'listeo_core'),
								'type'        => 'skipped',
								'required'    => false,
								'name'        => '_my_listings_section_status',
							),
							// title field

							'_my_listings' => array(
								'label'       => __('Select some of your listings to display below the content', 'listeo_core'),
								'name'       => '_my_listings',
								'type'        => 'select',
								'placeholder' => '',
								'class'		  => '',
								'priority'    => 1,
								'multi'    => 'on',
								'options'	 => $listing_options,
								'required'    => false,
							),
							'_my_listings_title' => array(
								'label'       => __('Set custom title for My Listings section', 'listeo_core'),
								'type'        => 'text',
								'required'    => false,
								'name'        => '_my_listings_title',
								'placeholder' => __('My Listings', 'listeo_core'),
								'class'		  => '',
								'priority'    => 1,
								'render_row_col' => '12'
							),
						),
					);
				}
			}
		}
		if (isset($this->fields['opening_hours']['fields']['_opening_hours'])) {
			$this->fields['opening_hours']['fields']['_monday_opening_hour'] = array(
				'label'       => __('Monday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_monday_opening_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_monday_closing_hour'] = array(
				'label'       => __('Monday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_monday_closing_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_tuesday_opening_hour'] = array(
				'label'       => __('Tuesday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_tuesday_opening_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_tuesday_closing_hour'] = array(
				'label'       => __('Monday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_tuesday_closing_hour',
				'before_row' 	 => '',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_wednesday_opening_hour'] = array(
				'label'       => __('Wednesday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_wednesday_opening_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_wednesday_closing_hour'] = array(
				'label'       => __('Wednesday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_wednesday_closing_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_thursday_opening_hour'] = array(
				'label'       => __('Thursday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_thursday_opening_hour',
				'before_row' 	 => '',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_thursday_closing_hour'] = array(
				'label'       => __('Thursday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_thursday_closing_hour',
				'before_row' 	 => '',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_friday_opening_hour'] = array(
				'label'       => __('Friday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_friday_opening_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_friday_closing_hour'] = array(
				'label'       => __('Friday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_friday_closing_hour',
				'before_row' 	 => '',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_saturday_opening_hour'] = array(
				'label'       => __('saturday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_saturday_opening_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_saturday_closing_hour'] = array(
				'label'       => __('saturday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_saturday_closing_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_sunday_opening_hour'] = array(
				'label'       => __('sunday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_sunday_opening_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
			$this->fields['opening_hours']['fields']['_sunday_closing_hour'] = array(
				'label'       => __('sunday Opening Hour', 'listeo_core'),
				'type'        => 'skipped',
				'required'    => false,
				'name'        => '_sunday_closing_hour',
				'priority'    => 9,
				'render_row_col' => '4'
			);
		}



		$this->fields['basic_info']['fields']['product_id'] = array(
			'name'        => 'product_id',
			'type'        => 'hidden',
			'required'    => false,
		);

		$this->fields['gallery']['fields']['_thumbnail_id'] = array(
			'label'       => __('Thumbnail ID', 'listeo_core'),
			'type'        => 'hidden',
			'name'        => '_thumbnail_id',
			'class'		  => '',
			'priority'    => 1,
			'required'    => false,
		);
		// remove parts for booking

		$packages_disabled_modules = get_option('listeo_listing_packages_options', array());
		if (empty($packages_disabled_modules)) {
			$packages_disabled_modules = array();
		}

		$package = false;

		if (!empty(self::$package_id)) {

			if (self::$is_user_package) {
				if (get_post_meta($this->listing_id, '_user_package_id', true)) {
					$package = listeo_core_get_user_package(get_post_meta($this->listing_id, '_user_package_id', true));
				} else {
					$package = listeo_core_get_user_package(self::$package_id);
				}

				//$package      = wc_get_product( $user_package->get_product_id() );

			} else {
				$package = wc_get_product(self::$package_id);
			}
		}


		foreach ($this->fields as $group_key => $group_fields) {
			if ( empty( $group_fields['fields'] ) || ! is_array( $group_fields['fields'] ) ) {
				continue;
			}
			foreach ($group_fields['fields']  as $key => $field) {


				if (in_array('option_booking', $packages_disabled_modules)) {
					if ($key == '_booking_status') {
						if ($package && $package->has_listing_booking() == 1) {
						} else {
							unset($this->fields[$group_key]);
						}
					}
				}
				if (in_array('option_social_links', $packages_disabled_modules)) {
					if (in_array($key, array('_facebook', '_tiktok', 'tiktok', '_twitter', '_youtube', '_instagram', '_whatsapp', '_skype', '_website', '_linkedin', '_pinterest', '_tumblr', '_vimeo', '_flickr', '_dribbble', '_myspace', '_reddit', '_telegram', '_facebook_group', '_google_plus'))) {
						if ($package && $package->has_listing_social_links() == 1) {
						} else {
							unset($this->fields[$group_key]['fields'][$key]);
						}
					}
				}
				if (in_array('option_opening_hours', $packages_disabled_modules)) {

					if ($key == '_opening_hours') {
						if ($package && $package->has_listing_opening_hours() == 1) {
						} else {
							unset($this->fields[$group_key]);
						}
					}
				}
				if (in_array('option_pricing_menu', $packages_disabled_modules)) {

					if ($key == '_menu') {
						if ($package && $package->has_listing_pricing_menu() == 1) {
						} else {
							unset($this->fields[$group_key]);
						}
					}
				}

				if ($key == '_gallery') {
					$gallery_limit = get_option('listeo_max_files', 10);

					if (!empty(self::$package_id)) {
						//	$gallery_limit = self::$package_id;


						//if($package->package != null) {

						//if($package && $package->package->package_option_gallery_limit){

						if (is_object($package) && method_exists($package, 'get_option_gallery_limit') && $package->get_option_gallery_limit()) {

							$gallery_limit = $package->get_option_gallery_limit();
						} else {
							$gallery_limit = get_option('listeo_max_files', 10);
						}
					} else {
						$gallery_limit = get_option('listeo_max_files', 10);
					}

					$this->fields[$group_key]['fields'][$key]['max_files'] = $gallery_limit;
				}

				if (in_array('option_gallery', $packages_disabled_modules)) {
					if ($key == '_gallery') {
						if ($package && $package->has_listing_gallery() == 1) {
						} else {
							unset($this->fields[$group_key]);
						}
					}
				}

				if (in_array('option_video', $packages_disabled_modules)) {

					if ($key == '_video') {
						if ($package && $package->has_listing_video() == 1) {
						} else {
							unset($this->fields[$group_key]['fields'][$key]);
						}
					}
				}
				if (in_array('option_coupons', $packages_disabled_modules)) {
					if ($key == '_coupon_section_status') {
						if ($package && $package->has_listing_coupons() == 1) {
						} else {
							unset($this->fields['coupon_section']);
						}
					}
				}

				if (in_array('option_faq', $packages_disabled_modules)) {
					if ($key == '_faq_status') {
						if ($package && $package->has_listing_faq() == 1) {
						} else {
							unset($this->fields['faq']);
						}
					}
				}
			}
		}

		//inject custom listing type taxonomy inject_dynamic_taxonomy_fields
		$this->fields = $this->inject_dynamic_taxonomy_fields($this->fields, $this->listing_type);


		// Old filter_fields_by_type method removed - now handled by customize_fields_for_type in get_fields_for_listing_type
		if (get_option('listeo_bookings_disabled')) {
			unset($this->fields['booking']);
			unset($this->fields['slots']);
			unset($this->fields['basic_prices']);
			unset($this->fields['availability_calendar']);
		}
	}

	/**
	 * Validates the posted fields.
	 *
	 * @param array $values
	 * @throws Exception Uploaded file is not a valid mime-type or other validation error
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	protected function validate_fields($values)
	{

		foreach ($this->fields as $group_key => $group_fields) {

			// Skip empty sections to avoid validation issues
			if (empty($group_fields['fields']) || !is_array($group_fields['fields'])) {
				continue;
			}

			foreach ($group_fields['fields']  as $key => $field) {
				// Skip fields without a type defined
				if (empty($field['type'])) {
					continue;
				}
				if (!in_array($field['type'], array('header', 'number')) && isset($field['required']) && $field['required'] && empty($values[$group_key][$key])) {
					return new WP_Error('validation-error', sprintf(__('%s is a required field', 'listeo_core'), $field['label']));
				}
				if (! empty($field['taxonomy']) && in_array($field['type'], array('term-checkboxes', 'term-select', 'term-multiselect', 'drilldown-taxonomy'))) {
					if (is_array($values[$group_key][$key])) {
						$check_value = $values[$group_key][$key];
					} else {
						$check_value = empty($values[$group_key][$key]) ? array() : array($values[$group_key][$key]);
					}

					foreach ($check_value as $term) {
						if ((int) $term != -1) {

							if (! term_exists((int) $term, $field['taxonomy'])) {

								return new WP_Error('validation-error', sprintf(__('%s is invalid', 'listeo_core'), $field['label']));
							}
						}

						if (isset($field['required']) && $field['required'] != 0 &&  (int) $term == -1) {
							return new WP_Error('validation-error', sprintf(__('%s is a required field', 'listeo_core'), $field['label']));
						}
					}
				}
				// check if there is at least 4 files uploaded, if less than 4, return error

				// if ( '_gallery' === $field['name'] ) {

				// 	$check_value = array_filter( $values[ $group_key ][ $key ] );
				// 	if ( count( $check_value ) < 4 ) {
				// 		return new WP_Error( 'validation-error', sprintf( __( '%s requires at least 4 files', 'listeo_core' ), $field['label'], $field['min_files'] ) );
				// 	}
				// }


				// if ( 'file' === $field['type'] && ! empty( $field['allowed_mime_types'] ) ) {
				// 	if ( is_array( $values[ $group_key ][ $key ] ) ) {
				// 		$check_value = array_filter( $values[ $group_key ][ $key ] );
				// 	} else {
				// 		$check_value = array_filter( array( $values[ $group_key ][ $key ] ) );
				// 	}

				// 	if ( ! empty( $check_value ) ) {
				// 		foreach ( $check_value as $file_url ) {
				// 			$file_url  = current( explode( '?', $file_url ) );
				// 			$file_info = wp_check_filetype( $file_url );

				// 			if ( ! is_numeric( $file_url ) && $file_info && ! in_array( $file_info['type'], $field['allowed_mime_types'] ) ) {
				// 				throw new Exception( sprintf( __( '"%s" (filetype %s) needs to be one of the following file types: %s', 'listeo_core' ), $field['label'], $file_info['ext'], implode( ', ', array_keys( $field['allowed_mime_types'] ) ) ) );
				// 			}
				// 		}
				// 	}
				// }
			}
		}

		return apply_filters('submit_listing_form_validate_fields', true, $this->fields, $values);
	}



	/**
	 * Displays the form.
	 */
	public function submit()
	{


		$this->init_fields();
		$template_loader = new Listeo_Core_Template_Loader;
		if (! is_user_logged_in()) {
			$template_loader->get_template_part('listing-sign-in');
			$template_loader->get_template_part('account/login-form');
		} else {


			if (is_user_logged_in() && $this->listing_id) {
				$listing = get_post($this->listing_id);

				//basic_info/fields/listing_title
				if ($listing) {

					foreach ($this->fields as $group_key => $group_fields) {
						foreach ($group_fields['fields'] as $key => $field) {

							switch ($key) {
								case 'listing_title':
									$this->fields[$group_key]['fields'][$key]['value'] = $listing->post_title;
									break;
								case 'listing_description':
									$this->fields[$group_key]['fields'][$key]['value'] = $listing->post_content;
									break;
								// Handle all taxonomy fields dynamically using the field's taxonomy property
								case (isset($field['taxonomy']) && !empty($field['taxonomy'])):
									$this->fields[$group_key]['fields'][$key]['value'] = wp_get_object_terms($listing->ID, $field['taxonomy'], array('fields' => 'ids'));
									break;

								case (substr($key, 0, 4) == 'tax-'):
									$tax = substr($key, 4);
									$this->fields[$group_key]['fields'][$key]['value'] =  wp_get_object_terms($listing->ID, $tax, array('fields' => 'ids'));

									break;

								case '_opening_hours':

									$days = listeo_get_days();
									$opening_hours = array();
									foreach ($days as $d_key => $value) {
										$value_day = get_post_meta($listing->ID, '_' . $d_key . '_opening_hour', true);
										if ($value_day) {
											$opening_hours[$d_key . '_opening'] = $value_day;
										}
										$value_day = get_post_meta($listing->ID, '_' . $d_key . '_closing_hour', true);
										if ($value_day) {
											$opening_hours[$d_key . '_closing'] = $value_day;
										}
									}

									$this->fields[$group_key]['fields'][$key]['value'] = $opening_hours;
									break;
								case 'region':
									$this->fields[$group_key]['fields'][$key]['value'] = wp_get_object_terms($listing->ID, 'region', array('fields' => 'ids'));
									break;

								default:

									if (isset($this->fields[$group_key]['fields'][$key]['multi']) && $this->fields[$group_key]['fields'][$key]['multi']) {
										$this->fields[$group_key]['fields'][$key]['value'] = get_post_meta($listing->ID, $key, false);
									} else {
										$this->fields[$group_key]['fields'][$key]['value'] = get_post_meta($listing->ID, $key, true);
									}
									//$this->fields[ $group_key ]['fields'][ $key ]['value'] = get_post_meta( $listing->ID, $key, true );
									if (isset($this->fields[$group_key]['fields'][$key]['type']) && $this->fields[$group_key]['fields'][$key]['type'] == 'checkboxes') {
										$this->fields[$group_key]['fields'][$key]['value'] = get_post_meta($listing->ID, $key, false);
									}
									break;
							}
						}
					}
				}
			} elseif (is_user_logged_in() && empty($_POST['submit_listing'])) {
				$this->fields = apply_filters('submit_listing_form_fields_get_user_data', $this->fields, get_current_user_id());
			}


			$template_loader->set_template_data(
				array(
					'action' 		=> $this->get_action(),
					'fields' 		=> $this->fields,
					'form'      	=> $this->form_name,
					'listing_edit' => $this->listing_edit,
					'listing_id'   => $this->get_listing_id(),
					'step'      	=> $this->get_step(),
					'submit_button_text' => apply_filters('submit_listing_form_submit_button_text', __('Preview', 'listeo_core'))
				)
			)->get_template_part('listing-submit');
		}
	}


	/**
	 * Handles the submission of form data.
	 */
	public function submit_handler()
	{
		// Posted Data

		try {
			// Init fields
			$this->init_fields();

			// Get posted values
			$values = $this->get_posted_fields();

			if (empty($_POST['submit_listing'])) {
				return;
			}

			// Validate required
			if (is_wp_error(($return = $this->validate_fields($values)))) {
				throw new Exception($return->get_error_message());
			}


			if (! is_user_logged_in()) {
				throw new Exception(__('You must be signed in to post a new listing.', 'listeo_core'));
			}

			// in $values array find value of key 'listing_title' and assign it to $post_title

			$post_title = searchForPostedValue('listing_title', $values);
			$post_content = searchForPostedValue('listing_description', $values);
			$product_id = searchForPostedValue('product_id', $values);


			// Add or update listing as a WoCommerce product and save product id to values
			// if(is_woocommerce_activated()){
			// 	$values['basic_info']['product_id'] = $this -> save_as_product($post_title,$post_content,$product_id);	
			// }
			// PRODUCT IS NOW CREATED WHEN FIRST BOOKING IS MADE - NOT ON SUBMIT

			$content = '';

			// //locate listing_description
			// foreach ($values as $section => $s_fields) {
			// 	foreach ($s_fields as $key => $value) {
			// 		if($key == 'listing_description') {
			// 			$content = $value;
			// 		}
			// 	}

			// }


			//Update the listing
			$this->save_listing($post_title, $post_content, $this->listing_id ? '' : 'preview', $values);


			$this->update_listing_data($values);

			// Successful, show next step
			$this->step++;
		} catch (Exception $e) {

			$this->add_error($e->getMessage());
			return;
		}
	}


	/**
	 * Handles the preview step form response.
	 */
	public function preview_handler()
	{


		if (! $_POST) {
			return;
		}


		if (! is_user_logged_in()) {
			throw new Exception(__('You must be signed in to post a new listing.', 'listeo_core'));
		}

		// Edit = show submit form again
		if (! empty($_POST['edit_listing'])) {
			$this->step--;
		}

		// Continue = change listing status then show next screen
		if (! empty($_POST['continue'])) {

			// Process any form data that might have been submitted during preview


			$listing = get_post($this->listing_id);

			if (in_array($listing->post_status, array('preview', 'expired'))) {
				// Reset expiry
				delete_post_meta($listing->ID, '_listing_expires');

				// Update listing listing
				$update_listing                  = array();
				$update_listing['ID']            = $listing->ID;
				if ($this->form_action == "editing") {
					//$update_listing['post_status'] = $listing->post_status;

					$update_listing['post_status']   = apply_filters('edit_listing_post_status', get_option('listeo_edit_listing_requires_approval') ? 'pending' : $listing->post_status, $listing);
				} elseif ($this->form_action == "continue") {
					// For continue action, check if we need package payment
					if (get_option('listeo_new_listing_requires_purchase') && !self::$is_user_package && self::$package_id) {
						// Purchasable package - set to pending_payment to trigger package processing
						$update_listing['post_status'] = 'pending_payment';
					} else {
						// No payment needed or user package - treat like normal submission
						$update_listing['post_status'] = apply_filters('submit_listing_post_status', get_option('listeo_new_listing_requires_approval') ? 'pending' : 'publish', $listing);
					}
				} else {
					//$update_listing['post_status']   = 'pending';
					$update_listing['post_status']   = apply_filters('submit_listing_post_status', get_option('listeo_new_listing_requires_approval') ? 'pending' : 'publish', $listing);
				}

				$update_listing['post_date']     = current_time('mysql');
				$update_listing['post_date_gmt'] = current_time('mysql', 1);
				$update_listing['post_author']   = get_current_user_id();


				wp_update_post($update_listing);

				// Ensure package features are applied during preview-to-publish transition


				// For continue action with package payment, process package here
				if ($this->form_action == "continue" && get_option('listeo_new_listing_requires_purchase')) {
					if (self::$package_id && !self::$is_user_package) {
						// Purchasable package - trigger payment process
						if (self::process_package(self::$package_id, self::$is_user_package, $this->listing_id)) {
							// process_package handles redirect to checkout
							return;
						}
					} elseif (self::$package_id && self::$is_user_package) {
						// User package - process immediately
						if (self::process_package(self::$package_id, self::$is_user_package, $this->listing_id)) {
							// Continue to done step
							$this->step++;
						}
					}
				}
			}

			$this->step++;
		}
	}

	/**
	 * Displays the final screen after a listing listing has been submitted.
	 */
	public function done()
	{


		do_action('listeo_core_listing_submitted', $this->listing_id);
		if ($this->form_action == "editing") {
			if (get_option('listeo_edit_listing_requires_approval')) {
				wp_update_post(array(
					'ID'    => $this->listing_id,
					'post_status'   =>  'pending'
				));
			}
			do_action('listeo_core_listing_edited', $this->listing_id);
		} elseif ($this->form_action == "continue") {
			// For continue action, trigger the normal submission complete action
			do_action('listeo_core_listing_submission_completed', $this->listing_id);
		}

		$template_loader = new Listeo_Core_Template_Loader;
		$template_loader->set_template_data(
			array(
				'listing' 	=>  get_post($this->listing_id),
				'id' 		=> 	$this->listing_id,
			)
		)->get_template_part('listing-submitted');
	}


	public function type($atts = array())
	{

		$template_loader = new Listeo_Core_Template_Loader;
		if (! is_user_logged_in()) {
			$template_loader->get_template_part('listing-sign-in');
			$template_loader->get_template_part('account/login-form');
		} else {

			$template_loader->set_template_data(
				array(
					'form'      		=> $this->form_name,
					'action' 			=> $this->get_action(),
					'listing_id'   		=> $this->get_listing_id(),
					'step'      		=> $this->get_step(),
					'submit_button_text' => __('Submit Listing', 'listeo_core'),
				)
			)->get_template_part('listing-submit-type');
		}
	}


	public function type_handler()
	{

		// Process the package unless we're doing this before a job is submitted

		$this->next_step();
	}


	public function choose_package($atts = array())
	{
		$template_loader = new Listeo_Core_Template_Loader;
		if (! is_user_logged_in()) {
			$template_loader->get_template_part('listing-sign-in');
			$template_loader->get_template_part('account/login-form');
		} else {
			$packages      = self::get_packages();

			$user_packages = listeo_core_user_packages(get_current_user_id());

			$template_loader->set_template_data(
				array(
					'packages' 		=> $packages,
					'user_packages' => $user_packages,
					'form'      	=> $this->form_name,
					'action' 		=> $this->get_action(),
					'listing_id'   => $this->get_listing_id(),
					'step'      	=> $this->get_step(),
					'submit_button_text' => __('Submit Listing', 'listeo_core'),
				)
			)->get_template_part('listing-submit-package');
		}
	}

	public function choose_package_handler()
	{

		// Validate Selected Package
		$validation = self::validate_package(self::$package_id, self::$is_user_package);

		// Error? Go back to choose package step.
		if (is_wp_error($validation)) {
			$this->add_error($validation->get_error_message());
			$this->set_step(array_search('package', array_keys($this->get_steps())));
			return false;
		}

		// Store selection in cookie
		wc_setcookie('chosen_package_id', self::$package_id);
		wc_setcookie('chosen_package_is_user_package', self::$is_user_package ? 1 : 0);

		// Also save package selection to listing meta so it survives cookie expiry
		if ($this->get_listing_id()) {
			if (self::$is_user_package) {
				update_post_meta($this->get_listing_id(), '_user_package_id', self::$package_id);
				// For user packages, also store the product ID for consistency
				$user_package = listeo_core_get_user_package(self::$package_id);
				if ($user_package) {
					update_post_meta($this->get_listing_id(), '_package_id', $user_package->get_product_id());
				}
			} else {
				update_post_meta($this->get_listing_id(), '_package_id', self::$package_id);
				delete_post_meta($this->get_listing_id(), '_user_package_id');
			}
		}

		// Process the package unless we're doing this before a job is submitted
		if ('process-package' === $this->get_step_key()) {
			// Product the package
			if (self::process_package(self::$package_id, self::$is_user_package, $this->get_listing_id())) {
				$this->next_step();
			}
		} else {
			$this->next_step();
		}
	}

	/**
	 * Validate package
	 *
	 * @param  int  $package_id
	 * @param  bool $is_user_package
	 * @return bool|WP_Error
	 */
	private static function validate_package($package_id, $is_user_package)
	{
		if (empty($package_id)) {
			return new WP_Error('error', __('Invalid Package', 'listeo_core'));
		} elseif ($is_user_package) {
			if (! listeo_core_package_is_valid(get_current_user_id(), $package_id)) {
				return new WP_Error('error', __('Invalid Package', 'listeo_core'));
			}
		} else {
			$package = wc_get_product($package_id);

			if (! $package->is_type('listing_package') && ! $package->is_type('listing_package_subscription')) {
				return new WP_Error('error', __('Invalid Package', 'listeo_core'));
			}
		}
		return true;
	}


	/**
	 * Purchase a job package
	 *
	 * @param  int|string $package_id
	 * @param  bool       $is_user_package
	 * @param  int        $listing_id
	 * @return bool Did it work or not?
	 */
	private static function process_package($package_id, $is_user_package, $listing_id)
	{
		// Make sure the job has the correct status
		//do_action( 'listeo_core_listing_submitted', $listing_id );
		if ('preview' === get_post_status($listing_id)) {
			// Update job listing
			$update_job                  = array();
			$update_job['ID']            = $listing_id;
			$update_job['post_status']   = 'pending_payment';
			$update_job['post_date']     = current_time('mysql');
			$update_job['post_date_gmt'] = current_time('mysql', 1);
			$update_job['post_author']   = get_current_user_id();

			wp_update_post($update_job);
		}

		if ($is_user_package) {
			$user_package = listeo_core_get_user_package($package_id);
			$package      = wc_get_product($user_package->get_product_id());

			// Give listing the package attributes
			update_post_meta($listing_id, '_duration', $user_package->get_duration());
			update_post_meta($listing_id, '_featured', $user_package->is_featured() ?  'on' : 0);
			update_post_meta($listing_id, '_package_id', $user_package->get_product_id());
			update_post_meta($listing_id, '_user_package_id', $package_id);


			// Approve the listing
			if (in_array(get_post_status($listing_id), array('pending_payment', 'expired'))) {
				listeo_core_approve_listing_with_package($listing_id, get_current_user_id(), $package_id);
			}
			if (isset($_GET["action"]) && $_GET["action"] == 'renew') {
				$post_types_expiry = new Listeo_Core_Post_Types;
				$post_types_expiry->set_expiry(get_post($listing_id));
			}

			// Clear cookie so a future submission cannot reuse a stale user_package row id
			// (rows can be recreated/relinked by the subscription self-heal in
			// class-listeo-core-paid-subscriptions.php, leaving the old id invalid).
			wc_setcookie('chosen_package_id', '', time() - HOUR_IN_SECONDS);
			wc_setcookie('chosen_package_is_user_package', '', time() - HOUR_IN_SECONDS);

			return true;
		} elseif ($package_id) {
			$package = wc_get_product($package_id);


			$is_featured = $package->is_listing_featured();


			// Give listing the package attributes
			update_post_meta($listing_id, '_duration', $package->get_duration());
			update_post_meta($listing_id, '_featured', $is_featured ? 'on' : 0);
			update_post_meta($listing_id, '_package_id', $package_id);
			delete_post_meta($listing_id, '_user_package_id');
			//_opening_hours_status	
			if (isset($_GET["action"]) && $_GET["action"] == 'renew') {
				update_post_meta($listing_id, '_package_change', $package_id);
			}
			// Clear cookie
			wc_setcookie('chosen_package_id', '', time() - HOUR_IN_SECONDS);
			wc_setcookie('chosen_package_is_user_package', '', time() - HOUR_IN_SECONDS);


			// Remove any pre-existing listing packages from the cart before adding the
			// new one. Otherwise an abandoned package from an earlier listing flow stays
			// in the cart, and the checkout transition can end on "Cart is empty" (the
			// orphan item gets stripped on session load and takes the redirect with it).
			// One listing, one package — match what the listing submit flow expects.
			if (function_exists('WC') && WC()->cart) {
				foreach (WC()->cart->get_cart() as $existing_key => $existing_item) {
					if (! empty($existing_item['data']) && $existing_item['data'] instanceof WC_Product
						&& ($existing_item['data']->is_type('listing_package') || $existing_item['data']->is_type('listing_package_subscription'))) {
						WC()->cart->remove_cart_item($existing_key);
					}
				}
			}

			// Add package to the cart
			WC()->cart->add_to_cart($package_id, 1, '', '', array(
				'listing_id' => $listing_id,
			));

			wc_add_to_cart_message($package_id);


			// Redirect to checkout page
			wp_redirect(get_permalink(wc_get_page_id('checkout')));
			exit;
		} // End if().
	}


	/**
	 * Adds an error.
	 *
	 * @param string $error The error message.
	 */
	public function add_error($error)
	{
		$this->errors[] = $error;
	}

	/**
	 * Gets post data for fields.
	 *
	 * @return array of data
	 */
	protected function get_posted_fields()
	{
		$this->init_fields();

		$values = array();

		foreach ($this->fields as $group_key => $group_fields) {

			// Skip empty sections to avoid processing issues
			if (empty($group_fields['fields']) || !is_array($group_fields['fields'])) {
				continue;
			}

			foreach ($group_fields['fields'] as $key => $field) {
				// Skip fields without a type defined
				if (empty($field['type'])) {
					continue;
				}
				// Get the value
				$field_type = str_replace('-', '_', $field['type']);

				// Debug logging to track which handler is used

				if ($handler = apply_filters("listeo_core_get_posted_{$field_type}_field", false)) {

					$values[$group_key][$key] = call_user_func($handler, $key, $field);
				} elseif (method_exists($this, "get_posted_{$field_type}_field")) {

					$values[$group_key][$key] = call_user_func(array($this, "get_posted_{$field_type}_field"), $key, $field);
				} else {

					$values[$group_key][$key] = $this->get_posted_field($key, $field);
				}


				// Set fields value
				$this->fields[$group_key]['fields'][$key]['value'] = $values[$group_key][$key];
			}
		}


		return $values;
	}


	/**
	 * Gets the value of a posted field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string|array
	 */
	protected function get_posted_field($key, $field)
	{
		$value = isset($_POST[$key]) ? $this->sanitize_posted_field($_POST[$key]) : '';

		// Convert 'none' value back to empty string for select fields
		if ($value === 'none' && isset($field['type']) && $field['type'] === 'select') {
			$value = '';
		}

		return $value;
	}

	/**
	 * Navigates through an array and sanitizes the field.
	 *
	 * @param array|string $value The array or string to be sanitized.
	 * @return array|string $value The sanitized array (or string from the callback).
	 */
	protected function sanitize_posted_field($value)
	{
		// Santize value
		$value = is_array($value) ? array_map(array($this, 'sanitize_posted_field'), $value) : sanitize_text_field(stripslashes(trim($value)));

		return $value;
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_textarea_field($key, $field)
	{
		return isset($_POST[$key]) ? wp_kses_post(trim(stripslashes($_POST[$key]))) : '';
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_wp_editor_field($key, $field)
	{
		return $this->get_posted_textarea_field($key, $field);
	}


	protected function get_posted_pricing_field($key, $field)
	{


		require_once(ABSPATH . 'wp-admin/includes/image.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		$file_urls       = array();

		$my_files_array = $_FILES[$key];
		if (!empty($my_files_array)) {


			foreach ($my_files_array['name'] as $x => $xvalue) {

				foreach ($xvalue as $y => $yvalue) {
					foreach ($yvalue as $z => $file) {
						if (!empty($file['cover'])) {

							if (!isset($my_files_array['name'][$x][$y][$z]['cover'])) {
								continue;
							}
							$file_data = $my_files_array;
							$type              = wp_check_filetype($file_data['name'][$x][$y][$z]['cover']); // Map mime type to one WordPress recognises
							$file_to_upload = array(
								'name'     => $file_data['name'][$x][$y][$z]['cover'],
								'type'     => $type['type'],
								'tmp_name' => $file_data['tmp_name'][$x][$y][$z]['cover'],
								'error'    => $file_data['error'][$x][$y][$z]['cover'],
								'size'     => $file_data['size'][$x][$y][$z]['cover']
							);

							$_FILES = array('upload' => $file_to_upload);
							foreach ($_FILES as $file => $array) {

								$attachment_id = media_handle_upload($file, 0);
							}

							// These files need to be included as dependencies when on the front end.
							if (is_wp_error($attachment_id)) {
								//	echo "Error adding file";
							} else {
								$file_urls[] = $attachment_id;
								$_POST[$key][$x][$y][$z]['cover'] = $attachment_id;
							}
						}
					}
				}
			}
		}
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		$sanitized = $this->sanitize_posted_field( $_POST[ $key ] );

		// `sanitize_posted_field()` recursively applies `sanitize_text_field()`,
		// which strips newlines/tabs. The menu element `description` is a
		// textarea, so re-sanitize it with `sanitize_textarea_field()` to keep
		// line breaks while still stripping tags.
		if ( is_array( $sanitized ) ) {
			foreach ( $sanitized as $i => $section ) {
				if ( empty( $section['menu_elements'] ) || ! is_array( $section['menu_elements'] ) ) {
					continue;
				}
				foreach ( $section['menu_elements'] as $z => $el ) {
					if ( isset( $_POST[ $key ][ $i ]['menu_elements'][ $z ]['description'] ) ) {
						$sanitized[ $i ]['menu_elements'][ $z ]['description'] = sanitize_textarea_field(
							wp_unslash( $_POST[ $key ][ $i ]['menu_elements'][ $z ]['description'] )
						);
					}
				}
			}
		}

		return $sanitized;
	}


	protected function get_posted_file_field($key, $field)
	{

		$file = $this->upload_file($key, $field);


		if (! $file) {
			$file = $this->get_posted_field('current_' . $key, $field);
		} elseif (is_array($file)) {
			$file = array_filter(array_merge($file, (array) $this->get_posted_field('current_' . $key, $field)));
		}

		return $file;
	}

	/**
	 * Updates or creates a listing listing from posted data.
	 *
	 * @param  string $post_title
	 * @param  string $post_content
	 * @param  string $status
	 * @param  array  $values
	 * @param  bool   $update_slug
	 */
	protected function save_listing($post_title, $post_content, $status = 'preview', $values = array(), $update_slug = true)
	{
		$listing_data = array(
			'post_title'     => $post_title,
			'post_content'   => $post_content,
			'post_type'      => 'listing',
			'comment_status' => 'open'
		);

		if ($update_slug) {
			$listing_slug   = array();

			$listing_slug[]            = $post_title;
			$listing_data['post_name'] = sanitize_title(implode('-', $listing_slug));
		}

		if ($status && $this->form_action != "editing") {
			$listing_data['post_status'] = $status;
		} elseif ($this->form_action == "editing" && $this->listing_id) {
			// When editing a rejected listing, change status to pending for review
			$current_post = get_post($this->listing_id);
			if ($current_post && $current_post->post_status === 'rejected') {
				$listing_data['post_status'] = 'pending';
				// Clear the rejection reason since the listing is being resubmitted
				delete_post_meta($this->listing_id, '_listing_rejection_reason');
			}
		}

		$listing_data = apply_filters('submit_listing_form_save_listing_data', $listing_data, $post_title, $post_content, $status, $values);

		if ($this->listing_id) {
			$listing_data['ID'] = $this->listing_id;
			wp_update_post($listing_data);
		} else {
			$this->listing_id = wp_insert_post($listing_data);

			if (! headers_sent()) {
				$submitting_key = uniqid();

				setcookie('listeo_core-submitting-listing-id', $this->listing_id, false, COOKIEPATH, COOKIE_DOMAIN, false);
				setcookie('listeo_core-submitting-listing-key', $submitting_key, false, COOKIEPATH, COOKIE_DOMAIN, false);

				update_post_meta($this->listing_id, '_submitting_key', $submitting_key);
			}
		}
	}

	/**
	 * Sets listing meta and terms based on posted values.
	 *
	 * @param  array $values
	 */
	protected function update_listing_data($values)
	{
		// Set defaults

		// Capture the old place ID before any updates
		$this->old_place_id = get_post_meta($this->listing_id, '_place_id', true);

		$maybe_attach = array();
		// Check if not availability dates are sended and then set them as booking reservations
		if (! empty($values['availability_calendar']['_availability'])) {

			$bookings = new Listeo_Core_Bookings_Calendar;

			// set array only with dates when listing is not avalible
			$dates = array_filter(explode("|", $values['availability_calendar']['_availability']['dates']));

			// Strip past dates before processing
			$yesterday = strtotime('-1 day');
			$dates = array_filter($dates, function($date) use ($yesterday) {
				return strtotime($date) >= $yesterday;
			});

			if (! empty($dates)) {

				$bookings::update_reservations($this->listing_id, $dates);
			} else {

				$bookings::delete_bookings(array(
					'listing_id' => $this->listing_id,
					'owner_id' => get_current_user_id(),
					'type' => 'reservation',
					'comment' => 'owner reservations'
				));
			}

			// set array only with dates when we have special prices for booking
			$special_prices = json_decode($values['availability_calendar']['_availability']['price'], true);

			if (! empty($special_prices)) $bookings::update_special_prices($this->listing_id, $special_prices);
		}
		// Loop fields and save meta and term data
		foreach ($this->fields as $group_key => $group_fields) {

			// Skip empty sections to avoid processing issues
			if (empty($group_fields['fields']) || !is_array($group_fields['fields'])) {
				continue;
			}

			foreach ($group_fields['fields'] as $key => $field) {

				// Save opening hours to array in post meta
				if ($key == '_opening_hours') {
					$open_hours = $this->posted_hours_to_array($key, $field);

					if ($open_hours) update_post_meta($this->listing_id,  '_opening_hours', json_encode($open_hours));
					else update_post_meta($this->listing_id,  '_opening_hours', json_encode(false));
					continue;
				}

				//			if field key is gallery, attach every gallery image to listing
				if ($key == '_gallery') {

					$gallery = $values[$group_key][$key];

					if (! empty($gallery)) {
						foreach ($gallery as $gkey => $gvalue) {
							// update parent post of $key attachment with ID of listing

							wp_update_post(array('ID' => $gkey, 'post_parent' => $this->listing_id));
						}
					}
				}

				// Save taxonomies
				if (! empty($field['taxonomy'])) {
					if (is_array($values[$group_key][$key])) {
						$new_tax_array = array_map('intval', $values[$group_key][$key]);
						/*TODO - fix the damn region string*/
						wp_set_object_terms($this->listing_id, $new_tax_array, $field['taxonomy'], false);
					} else {
						wp_set_object_terms($this->listing_id, array(intval($values[$group_key][$key])), $field['taxonomy'], false);
					}

					//  logo is a featured image
				} elseif ('thumbnail' === $key) {
					$attachment_id = is_numeric($values[$group_key][$key]);
					if (empty($attachment_id)) {
						delete_post_thumbnail($this->listing_id);
					} else {
						set_post_thumbnail($this->listing_id, $attachment_id);
					}
				} else {


					if ((isset($field['multi']) && ($field['multi'] == true || $field['multi'] == 'on')) || (isset($field['type']) && $field['type'] == 'checkboxes')) {

						delete_post_meta($this->listing_id, $key);

						$field_value = isset($values[$group_key][$key]) ? $values[$group_key][$key] : null;


						// Handle different data formats that might be received
						if (is_array($field_value)) {
							// Direct array - ideal case
							foreach ($field_value as $value) {
								if (!empty($value)) {
									add_post_meta($this->listing_id, $key, sanitize_text_field($value));
								}
							}
						} elseif (is_string($field_value) && is_serialized($field_value)) {
							// Serialized array - unserialize first
							$unserialized = unserialize($field_value);
							if (is_array($unserialized)) {

								foreach ($unserialized as $value) {
									if (!empty($value)) {
										add_post_meta($this->listing_id, $key, sanitize_text_field($value));
									}
								}
							} else {
								// Fallback: treat as single value
								if (!empty($field_value)) {
									add_post_meta($this->listing_id, $key, sanitize_text_field($field_value));
								}
							}
						} elseif (is_string($field_value) && strpos($field_value, ',') !== false) {
							// Comma-separated string - split it
							$values_array = array_map('trim', explode(',', $field_value));

							foreach ($values_array as $value) {
								if (!empty($value)) {
									add_post_meta($this->listing_id, $key, sanitize_text_field($value));
								}
							}
						} else {
							// Single value fallback
							if (!empty($field_value)) {
								add_post_meta($this->listing_id, $key, sanitize_text_field($field_value));
							}
						}
					} else {
						// Event date timestamps are now handled by the save_post hook for consistency
						// This eliminates race conditions and ensures uniform timestamp creation
						if (!isset($values[$group_key][$key])) {
							continue;
						}
						$field_value = $values[$group_key][$key];

						// Filter empty entries from repeatable fields before saving
						if (isset($field['type']) && $field['type'] === 'repeatable' && is_array($field_value)) {
							$field_value = array_values(array_filter($field_value, function($entry) {
								if (!is_array($entry)) {
									return !empty($entry);
								}
								foreach ($entry as $value) {
									if (is_array($value)) {
										if (!empty(array_filter($value, function($v) { return $v !== '' && $v !== null; }))) {
											return true;
										}
									} elseif ($value !== '' && $value !== null) {
										return true;
									}
								}
								return false;
							}));
						}

						// Repeatable fees engine — the frontend submit
						// form only POSTs title/price/description per
						// row, but rows configured via the admin CMB2
						// (or LBP resource form) may carry richer schema
						// (id, type, frequency, optional, conditions).
						// Merge by stable id so an owner resaving via
						// the frontend doesn't silently strip the rest.
						if ( '_mandatory_fees' === $key && is_array( $field_value ) ) {
							$existing = get_post_meta( $this->listing_id, '_mandatory_fees', true );
							$by_id    = array();
							if ( is_array( $existing ) ) {
								foreach ( $existing as $row ) {
									if ( is_array( $row ) && ! empty( $row['id'] ) ) {
										$by_id[ $row['id'] ] = $row;
									}
								}
							}
							$merged = array();
							foreach ( $field_value as $row ) {
								if ( ! is_array( $row ) ) {
									continue;
								}
								$row_id = isset( $row['id'] ) ? $row['id'] : '';
								if ( '' !== $row_id && isset( $by_id[ $row_id ] ) ) {
									// Overlay posted keys on top of the
									// stored row to keep type/frequency/
									// optional/conditions intact.
									$row = array_merge( $by_id[ $row_id ], $row );
								}
								$merged[] = $row;
							}
							$field_value = $merged;
						}

						update_post_meta($this->listing_id, $key, $field_value);
					}

					//update_post_meta( $this->listing_id, $key, $values[ $group_key ][ $key ] );	


					// Handle attachments


					if (isset($field['type']) && 'file' === $field['type']) {
						if (is_array($values[$group_key][$key])) {
							foreach ($values[$group_key][$key] as $file_url) {
								$maybe_attach[] = $file_url;
							}
						} else {
							$maybe_attach[] = $values[$group_key][$key];
						}
					}

					$maybe_attach = array_filter($maybe_attach);


					// if ( 'file' === $field['type'] ) {
					// 	$attachment_id = is_numeric( $values[ $group_key ][ $key ] ) ? absint( $values[ $group_key ][ $key ] ) : $this->create_attachment( $values[ $group_key ][ $key ] );

					// 	update_post_meta( $this->listing_id, $key.'_id', $attachment_id  );


					// 	// if ( is_array( $values[ $group_key ][ $key ] ) ) {
					// 	// 	foreach ( $values[ $group_key ][ $key ] as $file_url ) {
					// 	// 		$maybe_attach[] = $file_url;
					// 	// 	}
					// 	// } else {
					// 	// 	$maybe_attach[] = $values[ $group_key ][ $key ];
					// 	// }
					// }
				}
			}
		}

		// Handle attachments.
		if (count($maybe_attach)) {
			// Get attachments.
			$attachments     = get_posts('post_parent=' . $this->listing_id . '&post_type=attachment&fields=ids&numberposts=-1');
			$attachment_urls = [];

			// Loop attachments already attached to the job.
			foreach ($attachments as $attachment_id) {
				$attachment_urls[] = wp_get_attachment_url($attachment_id);
			}

			foreach ($maybe_attach as $attachment_url) {
				if (!in_array(
					$attachment_url,
					$attachment_urls,
					true
				)) {
					$this->create_attachment($attachment_url);
				}
			}
		}


		// save listing type
		update_post_meta($this->listing_id, '_listing_type', $this->listing_type);

		// $maybe_attach = array_filter( $maybe_attach );

		// // Handle attachments
		// if ( sizeof( $maybe_attach ) && apply_filters( 'listeo_core_attach_uploaded_files', true ) ) {
		// 	// Get attachments
		// 	$attachments     = get_posts( 'post_parent=' . $this->listing_id . '&post_type=attachment&fields=ids&post_mime_type=image&numberposts=-1' );
		// 	$attachment_urls = array();

		// 	// Loop attachments already attached to the listing
		// 	foreach ( $attachments as $attachment_id ) {
		// 		$attachment_urls[] = wp_get_attachment_url( $attachment_id );
		// 	}

		// 	foreach ( $maybe_attach as $attachment_url ) {
		// 		if ( ! in_array( $attachment_url, $attachment_urls ) ) {
		// 			$this->create_attachment( $attachment_url );
		// 		}
		// 	}
		// }

		// And user meta to save time in future

		$switchable_sections = ['opening_hours', 'booking', 'slots', 'menu', 'faq'];

		$fields = $this->fields;
		foreach ($switchable_sections as $section) {

			if (isset($fields[$section]) && isset($fields[$section]['onoff']) && $fields[$section]['onoff']) {
				$status_field = "_{$section}_status";

				$default_on = isset($fields[$section]['onoff_state']) && $fields[$section]['onoff_state'] == 'on';

				// Check if the status checkbox is present in POST data (will be if checked)
				if (isset($_POST[$status_field])) {

					update_post_meta($this->listing_id, $status_field, 'on');
				} else {
					// If this is a new listing and default is on
					if (!$this->listing_id && $default_on) {
						update_post_meta($this->listing_id, $status_field, 'on');
					} else {
						// Either existing listing or default is off
						delete_post_meta($this->listing_id, $status_field);
					}
				}
			}
		}

		// Handle place ID changes - clear Google reviews data when place ID is removed or changed
		$this->handle_place_id_changes($values);

		do_action('listeo_core_update_listing_data', $this->listing_id, $values);
	}

	/**
	 * Handle place ID changes and clear Google reviews data when necessary
	 * 
	 * @param array $values The submitted form values
	 */
	private function handle_place_id_changes($values)
	{
		// Use the captured old place ID
		$old_place_id = $this->old_place_id;

		// Get the new place ID from the form submission
		$new_place_id = '';

		// Look for _place_id in the submitted values
		foreach ($values as $group_key => $group_values) {
			if (isset($group_values['_place_id'])) {
				$new_place_id = sanitize_text_field($group_values['_place_id']);
				break;
			}
		}

		// If place ID was removed or changed, clear Google reviews data
		if ($old_place_id !== $new_place_id) {
			// Clear Google reviews transient cache
			delete_transient('listeo_reviews_' . $this->listing_id);

			// If place ID was removed (empty), clear all Google-related permanent data
			if (empty($new_place_id)) {
				delete_post_meta($this->listing_id, '_google_rating');
				delete_post_meta($this->listing_id, '_google_review_count');
				delete_post_meta($this->listing_id, '_google_last_updated');

				// Force recalculate combined rating without Google data
				// Clear existing combined rating to ensure fresh calculation
				delete_post_meta($this->listing_id, '_combined_rating');
				delete_post_meta($this->listing_id, '_combined_review_count');

				$reviews_instance = Listeo_Core_Reviews::instance();
				if (method_exists($reviews_instance, 'get_combined_rating')) {
					$new_combined_rating = $reviews_instance->get_combined_rating($this->listing_id);
				}

				// Log the action for debugging

			} else {
				// Place ID changed - clear cache to force fresh fetch next time
				// But keep permanent data until fresh data is fetched

			}
		}
	}


	/**
	 * Displays preview of listing Listing.
	 */
	public function preview()
	{
		global $post, $listing_preview;

		if ($this->listing_id) {
			$listing_preview       = true;
			$post              = get_post($this->listing_id);
			$post->post_status = 'preview';

			setup_postdata($post);

			$template_loader = new Listeo_Core_Template_Loader;
			$template_loader->set_template_data(
				array(
					'action' 		=> $this->get_action(),
					'fields' 		=> $this->fields,
					'form'      	=> $this->form_name,
					'post'      	=> $post,
					'listing_id'   => $this->get_listing_id(),
					'step'      	=> $this->get_step(),
					'submit_button_text' => apply_filters('submit_listing_form_preview_button_text', __('Submit', 'listeo_core'))
				)
			)->get_template_part('listing-preview');

			wp_reset_postdata();
		}
	}


	protected function get_posted_hours_field($key, $field)
	{

		$values = array();
		if ($key == '_opening_hours') {
			$days = listeo_get_days();
			foreach ($days as $d_key => $value) {
				if (isset($_POST['opening_hours_' . $d_key])) {
					$values['_opening_hours_' . $d_key] =  $_POST['opening_hours_' . $d_key];
				}
			}
		}

		return $values;
	}


	protected function posted_hours_to_array($key, $field)
	{

		$values = array();
		if ($key == '_opening_hours') {

			$days = listeo_get_days();
			$int = 0;
			$is_empty = true;

			foreach ($days as $d_key => $value) {
				if (isset($_POST['_' . $d_key . '_opening_hour'])) {
					$values[$int]['opening'] =  $_POST['_' . $d_key . '_opening_hour'];
					$values[$int]['closing'] =  $_POST['_' . $d_key . '_closing_hour'];
					$int++;

					// check if there are opened days
					if (
						$_POST['_' . $d_key . '_opening_hour'] != 'Closed' &&
						$_POST['_' . $d_key . '_closing_hour'] != 'Closed'
					) $is_empty = false;
				}
			}
		}

		// return false if all days is closed
		if ($is_empty) return false;

		return $values;
	}

	protected function get_posted_term_checkboxes_field($key, $field)
	{

		if (isset($_POST['tax_input']) && isset($_POST['tax_input'][$field['taxonomy']])) {
			return array_map('absint', $_POST['tax_input'][$field['taxonomy']]);
		} else {
			return array();
		}
	}

	/**
	 * Gets the value of a posted select_multiple field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_select_multiple_field($key, $field)
	{
		$value = isset($_POST[$key]) ? $_POST[$key] : array();

		// Debug logging

		// Ensure it's an array
		if (! is_array($value)) {
			// If it's a serialized string, unserialize it
			if (is_string($value) && is_serialized($value)) {
				$unserialized = unserialize($value);
				if (is_array($unserialized)) {
					$value = $unserialized;
				} else {
					$value = array($value);
				}
			} else {
				$value = array($value);
			}
		}

		// Sanitize each value
		$value = array_map('sanitize_text_field', $value);

		// Remove empty values
		$value = array_filter($value, function ($v) {
			return !empty($v);
		});


		return $value;
	}

	/**
	 * Gets the value of a posted multicheck_split field (same as select_multiple).
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_multicheck_split_field($key, $field)
	{
		return $this->get_posted_select_multiple_field($key, $field);
	}

	/**
	 * Gets the value of a posted checkboxes field (converted from multicheck_split).
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_checkboxes_field($key, $field)
	{
		return $this->get_posted_select_multiple_field($key, $field);
	}

	/**
	 * Gets the value of a posted select field that has multi=on (converted from select_multiple).
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array|string
	 */
	protected function get_posted_select_field($key, $field)
	{
		// Check if this is a multi-select (converted from select_multiple)
		if (isset($field['multi']) && $field['multi'] == 'on') {

			return $this->get_posted_select_multiple_field($key, $field);
		}

		// Regular select field
		$value = isset($_POST[$key]) ? $this->sanitize_posted_field($_POST[$key]) : '';

		// Convert 'none' value back to empty string for select fields
		if ($value === 'none') {
			$value = '';
		}

		return $value;
	}


	function enable_paid_listings($steps)
	{
		// 		// check if user has any packages
		global $wpdb;

		$user_id = get_current_user_id();
		$user_id = get_current_user_id();
		$packages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE user_id = %d AND ( package_count < package_limit OR package_limit = 0 );", $user_id), OBJECT_K);

		if (get_option('listeo_new_listing_requires_purchase') && get_option('listeo_skip_package_if_user_has_one') && sizeof($packages) > 0) {
			$steps['process-package'] = array(
				'name'     => '',
				'view'     => false,
				'handler'  => array($this, 'choose_package_handler'),
				'priority' => 25,
			);
			return $steps;
		}


		if (get_option('listeo_new_listing_requires_purchase') && !isset($_GET["action"]) || isset($_GET["action"]) && $_GET["action"] == 'renew') {

			/*
		if(get_option('listeo_core_listing_submit_option', 'listeo_core_new_listing_requires_purchase' ) && !isset($_GET["action"])){*/


			$steps['package'] = array(
				'name'     => __('Choose a package', 'listeo_core'),
				'view'     => array($this, 'choose_package'),
				'handler'  => array($this, 'choose_package_handler'),
				'priority' => 5,
			);
			$steps['process-package'] = array(
				'name'     => '',
				'view'     => false,
				'handler'  => array($this, 'choose_package_handler'),
				'priority' => 25,
			);
		}
		return $steps;
	}

	/**
	 * Gets step key from outside of the class.
	 *
	 * @since 1.24.0
	 * @param string|int $step
	 * @return string
	 */
	public function get_step_key($step = '')
	{
		if (! $step) {
			$step = $this->step;
		}
		$keys = array_keys($this->steps);
		return isset($keys[$step]) ? $keys[$step] : '';
	}


	/**
	 * Gets steps from outside of the class.
	 *
	 * @since 1.24.0
	 */
	public function get_steps()
	{
		return $this->steps;
	}

	/**
	 * Gets step from outside of the class.
	 */
	public function get_step()
	{
		return $this->step;
	}


	/**
	 * Decreases step from outside of the class.
	 */
	public function previous_step()
	{
		$this->step--;
	}

	/**
	 * Sets step from outside of the class.
	 *
	 * @since 1.24.0
	 * @param int $step
	 */
	public function set_step($step)
	{
		$this->step = absint($step);
	}

	/**
	 * Increases step from outside of the class.
	 */
	public function next_step()
	{
		$this->step++;
	}

	/**
	 * Displays errors.
	 */
	public function show_errors()
	{
		foreach ($this->errors as $error) {
			echo '<div class="notification closeable error listing-manager-error">' . wpautop($error, true) . '<a class="close"></a></div>';
		}
	}


	/**
	 * Gets the action (URL for forms to post to).
	 * As of 1.22.2 this defaults to the current page permalink.
	 *
	 * @return string
	 */
	public function get_action()
	{
		return esc_url_raw($this->action ? $this->action : wp_unslash($_SERVER['REQUEST_URI']));
	}

	/**
	 * Gets the submitted listing ID.
	 *
	 * @return int
	 */
	public function get_listing_id()
	{
		return absint($this->listing_id);
	}

	/**
	 * Sorts array by priority value.
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	protected function sort_by_priority($a, $b)
	{
		if ($a['priority'] == $b['priority']) {
			return 0;
		}
		return ($a['priority'] < $b['priority']) ? -1 : 1;
	}

	/**
	 * Calls the view handler if set, otherwise call the next handler.
	 *
	 * @param array $atts Attributes to use in the view handler.
	 */
	public function output($atts = array())
	{
		$step_key = $this->get_step_key($this->step);
		$this->show_errors();

		if ($step_key && is_callable($this->steps[$step_key]['view'])) {
			call_user_func($this->steps[$step_key]['view'], $atts);
		}
	}

	/**
	 * Returns the form content.
	 *
	 * @param string $form_name
	 * @param array  $atts Optional passed attributes
	 * @return string|null
	 */
	public function get_form($atts = array())
	{

		ob_start();
		$this->output($atts);
		return ob_get_clean();
	}

	/**
	 * This filter insures users only see their own media
	 */
	function filter_media($query)
	{
		// admins get to see everything
		if (! current_user_can('manage_options'))
			$query['author'] = get_current_user_id();
		return $query;
	}

	function change_page_title($title, $id = null)
	{

		if (is_page(get_option('submit_listing_page')) && in_the_loop()) {
			if ($this->form_action == "editing") {
				$title = esc_html__('Edit Listing', 'listeo_core');
			};
		}

		return $title;
	}


	/**
	 * Creates a file attachment.
	 *
	 * @param  string $attachment_url
	 * @return int attachment id
	 */
	protected function create_attachment($attachment_url)
	{
		include_once(ABSPATH . 'wp-admin/includes/image.php');
		include_once(ABSPATH . 'wp-admin/includes/media.php');

		$upload_dir     = wp_upload_dir();
		$attachment_url = str_replace(array($upload_dir['baseurl'], WP_CONTENT_URL, site_url('/')), array($upload_dir['basedir'], WP_CONTENT_DIR, ABSPATH), $attachment_url);

		if (empty($attachment_url) || ! is_string($attachment_url)) {
			return 0;
		}

		$attachment     = array(
			'post_title'   => get_the_title($this->listing_id),
			'post_content' => '',
			'post_status'  => 'inherit',
			'post_parent'  => $this->listing_id,
			'guid'         => $attachment_url
		);

		if ($info = wp_check_filetype($attachment_url)) {
			$attachment['post_mime_type'] = $info['type'];
		}

		$attachment_id = wp_insert_attachment($attachment, $attachment_url, $this->listing_id);

		if (! is_wp_error($attachment_id)) {
			wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $attachment_url));
			return $attachment_id;
		}

		return 0;
	}

	/**
	 * Return packages
	 *
	 * @param array $post__in
	 * @return array
	 */
	public static function get_packages($post__in = array())
	{
		return get_posts(array(
			'post_type'        => 'product',
			'posts_per_page'   => -1,
			'post__in'         => $post__in,
			'order'            => 'asc',
			'orderby'          => 'date',
			'suppress_filters' => false,
			'tax_query'        => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => array('listing_package', 'listing_package_subscription'),
					'operator' => 'IN',
				),
			),
			//'meta_query'       => WC()->query->get_meta_query(),
		));
	}

	/**
	 * Change initial job status
	 *
	 * @param string  $status
	 * @param WP_Post $job
	 * @return string
	 */
	public static function submit_listing_post_status($status, $listing)
	{
		if (get_option('listeo_new_listing_requires_purchase')) {
			switch ($listing->post_status) {
				case 'preview':
					return 'pending_payment';
					break;
				case 'expired':
					return 'expired';
					break;
				case 'rejected':
					return 'pending';
					break;
				default:
					return $status;
					break;
			}
		} else {
			// When editing a rejected listing, change status to pending for review
			if ($listing->post_status === 'rejected') {
				return 'pending';
			}
			return $status;
		}
	}

	/**
	 * Save or update current listing as WooCommerce product
	 *
	 * @return int $product_id number with product id associated with listing
	 *
	 */
	private function save_as_product($post_title, $post_content, $product_id)
	{

		//	$values = $this->get_posted_fields();

		//	$product_id = $values['basic_info']['product_id'];

		// basic listing informations will be added to listing
		$product = array(
			'post_author' => get_current_user_id(),
			'post_content' => $post_content,
			'post_status' => 'publish',
			'post_title' => $post_title,
			'post_parent' => '',
			'post_type' => 'product',
		);

		// add product if not exist
		if (! $product_id ||  get_post_type($product_id) != 'product') {

			// insert listing as WooCommerce product
			$product_id = wp_insert_post($product);
			wp_remove_object_terms($product_id, 'simple', 'product_type');
			wp_set_object_terms($product_id, 'listing_booking', 'product_type');

			wp_set_object_terms($product_id, 'exclude-from-catalog', 'product_visibility');
			wp_set_object_terms($product_id, 'exclude-from-search', 'product_visibility');
		} else {

			// update existing product
			$product['ID'] = $product_id;
			wp_update_post($product);
			wp_set_object_terms($product_id, 'listing_booking', 'product_type');
			wp_set_object_terms($product_id, 'exclude-from-catalog', 'product_visibility');
			wp_set_object_terms($product_id, 'exclude-from-search', 'product_visibility');
		}


		// set product category
		$term = get_term_by('name', apply_filters('listeo_default_product_category', 'Listeo booking'), 'product_cat', ARRAY_A);

		if (! $term) $term = wp_insert_term(
			apply_filters('listeo_default_product_category', 'Listeo booking'),
			'product_cat',
			array(
				'description' => __('Listings category', 'listeo-core'),
				'slug' => str_replace(' ', '-', apply_filters('listeo_default_product_category', 'Listeo booking'))
			)
		);

		wp_set_object_terms($product_id, $term['term_id'], 'product_cat');

		return $product_id;
	}


	/**
	 * Handles the uploading of files.
	 *
	 * @param string $field_key
	 * @param array  $field
	 * @throws Exception When file upload failed
	 * @return  string|array
	 */


	protected function upload_file($field_key, $field)
	{
		if (isset($_FILES[$field_key]) && ! empty($_FILES[$field_key]) && ! empty($_FILES[$field_key]['name'])) {

			$max_file_size_mb = get_option('listeo_max_filesize', 10);
			$max_file_size = $max_file_size_mb * 1024 * 1024;

			if (! empty($field['allowed_mime_types'])) {
				$allowed_mime_types = $field['allowed_mime_types'];
			} else {
				$allowed_mime_types = listeo_get_allowed_mime_types();
			}
			// Check file size for each file
			if (is_array($_FILES[$field_key]['size'])) {
				foreach ($_FILES[$field_key]['size'] as $file_size) {
					if ($file_size > $max_file_size) {
						throw new Exception('File size exceeds the maximum allowed limit of ' . $max_file_size_mb . 'MB.');
					}
				}
			} else {
				if ($_FILES[$field_key]['size'] > $max_file_size) {
					throw new Exception('File size exceeds the maximum allowed limit of ' . $max_file_size_mb . 'MB.');
				}
			}
			$file_urls       = array();
			$files_to_upload = listeo_prepare_uploaded_files($_FILES[$field_key]);

			foreach ($files_to_upload as $file_to_upload) {
				$uploaded_file = listeo_upload_file($file_to_upload, array(
					'file_key'           => $field_key,
					'allowed_mime_types' => $allowed_mime_types,
				));

				if (is_wp_error($uploaded_file)) {
					throw new Exception($uploaded_file->get_error_message());
				} else {
					$file_urls[] = $uploaded_file->url;
				}
			}

			if (! empty($field['multiple'])) {
				return $file_urls;
			} else {
				return current($file_urls);
			}
		}
	}

	/// get listing type based sections
	public function get_booking_section()
	{
		/**
		 * Filter the default state for booking section switch
		 *
		 * @param bool $default_state Whether the booking section should be enabled by default (false = off, true = on)
		 */
		$onoff_state = apply_filters('listeo_booking_section_default_state', false);

		$fields['booking'] = array(
			'title' 	=> __('Booking', 'listeo_core'),
			'class' 	=> 'booking-enable',
			'onoff'		=> true,
			'onoff_state' => $onoff_state ? 'on' : false,
			'icon' 		=> 'fa fa-calendar-check',
			'fields' 	=> array(
				'_booking_status' => array(
					'label'       => __('Booking status', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_booking_status',

				),
			)
		);
		return $fields;
	}

	public function get_event_section()
	{
		$fields['event'] = array(
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
		);
		return $fields;
	}



	public function get_availability_section()
	{
		$fields['availability_calendar'] = array(


			'title' 	=> __('Availability Calendar', 'listeo_core'),
			'icon' 		=> 'fa fa-calendar-check',
			'fields' 	=> array(

				'_availability' => array(
					'label'       => __('Click day in calendar to mark it as unavailable', 'listeo_core'),

					'name'       => '_availability_calendar',
					'type'        => 'calendar',
					'placeholder' => '',
					'class'		  => '',
					'priority'    => 1,
					'required'    => false,
				),

			),


		);
		return $fields;
	}

	public function get_slot_section()
	{
		/**
		 * Filter the default state for slots/availability section switch
		 *
		 * @param bool $default_state Whether the slots section should be enabled by default (false = off, true = on)
		 */
		$onoff_state = apply_filters('listeo_slots_section_default_state', false);

		$fields['slots'] = array(
			'title' 	=> __('Availability', 'listeo_core'),
			//'class' 	=> 'margin-top-40',
			'onoff'		=> true,
			'onoff_state' => $onoff_state ? 'on' : false,
			'icon' 		=> 'fa fa-calendar-check',
			'fields' 	=> array(
				'_slots_status' => array(
					'label'       => __('Slots status', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_slots_status',
				),
				'_slots' => array(
					'label'       => __('Time Slots', 'listeo_core'),
					'name'       => '_slots',
					'type'        => 'slots',
					'placeholder' => '',
					'class'		  => '',
					'priority'    => 1,
					'required'    => false,
				),

			),

		);
		return $fields;
	}


	public function get_menu_section()
	{
		/**
		 * Filter the default state for menu/pricing section switch
		 *
		 * @param bool $default_state Whether the menu section should be enabled by default (false = off, true = on)
		 */
		$onoff_state = apply_filters('listeo_menu_section_default_state', false);

		$fields['menu'] = array(
			'title' 	=> __('Pricing & Bookable Services', 'listeo_core'),
			//'class' 	=> 'margin-top-40',
			'onoff'		=> true,
			'onoff_state' => $onoff_state ? 'on' : false,
			'icon' 		=> 'sl sl-icon-book-open',
			'fields' 	=> array(
				'_menu_status' => array(
					'label'       => __('Menu status', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_menu_status',
				),
				'_menu' => array(
					'label'       => __('Pricing', 'listeo_core'),
					'name'       => '_menu',
					'type'        => 'pricing',
					'placeholder' => '',
					'class'		  => '',
					'priority'    => 1,
					'required'    => false,
				),
			),

		);
		return $fields;
	}

	public function get_opening_hours_section()
	{
		/**
		 * Filter the default state for opening hours section switch
		 *
		 * @param bool $default_state Whether the opening hours section should be enabled by default (false = off, true = on)
		 */
		$onoff_state = apply_filters('listeo_opening_hours_section_default_state', false);

		$fields['opening_hours'] = array(
			'title' 	=> __('Opening Hours', 'listeo_core'),
			//'class' 	=> 'margin-top-40',
			'onoff'		=> true,
			'onoff_state' => $onoff_state ? 'on' : false,
			'icon' 		=> 'sl sl-icon-clock',
			'fields' 	=> array(
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
				'_monday_opening_hour' => array(
					'label'       => __('Monday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_monday_opening_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_monday_closing_hour' => array(
					'label'       => __('Monday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_monday_closing_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_tuesday_opening_hour' => array(
					'label'       => __('Tuesday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_tuesday_opening_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_tuesday_closing_hour' => array(
					'label'       => __('Monday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_tuesday_closing_hour',
					'before_row' 	 => '',
					'priority'    => 9,
					'render_row_col' => '4'
				),

				'_wednesday_opening_hour' => array(
					'label'       => __('Wednesday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_wednesday_opening_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_wednesday_closing_hour' => array(
					'label'       => __('Wednesday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_wednesday_closing_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_thursday_opening_hour' => array(
					'label'       => __('Thursday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_thursday_opening_hour',
					'before_row' 	 => '',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_thursday_closing_hour' => array(
					'label'       => __('Thursday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_thursday_closing_hour',
					'before_row' 	 => '',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_friday_opening_hour' => array(
					'label'       => __('Friday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_friday_opening_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_friday_closing_hour' => array(
					'label'       => __('Friday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_friday_closing_hour',
					'before_row' 	 => '',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_saturday_opening_hour' => array(
					'label'       => __('saturday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_saturday_opening_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_saturday_closing_hour' => array(
					'label'       => __('saturday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_saturday_closing_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_sunday_opening_hour' => array(
					'label'       => __('sunday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_sunday_opening_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_sunday_closing_hour' => array(
					'label'       => __('sunday Opening Hour', 'listeo_core'),
					'type'        => 'skipped',
					'required'    => false,
					'name'        => '_sunday_closing_hour',
					'priority'    => 9,
					'render_row_col' => '4'
				),
				'_listing_timezone' => array(
					'label'       => __('Listing Timezone', 'listeo_core'),
					'type'        => 'timezone',
					'required'    => false,
					'name'        => '_listing_timezone',
				),

			),


		);
		return $fields;
	}

	public function get_service_fields()
	{
		$scale = get_option('listeo_scale', 'sq ft');

		$currency_abbr = get_option('listeo_currency');

		$currency = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

		$fields = array(
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
						'multi'    	  => true,
						'required'    => false,
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
					),

					'listing_feature' => array(
						'label'       	=> __('Other Features', 'listeo_core'),
						'type'        	=> 'term-checkboxes',
						'taxonomy'		=> 'listing_feature',
						'name'			=> 'listing_feature',
						'class'		  	 => 'select2-single',
						'default'    	 => '',
						'priority'    	 => 2,
						'required'    => false,
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

						'priority'    => 7,
						'render_row_col' => '6'
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
						'render_row_col' => '6'
					),
					'region' => array(
						'label'       => __('Region', 'listeo_core'),
						'type'        => 'term-select',
						'required'    => false,
						'name'        => 'region',
						'taxonomy'        => 'region',
						'placeholder' => '',
						'class'		  => '',

						'priority'    => 8,
						'render_row_col' => '3'
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

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_geolocation_lat' => array(
						'label'       => __('Latitude', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_geolocation_lat',
						'class'		  => '',
						'priority'    => 10,

						'render_row_col' => '3'
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
						'placeholder' => 'Description',
						'class'		  => '',
						'priority'    => 1,
						'required'    => true,
					),
					'_video' => array(
						'label'       => __('Video', 'listeo_core'),
						'type'        => 'text',
						'name'        => '_video',
						'required'    => false,
						'placeholder' => __('URL to oEmbed supported service', 'listeo_core'),
						'class'		  => '',
						'priority'    => 5,
					),

					'_phone' => array(
						'label'       => __('Phone', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_phone',
						'class'		  => '',
						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_website' => array(
						'label'       => __('Website', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_website',
						'class'		  => '',

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_email' => array(
						'label'       => __('E-mail', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_email',

						'class'		  => '',
						'priority'    => 10,
						'render_row_col' => '3'
					),
					'_email_contact_widget' => array(
						'label'       => __('Enable Contact Widget', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled listing will display Contact Form Widget that will send emails to this address', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_email_contact_widget',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3'
					),

					'_facebook' => array(
						'label'       => __('<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_facebook',
						'class'		  => 'fb-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_twitter' => array(
						'label'       => __('<i class="fa-brands fa-x-twitter"></i> X', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_twitter',
						'class'		  => 'twitter-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_youtube' => array(
						'label'       => __('<i class="fa fa-youtube-square"></i> YouTube', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_youtube',
						'class'		  => 'youtube-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_instagram' => array(
						'label'       => __('<i class="fa fa-instagram"></i> Instagram', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_instagram',
						'class'		  => 'instagram-input',
						'priority'    => 10,

						'render_row_col' => '4'
					),
					'_whatsapp' => array(
						'label'       => __('<i class="fa fa-whatsapp"></i> WhatsApp', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_whatsapp',
						'class'		  => 'whatsapp-input',
						'priority'    => 10,
						'render_row_col' => '4'
					),
					'_skype' => array(
						'label'       => __('<i class="fa fa-skype"></i> Skype', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_skype',
						'class'		  => 'skype-input',
						'priority'    => 10,
						'render_row_col' => '4'
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
					),

				),
			),

			'basic_prices' => array(
				'title'		=> __('Booking prices and settings', 'listeo_core'),
				//'class'		=> 'margin-top-40',
				'icon'		=> 'fa fa-money',
				'fields'	=> array(



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
						'render_row_col' => '6'

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
						'render_row_col' => '6'
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
						'render_row_col' => '6'

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
						'render_row_col' => '3'
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
						'render_row_col' => '3'
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
						'render_row_col' => '3'
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
						'render_row_col' => '3'
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
						'for_type'	  => 'service'
					),


					'_count_by_hour' => array(
						'label'       => __('Enable Price per Hour', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled regular price and weekend price will be multiplied by hours booked, requires End Hour field to be ON or time slot', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_count_by_hour',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3'
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
						'unit'		  => __('hours', 'listeo_core'),
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6'
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

					'_mandatory_fees' => array(
						'label'       => __('Mandatory Fees', 'listeo_core'),
						'type'        => 'repeatable',
						'tooltip'	  => __('Set mandatory fees that will always be added to total cost', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_mandatory_fees',
						'class'		  => '',
						'priority'    => 10,

						'render_row_col' => '6',
						'options'   => array(
							'title' => __('Title', 'listeo_core'),
							'price' => __('Price', 'listeo_core'),
						),
					),

				),
			),



		);
		return $fields;
	}



	public function get_rental_fields()
	{
		$scale = get_option('listeo_scale', 'sq ft');

		$currency_abbr = get_option('listeo_currency');

		$currency = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

		$fields = array(
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
						'multi'    	  => true,
						'required'    => false,
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
					),

					'listing_feature' => array(
						'label'       	=> __('Other Features', 'listeo_core'),
						'type'        	=> 'term-checkboxes',
						'taxonomy'		=> 'listing_feature',
						'name'			=> 'listing_feature',
						'class'		  	 => 'select2-single',
						'default'    	 => '',
						'priority'    	 => 2,
						'required'    => false,
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

						'priority'    => 7,
						'render_row_col' => '6'
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
						'render_row_col' => '6'
					),
					'region' => array(
						'label'       => __('Region', 'listeo_core'),
						'type'        => 'term-select',
						'required'    => false,
						'name'        => 'region',
						'taxonomy'        => 'region',
						'placeholder' => '',
						'class'		  => '',

						'priority'    => 8,
						'render_row_col' => '3'
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

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_geolocation_lat' => array(
						'label'       => __('Latitude', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_geolocation_lat',
						'class'		  => '',
						'priority'    => 10,

						'render_row_col' => '3'
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
						'placeholder' => 'Description',
						'class'		  => '',
						'priority'    => 1,
						'required'    => true,
					),
					'_video' => array(
						'label'       => __('Video', 'listeo_core'),
						'type'        => 'text',
						'name'        => '_video',
						'required'    => false,
						'placeholder' => __('URL to oEmbed supported service', 'listeo_core'),
						'class'		  => '',
						'priority'    => 5,
					),

					'_phone' => array(
						'label'       => __('Phone', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_phone',
						'class'		  => '',
						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_website' => array(
						'label'       => __('Website', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_website',
						'class'		  => '',

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_email' => array(
						'label'       => __('E-mail', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_email',

						'class'		  => '',
						'priority'    => 10,
						'render_row_col' => '3'
					),
					'_email_contact_widget' => array(
						'label'       => __('Enable Contact Widget', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled listing will display Contact Form Widget that will send emails to this address', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_email_contact_widget',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3'
					),

					'_facebook' => array(
						'label'       => __('<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_facebook',
						'class'		  => 'fb-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_twitter' => array(
						'label'       => __('<i class="fa-brands fa-x-twitter"></i> X', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_twitter',
						'class'		  => 'twitter-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_youtube' => array(
						'label'       => __('<i class="fa fa-youtube-square"></i> YouTube', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_youtube',
						'class'		  => 'youtube-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_instagram' => array(
						'label'       => __('<i class="fa fa-instagram"></i> Instagram', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_instagram',
						'class'		  => 'instagram-input',
						'priority'    => 10,

						'render_row_col' => '4'
					),
					'_whatsapp' => array(
						'label'       => __('<i class="fa fa-whatsapp"></i> WhatsApp', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_whatsapp',
						'class'		  => 'whatsapp-input',
						'priority'    => 10,
						'render_row_col' => '4'
					),
					'_skype' => array(
						'label'       => __('<i class="fa fa-skype"></i> Skype', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_skype',
						'class'		  => 'skype-input',
						'priority'    => 10,
						'render_row_col' => '4'
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
					),

				),
			),
			'opening_hours' => array(
				'title' 	=> __('Opening Hours', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'onoff'		=> true,
				'icon' 		=> 'sl sl-icon-clock',
				'fields' 	=> array(
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
					'_monday_opening_hour' => array(
						'label'       => __('Monday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_monday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_monday_closing_hour' => array(
						'label'       => __('Monday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_monday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_tuesday_opening_hour' => array(
						'label'       => __('Tuesday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_tuesday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_tuesday_closing_hour' => array(
						'label'       => __('Monday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_tuesday_closing_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),

					'_wednesday_opening_hour' => array(
						'label'       => __('Wednesday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_wednesday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_wednesday_closing_hour' => array(
						'label'       => __('Wednesday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_wednesday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_thursday_opening_hour' => array(
						'label'       => __('Thursday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_thursday_opening_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_thursday_closing_hour' => array(
						'label'       => __('Thursday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_thursday_closing_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_friday_opening_hour' => array(
						'label'       => __('Friday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_friday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_friday_closing_hour' => array(
						'label'       => __('Friday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_friday_closing_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_saturday_opening_hour' => array(
						'label'       => __('saturday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_saturday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_saturday_closing_hour' => array(
						'label'       => __('saturday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_saturday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_sunday_opening_hour' => array(
						'label'       => __('sunday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_sunday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_sunday_closing_hour' => array(
						'label'       => __('sunday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_sunday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_listing_timezone' => array(
						'label'       => __('Listing Timezone', 'listeo_core'),
						'type'        => 'timezone',
						'required'    => false,
						'name'        => '_listing_timezone',
					),

				),
			),
			'menu' => array(
				'title' 	=> __('Pricing & Bookable Services', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'onoff'		=> true,
				'icon' 		=> 'sl sl-icon-book-open',
				'fields' 	=> array(
					'_menu_status' => array(
						'label'       => __('Menu status', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_menu_status',
					),
					'_menu' => array(
						'label'       => __('Pricing', 'listeo_core'),
						'name'       => '_menu',
						'type'        => 'pricing',
						'placeholder' => '',
						'class'		  => '',
						'priority'    => 1,
						'required'    => false,
					),



				),
			),
			'booking' => array(
				'title' 	=> __('Booking', 'listeo_core'),
				'class' 	=> 'booking-enable',
				'onoff'		=> true,
				//'onoff_state' => 'on',
				'icon' 		=> 'fa fa-calendar-check',
				'fields' 	=> array(
					'_booking_status' => array(
						'label'       => __('Booking status', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_booking_status',

					),
				)
			),

			'basic_prices' => array(
				'title'		=> __('Booking prices and settings', 'listeo_core'),
				//'class'		=> 'margin-top-40',
				'icon'		=> 'fa fa-money',
				'fields'	=> array(


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
						'render_row_col' => '6'

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
						'render_row_col' => '6'
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
						'render_row_col' => '6'

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
						'render_row_col' => '3'
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
						'for_type'	  => 'rental'
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
						'render_row_col' => '3'
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
						'render_row_col' => '3'
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
						'render_row_col' => '3'
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
						'for_type'	  => 'service'
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
						'tooltip'	  => __('With this option enabled regular price and weekend price will be multiplied by hours booked, requires End Hour field to be ON or time slot', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_count_by_hour',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3'
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
						'unit'		  => __('hours', 'listeo_core'),
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '6'
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

					'_mandatory_fees' => array(
						'label'       => __('Mandatory Fees', 'listeo_core'),
						'type'        => 'repeatable',
						'tooltip'	  => __('Set mandatory fees that will always be added to total cost', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_mandatory_fees',
						'class'		  => '',
						'priority'    => 10,

						'render_row_col' => '6',
						'options'   => array(
							'title' => __('Title', 'listeo_core'),
							'price' => __('Price', 'listeo_core'),
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
						'render_row_col' => '3',
						'with_status' => '_animals',
					),


				),
			),
			'availability_calendar' => array(
				'title' 	=> __('Availability Calendar', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				//'onoff'		=> true,
				'icon' 		=> 'fa fa-calendar-check',
				'fields' 	=> array(

					'_availability' => array(
						'label'       => __('Click day in calendar to mark it as unavailable', 'listeo_core'),

						'name'       => '_availability_calendar',
						'type'        => 'calendar',
						'placeholder' => '',
						'class'		  => '',
						'priority'    => 1,
						'required'    => false,
					),

				),
			),

		);
		return $fields;
	}
	public function get_event_fields()
	{
		$scale = get_option('listeo_scale', 'sq ft');

		$currency_abbr = get_option('listeo_currency');

		$currency = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

		$fields = array(
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
						'multi'    	  => true,
						'required'    => false,
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
					),

					'listing_feature' => array(
						'label'       	=> __('Other Features', 'listeo_core'),
						'type'        	=> 'term-checkboxes',
						'taxonomy'		=> 'listing_feature',
						'name'			=> 'listing_feature',
						'class'		  	 => 'select2-single',
						'default'    	 => '',
						'priority'    	 => 2,
						'required'    => false,
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

						'priority'    => 7,
						'render_row_col' => '6'
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
						'render_row_col' => '6'
					),
					'region' => array(
						'label'       => __('Region', 'listeo_core'),
						'type'        => 'term-select',
						'required'    => false,
						'name'        => 'region',
						'taxonomy'        => 'region',
						'placeholder' => '',
						'class'		  => '',

						'priority'    => 8,
						'render_row_col' => '3'
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

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_geolocation_lat' => array(
						'label'       => __('Latitude', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_geolocation_lat',
						'class'		  => '',
						'priority'    => 10,

						'render_row_col' => '3'
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
						'placeholder' => 'Description',
						'class'		  => '',
						'priority'    => 1,
						'required'    => true,
					),
					'_video' => array(
						'label'       => __('Video', 'listeo_core'),
						'type'        => 'text',
						'name'        => '_video',
						'required'    => false,
						'placeholder' => __('URL to oEmbed supported service', 'listeo_core'),
						'class'		  => '',
						'priority'    => 5,
					),

					'_phone' => array(
						'label'       => __('Phone', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_phone',
						'class'		  => '',
						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_website' => array(
						'label'       => __('Website', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_website',
						'class'		  => '',

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_email' => array(
						'label'       => __('E-mail', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_email',

						'class'		  => '',
						'priority'    => 10,
						'render_row_col' => '3'
					),
					'_email_contact_widget' => array(
						'label'       => __('Enable Contact Widget', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled listing will display Contact Form Widget that will send emails to this address', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_email_contact_widget',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3'
					),

					'_facebook' => array(
						'label'       => __('<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_facebook',
						'class'		  => 'fb-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_twitter' => array(
						'label'       => __('<i class="fa-brands fa-x-twitter"></i> X', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_twitter',
						'class'		  => 'twitter-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_youtube' => array(
						'label'       => __('<i class="fa fa-youtube-square"></i> YouTube', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_youtube',
						'class'		  => 'youtube-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_instagram' => array(
						'label'       => __('<i class="fa fa-instagram"></i> Instagram', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_instagram',
						'class'		  => 'instagram-input',
						'priority'    => 10,

						'render_row_col' => '4'
					),
					'_whatsapp' => array(
						'label'       => __('<i class="fa fa-whatsapp"></i> WhatsApp', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_whatsapp',
						'class'		  => 'whatsapp-input',
						'priority'    => 10,
						'render_row_col' => '4'
					),
					'_skype' => array(
						'label'       => __('<i class="fa fa-skype"></i> Skype', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_skype',
						'class'		  => 'skype-input',
						'priority'    => 10,
						'render_row_col' => '4'
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
					),

				),
			),
			'opening_hours' => array(
				'title' 	=> __('Opening Hours', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'onoff'		=> true,
				'icon' 		=> 'sl sl-icon-clock',
				'fields' 	=> array(
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
					'_monday_opening_hour' => array(
						'label'       => __('Monday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_monday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_monday_closing_hour' => array(
						'label'       => __('Monday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_monday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_tuesday_opening_hour' => array(
						'label'       => __('Tuesday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_tuesday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_tuesday_closing_hour' => array(
						'label'       => __('Monday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_tuesday_closing_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),

					'_wednesday_opening_hour' => array(
						'label'       => __('Wednesday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_wednesday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_wednesday_closing_hour' => array(
						'label'       => __('Wednesday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_wednesday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_thursday_opening_hour' => array(
						'label'       => __('Thursday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_thursday_opening_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_thursday_closing_hour' => array(
						'label'       => __('Thursday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_thursday_closing_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_friday_opening_hour' => array(
						'label'       => __('Friday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_friday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_friday_closing_hour' => array(
						'label'       => __('Friday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_friday_closing_hour',
						'before_row' 	 => '',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_saturday_opening_hour' => array(
						'label'       => __('saturday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_saturday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_saturday_closing_hour' => array(
						'label'       => __('saturday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_saturday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_sunday_opening_hour' => array(
						'label'       => __('sunday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_sunday_opening_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_sunday_closing_hour' => array(
						'label'       => __('sunday Opening Hour', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_sunday_closing_hour',
						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_listing_timezone' => array(
						'label'       => __('Listing Timezone', 'listeo_core'),
						'type'        => 'timezone',
						'required'    => false,
						'name'        => '_listing_timezone',
					),

				),
			),
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
						'render_row_col' => '6'
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
						'render_row_col' => '6'
					),

				)
			),
			'menu' => array(
				'title' 	=> __('Pricing & Bookable Services', 'listeo_core'),
				//'class' 	=> 'margin-top-40',
				'onoff'		=> true,
				'icon' 		=> 'sl sl-icon-book-open',
				'fields' 	=> array(
					'_menu_status' => array(
						'label'       => __('Menu status', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_menu_status',
					),
					'_menu' => array(
						'label'       => __('Pricing', 'listeo_core'),
						'name'       => '_menu',
						'type'        => 'pricing',
						'placeholder' => '',
						'class'		  => '',
						'priority'    => 1,
						'required'    => false,
					),



				),
			),
			'booking' => array(
				'title' 	=> __('Booking', 'listeo_core'),
				'class' 	=> 'booking-enable',
				'onoff'		=> true,
				//'onoff_state' => 'on',
				'icon' 		=> 'fa fa-calendar-check',
				'fields' 	=> array(
					'_booking_status' => array(
						'label'       => __('Booking status', 'listeo_core'),
						'type'        => 'skipped',
						'required'    => false,
						'name'        => '_booking_status',

					),
				)
			),

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
						'render_row_col' => '6'
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
						'render_row_col' => '6'

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
						'render_row_col' => '6'

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
						'render_row_col' => '3'
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

					'_mandatory_fees' => array(
						'label'       => __('Mandatory Fees', 'listeo_core'),
						'type'        => 'repeatable',
						'tooltip'	  => __('Set mandatory fees that will always be added to total cost', 'listeo_core'),
						'required'    => false,
						'placeholder' => '',
						'name'        => '_mandatory_fees',
						'class'		  => '',
						'priority'    => 10,

						'render_row_col' => '6',
						'options'   => array(
							'title' => __('Title', 'listeo_core'),
							'price' => __('Price', 'listeo_core'),
						),
					),


				),
			),

		);
		return $fields;
	}
	public function get_classifieds_fields()
	{
		$scale = get_option('listeo_scale', 'sq ft');

		$currency_abbr = get_option('listeo_currency');

		$currency = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

		$fields = array(
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
						'multi'    	  => true,
						'required'    => false,
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
					),

					'listing_feature' => array(
						'label'       	=> __('Other Features', 'listeo_core'),
						'type'        	=> 'term-checkboxes',
						'taxonomy'		=> 'listing_feature',
						'name'			=> 'listing_feature',
						'class'		  	 => 'select2-single',
						'default'    	 => '',
						'priority'    	 => 2,
						'required'    => false,
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

						'priority'    => 7,
						'render_row_col' => '6'
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
						'render_row_col' => '6'
					),
					'region' => array(
						'label'       => __('Region', 'listeo_core'),
						'type'        => 'term-select',
						'required'    => false,
						'name'        => 'region',
						'taxonomy'        => 'region',
						'placeholder' => '',
						'class'		  => '',

						'priority'    => 8,
						'render_row_col' => '3'
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

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_geolocation_lat' => array(
						'label'       => __('Latitude', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_geolocation_lat',
						'class'		  => '',
						'priority'    => 10,

						'render_row_col' => '3'
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
						'placeholder' => 'Description',
						'class'		  => '',
						'priority'    => 1,
						'required'    => true,
					),
					'_video' => array(
						'label'       => __('Video', 'listeo_core'),
						'type'        => 'text',
						'name'        => '_video',
						'required'    => false,
						'placeholder' => __('URL to oEmbed supported service', 'listeo_core'),
						'class'		  => '',
						'priority'    => 5,
					),

					'_phone' => array(
						'label'       => __('Phone', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_phone',
						'class'		  => '',
						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_website' => array(
						'label'       => __('Website', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_website',
						'class'		  => '',

						'priority'    => 9,
						'render_row_col' => '3'
					),
					'_email' => array(
						'label'       => __('E-mail', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_email',

						'class'		  => '',
						'priority'    => 10,
						'render_row_col' => '3'
					),
					'_email_contact_widget' => array(
						'label'       => __('Enable Contact Widget', 'listeo_core'),
						'type'        => 'checkbox',
						'tooltip'	  => __('With this option enabled listing will display Contact Form Widget that will send emails to this address', 'listeo_core'),
						'required'    => false,

						'placeholder' => '',
						'name'        => '_email_contact_widget',
						'class'		  => '',
						'priority'    => 10,
						'priority'    => 9,
						'render_row_col' => '3'
					),

					'_facebook' => array(
						'label'       => __('<i class="fa fa-facebook-square"></i> Facebook', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_facebook',
						'class'		  => 'fb-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_twitter' => array(
						'label'       => __('<i class="fa-brands fa-x-twitter"></i> X', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_twitter',
						'class'		  => 'twitter-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_youtube' => array(
						'label'       => __('<i class="fa fa-youtube-square"></i> YouTube', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_youtube',
						'class'		  => 'youtube-input',

						'priority'    => 9,
						'render_row_col' => '4'
					),
					'_instagram' => array(
						'label'       => __('<i class="fa fa-instagram"></i> Instagram', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_instagram',
						'class'		  => 'instagram-input',
						'priority'    => 10,

						'render_row_col' => '4'
					),
					'_whatsapp' => array(
						'label'       => __('<i class="fa fa-whatsapp"></i> WhatsApp', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_whatsapp',
						'class'		  => 'whatsapp-input',
						'priority'    => 10,
						'render_row_col' => '4'
					),
					'_skype' => array(
						'label'       => __('<i class="fa fa-skype"></i> Skype', 'listeo_core'),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => '',
						'name'        => '_skype',
						'class'		  => 'skype-input',
						'priority'    => 10,
						'render_row_col' => '4'
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
					),

				),

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
		);
		return $fields;
	}



	/**
	 * Get fields for a specific listing type dynamically
	 * This method supports both existing hardcoded types and custom types
	 */
	public function get_fields_for_listing_type($listing_type)
	{
		// Get base fields using existing methods or service as template
		$method_name = 'get_' . $listing_type . '_fields';
		// check if there are saved configuration from Forms editor

		$base_fields = get_option("listeo_submit_{$listing_type}_form_fields");
		if (empty($base_fields)) {
			// If no saved configuration, fall back to method or service template
			if (method_exists($this, $method_name)) {
				$base_fields = call_user_func(array($this, $method_name));
			} else {

				// For custom types, use service fields as base template
				$base_fields = $this->get_service_fields();
			}
		}



		// Always check for custom type configuration to apply new flexible booking system
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();

			$type_config = $custom_types_manager->get_listing_type_by_slug($listing_type);


			if ($type_config) {
				// Apply new flexible booking system customizations
				$base_fields = $this->customize_fields_for_type($base_fields, $type_config);
			}
		}


		// Apply filters for custom field modifications
		return apply_filters('listeo_listing_type_fields', $base_fields, $listing_type);
	}

	/**
	 * Customize fields based on listing type configuration using new flexible booking system
	 */
	private function customize_fields_for_type($fields, $type_config)
	{
		// Get the booking features for this listing type
		$booking_features = isset($type_config->booking_features) ? json_decode($type_config->booking_features, true) : array();
		$booking_type = isset($type_config->booking_type) ? $type_config->booking_type : 'disabled';
		$supports_opening_hours = isset($type_config->supports_opening_hours) ? (bool) $type_config->supports_opening_hours : false;

		// First, ensure if the booking fields are from old version of editor,, remove the sections to avoid duplicates

		unset($fields['booking']);
		unset($fields['slots']);
		unset($fields['menu']);
		unset($fields['event']);
		unset($fields['availability_calendar']);

		// Then, customize and add sections based on configuration
		if ($booking_type === 'none' || $booking_type === 'disabled') {
			// Remove booking-flow sections when booking is disabled.
			unset($fields['booking']);
			unset($fields['slots']);
			unset($fields['availability_calendar']);
			//unset($fields['basic_prices']);

			// Booking-optional features still render. Add-on Services (menu) is the
			// only one today — admins enable it from Listing Types even when booking
			// is off, so directory-style listings can still expose priced extras.
			if (in_array('services', $booking_features)) {
				if (!isset($fields['menu'])) {
					$service_fields = $this->get_menu_section();
					$fields['menu'] = $service_fields['menu'];
				}
			} else {
				unset($fields['menu']);
			}
		} else {

			// now we should inject required sections based on booking type and features
			$fields = $this->inject_required_sections($fields, $booking_type, $booking_features, $supports_opening_hours);


			// Customize booking section based on enabled features
			$this->customize_booking_section($fields, $booking_type, $booking_features);


			if (in_array('hourly_picker', $booking_features)) {
			} else {
				$date_time_fields = array('_end_hour', '_rental_timepicker', '_count_by_hour');
				$this->remove_fields_recursive($fields, $date_time_fields);
			}

			// Basic pricing fields (_normal_price, _weekday_price, _reservation_price) are always available when booking is enabled


		}

		// Handle opening hours section (separate from booking features)
		if (!$supports_opening_hours) {
			// Remove opening hours section if not supported by this listing type
			unset($fields['opening_hours']);
		} else {
			$opening_hours_fields = $this->get_opening_hours_section();
			if ($opening_hours_fields) {
				$fields['opening_hours'] = $opening_hours_fields['opening_hours'];
			}
		}

		// Handle specific booking type restrictions
		if ($booking_type === 'tickets') {
			// For ticket-based bookings, remove date/time selection fields
			if (isset($fields['booking']['fields'])) {
				$date_time_fields = array('_slots', '_slots_status', '_end_hour', '_rental_timepicker', '_count_by_hour');

				foreach ($date_time_fields as $field) {
					unset($fields['basic_prices']['fields'][$field]);
				}
			}
		}

		// Handle separate slots section (outside of booking section)
		if (!in_array('time_slots', $booking_features) || $booking_type === 'disabled') {
			unset($fields['slots']);
		}

		// Apply saved section property overrides from Form Editor
		$listing_type_slug = isset($type_config->slug) ? $type_config->slug : '';
		if (!empty($listing_type_slug)) {
			$saved_overrides = get_option("listeo_booking_section_overrides_{$listing_type_slug}", array());
			$override_props = array('title', 'icon', 'onoff', 'onoff_state', 'class');
			foreach ($saved_overrides as $section_key => $overrides) {
				if (isset($fields[$section_key])) {
					foreach ($override_props as $prop) {
						if (isset($overrides[$prop])) {
							$fields[$section_key][$prop] = $overrides[$prop];
						}
					}
				}
			}

			// Apply saved section ordering
			$saved_order = get_option("listeo_section_order_{$listing_type_slug}", array());
			if (!empty($saved_order)) {
				$ordered = array();
				foreach ($saved_order as $sk) {
					if (isset($fields[$sk])) {
						$ordered[$sk] = $fields[$sk];
					}
				}
				foreach ($fields as $sk => $sd) {
					if (!isset($ordered[$sk])) {
						$ordered[$sk] = $sd;
					}
				}
				$fields = $ordered;
			}
		}

		return $fields;
	}

	/**
	 * Inject required sections that might be missing from Forms editor configuration
	 * This ensures new custom listing types have all necessary booking-related sections
	 */
	private function inject_required_sections($fields, $booking_type, $booking_features, $supports_opening_hours)
	{
		// Get appropriate reference template based on booking type



		// Inject booking section if needed
		if ($booking_type !== 'none' && $booking_type !== 'disabled') {
			$booking_data = $this->get_booking_section();
			$fields['booking'] = $booking_data['booking']; // Extract just the section data
		}


		// Event-specific sections for ticket-based bookings
		if ($booking_type === 'tickets') {
			// Inject event dates section if missing
			$event_fields = $this->get_event_section();
			$fields['event'] = $event_fields['event'];
		}

		// Inject slots section if time_slots feature is enabled but section is missing
		if (in_array('time_slots', $booking_features) && !isset($fields['slots'])) {
			$service_fields = $this->get_slot_section();
			$fields['slots'] = $service_fields['slots'];
		}


		// Inject menu section if services feature is enabled but section is missing
		if (in_array('services', $booking_features) && !isset($fields['menu'])) {
			$service_fields = $this->get_menu_section();
			$fields['menu'] = $service_fields['menu'];
		}

		// // Inject availability_calendar section if calendar feature is enabled but section is missing
		if (in_array('calendar', $booking_features) && !isset($fields['availability_calendar'])) {
			$calendar_fields = $this->get_availability_section();
			$fields['availability_calendar'] = $calendar_fields['availability_calendar'];
		}

		// Inject opening_hours section if supported but section is missing
		if ($supports_opening_hours && !isset($fields['opening_hours'])) {
			$opening_hours_fields = $this->get_opening_hours_section();
			$fields['opening_hours'] = $opening_hours_fields;
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
					'name'        => $taxonomy_name,
					'taxonomy'    => $taxonomy_name,
					'tooltip'     => sprintf(__('Select %s category', 'listeo_core'), $taxonomy_obj->labels->singular_name),
					'priority'    => 11, // Right after listing_category (priority 10)
					'default'     => '',
					'render_row_col' => '4',
					'multi'       => false,
					'required'    => false,
					'css_class'   => 'dynamic-taxonomy-field', // Mark as dynamic field
				);

				// Allow filtering of the taxonomy field settings
				$taxonomy_field = apply_filters('listeo_core_dynamic_taxonomy_field', $taxonomy_field, $taxonomy_name, $listing_type);

				// First remove any existing auto-injected field
				$fields = $this->remove_taxonomy_field($fields, $taxonomy_name, true); // Only remove auto-injected

				// Find the group containing listing_category and inject the taxonomy field there
				$inserted = false;

				foreach ($fields as $group_key => $group_data) {
					if (isset($group_data['fields']['listing_category']) || isset($group_data['fields']['tax-listing_category'])) {
						// Found the group with listing_category, inject our field right after it
						$group_fields = $group_data['fields'];
						$new_group_fields = array();

						foreach ($group_fields as $field_key => $field_data) {
							$new_group_fields[$field_key] = $field_data;

							// Insert right after listing_category
							if ($field_key === 'listing_category') {
								$taxonomy_field_key =  $taxonomy_name;
								$new_group_fields[$taxonomy_field_key] = $taxonomy_field;
								$inserted = true;
							}
							if ($field_key === 'tax-listing_category') {
								$taxonomy_field_key =  $taxonomy_name;
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
					$taxonomy_field_key =  $taxonomy_name;
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
		$taxonomy_field_key = $taxonomy_name;

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
		$taxonomy_field_key = $taxonomy_name;

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
	 * Customize booking section fields based on booking type and features
	 */
	private function customize_booking_section(&$fields, $booking_type, $booking_features)
	{


		$booking_fields = &$fields['basic_prices']['fields'];

		// Core booking type behavior
		switch ($booking_type) {
			case 'single_day':
				// Time slots are core for single day booking

				// Remove date range specific fields
				unset($booking_fields['_rental_timepicker']);
				break;

			case 'date_range':
				// Date range requires specific fields
				if (!in_array('hourly_picker', $booking_features)) {

					unset($booking_fields['_rental_timepicker']);
				}
				// Remove single day specific fields
				unset($booking_fields['_end_hour']);
				unset($booking_fields['_slots']);
				unset($booking_fields['_slots_status']);
				break;

			case 'tickets':
				// Ticket system removes most booking configuration
				$fields_to_remove = array(
					'_slots',
					'_slots_status',
					'_end_hour',
					'_rental_timepicker',
					'_count_by_hour',
					'_max_guests',
					'_min_guests'
				);
				foreach ($fields_to_remove as $field) {
					unset($booking_fields[$field]);
				}
				break;

			case 'custom':
				// Custom configuration allows all fields based on selected features
				if (!in_array('time_slots', $booking_features)) {
					unset($booking_fields['_slots']);
					unset($booking_fields['_slots_status']);
				}
				if (!in_array('hourly_picker', $booking_features)) {
					unset($booking_fields['_end_hour']);
					unset($booking_fields['_rental_timepicker']);
				}
				break;
		}

		// Handle additional features


	}


	function remove_fields_recursive(array &$node, array $remove_set): void
	{
		foreach ($node as $key => &$val) {
			if ($key === 'fields' && is_array($val)) {
				// Detect if 'fields' is associative (ids as keys) or a numerically indexed list.
				$is_assoc = array_keys($val) !== range(0, count($val) - 1);

				if ($is_assoc) {
					// 'fields' like ['_end_hour' => [...], '_foo' => [...]]
					$val = array_diff_key($val, $remove_set); // very fast
				} else {
					// 'fields' like [ ['id' => '_end_hour', ...], ['id' => 'foo', ...] ]
					foreach ($val as $i => $field) {
						if (is_array($field) && isset($field['id']) && isset($remove_set[$field['id']])) {
							unset($val[$i]);
						}
					}
					// Optional: reindex to keep nice numeric keys
					$val = array_values($val);
				}
			}

			// Recurse into all nested arrays
			if (is_array($val)) {
				$this->remove_fields_recursive($val, $remove_set);
			}
		}
		unset($val); // break reference
	}
	/**
	 * Filter fields based on listing type
	 * Dynamic replacement for hardcoded switch statements
	 */
	private function filter_fields_by_type($listing_type)
	{
		// Get all available listing types
		$all_types = listeo_core_get_listing_types();
		$other_types = array_keys(array_diff_key($all_types, array($listing_type => '')));

		// Remove fields that are marked for other types only
		foreach ($this->fields as $group_key => $group_fields) {
			if (isset($group_fields['fields'])) {
				foreach ($group_fields['fields'] as $key => $field) {
					if (!empty($field['for_type']) && in_array($field['for_type'], $other_types)) {
						unset($this->fields[$group_key]['fields'][$key]);
					}
				}
			}
		}

		// Special handling for classifieds (backward compatibility)
		if ($listing_type === 'classifieds' && !get_option("listeo_submit_classifieds_form_fields")) {
			unset($this->fields['booking']);
			unset($this->fields['slots']);
			unset($this->fields['basic_prices']);
			unset($this->fields['availability_calendar']);
			unset($this->fields['coupon_section']);
			unset($this->fields['menu']);
		}

		// Apply custom type configuration
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$type_config = $custom_types_manager->get_listing_type_by_slug($listing_type);

			if ($type_config) {
				// Apply configuration for any type (custom or default)
				if (!$type_config->booking_type || $type_config->booking_type === 'none') {
					unset($this->fields['booking']);
					unset($this->fields['slots']);
					unset($this->fields['availability_calendar']);
				}
			}
		}
	}

}
