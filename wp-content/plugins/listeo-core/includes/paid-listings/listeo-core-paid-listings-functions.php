<?php

/**
 * Give a user a package
 *
 * @param  int $user_id
 * @param  int $product_id
 * @param  int $order_id
 * @return int|bool false
 */
function listeo_core_give_user_package( $user_id, $product_id, $order_id = 0 ) {
	global $wpdb;

	$package = wc_get_product( $product_id );
	if ( ! $package->is_type( 'listing_package' ) && ! $package->is_type( 'listing_package_subscription' ) ) {
		return false;
	}

	$is_featured = false;
	$is_featured = $package->is_listing_featured();
	$has_booking = $package->has_listing_booking();
	$has_reviews = $package->has_listing_reviews();
	$has_gallery = $package->has_listing_gallery();
	$has_social_links = $package->has_listing_social_links();
	$has_opening_hours = $package->has_listing_opening_hours();
	$has_pricing_menu = $package->has_listing_pricing_menu();
	$has_video = $package->has_listing_video();
	$has_coupons = $package->has_listing_coupons();
	$has_faq = $package->has_listing_faq();
	$has_dokan_store = $package->has_listing_dokan_store();


	$id = $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}listeo_core_user_packages WHERE
			user_id = %d
			AND product_id = %d
			AND order_id = %d
			AND package_duration = %d
			AND package_limit = %d
			AND package_featured = %d
			AND package_option_booking = %d
			AND	package_option_reviews = %d
			AND	package_option_gallery  = %d
			AND	package_option_gallery_limit  = %d
			AND	package_option_social_links  = %d
			AND	package_option_opening_hours  = %d
			AND package_option_pricing_menu = %d
			AND	package_option_video   = %d
			AND	package_option_coupons = %d
			AND	package_option_faq = %d
			AND	package_option_dokan_store = %d",
			$user_id,
			$product_id,
			$order_id,
			$package->get_duration(),
			$package->get_limit(),
			$is_featured ? 1 : 0,
			$has_booking ? 1 : 0,
			$has_reviews ? 1 : 0,
			$has_gallery  ? 1 : 0,
			$package->get_option_gallery_limit(),
			$has_social_links ? 1 : 0,
			$has_opening_hours ? 1 : 0,
			$has_pricing_menu ? 1 : 0,
			$has_video ? 1 : 0,
			$has_coupons? 1 : 0,
			$has_faq? 1 : 0,
			$has_dokan_store ? 1 : 0
		));
		
	if ( $id ) {
		return $id;
	}

	// Calculate Dokan store expiry date if applicable
	$dokan_store_expires = null;
	if ($has_dokan_store) {
		$dokan_duration = $package->get_dokan_store_duration();
		if ($dokan_duration > 0) {
			$dokan_store_expires = date('Y-m-d H:i:s', strtotime('+' . $dokan_duration . ' days'));
		}
	}

	$wpdb->insert(
		"{$wpdb->prefix}listeo_core_user_packages",
		array(
			'user_id'          				=> $user_id,
			'product_id'       				=> $product_id,
			'order_id'         				=> $order_id,
			'package_count'    				=> 0,
			'package_duration' 				=> $package->get_duration(),
			'package_limit'    				=> $package->get_limit(),
			'package_featured' 				=> $is_featured ? 1 : 0,
			'package_option_booking' 		=> $has_booking ? 1 : 0,
			'package_option_reviews' 		=> $has_reviews ? 1 : 0,
			'package_option_gallery' 		=> $has_gallery ? 1 : 0,
			'package_option_gallery_limit' 	=> $package->get_option_gallery_limit(),
			'package_option_social_links' 	=> $has_social_links ? 1 : 0,
			'package_option_opening_hours'  => $has_opening_hours ? 1 : 0,
			'package_option_pricing_menu'   => $has_pricing_menu ? 1 : 0,
			'package_option_video'   		=> $has_video ? 1 : 0,
			'package_option_coupons' 		=> $has_coupons ? 1 : 0,
			'package_option_faq' 			=> $has_faq ? 1 : 0,
			'package_option_dokan_store' 	=> $has_dokan_store ? 1 : 0,
			'dokan_store_expires' 			=> $dokan_store_expires
		)
	);

	$insert_id = $wpdb->insert_id;

	// Auto-assign seller role if package has Dokan store access and option is enabled
	if ( $has_dokan_store && $insert_id ) {
		listeo_maybe_assign_seller_role( $user_id );
	}

	return $insert_id;
}

