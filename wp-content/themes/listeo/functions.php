<?php

/**
 * listeo functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package listeo
 */


add_action('admin_notices', 'add_taxonomy_top_notice');
if (!function_exists('listeo_setup')) :
    /**
     * Sets up theme defaults and registers support for various WordPress features.
     *
     * Note that this function is hooked into the after_setup_theme hook, which
     * runs before the init hook. The init hook is too late for some features, such
     * as indicating support for post thumbnails.
     */
    // $date_format = get_option('date_format');
    // echo strtotime( date( $date_format, strtotime('+5 days') ) );
    global $wpdb;

    //temp fix for listing author 
    // $ownerusers = get_users( 'role=owner' );
    // foreach ( $ownerusers as $user ) {
    //    $user->add_cap('level_1');
    // }

    function cc_mime_types($mimes)
    {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
    add_filter('upload_mimes', 'cc_mime_types');

    function listeo_setup()
    {


        load_theme_textdomain('listeo', get_template_directory() . '/languages');

        // Add default posts and comments RSS feed links to head.
        add_theme_support('automatic-feed-links');

        add_theme_support('title-tag');


        add_theme_support('wc-product-gallery-zoom');
        add_theme_support('wc-product-gallery-lightbox');
        add_theme_support('wc-product-gallery-slider');
        /*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
	 */
        add_theme_support('post-thumbnails');
        set_post_thumbnail_size(900, 500, true); //size of thumbs
        add_image_size('listeo-avatar', 590, 590);
        add_image_size('listeo-blog-post', 1200, 670);
        add_image_size('listeo-blog-related-post', 577, 866);
        add_image_size('listeo-post-thumb', 150, 150, true);
        remove_theme_support('widgets-block-editor');
        add_editor_style();
        // This theme uses wp_nav_menu() in one location.
        register_nav_menus(array(
            'primary' => esc_html__('Main Menu', 'listeo'),
            'mobile' => esc_html__('Side/Mobile Menu Widget', 'listeo'),
            'dashboard_main' => esc_html__('Dashboard Main Menu', 'listeo'),
            'dashboard_main_guest' => esc_html__('Dashboard Main Menu Guest only', 'listeo'),
            'dashboard_main_owner' => esc_html__('Dashboard Main Menu Owner only', 'listeo'),
            'dashboard_listings' => esc_html__('Dashboard Listings Menu', 'listeo'),
            'dashboard_account' => esc_html__('Dashboard Account Menu', 'listeo'),
            'dashboard_dokan' => esc_html__('Dashboard Dokan Menu', 'listeo'),
            'dashboard_top_menu' => esc_html__('Dashboard Top Menu', 'listeo'),
        ));

        do_action('purethemes-testimonials');
        /*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
        add_theme_support('html5', array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
        ));

        // Set up the WordPress core custom background feature.
        add_theme_support('custom-background', apply_filters('listeo_custom_background_args', array(
            'default-color' => 'ffffff',
            'default-image' => '',
        )));

        // Add theme support for selective refresh for widgets.
        add_theme_support('customize-selective-refresh-widgets');

        add_theme_support('woocommerce');
    }
endif;
add_action('after_setup_theme', 'listeo_setup');


/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function listeo_content_width()
{
    $GLOBALS['content_width'] = apply_filters('listeo_content_width', 760);
}
add_action('after_setup_theme', 'listeo_content_width', 0);

/**
 * Register widget area.
 */
function listeo_widgets_init()
{
    register_sidebar(array(
        'name'          => esc_html__('Sidebar', 'listeo'),
        'id'            => 'sidebar-1',
        'description'   => esc_html__('Add widgets here.', 'listeo'),
        'before_widget' => '<section id="%1$s" class="widget  margin-top-40 %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    register_sidebar(array(
        'name'          => esc_html__('Shop page sidebar', 'listeo', 'listeo'),
        'id'            => 'sidebar-shop',
        'description'   => '',
        'before_widget' => '<section id="%1$s" class="widget  margin-top-40 %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    register_sidebar(array(
        'id' => 'footer1',
        'name' => esc_html__('Footer 1st Column', 'listeo'),
        'description' => esc_html__('1st column for widgets in Footer', 'listeo'),
        'before_widget' => '<aside id="%1$s" class="footer-widget widget %2$s">',
        'after_widget' => '</aside>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ));
    register_sidebar(array(
        'id' => 'footer2',
        'name' => esc_html__('Footer 2nd Column', 'listeo'),
        'description' => esc_html__('2nd column for widgets in Footer', 'listeo'),
        'before_widget' => '<aside id="%1$s" class="footer-widget widget %2$s">',
        'after_widget' => '</aside>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ));
    register_sidebar(array(
        'id' => 'footer3',
        'name' => esc_html__('Footer 3rd Column', 'listeo'),
        'description' => esc_html__('3rd column for widgets in Footer', 'listeo'),
        'before_widget' => '<aside id="%1$s" class="footer-widget widget %2$s">',
        'after_widget' => '</aside>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ));
    register_sidebar(array(
        'id' => 'footer4',
        'name' => esc_html__('Footer 4th Column', 'listeo'),
        'description' => esc_html__('4th column for widgets in Footer', 'listeo'),
        'before_widget' => '<aside id="%1$s" class="footer-widget widget %2$s">',
        'after_widget' => '</aside>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ));

    register_sidebar(array(
        'id' => 'mobilemenu',
        'name' => esc_html__('Mobile Menu widget', 'listeo'),
        'description' => esc_html__('Mobilel Menu area', 'listeo'),
        'before_widget' => '<aside id="%1$s" class="mobile-menu-widget widget %2$s">',
        'after_widget' => '</aside>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ));

    if (get_option('pp_listeo_sidebar')) :

        $pp_sidebars = get_option('pp_listeo_sidebar');
        if (!empty($pp_sidebars)) :
            foreach ($pp_sidebars as $pp_sidebar) {

                register_sidebar(array(
                    'name' => esc_html($pp_sidebar["sidebar_name"]),
                    'id' => esc_attr($pp_sidebar["sidebar_id"]),
                    'before_widget' => '<section id="%1$s" class="widget %2$s">',
                    'after_widget'  => '</section>',
                    'before_title'  => '<h3 class="widget-title">',
                    'after_title'   => '</h3>',
                ));
            }
        endif;
    endif;
}
add_action('widgets_init', 'listeo_widgets_init');


add_action('after_switch_theme', 'listeo_setup_options');

function listeo_setup_options()
{
    $activation_date = get_option('listeo_activation_date');
    if (!$activation_date) {
        update_option('listeo_activation_date', time());
    }
}

// add_action('wp_ajax_dynamic_css', 'dynamic_css');
// add_action('wp_ajax_nopriv_dynamic_css', 'dynamic_css');
// function dynamic_css()
// {
// 	require(get_template_directory() . '/css/custom.css.php');
// 	exit;
// }

/**
 * Enqueue scripts and styles.
 */
function listeo_scripts()
{

    $my_theme = wp_get_theme();
    //$ver_num = $my_theme->get( 'Version' );
    $ver_num = '1.9.54';

    wp_register_style('bootstrap', get_template_directory_uri() . '/css/bootstrap-grid.css');
    wp_register_style('listeo-woocommerce', get_template_directory_uri() . '/css/woocommerce.min.css');
    wp_register_style('listeo-iconsmind', get_template_directory_uri() . '/css/icons.css');
    wp_register_style('listeo-dokan', get_template_directory_uri() . '/css/dokan.css');
    wp_register_style('simple-line-icons', get_template_directory_uri() . '/css/simple-line-icons.css');
    wp_register_style('font-awesome-5', get_template_directory_uri() . '/css/all.css');
    wp_register_style('font-awesome-5-shims', get_template_directory_uri() . '/css/v4-shims.min.css');
    wp_enqueue_style('listeo-style', get_stylesheet_uri(), array('bootstrap', 'font-awesome-5', 'font-awesome-5-shims', 'simple-line-icons', 'listeo-woocommerce'), $ver_num);

    // RTL
    wp_register_style('listeo-rtl', get_template_directory_uri() . '/rtl.css');
    if (is_rtl()) {
        wp_enqueue_style('listeo-rtl');
    }
    if (get_option('listeo_iconsmind') != 'hide') {
        wp_enqueue_style('listeo-iconsmind');
    }
    if (function_exists('dokan_get_sellers')) {
        wp_enqueue_style('listeo-dokan');
    }
    wp_register_style('listeo-dark', get_template_directory_uri() . '/css/dark-mode.css');
    if (get_option('listeo_dark_mode') == 'enable') {

        wp_enqueue_style('listeo-dark', get_template_directory_uri() . '/css/dark-mode.css', array('listeo-style'));
    }
    //wp_register_script( 'chosen-min', get_template_directory_uri() . '/js/chosen.min.js', array( 'jquery' ), $ver_num );
    wp_register_script('select2-min', get_template_directory_uri() . '/js/select2.min.js', array('jquery'), $ver_num);
    wp_register_script('bootstrap-select-min', get_template_directory_uri() . '/js/bootstrap-select.min.js', array('jquery'), $ver_num);
    wp_register_script('counterup-min', get_template_directory_uri() . '/js/counterup.min.js', array('jquery'), $ver_num);
    wp_register_script('jquery-scrollto', get_template_directory_uri() . '/js/jquery.scrollto.js', array('jquery'), $ver_num);
    wp_register_script('datedropper', get_template_directory_uri() . '/js/datedropper.js', array('jquery'), $ver_num);
    wp_register_script('dropzone', get_template_directory_uri() . '/js/dropzone.js', array('jquery'), $ver_num);

    wp_register_script('isotope-min', get_template_directory_uri() . '/js/isotope.min.js', array('jquery'), $ver_num);
    wp_register_script('jquery-counterdown-min', get_template_directory_uri() . '/js/jquery.countdown.min.js', array('jquery'), $ver_num);
    wp_register_script('magnific-popup-min', get_template_directory_uri() . '/js/magnific-popup.min.js', array('jquery'), $ver_num);


    wp_register_script('quantityButtons', get_template_directory_uri() . '/js/quantityButtons.js', array('jquery'), $ver_num);
    wp_register_script('rangeslider-min', get_template_directory_uri() . '/js/rangeslider.min.js', array('jquery'), $ver_num);
    wp_register_script('timedropper', get_template_directory_uri() . '/js/timedropper.js', array('jquery'), $ver_num);
    wp_register_script('tooltips-min', get_template_directory_uri() . '/js/tooltips.min.js', array('jquery'), $ver_num);
    wp_register_script('waypoints-min', get_template_directory_uri() . '/js/waypoints.min.js', array('jquery'), $ver_num);
    wp_register_script('slick-min', get_template_directory_uri() . '/js/slick.min.js', array('jquery'), $ver_num);

    wp_register_script('moment', get_template_directory_uri() . '/js/moment.min.js', array('jquery'), $ver_num);
    wp_register_script('daterangerpicker', get_template_directory_uri() . '/js/daterangepicker.js', array('jquery', 'moment'), $ver_num);
    wp_register_script('flatpickr', get_template_directory_uri() . '/js/flatpickr.js', array('jquery'), $ver_num);
    wp_register_script('bootstrap-slider-min', get_template_directory_uri() . '/js/bootstrap-slider.min.js', array('jquery'), $ver_num);

    //wp_enqueue_script( 'chosen-min' );
    wp_enqueue_script('select2-min');
    // enqueue script https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js

    wp_enqueue_script('bootstrap-select-min');
    wp_enqueue_script('counterup-min');
    wp_enqueue_script('datedropper');
    wp_enqueue_script('dropzone');


    if (is_page_template('template-comming-soon.php')) {
        wp_enqueue_script('jquery-counterdown-min');
    }
    wp_enqueue_script('magnific-popup-min');




    wp_enqueue_script('slick-min');
    wp_enqueue_script('quantityButtons');
    wp_enqueue_script('rangeslider-min');
    wp_enqueue_script('timedropper');
    wp_enqueue_script('jquery-scrollto');
    wp_enqueue_script('tooltips-min');
    wp_enqueue_script('waypoints-min');
    wp_enqueue_script('moment');
    wp_enqueue_script('daterangerpicker');
    wp_enqueue_script('bootstrap-slider-min');
    wp_enqueue_script('flatpickr');
    wp_enqueue_script('listeo-custom', get_template_directory_uri() . '/js/custom.js', array('jquery'), '20220821', true);


    $open_sans_args = array(
        'family' => 'Open+Sans:500,600,700', // Change this font to whatever font you'd like
        'display' => 'swap'
    );
    wp_register_style('google-fonts-open-sans', add_query_arg($open_sans_args, "//fonts.googleapis.com/css"), array(), null);

    $raleway_args = array(
        'family' => 'Raleway:300,400,500,600,700', // Change this font to whatever font you'd like
        'display' => 'swap'
    );
    wp_register_style('google-fonts-raleway', add_query_arg($raleway_args, "//fonts.googleapis.com/css"), array(), null);

    wp_enqueue_style('google-fonts-raleway');
    wp_enqueue_style('google-fonts-open-sans');




    $convertedData = listeo_date_time_wp_format();

    // add converented format date to javascript
    wp_localize_script('listeo-custom', 'wordpress_date_format', $convertedData);


    $ajax_url = admin_url('admin-ajax.php', 'relative');
    wp_localize_script(
        'listeo-custom',
        'listeo',
        array(
            'ajaxurl'                 => $ajax_url,
            'theme_url'                => get_template_directory_uri(),
            "menu_back"             => esc_html__("Back", 'listeo'),
            'mobile_map_collapsible'  => get_option('listeo_mobile_map_collapsible', 'collapsible'),
            'mobile_map_show_text'    => esc_html__('Show Map ', 'listeo'),
            'mobile_map_hide_text'    => esc_html__('Hide Map ', 'listeo'),
            'coupon_nonce'            => wp_create_nonce('listeo_booking_coupon_nonce'),
        )
    );

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }

    // Add inline script to prevent flash of unstyled content for mobile map
    $mobile_map_setting = get_option('listeo_mobile_map_collapsible', 'collapsible');
    if ($mobile_map_setting === 'collapsible') {
        wp_add_inline_script('listeo-custom', '
            if (window.innerWidth <= 1008) {
                document.body.className += " mobile-map-collapsible-loading";
                document.addEventListener("DOMContentLoaded", function() {
                    setTimeout(function() {
                        document.body.classList.remove("mobile-map-collapsible-loading");
                    }, 100);
                });
            }
        ', 'before');
    }

    //wp_enqueue_style('dynamic-css', admin_url('admin-ajax.php') . '?action=dynamic_css', array('listeo-style'));
}
add_action('wp_enqueue_scripts', 'listeo_scripts');


add_action('admin_enqueue_scripts', 'listeo_admin_scripts');
function listeo_admin_scripts($hook)
{

    // Load for taxonomy pages, post pages, and specific admin pages
    $load_scripts = false;

    // Load for all taxonomy pages (edit-tags.php covers all taxonomies, term.php covers individual term edit)
    if ($hook == 'edit-tags.php' || $hook == 'term.php') {
        // Check if we're on a listing taxonomy page
        $screen = get_current_screen();
        if ($screen && isset($screen->taxonomy)) {
            $listing_taxonomies = get_object_taxonomies('listing');
            if (in_array($screen->taxonomy, $listing_taxonomies)) {
                $load_scripts = true;
            }
        }
    }

    // Load for post pages and specific admin pages
    if ($hook == 'post.php' || $hook == 'toplevel_page_listeo_settings' || $hook == 'toplevel_page_listeo_addons' || $hook == 'listeo-core_page_listeo_settings' || $hook == 'listeo-core_page_listeo_license' || $hook == 'listeo-fields-and-form_page_listeo-listing-types') {
        $load_scripts = true;
    }

    // Also load for listeo-listing-types page (catch-all for different possible hooks)
    if (isset($_GET['page']) && $_GET['page'] == 'listeo-listing-types') {
        $load_scripts = true;
    }

    if ($load_scripts) {
        wp_enqueue_style('listeo-admin', get_template_directory_uri() . '/css/admin.css');
        wp_enqueue_style('listeo-icons', get_template_directory_uri() . '/css/all.css');
        wp_enqueue_style('listeo-icons-fav4', get_template_directory_uri() . '/css/fav4-shims.min.css');
        wp_enqueue_style('listeo-iconsmind', get_template_directory_uri() . '/css/icons.css');
        wp_enqueue_script('listeo-icon-selector', get_template_directory_uri() . '/js/iconselector.min.js', array('jquery'), '20180323', true);
    }
}


function listeo_add_editor_styles()
{
    add_editor_style('custom-editor-style.css');
}
add_action('admin_init', 'listeo_add_editor_styles');

/**
 * Load aq_resizer.
 */
require get_template_directory() . '/inc/aq_resize.php';


/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require get_template_directory() . '/inc/extras.php';

/**
 * Custom meta-boxes
 */
require get_template_directory() . '/inc/meta-boxes.php';

/*
 * Load the Kirki Fallback class
 */
require get_template_directory() . '/inc/kirki-fallback.php';


/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
require get_template_directory() . '/inc/jetpack.php';

function listeo_add_apple_google_pay()
{
    return array(
        'simple',
        'variable',
        'variation',
        'subscription',
        'variable-subscription',
        'subscription_variation',
        'listing_booking',
        'listing_package_subscription',
        'booking',
        'bundle',
        'composite'
    );
}
add_filter('wc_stripe_payment_request_supported_types', 'listeo_add_apple_google_pay');
/**
 * Load TGMPA file.
 */
require get_template_directory() . '/inc/tgmpa.php';



/**
 * Load big map.
 */
require get_template_directory() . '/inc/properties-maps.php';

/**
 * Load woocommerce 
 */
require get_template_directory() . '/inc/woocommerce.php';
/**
 * Load megamenu 
 */
require get_template_directory() . '/inc/megamenu.php';
if (function_exists('dokan_get_sellers')) {
    require get_template_directory() . '/inc/dokan.php';
    require get_template_directory() . '/inc/dokan-widget.php';
    //   require get_template_directory() . '/dokan-debug.php';
}


/**
 * Setup Wizard
 */
require get_template_directory() . '/envato_setup/envato_setup.php';

// Enable shortcodes in text widgets
add_filter('widget_text', 'do_shortcode');

function listeo_disable_admin_bar()
{
    if (current_user_can('administrator')) {
        // user can view admin bar
        show_admin_bar(true); // this line isn't essentially needed by default...
    } else {
        // hide admin bar
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'listeo_disable_admin_bar');


function listeo_new_customer_data($new_customer_data)
{
    $new_customer_data['role'] = 'owner';
    return $new_customer_data;
}
add_filter('woocommerce_new_customer_data', 'listeo_new_customer_data');



function listeo_noindex_for_products()
{
    if (is_singular('product')) {
        global $post;
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            //listing_booking, listing_package_subscription, listing_package
            if ($product->is_type('listing_booking') || $product->is_type('listing_package_subscription') || $product->is_type('listing_package')) {
                echo '<meta name="robots" content="noindex, follow">';
            }
        }
    }
}

add_action('wp_head', 'listeo_noindex_for_products');

/**
 * Add preconnect resource hints for Google Fonts
 * Improves performance by establishing early connections to external domains
 */
function listeo_google_fonts_preconnect()
{
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php
}
add_action('wp_head', 'listeo_google_fonts_preconnect', 1);

// ,make filter listeo_core_delete_expired_listings  __return_true
//add_filter('listeo_core_delete_expired_listings', '__return_true');



function listeo_register_elementor_locations($elementor_theme_manager)
{

    $elementor_theme_manager->register_location('header');
    $elementor_theme_manager->register_location('footer');
}
add_action('elementor/theme/register_locations', 'listeo_register_elementor_locations');


if (!class_exists("b472b0Base")) {
    require_once get_template_directory() . '/inc/b472b0Base.php';
}
require get_template_directory() . '/inc/licenser.php';
require_once get_template_directory() . '/inc/class-listeo-theme-updater.php';



add_filter('woocommerce_get_star_rating_html', 'replace_star_ratings', 10, 3);
function replace_star_ratings($html, $rating, $count)
{
    $html = '<span style="width:' . (($rating / 5) * 100) . '%">';

    if (0 < $count) {
        /* translators: 1: rating 2: rating count */
        $html .= sprintf(_n('%1$s based on %2$s rating', 'Rated %1$s based on %2$s ratings', $count, 'listeo'), '<strong class="rating">' . esc_html($rating) . '</strong>', '<span class="rating">' . esc_html($count) . '</span>');
    } else {
        /* translators: %s: rating */
        $html .= sprintf(esc_html__('%s out of 5', 'listeo'), '<strong class="rating">' . esc_html($rating) . '</strong>');
    }

    $html .= '</span>';
    return $html;
}


/**
 * Automatically set order status to Completed for listing_booking products.
 *
 * @param int $order_id The order ID.
 */
function set_order_status_to_completed_for_listing_booking($order_id)
{
    // Get the order object
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    // Check if the order contains any listing_booking products
    $has_listing_booking = false;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        if ($product && 'listing_booking' === $product->get_type()) {
            $has_listing_booking = true;
            break; // Exit the loop if a listing_booking product is found
        }
    }

    // Set order status to Completed if it contains listing_booking products
    if ($has_listing_booking) {
        $order->update_status('completed');
    }
}

add_filter('nav_menu_item_title', function ($title, $item, $args, $depth) {
    // Only modify certain menus if needed
    if (!in_array($args->theme_location, array('dashboard_main', 'dashboard_main_guest', 'dashboard_main_owner', 'dashboard_listings', 'dashboard_dokan', 'dashboard_account'))) {
        return $title;
    }

    // Get icons (adjust meta key as needed)

    $icon = get_post_meta($item->ID, '_menu-item-icon', true);
    if (!empty($icon)) {
        if (substr($icon, 0, 2) == 'ln') {
            $icon = '<i class="' . esc_attr($icon) . '"></i> ';
        } else {
            $icon = '<i class=" sl sl-icon-' . esc_attr($icon) . '"></i> ';
        }
        return $icon . $title;
    }

    return $title;
}, 10, 4);

// Hook this function to the 'woocommerce_thankyou' action
//add_action('woocommerce_thankyou', 'set_order_status_to_completed_for_listing_booking');

// add_filter('submit_listing_form_wp_editor_args', 'customize_editor_toolbar');
// function customize_editor_toolbar($args)
// {
// 	$args['tinymce']['toolbar1'] = 'styleselect, bold,italic,alignleft,aligncenter,alignright,|,bullist,numlist,|,undo,redo';
// 	return $args;
// }

// add_filter('listeo_core_service_timepicker_value', 'default_time');
// function default_time($time)
// {
// 	//return current time	
// 	return date('H:i');

// }

// use filter listeo_submit_page to change url of add listing button
// add_filter('listeo_submit_page', 'listeo_submit_page_change');
// function listeo_submit_page_change($submit_page)
// {
// 	$submit_page = 99; // where 99 is the page id you want to use	
// 	return $submit_page;
// }




function listeo_admin_custom_currency_change($currency_symbol)
{
    if (get_option('listeo_currency_custom')) {
        return get_option('listeo_currency_custom');
    } else {
        return $currency_symbol;
    }
}
add_filter('listeo_core_currency_symbol', 'listeo_admin_custom_currency_change');


//add_filter('woocommerce_available_payment_gateways', 'remove_dokan_payment_gateways_for_booking_products');
function remove_dokan_payment_gateways_for_booking_products($available_gateways)
{
    if (get_option('disable_dokan_stripe_payment_on_boookings')) {


        // Check if we are on the "pay for order" page
        if (is_checkout_pay_page()) {
            // Get the current order
            $order_id = absint(get_query_var('order-pay'));
            $order = wc_get_order($order_id);

            // Check if the order contains any products
            if ($order && count($order->get_items()) > 0) {
                foreach ($order->get_items() as $item) {
                    // Check if the product is of type "booking"
                    $product = $item->get_product();
                    if ($product && $product->is_type('listing_booking')) {
                        // Remove the Cash on Delivery payment gateway
                        unset($available_gateways['dokan_stripe_express']);
                        unset($available_gateways['dokan-stripe-connect']);
                        break; // No need to continue checking if we found a booking product
                    }
                }
            }
        }
    }

    return $available_gateways;
}

add_action('listeo/single-listing/after_content', 'listeo_single_listing_after_content');
function listeo_single_listing_after_content()
{
    global $post;
    $listing_id = $post->ID;
    //get custom field id soundcloud
    $soundcloud = get_post_meta($listing_id, 'soundcloud', true);
    if ($soundcloud) {
        echo '<div class="soundcloud-embed">';
        echo do_shortcode('[soundcloud url="' . $soundcloud . '"]');
        echo '</div>';
    }
}



add_filter('get_terms', 'ts_get_subcategory_terms', 10, 3);
function ts_get_subcategory_terms($terms, $taxonomies, $args)
{
    $new_terms = array();
    // if it is a product category and on the shop page
    if (!is_array($taxonomies)) {
        return $terms;
    }
    if (in_array('product_cat', $taxonomies) && !is_admin() && is_shop()) {
        foreach ($terms as $key => $term) {
            if (!in_array($term->slug, array('listeo-booking'))) { //pass the slug name here
                $new_terms[] = $term;
            }
        }
        $terms = $new_terms;
    }
    return $terms;
}




// get wpbd prefix

//add action for listeo_archive_split_before_title that displays the h2 title based on searched query, for exampl if location is Miami and keyword is "car" then the title will be "Car Rentals in Miami"
//add_action('listeo_archive_split_before_title', 'listeo_archive_split_before_title');
function listeo_archive_split_before_title()
{
    //get search query

    //get location
    $location = get_query_var('location_search');
    $keyword = get_query_var('keyword_search');
    //get category
    $category = get_query_var('tax-listing_category');
    $region = get_query_var('tax-region');
    if (is_array($category)) {
        $category = end($category);
        // get category name
        $category = get_term_by('slug', $category, 'listing_category');
    } else {
        $category = get_term_by('slug', $category, 'listing_category');
    }
    if (is_array($region)) {
        $region = end($region);

        // get region name
        $region = get_term_by('slug', $region, 'region');
    } else {
        $region = get_term_by('slug', $region, 'region');
    }

    // display title (Car Rentals in Miami) based on search query, check if location or region or category are set, there could be multiple combinations
    $title = '';
    if ($keyword) {
        $title .= $keyword;
    }
    if ($category) {
        $title .= ' in ' . $category->name;
    }
    if ($location) {
        $title .= ' in ' . $location;
    }
    if ($region) {
        $title .= ' in ' . $region->name;
    }
    echo '<h2>' . $title . '</h2>';
}


function add_taxonomy_top_notice()
{
    $screen = get_current_screen();

    // Replace 'your_taxonomy' with your taxonomy name
    if (in_array($screen->taxonomy, array('event_category', 'service_category', 'rental_category'))) {
        // For both add new and edit screens
        echo '<div class="listeo-admin-test-api" style="margin: 10px 0;"><p>';
        echo 'Check how categories and listing type taxonomies can be used in Listeo. <a href="https://docs.purethemes.net/listeo/knowledge-base/how-main-and-sub-categories-work/">Learn more</a>';
        echo '</p></div>';
    }
    if (in_array($screen->taxonomy, array('listing_feature'))) {
        // For both add new and edit screens
        echo '<div class="listeo-admin-test-api" style="margin: 10px 0;"><p>';
        echo 'Check how categories and features can be used in Listeo. <a href=https://docs.purethemes.net/listeo/knowledge-base/categories-and-features/">Learn more</a>';
        echo '</p></div>';
    }
}

remove_action('woocommerce_order_item_meta_start', 'dokan_attach_vendor_name', 10, 2);

add_filter('listeo/listings-list-data-tags', 'listeo_add_listing_tags', 10, 2);
function listeo_add_listing_tags($search_data)
{
    $_listing_type = get_query_var('_listing_type');
    if ($_listing_type) {
        $search_data .= ' data-_listing-type="' . esc_attr($_listing_type) . '" ';
    }

    return $search_data;
}

add_filter('body_class', 'add_booking_dashboard_body_class');
function add_booking_dashboard_body_class($classes)
{
    if (is_page_template('template-dashboard.php')) {
        $classes[] = 'bookings-page-dashboard';
    }
    return $classes;
}

/**
 * Fix Dokan React dashboard routing inside Listeo's dashboard template.
 *
 * Why: Dokan 4.x rewrites any menu item with a `react_route` to
 * `/store-dashboard/new/#<route>`, which only works when its React SPA boots.
 * Inside Listeo's template-dashboard.php that route renders an empty
 * "Loading..." page (Products, Orders, Withdraw, Reverse Withdrawal, etc.).
 *
 * Stripping `react_route` keeps Dokan's original legacy URL
 * (e.g. `/store-dashboard/products/`) which Listeo renders correctly.
 */
add_filter('dokan_get_dashboard_nav', 'listeo_fix_dokan_react_routing', 20);
function listeo_fix_dokan_react_routing($nav_menus)
{
    if (!is_array($nav_menus)) {
        return $nav_menus;
    }

    foreach ($nav_menus as $key => $item) {
        if (is_array($item) && isset($item['react_route'])) {
            unset($nav_menus[$key]['react_route']);
        }
        if (is_array($item) && !empty($item['submenu']) && is_array($item['submenu'])) {
            foreach ($item['submenu'] as $sub_key => $sub_item) {
                if (is_array($sub_item) && isset($sub_item['react_route'])) {
                    unset($nav_menus[$key]['submenu'][$sub_key]['react_route']);
                }
            }
        }
    }

    // Preserve the existing withdraw query-param override that Listeo relies on.
    if (isset($nav_menus['withdraw'])) {
        $dashboard_page_id = function_exists('dokan_get_option') ? dokan_get_option('dashboard', 'dokan_pages', 0) : 0;
        if ($dashboard_page_id) {
            $nav_menus['withdraw']['url'] = add_query_arg('withdraw', '1', get_permalink($dashboard_page_id));
        }
    }

    return $nav_menus;
}

/**
 * Display admin notice to upgrade from old Listeo AI Search to new AI & Chat Search plugin
 */
add_action('admin_notices', 'listeo_ai_search_upgrade_notice');
function listeo_ai_search_upgrade_notice()
{
    // Check if old plugin is installed
    $old_plugin_path = 'listeo-ai-search/listeo-ai-search.php';

    // Check if the old plugin file exists and is active
    if (file_exists(WP_PLUGIN_DIR . '/' . $old_plugin_path) && is_plugin_active($old_plugin_path)) {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if notice has been dismissed
        if (get_option('listeo_ai_search_upgrade_notice_dismissed')) {
            return;
        }

        $download_url = 'https://purethemes.net/ai-chat-search.zip';
    ?>
        <div class="notice notice-warning is-dismissible listeo-ai-upgrade-notice" data-notice="listeo_ai_search_upgrade">
            <h3 style="margin-top: 10px;">⚠️ <?php _e('Listeo AI Search Plugin Update Required', 'listeo'); ?></h3>
            <p>
                <?php _e('The <strong>Listeo AI Search</strong> plugin is discontinued and has been replaced with a new version: <strong>AI & Chat Search</strong>.', 'listeo'); ?>
            </p>
            <p>
                <?php _e('Please follow these steps to upgrade:', 'listeo'); ?>
            </p>
            <ol>
                <li><strong>⚠️ <?php _e('IMPORTANT: Deactivate the old "Listeo AI Search" plugin first', 'listeo'); ?></strong></li>
                <li><strong><?php _e('Download the new plugin from:', 'listeo'); ?></strong> <a href="<?php echo esc_url($download_url); ?>" target="_blank"><strong><?php echo esc_html($download_url); ?></strong></a></li>
                <li><?php _e('Install and activate the new "AI & Chat Search" plugin', 'listeo'); ?></li>
                <li><?php _e('All your settings will remain intact!', 'listeo'); ?></li>
            </ol>
            <p>
                <a href="<?php echo esc_url($download_url); ?>" class="button button-primary" target="_blank">
                    <?php _e('Download AI & Chat Search', 'listeo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button">
                    <?php _e('Go to Plugins', 'listeo'); ?>
                </a>
            </p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('.listeo-ai-upgrade-notice').on('click', '.notice-dismiss', function() {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'listeo_dismiss_ai_upgrade_notice'
                        }
                    });
                });
            });
        </script>
    <?php
    }
}

?>
