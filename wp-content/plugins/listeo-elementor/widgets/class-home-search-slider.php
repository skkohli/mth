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
class HomeSearchSlider extends Widget_Base
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
		return 'listeo-homesearchslider';
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
		return __('Home Search Slider', 'listeo_elementor');
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

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'slide_title_1st',
			[
				'label' => __('Title first line', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __('List Title', 'plugin-domain'),
				'label_block' => true,
			]
		);
		$repeater->add_control(
			'slide_title_2nd',
			[
				'label' => __('Title second line', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __('List Title', 'plugin-domain'),
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'list_background',
			[
				'label' => __('Content', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::MEDIA,

				'show_label' => false,
			]
		);

		$this->add_control(
			'list',
			[
				'label' => __('Slides', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'prevent_empty' => false,
				'default' => [
					[
						'slide_title_1st' => __('Title #1', 'plugin-domain'),
						'slide_title_2nd' => __('Item content. Click the edit button to change this text.', 'plugin-domain'),
					]

				],
				'title_field' => '{{{ slide_title_1st }}}',
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label' => __('Auto Play', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);


		$this->add_control(
			'autoplayspeed',
			array(
				'label'   => __('Auto Play Speed', 'listeo_elementor'),
				'type'    => Controls_Manager::NUMBER,
				'default' => __('Subtitle', 'listeo_elementor'),
				'min' => 1000,
				'max' => 10000,
				'step' => 500,
				'default' => 3000,
			)
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

		<!-- Banner
================================================== -->
		<div class="home-search-carousel carousel-not-ready" <?php if ($settings['autoplay'] == 'yes') { ?> data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
			<?php
			$settings = $this->get_settings_for_display();

			if ($settings['list']) {


				foreach ($settings['list'] as $item) { ?>

					<div class="home-search-slide" style="background-image: url(<?php echo  $item['list_background']['url'] ?>)">
						<div class="home-search-slider-headlines">
							<div class="container">
								<div class="col-md-12">
									<h3><?php echo $item['slide_title_1st']; ?></h3>
									<h3><?php echo $item['slide_title_2nd']; ?></h3>
								</div>
							</div>
						</div>
					</div>

			<?php }
			}
			?>
			<!-- Item -->


			<!-- Search -->
			<div class="container search-cont">
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
		<div class="home-search-carousel-placeholder">
			<div class="home-search-carousel-loader">

			</div>
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
