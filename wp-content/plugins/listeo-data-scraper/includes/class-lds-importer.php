<?php
class LDS_Importer {

    /**
     * Create a new listing with support for regions, features and custom listing type
     *
     * @param array $data Listing data from Google Places
     * @param array $category_ids Array of category term IDs to assign
     * @param array $region_ids Array of region term IDs to assign (optional)
     * @param string $listing_type Listing type to assign (e.g., 'service', 'rental')
     * @param string $region_taxonomy The region taxonomy name to use
     * @param string $category_taxonomy The category taxonomy name to use
     * @param array $import_prefs Import preferences for selective data import
     * @param array $feature_ids Array of feature term IDs to assign (optional)
     * @return int|false Post ID on success, false on failure
     */
    public function create_listing($data, $category_ids = array(), $region_ids = array(), $listing_type = 'service', $region_taxonomy = 'listing_region', $category_taxonomy = 'listing_category', $import_prefs = array(), $feature_ids = array()) {
        // Set default import preferences (all enabled if not provided)
        $import_prefs = array_merge(array(
            'import_phone' => 1,
            'import_website' => 1,
            'import_socials' => 1,
            'import_hours' => 1,
            'import_place_id' => 1,
            'import_photos' => 1
        ), $import_prefs);

        lds_log($data, 'Final data received by create_listing() method');
        lds_log($import_prefs, 'Import preferences for this listing');
        
        $existing_posts = get_posts([
            'post_type' => 'listing',
            'meta_key' => '_place_id',
            'meta_value' => $data['place_id'],
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        
        if (!empty($existing_posts)) {
            // This is a final safeguard, the main duplicate check is now in the AJAX handler.
            return false;
        }

        $post_data = [
            'post_title' => wp_strip_all_tags($data['name']),
            'post_content' => wp_kses_post($data['description']),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'post_type' => 'listing'
        ];
        
        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            lds_log('Failed to create post. WP_Error: ' . $post_id->get_error_message(), 'POST CREATION FAILED');
            return false;
        }

        // --- Standard Meta Data ---
        // ALWAYS save Place ID (required for duplicate detection and Google Reviews)
        // The import_place_id preference now controls whether reviews are fetched, not whether place_id is saved
        update_post_meta($post_id, '_place_id', sanitize_text_field($data['place_id']));

        update_post_meta($post_id, '_address', sanitize_text_field($data['address']));
        update_post_meta($post_id, '_friendly_address', sanitize_text_field($data['address']));
        update_post_meta($post_id, '_geolocation_lat', sanitize_text_field($data['lat']));
        update_post_meta($post_id, '_geolocation_long', sanitize_text_field($data['lng']));
        update_post_meta($post_id, 'listeo-avg-rating', sanitize_text_field($data['rating']));

        // --- NEW: Use the provided listing type instead of hardcoded 'service' ---
        update_post_meta($post_id, '_listing_type', sanitize_text_field($listing_type));

        // --- Smart Social & Contact Info Sorting ---
        $website_url = $data['website'] ?? '';
        $phone_number = $data['phone_number'] ?? '';

        // Initialize all fields to empty strings
        update_post_meta($post_id, '_website', '');
        update_post_meta($post_id, '_facebook', '');
        update_post_meta($post_id, '_twitter', '');
        update_post_meta($post_id, '_youtube', '');
        update_post_meta($post_id, '_instagram', '');
        update_post_meta($post_id, '_whatsapp', '');
        update_post_meta($post_id, '_phone', '');

        // Sort the website URL (only if website or socials import is enabled)
        if (!empty($website_url)) {
            $is_social_media = (strpos($website_url, 'facebook.com') !== false ||
                               strpos($website_url, 'instagram.com') !== false ||
                               strpos($website_url, 'twitter.com') !== false ||
                               strpos($website_url, 'youtube.com') !== false);

            if ($is_social_media && $import_prefs['import_socials']) {
                // Import social media links if enabled
                if (strpos($website_url, 'facebook.com') !== false) {
                    update_post_meta($post_id, '_facebook', esc_url_raw($website_url));
                } elseif (strpos($website_url, 'instagram.com') !== false) {
                    update_post_meta($post_id, '_instagram', esc_url_raw($website_url));
                } elseif (strpos($website_url, 'twitter.com') !== false) {
                    update_post_meta($post_id, '_twitter', esc_url_raw($website_url));
                } elseif (strpos($website_url, 'youtube.com') !== false) {
                    update_post_meta($post_id, '_youtube', esc_url_raw($website_url));
                }
            } elseif (!$is_social_media && $import_prefs['import_website']) {
                // Import regular website if enabled
                update_post_meta($post_id, '_website', esc_url_raw($website_url));
            }
        }

        // Sort the phone number (only if phone import is enabled)
        if (!empty($phone_number) && $import_prefs['import_phone']) {
            if (strpos($phone_number, 'wa.me') !== false || strpos($phone_number, 'whatsapp') !== false) {
                update_post_meta($post_id, '_whatsapp', sanitize_text_field($phone_number));
            } else {
                // If it's not a WhatsApp link, it's a regular phone number
                update_post_meta($post_id, '_phone', sanitize_text_field($phone_number));
            }
        }

        // --- Opening Hours ---
        if (!empty($data['opening_hours']) && is_array($data['opening_hours']) && $import_prefs['import_hours']) {
            update_post_meta($post_id, '_opening_hours_status', 'on');

            // Prepare JSON array for primary _opening_hours field (Listeo's main format)
            $opening_hours_json = [];

            // Map day names to WordPress week order (respects start_of_week setting)
            // Default start_of_week is 1 (Monday), but can be 0 (Sunday)
            $start_of_week = get_option('start_of_week', 1);
            $days_ordered = $start_of_week == 0
                ? ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
                : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            foreach ($days_ordered as $day) {
                $hours = $data['opening_hours'][$day] ?? 'closed';

                if ($hours === 'closed') {
                    // Save individual day fields as empty (closed)
                    update_post_meta($post_id, "_{$day}_opening_hour", '');
                    update_post_meta($post_id, "_{$day}_closing_hour", '');

                    // Add to JSON array
                    $opening_hours_json[] = [
                        'opening' => 'Closed',
                        'closing' => 'Closed'
                    ];
                } elseif (is_array($hours) && isset($hours['opening']) && isset($hours['closing'])) {
                    // Check if opening/closing are arrays (split shifts) or strings (single period)
                    $opening_times = is_array($hours['opening']) ? $hours['opening'] : [$hours['opening']];
                    $closing_times = is_array($hours['closing']) ? $hours['closing'] : [$hours['closing']];

                    // Save individual day fields (Listeo backward compatibility)
                    update_post_meta($post_id, "_{$day}_opening_hour", $opening_times);
                    update_post_meta($post_id, "_{$day}_closing_hour", $closing_times);

                    // Add to JSON array
                    $opening_hours_json[] = [
                        'opening' => $opening_times,
                        'closing' => $closing_times
                    ];
                } else {
                    // Invalid data, treat as closed
                    update_post_meta($post_id, "_{$day}_opening_hour", '');
                    update_post_meta($post_id, "_{$day}_closing_hour", '');

                    $opening_hours_json[] = [
                        'opening' => 'Closed',
                        'closing' => 'Closed'
                    ];
                }
            }

            // Save the primary JSON format that Listeo uses
            update_post_meta($post_id, '_opening_hours', json_encode($opening_hours_json));
            lds_log('Saved opening hours in both formats - Individual day fields and JSON (_opening_hours)', 'OPENING_HOURS_SAVED');
        }

        // --- Category Assignment (multi-select) ---
        if (!empty($category_ids) && is_array($category_ids)) {
            // Filter out any zero/empty values
            $category_ids = array_filter($category_ids, function($id) {
                return $id > 0;
            });

            if (!empty($category_ids)) {
                // Use the provided category taxonomy (could be listing_category, service_category, etc.)
                lds_log("Assigning category IDs " . implode(', ', $category_ids) . " to post {$post_id} using taxonomy {$category_taxonomy}", 'CATEGORY_ASSIGNMENT');
                $result = wp_set_post_terms($post_id, $category_ids, $category_taxonomy, false);

                if (is_wp_error($result)) {
                    lds_log("Category assignment failed: " . $result->get_error_message(), 'CATEGORY_ERROR');
                } else {
                    lds_log("Categories assigned successfully: " . print_r($result, true), 'CATEGORY_SUCCESS');

                    // Verify the assignment worked
                    $assigned_categories = wp_get_post_terms($post_id, $category_taxonomy);
                    if (!is_wp_error($assigned_categories)) {
                        $category_names = array_map(function($term) { return $term->name; }, $assigned_categories);
                        lds_log("Verified assigned categories: " . implode(', ', $category_names), 'CATEGORY_VERIFY');
                    }
                }
            } else {
                lds_log("No valid category IDs provided after filtering", 'CATEGORY_SKIP');
            }
        } else {
            lds_log("No categories provided for assignment", 'CATEGORY_SKIP');
        }

        // --- Region Assignment (multi-select) ---
        if (!empty($region_ids) && is_array($region_ids)) {
            // Filter out any zero/empty values
            $region_ids = array_filter($region_ids, function($id) {
                return $id > 0;
            });

            if (!empty($region_ids)) {
                lds_log("Attempting to assign regions to post {$post_id}: " . implode(', ', $region_ids), 'REGION_ASSIGNMENT');

                // First, verify the region taxonomy exists
                if (!taxonomy_exists($region_taxonomy)) {
                    lds_log("Taxonomy {$region_taxonomy} does not exist!", 'REGION_ERROR');

                    // Try alternative taxonomy names - prioritize region (confirmed from regions importer)
                    $alternative_taxonomies = ['region', 'listing_region', 'regions', 'location', 'listing_location'];
                    foreach ($alternative_taxonomies as $alt_tax) {
                        if (taxonomy_exists($alt_tax)) {
                            lds_log("Found alternative taxonomy: {$alt_tax}", 'REGION_ALTERNATIVE');
                            $region_taxonomy = $alt_tax;
                            break;
                        }
                    }

                    if (!taxonomy_exists($region_taxonomy)) {
                        lds_log("No valid region taxonomy found at all!", 'REGION_ERROR');
                        $region_taxonomy = null;
                    }
                }

                if ($region_taxonomy) {
                    $result = wp_set_post_terms($post_id, $region_ids, $region_taxonomy, false);

                    if (is_wp_error($result)) {
                        lds_log("Region assignment failed: " . $result->get_error_message(), 'REGION_ERROR');
                    } else {
                        lds_log("Regions assigned successfully: " . print_r($result, true), 'REGION_SUCCESS');

                        // Verify the assignment worked
                        $assigned_regions = wp_get_post_terms($post_id, $region_taxonomy);
                        if (!is_wp_error($assigned_regions)) {
                            $region_names = array_map(function($term) { return $term->name; }, $assigned_regions);
                            lds_log("Verified assigned regions: " . implode(', ', $region_names), 'REGION_VERIFY');
                        }
                    }
                }
            } else {
                lds_log("No valid region IDs provided after filtering", 'REGION_SKIP');
            }
        } else {
            lds_log("No regions provided for assignment", 'REGION_SKIP');
        }

        // --- Feature Assignment ---
        if (!empty($feature_ids) && is_array($feature_ids)) {
            lds_log("Attempting to assign features to post {$post_id}: " . implode(', ', $feature_ids), 'FEATURE_ASSIGNMENT');

            if (taxonomy_exists('listing_feature')) {
                // Filter out any zero/empty values
                $feature_ids = array_filter($feature_ids, function($id) {
                    return $id > 0;
                });

                if (!empty($feature_ids)) {
                    $result = wp_set_post_terms($post_id, $feature_ids, 'listing_feature', false);

                    if (is_wp_error($result)) {
                        lds_log("Feature assignment failed: " . $result->get_error_message(), 'FEATURE_ERROR');
                    } else {
                        lds_log("Features assigned successfully: " . print_r($result, true), 'FEATURE_SUCCESS');

                        // Verify the assignment worked
                        $assigned_features = wp_get_post_terms($post_id, 'listing_feature');
                        if (!is_wp_error($assigned_features)) {
                            $feature_names = array_map(function($term) { return $term->name; }, $assigned_features);
                            lds_log("Verified assigned features: " . implode(', ', $feature_names), 'FEATURE_VERIFY');
                        }
                    }
                }
            } else {
                lds_log("listing_feature taxonomy does not exist!", 'FEATURE_ERROR');
            }
        } else {
            lds_log("No features provided for assignment", 'FEATURE_SKIP');
        }

        // --- NEW: Cache Google Reviews in Listeo Format ---
        if (!empty($data['reviews']) && is_array($data['reviews']) && $import_prefs['import_place_id']) {
            // Create the Google Places API response format that Listeo expects
            $google_places_data = [
                'result' => [
                    'reviews' => $data['reviews'],
                    'rating' => $data['rating'] ?? 0,
                    'user_ratings_total' => $data['user_ratings_total'] ?? 0,
                    'place_id' => $data['place_id'],
                    'name' => $data['name']
                ]
            ];

            // Store in the transient format that your review highlights plugin expects
            $transient_name = 'listeo_reviews_' . $post_id;
            set_transient($transient_name, $google_places_data, 30 * DAY_IN_SECONDS); // Cache for 30 days

            lds_log('Cached ' . count($data['reviews']) . ' Google reviews for post ID ' . $post_id . ' in transient: ' . $transient_name, 'REVIEWS_CACHE');
        }

        // --- Photo Import ---
        // Get the API source to determine photo import behavior
        $api_source = get_option('lds_api_source', 'google');

        // For Google Places: Check lds_enable_photo_import (TOS compliance)
        // For Outscraper: Only check user preference (no TOS restrictions)
        $should_import_photos = false;
        if ($api_source === 'google') {
            $photo_import_enabled = get_option('lds_enable_photo_import', 0);
            $should_import_photos = $photo_import_enabled && $import_prefs['import_photos'];
        } else {
            // Outscraper - no TOS restrictions, just check user preference
            $should_import_photos = $import_prefs['import_photos'];
        }

        if ($should_import_photos) {
            $storage_method = get_option('lds_photo_storage_method', 'download');

            if (!empty($data['photo_urls']) && is_array($data['photo_urls'])) {
                if ($storage_method === 'download') {
                // Original download method (for backwards compatibility)
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                
                $gallery_data = [];
                $is_first_image = true;
                $image_counter = 1;

                // Create SEO-friendly filename slug from listing name
                $listing_slug = sanitize_title($data['name']);
                if (empty($listing_slug)) {
                    $listing_slug = 'listing-' . $post_id;
                }

                foreach ($data['photo_urls'] as $photo_url) {
                    // Download the image file first and check for errors
                    $temp_file = download_url($photo_url, 15);

                    if (is_wp_error($temp_file)) {
                        lds_log('Failed to download image from URL: ' . $photo_url . ' Error: ' . $temp_file->get_error_message(), 'IMAGE DOWNLOAD FAILED');
                        continue; // Skip this image and continue with the next one
                    }

                    // Determine file extension from URL or default to jpg
                    $file_ext = pathinfo(parse_url($photo_url, PHP_URL_PATH), PATHINFO_EXTENSION);
                    if (empty($file_ext) || !in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $file_ext = 'jpg';
                    }

                    // SEO-friendly filename: listing-name-1.jpg, listing-name-2.jpg, etc.
                    $seo_filename = $listing_slug . '-' . $image_counter . '.' . $file_ext;

                    $image_id = media_handle_sideload([
                        'tmp_name' => $temp_file,
                        'name' => $seo_filename
                    ], $post_id, $data['name']);

                    if (!is_wp_error($image_id)) {
                        $image_url = wp_get_attachment_url($image_id);
                        $gallery_data[$image_id] = $image_url;
                        if ($is_first_image) {
                            set_post_thumbnail($post_id, $image_id);
                            $is_first_image = false;
                        }
                        $image_counter++;
                    } else {
                        lds_log('Image import failed. URL: ' . $photo_url . ' Error: ' . $image_id->get_error_message(), 'IMAGE IMPORT FAILED');
                    }
                    
                    // Clean up the temporary file if it still exists
                    if (file_exists($temp_file)) {
                        @unlink($temp_file);
                    }
                }

                if (!empty($gallery_data)) {
                    update_post_meta($post_id, '_gallery', $gallery_data);
                }
            } else {
                // NEW: Google server method (TOS compliant)
                $google_gallery_data = [];
                
                foreach ($data['photo_urls'] as $index => $photo_url) {
                    $google_gallery_data[] = [
                        'url' => $photo_url,
                        'attribution' => isset($data['photo_attributions'][$index]) ? $data['photo_attributions'][$index] : []
                    ];
                }
                
                if (!empty($google_gallery_data)) {
                    update_post_meta($post_id, '_gallery_google', $google_gallery_data);
                    lds_log('Stored ' . count($google_gallery_data) . ' Google photo URLs for post ID ' . $post_id, 'GOOGLE_PHOTOS');
                }
            }
            } // Close the if (!empty($data['photo_urls']) && is_array($data['photo_urls'])) check
        } else {
            lds_log('Photo import disabled by user setting - skipping photo import', 'PHOTO_IMPORT');
        }

        // --- Contact Widget Assignment ---
        // Always disabled for imported listings since Google/Outscraper APIs never provide email addresses
        update_post_meta($post_id, '_email_contact_widget', 0);

        // --- SEO Plugin Integration (Yoast SEO / Rank Math) ---
        // Save SEO meta fields if provided and an SEO plugin is active
        $seo_setting_enabled = get_option('lds_enable_yoast_seo', 0);
        $yoast_installed = defined('WPSEO_VERSION');
        $rankmath_installed = defined('RANK_MATH_VERSION') || class_exists('RankMath');

        if ($seo_setting_enabled && ($yoast_installed || $rankmath_installed)) {
            $seo_data_saved = false;

            if ($yoast_installed) {
                // Save to Yoast SEO meta fields
                if (!empty($data['seo_title'])) {
                    update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($data['seo_title']));
                    $seo_data_saved = true;
                }
                if (!empty($data['seo_description'])) {
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($data['seo_description']));
                    $seo_data_saved = true;
                }
                if (!empty($data['focus_keyphrase'])) {
                    update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($data['focus_keyphrase']));
                    $seo_data_saved = true;
                }

                if ($seo_data_saved) {
                    lds_log('Saved Yoast SEO data for post ID ' . $post_id . ': title="' . ($data['seo_title'] ?? '') . '", keyphrase="' . ($data['focus_keyphrase'] ?? '') . '"', 'YOAST_SEO');
                }
            } elseif ($rankmath_installed) {
                // Save to Rank Math meta fields
                if (!empty($data['seo_title'])) {
                    update_post_meta($post_id, 'rank_math_title', sanitize_text_field($data['seo_title']));
                    $seo_data_saved = true;
                }
                if (!empty($data['seo_description'])) {
                    update_post_meta($post_id, 'rank_math_description', sanitize_text_field($data['seo_description']));
                    $seo_data_saved = true;
                }
                if (!empty($data['focus_keyphrase'])) {
                    update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($data['focus_keyphrase']));
                    $seo_data_saved = true;
                }

