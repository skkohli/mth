<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Listeo Core Event Recurrence Calculator
 */
class Listeo_Core_Event_Recurrence
{

    /**
     * Stores static instance of class.
     *
     * @access protected
     * @var Listeo_Core_Event_Recurrence The single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Stores recurring event IDs for current search to share between filters
     *
     * @access private
     * @var array
     */
    private static $current_search_recurring_events = array();

    /**
     * Returns static instance of class.
     *
     * @return self
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
        
        // Hook into the date range filter to include virtual occurrences
        add_filter('posts_where', array($this, 'include_recurring_events_in_date_search'), 10, 2);
        
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
        $event_date_end = get_post_meta($post_id, '_event_date_end', true);
        $recurring_interval = get_post_meta($post_id, '_event_recurring_interval', true);
        $recurring_end = get_post_meta($post_id, '_event_recurring_end', true);

        if (empty($event_date) || empty($recurring_interval)) {
            return false;
        }

        // Store original event dates for calculations
        update_post_meta($post_id, '_event_original_date', $event_date);
        if ($event_date_end) {
            update_post_meta($post_id, '_event_original_date_end', $event_date_end);
        }

        // Calculate next recurrence
        $next_occurrence = $this->calculate_next_occurrence($post_id);
        if ($next_occurrence) {
            update_post_meta($post_id, '_event_next_recurrence', $next_occurrence);
        }

        // Mark as active recurring event
        update_post_meta($post_id, '_event_recurring_active', 'yes');

        // Generate virtual occurrence timestamps for search compatibility
        $this->generate_virtual_occurrences($post_id);

        return true;
    }

    /**
     * Generate virtual occurrence timestamps for recurring events
     * This creates multiple date meta entries that regular search can find
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
                
                // Move to next occurrence
                $current_date->add($interval);
                $occurrence_count++;
            }
            
            // Store virtual timestamps as a serialized array
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
        delete_post_meta($post_id, '_event_original_date_end');
        delete_post_meta($post_id, '_event_next_recurrence');
        delete_post_meta($post_id, '_event_recurring_active');
        delete_post_meta($post_id, '_event_virtual_timestamps');
    }

    /**
     * Calculate next occurrence for an event
     */
    public function calculate_next_occurrence($post_id)
    {
        $event_date = get_post_meta($post_id, '_event_date', true);
        $recurring_interval = get_post_meta($post_id, '_event_recurring_interval', true);
        $recurring_end = get_post_meta($post_id, '_event_recurring_end', true);

        if (empty($event_date) || empty($recurring_interval)) {
            return false;
        }

        try {
            // Parse current event date
            $current_date = new DateTime($event_date);
            $now = new DateTime();

            // If event is in the future, return the original date
            if ($current_date > $now) {
                return $event_date;
            }

            // Calculate next occurrence
            $interval = new DateInterval('P' . intval($recurring_interval) . 'D');
            
            // Find the next occurrence after today
            while ($current_date <= $now) {
                $current_date->add($interval);
            }

            // Check if we've exceeded the end date
            if (!empty($recurring_end)) {
                $end_date = new DateTime($recurring_end);
                if ($current_date > $end_date) {
                    return false; // No more recurrences
                }
            }

            return $current_date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log('Listeo Event Recurrence Error: ' . $e->getMessage());
            return false;
        }
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
        }

