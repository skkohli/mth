<?php
/**
 * AI Description Regeneration Class
 *
 * Handles regenerating AI-powered descriptions for existing listings
 * using the selected AI provider. Reuses the AI generation logic from LDS_Ajax_Handler.
 *
 * @since 2.9.3
 */

class LDS_AI_Description_Regeneration {

    /**
     * Reference to AJAX handler for AI generation
     */
    private $ajax_handler;

    /**
     * Constructor
     */
    public function __construct() {
        // Get reference to AJAX handler for reusing AI generation methods
        $this->ajax_handler = new LDS_Ajax_Handler();

        // Register AJAX handlers
        add_action('wp_ajax_lds_get_listings_for_ai_regen', [ $this, 'ajax_get_listings' ]);
        add_action('wp_ajax_lds_process_ai_description_regeneration', [ $this, 'ajax_process_ai_description_regeneration' ]);
    }

    /**
     * AJAX handler to get listings available for AI description regeneration
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

        // Query listings that have a place_id (imported from scraper)
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
            $content = get_post_field('post_content', $listing_id);
            $word_count = str_word_count(wp_strip_all_tags($content));

            $listings[] = [
                'id'          => $listing_id,
                'title'       => get_the_title($listing_id),
                'place_id'    => $place_id,
                'address'     => $address,
                'word_count'  => $word_count,
                'edit_url'    => get_edit_post_link($listing_id),
            ];
        }

        lds_log("Found " . count($listings) . " listings for AI description regeneration (page {$page} of {$max_pages})", 'AI_REGEN');

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
     * AJAX handler to process AI description regeneration for a single listing
     */
    public function ajax_process_ai_description_regeneration() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            lds_log("Permission denied - user cannot manage_options", 'AI_REGEN', 'ERROR');
            wp_send_json_error(['message' => __('Insufficient permissions.', 'listeo-data-scraper')]);
        }

        // Get parameters
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;

        lds_log("Starting AI description regeneration for listing #{$listing_id}", 'AI_REGEN');

        if (empty($listing_id)) {
            lds_log("Invalid listing ID provided: " . ($listing_id ?? 'null'), 'AI_REGEN', 'ERROR');
            wp_send_json_error(['message' => __('Invalid listing ID.', 'listeo-data-scraper')]);
        }

        // Check if AI descriptions are enabled
        $ai_enabled = get_option('lds_enable_ai_descriptions', 0);
        if (!$ai_enabled) {
            lds_log("AI descriptions are disabled in settings", 'AI_REGEN', 'ERROR');
            wp_send_json_error(['message' => __('AI descriptions are not enabled. Please enable them in Settings.', 'listeo-data-scraper')]);
        }

        // Get selected AI provider API key
        $ai_provider = LDS_AI_Provider::get_selected_provider();
        $ai_provider_label = LDS_AI_Provider::get_provider_label($ai_provider);
        $api_key = LDS_AI_Provider::get_api_key($ai_provider);
        if (empty($api_key)) {
            lds_log("{$ai_provider_label} API key is not configured", 'AI_REGEN', 'ERROR');
            wp_send_json_error([
                'message' => sprintf(
                    __('%s API key is not set in Settings.', 'listeo-data-scraper'),
                    $ai_provider_label
                ),
            ]);
        }

        lds_log("Configuration validated - AI enabled, {$ai_provider_label} API key present (length: " . strlen($api_key) . " chars)", 'AI_REGEN');

        // Get the listing data
        lds_log("Fetching listing data for listing #{$listing_id}", 'AI_REGEN');
        $listing_data = $this->get_listing_data_for_ai($listing_id);

        if (is_wp_error($listing_data)) {
            lds_log("Failed to get listing data: " . $listing_data->get_error_code() . " - " . $listing_data->get_error_message(), 'AI_REGEN', 'ERROR');
            wp_send_json_error([
                'message' => $listing_data->get_error_message(),
                'listing_id' => $listing_id,
            ]);
        }

        lds_log("Listing data retrieved successfully", 'AI_REGEN');
        lds_log($listing_data, 'AI_REGEN_DATA');

        // Generate AI description using the AJAX handler's public method
        lds_log("Calling {$ai_provider_label} API to generate description", 'AI_REGEN');
        $description_result = $this->ajax_handler->generate_single_ai_description($listing_data, $api_key);

        if (is_wp_error($description_result)) {
            lds_log("{$ai_provider_label} API call failed: " . $description_result->get_error_code() . " - " . $description_result->get_error_message(), 'AI_REGEN', 'ERROR');
            wp_send_json_error([
                'message' => $description_result->get_error_message(),
                'listing_id' => $listing_id,
            ]);
        }

        lds_log("AI description generated successfully (length: " . strlen($description_result) . " chars, words: " . str_word_count(wp_strip_all_tags($description_result)) . ")", 'AI_REGEN');

        // Update the listing's description
        lds_log("Updating post content for listing #{$listing_id}", 'AI_REGEN');
        $update_result = wp_update_post([
            'ID' => $listing_id,
            'post_content' => $description_result,
        ]);

        if (is_wp_error($update_result)) {
            lds_log("Failed to update post: " . $update_result->get_error_code() . " - " . $update_result->get_error_message(), 'AI_REGEN', 'ERROR');
            wp_send_json_error([
                'message' => $update_result->get_error_message(),
                'listing_id' => $listing_id,
            ]);
        }

        lds_log("Successfully regenerated AI description for listing #{$listing_id}", 'AI_REGEN');

        wp_send_json_success([
            'message'    => __('Successfully regenerated AI description.', 'listeo-data-scraper'),
            'listing_id' => $listing_id,
            'word_count' => str_word_count(wp_strip_all_tags($description_result)),
        ]);
    }

    /**
     * Get listing data formatted for AI generation
     *
     * @param int $listing_id Listing post ID
     * @return array|WP_Error Listing data or WP_Error on failure
     */
    private function get_listing_data_for_ai($listing_id) {
        lds_log("Getting listing data for AI - listing #{$listing_id}", 'AI_REGEN');

        $listing = get_post($listing_id);

        if (!$listing) {
            lds_log("Listing #{$listing_id} not found in database", 'AI_REGEN', 'ERROR');
            return new WP_Error('invalid_listing', __('Invalid listing.', 'listeo-data-scraper'));
        }

        if ($listing->post_type !== 'listing') {
            lds_log("Post #{$listing_id} is not a listing (type: {$listing->post_type})", 'AI_REGEN', 'ERROR');
            return new WP_Error('invalid_listing', __('Invalid listing.', 'listeo-data-scraper'));
        }

        // Get listing meta data
        $name = $listing->post_title;
        $address = get_post_meta($listing_id, '_address', true);
        $place_id = get_post_meta($listing_id, '_place_id', true);
        $rating = get_post_meta($listing_id, 'listeo-avg-rating', true);
        $rating_count = get_post_meta($listing_id, '_user_ratings_total', true);

        lds_log("Listing meta - name: '{$name}', address: '{$address}', place_id: '{$place_id}', rating: '{$rating}'", 'AI_REGEN');

        // Get listing types/categories
        $types = [];
        $categories = wp_get_post_terms($listing_id, 'listing_category', ['fields' => 'names']);
        if (!is_wp_error($categories)) {
            $types = $categories;
            lds_log("Categories found: " . implode(', ', $types), 'AI_REGEN');
        } else {
            lds_log("Failed to get categories: " . $categories->get_error_message(), 'AI_REGEN', 'WARNING');
        }

        // Try to get reviews if stored
        $reviews = [];
        $stored_reviews = get_post_meta($listing_id, '_google_reviews', true);
        if (!empty($stored_reviews) && is_array($stored_reviews)) {
            $reviews = $stored_reviews;
            lds_log("Found " . count($reviews) . " stored Google reviews", 'AI_REGEN');
        } else {
            lds_log("No stored Google reviews found", 'AI_REGEN');
        }

        if (empty($place_id)) {
            lds_log("Listing #{$listing_id} has no Google Place ID", 'AI_REGEN', 'ERROR');
            return new WP_Error('no_place_id', __('Listing does not have a Google Place ID.', 'listeo-data-scraper'));
        }

        // Prepare review snippets (same logic as in AJAX handler)
        $review_snippets = [];
        if (!empty($reviews) && is_array($reviews)) {
            $good_reviews = array_filter($reviews, function($review) {
                return !empty($review['text']) && $review['rating'] >= 4;
            });
            usort($good_reviews, function($a, $b) {
                return $b['rating'] <=> $a['rating'];
            });
            $top_reviews = array_slice($good_reviews, 0, 3);
            foreach ($top_reviews as $review) {
                $review_snippets[] = $review['text'];
            }
            lds_log("Prepared " . count($review_snippets) . " review snippets for AI prompt", 'AI_REGEN');
        }

        // Return in the format expected by generate_single_ai_description()
        $result = [
            'name' => $name ?? 'Unknown Business',
            'address' => $address ?? '',
            'types' => $types ?? [],
            'rating' => floatval($rating) ?? 0,
            'rating_count' => intval($rating_count) ?? 0,
            'review_snippets' => $review_snippets,
        ];

        lds_log("Listing data prepared successfully for AI generation", 'AI_REGEN');

        return $result;
    }
}
