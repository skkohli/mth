<?php
class LDS_Google_API {

    private $api_key;
    private $language;
    private $places_new_unavailable = false;
    const TEXT_SEARCH_URL = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
    const TEXT_SEARCH_NEW_URL = 'https://places.googleapis.com/v1/places:searchText';
    const PLACES_NEW_ENABLE_URL = 'https://console.cloud.google.com/google/maps-apis/api-list';
    const NEARBY_SEARCH_URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
    const DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

    public function __construct($api_key, $language = null) {
        $this->api_key = $api_key;
        $this->language = $this->get_google_language_code($language);
        
        // Log the language being used for debugging
        lds_log("Google API initialized with language: {$this->language}", 'GOOGLE_API_LANGUAGE');
    }

    /**
     * Convert WordPress locale or language setting to Google Places API language code
     * 
     * @param string|null $language Language setting from plugin options
     * @return string Google Places API language code
     */
    private function get_google_language_code($language = null) {
        // If no language specified, get from plugin settings
        if (empty($language)) {
            $lang_setting = get_option('lds_description_language', 'site-default');
            
            // ENHANCED DEBUG - Let's see exactly what's stored
            lds_log("Raw lds_description_language option value: '" . $lang_setting . "' (type: " . gettype($lang_setting) . ")", 'DEBUG_LANGUAGE_RAW');
            
            if ($lang_setting === 'site-default') {
                $language = get_locale();
                lds_log("Using site locale: '{$language}'", 'DEBUG_SITE_LOCALE');
            } else {
                $language = $lang_setting;
                lds_log("Using custom language setting: '{$language}'", 'DEBUG_CUSTOM_LANGUAGE');
            }
        }
        
        lds_log("Language to map: '{$language}'", 'DEBUG_LANGUAGE_TO_MAP');

        // Convert WordPress locale to Google Places API language code
        $language_map = [
            // Major European languages - ADD BOTH FORMATS
            'en_US' => 'en', 'en_GB' => 'en', 'en_CA' => 'en', 'en_AU' => 'en', 'en' => 'en', 'English' => 'en',
            'es_ES' => 'es', 'es_MX' => 'es', 'es_AR' => 'es', 'es_CO' => 'es', 'es' => 'es', 'Spanish' => 'es',
            'fr_FR' => 'fr', 'fr_CA' => 'fr', 'fr_BE' => 'fr', 'fr' => 'fr', 'French' => 'fr',
            'de_DE' => 'de', 'de_AT' => 'de', 'de_CH' => 'de', 'de' => 'de', 'German' => 'de',
            'it_IT' => 'it', 'it_CH' => 'it', 'it' => 'it', 'Italian' => 'it',
            'pt_BR' => 'pt', 'pt_PT' => 'pt', 'pt' => 'pt', 'Portuguese' => 'pt',
            'ru_RU' => 'ru', 'ru_UA' => 'ru', 'ru' => 'ru', 'Russian' => 'ru',
            'nl_NL' => 'nl', 'nl_BE' => 'nl', 'nl' => 'nl', 'Dutch' => 'nl',
            'sv_SE' => 'sv', 'sv' => 'sv', 'Swedish' => 'sv',
            'da_DK' => 'da', 'da' => 'da', 'Danish' => 'da',
            'no' => 'no', 'nb_NO' => 'no', 'nn_NO' => 'no', 'Norwegian' => 'no',
            'fi' => 'fi', 'Finnish' => 'fi',
            'pl_PL' => 'pl', 'pl' => 'pl', 'Polish' => 'pl', // BOTH Polish formats
            'tr_TR' => 'tr', 'tr' => 'tr', 'Turkish' => 'tr',
            'cs_CZ' => 'cs', 'cs' => 'cs', 'Czech' => 'cs',
            'hu_HU' => 'hu', 'hu' => 'hu', 'Hungarian' => 'hu',
            'ro_RO' => 'ro', 'ro' => 'ro', 'Romanian' => 'ro',
            'bg_BG' => 'bg', 'bg' => 'bg', 'Bulgarian' => 'bg',
            'hr' => 'hr', 'Croatian' => 'hr',
            'sk_SK' => 'sk', 'sk' => 'sk', 'Slovak' => 'sk',
            'sl_SI' => 'sl', 'sl' => 'sl', 'Slovenian' => 'sl',
            'et' => 'et', 'Estonian' => 'et',
            'lv' => 'lv', 'Latvian' => 'lv',
            'lt_LT' => 'lt', 'lt' => 'lt', 'Lithuanian' => 'lt',
            'el' => 'el', 'Greek' => 'el',
            'uk' => 'uk', 'Ukrainian' => 'uk',
            
            // Asian languages
            'ja' => 'ja', 'Japanese' => 'ja',
            'zh_CN' => 'zh-CN', 'zh_TW' => 'zh-TW', 'zh_HK' => 'zh-TW', 'zh' => 'zh-CN', 'Chinese' => 'zh-CN',
            'ko_KR' => 'ko', 'ko' => 'ko', 'Korean' => 'ko',
            'hi_IN' => 'hi', 'hi' => 'hi', 'Hindi' => 'hi',
            'th' => 'th', 'Thai' => 'th',
            'vi' => 'vi', 'Vietnamese' => 'vi',
            'id_ID' => 'id', 'id' => 'id', 'Indonesian' => 'id',
            'ms_MY' => 'ms', 'ms' => 'ms', 'Malay' => 'ms',
            'tl' => 'fil', 'fil' => 'fil', 'Filipino' => 'fil',
            
            // Middle Eastern and African languages
            'ar' => 'ar', 'Arabic' => 'ar',
            'he_IL' => 'iw', 'he' => 'iw', 'iw' => 'iw', 'Hebrew' => 'iw',
            'fa_IR' => 'fa', 'fa' => 'fa', 'Persian' => 'fa',
            'ur' => 'ur', 'Urdu' => 'ur',
            
            // Other languages
            'ca' => 'ca', 'Catalan' => 'ca',
            'eu' => 'eu', 'Basque' => 'eu',
            'gl_ES' => 'gl', 'gl' => 'gl', 'Galician' => 'gl',
            'is_IS' => 'is', 'is' => 'is', 'Icelandic' => 'is',
            'mt_MT' => 'mt', 'mt' => 'mt', 'Maltese' => 'mt',
            'cy' => 'cy', 'Welsh' => 'cy',
            'ga' => 'ga', 'Irish' => 'ga',
            'sq' => 'sq', 'Albanian' => 'sq',
            'mk_MK' => 'mk', 'mk' => 'mk', 'Macedonian' => 'mk',
            'sr_RS' => 'sr', 'sr' => 'sr', 'Serbian' => 'sr',
            'bs_BA' => 'bs', 'bs' => 'bs', 'Bosnian' => 'bs',
        ];

        // First, try exact match
        if (isset($language_map[$language])) {
            $mapped_code = $language_map[$language];
            lds_log("EXACT MATCH found: '{$language}' -> '{$mapped_code}'", 'DEBUG_LANGUAGE_EXACT_MATCH');
            return $mapped_code;
        }

        // If no exact match, try to extract language code (e.g., 'en' from 'en_US')
        $lang_code = substr($language, 0, 2);
        lds_log("No exact match. Trying fallback with lang_code: '{$lang_code}'", 'DEBUG_LANGUAGE_FALLBACK');
        
        foreach ($language_map as $locale => $google_code) {
            if (substr($google_code, 0, 2) === $lang_code) {
                lds_log("FALLBACK MATCH found: '{$lang_code}' -> '{$google_code}' (from locale '{$locale}')", 'DEBUG_LANGUAGE_FALLBACK_MATCH');
                return $google_code;
            }
        }

        // Default to English if no match found
        lds_log("NO MATCH found for '{$language}'. Defaulting to 'en'", 'DEBUG_LANGUAGE_DEFAULT');
        return 'en';
    }

