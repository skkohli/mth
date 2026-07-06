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
class Addresbox extends Widget_Base {

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
		return 'listeo-address-box';
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
		return __( 'Address box', 'listeo_elementor' );
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
		return 'eicon-google-maps';
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
			'content',
			[
				'label' => __( 'Address', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::WYSIWYG,
				'rows' => 5,
				'placeholder' => __( 'Enter your Address & details', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'latitude',
			array(
				'label'   => __( 'Map Marker Latitude', 'listeo_elementor' ),
				'type'    => Controls_Manager::TEXT,
			)
		);	
		$this->add_control(
			'longitude',
			array(
				'label'   => __( 'Map Marker Longitude', 'listeo_elementor' ),
				'type'    => Controls_Manager::TEXT,
			)
		);	
		$this->add_control(
			'background',
			[
				'label' => __( 'Choose Background Image', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::MEDIA,
				'default' => [
					'url' => \Elementor\Utils::get_placeholder_image_src(),
				]
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

		?>
		
		<div class="contact-map margin-bottom-55">

			<!-- Google Maps -->
			<div id="singleListingMap-container">
				<div id="singleListingMap" data-latitude="<?php echo esc_attr($settings['latitude']); ?>" data-longitude="<?php echo esc_attr($settings['longitude']); ?>" data-map-icon="im im-icon-Map2"></div>
				<a href="#" id="streetView"><?php echo esc_html__('Street View','listeo_elementor') ?></a>
			</div>

			<!-- Office -->
			<div class="address-box-container">
				<div class="address-container" data-background-image="<?php echo esc_url($settings['background']['url']); ?>">
					<div class="office-address">
						<?php echo ($settings['content']); ?>
					</div>
				</div>
			</div>

		</div>
		<div class="clearfix"></div><?php
	}


}