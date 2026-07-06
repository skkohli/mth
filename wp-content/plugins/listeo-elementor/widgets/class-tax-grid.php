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
class TaxonomyGrid extends Widget_Base
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
		return 'listeo-taxonomy-grid';
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
		return __('Taxonomy Grid', 'listeo_elementor');
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
		return 'eicon-gallery-grid';
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
	 * Enqueue widget scripts and styles.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function get_script_depends()
	{
		return array('taxonomy-responsive-slider-script');
	}

	public function get_style_depends()
	{
		return array('taxonomy-responsive-slider-style');
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
		// 	'taxonomy' => '',
		// 'xd' 	=> '',
		// 'only_top' 	=> 'yes',
		// 'autoplay'      => '',
		//          'autoplayspeed'      => '3000',

		$this->add_control(
			'taxonomy',
			[
				'label' => __('Taxonomy', 'elementor-pro'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'default' => [],
				'options' => $this->get_taxonomies(),

			]
		);

		$taxonomy_names = get_object_taxonomies('listing', 'object');
		foreach ($taxonomy_names as $key => $value) {

			$this->add_control(
				$value->name . '_include',
				[
					'label' => __('Include listing from ' . $value->label, 'listeo_elementor'),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => true,
					'default' => [],
					'options' => $this->get_terms($value->name),
					'condition' => [
						'taxonomy' => $value->name,
					],
				]
			);
			$this->add_control(
				$value->name . '_exclude',
				[
					'label' => __('Exclude listings from ' . $value->label, 'listeo_elementor'),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => true,
					'default' => [],
					'options' => $this->get_terms($value->name),
					'condition' => [
						'taxonomy' => $value->name,
					],
				]
			);
		}

		// Add controls for listing types
		$this->add_control(
			'listing_types_include',
			[
				'label' => __('Include Listing Types', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_listing_types_options(),
				'condition' => [
					'taxonomy' => 'listing_types',
				],
			]
		);
		$this->add_control(
			'listing_types_exclude',
			[
				'label' => __('Exclude Listing Types', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_listing_types_options(),
				'condition' => [
					'taxonomy' => 'listing_types',
				],
			]
		);
		$this->add_control(
			'number',
			[
				'label' => __('Terms to display', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 199,
				'step' => 1,
				'default' => 6,
			]
		);
		$this->add_control(
			'only_top',
			[
				'label' => __('Show only top terms', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);


		$this->add_control(
			'show_counter',
			[
				'label' => __('Show listings counter', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);

		$this->add_control(
			'style',
			[
				'label' => __('Style ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'default' => __('Default', 'listeo_elementor'),
					'alt' => __('Alternative', 'listeo_elementor'),

				],
			]
		);

		$this->add_control(
			'mobile_carousel',
			[
				'label' => __('Mobile Carousel', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);



		// $taxonomy_names = get_object_taxonomies( 'listing','object' );

		// foreach ($taxonomy_names as $key => $value) {
		// 	$shortcode_atts[$value->name.'_include'] = '';
		// 	$shortcode_atts[$value->name.'_exclude'] = '';
		// }


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


		$taxonomy_names = get_object_taxonomies('listing', 'object');

		$taxonomy = $settings['taxonomy'];


		if (empty($taxonomy)) {
			$taxonomy = "listing_category";
		}

		// Handle listing types differently
		if ($taxonomy === 'listing_types') {
			$terms = $this->get_listing_types_for_display($settings);
		} else {
			$query_args = array(
				'include' => $settings[$taxonomy . '_include'],
				'exclude' => $settings[$taxonomy . '_exclude'],
				'hide_empty' => false,
				'number' => $settings['number'],
			);
			if ($settings['only_top'] == 'yes') {
				$query_args['parent'] = 0;
			}
			$terms = get_terms($taxonomy, $query_args);
		}

		if (!empty($terms) && !is_wp_error($terms)) {
?>
			<!-- Responsive Slider Wrapper (hidden on desktop, visible on mobile) -->
			<div class="taxonomy-slider-wrapper<?php if ($settings['mobile_carousel'] != 'yes') { echo ' no-mobile-carousel'; } ?>">
				<div class="categories-boxes-container<?php if ($settings['style'] == 'alt') {
															echo "-alt";
														} ?> margin-top-5 margin-bottom-30<?php if ($settings['mobile_carousel'] == 'yes') { echo ' taxonomy-responsive-slider'; } ?>">
					<!-- Item -->
					<?php
					foreach ($terms as $term) {
						if ($settings['taxonomy'] === 'listing_types') {
							// Handle listing types
							$icon = '';
							$_icon_svg = null;
							$_icon_svg_image = null;

							// Get icon from listing type (icon_id stored in the type object)
							if (!empty($term->icon_id)) {
								$_icon_svg = $term->icon_id;
								$_icon_svg_image = wp_get_attachment_image_src($term->icon_id, 'medium');
							} else {
								// Fallback to old option format
								$icon_id = get_option('listeo_' . $term->slug . '_type_icon');
								if ($icon_id) {
									$_icon_svg = $icon_id;
									$_icon_svg_image = wp_get_attachment_image_src($icon_id, 'medium');
								}
							}

							if (empty($_icon_svg)) {
								$icon = 'fa fa-globe';
							}
						} else {
							// Handle regular taxonomies
							$t_id = $term->term_id;

							// retrieve the existing value(s) for this meta field. This returns an array
							$icon = get_term_meta($t_id, 'icon', true);
							$_icon_svg = get_term_meta($t_id, '_icon_svg', true);
							$_icon_svg_image = wp_get_attachment_image_src($_icon_svg, 'medium');
							if (empty($icon)) {
								$icon = 'fa fa-globe';
							}
						}

					?>
						<a href="<?php  echo esc_url($this->get_term_link($term, $settings['taxonomy'])); ?>" class="category-small-box<?php if ($settings['style'] == 'alt') {
																									echo "-alt";
																								} ?>">
							<?php if (!empty($_icon_svg_image) && !empty($_icon_svg)) {
								$mime_type = get_post_mime_type($_icon_svg);
								if ($mime_type === 'image/svg+xml') { ?>
									<i class="listeo-svg-icon-box-grid">
										<?php echo listeo_render_svg_icon($_icon_svg); ?>
									</i>
								<?php } else { ?>
									<img src="<?php echo esc_url($_icon_svg_image[0]); ?>" alt="<?php echo esc_attr($term->name); ?>" class="listeo-icon-img-grid">
								<?php }
							} else {
								if ($icon != 'emtpy') {
									$check_if_im = substr($icon, 0, 3);
									if ($check_if_im == 'im ') {
										echo ' <i class="' . esc_attr($icon) . '"></i>';
									} else {
										echo ' <i class="fa ' . esc_attr($icon) . '"></i>';
									}
								}
							} ?>
							<h4><?php echo $term->name; ?></h4>
							<?php if ($settings['show_counter'] == "yes") { ?><span class="category-box-counter<?php if ($settings['style'] == 'alt') {
																													echo "-alt";
																												} ?>"><?php echo $this->get_term_count($term, $settings['taxonomy']); ?></span> <?php } ?>
							<?php if ($settings['style'] == 'alt') {
								if ($settings['taxonomy'] !== 'listing_types') {
									$cover_id = get_term_meta($term->term_id, '_cover', true);
									if ($cover_id) {
										$cover = wp_get_attachment_image_src($cover_id, 'listeo-blog-post');  ?>
										<img src="<?php echo $cover[0];  ?>">
								<?php }
								}
							} ?>
						</a>

				<?php } ?>

				</div>

				<?php if ($settings['mobile_carousel'] == 'yes') { ?>
				<!-- Slick-style Navigation -->
				<div class="taxonomy-slider-controls-container">
					<div class="taxonomy-slider-controls">
						<button type="button" class="taxonomy-slide-prev taxonomy-arrow"></button>
						<div class="taxonomy-slide-dots" role="toolbar">
							<ul class="taxonomy-dots" role="tablist">
								<!-- Dots will be generated by JavaScript -->
							</ul>
						</div>
						<button type="button" class="taxonomy-slide-next taxonomy-arrow"></button>
					</div>
				</div>
				<?php } ?>
			</div> <!-- Close taxonomy-slider-wrapper -->

		<?php }
	}


	protected function get_taxonomies()
	{
		$taxonomies = get_object_taxonomies('listing', 'objects');

		$options = ['' => ''];

		// Add regular taxonomies
		foreach ($taxonomies as $taxonomy) {
			$options[$taxonomy->name] = $taxonomy->label;
		}

		// Add listing types as an option
		$options['listing_types'] = __('Listing Types', 'listeo_elementor');

		return $options;
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

	protected function get_listing_types_options()
	{
		$options = ['' => ''];

		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$listing_types = $custom_types_manager->get_listing_types(true);

			if ($listing_types) {
				foreach ($listing_types as $type) {
					$options[$type->slug] = $type->name;
				}
			}
		} else {
			// Fallback to default types
			$options = [
				'' => '',
				'service' => __('Service', 'listeo_elementor'),
				'rental' => __('Rental', 'listeo_elementor'),
				'event' => __('Event', 'listeo_elementor'),
				'classifieds' => __('Classifieds', 'listeo_elementor'),
			];
		}

		return $options;
	}

	protected function get_listing_types_for_display($settings)
	{
		$all_types = array();

		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$all_types = $custom_types_manager->get_listing_types(true);
		} else {
			// Fallback to default types
			$all_types = array(
				(object) array(
					'slug' => 'service',
					'name' => __('Service', 'listeo_elementor'),
					'icon_id' => get_option('listeo_service_type_icon'),
				),
				(object) array(
					'slug' => 'rental',
					'name' => __('Rental', 'listeo_elementor'),
					'icon_id' => get_option('listeo_rental_type_icon'),
				),
				(object) array(
					'slug' => 'event',
					'name' => __('Event', 'listeo_elementor'),
					'icon_id' => get_option('listeo_event_type_icon'),
				),
				(object) array(
					'slug' => 'classifieds',
					'name' => __('Classifieds', 'listeo_elementor'),
					'icon_id' => get_option('listeo_classifieds_type_icon'),
				),
			);
		}

		if (empty($all_types)) {
			return array();
		}

		// Apply include/exclude filters
		$include = $settings['listing_types_include'];
		$exclude = $settings['listing_types_exclude'];

		$filtered_types = array();
		foreach ($all_types as $type) {
			// Check include filter
			if (!empty($include) && !in_array($type->slug, $include)) {
				continue;
			}

			// Check exclude filter
			if (!empty($exclude) && in_array($type->slug, $exclude)) {
				continue;
			}

			$filtered_types[] = $type;
		}

		// Apply number limit
		if (!empty($settings['number']) && count($filtered_types) > $settings['number']) {
			$filtered_types = array_slice($filtered_types, 0, $settings['number']);
		}


		return $filtered_types;
	}

protected function get_term_link($term, $taxonomy)
	{
		if ($taxonomy === 'listing_types') {
			// Use post type archive for listing types with query parameter
			$base_url = get_post_type_archive_link('listing');

			// If no archive link exists, the post type doesn't have archive enabled
			// In this case, we should not create a manual URL as it will lead to 404
			if (!$base_url) {
				// Return home URL as fallback - this prevents 404s
				$base_url = home_url('/');
			}

			return add_query_arg('_listing_type', $term->slug, $base_url);
		} else {
			// Create URL with only the child term slug, not full hierarchy
			$url = get_term_link($term, $taxonomy);
			if (!is_wp_error($url)) {
				return $url;
			}
			return '';
		}
	}

	protected function get_term_count($term, $taxonomy)
	{
		if ($taxonomy === 'listing_types') {
			// Count listings of this type
			$args = array(
				'post_type' => 'listing',
				'post_status' => 'publish',
				'meta_query' => array(
					array(
						'key' => '_listing_type',
						'value' => $term->slug,
						'compare' => '='
					)
				),
				'fields' => 'ids'
			);
			$query = new \WP_Query($args);
			return $query->found_posts;
		} else {
			return listeo_get_term_post_count($taxonomy, $term->term_id);
		}
	}
}
