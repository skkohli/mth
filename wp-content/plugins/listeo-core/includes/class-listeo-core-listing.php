<?php
// Exit if accessed directly
if (! defined('ABSPATH'))
	exit;

/**
 * Listeo_Core_Listing class
 */
class Listeo_Core_Listing
{

	private static $_instance = null;

	public function __construct()
	{
		add_filter('query_vars', array($this, 'add_query_vars'));
	}



	/**
	 * add_query_vars()
	 *
	 * Adds query vars for search and display.
	 *
	 * @param integer $vars Post ID
	 *
	 * @since 1.0.0
	 */
	public function add_query_vars($vars)
	{

		$new_vars = array();

		array_push($new_vars, 'date_range', 'keyword_search', 'location_search', 'listeo_core_order', 'search_radius', 'radius_type', 'rating-filter', 'open_now');

		$vars = array_merge($new_vars, $vars);
		return $vars;
	}

	public static function get_real_listings($args)
	{

		global $wpdb;
		
		global $paged;

		if (isset($args['listeo_orderby'])) {
			$ordering_args = Listeo_Core_Listing::get_listings_ordering_args($args['listeo_orderby']);
		} else {
			$ordering_args = Listeo_Core_Listing::get_listings_ordering_args();
		}

		// Register distance sorting filter if needed
		if (isset($ordering_args['listeo_distance_order']) &&
			$ordering_args['listeo_distance_order'] &&
			isset($args['listeo_user_lat']) &&
			isset($args['listeo_user_lng'])) {
			add_filter('posts_clauses', array('Listeo_Core_Listing', 'order_by_distance_post_clauses'), 10, 2);
		}

		// Register price sorting filter if needed
		if (isset($ordering_args['listeo_price_order']) && $ordering_args['listeo_price_order']) {
			if (isset($ordering_args['listeo_price_order_direction']) && $ordering_args['listeo_price_order_direction'] === 'desc') {
				add_filter('posts_clauses', array('Listeo_Core_Listing', 'order_by_price_desc_post_clauses'), 10, 2);
			} else {
				add_filter('posts_clauses', array('Listeo_Core_Listing', 'order_by_price_asc_post_clauses'), 10, 2);
			}
		}



		if (get_query_var('paged')) {
			$paged = get_query_var('paged');
		} elseif (get_query_var('page')) {
			$paged = get_query_var('page');
		} else {
			$paged = 1;
		}

		$search_radius_var = get_query_var('search_radius');
		if (!empty($search_radius_var)) {
			$args['search_radius'] = $search_radius_var;
		}

		$radius_type_var = get_query_var('radius_type');
		if (!empty($radius_type_var)) {
			$args['radius_type'] = $radius_type_var;
		}

		$keyword_search_too_long = false;
		$keyword_var = get_query_var('keyword_search');

		if (!empty($keyword_var)) {
			if (Listeo_Core_Search::is_keyword_search_too_long($keyword_var)) {
				$keyword_search_too_long = true;
			} else {
				$args['keyword'] = Listeo_Core_Search::sanitize_keyword_search($keyword_var);
			}
		}

		if (isset($args['keyword']) && Listeo_Core_Search::is_keyword_search_too_long($args['keyword'])) {
			$keyword_search_too_long = true;
			$args['keyword'] = '';
		} elseif (isset($args['keyword'])) {
			$args['keyword'] = Listeo_Core_Search::sanitize_keyword_search($args['keyword']);
		}


		$location_var = get_query_var('location_search');
		if (!empty($location_var)) {
			$args['location'] = $location_var;
		}

		// Read additional search params from URL query vars
		// These are needed when the [listings] shortcode is used on a custom search results page
		$rating_var = get_query_var('rating-filter');
		if (!empty($rating_var) && !isset($args['rating-filter'])) {
			$rating_var = sanitize_text_field($rating_var);
			if ($rating_var === 'any' || (is_numeric($rating_var) && $rating_var >= 0 && $rating_var <= 5)) {
				$args['rating-filter'] = $rating_var;
			}
		}

		if (empty($args['place_viewport']) || !is_array($args['place_viewport'])) {
			$viewport_var = get_query_var('place_viewport');
			if (!empty($viewport_var) && is_array($viewport_var)) {
				$args['place_viewport'] = array_map('sanitize_text_field', $viewport_var);
			} elseif (isset($_GET['place_viewport']) && is_array($_GET['place_viewport'])) {
				$args['place_viewport'] = array_map('sanitize_text_field', $_GET['place_viewport']);
			}
		}

		if (empty($args['place_type'])) {
			$place_type_var = get_query_var('place_type');
			if (!empty($place_type_var)) {
				$args['place_type'] = sanitize_text_field($place_type_var);
			}
		}

		if (empty($args['_listing_type'])) {
			$listing_type_var = get_query_var('_listing_type');
			if (!empty($listing_type_var)) {
				$args['_listing_type'] = is_array($listing_type_var)
					? array_map('sanitize_text_field', $listing_type_var)
					: sanitize_text_field($listing_type_var);
			}
		}

		if (!isset($args['open_now'])) {
			$open_now_var = get_query_var('open_now');
			if (!empty($open_now_var)) {
				$args['open_now'] = sanitize_text_field($open_now_var);
			}
		}

		$drilldown_var = get_query_var('drilldown-listing-types');
		if (!empty($drilldown_var) && !isset($args['drilldown-listing-types'])) {
			$args['drilldown-listing-types'] = is_array($drilldown_var)
				? array_map('sanitize_text_field', $drilldown_var)
				: sanitize_text_field($drilldown_var);
		}

		if (!isset($args['map_bounds']) || empty($args['map_bounds'])) {
			if (isset($_GET['map_bounds']) && is_array($_GET['map_bounds'])) {
				$args['map_bounds'] = array_map('sanitize_text_field', $_GET['map_bounds']);
			}
		}

		if (!isset($args['search_by_map_move'])) {
			$map_move_var = get_query_var('search_by_map_move');
			if (!empty($map_move_var)) {
				$args['search_by_map_move'] = $map_move_var;
			}
		}

		if (empty($args['date_range'])) {
			$date_range_var = get_query_var('date_range');
			if (empty($date_range_var) && isset($_GET['date_range'])) {
				$date_range_var = sanitize_text_field($_GET['date_range']);
			}
			if (!empty($date_range_var)) {
				$dates = explode(' - ', $date_range_var);
				if (count($dates) === 2) {
					$args['date_start'] = $dates[0];
					$args['date_end'] = $dates[1];
				}
			}
		}

		$query_args = array(
			'query_label' 			 => 'listeo_get_listing_query',
			'post_type'              => 'listing',
			'post_status'            => 'publish',
			'ignore_sticky_posts'    => 1,
			'paged' 		 		 => $paged,
			'posts_per_page'         => intval($args['posts_per_page']),
			'orderby'                => $ordering_args['orderby'],
			'order'                  => $ordering_args['order'],
			'tax_query'              => array(),
			'meta_query'             => array(),
		);

		// Add distance sorting parameters if present
		if (isset($args['listeo_user_lat'])) {
			$query_args['listeo_user_lat'] = $args['listeo_user_lat'];
		}
		if (isset($args['listeo_user_lng'])) {
			$query_args['listeo_user_lng'] = $args['listeo_user_lng'];
		}
		if (isset($args['listeo_distance_unit'])) {
			$query_args['listeo_distance_unit'] = $args['listeo_distance_unit'];
		}
		if (isset($ordering_args['listeo_distance_order'])) {
			$query_args['listeo_distance_order'] = $ordering_args['listeo_distance_order'];
		}

		// Add price sorting parameters if present
		if (isset($ordering_args['listeo_price_order'])) {
			$query_args['listeo_price_order'] = $ordering_args['listeo_price_order'];
		}
		if (isset($ordering_args['listeo_price_order_direction'])) {
			if ($ordering_args['listeo_price_order_direction'] === 'desc') {
				$query_args['listeo_price_order_desc'] = true;
			} else {
				$query_args['listeo_price_order_asc'] = true;
			}
		}


		$ai_search_input = get_query_var('ai_search_input');

		if (empty($ai_search_input) && isset($args['ai_search_input'])) {
			$ai_search_input = $args['ai_search_input'];
		}
		$ai_search_post_ids = array();
		$preserve_ai_order = false;
		if (!$keyword_search_too_long && !empty($ai_search_input)) {
			$ai_search_post_ids = apply_filters('listeo_search_ai_post_ids', $ai_search_input);
			
			if (!empty($ai_search_post_ids) && is_array($ai_search_post_ids)) {
				$preserve_ai_order = true;
				// Store the AI order for later use in posts_orderby filter
				$query_args['listeo_ai_post_order'] = $ai_search_post_ids;
			}
		}
		

		if (isset($args['open_now'])) {
			$query_args['open_now'] = true;
		}
		if (isset($args['offset'])) {
			$query_args['offset'] = $args['offset'];
		}
		if (isset($ordering_args['meta_type'])) {
			$query_args['meta_type'] = $ordering_args['meta_type'];
		}

		// Only skip meta_key if we're preserving AI order for default/relevance/best-match sorting
		$current_orderby = isset($args['listeo_orderby']) ? $args['listeo_orderby'] : 'default';
		$should_preserve_ai_order = $preserve_ai_order && in_array($current_orderby, ['default', 'relevance', 'best-match', '']);
		
		if (!$should_preserve_ai_order) {
			if (isset($ordering_args['meta_key']) && $ordering_args['meta_key'] != '_featured') {
				$query_args['meta_key'] = $ordering_args['meta_key'];
			}
		}



		
		$keywords_post_ids = array();
		$location_post_ids = array();
		$keyword_search = get_option('listeo_keyword_search', 'search_title');
		$search_mode = get_option('listeo_search_mode', 'exact');

		if (!$keyword_search_too_long && $search_mode == 'relevance') {
			if (isset($args['keyword']) && !empty($args['keyword'])) {
				// Combine title, content, and meta searches
				$search_terms = array_map('trim', explode('+', $args['keyword']));
				$search_string = implode(' ', $search_terms);
				$query_args['s'] = $search_string;
				$query_args['search_fields'] = array(
					'post_title',
					'post_content',
					'meta_value' // Search custom fields
				);
			}
		}

		
		
		if (!$keyword_search_too_long && $search_mode != 'relevance') {
			if (isset($args['keyword']) && !empty($args['keyword'])) {


				if ($search_mode == 'exact') {
					$keywords = array_map('trim', explode('+', $args['keyword']));
					$keyword_relation = "AND";
				} elseif ($search_mode == 'approx') {
					$keywords = array_map('trim', explode(' ', $args['keyword']));
					$keyword_relation = "AND";
				} else {
					$keywords = array_map('trim', explode(' ', $args['keyword']));
					$keyword_relation = "OR";
				}
				// Setup SQL

				$posts_keywords_sql    = array();
				$postmeta_keywords_sql = array();

				// $postmeta_keywords_sql[] = " meta_value LIKE '%" . esc_sql( $keywords[0] ) . "%' ";
				// // Create post title and content SQL
				// $posts_keywords_sql[]    = " post_title LIKE '%" . esc_sql( $keywords[0] ) . "%' OR post_content LIKE '%" . esc_sql(  $keywords[0] ) . "%' ";


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

				// Get post IDs from post meta search

				$post_ids = $wpdb->get_col("
				    SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				    WHERE " . implode($keyword_relation, $postmeta_keywords_sql) . "
				");

				// Merge with post IDs from post title and content search

				$keywords_post_ids = array_merge($post_ids, $wpdb->get_col("
				    SELECT ID FROM {$wpdb->posts}
				    WHERE ( " . implode($keyword_relation, $posts_keywords_sql) . " )
				    AND post_type = 'listing'
				   
				"), array(0));
				/* array( 0 ) is set to return no result when no keyword was found */
			}
		}

		$search_keyword = isset($args['keyword']) && ! empty($args['keyword']) ? $args['keyword'] : '';
		$keywords_post_ids = apply_filters('listeo_get_listings_keywords_post_ids', $keywords_post_ids, $search_keyword, $search_mode);
		
	
		// if (!empty($ai_search_input)) {
		// 	$keywords_post_ids = apply_filters('listeo_search_ai_post_ids', $ai_search_input);	
		// }
		if (!$keyword_search_too_long && isset($args['location']) && !empty($args['location'])) {
			// Centralized location resolution: viewport vs radius vs text search.
			// Same helper is used by Listeo_Core_Search (AJAX path) to keep behavior identical.
			// See listeo_core_resolve_location_post_ids() in listeo-core-template-functions.php.
			$resolved_location_ids = listeo_core_resolve_location_post_ids(array(
				'location'       => $args['location'],
				'radius'         => isset($args['search_radius']) ? $args['search_radius'] : '',
				'place_viewport' => isset($args['place_viewport']) ? $args['place_viewport'] : null,
				'place_type'     => isset($args['place_type']) ? $args['place_type'] : '',
			));
			if (is_array($resolved_location_ids)) {
				$location_post_ids = $resolved_location_ids;
			}
		}
		$post_ids = array();
		if ($keyword_search_too_long) {
			$post_ids = array(0);
		} else if (!empty($ai_search_post_ids)) {
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
			} else {
				// Only AI search results
				$post_ids = $ai_search_post_ids;
			}
		} else {
			// Original logic when no AI search
			if (sizeof($keywords_post_ids) != 0 && sizeof($location_post_ids) != 0) {
				$post_ids = array_intersect($keywords_post_ids, $location_post_ids);
				if (!empty($post_ids)) {
					$query_args['post__in'] = $post_ids;
				} else {
					$query_args['post__in'] = array(0);
				}
			} else if (sizeof($keywords_post_ids) != 0 && sizeof($location_post_ids) == 0) {
				$post_ids = $keywords_post_ids;
			} else if (sizeof($keywords_post_ids) == 0 && sizeof($location_post_ids) != 0) {
				$post_ids = $location_post_ids;
			}
		}
		if (!empty($post_ids)) {
			$query_args['post__in'] = $post_ids;

			// Only preserve AI order for default/relevance/best-match sorting
			$current_orderby = isset($args['listeo_orderby']) ? $args['listeo_orderby'] : 'default';
			$should_preserve_ai_order = $preserve_ai_order && in_array($current_orderby, ['default', 'relevance', 'best-match', '']);
			
			if ($should_preserve_ai_order) {
				// Keep AI relevance order for default sorting
				$query_args['orderby'] = 'post__in';
				$query_args['order'] = 'ASC';
				// Remove meta_key to avoid conflicts
				$query_args['meta_key'] = '';
			}
			// For other sorting options (rating, date, featured, etc.), 
			// let the existing ordering_args values stand and apply to AI-filtered results
		}

		$posts_not_ids = array();

		if (isset($args['_listing_type']) && $args['_listing_type'] == 'rental' && isset($args['date_start']) && !empty($args['date_start'])) {

			// $date_start =  str_replace("/", "-",	$args['date_start']);
			//       $date_end =  str_replace("/", "-", 	$args['date_end']);
			$date_start =   $args['date_start'];
			$date_end =   	$args['date_end'];


			$date_start_object = DateTime::createFromFormat('!' . listeo_date_time_wp_format_php(), $date_start);
			$date_end_object = DateTime::createFromFormat('!' . listeo_date_time_wp_format_php(), $date_end);

			// Fallback: try common numeric formats if WP format didn't match (e.g. flatpickr sends m/d/Y)
			if ( ! $date_start_object ) {
				$fallback_formats = array( 'm/d/Y', 'd/m/Y', 'Y-m-d', 'd.m.Y' );
				foreach ( $fallback_formats as $format ) {
					$date_start_object = DateTime::createFromFormat( '!' . $format, $date_start );
					if ( $date_start_object ) break;
				}
			}
			if ( ! $date_end_object ) {
				$fallback_formats = array( 'm/d/Y', 'd/m/Y', 'Y-m-d', 'd.m.Y' );
				foreach ( $fallback_formats as $format ) {
					$date_end_object = DateTime::createFromFormat( '!' . $format, $date_end );
					if ( $date_end_object ) break;
				}
			}

			// Skip date filtering if dates still can't be parsed
			if ( ! $date_start_object || ! $date_end_object ) {
				$date_excluded_posts = array();
			} else {

			$format_date_start 	= esc_sql($date_start_object->format("Y-m-d H:i:s"));
			//$format_date_end 	= esc_sql($date_end_object->format("Y-m-d H:i:s"));
			$format_date_end = esc_sql($date_end_object->modify('+0 day')->format('Y-m-d 00:00:00'));
			
			$date_excluded_posts =  $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT listing_id 
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
				            GROUP BY listing_id 
			            	",
					$format_date_end,
					$format_date_start,
					$format_date_start,
					$format_date_start,
					$format_date_start,
					$format_date_end,
					$format_date_start,
					$format_date_end
				)
			);

			$posts_not_ids = array_merge($posts_not_ids, $date_excluded_posts);
		} // end else (dates parsed successfully)
		}

		$query_args['post__in'] = array_diff($post_ids, $posts_not_ids);

		$query_args['tax_query'] = array(
			'relation' => 'AND',
		);
		$taxonomy_objects = get_object_taxonomies('listing', 'objects');

		
		foreach ($taxonomy_objects as $tax) {


			$get_tax = false;
			// Check standard tax- prefix
			if ((isset($_GET['tax-' . $tax->name]) && !empty($_GET['tax-' . $tax->name]))) {
				$get_tax = $_GET['tax-' . $tax->name];
			} elseif (isset($args['tax-' . $tax->name])) {
				$get_tax = $args['tax-' . $tax->name];
			}
			// Also check _tax_ prefix (meta fields with taxonomy association)
			if (!$get_tax) {
				if (isset($_GET['_tax_' . $tax->name]) && !empty($_GET['_tax_' . $tax->name])) {
					$get_tax = $_GET['_tax_' . $tax->name];
				} elseif (isset($args['_tax_' . $tax->name])) {
					$get_tax = $args['_tax_' . $tax->name];
				}
			}

			if (is_array($get_tax)) {
				// For checkbox arrays where all values are 'on', use keys as term slugs
				$non_on_values = array_filter($get_tax, function($v) { return $v !== 'on'; });
				if (empty($non_on_values) && !empty($get_tax)) {
					$get_tax = array_map('sanitize_text_field', array_keys($get_tax));
				} else {
					// Reset keys to numeric in case of associative arrays from checkboxes
					$get_tax = array_map('sanitize_text_field', array_values($get_tax));
				}
				if (empty($get_tax[0])) {
					continue;
				}
				$query_args['tax_query'][$tax->name] =
					array('relation' => get_option('listeo_' . $tax->name . 'search_mode', 'OR'));
				foreach ($get_tax as $key => $value) {
					array_push($query_args['tax_query'][$tax->name], array(
						'taxonomy' =>   $tax->name,
						'field'    =>   'slug',
						'terms'    =>   $value,

					));
				}
			} else {

				if ($get_tax) {
					if (is_numeric($get_tax)) {
						$term = get_term_by('slug', $get_tax, $tax->name);
						if ($term) {
							array_push($query_args['tax_query'], array(
								'taxonomy' =>  $tax->name,
								'field'    =>  'slug',
								'terms'    =>  $term->slug,
								'operator' =>  'IN'
							));
						}
					} else {
						$get_tax_array = explode(',', $get_tax);
						//$query_args['tax_query'][$tax->name] = array('relation'=> 'OR');
						array_push($query_args['tax_query'], array(
							'taxonomy' =>  $tax->name,
							'field'    =>  'slug',
							'terms'    =>  $get_tax_array,

						));
					}
				}
			}
		}

		// Handle drilldown-listing-types (listing type + taxonomy combined filter)
		$drilldown_listing_types = isset($args['drilldown-listing-types']) ? $args['drilldown-listing-types'] : '';
		if (!empty($drilldown_listing_types)) {
			if (!is_array($drilldown_listing_types)) {
				$drilldown_listing_types = array($drilldown_listing_types);
			}

			$drilldown_meta_queries = array();
			$drilldown_tax_queries = array();

			foreach ($drilldown_listing_types as $selection) {
				$selection = sanitize_text_field($selection);
				if (strpos($selection, 'listing_type_') === 0) {
					$listing_type = str_replace('listing_type_', '', $selection);
					$drilldown_meta_queries[] = array(
						'key' => '_listing_type',
						'value' => $listing_type,
						'compare' => '='
					);
				} else {
					if (strpos($selection, ':') !== false) {
						list($taxonomy, $term_slug) = explode(':', $selection, 2);
						if (taxonomy_exists($taxonomy)) {
							$term = get_term_by('slug', $term_slug, $taxonomy);
							if ($term && !is_wp_error($term)) {
								$drilldown_tax_queries[] = array(
									'taxonomy' => $taxonomy,
									'field'    => 'slug',
									'terms'    => $term->slug,
								);
							}
						}
					} else {
						$found_taxonomy = null;
						$taxonomies = array('listing_category', 'service_category', 'rental_category', 'event_category', 'classifieds_category');
						foreach ($taxonomies as $tax_name) {
							$term = get_term_by('slug', $selection, $tax_name);
							if ($term && !is_wp_error($term)) {
								$found_taxonomy = $tax_name;
								break;
							}
						}
						if ($found_taxonomy) {
							$drilldown_tax_queries[] = array(
								'taxonomy' => $found_taxonomy,
								'field'    => 'slug',
								'terms'    => $term->slug,
							);
						}
					}
				}
			}

			if (!empty($drilldown_tax_queries)) {
				if (count($drilldown_tax_queries) > 1) {
					$drilldown_tax_group = array('relation' => 'OR');
					$drilldown_tax_group = array_merge($drilldown_tax_group, $drilldown_tax_queries);
					$query_args['tax_query'][] = $drilldown_tax_group;
				} else {
					$query_args['tax_query'][] = $drilldown_tax_queries[0];
				}
			}

			if (!empty($drilldown_meta_queries)) {
				if (count($drilldown_meta_queries) > 1) {
					$drilldown_meta_queries['relation'] = 'OR';
				}
				$query_args['meta_query'][] = $drilldown_meta_queries;
			}
		}

		$available_query_vars = Listeo_Core_Search::build_available_query_vars();
		$meta_queries = array();
		if (isset($args['featured'])  && !$args['featured']) {
			$available_query_vars[] = 'featured';
		}

		foreach ($available_query_vars as $key => $meta_key) {

			if (substr($meta_key, 0, 4) == "tax-") {
				continue;
			}

			// Skip _tax_ prefixed fields that correspond to registered taxonomies
			// These are handled as taxonomy queries above
			if (substr($meta_key, 0, 5) == "_tax_") {
				$possible_tax = substr($meta_key, 5);
				if (taxonomy_exists($possible_tax)) {
					continue;
				}
			}

			// Exclude search parameters that should NOT be converted to meta queries
			// These are search PARAMETERS, not listing metadata
			if (in_array($meta_key, array(
				'_price_range',         // Price range search parameter
				'ai_search_input',      // AI search query parameter
				'rating-filter',        // Rating filter parameter
				'_listing_type',        // Listing type filter
				'drilldown-listing-types', // Drilldown filter
				'date_range',           // Date range parameter
				'location',             // Location search parameter
				'location_search',      // Same as above
				'keyword',              // Keyword search parameter
				'keyword_search',       // Same as above
				'search_radius',        // Radius search parameter
				'map_bounds',           // Map bounding box parameter
				'search_by_map_move',   // Map move search parameter
				'place_viewport',       // Viewport bounding box from Google Places
				'place_type'            // Place type from Google Places (country/region/city)
			), true)) {
				continue;
			}


			if ($meta_key == '_price') {

				$meta = false;
				if (!empty(get_query_var('_price_range'))) {
					$meta = get_query_var('_price_range');
				} else if (isset($args['_price_range'])) {
					$meta = $args['_price_range'];
				}
				if (!empty($meta)) {

					$range = array_map('absint', explode(',', $meta));

					$query_args['meta_query'][] = array(
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
				
					);
				}
			} else {
				if (substr($meta_key, -4) == "_min" || substr($meta_key, -4) == "_max") {
					continue;
				}
				$meta = false;



				if (!empty(get_query_var($meta_key))) {
					$meta = get_query_var($meta_key);
				} else if (isset($args[$meta_key])) {

					$meta = $args[$meta_key];
				}
			

				if ($meta) {

					if ($meta === 'featured') {
						$query_args['meta_query'][] = array(
							'key'     => '_featured',
							'value'   => 'on',
							'compare' => '='
						);
					} else {
						if ($meta_key == '_max_guests') {
							if (!empty(get_query_var($meta_key))) {
								$meta = get_query_var($meta_key);
							} else if (isset($args[$meta_key])) {

								$meta = $args[$meta_key];
							}

							if (!empty($meta)) {
								$query_args['meta_query'][] = array(
									'key' =>  '_max_guests',
									'value' => $meta,
									'compare' => '>=',
									'type' => 'NUMERIC'
								);
							}
						} else {
						
							if (is_array($meta)) {
								// Handle multi-value fields that are now stored as separate records
								// This is much simpler than the previous serialized array approach
								
								// error_log("Processing multi-value field: " . $meta_key . " with data: " . print_r($meta, true));
								
								// For checkbox arrays like [Longboard => on, Skateboard => on],
								// we need the KEYS (Longboard, Skateboard) not the values (on, on)
								$valid_values = array();
								
								foreach ($meta as $key => $value) {
									// Check if this is a checkbox-style array (key => 'on')
									if ($value === 'on' && !empty($key) && $key !== 'none') {
										$valid_values[] = $key;
									} 
									// Check if this is a regular array with actual values
									elseif (!empty($value) && $value !== 'none' && $value !== 'on') {
										$valid_values[] = $value;
									}
								}
								
								// error_log("Extracted valid values for search: " . print_r($valid_values, true));
								
								if (!empty($valid_values)) {
									// For separate records, we can simply use IN comparison
									// This will match any post that has any of these values in separate meta records
									$query_args['meta_query'][] = array(
										'key'     => $meta_key,
										'value'   => array_values($valid_values),
										'compare' => 'IN'
									);
									// error_log("Added IN query for multi-value field: " . $meta_key . " with values: " . print_r($valid_values, true));
								} else {
									// error_log("No valid values found for multi-value field: " . $meta_key);
								}
								
							} else {
							
								$query_args['meta_query'][] = array(
									'key'     => $meta_key,
									'value'   => $meta,
								);
							}
						}
					}
				}
			}
		}

		// Handle _listing_type parameter from Elementor widgets
		if (isset($args['_listing_type']) && !empty($args['_listing_type'])) {
			$listing_types = $args['_listing_type'];

			// Support both single value and array
			if (!is_array($listing_types)) {
				$listing_types = array($listing_types);
			}

			// Remove empty values
			$listing_types = array_filter($listing_types);

			if (!empty($listing_types)) {
				$listing_types = array_map('sanitize_text_field', $listing_types);

				$query_args['meta_query'][] = array(
					'key'     => '_listing_type',
					'value'   => count($listing_types) > 1 ? $listing_types : $listing_types[0],
					'compare' => count($listing_types) > 1 ? 'IN' : '='
				);
			}
		}

// listeo_write_log($query_args['meta_query']);

		if (isset($args['featured']) && $args['featured'] !== 'null') {
			if ($args['featured'] == 'true' || $args['featured'] == true) {

				$query_args['meta_query'][] = array(
					'key'     => '_featured',
					'value'   => 'on',
					'compare' => '='
				);
			}
		}

		if (isset($args['featured']) && $args['featured'] === 'null') {

			$query_args['meta_query'][] = array(
				'key'     => '_featured',
				'value'   => 'on',
				'compare' => '!='
			);
		}

		if (isset($args['rating-filter']) && $args['rating-filter'] != 'any') {
			if ($args['rating-filter']) {
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


		}
	


		// Handle map bounds search
		if (isset($args['map_bounds']) && !empty($args['map_bounds'])) {
			$bounds = $args['map_bounds'];

			// Make sure we have all required coordinates
			if (
				isset($bounds['ne_lat']) && isset($bounds['ne_lng']) &&
				isset($bounds['sw_lat']) && isset($bounds['sw_lng'])
			) {

				$query_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key' => '_geolocation_lat',
						'value' => array($bounds['sw_lat'], $bounds['ne_lat']),
						'compare' => 'BETWEEN',
						'type' => 'DECIMAL(10,7)'
					),
					array(
						'key' => '_geolocation_long',
						'value' => array($bounds['sw_lng'], $bounds['ne_lng']),
						'compare' => 'BETWEEN',
						'type' => 'DECIMAL(10,7)'
					)
				);
			}
		}