    private function build_google_api_error($endpoint, $response_body, $request_context = [], $http_status = null) {
        $status = '';
        $google_message = '';

        if (is_array($response_body)) {
            if (!empty($response_body['error']) && is_array($response_body['error'])) {
                $status = isset($response_body['error']['status']) ? sanitize_text_field($response_body['error']['status']) : '';
                $google_message = isset($response_body['error']['message']) ? sanitize_text_field($response_body['error']['message']) : '';
                $http_status = $http_status ?: ($response_body['error']['code'] ?? null);
            } else {
                $status = isset($response_body['status']) ? sanitize_text_field($response_body['status']) : '';
                $google_message = isset($response_body['error_message']) ? sanitize_text_field($response_body['error_message']) : '';
            }
        }

        $message = $google_message ?: ($status ?: __('Unknown Google API error', 'listeo-data-scraper'));

        return new WP_Error('google_api_error', $message, [
            'provider' => 'google',
            'endpoint' => sanitize_text_field($endpoint),
            'http_status' => $http_status ? absint($http_status) : '',
            'google_status' => $status,
            'google_error_message' => $google_message,
            'request_context' => $request_context,
        ]);
    }

    /**
     * NEW METHOD: Fetches only place IDs from Google (very fast).
     *
     * @param string $query The search query (e.g., "Pet Groomer in Texas").
     * @param int $limit The maximum number of place IDs to return.
     * @return array|WP_Error An array of place IDs or a WP_Error on failure.
     */
    public function fetch_place_ids_only($query, $limit) {
        $all_place_ids = [];
        $next_page_token = null;

        do {
            $search_params = [
                'query' => urlencode($query),
                'key'   => $this->api_key,
                'language' => $this->language, // Add language parameter
            ];

            if ($next_page_token) {
                $search_params['pagetoken'] = $next_page_token;
                sleep(2); // Google requires a delay before using pagetoken
            }

            $search_url = add_query_arg($search_params, self::TEXT_SEARCH_URL);
            $search_response = wp_remote_get($search_url, ['timeout' => 20]);

            if (is_wp_error($search_response)) {
                return new WP_Error('google_api_error', 'Failed to connect to Google Text Search API.');
            }

            $search_body = json_decode(wp_remote_retrieve_body($search_response), true);

            if ($search_body['status'] !== 'OK' && $search_body['status'] !== 'ZERO_RESULTS') {
                return new WP_Error('google_api_error', 'Google API Error: ' . ($search_body['error_message'] ?? $search_body['status']));
            }

            if (empty($search_body['results'])) {
                break; // No more results
            }

            // Extract only place IDs
            foreach ($search_body['results'] as $place) {
                if (count($all_place_ids) >= $limit) {
                    break 2; // Break out of both loops
                }

                if (!empty($place['place_id'])) {
                    $all_place_ids[] = $place['place_id'];
                }
            }

            $next_page_token = $search_body['next_page_token'] ?? null;

        } while ($next_page_token && count($all_place_ids) < $limit);

        return $all_place_ids;
    }

