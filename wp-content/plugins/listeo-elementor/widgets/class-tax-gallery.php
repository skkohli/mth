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

if (! defined('ABSPATH')) {
	// Exit if accessed directly.
	exit;
}

/**
 * Awesomesauce widget class.
 *
 * @since 1.0.0
 */
class TaxonomyGallery extends Widget_Base
{



	// public function __construct( $data = array(), $args = null ) {
	// 	parent::__construct( $data, $args );

	// 	wp_register_script( 'listeo-taxonomy-carousel-elementor', plugins_url( '/assets/tax-carousel/tax-carousel.js', ELEMENTOR_LISTEO ), array(), '1.0.0' );
	// }


	// public function get_script_depends() {
	// 	  $scripts = ['listeo-taxonomy-carousel-elementor'];

	// 	  return $scripts;
	// }
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
		return 'listeo-taxonomy-gallery';
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
		return __('Taxonomy Gallery', 'listeo_elementor');
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
		return 'eicon-image-hotspot';
	}


	// public function get_script_depends() {
	//    return [ 'listeo-taxonomy-carousel-script' ];
	//  }


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
			'item_description',
			[
				'label' => __('Right side Content', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::WYSIWYG,
				'default' => __('<h2>Find Your Ultimate <br> Local Weekend</h2>
					<p>A curated collection of stays and experiences to inspire your next trip.</p>
					<a href="listings-list-with-sidebar.html" class="button margin-top-25">Discover Places</a>', 'listeo_elementor'),
				'placeholder' => __('Type content description here', 'listeo_elementor'),
			]
		);
		$this->add_control(
			'taxonomy',
			[
				'label' => __('Taxonomy', 'listeo_elementor'),
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
					'default' => [],
					'multiple' => true,
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
					'default' => [],
					'multiple' => true,
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
				'max' => 99,
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

		if (! empty($terms) && ! is_wp_error($terms)) {
?>

			<section class="fullwidth taxonomy-gallery-container  padding-top-75 padding-bottom-70">

				<!-- Info Section -->
				<div class="container">
					<div class="row">
						<div class="col-md-6">

							<!-- Infobox -->
							<div class="taxonomy-gallery-text">
								<?php echo $settings['item_description']; ?>
							</div>
							<!-- Infobox / End -->

						</div>
					</div>
				</div>
				<!-- Info Section / End -->

				<div class="gallery-wrap">
					<?php foreach ($terms as $term) {
						$cover_id 	= get_term_meta($term->term_id, '_cover', true);
						$cover 		= wp_get_attachment_image_src($cover_id, 'listeo-blog-post');
					?>
						<?php
						// Create URL with only the child term slug, not full hierarchy
						$taxonomy_base = str_replace('_', '-', $settings['taxonomy']);
						$term_url = home_url($taxonomy_base . '/' . $term->slug . '/');
						?>
						<a href="<?php echo esc_url(get_term_link($term)); ?>" class="item">

							<h3><?php echo $term->name; ?> <?php if ($settings['show_counter'] == 'yes'): ?><span><?php $count = listeo_get_term_post_count($settings['taxonomy'], $term->term_id);
																													printf(_n('%s Listing', '%s Listings', $count, 'listeo_elementor'), $count); ?></span><?php endif; ?></h3>
							<img src="<?php echo $cover[0];  ?>" alt="">
						</a>
					<?php } ?>

				</div>


			</section>
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

		$taxonomies = get_terms($taxonomy, array(
			'hide_empty' => false,
		));

		$options = ['' => ''];

		if (!empty($taxonomies)) :
			foreach ($taxonomies as $term) {
				$options[$term->term_id] = $term->name;
			}
		endif;

		return $options;
	}
}