		// Handle featured listings sorting (only when not preserving AI order for default/relevance/best-match)
		$current_orderby = isset($args['listeo_orderby']) ? $args['listeo_orderby'] : 'default';
		$should_preserve_ai_order = $preserve_ai_order && in_array($current_orderby, ['default', 'relevance', 'best-match', '']);
		
		if (!$should_preserve_ai_order && isset($ordering_args['meta_key']) && $ordering_args['meta_key'] == '_featured') {

	

			$query_args['order'] = 'ASC DESC';
			$query_args['orderby'] = 'meta_value date';
			$query_args['meta_key'] = '_featured';
		}



		if (!$should_preserve_ai_order && isset($args['_listing_type']) && $args['_listing_type'] == 'event' && isset($args['date_start']) && !empty($args['date_start'])) {



			$date_start_obj = DateTime::createFromFormat(listeo_date_time_wp_format_php() . ' H:i:s', $args['date_start'] . ' 00:00:00');

			// Fallback: try common numeric formats if WP format didn't match
			if ( ! $date_start_obj ) {
				$fallback_formats = array( 'm/d/Y', 'd/m/Y', 'Y-m-d', 'd.m.Y' );
				foreach ( $fallback_formats as $format ) {
					$date_start_obj = DateTime::createFromFormat( $format . ' H:i:s', $args['date_start'] . ' 00:00:00' );
					if ( $date_start_obj ) break;
				}
			}

			if ($date_start_obj) {
				$date_start = $date_start_obj->getTimestamp();
			} else {
				$date_start = false;
			}

			$date_end_obj = DateTime::createFromFormat(listeo_date_time_wp_format_php() . ' H:i:s', $args['date_end'] . ' 23:59:59');

			// Fallback: try common numeric formats if WP format didn't match
			if ( ! $date_end_obj ) {
				$fallback_formats = array( 'm/d/Y', 'd/m/Y', 'Y-m-d', 'd.m.Y' );
				foreach ( $fallback_formats as $format ) {
					$date_end_obj = DateTime::createFromFormat( $format . ' H:i:s', $args['date_end'] . ' 23:59:59' );
					if ( $date_end_obj ) break;
				}
			}

			if ($date_end_obj) {
				$date_end = $date_end_obj->getTimestamp();
			} else {
				$date_end = false;
			}

			if ( $date_start !== false && $date_end !== false ) {
				$query_args['meta_query'][] = array(
					'relation' => 'OR',
					array(
						'key' => '_event_date_timestamp',
						'value' => array($date_start, $date_end),
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					),
					array(
						'key' => '_event_date_end_timestamp',
						'value' => array($date_start, $date_end),
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					),
				);
			}
		}


