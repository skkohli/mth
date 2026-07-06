<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Listeo Core Event Recurrence Calculator - Simplified Version
 */
class Listeo_Core_Event_Recurrence_New
{
    /**
     * Stores static instance of class.
     */
    protected static $_instance = null;

    /**
     * Returns static instance of class.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('save_post_listing', array($this, 'handle_recurring_event_save'), 20, 3);
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Add recurring event info to listing details
        add_action('listeo_after_listing_main_details', array($this, 'display_recurring_event_info'), 10);
        
        // Hook into posts_where to include virtual occurrences in searches
        add_filter('posts_where', array($this, 'include_virtual_occurrences_in_search'), 10, 2);
        
        // Clean up on plugin deactivation
        register_deactivation_hook(LISTEO_PLUGIN_DIR . 'listeo-core.php', array($this, 'deactivation_cleanup'));
    }

    /**
     * Initialize
     */
    public function init()
    {
        // Schedule recurring events check
        if (!wp_next_scheduled('listeo_process_recurring_events')) {
            wp_schedule_event(time(), 'daily', 'listeo_process_recurring_events');
        }
        add_action('listeo_process_recurring_events', array($this, 'process_recurring_events'));
    }

    /**
     * Handle recurring event save
     */
    public function handle_recurring_event_save($post_id, $post, $update)
    {
        // Verify if this is an auto save routine
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only process event listings
        $listing_type = get_post_meta($post_id, '_listing_type', true);
        if ($listing_type !== 'event') {
            return;
        }

        $is_recurring = get_post_meta($post_id, '_event_recurring', true);
        if ($is_recurring === 'on' || $is_recurring === '1') {
            $this->setup_recurring_event($post_id);
        } else {
            $this->remove_recurring_event($post_id);
        }
    }

    /**
     * Setup recurring event
     */
    public function setup_recurring_event($post_id)
    {
        $event_date = get_post_meta($post_id, '_event_date', true);
        $recurring_interval = get_post_meta($post_id, '_event_recurring_interval', true);

        if (empty($event_date) || empty($recurring_interval)) {
            return false;
        }

        // Store original event date for calculations
        update_post_meta($post_id, '_event_original_date', $event_date);
        
        // Mark as active recurring event
        update_post_meta($post_id, '_event_recurring_active', 'yes');

        // Generate virtual occurrence timestamps for search compatibility
        $this->generate_virtual_occurrences($post_id);

        return true;
    }

