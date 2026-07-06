<?php
listeo_Kirki::add_section( 'footer', array(
    'title'          => esc_html__( 'Footer Options', 'listeo'  ),
    'description'    => esc_html__( 'Footer related options', 'listeo'  ),
    'panel'          => '', // Not typically needed.
    'priority'       => 16,
    'capability'     => 'edit_theme_options',
    'theme_supports' => '', // Rarely needed.
) );

$footer_icons = array();
$footer_brand_icons = array();
$faicons = listeo_fa_icons_list();
foreach ($faicons as $key => $value) {
    $footer_icons['fa fa-' . $key] = $key . ' (Font Awesome)';;
}
$brandicons = listeoBrandIcons();

foreach ($brandicons as $key => $value) {
    // uppercase first letter of key
    $footer_icons['icon-brand-' . $key] = $value;
    $footer_brand_icons[$key] = $value;
}


	listeo_Kirki::add_field( 'listeo', array(
	    'type'        => 'editor',
	    'settings'    => 'pp_copyrights',
	    'label'       => esc_html__( 'Copyrights text', 'listeo' ),
	    'default'     => '&copy; Theme by Purethemes.net. All Rights Reserved.',
	    'section'     => 'footer',
	    'priority'    => 10,
	) );

	listeo_Kirki::add_field( 'listeo', array(
    'type'        => 'select',
    'settings'    => 'pp_footer_widgets',
    'label'       => esc_html__( 'Footer widgets layout', 'listeo' ),
    'description' => esc_html__( 'Total width of footer is 16 columns, here you can decide layout based on columns number for each widget area in footer', 'listeo' ),
    'section'     => 'footer',
    'default'     => '6,3,3',
    'priority'    => 10,
    'choices'     => array(
        '6,6'		=> esc_html__( '6 | 6', 'listeo' ),
        '3,3,3,3' 	=> esc_html__( '3 | 3 | 3 | 3', 'listeo' ),
        '6,3,3'     => esc_html__( '6 | 3 | 3 ', 'listeo' ),
        '5,4,3' 	=> esc_html__( '5 | 4 | 3 ', 'listeo' ),
        '3,6,3' 	=> esc_html__( '3 | 6 | 3', 'listeo' ),
        '3,3,6' 	=> esc_html__( '3 | 3 | 6', 'listeo' ),
        '4,4,4' 	=> esc_html__( '4 | 4 | 4', 'listeo' ),
        '4,8' 		=> esc_html__( '4 | 8', 'listeo' ),
        '8,4,' 		=> esc_html__( '8 | 4', 'listeo' ),
        '12' 		=> esc_html__( '12', 'listeo' ),
       
    ),
	) );


      listeo_Kirki::add_field( 'listeo', array(
        'type'        => 'radio',
        'settings'    => 'listeo_footer_style',
        'label'       => __( 'Footer style', 'listeo' ),
        'section'     => 'footer',
        'default'     => 'light',
        'priority'    => 10,
        'choices'     => array(
            'light'  => esc_attr__( 'Light', 'listeo' ),
            'dark'  => esc_attr__( 'Dark', 'listeo' ),
        ),
    ) );

listeo_Kirki::add_field('listeo', array(
    'type'        => 'repeater',
    'label'       => esc_html__('Social Icons', 'kirki'),
    'section'     => 'footer',
    'priority'    => 10,
    'row_label' => array(
        'type'  => 'text',
        'value' => esc_html__('Icon', 'kirki'),
    ),
    'button_label' => esc_html__('"Add new" social icon ', 'kirki'),
    'settings'     => 'pp_footericons',

    'fields' => array(
        'icons_service' => array(
            'type'        => 'select',
            'label'       => esc_html__('Select Social Site', 'kirki'),
            //'description' => esc_html__( 'This will be the label for your link', 'kirki' ),
            'default'     => '',
            'choices'     => $footer_brand_icons
        ),
        'icons_url'  => array(
            'type'        => 'text',
            'label'       => esc_html__('URL to  page', 'kirki'),
            //'description' => esc_html__( 'This will be the link URL', 'kirki' ),
            'default'     => '',
        ),
    )
));
?>