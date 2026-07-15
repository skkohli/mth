<?php 
add_action( 'wp_enqueue_scripts', 'listeo_enqueue_styles' );
function listeo_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css',array('bootstrap','font-awesome-5','font-awesome-5-shims','simple-line-icons','listeo-woocommerce') );
}

add_action( 'wp_enqueue_scripts', 'mth_enqueue_child_theme_overrides', 99 );
function mth_enqueue_child_theme_overrides() {
    $dependencies = wp_style_is( 'listeo-style', 'registered' ) || wp_style_is( 'listeo-style', 'enqueued' )
        ? array( 'listeo-style' )
        : array( 'parent-style' );

    wp_enqueue_style(
        'mth-child-theme-overrides',
        get_stylesheet_directory_uri() . '/style.css',
        $dependencies,
        filemtime( get_stylesheet_directory() . '/style.css' )
    );
}


 
function remove_parent_theme_features() {
   	
}
add_action( 'after_setup_theme', 'remove_parent_theme_features', 10 );
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'mth-pg-phone-validation',
        get_stylesheet_directory_uri() . '/js/pg-phone-validation.js',
        array(),
        '1.0.0',
        true
    );
} );

add_filter( 'wp_nav_menu_items', 'mth_add_listing_link_to_mobile_nav', 20, 2 );
function mth_add_listing_link_to_mobile_nav( $items, $args ) {
    if ( empty( $args->menu_id ) || 'mobile-nav' !== $args->menu_id ) {
        return $items;
    }

    if ( false === get_option( 'listeo_submit_display', true ) ) {
        return $items;
    }

    $submit_page = is_user_logged_in()
        ? apply_filters( 'listeo_submit_page', get_option( 'listeo_submit_page' ) )
        : apply_filters( 'listeo_submit_page_anonymous', get_option( 'listeo_submit_page' ) );

    if ( empty( $submit_page ) ) {
        return $items;
    }

    $submit_url = is_numeric( $submit_page ) ? get_permalink( absint( $submit_page ) ) : $submit_page;

    if ( empty( $submit_url ) ) {
        return $items;
    }

    if ( false !== strpos( $items, $submit_url ) || false !== strpos( $items, esc_url( $submit_url ) ) ) {
        return $items;
    }

    $items .= sprintf(
        '<li class="menu-item mth-mobile-add-listing"><a href="%1$s"><i class="sl sl-icon-plus" aria-hidden="true"></i><span>%2$s</span></a></li>',
        esc_url( $submit_url ),
        esc_html__( 'Add Listing', 'listeo_core' )
    );

    return $items;
}


?>
