<?php
/**
 * Listing Content Extractor
 *
 * Extracts content from Listeo 'listing' post type with sophisticated
 * 6-tier hierarchical prioritization for optimal AI embeddings.
 *
 * @package Listeo_AI_Search
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Content_Extractor_Listing {

    /**
     * Extract content from listing for embedding generation
     *
     * Uses structured data approach with 6-tier hierarchical content prioritization:
     * - TIER 1: Core Business Identity (Highest Priority)
     * - TIER 2: Location Information
     * - TIER 3: Official Business Information
     * - TIER 4: Owner/Official Descriptions
     * - TIER 5: Operating Information
     * - TIER 6: User-Generated Content
     *
     * @param int $listing_id Listing post ID
     * @return string Structured content for embedding
     */
    public function extract_content($listing_id) {
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
        }

        // Enhanced pricing with detailed menu/pricing table
        $pricing_info = $this->get_enhanced_pricing_info($listing_id);
        if ($pricing_info) {
            $structured_content .= "PRICING: " . $pricing_info . ". ";
        }

        // FAQ Answers (Operating Information)
        $faq_answers = $this->get_faq_answers($listing_id);
        if ($faq_answers) {
            $structured_content .= "FAQ_ANSWERS: " . $faq_answers . ". ";
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
        }

        // TIER 7: Auto-detect additional custom fields not already extracted
        $already_extracted = array(
            // Core listing fields (already extracted above)
            '_listing_type', '_friendly_address', '_address', '_features',
            '_video', '_website', '_phone', '_email', '_listing_description',
            '_tagline', '_keywords', '_opening_hours', '_price_min', '_price_max',
            '_price', '_menu_status', '_menu', '_faq_status', '_faq_list',
            // Rating & review fields (not useful for search)
            'listeo-avg-rating', '_combined_rating', '_combined_review_count',
            '_google_rating', '_google_review_count', '_google_last_updated',
            // Location/geo fields (already handled)
            '_place_id', '_geolocation_lat', '_geolocation_long',
            // Status & tracking fields (internal)
            '_opening_hours_status', '_listing_views_count',
            '_listeo_unique_last_updated', '_listeo_visits_last_updated',
            '_listeo_unique_total', '_listeo_visits_total',
            // Configuration & display fields (internal) - both with and without underscore
            '_product_id', 'product_id', '_listing_url', 'listing_url',
            '_gallery_style', '_listing_timezone', '_time_increment', '_payment_option',
            '_ad_status', 'ad_status', '_ad_type', 'ad_type',
            '_animal_fee_type', 'animal_fee_type',
            '_bookmarks_counter', 'bookmarks_counter', '_featured', 'featured',
            // Duplicate fields (already extracted above, stored without underscore)
            'listing_title', 'listing_description', 'keywords', 'tagline', 'city',
            // Pricing duplicates
            'normal_price', 'weekday_price', '_normal_price', '_weekday_price',
            // Rating breakdown fields (internal)
            'service_avg', 'value_for_money_avg', 'location_avg', 'cleanliness_avg',
            '_service_avg', '_value_for_money_avg', '_location_avg', '_cleanliness_avg',
            // Internal settings
            'layout', 'verified', 'expired_after', 'booking_status', 'bedtype',
            '_layout', '_verified', '_expired_after', '_booking_status', '_bedtype',
            // More tracking fields
            'listeo_booking_click_last_updated', '_listeo_booking_click_last_updated',
            'listeo_booking_click_total', '_listeo_booking_click_total',
            'new_listing_email_notification', '_new_listing_email_notification',
            'google_reviews_last_proactive_fetch', '_google_reviews_last_proactive_fetch',
            'listeo_email_click_last_updated', 'listeo_email_click_total',
            '_listeo_email_click_last_updated', '_listeo_email_click_total',
            'listeo_contact_click_last_updated', 'listeo_contact_click_total',
            '_listeo_contact_click_last_updated', '_listeo_contact_click_total',
            'listeo_whatsapp_click_last_updated', 'listeo_whatsapp_click_total',
            '_listeo_whatsapp_click_last_updated', '_listeo_whatsapp_click_total',
            // Commission and view tracking
            'per_product_admin_commission_type', '_per_product_admin_commission_type',
            // View counts (various possible keys)
            'listing_views_count', '_listing_views_count',
            'pageview', '_pageview', 'pageviews', '_pageviews',
            'views_count', '_views_count', 'view_count', '_view_count',
            'listeo_views_count', '_listeo_views_count',
        );
        $custom_fields_content = Listeo_AI_Content_Extractor_Factory::extract_custom_fields($listing_id, $already_extracted);
        if (!empty($custom_fields_content)) {
            $structured_content .= "CUSTOM_FIELDS: " . $custom_fields_content . ". ";
        }

        // Cap at 8000 chars - listings get single embedding, no chunking
        $structured_content = trim($structured_content);
        if (strlen($structured_content) > 8000) {
            $structured_content = substr($structured_content, 0, 8000);
        }

        return $structured_content;
    }

    /**
     * Get primary category for a listing with intelligent fallback
     */
    private function get_primary_category($listing_id) {
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
            return $categories[0];
        }

        $general_categories = wp_get_post_terms($listing_id, 'listing_category', array('fields' => 'names'));
        if (!is_wp_error($general_categories) && !empty($general_categories)) {
            return $general_categories[0];
        }

        return '';
    }

    /**
     * Get structured location information
     */
    private function get_structured_location($listing_id) {
        $location_parts = array();

        $friendly_address = get_post_meta($listing_id, '_friendly_address', true);
        if ($friendly_address) {
            return $friendly_address;
        }

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

        $regions = wp_get_post_terms($listing_id, 'region', array('fields' => 'names'));
        if (!is_wp_error($regions) && !empty($regions)) {
            $location_parts[] = $regions[0];
        }

        return implode(', ', array_filter($location_parts));
    }

    /**
     * Get contact information
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
     */
    private function get_verified_amenities($listing_id) {
        $amenities = array();

        $features = get_post_meta($listing_id, '_features', true);
        if ($features && is_array($features)) {
            $amenities = array_merge($amenities, $features);
        }

        $feature_terms = wp_get_post_terms($listing_id, 'listing_feature', array('fields' => 'names'));
        if (!is_wp_error($feature_terms) && !empty($feature_terms)) {
            $amenities = array_merge($amenities, $feature_terms);
        }

        $amenities = array_filter(array_unique($amenities));

        return implode(', ', $amenities);
    }

    /**
     * Get verified features (distinct from amenities)
     */
    private function get_verified_features($listing_id) {
        $features = array();

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
     */
    private function get_owner_description($listing_id) {
        $description_parts = array();

        $post = get_post($listing_id);
        if ($post && !empty($post->post_content)) {
            $description_parts[] = strip_tags($post->post_content);
        }

        if ($post && !empty($post->post_excerpt)) {
            $description_parts[] = strip_tags($post->post_excerpt);
        }

        $listing_description = get_post_meta($listing_id, '_listing_description', true);
        if (!empty($listing_description)) {
            $description_parts[] = strip_tags($listing_description);
        }

        $tagline = get_post_meta($listing_id, '_tagline', true);
        if (!empty($tagline)) {
            $description_parts[] = $tagline;
        }

        $full_description = implode(' ', $description_parts);
        $full_description = trim($full_description);

        // Limit to 5000 chars - leaves room for other structured data within 8k cap
        if (mb_strlen($full_description, 'UTF-8') > 5000) {
            $full_description = mb_substr($full_description, 0, 5000, 'UTF-8');
        }

        return $full_description;
    }

    /**
     * Get business details (contact, keywords, etc.)
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
     * Handles both serialized and JSON-encoded data from manual entry and Data Scraper
     */
    public function get_formatted_hours($listing_id) {
        $opening_hours = get_post_meta($listing_id, '_opening_hours', true);

        if (empty($opening_hours)) {
            return '';
        }

        // Try to decode the data - handle both serialized and JSON formats
        $hours_data = null;

        if (is_array($opening_hours)) {
            // Already an array
            $hours_data = $opening_hours;
        } elseif (is_serialized($opening_hours)) {
            // Serialized format (legacy/manual entry)
            $hours_data = maybe_unserialize($opening_hours);
        } else {
            // Try JSON decode (Data Scraper format)
            $decoded = json_decode($opening_hours, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $hours_data = $decoded;
            }
        }

        if (!is_array($hours_data) || empty($hours_data)) {
            return 'Operating hours available';
        }

        // Day names for sequential array mapping (respects WordPress start_of_week)
        $start_of_week = get_option('start_of_week', 1);
        $days_ordered = $start_of_week == 0
            ? ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
            : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        $hours_text = array();
        $has_24h = false;
        $typical_hours = true;

        foreach ($hours_data as $key => $hours) {
            // Determine day name - handle both sequential (numeric) and day-name keys
            $day = is_numeric($key) ? ($days_ordered[$key] ?? '') : $key;

            if (empty($day) || !is_array($hours)) {
                continue;
            }

            // Get opening/closing times - handle both string and array formats (split shifts)
            $opening = $hours['opening'] ?? null;
            $closing = $hours['closing'] ?? null;

            if (empty($opening) || empty($closing)) {
                continue;
            }

            // Handle array format (first element) or string format
            $opening_time = is_array($opening) ? ($opening[0] ?? '') : $opening;
            $closing_time = is_array($closing) ? ($closing[0] ?? '') : $closing;

            // Skip if closed or invalid
            if (empty($opening_time) || empty($closing_time) ||
                strtolower($opening_time) === 'closed' || strtolower($closing_time) === 'closed') {
                continue;
            }

            // Check for 24h operation
            if ($opening_time === '00:00' && ($closing_time === '23:59' || $closing_time === '24:00')) {
                $has_24h = true;
            }

            // Check for atypical hours
            $opening_hour = intval(substr($opening_time, 0, 2));
            $closing_hour = intval(substr($closing_time, 0, 2));
            if ($opening_hour < 6 || $closing_hour > 23) {
                $typical_hours = false;
            }

            $hours_text[] = ucfirst($day) . ' ' . $opening_time . '-' . $closing_time;
        }

        if (empty($hours_text)) {
            return 'Operating hours available';
        }

        if ($has_24h) {
            return '24 hour operation, ' . implode(', ', $hours_text);
        } elseif (!$typical_hours) {
            return 'Extended hours available, ' . implode(', ', $hours_text);
        } else {
            return implode(', ', $hours_text);
        }
    }

    /**
     * Get enhanced pricing information including detailed menu/pricing table
     */
    private function get_enhanced_pricing_info($listing_id) {
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

        $menu_status = get_post_meta($listing_id, '_menu_status', true);
        if ($menu_status) {
            $menu = get_post_meta($listing_id, '_menu', true);
            if (!empty($menu) && is_array($menu)) {
                $menu_items = array();

                foreach ($menu as $menu_section) {
                    $section_items = array();

                    if (isset($menu_section['menu_title']) && !empty($menu_section['menu_title'])) {
                        $section_items[] = "Section: " . $menu_section['menu_title'];
                    }

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
     * Get FAQ questions for embedding
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
     * Get FAQ answers for embedding
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
                $answer = strip_tags($faq['answer']);
                $answer = wp_trim_words($answer, 30);
                $answers[] = $answer;
            }
        }

        return !empty($answers) ? implode(', ', $answers) : '';
    }

    /**
     * Get positive AI review highlights for embedding (exclude negative sentiment)
     */
    private function get_positive_review_highlights($listing_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_review_summaries';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return '';
        }

        $summary_row = $wpdb->get_row($wpdb->prepare(
            "SELECT summary_data FROM {$table_name} WHERE listing_id = %d",
            $listing_id
        ));

        if (!$summary_row || empty($summary_row->summary_data)) {
            return '';
        }

        $summary_data = json_decode($summary_row->summary_data, true);
        if (!isset($summary_data['summaries']) || !is_array($summary_data['summaries'])) {
            return '';
        }

        $positive_highlights = array();
        foreach ($summary_data['summaries'] as $item) {
            if (isset($item['sentiment']) && $item['sentiment'] === 'positive') {
                $highlight_parts = array();

                if (isset($item['title']) && !empty($item['title'])) {
                    $highlight_parts[] = $item['title'];
                }

                if (isset($item['description']) && !empty($item['description'])) {
                    $description = strip_tags($item['description']);
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
     */
    private function get_raw_reviews_for_embedding($listing_id) {
        $review_content = array();

        // Get WordPress Reviews (max 5, rating >= 3.0)
        $wp_comments = get_comments(array(
            'post_id' => $listing_id,
            'status' => 'approve',
            'type__in' => array('comment', 'review'),
            'number' => 10,
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ));

        $wp_count = 0;
        foreach ($wp_comments as $comment) {
            if ($wp_count >= 5) break;

            if (!empty($comment->comment_content) && strlen($comment->comment_content) > 15) {
                $rating = get_comment_meta($comment->comment_ID, 'listeo-review-rating', true);

                if (!empty($rating) && floatval($rating) < 3.0) {
                    continue;
                }

                $content = strip_tags($comment->comment_content);
                $content = preg_replace('/\s+/', ' ', $content);
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
                if ($google_count >= 5) break;

                if (!empty($review['text']) && strlen($review['text']) > 15) {
                    $rating = isset($review['rating']) ? floatval($review['rating']) : null;

                    if (!empty($rating) && $rating < 3.0) {
                        continue;
                    }

                    $content = strip_tags($review['text']);
                    $content = preg_replace('/\s+/', ' ', $content);
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
     * Get extended context for LLM (POI, Nearby Listings)
     * This is NOT used for embeddings - only for "talk about this listing" and similar LLM contexts
     *
     * @param int $listing_id
     * @return string Extended context string
     */
    public function get_extended_context($listing_id) {
        $parts = [];

        // Points of Interest (from Listeo POI plugin cache)
        $poi_content = $this->get_points_of_interest($listing_id);
        if (!empty($poi_content)) {
            $parts[] = "POINTS_OF_INTEREST: " . $poi_content;
        }

        // Nearby Listings (from cached transients)
        $nearby_content = $this->get_nearby_listings($listing_id);
        if (!empty($nearby_content)) {
            $parts[] = "NEARBY_LISTINGS: " . $nearby_content;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Get Points of Interest from Listeo POI plugin cache
     *
     * @param int $listing_id
     * @return string
     */
    private function get_points_of_interest($listing_id) {
        // Check if Listeo POI plugin is active
        if (!class_exists('Listeo_POI_Cache') || !class_exists('Listeo_POI')) {
            return '';
        }

        $cache = new \Listeo_POI_Cache();
        $categories = \Listeo_POI::get_poi_categories();

        // Get listing coordinates
        $lat = get_post_meta($listing_id, '_geolocation_lat', true);
        $lng = get_post_meta($listing_id, '_geolocation_long', true);

        if (empty($lat) || empty($lng)) {
            return '';
        }

        $coordinates = $lat . ',' . $lng;
        $poi_parts = [];

        foreach ($categories as $key => $category) {
            if (empty($category['enabled'])) {
                continue;
            }

            $cached_places = $cache->get_cached_poi($listing_id, $key, $coordinates);

            if ($cached_places !== false && !empty($cached_places)) {
                $category_label = $category['label'] ?? ucfirst($key);
                $places_list = [];

                foreach (array_slice($cached_places, 0, 3) as $place) {
                    $place_info = $place['name'];
                    if (!empty($place['walking_time'])) {
                        $place_info .= ' (' . $place['walking_time'] . ' walk)';
                    }
                    $places_list[] = $place_info;
                }

                if (!empty($places_list)) {
                    $poi_parts[] = $category_label . ': ' . implode(', ', $places_list);
                }
            }
        }

        return !empty($poi_parts) ? implode('; ', $poi_parts) : '';
    }

    /**
     * Get Nearby Listings from cached transients
     * Matches the same filtering logic as the single listing slider
     *
     * @param int $listing_id
     * @return string
     */
    private function get_nearby_listings($listing_id) {
        // Check if nearby listings feature is enabled
        if (!get_option('listeo_nearby_listings_status')) {
            return '';
        }

        if (!function_exists('listeo_get_cached_nearby_listings')) {
            return '';
        }

        $radius = get_option('listeo_nearby_listings_radius', 50);
        $unit = get_option('listeo_nearby_listings_unit', 'km');
        $taxonomy = get_option('listeo_nearby_listings_taxonomy', 'all');
        $limit = get_option('listeo_nearby_listings_limit', 6);

        // Build additional args to match slider filtering
        $additional_args = [];

        if ($taxonomy === 'listing_type') {
            $current_listing_type = get_post_meta($listing_id, '_listing_type', true);
            if ($current_listing_type) {
                $additional_args['meta_query'] = [
                    [
                        'key' => '_listing_type',
                        'value' => $current_listing_type,
                        'compare' => '='
                    ]
                ];
            }
        } elseif ($taxonomy === 'listing_category+region') {
            $current_listing_type = get_post_meta($listing_id, '_listing_type', true);
            $category_taxonomy = 'listing_category';

            if ($current_listing_type && function_exists('listeo_core_get_taxonomy_for_listing_type')) {
                $category_taxonomy = listeo_core_get_taxonomy_for_listing_type($current_listing_type);
            }

            $category_terms = get_the_terms($listing_id, $category_taxonomy);
            $region_terms = get_the_terms($listing_id, 'region');

            if ($category_terms && $region_terms) {
                $category_term_ids = wp_list_pluck($category_terms, 'term_id');
                $region_term_ids = wp_list_pluck($region_terms, 'term_id');

                $additional_args['tax_query'] = [
                    'relation' => 'AND',
                    [
                        'taxonomy' => $category_taxonomy,
                        'field' => 'id',
                        'terms' => $category_term_ids,
                        'operator' => 'IN'
                    ],
                    [
                        'taxonomy' => 'region',
                        'field' => 'id',
                        'terms' => $region_term_ids,
                        'operator' => 'IN'
                    ]
                ];
            }
        } elseif ($taxonomy !== 'all') {
            $terms = get_the_terms($listing_id, $taxonomy);
            if ($terms) {
                $term_ids = wp_list_pluck($terms, 'term_id');
                $additional_args['tax_query'] = [
                    [
                        'taxonomy' => $taxonomy,
                        'field' => 'id',
                        'terms' => $term_ids,
                        'operator' => 'IN'
                    ]
                ];
            }
        }

        $query_limit = ($limit > 0) ? min($limit * 3, 50) : 20;
        $additional_args['posts_per_page'] = $query_limit;

        $nearby_listings = listeo_get_cached_nearby_listings($listing_id, $radius, $unit, $additional_args);

        if (empty($nearby_listings)) {
            return '';
        }

        // Apply same limit as slider
        if ($limit > 0 && count($nearby_listings) > $limit) {
            $nearby_listings = array_slice($nearby_listings, 0, $limit);
        }

        $nearby_parts = [];

        foreach ($nearby_listings as $nearby_item) {
            $nearby_post = $nearby_item['post'];
            $distance = $nearby_item['distance'];

            $nearby_parts[] = $nearby_post->post_title . ' (' . number_format($distance, 1) . $unit . ')';
        }

        return !empty($nearby_parts) ? implode(', ', $nearby_parts) : '';
    }
}
