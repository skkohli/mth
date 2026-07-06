<?php
/**
 * Coupons Display Widget.
 *
 * @category   Class
 * @package    ElementorListeo
 * @subpackage WordPress
 * @author     Purethemes
 * @copyright  2025 Purethemes
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @since      1.0.0
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Coupons Display widget class.
 *
 * @since 1.0.0
 */
class CouponsDisplay extends Widget_Base {

	/**
	 * Retrieve the widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'listeo-coupons-display';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget title.
	 */
	public function get_title() {
		return __( 'Coupons Display', 'listeo_elementor' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-price-list';
	}

	/**
	 * Retrieve the list of categories the widget belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return array( 'listeo' );
	}

	/**
	 * Get style dependencies.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Widget style dependencies.
	 */
	public function get_style_depends() {
		return [ 'listeo-coupons-display-style' ];
	}

	/**
	 * Get script dependencies.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Widget script dependencies.
	 */
	public function get_script_depends() {
		return [ 'listeo-coupons-display-script' ];
	}

	/**
	 * Register the widget controls.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function register_controls() {
		$this->register_query_controls();
		$this->register_display_controls();
		$this->register_carousel_controls();
	}

	/**
	 * Register query controls.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_query_controls() {
		$this->start_controls_section(
			'query_section',
			[
				'label' => __( 'Query', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'coupons_limit',
			[
				'label' => __( 'Number of Coupons', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 50,
				'default' => 6,
			]
		);

		$this->add_control(
			'author_filter',
			[
				'label' => __( 'Filter by Author', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => [
					'all' => __( 'All Users', 'listeo_elementor' ),
					'current' => __( 'Current User', 'listeo_elementor' ),
					'specific' => __( 'Specific User', 'listeo_elementor' ),
				],
			]
		);

		$this->add_control(
			'specific_author',
			[
				'label' => __( 'User ID', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'condition' => [
					'author_filter' => 'specific',
				],
			]
		);

		// Get listing categories for filter
		$categories = get_terms([
			'taxonomy' => 'listing_category',
			'hide_empty' => true,
			'orderby' => 'name',
			'order' => 'ASC',
		]);

		$category_options = ['0' => __( 'All Categories', 'listeo_elementor' )];
		if (!is_wp_error($categories) && !empty($categories)) {
			foreach ($categories as $category) {
				$category_options[$category->term_id] = $category->name;
			}
		}

		$this->add_control(
			'filter_by_category',
			[
				'label' => __( 'Filter by Listing Category', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $category_options,
				'default' => ['0'],
				'description' => __( 'Show coupons only from listings in selected categories', 'listeo_elementor' ),
			]
		);

		// Get listings for filter
		$listings = get_posts([
			'post_type' => 'listing',
			'posts_per_page' => 100, // Limit for performance
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'ASC',
		]);

		$listing_options = ['0' => __( 'All Listings', 'listeo_elementor' )];
		foreach ($listings as $listing) {
			$listing_options[$listing->ID] = $listing->post_title;
		}

		$this->add_control(
			'filter_by_listing',
			[
				'label' => __( 'Filter by Specific Listings', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $listing_options,
				'default' => ['0'],
				'description' => __( 'Show coupons only from selected specific listings', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'orderby',
			[
				'label' => __( 'Order By', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'date' => __( 'Date Created', 'listeo_elementor' ),
					'title' => __( 'Coupon Code', 'listeo_elementor' ),
					'amount' => __( 'Discount Amount', 'listeo_elementor' ),
					'rand' => __( 'Random', 'listeo_elementor' ),
				],
			]
		);

		$this->add_control(
			'order',
			[
				'label' => __( 'Order', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => [
					'ASC' => __( 'Ascending', 'listeo_elementor' ),
					'DESC' => __( 'Descending', 'listeo_elementor' ),
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register display controls.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_display_controls() {
		$this->start_controls_section(
			'display_section',
			[
				'label' => __( 'Display Settings', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'display_type',
			[
				'label' => __( 'Display Type', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => [
					'grid' => __( 'Grid', 'listeo_elementor' ),
					'carousel' => __( 'Carousel', 'listeo_elementor' ),
					'tabs' => __( 'Grid with Tabs', 'listeo_elementor' ),
				],
			]
		);

		$this->add_control(
			'grid_columns',
			[
				'label' => __( 'Grid Columns', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => '3',
				'options' => [
					'2' => __( '2 Columns', 'listeo_elementor' ),
					'3' => __( '3 Columns', 'listeo_elementor' ),
					'4' => __( '4 Columns', 'listeo_elementor' ),
				],
				'condition' => [
					'display_type' => ['grid', 'tabs'],
				],
			]
		);

		$this->add_control(
			'tab_settings_heading',
			[
				'label' => __( 'Tab Navigation Settings', 'listeo_elementor' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'display_type' => 'tabs',
				],
			]
		);

		$this->add_control(
			'show_all_tab',
			[
				'label' => __( 'Show "All" Tab', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'description' => __( 'Display an "All" tab that shows coupons from all categories', 'listeo_elementor' ),
				'condition' => [
					'display_type' => 'tabs',
				],
			]
		);

		$this->add_control(
			'all_tab_label',
			[
				'label' => __( '"All" Tab Label', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'All Coupons', 'listeo_elementor' ),
				'condition' => [
					'display_type' => 'tabs',
					'show_all_tab' => 'yes',
				],
			]
		);

		$this->add_control(
			'initial_display_count',
			[
				'label' => __( 'Initial Display Count', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 4,
				'min' => 1,
				'max' => 20,
				'description' => __( 'Number of coupons to show initially (before "Show More" button)', 'listeo_elementor' ),
				'condition' => [
					'display_type' => 'tabs',
				],
			]
		);

		$this->add_control(
			'show_more_increment',
			[
				'label' => __( 'Show More Increment', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 4,
				'min' => 1,
				'max' => 10,
				'description' => __( 'How many additional coupons to show when "Show More" is clicked', 'listeo_elementor' ),
				'condition' => [
					'display_type' => 'tabs',
				],
			]
		);

		$this->add_control(
			'show_more_text',
			[
				'label' => __( 'Show More Button Text', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Show More', 'listeo_elementor' ),
				'condition' => [
					'display_type' => 'tabs',
				],
			]
		);

		$this->add_control(
			'show_elements_heading',
			[
				'label' => __( 'Show/Hide Elements', 'listeo_elementor' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'show_listing_image',
			[
				'label' => __( 'Show Listing Image', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_listing_name',
			[
				'label' => __( 'Show Listing Name', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_listing_category',
			[
				'label' => __( 'Show Listing Category', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);





		$this->add_control(
			'show_coupon_code',
			[
				'label' => __( 'Show Coupon Code', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_description',
			[
				'label' => __( 'Show Description', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_expiry_date',
			[
				'label' => __( 'Show Expiry Date', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_button',
			[
				'label' => __( 'Show Button', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'button_text',
			[
				'label' => __( 'Button Text', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Get Code', 'listeo_elementor' ),
				'condition' => [
					'show_button' => 'yes',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register carousel controls.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_carousel_controls() {
		$this->start_controls_section(
			'carousel_section',
			[
				'label' => __( 'Carousel Settings', 'listeo_elementor' ),
				'condition' => [
					'display_type' => 'carousel',
				],
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label' => __( 'Autoplay', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'no',
			]
		);

		$this->add_control(
			'autoplay_speed',
			[
				'label' => __( 'Autoplay Speed (ms)', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 3000,
				'condition' => [
					'autoplay' => 'yes',
				],
			]
		);

		$this->add_control(
			'slides_to_show',
			[
				'label' => __( 'Slides to Show (Desktop)', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 6,
				'default' => 3,
			]
		);

		$this->add_control(
			'slides_to_show_tablet',
			[
				'label' => __( 'Slides to Show (Tablet)', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 4,
				'default' => 2,
			]
		);

		$this->add_control(
			'slides_to_show_mobile',
			[
				'label' => __( 'Slides to Show (Mobile)', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 2,
				'default' => 1,
			]
		);

		$this->add_control(
			'slides_to_scroll',
			[
				'label' => __( 'Slides to Scroll', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 6,
				'default' => 1,
			]
		);





		$this->end_controls_section();
	}

	/**
	 * Register style controls.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_style_controls() {
		// Card Style
		$this->start_controls_section(
			'card_style_section',
			[
				'label' => __( 'Card Style', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'card_background',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .listeo-coupon-card' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'card_padding',
			[
				'label' => __( 'Padding', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .listeo-coupon-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'card_border_radius',
			[
				'label' => __( 'Border Radius', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .listeo-coupon-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'card_box_shadow',
				'selector' => '{{WRAPPER}} .listeo-coupon-card',
			]
		);

		$this->end_controls_section();

		// Typography
		$this->start_controls_section(
			'typography_section',
			[
				'label' => __( 'Typography', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'listing_name_typography',
				'label' => __( 'Listing Name', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .coupon-company-name',
			]
		);

		$this->add_control(
			'listing_name_color',
			[
				'label' => __( 'Listing Name Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon-company-name' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'coupon_code_typography',
				'label' => __( 'Coupon Code', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .coupon-code',
			]
		);

		$this->add_control(
			'coupon_code_color',
			[
				'label' => __( 'Coupon Code Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon-code' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		// Button Style
		$this->start_controls_section(
			'button_style_section',
			[
				'label' => __( 'Button Style', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_button' => 'yes',
				],
			]
		);

		$this->add_control(
			'button_background',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon-get-code-btn' => 'background: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label' => __( 'Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon-get-code-btn' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_hover_background',
			[
				'label' => __( 'Hover Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon-get-code-btn:hover' => 'background: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_hover_text_color',
			[
				'label' => __( 'Hover Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon-get-code-btn:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Get coupons query.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param array $settings Widget settings.
	 * @return \WP_Query
	 */
	private function get_coupons_query( $settings ) {
		// Ensure settings have safe defaults for removed options
		$settings = wp_parse_args( $settings, [
			'coupons_limit' => 6,
			'orderby' => 'date',
			'order' => 'DESC',
		]);
		
		// Always enable validation (removed from UI but always active)
		$settings['validate_listing_settings'] = 'yes';
		
		// Set defaults for removed display controls (always enabled)
		$settings['show_verified_badge'] = 'yes';
		$settings['show_discount_badge'] = 'yes';
		$settings['image_source'] = 'coupon_first';
		$settings['button_action'] = 'link';

		$args = [
			'post_type' => 'shop_coupon',
			'post_status' => 'publish',
			'posts_per_page' => $settings['coupons_limit'],
			'orderby' => $settings['orderby'],
			'order' => $settings['order'],
			'meta_query' => [],
		];

		// Author filter
		if ( $settings['author_filter'] === 'current' ) {
			$args['author'] = get_current_user_id();
		} elseif ( $settings['author_filter'] === 'specific' && ! empty( $settings['specific_author'] ) ) {
			$args['author'] = $settings['specific_author'];
		}

		// Filter by category - get listings from selected categories first
		$category_listing_ids = [];
		if ( ! empty( $settings['filter_by_category'] ) && ! in_array( '0', $settings['filter_by_category'] ) ) {
			$category_listings = get_posts([
				'post_type' => 'listing',
				'post_status' => 'publish',
				'posts_per_page' => 199,
				'fields' => 'ids',
				'tax_query' => [
					[
						'taxonomy' => 'listing_category',
						'field' => 'term_id',
						'terms' => $settings['filter_by_category'],
						'operator' => 'IN',
					],
				],
			]);
			
			if ( ! empty( $category_listings ) ) {
				$category_listing_ids = $category_listings;
			}
		}

		// Filter by specific listing
		$specific_listing_ids = [];
		if ( ! empty( $settings['filter_by_listing'] ) && ! in_array( '0', $settings['filter_by_listing'] ) ) {
			$specific_listing_ids = $settings['filter_by_listing'];
		}

		// Combine category and specific listing filters
		$filtered_listing_ids = [];
		if ( ! empty( $category_listing_ids ) && ! empty( $specific_listing_ids ) ) {
			// If both filters are set, show intersection
			$filtered_listing_ids = array_intersect( $category_listing_ids, $specific_listing_ids );
		} elseif ( ! empty( $category_listing_ids ) ) {
			// Only category filter is set
			$filtered_listing_ids = $category_listing_ids;
		} elseif ( ! empty( $specific_listing_ids ) ) {
			// Only specific listing filter is set
			$filtered_listing_ids = $specific_listing_ids;
		}

		// Apply listing filter to coupon query
		if ( ! empty( $filtered_listing_ids ) ) {
			// Build meta query for listing_ids field (comma-separated values)
			$listing_meta_queries = [];
			foreach ( $filtered_listing_ids as $listing_id ) {
				$listing_meta_queries[] = [
					'key' => 'listing_ids',
					'value' => $listing_id,
					'compare' => 'REGEXP',
				];
			}
			
			if ( count( $listing_meta_queries ) > 1 ) {
				$or_query = [ 'relation' => 'OR' ];
				$or_query = array_merge( $or_query, $listing_meta_queries );
				$args['meta_query'][] = $or_query;
			} else {
				$args['meta_query'] = array_merge( $args['meta_query'], $listing_meta_queries );
			}
		}

		// Ensure coupons have associated listings
		$args['meta_query'][] = [
			'key' => 'listing_ids',
			'value' => '',
			'compare' => '!=',
		];

		// Handle ordering by amount
		if ( $settings['orderby'] === 'amount' ) {
			$args['meta_key'] = 'coupon_amount';
			$args['orderby'] = 'meta_value_num';
		}

		$query = new \WP_Query( $args );

		// Apply validation filtering if enabled
		if ( $settings['validate_listing_settings'] === 'yes' && $query->have_posts() ) {
			$query = $this->filter_validated_coupons( $query, $settings, $filtered_listing_ids );
		}

		return $query;
	}

	/**
	 * Format discount display with coupon code.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return string
	 */
	private function format_discount( $coupon ) {
		$type = $coupon->get_discount_type();
		$amount = $coupon->get_amount();
		$code = $coupon->get_code();
		
		if ( $type === 'percent' ) {
			$discount = '-' . $amount . '%';
		} else {
			$currency = get_woocommerce_currency_symbol();
			$discount = '-' . $currency . $amount;
		}
		
		return sprintf( 
			__( '%s', 'listeo_elementor' ), 
			$discount, 
			$code 
		);
	}

	/**
	 * Format expiry date.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return string|null
	 */
	private function format_expiry( $coupon ) {
		$expiry_date = $coupon->get_date_expires();
		if ( $expiry_date ) {
			return $expiry_date->date_i18n( get_option( 'date_format' ) );
		}
		// Return special value for no expiry
		return 'no_expiry';
	}

	/**
	 * Get primary listing from comma-separated IDs.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $listing_ids Comma-separated listing IDs.
	 * @return int|null
	 */
	private function get_primary_listing( $listing_ids ) {
		if ( empty( $listing_ids ) ) {
			return null;
		}
		
		$ids = explode( ',', $listing_ids );
		return intval( trim( $ids[0] ) );
	}

	/**
	 * Get listing category name.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param int $listing_id Listing ID.
	 * @return string
	 */
	private function get_listing_category( $listing_id ) {
		$terms = get_the_terms( $listing_id, 'listing_category' );
		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
	}

	/**
	 * Get listing categories that have active coupons.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param array $settings Widget settings.
	 * @return array Array of category objects with coupons.
	 */
	private function get_categories_with_coupons( $settings ) {
		// Check if specific categories are selected in the filter
		$selected_categories = [];
		if ( ! empty( $settings['filter_by_category'] ) && ! in_array( '0', $settings['filter_by_category'] ) ) {
			$selected_categories = $settings['filter_by_category'];
		}
		
		// Build query args for listings with coupon section enabled
		$listing_query_args = [
			'post_type' => 'listing',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => '_coupon_section_status',
					'value' => 'on',
					'compare' => '='
				],
				[
					'key' => '_coupon_for_widget',
					'compare' => 'EXISTS'
				]
			],
			'fields' => 'ids'
		];
		
