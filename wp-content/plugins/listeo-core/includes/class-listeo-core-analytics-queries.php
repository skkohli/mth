<?php
/**
 * Listeo Core Analytics Queries (Integrated with existing stats system)
 *
 * @package Listeo_Core
 * @subpackage Analytics
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Listeo_Core_Analytics_Queries {

    private static $instance = null;
    private $cache_group = 'listeo_analytics';
    private $cache_time = HOUR_IN_SECONDS;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $version = get_option('listeo_analytics_cache_version', 1);
        $this->cache_group = 'listeo_analytics_v' . $version;
    }

    /**
     * Get overview stats
     */
    public function get_overview_stats($days = 30, $listing_id = 0, $user_id = null) {
        $cache_key = "overview_stats_{$days}_{$listing_id}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        // Build query with optional user filtering
        $where = array($wpdb->prepare("s.stat_date >= %s", $date_cutoff));

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        $stats = $wpdb->get_row("
            SELECT
                SUM(CASE WHEN s.stat_id = 'visits' THEN s.stat_value ELSE 0 END) as total_views,
                SUM(CASE WHEN s.stat_id = 'unique' THEN s.stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN s.stat_id LIKE '%_click' THEN s.stat_value ELSE 0 END) as total_contacts,
                SUM(CASE WHEN s.stat_id IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as total_booking_interactions,
                COUNT(DISTINCT s.post_id) as total_listings_with_activity
            FROM {$table_name} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE {$where_sql}
        ", ARRAY_A);

        wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_time);
        return $stats;
    }

    /**
     * Get overview stats for a specific listing
     */
    public function get_overview_stats_for_listing($listing_id, $days = 30) {
        $cache_key = "overview_stats_listing_{$listing_id}_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(CASE WHEN stat_id = 'visits' THEN stat_value ELSE 0 END) as total_views,
                SUM(CASE WHEN stat_id = 'unique' THEN stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN stat_id LIKE '%_click' THEN stat_value ELSE 0 END) as total_contacts,
                SUM(CASE WHEN stat_id IN ('booking_click', 'external_booking_click') THEN stat_value ELSE 0 END) as total_booking_interactions,
                COUNT(DISTINCT post_id) as total_listings_with_activity
            FROM {$table_name}
            WHERE stat_date >= %s
                AND post_id = %d
        ", $date_cutoff, $listing_id), ARRAY_A);

        wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_time);
        return $stats;
    }

    /**
     * Get comparison data (current period vs previous period)
     */
    public function get_comparison_data($days = 30, $listing_id = 0, $user_id = null) {
        $cache_key = "comparison_data_{$days}_{$listing_id}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $current_start = date('Y-m-d', strtotime("-{$days} days"));
        $previous_start = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        // Build WHERE clauses
        $where_current = array($wpdb->prepare("s.stat_date >= %s", $current_start));
        $where_previous = array(
            $wpdb->prepare("s.stat_date >= %s", $previous_start),
            $wpdb->prepare("s.stat_date < %s", $current_start)
        );

        if ($listing_id > 0) {
            $where_current[] = $wpdb->prepare("s.post_id = %d", $listing_id);
            $where_previous[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $where_current[] = $wpdb->prepare("p.post_author = %d", $user_id);
            $where_previous[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_current_sql = implode(' AND ', $where_current);
        $where_previous_sql = implode(' AND ', $where_previous);

        // Current period
        $current = $wpdb->get_row("
            SELECT
                SUM(CASE WHEN s.stat_id = 'visits' THEN s.stat_value ELSE 0 END) as total_views,
                SUM(CASE WHEN s.stat_id = 'unique' THEN s.stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN s.stat_id LIKE '%_click' AND s.stat_id NOT IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as total_contacts,
                SUM(CASE WHEN s.stat_id IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as total_bookings
            FROM {$table_name} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE {$where_current_sql}
        ", ARRAY_A);

        // Previous period
        $previous = $wpdb->get_row("
            SELECT
                SUM(CASE WHEN s.stat_id = 'visits' THEN s.stat_value ELSE 0 END) as total_views,
                SUM(CASE WHEN s.stat_id = 'unique' THEN s.stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN s.stat_id LIKE '%_click' AND s.stat_id NOT IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as total_contacts,
                SUM(CASE WHEN s.stat_id IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as total_bookings
            FROM {$table_name} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE {$where_previous_sql}
        ", ARRAY_A);

        $result = array(
            'current' => $current,
            'previous' => $previous,
            'changes' => array(
                'views' => $this->calculate_change($current['total_views'], $previous['total_views']),
                'unique_views' => $this->calculate_change($current['unique_views'], $previous['unique_views']),
                'contacts' => $this->calculate_change($current['total_contacts'], $previous['total_contacts']),
                'bookings' => $this->calculate_change($current['total_bookings'], $previous['total_bookings']),
            )
        );

        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }

    /**
     * Get comparison data for a specific listing
     */
    public function get_comparison_data_for_listing($listing_id, $days = 30) {
        $cache_key = "comparison_data_listing_{$listing_id}_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $current_start = date('Y-m-d', strtotime("-{$days} days"));
        $previous_start = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        // Current period
        $current = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(CASE WHEN stat_id = 'visits' THEN stat_value ELSE 0 END) as total_views,
                SUM(CASE WHEN stat_id = 'unique' THEN stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN stat_id LIKE '%_click' AND stat_id NOT IN ('booking_click', 'external_booking_click') THEN stat_value ELSE 0 END) as total_contacts,
                SUM(CASE WHEN stat_id IN ('booking_click', 'external_booking_click') THEN stat_value ELSE 0 END) as total_bookings
            FROM {$table_name}
            WHERE stat_date >= %s
                AND post_id = %d
        ", $current_start, $listing_id), ARRAY_A);

        // Previous period
        $previous = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(CASE WHEN stat_id = 'visits' THEN stat_value ELSE 0 END) as total_views,
                SUM(CASE WHEN stat_id = 'unique' THEN stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN stat_id LIKE '%_click' AND stat_id NOT IN ('booking_click', 'external_booking_click') THEN stat_value ELSE 0 END) as total_contacts,
                SUM(CASE WHEN stat_id IN ('booking_click', 'external_booking_click') THEN stat_value ELSE 0 END) as total_bookings
            FROM {$table_name}
            WHERE stat_date >= %s AND stat_date < %s
                AND post_id = %d
        ", $previous_start, $current_start, $listing_id), ARRAY_A);

        $result = array(
            'current' => $current,
            'previous' => $previous,
            'changes' => array(
                'views' => $this->calculate_change($current['total_views'], $previous['total_views']),
                'unique_views' => $this->calculate_change($current['unique_views'], $previous['unique_views']),
                'contacts' => $this->calculate_change($current['total_contacts'], $previous['total_contacts']),
                'bookings' => $this->calculate_change($current['total_bookings'], $previous['total_bookings']),
            )
        );

        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }

    /**
     * Get views over time (for charts)
     */
    public function get_views_over_time($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "views_over_time_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        // Build WHERE clause
        $where = array($wpdb->prepare("s.stat_date >= %s", $date_cutoff));

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        $results = $wpdb->get_results("
            SELECT
                s.stat_date as date,
                SUM(CASE WHEN s.stat_id = 'visits' THEN s.stat_value ELSE 0 END) as total_views,
                SUM(CASE WHEN s.stat_id = 'unique' THEN s.stat_value ELSE 0 END) as unique_views
            FROM {$table_name} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE {$where_sql}
            GROUP BY s.stat_date
            ORDER BY s.stat_date ASC
        ", ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get top performing listings
     */
    public function get_top_listings($limit = 10, $days = 30, $user_id = null) {
        $cache_key = "top_listings_{$limit}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        // Build WHERE clause
        $where = array(
            "p.post_type = 'listing'",
            "p.post_status = 'publish'",
            $wpdb->prepare("s.stat_date >= %s", $date_cutoff)
        );

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                p.ID,
                p.post_title,
                SUM(CASE WHEN s.stat_id = 'unique' THEN s.stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN s.stat_id LIKE '%%_click' AND s.stat_id NOT IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as contact_clicks,
                SUM(CASE WHEN s.stat_id IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as booking_clicks,
                SUM(CASE WHEN s.stat_id LIKE '%%_click' THEN s.stat_value ELSE 0 END) as engagement_clicks
            FROM {$wpdb->posts} p
            INNER JOIN {$table_name} s ON p.ID = s.post_id
            WHERE {$where_sql}
            GROUP BY p.ID
            HAVING unique_views > 0
            ORDER BY unique_views DESC
            LIMIT %d
        ", $limit), ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get all listings performance
     */
    public function get_all_listings_performance($days = 30, $limit = 0, $user_id = null) {
        $cache_key = "all_listings_{$days}_{$limit}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));
        $limit_clause = $limit > 0 ? $wpdb->prepare("LIMIT %d", $limit) : '';

        // Build WHERE clause
        $where = array(
            "p.post_type = 'listing'",
            "p.post_status = 'publish'",
            $wpdb->prepare("s.stat_date >= %s", $date_cutoff)
        );

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        $results = $wpdb->get_results("
            SELECT
                p.ID,
                p.post_title,
                SUM(CASE WHEN s.stat_id = 'unique' THEN s.stat_value ELSE 0 END) as unique_views,
                SUM(CASE WHEN s.stat_id LIKE '%%_click' AND s.stat_id NOT IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as contact_clicks,
                SUM(CASE WHEN s.stat_id IN ('booking_click', 'external_booking_click') THEN s.stat_value ELSE 0 END) as booking_clicks,
                SUM(CASE WHEN s.stat_id LIKE '%%_click' THEN s.stat_value ELSE 0 END) as engagement_clicks,
                ROUND(
                    (SUM(CASE WHEN s.stat_id LIKE '%%_click' THEN s.stat_value ELSE 0 END) /
                    NULLIF(SUM(CASE WHEN s.stat_id = 'unique' THEN s.stat_value ELSE 0 END), 0)) * 100,
                    2
                ) as conversion_rate
            FROM {$wpdb->posts} p
            INNER JOIN {$table_name} s ON p.ID = s.post_id
            WHERE {$where_sql}
            GROUP BY p.ID
            HAVING unique_views > 0
            ORDER BY unique_views DESC
            {$limit_clause}
        ", ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get contact clicks by type
     */
    public function get_contact_clicks($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "contact_clicks_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        // Build WHERE clause
        $where = array(
            "s.stat_id IN ('whatsapp_click', 'phone_click', 'email_click', 'website_click', 'contact_click')",
            $wpdb->prepare("s.stat_date >= %s", $date_cutoff)
        );

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        if ($listing_id > 0 || $user_id) {
            $results = $wpdb->get_results("
                SELECT
                    REPLACE(s.stat_id, '_click', '') as contact_method,
                    SUM(s.stat_value) as clicks,
                    SUM(s.stat_value) as unique_clicks" . ($listing_id == 0 ? ",
                    COUNT(DISTINCT s.post_id) as listings_used_on" : "") . "
                FROM {$table_name} s
                INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
                WHERE {$where_sql}
                GROUP BY s.stat_id
                HAVING clicks > 0
                ORDER BY clicks DESC
            ", ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT
                    REPLACE(stat_id, '_click', '') as contact_method,
                    SUM(stat_value) as clicks,
                    SUM(stat_value) as unique_clicks,
                    COUNT(DISTINCT post_id) as listings_used_on
                FROM {$table_name}
                WHERE stat_id IN ('whatsapp_click', 'phone_click', 'email_click', 'website_click', 'contact_click')
                    AND stat_date >= %s
                GROUP BY stat_id
                HAVING clicks > 0
                ORDER BY clicks DESC
            ", $date_cutoff), ARRAY_A);
        }

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get social media performance
     */
    public function get_social_media_stats($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "social_media_stats_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        // Build WHERE clause
        $where = array(
            "s.stat_id IN ('facebook_click', 'instagram_click', 'twitter_click', 'linkedin_click',
                           'youtube_click', 'telegram_click', 'skype_click', 'viber_click',
                           'tiktok_click', 'snapchat_click', 'pinterest_click')",
            $wpdb->prepare("s.stat_date >= %s", $date_cutoff)
        );

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        if ($listing_id > 0 || $user_id) {
            $results = $wpdb->get_results("
                SELECT
                    REPLACE(s.stat_id, '_click', '') as platform,
                    SUM(s.stat_value) as clicks" . ($listing_id == 0 ? ",
                    COUNT(DISTINCT s.post_id) as listings_used_on" : "") . "
                FROM {$table_name} s
                INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
                WHERE {$where_sql}
                GROUP BY s.stat_id
                HAVING clicks > 0
                ORDER BY clicks DESC
            ", ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT
                    REPLACE(stat_id, '_click', '') as platform,
                    SUM(stat_value) as clicks,
                    COUNT(DISTINCT post_id) as listings_used_on
                FROM {$table_name}
                WHERE stat_id IN ('facebook_click', 'instagram_click', 'twitter_click', 'linkedin_click',
                                   'youtube_click', 'telegram_click', 'skype_click', 'viber_click',
                                   'tiktok_click', 'snapchat_click', 'pinterest_click')
                    AND stat_date >= %s
                GROUP BY stat_id
                HAVING clicks > 0
                ORDER BY clicks DESC
            ", $date_cutoff), ARRAY_A);
        }

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get engagement breakdown for charts
     */
    public function get_engagement_breakdown($days = 30) {
        $cache_key = "engagement_breakdown_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        $results = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(CASE WHEN stat_id IN ('whatsapp_click', 'phone_click', 'email_click', 'website_click', 'contact_click') THEN stat_value ELSE 0 END) as contacts,
                SUM(CASE WHEN stat_id IN ('booking_click', 'external_booking_click') THEN stat_value ELSE 0 END) as bookings,
                SUM(CASE WHEN stat_id IN ('facebook_click', 'instagram_click', 'twitter_click', 'linkedin_click', 'youtube_click', 'telegram_click') THEN stat_value ELSE 0 END) as social,
                0 as engagement
            FROM {$table_name}
            WHERE stat_date >= %s
        ", $date_cutoff), ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get engagement breakdown for a specific listing
     */
    public function get_engagement_breakdown_for_listing($listing_id, $days = 30) {
        $cache_key = "engagement_breakdown_listing_{$listing_id}_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));

        $results = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(CASE WHEN stat_id IN ('whatsapp_click', 'phone_click', 'email_click', 'website_click', 'contact_click') THEN stat_value ELSE 0 END) as contacts,
                SUM(CASE WHEN stat_id IN ('booking_click', 'external_booking_click') THEN stat_value ELSE 0 END) as bookings,
                SUM(CASE WHEN stat_id IN ('facebook_click', 'instagram_click', 'twitter_click', 'linkedin_click', 'youtube_click', 'telegram_click') THEN stat_value ELSE 0 END) as social,
                0 as engagement
            FROM {$table_name}
            WHERE stat_date >= %s
                AND post_id = %d
        ", $date_cutoff, $listing_id), ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Calculate percentage change
     */
    private function calculate_change($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get booking statistics
     */
    public function get_booking_stats($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "booking_stats_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'bookings_calendar';
        $stats_table = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff_datetime = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $date_cutoff_date = date('Y-m-d', strtotime("-{$days} days"));

        // Build WHERE clause for bookings
        $where = array(
            $wpdb->prepare("b.created >= %s", $date_cutoff_datetime),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'"
        );

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        // Get booking stats from bookings_calendar
        $booking_stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN b.status IN ('confirmed', 'paid', 'completed') THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN b.status = 'waiting' THEN 1 ELSE 0 END) as waiting_bookings
            FROM {$bookings_table} b
            INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
            WHERE {$where_sql}
        ", ARRAY_A);

        // Build WHERE clause for click stats
        $stats_where = array(
            $wpdb->prepare("s.stat_date >= %s", $date_cutoff_date)
        );

        if ($listing_id > 0) {
            $stats_where[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $stats_where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $stats_where_sql = implode(' AND ', $stats_where);

        // Get booking clicks from stats table
        $click_stats = $wpdb->get_row("
            SELECT
                SUM(CASE WHEN s.stat_id = 'booking_click' THEN s.stat_value ELSE 0 END) as internal_booking_clicks,
                SUM(CASE WHEN s.stat_id = 'external_booking_click' THEN s.stat_value ELSE 0 END) as external_booking_clicks
            FROM {$stats_table} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE {$stats_where_sql}
        ", ARRAY_A);

        // Combine stats
        $stats = array_merge($booking_stats, $click_stats);

        wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_time);
        return $stats;
    }

    /**
     * Get booking comparison data (current vs previous period)
     */
    public function get_booking_comparison($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "booking_comparison_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bookings_calendar';

        $current_start = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $previous_start = date('Y-m-d H:i:s', strtotime("-" . ($days * 2) . " days"));

        // Build WHERE clauses for current period
        $where_current = array(
            $wpdb->prepare("b.created >= %s", $current_start),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'"
        );

        if ($listing_id > 0) {
            $where_current[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where_current[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_current_sql = implode(' AND ', $where_current);

        // Build WHERE clauses for previous period
        $where_previous = array(
            $wpdb->prepare("b.created >= %s", $previous_start),
            $wpdb->prepare("b.created < %s", $current_start),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'"
        );

        if ($listing_id > 0) {
            $where_previous[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where_previous[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_previous_sql = implode(' AND ', $where_previous);

        // Current period
        $current = $wpdb->get_row("
            SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN b.status IN ('confirmed', 'paid', 'completed') THEN 1 ELSE 0 END) as confirmed_bookings
            FROM {$table_name} b
            INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
            WHERE {$where_current_sql}
        ", ARRAY_A);

        // Previous period
        $previous = $wpdb->get_row("
            SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN b.status IN ('confirmed', 'paid', 'completed') THEN 1 ELSE 0 END) as confirmed_bookings
            FROM {$table_name} b
            INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
            WHERE {$where_previous_sql}
        ", ARRAY_A);

        $result = array(
            'current' => $current,
            'previous' => $previous,
            'changes' => array(
                'total_bookings' => $this->calculate_change($current['total_bookings'], $previous['total_bookings']),
                'confirmed_bookings' => $this->calculate_change($current['confirmed_bookings'], $previous['confirmed_bookings']),
            )
        );

        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }

    /**
     * Get revenue statistics from WooCommerce orders (via bookings table)
     * Supports both HPOS (wp_wc_orders) and legacy (wp_postmeta) storage
     *
     * NOTE: This method checks WooCommerce ORDER status, not booking status.
     * Only orders with 'wc-completed' status are counted as revenue (confirmed payments).
     * For Bank Transfer orders, admin must manually mark order as "Completed" for it to appear in analytics.
     */
    public function get_revenue_stats($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "revenue_stats_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'bookings_calendar';
        $hpos_table = $wpdb->prefix . 'wc_orders';

        $date_cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Build WHERE clauses - exclude cancelled/failed bookings but don't filter by booking payment status
        // We'll filter by WooCommerce order status instead
        $where = array(
            $wpdb->prepare("b.created >= %s", $date_cutoff),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'",
            "b.order_id IS NOT NULL",
            "b.order_id > 0"
        );

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        // Check if WooCommerce HPOS is enabled
        $hpos_exists = $wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'") === $hpos_table;

        if ($hpos_exists) {
            // Use HPOS tables
            // Only count completed orders (confirmed payments)
            $revenue = $wpdb->get_row("
                SELECT
                    COUNT(DISTINCT b.order_id) as total_orders,
                    SUM(CAST(o.total_amount AS DECIMAL(10,2))) as total_revenue,
                    AVG(CAST(o.total_amount AS DECIMAL(10,2))) as avg_order_value
                FROM {$bookings_table} b
                LEFT JOIN {$hpos_table} o ON b.order_id = o.id
                INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
                WHERE {$where_sql}
                    AND o.status = 'wc-completed'
            ", ARRAY_A);
        } else {
            // Use legacy postmeta
            // Only count completed orders (confirmed payments)
            $revenue = $wpdb->get_row("
                SELECT
                    COUNT(DISTINCT b.order_id) as total_orders,
                    SUM(CAST(om.meta_value AS DECIMAL(10,2))) as total_revenue,
                    AVG(CAST(om.meta_value AS DECIMAL(10,2))) as avg_order_value
                FROM {$bookings_table} b
                LEFT JOIN {$wpdb->postmeta} om ON b.order_id = om.post_id AND om.meta_key = '_order_total'
                INNER JOIN {$wpdb->posts} ord ON b.order_id = ord.ID
                INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
                WHERE {$where_sql}
                    AND ord.post_type = 'shop_order'
                    AND ord.post_status = 'wc-completed'
            ", ARRAY_A);
        }

        // Get currency
        $revenue['currency'] = get_woocommerce_currency_symbol();

        // Calculate total commission (platform revenue)
        $commission_rate = (float) get_option('listeo_commission_rate', 10); // Default 10%
        $total_revenue = floatval($revenue['total_revenue'] ?? 0);
        $revenue['total_commission'] = $total_revenue * ($commission_rate / 100);
        $revenue['commission_rate'] = $commission_rate;

        wp_cache_set($cache_key, $revenue, $this->cache_group, $this->cache_time);
        return $revenue;
    }

    /**
     * Get revenue comparison (current vs previous period)
     * Supports both HPOS (wp_wc_orders) and legacy (wp_postmeta) storage
     *
     * NOTE: This method checks WooCommerce ORDER status, not booking status.
     * Only orders with 'wc-completed' status are counted as revenue (confirmed payments).
     * For Bank Transfer orders, admin must manually mark order as "Completed" for it to appear in analytics.
     */
    public function get_revenue_comparison($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "revenue_comparison_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'bookings_calendar';
        $hpos_table = $wpdb->prefix . 'wc_orders';

        $current_start = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $previous_start = date('Y-m-d H:i:s', strtotime("-" . ($days * 2) . " days"));

        // Build WHERE clauses for current period - check WooCommerce order status, not booking status
        $where_current = array(
            $wpdb->prepare("b.created >= %s", $current_start),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'",
            "b.order_id IS NOT NULL",
            "b.order_id > 0"
        );

        if ($listing_id > 0) {
            $where_current[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where_current[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_current_sql = implode(' AND ', $where_current);

        // Build WHERE clauses for previous period - check WooCommerce order status, not booking status
        $where_previous = array(
            $wpdb->prepare("b.created >= %s", $previous_start),
            $wpdb->prepare("b.created < %s", $current_start),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'",
            "b.order_id IS NOT NULL",
            "b.order_id > 0"
        );

        if ($listing_id > 0) {
            $where_previous[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where_previous[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_previous_sql = implode(' AND ', $where_previous);

        // Check if WooCommerce HPOS is enabled
        $hpos_exists = $wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'") === $hpos_table;

        if ($hpos_exists) {
            // Current period with HPOS
            // Only count completed orders (confirmed payments)
            $current = $wpdb->get_row("
                SELECT
                    SUM(CAST(o.total_amount AS DECIMAL(10,2))) as total_revenue
                FROM {$bookings_table} b
                LEFT JOIN {$hpos_table} o ON b.order_id = o.id
                INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
                WHERE {$where_current_sql}
                    AND o.status = 'wc-completed'
            ", ARRAY_A);

            // Previous period with HPOS
            $previous = $wpdb->get_row("
                SELECT
                    SUM(CAST(o.total_amount AS DECIMAL(10,2))) as total_revenue
                FROM {$bookings_table} b
                LEFT JOIN {$hpos_table} o ON b.order_id = o.id
                INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
                WHERE {$where_previous_sql}
                    AND o.status = 'wc-completed'
            ", ARRAY_A);
        } else {
            // Current period with legacy postmeta
            // Only count completed orders (confirmed payments)
            $current = $wpdb->get_row("
                SELECT
                    SUM(CAST(om.meta_value AS DECIMAL(10,2))) as total_revenue
                FROM {$bookings_table} b
                LEFT JOIN {$wpdb->postmeta} om ON b.order_id = om.post_id AND om.meta_key = '_order_total'
                INNER JOIN {$wpdb->posts} ord ON b.order_id = ord.ID
                INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
                WHERE {$where_current_sql}
                    AND ord.post_type = 'shop_order'
                    AND ord.post_status = 'wc-completed'
            ", ARRAY_A);

            // Previous period with legacy postmeta
            $previous = $wpdb->get_row("
                SELECT
                    SUM(CAST(om.meta_value AS DECIMAL(10,2))) as total_revenue
                FROM {$bookings_table} b
                LEFT JOIN {$wpdb->postmeta} om ON b.order_id = om.post_id AND om.meta_key = '_order_total'
                INNER JOIN {$wpdb->posts} ord ON b.order_id = ord.ID
                INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
                WHERE {$where_previous_sql}
                    AND ord.post_type = 'shop_order'
                    AND ord.post_status = 'wc-completed'
            ", ARRAY_A);
        }

        $result = array(
            'current' => $current,
            'previous' => $previous,
            'changes' => array(
                'revenue' => $this->calculate_change($current['total_revenue'], $previous['total_revenue']),
            )
        );

        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }

    /**
     * Get bookings over time (for charts)
     */
    public function get_bookings_over_time($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "bookings_over_time_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bookings_calendar';

        $date_cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Build WHERE clause
        $where = array(
            $wpdb->prepare("b.created >= %s", $date_cutoff),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'"
        );

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        $results = $wpdb->get_results("
            SELECT
                DATE(b.created) as date,
                COUNT(*) as total_bookings,
                SUM(CASE WHEN b.status IN ('confirmed', 'paid', 'completed') THEN 1 ELSE 0 END) as confirmed_bookings
            FROM {$table_name} b
            INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
            WHERE {$where_sql}
            GROUP BY DATE(b.created)
            ORDER BY date ASC
        ", ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get booking status breakdown (for pie chart)
     */
    public function get_booking_status_breakdown($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "booking_status_breakdown_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'bookings_calendar';
        $stats_table = $wpdb->prefix . 'listeo_core_stats';

        $date_cutoff_datetime = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $date_cutoff_date = date('Y-m-d', strtotime("-{$days} days"));

        // Build WHERE clause for actual bookings
        $where = array(
            $wpdb->prepare("b.created >= %s", $date_cutoff_datetime),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'"
        );

        if ($listing_id > 0) {
            $where[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $where_sql = implode(' AND ', $where);

        // Get actual booking statuses
        $results = $wpdb->get_results("
            SELECT
                b.status,
                COUNT(*) as count
            FROM {$bookings_table} b
            INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
            WHERE {$where_sql}
            GROUP BY b.status
            HAVING count > 0
            ORDER BY count DESC
        ", ARRAY_A);

        // Build WHERE clause for external booking clicks
        $stats_where = array(
            "s.stat_id = 'external_booking_click'",
            $wpdb->prepare("s.stat_date >= %s", $date_cutoff_date)
        );

        if ($listing_id > 0) {
            $stats_where[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $stats_where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $stats_where_sql = implode(' AND ', $stats_where);

        // Get external booking clicks
        $external_clicks = $wpdb->get_var("
            SELECT SUM(s.stat_value)
            FROM {$stats_table} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE {$stats_where_sql}
        ");

        // Add external bookings as a separate status if there are any
        if ($external_clicks > 0) {
            $results[] = array(
                'status' => 'external_booking',
                'count' => (int) $external_clicks
            );
        }

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get booking conversion rate (booking clicks vs actual bookings)
     */
    public function get_booking_conversion_rate($listing_id = 0, $days = 30, $user_id = null) {
        $cache_key = "booking_conversion_{$listing_id}_{$days}_{$user_id}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $stats_table = $wpdb->prefix . 'listeo_core_stats';
        $bookings_table = $wpdb->prefix . 'bookings_calendar';

        $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));
        $date_cutoff_datetime = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Build WHERE clauses for stats (only internal booking_click for conversion)
        // External bookings happen on external sites, so no conversion tracking possible
        $stats_where = array(
            "s.stat_id = 'booking_click'",
            $wpdb->prepare("s.stat_date >= %s", $date_cutoff)
        );

        if ($listing_id > 0) {
            $stats_where[] = $wpdb->prepare("s.post_id = %d", $listing_id);
        }

        if ($user_id) {
            $stats_where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $stats_where_sql = implode(' AND ', $stats_where);

        // Get booking clicks from stats
        $booking_clicks = $wpdb->get_var("
            SELECT SUM(s.stat_value)
            FROM {$stats_table} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE {$stats_where_sql}
        ");

        // Build WHERE clauses for bookings
        $bookings_where = array(
            $wpdb->prepare("b.created >= %s", $date_cutoff_datetime),
            "b.type = 'reservation'",
            "b.comment != 'owner reservations'",
            "b.status NOT IN ('cancelled')"
        );

        if ($listing_id > 0) {
            $bookings_where[] = $wpdb->prepare("b.listing_id = %d", $listing_id);
        }

        if ($user_id) {
            $bookings_where[] = $wpdb->prepare("p.post_author = %d", $user_id);
        }

        $bookings_where_sql = implode(' AND ', $bookings_where);

        // Get actual bookings
        $actual_bookings = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$bookings_table} b
            INNER JOIN {$wpdb->posts} p ON b.listing_id = p.ID
            WHERE {$bookings_where_sql}
        ");

        $conversion_rate = 0;
        if ($booking_clicks > 0 && $actual_bookings > 0) {
            $conversion_rate = round(($actual_bookings / $booking_clicks) * 100, 2);
        }

        $result = array(
            'booking_clicks' => (int) $booking_clicks,
            'actual_bookings' => (int) $actual_bookings,
            'conversion_rate' => $conversion_rate
        );

        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }

    /**
     * Get message statistics
     */
    public function get_message_stats($days = 30) {
        $cache_key = "message_stats_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $conversations_table = $wpdb->prefix . 'listeo_core_conversations';
        $messages_table = $wpdb->prefix . 'listeo_core_messages';

        $date_cutoff = strtotime("-{$days} days");

        // Get message and conversation stats - use separate queries for clarity
        $total_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} WHERE created_at >= %d",
            $date_cutoff
        ));

        $messages_with_attachments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} WHERE created_at >= %d AND attachment_id IS NOT NULL",
            $date_cutoff
        ));

        $active_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT id) FROM {$conversations_table} WHERE last_update >= %d",
            $date_cutoff
        ));

        $total_conversations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$conversations_table}"
        );

        $total_messages_all_time = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$messages_table}"
        );

        $stats = array(
            'total_conversations' => (int) $active_conversations,
            'active_conversations' => (int) $active_conversations,
            'total_messages' => (int) $total_messages,
            'messages_with_attachments' => (int) $messages_with_attachments,
            'total_messages_all_time' => (int) $total_messages_all_time,
            'total_conversations_all_time' => (int) $total_conversations
        );

        wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_time);
        return $stats;
    }

    /**
     * Get message comparison data (current vs previous period)
     */
    public function get_message_comparison($days = 30) {
        $cache_key = "message_comparison_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $conversations_table = $wpdb->prefix . 'listeo_core_conversations';
        $messages_table = $wpdb->prefix . 'listeo_core_messages';

        $current_start = strtotime("-{$days} days");
        $previous_start = strtotime("-" . ($days * 2) . " days");

        // Current period
        $current_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT id) FROM {$conversations_table} WHERE last_update >= %d",
            $current_start
        ));

        $current_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} WHERE created_at >= %d",
            $current_start
        ));

        // Previous period
        $previous_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT id) FROM {$conversations_table} WHERE last_update >= %d AND last_update < %d",
            $previous_start,
            $current_start
        ));

        $previous_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} WHERE created_at >= %d AND created_at < %d",
            $previous_start,
            $current_start
        ));

        $current = array(
            'conversations' => (int) $current_conversations,
            'messages' => (int) $current_messages
        );

        $previous = array(
            'conversations' => (int) $previous_conversations,
            'messages' => (int) $previous_messages
        );

        $result = array(
            'current' => $current,
            'previous' => $previous,
            'changes' => array(
                'conversations' => $this->calculate_change($current['conversations'], $previous['conversations']),
                'messages' => $this->calculate_change($current['messages'], $previous['messages']),
            )
        );

        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }

    /**
     * Get messages over time (for charts)
     */
    public function get_messages_over_time($days = 30) {
        $cache_key = "messages_over_time_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $messages_table = $wpdb->prefix . 'listeo_core_messages';

        $date_cutoff = strtotime("-{$days} days");

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                FROM_UNIXTIME(created_at, '%%Y-%%m-%%d') as date,
                COUNT(*) as total_messages,
                COUNT(DISTINCT conversation_id) as active_conversations
            FROM {$messages_table}
            WHERE created_at >= %d
            GROUP BY FROM_UNIXTIME(created_at, '%%Y-%%m-%%d')
            ORDER BY date ASC
        ", $date_cutoff), ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get conversation statistics by referral source
     */
    public function get_conversation_by_referral($days = 30) {
        $cache_key = "conversation_by_referral_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $conversations_table = $wpdb->prefix . 'listeo_core_conversations';

        $date_cutoff = strtotime("-{$days} days");

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                CASE
                    WHEN referral = '' OR referral IS NULL THEN 'direct'
                    ELSE referral
                END as source,
                COUNT(*) as count
            FROM {$conversations_table}
            WHERE last_update >= %d
            GROUP BY source
            HAVING count > 0
            ORDER BY count DESC
            LIMIT 10
        ", $date_cutoff), ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get top users by message activity
     */
    public function get_top_message_users($limit = 10, $days = 30) {
        $cache_key = "top_message_users_{$limit}_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $messages_table = $wpdb->prefix . 'listeo_core_messages';

        $date_cutoff = strtotime("-{$days} days");

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                sender_id,
                COUNT(*) as message_count,
                COUNT(DISTINCT conversation_id) as conversation_count
            FROM {$messages_table}
            WHERE created_at >= %d
            GROUP BY sender_id
            HAVING message_count > 0
            ORDER BY message_count DESC
            LIMIT %d
        ", $date_cutoff, $limit), ARRAY_A);

        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_time);
        return $results;
    }

    /**
     * Get average response time for conversations
     */
    public function get_avg_response_time($days = 30) {
        $cache_key = "avg_response_time_{$days}";
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $messages_table = $wpdb->prefix . 'listeo_core_messages';

        $date_cutoff = strtotime("-{$days} days");

        // Calculate average time between messages in a conversation
        $avg_time = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(time_diff)
            FROM (
                SELECT
                    m1.conversation_id,
                    m1.created_at - m2.created_at as time_diff
                FROM {$messages_table} m1
                INNER JOIN {$messages_table} m2
                    ON m1.conversation_id = m2.conversation_id
                    AND m1.id > m2.id
                    AND m1.sender_id != m2.sender_id
                WHERE m1.created_at >= %d
                GROUP BY m1.id
            ) as time_diffs
            WHERE time_diff > 0 AND time_diff < 86400
        ", $date_cutoff));

        $result = array(
            'avg_seconds' => (int) $avg_time,
            'avg_minutes' => round($avg_time / 60, 1),
            'avg_hours' => round($avg_time / 3600, 2)
        );

        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }

    /**
     * Clear analytics cache
     */
    public function clear_cache() {
        $version = get_option('listeo_analytics_cache_version', 1);
        update_option('listeo_analytics_cache_version', $version + 1);
        $this->cache_group = 'listeo_analytics_v' . ($version + 1);
    }
}

// Initialize
Listeo_Core_Analytics_Queries::instance();
