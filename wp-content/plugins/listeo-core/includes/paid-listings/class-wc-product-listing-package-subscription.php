<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job Package Product Type
 */
class WC_Product_Listing_Package_Subscription extends Listeo_Core_Paid_Listings_Subscription_Product {


	protected $product_type;
	/**
	 * Constructor
	 *
	 * @param int|WC_Product|object $product Product ID, post object, or product object
	 */
	public function __construct( $product ) {
		parent::__construct( $product );
		$this->product_type = 'listing_package_subscription';
	}

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'listing_package_subscription';
	}

	/**
	 * Checks the product type.
	 *
	 * Backwards compat with downloadable/virtual.
	 *
	 * @access public
	 * @param mixed $type Array or string of types
	 * @return bool
	 */
	public function is_type( $type ) {
		return ( 'listing_package_subscription' == $type || ( is_array( $type ) && in_array( 'listing_package_subscription', $type ) ) ) ? true : parent::is_type( $type );
	}

	/**
	 * Get the add to url used mainly in loops.
	 *
	 * @access public
	 * @return string
	 */
	public function add_to_cart_url() {
		$url = $this->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $this->id ) ) : get_permalink( $this->id );

		return apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this );
	}


	/**
	 * Return job listing duration granted
	 *
	 * @return int
	 */
	public function get_duration() {
		$listing_duration = $this->get_listing_duration();
		if ( 'listing' === $this->get_package_subscription_type() ) {
			return false;
		} elseif ( $listing_duration ) {
			return $listing_duration;
		} else {
			return 0; // it suppose to make the listing never expire
			//	return get_option( 'listeo_default_duration' );
		}
	}

	/**
	 * Return job listing limit
	 *
	 * @return int 0 if unlimited
	 */
	public function get_limit() {
		$listing_limit = $this->get_listing_limit();
		if ( $listing_limit ) {
			return $listing_limit;
		} else {
			return 0;
		}
	}

	/**
	 * Return if featured
	 *
	 * @return bool true if featured
	 */
	public function is_listing_featured() {
		return 'yes' === $this->get_listing_featured();
	}

	/**
	 * Get job listing featured flag
	 *
	 * @return string
	 */
	public function get_listing_featured() {
		return $this->get_product_meta( 'listing_featured' );
	}

	/**
	 * Get job listing limit
	 *
	 * @return int
	 */
	public function get_listing_limit() {
		return $this->get_product_meta( 'listing_limit' );
	}

	/**
	 * Get job listing duration
	 *
	 * @return int
	 */
	public function get_listing_duration() {
		return $this->get_product_meta( 'listing_duration' );
	}
	
		public function get_product_meta( $key ) {
			return $this->get_meta( '_' . $key );
		}

/**
		 * Return if featured
		 *
		 * @return bool true if featured
		 */
		public function has_listing_booking() {
			return 'yes' === $this->get_option_booking();
		}
		/**
		 * Get option booking
		 *
		 * @return int|bool
		 */
		public function get_option_booking() {
			return $this->get_product_meta( 'package_option_booking' );
			
		}


		public function has_listing_reviews() {
			return 'yes' === $this->get_option_reviews();
		}
		/**
		 * Get option reviews
		 *
		 * @return int|bool
		 */
		public function get_option_reviews() {
			return $this->get_product_meta( 'package_option_reviews' );
			
		}


		public function has_listing_social_links() {
			return 'yes' === $this->get_option_social_links();
		}
		/**
		 * Get option social links
		 *
		 * @return int|bool
		 */
		public function get_option_social_links() {
			return $this->get_product_meta( 'package_option_social_links' );
		}	


		public function has_listing_opening_hours() {
			return 'yes' === $this->get_option_opening_hours();
		}

		/**
		 * Get option hours
		 *
		 * @return int|bool
		 */
		public function has_listing_pricing_menu() {
			return 'yes' === $this->get_option_pricing_menu();
		}
		/**
		 * Get option hours
		 *
		 * @return int|bool
		 */
		public function get_option_opening_hours() {
			return $this->get_product_meta( 'package_option_opening_hours' );
		}		
		/**
		 * Get option hours
		 *
		 * @return int|bool
		 */
		public function get_option_pricing_menu() {
			return $this->get_product_meta('package_option_pricing_menu' );
		}		


		public function has_listing_video() {
			return 'yes' === $this->get_option_video();
		}
		/**
		 * Get option video
		 *
		 * @return int|bool
		 */
		public function get_option_video() {
			return $this->get_product_meta( 'package_option_video' );
			
		}	


		public function has_listing_coupons() {
			return 'yes' === $this->get_option_coupons();
		}

		public function has_listing_faq() {
			return 'yes' === $this->get_option_faq();
		}
		/**
		 * Get option coupons
		 *
		 * @return int|bool
		 */
		public function get_option_coupons() {
			return $this->get_product_meta( 'package_option_coupons' );
			
		}		
		public function get_option_faq() {
			return $this->get_product_meta( 'package_option_faq' );
			
		}
		
				


		public function has_listing_gallery() {
			return 'yes' === $this->get_option_gallery();
		}
		
		public function get_option_gallery() {
			return $this->get_product_meta( 'package_option_gallery' );
			
		}	

		/**
		 * Get gallery limit booking
		 *
		 * @return int|bool
		 */
		public function get_option_gallery_limit() {
			return $this->get_product_meta( 'package_option_gallery_limit' );

		}

		/**
		 * Check if package has Dokan store access
		 *
		 * @return bool
		 */
		public function has_listing_dokan_store() {
			return 'yes' === $this->get_option_dokan_store();
		}

		/**
		 * Get Dokan store access option
		 *
		 * @return string
		 */
		public function get_option_dokan_store() {
			return $this->get_product_meta( 'package_option_dokan_store' );
		}

		/**
		 * Get Dokan store access duration in days
		 *
		 * @return int 0 = unlimited
		 */
		public function get_dokan_store_duration() {
			$duration = $this->get_product_meta( 'package_dokan_store_duration' );
			return $duration ? intval( $duration ) : 0;
		}

	/**
	 * Get package subscription type
	 *
	 * @return string
	 */
	public function get_package_subscription_type() {
		return $this->get_product_meta( 'package_subscription_type' );
	}
}