		if (isset($ordering_args['listeo_key']) && $ordering_args['listeo_key'] == '_event_date_timestamp') {

			// $query->set('meta_key', false);

			$query_args['meta_query'][] =  array(
				'relation' => 'OR',
				array(
					'key' => '_event_date_timestamp',
					'value' => current_time('timestamp'),
					'compare' => '>',
					'type' => 'numeric'
				),
				array(
					'key' => '_event_date_timestamp', // Include an empty check for the key itself
					'compare' => 'NOT EXISTS',
				),

			);


			$query_args['orderby'] = [
				'has_event_date' => 'DESC',
				'event_date_distance' => 'ASC',
				'date' => 'DESC',
			];
			$query_args['listeo_custom_event_order'] = true;
			add_filter('posts_clauses', 'listeo_custom_event_clauses', 10, 2);
		}



		if (empty($query_args['meta_query']))
			unset($query_args['meta_query']);



		// ads
		$category = '';
		$feature = '';
		$region = '';
		if ((isset($_GET['tax-listing_category']) && !empty($_GET['tax-listing_category']))) {
			$raw = $_GET['tax-listing_category'];
			$category = is_array($raw) ? array_map('sanitize_text_field', array_values($raw)) : sanitize_text_field($raw);
		} else {
			if (isset($args['tax-listing_category'])) {
				$category = $args['tax-listing_category'];
			}
		}
		if ((isset($_GET['tax-listing_feature']) && !empty($_GET['tax-listing_feature']))) {
			$raw = $_GET['tax-listing_feature'];
			$feature = is_array($raw) ? array_map('sanitize_text_field', array_values($raw)) : sanitize_text_field($raw);
		} else {
			if (isset($args['tax-listing_feature'])) {
				$feature = $args['tax-listing_feature'];
			}
		}
		if ((isset($_GET['tax-region']) && !empty($_GET['tax-region']))) {
			$raw = $_GET['tax-region'];
			$region = is_array($raw) ? array_map('sanitize_text_field', array_values($raw)) : sanitize_text_field($raw);
		} else {
			if (isset($args['tax-region'])) {
				$region = $args['tax-region'];
			}
		}
		if ((isset($_GET['location']) && !empty($_GET['location']))) {
			$ad_location = sanitize_text_field($_GET['location']);
		} else {
			$ad_location = '';
		}
		if (is_array($region)) {
			$region = reset($region);
		}
		if (is_array($category)) {
			$category = reset($category);
		}
		if (is_array($feature) && !empty($feature)) {
			$feature = reset($feature);
		}
		$ad_filter = array(
			'listing_category' 	=> $category,
			'listing_feature'	=> $feature,
			'region' 			=> $region,
			'address' 			=> $ad_location,
		);


