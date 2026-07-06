<?php
/**
 * Content Extractor Factory
 *
 * Routes post types to their appropriate content extractors.
 *
 * @package Listeo_AI_Search
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Content_Extractor_Factory {

    /**
     * Get the appropriate extractor for a post type
     *
     * @param string $post_type Post type slug
     * @return object Content extractor instance
     */
    public static function get_extractor($post_type) {
        // Allow Pro plugin to provide extractors for premium post types
        $pro_extractor = apply_filters('listeo_ai_content_extractor', null, $post_type);
        if ($pro_extractor !== null) {
            return $pro_extractor;
        }

        switch ($post_type) {
            case 'listing':
                return new Listeo_AI_Content_Extractor_Listing();

            case 'post':
                return new Listeo_AI_Content_Extractor_Post();

            // Page and Product extractors are Pro features
            // Without Pro, return null extractor that produces no content
            case 'page':
            case 'product':
                // Pro plugin provides real extractors via filter above
                // Free version returns empty - no embeddings generated
                return new Listeo_AI_Content_Extractor_Null();

            case 'ai_pdf_document':
                return new Listeo_AI_Content_Extractor_PDF();

            case 'ai_content_chunk':
                return new Listeo_AI_Content_Extractor_Chunk();

            case 'ai_external_page':
                return new Listeo_AI_Content_Extractor_External_Page();

            default:
                return new Listeo_AI_Content_Extractor_Default();
        }
    }

    /**
     * Get extractor for a specific post ID
     *
     * @param int $post_id Post ID
     * @return object|false Content extractor instance or false if post not found
     */
    public static function get_extractor_for_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        return self::get_extractor($post->post_type);
    }

    /**
     * Extract content using the appropriate extractor
     *
     * @param int $post_id Post ID
     * @return string Extracted content or empty string on failure
     */
    public static function extract_content($post_id) {
        $extractor = self::get_extractor_for_post($post_id);

        if (!$extractor) {
            return '';
        }

        return $extractor->extract_content($post_id);
    }

    /**
     * Auto-detect and extract public custom fields from post meta
     *
     * Shared helper method that all extractors can use.
     * Automatically finds and extracts custom fields that pass two layers of filtering.
     *
     * @param int $post_id Post ID
     * @param array $already_extracted Array of meta keys already extracted by the specific extractor
     * @return string Formatted custom fields string or empty
     */
    public static function extract_custom_fields($post_id, $already_extracted = array()) {
        $post = get_post($post_id);
        if ($post && $post->post_type !== 'listing' && self::has_manual_custom_fields_config($post->post_type)) {
            return self::extract_selected_custom_fields($post_id, $post->post_type, $already_extracted);
        }

        $meta = get_post_meta($post_id);

        if (empty($meta)) {
            return '';
        }

        $acf_field_names = self::get_acf_field_names($post_id);

        // Layer 1: Exact WordPress core system fields to exclude
        $exclude_exact = array(
            '_edit_lock',
            '_edit_last',
            '_thumbnail_id',
            '_encloseme',
            '_pingme',
            '_wp_page_template',
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
            // View count fields (not useful for semantic search)
            'listing_views_count',
            '_listing_views_count',
        );

        // Layer 1: Pattern-based exclusions for known system/plugin fields
        $exclude_patterns = array(
            '_oembed_',      // oEmbed cache (creates many entries)
            '_wp_old_',      // Old slugs
            '_wp_attached',  // Attachment metadata
            'elementor',     // Elementor page builder
            'gdlr',          // GoodLayers page builder
            'rank_math',     // Rank Math SEO
            'yoast',         // Yoast SEO
            '_genesis',      // Genesis theme
        );

        // Allow filtering of exclusions
        $exclude_exact = apply_filters('listeo_ai_custom_fields_exclude_exact', $exclude_exact);
        $exclude_patterns = apply_filters('listeo_ai_custom_fields_exclude_patterns', $exclude_patterns);

        $custom_fields = array();
        $max_fields = 20;
        $field_count = 0;

        foreach ($meta as $key => $values) {
            if ($field_count >= $max_fields) {
                break;
            }

            // Skip fields already extracted by the specific extractor
            if (in_array($key, $already_extracted, true)) {
                continue;
            }

            // Let the ACF extractor handle ACF-managed meta, including subfields.
            if (self::is_acf_managed_meta_key($key, $acf_field_names)) {
                continue;
            }

            // Layer 1: Skip exact match system fields
            if (in_array($key, $exclude_exact, true)) {
                continue;
            }

            // Skip numeric keys
            if (is_numeric($key)) {
                continue;
            }

            // Layer 1: Skip known system field patterns
            $skip = false;
            foreach ($exclude_patterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // Layer 2: Get value and skip if empty
            $value = isset($values[0]) ? $values[0] : '';
            if (empty($value) && $value !== '0') {
                continue;
            }

            // Skip ACF field reference metadata (for example _answer => field_abc123).
            if (strpos($key, '_') === 0 && is_string($value) && strpos($value, 'field_') === 0) {
                continue;
            }

            // Layer 2: Skip serialized data (arrays, objects)
            if (is_serialized($value)) {
                continue;
            }

            // Layer 2: Skip non-string/non-numeric values
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }

            // Layer 2: Skip very long values (not useful for search)
            if (strlen($value) > 1000) {
                continue;
            }

            // Layer 2: Skip values that look like URLs, JSON, HTML, or hashes
            if (
                filter_var($value, FILTER_VALIDATE_URL) ||
                preg_match('/^[\[\{]/', trim($value)) ||  // JSON
                preg_match('/<[^>]+>/', $value) ||        // HTML
                preg_match('/^[a-f0-9]{32,}$/i', $value)  // Hashes
            ) {
                continue;
            }

            // Clean and format the value
            $value = strip_tags($value);
            $value = preg_replace('/\s+/', ' ', $value);
            $value = trim($value);

            // Layer 2: Skip if value became empty after cleaning
            if (empty($value)) {
                continue;
            }

            // Create human-readable label from meta key
            $label = self::format_field_label($key);

            $custom_fields[] = $label . ": " . $value;
            $field_count++;
        }

        $acf_fields = self::extract_acf_fields($post_id, $already_extracted, $custom_fields);
        if (!empty($acf_fields)) {
            $custom_fields = array_merge($custom_fields, $acf_fields);
        }

        // Allow filtering of extracted custom fields
        $custom_fields = apply_filters('listeo_ai_extracted_custom_fields', $custom_fields, $post_id);

        return implode('. ', $custom_fields);
    }

    /**
     * Extract only custom fields explicitly selected in Configure Custom Fields.
     *
     * @param int   $post_id Post ID.
     * @param array $already_extracted Meta keys already extracted by the specific extractor.
     * @return string Formatted custom fields string or empty.
     */
    public static function extract_configured_custom_fields($post_id, $already_extracted = array()) {
        $post = get_post($post_id);
        if (!$post || !self::has_manual_custom_fields_config($post->post_type)) {
            return '';
        }

        return self::extract_selected_custom_fields($post_id, $post->post_type, $already_extracted);
    }

    /**
     * Check whether a post type has explicit custom field config.
     *
     * Missing config means legacy auto-detection should still run. A present config
     * with an empty fields array intentionally disables custom fields for the type.
     *
     * @param string $post_type Post type.
     * @return bool
     */
    private static function has_manual_custom_fields_config($post_type) {
        $config = get_option('listeo_ai_search_custom_meta_fields', array());

        return is_array($config) && array_key_exists($post_type, $config);
    }

    /**
     * Get explicitly selected custom field keys for a post type.
     *
     * @param string $post_type Post type.
     * @return array
     */
    private static function get_selected_custom_meta_fields($post_type) {
        $config = get_option('listeo_ai_search_custom_meta_fields', array());
        if (!is_array($config) || !isset($config[$post_type]) || !is_array($config[$post_type])) {
            return array();
        }

        if (isset($config[$post_type]['fields']) && is_array($config[$post_type]['fields'])) {
            return array_values(array_filter(array_map('strval', $config[$post_type]['fields'])));
        }

        return array_values(array_filter(array_map('strval', $config[$post_type])));
    }

    /**
     * Extract only admin-selected custom fields.
     *
     * @param int $post_id Post ID.
     * @param string $post_type Post type.
     * @param array $already_extracted Meta keys already extracted by the specific extractor.
     * @return string
     */
    private static function extract_selected_custom_fields($post_id, $post_type, $already_extracted = array()) {
        $selected_keys = self::get_selected_custom_meta_fields($post_type);
        if (empty($selected_keys)) {
            return '';
        }

        $custom_fields = array();
        $seen = array();
        $max_fields = 50;
        $max_total_length = 12000;
        $total_length = 0;

        foreach ($selected_keys as $key) {
            if (count($custom_fields) >= $max_fields || $total_length >= $max_total_length) {
                break;
            }

            if (in_array($key, $already_extracted, true)) {
                continue;
            }

            $values = get_post_meta($post_id, $key, false);
            if (empty($values)) {
                continue;
            }

            $value_parts = array();
            $value_seen = array();
            foreach ($values as $value) {
                $text = self::stringify_selected_custom_field_value($value);
                if ($text === '') {
                    continue;
                }

                $normalized = self::normalize_extracted_field($text);
                if (in_array($normalized, $value_seen, true)) {
                    continue;
                }

                $value_parts[] = $text;
                $value_seen[] = $normalized;
            }

            if (empty($value_parts)) {
                continue;
            }

            $combined_value = implode('. ', array_unique($value_parts));
            if (strlen($combined_value) > 3000) {
                $combined_value = substr($combined_value, 0, 3000);
            }

            $entry = self::format_field_label($key) . ': ' . $combined_value;
            $normalized_entry = self::normalize_extracted_field($entry);
            if (in_array($normalized_entry, $seen, true)) {
                continue;
            }

            $custom_fields[] = $entry;
            $seen[] = $normalized_entry;
            $total_length += strlen($entry);
        }

        $custom_fields = apply_filters('listeo_ai_extracted_custom_fields', $custom_fields, $post_id);

        return implode('. ', $custom_fields);
    }

    /**
     * Convert selected custom field values into compact searchable text.
     *
     * @param mixed $value Meta value.
     * @param int $depth Recursion depth.
     * @return string
     */
    private static function stringify_selected_custom_field_value($value, $depth = 0) {
        if ($depth > 5 || $value === null || $value === false || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : '';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            if (is_serialized($value)) {
                return self::stringify_selected_custom_field_value(maybe_unserialize($value), $depth + 1);
            }

            $trimmed = trim($value);
            if (preg_match('/^[\[{]/', $trimmed)) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return self::stringify_selected_custom_field_value($decoded, $depth + 1);
                }
            }

            return self::clean_acf_text_value($value);
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return '';
        }

        $parts = array();
        $item_count = 0;
        foreach ($value as $key => $item) {
            if ($item_count >= 80) {
                break;
            }

            if (is_string($key) && in_array($key, array('ID', 'id', 'attachment_id', 'sizes', 'mime_type', 'filename', 'filesize'), true)) {
                continue;
            }

            $item_text = self::stringify_selected_custom_field_value($item, $depth + 1);
            if ($item_text === '') {
                continue;
            }

            if (is_string($key) && !is_numeric($key) && strpos($key, '_') !== 0) {
                $parts[] = self::format_field_label($key) . ': ' . $item_text;
            } else {
                $parts[] = $item_text;
            }

            $item_count++;
        }

        $text = implode('. ', array_unique($parts));
        if (strlen($text) > 3000) {
            $text = substr($text, 0, 3000);
        }

        return trim($text);
    }

    /**
     * Get top-level ACF field names for a post.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    private static function get_acf_field_names($post_id) {
        if (!function_exists('get_field_objects')) {
            return array();
        }

        $field_objects = get_field_objects($post_id);
        if (empty($field_objects) || !is_array($field_objects)) {
            return array();
        }

        $field_names = array();
        foreach ($field_objects as $field_object) {
            if (is_array($field_object) && !empty($field_object['name'])) {
                $field_names[] = $field_object['name'];
            }
        }

        return array_unique($field_names);
    }

    /**
     * Check whether a meta key is owned by an ACF field.
     *
     * @param string $key Meta key.
     * @param array $acf_field_names Top-level ACF field names.
     * @return bool
     */
    private static function is_acf_managed_meta_key($key, $acf_field_names) {
        if (empty($acf_field_names)) {
            return false;
        }

        foreach ($acf_field_names as $field_name) {
            if (
                $key === $field_name ||
                $key === '_' . $field_name ||
                strpos($key, $field_name . '_') === 0 ||
                strpos($key, '_' . $field_name . '_') === 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract readable values from ACF field objects, including grouped fields.
     *
     * @param int $post_id Post ID.
     * @param array $already_extracted Meta keys already extracted by a specific extractor.
     * @param array $existing_fields Fields already extracted by the generic meta scanner.
     * @return array
     */
    private static function extract_acf_fields($post_id, $already_extracted = array(), $existing_fields = array()) {
        if (!function_exists('get_field_objects')) {
            return array();
        }

        $field_objects = get_field_objects($post_id);
        if (empty($field_objects) || !is_array($field_objects)) {
            return array();
        }

        $seen = array();
        foreach ($existing_fields as $existing_field) {
            $seen[] = self::normalize_extracted_field($existing_field);
        }

        $acf_fields = array();
        $max_fields = 30;

        foreach ($field_objects as $field_object) {
            if (count($acf_fields) >= $max_fields) {
                break;
            }

            if (empty($field_object) || !is_array($field_object)) {
                continue;
            }

            $name = isset($field_object['name']) ? $field_object['name'] : '';
            if ($name && in_array($name, $already_extracted, true)) {
                continue;
            }

            $value = isset($field_object['value']) ? $field_object['value'] : null;
            $text = self::stringify_acf_value($value);
            if ($text === '') {
                continue;
            }

            $label = isset($field_object['label']) && $field_object['label'] !== ''
                ? $field_object['label']
                : self::format_field_label($name);

            $entry = $label . ': ' . $text;
            $normalized = self::normalize_extracted_field($entry);
            if (in_array($normalized, $seen, true)) {
                continue;
            }

            $acf_fields[] = $entry;
            $seen[] = $normalized;
        }

        return $acf_fields;
    }

    /**
     * Convert ACF values into compact searchable text.
     *
     * @param mixed $value Field value.
     * @param int $depth Recursion depth.
     * @return string
     */
    private static function stringify_acf_value($value, $depth = 0) {
        if ($depth > 4 || $value === null || $value === false || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : '';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return self::clean_acf_text_value($value);
        }

        if (is_object($value)) {
            if (isset($value->post_title)) {
                return self::clean_acf_text_value($value->post_title);
            }

            if (isset($value->name)) {
                return self::clean_acf_text_value($value->name);
            }

            if (isset($value->display_name)) {
                return self::clean_acf_text_value($value->display_name);
            }

            return '';
        }

        if (!is_array($value)) {
            return '';
        }

        if (isset($value['title']) && isset($value['url'])) {
            return self::clean_acf_text_value($value['title'] . ' ' . $value['url']);
        }

        $parts = array();
        foreach ($value as $key => $item) {
            if (is_string($key) && strpos($key, '_') === 0) {
                continue;
            }

            if (is_string($key) && in_array($key, array('ID', 'id', 'url', 'sizes', 'mime_type', 'filename', 'filesize'), true)) {
                continue;
            }

            $item_text = self::stringify_acf_value($item, $depth + 1);
            if ($item_text === '') {
                continue;
            }

            if (is_string($key) && !is_numeric($key)) {
                $parts[] = self::format_field_label($key) . ': ' . $item_text;
            } else {
                $parts[] = $item_text;
            }
        }

        $text = implode('. ', array_unique($parts));
        if (strlen($text) > 3000) {
            $text = substr($text, 0, 3000);
        }

        return trim($text);
    }

    /**
     * Clean text extracted from ACF values.
     *
     * @param string $value Raw value.
     * @return string
     */
    private static function clean_acf_text_value($value) {
        $value = self::preserve_links_and_strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        if ($value === '' || preg_match('/^[a-f0-9]{32,}$/i', $value)) {
            return '';
        }

        if (strlen($value) > 2000) {
            $value = substr($value, 0, 2000);
        }

        return $value;
    }

    /**
     * Normalize extracted fields for duplicate checks.
     *
     * @param string $field Field text.
     * @return string
     */
    private static function normalize_extracted_field($field) {
        $field = strtolower(wp_strip_all_tags((string) $field));
        return preg_replace('/\s+/', ' ', trim($field));
    }

    /**
     * Convert HTML links to readable text format and strip remaining tags
     *
     * Converts <a href="URL">text</a> to "text (URL)" before stripping other HTML tags.
     * This preserves URL information for LLM context.
     *
     * @param string $content HTML content
     * @return string Cleaned content with preserved link URLs
     */
    public static function preserve_links_and_strip_tags($content) {
        if (empty($content)) {
            return '';
        }

        // FIRST: Remove script/style tags AND their content (strip_tags only removes tags, not content)
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);
        $content = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        $content = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $content);

        // Remove page builder shortcodes
        $content = preg_replace('/\[elementor[^\]]*\]/', '', $content);
        $content = preg_replace('/\[vc_[^\]]*\]/', '', $content);
        $content = preg_replace('/\[\/vc_[^\]]*\]/', '', $content);
        $content = preg_replace('/\[et_pb_[^\]]*\]/', '', $content);
        $content = preg_replace('/\[\/et_pb_[^\]]*\]/', '', $content);
        $content = preg_replace('/\[gdlr_core_[^\]]*\]/', '', $content);
        $content = preg_replace('/\[\/gdlr_core_[^\]]*\]/', '', $content);

        // Remove Flavor documentation theme shortcode TAGS (keep content inside)
        $content = preg_replace('/\[lore_alert_message[^\]]*\]/', '', $content);
        $content = preg_replace('/\[\/lore_alert_message\]/', '', $content);
        // Separators - replace with markdown horizontal rule
        $content = preg_replace('/\[lore_separator[^\]]*\]/', '---', $content);
        // Accordion items - convert to Q&A format
        $content = preg_replace_callback(
            '/\[lore_accordion_item[^\]]*title=["\']([^"\']+)["\'][^\]]*\](.*?)\[\/lore_accordion_item\]/s',
            function($matches) {
                $question = trim($matches[1]);
                $answer = trim($matches[2]);
                return "Q: {$question}\nA: {$answer}";
            },
            $content
        );
        // Clean up accordion wrapper tags if present
        $content = preg_replace('/\[lore_accordion[^\]]*\]/', '', $content);
        $content = preg_replace('/\[\/lore_accordion\]/', '', $content);

        // Convert <a href="URL">text</a> to "text (URL)"
        // Handle both single and double quotes for href attribute
        $content = preg_replace_callback(
            '/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            function($matches) {
                $url = trim($matches[1]);
                $text = trim(strip_tags($matches[2])); // Strip any nested tags in link text

                // Skip empty links or javascript/anchor links
                if (empty($url) || strpos($url, 'javascript:') === 0 || $url === '#') {
                    return $text;
                }

                // Skip if text is empty
                if (empty($text)) {
                    return '';
                }

                // Don't duplicate URL if text already contains it
                if (strpos($text, $url) !== false) {
                    return $text;
                }

                return '[' . $text . '](' . $url . ')';
            },
            $content
        );

        // Convert headings to markdown before stripping tags
        // Using ** bold ** as heading marker since newlines get collapsed later
        $content = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', ' **$1** ', $content);
        $content = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', ' **$1** ', $content);
        $content = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', ' **$1** ', $content);
        $content = preg_replace('/<h4[^>]*>(.*?)<\/h4>/is', ' **$1** ', $content);
        $content = preg_replace('/<h5[^>]*>(.*?)<\/h5>/is', ' **$1** ', $content);
        $content = preg_replace('/<h6[^>]*>(.*?)<\/h6>/is', ' **$1** ', $content);

        // Now strip remaining HTML tags
        $content = strip_tags($content);

        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }

    /**
     * Format meta key into human-readable label
     *
     * @param string $key Meta key
     * @return string Formatted label
     */
    public static function format_field_label($key) {
        // Remove leading underscore for display
        $key = ltrim($key, '_');

        // Handle camelCase
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);

        // Replace underscores and hyphens with spaces
        $key = str_replace(array('_', '-'), ' ', $key);

        // Capitalize words
        $key = ucwords(strtolower($key));

        return $key;
    }

    /**
     * Check if a post uses a page builder that stores content as shortcodes or in meta
     *
     * @param int $post_id Post ID
     * @return bool True if this post needs rendered content fetching
     */
    public static function content_needs_rendering($post_id) {
        // 1. Divi
        $divi_builder = get_post_meta($post_id, '_et_pb_use_builder', true);
        if ($divi_builder === 'on') {
            return true;
        }

        // 2. GoodLayers
        $gdlr_builder = get_post_meta($post_id, '_gdlr_core_page_builder', true);
        if (!empty($gdlr_builder)) {
            return true;
        }

        // 3. Breakdance
        $breakdance_data = get_post_meta($post_id, '_breakdance_data', true);
        if (!empty($breakdance_data)) {
            return true;
        }

        // 4. WPBakery
        $wpb_status = get_post_meta($post_id, '_wpb_vc_js_status', true);
        if ($wpb_status === 'true') {
            return true;
        }

        // 5. Oxygen
        $oxygen_data = get_post_meta($post_id, 'ct_builder_shortcodes', true);
        if (!empty($oxygen_data)) {
            return true;
        }

        // 6. Bricks
        $bricks_data = get_post_meta($post_id, '_bricks_page_content_2', true);
        if (!empty($bricks_data)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether post content contains ACF Gutenberg blocks.
     *
     * @param string $content Post content.
     * @return bool
     */
    public static function content_has_acf_blocks($content) {
        return is_string($content) && stripos($content, '<!-- wp:acf/') !== false;
    }

    /**
     * Render ACF Gutenberg blocks through WordPress content filters.
     *
     * @param WP_Post $post Post object.
     * @return string Rendered and cleaned text content.
     */
    public static function render_acf_blocks_content($post) {
        if (!$post || empty($post->post_content) || !self::content_has_acf_blocks($post->post_content)) {
            return '';
        }

        $previous_post = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
        $GLOBALS['post'] = $post;

        $rendered_content = apply_filters('the_content', $post->post_content);

        if ($previous_post) {
            $GLOBALS['post'] = $previous_post;
        } else {
            unset($GLOBALS['post']);
        }

        return self::preserve_links_and_strip_tags($rendered_content);
    }

    /**
     * Fetch rendered HTML content from a post URL
     *
     * @param int $post_id Post ID
     * @return string|false Rendered HTML content or false on failure
     */
    public static function fetch_rendered_content($post_id) {
        $post = get_post($post_id);

        if (!$post || !empty($post->post_password) || $post->post_status !== 'publish') {
            return false;
        }

        $url = get_permalink($post_id);
        if (!$url) {
            return false;
        }

        $response = wp_remote_get($url, array(
            'timeout'     => 15,
            'user-agent'  => 'AI-Chat-Search-Embeddings/1.0',
            'redirection' => 3,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Extract main content from rendered HTML
     *
     * @param string $html Full page HTML
     * @return string Extracted text content
     */
    public static function extract_from_rendered_html($html) {
        if (empty($html)) {
            return '';
        }

        $content = $html;

        // Try to find main content area (avoid headers/footers/sidebars)
        $selectors = array(
            '/<div[^>]*id=["\']main-content["\'][^>]*>(.*?)<\/div>\s*(?:<footer|<div[^>]*id=["\']footer)/is',
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<div[^>]*class=["\'][^"\']*entry-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<main[^>]*>(.*?)<\/main>/is',
            '/<body[^>]*>(.*?)<\/body>/is',
        );

        foreach ($selectors as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $content = $matches[1];
                break;
            }
        }

        return self::preserve_links_and_strip_tags($content);
    }
}
