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

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Awesomesauce widget class.
 *
 * @since 1.0.0
 */
class PricingTable extends Widget_Base {

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
		return 'listeo-pricingtable';
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
		return __( 'Pricing Table', 'listeo_elementor' );
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
		return 'eicon-cart-light';
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
	protected function register_controls() {
 // "type"          => 'color-1',
 //      
 //        "color"         => '',
 //        "title"         => '',
 //       
 //        "price"         => '',
 //        "discounted"    => '',
 //        "per"           => '',
 
 //        "buttonlink"    => '',
 //        "buttontext"    => 'Sign Up',


		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'listeo_elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Title', 'listeo_elementor' ),
			)
		);		

		$this->add_control(
			'content',
			array(
				'label'   => __( 'Content', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::WYSIWYG,
				'default' => __( 'Title', 'listeo_elementor' ),
			)
		);	
		$this->add_control(
			'price',
			array(
				'label'   => __( 'Price', 'listeo_elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( '19.99', 'listeo_elementor' ),
			)
		);	
		$this->add_control(
			'discounted',
			array(
				'label'   => __( 'Discount', 'listeo_elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( '8.99', 'listeo_elementor' ),
			)
		);	
		$this->add_control(
			'per',
			array(
				'label'   => __( 'Per', 'listeo_elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'month', 'listeo_elementor' ),
			)
		);


		$this->add_control(
			'buttonlink',
			[
				'label' => __( 'Buy button URL','listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => __( 'https://your-link.com', 'listeo_elementor' ),
				'show_external' => true,
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
				],
			]
		);
		$this->add_control(
			'buttontext',
			[
				'label' => __( 'Buy button Label','listeo_elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Buy Now', 'listeo_elementor' ),
				
				
			]
		);


		$this->add_control(
			'type',
			[
				'label' => __( 'Featured package', 'plugin-domain' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'your-plugin' ),
				'label_off' => __( 'No', 'your-plugin' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);


		$this->add_control(
			'color',
			[
				'label' => __( 'Color', 'plugin-domain' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				// 'scheme' => [
				// 	'type' => \Elementor\Scheme_Color::get_type(),
				// 	'value' => \Elementor\Scheme_Color::COLOR_1,
				// ],
				// 'selectors' => [
				// 	'{{WRAPPER}} .title' => 'color: {{VALUE}}',
				// ],
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
		 <div class="plan <?php if($settings['type'] == 'yes') { ?> featured <?php } ?> ?>">
		 <?php
		if($settings['type'] == 'yes') { ?>
	        <div class="listing-badge">
	            <span class="featured"><?php esc_html__('Featured','listeo_elementor') ?></span>
	        </div>
	    <?php } ?>
	    
	        <div class="plan-price" style="background-color: <?php echo $settings['color'];?>;">
	            <h3><?php echo $settings['title']; ?></h3>
	            <?php if(!empty($settings['discounted'])){ ?>
	                <span class="value"> <del><span class="woocommerce-Price-amount amount"><bdi><?php echo $settings['price']; ?></bdi></span></del> <ins><span class="woocommerce-Price-amount amount"><bdi><?php echo $settings['discounted']; ?></bdi></span></ins></span>

	            <?php } else { ?>
	                <div class="value"><?php echo $settings['price'] ?></div>
	            <?php }
	            if(!empty($settings['per'])){ ?>
	                <span class="period"><?php echo $settings['per']; ?></span>
	            <?php } ?>
	        </div>
	        <div class="plan-features"><?php echo  $settings['content']; ?>
	        
	        <?php if($settings['buttontext']) { ?>
	            <a class="button"  style="background-color: <?php echo $settings['color'];?>" href="<?php echo $settings['buttonlink']['url'] ; ?>"><i class="fa fa-shopping-cart"></i> <?php echo $settings['buttontext']; ?></a>
	        <?php } ?>
	    	</div>
	    </div>
	<?php 
	}

	
}