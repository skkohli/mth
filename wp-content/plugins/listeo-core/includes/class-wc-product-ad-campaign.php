<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Init the plugin when all plugins are loaded
 */


// add a product type
add_filter('product_type_selector', 'listeo_core_add_listing_product_ad_campaign_type');
function listeo_core_add_listing_product_ad_campaign_type($types)
{
	$types['listeo_ad_campaign'] = __('Listeo Ad Campaign', 'listeo_core');
	return $types;
}

function init_campaign_product_class($classname, $product_type, $post_type, $product_id)
{
	if ($product_type === 'listeo_ad_campaign') {
		$classname = 'WC_Product_Ad_Campaign';
	}
	return $classname;
}
add_filter('woocommerce_product_class', 'init_campaign_product_class', 10, 4);


function listeo_core_create_ad_product_type() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	class WC_Product_Ad_Campaign extends WC_Product {


		protected $product_type;

		
	    public function __construct( $product ) {
	       $this->product_type = 'listeo_ad_campaign';
	       parent::__construct( $product );
	       // add additional functions here
	    }

		/**
		 * Compatibility function for `get_id()` method
		 *
		 * @return int
		 */
		public function get_id() {
			
			return parent::get_id();
		}

		/**
		 * Get product id
		 *
		 * @return int
		 */
		public function get_product_id() {
			return $this->get_id();
		}

		/**
		 * Get the product's title. For products this is the product name.
		 *
		 * @return string
		 */
		public function get_title() {
			return apply_filters( 'woocommerce_product_title', parent::get_name(), $this );
		}

    	/**
		 * Get internal type.
		 *
		 * @return string
		 */
		public function get_type() {
			return 'listeo_ad_campaign';
		}

		/**
		 *
		 * @return boolean
		 */
		public function is_sold_individually() {
			return true;
		}

		/**
		 * Get the add to url used mainly in loops.
		 *
		 * @access public
		 * @return string
		 */
		public function add_to_cart_url() {
			$url = $this->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $this->get_id() ) ) : get_permalink( $this->get_id() );

			return apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this );
		}

		/**
		 * Get the add to cart button text
		 *
		 * @access public
		 * @return string
		 */
		public function add_to_cart_text() {
			$text = $this->is_purchasable() && $this->is_in_stock() ? __( 'Add to cart', 'listeo_core' ) : __( 'Read More', 'listeo_core' );

			return apply_filters( 'woocommerce_product_add_to_cart_text', $text, $this );
		}

		/**
		 *
		 * @return boolean
		 */
		public function is_purchasable() {
			return true;
		}

		/**
		 *
		 * @return boolean
		 */
		public function is_virtual() {
			return true;
		}

		public function get_product_meta( $key ) {
			return $this->get_meta( '_' . $key );
		}


	}

	
}

add_action( 'plugins_loaded', 'listeo_core_create_ad_product_type' );