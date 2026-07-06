<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
* Listeo_Core_Paid_Listings_Subscriptions
*/
class Listeo_Core_Paid_Listings_Subscriptions {

	/** @var object Class Instance */
	private static $instance;

	/** @var array Cache for subscription check results to prevent repeated queries */
	private static $subscription_check_cache = array();

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
		
		if ( class_exists( 'WC_Subscriptions_Synchroniser' ) && method_exists( 'WC_Subscriptions_Synchroniser', 'save_subscription_meta' ) ) {
			add_action( 'woocommerce_process_product_meta_listing_package_subscription', 'WC_Subscriptions_Synchroniser::save_subscription_meta', 10 );
		}

		add_action( 'added_post_meta', array( $this, 'updated_post_meta' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'updated_post_meta' ), 20, 4 );
		add_filter( 'woocommerce_is_subscription', array( $this, 'woocommerce_is_subscription' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'wp_trash_post' ) );
		add_action( 'untrash_post', array( $this, 'untrash_post' ) );
		add_action( 'publish_to_expired', array( $this, 'check_expired_listing' ) );

		// Register our product type as switchable
		add_filter( 'wcs_is_product_switchable_type', array( $this, 'is_product_switchable_type' ), 10, 2 );

		// Subscription is paused
		add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'subscription_paused' ) ); // When a subscription is put on hold

		// Subscription is ended
		add_action( 'woocommerce_subscription_status_expired', array( $this, 'subscription_ended' ) ); // When a subscription expires
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'subscription_ended' ) ); // When the subscription status changes to cancelled

		// Subscription starts
		add_action( 'woocommerce_subscription_status_active', array( $this, 'subscription_activated' ) ); // When the subscription status changes to active

		// On renewal
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'subscription_renewed' ) ); // When the subscription is renewed

		// Subscription is switched
		add_action( 'woocommerce_subscriptions_switched_item', array( $this, 'subscription_switched' ), 10, 3 ); // When the subscription is switched and a new subscription is created
		add_action( 'woocommerce_subscription_item_switched', array( $this, 'subscription_item_switched' ), 10, 4 ); // When the subscription is switched and only the item is changed
	}

	/**
	 * Prevent listings linked to subscriptions from expiring.
	 *
	 * @param int         $meta_id
	 * @param int|WP_Post $object_id
	 * @param string      $meta_key
	 * @param mixed       $meta_value
	 */
	public function updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Only process _listing_expires meta key
		if ( '_listing_expires' !== $meta_key ) {
			return;
		}

		// CRITICAL: Only process actual listing posts, not orders/subscriptions/etc
		if ( 'listing' !== get_post_type( $object_id ) ) {
			return;
		}

		// Skip if already set to sentinel value or empty
		if ( '' === $meta_value || ' ' === $meta_value ) {
			return;
		}

		// CRITICAL FIX: Add static flag to prevent re-entry during our own update
		static $is_updating = array();
		if ( isset( $is_updating[ $object_id ] ) && $is_updating[ $object_id ] === true ) {
			return;
		}

		// Check cache first to avoid repeated queries
		if ( ! isset( self::$subscription_check_cache[ $object_id ] ) ) {
			self::$subscription_check_cache[ $object_id ] = $this->get_listing_subscription_order_id( $object_id );
		}

		if ( false !== self::$subscription_check_cache[ $object_id ] ) {
			// Set flag to prevent re-entry
			$is_updating[ $object_id ] = true;

			// Temporarily remove hooks to prevent infinite loop
			remove_action( 'added_post_meta', array( $this, 'updated_post_meta' ), 20 );
			remove_action( 'updated_post_meta', array( $this, 'updated_post_meta' ), 20 );

			update_post_meta( $object_id, '_listing_expires', ' ' ); // Never expire automatically

			// Re-add hooks
			add_action( 'added_post_meta', array( $this, 'updated_post_meta' ), 20, 4 );
			add_action( 'updated_post_meta', array( $this, 'updated_post_meta' ), 20, 4 );

			// Clear flag
			unset( $is_updating[ $object_id ] );
		}
	}


	/**
	 * Clear the subscription check cache for a specific listing or all listings
	 *
	 * @param int|null $listing_id Listing ID to clear cache for, or null to clear all
	 */
	private function clear_subscription_cache( $listing_id = null ) {
		if ( null === $listing_id ) {
			self::$subscription_check_cache = array();
		} else {
			unset( self::$subscription_check_cache[ $listing_id ] );
		}
	}

	/**
	 * If the job listing is tied to a subscription of type 'listing', return the order ID.
	 *
	 * @param int $job_id
	 *
	 * @return bool|int False if not found or is not the correct subscription type.
	 */
	private function get_listing_subscription_order_id( $listing_id ) {
		// Defensive check: ensure valid listing ID
		if ( empty( $listing_id ) || ! is_numeric( $listing_id ) ) {
			return false;
		}

		if ( 'listing' === get_post_type( $listing_id ) ) {
			$user_package_id = get_post_meta( $listing_id, '_user_package_id', true );

			// Early return if no package ID
			if ( empty( $user_package_id ) ) {
				return false;
			}

			$user_package    = listeo_core_get_user_package( $user_package_id );

			// Early return if package doesn't exist or has no package
			if ( ! $user_package || ! $user_package->has_package() ) {
				return false;
			}

			$package_id      = get_post_meta( $listing_id, '_package_id', true );

			// Early return if no product ID
			if ( empty( $package_id ) ) {
				return false;
			}

			$package         = wc_get_product( $package_id );

			// Check if it's a subscription package
			if ( $package && ( $package instanceof WC_Product_Listing_Package_Subscription) ) {
				return $user_package->get_order_id();
			}
		}
		return false;
	}

	/**
	 * get subscription type for package by ID
	 *
	 * @param  int $product_id
	 * @return string
	 */
	public function get_package_subscription_type( $product_id ) {
		$subscription_type = get_post_meta( $product_id, '_package_subscription_type', true );
		return empty( $subscription_type ) ? 'package' : $subscription_type;
	}

	/**
	 * Is this a subscription product?
	 *
	 * @param bool $is_subscription
	 * @param int  $product_id
	 * @return bool
	 */
	public function woocommerce_is_subscription( $is_subscription, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'listing_package_subscription') ) {
			$is_subscription = true;
		}
		return $is_subscription;
	}

	/**
	 * If a listing is expired, the pack may need it's listing count changing
	 *
	 * @param WP_Post $post
	 */
	public function check_expired_listing( $post ) {
		global $wpdb;

		if ( 'listing' === $post->post_type ) {
			$package_product_id = get_post_meta( $post->ID, '_package_id', true );
			$package_id         = get_post_meta( $post->ID, '_user_package_id', true );
			$package_product    = get_post( $package_product_id );

			if ( $package_product_id ) {
				$subscription_type = $this->get_package_subscription_type( $package_product_id );
				
				//if($subscription_type == 'listing_package_subscription') {

					$new_count = $wpdb->get_var( $wpdb->prepare( "SELECT package_count FROM {$wpdb->prefix}listeo_core_user_packages WHERE id = %d;", $package_id ) );
					$new_count --;

					$wpdb->update(
						"{$wpdb->prefix}listeo_core_user_packages",
						array(
							'package_count'  => max( 0, $new_count ),
						),
						array(
							'id' => $package_id,
						)
					);

					// Remove package meta after adjustment
					delete_post_meta( $post->ID, '_package_id' );
					delete_post_meta( $post->ID, '_user_package_id' );
				//}
				
				
				
			}
		}
	}

	/**
	 * If a listing gets trashed/deleted, the pack may need it's listing count changing
	 *
	 * @param int $id
	 */
	public function wp_trash_post( $id ) {
		global $wpdb;

		if ( $id > 0 ) {
			$post_type = get_post_type( $id );

			if ( 'listing' === $post_type  ) {
				$package_product_id = get_post_meta( $id, '_package_id', true );
				$package_id         = get_post_meta( $id, '_user_package_id', true );
				$package_product    = get_post( $package_product_id );

				if ( $package_product_id ) {
					
					
					$new_count = $wpdb->get_var( $wpdb->prepare( "SELECT package_count FROM {$wpdb->prefix}listeo_core_user_packages WHERE id = %d;", $package_id ) );
					$new_count --;

					$wpdb->update(
						"{$wpdb->prefix}listeo_core_user_packages",
						array(
							'package_count'  => max( 0, $new_count ),
						),
						array(
							'id' => $package_id,
						)
					);
					
				}
			}
		}
	}

