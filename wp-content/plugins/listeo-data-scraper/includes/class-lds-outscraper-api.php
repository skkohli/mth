<?php
/**
 * Outscraper API Integration Class
 *
 * Handles communication with Outscraper API for Google Maps data scraping
 * Implements the same interface as LDS_Google_API for drop-in replacement
 *
 * OPTIMIZED VERSION - Eliminates Redundant API Calls
 *
 * KEY OPTIMIZATION:
 * Unlike Google Places API which requires 2 separate API calls (Text Search + Place Details),
 * Outscraper's /google-maps-search endpoint returns COMPLETE place data in a single call.
 *
 * This class now caches the full place data from search results and reuses it when
 * get_place_details() is called, eliminating redundant API calls.
 *
 * BEFORE OPTIMIZATION:
 * - Import 5 listings = 1 search call + 5 details calls = 6 API calls
 * - Import 10 listings = 1 search call + 10 details calls = 11 API calls
 *
 * AFTER OPTIMIZATION:
 * - Import 5 listings = 1 search call + 0 details calls = 1 API call (83% savings!)
 * - Import 10 listings = 1 search call + 0 details calls = 1 API call (91% savings!)
 *
 * HOW IT WORKS:
 * 1. fetch_place_ids_paginated() caches full place data in WordPress transients (10 min TTL)
 * 2. get_place_details() checks cache first before making API calls
 * 3. Only makes API call if cache misses (shouldn't happen in normal flow)
 *
 * @since 2.7
 * @optimized 2025-10-31
 */

class LDS_Outscraper_API {

    private $api_key;
    private $language;
    const MAPS_SEARCH_URL = 'https://api.outscraper.cloud/google-maps-search';
    const REVIEWS_URL = 'https://api.outscraper.cloud/google-maps-reviews';
    const PLACES_BY_DOMAIN_URL = 'https://api.outscraper.cloud/google-maps-places-by-domain';
    const CACHE_EXPIRY = 600; // 10 minutes cache for place data

    /**
     * Constructor
     *
     * @param string $api_key Outscraper API key
     * @param string|null $language Language code for API requests
     */
    public function __construct($api_key, $language = null) {
        $this->api_key = $api_key;
        $this->language = $this->get_outscraper_language_code($language);

        // Log API initialization (don't log full API key for security)
        $masked_key = substr($api_key, 0, 8) . '...' . substr($api_key, -4);
        lds_log("Outscraper API initialized - Key: {$masked_key}, Language: {$this->language}", 'OUTSCRAPER_API_INIT');
    }

    /**
     * Convert WordPress locale to Outscraper language code
     *
     * @param string|null $language Language setting from plugin options
     * @return string Outscraper language code
     */
    private function get_outscraper_language_code($language = null) {
        if (empty($language)) {
            $lang_setting = get_option('lds_description_language', 'site-default');

            if ($lang_setting === 'site-default') {
                $language = get_locale();
            } else {
                $language = $lang_setting;
            }
        }

        // Outscraper uses similar language codes to Google
        // Map WordPress locales to Outscraper language codes
        $language_map = [
            // Major European languages
            'en_US' => 'en', 'en_GB' => 'en', 'en_CA' => 'en', 'en_AU' => 'en', 'en' => 'en', 'English' => 'en',
            'es_ES' => 'es', 'es_MX' => 'es', 'es_AR' => 'es', 'es' => 'es', 'Spanish' => 'es',
            'fr_FR' => 'fr', 'fr_CA' => 'fr', 'fr' => 'fr', 'French' => 'fr',
            'de_DE' => 'de', 'de_AT' => 'de', 'de' => 'de', 'German' => 'de',
            'it_IT' => 'it', 'it' => 'it', 'Italian' => 'it',
            'pt_BR' => 'pt-BR', 'pt_PT' => 'pt-PT', 'pt' => 'pt-BR', 'Portuguese' => 'pt-BR',
            'ru_RU' => 'ru', 'ru' => 'ru', 'Russian' => 'ru',
            'nl_NL' => 'nl', 'nl' => 'nl', 'Dutch' => 'nl',
            'pl_PL' => 'pl', 'pl' => 'pl', 'Polish' => 'pl',
            'ja' => 'ja', 'Japanese' => 'ja',
            'zh_CN' => 'zh-CN', 'zh_TW' => 'zh-TW', 'zh' => 'zh-CN', 'Chinese' => 'zh-CN',
            'ko_KR' => 'ko', 'ko' => 'ko', 'Korean' => 'ko',
            'ar' => 'ar', 'Arabic' => 'ar',
        ];

        if (isset($language_map[$language])) {
            return $language_map[$language];
        }

        // Fallback: extract language code
        $lang_code = substr($language, 0, 2);
        return $lang_code ?: 'en';
    }

