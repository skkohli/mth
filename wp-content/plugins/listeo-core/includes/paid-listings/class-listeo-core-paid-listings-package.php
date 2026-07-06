<?php
/**
 * Properties Package
 */
class Listeo_Core_Paid_Listings_Package {
	/**
	 * @var stdClass
	 */
	public $package;

	/**
	 * @var WP_Post
	 */
	private $product;

	/**
	 * Constructor
	 */
	public function __construct( $package ) {
		$this->package = $package;

	}

	/**
	 * Checks if package is set.
	 *
	 * @return bool
	 */
	public function has_package() {
		return ! empty( $this->package );
	}

	/**
	 * Get package ID
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->package->id;
	}

	/**
	 * Get product post
	 *
	 * @return WP_Post
	 */
	public function get_product() {
		if ( empty( $this->product ) ) {
			$this->product = get_post( $this->get_product_id() );
		}
		return $this->product;
	}

	/**
	 * Get product id
	 *
	 * @return int
	 */
	public function get_product_id() {
		return $this->package->product_id;
	}

	/**
	 * Get title for package
	 *
	 * @return string
	 */
	public function get_title() {
		$product = $this->get_product();
		return $product ? $product->post_title : '-';
	}

	/**
	 * Is this package for features jobs/resumes?
	 *
	 * @return boolean
	 */
	public function is_featured() {
		return $this->package->package_featured == 1;
	}

	/**
	 * Get limit
	 *
	 * @return int
	 */
	public function get_limit() {
		return $this->package->package_limit;
	}

	/**
	 * Get count
	 *
	 * @return int
	 */
	public function get_count() {
		return $this->package->package_count;
	}

	/**
	 * Get duration
	 *
	 * @return int|bool
	 */
	public function get_duration() {
		return $this->package->package_duration ? $this->package->package_duration : false;
	}
		
	/**
	 * Get option booking
	 *
	 * @return int|bool
	 */
	public function get_option_booking() {
		return $this->package->package_option_booking ? $this->package->package_option_booking : false;
	}

	public function has_listing_booking() {
		return $this->package->package_option_booking ? $this->package->package_option_booking : false;
	}

	

	/**
	 * Get option reviews
	 *
	 * @return int|bool
	 */
	public function get_option_reviews() {
		return $this->package->package_option_reviews ? $this->package->package_option_reviews : false;
	}

	public function has_listing_reviews() {
		return $this->package->package_option_reviews ? $this->package->package_option_reviews : false;
	}

	/**
	 * Get option social links
	 *
	 * @return int|bool
	 */
	public function get_option_social_links() {
		return $this->package->package_option_social_links ? $this->package->package_option_social_links : false;
	}		

	public function has_listing_social_links() {

		
		return $this->package->package_option_social_links ? $this->package->package_option_social_links : false;
	}	

	/**
	 * Get option hours
	 *
	 * @return int|bool
	 */
	public function get_option_opening_hours() {
		return $this->package->package_option_opening_hours ? $this->package->package_option_opening_hours : false;
	}	

	public function has_listing_opening_hours() {
		return $this->package->package_option_opening_hours ? $this->package->package_option_opening_hours : false;
	}		

	public function has_listing_pricing_menu() {
		return $this->package->package_option_pricing_menu ? $this->package->package_option_pricing_menu : false;
	}		

	/**
	 * Get option video
	 *
	 * @return int|bool
	 */
	public function get_option_video() {
		return $this->package->package_option_video ? $this->package->package_option_video : false;
	}	

	public function has_listing_video() {
		return $this->package->package_option_video ? $this->package->package_option_video : false;
	}	
	/**
	 * Get option coupons
	 *
	 * @return int|bool
	 */
	public function get_option_coupons() {
		return $this->package->package_option_coupons ? $this->package->package_option_coupons : false;
	}	

	public function has_listing_coupons() {
		return $this->package->package_option_coupons ? $this->package->package_option_coupons : false;
	}	
	
	public function has_listing_faq() {
		return $this->package->package_option_faq ? $this->package->package_option_faq : false;
	}	

	/**
	 * Get gallery limit booking
	 *
	 * @return int|bool
	 */
	public function get_option_gallery() {
		return $this->package->package_option_gallery ? $this->package->package_option_gallery : false;
	}
	public function has_listing_gallery() {
		return $this->package->package_option_gallery ? $this->package->package_option_gallery : false;
	}


	public function get_option_gallery_limit() {

		return $this->package->package_option_gallery_limit ? $this->package->package_option_gallery_limit : 10;;
	}

	
	/**
	 * Get order id
	 *
	 * @return int
	 */
	public function get_order_id() {
		return $this->package->order_id;
	}
}
