<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listeo_Core_Admin_Add_Package class.
 */
class Listeo_Core_Admin_Edit_Listing_Package {

	private $package_id;
	private $listing_id;
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->listing_id = isset( $_REQUEST['listing_id'] ) ? absint( $_REQUEST['listing_id'] ) : 0;
		$this->package_id = isset( $_REQUEST['package_id'] ) ? absint( $_REQUEST['package_id'] ) : 0;

		if ( ! empty( $_POST['save_listing_package'] ) && ! empty( $_POST['listeo_core_paid_listings_package_editor_nonce'] ) && wp_verify_nonce( $_POST['listeo_core_paid_listings_package_editor_nonce'], 'save' ) ) {
			$this->save();
		}
	}

	/**
	 * Output the form
	 */
	public function form() {
		global $wpdb;

		$user_string = '';
		$user_id     = '';

		
		?>
		<table class="form-table">
			<h2><?php 
				$post_author_id = get_post_field( 'post_author', $this->listing_id );
		    	$user_package = get_post_meta($this->listing_id,'_user_package_id',true);
		    	//echo $user_package;
		    	//$user_packages = listeo_core_available_packages($post_author_id,$user_package);
		    	
		    		$package = listeo_core_get_package_by_id($this->package_id);	
		    		
		    		if($package && $package->product_id){
		    			echo "Your are editing \""; echo get_the_title($this->listing_id); echo "\".<br><br>"; 
		    			echo "Currently this listing is assigned to package: "; echo get_the_title($package->product_id);
		    		};
		    		//return $package->get_title();
		    	
		     ?>
			</h2>
			
			 <?php // display author name and link to profile of this post by $$post_author_id
			 
				// Assuming $post_author_id is defined and contains the ID of the post author
				$post_author_id = get_post_field('post_author', $this->listing_id); // Replace $post_id with the actual post ID

				// Get the author's display name
				$author_name = get_the_author_meta('display_name', $post_author_id);

				// Get the URL to the author's profile
				$author_profile_url = get_author_posts_url($post_author_id);
				echo "You are editing package for listing: "; echo get_the_title($this->listing_id); echo "<br>";
				// Display the author's name with a link to their profile
				echo 'This listing owner is <a href="' . esc_url($author_profile_url) . '">' . esc_html($author_name) . '</a>';
				// display link to admin page admin.php?page=listeo_core_paid_listings_packages
				echo ". Make sure this user has available package, you can set it in";
// Generate the URL to the admin page
				$admin_page_url = admin_url('admin.php?page=listeo_core_paid_listings_packages');
				echo ' <a href="' . esc_url($admin_page_url) . '">Packages Manager</a>';
?>
				



			<tr>
				<th>
					<label for="_user_package_id"><?php _e( 'Assigned to Listing Package', 'listeo_core' ); ?></label><br>
					
				</th>
				<td>
					<select name="_user_package_id" id="package_selector">
					<?php echo listeo_core_available_packages($post_author_id,$user_package); ?>	
					</select>
					<small>Changing package will increase limit of used listings in the package</small>
					<div id="package_features_info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
						<strong><?php _e( 'Package Features Update:', 'listeo_core' ); ?></strong>
						<p><?php _e( 'When you change the package, the following listing features will be automatically updated based on the new package settings:', 'listeo_core' ); ?></p>
						<ul style="list-style: disc; margin-left: 20px;">
							<li><?php _e( 'Featured status (on/off)', 'listeo_core' ); ?></li>
							<li><?php _e( 'Opening hours module (enabled/disabled)', 'listeo_core' ); ?></li>
							<li><?php _e( 'Booking system availability', 'listeo_core' ); ?></li>
							<li><?php _e( 'Reviews system availability', 'listeo_core' ); ?></li>
							<li><?php _e( 'Pricing menu module', 'listeo_core' ); ?></li>
							<li><?php _e( 'Video support', 'listeo_core' ); ?></li>
							<li><?php _e( 'Social links', 'listeo_core' ); ?></li>
							<li><?php _e( 'Gallery features', 'listeo_core' ); ?></li>
						</ul>
						<p><small><?php _e( 'Note: Existing data will be preserved when possible, but disabled features will be hidden from the frontend.', 'listeo_core' ); ?></small></p>
					</div>
				</td>
			</tr>
			<tr>
				<th>
					<label for="_user_package_decrease">Decrease previous package count on package change</label>
				</th>
				<td>
					<input type="checkbox" name="_user_package_decrease">
				</td>
			</tr>
			
			
		</table>
		<p class="submit">
			<input type="hidden" name="package_id" value="<?php echo esc_attr( $this->package_id ); ?>" />
			<input type="hidden" name="listing_id" value="<?php echo esc_attr( $this->listing_id ); ?>" />
			<input type="submit" class="button button-primary" name="save_listing_package" value="<?php _e( 'Save Package', 'listeo_core' ); ?>" />
		</p>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var originalPackage = $('#package_selector').val();
			
			$('#package_selector').change(function() {
				var selectedPackage = $(this).val();
				
				if (selectedPackage !== originalPackage) {
					$('#package_features_info').slideDown();
				} else {
					$('#package_features_info').slideUp();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Save the new key
	 */
	public function save() {
		global $wpdb;

		try {
		//	$package_type     = wc_clean( $_POST['package_type'] );
			
			$listing_id    = absint( $_POST['listing_id'] );
			$current_package_id    = get_post_meta($listing_id,'_user_package_id',true);
			$new_package_id    = absint( $_POST['_user_package_id'] );
			$decrease    = isset( $_POST['_user_package_decrease'] ) ? 1 : 0;

			if ( $current_package_id != $new_package_id) {
					$post_author_id = get_post_field( 'post_author', $listing_id );

					// Get the new package data to retrieve the product ID
					$new_package_data = listeo_core_get_package_by_id( $new_package_id );

					// Update both package meta fields
					update_post_meta($listing_id,'_user_package_id',$new_package_id);

					// Update _package_id with the WooCommerce product ID
					if ( $new_package_data && isset( $new_package_data->product_id ) ) {
						update_post_meta($listing_id,'_package_id', $new_package_data->product_id);
					}

					listeo_core_increase_package_count($post_author_id, $new_package_id);
					if($decrease == 1) {

						listeo_core_decrease_package_count($post_author_id, $current_package_id);
					}

					// Update listing features based on new package
					$this->update_listing_features_from_package( $listing_id, $new_package_id );
					
				echo sprintf( '<div class="updated"><p>%s</p></div>', __( 'Package successfully changed and listing features updated', 'listeo_core' ) );

			} else {
			echo sprintf( '<div class="updated"><p>%s</p></div>', __( 'You haven\'t changed package', 'listeo_core' ) );
			}// End if().

			

		} catch ( Exception $e ) {
			echo sprintf( '<div class="error"><p>%s</p></div>', $e->getMessage() );
		}// End try().
	}

	/**
	 * Update listing features based on package settings
	 */
	private function update_listing_features_from_package( $listing_id, $package_id ) {
		$package_data = listeo_core_get_package_by_id( $package_id );
		
		if ( ! $package_data ) {
			return;
		}

		// Create proper package object
		$package = new Listeo_Core_Paid_Listings_Package( $package_data );

		// Update featured status based on package
		if ( $package->is_featured() ) {
			update_post_meta( $listing_id, '_featured', 'on' );
		} else {
			delete_post_meta( $listing_id, '_featured' );
		}

		// Update opening hours status based on package - only DISABLE if not supported
		if ( ! $package->has_listing_opening_hours() ) {
			update_post_meta( $listing_id, '_opening_hours_status', '' );
			// Also clear the opening hours data to prevent confusion
			delete_post_meta( $listing_id, '_opening_hours' );
		}

		// Update other package-based features
		$this->sync_additional_package_features( $listing_id, $package );
	}

	/**
	 * Sync additional package features
	 *
	 * Only DISABLES features that the package doesn't support.
	 * Does NOT auto-enable features - user controls that via listing form.
	 */
	private function sync_additional_package_features( $listing_id, $package ) {
		// Video feature - only disable if not supported
		if ( ! $package->has_listing_video() ) {
			update_post_meta( $listing_id, '_video_status', '' );
			delete_post_meta( $listing_id, '_video' );
		}

		// Pricing menu feature - only disable if not supported
		if ( ! $package->has_listing_pricing_menu() ) {
			update_post_meta( $listing_id, '_menu_status', '' );
			delete_post_meta( $listing_id, '_menu' );
		}

		// Social links feature - only disable if not supported
		if ( ! $package->has_listing_social_links() ) {
			update_post_meta( $listing_id, '_social_status', '' );
			delete_post_meta( $listing_id, '_facebook' );
			delete_post_meta( $listing_id, '_twitter' );
			delete_post_meta( $listing_id, '_instagram' );
			delete_post_meta( $listing_id, '_youtube' );
		}

		// Gallery feature - only disable if not supported
		if ( ! $package->has_listing_gallery() ) {
			update_post_meta( $listing_id, '_gallery_status', '' );
			delete_post_meta( $listing_id, '_gallery_limit' );
		}

		// Booking feature - only disable if not supported
		if ( ! $package->has_listing_booking() ) {
			update_post_meta( $listing_id, '_booking_status', '' );
		}

		// Reviews feature - only disable if not supported
		if ( ! $package->has_listing_reviews() ) {
			update_post_meta( $listing_id, '_reviews_status', '' );
		}

		// Coupons feature - only disable if not supported
		if ( method_exists( $package, 'has_listing_coupons' ) && ! $package->has_listing_coupons() ) {
			update_post_meta( $listing_id, '_coupons_status', '' );
			delete_post_meta( $listing_id, '_coupons' );
		}

		// FAQ feature - only disable if not supported
		if ( method_exists( $package, 'has_listing_faq' ) && ! $package->has_listing_faq() ) {
			update_post_meta( $listing_id, '_faq_status', '' );
		}
	}
}
