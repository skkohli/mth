<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * WP_listing_Manager_Content class.
 */
class Listeo_Core_Coupons {

	/**
	 * Product type constants
	 */
	const PRODUCT_TYPE_BOOKING = 'listing_booking';
	const PRODUCT_TYPE_PACKAGE = 'listing_package';
	const PRODUCT_TYPE_PACKAGE_SUB = 'listing_package_subscription';

	/**
	 * Allowed roles for creating coupons
	 */
	const ALLOWED_ROLES = array('owner', 'seller','administrator');

	/**
	 * Roles that have coupon restrictions (owner/seller only)
	 * Administrators are excluded - their coupons work everywhere
	 */
	const RESTRICTED_ROLES = array('owner', 'seller');

	/**
	 * Dashboard message.
	 *
	 * @access private
	 * @var string
	 */
	private $dashboard_message = '';


	public function __construct() {

		add_shortcode( 'listeo_coupons', array( $this, 'listeo_coupons' ) );
		// /add_action( 'init', array( $this, 'process' ) );
		add_action( 'wp', array( $this, 'dashboard_coupons_action_handler' ) );

		add_filter('woocommerce_coupon_is_valid', array($this, 'woocommerce_coupon_is_valid_for_product'), 30, 3);

		add_action('before_delete_post', array($this, 'remove_coupon_meta'), 10, 1);

		// Admin notices for problematic coupons
		add_action('admin_notices', array($this, 'admin_notice_problematic_coupons'));

	}

	/**
	 * Remove coupon meta
	 *
	 * @param int $post_id
	 */
	public function remove_coupon_meta($post_id) {
		if (get_post_type($post_id) !== 'shop_coupon') {

			return;
		}

		global $wpdb;

		// Delete any postmeta where the coupon is referenced as _coupon_for_widget

		$wpdb->query(

			$wpdb->prepare(

				"DELETE FROM {$wpdb->postmeta}

					WHERE meta_key = '_coupon_for_widget'

					AND meta_value = %d",

				$post_id

			)
		);
		if ( 'shop_coupon' === get_post_type( $post_id ) ) {
			$meta = get_post_meta($post_id);
			if ( ! empty( $meta ) ) {
				foreach ( $meta as $key => $value ) {
					delete_post_meta( $post_id, $key );
				}
			}
		}
	}


