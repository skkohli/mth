<?php

if (!defined('ABSPATH')) exit;

class Listeo_Core_Site_Health
{

    /**
     * Returns the instance.
     *
     * @since 2.0.0
     */
    public static function get_instance()
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new self;
        }
        return $instance;
    }


    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        add_filter('site_health_navigation_tabs',array($this,'listeo_site_health_navigation_tabs'));
        add_action('site_health_tab_content', array($this, 'listeo_site_health_tab_content'));
        add_action('admin_enqueue_scripts', array($this, 'listeo_site_health_enqueue_admin_scripts'));
        add_filter('admin_body_class', array($this, 'listeo_add_health_check_body_class'));
        add_action('admin_bar_menu', array($this, 'listeo_add_admin_bar_health_check'), 100);

        add_action('wp_ajax_listeo_recreate_page', array($this, 'listeo_recreate_page'));
        add_action('wp_ajax_listeo_update_memory_limit', array($this, 'listeo_update_memory_limit'));
        add_action('wp_ajax_listeo_toggle_debug_mode', array($this, 'listeo_toggle_debug_mode'));
        add_action('wp_ajax_listeo_test_email', array($this, 'listeo_test_email'));
        add_action('wp_ajax_listeo_get_heartbeat_status', array($this, 'listeo_get_heartbeat_status'));
        add_action('wp_ajax_listeo_update_heartbeat_settings', array($this, 'listeo_update_heartbeat_settings'));
        
        // Database health AJAX handlers
        add_action('wp_ajax_listeo_get_database_stats', array($this, 'listeo_get_database_stats'));
        add_action('wp_ajax_listeo_cleanup_transients', array($this, 'listeo_cleanup_transients'));
        add_action('wp_ajax_listeo_cleanup_revisions', array($this, 'listeo_cleanup_revisions'));

        // Booking email testing AJAX handler
        add_action('wp_ajax_listeo_test_booking_email', array($this, 'listeo_test_booking_email'));
        add_action('wp_ajax_listeo_debug_test', array($this, 'listeo_debug_test'));

    }

    /**
     * Simple debug test to verify AJAX is working
     */
    function listeo_debug_test() {
        wp_send_json_success(array('message' => 'AJAX is working! PHP version: ' . phpversion()));
    }

    
    function listeo_site_health_enqueue_admin_scripts( $hook ) {
        
        if ('site-health.php' == $hook ) {
            
            wp_enqueue_script('listeo_site_health_script', LISTEO_CORE_URL . 'assets/js/listeo.sitehealth.js', array('wp-util', 'jquery'), 1.0, true);
            
            // Localize script with nonce
            wp_localize_script('listeo_site_health_script', 'listeo_site_health_vars', array(
                'memory_limit_nonce' => wp_create_nonce('listeo_memory_limit_nonce'),
                'debug_toggle_nonce' => wp_create_nonce('listeo_debug_toggle_nonce'),
                'test_email_nonce' => wp_create_nonce('listeo_test_email_nonce'),
                'heartbeat_nonce' => wp_create_nonce('listeo_heartbeat_nonce'),
                'database_nonce' => wp_create_nonce('listeo_database_nonce'),
                'cleanup_nonce' => wp_create_nonce('listeo_cleanup_nonce'),
                'booking_email_test_nonce' => wp_create_nonce('listeo_booking_email_test_nonce'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'admin_email' => get_option('admin_email')
            ));
            
        }
        
    }

    function listeo_add_health_check_body_class($classes)
    {
        // Get current screen
        $screen = get_current_screen();
        
        // Check if we're on the site health page and on the Listeo tab
        if ($screen && $screen->id === 'site-health' && 
            isset($_GET['tab']) && $_GET['tab'] === 'listeo-site-health-tab') {
            $classes .= ' listeo-health-check-page';
        }
        
        return $classes;
    }

    function listeo_site_health_navigation_tabs($tabs)
    {
        // translators: Tab heading for Site Health navigation.
        $tabs['listeo-site-health-tab'] = esc_html_x('Listeo', 'Site Health', 'listeo_core');

        return $tabs;
    }

    function listeo_site_health_tab_content( $tab ) {
        // Do nothing if this is not our tab.
        if ('listeo-site-health-tab' !== $tab ) {
            return;
        }
    
        // Include the interface, kept in a separate file just to differentiate code from views.
        include trailingslashit( plugin_dir_path( __FILE__ ) ) . '/views/site-health-tab.php';
    }


    function listeo_recreate_page(){
        $pages = listeo_core_get_dashboard_pages_list();
        
        if(!empty($_POST['page'])){
            $page = $pages[$_POST['page']];
            $title = $page['title'];
            $content = $page['content'];
            delete_option($page['option']);
            $page_args = array(
                'comment_status' => 'close',
                'ping_status'    => 'close',
                'post_author'    => 1,
                'post_title'     => $title,
                'post_name'      => strtolower(str_replace(' ', '-', trim($title))),
                'post_status'    => 'publish',
                'post_content'   => $content,
                'post_type'      => 'page',
                'page_template'  => 'template-dashboard.php'
            );
            if(in_array($_POST['page'],array('listeo_lost_password_page', 'listeo_reset_password_page'))){
               unset($page_args['page_template']);
            }
            $page_id = wp_insert_post(
                $page_args
            );
            
            if($page_id){
                update_option($page['option'],$page_id);
                wp_send_json_success();
            }
        } else {
            wp_send_json_error();
        }
    
        
    }

    function listeo_update_memory_limit() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_memory_limit_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        $memory_limit = sanitize_text_field($_POST['memory_limit']);
        
        // Validate memory limit
        if (!in_array($memory_limit, array('256M', '512M'))) {
            wp_send_json_error(array('message' => __('Invalid memory limit value', 'listeo_core')));
            return;
        }

        $wp_config_path = ABSPATH . 'wp-config.php';
        
        // Check if wp-config.php exists and is writable
        if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
            wp_send_json_error(array('message' => __('wp-config.php is not writable', 'listeo_core')));
            return;
        }

        // Create backup
        $backup_path = $wp_config_path . '.backup.' . time();
        if (!copy($wp_config_path, $backup_path)) {
            wp_send_json_error(array('message' => __('Could not create backup', 'listeo_core')));
            return;
        }

        // Read wp-config.php
        $wp_config_content = file_get_contents($wp_config_path);
        
        if ($wp_config_content === false) {
            wp_send_json_error(array('message' => __('Could not read wp-config.php', 'listeo_core')));
            return;
        }

        $memory_definitions = array(
            "define('WP_MEMORY_LIMIT', '{$memory_limit}');",
            "define('WP_MAX_MEMORY_LIMIT', '{$memory_limit}');"
        );

        $updated_content = $wp_config_content;
        $added_definitions = array();

        foreach ($memory_definitions as $definition) {
            $constant_name = $definition === "define('WP_MEMORY_LIMIT', '{$memory_limit}');" ? 'WP_MEMORY_LIMIT' : 'WP_MAX_MEMORY_LIMIT';
            
            // Check if constant already exists
            $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant_name, '/') . '[\'"]\s*,\s*[\'"][^\'\"]*[\'"]\s*\)\s*;/';
            
            if (preg_match($pattern, $updated_content)) {
                // Update existing definition
                $updated_content = preg_replace($pattern, $definition, $updated_content);
            } else {
                // Add new definition
                $added_definitions[] = $definition;
            }
        }

        // Add new definitions before "/* That's all, stop editing! Happy publishing. */"
        if (!empty($added_definitions)) {
            $insert_point = "/* That's all, stop editing! Happy publishing. */";
            $new_definitions = "\n// WordPress Memory Limits\n" . implode("\n", $added_definitions) . "\n\n";
            $updated_content = str_replace($insert_point, $new_definitions . $insert_point, $updated_content);
        }

        // Write updated content
        if (file_put_contents($wp_config_path, $updated_content) === false) {
            wp_send_json_error(array('message' => __('Could not write to wp-config.php', 'listeo_core')));
            return;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Memory limit successfully updated to %s', 'listeo_core'), $memory_limit),
            'backup_created' => basename($backup_path)
        ));
    }

    function listeo_toggle_debug_mode() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_debug_toggle_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        $action = sanitize_text_field($_POST['debug_action']);
        
        // Validate action
        if (!in_array($action, array('enable_full', 'disable_all', 'enable_logging', 'disable_display'))) {
            wp_send_json_error(array('message' => __('Invalid action', 'listeo_core')));
            return;
        }

        $wp_config_path = ABSPATH . 'wp-config.php';
        
        // Check if wp-config.php exists and is writable
        if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
            wp_send_json_error(array('message' => __('wp-config.php is not writable', 'listeo_core')));
            return;
        }

        // Create backup
        $backup_path = $wp_config_path . '.backup.' . time();
        if (!copy($wp_config_path, $backup_path)) {
            wp_send_json_error(array('message' => __('Could not create backup', 'listeo_core')));
            return;
        }

        // Read wp-config.php
        $wp_config_content = file_get_contents($wp_config_path);
        
        if ($wp_config_content === false) {
            wp_send_json_error(array('message' => __('Could not read wp-config.php', 'listeo_core')));
            return;
        }

        $updated_content = $wp_config_content;
        $debug_definitions = array();
        $added_definitions = array();
        
        // Define debug settings based on action
        switch ($action) {
            case 'enable_full':
                $debug_definitions = array(
                    'WP_DEBUG' => "define( 'WP_DEBUG', true );",
                    'WP_DEBUG_LOG' => "define( 'WP_DEBUG_LOG', true );",
                    'WP_DEBUG_DISPLAY' => "define( 'WP_DEBUG_DISPLAY', true );",
                    'SCRIPT_DEBUG' => "define( 'SCRIPT_DEBUG', true );"
                );
                break;
                
            case 'disable_all':
                $debug_definitions = array(
                    'WP_DEBUG' => "define( 'WP_DEBUG', false );",
                    'WP_DEBUG_LOG' => "define( 'WP_DEBUG_LOG', false );",
                    'WP_DEBUG_DISPLAY' => "define( 'WP_DEBUG_DISPLAY', false );",
                    'SCRIPT_DEBUG' => "define( 'SCRIPT_DEBUG', false );"
                );
                break;
                
            case 'enable_logging':
                $debug_definitions = array(
                    'WP_DEBUG' => "define( 'WP_DEBUG', true );",
                    'WP_DEBUG_LOG' => "define( 'WP_DEBUG_LOG', true );",
                    'WP_DEBUG_DISPLAY' => "define( 'WP_DEBUG_DISPLAY', false );"
                );
                break;
                
            case 'disable_display':
                $debug_definitions = array(
                    'WP_DEBUG_DISPLAY' => "define( 'WP_DEBUG_DISPLAY', false );"
                );
                break;
        }

        // Update or add debug definitions
        foreach ($debug_definitions as $constant_name => $definition) {
            $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant_name, '/') . '[\'"]\s*,\s*[^)]+\)\s*;/';
            
            if (preg_match($pattern, $updated_content)) {
                // Update existing definition
                $updated_content = preg_replace($pattern, $definition, $updated_content);
            } else {
                // Add new definition
                $added_definitions[] = $definition;
            }
        }

        // Add new definitions before "/* That's all, stop editing! Happy publishing. */"
        if (!empty($added_definitions)) {
            $insert_point = "/* That's all, stop editing! Happy publishing. */";
            $new_definitions = "\n// Debug Mode Settings\n" . implode("\n", $added_definitions) . "\n\n";
            $updated_content = str_replace($insert_point, $new_definitions . $insert_point, $updated_content);
        }

        // Write updated content
        if (file_put_contents($wp_config_path, $updated_content) === false) {
            wp_send_json_error(array('message' => __('Could not write to wp-config.php', 'listeo_core')));
            return;
        }

        // Create success message based on action
        $messages = array(
            'enable_full' => __('Full debug mode enabled. All debugging features are now active.', 'listeo_core'),
            'disable_all' => __('All debug features disabled. Site is now in production mode.', 'listeo_core'),
            'enable_logging' => __('Error logging enabled. Errors will be logged but not displayed to visitors.', 'listeo_core'),
            'disable_display' => __('Frontend error display disabled. Errors will not be shown to visitors.', 'listeo_core')
        );
        
        $message = isset($messages[$action]) ? $messages[$action] : __('Debug settings updated successfully.', 'listeo_core');

        wp_send_json_success(array(
            'message' => $message,
            'backup_created' => basename($backup_path)
        ));
    }

    function listeo_test_email() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_test_email_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        $test_email = sanitize_email($_POST['test_email']);
        
        // Validate email
        if (!is_email($test_email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'listeo_core')));
            return;
        }

        // Prepare test email
        $subject = __('Test Email from Listeo Site Health', 'listeo_core');
        $message = sprintf(
            __('This is a test email sent from your Listeo site at %s to verify email functionality. If you received this email, your mail system is working correctly.', 'listeo_core'),
            home_url()
        );

        // Add additional diagnostic info
        $message .= "\n\n" . __('Email Configuration Details:', 'listeo_core') . "\n";
        $message .= sprintf(__('- WordPress Version: %s', 'listeo_core'), get_bloginfo('version')) . "\n";
        $message .= sprintf(__('- PHP Version: %s', 'listeo_core'), phpversion()) . "\n";
        $message .= sprintf(__('- Server Time: %s', 'listeo_core'), current_time('Y-m-d H:i:s')) . "\n";

        // Attempt to send email
        $sent = wp_mail($test_email, $subject, $message);

        if ($sent) {
            wp_send_json_success(array(
                'message' => sprintf(__('Test email sent successfully to %s. Please check your inbox (and spam folder).', 'listeo_core'), $test_email)
            ));
        } else {
            // Get the last error
            global $phpmailer;
            $error_message = '';
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_message = ' Error: ' . $phpmailer->ErrorInfo;
            }
            
            wp_send_json_error(array(
                'message' => sprintf(__('Failed to send test email to %s.%s', 'listeo_core'), $test_email, $error_message)
            ));
        }
    }

    function listeo_add_admin_bar_health_check($wp_admin_bar) {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add Listeo Health Check to admin bar
        $wp_admin_bar->add_menu(array(
            'id'    => 'listeo-health-check',
            'title' => '<span class="ab-icon dashicons dashicons-yes-alt" style="margin-top: 2px;"></span><span class="ab-label">' . __('Listeo Health', 'listeo_core') . '</span>',
            'href'  => admin_url('site-health.php?tab=listeo-site-health-tab'),
            'meta'  => array(
                'title' => __('Listeo Site Health Check - Monitor your site status', 'listeo_core'),
                'class' => 'listeo-health-admin-bar'
            ),
        ));
    }

    /**
     * Get current WordPress Heartbeat API status and intervals
     */
    function listeo_get_heartbeat_status() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_heartbeat_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        // Get heartbeat settings from wp-config.php or defaults
        $heartbeat_data = $this->get_heartbeat_configuration();

        wp_send_json_success(array(
            'heartbeat' => $heartbeat_data
        ));
    }

    /**
     * Update WordPress Heartbeat API settings
     */
    function listeo_update_heartbeat_settings() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_heartbeat_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        $action_type = sanitize_text_field($_POST['action_type']);
        
        // Validate action
        if (!in_array($action_type, array('normal', 'optimize', 'development', 'disable_frontend'))) {
            wp_send_json_error(array('message' => __('Invalid action type', 'listeo_core')));
            return;
        }

        $wp_config_path = ABSPATH . 'wp-config.php';
        
        // Check if wp-config.php exists and is writable
        if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
            wp_send_json_error(array('message' => __('wp-config.php is not writable', 'listeo_core')));
            return;
        }

        // Create backup
        $backup_path = $wp_config_path . '.backup.' . time();
        if (!copy($wp_config_path, $backup_path)) {
            wp_send_json_error(array('message' => __('Could not create backup', 'listeo_core')));
            return;
        }

        // Read wp-config.php
        $wp_config_content = file_get_contents($wp_config_path);
        
        if ($wp_config_content === false) {
            wp_send_json_error(array('message' => __('Could not read wp-config.php', 'listeo_core')));
            return;
        }

        $updated_content = $wp_config_content;
        $heartbeat_definitions = array();
        $added_definitions = array();
        
        // Define heartbeat settings based on action
        switch ($action_type) {
            case 'normal':
                $heartbeat_definitions = array(
                    'WP_HEARTBEAT_INTERVAL' => "define( 'WP_HEARTBEAT_INTERVAL', 60 );"
                );
                break;
                
            case 'optimize':
                $heartbeat_definitions = array(
                    'WP_HEARTBEAT_INTERVAL' => "define( 'WP_HEARTBEAT_INTERVAL', 120 );"
                );
                break;
                
            case 'development':
                $heartbeat_definitions = array(
                    'WP_HEARTBEAT_INTERVAL' => "define( 'WP_HEARTBEAT_INTERVAL', 360 );"
                );
                break;
                
            case 'disable_frontend':
                $heartbeat_definitions = array(
                    'WP_HEARTBEAT_DISABLED' => "define( 'WP_HEARTBEAT_DISABLED', true );"
                );
                break;
        }

        // Update or add heartbeat definitions
        foreach ($heartbeat_definitions as $constant_name => $definition) {
            $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant_name, '/') . '[\'"]\s*,\s*[^)]+\)\s*;/';
            
            if (preg_match($pattern, $updated_content)) {
                // Update existing definition
                $updated_content = preg_replace($pattern, $definition, $updated_content);
            } else {
                // Add new definition
                $added_definitions[] = $definition;
            }
        }

        // Add new definitions before "/* That's all, stop editing! Happy publishing. */"
        if (!empty($added_definitions)) {
            $insert_point = "/* That's all, stop editing! Happy publishing. */";
            $new_definitions = "\n// WordPress Heartbeat API Settings\n" . implode("\n", $added_definitions) . "\n\n";
            $updated_content = str_replace($insert_point, $new_definitions . $insert_point, $updated_content);
        }

        // Write updated content
        if (file_put_contents($wp_config_path, $updated_content) === false) {
            wp_send_json_error(array('message' => __('Could not write to wp-config.php', 'listeo_core')));
            return;
        }

        // Create success message based on action
        $messages = array(
            'normal' => __('Heartbeat set to Normal mode (60 seconds). This is the standard WordPress interval with moderate server load.', 'listeo_core'),
            'optimize' => __('Heartbeat set to Safe mode (120 seconds). This significantly reduces server load and improves performance.', 'listeo_core'),
            'development' => __('Heartbeat set to Super Safe mode (360 seconds). This minimizes server load to the maximum extent while maintaining functionality.', 'listeo_core'),
            'disable_frontend' => __('WordPress Heartbeat disabled completely. This eliminates all admin-ajax.php heartbeat requests.', 'listeo_core')
        );
        
        $message = isset($messages[$action_type]) ? $messages[$action_type] : __('Heartbeat settings updated successfully.', 'listeo_core');

        wp_send_json_success(array(
            'message' => $message,
            'backup_created' => basename($backup_path)
        ));
    }

    /**
     * Get current heartbeat configuration and analyze status
     */
    private function get_heartbeat_configuration() {
        // Default WordPress heartbeat interval is 15 seconds in admin, 60 seconds for logged-in users, disabled for logged-out users
        $default_interval = 15;
        $current_interval = $default_interval;

        // Check if custom heartbeat interval is defined
        if (defined('WP_HEARTBEAT_INTERVAL')) {
            $current_interval = WP_HEARTBEAT_INTERVAL;
        }

        // Check if heartbeat is completely disabled
        $is_disabled = defined('WP_HEARTBEAT_DISABLED') && WP_HEARTBEAT_DISABLED;

        // Determine status based on critical thresholds: ≤15s = critical, ≤60s = warning, ≥120s = good
        $status = 'good';
        $message = '';

        if ($is_disabled) {
            $status = 'good';
            $message = __('WordPress Heartbeat is disabled, eliminating server load from admin-ajax.php requests.', 'listeo_core');
            $current_interval = 0; // Show as 0 when disabled
        } elseif ($current_interval <= 15) {
            $status = 'critical';
            $message = __('WordPress Heartbeat API runs frequent admin-ajax.php requests that can cause server overload and high CPU usage. The API performs tasks on a "tick" interval utilizing admin-ajax.php across the dashboard, post editor, and frontend areas.', 'listeo_core');
        } elseif ($current_interval <= 60) {
            $status = 'warning';
            $message = __('Heartbeat interval could be optimized. Consider Safe mode (120s) or Super Safe mode (360s) to reduce server load.', 'listeo_core');
        } else {
            $status = 'good';
            $message = __('Heartbeat interval is well optimized for production use. Server load from admin-ajax.php requests is minimized.', 'listeo_core');
        }

        return array(
            'current_interval' => $current_interval,
            'is_disabled' => $is_disabled,
            'status' => $status,
            'message' => $message,
            'default_interval' => $default_interval
        );
    }

    /**
     * Get database statistics via AJAX
     */
    function listeo_get_database_stats() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_database_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        $stats = array(
            'transients' => $this->get_transient_stats(),
            'revisions' => $this->get_revision_stats(),
            'database' => $this->get_database_size_stats()
        );

        wp_send_json_success($stats);
    }

    /**
     * Get transient statistics
     */
    private function get_transient_stats() {
        global $wpdb;

        // Count all transients
        $total_transients = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%'
        ");

        // Count expired transients
        $expired_transients = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} o1
            JOIN {$wpdb->options} o2 ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
            WHERE o1.option_name LIKE '_transient_%' 
            AND o1.option_name NOT LIKE '_transient_timeout_%'
            AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
        ");

        // Count Listeo-specific transients
        $listeo_transients = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_listeo_%' 
            OR option_name LIKE '_transient_geocode_%'
        ");

        // Calculate total size
        $total_size = $wpdb->get_var("
            SELECT SUM(LENGTH(option_value)) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%'
        ");

        // Count autoloaded transients (problematic ones)
        $autoloaded_transients = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%' 
            AND autoload = 'yes'
        ");

        $status = 'good';
        $message = __('Transient usage is within normal limits.', 'listeo_core');
        
        if ($total_transients > 10000) {
            $status = 'critical';
            $message = __('High number of transients detected. This can severely impact performance.', 'listeo_core');
        } elseif ($total_transients > 5000) {
            $status = 'warning';
            $message = __('Elevated transient count. Consider cleanup for better performance.', 'listeo_core');
        }

        return array(
            'total' => (int) $total_transients,
            'expired' => (int) $expired_transients,
            'listeo_specific' => (int) $listeo_transients,
            'autoloaded' => (int) $autoloaded_transients,
            'total_size' => (int) $total_size,
            'total_size_formatted' => size_format($total_size),
            'status' => $status,
            'message' => $message
        );
    }

    /**
     * Get revision statistics
     */
    private function get_revision_stats() {
        global $wpdb;

        // Count all revisions
        $total_revisions = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'revision'
        ");

        // Count listing revisions specifically
        $listing_revisions = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} r
            JOIN {$wpdb->posts} p ON r.post_parent = p.ID
            WHERE r.post_type = 'revision' 
            AND p.post_type = 'listing'
        ");

        // Count revision meta entries
        $revision_meta_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'revision'
        ");

        // Calculate approximate size impact
        $revision_content_size = $wpdb->get_var("
            SELECT SUM(LENGTH(post_content)) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'revision'
        ");

        $revision_meta_size = $wpdb->get_var("
            SELECT SUM(LENGTH(meta_value)) 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'revision'
        ");

        $total_size = $revision_content_size + $revision_meta_size;

        $status = 'good';
        $message = __('Revision count is within acceptable limits.', 'listeo_core');
        
        if ($total_revisions > 2500) {
            $status = 'critical';
            $message = __('Very high revision count. Database cleanup recommended.', 'listeo_core');
        } elseif ($total_revisions > 1000) {
            $status = 'warning';
            $message = __('High revision count may be impacting query performance.', 'listeo_core');
        }

        return array(
            'total' => (int) $total_revisions,
            'listing_revisions' => (int) $listing_revisions,
            'meta_entries' => (int) $revision_meta_count,
            'total_size' => (int) $total_size,
            'total_size_formatted' => size_format($total_size),
            'status' => $status,
            'message' => $message
        );
    }

    /**
     * Get database size statistics
     */
    private function get_database_size_stats() {
        global $wpdb;

        $database_name = DB_NAME;
        $debug_info = array();
        
        // Try to get table sizes - first attempt with information_schema
        $debug_info['method_attempted'] = 'information_schema';
        $table_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 3) AS size_mb,
                table_rows
            FROM information_schema.tables 
            WHERE table_schema = %s
            AND table_name LIKE %s
            ORDER BY (data_length + index_length) DESC
        ", $database_name, $wpdb->prefix . '%'), ARRAY_A);

        $debug_info['query_error'] = $wpdb->last_error;
        $debug_info['results_count'] = count($table_stats);

        // Fallback method if information_schema fails or returns empty
        if (empty($table_stats) || $wpdb->last_error) {
            $debug_info['fallback_used'] = true;
            $table_stats = $this->get_table_sizes_fallback();
            $debug_info['fallback_results_count'] = count($table_stats);
        }

        // Get specific table info
        $options_size = 0;
        $posts_size = 0;
        $postmeta_size = 0;
        $total_size = 0;
        $debug_info['table_matches'] = array();

        foreach ($table_stats as $table) {
            $table_size = floatval($table['size_mb']);
            $total_size += $table_size;
            
            // Get table name - handle both uppercase and lowercase column names
            $table_name = isset($table['table_name']) ? $table['table_name'] : 
                         (isset($table['TABLE_NAME']) ? $table['TABLE_NAME'] : '');
            
            // Debug table matching
            $expected_options = $wpdb->prefix . 'options';
            $expected_posts = $wpdb->prefix . 'posts';
            $expected_postmeta = $wpdb->prefix . 'postmeta';
            
            // Multiple matching strategies for reliability (with null checks)
            $is_options = false;
            $is_posts = false;
            $is_postmeta = false;
            
            if (!empty($table_name)) {
                $is_options = ($table_name === $expected_options) || 
                             (strcasecmp($table_name, $expected_options) === 0) ||
                             (preg_match('/^.*options$/i', $table_name) && strpos($table_name, $wpdb->prefix) === 0);
                             
                $is_posts = ($table_name === $expected_posts) || 
                           (strcasecmp($table_name, $expected_posts) === 0) ||
                           (preg_match('/^.*posts$/i', $table_name) && strpos($table_name, $wpdb->prefix) === 0 && !preg_match('/postmeta/i', $table_name));
                           
                $is_postmeta = ($table_name === $expected_postmeta) || 
                              (strcasecmp($table_name, $expected_postmeta) === 0) ||
                              (preg_match('/^.*postmeta$/i', $table_name) && strpos($table_name, $wpdb->prefix) === 0);
            }
            
            // Assign sizes based on matches
            if ($is_options) {
                $options_size = $table_size;
                $debug_info['table_matches']['options'] = array(
                    'found' => $table_name,
                    'expected' => $expected_options,
                    'size' => $options_size,
                    'match_method' => 'options_match'
                );
            } elseif ($is_posts) {
                $posts_size = $table_size;
                $debug_info['table_matches']['posts'] = array(
                    'found' => $table_name,
                    'expected' => $expected_posts,
                    'size' => $posts_size,
                    'match_method' => 'posts_match'
                );
            } elseif ($is_postmeta) {
                $postmeta_size = $table_size;
                $debug_info['table_matches']['postmeta'] = array(
                    'found' => $table_name,
                    'expected' => $expected_postmeta,
                    'size' => $postmeta_size,
                    'match_method' => 'postmeta_match'
                );
            }
            
            // Log all tables for debugging
            $table_rows = isset($table['table_rows']) ? $table['table_rows'] : 
                         (isset($table['TABLE_ROWS']) ? $table['TABLE_ROWS'] : 0);
            
            $debug_info['all_tables'][] = array(
                'name' => $table_name,
                'size' => $table_size,
                'rows' => $table_rows
            );
        }

        // Add debug info to error log for troubleshooting
        // Commented out to prevent debug logging in production
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log('Listeo Database Debug: ' . print_r($debug_info, true));
        //     error_log('Listeo Table Stats: ' . print_r($table_stats, true));
        // }

        return array(
            'total_size_mb' => round($total_size, 2),
            'options_size_mb' => round($options_size, 2),
            'posts_size_mb' => round($posts_size, 2),
            'postmeta_size_mb' => round($postmeta_size, 2),
            'tables' => $table_stats,
            'debug' => $debug_info
        );
    }

    /**
     * Fallback method to get table sizes using SHOW TABLE STATUS
     */
    private function get_table_sizes_fallback() {
        global $wpdb;
        
        // Clear any previous errors
        $wpdb->last_error = '';
        
        // Try SHOW TABLE STATUS first
        $tables = $wpdb->get_results("SHOW TABLE STATUS LIKE '{$wpdb->prefix}%'", ARRAY_A);
        $table_stats = array();
        
        if (empty($tables) || $wpdb->last_error) {
            // If SHOW TABLE STATUS fails, try alternative method
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Listeo: SHOW TABLE STATUS failed: ' . $wpdb->last_error);
            }
            
            // Try getting table list and calculate sizes differently
            $table_names = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
            foreach ($table_names as $table_name) {
                // For each table, try to estimate size
                $size_estimate = $this->estimate_table_size($table_name);
                $table_stats[] = array(
                    'table_name' => $table_name,
                    'size_mb' => $size_estimate,
                    'table_rows' => 0 // We can't get this reliably in this method
                );
            }
        } else {
            foreach ($tables as $table) {
                $data_length = isset($table['Data_length']) ? intval($table['Data_length']) : 0;
                $index_length = isset($table['Index_length']) ? intval($table['Index_length']) : 0;
                $total_length = $data_length + $index_length;
                
                // Ensure we have a valid table name
                $table_name = isset($table['Name']) ? $table['Name'] : '';
                if (empty($table_name)) {
                    continue;
                }
                
                $size_mb = round(($total_length / 1024 / 1024), 3);
                
                $table_stats[] = array(
                    'table_name' => $table_name,
                    'size_mb' => $size_mb,
                    'table_rows' => isset($table['Rows']) ? intval($table['Rows']) : 0
                );
                
                // Debug output for troubleshooting
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Listeo: Table {$table_name} - Data: {$data_length}, Index: {$index_length}, Size: {$size_mb}MB");
                }
            }
        }
        
        // Sort by size descending
        usort($table_stats, function($a, $b) {
            return floatval($b['size_mb']) <=> floatval($a['size_mb']);
        });
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Listeo: Fallback method returned ' . count($table_stats) . ' tables');
        }
        
        return $table_stats;
    }
    
    /**
     * Estimate table size when other methods fail
     */
    private function estimate_table_size($table_name) {
        global $wpdb;
        
        // Get row count
        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
        
        if ($row_count === null || $wpdb->last_error) {
            return 0;
        }
        
        // Rough estimation based on table type and row count
        $estimated_bytes_per_row = 1024; // Default 1KB per row
        
        // Adjust estimation based on table type
        if (strpos($table_name, 'postmeta') !== false) {
            $estimated_bytes_per_row = 2048; // Post meta tends to be larger
        } elseif (strpos($table_name, 'posts') !== false) {
            $estimated_bytes_per_row = 3072; // Posts content can be large
        } elseif (strpos($table_name, 'options') !== false) {
            $estimated_bytes_per_row = 512; // Options tend to be smaller
        }
        
        $estimated_size_bytes = $row_count * $estimated_bytes_per_row;
        $estimated_size_mb = round($estimated_size_bytes / 1024 / 1024, 3);
        
        return $estimated_size_mb;
    }

    /**
     * Cleanup transients via AJAX
     */
    function listeo_cleanup_transients() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_cleanup_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        $cleanup_type = sanitize_text_field($_POST['cleanup_type']);
        
        // Validate cleanup type
        if (!in_array($cleanup_type, array('expired', 'all', 'listeo_only'))) {
            wp_send_json_error(array('message' => __('Invalid cleanup type', 'listeo_core')));
            return;
        }

        global $wpdb;
        $deleted_count = 0;

        try {
            switch ($cleanup_type) {
                case 'expired':
                    // Delete expired transients and their timeouts (excluding Google reviews)
                    $expired_transients = $wpdb->get_col("
                        SELECT o1.option_name
                        FROM {$wpdb->options} o1
                        JOIN {$wpdb->options} o2 ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
                        WHERE o1.option_name LIKE '_transient_%'
                        AND o1.option_name NOT LIKE '_transient_timeout_%'
                        AND o1.option_name NOT LIKE '_transient_listeo_reviews_%'
                        AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
                    ");

                    foreach ($expired_transients as $transient_name) {
                        $timeout_name = str_replace('_transient_', '_transient_timeout_', $transient_name);
                        $wpdb->delete($wpdb->options, array('option_name' => $transient_name));
                        $wpdb->delete($wpdb->options, array('option_name' => $timeout_name));
                        $deleted_count++;
                    }
                    break;

                case 'listeo_only':
                    // Delete only Listeo-specific transients (excluding Google reviews)
                    $deleted_count = $wpdb->query("
                        DELETE FROM {$wpdb->options}
                        WHERE (option_name LIKE '_transient_listeo_%'
                        OR option_name LIKE '_transient_timeout_listeo_%'
                        OR option_name LIKE '_transient_geocode_%'
                        OR option_name LIKE '_transient_timeout_geocode_%')
                        AND option_name NOT LIKE '_transient_listeo_reviews_%'
                        AND option_name NOT LIKE '_transient_timeout_listeo_reviews_%'
                    ");
                    break;

                case 'all':
                    // Delete all transients (excluding Google reviews to prevent API quota issues)
                    $deleted_count = $wpdb->query("
                        DELETE FROM {$wpdb->options}
                        WHERE option_name LIKE '_transient_%'
                        AND option_name NOT LIKE '_transient_listeo_reviews_%'
                        AND option_name NOT LIKE '_transient_timeout_listeo_reviews_%'
                    ");
                    break;
            }

            // Set autoload to 'no' for remaining transients
            $wpdb->query("
                UPDATE {$wpdb->options} 
                SET autoload = 'no' 
                WHERE option_name LIKE '_transient_%' 
                AND autoload = 'yes'
            ");

            $message = sprintf(__('Successfully cleaned up %d transient entries.', 'listeo_core'), $deleted_count);
            
            wp_send_json_success(array(
                'message' => $message,
                'deleted_count' => $deleted_count
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error during cleanup: ', 'listeo_core') . $e->getMessage()));
        }
    }

    /**
     * Cleanup revisions via AJAX
     */
    function listeo_cleanup_revisions() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_cleanup_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
            return;
        }

        $cleanup_type = sanitize_text_field($_POST['cleanup_type']);
        $keep_revisions = (int) $_POST['keep_revisions'];
        
        // Validate parameters
        if (!in_array($cleanup_type, array('keep_recent', 'all', 'listing_only'))) {
            wp_send_json_error(array('message' => __('Invalid cleanup type', 'listeo_core')));
            return;
        }

        if ($keep_revisions < 0 || $keep_revisions > 10) {
            wp_send_json_error(array('message' => __('Invalid number of revisions to keep', 'listeo_core')));
            return;
        }

        global $wpdb;
        $deleted_count = 0;

        try {
            switch ($cleanup_type) {
                case 'keep_recent':
                    // Keep specified number of recent revisions per post
                    if ($keep_revisions > 0) {
                        $old_revisions = $wpdb->get_col("
                            SELECT r1.ID 
                            FROM {$wpdb->posts} r1
                            WHERE r1.post_type = 'revision'
                            AND (
                                SELECT COUNT(*) 
                                FROM {$wpdb->posts} r2 
                                WHERE r2.post_parent = r1.post_parent 
                                AND r2.post_type = 'revision' 
                                AND r2.post_date > r1.post_date
                            ) >= {$keep_revisions}
                        ");
                    } else {
                        // Keep 0 means delete all
                        $old_revisions = $wpdb->get_col("
                            SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'
                        ");
                    }

                    foreach ($old_revisions as $revision_id) {
                        wp_delete_post_revision($revision_id);
                        $deleted_count++;
                    }
                    break;

                case 'listing_only':
                    // Delete only listing revisions
                    $listing_revisions = $wpdb->get_col("
                        SELECT r.ID 
                        FROM {$wpdb->posts} r
                        JOIN {$wpdb->posts} p ON r.post_parent = p.ID
                        WHERE r.post_type = 'revision' 
                        AND p.post_type = 'listing'
                    ");

                    foreach ($listing_revisions as $revision_id) {
                        wp_delete_post_revision($revision_id);
                        $deleted_count++;
                    }
                    break;

                case 'all':
                    // Delete all revisions
                    $all_revisions = $wpdb->get_col("
                        SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'
                    ");

                    foreach ($all_revisions as $revision_id) {
                        wp_delete_post_revision($revision_id);
                        $deleted_count++;
                    }
                    break;
            }

            // Clean up orphaned meta data
            $wpdb->query("
                DELETE pm FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.ID IS NULL
            ");

            $message = sprintf(__('Successfully cleaned up %d revision entries.', 'listeo_core'), $deleted_count);
            
            wp_send_json_success(array(
                'message' => $message,
                'deleted_count' => $deleted_count
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error during cleanup: ', 'listeo_core') . $e->getMessage()));
        }
    }

    /**
     * Test booking emails with sample data - sends directly without hooks
     */
    function listeo_test_booking_email() {
        try {
            // Security checks
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Insufficient permissions', 'listeo_core')));
                return;
            }

            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'listeo_booking_email_test_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
                return;
            }

            $email_type = sanitize_text_field($_POST['email_type']);

            // Get sample booking data
            $sample_booking = $this->get_sample_booking_data();

            if (!$sample_booking) {
                wp_send_json_error(array('message' => __('No bookings found in database. Please create at least one booking first.', 'listeo_core')));
                return;
            }

            $admin_email = get_option('admin_email');

            // Check if Listeo_Core_Emails class exists
            if (!class_exists('Listeo_Core_Emails')) {
                wp_send_json_error(array('message' => __('Listeo_Core_Emails class not found. Please make sure Listeo Core plugin is active.', 'listeo_core')));
                return;
            }

            // Get booking formatted data
            $email_class = Listeo_Core_Emails::instance();

            if (!method_exists($email_class, 'get_booking_data_emails')) {
                wp_send_json_error(array('message' => __('Email method not found. Please update Listeo Core plugin.', 'listeo_core')));
                return;
            }

            $booking_data = $email_class->get_booking_data_emails($sample_booking);

            // Build email content with template or fallback
            $email_content = $this->build_test_email_content($email_type, $sample_booking, $booking_data);

            if (!is_array($email_content) || count($email_content) < 3) {
                wp_send_json_error(array('message' => __('Failed to build email content', 'listeo_core')));
                return;
            }

            $subject = $email_content[0];
            $body = $email_content[1];
            $email_name = $email_content[2];

            if (empty($subject) || empty($body)) {
                wp_send_json_error(array(
                    'message' => __('Email templates not configured. Please configure email templates in Listeo Settings → Emails first.', 'listeo_core')
                ));
                return;
            }

            // Send email directly
            $from_name = get_option('listeo_emails_name', get_bloginfo('name'));
            $from_email = get_option('listeo_emails_from_email', get_bloginfo('admin_email'));
            $headers = sprintf("From: %s <%s>\r\n Content-type: text/html", $from_name, $from_email);

            // Check if template loader class exists
            if (!class_exists('listeo_core_Template_Loader')) {
                wp_send_json_error(array('message' => __('Template loader class not found. Please make sure Listeo Core plugin is properly installed.', 'listeo_core')));
                return;
            }

            // Wrap in email template
            $template_loader = new listeo_core_Template_Loader();
            ob_start();

            try {
                $template_loader->get_template_part('emails/header');
                ?>
                <tr>
                    <td align="left" valign="top" style="border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 25px; padding-right: 25px; padding-bottom: 28px; width: 87.5%; font-size: 16px; font-weight: 400; padding-top: 28px; color: #666; font-family: sans-serif;" class="paragraph">
                        <?php echo $body; ?>
                    </td>
                </tr>
                <?php
                $template_loader->get_template_part('emails/footer');
            } catch (Exception $e) {
                ob_end_clean();
                wp_send_json_error(array('message' => __('Error loading email template: ', 'listeo_core') . $e->getMessage()));
                return;
            }

            $content = ob_get_clean();

            // Send the email
            $sent = wp_mail($admin_email, $subject, $content, $headers);

            if ($sent) {
                wp_send_json_success(array(
                    'message' => sprintf(
                        __('Test email "%s" sent successfully to %s. Please check your inbox and WP Mail Log.', 'listeo_core'),
                        $email_name,
                        $admin_email
                    )
                ));
            } else {
                global $phpmailer;
                $error_info = '';
                if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                    $error_info = ' Error: ' . $phpmailer->ErrorInfo;
                }
                wp_send_json_error(array(
                    'message' => __('Failed to send email.', 'listeo_core') . $error_info
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Unexpected error: ', 'listeo_core') . $e->getMessage()
            ));
        } catch (Error $e) {
            wp_send_json_error(array(
                'message' => __('Fatal error: ', 'listeo_core') . $e->getMessage()
            ));
        }
    }

    /**
     * Build email content for testing
     */
    private function build_test_email_content($email_type, $booking, $booking_data) {
        // Prepare replacement data
        $listing_id = $booking['listing_id'];
        $user_id = $booking['bookings_author'];

        $replace_data = array(
            'user_name' => get_the_author_meta('display_name', $user_id),
            'user_mail' => get_the_author_meta('user_email', $user_id),
            'booking_date' => $booking['created'],
            'listing_name' => get_the_title($listing_id),
            'listing_url' => get_permalink($listing_id),
            'listing_address' => get_post_meta($listing_id, '_address', true),
            'listing_phone' => get_post_meta($listing_id, '_phone', true),
            'listing_email' => get_post_meta($listing_id, '_email', true),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'dates' => isset($booking_data['dates']) ? $booking_data['dates'] : '',
            'details' => isset($booking_data['details']) ? $booking_data['details'] : '',
            'service' => isset($booking_data['service']) ? $booking_data['service'] : '',
            'tickets' => isset($booking_data['tickets']) ? $booking_data['tickets'] : '',
            'adults' => isset($booking_data['adults']) ? $booking_data['adults'] : '',
            'children' => isset($booking_data['children']) ? $booking_data['children'] : '',
            'user_message' => isset($booking_data['user_message']) ? $booking_data['user_message'] : '',
            'client_first_name' => isset($booking_data['client_first_name']) ? $booking_data['client_first_name'] : '',
            'client_last_name' => isset($booking_data['client_last_name']) ? $booking_data['client_last_name'] : '',
            'client_email' => isset($booking_data['client_email']) ? $booking_data['client_email'] : '',
            'client_phone' => isset($booking_data['client_phone']) ? $booking_data['client_phone'] : '',
            'billing_address' => isset($booking_data['billing_address']) ? $booking_data['billing_address'] : '',
            'billing_postcode' => isset($booking_data['billing_postcode']) ? $booking_data['billing_postcode'] : '',
            'billing_city' => isset($booking_data['billing_city']) ? $booking_data['billing_city'] : '',
            'billing_country' => isset($booking_data['billing_country']) ? $booking_data['billing_country'] : '',
            'price' => isset($booking['price']) ? $booking['price'] : '',
            'payment_url' => home_url('/booking-confirmation/'),
            'expiration' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        );

        // Get subject and body from options based on email type
        $option_map = array(
            'booking_user_waiting_approval' => array(
                'subject' => 'listeo_booking_user_waiting_approval_email_subject',
                'content' => 'listeo_booking_user_waiting_approval_email_content',
                'name' => __('Booking Confirmation (Waiting Approval)', 'listeo_core')
            ),
            'instant_booking_user' => array(
                'subject' => 'listeo_instant_booking_user_waiting_approval_email_subject',
                'content' => 'listeo_instant_booking_user_waiting_approval_email_content',
                'name' => __('Instant Booking Confirmation', 'listeo_core')
            ),
            'booking_owner_new' => array(
                'subject' => 'listeo_booking_owner_new_booking_email_subject',
                'content' => 'listeo_booking_owner_new_booking_email_content',
                'name' => __('New Booking Request', 'listeo_core')
            ),
            'instant_booking_owner' => array(
                'subject' => 'listeo_booking_instant_owner_new_booking_email_subject',
                'content' => 'listeo_booking_instant_owner_new_booking_email_content',
                'name' => __('New Instant Booking', 'listeo_core')
            ),
            'booking_confirmed_free' => array(
                'subject' => 'listeo_free_booking_confirmation_email_subject',
                'content' => 'listeo_free_booking_confirmation_email_content',
                'name' => __('Free Booking Confirmed', 'listeo_core')
            ),
            'booking_confirmed_cash' => array(
                'subject' => 'listeo_mail_to_user_pay_cash_confirmed_email_subject',
                'content' => 'listeo_mail_to_user_pay_cash_confirmed_email_content',
                'name' => __('Pay with Cash Booking Confirmed', 'listeo_core')
            ),
            'booking_pay' => array(
                'subject' => 'listeo_pay_booking_confirmation_email_subject',
                'content' => 'listeo_pay_booking_confirmation_email_content',
                'name' => __('Payment Required', 'listeo_core')
            ),
            'booking_paid_owner' => array(
                'subject' => 'listeo_paid_booking_confirmation_email_subject',
                'content' => 'listeo_paid_booking_confirmation_email_content',
                'name' => __('Booking Paid (Owner)', 'listeo_core')
            ),
            'booking_paid_user' => array(
                'subject' => 'listeo_user_paid_booking_confirmation_email_subject',
                'content' => 'listeo_user_paid_booking_confirmation_email_content',
                'name' => __('Booking Paid (User)', 'listeo_core')
            ),
            'booking_cancelled_user' => array(
                'subject' => 'listeo_cancelled_booking_confirmation_email_subject',
                'content' => 'listeo_cancelled_booking_confirmation_email_content',
                'name' => __('Booking Cancelled (User)', 'listeo_core')
            ),
            'booking_cancelled_owner' => array(
                'subject' => 'listeo_owner_cancelled_booking_confirmation_email_subject',
                'content' => 'listeo_owner_cancelled_booking_confirmation_email_content',
                'name' => __('Booking Cancelled (Owner)', 'listeo_core')
            ),
            'booking_reminder' => array(
                'subject' => 'listeo_user_booking_reminder_email_subject',
                'content' => 'listeo_user_booking_reminder_email_content',
                'name' => __('Upcoming Booking Reminder', 'listeo_core')
            ),
            'booking_review_reminder' => array(
                'subject' => 'listeo_listing_remind_review_email_subject',
                'content' => 'listeo_listing_remind_review_email_content',
                'name' => __('Review Reminder', 'listeo_core')
            ),
        );

        if (!isset($option_map[$email_type])) {
            return array('', '', '');
        }

        $options = $option_map[$email_type];
        $subject = get_option($options['subject'], '');
        $body = get_option($options['content'], '');

        // Replace tags in subject and body
        foreach ($replace_data as $key => $value) {
            $subject = str_replace('{' . $key . '}', $value, $subject);
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        return array(html_entity_decode($subject), $body, $options['name']);
    }

    /**
     * Get sample booking data from database or create sample data
     */
    private function get_sample_booking_data() {
        global $wpdb;

        // Try to get a real booking from database
        $booking_table = $wpdb->prefix . 'bookings_calendar';

        $booking = $wpdb->get_row(
            "SELECT * FROM {$booking_table} WHERE status = 'confirmed' ORDER BY ID DESC LIMIT 1",
            ARRAY_A
        );

        // If no confirmed booking, get any booking
        if (!$booking) {
            $booking = $wpdb->get_row(
                "SELECT * FROM {$booking_table} ORDER BY ID DESC LIMIT 1",
                ARRAY_A
            );
        }

        // If still no booking, create sample data
        if (!$booking) {
            // Get a sample listing
            $listing_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'listing' AND post_status = 'publish' LIMIT 1");

            if (!$listing_id) {
                return false; // No listings exist
            }

            $booking = array(
                'ID' => 99999, // Fake ID for testing
                'bookings_author' => get_current_user_id(),
                'owner_id' => get_current_user_id(),
                'listing_id' => $listing_id,
                'date_start' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'date_end' => date('Y-m-d H:i:s', strtotime('+8 days')),
                'status' => 'confirmed',
                'order_id' => 0,
                'created' => current_time('mysql'),
                'expiring' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                'price' => '150.00',
                'comment' => json_encode(array(
                    'adults' => 2,
                    'children' => 1,
                    'tickets' => 3,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => get_option('admin_email'),
                    'phone' => '+1 234 567 890',
                    'message' => 'This is a test booking for email testing purposes.',
                    'billing_address_1' => '123 Test Street',
                    'billing_postcode' => '12345',
                    'billing_city' => 'Test City',
                    'billing_country' => 'Test Country',
                    'service' => array()
                ))
            );
        }

        return $booking;
    }

}


