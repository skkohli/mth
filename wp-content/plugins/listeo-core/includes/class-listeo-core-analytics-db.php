<?php
/**
 * Listeo Core Analytics Database Manager
 *
 * Handles database table creation and migrations for analytics system
 *
 * @package Listeo_Core
 * @subpackage Analytics
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Core_Analytics_DB {

    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'listeo_core_analytics';

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'maybe_create_table'));
    }

    /**
     * Maybe create or update table
     */
    public function maybe_create_table() {
        $current_version = get_option('listeo_analytics_db_version', '0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->create_table();
            update_option('listeo_analytics_db_version', self::DB_VERSION);
        }
    }

    /**
     * Create analytics table
     */
    public function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            listing_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            event_category varchar(50) DEFAULT NULL,
            event_label varchar(255) DEFAULT NULL,
            event_value decimal(10,2) DEFAULT 0.00,
            user_id bigint(20) unsigned DEFAULT 0,
            session_id varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            referrer varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_listing_event (listing_id, event_type),
            KEY idx_listing_date (listing_id, created_at),
            KEY idx_event_type (event_type),
            KEY idx_event_category (event_category),
            KEY idx_created_at (created_at),
            KEY idx_session (session_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify table was created
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            error_log('Listeo Analytics: Failed to create analytics table');
        }
    }

    /**
     * Get table name with prefix
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Delete all analytics data (use with caution)
     */
    public function truncate_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("TRUNCATE TABLE {$table_name}");
    }

    /**
     * Delete old analytics data
     *
     * @param int $days Delete data older than X days
     */
    public function delete_old_data($days = 90) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result;
    }

    /**
     * Get table stats (uses existing wp_listeo_core_stats table)
     */
    public function get_table_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_core_stats';

        $stats = $wpdb->get_row("
            SELECT
                SUM(stat_value) as total_events,
                COUNT(DISTINCT post_id) as total_listings,
                COUNT(DISTINCT CONCAT(post_id, stat_date)) as total_sessions,
                MIN(stat_date) as oldest_event,
                MAX(stat_date) as newest_event
            FROM {$table_name}
        ", ARRAY_A);

        return $stats;
    }
}

// Initialize
Listeo_Core_Analytics_DB::instance();