	/**
	 * Validate vendor coupon - SECURITY CRITICAL
	 *
	 * This method ensures that owner/seller coupons can ONLY be used for:
	 * 1. Booking products (not listing packages)
	 * 2. Bookings on listings that belong to the coupon creator
	 *
	 * IMPORTANT: Administrator coupons are NOT restricted - they work everywhere.
	 *
	 * @param boolean $valid Current validity status
	 * @param WC_Coupon $coupon The coupon object
	 * @param WC_Discounts $discount The discount object
	 * @return boolean Whether the coupon is valid for the product
	 * @throws Exception When coupon is not valid
	 */
	public function woocommerce_coupon_is_valid_for_product($valid, $coupon, $discount) {

		// Get the coupon post object
		$current_coupon = get_post($coupon->get_id());

		// If no author, this is not a user-created coupon (could be system coupon)
		if (!$current_coupon || !$current_coupon->post_author) {
			return $valid; // Let WooCommerce handle it normally
		}

		// Get coupon author data
		$author = get_userdata($current_coupon->post_author);

		if (!$author) {
			return $valid;
		}

		$author_roles = $author->roles;

		// CRITICAL: Administrators can create coupons that work everywhere
		// Only apply restrictions to owner/seller roles
		if (in_array('administrator', $author_roles)) {
			return $valid; // Admin coupons have no restrictions
		}

		// Check if coupon author has a restricted role (owner/seller)
		$is_restricted_role = false;
		foreach (self::RESTRICTED_ROLES as $role) {
			if (in_array($role, $author_roles)) {
				$is_restricted_role = true;
				break;
			}
		}

		// If not an owner/seller coupon, allow normal WooCommerce processing
		if (!$is_restricted_role) {
			return $valid;
		}

		// SECURITY: Owner/seller coupons have strict restrictions
		$products = $discount->get_items();

		if (empty($products)) {
			return $valid;
		}

		foreach ($products as $product) {

			$current_product = wc_get_product($product->product->get_id());

			if (!$current_product) {
				continue;
			}

			$product_type = $current_product->get_type();

			// // CRITICAL SECURITY FIX: Explicitly block listing packages and subscriptions
			// if (in_array($product_type, array(self::PRODUCT_TYPE_PACKAGE, self::PRODUCT_TYPE_PACKAGE_SUB))) {
			// 	$this->log_security_event(
			// 		'Attempted to use owner coupon on listing package',
			// 		$coupon->get_id(),
			// 		get_current_user_id()
			// 	);
			// 	throw new Exception(__('This coupon cannot be used for listing packages. It can only be used for bookings on your listings.', 'listeo_core'));
			// }

			// Only allow listing_booking products
			if ($product_type !== self::PRODUCT_TYPE_BOOKING) {
				$this->log_security_event(
					'Attempted to use owner coupon on non-booking product: ' . $product_type,
					$coupon->get_id(),
					get_current_user_id()
				);
				throw new Exception(__('This coupon can only be used for booking payments.', 'listeo_core'));
			}

			// CRITICAL SECURITY FIX: Verify listing ownership
			// Get listing_id from cart item data
			$listing_id = null;

			// Try to get from cart
			if (function_exists('WC') && WC()->cart) {
				foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
					if ($cart_item['product_id'] == $current_product->get_id()) {
						$listing_id = isset($cart_item['listing_id']) ? absint($cart_item['listing_id']) : null;
						break;
					}
				}
			}

			// If we couldn't find the listing_id, we can't verify ownership
			if (!$listing_id) {
				$this->log_security_event(
					'Could not determine listing_id for booking product',
					$coupon->get_id(),
					get_current_user_id()
				);
				throw new Exception(__('Could not verify listing ownership for this coupon. Please contact support.', 'listeo_core'));
			}

			// Get the listing post
			$listing = get_post($listing_id);

			if (!$listing || $listing->post_type !== 'listing') {
				$this->log_security_event(
					'Invalid listing ID in cart: ' . $listing_id,
					$coupon->get_id(),
					get_current_user_id()
				);
				throw new Exception(__('Invalid listing for this booking.', 'listeo_core'));
			}

			// CRITICAL: Verify the listing belongs to the coupon creator
			if ((int)$listing->post_author !== (int)$current_coupon->post_author) {
				$this->log_security_event(
					sprintf(
						'Ownership mismatch - Listing owner: %d, Coupon owner: %d',
						$listing->post_author,
						$current_coupon->post_author
					),
					$coupon->get_id(),
					get_current_user_id()
				);
				throw new Exception(__('This coupon can only be used for bookings on the coupon creator\'s listings.', 'listeo_core'));
			}
		}