    /**
     * ORIGINAL METHOD: Fetches a list of places from Google, including their details.
     * This is kept for backward compatibility but not used in the new system.
     */
    public function fetch_places($query, $limit, $photo_limit) {
        $all_places_details = [];
        $next_page_token = null;

        do {
            $search_params = [
                'query' => urlencode($query),
                'key'   => $this->api_key,
                'language' => $this->language, // Add language parameter
            ];

            if ($next_page_token) {
                $search_params['pagetoken'] = $next_page_token;
                sleep(2);
            }

            $search_url = add_query_arg($search_params, self::TEXT_SEARCH_URL);
            $search_response = wp_remote_get($search_url, ['timeout' => 20]);

            if (is_wp_error($search_response)) {
                return new WP_Error('google_api_error', 'Failed to connect to Google Text Search API.');
            }

            $search_body = json_decode(wp_remote_retrieve_body($search_response), true);
            
            // Add this after line 95 in class-lds-google-api.php
            lds_log($result['opening_hours'] ?? 'NO OPENING HOURS', 'GOOGLE_OPENING_HOURS_RAW');
            lds_log($result['opening_hours']['weekday_text'] ?? 'NO WEEKDAY_TEXT', 'GOOGLE_WEEKDAY_TEXT');

            if ($search_body['status'] !== 'OK' && $search_body['status'] !== 'ZERO_RESULTS') {
                return new WP_Error('google_api_error', 'Google API Error: ' . ($search_body['error_message'] ?? $search_body['status']));
            }

            if (empty($search_body['results'])) {
                break;
            }

            foreach ($search_body['results'] as $place) {
                if (count($all_places_details) >= $limit) {
                    break 2;
                }

                $details_data = $this->get_place_details($place['place_id'], $photo_limit);
                if ($details_data && !is_wp_error($details_data)) {
                    $all_places_details[] = $details_data;
                }
            }

            

            $next_page_token = $search_body['next_page_token'] ?? null;

        } while ($next_page_token && count($all_places_details) < $limit);

        return $all_places_details;
    }

