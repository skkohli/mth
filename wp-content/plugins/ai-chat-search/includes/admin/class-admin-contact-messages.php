<?php
/**
 * Admin Contact Messages Handler
 *
 * Handles contact messages rendering and AJAX operations for the admin dashboard.
 *
 * @package AI_Chat_Search
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin_Contact_Messages
 *
 * Manages contact messages display and operations in the admin area.
 */
class Admin_Contact_Messages {

    /**
     * Items per page for pagination
     */
    const PER_PAGE = 5;

    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct() {
        // Contact form settings AJAX handlers
        add_action('wp_ajax_listeo_ai_test_contact_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_listeo_ai_save_contact_form_settings', array($this, 'ajax_save_settings'));

        // Contact messages AJAX handlers
        add_action('wp_ajax_listeo_ai_view_contact_message', array($this, 'ajax_view_message'));
        add_action('wp_ajax_listeo_ai_delete_contact_message', array($this, 'ajax_delete_message'));
        add_action('wp_ajax_listeo_ai_load_contact_messages', array($this, 'ajax_load_messages'));
    }

    /**
     * Render the complete contact messages section
     */
    public function render_section() {
        // Check if class exists
        if (!class_exists('Listeo_AI_Search_Contact_Messages')) {
            return;
        }

        // Create table if it doesn't exist
        if (!Listeo_AI_Search_Contact_Messages::table_exists()) {
            Listeo_AI_Search_Contact_Messages::create_table();
        }

        // Get stats
        $stats = Listeo_AI_Search_Contact_Messages::get_stats(30);
        $messages = Listeo_AI_Search_Contact_Messages::get_messages(self::PER_PAGE, 0);
        $total_count = Listeo_AI_Search_Contact_Messages::get_total_count();
        ?>
        <div class="airs-card airs-card-toggleable" data-toggle-id="stats-contact-messages">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path><rect x="2" y="4" width="20" height="16" rx="2"></rect></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Emails Sent via Chat', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Messages sent via AI chat tool and contact form button.', 'ai-chat-search'); ?></p>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2 airs-card-toggle-icon"></span>
            </div>
            <div class="airs-card-body">
                <?php if (!AI_Chat_Search_Pro_Manager::can_access_conversation_logs()): ?>
                    <?php $this->render_locked(); ?>
                <?php else: ?>
                    <?php $this->render_stats_boxes($stats, $total_count); ?>
                    <?php $this->render_messages_list($messages, $total_count); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php $this->render_modal(); ?>
        <?php $this->render_javascript(); ?>
        <?php
    }

    /**
     * Render stats boxes
     *
     * @param array $stats Stats array
     * @param int $total_count Total message count
     */
    private function render_stats_boxes($stats, $total_count) {
        ?>
        <div class="airs-stats-boxes">
            <div class="airs-stat-box airs-stat-box-green">
                <div class="airs-stat-number airs-stat-number-green">
                    <?php echo intval($total_count); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-green">
                    <?php _e('Total Emails', 'ai-chat-search'); ?>
                </div>
            </div>
            <div class="airs-stat-box airs-stat-box-blue">
                <div class="airs-stat-number airs-stat-number-blue">
                    <?php echo intval($stats['from_llm']); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-blue">
                    <?php _e('Via AI Chat', 'ai-chat-search'); ?>
                </div>
            </div>
            <div class="airs-stat-box airs-stat-box-purple">
                <div class="airs-stat-number airs-stat-number-purple">
                    <?php echo intval($stats['from_button']); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-purple">
                    <?php _e('Via Button', 'ai-chat-search'); ?>
                </div>
            </div>
            <?php if ($stats['failed_emails'] > 0): ?>
            <div class="airs-stat-box airs-stat-box-red">
                <div class="airs-stat-number airs-stat-number-red">
                    <?php echo intval($stats['failed_emails']); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-red">
                    <?php _e('Failed Emails', 'ai-chat-search'); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render messages list
     *
     * @param array $messages Messages array
     * @param int $total_count Total message count
     */
    private function render_messages_list($messages, $total_count) {
        if (empty($messages)): ?>
            <p style="padding: 20px; text-align: center; color: #666;">
                <?php _e('No contact messages yet. Messages will appear here when users send them via the AI chat or contact form button.', 'ai-chat-search'); ?>
            </p>
        <?php else: ?>
            <div id="contact-messages-container" style="margin-top: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <?php $this->render_message_box($msg); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($total_count > self::PER_PAGE): ?>
            <div class="airs-pagination">
                <button type="button" class="airs-pagination-btn load-more-contact-messages" data-offset="<?php echo self::PER_PAGE; ?>" data-total="<?php echo intval($total_count); ?>">
                    <?php printf(__('Load More (%d remaining)', 'ai-chat-search'), $total_count - self::PER_PAGE); ?>
                </button>
            </div>
            <?php endif; ?>
        <?php endif;
    }

    /**
     * Render a single message box
     *
     * @param array $msg Message data
     */
    public function render_message_box($msg) {
        ?>
        <div class="contact-message-box view-contact-message" data-message-id="<?php echo intval($msg['id']); ?>" data-id="<?php echo intval($msg['id']); ?>">
            <div class="contact-message-header">
                <div class="contact-message-info">
                    <strong><?php echo esc_html($msg['sender_name']); ?></strong>
                    <span class="contact-message-email"><?php echo esc_html($msg['sender_email']); ?></span>
                </div>
                <div class="contact-message-meta">
                    <?php if ($msg['source'] === 'llm'): ?>
                        <span class="airs-badge airs-badge-blue"><?php _e('AI', 'ai-chat-search'); ?></span>
                    <?php else: ?>
                        <span class="airs-badge airs-badge-gray"><?php _e('Button', 'ai-chat-search'); ?></span>
                    <?php endif; ?>
                    <?php if (!$msg['email_sent']): ?>
                        <span class="airs-badge airs-badge-red" title="<?php esc_attr_e('Email failed to send', 'ai-chat-search'); ?>">!</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="contact-message-time">
                <?php echo esc_html(human_time_diff(strtotime($msg['created_at']), current_time('timestamp'))); ?> <?php _e('ago', 'ai-chat-search'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render locked preview for free users
     */
    private function render_locked() {
        ?>
        <div class="ai-chat-pro-feature-locked">
            <!-- Blurred preview with dummy stat boxes -->
            <div class="preview-container preview-blurred">
                <div class="airs-stats-boxes">
                    <!-- Total Emails -->
                    <div class="airs-stat-box airs-stat-box-green">
                        <div class="airs-stat-number airs-stat-number-green">
                            23
                        </div>
                        <div class="airs-stat-label airs-stat-label-green">
                            <?php _e('Total Emails', 'ai-chat-search'); ?>
                        </div>
                    </div>

                    <!-- Via AI Chat -->
                    <div class="airs-stat-box airs-stat-box-blue">
                        <div class="airs-stat-number airs-stat-number-blue">
                            18
                        </div>
                        <div class="airs-stat-label airs-stat-label-blue">
                            <?php _e('Via AI Chat', 'ai-chat-search'); ?>
                        </div>
                    </div>

                    <!-- Via Button -->
                    <div class="airs-stat-box airs-stat-box-purple">
                        <div class="airs-stat-number airs-stat-number-purple">
                            5
                        </div>
                        <div class="airs-stat-label airs-stat-label-purple">
                            <?php _e('Via Button', 'ai-chat-search'); ?>
                        </div>
                    </div>
                </div>

                <!-- Dummy messages -->
                <div style="margin-top: 20px;">
                    <?php
                    $dummy_messages = array(
                        array('name' => 'John Smith', 'email' => 'john.smith@example.com', 'source' => 'llm', 'time' => 2),
                        array('name' => 'Sarah Johnson', 'email' => 'sarah.johnson@email.com', 'source' => 'button', 'time' => 5),
                    );

                    foreach ($dummy_messages as $msg):
                    ?>
                    <div class="contact-message-box" style="pointer-events: none;">
                        <div class="contact-message-header">
                            <div class="contact-message-info">
                                <strong><?php echo esc_html($msg['name']); ?></strong>
                                <span class="contact-message-email"><?php echo esc_html($msg['email']); ?></span>
                            </div>
                            <div class="contact-message-meta">
                                <?php if ($msg['source'] === 'llm'): ?>
                                    <span class="airs-badge airs-badge-blue"><?php _e('AI', 'ai-chat-search'); ?></span>
                                <?php else: ?>
                                    <span class="airs-badge airs-badge-gray"><?php _e('Button', 'ai-chat-search'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="contact-message-time">
                            <?php echo sprintf(__('%d hours ago', 'ai-chat-search'), $msg['time']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Overlay -->
            <div class="lock-overlay" style="background: rgba(255, 255, 255, 0.55); backdrop-filter: blur(3px);">
                <div class="lock-content">
                    <h3><?php _e('Contact Message Tracking', 'ai-chat-search'); ?></h3>

                    <ul class="benefits-list">
                        <li><?php _e('Track all messages sent via AI chat', 'ai-chat-search'); ?></li>
                        <li><?php _e('View complete message content and context', 'ai-chat-search'); ?></li>
                    </ul>

                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('contact_messages')); ?>"
                       class="button button-primary button-hero" target="_blank">
                        <?php _e('Upgrade to Pro', 'ai-chat-search'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the view message modal
     */
    private function render_modal() {
        ?>
        <!-- View Message Modal -->
        <div id="contact-message-modal" class="airs-modal" style="display: none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content" style="max-width: 600px;">
                <div class="airs-modal-header">
                    <h3><?php _e('Contact Message', 'ai-chat-search'); ?></h3>
                    <button type="button" class="listeo-ai-modal-close contact-message-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="airs-modal-body" id="contact-message-modal-body">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="airs-modal-footer">
                    <button type="button" class="airs-button airs-button-secondary delete-contact-message" id="contact-message-delete-btn">
                        <?php _e('Delete', 'ai-chat-search'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render JavaScript for contact message interactions
     */
    private function render_javascript() {
        $nonce = wp_create_nonce('listeo_ai_contact_messages_nonce');
        ?>
        <script>
        jQuery(document).ready(function($) {
            var currentMessageId = null;

            // View message - delegated on the whole row (no inline buttons anymore)
            $(document).on('click', '.view-contact-message', function() {
                var messageId = $(this).data('id');
                if (!messageId) return;
                currentMessageId = messageId;

                var $modal = $('#contact-message-modal');
                var $body = $('#contact-message-modal-body');

                $body.html('<p style="text-align: center; padding: 20px;"><span class="airs-spinner" style="margin-right: 6px;"></span><?php _e('Loading...', 'ai-chat-search'); ?></p>');
                $modal.fadeIn(200);

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'listeo_ai_view_contact_message',
                        nonce: '<?php echo $nonce; ?>',
                        message_id: messageId
                    },
                    success: function(response) {
                        if (response.success) {
                            $body.html(response.data.html);
                        } else {
                            $body.html('<p style="color: #dc3232;">' + (response.data.message || '<?php _e('Error loading message.', 'ai-chat-search'); ?>') + '</p>');
                        }
                    },
                    error: function() {
                        $body.html('<p style="color: #dc3232;"><?php _e('Error loading message.', 'ai-chat-search'); ?></p>');
                    }
                });
            });

            // Close modal
            $(document).on('click', '.contact-message-modal-close, #contact-message-modal .airs-modal-overlay', function() {
                $('#contact-message-modal').fadeOut(200);
                currentMessageId = null;
            });

            // Delete message - now lives in the modal footer
            $(document).on('click', '#contact-message-delete-btn', function() {
                if (!currentMessageId) return;
                if (!confirm('<?php _e('Are you sure you want to delete this message?', 'ai-chat-search'); ?>')) {
                    return;
                }

                var $btn = $(this);
                var messageId = currentMessageId;
                var $box = $('.contact-message-box[data-message-id="' + messageId + '"]');

                $btn.prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'listeo_ai_delete_contact_message',
                        nonce: '<?php echo $nonce; ?>',
                        message_id: messageId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#contact-message-modal').fadeOut(200);
                            currentMessageId = null;
                            $box.fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert(response.data.message || '<?php _e('Error deleting message.', 'ai-chat-search'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Error deleting message.', 'ai-chat-search'); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Load more messages
            $(document).on('click', '.load-more-contact-messages', function() {
                var $btn = $(this);
                var offset = parseInt($btn.data('offset'));
                var total = parseInt($btn.data('total'));

                $btn.prop('disabled', true).html('<span class="airs-spinner" style="margin-right: 6px;"></span><?php _e('Loading...', 'ai-chat-search'); ?>');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'listeo_ai_load_contact_messages',
                        nonce: '<?php echo $nonce; ?>',
                        offset: offset
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            $('#contact-messages-container').append(response.data.html);
                            var newOffset = offset + <?php echo self::PER_PAGE; ?>;
                            var remaining = total - newOffset;

                            if (remaining > 0) {
                                $btn.data('offset', newOffset)
                                    .prop('disabled', false)
                                    .text('<?php _e('Load More', 'ai-chat-search'); ?> (' + remaining + ' <?php _e('remaining', 'ai-chat-search'); ?>)');
                            } else {
                                $btn.remove();
                            }
                        } else {
                            $btn.remove();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php _e('Load More', 'ai-chat-search'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for testing contact email
     */
    public function ajax_test_email() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_test_email', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get settings
        $recipient = get_option('listeo_ai_contact_form_recipient', get_option('admin_email'));
        $from_name = get_option('listeo_ai_contact_form_from_name', get_bloginfo('name'));
        $from_email = get_option('listeo_ai_contact_form_from_email', get_option('admin_email'));

        // Prepare test email
        $subject = sprintf(__('[%s] Test Email from AI Chat Contact Form', 'ai-chat-search'), get_bloginfo('name'));
        $message = sprintf(
            __("This is a test email from the PurioChat plugin.\n\n" .
            "If you received this email, your contact form email delivery is working correctly.\n\n" .
            "Settings:\n" .
            "- Recipient: %s\n" .
            "- From Name: %s\n" .
            "- From Email: %s\n\n" .
            "Sent at: %s", 'ai-chat-search'),
            $recipient,
            $from_name,
            $from_email,
            current_time('mysql')
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );

        // Send test email
        $sent = wp_mail($recipient, $subject, $message, $headers);

        if ($sent) {
            wp_send_json_success(array(
                'message' => sprintf(__('Test email sent to %s. Please check your inbox (and spam folder).', 'ai-chat-search'), $recipient)
            ));
        } else {
            // Try to get error info
            global $phpmailer;
            $error_message = '';
            if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_message = $phpmailer->ErrorInfo;
            }

            wp_send_json_error(array(
                'message' => sprintf(
                    __('Failed to send test email. %s', 'ai-chat-search'),
                    $error_message ? sprintf(__('Error: %s', 'ai-chat-search'), $error_message) : __('Check your server mail configuration or install an SMTP plugin.', 'ai-chat-search')
                )
            ));
        }
    }

    /**
     * AJAX handler for saving contact form settings
     */
    public function ajax_save_settings() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_contact_form_settings', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Sanitize and save settings
        $recipient = isset($_POST['recipient']) ? sanitize_email($_POST['recipient']) : '';
        $from_name = isset($_POST['from_name']) ? sanitize_text_field($_POST['from_name']) : '';
        $from_email = isset($_POST['from_email']) ? sanitize_email($_POST['from_email']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $success_message = isset($_POST['success_message']) ? sanitize_text_field($_POST['success_message']) : '';

        // Validate recipient email
        if (!empty($recipient) && !is_email($recipient)) {
            wp_send_json_error(array('message' => __('Invalid recipient email address.', 'ai-chat-search')));
            return;
        }

        // Validate from email
        if (!empty($from_email) && !is_email($from_email)) {
            wp_send_json_error(array('message' => __('Invalid from email address.', 'ai-chat-search')));
            return;
        }

        // Save settings
        update_option('listeo_ai_contact_form_recipient', $recipient);
        update_option('listeo_ai_contact_form_from_name', $from_name);
        update_option('listeo_ai_contact_form_from_email', $from_email);
        update_option('listeo_ai_contact_form_subject', $subject);
        update_option('listeo_ai_contact_form_success_message', $success_message);

        wp_send_json_success(array(
            'message' => __('Settings saved successfully!', 'ai-chat-search')
        ));
    }

    /**
     * AJAX handler for viewing a contact message
     */
    public function ajax_view_message() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_contact_messages_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Validate message ID
        $message_id = isset($_POST['message_id']) ? absint($_POST['message_id']) : 0;
        if (!$message_id) {
            wp_send_json_error(array('message' => __('Invalid message ID.', 'ai-chat-search')));
            return;
        }

        // Get message
        if (!class_exists('Listeo_AI_Search_Contact_Messages')) {
            wp_send_json_error(array('message' => __('Contact messages class not found.', 'ai-chat-search')));
            return;
        }

        $message = Listeo_AI_Search_Contact_Messages::get_message($message_id);
        if (!$message) {
            wp_send_json_error(array('message' => __('Message not found.', 'ai-chat-search')));
            return;
        }

        // Build HTML for modal
        ob_start();
        ?>
        <div class="contact-message-details">
            <div class="field-row">
                <span class="field-label"><?php _e('Date:', 'ai-chat-search'); ?></span>
                <span class="field-value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message['created_at']))); ?></span>
            </div>
            <div class="field-row">
                <span class="field-label"><?php _e('Name:', 'ai-chat-search'); ?></span>
                <span class="field-value"><?php echo esc_html($message['sender_name']); ?></span>
            </div>
            <div class="field-row">
                <span class="field-label"><?php _e('Email:', 'ai-chat-search'); ?></span>
                <span class="field-value"><a href="mailto:<?php echo esc_attr($message['sender_email']); ?>"><?php echo esc_html($message['sender_email']); ?></a></span>
            </div>
            <div class="field-row">
                <span class="field-label"><?php _e('Source:', 'ai-chat-search'); ?></span>
                <span class="field-value">
                    <?php if ($message['source'] === 'llm'): ?>
                        <span class="airs-badge airs-badge-blue"><?php _e('AI Chat', 'ai-chat-search'); ?></span>
                    <?php else: ?>
                        <span class="airs-badge airs-badge-gray"><?php _e('Button', 'ai-chat-search'); ?></span>
                    <?php endif; ?>
                    <?php if (!$message['email_sent']): ?>
                        <span class="airs-badge airs-badge-red"><?php _e('Email Failed', 'ai-chat-search'); ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($message['page_url'])): ?>
            <div class="field-row">
                <span class="field-label"><?php _e('Page:', 'ai-chat-search'); ?></span>
                <span class="field-value"><a href="<?php echo esc_url($message['page_url']); ?>" target="_blank"><?php echo esc_html($message['page_url']); ?></a></span>
            </div>
            <?php endif; ?>
            <?php if ($message['source'] === 'llm' && !empty($message['conversation_id'])): ?>
            <div class="field-row">
                <span class="field-label"><?php _e('Conversation ID:', 'ai-chat-search'); ?></span>
                <span class="field-value"><code class="airs-conversation-id-code"><?php echo esc_html($message['conversation_id']); ?></code></span>
            </div>
            <?php endif; ?>
            <div class="field-row" style="grid-template-columns: 1fr;">
                <span class="field-label"><?php _e('Message:', 'ai-chat-search'); ?></span>
                <div class="message-content"><?php echo esc_html($message['message']); ?></div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX handler for deleting a contact message
     */
    public function ajax_delete_message() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_contact_messages_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Validate message ID
        $message_id = isset($_POST['message_id']) ? absint($_POST['message_id']) : 0;
        if (!$message_id) {
            wp_send_json_error(array('message' => __('Invalid message ID.', 'ai-chat-search')));
            return;
        }

        // Delete message
        if (!class_exists('Listeo_AI_Search_Contact_Messages')) {
            wp_send_json_error(array('message' => __('Contact messages class not found.', 'ai-chat-search')));
            return;
        }

        $deleted = Listeo_AI_Search_Contact_Messages::delete_message($message_id);
        if (!$deleted) {
            wp_send_json_error(array('message' => __('Failed to delete message.', 'ai-chat-search')));
            return;
        }

        wp_send_json_success(array('message' => __('Message deleted.', 'ai-chat-search')));
    }

    /**
     * AJAX handler for loading more contact messages
     */
    public function ajax_load_messages() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_contact_messages_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get offset
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

        // Get messages
        if (!class_exists('Listeo_AI_Search_Contact_Messages')) {
            wp_send_json_error(array('message' => __('Contact messages class not found.', 'ai-chat-search')));
            return;
        }

        $messages = Listeo_AI_Search_Contact_Messages::get_messages(self::PER_PAGE, $offset);
        if (empty($messages)) {
            wp_send_json_success(array('html' => ''));
            return;
        }

        // Build HTML for message boxes
        ob_start();
        foreach ($messages as $msg) {
            $this->render_message_box($msg);
        }
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }
}
