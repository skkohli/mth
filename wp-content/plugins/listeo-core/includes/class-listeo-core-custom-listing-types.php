<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Listeo Core Custom Listing Types Management
 * 
 * Handles custom listing types database operations, migration, and management
 * 
 * @since 1.0.0
 */
class Listeo_Core_Custom_Listing_Types {

    /**
     * The single instance of the class.
     */
    private static $_instance = null;

    /**
     * Database table name for custom listing types
     */
    private $table_name;

    /**
     * Database version for tracking updates
     */
    const DB_VERSION = '2.3.0';

    /**
     * Available booking features (future-proof)
     */
    const AVAILABLE_BOOKING_FEATURES = array(
        'time_slots' => array(
            'label' => 'Time Slots Selection',
            'description' => 'Allow customers to select specific time slots (disabled will fallback to time picker)',
            'core_feature' => true,
            'preset_only' => array('single_day'),
            'conflicts_with' => array('date_range', 'tickets', 'hourly_picker')
        ),
        'date_range' => array(
            'label' => 'Date Range Selection', 
            'description' => 'Allow start and end date selection',
            'core_feature' => true,
            'preset_only' => array('date_range'),
            'conflicts_with' => array('time_slots', 'tickets')
        ),
        'hourly_picker' => array(
            'label' => 'Start/End Time Picker',
            'description' => 'Hour selection for start/end of booking (only available with Date Range or Time picker)',
            'typical_for' => array('date_range'),
            'conflicts_with' => array('time_slots', 'tickets')
        ),
        'tickets' => array(
            'label' => 'Ticket System',
            'description' => 'Event ticket types and quantities',
            'core_feature' => true,
            'preset_only' => array('tickets'),
            'conflicts_with' => array('time_slots', 'date_range', 'calendar')
        ),
        'services' => array(
            'label' => 'Add-on Services',
            'description' => 'Additional services/extras',
            'typical_for' => array('single_day', 'date_range', 'tickets'),
            // Available even when booking_type = 'none' — services can be priced and listed
            // on static listings without a full booking flow (e.g. a directory with add-on items).
            'booking_optional' => true,
        ),
        'calendar' => array(
            'label' => 'Availability Calendar',
            'description' => 'Calendar for checking availability (not for events with fixed dates)',
            'typical_for' => array('single_day', 'date_range'),
            'conflicts_with' => array('tickets')
        )
    );

    /**
     * Booking type presets (for quick setup)
     */
    const BOOKING_TYPE_PRESETS = array(
        'none' => array(
            'label' => 'No Booking',
            'description' => 'Static listings without booking functionality',
            'features' => array()
        ),
        'single_day' => array(
            'label' => 'Single Day Booking',
            'description' => 'Services with time slots and same-day booking',
            'features' => array('time_slots', 'services', 'calendar')
        ),
        'date_range' => array(
            'label' => 'Date Range Booking',
            'description' => 'Rentals with multi-day stays and time picker',
            'features' => array('date_range', 'hourly_picker', 'services', 'calendar')
        ),
        'tickets' => array(
            'label' => 'Event Tickets',
            'description' => 'Events with ticket sales and attendee management',
            'features' => array('tickets', 'services')
        ),
        // 'custom' => array(
        //     'label' => 'Custom Configuration',
        //     'description' => 'Manually select specific features',
        //     'features' => array() // User selects manually
        // )
    );

    /**
     * Default listing types (for backwards compatibility)
     */
    const DEFAULT_TYPES = array(
        array(
            'slug' => 'service',
            'name' => 'Service',
            'plural_name' => 'Services',
            'description' => 'Service-based listings with booking functionality',
            'booking_type' => 'single_day',
            'booking_features' => '["time_slots","services","calendar"]',
            'supports_opening_hours' => 1,
            'register_taxonomy' => 1,
            'menu_order' => 1,
            'is_active' => 1,
            'is_default' => 1
        ),
        array(
            'slug' => 'rental',
            'name' => 'Rental',
            'plural_name' => 'Rentals',
            'description' => 'Rental-based listings with multi-day stays',
            'booking_type' => 'date_range',
            'booking_features' => '["date_range","hourly_picker","services","calendar"]',
            'supports_opening_hours' => 0,
            'register_taxonomy' => 1,
            'menu_order' => 2,
            'is_active' => 1,
            'is_default' => 1
        ),
        array(
            'slug' => 'event',
            'name' => 'Event',
            'plural_name' => 'Events',
            'description' => 'Event-based listings with ticket sales',
            'booking_type' => 'tickets',
            'booking_features' => '["tickets","services"]',
            'supports_opening_hours' => 0,
            'register_taxonomy' => 1,
            'menu_order' => 3,
            'is_active' => 1,
            'is_default' => 1
        ),
        array(
            'slug' => 'classifieds',
            'name' => 'Classified',
            'plural_name' => 'Classifieds',
            'description' => 'Classified ad listings without booking functionality',
            'booking_type' => 'none',
            'booking_features' => '[]',
            'supports_opening_hours' => 0,
            'register_taxonomy' => 1,
            'menu_order' => 4,
            'is_active' => 1,
            'is_default' => 1
        )
    );

