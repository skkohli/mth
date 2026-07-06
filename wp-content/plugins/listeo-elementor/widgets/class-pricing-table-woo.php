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
class PricingTableWoo extends Widget_Base
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
		return 'listeo-pricingtable-woocommerce';
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
		return __('Pricing Table WooCommerce', 'listeo_elementor');
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
				'label' => __('Content', 'listeo_elementor'),
			)
		);


		$this->add_control(
			'orderby',
			[
				'label' => __('Order by', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'price' 		=>  __('Price', 'listeo_elementor'),
					'price-desc' 	=> __('Price desc', 'listeo_elementor'),
					'rating' 		=> __('Rating', 'listeo_elementor'),
					'title' 		=> __('Title', 'listeo_elementor'),
					'popularity' 	=> __('Popularity', 'listeo_elementor'),
					'random' 		=> __('Random', 'listeo_elementor'),

				],
			]
		);
		$this->add_control(
			'columns_per_row',
			[
				'label' => __('Columns in a row', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 6,
				'step' => 1,
				'default' => 3,
			]
		);

		$this->add_control(
			'buttonlink',
			[
				'label' => __('Option URL for add Listing overide', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => __('https://your-link.com', 'listeo_elementor'),
				'show_external' => true,
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
				],
			]
		);

		// add control for button text
		$this->add_control(
			'buttontext',
			[
				'label' => __('Pricing table Button Text', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __('Add Listing', 'listeo_elementor'),
			]
		);

		// add a control that lets you select products that are set as listing_package or listing_package_subscription type
		$this->add_control(
			'listing_package',
			[
				'label' => __('Select Listing Packages', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $this->get_listing_packages(),
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

		$target = $settings['buttonlink']['is_external'] ? ' target="_blank"' : '';
		$nofollow = $settings['buttonlink']['nofollow'] ? ' rel="nofollow"' : '';
		$button_text = $settings['buttontext'];
		ob_start();


		$args = array(
			'post_type'  => 'product',
			'post_status' => 'publish',
			'limit'      => 999,
			'tax_query'  => array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => array('listing_package', 'listing_package_subscription')
				)
			)
		);
		if(!empty($settings['listing_package'])){
			$args['post__in'] = $settings['listing_package'];
		}
		switch ($settings['orderby']) {
			case 'price':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order'] = 'asc';
				break;

			case 'price-desc':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order'] = 'desc';
				break;

			case 'rating':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating';
				$args['order'] = 'desc';
				break;

			case 'popularity':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = 'total_sales';
				$args['order'] = 'desc';
				break;

			case 'random':
				$args['orderby'] = 'rand';
				$args['order'] = '';
				$args['meta_key'] = '';
				break;
			case 'title':
				$args['orderby'] = 'title';
				$args['order'] = 'ASC';
				$args['meta_key'] = '';
				break;
		}

		$products = new \WP_Query($args); ?>

		<div class="new-pricing-packages-container margin-top-30">

			<?php
			$counter = 1;
			$single_buy_products = get_option('listeo_buy_only_once');
			while ($products->have_posts()) : $products->the_post();
				$single_buy_products = get_option('listeo_buy_only_once');

				$product = wc_get_product(get_post()->ID);

				if (!$product->is_type(array('listing_package', 'listing_package_subscription')) || !$product->is_purchasable()) {
					continue;
				}
				if ($single_buy_products) {
					$user = wp_get_current_user();
					if (in_array($product->get_id(), $single_buy_products)  && wc_customer_bought_product($user->user_email, $user->ID, $product->get_id())) {
						continue;
					}
				}
			?>
				<div class="pricing-package  <?php echo ($product->is_featured()) ? 'best-value-plan' : ''; ?>">
					<div class="pricing-package-header">
						<h4><?php echo $product->get_title(); ?></h4>
						<?php if ($product->is_featured()) { ?><span><?php esc_html_e('Best Value', 'listeo_elementor') ?></span> <?php } ?>
					</div>
					<?php if ($product->get_short_description()) { ?><div class="pricing-package-text"><?php echo $product->get_short_description(); ?></div><?php } ?>

					<div class="pricing-package-price">
						<strong><?php echo $product->get_price_html(); ?></strong>
					</div>
					<div class="pricing-package-details">
						<?php
						//get product meta field _package_subtitle
						$package_subtitle = get_post_meta($product->get_id(), '_package_subtitle', true);
						if ($package_subtitle) {
							echo '<h6>' . $package_subtitle . '</h6>';
						} else { ?>
							<h6><?php echo $product->get_title(); ?> <?php esc_html_e('features', 'listeo_elementor') ?>:</h6>
						<?php }
						?>
						<ul class="plan-features-auto-wc">
							<li>
								<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
									<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
										<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
										<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
									</g>
								</svg>

								<?php
								$listingslimit = $product->get_limit();
								if (!$listingslimit) {

									esc_html_e('Unlimited number of listings', 'listeo_elementor');
								} else { ?>

									<?php esc_html_e('This plan includes ', 'listeo_elementor');
									printf(_n('%d listing', '%s listings', $listingslimit, 'listeo_elementor') . ' ', $listingslimit); ?>

								<?php } ?>
							</li>
							<li>
								<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
									<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
										<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
										<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
									</g>
								</svg>

								<?php $duration = $product->get_duration();
								if ($duration > 0) :
									esc_html_e('Listings are visible ', 'listeo_elementor');
									printf(_n('for %s day', 'for %s days', $product->get_duration(), 'listeo_elementor'), $product->get_duration());
								else :
									esc_html_e('Unlimited availability of listings', 'listeo_elementor');
								endif; ?>
							</li>
						</ul>
						<ul>


							<?php if (get_option('listeo_populate_listing_package_options')) : ?>
								<?php
								$bookingOptions = $product->has_listing_booking();
								if ($bookingOptions) : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Booking Module enabled', 'listeo_elementor');  ?>
									</li>
								<?php endif; ?>


								<?php
								$reviewsOptions = $product->has_listing_reviews();
								if ($reviewsOptions) : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Reviews Module enabled', 'listeo_elementor');  ?>
									</li>
								<?php endif; ?>

								<?php
								$sociallinksOptions = $product->has_listing_social_links();
								if ($sociallinksOptions) : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Social Links Module enabled', 'listeo_elementor');  ?>
									</li>
								<?php endif; ?>

								<?php
								$openinghoursOptions = $product->has_listing_opening_hours();
								if ($openinghoursOptions) : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Opening Hours Module enabled', 'listeo_elementor');  ?>
									</li>
								<?php endif; ?>

								<?php
								$vidosOptions = $product->has_listing_video();
								if ($vidosOptions) : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Video option enabled', 'listeo_elementor');  ?>
									</li>
								<?php endif; ?>

								<?php
								$couponsOptions = $product->has_listing_coupons();
								if ($couponsOptions == 'yes') : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Coupons option enabled', 'listeo_core');  ?>
									</li>
								<?php endif; ?>
								<?php
								$faqOptions = $product->has_listing_faq();
								if ($faqOptions == 'yes') : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('FAQ option enabled', 'listeo_core');  ?>
									</li>
								<?php endif; ?>
								<?php
								$pricingMenuOptions = $product->has_listing_pricing_menu();
								if ($pricingMenuOptions == 'yes') : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Pricing Menu Module enabled', 'listeo_core');  ?>
									</li>
								<?php endif; ?>
								<?php
								$galleryOptions = $product->has_listing_gallery();
								if ($galleryOptions == 'yes') : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php esc_html_e('Gallery Module enabled', 'listeo_core');  ?>
									</li>
								<?php endif; ?>
								<?php
								$gallery_limitOptions = $product->get_option_gallery_limit();
								if ($gallery_limitOptions) : ?>
									<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
											<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
												<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
												<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
											</g>
										</svg>
										<?php printf(esc_html__('Maximum  %s images in gallery', 'listeo_core'), $product->get_option_gallery_limit());  ?>
									</li>
								<?php endif; ?>
							<?php endif; ?>
							<?php
							$custom_listing_fields = get_post_meta($product->get_id(), 'package_items_group', true);
							if ($custom_listing_fields) {


								foreach ($custom_listing_fields as $key => $entry) {
									$title = esc_html($entry['title']);

									if (!empty($title)) { ?>
										<li class="custom_listing_field"><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg>
											<?php echo esc_html($title); ?>
										</li>

							<?php }
								}
							}
							?>
						</ul>
						<?php

						echo $product->get_description();

						?>
					</div>
					<div class="pricing-package-select">
						<?php $link   = $product->add_to_cart_url();
						$label  = $button_text;

						if (!empty($settings['buttonlink']['url'])) {
							echo '<a class="button" href="' . $settings['buttonlink']['url'] . '"' . $target . $nofollow . '><i class="fa fa-shopping-cart"></i>' . esc_html($label) . '</a>';
						} else { ?>
							<a href="<?php echo esc_url($link); ?>" class="button"><i class="fa fa-shopping-cart"></i> <?php echo esc_html($label); ?></a>

						<?php } ?>
					</div>
				</div>
				<?php if (($counter % $settings['columns_per_row']) == 0) { ?>
		</div>
		<div class="pricing-container margin-top-30">
		<?php } ?>
	<?php
				$counter++;
			endwhile; ?>
		</div>
<?php $pricing__output =  ob_get_clean();
		wp_reset_postdata();
		echo $pricing__output;
	}

	/**
	 * Get listing packages.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected function get_listing_packages(){
		$packages = array();
		$args = array(
			'post_type'  => 'product',
			'post_status' => 'publish',
			'limit'      => 999,
			'tax_query'  => array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => array('listing_package', 'listing_package_subscription')
				)
			)
		);
		$products = new \WP_Query($args);
		while ($products->have_posts()) : $products->the_post();
			$product = wc_get_product(get_post()->ID);
			$packages[get_the_ID()] = get_the_title();
		endwhile;
		wp_reset_postdata();
		return $packages;
	}

}
