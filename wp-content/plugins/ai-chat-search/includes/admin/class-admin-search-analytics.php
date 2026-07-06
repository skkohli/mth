<?php
/**
 * Admin Search Analytics Handler
 *
 * Handles search analytics rendering and AJAX operations for the admin dashboard.
 *
 * @package AI_Chat_Search
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin_Search_Analytics
 *
 * Manages search analytics display and operations in the admin area.
 */
class Admin_Search_Analytics {

    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct() {
        add_action('wp_ajax_listeo_ai_export_analytics_csv', array($this, 'ajax_export_csv'));
    }

    /**
     * Render the complete search analytics section
     */
    public function render_section() {
        ?>
        <!-- Search Analytics Section -->
        <?php if (get_option('listeo_ai_search_enable_analytics') && class_exists('Listeo_AI_Search_Analytics')): ?>
            <?php $this->render_enabled_section(); ?>
        <?php else: ?>
            <?php $this->render_disabled_section(); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Render section when analytics is enabled
     */
    private function render_enabled_section() {
        $analytics_7d = Listeo_AI_Search_Analytics::get_analytics(7);
        $analytics_30d = Listeo_AI_Search_Analytics::get_analytics(30);
        ?>
        <div class="airs-card airs-card-full-width airs-card-toggleable" data-toggle-id="stats-popular-queries">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-sky">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Popular Search Queries', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Analytics of the keywords used by AI to provide responses to users.', 'ai-chat-search'); ?></p>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2 airs-card-toggle-icon"></span>
            </div>
            <div class="airs-card-body">
                <?php $this->render_stats_boxes($analytics_7d, $analytics_30d); ?>
                <?php $this->render_query_tags($analytics_7d, $analytics_30d); ?>
                <?php $this->render_actions(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render stats boxes
     *
     * @param array $analytics_7d Analytics for last 7 days
     * @param array $analytics_30d Analytics for last 30 days
     */
    private function render_stats_boxes($analytics_7d, $analytics_30d) {
        ?>
        <!-- Statistics Boxes -->
        <div class="airs-stats-boxes airs-stats-boxes-two-cols">
            <!-- 7 Days Total Searches -->
            <div class="airs-stat-box airs-stat-box-green">
                <div class="airs-stat-number airs-stat-number-green">
                    <?php echo $analytics_7d['total_searches']; ?>
                </div>
                <div class="airs-stat-label airs-stat-label-green">
                    <?php _e('Total Searches in Last 7 Days', 'ai-chat-search'); ?>
                </div>
            </div>

            <!-- 30 Days Total Searches -->
            <div class="airs-stat-box airs-stat-box-blue">
                <div class="airs-stat-number airs-stat-number-blue">
                    <?php echo $analytics_30d['total_searches']; ?>
                </div>
                <div class="airs-stat-label airs-stat-label-blue">
                    <?php _e('Total Searches in Last 30 Days', 'ai-chat-search'); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render popular search query tags
     *
     * @param array $analytics_7d Analytics for last 7 days
     * @param array $analytics_30d Analytics for last 30 days
     */
    private function render_query_tags($analytics_7d, $analytics_30d) {
        ?>
        <!-- Popular Search Queries - 7 Days -->
        <div class="airs-queries-box airs-queries-box-green">
            <h3><?php _e('Last 7 Days (Top 50 Searches)', 'ai-chat-search'); ?></h3>
            <?php if (!empty($analytics_7d['popular_queries'])): ?>
                <div class="airs-query-tags">
                    <?php foreach ($analytics_7d['popular_queries'] as $query => $count): ?>
                        <span class="airs-query-tag-green">
                            <strong><?php echo esc_html($query); ?></strong> (<?php echo $count; ?>x)
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><em><?php _e('No search queries recorded yet for the last 7 days.', 'ai-chat-search'); ?></em></p>
            <?php endif; ?>
        </div>

        <!-- Popular Search Queries - 30 Days -->
        <div class="airs-queries-box airs-queries-box-blue">
            <h3><?php _e('Last 30 Days (Top 50 Searches)', 'ai-chat-search'); ?></h3>
            <?php if (!empty($analytics_30d['popular_queries'])): ?>
                <div class="airs-query-tags">
                    <?php foreach ($analytics_30d['popular_queries'] as $query => $count): ?>
                        <span class="airs-query-tag-blue">
                            <strong><?php echo esc_html($query); ?></strong> (<?php echo $count; ?>x)
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><em><?php _e('No search queries recorded yet for the last 30 days.', 'ai-chat-search'); ?></em></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render analytics action buttons
     */
    private function render_actions() {
        ?>
        <!-- Analytics Actions -->
        <div>
            <div class="airs-form-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=listeo_ai_export_analytics_csv&nonce=' . wp_create_nonce('listeo_ai_search_nonce'))); ?>" class="airs-button airs-button-secondary">
                    <span class="dashicons dashicons-download" style="margin-top: 3px; margin-right: 3px;"></span>
                    <?php _e('Export All Queries to CSV', 'ai-chat-search'); ?>
                </a>
                <button type="button" id="clear-analytics" class="airs-button airs-button-danger" onclick="return confirm('Are you sure? This will delete all analytics data.');">
                    <?php _e('Clear Analytics Data', 'ai-chat-search'); ?>
                </button>
            </div>
            <div class="airs-help-text"><?php _e('Analytics data is automatically cleaned up after 10.000 entries to prevent database bloat.', 'ai-chat-search'); ?></div>
            <div class="airs-form-group" style="margin-top: 15px; margin-bottom: 5px;">
                <label class="airs-checkbox-label">
                    <input type="checkbox" id="toggle-search-analytics" value="1" <?php checked(get_option('listeo_ai_search_enable_analytics'), 1); ?>>
                    <span class="airs-checkbox-custom"></span>
                    <span class="airs-checkbox-text" style="font-weight: 500;"><?php _e('Enable Search Analytics Tracking', 'ai-chat-search'); ?></span>
                </label>
                <script>
                jQuery(function($){
                    $('#toggle-search-analytics').on('change', function(){
                        var $cb = $(this),
                            $custom = $cb.next('.airs-checkbox-custom'),
                            $spinner = $('<span class="airs-spinner airs-spinner--small" style="margin-left:0;top:4px"></span>');
                        $custom.hide().after($spinner);
                        AIRS.ajax({
                            action: 'listeo_ai_toggle_search_analytics',
                            data: { enabled: $cb.is(':checked') },
                            success: function(r){ if(r.success) location.reload(); },
                            error: function(){ $cb.prop('checked', !$cb.is(':checked')); },
                            complete: function(){ $spinner.remove(); $custom.show(); }
                        });
                    });
                });
                </script>
            </div>
        </div>
        <?php
    }

    /**
     * Render section when analytics is disabled
     */
    private function render_disabled_section() {
        ?>
        <div class="airs-card airs-card-full-width airs-card-toggleable" data-toggle-id="stats-popular-queries">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-sky">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Popular Search Queries', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Analytics of the keywords used by AI to provide responses to users.', 'ai-chat-search'); ?></p>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2 airs-card-toggle-icon"></span>
            </div>
            <div class="airs-card-body">
                <div style="background: #f0f0f0; padding: 40px 20px; border-radius: 5px; text-align: center;">
                    <h3><?php _e('Search Analytics Disabled', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Enable search analytics to track search patterns and performance.', 'ai-chat-search'); ?></p>
                    <div class="airs-form-group" style="display: inline-block; margin-top: 10px;">
                        <label class="airs-checkbox-label">
                            <input type="checkbox" id="toggle-search-analytics-disabled" value="1">
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text" style="font-weight: 500;"><?php _e('Enable Search Analytics Tracking', 'ai-chat-search'); ?></span>
                        </label>
                        <script>
                        jQuery(function($){
                            $('#toggle-search-analytics-disabled').on('change', function(){
                                var $cb = $(this),
                                    $custom = $cb.next('.airs-checkbox-custom'),
                                    $spinner = $('<span class="airs-spinner airs-spinner--small" style="margin-left:0;top:4px"></span>');
                                $custom.hide().after($spinner);
                                AIRS.ajax({
                                    action: 'listeo_ai_toggle_search_analytics',
                                    data: { enabled: $cb.is(':checked') },
                                    success: function(r){ if(r.success) location.reload(); },
                                    error: function(){ $cb.prop('checked', !$cb.is(':checked')); },
                                    complete: function(){ $spinner.remove(); $custom.show(); }
                                });
                            });
                        });
                        </script>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for exporting analytics as CSV
     */
    public function ajax_export_csv() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'listeo_ai_search_nonce')) {
            wp_die(__('Security check failed.', 'ai-chat-search'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'ai-chat-search'));
        }

        if (!class_exists('Listeo_AI_Search_Analytics')) {
            wp_die(__('Analytics class not found.', 'ai-chat-search'));
        }

        // Get optional days parameter
        $days = isset($_GET['days']) ? intval($_GET['days']) : null;

        // Export CSV (this method outputs directly and exits)
        Listeo_AI_Search_Analytics::export_popular_queries_csv($days);
        exit;
    }
}