    /**
     * Generate virtual occurrence timestamps for recurring events
     * This creates multiple timestamp entries that regular search can find
     */
    public function generate_virtual_occurrences($post_id)
    {
        $event_date = get_post_meta($post_id, '_event_date', true);
        $recurring_interval = get_post_meta($post_id, '_event_recurring_interval', true);
        $recurring_end = get_post_meta($post_id, '_event_recurring_end', true);

        if (empty($event_date) || empty($recurring_interval)) {
            return false;
        }

        // Clear existing virtual occurrences
        delete_post_meta($post_id, '_event_virtual_timestamps');
        
        try {
            $current_date = new DateTime($event_date);
            $interval = new DateInterval('P' . intval($recurring_interval) . 'D');
            $end_date = !empty($recurring_end) ? new DateTime($recurring_end) : null;
            
            // Generate up to 365 days worth of future occurrences
            $max_future_date = new DateTime();
            $max_future_date->add(new DateInterval('P365D'));
            
            $virtual_timestamps = array();
            $occurrence_count = 0;
            $max_occurrences = 50; // Limit to prevent too much data
            
            // Generate future occurrences
            while ($occurrence_count < $max_occurrences) {
                // Check if we've exceeded the end date
                if ($end_date && $current_date > $end_date) {
                    break;
                }
                
                // Check if we've gone too far into the future
                if ($current_date > $max_future_date) {
                    break;
                }
                
                // Add this occurrence timestamp
                $timestamp = $current_date->getTimestamp();
                $virtual_timestamps[] = $timestamp;
                
                // Also store individual timestamp entries for easier SQL querying
                add_post_meta($post_id, '_event_virtual_timestamp', $timestamp);
                
                // Move to next occurrence
                $current_date->add($interval);
                $occurrence_count++;
            }
            
            // Store virtual timestamps as a serialized array for admin use
            if (!empty($virtual_timestamps)) {
                update_post_meta($post_id, '_event_virtual_timestamps', $virtual_timestamps);
                error_log('Generated ' . count($virtual_timestamps) . ' virtual timestamps for recurring event ' . $post_id);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error generating virtual occurrences for event ' . $post_id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove recurring event setup
     */
    public function remove_recurring_event($post_id)
    {
        delete_post_meta($post_id, '_event_original_date');
        delete_post_meta($post_id, '_event_next_recurrence');
        delete_post_meta($post_id, '_event_recurring_active');
        delete_post_meta($post_id, '_event_virtual_timestamps');
        delete_post_meta($post_id, '_event_virtual_timestamp'); // Remove all virtual timestamps
    }

    /**
     * Include virtual occurrences in date searches
     */
    public function include_virtual_occurrences_in_search($where, $query)
    {
        global $wpdb;
        
        // Only apply to event date searches
        if (!isset($_REQUEST['date_range']) || empty($_REQUEST['date_range'])) {
            return $where;
        }
        
        $listing_type = isset($_REQUEST['_listing_type']) ? $_REQUEST['_listing_type'] : '';
        if ($listing_type !== 'event') {
            return $where;
        }
        
        // Parse date range
        $date_range = sanitize_text_field($_REQUEST['date_range']);
        $dates = explode(' - ', $date_range);
        if (count($dates) != 2) {
            if (!empty($date_range)) {
                $dates = array($date_range, $date_range);
            } else {
                return $where;
            }
        }
        
        try {
            $date_start = new DateTime(trim($dates[0]));
            $date_end = new DateTime(trim($dates[1]));
            
            $start_timestamp = $date_start->getTimestamp();
            $end_timestamp = $date_end->getTimestamp();
            
            // Find existing date conditions in WHERE clause
            if (strpos($where, '_event_date_timestamp') !== false) {
                // Add OR condition to include virtual timestamps
                $virtual_condition = $wpdb->prepare(
                    " OR EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta} vm 
                        WHERE vm.post_id = {$wpdb->posts}.ID 
                        AND vm.meta_key = '_event_virtual_timestamp'
                        AND CAST(vm.meta_value AS UNSIGNED) BETWEEN %d AND %d
                    )",
                    $start_timestamp,
                    $end_timestamp
                );
                
                // Insert the OR condition after the existing date timestamp condition
                $where = preg_replace(
                    '/(AND\s*\([^)]*_event_date_timestamp[^)]*BETWEEN[^)]*\))/i',
                    '$1' . $virtual_condition,
                    $where
                );
                
                error_log('Recurring Events: Added virtual timestamp condition to WHERE clause');
            }
            
        } catch (Exception $e) {
            error_log('Recurring Events WHERE Error: ' . $e->getMessage());
        }
        
        return $where;
    }

    /**
     * Process all recurring events (daily cron job)
     */
    public function process_recurring_events()
    {
        $args = array(
            'post_type' => 'listing',
            'post_status' => array('publish', 'expired'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_listing_type',
                    'value' => 'event',
                    'compare' => '='
                ),
                array(
                    'key' => '_event_recurring_active',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );

        $recurring_events = get_posts($args);

        foreach ($recurring_events as $event) {
            $this->update_event_dates($event->ID);
            $this->generate_virtual_occurrences($event->ID); // Regenerate virtual timestamps
        }

        wp_reset_postdata();
    }

    /**
     * Update event dates for recurring events
     */
    public function update_event_dates($post_id)
    {
        $current_event_date = get_post_meta($post_id, '_event_date', true);
        $recurring_interval = get_post_meta($post_id, '_event_recurring_interval', true);
        $recurring_end = get_post_meta($post_id, '_event_recurring_end', true);

        if (empty($current_event_date) || empty($recurring_interval)) {
            return false;
        }

        try {
            $event_date = new DateTime($current_event_date);
            $now = new DateTime();

            // Only update if event has passed
            if ($event_date > $now) {
                return false;
            }

            // Calculate next occurrence
            $interval = new DateInterval('P' . intval($recurring_interval) . 'D');
            $next_date = clone $event_date;
            $next_date->add($interval);

            // Check if we've exceeded the end date
            if (!empty($recurring_end)) {
                $end_date = new DateTime($recurring_end);
                if ($next_date > $end_date) {
                    // Disable recurring and mark as expired
                    update_post_meta($post_id, '_event_recurring_active', 'no');
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_status' => 'expired'
                    ));
                    return false;
                }
            }

            // Update event dates
            $new_date_str = $next_date->format('Y-m-d H:i:s');
            update_post_meta($post_id, '_event_date', $new_date_str);
            update_post_meta($post_id, '_event_date_timestamp', $next_date->getTimestamp());

            // Update post status to published if it was expired
            $post_status = get_post_status($post_id);
            if ($post_status === 'expired') {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ));
            }

            return true;
        } catch (Exception $e) {
            error_log('Listeo Event Recurrence Update Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Display recurring event information in single listing
     */
    public function display_recurring_event_info($post = null)
    {
        if (!$post) {
            global $post;
        }
        
        if (!$post) {
            return;
        }
        
        $listing_type = get_post_meta($post->ID, '_listing_type', true);
        if ($listing_type !== 'event') {
            return;
        }

        $is_recurring = get_post_meta($post->ID, '_event_recurring', true);
        if ($is_recurring !== 'on' && $is_recurring !== '1') {
            return;
        }

        $interval = get_post_meta($post->ID, '_event_recurring_interval', true);
        $end_date = get_post_meta($post->ID, '_event_recurring_end', true);

        // Get description
        $description = '';
        if ($interval == 1) {
            $description = __('Daily', 'listeo_core');
        } elseif ($interval == 7) {
            $description = __('Weekly', 'listeo_core');
        } elseif ($interval == 30) {
            $description = __('Monthly', 'listeo_core');
        } else {
            $description = sprintf(__('Every %d days', 'listeo_core'), intval($interval));
        }

        if (!empty($end_date)) {
            $end_date_formatted = date_i18n(get_option('date_format'), strtotime($end_date));
            $description .= sprintf(__(' until %s', 'listeo_core'), $end_date_formatted);
        }

        ?>
        <div class="recurring-event-info margin-top-40">
            <h4 class="listing-details-header detail-header-with-icon">
                <i class="fa fa-calendar-alt"></i> <?php _e('Recurring Event', 'listeo_core'); ?>
            </h4>
            <ul class="listing-details">
                <li class="recurring-schedule">
                    <i class="fa fa-sync-alt"></i>
                    <div class="single-property-detail-label-recurring">
                        <?php _e('Schedule', 'listeo_core'); ?>
                    </div>
                    <span><?php echo esc_html($description); ?></span>
                </li>
                <li class="recurring-status">
                    <i class="fa fa-check-circle"></i>
                    <div class="single-property-detail-label-status">
                        <?php _e('Status', 'listeo_core'); ?>
                    </div>
                    <span class="recurring-active"><?php _e('Active', 'listeo_core'); ?></span>
                </li>
            </ul>
        </div>
        <style>
        .recurring-event-info .recurring-active {
            color: #27ae60;
            font-weight: 600;
        }
        </style>
        <?php
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules)
    {
        if (!isset($schedules['listeo_hourly'])) {
            $schedules['listeo_hourly'] = array(
                'interval' => HOUR_IN_SECONDS,
                'display' => __('Listeo Hourly', 'listeo_core')
            );
        }
        return $schedules;
    }

    /**
     * Clean up cron jobs on deactivation
     */
    public function deactivation_cleanup()
    {
        $timestamp = wp_next_scheduled('listeo_process_recurring_events');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'listeo_process_recurring_events');
        }
    }
}

// Initialize the class
Listeo_Core_Event_Recurrence_New::instance();