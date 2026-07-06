<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Listeo Core Report Feature
 * Adds a reporting system for listings to notify admins about inappropriate content
 */
class Listeo_Core_Report_Feature
{

    /** @var object Class Instance */
    private static $instance;

    /**
     * Get the class instance
     * @return Listeo_Core_Report_Feature
     */
    public static function get_instance()
    {
        return null === self::$instance ? (self::$instance = new self) : self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {

        $enable_report = get_option('listeo_report_listing');
        if (empty($enable_report)) {
            return;
        }
        // Check and create database table if needed
        $this->check_database();

        // Initialize hooks
        $this->init_hooks();

        // Register activation hook for database creation
        register_activation_hook(__FILE__, array($this, 'create_reports_table'));
    }

    /**
     * Check if database table exists, create if it doesn't
     */
    private function check_database()
    {
        global $wpdb;

        // Get current database version
        $db_version = get_option('listeo_reports_db_version', '0');
        $current_version = '1.0';

        // If the database version is current, no need to check/create table
        if (version_compare($db_version, $current_version, '>=')) {
            return;
        }

        // Check if the table exists
        $table_name = $wpdb->prefix . 'listeo_core_listing_reports';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Table doesn't exist, create it
            $this->create_reports_table();
        }

        // Update the database version
        update_option('listeo_reports_db_version', $current_version);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Frontend hooks
        add_action('listeo/single-listing/sidebar-end', array($this, 'add_report_button'));
        // register the script for the report button

        // AJAX handler
        add_action('wp_ajax_listeo_report_listing', array($this, 'handle_listing_report'));

        // Admin hooks
        add_action('admin_menu', array($this, 'add_reports_menu'));

        // Notification hooks
        add_action('admin_bar_menu', array($this, 'admin_bar_notification'), 999);
        add_action('admin_footer', array($this, 'admin_notification_styles'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts()
    {
        // Enqueue the script for the report button
        wp_enqueue_script('listeo-report-listing', LISTEO_CORE_URL . 'assets/js/listeo.listing-report.js', array('jquery'), '1.0', true);

        // Enqueue the CSS for the report button

    }
    /**
     * Create the database table for storing reports
     */
    public function create_reports_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'listeo_core_listing_reports';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL auto_increment,
            listing_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            report_reason varchar(255) NOT NULL,
            report_description text,
            report_status varchar(20) DEFAULT 'pending',
            report_date datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add report button to single listing
     */
    public function add_report_button()
    {
        if (!is_singular('listing')) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
?>
        <div class="listing-report-button-container">

            <a href="#report-listing-dialog" class="popup-with-zoom-anim"><?php esc_html_e('Report this listing', 'listeo_core'); ?></a>
           
        </div>

        <!-- Report Modal -->
        <?php if (is_user_logged_in()): ?>

            <div id="report-listing-dialog" class="listeo-dialog zoom-anim-dialog mfp-hide">
                <div class="small-dialog-header">
                    <h3><?php esc_html_e('Report Listings', 'listeo_core'); ?></h3>
                </div>
                <div class="message-reply margin-top-0">
                    <form id="report-listing-form">
                        <div class="form-group">
                            <label for="report_reason"><?php esc_html_e('Reason', 'listeo_core'); ?></label>
                            <select name="report_reason" id="report_reason" class="select2-single">
                                <option value=""><?php esc_html_e('Select a reason', 'listeo_core'); ?></option>
                                <option value="inappropriate"><?php esc_html_e('Inappropriate Content', 'listeo_core'); ?></option>
                                <option value="spam"><?php esc_html_e('Spam', 'listeo_core'); ?></option>
                                <option value="fake"><?php esc_html_e('Fake or Misleading', 'listeo_core'); ?></option>
                                <option value="wrong_category"><?php esc_html_e('Wrong Category', 'listeo_core'); ?></option>
                                <option value="duplicate"><?php esc_html_e('Duplicate Listing', 'listeo_core'); ?></option>
                                <option value="other"><?php esc_html_e('Other', 'listeo_core'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="report_description"><?php esc_html_e('Description', 'listeo_core'); ?></label>
                            <textarea name="report_description" id="report_description" placeholder="<?php esc_attr_e('Please describe the issue...', 'listeo_core'); ?>" required></textarea>
                        </div>
                        <input type="hidden" name="listing_id" value="<?php echo get_the_ID(); ?>">
                        <?php wp_nonce_field('report_listing_nonce', 'report_nonce'); ?>
                        <button type="submit" class="button"><?php esc_html_e('Submit Report', 'listeo_core'); ?></button>
                    </form>
                    <div class="notification notice margin-top-20" style="display:none;"></div>
                </div>
            </div>



        <?php endif; ?>
    <?php
    }



    /**
     * Handle the AJAX submission of listing reports
     */
    public function handle_listing_report()
    {
        check_ajax_referer('report_listing_nonce', 'report_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => esc_html__('You must be logged in to report listings', 'listeo_core')));
        }

        $listing_id = absint($_POST['listing_id']);
        $reason = sanitize_text_field($_POST['report_reason']);
        $description = sanitize_textarea_field($_POST['report_description']);
        $user_id = get_current_user_id();

        // Validation
        if (empty($listing_id) || !get_post($listing_id)) {
            wp_send_json_error(array('message' => esc_html__('Invalid listing', 'listeo_core')));
        }

        if (empty($reason)) {
            wp_send_json_error(array('message' => esc_html__('Please select a reason', 'listeo_core')));
        }

        if (empty($description) ) {
            wp_send_json_error(array('message' => esc_html__('Please provide a detailed description of the issue', 'listeo_core')));
        }

        global $wpdb;

        // Check if user already reported this listing
        $existing_report = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}listeo_core_listing_reports 
            WHERE listing_id = %d AND user_id = %d",
            $listing_id,
            $user_id
        ));

        if ($existing_report) {
            wp_send_json_error(array('message' => esc_html__('You have already reported this listing', 'listeo_core')));
        }

        // Insert report
        $result = $wpdb->insert(
            $wpdb->prefix . 'listeo_core_listing_reports',
            array(
                'listing_id' => $listing_id,
                'user_id' => $user_id,
                'report_reason' => $reason,
                'report_description' => $description,
                'report_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );

        if (!$result) {
            wp_send_json_error(array('message' => esc_html__('Failed to submit report. Please try again later.', 'listeo_core')));
        }

        // Send notification email to admin
        $this->send_admin_notification($listing_id, $reason, $description, $user_id);

        // Return success
        wp_send_json_success(array('message' => esc_html__('Thank you for reporting this listing. We will review it shortly.', 'listeo_core')));
    }

    /**
     * Send email notification to admin about the new report
     */
    private function send_admin_notification($listing_id, $reason, $description, $user_id)
    {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $listing_title = get_the_title($listing_id);
        $user_data = get_userdata($user_id);
        $user_name = $user_data ? $user_data->display_name : __('Unknown user', 'listeo_core');

        $subject = sprintf(__('[%s] New listing report: "%s"', 'listeo_core'), $site_name, $listing_title);

        $report_reasons = array(
            'inappropriate' => __('Inappropriate Content', 'listeo_core'),
            'spam' => __('Spam', 'listeo_core'),
            'fake' => __('Fake or Misleading', 'listeo_core'),
            'wrong_category' => __('Wrong Category', 'listeo_core'),
            'duplicate' => __('Duplicate Listing', 'listeo_core'),
            'other' => __('Other', 'listeo_core')
        );

        $reason_text = isset($report_reasons[$reason]) ? $report_reasons[$reason] : $reason;

        $message = sprintf(
            __("A new report has been submitted for listing \"%s\"\n\nReason: %s\nDescription: %s\nReporter: %s (ID: %d)\n\nView listing: %s\nManage reports: %s", 'listeo_core'),
            $listing_title,
            $reason_text,
            $description,
            $user_name,
            $user_id,
            get_permalink($listing_id),
            admin_url('admin.php?page=listeo-reports')
        );

        wp_mail($admin_email, $subject, $message);
    }

    public function add_reports_menu()
    {
        // Add as top-level menu item
        add_menu_page(
            __('Listing Reports', 'listeo_core'),
            __('Listing Reports', 'listeo_core'),
            'manage_options',
            'listeo-reports',
            array($this, 'reports_page'),
            'dashicons-flag', // Icon
            58  // Position after Listings
        );
    }
    /**
     * Display the reports management page
     */
    public function reports_page()
    {
        global $wpdb;

        // Handle status updates
        if (isset($_POST['report_id']) && isset($_POST['status']) && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'update_report_status_' . $_POST['report_id'])) {
                $wpdb->update(
                    $wpdb->prefix . 'listeo_core_listing_reports',
                    array('report_status' => sanitize_text_field($_POST['status'])),
                    array('id' => absint($_POST['report_id'])),
                    array('%s'),
                    array('%d')
                );

                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Report status updated successfully.', 'listeo_core') . '</p></div>';
            }
        }

        // Handle single report deletion
        if (isset($_POST['report_id']) && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'delete_report_' . $_POST['report_id'])) {
                $deleted = $wpdb->delete(
                    $wpdb->prefix . 'listeo_core_listing_reports',
                    array('id' => absint($_POST['report_id'])),
                    array('%d')
                );

                if ($deleted) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Report deleted successfully.', 'listeo_core') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error deleting report.', 'listeo_core') . '</p></div>';
                }
            }
        }

        // Handle bulk actions
        if (isset($_POST['bulk_action']) && isset($_POST['report_ids']) && is_array($_POST['report_ids']) && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'bulk_update_reports')) {
                $bulk_action = sanitize_text_field($_POST['bulk_action']);
                $report_ids = array_map('absint', $_POST['report_ids']);

                if (!empty($bulk_action) && !empty($report_ids)) {
                    foreach ($report_ids as $report_id) {
                        if ($bulk_action === 'delete') {
                            $wpdb->delete(
                                $wpdb->prefix . 'listeo_core_listing_reports',
                                array('id' => $report_id),
                                array('%d')
                            );
                        } else {
                            $wpdb->update(
                                $wpdb->prefix . 'listeo_core_listing_reports',
                                array('report_status' => $bulk_action),
                                array('id' => $report_id),
                                array('%s'),
                                array('%d')
                            );
                        }
                    }

                    $message = $bulk_action === 'delete' ?
                        esc_html__('Reports deleted successfully.', 'listeo_core') :
                        esc_html__('Report statuses updated successfully.', 'listeo_core');

                    echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
                }
            }
        }

        // Get reports with pagination
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;

        // Apply filters
        $where = "1=1";
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status = sanitize_text_field($_GET['status']);
            $where .= $wpdb->prepare(" AND report_status = %s", $status);
        }

        // Count total reports for pagination
        $total_reports = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}listeo_core_listing_reports
            WHERE $where
        ");

        // Get reports
        $reports = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, l.post_title as listing_title, u.display_name as reporter_name 
            FROM {$wpdb->prefix}listeo_core_listing_reports r
            LEFT JOIN {$wpdb->posts} l ON r.listing_id = l.ID
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE $where
            ORDER BY r.report_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));

        // Get report counts by status
        $status_counts = $wpdb->get_results("
            SELECT report_status, COUNT(*) as count
            FROM {$wpdb->prefix}listeo_core_listing_reports
            GROUP BY report_status
        ", OBJECT_K);

        $pending_count = isset($status_counts['pending']) ? $status_counts['pending']->count : 0;
        $resolved_count = isset($status_counts['resolved']) ? $status_counts['resolved']->count : 0;
        $dismissed_count = isset($status_counts['dismissed']) ? $status_counts['dismissed']->count : 0;
        $total_count = $pending_count + $resolved_count + $dismissed_count;

        // Status links
        $all_link = admin_url('admin.php?page=listeo-reports');
        $pending_link = add_query_arg('status', 'pending', $all_link);
        $resolved_link = add_query_arg('status', 'resolved', $all_link);
        $dismissed_link = add_query_arg('status', 'dismissed', $all_link);

        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Listing Reports', 'listeo_core'); ?></h1>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url($all_link); ?>" <?php echo empty($current_status) ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('All', 'listeo_core'); ?> <span class="count">(<?php echo esc_html($total_count); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url($pending_link); ?>" <?php echo $current_status === 'pending' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Pending', 'listeo_core'); ?> <span class="count">(<?php echo esc_html($pending_count); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url($resolved_link); ?>" <?php echo $current_status === 'resolved' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Resolved', 'listeo_core'); ?> <span class="count">(<?php echo esc_html($resolved_count); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url($dismissed_link); ?>" <?php echo $current_status === 'dismissed' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Dismissed', 'listeo_core'); ?> <span class="count">(<?php echo esc_html($dismissed_count); ?>)</span>
                    </a>
                </li>
            </ul>

            <form method="post">
                <?php wp_nonce_field('bulk_update_reports'); ?>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value=""><?php esc_html_e('Bulk actions', 'listeo_core'); ?></option>
                            <option value="pending"><?php esc_html_e('Mark as pending', 'listeo_core'); ?></option>
                            <option value="resolved"><?php esc_html_e('Mark as resolved', 'listeo_core'); ?></option>
                            <option value="dismissed"><?php esc_html_e('Mark as dismissed', 'listeo_core'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'listeo_core'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'listeo_core'); ?>">
                    </div>

                    <?php
                    // Pagination
                    $total_pages = ceil($total_reports / $per_page);

                    if ($total_pages > 1) {
                        $pagination_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ));

                        if ($pagination_links) {
                            echo '<div class="tablenav-pages">' . $pagination_links . '</div>';
                        }
                    }
                    ?>
                </div>

                <table class="wp-list-table widefat fixed striped reports-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th scope="col" class="manage-column column-listing"><?php esc_html_e('Listing', 'listeo_core'); ?></th>
                            <th scope="col" class="manage-column column-reporter"><?php esc_html_e('Reporter', 'listeo_core'); ?></th>
                            <th scope="col" class="manage-column column-reason"><?php esc_html_e('Reason', 'listeo_core'); ?></th>
                            <th scope="col" class="manage-column column-description"><?php esc_html_e('Description', 'listeo_core'); ?></th>
                            <th scope="col" class="manage-column column-date"><?php esc_html_e('Date', 'listeo_core'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php esc_html_e('Status', 'listeo_core'); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'listeo_core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="8"><?php esc_html_e('No reports found.', 'listeo_core'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="report_ids[]" value="<?php echo esc_attr($report->id); ?>">
                                    </th>
                                    <td class="column-listing">
                                        <?php if ($report->listing_title): ?>
                                            <a href="<?php echo esc_url(get_permalink($report->listing_id)); ?>" target="_blank">
                                                <?php echo esc_html($report->listing_title); ?>
                                            </a>
                                            <div class="row-actions">
                                                <span class="view">
                                                    <a href="<?php echo esc_url(get_permalink($report->listing_id)); ?>" target="_blank"><?php esc_html_e('View Listing', 'listeo_core'); ?></a> |
                                                </span>
                                                <span class="edit">
                                                    <a href="<?php echo esc_url(get_edit_post_link($report->listing_id)); ?>"><?php esc_html_e('Edit Listing', 'listeo_core'); ?></a>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="deleted-listing"><?php esc_html_e('Listing Deleted', 'listeo_core'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-reporter">
                                        <?php if ($report->reporter_name): ?>
                                            <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $report->user_id)); ?>">
                                                <?php echo esc_html($report->reporter_name); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php esc_html_e('Unknown User', 'listeo_core'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-reason">
                                        <?php echo esc_html($this->get_reason_label($report->report_reason)); ?>
                                    </td>
                                    <td class="column-description">
                                        <div class="report-description-text">
                                            <?php echo esc_html($report->report_description); ?>
                                        </div>
                                    </td>
                                    <td class="column-date">
                                        <?php echo mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $report->report_date); ?>
                                    </td>
                                    <td class="column-status">
                                        <span class="report-status status-<?php echo esc_attr($report->report_status); ?>">
                                            <?php echo esc_html($this->get_status_label($report->report_status)); ?>
                                        </span>
                                    </td>
                                    <td class="column-actions">
                                        <form method="post" style="display:inline; margin-bottom:5px;">
                                            <?php wp_nonce_field('update_report_status_' . $report->id); ?>
                                            <input type="hidden" name="report_id" value="<?php echo esc_attr($report->id); ?>">
                                            <select name="status">
                                                <option value="pending" <?php selected($report->report_status, 'pending'); ?>>
                                                    <?php esc_html_e('Pending', 'listeo_core'); ?>
                                                </option>
                                                <option value="resolved" <?php selected($report->report_status, 'resolved'); ?>>
                                                    <?php esc_html_e('Resolved', 'listeo_core'); ?>
                                                </option>
                                                <option value="dismissed" <?php selected($report->report_status, 'dismissed'); ?>>
                                                    <?php esc_html_e('Dismissed', 'listeo_core'); ?>
                                                </option>
                                            </select>
                                            <button type="submit" class="button button-small">
                                                <?php esc_html_e('Update', 'listeo_core'); ?>
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this report? This action cannot be undone.', 'listeo_core'); ?>')">
                                            <?php wp_nonce_field('delete_report_' . $report->id); ?>
                                            <input type="hidden" name="report_id" value="<?php echo esc_attr($report->id); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="button button-small button-link-delete">
                                                <?php esc_html_e('Delete', 'listeo_core'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <style>
            .reports-table .column-listing {
                width: 18%;
            }

            .reports-table .column-reporter {
                width: 12%;
            }

            .reports-table .column-reason {
                width: 12%;
            }

            .reports-table .column-description {
                width: 25%;
            }

            .reports-table .column-date {
                width: 12%;
            }

            .reports-table .column-status {
                width: 10%;
            }

            .reports-table .column-actions {
                width: 11%;
            }

            .report-description-text {
                max-height: 80px;
                overflow-y: auto;
            }

            .report-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }

            .status-pending {
                background-color: #f8f5bb;
                color: #9a8f00;
            }

            .status-resolved {
                background-color: #c6efd3;
                color: #2a6f3b;
            }

            .status-dismissed {
                background-color: #f1f1f1;
                color: #777;
            }

            .deleted-listing {
                color: #a00;
                font-style: italic;
            }
        </style>
    <?php
    }

    /**
     * Get human readable label for report reason
     */
    private function get_reason_label($reason)
    {
        $reasons = array(
            'inappropriate' => __('Inappropriate Content', 'listeo_core'),
            'spam' => __('Spam', 'listeo_core'),
            'fake' => __('Fake or Misleading', 'listeo_core'),
            'wrong_category' => __('Wrong Category', 'listeo_core'),
            'duplicate' => __('Duplicate Listing', 'listeo_core'),
            'other' => __('Other', 'listeo_core')
        );

        return isset($reasons[$reason]) ? $reasons[$reason] : $reason;
    }

    /**
     * Get human readable label for report status
     */
    private function get_status_label($status)
    {
        $statuses = array(
            'pending' => __('Pending', 'listeo_core'),
            'resolved' => __('Resolved', 'listeo_core'),
            'dismissed' => __('Dismissed', 'listeo_core')
        );

        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * Add notification in admin bar for pending reports
     */
    public function admin_bar_notification($wp_admin_bar)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $pending_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}listeo_core_listing_reports 
            WHERE report_status = 'pending'
        ");

        if ($pending_count > 0) {
            $wp_admin_bar->add_node(array(
                'id'    => 'listeo-reports',
                'title' => sprintf(
                    '<span class="ab-icon dashicons dashicons-flag"></span><span class="ab-label">%s</span>',
                    sprintf(_n('%d Report', '%d Reports', $pending_count, 'listeo_core'), $pending_count)
                ),
                'href'  => admin_url('admin.php?page=listeo-reports&status=pending'),
                'meta'  => array(
                    'class' => 'listeo-reports-notification'
                )
            ));
        }
    }

    /**
     * Add styles for admin bar notification
     */
    public function admin_notification_styles()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
    ?>
        <style>
            #wpadminbar .listeo-reports-notification .ab-icon {
                position: relative;
                top: 3px;
            }

         

            #wpadminbar .listeo-reports-notification:hover .ab-label {
                background: #fff;
                color: #f91942;
            }
        </style>
<?php
    }
}