/**
 * If a listing gets restored, the pack may need it's listing count changing
 *
 * @param int $id
 */
public function untrash_post( $id ) {
	global $wpdb;

	if ( $id > 0 ) {
		$post_type = get_post_type( $id );

		if ( 'listing' === $post_type) {
			$package_product_id = get_post_meta( $id, '_package_id', true );
			$package_id         = get_post_meta( $id, '_user_package_id', true );
			$package_product    = get_post( $package_product_id );

			if ( $package_product_id ) {
				
				
				$package  = $wpdb->get_row( $wpdb->prepare( "SELECT package_count, package_limit FROM {$wpdb->prefix}listeo_core_user_packages WHERE id = %d;", $package_id ) );
				$new_count = $package->package_count + 1;

				$wpdb->update(
					"{$wpdb->prefix}listeo_core_user_packages",
					array(
						'package_count'  => min( $package->package_limit, $new_count ),
					),
					array(
						'id' => $package_id,
					)
				);
				
			}
		}
	}
}

/**
 * Subscription is on-hold for payment. Suspend package and listings.
 *
 * @param WC_Subscription $subscription
 */
public function subscription_paused( $subscription ) {
	$this->subscription_ended( $subscription, true );
}

/**
 * Subscription has expired - cancel job packs
 *
 * @param WC_Subscription $subscription
 * @param bool            $paused
 */
