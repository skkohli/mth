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


if (!defined('ABSPATH')) {
	// Exit if accessed directly.
	exit;
}

/**
 * Awesomesauce widget class.
 *
 * @since 1.0.0
 */
class HomeBannerSimpleSlider extends Widget_Base
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
		return 'listeo-homebanner-simple-slider';
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
		return __('Home Search Simple Slider', 'listeo_elementor');
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


		$this->start_controls_section(
			'section_content',
			array(
				'label' => __('Content', 'listeo_elementor'),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __('Title', 'listeo_elementor'),
				'type'    => Controls_Manager::TEXT,
				'default' => __('Find Nearby Attractions', 'listeo_elementor'),
			)
		);
		$this->add_control(
			'subtitle',
			array(
				'label'   => __('Subtitle', 'listeo_elementor'),
				'type'    => Controls_Manager::TEXT,
				'default' => __('Explore top-rated attractions, activities and more!', 'listeo_elementor'),
			)
		);

		$this->add_control(
			'typed',
			[
				'label' => __('Enable Type words effect', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);
		$this->add_control(
			'typed_text',
			array(
				'label'   => __('Text to displayed in "typed" section, separate by coma', 'listeo_elementor'),
				'label_block' => true,
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __('Attractions, Restaurants, Hotels', 'listeo_elementor'),
			)
		);
		$this->add_control(
			'listing_types',
			[
				'label' => __('Hide Listing Type selection', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'no',

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
					'custom' => __('Custom', 'listeo_elementor'),
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
		$this->add_control(
			'headers_color',
			[
				'label' => esc_html__('Title Color', 'plugin-name'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#fff',
				'selectors' => [
					'{{WRAPPER}} h1' => 'color: {{VALUE}}',
					'{{WRAPPER}} h2' => 'color: {{VALUE}}',
					'{{WRAPPER}} h4' => 'color: {{VALUE}}',
					'{{WRAPPER}} h5' => 'color: {{VALUE}}',
				],
			]
		);


		$this->add_control(
			'home_banner_text_align',
			[
				'label' => __('Text alignment ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'left',
				'options' => [
					'center' => __('Center', 'listeo_elementor'),
					'left' 	 => __('Left', 'listeo_elementor'),

				],
				'selectors' => [
					'{{WRAPPER}} .main-search-inner' => 'text-align: {{VALUE}}'
				],


			]
		);

		$this->add_control(
			'button_link',
			[
				'label' => esc_html__('Button', 'textdomain'),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => esc_html__('https://your-link.com', 'textdomain'),
				'options' => ['url', 'is_external', 'nofollow'],
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
					// 'custom_attributes' => '',
				],
				'label_block' => true,
			]
		);
		// add text control
		$this->add_control(
			'button_label',
			[
				'label' => esc_html__('Button Text', 'textdomain'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__('Search', 'textdomain'),
				'placeholder' => esc_html__('Search', 'textdomain'),
				'label_block' => true,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'background',
				'label' => esc_html__('Background', 'plugin-name'),
				'types' => ['classic', 'gradient', 'video'],
				'selector' => '{{WRAPPER}} .main-search-container.plain-color',
			]
		);
		// $this->add_control(
		// 	'background',
		// 	[
		// 		'label' => __( 'Choose Background Image', 'listeo_elementor' ),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,
		// 		'selectors' => [

		// 			'{{WRAPPER}} .main-search-container.plain-color' => 'background-image:  url({{VALUE}})',
		// 		],
		// 	]
		// );





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
				'title_field' => '{{{ slide_title_1st }}}',
			]
		);


		$this->add_control(
			'featured_categories_status',
			[
				'label' => __('Show Featured Categories', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'listeo_elementor'),
				'label_off' => __('Hide', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);


		$this->add_control(
			'tax-listing_category',
			[
				'label' => __('Show in Featured Categories this terms', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_terms('listing_category'),
				'condition' => [
					'featured_categories_status' => 'yes',
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

		if (!empty($settings['background']['url'])) {

			$background_image = $settings['background']['url'];
		} else {
			$background_image = get_option('listeo_search_bg');
		}

		$video = false;

		if (isset($settings['video']['url']) && !empty($settings['video']['url'])) {
			$video = $settings['video']['url'];
		}

		$classes = array();

		// if($settings['solid_background'] == 'solid'){
		// 	$classes[] = 'solid-bg-home-banner';
		// }
		// if( $settings['search_form_style'] == 'boxed') { 
		// 	$classes[] = 'alt-search-box centered';
		// }

		if ($video) {
			$classes[] = 'dark-overlay';
		}

?>


		<div class="main-search-container  elementor-main-search-container  plain-color main-search-container-with-slider main-search-simple-slider">

			<!-- Main Search Photo Slider -->
			<div class="container msps-container">


				<?php if ($settings['list']) { ?>
					<div class="main-search-photo-slider">
						<div class="msps-slider-container">
							<div class="msps-slider alt">
								<?php
								foreach ($settings['list'] as $item) { ?>
									<div class="item"><img src="<?php echo $item['list_background']['url']; ?>" class="item" title="<?php echo $item['slide_title_1st']; ?>" /></div>
								<?php }

								?>
							</div>
						</div>
					</div>
				<?php } ?>


				<div class="main-search-inner">

					<div class="container">
						<div class="row">
							<div class="col-md-12">

								<h1><?php echo $settings['title']; ?> <span class="typed-words"></span></h1>
								<?php if (!empty($settings['subtitle'])) { ?><h4><?php echo $settings['subtitle']; ?></h4><?php } ?>
								<?php if (!empty($settings['button_link']['url'])) {
									$this->add_link_attributes('button_link', $settings['button_link']);
								}
								if ($settings['button_label']) { ?>
									<a class="button" <?php echo $this->get_render_attribute_string('button_link'); ?>>
										<?php echo $settings['button_label']; ?>
									</a>
								<?php } ?>
								<?php
								if (isset($settings['featured_categories_status']) && $settings['featured_categories_status'] == 'yes') :

									if (isset($settings['tax-listing_category'])) :
										$category = is_array($settings['tax-listing_category']) ? $settings['tax-listing_category'] : array_filter(array_map('trim', explode(',', $settings['tax-listing_category'])));


										if (!empty($category)) : ?>
											<div class="row">
												<div class="col-md-12">
													<h5 class="highlighted-categories-headline"><?php esc_html_e('Or browse featured categories:', 'listeo_elementor') ?></h5>


													<div class="highlighted-categories">

														<?php

														foreach ($category as $value) {

															$term = get_term($value, 'listing_category');

															if ($term && !is_wp_error($term)) {
																$icon = get_term_meta($value, 'icon', true);
																$_icon_svg = get_term_meta($value, '_icon_svg', true);
														?>
																<!-- Box -->
																<a href="<?php echo get_term_link($term->slug, 'listing_category'); ?>" class="highlighted-category">
																	<?php if (!empty($_icon_svg)) { ?>
																		<i>
																			<?php echo listeo_render_svg_icon($_icon_svg); ?>
																		</i>
																	<?php } else if ($icon && $icon != 'empty') { ?><i class="<?php echo esc_attr($icon); ?>"></i><?php
																																								} ?>
																	<h4><?php echo esc_html($term->name) ?></h4>
																</a>

														<?php }
														} ?>

													</div>

												</div>
											</div>
								<?php
										endif;
									endif;
								endif; ?>

							</div>
						</div>


					</div>

				</div>

				<div class="simple-slider-search-form <?php if ($settings['listing_types'] == 'yes') {
															echo "no-type-filter";
														} ?>">
					<?php 
					// Get dynamic listing types
					if (function_exists('listeo_core_custom_listing_types')) {
						$custom_types_manager = listeo_core_custom_listing_types();
						$available_types = $custom_types_manager->get_listing_types(true);
						$listing_types = array();
						foreach ($available_types as $type) {
							$listing_types[] = $type->slug;
						}
					} else {
						$listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event', 'classifieds'));
					}
					?>


					<div class="container main-search-inner">
						<?php
						if (!empty($listing_types) && sizeof($listing_types) > 1) {
							if ($settings['listing_types'] != 'yes') { ?>
								<div class="simple-slider-form-tabs">
									<div class="simple-slider-form-inner">
										<div class="filter-tabs">
											<div class="filter-tab active"><?php esc_html_e('All', 'listeo_elementor'); ?></div>
											<?php foreach ($listing_types as $type) {
												// Get dynamic listing type information
												if (function_exists('listeo_core_custom_listing_types')) {
													$custom_types_manager = listeo_core_custom_listing_types();
													$type_obj = $custom_types_manager->get_listing_type_by_slug($type);
													if ($type_obj) {
														echo '<div id="filter-tab-' . esc_attr($type) . '" data-type="' . esc_attr($type) . '" class="filter-tab">' . esc_html($type_obj->name) . '</div>';
													}
												} else {
													// Fallback to old hardcoded names
													switch ($type) {
														case 'rental':
															echo '<div id="filter-tab-rental" data-type="rental" class="filter-tab">' . esc_html__('Rental', 'listeo_elementor') . '</div>';
															break;
														case 'service':
															echo '<div id="filter-tab-service" data-type="service" class="filter-tab">' . esc_html__('Services', 'listeo_elementor') . '</div>';
															break;
														case 'event':
															echo '<div id="filter-tab-event" data-type="event" class="filter-tab">' . esc_html__('Events', 'listeo_elementor') . '</div>';
															break;
														case 'classifieds':
															echo '<div id="filter-tab-classifieds" data-type="classifieds" class="filter-tab">' . esc_html__('Classifieds', 'listeo_elementor') . '</div>';
															break;
														default:
															// Generic display for custom types
															echo '<div id="filter-tab-' . esc_attr($type) . '" data-type="' . esc_attr($type) . '" class="filter-tab">' . esc_html(ucfirst($type)) . '</div>';
															break;
													}
												}
											} ?>
										</div>
									</div>
								</div>
						<?php }
						} ?>
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

						echo do_shortcode('[listeo_search_form action=' . $home_banner_form_action . ' source="' . $settings['home_banner_form'] . '"  custom_class="main-search-form"]') ?>
					</div>

				</div>
			</div>

		</div>

		<?php
		if ($settings['typed'] == 'yes') {

			$typed = $settings['typed_text'];
			$typed_array = explode(',', $typed);
		?>
			<script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.9"></script>
			<script>
				var typed = new Typed('.typed-words', {
					strings: <?php echo json_encode($typed_array); ?>,
					typeSpeed: 70,
					backSpeed: 80,
					backDelay: 4000,
					startDelay: 1000,
					loop: true,
					showCursor: true
				});
			</script>

		<?php
		} ?>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/parallax/2.1.3/parallax.min.js"></script>

		<script>
			/* ----------------- Start Document ----------------- */
			(function($) {
				"use strict";

				$(document).ready(function() {

					$('.filter-tab').click(function() {
						$(this)
							.addClass('active')
							.siblings()
							.removeClass('active');
					});


					$('.msps-slider').slick({
						infinite: true,
						slidesToShow: 1,
						slidesToScroll: 1,
						dots: false,
						arrows: true,
						autoplay: true,
						autoplaySpeed: 4000,
						centerMode: true,
						speed: 1000,
						fade: true,
						cssEase: 'linear'
					});

					$(".msps-slider").slick('slickGoTo', 1);
				});

			})(this.jQuery);
		</script>
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