    /**
     * Allows for accessing single instance of class. Class should only be constructed once per call.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'listeo_listing_types';
        
        // Hook into WordPress
        add_action('plugins_loaded', array($this, 'init'));
        
        // Activation/deactivation hooks
        register_activation_hook(LISTEO_PLUGIN_DIR . 'listeo-core.php', array($this, 'activate'));
        register_deactivation_hook(LISTEO_PLUGIN_DIR . 'listeo-core.php', array($this, 'deactivate'));
    }

    /**
     * Initialize the custom listing types system
     */
    public function init() {
        // Check if we need to create/update the database
        $this->maybe_create_table();

        // Check if we need to run migration
        $this->maybe_migrate_data();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
        }

        // Cache invalidation hooks
        add_action('save_post', array($this, 'invalidate_cache_on_listing_change'), 10, 2);
        add_action('delete_post', array($this, 'invalidate_cache_on_listing_change'), 10, 2);
        add_action('wp_trash_post', array($this, 'invalidate_cache_on_listing_change'), 10, 1);
        add_action('untrash_post', array($this, 'invalidate_cache_on_listing_change'), 10, 1);
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check database version and update if needed
        $installed_ver = get_option('listeo_custom_types_db_version');
        
        if ($installed_ver != self::DB_VERSION) {
            $this->create_table();
            update_option('listeo_custom_types_db_version', self::DB_VERSION);
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_table();
        $this->migrate_default_types();
        
        // Force update default types to ensure correct booking configuration
        $this->force_update_default_types();
        
        // Set database version
        update_option('listeo_custom_types_db_version', self::DB_VERSION);
        
        // Set migration flag to completed since we've handled everything
        update_option('listeo_custom_types_migration_needed', '0');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        // Note: We don't drop the table to preserve custom data
    }

    /**
     * Create the custom listing types database table
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            slug varchar(50) NOT NULL UNIQUE,
            name varchar(100) NOT NULL,
            plural_name varchar(100) NOT NULL,
            description text,
            icon_id int(11) DEFAULT NULL,
            booking_type varchar(20) DEFAULT 'none',
            booking_features text DEFAULT NULL,
            booking_enabled tinyint(1) DEFAULT 0,
            supports_opening_hours tinyint(1) DEFAULT 1,
            register_taxonomy tinyint(1) DEFAULT 1,
            slug_translations text DEFAULT NULL,
            menu_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_active (is_active),
            KEY idx_menu_order (menu_order),
            KEY idx_booking_type (booking_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $result = dbDelta($sql);
        
        // Log the result for debugging
        
    }

    /**
     * Check if table needs to be created
     */
    public function maybe_create_table() {
        if (get_option('listeo_custom_types_table_created')) {
            return;
        }

        global $wpdb;

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");

        if (!$table_exists) {
            $this->create_table();
        }

        update_option('listeo_custom_types_table_created', true);
    }

    /**
     * Check if data migration is needed
     */
    private function maybe_migrate_data() {
        $migration_needed = get_option('listeo_custom_types_migration_needed');
        $current_db_version = get_option('listeo_custom_types_db_version');
        
        // Only run migrations if we're upgrading or initial setup
        if ($migration_needed === '1' || $current_db_version !== self::DB_VERSION) {
            global $wpdb;
            $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            
            if ($existing_count == 0) {
                $this->migrate_default_types();
            }
            
            // Check if we need to remove booking_type column (only for version upgrades)
            if (version_compare($current_db_version, '2.0.0', '<')) {
                $this->maybe_remove_booking_type_column();
                $this->maybe_add_register_taxonomy_column();
                $this->maybe_add_booking_features_columns();
                $this->force_update_default_types();
            }
            
            // Add opening hours support column (v2.1.0)
            if (version_compare($current_db_version, '2.1.0', '<')) {
                $this->maybe_add_opening_hours_column();
                $this->cleanup_opening_hours_from_booking_features();
            }
            
            // Remove redundant booking feature columns (v2.2.0)
            if (version_compare($current_db_version, '2.2.0', '<')) {
                $this->cleanup_redundant_booking_columns();
            }

            // Add slug translations column (v2.3.0)
            if (version_compare($current_db_version, '2.3.0', '<')) {
                $this->maybe_add_slug_translations_column();
            }

            update_option('listeo_custom_types_migration_needed', '0');
            update_option('listeo_custom_types_db_version', self::DB_VERSION);
        }
    }

