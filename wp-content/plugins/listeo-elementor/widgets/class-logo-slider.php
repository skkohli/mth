<?php
/**
 * Listeo Elementor Address Box class.
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

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Awesomesauce widget class.
 *
 * @since 1.0.0
 */
class LogoSlider extends Widget_Base {

	/**
	 * Retrieve the widget name.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'listeo-logo-slider';
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
	public function get_title() {
		return __( 'Logo Slider/List', 'listeo_elementor' );
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
	public function get_icon() {
		return 'eicon-site-logo';
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
	public function get_categories() {
		return array( 'listeo' );
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
	// 	'latitude' 	=> '', 
		 	// 'longitude' 	=> '', 
		 	// 'background' => '',
	   //      'from_vs'  	=> '',
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'listeo_elementor' ),
			)
		);


		$this->add_control(
			'type',
			[
				'label' => __('Type', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'list',
				'options' => [
					'carousel' =>  __('Carousel', 'listeo_elementor'),
					'list' =>  __('List. ', 'listeo_elementor'),


				],
			]
		);

		$this->add_control(
			'gallery',
			[
				'label' => __( 'Add Images', 'listeo_elementor'  ),
				'type' => \Elementor\Controls_Manager::GALLERY,
				'default' => [],
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
	protected function render() {
		$settings = $this->get_settings_for_display(); 
	
		if($settings['type'] == 'carousel'){

		
			$output = '<div class="logo-slick-carousel dot-navigation">';
			if(!empty($settings['gallery'])){
		
				foreach ($settings['gallery'] as $image) {
					
					$output .= '<div class="item"><img src="'.$image['url'].'" alt=""/></div>';
				}
			}
			$output .= '
		</div>';
		echo $output;
		} else {
			$output = '<div class="trusted-by-container">';
			if(!empty($settings['gallery'])){
				foreach ($settings['gallery'] as $image) {
					$output .= '<div class="trusted-by-logo"><img src="'.$image['url'].'" alt=""/></div>';
				}
			}
			$output .= '</div>';
			echo $output;
		}


	}



}