/**
 * Maybe assign seller role to user when purchasing Dokan package.
 *
 * Checks if the auto-assign option is enabled and assigns the 'seller' role
 * to the user if they don't already have it.
 *
 * @since 2.5.0
 * @param int $user_id The user ID to potentially assign the role to.
 * @return bool True if role was assigned, false otherwise.
 */
function listeo_maybe_assign_seller_role( $user_id ) {
	// Check if Dokan is active
	if ( ! class_exists( 'WeDevs_Dokan' ) ) {
		return false;
	}

	// Check if auto-assign option is enabled
	$auto_assign = get_option( 'listeo_dokan_auto_assign_seller_role' );
	if ( ! $auto_assign ) {
		return false;
	}

	// Get the user
	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		return false;
	}

	// Check if user already has seller role
	if ( in_array( 'seller', (array) $user->roles, true ) ) {
		return false; // Already a seller
	}

	// Assign seller role
	$user->set_role( 'seller' );

	// Trigger action for custom integrations
	do_action( 'listeo_user_assigned_seller_role', $user_id );

	return true;
}


/**
 * See if a package is valid for use
 *
 * @param int $user_id
 * @param int $package_id
 * @return bool
 */
function listeo_core_package_is_valid( $user_id, $package_id ) {
	global $wpdb;

	$package = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE user_id = %d AND id = %d;", $user_id, $package_id ) );

	if ( ! $package ) {
		return false;
	}

	if ( $package->package_count >= $package->package_limit && $package->package_limit != 0 ) {
		return false;
	}

	return true;
}



/**
 * Increase job count for package
 *
 * @param  int $user_id
 * @param  int $package_id
 * @return int affected rows
 */
function listeo_core_increase_package_count( $user_id, $package_id ) {
	global $wpdb;

	$packages = listeo_core_user_packages( $user_id );

	if ( isset( $packages[ $package_id ] ) ) {
		$new_count = $packages[ $package_id ]->package_count + 1;
	} else {
		$new_count = 1;
	}

	return $wpdb->update(
		"{$wpdb->prefix}listeo_core_user_packages",
		array(
			'package_count' => $new_count,
		),
		array(
			'user_id' => $user_id,
			'id'      => $package_id,
		),
		array( '%d' ),
		array( '%d', '%d' )
	);
}

/**
 * decrease job count for package
 *
 * @param  int $user_id
 * @param  int $package_id
 * @return int affected rows
 */
