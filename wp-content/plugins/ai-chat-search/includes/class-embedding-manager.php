<?php
/**
 * Embedding Manager Class
 *
 * Handles AI embedding generation and management (OpenAI/Gemini)
 *
 * @package Listeo_AI_Search
 * @since 1.0.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Embedding_Manager {

    /**
     * API key (deprecated - use provider instead)
     *
     * @var string
     */
    private $api_key;

    /**
     * AI Provider instance
     *
     * @var Listeo_AI_Provider
     */
    private $provider;

    /**
     * Constructor
     *
     * @param string $api_key Deprecated - API key (kept for backward compatibility)
     */
    public function __construct($api_key = '') {
        // Backward compatibility: if API key is provided, assume OpenAI
        if ($api_key) {
            $this->api_key = $api_key;
            $this->provider = new Listeo_AI_Provider('openai', $api_key);
        } else {
            // Use configured provider from settings
            $this->provider = new Listeo_AI_Provider();
            $this->api_key = $this->provider->get_api_key();
        }
    }
    
    /**
     * Atomically acquire a rate limit slot before making an API call.
     *
     * Uses conditional SQL UPDATE so check + increment happen in one
     * DB operation. No TOCTOU gap, no lost increments under burst traffic.
     *
     * @return bool True if slot acquired (caller may proceed with API call)
     */
    public static function try_acquire_rate_limit() {
        global $wpdb;
        $rate_limit_key = 'listeo_ai_rate_limit_' . date('Y-m-d-H');
        $max_calls = (int) get_option('listeo_ai_search_rate_limit_per_hour', 100);

        // Attempt atomic increment: only succeeds if current count < limit
        $rows = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options}
             SET option_value = CAST(option_value AS UNSIGNED) + 1
             WHERE option_name = %s
               AND CAST(option_value AS UNSIGNED) < %d",
            $rate_limit_key,
            $max_calls
        ));

        if ($rows === 1) {
            wp_cache_delete($rate_limit_key, 'options');
            return true;
        }

        // Either option doesn't exist yet, or limit is reached
        $current = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $rate_limit_key
        ));

        if ($current > 0) {
            // Option exists but limit is reached
            return false;
        }

        // First call this hour - bootstrap the counter
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
             VALUES (%s, '1', 'no')
             ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
            $rate_limit_key
        ));

        wp_cache_delete($rate_limit_key, 'options');

        if ($inserted === false) {
            error_log('Listeo AI Search: Rate limit bootstrap failed');
            return true; // fail open
        }

        // Re-check after insert (another request may have won the race and hit the limit)
        $val = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $rate_limit_key
        ));

        return $val <= $max_calls;
    }

    /**
     * Read-only rate limit check for UI / preflight guards.
     * Does NOT reserve quota. Use try_acquire_rate_limit() before API calls.
     *
     * @return bool True if under limit
     */
    public static function check_rate_limit() {
        $rate_limit_key = 'listeo_ai_rate_limit_' . date('Y-m-d-H');
        $current_calls = (int) get_option($rate_limit_key, 0);
        $max_calls_per_hour = get_option('listeo_ai_search_rate_limit_per_hour', 100);

        return $current_calls < $max_calls_per_hour;
    }
    
    /**
     * Generate AI embedding for text (OpenAI/Gemini)
     *
     * @param string $text Text to generate embedding for
     * @param bool $skip_rate_limit Whether to skip rate limiting check (for batch processing)
     * @return array|false Embedding array or false on failure
     * @throws Exception On API errors
     */
    public function generate_embedding($text, $skip_rate_limit = false) {
        if (empty($this->api_key)) {
            return false;
        }

        // Atomically acquire rate limit slot before API call (unless skip is explicitly requested for batch operations)
        if (!$skip_rate_limit && !self::try_acquire_rate_limit()) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }

        // Get provider-specific configuration
        $endpoint = $this->provider->get_endpoint('embeddings');
        $headers = $this->provider->get_headers();

        // Sanitize text to ensure valid UTF-8 encoding (prevents json_encode failures)
        $sanitized_text = self::sanitize_utf8($text);
        $payload = $this->provider->prepare_embedding_payload($sanitized_text);

        // Encode payload to JSON with error handling
        $json_body = json_encode($payload);
        if ($json_body === false) {
            $json_error = json_last_error_msg();
            error_log("Listeo AI Search: JSON encoding failed - " . $json_error);
            throw new Exception('Failed to encode content for API request: ' . $json_error . '. The content may contain invalid characters.');
        }

        // DEBUG: Log model being sent
        if (get_option('listeo_ai_search_debug_mode', false)) {
            error_log(sprintf(
                "[AI Search Embedding] Provider: %s | Endpoint: %s | Model: %s | Dimensions: %d",
                $this->provider->get_provider(),
                $endpoint,
                isset($payload['model']) ? $payload['model'] : 'N/A',
                $this->provider->get_embedding_dimensions()
            ));
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => $json_body,
            'timeout' => 60, // Increased from 30 to 60 seconds for more reliability
        ));

        if (is_wp_error($response)) {
            $provider_name = $this->provider->get_provider_name();
            $error_msg = $provider_name . ' API request failed: ' . $response->get_error_message();
            $error_code = $response->get_error_code();

            // Always log critical API errors with more context
            error_log("CRITICAL: Embedding API Error [Code: {$error_code}]: " . $error_msg);

            // Log the full WP_Error object for debugging
            if (get_option('listeo_ai_search_debug_mode', false)) {
                error_log("Full WP_Error: " . print_r($response, true));
            }

            throw new Exception($error_msg);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        // Log non-200 response codes
        if ($response_code !== 200) {
            error_log(sprintf(
                "Listeo AI Search: Non-200 response code: %d, Body: %s",
                $response_code,
                substr($raw_body, 0, 500)
            ));
        }

        // Only log successful responses in debug mode
        if (get_option('listeo_ai_search_debug_mode', false)) {
            Listeo_AI_Search_Utility_Helper::debug_log(
                sprintf("Embedding API Response - Code: %d, Provider: %s | Model: %s | Dimensions: %d",
                    $response_code,
                    $this->provider->get_provider_name(),
                    $this->provider->get_embedding_model(),
                    $this->provider->get_embedding_dimensions()),
                'info'
            );
        }

        if ($response_code !== 200) {
            $provider_name = $this->provider->get_provider_name();
            $full_error = self::format_api_error_message($provider_name, $response_code, $body, $raw_body);

            error_log("CRITICAL: Embedding API HTTP Error Response: " . $full_error);

            throw new Exception($full_error);
        }

        if (isset($body['error'])) {
            $provider_name = $this->provider->get_provider_name();
            $full_error = self::format_api_error_message($provider_name, $response_code, $body, $raw_body);

            // Always log critical API errors
            error_log("CRITICAL: Embedding API Error Response: " . $full_error);

            // Detailed response body only in debug mode
            Listeo_AI_Search_Utility_Helper::debug_log(
                "Full response body: " . print_r($body, true),
                'error'
            );

            throw new Exception($full_error);
        }

        // Rate limit already acquired atomically before the API call

        $embedding = $this->provider->parse_embedding_response($body);
        if (!$embedding) {
            $provider_name = $this->provider->get_provider_name();
            $full_error = self::format_api_error_message($provider_name, $response_code, $body, $raw_body);

            throw new Exception($full_error);
        }

        return $embedding;
    }

    /**
     * Format the exact provider response into an error string safe for logs/admin UI.
     *
     * @param string $provider_name Provider display name.
     * @param int    $http_code HTTP response code.
     * @param array|null $body Decoded response body.
     * @param string $raw_body Raw response body.
     * @return string
     */
    public static function format_api_error_message($provider_name, $http_code, $body, $raw_body = '') {
        $message = '';

        if (is_array($body)) {
            if (isset($body['error'])) {
                if (is_array($body['error'])) {
                    $message = $body['error']['message'] ?? wp_json_encode($body['error']);
                } else {
                    $message = (string) $body['error'];
                }
            } elseif (isset($body['message'])) {
                $message = is_scalar($body['message']) ? (string) $body['message'] : wp_json_encode($body['message']);
            } elseif (isset($body['detail'])) {
                $message = is_scalar($body['detail']) ? (string) $body['detail'] : wp_json_encode($body['detail']);
            }
        }

        if ($message === '' && is_string($raw_body) && trim($raw_body) !== '') {
            $message = trim($raw_body);
        }

        if ($message === '') {
            $message = 'Response did not include embedding data';
        }

        return sprintf(
            '%s API HTTP %d: %s',
            $provider_name,
            (int) $http_code,
            substr($message, 0, 1000)
        );
    }
    
    /**
     * Get content formatted for embedding generation using modular extractor system
     *
     * @param int $post_id Post ID (any post type)
     * @return string Structured content for embedding
     */
    public function get_content_for_embedding($post_id) {
        return Listeo_AI_Content_Extractor_Factory::extract_content($post_id);
    }

    /**
     * Get listing content formatted for embedding generation using structured data approach
     *
     * @deprecated 2.0.0 Use get_content_for_embedding() instead
     * @param int $listing_id Listing post ID
     * @return string Structured content for embedding
     */
    public function get_listing_content_for_embedding($listing_id) {
        // Backward compatibility wrapper
        return $this->get_content_for_embedding($listing_id);
    }

    /**
     * Legacy method - keeping for reference but not used anymore
     */
    private function _old_get_listing_content_for_embedding($listing_id) {
        $post = get_post($listing_id);

        if (!$post || $post->post_type !== 'listing') {
            return '';
        }
        
        $structured_content = "";
        
        // TIER 1: Core Business Identity (Highest Priority)
        $structured_content .= "BUSINESS_NAME: " . get_the_title($listing_id) . ". ";
        
        $primary_category = $this->get_primary_category($listing_id);
        if ($primary_category) {
            $structured_content .= "PRIMARY_CATEGORY: " . $primary_category . ". ";
        }
        
        $listing_type = get_post_meta($listing_id, '_listing_type', true);
        if ($listing_type) {
            $structured_content .= "BUSINESS_TYPE: " . $listing_type . ". ";
        }
        
        // TIER 2: Location Information (Critical for geographic searches)
        $location_info = $this->get_structured_location($listing_id);
        if ($location_info) {
            $structured_content .= "LOCATION: " . $location_info . ". ";
        }
        
        // TIER 3: Official Business Information
        $verified_amenities = $this->get_verified_amenities($listing_id);
        if ($verified_amenities) {
            $structured_content .= "OFFICIAL_AMENITIES: " . $verified_amenities . ". ";
        }
        
        $verified_features = $this->get_verified_features($listing_id);
        if ($verified_features) {
            $structured_content .= "VERIFIED_FEATURES: " . $verified_features . ". ";
        }
        
        // TIER 4: Owner/Official Descriptions (High Authority)
        $owner_description = $this->get_owner_description($listing_id);
        if ($owner_description) {
            $structured_content .= "OWNER_DESCRIPTION: " . $owner_description . ". ";
        }

        $business_details = $this->get_business_details($listing_id);
        if ($business_details) {
            $structured_content .= "BUSINESS_DETAILS: " . $business_details . ". ";
        }

        // FAQ Questions (Official Business Information - High Authority)
        $faq_questions = $this->get_faq_questions($listing_id);
        if ($faq_questions) {
            $structured_content .= "FAQ_QUESTIONS: " . $faq_questions . ". ";
            Listeo_AI_Search_Utility_Helper::debug_log("Added FAQ questions for listing {$listing_id}: " . substr($faq_questions, 0, 100) . "...", 'embedding');
        }        // TIER 5: Operating Information
        $operating_hours = $this->get_formatted_hours($listing_id);
        if ($operating_hours) {
            $structured_content .= "OPERATING_HOURS: " . $operating_hours . ". ";
        }

        // Enhanced pricing with detailed menu/pricing table
        $pricing_info = $this->get_enhanced_pricing_info($listing_id);
        if ($pricing_info) {
            $structured_content .= "PRICING: " . $pricing_info . ". ";
            Listeo_AI_Search_Utility_Helper::debug_log("Added enhanced pricing for listing {$listing_id}: " . substr($pricing_info, 0, 100) . "...", 'embedding');
        }

        // FAQ Answers (Operating Information)
        $faq_answers = $this->get_faq_answers($listing_id);
        if ($faq_answers) {
            $structured_content .= "FAQ_ANSWERS: " . $faq_answers . ". ";
            Listeo_AI_Search_Utility_Helper::debug_log("Added FAQ answers for listing {$listing_id}: " . substr($faq_answers, 0, 100) . "...", 'embedding');
        }

        // Contact Information (Business Contact Details)
        $contact_info = $this->get_contact_information($listing_id);
        if ($contact_info) {
            $structured_content .= "CONTACT: " . $contact_info . ". ";
        }

        // TIER 6: User-Generated Content (Lower Priority but still valuable)
        $raw_reviews = $this->get_raw_reviews_for_embedding($listing_id);
        if ($raw_reviews) {
            $structured_content .= "USER_REVIEWS: " . $raw_reviews . ". ";
        }

        // TIER 6.5: AI-Generated Review Insights (Positive Only)
        $review_highlights = $this->get_positive_review_highlights($listing_id);
        if ($review_highlights) {
            $structured_content .= "REVIEW_INSIGHTS: " . $review_highlights . ". ";
            Listeo_AI_Search_Utility_Helper::debug_log("Added positive review highlights for listing {$listing_id}: " . substr($review_highlights, 0, 100) . "...", 'embedding');
        }
        
        return trim($structured_content);
    }
    
    /**
     * Get primary category for a listing with intelligent fallback
     * 
     * @param int $listing_id Listing post ID
     * @return string Primary category
     */
    private function get_primary_category($listing_id) {
        // Try listing-specific categories first
        $listing_type = get_post_meta($listing_id, '_listing_type', true);
        
        switch ($listing_type) {
            case 'service':
                $categories = wp_get_post_terms($listing_id, 'service_category', array('fields' => 'names'));
                break;
            case 'rental':
                $categories = wp_get_post_terms($listing_id, 'rental_category', array('fields' => 'names'));
                break;
            case 'event':
                $categories = wp_get_post_terms($listing_id, 'event_category', array('fields' => 'names'));
                break;
            case 'classifieds':
                $categories = wp_get_post_terms($listing_id, 'classifieds_category', array('fields' => 'names'));
                break;
            default:
                $categories = wp_get_post_terms($listing_id, 'listing_category', array('fields' => 'names'));
                break;
        }
        
        if (!is_wp_error($categories) && !empty($categories)) {
            return $categories[0]; // Return the first (primary) category
        }
        
        // Fallback to general listing category
        $general_categories = wp_get_post_terms($listing_id, 'listing_category', array('fields' => 'names'));
        if (!is_wp_error($general_categories) && !empty($general_categories)) {
            return $general_categories[0];
        }
        
        return '';
    }
    
    /**
     * Get structured location information
     * 
     * @param int $listing_id Listing post ID
     * @return string Formatted location
     */
    private function get_structured_location($listing_id) {
        $location_parts = array();
        
        // Get friendly address first (most complete)
        $friendly_address = get_post_meta($listing_id, '_friendly_address', true);
        if ($friendly_address) {
            return $friendly_address;
        }
        
        // Build from components
        $address_data = get_post_meta($listing_id, '_address', true);
        if (is_array($address_data)) {
            if (!empty($address_data['street'])) {
                $location_parts[] = $address_data['street'];
            }
            if (!empty($address_data['city'])) {
                $location_parts[] = $address_data['city'];
            }
            if (!empty($address_data['state'])) {
                $location_parts[] = $address_data['state'];
            }
            if (!empty($address_data['country'])) {
                $location_parts[] = $address_data['country'];
            }
        }
        
        // Add region if available
        $regions = wp_get_post_terms($listing_id, 'region', array('fields' => 'names'));
        if (!is_wp_error($regions) && !empty($regions)) {
            $location_parts[] = $regions[0];
        }
        
        return implode(', ', array_filter($location_parts));
    }

    /**
     * Get contact information
     *
     * @param int $listing_id Listing post ID
     * @return string Formatted contact details
     */
    private function get_contact_information($listing_id) {
        $contact_parts = array();

        $phone = get_post_meta($listing_id, '_phone', true);
        if ($phone) {
            $contact_parts[] = "Phone: " . $phone;
        }

        $email = get_post_meta($listing_id, '_email', true);
        if ($email) {
            $contact_parts[] = "Email: " . $email;
        }

        $website = get_post_meta($listing_id, '_website', true);
        if ($website) {
            $contact_parts[] = "Website: " . $website;
        }

        return implode(', ', $contact_parts);
    }

    /**
     * Get verified amenities (from official sources)
     *
     * @param int $listing_id Listing post ID
     * @return string Comma-separated amenities
     */
    private function get_verified_amenities($listing_id) {
        $amenities = array();
        
        // Get features from meta
        $features = get_post_meta($listing_id, '_features', true);
        if ($features && is_array($features)) {
            $amenities = array_merge($amenities, $features);
        }
        
        // Get listing features taxonomy
        $feature_terms = wp_get_post_terms($listing_id, 'listing_feature', array('fields' => 'names'));
        if (!is_wp_error($feature_terms) && !empty($feature_terms)) {
            $amenities = array_merge($amenities, $feature_terms);
        }
        
        // Filter out duplicates and empty values
        $amenities = array_filter(array_unique($amenities));
        
        return implode(', ', $amenities);
    }
    
    /**
     * Get verified features (distinct from amenities)
     * 
     * @param int $listing_id Listing post ID
     * @return string Comma-separated features
     */
    private function get_verified_features($listing_id) {
        $features = array();

        // Check for specific feature flags
        $has_video = get_post_meta($listing_id, '_video', true);
        if (!empty($has_video)) {
            $features[] = 'Video Content Available';
        }
        
        $website = get_post_meta($listing_id, '_website', true);
        if (!empty($website)) {
            $features[] = 'Official Website';
        }
        
        $phone = get_post_meta($listing_id, '_phone', true);
        if (!empty($phone)) {
            $features[] = 'Phone Contact Available';
        }
        
        return implode(', ', $features);
    }
    
    /**
     * Get owner/official description
     * 
     * @param int $listing_id Listing post ID
     * @return string Owner description
     */
    private function get_owner_description($listing_id) {
        $description_parts = array();
        
        // Post content (main description)
        $post = get_post($listing_id);
        if ($post && !empty($post->post_content)) {
            $description_parts[] = strip_tags($post->post_content);
        }
        
        // Post excerpt
        if ($post && !empty($post->post_excerpt)) {
            $description_parts[] = strip_tags($post->post_excerpt);
        }
        
        // Listing description meta
        $listing_description = get_post_meta($listing_id, '_listing_description', true);
        if (!empty($listing_description)) {
            $description_parts[] = strip_tags($listing_description);
        }
        
        // Tagline
        $tagline = get_post_meta($listing_id, '_tagline', true);
        if (!empty($tagline)) {
            $description_parts[] = $tagline;
        }
        
        // Combine and limit length
        $full_description = implode(' ', $description_parts);
        
        // Limit to reasonable length for embedding (about 200 words)
        $words = str_word_count($full_description, 1);
        if (count($words) > 200) {
            $full_description = implode(' ', array_slice($words, 0, 200));
        }
        
        return trim($full_description);
    }
    
    /**
     * Get business details (contact, keywords, etc.)
     * 
     * @param int $listing_id Listing post ID
     * @return string Business details
     */
    private function get_business_details($listing_id) {
        $details = array();
        
        $keywords = get_post_meta($listing_id, '_keywords', true);
        if (!empty($keywords)) {
            $details[] = $keywords;
        }
        
        $email = get_post_meta($listing_id, '_email', true);
        if (!empty($email)) {
            $details[] = 'Email contact available';
        }
        
        return implode(', ', $details);
    }
    
    /**
     * Get formatted operating hours
     * 
     * @param int $listing_id Listing post ID
     * @return string Formatted hours
     */
    private function get_formatted_hours($listing_id) {
        $opening_hours = get_post_meta($listing_id, '_opening_hours', true);
        
        if (empty($opening_hours)) {
            return '';
        }
        
        if (is_serialized($opening_hours)) {
            $hours_data = maybe_unserialize($opening_hours);
            if (is_array($hours_data)) {
                $hours_text = array();
                $has_24h = false;
                $typical_hours = true;
                
                foreach ($hours_data as $day => $hours) {
                    if (!empty($hours['opening']) && !empty($hours['closing'])) {
                        // Check for 24h operation
                        if ($hours['opening'] === '00:00' && $hours['closing'] === '23:59') {
                            $has_24h = true;
                        }
                        // Check for unusual hours (before 6 AM or after 11 PM)
                        $opening_hour = intval(substr($hours['opening'], 0, 2));
                        $closing_hour = intval(substr($hours['closing'], 0, 2));
                        if ($opening_hour < 6 || $closing_hour > 23) {
                            $typical_hours = false;
                        }
                        
                        $hours_text[] = ucfirst($day) . ' ' . $hours['opening'] . '-' . $hours['closing'];
                    }
                }
                
                // Provide semantic summaries for common patterns
                if ($has_24h) {
                    return '24 hour operation, ' . implode(', ', array_slice($hours_text, 0, 2));
                } elseif (!$typical_hours) {
                    return 'Extended hours available, ' . implode(', ', array_slice($hours_text, 0, 2));
                } else {
                    return implode(', ', array_slice($hours_text, 0, 3)); // Limit to 3 days
                }
            }
        }
        
        return 'Operating hours available';
    }
    
    /**
     * Get pricing information
     * 
     * @param int $listing_id Listing post ID
     * @return string Pricing info
     */
    private function get_pricing_info($listing_id) {
        $pricing_parts = array();
        
        $price_min = get_post_meta($listing_id, '_price_min', true);
        $price_max = get_post_meta($listing_id, '_price_max', true);
        
        if (!empty($price_min) || !empty($price_max)) {
            if (!empty($price_min) && !empty($price_max)) {
                $pricing_parts[] = "Price range {$price_min}-{$price_max}";
            } elseif (!empty($price_min)) {
                $pricing_parts[] = "Starting from {$price_min}";
            } else {
                $pricing_parts[] = "Up to {$price_max}";
            }
        }
        
        $price = get_post_meta($listing_id, '_price', true);
        if (!empty($price) && empty($pricing_parts)) {
            $pricing_parts[] = "Price {$price}";
        }
        
        return implode(', ', $pricing_parts);
    }

    /**
     * Get enhanced pricing information including detailed menu/pricing table
     * 
     * @param int $listing_id Listing post ID
     * @return string Enhanced pricing info
     */
    private function get_enhanced_pricing_info($listing_id) {
        $pricing_parts = array();
        
        // First get basic pricing (keep existing functionality)
        $price_min = get_post_meta($listing_id, '_price_min', true);
        $price_max = get_post_meta($listing_id, '_price_max', true);
        
        if (!empty($price_min) || !empty($price_max)) {
            if (!empty($price_min) && !empty($price_max)) {
                $pricing_parts[] = "Price range {$price_min}-{$price_max}";
            } elseif (!empty($price_min)) {
                $pricing_parts[] = "Starting from {$price_min}";
            } else {
                $pricing_parts[] = "Up to {$price_max}";
            }
        }
        
        $price = get_post_meta($listing_id, '_price', true);
        if (!empty($price) && empty($pricing_parts)) {
            $pricing_parts[] = "Price {$price}";
        }

        // Add detailed menu/pricing table information
        $menu_status = get_post_meta($listing_id, '_menu_status', true);
        if ($menu_status) {
            $menu = get_post_meta($listing_id, '_menu', true);
            if (!empty($menu) && is_array($menu)) {
                $menu_items = array();
                
                foreach ($menu as $menu_section) {
                    $section_items = array();
                    
                    // Add menu section title
                    if (isset($menu_section['menu_title']) && !empty($menu_section['menu_title'])) {
                        $section_items[] = "Section: " . $menu_section['menu_title'];
                    }
                    
                    // Add menu items
                    if (isset($menu_section['menu_elements']) && !empty($menu_section['menu_elements'])) {
                        foreach ($menu_section['menu_elements'] as $item) {
                            $item_parts = array();
                            
                            if (isset($item['name']) && !empty($item['name'])) {
                                $item_parts[] = $item['name'];
                            }
                            
                            if (isset($item['price']) && !empty($item['price'])) {
                                $item_parts[] = "(" . $item['price'] . ")";
                            } else {
                                $item_parts[] = "(Free)";
                            }
                            
                            if (isset($item['description']) && !empty($item['description'])) {
                                // Clean and truncate description for embedding
                                $desc = strip_tags($item['description']);
                                $desc = wp_trim_words($desc, 15);
                                $item_parts[] = $desc;
                            }
                            
                            if (!empty($item_parts)) {
                                $section_items[] = implode(' ', $item_parts);
                            }
                        }
                    }
                    
                    if (!empty($section_items)) {
                        $menu_items[] = implode(', ', $section_items);
                    }
                }
                
                if (!empty($menu_items)) {
                    $pricing_parts[] = "Menu: " . implode('; ', $menu_items);
                }
            }
        }
        
        return implode(', ', $pricing_parts);
    }

    /**
     * Get FAQ questions for embedding (Tier 4 - Official Business Information)
     * 
     * @param int $listing_id Listing post ID
     * @return string FAQ questions content
     */
    private function get_faq_questions($listing_id) {
        $faq_status = get_post_meta($listing_id, '_faq_status', true);
        if (!$faq_status) {
            return '';
        }
        
        $faqs = get_post_meta($listing_id, '_faq_list', true);
        if (empty($faqs) || !is_array($faqs)) {
            return '';
        }
        
        $questions = array();
        foreach ($faqs as $faq) {
            if (isset($faq['question']) && !empty($faq['question'])) {
                $questions[] = $faq['question'];
            }
        }
        
        return !empty($questions) ? implode(', ', $questions) : '';
    }

    /**
     * Get FAQ answers for embedding (Tier 5 - Operating Information)
     * 
     * @param int $listing_id Listing post ID
     * @return string FAQ answers content
     */
    private function get_faq_answers($listing_id) {
        $faq_status = get_post_meta($listing_id, '_faq_status', true);
        if (!$faq_status) {
            return '';
        }
        
        $faqs = get_post_meta($listing_id, '_faq_list', true);
        if (empty($faqs) || !is_array($faqs)) {
            return '';
        }
        
        $answers = array();
        foreach ($faqs as $faq) {
            if (isset($faq['answer']) && !empty($faq['answer'])) {
                // Clean HTML and truncate for embedding
                $answer = strip_tags($faq['answer']);
                $answer = wp_trim_words($answer, 30); // Limit to 30 words per answer
                $answers[] = $answer;
            }
        }
        
        return !empty($answers) ? implode(', ', $answers) : '';
    }

    /**
     * Get positive AI review highlights for embedding (exclude negative sentiment)
     * 
     * @param int $listing_id Listing post ID
     * @return string Positive review highlights content
     */
    private function get_positive_review_highlights($listing_id) {
        global $wpdb;
        
        // Check if ai-review-highlights plugin table exists
        $table_name = $wpdb->prefix . 'ai_review_summaries';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return '';
        }
        
        // Get review summary data for this listing
        $summary_row = $wpdb->get_row($wpdb->prepare(
            "SELECT summary_data FROM {$table_name} WHERE listing_id = %d",
            $listing_id
        ));
        
        if (!$summary_row || empty($summary_row->summary_data)) {
            return '';
        }
        
        // Decode JSON data
        $summary_data = json_decode($summary_row->summary_data, true);
        if (!isset($summary_data['summaries']) || !is_array($summary_data['summaries'])) {
            return '';
        }
        
        // Extract only positive highlights
        $positive_highlights = array();
        foreach ($summary_data['summaries'] as $item) {
            // Only include positive sentiment highlights
            if (isset($item['sentiment']) && $item['sentiment'] === 'positive') {
                $highlight_parts = array();
                
                // Add title (the main theme)
                if (isset($item['title']) && !empty($item['title'])) {
                    $highlight_parts[] = $item['title'];
                }
                
                // Add description if available (AI highlights are already optimized)
                if (isset($item['description']) && !empty($item['description'])) {
                    // Clean but don't truncate - AI highlights are already concise and valuable
                    $description = strip_tags($item['description']);
                    // Only truncate if extremely long (over 100 words)
                    if (str_word_count($description) > 100) {
                        $description = wp_trim_words($description, 100);
                    }
                    $highlight_parts[] = $description;
                }
                
                if (!empty($highlight_parts)) {
                    $positive_highlights[] = implode(' - ', $highlight_parts);
                }
            }
        }
        
        return !empty($positive_highlights) ? implode(', ', $positive_highlights) : '';
    }
    
    /**
     * Get raw reviews for embedding (authentic user language)
     * 
     * @param int $listing_id Listing post ID
     * @return string Raw review content
     */
    private function get_raw_reviews_for_embedding($listing_id) {
        $review_content = array();
        
        // Get WordPress Reviews (max 5, rating >= 3.0)
        $wp_comments = get_comments(array(
            'post_id' => $listing_id,
            'status' => 'approve',
            'type__in' => array('comment', 'review'),
            'number' => 10, // Get more to filter for quality
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ));
        
        $wp_count = 0;
        foreach ($wp_comments as $comment) {
            if ($wp_count >= 5) break; // Max 5 WordPress reviews
            
            if (!empty($comment->comment_content) && strlen($comment->comment_content) > 15) {
                $rating = get_comment_meta($comment->comment_ID, 'listeo-review-rating', true);
                
                // Skip negative reviews (below 3.0)
                if (!empty($rating) && floatval($rating) < 3.0) {
                    continue;
                }
                
                $content = strip_tags($comment->comment_content);
                $content = preg_replace('/\s+/', ' ', $content); // Clean whitespace
                $content = trim($content);
                
                if (!empty($content)) {
                    $review_content[] = "[WordPress Review] " . $content;
                    $wp_count++;
                }
            }
        }
        
        // Get Google Reviews (max 5, rating >= 3.0)
        $transient_name = 'listeo_reviews_' . $listing_id;
        $google_data = get_transient($transient_name);
        
        if (!empty($google_data) && isset($google_data['result']['reviews']) && is_array($google_data['result']['reviews'])) {
            $google_count = 0;
            
            foreach ($google_data['result']['reviews'] as $review) {
                if ($google_count >= 5) break; // Max 5 Google reviews
                
                if (!empty($review['text']) && strlen($review['text']) > 15) {
                    $rating = isset($review['rating']) ? floatval($review['rating']) : null;
                    
                    // Skip negative reviews (below 3.0)
                    if (!empty($rating) && $rating < 3.0) {
                        continue;
                    }
                    
                    $content = strip_tags($review['text']);
                    $content = preg_replace('/\s+/', ' ', $content); // Clean whitespace
                    $content = trim($content);
                    
                    if (!empty($content)) {
                        $review_content[] = "[Google Review] " . $content;
                        $google_count++;
                    }
                }
            }
        }
        
        return !empty($review_content) ? implode(' ', $review_content) : '';
    }
    
    /**
     * Extract meaningful insights from review data using semantic analysis
     * 
     * @param array $review_data Array of review data
     * @return string Meaningful review insights
     */
    private function extract_review_insights($review_data) {
        $insights = array();
        $rating_sum = 0;
        $rating_count = 0;
        $positive_themes = array();
        $negative_themes = array();
        
        foreach ($review_data as $review) {
            // Collect ratings
            if (!empty($review['rating']) && is_numeric($review['rating'])) {
                $rating_sum += $review['rating'];
                $rating_count++;
                
                // Analyze sentiment based on rating
                $rating_value = floatval($review['rating']);
                $content = $this->normalize_text($review['content']);
                
                if ($rating_value >= 4) {
                    // High rating - extract positive aspects
                    $themes = $this->extract_business_themes($content, 'positive');
                    $positive_themes = array_merge($positive_themes, $themes);
                } elseif ($rating_value <= 2) {
                    // Low rating - extract negative aspects
                    $themes = $this->extract_business_themes($content, 'negative');
                    $negative_themes = array_merge($negative_themes, $themes);
                }
            }
        }
        
        // Build insights starting with ratings
        if ($rating_count > 0) {
            $avg_rating = round($rating_sum / $rating_count, 1);
            $insights[] = "Average user rating {$avg_rating}/5 from {$rating_count} reviews";
            
            // Add rating distribution context
            if ($avg_rating >= 4.5) {
                $insights[] = "Consistently excellent customer satisfaction";
            } elseif ($avg_rating >= 4.0) {
                $insights[] = "Generally positive customer feedback";
            } elseif ($avg_rating >= 3.0) {
                $insights[] = "Mixed customer experiences";
            } else {
                $insights[] = "Below average customer satisfaction";
            }
        }
        
        // Add positive themes summary
        if (!empty($positive_themes)) {
            $top_positive = $this->summarize_themes($positive_themes);
            if (!empty($top_positive)) {
                $insights[] = "Customers appreciate: " . $top_positive;
            }
        }
        
        // Add areas for improvement (if any)
        if (!empty($negative_themes)) {
            $top_negative = $this->summarize_themes($negative_themes);
            if (!empty($top_negative)) {
                $insights[] = "Areas mentioned for improvement: " . $top_negative;
            }
        }
        
        return !empty($insights) ? implode('. ', $insights) : '';
    }
    
    /**
     * Extract business themes from review content using semantic patterns
     * 
     * @param string $content Normalized review content
     * @param string $sentiment_type 'positive' or 'negative'
     * @return array Array of business themes
     */
    private function extract_business_themes($content, $sentiment_type = 'positive') {
        $themes = array();
        
        // Universal business aspects that work across languages
        $business_patterns = array(
            'service' => array(
                'positive' => array('excellent', 'great', 'amazing', 'wonderful', 'fantastic', 'professional', 'friendly', 'helpful', 'courteous', 'attentive'),
                'negative' => array('poor', 'bad', 'terrible', 'rude', 'slow', 'unprofessional', 'unhelpful', 'disappointing')
            ),
            'quality' => array(
                'positive' => array('high quality', 'excellent', 'fresh', 'delicious', 'perfect', 'outstanding', 'superior'),
                'negative' => array('poor quality', 'stale', 'old', 'tasteless', 'inferior', 'substandard')
            ),
            'cleanliness' => array(
                'positive' => array('clean', 'spotless', 'hygienic', 'tidy', 'well-maintained'),
                'negative' => array('dirty', 'messy', 'unclean', 'unhygienic', 'poorly maintained')
            ),
            'value' => array(
                'positive' => array('affordable', 'reasonable', 'good value', 'worth it', 'fair price'),
                'negative' => array('expensive', 'overpriced', 'not worth it', 'poor value')
            ),
            'atmosphere' => array(
                'positive' => array('cozy', 'welcoming', 'comfortable', 'relaxing', 'pleasant', 'nice ambiance'),
                'negative' => array('noisy', 'uncomfortable', 'unwelcoming', 'chaotic', 'unpleasant')
            ),
            'location' => array(
                'positive' => array('convenient', 'accessible', 'easy to find', 'good location', 'central'),
                'negative' => array('difficult to find', 'inconvenient', 'poor location', 'hard to reach')
            ),
            'speed' => array(
                'positive' => array('fast', 'quick', 'prompt', 'efficient', 'timely'),
                'negative' => array('slow', 'delayed', 'long wait', 'inefficient', 'late')
            )
        );
        
        // Look for semantic patterns rather than exact word matches
        foreach ($business_patterns as $aspect => $patterns) {
            if (isset($patterns[$sentiment_type])) {
                foreach ($patterns[$sentiment_type] as $pattern) {
                    // Use flexible matching for universal concepts
                    if ($this->content_mentions_concept($content, $pattern, $aspect)) {
                        $themes[] = $aspect;
                        break; // Only count each aspect once per review
                    }
                }
            }
        }
        
        return $themes;
    }
    
    /**
     * Check if content mentions a business concept (language-flexible)
     * 
     * @param string $content Review content
     * @param string $pattern Pattern to look for
     * @param string $aspect Business aspect being checked
     * @return bool True if concept is mentioned
     */
    private function content_mentions_concept($content, $pattern, $aspect) {
        // Simple pattern matching for now - could be enhanced with ML/NLP
        $content_lower = mb_strtolower($content, 'UTF-8');
        
        // Direct pattern match
        if (strpos($content_lower, $pattern) !== false) {
            return true;
        }
        
        // Aspect-specific semantic hints (works across languages)
        switch ($aspect) {
            case 'service':
                // Look for service-related indicators
                if (preg_match('/\b(staff|personnel|service|waiter|waitress|employee|worker)\b/i', $content)) {
                    return $this->has_positive_context($content, $pattern);
                }
                break;
                
            case 'quality':
                // Look for quality-related indicators  
                if (preg_match('/\b(food|meal|dish|product|item|quality)\b/i', $content)) {
                    return $this->has_positive_context($content, $pattern);
                }
                break;
                
            case 'cleanliness':
                // Look for cleanliness indicators
                if (preg_match('/\b(clean|dirty|hygiene|maintenance|condition)\b/i', $content)) {
                    return $this->has_positive_context($content, $pattern);
                }
                break;
                
            case 'value':
                // Look for price/value indicators
                if (preg_match('/\b(price|cost|value|money|expensive|cheap|affordable)\b/i', $content)) {
                    return $this->has_positive_context($content, $pattern);
                }
                break;
                
            case 'atmosphere':
                // Look for atmosphere indicators
                if (preg_match('/\b(atmosphere|ambiance|environment|mood|feeling|vibe)\b/i', $content)) {
                    return $this->has_positive_context($content, $pattern);
                }
                break;
                
            case 'location':
                // Look for location indicators
                if (preg_match('/\b(location|place|address|find|parking|access)\b/i', $content)) {
                    return $this->has_positive_context($content, $pattern);
                }
                break;
                
            case 'speed':
                // Look for timing indicators
                if (preg_match('/\b(fast|slow|quick|wait|time|service|delivery)\b/i', $content)) {
                    return $this->has_positive_context($content, $pattern);
                }
                break;
        }
        
        return false;
    }
    
    /**
     * Check if the context around a mention is positive (language-flexible)
     * 
     * @param string $content Review content
     * @param string $pattern Pattern being checked
     * @return bool True if context seems positive
     */
    private function has_positive_context($content, $pattern) {
        // Universal positive/negative indicators
        $positive_indicators = array('good', 'great', 'excellent', 'amazing', 'wonderful', 'perfect', 'love', 'like', 'best', 'fantastic', 'awesome', 'nice', 'beautiful', 'comfortable', 'pleasant', 'satisfied', 'happy', 'recommend');
        $negative_indicators = array('bad', 'terrible', 'awful', 'worst', 'hate', 'dislike', 'poor', 'disappointing', 'unacceptable', 'disgusting', 'horrible', 'unsatisfied', 'unhappy', 'avoid', 'never');
        
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_indicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                $positive_count++;
            }
        }
        
        foreach ($negative_indicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                $negative_count++;
            }
        }
        
        // Return true if more positive than negative indicators
        return $positive_count > $negative_count;
    }
    
    /**
     * Summarize themes into readable format
     * 
     * @param array $themes Array of theme occurrences
     * @return string Formatted theme summary
     */
    private function summarize_themes($themes) {
        if (empty($themes)) {
            return '';
        }
        
        // Count theme frequency
        $theme_counts = array_count_values($themes);
        arsort($theme_counts);
        
        // Convert to readable format
        $readable_themes = array();
        foreach ($theme_counts as $theme => $count) {
            switch ($theme) {
                case 'service':
                    $readable_themes[] = 'customer service';
                    break;
                case 'quality':
                    $readable_themes[] = 'product quality';
                    break;
                case 'cleanliness':
                    $readable_themes[] = 'cleanliness standards';
                    break;
                case 'value':
                    $readable_themes[] = 'value for money';
                    break;
                case 'atmosphere':
                    $readable_themes[] = 'atmosphere and ambiance';
                    break;
                case 'location':
                    $readable_themes[] = 'location and accessibility';
                    break;
                case 'speed':
                    $readable_themes[] = 'service speed';
                    break;
                default:
                    $readable_themes[] = $theme;
            }
            
            // Limit to top 3 themes
            if (count($readable_themes) >= 3) {
                break;
            }
        }
        
        return implode(', ', $readable_themes);
    }
    
    /**
     * Normalize text for language-agnostic processing
     * 
     * @param string $text Input text
     * @return string Normalized text
     */
    private function normalize_text($text) {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Get review data in structured format
     * 
     * @param int $listing_id Listing post ID
     * @return array Array of review data
     */
    private function get_listing_reviews_data($listing_id) {
        $all_reviews = array();
        
        // Get WordPress Comments & Reviews
        $wp_comments = get_comments(array(
            'post_id' => $listing_id,
            'status' => 'approve',
            'type__in' => array('comment', 'review'),
            'number' => 20, // Reduced for better performance
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ));
        
        foreach ($wp_comments as $comment) {
            if (!empty($comment->comment_content) && strlen($comment->comment_content) > 10) {
                $rating = get_comment_meta($comment->comment_ID, 'listeo-review-rating', true);
                
                $all_reviews[] = array(
                    'type' => 'wordpress',
                    'content' => strip_tags($comment->comment_content),
                    'rating' => $rating ? floatval($rating) : null,
                    'author' => $comment->comment_author
                );
            }
        }
        
        // Get Google Reviews
        $transient_name = 'listeo_reviews_' . $listing_id;
        $google_data = get_transient($transient_name);
        
        if (!empty($google_data) && isset($google_data['result']['reviews']) && is_array($google_data['result']['reviews'])) {
            $google_reviews = array_slice($google_data['result']['reviews'], 0, 15); // Limit Google reviews
            
            foreach ($google_reviews as $review) {
                if (!empty($review['text']) && strlen($review['text']) > 10) {
                    $all_reviews[] = array(
                        'type' => 'google',
                        'content' => strip_tags($review['text']),
                        'rating' => isset($review['rating']) ? floatval($review['rating']) : null,
                        'author' => isset($review['author_name']) ? $review['author_name'] : 'Google User'
                    );
                }
            }
        }
        
        return $all_reviews;
    }
    
    /**
     * Regenerate all embeddings with improved structured format
     *
     * @param int $batch_size Number of posts to process per batch
     * @param int $start_offset Offset to start from (for resuming)
     * @param array $post_types Post types to process (default: all supported types)
     * @return array Status information
     */
    public function regenerate_structured_embeddings($batch_size = 20, $start_offset = 0, $post_types = array()) {
        global $wpdb;

        // CRITICAL: Allow unlimited execution time for long-running batch operations
        // This prevents PHP timeout errors when processing large batches
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // Continue processing even if user closes browser
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // Log start of batch processing
        if (get_option('listeo_ai_search_debug_mode', false)) {
            error_log(sprintf(
                'Listeo AI Search: Starting batch regeneration - batch_size: %d, offset: %d',
                $batch_size,
                $start_offset
            ));
        }

        if (empty($this->api_key)) {
            return array('error' => 'OpenAI API key not configured');
        }

        // Use enabled post types from database settings instead of hardcoded defaults
        if (empty($post_types)) {
            $post_types = Listeo_AI_Search_Database_Manager::get_enabled_post_types();
        }

        // If no post types are enabled, return early
        if (empty($post_types)) {
            return array(
                'status' => 'complete',
                'message' => 'No post types enabled for embedding generation',
                'processed' => 0,
                'total_posts' => 0,
                'next_offset' => 0
            );
        }

        // Check for manual selections to respect user's specific post choices
        $manual_selections = get_option('listeo_ai_search_manual_selections', array());
        $all_post_ids = array();

        // Process each post type individually:
        // - If it has manual selections, use those specific IDs
        // - If it has no manual selections, get ALL published posts of that type
        foreach ($post_types as $post_type) {
            $has_manual_selection_for_type = array_key_exists($post_type, $manual_selections)
                && !empty($manual_selections[$post_type]);

            if ($has_manual_selection_for_type) {
                // This post type has manual selections - use only those specific IDs
                $type_ids = is_array($manual_selections[$post_type])
                    ? array_filter(array_map('intval', $manual_selections[$post_type]))
                    : array();

                if (get_option('listeo_ai_search_debug_mode', false)) {
                    error_log("Listeo AI Search: Post type '{$post_type}' - using {" . count($type_ids) . "} manually selected items");
                }

                $all_post_ids = array_merge($all_post_ids, $type_ids);
            } else {
                // This post type has NO manual selections - get ALL published posts
                if (get_option('listeo_ai_search_debug_mode', false)) {
                    error_log("Listeo AI Search: Post type '{$post_type}' - getting ALL published items");
                }

                // Build query args
                $type_query_args = array(
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'orderby' => 'ID',
                    'order' => 'ASC'
                );

                // Exclude listeo-booking products (hidden booking products)
                if ($post_type === 'product') {
                    $type_query_args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'slug',
                            'terms' => 'listeo-booking',
                            'operator' => 'NOT IN'
                        )
                    );
                }

                $type_posts = get_posts($type_query_args);

                if (get_option('listeo_ai_search_debug_mode', false)) {
                    error_log("Listeo AI Search: Post type '{$post_type}' - found {" . count($type_posts) . "} published items");
                }

                $all_post_ids = array_merge($all_post_ids, $type_posts);
            }
        }

        // Remove duplicates and sort
        $all_post_ids = array_unique($all_post_ids);
        sort($all_post_ids);

        if (empty($all_post_ids)) {
            return array(
                'status' => 'complete',
                'message' => 'No posts to process',
                'processed' => 0,
                'total_posts' => 0,
                'next_offset' => 0
            );
        }

        // Get batch of posts for this request (apply pagination)
        $posts = array_slice($all_post_ids, $start_offset, $batch_size);

        // Calculate total
        $total_posts = count($all_post_ids);

        if (get_option('listeo_ai_search_debug_mode', false) && $start_offset === 0) {
            error_log("Listeo AI Search: Total items to process: {$total_posts}");
        }

        if (empty($posts)) {
            return array(
                'status' => 'complete',
                'message' => 'No more posts to process',
                'processed' => 0,
                'total_posts' => $total_posts,
                'next_offset' => $start_offset
            );
        }

        $processed = 0;
        $errors = array();
        $table_name = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();

        // Log memory usage at start of batch
        if (get_option('listeo_ai_search_debug_mode', false)) {
            $memory_used = round(memory_get_usage() / 1024 / 1024, 2);
            $memory_limit = ini_get('memory_limit');
            error_log("Listeo AI Search: Memory at batch start: {$memory_used}MB / {$memory_limit}");
        }

        $chunked_count = 0;

        foreach ($posts as $post_id) {
            try {
                // Use Database Manager's generate_single_embedding which handles chunking
                $result = Listeo_AI_Search_Database_Manager::generate_single_embedding($post_id);

                if (!$result['success']) {
                    $error_msg = isset($result['error']) ? $result['error'] : "Failed to generate embedding for post {$post_id}";
                    $errors[] = $error_msg;
                    error_log("Listeo AI Search: " . $error_msg);
                    continue;
                }

                $processed++;

                // Track chunked posts
                if (!empty($result['chunked'])) {
                    $chunked_count++;
                    if (get_option('listeo_ai_search_debug_mode', false)) {
                        error_log(sprintf(
                            "Listeo AI Search: Post %d chunked into %d parts (%d words)",
                            $post_id,
                            $result['chunks_created'] ?? 0,
                            $result['word_count'] ?? 0
                        ));
                    }
                }

                // Log progress periodically in debug mode
                if (get_option('listeo_ai_search_debug_mode', false) && $processed % 10 === 0) {
                    $memory_used = round(memory_get_usage() / 1024 / 1024, 2);
                    error_log("Listeo AI Search: Processed {$processed} embeddings, Memory: {$memory_used}MB");
                }

                // Free up memory periodically
                if ($processed % 25 === 0) {
                    wp_cache_flush();
                }

                // Add delay to avoid overwhelming the server and API rate limits
                // Optimized to 25ms for faster batch processing
                usleep(25000); // 25ms delay

            } catch (Exception $e) {
                $error_msg = "Error processing post {$post_id}: " . $e->getMessage();
                $errors[] = $error_msg;
                // Always log exceptions to error_log
                error_log("CRITICAL Listeo AI Search: " . $error_msg);

                // Log stack trace in debug mode
                if (get_option('listeo_ai_search_debug_mode', false)) {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
        }

        // Total was already calculated above
        $next_offset = min($start_offset + $batch_size, $total_posts);

        // Log completion of batch
        if (get_option('listeo_ai_search_debug_mode', false)) {
            error_log(sprintf(
                'Listeo AI Search: Batch completed - processed: %d, errors: %d, next_offset: %d, total: %d',
                $processed,
                count($errors),
                $next_offset,
                $total_posts
            ));
        }

        // If there are critical errors, log them always (not just in debug mode)
        if (!empty($errors)) {
            error_log(sprintf(
                'Listeo AI Search: Batch had %d errors. First error: %s',
                count($errors),
                isset($errors[0]) ? $errors[0] : 'Unknown'
            ));
        }

        return array(
            'status' => 'processing',
            'processed' => $processed,
            'chunked' => $chunked_count,
            'errors' => $errors,
            'next_offset' => $next_offset,
            'total_posts' => $total_posts,
            'total_listings' => $total_posts, // Add for frontend compatibility
            'post_types' => $post_types
        );
    }
    

    
    /**
     * Get reviews for a listing (both WordPress comments and Google reviews)
     * 
     * @param int $listing_id Listing post ID
     * @return string Formatted reviews content
     */
    private function get_listing_reviews($listing_id) {
        $reviews_content = '';
        $all_reviews = array();
        
        // 1. Get WordPress Comments & Reviews (same as AI Review Highlights plugin)
        $wp_comments = get_comments(array(
            'post_id' => $listing_id,
            'status' => 'approve',
            'type__in' => array('comment', 'review'),
            'number' => 25, // Limit WordPress reviews
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ));
        
        foreach ($wp_comments as $comment) {
            if (!empty($comment->comment_content)) {
                $review_data = array(
                    'type' => 'wordpress',
                    'author' => $comment->comment_author,
                    'content' => strip_tags($comment->comment_content),
                    'rating' => get_comment_meta($comment->comment_ID, 'listeo-review-rating', true),
                    'criteria' => array()
                );
                
                // Get individual criteria ratings
                $criteria = array('service', 'value-for-money', 'location', 'cleanliness');
                foreach ($criteria as $criterion) {
                    $criterion_rating = get_comment_meta($comment->comment_ID, 'listeo-review-' . $criterion, true);
                    if (!empty($criterion_rating)) {
                        $review_data['criteria'][$criterion] = $criterion_rating;
                    }
                }
                
                $all_reviews[] = $review_data;
            }
        }
        
        // 2. Get Google Reviews from Listeo transient (same approach as AI Review Highlights)
        $transient_name = 'listeo_reviews_' . $listing_id;
        $google_data = get_transient($transient_name);
        
        if (!empty($google_data) && isset($google_data['result']['reviews']) && is_array($google_data['result']['reviews'])) {
            $google_reviews = $google_data['result']['reviews'];
            
            foreach ($google_reviews as $review) {
                if (!empty($review['text'])) {
                    $author = isset($review['author_name']) ? $review['author_name'] : 'Google User';
                    $rating = isset($review['rating']) ? $review['rating'] : null;
                    
                    $review_data = array(
                        'type' => 'google',
                        'author' => $author,
                        'content' => strip_tags($review['text']),
                        'rating' => $rating,
                        'criteria' => array()
                    );
                    
                    $all_reviews[] = $review_data;
                }
            }
        }
        
        // Format all reviews for embedding
        if (!empty($all_reviews)) {
            $reviews_content .= "\n--- REVIEWS ---\n";
            
            // Limit total reviews to avoid overly long embeddings
            $review_limit = 30;
            $limited_reviews = array_slice($all_reviews, 0, $review_limit);
            
            foreach ($limited_reviews as $review) {
                $source = ($review['type'] === 'google') ? 'Google' : 'WordPress';
                $reviews_content .= "{$source} review from {$review['author']}: {$review['content']}\n";
                
                if (!empty($review['rating'])) {
                    $reviews_content .= "Rating: {$review['rating']}/5\n";
                }
                
                // Add criteria ratings for WordPress reviews
                if (!empty($review['criteria'])) {
                    foreach ($review['criteria'] as $criterion => $rating) {
                        $reviews_content .= ucfirst(str_replace('-', ' ', $criterion)) . " rating: {$rating}/5\n";
                    }
                }
                
                $reviews_content .= "\n";
            }
            
            // Add summary stats
            $wp_count = count(array_filter($all_reviews, function($r) { return $r['type'] === 'wordpress'; }));
            $google_count = count(array_filter($all_reviews, function($r) { return $r['type'] === 'google'; }));
            $reviews_content .= "--- REVIEW SUMMARY ---\n";
            $reviews_content .= "Total WordPress reviews: {$wp_count}\n";
            $reviews_content .= "Total Google reviews: {$google_count}\n";
        }
        
        return $reviews_content;
    }
    
    /**
     * Sanitize text to ensure valid UTF-8 encoding
     *
     * This prevents json_encode() failures when content contains:
     * - Invalid UTF-8 sequences
     * - Control characters
     * - Binary data from copy/paste operations
     * - Broken multibyte characters
     *
     * @param string $text Text to sanitize
     * @return string Sanitized text with valid UTF-8 encoding
     */
    public static function sanitize_utf8($text) {
        if (empty($text)) {
            return '';
        }

        // Convert to UTF-8 if it's not already
        if (!mb_check_encoding($text, 'UTF-8')) {
            // Try to detect encoding and convert
            $detected = mb_detect_encoding($text, array('UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'), true);
            if ($detected && $detected !== 'UTF-8') {
                $text = mb_convert_encoding($text, 'UTF-8', $detected);
            } else {
                // Force conversion, replacing invalid characters
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
        }

        // Remove invalid UTF-8 sequences
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // Remove null bytes and other problematic characters
        $text = str_replace(array("\0", "\xEF\xBB\xBF"), '', $text);

        // Ensure the string is valid UTF-8 by encoding/decoding through json
        // This is a fallback that removes any remaining invalid sequences
        $test_encode = json_encode($text);
        if ($test_encode === false) {
            // Last resort: strip all non-ASCII characters and rebuild
            $text = preg_replace('/[^\x20-\x7E\s]/u', '', $text);
        }

        return $text;
    }

    /**
     * Analyze embedding vector for debugging
     *
     * @param array $embedding Embedding vector
     * @return array Analysis results
     */
    public function analyze_embedding($embedding) {
        if (empty($embedding) || !is_array($embedding)) {
            return array('error' => 'Invalid embedding data');
        }
        
        $analysis = array(
            'dimensions' => count($embedding),
            'mean' => array_sum($embedding) / count($embedding),
            'min' => min($embedding),
            'max' => max($embedding),
            'std_dev' => 0
        );
        
        // Calculate standard deviation
        $mean = $analysis['mean'];
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $embedding)) / count($embedding);
        
        $analysis['std_dev'] = sqrt($variance);
        
        // Check for potential issues
        $zero_count = count(array_filter($embedding, function($x) { return $x == 0; }));
        $analysis['zero_percentage'] = ($zero_count / count($embedding)) * 100;
        
        // Determine if embedding looks healthy
        $analysis['health_status'] = 'healthy';
        if ($analysis['zero_percentage'] > 50) {
            $analysis['health_status'] = 'poor - too many zeros';
        } elseif ($analysis['std_dev'] < 0.1) {
            $analysis['health_status'] = 'poor - low variance';
        } elseif (abs($analysis['mean']) > 1) {
            $analysis['health_status'] = 'unusual - high mean';
        }
        
        return $analysis;
    }
    

}
