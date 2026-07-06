<?php

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Listeo_Core_Search class.
 */
class Listeo_Core_Search
{

	const CACHE_KEY = 'listeo_query_vars_cache';
	const CACHE_VERSION_KEY = 'listeo_query_vars_version';
	const CACHE_EXPIRY = HOUR_IN_SECONDS; // 24 hours
	const KEYWORD_SEARCH_MAX_LENGTH = 200;
	const KEYWORD_SEARCH_HONEYPOT_FIELD = 'listeo_keyword_search_website';
	const KEYWORD_SEARCH_INTERACTION_FIELD = '_listeo_keyword_search_interaction';
	const KEYWORD_SEARCH_MIN_SUBMIT_SECONDS = 1.5;

	public $found_posts = 0;
	/**
	 * Constructor
	 */
	public function __construct()
	{


		add_action('pre_get_posts', array($this, 'pre_get_posts_listings'), 0);
		add_action('pre_get_posts', array($this, 'remove_products_from_search'), 0);
		// add_filter( 'posts_orderby', array( $this, 'featured_filter' ), 10, 2 );
		// add_filter( 'posts_request', array( $this, 'featured_filter' ), 10, 2 );


		add_filter('posts_results', array($this, 'open_now_results_filter'));
		add_filter('found_posts', array($this, 'open_now_results_filter_pagination'), 1, 2);

		//add_action( 'parse_tax_query', array( $this, 'parse_tax_query_listings' ), 1 );
		add_shortcode('listeo_search_form', array($this, 'output_search_form'));

		add_filter('query_vars', array($this, 'add_query_vars'));
		add_action('parse_request', array($this, 'maybe_reject_oversized_keyword_search'), 0);
		add_action('wp_footer', array(__CLASS__, 'print_keyword_search_bot_script'), 20);

		add_action('parse_query', [$this, 'admin_search_by_category']);
		add_action('restrict_manage_posts',  [$this, 'admin_filter_search_by_category']);

		if (get_option('listeo_search_name_autocomplete')) {
			add_action('wp_print_footer_scripts', array(__CLASS__, 'wp_print_footer_scripts'), 11);
			add_action('wp_ajax_listeo_core_incremental_listing_suggest', array(__CLASS__, 'wp_ajax_listeo_core_incremental_listing_suggest'));
			add_action('wp_ajax_nopriv_listeo_core_incremental_listing_suggest', array(__CLASS__, 'wp_ajax_listeo_core_incremental_listing_suggest'));
		}

		add_action('wp_ajax_nopriv_listeo_get_listings', array($this, 'ajax_get_listings'));
		add_action('wp_ajax_listeo_get_listings', array($this, 'ajax_get_listings'));

		add_action('wp_ajax_nopriv_listeo_get_features_from_category', array($this, 'ajax_get_features_from_category'));
		add_action('wp_ajax_listeo_get_features_from_category', array($this, 'ajax_get_features_from_category'));

		add_action('wp_ajax_nopriv_listeo_get_features_ids_from_category', array($this, 'ajax_get_features_ids_from_category'));
		add_action('wp_ajax_listeo_get_features_ids_from_category', array($this, 'ajax_get_features_ids_from_category'));

		add_action('wp_ajax_nopriv_listeo_get_listing_types_from_categories', array($this, 'ajax_get_listing_types_from_categories'));
		add_action('wp_ajax_listeo_get_listing_types_from_categories', array($this, 'ajax_get_listing_types_from_categories'));


		add_action('wp_ajax_nopriv_listeo_get_custom_search_fields_from_term', array($this, 'ajax_get_custom_search_fields_from_term'));
		add_action('wp_ajax_listeo_get_custom_search_fields_from_term', array($this, 'ajax_get_custom_search_fields_from_term'));

		//add_filter('posts_where', array($this, 'listeo_date_range_filter'));
	}

	public static function get_keyword_search_max_length()
	{
		$max_length = apply_filters('listeo_core_keyword_search_max_length', self::KEYWORD_SEARCH_MAX_LENGTH);

		return max(1, absint($max_length));
	}

	public static function get_keyword_search_length($keyword)
	{
		if (is_array($keyword)) {
			return PHP_INT_MAX;
		}

		$keyword = (string) $keyword;

		if (function_exists('mb_strlen')) {
			return mb_strlen($keyword, 'UTF-8');
		}

		return strlen($keyword);
	}

	public static function is_keyword_search_too_long($keyword)
	{
		return self::get_keyword_search_length($keyword) > self::get_keyword_search_max_length();
	}

	public static function sanitize_keyword_search($keyword)
	{
		if (is_array($keyword)) {
			return '';
		}

		$keyword = sanitize_text_field(wp_unslash($keyword));

		if (self::is_keyword_search_too_long($keyword)) {
			return '';
		}

		return $keyword;
	}

	public static function get_keyword_search_too_long_message()
	{
		return sprintf(
			__('Search query is too long. Please use %d characters or fewer.', 'listeo_core'),
			self::get_keyword_search_max_length()
		);
	}

	public static function get_keyword_search_min_submit_seconds()
	{
		$seconds = apply_filters('listeo_core_keyword_search_min_submit_seconds', self::KEYWORD_SEARCH_MIN_SUBMIT_SECONDS);

		return max(0, (float) $seconds);
	}

	public static function get_keyword_search_bot_message()
	{
		return __('Search request could not be verified. Please try again.', 'listeo_core');
	}

	public static function is_keyword_search_bot_request($request)
	{
		if (empty($request) || !isset($request['keyword_search']) || is_array($request['keyword_search'])) {
			return false;
		}

		$keyword = trim((string) wp_unslash($request['keyword_search']));
		if ($keyword === '') {
			return false;
		}

		$honeypot_field = self::KEYWORD_SEARCH_HONEYPOT_FIELD;
		if (isset($request[$honeypot_field]) && is_array($request[$honeypot_field])) {
			return true;
		}

		if (isset($request[$honeypot_field]) && trim((string) wp_unslash($request[$honeypot_field])) !== '') {
			return true;
		}

		$interaction_field = self::KEYWORD_SEARCH_INTERACTION_FIELD;
		if (isset($request[$interaction_field]) && is_array($request[$interaction_field])) {
			return true;
		}

		if (empty($request[$interaction_field])) {
			return false;
		}

		$elapsed_ms = (float) wp_unslash($request[$interaction_field]);
		if ($elapsed_ms <= 0) {
			return false;
		}

		return $elapsed_ms < (self::get_keyword_search_min_submit_seconds() * 1000);
	}

	public static function render_keyword_search_bot_fields()
	{
		?>
		<span class="listeo-keyword-search-check" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
			<input type="text" name="<?php echo esc_attr(self::KEYWORD_SEARCH_HONEYPOT_FIELD); ?>" value="" tabindex="-1" autocomplete="off" />
		</span>
		<input type="hidden" name="<?php echo esc_attr(self::KEYWORD_SEARCH_INTERACTION_FIELD); ?>" value="0" />
		<?php
	}

	public static function print_keyword_search_bot_script()
	{
		if (is_admin()) {
			return;
		}
		?>
		<script>
		(function() {
			var interactionName = <?php echo wp_json_encode(self::KEYWORD_SEARCH_INTERACTION_FIELD); ?>;
			var fallbackStartedAt = Date.now();

			function getElapsedMs() {
				if (window.performance && typeof window.performance.now === 'function') {
					return Math.max(1, Math.round(window.performance.now()));
				}

				return Math.max(1, Date.now() - fallbackStartedAt);
			}

			function markKeywordSearchInteraction(form) {
				if (!form) {
					return;
				}

				var fields = form.querySelectorAll('input');
				for (var i = 0; i < fields.length; i++) {
					if (fields[i].name === interactionName) {
						fields[i].value = getElapsedMs();
						return;
					}
				}
			}

			document.addEventListener('input', function(event) {
				if (event.target && event.target.name === 'keyword_search') {
					markKeywordSearchInteraction(event.target.form);
				}
			}, true);

			document.addEventListener('change', function(event) {
				if (event.target && event.target.name === 'keyword_search') {
					markKeywordSearchInteraction(event.target.form);
				}
			}, true);

			document.addEventListener('keydown', function(event) {
				if (event.target && event.target.name === 'keyword_search') {
					markKeywordSearchInteraction(event.target.form);
				}
			}, true);

			document.addEventListener('submit', function(event) {
				if (event.target && event.target.querySelector('input[name="keyword_search"]')) {
					markKeywordSearchInteraction(event.target);
				}
			}, true);
		}());
		</script>
		<?php
	}

