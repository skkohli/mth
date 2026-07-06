<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Listeo  class.
 */
class Listeo_Core_Claim_Listings
{
    /**
     * The single instance of the class.
     *
     * @var self
     * @since  1.26
     */
    private static $_instance = null;

    /**
     * Allows for accessing single instance of class. Class should only be constructed once per call.
     *
     * @since  1.26
     * @static
     * @return self Main instance.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {

        add_action('init', array($this, 'register_post_types'), 5);
        add_action('manage_claim_posts_custom_column', array($this, 'custom_columns'), 2);
        add_filter('manage_edit-claim_columns', array($this, 'columns'));
        add_filter('add_menu_classes', array($this, 'show_pending_number'));
		
        add_action('wp_ajax_listeo_send_claim_request', array($this, 'listeo_send_claim_request'));
        add_action('wp_ajax_nopriv_listeo_send_claim_request', array($this, 'listeo_send_claim_request'));

        // Add a meta box to the post editor screen
        add_action('cmb2_admin_init', array($this, 'add_claim_meta_boxes'));
        add_action('add_meta_boxes', array($this, 'claim_info_meta_box'));

        // add action on claim post update
        add_action('updated_post_meta', array($this, 'claim_post_update'), 2, 4);

        // Handle claim package assignment when WooCommerce order is paid
        add_action('woocommerce_order_status_processing', array($this, 'process_claim_order_payment'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'process_claim_order_payment'), 10, 1);

    }

    function show_pending_number($menu)
    {
        $types = array("claim");

        foreach ($types as $type) {
            
            // count number of claim post type that has custom meta field "status" set as "pending"
            $args = array(
                'post_type' => 'claim',
                'meta_key' => 'status',
                'meta_value' => 'pending',
                'posts_per_page' => 99
            );
            
            $pending_posts = new WP_Query($args);
            $pending_count = $pending_posts->found_posts;
            wp_reset_postdata();
            if ($type == 'post') {
                $menu_str = 'edit.php';
            } else {
                $menu_str = 'edit.php?post_type=' . $type;
            }

            foreach ($menu as $menu_key => $menu_data) {
                if ($menu_str != $menu_data[2])
                    continue;
                $menu[$menu_key][0] .= " <span class='update-plugins count-$pending_count'><span class='plugin-count'>"
                . number_format_i18n($pending_count)
                    . '</span></span>';
            }
        }
        return $menu;
    }


    /**
     * Process claim completion - transfer listing ownership and finalize claim
     * 
     * @param int $claim_id Claim post ID
     * @param int $listing_id Listing post ID
     * @param int $user_id New owner user ID
     * @param int $package_id Package ID
     */
    /**
     * Complete the claim process - transfer ownership and verify listing.
     *
     * NOTE: This function does NOT assign packages - that must be done separately
     * by calling listeo_core_give_user_package() and listeo_core_approve_listing_with_package()
     * BEFORE calling this function.
     *
     * @param int $claim_id The claim post ID
     * @param int $listing_id The listing post ID
     * @param int $user_id The user claiming the listing
     * @param int $package_id The package ID (for reference only, not assigned here)
     */
    function complete_claim_process($claim_id, $listing_id, $user_id, $package_id) {
        // Get the listing
        $listing = get_post($listing_id);
        if ($listing) {
            // Transfer ownership of listing to the claiming user
            $listing->post_author = $user_id;

            // Claimed listings should always be published - the admin already approved the claim
            // and the listing existed before. Don't let listeo_core_approve_listing_with_package()
            // reset it to 'pending' based on the new-listing-approval setting.
            $listing->post_status = 'publish';

            // Remove any existing product ID associated with the listing
            $product_id = get_post_meta($listing_id, '_product_id', true);
            if ($product_id) {
                wp_delete_post($product_id);
                delete_post_meta($listing_id, '_product_id');
            }

            // Update the listing with new ownership
            wp_update_post($listing);
            
            // Mark the listing as verified since it's been claimed
            update_post_meta($listing_id, '_verified', 'on');
        }

        // Send completion email
        $args = array(
            'user_id' => $user_id,
            'listing_id' => $listing_id,
            'claim_id' => $claim_id
        );
        do_action('listeo_mail_to_user_claim_completed', $args);
    }