function listeo_core_decrease_package_count( $user_id, $package_id ) {
	global $wpdb;

	$packages = listeo_core_user_packages( $user_id );

	if ( isset( $packages[ $package_id ] ) ) {
		$new_count = $packages[ $package_id ]->package_count - 1;
	} 
	if($new_count < 0){
		$new_count = 0;
	}

	if(isset($new_count)) {

		return $wpdb->update(
			"{$wpdb->prefix}listeo_core_user_packages",
			array(
				'package_count' => $new_count,
			),
			array(
				'user_id' => $user_id,
				'id'      => $package_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}
}




/**
 * Get a users packages from the DB
 *
 * @param  int          $user_id
 * @param string|array $package_type
 * @return array of objects
 */
function listeo_core_user_packages( $user_id ) {
	global $wpdb;

	
	$package_type = array( 'listing_package' );


	$packages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE user_id = %d AND ( package_count < package_limit OR package_limit = 0 );", $user_id ), OBJECT_K );

	return $packages;
}

function listeo_core_get_package_by_id($id){
	global $wpdb;

	$packages = 
	$wpdb->get_row( 
		$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE id = %d ;", $id )
	);

	return $packages;
}

/**
 * Get a package
 *
 * @param  stdClass $package
 * @return listeo_core__Package
 */
function listeo_core_get_package( $package ) {
	return new Listeo_Core_Paid_Listings_Package( $package );
}



function listeo_core_available_packages( $user_id, $selected ) {
	global $wpdb;

	
	$package_type = array( 'listing_package' );


	$packages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE user_id = %d AND ( package_count < package_limit OR package_limit = 0 );", $user_id ), ARRAY_A );
	
	$options = '<option  '.selected('',$selected,false).' value="">No package assigned</option>';
	if($packages){
		foreach ($packages as $row) {
			
			$options .= '<option '.selected($row['id'],$selected,false).' value="'.$row['id'].'">'.get_the_title( $row['product_id']).' (orderID:'.$row['order_id'].')</option>';
			# code...
		}
	}
	
	return $options;
}



/**
 * Approve a listing
 *
 * @param  int $listing_id
 * @param  int $user_id
 * @param  int $user_package_id
 * @return void
 */
function listeo_core_approve_listing_with_package( $listing_id, $user_id, $user_package_id ) {
	if ( listeo_core_package_is_valid( $user_id, $user_package_id ) ) {
		$resumed_post_status = get_post_meta( $listing_id, '_post_status_before_package_pause', true );
		if ( ! empty( $resumed_post_status ) ) {
			$listing = array(
				'ID'            => $listing_id,
				'post_status'   => $resumed_post_status,
			);
			delete_post_meta( $listing_id, '_post_status_before_package_pause' );
		} else {
			$listing = array(
				'ID'            => $listing_id,
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', 1 ),
			);

			switch ( get_post_type( $listing_id ) ) {
				case 'listing' :
					delete_post_meta( $listing_id, '_listing_expires' );
					$listing[ 'post_status' ] = get_option( 'listeo_new_listing_requires_approval' ) ? 'pending' : 'publish';
					break;
				
			}
		}

		// Do update
		wp_update_post( $listing );
		update_post_meta( $listing_id, '_user_package_id', $user_package_id );
		$expire_obj = new Listeo_Core_Post_Types;
		$expire_obj->set_expiry(get_post($listing_id));
		listeo_core_increase_package_count( $user_id, $user_package_id );
		
		// Update listing meta fields based on package features
		listeo_core_sync_listing_with_package_features( $listing_id, $user_package_id );
		
	}
}

/**
 * Sync listing meta fields with package features
 *
 * This function enforces package restrictions by DISABLING features that
 * the package doesn't support. It does NOT auto-enable features - that's
 * controlled by the user during listing submission.
 *
 * Use case: When user switches from a package with booking to one without,
 * this disables booking. But it won't auto-enable booking just because
 * a package supports it.
 *
 * @param  int $listing_id
 * @param  int $user_package_id
 * @return void
 */
function listeo_core_sync_listing_with_package_features( $listing_id, $user_package_id ) {
	// Get the package
	$package = listeo_core_get_user_package( $user_package_id );

	if ( ! $package || ! $package->has_package() ) {
		return;
	}

	// PERFORMANCE: Suspend cache invalidation during bulk meta updates
	wp_suspend_cache_invalidation( true );

	// Sync featured status - this IS auto-enabled because it's a premium perk the user pays for
	if ( $package->is_featured() ) {
		update_post_meta( $listing_id, '_featured', 'on' );
	} else {
		delete_post_meta( $listing_id, '_featured' );
	}

	// Opening hours - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_opening_hours() ) {
		update_post_meta( $listing_id, '_opening_hours_status', '' );
	}

	// Booking - only DISABLE if package doesn't support it
	// User controls enabling this via the listing form
	if ( ! $package->has_listing_booking() ) {
		update_post_meta( $listing_id, '_booking_status', '' );
	}

	// Reviews - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_reviews() ) {
		update_post_meta( $listing_id, '_reviews_status', '' );
	}

	// Social links - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_social_links() ) {
		update_post_meta( $listing_id, '_social_status', '' );
	}

	// Video - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_video() ) {
		update_post_meta( $listing_id, '_video_status', '' );
	}

	// Gallery - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_gallery() ) {
		update_post_meta( $listing_id, '_gallery_status', '' );
		delete_post_meta( $listing_id, '_gallery_limit' );
	} else {
		// Update gallery limit if package supports gallery
		$gallery_limit = $package->get_option_gallery_limit();
		if ( $gallery_limit > 0 ) {
			update_post_meta( $listing_id, '_gallery_limit', $gallery_limit );
		}
	}

	// Coupons - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_coupons() ) {
		update_post_meta( $listing_id, '_coupons_status', '' );
	}

	// FAQ - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_faq() ) {
		update_post_meta( $listing_id, '_faq_status', '' );
	}

	// Pricing menu - only DISABLE if package doesn't support it
	if ( ! $package->has_listing_pricing_menu() ) {
		update_post_meta( $listing_id, '_menu_status', '' );
	}

	// Resume cache invalidation
	wp_suspend_cache_invalidation( false );

	// Allow other plugins to hook into the sync process
	do_action( 'listeo_core_sync_listing_package_features', $listing_id, $user_package_id, $package );
}