    /**
     * Migrate default listing types to the new system
     */
    public function migrate_default_types() {
        global $wpdb;

        // Check if we already have types in the database
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        if ($existing_count > 0) {
            return; // Already migrated
        }

        // Insert default types
        foreach (self::DEFAULT_TYPES as $type_data) {
            $result = $this->insert_listing_type($type_data);
           
        }

        // Migrate existing type icons from theme options
        $this->migrate_type_icons();

        // Clear any caches
        wp_cache_flush();

        // Log migration completion
       
    }

    /**
     * Remove booking_type column if it exists (migration)
     */
    private function maybe_remove_booking_type_column() {
        global $wpdb;
        
        // Check if we already did this migration
        $migration_done = get_option('listeo_custom_types_booking_column_removed', '0');
        if ($migration_done === '1') {
            return;
        }
        
        // Check if booking_type column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'booking_type'");
        
        if (!empty($column_exists)) {
            // Remove the column
            $wpdb->query("ALTER TABLE {$this->table_name} DROP COLUMN booking_type");
            
            
        }
        
        // Mark as done
        update_option('listeo_custom_types_booking_column_removed', '1');
    }

    /**
     * Add register_taxonomy column if it doesn't exist (migration)
     */
    private function maybe_add_register_taxonomy_column() {
        global $wpdb;
        
        // Check if we already did this migration
        $migration_done = get_option('listeo_custom_types_taxonomy_column_added', '0');
        if ($migration_done === '1') {
            return;
        }
        
        // Check if register_taxonomy column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'register_taxonomy'");
        
        if (empty($column_exists)) {
            // Add the column
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN register_taxonomy tinyint(1) DEFAULT 1 AFTER supports_services");
            
           
        }
        
        // Mark as done
        update_option('listeo_custom_types_taxonomy_column_added', '1');
    }

    /**
     * Add booking type and features columns if they don't exist (migration to v2.0)
     */
    private function maybe_add_booking_features_columns() {
        global $wpdb;
        
        // Check if we already did this migration
        $migration_done = get_option('listeo_custom_types_features_columns_added', '0');
        if ($migration_done === '1') {
            return;
        }
        
        // Check if booking_type column exists
        $booking_type_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'booking_type'");
        $booking_features_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'booking_features'");
        
        if (empty($booking_type_exists)) {
            // Add booking_type column
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN booking_type varchar(20) DEFAULT 'none' AFTER icon_id");
            
            
        }
        
        if (empty($booking_features_exists)) {
            // Add booking_features column
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN booking_features text DEFAULT NULL AFTER booking_type");
            
           
        }
        
        // Migrate existing data to new format
        $this->migrate_to_feature_system();
        
        // Mark as done
        update_option('listeo_custom_types_features_columns_added', '1');
    }