		// get posts from ad
		$ads_ids = listeo_get_ids_listings_for_ads('search', $ad_filter);
		// check if there's already a query_args['post_not_in'] and merge it with ads ids



		if (!empty($ads_ids)) {
			$posts_not_ids = array_merge($posts_not_ids, $ads_ids);
		}
		if (!empty($posts_not_ids)) {
			$query_args['post__not_in'] = array_unique($posts_not_ids);
		}

		if (isset($args['search_by_map_move']) && $args['search_by_map_move'] == 'true') {
			// Don't update map bounds automatically
			$query_args['skip_map_bounds_update'] = true;
		}

		$query_args = apply_filters('listeo_get_listings', $query_args, $args);

		// DEBUG: Log query_args before cleanup

		// Remove search parameters that should NOT be in WP_Query (they're handled separately)
		// These are search PARAMETERS, not listing metadata
		$search_params_to_remove = array(
			'location',          // Handled by viewport/radius search logic
			'location_search',   // Same as above
			'keyword',           // Handled by keyword search logic
			'keyword_search',    // Same as above
			'search_radius',     // Handled by radius search logic
			'map_bounds',        // Handled by map move search logic
			'search_by_map_move',// Handled by map move search logic
			'place_viewport',    // Handled by viewport search logic
			'place_type',        // Handled by viewport search logic
			'ai_search_input'    // Handled by AI search logic
		);