/**
 * Get a package
 *
 * @param  int $package_id
 * @return listeo_core_Package
 */
function listeo_core_get_user_package( $package_id ) {
	global $wpdb;

	$package = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}listeo_core_user_packages WHERE id = %d;", $package_id ) );
	return listeo_core_get_package( $package );
}
/**
 * Get listing IDs for a user package
 *
 * @return array
 */
function listeo_core_get_listings_for_package( $user_package_id ) {
	global $wpdb;

	return $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} " .
		"LEFT JOIN {$wpdb->posts} ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID " .
		"WHERE meta_key = '_user_package_id' " .
		'AND meta_value = %s;'
	, $user_package_id ) );
}

function listeo_core_get_dashboard_pages_list(){
	$pages = array(
		'listeo_dashboard_page' => array('title' => 'Dashboard', 'content' => '[listeo_dashboard]', 'option' => 'listeo_dashboard_page',),
		'listeo_messages_page' => array('title' => 'Messages', 'content' => '[listeo_messages]', 'option' => 'listeo_messages_page',),
		'listeo_bookings_page' => array('title' => 'Bookings', 'content' => '[listeo_bookings]', 'option' => 'listeo_bookings_page',),
		'listeo_bookings_calendar_page' => array('title' => 'Calendar View', 'content' => '[listeo_calendar_view]', 'option' => 'listeo_bookings_calendar_page',),
		'listeo_user_bookings_page' => array('title' => 'My Bookings', 'content' => '[listeo_my_bookings]', 'option' => 'listeo_user_bookings_page',),
		'listeo_booking_confirmation_page' => array('title' => 'Booking Confirmation', 'content' => '[listeo_booking_confirmation]', 'option' => 'listeo_booking_confirmation_page',),
		'listeo_listings_page' => array('title' => 'My Listings', 'content' => '[listeo_my_listings]', 'option' => 'listeo_listings_page',),
		'listeo_wallet_page' => array('title' => 'Wallet', 'content' => '[listeo_wallet]', 'option' => 'listeo_wallet_page',),
		'listeo_reviews_page' => array('title' => 'Reviews', 'content' => '[listeo_reviews]', 'option' => 'listeo_reviews_page',),
		'listeo_bookmarks_page' => array('title' => 'Bookmarks', 'content' => '[listeo_bookmarks]', 'option' => 'listeo_bookmarks_page',),
		'listeo_submit_page' => array('title' => 'Add Listing', 'content' => '[listeo_submit_listing]', 'option' => 'listeo_submit_page',),
		'listeo_stats_page' => array('title' => 'Statistics', 'content' => '[listeo_stats_full]', 'option' => 'listeo_stats_page',),
		'listeo_profile_page' => array('title' => 'My profile', 'content' => '[listeo_my_account]', 'option' => 'listeo_profile_page',),
		'listeo_lost_password_page' => array('title' => 'Lost Password', 'content' => '[listeo_lost_password]', 'option' => 'listeo_lost_password_page',),
		'listeo_reset_password_page' => array('title' => 'Reset Password', 'content' => '[listeo_reset_password]', 'option' => 'listeo_reset_password_page',),
		'listeo_coupons_page' => array('title' => 'Coupons', 'content' => '[listeo_coupons_page]', 'option' => 'listeo_coupons_page',),
	);

	/**
	 * Allow plugins to register their own dashboard pages so they get:
	 * - A "Listeo: ..." post-state badge on the Pages list
	 * - Entries in the Site Health recreate-page tool
	 *
	 * Each entry must have: title, content (shortcode), option (WP option name storing the page ID).
	 */
	return apply_filters( 'listeo_core_dashboard_pages', $pages );
}