   /**
 * Fetches a single page of place IDs from Google.
 * Returns the IDs and the token for the next page.
 *
 * @param string $query The search query.
 * @param string|null $pagetoken The token for the next page of results.
 * @return array|WP_Error An array containing 'place_ids' and 'next_page_token', or a WP_Error.
 */
	public function fetch_place_ids_paginated($query, $pagetoken = null, $limit = null) {
	    $token_source = '';
	    if ($pagetoken && strpos($pagetoken, 'new:') === 0) {
	        $token_source = 'new';
	        $pagetoken = substr($pagetoken, 4);
	    } elseif ($pagetoken && strpos($pagetoken, 'legacy:') === 0) {
	        $token_source = 'legacy';
	        $pagetoken = substr($pagetoken, 7);
	    }

	    if (!$pagetoken || $token_source === 'new') {
	        $new_api_response = $this->fetch_place_ids_paginated_new($query, $pagetoken, $limit);
	        if (!is_wp_error($new_api_response)) {
	            return $new_api_response;
	        }

	        if ($pagetoken || !$this->should_fallback_to_legacy_text_search($new_api_response)) {
	            return $new_api_response;
	        }

	        lds_log('Places API (New) is unavailable for this key. Falling back to legacy Text Search.', 'GOOGLE_PLACES_NEW_FALLBACK');
	        $this->places_new_unavailable = true;
	    }

	    // Google API ignores limit parameter (returns ~20 per page automatically)
	    // Parameter added for interface compatibility with Outscraper API
	    if ($pagetoken) {
	        $search_params = [
	            'pagetoken' => $pagetoken,
	            'key' => $this->api_key,
	        ];
	        sleep(2); // Google requires a delay before using a pagetoken.
	    } else {
	        $search_params = [
	            'query' => $query,
	            'key'   => $this->api_key,
	            'language' => $this->language, // Add language parameter
	        ];
	    }

	    $request_context = [
	        'query' => $query,
	        'language' => $this->language,
	        'page_token_request' => !empty($pagetoken),
	    ];
	    if ($this->places_new_unavailable) {
	        $request_context['api_version'] = 'Legacy Text Search';
	        $request_context['places_new_unavailable'] = true;
	        $request_context['places_new_enable_url'] = self::PLACES_NEW_ENABLE_URL;
	    }

	    $max_attempts = $pagetoken ? 4 : 1;
	    $retry_delays = [3, 5, 8];

	    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
	        $search_url = add_query_arg($search_params, self::TEXT_SEARCH_URL);
	        $search_response = wp_remote_get($search_url, ['timeout' => 20]);

	        if (is_wp_error($search_response)) {
	            return new WP_Error('google_api_error', __('Failed to connect to Google Text Search API.', 'listeo-data-scraper'), [
	                'provider' => 'google',
	                'endpoint' => 'Text Search',
	                'request_context' => $request_context,
	            ]);
	        }

	        $http_status = wp_remote_retrieve_response_code($search_response);
	        $search_body = json_decode(wp_remote_retrieve_body($search_response), true);

	        if (!is_array($search_body) || empty($search_body['status'])) {
	            return new WP_Error('google_api_error', __('Invalid response from Google Text Search API.', 'listeo-data-scraper'), [
	                'provider' => 'google',
	                'endpoint' => 'Text Search',
	                'http_status' => $http_status ? absint($http_status) : '',
	                'request_context' => $request_context,
	            ]);
	        }

	        if ($search_body['status'] === 'OK' || $search_body['status'] === 'ZERO_RESULTS') {
	            break;
	        }

	        if ($pagetoken && $search_body['status'] === 'INVALID_REQUEST' && $attempt < $max_attempts) {
	            $delay = $retry_delays[$attempt - 1] ?? 4;
	            lds_log(
	                "Google pagination token not ready on attempt {$attempt}/{$max_attempts}. Retrying in {$delay} seconds.",
	                'GOOGLE_PAGINATION_RETRY'
	            );
	            sleep($delay);
	            continue;
	        }

	        if ($pagetoken && $attempt > 1) {
	            $request_context['pagination_retries'] = $attempt - 1;
	        }

	        return $this->build_google_api_error('Text Search', $search_body, $request_context, $http_status);
	    }

	    $place_ids = [];
    if (!empty($search_body['results'])) {
        // Use wp_list_pluck to safely extract the 'place_id' from each result
        $place_ids = wp_list_pluck($search_body['results'], 'place_id');
    }
    
