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


?>