add_action('template_redirect', 'custom_clear_cart_if_specific_product_type_and_leave_checkout');

function custom_clear_cart_if_specific_product_type_and_leave_checkout() {

	// CRITICAL: Bypass cart logic during REST API calls
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return;
	}
	// Define the specific product type to check against
	$specific_product_type = 'listing_booking'; // Replace 'YOUR_PRODUCT_TYPE' with the desired product type slug

	if (class_exists('woocommerce') && WC()->cart) {
		// Check if the cart contains the specific product type
		$contains_specific_product_type = false;
		foreach (WC()->cart->get_cart() as $cart_item) {

			if ($cart_item['data']->is_type($specific_product_type)) {
				$contains_specific_product_type = true;
				break;
			}
		}

		// Clear the cart if it contains the specific product type
		if ($contains_specific_product_type) {
			WC()->cart->empty_cart();
		}
	}
}

/**
 * Return owned product IDs that are usable for new listings.
 * Includes products for packages that are unlimited (no listing limit)
 * or that have remaining capacity. Excludes products for fully used packages.
 * Accepts the mixed $user_packages array (objects or IDs) used across templates.
 */
function listeo_get_owned_available_product_ids($user_packages)
{
	$ids = array();
	if (empty($user_packages)) {
		return $ids;
	}
	foreach ((array) $user_packages as $pkg) {
		// Normalize to package object from core helpers when needed
		$up = is_object($pkg) && method_exists($pkg, 'get_id') ? $pkg : (function_exists('listeo_core_get_package') ? listeo_core_get_package($pkg) : null);
		if (! $up || ! method_exists($up, 'get_limit') || ! method_exists($up, 'get_count')) {
			continue;
		}
		if (! method_exists($up, 'get_product_id')) {
			continue;
		}
		$limit = (int) $up->get_limit();
		$count = (int) $up->get_count();
		// Include unlimited packages (limit == 0) or those with remaining slots
		if ($limit === 0 || ($limit > 0 && $count < $limit)) {
			$ids[] = (int) $up->get_product_id();
		}
	}
	return array_values(array_unique($ids));
}


/**
 * Return purchasable listing package product IDs available to the current user, applying:
 * - product type check (listing_package / listing_package_subscription)
 * - WooCommerce purchasable check
 * - optional allowed listing type restriction (via _allowed_listing_types meta)
 * - exclusion of products for which the user already owns an available package
 * - optional subscription limit check (wcs_is_product_limited_for_user)
 * - optional single-buy rule from listeo_buy_only_once
 */