public function subscription_ended( $subscription, $paused = false ) {
	global $wpdb;

	// Clear cache since subscription status is changing
	$this->clear_subscription_cache();

	foreach ( $subscription->get_items() as $item ) {
		

		/**
		 * @var WC_Order $parent
		 */
		
		$parent            = $subscription->get_parent();
		$parent_id         = ! empty( $parent ) ?  $parent->get_id() : null;
		$legacy_id         = isset( $parent_id ) ? $parent_id : $subscription->get_id();
		$user_package      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE order_id IN ( %d, %d ) AND product_id = %d;", $subscription->get_id(), $legacy_id, $item['product_id'] ) );

		if ( $user_package ) {
			// Delete the package
			$wpdb->delete(
				"{$wpdb->prefix}listeo_core_user_packages",
				array(
					'id' => $user_package->id,
				)
			);

			// Expire listings posted with package
			
			$listing_ids = listeo_core_get_listings_for_package( $user_package->id );
				
			foreach ( $listing_ids as $listing_id ) {

					if ( $paused ) {
						// Record the current post status in case subscription is resumed
						update_post_meta( $listing_id, '_post_status_before_package_pause', get_post_status( $listing_id ) );
					} else {
						delete_post_meta( $listing_id, '_post_status_before_package_pause' );
					}
					$listing = array(
						'ID' => $listing_id,
						'post_status' => 'expired',
					);
					
					wp_update_post( $listing );

					// Make a record of the subscription ID in case of re-activation
					update_post_meta( $listing_id, '_expired_subscription_id', $subscription->get_id() );
				}
			
		}
	}// End foreach().

	delete_post_meta(  $subscription->get_id(), 'listeo_core_subscription_packages_processed' );
}

/**
 * Subscription activated.
 *
 * Idempotent gap-fill: ensure every listing_package_subscription line item
 * has its expected row(s) in wp_listeo_core_user_packages, without ever
 * deleting existing rows. Switched items are NOT skipped — the persistent
 * `switched_subscription_item_id` line-item meta would otherwise lock
 * subscribers out forever once a row is lost (e.g. an on-hold -> active
 * cycle during a Stripe renewal of a free->paid switch), since
 * `subscription_switched()` only fires at the actual switch event and
 * never recovers later.
 *
 * @param WC_Subscription $subscription
 */