		foreach ($search_params_to_remove as $param) {
			if (isset($query_args[$param])) {
				unset($query_args[$param]);
			}
		}


		$result = new WP_Query($query_args);

		return $result;
	}


	/**
	 * get_listing_price_raw()
	 *
	 * Return listings price without formatting.
	 *
	 * @param integer $post_id Post ID
	 * @uses get_the_ID()
	 * @uses get_post_meta()
	 * @return string Listing price meta value
	 *
	 * @since 1.0.0
	 */
	public static function get_listing_price($post)
	{

		// Use global post ID if not defined

		if (! $post) {
			$post = get_the_ID();
		} else {
			$post = $post->ID;
		}

		$price = get_post_meta($post, '_price', true);
		if (is_numeric($price)) {
			$decimals = get_option('listeo_number_decimals', 2);
			$price_raw = number_format_i18n($price, $decimals);
		} else {
			return $price;
		}

		$price_output = '';
		if (!empty($price_raw)) :

			$currency_abbr = get_option('listeo_currency');
			$currency_postion = get_option('listeo_currency_postion');
			$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

			if ($currency_postion == 'after') {
				$price_output = $price_raw . $currency_symbol;
			} else {
				$price_output = $currency_symbol . $price_raw;
			}

		endif;
		// Return listing price
		return apply_filters('get_listing_price', $price_output, $post);
	}


	public static function get_listing_price_range($post)
	{
		if (! $post) {
			$post = get_the_ID();
		} else {
			$post = $post->ID;
		}

		$price_output = '';

		$price_min = get_post_meta($post, '_price_min', true);
		$price_max = get_post_meta($post, '_price_max', true);
		$decimals = get_option('listeo_number_decimals', 2);
		if(empty($price_min) && empty($price_max)){
			$price_min = get_post_meta($post, '_normal_price', true);
			
			$price_max = get_post_meta($post, '_weekday_price', true);
		}
		if (!empty($price_min) || !empty($price_max)) {
			if (is_numeric($price_min)) {
				$price_min_raw = number_format_i18n($price_min, $decimals);
			} else {
				$price_min_raw = $price_min;
			}

			if (is_numeric($price_max)) {
				$price_max_raw = number_format_i18n($price_max, $decimals);
			} else {
				$price_max_raw = $price_max;
			}
			$currency_abbr = get_option('listeo_currency');
			$currency_postion = get_option('listeo_currency_postion');
			$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
			if ($currency_postion == 'after') {
				if (!empty($price_min_raw) && !empty($price_max_raw)) {
					$price_output .=  $price_min_raw . $currency_symbol;
					$price_output .=  ' - ';
					$price_output .=  $price_max_raw . $currency_symbol;
				} else 
				if (!empty($price_min_raw) && empty($price_max_raw)) {
					$price_output .=  esc_html__('Starts from ', 'listeo_core') . $price_min_raw . $currency_symbol;
				} else {
					$price_output .=  esc_html__('Up to ', 'listeo_core') . $price_max_raw . $currency_symbol;
				}
			} else {
				if (!empty($price_min_raw) && !empty($price_max_raw)) {
					$price_output .=  $currency_symbol . $price_min_raw;
					$price_output .=  ' - ';
					$price_output .=  $currency_symbol . $price_max_raw;
				} else 
				if (!empty($price_min_raw) && empty($price_max_raw)) {
					$price_output .=  esc_html__('Starts from ', 'listeo_core') . $currency_symbol . $price_min_raw;
				} else {
					$price_output .=  esc_html__('Up to ', 'listeo_core') . $currency_symbol . $price_max_raw;
				}
			}
		}

		return apply_filters('listing_price_range', $price_output, $post);
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public static function get_listing_price_per_scale($post)
	{
		if (! $post)
			$post = get_the_ID();

		$price_raw 		= get_post_meta($post, '_price', true);
		if (empty($price_raw) || !is_numeric($price_raw)) {
			return;
		}
		$area 			= get_post_meta($post, '_area', true);

		$price_per_raw 	= get_post_meta($post, '_price_per', true);
		$output 		= '';
		$currency_abbr = get_option('currency');
		$currency_postion = get_option('currency_postion');
		$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

		if (empty($price_per_raw) && !empty($area)) {
			$output = intval($price_raw / $area, 10);
		} else {
			if (empty($price_per_raw)) {
				$output = '';
			} else {
				$output = $price_per_raw;
			}
		}

		$offer_type = get_the_listing_offer_type();

		if ($currency_postion == 'after') {
			$output = $output . $currency_symbol;
		} else {
			$output = $currency_symbol . $output;
		}

		if ($offer_type == 'rent') {
			$periods = listeo_core_get_rental_period();

			$current_selection = get_post_meta($post, '_rental_period', true);

			if (!empty($current_selection) && isset($periods[$current_selection])) {

				$output = $periods[$current_selection];
			} else {
				$output = '';
			}
		} else {
			if (get_option('listeo_core_hide_price_per_scale')) {
				$output = '';
			} else {
				$scale = get_option('listeo_scale', 'sq ft');
				$output .= ' / ' . apply_filters('listeo_core_scale', $scale);
			}
		}



		return apply_filters('get_listing_price', $output, $post);
	}



	public static function get_currency_symbol($currency = '', $show_vendor_currency_symbol = true)
	{
		if (! $currency) {
			$currency = get_option('currency');
		}

		switch ($currency) {
			case 'BHD':
				$currency_symbol = '.د.ب';
				break;
			case 'AED':
				$currency_symbol = 'د.إ';
				break;
			case 'AUD':
			case 'ARS':
			case 'CAD':
			case 'CLP':
			case 'COP':
			case 'HKD':
			case 'MXN':
			case 'NZD':
			case 'SGD':
			case 'USD':
				$currency_symbol = '&#36;';
				break;
			case 'BDT':
				$currency_symbol = '&#2547;&nbsp;';
				break;
			case 'LKR':
				$currency_symbol = '&#3515;&#3540;&nbsp;';
				break;
			case 'BGN':
				$currency_symbol = '&#1083;&#1074;.';
				break;
			case 'BRL':
				$currency_symbol = '&#82;&#36;';
				break;
			case 'CHF':
				$currency_symbol = '&#67;&#72;&#70;';
				break;
			case 'CNY':
			case 'JPY':
			case 'RMB':
				$currency_symbol = '&yen;';
				break;
			case 'CZK':
				$currency_symbol = '&#75;&#269;';
				break;
			case 'DKK':
				$currency_symbol = 'DKK';
				break;
			case 'DOP':
				$currency_symbol = 'RD&#36;';
				break;
			case 'EGP':
				$currency_symbol = 'EGP';
				break;
			case 'EUR':
				$currency_symbol = '&euro;';
				break;
			case 'GBP':
				$currency_symbol = '&pound;';
				break;
			case 'GHS':
				$currency_symbol = 'GH₵';
				break;
			case 'HRK':
				$currency_symbol = 'Kn';
				break;
			case 'HUF':
				$currency_symbol = '&#70;&#116;';
				break;
			case 'IDR':
				$currency_symbol = 'Rp';
				break;
			case 'ILS':
				$currency_symbol = '&#8362;';
				break;
			case 'INR':
				$currency_symbol = 'Rs.';
				break;
			case 'JOD':
				$currency_symbol = 'JOD';
				break;
			case 'ISK':
				$currency_symbol = 'Kr.';
				break;
			case 'KZT':
				$currency_symbol = '₸';
				break;
			case 'KIP':
				$currency_symbol = '&#8365;';
				break;
			case 'KRW':
				$currency_symbol = '&#8361;';
				break;
			case 'MYR':
				$currency_symbol = '&#82;&#77;';
				break;
			case 'NGN':
				$currency_symbol = '&#8358;';
				break;
			case 'NOK':
				$currency_symbol = '&#107;&#114;';
				break;
			case 'NPR':
				$currency_symbol = 'Rs.';
				break;
			case 'MAD':
				$currency_symbol = 'DH';
				break;
			case 'PHP':
				$currency_symbol = '&#8369;';
				break;
			case 'PLN':
				$currency_symbol = '&#122;&#322;';
				break;
			case 'PYG':
				$currency_symbol = '&#8370;';
				break;
			case 'RON':
				$currency_symbol = 'lei';
				break;
			case 'RUB':
				$currency_symbol = '&#1088;&#1091;&#1073;.';
				break;
			case 'SEK':
				$currency_symbol = '&#107;&#114;';
				break;
			case 'THB':
				$currency_symbol = '&#3647;';
				break;
			case 'TRY':
				$currency_symbol = '&#8378;';
				break;
			case 'TWD':
				$currency_symbol = '&#78;&#84;&#36;';
				break;
			case 'UAH':
				$currency_symbol = '&#8372;';
				break;
			case 'VND':
				$currency_symbol = '&#8363;';
				break;
			case 'ZAR':
				$currency_symbol = '&#82;';
				break;
			case 'ZMK':
				$currency_symbol = 'ZK';
				break;
			default:
				$currency_symbol = '';
				break;
		}

		return apply_filters('listeo_core_currency_symbol', $currency_symbol, $currency, $show_vendor_currency_symbol);
	}

	public static function get_listings_ordering_args($orderby = '', $order = '')
	{

		// Get ordering from query string unless defined
		if ($orderby) {
			$orderby_value = $orderby;
		} else {
			$orderby_value = isset($_GET['listeo_core_order']) ? (string) $_GET['listeo_core_order']  : get_option('listeo_sort_by', 'date');
		}

		// Get order + orderby args from string
		$orderby_value = explode('-', $orderby_value);
		$orderby       = esc_attr($orderby_value[0]);
		$order         = ! empty($orderby_value[1]) ? $orderby_value[1] : $order;

		$args    = array();

		// default - menu_order
		$args['orderby']  = 'date ID'; //featured
		$args['order']    = ('desc' === $order) ? 'DESC' : 'ASC';
		$args['meta_key'] = '';

		switch ($orderby) {
			case 'rand':
				$args['orderby']  = 'rand';
				break;
			case 'featured':
				$args['orderby']  = 'meta_value_num date';
				$args['meta_key']  = '_featured';

				break;
			case 'verified':
				$args['orderby']  = 'meta_value';
				$args['meta_key']  = '_verified';
				$args['order'] = 'DESC';
				$args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => '_verified',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_verified',
						'compare' => 'NOT EXISTS',
					),
				);
				break;

				break;
			case 'date':
				$args['orderby']  = 'date';
				$args['order']    = ('asc' === $order) ? 'ASC' : 'DESC';
				break;

			case 'highest-rated':
			case 'highest':
				$args['orderby']  = 'meta_value_num';
				$args['order']  = 'DESC';
				$args['meta_type'] = 'NUMERIC';
				$args['meta_key']  = '_combined_rating';
				break;
			case 'views':
				$args['orderby']  = 'meta_value_num';
				$args['order']  = 'DESC';
				$args['meta_type'] = 'NUMERIC';
				$args['meta_key']  = '_listing_views_count';
				break;
			case 'upcoming':
			case 'upcoming-event':
				// $args['orderby']  = array(
				// 	'meta_value_num' => 'ASC', // Order by meta value (date) in ascending order
				// //	'ID' => 'DESC' // If no meta value, order by post ID in descending order (newest to oldest)
				// );
				$args['listeo_key']  = '_event_date_timestamp';
				// $args['orderby']  = 'meta_value_num';
				// $args['order']  = 'ASC';


				break;
			case 'reviewed':
				$args['orderby']  = 'comment_count';
				$args['order']  = 'DESC';
				break;

			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = ('desc' === $order) ? 'DESC' : 'ASC';
				break;
			case 'best-match':
				// For best-match, default to date ordering (AI order will be handled at query level)
				$args['orderby'] = 'date';
				$args['order'] = 'DESC';
				break;
			case 'distance':
				// Distance-based ordering (handled by custom SQL)
				$args['orderby'] = 'distance';
				$args['order'] = 'ASC';
				$args['listeo_distance_order'] = true;
				break;
		case 'price':
			// Price sorting (ascending by default, handled by custom SQL)
			$args['orderby'] = 'price';
			$args['order'] = ('desc' === $order) ? 'DESC' : 'ASC';
			$args['listeo_price_order'] = true;
			$args['listeo_price_order_direction'] = ('desc' === $order) ? 'desc' : 'asc';
			break;
			default:
				$args['orderby']  = 'date ID';
				$args['order']    = ('ASC' === $order) ? 'ASC' : 'DESC';
				break;
		}

		return apply_filters('listeo_core_get_listings_ordering_args', $args);
	}

	/**
	 * Handle numeric price sorting.
	 *
	 * @access public
	 * @param array $args
	 * @return array
	 */
	public static function order_by_price_asc_post_clauses($clauses, $query)
	{
		// Only apply price sorting if specifically requested
		if (!$query->get('listeo_price_order_asc')) {
			return $clauses;
		}

		global $wpdb;
		// Support all listing price types:
		// - _price_min/_price_max: Price range (rentals, hotels, restaurants)
		// - _normal_price: Single price (services, events)
		// - _classifieds_price: Single price (classified ads)
		// For ascending sort, use the MINIMUM price from any field
		$clauses['join']    .= " INNER JOIN (
			SELECT post_id,
			       LEAST(
			           COALESCE(MIN(CASE WHEN meta_key='_price_min' THEN meta_value+0 END), 999999999),
			           COALESCE(MIN(CASE WHEN meta_key='_price_max' THEN meta_value+0 END), 999999999),
			           COALESCE(MIN(CASE WHEN meta_key='_normal_price' THEN meta_value+0 END), 999999999),
			           COALESCE(MIN(CASE WHEN meta_key='_classifieds_price' THEN meta_value+0 END), 999999999)
			       ) as price
			FROM $wpdb->postmeta
			WHERE meta_key IN ('_price_min', '_price_max', '_normal_price', '_classifieds_price')
			GROUP BY post_id
		) as price_query ON $wpdb->posts.ID = price_query.post_id ";
		$clauses['orderby'] = " price_query.price ASC ";
		return $clauses;
	}

	/**
	 * Handle numeric price sorting (descending).
	 *
	 * @access public
	 * @param array $clauses
	 * @param WP_Query $query
	 * @return array
	 */
	public static function order_by_price_desc_post_clauses($clauses, $query)
	{
		// Only apply price sorting if specifically requested
		if (!$query->get('listeo_price_order_desc')) {
			return $clauses;
		}

		global $wpdb;
		// Support all listing price types:
		// - _price_min/_price_max: Price range (rentals, hotels, restaurants)
		// - _normal_price: Single price (services, events)
		// - _classifieds_price: Single price (classified ads)
		// For descending sort, use the MAXIMUM price from any field
		$clauses['join']    .= " INNER JOIN (
			SELECT post_id,
			       GREATEST(
			           COALESCE(MAX(CASE WHEN meta_key='_price_min' THEN meta_value+0 END), 0),
			           COALESCE(MAX(CASE WHEN meta_key='_price_max' THEN meta_value+0 END), 0),
			           COALESCE(MAX(CASE WHEN meta_key='_normal_price' THEN meta_value+0 END), 0),
			           COALESCE(MAX(CASE WHEN meta_key='_classifieds_price' THEN meta_value+0 END), 0)
			       ) as price
			FROM $wpdb->postmeta
			WHERE meta_key IN ('_price_min', '_price_max', '_normal_price', '_classifieds_price')
			GROUP BY post_id
		) as price_query ON $wpdb->posts.ID = price_query.post_id ";
		$clauses['orderby'] = " price_query.price DESC ";
		return $clauses;
	}

	/**
	 * Handle distance-based sorting using Haversine formula.
	 *
	 * @access public
	 * @param array $clauses
	 * @param WP_Query $query
	 * @return array
	 */
	public static function order_by_distance_post_clauses($clauses, $query)
	{
		// Only apply distance sorting if specifically requested
		if (!$query->get('listeo_distance_order')) {
			return $clauses;
		}

		// If no location coordinates provided, fall back to default ordering
		if (!$query->get('listeo_user_lat') || !$query->get('listeo_user_lng')) {
			// Fallback to date ordering when no location is available
			$clauses['orderby'] = 'wp_posts.post_date DESC, wp_posts.ID DESC';
			return $clauses;
		}

		global $wpdb;
		
		$user_lat = floatval($query->get('listeo_user_lat'));
		$user_lng = floatval($query->get('listeo_user_lng'));
		$unit = $query->get('listeo_distance_unit') ?: 'km';
		
		// Earth's radius in km or miles
		$radius = ($unit === 'km') ? 6371 : 3959;
		
		// Get max distance for performance optimization
		// Use query-specific radius from AJAX handler, fall back to global setting
		$max_distance = $query->get('listeo_default_radius') ?: get_option('listeo_distance_default_radius', 50);
		
		// Calculate bounding box for performance optimization
		// This dramatically reduces the number of Haversine calculations needed
		$lat_range = $max_distance / 111; // ~111km per degree latitude
		$lng_range = $max_distance / (111 * cos(deg2rad($user_lat))); // Varies by latitude
		
		$min_lat = $user_lat - $lat_range;
		$max_lat = $user_lat + $lat_range;
		$min_lng = $user_lng - $lng_range;
		$max_lng = $user_lng + $lng_range;

		// Add distance calculation to SELECT fields
		$clauses['fields'] .= ", ($radius * acos(
			cos(radians($user_lat)) * cos(radians(lat_meta.meta_value)) * 
			cos(radians(lng_meta.meta_value) - radians($user_lng)) + 
			sin(radians($user_lat)) * sin(radians(lat_meta.meta_value))
		)) AS distance";

		// Add JOINs for latitude and longitude meta fields
		$clauses['join'] .= "
			LEFT JOIN $wpdb->postmeta lat_meta 
				ON $wpdb->posts.ID = lat_meta.post_id 
				AND lat_meta.meta_key = '_geolocation_lat'
			LEFT JOIN $wpdb->postmeta lng_meta 
				ON $wpdb->posts.ID = lng_meta.post_id 
				AND lng_meta.meta_key = '_geolocation_long'";

		// Order by calculated distance (ascending - nearest first)
		$clauses['orderby'] = 'distance ASC, ' . $wpdb->posts . '.post_date DESC';

		// PERFORMANCE OPTIMIZATION: Add bounding box filter BEFORE Haversine calculation
		// This reduces distance calculations from potentially thousands to dozens/hundreds
		$clauses['where'] .= $wpdb->prepare(
			" AND lat_meta.meta_value IS NOT NULL AND lng_meta.meta_value IS NOT NULL
			  AND lat_meta.meta_value BETWEEN %f AND %f 
			  AND lng_meta.meta_value BETWEEN %f AND %f",
			$min_lat,
			$max_lat, 
			$min_lng,
			$max_lng
		);

		// Add HAVING clause to further limit results by actual calculated distance
		$clauses['having'] = $wpdb->prepare("distance <= %f", $max_distance);

		return $clauses;
	}
}
