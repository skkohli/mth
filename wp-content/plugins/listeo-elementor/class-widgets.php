<?php
/**
 * Widgets class.
 *
 * @category   Class
 * @package    ElementorListeo
 * @subpackage WordPress
 * @author     Purethemes.net
 * @copyright  Purethemes.net
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @since      1.0.0
 * php version 7.3.9
 */

namespace ElementorListeo;

use ElementorListeo\Controls\Listings_Autocomplete_Control;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Class Plugin
 *
 * Main Plugin class
 *
 * @since 1.0.0
 */
class Widgets {

	/**
	 * Instance
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @var Plugin The single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return Plugin An instance of the class.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the widget scripts.
	 *
	 * Load required plugin core files.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function widget_scripts() {
		// Register required scripts for widgets
		wp_register_script('leaflet', get_template_directory_uri() . '/js/listeo.big.leaflet.min.js', array('jquery'), '1.0.0', true);
        
		// Register hierarchical taxonomy widget assets
		wp_register_style(
			'hierarchical-taxonomy-css',
			plugins_url('/assets/css/hierarchical-taxonomy.css', __FILE__),
			array(),
			'1.0.0'
		);
		
		wp_register_script(
			'hierarchical-taxonomy-js',
			plugins_url('/assets/js/hierarchical-taxonomy.js', __FILE__),
			array('jquery'),
			'1.0.0',
			true
		);

		// Register Listeo Taxonomy Tabs widget assets
		wp_register_style(
			'listeo-taxonomy-tabs-style',
			plugins_url('/assets/css/taxonomy-tabs.css', __FILE__),
			array(),
			'1.0.1'
		);
		
		wp_register_script(
			'listeo-taxonomy-tabs-script',
			plugins_url('/assets/js/taxonomy-tabs.js', __FILE__),
			array('jquery'),
			'1.0.2',
			true
		);

		// Register Listeo FAQ widget assets
		wp_register_style(
			'listeo-faq-style',
			plugins_url('/assets/css/faq.css', __FILE__),
			array(),
			'1.0.0'
		);
		
		wp_register_script(
			'listeo-faq-script',
			plugins_url('/assets/js/faq.js', __FILE__),
			array('jquery'),
			'1.0.0',
			true
		);

		// Register Listeo Content Carousel widget assets
		wp_register_style(
			'listeo-content-carousel-style',
			plugins_url('/assets/css/content-carousel.css', __FILE__),
			array(),
			'1.0.0'
		);
		
		wp_register_script(
			'listeo-content-carousel-script',
			plugins_url('/assets/js/content-carousel.js', __FILE__),
			array('jquery'),
			'1.0.0',
			true
		);

		// Register Listeo Coupons Display widget assets
		wp_register_style(
			'listeo-coupons-display-style',
			plugins_url('/assets/css/coupons-display.css', __FILE__),
			array(),
			'1.0.0'
		);

		wp_register_script(
			'listeo-coupons-display-script',
			plugins_url('/assets/js/coupons-display.js', __FILE__),
			array('jquery'),
			'1.0.0',
			true
		);

		// Register Taxonomy Responsive Slider assets
		wp_register_style(
			'taxonomy-responsive-slider-style',
			plugins_url('/assets/css/taxonomy-responsive-slider.css', __FILE__),
			array(),
			'1.0.0'
		);

		wp_register_script(
			'taxonomy-responsive-slider-script',
			plugins_url('/assets/js/taxonomy-responsive-slider.js', __FILE__),
			array('jquery'),
			'1.0.0',
			true
		);
        
		// This is important for map in editor - register our elementor preview script
		if (is_admin() || defined('ELEMENTOR_VERSION')) {
			wp_register_script('elementor-preview-listeo', plugins_url('/assets/js/elementor_preview_listeo.js', __FILE__), array('jquery'), '1.0.0', true);
		}
	}

	public function backend_preview_scripts() {
		wp_enqueue_script( 'elementor-preview-listeo', plugins_url( '/assets/js/elementor_preview_listeo.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
	}

	/**
	 * Include Widgets files
	 *
	 * Load widgets files
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function include_widgets_files() {

		require_once 'widgets/class-headline.php';
		require_once 'widgets/class-tax-carousel.php';
		require_once 'widgets/class-tax-grid.php';
		require_once 'widgets/class-tax-list.php';
		require_once 'widgets/class-tax-gallery.php';
		require_once 'widgets/class-tax-wide.php';
		require_once 'widgets/class-tax-box.php';
		require_once 'widgets/class-hierarchical-taxonomy.php';
		
		require_once 'widgets/class-iconbox.php';
		require_once 'widgets/class-imagebox.php';
		require_once 'widgets/class-post-grid.php';
		require_once 'widgets/class-listings-carousel.php';
		require_once 'widgets/class-listings-wide.php';
		require_once 'widgets/class-listings.php';
		require_once 'widgets/class-flip-banner.php';
		require_once 'widgets/class-testimonials.php';
		require_once 'widgets/class-pricing-table.php';
		require_once 'widgets/class-listings-map.php';
		
		require_once 'widgets/class-text-typed.php';
		require_once 'widgets/class-reviews-carousel.php';

		if (function_exists('is_woocommerce_activated') && is_woocommerce_activated()) {
			require_once 'widgets/class-pricing-table-woo.php';
			//require_once 'widgets/class-woo-products-grid.php';
			require_once 'widgets/class-woo-products-carousel.php';
			require_once 'widgets/class-dokan-vendors-carousel.php';
			require_once 'widgets/class-dokan-vendors-grid.php';
			require_once 'widgets/class-woo-tax-grid.php';
		}
		

		require_once 'widgets/class-home-banner.php';
		require_once 'widgets/class-home-banner-boxed.php';
		require_once 'widgets/class-home-banner-slider.php';
		require_once 'widgets/class-home-banner-simple-slider.php';
		require_once 'widgets/class-home-search-slider.php';
		// require_once 'widgets/class-home-search-map.php'; // Disabled - broken widget
		
		require_once 'widgets/class-logo-slider.php';
		require_once 'widgets/class-address-box.php';
		require_once 'widgets/class-alertbox.php';
		// home search boxes


		// //single listing widgets
		require_once 'widgets/single/class-listing-custom-field.php';
		require_once 'widgets/single/class-listing-custom-fields.php';
		require_once 'widgets/single/class-listing-gallery.php';
		require_once 'widgets/single/class-listing-grid-gallery.php';
		require_once 'widgets/single/class-listing-map.php';
		require_once 'widgets/single/class-listing-pricing-menu.php';
		require_once 'widgets/single/class-listing-sidebar.php';
		require_once 'widgets/single/class-listing-store-carousel.php';
		require_once 'widgets/single/class-listing-tax-checkboxes.php';
		require_once 'widgets/single/class-listing-title.php';
		require_once 'widgets/single/class-listing-verified.php';
		require_once 'widgets/single/class-listing-video.php';
		require_once 'widgets/single/class-listing-single-navigation.php';
		require_once 'widgets/single/class-listing-socials.php';
		require_once 'widgets/single/class-listing-calendar.php';
		require_once 'widgets/single/class-listing-related.php';
		require_once 'widgets/single/class-listing-google-reviews.php';
		require_once 'widgets/single/class-listing-reviews.php';
		require_once 'widgets/single/class-listing-bookmarks.php';
		require_once 'widgets/single/class-listing-claim.php';
		require_once 'widgets/single/class-listing-faq.php';
		require_once 'widgets/single/class-listing-other-listings.php';
		require_once 'widgets/single/class-listing-nearby.php';
		require_once 'widgets/single/class-listing-poi.php';

		// Listing Resources — surfaces LBP's Resources block as an
		// Elementor widget. Only loaded when LBP is active to avoid
		// shipping a broken widget when the add-on isn't installed.
		if ( class_exists( 'LBP_Frontend' ) ) {
			require_once 'widgets/single/class-listing-resources.php';
		}
		
		// Custom widgets
		require_once 'widgets/class-taxonomy-tabs.php';
		require_once 'widgets/class-faq.php';
		require_once 'widgets/class-content-carousel.php';
		require_once 'widgets/class-coupons-display.php';
		require_once 'widgets/class-minimal-search-form.php';

		// AI Chat widget - only if AI Search plugin is active
		if ( defined( 'LISTEO_AI_SEARCH_VERSION' ) ) {
			require_once 'widgets/class-ai-chat.php';
		}



		//require_once 'widgets/class-widget2.php';
	}

	/**
	 * Register Widgets
	 *
	 * Register new Elementor widgets.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function register_widgets() {
		// It's now safe to include Widgets files.
		$this->include_widgets_files();
			
            // 'imagebox',
            // 'posts-carousel',
            // 'listings-carousel',
            // 'flip-banner',
            // 'testimonials',
            // 'pricing-table',
            // 'pricingwrapper',
            // 'logo-slider',
           
            // 'address-box',
            // 'button',
            // 'alertbox',
            // 'list',
            // 'pricing-tables-wc',
            // 'masonry'
		// Register the plugin widget classes.
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\Headline() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\TaxonomyCarousel() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\TaxonomyGrid() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\TaxonomyList() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\TaxonomyGallery() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\TaxonomyWide() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\TaxonomyBox() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\HierarchicalTaxonomy() );

		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\IconBox() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ImageBox() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\PostGrid() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingsCarousel() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingsWide() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\Listings() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\FlipBanner() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\Testimonials() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\PricingTable() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\TextTyped() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ReviewsCarousel() );
		
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\LogoSlider() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\Addresbox() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\Alertbox() );
		if (function_exists('is_woocommerce_activated') && is_woocommerce_activated()) {
			\Elementor\Plugin::instance()->widgets_manager->register(new Widgets\WooTaxonomyGrid());
			\Elementor\Plugin::instance()->widgets_manager->register(new Widgets\PricingTableWoo());
			\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\WooProductsCarousel() );
			\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\DokanVendordsCarousel() );
			\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\DokanVendordsGrid() );
			
		}
		\Elementor\Plugin::instance()->widgets_manager->register(new Widgets\HomeSearchSlider());
		// \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\HomeSearchMap()); // Disabled - broken widget
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\HomeBanner() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\HomeBannerBoxed() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\HomeBannerSlider() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\HomeBannerSimpleSlider() );

		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingsMap());
		
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingCustomField());
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingCustomFields());
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingGallery() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingGridGallery() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingMap() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingPricingMenu() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingSidebar());
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingStoreCarousel());
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingTaxonomyCheckboxes());
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingTitle() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingVerifiedBadge() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingVideo() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingSingleNavigation() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingSocials() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingCalendar() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingRelated() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingGoogleReviews() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingReviews() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingBookmarks() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingClaim() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingFaq() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingOtherListings() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingNearby() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingPOI() );
		if ( class_exists( 'LBP_Frontend' ) ) {
			\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ListingResources() );
		}
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ServicesShowcase() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\FAQ() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\ContentCarousel() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\CouponsDisplay() );
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\MinimalSearchForm() );

		// AI Chat widget - only if AI Search plugin is active
		if ( defined( 'LISTEO_AI_SEARCH_VERSION' ) ) {
			\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\AiChat() );
		}

		//\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\WooProductsGrid() );

	}

	/**
	 *  Plugin class constructor
	 *
	 * Register plugin action hooks and filters
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function __construct() {

		add_action( 'elementor/elements/categories_registered', array( $this, 'create_custom_categories') );

		// Register the widget scripts.
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'widget_scripts' ) );

		add_action('elementor/preview/enqueue_styles', array($this, 'backend_preview_scripts'), 10);
        
        //add_action('elementor/frontend/after_register_scripts', array($this, 'cocobasic_frontend_enqueue_script'));

		// Register the widgets.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		add_action( 'elementor/controls/register', array( $this, 'register_controls' ) );

		add_action( 'wp_ajax_listeo_elementor_search_listings', array( $this, 'ajax_search_listings' ) );
		add_action( 'wp_ajax_listeo_elementor_get_listings', array( $this, 'ajax_get_listings' ) );
		
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_scripts_early' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_preview_scripts' ) );
	}

	public function register_controls( $controls_manager ) {
		require_once plugin_dir_path( __FILE__ ) . 'controls/class-listings-autocomplete-control.php';

		$control = new \ElementorListeo\Controls\Listings_Autocomplete_Control();
		$controls_manager->register( $control );
	}

	public function enqueue_editor_scripts_early() {
		// Reserved for future use
	}

	public function enqueue_preview_scripts() {
		// Reserved for future use
	}

	public function enqueue_editor_scripts() {
		// Only ensure select2 is available
		if (!\wp_style_is('elementor-select2', 'enqueued')) {
			\wp_enqueue_style('elementor-select2');
		}
	}


	function create_custom_categories( $elements_manager ) {

	    $elements_manager->add_category(
	        'listeo',
	        [
	         'title' => __( 'Listeo', 'plugin-name' ),
	         'icon' => 'fa fa-clipboard',
	        ]
	    );
	    $elements_manager->add_category(
	        'listeo-single',
	        [
	         'title' => __( 'Listeo Single Listing', 'plugin-name' ),
	         'icon' => 'fa fa-clipboard',
	        ]
	    );
	}

	public function ajax_search_listings() {
		$this->verify_ajax_request();

		$search = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash($_GET['q'])) : '';
		$page   = isset($_GET['page']) ? max(1, \absint($_GET['page'])) : 1;

		$query = new \WP_Query(array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $page,
			's'              => $search,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		));

		$results = array();
		if (!empty($query->posts)) {
			foreach ($query->posts as $post_id) {
				$results[] = array(
					'id'   => (string) $post_id,
					'text' => html_entity_decode(\get_the_title($post_id), ENT_QUOTES, \get_bloginfo('charset')),
				);
			}
		}

		\wp_send_json(array(
			'results'    => $results,
			'pagination' => array(
				'more' => ($page < $query->max_num_pages),
			),
		));
	}

	public function ajax_get_listings() {
		$this->verify_ajax_request();

		$ids = array();
		if (isset($_GET['ids'])) {
			$ids = $_GET['ids'];
			if (!is_array($ids)) {
				$ids = explode(',', (string) $ids);
			}
			$ids = array_filter(array_map('\absint', $ids));
		}

		$results = array();
		if (!empty($ids)) {
			$posts = \get_posts(array(
				'post_type'      => 'listing',
				'post__in'       => $ids,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			));

			foreach ($posts as $post) {
				$results[] = array(
					'id'   => (string) $post->ID,
					'text' => html_entity_decode(\get_the_title($post->ID), ENT_QUOTES, \get_bloginfo('charset')),
				);
			}
		}

		\wp_send_json(array(
			'results' => $results,
		));
	}

	private function verify_ajax_request() {
		\check_ajax_referer('listeo_elementor_posts', 'nonce');

		if (!\current_user_can('edit_posts')) {
			\wp_send_json_error(array('message' => __('Permission denied.', 'listeo_elementor')), 403);
		}
	}

	
}

// Instantiate the Widgets class.
Widgets::instance();
