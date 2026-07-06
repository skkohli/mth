<?php
// dokan functionality
add_action('wp_enqueue_scripts', 'listeo_dokan_child_dequeue_scripts', 30);
add_action('admin_enqueue_scripts', 'listeo_dokan_child_dequeue_scripts', 30);
add_action('dokan_enqueue_scripts', 'listeo_dokan_child_dequeue_scripts', 30);

function listeo_dokan_child_dequeue_scripts()
{
    if (listeo_dokan_should_preserve_assets()) {
        return;
    }

    wp_dequeue_style('dokan-fontawesome');
    wp_dequeue_style('dokan-fontawesome-css');
    wp_dequeue_style('dokan-follow-store');
    wp_dequeue_style('dokan-select2-css');
    wp_dequeue_script('dokan-select2-js');
    wp_dequeue_script('dokan-admin-notice-js');
    wp_dequeue_script('dokan-magnific-popup');
    wp_dequeue_script('dokan-promo-notice-js');
}

function listeo_dokan_should_preserve_assets() {
    if (!function_exists('dokan_is_seller_dashboard')) {
        return false;
    }

    $has_product_id = isset($_GET['product_id']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $is_new_product = isset($_GET['dokan-add-new-product']); // fallback for popup

    if ($has_product_id || $is_new_product) {
        return true;
    }

    if (dokan_is_seller_dashboard()) {
        global $wp;

        if (isset($wp->query_vars['products']) || isset($wp->query_vars['new-product'])) {
            return true;
        }
    }

    return false;
}

/**
 * Ensure Dokan multi-step category assets remain available on vendor product screens.
 *
 * The theme dequeues a number of Dokan assets to keep things lean, but the
 * product category modal introduced in newer Dokan releases relies on the
 * core `product-category-ui` bundle (and WordPress' `wp-hooks` utility).
 * When that script is missing the category picker fails silently.
 */
add_action('wp_enqueue_scripts', 'listeo_dokan_ensure_category_assets', 40);
add_action('dokan_enqueue_scripts', 'listeo_dokan_ensure_category_assets', 40);

function listeo_dokan_ensure_category_assets() {
    if (!function_exists('dokan_is_seller_dashboard')) {
        return;
    }

    global $wp;

    $has_product_context = isset($wp->query_vars['products'])
        || isset($wp->query_vars['new-product'])
        || isset($_GET['product_id']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        || (get_query_var('edit') && is_singular('product'));

    if (!$has_product_context) {
        return;
    }

    // The category picker calls wp.hooks.* helpers, so make sure the script is present.
    if (!wp_script_is('wp-hooks', 'enqueued')) {
        wp_enqueue_script('wp-hooks');
    }

    if (class_exists('WeDevs\\Dokan\\ProductCategory\\Helper')) {
        \WeDevs\Dokan\ProductCategory\Helper::enqueue_and_localize_dokan_multistep_category();
    }
}


add_filter('woocommerce_product_tabs', 'dokan_remove_seller_info_tab', 50);
function dokan_remove_seller_info_tab($array)
{
    unset($array['seller']);
    return $array;
}

//dokan_geolocation_product_dropdown_categories_args
function listeo_exclude_dokan_listing_booking($query_vars)
{
    // ALWAYS exclude 'listeo-booking' category by slug (safeguard)
    $query_vars[] = array(
        'taxonomy' => 'product_cat',
        'field' => 'slug',
        'terms' => array('listeo-booking'),
        'operator' => 'NOT IN'
    );

    // Additionally exclude categories from settings
    $excluded_from_settings = get_option('listeo_dokan_exclude_categories');
    if (is_array($excluded_from_settings) && !empty($excluded_from_settings)) {
        // Filter out empty/invalid values (0, empty strings, null, false)
        $excluded_from_settings = array_filter($excluded_from_settings, function($value) {
            return !empty($value) && is_numeric($value) && $value > 0;
        });

        // Remove duplicates and re-index
        $excluded_from_settings = array_values(array_unique($excluded_from_settings));

        // Only add if we have valid IDs
        if (!empty($excluded_from_settings)) {
            $query_vars[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $excluded_from_settings,
                'operator' => 'NOT IN'
            );
        }
    }

    // Always exclude listing packages and listing package subscriptions from store pages
    $query_vars[] = array(
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => array('listing_package', 'listing_package_subscription'),
        'operator' => 'NOT IN'
    );

    return $query_vars;
}
add_filter('dokan_store_tax_query', 'listeo_exclude_dokan_listing_booking', 10);

add_filter('dokan_load_hamburger_menu', '__return_false');
add_filter('dokan_dashboard_nav_common_link', '__return_false');
add_filter('dokan_force_page_redirect', '__return_false');

add_action('parse_request', function ( $wp ) {
    if ( empty( $_GET['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    if ( function_exists( 'dokan_is_seller_dashboard' ) && ! dokan_is_seller_dashboard() ) {
        return;
    }

    $wp->query_vars['products'] = 1;
    $wp->query_vars['edit']     = 1;
}, -999);

add_filter('request', function ( $vars ) {
    if ( isset( $_GET['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $vars['products'] = 1;
        $vars['edit']     = 1;
    }

    return $vars;
}, PHP_INT_MAX);

add_filter('dokan_dashboard_shortcode_query_vars', function( $vars ) {
    if ( isset( $_GET['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $vars['products'] = 1;
        $vars['edit']     = 1;
    }

    return $vars;
}, PHP_INT_MAX);

add_action('init', function () {
    if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() && isset( $_GET['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        add_filter('dokan_dashboard_shortcode_query_vars', function ($query_vars) {
            $query_vars['products'] = true;
            $query_vars['edit']     = true;
            return $query_vars;
        }, 0);
    }
});


add_filter('dokan_best_selling_products_query', 'listeo_dokan_best_selling_products_query');
add_filter('dokan_all_products_query', 'listeo_dokan_best_selling_products_query');
function listeo_dokan_best_selling_products_query($args)
{
    // Don't filter on the vendor's own dashboard ("My Products" etc.) —
    // vendors must see every product they own, regardless of category or
    // type. The exclusions are for public-facing widgets (Best Selling,
    // store-front product grids) where booking/package products should
    // stay hidden from end customers.
    if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
        return $args;
    }

    // ALWAYS exclude 'listeo-booking' category by slug (safeguard)
    $args['tax_query'][] = array(
        'taxonomy' => 'product_cat',
        'field' => 'slug',
        'terms' => array('listeo-booking'),
        'operator' => 'NOT IN'
    );

    // Additionally exclude categories from settings
    $excluded_from_settings = get_option('listeo_dokan_exclude_categories');
    if (is_array($excluded_from_settings) && !empty($excluded_from_settings)) {
        // Filter out empty/invalid values (0, empty strings, null, false)
        $excluded_from_settings = array_filter($excluded_from_settings, function($value) {
            return !empty($value) && is_numeric($value) && $value > 0;
        });

        // Remove duplicates and re-index
        $excluded_from_settings = array_values(array_unique($excluded_from_settings));

        // Only add if we have valid IDs
        if (!empty($excluded_from_settings)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $excluded_from_settings,
                'operator' => 'NOT IN'
            );
        }
    }

    // Always exclude listing packages and listing package subscriptions
    $args['tax_query'][] = array(
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => array('listing_package', 'listing_package_subscription'),
        'operator' => 'NOT IN'
    );

    return $args;
}

//remove_action('dokan_store_profile_frame_after', 'store_products_orderby');
remove_action('login_init', 'dokan_redirect_to_register');


function listeo_dokan_get_more_products_from_seller($seller_id = 0, $posts_per_page = 6)
{
    global $product, $post;

    if ($seller_id === 0 || 'more_seller_product' === $seller_id) {
        $seller_id = $post->post_author;
    }

    if (!is_int($posts_per_page)) {
        $posts_per_page = apply_filters('dokan_get_more_products_per_page', 6);
    }

    $args = [
        'post_type'      => 'product',
        'posts_per_page' => $posts_per_page,
        'orderby'        => 'rand',
        'post__not_in'   => [$post->ID],
        'author'         => $seller_id,
    ];

    // ALWAYS exclude 'listeo-booking' category by slug (safeguard)
    $args['tax_query'][] = array(
        'taxonomy' => 'product_cat',
        'field' => 'slug',
        'terms' => array('listeo-booking'),
        'operator' => 'NOT IN'
    );

    // Additionally exclude categories from settings
    $excluded_from_settings = get_option('listeo_dokan_exclude_categories');
    if (is_array($excluded_from_settings) && !empty($excluded_from_settings)) {
        // Filter out empty/invalid values (0, empty strings, null, false)
        $excluded_from_settings = array_filter($excluded_from_settings, function($value) {
            return !empty($value) && is_numeric($value) && $value > 0;
        });

        // Remove duplicates and re-index
        $excluded_from_settings = array_values(array_unique($excluded_from_settings));

        // Only add if we have valid IDs
        if (!empty($excluded_from_settings)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $excluded_from_settings,
                'operator' => 'NOT IN'
            );
        }
    }

    // Always exclude listing packages and listing package subscriptions
    $args['tax_query'][] = array(
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => array('listing_package', 'listing_package_subscription'),
        'operator' => 'NOT IN'
    );


    $products = new WP_Query($args);

    if ($products->have_posts()) {
        woocommerce_product_loop_start();

        while ($products->have_posts()) {
            $products->the_post();
            wc_get_template_part('content', 'product');
        }

        woocommerce_product_loop_end();
    } else {
        esc_html_e('No product has been found!', 'dokan-lite');
    }

    wp_reset_postdata();
}

remove_action('woocommerce_product_tabs', 'dokan_set_more_from_seller_tab', 10);
/**
 * Set More products from seller tab
 * On Single Product Page
 *
 * @param array $tabs
 *
 * @since 2.5
 * @return int
 */
function listeo_dokan_set_more_from_seller_tab($tabs)
{
    if (check_more_seller_product_tab()) {
        $tabs['more_seller_product'] = [
            'title'    => __('More Products', 'listeo'),
            'priority' => 99,
            'callback' => 'listeo_dokan_get_more_products_from_seller',
        ];
    }

    return $tabs;
}


add_action('woocommerce_product_tabs', 'listeo_dokan_set_more_from_seller_tab', 10);



function my_categories_widget_register()
{
    unregister_widget('WeDevs\Dokan\Widgets\StoreCategoryMenu');
    register_widget('WeDevs\Dokan\Widgets\StoreCategoryMenu2');
}
add_action('widgets_init', 'my_categories_widget_register');



function listeo_dokan_product_cat_dropdown_args($args){
    $excluded = get_option('listeo_dokan_exclude_categories');
    if(is_array($excluded)){
        $args['exclude'] = $excluded;
    }

    return $args;
}
add_filter('dokan_product_cat_dropdown_args', 'listeo_dokan_product_cat_dropdown_args', 10);
add_filter('dokan_geolocation_product_dropdown_categories_args', 'listeo_dokan_product_cat_dropdown_args', 10);
add_filter('woocommerce_product_categories_widget_dropdown_args', 'listeo_dokan_product_cat_dropdown_args', 10);
add_filter('woocommerce_product_categories_widget_args', 'listeo_dokan_product_cat_dropdown_args', 10);



add_filter(
    'woocommerce_products_widget_query_args',
    function ($query_args) {
        // Set HERE your product category slugs 
        $exluded = get_option('listeo_dokan_exclude_categories');
        if (is_array($exluded)) {
            $query_args['tax_query'] = array(array(
                'taxonomy' => 'product_cat',
                'field'    => 'id',
                'terms'    => $exluded,
                'operator' => 'NOT IN'
            ));
        }
        return $query_args;
    },
    10,
    1
);


add_filter('dokan_category_widget', function ($args) {
    // ID of the category to exclude

    $exluded = get_option('listeo_dokan_exclude_categories');

    if (is_array($exluded)) {
        $args['exclude'] = $exluded;
        return $args;
    }

    return $args;
});

/**
 * Listeo Package-based Dokan Access Control
 */

// Block Dokan dashboard access for users without proper package and show upgrade interface
add_action('template_redirect', 'listeo_dokan_check_dashboard_access', 5);
function listeo_dokan_check_dashboard_access() {
    // Early return if helper function doesn't exist yet
    if (!function_exists('listeo_user_has_dokan_access')) {
        return;
    }

    // Get current user
    $user_id = get_current_user_id();
    if (!$user_id) {
        return; // Not logged in
    }

    // Check if user has Dokan access
    $has_access = listeo_user_has_dokan_access($user_id);

    // Check if on Dokan dashboard (multiple detection methods)
    $is_dokan_dashboard = false;

    // Method 1: Dokan native function
    if (function_exists('dokan_is_seller_dashboard') && dokan_is_seller_dashboard()) {
        $is_dokan_dashboard = true;
    }

    // Method 2: Check URL patterns for Dokan pages
    if (!$is_dokan_dashboard) {
        global $wp;
        $current_url = home_url($wp->request);
        $dokan_url_patterns = array('dashboard', 'store-dashboard', 'products', 'orders', 'withdraw', 'settings', 'analytics');
        foreach ($dokan_url_patterns as $pattern) {
            if (strpos($current_url, $pattern) !== false) {
                $is_dokan_dashboard = true;
                break;
            }
        }
    }

    // Method 3: Check query vars
    if (!$is_dokan_dashboard && (get_query_var('dashboard') || (isset($_GET['path']) && strpos($_GET['path'], 'analytics') !== false))) {
        $is_dokan_dashboard = true;
    }

    // If on Dokan dashboard without access, show upgrade interface
    if ($is_dokan_dashboard && !$has_access) {
        // Remove the upgrade notice (we'll show packages instead)
        remove_action('dokan_dashboard_content_before', 'listeo_dokan_show_upgrade_notice');

        add_action('dokan_dashboard_wrap_start', 'listeo_dokan_show_package_upgrade_interface', 1);
        add_filter('dokan_dashboard_content_before', '__return_false');
        remove_all_actions('dokan_dashboard_content');
    }
}

/**
 * Show package upgrade interface instead of Dokan dashboard
 */
function listeo_dokan_show_package_upgrade_interface() {
    if (function_exists('listeo_user_has_dokan_access') && listeo_user_has_dokan_access(get_current_user_id())) {
        return;
    }

    // Hide the default Dokan dashboard wrapper
    ?>
    <style>
        .dokan-dashboard-wrap { display: none !important; }
    </style>

    <div class="listeo-dokan-package-selector">

        <!-- Notification Banner -->
        <div class="dokan-alert dokan-alert-warning" style="margin-bottom: 30px; background-color: #fcf3cd;">
            <strong><?php _e('Dokan Store Access Required', 'listeo'); ?></strong>
            <p>
                <?php _e('To create and manage your vendor store, you need an active package that includes Dokan store access.', 'listeo'); ?>
            </p>
        </div>

        <?php
            // Get packages with Dokan access
            $dokan_packages = listeo_core_get_packages_for_dokan();

            if (empty($dokan_packages)) {
                // No packages with Dokan access - show all packages with a notice
                $all_packages = get_posts(array(
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_type',
                            'field' => 'slug',
                            'terms' => array('listing_package', 'listing_package_subscription'),
                        ),
                    ),
                    'orderby' => 'menu_order',
                    'order' => 'ASC'
                ));
                $packages = wp_list_pluck($all_packages, 'ID');

                if (empty($packages)) {
                    echo '<div class="dokan-alert dokan-alert-danger">';
                    echo '<strong>' . __('No Packages Available', 'listeo') . '</strong>';
                    echo '<p>' . __('There are currently no listing packages available. Please contact the site administrator.', 'listeo') . '</p>';
                    echo '</div>';
                    return;
                } else {
                    echo '<div class="dokan-alert dokan-alert-info" style="margin-bottom: 20px; background-color: #d1ecf1;">';
                    echo '<strong>' . __('Important Notice', 'listeo') . '</strong>';
                    echo '<p>' . __('None of the available packages currently include Dokan store access. Please contact the administrator to enable Dokan access on a package, or choose a package below and the administrator will enable Dokan access for you.', 'listeo') . '</p>';
                    echo '</div>';
                }
            } else {
                $packages = $dokan_packages;
            }

            $user_packages = listeo_core_user_packages_with_dokan(get_current_user_id());

            // Prepare data for package template
            $data = new stdClass();
            $data->packages = $packages;
            $data->user_packages = $user_packages;
            $data->form = 'dokan_upgrade';
            $data->listing_id = 0;
            $data->step = 1;
            $data->submit_button_text = __('Get Package & Access Store', 'listeo');

            // Load package selection template (reuse from submit listing)
            if (class_exists('Listeo_Core_Template_Loader')) {
                $template_loader = new Listeo_Core_Template_Loader;
                $template_loader->set_template_data($data)->get_template_part('listing-submit-package');
            }
            ?>

    </div>
    <?php

    // Stop any further Dokan content from rendering
    remove_all_actions('dokan_dashboard_content_inside_before');
    remove_all_actions('dokan_dashboard_content_inside_after');
}

// Block vendor registration without proper package
add_filter('dokan_can_post', 'listeo_dokan_check_vendor_capabilities', 10, 2);
function listeo_dokan_check_vendor_capabilities($can_post, $post_type = null) {
    if (($post_type && $post_type !== 'product') || !function_exists('listeo_user_has_dokan_access')) {
        return $can_post;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        return false;
    }

    // Check if user has Dokan access via package
    if (!listeo_user_has_dokan_access($user_id)) {
        return false;
    }

    return $can_post;
}

// Show upgrade notice on Dokan pages for users without access
add_action('dokan_dashboard_content_before', 'listeo_dokan_show_upgrade_notice');
function listeo_dokan_show_upgrade_notice() {
    if (!function_exists('listeo_user_has_dokan_access')) {
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id || listeo_user_has_dokan_access($user_id)) {
        return;
    }

    $upgrade_url = apply_filters('listeo_dokan_upgrade_url', home_url('/pricing'));
    ?>
    <div class="dokan-alert dokan-alert-warning">
        <strong><?php _e('Upgrade Required', 'listeo'); ?></strong>
        <p>
            <?php printf(
                __('Your current package does not include Dokan store functionality. <a href="%s">Upgrade your package</a> to access vendor features.', 'listeo'),
                esc_url($upgrade_url)
            ); ?>
        </p>
    </div>
    <?php
}

// Filter seller capability check
add_filter('dokan_is_user_seller', 'listeo_dokan_filter_seller_check', 10, 2);
function listeo_dokan_filter_seller_check($is_seller, $user_id) {
    if (!function_exists('listeo_user_has_dokan_access')) {
        return $is_seller;
    }

    // If user is marked as seller but doesn't have package access, override
    if ($is_seller && !listeo_user_has_dokan_access($user_id)) {
        return false;
    }

    return $is_seller;
}

/**
 * Handle Dokan upgrade package selection form submission
 */
add_action('template_redirect', 'listeo_dokan_upgrade_package_handler', 10);
function listeo_dokan_upgrade_package_handler() {
    if (!isset($_POST['listeo_core_form']) || $_POST['listeo_core_form'] !== 'dokan_upgrade') {
        return;
    }

    if (!isset($_POST['continue']) || !isset($_POST['package'])) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    $package = sanitize_text_field($_POST['package']);

    // Check if it's a user package or product package
    if (strpos($package, 'user-') === 0) {
        // User already has a package - shouldn't happen for Dokan upgrade
        // But handle it anyway - just redirect to dashboard
        wp_safe_redirect(home_url('/dashboard'));
        exit;
    } else {
        // It's a product package - add to cart and redirect to checkout
        $package_id = absint($package);

        if ($package_id && function_exists('WC')) {
            // Empty cart first to avoid confusion
            WC()->cart->empty_cart();

            // Add package to cart
            WC()->cart->add_to_cart($package_id, 1, '', '', array(
                'dokan_upgrade' => true
            ));

            // Redirect to checkout
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }
}