        wp_reset_postdata();
    }

    /**
     * Update event dates for recurring events
     */
    public function update_event_dates($post_id)
    {
        $current_event_date = get_post_meta($post_id, '_event_date', true);
        $current_event_end = get_post_meta($post_id, '_event_date_end', true);
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

            // Update event date timestamp for search compatibility
            update_post_meta($post_id, '_event_date_timestamp', $next_date->getTimestamp());

            // Update end date if it exists
            if (!empty($current_event_end)) {
                $event_end = new DateTime($current_event_end);
                $duration = $event_date->diff($event_end);
                $new_end_date = clone $next_date;
                $new_end_date->add($duration);
                
                $new_end_str = $new_end_date->format('Y-m-d H:i:s');
                update_post_meta($post_id, '_event_date_end', $new_end_str);
                update_post_meta($post_id, '_event_date_end_timestamp', $new_end_date->getTimestamp());
            }

            // Update post status to published if it was expired
            $post_status = get_post_status($post_id);
            if ($post_status === 'expired') {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ));
            }

            // Update next recurrence for tracking
            $next_next_date = clone $next_date;
            $next_next_date->add($interval);
            update_post_meta($post_id, '_event_next_recurrence', $next_next_date->format('Y-m-d H:i:s'));

            // Generate virtual occurrence timestamps for future dates
            $this->generate_virtual_occurrences($post_id);

            // Log the update
            do_action('listeo_event_recurrence_updated', $post_id, $new_date_str);

            // Trigger AI search embedding regeneration if the plugin is available
            if (class_exists('Listeo_AI_Search_Embedding_Manager')) {
                do_action('listeo_ai_search_regenerate_embedding', $post_id);
            }

            return true;
        } catch (Exception $e) {
            error_log('Listeo Event Recurrence Update Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get upcoming recurrence dates for an event
     */
    public function get_upcoming_recurrences($post_id, $limit = 5)
    {
        $is_recurring = get_post_meta($post_id, '_event_recurring_active', true);
        if ($is_recurring !== 'yes') {
            return array();
        }

        $event_date = get_post_meta($post_id, '_event_date', true);
        $recurring_interval = get_post_meta($post_id, '_event_recurring_interval', true);
        $recurring_end = get_post_meta($post_id, '_event_recurring_end', true);

        if (empty($event_date) || empty($recurring_interval)) {
            return array();
        }

        $recurrences = array();

        try {
            $current_date = new DateTime($event_date);
            $interval = new DateInterval('P' . intval($recurring_interval) . 'D');
            $end_date = !empty($recurring_end) ? new DateTime($recurring_end) : null;

            for ($i = 0; $i < $limit; $i++) {
                if ($end_date && $current_date > $end_date) {
                    break;
                }

                $recurrences[] = $current_date->format('Y-m-d H:i:s');
                $current_date->add($interval);
            }
        } catch (Exception $e) {
            error_log('Listeo Event Recurrence Preview Error: ' . $e->getMessage());
        }

        return $recurrences;
    }

    /**
     * Check if an event is recurring
     */
    public function is_recurring_event($post_id)
    {
        $is_recurring = get_post_meta($post_id, '_event_recurring', true);
        return ($is_recurring === 'on' || $is_recurring === '1');
    }

    /**
     * Get recurrence information for display
     */
    public function get_recurrence_info($post_id)
    {
        if (!$this->is_recurring_event($post_id)) {
            return false;
        }

        $interval = get_post_meta($post_id, '_event_recurring_interval', true);
        $end_date = get_post_meta($post_id, '_event_recurring_end', true);

        $info = array(
            'interval' => $interval,
            'end_date' => $end_date,
            'is_active' => get_post_meta($post_id, '_event_recurring_active', true) === 'yes',
            'next_occurrence' => get_post_meta($post_id, '_event_next_recurrence', true)
        );

        return $info;
    }

    /**
     * Get human-readable recurrence description
     */
    public function get_recurrence_description($post_id)
    {
        $info = $this->get_recurrence_info($post_id);
        if (!$info) {
            return '';
        }

        $interval = intval($info['interval']);
        $description = '';

        if ($interval === 1) {
            $description = __('Daily', 'listeo_core');
        } elseif ($interval === 7) {
            $description = __('Weekly', 'listeo_core');
        } elseif ($interval === 14) {
            $description = __('Bi-weekly', 'listeo_core');
        } elseif ($interval === 30) {
            $description = __('Monthly', 'listeo_core');
        } else {
            $description = sprintf(__('Every %d days', 'listeo_core'), $interval);
        }

        if (!empty($info['end_date'])) {
            $end_date = date_i18n(get_option('date_format'), strtotime($info['end_date']));
            $description .= sprintf(__(' until %s', 'listeo_core'), $end_date);
        }

        return $description;
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules)
    {
        // Add custom schedules if needed
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

    /**
     * Manual trigger for recurring events (admin use)
     */
    public function manual_process_recurring_events()
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        return $this->process_recurring_events();
    }

    /**
     * Add recurring event fields to event details
     */
    public function add_recurring_fields_to_details($fields)
    {
        if (!isset($fields['fields'])) {
            return $fields;
        }

        // Add recurring event information fields (for display only)
        $recurring_fields = array(
            '_event_recurring_info' => array(
                'name' => __('Event Schedule', 'listeo_core'),
                'id' => '_event_recurring_info',
                'type' => 'text',
                'icon' => 'fa fa-calendar-alt',
                'invert' => false
            ),
            '_event_next_occurrence' => array(
                'name' => __('Next Occurrence', 'listeo_core'),
                'id' => '_event_next_occurrence',
                'type' => 'datetime',
                'icon' => 'fa fa-clock',
                'invert' => false
            )
        );

        // Add recurring fields to the end
        $fields['fields'] = array_merge($fields['fields'], $recurring_fields);

        return $fields;
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

        if (!$this->is_recurring_event($post->ID)) {
            return;
        }

        $recurrence_info = $this->get_recurrence_info($post->ID);
        if (!$recurrence_info) {
            return;
        }

        $description = $this->get_recurrence_description($post->ID);
        $upcoming_dates = $this->get_upcoming_recurrences($post->ID, 3);

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
                
                <?php if (!empty($upcoming_dates)): ?>
                    <li class="upcoming-dates">
                        <i class="fa fa-clock"></i>
                        <div class="single-property-detail-label-upcoming">
                            <?php _e('Upcoming Dates', 'listeo_core'); ?>
                        </div>
                        <span class="upcoming-dates-list">
                            <?php 
                            $formatted_dates = array_map(function($date) {
                                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date));
                            }, $upcoming_dates);
                            echo esc_html(implode(', ', $formatted_dates));
                            ?>
                        </span>
                    </li>
                <?php endif; ?>
                
                <?php if ($recurrence_info['is_active']): ?>
                    <li class="recurring-status">
                        <i class="fa fa-check-circle"></i>
                        <div class="single-property-detail-label-status">
                            <?php _e('Status', 'listeo_core'); ?>
                        </div>
                        <span class="recurring-active"><?php _e('Active', 'listeo_core'); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <style>
        .recurring-event-info .recurring-active {
            color: #27ae60;
            font-weight: 600;
        }
        .upcoming-dates-list {
            display: block;
            line-height: 1.4;
        }
        </style>
        <?php
    }

    /**
     * Add recurring event badge to listing cards
     */
    public function add_recurring_badge($post_id)
    {
        if (!$this->is_recurring_event($post_id)) {
            return '';
        }

        $listing_type = get_post_meta($post_id, '_listing_type', true);
        if ($listing_type !== 'event') {
            return '';
        }

        return '<div class="listing-badge recurring-event"><i class="fa fa-sync-alt"></i> ' . __('Recurring', 'listeo_core') . '</div>';
    }

    /**
     * Include recurring events in date search by modifying the WHERE clause
     */
    public function include_recurring_events_in_date_search($where, $query)
    {
        global $wpdb;
        
        // Only apply to main queries that search for event dates
        if (!$query->is_main_query() && !isset($_REQUEST['date_range'])) {
            return $where;
        }
        
        // Check if this is a date-filtered event search
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
            // Parse multiple date formats
            $date_start = null;
            $date_end = null;
            
            $date_formats = array(
                'm/d/Y H:i', 'm/d/Y', 'd/m/Y H:i', 'd/m/Y', 
                'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'
            );
            
            foreach ($date_formats as $format) {
                $date_start = DateTime::createFromFormat($format, trim($dates[0]));
                $date_end = DateTime::createFromFormat($format, trim($dates[1]));
                if ($date_start && $date_end) {
                    break;
                }
            }
            
            if (!$date_start || !$date_end) {
                $date_start = new DateTime(trim($dates[0]));
                $date_end = new DateTime(trim($dates[1]));
            }
            
            if (!$date_start || !$date_end) {
                return $where;
            }
            
            $start_timestamp = $date_start->getTimestamp();
            $end_timestamp = $date_end->getTimestamp();
            
            // Add OR condition to include recurring events with virtual timestamps
            $virtual_timestamp_condition = $wpdb->prepare(
                "OR EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} vtm
                    WHERE vtm.post_id = {$wpdb->posts}.ID
                    AND vtm.meta_key = '_event_virtual_timestamps'
                    AND vtm.meta_value LIKE %s
                )",
                '%' . $wpdb->esc_like('"' . $start_timestamp) . '%'
            );
            
            // Find existing date conditions and modify them
            if (strpos($where, '_event_date_timestamp') !== false) {
                $where = preg_replace(
                    '/(AND\s*\([^)]*_event_date_timestamp[^)]*\))/i',
                    '$1 ' . $virtual_timestamp_condition,
                    $where
                );
            }
            
            error_log('Recurring Events: Modified WHERE clause for date search');
            
        } catch (Exception $e) {
            error_log('Recurring Events WHERE Error: ' . $e->getMessage());
        }
        
        return $where;
    }

    /**
     * Generate virtual timestamps for existing recurring events (admin tool)
     */
    public function regenerate_all_virtual_occurrences()
    {
        error_log('Recurring Events Debug: modify_listings_query_for_recurring_events CALLED');
        error_log('Recurring Events Debug: _REQUEST date_range: ' . (isset($_REQUEST['date_range']) ? $_REQUEST['date_range'] : 'NOT SET'));
        error_log('Recurring Events Debug: is_admin: ' . (is_admin() ? 'YES' : 'NO'));
        error_log('Recurring Events Debug: args date_start: ' . (isset($args['date_start']) ? $args['date_start'] : 'NOT SET'));
        error_log('Recurring Events Debug: args date_end: ' . (isset($args['date_end']) ? $args['date_end'] : 'NOT SET'));
        error_log('Recurring Events Debug: Full args: ' . print_r($args, true));
        
        // Temporarily comment out early returns to see if filter is being called
        /*
        // Only apply to listing queries with date filtering
        if (!isset($_REQUEST['date_range']) ||
            empty($_REQUEST['date_range']) ||
            is_admin() ||
            !isset($args['date_start']) ||
            !isset($args['date_end'])) {
            error_log('Recurring Events Debug: Skipping - conditions not met');
            return $query_args;
        }
        */
        
        // Continue with minimal check
        if (!isset($_REQUEST['date_range']) || empty($_REQUEST['date_range'])) {
            error_log('Recurring Events Debug: No date_range in REQUEST');
            return $query_args;
        }
        
        // Check if this is for event listings
        $listing_type = isset($_REQUEST['_listing_type']) ? $_REQUEST['_listing_type'] : '';
        if ($listing_type !== 'event') {
            return $query_args;
        }

        // Get the date range from the search parameters
        $date_range = sanitize_text_field($_REQUEST['date_range']);
        
        // Debug: Log what we're receiving
        error_log('Recurring Events Debug: Received date_range: ' . $date_range);
        error_log('Recurring Events Debug: Full REQUEST: ' . print_r($_REQUEST, true));
        error_log('Recurring Events Debug: args passed to filter: ' . print_r($args, true));
        
        if (strpos($date_range, ' - ') === false) {
            // Check if it's a single date search
            if (!empty($date_range)) {
                $dates = array($date_range, $date_range); // Single date becomes range
            } else {
                return;
            }
        } else {
            $dates = explode(' - ', $date_range);
            if (count($dates) != 2) {
                return;
            }
        }

        try {
            $date_start = null;
            $date_end = null;
            
            // Try parsing as timestamps first
            if (is_numeric(trim($dates[0])) && is_numeric(trim($dates[1]))) {
                $date_start = new DateTime();
                $date_start->setTimestamp(intval(trim($dates[0])));
                $date_end = new DateTime();
                $date_end->setTimestamp(intval(trim($dates[1])));
            } else {
                // Try multiple date formats
                $date_formats = array(
                    'm/d/Y H:i', 'm/d/Y', 'd/m/Y H:i', 'd/m/Y', 
                    'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d',
                    'F j, Y \a\t g:i A', 'F j, Y g:i A', 'F j, Y'
                );
                
                foreach ($date_formats as $format) {
                    $date_start = DateTime::createFromFormat($format, trim($dates[0]));
                    $date_end = DateTime::createFromFormat($format, trim($dates[1]));
                    if ($date_start && $date_end) {
                        error_log('Recurring Events Debug: Successfully parsed with format: ' . $format);
                        break;
                    }
                }
                
                // If still no luck, try strtotime
                if (!$date_start || !$date_end) {
                    $date_start = new DateTime(trim($dates[0]));
                    $date_end = new DateTime(trim($dates[1]));
                }
            }
            
            if (!$date_start || !$date_end) {
                // Log for debugging
                error_log('Recurring Events: Could not parse date range: ' . $date_range);
                error_log('Recurring Events: Date parts: ' . print_r($dates, true));
                return;
            }
            
            // Log for debugging
            error_log('Recurring Events: Searching for events between ' . $date_start->format('Y-m-d') . ' and ' . $date_end->format('Y-m-d'));

            $start_timestamp = $date_start->getTimestamp();
            $end_timestamp = $date_end->getTimestamp();

            // Get all recurring events that might match
            $recurring_events = $this->get_recurring_events_in_date_range($start_timestamp, $end_timestamp);
            
            if (!empty($recurring_events)) {
                // Store recurring events for use in WHERE filter
                self::$current_search_recurring_events = $recurring_events;
                
                // Log for debugging
                error_log('Recurring Events: Found ' . count($recurring_events) . ' matching recurring events: ' . implode(', ', $recurring_events));
                
                // Get regular events that match the date range
                $regular_events = $this->get_regular_events_in_date_range($start_timestamp, $end_timestamp);
                $all_matching_events = array_unique(array_merge($regular_events, $recurring_events));
                
                if (!empty($all_matching_events)) {
                    // Override the post__in parameter to include our matching events
                    $query_args['post__in'] = $all_matching_events;
                    
                    // Remove or modify existing meta queries that filter by event dates
                    if (isset($query_args['meta_query']) && is_array($query_args['meta_query'])) {
                        $filtered_meta_query = array();
                        foreach ($query_args['meta_query'] as $key => $meta_condition) {
                            if (is_array($meta_condition) && isset($meta_condition['key'])) {
                                // Skip date timestamp meta queries - we're handling dates with post__in now
                                if (in_array($meta_condition['key'], ['_event_date_timestamp', '_event_date_end_timestamp'])) {
                                    continue;
                                }
                            }
                            $filtered_meta_query[$key] = $meta_condition;
                        }
                        $query_args['meta_query'] = $filtered_meta_query;
                    }
                    
                    error_log('Recurring Events: Modified query args with post__in: ' . implode(', ', $all_matching_events));
                    error_log('Recurring Events Debug: Modified query args: ' . print_r($query_args, true));
                }
            } else {
                error_log('Recurring Events: No matching recurring events found');
                // Clear stored events if no matches found
                self::$current_search_recurring_events = array();
            }

        } catch (Exception $e) {
            error_log('Recurring Events Date Search Error: ' . $e->getMessage());
            // Clear stored events on error
            self::$current_search_recurring_events = array();
        }

        return $query_args;
    }

    /**
     * Get recurring events that have occurrences within the specified date range
     */
    private function get_recurring_events_in_date_range($start_timestamp, $end_timestamp)
    {
        global $wpdb;
        
        // Get all active recurring events
        $recurring_events = $wpdb->get_results("
            SELECT p.ID, p.post_title,
                   event_date.meta_value as event_date,
                   interval_meta.meta_value as recurring_interval,
                   end_meta.meta_value as recurring_end
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} listing_type ON (p.ID = listing_type.post_id AND listing_type.meta_key = '_listing_type' AND listing_type.meta_value = 'event')
            INNER JOIN {$wpdb->postmeta} recurring ON (p.ID = recurring.post_id AND recurring.meta_key = '_event_recurring' AND recurring.meta_value = 'on')
            INNER JOIN {$wpdb->postmeta} active ON (p.ID = active.post_id AND active.meta_key = '_event_recurring_active' AND active.meta_value = 'yes')
            LEFT JOIN {$wpdb->postmeta} event_date ON (p.ID = event_date.post_id AND event_date.meta_key = '_event_date')
            LEFT JOIN {$wpdb->postmeta} interval_meta ON (p.ID = interval_meta.post_id AND interval_meta.meta_key = '_event_recurring_interval')
            LEFT JOIN {$wpdb->postmeta} end_meta ON (p.ID = end_meta.post_id AND end_meta.meta_key = '_event_recurring_end')
            WHERE p.post_type = 'listing' 
            AND p.post_status IN ('publish')
        ");

        $matching_events = array();

        foreach ($recurring_events as $event) {
            if (empty($event->event_date) || empty($event->recurring_interval)) {
                continue;
            }

            try {
                $event_start = new DateTime($event->event_date);
                $interval_days = intval($event->recurring_interval);
                $interval = new DateInterval('P' . $interval_days . 'D');
                
                // Check if recurring end date exists
                $recurring_end = null;
                if (!empty($event->recurring_end)) {
                    $recurring_end = new DateTime($event->recurring_end);
                }

                // Generate occurrences and check if any fall within the search range
                $current_date = clone $event_start;
                $search_start = new DateTime();
                $search_start->setTimestamp($start_timestamp);
                $search_end = new DateTime();
                $search_end->setTimestamp($end_timestamp);
                
                // Set search end to include full day
                $search_end->setTime(23, 59, 59);

                // Debug logging for this specific event
                error_log('Recurring Events Debug: Checking event ID ' . $event->ID);
                error_log('Recurring Events Debug: Event start date: ' . $current_date->format('Y-m-d H:i:s'));
                error_log('Recurring Events Debug: Search range: ' . $search_start->format('Y-m-d H:i:s') . ' to ' . $search_end->format('Y-m-d H:i:s'));
                error_log('Recurring Events Debug: Interval: ' . $interval_days . ' days');

                // If the original event date is in the past, advance to next occurrence after today
                $now = new DateTime();
                if ($current_date < $now) {
                    $days_since = $now->diff($current_date)->days;
                    $occurrences_passed = ceil($days_since / $interval_days);
                    $current_date->add(new DateInterval('P' . ($occurrences_passed * $interval_days) . 'D'));
                    error_log('Recurring Events Debug: Advanced to current occurrence: ' . $current_date->format('Y-m-d H:i:s'));
                }

                // Limit to reasonable number of iterations to prevent infinite loops
                $max_iterations = 365; // Check up to 1 year of future occurrences
                $iterations = 0;

                while ($iterations < $max_iterations) {
                    error_log('Recurring Events Debug: Checking occurrence: ' . $current_date->format('Y-m-d H:i:s'));
                    
                    // Check if we've exceeded the recurring end date
                    if ($recurring_end && $current_date > $recurring_end) {
                        error_log('Recurring Events Debug: Exceeded end date, breaking');
                        break;
                    }

                    // If current occurrence is after our search end, stop
                    if ($current_date > $search_end) {
                        error_log('Recurring Events Debug: Past search end, breaking');
                        break;
                    }

                    // Check if current occurrence falls within search range (check just the date part)
                    $occurrence_date = $current_date->format('Y-m-d');
                    $search_start_date = $search_start->format('Y-m-d');
                    $search_end_date = $search_end->format('Y-m-d');
                    
                    if ($occurrence_date >= $search_start_date && $occurrence_date <= $search_end_date) {
                        error_log('Recurring Events Debug: Found matching occurrence on ' . $occurrence_date . ' for event ' . $event->ID);
                        $matching_events[] = $event->ID;
                        break; // Found a match, no need to check more occurrences
                    }

                    // Move to next occurrence
                    $current_date->add($interval);
                    $iterations++;
                }

            } catch (Exception $e) {
                error_log('Error processing recurring event ' . $event->ID . ': ' . $e->getMessage());
                continue;
            }
        }

        return array_unique($matching_events);
    }

    /**
     * Get regular (non-recurring) events that fall within the specified date range
     */
    private function get_regular_events_in_date_range($start_timestamp, $end_timestamp)
    {
        global $wpdb;
        
        // Validate timestamps
        if (!is_numeric($start_timestamp) || !is_numeric($end_timestamp)) {
            error_log('Recurring Events: Invalid timestamps for regular events query');
            return array();
        }
        
        $regular_events = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} listing_type ON (p.ID = listing_type.post_id AND listing_type.meta_key = '_listing_type' AND listing_type.meta_value = 'event')
            LEFT JOIN {$wpdb->postmeta} event_start ON (p.ID = event_start.post_id AND event_start.meta_key = '_event_date_timestamp')
            LEFT JOIN {$wpdb->postmeta} event_end ON (p.ID = event_end.post_id AND event_end.meta_key = '_event_date_end_timestamp')
            WHERE p.post_type = 'listing' 
            AND p.post_status = 'publish'
            AND (
                (event_start.meta_value BETWEEN %d AND %d) OR
                (event_end.meta_value BETWEEN %d AND %d)
            )
        ", $start_timestamp, $end_timestamp, $start_timestamp, $end_timestamp));

        return array_map('intval', $regular_events);
    }

    /**
     * Remove conflicting date conditions from WHERE clause for recurring events
     */
    public function remove_conflicting_date_conditions($where, $query)
    {
        // Debug: Log that the filter is being called
        error_log('Recurring Events WHERE Debug: posts_where filter called');
        error_log('Recurring Events WHERE Debug: Has stored recurring events: ' . (empty(self::$current_search_recurring_events) ? 'NO' : 'YES - ' . implode(', ', self::$current_search_recurring_events)));
        
        // Only apply if we have recurring events stored from the previous filter
        if (empty(self::$current_search_recurring_events)) {
            return $where;
        }

        // Only apply to listing queries with date filtering 
        if (!isset($_REQUEST['date_range']) ||
            empty($_REQUEST['date_range'])) {
            error_log('Recurring Events WHERE Debug: No date_range, skipping');
            return $where;
        }

        // Check if the WHERE clause contains date timestamp conditions
        if (strpos($where, '_event_date_timestamp') === false) {
            error_log('Recurring Events WHERE Debug: No date timestamp conditions found');
            return $where;
        }

        // We have recurring events and date timestamp conditions, so remove the conflicting conditions
        error_log('Recurring Events WHERE Debug: Removing date timestamp conditions for recurring events');
        
        // Remove date timestamp meta conditions that would exclude our recurring events
        $original_where = $where;
        $where = preg_replace(
            '/AND\s*\(\s*[^)]*_event_date(_end)?_timestamp[^)]*BETWEEN[^)]*\)/i',
            '',
            $where
        );
        
        // Clean up any resulting empty conditions
        $where = preg_replace('/AND\s*\(\s*\)/i', '', $where);
        $where = preg_replace('/\(\s*AND\s*/i', '(', $where);
        
        if ($where !== $original_where) {
            error_log('Recurring Events: Successfully removed date timestamp conditions from WHERE clause');
            error_log('Recurring Events Debug: Original WHERE: ' . $original_where);
            error_log('Recurring Events Debug: Modified WHERE: ' . $where);
        }

        // Clear stored recurring events after processing to prevent affecting other queries
        self::$current_search_recurring_events = array();

        return $where;
    }

    /**
     * Debug: Log the final SQL query to see what's actually being executed
     */
    public function debug_final_sql_query($sql)
    {
        error_log('Recurring Events Debug: Final SQL Query: ' . $sql);
        
        // Remove the filter after first use to avoid logging all queries
        remove_filter('posts_request', array($this, 'debug_final_sql_query'), 9999);
        
        return $sql;
    }
}

// Initialize the class
Listeo_Core_Event_Recurrence::instance();