<?php
/**
 * Admin Chat History Handler
 *
 * Handles chat history rendering and AJAX operations for the admin dashboard.
 *
 * @package AI_Chat_Search
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin_Chat_History
 *
 * Manages chat history display and operations in the admin area.
 */
class Admin_Chat_History {

    /**
     * Items per page for pagination
     */
    const PER_PAGE = 4;

    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct() {
        add_action('wp_ajax_listeo_ai_load_chat_history', array($this, 'ajax_load'));
        add_action('wp_ajax_listeo_ai_load_conversation_messages', array($this, 'ajax_load_conversation_messages'));
        add_action('wp_ajax_listeo_ai_clear_chat_history', array($this, 'ajax_clear'));
        add_action('wp_ajax_listeo_ai_delete_conversation', array($this, 'ajax_delete_conversation'));
        add_action('wp_ajax_listeo_ai_export_chat_history_csv', array($this, 'ajax_export_csv'));
    }

    /**
     * Render the complete chat history section
     *
     * @param bool $history_enabled Whether chat history is enabled
     */
    public function render_section($history_enabled) {
        $this->render_config_modal();

        if ($history_enabled) {
            $this->render_enabled_section();
        } else {
            $this->render_disabled_section();
        }
    }

    /**
     * Render the shared configure modal and its JS
     */
    private function render_config_modal() {
        $nonce = wp_create_nonce('listeo_ai_search_nonce');
        ?>
        <div id="chat-history-config-modal" class="airs-modal" style="display: none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content airs-audit-settings-modal-content">
                <form id="chat-history-config-form">
                    <div class="airs-modal-header">
                        <h3 style="margin: 0;"><?php _e('Chat History Settings', 'ai-chat-search'); ?></h3>
                        <button type="button" id="chat-history-modal-close" class="listeo-ai-modal-close">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="airs-modal-body">

                        <div class="airs-form-group">
                            <label class="airs-checkbox-label">
                                <input type="checkbox" name="listeo_ai_chat_history_enabled" value="1" <?php checked(get_option('listeo_ai_chat_history_enabled', 0), 1); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text">
                                    <?php _e('Enable Chat History Tracking', 'ai-chat-search'); ?>
                                    <small><?php _e('Save user questions and AI responses for analytics.', 'ai-chat-search'); ?></small>
                                </span>
                            </label>
                        </div>

                        <div class="airs-form-group">
                            <label class="airs-label" for="chat-history-retention-days"><?php _e('Data Retention', 'ai-chat-search'); ?></label>
                            <?php $retention = get_option('listeo_ai_chat_retention_days', 30); ?>
                            <select name="listeo_ai_chat_retention_days" id="chat-history-retention-days" class="airs-input">
                                <option value="30" <?php selected($retention, 30); ?>><?php printf(__('Last %d days', 'ai-chat-search'), 30); ?></option>
                                <option value="90" <?php selected($retention, 90); ?>><?php printf(__('Last %d days', 'ai-chat-search'), 90); ?></option>
                                <option value="180" <?php selected($retention, 180); ?>><?php printf(__('Last %d days', 'ai-chat-search'), 180); ?></option>
                                <option value="360" <?php selected($retention, 360); ?>><?php printf(__('Last %d days', 'ai-chat-search'), 360); ?></option>
                            </select>
                            <p class="airs-help-text"><?php _e('Conversations older than this will be automatically deleted by the weekly cleanup cron.', 'ai-chat-search'); ?></p>
                        </div>

                        <?php do_action('ai_chat_search_chat_history_settings_fields'); ?>

                        <?php if (get_option('listeo_ai_chat_history_enabled', 0)): ?>
                        <!-- Export Chat History CSV -->
                        <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 15px; background: #f8f9fa; border-radius: 6px;">
                            <div>
                                <strong style="font-size: 14px;"><?php _e('Export Chat History', 'ai-chat-search'); ?></strong>
                                <p style="margin: 3px 0 0; font-size: 13px; color: #666;"><?php _e('Download all conversations as a CSV file.', 'ai-chat-search'); ?></p>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=listeo_ai_export_chat_history_csv&nonce=' . $nonce)); ?>" class="airs-button airs-button-secondary" style="font-size: 13px; padding: 6px 14px; text-decoration: none; white-space: nowrap;">
                                <span class="dashicons dashicons-download" style="margin-top: 3px; margin-right: 3px;"></span>
                                <?php _e('Export CSV', 'ai-chat-search'); ?>
                            </a>
                        </div>

                        <!-- Clear History -->
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 15px; background: #f8f9fa; border-radius: 6px;">
                            <div>
                                <strong style="font-size: 14px; color: #b32d2e;"><?php _e('Clear All History', 'ai-chat-search'); ?></strong>
                                <p style="margin: 3px 0 0; font-size: 13px; color: #666;"><?php _e('Permanently delete all chat history records.', 'ai-chat-search'); ?></p>
                            </div>
                            <button type="button" id="clear-chat-history" class="airs-button airs-button-secondary airs-button-danger" style="font-size: 13px; padding: 6px 14px; white-space: nowrap;">
                                <?php _e('Clear History', 'ai-chat-search'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="airs-modal-footer">
                        <button type="submit" class="airs-button airs-button-primary">
                            <?php _e('Save Settings', 'ai-chat-search'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var $configModal = $('#chat-history-config-modal');

            $(document).on('click', '#chat-history-configure-btn', function(e) {
                e.preventDefault();
                $configModal.fadeIn(200);
            });

            function closeConfigModal() {
                $configModal.fadeOut(200);
            }
            $(document).on('click', '#chat-history-modal-close, #chat-history-config-modal .airs-modal-overlay', closeConfigModal);

            // Save settings (form submit)
            $(document).on('submit', '#chat-history-config-form', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('button[type="submit"]');

                $btn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'ai-chat-search')); ?>');

                var data = {
                    action: 'listeo_ai_save_settings',
                    nonce: '<?php echo $nonce; ?>',
                    listeo_ai_chat_history_enabled: $('input[name="listeo_ai_chat_history_enabled"]').is(':checked') ? 1 : 0,
                    listeo_ai_chat_retention_days: $('#chat-history-retention-days').val()
                };

                var $translateEnabled = $('input[name="listeo_ai_chat_history_translate_enabled"]');
                if ($translateEnabled.length) {
                    data.listeo_ai_chat_history_translate_enabled = $translateEnabled.is(':checked') ? 1 : 0;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to save settings.', 'ai-chat-search')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Failed to save settings.', 'ai-chat-search')); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Save Settings', 'ai-chat-search')); ?>');
                    }
                });
            });

            // Clear History button handler
            $(document).on('click', '#clear-chat-history', function(e) {
                e.preventDefault();

                if (!confirm('<?php echo esc_js(__('Are you sure you want to delete all chat history? This action cannot be undone.', 'ai-chat-search')); ?>')) {
                    return;
                }

                var $button = $(this);
                var originalHtml = $button.html();

                $button.prop('disabled', true).html('<span class="airs-spinner" style="margin-right: 6px;"></span> <?php echo esc_js(__('Clearing...', 'ai-chat-search')); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_clear_chat_history',
                        nonce: '<?php echo $nonce; ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                            $button.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Failed to clear chat history. Please try again.', 'ai-chat-search')); ?>');
                        $button.prop('disabled', false).html(originalHtml);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render section when chat history is enabled
     */
    private function render_enabled_section() {
        // Pagination
        $page = isset($_GET['history_page']) ? max(1, intval($_GET['history_page'])) : 1;
        $offset = ($page - 1) * self::PER_PAGE;

        // Get stats and conversations
        $history_stats_30d = null;
        $history_stats_today = null;
        $recent_conversations = array();
        $total_pages = 1;

        if (class_exists('Listeo_AI_Search_Chat_History')) {
            $history_stats_30d = Listeo_AI_Search_Chat_History::get_stats(30);
            $history_stats_today = Listeo_AI_Search_Chat_History::get_stats_today();
            $recent_conversations = Listeo_AI_Search_Chat_History::get_recent_conversations(self::PER_PAGE, $offset);

            // Get total count for pagination
            global $wpdb;
            $table_name = Listeo_AI_Search_Chat_History::get_table_name();
            $total_conversations = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM {$table_name}");
            $total_pages = ceil($total_conversations / self::PER_PAGE);
        }
        ?>
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l4 2"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php printf(__('Chat History (Last %d Days)', 'ai-chat-search'), get_option('listeo_ai_chat_retention_days', 30)); ?></h3>
                    <p><?php _e('Detailed conversation tracking', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">
                <?php if (!AI_Chat_Search_Pro_Manager::can_access_conversation_logs()): ?>
                    <?php $this->render_locked_preview(); ?>
                <?php else: ?>
                    <?php $this->render_stats_boxes($history_stats_30d, $history_stats_today); ?>
                    <?php $this->render_conversations_list($recent_conversations, $page, $total_pages); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render section when chat history is disabled
     */
    private function render_disabled_section() {
        ?>
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l4 2"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Chat History', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Detailed conversation tracking', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">
                <div class="airs-audit-empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <h3><?php _e('Chat history tracking is off', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Enable "Chat History Tracking" in the configure settings, have at least a few conversations, then come back here.', 'ai-chat-search'); ?></p>
                    <button type="button" id="chat-history-configure-btn" class="airs-button airs-button-primary">
                        <?php _e('Configure', 'ai-chat-search'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render locked preview for free users
     */
    public function render_locked_preview() {
        $dummy_conversations = array(
            array('id' => 'a1b2c3d4e5f6g7h8', 'messages' => 8, 'user' => 'Guest User', 'ip' => '192.168.1.45', 'country' => 'us', 'country_name' => 'United States', 'city' => 'New York', 'region' => 'New York', 'continent' => 'Americas', 'started' => 3, 'last_msg' => 1),
            array('id' => 'x9y8z7w6v5u4t3s2', 'messages' => 5, 'user' => 'john.doe@example.com', 'ip' => '85.214.132.117', 'country' => 'de', 'country_name' => 'Germany', 'city' => 'Berlin', 'region' => 'Berlin', 'continent' => 'Europe', 'started' => 6, 'last_msg' => 4),
            array('id' => 'm3n4o5p6q7r8s9t0', 'messages' => 12, 'user' => 'Guest User', 'ip' => '46.125.70.146', 'country' => 'pl', 'country_name' => 'Poland', 'city' => 'Warsaw', 'region' => 'Masovia', 'continent' => 'Europe', 'started' => 9, 'last_msg' => 7),
            array('id' => 'k5l6m7n8o9p0q1r2', 'messages' => 3, 'user' => 'jane.smith@email.com', 'ip' => '78.90.123.45', 'country' => 'gb', 'country_name' => 'United Kingdom', 'city' => 'London', 'region' => 'England', 'continent' => 'Europe', 'started' => 12, 'last_msg' => 10),
        );
        ?>
        <div class="ai-chat-pro-feature-locked">
            <div class="preview-container preview-blurred">
                <div class="airs-stats-boxes">
                    <div class="airs-stat-box airs-stat-box-green">
                        <div class="airs-stat-number airs-stat-number-green">42</div>
                        <div class="airs-stat-label airs-stat-label-green"><?php _e('Conversations', 'ai-chat-search'); ?></div>
                    </div>
                    <div class="airs-stat-box airs-stat-box-blue">
                        <div class="airs-stat-number airs-stat-number-blue">287</div>
                        <div class="airs-stat-label airs-stat-label-blue"><?php _e('Messages', 'ai-chat-search'); ?></div>
                    </div>
                    <div class="airs-stat-box airs-stat-box-orange">
                        <div class="airs-stat-number airs-stat-number-orange">6.8</div>
                        <div class="airs-stat-label airs-stat-label-orange"><?php _e('Avg per Conversation', 'ai-chat-search'); ?></div>
                    </div>
                </div>

                <div style="margin: 20px 0;">
                    <h3 style="margin: 0 0 15px 0;"><?php _e('Recent Conversations', 'ai-chat-search'); ?></h3>

                    <?php foreach ($dummy_conversations as $conv): ?>
                    <div class="airs-conversation-card">
                        <div class="airs-conversation-header">
                            <div class="airs-conversation-id">
                                <strong><?php _e('Conversation ID:', 'ai-chat-search'); ?></strong>
                                <code class="airs-conversation-id-code"><?php echo esc_html($conv['id']); ?></code>
                            </div>
                            <div class="airs-conversation-meta">
                                <div style="font-size: 12px; color: #666;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php echo esc_html($conv['user']); ?>
                                    <?php
                                    $dummy_tip = array();
                                    if (!empty($conv['country_name'])) $dummy_tip[] = esc_attr($conv['country_name']);
                                    if (!empty($conv['city'])) $dummy_tip[] = esc_attr($conv['city']);
                                    if (!empty($conv['region'])) $dummy_tip[] = esc_attr($conv['region']);
                                    if (!empty($conv['continent'])) $dummy_tip[] = esc_attr($conv['continent']);
                                    ?>
                                    <span class="airs-ip-geo" data-geo-tooltip="<?php echo implode('|', $dummy_tip); ?>">
                                        <img src="https://flagcdn.com/16x12/<?php echo esc_attr($conv['country']); ?>.png" alt="<?php echo esc_attr(strtoupper($conv['country'])); ?>" style="vertical-align: middle;" />
                                        <span style="color: #999;"><?php echo esc_html($conv['ip']); ?></span>
                                    </span>
                                </div>
                                <div style="font-size: 12px; color: #999;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg><?php printf(__('Started: %d hours ago', 'ai-chat-search'), $conv['started']); ?>
                                </div>
                            </div>
                        </div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><?php echo $conv['messages']; ?> <?php _e('messages', 'ai-chat-search'); ?>
                            &bull; <?php printf(__('last %d hours ago', 'ai-chat-search'), $conv['last_msg']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                        <span class="airs-button airs-button-secondary" style="font-size: 13px; padding: 8px 16px; text-decoration: none; white-space: nowrap; opacity: 0.7; pointer-events: none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                            <?php _e('Configure', 'ai-chat-search'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="lock-overlay" style="background: rgba(255, 255, 255, 0.55); backdrop-filter: blur(3px);">
                <div class="lock-content">
                    <h3><?php _e('Chat History & Analytics', 'ai-chat-search'); ?></h3>
                    <ul class="benefits-list">
                        <li><?php _e('Conversation statistics and metrics', 'ai-chat-search'); ?></li>
                        <li><?php _e('Complete message history', 'ai-chat-search'); ?></li>
                    </ul>
                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('chat_history')); ?>" class="button button-primary button-hero" target="_blank">
                        <?php _e('Upgrade to Pro', 'ai-chat-search'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render stats boxes
     *
     * @param array|null $stats_30d Stats for last 30 days
     * @param array|null $stats_today Stats for today
     */
    private function render_stats_boxes($stats_30d, $stats_today) {
        if (!is_array($stats_30d) || empty($stats_30d)) {
            echo '<p>' . __('No chat history data available yet. Start using the AI chat to see statistics here.', 'ai-chat-search') . '</p>';
            return;
        }
        ?>
        <div class="airs-stats-boxes">
            <div class="airs-stat-box airs-stat-box-green">
                <div class="airs-stat-number airs-stat-number-green">
                    <?php echo number_format(isset($stats_30d['total_conversations']) ? intval($stats_30d['total_conversations']) : 0); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-green"><?php _e('Conversations', 'ai-chat-search'); ?></div>
                <div class="airs-stat-today airs-stat-today-green">
                    <?php printf(__('Today: %s', 'ai-chat-search'), number_format(isset($stats_today['total_conversations']) ? intval($stats_today['total_conversations']) : 0)); ?>
                </div>
            </div>

            <div class="airs-stat-box airs-stat-box-blue">
                <div class="airs-stat-number airs-stat-number-blue">
                    <?php echo number_format(isset($stats_30d['total_messages']) ? intval($stats_30d['total_messages']) : 0); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-blue"><?php _e('Messages', 'ai-chat-search'); ?></div>
                <div class="airs-stat-today airs-stat-today-blue">
                    <?php printf(__('Today: %s', 'ai-chat-search'), number_format(isset($stats_today['total_messages']) ? intval($stats_today['total_messages']) : 0)); ?>
                </div>
            </div>

            <div class="airs-stat-box airs-stat-box-orange">
                <div class="airs-stat-number airs-stat-number-orange">
                    <?php echo isset($stats_30d['avg_per_conversation']) ? floatval($stats_30d['avg_per_conversation']) : 0; ?>
                </div>
                <div class="airs-stat-label airs-stat-label-orange"><?php _e('Avg per Conversation', 'ai-chat-search'); ?></div>
                <div class="airs-stat-today airs-stat-today-orange">
                    <?php printf(__('Today: %s', 'ai-chat-search'), isset($stats_today['avg_per_conversation']) ? floatval($stats_today['avg_per_conversation']) : 0); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render conversations list with pagination
     *
     * @param array $conversations Recent conversations
     * @param int $page Current page
     * @param int $total_pages Total pages
     */
    private function render_conversations_list($conversations, $page, $total_pages) {
        if (empty($conversations)) {
            echo '<p style="padding: 20px; text-align: center; color: #666;">' . __('No conversations yet. Start using the AI chat to see history here.', 'ai-chat-search') . '</p>';
            return;
        }
        ?>
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div>
                        <h3 style="margin: 0;"><?php _e('Recent Conversations', 'ai-chat-search'); ?></h3>
                        <p style="color: #666; margin: 5px 0 0 0;"><?php _e('Click on a conversation to view the full chat history', 'ai-chat-search'); ?></p>
                    </div>
                </div>
                <div class="conversation-search-actions">
                    <input type="text" id="conversation-search-input" placeholder="<?php esc_attr_e('ID, IP or keyword', 'ai-chat-search'); ?>" style="width: 200px; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="button" id="conversation-search-btn" class="button button-small conversation-search-btn"><?php _e('Search', 'ai-chat-search'); ?></button>
                    <button type="button" id="conversation-search-clear" class="button button-small conversation-search-clear" style="display: none;"><?php _e('Clear', 'ai-chat-search'); ?></button>
                </div>
            </div>

            <div id="listeo-history-conversations">
            <?php foreach ($conversations as $conv): ?>
                <?php
                $user_info = $conv['user_id'] ? get_userdata($conv['user_id']) : null;
                $this->render_conversation_card($conv, null, $user_info);
                ?>
            <?php endforeach; ?>
            </div>

            <div id="listeo-history-pagination">
                <?php $this->render_pagination($page, $total_pages); ?>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                <button type="button" id="chat-history-configure-btn" class="airs-button airs-button-secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <?php _e('Configure', 'ai-chat-search'); ?>
                </button>
            </div>

            <?php $this->render_javascript(); ?>
        </div>
        <?php
    }

    /**
     * Render pagination controls
     *
     * @param int $page Current page
     * @param int $total_pages Total pages
     */
    public function render_pagination($page, $total_pages) {
        if ($total_pages <= 1) {
            return;
        }

        $range = 2;
        $start = max(1, $page - $range);
        $end = min($total_pages, $page + $range);
        ?>
        <div class="airs-pagination-nav">
            <?php if ($page > 1): ?>
                <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $page - 1; ?>">
                    <?php _e('Previous', 'ai-chat-search'); ?>
                </button>
            <?php endif; ?>

            <div class="airs-page-numbers">
                <?php if ($start > 1): ?>
                    <button class="airs-pagination-btn listeo-history-page" data-page="1">1</button>
                    <?php if ($start > 2): ?>
                        <span class="airs-pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="airs-pagination-btn is-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <span class="airs-pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $total_pages; ?>"><?php echo $total_pages; ?></button>
                <?php endif; ?>
            </div>

            <?php if ($page < $total_pages): ?>
                <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $page + 1; ?>">
                    <?php _e('Next', 'ai-chat-search'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a single conversation card
     *
     * @param array $conv Conversation data
     * @param array|null $messages Messages in the conversation, or null to lazy load
     * @param WP_User|null $user_info User info or null for guest
     */
    public function render_conversation_card($conv, $messages, $user_info) {
        $messages_loaded = is_array($messages);
        $message_count = isset($conv['message_count'])
            ? intval($conv['message_count'])
            : ($messages_loaded ? count($messages) : 0);
        ?>
        <div class="airs-conversation-card" data-conversation-id="<?php echo esc_attr($conv['conversation_id']); ?>">
            <div class="airs-conversation-header">
                <div class="airs-conversation-id">
                    <strong><?php _e('Conversation ID:', 'ai-chat-search'); ?></strong>
                    <code class="airs-conversation-id-code"><?php echo esc_html($conv['conversation_id']); ?></code>
                    <?php do_action('ai_chat_search_conversation_id_badge', $conv['conversation_id']); ?>
                    <button type="button" class="delete-conversation-btn" data-id="<?php echo esc_attr($conv['conversation_id']); ?>" title="<?php esc_attr_e('Delete this conversation', 'ai-chat-search'); ?>" style="background: none; border: none; cursor: pointer; padding: 2px 6px; border-radius: 3px; color: #b32d2e; opacity: 0.6; transition: opacity 0.2s; margin-left: -5px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                    </button>
                </div>
                <div class="airs-conversation-meta">
                    <div style="font-size: 12px; color: #666;">
                        <?php if ($user_info): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php echo esc_html($user_info->display_name); ?> (<?php echo esc_html($user_info->user_email); ?>)
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php _e('Guest User', 'ai-chat-search'); ?>
                        <?php endif; ?>
                        <?php if (!empty($conv['ip_address'])): ?>
                            <?php
                            $geo = Listeo_AI_Search_Chat_History::get_country_from_ip($conv['ip_address']);
                            $tip_parts = array();
                            if ($geo) {
                                if (!empty($geo['country_name'])) $tip_parts[] = esc_attr($geo['country_name']);
                                if (!empty($geo['city'])) $tip_parts[] = esc_attr($geo['city']);
                                if (!empty($geo['region'])) $tip_parts[] = esc_attr($geo['region']);
                                if (!empty($geo['continent'])) $tip_parts[] = esc_attr($geo['continent']);
                            }
                            $tip_data = !empty($tip_parts) ? implode('|', $tip_parts) : '';
                            ?>
                            <span class="airs-ip-geo" <?php if ($tip_data): ?>data-geo-tooltip="<?php echo $tip_data; ?>"<?php endif; ?>>
                                <?php if ($geo): ?>
                                    <img src="https://flagcdn.com/16x12/<?php echo esc_attr($geo['country_code']); ?>.png" alt="<?php echo esc_attr(strtoupper($geo['country_code'])); ?>" style="vertical-align: middle;" />
                                <?php endif; ?>
                                <span style="color: #999;"><?php echo esc_html($conv['ip_address']); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 12px; color: #999;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg><?php printf(__('Started: %s ago', 'ai-chat-search'), human_time_diff(strtotime($conv['first_message_at']), current_time('timestamp'))); ?>
                    </div>
                </div>
            </div>

            <?php
            // Get cart events for this conversation from stored data
            $all_cart_events = get_option('listeo_ai_cart_events', array());
            $cart_products = array();
            if (isset($all_cart_events[$conv['conversation_id']])) {
                foreach ($all_cart_events[$conv['conversation_id']] as $event) {
                    $cart_products[] = $event['product_name'];
                }
            }
            ?>
            <div style="font-size: 13px; color: #666; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                <span class="airs-conversation-meta-info">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><?php echo $conv['message_count']; ?> <?php _e('messages', 'ai-chat-search'); ?>
                    <?php if ($conv['first_message_at'] !== $conv['last_message_at']): ?>
                        &bull; <?php printf(__('last %s ago', 'ai-chat-search'), human_time_diff(strtotime($conv['last_message_at']), current_time('timestamp'))); ?>
                    <?php endif; ?>
                    <?php if (!empty($cart_products)): ?>
                        &bull;
                        <span class="airs-cart-indicator" data-cart-tooltip="<?php echo esc_attr(implode(', ', $cart_products)); ?>" style="color: #27ae60; cursor: help;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 2px;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg><?php printf(_n('%d product added', '%d products added', count($cart_products), 'ai-chat-search'), count($cart_products)); ?>
                        </span>
                    <?php endif; ?>
                </span>
                <?php
                // Pro extension point: Conversation Auditor injects its "Analyze with AI" button here.
                do_action('ai_chat_search_conversation_actions', $conv['conversation_id']);
                ?>
            </div>

            <details class="chat-history-details" style="margin-top: 10px;">
                <summary class="airs-view-messages-summary">
                    <span><?php _e('View Messages', 'ai-chat-search'); ?> (<?php echo number_format_i18n($message_count); ?>)</span>
                    <span class="dashicons dashicons-arrow-down-alt2 airs-view-messages-chevron"></span>
                </summary>
                <div class="chat-history-messages" data-loaded="<?php echo $messages_loaded ? '1' : '0'; ?>">
                    <?php if ($messages_loaded): ?>
                        <?php $this->render_conversation_messages($conv['conversation_id'], $messages); ?>
                    <?php else: ?>
                        <div class="airs-audit-loading"><span class="airs-spinner"></span></div>
                    <?php endif; ?>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Render messages for a conversation.
     *
     * @param string $conversation_id Conversation identifier
     * @param array $messages Messages in the conversation
     */
    private function render_conversation_messages($conversation_id, $messages) {
        if (empty($messages)) {
            echo '<p style="text-align: center; padding: 24px; color: #666;">' . esc_html__('No messages found for this conversation.', 'ai-chat-search') . '</p>';
            return;
        }
        ?>
        <?php
        // Hook for displaying pre-chat field data before messages
        do_action('ai_chat_search_conversation_messages_before', $messages, $conversation_id);
        ?>
        <?php foreach ($messages as $msg): ?>
            <div class="airs-chat-msg airs-chat-msg-user">
                <div class="airs-chat-msg-head">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span class="airs-chat-msg-name"><?php _e('User', 'ai-chat-search'); ?></span>
                    <span class="airs-chat-msg-time"><?php echo date_i18n('M j, ' . get_option('time_format'), strtotime($msg['created_at'])); ?></span>
                    <?php if (!empty($msg['page_url'])): ?>
                        <span class="airs-chat-msg-sep">&bull;</span>
                        <a href="<?php echo esc_url($msg['page_url']); ?>"
                           target="_blank"
                           title="<?php echo esc_attr($msg['page_url']); ?>"
                           class="airs-chat-page-link">
                            <span class="airs-chat-page-link-text"><?php echo esc_html($this->get_page_title_from_url($msg['page_url'])); ?></span>
                            <svg class="airs-chat-page-link-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="airs-chat-msg-body">
                    <?php echo esc_html(trim($msg['user_message'])); ?>
                </div>
            </div>

            <div class="airs-chat-msg airs-chat-msg-assistant">
                <div class="airs-chat-msg-head">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg>
                    <span class="airs-chat-msg-name"><?php _e('AI Assistant', 'ai-chat-search'); ?></span>
                    <span class="airs-chat-msg-time"><?php echo esc_html($msg['model_used']); ?></span>
                </div>
                <div class="airs-chat-msg-body">
                    <?php echo nl2br(wp_kses($msg['assistant_message'], array('a' => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array())))); ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php
    }

    /**
     * Render JavaScript for chat history interactions
     */
    private function render_javascript() {
        $nonce = wp_create_nonce('listeo_ai_search_nonce');
        ?>
        <script>
        jQuery(document).ready(function($) {
            function escapeHtml(text) {
                return $('<span>').text(text || '').html();
            }

            // Build geo tooltips from data attributes
            function initGeoTooltips() {
                $('.airs-ip-geo[data-geo-tooltip]').not(':has(.airs-geo-tooltip)').each(function() {
                    var $el = $(this);
                    var parts = $el.attr('data-geo-tooltip').split('|');
                    var labels = ['Country', 'City', 'Region', 'Continent'];
                    var rows = '';
                    for (var i = 0; i < parts.length; i++) {
                        if (parts[i]) {
                            rows += '<div class="airs-geo-row"><span class="airs-geo-label">' + labels[i] + ':</span> <span>' + $('<span>').text(parts[i]).html() + '</span></div>';
                        }
                    }
                    if (rows) {
                        $el.append('<div class="airs-geo-tooltip">' + rows + '</div>');
                    }
                });
            }
            initGeoTooltips();

            // Build cart tooltips from data attributes
            $('.airs-cart-indicator[data-cart-tooltip]').not(':has(.airs-cart-tooltip)').each(function() {
                var $el = $(this);
                var products = $el.attr('data-cart-tooltip');
                if (products) {
                    var items = products.split(', ');
                    var rows = '';
                    for (var i = 0; i < items.length; i++) {
                        rows += '<div style="padding: 2px 0;">' + $('<span>').text(items[i]).html() + '</div>';
                    }
                    $el.css('position', 'relative');
                    $el.append('<div class="airs-cart-tooltip" style="display:none; position:absolute; bottom:100%; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:8px 12px; border-radius:6px; font-size:12px; white-space:nowrap; z-index:100; margin-bottom:6px; box-shadow:0 2px 8px rgba(0,0,0,0.15);">' +
                        '<div style="font-weight:600; margin-bottom:4px; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:4px;"><?php echo esc_js(__('Products added to cart:', 'ai-chat-search')); ?></div>' +
                        rows + '</div>');
                    $el.on('mouseenter', function() { $(this).find('.airs-cart-tooltip').fadeIn(150); });
                    $el.on('mouseleave', function() { $(this).find('.airs-cart-tooltip').fadeOut(150); });
                }
            });

            function loadConversationMessages($details) {
                var $messages = $details.find('.chat-history-messages').first();
                var conversationId = $details.closest('[data-conversation-id]').data('conversation-id');

                if (!conversationId || $messages.attr('data-loaded') === '1' || $messages.attr('data-loading') === '1') {
                    return;
                }

                $messages
                    .attr('data-loading', '1')
                    .html('<div class="airs-audit-loading"><span class="airs-spinner"></span></div>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_load_conversation_messages',
                        nonce: '<?php echo $nonce; ?>',
                        conversation_id: conversationId
                    },
                    success: function(response) {
                        if (response.success) {
                            $messages.html(response.data.messages).attr('data-loaded', '1');
                        } else {
                            $messages.html('<p style="color: #d63638; text-align: center; padding: 20px;">' + escapeHtml(response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to load messages.', 'ai-chat-search')); ?>') + '</p>');
                        }
                    },
                    error: function() {
                        $messages.html('<p style="color: #d63638; text-align: center; padding: 20px;"><?php echo esc_js(__('Failed to load messages. Please try again.', 'ai-chat-search')); ?></p>');
                    },
                    complete: function() {
                        $messages.removeAttr('data-loading');
                    }
                });
            }

            function loadOpenConversationMessages($scope) {
                $scope.find('.chat-history-details[open]').each(function() {
                    loadConversationMessages($(this));
                });
            }

            document.addEventListener('toggle', function(event) {
                if ($(event.target).is('.chat-history-details') && $(event.target).prop('open')) {
                    loadConversationMessages($(event.target));
                }
            }, true);

            $(document).on('click', '.airs-view-messages-summary', function() {
                var $details = $(this).closest('.chat-history-details');
                setTimeout(function() {
                    if ($details.prop('open')) {
                        loadConversationMessages($details);
                    }
                }, 0);
            });

            // Pagination click handler
            $(document).on('click', '.listeo-history-page', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                var $container = $('#listeo-history-conversations');
                var $pagination = $('#listeo-history-pagination');

                $container.html('<p style="text-align: center; padding: 40px;"><span class="airs-spinner"></span> Loading...</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_load_chat_history',
                        nonce: '<?php echo $nonce; ?>',
                        page: page
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.html(response.data.conversations);
                            $pagination.html(response.data.pagination);
                            initGeoTooltips();
                            loadOpenConversationMessages($container);
                            $('html, body').animate({
                                scrollTop: $container.offset().top - 100
                            }, 300);
                        } else {
                            $container.html('<p style="color: #d63638; text-align: center; padding: 20px;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        $container.html('<p style="color: #d63638; text-align: center; padding: 20px;"><?php _e('Failed to load conversations. Please try again.', 'ai-chat-search'); ?></p>');
                    }
                });
            });

            // Search conversations
            function searchConversations(searchTerm) {
                var $container = $('#listeo-history-conversations');
                var $pagination = $('#listeo-history-pagination');
                var $clearBtn = $('#conversation-search-clear');

                $container.html('<p style="text-align: center; padding: 40px;"><span class="airs-spinner"></span> <?php _e('Searching...', 'ai-chat-search'); ?></p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_load_chat_history',
                        nonce: '<?php echo $nonce; ?>',
                        page: 1,
                        search: searchTerm
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.html(response.data.conversations);
                            $pagination.html(response.data.pagination);
                            initGeoTooltips();
                            loadOpenConversationMessages($container);
                            if (searchTerm) {
                                $clearBtn.show();
                            }
                        } else {
                            $container.html('<p style="color: #d63638; text-align: center; padding: 20px;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        $container.html('<p style="color: #d63638; text-align: center; padding: 20px;"><?php _e('Failed to search. Please try again.', 'ai-chat-search'); ?></p>');
                    }
                });
            }

            // Search button click
            $(document).on('click', '#conversation-search-btn', function(e) {
                e.preventDefault();
                var searchTerm = $('#conversation-search-input').val().trim();
                if (searchTerm) {
                    searchConversations(searchTerm);
                }
            });

            // Enter key in search input
            $(document).on('keypress', '#conversation-search-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    var searchTerm = $(this).val().trim();
                    if (searchTerm) {
                        searchConversations(searchTerm);
                    }
                }
            });

            // Clear search
            $(document).on('click', '#conversation-search-clear', function(e) {
                e.preventDefault();
                $('#conversation-search-input').val('');
                $(this).hide();
                searchConversations('');
            });

            // Delete single conversation button handler
            $(document).on('click', '.delete-conversation-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $button = $(this);
                var conversationId = $button.data('id');
                var $card = $button.closest('[data-conversation-id]');

                if (!confirm('<?php _e('Delete this conversation?', 'ai-chat-search'); ?>')) {
                    return;
                }

                $button.prop('disabled', true).css('opacity', '0.3');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_delete_conversation',
                        conversation_id: conversationId,
                        nonce: '<?php echo $nonce; ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $card.slideUp(200, function() {
                                $(this).remove();
                                if ($('#listeo-history-conversations').children().length === 0) {
                                    $('#listeo-history-conversations').html('<p style="text-align: center; padding: 40px; color: #666;"><?php _e('No conversations found.', 'ai-chat-search'); ?></p>');
                                }
                            });
                        } else {
                            alert(response.data.message || '<?php _e('Failed to delete conversation.', 'ai-chat-search'); ?>');
                            $button.prop('disabled', false).css('opacity', '0.6');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to delete conversation. Please try again.', 'ai-chat-search'); ?>');
                        $button.prop('disabled', false).css('opacity', '0.6');
                    }
                });
            });

            // Hover effect for delete button
            $(document).on('mouseenter', '.delete-conversation-btn', function() {
                $(this).css('opacity', '1');
            }).on('mouseleave', '.delete-conversation-btn', function() {
                $(this).css('opacity', '0.6');
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for loading chat history with pagination
     */
    public function ajax_load() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        if (!AI_Chat_Search_Pro_Manager::can_access_conversation_logs()) {
            wp_send_json_error(array(
                'message' => __('Conversation logs are a Pro feature. Please upgrade to access full chat history.', 'ai-chat-search'),
                'upgrade_url' => AI_Chat_Search_Pro_Manager::get_upgrade_url('conversation_logs')
            ));
            return;
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_send_json_error(array('message' => __('Chat history class not found.', 'ai-chat-search')));
            return;
        }

        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $offset = ($page - 1) * self::PER_PAGE;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        global $wpdb;
        $table_name = Listeo_AI_Search_Chat_History::get_table_name();

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $recent_conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    conversation_id,
                    MIN(created_at) as first_message_at,
                    MAX(created_at) as last_message_at,
                    COUNT(*) as message_count,
                    user_id,
                    MAX(ip_address) as ip_address
                FROM {$table_name}
                WHERE conversation_id LIKE %s OR ip_address LIKE %s OR user_message LIKE %s OR assistant_message LIKE %s
                GROUP BY conversation_id
                ORDER BY last_message_at DESC
                LIMIT %d OFFSET %d",
                $search_like,
                $search_like,
                $search_like,
                $search_like,
                self::PER_PAGE,
                $offset
            ), ARRAY_A);

            $total_conversations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT conversation_id) FROM {$table_name} WHERE conversation_id LIKE %s OR ip_address LIKE %s OR user_message LIKE %s OR assistant_message LIKE %s",
                $search_like,
                $search_like,
                $search_like,
                $search_like
            ));
        } else {
            $recent_conversations = Listeo_AI_Search_Chat_History::get_recent_conversations(self::PER_PAGE, $offset);
            $total_conversations = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM {$table_name}");
        }

        $total_pages = ceil($total_conversations / self::PER_PAGE);

        // Build conversations HTML
        ob_start();
        if (empty($recent_conversations)) {
            if (!empty($search)) {
                echo '<p style="text-align: center; padding: 40px; color: #666;">' . sprintf(__('No conversations found matching "%s"', 'ai-chat-search'), esc_html($search)) . '</p>';
            } else {
                echo '<p style="text-align: center; padding: 40px; color: #666;">' . __('No conversations found.', 'ai-chat-search') . '</p>';
            }
        }
        foreach ($recent_conversations as $conv) {
            $user_info = $conv['user_id'] ? get_userdata($conv['user_id']) : null;
            $this->render_conversation_card($conv, null, $user_info);
        }
        $conversations_html = ob_get_clean();

        // Build pagination HTML
        ob_start();
        $this->render_pagination($page, $total_pages);
        $pagination_html = ob_get_clean();

        wp_send_json_success(array(
            'conversations' => $conversations_html,
            'pagination' => $pagination_html,
            'page' => $page,
            'total_pages' => $total_pages
        ));
    }

    /**
     * AJAX handler for lazy-loading messages for a single conversation.
     */
    public function ajax_load_conversation_messages() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        if (!AI_Chat_Search_Pro_Manager::can_access_conversation_logs()) {
            wp_send_json_error(array(
                'message' => __('Conversation logs are a Pro feature. Please upgrade to access full chat history.', 'ai-chat-search'),
                'upgrade_url' => AI_Chat_Search_Pro_Manager::get_upgrade_url('conversation_logs')
            ));
            return;
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_send_json_error(array('message' => __('Chat history class not found.', 'ai-chat-search')));
            return;
        }

        $conversation_id = isset($_POST['conversation_id'])
            ? sanitize_text_field(wp_unslash($_POST['conversation_id']))
            : '';

        if (empty($conversation_id)) {
            wp_send_json_error(array('message' => __('Missing conversation ID.', 'ai-chat-search')));
            return;
        }

        $messages = Listeo_AI_Search_Chat_History::get_conversation($conversation_id);

        ob_start();
        $this->render_conversation_messages($conversation_id, $messages);
        $messages_html = ob_get_clean();

        wp_send_json_success(array(
            'messages' => $messages_html,
            'count' => count($messages),
        ));
    }

    /**
     * AJAX handler for clearing all chat history
     */
    public function ajax_clear() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_send_json_error(array('message' => __('Chat history class not found.', 'ai-chat-search')));
            return;
        }

        global $wpdb;
        $table_name = Listeo_AI_Search_Chat_History::get_table_name();

        $deleted = $wpdb->query("DELETE FROM {$table_name}");

        if ($deleted === false) {
            wp_send_json_error(array('message' => __('Failed to clear chat history.', 'ai-chat-search')));
            return;
        }

        if (function_exists('listeo_ai_clear_all_cart_events')) {
            listeo_ai_clear_all_cart_events();
        }

        do_action('listeo_ai_chat_history_cleared', $deleted);

        wp_send_json_success(array(
            'message' => sprintf(__('Successfully deleted %d chat records.', 'ai-chat-search'), $deleted),
            'deleted' => $deleted
        ));
    }

    /**
     * AJAX handler for deleting a single conversation
     */
    public function ajax_delete_conversation() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field(wp_unslash($_POST['conversation_id'])) : '';

        if (empty($conversation_id)) {
            wp_send_json_error(array('message' => __('Conversation ID is required.', 'ai-chat-search')));
            return;
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_send_json_error(array('message' => __('Chat history class not found.', 'ai-chat-search')));
            return;
        }

        global $wpdb;
        $table_name = Listeo_AI_Search_Chat_History::get_table_name();

        $deleted = $wpdb->delete(
            $table_name,
            array('conversation_id' => $conversation_id),
            array('%s')
        );

        if ($deleted === false) {
            wp_send_json_error(array('message' => __('Failed to delete conversation.', 'ai-chat-search')));
            return;
        }

        if (function_exists('listeo_ai_clear_cart_events_for_conversation')) {
            listeo_ai_clear_cart_events_for_conversation($conversation_id);
        }

        do_action('listeo_ai_chat_history_conversation_deleted', $conversation_id, $deleted);

        wp_send_json_success(array(
            'message' => sprintf(__('Deleted %d message(s) from conversation.', 'ai-chat-search'), $deleted),
            'deleted' => $deleted
        ));
    }

    /**
     * AJAX handler for exporting chat history as CSV
     */
    public function ajax_export_csv() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'listeo_ai_search_nonce')) {
            wp_die(__('Security check failed.', 'ai-chat-search'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'ai-chat-search'));
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_die(__('Chat history class not found.', 'ai-chat-search'));
        }

        $days = isset($_GET['days']) ? intval($_GET['days']) : null;

        Listeo_AI_Search_Chat_History::export_csv($days);
        exit;
    }

    /**
     * Get a display-friendly page title from URL
     * Attempts to resolve the URL to a post/page title, falls back to URL path
     *
     * @param string $url The page URL
     * @return string Display-friendly page name
     */
    private function get_page_title_from_url($url) {
        if (empty($url)) {
            return '';
        }

        // Parse the URL
        $parsed = wp_parse_url($url);
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';

        // Check for homepage
        if (empty($path) || $path === '/') {
            return __('Homepage', 'ai-chat-search');
        }

        // Try to get post ID from URL (works for listings, posts, pages, products)
        $post_id = url_to_postid($url);
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post) {
                return $post->post_title;
            }
        }

        // Fallback: extract last segment of URL path and clean it up
        $segments = explode('/', $path);
        $last_segment = end($segments);

        // Remove common URL patterns
        $last_segment = preg_replace('/\.(html?|php)$/i', '', $last_segment);

        // Convert hyphens/underscores to spaces and capitalize
        $title = str_replace(array('-', '_'), ' ', $last_segment);
        $title = ucwords($title);

        // If still empty, use a shortened URL
        if (empty($title)) {
            return '/' . $path;
        }

        return $title;
    }
}
