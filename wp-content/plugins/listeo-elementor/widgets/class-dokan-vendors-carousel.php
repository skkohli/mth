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
class DokanVendordsCarousel extends Widget_Base
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
		return 'listeo-dokan-vendords-carousel';
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
		return __('Listeo Dokan Vendors Carousel', 'listeo_elementor');
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
		return 'eicon-carousel-loop';
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
				'label' => __('Vendors to display', 'listeo_elementor'),
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
			'text',
			[
				'label' => __('Search string', 'listeo_elementor'),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => esc_html__('Show vendors matching search', 'listeo_elementor'),



			]
		);




		$this->add_control(
			'store_id',
			[
				'label' => __('Include Only Stores', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_stores(),

			]
		);




		$this->add_control(
			'with_products_only',
			[
				'label' => __('Show only stores with products', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'listeo_elementor'),
				'label_off' => __('Hide', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'no',
			]
		);

		$this->add_control(
			'featured',
			[
				'label' => __('Show only featured listings', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'listeo_elementor'),
				'label_off' => __('Hide', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'no',
			]
		);

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
				'label_on' => __('Show', 'listeo_elementor'),
				'label_off' => __('Hide', 'listeo_elementor'),
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
		if (!function_exists('dokan_get_sellers')) {
			echo "Please install Dokan plugin to use Vendors element";
			return;
		}
		$settings = $this->get_settings_for_display();


		$limit = $settings['limit'] ? $settings['limit'] : 3;


		//settings




		$seller_args = array(
			'number' => $limit,
			'order'  => 'DESC',
		);

		$_get_data = wp_unslash($_GET);

		// if search is enabled, perform a search
		if (!empty($settings['search'])) {

			$seller_args['meta_query'] = [
				[
					'key'     => 'dokan_store_name',
					'value'   => wc_clean($settings['search']),
					'compare' => 'LIKE',
				],
			];
		}

		if ('yes' === $settings['featured']) {
			$seller_args['featured'] = 'yes';
		}

		if (!empty($settings['category'])) {
			$seller_args['store_category_query'][] = array(
				'taxonomy' => 'store_category',
				'field'    => 'slug',
				'terms'    => explode(',', $settings['category']),
			);
		}

		if (!empty($settings['order'])) {
			$seller_args['order'] = $settings['order'];
		}

		if (!empty($settings['orderby'])) {
			$seller_args['orderby'] = $settings['orderby'];
		}

		if (!empty($settings['with_products_only']) && 'yes' === $settings['with_products_only']) {
			$seller_args['has_published_posts'] = ['product'];
		}

		if (!empty($settings['store_id'])) {
			
			$seller_args['include'] =$settings['store_id'];
		}
		if(!function_exists('dokan_get_sellers')){
			return;
		}
		$sellers = dokan_get_sellers(apply_filters('dokan_seller_listing_args', $seller_args, $_GET));

		/**
		 * Filter for store listing args
		 *
		 * @since 2.4.9
		 */



		ob_start(); ?>
		<div id="dokan-seller-listing-wrap" class="grid-view listeo-dokan-widget">
			<div class="seller-listing-content">
				<?php
				if ($settings['fullwidth']) { ?>
					<!-- Carousel / Start -->
					<div class="simple-fw-slick-carousel listeo-vendors-slider  dots-nav" <?php if ($settings['autoplay'] == 'yes') { ?> data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
					<?php } else { ?>
						<!-- Carousel / Start -->
						<div class="simple-slick-carousel  listeo-vendors-slider dots-nav" <?php if ($settings['autoplay'] == 'yes') { ?> data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
						<?php }  ?>

						<?php
						foreach ($sellers['users'] as $seller) {
							$vendor            = dokan()->vendor->get($seller->ID);
							$store_banner_id   = $vendor->get_banner_id();
							$store_name        = $vendor->get_shop_name();
							$store_url         = $vendor->get_shop_url();
							$store_rating      = $vendor->get_rating();
							$is_store_featured = $vendor->is_featured();
							$store_phone       = $vendor->get_phone();
							$store_info        = dokan_get_store_info($seller->ID);
							$store_address     = dokan_get_seller_short_address($seller->ID);
							$store_banner_url  = $store_banner_id ? wp_get_attachment_image_src($store_banner_id, 'full') : DOKAN_PLUGIN_ASSEST . '/images/default-store-banner.png';

							$show_store_open_close    = dokan_get_option('store_open_close', 'dokan_appearance', 'on');
							$dokan_store_time_enabled = isset($store_info['dokan_store_time_enabled']) ? $store_info['dokan_store_time_enabled'] : '';
							$store_open_is_on = ('on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled && !$is_store_featured) ? 'store_open_is_on' : '';
						?>
							<div class=" fw-carousel-item ">
								<ul class="dokan-seller-wrap">


									<li class=" dokan-single-seller <?php echo (!$store_banner_id) ? 'no-banner-img' : ''; ?>">
										<a href="<?php echo esc_url($store_url); ?>">
											<div class="store-wrapper">
												<div class="store-header">
													<div class="store-banner">

														<img src="<?php echo is_array($store_banner_url) ? esc_attr($store_banner_url[0]) : esc_attr($store_banner_url); ?>">

													</div>
												</div>

												<div class="store-content <?php echo !$store_banner_id ? esc_attr('default-store-banner') : '' ?>">
													<div class="store-data-container">
														<div class="featured-favourite">
															<?php if ($is_store_featured) : ?>
																<div class="featured-label"><?php esc_html_e('Featured', 'dokan-lite'); ?></div>
															<?php endif ?>

															<?php do_action('dokan_seller_listing_after_featured', $seller, $store_info); ?>
														</div>

														<?php if ('on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled) : ?>
															<?php if (dokan_is_store_open($seller->ID)) { ?>
																<span class="dokan-store-is-open-close-status dokan-store-is-open-status" title="<?php esc_attr_e('Store is Open', 'dokan-lite'); ?>"><?php esc_html_e('Open', 'dokan-lite'); ?></span>
															<?php } else { ?>
																<span class="dokan-store-is-open-close-status dokan-store-is-closed-status" title="<?php esc_attr_e('Store is Closed', 'dokan-lite'); ?>"><?php esc_html_e('Closed', 'dokan-lite'); ?></span>
															<?php } ?>
														<?php endif ?>

														<div class="store-data <?php echo esc_attr($store_open_is_on); ?>">
															<h2><?php echo esc_html($store_name); ?></h2>


															<?php $rating = dokan_get_readable_seller_rating($seller->ID); ?>
															<div class="dokan-store-rating <?php if (!strpos($rating, 'seller-rating') == '<') {
																								echo "no-reviews-rating";
																							} ?>">
																<i class="fa fa-star"></i>
																<?php echo wp_kses_post($rating); ?>
															</div>


															<?php if (!dokan_is_vendor_info_hidden('address') && $store_address) : ?>
																<?php
																$allowed_tags = array(
																	'span' => array(
																		'class' => array(),
																	),
																	'br' => array()
																);
																?>
																<p class="store-address"><?php echo wp_kses($store_address, $allowed_tags); ?></p>
															<?php endif ?>

															<?php if (!dokan_is_vendor_info_hidden('phone') && $store_phone) { ?>
																<p class="store-phone">
																	<i class="fa fa-phone" aria-hidden="true"></i> <?php echo esc_html($store_phone); ?>
																</p>
															<?php } ?>

															<?php do_action('dokan_seller_listing_after_store_data', $seller, $store_info); ?>
														</div>
													</div>
												</div>

												<div class="store-footer">

													<?php $rating = dokan_get_readable_seller_rating($seller->ID); ?>
													<div class="dokan-store-rating <?php if (!strpos($rating, 'seller-rating') == '<') {
																						echo "no-reviews-rating";
																					} ?>">
														<i class="fa fa-star"></i>
														<?php echo wp_kses_post($rating); ?>
													</div>

													<div class="seller-avatar">

														<img src="<?php echo esc_url($vendor->get_avatar()) ?>" alt="<?php echo esc_attr($vendor->get_shop_name()) ?>" size="150">

													</div>

													<span class="dashicons dashicons-arrow-right-alt2 dokan-btn-theme dokan-btn-round"></span>

													<?php do_action('dokan_seller_listing_footer_content', $seller, $store_info); ?>
												</div>
											</div>
										</a>
									</li>
								</ul>
							</div>

						<?php } ?>

						</div> <!-- .dokan-seller-wrap -->


					</div>
			</div>
	<?php echo ob_get_clean();
	}


	protected function get_terms($taxonomy)
	{
		$taxonomies = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

		$options = ['' => ''];

		if (!is_wp_error($taxonomies)) :
			foreach ($taxonomies as $taxonomy) {
				if ($taxonomy) {
					$options[$taxonomy->slug] = $taxonomy->name;
				}
			}
		endif;

		return $options;
	}

	protected function get_stores()
	{
		if (function_exists('dokan_get_sellers')) {
		$seller_args = array(
			'number' => 99,
			'order'  => 'DESC',
		);
		$sellers = dokan_get_sellers(apply_filters('dokan_seller_listing_args', $seller_args, $_GET));
		

		$options = ['' => ''];

		if (!empty($sellers)) :
			foreach ($sellers['users'] as $seller) {
				$vendor            = dokan()->vendor->get($seller->ID);
				$options[$seller->ID] = $vendor->get_shop_name();
			}
		endif;

		return $options;
	 }
	}
}
