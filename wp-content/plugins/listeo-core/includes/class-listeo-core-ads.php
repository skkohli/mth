<?php
// Exit if accessed directly
if (! defined('ABSPATH'))
    exit;



/**
 * WP_listing_Manager_Content class.
 */
class Listeo_Core_Ads
{

    /**
     * Dashboard message.
     *
     * @access private
     * @var string
     */
    private $dashboard_message = '';

    public $table_name = 'listeo_core_ad_stats';

    public function __construct()
    {

        add_action('init', array($this, 'register_post_types'), 5);
       
        // Add a meta box to the post editor screen
        add_action('cmb2_admin_init', array($this, 'add_ads_meta_boxes'));
      
        add_shortcode('listeo_ads', array($this, 'listeo_ads'));

        add_action('wp', array($this, 'dashboard_ads_action_handler'));

        // I need ajax function that based on form input will return the price of the ad
        add_action('wp_ajax_listeo_get_ad_price', array($this, 'get_ad_price'));
        // enquerue the ads caluclation scrip if user is on add management page
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_order_status_completed', array($this, 'ad_paid'), 9, 3);

        // Auto-complete WC orders that were placed for an ad campaign so the ad activates
        // immediately after payment instead of sitting in 'Processing' (with the user's
        // dashboard showing "Pending Payment") until an admin manually marks it Completed.
        // The ad campaign product isn't a shippable item, so completing it on payment is safe.
        // This fires when the payment gateway confirms payment; we promote to 'completed' so
        // 'woocommerce_order_status_completed' then triggers ad_paid() in the normal chain.
        add_action('woocommerce_payment_complete', array($this, 'maybe_complete_ad_order'), 5, 1);

        // add columns to the ad post type
        add_filter('manage_listeoad_posts_columns', array($this, 'ad_columns'));
        add_action('manage_listeoad_posts_custom_column', array($this, 'ad_columns_content'), 10, 2);

        add_action('wp_ajax_track_ad_view',array($this, 'track_ad_view'));
        add_action('wp_ajax_nopriv_track_ad_view',array($this, 'track_ad_view'));

        add_action('wp_ajax_track_ad_click',array($this, 'track_ad_click'));
        add_action('wp_ajax_nopriv_track_ad_click',array($this, 'track_ad_click'));

        add_action('cleanup_ad_stats_hook',array($this, 'cleanup_old_ad_stats'));
        add_action('save_post',array($this, 'save_multicheck_values_as_separate_meta'));
        add_filter('cmb2_override_meta_value', array($this, 'override_multicheck_value_display'), 10, 4);

        // detect if the post type "listeoad" was edited in the admin and value of the field "status" was changed
        add_action('save_post', array($this, 'ad_status_changed'), 10, 3);
        
        add_action('update_ad_budgets', array($this, 'update_all_ad_budgets'));
        if (!wp_next_scheduled('update_ad_budgets')) {
            wp_schedule_event(time(), 'hourly', 'update_ad_budgets');
        }

        // Prevent the ad campaign product from being added to the regular cart
        add_filter('woocommerce_add_to_cart_validation', array($this, 'prevent_ad_product_in_cart'), 10, 2);
        // Remove the ad product from cart if it ever appears there (e.g. pay-for-order page adds it)
        add_action('woocommerce_before_calculate_totals', array($this, 'remove_ad_product_from_cart'));

        // schedule job that check if any ad has  status 'paid_and_waiting' and if th 'start_date' is in the past, change the status to 'active'
        add_action('check_ad_status', array($this, 'check_ad_status'));
        if (!wp_next_scheduled('check_ad_status')) {
            wp_schedule_event(time(), 'daily', 'check_ad_status');
        }

        add_action('wp_ajax_post_title_autocomplete', array($this,'listing_title_autocomplete'));


    }