	    return [
	        'place_ids'       => array_filter($place_ids), // Ensure no empty values are returned
	        'next_page_token' => !empty($search_body['next_page_token']) ? 'legacy:' . $search_body['next_page_token'] : null,
	    ];
}

    private function fetch_place_ids_paginated_new($query, $pagetoken = null, $limit = null) {
        $request_context = [
            'query' => $query,
            'language' => $this->language,
            'page_token_request' => !empty($pagetoken),
            'api_version' => 'Places API (New)',
        ];

        $request_body = [
            'textQuery' => $query,
            'languageCode' => $this->language,
            'pageSize' => 20,
        ];

        if ($pagetoken) {
            $request_body['pageToken'] = $pagetoken;
            sleep(2);
        }

        $search_response = wp_remote_post(self::TEXT_SEARCH_NEW_URL, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->api_key,
                'X-Goog-FieldMask' => 'places.id,nextPageToken',
            ],
            'body' => wp_json_encode($request_body),
        ]);

        if (is_wp_error($search_response)) {
            return new WP_Error('google_api_error', __('Failed to connect to Google Places API (New).', 'listeo-data-scraper'), [
                'provider' => 'google',
                'endpoint' => 'Text Search (New)',
                'request_context' => $request_context,
            ]);
        }

        $http_status = wp_remote_retrieve_response_code($search_response);
        $search_body = json_decode(wp_remote_retrieve_body($search_response), true);

        if (!is_array($search_body)) {
            return new WP_Error('google_api_error', __('Invalid response from Google Places API (New).', 'listeo-data-scraper'), [
                'provider' => 'google',
                'endpoint' => 'Text Search (New)',
                'http_status' => $http_status ? absint($http_status) : '',
                'request_context' => $request_context,
            ]);
        }

        if (!empty($search_body['error'])) {
            return $this->build_google_api_error('Text Search (New)', $search_body, $request_context, $http_status);
        }

        $place_ids = [];
        if (!empty($search_body['places']) && is_array($search_body['places'])) {
            foreach ($search_body['places'] as $place) {
                if (!empty($place['id'])) {
                    $place_ids[] = $place['id'];
                }
            }
        }

        return [
            'place_ids' => array_filter($place_ids),
            'next_page_token' => !empty($search_body['nextPageToken']) ? 'new:' . $search_body['nextPageToken'] : null,
        ];
    }

    private function should_fallback_to_legacy_text_search($api_error) {
        if (!is_wp_error($api_error)) {
            return false;
        }

        $error_data = $api_error->get_error_data();
        if (!is_array($error_data)) {
            return false;
        }

        $google_status = $error_data['google_status'] ?? '';
        $google_message = $error_data['google_error_message'] ?? $api_error->get_error_message();

        return $google_status === 'PERMISSION_DENIED'
            && (
                strpos($google_message, 'Places API (New)') !== false
                || strpos($google_message, 'places.googleapis.com') !== false
                || strpos($google_message, 'disabled') !== false
            );
    }

/**
 * Fetch place IDs by coordinates and radius using Google Places Nearby Search API
 * 
 * @param float $lat Latitude
 * @param float $lng Longitude
 * @param int $radius_meters Search radius in meters (max 50000) - strictly enforced
 * @param string $business_type Type of business to search for
 * @param string|null $pagetoken The token for the next page of results
 * @return array|WP_Error An array containing 'place_ids' and 'next_page_token', or a WP_Error
 */