    function create_payment_order($user_id, $listing_id, $package_id, $claim_id)
    {

        $product = wc_get_product($package_id);
        $order = wc_create_order(
            array('customer_id' => $user_id)
        );
        // if package is subscription type
        if ($product->is_type('listing_package_subscription')) {

            // Check if WooCommerce Subscriptions is active
            if (!function_exists('wcs_create_subscription')) {
                $order->update_status('cancelled', 'WooCommerce Subscriptions plugin is not active.');
                return false;
            }

            $item_id = $order->add_product($product);

            // Calculate totals and save the order
            $order->calculate_totals();
            $order->save();

            // Create a subscription from this order
            $subscription = wcs_create_subscription(array(
                'order_id'    => $order->get_id(),
                'customer_id' => $user_id,
                'status'      => 'pending',
                'billing_period' => $product->get_meta('_subscription_period'),
                'billing_interval' => $product->get_meta('_subscription_period_interval'),
            ));

            if (is_wp_error($subscription)) {
                $order->update_status('cancelled', 'Failed to create subscription.');
                return false;
            }

            // Link the subscription to the order
            wcs_set_objects_property($order, 'subscription', $subscription->get_id());

            // Get the payment link
            wc_update_order_item_meta($item_id,  __('Listing title', 'listeo_core'), get_the_title($listing_id));
            wc_update_order_item_meta($item_id, '_listing_id', $listing_id);
            wc_update_order_item_meta($item_id, '_claim_id', $claim_id);
            // get order id
            $order_id = $order->get_id();
            // updte claim meta with order id
            update_post_meta($claim_id, 'order_id', $order_id);
            return $order;
        } else {

            $item_id =  $order->add_product(wc_get_product($package_id), 1);
            // add order item meta
            wc_update_order_item_meta($item_id,  __('Listing title', 'listeo_core'), get_the_title($listing_id));
            wc_update_order_item_meta($item_id, '_listing_id', $listing_id);
            wc_update_order_item_meta($item_id, '_claim_id', $claim_id);

            $order->calculate_totals();
            $order->update_status('pending', 'Claim approved. Please pay for the package to complete the claim.');
            $order->save();
            $order_id = $order->get_id();
            // updte claim meta with order id
            update_post_meta($claim_id, 'order_id', $order_id);
            return $order;
        }
    }



    /**
     * @param mixed $meta_id 
     * @param mixed $object_id 
     * @param mixed $meta_key 
     * @param mixed $_meta_value 
     * @return void 
     */
    function claim_post_update($meta_id, $object_id, $meta_key, $_meta_value)
    {

        if ($meta_key === 'status') {
            $status = get_post_meta($object_id, 'status', true);


            switch ($status) {
                case 'approved':
                    
                    // create a new order for the package and sent it to the user
                    $package_id = get_post_meta($object_id, 'package', true);
                    $user_id = get_post_field('post_author', $object_id);
                    $listing_id = get_post_meta($object_id, 'listing_id', true);
                    
                    $order = false;
                    if($package_id && get_option('listeo_enable_paid_claims')) {
                        // Check if package is free (price = 0) and handle accordingly
                        $product = wc_get_product($package_id);
                        $package_price = $product ? $product->get_price() : 0;

                        if ($package_price == 0) {
                            // Free package - complete the claim immediately without payment

                            // Temporarily remove the hook to prevent recursive call
                            remove_action('updated_post_meta', array($this, 'claim_post_update'), 2);
                            update_post_meta($object_id, 'status', 'completed');
                            add_action('updated_post_meta', array($this, 'claim_post_update'), 2, 4);

                            // Assign the free package to the user (same as paid order flow)
                            $user_package_id = listeo_core_give_user_package($user_id, $package_id, 0);

                            if ($user_package_id) {
                                // Store the package ID to prevent double-assignment
                                update_post_meta($object_id, '_user_package_id', $user_package_id);
                                // Assign the listing to the package and sync features
                                listeo_core_approve_listing_with_package($listing_id, $user_id, $user_package_id);
                            }

                            // Process the claim completion (transfer ownership, verify listing)
                            // This also sends the "claim completed" email - no need for a separate
                            // "approved" email since the claim is instantly completed for free packages
                            $this->complete_claim_process($object_id, $listing_id, $user_id, $package_id);

                            // Exit early since claim is already completed
                            break;
                        } else {
                            // Paid package - create order for payment
                            if(!get_post_meta($object_id,'order_id',true)){
                                
                                $order = $this->create_payment_order($user_id, $listing_id, $package_id, $object_id);   
                            } else {
                                $order_id = get_post_meta($object_id, 'order_id', true);
                                $order = wc_get_order($order_id);
                            }
                        }
                        
                    }
                  
                    $args = array(
                        'user_id' => $user_id,
                        'listing_id' => get_post_meta($object_id, 'listing_id', true),
                        'package_id' => $package_id,
                        'claim_id' => $object_id
                    );

                    if($order){  
                        $args['payment_url'] = $order->get_checkout_payment_url();
                        $args['order_id'] = $order->get_id();
                    }

                    do_action('listeo_mail_to_user_claim_approved', $args);



                    break;
                case 'rejected':
                    // send email to the user with the rejection message
                    $user_id = get_post_field('post_author', $object_id);
                    $user = get_userdata($user_id);
                    $email = $user->user_email;
                    $args = array(
                        'user_id' => $user_id,
                        'listing_id' => get_post_meta($object_id, 'listing_id', true),
                        'claim_id' => $object_id
                    );
                    do_action('listeo_mail_to_user_claim_rejected', $args);

                    break;
                case 'pending':
                    // send email to the user with the pending message
                    $user_id = get_post_field('post_author', $object_id);
                    $user = get_userdata($user_id);
                    $email = $user->user_email;
                    $args = array(
                        'user_id' => $user_id,
                        'listing_id' => get_post_meta($object_id, 'listing_id', true),
                        'claim_id' => $object_id
                    );
                    do_action('listeo_mail_to_user_claim_pending', $args);

                    break;
                case 'completed':
                    // When admin manually marks claim as "completed",
                    // assign the package to the user (same as approved flow)
                    $user_id = get_post_field('post_author', $object_id);
                    $listing_id = get_post_meta($object_id, 'listing_id', true);
                    $package_id = get_post_meta($object_id, 'package', true);

                    // Check if package was already assigned (e.g. by WC order completion hook)
                    // to prevent double-assignment
                    $existing_package = get_post_meta($object_id, '_user_package_id', true);

                    // Assign the package to the user
                    if (!$existing_package && $package_id && get_option('listeo_enable_paid_claims')) {
                        $user_package_id = listeo_core_give_user_package($user_id, $package_id, 0);

                        if ($user_package_id) {
                            // Store the package ID to prevent double-assignment
                            update_post_meta($object_id, '_user_package_id', $user_package_id);
                            // Sync the listing with package features (duration, limits, modules)
                            listeo_core_approve_listing_with_package($listing_id, $user_id, $user_package_id);
                        }
                    }

                    // Now transfer ownership and verify listing
                    $listing = get_post($listing_id);
                    if ($listing) {
                        $listing->post_author = $user_id;

                        // Claimed listings should always be published
                        $listing->post_status = 'publish';

                        $product_id = get_post_meta($listing_id, '_product_id', true);
                        if ($product_id) {
                            wp_delete_post($product_id);
                            delete_post_meta($listing_id, '_product_id');
                        }

                        wp_update_post($listing);
                        update_post_meta($listing_id, '_verified', 'on');
                    }

                    // Send completion email
                    $args = array(
                        'user_id' => $user_id,
                        'listing_id' => $listing_id,
                        'claim_id' => $object_id,
                        'package_id' => $package_id
                    );
                    do_action('listeo_mail_to_user_claim_completed', $args);

                    break;

                default:
                    # code...
                    break;
            }
        }
    }

