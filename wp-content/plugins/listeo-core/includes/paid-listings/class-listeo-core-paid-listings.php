<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 *  class
 */
class Listeo_Core_Paid_Properties {
	
	/**
	 * Returns static instance of class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	/**
	 * Constructor
	 */
	public function __construct() {

		/* Hooks */
		add_action( 'woocommerce_product_options_general_product_data', array( $this,  'listeo_core_add_custom_settings' ) );
		add_action( 'woocommerce_process_product_meta_listing_package', array( $this, 'save_package_data' ) );
		add_action( 'woocommerce_process_product_meta_listing_package_subscription', array( $this, 'save_package_data' ) );
		

		add_filter( 'woocommerce_subscription_product_types', array( $this, 'woocommerce_subscription_product_types' ) );
		/* Includes */
		include_once( 'class-listeo-core-paid-listings-orders.php' );
		include_once( 'class-listeo-core-paid-listings-package.php' );
		include_once( 'class-listeo-core-paid-listings-cart.php' );

	}


	/**
	 * Types for subscriptions
	 *
	 * @param  array $types
	 * @return array
	 */
	public function woocommerce_subscription_product_types( $types ) {
		$types[] = 'listing_package_subscription';
		return $types;
	}

	function listeo_core_add_custom_settings() {
	    global $woocommerce, $post;
	    echo '<div class="options_group show_if_listing_package show_if_listing_package_subscription">';

		woocommerce_wp_text_input(array(
			'id' 				=> '_package_subtitle',
			'label' 			=> __('Package features title ', 'listeo_core'),
			'description' 		=> __('The text displayed over list of features in pricint tables.', 'listeo_core'),
			'value' 			=> get_post_meta($post->ID, '_package_subtitle', true),
			'placeholder' 		=> '',
			'desc_tip' 			=> true,
			'type' 				=> 'text',
			
		));
	    // Create a number field, for example for UPC
	     woocommerce_wp_text_input( array(
			'id' 				=> '_listing_limit',
			'label' 			=> __( 'Listing limit', 'listeo_core' ),
			'description' 		=> __( 'The number of listings a user can post with this package.', 'listeo_core' ),
			'value' 			=> ( $limit = get_post_meta( $post->ID, '_listing_limit', true ) ) ? $limit : '',
			'placeholder' 		=> __( 'Unlimited', 'listeo_core' ),
			'type' 				=> 'number',
			'desc_tip' 			=> true,
			'custom_attributes' => array(
			'min'   			=> '',
			'step' 				=> '1',
			),
		) ); 

	    woocommerce_wp_text_input( array(
			'id' 				=> '_listing_duration',
			'label' 			=> __( 'Listing duration', 'listeo_core' ),
			'description' 		=> __( 'The number of days that the listing will be active.', 'listeo_core' ),
			'value' 			=> get_post_meta(  $post->ID, '_listing_duration', true ),
			//'placeholder' 		=> get_option('listeo_default_duration' ),
			'placeholder' 		=>__('Unlimited', 'listeo_core'),
			'desc_tip' 			=> true,
			'type' 				=> 'number',
			'custom_attributes' => array(
			'min'  				=> '',
			'step' 				=> '1',
			),
		) );

		woocommerce_wp_checkbox( array(
			'id' => '_listing_featured',
			'label' => __( 'Feature Listing?', 'listeo_core' ),
			'description' => __( 'Feature this listing - it will have a badge and sticky status.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_listing_featured', true ),
		) ); 		


		woocommerce_wp_checkbox( array(
			'id' => '_package_option_booking',
			'label' => __( 'Booking Module', 'listeo_core' ),
			'description' => __( 'Allow booking on listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_booking', true ),
		) ); 

		woocommerce_wp_checkbox( array(
			'id' => '_package_option_reviews',
			'label' => __( 'Reviews Module', 'listeo_core' ),
			'description' => __( 'Allow reviews on listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_reviews', true ),
		) );	


		woocommerce_wp_checkbox( array(
			'id' => '_package_option_gallery',
			'label' => __( 'Gallery Module', 'listeo_core' ),
			'description' => __( 'Allow gallery on listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_gallery', true ),
		) ); 
		woocommerce_wp_checkbox( array(
			'id' => '_package_option_pricing_menu',
			'label' => __( 'Pricing Menu Module', 'listeo_core' ),
			'description' => __( 'Allow pricing menu on listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_pricing_menu', true ),
		) ); 
		// faq
		woocommerce_wp_checkbox( array(
			'id' => '_package_option_faq',
			'label' => __( 'FAQ Module', 'listeo_core' ),
			'description' => __( 'Allow FAQ on listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_faq', true ),
		) );

		// Dokan Store Access
		woocommerce_wp_checkbox( array(
			'id' => '_package_option_dokan_store',
			'label' => __( 'Dokan Store Access', 'listeo_core' ),
			'description' => __( 'Allow user to create and manage a Dokan vendor store with this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_dokan_store', true ),
		) );

		woocommerce_wp_text_input( array(
			'id' 				=> '_package_dokan_store_duration',
			'label' 			=> __( 'Dokan Store Duration (days)', 'listeo_core' ),
			'description' 		=> __( 'Number of days the Dokan store access will be active. Leave empty for unlimited access.', 'listeo_core' ),
			'value' 			=> get_post_meta(  $post->ID, '_package_dokan_store_duration', true ),
			'placeholder' 		=> __( 'Unlimited', 'listeo_core' ),
			'desc_tip' 			=> true,
			'type' 				=> 'number',
			'custom_attributes' => array(
				'min'  			=> '',
				'step' 			=> '1',
			),
		) );

	    woocommerce_wp_text_input( array(
			'id' 				=> '_package_option_gallery_limit',
			'label' 			=> __( 'Gallery module images limit', 'listeo_core' ),
			'description' 		=> __( 'Limit the number of images that can be uploaded. Set to empty or 0 set no limit', 'listeo_core' ),
			'value' 			=> get_post_meta(  $post->ID, '_package_option_gallery_limit', true ),
			'desc_tip' 			=> true,
			'type' 				=> 'number',
			
			'custom_attributes' => array(
			'min'  				=> '',
			'step' 				=> '1',
			),
		) );

		woocommerce_wp_checkbox( array(
			'id' => '_package_option_social_links',
			'label' => __( 'Social Links Module', 'listeo_core' ),
			'description' => __( 'Allow social links to be displayed on the listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_social_links', true ),
		) );

		woocommerce_wp_checkbox( array(
			'id' => '_package_option_opening_hours',
			'label' => __( 'Opening Hours Module', 'listeo_core' ),
			'description' => __( 'Allow Opening Hours widget to be displayed on the listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_opening_hours', true ),
		) );
		
		woocommerce_wp_checkbox( array(
			'id' => '_package_option_video',
			'label' => __( 'Video Module', 'listeo_core' ),
			'description' => __( 'Allow Video widget to be displayed on the listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_video', true ),
		) );		
		woocommerce_wp_checkbox( array(
			'id' => '_package_option_coupons',
			'label' => __( 'Coupons Module', 'listeo_core' ),
			'description' => __( 'Allow Coupons widget to be displayed on the listings bought from this package.', 'listeo_core' ),
			'value' => get_post_meta(  $post->ID, '_package_option_coupons', true ),
		) );

		

	    echo '</div>';
	    ?>
	    <script type="text/javascript">
		jQuery(function(){
			jQuery('#product-type').change( function() {
				jQuery('#woocommerce-product-data').removeClass(function(i, classNames) {
					var classNames = classNames.match(/is\_[a-zA-Z\_]+/g);
					if ( ! classNames ) {
						return '';
					}
					return classNames.join(' ');
				});
				jQuery('#woocommerce-product-data').addClass( 'is_' + jQuery(this).val() );
			} );
			jQuery('.pricing').addClass( 'show_if_listing_package' );
			jQuery('._tax_status_field').closest('div').addClass( 'show_if_listing_package' ).addClass( 'show_if_listing_package_subscription' );
			
			jQuery('.show_if_subscription, .options_group.pricing').addClass( 'show_if_listing_package_subscription' );
			jQuery('.options_group.pricing ._regular_price_field').addClass( 'hide_if_listing_package_subscription' );
			
			jQuery('#product-type').change();
			jQuery('#_listing_package_subscription_type').change(function(){
				if ( jQuery(this).val() === 'listing' ) {
					jQuery('#_listing_duration').closest('.form-field').hide().val('');
				} else {
					jQuery('#_listing_duration_duration').closest('.form-field').show();
				}		
			}).change();
			
		});
	</script>
	<?php

	// Render only for listing package product types (use classes for conditional display)
    // Build options from custom listing types manager if available
    $type_options = array();
    if ( function_exists( 'listeo_core_custom_listing_types' ) ) {
        $manager = listeo_core_custom_listing_types();
        if ( method_exists( $manager, 'get_listing_types' ) ) {
            $types = $manager->get_listing_types( true );
            if ( $types ) {
                foreach ( $types as $t ) {
                    $slug = isset( $t->slug ) ? $t->slug : '';
                    $name = isset( $t->name ) ? $t->name : $slug;
                    if ( $slug ) {
                        $type_options[ $slug ] = $name;
                    }
                }
            }
        }
    }
    if ( empty( $type_options ) ) {
        $type_options = array(
            'service'     => __( 'Service', 'listeo_core' ),
            'rental'      => __( 'Rental', 'listeo_core' ),
            'event'       => __( 'Event', 'listeo_core' ),
            'classifieds' => __( 'Classifieds', 'listeo_core' ),
        );
    }
    $selected_types = get_post_meta( $post->ID, '_allowed_listing_types', true );
    if ( ! is_array( $selected_types ) ) {
        $selected_types = empty( $selected_types ) ? array() : (array) $selected_types;
    }
    ?>
    <p class="form-field show_if_listing_package show_if_listing_package_subscription">
        <label for="_allowed_listing_types"><?php _e( 'Allowed Listing Types', 'listeo_core' ); ?></label>
        <select id="_allowed_listing_types" name="_allowed_listing_types[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'All types (leave empty)', 'listeo_core' ); ?>">
            <?php foreach ( $type_options as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( in_array( $slug, $selected_types, true ) ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
		<br><span style="display: block;" class="description"><?php esc_html_e( 'Select one or more listing types that this package applies to. Leave empty to allow all listing types.', 'listeo_core' ); ?></span>
    </p>
  
    <script>
    jQuery(function($){
        $('#product-type').trigger('change');
    });
    </script> <?php
	}

	/**
	 * Save Job Package data for the product
	 *
	 * @param  int $post_id
	 */
	public function save_package_data( $post_id ) {
		global $wpdb;

		// Save meta
		$meta_to_save = array(
			'_package_subtitle'             	=> '',
			'_listing_duration'             	=> '',
			'_listing_limit'                	=> 'int',
			'_listing_featured'             	=> 'yesno',
			'_package_option_booking'           => 'yesno',
			'_package_option_reviews'           => 'yesno',
			'_package_option_pricing_menu'           => 'yesno',
			'_package_option_gallery'           => 'yesno',
			'_package_option_gallery_limit'     => 'int',
			'_package_option_social_links'      => 'yesno',
			'_package_option_opening_hours'     => 'yesno',
			'_package_option_video'             => 'yesno',
			'_package_option_coupons'           => 'yesno',
			'_package_option_faq'               => 'yesno',
			'_package_option_dokan_store'       => 'yesno',
			'_package_dokan_store_duration'     => 'int',

		);

		foreach ( $meta_to_save as $meta_key => $sanitize ) {
			$value = ! empty( $_POST[ $meta_key ] ) ? $_POST[ $meta_key ] : '';
			switch ( $sanitize ) {
				case 'int' :
					$value = absint( $value );
					break;
				case 'float' :
					$value = floatval( $value );
					break;
				case 'yesno' :
					$value = $value == 'yes' ? 'yes' : 'no';
					break;
				default :
					$value = sanitize_text_field( $value );
			}
			update_post_meta( $post_id, $meta_key, $value );
		}

		$_package_subscription_type = ! empty( $_POST['_listing_package_subscription_type'] ) ? $_POST['listing_package_subscription_type'] : 'package';
		update_post_meta( $post_id, '_package_subscription_type', $_package_subscription_type );


		if (isset($_POST['_allowed_listing_types'])) {
			$allowed_types = $_POST['_allowed_listing_types'];
			if (! is_array($allowed_types)) {
				$allowed_types = array($allowed_types);
			}
			$allowed_types = array_filter(array_map('sanitize_title', $allowed_types));
			update_post_meta($post_id, '_allowed_listing_types', $allowed_types);
		} else {
			delete_post_meta($post_id, '_allowed_listing_types');
		}
	}

}

new Listeo_Core_Paid_Properties();