public function fetch_places_by_coordinates($lat, $lng, $radius_meters, $business_type, $pagetoken = null, $limit = null) {
    // Google API ignores limit parameter (returns ~20 per page automatically)
    // Parameter added for interface compatibility with Outscraper API

    // Ensure radius is within Google's limits (max 50000 meters)
    $radius_meters = min($radius_meters, 50000);

    lds_log("Nearby search: lat={$lat}, lng={$lng}, radius={$radius_meters}m, keyword={$business_type}", 'NEARBY_SEARCH');
    
    $search_params = [
        'location' => "{$lat},{$lng}",
        'radius' => $radius_meters,
        'keyword' => urlencode($business_type), // Use keyword for business type matching
        'key' => $this->api_key,
        'language' => $this->language,
    ];

    // Type mapping removed for broader results - let keyword matching handle it
    // This allows more results while still maintaining strict radius enforcement

    if ($pagetoken) {
        $search_params['pagetoken'] = $pagetoken;
        sleep(2); // Google requires a delay before using a pagetoken
    }

    $search_url = add_query_arg($search_params, self::NEARBY_SEARCH_URL);
    lds_log("Nearby search URL: " . $search_url, 'NEARBY_SEARCH_URL');
    
    $search_response = wp_remote_get($search_url, ['timeout' => 20]);

    if (is_wp_error($search_response)) {
        lds_log("Nearby search failed: " . $search_response->get_error_message(), 'NEARBY_SEARCH_ERROR');
        return new WP_Error('google_api_error', 'Failed to connect to Google Nearby Search API.');
    }

    $search_body = json_decode(wp_remote_retrieve_body($search_response), true);

    if ($search_body['status'] !== 'OK' && $search_body['status'] !== 'ZERO_RESULTS') {
        $error_msg = 'Google Nearby Search API Error: ' . ($search_body['error_message'] ?? $search_body['status']);
        lds_log($error_msg, 'NEARBY_SEARCH_ERROR');
        return new WP_Error('google_api_error', $error_msg);
    }

    $place_ids = [];
    if (!empty($search_body['results'])) {
        $place_ids = wp_list_pluck($search_body['results'], 'place_id');
        lds_log("Found " . count($place_ids) . " places in nearby search", 'NEARBY_SEARCH_RESULTS');
    } else {
        lds_log("No places found in nearby search", 'NEARBY_SEARCH_RESULTS');
    }
    
    return [
        'place_ids'       => array_filter($place_ids),
        'next_page_token' => $search_body['next_page_token'] ?? null,
    ];
}

/**
 * Map business type keywords to Google Places API types for better search results
 * 
 * @param string $business_type The business type search term
 * @return string|null The corresponding Google Places API type, or null if no mapping
 */
