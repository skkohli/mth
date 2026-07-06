<?php
// --- START OF UPGRADED class-lds-ajax-handler.php ---
class LDS_Ajax_Handler {
    private $cache_expiry = 30 * DAY_IN_SECONDS;
    const PLACES_NEW_ENABLE_URL = 'https://console.cloud.google.com/google/maps-apis/api-list';

    public function __construct() {
        add_action('wp_ajax_lds_get_place_ids', [ $this, 'get_place_ids' ]);
        add_action('wp_ajax_lds_fetch_place_details', [ $this, 'fetch_place_details' ]);
        add_action('wp_ajax_lds_process_batch_ai', [ $this, 'process_batch_ai' ]);
        add_action('wp_ajax_lds_process_single_job', [ $this, 'process_single_job' ]);
        add_action('wp_ajax_lds_check_duplicate', [ $this, 'check_duplicate' ]);
        add_action('wp_ajax_lds_test_api_key', [ $this, 'test_api_key' ]);
        add_action('wp_ajax_lds_test_openai_api_key', [ $this, 'test_openai_api_key' ]);
        add_action('wp_ajax_lds_test_outscraper_api_key', [ $this, 'test_outscraper_api_key' ]);
        add_action('wp_ajax_lds_run_image_regeneration', [ $this, 'run_image_regeneration' ]);
        add_action('wp_ajax_lds_process_image_regeneration_batch', [ $this, 'process_image_regeneration_batch' ]);
        add_action('wp_ajax_lds_save_default_location', [ $this, 'save_default_location' ]);
        add_action('wp_ajax_lds_get_current_location_settings', [ $this, 'get_current_location_settings' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
        add_action('wp_ajax_lds_debug', [ $this, 'debug_ajax_request' ]);
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'listeo-data-scraper') === false && strpos($hook, 'lds-settings') === false) return;
    
        // Enqueue the main script
        $js_path = LDS_PLUGIN_DIR . 'assets/js/lds-admin-scripts.js';
        $js_ver  = file_exists($js_path) ? filemtime($js_path) : LDS_VERSION;
        wp_enqueue_script('lds-admin-js', LDS_PLUGIN_URL . 'assets/js/lds-admin-scripts.js', ['jquery'], $js_ver, true);
    
        // --- NEW PART: Pass PHP settings to our JavaScript file ---
        $settings_for_js = [
            'import_limit' => LDS_Pro_Manager::get_import_limit(),
            'nonce' => wp_create_nonce('lds_import_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'ai_enabled' => (bool) get_option('lds_enable_ai_descriptions', 1),
            'is_pro' => LDS_Pro_Manager::is_pro_active(),
            'schedule_locked' => LDS_Pro_Manager::is_feature_locked('schedule_import'),
            'min_schedule_minutes' => (int) ( LDS_Scheduler::MIN_DELAY / MINUTE_IN_SECONDS ),
            'max_schedule_minutes' => (int) ( LDS_Scheduler::MAX_DELAY / MINUTE_IN_SECONDS ),
            'schedule_page_size' => (int) LDS_Scheduler::LIST_LIMIT,
            'upgrade_url_schedule' => LDS_Pro_Manager::get_upgrade_url('schedule_import'),
            'i18n' => [
                'api_error' => __('API Error', 'listeo-data-scraper'),
                'api_key_problem_detected' => __('API Key Problem Detected', 'listeo-data-scraper'),
                'details' => __('Details', 'listeo-data-scraper'),
                'error' => __('Error', 'listeo-data-scraper'),
                'failed' => __('Failed', 'listeo-data-scraper'),
                'how_to_fix' => __('How to fix', 'listeo-data-scraper'),
                'import_stopped' => __('Import stopped. Please fix your API key and try again.', 'listeo-data-scraper'),
                'missing_category' => __('Category is required', 'listeo-data-scraper'),
                'show_technical_details' => __('Show technical details', 'listeo-data-scraper'),
                'technical_message' => __('Technical message', 'listeo-data-scraper'),
                'tip' => __('Tip', 'listeo-data-scraper'),
                'tip_test_api_key' => __('Use the "Test API Key" button in Settings to verify your key is working correctly.', 'listeo-data-scraper'),
                'troubleshooting' => __('Troubleshooting', 'listeo-data-scraper'),
                'warning' => __('Warning', 'listeo-data-scraper'),
                'schedule_saved' => __('Import scheduled successfully.', 'listeo-data-scraper'),
                'schedule_save_failed' => __('Could not save the schedule.', 'listeo-data-scraper'),
                'schedule_delete_confirm' => __('Delete this scheduled import?', 'listeo-data-scraper'),
                'schedule_fill_form' => __('Please fill out the import form (business type, location, listing type and category) before scheduling.', 'listeo-data-scraper'),
                'schedule_invalid_time' => __('Please enter a valid time.', 'listeo-data-scraper'),
                'schedule_pro_required' => __('Scheduled Imports is a Pro feature.', 'listeo-data-scraper'),
                'schedule_none' => __('No scheduled imports yet.', 'listeo-data-scraper'),
                /* translators: {max} is replaced with a number in the browser. */
                'schedule_hint' => __('Up to {max} days from now. Runs once via WP-Cron when your site receives traffic at/after that time.', 'listeo-data-scraper'),
                /* translators: {min} is replaced with a number in the browser. */
                'schedule_min_error' => __('Please choose at least {min} minutes from now.', 'listeo-data-scraper'),
                /* translators: {max} is replaced with a number in the browser. */
                'schedule_max_error' => __('Please choose a time within {max} days from now.', 'listeo-data-scraper'),
                /* translators: {label} is the saved schedule name. */
                'schedule_edit_intro' => __('Change when "{label}" runs.', 'listeo-data-scraper'),
                'schedule_create_intro' => __('This import will run automatically with the settings below.', 'listeo-data-scraper'),
                'schedule_runnow_confirm' => __('Run this import now? This will start the import immediately and may take a moment.', 'listeo-data-scraper'),
                'schedule_rerun_confirm' => __('Run this import again now? It will import any new listings found for the same search.', 'listeo-data-scraper'),
                'schedule_btn_create' => __('Schedule Import', 'listeo-data-scraper'),
                'schedule_btn_save' => __('Save', 'listeo-data-scraper'),
                'sched_status_processing' => __('Processing', 'listeo-data-scraper'),
                'sched_business' => __('Business type', 'listeo-data-scraper'),
                'sched_location' => __('Location', 'listeo-data-scraper'),
                'sched_listing_type' => __('Listing type', 'listeo-data-scraper'),
                'sched_categories' => __('Categories', 'listeo-data-scraper'),
                'sched_regions' => __('Regions', 'listeo-data-scraper'),
                'sched_features' => __('Features', 'listeo-data-scraper'),
                'sched_photos' => __('Photos', 'listeo-data-scraper'),
                'sched_selected' => __('selected', 'listeo-data-scraper'),
                'sched_yes' => __('Yes', 'listeo-data-scraper'),
                'sched_no' => __('No', 'listeo-data-scraper'),
                'sched_map_location' => __('Map location', 'listeo-data-scraper'),
            ],
        ];
        wp_localize_script('lds-admin-js', 'lds_admin_vars', $settings_for_js);
        // This creates a JavaScript object named 'lds_settings' that our script can access.
    
        // Enqueue the style
        $css_path = LDS_PLUGIN_DIR . 'assets/css/lds-admin-styles.css';
        $css_ver  = file_exists($css_path) ? filemtime($css_path) : LDS_VERSION;
        wp_enqueue_style('lds-admin-css', LDS_PLUGIN_URL . 'assets/css/lds-admin-styles.css', [], $css_ver);
    }
    

    public function get_place_ids() {
        check_ajax_referer('lds_import_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query']);
        if (empty($query)) {
            wp_send_json_error(['message' => 'Search query cannot be empty.']);
        }

        // Get API source from settings
        $api_source = get_option('lds_api_source', 'google');

        // Get appropriate API key based on source
        if ($api_source === 'outscraper') {
            $api_key = get_option('lds_outscraper_api_key');
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'Outscraper API Key is not set in Settings.']);
            }
        } else {
            $api_key = get_option('lds_google_api_key');
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'Google API Key is not set in Settings.']);
            }
        }

        // Check search mode (text or map)
        $search_mode = isset($_POST['lds_search_mode']) ? sanitize_text_field($_POST['lds_search_mode']) : 'text';

        // Block map mode if not Pro (server-side validation)
        if ($search_mode === 'map' && LDS_Pro_Manager::is_feature_locked('map_mode')) {
            lds_log("Map search attempted without Pro license - blocking request", 'PRO_CHECK');
            wp_send_json_error([
                'message' => 'Interactive Map Search is a Pro feature.',
                'type' => 'pro_required',
                'upgrade_url' => LDS_Pro_Manager::get_upgrade_url('map_mode')
            ]);
        }

        // Get coordinates for map mode
        $lat = null;
        $lng = null;
        $location_address = null;

        if ($search_mode === 'map') {
            $lat = isset($_POST['lds_lat']) ? floatval($_POST['lds_lat']) : null;
            $lng = isset($_POST['lds_lng']) ? floatval($_POST['lds_lng']) : null;

            if (empty($lat) || empty($lng)) {
                wp_send_json_error(['message' => 'Map coordinates are required for map search mode.']);
            }

            // Get human-readable address from location field
            $location_address = isset($_POST['lds_location']) ? sanitize_text_field($_POST['lds_location']) : null;

            lds_log("Map search: {$query} near {$location_address} at ({$lat}, {$lng}) (using Text Search)", 'MAP_SEARCH');
        }
    
        // Enforce Pro/Free limits (server-side validation)
        $limit = LDS_Pro_Manager::get_import_limit();

        lds_log("Import limit enforced: User={$limit}, Pro=" . (LDS_Pro_Manager::is_pro_active() ? 'yes' : 'no'), 'PRO_CHECK');
        
        // Get language setting for API
        $lang_setting = get_option('lds_description_language', 'site-default');
        $language = ($lang_setting === 'site-default') ? get_locale() : $lang_setting;

        // Instantiate appropriate API class based on source
        if ($api_source === 'outscraper') {
            $api = new LDS_Outscraper_API($api_key, $language);
            lds_log("Using Outscraper API for search", 'API_SOURCE');
        } else {
            $api = new LDS_Google_API($api_key, $language);
            lds_log("Using Google Places API for search", 'API_SOURCE');
        }

        $found_new_place_ids = [];
        $next_page_token = null;
        $max_pages = 10;
        $current_page = 0;
        $search_warning = null;
        $seen_place_ids_count = 0;
        $seen_place_ids = [];
        $already_imported_place_ids = [];

        do {
            $current_page++;
            lds_log("Fetching page {$current_page} to find {$limit} new listings...", 'SMART_SEARCH');

            // Use appropriate search method based on mode
            if ($search_mode === 'map') {
                // For map search, combine the query with the human-readable address
                $combined_query = !empty($location_address) ? "{$query} near {$location_address}" : $query;
                $api_response = $api->fetch_place_ids_paginated($combined_query, $next_page_token, $limit);
            } else {
                $api_response = $api->fetch_place_ids_paginated($query, $next_page_token, $limit);
            }

            if (is_wp_error($api_response)) {
                $api_name = ($api_source === 'outscraper') ? 'Outscraper' : 'Google';

                if ($api_name === 'Google' && $this->is_google_pagination_invalid_request($api_response)) {
                    lds_log('Google pagination token stayed invalid after retries. Stopping pagination and keeping results found so far.', 'GOOGLE_PAGINATION_STOP');

                    $places_new_unavailable = $this->is_places_new_unavailable_error($api_response);
                    $search_warning = $this->build_api_error_payload($api_name, $api_response);
                    $search_warning['type'] = 'api_warning';
                    $search_warning['technical_message'] = $search_warning['message'];
                    if (empty($search_warning['diagnostics']) || !is_array($search_warning['diagnostics'])) {
                        $search_warning['diagnostics'] = [];
                    }
                    $this->add_api_diagnostic($search_warning['diagnostics'], __('Google pages loaded before pagination failed', 'listeo-data-scraper'), max(0, $current_page - 1));
                    $this->add_api_diagnostic($search_warning['diagnostics'], __('Listings returned before pagination failed', 'listeo-data-scraper'), $seen_place_ids_count);

                    if ($places_new_unavailable) {
                        $search_warning['message'] = __('This Google key is using the legacy Places API, and legacy pagination failed after the first page. Enable Places API (New) in Google Cloud to import more Google results.', 'listeo-data-scraper');
                        $search_warning['detailed_message'] = __('Open the Google Maps API list, enable Places API (New), then make sure this API key is allowed to use it.', 'listeo-data-scraper');
                        $search_warning['action_label'] = __('Enable Places API (New)', 'listeo-data-scraper');
                        $search_warning['action_url'] = self::PLACES_NEW_ENABLE_URL;
                    } elseif (!empty($found_new_place_ids)) {
                        $search_warning['message'] = __('Google loaded the first page of listings, but the next page could not be loaded. This does not mean there are no more matching businesses. Import will continue with the listings found so far. Try a more specific search query or location if you need more results.', 'listeo-data-scraper');
                    } elseif ($seen_place_ids_count > 0) {
                        $search_warning['message'] = __('Google loaded listings, but the next page could not be loaded. The listings found so far already exist in your database. This does not mean there are no more matching businesses. Try a more specific search query or location if you need new listings.', 'listeo-data-scraper');
                    } else {
                        $search_warning['message'] = __('Google could not load listings for this query. Try a different search query or location.', 'listeo-data-scraper');
                    }

                    break;
                }

                wp_send_json_error($this->build_api_error_payload($api_name, $api_response));
            }

            $place_ids_from_api = $api_response['place_ids'];
            $next_page_token = $api_response['next_page_token'];
            $seen_place_ids_count += count($place_ids_from_api);

            foreach ($place_ids_from_api as $place_id_from_api) {
                if (!empty($place_id_from_api)) {
                    $seen_place_ids[$place_id_from_api] = true;
                }
            }

            if (empty($place_ids_from_api)) {
                break;
            }

            // --- THIS IS THE CORRECTED PART ---
            // Step A: Get the IDs of posts that match one of the Place IDs. This is very fast.
            $existing_post_ids = get_posts([
                'post_type'      => 'listing',
                'posts_per_page' => -1,
                'fields'         => 'ids', // This is the correct parameter to get just the post IDs.
                'meta_query'     => [
                    [
                        'key'     => '_place_id',
                        'value'   => $place_ids_from_api,
                        'compare' => 'IN',
                    ]
                ]
            ]);
            
            // Step B: Now, get the actual Place ID meta values for those posts.
            $existing_place_ids = [];
            if (!empty($existing_post_ids)) {
                foreach ($existing_post_ids as $post_id) {
                    // Get the single meta value (the string) for the _place_id key.
                    $place_id_meta = get_post_meta($post_id, '_place_id', true);
                    if ($place_id_meta) {
                        $existing_place_ids[] = $place_id_meta;
                        $already_imported_place_ids[$place_id_meta] = true;
                    }
                }
            }
            
            // Step C: Now we can safely compare the two arrays of strings.
            $new_ids_in_batch = array_diff($place_ids_from_api, $existing_place_ids);
    
            if (!empty($new_ids_in_batch)) {
                foreach ($new_ids_in_batch as $new_id) {
                    if (count($found_new_place_ids) < $limit) {
                        $found_new_place_ids[] = $new_id;
                    } else {
                        break;
                    }
                }
            }

        } while (count($found_new_place_ids) < $limit && !empty($next_page_token) && $current_page < $max_pages);

        $search_summary = $this->build_search_summary(
            count($already_imported_place_ids),
            count(array_unique($found_new_place_ids)),
            $limit
        );

        if (!empty($search_warning)) {
            $search_warning['summary'] = $search_summary;
        }
	    
        if (empty($found_new_place_ids)) {
            if (!empty($search_warning)) {
                wp_send_json_error([
                    'message' => $search_warning['message'],
                    'type' => 'no_results',
                    'summary' => $search_summary,
                    'warning' => $search_warning,
                    'suggestion' => __('The first results may already be imported. Try adding a neighborhood, a more specific business type, or a different location.', 'listeo-data-scraper'),
                ]);
            }

            wp_send_json_error([
                'message' => 'No new listings found for this query. All results found already exist in your database or Google returned no results.',
                'type' => 'no_results',
                'summary' => $search_summary,
                'suggestion' => 'If you expect results for this search, check: 1) Your Google API key is working (use "Test API Key" in Settings), 2) Try different search terms, 3) Check if Places API is enabled in Google Cloud Console.'
            ]);
        }
    
	        lds_log("Smart search complete. Found " . count($found_new_place_ids) . " new listings to import.", 'SMART_SEARCH');
        $success_payload = [
            'place_ids' => $found_new_place_ids,
            'summary' => $search_summary,
        ];
        if (!empty($search_warning)) {
            $success_payload['warning'] = $search_warning;
        }

        wp_send_json_success($success_payload);
    }

    private function build_search_summary($already_imported_count, $new_results_count, $import_limit) {
        return [
            [
                'key' => 'ready_to_import',
                'label' => __('listings ready to import', 'listeo-data-scraper'),
                'value' => absint($new_results_count),
            ],
            [
                'key' => 'already_imported',
                'label' => __('already imported skipped', 'listeo-data-scraper'),
                'value' => absint($already_imported_count),
            ],
            [
                'key' => 'import_limit',
                'label' => __('import limit', 'listeo-data-scraper'),
                'value' => absint($import_limit),
            ],
        ];
    }

    /**
     * Get detailed user-friendly error message for Google API errors
     * 
     * @param string $error_message Raw error message from Google API
     * @param array $error_data Structured API error data.
     * @return string User-friendly detailed message
     */
    private function get_detailed_api_error_message($error_message, $error_data = []) {
        $google_status = $error_data['google_status'] ?? '';
        $request_context = isset($error_data['request_context']) && is_array($error_data['request_context'])
            ? $error_data['request_context']
            : [];

        if ($google_status === 'PERMISSION_DENIED' || strpos($error_message, 'PERMISSION_DENIED') !== false) {
            if (strpos($error_message, 'Places API (New)') !== false || strpos($error_message, 'places.googleapis.com') !== false) {
                return __('Places API (New) is not enabled or is blocked for this key. Enable "Places API (New)" in Google Cloud Console, then check API restrictions and make sure this server IP is allowed.', 'listeo-data-scraper');
            }

            return __('Google denied the API request. Check that the correct API is enabled, billing is active, and this server IP is allowed by the API key restrictions.', 'listeo-data-scraper');
        } elseif ($google_status === 'REQUEST_DENIED' || strpos($error_message, 'REQUEST_DENIED') !== false) {
            if (strpos($error_message, 'restricted') !== false) {
                return __('Your API key has restrictions that are blocking this request. Go to Google Cloud Console > APIs & Services > Credentials > Your API Key > Application restrictions / API restrictions and ensure "Places API" is enabled and HTTP referrer restrictions (if any) include your website domain.', 'listeo-data-scraper');
            } elseif (strpos($error_message, 'API key not valid') !== false) {
                return __('Your API key is invalid. Please check for typos in the Settings page, or generate a new API key in Google Cloud Console.', 'listeo-data-scraper');
            } elseif (strpos($error_message, 'expired') !== false) {
                return __('Your API key has expired. Generate a new API key in Google Cloud Console.', 'listeo-data-scraper');
            } elseif (strpos($error_message, 'not authorized to use this API key') !== false) {
                return __('API key authorization error. Go to Google Cloud Console > APIs & Services > Credentials > Your API Key and configure: 1) Set Application restrictions to "IP addresses (web servers, cron jobs, etc.)" and add your server IP address, 2) Under "API restrictions" ensure both "Places API" AND "Maps JavaScript API" are selected. Your server IP can be found in the plugin Settings page.', 'listeo-data-scraper');
            } else {
                return __('Google is denying requests from your API key. This usually means the key is invalid, expired, or has incorrect restrictions. Please check your API key settings in Google Cloud Console.', 'listeo-data-scraper');
            }
        } elseif ($google_status === 'OVER_QUERY_LIMIT' || strpos($error_message, 'OVER_QUERY_LIMIT') !== false) {
            return __('You have exceeded your Google Places API quota. Check your Google Cloud Console billing and increase your quota limits, or wait until your quota resets.', 'listeo-data-scraper');
        } elseif ($google_status === 'INVALID_REQUEST' || strpos($error_message, 'INVALID_REQUEST') !== false) {
            if (!empty($request_context['page_token_request'])) {
                return __('Google rejected the next-page token after retries. This is a Google pagination failure, not confirmation that no more matching businesses exist. Retry the import; if it repeats, try a more specific query or location and check billing, API restrictions, and whether Places API is enabled.', 'listeo-data-scraper');
            }

            return __('Google marked the request invalid. Check the search query, billing, API restrictions, and whether Places API is enabled. If this happens only after some results are fetched, it may be a pagination-token timing issue.', 'listeo-data-scraper');
        } else {
            return __('Please check your Google API key configuration in Google Cloud Console and ensure the Places API is enabled.', 'listeo-data-scraper');
        }
    }

    private function build_api_error_payload($api_name, $api_error) {
        $error_data = $api_error->get_error_data();
        $error_data = is_array($error_data) ? $error_data : [];
        $error_message = $this->strip_api_error_prefixes($api_error->get_error_message(), $api_name);
        $is_api_key_related = $this->is_api_key_related_error($error_message, $error_data);

        $payload = [
            'message' => $error_message,
            'type' => $is_api_key_related ? 'api_key_error' : 'api_error',
            'api_name' => $api_name,
        ];

        if ($api_name === 'Google') {
            $payload['detailed_message'] = $this->get_detailed_api_error_message($error_message, $error_data);
            if ($this->is_places_new_unavailable_error_data($error_data, $error_message)) {
                $payload['action_label'] = __('Enable Places API (New)', 'listeo-data-scraper');
                $payload['action_url'] = self::PLACES_NEW_ENABLE_URL;
            }
        }

        $diagnostics = $this->build_api_error_diagnostics($api_name, $error_data);
        if (!empty($diagnostics)) {
            $payload['diagnostics'] = $diagnostics;
        }

        return $payload;
    }

    private function is_google_pagination_invalid_request($api_error) {
        $error_data = $api_error->get_error_data();
        if (!is_array($error_data)) {
            return false;
        }

        $request_context = isset($error_data['request_context']) && is_array($error_data['request_context'])
            ? $error_data['request_context']
            : [];

        return ($error_data['google_status'] ?? '') === 'INVALID_REQUEST'
            && !empty($request_context['page_token_request']);
    }

    private function is_places_new_unavailable_error($api_error) {
        if (!is_wp_error($api_error)) {
            return false;
        }

        $error_data = $api_error->get_error_data();
        $error_data = is_array($error_data) ? $error_data : [];

        return $this->is_places_new_unavailable_error_data($error_data, $api_error->get_error_message());
    }

    private function is_places_new_unavailable_error_data($error_data, $error_message = '') {
        $request_context = isset($error_data['request_context']) && is_array($error_data['request_context'])
            ? $error_data['request_context']
            : [];

        if (!empty($request_context['places_new_unavailable'])) {
            return true;
        }

        $google_status = $error_data['google_status'] ?? '';
        $google_message = $error_data['google_error_message'] ?? $error_message;

        return $google_status === 'PERMISSION_DENIED'
            && (
                strpos($google_message, 'Places API (New)') !== false
                || strpos($google_message, 'places.googleapis.com') !== false
                || strpos($google_message, 'disabled') !== false
            );
    }

    private function strip_api_error_prefixes($error_message, $api_name) {
        $prefixes = [
            $api_name . ' API Key Error:',
            $api_name . ' API Error:',
            'Google Nearby Search API Error:',
            'Google API Error:',
            'Outscraper API Error:',
        ];

        do {
            $stripped = false;
            foreach ($prefixes as $prefix) {
                if (stripos($error_message, $prefix) === 0) {
                    $error_message = trim(substr($error_message, strlen($prefix)));
                    $stripped = true;
                }
            }
        } while ($stripped);

        return $error_message;
    }

    private function is_api_key_related_error($error_message, $error_data = []) {
        $google_status = $error_data['google_status'] ?? '';

        return $google_status === 'REQUEST_DENIED'
            || $google_status === 'PERMISSION_DENIED'
            || $google_status === 'OVER_QUERY_LIMIT'
            || strpos($error_message, 'REQUEST_DENIED') !== false
            || strpos($error_message, 'PERMISSION_DENIED') !== false
            || strpos($error_message, 'API key not valid') !== false
            || strpos($error_message, 'restricted') !== false
            || strpos($error_message, 'OVER_QUERY_LIMIT') !== false
            || strpos($error_message, 'expired') !== false;
    }

    private function build_api_error_diagnostics($api_name, $error_data) {
        $diagnostics = [];

        $this->add_api_diagnostic($diagnostics, __('API source', 'listeo-data-scraper'), $api_name);
        $this->add_api_diagnostic($diagnostics, __('Endpoint', 'listeo-data-scraper'), $error_data['endpoint'] ?? '');
        $this->add_api_diagnostic($diagnostics, __('HTTP status', 'listeo-data-scraper'), $error_data['http_status'] ?? '');
        $this->add_api_diagnostic($diagnostics, __('Google status', 'listeo-data-scraper'), $error_data['google_status'] ?? '');
        $this->add_api_diagnostic($diagnostics, __('Google error message', 'listeo-data-scraper'), $error_data['google_error_message'] ?? '');

        if (!empty($error_data['request_context']) && is_array($error_data['request_context'])) {
            $context_labels = [
                'query' => __('Search query', 'listeo-data-scraper'),
                'language' => __('Language', 'listeo-data-scraper'),
                'api_version' => __('Google API version', 'listeo-data-scraper'),
                'places_new_unavailable' => __('Places API (New) unavailable', 'listeo-data-scraper'),
                'page_token_request' => __('Pagination request', 'listeo-data-scraper'),
                'pagination_retries' => __('Pagination retries', 'listeo-data-scraper'),
                'place_id' => __('Place ID', 'listeo-data-scraper'),
                'photo_limit' => __('Photo limit', 'listeo-data-scraper'),
            ];

            foreach ($context_labels as $context_key => $label) {
                if (!array_key_exists($context_key, $error_data['request_context'])) {
                    continue;
                }

                $value = $error_data['request_context'][$context_key];
                if (is_bool($value)) {
                    $value = $value ? __('Yes', 'listeo-data-scraper') : __('No', 'listeo-data-scraper');
                }

                $this->add_api_diagnostic($diagnostics, $label, $value);
            }
        }

        return $diagnostics;
    }

    private function add_api_diagnostic(&$diagnostics, $label, $value) {
        if (is_array($value) || is_object($value) || $value === '' || $value === null) {
            return;
        }

        $diagnostics[] = [
            'label' => sanitize_text_field($label),
            'value' => sanitize_text_field((string) $value),
        ];
    }

    public function fetch_place_details() {
        check_ajax_referer('lds_import_nonce', 'nonce');
        $place_id = sanitize_text_field($_POST['place_id']);
        if (empty($place_id)) { wp_send_json_error(['message' => 'Place ID cannot be empty.']); }

        // Get API source from settings
        $api_source = get_option('lds_api_source', 'google');

        // Get appropriate API key based on source
        if ($api_source === 'outscraper') {
            $api_key = get_option('lds_outscraper_api_key');
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'Outscraper API Key is not set in Settings.']);
            }
        } else {
            $api_key = get_option('lds_google_api_key');
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'Google API Key is not set in Settings.']);
            }
        }

        // Check if photo import is enabled before setting photo limit
        // For Google Places: Check lds_enable_photo_import (TOS compliance)
        // For Outscraper: Always allow photos (no TOS restrictions)
        if ($api_source === 'google') {
            $photo_import_enabled = get_option('lds_enable_photo_import', 0);
            $photo_limit = $photo_import_enabled ? LDS_Pro_Manager::get_photo_import_limit() : 0;
        } else {
            // Outscraper - no TOS restrictions, use photo limit directly
            $photo_limit = LDS_Pro_Manager::get_photo_import_limit();
        }

        // Get language setting for API
        $lang_setting = get_option('lds_description_language', 'site-default');
        $language = ($lang_setting === 'site-default') ? get_locale() : $lang_setting;

        // OPTIMIZATION: Determine if reviews are needed
        // Reviews are needed if:
        // 1. AI descriptions are enabled (reviews used for AI generation), OR
        // 2. User wants to import Google Reviews (import_place_id checkbox is checked)
        // This allows users to disable review fetching entirely to save API calls
        $ai_enabled = (bool) get_option('lds_enable_ai_descriptions', 1);

        // Get import preference from the current job (if available)
        // Note: This is set in the fetch_and_import_batch handler from the frontend checkbox
        $import_place_id_pref = isset($_POST['import_prefs']['import_place_id']) ? (int)$_POST['import_prefs']['import_place_id'] : 1;

        $fetch_reviews = $ai_enabled || $import_place_id_pref; // Fetch if AI enabled OR user wants reviews

        if (!$fetch_reviews) {
            lds_log("OPTIMIZATION: Skipping reviews fetch (AI disabled AND user disabled Google Reviews checkbox)", 'REVIEWS_OPTIMIZATION');
        } elseif ($ai_enabled && !$import_place_id_pref) {
            lds_log("Fetching reviews for AI descriptions only (user disabled Google Reviews checkbox)", 'REVIEWS_AI_ONLY');
        } elseif (!$ai_enabled && $import_place_id_pref) {
            lds_log("Fetching reviews for Google Reviews display only (AI descriptions disabled)", 'REVIEWS_DISPLAY_ONLY');
        }

        // Instantiate appropriate API class based on source
        if ($api_source === 'outscraper') {
            $api = new LDS_Outscraper_API($api_key, $language);
            // Pass fetch_reviews parameter for Outscraper (Google API doesn't support it yet)
            $place_data = $api->get_place_details($place_id, $photo_limit, $fetch_reviews);
        } else {
            $api = new LDS_Google_API($api_key, $language);
            // Google API class doesn't have fetch_reviews parameter yet (reviews come with details)
            $place_data = $api->get_place_details($place_id, $photo_limit);
        }
        if (is_wp_error($place_data) || empty($place_data)) {
            $api_name = ($api_source === 'outscraper') ? 'Outscraper' : 'Google';

            if (is_wp_error($place_data)) {
                $payload = $this->build_api_error_payload($api_name, $place_data);
                $payload['message'] = sprintf(
                    /* translators: %s: API error message. */
                    __('Could not fetch place details: %s', 'listeo-data-scraper'),
                    $payload['message']
                );
                wp_send_json_error($payload);
            }

            wp_send_json_error([
                'message' => __('Failed to get place details.', 'listeo-data-scraper'),
                'type' => 'api_error',
                'api_name' => $api_name,
            ]);
        }
        wp_send_json_success(['place_data' => $place_data, 'display_info' => ['name' => $place_data['name'] ?? 'Unknown Place', 'address' => $place_data['address'] ?? 'No address', 'rating' => $place_data['rating'] ?? 'No rating', 'phone' => $place_data['phone_number'] ?? 'No phone', 'website' => $place_data['website'] ?? 'No website']]);
    }

    public function process_batch_ai() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        $places_data = stripslashes_deep($_POST['places_data']);
        if (empty($places_data) || !is_array($places_data)) {
            wp_send_json_error(['message' => 'Invalid places data received.']);
        }

        wp_send_json_success(['listings' => $this->build_processed_listings($places_data)]);
    }

    /**
     * Turn an array of raw place-data records into ready-to-import listing records.
     *
     * Extracted from process_batch_ai() so the same AI generation, transient caching and
     * fallback logic can be reused by the server-side scheduled-import runner (which has no
     * AJAX request). Returns the processed listings array (never sends a JSON response).
     *
     * @param array $places_data Array of place-data arrays from the API classes.
     * @return array Processed listing-data arrays ready for LDS_Importer::create_listing().
     */
    public function build_processed_listings($places_data) {
        if (empty($places_data) || !is_array($places_data)) {
            return [];
        }

        $ai_enabled = (bool) get_option('lds_enable_ai_descriptions', 1);

        // Block AI if locked (server-side validation)
        $is_ai_locked = LDS_Pro_Manager::is_feature_locked('ai_descriptions');
        if ($ai_enabled && $is_ai_locked) {
            lds_log('AI descriptions attempted without Pro license - using fallback', 'PRO_CHECK');
            $ai_enabled = false; // Force fallback descriptions
        }

        $processed_listings = [];
        $places_needing_ai = [];
        $cache_hits = 0;

        if ($ai_enabled) {
            foreach ($places_data as $place_data) {
                $cache_key = $this->get_description_cache_key($place_data);
                $cached_data = get_transient($cache_key);

                if ($cached_data !== false) {
                    // Handle both old format (string) and new format (array with description + SEO)
                    if (is_array($cached_data) && isset($cached_data['description'])) {
                        $description = $cached_data['description'];
                        $seo_data = [
                            'seo_title' => $cached_data['seo_title'] ?? '',
                            'seo_description' => $cached_data['seo_description'] ?? '',
                            'focus_keyphrase' => $cached_data['focus_keyphrase'] ?? '',
                        ];
                    } else {
                        // Old format - just a string
                        $description = $cached_data;
                        $seo_data = [];
                    }
                    $listing_data = $this->process_data_locally($place_data, $description, $seo_data);
                    $processed_listings[] = $listing_data;
                    $cache_hits++;
                } else {
                    // Pass the full place data, including new fields
                    $places_needing_ai[] = $place_data;
                }
            }

            lds_log("AI enabled. Cache performance: {$cache_hits} hits, " . count($places_needing_ai) . " need AI", 'CACHE_STATS');

            if (!empty($places_needing_ai)) {
                $ai_descriptions_result = $this->get_batch_descriptions_directly($places_needing_ai);

                if (!is_wp_error($ai_descriptions_result)) {
                    foreach ($places_needing_ai as $index => $place_data) {
                        // AI result is now an object with description + optional SEO fields
                        $ai_data = $ai_descriptions_result[$index] ?? null;

                        if ($ai_data && isset($ai_data['description'])) {
                            $description = $ai_data['description'];
                            $seo_data = [
                                'seo_title' => $ai_data['seo_title'] ?? '',
                                'seo_description' => $ai_data['seo_description'] ?? '',
                                'focus_keyphrase' => $ai_data['focus_keyphrase'] ?? '',
                            ];
                        } else {
                            $description = $this->generate_fallback_description($place_data);
                            $seo_data = [];
                        }

                        $cache_key = $this->get_description_cache_key($place_data);
                        // Cache full AI data (description + SEO)
                        set_transient($cache_key, $ai_data ?: ['description' => $description], $this->cache_expiry);
                        $listing_data = $this->process_data_locally($place_data, $description, $seo_data);
                        $processed_listings[] = $listing_data;
                    }
                } else {
                    lds_log('AI batch processing failed: ' . $ai_descriptions_result->get_error_message(), 'AI_ERROR');
                    foreach ($places_needing_ai as $place_data) {
                        $description = $this->generate_fallback_description($place_data);
                        $listing_data = $this->process_data_locally($place_data, $description);
                        $processed_listings[] = $listing_data;
                    }
                }
            }
        } else {
            lds_log('AI descriptions disabled. Using fallback descriptions for all places.', 'AI_DISABLED');
            foreach ($places_data as $place_data) {
                $description = $this->generate_fallback_description($place_data);
                $listing_data = $this->process_data_locally($place_data, $description);
                $processed_listings[] = $listing_data;
            }
        }

        return $processed_listings;
    }

    public function check_duplicate() {
        check_ajax_referer('lds_import_nonce', 'nonce');
    
        $place_id = sanitize_text_field($_POST['place_id'] ?? '');
    
        if (empty($place_id)) {
            wp_send_json_error(['message' => 'No Place ID provided for duplicate check.']);
        }
    
        // This is the same query used by the importer. It's very efficient.
        $existing_posts = get_posts([
            'post_type'      => 'listing',
            'meta_key'       => '_place_id',
            'meta_value'     => $place_id,
            'posts_per_page' => 1,
            'post_status'    => 'any', // Check against all statuses (published, draft, etc.)
            'fields'         => 'ids', // We only need the ID, which is very fast.
        ]);
    
        if (!empty($existing_posts)) {
            // Found a duplicate
            wp_send_json_success(['duplicate' => true, 'post_id' => $existing_posts[0]]);
        } else {
            // Not a duplicate
            wp_send_json_success(['duplicate' => false]);
        }
    }

    private function get_selected_ai_config() {
        $provider = LDS_AI_Provider::get_selected_provider();

        return [
            'provider' => $provider,
            'label' => LDS_AI_Provider::get_provider_label($provider),
            'api_key' => LDS_AI_Provider::get_api_key($provider),
            'endpoint' => LDS_AI_Provider::get_chat_endpoint($provider),
        ];
    }

    private function get_validated_ai_model($provider, $update_option = false) {
        $selected_model = get_option(
            'lds_gpt_model',
            LDS_AI_Provider::get_default_model($provider)
        );

        if (
            empty($selected_model) ||
            !LDS_AI_Provider::is_valid_model($provider, $selected_model)
        ) {
            $selected_model = LDS_AI_Provider::get_default_model($provider);
            if ($update_option) {
                update_option('lds_gpt_model', $selected_model);
            }
        }

        return $selected_model;
    }

    private function get_ai_request_headers($provider, $api_key) {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];

        if ($provider === LDS_AI_Provider::OPENROUTER) {
            $headers['HTTP-Referer'] = home_url('/');
            $headers['X-Title'] = sanitize_text_field(
                wp_strip_all_tags(get_bloginfo('name') ?: 'Listeo Data Importer')
            );
        }

        return $headers;
    }

    private function add_ai_generation_parameters(&$payload, $provider, $selected_model) {
        if (
            $provider === LDS_AI_Provider::OPENAI &&
            strpos($selected_model, 'gpt-5.') === 0
        ) {
            $payload['max_completion_tokens'] = 16000;
            $payload['verbosity'] = 'medium';
            return;
        }

        $payload['max_tokens'] = 16000;
        $payload['temperature'] = 0.7;
    }

    private function decode_ai_json_response($ai_content) {
        $ai_response = json_decode($ai_content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $ai_response;
        }

        $trimmed_content = trim($ai_content);
        if (
            preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed_content, $matches)
        ) {
            $ai_response = json_decode(trim($matches[1]), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $ai_response;
            }
        }

        $json_start = strpos($trimmed_content, '{');
        $json_end = strrpos($trimmed_content, '}');
        if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
            $json_fragment = substr(
                $trimmed_content,
                $json_start,
                $json_end - $json_start + 1
            );
            $ai_response = json_decode($json_fragment, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $ai_response;
            }
        }

        return null;
    }

    private function get_batch_descriptions_directly($places_data) {
        $ai_config = $this->get_selected_ai_config();
        if (empty($ai_config['api_key'])) {
            return new WP_Error(
                'ai_config_error',
                sprintf(
                    __('%s API Key is not set in Listeo Scraper settings.', 'listeo-data-scraper'),
                    $ai_config['label']
                )
            );
        }

        // Get the selected GPT model from settings
        $selected_model = $this->get_validated_ai_model(
            $ai_config['provider'],
            true
        );
        
        // Debug: Log the selected model
        if (get_option('lds_enable_debug_mode', 0)) {
            error_log("[LDS_DEBUG] Selected AI provider: " . $ai_config['provider']);
            error_log("[LDS_DEBUG] Selected AI model: " . $selected_model);
        }

        $lang_setting = get_option('lds_description_language', 'site-default');
        if ($lang_setting === 'site-default') {
            if (class_exists('Locale')) {
                try {
                    $description_language = \Locale::getDisplayLanguage(get_locale(), 'en');
                } catch (\Exception $e) {
                    $description_language = get_locale();
                }
            } else {
                $description_language = get_locale();
            }
        } else {
            $description_language = $lang_setting;
        }

        // Get description word length setting
        $word_length = get_option('lds_description_word_length', 300);

        $batch_size = count($places_data);

        // Build complete system prompt: locked section + editable instructions
        $locked_prompt = $this->get_locked_system_prompt();
        $editable_instructions = get_option('lds_ai_system_prompt', $this->get_default_editable_instructions());

        // Combine both sections
        $system_prompt = $locked_prompt . "\n\n" . $editable_instructions;

        // Replace dynamic placeholders with actual values
        $system_prompt = str_replace(
            ['{{LANGUAGE}}', '{{WORD_LENGTH}}'],
            [htmlspecialchars($description_language, ENT_QUOTES, 'UTF-8'), $word_length],
            $system_prompt
        );

        // --- The rest of the function is the same ---
        $business_data_for_ai = [];
        foreach ($places_data as $listing) {
            $review_snippets = [];
            if (!empty($listing['reviews']) && is_array($listing['reviews'])) {
                $good_reviews = array_filter($listing['reviews'], function($review) {
                    return !empty($review['text']) && $review['rating'] >= 4;
                });
                usort($good_reviews, function($a, $b) {
                    return $b['rating'] <=> $a['rating'];
                });
                $top_reviews = array_slice($good_reviews, 0, 3);
                foreach ($top_reviews as $review) {
                    $review_snippets[] = $review['text'];
                }
            }

            $business_data_for_ai[] = [
                'name' => $listing['name'] ?? 'Unknown Business',
                'address' => $listing['address'] ?? '',
                'types' => $listing['types'] ?? [],
                'rating' => $listing['rating'] ?? 0,
                'rating_count' => $listing['user_ratings_total'] ?? 0,
                'review_snippets' => $review_snippets,
            ];
        }

        $user_prompt = "Generate HTML descriptions for these " . $batch_size . " businesses based on the provided data:\n\n" . json_encode($business_data_for_ai, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Build payload with model-specific parameters
        $payload = [
            "model" => $selected_model,
            "messages" => [
                ["role" => "system", "content" => $system_prompt],
                ["role" => "user", "content" => $user_prompt]
            ],
        ];

        if ($ai_config['provider'] === LDS_AI_Provider::OPENAI) {
            $payload["response_format"] = ["type" => "json_object"];
        }

        $this->add_ai_generation_parameters(
            $payload,
            $ai_config['provider'],
            $selected_model
        );

        // Debug: Log the payload model parameter
        if (get_option('lds_enable_debug_mode', 0)) {
            error_log("[LDS_DEBUG] API Payload model parameter: " . $payload["model"]);
        }

        $response = wp_remote_post($ai_config['endpoint'], [
            'method'    => 'POST', 'timeout'   => 90,
            'headers'   => $this->get_ai_request_headers(
                $ai_config['provider'],
                $ai_config['api_key']
            ),
            'body'      => json_encode($payload), 'sslverify' => true,
        ]);

        if (is_wp_error($response)) { return new WP_Error('openai_connection_error', sprintf(__('Failed to connect to %s API: %s', 'listeo-data-scraper'), $ai_config['label'], $response->get_error_message())); }
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Debug logging for troubleshooting
        lds_log($ai_config['label'] . ' API Response Code: ' . $response_code, 'OPENAI_DEBUG');
        lds_log($ai_config['label'] . ' API Response Body: ' . substr($response_body, 0, 500), 'OPENAI_DEBUG');
        
        if ($response_code !== 200) { $error_message = $response_data['error']['message'] ?? __('Unknown API error', 'listeo-data-scraper'); return new WP_Error('openai_api_error', $ai_config['label'] . ' API Error (HTTP ' . $response_code . '): ' . $error_message); }
        $ai_content = $response_data['choices'][0]['message']['content'] ?? null;
        if (!$ai_content) { 
            lds_log('Empty AI content. Full response: ' . print_r($response_data, true), 'OPENAI_DEBUG');
            return new WP_Error('openai_empty_response', sprintf(__('Empty response content from %s.', 'listeo-data-scraper'), $ai_config['label'])); 
        }
        $ai_response = $this->decode_ai_json_response($ai_content);
        if (!is_array($ai_response)) {
            return new WP_Error('openai_invalid_format', 'Invalid AI response format. AI returned: ' . $ai_content);
        }

        // Check if SEO plugin integration is enabled (Yoast SEO or Rank Math)
        $seo_setting_enabled = get_option('lds_enable_yoast_seo', 0);
        $yoast_installed = defined('WPSEO_VERSION');
        $rankmath_installed = defined('RANK_MATH_VERSION') || class_exists('RankMath');
        $seo_enabled = $seo_setting_enabled && ($yoast_installed || $rankmath_installed);

        // Handle both response formats: {descriptions: [...]} and {listings: [{description, seo_title, ...}, ...]}
        if ($seo_enabled && isset($ai_response['listings'])) {
            // SEO format - return array of objects with description + SEO fields
            $listings = $ai_response['listings'];
            if (count($listings) !== $batch_size) {
                return new WP_Error('openai_count_mismatch', 'AI returned ' . count($listings) . ' listings, but ' . $batch_size . ' were expected.');
            }
            return $listings;
        } elseif (isset($ai_response['descriptions'])) {
            // Standard format - convert to consistent structure
            $descriptions = $ai_response['descriptions'];
            if (count($descriptions) !== $batch_size) {
                return new WP_Error('openai_count_mismatch', 'AI returned ' . count($descriptions) . ' descriptions, but ' . $batch_size . ' were expected.');
            }
            // Convert to object format for consistency
            return array_map(function($desc) {
                return ['description' => $desc];
            }, $descriptions);
        } else {
            return new WP_Error('openai_invalid_format', 'Invalid AI response format. Expected "descriptions" or "listings" key. AI returned: ' . $ai_content);
        }
    }
    
    private function get_description_cache_key($place_data) { 
        $name = sanitize_title($place_data['name'] ?? ''); 
        $address = sanitize_title($place_data['address'] ?? ''); 
        $lang = get_option('lds_description_language', 'site-default'); 
        return 'lds_desc_' . md5($name . '_' . $address . '_' . $lang); 
    }
    
    private function process_data_locally($place_data, $description, $seo_data = []) {
        $opening_hours = $place_data['opening_hours'] ?? [];

        // Detect if hours are already in final format or need processing
        // Both Google and Outscraper now use Google's periods format: [['open' => ['day' => 1, 'time' => '0900'], 'close' => [...]], ...]
        // Legacy format (if exists): ['monday' => ['opening' => '2 PM', 'closing' => '10 PM'], ...]
        $is_already_processed = false;
        if (!empty($opening_hours) && is_array($opening_hours)) {
            // Check if first element has day names as keys (legacy format)
            $first_key = array_key_first($opening_hours);
            if (is_string($first_key) && in_array($first_key, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])) {
                $is_already_processed = true;
                lds_log("Opening hours already in final format (legacy)", 'OPENING_HOURS_DETECT');
            }
        }

        $processed_hours = $is_already_processed ? $opening_hours : $this->process_opening_hours_locally($opening_hours);

        $listing_data = [
            'name' => $place_data['name'],
            'place_id' => $place_data['place_id'],
            'address' => $place_data['address'],
            'lat' => $place_data['lat'],
            'lng' => $place_data['lng'],
            'website' => $place_data['website'],
            'phone_number' => $place_data['phone_number'],
            'rating' => $place_data['rating'],
            'opening_hours' => $processed_hours,
            'photo_urls' => $place_data['photo_urls'] ?? [], // Use empty array if photos disabled
            'description' => $description,
            'reviews' => $place_data['reviews'] ?? [], // NEW: Include reviews
            'user_ratings_total' => $place_data['user_ratings_total'] ?? 0 // NEW: Include total ratings
        ];

        // Add Yoast SEO data if available
        if (!empty($seo_data)) {
            $listing_data['seo_title'] = $seo_data['seo_title'] ?? '';
            $listing_data['seo_description'] = $seo_data['seo_description'] ?? '';
            $listing_data['focus_keyphrase'] = $seo_data['focus_keyphrase'] ?? '';
        }

        return $listing_data;
    }
    
    private function process_opening_hours_locally($opening_hours_periods) {
        if (empty($opening_hours_periods) || !is_array($opening_hours_periods)) {
            return [];
        }

        lds_log("Processing opening hours from periods array (language-agnostic)", 'OPENING_HOURS_PARSE');

        // Google uses numeric days: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
        $day_number_to_name = [
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday'
        ];

        // Initialize all days as empty arrays to collect periods
        $processed_hours = [];

        // Process each period from Google
        foreach ($opening_hours_periods as $period) {
            if (!isset($period['open']) || !isset($period['close'])) {
                lds_log("Skipping invalid period (missing open or close): " . print_r($period, true), 'OPENING_HOURS_INVALID');
                continue;
            }

            $open_day = $period['open']['day'] ?? null;
            $open_time = $period['open']['time'] ?? null;
            $close_day = $period['close']['day'] ?? null;
            $close_time = $period['close']['time'] ?? null;

            if ($open_day === null || $open_time === null || $close_day === null || $close_time === null) {
                lds_log("Skipping period with missing data", 'OPENING_HOURS_INVALID');
                continue;
            }

            // Convert numeric day to day name
            $day_name = $day_number_to_name[$open_day] ?? null;

            if (!$day_name) {
                lds_log("Invalid day number: {$open_day}", 'OPENING_HOURS_INVALID');
                continue;
            }

            // Convert time from '0700' to '07:00' format
            $opening_formatted = $this->format_time_from_google($open_time);
            $closing_formatted = $this->format_time_from_google($close_time);

            lds_log("Parsed period: {$day_name} from {$opening_formatted} to {$closing_formatted}", 'OPENING_HOURS_SUCCESS');

            // For now, store simple format (first period only - can be enhanced later for split shifts)
            if (!isset($processed_hours[$day_name])) {
                $processed_hours[$day_name] = [
                    'opening' => $opening_formatted,
                    'closing' => $closing_formatted
                ];
            } else {
                // Handle multiple periods per day (split shifts) - store as arrays
                if (!is_array($processed_hours[$day_name]['opening'])) {
                    $processed_hours[$day_name]['opening'] = [$processed_hours[$day_name]['opening']];
                    $processed_hours[$day_name]['closing'] = [$processed_hours[$day_name]['closing']];
                }
                $processed_hours[$day_name]['opening'][] = $opening_formatted;
                $processed_hours[$day_name]['closing'][] = $closing_formatted;

                lds_log("Added additional period for {$day_name} (split shift)", 'OPENING_HOURS_SPLIT');
            }
        }

        // Fill in missing days as closed
        foreach ($day_number_to_name as $day_name) {
            if (!isset($processed_hours[$day_name])) {
                $processed_hours[$day_name] = 'closed';
                lds_log("Day {$day_name} marked as closed (not in periods)", 'OPENING_HOURS_CLOSED');
            }
        }

        lds_log("Final processed hours: " . print_r($processed_hours, true), 'OPENING_HOURS_FINAL');
        return $processed_hours;
    }

    /**
     * Format Google time from '0700' to '07:00'
     */
    private function format_time_from_google($time_string) {
        // Google provides time as '0700', '1430', etc.
        $time_string = str_pad($time_string, 4, '0', STR_PAD_LEFT); // Ensure 4 digits
        $hours = substr($time_string, 0, 2);
        $minutes = substr($time_string, 2, 2);

        return sprintf('%02d:%02d', intval($hours), intval($minutes));
    }
    
    /**
     * Ensure time is always in 24-hour format for Listeo
     * Handles both 12-hour (with AM/PM) and 24-hour formats
     */
    private function ensure_24h_format($hour, $minute, $ampm = null) { 
        $hour = intval($hour); 
        $minute = intval($minute); 
        
        // If AM/PM is provided, convert from 12-hour to 24-hour
        if ($ampm) { 
            if (strtoupper($ampm) === 'PM' && $hour !== 12) { 
                $hour += 12; 
            } elseif (strtoupper($ampm) === 'AM' && $hour === 12) { 
                $hour = 0; 
            } 
        } else {
            // If no AM/PM provided, validate 24-hour format
            // Handle special cases like midnight represented as 24:00
            if ($hour === 24) {
                $hour = 0; // 24:00 becomes 00:00
            }
            
            // Ensure hour is within valid 24-hour range
            if ($hour > 23) {
                lds_log("Invalid hour value: {$hour}, setting to 23", 'OPENING_HOURS_HOUR_FIX');
                $hour = 23;
            }
        }
        
        // Ensure minute is within valid range
        if ($minute > 59) {
            lds_log("Invalid minute value: {$minute}, setting to 59", 'OPENING_HOURS_MINUTE_FIX');
            $minute = 59;
        }
        
        $formatted_time = sprintf('%02d:%02d', $hour, $minute);
        lds_log("Converted time: {$hour}:{$minute} {$ampm} -> {$formatted_time}", 'OPENING_HOURS_24H_CONVERT');
        
        return $formatted_time; 
    }

    /**
     * Generate an extremely simple, data-only, zero-translation fallback description.
     */
    private function generate_fallback_description($place_data) {
        // Start with the name and address.
        $name = $place_data['name'] ?? '';
        $address = $place_data['address'] ?? '';

        $description = '<p><strong>' . esc_html($name) . '</strong>';
        if ($address) {
            $description .= '<br>' . esc_html($address);
        }
        $description .= '</p>';
        
        // Create an array to hold the list items.
        $details_list = [];

        // 1. Add Rating (data only)
        if (!empty($place_data['rating']) && $place_data['rating'] > 0) {
            $details_list[] = '<li>⭐ ' . esc_html($place_data['rating']) . '</li>';
        }

        // 2. Add Phone Number (data only)
        if (!empty($place_data['phone_number'])) {
            $details_list[] = '<li>📞 ' . esc_html($place_data['phone_number']) . '</li>';
        }

        // 3. Add Website (data only, as a clickable link)
        if (!empty($place_data['website'])) {
            $details_list[] = '<li>🌐 <a rel="nofollow" href="' . esc_url($place_data['website']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($place_data['website']) . '</a></li>';
        }

        // If we have any details, wrap them in a <ul> tag.
        if (!empty($details_list)) {
            $description .= '<ul>' . implode('', $details_list) . '</ul>';
        }
        
        return $description;
    }

    public function process_single_job() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        $listing_data = stripslashes_deep($_POST['listing_data']);
        $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? array_filter(array_map('absint', $_POST['category_ids'])) : []; // Category multi-select
        $category_taxonomy = isset($_POST['category_taxonomy']) ? sanitize_text_field($_POST['category_taxonomy']) : 'listing_category'; // NEW
        $region_ids = isset($_POST['region_ids']) && is_array($_POST['region_ids']) ? array_map('absint', $_POST['region_ids']) : []; // Region multi-select
        $region_taxonomy = isset($_POST['region_taxonomy']) ? sanitize_text_field($_POST['region_taxonomy']) : 'region'; // NEW - default back to region
        $listing_type = isset($_POST['listing_type']) ? sanitize_text_field($_POST['listing_type']) : 'service'; // NEW
        $feature_ids = isset($_POST['feature_ids']) && is_array($_POST['feature_ids']) ? array_map('absint', $_POST['feature_ids']) : []; // Features multi-select

        // NEW: Get import preferences (all fields enabled by default if not provided)
        $import_prefs = isset($_POST['import_prefs']) ? $_POST['import_prefs'] : array();
        $import_prefs = array(
            'import_phone' => isset($import_prefs['import_phone']) ? (int)$import_prefs['import_phone'] : 1,
            'import_website' => isset($import_prefs['import_website']) ? (int)$import_prefs['import_website'] : 1,
            'import_socials' => isset($import_prefs['import_socials']) ? (int)$import_prefs['import_socials'] : 1,
            'import_hours' => isset($import_prefs['import_hours']) ? (int)$import_prefs['import_hours'] : 1,
            'import_place_id' => isset($import_prefs['import_place_id']) ? (int)$import_prefs['import_place_id'] : 1,
            'import_photos' => isset($import_prefs['import_photos']) ? (int)$import_prefs['import_photos'] : 1
        );

        lds_log("Received job data - Category IDs: " . implode(',', $category_ids) . ", Category Taxonomy: {$category_taxonomy}, Region IDs: " . implode(',', $region_ids) . ", Region Taxonomy: {$region_taxonomy}, Listing Type: {$listing_type}, Feature IDs: " . implode(',', $feature_ids) . ", Import Prefs: " . print_r($import_prefs, true), 'JOB_PROCESSING');

        if (empty($listing_data) || !is_array($listing_data)) {
            wp_send_json_error(['message' => 'Invalid listing data received.']);
        }

        if (empty($category_ids)) {
            wp_send_json_error(['message' => __('Please select at least one category before running the import.', 'listeo-data-scraper')]);
        }

        // Strip website/phone from fallback description if user unchecked those import options
        if (!empty($listing_data['description'])) {
            if (!$import_prefs['import_website']) {
                $listing_data['description'] = preg_replace('/<li>🌐.*?<\/li>/su', '', $listing_data['description']);
            }
            if (!$import_prefs['import_phone']) {
                $listing_data['description'] = preg_replace('/<li>📞.*?<\/li>/su', '', $listing_data['description']);
            }
            // Clean up empty <ul></ul> if all items were stripped
            $listing_data['description'] = preg_replace('/<ul>\s*<\/ul>/s', '', $listing_data['description']);
        }

        $importer = new LDS_Importer();
        $post_id = $importer->create_listing($listing_data, $category_ids, $region_ids, $listing_type, $region_taxonomy, $category_taxonomy, $import_prefs, $feature_ids);
        
        if ($post_id) {
            wp_send_json_success([
                'post_id' => $post_id, 
                'post_title' => get_the_title($post_id)
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Failed to import listing: ' . ($listing_data['name'] ?? 'Unknown')
            ]);
        }
    }
     
    public function debug_ajax_request() { 
        lds_log($_POST, 'AJAX_POST_DATA', 'DEBUG'); 
        lds_log($_REQUEST, 'AJAX_REQUEST_DATA', 'DEBUG'); 
        $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'lds_import_nonce'); 
        lds_log($nonce_check, 'NONCE_CHECK_RESULT', 'DEBUG'); 
        $can_manage = current_user_can('manage_options'); 
        lds_log($can_manage, 'USER_CAN_MANAGE_OPTIONS', 'DEBUG'); 
        lds_log(getallheaders(), 'REQUEST_HEADERS', 'DEBUG'); 
        wp_send_json_success(['debug' => 'AJAX working', 'post_data' => $_POST]); 
    }

    /**
     * Test Google Places API (New) search availability.
     */
    public function test_api_key() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lds_test_api_key_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'listeo-data-scraper')]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'listeo-data-scraper')]);
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API key is required.', 'listeo-data-scraper')]);
        }

        $request_body = [
            'textQuery' => 'barber in New York',
            'languageCode' => 'en',
            'pageSize' => 20,
        ];

        lds_log('Testing Places API (New) Text Search.', 'API_KEY_TEST');

        $response = wp_remote_post('https://places.googleapis.com/v1/places:searchText', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $api_key,
                'X-Goog-FieldMask' => 'places.id',
            ],
            'body' => wp_json_encode($request_body),
        ]);

        if (is_wp_error($response)) {
            lds_log('Places API (New) test failed - WP Error: ' . $response->get_error_message(), 'API_KEY_TEST');
            wp_send_json_error(['message' => sprintf(__('Failed to connect to Google Places API (New): %s', 'listeo-data-scraper'), $response->get_error_message())]);
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        $http_status = wp_remote_retrieve_response_code($response);
        
        lds_log('Places API (New) test response status: ' . $http_status, 'API_KEY_TEST');

        if (!is_array($data)) {
            wp_send_json_error(['message' => __('Invalid response from Google Places API (New).', 'listeo-data-scraper')]);
        }

        if (!empty($data['error'])) {
            wp_send_json_error(['message' => $this->get_places_new_test_error_message($data, $http_status)]);
        }

        if (empty($data['places']) || !is_array($data['places'])) {
            wp_send_json_success(['message' => __('Places API (New) is enabled, but the test query returned no places. Try an import query to confirm results.', 'listeo-data-scraper')]);
        }

        wp_send_json_success(['message' => __('Places API (New) is enabled. Ready for imports.', 'listeo-data-scraper')]);
    }

    private function get_places_new_test_error_message($data, $http_status = null) {
        $error = isset($data['error']) && is_array($data['error']) ? $data['error'] : [];
        $status = isset($error['status']) ? sanitize_text_field($error['status']) : '';
        $message = isset($error['message']) ? sanitize_text_field($error['message']) : __('Unknown Google API error.', 'listeo-data-scraper');

        if ($status === 'PERMISSION_DENIED') {
            if (strpos($message, 'Places API (New)') !== false || strpos($message, 'places.googleapis.com') !== false || strpos($message, 'disabled') !== false) {
                return __('Places API (New) is not enabled for this Google Cloud project. Enable "Places API (New)", wait a few minutes, then test again.', 'listeo-data-scraper');
            }

            return __('Permission denied. Check API key restrictions, billing, and make sure this server IP is allowed for Places API (New).', 'listeo-data-scraper');
        }

        if ($status === 'RESOURCE_EXHAUSTED') {
            return __('Google API quota exceeded. Check Google Cloud billing and usage limits.', 'listeo-data-scraper');
        }

        if ($status === 'INVALID_ARGUMENT') {
            return __('Google marked the Places API (New) test request invalid. Check that Places API (New) is enabled and the key is allowed to use it.', 'listeo-data-scraper');
        }

        if ($status === 'UNAUTHENTICATED' || strpos($message, 'API key not valid') !== false) {
            return __('API key invalid. Check for typos or regenerate the key in Google Cloud Console.', 'listeo-data-scraper');
        }

        $status_label = $status ?: ($http_status ? 'HTTP ' . absint($http_status) : __('unknown status', 'listeo-data-scraper'));
        return sprintf(__('Places API (New) test failed (%1$s): %2$s', 'listeo-data-scraper'), $status_label, $message);
    }

    /**
     * Initialize image regeneration process
     */
    public function run_image_regeneration() {
        // Check nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'lds_image_regeneration_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $method = sanitize_text_field($_POST['method']);
        
        if (!in_array($method, ['refresh_api', 'fallback_placeholder'])) {
            wp_send_json_error(['message' => 'Invalid regeneration method']);
            return;
        }

        // Get all listings that have Google photos
        $args = [
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_gallery_google',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => '_gallery',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        $all_listing_ids = get_posts($args);
        
        // Filter to only include listings that actually have Google photos (not WordPress media library photos)
        $listing_ids = [];
        foreach ($all_listing_ids as $listing_id) {
            $google_gallery = get_post_meta($listing_id, '_gallery_google', true);
            $wp_gallery = get_post_meta($listing_id, '_gallery', true);
            
            // Include if it has _gallery_google OR if _gallery contains googleusercontent URLs
            if (!empty($google_gallery)) {
                $listing_ids[] = $listing_id;
                lds_log("Found listing {$listing_id} with _gallery_google: " . count($google_gallery) . " photos", 'IMAGE_REGENERATION');
            } elseif (!empty($wp_gallery) && is_array($wp_gallery)) {
                // Check if any URLs in the gallery are Google URLs
                foreach ($wp_gallery as $photo_id => $photo_url) {
                    if (strpos($photo_url, 'googleusercontent.com') !== false || strpos($photo_url, 'googleapis.com') !== false) {
                        $listing_ids[] = $listing_id;
                        lds_log("Found listing {$listing_id} with Google URLs in _gallery", 'IMAGE_REGENERATION');
                        break;
                    }
                }
            }
        }

        if (empty($listing_ids)) {
            wp_send_json_error(['message' => 'No listings with Google-hosted images found']);
            return;
        }

        // Create a unique batch ID for this regeneration process
        $batch_id = 'lds_regen_' . time() . '_' . wp_rand(1000, 9999);
        
        // Store batch data in transient
        $batch_data = [
            'listing_ids' => $listing_ids,
            'method' => $method,
            'total' => count($listing_ids),
            'processed' => 0,
            'current_index' => 0,
            'summary' => [
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0
            ]
        ];
        
        set_transient($batch_id, $batch_data, 3600); // Store for 1 hour

        lds_log("Started image regeneration batch {$batch_id} with method {$method} for " . count($listing_ids) . " listings", 'IMAGE_REGENERATION');

        wp_send_json_success([
            'batch_id' => $batch_id,
            'total_listings' => count($listing_ids),
            'method' => $method
        ]);
    }

    /**
     * Process a batch of image regeneration
     */
    public function process_image_regeneration_batch() {
        // Check nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'lds_image_regeneration_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $batch_id = sanitize_text_field($_POST['batch_id']);
        $method = sanitize_text_field($_POST['method']);
        
        // Get batch data
        $batch_data = get_transient($batch_id);
        if (!$batch_data) {
            wp_send_json_error(['message' => 'Batch data not found or expired']);
            return;
        }

        $batch_size = 5; // Process 5 listings at a time
        $current_index = $batch_data['current_index'];
        $listing_ids = $batch_data['listing_ids'];
        $total = $batch_data['total'];
        $processed = $batch_data['processed'];
        $summary = $batch_data['summary'];

        // Process this batch
        for ($i = 0; $i < $batch_size && $current_index < $total; $i++, $current_index++) {
            $listing_id = $listing_ids[$current_index];
            
            try {
                if ($method === 'refresh_api') {
                    $result = $this->regenerate_google_photos($listing_id);
                } else if ($method === 'fallback_placeholder') {
                    $result = $this->remove_google_photos($listing_id);
                }
                
                if ($result === 'updated') {
                    $summary['updated']++;
                } else if ($result === 'skipped') {
                    $summary['skipped']++;
                } else {
                    $summary['errors']++;
                }
                
            } catch (Exception $e) {
                lds_log("Error processing listing {$listing_id}: " . $e->getMessage(), 'IMAGE_REGENERATION_ERROR');
                $summary['errors']++;
            }
            
            $processed++;
        }

        // Update batch data
        $batch_data['current_index'] = $current_index;
        $batch_data['processed'] = $processed;
        $batch_data['summary'] = $summary;
        set_transient($batch_id, $batch_data, 3600);

        $completed = ($current_index >= $total);

        if ($completed) {
            // Clean up transient when completed
            delete_transient($batch_id);
            lds_log("Completed image regeneration batch {$batch_id}. Summary: " . json_encode($summary), 'IMAGE_REGENERATION');
        }

        wp_send_json_success([
            'processed' => $processed,
            'total' => $total,
            'completed' => $completed,
            'summary' => $completed ? $summary : null
        ]);
    }

    /**
     * Regenerate Google photos for a listing with new API key
     */
    private function regenerate_google_photos($listing_id) {
        $google_gallery = get_post_meta($listing_id, '_gallery_google', true);
        $wp_gallery = get_post_meta($listing_id, '_gallery', true);
        
        // Check if this listing has Google photos to regenerate
        $has_google_photos = false;
        $current_photo_count = 0;
        
        if (!empty($google_gallery) && is_array($google_gallery)) {
            $has_google_photos = true;
            $current_photo_count = count($google_gallery);
            lds_log("Listing {$listing_id} has _gallery_google with {$current_photo_count} photos", 'IMAGE_REGENERATION');
        } elseif (!empty($wp_gallery) && is_array($wp_gallery)) {
            // Check if the _gallery contains Google URLs
            foreach ($wp_gallery as $photo_id => $photo_url) {
                if (strpos($photo_url, 'googleusercontent.com') !== false || strpos($photo_url, 'googleapis.com') !== false) {
                    $has_google_photos = true;
                    $current_photo_count = count($wp_gallery);
                    lds_log("Listing {$listing_id} has Google URLs in _gallery with {$current_photo_count} photos", 'IMAGE_REGENERATION');
                    break;
                }
            }
        }
        
        if (!$has_google_photos) {
            lds_log("Listing {$listing_id} has no Google photos to regenerate", 'IMAGE_REGENERATION');
            return 'skipped';
        }

        $api_key = get_option('lds_google_api_key');
        if (empty($api_key)) {
            lds_log("No Google API key found for regenerating photos", 'IMAGE_REGENERATION_ERROR');
            return 'error';
        }

        // Get place ID to regenerate photos
        $place_id = get_post_meta($listing_id, '_place_id', true);
        if (empty($place_id)) {
            lds_log("No place ID found for listing {$listing_id}", 'IMAGE_REGENERATION_ERROR');
            return 'error';
        }

        try {
            // Get language setting
            $lang_setting = get_option('lds_description_language', 'site-default');
            $language = ($lang_setting === 'site-default') ? get_locale() : $lang_setting;
            
            // Initialize Google API
            $google_api = new LDS_Google_API($api_key, $language);
            
            // Use current photo count as limit, but minimum of 1
            $photo_limit = max(1, $current_photo_count);
            
            lds_log("Fetching fresh photos for place ID {$place_id}, photo limit: {$photo_limit}", 'IMAGE_REGENERATION');
            
            // Fetch fresh place details with photos
            $place_details = $google_api->get_place_details($place_id, $photo_limit);
            
            if (is_wp_error($place_details)) {
                lds_log("Failed to fetch place details for listing {$listing_id}: " . $place_details->get_error_message(), 'IMAGE_REGENERATION_ERROR');
                return 'error';
            }

            lds_log("Google API returned photo data: " . json_encode($place_details['photo_urls'] ?? []), 'IMAGE_REGENERATION');

            if (!empty($place_details['photo_urls'])) {
                // Remove old Google gallery data (both types)
                delete_post_meta($listing_id, '_gallery_google');
                if (!empty($wp_gallery)) {
                    // Only remove _gallery if it contained Google URLs
                    foreach ($wp_gallery as $photo_id => $photo_url) {
                        if (strpos($photo_url, 'googleusercontent.com') !== false || strpos($photo_url, 'googleapis.com') !== false) {
                            delete_post_meta($listing_id, '_gallery');
                            break;
                        }
                    }
                }
                
                // Create new Google gallery data
                $new_google_gallery = [];
                foreach ($place_details['photo_urls'] as $index => $photo_url) {
                    $new_google_gallery[] = [
                        'url' => $photo_url,
                        'attribution' => isset($place_details['photo_attributions'][$index]) ? $place_details['photo_attributions'][$index] : []
                    ];
                }
                
                // Store in the proper _gallery_google meta
                update_post_meta($listing_id, '_gallery_google', $new_google_gallery);
                lds_log("Regenerated " . count($new_google_gallery) . " Google photos for listing {$listing_id}. New URLs: " . json_encode(array_column($new_google_gallery, 'url')), 'IMAGE_REGENERATION');
                return 'updated';
            } else {
                lds_log("No photos available for place ID {$place_id} (listing {$listing_id})", 'IMAGE_REGENERATION');
                return 'skipped';
            }
            
        } catch (Exception $e) {
            lds_log("Exception regenerating photos for listing {$listing_id}: " . $e->getMessage(), 'IMAGE_REGENERATION_ERROR');
            return 'error';
        }
    }

    /**
     * Remove Google photos and use theme fallback
     */
    private function remove_google_photos($listing_id) {
        $google_gallery = get_post_meta($listing_id, '_gallery_google', true);
        $wp_gallery = get_post_meta($listing_id, '_gallery', true);
        
        $removed_count = 0;
        
        // Remove _gallery_google if it exists
        if (!empty($google_gallery)) {
            delete_post_meta($listing_id, '_gallery_google');
            $removed_count += count($google_gallery);
            lds_log("Removed _gallery_google with " . count($google_gallery) . " photos from listing {$listing_id}", 'IMAGE_REGENERATION');
        }
        
        // Remove _gallery if it contains Google URLs
        if (!empty($wp_gallery) && is_array($wp_gallery)) {
            $has_google_urls = false;
            foreach ($wp_gallery as $photo_id => $photo_url) {
                if (strpos($photo_url, 'googleusercontent.com') !== false || strpos($photo_url, 'googleapis.com') !== false) {
                    $has_google_urls = true;
                    break;
                }
            }
            
            if ($has_google_urls) {
                delete_post_meta($listing_id, '_gallery');
                $removed_count += count($wp_gallery);
                lds_log("Removed _gallery with Google URLs (" . count($wp_gallery) . " photos) from listing {$listing_id}", 'IMAGE_REGENERATION');
            }
        }
        
        // Also remove featured image if it was from Google
        $thumbnail_id = get_post_thumbnail_id($listing_id);
        if ($thumbnail_id) {
            $attachment_url = wp_get_attachment_url($thumbnail_id);
            if (strpos($attachment_url, 'googleapis.com') !== false || strpos($attachment_url, 'googleusercontent.com') !== false) {
                delete_post_thumbnail($listing_id);
                lds_log("Removed Google-hosted featured image from listing {$listing_id}", 'IMAGE_REGENERATION');
            }
        }
        
        if ($removed_count > 0) {
            lds_log("Successfully removed {$removed_count} Google photos from listing {$listing_id}", 'IMAGE_REGENERATION');
            return 'updated';
        } else {
            lds_log("No Google photos found to remove from listing {$listing_id}", 'IMAGE_REGENERATION');
            return 'skipped';
        }
    }

    /**
     * Save current location as default map center
     */
    public function save_default_location() {
        // Verify nonce
        if (!check_ajax_referer('lds_import_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
            return;
        }

        // Get and validate coordinates
        $lat = sanitize_text_field($_POST['lat'] ?? '');
        $lng = sanitize_text_field($_POST['lng'] ?? '');
        $location_name = sanitize_text_field($_POST['location_name'] ?? '');

        if (empty($lat) || empty($lng)) {
            wp_send_json_error(['message' => 'Invalid coordinates provided.']);
            return;
        }

        // Validate coordinate format
        if (!is_numeric($lat) || !is_numeric($lng)) {
            wp_send_json_error(['message' => 'Coordinates must be numeric values.']);
            return;
        }

        // Validate coordinate ranges
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            wp_send_json_error(['message' => 'Coordinates are out of valid range.']);
            return;
        }

        // Save to settings
        update_option('lds_default_map_center_lat', $lat);
        update_option('lds_default_map_center_lng', $lng);

        // Log the update
        lds_log("Default map center updated to: {$location_name} ({$lat}, {$lng})", 'SETTINGS');

        wp_send_json_success([
            'message' => 'Default location saved successfully!',
            'lat' => $lat,
            'lng' => $lng,
            'location_name' => $location_name
        ]);
    }

    /**
     * Get current saved location settings
     */
    public function get_current_location_settings() {
        // Verify nonce
        if (!check_ajax_referer('lds_import_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
            return;
        }

        // Get current saved values
        $lat = get_option('lds_default_map_center_lat', '51.5074');
        $lng = get_option('lds_default_map_center_lng', '-0.1278');

        wp_send_json_success([
            'lat' => $lat,
            'lng' => $lng,
            'message' => 'Current location settings retrieved successfully.'
        ]);
    }

    /**
     * Test selected AI provider API key validity with a simple API call
     */
    public function test_openai_api_key() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lds_test_openai_api_key_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $provider = LDS_AI_Provider::sanitize_provider(
            wp_unslash($_POST['provider'] ?? 'openai')
        );
        $provider_label = LDS_AI_Provider::get_provider_label($provider);
        $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key is required.']);
        }

        if ($provider === LDS_AI_Provider::OPENROUTER) {
            lds_log('Testing OpenRouter API key with current key request', 'OPENAI_API_TEST');

            $response = wp_remote_get('https://openrouter.ai/api/v1/key', [
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                lds_log('OpenRouter API test failed - WP Error: ' . $response->get_error_message(), 'OPENAI_API_TEST');
                wp_send_json_error([
                    'message' => sprintf(
                        __('Failed to connect to %s API: %s', 'listeo-data-scraper'),
                        $provider_label,
                        $response->get_error_message()
                    ),
                ]);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);

            lds_log("OpenRouter API test response code: {$response_code}", 'OPENAI_API_TEST');
            lds_log('OpenRouter API test response: ' . substr($response_body, 0, 500), 'OPENAI_API_TEST');

            if ($response_code === 200) {
                wp_send_json_success([
                    'message' => __('OpenRouter API key is valid and working. Ready for AI descriptions!', 'listeo-data-scraper'),
                ]);
            }

            if ($response_code === 401 || $response_code === 403) {
                wp_send_json_error([
                    'message' => __('Invalid OpenRouter API key. Please check your key for typos or generate a new one.', 'listeo-data-scraper'),
                ]);
            }

            if ($response_code === 429) {
                wp_send_json_error([
                    'message' => __('OpenRouter rate limit exceeded. Your API key may be valid, but OpenRouter is rate limiting requests.', 'listeo-data-scraper'),
                ]);
            }

            $error_msg = $data['error']['message'] ?? $data['message'] ?? __('Unknown error', 'listeo-data-scraper');
            wp_send_json_error([
                'message' => sprintf(
                    __('OpenRouter API test failed (HTTP %1$d): %2$s', 'listeo-data-scraper'),
                    $response_code,
                    $error_msg
                ),
            ]);
        }

        // Test with a simple, low-cost API call
        $payload = [
            "model" => "gpt-4.1-mini", // Use the most cost-effective model for testing
            "messages" => [
                ["role" => "user", "content" => "Hello"]
            ],
            "max_tokens" => 5 // Minimal token usage for testing
        ];

        lds_log('Testing OpenAI API key with minimal request', 'OPENAI_API_TEST');

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'method'    => 'POST',
            'timeout'   => 15,
            'headers'   => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'      => json_encode($payload),
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            lds_log('OpenAI API test failed - WP Error: ' . $response->get_error_message(), 'OPENAI_API_TEST');
            wp_send_json_error(['message' => 'Failed to connect to OpenAI API: ' . $response->get_error_message()]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        lds_log("OpenAI API test response code: {$response_code}", 'OPENAI_API_TEST');
        lds_log('OpenAI API test response: ' . substr($response_body, 0, 500), 'OPENAI_API_TEST');

        if (!$data) {
            wp_send_json_error(['message' => 'Invalid response from OpenAI API']);
        }

        // Handle different response codes
        switch ($response_code) {
            case 200:
                wp_send_json_success(['message' => __('OpenAI API key is valid and working. Ready for AI descriptions!', 'listeo-data-scraper')]);
                break;

            case 401:
                wp_send_json_error(['message' => 'Invalid API key. Please check your OpenAI API key for typos or generate a new one.']);
                break;

            case 402:
                wp_send_json_error(['message' => 'Payment required. Check your OpenAI account billing and add payment method.']);
                break;

            case 403:
                wp_send_json_error(['message' => 'Access forbidden. Your API key may not have the required permissions.']);
                break;

            case 429:
                // Check if it's rate limit or quota exceeded
                if (isset($data['error']['type'])) {
                    if ($data['error']['type'] === 'insufficient_quota') {
                        wp_send_json_error(['message' => 'Quota exceeded. Add credits to your OpenAI account or upgrade your plan.']);
                    } else {
                        wp_send_json_error(['message' => 'Rate limit exceeded. Your API key is working but hitting rate limits.']);
                    }
                } else {
                    wp_send_json_error(['message' => 'Rate limit or quota exceeded. Check your OpenAI usage limits.']);
                }
                break;

            case 500:
            case 502:
            case 503:
                wp_send_json_error(['message' => 'OpenAI server error. Try again in a few moments.']);
                break;

            default:
                $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                wp_send_json_error(['message' => "OpenAI API test failed (HTTP {$response_code}): " . $error_msg]);
                break;
        }
    }

    /**
     * Test Outscraper API key
     */
    public function test_outscraper_api_key() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lds_test_outscraper_api_key_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key is required.']);
        }

        lds_log('Testing Outscraper API key with minimal request', 'OUTSCRAPER_API_TEST');

        // Test with a simple search query (limited to 1 result for minimal cost)
        $params = [
            'query' => 'restaurant',
            'limit' => 1,
            'async' => 'false',
        ];

        $url = add_query_arg($params, 'https://api.outscraper.cloud/google-maps-search');

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'X-API-KEY' => $api_key,
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            lds_log('Outscraper API test failed - WP Error: ' . $response->get_error_message(), 'OUTSCRAPER_API_TEST');
            wp_send_json_error(['message' => 'Failed to connect to Outscraper API: ' . $response->get_error_message()]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        lds_log("Outscraper API test response code: {$response_code}", 'OUTSCRAPER_API_TEST');
        lds_log('Outscraper API test response: ' . substr($response_body, 0, 500), 'OUTSCRAPER_API_TEST');

        // Handle different response codes
        switch ($response_code) {
            case 200:
                if (isset($data['data'])) {
                    wp_send_json_success(['message' => lds_get_inline_svg_icon('check') . " Outscraper API key is valid and working!"]);
                } else {
                    wp_send_json_error(['message' => 'Unexpected response format from Outscraper API.']);
                }
                break;

            case 401:
                wp_send_json_error(['message' => 'Invalid API key. Please check your Outscraper API key for typos or generate a new one.']);
                break;

            case 402:
                wp_send_json_error(['message' => 'Payment required. Your Outscraper account has past due invoices or no payment method connected. Visit https://outscraper.com/ to update billing.']);
                break;

            case 403:
                wp_send_json_error(['message' => 'Access forbidden. Your API key may not have the required permissions.']);
                break;

            case 422:
                $error_msg = isset($data['error']) ? $data['error'] : 'Invalid query parameters';
                wp_send_json_error(['message' => 'Invalid request: ' . $error_msg]);
                break;

            case 429:
                wp_send_json_error(['message' => 'Rate limit exceeded. Your API key is working but hitting rate limits.']);
                break;

            case 500:
            case 502:
            case 503:
                wp_send_json_error(['message' => 'Outscraper server error. Try again in a few moments.']);
                break;

            default:
                $error_msg = isset($data['error']) ? $data['error'] : 'Unknown error';
                wp_send_json_error(['message' => "Outscraper API test failed (HTTP {$response_code}): " . $error_msg]);
                break;
        }
    }

    /**
     * Get the locked system prompt (technical requirements - not editable by users)
     */
    private function get_locked_system_prompt() {
        // Check if SEO plugin integration is enabled (supports Yoast SEO and Rank Math)
        $seo_setting_enabled = get_option('lds_enable_yoast_seo', 0);
        $yoast_installed = defined('WPSEO_VERSION');
        $rankmath_installed = defined('RANK_MATH_VERSION') || class_exists('RankMath');
        $seo_enabled = $seo_setting_enabled && ($yoast_installed || $rankmath_installed);

        $base_prompt = "You are an expert local SEO and marketing copywriter creating content for a premium business directory.

**Source Material You Will Be Given for each business:**
- `name`: The business name.
- `address`: The full address.
- `types`: Google's categories for the business (e.g., 'cafe', 'coffee_shop').
- `rating`: The overall star rating.
- `rating_count`: The total number of reviews.
- `review_snippets`: An array of short, relevant text from up to 3 real customer reviews.

**Technical Requirements (DO NOT MODIFY):**
- Write all descriptions in {{LANGUAGE}}.
- Target approximately {{WORD_LENGTH}} words for each description.
- **CRITICAL OUTPUT FORMAT:** For each business, you must generate a single string of HTML with the following structure:
    - A short, catchy, SEO-friendly headline enclosed in `<h2>` tags.
    - Following the headline, the main description paragraph(s).
    - Within the paragraph, strategically wrap 3-4 important keywords in `<strong>` tags.
    - Example: `<h2>A Coffee Lover's Paradise in Downtown</h2><p><strong>Example Cafe</strong> is a gem in the heart of <strong>Example City</strong>, beloved for its...</p>`";

        if ($seo_enabled) {
            // Enhanced prompt with SEO fields (works for both Yoast SEO and Rank Math)
            $base_prompt .= "

**ADDITIONAL SEO REQUIREMENTS:**
For each business, you must ALSO generate:
- `seo_title`: An optimized SEO title (max 60 characters). Format: \"Business Name - Key Service in City\" or similar. Must include primary keyword.
- `seo_description`: A compelling meta description (max 155 characters). Should entice clicks and include the focus keyphrase naturally.
- `focus_keyphrase`: A strategic 2-4 word keyphrase that the business should rank for. Combine business type with location (e.g., \"italian restaurant Warsaw\", \"coffee shop downtown\").

- **Final JSON Structure:** Return ONLY a valid JSON object:
{\"listings\": [{\"description\": \"<h2>...</h2><p>...</p>\", \"seo_title\": \"Best Coffee Shop in Warsaw | Cafe Name\", \"seo_description\": \"Discover Cafe Name...\", \"focus_keyphrase\": \"coffee shop Warsaw\"}, ...]}
in the exact same order as the input array.";
        } else {
            // Standard prompt without Yoast SEO
            $base_prompt .= "
- **Final JSON Structure:** Return ONLY a valid JSON object: {\"descriptions\": [\"<h2>...</h2><p>...</p>\", \"<h2>...</h2><p>...</p>\"]} in the exact same order as the input array.";
        }

        return $base_prompt;
    }

    /**
     * Get the default editable instructions (user can customize this)
     */
    private function get_default_editable_instructions() {
        return "**Your Writing & Formatting Instructions:**

1.  **Synthesize, Don't Just List:** Weave the information together naturally. Instead of \"They have good coffee,\" write \"Customers consistently rave about the <strong>rich, aromatic coffee</strong>, calling it a must-try.\"

2.  **Incorporate Keywords:** Naturally include the business name, its type (e.g., \"cafe,\" \"restaurant\"), and its city/neighborhood.

3.  **Highlight Social Proof:** If the rating count is over 10, mention it (e.g., \"...trusted by over 500 reviewers\").

4.  **Use Review Content:** Use the `review_snippets` as the primary source for what makes the business special.

5.  **Professional Tone:** The description must be professional, inviting, and trustworthy.

6.  **DO NOT mention \"Google,\" \"reviews,\" or \"data.\"** Do not mention amount of reviews and average rating. Write as if you are a local expert recommending the place.

7.  **Add paragraphs:** Use <p> tags to make description easier to read. Language should be casual, not like AI-generated content, and not clunky.

8.  **Keywords in bold:** Use <strong> tags for the business name, the city, and key services (e.g., \"specialty coffee\", \"artisanal pastries\", \"cozy atmosphere\"). Do not overuse bolding.";
    }

    /**
     * PUBLIC METHODS FOR AI DESCRIPTION REGENERATION
     * These methods are used by LDS_AI_Description_Regeneration class
     */

    /**
     * Generate AI description for a single business
     *
     * @param array $business_data Business data array with keys: name, address, types, rating, rating_count, review_snippets
     * @param string $api_key Optional selected provider API key
     * @return string|WP_Error Generated HTML description or WP_Error on failure
     */
    public function generate_single_ai_description($business_data, $api_key = '') {
        lds_log("Starting AI description generation for: " . ($business_data['name'] ?? 'Unknown'), 'AI_GEN');

        $ai_config = $this->get_selected_ai_config();
        if (!empty($api_key)) {
            $ai_config['api_key'] = $api_key;
        }

        if (empty($ai_config['api_key'])) {
            lds_log($ai_config['label'] . " API key is empty", 'AI_GEN', 'ERROR');
            return new WP_Error(
                'ai_config_error',
                sprintf(
                    __('%s API Key is not set.', 'listeo-data-scraper'),
                    $ai_config['label']
                )
            );
        }

        // Get the selected GPT model from settings
        $selected_model = $this->get_validated_ai_model($ai_config['provider']);

        lds_log("Using provider: {$ai_config['provider']}, model: {$selected_model}", 'AI_GEN');

        // Get language setting
        $lang_setting = get_option('lds_description_language', 'site-default');
        if ($lang_setting === 'site-default') {
            if (class_exists('Locale')) {
                try {
                    $description_language = \Locale::getDisplayLanguage(get_locale(), 'en');
                } catch (\Exception $e) {
                    $description_language = get_locale();
                    lds_log("Locale class error, using fallback: {$description_language}", 'AI_GEN', 'WARNING');
                }
            } else {
                $description_language = get_locale();
            }
        } else {
            $description_language = $lang_setting;
        }

        lds_log("Language setting: {$description_language}", 'AI_GEN');

        // Get description word length setting
        $word_length = get_option('lds_description_word_length', 300);
        lds_log("Word length setting: {$word_length}", 'AI_GEN');

        // Build complete system prompt
        $locked_prompt = $this->get_locked_system_prompt();
        $editable_instructions = get_option('lds_ai_system_prompt');

        // If no custom prompt exists, use default
        if (empty($editable_instructions)) {
            $editable_instructions = $this->get_default_editable_instructions();
            lds_log("Using default AI system prompt", 'AI_GEN');
        } else {
            lds_log("Using custom AI system prompt (length: " . strlen($editable_instructions) . " chars)", 'AI_GEN');
        }

        $system_prompt = $locked_prompt . "\n\n" . $editable_instructions;

        // Replace dynamic placeholders
        $system_prompt = str_replace(
            ['{{LANGUAGE}}', '{{WORD_LENGTH}}'],
            [htmlspecialchars($description_language, ENT_QUOTES, 'UTF-8'), $word_length],
            $system_prompt
        );

        $user_prompt = "Generate an HTML description for this business based on the provided data:\n\n" . json_encode($business_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Build payload with model-specific parameters
        $payload = [
            "model" => $selected_model,
            "messages" => [
                ["role" => "system", "content" => $system_prompt],
                ["role" => "user", "content" => $user_prompt]
            ],
        ];

        if ($ai_config['provider'] === LDS_AI_Provider::OPENAI) {
            $payload["response_format"] = ["type" => "json_object"];
        }

        $this->add_ai_generation_parameters(
            $payload,
            $ai_config['provider'],
            $selected_model
        );

        if (
            $ai_config['provider'] === LDS_AI_Provider::OPENAI &&
            $selected_model === 'gpt-4.1-mini'
        ) {
            $payload["top_p"] = 1.0;
        }

        lds_log("Making {$ai_config['label']} API request...", 'AI_GEN');
        lds_log("Payload model: {$payload['model']}, system prompt length: " . strlen($system_prompt) . ", user prompt length: " . strlen($user_prompt), 'AI_GEN');

        // Make API request
        $response = wp_remote_post($ai_config['endpoint'], [
            'method'    => 'POST',
            'timeout'   => 60,
            'headers'   => $this->get_ai_request_headers(
                $ai_config['provider'],
                $ai_config['api_key']
            ),
            'body'      => json_encode($payload),
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            lds_log($ai_config['label'] . " connection failed: " . $response->get_error_code() . " - " . $response->get_error_message(), 'AI_GEN', 'ERROR');
            return new WP_Error(
                'openai_connection_error',
                sprintf(
                    __('Failed to connect to %s API: %s', 'listeo-data-scraper'),
                    $ai_config['label'],
                    $response->get_error_message()
                )
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        lds_log("{$ai_config['label']} response code: {$response_code}", 'AI_GEN');

        if ($response_code !== 200) {
            $error_message = $response_data['error']['message'] ?? __('Unknown API error', 'listeo-data-scraper');
            $error_type = $response_data['error']['type'] ?? 'unknown';
            $error_code = $response_data['error']['code'] ?? 'unknown';
            lds_log("{$ai_config['label']} API error - HTTP {$response_code}, type: {$error_type}, code: {$error_code}, message: {$error_message}", 'AI_GEN', 'ERROR');
            lds_log("Full error response: " . $response_body, 'AI_GEN_ERROR');
            return new WP_Error('openai_api_error', $ai_config['label'] . ' API Error (HTTP ' . $response_code . '): ' . $error_message);
        }

        $ai_content = $response_data['choices'][0]['message']['content'] ?? null;

        if (!$ai_content) {
            lds_log($ai_config['label'] . " returned empty content in response", 'AI_GEN', 'ERROR');
            lds_log("Full response: " . $response_body, 'AI_GEN_ERROR');
            return new WP_Error(
                'openai_empty_response',
                sprintf(
                    __('Empty response content from %s.', 'listeo-data-scraper'),
                    $ai_config['label']
                )
            );
        }

        lds_log("Received AI content (length: " . strlen($ai_content) . " chars)", 'AI_GEN');

        $ai_response = $this->decode_ai_json_response($ai_content);

        if (!is_array($ai_response)) {
            lds_log("Failed to parse AI response as JSON: " . json_last_error_msg(), 'AI_GEN', 'ERROR');
            lds_log("Raw AI content: " . substr($ai_content, 0, 500) . "...", 'AI_GEN_ERROR');
            return new WP_Error('openai_invalid_json', 'Invalid AI response format.');
        }

        // Extract description - handle multiple response formats
        $description = '';

        // Format 1: {"descriptions": ["...", "..."]}
        if (isset($ai_response['descriptions']) && is_array($ai_response['descriptions']) && !empty($ai_response['descriptions'])) {
            $description = $ai_response['descriptions'][0];
            lds_log("Extracted description from 'descriptions' array", 'AI_GEN');
        }
        // Format 2: {"description": "..."}
        elseif (isset($ai_response['description'])) {
            $description = $ai_response['description'];
            lds_log("Extracted description from 'description' key", 'AI_GEN');
        }
        // Format 3: {"listings": [{"description": "..."}]} (custom prompt format)
        elseif (isset($ai_response['listings']) && is_array($ai_response['listings']) && !empty($ai_response['listings'])) {
            if (isset($ai_response['listings'][0]['description'])) {
                $description = $ai_response['listings'][0]['description'];
                lds_log("Extracted description from 'listings[0].description'", 'AI_GEN');
            }
        }

        if (empty($description)) {
            lds_log("No description found in AI response", 'AI_GEN', 'ERROR');
            lds_log("AI response keys: " . implode(', ', array_keys($ai_response)), 'AI_GEN_ERROR');
            lds_log("Full AI response: " . json_encode($ai_response), 'AI_GEN_ERROR');
            return new WP_Error('openai_no_description', 'No description found in AI response.');
        }

        lds_log("AI description generated successfully (length: " . strlen($description) . " chars)", 'AI_GEN');

        return $description;
    }
}