public function subscription_activated( $subscription ) {
	global $wpdb;

	// Clear cache since we're activating subscriptions
	$this->clear_subscription_cache();

	$user_id = (int) $subscription->get_user_id();
	if ( ! $user_id ) {
		update_post_meta( $subscription->get_id(), 'listeo_core_subscription_packages_processed', true );
		return;
	}

	$parent    = $subscription->get_parent();
	$parent_id = ! empty( $parent ) ? $parent->get_id() : null;
	$legacy_id = isset( $parent_id ) ? $parent_id : $subscription->get_id();

	foreach ( $subscription->get_items() as $item ) {
		$product = wc_get_product( $item['product_id'] );

		if ( ! $product || ! $product->is_type( array( 'listing_package_subscription' ) ) ) {
			continue;
		}

		$expected_qty = isset( $item['qty'] ) ? max( 1, (int) $item['qty'] ) : 1;

		// Existing rows for this subscription (legacy/parent or current id) + product.
		$existing_ids = (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}listeo_core_user_packages WHERE order_id IN ( %d, %d ) AND product_id = %d ORDER BY id ASC;",
			$subscription->get_id(),
			$legacy_id,
			$item['product_id']
		) );

		$rows_to_create = max( 0, $expected_qty - count( $existing_ids ) );

		// Gap-fill: create only the missing rows. listeo_core_give_user_package()
		// is idempotent on (user, product, order, full feature signature) and
		// returns the existing row id when features match — so a row created by
		// `subscription_switched()` immediately before this call is reused, not
		// duplicated.
		$created_ids = array();
		for ( $i = 0; $i < $rows_to_create; $i ++ ) {
			$new_id = listeo_core_give_user_package( $user_id, $product->get_id(), $subscription->get_id() );
			if ( $new_id ) {
				$created_ids[] = (int) $new_id;
			}
		}

		$user_package_ids = array_values( array_unique( array_filter( array_map( 'absint', array_merge( $existing_ids, $created_ids ) ) ) ) );
		if ( empty( $user_package_ids ) ) {
			continue;
		}

		$primary_user_package_id = $user_package_ids[0];

		// Re-approve listings tagged with _expired_subscription_id for this sub
		// (typical pause/cancel -> resume flow). approve_listing_with_package
		// restores post status from `_post_status_before_package_pause` and
		// increments package_count atomically.
		$tagged_listing_ids   = (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", '_expired_subscription_id', $subscription->get_id() ) );
		$tagged_listing_ids[] = isset( $item['listing_id'] ) ? $item['listing_id'] : '';
		$tagged_listing_ids   = array_unique( array_filter( array_map( 'absint', $tagged_listing_ids ) ) );

		foreach ( $tagged_listing_ids as $listing_id ) {
			if ( in_array( get_post_status( $listing_id ), array( 'pending_payment', 'expired', 'publish' ), true ) ) {
				listeo_core_approve_listing_with_package( $listing_id, $user_id, $primary_user_package_id );
				delete_post_meta( $listing_id, '_expired_subscription_id' );
			}
		}

		// Defensive orphan re-link for listings whose `_user_package_id` points
		// at a row that no longer exists (data loss from prior buggy deletes,
		// manual DB edits, or a renewal pause cycle on a switched item that
		// historically couldn't be self-healed). Without this, customers who
		// already lost rows under the old code stay broken until they re-buy.
		$this->relink_orphan_listings( $user_id, (int) $subscription->get_id(), (int) $item['product_id'], $user_package_ids );
	}

	update_post_meta( $subscription->get_id(), 'listeo_core_subscription_packages_processed', true );
}

/**
 * Re-link listings whose `_user_package_id` references a row that no longer
 * exists in wp_listeo_core_user_packages, distributing them across the
 * caller's verified row ids while respecting each row's package_limit
 * (0 = unlimited).
 *
 * Used on activation, renewal, and the no-old-row recovery path of
 * `switch_package()` to recover orphans from any past row-loss event without
 * touching listings whose `_user_package_id` is still valid.
 *
 * @param int    $user_id          Subscription owner.
 * @param int    $subscription_id  Subscription id used to match `_expired_subscription_id` meta.
 * @param int    $product_id       Product id used to scope orphan detection via `_package_id`.
 * @param int[]  $user_package_ids Verified row ids to assign orphans to.
 */
