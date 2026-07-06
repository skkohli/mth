<?php
if (!current_user_can('view_site_health_checks')) {
    wp_die(__('Sorry, you are not allowed to access site health information.'), '', 403);
}

wp_enqueue_style('site-health');
wp_enqueue_script('site-health');
wp_enqueue_style('listeo-site-health-tab', plugin_dir_url(__FILE__) . 'site-health-tab.css', array(), '1.0.0');

// Get system information
global $wp_version;
$php_version = phpversion();
$wordpress_version = $wp_version;

// Get pages info
$pages = listeo_core_get_dashboard_pages_list();
$existing_pages = 0;
$total_pages = count($pages);
foreach ($pages as $page) {
    if (get_option($page['option']) && ('publish' === get_post_status(get_option($page['option'])))) {
        $existing_pages++;
    }
}

// Get plugins info
$all_plugins = get_plugins();
$active_plugins = get_option('active_plugins', array());
$required_plugins = array(
    'listeo-core/listeo-core.php',
    'listeo-shortcodes/listeo-shortcodes.php', 
    'listeo-forms-and-fields-editor/listeo-forms-and-fields-editor.php',
    'cmb2/init.php',
    'woocommerce/woocommerce.php'
);
$active_required = 0;
foreach ($required_plugins as $plugin) {
    if (in_array($plugin, $active_plugins) || is_plugin_active($plugin)) {
        $active_required++;
    }
}

// Memory information
$wp_memory_limit = wp_convert_hr_to_bytes(WP_MEMORY_LIMIT);
$wp_memory_usage = memory_get_usage(true);
$php_memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
$php_memory_usage = memory_get_peak_usage(true);
$max_execution_time = ini_get('max_execution_time');
$upload_max_filesize = wp_max_upload_size();

?>




    <div class="listeo-health-check-title-section">
        <h1>
            <?php _e('Listeo Site Health'); ?>
        </h1>
    </div>