    /**
     * Migrate existing boolean fields to new feature system
     */
    private function migrate_to_feature_system() {
        global $wpdb;
        
        // Get all types that need migration (booking_features is NULL OR empty)
        $types_to_migrate = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE booking_features IS NULL OR booking_features = '' OR booking_features = '[]'");
        
        foreach ($types_to_migrate as $type) {
            $features = array();
            $booking_type = 'none';
            
            // Use preset configurations for default types
            if ($type->is_default) {
                switch ($type->slug) {
                    case 'service':
                        $booking_type = 'single_day';
                        $features = array('time_slots', 'services', 'calendar');
                        break;
                    case 'rental':
                        $booking_type = 'date_range';
                        $features = array('date_range', 'hourly_picker', 'services', 'calendar');
                        break;
                    case 'event':
                        $booking_type = 'tickets';
                        $features = array('tickets', 'services');
                        break;
                    case 'classifieds':
                        $booking_type = 'none';
                        $features = array();
                        break;
                }
                
                $wpdb->update(
                    $this->table_name,
                    array(
                        'booking_type' => $booking_type,
                        'booking_features' => wp_json_encode($features)
                    ),
                    array('id' => $type->id),
                    array('%s', '%s'),
                    array('%d')
                );
            } else {
                // For custom types, use the old mapping logic
                // Map old boolean fields to new features
                // Check if old columns exist before accessing them (columns may have been removed)
                if (isset($type->supports_calendar) && $type->supports_calendar) $features[] = 'calendar';
                if (isset($type->supports_services) && $type->supports_services) $features[] = 'services';
                if (isset($type->supports_time_slots) && $type->supports_time_slots) $features[] = 'time_slots';
                
                // Determine booking type based on old settings
                if ($type->booking_enabled) {
                    if (isset($type->supports_time_slots) && $type->supports_time_slots) {
                        $booking_type = 'single_day';
                    } else {
                        $booking_type = 'custom'; // Custom configuration for non-standard setups
                    }
                }
                
                $wpdb->update(
                    $this->table_name,
                    array(
                        'booking_type' => $booking_type,
                        'booking_features' => wp_json_encode($features)
                    ),
                    array('id' => $type->id),
                    array('%s', '%s'),
                    array('%d')
                );
            }
        }
        
       
    }

    /**
     * Add supports_opening_hours column if it doesn't exist (migration to v2.1.0)
     */
    private function maybe_add_opening_hours_column() {
        global $wpdb;
        
        // Check if we already did this migration
        $migration_done = get_option('listeo_custom_types_opening_hours_column_added', '0');
        if ($migration_done === '1') {
            return;
        }
        
        // Check if supports_opening_hours column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'supports_opening_hours'");
        
        if (empty($column_exists)) {
            // Add the column
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN supports_opening_hours tinyint(1) DEFAULT 1 AFTER supports_services");
            
            // Update default types with appropriate opening hours settings
            $this->update_default_types_opening_hours();
            
            
        }
        
        // Mark as done
        update_option('listeo_custom_types_opening_hours_column_added', '1');
    }

    /**
     * Update default types with opening hours settings
     */
    private function update_default_types_opening_hours() {
        global $wpdb;
        
        $opening_hours_configs = array(
            'service' => 1,     // Services typically have opening hours
            'rental' => 0,      // Rentals may have opening hours
            'event' => 0,       // Events typically don't need opening hours (they have fixed times)
            'classifieds' => 0  // Classifieds don't need opening hours
        );

        foreach ($opening_hours_configs as $slug => $supports_opening_hours) {
            $wpdb->update(
                $this->table_name,
                array('supports_opening_hours' => $supports_opening_hours),
                array('slug' => $slug, 'is_default' => 1),
                array('%d'),
                array('%s', '%d')
            );
        }

        
    }

