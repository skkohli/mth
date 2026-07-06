<?php
/**
 * This file represents an example of the code that themes would use to register
 * the required plugins.
 *
 * It is expected that theme authors would copy and paste this code into their
 * functions.php file, and amend to suit.
 *
 * @see http://tgmpluginactivation.com/configuration/ for detailed documentation.
 *
 * @package    TGM-Plugin-Activation
 * @subpackage Example
 * @version    2.6.1 for parent theme listeo for publication on WordPress.org
 * @author     Thomas Griffin, Gary Jones, Juliette Reinders Folmer
 * @copyright  Copyright (c) 2011, Thomas Griffin
 * @license    http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 * @link       https://github.com/TGMPA/TGM-Plugin-Activation
 */

/**
 * Include the TGM_Plugin_Activation class.
 *
 * Depending on your implementation, you may want to change the include call:
 *
 * Parent Theme:
 * require_once get_template_directory() . '/path/to/class-tgm-plugin-activation.php';
 *
 * Child Theme:
 * require_once get_stylesheet_directory() . '/path/to/class-tgm-plugin-activation.php';
 *
 * Plugin:
 * require_once dirname( __FILE__ ) . '/path/to/class-tgm-plugin-activation.php';
 */
require_once get_template_directory() . '/inc/class-tgm-plugin-activation.php';

add_action( 'tgmpa_register', 'listeo_register_required_plugins' );

/**
 * Register the required plugins for this theme.
 *
 * In this example, we register five plugins:
 * - one included with the TGMPA library
 * - two from an external source, one from an arbitrary source, one from a GitHub repository
 * - two from the .org repo, where one demonstrates the use of the `is_callable` argument
 *
 * The variables passed to the `tgmpa()` function should be:
 * - an array of plugin arrays;
 * - optionally a configuration array.
 * If you are not changing anything in the configuration array, you can remove the array and remove the
 * variable from the function call: `tgmpa( $plugins );`.
 * In that case, the TGMPA default settings will be used.
 *
 * This function is hooked into `tgmpa_register`, which is fired on the WP `init` action on priority 10.
 */
function listeo_register_required_plugins() {
	/*
	 * Array of plugin arrays. Required keys are name and slug.
	 * If the source is NOT from the .org repo, then source is also required.
	 */
	$plugins = array(


	    array(
	        'name'                  => 'Listeo Core',
	        'slug'                  => 'listeo-core',
	        'source'                => get_template_directory() . '/plugins/listeo-core.zip',
	        'version'               => '2.0.44',
	        'required'              => true,
	    ),
	    array(
	        'name'                  => 'Listeo Shortcodes',
	        'slug'                  => 'listeo-shortcodes',
	        'source'                => get_template_directory() . '/plugins/listeo-shortcodes.zip',
	        'version'               => '1.5.22',
	        'required'              => true,
	    ),

	     array(
	        'name'                  => 'Listeo Forms and Fields Editor',
	        'slug'                  => 'listeo-forms-and-fields-editor',
	        'source'                => get_template_directory() . '/plugins/listeo-forms-and-fields-editor.zip',
	        'version'               => '2.0.31',
	        'required'              => true,
	    ),
	    array(
	        'name'                  => 'Purethemes CPT',
	        'slug'                  => 'purethemes-cpt',
	        'source'                => get_template_directory() . '/plugins/purethemes-cpt.zip',
	        'version'               => '1.3',
	        'required'              => true,
	    ),
	    array(
	        'name'                  => 'PurioChat',
	        'slug'                  => 'ai-chat-search',
	        'source'                => 'https://purethemes.net/license/plugins/ai-chat-search.zip',
	        'required'              => false,
	        'recommended'           => true,
	    ),
	    array(
	        'name'                  => 'Listeo Data Importer',
	        'slug'                  => 'listeo-data-scraper',
	        'source'                => 'https://purethemes.net/license/plugins/listeo-data-scraper.zip',
	        'version'               => '3.0.1',
	    ),

		// array for purecustomizer plugin
		 array(
	        'name'                  => 'PureCustomizer',
	        'slug'                  => 'pure-customizer',
	        'source'                => get_template_directory() . '/plugins/pure-customizer.zip',
	        'required'              => false,
	        'recommended'           => true,
			'version'               => '1.0.0',
	    ),
		// array(
		// 	'name'      			=> 'Kirki',
		// 	'slug'      			=> 'kirki',
		// 	'required'  			=> true,
		// ),			
		array(
			'name'      			=> 'CMB2',
			'slug'      			=> 'cmb2',
			'required'  			=> true,
		),		
		array(
			'name'      			=> 'CMB2 Field Slider',
			'slug'      			=> 'cmb2-field-slider',
			'source'    			=> get_template_directory() . '/plugins/cmb2-field-slider.zip',
			'required'  			=> true,
		),	
	array(
			'name'      			=> 'Breadcrumb NavXT',
			'slug'      			=> 'breadcrumb-navxt',
			'required'  			=> true,
		),	
		array(
			'name'      			=> 'WooCommerce',
			'slug'      			=> 'woocommerce',
			'required'  			=> true,
		),
		array(
			'name'      			=> 'Autocomplete WooCommerce Orders',
			'slug'      			=> 'autocomplete-woocommerce-orders',
			'required'  			=> true,
		),
    	array(
			'name'      			=> esc_html__('Contact Form 7','listeo' ),
			'slug'      			=> 'contact-form-7',
			'required'  			=> false,
		),
		array(
			'name'      			=> esc_html__('Contact Form 7 - Dynamic Text Extension','listeo' ),
			'slug'      			=> 'contact-form-7-dynamic-text-extension',
			'required'  			=> false,
		),	
		 array(
			'name'      			=> 'Elementor',
			'slug'      			=> 'elementor',
			'required'  			=> true,
		
		 ), array(
	        'name'                  => 'Listeo Elementor',
	        'slug'                  => 'listeo-elementor',
	        'source'                => get_template_directory() . '/plugins/listeo-elementor.zip',
	        'version'               => '2.0.28',
	        'required'              => true,
	    )




	);



	/*
	 * Array of configuration settings. Amend each line as needed.
	 *
	 * TGMPA will start providing localized text strings soon. If you already have translations of our standard
	 * strings available, please help us make TGMPA even better by giving us access to these translations or by
	 * sending in a pull-request with .po file(s) with the translations.
	 *
	 * Only uncomment the strings in the config array if you want to customize the strings.
	 */
	$config = array(
		'id'           => 'listeo',                 // Unique ID for hashing notices for multiple instances of TGMPA.
		'default_path' => '',                      // Default absolute path to bundled plugins.
		'menu'         => 'tgmpa-install-plugins', // Menu slug.
		'has_notices'  => true,                    // Show admin notices or not.
		'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
		'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
		'is_automatic' => false,                   // Automatically activate plugins after installation or not.
		'message'      => '',                      // Message to output right before the plugins table.

	
	);

	tgmpa( $plugins, $config );
}
