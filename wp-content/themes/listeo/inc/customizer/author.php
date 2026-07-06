<?php

listeo_Kirki::add_section( 'author_page', array(
    'title'          => esc_html__( 'Author Page', 'listeo'  ),
    'description'    => esc_html__( 'Author page display options', 'listeo'  ),
    'panel'          => '', // Not typically needed.
    'priority'       => 14,
    'capability'     => 'edit_theme_options',
    'theme_supports' => '', // Rarely needed.
) );

// Overall Rating Section
listeo_Kirki::add_field( 'listeo', array(
    'type'        => 'radio',
    'settings'    => 'listeo_author_disable_overall_rating',
    'label'       => esc_html__( 'Overall Rating Section', 'listeo' ),
    'description' => esc_html__( 'Show or hide the overall rating section on author pages', 'listeo' ),
    'section'     => 'author_page',
    'default'     => 'enable',
    'priority'    => 10,
    'choices'     => array(
        'enable'  => esc_attr__( 'Enable', 'listeo' ),
        'disable' => esc_attr__( 'Disable', 'listeo' ),
    ),
) );

// Reviews Section
listeo_Kirki::add_field( 'listeo', array(
    'type'        => 'radio',
    'settings'    => 'listeo_author_disable_reviews',
    'label'       => esc_html__( 'Reviews Section', 'listeo' ),
    'description' => esc_html__( 'Show or hide the reviews section on author pages', 'listeo' ),
    'section'     => 'author_page',
    'default'     => 'enable',
    'priority'    => 15,
    'choices'     => array(
        'enable'  => esc_attr__( 'Enable', 'listeo' ),
        'disable' => esc_attr__( 'Disable', 'listeo' ),
    ),
) );

// Statistics Section - Master Toggle
listeo_Kirki::add_field( 'listeo', array(
    'type'        => 'radio',
    'settings'    => 'listeo_author_disable_statistics',
    'label'       => esc_html__( 'Statistics Section', 'listeo' ),
    'description' => esc_html__( 'Show or hide the entire statistics section on author pages', 'listeo' ),
    'section'     => 'author_page',
    'default'     => 'enable',
    'priority'    => 20,
    'choices'     => array(
        'enable'  => esc_attr__( 'Enable', 'listeo' ),
        'disable' => esc_attr__( 'Disable', 'listeo' ),
    ),
) );

// Individual Statistics Controls
listeo_Kirki::add_field( 'listeo', array(
    'type'        => 'multicheck',
    'settings'    => 'listeo_author_statistics_items',
    'label'       => esc_html__( 'Individual Statistics Items', 'listeo' ),
    'description' => esc_html__( 'Select which individual statistics to display on author pages', 'listeo' ),
    'section'     => 'author_page',
    'default'     => array( 'active_listings', 'reviews', 'rating', 'total_bookings', 'guests_hosted', 'total_views' ),
    'priority'    => 25,
    'choices'     => array(
        'active_listings' => esc_attr__( 'Active Listings', 'listeo' ),
        'reviews'         => esc_attr__( 'Reviews Count', 'listeo' ),
        'rating'          => esc_attr__( 'Rating Score', 'listeo' ),
        'total_bookings'  => esc_attr__( 'Total Bookings', 'listeo' ),
        'guests_hosted'   => esc_attr__( 'Guests Hosted', 'listeo' ),
        'total_views'     => esc_attr__( 'Total Views', 'listeo' ),
    ),
    'active_callback' => array(
        array(
            'setting'  => 'listeo_author_disable_statistics',
            'operator' => '==',
            'value'    => 'enable',
        ),
    ),
) );

// About Section
listeo_Kirki::add_field( 'listeo', array(
    'type'        => 'radio',
    'settings'    => 'listeo_author_disable_about_section',
    'label'       => esc_html__( 'About Me Section', 'listeo' ),
    'description' => esc_html__( 'Show or hide the about me section on author pages', 'listeo' ),
    'section'     => 'author_page',
    'default'     => 'enable',
    'priority'    => 30,
    'choices'     => array(
        'enable'  => esc_attr__( 'Enable', 'listeo' ),
        'disable' => esc_attr__( 'Disable', 'listeo' ),
    ),
) );

// Store Section (Dokan)
listeo_Kirki::add_field( 'listeo', array(
    'type'        => 'radio',
    'settings'    => 'listeo_author_disable_store_section',
    'label'       => esc_html__( 'Store Section', 'listeo' ),
    'description' => esc_html__( 'Show or hide the Dokan store section on author pages (only visible if author has products)', 'listeo' ),
    'section'     => 'author_page',
    'default'     => 'enable',
    'priority'    => 35,
    'choices'     => array(
        'enable'  => esc_attr__( 'Enable', 'listeo' ),
        'disable' => esc_attr__( 'Disable', 'listeo' ),
    ),
) );

?>