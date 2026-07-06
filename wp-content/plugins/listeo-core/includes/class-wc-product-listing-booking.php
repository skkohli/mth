<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Init the plugin when all plugins are loaded
 */

// add a product type
add_filter( 'product_type_selector', 'listeo_core_add_listing_product_booking_type' );
function listeo_core_add_listing_product_booking_type( $types ){
    $types[ 'listing_booking' ] = __( 'Listing Booking','listeo_core' );
    return $types;
}

function init_custom_product_class($classname, $product_type, $post_type, $product_id)
{
	if ($product_type === 'listing_booking') {
		$classname = 'WC_Product_Listing_Booking';
	}
	return $classname;
}
add_filter('woocommerce_product_class', 'init_custom_product_class', 10, 4);


function listeo_core_create_listing_product_booking_type() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	class WC_Product_Listing_Booking extends WC_Product_Simple {

		/**
		 * Product type
		 *
		 * @var string
		 */
		protected $product_type = 'listing_booking';

	    public function __construct( $product ) {
	       $this->product_type = 'listing_booking';
			$this->set_virtual(true);
			$this->set_downloadable(true);
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
			return 'listing_booking';
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

		// /**
		//  * Return  duration granted
		//  *
		//  * @return int
		//  */
		// public function get_duration() {
		// 	$listing_duration = $this->get_listing_duration();
		// 	if ( $listing_duration ) {
		// 		return $listing_duration;
		// 	} else {
		// 		return get_option( 'listeo_core_submission_duration' );
		// 	}
		// }


		// /**
		//  * Return listing limit
		//  *
		//  * @return int 0 if unlimited
		//  */
		// public function get_limit() {
		// 	$listing_limit = $this->get_listing_limit();
		// 	if ( $listing_limit ) {
		// 		return $listing_limit;
		// 	} else {
		// 		return 0;
		// 	}
		// } 


	
		public function get_product_meta( $key ) {
			return $this->get_meta( '_' . $key );
		}


	}

	
}

add_action( 'plugins_loaded', 'listeo_core_create_listing_product_booking_type' );