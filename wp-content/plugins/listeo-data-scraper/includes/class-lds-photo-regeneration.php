<?php
/**
 * Photo Regeneration Class
 *
 * Handles re-fetching and updating gallery photos for existing listings
 * using Outscraper's Google Maps Photos API.
 *
 * @since 2.9.3
 */

class LDS_Photo_Regeneration {

    /**
     * Outscraper Google Maps Photos API endpoint
     */
    const PHOTOS_API_URL = 'https://api.outscraper.cloud/google-maps-photos';

    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_lds_get_listings_for_photo_regen', [ $this, 'ajax_get_listings' ]);
        add_action('wp_ajax_lds_process_photo_regeneration', [ $this, 'ajax_process_photo_regeneration' ]);
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

            lds_log("Upscaled photo URL: {$original_width}x{$original_height} → {$new_width}x{$new_height}", 'PHOTO_REGEN_UPSCALE');

            return $new_photo_url;
        }

        // If no match found, return original URL
        lds_log("Photo URL format not recognized for upscaling, using original", 'PHOTO_REGEN_UPSCALE');
        return $photo_url;
    }

    /**
     * AJAX handler to get listings available for photo regeneration
     */
    public function ajax_get_listings() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'listeo-data-scraper')]);
        }

        // Get pagination parameters
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = 50; // Load 50 listings at a time
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        // Query listings that have a place_id
        $args = [
            'post_type'      => 'listing',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'fields'         => 'ids',
            'post_status'    => 'any',
            'meta_query'     => [
                [
                    'key'     => '_place_id',
                    'compare' => 'EXISTS',
                ]
            ]
        ];

        // Add search query if provided
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $listing_ids = $query->posts;
        $total_listings = $query->found_posts;
        $max_pages = $query->max_num_pages;

        if (empty($listing_ids)) {
            wp_send_json_error(['message' => __('No listings found with Google Place IDs.', 'listeo-data-scraper')]);
        }

        // Build listing data for frontend
        $listings = [];
        foreach ($listing_ids as $listing_id) {
            $place_id = get_post_meta($listing_id, '_place_id', true);
            $address = get_post_meta($listing_id, '_address', true);
            $gallery = get_post_meta($listing_id, '_gallery', true);
            $google_gallery = get_post_meta($listing_id, '_gallery_google', true);

            // Count photos
            $photo_count = 0;
            if (!empty($gallery) && is_array($gallery)) {
                $photo_count = count($gallery);
            } elseif (!empty($google_gallery) && is_array($google_gallery)) {
                $photo_count = count($google_gallery);
            }

            $listings[] = [
                'id'          => $listing_id,
                'title'       => get_the_title($listing_id),
                'place_id'    => $place_id,
                'address'     => $address,
                'photo_count' => $photo_count,
                'edit_url'    => get_edit_post_link($listing_id),
            ];
        }

        lds_log("Found " . count($listings) . " listings for photo regeneration (page {$page} of {$max_pages})", 'PHOTO_REGEN');

        wp_send_json_success([
            'listings'   => $listings,
            'total'      => $total_listings,
            'page'       => $page,
            'per_page'   => $per_page,
            'max_pages'  => $max_pages,
            'has_more'   => $page < $max_pages,
        ]);
    }

    /**
     * AJAX handler to process photo regeneration for selected listings
     */
    public function ajax_process_photo_regeneration() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'listeo-data-scraper')]);
        }

        // Get parameters
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        $photos_limit = isset($_POST['photos_limit']) ? intval($_POST['photos_limit']) : 10;

        if (empty($listing_id)) {
            wp_send_json_error(['message' => __('Invalid listing ID.', 'listeo-data-scraper')]);
        }

        // Get the listing's place ID
        $place_id = get_post_meta($listing_id, '_place_id', true);

        if (empty($place_id)) {
            wp_send_json_error(['message' => __('Listing does not have a Google Place ID.', 'listeo-data-scraper')]);
        }

        // Get Outscraper API key
        $api_key = get_option('lds_outscraper_api_key');

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('Outscraper API key is not set in Settings.', 'listeo-data-scraper')]);
        }

        // Fetch photos from Outscraper
        $photos_result = $this->fetch_photos_from_outscraper($place_id, $api_key, $photos_limit);

        if (is_wp_error($photos_result)) {
            wp_send_json_error([
                'message' => $photos_result->get_error_message(),
                'listing_id' => $listing_id,
            ]);
        }

        // Update the listing's gallery
        $update_result = $this->update_listing_gallery($listing_id, $photos_result);

        if (is_wp_error($update_result)) {
            wp_send_json_error([
                'message' => $update_result->get_error_message(),
                'listing_id' => $listing_id,
            ]);
        }

        lds_log("Successfully updated photos for listing #{$listing_id}", 'PHOTO_REGEN');

        wp_send_json_success([
            'message'    => sprintf(
                /* translators: %d: number of photos */
                __('Successfully updated %d photos.', 'listeo-data-scraper'),
                $update_result
            ),
            'listing_id' => $listing_id,
            'photo_count' => $update_result,
        ]);
    }

    /**
     * Fetch photos from Outscraper Google Maps Photos API
     *
     * @param string $place_id Google Place ID or query
     * @param string $api_key Outscraper API key
     * @param int $photos_limit Number of photos to fetch
     * @return array|WP_Error Array of photo URLs or WP_Error on failure
     */
    private function fetch_photos_from_outscraper($place_id, $api_key, $photos_limit = 10) {
        lds_log("Fetching photos for place_id: {$place_id}, limit: {$photos_limit}", 'PHOTO_REGEN');

        // ==================================================================================
        // TEMPORARY WORKAROUND (Added: 2025-01-14)
        // ==================================================================================
        // Outscraper's dedicated Photos API endpoint is currently broken/unreliable.
        // We're temporarily using the standard Places API which returns 1 photo per listing.
        //
        // TODO: When Outscraper fixes their Photos API, revert to the original implementation
        // below (uncomment the commented code and remove this temporary section).
        // ==================================================================================

        // Use standard Places API instead of Photos API
        $params = [
            'query' => $place_id,
            'limit' => 1,        // Only return one place result
            'async' => 'false',  // Get synchronous response
        ];

        // Using google-maps-search endpoint instead of google-maps-photos
        $places_api_url = 'https://api.outscraper.cloud/google-maps-search';
        $url = add_query_arg($params, $places_api_url);

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'X-API-KEY' => $api_key,
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            lds_log('Outscraper Places API connection failed: ' . $error_message, 'PHOTO_REGEN_ERROR');
            return new WP_Error('outscraper_connection_error', $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        lds_log("Outscraper Places API response code: {$response_code}", 'PHOTO_REGEN');

        if ($response_code !== 200) {
            $error_msg = "Outscraper API returned error code: {$response_code}";

            // Try to parse error message from response
            $error_data = json_decode($body, true);
            if (!empty($error_data['message'])) {
                $error_msg .= ' - ' . $error_data['message'];
            }

            lds_log($error_msg . ' | Body: ' . $body, 'PHOTO_REGEN_ERROR');
            return new WP_Error('outscraper_api_error', $error_msg);
        }

        $data = json_decode($body, true);

        if (empty($data) || !is_array($data)) {
            lds_log('Invalid response from Outscraper Places API: ' . $body, 'PHOTO_REGEN_ERROR');
            return new WP_Error('invalid_response', __('Invalid response from Outscraper API.', 'listeo-data-scraper'));
        }

        // Extract the single photo from the standard Places API response
        $photos = [];

        // Log full response only in debug mode (expensive operation)
        if (get_option('lds_enable_debug_mode', 0)) {
            lds_log('Outscraper Places API full response: ' . json_encode($data, JSON_PRETTY_PRINT), 'PHOTO_REGEN');
        }

        // The API returns results wrapped in: {id, status, data: [[{place_data}]]}
        // We need to access data['data'][0][0] to get the actual place object
        if (isset($data['data'][0][0]) && is_array($data['data'][0][0])) {
            $place_data = $data['data'][0][0];

            // Log place data structure only in debug mode
            if (get_option('lds_enable_debug_mode', 0)) {
                lds_log('Place data keys: ' . implode(', ', array_keys($place_data)), 'PHOTO_REGEN');
            }

            // Standard Places API returns a single photo in the 'photo' field
            if (!empty($place_data['photo'])) {
                // UPSCALE: Double the photo dimensions for higher quality
                $upscaled_photo = $this->upscale_google_photo_url($place_data['photo']);
                $photos[] = $upscaled_photo;
                lds_log("Found and upscaled 1 photo from Outscraper Places API (temporary workaround)", 'PHOTO_REGEN');
            } else {
                if (get_option('lds_enable_debug_mode', 0)) {
                    lds_log("Photo field is empty or missing. Photo value: " . var_export($place_data['photo'] ?? 'NOT SET', true), 'PHOTO_REGEN');
                }
            }
        } else {
            if (get_option('lds_enable_debug_mode', 0)) {
                lds_log('Data is not in expected format. Data structure: ' . var_export($data, true), 'PHOTO_REGEN');
            }
        }

        if (empty($photos)) {
            lds_log('No photo found in Outscraper Places API response for place_id: ' . $place_id, 'PHOTO_REGEN');
            return new WP_Error('no_photos', __('No photos found for this listing.', 'listeo-data-scraper'));
        }

        return $photos;

        // ==================================================================================
        // ORIGINAL PHOTOS API IMPLEMENTATION (COMMENTED OUT - CURRENTLY BROKEN)
        // ==================================================================================
        // Uncomment this section when Outscraper fixes their Photos API endpoint
        // and remove the temporary workaround code above.
        // ==================================================================================
        /*
        $params = [
            'query' => $place_id,
            'photosLimit' => max(1, min(100, $photos_limit)),
            'async' => 'false',  // Get synchronous response instead of 202 pending
            'limit' => 1,        // Only return one place result
        ];

        $url = add_query_arg($params, self::PHOTOS_API_URL);

        $response = wp_remote_get($url, [
            'timeout' => 20,  // 20 seconds for synchronous API response
            'headers' => [
                'X-API-KEY' => $api_key,
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            lds_log('Outscraper Photos API connection failed: ' . $error_message, 'PHOTO_REGEN_ERROR');
            return new WP_Error('outscraper_connection_error', $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        lds_log("Outscraper Photos API response code: {$response_code}", 'PHOTO_REGEN');

        if ($response_code !== 200) {
            $error_msg = "Outscraper API returned error code: {$response_code}";

            // Try to parse error message from response
            $error_data = json_decode($body, true);
            if (!empty($error_data['message'])) {
                $error_msg .= ' - ' . $error_data['message'];
            }

            lds_log($error_msg . ' | Body: ' . $body, 'PHOTO_REGEN_ERROR');
            return new WP_Error('outscraper_api_error', $error_msg);
        }

        $data = json_decode($body, true);

        if (empty($data) || !is_array($data)) {
            lds_log('Invalid response from Outscraper Photos API: ' . $body, 'PHOTO_REGEN_ERROR');
            return new WP_Error('invalid_response', __('Invalid response from Outscraper API.', 'listeo-data-scraper'));
        }

        // Extract photos from the response
        // Outscraper Photos API returns an array with place data including photos
        $photos = [];

        // The API returns results as an array of place objects
        if (isset($data[0]) && is_array($data[0])) {
            $place_data = $data[0];

            // Check for photos in different possible keys
            if (!empty($place_data['photos'])) {
                $photos = $place_data['photos'];
            } elseif (!empty($place_data['photos_data'])) {
                $photos = $place_data['photos_data'];
            }
        }

        if (empty($photos)) {
            lds_log('No photos found in Outscraper response for place_id: ' . $place_id, 'PHOTO_REGEN');
            return new WP_Error('no_photos', __('No photos found for this listing.', 'listeo-data-scraper'));
        }

        lds_log("Found " . count($photos) . " photos from Outscraper", 'PHOTO_REGEN');

        return $photos;
        */
    }

    /**
     * Update listing gallery with new photos
     *
     * @param int $listing_id Listing post ID
     * @param array $photos Array of photo URLs or photo data
     * @return int|WP_Error Number of photos updated or WP_Error on failure
     */
    private function update_listing_gallery($listing_id, $photos) {
        if (empty($photos) || !is_array($photos)) {
            return new WP_Error('invalid_photos', __('Invalid photos data.', 'listeo-data-scraper'));
        }

        // Get photo storage method from settings
        $storage_method = get_option('lds_photo_storage_method', 'download');

        lds_log("Updating gallery for listing #{$listing_id} with {$storage_method} method", 'PHOTO_REGEN');

        if ($storage_method === 'download') {
            // Download photos to WordPress media library
            return $this->download_photos_to_media_library($listing_id, $photos);
        } else {
            // Store Google-hosted photo URLs
            return $this->store_google_hosted_photos($listing_id, $photos);
        }
    }

    /**
     * Download photos to WordPress media library
     *
     * Uses the same method as the main importer for consistency and reliability.
     *
     * @param int $listing_id Listing post ID
     * @param array $photos Array of photo URLs
     * @return int|WP_Error Number of photos downloaded or WP_Error on failure
     */
    private function download_photos_to_media_library($listing_id, $photos) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $gallery = [];
        $download_count = 0;
        $is_first_image = true;

        // Create SEO-friendly filename slug from listing name
        $listing_title = get_the_title($listing_id);
        $listing_slug = sanitize_title($listing_title);
        if (empty($listing_slug)) {
            $listing_slug = 'listing-' . $listing_id;
        }

        foreach ($photos as $photo) {
            // Get photo URL (handle both string URLs and array with photo data)
            $photo_url = is_array($photo) ? ($photo['url'] ?? $photo['photo_url'] ?? '') : $photo;

            if (empty($photo_url)) {
                continue;
            }

            // UPSCALE: Double the photo dimensions for higher quality
            $photo_url = $this->upscale_google_photo_url($photo_url);

            // Download the image file first and check for errors
            $temp_file = download_url($photo_url, 15);

            if (is_wp_error($temp_file)) {
                lds_log('Failed to download image from URL: ' . $photo_url . ' Error: ' . $temp_file->get_error_message(), 'PHOTO_REGEN_ERROR');
                continue; // Skip this image and continue with the next one
            }

            // Get the file extension from the URL or default to jpg
            $file_ext = pathinfo(parse_url($photo_url, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($file_ext) || !in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $file_ext = 'jpg';
            }

            // SEO-friendly filename: listing-name-1.jpg, listing-name-2.jpg, etc.
            $seo_filename = $listing_slug . '-' . ($download_count + 1) . '.' . $file_ext;

            $image_id = media_handle_sideload([
                'tmp_name' => $temp_file,
                'name' => $seo_filename
            ], $listing_id, $listing_title);

            if (!is_wp_error($image_id)) {
                $gallery[$image_id] = wp_get_attachment_url($image_id);
                $download_count++;
                lds_log("Downloaded photo to media library: attachment_id={$image_id}", 'PHOTO_REGEN');

                // Set first image as featured image
                if ($is_first_image) {
                    set_post_thumbnail($listing_id, $image_id);
                    $is_first_image = false;
                }
            } else {
                lds_log('Image import failed. URL: ' . $photo_url . ' Error: ' . $image_id->get_error_message(), 'PHOTO_REGEN_ERROR');
            }

            // Clean up the temporary file if it still exists
            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }
        }

        if (empty($gallery)) {
            return new WP_Error('download_failed', __('Failed to download any photos.', 'listeo-data-scraper'));
        }

        // Clear old galleries
        delete_post_meta($listing_id, '_gallery_google');

        // Update gallery meta
        update_post_meta($listing_id, '_gallery', $gallery);

        return $download_count;
    }

    /**
     * Store Google-hosted photo URLs
     *
     * @param int $listing_id Listing post ID
     * @param array $photos Array of photo URLs or photo data
     * @return int Number of photos stored
     */
    private function store_google_hosted_photos($listing_id, $photos) {
        $google_gallery = [];

        foreach ($photos as $photo) {
            // Get photo URL (handle both string URLs and array with photo data)
            $photo_url = is_array($photo) ? ($photo['url'] ?? $photo['photo_url'] ?? '') : $photo;

            if (empty($photo_url)) {
                continue;
            }

            // UPSCALE: Double the photo dimensions for higher quality
            $photo_url = $this->upscale_google_photo_url($photo_url);

            $google_gallery[] = [
                'url' => $photo_url,
                'attribution' => is_array($photo) ? ($photo['attribution'] ?? '') : '',
            ];
        }

        if (empty($google_gallery)) {
            return new WP_Error('no_valid_photos', __('No valid photo URLs found.', 'listeo-data-scraper'));
        }

        // Clear old galleries
        delete_post_meta($listing_id, '_gallery');

        // Update Google gallery meta
        update_post_meta($listing_id, '_gallery_google', $google_gallery);

        lds_log("Stored " . count($google_gallery) . " Google-hosted photos for listing #{$listing_id}", 'PHOTO_REGEN');

        return count($google_gallery);
    }
}
