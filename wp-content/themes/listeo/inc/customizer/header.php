<?php
global $wpdb;

$rev_sliders = array();
// Table name
$table_name = $wpdb->prefix . "revslider_sliders";

// Get sliders
if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
	$sliders = $wpdb->get_results( "SELECT alias, title FROM $table_name" );
} else {
	$sliders = '';
}
$rev_sliders[] = esc_html__("--Select slider--","listeo");
// Iterate over the sliders
if($sliders) {
	foreach($sliders as $key => $item) {
	  $rev_sliders[$item->alias] = $item->title;
	}
} else {
	$rev_sliders = array();
}
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


	listeo_Kirki::add_section( 'general_header', array(
		    'title'          => esc_html__( 'Header','listeo'  ),
		    'description'    => esc_html__( 'Header settings','listeo' ),
		    'priority'       => 10,
		    'capability'     => 'edit_theme_options',
		    'theme_supports' => '', // Rarely needed.
		) );

      

    listeo_Kirki::add_field( 'listeo', array(
        'settings'    => 'listeo_slim_mobile_menu',
        'label'		  => 'Slim mobile menu',
        'description' => esc_html__('Switching it to ON will  enable Slim mobile menu  for all pages', 'listeo' ),
        'section'     => 'general_header',
        'type'        => 'radio',
		'default'     => 'true',
		'priority'    => 10,
		'choices'     => array(
			'true'  => esc_attr__( 'Enable', 'listeo' ),
			'false' => esc_attr__( 'Disable', 'listeo' ),
		),
    ) );    
    listeo_Kirki::add_field( 'listeo', array(
        'settings'    => 'listeo_full_width_header',
        'label'		  => 'Header with Search Form',
        'description' => esc_html__('Switching it to ON will globally enable Header with Search Form for all pages', 'listeo' ),
        'section'     => 'general_header',
        'type'        => 'radio',
		'default'     => 'false',
		'priority'    => 10,
		'choices'     => array(
			'true'  => esc_attr__( 'Enable', 'listeo' ),
			'false' => esc_attr__( 'Disable', 'listeo' ),
		),
    ) );    
    listeo_Kirki::add_field( 'listeo', array(
        'settings'    => 'listeo_sticky_header',
        'label'		  => 'Sticky Header',
        'description' => esc_html__( 'Switching it to ON will globally enable sticky header for all pages', 'listeo' ),
        'section'     => 'general_header',
        'type'        => 'radio',
		'default'     => false,
		'priority'    => 10,
		'choices'     => array(
			true  => esc_attr__( 'Enable', 'listeo' ),
			false => esc_attr__( 'Disable', 'listeo' ),
		),
		'active_callback' => [
			[
				'setting'  => 'listeo_full_width_header',
				'operator' => '!=',
				'value'    => 'true',
			]
		],
    ) );    

    listeo_Kirki::add_field( 'listeo', array(
        'type'        => 'radio',
        'settings'    => 'listeo_cart_display',
        'label'       => esc_html__( 'Display Cart in header', 'listeo' ),
        'section'     => 'general_header',
        'default'     => 0,
		'priority'    => 10,
		'choices'     => array(
			true  => esc_attr__( 'Enable', 'listeo' ),
			false => esc_attr__( 'Disable', 'listeo' ),
		),
    ) );    
    listeo_Kirki::add_field( 'listeo', array(
        'type'        => 'radio',
        'settings'    => 'listeo_my_account_display',
        'label'       => esc_html__( 'Display "My account" button in header', 'listeo' ),
        'section'     => 'general_header',
        'default'     => 0,
		'priority'    => 10,
		'choices'     => array(
			true  => esc_attr__( 'Enable', 'listeo' ),
			false => esc_attr__( 'Disable', 'listeo' ),
		),

    ) );    
    listeo_Kirki::add_field( 'listeo', array(
        'type'        => 'radio',
        'settings'    => 'listeo_submit_display',
        'label'       => esc_html__( 'Display "Add Listing" button in header', 'listeo' ),
        'section'     => 'general_header',
        'default'     => false,
		'priority'    => 10,
		'choices'     => array(
			true  => esc_attr__( 'Enable', 'listeo' ),
			false => esc_attr__( 'Disable', 'listeo' ),
		),
		'active_callback' => [
			[
				'setting'  => 'listeo_full_width_header',
				'operator' => '!=',
			'value'    => 'true',
			]
		],
    ) );
  	listeo_Kirki::add_field( 'listeo', array(
        'type'        => 'radio',
        'settings'    => 'listeo_fw_header',
        'label'       => esc_html__( 'Full width header', 'listeo' ),
        'section'     => 'general_header',
        'default'     => false,
		'priority'    => 10,
		'choices'     => array(
			true  => esc_attr__( 'Enable', 'listeo' ),
			false => esc_attr__( 'Disable', 'listeo' ),
		),
	'active_callback' => [
		[
			'setting'  => 'listeo_full_width_header',
			'operator' => '!=',
			'value'    => 'true',
		]
	],
    ) );

listeo_Kirki::add_field('listeo', array(
	'type'        => 'repeater',
	'label'       => esc_html__('Side menu Social Icons', 'kirki'),
	'description' => esc_html__('This settings applies for Header with Search form and side moving menu or mobile menu', 'listeo'),
	'section'     => 'general_header',
	'priority'    => 10,
	'row_label' => array(
		'type'  => 'text',
		'value' => esc_html__('Icon', 'kirki'),
	),
	'button_label' => esc_html__('"Add new" social icon ', 'kirki'),
	'settings'     => 'listeo_side_social_icons',

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
	),
));


?>