    /**
     * Clean up opening_hours from booking_features (migration to v2.1.0)
     */
    private function cleanup_opening_hours_from_booking_features() {
        global $wpdb;
        
        // Get all types that have opening_hours in their booking_features
        $types_with_opening_hours = $wpdb->get_results("
            SELECT id, slug, booking_features 
            FROM {$this->table_name} 
            WHERE booking_features LIKE '%opening_hours%'
        ");
        
        foreach ($types_with_opening_hours as $type) {
            $features = json_decode($type->booking_features, true);
            if (is_array($features)) {
                // Remove opening_hours from the array
                $features = array_diff($features, array('opening_hours'));
                
                // Update the database
                $wpdb->update(
                    $this->table_name,
                    array('booking_features' => wp_json_encode(array_values($features))),
                    array('id' => $type->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        
    }

    /**
     * Force seed default types (for admin/debug purposes)
     */
    public function force_seed_default_types() {
        global $wpdb;

        // Clear existing default types
        $wpdb->query("DELETE FROM {$this->table_name} WHERE is_default = 1");

        // Insert default types
        foreach (self::DEFAULT_TYPES as $type_data) {
            $result = $this->insert_listing_type($type_data);
            
        }

        // Clear cache
        wp_cache_flush();
        
        return true;
    }

    /**
     * Force update existing default types to new feature system
     */
    public function force_update_default_types() {
        global $wpdb;

        $default_configs = array(
            'service' => array(
                'booking_type' => 'single_day',
                'booking_features' => wp_json_encode(array('time_slots', 'services', 'calendar')),
            ),
            'rental' => array(
                'booking_type' => 'date_range',
                'booking_features' => wp_json_encode(array('date_range', 'hourly_picker', 'services', 'calendar')),
            ),
            'event' => array(
                'booking_type' => 'tickets',
                'booking_features' => wp_json_encode(array('tickets', 'services')),
            ),
            'classifieds' => array(
                'booking_type' => 'none',
                'booking_features' => wp_json_encode(array()),
            )
        );

        foreach ($default_configs as $slug => $config) {
            $wpdb->update(
                $this->table_name,
                $config,
                array('slug' => $slug, 'is_default' => 1),
                array('%s', '%s', '%d'),
                array('%s', '%d')
            );
        }

        // Clear cache
        $this->clear_cache();
        
       
        
        return true;
    }

    /**
     * Migrate existing type icons from theme options
     */
    private function migrate_type_icons() {
        $icon_mapping = array(
            'service' => get_option('listeo_service_type_icon'),
            'rental' => get_option('listeo_rental_type_icon'),
            'event' => get_option('listeo_event_type_icon'),
            'classifieds' => get_option('listeo_classifieds_type_icon')
        );

        foreach ($icon_mapping as $slug => $icon_id) {
            if ($icon_id) {
                $this->update_listing_type($slug, array('icon_id' => $icon_id));
            }
        }
    }

    /**
     * Insert a new listing type
     */
    public function insert_listing_type($data) {
        global $wpdb;

        $defaults = array(
            'slug' => '',
            'name' => '',
            'plural_name' => '',
            'description' => '',
            'icon_id' => null,
            'booking_type' => 'none',
            'booking_features' => '[]',
            'booking_enabled' => 0,
            'supports_opening_hours' => 1,
            'register_taxonomy' => 1,
            'slug_translations' => null,
            'menu_order' => 0,
            'is_active' => 1,
            'is_default' => 0
        );

        $data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($data['slug']) || empty($data['name'])) {
            return new WP_Error('missing_required_fields', 'Slug and name are required fields.');
        }

        // Sanitize data
        $data = $this->sanitize_type_data($data);

        // Build format array dynamically based on data types
        $format = array();
        foreach ($data as $key => $value) {
            if (in_array($key, array('icon_id', 'booking_enabled', 'supports_opening_hours', 'register_taxonomy', 'menu_order', 'is_active', 'is_default'))) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            $format
        );

        if ($result === false) {
            return new WP_Error('db_insert_error', 'Failed to insert listing type: ' . $wpdb->last_error);
        }

        $type_id = $wpdb->insert_id;

        // Clear cache
        $this->clear_cache();

        // Refresh taxonomies if post types class is available
        if (class_exists('Listeo_Core_Post_Types')) {
            Listeo_Core_Post_Types::refresh_dynamic_taxonomies();
        }

        return $type_id;
    }

    /**
     * Update an existing listing type
     */
    public function update_listing_type($slug, $data) {
        global $wpdb;

        // Sanitize data
        $data = $this->sanitize_type_data($data);

        // Build format array dynamically based on data types (matching insert_listing_type)
        $format = array();
        foreach ($data as $key => $value) {
            if (in_array($key, array('icon_id', 'booking_enabled', 'supports_opening_hours', 'register_taxonomy', 'menu_order', 'is_active', 'is_default'))) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('slug' => $slug),
            $format,
            array('%s')
        );

        if ($result === false) {
            return new WP_Error('db_update_error', 'Failed to update listing type: ' . $wpdb->last_error);
        }

        // Clear cache
        $this->clear_cache();

        // Refresh taxonomies if post types class is available
        if (class_exists('Listeo_Core_Post_Types')) {
            Listeo_Core_Post_Types::refresh_dynamic_taxonomies();
        }

        return true;
    }

    /**
     * Delete a listing type
     */
    public function delete_listing_type($slug) {
        global $wpdb;

        // Check if this is a default type
        $type = $this->get_listing_type_by_slug($slug);
        if ($type && $type->is_default) {
            return new WP_Error('cannot_delete_default', 'Default listing types cannot be deleted.');
        }

        // Check if there are any listings using this type
        $listing_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_listing_type' AND meta_value = %s
        ", $slug));

        if ($listing_count > 0) {
            return new WP_Error('type_in_use', sprintf('Cannot delete listing type. %d listings are using this type.', $listing_count));
        }

        $result = $wpdb->delete(
            $this->table_name,
            array('slug' => $slug),
            array('%s')
        );

        if ($result === false) {
            return new WP_Error('db_delete_error', 'Failed to delete listing type: ' . $wpdb->last_error);
        }

        // Clear cache
        $this->clear_cache();

        return true;
    }

    /**
     * Get all listing types
     */
    public function get_listing_types($active_only = true, $include_counts = false) {
        global $wpdb;

        $cache_key = 'listeo_listing_types_' . ($active_only ? 'active' : 'all') . ($include_counts ? '_with_counts' : '');
        $types = wp_cache_get($cache_key, 'listeo_core');

        if ($types === false) {
            $where = $active_only ? 'WHERE is_active = 1' : '';

            $sql = "SELECT * FROM {$this->table_name} {$where} ORDER BY menu_order ASC, name ASC";
            $types = $wpdb->get_results($sql);

            if ($include_counts && $types) {
                // Optimize: Get all counts in a single query instead of individual queries
                $slugs = array_map(function($type) { return $type->slug; }, $types);
                $slugs_placeholders = implode(',', array_fill(0, count($slugs), '%s'));

                // Use INNER JOIN instead of LEFT JOIN for better performance (we filter by post_status anyway)
                // This leverages the PRIMARY KEY index on posts(ID) and any existing indexes on postmeta
                $counts_sql = "
                    SELECT pm.meta_value as type_slug, COUNT(*) as listing_count
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE pm.meta_key = '_listing_type'
                    AND p.post_status = 'publish'
                    AND pm.meta_value IN ({$slugs_placeholders})
                    GROUP BY pm.meta_value
                ";

                $counts = $wpdb->get_results($wpdb->prepare($counts_sql, $slugs), OBJECT_K);

                // Assign counts to types
                foreach ($types as $type) {
                    $type->listing_count = isset($counts[$type->slug]) ? intval($counts[$type->slug]->listing_count) : 0;
                }
            }

            // Cache for longer period since listing counts don't change that frequently
            wp_cache_set($cache_key, $types, 'listeo_core', 2 * HOUR_IN_SECONDS);
        }

        return $types;
    }

    /**
     * Invalidate listing types cache when listings are created/updated/deleted
     * This ensures counts stay accurate
     */
    public function invalidate_cache_on_listing_change($post_id, $post = null) {
        // Only invalidate for listing post type
        $post_type = $post ? $post->post_type : get_post_type($post_id);
        if ($post_type !== 'listing') {
            return;
        }

        // Clear all cached versions of listing types
        wp_cache_delete('listeo_listing_types_active', 'listeo_core');
        wp_cache_delete('listeo_listing_types_all', 'listeo_core');
        wp_cache_delete('listeo_listing_types_active_with_counts', 'listeo_core');
        wp_cache_delete('listeo_listing_types_all_with_counts', 'listeo_core');
    }

    /**
     * Get a single listing type by slug
     */
    public function get_listing_type_by_slug($slug) {
        global $wpdb;

        $cache_key = 'listeo_listing_type_' . $slug;
        $type = wp_cache_get($cache_key, 'listeo_core');

        if ($type === false) {
            $type = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$this->table_name} 
                WHERE slug = %s AND is_active = 1
            ", $slug));

            wp_cache_set($cache_key, $type, 'listeo_core', HOUR_IN_SECONDS);
        }

        return $type;
    }

    /**
     * Get listing type slugs only
     */
    public function get_listing_type_slugs($active_only = true) {
        $types = $this->get_listing_types($active_only);
        return wp_list_pluck($types, 'slug');
    }

    /**
     * Check if a listing type exists
     */
    public function listing_type_exists($slug) {
        return $this->get_listing_type_by_slug($slug) !== null;
    }

    /**
     * Sanitize listing type data
     */
    private function sanitize_type_data($data) {
        $sanitized = array();

        if (isset($data['slug'])) {
            $sanitized['slug'] = sanitize_title($data['slug']);
        }

        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['plural_name'])) {
            $sanitized['plural_name'] = sanitize_text_field($data['plural_name']);
        }

        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['icon_id'])) {
            $sanitized['icon_id'] = absint($data['icon_id']);
        }