<div class="health-check-body health-check-debug-tab hide-if-no-js">
    
    <div class="listeo-health-grid">
        <!-- Quick Stats Section -->
        <div class="listeo-health-section">
            <h2><?php _e('Quick Stats', 'listeo_core'); ?></h2>
            <div class="listeo-stats-cards">
                <div class="listeo-stat-card">
                    <h3><?php _e('PHP Version', 'listeo_core'); ?></h3>
                    <div class="listeo-stat-value <?php echo version_compare($php_version, '8.0', '>=') ? 'stat-good' : (version_compare($php_version, '7.4', '>=') ? 'stat-warning' : 'stat-critical'); ?>">
                        <?php echo $php_version; ?>
                    </div>
                    <div class="listeo-stat-label">
                        <?php echo version_compare($php_version, '8.0', '>=') ? __('Excellent', 'listeo_core') : (version_compare($php_version, '7.4', '>=') ? __('Good', 'listeo_core') : __('Outdated', 'listeo_core')); ?>
                    </div>
                </div>
                
                <div class="listeo-stat-card">
                    <h3><?php _e('WordPress', 'listeo_core'); ?></h3>
                    <div class="listeo-stat-value <?php echo version_compare($wordpress_version, '6.0', '>=') ? 'stat-good' : 'stat-warning'; ?>">
                        <?php echo $wordpress_version; ?>
                    </div>
                    <div class="listeo-stat-label">
                        <?php echo version_compare($wordpress_version, '6.0', '>=') ? __('Latest', 'listeo_core') : __('Consider Update', 'listeo_core'); ?>
                    </div>
                </div>
                
                
                <div class="listeo-stat-card">
                    <h3><?php _e('Theme Version', 'listeo_core'); ?></h3>
                    <div class="listeo-stat-value stat-good">
                        <?php echo wp_get_theme()->get('Version'); ?>
                    </div>
                    <div class="listeo-stat-label">
                        <?php echo __('Listeo Theme', 'listeo_core'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plugin Versions Section -->
        <div class="listeo-health-section">
            <h2><?php _e('Plugin Versions', 'listeo_core'); ?></h2>
    <div class="listeo-stats-cards">
        <?php
        // Get TGMPA instance to read plugin configurations
        $tgmpa_plugins = array();
        if (function_exists('listeo_register_required_plugins')) {
            // Hook into TGMPA to get plugin configurations
            if (class_exists('TGM_Plugin_Activation') && isset($GLOBALS['tgmpa'])) {
                $tgmpa_instance = $GLOBALS['tgmpa'];
                if (isset($tgmpa_instance->plugins)) {
                    foreach ($tgmpa_instance->plugins as $plugin) {
                        if (isset($plugin['slug']) && isset($plugin['version'])) {
                            $tgmpa_plugins[$plugin['slug']] = array(
                                'name' => $plugin['name'],
                                'version' => $plugin['version']
                            );
                        }
                    }
                }
            }
        }
        
        // Define plugin mappings (slug to actual plugin file)
        $plugin_mappings = array(
            'listeo-core' => array(
                'file' => 'listeo-core/listeo-core.php',
                'name' => __('Listeo Core', 'listeo_core')
            ),
            'listeo-elementor' => array(
                'file' => 'listeo-elementor/listeo-elementor.php',
                'name' => __('Listeo Elementor', 'listeo_core')
            ),
            'listeo-forms-and-fields-editor' => array(
                'file' => 'listeo-forms-and-fields-editor/listeo-forms-and-fields-editor.php',
                'name' => __('Forms & Fields Editor', 'listeo_core')
            )
        );
        
        foreach ($plugin_mappings as $slug => $plugin_info) {
            $plugin_file = $plugin_info['file'];
            $plugin_name = $plugin_info['name'];
            $expected_version = isset($tgmpa_plugins[$slug]) ? $tgmpa_plugins[$slug]['version'] : 'Unknown';
            
            if (is_plugin_active($plugin_file)) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
                $current_version = $plugin_data['Version'];
                $is_updated = ($expected_version !== 'Unknown') ? version_compare($current_version, $expected_version, '>=') : true;
                $status_class = $is_updated ? 'stat-good' : 'stat-warning';
                ?>
                <div class="listeo-stat-card">
                    <h3><?php echo $plugin_name; ?></h3>
                    <div class="listeo-stat-value <?php echo !$is_updated ? 'stat-critical' : 'stat-good'; ?>">
                        <?php echo $current_version; ?>
                    </div>
                    <div class="listeo-stat-label">
                        <?php 
                        if ($expected_version !== 'Unknown') {
                            echo $is_updated ? __('Up to Date', 'listeo_core') : sprintf(__('Expected: %s', 'listeo_core'), $expected_version);
                        } else {
                            echo __('Active', 'listeo_core');
                        }
                        ?>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <div class="listeo-stat-card">
                    <h3><?php echo $plugin_name; ?></h3>
                    <div class="listeo-stat-value stat-critical">
                        <?php _e('N/A', 'listeo_core'); ?>
                    </div>
                    <div class="listeo-stat-label">
                        <?php _e('Not Active', 'listeo_core'); ?>
                    </div>
                </div>
                <?php
            }
        }
        ?>
            </div>
        </div>

        <!-- Memory Usage Section -->
        <div class="listeo-health-section">
            <h2><?php _e('Memory Usage', 'listeo_core'); ?></h2>
    <div class="listeo-memory-bars">
        
        <!-- WordPress Memory -->
        <?php
        $wp_memory_percent = ($wp_memory_usage / $wp_memory_limit) * 100;
        $wp_memory_class = $wp_memory_percent > 80 ? 'memory-critical' : ($wp_memory_percent > 60 ? 'memory-warning' : 'memory-good');
        $wp_memory_mb = $wp_memory_limit / (1024 * 1024);
        ?>
        <div class="listeo-memory-item">
            <div class="listeo-memory-label">
                <span><?php _e('WordPress Memory', 'listeo_core'); ?></span>
                <span><?php echo size_format($wp_memory_usage) . ' / ' . size_format($wp_memory_limit); ?> (<?php echo round($wp_memory_percent, 1); ?>%)</span>
            </div>
            <div class="listeo-memory-bar">
                <div class="listeo-memory-progress <?php echo $wp_memory_class; ?>" style="width: <?php echo min($wp_memory_percent, 100); ?>%"></div>
                <div class="listeo-memory-text"><?php echo round($wp_memory_percent, 1); ?>%</div>
            </div>
            <?php if ($wp_memory_mb < 256): ?>
                <div class="listeo-memory-warning <?php echo ($wp_memory_mb < 128) ? 'critical' : 'warning'; ?>">
                    <div class="listeo-memory-warning-content">
                        <strong><?php echo ($wp_memory_mb < 128) ? __('Critical:', 'listeo_core') : __('Warning:', 'listeo_core'); ?></strong>
                        <?php 
                        if ($wp_memory_mb < 128) {
                            printf(__('Only %s available. Listeo requires 256MB+ for optimal performance.', 'listeo_core'), size_format($wp_memory_limit));
                        } else {
                            printf(__('Currently %s. Recommend 256MB+ for all Listeo features.', 'listeo_core'), size_format($wp_memory_limit));
                        }
                        ?>
                    </div>
                    <?php if (current_user_can('manage_options') && is_writable(ABSPATH . 'wp-config.php')): ?>
                        <div class="listeo-memory-fix-container">
                            <button type="button" class="button listeo-memory-limit-fix critical-button" data-memory-limit="512M">
                                <?php _e('Fix Memory Limit (512MB)', 'listeo_core'); ?>
                            </button>
                        </div>
                    <?php elseif (!is_writable(ABSPATH . 'wp-config.php')): ?>
                        <div class="listeo-memory-note">
                            <?php _e('Note: wp-config.php is not writable. Contact your host or set file permissions to enable automatic fixing.', 'listeo_core'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- PHP Memory -->
        <?php
        $php_memory_percent = ($php_memory_usage / $php_memory_limit) * 100;
        $php_memory_class = $php_memory_percent > 80 ? 'memory-critical' : ($php_memory_percent > 60 ? 'memory-warning' : 'memory-good');
        ?>
        <div class="listeo-memory-item">
            <div class="listeo-memory-label">
                <span><?php _e('PHP Memory (Peak)', 'listeo_core'); ?></span>
                <span><?php echo size_format($php_memory_usage) . ' / ' . size_format($php_memory_limit); ?> (<?php echo round($php_memory_percent, 1); ?>%)</span>
            </div>
            <div class="listeo-memory-bar">
                <div class="listeo-memory-progress <?php echo $php_memory_class; ?>" style="width: <?php echo min($php_memory_percent, 100); ?>%"></div>
                <div class="listeo-memory-text"><?php echo round($php_memory_percent, 1); ?>%</div>
            </div>
        </div>

        <!-- Execution Time -->
        <?php
        $recommended_time = 300; // 5 minutes recommended
        $time_percent = ($max_execution_time / $recommended_time) * 100;
        $time_class = $max_execution_time < 120 ? 'memory-warning' : ($max_execution_time >= $recommended_time ? 'memory-good' : 'memory-warning');
        ?>
        <div class="listeo-memory-item">
            <div class="listeo-memory-label">
                <span><?php _e('Max Execution Time', 'listeo_core'); ?></span>
                <span><?php echo $max_execution_time; ?>s / <?php echo $recommended_time; ?>s (<?php _e('Recommended', 'listeo_core'); ?>)</span>
            </div>
            <div class="listeo-memory-bar">
                <div class="listeo-memory-progress <?php echo $time_class; ?>" style="width: <?php echo min($time_percent, 100); ?>%"></div>
                <div class="listeo-memory-text"><?php echo $max_execution_time; ?>s</div>
            </div>
        </div>

        <!-- Upload Size -->
        <?php
        $recommended_upload = 64 * 1024 * 1024; // 64MB recommended
        $upload_percent = ($upload_max_filesize / $recommended_upload) * 100;
        $upload_class = $upload_max_filesize < (32 * 1024 * 1024) ? 'memory-warning' : ($upload_max_filesize >= $recommended_upload ? 'memory-good' : 'memory-warning');
        ?>
        <div class="listeo-memory-item">
            <div class="listeo-memory-label">
                <span><?php _e('Max Upload Size', 'listeo_core'); ?></span>
                <span><?php echo size_format($upload_max_filesize) . ' / ' . size_format($recommended_upload); ?> (<?php _e('Recommended', 'listeo_core'); ?>)</span>
            </div>
            <div class="listeo-memory-bar">
                <div class="listeo-memory-progress <?php echo $upload_class; ?>" style="width: <?php echo min($upload_percent, 100); ?>%"></div>
                <div class="listeo-memory-text"><?php echo size_format($upload_max_filesize); ?></div>
            </div>
        </div>
        
            </div>
        </div>

        <!-- WordPress Heartbeat API Section -->
        <div class="listeo-health-section listeo-heartbeat-section">
            <h2><?php _e('WordPress Heartbeat API', 'listeo_core'); ?></h2>
            
            <!-- Combined Status and Interval Display -->
            <div class="listeo-heartbeat-box heartbeat-interval-display" id="heartbeat-status-banner">
                <div class="listeo-heartbeat-header">
                    <h4><?php _e('Current Heartbeat Status', 'listeo_core'); ?></h4>
                </div>
                <div class="heartbeat-combined-content">
                    <div class="heartbeat-current-status">
                        <span class="status-indicator status-warning" id="heartbeat-indicator"></span>
                        <div class="heartbeat-interval-value">
                            <span id="heartbeat-interval" class="interval-number">--</span>
                        </div>
                    </div>
                    <div class="heartbeat-status-text">
                        <strong id="heartbeat-verdict"><?php _e('Loading...', 'listeo_core'); ?></strong>
                        <span id="heartbeat-message"><?php _e('Checking heartbeat settings...', 'listeo_core'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="listeo-heartbeat-box">
                <div class="listeo-heartbeat-header">
                    <h4><?php _e('Actions', 'listeo_core'); ?></h4>
                </div>
                
                <!-- Control Buttons -->
                <div class="heartbeat-controls">
                    <button type="button" class="heartbeat-btn" data-action="normal">
                        <?php _e('Normal (60s)', 'listeo_core'); ?>
                    </button>
                    <button type="button" class="heartbeat-btn" data-action="optimize">
                        <?php _e('Safe (120s)', 'listeo_core'); ?>
                    </button>
                    <button type="button" class="heartbeat-btn" data-action="development">
                        <?php _e('Super Safe (360s)', 'listeo_core'); ?>
                    </button>
                    <button type="button" class="heartbeat-btn" data-action="disable_frontend">
                        <?php _e('Disable Heartbeat', 'listeo_core'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Debug & Error Management Section -->
        <div class="listeo-health-section">
            <h2><?php _e('Debug & Error Management', 'listeo_core'); ?></h2>
    <div class="listeo-debug-section">
        
        <!-- Debug Status Display -->
        <div class="listeo-debug-grid">
            <?php 
            $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
            $debug_log_enabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
            $debug_display_enabled = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;
            ?>
            
            <div class="listeo-debug-status <?php echo $debug_enabled ? 'status-warning' : 'status-good'; ?>">
                <strong><?php _e('Core Debug:', 'listeo_core'); ?></strong><br>
                <span class="listeo-debug-status-text <?php echo $debug_enabled ? 'enabled' : ''; ?>">
                    <?php echo $debug_enabled ? __('ON', 'listeo_core') : __('OFF', 'listeo_core'); ?>
                </span>
            </div>
            
            <div class="listeo-debug-status <?php echo $debug_log_enabled ? 'status-warning' : 'status-good'; ?>">
                <strong><?php _e('Error Logging:', 'listeo_core'); ?></strong><br>
                <span class="listeo-debug-status-text <?php echo $debug_log_enabled ? 'enabled' : ''; ?>">
                    <?php echo $debug_log_enabled ? __('ON', 'listeo_core') : __('OFF', 'listeo_core'); ?>
                </span>
            </div>
            
            <div class="listeo-debug-status <?php echo $debug_display_enabled ? 'status-error' : 'status-good'; ?>">
                <strong><?php _e('Frontend Display:', 'listeo_core'); ?></strong><br>
                <span class="listeo-debug-status-text <?php echo $debug_display_enabled ? 'critical' : ''; ?>">
                    <?php echo $debug_display_enabled ? __('ON', 'listeo_core') : __('OFF', 'listeo_core'); ?>
                </span>
            </div>
        </div>

        <!-- Debug Control Buttons -->
        <?php if (current_user_can('manage_options') && is_writable(ABSPATH . 'wp-config.php')): ?>
            <div class="listeo-debug-actions">
                <h4><?php _e('Quick Actions:', 'listeo_core'); ?></h4>
                <div class="listeo-debug-buttons">
                    
                    <!-- Enable Full Debug for Troubleshooting -->
                    <button type="button" class="button listeo-debug-control enable-full" data-debug-action="enable_full">
                        <?php _e('Enable Full Debug Mode', 'listeo_core'); ?>
                    </button>
                    
                    <!-- Disable All Debug -->
                    <button type="button" class="button listeo-debug-control disable-all" data-debug-action="disable_all">
                        <?php _e('Turn Off All Debug', 'listeo_core'); ?>
                    </button>
                    
                    <!-- Enable Logging Only -->
                    <button type="button" class="button listeo-debug-control enable-logging" data-debug-action="enable_logging">
                        <?php _e('Log Errors Only', 'listeo_core'); ?>
                    </button>
                    
                    <!-- Disable Frontend Display -->
                    <button type="button" class="button listeo-debug-control disable-display" data-debug-action="disable_display">
                         <?php _e('Hide Frontend Errors', 'listeo_core'); ?>
                    </button>
                    
                </div>
            </div>
            
            <div class="listeo-cleanup-descriptions">
                <strong style="color:#d63638"><?php _e('Enable Full Debug Mode:', 'listeo_core'); ?></strong> <?php _e('Turns on all debugging features for complete troubleshooting', 'listeo_core'); ?><br>
                <strong style="color: #00a32a;"><?php _e('Turn Off All Debug:', 'listeo_core'); ?></strong> <?php _e('Disables all debug features for production use', 'listeo_core'); ?><br>
                <strong style="color: #dba617;"><?php _e('Log Errors Only:', 'listeo_core'); ?></strong> <?php _e('Enables error logging without showing errors to visitors', 'listeo_core'); ?><br>
                <strong><?php _e('Hide Frontend Errors:', 'listeo_core'); ?></strong> <?php _e('Prevents errors from displaying on the frontend', 'listeo_core'); ?>
            </div>
        <?php endif; ?>

        <!-- Recent Critical Errors -->
        <h4><?php _e('Recent Critical Errors (Last 24h)', 'listeo_core'); ?></h4>
        
        <?php
        // Pre-scan to determine Listeo error status for display at top
        $listeo_prescan_found = false;
        $all_prescan_found = false;
        $yesterday_prescan = strtotime('-24 hours');
        
        // Define Listeo-related paths and identifiers - must be more specific
        $listeo_identifiers_prescan = array(
            '/themes/listeo/',
            '/plugins/listeo-core/',
            '/plugins/listeo-elementor/',
            '/plugins/listeo-forms-and-fields-editor/',
            '/plugins/listeo-shortcodes/',
            '/plugins/listeo-ai-search/',
            '/plugins/listeo-data-scraper/',
            '/plugins/listeo-sms/',
            '/plugins/purethemes-cpt/',
            'listeo-core',
            'listeo-elementor',
            'listeo-forms-and-fields-editor',
            'listeo-shortcodes',
            'listeo-ai-search',
            'listeo-data-scraper',
            'listeo-sms',
            'purethemes-cpt'
        );
        
        // Quick prescan for status
        $error_log_path_prescan = ini_get('error_log');
        $wp_debug_log_prescan = WP_CONTENT_DIR . '/debug.log';
        $log_files_prescan = array();
        if (file_exists($wp_debug_log_prescan) && is_readable($wp_debug_log_prescan)) {
            $log_files_prescan[] = $wp_debug_log_prescan;
        }
        if ($error_log_path_prescan && file_exists($error_log_path_prescan) && is_readable($error_log_path_prescan) && $error_log_path_prescan !== $wp_debug_log_prescan) {
            $log_files_prescan[] = $error_log_path_prescan;
        }
        
        foreach ($log_files_prescan as $log_file_prescan) {
            if (filesize($log_file_prescan) > 10 * 1024 * 1024) continue;
            $lines_prescan = file($log_file_prescan, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines_prescan) {
                $lines_prescan = array_reverse($lines_prescan);
                $count_prescan = 0;
                foreach ($lines_prescan as $line_prescan) {
                    if ($count_prescan >= 50) break;
                    if (preg_match('/\[(.*?)\].*?(Fatal error|PHP Fatal error|Uncaught|Parse error|PHP Parse error|Call to undefined|Cannot redeclare|Class .* not found|Interface .* not found|Trait .* not found|Maximum execution time|Allowed memory size|require\(\): Failed opening|include\(\): Failed opening|Call to a member function .* on null|Call to a member function .* on bool)/i', $line_prescan, $matches_prescan)) {
                        $timestamp_str_prescan = trim($matches_prescan[1]);
                        $error_timestamp_prescan = strtotime($timestamp_str_prescan);
                        if ($error_timestamp_prescan && $error_timestamp_prescan > $yesterday_prescan) {
                            $all_prescan_found = true;
                            $line_lower_prescan = strtolower($line_prescan);
                            foreach ($listeo_identifiers_prescan as $identifier_prescan) {
                                if (strpos($line_lower_prescan, strtolower($identifier_prescan)) !== false) {
                                    $listeo_prescan_found = true;
                                    break 2;
                                }
                            }
                            $count_prescan++;
                        }
                    }
                }
            }
        }
        
        // Show Listeo status at top
        if ($all_prescan_found && !$listeo_prescan_found) {
            echo '<div class="listeo-success-message">';
            echo '✅ ' . __('No errors related to Listeo!', 'listeo_core');
            echo '</div>';
        }
        ?>
        
        <div class="listeo-error-log">
            <?php
            $fatal_errors = array();
            $error_log_path = ini_get('error_log');
            $wp_debug_log = WP_CONTENT_DIR . '/debug.log';
            
            // Check both PHP error log and WordPress debug log
            $log_files = array();
            if (file_exists($wp_debug_log) && is_readable($wp_debug_log)) {
                $log_files[] = $wp_debug_log;
            }
            if ($error_log_path && file_exists($error_log_path) && is_readable($error_log_path) && $error_log_path !== $wp_debug_log) {
                $log_files[] = $error_log_path;
            }

            $listeo_fatal_errors_found = false;
            $all_fatal_errors_found = false;
            $yesterday = strtotime('-24 hours');

            // Define Listeo-related paths and identifiers - must be more specific
            $listeo_identifiers = array(
                '/themes/listeo/',
                '/plugins/listeo-core/',
                '/plugins/listeo-elementor/',
                '/plugins/listeo-forms-and-fields-editor/',
                '/plugins/listeo-shortcodes/',
                '/plugins/listeo-ai-search/',
                '/plugins/listeo-data-scraper/',
                '/plugins/listeo-sms/',
                '/plugins/purethemes-cpt/',
                'listeo-core',
                'listeo-elementor',
                'listeo-forms-and-fields-editor',
                'listeo-shortcodes',
                'listeo-ai-search',
                'listeo-data-scraper',
                'listeo-sms',
                'purethemes-cpt'
            );

            foreach ($log_files as $log_file) {
                if (filesize($log_file) > 10 * 1024 * 1024) continue; // Skip files larger than 10MB
                
                $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    $lines = array_reverse($lines); // Start from newest entries
                    $count = 0;
                    
                    foreach ($lines as $line) {
                        if ($count >= 50) break; // Limit to last 50 entries
                        
                        // Look for critical errors that break functionality
                        if (preg_match('/\[(.*?)\].*?(Fatal error|PHP Fatal error|Uncaught|Parse error|PHP Parse error|Call to undefined|Cannot redeclare|Class .* not found|Interface .* not found|Trait .* not found|Maximum execution time|Allowed memory size|require\(\): Failed opening|include\(\): Failed opening|Call to a member function .* on null|Call to a member function .* on bool)/i', $line, $matches)) {
                            $timestamp_str = trim($matches[1]);
                            $error_timestamp = strtotime($timestamp_str);
                            
                            if ($error_timestamp && $error_timestamp > $yesterday) {
                                $all_fatal_errors_found = true;
                                
                                // Check if error is related to Listeo
                                $is_listeo_error = false;
                                $line_lower = strtolower($line);
                                
                                foreach ($listeo_identifiers as $identifier) {
                                    if (strpos($line_lower, strtolower($identifier)) !== false) {
                                        $is_listeo_error = true;
                                        $listeo_fatal_errors_found = true;
                                        break;
                                    }
                                }
                                
                                // Display all errors, but highlight Listeo ones
                                $error_class = $is_listeo_error ? 'listeo-error-entry listeo-related' : 'listeo-error-entry';
                                
                                echo '<div class="' . $error_class . '">';
                                if ($is_listeo_error) {
                                    echo '<span class="listeo-error-tag">LISTEO</span>';
                                }
                                echo '<strong>' . esc_html(date('Y-m-d H:i:s', $error_timestamp)) . '</strong><br>';
                                echo '<span>' . esc_html(substr($line, 0, 500)) . (strlen($line) > 500 ? '...' : '') . '</span>';
                                echo '</div>';
                                $count++;
                            }
                        }
                    }
                }
            }
            
            // Show appropriate message
            if (!$all_fatal_errors_found) {
                echo '<div class="listeo-no-errors">';
                echo '✅ ' . __('No critical errors found in the last 24 hours!', 'listeo_core');
                echo '</div>';
            }
            ?>
        </div>
        
        <?php if (!is_writable(ABSPATH . 'wp-config.php')): ?>
            <div class="listeo-config-warning">
                <?php _e('Note: wp-config.php is not writable. Debug mode cannot be toggled automatically.', 'listeo_core'); ?>
            </div>
        <?php endif; ?>
            </div>
        </div>

        <!-- Email Configuration Section -->
        <div class="listeo-health-section">
            <h2><?php _e('Email Configuration', 'listeo_core'); ?></h2>
    <div class="listeo-debug-section">
        
        <?php
        // Get email configuration info
        $admin_email = get_option('admin_email');
        $blogname = get_option('blogname');
        $from_email = get_option('woocommerce_email_from_address', $admin_email);
        $from_name = get_option('woocommerce_email_from_name', $blogname);
        
        // Check for SMTP plugins
        $smtp_plugins = array(
            'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
            'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
            'post-smtp/postman-smtp.php' => 'Post SMTP',
            'fluent-smtp/fluent-smtp.php' => 'FluentSMTP',
            'wp-ses/wp-ses.php' => 'WP Offload SES'
        );
        
        $active_smtp_plugin = null;
        $smtp_configured = false;
        
        foreach ($smtp_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $active_smtp_plugin = $plugin_name;
                // Basic check if SMTP might be configured
                if ($plugin_file === 'wp-mail-smtp/wp_mail_smtp.php') {
                    $smtp_configured = get_option('wp_mail_smtp_mail_mailer') && get_option('wp_mail_smtp_mail_mailer') !== 'mail';
                } elseif ($plugin_file === 'post-smtp/postman-smtp.php') {
                    $smtp_configured = get_option('postman_options') ? true : false;
                } else {
                    $smtp_configured = true; // Assume configured if plugin is active
                }
                break;
            }
        }
        
        // Determine mail method
        if ($active_smtp_plugin && $smtp_configured) {
            $mail_method = 'SMTP';
            $mail_status = 'good';
            $mail_status_text = __('SMTP Configured', 'listeo_core');
        } elseif ($active_smtp_plugin && !$smtp_configured) {
            $mail_method = 'SMTP Plugin Installed';
            $mail_status = 'warning';
            $mail_status_text = __('Needs Configuration', 'listeo_core');
        } else {
            $mail_method = 'PHP Mail';
            $mail_status = 'error';
            $mail_status_text = __('Critical - Emails May Not Deliver', 'listeo_core');
        }
        ?>
                
        <!-- Email Status Display -->
        <div class="listeo-email-grid">
            
            <div class="listeo-email-status mail-<?php echo $mail_status; ?>">
                <strong><?php _e('Mail Method:', 'listeo_core'); ?></strong><br>
                <span class="listeo-email-status-text <?php echo $mail_status; ?>">
                    <?php echo $mail_method; ?>
                </span><br>
                <small class="listeo-email-meta"><?php echo $mail_status_text; ?></small>
            </div>
            
            <div class="listeo-email-status">
                <strong><?php _e('From Email:', 'listeo_core'); ?></strong><br>
                <span class="listeo-email-content"><?php echo esc_html($from_email); ?></span><br>
                <small class="listeo-email-meta"><?php echo esc_html($from_name); ?></small>
            </div>
            
            <?php if ($active_smtp_plugin): ?>
            <div class="listeo-email-status mail-good">
                <strong><?php _e('SMTP Plugin:', 'listeo_core'); ?></strong><br>
                <span class="listeo-email-meta"><?php echo $active_smtp_plugin; ?></span><br>
                <small class="listeo-email-meta"><?php _e('Active', 'listeo_core'); ?></small>
            </div>
            <?php endif; ?>
            
        </div>

        <!-- Test Email Section -->
        <div class="listeo-email-test">
            <h4><?php _e('Email Test', 'listeo_core'); ?></h4>
            <div class="listeo-email-test-form">
                <input type="email" id="test_email_input" placeholder="<?php esc_attr_e('Enter email address to test...', 'listeo_core'); ?>" 
                       class="listeo-form-input">
                <button type="button" class="button listeo-test-email blue-button">
                    <?php _e('Send Test Email', 'listeo_core'); ?>
                </button>
            </div>
            <div class="listeo-email-tip">
                💡 <?php _e('Tip: Test with external emails (Gmail, Yahoo, Outlook) to verify real delivery capability. Internal server emails may work even with PHP Mail.', 'listeo_core'); ?>
            </div>
            <div id="email-test-result" class="listeo-email-test-result"></div>
        </div>

        <!-- Booking Email Testing Section -->
        <div class="listeo-booking-email-test" style="margin-top: 30px;">
            <h4><?php _e('Booking Email Testing', 'listeo_core'); ?></h4>
            <p class="description">
                <?php _e('Test booking notification emails with sample data from your database. All test emails will be sent to the admin email address.', 'listeo_core'); ?>
            </p>
            <div class="listeo-booking-email-test-form" style="margin-top: 15px;">
                <div style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px;">
                        <label for="booking_email_type" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php _e('Select Email Type:', 'listeo_core'); ?>
                        </label>
                        <select id="booking_email_type" class="listeo-form-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value=""><?php _e('-- Select Email Type --', 'listeo_core'); ?></option>
                            <optgroup label="<?php esc_attr_e('User Notifications', 'listeo_core'); ?>">
                                <option value="booking_user_waiting_approval"><?php _e('Booking Confirmation (Waiting Approval)', 'listeo_core'); ?></option>
                                <option value="instant_booking_user"><?php _e('Instant Booking Confirmation', 'listeo_core'); ?></option>
                                <option value="booking_confirmed_free"><?php _e('Free Booking Confirmed', 'listeo_core'); ?></option>
                                <option value="booking_confirmed_cash"><?php _e('Pay with Cash Booking Confirmed', 'listeo_core'); ?></option>
                                <option value="booking_pay"><?php _e('Payment Required', 'listeo_core'); ?></option>
                                <option value="booking_paid_user"><?php _e('Booking Paid Confirmation', 'listeo_core'); ?></option>
                                <option value="booking_cancelled_user"><?php _e('Booking Cancelled', 'listeo_core'); ?></option>
                                <option value="booking_reminder"><?php _e('Upcoming Booking Reminder', 'listeo_core'); ?></option>
                                <option value="booking_review_reminder"><?php _e('Review Reminder', 'listeo_core'); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e('Owner Notifications', 'listeo_core'); ?>">
                                <option value="booking_owner_new"><?php _e('New Booking Request', 'listeo_core'); ?></option>
                                <option value="instant_booking_owner"><?php _e('New Instant Booking', 'listeo_core'); ?></option>
                                <option value="booking_paid_owner"><?php _e('Booking Paid', 'listeo_core'); ?></option>
                                <option value="booking_cancelled_owner"><?php _e('Booking Cancelled', 'listeo_core'); ?></option>
                            </optgroup>
                        </select>
                    </div>
                    <div style="margin-top: 27px;">
                        <button type="button" class="button listeo-test-booking-email blue-button">
                            <?php _e('Send Test Email', 'listeo_core'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="listeo-email-tip" style="margin-top: 15px;">
                💡 <?php printf(
                    __('Test emails will be sent to: <strong>%s</strong>. The email will use data from an existing booking in your database, or sample data if no bookings exist.', 'listeo_core'),
                    get_option('admin_email')
                ); ?>
            </div>
            <div id="booking-email-test-result" class="listeo-email-test-result"></div>
        </div>

        <!-- Recommendations -->
        <?php if (!$active_smtp_plugin || !$smtp_configured): ?>
        <?php 
        $warning_class = (!$active_smtp_plugin) ? 'critical' : 'warning';
        $warning_color = (!$active_smtp_plugin) ? '#333333' : '#999999';
        ?>
        <div class="listeo-dynamic-warning <?php echo (!$active_smtp_plugin) ? 'critical' : 'warning'; ?>">
            <h4 class="listeo-dynamic-warning-header">
                <?php echo (!$active_smtp_plugin) ? __('⚠️ Critical Issue', 'listeo_core') : __('💡 Recommendation', 'listeo_core'); ?>
            </h4>
            <p>
                <?php if (!$active_smtp_plugin): ?>
                    <?php _e('CRITICAL: Without SMTP, emails (booking confirmations, password resets, notifications) will likely NOT be delivered. Install an SMTP plugin immediately:', 'listeo_core'); ?>
                <?php else: ?>
                    <?php _e('Your SMTP plugin needs to be configured for reliable email delivery:', 'listeo_core'); ?>
                <?php endif; ?>
            </p>
            <div class="listeo-dynamic-warning-content">
                <strong><?php _e('Popular SMTP Plugins:', 'listeo_core'); ?></strong><br>
                • <strong>WP Mail SMTP</strong> - <?php _e('Most popular, supports Gmail, Outlook, SendGrid', 'listeo_core'); ?><br>
                • <strong>Post SMTP</strong> - <?php _e('Great logging and debugging features', 'listeo_core'); ?><br>
                • <strong>FluentSMTP</strong> - <?php _e('Lightweight and fast', 'listeo_core'); ?>
            </div>
        </div>
        <?php endif; ?>

            <a href="https://docs.purethemes.net/workscout/knowledge-base/having-problems-with-your-wordpress-site-not-sending-emails/">Having problems with your WordPress site not sending emails? →</a>

        
            </div>
        </div>


        <!-- Transient Health Section -->
        <div class="listeo-health-section listeo-transient-section">
            <h2><?php _e('Transient Cache', 'listeo_core'); ?></h2>

            <div class="listeo-transient-box">

                <div class="transient-stats-display" id="transient-stats-display">
                    <div class="transient-loading">
                        <span class="spinner is-active"></span>
                        <?php _e('Analyzing transients...', 'listeo_core'); ?>
                    </div>
                </div>

                <div class="listeo-transient-actions" id="transient-cleanup-actions" style="display: none;">
                    <h4><?php _e('Cleanup Options:', 'listeo_core'); ?></h4>
                    <div class="listeo-transient-buttons">
                        <button type="button" class="button button-primary listeo-cleanup-transients" data-cleanup-type="expired">
                            <?php _e('Clean Expired Transients', 'listeo_core'); ?>
                        </button>
                        
                        <button type="button" class="button button-secondary-light listeo-cleanup-transients" data-cleanup-type="listeo_only">
                            <?php _e('Clean Listeo Transients', 'listeo_core'); ?>
                        </button>
                        
                        <button type="button" class="button button-link-delete listeo-cleanup-transients" data-cleanup-type="all">
                            <?php _e('Clean All Transients', 'listeo_core'); ?>
                        </button>
                    </div>
                    
                    <div class="listeo-cleanup-descriptions">
                        <strong><?php _e('Clean Expired:', 'listeo_core'); ?></strong> <?php _e('Safely removes old plugin cache data (Recommended, preserves Google reviews)', 'listeo_core'); ?><br>
                        <strong><?php _e('Clean Listeo:', 'listeo_core'); ?></strong> <?php _e('Removes Listeo search & listing cache (preserves Google reviews)', 'listeo_core'); ?><br>
                        <strong><?php _e('Clean All:', 'listeo_core'); ?></strong> <?php _e('Removes all plugin cache data (preserves Google reviews, may slow site temporarily)', 'listeo_core'); ?>
                    </div>
                    
                    <div class="listeo-cleanup-results" id="transient-cleanup-results" style="display: none;">
                        <!-- Results will be displayed here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Revision Health Section -->
        <div class="listeo-health-section listeo-revision-section">
            <h2><?php _e('Post Revision', 'listeo_core'); ?></h2>
            
            <div class="listeo-revision-box">
                
                <div class="revision-stats-display" id="revision-stats-display">
                    <div class="revision-loading">
                        <span class="spinner is-active"></span>
                        <?php _e('Analyzing revisions...', 'listeo_core'); ?>
                    </div>
                </div>

                <div class="listeo-revision-actions" id="revision-cleanup-actions" style="display: none;">
                    <h4><?php _e('Cleanup Options:', 'listeo_core'); ?></h4>
                    
                    <div class="listeo-revision-controls">
                        <label for="keep-revisions-count"><?php _e('Keep per post:', 'listeo_core'); ?></label>
                        <select id="keep-revisions-count">
                            <option value="0"><?php _e('Delete All', 'listeo_core'); ?></option>
                            <option value="1"><?php _e('Keep 1 Revision', 'listeo_core'); ?></option>
                            <option value="2" selected><?php _e('Keep 2 Revisions', 'listeo_core'); ?></option>
                            <option value="3"><?php _e('Keep 3 Revisions', 'listeo_core'); ?></option>
                            <option value="5"><?php _e('Keep 5 Revisions', 'listeo_core'); ?></option>
                        </select>
                    </div>
                    
                    <div class="listeo-revision-buttons">
                        <button type="button" class="button button-primary listeo-cleanup-revisions" data-cleanup-type="keep_recent">
                            <?php _e('Clean Old Revisions', 'listeo_core'); ?>
                        </button>
                        
                        <button type="button" class="button button-secondary-light listeo-cleanup-revisions" data-cleanup-type="listing_only">
                            <?php _e('Clean Listing Revisions Only', 'listeo_core'); ?>
                        </button>
                        
                        <button type="button" class="button button-link-delete listeo-cleanup-revisions" data-cleanup-type="all">
                            <?php _e('Delete All Revisions', 'listeo_core'); ?>
                        </button>
                    </div>
                    
                    <div class="listeo-cleanup-descriptions">
                        <strong><?php _e('Clean Old:', 'listeo_core'); ?></strong> <?php _e('Keeps recent versions of pages/posts, removes old Elementor data (Recommended)', 'listeo_core'); ?><br>
                        <strong><?php _e('Listing Only:', 'listeo_core'); ?></strong> <?php _e('Removes only business listing revisions and their data', 'listeo_core'); ?><br>
                        <strong><?php _e('Delete All:', 'listeo_core'); ?></strong> <?php _e('Removes all page/post history and Elementor data (Cannot be undone)', 'listeo_core'); ?>
                    </div>
                    
                    <div class="listeo-cleanup-results" id="revision-cleanup-results" style="display: none;">
                        <!-- Results will be displayed here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Pages Section -->
        <div class="listeo-health-section">
            <h2>Pages</h2>

    <table class="widefat striped health-check-table listeo-health-check-table-pages">
        <?php
        $pages = listeo_core_get_dashboard_pages_list();
        foreach ($pages as $key => $page) {
        ?>
            <tr>
                <td>
                    <?php echo $page['title']; ?>
                </td>


                <?php if (get_option($page['option']) && ( 'publish' === get_post_status(get_option($page['option']) ) ) ) { ?>
                    <td colspan="2">
                        <span class="listeo-status-icon success">&#9989;</span> Page exists: <a href="<?php echo get_permalink(get_option($page['option'])); ?>"><?php echo get_the_title(get_option($page['option'])); ?></a>
                    </td>
                <?php } else { ?>
                    <td>
                        <span class="listeo-status-icon error">&#x2717;</span> Page is missing.
                    </td>
                    <td>
                        <a class="button" data-page="<?php echo esc_attr($page['option']); ?>" href=" #">Create</a>
                    </td>
                <?php } ?>


            </tr>
        <?php } ?>
            </table>
        </div>

        <!-- Database Tables Section -->
        <div class="listeo-health-section">
            <h2>Database Tables</h2>
    <table class="widefat striped health-check-table">

        <?php
        global $wpdb;
        $listeo_tables_list = array(
            'listeo_core_activity_log' => 'Activity Log',
            'listeo_core_commissions' => 'Commissions',
            'listeo_core_commissions_payouts' => 'Payouts',
            'listeo_core_conversations' => 'Conversations',
            'listeo_core_messages' => 'Messages',
            
            'listeo_core_stats' => 'Statistics',
            'listeo_core_user_packages' => 'User Packages',
            'listeo_core_ad_stats' => 'Ad Stats',
            'bookings_calendar' => 'Bookings',
            'bookings_meta' => 'Bookings Meta',
        );

        foreach ($listeo_tables_list as $table => $name) { ?>
            <tr>
                <td><?php echo $name; ?> Table:</td>
                <td>
                    <?php
                    
                    $table_name = $wpdb->prefix . $table;
                    
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) { ?>

                        <span class="listeo-status-icon success">&#9989;</span> Table exists
                    <?php } else { ?>
                        <span class="listeo-status-icon error">&#x2717;</span> Table does not exists, try to reactivate Listeo Core plugin or <a href="https://themeforest.net/item/listeo-directory-listings-wordpress-theme/23239259/support">contact Support</a>
                    <?php } ?>

                </td>
            </tr>
        <?php } ?>

            </table>
        </div>

        <!-- Database Health Section -->
        <div class="listeo-health-section listeo-database-section">
            <h2><?php _e('Database Size', 'listeo_core'); ?></h2>
            
            <!-- Database Stats Display -->
            <div class="listeo-database-stats-container" id="database-stats-container">
                <div class="listeo-loading" id="database-stats-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading database statistics...', 'listeo_core'); ?>
                </div>
                
                <!-- Stats will be loaded here via AJAX -->
                <div class="listeo-database-stats" id="database-stats-content" style="display: none;">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>


    </div>
</div>