<?php
/**
 * Listeo Integration
 *
 * Provides Listeo-specific listing search and details functionality
 * Only active when Listeo theme or core plugin is detected
 *
 * @package AI_Chat_By_Purethemes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class Listeo_AI_Integration {

    /**
     * Constructor - Registers REST API routes
     */
    public function __construct() {
        // Only register routes if Listeo is available
        if (Listeo_AI_Detection::is_listeo_available()) {
            add_action('rest_api_init', array($this, 'register_listeo_routes'));
        }
    }

    /**
     * Register Listeo-specific REST API routes
     */
    public function register_listeo_routes() {

        // Hybrid search endpoint (AI semantic search + Listeo filters)
        register_rest_route('listeo/v1', '/listeo-hybrid-search', array(
            'methods' => 'POST',
            'callback' => array($this, 'hybrid_search'),
            'permission_callback' => '__return_true', // Public: global hourly API cap enforced in callback
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Natural language search query'
                ),
                'location' => array(
                    'type' => 'string',
                    'description' => 'Location search (address, city, region)'
                ),
                'radius' => array(
                    'type' => 'integer',
                    'description' => 'Search radius in km/miles'
                ),
                'category' => array(
                    'type' => 'string',
                    'description' => 'Category slug or ID'
                ),
                'features' => array(
                    'type' => 'array',
                    'description' => 'Array of feature slugs',
                    'items' => array('type' => 'string')
                ),
                'listing_type' => array(
                    'type' => 'string',
                    'description' => 'Listing type: service, rental, event, classifieds'
                ),
                'rating' => array(
                    'type' => 'number',
                    'description' => 'Minimum rating (1-5)'
                ),
                'price_min' => array(
                    'type' => 'number',
                    'description' => 'Minimum price'
                ),
                'price_max' => array(
                    'type' => 'number',
                    'description' => 'Maximum price'
                ),
                'open_now' => array(
                    'type' => 'boolean',
                    'description' => 'Filter by currently open businesses'
                ),
                'date_start' => array(
                    'type' => 'string',
                    'description' => 'Start date (mm/dd/yyyy)'
                ),
                'date_end' => array(
                    'type' => 'string',
                    'description' => 'End date (mm/dd/yyyy)'
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'default' => 10,
                    'description' => 'Results per page'
                )
            )
        ));

        // Get listing details endpoint (supports multiple IDs for comparison)
        register_rest_route('listeo/v1', '/listeo-listing-details', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_listing_details'),
            'permission_callback' => '__return_true', // Public by design: returns public listing data only
            'args' => array(
                'listing_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Single listing post ID (legacy support)'
                ),
                'listing_ids' => array(
                    'required' => false,
                    'type' => 'array',
                    'items' => array('type' => 'integer'),
                    'description' => 'Array of listing post IDs (max 3 for comparison)',
                    'maxItems' => 3
                )
            )
        ));
    }

    /**
     * Hybrid search endpoint - combines AI search with Listeo filters
     * Adapted from ai-chat-search plugin
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function hybrid_search($request) {
        // Read-only pre-filter; actual atomic quota is consumed deeper in AI_Engine → generate_embedding() / expand_query_if_enabled()
        if (!Listeo_AI_Search_Embedding_Manager::check_rate_limit()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'type' => 'rate_limit_error'
            ), 429);
        }

        // Check if Listeo Core is active
        if (!class_exists('Listeo_Core_Listing')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Listeo Core plugin is required but not active.',
                'results' => array()
            ), 503);
        }

        $query = $request->get_param('query');
        $location = $request->get_param('location');
        $radius = $request->get_param('radius');
        $source = $request->get_param('source');
        $is_chatbot = ($source === 'chatbot');
        $has_ai = class_exists('Listeo_AI_Search_AI_Engine');

        $debug = get_option('listeo_ai_search_debug_mode', false);

        if ($debug) {
            error_log('=== LISTEO HYBRID SEARCH (Universal Plugin) ===');
            error_log('Query: ' . ($query ?: 'NOT SET'));
            error_log('Location: ' . ($location ?: 'NOT SET'));
            error_log('AI Available: ' . ($has_ai ? 'YES' : 'NO'));
        }

        // Normalize location
        if (!empty($location)) {
            $location = $this->normalize_location($location);
        }

        // Check geocoding availability
        $geocoding_provider = get_option('listeo_geocoding_provider', 'google');
        if ($geocoding_provider == 'google') {
            $has_geocoding = !empty(get_option('listeo_maps_api_server'));
        } else {
            $has_geocoding = !empty(get_option('listeo_geoapify_maps_api_server'));
        }

        // Build query args
        $query_args = array(
            'post_type' => 'listing',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => 1,
            'ignore_sticky_posts' => 1,
            'ai_search_input' => $query,
            'tax-listing_category' => $request->get_param('category'),
            'tax-listing_feature' => $request->get_param('features'),
            '_listing_type' => $request->get_param('listing_type'),
            'rating-filter' => $request->get_param('rating'),
            'open_now' => $request->get_param('open_now'),
        );

        // Location handling - only add if AI is not active
        if (!empty($location) && !($has_ai && !empty($query))) {
            $query_args['location'] = $location;
            if (!empty($radius) && $has_geocoding) {
                $query_args['search_radius'] = $radius;
            } else {
                $query_args['search_radius'] = 0;
            }
        }

        // Price params (applied post-query)
        $price_min = $request->get_param('price_min');
        $price_max = $request->get_param('price_max');

        // Date range handling
        $date_start = $request->get_param('date_start');
        $date_end = $request->get_param('date_end');

        if ($date_start && $date_end) {
            $query_args['date_start'] = $date_start;
            $query_args['date_end'] = $date_end;
            if (!$has_ai || empty($query)) {
                $query_args['_booking_status'] = 'on';
            }
        }

        // Execute search
        $results = array();
        $ai_notice = null;
        $ai_notice_type = null;

        if ($has_ai && !empty($query)) {
            // Check if embeddings exist before attempting AI search
            if (class_exists('Listeo_AI_Search_Database_Manager')) {
                $has_embeddings = Listeo_AI_Search_Database_Manager::has_any_embeddings('listing');
                if (!$has_embeddings) {
                    $ai_notice = __('No data available yet. Please train the AI first.', 'ai-chat-search');
                    $ai_notice_type = 'no_embeddings';
                    // Return early with notice
                    return new WP_REST_Response(array(
                        'success' => true,
                        'search_type' => 'hybrid_ai_enabled',
                        'ai_available' => $has_ai,
                        'geocoding_available' => $has_geocoding,
                        'query' => $query,
                        'total' => 0,
                        'total_displayed' => 0,
                        'display_limit' => intval(get_option('listeo_ai_chat_max_results', 10)),
                        'results' => array(),
                        'notice' => $ai_notice,
                        'notice_type' => $ai_notice_type
                    ), 200);
                }
            }

            // LOCATION PRE-FILTERING: SQL filter BEFORE AI search to reduce embeddings loaded
            $location_filtered_ids = array();
            $location_specified = !empty($location);
            if ($location_specified) {
                $location_filtered_ids = $this->get_listing_ids_by_location($location);
                if ($debug) {
                    error_log('LOCATION PRE-FILTER: Found ' . count($location_filtered_ids) . ' listings matching location: ' . $location);
                }

                if (empty($location_filtered_ids)) {
                    if ($debug) {
                        error_log('LOCATION PRE-FILTER: No listings in location "' . $location . '"' . ($is_chatbot ? ' - continuing without location pre-filter for LLM filtering' : ' - returning empty results'));
                    }

                    if ($is_chatbot) {
                        $location_filtered_ids = array();
                    } else {
                        return array(
                            'results' => array(),
                            'total' => 0,
                            'page' => 1,
                            'per_page' => $per_page,
                            'search_mode' => 'ai',
                            'location_not_found' => true,
                        );
                    }
                }
            }

            // AI search path - pass location-filtered IDs to reduce embedding load
            // When chatbot: skip threshold (LLM will re-rank), use chatbot max results limit
            $ai_results = apply_filters('listeo_search_ai_post_ids', $query, $location_filtered_ids, $is_chatbot);

            if ($debug) {
                error_log('AI search returned: ' . (is_array($ai_results) ? count($ai_results) : 0) . ' results');
            }

            if (!is_array($ai_results) || count($ai_results) === 0 || (count($ai_results) === 1 && $ai_results[0] === 0)) {
                $results = array();
            } else {
                $custom_query_args = array(
                    'post_type' => 'listing',
                    'post_status' => 'publish',
                    'posts_per_page' => count($ai_results),
                    'paged' => 1,
                    'post__in' => $ai_results,
                    'orderby' => 'post__in',
                    'ignore_sticky_posts' => 1,
                );

                $listings = new WP_Query($custom_query_args);

                if ($listings->have_posts()) {
                    while ($listings->have_posts()) {
                        $listings->the_post();
                        $results[] = $this->format_listing_data(get_the_ID());
                    }
                    wp_reset_postdata();
                }
            }
        } else {
            // Traditional search path
            $listings = Listeo_Core_Listing::get_real_listings($query_args);

            if ($listings->have_posts()) {
                while ($listings->have_posts()) {
                    $listings->the_post();
                    $results[] = $this->format_listing_data(get_the_ID());
                }
                wp_reset_postdata();
            }
        }

        // Post-query filtering
        // Location filter is now handled by SQL pre-filter (see lines 199-205)
        // No need for PHP array_filter() - we already filtered at SQL level before loading embeddings!

        // Price filter
        if ($price_min || $price_max) {
            $results = array_filter($results, function($listing) use ($price_min, $price_max) {
                $search_min = $price_min ?: 0;
                $search_max = $price_max ?: 999999;
                $listing_min = !empty($listing['pricing']['price_min']) ? floatval($listing['pricing']['price_min']) : 0;
                $listing_max = !empty($listing['pricing']['price_max']) ? floatval($listing['pricing']['price_max']) : 999999;
                return ($listing_min <= $search_max) && ($listing_max >= $search_min);
            });
            $results = array_values($results);
        }

        // Rating filter
        $rating_filter = $request->get_param('rating');
        if ($rating_filter && $has_ai && !empty($query)) {
            $results = array_filter($results, function($listing) use ($rating_filter) {
                $listing_rating = !empty($listing['rating']['average']) ? floatval($listing['rating']['average']) : 0;
                return $listing_rating >= floatval($rating_filter);
            });
            $results = array_values($results);
        }

        // Date filter - only include listings that have date-relevant data
        if ($date_start && $date_end && $has_ai && !empty($query)) {
            $results = array_filter($results, function($listing) use ($date_start, $date_end) {
                // Event dates check - listing has event dates, check if they overlap with search range
                if (!empty($listing['event'])) {
                    return $this->check_event_date_overlap($listing, $date_start, $date_end);
                }
                // Booking availability check - listing has booking enabled, check if available in search range
                if (!empty($listing['booking']['enabled'])) {
                    return $this->check_listing_availability($listing['id'], $date_start, $date_end);
                }
                // Listing has no event dates and no booking - exclude from date-filtered results
                return false;
            });
            $results = array_values($results);
        }

        // Limit results
        $display_limit = intval(get_option('listeo_ai_chat_max_results', 10));
        $actual_total = count($results);
        $displayed_results = array_slice($results, 0, $display_limit);

        return new WP_REST_Response(array(
            'success' => true,
            'search_type' => ($has_ai && !empty($query)) ? 'hybrid_ai_enabled' : 'hybrid_traditional',
            'ai_available' => $has_ai,
            'geocoding_available' => $has_geocoding,
            'query' => $query,
            'total' => $actual_total,
            'total_displayed' => count($displayed_results),
            'display_limit' => $display_limit,
            'results' => $displayed_results
        ), 200);
    }

    /**
     * Get listing details endpoint (supports multiple IDs for comparison)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_listing_details($request) {
        // Support both single ID (legacy) and array of IDs
        $listing_ids = $request->get_param('listing_ids');
        $single_id = $request->get_param('listing_id');

        // Normalize to array
        if (!empty($listing_ids) && is_array($listing_ids)) {
            $ids = array_map('intval', $listing_ids);
        } elseif (!empty($single_id)) {
            $ids = array(intval($single_id));
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Missing listing_id or listing_ids parameter.'
            ), 400);
        }

        // Limit to 3 listings max
        $ids = array_slice($ids, 0, 3);

        // Check if embedding manager is available
        if (!class_exists('Listeo_AI_Search_Embedding_Manager')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Embedding manager not available. Please install ai-chat-search plugin.'
            ), 503);
        }

        $embedding_manager = new Listeo_AI_Search_Embedding_Manager();
        $listings = array();
        $errors = array();

        foreach ($ids as $listing_id) {
            // Verify listing exists
            $post = get_post($listing_id);
            if (!$post || $post->post_type !== 'listing' || $post->post_status !== 'publish') {
                $errors[] = "Listing {$listing_id} not found or not published.";
                continue;
            }

            // Get structured content
            $structured_content = '';
            if (method_exists($embedding_manager, 'get_listing_content_for_embedding')) {
                $structured_content = $embedding_manager->get_listing_content_for_embedding($listing_id);
            } elseif (method_exists($embedding_manager, 'get_content_for_embedding')) {
                $structured_content = $embedding_manager->get_content_for_embedding($listing_id);
            }

            if (empty($structured_content)) {
                $errors[] = "Could not generate content for listing {$listing_id}.";
                continue;
            }

            // Add additional data
            $opening_hours_text = $this->get_opening_hours_text($listing_id);
            $faq_text = $this->get_faq_text($listing_id);
            $pricing_text = $this->get_pricing_menu_text($listing_id);
            $booking_prices_text = $this->get_booking_prices_text($listing_id);
            $event_dates_text = $this->get_event_dates_text($listing_id);

            // Add extended context (POI, Nearby Listings)
            $extended_context = '';
            if (class_exists('Listeo_AI_Content_Extractor_Listing')) {
                $extractor = new Listeo_AI_Content_Extractor_Listing();
                $extended_context = $extractor->get_extended_context($listing_id);
                if (!empty($extended_context)) {
                    $extended_context = "\n\n" . $extended_context;
                }
            }

            // Combine all content
            $full_content = $structured_content . $opening_hours_text . $faq_text . $pricing_text . $booking_prices_text . $event_dates_text . $extended_context;

            $listings[] = array(
                'listing_id' => $listing_id,
                'title' => html_entity_decode(wp_strip_all_tags(get_the_title($listing_id)), ENT_QUOTES, 'UTF-8'),
                'url' => esc_url(get_permalink($listing_id)),
                'structured_content' => $full_content
            );
        }

        if (empty($listings)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => !empty($errors) ? implode(' ', $errors) : 'No valid listings found.'
            ), 404);
        }

        // For backward compatibility: if single listing requested, return flat structure
        if (count($ids) === 1 && count($listings) === 1) {
            return new WP_REST_Response(array(
                'success' => true,
                'listing_id' => $listings[0]['listing_id'],
                'title' => $listings[0]['title'],
                'url' => $listings[0]['url'],
                'structured_content' => $listings[0]['structured_content']
            ), 200);
        }

        // Multiple listings: return array structure
        return new WP_REST_Response(array(
            'success' => true,
            'count' => count($listings),
            'listings' => $listings,
            'errors' => !empty($errors) ? $errors : null
        ), 200);
    }

    /**
     * Format listing data for API response
     *
     * @param int $listing_id
     * @return array
     */
    private function format_listing_data($listing_id) {
        $post = get_post($listing_id);

        // Basic info
        $data = array(
            'id' => $listing_id,
            'title' => html_entity_decode(wp_strip_all_tags(get_the_title($listing_id)), ENT_QUOTES, 'UTF-8'),
            'slug' => $post->post_name,
            'url' => esc_url(get_permalink($listing_id)),
            'excerpt' => html_entity_decode(wp_strip_all_tags(get_the_excerpt($listing_id)), ENT_QUOTES, 'UTF-8'),
            'content' => html_entity_decode(wp_strip_all_tags(apply_filters('the_content', $post->post_content)), ENT_QUOTES, 'UTF-8'),
            'featured_image' => get_the_post_thumbnail_url($listing_id, 'thumbnail'),
            'gallery' => $this->get_gallery_images($listing_id),
        );

        // Location data
        $friendly_address = get_post_meta($listing_id, '_friendly_address', true);
        $google_address = get_post_meta($listing_id, '_address', true);
        $combined_address = trim($friendly_address . ' ' . $google_address);

        $data['location'] = array(
            'address' => html_entity_decode(wp_strip_all_tags($combined_address), ENT_QUOTES, 'UTF-8'),
            'lat' => floatval(get_post_meta($listing_id, '_geolocation_lat', true)),
            'lng' => floatval(get_post_meta($listing_id, '_geolocation_long', true)),
            'region' => wp_get_post_terms($listing_id, 'region', array('fields' => 'names'))
        );

        // Business details
        $data['listing_type'] = get_post_meta($listing_id, '_listing_type', true);
        $data['categories'] = wp_get_post_terms($listing_id, 'listing_category', array('fields' => 'names'));
        $data['features'] = wp_get_post_terms($listing_id, 'listing_feature', array('fields' => 'names'));
        $data['llm_categories'] = $this->get_listing_categories_for_llm($listing_id);
        $data['llm_features'] = $this->get_listing_features_for_llm($listing_id);

        // Contact info
        $data['contact'] = array(
            'phone' => get_post_meta($listing_id, '_phone', true),
            'email' => get_post_meta($listing_id, '_email', true),
            'website' => esc_url(get_post_meta($listing_id, '_website', true)),
        );

        // Pricing
        $price_min = get_post_meta($listing_id, '_price_min', true);
        $price_max = get_post_meta($listing_id, '_price_max', true);

        if (empty($price_min) && empty($price_max)) {
            $normal_price = get_post_meta($listing_id, '_normal_price', true);
            if (!empty($normal_price)) {
                $price_min = $normal_price;
                $price_max = $normal_price;
            }
        }

        $data['pricing'] = array(
            'price' => get_post_meta($listing_id, '_price', true),
            'price_min' => $price_min,
            'price_max' => $price_max,
            'currency' => get_option('listeo_currency', 'USD'),
        );

        // Rating
        $data['rating'] = array(
            'average' => floatval(get_post_meta($listing_id, '_combined_rating', true)),
            'count' => intval(get_post_meta($listing_id, 'listeo-reviews-count', true))
        );

        // Opening hours
        $opening_hours = get_post_meta($listing_id, '_opening_hours', true);
        if ($opening_hours) {
            $data['opening_hours'] = maybe_unserialize($opening_hours);
            $data['open_now'] = $this->is_open_now($listing_id);
        }

        // Booking
        $data['booking'] = array(
            'enabled' => get_post_meta($listing_id, '_booking_status', true) === 'on',
            'instant' => get_post_meta($listing_id, '_instant_booking', true) === 'on',
        );

        // Event dates - use timestamps for reliable date comparison
        $event_date = get_post_meta($listing_id, '_event_date', true);
        $event_date_end = get_post_meta($listing_id, '_event_date_end', true);
        $event_timestamp = get_post_meta($listing_id, '_event_date_timestamp', true);
        $event_end_timestamp = get_post_meta($listing_id, '_event_date_end_timestamp', true);

        if (!empty($event_date) || !empty($event_timestamp)) {
            $data['event'] = array(
                'start_date' => $event_date,
                'end_date' => !empty($event_date_end) ? $event_date_end : $event_date,
                'start_timestamp' => !empty($event_timestamp) ? intval($event_timestamp) : null,
                'end_timestamp' => !empty($event_end_timestamp) ? intval($event_end_timestamp) : (!empty($event_timestamp) ? intval($event_timestamp) : null)
            );

            // Add human-readable event_dates at root level for LLM context (cache-agnostic)
            $data['event_dates'] = $this->format_event_dates_for_display($event_timestamp, $event_end_timestamp);
        }

        return apply_filters('listeo_api_listing_data', $data, $listing_id);
    }

    // ===== HELPER METHODS =====

    private function get_listing_categories_for_llm($listing_id) {
        $listing_type = get_post_meta($listing_id, '_listing_type', true);
        $taxonomies = array('listing_category', 'service_category', 'rental_category', 'event_category', 'classifieds_category');

        if (!empty($listing_type)) {
            $taxonomies[] = $listing_type . '_category';
        }

        return $this->get_unique_term_names($listing_id, array_unique($taxonomies));
    }

    private function get_listing_features_for_llm($listing_id) {
        $features = $this->get_unique_term_names($listing_id, array('listing_feature'));
        $meta_features = get_post_meta($listing_id, '_features', true);

        if (is_array($meta_features)) {
            $features = array_merge($features, $meta_features);
        } elseif (is_string($meta_features) && $meta_features !== '') {
            $features[] = $meta_features;
        }

        return $this->normalize_string_list($features);
    }

    private function get_unique_term_names($post_id, $taxonomies) {
        $names = array();

        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
            if (!is_wp_error($terms) && !empty($terms)) {
                $names = array_merge($names, $terms);
            }
        }

        return $this->normalize_string_list($names);
    }

    private function normalize_string_list($values) {
        $normalized = array();

        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
            $value = trim(preg_replace('/\s+/', ' ', $value));

            if ($value === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            $normalized[$key] = $value;
        }

        return array_values($normalized);
    }

    private function get_gallery_images($listing_id) {
        $gallery_ids = get_post_meta($listing_id, '_gallery', true);
        if (empty($gallery_ids)) return array();

        if (is_string($gallery_ids)) {
            $gallery_ids = explode(',', $gallery_ids);
        } elseif (!is_array($gallery_ids)) {
            return array();
        }

        $images = array();
        foreach ($gallery_ids as $image_id) {
            $image_id = intval($image_id);
            if ($image_id > 0) {
                $images[] = array(
                    'id' => $image_id,
                    'url' => wp_get_attachment_url($image_id),
                    'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                    'medium' => wp_get_attachment_image_url($image_id, 'medium'),
                    'large' => wp_get_attachment_image_url($image_id, 'large'),
                );
            }
        }
        return $images;
    }

    private function is_open_now($listing_id) {
        $current_day = strtolower(date('l'));
        $current_time = date('H:i');

        // Format 1: Check for serialized opening hours
        $opening_hours = get_post_meta($listing_id, '_opening_hours', true);
        if (!empty($opening_hours)) {
            $hours = maybe_unserialize($opening_hours);
            if (is_array($hours) && isset($hours[$current_day])) {
                $day_hours = $hours[$current_day];
                if (!empty($day_hours['opening']) && !empty($day_hours['closing'])) {
                    return ($current_time >= $day_hours['opening'] && $current_time <= $day_hours['closing']);
                }
            }
        }

        // Format 2: Check for separate meta fields
        $opening = get_post_meta($listing_id, "_{$current_day}_opening_hour", true);
        $closing = get_post_meta($listing_id, "_{$current_day}_closing_hour", true);

        // Extract value from array if needed
        if (is_array($opening) && isset($opening[0])) {
            $opening = $opening[0];
        }
        if (is_array($closing) && isset($closing[0])) {
            $closing = $closing[0];
        }

        if (!empty($opening) && !empty($closing)) {
            return ($current_time >= $opening && $current_time <= $closing);
        }

        return null;
    }

    private function check_listing_availability($listing_id, $check_in, $check_out) {
        global $wpdb;

        $check_in_obj = DateTime::createFromFormat('m/d/Y', $check_in);
        $check_out_obj = DateTime::createFromFormat('m/d/Y', $check_out);

        if (!$check_in_obj || !$check_out_obj) {
            return false; // Invalid dates - exclude listing
        }

        $date_start = $check_in_obj->format('Y-m-d');
        $date_end = $check_out_obj->format('Y-m-d');
        $table_name = $wpdb->prefix . 'bookings_calendar';

        $conflicting_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE listing_id = %d
            AND status IN ('confirmed', 'paid', 'approved')
            AND date_start <= %s
            AND date_end >= %s
        ", $listing_id, $date_end, $date_start));

        return ($conflicting_bookings == 0);
    }

    private function check_event_date_overlap($listing, $search_start, $search_end) {
        $search_start_obj = DateTime::createFromFormat('m/d/Y', $search_start);
        $search_end_obj = DateTime::createFromFormat('m/d/Y', $search_end);

        if (!$search_start_obj || !$search_end_obj) {
            return false; // Invalid search dates - exclude listing
        }

        $search_start_ts = $search_start_obj->getTimestamp();
        $search_end_ts = $search_end_obj->getTimestamp();

        // Use stored timestamps if available (more reliable), fallback to strtotime on date strings
        if (!empty($listing['event']['start_timestamp'])) {
            $event_start_ts = $listing['event']['start_timestamp'];
            $event_end_ts = !empty($listing['event']['end_timestamp']) ? $listing['event']['end_timestamp'] : $event_start_ts;
        } else {
            $event_start_ts = strtotime($listing['event']['start_date']);
            $event_end_ts = strtotime($listing['event']['end_date']);
        }

        if (!$event_start_ts || !$event_end_ts) {
            return false; // Invalid event dates - exclude listing
        }

        return ($event_start_ts <= $search_end_ts) && ($event_end_ts >= $search_start_ts);
    }

    private function normalize_location($location) {
        if (empty($location)) {
            return $location;
        }
        return strtolower(remove_accents($location));
    }

    /**
     * Format event dates for display (human-readable, no time)
     * Uses timestamps for reliable formatting
     */
    private function format_event_dates_for_display($start_timestamp, $end_timestamp) {
        if (empty($start_timestamp)) {
            return null;
        }

        $start_ts = intval($start_timestamp);
        $end_ts = !empty($end_timestamp) ? intval($end_timestamp) : $start_ts;

        // Format: "Jan 15, 2026"
        $start_formatted = date_i18n('M j, Y', $start_ts);
        $end_formatted = date_i18n('M j, Y', $end_ts);

        if ($start_formatted === $end_formatted) {
            return $start_formatted;
        }

        return $start_formatted . ' – ' . $end_formatted;
    }

    private function get_opening_hours_text($listing_id) {
        $days_map = array(
            'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
            'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'
        );

        // Format 1: Check for serialized opening hours
        $opening_hours = get_post_meta($listing_id, '_opening_hours', true);
        $hours_data = array();

        if ($opening_hours) {
            $hours = maybe_unserialize($opening_hours);
            if (is_array($hours)) {
                $hours_data = $hours;
            }
        }

        // Format 2: Check for separate meta fields if Format 1 not found
        if (empty($hours_data)) {
            foreach (array_keys($days_map) as $day) {
                $opening = get_post_meta($listing_id, "_{$day}_opening_hour", true);
                $closing = get_post_meta($listing_id, "_{$day}_closing_hour", true);

                // Extract value from array if needed
                if (is_array($opening) && isset($opening[0])) {
                    $opening = $opening[0];
                }
                if (is_array($closing) && isset($closing[0])) {
                    $closing = $closing[0];
                }

                if (!empty($opening) && !empty($closing)) {
                    $hours_data[$day] = array('opening' => $opening, 'closing' => $closing);
                }
            }
        }

        if (empty($hours_data)) {
            return '';
        }

        $text = "\n\nOPENING HOURS:\n";
        foreach ($days_map as $day_key => $day_name) {
            if (isset($hours_data[$day_key])) {
                $day_hours = $hours_data[$day_key];
                if (!empty($day_hours['opening']) && !empty($day_hours['closing'])) {
                    $text .= "- {$day_name}: {$day_hours['opening']} - {$day_hours['closing']}\n";
                } else {
                    $text .= "- {$day_name}: Closed\n";
                }
            }
        }

        $is_open_now = $this->is_open_now($listing_id);
        if ($is_open_now !== null) {
            $text .= "\nCurrently: " . ($is_open_now ? "OPEN" : "CLOSED") . "\n";
        }

        return $text;
    }

    private function get_faq_text($listing_id) {
        $faq_status = get_post_meta($listing_id, '_faq_status', true);
        if ($faq_status !== 'on') {
            return '';
        }

        $faqs = get_post_meta($listing_id, '_faq_list', true);
        if (empty($faqs) || !is_array($faqs)) {
            return '';
        }

        $text = "\n\nFREQUENTLY ASKED QUESTIONS:\n";
        foreach ($faqs as $faq) {
            if (!empty($faq['question'])) {
                $question = $faq['question'];
                $answer = !empty($faq['answer']) ? strip_tags($faq['answer']) : 'No answer provided';
                $text .= "\nQ: {$question}\n";
                $text .= "A: {$answer}\n";
            }
        }

        return $text;
    }

    private function get_pricing_menu_text($listing_id) {
        $menu_status = get_post_meta($listing_id, '_menu_status', true);
        $hide_pricing = get_post_meta($listing_id, '_hide_pricing_if_bookable', true);

        if (!$menu_status || $hide_pricing) {
            return '';
        }

        $menu = get_post_meta($listing_id, '_menu', true);
        if (empty($menu) || !is_array($menu)) {
            return '';
        }

        $text = "\n\nPRICING MENU:\n";
        foreach ($menu as $menu_section) {
            if (!empty($menu_section['menu_title'])) {
                $text .= "\n" . strtoupper($menu_section['menu_title']) . ":\n";
            }

            if (!empty($menu_section['menu_elements']) && is_array($menu_section['menu_elements'])) {
                foreach ($menu_section['menu_elements'] as $item) {
                    if (!empty($item['name'])) {
                        $name = $item['name'];
                        $price = !empty($item['price']) ? $item['price'] : 'Free';
                        $description = !empty($item['description']) ? ' - ' . strip_tags($item['description']) : '';
                        $text .= "- {$name}: {$price}{$description}\n";
                    }
                }
            }
        }

        return $text;
    }

    private function get_booking_prices_text($listing_id) {
        $booking_status = get_post_meta($listing_id, '_booking_status', true);
        if ($booking_status !== 'on') {
            return '';
        }

        $normal_price = get_post_meta($listing_id, '_normal_price', true);
        $weekday_price = get_post_meta($listing_id, '_weekday_price', true);

        if (empty($normal_price) && empty($weekday_price)) {
            return '';
        }

        $currency_abbr = get_option('listeo_currency', 'USD');
        $currency_symbol = class_exists('Listeo_Core_Listing') ? Listeo_Core_Listing::get_currency_symbol($currency_abbr) : '$';

        $text = "\n\nBOOKING PRICES:\n";
        if (!empty($normal_price)) {
            $text .= "- Regular Rate: {$currency_symbol}{$normal_price}\n";
        }
        if (!empty($weekday_price)) {
            $text .= "- Weekend Rate: {$currency_symbol}{$weekday_price}\n";
        }

        return $text;
    }

    private function get_event_dates_text($listing_id) {
        $event_date = get_post_meta($listing_id, '_event_date', true);
        $event_date_end = get_post_meta($listing_id, '_event_date_end', true);

        if (empty($event_date)) {
            return '';
        }

        $text = "\n\nEVENT DATES:\n";
        $start_formatted = date('F j, Y g:i A', strtotime($event_date));
        $text .= "- Start: {$start_formatted}\n";

        if (!empty($event_date_end) && $event_date_end !== $event_date) {
            $end_formatted = date('F j, Y g:i A', strtotime($event_date_end));
            $text .= "- End: {$end_formatted}\n";
        }

        return $text;
    }

    /**
     * Get listing IDs by location using SQL pre-filter
     * This runs BEFORE AI search to reduce embeddings loaded
     *
     * @param string $location Location search term
     * @return array Array of listing IDs matching location
     */
    private function get_listing_ids_by_location($location) {
        global $wpdb;

        if (empty($location)) {
            return array();
        }

        $normalized_location = $this->normalize_location($location);
        $safe_location = '%' . $wpdb->esc_like($normalized_location) . '%';

        // Check both _address AND _friendly_address meta fields
        $query = $wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_address ON p.ID = pm_address.post_id AND pm_address.meta_key = '_address'
            LEFT JOIN {$wpdb->postmeta} pm_friendly ON p.ID = pm_friendly.post_id AND pm_friendly.meta_key = '_friendly_address'
            WHERE p.post_type = 'listing'
            AND p.post_status = 'publish'
            AND (
                pm_address.meta_value LIKE %s
                OR pm_friendly.meta_value LIKE %s
            )
        ", $safe_location, $safe_location);

        $results = $wpdb->get_col($query);

        return array_map('intval', $results);
    }
}
