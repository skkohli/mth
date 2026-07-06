<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orders
 */
class Listeo_Core_Orders {

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
	// Statuses
		add_action( 'woocommerce_thankyou', array( $this, 'woocommerce_thankyou' ), 5 );

		add_action( 'woocommerce_order_status_processing', array( $this, 'order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'order_paid' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'order_cancelled' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'order_cancelled' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'order_cancelled' ) );
	}

	/**
	 * Triggered when an order is paid
	 *
	 * @param  int $order_id
	 */
	public function order_paid( $order_id ) {
		// Get the order


		$order = wc_get_order( $order_id );

		if ( get_post_meta( $order_id, 'listeo_core_paid_listings_processed', true ) ) {
			return;
		}

		// Clear cancelled flag if order is being re-paid after cancellation
		if ( get_post_meta( $order_id, 'listeo_core_paid_listings_cancelled', true ) ) {
			delete_post_meta( $order_id, 'listeo_core_paid_listings_cancelled' );
		}
		
		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item['product_id'] );

			// Skip claim order items - handled by Listeo_Core_Claim_Listings::process_claim_order_payment()
			$claim_id = wc_get_order_item_meta( $item->get_id(), '_claim_id', true );
			if ( $claim_id ) {
				continue;
			}

			if ( $product &&  $product->is_type( 'listing_package' ) && $order->get_customer_id() ) {

				// Give packages to user
				$user_package_id = false;
				for ( $i = 0; $i < $item['qty']; $i ++ ) {
					$user_package_id = listeo_core_give_user_package( $order->get_customer_id(), $product->get_id(), $order_id );
				}

				$this->attach_package_listings( $item, $order, $user_package_id );
			}

		}

		update_post_meta( $order_id, 'listeo_core_paid_listings_processed', true );
	}

	/**
	 * Triggered when an order is cancelled, refunded, or failed
	 *
	 * @param  int $order_id
	 */
	public function order_cancelled( $order_id ) {
		global $wpdb;

		// Get the order
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if this order was processed (packages were given)
		if ( ! get_post_meta( $order_id, 'listeo_core_paid_listings_processed', true ) ) {
			return; // No packages were given, nothing to cancel
		}

		// Check if already cancelled to prevent duplicate processing
		if ( get_post_meta( $order_id, 'listeo_core_paid_listings_cancelled', true ) ) {
			return;
		}

		$user_id = $order->get_customer_id();

		if ( ! $user_id ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item['product_id'] );

			if ( $product && $product->is_type( 'listing_package' ) ) {

				// Find user packages associated with this order and product
				$user_packages = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}listeo_core_user_packages
						WHERE user_id = %d
						AND product_id = %d
						AND order_id = %d",
						$user_id,
						$product->get_id(),
						$order_id
					)
				);

				if ( $user_packages ) {
					foreach ( $user_packages as $package ) {
						$user_package_id = $package->id;

						// Find all listings using this package
						$listings = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT post_id FROM {$wpdb->postmeta}
								WHERE meta_key = '_user_package_id'
								AND meta_value = %d",
								$user_package_id
							)
						);

						// Revert listings to pending_payment status
						if ( $listings ) {
							foreach ( $listings as $listing ) {
								$listing_id = $listing->post_id;

								// Update listing status to pending_payment
								wp_update_post( array(
									'ID'          => $listing_id,
									'post_status' => 'pending_payment',
								) );

								// Store the cancelled order ID for potential recovery
								update_post_meta( $listing_id, '_cancelled_package_order_id', $order_id );

								// Remove package association
								delete_post_meta( $listing_id, '_user_package_id' );

								// Remove listing expiry as it's no longer active
								delete_post_meta( $listing_id, '_listing_expires' );

								// Log activity
								do_action( 'listeo_core_listing_package_cancelled', $listing_id, $user_package_id, $order_id );
							}
						}

						// Delete the user package from database
						$wpdb->delete(
							"{$wpdb->prefix}listeo_core_user_packages",
							array( 'id' => $user_package_id ),
							array( '%d' )
						);
					}
				}
			}
		}

		// Mark as cancelled to prevent duplicate processing
		update_post_meta( $order_id, 'listeo_core_paid_listings_cancelled', true );

		// Remove the "processed" flag so the order can be re-processed if it's paid again
		delete_post_meta( $order_id, 'listeo_core_paid_listings_processed' );
	}


	/**
	 * Attached listings to the user package.
	 *
	 * @param array    $item
	 * @param WC_Order $order
	 * @param int      $user_package_id
	 */
	private function attach_package_listings( $item, $order, $user_package_id ) {
		global $wpdb;
		$listing_ids = (array) $wpdb->get_col( 
			$wpdb->prepare( 
				"SELECT post_id 
				FROM $wpdb->postmeta 
				WHERE meta_key=%s 
				AND meta_value=%s", '_cancelled_package_order_id', $order->get_id() ) );

		$listing_ids[] = isset( $item[ 'listing_id' ] ) ? $item[ 'listing_id' ] : '';
		$listing_ids   = array_unique( array_filter( array_map( 'absint', $listing_ids ) ) );

		foreach ( $listing_ids as $listing_id ) {
			if ( in_array( get_post_status( $listing_id ), array( 'pending_payment', 'expired' ) ) ) {
				listeo_core_approve_listing_with_package( $listing_id, $order->get_user_id(), $user_package_id );
				delete_post_meta( $listing_id, '_cancelled_package_order_id' );
			}

			if ( get_post_meta($listing_id, '_package_change', true) ){
				listeo_core_approve_listing_with_package( $listing_id, $order->get_user_id(), $user_package_id );
				$post_types_expiry = new Listeo_Core_Post_Types;
				$post_types_expiry->set_expiry(get_post($listing_id));
				delete_post_meta( $listing_id, '_package_change' );
			}
		}
	}


		/**
	 * Thanks page
	 *
	 * @param mixed $order_id
	 */
	public function woocommerce_thankyou( $order_id ) {
		global $wp_post_types;

		$order = wc_get_order( $order_id );

		foreach ( $order->get_items() as $item ) {
			if ( isset( $item['listing_id'] )  ) {
				switch ( get_post_status( $item['listing_id'] ) ) {
					case 'pending' :
						echo wpautop( sprintf( __( '<strong>%s</strong> has been submitted successfully and will be visible once approved.', 'listeo_core' ), get_the_title( $item['listing_id'] ) ) );
					break;
					case 'pending_payment' :
					case 'expired' :
						echo wpautop( sprintf( __( '<strong>%s</strong> has been submitted successfully and will be visible once payment has been confirmed.', 'listeo_core' ), get_the_title( $item['listing_id'] ) ) );
					break;
					default :
						echo wpautop( sprintf( __( '<strong>%s</strong> has been submitted successfully.', 'listeo_core' ), get_the_title( $item['listing_id'] ) ) );
					break;
				}
			} 
		}// End foreach().
	}
}

Listeo_Core_Orders::get_instance();