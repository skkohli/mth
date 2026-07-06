<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart
 */
class Listeo_Core_Paid_Properties_Cart {

	/** @var object Class Instance */
	private static $instance;

	/**
	 * Get the class instance
	 *
	 * @return static
	 */
	public static function get_instance() {
		return null === self::$instance ? ( self::$instance = new self ) : self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_listing_package_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );
		add_action( 'woocommerce_listing_package_subscription_add_to_cart', 'WCS_Template_Loader::get_subscription_add_to_cart', 30 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'checkout_create_order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

		// Force reg during checkout process
		add_filter( 'option_woocommerce_enable_signup_and_login_from_checkout', array( $this, 'enable_signup_and_login_from_checkout' ) );
		add_filter( 'option_woocommerce_enable_guest_checkout', array( $this, 'enable_guest_checkout' ) );
	}

	/**
	 * Checks an cart to see if it contains a listing_package.
	 *
	 * @return bool|null
	 */
	public function cart_contains_listing_package() {
		global $woocommerce;

		if ( ! empty( $woocommerce->cart->cart_contents ) ) {
			foreach ( $woocommerce->cart->cart_contents as $cart_item ) {
				$product = $cart_item['data'];

			
				
				if ( $product instanceof WC_Product && $product->is_type( 'listing_package' ) && ! $product->is_type( 'listing_package_subscription' )) {
					return true;
				}
			}
		}
	}


	/**
	 * Ensure this is yes
	 *
	 * @param string $value
	 * @return string
	 */
	public function enable_signup_and_login_from_checkout( $value ) {
		remove_filter( 'option_woocommerce_enable_guest_checkout', array( $this, 'enable_guest_checkout' ) );
		$woocommerce_enable_guest_checkout = get_option( 'woocommerce_enable_guest_checkout' );
		add_filter( 'option_woocommerce_enable_guest_checkout', array( $this, 'enable_guest_checkout' ) );

		if ( 'yes' === $woocommerce_enable_guest_checkout && ( $this->cart_contains_listing_package() ) ) {
			return 'yes';
		} else {
			return $value;
		}
	}

	/**
	 * Ensure this is no
	 *
	 * @param string $value
	 * @return string
	 */
	public function enable_guest_checkout( $value ) {
		if ( $this->cart_contains_listing_package() ) {
			return 'no';
		} else {
			return $value;
		}
	}

	/**
	 * Get the data from the session on page load
	 *
	 * @param array $cart_item
	 * @param array $values
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( ! empty( $values['listing_id'] ) ) {
			$cart_item['listing_id'] = $values['listing_id'];
		}
	
		return $cart_item;
	}

	/**
	 * Legacy function for storing meta data in order line items pre-WC 3.0.
	 *
	 * @param mixed $item_id
	 * @param array $values
	 */
	public function legacy_order_item_meta( $item_id, $values ) {
		// Add the fields
		if ( isset( $values['listing_id'] ) ) {
			$listing = get_post( absint( $values['listing_id'] ) );

			wc_add_order_item_meta( $item_id, __( 'Listing title', 'listeo_core' ), $listing->post_title );
			wc_add_order_item_meta( $item_id, '_listing_id', $values['listing_id'] );
		}
	}

	/**
	 * Set the order line item's meta data prior to being saved (WC >= 3.0.0).
	 *
	 * @since 2.7.3
	 *
	 * @param WC_Order_Item_Product $order_item
	 * @param string                $cart_item_key  The hash used to identify the item in the cart
	 * @param array                 $cart_item_data The cart item's data.
	 * @param WC_Order              $order          The order or subscription object to which the line item relates
	 */
	public function checkout_create_order_line_item( $order_item, $cart_item_key, $cart_item_data, $order ) {
		if ( isset( $cart_item_data['listing_id'] ) ) {
			$listing = get_post( absint( $cart_item_data['listing_id'] ) );

			$order_item->update_meta_data( __( 'Listing title', 'listeo_core' ), $listing->post_title );
			$order_item->update_meta_data( '_listing_id', $cart_item_data['listing_id']  );
		}
		
	}

	/**
	 * Output listing name in cart
	 *
	 * @param  array $data
	 * @param  array $cart_item
	 * @return array
	 */
	public function get_item_data( $data, $cart_item ) {
		if ( isset( $cart_item['listing_id'] ) ) {
			$listing = get_post( absint( $cart_item['listing_id'] ) );
			$title  = (isset($listing->post_title)) ? $listing->post_title : '' ;
			$data[] = array(
				'name'  => __( 'Listing title', 'listeo_core' ),
				'value' => $title,
			);
		}
	
		return $data;
	}
}
Listeo_Core_Paid_Properties_Cart::get_instance();
