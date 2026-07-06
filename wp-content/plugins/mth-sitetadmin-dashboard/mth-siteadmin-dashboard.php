<?php
/**
 * Plugin Name: MTH Site Admin Dashboard
 * Description: Adds a Site Admin role and a custom dashboard for property, user, subscription, analytics, and communication management.
 * Version: 1.0.1
 * Author: MTH
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MTH_SiteAdmin_Dashboard {
    const ROLE = 'siteadmin';
    const CAP = 'mth_siteadmin_access';
    const PAGE = 'mth-siteadmin';

    public static function init() {
        add_action('init', array(__CLASS__, 'add_role_and_caps'), 20);
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_bar_menu', array(__CLASS__, 'add_admin_home_button'), 35);
        add_action('admin_init', array(__CLASS__, 'guard_siteadmin_admin_pages'), 1);
        add_action('admin_head', array(__CLASS__, 'admin_styles'));
        add_action('admin_head', array(__CLASS__, 'hide_admin_sidebar_for_siteadmin'), 999);
        add_action('pre_get_posts', array(__CLASS__, 'limit_siteadmin_product_queries'));


        add_filter('login_redirect', array(__CLASS__, 'redirect_siteadmin_after_login'), 999, 3);
        add_filter('woocommerce_login_redirect', array(__CLASS__, 'redirect_siteadmin_after_woocommerce_login'), 999, 2);
        add_action('wp_ajax_nopriv_listeoajaxlogin', array(__CLASS__, 'handle_listeo_siteadmin_ajax_login'), 1);

        add_filter('editable_roles', array(__CLASS__, 'hide_admin_role_from_siteadmin'));
        add_filter('map_meta_cap', array(__CLASS__, 'protect_administrator_accounts'), 10, 4);
        add_filter('user_has_cap', array(__CLASS__, 'allow_siteadmin_scoped_listeo_pages'), 20, 4);
    }

    public static function activate() {
        self::add_role_and_caps();
    }

    private static function dashboard_url() {
        return admin_url('admin.php?page=' . self::PAGE);
    }

    private static function vendor_subscriptions_url() {
        return admin_url('admin.php?page=listeo_core_paid_listings_packages');
    }

    private static function listing_package_assignments_url() {
        return admin_url('admin.php?page=listeo_core_paid_listings_package_editor');
    }

    private static function package_products_url() {
        return admin_url('edit.php?post_type=product&product_type=listing_package');
    }

    private static function is_siteadmin_user($user) {
        if (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        if (!$user instanceof WP_User) {
            return false;
        }

        return in_array(self::ROLE, (array) $user->roles, true);
    }

    public static function add_admin_home_button($wp_admin_bar) {
        if (!is_user_logged_in() || !self::is_siteadmin_user(wp_get_current_user())) {
            return;
        }

        if (!is_object($wp_admin_bar)) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id'    => 'mth-siteadmin-home',
            'title' => 'Admin Home',
            'href'  => self::dashboard_url(),
            'meta'  => array(
                'class' => 'mth-siteadmin-home-button',
            ),
        ));
    }

    public static function redirect_siteadmin_after_login($redirect_to, $requested_redirect_to, $user) {
        if (self::is_siteadmin_user($user)) {
            return self::dashboard_url();
        }

        return $redirect_to;
    }

    public static function redirect_siteadmin_after_woocommerce_login($redirect_to, $user) {
        if (self::is_siteadmin_user($user)) {
            return self::dashboard_url();
        }

        return $redirect_to;
    }

    public static function handle_listeo_siteadmin_ajax_login() {
        if (!isset($_POST['action']) || sanitize_key(wp_unslash($_POST['action'])) !== 'listeoajaxlogin') {
            return;
        }

        if (!get_option('listeo_login_nonce_skip')) {
            $nonce = isset($_POST['login_security']) ? sanitize_text_field(wp_unslash($_POST['login_security'])) : '';
            if (!wp_verify_nonce($nonce, 'listeo-ajax-login-nonce')) {
                return;
            }
        }

        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

        if ($username === '' || $password === '') {
            return;
        }

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user) || !self::is_siteadmin_user($user)) {
            return;
        }

        $remember = !empty($_POST['rememberme']);

        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        do_action('wp_login', $user->user_login, $user);

        wp_send_json(array(
            'loggedin' => true,
            'message'  => esc_html__('Login successful, redirecting...', 'mth-siteadmin'),
            'redirect' => self::dashboard_url(),
        ));
    }

    public static function allow_siteadmin_scoped_listeo_pages($allcaps, $caps, $args, $user) {
        if (empty($args[0]) || !self::is_siteadmin_user($user)) {
            return $allcaps;
        }

        $requested_cap = (string) $args[0];

        if ($requested_cap === 'manage_options' && self::is_allowed_listeo_manage_options_context()) {
            $allcaps['manage_options'] = true;
        }

        if ($requested_cap === 'manage_woocommerce' && self::is_allowed_woocommerce_context()) {
            $allcaps['manage_woocommerce'] = true;
        }

        if (self::cap_request_matches($requested_cap, $caps, self::product_caps()) && self::is_listing_package_product_context()) {
            foreach (self::product_caps() as $cap) {
                $allcaps[$cap] = true;
            }
        }

        if (self::cap_request_matches($requested_cap, $caps, self::shop_order_caps()) && self::is_allowed_woocommerce_context()) {
            foreach (self::shop_order_caps() as $cap) {
                $allcaps[$cap] = true;
            }
        }

        return $allcaps;
    }

    private static function cap_request_matches($requested_cap, $primitive_caps, $allowed_caps) {
        if (in_array($requested_cap, $allowed_caps, true)) {
            return true;
        }

        return (bool) array_intersect((array) $primitive_caps, $allowed_caps);
    }

    private static function is_allowed_listeo_manage_options_context() {
        return self::is_listeo_bookings_context() || self::is_listeo_vendor_subscription_context();
    }

    private static function is_listeo_bookings_context() {
        if (wp_doing_ajax()) {
            $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
            $allowed_actions = array(
                'listeo_get_booking_details',
                'listeo_update_booking_status',
                'listeo_delete_booking',
                'listeo_export_bookings_csv',
            );

            return in_array($action, $allowed_actions, true);
        }

        if (!is_admin() || self::current_admin_file() !== 'admin.php') {
            return false;
        }

        return self::request_key('page') === 'listeo_bookings_manage';
    }

    private static function is_listeo_vendor_subscription_context() {
        if (wp_doing_ajax()) {
            $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
            $allowed_ajax_actions = array(
                'listeo_save_package_listing_types',
            );

            return in_array($action, $allowed_ajax_actions, true);
        }

        if (!is_admin() || self::current_admin_file() !== 'admin.php') {
            return false;
        }

        $page = self::request_key('page');

        $allowed_pages = array(
            'listeo_core_paid_listings_packages',
            'listeo_core_paid_listings_package_editor',
        );

        return in_array($page, $allowed_pages, true);
    }

    private static function is_allowed_woocommerce_context() {
        if (wp_doing_ajax()) {
            return false;
        }

        if (!is_admin() || self::current_admin_file() !== 'admin.php') {
            return false;
        }

        $page = self::request_key('page');
        if ($page === 'wc-orders') {
            return true;
        }

        if ($page !== 'wc-admin') {
            return false;
        }

        $path = self::request_text('path');
        return $path === '/analytics/overview' || strpos($path, '/analytics/') === 0;
    }

    private static function caps() {
        $caps = array(
            'read' => true,
            self::CAP => true,
            'view_admin_dashboard' => true,

            'upload_files' => true,

            'list_users' => true,
            'create_users' => true,
            'edit_users' => true,
            'promote_users' => true,
            'delete_users' => true,

            'view_woocommerce_reports' => true,
        );

        foreach (self::post_type_caps('listing', 'listings') as $cap) {
            $caps[$cap] = true;
        }

        foreach (self::shop_order_caps() as $cap) {
            $caps[$cap] = true;
        }

        return $caps;
    }

    private static function product_caps() {
        return self::post_type_caps('product', 'products');
    }

    private static function shop_order_caps() {
        return array(
            'edit_shop_order',
            'read_shop_order',
            'edit_shop_orders',
            'edit_others_shop_orders',
            'edit_private_shop_orders',
            'edit_published_shop_orders',
            'read_private_shop_orders',
        );
    }

    private static function legacy_caps_to_remove() {
        return array_unique(array_merge(
            array(
                'manage_woocommerce',
                'edit_posts',
                'edit_others_posts',
                'edit_published_posts',
                'edit_private_posts',
                'publish_posts',
                'read_private_posts',
                'delete_posts',
                'delete_others_posts',
                'delete_published_posts',
                'delete_private_posts',
                'manage_categories',
            ),
            self::product_caps(),
            self::post_type_caps('shop_subscription', 'shop_subscriptions'),
            array(
                'delete_shop_order',
                'delete_shop_orders',
                'delete_private_shop_orders',
                'delete_published_shop_orders',
                'delete_others_shop_orders',
                'publish_shop_orders',
            )
        ));
    }

    private static function post_type_caps($singular, $plural) {
        return array(
            "edit_{$singular}",
            "read_{$singular}",
            "delete_{$singular}",
            "edit_{$plural}",
            "edit_others_{$plural}",
            "edit_private_{$plural}",
            "edit_published_{$plural}",
            "publish_{$plural}",
            "read_private_{$plural}",
            "delete_{$plural}",
            "delete_private_{$plural}",
            "delete_published_{$plural}",
            "delete_others_{$plural}",
        );
    }

    public static function add_role_and_caps() {
        if (!get_role(self::ROLE)) {
            add_role(self::ROLE, 'Site Admin', self::caps());
        }

        $role = get_role(self::ROLE);
        if ($role) {
            $secure_caps = self::caps();

            foreach (self::legacy_caps_to_remove() as $cap) {
                if (!isset($secure_caps[$cap])) {
                    $role->remove_cap($cap);
                }
            }

            foreach ($secure_caps as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                }
            }
        }

        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(self::CAP);
        }
    }

    public static function register_menu() {
        add_menu_page(
            'Site Admin Dashboard',
            'Site Admin',
            self::CAP,
            self::PAGE,
            array(__CLASS__, 'render_dashboard'),
            'dashicons-chart-area',
            3
        );
    }

    public static function hide_admin_role_from_siteadmin($roles) {
        if (!self::is_siteadmin_user(wp_get_current_user())) {
            return $roles;
        }

        unset($roles['administrator']);
        unset($roles[self::ROLE]);
        return $roles;
    }

    public static function protect_administrator_accounts($caps, $cap, $user_id, $args) {
        $actor = get_userdata($user_id);
        if (!$actor || !self::is_siteadmin_user($actor)) {
            return $caps;
        }

        if (in_array($cap, array('edit_post', 'delete_post', 'read_post'), true) && !empty($args[0])) {
            $post_id = (int) $args[0];
            if (get_post_type($post_id) === 'product' && !self::is_listing_package_product($post_id)) {
                return array('do_not_allow');
            }
        }

        if (!in_array($cap, array('edit_user', 'delete_user', 'remove_user', 'promote_user'), true)) {
            return $caps;
        }

        if (self::requested_role_is_blocked()) {
            return array('do_not_allow');
        }

        if (empty($args[0])) {
            return $caps;
        }

        $target = get_userdata((int) $args[0]);
        if (!$target) {
            return $caps;
        }

        $target_roles = (array) $target->roles;
        $target_is_admin = in_array('administrator', $target_roles, true);
        $target_is_siteadmin = in_array(self::ROLE, $target_roles, true);

        if ($target_is_admin || ($target_is_siteadmin && ((int) $target->ID !== (int) $user_id || $cap !== 'edit_user'))) {
            return array('do_not_allow');
        }

        if ($cap === 'promote_user' && (int) $target->ID === (int) $user_id) {
            return array('do_not_allow');
        }

        return $caps;
    }

    private static function requested_role_is_blocked() {
        foreach (array('role', 'roles', 'new_role', 'new_role2') as $key) {
            if (!isset($_REQUEST[$key])) {
                continue;
            }

            $roles = (array) wp_unslash($_REQUEST[$key]);
            foreach ($roles as $role) {
                if (in_array(sanitize_key($role), array('administrator', self::ROLE), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function guard_siteadmin_admin_pages() {
        if (!self::is_siteadmin_user(wp_get_current_user()) || wp_doing_ajax()) {
            return;
        }

        if (self::current_admin_file() === 'index.php') {
            wp_safe_redirect(self::dashboard_url());
            exit;
        }

        if (self::is_allowed_siteadmin_admin_request()) {
            return;
        }

        wp_die(
            esc_html__('Site Admin access is limited to the approved dashboard tools.', 'mth-siteadmin'),
            esc_html__('Access denied', 'mth-siteadmin'),
            array('response' => 403)
        );
    }

    private static function is_allowed_siteadmin_admin_request() {
        $admin_file = self::current_admin_file();

        if ($admin_file === 'admin.php') {
            $page = self::request_key('page');

            if (in_array($page, array(self::PAGE, 'listeo_bookings_manage', 'listeo_core_paid_listings_packages', 'listeo_core_paid_listings_package_editor', 'wc-orders'), true)) {
                return true;
            }

            if ($page === 'wc-admin') {
                $path = self::request_text('path');
                return $path === '/analytics/overview' || strpos($path, '/analytics/') === 0;
            }

            return false;
        }

        if ($admin_file === 'edit.php') {
            $post_type = self::request_key('post_type');

            if ($post_type === 'listing') {
                return true;
            }

            return $post_type === 'product' && self::request_is_listing_package_product_filter();
        }

        if ($admin_file === 'post-new.php') {
            return self::request_key('post_type') === 'listing';
        }

        if ($admin_file === 'post.php') {
            $post_id = isset($_REQUEST['post']) ? absint($_REQUEST['post']) : 0;
            if (!$post_id && isset($_POST['post_ID'])) {
                $post_id = absint($_POST['post_ID']);
            }

            if (!$post_id) {
                return false;
            }

            $post_type = get_post_type($post_id);
            return $post_type === 'listing' || self::is_listing_package_product($post_id);
        }

        if ($admin_file === 'user-edit.php') {
            return self::is_allowed_siteadmin_user_edit_request();
        }

        return in_array($admin_file, array(
            'upload.php',
            'media-new.php',
            'media-upload.php',
            'async-upload.php',
            'users.php',
            'user-new.php',
            'profile.php',
        ), true);
    }

    private static function is_allowed_siteadmin_user_edit_request() {
        $target_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;
        if (!$target_id) {
            return false;
        }

        if ((int) $target_id === (int) get_current_user_id()) {
            return true;
        }

        $target = get_userdata($target_id);
        if (!$target) {
            return false;
        }

        $target_roles = (array) $target->roles;
        return !in_array('administrator', $target_roles, true) && !in_array(self::ROLE, $target_roles, true);
    }

    public static function limit_siteadmin_product_queries($query) {
        if (!is_admin() || !$query->is_main_query() || !self::is_siteadmin_user(wp_get_current_user())) {
            return;
        }

        if (self::current_admin_file() !== 'edit.php' || self::request_key('post_type') !== 'product') {
            return;
        }

        $query->set('tax_query', array(
            array(
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => self::allowed_package_product_types(),
            ),
        ));
    }

    private static function is_listing_package_product_context() {
        if (wp_doing_ajax() || !is_admin()) {
            return false;
        }

        $admin_file = self::current_admin_file();

        if ($admin_file === 'edit.php') {
            return self::request_key('post_type') === 'product' && self::request_is_listing_package_product_filter();
        }

        if ($admin_file !== 'post.php') {
            return false;
        }

        $post_id = isset($_REQUEST['post']) ? absint($_REQUEST['post']) : 0;
        if (!$post_id && isset($_POST['post_ID'])) {
            $post_id = absint($_POST['post_ID']);
        }

        return $post_id > 0 && self::is_listing_package_product($post_id);
    }

    private static function is_listing_package_product($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return false;
        }

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product && is_callable(array($product, 'is_type'))) {
                return $product->is_type(self::allowed_package_product_types());
            }
        }

        return has_term(self::allowed_package_product_types(), 'product_type', $post_id);
    }

    private static function request_is_listing_package_product_filter() {
        return in_array(self::request_key('product_type'), self::allowed_package_product_types(), true);
    }

    private static function allowed_package_product_types() {
        return array('listing_package', 'listing_package_subscription');
    }

    private static function current_admin_file() {
        global $pagenow;
        return is_string($pagenow) ? $pagenow : '';
    }

    private static function request_key($key) {
        if (!isset($_GET[$key])) {
            return '';
        }

        return sanitize_key(wp_unslash($_GET[$key]));
    }

    private static function request_text($key) {
        if (!isset($_GET[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_GET[$key]));
    }

    private static function count_posts_total($post_type) {
        if (!post_type_exists($post_type)) {
            return 0;
        }

        $counts = wp_count_posts($post_type);
        $total = 0;

        foreach (get_object_vars($counts) as $status => $count) {
            if (!in_array($status, array('trash', 'auto-draft'), true)) {
                $total += (int) $count;
            }
        }

        return $total;
    }

    private static function count_posts_status($post_type, $status) {
        if (!post_type_exists($post_type)) {
            return 0;
        }

        $counts = wp_count_posts($post_type);
        return isset($counts->{$status}) ? (int) $counts->{$status} : 0;
    }

    private static function bookings_count() {
        global $wpdb;

        $table = $wpdb->prefix . 'bookings_calendar';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists !== $table) {
            return 'N/A';
        }

        $safe_table = str_replace('`', '``', $table);
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$safe_table}`");
    }

    private static function vendor_subscription_count() {
        global $wpdb;

        $table = $wpdb->prefix . 'listeo_core_user_packages';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists !== $table) {
            return 'N/A';
        }

        $safe_table = str_replace('`', '``', $table);
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$safe_table}`");
    }

    private static function stat_card($icon, $label, $value, $note) {
        if (is_numeric($value)) {
            $value = number_format_i18n((int) $value);
        }

        echo '<div class="mth-sa-stat">';
        echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
        echo '<strong>' . esc_html($value) . '</strong>';
        echo '<label>' . esc_html($label) . '</label>';
        echo '<small>' . esc_html($note) . '</small>';
        echo '</div>';
    }

    private static function button($icon, $label, $note, $url) {
        echo '<a class="mth-sa-button" href="' . esc_url($url) . '">';
        echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
        echo '<span><strong>' . esc_html($label) . '</strong><small>' . esc_html($note) . '</small></span>';
        echo '</a>';
    }

    private static function disabled_button($icon, $label, $note) {
        echo '<span class="mth-sa-button is-disabled">';
        echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
        echo '<span><strong>' . esc_html($label) . '</strong><small>' . esc_html($note) . '</small></span>';
        echo '</span>';
    }

    public static function render_dashboard() {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mth-siteadmin'));
        }

        $users = count_users();
        $total_users = isset($users['total_users']) ? (int) $users['total_users'] : 0;

        $listing_url = admin_url('edit.php?post_type=listing');
        $add_listing_url = admin_url('post-new.php?post_type=listing');
        $pending_listing_url = admin_url('edit.php?post_type=listing&post_status=pending');

        echo '<div class="wrap mth-sa-wrap">';
        echo '<div class="mth-sa-hero">';
        echo '<h1>Site Admin Dashboard</h1>';
        echo '<p>Manage analytics, properties, owners, subscriptions, and communication from one place.</p>';
        echo '</div>';

        echo '<div class="mth-sa-stats">';
        self::stat_card('dashicons-admin-users', 'Total Users', $total_users, 'All registered users');
        self::stat_card('dashicons-admin-home', 'Listed Properties', self::count_posts_total('listing'), 'All listing statuses');
        self::stat_card('dashicons-clock', 'Pending Approvals', self::count_posts_status('listing', 'pending'), 'Listings waiting for review');
        self::stat_card('dashicons-calendar-alt', 'Bookings', self::bookings_count(), 'From Listeo bookings table');
        self::stat_card('dashicons-update', 'Vendor Subscriptions', self::vendor_subscription_count(), 'Listeo vendor packages');
        echo '</div>';

        echo '<div class="mth-sa-section">';
        echo '<h2><span class="dashicons dashicons-chart-area"></span> Dashboard & Analytics</h2>';
        echo '<div class="mth-sa-grid">';
        self::button('dashicons-admin-users', 'Total Users', 'View and manage all users', admin_url('users.php'));
        self::button('dashicons-admin-home', 'Listed Properties', 'View all property listings', $listing_url);
        self::button('dashicons-clock', 'Pending Approvals', 'Approve or reject pending listings', $pending_listing_url);
        self::button('dashicons-calendar-alt', 'Bookings', 'View property bookings', admin_url('admin.php?page=listeo_bookings_manage'));
        self::button('dashicons-chart-bar', 'Reports & Charts', 'Open WooCommerce analytics', admin_url('admin.php?page=wc-admin&path=/analytics/overview'));
        self::button('dashicons-update', 'Vendor Subscription Status', 'View vendor package status', self::vendor_subscriptions_url());
        echo '</div></div>';

        echo '<div class="mth-sa-section">';
        echo '<h2><span class="dashicons dashicons-admin-home"></span> Property & Listing Management</h2>';
        echo '<div class="mth-sa-grid">';
        self::button('dashicons-plus-alt2', 'Add Listing', 'Create a new PG or hall listing', $add_listing_url);
        self::button('dashicons-yes-alt', 'Approve Listings', 'Review pending properties', $pending_listing_url);
        self::button('dashicons-edit', 'Edit Listings', 'Update property details', $listing_url);
        self::button('dashicons-trash', 'Remove Listings', 'Move unwanted listings to trash', $listing_url);
        self::button('dashicons-format-image', 'Photos & Images', 'Manage uploaded media', admin_url('upload.php'));
        self::button('dashicons-calendar', 'Availability Dates', 'Manage booking availability', admin_url('admin.php?page=listeo_bookings_manage'));
        echo '</div></div>';

        echo '<div class="mth-sa-section">';
        echo '<h2><span class="dashicons dashicons-groups"></span> User & Owner Management</h2>';
        echo '<div class="mth-sa-grid">';
        self::button('dashicons-groups', 'All Users', 'View users and owners', admin_url('users.php'));
        self::button('dashicons-businessperson', 'Property Owners', 'View vendor / owner accounts', admin_url('users.php?role=seller'));
        self::button('dashicons-yes', 'Approve Owners', 'Review owner registrations', admin_url('users.php?role=seller'));
        self::button('dashicons-hidden', 'Block Owners', 'Change owner status or role', admin_url('users.php?role=seller'));
        self::button('dashicons-no-alt', 'Remove Owners', 'Delete or downgrade owner users', admin_url('users.php?role=seller'));
        echo '</div></div>';

        echo '<div class="mth-sa-section">';
        echo '<h2><span class="dashicons dashicons-money-alt"></span> Subscription Management</h2>';
        echo '<div class="mth-sa-grid">';
        self::button('dashicons-update', 'Vendor Subscription Status', 'Modify vendor package, limits, features and expiry', self::vendor_subscriptions_url());
        self::button('dashicons-admin-tools', 'Listing Package Assignment', 'Change package attached to listings', self::listing_package_assignments_url());
        echo '</div></div>';

        echo '<div class="mth-sa-section">';
        echo '<h2><span class="dashicons dashicons-email-alt2"></span> Communication Management</h2>';
        echo '<div class="mth-sa-grid">';
        self::button('dashicons-admin-users', 'Users & Owners', 'Open users involved in communication', admin_url('users.php'));
        self::button('dashicons-admin-home', 'Related Listings', 'Open listings connected to enquiries', $listing_url);
        self::button('dashicons-calendar-alt', 'Booking Contacts', 'Review booking-related contacts', admin_url('admin.php?page=listeo_bookings_manage'));
        self::disabled_button('dashicons-email', 'Contact Tracking Log', 'Needs a custom contact/event logging table');
        echo '</div></div>';

        echo '</div>';
    }
public static function hide_admin_sidebar_for_siteadmin() {
    if (!is_user_logged_in() || !self::is_siteadmin_user(wp_get_current_user())) {
        return;
    }
    ?>
    <style id="mth-hide-siteadmin-sidebar">
        #adminmenumain,
        #adminmenuwrap,
        #adminmenu,
        #wpadminbar #wp-admin-bar-menu-toggle {
            display: none !important;
        }

        #wpadminbar #wp-admin-bar-mth-siteadmin-home > .ab-item {
            background: #5b45dc !important;
            color: #fff !important;
            font-weight: 700 !important;
            padding: 0 14px !important;
        }

        #wpadminbar #wp-admin-bar-mth-siteadmin-home > .ab-item:hover,
        #wpadminbar #wp-admin-bar-mth-siteadmin-home > .ab-item:focus {
            background: #4b36c5 !important;
            color: #fff !important;
        }

        #wpcontent,
        #wpfooter {
            margin-left: 0 !important;
        }

        body.folded #wpcontent,
        body.folded #wpfooter {
            margin-left: 0 !important;
        }

        body.toplevel_page_mth-siteadmin #wpcontent {
            margin-left: 0 !important;
            padding-left: 0 !important;
        }

        body.toplevel_page_mth-siteadmin #wpbody-content {
            display: flex;
            justify-content: center;
            box-sizing: border-box;
            padding: 24px;
        }

        body.toplevel_page_mth-siteadmin .mth-sa-wrap {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto !important;
            box-sizing: border-box;
        }

        @media screen and (max-width: 782px) {
            #wpcontent {
                padding-left: 0 !important;
            }

            body.toplevel_page_mth-siteadmin #wpbody-content {
                padding: 12px;
            }
        }
    </style>
    <?php
}
    public static function admin_styles() {
        if (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== self::PAGE) {
            return;
        }
        ?>
        <style>
            body.toplevel_page_mth-siteadmin {
                background: #f3f4fb;
                color: #20232b;
                font-family: Inter, Poppins, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            body.toplevel_page_mth-siteadmin #wpcontent,
            body.toplevel_page_mth-siteadmin #wpbody,
            body.toplevel_page_mth-siteadmin #wpbody-content {
                background: #f3f4fb;
            }

            body.toplevel_page_mth-siteadmin .notice,
            body.toplevel_page_mth-siteadmin div.error,
            body.toplevel_page_mth-siteadmin div.updated {
                border-left-color: #5b45dc;
                border-radius: 8px;
                box-shadow: 0 12px 30px rgba(32, 35, 43, .08);
            }

            .mth-sa-wrap {
                max-width: 1280px;
                color: #20232b;
                font-family: Inter, Poppins, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            .mth-sa-hero {
                margin: 20px 0;
                padding: 28px;
                background: linear-gradient(135deg, #4f3bd1 0%, #6b56f2 100%);
                border: 1px solid rgba(255, 255, 255, .42);
                border-radius: 8px;
                box-shadow: 0 22px 48px rgba(91, 69, 220, .24);
            }

            .mth-sa-hero h1 {
                margin: 0 0 8px;
                font-size: 28px;
                line-height: 1.2;
                color: #fff;
                font-weight: 800;
            }

            .mth-sa-hero p {
                margin: 0;
                color: rgba(255, 255, 255, .84);
                font-size: 15px;
                line-height: 1.55;
            }

            .mth-sa-stats,
            .mth-sa-grid {
                display: grid;
                gap: 16px;
            }

            .mth-sa-stats {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                margin: 20px 0;
            }

            .mth-sa-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }

            .mth-sa-stat,
            .mth-sa-section {
                background: #fff;
                border: 1px solid #eceef7;
                border-radius: 8px;
                box-shadow: 0 16px 34px rgba(32, 35, 43, .07);
            }

            .mth-sa-stat {
                padding: 20px;
                min-height: 120px;
            }

            .mth-sa-section {
                margin-top: 20px;
                padding: 22px;
            }

            .mth-sa-section h2 {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0 0 16px;
                font-size: 20px;
                line-height: 1.25;
                color: #20232b;
                font-weight: 800;
            }

            .mth-sa-section h2 .dashicons {
                color: #5b45dc;
            }

            .mth-sa-stat .dashicons {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #5b45dc;
                background: #ece9ff;
                border-radius: 8px;
                font-size: 28px;
                width: 42px;
                height: 42px;
                margin-bottom: 12px;
            }

            .mth-sa-stat strong {
                display: block;
                font-size: 26px;
                line-height: 1.1;
                margin-bottom: 6px;
                color: #20232b;
                font-weight: 800;
            }

            .mth-sa-stat label {
                display: block;
                font-weight: 700;
                margin-bottom: 4px;
                color: #20232b;
            }

            .mth-sa-stat small {
                color: #676b76;
            }

            .mth-sa-button {
                display: flex;
                align-items: center;
                gap: 14px;
                min-height: 82px;
                padding: 16px;
                background: #f7f7ff;
                border: 1px solid #e2defd;
                border-radius: 8px;
                text-decoration: none;
                color: #20232b;
                box-shadow: 0 10px 22px rgba(32, 35, 43, .05);
                transition: border-color .15s ease, background .15s ease, box-shadow .15s ease, transform .15s ease;
            }

            .mth-sa-button:hover {
                background: #fff;
                border-color: #5b45dc;
                color: #20232b;
                box-shadow: 0 18px 34px rgba(91, 69, 220, .16);
                transform: translateY(-1px);
            }

            .mth-sa-section .mth-sa-grid .mth-sa-button:first-child {
                background: linear-gradient(135deg, #503bd0 0%, #7058f4 100%);
                border-color: transparent;
                color: #fff;
            }

            .mth-sa-section .mth-sa-grid .mth-sa-button:first-child:hover {
                color: #fff;
                box-shadow: 0 18px 34px rgba(91, 69, 220, .24);
            }

            .mth-sa-button .dashicons {
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #5b45dc;
                background: #ece9ff;
                border-radius: 8px;
                font-size: 30px;
                width: 44px;
                height: 44px;
            }

            .mth-sa-section .mth-sa-grid .mth-sa-button:first-child .dashicons {
                color: #fff;
                background: rgba(255, 255, 255, .18);
            }

            .mth-sa-button strong {
                display: block;
                font-size: 15px;
                margin-bottom: 4px;
                color: inherit;
                font-weight: 800;
            }

            .mth-sa-button small {
                display: block;
                color: #676b76;
                line-height: 1.35;
            }

            .mth-sa-section .mth-sa-grid .mth-sa-button:first-child small {
                color: rgba(255, 255, 255, .78);
            }

            .mth-sa-button.is-disabled {
                opacity: .65;
                cursor: not-allowed;
            }

            @media screen and (max-width: 782px) {
                .mth-sa-hero,
                .mth-sa-section {
                    padding: 18px;
                }

                .mth-sa-hero h1 {
                    font-size: 24px;
                }

                .mth-sa-stats,
                .mth-sa-grid {
                    gap: 12px;
                }
            }
        </style>
        <?php
    }
}

register_activation_hook(__FILE__, array('MTH_SiteAdmin_Dashboard', 'activate'));
MTH_SiteAdmin_Dashboard::init();