function listeo_get_available_purchasable_product_ids($packages, $args = array())
{
	$defaults = array(
		'selected_type'      => '',            // listing type slug; if provided, respect product meta restrictions
		'exclude_product_ids' => array(),      // product IDs to exclude (e.g., already owned with remaining slots)
		'user_id'            => get_current_user_id(),
		'single_buy_products' => get_option('listeo_buy_only_once'),
		// When false, do not exclude products due to prior purchase; useful in selection screens to avoid empty UIs
		'enforce_single_buy' => true,
	);
	$args = wp_parse_args($args, $defaults);
	$selected_type = sanitize_title($args['selected_type']);
	$exclude = array_map('absint', (array) $args['exclude_product_ids']);
	$user_id = (int) $args['user_id'];
	$single_buy_products = (array) $args['single_buy_products'];
	$enforce_single_buy = (bool) $args['enforce_single_buy'];

	$out = array();
	foreach ((array) $packages as $p) {
		// Normalize to product ID
		$pid = 0;
		if (is_object($p)) {
			// WP_Post or WC_Product
			if (isset($p->ID)) {
				$pid = (int) $p->ID;
			}
			if (! $pid && method_exists($p, 'get_id')) {
				$pid = (int) $p->get_id();
			}
		} else {
			$pid = (int) $p;
		}
		if (! $pid) {
			continue;
		}

		if (! function_exists('wc_get_product')) {
			continue;
		}
		$product = wc_get_product($pid);
		if (! $product) {
			continue;
		}
		if (! $product->is_type(array('listing_package', 'listing_package_subscription'))) {
			continue;
		}
		if (! $product->is_purchasable()) {
			continue;
		}

		// Respect selected listing type restrictions encoded in product meta
		if ($selected_type) {
			$allowed = get_post_meta($pid, '_allowed_listing_types', true);
			
			if (! empty($allowed)) {
				if (! is_array($allowed)) {
					$allowed = (array) $allowed;
				}
		
				if (!in_array($selected_type, $allowed, true)) {
				
					continue;
				}
			}
		}



		
		// Exclude products for which user already has an available package
		if (in_array($pid, $exclude, true)) {
			continue;
		}

		// Subscription limited per user (if Subscriptions is active)
		if ($product->is_type(array('listing_package_subscription')) && function_exists('wcs_is_product_limited_for_user')) {
			if (wcs_is_product_limited_for_user($product, $user_id)) {
				continue;
			}
		}

		// One-time purchase restrictions (optional). If product is configured as single-buy
		// and the user previously bought it, exclude it from purchasable options.
		if ($enforce_single_buy && ! empty($single_buy_products)) {
			$single_buy_products = array_map('absint', $single_buy_products);
			if (in_array($pid, $single_buy_products, true)) {
				$user = wp_get_current_user();
				if ($user && function_exists('wc_customer_bought_product') && wc_customer_bought_product($user->user_email, $user->ID, $pid)) {
					continue;
				}
			}
		}

		$out[] = $pid;
	}

	return $out;
}


/**
 * Return displayable owned user packages for selection UIs.
 * Normalizes packages to core package objects, optionally excludes the
 * currently assigned user package, and hides used up packages.
 *
 * @param array $user_packages Raw user packages array (IDs/rows) keyed by user_package_id
 * @param array $args {
 *   Optional. Args to control filtering.
 *   @type int  $exclude_user_package_id A user_package_id to skip (e.g., current assignment)
 *   @type bool $hide_used_up            When true, hide packages where limit>0 and count>=limit
 * }
 * @return array user_package_id => core package object
 */
function listeo_get_displayable_owned_packages($user_packages, $args = array())
{
	$defaults = array(
		'exclude_user_package_id' => 0,
		'hide_used_up'            => true,
	);

	
	$args = wp_parse_args($args, $defaults);
	$out = array();
	if (empty($user_packages)) {
		return $out;
	}
	foreach ((array) $user_packages as $key => $pkgref) {
		$package = function_exists('listeo_core_get_package') ? listeo_core_get_package($pkgref) : null;
		if (! $package) {
			continue;
		}
		if ($args['exclude_user_package_id'] && (int)$key === (int)$args['exclude_user_package_id']) {
			continue;
		}
		if ($args['hide_used_up']) {
			$limit = method_exists($package, 'get_limit') ? (int) $package->get_limit() : 0;
			$count = method_exists($package, 'get_count') ? (int) $package->get_count() : 0;
			if ($limit > 0 && $count >= $limit) {
				continue;
			}
		}
		$out[$key] = $package;
	}
	return $out;
}