	public function maybe_reject_oversized_keyword_search()
	{
		if (is_admin() || wp_doing_ajax() || !isset($_GET['keyword_search'])) {
			return;
		}

		if (self::is_keyword_search_too_long(wp_unslash($_GET['keyword_search']))) {
			wp_die(
				esc_html(self::get_keyword_search_too_long_message()),
				esc_html__('Search query too long', 'listeo_core'),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		if (self::is_keyword_search_bot_request($_GET)) {
			wp_die(
				esc_html(self::get_keyword_search_bot_message()),
				esc_html__('Search request blocked', 'listeo_core'),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * AJAX handler to get custom fields for search filters based on selected taxonomy terms
	 */
	public function ajax_get_custom_search_fields_from_term()
	{
		$term = isset($_REQUEST['term']) ? sanitize_text_field($_REQUEST['term']) : false;
		$categories = isset($_REQUEST['cat_ids']) ? $_REQUEST['cat_ids'] : false;
		$context = isset($_REQUEST['context']) ? sanitize_text_field($_REQUEST['context']) : 'sidebar';
		$form_type = isset($_REQUEST['form_type']) ? sanitize_text_field($_REQUEST['form_type']) : '';

		if (!$term || !$categories || !is_array($categories) || empty($categories)) {
			wp_send_json_error(array('message' => esc_html__('Invalid request', 'listeo_core')));
			return;
		}

	

		// Check if custom taxonomy fields should be loaded for homepage forms
		if (($form_type == 'search_on_home_page' || $form_type == 'search_on_homebox_page') && class_exists('Listeo_Forms_Editor')) {
			$should_load = Listeo_Forms_Editor::should_load_custom_taxonomy_fields($form_type);
	

			if (!$should_load) {
				
				// Return empty success response - no custom fields should be loaded
				wp_send_json_success(array('html' => '', 'fields' => array()));
				return;
			}
		}

		$grouped_search_fields = array();
		$success = true;

		foreach ($categories as $category) {
			// Get custom fields for term
			if (is_numeric($category)) {
				// If it's numeric, it's already an ID
				$category_id = $category;
			} else {
				// If it's a string, it's a slug - convert to ID
				$term_obj = get_term_by('slug', $category, $term);
				if ($term_obj) {
					$category_id = $term_obj->term_id;
				} else {
					continue; // Skip if term not found
				}
			}

			// Get the term object for name
			$current_term = get_term($category_id, $term);
			if (!$current_term || is_wp_error($current_term)) {
				continue;
			}

			$term_fields = get_option("listeo_tax-{$term}_term_{$category_id}_fields");

			if (is_array($term_fields) && !empty($term_fields)) {
				$term_search_fields = array();
				foreach ($term_fields as $field) {
					// Check if field has "addtosearch" enabled

					if (isset($field['addtosearch']) && $field['addtosearch'] == '1') {
						// Prepare field for search form
						$search_field = $this->prepare_custom_field_for_search($field);
						if ($search_field) {
							$term_search_fields[] = $search_field;
						}
					}
				}

				// Only add to grouped fields if we have fields for this term
				if (!empty($term_search_fields)) {
					$grouped_search_fields[] = array(
						'term_id' => $category_id,
						'term_name' => $current_term->name,
						'term_slug' => $current_term->slug,
						'fields' => $term_search_fields
					);
				}
			}
		}

		ob_start();

		if (!empty($grouped_search_fields)) {
			if ($context === 'panel') {
				// Panel layout: Create separate panels for each term
				foreach ($grouped_search_fields as $group) {
?>
					<div class="panel-dropdown wide custom-fields-panel" id="custom-fields-<?php echo esc_attr($group['term_slug']); ?>-panel">
						<a href="#"><?php echo esc_html(sprintf(__('Filters for "%s"', 'listeo_core'), $group['term_name'])); ?></a>
						<div class="panel-dropdown-content ">
							<?php
							$template_loader = new Listeo_Core_Template_Loader;
							foreach ($group['fields'] as $field) {
							?>
								<div class="custom-search-field-wrapper" data-field-id="<?php echo esc_attr($field['id']); ?>">
									<label for="<?php echo esc_attr($field['id']); ?>" class="col-md-12 custom-search-field-label">
										<?php echo esc_html($field['label']); ?>
									</label>
									<?php
									$template_loader->set_template_data($field)->get_template_part('search-form/' . $field['type']);
									?>
								</div>
							<?php
							}
							?>
						</div>
					</div>
				<?php
				}
			} else {
				// Sidebar layout: Use collapsible groups
				?>
				<div class="custom-search-fields-container">
					<?php foreach ($grouped_search_fields as $group_index => $group): ?>
						<div class="custom-search-group" data-term-id="<?php echo esc_attr($group['term_id']); ?>">
							<div class="custom-search-group-header" onclick="toggleCustomSearchGroup(this)">
								<h4 class="custom-search-group-title">
									<?php echo esc_html(sprintf(__('Filters for "%s"', 'listeo_core'), $group['term_name'])); ?>
									<i class="fa fa-angle-down toggle-icon"></i>
								</h4>
							</div>
							<div class="custom-search-group-content" style="<?php echo $group_index === 0 ? 'display: block;' : 'display: none;'; ?>">
								<?php
								$template_loader = new Listeo_Core_Template_Loader;
								foreach ($group['fields'] as $field) {
								?>
									<div class="custom-search-field-wrapper" data-field-id="<?php echo esc_attr($field['id']); ?>">
										<label for="<?php echo esc_attr($field['id']); ?>" class="col-md-12 custom-search-field-label">
											<?php echo esc_html($field['label']); ?>
										</label>
										<?php
										$template_loader->set_template_data($field)->get_template_part('search-form/' . $field['type']);
										?>
									</div>
								<?php
								}
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
		<?php
			}
		} else {
			$success = false;
		}

		$result = array(
			'output' => ob_get_clean(),
			'success' => $success,
			'groups_count' => count($grouped_search_fields),
			'context' => $context
		);

		wp_send_json($result);
	}

	/**
	 * Prepare custom field for search form
	 */
	private function prepare_custom_field_for_search($field)
	{
		if (!isset($field['id']) || !isset($field['type'])) {
			return false;
		}

		$search_field = array(
			'id' => $field['id'],
			'name' => $field['id'],
			'key' => $field['id'],
			'label' => isset($field['name']) ? $field['name'] : $field['id'],
			'placeholder' => isset($field['name']) ? $field['name'] : $field['id'],
			'type' => $this->convert_field_type_for_search($field['type']),
			'class' => 'col-md-12',
			'priority' => 10,
			//'place' => 'dynamic'
		);


		// Handle field-specific configurations
		switch ($field['type']) {
			
			case 'select':
				if (isset($field['options']) && is_array($field['options'])) {
					$search_field['options'] = $field['options'];
				}
				break;
				
			case 'select-multiple':
			case 'select_multiple':
				if (isset($field['options']) && is_array($field['options'])) {
					$search_field['options'] = $field['options'];
				}
				$search_field['multi'] = true;
				$search_field['type'] = 'select';
				break;
			case 'slider':
			case 'range':
				if (isset($field['min'])) {
					$search_field['min'] = $field['min'];
				}
				if (isset($field['max'])) {
					$search_field['max'] = $field['max'];
				}
				if (isset($field['step'])) {
					$search_field['step'] = $field['step'];
				}
				break;


			case 'multi-checkbox':
			case 'multicheck_split':
			case 'multicheck':
				if (isset($field['options']) && is_array($field['options'])) {
					$search_field['options'] = $field['options'];
					$search_field['options_source'] = 'custom';
				}
				break;
		}
		
		return $search_field;
	}

	/**
	 * Convert custom field types to search form compatible types
	 */
	private function convert_field_type_for_search($field_type)
	{
		$type_mapping = array(
			'text' => 'text',
			'textarea' => 'text', // Convert textarea to text for search
			'select' => 'select',
			'select_multiple' => 'select',
			'multicheck_split' => 'multi-checkbox',
			'multicheck' => 'multi-checkbox',
			'checkboxes' => 'multi-checkbox',
			'checkbox' => 'checkbox',
			'slider' => 'slider',
			'range' => 'slider',
			'number' => 'text',
			'date' => 'text',
			'datetime' => 'text'
		);

		return isset($type_mapping[$field_type]) ? $type_mapping[$field_type] : 'text';
	}

	/**
	 * Remove duplicate search fields based on field ID
	 */
	private function remove_duplicate_search_fields($fields)
	{
		$unique_fields = array();
		$field_ids = array();

		foreach ($fields as $field) {
			if (!in_array($field['id'], $field_ids)) {
				$unique_fields[] = $field;
				$field_ids[] = $field['id'];
			}
		}

		return $unique_fields;
	}
	function admin_filter_search_by_category()
	{
		global $typenow;
		$post_type = 'listing'; // change to your post type
		$taxonomy  = 'listing_category'; // change to your taxonomy
		if ($typenow == $post_type) {
			$selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
			$info_taxonomy = get_taxonomy($taxonomy);
			wp_dropdown_categories(array(
				'show_option_all' => sprintf(__('Show all %s', 'listeo_core'), $info_taxonomy->label),
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => $selected,
				'show_count'      => true,
				'hide_empty'      => true,
			));
		};
	}

	function admin_search_by_category($query)
	{
		global $pagenow;
		$post_type = 'listing'; // change to your post type
		$taxonomy  = 'listing_category'; // change to your taxonomy
		$q_vars    = &$query->query_vars;
		if ($pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0) {
			$term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
			$q_vars[$taxonomy] = $term->slug;
		}
	}


	function listeo_date_range_filter($where)
	{

		global $wpdb;
		global $wp_query;
		if (!isset($wp_query) || !method_exists($wp_query, 'get'))
			return $where;

		// For AJAX queries, get values from query object instead of global query vars
		if (defined('DOING_AJAX') && DOING_AJAX) {
			$date_range = $wp_query->get('date_range');
			$listing_type = $wp_query->get('_listing_type');
		} else {
			$date_range = get_query_var('date_range');
			$listing_type = get_query_var('_listing_type');
		}

		// Skip date filtering for events and rentals as they are handled separately in pre_get_posts_listings
		if ($listing_type == 'event' || $listing_type == 'rental') {
			return $where;
		}

		if (!empty($date_range)) :
			//TODO replace / with - if first is day - month- year
			$dates = explode(' - ', $date_range);
			//setcookie('listeo_date_range', $date_range, time()+31556926);
			$date_start = $dates[0];
			$date_end = $dates[1];

			// Use the same date format as the working AJAX version
			$date_start_object = DateTime::createFromFormat('!m/d/Y', $date_start);
			$date_end_object = DateTime::createFromFormat('!m/d/Y', $date_end);

			if (!$date_start_object || !$date_end_object) {
				return $where;
			}
			$format_date_start 	= esc_sql($date_start_object->format("Y-m-d H:i:s"));
			//$format_date_end 	= esc_sql($date_end_object->modify('+23 hours 59 minutes 59 seconds')->format("Y-m-d H:i:s"));
			$format_date_end = esc_sql($date_end_object->modify('+0 day')->format('Y-m-d 00:00:00'));


			// $where .= $GLOBALS['wpdb']->prepare(  " AND {$wpdb->prefix}posts.ID ".
			//     'NOT IN ( '.
			//         'SELECT listing_id '.
			//         "FROM {$wpdb->prefix}bookings_calendar ".
			//         'WHERE 
			//     	(( %s > date_start AND %s < date_end ) 
			//     	OR 
			//     	( %s > date_start AND %s < date_end ) 
			//     	OR 
			//     	( date_start >= %s AND date_end <= %s ))
			//     	AND type = "reservation" AND NOT status="cancelled" AND NOT status="expired"
			//     	GROUP BY listing_id '.
			//     ' ) ', $format_date_start, $format_date_start, $format_date_end,  $format_date_end, $format_date_start, $format_date_end );
			$where .= $GLOBALS['wpdb']->prepare(
				" AND {$wpdb->prefix}posts.ID NOT IN ( 
                SELECT DISTINCT listing_id 
                FROM {$wpdb->prefix}bookings_calendar 
                WHERE 
                (
                    (date_start < %s AND date_end > %s) 
                    OR (date_start < %s AND date_end > %s) 
                    OR (date_start >= %s AND date_end <= %s)
                    OR (date_start = %s AND date_end = %s)
                )
                AND type = 'reservation' 
                AND status NOT IN ('cancelled', 'expired')
            )",
				$format_date_end,
				$format_date_start,
				$format_date_start,
				$format_date_start,
				$format_date_start,
				$format_date_end,
				$format_date_start,
				$format_date_end
			);
			

		endif;

		return $where;
	}

	public function remove_products_from_search($query)
	{

		/* check is front end main loop content */
		if (is_admin() || !$query->is_main_query()) return;

		/* check is search result query */
		if ($query->is_search()) {
			if (isset($_GET['post_type']) && $_GET['post_type'] == 'product') {
			} else {
				$post_type_to_remove = 'product';
				/* get all searchable post types */
				$searchable_post_types = get_post_types(array('exclude_from_search' => false));

				/* make sure you got the proper results, and that your post type is in the results */
				if (is_array($searchable_post_types) && in_array($post_type_to_remove, $searchable_post_types)) {
					/* remove the post type from the array */
					unset($searchable_post_types[$post_type_to_remove]);
					/* set the query to the remaining searchable post types */
					$query->set('post_type', $searchable_post_types);
				}
			}
		}
	}


	public function open_now_results_filter($posts)
	{
		// Return empty array if posts is null to prevent errors in other plugins
		if (!is_array($posts)) {
			return array();
		}

		// Only apply filter to listing queries on frontend
		if (is_admin()) {
			return $posts;
		}

		// Check if posts are WP_Post objects before filtering
		if (!empty($posts) && !($posts[0] instanceof WP_Post)) {
			return $posts;
		}

		if (isset($_GET['open_now'])) {
			$filtered_posts = array();

			foreach ($posts as $post) {
				if (listeo_check_if_open($post)) {
					$filtered_posts[] = $post;
				}
			}
			$this->found_posts = count($filtered_posts);
			return $filtered_posts;
		}

		return $posts;
	}

	function open_now_results_filter_pagination($found_posts, $query)
	{
		if (isset($_GET['open_now'])) {
			// Define the homepage offset...
			$found_posts = $this->found_posts;
		}
		return $found_posts;
	}


	static function wp_print_footer_scripts()
	{
		?>
		<script type="text/javascript">
			(function($) {
				$(document).ready(function() {

					$('#keyword_search.title-autocomplete').autocomplete({

						source: function(req, response) {
							$.getJSON('<?php echo admin_url('admin-ajax.php'); ?>' + '?callback=?&action=listeo_core_incremental_listing_suggest', req, response);
						},
						select: function(event, ui) {
							window.location.href = ui.item.link;
						},
						minLength: 3,
					});
				});

			})(this.jQuery);
		</script><?php
				}

				static function wp_ajax_listeo_core_incremental_listing_suggest()
				{

					$suggestions = array();
					$posts = get_posts(array(
						's' => $_REQUEST['term'],
						'post_type'     => 'listing',
					));
					global $post;
					$results = array();
					foreach ($posts as $post) {
						setup_postdata($post);
						$suggestion = array();
						$suggestion['label'] =  html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8');
						$suggestion['link'] = get_permalink($post->ID);

						$suggestions[] = $suggestion;
					}
					// JSON encode and echo
					$response = $_GET["callback"] . "(" . json_encode($suggestions) . ")";
					echo $response;
					// Don't forget to exit!
					exit;
				}

				public function add_query_vars($vars)
				{
					$cached_vars = $this->get_cached_query_vars();

					return array_merge($cached_vars, $vars);
				}

				/**
				 * Get query vars from cache or build them
				 */
				private function get_cached_query_vars()
				{
					// Try to get from object cache first (fastest)
					// $cached_vars = wp_cache_get(self::CACHE_KEY, 'listeo');
					// if ($cached_vars !== false) {
					// 	return $cached_vars;
					// }

					// // Try transient cache (persistent across requests)
					// $cached_vars = get_transient(self::CACHE_KEY);
					// if ($cached_vars !== false) {
					// 	// Store in object cache for this request
					// 	wp_cache_set(self::CACHE_KEY, $cached_vars, 'listeo', 300); // 5 minutes
					// 	return $cached_vars;
					// }

					// Build fresh data and cache it
					$query_vars = $this->build_available_query_vars();

					// Cache in both object cache and transient
					wp_cache_set(self::CACHE_KEY, $query_vars, 'listeo', 300);
					set_transient(self::CACHE_KEY, $query_vars, self::CACHE_EXPIRY);

					return $query_vars;
				}

				/**
				 * Build query vars with optimizations
				 */
				public static function build_available_query_vars()
				{
					$query_vars = array();

					// Add taxonomy query vars
					$taxonomy_objects = get_object_taxonomies('listing', 'objects');
					foreach ($taxonomy_objects as $tax) {
						$query_vars[] = 'tax-' . $tax->name;
					}

					// Process meta box fields efficiently
					$query_vars = array_merge($query_vars, self::get_meta_box_fields());

					// Process custom term fields with batch loading
					$query_vars = array_merge($query_vars, self::get_custom_term_fields());

					// Process custom listing type fields from fields builder
					$query_vars = array_merge($query_vars, self::get_custom_listing_type_fields());

					// Add standard listing query vars
					$standard_vars = [
						'ai_search_input',
						'_price_range',
						'_listing_type',
						'_price',
						'_max_guests',
						'rating-filter',
						'_min_guests',
						'_instant_booking',
						'drilldown-listing-types',
						'date_range',
						'location_search',
						'keyword_search',
						'search_radius',
						'map_bounds',
						'search_by_map_move',
						'place_viewport',
						'place_type'
					];

					$query_vars = array_merge($query_vars, $standard_vars);

					return array_values(array_unique(array_filter($query_vars)));
				}


				/**
				 * Get all meta box fields efficiently
				 */
				private static function get_meta_box_fields()
				{
					$field_ids = array();

					$meta_box_methods = [
						'meta_boxes_service',
						'meta_boxes_location',
						'meta_boxes_event',
						'meta_boxes_prices',
						'meta_boxes_contact',
						'meta_boxes_rental',
						'meta_boxes_classifieds',
						'meta_boxes_custom'
					];

					foreach ($meta_box_methods as $method) {
						if (method_exists('Listeo_Core_Meta_Boxes', $method)) {
							$meta_box = call_user_func(['Listeo_Core_Meta_Boxes', $method]);
							if (isset($meta_box['fields']) && is_array($meta_box['fields'])) {
								foreach ($meta_box['fields'] as $field) {
									if (isset($field['id']) && !empty($field['id'])) {
										$field_ids[] = $field['id'];
									}
								}
							}
						}
					}

					return $field_ids;
				}

				/**
				 * Get custom term fields with optimized database queries
				 */
				private static function get_custom_term_fields()
				{
					$field_ids = array();
					$custom_term_fields = get_option("listeo_custom_term_fields", array());

					if (!is_array($custom_term_fields) || empty($custom_term_fields)) {
						return $field_ids;
					}

					// Batch collect all option keys first
					$option_keys = array();
					foreach ($custom_term_fields as $key => $term_ids) {
						if (is_array($term_ids)) {
							foreach ($term_ids as $term_id => $fields) {
								$option_keys[] = 'listeo_tax-' . $key . '_term_' . $term_id . '_fields';
							}
						}
					}

					// Get all options in batch (if using object cache, this is more efficient)
					foreach ($option_keys as $option_key) {
						$output_fields = get_option($option_key);
						if (is_array($output_fields) && !empty($output_fields)) {
							foreach ($output_fields as $var_field) {
								if (isset($var_field['id']) && !empty($var_field['id'])) {
									$field_ids[] = $var_field['id'];
								}
							}
						}
					}

					return $field_ids;
				}

				/**
				 * Get custom listing type fields from fields builder
				 */
				private static function get_custom_listing_type_fields()
				{
					$field_ids = array();

					// Get all listing types using the custom listing types manager
					if (function_exists('listeo_core_custom_listing_types')) {
						$custom_types_manager = listeo_core_custom_listing_types();
						$listing_types = $custom_types_manager->get_listing_types(true); // Active types only

						if (!empty($listing_types)) {
							foreach ($listing_types as $type) {
								// Get fields from fields builder: listeo_{listing_type}_tab_fields
								$tab_fields = get_option("listeo_{$type->slug}_tab_fields", array());

								if (is_array($tab_fields) && !empty($tab_fields)) {
									foreach ($tab_fields as $field) {
										if (isset($field['id']) && !empty($field['id'])) {
											$field_ids[] = $field['id'];
										}
									}
								}
							}
						}
					} else {
						// Fallback to old system if custom types manager not available
						$listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));

						if (is_array($listing_types)) {
							foreach ($listing_types as $type_slug) {
								// Get fields from fields builder
								$tab_fields = get_option("listeo_{$type_slug}_tab_fields", array());

								if (is_array($tab_fields) && !empty($tab_fields)) {
									foreach ($tab_fields as $field) {
										if (isset($field['id']) && !empty($field['id'])) {
											$field_ids[] = $field['id'];
										}
									}
								}
							}
						}
					}

					// Also include general tab fields that apply to all listing types
					$general_tabs = array('contact', 'location', 'custom');
					foreach ($general_tabs as $tab) {
						$tab_fields = get_option("listeo_{$tab}_tab_fields", array());
						if (is_array($tab_fields) && !empty($tab_fields)) {
							foreach ($tab_fields as $field) {
								if (isset($field['id']) && !empty($field['id'])) {
									$field_ids[] = $field['id'];
								}
							}
						}
					}

					return $field_ids;
				}

				private function get_request_bound_value($key)
				{
					$value = get_query_var($key, null);

					if ($this->is_empty_meta_request_value($value) && isset($_REQUEST[$key])) {
						$value = $_REQUEST[$key];
					}

					if (is_array($value)) {
						$value = reset($value);
					}

					if ($this->is_empty_meta_request_value($value)) {
						return null;
					}

					if (is_scalar($value)) {
						$value = sanitize_text_field($value);
					}

					if ($this->is_empty_meta_request_value($value) || $value === 'NaN' || $value === '-1') {
						return null;
					}

					if (is_numeric($value)) {
						return 0 + $value;
					}

					return $value;
				}

				private function is_empty_meta_request_value($value)
				{
					if ($value === null) {
						return true;
					}

					if ($value === '' || $value === false) {
						return true;
					}

					if (is_array($value) && empty($value)) {
						return true;
					}

					return false;
				}

				/**
				 * Clear cache when relevant data changes
				 */
				public function clear_cache()
				{
					wp_cache_delete(self::CACHE_KEY, 'listeo');
					delete_transient(self::CACHE_KEY);

					// Increment version for cache invalidation
					$version = get_option(self::CACHE_VERSION_KEY, 1);
					update_option(self::CACHE_VERSION_KEY, $version + 1);
				}

				/**
				 * Hook into relevant actions to clear cache
				 */
				public function init_cache_invalidation()
				{
					// Clear cache when taxonomies or meta boxes might change
					add_action('created_term', array($this, 'clear_cache'));
					add_action('edited_term', array($this, 'clear_cache'));
					add_action('delete_term', array($this, 'clear_cache'));

					// Clear cache when options are updated
					add_action('update_option_listeo_custom_term_fields', array($this, 'clear_cache'));

					// Clear cache when listing-related options change
					add_action('update_option', array($this, 'maybe_clear_cache'), 10, 2);

					// Clear cache on plugin activation/deactivation
					add_action('activate_plugin', array($this, 'clear_cache'));
					add_action('deactivate_plugin', array($this, 'clear_cache'));
				}

				/**
				 * Conditionally clear cache based on option name
				 */
				public function maybe_clear_cache($option_name, $old_value)
				{
					// Clear cache if any listeo-related option changes
					if (strpos($option_name, 'listeo_') === 0) {
						$this->clear_cache();
					}
				}

				/**
				 * Check if we're on any listing type category taxonomy page
				 */
				private function is_listing_type_taxonomy() {
					// Check standard taxonomies
					if (is_tax('listing_category') || is_tax('service_category') || is_tax('event_category') || is_tax('rental_category') || is_tax('classifieds_category') || is_tax('listing_feature') || is_tax('region')) {
						return true;
					}

					// Check custom listing type taxonomies
					if (function_exists('listeo_core_custom_listing_types')) {
						$custom_types_manager = listeo_core_custom_listing_types();
						$listing_types = $custom_types_manager->get_listing_types(true);

						foreach ($listing_types as $type) {
							// Skip default types as they're already checked above
							if (in_array($type->slug, array('service', 'rental', 'event', 'classifieds'))) {
								continue;
							}

							$taxonomy_name = $type->slug . '_category';
							if (is_tax($taxonomy_name)) {
								return true;
							}
						}
					}

					return false;
					
				}



	/**
	 * Check if the current request has listing search parameters
	 * Used to apply search filters on custom pages
	 * 
	 * @return bool
	 */
	private function has_listing_search_params()
	{
		// Check for common search parameters
		$search_params = array(
			'keyword_search',
			'location_search',
			'search_radius',
			'listing_category',
			'listing_feature',
			'listing_region',
			'ai_search_input',
			'place_viewport',
			'rating-filter',
			'drilldown-listing-types'
		);

		foreach ($search_params as $param) {
			$value = get_query_var($param);
			if (!empty($value)) {
				return true;
			}
		}

		// Check if any taxonomy filters are present
		$taxonomies = get_object_taxonomies('listing');
		foreach ($taxonomies as $tax) {
			$value = get_query_var($tax);
			if (!empty($value)) {
				return true;
			}
		}

		return false;
	}

				public function pre_get_posts_listings($query)
				{

					if (is_admin() || ! $query->is_main_query()) {
						return $query;
					}

					if (!is_admin() && $query->is_main_query() && is_post_type_archive('listing')) {
						$per_page = get_option('listeo_listings_per_page', 10);
						$query->set('posts_per_page', $per_page);
						$query->set('post_type', 'listing');
						$query->set('post_status', 'publish');
					}

					if ($this->is_listing_type_taxonomy()) {

						$per_page = get_option('listeo_listings_per_page', 10);
						$query->set('posts_per_page', $per_page);
					}

					

				// Check if this is a listing search page (archive, author, taxonomy)
				// Don't modify main query on regular pages (e.g. /explore/) - shortcodes handle search there
				$is_listing_search = (
					is_post_type_archive('listing') ||
					is_author() ||
					$this->is_listing_type_taxonomy() ||
					((isset($_GET['action']) && $_GET['action'] === 'listeo_get_listings') && $this->has_listing_search_params() && !$query->get('pagename') && !$query->get('page_id'))
				);
				
				

				if ($is_listing_search) {

						$keyword = get_query_var('keyword_search');
						if (Listeo_Core_Search::is_keyword_search_too_long($keyword)) {
							$query->set('post__in', array(0));
							$query->set('s', '');
							return $query;
						}

						$ordering_args = Listeo_Core_Listing::get_listings_ordering_args();
						// Check if we have AI search input first
						$ai_search_input = get_query_var('ai_search_input');
						$ai_search_post_ids = array();
						$preserve_ai_order = false;
						if (!empty($ai_search_input)) {
							$ai_search_post_ids = apply_filters('listeo_search_ai_post_ids', $ai_search_input);
							if (!empty($ai_search_post_ids) && is_array($ai_search_post_ids)) {
								$preserve_ai_order = true;
								// Store the AI order for later use in posts_orderby filter
								$query->set('listeo_ai_post_order', $ai_search_post_ids);
							}
						}
						if (!$preserve_ai_order) {
							if (isset($ordering_args['meta_key']) && $ordering_args['meta_key'] != '_featured') {
								$query->set('meta_key', $ordering_args['meta_key']);
							}
							$query->set('orderby', $ordering_args['orderby']);
							$query->set('order', $ordering_args['order']);
						}

						$keyword = Listeo_Core_Search::sanitize_keyword_search($keyword);

						$date_range =  (isset($_REQUEST['date_range'])) ? sanitize_text_field($_REQUEST['date_range']) : '';

						$keyword_search = get_option('listeo_keyword_search', 'search_title');
						$search_mode = get_option('listeo_search_mode', 'exact');
						// make wp_query show only listings that have _event_date meta field value in future	




						$keywords_post_ids = array();
						$location_post_ids = array();
						if ($search_mode == 'fibosearch') {
						} else {
							if ($search_mode == 'relevance') {
								if ($keyword) {

									// Combine title, content, and meta searches
									$search_terms = array_map('trim', explode('+', $keyword));
									$search_string = implode(' ', $search_terms);
									global $wpdb;
									$post_ids = $wpdb->get_col($wpdb->prepare(
										"SELECT DISTINCT post_id FROM {$wpdb->postmeta} 
											WHERE meta_key = 'keywords' 
											AND meta_value LIKE %s",
										'%' . $wpdb->esc_like($keyword) . '%'
									));
									if (!empty($post_ids)) {
										$keywords_post_ids = $post_ids;
										//clear default search
										$query->set('s', ''); // Clear the default search
									} else {

										// Set search parameters for wp_query
										$query->set('s', $search_string);
									}
								}
							} else if ($search_mode == 'searchwp') {
								// Use SearchWP to get post IDs matching the keyword
								// check if class SearchWP is exists
								if (!class_exists('SearchWP')) {
									return;
								}

								$searchwp_query = new \SearchWP\Query($keyword, [
									'engine'         => 'default',       // Replace with your engine name if different
									'fields'         => 'ids',           // Retrieve only the post IDs
									'posts_per_page' => -1,              // Get all matching posts
									'post_type'      => ['listing'],     // Limit search to 'listing' post type
								]);

								$keywords_post_ids = $searchwp_query->get_results();

								if (empty($keywords_post_ids)) {
									$keywords_post_ids = array(0); // No matching posts
								}
							} else if ($search_mode == 'exact' || $search_mode == 'approx') {


								if ($keyword) {
									global $wpdb;
									// Trim and explode keywords
									if ($search_mode == 'exact') {
										$keywords = array_map('trim', explode('+', $keyword));
									} else {
										$keywords = array_map('trim', explode(' ', $keyword));
									}

									// Setup SQL
									$posts_keywords_sql    = array();
									$postmeta_keywords_sql = array();
									// Loop through keywords and create SQL snippets
									foreach ($keywords as $keyword) {

										# code...
										if (strlen($keyword) > 2) {

											// Create post meta SQL
											if ($keyword_search == 'search_title') {

												$postmeta_keywords_sql[] = " meta_value LIKE '%" . esc_sql($keyword) . "%' AND meta_key IN ('listeo_subtitle','listing_title','listing_description','keywords') ";
											} else {
												$postmeta_keywords_sql[] = " meta_value LIKE '%" . esc_sql($keyword) . "%'";
											}

											// Create post title and content SQL
											$posts_keywords_sql[]    = " post_title LIKE '%" . esc_sql($keyword) . "%' OR post_content LIKE '%" . esc_sql($keyword) . "%' ";
										}
									}


									// Construct the final SQL queries using AND between different keywords
									if (!empty($postmeta_keywords_sql)) {
										$post_ids_meta = $wpdb->get_col("
								SELECT DISTINCT post_id FROM {$wpdb->postmeta}
								WHERE " . implode(' AND ', $postmeta_keywords_sql) . "
							");
									} else {
										$post_ids_meta = array();
									}

									if (!empty($posts_keywords_sql)) {
										$post_ids_posts = $wpdb->get_col("
								SELECT ID FROM {$wpdb->posts}
								WHERE " . implode(' AND ', $posts_keywords_sql) . "
								AND post_type = 'listing'
							");
									} else {
										$post_ids_posts = array();
									}


									// Merge and filter duplicates
									$keywords_post_ids = array_unique(array_merge($post_ids_meta, $post_ids_posts));
									if (empty($keywords_post_ids)) {
										$keywords_post_ids = array(0);
									}
								}
							}
						}
						$keywords_post_ids = apply_filters('listeo_search_keywords_post_ids', $keywords_post_ids, $keyword, $search_mode);


						$location = get_query_var('location_search');

						if ($location) {
							// Centralized location resolution: viewport vs radius vs text search.
							// Same helper is used by Listeo_Core_Listing to keep behavior identical
							// between AJAX search and standard archive page-load.
							// See listeo_core_resolve_location_post_ids() in listeo-core-template-functions.php.
							$resolved_location_ids = listeo_core_resolve_location_post_ids(array(
								'location'       => $location,
								'radius'         => get_query_var('search_radius'),
								'place_viewport' => get_query_var('place_viewport'),
								'place_type'     => get_query_var('place_type'),
							));
							if (is_array($resolved_location_ids)) {
								$location_post_ids = $resolved_location_ids;
							}
						}
						$post_ids = array();
						if (!empty($ai_search_post_ids)) {
							if (sizeof($keywords_post_ids) != 0 && sizeof($location_post_ids) != 0) {
								// Get intersection but preserve AI order
								$intersect_ids = array_intersect($ai_search_post_ids, array_intersect($keywords_post_ids, $location_post_ids));
								$post_ids = !empty($intersect_ids) ? $intersect_ids : array(0);
							} else if (sizeof($keywords_post_ids) != 0 && sizeof($location_post_ids) == 0) {
								// Preserve AI order within keyword results
								$post_ids = array_intersect($ai_search_post_ids, $keywords_post_ids);
							} else if (sizeof($keywords_post_ids) == 0 && sizeof($location_post_ids) != 0) {
								// Preserve AI order within location results
								$post_ids = array_intersect($ai_search_post_ids, $location_post_ids);
								if (empty($post_ids)) {
									$post_ids = array(0);
								}
							} else {
								// Only AI search results
								$post_ids = $ai_search_post_ids;
							}
						} else {
							// Original logic when no AI search

							if (sizeof($keywords_post_ids) != 0 && sizeof($location_post_ids) != 0) {
								$post_ids = array_intersect($keywords_post_ids, $location_post_ids);
								// If no intersection found, set sentinel value
								if (empty($post_ids)) {
									$post_ids = array(0);
								}
							} else if (sizeof($keywords_post_ids) != 0 && sizeof($location_post_ids) == 0) {
								$post_ids = $keywords_post_ids;
							} else if (sizeof($keywords_post_ids) == 0 && sizeof($location_post_ids) != 0) {
								$post_ids = $location_post_ids;
							}
						}

						// Set post__in if we have post IDs - array(0) forces no results when search found nothing
						if (!empty($post_ids)) {
							$query->set('post__in', $post_ids);

							// If we have AI search results, override ordering to preserve AI order
							if ($preserve_ai_order) {
								$query->set('orderby', 'post__in');
								$query->set('order', 'ASC');
								// Remove meta_key to avoid conflicts
								$query->set('meta_key', '');
							}
						}

						$query->set('post_type', 'listing');

						// DEBUG: Log what post__in is set to right before WordPress runs the query
						$current_post_in = $query->get('post__in');

						$args = array();

						// Handle drilldown-listing-types field
						$drilldown_listing_types = get_query_var('drilldown-listing-types');
						$listing_type_meta_queries = array();
						$drilldown_tax_queries = array();
						
						if ($drilldown_listing_types) {
							if (!is_array($drilldown_listing_types)) {
								$drilldown_listing_types = array($drilldown_listing_types);
							}
							
							foreach ($drilldown_listing_types as $selection) {
								// Check if this is a listing type selection (prefixed with 'listing_type_')
								if (strpos($selection, 'listing_type_') === 0) {
									$listing_type = str_replace('listing_type_', '', $selection);
									// Add meta query to filter by listing type
									$listing_type_meta_queries[] = array(
										'key' => '_listing_type',
										'value' => $listing_type,
										'compare' => '='
									);
								} else {
									// This is a taxonomy term with format "taxonomy:slug"
									if (strpos($selection, ':') !== false) {
										list($taxonomy, $term_slug) = explode(':', $selection, 2);
										
										// Verify the taxonomy exists and term is valid
										if (taxonomy_exists($taxonomy)) {
											$term = get_term_by('slug', $term_slug, $taxonomy);
											if ($term) {
												// Add taxonomy query for the specified taxonomy
												$drilldown_tax_queries[] = array(
													'taxonomy' => $taxonomy,
													'field'    => 'slug',
													'terms'    => $term_slug,
												);
											}
										}
									} else {
										// Fallback: Legacy format without taxonomy prefix
										// Find which taxonomy it belongs to (for backward compatibility)
										$found_taxonomy = null;
										$taxonomies = array('listing_category', 'service_category', 'rental_category', 'event_category', 'classifieds_category');
										
										foreach ($taxonomies as $tax_name) {
											$term = get_term_by('slug', $selection, $tax_name);
											if ($term) {
												$found_taxonomy = $tax_name;
												break;
											}
										}
										
										if ($found_taxonomy) {
											// Add taxonomy query for the found taxonomy
											$drilldown_tax_queries[] = array(
												'taxonomy' => $found_taxonomy,
												'field'    => 'slug',
												'terms'    => $selection,
											);
										}
									}
								}
							}
						}

						$tax_query = array(
							'relation' => get_option('listeo_taxonomy_or_and', 'AND')
						);
						$taxonomy_objects = get_object_taxonomies('listing', 'objects');

						foreach ($taxonomy_objects as $tax) {
							$get_tax = get_query_var('tax-' . $tax->name);

							if (is_array($get_tax)) {
								// Reset keys to numeric in case of associative arrays from checkboxes
								$get_tax = array_values($get_tax);
								if (empty($get_tax[0])) {
									continue;
								}
								$tax_query[$tax->name] = array('relation' => get_option('listeo_' . $tax->name . 'search_mode', 'OR'));

								foreach ($get_tax as $key => $value) {
									array_push($tax_query[$tax->name], array(
										'taxonomy' =>   $tax->name,
										'field'    =>   'slug',
										'terms'    =>   $value,

									));
								}
							} else {

								if ($get_tax) {

									$term = get_term_by('slug', $get_tax, $tax->name);
									if ($term) {
										array_push($tax_query, array(
											'taxonomy' =>  $tax->name,
											'field'    =>  'slug',
											'terms'    =>  $term->slug,
											'operator' =>  'IN'
										));
									}
								}
							}
						}

						// exlcude posts that are from ads
						// ads

						$category = get_query_var('tax-listing_category');
						$feature = get_query_var('tax-listing_feature');
						$region = get_query_var('tax-region');
						// region might be array, so we need to check if it is array
						if (is_array($region)) {
							$region = reset($region);
						}
						if (is_array($category)) {
							$category = reset($category);
						}
						if (is_array($feature)) {
							$feature = reset($feature);
						}


						$ad_filter = array(
							'listing_category' 	=> $category,
							'listing_feature'	=> $feature,
							'region' 			=> $region,
							'address' 			=> $location,
						);


						// get posts from ad
						$ads_ids = listeo_get_ids_listings_for_ads('search', $ad_filter);


						if (!empty($ads_ids)) {
							$query->set('post__not_in', $ads_ids);
						}




						// Merge drilldown taxonomy queries with existing tax_query
						if (!empty($drilldown_tax_queries)) {
							if (count($drilldown_tax_queries) > 1) {
								// If multiple drilldown taxonomy queries, use OR relation
								$drilldown_tax_group = array(
									'relation' => 'OR'
								);
								$drilldown_tax_group = array_merge($drilldown_tax_group, $drilldown_tax_queries);
								$tax_query[] = $drilldown_tax_group;
							} else {
								// Single drilldown taxonomy query
								$tax_query[] = $drilldown_tax_queries[0];
							}
						}

						$query->set('tax_query', $tax_query);

						// Apply listing type meta queries from drilldown-listing-types field
						if (!empty($listing_type_meta_queries)) {
							if (count($listing_type_meta_queries) > 1) {
								$listing_type_meta_queries['relation'] = 'OR';
							}
							// Get existing meta_query or create new one
							$existing_meta_query = $query->get('meta_query');
							if (!$existing_meta_query) {
								$existing_meta_query = array();
							}
							$existing_meta_query[] = $listing_type_meta_queries;
							$query->set('meta_query', $existing_meta_query);
						}

						$available_query_vars = $this->build_available_query_vars();

						$meta_queries = array();

						foreach ($available_query_vars as $key => $meta_key) {

							if (substr($meta_key, 0, 4) == "tax-") {
								continue;
							}
							// Exclude search parameters that should NOT be treated as meta queries
							if (in_array($meta_key, array(
								'_price_range',
								'ai_search_input',
								'drilldown-listing-types',
								'date_range',
								'location_search',      // Search location parameter
								'keyword_search',       // Keyword search parameter
								'search_radius',        // Radius search parameter
								'map_bounds',           // Map bounding box parameter
								'search_by_map_move',   // Map move search parameter
								'place_viewport',       // Viewport bounding box from Google Places
								'place_type'            // Place type from Google Places (country/region/city)
							), true)) {
								continue;
							}
							if ($meta_key === 'rating-filter') {
								$meta = get_query_var($meta_key);
								if (($meta && $meta !== 'any') || $meta === '0' || $meta === 0) {
									$meta_queries[] = array(
										'key'     => '_combined_rating',
										'value'   => sanitize_text_field($meta),
										'compare' => '>='
									);
								}
								continue;
							}

							$meta_min = $this->get_request_bound_value($meta_key . '_min');
							$meta_max = $this->get_request_bound_value($meta_key . '_max');

							$range_meta_key = $meta_key;
							if (substr($meta_key, -4) === '_min' || substr($meta_key, -4) === '_max') {
								$range_meta_key = substr($meta_key, 0, -4);
							}

							$range_clause_added = false;
							if ($range_meta_key && $meta_key !== '_price') {
								if ($meta_min !== null && $meta_max !== null) {
									$meta_queries[] = array(
										'key' => $range_meta_key,
										'value' => array($meta_min, $meta_max),
										'compare' => 'BETWEEN',
										'type' => 'NUMERIC'
									);
									$range_clause_added = true;
								} elseif ($meta_min !== null) {
									$meta_queries[] = array(
										'key' => $range_meta_key,
										'value' => $meta_min,
										'compare' => '>=',
										'type' => 'NUMERIC'
									);
									$range_clause_added = true;
								} elseif ($meta_max !== null) {
									$meta_queries[] = array(
										'key' => $range_meta_key,
										'value' => $meta_max,
										'compare' => '<=',
										'type' => 'NUMERIC'
									);
									$range_clause_added = true;
								}
							}

							if ($range_clause_added) {
								continue;
							}

							if ($meta_key === '_price') {
								$meta = get_query_var('_price_range');
								if ((is_string($meta) && $meta !== '' && $meta !== '-1') || is_numeric($meta)) {
									$range = array_map('absint', explode(',', $meta));

									$meta_queries[] = array(
										'relation' => 'OR',
										array(
											'relation' => 'OR',
											array(
												'key' => '_price_min',
												'value' => $range,
												'compare' => 'BETWEEN',
												'type' => 'NUMERIC',
											),
											array(
												'key' => '_price_max',
												'value' => $range,
												'compare' => 'BETWEEN',
												'type' => 'NUMERIC',
											),
											array(
												'key' => '_classifieds_price',
												'value' => $range,
												'compare' => 'BETWEEN',
												'type' => 'NUMERIC',
											),
											array(
												'key' => '_normal_price',
												'value' => $range,
												'compare' => 'BETWEEN',
												'type' => 'NUMERIC',
											),

										),
										array(
											'relation' => 'AND',
											array(
												'key' => '_price_min',
												'value' => $range[0],
												'compare' => '<=',
												'type' => 'NUMERIC',
											),
											array(
												'key' => '_price_max',
												'value' => $range[1],
												'compare' => '>=',
												'type' => 'NUMERIC',
											),

										),
									);
								}
								continue;
							}

							if (substr($meta_key, -4) === '_min' || substr($meta_key, -4) === '_max') {
								continue;
							}

							if ($meta_key === '_max_guests') {
								$meta = get_query_var($meta_key);
								if ($meta === '' || $meta === null || (is_array($meta) && empty($meta))) {
									if (isset($_REQUEST[$meta_key])) {
										$meta = $_REQUEST[$meta_key];
									}
								}

								if ($meta !== '' && $meta !== null && $meta !== -1) {
									$meta_queries[] = array(
										'key' => '_max_guests',
										'value' => floatval($meta),
										'compare' => '>=',
										'type' => 'NUMERIC'
									);
								}
								continue;
							}

							$meta = get_query_var($meta_key);
							if (($meta === '' || $meta === null || (is_array($meta) && empty($meta))) && isset($_REQUEST[$meta_key])) {
								$meta = $_REQUEST[$meta_key];
							}

							if ($meta === '' || $meta === null || $meta === false || $meta == -1) {
								continue;
							}

							if (is_array($meta)) {
								$valid_values = array();
								foreach ($meta as $meta_array_key => $meta_value) {
									if ($meta_value === 'on' && !empty($meta_array_key) && $meta_array_key !== 'none') {
										$valid_values[] = sanitize_text_field($meta_array_key);
									} elseif ($meta_value !== '' && $meta_value !== 'none' && $meta_value !== 'on') {
										$valid_values[] = sanitize_text_field($meta_value);
									}
								}

								if (!empty($valid_values)) {
									$meta_queries[] = array(
										'key'     => $meta_key,
										'value'   => array_values($valid_values),
										'compare' => 'IN'
									);
								}
							} else {
								$meta_value = is_scalar($meta) ? sanitize_text_field($meta) : $meta;

								if ($meta_value !== '' && $meta_value !== 'none') {
									$meta_queries[] = array(
										'key'   => $meta_key,
										'value' => $meta_value,
									);
								}
							}
						}

						if (!empty($meta_queries)) {
							$existing_meta_query = $query->get('meta_query');
							if (!is_array($existing_meta_query)) {
								$existing_meta_query = array();
							}

							$relation = 'AND';
							if (isset($existing_meta_query['relation'])) {
								$relation = $existing_meta_query['relation'];
								unset($existing_meta_query['relation']);
							}

							$normalized_meta_query = array('relation' => $relation);
							foreach ($existing_meta_query as $clause) {
								$normalized_meta_query[] = $clause;
							}
							foreach ($meta_queries as $clause) {
								$normalized_meta_query[] = $clause;
							}

							if (count($normalized_meta_query) > 1) {
								$query->set('meta_query', $normalized_meta_query);
							}
						}





						// For AJAX queries, get values from query object instead of global query vars
						if (defined('DOING_AJAX') && DOING_AJAX) {
							$listing_type = $query->get('_listing_type');
							$date_range = $query->get('date_range');
						} else {
							$listing_type = get_query_var('_listing_type');
							$date_range = get_query_var('date_range');
						}


						if ($date_range && $listing_type == 'event') {
							//check to apply only for events
							$dates = explode(' - ', $date_range);
							
							// Debug logging
							$wp_format = listeo_date_time_wp_format_php();
							// error_log("Event Date Search Debug - Date range: {$date_range}");
							// error_log("Event Date Search Debug - WP Format: {$wp_format}");
							// error_log("Event Date Search Debug - Start date: '{$dates[0]}', End date: '{$dates[1]}'");

							// Try primary format first
							$date_start_obj = DateTime::createFromFormat($wp_format . ' H:i:s', $dates[0] . ' 00:00:00');

							if ($date_start_obj) {
								$date_start = $date_start_obj->getTimestamp();
								//error_log("Event Date Search Debug - Start timestamp: {$date_start} (" . date('Y-m-d H:i:s', $date_start) . ")");
							} else {
								// Try fallback formats
								$fallback_formats = array('m/d/Y', 'd/m/Y', 'Y-m-d', 'Y/m/d');
								$date_start = false;
								
								foreach ($fallback_formats as $format) {
									$date_start_obj = DateTime::createFromFormat($format . ' H:i:s', $dates[0] . ' 00:00:00');
									if ($date_start_obj) {
										$date_start = $date_start_obj->getTimestamp();
										//error_log("Event Date Search Debug - Start timestamp (fallback {$format}): {$date_start} (" . date('Y-m-d H:i:s', $date_start) . ")");
										break;
									}
								}
								
								if (!$date_start) {
									//	error_log("Event Date Search Debug - Failed to parse start date '{$dates[0]}' with format '{$wp_format}' or fallbacks");
								}
							}

							// Try primary format first
							$date_end_obj = DateTime::createFromFormat($wp_format . ' H:i:s', $dates[1] . ' 23:59:59');

							if ($date_end_obj) {
								$date_end = $date_end_obj->getTimestamp();
								//error_log("Event Date Search Debug - End timestamp: {$date_end} (" . date('Y-m-d H:i:s', $date_end) . ")");
							} else {
								// Try fallback formats
								$fallback_formats = array('m/d/Y', 'd/m/Y', 'Y-m-d', 'Y/m/d');
								$date_end = false;
								
								foreach ($fallback_formats as $format) {
									$date_end_obj = DateTime::createFromFormat($format . ' H:i:s', $dates[1] . ' 23:59:59');
									if ($date_end_obj) {
										$date_end = $date_end_obj->getTimestamp();
									//	error_log("Event Date Search Debug - End timestamp (fallback {$format}): {$date_end} (" . date('Y-m-d H:i:s', $date_end) . ")");
										break;
									}
								}
								
								if (!$date_end) {
									// Failed to parse end date
								}
							}

							if ($date_start && $date_end) {
								// Comprehensive event date overlap detection
								// An event overlaps with search range if:
								// 1. Event starts within search range, OR
								// 2. Event ends within search range, OR  
								// 3. Event spans the entire search range (starts before, ends after), OR
								// 4. Single-day events within search range
								// OPTIMIZED: Use direct SQL query instead of complex meta_query
								// This prevents the slow 7-JOIN query that was taking 240+ seconds
								global $wpdb;

								// Single optimized SQL query to find all matching events
								$matching_event_ids = $wpdb->get_col($wpdb->prepare("
									SELECT DISTINCT p.ID
									FROM {$wpdb->posts} p
									INNER JOIN {$wpdb->postmeta} pm_type ON (p.ID = pm_type.post_id AND pm_type.meta_key = '_listing_type' AND pm_type.meta_value = 'event')
									LEFT JOIN {$wpdb->postmeta} pm_start ON (p.ID = pm_start.post_id AND pm_start.meta_key = '_event_date_timestamp')
									LEFT JOIN {$wpdb->postmeta} pm_end ON (p.ID = pm_end.post_id AND pm_end.meta_key = '_event_date_end_timestamp')
									WHERE p.post_type = 'listing'
									AND p.post_status = 'publish'
									AND (
										/* Case 1: Event start date within search range */
										(CAST(pm_start.meta_value AS SIGNED) BETWEEN %d AND %d)
										OR
										/* Case 2: Event end date within search range */
										(CAST(pm_end.meta_value AS SIGNED) BETWEEN %d AND %d)
										OR
										/* Case 3: Event spans entire search range (starts before, ends after) */
										(CAST(pm_start.meta_value AS SIGNED) <= %d AND CAST(pm_end.meta_value AS SIGNED) >= %d)
										OR
										/* Case 4: Single-day events (no end date) within search range */
										(CAST(pm_start.meta_value AS SIGNED) BETWEEN %d AND %d AND (pm_end.post_id IS NULL OR pm_end.meta_value = ''))
									)
								",
									$date_start, $date_end,  // Case 1
									$date_start, $date_end,  // Case 2
									$date_start, $date_end,  // Case 3
									$date_start, $date_end   // Case 4
								));

								// Allow Booking Plus (or any subscriber) to contribute recurring event
								// occurrences stored outside the legacy _event_date_timestamp meta.
								// Subscribers return an array of listing IDs whose occurrences fall in
								// the range, or null to fall through. Merged as a union so legacy events
								// continue to match via the SQL above.
								$bp_recurring_ids = apply_filters(
									'listeo_event_search_recurring_ids',
									null,
									wp_date('Y-m-d H:i:s', $date_start),
									wp_date('Y-m-d H:i:s', $date_end)
								);
								if (is_array($bp_recurring_ids) && !empty($bp_recurring_ids)) {
									$bp_recurring_ids = array_filter(
										array_map('intval', $bp_recurring_ids),
										function ($id) { return $id > 0; }
									);
									if (!empty($bp_recurring_ids)) {
										$matching_event_ids = array_values(array_unique(array_merge(
											(array) $matching_event_ids,
											$bp_recurring_ids
										)));
									}
								}

								// Use post__in to filter results instead of complex meta_query
								if (!empty($matching_event_ids)) {
									// Merge with existing post__in if it exists
									$existing_post_in = $query->get('post__in');

									if (!empty($existing_post_in) && is_array($existing_post_in)) {
										// Intersect with existing filters (keywords, location, etc.)
										$matching_event_ids = array_intersect($matching_event_ids, $existing_post_in);
									}

									if (!empty($matching_event_ids)) {
										$query->set('post__in', $matching_event_ids);
									} else {
										$query->set('post__in', array(0)); // No matches after intersection
									}
								} else {
									$query->set('post__in', array(0)); // No events match the date range
								}
							} else {
								// error_log("Event Date Search Debug - Date start or end could not be parsed, skipping event date filter");
								// error_log("Event Date Search Debug - date_start: " . ($date_start ? $date_start : 'false'));
								// error_log("Event Date Search Debug - date_end: " . ($date_end ? $date_end : 'false'));
							}
						}

						// Handle rental date search
						if ($date_range && $listing_type == 'rental') {

							$dates = explode(' - ', $date_range);
							$date_start = trim($dates[0]);
							$date_end = trim($dates[1]);

							// Get WordPress date format
							$wp_format = listeo_date_time_wp_format_php();

							// Parse dates to get proper format for database query
							// Try primary format first
							$date_start_object = DateTime::createFromFormat($wp_format, $date_start);
							$date_end_object = DateTime::createFromFormat($wp_format, $date_end);

							// Try fallback formats if primary format fails
							if (!$date_start_object || !$date_end_object) {
								$fallback_formats = array('m/d/Y', 'd/m/Y', 'Y-m-d', 'Y/m/d');

								if (!$date_start_object) {
									foreach ($fallback_formats as $format) {
										$date_start_object = DateTime::createFromFormat($format, $date_start);
										if ($date_start_object) {
											break;
										}
									}
								}

								if (!$date_end_object) {
									foreach ($fallback_formats as $format) {
										$date_end_object = DateTime::createFromFormat($format, $date_end);
										if ($date_end_object) {
											break;
										}
									}
								}
							}

							if ($date_start_object && $date_end_object) {
								$format_date_start = $date_start_object->format('Y-m-d');
								$format_date_end = $date_end_object->format('Y-m-d');
					
								// error_log("PRE_GET_POSTS RENTAL DEBUG - format_date_start: " . $format_date_start);
								// error_log("PRE_GET_POSTS RENTAL DEBUG - format_date_end: " . $format_date_end);
								
								// Find rentals that are NOT available during the search period
								global $wpdb;
								$query_sql = $wpdb->prepare("
									SELECT DISTINCT listing_id 
									FROM {$wpdb->prefix}bookings_calendar bc
									INNER JOIN {$wpdb->postmeta} pm ON bc.listing_id = pm.post_id 
									WHERE pm.meta_key = '_listing_type' 
									AND pm.meta_value = 'rental'
									AND bc.status NOT IN ('cancelled', 'expired')
									AND bc.type = 'reservation'
									AND (
										(bc.date_start < %s AND bc.date_end > %s)
										OR (bc.date_start < %s AND bc.date_end > %s)
										OR (bc.date_start >= %s AND bc.date_end <= %s)
										OR (bc.date_start = %s AND bc.date_end = %s)
									)
								", 
								$format_date_end,
								$format_date_start,
								$format_date_start,
								$format_date_start,
								$format_date_start,
								$format_date_end,
								$format_date_start,
								$format_date_end
								);
								
							//	error_log("PRE_GET_POSTS RENTAL DEBUG - SQL Query: " . $query_sql);
								$unavailable_rental_ids = $wpdb->get_col($query_sql);
							//	error_log("PRE_GET_POSTS RENTAL DEBUG - Unavailable rental IDs: " . print_r($unavailable_rental_ids, true));
								
								// Exclude unavailable rentals and ensure only rentals are shown
								if (!empty($unavailable_rental_ids)) {
									$query->set('post__not_in', $unavailable_rental_ids);
								//	error_log("PRE_GET_POSTS RENTAL DEBUG - Set post__not_in: " . print_r($unavailable_rental_ids, true));
								} else {
								//	error_log("PRE_GET_POSTS RENTAL DEBUG - No unavailable rentals found, all should be available");
								}
								
								// Ensure we only show rental listings
								$existing_meta_query = $query->get('meta_query');
							//	error_log("PRE_GET_POSTS RENTAL DEBUG - Existing meta_query before adding rental filter: " . print_r($existing_meta_query, true));
								
								if (!$existing_meta_query) {
									$existing_meta_query = array();
								}
								$existing_meta_query[] = array(
									'key'     => '_listing_type',
									'value'   => 'rental',
									'compare' => '='
								);
								$query->set('meta_query', $existing_meta_query);
							//	error_log("PRE_GET_POSTS RENTAL DEBUG - Set meta_query to filter rentals only");
								
								// Check if there are any rental listings at all
								$rental_check_query = $wpdb->prepare("
									SELECT COUNT(*) as count, GROUP_CONCAT(post_id) as ids
									FROM {$wpdb->postmeta} pm
									INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
									WHERE pm.meta_key = '_listing_type' 
									AND pm.meta_value = 'rental'
									AND p.post_type = 'listing'
									AND p.post_status = 'publish'
								");
								$rental_check = $wpdb->get_row($rental_check_query);
						
								// Add hook to capture the final SQL query
								add_filter('posts_request', function($sql) {
								
									return $sql;
								}, 10, 1);
							} else {
						
							}
						} else {
						
						}

						// Handle featured ordering (only if not preserving AI order)
						if (!$preserve_ai_order && isset($ordering_args['meta_key']) && $ordering_args['meta_key'] == '_featured') {
							$query->set('order', 'ASC DESC');
							$query->set('orderby', 'meta_value date');
							$query->set('meta_key', '_featured');
						}

						if (!$preserve_ai_order && isset($ordering_args['listeo_key']) && $ordering_args['listeo_key'] == '_event_date_timestamp') {




							$query->set('meta_query', [
								'relation' => 'OR',
								'has_event_date' => [
									'key' => '_event_date_timestamp',
									'value' => current_time('timestamp'),
									'compare' => '>',
									'type' => 'numeric'
								],
								'no_event_date' => [
									'key' => '_event_date_timestamp',
									'compare' => 'NOT EXISTS',
								],
							]);

							$query->set('orderby', [
								'has_event_date' => 'DESC',
								'event_date_distance' => 'ASC',
								'date' => 'DESC',
							]);

							$query->set('meta_type', 'NUMERIC');
							$query->set('listeo_custom_event_order', true);
							// Custom ordering function
							add_filter('posts_clauses', 'listeo_custom_event_clauses', 10, 2);
							// add_filter('posts_orderby', function ($orderby, $wp_query) use ($current_timestamp) {
							// 	if ($wp_query->is_main_query()) {
							// 		global $wpdb;
							// 		$orderby = "
							//     CASE
							//         WHEN {$wpdb->postmeta}.meta_key = '_event_date_timestamp' THEN 1
							//         ELSE 0
							//     END DESC,
							//     ABS({$wpdb->postmeta}.meta_value - $current_timestamp) ASC,
							//     {$wpdb->posts}.post_date DESC
							// ";
							// 	}
							// 	return $orderby;
							// }, 10, 2);
						}

						if (isset($args['rating-filter']) && $args['rating-filter'] != 'any') {

							$query_args['meta_query'][] = array(
								'relation' => 'OR',
								array(
									'key'     => '_combined_rating',
									'value'   => $args['rating-filter'],
									'compare' => '>=',
									'type'    => 'DECIMAL(3,2)'
								),
								// Also include posts that might need migration (fallback to old rating field)
								array(
									'relation' => 'AND',
									array(
										'key'     => '_combined_rating',
										'compare' => 'NOT EXISTS'
									),
									array(
										'key'     => 'listeo-avg-rating',
										'value'   => $args['rating-filter'],
										'compare' => '>=',
										'type'    => 'DECIMAL(3,2)'
									)
								)
							);
						}

						if (!empty($meta_queries)) {
							$query->set('meta_query', array(
								'relation' => 'AND',
								$meta_queries
							));
						}
					}
					// show in log full query arguments

					// DEBUG: Final check before WordPress executes
					$final_post_in = $query->get('post__in');

					return $query;
				} /*eof function*/


				public function ajax_get_listings()
				{


					global $wp_post_types;

					$template_loader = new Listeo_Core_Template_Loader;

					if (isset($_REQUEST['keyword_search']) && self::is_keyword_search_too_long(wp_unslash($_REQUEST['keyword_search']))) {
						wp_send_json_error(array(
							'message' => self::get_keyword_search_too_long_message(),
						), 400);
					}

					if (self::is_keyword_search_bot_request($_REQUEST)) {
						wp_send_json_error(array(
							'message' => self::get_keyword_search_bot_message(),
						), 403);
					}

					$location  	= (isset($_REQUEST['location_search'])) ? sanitize_text_field(stripslashes($_REQUEST['location_search'])) : '';
					$keyword   	= (isset($_REQUEST['keyword_search'])) ? self::sanitize_keyword_search($_REQUEST['keyword_search']) : '';
					$radius   	= (isset($_REQUEST['search_radius'])) ?  sanitize_text_field(stripslashes($_REQUEST['search_radius'])) : '';
					$rating   	= (isset($_REQUEST['rating-filter'])) ?  sanitize_text_field(stripslashes($_REQUEST['rating-filter'])) : '';

					// Check if this is an append request for infinite scroll
					$append_mode = (isset($_REQUEST['append']) && $_REQUEST['append'] === 'true') ? true : false;
					
					// error_log('=== LISTEO AJAX DEBUG ===');
					// error_log('Location: ' . $location);
					// error_log('Keyword: ' . $keyword);
					// error_log('Radius: ' . $radius);
					// error_log('All REQUEST data: ' . print_r($_REQUEST, true));

					$ai_search_input = (isset($_REQUEST['ai_search_input'])) ? sanitize_text_field(stripslashes($_REQUEST['ai_search_input'])) : '';
					$orderby   	= (isset($_REQUEST['orderby'])) ?  sanitize_text_field(stripslashes($_REQUEST['orderby'])) : '';
					$order   	= (isset($_REQUEST['order'])) ?  sanitize_text_field(stripslashes($_REQUEST['order'])) : '';

					$style   	= sanitize_text_field(stripslashes($_REQUEST['style']));
					$grid_columns  = sanitize_text_field(stripslashes($_REQUEST['grid_columns']));
					$per_page   = sanitize_text_field(stripslashes($_REQUEST['per_page']));
					$date_range =  (isset($_REQUEST['date_range'])) ? sanitize_text_field($_REQUEST['date_range']) : '';


					$region   	= '';
					if (isset($_REQUEST['tax-region'])) {
						$region = is_array($_REQUEST['tax-region']) ? array_map('sanitize_text_field', array_values($_REQUEST['tax-region'])) : sanitize_text_field($_REQUEST['tax-region']);
					}
					$category   	= '';
					if (isset($_REQUEST['tax-listing_category'])) {
						$category = is_array($_REQUEST['tax-listing_category']) ? array_map('sanitize_text_field', array_values($_REQUEST['tax-listing_category'])) : sanitize_text_field($_REQUEST['tax-listing_category']);
					}

					$feature   	= '';
					if (isset($_REQUEST['tax-listing_feature'])) {
						$feature = is_array($_REQUEST['tax-listing_feature']) ? array_map('sanitize_text_field', array_values($_REQUEST['tax-listing_feature'])) : sanitize_text_field($_REQUEST['tax-listing_feature']);
					}

					$open_now = (isset($_REQUEST['open_now'])) ? sanitize_text_field($_REQUEST['open_now']) : '';

					$map_bounds = array();
					if (isset($_REQUEST['map_bounds']) && is_array($_REQUEST['map_bounds'])) {
						$map_bounds = array_map('sanitize_text_field', $_REQUEST['map_bounds']);
					}
					$search_by_map_move = (isset($_REQUEST['search_by_map_move'])) ? sanitize_text_field($_REQUEST['search_by_map_move']) : '';

					// Capture place viewport from Google Places for viewport-based search
					$place_viewport = array();
					if (isset($_REQUEST['place_viewport']) && is_array($_REQUEST['place_viewport'])) {
						$place_viewport = array_map('sanitize_text_field', $_REQUEST['place_viewport']);
					}
					$place_type = isset($_REQUEST['place_type']) ? sanitize_text_field($_REQUEST['place_type']) : '';


					$date_start = '';
					$date_end = '';

					if ($date_range) {

						$dates = explode(' - ', $date_range);
						$date_start = $dates[0];
						$date_end = $dates[1];

						// $date_start = esc_sql ( date( "Y-m-d H:i:s", strtotime(  $date_start )  ) );
						//    $date_end = esc_sql ( date( "Y-m-d H:i:s", strtotime( $date_end ) )  );

					}

					if (empty($per_page)) {
						$per_page = get_option('listeo_listings_per_page', 10);
					}

					$query_args = array(
						'ignore_sticky_posts'    => 1,
						'post_type'         => 'listing',
						'orderby'           => $orderby,
						'order'             =>  $order,
						'offset'            => (absint($_REQUEST['page']) - 1) * absint($per_page),
						'location'   		=> $location,
						'keyword'   		=> $keyword,
						'ai_search_input'   => $ai_search_input,
						'search_radius'   	=> $radius,
						'rating-filter'   	=> $rating,
						'posts_per_page'    => $per_page,
						'date_start'    	=> $date_start,
						'date_end'    		=> $date_end,
						'tax-region'    		=> $region,
						'tax-listing_feature'   => $feature,
						'tax-listing_category'  => $category,
						'open_now' => $open_now,
						'map_bounds' => $map_bounds,
						'search_by_map_move' => $search_by_map_move,
						'place_viewport' => $place_viewport,
						'place_type' => $place_type

					);


					$query_args['listeo_orderby'] = (isset($_REQUEST['listeo_core_order'])) ? sanitize_text_field($_REQUEST['listeo_core_order']) : false;

					$taxonomy_objects = get_object_taxonomies('listing', 'objects');
					foreach ($taxonomy_objects as $tax) {
						// Check both tax-TAXONOMY (standard) and _tax_TAXONOMY (meta field with taxonomy) prefixes
						$request_key = false;
						if (isset($_REQUEST['tax-' . $tax->name])) {
							$request_key = 'tax-' . $tax->name;
						} elseif (isset($_REQUEST['_tax_' . $tax->name])) {
							$request_key = '_tax_' . $tax->name;
						}

						if ($request_key) {
							// check if request is array and it's not empty
							if (is_array($_REQUEST[$request_key]) && !empty($_REQUEST[$request_key])) {

								$raw_values = $_REQUEST[$request_key];

								// For checkbox arrays where all values are 'on', use keys as term slugs
								$non_on_values = array_filter($raw_values, function($v) { return $v !== 'on'; });
								if (empty($non_on_values) && !empty($raw_values)) {
									$tax_array = array_map('sanitize_text_field', array_keys($raw_values));
								} else {
									$tax_array = array_map('sanitize_text_field', array_values($raw_values));
								}

								// check if first value is empty, if yes then skip it
								if (!empty($tax_array[0])) {
									$query_args['tax-' . $tax->name] = $tax_array;
								}
							} else if (!is_array($_REQUEST[$request_key]) && !empty($_REQUEST[$request_key])) {
								// if it's not array, we can just sanitize it
								$query_args['tax-' . $tax->name] = sanitize_text_field($_REQUEST[$request_key]);
							}
						}
					}


					$available_query_vars = $this->build_available_query_vars();
					foreach ($available_query_vars as $key => $meta_key) {

						if (substr($meta_key, 0, 4) == "tax-") {
							continue;
						}

						// Skip _tax_ prefixed fields - they are handled as taxonomy queries above
						if (substr($meta_key, 0, 5) == "_tax_") {
							// Check if this corresponds to a registered taxonomy
							$possible_tax = substr($meta_key, 5);
							if (taxonomy_exists($possible_tax)) {
								continue;
							}
						}

						// Exclude search parameters that should NOT be treated as meta queries
						// These are already handled separately in the location/keyword search logic
						if (in_array($meta_key, array(
							'location_search',      // Handled by viewport/radius search
							'keyword_search',       // Handled by keyword search
							'search_radius',        // Handled by radius search
							'map_bounds',           // Handled by map move search
							'search_by_map_move',   // Handled by map move search
							'place_viewport',       // Handled by viewport search
							'place_type'            // Handled by viewport search
						), true)) {
							continue;
						}

						if (isset($_REQUEST[$meta_key]) && $_REQUEST[$meta_key] != -1) {
							if (is_array($_REQUEST[$meta_key]) && !empty(array_values($_REQUEST[$meta_key])[0])) {
								// if it's array, we need to sanitize each value
								$query_args[$meta_key] = array_map('sanitize_text_field', array_values($_REQUEST[$meta_key]));
							} else {
								$query_args[$meta_key] = $_REQUEST[$meta_key];
							}
						}
					}
				



					// add meta boxes support

					$orderby = isset($_REQUEST['listeo_core_order']) ? $_REQUEST['listeo_core_order'] : 'date';

					// Handle distance-based sorting
					if ($orderby === 'distance' && !empty($location)) {
						// Geocode the location to get coordinates
						$latlng = listeo_core_geocode($location);
						if (!empty($latlng) && is_array($latlng) && count($latlng) >= 2) {
							// Add coordinates to query args for distance calculation
							$query_args['listeo_user_lat'] = floatval($latlng[0]);
							$query_args['listeo_user_lng'] = floatval($latlng[1]);
							$query_args['listeo_distance_unit'] = get_option('listeo_radius_unit', 'km');
							
							// Apply default radius if none specified for performance optimization
							if (empty($radius)) {
								$default_radius = get_option('listeo_distance_default_radius', 50);
								$query_args['listeo_default_radius'] = intval($default_radius);
							}
						}
					}

					// if ( ! is_null( $featured ) ) {
					// 	$featured = ( is_bool( $featured ) && $featured ) || in_array( $featured, array( '1', 'true', 'yes' ) ) ? true : false;
					// }

					// Add date range parameters for rental bookings
					if ($date_start && $date_end) {
						$query_args['date_start'] = $date_start;
						$query_args['date_end'] = $date_end;
					}

					$listings = Listeo_Core_Listing::get_real_listings(apply_filters('listeo_core_output_defaults_args', $query_args));

					// DEBUG: Log query results
					if (isset($listings->query_vars['post__in'])) {
					}

				// Calculate next page count for map button
				$current_page = isset($_REQUEST['page']) ? absint($_REQUEST['page']) : 1;
				$next_page_count = 0;
				if ($current_page < $listings->max_num_pages) {
					$remaining_listings = $listings->found_posts - ($current_page * $per_page);
					$next_page_count = min($per_page, $remaining_listings);
				}
				
				$result = array(
					'found_listings'    => $listings->have_posts(),
					'max_num_pages' => $listings->max_num_pages,
					'next_page_count' => $next_page_count,
					'current_page' => $current_page,
					'has_more_pages' => ($current_page < $listings->max_num_pages),
					'total_found' => $listings->found_posts,
					'append' => $append_mode,
				);

					ob_start();
					if ($result['found_listings']) {
						$style_data = array(
							'style' 		=> $style,
							//				'class' 		=> $custom_class, 
							//'in_rows' 		=> $in_rows, 
							'grid_columns' 	=> $grid_columns,
							'max_num_pages'	=> $listings->max_num_pages,
							'counter'		=> $listings->found_posts
						);
						//$template_loader->set_template_data( $style_data )->get_template_part( 'listings-start' ); 
					?>
			<div class="loader-ajax-container" style="">
				<div class="loader-ajax"></div>
			</div>


			<?php
						// get posts from ad
						$ad_filter = array(
							'listing_category' 	=> $category,
							'listing_feature'	=> $feature,
							'region' 			=> $region,
							'address' 			=> $location,
						);

						// get posts from ad
						$ads = listeo_get_ids_listings_for_ads('search', $ad_filter);

						if (!empty($ads)) {
							$ad_posts_count = count($ads);
							$ad_posts_index = 0;
							$ads_args = array(
								'post_type' => 'listing',
								'post_status' => 'publish',
								'posts_per_page' => 4,
								'orderby' => 'rand',

								'post__in' => $ads,
							);
							$ads_query = new \WP_Query($ads_args);
							if($style  == "list_old"){
								$style = "old";
							}
							if($style  == "grid_old"){
								$style = "grid-old";
							}
							if ($ads_query->have_posts()) {
								while ($ads_query->have_posts()) {
									$ads_query->the_post();
									$ad_posts_index++;
									$ad_data = array(
										'ad' => true,
										'ad_id' => get_the_ID(),
									);
									// merge ad data with style data
									$stylead_data = array_merge($style_data, $ad_data);
									$template_loader->set_template_data($stylead_data)->get_template_part('content-listing', $style);
								}
							}
							// reset post data
							wp_reset_postdata();
						}
						if ($style  == "list_old") {
							$style = "-old";
						}
						if ($style  == "grid_old") {
							$style = "grid-old";
						}
						
						while ($listings->have_posts()) {
							$listings->the_post();

							$template_loader->set_template_data($style_data)->get_template_part('content-listing', $style);
						}
			?>
			<div class="clearfix"></div>
			</div>
		<?php
						//$template_loader->set_template_data( $style_data )->get_template_part( 'listings-end' ); 
					} else {
		?>
			<div class="loader-ajax-container">
				<div class="loader-ajax"></div>
			</div>
			<?php
						$template_loader->get_template_part('archive/no-found');
			?><div class="clearfix"></div>
			<?php
					}

					$result['html'] = ob_get_clean();

					// Only include pagination HTML if not in append mode and infinite scroll is disabled
					$infinite_scroll = get_option('listeo_listeo_infinite_scroll', 'off');
					if (!$append_mode && $infinite_scroll === 'off') {
						$result['pagination'] = listeo_core_ajax_pagination($listings->max_num_pages, absint($_REQUEST['page']));
					} else {
						$result['pagination'] = ''; // Empty pagination for infinite scroll
					}

					// Add user location data for map marker and radius circle
					if (!empty($location) && !empty($radius)) {
						$geocoding_provider = get_option('listeo_geocoding_provider', 'google');
						$api_key = '';
						if ($geocoding_provider == 'google') {
							$api_key = get_option('listeo_maps_api_server');
						} else {
							$api_key = get_option('listeo_geoapify_maps_api_server');
						}

						if (!empty($api_key)) {
							$latlng = listeo_core_geocode($location);
							if (!empty($latlng) && is_array($latlng) && count($latlng) >= 2) {
								$result['user_location'] = array(
									'lat' => floatval($latlng[0]),
									'lng' => floatval($latlng[1]),
									'radius' => intval($radius),
									'address' => $location,
									'unit' => get_option('listeo_radius_unit', 'km')
								);
							}
						}
					}

					wp_send_json($result);
				}

				public function ajax_get_features_from_category()
				{

					$categories  = (isset($_REQUEST['cat_ids'])) ? $_REQUEST['cat_ids'] : '';

					$panel  =  (isset($_REQUEST['panel'])) ? $_REQUEST['panel'] : '';
					$success = true;
					$clean_data = array();
					ob_start();
					$clean_data[] = array(
						'text' => __('Any feature', 'listeo_core'),
						'id' =>  '0',
					);

					if ($categories) {
						$features = array();

						foreach ($categories as $category) {
							$cat_object = null;
							
							// Check if category contains taxonomy prefix (e.g., "service_category:service-cat-1")
							if (strpos($category, ':') !== false) {
								$parts = explode(':', $category, 2);
								$taxonomy = $parts[0];
								$term_identifier = $parts[1];
								
								if (!empty($term_identifier)) {
									if (is_numeric($term_identifier)) {
										$cat_object = get_term_by('id', $term_identifier, $taxonomy);
									} else {
										$cat_object = get_term_by('slug', $term_identifier, $taxonomy);
									}
								}
							} else {
								// Legacy format - search through all listing taxonomies
								$listing_taxonomies = get_object_taxonomies('listing');
								
								foreach ($listing_taxonomies as $taxonomy) {
									if (is_numeric($category)) {
										$temp_object = get_term_by('id', $category, $taxonomy);
									} else {
										$temp_object = get_term_by('slug', $category, $taxonomy);
									}
									
									if ($temp_object && !is_wp_error($temp_object)) {
										$cat_object = $temp_object;
										break; // Found it, no need to continue searching
									}
								}
							}
							
							if ($cat_object) {
								$features_temp = get_term_meta($cat_object->term_id, 'listeo_taxonomy_multicheck', true);
								if ($features_temp) {
									$features = array_merge($features, $features_temp);
								}
								$features = array_unique($features);
							}
						}


						if ($features) {
							if ($panel != 'false') { ?>
					<div class="panel-checkboxes-container">
						<?php
								$groups = array_chunk($features, 4, true);

								foreach ($groups as $group) { ?>

							<?php foreach ($group as $feature) {
										$feature_obj = get_term_by('slug', $feature, 'listing_feature');
										if (!$feature_obj) {
											continue;
										}
										$clean_data[] = array(
											'text' => $feature_obj->name,
											'id' =>  $feature,
										);

							?>
								<div class="panel-checkbox-wrap">
									<input form="listeo_core-search-form" id="<?php echo esc_html($feature) ?>" value="<?php echo esc_html($feature) ?>" type="checkbox" name="tax-listing_feature[<?php echo esc_html($feature); ?>]">
									<label for="<?php echo esc_html($feature) ?>"><?php echo $feature_obj->name; ?></label>
								</div>
							<?php } ?>


						<?php } ?>

					</div>
					<?php } else {

								foreach ($features as $feature) {
									$feature_obj = get_term_by('slug', $feature, 'listing_feature');
									if (!$feature_obj) {
										continue;
									}
									$clean_data[] = array(
										'text' => $feature_obj->name,
										'id' =>  $feature,
									);
					?>
						<input form="listeo_core-search-form" id="<?php echo esc_html($feature) ?>" value="<?php echo esc_html($feature) ?>" type="checkbox" name="tax-listing_feature[<?php echo esc_html($feature); ?>]">
						<label for="<?php echo esc_html($feature) ?>"><?php echo $feature_obj->name; ?></label>
					<?php }
							}
						} else {
							if ($cat_object && isset($cat_object->name)) {
								$success = false; ?>
					<div class="notification notice <?php if ($panel) {
														echo "col-md-12";
													} ?>">
						<p>
							<?php printf(__('Category "%s" doesn\'t have any additional filters', 'listeo_core'), $cat_object->name)  ?>

						</p>
					</div>
				<?php } else {
								$success = false; ?>
					<div class="notification warning">
						<p><?php esc_html_e('Please choose category to display filters', 'listeo_core') ?></p>
					</div>
			<?php }
						}
					} else {
						$success = false; ?>
			<div class="notification warning">
				<p><?php esc_html_e('Please choose category to display filters', 'listeo_core') ?></p>
			</div>
			<?php }

					$result['output'] = ob_get_clean();
					$result['data'] = $clean_data;
					$result['success'] = $success;
					wp_send_json($result);
				}

				public function ajax_get_features_ids_from_category()
				{

					$categories  = isset($_REQUEST['cat_ids']) ? $_REQUEST['cat_ids'] : false;
					$panel  =  $_REQUEST['panel'];
					$selected  =  isset($_REQUEST['selected']) ? $_REQUEST['selected'] : false;
					$listing_id  =  isset($_REQUEST['listing_id']) ? $_REQUEST['listing_id'] : false;
					$success = true;
					if (!$selected) {
						if ($listing_id) {
							$selected_check = wp_get_object_terms($listing_id, 'listing_feature', array('fields' => 'ids'));
							if (! empty($selected_check)) {
								if (! is_wp_error($selected_check)) {
									$selected = $selected_check;
								}
							}
						}
					};
					ob_start();

					if ($categories) {

						$features = array();
						
						// Get all listing taxonomies to search through
						$listing_taxonomies = get_object_taxonomies('listing');
						
						foreach ($categories as $category) {
							// Try to find the term in any listing taxonomy
							$cat_object = null;
							
							foreach ($listing_taxonomies as $taxonomy) {
								if (is_numeric($category)) {
									$temp_object = get_term_by('id', $category, $taxonomy);
								} else {
									$temp_object = get_term_by('slug', $category, $taxonomy);
								}
								
								if ($temp_object && !is_wp_error($temp_object)) {
									$cat_object = $temp_object;
									break; // Found it, no need to continue searching
								}
							}

							if ($cat_object) {
								$features_temp = get_term_meta($cat_object->term_id, 'listeo_taxonomy_multicheck', true);
								if ($features_temp) {
									foreach ($features_temp as $key => $value) {
										$features[] = $value;
									}
								}
							}
						}

						$features = array_unique($features);

						if ($features) {
							if ($panel != 'false') { ?>
					<div class="panel-checkboxes-container">
						<?php
								$groups = array_chunk($features, 4, true);

								foreach ($groups as $group) { ?>

							<?php foreach ($group as $feature) {
										$feature_obj = get_term_by('slug', $feature, 'listing_feature');
										if (!$feature_obj) {
											continue;
										}

							?>
								<div class="panel-checkbox-wrap">
									<input form="listeo_core-search-form" value="<?php echo esc_html($feature_obj->term_id) ?>" type="checkbox" id="in-listing_feature-<?php echo esc_html($feature_obj->term_id) ?>" name="tax_input[listing_feature][]">
									<label for="in-listing_feature-<?php echo esc_html($feature_obj->term_id) ?>"><?php echo $feature_obj->name; ?></label>
								</div>
							<?php } ?>


						<?php } ?>

					</div>
					<?php } else {


								foreach ($features as $feature) {
									$feature_obj = get_term_by('slug', $feature, 'listing_feature');
									if (!$feature_obj) {
										continue;
									}
					?>
						<input <?php if ($selected) checked(in_array($feature_obj->term_id, $selected)); ?>value="<?php echo esc_html($feature_obj->term_id) ?>" type="checkbox" id="in-listing_feature-<?php echo esc_html($feature_obj->term_id) ?>" name="tax_input[listing_feature][]">
						<label id="label-in-listing_feature-<?php echo esc_html($feature_obj->term_id) ?>" for="in-listing_feature-<?php echo esc_html($feature_obj->term_id) ?>"><?php echo $feature_obj->name; ?></label>
					<?php }
							}
						} else {
							if ($cat_object) {


								if ($cat_object->name) {
									$success = false; ?>
						<div class="notification notice <?php if ($panel) {
															echo "col-md-12";
														} ?>">
							<p>
								<?php printf(__('Category "%s" doesn\'t have any additional filters', 'listeo_core'), $cat_object->name)  ?>

							</p>
						</div>
					<?php
								}
							} else {
								$success = false; ?>
					<div class="notification warning">
						<p><?php esc_html_e('Please choose category to display filters', 'listeo_core') ?></p>
					</div>
			<?php }
						}
					} else {
						$success = false; ?>
			<div class="notification warning">
				<p><?php esc_html_e('Please choose category to display filters', 'listeo_core') ?></p>
			</div>
		<?php }

					$result['output'] = ob_get_clean();
					$result['success'] = $success;
					wp_send_json($result);
				}

				public function ajax_get_listing_types_from_categories()
				{
					$categories  = isset($_REQUEST['cat_ids']) ? $_REQUEST['cat_ids'] : false;

					$success = true;
					$types = array();

					if ($categories) {


						foreach ($categories as $category) {
							if (is_numeric($category)) {
								$cat_object = get_term_by('id', $category, 'listing_category');
							} else {
								$cat_object = get_term_by('slug', $category, 'listing_category');
							}

							if ($cat_object) {
								$types_temp = get_term_meta($cat_object->term_id, 'listeo_taxonomy_type', true);
								if ($types_temp) {
									foreach ($types_temp as $key => $value) {
										$types[] = $value;
									}
								}
							}
						}
					}
					$result['output'] = $types;
					$result['success'] = $success;
					wp_send_json($result);
				}

				//sidebar
				public static function get_search_fields()
				{


					$currency_abbr = get_option('listeo_currency');

					$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
					$search_fields = array(

						'keyword_search' => array(
							'placeholder'	=> __('What are you looking for?', 'listeo_core'),
							'key'			=> 'keyword_search',
							'class'			=> 'col-md-12',
							'name'			=> 'keyword_search',
							'priority'		=> 1,
							'place'			=> 'main',
							'type' 			=> 'text',
						),
						'category' => array(
							'placeholder'	=> __('All Categories', 'listeo_core'),
							'key'			=> '_category',
							'class'			=> 'col-md-12 ',
							'name'			=> 'tax-listing_category',
							'priority'		=> 2,
							'place'			=> 'main',
							'type' 			=> 'drilldown-taxonomy',
							'taxonomy' 		=> 'listing_category',
						),
						'date_range' => array(
							'placeholder'	=> __('Check-In - Check-Out', 'listeo_core'),
							'key'			=> '_date_range',
							'name'			=> 'date_range',
							'type' 			=> 'date-range',
							'place'			=> 'main',
							'class'			=> 'col-md-12',
							'priority'		=> 3,
						),

						'location_search' => array(
							'placeholder'	=> __('Location', 'listeo_core'),
							'key'			=> 'location_search',
							'class'			=> 'col-md-12',
							'css_class'		=> 'input-with-icon location',
							'name'			=> 'location_search',
							'priority'		=> 4,
							'place'			=> 'main',
							'type' 			=> 'location',
						),


						'radius' => array(
							'placeholder' 	=> __('Radius around selected destination', 'listeo_core'),
							'key'			=> 'search_radius',
							'class'			=> 'col-md-12',
							'css_class'		=> 'margin-top-30',
							'name'			=> 'search_radius',
							'priority'		=> 5,
							'place'			=> 'main',
							'type' 			=> 'radius',
							'max' 			=> '100',
							'min' 			=> '1',
						),




						'rating' => array(
							'placeholder' 	=> __('Rating', 'listeo_core'),
							'key'			=> '_rating',
							'class'			=> 'col-md-12',
							'name'			=> '_rating',
							'priority'		=> 6,
							'place'			=> 'main',
							'type' 			=> 'rating',


						),
						'price_range' => array(
							'placeholder' 	=> __('Price Filter', 'realteo'),
							'key'			=> '_price',
							'class'			=> 'col-md-12',
							'css_class'		=> '',
							'name'			=> '_price',
							'priority'		=> 7,
							'place'			=> 'main',
							'type' 			=> 'slider',
							'max' 			=> 'auto',
							'min' 			=> 'auto',
							'unit' 			=> $currency_symbol,

						),


						'features' => array(
							'placeholder' 	=> __('Features', 'listeo_core'),
							'key'			=> '_features',
							'class'			=> 'col-md-12',
							'name'			=> 'tax-listing_feature',
							'priority'		=> 8,
							'options'		=> array(),
							'place'			=> 'adv',
							'type' 			=> 'multi-checkbox',
							'taxonomy' 		=> 'listing_feature',
							'dynamic' 		=> (get_option('listeo_dynamic_features') == "on") ? "yes" : "no",
						),
					);

					$fields = listeo_core_sort_by_priority(apply_filters('listeo_core_search_fields', $search_fields));

					return $fields;
				}

				public static function get_search_fields_half()
				{

					$search_fields = array(

						'keyword_search' => array(
							'placeholder'	=> __('What are you looking for?', 'listeo_core'),
							'key'			=> 'keyword_search',
							'class'			=> 'col-fs-6',
							'name'			=> 'keyword_search',
							'priority'		=> 1,
							'place'			=> 'main',
							'type' 			=> 'text',
						),
						'location_search' => array(
							'placeholder'	=> __('Location', 'listeo_core'),
							'key'			=> 'location_search',
							'class'			=> 'col-fs-6',
							'css_class'		=> 'input-with-icon location',
							'name'			=> 'location_search',
							'priority'		=> 1,
							'place'			=> 'main',
							'type' 			=> 'location',
						),
						'category' => array(
							'placeholder'	=> __('Categories', 'listeo_core'),
							'key'			=> '_category',
							'name'			=> 'tax-listing_category',
							'type' 			=> 'multi-checkbox-row',
							'place'			=> 'panel',
							'taxonomy' 		=> 'listing_category',
						),
						'features' => array(
							'placeholder'	=> __('More Filters', 'listeo_core'),
							'key'			=> '_category',
							'name'			=> 'tax-listing_feature',
							'type' 			=> 'multi-checkbox-row',
							'place'			=> 'panel',
							'taxonomy' 		=> 'listing_feature',
							'dynamic' 		=> (get_option('listeo_dynamic_features') == "on") ? "yes" : "no",
						),
						'radius' => array(
							'placeholder'	=> __('Distance Radius', 'listeo_core'),
							'key'			=> 'search_radius',
							'name'			=> 'search_radius',
							'type' 			=> 'radius',
							'place'			=> 'panel',
							'max' 			=> '100',
							'min' 			=> '1',
						),
						'price' => array(
							'placeholder'	=> __('Price Filter', 'listeo_core'),
							'key'			=> '',
							'name'			=> '_price',
							'type' 			=> 'slider',
							'place'			=> 'panel',
							'max' 			=> 'auto',
							'min' 			=> 'auto',

						),
						'rating' => array(
							'placeholder' 	=> __('Rating', 'listeo_core'),
							'key'			=> '_rating',
							'name'			=> '_rating',
							'place'			=> 'panel',
							'type' 			=> 'rating',
						),

						'submit' => array(
							'class'			=> 'button fs-map-btn right',
							'open_row'		=> false,
							'close_row'		=> false,
							'place'			=> 'panel',
							'name' 			=> 'submit',
							'type' 			=> 'submit',
							'placeholder'	=> __('Search', 'listeo_core'),
						),
					);
					if (is_post_type_archive('listing')) {
						$top_buttons_conf = get_option('listeo_listings_top_buttons_conf');
						if ($top_buttons_conf) {

							if (get_option('pp_listings_top_layout') != 'half') {
								if (!in_array('filters', $top_buttons_conf)) {
									unset($search_fields['features']);
									unset($search_fields['category']);
								}
								if (!in_array('radius', $top_buttons_conf)) {
									unset($search_fields['radius']);
								}
							}
						}
						// 	'filters' (length=7)
						// 2 => string 'radius'

					}

					return apply_filters('listeo_core_search_fields_half', $search_fields);
				}

				public static function get_search_fields_home()
				{

					$search_fields = array(
						// 'order' => array(
						// 	'placeholder'	=> __( 'Hidden order', 'listeo_core' ),
						// 	'key'			=> 'listeo_core_order',
						// 	'name'			=> 'listeo_core_order',
						//    	'place'			=> 'main',
						// 	'type' 			=> 'hidden',
						// ),	
						// 'search_radius' => array(
						// 	'placeholder'	=> __( 'Radius hidde', 'listeo_core' ),
						// 	'key'			=> 'search_radius',
						// 	'name'			=> 'search_radius',
						//    	'place'			=> 'main',
						// 	'type' 			=> 'hidden',
						// ),	
						'keyword_search' => array(
							'placeholder'	=> __('What are you looking for?', 'listeo_core'),
							'key'			=> 'keyword_search',
							'name'			=> 'keyword_search',
							'priority'		=> 1,
							'place'			=> 'main',
							'type' 			=> 'text',
						),
						'location_search' => array(
							'placeholder'	=> __('Location', 'listeo_core'),
							'key'			=> 'location_search',
							'name'			=> 'location_search',
							'priority'		=> 2,
							'place'			=> 'main',
							'type' 			=> 'location',
						),
						'category' => array(
							'placeholder'	=> __('All Categories', 'listeo_core'),
							'key'			=> '_category',
							'name'			=> 'tax-listing_category',
							'type' 			=> 'drilldown-taxonomy',
							'place'			=> 'main',
							'taxonomy' 		=> 'listing_category',

						),


					);

					return apply_filters('listeo_core_search_fields_home', $search_fields);
				}
				public static function get_search_fields_header()
				{

					$search_fields = array(

						'keyword_search' => array(
							'placeholder'	=> __('What are you looking for?', 'listeo_core'),
							'key'			=> 'keyword_search',
							'name'			=> 'keyword_search',
							'priority'		=> 1,
							'place'			=> 'main',
							'type' 			=> 'text',
						),
						'location_search' => array(
							'placeholder'	=> __('Location', 'listeo_core'),
							'key'			=> 'location_search',
							'name'			=> 'location_search',
							'priority'		=> 2,
							'place'			=> 'main',
							'type' 			=> 'location',
						),
						'category' => array(
							'placeholder'	=> __('All Categories', 'listeo_core'),
							'key'			=> '_category',
							'name'			=> 'tax-listing_category',
							'type' 			=> 'select-taxonomy',
							'place'			=> 'main',
							'taxonomy' 		=> 'listing_category',

						),


					);

					return apply_filters('listeo_core_search_fields_header', $search_fields);
				}

				public static function get_search_fields_home_box()
				{
					$currency_abbr = get_option('listeo_currency');

					$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

					$search_fields = array(
						'location_search' => array(
							'placeholder'	=> __('Location', 'listeo_core'),
							'key'			=> 'location_search',
							'name'			=> 'location_search',
							'priority'		=> 2,
							'place'			=> 'main',
							'type' 			=> 'location',
						),
						'date_range' => array(
							'placeholder'	=> __('Check-In - Check-Out', 'listeo_core'),
							'key'			=> '_date_range',
							'name'			=> 'date_range',
							'type' 			=> 'date-range',
							'place'			=> 'main',
						),
						'price_range' => array(
							'placeholder' 	=> __('Price Filter', 'realteo'),
							'key'			=> '_price',
							'css_class'		=> '',
							'name'			=> '_price',
							'priority'		=> 4,
							'place'			=> 'main',
							'type' 			=> 'slider',
							'max' 			=> 'auto',
							'min' 			=> 'auto',
							'unit' 			=> $currency_symbol,
							'state'			=> 'on'
						),



					);

					return apply_filters('listeo_core_search_fields_homebox', $search_fields);
				}


				public function output_search_form($atts = array())
				{
					extract($atts = shortcode_atts(apply_filters('listeo_core_output_defaults', array(
						'source'			=> 'sidebar', // home/sidebar/split
						'wrap_with_form'	=> 'yes',
						'custom_class' 		=> '',
						'action'			=> '',
						'more_trigger'		=> 'yes',
						'more_text_open'	=> __('Additional Features', 'listeo_core'),
						'more_text_close'	=> __('Additional Features', 'listeo_core'),
						'more_custom_class' => ' margin-bottom-10 margin-top-30',
						'more_trigger_style' => 'relative',
						'ajax_browsing'		=> get_option('listeo_ajax_browsing'),
						'dynamic_filters' 	=> (get_option('listeo_dynamic_features') == "on") ? "on" : "off",
						'dynamic_taxonomies' => (get_option('listeo_dynamic_taxonomies') == "on") ? "on" : "off",

					)), $atts));

					switch ($source) {

						case 'search_on_home_page':
						case 'home':
							$source = 'search_on_home_page';
							$form_type = 'fullwidth';
							$search_fields = $this->get_search_fields_home();
							//fix for panel slider for search
							if (isset($search_fields['_price'])) {
								$search_fields['_price']['place'] = 'panel';
							}

							if (isset($search_fields['search_radius'])) {
								$search_fields['search_radius']['place'] = 'panel';
							}
							break;

						case 'sidebar_search':
						case 'sidebar':
							$source = 'sidebar_search';
							$search_fields = $this->get_search_fields();
							$form_type = 'sidebar';
							break;

						case 'search_on_half_map':
						case 'half':
							$source = 'search_on_half_map';
							$search_fields = $this->get_search_fields_half();
							$form_type = 'split';
							break;

						case 'search_on_homebox_page':
						case 'homebox':
							$source = 'search_on_homebox_page';
							$search_fields = $this->get_search_fields_home_box();
							$form_type = 'boxed';

							break;
						case 'search_in_header':
						case 'header':
							$source = 'search_in_header';
							$search_fields = $this->get_search_fields_header();
							$form_type = 'fullwidth';
							if (isset($search_fields['_price'])) {
								$search_fields['_price']['place'] = 'panel';
							}

							if (isset($search_fields['search_radius'])) {
								$search_fields['search_radius']['place'] = 'panel';
							}

							break;
						default:
							$options = get_option("listeo_{$source}_form_fields");
							$search_fields = $options ? $options : $this->get_search_fields_home();
							break;
					}

					$forms = get_option('listeo_search_forms', array());

					$default_forms = listeo_get_default_search_forms();

					if (array_key_exists($source, $default_forms)) {
						$default_form = true;
					} else {
						$form_type = $forms[$source]['type'];
					}

					if (isset($search_fields['tax-listing_feature'])) {
						$search_fields['tax-listing_feature']['dynamic'] = (get_option('listeo_dynamic_features') == "on") ? "yes" : "no";
					}
					if (isset($search_fields['features'])) {
						$search_fields['features']['dynamic'] = (get_option('listeo_dynamic_features') == "on") ? "yes" : "no";
					}

					$ajax = ($ajax_browsing == 'on') ? 'ajax-search' : get_option('listeo_ajax_browsing');
					if ($ajax_browsing == 'on') {
						if (isset($search_fields['submit'])) {
							unset($search_fields['submit']);
						}
					}

					if (!get_option('listeo_maps_api_server') && !get_option('listeo_geoapify_maps_api_server')) {

						unset($search_fields['radius']);
						unset($search_fields['search_radius']);
					}

					if ($form_type == 'fullwidth') {
						if (isset($search_fields['price_range'])) {
							$search_fields['price_range']['place'] = 'panel';
						}
						if (isset($search_fields['_price'])) {
							$search_fields['_price']['place'] = 'panel';
						}

						if (isset($search_fields['search_radius'])) {
							$search_fields['search_radius']['place'] = 'panel';
						}

						if (isset($search_fields['_rating'])) {

							$search_fields['_rating']['place'] = 'panel';
						}
						foreach ($search_fields as $key => $value) {
							if (in_array($value['type'], array('multi-checkbox', 'multi-checkbox-row'))) {
								$search_fields[$key]['place'] = 'panel';
							}
							//place = panel
						}
					}


					$template_loader = new Listeo_Core_Template_Loader;

					//$action = get_post_type_archive_link( 'listing' );

					if (is_author()) {
						$author = get_queried_object();
						$author_id = $author->ID;
						$action = get_author_posts_url($author_id);
					}
					// 

					//change source to type
					ob_start();
					if ($wrap_with_form == 'yes') { ?>
			<form action="<?php echo $action; ?>" id="listeo_core-search-form" class="listeo-form-<?php echo esc_attr($source);
																									if ($dynamic_filters == 'on') {
																										echo esc_attr(' dynamic');
																									}  ?> <?php if ($dynamic_taxonomies == 'on') {
																												echo esc_attr('dynamic-taxonomies');
																											}  ?>  <?php echo esc_attr($custom_class) ?> <?php echo esc_attr($ajax) ?>" method="GET">
			<?php }
					if (in_array($form_type, array('fullwidth'))) { ?>
				<div class="main-search-input">
					<?php }

					$more_trigger = false;
					$panel_trigger = false;
					foreach ($search_fields as $key => $value) {
						if ((isset($value['place']) && $value['place'] == 'adv')) {
							$more_trigger = 'yes';
						}
						if ((isset($value['place']) && $value['place'] == 'panel')) {
							$panel_trigger = 'yes';
						}
					}
					//count main fields
					$count = 0;
					foreach ($search_fields as $key => $value) {
						if (isset($value['place']) && $value['place'] == 'main') {
							$count++;
						}
					}
					$temp_count = 0;
					foreach ($search_fields as $key => $value) {

						if (in_array($form_type, array('fullwidth', 'boxed')) && $value['type'] != 'hidden') { ?>
						<div class="main-search-input-item <?php
															switch ($value['type']) {
																case 'slider':
																	echo 'slider_type';
																	break;
																case 'rating':
																	echo 'listeo-rating-filter';
																	break;

																default:
																	echo esc_attr($value['type']);
																	break;
															}
															?>">
							<?php }

						if (isset($value['place']) && $value['place'] == 'main') {

							//displays search form

							if ($form_type == 'split') {

								// Skip radius field entirely if viewport mode is enabled
								if ($value['type'] === 'radius') {
									$search_mode = get_option('listeo_location_search_mode', 'radius');
									if ($search_mode === 'viewport') {
										continue; // Don't render radius field at all in viewport mode
									}
								}

								if ($temp_count == 0) {
									echo '<div class="row with-forms split-top-inputs">';
								}
								$temp_count++;
								$template_loader->set_template_data($value)->get_template_part('search-form/' . $value['type']);
								if ($temp_count == $count) {
									echo '</div>';
								}
							} else {

								// Skip radius field entirely if viewport mode is enabled
								if ($value['type'] === 'radius') {
									$search_mode = get_option('listeo_location_search_mode', 'radius');
									if ($search_mode === 'viewport') {
										continue; // Don't render radius field at all in viewport mode
									}
								}

								if ($form_type == 'sidebar') {
									echo '<div class="row with-forms" id="listeo-search-form_' . $value['name'] . '">';
								}
								$template_loader->set_template_data($value)->get_template_part('search-form/' . $value['type']);
								if ($form_type == 'sidebar') {
									echo '</div>';
								}
							}


							if ($value['type'] == 'radius') { ?>
								<!-- <div class="row with-forms">
							<div class="col-md-12">
								<span class="panel-disable" data-disable="<?php echo esc_attr_e('Disable Radius', 'listeo_core'); ?>" data-enable="<?php echo esc_attr_e('Enable Radius', 'listeo_core'); ?>"><?php esc_html_e('Disable Radius', 'listeo_core'); ?></span>
							</div>
						</div> -->

							<?php }
						}

						if (in_array($form_type, array('fullwidth', 'boxed'))) {
							//fix for price on home search

							if (isset($value['place']) && $value['place'] == 'panel') {
							?>
								<?php
								//if value type is drilldown-taxonomy or drilldown-listing-types, don't show it in panel

								if ($value['type'] == 'drilldown_taxonomy' || $value['type'] == 'drilldown-listing-types') { ?>


									$template_loader->set_template_data( $value )->get_template_part( 'search-form/'.$value['type']);


									<?php } else {

									if (isset($value['type']) && $value['type'] != 'submit') { ?>
										<!-- Panel Dropdown -->
										<div class="panel-dropdown <?php if ($value['type'] == 'multi-checkbox-row') {
																		echo "wide";
																	}
																	if ($value['type'] == 'radius') {
																		echo 'radius-dropdown';
																	} ?> " id="<?php echo esc_attr($value['name']); ?>-panel">
											<a href="#"><?php echo esc_html($value['placeholder']); ?></a>
											<div class="panel-dropdown-content <?php if ($value['type'] == 'multi-checkbox-row') {
																					echo "checkboxes";
																				} ?> <?php if (isset($value['dynamic']) && $value['dynamic'] == 'yes') {
																							echo esc_attr('dynamic');
																						} ?>">
											<?php }

										$template_loader->set_template_data($value)->get_template_part('search-form/' . $value['type']);

										if (isset($value['type']) && $value['type'] != 'submit') { ?>
												<!-- Panel Dropdown -->
												<div class="panel-buttons">
													<?php if ($value['type'] == 'radius') { ?>
														<span class="panel-disable" data-disable="<?php echo esc_attr_e('Disable', 'listeo_core'); ?>" data-enable="<?php echo esc_attr_e('Enable', 'listeo_core'); ?>"><?php esc_html_e('Disable', 'listeo_core'); ?></span>
													<?php } else { ?>
														<span class="panel-cancel"><?php esc_html_e('Close', 'listeo_core'); ?></span>
													<?php } ?>

													<button class="panel-apply"><?php esc_html_e('Apply', 'listeo_core'); ?></button>
												</div>
											</div>
										</div>
							<?php }
									}
								}
							}
							if (in_array($form_type, array('fullwidth', 'boxed'))  && $value['type'] != 'hidden') { ?>
						</div>
				<?php }
						}
				?>

				<?php if ($more_trigger == 'yes') : ?>
					<!-- More Search Options -->
					<a href="#" class="more-search-options-trigger <?php echo esc_attr($more_custom_class) ?>" data-open-title="<?php echo esc_attr($more_text_open) ?>" data-close-title="<?php echo esc_attr($more_text_close) ?>"></a>
					<?php if ($more_trigger_style == "over") : ?>
						<div class="more-search-options ">
							<div class="more-search-options-container">
							<?php else: ?>
								<div class="more-search-options relative">
								<?php endif; ?>

								<?php foreach ($search_fields as $key => $value) {
									if ($value['place'] == 'adv') {

										$template_loader->set_template_data($value)->get_template_part('search-form/' . $value['type']);
									}
								} ?>
								<?php if ($more_trigger_style == "over") : ?>
								</div>
							<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ($form_type != 'fullwidth' && $panel_trigger == 'yes') { ?>
							<div class="row">
								<?php echo ($form_type == 'split') ? '<div class="col-fs-12 panel-wrapper">' : '<div class="col-md-12  panel-wrapper">'; {  ?>
									<?php
									foreach ($search_fields as $key => $value) {
										if ($form_type != 'fullwidth' && isset($value['place']) && $value['place'] == 'panel') {
									?>

											<?php
											//if value type is drilldown-taxonomy or drilldown-listing-types, don't show it in panel
											if ($value['type'] == 'drilldown-taxonomy' || $value['type'] == 'drilldown-listing-types') { ?>
												<div class="drilldown-menu-panel" id="<?php echo esc_attr($value['name']); ?>-panel">
													<?php $template_loader->set_template_data($value)->get_template_part('search-form/' . $value['type']); ?>
												</div>
												<?php } else {
												if (isset($value['type']) && !in_array($value['type'], array('submit', 'sortby'))) {

												?>
													<!-- Panel Dropdown -->
													<div class="panel-dropdown <?php if ($value['type'] == 'multi-checkbox-row') {
																					echo "wide";
																				}
																				if ($value['type'] == 'radius') {
																					echo 'radius-dropdown';
																				} ?> " id="<?php echo esc_attr($value['name']); ?>-panel">
														<a href="#"><?php echo esc_html($value['placeholder']); ?></a>
														<div class="panel-dropdown-content <?php if ($value['type'] == 'multi-checkbox-row') {
																								echo "checkboxes";
																							} ?> <?php if (isset($value['dynamic']) && $value['dynamic'] == 'yes') {
																										echo esc_attr('dynamic');
																									} ?>">
														<?php }

													$template_loader->set_template_data($value)->get_template_part('search-form/' . $value['type']);
												}
												if (isset($value['type']) && !in_array($value['type'], array('submit', 'sortby', 'drilldown-taxonomy', 'drilldown-listing-types'))) { ?>
														<!-- Panel Dropdown -->
														<div class="panel-buttons">
															<?php if ($value['type'] == 'radius') { ?>
																<span class="panel-disable" data-disable="<?php echo esc_attr_e('Disable', 'listeo_core'); ?>" data-enable="<?php echo esc_attr_e('Enable', 'listeo_core'); ?>"><?php esc_html_e('Disable', 'listeo_core'); ?></span>
															<?php } else { ?>
																<span class="panel-cancel"><?php esc_html_e('Close', 'listeo_core'); ?></span>
															<?php } ?>

															<button class="panel-apply"><?php esc_html_e('Apply', 'listeo_core'); ?></button>
														</div>
														</div>
													</div>
										<?php }
											}
										} ?>

							</div>
							<?php do_action('listeo_after_search_panel_wrapper', $form_type); ?>
						</div>
				<?php }
							} ?>
				<input type="hidden" name="action" value="listeo_get_listings" />
				<!-- More Search Options / End -->
				<?php if ($form_type == 'sidebar' && $ajax_browsing != 'on') {	?>
					<button class="button fullwidth margin-top-30"><?php esc_html_e('Search', 'listeo_core') ?></button>
				<?php } ?>