private function relink_orphan_listings( $user_id, $subscription_id, $product_id, $user_package_ids ) {
	global $wpdb;

	$user_id         = (int) $user_id;
	$subscription_id = (int) $subscription_id;
	$product_id      = (int) $product_id;

	if ( ! $user_id || ! $product_id || empty( $user_package_ids ) ) {
		return;
	}

	// High-confidence: listings tagged with _expired_subscription_id for this sub
	// (set by subscription_ended on pause/cancel). At activation we already
	// re-approve these via approve_listing_with_package and clear the meta — by
	// the time we reach this helper there, none should remain. At renewal they
	// can still show up if a sub renews without a separate activation event.
	$tagged_ids = array();
	if ( $subscription_id ) {
		$tagged_ids = (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT pm.post_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND pm.meta_value = %s
			   AND p.post_author = %d",
			'_expired_subscription_id',
			$subscription_id,
			$user_id
		) );
	}

	// Fallback: listings whose _user_package_id references a non-existent row,
	// scoped to this user + product via _package_id. REGEXP guard prevents
	// MySQL's silent string->int cast (e.g. '5abc'->5) producing false-negative
	// orphan detection; CAST(... AS UNSIGNED) on the JOIN avoids relying on
	// collation for the comparison.
	$orphan_query_ids = (array) $wpdb->get_col( $wpdb->prepare(
		"SELECT p.ID
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} pm_pid
			 ON pm_pid.post_id = p.ID
			 AND pm_pid.meta_key = '_package_id'
			 AND pm_pid.meta_value = %d
		 INNER JOIN {$wpdb->postmeta} pm_upid
			 ON pm_upid.post_id = p.ID
			 AND pm_upid.meta_key = '_user_package_id'
			 AND pm_upid.meta_value REGEXP '^[1-9][0-9]*$'
		 LEFT JOIN {$wpdb->prefix}listeo_core_user_packages up
			 ON up.id = CAST( pm_upid.meta_value AS UNSIGNED )
		 WHERE p.post_author = %d
		   AND up.id IS NULL",
		$product_id,
		$user_id
	) );

	$orphan_ids = array_unique( array_filter( array_map( 'absint', array_merge( $tagged_ids, $orphan_query_ids ) ) ) );

	if ( empty( $orphan_ids ) ) {
		return;
	}

	// Build slot table: each row contributes its package_limit (0 = unlimited).
	// Orphans fill slots in order; we never assign more than `limit` listings
	// to any single row.
	$slots = array();
	foreach ( $user_package_ids as $upid ) {
		$pkg = listeo_core_get_user_package( $upid );
		if ( ! $pkg || ! $pkg->has_package() ) {
			continue;
		}
		$slots[] = array(
			'id'       => (int) $upid,
			'limit'    => (int) $pkg->get_limit(),
			'assigned' => 0,
		);
	}

	if ( empty( $slots ) ) {
		return;
	}

	foreach ( $orphan_ids as $listing_id ) {
		foreach ( $slots as $idx => $slot ) {
			if ( 0 === $slot['limit'] || $slot['assigned'] < $slot['limit'] ) {
				update_post_meta( $listing_id, '_user_package_id', $slot['id'] );
				delete_post_meta( $listing_id, '_expired_subscription_id' );
				listeo_core_sync_listing_with_package_features( $listing_id, $slot['id'] );
				$slots[ $idx ]['assigned']++;
				break;
			}
		}
	}

	// Bulk-increment package_count per row in one UPDATE each. Avoids the
	// per-call cache-reset bug in listeo_core_increase_package_count() when
	// the same row is incremented repeatedly in one request.
	foreach ( $slots as $slot ) {
		if ( $slot['assigned'] > 0 ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}listeo_core_user_packages
				 SET package_count = package_count + %d
				 WHERE id = %d AND user_id = %d",
				$slot['assigned'],
				$slot['id'],
				$user_id
			) );
		}
	}
}

/**
 * Subscription renewed - renew the job pack
 *
 * @param WC_Subscription $subscription
 */