        if (isset($data['booking_type'])) {
            $allowed_types = array_keys(self::BOOKING_TYPE_PRESETS);
            $sanitized['booking_type'] = in_array($data['booking_type'], $allowed_types) ? $data['booking_type'] : 'none';
        }

        if (isset($data['booking_features'])) {
            if (is_array($data['booking_features'])) {
                // Use the filtered list so plugin-registered features survive sanitisation.
                $available_features = array_keys(self::get_available_booking_features());
                $valid_features = array_intersect($data['booking_features'], $available_features);
                $sanitized['booking_features'] = wp_json_encode($valid_features);
            } else {
                $sanitized['booking_features'] = sanitize_text_field($data['booking_features']);
            }
        }

        // Slug translations (JSON format)
        if (isset($data['slug_translations'])) {
            if (is_array($data['slug_translations'])) {
                // Sanitize each translation
                $sanitized_translations = array();
                foreach ($data['slug_translations'] as $lang => $slug) {
                    $lang_code = sanitize_text_field($lang);
                    $slug_value = sanitize_title($slug);
                    if (!empty($slug_value)) {
                        $sanitized_translations[$lang_code] = $slug_value;
                    }
                }
                $sanitized['slug_translations'] = wp_json_encode($sanitized_translations);
            } else if (is_string($data['slug_translations'])) {
                // Already JSON string
                $sanitized['slug_translations'] = $data['slug_translations'];
            }
        }