		// If specific categories are selected, filter listings by those categories
		if ( ! empty( $selected_categories ) ) {
			$listing_query_args['tax_query'] = [
				[
					'taxonomy' => 'listing_category',
					'field'    => 'term_id',
					'terms'    => $selected_categories,
				]
			];
		}
		
		$listings_with_coupons = get_posts( $listing_query_args );

		if ( empty( $listings_with_coupons ) ) {
			return [];
		}

		// Get categories from these listings
		$category_ids = [];
		foreach ( $listings_with_coupons as $listing_id ) {
			$terms = get_the_terms( $listing_id, 'listing_category' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					// Only include categories that are in the selected filter (if any)
					if ( empty( $selected_categories ) || in_array( $term->term_id, $selected_categories ) ) {
						$category_ids[] = $term->term_id;
					}
				}
			}
		}

		// Remove duplicates and get category objects
		$category_ids = array_unique( $category_ids );
		$categories = [];
		
		foreach ( $category_ids as $cat_id ) {
			$category = get_term( $cat_id, 'listing_category' );
			if ( $category && ! is_wp_error( $category ) ) {
				$categories[] = $category;
			}
		}

		return $categories;
	}

	/**
	 * Get icon from taxonomy term meta or category settings.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param int $term_id Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string Icon class or SVG markup.
	 */
	private function get_term_icon( $term_id, $taxonomy ) {
		// First try SVG icon using Listeo's method
		$svg_id = get_term_meta( $term_id, '_icon_svg', true );
		if ( $svg_id && function_exists( 'listeo_render_svg_icon' ) ) {
			return 'svg:' . $svg_id; // Return with prefix to indicate SVG
		}
		
		// Try to get font icon from term meta
		$icon = get_term_meta( $term_id, 'icon', true );
		
		// Try alternative meta key used by some themes
		if ( empty( $icon ) ) {
			$icon = get_term_meta( $term_id, '_icon', true );
		}
		
		// Try Font Awesome icon meta
		if ( empty( $icon ) ) {
			$icon = get_term_meta( $term_id, 'fa_icon', true );
		}
		
		// For listing categories, try category-specific meta
		if ( empty( $icon ) && $taxonomy === 'listing_category' ) {
			$icon = get_term_meta( $term_id, 'category_icon', true );
		}
		
		// Try to get from category meta table (if using custom fields)
		if ( empty( $icon ) ) {
			global $wpdb;
			$icon_result = $wpdb->get_var( $wpdb->prepare( 
				"SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = 'icon'", 
				$term_id 
			) );
			if ( $icon_result ) {
				$icon = $icon_result;
			}
		}
		
		// Default fallback
		if ( empty( $icon ) ) {
			$icon = 'fa fa-star';
		}
		
		return $icon;
	}

	/**
	 * Check if a coupon is expired based on its expiry date.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param int $coupon_id Coupon ID.
	 * @return bool True if expired, false if still valid or no expiry date.
	 */
	private function is_coupon_expired( $coupon_id ) {
		$expiry_date = get_post_meta( $coupon_id, 'date_expires', true );
		
		// If no expiry date is set, coupon never expires
		if ( empty( $expiry_date ) || $expiry_date === '0' || $expiry_date === 0 ) {
			return false;
		}
		
		// Convert expiry date to timestamp if it's not already
		$expiry_timestamp = is_numeric( $expiry_date ) ? intval( $expiry_date ) : strtotime( $expiry_date );
		
		// If we can't parse the date, assume it's not expired (safe fallback)
		if ( $expiry_timestamp === false ) {
			return false;
		}
		
		// Check if expiry date is in the past
		return $expiry_timestamp < time();
	}

	/**
	 * Filter coupons based on listing validation settings and create cards for each valid listing.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param \WP_Query $query Original query results.
	 * @param array $settings Widget settings.
	 * @param array $filtered_listing_ids Listing IDs from widget filters.
	 * @return \WP_Query
	 */
	private function filter_validated_coupons( $query, $settings, $filtered_listing_ids = [] ) {
		// Build query args for listings with coupon section enabled
		$listing_args = [
			'post_type' => 'listing',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => '_coupon_section_status',
					'value' => 'on',
					'compare' => '='
				],
				[
					'key' => '_coupon_for_widget',
					'compare' => 'EXISTS'
				]
			],
			'fields' => 'ids'
		];

		// Apply widget filters if provided
		if ( ! empty( $filtered_listing_ids ) ) {
			$listing_args['post__in'] = $filtered_listing_ids;
		}

		$listings_with_coupons = get_posts( $listing_args );

		if ( empty( $listings_with_coupons ) ) {
			$query->posts = [];
			$query->post_count = 0;
			$query->found_posts = 0;
			return $query;
		}

		// Create lookup for valid listings to avoid repeated queries
		$valid_listings_lookup = array_flip( $listings_with_coupons );
		$seen_listings = []; // Prevent duplicate listings

		// FIRST PASS: Process coupons in original query order to preserve ordering
		$validated_posts = [];
		foreach ( $query->posts as $coupon_post ) {
			// Skip expired coupons
			if ( $this->is_coupon_expired( $coupon_post->ID ) ) {
				continue;
			}

			// Find the listing that selected this coupon
			foreach ( $listings_with_coupons as $listing_id ) {
				// Skip if we already processed this listing
				if ( isset( $seen_listings[ $listing_id ] ) ) {
					continue;
				}

				// Check if this listing selected this specific coupon
				$selected_coupon_id = get_post_meta( $listing_id, '_coupon_for_widget', true );
				
				if ( empty( $selected_coupon_id ) || $selected_coupon_id != $coupon_post->ID ) {
					continue;
				}

				// Add to validated results
				$validated_post = clone $coupon_post;
				$validated_post->primary_listing_id = $listing_id;
				$validated_posts[] = $validated_post;
				$seen_listings[ $listing_id ] = true;
				
				// One coupon can only match one listing, so break here
				break;
			}
		}

		// SECOND PASS: Handle listings that selected coupons not in original query
		foreach ( $listings_with_coupons as $listing_id ) {
			// Skip listings we already processed
			if ( isset( $seen_listings[ $listing_id ] ) ) {
				continue;
			}

			// Get the coupon selected by this listing
			$selected_coupon_id = get_post_meta( $listing_id, '_coupon_for_widget', true );
			
			if ( empty( $selected_coupon_id ) ) {
				continue;
			}

			// Fetch the coupon directly since it wasn't in original query
			$coupon_post = get_post( $selected_coupon_id );
			if ( ! $coupon_post || $coupon_post->post_type !== 'shop_coupon' || $coupon_post->post_status !== 'publish' ) {
				continue;
			}

			// Skip expired coupons
			if ( $this->is_coupon_expired( $coupon_post->ID ) ) {
				continue;
			}

			// Add to validated results
			$validated_post = clone $coupon_post;
			$validated_post->primary_listing_id = $listing_id;
			$validated_posts[] = $validated_post;
			$seen_listings[ $listing_id ] = true;
		}


		// Apply the posts_per_page limit from the original query
		$posts_per_page = isset( $query->query_vars['posts_per_page'] ) ? (int) $query->query_vars['posts_per_page'] : -1;
		$total_found = count( $validated_posts );
		
		if ( $posts_per_page > 0 && $posts_per_page < $total_found ) {
			$validated_posts = array_slice( $validated_posts, 0, $posts_per_page );
		}
		
		$query->posts = $validated_posts;
		$query->post_count = count( $validated_posts );
		$query->found_posts = $total_found; // Keep total for pagination
		$query->current_post = -1;
		
		// Update max_num_pages based on posts_per_page if set
		if ( $posts_per_page > 0 ) {
			$query->max_num_pages = ceil( $total_found / $posts_per_page );
		} else {
			$query->max_num_pages = 1;
		}
		
		return $query;
	}

	/**
	 * Get coupon or listing image URL with priority and fallback logic.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param int $coupon_id Coupon ID.
	 * @param int $listing_id Listing ID.
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function get_coupon_or_listing_image( $coupon_id, $listing_id, $settings ) {
		$image_url = '';
		
		// Priority 1: Check for coupon background image (for coupon_first and coupon_logo options)
		if ( in_array( $settings['image_source'], [ 'coupon_first', 'coupon_logo' ] ) ) {
			$coupon_bg_id = get_post_meta( $coupon_id, 'coupon_bg-uploader-id', true );
			if ( $coupon_bg_id ) {
				$coupon_bg_url = wp_get_attachment_image_url( $coupon_bg_id, array( 400, 200 ) );
				if ( $coupon_bg_url ) {
					return $coupon_bg_url;
				}
			}
		}
		
		// Priority 2: Listing images based on settings
		switch ( $settings['image_source'] ) {
			case 'coupon_logo':
			case 'logo':
				// Try listing logo first
				$logo_id = get_post_meta( $listing_id, '_listing_logo', true );
				if ( $logo_id ) {
					$image_url = wp_get_attachment_image_url( $logo_id, array( 400, 200 ) );
				}
				break;
				
			case 'coupon_first':
			case 'featured':
			default:
				// Try featured image first
				if ( has_post_thumbnail( $listing_id ) ) {
					$image_url = get_the_post_thumbnail_url( $listing_id, array( 400, 200 ) );
				}
				break;
		}
		
		// Priority 3: Cross-fallback (if primary source didn't work)
		if ( empty( $image_url ) ) {
			if ( in_array( $settings['image_source'], [ 'coupon_logo', 'logo' ] ) ) {
				// Logo sources: fallback to featured image
				if ( has_post_thumbnail( $listing_id ) ) {
					$image_url = get_the_post_thumbnail_url( $listing_id, array( 400, 200 ) );
				}
			} else {
				// Featured sources: fallback to logo
				$logo_id = get_post_meta( $listing_id, '_listing_logo', true );
				if ( $logo_id ) {
					$image_url = wp_get_attachment_image_url( $logo_id, array( 400, 200 ) );
				}
			}
		}
		
		// Priority 4: If still no image found, use Listeo's placeholder system
		if ( empty( $image_url ) ) {
			// Use Listeo's built-in function if available
			if ( function_exists( 'listeo_core_get_listing_image' ) ) {
				$image_url = listeo_core_get_listing_image( $listing_id );
			} elseif ( function_exists( 'get_listeo_core_placeholder_image' ) ) {
				$placeholder = get_listeo_core_placeholder_image();
				// Check if placeholder is an ID or URL
				if ( is_numeric( $placeholder ) ) {
					$image_url = wp_get_attachment_image_url( $placeholder, array( 400, 200 ) );
				} else {
					$image_url = $placeholder;
				}
			} else {
				// Fallback to a default placeholder if Listeo functions not available
				$image_url = plugin_dir_url( __FILE__ ) . '../assets/images/placeholder.jpg';
			}
		}
		
		return $image_url;
	}
	
	/**
	 * Get listing image URL with placeholder fallback (legacy method for backward compatibility).
	 *
	 * @since 1.0.0
	 * @access private
	 * @param int $listing_id Listing ID.
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function get_listing_image( $listing_id, $settings ) {
		// For backward compatibility, call the new method without coupon ID
		return $this->get_coupon_or_listing_image( null, $listing_id, $settings );
	}

	/**
	 * Render coupon card.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param object $coupon Coupon post object.
	 * @param array $settings Widget settings.
	 */
	private function render_coupon_card( $coupon, $settings ) {
		// Get coupon data
		$coupon_obj = new \WC_Coupon( $coupon->ID );
		
		// Use specific listing ID if set during validation, otherwise use primary listing
		if ( isset( $coupon->primary_listing_id ) ) {
			$listing_id = $coupon->primary_listing_id;
		} else {
			$listing_ids = get_post_meta( $coupon->ID, 'listing_ids', true );
			$listing_id = $this->get_primary_listing( $listing_ids );
		}
		
		if ( ! $listing_id ) {
			return; // Skip if no associated listing
		}
		
		// Get listing data
		$listing = get_post( $listing_id );
		if ( ! $listing ) {
			return; // Skip if listing doesn't exist
		}
		
		$listing_category = $settings['show_listing_category'] === 'yes' ? $this->get_listing_category( $listing_id ) : '';
		$is_verified = get_post_meta( $listing_id, '_verified', true );
		
		// Get display data
		$discount_text = $this->format_discount( $coupon_obj );
		$expiry_date = $this->format_expiry( $coupon_obj );
		$image_url = $settings['show_listing_image'] === 'yes' ? $this->get_coupon_or_listing_image( $coupon->ID, $listing_id, $settings ) : '';
		
		// Build card HTML
		$card_classes = 'listeo-coupon-card';
		if ( $settings['show_listing_image'] !== 'yes' ) {
			$card_classes .= ' coupon-no-pic';
		}
		?>
		<a href="<?php echo esc_url( get_permalink( $listing_id ) ); ?>" class="<?php echo esc_attr( $card_classes ); ?>" data-coupon-id="<?php echo esc_attr( $coupon->ID ); ?>" rel="noopener">
			<div class="coupon-card-inner">
				
				<?php if ( $settings['show_listing_image'] === 'yes' && !empty( $image_url ) ) : ?>
				<div class="coupon-header" style="background-image: url(<?php echo esc_url( $image_url ); ?>);">
					<?php if ( $settings['show_discount_badge'] === 'yes' ) : ?>
						<div class="coupon-discount-badge">
							<?php echo esc_html( $discount_text ); ?>
						</div>
					<?php endif; ?>
				</div>
				<?php elseif ( $settings['show_discount_badge'] === 'yes' ) : ?>
					<div class="coupon-discount-badge">
						<?php echo esc_html( $discount_text ); ?>
					</div>
				<?php endif; ?>
				
				<div class="coupon-content">
					<div class="coupon-content-wrapper">
						<?php if ( $settings['show_listing_name'] === 'yes' ) : ?>
							<div class="coupon-company-name"><?php echo esc_html( $listing->post_title ); ?></div>
						<?php endif; ?>
						
						<?php if ( $settings['show_listing_category'] === 'yes' && !empty( $listing_category ) ) : ?>
							<div class="coupon-category"><?php echo esc_html( $listing_category ); ?></div>
						<?php endif; ?>
						
						<?php if ( $coupon->post_excerpt && $settings['show_description'] === 'yes' ) : ?>
							<div class="coupon-description">
								<?php echo esc_html( $coupon->post_excerpt ); ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $settings['show_expiry_date'] === 'yes' ) : ?>
							<?php 
							$expiry_class = ( $expiry_date === 'no_expiry' ) ? 'coupon-valid-till no-expiry' : 'coupon-valid-till';
							?>
							<div class="<?php echo esc_attr( $expiry_class ); ?>">
								<?php 
								if ( $expiry_date === 'no_expiry' ) {
									echo __( 'No expiry date', 'listeo_elementor' );
								} elseif ( $expiry_date ) {
									echo sprintf( __( 'Valid till: %s', 'listeo_elementor' ), $expiry_date );
								}
								?>
							</div>
						<?php endif; ?>
					</div>
					
					<?php if ( $settings['show_button'] === 'yes' ) : ?>
						<span class="coupon-get-deal-btn" data-coupon-code="<?php echo esc_attr( $coupon_obj->get_code() ); ?>" data-hover-text="<?php echo esc_attr( $settings['button_text'] ?: __( 'Get Deal', 'listeo_elementor' ) ); ?>">
							<span class="coupon-code-text"><?php echo esc_html( $coupon_obj->get_code() ); ?></span>
							<span class="hover-text"><?php echo esc_html( $settings['button_text'] ?: __( 'Get Deal', 'listeo_elementor' ) ); ?></span>
						</span>
					<?php endif; ?>
				</div>
				
			</div>
		</a>
		<?php
	}

	/**
	 * Get render attribute string from array.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param array $attrs Attributes array.
	 * @return string
	 */
	private function get_render_attribute_string_from_array( $attrs ) {
		$output = '';
		foreach ( $attrs as $key => $value ) {
			$output .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
		return $output;
	}

	/**
	 * Render contextual no results message.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param array $settings Widget settings.
	 */
	private function render_no_results_message( $settings ) {
		$message = __( 'No coupons found.', 'listeo_elementor' );
		$additional_info = '';

		if ( isset( $settings['validate_listing_settings'] ) && $settings['validate_listing_settings'] === 'yes' ) {
			$additional_info = ' ' . __( 'Make sure individual listing owners have enabled coupons and selected them for display.', 'listeo_elementor' );
		}

		echo '<div class="listeo-coupons-no-results">';
		echo '<p>' . esc_html( $message . $additional_info ) . '</p>';

		// Add helpful information for admins/editors
		if ( current_user_can( 'edit_posts' ) && isset( $settings['validate_listing_settings'] ) && $settings['validate_listing_settings'] === 'yes' ) {
			echo '<div class="listeo-coupons-validation-info" style="font-size: 12px; color: #666; margin-top: 10px;">';
			echo '<strong>' . __( 'For developers:', 'listeo_elementor' ) . '</strong> ';
			echo __( 'Coupons require both a valid coupon-to-listing association AND individual listing owners must enable coupon sections and select specific coupons. Disable "Validate Listing Settings" to show all coupons regardless of individual listing configurations.', 'listeo_elementor' );
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render grid layout.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param \WP_Query $query Coupons query.
	 * @param array $settings Widget settings.
	 */
	private function render_grid( $query, $settings ) {
		if ( ! $query->have_posts() ) {
			$this->render_no_results_message( $settings );
			return;
		}
		
		?>
		<div class="listeo-coupons-grid columns-<?php echo esc_attr( $settings['grid_columns'] ); ?>">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_coupon_card( get_post(), $settings );
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
	}

	/**
	 * Render carousel layout.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param \WP_Query $query Coupons query.
	 * @param array $settings Widget settings.
	 */
	private function render_carousel( $query, $settings ) {
		if ( ! $query->have_posts() ) {
			$this->render_no_results_message( $settings );
			return;
		}
		
		// Use theme's carousel system - number of slides to show
		$slides_to_show = isset( $settings['slides_to_show'] ) ? (int) $settings['slides_to_show'] : 4;
		
		// Set carousel classes with default navigation enabled (no simple-slick-carousel to avoid theme auto-init)
		$carousel_classes = ['listeo-coupons-carousel', 'dots-nav', 'arrows-nav'];
		
		// Prepare autoplay data attributes (removed data-slick to avoid conflicts)
		$autoplay_data = '';
		if ( $settings['autoplay'] === 'yes' ) {
			$autoplay_speed = isset( $settings['autoplay_speed'] ) ? (int) $settings['autoplay_speed'] : 3000;
			$autoplay_data = ' data-autoplay="yes" data-autoplay-speed="' . $autoplay_speed . '"';
		}
		
		?>
		<div class="<?php echo esc_attr( implode( ' ', $carousel_classes ) ); ?>" data-slides="<?php echo esc_attr( $slides_to_show ); ?>"<?php echo $autoplay_data; ?>>
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				echo '<div class="fw-carousel-item">';
				$this->render_coupon_card( get_post(), $settings );
				echo '</div>';
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
	}

	/**
	 * Render tabs layout with category navigation.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param WP_Query $query The query object.
	 * @param array $settings Widget settings.
	 */
	private function render_tabs( $query, $settings ) {
		$categories = $this->get_categories_with_coupons( $settings );
		
		if ( empty( $categories ) && $settings['show_all_tab'] !== 'yes' ) {
			$this->render_no_results_message( $settings );
			return;
		}
		
		$show_all_tab = $settings['show_all_tab'] === 'yes';
		$all_tab_label = !empty( $settings['all_tab_label'] ) ? $settings['all_tab_label'] : __( 'All Coupons', 'listeo_elementor' );
		
		?>
		<div class="coupons-container coupons-with-tabs coupons-loading">
			<!-- Loading indicator -->
			<div class="coupons-loader">
				<div class="coupons-loader-spinner"></div>
			</div>
			
			<nav class="coupons-nav">
				<?php if ( $show_all_tab ) : ?>
					<div class="coupons-nav-item active" data-category="all">
						<div class="coupons-nav-icon">
							<i class="fas fa-tags"></i>
						</div>
						<div class="coupons-nav-label"><?php echo esc_html( $all_tab_label ); ?></div>
					</div>
				<?php endif; ?>
				
				<?php foreach ( $categories as $index => $category ) : ?>
					<div class="coupons-nav-item <?php echo ( !$show_all_tab && $index === 0 ) ? 'active' : ''; ?>" data-category="<?php echo esc_attr( $category->term_id ); ?>">
						<div class="coupons-nav-icon">
							<?php
							$icon = $this->get_term_icon( $category->term_id, 'listing_category' );
							
							// Handle SVG and font icons properly
							if ( strpos( $icon, 'svg:' ) === 0 ) {
								// SVG icon - render using Listeo's method
								$svg_id = str_replace( 'svg:', '', $icon );
								if ( function_exists( 'listeo_render_svg_icon' ) ) {
									echo listeo_render_svg_icon( $svg_id );
								} else {
									// Fallback if SVG function not available
									echo '<i class="fas fa-star"></i>';
								}
							} else {
								// Font icon - render as font icon
								echo '<i class="' . esc_attr( $icon ) . '"></i>';
							}
							?>
						</div>
						<div class="coupons-nav-label"><?php echo esc_html( $category->name ); ?></div>
					</div>
				<?php endforeach; ?>
			</nav>

			<div class="coupons-content-area">
				<?php if ( $show_all_tab ) : ?>
					<div class="coupons-tab-content active" id="coupons-category-all">
						<?php $this->render_tab_content( $query, $settings, 'all' ); ?>
					</div>
				<?php endif; ?>
				
				<?php foreach ( $categories as $index => $category ) : ?>
					<div class="coupons-tab-content <?php echo ( !$show_all_tab && $index === 0 ) ? 'active' : ''; ?>" id="coupons-category-<?php echo esc_attr( $category->term_id ); ?>">
						<?php $this->render_tab_content( $query, $settings, $category->term_id ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tab content (filtered coupon grid).
	 *
	 * @since 1.0.0
	 * @access private
	 * @param WP_Query $query The query object.
	 * @param array $settings Widget settings.
	 * @param mixed $category_filter Category ID to filter by, or 'all' for all categories.
	 */
	private function render_tab_content( $query, $settings, $category_filter ) {
		if ( ! $query->have_posts() ) {
			$this->render_no_results_message( $settings );
			return;
		}
		
		$columns = $settings['grid_columns'] ?? '3';
		$initial_count = isset( $settings['initial_display_count'] ) ? (int) $settings['initial_display_count'] : 4;
		$show_more_text = isset( $settings['show_more_text'] ) ? $settings['show_more_text'] : __( 'Show More', 'listeo_elementor' );
		
		// Collect all valid coupons for this tab
		$valid_coupons = [];
		while ( $query->have_posts() ) {
			$query->the_post();
			$coupon = get_post();
			
			// Check if this coupon should be displayed in this tab
			if ( $category_filter !== 'all' ) {
				$listing_id = isset( $coupon->primary_listing_id ) ? $coupon->primary_listing_id : null;
				if ( $listing_id ) {
					$listing_terms = get_the_terms( $listing_id, 'listing_category' );
					$show_in_tab = false;
					
					if ( $listing_terms && ! is_wp_error( $listing_terms ) ) {
						foreach ( $listing_terms as $term ) {
							if ( $term->term_id == $category_filter ) {
								$show_in_tab = true;
								break;
							}
						}
					}
					
					if ( ! $show_in_tab ) {
						continue;
					}
				} else {
					continue;
				}
			}
			
			$valid_coupons[] = $coupon;
		}
		wp_reset_postdata();
		
		if ( empty( $valid_coupons ) ) {
			$this->render_no_results_message( $settings );
			return;
		}
		
		$total_coupons = count( $valid_coupons );
		$category_id = $category_filter === 'all' ? 'all' : $category_filter;
		?>
		
		<div class="coupons-grid coupons-grid-columns-<?php echo esc_attr( $columns ); ?>" data-category="<?php echo esc_attr( $category_id ); ?>">
			<?php
			// Render all coupons but hide the ones beyond initial count
			foreach ( $valid_coupons as $index => $coupon ) {
				$is_hidden = $index >= $initial_count;
				?>
				<div class="coupon-grid-item <?php echo $is_hidden ? 'coupon-hidden' : ''; ?>">
					<?php $this->render_coupon_card( $coupon, $settings ); ?>
				</div>
				<?php
			}
			?>
		</div>
		
		<?php if ( $total_coupons > $initial_count ) : ?>
			<div class="coupons-show-more-container">
				<button type="button" class="coupons-show-more-btn"
				        data-category="<?php echo esc_attr( $category_id ); ?>"
				        data-increment="<?php echo esc_attr( isset( $settings['show_more_increment'] ) ? $settings['show_more_increment'] : 4 ); ?>"
				        data-showing="<?php echo esc_attr( $initial_count ); ?>"
				        data-total="<?php echo esc_attr( $total_coupons ); ?>"
				        data-show-more-text="<?php echo esc_attr( $show_more_text ); ?>">
					<?php echo esc_html( $show_more_text ); ?> 
					<i class="fas fa-chevron-down"></i>
				</button>
			</div>
		<?php endif; ?>
		
		<?php
	}

	/**
	 * Render widget output on the frontend.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		
		
		// Set defaults for removed display controls (always enabled)
		$settings['show_verified_badge'] = 'yes';
		$settings['show_discount_badge'] = 'yes';
		$settings['image_source'] = 'coupon_first';
		$settings['button_action'] = 'link';
		
		$query = $this->get_coupons_query( $settings );
		
		// Add wrapper div
		echo '<div class="listeo-coupons-display-widget">';
		
		if ( $settings['display_type'] === 'carousel' ) {
			$this->render_carousel( $query, $settings );
		} elseif ( $settings['display_type'] === 'tabs' ) {
			$this->render_tabs( $query, $settings );
		} else {
			$this->render_grid( $query, $settings );
		}
		
		echo '</div>';
	}
}