public function subscription_renewed( $subscription ) {
	global $wpdb;

	// Clear cache since subscription status is changing
	$this->clear_subscription_cache();

	foreach ( $subscription->get_items() as $item ) {
		$product           = wc_get_product( $item['product_id'] );
		$subscription_type = $this->get_package_subscription_type( $item['product_id'] );
		$parent            = $subscription->get_parent();
		$parent_id         = ! empty( $parent ) ? $parent->get_id() : null;
		$legacy_id         = isset( $parent_id ) ? $parent_id : $subscription->get_id();

		// Renew packages which refresh every term


			// Otherwise the listings stay active, but we can ensure they are synced in terms of featured status etc
		$user_package_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}listeo_core_user_packages WHERE order_id IN ( %d, %d ) AND product_id = %d;", $subscription->get_id(), $legacy_id, $item['product_id'] ) );

		// Self-heal: a paid renewal should always leave the user with an active
		// package row. If the row is missing (data loss, manual deletion, partial
		// cleanup, on-hold->active cycle on a switched item), recreate it now
		// so the subscription doesn't silently lock the user out of their listings.
		// `switched_subscription_item_id` is intentionally NOT excluded here —
		// items that came from a switch keep that meta forever, so excluding them
		// would permanently disable self-heal for free->paid switchers after
		// their first row-loss event.
		if ( empty( $user_package_ids ) && $product && $product->is_type( array( 'listing_package_subscription' ) ) && $subscription->get_user_id() ) {
			$qty = isset( $item['qty'] ) ? max( 1, (int) $item['qty'] ) : 1;
			for ( $i = 0; $i < $qty; $i ++ ) {
				$new_id = listeo_core_give_user_package( $subscription->get_user_id(), $item['product_id'], $subscription->get_id() );
				if ( $new_id ) {
					$user_package_ids[] = $new_id;
				}
			}

			// Re-link listings orphaned by the lost row. Without this, listings keep
			// pointing at a user_package id that no longer exists, surfacing as
			// "Invalid plan" on edit screens; package_count on the new row stays at 0
			// and _featured never resyncs.
			$this->relink_orphan_listings( (int) $subscription->get_user_id(), (int) $subscription->get_id(), (int) $item['product_id'], $user_package_ids );
		}

		if ( ! empty( $user_package_ids ) ) {
			foreach ( $user_package_ids as $user_package_id ) {
				$package = listeo_core_get_user_package( $user_package_id );

				if ( $listing_ids = listeo_core_get_listings_for_package( $user_package_id ) ) {
					foreach ( $listing_ids as $listing_id ) {

						// Featured or not
						update_post_meta( $listing_id, '_featured', $package->is_featured() ? 1 : 0 );
					}
				}
			}
		}

	}// End foreach().
}

/**
 * When switching a subscription we need to update old listings.
 *
 * No need to give the user a new package; that is still handled by the orders class.
 *
 * @param WC_Order        $order
 * @param WC_Subscription $subscription
 * @param int             $new_order_item_id
 * @param int             $old_order_item_id
 */
public function subscription_item_switched( $order, $subscription, $new_order_item_id, $old_order_item_id ) {
	global $wpdb;

	$new_order_item = WC_Subscriptions_Order::get_item_by_id( $new_order_item_id );
	$old_order_item = WC_Subscriptions_Order::get_item_by_id( $old_order_item_id );

	$new_subscription = (object) array(
		'id'           => $subscription->get_id(),
		'subscription' => $subscription,
		'product_id'   => $new_order_item['product_id'],
		'product'      => wc_get_product( $new_order_item['product_id'] ),
		'type'         => $this->get_package_subscription_type( $new_order_item['product_id'] ),
	);

	$old_subscription = (object) array(
		'id'           => $subscription->get_id(),
		'subscription' => $subscription,
		'product_id'   => $old_order_item['product_id'],
		'product'      => wc_get_product( $old_order_item['product_id'] ),
		'type'         => $this->get_package_subscription_type( $old_order_item['product_id'] ),
	);

	$this->switch_package( $subscription->get_user_id(), $new_subscription, $old_subscription );
}

/**
 * When switching a subscription we need to update old listings.
 *
 * No need to give the user a new package; that is still handled by the orders class.
 *
 * @param WC_Subscription $subscription
 * @param array           $new_order_item
 * @param array           $old_order_item
 */