    function check_ad_status(){
        $args = array(
            'post_type' => 'listeoad',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'ad_status',
                    'value' => 'paid_and_waiting',
                    'compare' => '='
                ),
                array(
                    'key' => 'start_date',
                    'value' => current_time('timestamp'),
                    'compare' => '<='
                )
            )
        );
        $ads = get_posts($args);
        foreach($ads as $ad){
            update_post_meta($ad->ID, 'ad_status', 'active');
            $this->sync_listing_ad_meta($ad->ID, 'active');
        }
    }

    function override_multicheck_value_display($value, $object_id, $field_args, $field)
    {
        if ('placement' === $field->id()) {
            // Fetch all saved meta values for the key
            $saved_values = get_post_meta($object_id, 'placement', false);

            // Return the saved values as an array (since CMB2 expects it for multicheck)
            return $saved_values ? $saved_values : array();
        }

        return $value;
    }
    function save_multicheck_values_as_separate_meta($post_id)
    {
        // Check if the user is allowed to edit the post and if it's not an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if our custom field data is set in $_POST
        if (isset($_POST['placement']) && is_array($_POST['placement'])) {
            // First, delete any existing meta entries for this key to avoid duplicates
            delete_post_meta($post_id, 'placement');

            // Loop through each selected value and add it as separate meta entry
            foreach ($_POST['placement'] as $selected_value) {
                add_post_meta($post_id, 'placement', sanitize_text_field($selected_value), false);
            }
        }
    }
    // 2. Function to increment view count
    function increment_ad_view($ad_id, $campaign_type, $campaign_placement) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $date = current_time('Y-m-d');

        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (ad_id, campaign_type, views, date, campaign_placement) 
            VALUES (%d, %s, 1, %s, %s) 
            ON DUPLICATE KEY UPDATE views = views + 1",
            $ad_id, $campaign_type, $date, $campaign_placement
        ));
        
        $this->update_ad_budget($ad_id);

        return $result !== false;
    }

    // 3. Function to increment click count
    function increment_ad_click($ad_id, $campaign_type, $campaign_placement) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $date = current_time('Y-m-d');

        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (ad_id, campaign_type, clicks, date, campaign_placement) 
            VALUES (%d, %s, 1, %s, %s) 
            ON DUPLICATE KEY UPDATE clicks = clicks + 1",
            $ad_id, $campaign_type, $date, $campaign_placement
        ));
        $this->update_ad_budget($ad_id);
        return $result !== false;
    }

    // 4. AJAX handler for tracking views
    function track_ad_view() {
        check_ajax_referer('ad_tracking_nonce', 'nonce');
        $ad_id = intval($_POST['ad_id']);
        $campaign_type = sanitize_text_field($_POST['campaign_type']);
        if(isset($_POST['campaign_placement'])){
            $campaign_placemnt = sanitize_text_field($_POST['campaign_placement']);
        }else{
            $campaign_placemnt = 'archive';
        }
        
        // 
        if ($campaign_type ==='ppv' && $this->is_unique_view($ad_id, $campaign_type)) {
            
            $result = $this->increment_ad_view($ad_id, $campaign_type, $campaign_placemnt);
            wp_send_json_success($result);
        }
        wp_send_json_error('Invalid campaign type for view tracking');
    }
   

    // 5. AJAX handler for tracking clicks
    function track_ad_click() {
        check_ajax_referer('ad_tracking_nonce', 'nonce');
        $ad_id = intval($_POST['ad_id']);
        $campaign_type = sanitize_text_field($_POST['campaign_type']);
        $campaign_placemnt = sanitize_text_field($_POST['campaign_placement']);

        if ($this->is_unique_click($ad_id, $campaign_type)) {
            $result = $this->increment_ad_click($ad_id, $campaign_type, $campaign_placemnt);
            wp_send_json_success($result);
        }
        wp_send_json_error('Not a unique click');
    }

    function is_unique_view($ad_id, $campaign_type)
    {
        
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $view_key = md5($ad_id . $campaign_type . $ip_address . $user_agent);

        if (!isset($_COOKIE['ad_view_' . $view_key])) {
            // Set a cookie that expires in 24 hours
            setcookie('ad_view_' . $view_key, '1', time() + 86400, '/');
            return true;
        }
        return false;
    }

    // New function to check for unique click
    function is_unique_click($ad_id, $campaign_type)
    {
        
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $click_key = md5($ad_id . $campaign_type . $ip_address . $user_agent);

        if (!isset($_COOKIE['ad_click_' . $click_key])) {
            // Set a cookie that expires in 7 days
            setcookie('ad_click_' . $click_key, '1', time() + 604800, '/');
            return true;
        }
        return false;
    }


    function cleanup_old_ad_stats()
    {
        global $wpdb;
        $table_name = $this->table_name;
        $older_than_date = date('Y-m-d', strtotime('-30 days'));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE date < %s",
                $older_than_date
            )
        );
    }

    private function update_ad_budget($ad_id)
    {
        $budget = (float) get_post_meta($ad_id, 'budget', true);
        
        $spent = $this->calculate_spent($ad_id);
        
        $remaining = max(0, $budget - $spent);
        update_post_meta($ad_id, 'remaining_budget', $remaining);
        
        if ($remaining <= 0) {
            update_post_meta($ad_id, 'ad_status', 'completed');

            $listing_id = get_post_meta($ad_id, 'listing_id', true);
            delete_post_meta($listing_id, 'ad_status');
            delete_post_meta($listing_id, 'ad_id');
            delete_post_meta($listing_id, 'ad_placement');
            delete_post_meta($listing_id, 'ad_type');

           
        }
    }

    public function update_all_ad_budgets()
    {
        $ads = get_posts(array('post_type' => 'listeoad', 'numberposts' => -1));
        foreach ($ads as $ad) {
            $this->update_ad_budget($ad->ID);
        }
    }

    private function calculate_spent($ad_id)
    {

        $price_home_click = get_option('listeo_ad_campaigns_price_home_click');
        $price_search_click = get_option('listeo_ad_campaigns_price_search_click');
        $price_sidebar_click = get_option('listeo_ad_campaigns_price_sidebar_click');
        $price_home_view = get_option('listeo_ad_campaigns_price_home_view');
        $price_search_view = get_option('listeo_ad_campaigns_price_search_view');
        $price_sidebar_view = get_option('listeo_ad_campaigns_price_sidebar_view'); 
    
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_name;
        $placements = array(
            'home' => array(
                'ppv' => floatval($price_home_view), 
                'ppc' => floatval($price_home_click)
            ),    // $5 per 1000 views or $0.5 per click
            'archive' => array(
                'ppv' => floatval($price_search_view), 
                'ppc' => floatval($price_search_click)
            ),  // $7 per 1000 views or $0.7 per click
            'sidebar' => array(
                'ppv' => floatval($price_sidebar_view), 
                'ppc' => floatval($price_sidebar_click)
            )  // $3 per 1000 views or $0.3 per click
        );

        $total_spent = 0;

        foreach ($placements as $placement => $rates) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(views) as total_views, SUM(clicks) as total_clicks, campaign_type 
            FROM $table_name 
            WHERE ad_id = %d AND campaign_placement = %s
            GROUP BY campaign_type",
                $ad_id,
                $placement
            ));
         

            if ($result) {
               
                if ($result->campaign_type === 'ppv') {
                    $total_spent += ($result->total_views / 1000) * $rates['ppv'];
                } elseif ($result->campaign_type === 'ppc') {
                    
                    $total_spent += $result->total_clicks * $rates['ppc'];
                    
                }
            }
        }
       
        return floatval($total_spent);
    }
 


    public function enqueue_scripts()
    {
        if (is_page(get_option('listeo_ad_campaigns_page'))) {

            wp_enqueue_script('listeo-ad-calculation', LISTEO_CORE_URL . 'assets/js/ad-calculation.js', array('jquery'), '1.0', true);
            wp_localize_script('listeo-ad-calculation', 'ad_calculation', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ad-calculation-nonce'),
            ));
            wp_enqueue_script('jquery-ui-autocomplete');
        }
        wp_enqueue_script('listeo-ad-tracking', LISTEO_CORE_URL . 'assets/js/ad-tracking.js', array('jquery'), '1.0', true);
        wp_localize_script('listeo-ad-tracking', 'adTrackingAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ad_tracking_nonce')
        ));
    }

    function listing_title_autocomplete() {
    $term = sanitize_text_field($_GET['term']);
    $args = array(
        'post_type' => 'listing',
        'post_status' => 'publish',
        's' => $term,
        'posts_per_page' => 10,
    );
    $posts = get_posts($args);
    $results = array();
    foreach ($posts as $post) {
        $results[] = array(
            'id' => $post->ID,
            'label' => $post->post_title,
            'value' => $post->post_title
        );
    }
    wp_send_json($results);
}


    // 7. Function to display an ad (example usage)
    function display_ad($ad_id, $campaign_type) {
        // Fetch ad content based on $ad_id
        $ad_content = get_post_meta($ad_id, 'ad_content', true);
        $ad_link = get_post_meta($ad_id, 'ad_link', true);

        $output = '<div class="ad" data-ad-id="' . esc_attr($ad_id) . '" data-campaign-type="' . esc_attr($campaign_type) . '">';
        $output .= '<a href="' . esc_url($ad_link) . '" class="ad-link">';
        $output .= $ad_content;
        $output .= '</a></div>';

        return $output;
    }

    public function ad_columns()
    {
        return array(
            'cb' => '<input type="checkbox" />',
            'title' => __('Title'),
            'budget' => __('Budget'),
            'remaining' => __('Remaining'),
            'ad_status' => __('Status'),
          
            'placement' => __('Placement'),
            'type' => __('Type'),
        );
    }
    public function ad_columns_content($column, $post_id)
    {
        switch ($column) {
            case 'budget':
                 $budget = get_post_meta($post_id, 'budget', true);
                 if($budget){
                    echo listeo_output_price($budget);
                 } else {
                     echo '-';
                 }
                break;
            case 'remaining':
                $remaining = get_post_meta($post_id, 'remaining_budget', true);
                 if($remaining){
                    echo listeo_output_price($remaining);
                 } else {
                     echo '-';
                 }
                break;
            case 'ad_status':
                echo get_post_meta($post_id, 'ad_status', true);
                break;
            case 'placement':
                $placemnt = get_post_meta($post_id, 'placement', false);
                if (is_array($placemnt)) {
                    echo implode(', ', $placemnt);
                } else {
                    echo $placemnt;
                }
                break;
            case 'type':
                echo get_post_meta($post_id, 'type', true);
                break;
        }
    }



    /**
     * Prevent the ad campaign product from being added to the regular WooCommerce cart.
     * This product uses dynamic pricing set via wc_create_order() — its base price is $0.
     */
    public function prevent_ad_product_in_cart($passed, $product_id) {
        $ad_product_id = (int) get_option('listeo_ad_campaign_product_id', 0);
        if ($ad_product_id && $product_id === $ad_product_id) {
            wc_add_notice(__('This product cannot be purchased directly.', 'listeo_core'), 'error');
            return false;
        }
        return $passed;
    }

    /**
     * Remove the ad campaign product from the cart if it appears there.
     * Ad purchases go through wc_create_order() only — the product should never be in the cart.
     */
    public function remove_ad_product_from_cart($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $ad_product_id = (int) get_option('listeo_ad_campaign_product_id', 0);
        if (!$ad_product_id) return;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if ((int) $cart_item['product_id'] === $ad_product_id) {
                $cart->remove_cart_item($cart_item_key);
            }
        }
    }

    public function register_post_types()
    {
        $labels = array(
            'name'               => _x('Ads', 'post type general name', 'listeo_core'),
            'singular_name'      => _x('Ad', 'post type singular name', 'listeo_core'),
            'menu_name'          => _x('Ads', 'admin menu', 'listeo_core'),
            'name_admin_bar'     => _x('Ad', 'add new on admin bar', 'listeo_core'),
            'add_new'            => _x('Add New', 'listeoad', 'listeo_core'),
            'add_new_item'       => __('Add New Ad', 'listeo_core'),
            'new_item'           => __('New Ad', 'listeo_core'),
            'edit_item'          => __('Edit Ad', 'listeo_core'),
            'view_item'          => __('View Ad', 'listeo_core'),
            'all_items'          => __('All Ads', 'listeo_core'),
            'search_items'       => __('Search Ads', 'listeo_core'),
            'parent_item_colon'  => __('Parent Ads:', 'listeo_core'),
            'not_found'          => __('No ads found.', 'listeo_core'),
            'not_found_in_trash' => __('No ads found in Trash.', 'listeo_core')
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __('Ad Campaigns for Listings.', 'listeo_core'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'listeo-ad'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'author'),
            'menu_icon'          => 'dashicons-megaphone',
        );

        register_post_type('listeoad', $args);
    }


    public function add_ads_meta_boxes()
    {

        $claim_admin_options = array(
            'id'           => 'listeo_core_ads_metaboxes',
            'title'        => __('Ad Campaign Options', 'listeo_core'),
            'object_types' => array('listeoad'), // Post type
            'show_names'   => true,
            'show_in_rest' => WP_REST_Server::READABLE,
        );
        $cmb_claim_admin = new_cmb2_box($claim_admin_options);

        // dropdown list with products of type "package"
        // $cmb_claim_admin->add_field(array(
        //     'name' => __('Selected Package', 'listeo_core'),
        //     'desc' => '',
        //     'id'   => 'package',
        //     'type' => 'select',
        //     'options_cb' => array($this, 'get_packages'),
        // ));

        // add status field to the claim post type
        $cmb_claim_admin->add_field(array(
            'name' => __('Listing ', 'listeo_core'),
            'desc' => '',
            'id'   => 'listing_id',
            'type' => 'select',
            'options_cb' => array($this, 'get_listings'),
            'description' => 'Select the listing for the ad',
        ));
        // woocommerce id field
        $cmb_claim_admin->add_field(array(
            'name' => __('WooCommerce Order ID', 'listeo_core'),
            'desc' => '',
            'id'   => 'order_id',
            'type' => 'text',
            'description' => 'Enter the Oder id of the ad',
        ));
        // budget field
        $cmb_claim_admin->add_field(array(
            'name' => __('Budget', 'listeo_core'),
            'desc' => '',
            'id'   => 'budget',
            'type' => 'text',
            'description' => 'Budget of the ad',
        ));

        $cmb_claim_admin->add_field(array(
            'name' => __('Status', 'listeo_core'),
            'desc' => '',
            'id'   => 'ad_status',
            'type' => 'select',
            'options' => array(
                'paused' => __('Paused', 'listeo_core'),
                'active' => __('Active', 'listeo_core'),
                'waiting' => __('Waiting for payment', 'listeo_core'),
                'paid_and_waiting' => __('Paid and waiting for start', 'listeo_core'),
                'expired' => __('Expired', 'listeo_core'),
                'completed' => __('Completed', 'listeo_core'),
            ),
            'description' => 'Select the status of the ad',
        ));

        // start date field
        $cmb_claim_admin->add_field(array(
            'name' => __('Start Date', 'listeo_core'),
            'desc' => '',
            'id'   => 'start_date',
            'type' => 'text_date_timestamp',
            'description' => 'Enter the start date of the ad',
        ));
        // end date field
        // $cmb_claim_admin->add_field(array(
        //     'name' => __('End Date', 'listeo_core'),
        //     'desc' => '',
        //     'id'   => 'end_date',
        //     'type' => 'text_date_timestamp',
        //     'description' => 'Enter the end date of the ad',
        // ));
        // placement field
        $cmb_claim_admin->add_field(array(
            'name' => __('Placement', 'listeo_core'),
            'desc' => '',
            'id'   => 'placement',
            'type'    => 'multicheck',
            'options' => array(
                'home' => __('Home Section', 'listeo_core'),
                'search' => __('Search Page', 'listeo_core'),
                'sidebar' => __('Sidebar', 'listeo_core'),
            ),
            'description' => 'Select the placement of the ad',
            'save_field' => false, // Disables the saving of this field.
        ));

 

        // typ of ad  - per click or per view
        $cmb_claim_admin->add_field(array(
            'name' => __('Type', 'listeo_core'),
            'desc' => '',
            'id'   => 'type',
            'type' => 'select',
            'options' => array(
                'ppc' => __('Per Click', 'listeo_core'),
                'ppv' => __('Per View', 'listeo_core'),
            ),
            'description' => 'Select the type of the ad',
        ));


        // price field
        $cmb_claim_admin->add_field(array(
            'name' => __('Budget', 'listeo_core'),
            'desc' => '',
            'id'   => 'budget',
            'type' => 'text',
            'description' => 'Enter the price of the ad',
        ));
        $cmb_claim_admin->add_field(array(
            'name' => __('Remaining Budget', 'listeo_core'),
            'desc' => '',
            'id'   => 'remaining_budget',
            'type' => 'text',
            'description' => 'Enter the price of the ad',
        ));

        // add field for listing category (supporting all listing type categories)
        $cmb_claim_admin->add_field(array(
            'name' => __('Category Filter', 'listeo_core'),
            'desc' => '',
            'id'   => 'ad_category_filter',
            'type' => 'select',
            'description' => 'Select the category filter for the ad (supports all listing type categories)',
            'options_cb' => array($this, 'get_all_listing_categories'),
            'show_option_none' => true,
        ));

        // add field for listing location as text input
        $cmb_claim_admin->add_field(array(
            'name' => __('Location', 'listeo_core'),
            'desc' => '',
            'id'   => '_address',
            'type' => 'text',
            'description' => 'Enter the location of the ad',
        ));
       

        // add field for listing region
        $cmb_claim_admin->add_field(array(
            'name' => __('Region', 'listeo_core'),
            'desc' => '',
            'id'   => 'taxonomy-region',
            'type' => 'select',
            'taxonomy' => 'region',
            'description' => 'Select the region of the ad',
            'options_cb' => array($this, 'get_regions'),
            'show_option_none' => true,
        ));

        // add checkbox for status of "logged in" users
        $cmb_claim_admin->add_field(array(
            'name' => __('Logged In Users Only', 'listeo_core'),
            'desc' => '',
            'id'   => 'only_loggedin',
            'type' => 'checkbox',
            'description' => 'Check this box if the ad should be shown only to logged in users',
        ));
  
        
    }

    function get_listing_categories()
    {
        $terms = get_terms('listing_category', array('hide_empty' => false));
        $options = array();
        foreach ($terms as $term) {
            $options[$term->slug] = $term->name;
        }
        return $options;
    }

    function get_all_listing_categories()
    {
        // Get all listing taxonomies and filter for category taxonomies
        $taxonomy_objects = get_object_taxonomies('listing', 'objects');
        $options = array();

        // Filter for category taxonomies (those ending with '_category' or named 'listing_category')
        foreach ($taxonomy_objects as $taxonomy) {
            if ($taxonomy->name === 'listing_category' || strpos($taxonomy->name, '_category') !== false) {
                // Skip 'region' as it's handled separately
                if ($taxonomy->name !== 'region') {
                    // Get terms for this taxonomy
                    $terms = get_terms($taxonomy->name, array('hide_empty' => false));
                    if (!empty($terms) && !is_wp_error($terms)) {
                        foreach ($terms as $term) {
                            // Prefix term value with taxonomy name for uniqueness
                            $prefixed_value = $taxonomy->name . ':' . $term->term_id;
                            $options[$prefixed_value] = $taxonomy->labels->name . ' - ' . $term->name;
                        }
                    }
                }
            }
        }

        return $options;
    }
    function get_regions()
    {
        $terms = get_terms('region', array('hide_empty' => false));
        $options = array();
        foreach ($terms as $term) {
            $options[$term->slug] = $term->name;
        }
        return $options;
    }
    
    public function get_ad_price()
    {

       
        if(!isset($_POST['ad'])){
            wp_send_json_error(__('No ad data', 'listeo_core'));
        }
        $data = $_POST['ad'];
        $type = $data['campaign_type'];
        $placement = $data['placement'];
        if(!$placement){
          $placement = array();
        }
        $budget = $data['budget'];

        $price_home_click = get_option('listeo_ad_campaigns_price_home_click');
        $price_search_click = get_option('listeo_ad_campaigns_price_search_click');
        $price_sidebar_click = get_option('listeo_ad_campaigns_price_sidebar_click');
        $price_home_view = get_option('listeo_ad_campaigns_price_home_view');
        $price_search_view = get_option('listeo_ad_campaigns_price_search_view');
        $price_sidebar_view = get_option('listeo_ad_campaigns_price_sidebar_view'); 
    
        $output = array();
        
        
        if($type == 'ppc'){
            if(in_array('home', $placement)){
                // calculate how many click on home can you get with the budget
                $home_price = $budget / $price_home_click; 
                // make sure the price is an integer and it's rounded down
                $home_price = floor($home_price);
                $output['home'] = $home_price;
            }
            if(in_array('search', $placement)){
                // calculate how many click on search can you get with the budget
                $search_price = $budget / $price_search_click; 
                $search_price = floor($search_price);
                $output['search'] = $search_price;
            }
            if(in_array('sidebar', $placement)){
                $price = $price_sidebar_click;
                // calculate how many click on sidebar can you get with the budget
                $sidebar_price = $budget / $price_sidebar_click;
                $sidebar_price = floor($sidebar_price);
                $output['sidebar'] = $sidebar_price;
            }
            $output['type'] = __('Clicks', 'listeo_core');
        } else {

            if(in_array('home', $placement)){
                // calculate how many view on home can you get with the budget
                $home_price = $budget / $price_home_view; 
                // price is per 1k views
                $home_price = floor($home_price);
                $output['home'] = $home_price *1000;
            }
            if(in_array('search', $placement)){
                // calculate how many view on search can you get with the budget
                $search_price = $budget / $price_search_view; 
                $search_price = floor($search_price);
                $output['search'] = $search_price * 1000;
            }
            if(in_array('sidebar', $placement)){
                // calculate how many view on sidebar can you get with the budget
                $sidebar_price = $budget / $price_sidebar_view;
                $sidebar_price = floor($sidebar_price);
                $output['sidebar'] = $sidebar_price * 1000;
            }
            $output['type'] = __('Impressions', 'listeo_core');
        }

        wp_send_json_success($output);
    }
    /**
     * User bookmarks shortcode
     */
    public function listeo_ads($atts)
    {

        if (! is_user_logged_in()) {
            return __('You need to be signed in to manage your ads.', 'listeo_core');
        }

        extract(shortcode_atts(array(
            'posts_per_page' => '25',
        ), $atts));
        $page = 1;
        ob_start();
        $template_loader = new Listeo_Core_Template_Loader;

        if (isset($_GET['add_new_ad'])) {
            $template_loader->set_template_data(
                array(
                    'message' => $this->dashboard_message
                )
            )->get_template_part('account/ad-submit');
        } else {
            $template_loader->set_template_data(array(
                'ids' => $this->get_user_ads($page, 10),
                'message' => $this->dashboard_message
            ))->get_template_part('account/ads');
       }

        return ob_get_clean();
    }

    function get_listings()
    {
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        );
        $listings = get_posts($args);
        $options = array();
        foreach ($listings as $listing) {
            $options[$listing->ID] = $listing->post_title;
        }
        return $options;
    }

    public function get_user_ads($page, $per_page)
    {
        $current_user = wp_get_current_user();


        $args = array(
            'author'            =>  $current_user->ID,
            'posts_per_page'   => -1,
            'orderby'          => 'title',
            'order'            => 'asc',
            'post_type'        => 'listeoad',
            'post_status'      => 'publish',
        );

        $q = get_posts($args);


        return $q;
    }




    function get_user_id()
    {
        global $current_user;
        wp_get_current_user();
        return $current_user->ID;
    }


    public function dashboard_ads_action_handler()
    {

        global $post;

        if (is_page(get_option('listeo_ad_campaigns_page'))) {
            if (isset($_POST['listeo-ad-submission']) && '1' == $_POST['listeo-ad-submission']) {

               
                    $listing_id = sanitize_text_field($_POST['listing_id']);
                    // if the listing id is not set, return an error
                    if(!$listing_id){
                        $this->dashboard_message =  '<div class="notification closeable error"><p>' . __('Please select a listing', 'listeo_core') . '</p><a class="close" href="#"></a></div>';
                        return;
                    }

                    $type = sanitize_text_field($_POST['ad_campaign_type']);

                    if($type == 'ppv'){
                        $title_type = esc_html__('Pay Per View', 'listeo_core'); 
                    } else {
                        $title_type = esc_html__('Pay Per Click', 'listeo_core');
                    }
                    $title = sprintf(__('%s Ad Campaign for %s', 'listeo_core'), $title_type, get_the_title($listing_id));

                    $placement = $_POST['placement'];
                    if (!$placement) {
                        $this->dashboard_message =  '<div class="notification closeable error"><p>' . __('Please select a placement', 'listeo_core') . '</p><a class="close" href="#"></a></div>';
                        return;
                    }
                    $budget = sanitize_text_field($_POST['budget']);

                    $data = array(
                        'placement'        => $_POST['placement'],
                        'budget'           => $budget,
                        'type'             => $type,
                        'listing_id'       => $listing_id,
                        'start_date'       => strtotime(sanitize_text_field($_POST['start_date'])),
                        'ad_category_filter' => sanitize_text_field($_POST['ad_category_filter']),
                        'taxonomy-region'           => sanitize_text_field($_POST['taxonomy-region']),
                        '_address'          => sanitize_text_field($_POST['_address']),
                        'only_loggedin' =>  (isset($_POST['only_loggedin'])) ? sanitize_text_field($_POST['only_loggedin']) : '',
                        'ad_status'        => 'waiting',
                        //'only_loggedin'    => sanitize_text_field($_POST['only_loggedin']),
                      //  'end_date'         => strtotime(sanitize_text_field($_POST['end_date'])),
                    );
                    

                    // Save the ad in the database
                    $ad = array(
                        'post_title' => $title,
                        'post_content' => '',
                        'post_status' => 'publish',
                        'post_author' => $this->get_user_id(),
                        'post_type' => 'listeoad'
                    );

                    $new_ad_id = wp_insert_post($ad);

                    if(is_wp_error($new_ad_id)){
                        $this->dashboard_message =  '<div class="notification closeable error"><p>' . $new_ad_id->get_error_message() . '</p><a class="close" href="#"></a></div>';
                    } else {  
                    // Write the $data values into postmeta table
                        foreach ($data as $key => $value) {
                            //if it's placement use add_post_meta to add multiple values
                            if ($key == 'placement') {
                                
                                foreach ($value as $val) {
                                    add_post_meta($new_ad_id, $key, $val, false);
                                }
                            } else {
                                update_post_meta($new_ad_id, $key, $value);
                            }
                        }
                        $this->dashboard_message =  '<div class="notification closeable success"><p>' . sprintf(__('%s has been added', 'listeo_core'), $title) . '</p><a class="close" href="#"></a></div>';


                    $product_id = get_option('listeo_ad_campaign_product_id');
                    if($product_id){
                        // create a woocommerce order based on the ad
                        $order = wc_create_order();
                        $user_id = get_current_user_id();
                        $user = get_userdata($user_id);

                        // Get the user's billing details (you may need to adjust these fields based on your setup)
                        $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
                        $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
                        $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
                        $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
                        $billing_city = get_user_meta($user_id, 'billing_city', true);
                        $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
                        $billing_country = get_user_meta($user_id, 'billing_country', true);
                        $billing_state = get_user_meta($user_id, 'billing_state', true);
                        $billing_email = get_user_meta($user_id, 'billing_email', true);
                        $billing_phone = get_user_meta($user_id, 'billing_phone', true);

                        // Set the billing details on the order
                        $order->set_address(array(
                            'first_name' => $billing_first_name,
                            'last_name'  => $billing_last_name,
                            'address_1'  => $billing_address_1,
                            'address_2'  => $billing_address_2,
                            'city'       => $billing_city,
                            'postcode'   => $billing_postcode,
                            'country'    => $billing_country,
                            'state'      => $billing_state,
                            'email'      => $billing_email,
                            'phone'      => $billing_phone,
                        ), 'billing');

                        // Set the shipping details on the order (if needed)
                        $order->set_address(array(
                            'first_name' => $billing_first_name,
                            'last_name'  => $billing_last_name,
                            'address_1'  => $billing_address_1,
                            'address_2'  => $billing_address_2,
                            'city'       => $billing_city,
                            'postcode'   => $billing_postcode,
                            'country'    => $billing_country,
                            'state'      => $billing_state,
                        ), 'shipping');

                        $product = wc_get_product($product_id);
                        
                       if( $budget>0){
                            $product->set_price($budget);
                        }
                      

                        $order->add_product($product, 1);


                        // prepare all data needed for the order


              
                        $user_id = get_current_user_id();
                        $email = get_userdata($user_id)->user_email;
                        
                        $order->set_customer_id($user_id);
                        $order->set_billing_email($email);
                        $order->update_meta_data('ad_id', $new_ad_id);
                        $order->calculate_totals();
                        $order->save();
                        $order->update_meta_data('ad_id', $new_ad_id);
                        $order->save_meta_data();
                        update_post_meta($new_ad_id, 'order_id', $order->get_id());
                        $payment_url = $order->get_checkout_payment_url();

                        // Clear the cart so the mini cart doesn't show the $0 base-price product
                        WC()->cart->empty_cart();

                        wp_redirect($payment_url);
                        exit;
                    }
                    // create a woocommerce order based on the ad
                  
                    wp_redirect(get_permalink());
                    exit;
                } 
            }

            //delete

            if (! empty($_REQUEST['action']) && ! empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'listeo_core_ads_actions')) {

                $action = sanitize_title($_REQUEST['action']);
                $_id = absint($_REQUEST['ad_id']);

                try {
                    //Get ad
                    $ad    = get_post($_id);
                    $ad_data = get_post($ad);
                    if (! $ad_data || 'listeoad' !== $ad_data->post_type) {
                        $title = false;
                    } else {
                        $title = esc_html(get_the_title($ad_data));
                    }


                    switch ($action) {

                        case 'delete':
                            // Trash it
                            wp_delete_post($_id);
                            $listing_id = get_post_meta($_id, 'listing_id', true);
                            delete_post_meta($listing_id, 'ad_status');
                            delete_post_meta($listing_id, 'ad_id');
                            delete_post_meta($listing_id, 'ad_placement');
                            delete_post_meta($listing_id, 'ad_type');

                            // Message
                            $this->dashboard_message =  '<div class="notification closeable success"><p>' . sprintf(__('%s has been deleted', 'listeo_core'), $title) . '</p><a class="close" href="#"></a></div>';

                            break;

                        default:
                            do_action('listeo_core_dashboard_do_action_' . $action);
                            break;
                    }

//                    do_action('listeo_core_my_listing_do_action', $action, $listing_id);
                } catch (Exception $e) {
                    $this->dashboard_message = '<div class="notification closeable error">' . $e->getMessage() . '</div>';
                }
            }

        }
    }


    /**
     * Auto-complete WooCommerce orders that were placed for an ad campaign.
     *
     * The ad campaign product is dynamically priced and has no physical fulfillment,
     * but WooCommerce keeps the order in 'processing' after payment unless the product
     * is flagged Virtual. Site admins often don't realise this, so the ad order sits
     * pending and the ad never activates (ad_paid() only fires on 'completed'),
     * while the user's dashboard shows "Pending Payment".
     *
     * Promote any ad-bearing paid order straight to 'completed' so the existing
     * ad_paid() handler runs and the campaign goes Active immediately.
     *
     * @param int $order_id
     */
    public function maybe_complete_ad_order($order_id)
    {
        if (!$order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        // Only act on orders tied to an ad campaign. The ad_id meta is set when the
        // ad-campaign order is created (see ~line 1018-1085 in this file).
        if (!$order->get_meta('ad_id')) {
            return;
        }
        // Skip if already in a terminal status to avoid noise / double processing.
        if (in_array($order->get_status(), array('completed', 'cancelled', 'refunded', 'failed'), true)) {
            return;
        }
        $order->update_status('completed', __('Auto-completed: Listeo ad campaign order does not require fulfillment.', 'listeo_core'));
    }

    /**
     * Booking Paid
     *
     * @param number $order_id with id of order
     *
     */
    public function ad_paid($order_id)
    {

        $order = wc_get_order($order_id);

        $ad_id = $order->get_meta('ad_id');
        if ($ad_id) {
           // check ad by ID configuration
            $ad = get_post($ad_id);
            if ($ad) {
                // check the start date of the ad
                $start_date = get_post_meta($ad_id, 'start_date', true);
                $current_date = current_time('timestamp');

                //if the start date is in the future, set the status to paid_and_waiting
                if ($start_date && $start_date > $current_date) {
                    update_post_meta($ad_id, 'ad_status', 'paid_and_waiting');
                } else {
                    update_post_meta($ad_id, 'ad_status', 'active');
                    $this->sync_listing_ad_meta($ad_id, 'active');
                }
            }
        }
    }


    function ad_status_changed($post_id, $post, $update)
    {
        if ($post->post_type !== 'listeoad' || !$update) {
            return;
        }

        // Read the ad_status that CMB2 just saved from $_POST
        if (isset($_POST['ad_status'])) {
            $new_status = sanitize_text_field($_POST['ad_status']);
        } else {
            return;
        }

        $this->sync_listing_ad_meta($post_id, $new_status);
    }

    /**
     * Sync listing meta based on ad status.
     * Sets ad meta on the listing when active, removes it when completed/expired/paused.
     */
    private function sync_listing_ad_meta($ad_id, $new_status)
    {
        $listing_id = get_post_meta($ad_id, 'listing_id', true);
        if (!$listing_id) {
            return;
        }

        if ($new_status === 'active') {
            $ad_placement = get_post_meta($ad_id, 'placement', true);
            $ad_type = get_post_meta($ad_id, 'type', true);

            update_post_meta($listing_id, 'ad_status', 'active');
            update_post_meta($listing_id, 'ad_id', $ad_id);
            update_post_meta($listing_id, 'ad_placement', $ad_placement);
            update_post_meta($listing_id, 'ad_type', $ad_type);
        } elseif (in_array($new_status, array('completed', 'expired', 'paused', 'waiting'), true)) {
            delete_post_meta($listing_id, 'ad_status');
            delete_post_meta($listing_id, 'ad_id');
            delete_post_meta($listing_id, 'ad_placement');
            delete_post_meta($listing_id, 'ad_type');
        }
    }
  
}