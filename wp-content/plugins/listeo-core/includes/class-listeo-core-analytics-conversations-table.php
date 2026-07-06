<?php
/**
 * Listeo Analytics - Conversations Table
 *
 * Displays conversations list in analytics dashboard (adapted from listeo-spy)
 *
 * @package Listeo_Core
 * @subpackage Analytics
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Listeo_Core_Analytics_Conversations_Table extends WP_List_Table {

    public $messages_obj;

    /** Class constructor */
    public function __construct() {
        parent::__construct([
            'singular' => __('Conversation', 'listeo_core'),
            'plural'   => __('Conversations', 'listeo_core'),
            'ajax'     => false
        ]);

        // Get messages instance
        $this->messages_obj = new Listeo_Core_Messages();
    }

    /**
     * Retrieve conversations data from the database
     */
    public static function get_conversations($args, $page_number) {
        global $wpdb;

        if (!$page_number) {
            $page_number = 1;
        }

        $sql = "SELECT * FROM {$wpdb->prefix}listeo_core_conversations WHERE 1=1";

        // Filter by date range if provided
        if (isset($args['days']) && $args['days'] > 0) {
            $date_cutoff = strtotime("-{$args['days']} days");
            $sql .= $wpdb->prepare(" AND last_update >= %d", $date_cutoff);
        }

        // Filter by user
        if (isset($args['user_id']) && !empty($args['user_id'])) {
            $user_id = intval($args['user_id']);
            $sql .= $wpdb->prepare(" AND (user_1 = %d OR user_2 = %d)", $user_id, $user_id);
        }

        // Search
        if (isset($args['s']) && !empty($args['s'])) {
            $search = '%' . $wpdb->esc_like($args['s']) . '%';
            $sql .= $wpdb->prepare(" AND (referral LIKE %s)", $search);
        }

        // Ordering
        if (!empty($_REQUEST['orderby'])) {
            $sql .= ' ORDER BY ' . esc_sql($_REQUEST['orderby']);
            $sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
        } else {
            $sql .= ' ORDER BY last_update DESC';
        }

        // Pagination
        if (isset($args['id'])) {
            $sql .= $wpdb->prepare(" AND id = %d", $args['id']);
        } else {
            $sql .= " LIMIT " . intval($args['per_page']);
            $sql .= ' OFFSET ' . (($page_number - 1) * intval($args['per_page']));
        }

        $result = $wpdb->get_results($sql, 'ARRAY_A');
        return $result;
    }

    /**
     * Returns the count of records in the database.
     */
    public static function record_count($args) {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}listeo_core_conversations WHERE 1=1";

        // Filter by date range
        if (isset($args['days']) && $args['days'] > 0) {
            $date_cutoff = strtotime("-{$args['days']} days");
            $sql .= $wpdb->prepare(" AND last_update >= %d", $date_cutoff);
        }

        // Filter by user
        if (isset($args['user_id']) && !empty($args['user_id'])) {
            $user_id = intval($args['user_id']);
            $sql .= $wpdb->prepare(" AND (user_1 = %d OR user_2 = %d)", $user_id, $user_id);
        }

        // Search
        if (isset($args['s']) && !empty($args['s'])) {
            $search = '%' . $wpdb->esc_like($args['s']) . '%';
            $sql .= $wpdb->prepare(" AND (referral LIKE %s)", $search);
        }

        return $wpdb->get_var($sql);
    }

    /** Text displayed when no conversation data is available */
    public function no_items() {
        esc_html_e('No conversations found.', 'listeo_core');
    }

    /**
     * Render a column when no column specific method exist.
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return sprintf(
                    '<strong>#%d</strong> <a href="#" class="button button-small listeo-view-conversation" data-conversation-id="%d">%s</a>',
                    $item[$column_name],
                    $item['id'],
                    __('View', 'listeo_core')
                );

            case 'referral':
                return $this->messages_obj->get_conversation_referral($item[$column_name]);

            case 'read_user_1':
            case 'read_user_2':
                if ($item[$column_name] == 1) {
                    return '<span class="dashicons dashicons-yes" style="color: #46b450;"></span>';
                } else {
                    return '<span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>';
                }

            case 'user_1':
            case 'user_2':
                if ($item[$column_name] != 0) {
                    $user_data = get_userdata($item[$column_name]);
                    if ($user_data) {
                        return sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            get_edit_user_link($user_data->ID),
                            esc_html($user_data->user_login)
                        );
                    } else {
                        return esc_html__('Deleted User', 'listeo_core');
                    }
                } else {
                    return esc_html__('System', 'listeo_core');
                }

            case 'notification':
                if ($item[$column_name] == 'sent') {
                    return '<span class="dashicons dashicons-yes" style="color: #46b450;"></span>';
                } else {
                    return '<span class="dashicons dashicons-minus" style="color: #999;"></span>';
                }

            case 'last_update':
                $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $item[$column_name]);
                return esc_html($date);

            case 'message_count':
                global $wpdb;
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}listeo_core_messages WHERE conversation_id = %d",
                    $item['id']
                ));
                return '<span class="message-count-badge">' . intval($count) . '</span>';

            default:
                return '';
        }
    }

    /**
     *  Associative array of columns
     */
    function get_columns() {
        $columns = [
            'id'                    => __('ID', 'listeo_core'),
            'user_1'                => __('User 1', 'listeo_core'),
            'user_2'                => __('User 2', 'listeo_core'),
            'referral'              => __('Source', 'listeo_core'),
            'message_count'         => __('Messages', 'listeo_core'),
            'read_user_1'           => __('Read (U1)', 'listeo_core'),
            'read_user_2'           => __('Read (U2)', 'listeo_core'),
            'last_update'           => __('Last Update', 'listeo_core'),
            'notification'          => __('Notified', 'listeo_core'),
        ];

        return $columns;
    }

    /**
     * Columns to make sortable.
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'id'             => array('id', true),
            'last_update'    => array('last_update', false),
        );

        return $sortable_columns;
    }

    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $args = array();

        if (!empty($_REQUEST['id'])) {
            $args['id'] = sanitize_text_field($_REQUEST['id']);
        }
        if (!empty($_REQUEST['user_id'])) {
            $args['user_id'] = sanitize_text_field($_REQUEST['user_id']);
        }
        if (!empty($_REQUEST['days'])) {
            $args['days'] = intval($_REQUEST['days']);
        }
        if (!empty($_REQUEST['s'])) {
            $args['s'] = sanitize_text_field($_REQUEST['s']);
        }

        $args['per_page'] = 999; // Show all conversations without pagination
        $current_page = 1;

        $total_items = self::record_count($args);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $args['per_page']
        ]);

        $this->items = self::get_conversations($args, $current_page);
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     * Disabled - no search needed
     */
    protected function extra_tablenav($which) {
        // Removed search functionality
        return;
    }

    /**
     * Display the conversation detail view
     */
    public function display_conversation_detail($conversation_id) {
        $args['id'] = $conversation_id;
        $conversation = $this->messages_obj->get_conversation($conversation_id);

        if (!$conversation) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('This conversation does not exist.', 'listeo_core'); ?></p>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=listeo-analytics&tab=messages')); ?>" class="button">
                <?php esc_html_e('Back to Conversations', 'listeo_core'); ?>
            </a>
            <?php
            return;
        }

        $user1 = get_userdata($conversation[0]->user_1);
        $user2 = get_userdata($conversation[0]->user_2);

        // Get user names
        if (!$user1) {
            $name1 = esc_html__('Deleted User', 'listeo_core');
        } else {
            $name1 = (!empty($user1->first_name) && !empty($user1->last_name))
                ? $user1->first_name . ' ' . $user1->last_name
                : $user1->user_nicename;
        }

        if (!$user2) {
            $name2 = esc_html__('Deleted User', 'listeo_core');
        } else {
            $name2 = (!empty($user2->first_name) && !empty($user2->last_name))
                ? $user2->first_name . ' ' . $user2->last_name
                : $user2->user_nicename;
        }

        $referral = $this->messages_obj->get_conversation_referral($conversation[0]->referral);
        ?>
        <div class="listeo-conversation-detail">
            <div class="conversation-header">
                <h2><?php esc_html_e('Conversation Details', 'listeo_core'); ?></h2>
                <p class="conversation-meta">
                    <strong><?php esc_html_e('Between:', 'listeo_core'); ?></strong>
                    <?php if ($user1): ?>
                        <a href="<?php echo esc_url(get_author_posts_url($user1->ID)); ?>" target="_blank">
                            <?php echo esc_html($name1); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($name1); ?>
                    <?php endif; ?>
                    <?php esc_html_e('and', 'listeo_core'); ?>
                    <?php if ($user2): ?>
                        <a href="<?php echo esc_url(get_author_posts_url($user2->ID)); ?>" target="_blank">
                            <?php echo esc_html($name2); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($name2); ?>
                    <?php endif; ?>
                    <br>
                    <?php if ($referral): ?>
                        <strong><?php esc_html_e('Source:', 'listeo_core'); ?></strong>
                        <span><?php echo wp_kses_post($referral); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="conversation-messages">
                <?php
                $messages = $this->messages_obj->get_single_conversation('1', $conversation_id);
                if ($messages && count($messages) > 0):
                    foreach ($messages as $message):
                        $is_user1 = ($message->sender_id == ($user1 ? $user1->ID : 0));
                        ?>
                        <div class="message-bubble <?php echo $is_user1 ? 'user-1' : 'user-2'; ?>">
                            <div class="message-avatar">
                                <a href="<?php echo esc_url(get_author_posts_url($message->sender_id)); ?>" target="_blank">
                                    <?php echo get_avatar($message->sender_id, 50); ?>
                                </a>
                            </div>
                            <div class="message-content">
                                <div class="message-text"><?php echo wp_kses_post(wpautop($message->message)); ?></div>
                                <?php if (!empty($message->attachment_url)): ?>
                                    <div class="message-attachment">
                                        <i class="dashicons dashicons-paperclip"></i>
                                        <a href="<?php echo esc_url($message->attachment_url); ?>" target="_blank">
                                            <?php echo esc_html($message->attachment_name); ?>
                                        </a>
                                        <span class="attachment-size">(<?php echo size_format($message->attachment_size); ?>)</span>
                                    </div>
                                <?php endif; ?>
                                <div class="message-time">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $message->created_at); ?>
                                </div>
                            </div>
                        </div>
                    <?php
                    endforeach;
                else:
                    ?>
                    <p class="no-messages"><?php esc_html_e('No messages in this conversation yet.', 'listeo_core'); ?></p>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url(admin_url('admin.php?page=listeo-analytics&tab=messages')); ?>" class="button button-primary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e('Back to Conversations', 'listeo_core'); ?>
            </a>
        </div>
        <?php
    }
}