		// All security checks passed
		return $valid;
	}

	/**
	 * Check if user has at least one published listing
	 *
	 * @param int $user_id User ID to check
	 * @return bool True if user has published listings, false otherwise
	 */
	private function user_has_published_listings($user_id) {
		$args = array(
			'author'         => $user_id,
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids'
		);

		$listings = get_posts($args);

		return !empty($listings);
	}

	/**
	 * Log security events for debugging and audit trail
	 *
	 * @param string $message Event message
	 * @param int $coupon_id Coupon ID involved
	 * @param int $user_id User ID involved
	 */
	private function log_security_event($message, $coupon_id, $user_id) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log(sprintf(
				'[Listeo Coupon Security] %s | Coupon ID: %d | User ID: %d | Time: %s',
				$message,
				$coupon_id,
				$user_id,
				current_time('mysql')
			));
		}
	}

	/**
	 * User coupons shortcode - with security validation
	 */
	public function listeo_coupons( $atts ) {

		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to manage your coupons.', 'listeo_core' );
		}

		// SECURITY FIX: Check if user has at least one published listing
		$current_user = wp_get_current_user();
		if (!$this->user_has_published_listings($current_user->ID)) {
			ob_start();
			?>
			<div class="notification notice">
				<p>
					<strong><?php esc_html_e('No Published Listings', 'listeo_core'); ?></strong><br>
					<?php esc_html_e('You need to have at least one published listing before you can create coupons. Coupons can only be used for bookings on your own listings.', 'listeo_core'); ?>
				</p>
			</div>
			<?php
			return ob_get_clean();
		}

		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );
		$page = 1;
		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;

		if(isset($_GET['add_new_coupon'])) {
			$template_loader->set_template_data(
				array(
					'message' => $this->dashboard_message
				) )->get_template_part( 'account/coupon-submit' );
		} else if(isset($_GET['action']) && $_GET['action'] == 'coupon_edit') {
				$template_loader->set_template_data(
				array(
					'coupon_data' => (isset($_GET['coupon_id'])) ? get_post($_GET['coupon_id']) : '' ,
					'coupon_edit' => 'on' ,
					'message' => $this->dashboard_message
				) )->get_template_part( 'account/coupon-submit' );
		} else {
			$template_loader->set_template_data( array(
				'ids' => $this->get_user_coupons($page,10),
				'message' => $this->dashboard_message
			) )->get_template_part( 'account/coupons' );
		}

		return ob_get_clean();
	}

	function get_user_id() {
	    global $current_user;
	    wp_get_current_user();
	    return $current_user->ID;
	}

	// function get_user_coupons(){
	// 	$user_id = $this->get_user_id();
	// }
	/**
	 * Function to get ids added by the user/agent
	 * @return array array of listing ids
	 */
	public function get_user_coupons($page,$per_page){
		$current_user = wp_get_current_user();
		

		$args = array(
			'author'        	=>  $current_user->ID,
		    'posts_per_page'   => -1,
		    'orderby'          => 'title',
		    'order'            => 'asc',
		    'post_type'        => 'shop_coupon',
		    'post_status'      => 'publish',
		);
    
		$q = get_posts( $args );


		return $q;
	}

	public function get_products_ids_by_listing($listings){
		$products = array();
		if(is_array($listings)){
			foreach ($listings as $key => $listing_id) {
				$product_id = get_post_meta($listing_id, 'product_id', true);
				$products[] = $product_id;
			}
			$products = implode(',',$products);
		}
		return $products;
	}


	



	public function dashboard_coupons_action_handler() {

		global $post;
		
		if ( is_page(get_option( 'listeo_coupons_page' ) ) ) {


			if ( isset( $_POST['listeo-coupon-submission'] ) && '1' == $_POST['listeo-coupon-submission'] ) {

				// SECURITY FIX: Verify user has published listings before allowing coupon creation
				$current_user = wp_get_current_user();
				if (!$this->user_has_published_listings($current_user->ID)) {
					$this->dashboard_message = '<div class="notification closeable error"><p>' .
						__('You must have at least one published listing to create coupons.', 'listeo_core') .
						'</p><a class="close" href="#"></a></div>';
					return;
				}

				global $wpdb;

				$title = sanitize_text_field($_POST['title']);

			    $sql = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1;", $title );
			    //check if coupon with that code exits
			    $coupon_id = $wpdb->get_var( $sql );

			    if ( empty( $coupon_id ) ) {
					
					$customer_emails = sanitize_text_field($_POST['customer_email']);
					// if customer emails are not empty, then explode by comma and remove spaces, it needs to be saved as array
					if(!empty($customer_emails)){
						$customer_emails = explode(',', $customer_emails);
						$customer_emails = array_map('trim', $customer_emails);
						
					}
					// if listing_ids are not empty, then explode by comma and remove spaces, it needs to be saved as array
					

					if(isset($_POST['listing_ids']) && is_array($_POST['listing_ids'])){

						$products = $this->get_products_ids_by_listing($_POST['listing_ids']);
						$listings = implode(",",$_POST['listing_ids']);

					} else {

						global $current_user;                     

						$args = array(
						  'author'        =>  $current_user->ID, 
						  'orderby'       =>  'post_date',
						  'order'         =>  'ASC',
						  'fields'        => 'ids',
						  'post_type'      => 'listing',
						  'posts_per_page' => -1 // no limit
						);


						$current_user_posts = 
						$listings = get_posts( $args );
						$products = $this->get_products_ids_by_listing($listings);
						$listings = implode(",",$listings);
					}
					
				    $data = array(
			            'discount_type'              => sanitize_text_field($_POST['discount_type']),
			            'coupon_amount'              => sanitize_text_field($_POST['coupon_amount']), // value
			            'individual_use'             => (isset($_POST['individual_use'])) ? sanitize_text_field($_POST['individual_use']) : 'no',//'no',
			            'product_ids'                => $products,
			            'listing_ids'                => $listings,
			            //'exclude_product_ids'        => '',
			            'usage_limit'                => sanitize_text_field($_POST['usage_limit']),
			            'usage_limit_per_user'       => sanitize_text_field($_POST['usage_limit_per_user']),//'1',
			            'limit_usage_to_x_items'     => '',
			            'usage_count'                => '',
			            'expiry_date'                => sanitize_text_field($_POST['expiry_date']),
			            'free_shipping'              => 'no',
			            'product_categories'         => '',
			            'exclude_product_categories' => '',
			            'exclude_sale_items'         => 'no',
			            'minimum_amount'             => sanitize_text_field($_POST['minimum_amount']),
			            'maximum_amount'             => sanitize_text_field($_POST['maximum_amount']),
			            'customer_email'             => $customer_emails,
			            'coupon_bg-uploader-id'		 => sanitize_text_field($_POST['listeo_coupon_bg_id']),
			        );
				  
			        // Save the coupon in the database
			        $coupon = array(
			            'post_title' => $_POST['title'],
			            'post_excerpt' => $_POST['excerpt'],
			            'post_content' => '',
			            'post_status' => 'publish',
			            'post_author' => $this->get_user_id(),
			            'post_type' => 'shop_coupon'
			        );
			        $new_coupon_id = wp_insert_post( $coupon );
			        // Write the $data values into postmeta table
			        foreach ($data as $key => $value) {
			            update_post_meta( $new_coupon_id, $key, $value );
			        }
			        $this->dashboard_message =  '<div class="notification closeable success"><p>' . sprintf( __( '%s has been added', 'listeo_core' ), $title ) . '</p><a class="close" href="#"></a></div>';
			    } else {
			    	$this->dashboard_message =  '<div class="notification closeable error"><p>' . sprintf( __( 'Coupon with code "%s" already exists', 'listeo_core' ), $title ) . '</p><a class="close" href="#"></a></div>';
			    }
			}

			//delete

			if ( ! empty( $_REQUEST['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'listeo_core_coupons_actions' ) ) {

				$action = sanitize_title( $_REQUEST['action'] );
				$_id = absint( $_REQUEST['coupon_id'] );

				try {
					//Get coupon
					$coupon    = get_post( $_id );
					$coupon_data = get_post( $coupon );
					if ( ! $coupon_data || 'shop_coupon' !== $coupon_data->post_type ) {
						$title = false;
					} else {
						$title = esc_html( get_the_title( $coupon_data ) );	
					}

					
					switch ( $action ) {
						
						case 'delete' :
							// Trash it
							wp_delete_post( $_id );

							// Message
							$this->dashboard_message =  '<div class="notification closeable success"><p>' . sprintf( __( '%s has been deleted', 'listeo_core' ), $title ) . '</p><a class="close" href="#"></a></div>';

							break;
						
						default :
							do_action( 'listeo_core_dashboard_do_action_' . $action );
							break;
					}

					do_action( 'listeo_core_my_listing_do_action', $action, $listing_id );

				} catch ( Exception $e ) {
					$this->dashboard_message = '<div class="notification closeable error">' . $e->getMessage() . '</div>';
				}
			}
			
				if ( isset( $_POST['listeo-coupon-edit'] ) && '1' == $_POST['listeo-coupon-edit'] ) {

					$customer_emails = sanitize_text_field($_POST['customer_email']);
					
					if (!empty($customer_emails)) {
						$customer_emails = explode(',', $customer_emails);
						$customer_emails = array_map('trim', $customer_emails);
					}
					if(isset($_POST['listing_ids']) && is_array($_POST['listing_ids'])){

						$products = $this->get_products_ids_by_listing($_POST['listing_ids']);
						$listings = implode(",",$_POST['listing_ids']);

					} else {

						global $current_user;                     

						$args = array(
						  'author'        =>  $current_user->ID, 
						  'orderby'       =>  'post_date',
						  'order'         =>  'ASC',
						  'fields'        => 'ids',
						  'post_type'      => 'listing',
						  'posts_per_page' => -1 // no limit
						);


						$current_user_posts = 
						$listings = get_posts( $args );
						$products = $this->get_products_ids_by_listing($listings);
						$listings = implode(",",$listings);
					}

			
					$data = array(
			            'discount_type'              => sanitize_text_field($_POST['discount_type']),
			            'coupon_amount'              => sanitize_text_field($_POST['coupon_amount']), // value
			            'individual_use'             => (isset($_POST['individual_use'])) ? sanitize_text_field($_POST['individual_use']) : 'no',//'no',
			            'product_ids'                => $products,
			            'listing_ids'                => $listings,
			            //'exclude_product_ids'        => '',
			            'usage_limit'                => sanitize_text_field($_POST['usage_limit']),
			            'usage_limit_per_user'       => sanitize_text_field($_POST['usage_limit_per_user']),//'1',
			            'limit_usage_to_x_items'     => '',
			            'usage_count'                => '',
			           // 'expiry_date'                => sanitize_text_field($_POST['expiry_date']),
			            'free_shipping'              => 'no',
			            'product_categories'         => '',
			            'exclude_product_categories' => '',
			            'exclude_sale_items'         => 'no',
			            'minimum_amount'             => sanitize_text_field($_POST['minimum_amount']),
			            'maximum_amount'             => sanitize_text_field($_POST['maximum_amount']),
			            'customer_email'             => $customer_emails,
			            'coupon_bg-uploader-id'		 => sanitize_text_field($_POST['listeo_coupon_bg_id']),
			        );
				
				  
			        // Save the coupon in the database
			        $coupon = array(
			        	'ID'           => $_POST['listeo-coupon-id'],
			            'post_title' => $_POST['title'],
			            'post_content' => '',
			            'post_excerpt' => $_POST['excerpt'],
			            'post_status' => 'publish',
			            'post_author' => $this->get_user_id(),
			            'post_type' => 'shop_coupon'
			        );

			        $wc_coupon = new WC_Coupon($_POST['listeo-coupon-id']);
			        $wc_coupon->set_date_expires( $_POST['expiry_date']);
					wp_update_post($coupon);
					foreach ($data as $key => $value) {
			            update_post_meta( $_POST['listeo-coupon-id'], $key, $value );
			        }
			        $wc_coupon->save();

			}
		}

	}

	/**
	 * Admin notice for coupons created by users without listings
	 * Helps identify potential security issues or old problematic coupons
	 */
	public function admin_notice_problematic_coupons() {
		// Only show to administrators
		if (!current_user_can('manage_options')) {
			return;
		}

		// Only show on admin pages
		if (!is_admin()) {
			return;
		}

		global $wpdb;

		// Find coupons created by owners/sellers who have no published listings
		$query = "
			SELECT p.ID, p.post_title, p.post_author
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->usermeta} um ON p.post_author = um.user_id
			WHERE p.post_type = 'shop_coupon'
			AND p.post_status = 'publish'
			AND p.post_author > 0
			AND um.meta_key = '{$wpdb->prefix}capabilities'
			AND (um.meta_value LIKE '%owner%' OR um.meta_value LIKE '%seller%')
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->posts} l
				WHERE l.post_type = 'listing'
				AND l.post_status = 'publish'
				AND l.post_author = p.post_author
			)
		";

		$problematic_coupons = $wpdb->get_results($query);

		if (!empty($problematic_coupons)) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e('Listeo Core: Problematic Coupons Detected', 'listeo_core'); ?></strong><br>
					<?php
					printf(
						esc_html__('Found %d coupon(s) created by owner/seller users with no published listings. These coupons should be reviewed as they may have been created before security restrictions were implemented.', 'listeo_core'),
						count($problematic_coupons)
					);
					?>
				</p>
				<p>
					<?php esc_html_e('Affected coupons:', 'listeo_core'); ?>
				</p>
				<ul style="list-style: disc; margin-left: 20px;">
					<?php foreach (array_slice($problematic_coupons, 0, 10) as $coupon) : ?>
						<li>
							<a href="<?php echo admin_url('post.php?post=' . $coupon->ID . '&action=edit'); ?>" target="_blank">
								<?php echo esc_html($coupon->post_title); ?>
							</a>
							(<?php esc_html_e('User ID:', 'listeo_core'); ?> <?php echo $coupon->post_author; ?>)
						</li>
					<?php endforeach; ?>
					<?php if (count($problematic_coupons) > 10) : ?>
						<li><em><?php printf(esc_html__('...and %d more', 'listeo_core'), count($problematic_coupons) - 10); ?></em></li>
					<?php endif; ?>
				</ul>
			</div>
			<?php
		}
	}
}