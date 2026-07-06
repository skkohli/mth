<?php
/**
 * Listeo Detection Helper
 *
 * Detects if Listeo theme/core is active and provides utility methods
 * This allows the plugin to work universally but add Listeo-specific features when available
 *
 * @package AI_Chat_By_Purethemes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class Listeo_AI_Detection {

    /**
     * Check if Listeo theme or child theme is active
     *
     * @return bool
     */
    public static function is_listeo_theme_active() {
        $theme = wp_get_theme();
        $template = $theme->get_template();

        // Check if current theme or parent theme is Listeo
        return ($template === 'listeo' || $theme->get('Name') === 'Listeo');
    }

    /**
     * Check if Listeo Core plugin is active
     *
     * @return bool
     */
    public static function is_listeo_core_active() {
        return class_exists('Listeo_Core') || class_exists('Listeo_Core_Listing');
    }

    /**
     * Check if Listeo environment is available
     * If theme is active, Core is always active (required dependency)
     *
     * @return bool
     */
    public static function is_listeo_available() {
        return self::is_listeo_theme_active();
    }

    /**
     * Check if listing post type exists
     *
     * @return bool
     */
    public static function has_listing_post_type() {
        return post_type_exists('listing');
    }

    /**
     * Get active Listeo features
     *
     * @return array Available features
     */
    public static function get_available_features() {
        $features = array();

        if (self::is_listeo_available()) {
            $features[] = 'listings';
            $features[] = 'categories';
            $features[] = 'regions';
        }

        if (class_exists('Listeo_Core_Search')) {
            $features[] = 'advanced_search';
        }

        if (class_exists('Listeo_Core_Bookings_Calendar')) {
            $features[] = 'bookings';
        }

        if (function_exists('listeo_get_opening_hours')) {
            $features[] = 'opening_hours';
        }

        return $features;
    }

    /**
     * Log debug information about Listeo environment
     */
    public static function debug_environment() {
        if (!get_option('listeo_ai_search_debug_mode', false)) {
            return;
        }

        $theme_active = self::is_listeo_theme_active();

        error_log('=== LISTEO ENVIRONMENT DEBUG ===');
        error_log('Listeo Theme: ' . ($theme_active ? 'ACTIVE ✓' : 'NOT FOUND'));

        if ($theme_active) {
            error_log('Listeo Integration: ENABLED');
            error_log('Available Endpoints:');
            error_log('  - POST /wp-json/listeo/v1/listeo-hybrid-search');
            error_log('  - POST /wp-json/listeo/v1/listeo-listing-details');
        } else {
            error_log('Listeo Integration: DISABLED (Universal mode only)');
        }

        error_log('=== END DEBUG ===');
    }
}