				<?php if (in_array($form_type, array('fullwidth'))) { ?>
					<button class="button"><?php esc_html_e('Search', 'listeo_core') ?></button>
				</div>
			<?php } ?>
				<?php if (in_array($form_type, array('boxed'))) { ?>
					<button class="button"><?php esc_html_e('Search', 'listeo_core') ?></button>

				<?php } ?>
					<?php do_action('listeo_after_search_form_fields', $form_type); ?>
				<?php if ($wrap_with_form == 'yes') { ?>
				</form>
<?php }
					//if ajax

					$output = ob_get_clean();
					echo $output;
				}



				public static function get_min_meta_value($meta_key = '', $type = '')
				{
					$transient_name = 'min_meta_value_' . $meta_key . '_' . $type;
					// Check if the transient exists and is not expired
					$cached_value = get_transient($transient_name);
					if ($cached_value !== false) {
						return $cached_value;
					}
					global $wpdb;
					$result = false;
					if (!empty($type)) {
						$type_query = 'AND ( m1.meta_key = "_listing_type" AND m1.meta_value = "' . $type . '")';
					} else {
						$type_query = false;
					}
					if ($meta_key):

						$result = $wpdb->get_var(
							$wpdb->prepare("
SELECT MIN(CAST(m2.meta_value AS DECIMAL(10,2)))
FROM $wpdb->posts AS p
INNER JOIN $wpdb->postmeta AS m1 ON ( p.ID = m1.post_id )
INNER JOIN $wpdb->postmeta AS m2 ON ( p.ID = m2.post_id )
WHERE
p.post_type = 'listing'
AND p.post_status = 'publish'
$type_query
AND ( m2.meta_key IN ( %s, %s, %s, %s ) )
AND m2.meta_value IS NOT NULL
AND m2.meta_value != ''
AND m2.meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
AND CAST(m2.meta_value AS DECIMAL(10,2)) > 0
", $meta_key . '_min', $meta_key . '_max', '_classifieds' . $meta_key, '_normal' . $meta_key)
						);

					endif;
					set_transient($transient_name, $result, 86400);

					return $result;
				}

				public static function get_max_meta_value($meta_key = '', $type = '')
				{
					$transient_name = 'max_meta_value_' . $meta_key . '_' . $type;
					// Check if the transient exists and is not expired
					$cached_value = get_transient($transient_name);
					if ($cached_value !== false) {
						return $cached_value;
					}
					global $wpdb;
					$result = false;
					if (!empty($type)) {
						$type_query = 'AND ( m1.meta_key = "_listing_type" AND m1.meta_value = "' . $type . '")';
					} else {
						$type_query = false;
					}
					if ($meta_key):

						$result = $wpdb->get_var(
							$wpdb->prepare("
		            SELECT max(m2.meta_value + 0)
		            FROM $wpdb->posts AS p
		            INNER JOIN $wpdb->postmeta AS m1 ON ( p.ID = m1.post_id )
					INNER JOIN $wpdb->postmeta AS m2  ON ( p.ID = m2.post_id )
					WHERE
					p.post_type = 'listing'
					AND p.post_status = 'publish'
					$type_query
					AND ( m2.meta_key IN ( %s, %s, %s, %s )  ) AND m2.meta_value != ''
		        ", $meta_key . '_min', $meta_key . '_max', '_classifieds' . $meta_key, '_normal' . $meta_key)
						);

					endif;
					set_transient($transient_name, $result, 86400);

					return $result;
				}

			}
