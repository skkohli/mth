<?php 

	add_action( 'cmb2_admin_init', 'listeo_register_metabox_testimonial' );
	/**
	 * Hook in and add a demo metabox. Can only happen on the 'cmb2_admin_init' or 'cmb2_init' hook.
	 */
	function listeo_register_metabox_testimonial() {
		$prefix = 'listeo_';
		$listeo_testimonials_mb = new_cmb2_box( array(
			'id'            => $prefix . 'testimonial',
			'title'         => esc_html__( 'Additional Informations', 'listeo' ),
			'object_types'  => array( 'testimonial', ), // Post type
			'priority'   => 'high',
		) );
		$listeo_testimonials_mb->add_field( array(
			'name' => esc_html__( 'Company', 'listeo' ),
			'id'   => $prefix . 'pp_company',
			'type' => 'text_medium',
			
		) );	

	}	

	add_action( 'cmb2_admin_init', 'listeo_register_metabox_page_search' );
	/**
	 * Hook in and add a demo metabox. Can only happen on the 'cmb2_admin_init' or 'cmb2_init' hook.
	 */
	function listeo_register_metabox_page_search() {

		$searchforms = array();
		if(function_exists('listeo_get_search_forms_dropdown')){
			
		
			$prefix = 'listeo_page_search_';
			$listeo_page_search_mb = new_cmb2_box( array(
				'id'            => $prefix . '_fullwidth',
				'title'         => esc_html__( 'Search Form Selection', 'listeo' ),
				'object_types'  => array( 'page', ), // Post type
				'show_on'      => array( 
				'key' => 'page-template',
				'value' => 'template-listings-map.php',
				),
				'priority'   => 'low',
			) );
			$search_forms_full = listeo_get_search_forms_dropdown('fullwidth');
			$listeo_page_search_mb->add_field( array(
				'name' => esc_html__( 'Search Form for Page Template', 'listeo' ),
				'id'   => $prefix . 'search_form',
				'type' => 'select',
				'options' => $search_forms_full,
			) );
			$listeo_page_search_split_mb = new_cmb2_box( array(
				'id'            => $prefix . '_split',
				'title'         => esc_html__( 'Search Form Selection', 'listeo' ),
				'object_types'  => array( 'page', ), // Post type
				'show_on'      => array( 
				'key' => 'page-template',
				'value' => 'template-split-map.php',
				) ,
				'priority'   => 'low',
			) );
			$search_forms_split = listeo_get_search_forms_dropdown('split');
		$listeo_page_search_split_mb->add_field( array(
				'name' => esc_html__( 'Search Form Split Map Template', 'listeo' ),
				'id'   => $prefix . 'search_form',
				'type' => 'select',
				'options' => $search_forms_split,
			) );
		}
	}


	add_action( 'cmb2_admin_init', 'listeo_register_metabox_commingsoon' );
	/**
	 * Hook in and add a demo metabox. Can only happen on the 'cmb2_admin_init' or 'cmb2_init' hook.
	 */
	function listeo_register_metabox_commingsoon() {
			$prefix = 'listeo_comming_soon_';

		$listeo_comming_soon_cmb = new_cmb2_box( array(
			'id'           => 'comming-soon',
			'title'        => esc_html__('Comming Soon Page Options','listeo'),
			'object_types' => array( 'page' ), // post type
			'show_on'      => array( 'key' => 'page-template', 'value' => 'template-comming-soon.php' ),
			'context'      => 'normal', //  'normal', 'advanced', or 'side'
			'priority'     => 'high',  //  'high', 'core', 'default' or 'low'
			'show_names'   => true, // Show field names on the left
		) );

		$listeo_comming_soon_cmb->add_field( array(
			'name' => esc_html__( 'Background image', 'listeo' ),
			'desc' => esc_html__( 'Set background image for this page', 'listeo' ),
			'id'   => $prefix . 'bg_image',
			'type' => 'file',
		) );
		$listeo_comming_soon_cmb->add_field( array(
			'name' => esc_html__( 'Title', 'listeo' ),
			'desc' => esc_html__( 'Title on the page', 'listeo' ),
			'default' => 'We are launching Listeo soon!',
			'id'   => $prefix . 'title',
			'type' => 'text',
		) );
		$listeo_comming_soon_cmb->add_field( array(
			'name' => esc_html__( 'Date to count to', 'listeo' ),
			'desc' => esc_html__( 'For instructions check documentaiton', 'listeo' ),
			'id'   => $prefix . 'signup_countdown_date',
			'type' => 'text_date',
			// 'timezone_meta_key' => 'wiki_test_timezone',
			'date_format' => 'Y/n/d',
		) );
		$listeo_comming_soon_cmb->add_field( array(
			'name' => esc_html__( 'Sign up form acton', 'listeo' ),
			'desc' => esc_html__( 'For instructions check documentaiton', 'listeo' ),
			'id'   => $prefix . 'signup_form_action',
			'type' => 'text',
		) );		
		$listeo_comming_soon_cmb->add_field( array(
			'name' => esc_html__( 'Sign up hidden input id', 'listeo' ),
			'desc' => esc_html__( 'For instructions check documentaiton', 'listeo' ),
			'id'   => $prefix . 'signup_hidden_id',
			'type' => 'text',
		) );		
		
	}


	add_action( 'cmb2_admin_init', 'listeo_register_metabox_product' );
	function listeo_register_metabox_product() {
		$prefix = 'listeo_';
		
		/* get the registered sidebars */
	    global $wp_registered_sidebars;

	    $sidebars = array();
	    foreach( $wp_registered_sidebars as $id=>$sidebar ) {
	      $sidebars[ $id ] = $sidebar[ 'name' ];
	    }

		/**
		 * Sample metabox to demonstrate each field type included
		 */
		$listeo_product_mb = new_cmb2_box( array(
			'id'            => $prefix . 'product_sb_metabox',
			'title'         => esc_html__( 'Listeo Package Details', 'listeo' ),
			'object_types'  => array( 'product' ), // Post type
			'priority'   => 'high',
		) );

		$product_group_id = $listeo_product_mb->add_field(array(
			'id'          => 'package_items_group',
			'type'        => 'group',
			'repeatable'  => true,
			'options'     => array(
				'group_title'   => 'Item {#}',
				'add_button'    => 'Add Another Item do this list',
				'remove_button' => 'Remove Item',
				'closed'        => true,  // Repeater fields closed by default - neat & compact.
				'sortable'      => true,  // Allow changing the order of repeated groups.
			),
		));
		
		$listeo_product_mb->add_group_field($product_group_id, array(
			'name' => 'List item title',
			'desc' => 'Enter the item title.',
			'id'   => 'title',
			'type' => 'text',
		));
	
	}


	add_action( 'cmb2_admin_init', 'listeo_register_metabox_pages' );
	/**
	 * Hook in and add a demo metabox. Can only happen on the 'cmb2_admin_init' or 'cmb2_init' hook.
	 */
	function listeo_register_metabox_pages() {
		$prefix = 'listeo_';

		
		/* get the registered sidebars */
	    global $wp_registered_sidebars;

	    $sidebars = array();
	    foreach( $wp_registered_sidebars as $id=>$sidebar ) {
	      $sidebars[ $id ] = $sidebar[ 'name' ];
	    }

	$listeo_post_mb = new_cmb2_box( array(
			'id'            => $prefix . 'post_metabox',
			'title'         => esc_html__( 'Post Options', 'listeo' ),
			'object_types'  => array( 'post' ), // Post type
			'priority'   => 'high',
		) );

$listeo_post_mb->add_field( array(
			'name'             => esc_html__( 'Post Layout', 'listeo' ),
			'desc'             => esc_html__( 'Select post layout, default is full-width', 'listeo' ),
			'id'               => $prefix . 'page_layout',
			'type'             => 'radio_inline',
			'default'			=> 'right-width',
			'options'          => array(
				'full-width' 		=> esc_html__( 'Full width', 'listeo' ),
				'left-sidebar'   	=> esc_html__( 'Left Sidebar', 'listeo' ),
				'right-sidebar'     => esc_html__( 'Right Sidebar', 'listeo' ),
			),
		) );
		/**
		 * Sample metabox to demonstrate each field type included
		 */
		$listeo_page_mb = new_cmb2_box( array(
			'id'            => $prefix . 'page_metabox',
			'title'         => esc_html__( 'Page Options', 'listeo' ),
			'object_types'  => array( 'page' ), // Post type
			'priority'   => 'high',
		) );

		$listeo_page_mb->add_field( array(
			'name'             => esc_html__( 'Page Top Section', 'listeo' ),
			'desc'             => esc_html__( 'Select page layout, default is full-width', 'listeo' ),
			'id'               => $prefix . 'page_top',
			'type'             => 'select',
			'default'			=> 'titlebar',
			'options'          => array(
				'titlebar' 	=> esc_html__( 'Regular Titlebar', 'listeo' ),
				'parallax'  => esc_html__( 'Parallax image background', 'listeo' ),
				'off'     	=> esc_html__( 'Disable top section', 'listeo' ),
			),
		) );

		$listeo_page_mb->add_field( array(
			'name'             => esc_html__( 'Regular Titlebar style', 'listeo' ),
			'desc'             => esc_html__( 'Select titlebar style', 'listeo' ),
			'id'               => $prefix . 'page_top_regular_style',
			'type'             => 'select',
			'default'			=> 'titlebar',
			'options'          => array(
				'solid' 	=> esc_html__( 'Solid gray color', 'listeo' ),
				'gradient'  => esc_html__( 'Gray gradient', 'listeo' ),
				
			),
		) );


		$listeo_page_mb->add_field(array(
			'name' => esc_html__('Transparent header', 'listeo'),
			'desc' => esc_html__('Use with Elementor home search widgets', 'listeo'),
			'id'   => $prefix . 'transparent_header',
			'type' => 'checkbox', //#303133

		));
		

		$listeo_page_mb->add_field(array(
			'name' => esc_html__('Disable header shadow', 'listeo'),
			'desc' => esc_html__('Use with Elementor home search widgets', 'listeo'),
			'id'   => $prefix . 'header_shadow',
			'type' => 'checkbox', //#303133

		));
		
	
	
		$listeo_page_mb->add_field( array(
			'name' => esc_html__( '"Glue" footer to content', 'listeo' ),
			'desc' => esc_html__( 'Removes the top margin from footer section,', 'listeo' ),
			'id'   => $prefix . 'glued_footer',
			'type' => 'checkbox', //#303133
			
		) );
	
		
		$listeo_page_mb->add_field( array(
			'name'             => esc_html__( 'Footer color style', 'listeo' ),
			'desc'             => esc_html__( 'Sets footer color style, ignoring global settings', 'listeo' ),
			'id'               => $prefix . 'footer_style',
			'type'             => 'select',
			'default'			=> 'light',
			'options'          => array(
				'use_global' 	=> esc_html__( 'Use Global setting from Customizer', 'listeo' ),
				'light' 	=> esc_html__( 'Light', 'listeo' ),
				'dark'  	=> esc_html__( 'Dark', 'listeo' ),
			),
		) );

		$listeo_page_mb->add_field( array(
			'name' => esc_html__('Header with Search Form', 'listeo' ),
			'desc' => esc_html__( 'Enables header with search form for this page, even if it disabled in global settings', 'listeo' ),
			'id'   => $prefix . 'full_width_header',
			'type' => 'select',
		    'default' => 'use_global',
		    'options'     => array(
				'use_global' 	=> esc_html__( 'Use Global setting from Customizer', 'listeo' ),
				'disable' 		=> esc_html__( 'Disable', 'listeo' ),
				'enable'     	=> esc_html__( 'Enable, always', 'listeo' ),
			),
		) );
		$listeo_page_mb->add_field( array(
			'name' => esc_html__( 'Sticky header', 'listeo' ),
			'desc' => esc_html__( 'Enables sticky header for this page, even if it disabled in global settings', 'listeo' ),
			'id'   => $prefix . 'sticky_header',
			'type' => 'select',
		    'default' => 'use_global',
		    'options'     => array(
				'use_global' 	=> esc_html__( 'Use Global setting from Customizer', 'listeo' ),
				'disable' 		=> esc_html__( 'Disable', 'listeo' ),
				'enable'     	=> esc_html__( 'Enable, always', 'listeo' ),
			),
		) );

		// $listeo_page_mb->add_field( array(
		// 	'name' => esc_html__( 'Full-width header', 'listeo' ),
		// 	'desc' => esc_html__( 'Enables full-width header for this page, even if it disabled in global settings', 'listeo' ),
		// 	'type' => 'select',
		//     'default' => 'use_global',
		//     'options'     => array(
		// 		'use_global' 	=> esc_html__( 'Use Global setting from Customizer', 'listeo' ),
		// 		'disable' 		=> esc_html__( 'Disable', 'listeo' ),
		// 		'enable'     	=> esc_html__( 'Enable, always', 'listeo' ),
		// 	),
		// 	//'default' => get_option('listeo_header_layout'),
		// ) );



		global $wpdb;


		/*parallax*/
		$listeo_page_mb->add_field( array(
			'name' => esc_html__( 'Background for header', 'listeo' ),
			'desc' => esc_html__( 'If added, titlebar will use parallax effect', 'listeo' ),
			'id'   => $prefix . 'parallax_image',
			'type' => 'file',
			
		) );
		$listeo_page_mb->add_field( array(
			'name' => esc_html__( 'Overlay color', 'listeo' ),
			'desc' => esc_html__( 'For Parallax or Titlebar section', 'listeo' ),
			'id'   => $prefix . 'parallax_color',
			'type' => 'colorpicker', //
			'default' => '#303133'
			
		) );		


		$listeo_page_mb->add_field( array(
			    'name' 		=> esc_html__( 'Parallax overlay opacity', 'listeo' ),
			    'desc'        => esc_html__( 'Set your value', 'listeo' ),
			    'id'          => $prefix . 'parallax_opacity',
			    'type'        => 'own_slider',
			    'min'         => '0',
			    'max'         => '1',
			    'step'        => '0.01',
			    'default'     => '0.6', // start value
			    'value_label' => 'Opacity Value:',
		) );
/* eof parallax*/

/* video */ 

		

		$listeo_page_mb->add_field( array(
			'name'             => esc_html__( 'Page Layout', 'listeo' ),
			'desc'             => esc_html__( 'Select page layout, default is full-width', 'listeo' ),
			'id'               => $prefix . 'page_layout',
			'type'             => 'radio_inline',
			'default'			=> 'full-width',
			'options'          => array(
				'full-width' 		=> esc_html__( 'Full width', 'listeo' ),
				'left-sidebar'   	=> esc_html__( 'Left Sidebar', 'listeo' ),
				'right-sidebar'     => esc_html__( 'Right Sidebar', 'listeo' ),
			),
		) );


		$listeo_page_mb->add_field( array(
			'name' => esc_html__( 'Subtitle', 'listeo' ),
			'desc' => esc_html__( 'If added, displayed under page title (if applicable)', 'listeo' ),
			'id'   => $prefix . 'subtitle',
			'type' => 'text',
		) );

		


		$listeo_page_mb->add_field( array( 
			'name'    => esc_html__( 'Selected Sidebar', 'listeo' ),
			'id'      => $prefix . 'sidebar_select',
			'type'    => 'select',
			'default' => 'sidebar-1',
			'options' => $sidebars,
		) );
	}



?>