    /**
     * Handle claim completion when WooCommerce order is paid
     *
     * This method is called when a WooCommerce order containing a claim package
     * is marked as completed. It assigns the package to the user and completes
     * the claim automatically.
     *
     * @param int $order_id WooCommerce order ID
     * @return void
     */
    function process_claim_order_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check all order items for claim IDs
        $items = $order->get_items();
        foreach ($items as $item) {
            $claim_id = wc_get_order_item_meta($item->get_id(), '_claim_id', true);

            if ($claim_id) {
                // Get claim details
                $listing_id = get_post_meta($claim_id, 'listing_id', true);
                $package_id = get_post_meta($claim_id, 'package', true);
                $user_id = get_post_field('post_author', $claim_id);

                // Check if package already assigned (prevent double-assignment)
                $existing_package = get_post_meta($claim_id, '_user_package_id', true);
                if ($existing_package) {
                    // Package already assigned, skip
                    continue;
                }

                // Validate all required data exists
                if ($package_id && $listing_id && $user_id) {
                    // Assign the package to the user
                    $user_package_id = listeo_core_give_user_package($user_id, $package_id, $order_id);

                    if ($user_package_id) {
                        // Store the package ID to prevent double-assignment
                        update_post_meta($claim_id, '_user_package_id', $user_package_id);

                        // Sync the listing with package features
                        listeo_core_approve_listing_with_package($listing_id, $user_id, $user_package_id);

                        // Complete the claim process (transfer ownership, verify listing)
                        $this->complete_claim_process($claim_id, $listing_id, $user_id, $package_id);

                        // Update claim status to completed
                        // Temporarily remove hook to prevent recursive call
                        remove_action('updated_post_meta', array($this, 'claim_post_update'), 2);
                        update_post_meta($claim_id, 'status', 'completed');
                        add_action('updated_post_meta', array($this, 'claim_post_update'), 2, 4);
                    }
                }
            }
        }
    }

    function claim_info_meta_box()
    {
        add_meta_box(
            'claim-info-box', // Unique ID
            'Claim Information', // Box title
            array($this, 'display_claim_meta_info'), // Callback function to display content
            'claim', // Post type
            'normal', // Context
            'high' // Priority
        );
    }

    // Callback function to display content inside the meta box
    function display_claim_meta_info($post)
    {
        // Retrieve custom field data
        $listing_id = get_post_meta($post->ID, 'listing_id', true);
        $first_name = get_post_meta($post->ID, 'firstname', true);
        $last_name = get_post_meta($post->ID, 'lastname', true);
        $phone = get_post_meta($post->ID, 'phone', true);
        $email = get_post_meta($post->ID, 'email', true);
        $message = get_post_meta($post->ID, 'message', true);

        // display the custom fields in columns

?>
        <div class="inside">
            <div class="main">
                <h3>Claim for <a href="<?php the_permalink($listing_id); ?>"><?php echo get_the_title($listing_id); ?></a></h3>

                <table class="form-table">

                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('User claiming:', 'listeo_core'); ?></th>
                            <td> <?php // get post author name
                                    $author_id = get_post_field('post_author', $post->ID);

                                    $author_name = get_the_author_meta('display_name', $author_id);
                                    if (empty($author_name)) {
                                        $author_name = "User " . $author_id;
                                    }
                                    echo esc_attr($author_name);

                                    ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Name:', 'listeo_core'); ?></th>
                            <td> <?php echo esc_attr($first_name); ?> <?php echo esc_attr($last_name); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Phone:', 'listeo_core'); ?></th>
                            <td> <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_attr($phone); ?></a></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Email:', 'listeo_core'); ?></th>
                            <td> <a href="mailto:<?php echo esc_attr($email); ?>"> <?php echo esc_attr($email); ?></a></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Message:', 'listeo_core'); ?></th>
                            <td><?php echo esc_textarea($message); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Current listing author:', 'listeo_core'); ?></th>
                            <td><?php //get author of $listing_id;
                                $author_id = get_post_field('post_author', $listing_id);
                                $author_name = get_the_author_meta('display_name', $author_id);
                                if (empty($author_name)) {
                                    $author_name = "User " . $author_id;
                                }

                                echo esc_attr($author_name);

                                ?></td>
                        </tr>
                        <tr>
                            
                            <th scope="row"><?php esc_html_e('File:', 'listeo_core'); ?></th>
                            <td><?php
                                $file = get_post_meta($post->ID, 'file', true);
                               
                                if ($file) {
                                    echo '<a href="' . wp_get_attachment_url($file) . '">' . basename(wp_get_attachment_url($file)) . '</a>';
                                } else {
                                    echo esc_html__('No file uploaded', 'listeo_core');
                                }
                                ?></td>

                        </tr>



                    </tbody>
                </table>


            </div>
        </div>
        <?php
    }


    public function add_claim_meta_boxes()
    {

        $claim_admin_options = array(
            'id'           => 'listeo_core_claim_metaboxes',
            'title'        => __('Claim Listing Request data', 'listeo_core'),
            'object_types' => array('claim'),
            'show_names'   => true,
            'show_in_rest' => WP_REST_Server::READABLE,
        );
        $cmb_claim_admin = new_cmb2_box($claim_admin_options);

        // dropdown list with products of type "package"
        $cmb_claim_admin->add_field(array(
            'name' => __('Selected Package', 'listeo_core'),
            'desc' => '',
            'id'   => 'package',
            'type' => 'select',
            'options_cb' => array($this, 'get_packages'),
        ));

        // add status field to the claim post type
        $cmb_claim_admin->add_field(array(
            'name' => __('Status', 'listeo_core'),
            'desc' => '',
            'id'   => 'status',
            'type' => 'select',
            'options' => array(
                'pending' => __('Pending', 'listeo_core'),
                'approved' => __('Approved', 'listeo_core'),
                'rejected' => __('Rejected', 'listeo_core'),
                'completed' => __('Completed', 'listeo_core'),
            ),
            'description' => 'Selecting "Approved" in case of paid claim will send payment link to user and automatically assign the package to the user when the order will be paid.
            Selecting "Completed" will skip the payment and mark the claim as completed and will not allow any further action on the claim.',
        ));
    }

    // get all the packages of type "package" to display in the dropdown list
    function get_packages()
    {
        $listing_package                 = get_term_by('slug', 'listing_package', 'product_type');
        $listing_package_subscription    = get_term_by('slug', 'listing_package_subscription', 'product_type');
        $find_terms = array();
        if ($listing_package) {
            $find_terms[]                = $listing_package->term_id;
        }
        if ($listing_package_subscription) {
            $find_terms[]                = $listing_package_subscription->term_id;
        }

        $posts_in                    = array_unique((array) get_objects_in_term($find_terms, 'product_type'));
        $args                        = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'order'          => 'ASC',
            'orderby'        => 'title',
            'include'        => $posts_in,
        );

        $options = array();
        $packages = get_posts($args);
        foreach ($packages as $package) {
            $options[$package->ID] = $package->post_title;
        }
        return $options;
    }
    /**
     * register_post_types function.
     *
     * @access public
     * @return void
     */
    public function register_post_types()
    {
        //register post type "claims"
        register_post_type(
            "claim",
            apply_filters("register_post_type_claim", array(
                'labels' => array(
                    'name'                    => __('Claims', 'listeo_core'),
                    'singular_name'         => __('Claim', 'listeo_core'),
                    'menu_name'             => esc_html__('Claims', 'listeo_core'),
                    'all_items'             => sprintf(esc_html__('All %s', 'listeo_core'), __('Claims', 'listeo_core')),
                    'add_new'                 => esc_html__('Add New', 'listeo_core'),
                    'add_new_item'             => sprintf(esc_html__('Add %s', 'listeo_core'), __('Claim', 'listeo_core')),
                    'edit'                     => esc_html__('Edit', 'listeo_core'),
                    'edit_item'             => sprintf(esc_html__('Edit %s', 'listeo_core'), __('Claim', 'listeo_core')),
                    'new_item'                 => sprintf(esc_html__('New %s', 'listeo_core'), __('Claim', 'listeo_core')),
                    'view'                     => sprintf(esc_html__('View %s', 'listeo_core'), __('Claim', 'listeo_core')),
                    'view_item'             => sprintf(esc_html__('View %s', 'listeo_core'), __('Claim', 'listeo_core')),
                    'search_items'             => sprintf(esc_html__('Search %s', 'listeo_core'), __('Claims', 'listeo_core')),
                    'not_found'             => sprintf(esc_html__('No %s found', 'listeo_core'), __('Claims', 'listeo_core')),
                    'not_found_in_trash'     => sprintf(esc_html__('No %s found in trash', 'listeo_core'), __('Claims', 'listeo_core')),
                    'parent'                 => sprintf(esc_html__('Parent %s', 'listeo_core'), __('Claim', 'listeo_core')),
                ),
                'description' => sprintf(esc_html__('This is where you can create and manage %s.', 'listeo_core'), __('Claims', 'listeo_core')),
                'public'                 => true,
                'show_ui'                 => true,
                'show_in_rest'             => true,
                'capability_type'         => array('listing', 'listings', 'post'),
                'map_meta_cap'          => true,
                'publicly_queryable'     => false,
                'exclude_from_search'     => true,
                'hierarchical'             => false,
                'menu_icon'           => 'dashicons-id-alt',
                'query_var'             => false,
                'supports'                 => array('author', 'custom-fields', 'publicize'),
                'has_archive'             => false,
                'show_in_nav_menus'     => false,
                'show_in_admin_bar'     => true
            ))
        );
    }

    /**
     * Adds columns to admin listing of listing Listings.
     *
     * @param array $columns
     * @return array
     */
    public function columns($columns)
    {
        if (!is_array($columns)) {
            $columns = array();
        }

        $columns["status"]              = __("Status", 'listeo_core');
        return $columns;
    }

    /**
     * Displays the content for each custom column on the admin list for listing Listings.
     *
     * @param mixed $column
     */
    public function custom_columns($column)
    {
        global $post;

        switch ($column) {
            case "status":
                $type = get_post_meta($post->ID, 'status', true);

                switch ($type) {
                    case 'pending':
                        esc_html_e('Pending', 'listeo_core');
                        break;
                    case 'approved':
                        esc_html_e('Approved', 'listeo_core');
                        break;
                    case 'rejected':
                        esc_html_e('Rejected', 'listeo_core');
                        break;
                    case 'completed':
                        esc_html_e('Completed', 'listeo_core');
                        break;

                    default:
                        esc_html_e('Pending', 'listeo_core');
                        break;
                }
                break;

       
        }
    }


    function listeo_send_claim_request()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'listeo_send_claim')) {
            echo json_encode(array(
                'success' => false,
                'message' => esc_html__('Security verification failed. Please refresh the page and try again.', 'listeo_core')
            ));
            die();
        }

        $listing_id = sanitize_text_field($_POST["listing_id"]);
        // get current user id
        $user = get_current_user_id();
        $firstname = sanitize_text_field($_POST["firstname"]);
        $lastname = sanitize_text_field($_POST["lastname"]);
        if(get_option('listeo_enable_paid_claims')){
            $package = sanitize_text_field($_POST["package"]);
        } else {
            $package = '';
        }
        
        $email = sanitize_email($_POST["email"]);
        $phone = sanitize_text_field($_POST["phone"]);
        $message = sanitize_textarea_field($_POST["message"]);
        $new_user_with_claim = false;
        // register user if he is not logged in
        if ($user == 0) {
            // check if user with this email already exists
            $registration_errors = array();
            if (!get_option('users_can_register')) {
                // Registration closed, display error
                $registration_errors[] = "registration_closed";
            }
            if (get_option('listeo_registration_hide_username')) {
                $email_arr = explode('@', $email);
                $user_login = sanitize_user(trim($email_arr[0]),
                    true
                );
            } else {
                $user_login = sanitize_user(trim($_POST['username']));
            }
            $role = "owner";

            $password = (!empty($_POST['password'])) ? sanitize_text_field($_POST['password']) : false;
            
            $privacy_policy_status = get_option('listeo_privacy_policy');

            $privacy_policy_pass = true;
            if ($privacy_policy_status) {
                $privacy_policy_pass = false;
                if (isset($_POST['privacy_policy']) && !empty($_POST['privacy_policy'])) :
                    $privacy_policy_pass = true;
                else :
                    $registration_errors[] = "policy-fail";

                endif;
            }

            $terms_and_conditions_status =  get_option('listeo_terms_and_conditions_req');
            $terms_and_conditions_pass = true;
            if ($terms_and_conditions_status) {
                $terms_and_conditions_pass = false;
                if (isset($_POST['terms_and_conditions']) && !empty($_POST['terms_and_conditions'])) :
                    $terms_and_conditions_pass = true;
                else :
                    $registration_errors[] = "terms-fail";

                endif;
            }

            $recaptcha_status = get_option('listeo_recaptcha');
            $recaptcha_version = get_option('listeo_recaptcha_version');


            if ($recaptcha_status) {

                if ($recaptcha_status && $recaptcha_version == "v2") {
                    if ($recaptcha_version == "v2" && isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) :
                        $secret = get_option('listeo_recaptcha_secretkey');
                        //get verify response data using POST method
                        $verifyResponse = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                            'body' => array(
                                'secret' => $secret,
                                'response' => $_POST['g-recaptcha-response'],
                                'remoteip' => $_SERVER['REMOTE_ADDR']
                            )
                        ));

                        if (is_wp_error($verifyResponse)) {
                            $registration_errors[] = 'captcha-fail';
                        } else {
                            $responseData = json_decode(wp_remote_retrieve_body($verifyResponse));
                            if ($responseData && $responseData->success) :
                                //passed captcha, proceed to register
                            else :
                                $registration_errors[] = 'captcha-fail';
                            endif;
                        }
                    else :
                        $registration_errors[] = 'captcha-no';
                    endif;
                }


                if ($recaptcha_status && $recaptcha_version == "v3") {
                    if ($recaptcha_version == "v3" && isset($_POST['token']) && !empty($_POST['token'])) :
                        //your site secret key
                        $secret = get_option('listeo_recaptcha_secretkey3');
                        //get verify response data using POST method
                        $verifyResponse = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                            'body' => array(
                                'secret' => $secret,
                                'response' => $_POST['token'],
                                'remoteip' => $_SERVER['REMOTE_ADDR']
                            )
                        ));

                        if (is_wp_error($verifyResponse)) {
                            $registration_errors[] = 'captcha-fail';
                        } else {
                            $responseData_w = wp_remote_retrieve_body($verifyResponse);
                            $responseData = json_decode($responseData_w);

                            // Check for valid response and score
                            if ($responseData && $responseData->success == true && 
                                isset($responseData->score) && $responseData->score >= 0.5 &&
                                isset($responseData->action) && ($responseData->action == 'submit' || $responseData->action == 'claim')) :
                                //passed captcha, proceed to register
                            else :
                                $registration_errors[] = 'captcha-fail';
                            endif;
                        }
                    else :
                        $registration_errors[] = 'captcha-no';
                    endif;
                }

                if($recaptcha_version == 'hcaptcha') {
                    if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) :
                        $secret = get_option('listeo_hcaptcha_secretkey');
                        //get verify response data
                        $verifyResponse = wp_remote_post('https://hcaptcha.com/siteverify', array(
                            'body' => array(
                                'secret' => $secret,
                                'response' => $_POST['h-captcha-response'],
                                'remoteip' => $_SERVER['REMOTE_ADDR']
                            )
                        ));

                        if (is_wp_error($verifyResponse)) {
                            $registration_errors[] = 'captcha-fail';
                        } else {
                            $responseData = json_decode(wp_remote_retrieve_body($verifyResponse));
                            if ($responseData && $responseData->success) :
                                //passed captcha, proceed to register
                            else :
                                $registration_errors[] = 'captcha-fail';
                            endif;
                        }
                    else :
                        $registration_errors[] = 'captcha-no';
                    endif;
                }

                if($recaptcha_version == 'turnstile') {
                    if (isset($_POST['cf-turnstile-response']) && !empty($_POST['cf-turnstile-response'])) :
                        $secret = get_option('listeo_turnstile_secretkey');
                        //get verify response data
                        $verifyResponse = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                            'body' => array(
                                'secret' => $secret,
                                'response' => $_POST['cf-turnstile-response'],
                                'remoteip' => $_SERVER['REMOTE_ADDR']
                            )
                        ));

                        if (is_wp_error($verifyResponse)) {
                            $registration_errors[] = 'captcha-fail';
                        } else {
                            $responseData = json_decode(wp_remote_retrieve_body($verifyResponse));
                            if ($responseData && $responseData->success) :
                                //passed captcha, proceed to register
                            else :
                                $registration_errors[] = 'captcha-fail';
                            endif;
                        }
                    else :
                        $registration_errors[] = 'captcha-no';
                    endif;
                }
            }

            $custom_registration_fields = array();

            if (empty($registration_errors)) {
                $user_class = new Listeo_Core_Users;
                $phone = false;
                $user = $user_class->register_user($email, $user_login, $firstname, $lastname, $role, $phone, $password, $custom_registration_fields);
                if (!is_wp_error($user)) {
                    $new_user_with_claim = true;
                   
                } else {

                    $registration_errors[] = $user->get_error_code();
                  
                  
                }
            } 

            if(!empty($registration_errors)){ 
                
                if ($registration_errors) {
                    foreach ($registration_errors as $key => $error) {
                        switch ($error) {
                            case 'email':
                                $errors[] = esc_html__('The email address you entered is not valid.', 'listeo_core');
                                break;
                            case 'email_exists':
                                $errors[] = esc_html__('An account exists with this email address.', 'listeo_core');
                                break;
                            case 'closed':
                                $errors[] = esc_html__('Registering new users is currently not allowed.', 'listeo_core');
                                break;
                            case 'captcha-no':
                                $errors[] = esc_html__('Please check reCAPTCHA checbox to register.', 'listeo_core');
                                break;
                            case 'username_exists':
                                $errors[] =  esc_html__('This username already exists.', 'listeo_core');
                                break;
                            case 'captcha-fail':
                                $errors[] = esc_html__("You're a bot, aren't you?.", 'listeo_core');
                                break;
                            case 'policy-fail':
                                $errors[] = esc_html__("Please accept the Privacy Policy to register account.", 'listeo_core');
                                break;
                            case 'empty_password':
                                $errors[] = esc_html__('You need to enter a password to login.', 'listeo_core');
                                break;
                            case 'terms-fail':
                                $errors[] = esc_html__("Please accept the Terms and Conditions to register account.", 'listeo_core');
                                break;
                            case 'otp-fail':
                                $errors[] = esc_html__("Your one time verification code was not correct, please try again.", 'listeo_core');
                                break;
                            case 'first_name':
                                $errors[] = esc_html__("Please provide your first name", 'listeo_core');
                                break;
                            case 'last_name':
                                $errors[] = esc_html__("Please provide your last name", 'listeo_core');
                                break;
                            case 'empty_user_login':
                                $errors[] = esc_html__("Please provide your user login", 'listeo_core');
                                break;
                            case 'empty_password':
                                $errors[] = esc_html__('You need to enter a password to login.', 'listeo_core');
                                break;
                            case 'strong_password':
                                $errors[] = esc_html__('You password is too weak.', 'listeo_core');
                                break;
                            case 'password-no':
                                $errors[] = esc_html__("You have forgot about password.", 'listeo_core');
                                break;
                            case 'registration_closed':
                                $errors[] = esc_html__("Registration is closed.", 'listeo_core');
                                break;
                            case 'incorrect_password':
                                $err = __(
                                    "The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
                                    'listeo_core'
                                );
                                $errors[] =  sprintf($err, wp_lostpassword_url());
                                break;

                            default:
                                $errors[] = esc_html__('An unknown error occurred. Please try again.', 'listeo_core');
                                # code...
                                break;
                        }
                    }
                    echo json_encode(array(
                        'success' => false,
                        'message' => implode(", ", $errors)
                    ));
                    die();
                } 
               
            }

        }
        // handle file upload
        $upload_file_status = get_option('listeo_file_upload_claims');
        if($upload_file_status){
            if (isset($_FILES['claim_file']) && !empty($_FILES['claim_file']['name'])) {
                $file = $_FILES['claim_file'];

                $upload = wp_handle_upload($file, array('test_form' => false));

                if (isset($upload['error']) && $upload['error'] != 0) {
                    echo json_encode(array(
                        'success' => false,
                        'message' => esc_html__('There was an error uploading the file. Please try again.', 'listeo_core')
                    ));
                    die();
                }

                $file_path = $upload['file'];
                $file_name = basename($file_path);
                $file_url = $upload['url'];
            }
        }
        

        // based on the data above create a new post in the "claim" post type
        $post_id = wp_insert_post(array(
            'post_title' => sprintf(__('Claim for listing %s by %s %s', 'listeo_core'), get_the_title($listing_id), $firstname, $lastname),
            'post_type' => 'claim',
            'post_status' => 'publish',
            'post_author' => $user,
            'meta_input' => array(
                'listing_id' => $listing_id,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'package' => $package,
                'email' => $email,
                'phone' => $phone,
                'message' => $message,
                'status' => 'pending',
                
            )
        ));

        if ($upload_file_status && $post_id && $upload['url']) {
            // attach the file to the claim 
            $attachment = array(
                'post_mime_type' => $upload['type'],
                'post_title' => $file_name,
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
            if (!is_wp_error($attach_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                wp_update_attachment_metadata($attach_id, $attach_data);
                update_post_meta($post_id, 'file', $attach_id);
            }
        }



        // if the post was created successfully, return a success message
        if ($post_id) {
            $pay_to_claim = get_option('listeo_skip_claim_approval');
            if($pay_to_claim){
                // Temporarily remove the hook to prevent recursive calls
                remove_action('updated_post_meta', array($this, 'claim_post_update'), 2);

                update_post_meta($post_id, 'status', 'approved');
                // create a new order for the package, send it to user and return payment link
                $package_id = get_post_meta($post_id, 'package', true);
                $user_id = get_post_field('post_author', $post_id);
                $listing_id = get_post_meta($post_id, 'listing_id', true);

                // Check if package is free (price = 0) and handle accordingly
                $product = wc_get_product($package_id);
                $package_price = $product ? $product->get_price() : 0;

                if ($package_price == 0) {
                    // Free package - complete the claim immediately without payment
                    update_post_meta($post_id, 'status', 'completed');

                    // Re-add the hook after status updates
                    add_action('updated_post_meta', array($this, 'claim_post_update'), 2, 4);

                    // Assign the free package to the user (same as paid order flow)
                    $user_package_id = listeo_core_give_user_package($user_id, $package_id, 0);

                    if ($user_package_id) {
                        // Assign the listing to the package and sync features
                        listeo_core_approve_listing_with_package($listing_id, $user_id, $user_package_id);
                    }

                    // Process the claim completion (transfer ownership, verify listing)
                    // This also sends the "claim completed" email - no need for a separate
                    // "approved" email since the claim is instantly completed for free packages
                    $this->complete_claim_process($post_id, $listing_id, $user_id, $package_id);

                    echo json_encode(array(
                        'success' => true,
                        'message' => esc_html__('Your claim request has been approved and completed successfully! You can now manage your listing.', 'listeo_core'),
                        'button_text' => esc_html__('Go to Dashboard', 'listeo_core'),
                        'payment_url' => get_permalink(get_option('listeo_dashboard_page')),
                        'reload' => false,
                    ));
                    die();
                } else {
                    // Paid package - create order and payment flow

                    // Re-add the hook after status update (only 'approved' was set)
                    add_action('updated_post_meta', array($this, 'claim_post_update'), 2, 4);

                    // check if claim post already has order id field
                    if(!get_post_meta($post_id,'order_id',true)){

                        $order = $this->create_payment_order($user_id, $listing_id, $package_id, $post_id);
                    } else {
                        $order_id = get_post_meta($post_id, 'order_id', true);
                        $order = wc_get_order($order_id);
                    }

                    if($order){
                        $args = array(
                            'user_id' => $user_id,
                            'listing_id' => get_post_meta($post_id, 'listing_id', true),
                            'package_id' => $package_id,
                            'claim_id' => $post_id,
                            'payment_url' => $order->get_checkout_payment_url(),
                            'order_id' => $order->get_id()
                        );
                        //  do_action('listeo_mail_to_user_claim_approved', $args);
                        echo json_encode(array(
                            'success' => true,
                            'message' => esc_html__('Please click the link below to pay for the package and complete the claim.', 'listeo_core'),
                            'button_text' => esc_html__('Pay for the package', 'listeo_core'),
                            'payment_url' => $order->get_checkout_payment_url(),
                            'reload'    => false,
                        ));
                    } else {
                        echo json_encode(array(
                            'success' => false,
                            'message' => esc_html__('Failed to create payment order. Please try again or contact support.', 'listeo_core')
                        ));
                    }
                    die();
                }
            }
            if (get_option('listeo_admin_claim_notification')) {
                $admin_email = get_option('admin_email');
                // send email directly to the admin
                $args = array(
                    'user_id' => $user,
                    'listing_id' => $listing_id,
                    'claim_id' => $post_id
                );
                do_action('listeo_mail_to_admin_claim_request', $args);
            }

            $user_id = get_post_field('post_author', $post_id);
            $user = get_userdata($user_id);
            $email = $user->user_email;
            $args = array(
                'user_id' => $user_id,
                'listing_id' => get_post_meta($post_id, 'listing_id', true),
                'claim_id' => $post_id
            );
            do_action('listeo_mail_to_user_claim_pending', $args);



            if($new_user_with_claim ){
                echo json_encode(array(
                    'success' => true,
                    'message' => esc_html__('Claim request sent successfully. You will receive an email once the claim is approved or rejected. Please check your email for login details.', 'listeo_core'),
                    'button_text' => esc_html__('Claim request sent', 'listeo_core'),
                    'reload'    => true,
                ));

            } else {
                echo json_encode(array(
                    'success' => true,
                    'message' => esc_html__('Claim request sent successfully. You will receive an email once the claim is approved or rejected.', 'listeo_core'),
                    'button_text' => esc_html__('Claim request sent', 'listeo_core'),
                ));
            }
        } else {
            echo json_encode(array(
                'success' => false,
                'message' => esc_html__('Error sending claim request. Please try again.', 'listeo_core')
            ));
        }
        die();
    }



}
