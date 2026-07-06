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
class TaxonomyBox extends Widget_Base
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
		return 'listeo-taxonomy-box';
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
		return __('Taxonomy Boxes', 'listeo_elementor');
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
				'default' => 'listing_category',
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
		$query_args = array(
			'include' => $settings[$taxonomy . '_include'],
			'exclude' => $settings[$taxonomy . '_exclude'],
			'hide_empty' => false,
			'number' => $settings['number'],
		);
		if ($settings['only_top'] == 'yes') {
			$query_args['parent'] = 0;
		}
		$terms = get_terms($settings['taxonomy'], $query_args);

		if (!empty($terms) && !is_wp_error($terms)) {
?>

			<div class="taxonomy-boxes-wrapper">

				<!-- Item -->
				<?php
				foreach ($terms as $term) {
					$t_id = $term->term_id;

					// retrieve the existing value(s) for this meta field. This returns an array
					$icon = get_term_meta($t_id, 'icon', true);
					$_icon_svg = get_term_meta($t_id, '_icon_svg', true);
					$_icon_svg_image = wp_get_attachment_image_src($_icon_svg, 'medium');
					if (empty($icon)) {
						$icon = 'fa fa-globe';
					}

				?>
				<a href="<?php echo get_term_link($term); ?>" class="taxonomy-box-wrapper">

						<div class="taxonomy-box-top">
							<div class="taxonomy-box-left">
								<h4><?php echo $term->name; ?></h4>
								<div class="taxonomy-box-content">

									<?php if (!empty($_icon_svg_image)) { ?>
										<i class="listeo-svg-icon-box-grid">
											<?php echo listeo_render_svg_icon($_icon_svg); ?>
										</i>
									<?php } else {
										if ($icon != 'emtpy') {
											$check_if_im = substr($icon, 0, 3);
											if ($check_if_im == 'im ') {
												echo ' <i class="' . esc_attr($icon) . '"></i>';
											} else {
												echo ' <i class="fa ' . esc_attr($icon) . '"></i>';
											}
										}
									} ?>
									<?php if ($settings['show_counter'] == "yes") { ?>
										<span class="category-box-counter">
											<?php $count = listeo_get_term_post_count($settings['taxonomy'], $term->term_id);

											echo $count;
											// add translatable listings or listing based on count
											?>
											<?php echo ($count == 1) ? esc_html__('Listing', 'listeo_elementor') : esc_html__('Listings', 'listeo_elementor');
											?>


										</span>
									<?php } ?>
								</div>
							</div>
							<div class="taxonomy-box-right">
								<svg xmlns="http://www.w3.org/2000/svg" width="18.925" height="12.091" viewBox="0 0 18.925 12.091">
									<g id="square-filled" transform="translate(0 0)">
										<path id="right-arrow" d="M175.2,39.153l5.542,5.494.031.028a.518.518,0,0,1,.153.338v.065a.518.518,0,0,1-.153.338l-.027.023-5.546,5.5a.528.528,0,0,1-.743,0,.517.517,0,0,1,0-.737l4.735-4.7H162.525a.521.521,0,1,1,0-1.042h16.543l-4.612-4.573a.517.517,0,0,1,0-.737A.528.528,0,0,1,175.2,39.153Zm5.261,5.831-5.632,5.586,5.573-5.524v0l-.031-.028-.032-.031Z" transform="translate(-162 -39)" fill="#252528" />
									</g>
								</svg>
							</div>
						</div>
						<div class="taxonomy-box-bottom">
							<?php
							$cover_id = get_term_meta($term->term_id, '_cover', true);
							if ($cover_id) {
								$cover = wp_get_attachment_image_src($cover_id, 'listeo-blog-post'); ?>
								<img src="<?php echo $cover[0];  ?>">
							<?php } ?>
						</div>
					</a>

				<?php } ?>
			</div>

<?php }
	}


	protected function get_taxonomies()
	{
		$taxonomies = get_object_taxonomies('listing', 'objects');

		$options = ['' => ''];

		foreach ($taxonomies as $taxonomy) {
			$options[$taxonomy->name] = $taxonomy->label;
		}

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
}