        // Boolean fields
        $boolean_fields = array('booking_enabled', 'supports_opening_hours', 'register_taxonomy', 'is_active', 'is_default');
        foreach ($boolean_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = (bool) $data[$field] ? 1 : 0;
            }
        }

        if (isset($data['menu_order'])) {
            $sanitized['menu_order'] = absint($data['menu_order']);
        }

        return $sanitized;
    }

    /**
     * Clear cache for listing types
     */
    public function clear_cache() {
        wp_cache_delete('listeo_listing_types_active', 'listeo_core');
        wp_cache_delete('listeo_listing_types_all', 'listeo_core');
        wp_cache_delete('listeo_listing_types_active_with_counts', 'listeo_core');
        wp_cache_delete('listeo_listing_types_all_with_counts', 'listeo_core');
        
        // Clear individual type caches - get directly from database to avoid recursion
        global $wpdb;
        $slugs = $wpdb->get_col("SELECT slug FROM {$this->table_name}");
        if ($slugs) {
            foreach ($slugs as $slug) {
                wp_cache_delete('listeo_listing_type_' . $slug, 'listeo_core');
            }
        }
    }

    /**
     * Get the table name
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Get database version
     */
    public static function get_db_version() {
        return self::DB_VERSION;
    }

    /**
     * Get available booking features
     */
    public static function get_available_booking_features() {
        /**
         * Filter the available listing-type features.
         *
         * Lets add-ons register their own per-type capability flags so
         * site admins can toggle them from Listeo → Listing Types. Each
         * feature entry should mirror the shape of `AVAILABLE_BOOKING_FEATURES`:
         *   array(
         *     'label'         => 'Display label',
         *     'description'   => 'Help text',
         *     'typical_for'   => array( 'single_day', 'date_range' ),
         *     'conflicts_with'=> array( ... ),  // optional
         *     'preset_only'   => array( ... ),  // optional
         *   )
         *
         * Used by Listeo Booking Plus to register `bed_configuration`,
         * which gates the per-resource Bed Configuration field.
         *
         * @param array $features Map of feature key => meta.
         */
        return apply_filters( 'listeo_available_booking_features', self::AVAILABLE_BOOKING_FEATURES );
    }

    /**
     * Get booking type presets
     */
    public static function get_booking_type_presets() {
        return self::BOOKING_TYPE_PRESETS;
    }

    // Duplicate method removed - using the more complete version with feature mapping below

    /**
     * Get enabled features for a listing type
     */
    public function get_type_features($slug) {
        $type = $this->get_listing_type_by_slug($slug);
        if (!$type) {
            return array();
        }

        $features = json_decode($type->booking_features, true);
        return is_array($features) ? $features : array();
    }

    /**
     * Update booking features for a listing type
     */
    public function update_type_features($slug, $features) {
        if (!is_array($features)) {
            return false;
        }

        // Use the filtered list so plugin-registered features survive validation.
        $available_features = array_keys(self::get_available_booking_features());
        $valid_features = array_intersect($features, $available_features);

        return $this->update_listing_type($slug, array(
            'booking_features' => wp_json_encode($valid_features)
        ));
    }

    /**
     * Check if a listing type supports opening hours
     */
    public function type_supports_opening_hours($slug) {
        $type = $this->get_listing_type_by_slug($slug);
        if (!$type) {
            return false;
        }

        return (bool) $type->supports_opening_hours;
    }

    /**
     * Update opening hours support for a listing type
     */
    public function update_type_opening_hours_support($slug, $supports_opening_hours) {
        return $this->update_listing_type($slug, array(
            'supports_opening_hours' => $supports_opening_hours ? 1 : 0
        ));
    }

    /**
     * Clean up redundant booking feature columns (migration to v2.2.0)
     * Removes old booking support columns that are now handled by booking_features JSON
     */
    private function cleanup_redundant_booking_columns() {
        global $wpdb;
        
        // Check if we already did this migration
        $migration_done = get_option('listeo_custom_types_redundant_columns_removed', '0');
        if ($migration_done === '1') {
            return;
        }
        
        // List of redundant columns to remove (keeping supports_opening_hours and booking_enabled)
        $columns_to_remove = array(
            'supports_pricing',
            'supports_calendar', 
            'supports_time_slots',
            'supports_guests',
            'supports_services'
        );
        
        // Before removing columns, ensure all data is migrated to booking_features
        $this->ensure_booking_features_migration();
        
        // Remove each redundant column
        foreach ($columns_to_remove as $column) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE '{$column}'");
            
            if (!empty($column_exists)) {
                $result = $wpdb->query("ALTER TABLE {$this->table_name} DROP COLUMN {$column}");
                
                
            }
        }
        
        // Mark as done
        update_option('listeo_custom_types_redundant_columns_removed', '1');


    }

    /**
     * Add slug_translations column for storing taxonomy slug translations per language
     */
    private function maybe_add_slug_translations_column() {
        global $wpdb;

        // Check if we already did this migration
        $migration_done = get_option('listeo_custom_types_slug_translations_added', '0');
        if ($migration_done === '1') {
            return;
        }

        // Check if slug_translations column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'slug_translations'");

        if (empty($column_exists)) {
            // Add the column - TEXT type for JSON storage
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN slug_translations TEXT DEFAULT NULL AFTER register_taxonomy");
        }

        // Mark as done
        update_option('listeo_custom_types_slug_translations_added', '1');
    }

    /**
     * Ensure all booking feature data is properly migrated from old columns to booking_features JSON
     */
    private function ensure_booking_features_migration() {
        global $wpdb;
        
        // Get all types that might need migration
        $types = $wpdb->get_results("SELECT * FROM {$this->table_name}");
        
        foreach ($types as $type) {
            // Skip if booking_features already has data
            $current_features = json_decode($type->booking_features, true);
            if (!empty($current_features)) {
                continue;
            }
            
            // Build features array from old columns
            $features = array();
            
            // Check if old columns exist before accessing them (columns may have been removed)
            if (isset($type->supports_calendar) && !empty($type->supports_calendar)) $features[] = 'calendar';  
            if (isset($type->supports_time_slots) && !empty($type->supports_time_slots)) $features[] = 'time_slots';
            if (isset($type->supports_services) && !empty($type->supports_services)) $features[] = 'services';
            
            // Determine booking type based on old settings
            $booking_type = 'disabled';
            if (!empty($type->booking_enabled)) {
                if (isset($type->supports_time_slots) && !empty($type->supports_time_slots)) {
                    $booking_type = 'single_day';
                } elseif (isset($type->supports_calendar) && !empty($type->supports_calendar)) {
                    $booking_type = 'date_range';
                } else {
                    $booking_type = 'custom';
                }
            }
            
            // Update the record
            $wpdb->update(
                $this->table_name,
                array(
                    'booking_type' => $booking_type,
                    'booking_features' => json_encode($features)
                ),
                array('id' => $type->id),
                array('%s', '%s'),
                array('%d')
            );
            
            
        }
    }

    /**
     * Backward compatibility method - checks booking_features instead of old columns
     */
    public function type_supports_feature($slug, $feature) {
        $type = $this->get_listing_type_by_slug($slug);
        if (!$type) {
            return false;
        }
        
        $features = json_decode($type->booking_features, true);
        if (!is_array($features)) {
            $features = array();
        }
        
        // Map old feature names to new ones for backward compatibility
        $feature_mapping = array(
            'calendar' => 'calendar',
            'time_slots' => 'time_slots', 
            'services' => 'services'
        );
        
        $mapped_feature = isset($feature_mapping[$feature]) ? $feature_mapping[$feature] : $feature;
        return in_array($mapped_feature, $features);
    }
}

// Initialize the custom listing types system
function listeo_core_custom_listing_types() {
    return Listeo_Core_Custom_Listing_Types::instance();
}

// Initialize when the plugin loads
add_action('plugins_loaded', 'listeo_core_custom_listing_types', 5);