private function map_business_type_to_google_type($business_type) {
    $business_type_lower = strtolower($business_type);
    
    $type_mappings = [
        // Food & Dining
        'restaurant' => 'restaurant',
        'restaurants' => 'restaurant', 
        'food' => 'restaurant',
        'cafe' => 'cafe',
        'coffee' => 'cafe',
        'bar' => 'bar',
        'bakery' => 'bakery',
        'meal_takeaway' => 'meal_takeaway',
        'pizza' => 'meal_takeaway',
        
        // Health & Medical
        'doctor' => 'doctor',
        'doctors' => 'doctor',
        'hospital' => 'hospital',
        'pharmacy' => 'pharmacy',
        'dentist' => 'dentist',
        'physiotherapist' => 'physiotherapist',
        'veterinary' => 'veterinary_care',
        
        // Services
        'lawyer' => 'lawyer',
        'plumber' => 'plumber',
        'electrician' => 'electrician',
        'locksmith' => 'locksmith',
        'beauty' => 'beauty_salon',
        'hair' => 'hair_care',
        'gym' => 'gym',
        'bank' => 'bank',
        
        // Automotive
        'gas' => 'gas_station',
        'fuel' => 'gas_station',
        'car_repair' => 'car_repair',
        'garage' => 'car_repair',
        
        // Shopping
        'store' => 'store',
        'shopping' => 'shopping_mall',
        'supermarket' => 'supermarket',
        'grocery' => 'grocery_or_supermarket',
        
        // Accommodation
        'hotel' => 'lodging',
        'accommodation' => 'lodging',
        
        // Other
        'school' => 'school',
        'university' => 'university',
        'church' => 'church',
        'tourist' => 'tourist_attraction',
    ];
    
    foreach ($type_mappings as $keyword => $google_type) {
        if (strpos($business_type_lower, $keyword) !== false) {
            return $google_type;
        }
    }
    
    return null; // No mapping found
}

    /**
     * UPDATED METHOD: Made public so it can be called directly.
     * Fetches detailed information for a single place ID.
     *
     * @param string $place_id The Google Place ID.
     * @param int $photo_limit Number of photos to fetch.
     * @return array|WP_Error Detailed data for the place.
     */
    public function get_place_details($place_id, $photo_limit = 0) {
        $fields = 'name,place_id,formatted_address,geometry,website,international_phone_number,opening_hours,photos,rating,reviews,types,user_ratings_total';    
        $details_params = [
            'place_id' => $place_id,
            'fields'   => $fields,
            'key'      => $this->api_key,
            'language' => $this->language, // Add language parameter for reviews and other text
        ];
    
        $details_url = add_query_arg($details_params, self::DETAILS_URL);
        $details_response = wp_remote_get($details_url, ['timeout' => 20]);
    
        $request_context = [
            'place_id' => $place_id,
            'language' => $this->language,
            'photo_limit' => $photo_limit,
        ];

        if (is_wp_error($details_response)) {
            lds_log('wp_remote_get failed for Place Details API. Error: ' . $details_response->get_error_message(), 'GOOGLE API CONNECTION FAILED');
            return new WP_Error('connection_failed', __('Failed to connect to Google Place Details API', 'listeo-data-scraper'), [
                'provider' => 'google',
                'endpoint' => 'Place Details',
                'request_context' => $request_context,
            ]);
        }
    
        $http_status = wp_remote_retrieve_response_code($details_response);
        $details_body = json_decode(wp_remote_retrieve_body($details_response), true);
        lds_log($details_body, 'Raw Response from Google Place Details API for Place ID: ' . $place_id);
    
        if (!is_array($details_body) || empty($details_body['status'])) {
            return new WP_Error('google_api_error', __('Invalid response from Google Place Details API', 'listeo-data-scraper'), [
                'provider' => 'google',
                'endpoint' => 'Place Details',
                'http_status' => $http_status ? absint($http_status) : '',
                'request_context' => $request_context,
            ]);
        }

        if ($details_body['status'] !== 'OK' || empty($details_body['result'])) {
            lds_log('Google returned status: ' . $details_body['status'] . '. Skipping this place.', 'INVALID GOOGLE PLACE');
            return $this->build_google_api_error('Place Details', $details_body, $request_context, $http_status);
        }
    
        $result = $details_body['result'];
        
        // DEBUG: Log the photos array structure
        if (!empty($result['photos'])) {
            lds_log("Photos array from Google API: " . json_encode($result['photos']), 'GOOGLE_PHOTOS_DEBUG');
            
            // Log each photo individually for better readability
            foreach ($result['photos'] as $idx => $photo_debug) {
                lds_log("Photo {$idx} raw data: " . json_encode($photo_debug), 'GOOGLE_PHOTO_DETAIL');
                lds_log("Photo {$idx} has photo_reference: " . (isset($photo_debug['photo_reference']) ? 'YES' : 'NO'), 'GOOGLE_PHOTO_DETAIL');
                if (isset($photo_debug['photo_reference'])) {
                    lds_log("Photo {$idx} photo_reference value: " . $photo_debug['photo_reference'], 'GOOGLE_PHOTO_DETAIL');
                }
            }
        } else {
            lds_log("No photos found in Google API response", 'GOOGLE_PHOTOS_DEBUG');
        }
    
        // Initialize the photo_urls array
        $photo_urls = [];
        $photo_attributions = []; // NEW: Store attributions

        // Populate the photo_urls array IF photos are requested and exist
        if ($photo_limit > 0 && !empty($result['photos'])) { 
            $photos_to_import = array_slice($result['photos'], 0, $photo_limit);
            
            foreach ($photos_to_import as $index => $photo) {
                $photo_url = null;
                lds_log("Processing photo {$index}: " . json_encode($photo), 'GOOGLE_PHOTO_PROCESSING');
                
                // Method 1: New Places API (v1) - Resource name approach
                if (!empty($photo['name'])) {
                    $photo_name = $photo['name'];
                    
                    // Check if it's a new API resource name format (places/PLACE_ID/photos/PHOTO_ID)
                    if (strpos($photo_name, 'places/') !== false && strpos($photo_name, '/photos/') !== false) {
                        // New Places API format: https://places.googleapis.com/v1/NAME/media
                        $photo_url = "https://places.googleapis.com/v1/{$photo_name}/media?maxWidthPx=1024&key={$this->api_key}";
                        lds_log("Using NEW Places API (v1) method for photo {$index}: {$photo_name}", 'GOOGLE_PHOTO_METHOD');
                    }
                    // Fallback: Try to extract photo reference from old format in name
                    elseif (preg_match('/photos\/(.+)/', $photo_name, $matches)) {
                        $extracted_ref = $matches[1];
                        $photo_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=1024&photoreference={$extracted_ref}&key={$this->api_key}";
                        lds_log("Using extracted reference from name for photo {$index}: {$extracted_ref}", 'GOOGLE_PHOTO_METHOD');
                    }
                }
                
                // Method 2: Traditional photo_reference approach (legacy)
                elseif (!empty($photo['photo_reference'])) {
                    $photo_ref = $photo['photo_reference'];
                    $photo_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=1024&photoreference={$photo_ref}&key={$this->api_key}";
                    lds_log("Using LEGACY photo_reference method for photo {$index}", 'GOOGLE_PHOTO_METHOD');
                    lds_log("Photo reference token: {$photo_ref}", 'GOOGLE_PHOTO_METHOD');
                    lds_log("Constructed URL: {$photo_url}", 'GOOGLE_PHOTO_METHOD');
                }
                
                // Method 3: Direct URL from Google (getUrl field) - ONLY if it's an API URL
                elseif (!empty($photo['getUrl'])) {
                    $potential_url = $photo['getUrl'];
                    // Only accept if it's a proper API URL that will use our key
                    if (strpos($potential_url, 'googleapis.com') !== false && strpos($potential_url, 'key=') !== false) {
                        $photo_url = $potential_url;
                        lds_log("Using getUrl API method for photo {$index}: {$photo_url}", 'GOOGLE_PHOTO_METHOD');
                    } else {
                        lds_log("Rejecting getUrl direct photo URL (not API format): {$potential_url}", 'GOOGLE_PHOTO_REJECTED');
                    }
                }
                
                // Method 4: Direct URL in url field - ONLY if it's an API URL  
                elseif (!empty($photo['url'])) {
                    $potential_url = $photo['url'];
                    // Only accept if it's a proper API URL that will use our key
                    if (strpos($potential_url, 'googleapis.com') !== false && strpos($potential_url, 'key=') !== false) {
                        $photo_url = $potential_url;
                        lds_log("Using url API method for photo {$index}: {$photo_url}", 'GOOGLE_PHOTO_METHOD');
                    } else {
                        lds_log("Rejecting url direct photo URL (not API format): {$potential_url}", 'GOOGLE_PHOTO_REJECTED');
                    }
                }
                
                // If we successfully got a photo URL, add it to our array
                if ($photo_url) {
                    // Check if it's a proper API URL (either legacy or new format)
                    $is_legacy_api = strpos($photo_url, 'maps.googleapis.com/maps/api/place/photo') !== false;
                    $is_new_api = strpos($photo_url, 'places.googleapis.com/v1/') !== false;
                    $is_direct_google = strpos($photo_url, 'googleusercontent.com') !== false;
                    
                    if ($is_legacy_api) {
                        lds_log("Using LEGACY Places API URL: {$photo_url}", 'GOOGLE_PHOTO_FINAL');
                    } elseif ($is_new_api) {
                        lds_log("Using NEW Places API (v1) URL: {$photo_url}", 'GOOGLE_PHOTO_FINAL');
                    } elseif ($is_direct_google) {
                        lds_log("Found direct Google Photos URL (may not count towards API quota): {$photo_url}", 'GOOGLE_PHOTO_DIRECT');
                    } else {
                        lds_log("Using other photo URL format: {$photo_url}", 'GOOGLE_PHOTO_OTHER');
                    }
                    
                    $photo_urls[] = $photo_url;
                } else {
                    lds_log("Could not extract photo URL from photo data: " . json_encode($photo), 'GOOGLE_PHOTO_ERROR');
                }
                
                // Store attribution if available
                if (!empty($photo['html_attributions'])) {
                    $photo_attributions[] = $photo['html_attributions'];
                } else {
                    $photo_attributions[] = []; // Empty array if no attribution
                }
            }
            
            lds_log("Total photos processed: " . count($photo_urls), 'GOOGLE_PHOTO_SUMMARY');
        }        // Build the complete data array
        $final_data = [
            'name'           => $result['name'] ?? 'Unknown Place',
            'place_id'       => $result['place_id'] ?? $place_id,
            'address'        => $result['formatted_address'] ?? 'No Address Provided',
            'lat'            => $result['geometry']['location']['lat'] ?? 0,
            'lng'            => $result['geometry']['location']['lng'] ?? 0,
            'website'        => $result['website'] ?? '',
            'phone_number'   => $result['international_phone_number'] ?? '',
            'rating'         => $result['rating'] ?? 0,
            'opening_hours'  => $result['opening_hours']['periods'] ?? [],
            'photo_urls'     => $photo_urls,
            'photo_attributions' => $photo_attributions, // NEW
            'reviews'        => $result['reviews'] ?? [],
            'types'          => $result['types'] ?? [],
            'user_ratings_total' => $result['user_ratings_total'] ?? 0,
        ];
    
        lds_log($final_data, 'Final Parsed Data from Google Details API');
    
        return $final_data;
    }
}