public function subscription_switched( $subscription, $new_order_item, $old_order_item ) {
	global $wpdb;

	$new_subscription = (object) array(
		'id'         => $subscription->get_id(),
		'product_id' => $new_order_item['product_id'],
		'product'    => wc_get_product( $new_order_item['product_id'] ),
		'type'       => $this->get_package_subscription_type( $new_order_item['product_id'] ),
	);

	$old_subscription = (object) array(
		'id'         => $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d ", $new_order_item['switched_subscription_item_id'] ) ),
		'product_id' => $old_order_item['product_id'],
		'product'    => wc_get_product( $old_order_item['product_id'] ),
		'type'       => $this->get_package_subscription_type( $old_order_item['product_id'] ),
	);

	$this->switch_package( $subscription->get_user_id(), $new_subscription, $old_subscription );
}

/**
 * Handle Switch Event
 *
 * @param int      $user_id
 * @param stdClass $new_subscription
 * @param stdClass $old_subscription
 */
public function switch_package( $user_id, $new_subscription, $old_subscription ) {
	global $wpdb;

	// Get the user package
	/**
	 * @var null|WC_Subscription $parent
	 */
	$parent            = isset( $old_subscription->subscription ) ? $old_subscription->subscription->get_parent() : null;
	$parent_id         = ! empty( $parent ) ? $parent->get_id() : null;
	$legacy_id         = isset( $parent_id ) ? $parent_id : $old_subscription->id;
	$user_package      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE order_id IN ( %d, %d ) AND product_id = %d;", $old_subscription->id, $legacy_id, $old_subscription->product_id ) );

	// If the new product isn't one of ours, nothing for us to do.
	if ( ! $new_subscription->product || ! $new_subscription->product->is_type( array( 'listing_package_subscription' ) ) ) {
		return false;
	}

	// Give new package to user (idempotent — returns existing row if
	// subscription_activated already created one before this hook fired).
	$switching_to_package_id = listeo_core_give_user_package( $user_id, $new_subscription->product_id, $new_subscription->id );

	if ( $user_package ) {
		// Standard switch path: an old user_package row exists for the previous product.
		$is_upgrade = ( 0 === $new_subscription->product->get_limit() || $new_subscription->product->get_limit() >= $user_package->package_count );

		// Delete the old package
		$wpdb->delete( "{$wpdb->prefix}listeo_core_user_packages", array(
			'id' => $user_package->id,
		) );

		// Update old listings
		$listing_ids = listeo_core_get_listings_for_package( $user_package->id );

		foreach ( $listing_ids as $listing_id ) {
			// If we are not upgrading, expire the old listing
			if ( ! $is_upgrade ) {
				$listing = array(
					'ID' => $listing_id,
					'post_status' => 'expired',
				);
				wp_update_post( $listing );
			} else {
				// Change the user package ID and package ID
				update_post_meta( $listing_id, '_user_package_id', $switching_to_package_id );
				update_post_meta( $listing_id, '_package_id', $new_subscription->product_id );

				// Sync all package features to the listing (booking, opening_hours,
				// gallery, reviews, etc.) so features are enabled/disabled based on
				// the new package.
				listeo_core_sync_listing_with_package_features( $listing_id, $switching_to_package_id );
			}

			// Fire action
			do_action( 'wc_paid_listings_switched_subscription', $listing_id, $user_package );
		}
	} else {
		// Recovery path: no old user_package row found (free plan never had a
		// row, or the row was already deleted). Without this fall-through,
		// listings that were posted under the old plan would silently keep a
		// `_user_package_id` pointing at a missing row -> "Invalid Package".
		// Re-link any orphans by author + old_product_id to the new row,
		// respecting the new package's limit.
		if ( $switching_to_package_id ) {
			$this->relink_orphan_listings(
				(int) $user_id,
				(int) ( isset( $old_subscription->id ) ? $old_subscription->id : 0 ),
				(int) $old_subscription->product_id,
				array( (int) $switching_to_package_id )
			);
		}
	}
}

	/**
	 * Mark listing package subscriptions as switchable product type
	 *
	 * @param bool $is_switchable
	 * @param string $product_type
	 * @return bool
	 */
	public function is_product_switchable_type( $is_switchable, $product_type ) {
		if ( 'listing_package_subscription' === $product_type ) {
			return true;
		}
		return $is_switchable;
	}
}

Listeo_Core_Paid_Listings_Subscriptions::get_instance();