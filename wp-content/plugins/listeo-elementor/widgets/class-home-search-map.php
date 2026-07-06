<?php

/**
 * Awesomesauce class.
 *
 * @category   Class
 * @package    ElementorAwesomesauce
 * @subpackage WordPress
 * @author     Ben Marshall <me@benmarshall.me>
 * @copyright  2020 Ben Marshall
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @link       link(https://www.benmarshall.me/build-custom-elementor-widgets/,
 *             Build Custom Elementor Widgets)
 * @since      1.0.0
 * php version 7.3.9
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;
use Elementor\Scheme_Color;

if (!defined('ABSPATH')) {
	// Exit if accessed directly.
	exit;
}

/**
 * Awesomesauce widget class.
 *
 * @since 1.0.0
 */
class HomeSearchMap extends Widget_Base
{

	/**
	 * Retrieve the widget name.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name()
	{
		return 'listeo-homesearchmap';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget title.
	 */
	public function get_title()
	{
		return __('Home Search Map', 'listeo_elementor');
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget icon.
	 */
	public function get_icon()
	{
		return 'eicon-site-search';
	}

	
	public function get_script_depends()
	{
		return [
			'leaflet',
			'listeo_core-leaflet-geocoder', 
			'listeo-map-big',
			'listeo-map',
			'elementor-preview-listeo', // Add our Elementor preview script
		];
	}
	/**
	 * Retrieve the list of categories the widget belongs to.
	 *
	 * Used to determine where to display the widget in the editor.
	 *
	 * Note that currently Elementor supports only one category.
	 * When multiple categories passed, Elementor uses the first one.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return array Widget categories.
	 */
	public function get_categories()
	{
		return array('listeo');
	}

	/**
	 * Register the widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected function register_controls()
	{
		// 'title' 		=> 'Service Title',
		// 	    'url' 			=> '',
		// 	    'url_title' 	=> '',

		// 	   	'icon'          => 'im im-icon-Office',
		// 	    'type'			=> 'box-1', // 'box-1, box-1 rounded, box-2, box-3, box-4'
		// 	    'with_line' 	=> 'yes',
		// 	    
		$this->start_controls_section(
			'content_section',
			[
				'label' => __('Content', 'plugin-name'),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		if (function_exists('listeo_get_search_forms_dropdown')) {

			$search_forms = listeo_get_search_forms_dropdown('fullwidth');
			$this->add_control(
				'home_banner_form',
				[
					'label' => __('Form source ', 'listeo_elementor'),
					'type' => \Elementor\Controls_Manager::SELECT,

					'options' => $search_forms,
					'default' => 'search_on_home_page'


				]
			);
		}
		$this->add_control(
			'home_banner_form_action',
			[
				'label' => __('Form action ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'listing' => __('Listing', 'listeo_elementor'),
					'page' => __('Page', 'listeo_elementor'),
					'custom' => __(
						'Custom',
						'listeo_elementor'
					),
				],
				'default' => 'listing'
			]
		);
		$this->add_control(
			'home_banner_form_action_custom',
			[
				'label' => __('Custom action ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'condition' => [
					'home_banner_form_action' => 'custom',
				],

			]
		);
		$this->add_control(
			'home_banner_form_action_page',
			[
				'label' => __('Page ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $this->listeo_get_pages_dropdown(),
				'default' => '',
				'condition' => [
					'home_banner_form_action' => 'page',
				],
			]
		);



		$this->end_controls_section();
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$this->add_inline_editing_attributes('title', 'none');
		$this->add_inline_editing_attributes('subtitle', 'none');



?>


		<!-- Map
================================================== -->
		<div id="map-container" class="fullwidth-home-map">

			<!-- <div id="map" data-map-zoom="9">
        
    </div> -->

			<?php
			$maps = new \ListeoMaps;
			$maps->show_map();
			?>

			<div class="main-search-inner">

				<div class="container">
					<div class="row">
						<div class="col-md-12">

							<?php
							$home_banner_form_action_page = $settings['home_banner_form_action_page'];
							$home_banner_form_action_custom = $settings['home_banner_form_action_custom'];
							$home_banner_form_action = $settings['home_banner_form_action'];
							if ($home_banner_form_action == 'page' && !empty($home_banner_form_action_page)) {
								$home_banner_form_action = get_permalink($home_banner_form_action_page);
							} else if ($home_banner_form_action == 'custom' && !empty($home_banner_form_action_custom)) {
								$home_banner_form_action = $home_banner_form_action_custom;
							} else {
								$home_banner_form_action = get_post_type_archive_link('listing');
							}

							?>
							<?php

							echo do_shortcode('[listeo_search_form action=' . $home_banner_form_action . '  source="' . $settings['home_banner_form'] . '"  custom_class="main-search-form"]') ?>

						</div>
					</div>
				</div>

			</div>
			<a href="#" id="show-map-button" class="show-map-button" data-enabled="<?php esc_attr_e('Show Map ', 'listeo'); ?>" data-disabled="<?php esc_attr_e('Hide Map ', 'listeo'); ?>"><?php esc_html_e('Show Map ', 'listeo') ?></a>

			<!-- Scroll Enabling Button -->
			<a href="#" id="scrollEnabling" title="<?php esc_attr_e('Enable or disable scrolling on map', 'listeo') ?>"><?php esc_html_e('Enable Scrolling', 'listeo') ?></a>

		</div>


	
<?php

	}

	protected function get_terms($taxonomy)
	{
		$taxonomies = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

		$options = ['' => ''];

		if (!empty($taxonomies)) :
			foreach ($taxonomies as $taxonomy) {
				$options[$taxonomy->term_id] = $taxonomy->name;
			}
		endif;

		return $options;
	}

	function listeo_get_pages_dropdown()
	{
		$pages = get_pages();
		$options = ['' => ''];
		if (!empty($pages)) :
			foreach ($pages as $page) {
				$options[$page->ID] = $page->post_title;
			}
		endif;
		return $options;
	}
}