    /**
     * Fetch place IDs from Google Maps search (paginated)
     * Compatible interface with LDS_Google_API
     *
     * @param string $query Search query
     * @param string|null $pagetoken Page token for pagination (not used by Outscraper)
     * @param int $limit Maximum results to fetch (default 100, max 500)
     * @return array|WP_Error Array with place_ids and next_page_token
     */
    public function fetch_place_ids_paginated($query, $pagetoken = null, $limit = 100) {
        lds_log("Outscraper search query: {$query}, limit: {$limit}", 'OUTSCRAPER_SEARCH');

        // Outscraper doesn't use pagetoken - it returns all results at once
        // We respect the pagetoken parameter for interface compatibility but don't use it

        // Cap limit at 500 (Outscraper max) and ensure minimum of 1
        $limit = max(1, min(500, $limit));

        $params = [
            'query' => $query,
            'limit' => $limit, // User's import limit (optimizes API costs)
            'language' => $this->language,
            'async' => 'false', // Get results immediately
        ];

        $url = add_query_arg($params, self::MAPS_SEARCH_URL);

        lds_log("Outscraper API request URL: {$url}", 'OUTSCRAPER_REQUEST');
        lds_log("Outscraper request parameters: " . json_encode($params), 'OUTSCRAPER_REQUEST');

        $response = wp_remote_get($url, [
            'timeout' => 120, // Increased to 2 minutes for Outscraper searches
            'headers' => [
                'X-API-KEY' => $this->api_key,
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            lds_log('Outscraper API connection failed: ' . $error_message, 'OUTSCRAPER_ERROR');

            // Check if it's a timeout error
            if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'timeout') !== false) {
                return new WP_Error('outscraper_timeout',
                    'Outscraper API request timed out after 2 minutes. This usually happens with very broad searches or slow API responses. Try: (1) Using a more specific search query, (2) Searching in a smaller area, or (3) Reducing the import limit.');
            }

            // Check if it's a connection error
            if (strpos($error_message, 'Could not resolve host') !== false || strpos($error_message, 'Failed to connect') !== false) {
                return new WP_Error('outscraper_connection',
                    'Unable to connect to Outscraper API. Please check your internet connection and try again.');
            }

            // Generic error with the actual message
            return new WP_Error('outscraper_api_error', 'Outscraper API Error: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);

        lds_log("Outscraper response code: {$response_code}", 'OUTSCRAPER_RESPONSE');

        // Log response body if debug mode is enabled (full response, no truncation)
        if (get_option('lds_enable_debug_mode', 0)) {
            $body_size = strlen($response_body);
            lds_log("Outscraper response body ({$body_size} bytes): {$response_body}", 'OUTSCRAPER_RESPONSE_BODY');
        }

        // Always log the structure/summary even if debug is off
        if (isset($body['data']) && is_array($body['data'])) {
            $total_results = 0;
            foreach ($body['data'] as $place_group) {
                if (is_array($place_group)) {
                    $total_results += count($place_group);
                }
            }
            lds_log("Outscraper returned {$total_results} results", 'OUTSCRAPER_RESPONSE');
        }

        // Handle error responses
        if ($response_code !== 200) {
            $error_message = $this->parse_error_message($body, $response_code);
            lds_log("Outscraper API error (HTTP {$response_code}): {$error_message}", 'OUTSCRAPER_ERROR');
            return new WP_Error('outscraper_api_error', $error_message);
        }

        // Parse place IDs from response AND cache full place data
        $place_ids = [];
        $cached_count = 0;

        if (isset($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $place_group) {
                if (is_array($place_group)) {
                    foreach ($place_group as $place) {
                        if (isset($place['place_id'])) {
                            $place_id = $place['place_id'];
                            $place_ids[] = $place_id;

                            // OPTIMIZATION: Cache the full place data to avoid redundant API calls
                            // Store raw Outscraper data for later use in get_place_details()
                            $cache_key = 'lds_outscraper_place_' . md5($place_id);
                            set_transient($cache_key, $place, self::CACHE_EXPIRY);
                            $cached_count++;
                        }
                    }
                }
            }
        }

        lds_log("Extracted " . count($place_ids) . " place IDs from Outscraper", 'OUTSCRAPER_PLACE_IDS');
        lds_log("Cached {$cached_count} complete place data objects for 10 minutes (avoiding redundant API calls)", 'OUTSCRAPER_OPTIMIZATION');

        // Log the place IDs if debug mode is enabled
        if (get_option('lds_enable_debug_mode', 0)) {
            lds_log("Place IDs: " . json_encode($place_ids), 'OUTSCRAPER_PLACE_IDS');
        }

        return [
            'place_ids' => $place_ids,
            'next_page_token' => null // Outscraper doesn't use pagination tokens
        ];
    }

    /**
     * Fetch place IDs by coordinates using Nearby Search
     * Compatible interface with LDS_Google_API
     *
     * OPTIMIZED: This method also benefits from caching
     * The search results include complete place data which is cached automatically
     * by fetch_place_ids_paginated()
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param int $radius_meters Search radius in meters
     * @param string $business_type Type of business to search for
     * @param string|null $pagetoken Page token for pagination (not used)
     * @param int $limit Maximum results to fetch (default 100, max 500)
     * @return array|WP_Error Array with place_ids and next_page_token
     */
    public function fetch_places_by_coordinates($lat, $lng, $radius_meters, $business_type, $pagetoken = null, $limit = 100) {
        // Convert radius from meters to kilometers for Outscraper
        $radius_km = round($radius_meters / 1000, 1);

        // Build query with coordinates - Outscraper format: "type @ lat,lng,radius"
        $query = "{$business_type} @ {$lat},{$lng},{$radius_km}km";

        lds_log("Outscraper coordinates search: {$query}, limit: {$limit}", 'OUTSCRAPER_COORDINATES');
        lds_log("Coordinates: lat={$lat}, lng={$lng}, radius={$radius_meters}m ({$radius_km}km), type={$business_type}", 'OUTSCRAPER_COORDINATES');

        // Calls fetch_place_ids_paginated which caches the full place data
        return $this->fetch_place_ids_paginated($query, $pagetoken, $limit);
    }

    /**
     * Get detailed information for a single place
     * Compatible interface with LDS_Google_API
     *
     * OPTIMIZED: Checks cache first to avoid redundant API calls
     * The search endpoint already returns complete data, so we cache it
     * and only make API calls if cache misses
     *
     * OPTIMIZED: Conditional reviews fetching
     * Reviews are only fetched if needed (for AI descriptions or importing)
     * This saves significant API calls when reviews are disabled
     *
     * @param string $place_id Google Place ID
     * @param int $photo_limit Number of photos to fetch
     * @param bool $fetch_reviews Whether to fetch reviews (default: true for backward compatibility)
     * @return array|WP_Error Place details array
     */
    public function get_place_details($place_id, $photo_limit = 0, $fetch_reviews = true) {
        lds_log("Requesting place details for place_id: {$place_id}, photo_limit: {$photo_limit}", 'OUTSCRAPER_DETAILS');

        // OPTIMIZATION: Check cache first (data from previous search call)
        $cache_key = 'lds_outscraper_place_' . md5($place_id);
        $cached_place = get_transient($cache_key);

        if ($cached_place !== false) {
            lds_log("CACHE HIT: Using cached place data (saved API call)", 'OUTSCRAPER_OPTIMIZATION');
            $place = $cached_place;

            // OPTIMIZATION: Only fetch reviews if needed (for AI descriptions or importing)
            $reviews = [];
            if ($fetch_reviews) {
                lds_log("Fetching reviews for AI descriptions/import", 'OUTSCRAPER_REVIEWS');
                $reviews_data = $this->get_place_reviews($place_id, 5);
                if (!is_wp_error($reviews_data)) {
                    $reviews = $reviews_data;
                }
            } else {
                lds_log("SKIPPING reviews fetch (not needed - saves API call)", 'OUTSCRAPER_OPTIMIZATION');
            }

            // Map and return cached data
            return $this->map_outscraper_to_plugin_format($place, $reviews, $photo_limit);
        }

        // CACHE MISS: Make API call (this should rarely happen with optimized flow)
        lds_log("CACHE MISS: Making API call to fetch place details", 'OUTSCRAPER_OPTIMIZATION');

        $params = [
            'query' => $place_id,
            'limit' => 1,
            'language' => $this->language,
            'async' => 'false',
        ];

        $url = add_query_arg($params, self::MAPS_SEARCH_URL);

        lds_log("Outscraper details request URL: {$url}", 'OUTSCRAPER_DETAILS_REQUEST');

        $response = wp_remote_get($url, [
            'timeout' => 120, // Increased to 2 minutes
            'headers' => [
                'X-API-KEY' => $this->api_key,
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            lds_log('Outscraper API connection failed for place details: ' . $error_message, 'OUTSCRAPER_ERROR');

            // Check if it's a timeout error
            if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'timeout') !== false) {
                return new WP_Error('outscraper_timeout',
                    'Outscraper API request timed out while fetching place details. The Outscraper service may be slow. Please try again in a few moments.');
            }

            // Generic error with the actual message
            return new WP_Error('outscraper_api_error', 'Outscraper API Error: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);

        lds_log("Outscraper details response code: {$response_code}", 'OUTSCRAPER_DETAILS_RESPONSE');

        if ($response_code !== 200) {
            $error_message = $this->parse_error_message($body, $response_code);
            lds_log("Outscraper details API error (HTTP {$response_code}): {$error_message}", 'OUTSCRAPER_ERROR');
            return new WP_Error('outscraper_api_error', $error_message);
        }

        // Log response in debug mode (full response, no truncation)
        if (get_option('lds_enable_debug_mode', 0)) {
            $body_size = strlen($response_body);
            lds_log("Outscraper details response ({$body_size} bytes): {$response_body}", 'OUTSCRAPER_DETAILS_RESPONSE');
        }

        // Parse the place data
        if (!isset($body['data'][0][0])) {
            lds_log('No place data found in Outscraper response for place_id: ' . $place_id, 'OUTSCRAPER_ERROR');
            return new WP_Error('outscraper_api_error', 'No place data found for this ID.');
        }

        $place = $body['data'][0][0];

        // Cache the fetched data for future requests
        set_transient($cache_key, $place, self::CACHE_EXPIRY);

        lds_log("Successfully fetched place details from Outscraper API: " . ($place['name'] ?? 'Unknown Name'), 'OUTSCRAPER_DETAILS');

        // OPTIMIZATION: Only fetch reviews if needed (for AI descriptions or importing)
        $reviews = [];
        if ($fetch_reviews) {
            try {
                lds_log("Fetching reviews for AI descriptions/import", 'OUTSCRAPER_REVIEWS');
                $reviews_data = $this->get_place_reviews($place_id, 5); // Get up to 5 reviews
                if (!is_wp_error($reviews_data)) {
                    $reviews = $reviews_data;
                } else {
                    lds_log('Failed to fetch reviews: ' . $reviews_data->get_error_message(), 'OUTSCRAPER_REVIEWS_ERROR');
                    // Continue without reviews - listing will still be imported
                }
            } catch (Exception $e) {
                lds_log('Exception while fetching reviews: ' . $e->getMessage() . ' - Continuing without reviews', 'OUTSCRAPER_REVIEWS_EXCEPTION');
                // Continue without reviews - listing will still be imported
            } catch (Throwable $e) {
                lds_log('Fatal error while fetching reviews: ' . $e->getMessage() . ' - Continuing without reviews', 'OUTSCRAPER_REVIEWS_FATAL');
                // Continue without reviews - listing will still be imported
            }
        } else {
            lds_log("SKIPPING reviews fetch (not needed - saves API call)", 'OUTSCRAPER_OPTIMIZATION');
        }

        // Map Outscraper response to plugin's expected format
        return $this->map_outscraper_to_plugin_format($place, $reviews, $photo_limit);
    }

    /**
     * Get reviews for a place
     *
     * @param string $place_id Google Place ID
     * @param int $reviews_limit Number of reviews to fetch
     * @return array|WP_Error Array of reviews
     */
    private function get_place_reviews($place_id, $reviews_limit = 5) {
        lds_log("Fetching reviews from Outscraper for place_id: {$place_id}, limit: {$reviews_limit}", 'OUTSCRAPER_REVIEWS');

        $params = [
            'query' => $place_id,
            'reviewsLimit' => $reviews_limit,
            'language' => $this->language,
            'async' => 'false',
            'sort' => 'most_relevant',
        ];

        $url = add_query_arg($params, self::REVIEWS_URL);

        lds_log("Outscraper reviews request URL: {$url}", 'OUTSCRAPER_REVIEWS_REQUEST');

        $response = wp_remote_get($url, [
            'timeout' => 15, // 15 seconds - fail fast if slow, listing will still import without reviews
            'headers' => [
                'X-API-KEY' => $this->api_key,
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            lds_log('Outscraper reviews API connection failed: ' . $error_message . ' - Listing will still import without reviews', 'OUTSCRAPER_ERROR');

            // Check if it's a timeout error
            if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'timeout') !== false) {
                return new WP_Error('outscraper_timeout', 'Outscraper reviews request timed out after 15 seconds. Listing will be imported without reviews.');
            }

            return new WP_Error('outscraper_api_error', 'Failed to fetch reviews: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);

        lds_log("Outscraper reviews response code: {$response_code}", 'OUTSCRAPER_REVIEWS_RESPONSE');

        if ($response_code !== 200) {
            lds_log("Outscraper reviews API error (HTTP {$response_code})", 'OUTSCRAPER_ERROR');
            return new WP_Error('outscraper_api_error', 'Failed to fetch reviews.');
        }

        // Log response in debug mode (full response, no truncation)
        if (get_option('lds_enable_debug_mode', 0)) {
            $body_size = strlen($response_body);
            lds_log("Outscraper reviews response ({$body_size} bytes): {$response_body}", 'OUTSCRAPER_REVIEWS_RESPONSE');
        }

        // Extract reviews from response
        $reviews_data = [];
        if (isset($body['data'][0]['reviews_data']) && is_array($body['data'][0]['reviews_data'])) {
            foreach ($body['data'][0]['reviews_data'] as $review) {
                $reviews_data[] = [
                    'rating' => $review['review_rating'] ?? 0,
                    'text' => $review['review_text'] ?? '',
                    'author_name' => $review['author_title'] ?? 'Anonymous',
                    'time' => $review['review_datetime_utc'] ?? '',
                    'profile_photo_url' => $review['author_image'] ?? '',
                    'author_url' => $review['author_link'] ?? '',
                    'relative_time_description' => $this->format_relative_time($review['review_datetime_utc'] ?? ''),
                ];
            }
        }

        lds_log("Fetched " . count($reviews_data) . " reviews from Outscraper", 'OUTSCRAPER_REVIEWS');

        return $reviews_data;
    }

    /**
     * Upscale Google Photos URL dimensions
     *
     * Doubles the width and height parameters in Google Photos URLs
     * to get higher quality images from Outscraper.
     *
     * Example: ...w800-h500-k-no → ...w1600-h1000-k-no
     *
     * @param string $photo_url Original Google Photos URL
     * @return string Upscaled photo URL with doubled dimensions
     */
    private function upscale_google_photo_url($photo_url) {
        // Google Photos URLs have format: ...=wWIDTH-hHEIGHT-k-no
        // We need to find and double both WIDTH and HEIGHT values

        // Pattern to match width and height parameters
        // Matches: w800-h500 or w1200-h900, etc.
        $pattern = '/=w(\d+)-h(\d+)(-[^&]*)?/';

        if (preg_match($pattern, $photo_url, $matches)) {
            $original_width = (int) $matches[1];
            $original_height = (int) $matches[2];
            $suffix = isset($matches[3]) ? $matches[3] : '';

            // Double the dimensions
            $new_width = $original_width * 2;
            $new_height = $original_height * 2;

            // Replace the old dimensions with new ones
            $new_photo_url = preg_replace(
                $pattern,
                '=w' . $new_width . '-h' . $new_height . $suffix,
                $photo_url
            );

            lds_log("Upscaled photo URL: {$original_width}x{$original_height} → {$new_width}x{$new_height}", 'OUTSCRAPER_PHOTO_UPSCALE');

            return $new_photo_url;
        }

        // If no match found, return original URL
        lds_log("Photo URL format not recognized for upscaling, using original", 'OUTSCRAPER_PHOTO_UPSCALE');
        return $photo_url;
    }

    /**
     * Map Outscraper place data to plugin's expected format
     * This ensures compatibility with the existing importer
     *
     * @param array $place Outscraper place data
     * @param array $reviews Reviews data
     * @param int $photo_limit Number of photos to include
     * @return array Formatted place data matching Google API format
     */
    private function map_outscraper_to_plugin_format($place, $reviews = [], $photo_limit = 0) {
        lds_log("Mapping Outscraper data to plugin format for: " . ($place['name'] ?? 'Unknown'), 'OUTSCRAPER_MAPPING');

        // Extract opening hours
        // Try standard working_hours first, then fall back to other_hours
        $opening_hours = [];
        $hours_data = null;

        if (isset($place['working_hours']) && is_array($place['working_hours']) && !empty($place['working_hours'])) {
            $hours_data = $place['working_hours'];
            lds_log("Using standard working_hours field", 'OUTSCRAPER_MAPPING');
        } elseif (isset($place['other_hours']) && is_array($place['other_hours']) && !empty($place['other_hours'])) {
            // other_hours is an array of objects with different service types (delivery, dine-in, pickup, etc.)
            // Try to find the most relevant hours (prefer dine-in, then delivery, then first available)
            $service_priority = ['dine-in', 'takeout', 'delivery', 'pickup'];

            foreach ($place['other_hours'] as $hours_obj) {
                if (!is_array($hours_obj)) continue;

                // Try priority services first
                foreach ($service_priority as $service) {
                    if (isset($hours_obj[$service]) && is_array($hours_obj[$service])) {
                        $hours_data = $hours_obj[$service];
                        lds_log("Using '{$service}' hours from other_hours", 'OUTSCRAPER_MAPPING');
                        break 2;
                    }
                }

                // If no priority match, use first available service type
                if ($hours_data === null) {
                    foreach ($hours_obj as $service_type => $service_hours) {
                        if (is_array($service_hours)) {
                            $hours_data = $service_hours;
                            lds_log("Using '{$service_type}' hours from other_hours", 'OUTSCRAPER_MAPPING');
                            break 2;
                        }
                    }
                }
            }
        }

        if ($hours_data !== null) {
            $opening_hours = $this->parse_outscraper_hours($hours_data);
            lds_log("Parsed " . count($opening_hours) . " opening hours entries", 'OUTSCRAPER_MAPPING');
        } else {
            lds_log("No working hours data found", 'OUTSCRAPER_MAPPING');
        }

        // Extract photos from Outscraper response
        // Outscraper provides photos in two ways:
        // 1. Direct `photo` field - main business photo
        // 2. `photos_data` array - multiple photos (only if requested with higher limits)
        $photo_urls = [];
        $photo_attributions = [];

        // Method 1: Use direct photo field (always available, no extra API cost)
        if (isset($place['photo']) && !empty($place['photo'])) {
            // UPSCALE: Double the photo dimensions for higher quality
            $upscaled_photo = $this->upscale_google_photo_url($place['photo']);
            $photo_urls[] = $upscaled_photo;
            $photo_attributions[] = ['Google Maps']; // Generic attribution for direct photo
            lds_log("Extracted and upscaled main business photo from 'photo' field", 'OUTSCRAPER_MAPPING');
        }

        // Method 2: Use photos_data array if available and photo_limit > 0
        // This is only used if the Google Places API compatibility mode is enabled
        if ($photo_limit > 0 && isset($place['photos_data']) && is_array($place['photos_data'])) {
            $photos_to_import = array_slice($place['photos_data'], 0, $photo_limit);
            foreach ($photos_to_import as $photo) {
                if (isset($photo['photo_url'])) {
                    // UPSCALE: Double the photo dimensions for higher quality
                    $upscaled_photo = $this->upscale_google_photo_url($photo['photo_url']);

                    // Avoid duplicates
                    if (!in_array($upscaled_photo, $photo_urls)) {
                        $photo_urls[] = $upscaled_photo;
                        $photo_attributions[] = isset($photo['photo_author']) ? [$photo['photo_author']] : ['Google Maps'];
                    }
                }
            }
            lds_log("Extracted and upscaled " . count($photo_urls) . " total photos (direct photo + photos_data, limit: {$photo_limit})", 'OUTSCRAPER_MAPPING');
        } elseif (!empty($photo_urls)) {
            lds_log("Using 1 upscaled main business photo (Outscraper direct photo field)", 'OUTSCRAPER_MAPPING');
        }

        // Build the final data array matching Google API format exactly
        $mapped_data = [
            'name' => $place['name'] ?? 'Unknown Place',
            'place_id' => $place['place_id'] ?? '',
            'address' => $place['full_address'] ?? ($place['address'] ?? 'No Address Provided'),
            'lat' => $place['latitude'] ?? 0,
            'lng' => $place['longitude'] ?? 0,
            'website' => $place['website'] ?? $place['site'] ?? '',
            'phone_number' => $place['phone'] ?? '',
            'rating' => $place['rating'] ?? 0,
            'opening_hours' => $opening_hours,
            'photo_urls' => $photo_urls,
            'photo_attributions' => $photo_attributions,
            'reviews' => $reviews,
            'types' => isset($place['type']) ? (is_array($place['type']) ? $place['type'] : [$place['type']]) : [],
            'user_ratings_total' => $place['reviews'] ?? 0,
        ];

        // Log the mapped data in debug mode
        if (get_option('lds_enable_debug_mode', 0)) {
            lds_log("Mapped data structure: " . json_encode($mapped_data, JSON_PRETTY_PRINT), 'OUTSCRAPER_MAPPING');
        }

        lds_log("Successfully mapped Outscraper data: {$mapped_data['name']}, {$mapped_data['address']}", 'OUTSCRAPER_MAPPING');

        return $mapped_data;
    }

    /**
     * Parse Outscraper working hours to plugin format
     *
     * @param array $working_hours Outscraper working hours data
     * @return array Formatted opening hours
     */
    private function parse_outscraper_hours($working_hours) {
        if (get_option('lds_enable_debug_mode', 0)) {
            lds_log("Parsing Outscraper working hours: " . json_encode($working_hours), 'OUTSCRAPER_HOURS');
        }

        /**
         * LANGUAGE-AGNOSTIC APPROACH:
         * Convert Outscraper's language-specific day names to Google's numeric format.
         * Outscraper returns days in consistent order: Monday, Tuesday, ... Sunday (confirmed by testing)
         * We convert to Google's periods format: [['open' => ['day' => 1, 'time' => '0900'], 'close' => [...]]]
         * Where day: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
         * This way, downstream processing is identical for both Google and Outscraper (no language dependencies)
         */

        $periods = [];

        // Map array position to Google's numeric day
        // Outscraper returns Monday-Sunday order (positions 0-6)
        $position_to_google_day = [
            0 => 1, // Monday
            1 => 2, // Tuesday
            2 => 3, // Wednesday
            3 => 4, // Thursday
            4 => 5, // Friday
            5 => 6, // Saturday
            6 => 0, // Sunday
        ];

        $position = 0;
        foreach ($working_hours as $day_name => $hours) {
            // Get Google's numeric day for this position
            $google_day = $position_to_google_day[$position] ?? null;

            if ($google_day === null) {
                // Unexpected: more than 7 days in response
                if (get_option('lds_enable_debug_mode', 0)) {
                    lds_log("Unexpected day at position {$position}: {$day_name}", 'OUTSCRAPER_HOURS_WARNING');
                }
                $position++;
                continue;
            }

            // Check if closed
            if (empty($hours) || !is_string($hours) || $hours === 'Closed' || stripos($hours, 'closed') !== false || stripos($hours, 'Zamknięte') !== false) {
                // Skip closed days (don't add to periods array)
                $position++;
                continue;
            }

            // Normalize unicode spaces (non-breaking space U+202F and others)
            $hours = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]/u', ' ', $hours);

            // Handle both regular hyphen, en-dash, and em-dash
            $hours = str_replace(['–', '—'], '-', $hours);

            // Normalize multiple spaces to single space
            $hours = preg_replace('/\s+/', ' ', $hours);
            $hours = trim($hours);

            // Parse hours like "10:00-18:00", "9:00 AM - 5:00 PM", or "2-10 PM"
            if (strpos($hours, '-') !== false) {
                $parts = explode('-', $hours, 2);
                if (count($parts) === 2) {
                    $opening = trim($parts[0]);
                    $closing = trim($parts[1]);

                    // Handle cases like "2-10 PM" where AM/PM is only on closing time
                    if (preg_match('/(AM|PM)$/i', $closing, $matches)) {
                        $period = $matches[1];
                        if (!preg_match('/(AM|PM)$/i', $opening)) {
                            $opening .= ' ' . $period;
                        }
                    }

                    // Convert to Google's 4-digit time format (e.g., "10:00" -> "1000", "2:30 PM" -> "1430")
                    $opening_time = $this->convert_to_google_time($opening);
                    $closing_time = $this->convert_to_google_time($closing);

                    if ($opening_time !== null && $closing_time !== null) {
                        $periods[] = [
                            'open' => [
                                'day' => $google_day,
                                'time' => $opening_time
                            ],
                            'close' => [
                                'day' => $google_day,
                                'time' => $closing_time
                            ]
                        ];
                    }
                }
            }

            $position++;
        }

        if (get_option('lds_enable_debug_mode', 0)) {
            lds_log("Converted Outscraper hours to Google periods format: " . json_encode($periods), 'OUTSCRAPER_HOURS');
        }

        return $periods;
    }

    /**
     * Convert time string to Google's 4-digit format
     * Handles: "10:00", "2:30 PM", "14:30", etc.
     *
     * @param string $time_str Time string
     * @return string|null 4-digit time like "1000" or null if invalid
     */
    private function convert_to_google_time($time_str) {
        $time_str = trim($time_str);

        // Check for AM/PM
        $is_pm = stripos($time_str, 'PM') !== false;
        $is_am = stripos($time_str, 'AM') !== false;

        // Remove AM/PM
        $time_str = str_ireplace(['AM', 'PM'], '', $time_str);
        $time_str = trim($time_str);

        // Parse H:MM or HH:MM
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time_str, $matches)) {
            $hour = intval($matches[1]);
            $minute = intval($matches[2]);

            // Convert 12-hour to 24-hour if needed
            if ($is_pm && $hour < 12) {
                $hour += 12;
            } elseif ($is_am && $hour == 12) {
                $hour = 0;
            }

            // Validate
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d%02d', $hour, $minute);
            }
        }

        return null;
    }

    /**
     * Parse error message from Outscraper API response
     *
     * @param array $body Response body
     * @param int $response_code HTTP response code
     * @return string User-friendly error message
     */
    private function parse_error_message($body, $response_code) {
        lds_log("Parsing Outscraper error - HTTP Code: {$response_code}", 'OUTSCRAPER_ERROR');

        if (get_option('lds_enable_debug_mode', 0)) {
            lds_log("Error response body: " . json_encode($body), 'OUTSCRAPER_ERROR');
        }

        $error_message = '';

        if ($response_code === 401) {
            $error_message = 'Invalid Outscraper API key. Please check your API key in settings.';
        } elseif ($response_code === 402) {
            $error_message = 'Outscraper account has past due invoices or no payment method connected. Please check your billing at https://outscraper.com/';
        } elseif ($response_code === 422) {
            $error_detail = isset($body['error']) ? $body['error'] : 'Invalid query parameters';
            $error_message = 'Outscraper API Error: ' . $error_detail;
        } elseif (isset($body['error'])) {
            $error_message = 'Outscraper API Error: ' . $body['error'];
        } else {
            $error_message = 'Outscraper API Error (HTTP Code: ' . $response_code . ')';
        }

        lds_log("Final error message: {$error_message}", 'OUTSCRAPER_ERROR');

        return $error_message;
    }

    /**
     * Truncate large strings for debug logging
     * Prevents memory issues when logging huge API responses
     *
     * @param string $string String to truncate
     * @param int $max_length Maximum length (default 5000 chars = ~5KB)
     * @return string Truncated string with indicator if cut off
     */
    private function truncate_for_log($string, $max_length = 5000) {
        if (strlen($string) <= $max_length) {
            return $string;
        }

        $truncated = substr($string, 0, $max_length);
        $remaining = strlen($string) - $max_length;
        return $truncated . "\n... [TRUNCATED: {$remaining} more bytes]";
    }

    /**
     * Clear cached place data for a specific place ID
     * Useful for debugging or forcing fresh API calls
     *
     * @param string $place_id Google Place ID to clear from cache
     * @return bool True if cache was cleared, false otherwise
     */
    public function clear_place_cache($place_id) {
        $cache_key = 'lds_outscraper_place_' . md5($place_id);
        $result = delete_transient($cache_key);

        if ($result) {
            lds_log("Cleared cache for place_id: {$place_id}", 'OUTSCRAPER_CACHE');
        }

        return $result;
    }

    /**
     * Clear all cached Outscraper place data
     * Useful for debugging or after making changes to data structure
     *
     * @return int Number of cache entries cleared
     */
    public function clear_all_place_cache() {
        global $wpdb;

        // WordPress transients are stored in wp_options table
        $pattern = $wpdb->esc_like('_transient_lds_outscraper_place_') . '%';
        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                $wpdb->esc_like('_transient_timeout_lds_outscraper_place_') . '%'
            )
        );

        lds_log("Cleared {$count} Outscraper place cache entries", 'OUTSCRAPER_CACHE');

        return $count;
    }

    /**
     * Convert UTC datetime to relative time description
     * Matches Google's review timestamp display format (e.g., "2 months ago")
     *
     * @param string $utc_datetime Datetime string from Outscraper (format: "MM/DD/YYYY HH:MM:SS")
     * @return string Relative time description (e.g., "2 months ago")
     */
    private function format_relative_time($utc_datetime) {
        if (empty($utc_datetime)) {
            return '';
        }

        // Parse the Outscraper datetime format (MM/DD/YYYY HH:MM:SS)
        $timestamp = strtotime($utc_datetime);

        if ($timestamp === false) {
            return '';
        }

        // Use WordPress's built-in human_time_diff function for localization support
        return human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('ago', 'listeo_core');
    }
}