                if ($seo_data_saved) {
                    lds_log('Saved Rank Math SEO data for post ID ' . $post_id . ': title="' . ($data['seo_title'] ?? '') . '", keyphrase="' . ($data['focus_keyphrase'] ?? '') . '"', 'RANKMATH_SEO');
                }
            }
        }

        lds_log('Successfully created listing with Post ID: ' . $post_id . ', Categories: ' . implode(',', $category_ids) . ', Regions: ' . implode(',', $region_ids) . ', Type: ' . $listing_type . ', Features: ' . implode(',', $feature_ids), 'POST CREATION SUCCESS');

        // Proactively fetch Google reviews if place_id exists, import_place_id is enabled, and Google reviews are enabled
        if (!empty($data['place_id']) && $import_prefs['import_place_id'] && get_option('listeo_google_reviews')) {
            $post = get_post($post_id);
            if ($post) {
                // Use the listeo_get_google_reviews function to fetch and cache reviews
                $reviews = listeo_get_google_reviews($post);

                // Log the proactive fetch for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $has_reviews = !empty($reviews) && isset($reviews['result']['reviews']) && is_array($reviews['result']['reviews']);
                    $review_count = $has_reviews ? count($reviews['result']['reviews']) : 0;
                    error_log("Listeo: Proactively fetched Google reviews for imported listing {$post_id} with place_id: {$data['place_id']} (reviews fetched: {$review_count})");
                }
            }
        } elseif (empty($data['place_id'])) {
            lds_log('No place_id provided for listing, skipping Google reviews fetch', 'GOOGLE_REVIEWS_SKIP');
        } elseif (!$import_prefs['import_place_id']) {
            lds_log('Google Place ID import disabled by user preference, skipping reviews fetch', 'GOOGLE_REVIEWS_USER_DISABLED');
        } elseif (!get_option('listeo_google_reviews')) {
            lds_log('Google reviews disabled in settings, skipping fetch', 'GOOGLE_REVIEWS_DISABLED');
        }

        return $post_id;
    }
}