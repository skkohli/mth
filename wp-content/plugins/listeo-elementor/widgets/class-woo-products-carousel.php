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
class WooProductsCarousel extends Widget_Base
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
		return 'listeo-woocommerce-products-carousel';
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
		return __('Listeo Products Carousel', 'listeo_elementor');
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
		return 'eicon-carousel';
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

		//             'layout'        =>'standard',


		//             'relation'    => 'OR',
		//         
		//             '_property_type' => '',
		//             '_offer_type'   => '',
		//             'featured'      => '',
		//             'fullwidth'     => '',
		//             'style'         => '',
		//             'autoplay'      => '',
		//             'autoplayspeed'      => '3000',
		//             'from_vs'       => 'no',


		$this->start_controls_section(
			'section_query',
			array(
				'label' => __('Query', 'listeo_elementor'),
			)
		);


		// $this->add_control(
		// 	'post_status',
		// 	[
		// 		'label' => esc_html__( 'Post Status', 'wpce' ),
		// 		'placeholder' => esc_html__( 'Choose Post Status', 'wpce' ),
		// 		'type' => \Elementor\Controls_Manager::SELECT2,
		// 		'label_block' => true,
		// 		'default' => 'publish',
		// 		'multiple' => true,
		// 		'options' => wpce_get_post_status(),
		// 	]
		// );

		// $this->add_control(
		// 	'product_types',
		// 	[
		// 		'label' => esc_html__( 'Product Types', 'wpce' ),
		// 		'placeholder' => esc_html__( 'Choose Products to Include', 'wpce' ),
		// 		'type' => \Elementor\Controls_Manager::SELECT2,
		// 		'label_block' => true,
		// 		'multiple' => true,
		// 		'default' => '',
		// 		'options' => wpce_get_product_types(),
		// 	]
		// );


		$this->add_control(
			'limit',
			[
				'label' => __('Products to display', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => -1,
				'step' => 1,
				'default' => 6,
			]
		);




		$this->add_control(
			'orderby',
			[
				'label' => __('Order by', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'none' =>  __('No order', 'listeo_elementor'),
					'ID' =>  __('Order by post id. ', 'listeo_elementor'),
					'author' =>  __('Order by author.', 'listeo_elementor'),
					'title' =>  __('Order by title.', 'listeo_elementor'),
					'name' =>  __(' Order by post name (post slug).', 'listeo_elementor'),
					'type' =>  __(' Order by post type.', 'listeo_elementor'),
					'date' =>  __(' Order by date.', 'listeo_elementor'),
					'modified' =>  __(' Order by last modified date.', 'listeo_elementor'),
					'parent' =>  __(' Order by post/page parent id.', 'listeo_elementor'),
					'rand' =>  __(' Random order.', 'listeo_elementor'),
					'comment_count' =>  __(' Order by number of commen', 'listeo_elementor'),

				],
			]
		);
		$this->add_control(
			'order',
			[
				'label' => __('Order', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => [
					'DESC' =>  __('Descending', 'listeo_elementor'),
					'ASC' =>  __('Ascending. ', 'listeo_elementor'),


				],
			]
		);

		$this->add_control(
			'product_types',
			[
				'label' => esc_html__('Product Types', 'listeo_elementor'),
				'placeholder' => esc_html__('Choose Products to Include', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => '',
				'options' => wc_get_product_types(),
			]
		);

		$this->add_control(
			'tax-product_cat',
			[
				'label' => __('Show only from  categories', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_terms('product_cat'),

			]
		);


		$this->add_control(
			'exclude_posts',
			[
				'label' => __('Exclude Products', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_posts(),

			]
		);
		$this->add_control(
			'include_posts',
			[
				'label' => __('Include Products', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_posts(),

			]
		);




		// 		$this->add_control(
		// 	'featured',
		// 	[
		// 		'label' => __( 'Show only featured listings', 'listeo_elementor' ),
		// 		'type' => \Elementor\Controls_Manager::SWITCHER,
		// 		'label_on' => __( 'Show', 'your-plugin' ),
		// 		'label_off' => __( 'Hide', 'your-plugin' ),
		// 		'return_value' => 'yes',
		// 		'default' => 'no',
		// 	]
		// );

		$this->end_controls_section();
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __('Settings', 'listeo_elementor'),
			)
		);


		$this->add_control(
			'fullwidth',
			[
				'label' => __('Show fullwidth slider', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'no',
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
		// 'limit'         =>'6',
		//           'layout'        =>'standard',
		//           'orderby'       => 'date',
		//           'order'         => 'DESC',
		//           'tax-listing_category'    => '',
		//           'tax-service_category'    => '',
		//           'tax-rental_category'    => '',
		//           'tax-event_category'    => '',
		//           'relation'    => 'OR',
		//           'exclude_posts' => '',
		//           'include_posts' => '',
		//           'feature'       => '',
		//           'region'        => '',
		//           '_property_type' => '',
		//           '_offer_type'   => '',
		//           'featured'      => '',
		//           'fullwidth'     => '',
		//           'style'         => '',
		//           'autoplay'      => '',
		//           'autoplayspeed'      => '3000',
		//           'from_vs'       => 'no',
		$settings = $this->get_settings_for_display();


		$limit = $settings['limit'] ? $settings['limit'] : 3;
		$orderby = $settings['orderby'] ? $settings['orderby'] : 'title';
		$order = $settings['order'] ? $settings['order'] : 'ASC';
		$exclude_posts = $settings['exclude_posts'] ? $settings['exclude_posts'] : array();
		$include_posts = $settings['include_posts'] ? $settings['include_posts'] : array();


		//var_dump($settings);

		$output = '';
		$randID = rand(1, 99); // Get unique ID for carousel

		$meta_query = array();


		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'limit' => $limit,
			'orderby' => $orderby,
			'order' => $order,

		);

	
		if (isset($settings['product_types']) && is_array($settings['product_types']) && !empty($settings['product_types'])) {
			$args['type'] = $settings['product_types'];
		}

		if (isset($settings['tax-product_cat']) && is_array($settings['tax-product_cat']) && !empty($settings['tax-product_cat'])) {
			$args['category'] = $settings['tax-product_cat'];
		}

		// Exclude categories based on listeo_dokan_exclude_categories option
		$excluded_categories = get_option('listeo_dokan_exclude_categories');
		if (is_array($excluded_categories) && !empty($excluded_categories)) {
			if (!isset($args['tax_query'])) {
				$args['tax_query'] = array();
			}
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $excluded_categories,
				'operator' => 'NOT IN'
			);
		}

		if (!empty($exclude_posts)) {
			$exl = is_array($exclude_posts) ? $exclude_posts : array_filter(array_map('trim', explode(',', $exclude_posts)));
			// Remove empty values and reindex array
			$exl = array_values(array_filter($exl));
			if (!empty($exl)) {
				$args['exclude'] = $exl;
			}
		}
		// Exclude Dokan internal products (Reverse Withdrawal Payment, etc.)
		// These products are used internally by Dokan and should not be displayed publicly
		$dokan_internal_product = 0;

		// Try Dokan's Helper class first (Dokan 3.5.1+)
		if (class_exists('WeDevs\Dokan\ReverseWithdrawal\Helper')) {
			$dokan_internal_product = \WeDevs\Dokan\ReverseWithdrawal\Helper::get_reverse_withdrawal_base_product();
		}
		// Fallback: Get product ID directly from option
		elseif (function_exists('get_option')) {
			$dokan_internal_product = (int) get_option('dokan_reverse_withdrawal_product_id', 0);
		}

		if ($dokan_internal_product > 0) {
			if (!isset($args['exclude'])) {
				$args['exclude'] = array();
			}
			$args['exclude'][] = $dokan_internal_product;
		}

		// Exclude Listeo ad campaign product
		// This product is used internally for ads and should not be displayed in product carousels
		$ad_campaign_product_id = (int) get_option('listeo_ad_campaign_product_id', 0);
		if ($ad_campaign_product_id > 0) {
			if (!isset($args['exclude'])) {
				$args['exclude'] = array();
			}
			$args['exclude'][] = $ad_campaign_product_id;
		}



		if (!empty($include_posts)) {
			$inc = is_array($include_posts) ? $include_posts : array_filter(array_map('trim', explode(',', $include_posts)));
			// Remove empty values and reindex array
			$inc = array_values(array_filter($inc));
			if (!empty($inc)) {
				$args['include'] = $inc;
			}
		}



		$i = 0;
		$args['exclude_listing_booking'] = 'true';
		$products = wc_get_products($args);
		
		if (!class_exists('Listeo_Core_Template_Loader')) {
			return;
		}
		$template_loader = new \Listeo_Core_Template_Loader;

		ob_start();
		if ($settings['fullwidth']) { ?>
			<!-- Carousel / Start -->
			<div data-slides="6" class="simple-fw-slick-carousel listeo-products-slider  dots-nav" <?php if ($settings['autoplay'] == 'yes') { ?> data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
			<?php } else { ?>
				<!-- Carousel / Start -->
				<div data-slides="4" class="simple-slick-carousel  listeo-products-slider dots-nav" <?php if ($settings['autoplay'] == 'yes') { ?> data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
					<?php }
				if ($products) {
					$count = 0;
					foreach ($products as $product) {
						$count++;
						$thumbnail_id = $product->get_image_id();
						// $product = wc_get_product(get_the_ID());

					?>
						<div class="fw-carousel-item">

							<div <?php post_class('', $product->get_id()); ?>>
								<div class="mediaholder">
									<a href="<?php echo get_permalink($product->get_id()); ?>">
										<?php
										if (has_post_thumbnail($product->get_id())) {
											$attachment_count = count($product->get_gallery_image_ids());
											$gallery          = $attachment_count > 0 ? '[product-gallery]' : '';
											$props            = wc_get_product_attachment_props(get_post_thumbnail_id(), $product->get_id());
											$image            = get_the_post_thumbnail($product->get_id(), apply_filters('single_product_large_thumbnail_size', 'shop_single'), array(
												'title'	 => $props['title'],
												'alt'    => $props['alt'],
											));
											$size = 'listeo_core-avatar';
											$image_size = apply_filters('single_product_archive_thumbnail_size', $size);
											echo $product->get_image($image_size);
										}
										?>
									</a>
									<?php $link 	= $product->add_to_cart_url();
									$label 	= apply_filters('add_to_cart_text', esc_html__('Add to cart', 'listeo'));
									?>
									<a href="<?php echo esc_url($link); ?>" class="button"><i class="fa fa-shopping-cart"></i> <?php echo esc_html($label); ?></a>
								</div>
								<section>
									<span class="product-category">
										<?php
										$product_cats = wp_get_post_terms($product->get_id(), 'product_cat');
										if ($product_cats && !is_wp_error($product_cats)) {
											$single_cat = array_shift($product_cats);
											echo esc_html($single_cat->name);
										} ?>
									</span>

									<h5><a href="<?php echo get_permalink($product->get_id()); ?>"><?php echo $product->get_title(); ?></a></h5>

									<?php echo $product->get_price_html(); ?>
								</section>
							</div>



						</div>
				<?php
					}
				}



				?>
				</div>
		<?php wp_reset_postdata();
		wp_reset_query();

		echo ob_get_clean();
	}


	protected function get_terms($taxonomy)
	{
		$taxonomies = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

		$options = ['' => ''];

		if (!empty($taxonomies)) :
			foreach ($taxonomies as $taxonomy) {
				if ($taxonomy) {
					$options[$taxonomy->slug] = $taxonomy->name;
				}
			}
		endif;

		return $options;
	}

	protected function get_posts()
	{
		$posts = get_posts(
			array(
				'numberposts' => 199,
				'post_type' => 'product',
				'suppress_filters' => true
			)
		);

		$options = ['' => ''];

		if (!empty($posts)) :
			foreach ($posts as $post) {
				$options[$post->ID] = get_the_title($post->ID);
			}
		endif;

		return $options;
	}
}
