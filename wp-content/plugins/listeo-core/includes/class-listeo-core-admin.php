<?php

if (!defined('ABSPATH')) exit;

class Listeo_Core_Admin
{

    /**
     * The single instance of WordPress_Plugin_Template_Settings.
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null;

    /**
     * The main plugin object.
     * @var     object
     * @access  public
     * @since   1.0.0
     */


    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    

    /**
     * Suffix for Javascripts.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $script_suffix;

    /**
     * Prefix for plugin settings.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $base = '';

    /**
     * Available settings for plugin.
     * @var     array
     * @access  public
     * @since   1.0.0
     */
    public $settings = array();

    /**
     * Store old place IDs before meta updates
     * @var     array
     * @access  private
     */
    private $old_place_ids = array();

    /**
     * Store taxonomy menu items for reorganization
     * @var     array
     * @access  public
     * @since   1.9.50
     */
    public $taxonomy_items = array();

    public function __construct()
    {

        
        $this->_token = 'listeo';
        // $this->dir = dirname($this->file);
        //  $this->assets_dir = trailingslashit($this->dir) . 'assets';


        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        $this->base = 'listeo_';

        // Initialise settings
        add_action('init', array($this, 'init_settings'), 11);

        // Register plugin settings
        add_action('admin_init', array($this, 'register_settings'));

        // Handle force update check parameter
        add_action('admin_init', array($this, 'handle_force_update_check'));

        // Add settings page to menu
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_menu', array($this, 'add_regions_importer_menu'));
        add_action('admin_menu', array($this, 'add_bulk_categories_menu'));
        add_action('admin_menu', array($this, 'add_translation_importer_menu'));
        add_action('admin_menu', array($this, 'listeo_add_debug_menu_page'));
        
        // Reorganize listing type taxonomies into submenu
        add_action('admin_menu', array($this, 'reorganize_listing_taxonomies'), 999);
        
        // Enqueue admin styles for categories page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_categories_admin_styles'));
        
        // Add AJAX handler for rating migration
        add_action('wp_ajax_listeo_migrate_ratings', array($this, 'ajax_migrate_ratings'));

        // Capture place ID before meta fields are updated
        add_action('save_post', array($this, 'capture_old_place_id'), 1, 1);
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 1);
        
        // Add proactive Google reviews fetching for all listing updates
        add_action('save_post', array($this, 'proactive_google_reviews_fetch'), 20, 1);
        
        // Additional hook to catch programmatic meta updates (like from data scraper)
        add_action('updated_post_meta', array($this, 'on_place_id_meta_updated'), 10, 4);

        // Add AJAX handler for testing Google Maps API key
        add_action('wp_ajax_listeo_test_google_maps_api_key', array($this, 'test_google_maps_api_key'));
        
        // Add AJAX handler for testing Google Reviews API key
        add_action('wp_ajax_listeo_test_google_reviews_api_key', array($this, 'test_google_reviews_api_key'));

        // Add AJAX handler for resetting API usage statistics
        add_action('wp_ajax_listeo_reset_api_stats', array($this, 'ajax_reset_api_stats'));

        // Add settings link to plugins page
        //add_filter( 'plugin_action_links_' . plugin_basename( 'listeo_core' ) , array( $this, 'add_settings_link' ) );
        add_action('current_screen', array($this, 'conditional_includes'));
        add_action('admin_bar_menu', array($this, 'listeo_admin_bar'), 999);
    }

    function listeo_add_debug_menu_page(){
        // Add a submenu page under your plugin's main menu
        add_submenu_page(
            'listeo_settings', // The slug name for the parent menu
            'View Debug Log', // Page title
            'View Debug Log', // Menu title
            'manage_options', // Capability required to see this menu item
            'listeo_settings-debug-log', // Menu slug, used to uniquely identify the page
            array($this,'listeo_display_log_page') // Function to call to output the page content
        );
    }
    function listeo_admin_bar($wp_admin_bar)
    {
        // Check if plugins need updates - show everywhere (admin and frontend)
        $updates_needed = $this->check_listeo_plugins_for_updates();
        
        if ($updates_needed) {
            $wp_admin_bar->add_menu(
                array(
                    'id'    => 'listeo-plugins-update-required',
                    'title' => '<span style="color: #fff; background-color: #dc3545; padding: 4px 20px; border-radius: 3px; font-weight: bold;">Listeo plugins update required!</span>',
                    'href'  => admin_url('themes.php?page=tgmpa-install-plugins'),
                    'meta'  => array(
                        'title' => 'Some Listeo plugins need updates. Click to view details.'
                    )
                )
            );
        }

        // Check for remote updates (from purethemes.net)
        $remote_update = $this->check_listeo_remote_update();
        if ($remote_update && !empty($remote_update['update_available'])) {
            $wp_admin_bar->add_menu(
                array(
                    'id'    => 'listeo-update-available',
                    'title' => '<span style="color: #fff; background-color: #0073aa; padding: 4px 20px; border-radius: 3px; font-weight: bold;">' .
                               sprintf(__('Listeo update available (v%s)', 'listeo_core'), esc_html($remote_update['latest_version'])) .
                               '</span>',
                    'href'  => esc_url($remote_update['docs_link']),
                    'meta'  => array(
                        'title' => __('A new version of Listeo is available. Click to view changelog.', 'listeo_core'),
                        'target' => '_blank'
                    )
                )
            );
        }

        // Only show the regular Listeo menu on frontend
        if (is_admin()) {
            return;
        }
        $menu_id = 'listeo-core';
        $wp_admin_bar->add_menu(
            array(
                'id'    => $menu_id,
                'title' => __('Listeo Core', 'listeo_core'),
                'href'  => admin_url('admin.php?page=' . (class_exists('Listeo_Core_Addons_Dashboard') ? Listeo_Core_Addons_Dashboard::PAGE_SLUG : 'listeo_settings')),
            )
        );
        foreach ($this->settings as $section => $data) {
            $wp_admin_bar->add_menu(
                array(
                    'parent'    => $menu_id,
                    'title'  => preg_replace('/<i[^>]*>.*?<\/i>/', '', $data['title']),
                    'id'     => $menu_id . $section,
                    'href'  => add_query_arg(array('page' => 'listeo_settings', 'tab' => $section), admin_url('admin.php')),
                )
            );

        }
        $wp_admin_bar->add_menu(
            array(
                'id'    => $menu_id.'-editor',
                'title' => 'Listeo Editor',
                'href'  => admin_url('admin.php?page=listeo-fields-and-form'),
            )
        );
        $wp_admin_bar->add_menu(
            array(
                'parent'    => $menu_id . '-editor',
                'title'  => 'Submit Listing Builder',
                'id'     => $menu_id . '-submit-builder',
                'href'  => admin_url('admin.php?page=listeo-submit-builder'),
            )
        );
        $wp_admin_bar->add_menu(
            array(
                'parent'    => $menu_id . '-editor',
                'title'  => 'Search Forms Editor',
                'id'     => $menu_id . '-search-forms',
                'href'  => admin_url('admin.php?page=listeo-forms-builder'),
            )
        );

        $wp_admin_bar->add_menu(
            array(
                'parent'    => $menu_id . '-editor',
                'title'  => 'Listing Fields Manager',
                'id'     => $menu_id . '-listing-fields',
                'href'  => admin_url('admin.php?page=listeo-fields-builder'),
            )
        );

       
    }

    /**
     * Check if Listeo plugins need updates
     * @return bool
     */
    private function check_listeo_plugins_for_updates()
    {
        // Get TGMPA instance to read plugin configurations
        $tgmpa_plugins = array();
        if (function_exists('listeo_register_required_plugins')) {
            // Hook into TGMPA to get plugin configurations
            if (class_exists('TGM_Plugin_Activation') && isset($GLOBALS['tgmpa'])) {
                $tgmpa_instance = $GLOBALS['tgmpa'];
                if (isset($tgmpa_instance->plugins)) {
                    foreach ($tgmpa_instance->plugins as $plugin) {
                        if (isset($plugin['slug']) && isset($plugin['version'])) {
                            $tgmpa_plugins[$plugin['slug']] = array(
                                'name' => $plugin['name'],
                                'version' => $plugin['version']
                            );
                        }
                    }
                }
            }
        }
        
        // Define plugin mappings (slug to actual plugin file)
        $plugin_mappings = array(
            'listeo-core' => array(
                'file' => 'listeo-core/listeo-core.php',
                'name' => __('Listeo Core', 'listeo_core')
            ),
            'listeo-elementor' => array(
                'file' => 'listeo-elementor/listeo-elementor.php',
                'name' => __('Listeo Elementor', 'listeo_core')
            ),
            'listeo-forms-and-fields-editor' => array(
                'file' => 'listeo-forms-and-fields-editor/listeo-forms-and-fields-editor.php',
                'name' => __('Forms & Fields Editor', 'listeo_core')
            )
        );
        
        $updates_needed = false;
        
        foreach ($plugin_mappings as $slug => $plugin_info) {
            $plugin_file = $plugin_info['file'];
            $expected_version = isset($tgmpa_plugins[$slug]) ? $tgmpa_plugins[$slug]['version'] : 'Unknown';
            
            if (is_plugin_active($plugin_file)) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
                $current_version = $plugin_data['Version'];
                $is_updated = ($expected_version !== 'Unknown') ? version_compare($current_version, $expected_version, '>=') : true;
                
                if (!$is_updated) {
                    $updates_needed = true;
                    break;
                }
            }
        }
        
        return $updates_needed;
    }

    /**
     * Handle force update check via URL parameter
     * Usage: /wp-admin/?listeo_force_update_check=1
     */
    public function handle_force_update_check() {
        if (isset($_GET['listeo_force_update_check']) && $_GET['listeo_force_update_check'] === '1') {
            if (current_user_can('manage_options')) {
                delete_transient('listeo_remote_version_check');
                delete_transient('listeo_update_check_result');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-info is-dismissible"><p>' .
                         __('Listeo update cache cleared. Checking for updates...', 'listeo_core') .
                         '</p></div>';
                });
            }
        }
    }

    /**
     * Check for Listeo updates from remote server
     * Results are cached for 7 days to minimize server requests
     * Version comparison result is cached for 1 minute to reduce DB queries
     *
     * @return array|false Array with update info if available, false otherwise
     */
    private function check_listeo_remote_update() {
        // Only for admins (works in admin and frontend admin bar)
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Check short-term cache for comparison result (1 minute)
        $result_cache_key = 'listeo_update_check_result';
        $cached_result = get_transient($result_cache_key);
        if ($cached_result !== false) {
            return $cached_result === 'none' ? false : $cached_result;
        }

        $transient_key = 'listeo_remote_version_check';
        $cached = get_transient($transient_key);

        // Get current version from theme
        $theme = wp_get_theme('listeo');
        $current_version = $theme->get('Version');

        // If we have cached data, use it but compare version fresh
        if ($cached !== false && !isset($cached['error'])) {
            $update_available = version_compare($current_version, $cached['latest_version'], '<');
            $result = $update_available ? array(
                'latest_version' => $cached['latest_version'],
                'docs_link' => $cached['docs_link'],
                'update_available' => true
            ) : false;

            // Cache result for 1 minute
            set_transient($result_cache_key, $result === false ? 'none' : $result, MINUTE_IN_SECONDS);
            return $result;
        }

        // If cached error, don't try again until cache expires
        if ($cached !== false && isset($cached['error'])) {
            set_transient($result_cache_key, 'none', MINUTE_IN_SECONDS);
            return false;
        }

        // Fetch remote version
        $response = wp_remote_get('https://purethemes.net/license/listeo.json', array(
            'timeout' => 5,
            'sslverify' => true
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Cache failure for 1 hour to avoid hammering server
            set_transient($transient_key, array('error' => true), HOUR_IN_SECONDS);
            set_transient($result_cache_key, 'none', MINUTE_IN_SECONDS);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['latest_version'])) {
            set_transient($transient_key, array('error' => true), HOUR_IN_SECONDS);
            set_transient($result_cache_key, 'none', MINUTE_IN_SECONDS);
            return false;
        }

        // Cache only server data (not the comparison result)
        $server_data = array(
            'latest_version' => sanitize_text_field($data['latest_version']),
            'docs_link' => isset($data['docs_link']) ? esc_url_raw($data['docs_link']) : 'https://developer-developer.dev/changelog/'
        );

        // Cache for 7 days
        set_transient($transient_key, $server_data, 7 * DAY_IN_SECONDS);

        // Compare and return
        $update_available = version_compare($current_version, $server_data['latest_version'], '<');
        $result = $update_available ? array(
            'latest_version' => $server_data['latest_version'],
            'docs_link' => $server_data['docs_link'],
            'update_available' => true
        ) : false;

        // Cache result for 1 minute
        set_transient($result_cache_key, $result === false ? 'none' : $result, MINUTE_IN_SECONDS);
        return $result;
    }

    /**
     * Initialise settings
     * @return void
     */
    public function init_settings()
    {
        $this->settings = $this->settings_fields();
    }


    /**
     * Include admin files conditionally.
     */
    public function conditional_includes()
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        switch ($screen->id) {
            case 'options-permalink':
                include 'class-listeo-core-permalinks.php';
                break;
        }
    }


    /**
     * Add settings page to admin menu
     * @return void
     */
    public function add_menu_item()
    {
        $dashboard = isset( $GLOBALS['listeo_core_addons_dashboard'] ) && $GLOBALS['listeo_core_addons_dashboard'] instanceof Listeo_Core_Addons_Dashboard
            ? $GLOBALS['listeo_core_addons_dashboard']
            : null;
        $parent_slug = $dashboard ? Listeo_Core_Addons_Dashboard::PAGE_SLUG : $this->_token . '_settings';
        $page_callback = $dashboard ? array( $dashboard, 'render_page' ) : array($this, 'settings_page');

        $page = add_menu_page(__('Listeo Core', 'listeo_core'), __('Listeo Core', 'listeo_core'), 'manage_options', $parent_slug, $page_callback);

        if ( $dashboard ) {
            $dashboard->set_page_hook( $page );
        } else {
            add_action('admin_print_styles-' . $page, array($this, 'settings_assets'));
        }

        $settings_page = add_submenu_page($parent_slug, __('General Settings', 'listeo_core'), __('General', 'listeo_core'), 'manage_options', $this->_token . '_settings',  array($this, 'settings_page'));
        add_action('admin_print_styles-' . $settings_page, array($this, 'settings_assets'));

        // submit_listing
        // browse_listing
        // Registration
        // Booking
        // Pages
        // Emails
        add_submenu_page($parent_slug, __('Map Settings', 'listeo_core'), __('Map Settings', 'listeo_core'), 'manage_options', 'listeo_settings&tab=maps',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Submit Listing', 'listeo_core'), __('Submit Listing', 'listeo_core'), 'manage_options', 'listeo_settings&tab=submit_listing',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Packages Options', 'listeo_core'), __('Packages Options', 'listeo_core'), 'manage_options', 'listeo_settings&tab=listing_packages',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Single Listing', 'listeo_core'), __('Single Listing', 'listeo_core'), 'manage_options', 'listeo_settings&tab=single',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Booking Settings', 'listeo_core'), __('Booking Settings', 'listeo_core'), 'manage_options', 'listeo_settings&tab=booking',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Browse/Search Options', 'listeo_core'), __('Browse/Search Options', 'listeo_core'), 'manage_options', 'listeo_settings&tab=browse',  array($this, 'settings_page'));
        add_submenu_page($parent_slug, __('Ad Campaigns', 'listeo_core'), __('Ad Campaigns', 'listeo_core'), 'manage_options', 'listeo_settings&tab=ad_campaigns',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Registration', 'listeo_core'), __('Registration', 'listeo_core'), 'manage_options', 'listeo_settings&tab=registration',  array($this, 'settings_page'));
        
        add_submenu_page($parent_slug, __('Claim Listings', 'listeo_core'), __('Claim Listings', 'listeo_core'), 'manage_options', 'listeo_settings&tab=claims',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Pages', 'listeo_core'), __('Pages', 'listeo_core'), 'manage_options', 'listeo_settings&tab=pages',  array($this, 'settings_page'));

        add_submenu_page($parent_slug, __('Emails', 'listeo_core'), __('Emails', 'listeo_core'), 'manage_options', 'listeo_settings&tab=emails',  array($this, 'settings_page'));

        //add_submenu_page($this->_token . '_settings', 'PayPal Payout', 'PayPal Payout', 'manage_options', 'listeo_settings&tab=paypal_payout',  array( $this, 'settings_page' ) );
        add_submenu_page($parent_slug, __('Stripe Connect', 'listeo_core'), __('Stripe Connect', 'listeo_core'), 'manage_options', 'listeo_settings&tab=stripe_connect',  array($this, 'settings_page'));

        //add_submenu_page($this->_token . '_settings', __('Listeo Health Check', 'listeo_core'), __('Listeo Health Check', 'listeo_core'), 'manage_options', 'listeo_core_health_check', array($this, 'listeo_core_health_check'));
        //add_submenu_page('listeo_sms_settings', 'SMS Settings', 'SMS Settings', 'manage_options', 'listeo_settings&tab=sms',  array($this, 'settings_page'));
        
        // Add cache utilities submenu
        add_submenu_page($parent_slug, __('Cache Utils', 'listeo_core'), __('Cache Utils', 'listeo_core'), 'manage_options', 'listeo_cache_utils', array($this, 'cache_utils_page'));
        
        // Add rating migration submenu
        add_submenu_page($parent_slug, __('Rating Migration', 'listeo_core'), __('Rating Migration', 'listeo_core'), 'manage_options', 'listeo_rating_migration', array($this, 'rating_migration_page'));

    }

    /**
     * Add regions importer submenu to Listings menu
     * @return void
     */
    public function add_regions_importer_menu()
    {
        // Ensure is_plugin_active function is available
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        // Only add if no conflicts exist and user has proper permissions
        if (class_exists('Listeo_Core_Regions_Importer') && 
            !class_exists('Dynamic_Regions_Importer') && 
            (!function_exists('is_plugin_active') || !is_plugin_active('regions-importer/regions-import.php'))) {
            
            add_submenu_page(
                'edit.php?post_type=listing',    // Parent menu (Listings)
                __('Regions Importer', 'listeo_core'),  // Page title
                __('Regions Importer', 'listeo_core'),  // Menu title
                'manage_options',                 // Capability
                'listeo-regions-importer',       // Menu slug
                array($this, 'regions_importer_page') // Callback
            );
        }
    }

    /**
     * Render regions importer page
     * @return void
     */
    public function regions_importer_page()
    {
        if (class_exists('Listeo_Core_Regions_Importer')) {
            $importer = Listeo_Core_Regions_Importer::instance();
            $importer->render_import_page();
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Regions importer is not available.', 'listeo_core') . '</p></div></div>';
        }
    }

    /**
     * Add bulk categories submenu under Listings
     * @return void
     */
    public function add_bulk_categories_menu()
    {
        // Ensure is_plugin_active function is available
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        // Only add if no conflicts exist
        if (class_exists('Listeo_Core_Bulk_Categories') &&
            !class_exists('Listeo_Bulk_Categories') &&
            (!function_exists('is_plugin_active') || !is_plugin_active('listeo-bulk-categories/listeo-bulk-categories.php'))) {

            add_submenu_page(
                'edit.php?post_type=listing',
                __('Bulk Add Categories', 'listeo_core'),
                __('Bulk Add Categories', 'listeo_core'),
                'manage_options',
                'listeo-bulk-categories',
                array($this, 'bulk_categories_page')
            );
        }
    }

    /**
     * Render bulk categories page
     * @return void
     */
    public function bulk_categories_page()
    {
        if (class_exists('Listeo_Core_Bulk_Categories')) {
            $importer = Listeo_Core_Bulk_Categories::instance();
            $importer->render_import_page();
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Bulk categories importer is not available.', 'listeo_core') . '</p></div></div>';
        }
    }

    /**
     * Add translation importer submenu to WordPress Settings menu
     * @return void
     */
    public function add_translation_importer_menu()
    {
        // Ensure is_plugin_active function is available
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        // Only add if no conflicts exist and user has proper permissions
        if (class_exists('Listeo_Core_Translation_Importer') && 
            !class_exists('PT_Translation_Importer_Core') && 
            !class_exists('PT_Admin_Page') &&
            (!function_exists('is_plugin_active') || !is_plugin_active('translation-importer/translation-importer.php'))) {
            
            add_options_page(
                __('Translation Importer', 'listeo_core'),      // Page title
                __('Translation Importer', 'listeo_core'),      // Menu title
                'manage_options',                                // Capability
                'listeo-translation-importer',                  // Menu slug
                array($this, 'translation_importer_page')       // Callback
            );
        }
    }

    /**
     * Render translation importer page
     * @return void
     */
    public function translation_importer_page()
    {
        if (class_exists('Listeo_Core_Translation_Importer')) {
            $importer = Listeo_Core_Translation_Importer::instance();
            $importer->render_import_page();
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Translation importer is not available.', 'listeo_core') . '</p></div></div>';
        }
    }

    /**
     * Load settings JS & CSS
     * @return void
     */
    public function settings_assets()
    {

        // Color picker is now handled by the WordPress-bundled
        // `wp-color-picker` (enqueued for the whole admin via
        // class-listeo-core.php::admin_enqueue_scripts). Farbtastic is
        // no longer needed — it had a quirky `if (this.value && ...)`
        // guard in farbtastic.js:192 that refused to write back to an
        // empty input, making any color field with an empty default
        // permanently unsavable. wpColorPicker doesn't have that quirk
        // and supports a Clear button out of the box.

        // We're including the WP media scripts here because they're needed for the image upload field
        // If you're not including an image upload then you can leave this function call out
        wp_enqueue_media();

        // Enqueue modern admin CSS
        wp_enqueue_style(
            'listeo-modern-admin',
            plugins_url('listeo-core/assets/css/listeo-modern-admin.css'),
            array(),
            '1.0.0'
        );

        // Enqueue modern admin JavaScript
        wp_enqueue_script(
            'listeo-modern-admin-js',
            plugins_url('listeo-core/assets/js/listeo-modern-admin.js'),
            array('jquery'),
            '1.0.0',
            true
        );

        //wp_register_script( $this->_token . '-settings-js', $this->assets_url . 'js/settings' . $this->script_suffix . '.js', array( 'farbtastic', 'jquery' ), '1.0.0' );
        //wp_enqueue_script( $this->_token . '-settings-js' );

        // Add inline CSS for license reset buttons
    
    }


    /**
     * Build settings fields
     * @return array Fields to be displayed on settings page
     */
    private function settings_fields()
    {

        $settings['general'] = array(
            'title'                 => __('<i class="fa fa-sliders-h"></i> General', 'listeo_core'),
            'fields'                => array(

                // Date & Time Formatting Block
                array(
                    'label' =>  __('<i class="fa fa-clock"></i> Date & Time Formatting', 'listeo_core'),
                    'description' =>  __('Configure date, time, and calendar display formats', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_datetime_block'
                ),
                array(
                    'label'      => __('Clock format', 'listeo_core'),
                    'description'      => __('Set 12/24 clock for timepickers', 'listeo_core'),
                    'id'        => 'clock_format',
                    'type'      => 'radio',
                    'options'   => array(
                        '12' => '12H',
                        '24' => '24H'
                    ),
                    'default'   => '12'
                ),
                array(
                    'label'      => __('Date format separator', 'listeo_core'),
                    'description'      => __('Choose hyphen (-), slash (/), or dot (.)', 'listeo_core'),
                    'id'        => 'date_format_separator',
                    'type'      => 'text',
                    'default'   => '/'
                ),
                array(
                    'label'      => __('Add timezone for iCal files', 'listeo_core'),
                    'description'      => __('It requires timezone in WordPress Settings → General is set to city, not UTC', 'listeo_core'),
                    'id'        => 'ical_timezone',
                    'type'      => 'checkbox',
                ),
                array(
                    'id'            => 'calendar_view_lang',
                    'label'         => __('Set language for Calendar View', 'listeo_core'),
                    'description'   => __('This option will set in which language the calendar with bookings list will be loaded', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'en' => 'en',
                        'af' => 'af',
                        'ar-dz' => 'ar-dz',
                        'ar-kw' => 'ar-kw',
                        'ar-ly' => 'ar-ly',
                        'ar-ma' => 'ar-ma',
                        'ar-sa' => 'ar-sa',
                        'ar-tn' => 'ar-tn',
                        'ar' => 'ar',
                        'az' => 'az',
                        'bg' => 'bg',
                        'bn' => 'bn',
                        'bs' => 'bs',
                        'ca' => 'ca',
                        'cs' => 'cs',
                        'cy' => 'cy',
                        'da' => 'da',
                        'de-at' => 'de-at',
                        'de' => 'de',
                        'el' => 'el',
                        'en-au' => 'en-au',
                        'en-gb' => 'en-gb',
                        'en-nz' => 'en-nz',
                        'eo' => 'eo',
                        'es' => 'es',
                        'es-us' => 'es-us',
                        'eu' => 'eu',
                        'et' => 'et',
                        'fa' => 'fa',
                        'fi' => 'fi',
                        'fr' => 'fr',
                        'fr-ch' => 'fr-ch',
                        'fr-ca' => 'fr-ca',
                        'gl' => 'gl',
                        'he' => 'he',
                        'hi' => 'hi',
                        'hr' => 'hr',
                        'hu' => 'hu',
                        'hy-am' => 'hy-am',
                        'id' => 'id',
                        'is' => 'is',
                        'it' => 'it',
                        'ja' => 'ja',
                        'ka' => 'ka',
                        'kk' => 'kk',
                        'km' => 'km',
                        'ko' => 'ko',
                        'ku' => 'ku',
                        'lb' => 'lb',
                        'lt' => 'lt',
                        'lv' => 'lv',
                        'mk' => 'mk',
                        'ms' => 'ms',
                        'nb' => 'nb',
                        'ne' => 'ne',
                        'nl' => 'nl',
                        'nn' => 'nn',
                        'pl' => 'pl',
                        'pt-br' => 'pt-br',
                        'pt' => 'pt',
                        'ro' => 'ro',
                        'ru' => 'ru',
                        'si-lk' => 'si-lk',
                        'sk' => 'sk',
                        'sl' => 'sl',
                        'sm' => 'sm',
                        'sq' => 'sq',
                        'sr-cyrl' => 'sr-cyrl',
                        'sr' => 'sr',
                        'sv' => 'sv',
                        'ta-in' => 'ta-in',
                        'th' => 'th',
                        'tr' => 'tr',
                        'ug' => 'ug',
                        'uk' => 'uk',
                        'uz' => 'uz',
                        'vi' => 'vi',
                        'zh-cn' => 'zh-cn',
                        'zh-tw' => 'zh-tw',
                    ),
                    'default'       => 'en'
                ),

                // Currency & Pricing Block
                array(
                    'label' =>  __('<i class="fa fa-dollar-sign"></i> Currency & Pricing', 'listeo_core'),
                    'description' =>  __('Configure currency display, pricing formats, and commission settings', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_currency_block'
                ),

                array(
                    'label'      => __('Commission Type', 'listeo_core'),
                    'description'      => __('Choose whether commission is calculated as a percentage or fixed amount', 'listeo_core'),
                    'id'        => 'commission_type',
                    'type'      => 'select',
                    'options'   => array(
                        'percentage' => esc_html__('Percentage (%)', 'listeo_core'),
                        'fixed' => esc_html__('Fixed Amount', 'listeo_core'),
                    ),
                    'default'   => 'percentage'
                ),

                array(
                    'label'      => __('Commission Value', 'listeo_core'),
                    'description'      => __('Commission value (percentage or fixed amount depending on type selected above)', 'listeo_core'),
                    'id'        => 'commission_rate',
                    'type'      => 'number',
                    'placeholder'      => 'Put just a number',
                    'default'   => '10'
                ),

                array(
                    'label'      => __('Commission Calculation Method', 'listeo_core'),
                    'description'      => __('How commission should be applied to the booking price', 'listeo_core'),
                    'id'        => 'commission_calculation_method',
                    'type'      => 'radio',
                    'options'   => array(
                        'deduct' => sprintf(
                            '%s<br><span class="lc-option-note">%s</span>',
                            esc_html__('Deduct from booking total', 'listeo_core'),
                            esc_html__('customer pays booking price, owner receives booking price minus commission', 'listeo_core')
                        ),
                        'add' => sprintf(
                            '%s<br><span class="lc-option-note">%s</span>',
                            esc_html__('Add to booking total', 'listeo_core'),
                            esc_html__('customer pays booking price plus commission, owner receives full booking price', 'listeo_core')
                        ),
                    ),
                    'default'   => 'deduct'
                ),

                array(
                    'label'      => __('Show Commission Breakdown to Users', 'listeo_core'),
                    'description'      => __('When commission is added to booking total, show the commission/service fee breakdown to users for transparency', 'listeo_core'),
                    'id'        => 'show_commission_to_users',
                    'type'      => 'checkbox',
                    'default'   => 'on'
                ),

                array(
                    'label'      => __('Currency', 'listeo_core'),
                    'description'      => __('Choose a currency used.', 'listeo_core'),
                    'id'        => 'currency', //each field id must be unique
                    'type'      => 'select',
                    'options'   => array(
                        'none' => esc_html__('Disable Currency Symbol', 'listeo_core'),
                        'USD' => esc_html__('US Dollars', 'listeo_core'),
                        'AED' => esc_html__('United Arab Emirates Dirham', 'listeo_core'),
                        'ARS' => esc_html__('Argentine Peso', 'listeo_core'),
                        'AUD' => esc_html__('Australian Dollars', 'listeo_core'),
                        'BDT' => esc_html__('Bangladeshi Taka', 'listeo_core'),
                        'BHD' => esc_html__('Bahraini Dinar', 'listeo_core'),
                        'BRL' => esc_html__('Brazilian Real', 'listeo_core'),
                        'BGN' => esc_html__('Bulgarian Lev', 'listeo_core'),
                        'CAD' => esc_html__('Canadian Dollars', 'listeo_core'),
                        'CLP' => esc_html__('Chilean Peso', 'listeo_core'),
                        'CNY' => esc_html__('Chinese Yuan', 'listeo_core'),
                        'COP' => esc_html__('Colombian Peso', 'listeo_core'),
                        'CZK' => esc_html__('Czech Koruna', 'listeo_core'),
                        'DKK' => esc_html__('Danish Krone', 'listeo_core'),
                        'DOP' => esc_html__('Dominican Peso', 'listeo_core'),
                        'MAD' => esc_html__('Moroccan Dirham', 'listeo_core'),
                        'EUR' => esc_html__('Euros', 'listeo_core'),
                        'GHS' => esc_html__('Ghanaian Cedi', 'listeo_core'),
                        'HKD' => esc_html__('Hong Kong Dollar', 'listeo_core'),
                        'HRK' => esc_html__('Croatia kuna', 'listeo_core'),
                        'HUF' => esc_html__('Hungarian Forint', 'listeo_core'),
                        'ISK' => esc_html__('Icelandic krona', 'listeo_core'),
                        'IDR' => esc_html__('Indonesia Rupiah', 'listeo_core'),
                        'INR' => esc_html__('Indian Rupee', 'listeo_core'),
                        'NPR' => esc_html__('Nepali Rupee', 'listeo_core'),
                        'ILS' => esc_html__('Israeli Shekel', 'listeo_core'),
                        'JPY' => esc_html__('Japanese Yen', 'listeo_core'),
                        'JOD' => esc_html__('Jordanian Dinar', 'listeo_core'),
                        'KZT' => esc_html__('Kazakhstani tenge', 'listeo_core'),
                        'KIP' => esc_html__('Lao Kip', 'listeo_core'),
                        'KRW' => esc_html__('South Korean Won', 'listeo_core'),
                        'LKR' => esc_html__('Sri Lankan Rupee', 'listeo_core'),
                        'MYR' => esc_html__('Malaysian Ringgits', 'listeo_core'),
                        'MXN' => esc_html__('Mexican Peso', 'listeo_core'),
                        'NGN' => esc_html__('Nigerian Naira', 'listeo_core'),
                        'NOK' => esc_html__('Norwegian Krone', 'listeo_core'),
                        'NZD' => esc_html__('New Zealand Dollar', 'listeo_core'),
                        'PYG' => esc_html__('Paraguayan Guaraní', 'listeo_core'),
                        'PHP' => esc_html__('Philippine Pesos', 'listeo_core'),
                        'PLN' => esc_html__('Polish Zloty', 'listeo_core'),
                        'GBP' => esc_html__('Pounds Sterling', 'listeo_core'),
                        'RON' => esc_html__('Romanian Leu', 'listeo_core'),
                        'RUB' => esc_html__('Russian Ruble', 'listeo_core'),
                        'SGD' => esc_html__('Singapore Dollar', 'listeo_core'),
                        'SRD' => esc_html__('Suriname Dollar', 'listeo_core'),
                        'ZAR' => esc_html__('South African rand', 'listeo_core'),
                        'SEK' => esc_html__('Swedish Krona', 'listeo_core'),
                        'CHF' => esc_html__('Swiss Franc', 'listeo_core'),
                        'TWD' => esc_html__('Taiwan New Dollars', 'listeo_core'),
                        'THB' => esc_html__('Thai Baht', 'listeo_core'),
                        'TRY' => esc_html__('Turkish Lira', 'listeo_core'),
                        'UAH' => esc_html__('Ukrainian Hryvnia', 'listeo_core'),
                        'USD' => esc_html__('US Dollars', 'listeo_core'),
                        'VND' => esc_html__('Vietnamese Dong', 'listeo_core'),
                        'EGP' => esc_html__('Egyptian Pound', 'listeo_core'),
                        'ZMK' => esc_html__('Zambian Kwacha', 'listeo_core')
                    ),
                    'default'       => 'USD'
                ),
                array(
                    'label'      => __('Custom Currency', 'listeo_core'),
                    'description'      => __('Set your custom currency sybmbol if you do not see yours aboves', 'listeo_core'),
                    'id'        => 'currency_custom',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Currency position', 'listeo_core'),
                    'description'      => __('Set currency symbol before or after', 'listeo_core'),
                    'id'        => 'currency_postion',
                    'type'      => 'radio',
                    'options'   => array(
                        'after' => 'After',
                        'before' => 'Before'
                    ),
                    'default'   => 'after'
                ),
                array(
                    'label'      => __('Decimal places for prices', 'listeo_core'),
                    'description'      => __('Set Precision of the number of decimal places (for example 4.56$ instead of 5$)', 'listeo_core'),
                    'id'        => 'number_decimals',
                    'type'      => 'number',
                    'placeholder'      => 'Put just a number',
                    'default'   => '2'
                ),
                array(
                    'label'      => __('Area unit', 'listeo_core'),
                    'description'      => __('Set unit for area field', 'listeo_core'),
                    'id'        => 'scale',
                    'type'      => 'select',
                    'options'   => array(
                        'sq_ft' => 'Sq Ft',
                        'sq_m' => 'Sq M',
                        'sq_km' => 'Sq Km',
                        'sq_yd' => 'Sq Yd',
                        'sq_mi' => 'Sq Mi',
                        'ha' => 'Ha',
                        'ac' => 'Ac'
                    ),
                    'default'   => 'sq_ft'
                ),

                // URL & Permalink Settings Block
                array(
                    'label' =>  __('<i class="fa fa-link"></i> URL & Permalink Settings', 'listeo_core'),
                    'description' =>  __('Configure URL structures and permalink formats for listings', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_url_block'
                ),
                array(
                    'label'      => __('Region in listing permalinks', 'listeo_core'),
                    'description'      => __('By enabling this option the links to properties will <br> be prepended  with regions (e.g /listing/las-vegas/arlo-apartment/).<br> After enabling this go to Settings → Permalinks and click \' Save Changes \' ', 'listeo_core'),
                    'id'        => 'region_in_links',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Combined region and feature URLs', 'listeo_core'),
                    'description'      => __('Enables URLs like <mark>/region/feature/</mark> to show listings filtered by both region and feature.<br>Examples: <mark>/huntsville/alcohol/</mark>, <mark>/new-york/parking/</mark> <br>After enabling this go to Settings → Permalinks and click \' Save Changes \' ', 'listeo_core'),
                    'id'        => 'combined_taxonomy_urls',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Available Combined URLs Preview', 'listeo_core'),
                    'description'      => __('Preview of available combined taxonomy URLs on your site', 'listeo_core'),
                    'id'        => 'combined_taxonomy_preview',
                    'type'      => 'combined_urls_preview',
                ),

                // User Privacy & Contact Block
                array(
                    'label' =>  __('<i class="fa fa-user-shield"></i> User Privacy & Contact', 'listeo_core'),
                    'description' =>  __('Configure privacy and contact information visibility settings', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_privacy_block'
                ),
                array(
                    'label'      => __('Owner contact information visibility', 'listeo_core'),
                    'description'      => __('By enabling this option phone and emails fields will be visible only for:', 'listeo_core'),
                    'id'        => 'user_contact_details_visibility',
                    'type'      => 'select',
                    'options'   => array(
                        'show_logged' => esc_html__('Show owner contact information only for logged in users', 'listeo_core'),
                        'hide_all' => esc_html__('Hide all owner contact information', 'listeo_core'),
                        'show_all' => esc_html__('Always show', 'listeo_core'),
                    ),
                    'default'   => 'hide_logged'
                ),
                array(
                    'label'      => __('Chat filter', 'listeo_core'),
                    'description'      => __('Automatically blocks users from sharing phone numbers, emails, and contact info in messages to keep conversations on the platform.', 'listeo_core'),
                    'id'        => 'chat_filter',
                    'type'      => 'checkbox',
                    'default'   => 'on',
                ),
                array(
                    'label'      => __('Message attachments', 'listeo_core'),
                    'description'      => __('Allow users to attach files (images, documents) to dashboard messages. Files are stored securely and can only be accessed by conversation participants.', 'listeo_core'),
                    'id'        => 'message_attachments',
                    'type'      => 'checkbox',
                    'default'   => 'on',
                ),

                // Listing Management Block
                array(
                    'label' =>  __('<i class="fa fa-list-alt"></i> Listing Management', 'listeo_core'),
                    'description' =>  __('Configure automatic listing management and expiration settings', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_listing_block'
                ),
                array(
                    'label'      => __('Expire listing after event date', 'listeo_core'),
                    'description'      => __('By enabling this option the listing will be automatically expired after the event date', 'listeo_core'),
                    'id'        => 'expire_after_event',
                    'type'      => 'checkbox',
                ),

                // Schema / structured data output. The toggle below is read by
                // Listeo_Core_Post_Types::add_local_business_schema() (get_option
                // 'listeo_disable_schema'), which returns early when enabled.
                array(
                    'label'       => __('<i class="fa fa-code"></i> Schema (Structured Data)', 'listeo_core'),
                    'description' => __('Control Listeo\'s automatically generated JSON-LD schema', 'listeo_core'),
                    'type'        => 'title',
                    'id'          => 'general_schema_block',
                ),
                array(
                    'label'       => __('Disable Listeo auto-generated schema', 'listeo_core'),
                    'description' => __('Stops Listeo from outputting its LocalBusiness/Product JSON-LD schema in the page head. Enable this if you provide your own schema (e.g. via an SEO plugin or custom JSON-LD).', 'listeo_core'),
                    'id'          => 'disable_schema',
                    'type'        => 'checkbox',
                ),

                // Author Page Block — surface what users have published
                // on their public profile page. The author template
                // (`author.php`) reads these toggles to decide which
                // sections to render and how many items to include.
                array(
                    'label'       => __('<i class="fa fa-user-edit"></i> Author Page', 'listeo_core'),
                    'description' => __('Show what users have published on their public author profile page', 'listeo_core'),
                    'type'        => 'title',
                    'id'          => 'general_author_block',
                ),
                array(
                    'label'       => __('Show author\'s listings', 'listeo_core'),
                    'description' => __('Show the "Listings" section on each user\'s author profile page. Disable when you only want to highlight other content (e.g. blog posts). On by default.', 'listeo_core'),
                    'id'          => 'author_show_listings',
                    'type'        => 'checkbox',
                    'default'     => 'on',
                ),
                array(
                    'label'       => __('Show author\'s blog posts', 'listeo_core'),
                    'description' => __('Adds a "Posts" section under the listings on each user\'s author profile page, listing their published blog posts (post type: post).', 'listeo_core'),
                    'id'          => 'author_show_posts',
                    'type'        => 'checkbox',
                ),
                array(
                    'label'       => __('Posts per page', 'listeo_core'),
                    'description' => __('How many of the author\'s blog posts to show. Leave blank for 6.', 'listeo_core'),
                    'id'          => 'author_posts_per_page',
                    'type'        => 'number',
                    'min'         => 1,
                    'max'         => 30,
                    'step'        => 1,
                    'default'     => 6,
                ),
                array(
                    'label' =>  __('<i class="fa fa-money-bill-wave"></i> Payout options', 'listeo_core'),
                    'description' =>  __('Configure payout settings for your platform', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_payouts_listeo'
                ),

                array(
                    'id'            => 'payout_options',
                    'label'         => __('Payouts Options', 'listeo_core'),
                    'description'   => __('Set which payouts method you want to have available on Wallet page (Stripe is configured in Stripe Connect tab)', 'listeo_core'),
                    'type'          => 'checkbox_multi',
                    'options'       => array(

                        'paypal' => esc_html__('PayPal (if PayPal Payouts is active it replaces that option)', 'listeo_core'),
                        'bank' => esc_html__('Bank Transfer', 'listeo_core'),

                    ), //service

                    'default'       => array('paypal', 'bank')
                ),


                array(
                    'label' =>  __('<i class="fa fa-history"></i> Backward compatibility options', 'listeo_core'),
                    'description' =>  __('Settings for compatibility with older versions', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_backward_liste'
                ),
                array(
                    'label'      => __('Preferred Page Builder', 'listeo_core'),
                    'description'      => __('Since version 1.5 we have added Elementor support and we recommend it as the best Page Builder for Listeo', 'listeo_core'),
                    'id'        => 'page_builder',
                    'type'      => 'select',
                    'options'   => array(

                        'elementor' => esc_html__('Elementor', 'listeo_core'),
                        'js_composer' => esc_html__('WPBakery Page Builder', 'listeo_core'),

                    ),
                    'default' => 'elementor'
                ),
                array(
                    'label'      => __('Enable Iconsmind', 'listeo_core'),
                    'description'      => __('Iconsmind is heavy icon pack that was used in Listeo versions before 1.5, if you still want to use those icons please enable it here, ', 'listeo_core'),
                    'id'        => 'iconsmind',
                    'type'      => 'select',
                    'options'   => array(

                        'use' => esc_html__('Use iconsmind', 'listeo_core'),
                        'hide' => esc_html__('Hide', 'listeo_core'),

                    ),
                    'default' => 'hide'
                ),

                // Region Permalink Settings
                array(
                    'label' =>  __('<i class="fa fa-link"></i> Region Permalink Settings', 'listeo_core'),
                    'description' =>  __('Configure how region taxonomy URLs are structured', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'general_region_permalink_block'
                ),
                array(
                    'label'      => __('Enable hierarchical region permalinks', 'listeo_core'),
                    'description'      => __('Allow nested parent/child regions in permalinks (e.g., yoursite.com/region/usa/california/). <strong style="color: #d63638;">Important: After changing this setting, you MUST go to Settings → Permalinks and click "Save Changes" to regenerate rewrite rules.</strong>', 'listeo_core'),
                    'id'        => 'region_hierarchical_permalinks',
                    'type'      => 'checkbox',
                ),

            )
        );

        $settings['maps'] = array(
            'title'                 => __('<i class="fa fa-map-marked-alt"></i> Map Settings', 'listeo_core'),
            'fields'                => array(

                // Search Restrictions & Behavior Block
                array(
                    'label' =>  __('<i class="fa fa-search-location"></i> Search Restrictions & Behavior', 'listeo_core'),
                    'description' =>  __('Configure map search behavior and geographical restrictions', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'maps_search_block'
                ),
                array(
                    'label' => __('Restrict search results to one country (works only with Google Maps)', 'listeo_core'),
                    'description' => __('Put symbol of country you want to restrict your results to (<mark>eg. uk for United Kingdon)</mark>. Leave empty to search whole world.', 'listeo_core'),
                    'id'   => 'maps_limit_country',
                    'type' => 'text',
                ),
                array(
                    'label' => __('Enable Map Bounds Search', 'listeo_core'),
                    'description' => __('Search listings within current map view when dragging/zooming the map', 'listeo_core'),
                    'id'   => 'map_bounds_search',
                    'type' => 'checkbox',
                    'default' => 'on',
                ),
                array(
                    'label'         => __('Automatically locate users on page load', 'listeo_core'),
                    'description'   => sprintf(
                        __('You need to be on HTTPS, this uses html5 geolocation feature. %s', 'listeo_core'),
                        '<a href="https://www.w3schools.com/html/html5_geolocation.asp" target="_blank">' . __('Learn more', 'listeo_core') . '</a>'
                    ),
                    'id'            => 'map_autolocate',
                    'type'          => 'checkbox',
                    'default'          => 'off',
                ),

                // Map Configuration Block
                array(
                    'label' =>  __('<i class="fa fa-map"></i> Map Configuration', 'listeo_core'),
                    'description' =>  __('Configure map display settings, center points, and zoom levels', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'maps_config_block'
                ),
                array(
                    'label' => __('Listings map center point', 'listeo_core'),
                    'description' => __('Write latitude and longitude separated by comma, for example -34.397,150.644', 'listeo_core'),
                    'id'   => 'map_center_point',
                    'type' => 'text',
                    'default' => "29.577712,-45.629483",
                    'callback' => array($this, 'validate_map_center_point'),
                ),
                array(
                    'label'         => __('Autofit all markers on map', 'listeo_core'),
                    'description'   => __('Disable checkbox to set the zoom of map manually', 'listeo_core'),
                    'id'            => 'map_autofit',
                    'type'          => 'checkbox',
                    'default'          => 'on',
                ),
                array(
                    'label'         => __('Zoom level for Listings Map', 'listeo_core'),
                    'description'   => __('Put number between 0-20, works only with autofit disabled', 'listeo_core'),
                    'id'            => 'map_zoom_global',
                    'type'          => 'text',
                    'default'       => 9
                ),
                array(
                    'label'         => __('Zoom level for Single Listing Map', 'listeo_core'),
                    'description'   => __('Put number between 0-20', 'listeo_core'),
                    'id'            => 'map_zoom_single',
                    'type'          => 'text',
                    'default'       => 9
                ),

                // Map Provider & Services Block
                array(
                    'label' =>  __('<i class="fa fa-layer-group"></i> Map Provider & Services', 'listeo_core'),
                    'description' =>  __('Select map providers and address suggestion services', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'maps_provider_block'
                ),
                array(
                    'label'      => __('Maps Provider', 'listeo_core'),
                    'description'      => __('Choose which service you want to use for maps', 'listeo_core'),
                    'id'        => 'map_provider',
                    'type'      => 'radio',
                    'options'   => array(
                        'osm' => esc_html__('OpenStreetMap', 'listeo_core'),
                        'google' => sprintf(
                            '%s <a href="%s" target="_blank">%s</a>',
                            esc_html__('Google Maps', 'listeo_core'),
                            'http://www.docs.purethemes.net/listeo/knowledge-base/getting-google-maps-api-key/',
                            esc_html__('(requires API key)', 'listeo_core')
                        ),
                        'mapbox' => sprintf(
                            '%s <a href="%s" target="_blank">%s</a>',
                            esc_html__('MapBox', 'listeo_core'),
                            'https://account.mapbox.com/access-tokens/create',
                            esc_html__('(requires API key)', 'listeo_core')
                        ),
                        'none' => esc_html__('None - this will dequeue all map related scripts', 'listeo_core'),
                    ),
                    'default'   => 'osm'
                ),
                array(
                    'label'      => __('Address suggestion provider', 'listeo_core'),
                    'description'      => __('Choose which service you want to use for adress autocomplete', 'listeo_core'),
                    'id'        => 'map_address_provider',
                    'type'      => 'radio',
                    'options'   => array(
                        'osm' => esc_html__('OpenStreetMap', 'listeo_core'),
                        'google' => sprintf(
                            '%s <a href="%s" target="_blank">%s</a>',
                            esc_html__('Google Maps', 'listeo_core'),
                            'http://www.docs.purethemes.net/listeo/knowledge-base/getting-google-maps-api-key/',
                            esc_html__('(requires API key and Maps Provider set to Google Maps)', 'listeo_core')
                        ),
                        'off' => esc_html__('Disable address suggestion', 'listeo_core'),
                    ),
                    'default'   => 'osm'
                ),

                // API Keys & Credentials Block
                array(
                    'label' =>  __('<i class="fa fa-key"></i> API Keys & Credentials', 'listeo_core'),
                    'description' =>  __('Configure API keys and access tokens for map services', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'maps_api_block'
                ),
                array(
                    'label' => __('Google Maps API key', 'listeo_core'),
                    'description' => __('Generate API key for google maps functionality (can be domain restricted).', 'listeo_core'),
                    'id'   => 'maps_api',
                    'type' => 'text',
                    'placeholder'   => __('Google Maps API key', 'listeo_core')
                ),
                array(
                    'label' => __('MapBox Access Token', 'listeo_core'),
                    'description' => __('Generate Access Token for MapBox', 'listeo_core'),
                    'id'   => 'mapbox_access_token',
                    'type' => 'text',
                    'placeholder'   => __('MapBox Access Token key', 'listeo_core')
                ),
                array(
                    'label' => __('MapBox Studio Style URL', 'listeo_core'),
                    'description' => __('Paste style link generated in Studio MapBox.  ', 'listeo_core') . '<br><a href="https://www.docs.purethemes.net/listeo/knowledge-base/how-to-use-mapbox-custom-map-styles/">How to use MapBox custom map styles</a>',
                    'id'   => 'mapbox_style_url',
                    'type' => 'text',
                    'placeholder'   => __('MapBox Style URL', 'listeo_core')
                ),
                array(
                    'label' => __('MapBox Retina Tiles', 'listeo_core'),
                    'description' => __('Enable to use Retina Tiles. Might affect map loading speed.', 'listeo_core'),
                    'id'   => 'mapbox_retina',
                    'type' => 'checkbox',
                ),
                array(
                    'id'             => 'google_maps_id',
                    'label'            => __('Google Map ID', 'listeo_core'),
                    'description'    => __('Optional: Enter your Google Cloud Styled Map ID for custom map styling. Create one in Google Cloud Console > Maps > Map Styles.', 'listeo_core'),
                    'type'            => 'text',
                    'default'        => '',
                    'placeholder'    => __('Map ID (e.g., a1b2c3d4e5f6g7h8)', 'listeo_core')
                ),
                // Server-side Geocoding & Radius Search Block
                array(
                    'label' =>  __('<i class="fa fa-globe"></i> Server-side Geocoding & Radius Search', 'listeo_core'),
                    'description' =>  __('Configure geocoding services and radius search functionality', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'maps_geocoding_block'
                ),
                array(
                    'label'      => __('Server side geocoding provider', 'listeo_core'),
                    'description'      => __('Choose service provider', 'listeo_core'),
                    'id'        => 'geocoding_provider',
                    'type'      => 'select',
                    'options'   => array(
                        'google' => esc_html__('Google Maps', 'listeo_core'),
                        'geoapify' => esc_html__('Geoapify', 'listeo_core'),
                    ),
                    'default'   => 'google'
                ),
                array(
                    'label' => __('Google Maps API key for server side geocoding', 'listeo_core'),
                    'description' => __('Generate API key for geocoding search functionality (without any domain/key restriction).', 'listeo_core'),
                    'id'   => 'maps_api_server',
                    'type' => 'text',
                    'placeholder'   => __('Google Maps API key', 'listeo_core')
                ),
                array(
                    'label' => __('Geoapify API key for server side geocoding', 'listeo_core'),
                    'description' => __('Generate Geoapify API key for geocoding search functionality.', 'listeo_core'),
                    'id'   => 'geoapify_maps_api_server',
                    'type' => 'text',
                    'placeholder'   => __('Geoapify API key', 'listeo_core')
                ),
                array(
                    'label'      => __('Radius slider default state', 'listeo_core'),
                    'description'      => __('Choose radius search slider', 'listeo_core'),
                    'id'        => 'radius_state',
                    'type'      => 'select',
                    'options'   => array(
                        'disabled' => esc_html__('Disabled by default', 'listeo_core'),
                        'enabled' => esc_html__('Enabled by default', 'listeo_core'),
                    ),
                    'default'   => 'km'
                ),
                array(
                    'label'      => __('Radius search unit', 'listeo_core'),
                    'description'      => __('Choose a unit', 'listeo_core'),
                    'id'        => 'radius_unit',
                    'type'      => 'select',
                    'options'   => array(
                        'km' => esc_html__('km', 'listeo_core'),
                        'miles' => esc_html__('miles', 'listeo_core'),
                    ),
                    'default'   => 'km'
                ),
                array(
                    'label' => __('Default radius search value', 'listeo_core'),
                    'description' => __('Set default radius for search, leave empty to disable default radius search.', 'listeo_core'),
                    'id'   => 'maps_default_radius',
                    'type' => 'text',
                    'default'   => 50
                ),

                // Location Search Method Block
                array(
                    'label' =>  __('<i class="fa fa-map-marked-alt"></i> Location Search Method', 'listeo_core'),
                    'description' =>  __('Choose how location-based searches are performed. Viewport uses rectangular area from Google Places (best for large areas). Radius uses circular distance (current behavior). Auto intelligently switches based on location type.', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'location_search_method_block'
                ),
                array(
                    'id'          => 'location_search_mode',
                    'label'       => __('Location Search Method', 'listeo_core'),
                    'description' => __('Viewport: rectangular area from Google Places (recommended for countries/regions). Radius: circular area from center point (current behavior). Auto: intelligent switching based on location type.', 'listeo_core'),
                    'type'        => 'radio',
                    'options'     => array(
                        'radius'   => __('Radius-based (current)', 'listeo_core'),
                        'viewport' => __('Viewport-based (recommended for large areas)', 'listeo_core'),
                        'auto'     => __('Auto (intelligent switching)', 'listeo_core'),
                    ),
                    'default'     => 'radius'
                ),
                array(
                    'id'          => 'viewport_fallback_radius',
                    'label'       => __('Viewport Fallback Radius', 'listeo_core'),
                    'description' => __('When viewport unavailable, use this radius (uses unit from "Radius search unit" setting above)', 'listeo_core'),
                    'type'        => 'number',
                    'default'     => '50',
                    'placeholder' => '50'
                ),
                array(
                    'id'          => 'listeo_auto_mode_threshold',
                    'label'       => __('Auto Mode Area Threshold (km²)', 'listeo_core'),
                    'description' => __('In Auto mode, if viewport area exceeds this value (in square kilometers), use viewport search. Otherwise use radius search. Default 50,000 km² (roughly size of Costa Rica).', 'listeo_core'),
                    'type'        => 'number',
                    'default'     => '50000',
                    'placeholder' => '50000'
                ),

            )
        );

        $settings['submit_listing'] = array(
            'title'                 => __('<i class="fa fa-plus-square"></i> Submit Listing', 'listeo_core'),
            'fields'                => array(

                // Listing Types & Configuration Block
                // array(
                //     'label' =>  __('<i class="fa fa-list-alt"></i> Listing Types & Configuration', 'listeo_core'),
                //     'description' =>  __('Configure supported listing types and their visual icons', 'listeo_core'),
                //     'type' => 'title',
                //     'id'   => 'listing_types_block'
                // ),
                // array(
                //     'id'            => 'listing_types',
                //     'label'         => __('Supported listing types', 'listeo_core'),
                //     'description'   => __('If you select onae it will be the default type and Choose Listing Type step in Submit Listing form will be skipped. If you deselect all the default type will always be Service', 'listeo_core'),
                //     'type'          => 'checkbox_multi',
                //     'options'       => array(
                //         'service' => esc_html__('Service', 'listeo_core'),
                //         'rental' => esc_html__('Rental', 'listeo_core'),
                //         'event' => esc_html__('Event', 'listeo_core'),
                //         'classifieds' => esc_html__('Classifieds', 'listeo_core')
                //     ),
                //     'default'       => array('service', 'rental', 'event')
                // ),
                // array(
                //     'id'            => 'service_type_icon',
                //     'label'         => __('Service Type Icon', 'listeo_core'),
                //     'description'   => __('Set icon for service listing type selection on Submit Listing page.', 'listeo_core'),
                //     'type'          => 'image',
                //     'default'       => '',
                //     'placeholder'   => ''
                // ),
                // array(
                //     'id'            => 'rental_type_icon',
                //     'label'         => __('Rental Type Icon', 'listeo_core'),
                //     'description'   => __('Set icon for rental listing type selection on Submit Listing page.', 'listeo_core'),
                //     'type'          => 'image',
                //     'default'       => '',
                //     'placeholder'   => ''
                // ),
                // array(
                //     'id'            => 'event_type_icon',
                //     'label'         => __('Event Type Icon', 'listeo_core'),
                //     'description'   => __('Set icon for event listing type selection on Submit Listing page.', 'listeo_core'),
                //     'type'          => 'image',
                //     'default'       => '',
                //     'placeholder'   => ''
                // ),
                // array(
                //     'id'            => 'classifieds_type_icon',
                //     'label'         => __('Classifieds Type Icon', 'listeo_core'),
                //     'description'   => __('Set icon for classifieds listing type selection on Submit Listing page.', 'listeo_core'),
                //     'type'          => 'image',
                //     'default'       => '',
                //     'placeholder'   => ''
                // ),

                // Form Features & Modules Block
                array(
                    'label' =>  __('<i class="fa fa-cogs"></i> Form Features & Modules', 'listeo_core'),
                    'description' =>  __('Enable or disable specific features and modules in the submit form', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'form_features_block'
                ),
                array(
                    'label'      => __('Disable Bookings module', 'listeo_core'),
                    'description'      => __('By default bookings are enabled, check this checkbox to disable it and remove booking options from Submit Listing', 'listeo_core'),
                    'id'        => 'bookings_disabled',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Disable Submit form modules', 'listeo_core'),
                    'description'      => __('Select specific modules to disable in the submit listing form', 'listeo_core'),
                    'id'        => 'submit_form_modules_disabled',
                    'type'      => 'checkbox_multi',
                    'options'   => array(
                        'faq' => esc_html__('FAQ section', 'listeo_core'),
                        'other_listings' => esc_html__('My Other Listings section', 'listeo_core')
                    )
                ),

                // Content Approval & Notifications Block
                array(
                    'label' =>  __('<i class="fa fa-check-circle"></i> Content Approval & Notifications', 'listeo_core'),
                    'description' =>  __('Configure approval requirements and notification settings', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'approval_notifications_block'
                ),
                array(
                    'label'      => __('Admin approval required for new listings', 'listeo_core'),
                    'description'      => __('Require admin approval for any new listings added', 'listeo_core'),
                    'id'        => 'new_listing_requires_approval',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Admin approval required for editing listing', 'listeo_core'),
                    'description'      => __('Require admin approval for any edited listings', 'listeo_core'),
                    'id'        => 'edit_listing_requires_approval',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Notify admin by email about new listing waiting for approval', 'listeo_core'),
                    'description'      => __('Send email about any new listings added', 'listeo_core'),
                    'id'        => 'new_listing_admin_notification',
                    'type'      => 'checkbox',
                ),

                // Listing Limits & Media Settings Block
                array(
                    'label' =>  __('<i class="fa fa-upload"></i> Listing Limits & Media Settings', 'listeo_core'),
                    'description' =>  __('Configure listing duration, image upload limits and file size restrictions', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'limits_media_block'
                ),
                array(
                    'label' => __('Listing duration', 'listeo_core'),
                    'description' => __('Set default listing duration (if not set via listing package). Set to 0 if you don\'t want listings to have an expiration date.', 'listeo_core'),
                    'id'   => 'default_duration',
                    'type' => 'text',
                    'default' => '30',
                ),
                array(
                    'label' => __('Listing images upload limit', 'listeo_core'),
                    'description' => __('Number of images that can be uploaded to one listing', 'listeo_core'),
                    'id'   => 'max_files',
                    'type' => 'text',
                    'default' => '10',
                ),
                array(
                    'label' => __('Listing image maximum size (in MB)', 'listeo_core'),
                    'description' => __('Maximum file size to upload', 'listeo_core'),
                    'id'   => 'max_filesize',
                    'type' => 'text',
                    'default' => '2',
                ),

                // Map Configuration Block
                array(
                    'label' =>  __('<i class="fa fa-map-marker"></i> Map Configuration', 'listeo_core'),
                    'description' =>  __('Configure map settings for the submit listing form', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'submit_map_block'
                ),
                array(
                    'label' => __('Submit Listing map center point', 'listeo_core'),
                    'description' => __('Write latitude and longitude separated by comma, for example -34.397,150.644', 'listeo_core'),
                    'id'   => 'submit_center_point',
                    'type' => 'text',
                    'default' => "52.2296756,21.012228700000037",
                ),

            )
        );

        // Build listing packages fields array
        $listing_packages_fields = array(

            // Payment & Purchase Settings Block
                array(
                    'label' =>  __('<i class="fa fa-credit-card"></i> Payment & Purchase Settings', 'listeo_core'),
                    'description' =>  __('Configure payment requirements and package purchase behavior', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'payment_purchase_block'
                ),
                array(
                    'label'      => __('Paid listings', 'listeo_core'),
                    'description'      => __('Adding listings by users will require purchasing a Listing Package', 'listeo_core'),
                    'id'        => 'new_listing_requires_purchase',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'         => __('Allow packages to only be purchased once per client', 'listeo_core'),
                    'description'   => __('Selected packages can be bought only once, useful for demo/free packages', 'listeo_core'),
                    'id'            => 'buy_only_once',
                    'type'          => 'checkbox_multi',
                    'options'       => listeo_core_get_listing_packages_as_options(),
                    'default'       => array()
                ),
                array(
                    'label'         => __('Skip package selection if user already has a package', 'listeo_core'),
                    'description'   => __('If user already has any active package the choose package step will be skipped and the package he has will be selected automatically', 'listeo_core'),
                    'id'            => 'skip_package_if_user_has_one',
                    'type'          => 'checkbox',
                ),
                array(
                    'label'       => __('Show purchasable same-type packages when user already owns that package', 'listeo_core'),
                    'description' => __('When enabled, users will see purchasable packages of the same product/type they already own.', 'listeo_core'),
                    'id'          => 'show_same_type_purchasables',
                    'type'        => 'checkbox',
                    'default'     => '',
                ),

                // Package Feature Restrictions Block
                array(
                    'label' =>  __('<i class="fa fa-lock"></i> Package Feature Restrictions', 'listeo_core'),
                    'description' =>  __('Control which modules are only available through packages', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'package_restrictions_block'
                ),
                array(
                    'id'            => 'listing_packages_options',
                    'label'         => __('Check module to disable it in Submit Listing form if you want to make them available only in packages', 'listeo_core'),
                    'description'   => __('Modules checked here will be disabled in the free submit form and only available through paid packages', 'listeo_core'),
                    'type'          => 'checkbox_multi',
                    'options'       => array(
                        'option_booking' => esc_html__('Booking Module', 'listeo_core'),
                        'option_reviews' => esc_html__('Reviews Module', 'listeo_core'),
                        'option_gallery' => esc_html__('Gallery Module', 'listeo_core'),
                        'option_pricing_menu' => esc_html__('Pricing Menu Module', 'listeo_core'),
                        'option_social_links' => esc_html__('Social Links Module', 'listeo_core'),
                        'option_opening_hours' => esc_html__('Opening Hours Module', 'listeo_core'),
                        'option_video' => esc_html__('Video Module', 'listeo_core'),
                        'option_coupons' => esc_html__('Coupons Module', 'listeo_core'),
                        'option_faq' => esc_html__('FAQ Module', 'listeo_core'),
                    ),
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'package_restrictions_block'
                ),

                // Package Display Settings Block
                array(
                    'label' =>  __('<i class="fa fa-table"></i> Package Display Settings', 'listeo_core'),
                    'description' =>  __('Configure how package options are displayed in pricing tables', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'package_display_block'
                ),
                array(
                    'label'      => __('Show extra package options automatically in pricing table', 'listeo_core'),
                    'description'      => __('Automatically display additional package features in the pricing comparison table', 'listeo_core'),
                    'id'        => 'populate_listing_package_options',
                    'type'      => 'checkbox',
                ),
        );

        // Add Dokan Store Restrictions section if Dokan is active
        if ( function_exists('dokan_get_sellers') ) {
            // Insert Dokan section before Package Display Settings (before last 2 elements: title + checkbox)
            $package_display_section = array_splice($listing_packages_fields, -2);

            $listing_packages_fields[] = array(
                'label' => __('<i class="fa fa-store"></i> Dokan Store Restrictions', 'listeo_core'),
                'description' => __('Control Dokan marketplace vendor store access through packages', 'listeo_core'),
                'type' => 'title',
                'id'   => 'dokan_restrictions_block'
            );
            $listing_packages_fields[] = array(
                'id' => 'dokan_store_restriction',
                'label' => __('Restrict Dokan Store to Packages Only', 'listeo_core'),
                'description' => __('When enabled, users must have an active package with Dokan store access to create and manage vendor stores. When disabled, all users can access Dokan stores for free.', 'listeo_core'),
                'type' => 'checkbox',
                'default' => '',
            );
            $listing_packages_fields[] = array(
                'id' => 'dokan_apply_to_existing',
                'label' => __('Apply restrictions to existing Dokan vendors', 'listeo_core'),
                'description' => __('When enabled, existing Dokan vendors without proper packages will lose access until they purchase a package. When disabled, existing vendors keep access (grandfathered).', 'listeo_core'),
                'type' => 'checkbox',
                'default' => '',
            );
            $listing_packages_fields[] = array(
                'id' => 'dokan_auto_assign_seller_role',
                'label' => __('Auto-assign Seller role on package purchase', 'listeo_core'),
                'description' => __('When enabled, users who purchase a package with Dokan store access will automatically be assigned the "Seller" role, making them a Dokan vendor.', 'listeo_core'),
                'type' => 'checkbox',
                'default' => '',
            );

            // Add back the Package Display Settings section
            $listing_packages_fields = array_merge($listing_packages_fields, $package_display_section);
        }

        // Now assign to settings array
        $settings['listing_packages'] = array(
            'title'   => __('<i class="fa fa-cubes"></i> Packages Options', 'listeo_core'),
            'fields'  => $listing_packages_fields
        );


        //        woocommerce_wp_checkbox( array(
        //            'id' => '_package_option_social_links',
        //            'label' => __( 'Social Links Module', 'listeo_core' ),
        //            'description' => __( 'Allow social links to be displayed on the listings bought from this package.', 'listeo_core' ),
        //            'value' => get_post_meta(  $post->ID, '_package_option_social_links', true ),
        //        ) );

        //        woocommerce_wp_checkbox( array(
        //            'id' => '_package_option_opening_hours',
        //            'label' => __( 'Opening Hours Module', 'listeo_core' ),
        //            'description' => __( 'Allow Opening Hours widget to be displayed on the listings bought from this package.', 'listeo_core' ),
        //            'value' => get_post_meta(  $post->ID, '_package_option_opening_hours', true ),
        //        ) );

        //        woocommerce_wp_checkbox( array(
        //            'id' => '_package_option_video',
        //            'label' => __( 'Video Module', 'listeo_core' ),
        //            'description' => __( 'Allow Video widget to be displayed on the listings bought from this package.', 'listeo_core' ),
        //            'value' => get_post_meta(  $post->ID, '_package_option_video', true ),
        //        ) );        
        //        woocommerce_wp_checkbox( array(
        //            'id' => '_package_option_coupons',
        //            'label' => __( 'Coupons Module', 'listeo_core' ),
        //            'description' => __( 'Allow Coupons widget to be displayed on the listings bought from this package.', 'listeo_core' ),
        //            'value' => get_post_meta(  $post->ID, '_package_option_coupons', true ),
        //        ) );    

        $settings['single'] = array(
            'title'                 => __('<i class="fa fa-file"></i> Single Listing', 'listeo_core'),
            'fields'                => array(

                // Listing Display & Security Block
                array(
                    'label' =>  __('<i class="fa fa-eye"></i> Listing Display & Security', 'listeo_core'),
                    'description' =>  __('Configure listing visibility, reporting and security features', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'display_security_block'
                ),
                array(
                    'id'            => 'report_listing',
                    'label'         => __('Enable Flag/Report Listing', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'disable_address',
                    'label'         => __('Hide real address on listings and lists', 'listeo_core'),
                      'description' =>  __('This will hide the real address on the front-end for all listings and lists - instead random location in circle will be shown', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Gallery & Visual Settings Block
                array(
                    'label' =>  __('<i class="fa fa-images"></i> Gallery & Visual Settings', 'listeo_core'),
                    'description' =>  __('Configure gallery display and visual presentation options', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'gallery_visual_block'
                ),
                array(
                    'id'            => 'gallery_type',
                    'label'         => __('Default Gallery Type', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'grid'       => __('Grid Gallery', 'listeo_core'),
                        'top'       => __('Gallery on top (requires minimum 4 photos)', 'listeo_core'),
                        'content'   => __('Gallery in content', 'listeo_core'),
                    ),
                    'default'       => 'grid'
                ),
                array(
                    'label'         => __('Show taxonomies as list of checkboxes on single template', 'listeo_core'),
                    'description'   => __('Selected which taxonomies should be displayed as list on single listing view', 'listeo_core'),
                    'id'            => 'single_taxonomies_checkbox_list',
                    'type'          => 'checkbox_multi',
                    'options'       => listeo_core_get_listing_taxonomies_as_options(),
                    'default'       => array('listing_feature')
                ),

                // Calendar & Booking Display Block
                array(
                    'label' =>  __('<i class="fa fa-calendar"></i> Calendar & Booking Display', 'listeo_core'),
                    'description' =>  __('Configure calendar visibility and booking display options', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'calendar_booking_block'
                ),
                array(
                    'id'            => 'show_calendar_single',
                    'label'         => __('Show Full Calendar on single listing', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'show_calendar_single_type',
                    'label'         => __('Single listing Full Calendar content type', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'owner'       => __('Show only blocked days by owner', 'listeo_core'),
                        'user'   => __('Show all booked days and times', 'listeo_core'),
                    ),
                    'default'       => 'owner'
                ),
                array(
                    'id'            => 'show_calendar_single_ical',
                    'label'         => __('Show iCal imported events on single listing calendar', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Google Reviews Integration Block
                array(
                    'label' =>  __('<i class="fa fa-google"></i> Google Reviews Integration', 'listeo_core'),
                    'description' =>  __('Configure Google Reviews display and language settings', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'google_reviews_block'
                ),
                array(
                    'id'            => 'google_reviews',
                    'label'         => __('Enable Google Reviews', 'listeo_core'),
                    'description' =>  __('Enable Google Reviews functionality for listings with Google Place IDs', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'label' => __('Google Places API key for Google Reviews', 'listeo_core'),
                    'description' => __('Generate API key for Google Reviews functionality (without any domain/key restriction).', 'listeo_core'),
                    'id'   => 'google_reviews_api_key',
                    'type' => 'text',
                    'placeholder'   => __('Google Places API key', 'listeo_core')
                ),
                array(
                    'id'            => 'google_reviews_lang',
                    'label'         => __('Set language for Google Reviews', 'listeo_core'),
                    'description'   => __('This option will set in which language the reviews will be loaded', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'af' => __('AFRIKAANS', 'listeo_core'),
                        'sq' => __('ALBANIAN', 'listeo_core'),
                        'am' => __('AMHARIC', 'listeo_core'),
                        'ar' => __('ARABIC', 'listeo_core'),
                        'hy' => __('ARMENIAN', 'listeo_core'),
                        'az' => __('AZERBAIJANI', 'listeo_core'),
                        'eu' => __('BASQUE', 'listeo_core'),
                        'be' => __('BELARUSIAN', 'listeo_core'),
                        'bn' => __('BENGALI', 'listeo_core'),
                        'bs' => __('BOSNIAN', 'listeo_core'),
                        'bg' => __('BULGARIAN', 'listeo_core'),
                        'my' => __('BURMESE', 'listeo_core'),
                        'ca' => __('CATALAN', 'listeo_core'),
                        'zh' => __('CHINESE', 'listeo_core'),
                        'zh-CN' => __('CHINESE (SIMPLIFIED)', 'listeo_core'),
                        'zh-HK' => __('CHINESE (HONG KONG)', 'listeo_core'),
                        'zh-TW' => __('CHINESE (TRADITIONAL)', 'listeo_core'),
                        'hr' => __('CROATIAN', 'listeo_core'),
                        'cs' => __('CZECH', 'listeo_core'),
                        'da' => __('DANISH', 'listeo_core'),
                        'nl' => __('DUTCH', 'listeo_core'),
                        'en' => __('ENGLISH', 'listeo_core'),
                        'en-AU' => __('ENGLISH (AUSTRALIAN)', 'listeo_core'),
                        'en-GB' => __('ENGLISH (GREAT BRITAIN)', 'listeo_core'),
                        'et' => __('ESTONIAN', 'listeo_core'),
                        'fa' => __('FARSI', 'listeo_core'),
                        'fi' => __('FINNISH', 'listeo_core'),
                        'fil' => __('FILIPINO', 'listeo_core'),
                        'fr' => __('FRENCH', 'listeo_core'),
                        'fr-CA' => __('FRENCH (CANADA)', 'listeo_core'),
                        'gl' => __('GALICIAN', 'listeo_core'),
                        'ka' => __('GEORGIAN', 'listeo_core'),
                        'de' => __('GERMAN', 'listeo_core'),
                        'el' => __('GREEK', 'listeo_core'),
                        'gu' => __('GUJARATI', 'listeo_core'),
                        'iw' => __('HEBREW', 'listeo_core'),
                        'hi' => __('HINDI', 'listeo_core'),
                        'hu' => __('HUNGARIAN', 'listeo_core'),
                        'is' => __('ICELANDIC', 'listeo_core'),
                        'id' => __('INDONESIAN', 'listeo_core'),
                        'it' => __('ITALIAN', 'listeo_core'),
                        'ja' => __('JAPANESE', 'listeo_core'),
                        'kn' => __('KANNADA', 'listeo_core'),
                        'kk' => __('KAZAKH', 'listeo_core'),
                        'km' => __('KHMER', 'listeo_core'),
                        'ko' => __('KOREAN', 'listeo_core'),
                        'ky' => __('KYRGYZ', 'listeo_core'),
                        'lo' => __('LAO', 'listeo_core'),
                        'lv' => __('LATVIAN', 'listeo_core'),
                        'lt' => __('LITHUANIAN', 'listeo_core'),
                        'mk' => __('MACEDONIAN', 'listeo_core'),
                        'ms' => __('MALAY', 'listeo_core'),
                        'ml' => __('MALAYALAM', 'listeo_core'),
                        'mr' => __('MARATHI', 'listeo_core'),
                        'mn' => __('MONGOLIAN', 'listeo_core'),
                        'ne' => __('NEPALI', 'listeo_core'),
                        'no' => __('NORWEGIAN', 'listeo_core'),
                        'pl' => __('POLISH', 'listeo_core'),
                        'pt' => __('PORTUGUESE', 'listeo_core'),
                        'pt-BR' => __('PORTUGUESE (BRAZIL)', 'listeo_core'),
                        'pt-PT' => __('PORTUGUESE (PORTUGAL)', 'listeo_core'),
                        'pa' => __('PUNJABI', 'listeo_core'),
                        'ro' => __('ROMANIAN', 'listeo_core'),
                        'ru' => __('RUSSIAN', 'listeo_core'),
                        'sr' => __('SERBIAN', 'listeo_core'),
                        'si' => __('SINHALESE', 'listeo_core'),
                        'sk' => __('SLOVAK', 'listeo_core'),
                        'sl' => __('SLOVENIAN', 'listeo_core'),
                        'es' => __('SPANISH', 'listeo_core'),
                        'es-419' => __('SPANISH (LATIN AMERICA)', 'listeo_core'),
                        'sw' => __('SWAHILI', 'listeo_core'),
                        'sv' => __('SWEDISH', 'listeo_core'),
                        'ta' => __('TAMIL', 'listeo_core'),
                        'te' => __('TELUGU', 'listeo_core'),
                        'th' => __('THAI', 'listeo_core'),
                        'tr' => __('TURKISH', 'listeo_core'),
                        'uk' => __('UKRAINIAN', 'listeo_core'),
                        'ur' => __('URDU', 'listeo_core'),
                        'uz' => __('UZBEK', 'listeo_core'),
                        'vi' => __('VIETNAMESE', 'listeo_core'),
                        'zu' => __('ZULU', 'listeo_core'),
                    ),
                    'default'       => 'en'
                ),
                array(
                    'id'            => 'google_reviews_cache_days',
                    'label'         => __('Google Reviews Cache Duration (days)', 'listeo_core'),
                    'description' =>  __('How many days Google Reviews should be cached before refreshing. Longer cache periods reduce API costs but show less current data. Google Reviews cannot be stored permanently due to Google Terms of Service. <strong>Cache location:</strong> This setting can be modified here or via smart caching in the Rate Limiting system above.', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'      => 'Put just a number',
                    'default'   => '1',
                    'min' => '1',
                    'max' => '999'
                ),
                array(
                    'id'            => 'google_reviews_instead',
                    'label'         => __('Show Google Reviews rating on listing if there are no Listeo reviews', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Google Reviews API Rate Limiting Block
                array(
                    'label' =>  __('<i class="fa fa-shield-alt"></i> Google Reviews API Rate Limiting', 'listeo_core'),
                    'description' =>  __('Control API usage to prevent excessive charges from Google Places API.', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'google_reviews_gateway_block'
                ),
                array(
                    'id'            => 'google_reviews_gateway_enabled',
                    'label'         => __('Enable API Rate Limiting', 'listeo_core'),
                    'description' =>  __('Enable rate limiting to prevent excessive Google Places API charges', 'listeo_core'),
                    'type'          => 'checkbox',
                    'default'       => 'on',
                ),
                array(
                    'id'            => 'google_limit_per_hour',
                    'label'         => __('Maximum API calls per hour', 'listeo_core'),
                    'description' =>  __('Limit the number of Google Places API calls per hour to control costs', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => '20',
                    'default'       => '20',
                    'min'           => '10',
                    'max'           => '1000'
                ),
                array(
                    'id'            => 'google_limit_per_day',
                    'label'         => __('Maximum API calls per day', 'listeo_core'),
                    'description' =>  __('Limit the number of Google Places API calls per day to control costs', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => '500',
                    'default'       => '500',
                    'min'           => '100',
                    'max'           => '10000'
                ),
                array(
                    'id'            => 'google_bot_protection_enabled',
                    'label'         => __('Enable Bot Protection', 'listeo_core'),
                    'description' =>  __('Block search engine crawlers and bots from triggering expensive Google Places API calls. <br><mark>Important:</mark>  Sites with many listings may still experience crawler-triggered API calls from unknown bots or user agents not in the detection list.', 'listeo_core'),
                    'type'          => 'checkbox',
                    'default'       => 'on',
                ),
                array(
                    'id'            => 'google_api_usage_stats',
                    'label'         => __('API Usage Statistics', 'listeo_core') . ' <a href="#" id="listeo-reset-api-stats" class="button button-small" style="padding: 0; margin: 0; background: transparent; border: none; top: 2px; position: relative;" title="' . __('Reset API usage statistics', 'listeo_core') . '"><span class="dashicons dashicons-update" style="font-size: 12px; line-height: 1.2;"></span></a>',
                    'description' =>  __('Current Google Places API usage for monitoring costs', 'listeo_core'),
                    'type'          => 'custom',
                    'callback'      => array($this, 'display_google_api_stats'),
                ),

                // Review System Settings Block
                array(
                    'label' =>  __('<i class="fa fa-star"></i> Review System Settings', 'listeo_core'),
                    'description' =>  __('Configure review permissions and display options', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'review_system_block'
                ),
                array(
                    'id'            => 'disable_reviews',
                    'label'         => __('Disable reviews on listings', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'owners_can_review',
                    'label'         => __('Allow owners to add reviews', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'reviews_only_booked',
                    'label'         => __('Allow reviewing only to users who made a booking', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'review_photos_disable',
                    'label'         => __('Disable "Add Photos" option in the review form', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Related Listings Configuration Block
                array(
                    'label' =>  __('<i class="fa fa-link"></i> Related Listings Configuration', 'listeo_core'),
                    'description' =>  __('Configure related listing section on single listing view', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'related_listings_block'
                ),
                array(
                    'id'            => 'related_listings_status',
                    'label'         => __('Show related listings section', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'label'         => __('Which taxonomy should be used to relate listings', 'listeo_core'),
                    'description'   => __('Choose taxonomy to find similar listings, or filter by listing type', 'listeo_core'),
                    'id'            => 'single_related_taxonomy',
                    'type'          => 'select',
                    'options'       => listeo_core_get_related_listing_taxonomies_as_options(),
                    'default'       => 'listing_category'
                ),
                array(
                    'label'         => __('Show only related listings from current author', 'listeo_core'),
                    'description'   => __('Related listings will be limited to show only other listings from the main listing author', 'listeo_core'),
                    'id'            => 'single_related_current_author',
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'similar_grid_style',
                    'label'         => __('Related listings grid style', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'compact'       => __('Compact', 'listeo_core'),
                        'grid'   => __('Standard', 'listeo_core'),
                    ),
                    'default'       => 'compact'
                ),
                
                // Nearby Listings Configuration Block (Separate Feature)
                array(
                    'label' =>  __('<i class="fa fa-location-arrow"></i> Nearby Listings Feature', 'listeo_core'),
                    'description' =>  __('Show nearby listings section on single listing pages based on geolocation', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'nearby_listings_feature_block'
                ),
                array(
                    'id'            => 'nearby_listings_status',
                    'label'         => __('Enable nearby listings section', 'listeo_core'),
                    'description'   => __('Show a separate "Nearby Listings" section on single listing pages based on geographical distance', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'nearby_listings_radius',
                    'label'         => __('Search radius for nearby listings', 'listeo_core'),
                    'description'   => __('Distance radius to search for nearby listings', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => '50',
                    'default'       => '50',
                    'min'           => '1',
                    'max'           => '500'
                ),
                array(
                    'id'            => 'nearby_listings_unit',
                    'label'         => __('Distance unit', 'listeo_core'),
                    'description'   => __('Unit of measurement for displaying distances', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'km'        => __('Kilometers (km)', 'listeo_core'),
                        'miles'     => __('Miles (mi)', 'listeo_core'),
                    ),
                    'default'       => 'km'
                ),
                array(
                    'id'            => 'nearby_listings_cache_days',
                    'label'         => __('Cache nearby listings for (days)', 'listeo_core'),
                    'description'   => __('How long to cache nearby listings results to improve performance', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => '30',
                    'default'       => '30',
                    'min'           => '1',
                    'max'           => '365'
                ),
                array(
                    'label'         => __('Which taxonomy should be used to filter nearby listings', 'listeo_core'),
                    'description'   => __('Choose taxonomy to filter nearby listings, or display all nearby listings', 'listeo_core'),
                    'id'            => 'nearby_listings_taxonomy',
                    'type'          => 'select',
                    'options'       => listeo_core_get_nearby_listing_taxonomies_as_options(),
                    'default'       => 'all'
                ),
                array(
                    'id'            => 'nearby_listings_limit',
                    'label'         => __('Limit number of nearby listings to display', 'listeo_core'),
                    'description'   => __('Maximum number of nearby listings to show (0 = unlimited)', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => '6',
                    'default'       => '6',
                    'min'           => '0',
                    'max'           => '50'
                ),
                array(
                    'id'            => 'nearby_listings_grid_style',
                    'label'         => __('Nearby listings grid style', 'listeo_core'),
                    'description'   => __('Choose display style for nearby listings section', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'compact'       => __('Compact', 'listeo_core'),
                        'grid'   => __('Standard', 'listeo_core'),
                    ),
                    'default'       => 'compact'
                ),

            )
        );

        $settings['booking'] = array(
            'title'                 => __('<i class="fa fa-calendar-alt"></i> Booking', 'listeo_core'),
            'fields'                => array(

                // User Access & Permissions Block
                array(
                    'label' =>  __('<i class="fa fa-user-check"></i> User Access & Permissions', 'listeo_core'),
                    'description' =>  __('Configure user access and booking permissions', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'user_access_block'
                ),
                array(
                    'id'            => 'booking_without_login',
                    'label'         => __('Allow user to book without being logged in', 'listeo_core'),
                    'description'   => __('User will be registered in the booking form with default role "guest"', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'owners_can_book',
                    'label'         => __('Allow owners to make bookings', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Booking Features & Options Block
                array(
                    'label' =>  __('<i class="fa fa-cogs"></i> Booking Features & Options', 'listeo_core'),
                    'description' =>  __('Configure booking widget features and display options', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'booking_features_block'
                ),
                array(
                    'id'            => 'remove_guests',
                    'label'         => __('Remove Guests options from all booking widgets', 'listeo_core'),
                    'description'   => __('Guest picker will be removed from booking widget', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'remove_coupons',
                    'label'         => __('Remove Coupons option from Booking widget and confirmation', 'listeo_core'),
                    'description'   => __('Coupons are enabled by default', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'label'      => __('Count last day of data range in rental bookings', 'listeo_core'),
                    'description'      => __('By default the last day as the check-out day is not calculated in price', 'listeo_core'),
                    'id'        => 'count_last_day_booking',
                    'type'      => 'checkbox',
                ),
                array(
                    'id'            => 'time_slot_layout',
                    'label'         => __('Time slot display layout', 'listeo_core'),
                    'description'   => __('Choose how time slots are displayed in the booking widget for service listings', 'listeo_core'),
                    'type'          => 'radio',
                    'options'       => array(
                        'dropdown' => __('Dropdown', 'listeo_core'),
                        'grid'     => __('Grid with date tabs (default)', 'listeo_core'),
                    ),
                    'default'       => 'grid'
                ),
                array(
                    'id'            => 'hide_booked_slots',
                    'label'         => __('Hide booked slots', 'listeo_core'),
                    'description'   => __('When enabled, fully booked time slots will be hidden. When disabled, booked slots will be shown as greyed out and unavailable.', 'listeo_core'),
                    'type'          => 'checkbox',
                    'default'       => 'off'
                ),
                array(
                    'id'            => 'extra_services_options_type',
                    'label'         => __('Disable extra services type option', 'listeo_core'),
                    'description'   => __('Those services are enabled by default, if you check any of them now it will disable it on the list. Disabling all will remove that option', 'listeo_core'),
                    'type'          => 'checkbox_multi',
                    'options'       => array(
                        'onetime' => esc_html__('One time fee', 'listeo_core'),
                        'byguest' => esc_html__('Multiply by guests', 'listeo_core'),
                        'bydays' => esc_html__('Multiply by days', 'listeo_core'),
                        'byguestanddays' => esc_html__('Multiply by guests & days ', 'listeo_core'),
                    ),
                ),

                // Form Fields & Requirements Block
                array(
                    'label' =>  __('<i class="fa fa-wpforms"></i> Form Fields & Requirements', 'listeo_core'),
                    'description' =>  __('Configure required fields and form validation for booking confirmation', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'form_fields_block'
                ),
                array(
                    'id'            => 'booking_first_name_required',
                    'label'         => __('Make First Name field required in booking confirmation form', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'booking_last_name_required',
                    'label'         => __('Make Last Name field required in booking confirmation form', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'booking_email_required',
                    'label'         => __('Make Email field required in booking confirmation form', 'listeo_core'),
                    'type'          => 'checkbox',
                ),   
                array(
                    'id'            => 'booking_phone_required',
                    'label'         => __('Make Phone field required in booking confirmation form', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Address Fields Configuration Block
                array(
                    'label' =>  __('<i class="fa fa-map-marker-alt"></i> Address Fields Configuration', 'listeo_core'),
                    'description' =>  __('Configure address fields for booking forms and payment gateway requirements', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'address_fields_block'
                ),
                array(
                    'id'            => 'add_address_fields_booking_form',
                    'label'         => __('Add address fields section to booking confirmation form', 'listeo_core'),
                    'type'          => 'checkbox',
                    'description'   => __('Used in WooCommerce Orders and required for some payment gateways', 'listeo_core'),
                ),
                array(
                    'id'            => 'booking_address_displayed',
                    'label'         => __('Control display of selected Address fields in booking confirmation form', 'listeo_core'),
                    'type'          => 'checkbox_multi',
                    'options'       => array(
                        'billing_company' => esc_html__('Company Name', 'listeo_core'),
                        'billing_address_1' => esc_html__('Street Address', 'listeo_core'),
                        'billing_address_2' => esc_html__('Street Address 2 (Apartment, suite, unit, etc.)', 'listeo_core'),
                        'billing_postcode' => esc_html__('Postcode/ZIP', 'listeo_core'),
                        'billing_city' => esc_html__('Town', 'listeo_core'),
                        'billing_country' => esc_html__('Country', 'listeo_core'),
                        'billing_state' => esc_html__('State', 'listeo_core'),
                    ),
                    'default' => array('billing_address_1','billing_address_2', 'billing_postcode', 'billing_city', 'billing_country', 'billing_state' ),
                    'description'   => __('Used in WooCommerce Orders and required for some payment gateways', 'listeo_core'),
                ),
                array(
                    'id'            => 'booking_address_required',
                    'label'         => __('Make selected Address fields required in booking confirmation form', 'listeo_core'),
                    'type'          => 'checkbox_multi',
                    'options'       => array(
                        'billing_company' => esc_html__('Company Name', 'listeo_core'),
                        'billing_address_1' => esc_html__('Street Address', 'listeo_core'),
                        'billing_address_2' => esc_html__('Street Address 2 (Apartment, suite, unit, etc.)', 'listeo_core'),
                        'billing_postcode' => esc_html__('Postcode/ZIP', 'listeo_core'),
                        'billing_city' => esc_html__('Town', 'listeo_core'),
                        'billing_country' => esc_html__('Country', 'listeo_core'),
                        'billing_state' => esc_html__('State', 'listeo_core'),
                    ),
                    'default' => array('billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_country', 'billing_state'),
                    'description'   => __('Used in WooCommerce Orders and required for some payment gateways', 'listeo_core'),
                ),

                // Payment & Booking Management Block
                array(
                    'label' =>  __('<i class="fa fa-credit-card"></i> Payment & Booking Management', 'listeo_core'),
                    'description' =>  __('Configure payment settings and booking management options', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'payment_management_block'
                ),
                array(
                    'id'            => 'disable_payments',
                    'label'         => __('Disable payments in bookings', 'listeo_core'),
                    'description'   => __('Bookings will have prices but the payments won\'t be handled by the site. Disable Wallet page in Listeo Core → Pages', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'instant_booking_require_payment',
                    'label'         => __('For "instant booking option" require payment first to confirm the booking', 'listeo_core'),
                    'description'   => __('Users will have to pay for booking immediately to confirm the booking.', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'default_booking_expiration_time',
                    'label'         => __('Set how long booking will be waiting for payment before expiring', 'listeo_core'),
                    'description'   => __('Default is 48 hours, set to 0 to disable', 'listeo_core'),
                    'type'          => 'text',
                    'default'       => '48',
                ),
                array(
                    'id'            => 'block_bookings_period',
                    'label'         => __('Add 15 minutes lock after booking', 'listeo_core'),
                    'description'   => __('Add 15 minutes lock after booking a listing to not allow users to book again immediately', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Dashboard & Display Settings Block
                array(
                    'label' =>  __('<i class="fa fa-tachometer-alt"></i> Dashboard & Display Settings', 'listeo_core'),
                    'description' =>  __('Configure booking dashboard display and contact information visibility', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'dashboard_display_block'
                ),
                array(
                    'id'            => 'show_expired',
                    'label'         => __('Show Expired Bookings in Dashboard page', 'listeo_core'),
                    'description'   => __('Adds "Expired" subpage to Bookings page in owner Dashboard, with list of expired bookings', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'lock_contact_info_to_paid_bookings',
                    'label'         => __('Show Host/Guest contact and address info only for Paid Bookings in Dashboard page', 'listeo_core'),
                    'description'   => __('Contact informations will be hidden for pending bookings', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Ticket Options Block
                array(
                    'label' =>  __('<i class="fa fa-ticket-alt"></i> Ticket Options', 'listeo_core'),
                    'description' =>  __('Configure ticket booking and event settings', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'ticket_options_block'
                ),
                array(
                    'id'            => 'ticket_status',
                    'label'         => __('Enable Ticket option', 'listeo_core'),
                    'description'   => __('It will add downloadable/printable tickets to bookings', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'ticket_show_booking_id',
                    'label'         => __('Display Booking ID on Ticket', 'listeo_core'),
                    'description'   => __('Enable or disable the display of Booking ID on the ticket', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'ticket_terms',
                    'label'         => __('Ticket Terms and Conditions', 'listeo_core'),
                    'description'   => __('Text that will be displayed on the ticket', 'listeo_core'),
                    'type'          => 'textarea',
                ),

                // Developer Settings Block
                array(
                    'label' =>  __('<i class="fa fa-code"></i> Developer Settings', 'listeo_core'),
                    'description' =>  __('Developer and debugging options', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'developer_settings_block'
                ),
                array(
                    'id'            => 'skip_hyphen_check',
                    'label'         => __('If you have a problem with slots not showing despite being configured, try enabling this option.', 'listeo_core'),
                    'description'   => __('Possible fix for slots issue if the file encoding is wrong', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

            )
        );


        $settings['browse'] = array(
            'title'                 => __('<i class="fa fa-search-location"></i> Browse/Search Options', 'listeo_core'),
            'fields'                => array(

                // Default Sorting & Display Block
                array(
                    'label' =>  __('<i class="fa fa-sort"></i> Default Sorting & Display', 'listeo_core'),
                    'description' =>  __('Configure default sorting options and listing display behavior', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'sorting_display_block'
                ),
                array(
                    'label'      => __('By default sort listings by:', 'listeo_core'),
                    'description'      => __('Default sorting method for listing results', 'listeo_core'),
                    'id'        => 'sort_by',
                    'type'      => 'select',
                    'options'   => array(
                        'date-asc' => esc_html__('Oldest Listings', 'listeo_core'),
                        'date-desc' => esc_html__('Newest Listings', 'listeo_core'),
                        'distance' => esc_html__('Nearest First', 'listeo_core'),
                        'featured' => esc_html__('Featured', 'listeo_core'),
                        'highest-rated' => esc_html__('Highest Rated', 'listeo_core'),
                        'reviewed' => esc_html__('Most Reviewed', 'listeo_core'),
                        'upcoming-event' => esc_html__('Upcoming Event', 'listeo_core'),
                        'title' => esc_html__('Alphabetically', 'listeo_core'),
                        'views' => esc_html__('Views', 'listeo_core'),
                        'verified' => esc_html__('Verified', 'listeo_core'),
                        'rand' => esc_html__('Random', 'listeo_core'),
                        'best-match' => esc_html__('Best Match (AI Search)', 'listeo_core'),
                    ),
                    'default'   => 'date-desc'
                ),
                array(
                    'label'      => __('Default radius for "nearest" sort by filter (km)', 'listeo_core'),
                    'description'      => __('Maximum distance to search when sorting by "Nearest First" without specific radius. This improves performance by limiting calculations.', 'listeo_core'),
                    'id'        => 'distance_default_radius',
                    'type'      => 'number',
                    'default'   => 50,
                    'min'       => 1,
                    'max'       => 500,
                    'step'      => 1
                ),

                // Search Technology & Performance Block
                array(
                    'label' =>  __('<i class="fa fa-rocket"></i> Search Technology & Performance', 'listeo_core'),
                    'description' =>  __('Configure search functionality and performance optimization', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'search_technology_block'
                ),
                array(
                    'id'            => 'ajax_browsing',
                    'label'         => __('Ajax based listing browsing', 'listeo_core'),
                    'description'   => __('Enable AJAX for faster, seamless browsing experience', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'on'    => __('Enabled', 'listeo_core'),
                        'off'   => __('Disabled', 'listeo_core'),
                    ),
                    'default'       => 'on'
                ),
                array(
                    'id'            => 'keyword_search',
                    'label'         => __('Keyword Search options', 'listeo_core'),
                    'description'   => __('Select how searching by text will work', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'search_title' => esc_html__('Search Listing Title, Content and Keywords field', 'listeo_core'),
                        'search_meta' => esc_html__('Search above and all custom meta fields', 'listeo_core'),
                    ),
                    'default'       => array('search_title')
                ),
                array(
                    'id'            => 'search_mode',
                    'label'         => __('Keywords search mode', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'relevance'    => __('WordPress search mode (beta)', 'listeo_core'),
                        'exact'    => __('Exact match', 'listeo_core'),
                        'approx'   => __('Approximate match', 'listeo_core'),
                        'fibosearch'   => __('Fibo Search plugin compatibility', 'listeo_core'),
                        'searchwp'   => __('Search WP compatibility', 'listeo_core'),
                    ),
                    'description'   => __('With precise match the keywords will be exactly as users types, so if someone searches for "Apartment Sunny" he won\'t see results with title "Sunny Apartment"', 'listeo_core'),
                    'default'       => 'relevance'
                ),

                // Location & Geographic Search Block
                array(
                    'label' =>  __('<i class="fa fa-map-marker-alt"></i> Location & Geographic Search', 'listeo_core'),
                    'description' =>  __('Configure location-based search behavior and restrictions - settings for location searching without Google API', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'location_search_block'
                ),
                array(
                    'id'            => 'search_only_address',
                    'label'         => __('Restrict location search only to address field', 'listeo_core'),
                    'description'   => __('This option will limit search only to address field if Radius search is not used, otherwise it searches for content and title as well', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'on'    => __('Enabled', 'listeo_core'),
                        'off'   => __('Disabled', 'listeo_core'),
                    ),
                    'default'       => 'off'
                ),
                array(
                    'id'            => 'location_search_method',
                    'label'         => __('Location search method (without Google API)', 'listeo_core'),
                    'description'   => __('Choose the location search method when Google API radius search is not available', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'basic'  => __('Basic - Simple search in address fields only', 'listeo_core'),
                        'broad'  => __('Broad - Smart combination search with fallbacks', 'listeo_core'),
                    ),
                    'default'       => 'basic'
                ),

                // Pagination & Loading Block
                array(
                    'label' =>  __('<i class="fa fa-sync"></i> Pagination & Loading Options', 'listeo_core'),
                    'description' =>  __('Configure how listings are loaded and paginated', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'pagination_loading_block'
                ),
                array(
                    'id'            => 'listeo_infinite_scroll',
                    'label'         => __('Infinite Scroll with Load More Button', 'listeo_core'),
                    'description'   => __('Replace pagination with a "Load More" button for better user experience', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'off'   => __('Disabled - Use standard pagination', 'listeo_core'),
                        'on'    => __('Enabled - Use Load More button', 'listeo_core'),
                    ),
                    'default'       => 'off'
                ),

                // Dynamic Taxonomy Behavior Block
                array(
                    'label' =>  __('<i class="fa fa-tags"></i> Dynamic Taxonomy Behavior', 'listeo_core'),
                    'description' =>  __('Configure how taxonomies interact and filter each other', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'dynamic_taxonomy_block'
                ),
                array(
                    'id'            => 'dynamic_features',
                    'label'         => __('Make "features" taxonomy related to categories', 'listeo_core'),
                    'description'   => __('This option will refresh list of features based on selected category', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'on'    => __('Enabled', 'listeo_core'),
                        'off'   => __('Disabled', 'listeo_core'),
                    ),
                    'default'       => 'on'
                ),
                array(
                    'id'            => 'dynamic_taxonomies',
                    'label'         => __('Make "listing type" taxonomy related to categories', 'listeo_core'),
                    'description'   => __('This option will show listing type taxonomy field based on selected category', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'on'    => __('Enabled', 'listeo_core'),
                        'off'   => __('Disabled', 'listeo_core'),
                    ),
                    'default'       => 'off'
                ),

                // Saved Search Alerts Block
                array(
                    'label' =>  __('<i class="fa fa-bell"></i> Saved Search Alerts', 'listeo_core'),
                    'description'   => __('Configure saved search alerts feature for users', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'saved_search_alerts_block'
                ),
                array(
                    'label'      => __('Enable Saved Search Alerts Feature', 'listeo_core'),
                    'description'      => __('Allow users to save their searches and receive email alerts when new matching listings are published', 'listeo_core'),
                    'id'        => 'saved_search_alerts_enabled',
                    'type'      => 'checkbox',
                    'default'   => true,
                ),
                array(
                    'label'      => __('Maximum saved searches per user', 'listeo_core'),
                    'description'      => __('Limit how many searches a user can save', 'listeo_core'),
                    'id'        => 'max_saved_searches',
                    'type'      => 'number',
                    'default'   => 10,
                ),

                // Search Logic & Relations Block
                array(
                    'label' =>  __('<i class="fa fa-project-diagram"></i> Search Logic & Relations', 'listeo_core'),
                    'description' =>  __('Configure logical relationships for taxonomy and search filtering', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'search_logic_block'
                ),
                array(
                    'id'            => 'taxonomy_or_and',
                    'label'         => __('For taxonomy search as default use logical relation:', 'listeo_core'),
                    'description'   => __('This option will let you choose search results that have one of the features or all of the features you look for.', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'OR'    => __('OR', 'listeo_core'),
                        'AND'   => __('AND', 'listeo_core'),
                    ),
                    'default'       => 'OR'
                ),

            )
        );

        $taxonomy_objects = get_object_taxonomies('listing', 'objects');
        if ($taxonomy_objects) {
            foreach ($taxonomy_objects as $tax) {
                $settings['browse']['fields'][] =    array(
                    'id'            => $tax->name . 'search_mode',
                    'label'         =>  $tax->label . __(' search logical relation', 'listeo_core'),
                    'description'  =>  __('<mark>AND Logic</mark>: Listings must have ALL selected categories to appear in results. More restrictive search. <mark>OR Logic</mark>: Listings need ANY of the selected categories to appear in results. Broader search.', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'OR'    => __('OR', 'listeo_core'),
                        'AND'   => __('AND', 'listeo_core'),
                    ),
                    'default'       => 'AND'
                );
            }
        }

        $settings['registration'] = array(
            'title'                 => __('<i class="fa fa-user-friends"></i> Registration', 'listeo_core'),
            'fields'                => array(

                // Login & Authentication Settings Block
                array(
                    'label' =>  __('<i class="fa fa-sign-in-alt"></i> Login & Authentication Settings', 'listeo_core'),
                    'description' =>  __('Configure login behavior and authentication security', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'login_auth_block'
                ),
                array(
                    'id'            => 'front_end_login',
                    'label'         => __('Enable Forced Front End Login & Password Reset', 'listeo_core'),
                    'description'   => __('Enabling this option will redirect all wp-login request to frontend form. Be aware that on some servers or some configuration, especially with security plugins, this might cause a redirect loop, so always test this setting on different browser, while being still logged in Dashboard to have option to disable that if things go wrong. It is required setting to use Listeo Front-end Reset/Lost Password pages', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'login_nonce_skip',
                    'label'         => __('Skip additional login/registration security check', 'listeo_core'),
                    'description'   => __('Not advised, but might be required if you using aggressive cache plugins.', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'popup_login',
                    'label'         => __('Login/Registration Form Type', 'listeo_core'),
                    'description'   => __('Choose how login and registration forms are displayed', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'ajax'       => __('Ajax form in a popup', 'listeo_core'),
                        'page'   => __('Separate page', 'listeo_core'),
                    ),
                    'default'       => 'ajax'
                ),
                array(
                    'id'            => 'email_otp_verification',
                    'label'         => __('Enable email OTP verification', 'listeo_core'),
                    'description'   => __('User will have to verify his email address before being able to login', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'autologin',
                    'label'         => __('Automatically login user after successful registration', 'listeo_core'),
                    'description'   => __('Users will be logged in immediately after successful registration', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Form Fields & Requirements Block
                array(
                    'label' =>  __('<i class="fa fa-wpforms"></i> Form Fields & Requirements', 'listeo_core'),
                    'description' =>  __('Configure registration form fields and validation requirements', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'form_fields_registration_block'
                ),
                array(
                    'id'            => 'registration_form_default_role',
                    'label'         => __('Set default role for Registration Form', 'listeo_core'),
                    'description'   => __('If you set it hidden, set default role in Settings → General → New User Default Role', 'listeo_core'),
                    'type'          => 'select',
                    'default'       => 'guest',
                    'options'       => array(
                        'owner' => esc_html__('Owner', 'listeo_core'),
                        'guest' => esc_html_x('Guest', 'User role', 'listeo_core'),
                    ),
                ),
                array(
                    'id'            => 'registration_hide_role',
                    'label'         => __('Hide Role field in Registration Form', 'listeo_core'),
                    'description'   => __('If hidden, set default role in Settings → General → New User Default Role', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'registration_hide_username',
                    'label'         => __('Hide Username field in Registration Form', 'listeo_core'),
                    'description'   => __('Username will be generated from email address (part before @)', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'registration_hide_username_use_email',
                    'label'         => __('If username is hidden use full email as user login', 'listeo_core'),
                    'description'   => __('If not selected, the username will be generated from the first part of email, all before the "@"', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'display_first_last_name',
                    'label'         => __('Display First and Last name fields in registration form', 'listeo_core'),
                    'description'   => __('Adds optional input fields for first and last name', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'display_first_last_name_required',
                    'label'         => __('Make First and Last name fields required', 'listeo_core'),
                    'description'   => __('Enable to make those fields required', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'display_password_field',
                    'label'         => __('Add Password pickup field to registration form', 'listeo_core'),
                    'description'   => __('Enable to add password field, when disabled it will be randomly generated and sent via email', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'strong_password',
                    'label'         => __('Add additional password strength requirement', 'listeo_core'),
                    'description'   => __('Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Security & Privacy Block
                array(
                    'label' =>  __('<i class="fa fa-shield-alt"></i> Security & Privacy', 'listeo_core'),
                    'description' =>  __('Configure CAPTCHA protection and privacy policy requirements', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'security_privacy_block'
                ),
                array(
                    'id'            => 'privacy_policy',
                    'label'         => __('Enable Privacy Policy link in registration form', 'listeo_core'),
                    'description'   => __('You can set Privacy page in Settings → Privacy', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'terms_and_conditions_req',
                    'label'         => __('Require terms and conditions approval in registration form', 'listeo_core'),
                    'description'   => __('Do not forget to add this page and set in setting below', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'terms_and_conditions_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Terms and conditions page', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'recaptcha',
                    'label'         => __('Enable CAPTCHA on registration form', 'listeo_core'),
                    'description'   => __('Check this checkbox to add CAPTCHA protection to registration and login forms. You need to provide API keys for your selected CAPTCHA service.', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'recaptcha_reviews',
                    'label'         => __('Enable CAPTCHA on reviews form', 'listeo_core'),
                    'description'   => __('Check this checkbox to add CAPTCHA protection to Reviews form. You need to provide API keys for your selected CAPTCHA service.', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // CAPTCHA Configuration Block
                array(
                    'label' =>  __('<i class="fa fa-robot"></i> CAPTCHA Configuration', 'listeo_core'),
                    'description' =>  __('Configure CAPTCHA services and API keys for spam protection', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'captcha_config_block'
                ),
                array(
                    'id'            => 'recaptcha_version',
                    'label'         => __('Captcha version', 'listeo_core'),
                    'description'   => __('Select your preferred CAPTCHA service provider', 'listeo_core'),
                    'type'          => 'select',
                    'options'       => array(
                        'v2'        => __('reCAPTCHA V2 checkbox', 'listeo_core'),
                        'v3'        => __('reCAPTCHA V3', 'listeo_core'),
                        'hcaptcha'  => __('hCaptcha', 'listeo_core'),
                        'turnstile' => __('Cloudflare Turnstile', 'listeo_core'),
                    ),
                    'default'       => 'v2'
                ),
                array(
                    'id'            => 'recaptcha_sitekey',
                    'label'         => __('reCAPTCHA v2 Site Key', 'listeo_core'),
                    'description'   => sprintf(
                        __('Get the sitekey from %s - use reCaptcha v2', 'listeo_core'),
                        '<a href="https://www.google.com/recaptcha/admin#list" target="_blank">Google reCAPTCHA Admin</a>'
                    ),
                    'type'          => 'text',
                ),
                array(
                    'id'            => 'recaptcha_secretkey',
                    'label'         => __('reCAPTCHA v2 Secret Key', 'listeo_core'),
                    'description'   => sprintf(
                        __('Get the sitekey from %s - use reCaptcha v2', 'listeo_core'),
                        '<a href="https://www.google.com/recaptcha/admin#list" target="_blank">Google reCAPTCHA Admin</a>'
                    ),
                    'type'          => 'text',
                ),
                array(
                    'id'            => 'recaptcha_sitekey3',
                    'label'         => __('reCAPTCHA v3 Site Key', 'listeo_core'),
                    'description'   => sprintf(
                        __('Get the sitekey from %s - use reCaptcha v3', 'listeo_core'),
                        '<a href="https://www.google.com/recaptcha/admin#list" target="_blank">Google reCAPTCHA Admin</a>'
                    ),
                    'type'          => 'text',
                ),
                array(
                    'id'            => 'recaptcha_secretkey3',
                    'label'         => __('reCAPTCHA v3 Secret Key', 'listeo_core'),
                    'description'   => sprintf(
                        __('Get the sitekey from %s - use reCaptcha v3', 'listeo_core'),
                        '<a href="https://www.google.com/recaptcha/admin#list" target="_blank">Google reCAPTCHA Admin</a>'
                    ),
                    'type'          => 'text',
                ),
                array(
                    'id'            => 'hcaptcha_sitekey',
                    'label'         => __('hCaptcha Site Key', 'listeo_core'),
                    'description'   => sprintf(
                        __('Get the sitekey from %s - use hCaptcha', 'listeo_core'),
                        '<a href="https://www.hcaptcha.com/" target="_blank">hCaptcha</a>'
                    ),
                    'type'          => 'text',
                ),
                array(
                    'id'            => 'hcaptcha_secretkey',
                    'label'         => __('hCaptcha Secret Key', 'listeo_core'),
                    'description'   => sprintf(
                        __('Get the sitekey from %s - use hCaptcha', 'listeo_core'),
                        '<a href="https://www.hcaptcha.com/" target="_blank">hCaptcha</a>'
                    ),
                    'type'          => 'text',
                ),
                array(
                    'id'            => 'turnstile_sitekey',
                    'label'         => __('Cloudflare Turnstile Site Key', 'listeo_core'),
                    'description'   => __('Get the site key from Cloudflare dashboard - use Turnstile', 'listeo_core'),
                    'type'          => 'text',
                ),
                array(
                    'id'            => 'turnstile_secretkey',
                    'label'         => __('Cloudflare Turnstile Secret Key', 'listeo_core'),
                    'description'   => __('Get the secret key from Cloudflare dashboard - use Turnstile', 'listeo_core'),
                    'type'          => 'text',
                ),

                // User Account Management Block
                array(
                    'label' =>  __('<i class="fa fa-user-cog"></i> User Account Management', 'listeo_core'),
                    'description' =>  __('Configure user account features and role management', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'user_account_block'
                ),
                array(
                    'id'            => 'profile_allow_role_change',
                    'label'         => __('Allow user to change his role in "My Account" page', 'listeo_core'),
                    'description'   => __('Works only for owners and guests', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Redirect Settings Block
                array(
                    'label' =>  __('<i class="fa fa-external-link-alt"></i> Redirect Settings', 'listeo_core'),
                    'description' =>  __('Configure post-login and post-registration redirect pages', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'redirect_settings_block'
                ),
                array(
                    'id'            => 'ajax_login_redirect_enable',
                    'label'         => __('Enable redirect for AJAX popup login', 'listeo_core'),
                    'description'   => __('When enabled, users logging in via AJAX popup will be redirected to the configured pages below instead of staying on the current page. By default, AJAX login refreshes the current page without redirecting.', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                array(
                    'id'            => 'owner_registration_redirect',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Owner redirect after registration to page', 'listeo_core'),
                    'description'   => __('This works only with static page login form, not ajax', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'owner_login_redirect',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Owner redirect after login to page', 'listeo_core'),
                    'description'   => __('This works only with static page login form, not ajax', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'guest_registration_redirect',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Guest redirect after registration to page', 'listeo_core'),
                    'description'   => __('This works only with static page login form, not ajax', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'guest_login_redirect',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Guest redirect after login to page', 'listeo_core'),
                    'description'   => __('This works only with static page login form, not ajax', 'listeo_core'),
                    'type'          => 'select',
                ),

            )
        );
        if (class_exists('WeDevs_Dokan')) :
            $settings['dokan'] = array(
                'title'                 => __('<i class="fa fa-shopping-cart"></i> Dokan', 'listeo_core'),
                'fields'                => array(

                    // User Role & Registration
                    array(
                        'label'         => __('<i class="fa fa-user-tag"></i> User Role & Registration', 'listeo_core'),
                        'description'   => __('Configure default user roles and vendor registration settings', 'listeo_core'),
                        'type'          => 'title',
                        'id'            => 'dokan_user_role_block'
                    ),
                    array(
                        'label'      => __('Default user role for new users with Dokan active', 'listeo_core'),
                        'description'      => __('Choose if you want all new owners to be vendors', 'listeo_core'),
                        'id'        => 'role_dokan',
                        'type'      => 'select',
                        'options'   => array(
                            'seller' => esc_html__('Vendor', 'listeo_core'),
                            'owner' => esc_html__('Owner', 'listeo_core')
                        ),
                        'default'       => 'no'
                    ),

                    // Payment Gateway Integration
                    array(
                        'label'         => __('<i class="fa fa-credit-card"></i> Payment Gateway Integration', 'listeo_core'),
                        'description'   => __('Configure payment gateway compatibility and restrictions', 'listeo_core'),
                        'type'          => 'title',
                        'id'            => 'dokan_payment_block'
                    ),
                    array(
                        'id'            => 'disable_dokan_stripe_payment_on_boookings',
                        'label'         => __('Disable Dokan Stripe Connect payment gateway on booking payments', 'listeo_core'),
                        'description'   => __('In case you are using Listeo Stripe Connect', 'listeo_core'),
                        'type'          => 'checkbox',
                    ),

                    // Product & Category Management
                    array(
                        'label'         => __('<i class="fa fa-boxes"></i> Product & Category Management', 'listeo_core'),
                        'description'   => __('Configure product categories and taxonomy visibility in Dokan vendor dashboard', 'listeo_core'),
                        'type'          => 'title',
                        'id'            => 'dokan_product_block'
                    ),
                    array(
                        'label'         => __('Disable product categories from Dokan', 'listeo_core'),
                        'description'   => __('Selected which taxonomies should not be displayed in stores and products screen', 'listeo_core'),
                        'id'            => 'dokan_exclude_categories',
                        'type'          => 'checkbox_multi',
                        'options'       => listeo_core_get_product_taxonomies_as_options(),
                        'default'       => array('listeo-booking')
                    ),

                )
            );
        endif;

        $settings['ad_campaigns'] = array(
            'title'                 => __('<i class="fa fa-bullhorn"></i> Ad Campaigns', 'listeo_core'),
            'fields' => array(

                // Campaign Setup & Configuration
                array(
                    'label'         => __('<i class="fa fa-cog"></i> Campaign Setup & Configuration', 'listeo_core'),
                    'description'   => __('Configure basic ad campaign settings and payment products', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'ad_campaign_setup_block'
                ),
                array(
                    'id'            => 'ad_campaign_product_id',
                    'options'       => listeo_core_get_product_options('listeo_ad_campaign'),
                    'label'         => __('Campaign Product', 'listeo_core'),
                    'description'   => __('This product will be used for payments, if you don\'t see anything create new product and set it\'s type to Listeo Ad Campaign', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'ad_campaigns_type',
                    'label'         => __('Ad Campaigns type', 'listeo_core'),
                    'description'   => __('Price per view and/or Price per click', 'listeo_core'),
                    'type'          => 'checkbox_multi',
                    'options'       => array(
                        'ppc' => __('Per click', 'listeo_core'),
                        'ppv' => __('Per views', 'listeo_core'),
                    ),
                    'default'       => array('ppc','ppv')
                ),

                // Placement & Visibility Options
                array(
                    'label'         => __('<i class="fa fa-map-marker-alt"></i> Placement & Visibility Options', 'listeo_core'),
                    'description'   => __('Configure where ads will be displayed across the website', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'ad_placement_block'
                ),
                array(
                    'id'            => 'ad_campaigns_placement',
                    'label'         => __('Ad Campaigns placement', 'listeo_core'),
                    'description'   => __('Where the ad will be displayed, deselecting all options will default to Search results', 'listeo_core'),
                    'type'          => 'checkbox_multi',
                    'options'       => array(
                        'home' => __('Home Page section', 'listeo_core'),
                        'search' => __('Search results', 'listeo_core'),
                        'sidebar' => __('Sidebar widget', 'listeo_core'),
                    ),
                    'default'       => array('home','search','sidebar','location','tag')
                ),

                // Per-Click Pricing Configuration
                array(
                    'label'         => __('<i class="fa fa-mouse-pointer"></i> Per-Click Pricing Configuration', 'listeo_core'),
                    'description'   => __('Set pricing for pay-per-click advertising across different placements', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'ad_click_pricing_block'
                ),

                array(
                    'id'            => 'ad_campaigns_price_home_click',
                    'label'         => __('Ad Campaigns price for Home Page', 'listeo_core'),
                    'description'   => __('Price per click', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'      => 'Put just a number',
                ),
                array(
                    'id'            => 'ad_campaigns_price_search_click',
                    'label'         => __('Ad Campaigns price for Search', 'listeo_core'),
                    'description'   => __('Price per click', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'      => 'Put just a number',
                ),
                array(
                    'id'            => 'ad_campaigns_price_sidebar_click',
                    'label'         => __('Ad Campaigns price for Sidebar', 'listeo_core'),
                    'description'   => __('Price per click', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'      => 'Put just a number',
                ),

                // Per-View Pricing Configuration
                array(
                    'label'         => __('<i class="fa fa-eye"></i> Per-View Pricing Configuration', 'listeo_core'),
                    'description'   => __('Set pricing for pay-per-view advertising (per 1000 views) across different placements', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'ad_view_pricing_block'
                ),
                array(
                    'id'            => 'ad_campaigns_price_home_view',
                    'label'         => __('Ad Campaigns price for Home per 1k views', 'listeo_core'),
                    'description'   => __('Price per 1000 views', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => __('Price per 1000 views', 'listeo_core'),
                ),
                array(
                    'id'            => 'ad_campaigns_price_search_view',
                    'label'         => __('Ad Campaigns price for Search per 1k views', 'listeo_core'),
                    'description'   => __('Price per 1000 views', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => __('Price per 1000 views', 'listeo_core'),
                ),
                array(
                    'id'            => 'ad_campaigns_price_sidebar_view',
                    'label'         => __('Ad Campaigns price for Sidebar per 1k views', 'listeo_core'),
                    'description'   => __('Price per 1000 views', 'listeo_core'),
                    'type'          => 'number',
                    'placeholder'   => __('Price per 1000 views', 'listeo_core'),
                ),

                // array(
                //     'id'            => 'ad_campaigns_limit',
                //     'label'         => __('Ad Campaigns limit', 'listeo_core'),
                //     'description'   => __('How many ads can be displayed at the same time', 'listeo_core'),
                //     'type'          => 'text',
                // ),
                // array(
                //     'id'            => 'ad_campaigns_limit_per_user',
                //     'label'         => __('Ad Campaigns limit per user', 'listeo_core'),
                //     'description'   => __('How many ads can be displayed at the same time', 'listeo_core'),
                //     'type'          => 'text',
                // ),
                // array(
                //     'id'            => 'ad_campaigns_limit_per_listing',
                //     'label'         => __('Ad Campaigns limit per listing', 'listeo_core'),
                //     'description'   => __('How many ads can be displayed at the same time', 'listeo_core'),
                //     'type'          => 'text',
                // ),
                // array(
                //     'id'            => 'ad_campaigns_limit_per_category',
                //     'label'         => __('Ad Campaigns limit per category', 'listeo_core'),
                //     'description'   => __('How many ads can be displayed at the same time', 'listeo_core'),
                //     'type'          => 'text',
                // ),
                // array(
                //     'id'            => 'ad_campaigns_limit_per_location',
                //     'label'         => __('Ad Campaigns limit per location', 'listeo_core'),
                //     'description'   => __('How many ads can be displayed at the same time', 'listeo_core'),
                //     'type'          => 'text',
                // ),
                // array(
                //     'id'            => 'ad_campaigns_limit_per_tag',
                //     'label'         => __('Ad Campaigns limit per tag', 'listeo_core'),
                //     'description'   => __('How many ads can be displayed at the same time', 'listeo_core'),
                //     'type'          => 'text',
                // ),

            )
        );
        $settings['claims'] = array(
            'title'                 => __('<i class="fa fa-clipboard-check"></i> Claim Listing Options', 'listeo_core'),
            // 'description'           => __( 'Settings for the Claims', 'listeo_core' ),
            'fields'                => array(
                
                // Claim Configuration & Access
                array(
                    'label'         => __('<i class="fa fa-cogs"></i> Claim Configuration & Access', 'listeo_core'),
                    'description'   => __('Basic settings for claim listing functionality and user access permissions', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'claims_config_block'
                ),
                array(
                    'id'            => 'disable_claims',
                    'label'         => __('Disable Claims button on all listings', 'listeo_core'),
                    'description'   => __('By default it is enabled on all not verified listings', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'enable_registration_claims',
                    'label'         => __('Allow registration in Claim Listing popup', 'listeo_core'),
                    'description'   => __('Claim option will be available for anyone without prior login, and user will be registered during the claim process', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'file_upload_claims',
                    'label'         => __('Add File Upload option to claim listing form', 'listeo_core'),
                    'description'   => __('User will be able to upload single field for verification', 'listeo_core'),
                    'type'          => 'checkbox',
                ),

                // Payment & Package Management
                array(
                    'label'         => __('<i class="fa fa-credit-card"></i> Payment & Package Management', 'listeo_core'),
                    'description'   => __('Configure paid claims, approval process, and package restrictions for claiming listings', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'claims_payment_block'
                ),
                array(
                    'id'            => 'enable_paid_claims',
                    'label'         => __('Enable Paid Claims option', 'listeo_core'),
                    'description'   => __('Adds package selection for claims', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                // skip approval, require payment to approve
                array(
                    'id'            => 'skip_claim_approval',
                    'label'         => __('Skip approval for claims', 'listeo_core'),
                    'description'   => __('Claims will be automatically approved after immediate payment', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'label'         => __('Exclude packages from claim selection', 'listeo_core'),
                    'description'   => __('If you do not want to use some package for claiming select them below ', 'listeo_core'),
                    'id'            => 'exclude_from_claim',
                    'type'          => 'checkbox_multi',
                    'options'       => listeo_core_get_listing_packages_as_options(true),
                    //'options'       => array( 'linux' => 'Linux', 'mac' => 'Mac', 'windows' => 'Windows' ),
                    'default'       => array()
                ),

                // Admin Notifications
                array(
                    'label'         => __('<i class="fa fa-bell"></i> Admin Notifications', 'listeo_core'),
                    'description'   => __('Configure email notifications sent to administrators when new claim requests are submitted', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'claims_admin_notifications_block'
                ),
                array(
                    'id'            => 'admin_claim_notification',
                    'label'         => __('Notify admin about new claim request', 'listeo_core'),
                    'description'   => __('Sends email to site admin', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'label'      => __('Claim listing approved notification email subject', 'listeo_core'),
                    'default'      => __('New claim request', 'listeo_core'),
                    'id'        => 'claim_request_notification_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Claimant (recipient):</strong> {user_name}, {user_mail}, {first_name}, {last_name}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}', 'listeo_core') . '<br>'
						. __('<strong>Payment:</strong> {payment_url}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Claim listing approved notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi Admin,<br>
					 There's a new claim request for '{listing_name}' from {first_name} {last_name}. You can check it <a href='{claim_url}'>here</a>.
					<br>Thank you")),
                    'id'        => 'claim_request_notification_email_content',
                    'type'      => 'editor',
                ),
                /*Claim listing approved*/
                // User Status Notifications
                array(
                    'label'         => __('<i class="fa fa-envelope"></i> User Status Notifications', 'listeo_core'),
                    'description'   => __('Configure automated email notifications sent to users when their claim status changes', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'claims_user_notifications_block'
                ),
                array(

                    'label' =>  __('<i class="fa fa-check-circle"></i> Claim Listing approved notification', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_claim_approved_message'
                ),
                array(
                    'label'      => __('Enable claim listing approved notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user when claim listing was approved', 'listeo_core'),
                    'id'        => 'claim_approved_notification',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Claim listing approved notification email subject', 'listeo_core'),
                    'default'      => __('Your claim was approved', 'listeo_core'),
                    'id'        => 'claim_approved_notification_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Claimant (recipient):</strong> {user_name}, {user_mail}, {first_name}, {last_name}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}', 'listeo_core') . '<br>'
						. __('<strong>Payment:</strong> {payment_url}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Claim listing approved notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your claim for '{listing_name}' was approved. You can now manage this listing.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'claim_approved_notification_email_content',
                    'type'      => 'editor',
                ),

                /*Claim listing rejected*/
                array(

                    'label' =>  __('<i class="fa fa-times-circle"></i> Claim Listing rejected notification', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_claim_rejected_message'
                ),
                array(
                    'label'      => __('Enable claim listing rejected notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user when claim listing was rejected', 'listeo_core'),
                    'id'        => 'claim_rejected_notification',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Claim listing rejected notification email subject', 'listeo_core'),
                    'default'      => __('Your claim was rejected', 'listeo_core'),
                    'id'        => 'claim_rejected_notification_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Claimant (recipient):</strong> {user_name}, {user_mail}, {first_name}, {last_name}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Claim listing rejected notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your claim for '{listing_name}' was rejected. Please contact us for more information.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'claim_rejected_notification_email_content',
                    'type'      => 'editor',
                ),

                //Claim listing pending
                // Claim Pending Notifications
                array(
                    'label' =>  __('<i class="fa fa-clock"></i> Claim Listing Pending Notification', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_claim_pending_message'
                ),
                array(
                    'label'      => __('Enable claim listing pending notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user when claim listing was pending', 'listeo_core'),
                    'id'        => 'claim_pending_notification',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Claim listing pending notification email subject', 'listeo_core'),
                    'default'      => __('Your claim is pending', 'listeo_core'),
                    'id'        => 'claim_pending_notification_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Claimant (recipient):</strong> {user_name}, {user_mail}, {first_name}, {last_name}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Claim listing pending notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your claim for '{listing_name}' is pending. We will inform you about the decision soon.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'claim_pending_notification_email_content',
                    'type'      => 'editor',
                ),
                //Claim listing completed
                // Claim Completed Notifications
                array(
                    'label' =>  __('<i class="fa fa-flag-checkered"></i> Claim Listing Completed Notification', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_claim_completed_message'
                ),
                array(
                    'label'      => __('Enable claim listing completed notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user when claim listing was completed', 'listeo_core'),
                    'id'        => 'claim_completed_notification',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Claim listing completed notification email subject', 'listeo_core'),
                    'default'      => __('Your claim is completed', 'listeo_core'),
                    'id'        => 'claim_completed_notification_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Claimant (recipient):</strong> {user_name}, {user_mail}, {first_name}, {last_name}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Claim listing completed notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your claim for '{listing_name}' is completed. You can now manage this listing.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'claim_completed_notification_email_content',
                    'type'      => 'editor',
                ),

            )
        );

        $settings['stripe_connect'] = array(
            'title'                 => __('<i class="fa fa-cc-stripe"></i> Stripe Connect', 'listeo_core'),
            'fields'                => array(
                
                // Stripe Connect Configuration
                array(
                    'label'         => __('<i class="fa fa-cogs"></i> Stripe Connect Configuration', 'listeo_core'),
                    'description'   => __('Basic settings to enable and configure Stripe Connect split payment functionality', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'stripe_connect_config_block'
                ),
                array(
                    'label'      => __('Activate / Deactivate Stripe Connect feature', 'listeo_core'),
                    'description'      => __('Activate/Deactivate Stripe Connect  feature', 'listeo_core'),
                    'id'        => 'stripe_connect_activation', //each field id must be unique
                    'type'      => 'select',
                    'options'   => array(
                        'no' => esc_html__('Deactivate', 'listeo_core'),
                        'yes' => esc_html__('Activate', 'listeo_core')
                    ),
                    'default'       => 'no'
                ),
                
                // Setup Information & Requirements
                array(
                    'label'         => __('<i class="fa fa-info-circle"></i> Setup Information & Requirements', 'listeo_core'),
                    'description'   => __('Important information about Stripe Connect setup and WooCommerce integration requirements', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'stripe_connect_info_block'
                ),
                array(
                    'label' =>  __('Stripe Connect info', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_stripe',
                    'description' => sprintf(
                        __('To use Stripe Connect Split payment feature you need to use official WooCommerce Stripe Payment Gateway. Please check our %s for more details', 'listeo_core'),
                        '<a href="https://www.docs.purethemes.net/listeo/knowledge-base/stripe-connect-support/" target="_blank">' . __('documentation', 'listeo_core') . '</a>'
                    ),
                ),
                
                // Account Configuration
                array(
                    'label'         => __('<i class="fa fa-user-cog"></i> Account Configuration', 'listeo_core'),
                    'description'   => __('Configure Stripe account type and operating mode for your marketplace', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'stripe_account_config_block'
                ),
                array(
                    'label'      => __('Account type creation ', 'listeo_core'),
                    'description'      => sprintf(
                        __('Choose from express or standard account. %s', 'listeo_core'),
                        '<a href="https://stripe.com/docs/connect/accounts" target="_blank">' . __('Learn about account types', 'listeo_core') . '</a>'
                    ),
                    'id'        => 'stripe_connect_account_type',
                    'type'      => 'radio',
                    'options'   => array(
                        'express' => 'Express',
                        'standard' => 'Standard'
                    ),
                    'default'   => 'express'
                ),

                // test/live mode option:
                array(
                    'label'      => __('Stripe Connect mode', 'listeo_core'),
                    'description'      => __('Select the Environment', 'listeo_core'),
                    'id'        => 'stripe_connect_mode', //each field id must be unique
                    'type'      => 'select',
                    'options'   => array(
                        'test' => esc_html__('Test', 'listeo_core'),
                        'live' => esc_html__('Live', 'listeo_core')
                    ),
                    'default'       => 'test'
                ),

                // Test Mode API Keys
                array(
                    'label'         => __('<i class="fa fa-flask"></i> Test Mode API Keys', 'listeo_core'),
                    'description'   => __('Configure your Stripe Connect test environment API keys and webhook settings', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'stripe_test_keys_block'
                ),

                //Publishable key
                array(
                    'label'      => __('Stripe Connect Test mode Publishable key', 'listeo_core'),
                    'id'        => 'stripe_connect_test_public_key', //each field id must be unique
                    'type'      => 'textarea',
                    'description'      => __('Stripe Connect Test mode Publishable key', 'listeo_core'),
                ),
                array(
                    'label'      => __('Stripe Connect Test mode Secret key', 'listeo_core'),
                    'id'        => 'stripe_connect_test_secret_key', //each field id must be unique
                    'type'      => 'textarea',
                    'description'      => __('Stripe Connect Test mode Secret key', 'listeo_core'),
                ),
                //webhook_secret
                array(
                    'label'      => __('Stripe Connect Test mode Webhook Secret', 'listeo_core'),
                    'id'        => 'stripe_connect_test_webhook_secret', //each field id must be unique
                    'type'      => 'textarea',
                    'description'      => __('Stripe Connect Test mode Webhook Secret', 'listeo_core'),
                ),



                array(
                    'label'      => __('Stripe Connect Test mode Client ID ', 'listeo_core'),
                    'id'        => 'stripe_connect_test_client_id', //each field id must be unique
                    'type'      => 'text',
                    'description'      => sprintf(
                        __('Stripe Connect Test mode Client ID, get it from %s → Onboarding options → OAuth', 'listeo_core'),
                        '<a href="https://dashboard.stripe.com/test/settings/connect" target="_blank">Stripe Dashboard (Test mode)</a>'
                    ),
                ),

                // Live Mode API Keys
                array(
                    'label'         => __('<i class="fa fa-globe"></i> Live Mode API Keys', 'listeo_core'),
                    'description'   => __('Configure your Stripe Connect production environment API keys and settings', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'stripe_live_keys_block'
                ),
                //publishable key
                array(
                    'label'      => __('Stripe Connect Live mode Publishable key', 'listeo_core'),
                    'id'        => 'stripe_connect_live_public_key', //each field id must be unique
                    'type'      => 'textarea',
                    'description'      => __('Stripe Connect Live mode Publishable key', 'listeo_core'),
                ),
                array(
                    'label'      => __('Stripe Connect Live mode Secret key', 'listeo_core'),
                    'id'        => 'stripe_connect_live_secret_key', //each field id must be unique
                    'type'      => 'textarea',
                    'description'      => __('Stripe Connect Live mode Secret key', 'listeo_core'),
                ),
                array(
                    'label'      => __('Stripe Connect Live mode Client ID ', 'listeo_core'),
                    'id'        => 'stripe_connect_live_client_id', //each field id must be unique
                    'type'      => 'text',
                    'description'      => sprintf(
                        __('Stripe Connect Live mode Client ID, get it from %s', 'listeo_core'),
                        '<a href="https://dashboard.stripe.com/settings/connect" target="_blank">Stripe Dashboard (Live mode)</a>'
                    ),
                ),
                array(
                    'label'      => __('Stripe Connect Live mode Webhook Secret', 'listeo_core'),
                    'id'        => 'stripe_connect_live_webhook_secret', //each field id must be unique
                    'type'      => 'textarea',
                    'description'      => __('Stripe Connect Live mode Webhook Secret', 'listeo_core'),
                ),


            )
        );
        
        // PayPal Payout settings commented out - broken functionality
        /*
        $settings['paypal_payout'] = array(
            'title'                 => __('<i class="fa fa-paypal"></i> PayPal Payout', 'listeo_core'),
            // 'description'           => __( 'Settings for the PayPal Payout', 'listeo_core' ),
            'fields'                => array(
                
                // PayPal Payout Configuration
                array(
                    'label'         => __('<i class="fa fa-cogs"></i> PayPal Payout Configuration', 'listeo_core'),
                    'description'   => __('Basic settings to enable and configure PayPal Payout functionality for commission payments', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'paypal_payout_config_block'
                ),
                array(
                    'label'      => __('Activate / Deactivate PayOut feature', 'listeo_core'),
                    'description'      => __('Activate/Deactivate PayPal Payout feature', 'listeo_core'),
                    'id'        => 'payout_activation', //each field id must be unique
                    'type'      => 'select',
                    'options'   => array(
                        'no' => esc_html__('Deactivate', 'listeo_core'),
                        'yes' => esc_html__('Activate', 'listeo_core')
                    ),
                    'default'       => 'no'
                ),
                array(
                    'label'      => __('Live/Sandbox', 'listeo_core'),
                    'description'      => __('Select the Environment', 'listeo_core'),
                    'id'        => 'payout_environment', //each field id must be unique
                    'type'      => 'select',
                    'options'   => array(
                        'sandbox' => esc_html__('Sandbox / Testing', 'listeo_core'),
                        'live' => esc_html__('Live / Production', 'listeo_core')
                    ),
                    'default'       => 'sandbox'
                ),

                // Sandbox API Credentials
                array(
                    'label'         => __('<i class="fa fa-flask"></i> Sandbox API Credentials', 'listeo_core'),
                    'description'   => __('Configure your PayPal sandbox environment API credentials for testing', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'paypal_sandbox_credentials_block'
                ),
                array(
                    'label'      => __('PayPal Client ID', 'listeo_core'),
                    'id'        => 'payout_sandbox_client_id', //each field id must be unique
                    'type'      => 'text',
                    'description'      => __('PayPal Client ID for Sand box', 'listeo_core'),
                ),
                array(
                    'label'      => __('PayPal Client Secret', 'listeo_core'),
                    'id'        => 'payout_sandbox_client_secret', //each field id must be unique
                    'type'      => 'password',
                    'description'      => __('PayPal Client Secret for Sand box', 'listeo_core'),
                ),

                // Live API Credentials
                array(
                    'label'         => __('<i class="fa fa-globe"></i> Live API Credentials', 'listeo_core'),
                    'description'   => __('Configure your PayPal production environment API credentials for live payouts', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'paypal_live_credentials_block'
                ),
                array(
                    'label'      => __('PayPal Client ID', 'listeo_core'),
                    'id'        => 'payout_live_client_id', //each field id must be unique
                    'type'      => 'text',
                    'description'      => __('PayPal Client ID for Production / Live Environment', 'listeo_core'),
                ),
                array(
                    'label'      => __('PayPal Client Secret', 'listeo_core'),
                    'id'        => 'payout_live_client_secret', //each field id must be unique
                    'type'      => 'password',
                    'description'      => __('PayPal Client Secret for Production / Live Environment', 'listeo_core'),
                ),

                // Email & Transaction Settings
                array(
                    'label'         => __('<i class="fa fa-envelope"></i> Email & Transaction Settings', 'listeo_core'),
                    'description'   => __('Configure default email templates and transaction notes for PayPal payouts', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'paypal_email_settings_block'
                ),

                array(
                    'label'      => __('Email Subject', 'listeo_core'),
                    'description'      => __('Default Email Subject', 'listeo_core'),
                    'id'        => 'payout_email_subject', //each field id must be unique
                    'type'      => 'textarea',
                    'default'   => 'Here is your commission.'
                ),
                array(
                    'label'      => __('Email Message', 'listeo_core'),
                    'description'      => __('Default Email Message', 'listeo_core'),
                    'id'        => 'payout_email_message', //each field id must be unique
                    'type'      => 'textarea',
                    'default'   => 'You have received a payout (commission)! Thanks for using our service!'
                ),
                array(
                    'label'      => __('Transaction Note', 'listeo_core'),
                    'description'      => __('Any note that you want to add', 'listeo_core'),
                    'id'        => 'payout_trx_note', //each field id must be unique
                    'type'      => 'textarea',
                    'default'   => ''
                ),
            )
        );
        */

        $settings['pages'] = array(
            'title'                 => __('<i class="fa fa-layer-group"></i> Pages', 'listeo_core'),
            // 'description'           => __( 'Set all pages required in Listeo.', 'listeo_core' ),
            'fields'                => array(
                
                // Page Configuration
                array(
                    'label'         => __('<i class="fa fa-file-alt"></i> Page Configuration', 'listeo_core'),
                    'description'   => __('Configure all WordPress pages required for Listeo functionality with their corresponding shortcodes', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'page_config_block'
                ),
                array(
                    'id'            => 'dashboard_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Dashboard Page', 'listeo_core'),
                    'description'   => __('Main Dashboard page for user, content: <mark>[listeo_dashboard]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'messages_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Messages Page', 'listeo_core'),
                    'description'   => __('Main page for user messages, content: <mark>[listeo_messages]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'bookings_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Bookings Page', 'listeo_core'),
                    'description'   => __('Page for owners to manage their bookings, content: <mark>[listeo_bookings]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'bookings_calendar_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Bookings Calendar View Page', 'listeo_core'),
                    'description'   => __('Page for owners to manage their bookings in the calendar, content: <mark>[listeo_calendar_view]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'user_bookings_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('My Bookings Page', 'listeo_core'),
                    'description'   => __('Page for guest to see their bookings,content: <mark>[listeo_my_bookings]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'bookings_user_calendar_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('User Bookings Calendar View Page', 'listeo_core'),
                    'description'   => __('Page for guest to view their bookings in the calendar, content: <mark>[listeo_user_calendar_view]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'booking_confirmation_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Booking confirmation', 'listeo_core'),
                    'description'   => __('Displays page for booking confirmation, content: <mark>[listeo_booking_confirmation]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'listings_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('My Listings Page', 'listeo_core'),
                    'description'   => __('Displays or listings added by user, content <mark>[listeo_my_listings]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'wallet_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Wallet Page', 'listeo_core'),
                    'description'   => __('Displays or owners earnings, content <mark>[listeo_wallet]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'reviews_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Reviews Page', 'listeo_core'),
                    'description'   => __('Displays reviews of user listings, content: <mark>[listeo_reviews]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'bookmarks_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Bookmarks Page', 'listeo_core'),
                    'description'   => __('Displays user bookmarks, content: <mark>[listeo_bookmarks]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'saved_searches_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Saved Searches Page', 'listeo_core'),
                    'description'   => __('Displays user saved searches with email alerts, content: <mark>[listeo_saved_searches]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'submit_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Submit Listing Page', 'listeo_core'),
                    'description'   => __('Displays submit listing page, content: <mark>[listeo_submit_listing]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'stats_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Statistics  Page', 'listeo_core'),
                    'description'   => __('Displays chart with listing statistics, content: <mark>[listeo_stats_full]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'ticket_check_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Ticket/Booking Verification Page', 'listeo_core'),
                    'description'   => __('Check if the QR code is valid <mark>[listeo_qr_check]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'profile_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('My Profile Page', 'listeo_core'),
                    'description'   => __('Displays user profile page, content: <mark>[listeo_my_account]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),

                array(
                    'label'          => __('Lost Password Page', 'listeo_core'),
                    'description'          => __('Select page that holds <mark>[listeo_lost_password]</mark> shortcode', 'listeo_core'),
                    'id'            =>  'lost_password_page',
                    'type'          => 'select',
                    'options'       => listeo_core_get_pages_options(),
                ),
                array(
                    'label'          => __('Reset Password Page', 'listeo_core'),
                    'description'          => __('Select page that holds <mark>[listeo_reset_password]</mark> shortcode', 'listeo_core'),
                    'id'            =>  'reset_password_page',
                    'type'          => 'select',
                    'options'       => listeo_core_get_pages_options(),
                ),
                array(
                    'id'            => 'ad_campaigns_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Ad Campaigns Manage Page', 'listeo_core'),
                    'description'   => __('Page to manage ads <mark>[listeo_ads]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),

                array(
                    'id'            => 'coupons_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('Coupons Manage Page', 'listeo_core'),
                    'description'   => __('Displays form to manage coupons <mark>[listeo_coupons]</mark>', 'listeo_core'),
                    'type'          => 'select',
                ),
                array(
                    'id'            => 'orders_page',
                    'label'         => __('WooCommerce Orders Page', 'listeo_core'),
                    'description'   => __('Displays orders page in dashboard menu', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'subscription_page',
                    'label'         => __('WooCommerce Subscription Page', 'listeo_core'),
                    'description'   => __('Displays subscription page in dashboard menu (requires WooCommerce Subscription plugin)', 'listeo_core'),
                    'type'          => 'checkbox',
                ),
                array(
                    'id'            => 'ical_page',
                    'options'       => listeo_core_get_pages_options(),
                    'label'         => __('iCal generator', 'listeo_core'),
                    'description'   => __('Used to generate iCal output', 'listeo_core'),
                    'type'          => 'select',
                ),

                //         array(
                //             'id'            => 'colour_picker',
                //             'label'         => __( 'Pick a colour', 'listeo_core' ),
                //             'description'   => __( 'This uses WordPress\' built-in colour picker - the option is stored as the colour\'s hex code.', 'listeo_core' ),
                //             'type'          => 'color',
                //             'default'       => '#21759B'
                //         ),
                // array(
                //     'id'            => 'an_image',
                //     'label'         => __( 'An Image' , 'listeo_core' ),
                //     'description'   => __( 'This will upload an image to your media library and store the attachment ID in the option field. Once you have uploaded an imge the thumbnail will display above these buttons.', 'listeo_core' ),
                //     'type'          => 'image',
                //     'default'       => '',
                //     'placeholder'   => ''
                // ),
                //         array(
                //             'id'            => 'multi_select_box',
                //             'label'         => __( 'A Multi-Select Box', 'listeo_core' ),
                //             'description'   => __( 'A standard multi-select box - the saved data is stored as an array.', 'listeo_core' ),
                //             'type'          => 'select_multi',
                //             'options'       => array( 'linux' => 'Linux', 'mac' => 'Mac', 'windows' => 'Windows' ),
                //             'default'       => array( 'linux' )
                //         )
            )
        );

        $settings['emails'] = array(
            'title'                 => __('<i class="fa fa-envelope"></i> Emails', 'listeo_core'),
            //'description'           => __( 'Email settings.', 'listeo_core' ),
            'fields'                => array(

                // Basic Email Configuration
                array(
                    'label'         => __('<i class="fa fa-cog"></i> Basic Email Configuration', 'listeo_core'),
                    'description'   => __('Configure basic email settings including sender name, email address, and logo', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'basic_email_config_block'
                ),
                array(
                    'label'  => __('"From name" in email', 'listeo_core'),
                    'description'  => __('The name from who the email is received, by default it is your site name.', 'listeo_core'),
                    'id'    => 'emails_name',
                    'default' =>  get_bloginfo('name'),
                    'type'  => 'text',
                ),

                array(
                    'label'  => __('"From" email ', 'listeo_core'),
                    'description'  => __('This will act as the "from" and "reply-to" address. This emails should match your domain address', 'listeo_core'),
                    'id'    => 'emails_from_email',
                    'default' =>  get_bloginfo('admin_email'),
                    'type'  => 'text',
                ),
                array(
                    'id'            => 'email_logo',
                    'label'         => __('Logo for emails', 'listeo_core'),
                    'description'   => __('Set here logo for emails, if nothing is set emails will be using default site logo', 'listeo_core'),
                    'type'          => 'image',
                    'default'       => '',
                    'placeholder'   => ''
                ),

                // Authentication & User Emails
                array(
                    'label'         => __('<i class="fa fa-user-plus"></i> Authentication & User Emails', 'listeo_core'),
                    'description'   => __('Configure OTP authentication and welcome emails for new user registrations', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'auth_user_emails_block'
                ),
                array(
                    'label'      => __('OTP Email Subject', 'listeo_core'),
                    'default'      => __('Authenticate Your Email Address', 'listeo_core'),
                    'id'        => 'otp_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('OTP Email Content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your OTP code is {otp}.<br>
                    <br>
                    Thank you.
                    <br>")),
                    'id'        => 'otp_email_content',
                    'type'      => 'editor',
                ),
                array(
                    'label'      => __('Disable Welcome email to user (enabled by default)', 'listeo_core'),
                    'description'      => __('Check this checkbox to disable sending emails to new users', 'listeo_core'),
                    'id'        => 'welcome_email_disable',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Welcome Email Subject', 'listeo_core'),
                    'default'      => __('Welcome to {site_name}', 'listeo_core'),
                    'id'        => 'listing_welcome_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Welcome Email Content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
Welcome to our website.<br>
<ul>
<li>Username: {login}</li>
<li>Password: {password}</li>
</ul>
<br>
Thank you.
<br>")),
                    'id'        => 'listing_welcome_email_content',
                    'type'      => 'editor',
                ),

                // Listing Management Emails
                array(
                    'label'         => __('<i class="fa fa-list-alt"></i> Listing Management Emails', 'listeo_core'),
                    'description'   => __('Configure email notifications for listing publication, submission, and expiration events', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'listing_management_emails_block'
                ),
                array(
                    'label'      => __('Enable listing published notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to listing authors', 'listeo_core'),
                    'id'        => 'listing_published_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Published notification Email Subject', 'listeo_core'),
                    'default'      => __('Your listing was published - {listing_name}', 'listeo_core'),
                    'id'        => 'listing_published_email_subject',
                    'type'      => 'text',

                ),
                array(
                    'label'      => __('Published notification Email Content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
We are pleased to inform you that your submission '{listing_name}' was just published on our website.<br>
<br>
Thank you.
<br>")),
                    'id'        => 'listing_published_email_content',
                    'type'      => 'editor',
                ),
                array(
                    'label'      => __('Enable new listing notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to listing authors', 'listeo_core'),
                    'id'        => 'listing_new_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('New listing notification email subject', 'listeo_core'),
                    'default'      => __('Thank you for adding a listing', 'listeo_core'),
                    'id'        => 'listing_new_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('New listing notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Thank you for submitting your listing '{listing_name}'.<br>
                    <br>")),
                    'id'        => 'listing_new_email_content',
                    'type'      => 'editor',
                ),
                array(
                    'label'      => __('Enable expired listing notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to listing authors', 'listeo_core'),
                    'id'        => 'listing_expired_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Expired listing notification email subject', 'listeo_core'),
                    'default'      => __('Your listing has expired - {listing_name}', 'listeo_core'),
                    'id'        => 'listing_expired_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Expired listing notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    We'd like you to inform you that your listing '{listing_name}' has expired and is no longer visible on our website. You can renew it in your account.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'listing_expired_email_content',
                    'type'      => 'editor',
                ),
                array(
                    'label'      => __('Enable Expiring soon listing notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to listing authors', 'listeo_core'),
                    'id'        => 'listing_expiring_soon_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Expiring soon listing notification email subject', 'listeo_core'),
                    'default'      => __('Your listing is expiring in 5 days - {listing_name}', 'listeo_core'),
                    'id'        => 'listing_expiring_soon_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Expiring soon listing notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    We'd like you to inform you that your listing '{listing_name}' is expiring in 5 days.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'listing_expiring_soon_email_content',
                    'type'      => 'editor',
                ),

			array(
				'label'      => __('Enable Listing Rejected notification email', 'listeo_core'),
				'description'      => __('Check this checkbox to enable sending emails to listing owners when their listing is rejected', 'listeo_core'),
				'id'        => 'listing_rejected_email',
				'type'      => 'checkbox',
			),
			array(
				'label'      => __('Listing Rejected notification email subject', 'listeo_core'),
				'default'      => __('Your listing has been rejected - {listing_name}', 'listeo_core'),
				'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
					. __('<strong>Listing owner:</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
					. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_dashboard_url}', 'listeo_core') . '<br>'
					. __('<strong>Other:</strong> {rejection_reason}, {site_name}, {site_url}', 'listeo_core'),
				'id'        => 'listing_rejected_email_subject',
				'type'      => 'text',
			),
			array(
				'label'      => __('Listing Rejected notification email content', 'listeo_core'),
				'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
				We regret to inform you that your listing '{listing_name}' has been rejected.<br>
				<br>
				<strong>Rejection Reason:</strong> {rejection_reason}<br>
				<br>
				Please review the feedback and make the necessary changes. You can edit and resubmit your listing from your dashboard.<br>
				<br>
				<a href='{listing_dashboard_url}'>View Your Listings</a><br>
				<br>
				If you have any questions, please contact our support team.<br>
				<br>
				Thank you
				<br>")),
				'id'        => 'listing_rejected_email_content',
				'type'      => 'editor',
			),

                // Booking Management Emails
                array(
                    'label'         => __('<i class="fa fa-calendar-check"></i> Booking Management Emails', 'listeo_core'),
                    'description'   => __('Configure all email notifications related to booking requests, confirmations, payments, and cancellations', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'booking_management_emails_block'
                ),
                array(
                    'label'      => __('Enable Booking confirmation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to users after they request booking', 'listeo_core'),
                    'id'        => 'booking_user_waiting_approval_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'booking_user_waiting_approval_email_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Booking confirmation notification email subject', 'listeo_core'),
                    'default'      => __('Thank you for your booking - {listing_name}', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'booking_user_waiting_approval_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking confirmation notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Thank you for your booking request on {listing_name} for {dates}. Please wait for confirmation and further instructions.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'booking_user_waiting_approval_email_content',
                    'type'      => 'editor',
                ),
                /*----------------*/
                array(

                    'label' =>  __('<i class="fa fa-bolt"></i> Booking confirmation to user - Instant Booking', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_instant_booking_confirmation'
                ),
                array(
                    'label'      => __('Enable Instant Booking confirmation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to users after they request booking', 'listeo_core'),
                    'id'        => 'instant_booking_user_waiting_approval_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'instant_booking_user_waiting_approval_email_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Instant Booking confirmation notification email subject', 'listeo_core'),
                    'default'      => __('Thank you for your booking - {listing_name}', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Payment:</strong> {payment_url}, {expiration}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'instant_booking_user_waiting_approval_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Instant Booking confirmation notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Thank you for your booking request on {listing_name} for {dates}. Please wait for confirmation and further instructions.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'instant_booking_user_waiting_approval_email_content',
                    'type'      => 'editor',
                ),

                /*----------------*/
                array(

                    'label' =>  __('Booking request notification to owner ', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_booking_notification_owner'
                ),
                array(
                    'label'      => __('Enable Booking request notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to owners when new booking was requested', 'listeo_core'),
                    'id'        => 'booking_owner_new_booking_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'booking_owner_new_booking_email_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking request notification email subject', 'listeo_core'),
                    'default'      => __('There is a new booking request for {listing_name}', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Listing owner (recipient):</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
						. __('<strong>Customer who booked:</strong> {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'booking_owner_new_booking_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking request notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    There's a new booking request on '{listing_name}' for {dates}. Go to your Bookings Dashboard to accept or reject it.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'booking_owner_new_booking_email_content',
                    'type'      => 'editor',
                ),


                array(

                    'label' =>  __('Instant Booking notification to owner ', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_instant_booking_notification_owner'
                ),
                array(
                    'label'      => __('Enable Instant Booking notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to owners when new instant booking was made', 'listeo_core'),
                    'id'        => 'booking_instant_owner_new_booking_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'booking_instant_owner_new_booking_email_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Instant Booking notification email subject', 'listeo_core'),
                    'default'      => __('There is a new instant booking for {listing_name}', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Listing owner (recipient):</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
						. __('<strong>Customer who booked:</strong> {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Payment:</strong> {payment_url}, {expiration}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'booking_instant_owner_new_booking_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Instant Booking notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    There's a new booking  on '{listing_name}' for {dates}.
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'booking_instant_owner_new_booking_email_content',
                    'type'      => 'editor',
                ),

                /*----------------*/
                array(

                    'label' =>  __('Free Booking confirmation to user', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_free_booking_notification_user'
                ),
                array(
                    'label'      => __('Enable Booking confirmation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to users when booking was accepted by owner', 'listeo_core'),
                    'id'        => 'free_booking_confirmation',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'free_booking_confirmation_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking request notification email subject', 'listeo_core'),
                    'default'      => __('Your booking request was approved {listing_name}', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'free_booking_confirmation_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking request notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your booking request on '{listing_name}' for {dates} was approved. See you soon!.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'free_booking_confirmation_email_content',
                    'type'      => 'editor',
                ),


                /*----------------*/
                /*----------------*/
                array(

                    'label' =>  __('Booking Confirmation to user - pay in cash only', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_cash_booking_notification_user'
                ),
                array(
                    'label'      => __('Enable Booking pay in cash confirmation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to users when booking was accepted by owner and requires payment in cash', 'listeo_core'),
                    'id'        => 'mail_to_user_pay_cash_confirmed',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'mail_to_user_pay_cash_confirmed_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking confirmation "pay with cash" notification email subject', 'listeo_core'),
                    'default'      => __('Your booking request was approved {listing_name}', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'mail_to_user_pay_cash_confirmed_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking confirmation "pay with cash" notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your booking request on '{listing_name}' for {dates} was approved. See you soon!.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'mail_to_user_pay_cash_confirmed_email_content',
                    'type'      => 'editor',
                ),


                /*----------------*/
                array(

                    'label' =>  __('Booking approved - payment needed - notification to user', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_pay_booking_notification_owner'
                ),
                array(
                    'label'      => __('Enable Booking confirmation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to users when booking was accepted by owner and they need to pay', 'listeo_core'),
                    'id'        => 'pay_booking_confirmation_user',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'pay_booking_confirmation_user_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking request notification email subject', 'listeo_core'),
                    'default'      => __('Your booking request was approved {listing_name}, please pay', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Payment:</strong> {payment_url}, {expiration}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'pay_booking_confirmation_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking request notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your booking request on '{listing_name}' for {dates} was approved. Here's the payment link {payment_url}, the booking will expire after {expiration} if not paid!.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'pay_booking_confirmation_email_content',
                    'type'      => 'editor',
                ),

                /*----------------*/
                array(

                    'label' =>  __('Booking paid notification to owner', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_pay_booking_confirmation_owner'
                ),
                array(
                    'label'      => __('Enable Booking paid confirmation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to owner when booking was paid by use', 'listeo_core'),
                    'id'        => 'paid_booking_confirmation',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'paid_booking_confirmation_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking paid notification email subject', 'listeo_core'),
                    'default'      => __('Your booking was paid by user - {listing_name}', 'listeo_core'),
                    'id'        => 'paid_booking_confirmation_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Listing owner (recipient):</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
						. __('<strong>Customer who booked:</strong> {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking paid notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    The booking for '{listing_name}' on {dates} was paid by user.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'paid_booking_confirmation_email_content',
                    'type'      => 'editor',
                ),
                /*----------------*/
                array(

                    'label' =>  __('Booking paid confirmation to user', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_pay_booking_confirmation_user'
                ),
                array(
                    'label'      => __('Enable Booking paid confirmation email to user', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user with confirmation of payment', 'listeo_core'),
                    'id'        => 'user_paid_booking_confirmation',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'user_paid_booking_confirmation_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking paid confirmation email subject', 'listeo_core'),
                    'default'      => __('Your booking was paid {listing_name}', 'listeo_core'),
                    'id'        => 'user_paid_booking_confirmation_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking paid confirmation email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Here are details about your paid booking for '{listing_name}' on {dates}.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'user_paid_booking_confirmation_email_content',
                    'type'      => 'editor',
                ),

                // booking cancelled
                array(

                    'label' =>  __('Booking cancelled notification to user ', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_booking_cancellation_user'
                ),
                array(
                    'label'      => __('Enable Booking cancellation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user when booking is cancelled', 'listeo_core'),
                    'id'        => 'booking_user_cancallation_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'booking_user_cancallation_email_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking cancelled notification email subject', 'listeo_core'),
                    'default'      => __('Your booking request for {listing_name} was cancelled', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'booking_user_cancellation_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking cancelled notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your booking '{listing_name}' for {dates} was cancelled.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'booking_user_cancellation_email_content',
                    'type'      => 'editor',
                ),
                // booking owner cancelled
                array(

                    'label' =>  __('Booking cancelled notification to owner ', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_booking_cancellation_owner'
                ),
                array(
                    'label'      => __('Enable Booking cancellation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to owner when booking is cancelled', 'listeo_core'),
                    'id'        => 'booking_owner_cancallation_email',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'booking_owner_cancallation_email_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking cancelled notification email subject', 'listeo_core'),
                    'default'      => __('Booking request for {listing_name} was cancelled', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Listing owner (recipient):</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'booking_owner_cancellation_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking cancelled notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Your booking '{listing_name}' for {dates} was cancelled.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'booking_owner_cancellation_email_content',
                    'type'      => 'editor',
                ),


                // // booking reminder
                array(

                    'label' =>  __('Booking reminder to user', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_booking_reminder_user'
                ),
                array(
                    'label'      => __('Enable Booking reminder email to user', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user abour upcoming booking 24 hours before the date', 'listeo_core'),
                    'id'        => 'user_booking_reminder_status',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'user_booking_reminder_status_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Booking reminder email subject', 'listeo_core'),
                    'default'      => __('Your booking is coming up {listing_name}', 'listeo_core'),
                    'id'        => 'user_booking_reminder_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Booking reminder email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    Just a friendly reminder about your upcoming booking in '{listing_name}' on {dates}.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'user_booking_reminder_email_content',
                    'type'      => 'editor',
                ),

                // Zoom Meeting Invitation
                array(
                    'label'      =>  __('<i class="fa fa-video-camera"></i> Zoom Meeting Invitation', 'listeo_core'),
                    'type'      => 'title',
                    'id'        => 'header_zoom_meeting_invitation'
                ),
                array(
                    'label'      => __('Enable Zoom meeting invitation email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending Zoom meeting details to users when a meeting is created for their booking', 'listeo_core'),
                    'id'        => 'listeo_zoom_meeting_invitation_email_enabled',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Zoom meeting invitation email subject', 'listeo_core'),
                    'default'      => __('Your Zoom Meeting for {listing_name}', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_id}, {dates}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}', 'listeo_core') . '<br>'
						. __('<strong>Zoom:</strong> {meeting_date}, {meeting_topic}, {zoom_meeting_id}, {zoom_password}, {zoom_join_url}, {zoom_start_url}', 'listeo_core'),
                    'id'        => 'listeo_zoom_meeting_invitation_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Zoom meeting invitation email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "
                    <p>Hi {client_first_name},</p>
                    <p>Great news! Your Zoom meeting for <strong>{listing_name}</strong> is ready.</p>
                    <h3>Meeting Details</h3>
                    <p><strong>Date & Time:</strong> {meeting_date}</p>
                    <p><strong>Topic:</strong> {meeting_topic}</p>
                    <p><strong>Meeting ID:</strong> {zoom_meeting_id}</p>
                    <p><strong>Password:</strong> {zoom_password}</p>
                    <div style=\"margin: 30px 0; text-align: center;\">
                        <a href=\"{zoom_join_url}\" style=\"background-color: #2997F8; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;\">Join Zoom Meeting</a>
                    </div>
                    <p style=\"color: #666; font-size: 12px;\">Or copy and paste this URL into your browser:<br>{zoom_join_url}</p>
                    <p>See you at the meeting!</p>
                    ")),
                    'id'        => 'listeo_zoom_meeting_invitation_email_content',
                    'type'      => 'editor',
                ),

                // Reviews & Communication Emails
                array(
                    'label'         => __('<i class="fa fa-comments"></i> Reviews & Communication Emails', 'listeo_core'),
                    'description'   => __('Configure email notifications for reviews, messages, and user communications', 'listeo_core'),
                    'type'          => 'title',
                    'id'            => 'reviews_communication_emails_block'
                ),
                array(
                    'label'      => __('Enable notification about new review', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to listing authors', 'listeo_core'),
                    'id'        => 'listing_new_review_mail',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('New review notification email subject', 'listeo_core'),
                    'default'      => __('There is new review on your listing', 'listeo_core'),
                    'id'        => 'listing_new_review_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('New review notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    There's new review added to your listing '{listing_name}'.<br>
                    <br>")),
                    'id'        => 'listing_new_review_email_content',
                    'type'      => 'editor',
                ),

                array(

                    'label'      =>  __('User Review reminder after booking:', 'listeo_core'),
                    'type'      => 'title',
                    'id'        => 'header_remind_review'
                ),
                array(
                    'label'      => __('Enable reminder about reviewing listing', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to listing user asking him to review after booking', 'listeo_core'),
                    'id'        => 'listing_remind_review_mail',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('Send admin copy (BCC)', 'listeo_core'),
                    'description'      => __('Send a copy of this email to the site administrator', 'listeo_core'),
                    'id'        => 'listing_remind_review_mail_send_admin_copy',
                    'type'      => 'checkbox',
                ),
                
                array(
                    'label'      => __('Review reminder notification email subject', 'listeo_core'),
                    'default'      => __('How was your stay?', 'listeo_core'),
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Customer (recipient):</strong> {user_name}, {user_mail}, {client_first_name}, {client_last_name}, {client_email}, {client_phone}', 'listeo_core') . '<br>'
						. __('<strong>Booking:</strong> {booking_date}, {dates}, {details}, {tickets}, {adults}, {children}, {service}, {user_message}, {price}', 'listeo_core') . '<br>'
						. __('<strong>Resource (Booking Plus):</strong> {resource_title}, {resource_subtitle}, {resource_image}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}, {listing_phone}, {listing_email}', 'listeo_core') . '<br>'
						. __('<strong>Billing:</strong> {billing_address}, {billing_postcode}, {billing_city}, {billing_country}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'id'        => 'listing_remind_review_email_subject',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Review notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    thank you for doing business with us. Can you take 1 minute to leave a review about your experience with us? Just go here: {listing_url}. Thanks for your help!
                    <br>")),
                    'id'        => 'listing_remind_review_email_content',
                    'type'      => 'editor',
                ),

                /* Saved Search Alerts Emails */
                array(
                    'label' =>  __('<i class="fa fa-bell"></i> Saved Search Alerts Emails', 'listeo_core'),
                    'description'   => __('Configure email templates for saved search alerts', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_saved_search_alerts'
                ),
                array(
                    'label'      => __('Saved search alert email subject', 'listeo_core'),
                    'default'      => __('New listings matching your saved searches!', 'listeo_core'),
                    'id'        => 'saved_search_email_subject',
                    'description' => '<br>' . __('Available tags:') . ' {user_name}, {match_count}, {search_count}, {site_name}',
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('Saved search alert email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br><br>
We found {match_count} new listing(s) matching your saved searches!<br><br>
{listings}<br>
<a href=\"{manage_url}\">Manage your saved searches</a><br><br>
Best regards,<br>
{site_name}")),
                    'id'        => 'saved_search_email_content',
                    'description' => '<br>' . __('Available tags:') . ' {user_name}, {match_count}, {search_count}, {listings}, {manage_url}, {site_name}',
                    'type'      => 'editor',
                ),

                /*New message in conversation*/
                array(

                    'label' =>  __('<i class="fa fa-comments"></i> New conversation', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_new_converstation'
                ),
                array(
                    'label'      => __('Enable new conversation notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user when there was new conversation started', 'listeo_core'),
                    'id'        => 'new_conversation_notification',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('New conversation notification email subject', 'listeo_core'),
                    'default'      => __('You got new conversation', 'listeo_core'),
                    'id'        => 'new_conversation_notification_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Recipient:</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
						. __('<strong>Message:</strong> {sender} (person who sent the message), {conversation_url}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('New conversation notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    There's a new conversation waiting for your on {site_name}.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'new_conversation_notification_email_content',
                    'type'      => 'editor',
                ),

                /*New message in conversation*/
                array(

                    'label' =>  __('<i class="fa fa-envelope-open"></i> New message', 'listeo_core'),
                    'type' => 'title',
                    'id'   => 'header_new_message'
                ),
                array(
                    'label'      => __('Enable new message notification email', 'listeo_core'),
                    'description'      => __('Check this checkbox to enable sending emails to user when there was new message send', 'listeo_core'),
                    'id'        => 'new_message_notification',
                    'type'      => 'checkbox',
                ),
                array(
                    'label'      => __('New message notification email subject', 'listeo_core'),
                    'default'      => __('You got new message', 'listeo_core'),
                    'id'        => 'new_message_notification_email_subject',
                    'description' => '<br>' . __('Available tags:', 'listeo_core') . '<br>'
						. __('<strong>Recipient:</strong> {user_name}, {user_mail}', 'listeo_core') . '<br>'
						. __('<strong>Message:</strong> {sender} (person who sent the message), {conversation_url}', 'listeo_core') . '<br>'
						. __('<strong>Listing:</strong> {listing_name}, {listing_url}, {listing_address}', 'listeo_core') . '<br>'
						. __('<strong>Other:</strong> {site_name}, {site_url}', 'listeo_core'),
                    'type'      => 'text',
                ),
                array(
                    'label'      => __('New message notification email content', 'listeo_core'),
                    'default'      => trim(preg_replace('/\t+/', '', "Hi {user_name},<br>
                    There's a new message waiting for your on {site_name}.<br>
                    <br>
                    Thank you
                    <br>")),
                    'id'        => 'new_message_notification_email_content',
                    'type'      => 'editor',
                ),


               
                





            ),
        );

        // Listing Types management moved to Listeo Editor menu

        $settings = apply_filters($this->_token . '_settings_fields', $settings);

        return $settings;
    }

    /**
     * Register plugin settings
     * @return void
     */
    public function register_settings()
    {
        if (is_array($this->settings)) {

            // Check posted/selected tab
            $current_section = '';
            if (isset($_POST['tab']) && $_POST['tab']) {
                $current_section = sanitize_key($_POST['tab']);
            } else {
                if (isset($_GET['tab']) && $_GET['tab']) {
                    $current_section = sanitize_key($_GET['tab']);
                }
            }

            foreach ($this->settings as $section => $data) {

                if ($current_section && $current_section != $section) continue;

                // Add section to page
                add_settings_section($section, $data['title'], array($this, 'settings_section'), $this->_token . '_settings');

                foreach ($data['fields'] as $field) {

                    // Validation callback for field
                    $validation = '';
                    if (isset($field['callback'])) {
                        $validation = $field['callback'];
                    }

                    // Register field
                    $option_name = $this->base . $field['id'];

                    register_setting($this->_token . '_settings', $option_name, $validation);

                    // Add field to page
                    $field_label = isset($field['label']) ? $field['label'] : '';
                    add_settings_field($field['id'], $field_label, array($this, 'display_field'), $this->_token . '_settings', $section, array('field' => $field, 'class' => 'listeo_map_settings ' . $field['id'],  'prefix' => $this->base));
                }

                if (!$current_section) break;
            }
        }
    }

    public function settings_section($section)
    {
        if (isset($this->settings[$section['id']]['description'])) {
            $html = '' . $this->settings[$section['id']]['description'] . '' . "\n";
            echo $html;
        }
    }

    /**
     * Load settings page content
     * @return void
     */
    public function settings_page()
    {

        // Build page HTML with modern sidebar design
        //$html = '<h1>Listeo Core ' . __('Settings', 'listeo_core') . '</h1>' . "\n";
        $html = '<div class=" listeo-modern-admin" id="' . $this->_token . '_settings">' . "\n";
        $html .= '<div class="lc-admin-layout">' . "\n";
        
        $tab = '';
        if (isset($_GET['tab']) && $_GET['tab']) {
            // Sanitize tab parameter - only allow alphanumeric, dash, and underscore
            $tab = sanitize_key($_GET['tab']);
        }

        // Sidebar container wrapper for proper sticky positioning
        $html .= '<div class="lc-sidebar-container">' . "\n";
        
        // Build sidebar navigation
        $html .= '<aside class="lc-sidebar">' . "\n";
        
        // Sidebar header
        $html .= '<div class="lc-sidebar-header">' . "\n";
        $html .= '<div class="lc-sidebar-title">';
        $html .= '<svg class="lc-logo-svg" xmlns="http://www.w3.org/2000/svg" width="138.72" height="22.2" viewBox="0 0 138.72 22.2">';
        $html .= '<defs><style>.cls-1{fill:#222;}.cls-2{fill:#222;}</style></defs>';
        $html .= '<g transform="translate(-81.629 -45.374)">';
        $html .= '<path class="cls-1" d="M1.26-7.86a8.554,8.554,0,0,1,.57-3.12,7.623,7.623,0,0,1,1.605-2.55A7.7,7.7,0,0,1,5.88-15.24a7.488,7.488,0,0,1,3.09-.63,7.136,7.136,0,0,1,3.735.96,6.254,6.254,0,0,1,2.445,2.67l-1.47.48a5.011,5.011,0,0,0-1.98-2.025,5.6,5.6,0,0,0-2.82-.735,5.679,5.679,0,0,0-2.4.51A5.994,5.994,0,0,0,4.545-12.6a6.567,6.567,0,0,0-1.29,2.115A7.283,7.283,0,0,0,2.79-7.86a7.12,7.12,0,0,0,.5,2.655,7.116,7.116,0,0,0,1.32,2.16A6.281,6.281,0,0,0,6.54-1.59a5.347,5.347,0,0,0,2.37.54,5.526,5.526,0,0,0,1.6-.24,6.929,6.929,0,0,0,1.47-.63,5.1,5.1,0,0,0,1.17-.915,2.865,2.865,0,0,0,.675-1.1l1.47.42A4.874,4.874,0,0,1,14.385-2,6.149,6.149,0,0,1,12.93-.78a7.453,7.453,0,0,1-1.845.8A7.605,7.605,0,0,1,9,.3,7.267,7.267,0,0,1,5.94-.345,7.56,7.56,0,0,1,3.5-2.115,8.59,8.59,0,0,1,1.86-4.71,8.254,8.254,0,0,1,1.26-7.86ZM24.78.3a7.167,7.167,0,0,1-3.045-.645A7.543,7.543,0,0,1,19.32-2.1a8.1,8.1,0,0,1-1.59-2.58,8.472,8.472,0,0,1-.57-3.09,8.36,8.36,0,0,1,.585-3.12,8.176,8.176,0,0,1,1.62-2.58,7.769,7.769,0,0,1,2.415-1.755,7,7,0,0,1,3-.645,7.1,7.1,0,0,1,3.015.645,7.717,7.717,0,0,1,2.43,1.755,8.176,8.176,0,0,1,1.62,2.58,8.36,8.36,0,0,1,.585,3.12,8.28,8.28,0,0,1-.585,3.09A8.319,8.319,0,0,1,30.24-2.1,7.5,7.5,0,0,1,27.81-.345,7.2,7.2,0,0,1,24.78.3ZM18.69-7.71a7,7,0,0,0,.48,2.595A6.783,6.783,0,0,0,20.475-3,6.19,6.19,0,0,0,22.41-1.575a5.474,5.474,0,0,0,2.37.525,5.474,5.474,0,0,0,2.37-.525A5.983,5.983,0,0,0,29.085-3.03,7.421,7.421,0,0,0,30.4-5.175,6.891,6.891,0,0,0,30.9-7.8,6.811,6.811,0,0,0,30.4-10.4a7.212,7.212,0,0,0-1.32-2.13A6.281,6.281,0,0,0,27.15-13.98a5.347,5.347,0,0,0-2.37-.54,5.282,5.282,0,0,0-2.34.54,6.35,6.35,0,0,0-1.95,1.47,6.926,6.926,0,0,0-1.32,2.175A7.233,7.233,0,0,0,18.69-7.71Zm24.36-6.54a6.228,6.228,0,0,0-3.69,1.245A6.234,6.234,0,0,0,37.17-9.75V0h-1.5V-15.6h1.41v3.84a7.532,7.532,0,0,1,2.145-2.685A5.494,5.494,0,0,1,42.09-15.63q.3,0,.54-.015t.42-.015ZM52.26.3A7.267,7.267,0,0,1,49.2-.345a7.56,7.56,0,0,1-2.445-1.77,8.36,8.36,0,0,1-1.62-2.6,8.44,8.44,0,0,1-.585-3.15,8.245,8.245,0,0,1,.585-3.1,8.176,8.176,0,0,1,1.6-2.55,7.415,7.415,0,0,1,2.43-1.725,7.342,7.342,0,0,1,3.03-.63,7.167,7.167,0,0,1,3.045.645,7.654,7.654,0,0,1,2.415,1.74,8.176,8.176,0,0,1,1.6,2.55A8.165,8.165,0,0,1,59.85-7.86q0,.15-.015.375T59.82-7.2H46.11A6.952,6.952,0,0,0,46.74-4.7a7.121,7.121,0,0,0,1.365,2A6.143,6.143,0,0,0,50.01-1.38a5.623,5.623,0,0,0,2.31.48,5.868,5.868,0,0,0,1.605-.225A6.418,6.418,0,0,0,55.4-1.74a5.272,5.272,0,0,0,1.2-.945A4.429,4.429,0,0,0,57.42-3.9l1.32.36A5.473,5.473,0,0,1,57.735-2a6.6,6.6,0,0,1-1.5,1.215,7.669,7.669,0,0,1-1.875.8A7.767,7.767,0,0,1,52.26.3Zm6.12-8.7a7.072,7.072,0,0,0-.615-2.52,6.728,6.728,0,0,0-1.35-1.965,6.055,6.055,0,0,0-1.9-1.29,5.779,5.779,0,0,0-2.31-.465,5.779,5.779,0,0,0-2.31.465,6.056,6.056,0,0,0-1.9,1.29,6.242,6.242,0,0,0-1.32,1.98A7.594,7.594,0,0,0,46.08-8.4Z" transform="translate(160.499 67.274)"/>';
        $html .= '<path class="cls-2" d="M2.13-21.9H4.77V-4.05a1.915,1.915,0,0,0,.465,1.365A1.7,1.7,0,0,0,6.54-2.19a3.5,3.5,0,0,0,.78-.1,6.374,6.374,0,0,0,.84-.255L8.58-.42A8.6,8.6,0,0,1,7.02.03,8.225,8.225,0,0,1,5.43.21,3.3,3.3,0,0,1,3.015-.66,3.253,3.253,0,0,1,2.13-3.09ZM10.62,0V-15.66h2.64V0Zm0-18.6v-3.3h2.64v3.3ZM23.1.3a12.148,12.148,0,0,1-3.72-.585A9.169,9.169,0,0,1,16.23-1.98l1.14-1.77a11.112,11.112,0,0,0,2.76,1.575,8.114,8.114,0,0,0,2.91.525,4.806,4.806,0,0,0,2.715-.675,2.164,2.164,0,0,0,1-1.9,1.717,1.717,0,0,0-.27-.975,2.272,2.272,0,0,0-.81-.705,6.618,6.618,0,0,0-1.38-.54q-.84-.24-1.95-.51-1.41-.36-2.43-.69a6.583,6.583,0,0,1-1.68-.78,2.731,2.731,0,0,1-.96-1.08,3.617,3.617,0,0,1-.3-1.56,4.538,4.538,0,0,1,.465-2.085,4.4,4.4,0,0,1,1.275-1.53,5.738,5.738,0,0,1,1.905-.93,8.485,8.485,0,0,1,2.355-.315,9.378,9.378,0,0,1,3.3.57,8.117,8.117,0,0,1,2.58,1.5l-1.2,1.59a6.738,6.738,0,0,0-2.22-1.305,7.505,7.505,0,0,0-2.52-.435,4.6,4.6,0,0,0-2.445.615,2.131,2.131,0,0,0-1,1.965,1.873,1.873,0,0,0,.2.9,1.7,1.7,0,0,0,.645.63,5.086,5.086,0,0,0,1.155.48q.7.21,1.695.45,1.56.36,2.715.735a7.815,7.815,0,0,1,1.92.885,3.413,3.413,0,0,1,1.14,1.2,3.476,3.476,0,0,1,.375,1.68A4.159,4.159,0,0,1,27.63-.99,7.255,7.255,0,0,1,23.1.3ZM40.56-.78q-.24.12-.63.285t-.885.33a7.744,7.744,0,0,1-1.08.27A6.867,6.867,0,0,1,36.75.21a4.007,4.007,0,0,1-2.49-.8A2.9,2.9,0,0,1,33.21-3.06V-13.59H31.08v-2.07h2.13v-5.22h2.64v5.22h3.51v2.07H35.85v9.72a1.637,1.637,0,0,0,.57,1.26,1.919,1.919,0,0,0,1.2.39,3.844,3.844,0,0,0,1.425-.255,6.2,6.2,0,0,0,.885-.4ZM49.98.3a7.977,7.977,0,0,1-3.24-.645A7.662,7.662,0,0,1,44.22-2.1a8,8,0,0,1-1.635-2.6A8.476,8.476,0,0,1,42-7.83a8.36,8.36,0,0,1,.585-3.12,7.912,7.912,0,0,1,1.65-2.58,7.832,7.832,0,0,1,2.535-1.755,7.977,7.977,0,0,1,3.24-.645,7.717,7.717,0,0,1,3.225.66,7.663,7.663,0,0,1,2.475,1.755,7.753,7.753,0,0,1,1.575,2.55A8.39,8.39,0,0,1,57.84-7.95q0,.33-.015.6a3.162,3.162,0,0,1-.045.42H44.79a6.073,6.073,0,0,0,.54,2.13A5.48,5.48,0,0,0,46.5-3.15a5.41,5.41,0,0,0,1.635,1.08,4.884,4.884,0,0,0,1.935.39,5.16,5.16,0,0,0,1.41-.195A6.12,6.12,0,0,0,52.77-2.4a4.476,4.476,0,0,0,1.065-.81,3.306,3.306,0,0,0,.705-1.08l2.28.63a5.757,5.757,0,0,1-1.065,1.59,7.063,7.063,0,0,1-1.56,1.245A8.058,8.058,0,0,1,52.23,0,8.368,8.368,0,0,1,49.98.3Zm5.37-9.18a5.643,5.643,0,0,0-.555-2.055,5.494,5.494,0,0,0-1.17-1.6,5.229,5.229,0,0,0-1.635-1.035,5.244,5.244,0,0,0-1.98-.375,5.244,5.244,0,0,0-1.98.375,5.094,5.094,0,0,0-1.635,1.05,5.314,5.314,0,0,0-1.14,1.6,5.9,5.9,0,0,0-.525,2.04ZM67.77.3a7.717,7.717,0,0,1-3.225-.66,7.723,7.723,0,0,1-2.49-1.77,7.963,7.963,0,0,1-1.6-2.58,8.472,8.472,0,0,1-.57-3.09,8.36,8.36,0,0,1,.585-3.12,8.176,8.176,0,0,1,1.62-2.58,7.723,7.723,0,0,1,2.49-1.77,7.644,7.644,0,0,1,3.195-.66,7.746,7.746,0,0,1,3.21.66,7.68,7.68,0,0,1,2.5,1.77,8.176,8.176,0,0,1,1.62,2.58A8.36,8.36,0,0,1,75.69-7.8a8.472,8.472,0,0,1-.57,3.09A7.835,7.835,0,0,1,73.5-2.13A7.9,7.9,0,0,1,70.995-.36A7.717,7.717,0,0,1,67.77.3ZM62.58-7.77a6.282,6.282,0,0,0,.405,2.28,5.824,5.824,0,0,0,1.11,1.83,5.241,5.241,0,0,0,1.65,1.23,4.65,4.65,0,0,0,2.025.45,4.65,4.65,0,0,0,2.025-.45A5.309,5.309,0,0,0,71.46-3.675a5.823,5.823,0,0,0,1.125-1.86,6.4,6.4,0,0,0,.4-2.3,6.318,6.318,0,0,0-.4-2.265,5.823,5.823,0,0,0-1.125-1.86A5.309,5.309,0,0,0,69.795-13.2a4.65,4.65,0,0,0-2.025-.45,4.525,4.525,0,0,0-2.025.465,5.334,5.334,0,0,0-1.65,1.26,5.972,5.972,0,0,0-1.11,1.86A6.4,6.4,0,0,0,62.58-7.77Z" transform="translate(79.499 67.274)"/>';
        $html .= '</g>';
        $html .= '</svg>';
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";

        // Sidebar navigation
        if (is_array($this->settings) && 1 < count($this->settings)) {
            $html .= '<nav class="lc-sidebar-nav">' . "\n";
            
            // Main Settings Section
            $html .= '<div class="lc-nav-section">' . "\n";
            $html .= '<h3 class="lc-nav-section-title">Settings</h3>' . "\n";

            $c = 0;
            foreach ($this->settings as $section => $data) {

                // Set nav item class
                $class = 'lc-nav-item';
                if (!isset($_GET['tab'])) {
                    if (0 == $c) {
                        $class .= ' active';
                    }
                } else {
                    if (isset($_GET['tab']) && $section == $_GET['tab']) {
                        $class .= ' active';
                    }
                }

                // Set tab link
                $tab_link = add_query_arg(array('tab' => $section));
                if (isset($_GET['settings-updated'])) {
                    $tab_link = remove_query_arg('settings-updated', $tab_link);
                }

                // Get icon from title or use default
                $icon = $this->getNavItemIcon($data['title']);
                
                // Output navigation item
                $html .= '<a href="' . esc_url($tab_link) . '" class="' . esc_attr($class) . '">';
                $html .= '<i class="' . esc_attr($icon) . '"></i>';
                $html .= '<span>' . esc_html(strip_tags($data['title'])) . '</span>';
                $html .= '</a>' . "\n";

                ++$c;
            }
            $html .= '</div>' . "\n";
            
            // Tools Section
            $html .= '<div class="lc-nav-section">' . "\n";
            $html .= '<h3 class="lc-nav-section-title">Tools</h3>' . "\n";
            $html .= '<a href="' . esc_url(add_query_arg(array('tab' => 'license'), menu_page_url('listeo_license', false))) . '" class="lc-nav-item"><i class="fa fa-key"></i><span>' . esc_html(__('License Activation', 'listeo_core')) . '</span></a>' . "\n";
            $html .= '<a href="' . esc_url(add_query_arg(array('tab' => 'listeo-site-health-tab'), admin_url('site-health.php', false))) . '" class="lc-nav-item"><i class="fa fa-heartbeat"></i><span>' . esc_html(__('Health Check', 'listeo_core')) . '</span></a>' . "\n";
            $html .= '</div>' . "\n";
            
            $html .= '</nav>' . "\n";
        }
        
        $html .= '</aside>' . "\n";
        $html .= '</div>' . "\n"; // Close lc-sidebar-container

        // Main content area
        $html .= '<main class="lc-main-content">' . "\n";
        
        // Page header removed - using only form section headers

                            $html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

        // Get settings fields with modern card layout
        ob_start();
        settings_fields($this->_token . '_settings');
        $this->do_listeo_settings_sections($this->_token . '_settings');
        $html .= ob_get_clean();

        $html .= '<div class="lc-save-footer">' . "\n";
        $html .= '<div class="lc-flex lc-items-center lc-justify-between">' . "\n";
        $html .= '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />' . "\n";

        $is_staging = function_exists('listeo_is_staging_environment') && listeo_is_staging_environment();
        $remote_state_available = class_exists('Listeo_Core_Environment_Sync') ? Listeo_Core_Environment_Sync::is_available() : true;

        if (!$remote_state_available) {
            $html .= '<div class="lc-license-status lc-license-inactive"><i class="fa fa-exclamation-triangle"></i> License Required</div>' . "\n";
            $html .= '<a href="' . admin_url('admin.php?page=listeo_license&tab=license') . '" class="lc-button lc-button-primary"><i class="fa fa-key"></i> ' . esc_attr(__('Activate License to Save Changes', 'listeo_core')) . '</a>' . "\n";
        } elseif ($is_staging) {
            $html .= '<div class="lc-license-status lc-license-staging" style="background: #e7f3ff; color: #0073aa; border: 1px solid #0073aa;"><i class="fa fa-info-circle"></i> ' . esc_html__('Staging Environment', 'listeo_core') . '</div>' . "\n";
            $html .= '<button name="Submit" type="submit" class="lc-button lc-button-primary"><i class="fa fa-save"></i> ' . esc_attr(__('Save Settings', 'listeo_core')) . '</button>' . "\n";
        } else {
            // Normal license check for production environments
            $licenseKey   = get_option("Listeo_lic_Key", "");

            $liceEmail    = get_option(
                "Listeo_lic_email",
                ""
            );

            $templateDir  = get_template_directory(); //or dirname(__FILE__);
            $activation_date = get_option('listeo_activation_date');

            $current_time = time();
            $time_diff = ($current_time - $activation_date) / 86400;

            $legacy_check_failed = !class_exists('b472b0Base') || !b472b0Base::CheckWPPlugin($licenseKey, $liceEmail, $licenseMessage, $responseObj, $templateDir . "/style.css");

            if ($legacy_check_failed && $time_diff > 1) {

                $html .= '<div class="lc-license-status lc-license-inactive"><i class="fa fa-exclamation-triangle"></i> License Required</div>' . "\n";
                $html .= '<a href="' . admin_url('admin.php?page=listeo_license&tab=license') . '" class="lc-button lc-button-primary"><i class="fa fa-key"></i> ' . esc_attr(__('Activate License to Save Changes', 'listeo_core')) . '</a>' . "\n";
            } else {
                $html .= '<div class="lc-license-status lc-license-active"><i class="fa fa-check-circle"></i> License Active</div>' . "\n";
                $html .= '<button name="Submit" type="submit" class="lc-button lc-button-primary"><i class="fa fa-save"></i> ' . esc_attr(__('Save Settings', 'listeo_core')) . '</button>' . "\n";
            }
        }

        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</form>' . "\n";
        $html .= '</main>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        if (isset($_GET['license_reset']) && $_GET['license_reset'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>License Reset Complete!</strong> All license data has been cleared. You can now test the setup wizard or enter a new license.</p></div>';
        }

        echo $html;
    }


    public function do_listeo_settings_sections($page)
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (!isset($wp_settings_sections[$page])) {
            return;
        }

        foreach ((array) $wp_settings_sections[$page] as $section) {
            echo '<div class="lc-card lc-mb-4">' . "\n";

            if ($section['title']) {
                echo '<div class="lc-card-header">' . "\n";
                echo '<h2 class="lc-card-title">' . $section['title'] . '</h2>' . "\n";

                if (isset($section['description']) && !empty($section['description'])) {
                    echo '<p class="lc-card-description">' . $section['description'] . '</p>' . "\n";
                }

                echo '</div>' . "\n";
            }

            // Build quick navigation from block titles (type='title' fields)
            if (isset($wp_settings_fields[$page][$section['id']])) {
                $nav_items = array();
                foreach ((array) $wp_settings_fields[$page][$section['id']] as $field) {
                    if (isset($field['args']['field']['type']) && $field['args']['field']['type'] === 'title') {
                        $block_id = isset($field['args']['field']['id']) ? $field['args']['field']['id'] : '';
                        $label = $field['args']['field']['label'];
                        $icon_class = '';
                        $clean_title = strip_tags($label);
                        if (preg_match('/<i class="([^"]+)"><\/i>/', $label, $m)) {
                            $icon_class = $m[1];
                        }
                        if ($block_id) {
                            $nav_items[] = array(
                                'id'    => $block_id,
                                'title' => trim($clean_title),
                                'icon'  => $icon_class,
                            );
                        }
                    }
                }

                if (count($nav_items) > 1) {
                    echo '<nav class="lc-section-quicknav">' . "\n";
                    echo '<span class="lc-quicknav-label">' . esc_html__('Jump to:', 'listeo_core') . '</span>' . "\n";
                    foreach ($nav_items as $item) {
                        echo '<a href="#' . esc_attr($item['id']) . '" class="lc-quicknav-link">';
                        if ($item['icon']) {
                            echo '<i class="' . esc_attr($item['icon']) . '"></i> ';
                        }
                        echo esc_html($item['title']);
                        echo '</a>' . "\n";
                    }
                    echo '</nav>' . "\n";
                }
            }

            // Single card content section for both callback and fields
            echo '<div class="lc-card-content">' . "\n";

            if ($section['callback']) {
                call_user_func($section['callback'], $section);
            }

            if (isset($wp_settings_fields) && isset($wp_settings_fields[$page]) && isset($wp_settings_fields[$page][$section['id']])) {
                $this->do_listeo_settings_fields($page, $section['id']);
            }

            echo '</div>' . "\n";
            echo '</div>' . "\n"; // Close card
        }
    }

    public function  do_listeo_settings_fields($page, $section)
    {
        global $wp_settings_fields;

        if (!isset($wp_settings_fields[$page][$section])) {
            return;
        }

        $current_block = null;
        $block_fields = array();

        foreach ((array) $wp_settings_fields[$page][$section] as $field) {
            $field_type = isset($field['args']['field']['type']) ? $field['args']['field']['type'] : '';
            
            // Check if this is a title field (starts a new block)
            if ($field_type === 'title') {
                // Output the previous block if it exists
                if ($current_block !== null && !empty($block_fields)) {
                    $this->output_settings_block($current_block, $block_fields);
                }
                
                // Start a new block
                $current_block = $field;
                $block_fields = array();
                continue;
            }
            
            // Add field to current block
            if ($current_block !== null) {
                $block_fields[] = $field;
            } else {
                // No block header, output field normally (backwards compatibility)
                $this->output_single_field($field);
            }
        }
        
        // Output the last block if it exists
        if ($current_block !== null && !empty($block_fields)) {
            $this->output_settings_block($current_block, $block_fields);
        }
    }

    private function output_settings_block($block_header, $fields) {
        $block_id = isset($block_header['args']['field']['id']) ? esc_attr($block_header['args']['field']['id']) : '';
        echo '<div class="lc-settings-block"' . ($block_id ? ' id="' . $block_id . '"' : '') . '>' . "\n";

        // Block header
        echo '<div class="lc-block-header">' . "\n";

        // Extract icon and title from the block header
        $title = $block_header['args']['field']['label'];
        $description = isset($block_header['args']['field']['description']) ? $block_header['args']['field']['description'] : '';
        
        // Extract icon from title if it exists
        if (preg_match('/<i class="([^"]+)"><\/i>\s*(.+)/', $title, $matches)) {
            echo '<div class="lc-block-icon"><i class="' . esc_attr($matches[1]) . '"></i></div>' . "\n";
            echo '<div>' . "\n";
            echo '<h3 class="lc-block-title">' . esc_html($matches[2]) . '</h3>' . "\n";
        } else {
            echo '<div class="lc-block-icon"><i class="fa fa-cog"></i></div>' . "\n";
            echo '<div>' . "\n";
            echo '<h3 class="lc-block-title">' . esc_html(strip_tags($title)) . '</h3>' . "\n";
        }

        if (!empty($description)) {
            echo '<p class="lc-block-description">' . wp_kses_post($description) . '</p>' . "\n";
        }
        echo '</div>' . "\n";
        echo '</div>' . "\n";
        
        // Block content
        echo '<div class="lc-block-content">' . "\n";
        
        foreach ($fields as $field) {
            $this->output_single_field($field);
        }
        
        echo '</div>' . "\n";
        echo '</div>' . "\n";
    }

    private function output_single_field($field) {
        $field_type = isset($field['args']['field']['type']) ? $field['args']['field']['type'] : '';
        $class = 'lc-form-row';

        if (!empty($field['args']['class'])) {
            $class .= ' listeo_settings_' . esc_attr($field_type);
        }

        // Special handling for checkbox fields (toggles)
        if ($field_type === 'checkbox') {
            echo '<div class="lc-form-row lc-form-row-toggle">' . "\n";
            
            // Store field title and description in the field args for the toggle
            $field['args']['field']['title'] = $field['title'];
            if (isset($field['args']['field']['description'])) {
                $field['args']['field']['description'] = $field['args']['field']['description'];
            }
            
            call_user_func($field['callback'], $field['args']);
            echo '</div>' . "\n";
            return;
        }

        // Special handling for checkbox_multi fields (toggle groups)
        if ($field_type === 'checkbox_multi') {
            echo '<div class="lc-form-row lc-form-row-toggle-group">' . "\n";
            echo '<div class="lc-form-label-column">' . "\n";
            echo '<div class="lc-form-label">' . $field['title'] . '</div>' . "\n";
            if (isset($field['args']['field']['description']) && !empty($field['args']['field']['description'])) {
                echo '<div class="lc-form-description">' . $field['args']['field']['description'] . '</div>' . "\n";
            }
            echo '</div>' . "\n";
            echo '<div class="lc-form-field-column">' . "\n";
            call_user_func($field['callback'], $field['args']);
            echo '</div>' . "\n";
            echo '</div>' . "\n";
            return;
        }

        echo '<div class="' . esc_attr($class) . '">' . "\n";

        // Label column
        echo '<div class="lc-form-label-column">' . "\n";
        if (!empty($field['args']['label_for'])) {
            echo '<label for="' . esc_attr($field['args']['label_for']) . '" class="lc-form-label">' . $field['title'] . '</label>' . "\n";
        } else {
            echo '<div class="lc-form-label">' . $field['title'] . '</div>' . "\n";
        }
        
        if (isset($field['args']['field']['description']) && !empty($field['args']['field']['description'])) {
            echo '<div class="lc-form-description">' . $field['args']['field']['description'] . '</div>' . "\n";
        }
        echo '</div>' . "\n";

        // Field column
        echo '<div class="lc-form-field-column">' . "\n";
        call_user_func($field['callback'], $field['args']);
        echo '</div>' . "\n";
        
        echo '</div>' . "\n";
    }

    /**
     * Generate HTML for displaying fields
     * @param  array   $field Field data
     * @param  boolean $echo  Whether to echo the field HTML or return it
     * @return void
     */
    public function display_field($data = array(), $post = false, $echo = true)
    {

        // Get field info
        if (isset($data['field'])) {
            $field = $data['field'];
        } else {
            $field = $data;
        }

        // Check for prefix on option name
        $option_name = '';
        if (isset($data['prefix'])) {
            $option_name = $data['prefix'];
        }

        // Get saved data
        $data = '';
        if ($post) {

            // Get saved field data
            $option_name .= $field['id'];
            $option = get_post_meta($post->ID, $field['id'], true);

            // Get data to display in field
            if (isset($option)) {
                $data = $option;
            }
        } else {

            // Get saved option
            $option_name .= $field['id'];
            $option = get_option($option_name);

            // Get data to display in field
            if (isset($option)) {
                $data = $option;
            }
        }

        // Show default data if no option saved and default is supplied
        if ($data === false && isset($field['default'])) {
            $data = $field['default'];
        } elseif ($data === false) {
            $data = '';
        }

        $html = '';

        switch ($field['type']) {

            case 'text':
            case 'url':
            case 'email':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" class="lc-input" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr((isset($field['placeholder'])) ? $field['placeholder'] : '') . '" value="' . esc_attr($data) . '" />' . "\n";
                break;

            case 'password':
            case 'number':
            case 'hidden':
                $min = '';
                if (isset($field['min'])) {
                    $min = ' min="' . esc_attr($field['min']) . '"';
                }

                $max = '';
                if (isset($field['max'])) {
                    $max = ' max="' . esc_attr($field['max']) . '"';
                }
                $html .= '<input step="0.001" id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" class="lc-input" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr((isset($field['placeholder'])) ? $field['placeholder'] : '') . '" value="' . esc_attr($data) . '"' . $min . '' . $max . '/>' . "\n";
                break;

            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" class="lc-input" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr((isset($field['placeholder'])) ? $field['placeholder'] : '') . '" value="" />' . "\n";
                break;

            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" class="lc-input lc-textarea" name="' . esc_attr($option_name) . '">' . $data . '</textarea>' . "\n";
                break;

            case 'checkbox':
                $checked = '';
                if ($data && 'on' == $data) {
                    $checked = 'checked="checked"';
                }
                $html .= '<div class="lc-toggle-item">';
                $html .= '<div class="lc-toggle-content">';
                $html .= '<h4 class="lc-toggle-title">' . (isset($field['label']) ? $field['label'] : $field['title'] ?? __('Enable Option', 'listeo_core')) . '</h4>';
                if (isset($field['description']) && !empty($field['description'])) {
                    $html .= '<p class="lc-toggle-description">' . $field['description'] . '</p>';
                }
                $html .= '</div>';
                $html .= '<label class="lc-toggle" for="' . esc_attr($field['id']) . '">';
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";
                $html .= '<span class="lc-toggle-slider"></span>';
                $html .= '</label>';
                $html .= '</div>';
                
                // Add conditional visibility script for region_in_links
                if ($field['id'] === 'region_in_links') {
                    $html .= '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        function updateRegionLinksVisibility() {
                            var regionToggle = document.getElementById("region_in_links");
                            var regionContainer = regionToggle ? regionToggle.closest(".lc-toggle-item") : null;
                            
                            if (!regionContainer || !regionToggle) return;
                            
                            // Hide by default, then show only if previously enabled
                            regionContainer.style.display = "none";
                            
                            // Check if the setting was previously enabled
                            if (regionToggle.checked) {
                                regionContainer.style.display = "block";
                            }
                        }
                        
                        updateRegionLinksVisibility();
                    });
                    </script>';
                }
                
                break;

            case 'checkbox_multi':
                $html .= '<div class="lc-toggle-group">';
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if (in_array($k, (array) $data)) {
                        $checked = true;
                    }
                    $html .= '<div class="lc-toggle-item">';
                    $html .= '<div class="lc-toggle-content">';
                    $html .= '<h4 class="lc-toggle-title">' . $v . '</h4>';
                    $html .= '</div>';
                    $html .= '<label class="lc-toggle" for="' . esc_attr($field['id'] . '_' . $k) . '">';
                    $html .= '<input type="checkbox" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '[]" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" />';
                    $html .= '<span class="lc-toggle-slider"></span>';
                    $html .= '</label>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                break;
            case 'combined_urls_preview':
                $html .= $this->generate_combined_urls_preview();
                break;

            case 'radio':
                $html .= '<div class="lc-radio-group">';
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if ($k == $data) {
                        $checked = true;
                    }
                    $html .= '<div class="lc-radio-item">';
                    $html .= '<input type="radio" class="lc-radio" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" />';
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '" class="lc-form-label">' . $v . '</label>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                break;

            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '" class="lc-input lc-select">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if ($k == $data) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select>';
                break;

            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) . '" class="lc-input lc-select" multiple="multiple">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if (in_array($k, (array) $data)) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select>';
                break;

            case 'image':
                $image_thumb = '';
                if ($data) {
                    $image_thumb = wp_get_attachment_thumb_url($data);
                }
                $html .= '<div class="lc-image-upload">';
                if ($image_thumb) {
                    $html .= '<img id="' . $option_name . '_preview" class="lc-image-preview" src="' . $image_thumb . '" />';
                }
                $html .= '<div class="lc-flex lc-gap-4">';
                $html .= '<button id="' . $option_name . '_button" type="button" data-uploader_title="' . __('Upload an image', 'listeo_core') . '" data-uploader_button_text="' . __('Use image', 'listeo_core') . '" class="image_upload_button lc-button lc-button-secondary"><i class="fa fa-upload"></i> ' . __('Upload Image', 'listeo_core') . '</button>';
                $html .= '<button id="' . $option_name . '_delete" type="button" class="image_delete_button lc-button lc-button-secondary"><i class="fa fa-trash"></i> ' . __('Remove', 'listeo_core') . '</button>';
                $html .= '</div>';
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/>';
                $html .= '</div>';
                break;

            case 'color':
                // Single WP-bundled color input. `wpColorPicker` (init'd
                // in settings.js) wraps this with a swatch, palette and
                // Clear button. Farbtastic was previously used here but
                // it has a quirk where it refuses to write back to an
                // empty input (the `if (this.value && ...)` guard in
                // farbtastic.js:192), which made any color field with
                // an empty default unsavable. wpColorPicker doesn't
                // have that quirk and supports clearing the value back
                // to empty cleanly.
                $html .= '<input type="text" name="' . esc_attr($option_name) . '" class="lc-color-field lc-input" value="' . esc_attr($data) . '" data-default-color="' . esc_attr(isset($field['default']) ? $field['default'] : '') . '" />';
                break;

            case 'editor':
                wp_editor($data, $option_name, array(
                    'textarea_name' => $option_name,
                    'editor_height' => 150
                ));
                break;
                
            case 'script':
                if (isset($field['script'])) {
                    $html .= $field['script'];
                }
                break;
        }

        switch ($field['type']) {

            case 'checkbox_multi':
            case 'radio':
            case 'select_multi':
                // $html .= '<br/><span class="description">' . $field['description'] . '</span>';
                break;
            case 'title':
                //$html .= '<br/><h3 class="description '.$field['id'].' ">' . $field['description'] . '</h3>';
                break;


            default:
                if (!$post) {
                    $html .= '<label for="' . esc_attr($field['id']) . '">' . "\n";
                }



                if (!$post) {
                    $html .= '</label>' . "\n";
                }
                if ($field['id'] == 'maps_api_server') {
                    // Get server IP address
                    $server_ip = $this->get_server_ip();
                    $is_local = $this->is_local_environment();
                    
                    $html .= '<div class="lc-api-test-container lc-mt-4">';
                    
                    // Test API Key button container
                    $html .= '<div class="lc-flex lc-items-center lc-gap-4">';
                    $html .= '<button type="button" id="listeo_test_google_maps_api" class="lc-button lc-button-primary"><i class="fa fa-check-circle"></i> Test API Key</button>';
                    $html .= '<span id="listeo_api_test_result" class="lc-api-test-result"></span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="lc-form-description lc-mt-4">';
                    $html .= '<strong><i class="fa fa-info-circle"></i> Creating Google Maps API Key:</strong> <a href="https://docs.purethemes.net/listeo/knowledge-base/creating-google-maps-api-key/#radius-key" target="_blank">Read our complete guide</a>';
                    $html .= '</div>';
                    
                    // Server IP and restrictions note - different message for local vs production
                    if ($is_local) {
                        $html .= '<div class="lc-notification lc-notification-warning">';
                        $html .= '<div class="lc-notification-content">';
                        $html .= '<h4 class="lc-notification-title warning"><i class="fa fa-tools"></i> Local Development Detected</h4>';
                        $html .= '<p class="lc-notification-text warning">You\'re working on a local environment. For development, you can:</p>';
                        $html .= '<ul class="lc-notification-list warning">';
                        $html .= '<li>Use an <strong>unrestricted API key</strong> (no IP restrictions)</li>';
                        $html .= '<li>Or set up your production server\'s IP restrictions and use the same key</li>';
                        $html .= '<li><strong>Remember:</strong> Always restrict your API key before deploying to production!</li>';
                        $html .= '</ul>';
                        $html .= '</div>';
                        $html .= '</div>';
                        
                        $html .= '<div class="lc-notification lc-notification-info">';
                        $html .= '<div class="lc-notification-content">';
                        $html .= '<h4 class="lc-notification-title info"><i class="fa fa-lightbulb"></i> For Production</h4>';
                        $html .= '<p class="lc-notification-text info">When you deploy your site, restrict your API key to your server\'s public IP address in Google Cloud Console → APIs & Services → Credentials → Edit your API key → Application restrictions → IP addresses.</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="lc-notification lc-notification-error">';
                        $html .= '<div class="lc-notification-content">';
                        $html .= '<h4 class="lc-notification-title error"><i class="fa fa-shield-alt"></i> Security Important</h4>';
                        $html .= '<p class="lc-notification-text error">For security, restrict this API key to your server IP address: <code>' . esc_html($server_ip) . '</code></p>';
                        $html .= '<p class="lc-notification-text error">In Google Cloud Console → APIs & Services → Credentials → Edit your API key → Application restrictions → IP addresses → Add: ' . esc_html($server_ip) . '</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }
                    
                    $html .= '<div class="lc-form-description lc-mt-4">';
                    $html .= '<strong>Required APIs:</strong> This key is used for server-side geocoding. Make sure the following APIs are enabled: Geocoding API, Places API (if using places functionality).';
                    $html .= '</div>';
                    
                    $html .= '</div>';
                    
                    // Add JavaScript for the test button
                    $html .= '<script>
                    jQuery(document).ready(function($) {
                        $("#listeo_test_google_maps_api").on("click", function() {
                            var apiKey = $("#maps_api_server").val().trim();
                            var button = $(this);
                            var resultSpan = $("#listeo_api_test_result");
                            
                            if (!apiKey) {
                                resultSpan.html("<span style=\"color: #d63384;\">Please enter an API key first</span>");
                                return;
                            }
                            
                            button.prop("disabled", true).text("Testing...");
                            resultSpan.html("<span style=\"color: #0d6efd;\">Testing Geocoding API...</span>");
                            
                            $.ajax({
                                url: ajaxurl,
                                type: "POST",
                                data: {
                                    action: "listeo_test_google_maps_api_key",
                                    api_key: apiKey,
                                    nonce: "' . wp_create_nonce('listeo_test_google_maps_api_nonce') . '"
                                },
                                success: function(response) {
                                    if (response.success) {
                                        resultSpan.html("<span style=\"color: #198754;\">✓ " + response.data.message + "</span>");
                                    } else {
                                        resultSpan.html("<span style=\"color: #d63384;\">✗ " + response.data.message + "</span>");
                                    }
                                },
                                error: function() {
                                    resultSpan.html("<span style=\"color: #d63384;\">✗ Network error occurred</span>");
                                },
                                complete: function() {
                                    button.prop("disabled", false).text("Test API Key");
                                }
                            });
                        });
                    });
                    </script>';
                }
                if ($field['id'] == 'google_reviews_api_key') {
                    // Get server IP address
                    $server_ip = $this->get_server_ip();
                    $is_local = $this->is_local_environment();
                    
                    $html .= '<div class="lc-api-test-container lc-mt-4">';
                    
                    // Test API Key button container
                    $html .= '<div class="lc-flex lc-items-center lc-gap-4">';
                    $html .= '<button type="button" id="listeo_test_google_reviews_api" class="lc-button lc-button-primary"><i class="fa fa-check-circle"></i> Test API Key</button>';
                    $html .= '<span id="listeo_reviews_api_test_result" class="lc-api-test-result"></span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="lc-form-description lc-mt-4">';
                    $html .= '<strong><i class="fa fa-info-circle"></i> Creating Google Maps API Key:</strong> <a href="https://docs.purethemes.net/listeo/knowledge-base/creating-google-maps-api-key/#radius-key" target="_blank">Read our complete guide</a>';
                    $html .= '</div>';
                    
                    // Server IP and restrictions note - different message for local vs production
                    if ($is_local) {
                        $html .= '<div class="lc-notification lc-notification-warning">';
                        $html .= '<div class="lc-notification-content">';
                        $html .= '<h4 class="lc-notification-title warning"><i class="fa fa-tools"></i> Local Development Detected</h4>';
                        $html .= '<p class="lc-notification-text warning">You\'re working on a local environment. For development, you can:</p>';
                        $html .= '<ul class="lc-notification-list warning">';
                        $html .= '<li>Use an <strong>unrestricted API key</strong> (no IP restrictions)</li>';
                        $html .= '<li>Or set up your production server\'s IP restrictions and use the same key</li>';
                        $html .= '<li><strong>Remember:</strong> Always restrict your API key before deploying to production!</li>';
                        $html .= '</ul>';
                        $html .= '</div>';
                        $html .= '</div>';
                        
                        $html .= '<div class="lc-notification lc-notification-info">';
                        $html .= '<div class="lc-notification-content">';
                        $html .= '<h4 class="lc-notification-title info"><i class="fa fa-lightbulb"></i> For Production</h4>';
                        $html .= '<p class="lc-notification-text info">When you deploy your site, restrict your API key to your server\'s public IP address in Google Cloud Console → APIs & Services → Credentials → Edit your API key → Application restrictions → IP addresses.</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="lc-notification lc-notification-error">';
                        $html .= '<div class="lc-notification-content">';
                        $html .= '<h4 class="lc-notification-title error"><i class="fa fa-shield-alt"></i> Security Important</h4>';
                        $html .= '<p class="lc-notification-text error">For security, restrict this API key to your server IP address: <code>' . esc_html($server_ip) . '</code></p>';
                        $html .= '<p class="lc-notification-text error">In Google Cloud Console → APIs & Services → Credentials → Edit your API key → Application restrictions → IP addresses → Add: ' . esc_html($server_ip) . '</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }
                    
                    $html .= '<div class="lc-form-description lc-mt-4">';
                    $html .= '<strong>Required APIs:</strong> This key is used for Google Reviews functionality. Make sure the following APIs are enabled: Places API.';
                    $html .= '</div>';
                    
                    $html .= '</div>';
                    
                    // Add JavaScript for the test button
                    $html .= '<script>
                    jQuery(document).ready(function($) {
                        $("#listeo_test_google_reviews_api").on("click", function() {
                            var apiKey = $("#google_reviews_api_key").val().trim();
                            var button = $(this);
                            var resultSpan = $("#listeo_reviews_api_test_result");
                            
                            if (!apiKey) {
                                resultSpan.html("<span style=\"color: #d63384;\">Please enter an API key first</span>");
                                return;
                            }
                            
                            button.prop("disabled", true).text("Testing...");
                            resultSpan.html("<span style=\"color: #0d6efd;\">Testing Places API...</span>");
                            
                            $.ajax({
                                url: ajaxurl,
                                type: "POST",
                                data: {
                                    action: "listeo_test_google_reviews_api_key",
                                    api_key: apiKey,
                                    nonce: "' . wp_create_nonce('listeo_test_google_reviews_api_nonce') . '"
                                },
                                success: function(response) {
                                    if (response.success) {
                                        resultSpan.html("<span style=\"color: #198754;\">✓ " + response.data.message + "</span>");
                                    } else {
                                        resultSpan.html("<span style=\"color: #d63384;\">✗ " + response.data.message + "</span>");
                                    }
                                },
                                error: function() {
                                    resultSpan.html("<span style=\"color: #d63384;\">✗ Network error occurred</span>");
                                },
                                complete: function() {
                                    button.prop("disabled", false).text("Test API Key");
                                }
                            });
                        });
                    });
                    </script>';
                }
                break;

            case 'custom':
                // Handle custom field types with callbacks
                if (isset($field['callback']) && is_callable($field['callback'])) {
                    ob_start();
                    call_user_func($field['callback'], $field);
                    $html .= ob_get_clean();
                } else {
                    $html .= '<p>' . __('Custom field callback not found.', 'listeo_core') . '</p>';
                }
                break;
        }

        if (!$echo) {
            return $html;
        }

        echo $html;
    }

    /**
     * Validate form field
     * @param  string $data Submitted value
     * @param  string $type Type of field to validate
     * @return string       Validated value
     */
    public function validate_field($data = '', $type = 'text')
    {

        switch ($type) {
            case 'text':
                $data = esc_attr($data);
                break;
            case 'url':
                $data = esc_url($data);
                break;
            case 'email':
                $data = is_email($data);
                break;
        }

        return $data;
    }

    /**
     * Add meta box to the dashboard
     * @param string $id            Unique ID for metabox
     * @param string $title         Display title of metabox
     * @param array  $post_types    Post types to which this metabox applies
     * @param string $context       Context in which to display this metabox ('advanced' or 'side')
     * @param string $priority      Priority of this metabox ('default', 'low' or 'high')
     * @param array  $callback_args Any axtra arguments that will be passed to the display function for this metabox
     * @return void
     */
    public function add_meta_box($id = '', $title = '', $post_types = array(), $context = 'advanced', $priority = 'default', $callback_args = null)
    {

        // Get post type(s)
        if (!is_array($post_types)) {
            $post_types = array($post_types);
        }

        // Generate each metabox
        foreach ($post_types as $post_type) {
            add_meta_box($id, $title, array($this, 'meta_box_content'), $post_type, $context, $priority, $callback_args);
        }
    }

    /**
     * Display metabox content
     * @param  object $post Post object
     * @param  array  $args Arguments unique to this metabox
     * @return void
     */
    public function meta_box_content($post, $args)
    {

        $fields = apply_filters($post->post_type . '_custom_fields', array(), $post->post_type);

        if (!is_array($fields) || 0 == count($fields)) return;

        echo '<div class="custom-field-panel">' . "\n";

        foreach ($fields as $field) {

            if (!isset($field['metabox'])) continue;

            if (!is_array($field['metabox'])) {
                $field['metabox'] = array($field['metabox']);
            }

            if (in_array($args['id'], $field['metabox'])) {
                $this->display_meta_box_field($post, $field);
            }
        }

        echo '</div>' . "\n";
    }

    /**
     * Dispay field in metabox
     * @param  array  $field Field data
     * @param  object $post  Post object
     * @return void
     */
    public function display_meta_box_field($post, $field = array())
    {

        if (!is_array($field) || 0 == count($field)) return;

        $field = '<p class="form-field"><label for="' . $field['id'] . '">' . $field['label'] . '</label>' . $this->display_field($field, $post, false) . '</p>' . "\n";

        echo $field;
    }

    /**
     * Capture old place ID before meta fields are updated
     * 
     * @param int $post_id The post ID being saved
     */
    public function capture_old_place_id($post_id = 0) {
        if (!$post_id) return;
        
        $post_type = get_post_type($post_id);
        if ($post_type !== 'listing') return;
        
        // Store the current place ID before any updates
        $this->old_place_ids[$post_id] = get_post_meta($post_id, '_place_id', true);
    }

    /**
     * Save metabox fields
     * @param  integer $post_id Post ID
     * @return void
     */
    public function save_meta_boxes($post_id = 0)
    {

        if (!$post_id) return;

        $post_type = get_post_type($post_id);

        $fields = apply_filters($post_type . '_custom_fields', array(), $post_type);

        if (!is_array($fields) || 0 == count($fields)) return;

        foreach ($fields as $field) {
            if (isset($_REQUEST[$field['id']])) {
                update_post_meta($post_id, $field['id'], $this->validate_field($_REQUEST[$field['id']], $field['type']));
            } else {
                update_post_meta($post_id, $field['id'], '');
            }
        }

        // Handle place ID changes - clear Google reviews data when place ID is removed or changed
        if ($post_type === 'listing') {
            $this->handle_place_id_changes($post_id);
        }
    }

    /**
     * Handle place ID changes and clear Google reviews data when necessary
     * 
     * @param int $post_id The post ID being saved
     */
    private function handle_place_id_changes($post_id) {
        // Get the old place ID that we captured before updates
        $old_place_id = isset($this->old_place_ids[$post_id]) ? $this->old_place_ids[$post_id] : '';
        
        // Get the new place ID from the form submission
        $new_place_id = isset($_REQUEST['_place_id']) ? sanitize_text_field($_REQUEST['_place_id']) : '';
        
        // If place ID was removed or changed, clear Google reviews data
        if ($old_place_id !== $new_place_id) {
            // Clear Google reviews transient cache
            delete_transient('listeo_reviews_' . $post_id);
            
            // If place ID was removed (empty), clear all Google-related permanent data
            if (empty($new_place_id)) {
                delete_post_meta($post_id, '_google_rating');
                delete_post_meta($post_id, '_google_review_count');
                delete_post_meta($post_id, '_google_last_updated');
                
                // Force recalculate combined rating without Google data
                // Clear existing combined rating to ensure fresh calculation
                delete_post_meta($post_id, '_combined_rating');
                delete_post_meta($post_id, '_combined_review_count');
                
                $reviews_instance = Listeo_Core_Reviews::instance();
                if (method_exists($reviews_instance, 'get_combined_rating')) {
                    $new_combined_rating = $reviews_instance->get_combined_rating($post_id);
                }
                
                // Log the action for debugging
                
			} else {
				// Place ID changed - clear cache to force fresh fetch next time
				// But keep permanent data until fresh data is fetched
				
				// Note: Proactive fetching will be handled by the proactive_google_reviews_fetch() method
			}
		}        // Clean up stored old place ID
        unset($this->old_place_ids[$post_id]);
    }

    /**
     * Proactively fetch Google reviews for listings with place_id
     * This runs after all meta data has been saved and handles ALL listing updates
     * 
     * @param int $post_id The post ID being saved
     */
    public function proactive_google_reviews_fetch($post_id) {
        // Only process listing post type
        if (get_post_type($post_id) !== 'listing') {
            return;
        }

        // Avoid infinite loops and unnecessary processing
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Check if Google reviews are enabled
        if (!get_option('listeo_google_reviews')) {
            
            return;
        }

        // Get the current place_id
        $place_id = get_post_meta($post_id, '_place_id', true);
        
        // Only proceed if we have a place_id
        if (empty($place_id)) {
           
            return;
        }

        // Check if we recently fetched reviews for this listing to avoid API abuse
        $last_fetch = get_post_meta($post_id, '_google_reviews_last_proactive_fetch', true);
        if (!empty($last_fetch)) {
            $time_since_last_fetch = time() - strtotime($last_fetch);
            // Don't fetch more than once every 10 minutes for the same listing
            if ($time_since_last_fetch < 600) {
                
                return;
            }
        }

        // Get post object and fetch reviews
        $post = get_post($post_id);
        if ($post) {
           
            
            // Proactively fetch Google reviews (populates transient + meta fields)
            $reviews = listeo_get_google_reviews($post);
            
            // Update the last proactive fetch timestamp
            update_post_meta($post_id, '_google_reviews_last_proactive_fetch', current_time('mysql'));
            
            // Log the action for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $source = 'unknown';
                if (did_action('listeo_core_update_listing_data')) {
                    $source = 'frontend_submission';
                } elseif (is_admin()) {
                    $source = 'admin_edit';
                } else {
                    $source = 'programmatic'; // likely data scraper or other import
                }
                
                $has_reviews = !empty($reviews) && isset($reviews['result']['reviews']) && is_array($reviews['result']['reviews']);
                $review_count = $has_reviews ? count($reviews['result']['reviews']) : 0;
                
             
            }
        }
    }

    /**
     * Handle when place_id meta is updated directly (e.g., by data scraper)
     * This catches cases where update_post_meta is called directly without triggering save_post
     * 
     * @param int    $meta_id    ID of the metadata entry
     * @param int    $post_id    Post ID
     * @param string $meta_key   Meta key that was updated
     * @param mixed  $meta_value The new meta value
     */
    public function on_place_id_meta_updated($meta_id, $post_id, $meta_key, $meta_value) {
        // Only process _place_id meta updates for listing post type
        if ($meta_key !== '_place_id' || get_post_type($post_id) !== 'listing') {
            return;
        }

        // Only proceed if we have a non-empty place_id
        if (empty($meta_value)) {
            return;
        }

        // Check if Google reviews are enabled
        if (!get_option('listeo_google_reviews')) {
            return;
        }

        // Check if we recently fetched reviews for this listing to avoid API abuse
        $last_fetch = get_post_meta($post_id, '_google_reviews_last_proactive_fetch', true);
        if (!empty($last_fetch)) {
            $time_since_last_fetch = time() - strtotime($last_fetch);
            // Don't fetch more than once every 5 minutes for meta updates (shorter than save_post)
            if ($time_since_last_fetch < 300) {
                return;
            }
        }

        // Get post object and fetch reviews
        $post = get_post($post_id);
        if ($post) {
            // Proactively fetch Google reviews (populates transient + meta fields)
            $reviews = listeo_get_google_reviews($post);
            
            // Update the last proactive fetch timestamp
            update_post_meta($post_id, '_google_reviews_last_proactive_fetch', current_time('mysql'));
            
            // Log the action for debugging
            
        }
    }


    public function listeo_core_health_check($page)
    {
        ob_start()
        ?>
        <div class="health-check-body health-check-debug-tab">
            <h2>Listeo Health Check</h2>
            <p>
                This page shows you if you have correctly configured Listeo and if something is missing.
            </p>

            <div id="health-check-debug">

                <h3 class="health-check-accordion-heading">
                    <button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-wp-core" type="button">
                        <span class="title">
                            WordPress </span>
                        <span class="icon"></span>
                    </button>
                </h3>

                <div id="health-check-accordion-block-wp-core" class="health-check-accordion-panel">
                    <table class="widefat striped health-check-table" role="presentation">
                        <tr>
                            <td>Dashboard Page</td>
                        </tr>

                    </table>
                </div>
            </div>
        </div>
<?php
        $output = ob_get_clean();
        echo $output;
    }


    /**
     * Generate preview of combined taxonomy URLs (only those with listings)
     */
    private function generate_combined_urls_preview()
    {
        $combined_urls_enabled = get_option('listeo_combined_taxonomy_urls');

        if (!$combined_urls_enabled) {
            return '<div style="padding: 15px; background: #f0f0f1; border-radius: 4px; color: #646970;">
                <p><em>Enable "Combined region and feature URLs" above to see available URL combinations.</em></p>
            </div>';
        }

        // Get regions and features that have listings
        $regions = get_terms(array(
            'taxonomy' => 'region',
            'hide_empty' => true, // Only get regions that have listings
            'number' => 50 // Increase limit for better coverage
        ));

        $features = get_terms(array(
            'taxonomy' => 'listing_feature',
            'hide_empty' => true, // Only get features that have listings
            'number' => 50 // Increase limit for better coverage
        ));

        if (empty($regions) || empty($features)) {
            return '<div style="padding: 15px; background: #fef7f0; border-left: 4px solid #dba617; color: #646970;">
                <p><strong>Note:</strong> You need to have both Regions and Listing Features with published listings to generate combined URLs.</p>
                <p>Create some regions and listing features, then add listings to them to see available combinations.</p>
            </div>';
        }

        $html = '<div style="max-height: 300px; overflow: auto; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-top: 10px;">';
        $html .= '<h4 style="margin-top: 0;">🔗 Available Combined URLs (with listings)</h4>';

        // Find combinations that actually have listings
        $valid_combinations = array();
        $total_checked = 0;
        $max_examples = 20; // Show more examples since we're filtering
        $max_checks = 100; // Limit how many combinations we check for performance

        foreach ($regions as $region) {
            foreach ($features as $feature) {
                if ($total_checked >= $max_checks) {
                    break 2;
                }

                $total_checked++;

                // Check if there are listings with both this region AND feature
                $listings_query = new WP_Query(array(
                    'post_type' => 'listing',
                    'post_status' => 'publish',
                    'posts_per_page' => 1, // We only need to know if any exist
                    'fields' => 'ids', // Only get IDs for performance
                    'tax_query' => array(
                        'relation' => 'AND',
                        array(
                            'taxonomy' => 'region',
                            'field'    => 'term_id',
                            'terms'    => $region->term_id,
                        ),
                        array(
                            'taxonomy' => 'listing_feature',
                            'field'    => 'term_id',
                            'terms'    => $feature->term_id,
                        ),
                    ),
                ));

                if ($listings_query->have_posts()) {
                    $url = home_url('/' . $region->slug . '/' . $feature->slug . '/');
                    $valid_combinations[] = array(
                        'url' => $url,
                        'title' => sprintf(__('%s in %s', 'listeo_core'), $feature->name, $region->name),
                        'region' => $region->name,
                        'feature' => $feature->name,
                        'count' => $listings_query->found_posts > 0 ? $listings_query->found_posts : 1
                    );

                    // Stop if we have enough examples
                    if (count($valid_combinations) >= $max_examples) {
                        break 2;
                    }
                }

                wp_reset_postdata();
            }
        }

        if (empty($valid_combinations)) {
            return '<div style="padding: 15px; background: #fef7f0; border-left: 4px solid #dba617; color: #646970;">
                <p><strong>No valid combinations found:</strong> While you have regions and features with listings, there are no listings that have both a region AND a feature assigned.</p>
                <p>Make sure your listings are assigned to both regions and listing features to create valid combined URLs.</p>
            </div>';
        }

        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<p style="margin-bottom: 10px;"><strong>Valid URL combinations (showing ' . count($valid_combinations) . ' combinations with listings):</strong></p>';

        foreach ($valid_combinations as $combo) {
            $html .= '<div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px; border-left: 3px solid #0073aa;">';
            $html .= '<div style="font-weight: 600; color: #0073aa;">' . esc_html($combo['title']) . '</div>';
            $html .= '<div style="font-size: 13px; color: #666; font-family: monospace;">';
            $html .= '<a href="' . esc_url($combo['url']) . '" target="_blank" style="text-decoration: none;">' . esc_html($combo['url']) . '</a>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Calculate total possible combinations
        $total_possible = count($regions) * count($features);
        $found_valid = count($valid_combinations);

        if ($total_checked < $total_possible) {
            $html .= '<div style="padding: 10px; background: #e7f3ff; border-radius: 3px; color: #0073aa; margin-bottom: 10px;">';
            $html .= '<strong>📊 Statistics:</strong><br>';
            $html .= '• <strong>Valid combinations found:</strong> ' . $found_valid . ' (checked ' . $total_checked . ' of ' . $total_possible . ' possible combinations)<br>';
            $html .= '• <strong>Total regions:</strong> ' . count($regions) . '<br>';
            $html .= '• <strong>Total features:</strong> ' . count($features) . '<br>';
            $html .= '• <em>Note: Only showing combinations that have published listings</em>';
            $html .= '</div>';
        } else {
            $html .= '<div style="padding: 10px; background: #e7f3ff; border-radius: 3px; color: #0073aa; margin-bottom: 10px;">';
            $html .= '<strong>📊 Complete Statistics:</strong><br>';
            $html .= '• <strong>Valid combinations:</strong> ' . $found_valid . ' out of ' . $total_possible . ' possible<br>';
            $html .= '• <strong>Total regions:</strong> ' . count($regions) . '<br>';
            $html .= '• <strong>Total features:</strong> ' . count($features);
            $html .= '</div>';
        }

        $html .= '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 3px; color: #856404; font-size: 13px;">';
        $html .= '<strong>💡 Tips:</strong><br>';
        $html .= '• Only combinations with published listings are shown above<br>';
        $html .= '• These URLs will automatically show listings that have both the region AND the feature<br>';
        $html .= '• Perfect for SEO - create specific landing pages for location + feature combinations<br>';
        $html .= '• URLs update automatically when you add new listings with matching taxonomies<br>';
        $html .= '• Remember to flush permalinks after enabling this feature';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    function listeo_display_log_page()
    {
        $log_file_path = WP_CONTENT_DIR . '/debug.log'; // Path to the log file

        echo '<div class="wrap">';
        echo '<h1>Listeo Debug Log Content</h1>';

        // Check if the log file exists and is readable
        if (file_exists($log_file_path) && is_readable($log_file_path)) {
            // Read the file content
            $log_content = file_get_contents($log_file_path);
            // Display the content in a textarea or preformatted text
            echo '<textarea readonly style="width: 100%; height: 500px;">' . esc_textarea($log_content) . '</textarea>';
        } else {
            echo '<p>The log file does not exist or is not readable.</p>';
        }

        echo '</div>';
    }


    /**
     * Main WordPress_Plugin_Template_Settings Instance
     *
     * Ensures only one instance of WordPress_Plugin_Template_Settings is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see WordPress_Plugin_Template()
     * @return Main WordPress_Plugin_Template_Settings instance
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    } // End __clone()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    } // End __wakeup()

    /**
     * Cache utilities page
     */
    public function cache_utils_page() {
        // Handle cache clearing actions
        if (isset($_POST['clear_price_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_price_cache')) {
            Listeo_Core_Calendar_View::clear_daily_prices_cache();
            echo '<div class="notice notice-success"><p>Daily prices cache cleared successfully!</p></div>';
        }
        
        if (isset($_POST['clear_listing_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_listing_cache') && isset($_POST['listing_id'])) {
            $listing_id = intval($_POST['listing_id']);
            Listeo_Core_Calendar_View::clear_daily_prices_cache($listing_id);
            echo '<div class="notice notice-success"><p>Daily prices cache cleared for listing #' . $listing_id . '</p></div>';
        }
        
        // Get cache statistics
        global $wpdb;
        $total_cache_entries = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_daily_prices_%'"
        );
        ?>
        <div class="wrap">
            <h1>Listeo Cache Utilities</h1>
            
            <div class="card">
                <h2>Daily Prices Cache</h2>
                <p>Cache helps improve performance by storing calculated daily prices for listings.</p>
                <p><strong>Current cache entries:</strong> <?php echo $total_cache_entries; ?></p>
                
                <h3>Clear All Daily Prices Cache</h3>
                <form method="post">
                    <?php wp_nonce_field('clear_price_cache'); ?>
                    <p>This will clear all cached daily prices for all listings. The cache will be rebuilt automatically when users visit listing pages.</p>
                    <input type="submit" name="clear_price_cache" class="button button-secondary" value="Clear All Cache" onclick="return confirm('Are you sure you want to clear all daily prices cache?');">
                </form>
                
                <h3>Clear Cache for Specific Listing</h3>
                <form method="post">
                    <?php wp_nonce_field('clear_listing_cache'); ?>
                    <p>
                        <label for="listing_id">Listing ID:</label>
                        <input type="number" name="listing_id" id="listing_id" min="1" required>
                        <input type="submit" name="clear_listing_cache" class="button button-secondary" value="Clear Listing Cache">
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2>Cache Information</h2>
                <ul>
                    <li><strong>Cache Duration:</strong> 6 hours</li>
                    <li><strong>Auto-clearing:</strong> Cache is automatically cleared when:
                        <ul style="margin-top: 5px;">
                            <li>Listing is updated</li>
                            <li>Booking is confirmed or cancelled</li>
                            <li>Price meta fields are modified</li>
                        </ul>
                    </li>
                    <li><strong>Storage:</strong> WordPress transients API</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Check if current environment is local development
     */
    private function is_local_environment() {
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        $server_addr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
        $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        
        // Check for common local development indicators
        $local_indicators = array(
            'localhost',
            '127.0.0.1',
            '::1',
            '.local',
            '.dev',
            '.test'
        );
        
        foreach ($local_indicators as $indicator) {
            if (strpos($server_name, $indicator) !== false || 
                strpos($http_host, $indicator) !== false ||
                strpos($server_addr, $indicator) !== false) {
                return true;
            }
        }
        
        // Check for private IP ranges
        if (filter_var($server_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        
        return false;
    }

    /**
     * Get server IP address for Google Maps API key restrictions
     */
    private function get_server_ip() {
        // Check if it's a local environment
        if ($this->is_local_environment()) {
            return 'local';
        }
        
        // Try multiple methods to get the server IP
        $ip = '';
        
        // Method 1: Check if server has public IP in $_SERVER
        if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            $ip = $_SERVER['SERVER_ADDR'];
        }
        
        // Method 2: Try external service if no public IP found
        if (empty($ip)) {
            $response = wp_remote_get('https://api.ipify.org?format=text', array('timeout' => 5));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $ip = wp_remote_retrieve_body($response);
                $ip = trim($ip);
            }
        }
        
        // Method 3: Fallback to local IP if everything else fails
        if (empty($ip) && !empty($_SERVER['SERVER_ADDR'])) {
            $ip = $_SERVER['SERVER_ADDR'];
        }
        
        // Final fallback
        if (empty($ip)) {
            $ip = 'Unable to detect';
        }
        
        return $ip;
    }

    /**
     * AJAX handler for testing Google Maps API key
     */
    public function test_google_maps_api_key() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_test_google_maps_api_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key is required.']);
        }

        // Test with a simple geocoding request (New York coordinates)
        $test_address = 'New York, NY, USA';
        
        $geocoding_url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'address' => $test_address,
            'key' => $api_key
        ]);

        $response = wp_remote_get($geocoding_url, [
            'timeout' => 15,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Failed to connect to Google Maps API: ' . $response->get_error_message()]);
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (!$data) {
            wp_send_json_error(['message' => 'Invalid response from Google Maps API']);
        }

        // Handle API response with detailed error messages
        switch ($data['status']) {
            case 'OK':
                wp_send_json_success(['message' => 'API key is valid and working perfectly! Geocoding test successful.']);
                break;
                
            case 'REQUEST_DENIED':
                $error_msg = isset($data['error_message']) ? $data['error_message'] : 'Request denied';
                
                if (strpos($error_msg, 'expired') !== false) {
                    wp_send_json_error(['message' => 'API key expired. Generate a new key in Google Cloud Console.']);
                } elseif (strpos($error_msg, 'API key not valid') !== false) {
                    wp_send_json_error(['message' => 'API key invalid. Check for typos or regenerate the key.']);
                } elseif (strpos($error_msg, 'restricted') !== false || strpos($error_msg, 'Geocoding') !== false) {
                    wp_send_json_error(['message' => 'API key restrictions are blocking requests. Check your API restrictions in Google Cloud Console. Make sure Geocoding API is enabled and your server IP is allowed.']);
                } else {
                    wp_send_json_error(['message' => 'Request denied: ' . $error_msg]);
                }
                break;
                
            case 'OVER_QUERY_LIMIT':
                wp_send_json_error(['message' => 'API quota exceeded. Check your Google Cloud billing and usage limits.']);
                break;
                
            case 'INVALID_REQUEST':
                wp_send_json_error(['message' => 'Invalid request. Ensure Geocoding API is enabled in Google Cloud Console.']);
                break;
                
            case 'ZERO_RESULTS':
                wp_send_json_success(['message' => 'API key is valid and working. (Zero results for test address is normal)']);
                break;
                
            default:
                wp_send_json_error(['message' => 'Unexpected API response: ' . $data['status']]);
                break;
        }
    }

    /**
     * AJAX handler for testing Google Reviews API key
     */
    public function test_google_reviews_api_key() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_test_google_reviews_api_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key is required.']);
        }

        // Test with a known place ID (Google's Googleplex)
        $test_place_id = 'ChIJj61dQgK6j4AR4GeTYWZsKWw';
        
        $places_url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
            'place_id' => $test_place_id,
            'fields' => 'name,rating,reviews,business_status,user_ratings_total',
            'key' => $api_key
        ]);

        $response = wp_remote_get($places_url, [
            'timeout' => 15,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Failed to connect to Google Places API: ' . $response->get_error_message()]);
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (!$data) {
            wp_send_json_error(['message' => 'Invalid response from Google Places API']);
        }

        // Handle API response with detailed error messages
        switch ($data['status']) {
            case 'OK':
                wp_send_json_success(['message' => 'API key is valid and working perfectly! Places API test successful.']);
                break;
                
            case 'REQUEST_DENIED':
                $error_msg = isset($data['error_message']) ? $data['error_message'] : 'Request denied';
                
                if (strpos($error_msg, 'expired') !== false) {
                    wp_send_json_error(['message' => 'API key expired. Generate a new key in Google Cloud Console.']);
                } elseif (strpos($error_msg, 'API key not valid') !== false) {
                    wp_send_json_error(['message' => 'Invalid API key. Check your API key in Google Cloud Console.']);
                } elseif (strpos($error_msg, 'Places API') !== false) {
                    wp_send_json_error(['message' => 'Places API is not enabled. Enable it in Google Cloud Console → APIs & Services → Library.']);
                } else {
                    wp_send_json_error(['message' => 'Request denied: ' . $error_msg]);
                }
                break;
                
            case 'OVER_QUERY_LIMIT':
                wp_send_json_error(['message' => 'API quota exceeded. Check your Google Cloud billing and usage limits.']);
                break;
                
            case 'INVALID_REQUEST':
                wp_send_json_error(['message' => 'Invalid request. Ensure Places API is enabled in Google Cloud Console.']);
                break;
                
            case 'NOT_FOUND':
                wp_send_json_success(['message' => 'API key is valid and working. (Test place not found is normal)']);
                break;
                
            default:
                wp_send_json_error(['message' => 'Unexpected API response: ' . $data['status']]);
                break;
        }
    }

    /**
     * AJAX handler for resetting API usage statistics
     */
    public function ajax_reset_api_stats() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_reset_api_stats')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        // Reset statistics through the gateway if available
        if (class_exists('Listeo_Core_Google_Reviews_Gateway')) {
            $gateway = listeo_google_reviews_gateway();
            if ($gateway->is_enabled()) {
                $result = $gateway->reset_counters();
                if ($result) {
                    wp_send_json_success(['message' => 'API usage statistics have been reset successfully.']);
                } else {
                    wp_send_json_error(['message' => 'Failed to reset statistics. Please try again.']);
                }
            } else {
                wp_send_json_error(['message' => 'API Rate Limiting is disabled. Enable it to reset statistics.']);
            }
        } else {
            wp_send_json_error(['message' => 'Google Reviews Gateway not available.']);
        }
    }

    /**
     * Rating migration page
     */
    public function rating_migration_page() {
        // Get statistics
        global $wpdb;
        $total_listings = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'listing' AND post_status = 'publish'"
        );
        
        $without_rating = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_combined_rating' 
             WHERE p.post_type = 'listing' AND p.post_status = 'publish' 
             AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = 0)"
        );
        
        $with_google = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'listing' AND p.post_status = 'publish' 
             AND pm.meta_key = '_google_rating' AND pm.meta_value != '' AND CAST(pm.meta_value AS DECIMAL(3,2)) > 0"
        );
        
        $with_listeo = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID 
             WHERE p.post_type = 'listing' AND p.post_status = 'publish' 
             AND c.comment_approved = '1'"
        );
        
        $combined_ratings = $total_listings - $without_rating;
        
        ?>
        <div class="wrap">
            <h1>Combined Rating System Migration</h1>
            
            <div class="card">
                <h2>Rating Statistics</h2>
                <ul>
                    <li><strong>Total Listings:</strong> <?php echo $total_listings; ?></li>
                    <li><strong>Without Rating:</strong> <?php echo $without_rating; ?></li>
                    <li><strong>With Google Ratings:</strong> <?php echo $with_google; ?></li>
                    <li><strong>With Listeo Ratings:</strong> <?php echo $with_listeo; ?></li>
                    <li><strong>Combined Ratings:</strong> <?php echo $combined_ratings; ?></li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Migration Tool</h2>
                <p>This tool will calculate combined ratings for all listings by merging Google Places reviews with local Listeo reviews using a weighted average based on review counts.</p>
                
                <div id="migration-status" style="display: none;">
                    <div id="migration-loader" style="display: none; text-align: center; margin: 20px 0;">
                        <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                        <p style="margin-top: 10px;"><strong>Running migration...</strong></p>
                    </div>
                    <div id="migration-done" style="display: none; text-align: center; margin: 20px 0;">
                        <div style="font-size: 48px; color: #46b450; margin-bottom: 10px;">✓</div>
                        <p style="font-size: 18px; font-weight: bold; color: #46b450;">DONE</p>
                        <p id="migration-result"></p>
                    </div>
                </div>
                
                <p>
                    <button id="start-migration" class="button button-primary" onclick="startMigration()">
                        Start Rating Migration
                    </button>
                    <button id="stop-migration" class="button button-secondary" onclick="stopMigration()" style="display: none;">
                        Stop Migration
                    </button>
                </p>
            </div>
            
            <div class="card">
                <h2>How Combined Ratings Work</h2>
                <p><strong>Formula:</strong> Combined Rating = (Google Count × Google Rating + Listeo Count × Listeo Rating) / (Google Count + Listeo Count)</p>
                
                <h3>Example:</h3>
                <ul>
                    <li>Google: 1000 reviews, 4.0 average = 4000 stars</li>
                    <li>Listeo: 5 reviews, 3.0 average = 15 stars</li>
                    <li>Result: 4015 ÷ 1005 = 3.995 ≈ 4.0</li>
                </ul>
                
                <h3>Data Sources:</h3>
                <ul>
                    <li><strong>Google Rating:</strong> '_google_rating' meta field</li>
                    <li><strong>Google Count:</strong> '_google_review_count' meta field</li>
                    <li><strong>Listeo Rating:</strong> 'listeo-avg-rating' meta field</li>
                    <li><strong>Listeo Count:</strong> Approved comments count</li>
                </ul>
                
                <h3>Results Stored In:</h3>
                <ul>
                    <li><strong>Combined Rating:</strong> '_combined_rating' meta field</li>
                    <li><strong>Combined Count:</strong> '_combined_review_count' meta field</li>
                </ul>
            </div>
        </div>
        
        <script>
        let migrationActive = false;
        let currentOffset = 0;
        
        function startMigration() {
            migrationActive = true;
            currentOffset = 0;
            
            document.getElementById('start-migration').style.display = 'none';
            document.getElementById('stop-migration').style.display = 'inline-block';
            document.getElementById('migration-status').style.display = 'block';
            document.getElementById('migration-loader').style.display = 'block';
            document.getElementById('migration-done').style.display = 'none';
            
            processBatch();
        }
        
        function stopMigration() {
            migrationActive = false;
            document.getElementById('start-migration').style.display = 'inline-block';
            document.getElementById('stop-migration').style.display = 'none';
            document.getElementById('migration-loader').style.display = 'none';
            document.getElementById('migration-done').style.display = 'block';
            document.getElementById('migration-result').textContent = 'Migration stopped by user.';
        }
        
        function processBatch() {
            if (!migrationActive) return;
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                const data = response.data;
                                currentOffset = data.next_offset;
                                
                                if (data.completed) {
                                    migrationActive = false;
                                    document.getElementById('start-migration').style.display = 'inline-block';
                                    document.getElementById('stop-migration').style.display = 'none';
                                    document.getElementById('migration-loader').style.display = 'none';
                                    document.getElementById('migration-done').style.display = 'block';
                                    document.getElementById('migration-result').textContent = 
                                        'Migration completed successfully! Refresh the page to see updated statistics.';
                                } else if (migrationActive) {
                                    // Process next batch after a short delay
                                    setTimeout(processBatch, 1000);
                                }
                            } else {
                                document.getElementById('migration-loader').style.display = 'none';
                                document.getElementById('migration-done').style.display = 'block';
                                document.getElementById('migration-result').textContent = 
                                    'Error: ' + response.data;
                                stopMigration();
                            }
                        } catch (e) {
                            document.getElementById('migration-loader').style.display = 'none';
                            document.getElementById('migration-done').style.display = 'block';
                            document.getElementById('migration-result').textContent = 
                                'Error parsing response: ' + e.message;
                            stopMigration();
                        }
                    } else {
                        document.getElementById('migration-loader').style.display = 'none';
                        document.getElementById('migration-done').style.display = 'block';
                        document.getElementById('migration-result').textContent = 
                            'Network error: ' + xhr.status;ds
                        stopMigration();
                    }
                }
            };
            
            xhr.send(
                'action=listeo_migrate_ratings&' +
                'offset=' + currentOffset + '&' +
                '_wpnonce=<?php echo wp_create_nonce('listeo_migrate_ratings'); ?>'
            );
        }
        </script>
        <?php
    }
    
    /**
     * AJAX handler for rating migration
     */
    public function ajax_migrate_ratings() {
        // Check nonce and permissions
        if (!wp_verify_nonce($_POST['_wpnonce'], 'listeo_migrate_ratings') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 25; // Process 25 listings at a time to prevent timeout
        
        // Get the reviews instance and run migration batch
        $reviews_instance = Listeo_Core_Reviews::instance();
        $result = $reviews_instance->migrate_to_combined_ratings($batch_size, $offset);
        
        wp_send_json_success($result);
    }

    /**
     * Validate map center point setting
     * 
     * @param string $value The value to validate
     * @return string The validated value
     */
    public function validate_map_center_point($value) {
        // If value is empty or only whitespace, return default fallback
        if (empty(trim($value))) {
            return '29.577712,-45.629483';
        }
        
        // Check if value contains comma and at least 2 parts
        $parts = explode(',', $value);
        if (count($parts) !== 2) {
            return '29.577712,-45.629483';
        }
        
        // Validate that both parts are valid numbers
        $lat = trim($parts[0]);
        $lng = trim($parts[1]);
        
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return '29.577712,-45.629483';
        }
        
        // Validate latitude range (-90 to 90)
        $lat_float = floatval($lat);
        if ($lat_float < -90 || $lat_float > 90) {
            return '29.577712,-45.629483';
        }
        
        // Validate longitude range (-180 to 180)
        $lng_float = floatval($lng);
        if ($lng_float < -180 || $lng_float > 180) {
            return '29.577712,-45.629483';
        }
        
        // Return the valid value (trimmed and properly formatted)
        return $lat_float . ',' . $lng_float;
    }

    /**
     * Get navigation item icon based on title
     * @param string $title Section title
     * @return string Icon class
     */
    private function getNavItemIcon($title) {
        // Try to extract FontAwesome icon class from HTML
        if (preg_match('/<i[^>]*class="([^"]*fa[^"]*)"[^>]*>/', $title, $matches)) {
            return $matches[1];
        }
        
        // Fallback to text-based matching if no icon found in HTML
        $title_lower = strtolower(strip_tags($title));
        
        if (strpos($title_lower, 'general') !== false) {
            return 'fa fa-sliders-h';
        } elseif (strpos($title_lower, 'map') !== false) {
            return 'fa fa-map-marked-alt';
        } elseif (strpos($title_lower, 'submit') !== false) {
            return 'fa fa-plus-square';
        } elseif (strpos($title_lower, 'package') !== false) {
            return 'fa fa-cubes';
        } elseif (strpos($title_lower, 'single') !== false) {
            return 'fa fa-file';
        } elseif (strpos($title_lower, 'booking') !== false) {
            return 'fa fa-calendar-alt';
        } elseif (strpos($title_lower, 'browse') !== false || strpos($title_lower, 'search') !== false) {
            return 'fa fa-search-location';
        } elseif (strpos($title_lower, 'registration') !== false) {
            return 'fa fa-user-friends';
        } elseif (strpos($title_lower, 'dokan') !== false) {
            return 'fa fa-shopping-cart';
        } elseif (strpos($title_lower, 'campaign') !== false) {
            return 'fa fa-bullhorn';
        } elseif (strpos($title_lower, 'claim') !== false) {
            return 'fa fa-clipboard-check';
        } elseif (strpos($title_lower, 'stripe') !== false) {
            return 'fa fa-cc-stripe';
        } elseif (strpos($title_lower, 'paypal') !== false) {
            return 'fa fa-paypal';
        } elseif (strpos($title_lower, 'pages') !== false) {
            return 'fa fa-layer-group';
        } elseif (strpos($title_lower, 'email') !== false || strpos($title_lower, 'mail') !== false) {
            return 'fa fa-envelope';
        } elseif (strpos($title_lower, 'sms') !== false) {
            return 'fa fa-sms';
        } elseif (strpos($title_lower, 'listing') !== false) {
            return 'fa fa-list';
        } elseif (strpos($title_lower, 'review') !== false) {
            return 'fa fa-star';
        } elseif (strpos($title_lower, 'display') !== false) {
            return 'fa fa-desktop';
        } elseif (strpos($title_lower, 'performance') !== false) {
            return 'fa fa-tachometer-alt';
        } elseif (strpos($title_lower, 'integration') !== false) {
            return 'fa fa-plug';
        } else {
            return 'fa fa-cog';
        }
    }

    /**
     * Get current section title for header
     * @param string $tab Current tab
     * @return string Section title
     */
    private function getCurrentSectionTitle($tab) {
        if (empty($tab)) {
            // Return first section title
            if (is_array($this->settings) && !empty($this->settings)) {
                $first_section = reset($this->settings);
                return strip_tags($first_section['title']);
            }
            return __('General Settings', 'listeo_core');
        }
        
        if (isset($this->settings[$tab])) {
            return strip_tags($this->settings[$tab]['title']);
        }
        
        // Handle special cases
        if ($tab === 'license') {
            return __('License Activation', 'listeo_core');
        }
        
        if ($tab === 'listeo-site-health-tab') {
            return __('Health Check', 'listeo_core');
        }
        
        return __('Settings', 'listeo_core');
    }

    /**
     * Reorganize listing type taxonomies into a submenu structure
     */
    public function reorganize_listing_taxonomies() {
        global $menu, $submenu;

        // Check if we have the listing menu
        if (!isset($submenu['edit.php?post_type=listing'])) {
            return;
        }

        // Define listing type taxonomies to group
        $listing_type_taxonomies = array(
            'listing_category' => __('Global Categories', 'listeo_core'),
            'event_category' => __('Events Categories', 'listeo_core'),
            'service_category' => __('Service Categories', 'listeo_core'),
            'rental_category' => __('Rentals Categories', 'listeo_core'),
            'classifieds_category' => __('Classifieds Categories', 'listeo_core')
        );

        // Get custom listing types from the custom listing types system
        if (class_exists('Listeo_Core_Custom_Listing_Types')) {
            $custom_types_manager = Listeo_Core_Custom_Listing_Types::instance();
            $custom_types = $custom_types_manager->get_listing_types(true); // Get active types only

            foreach ($custom_types as $type) {
                if ($type->register_taxonomy && !in_array($type->slug, array('service', 'rental', 'event', 'classifieds'))) {
                    // WordPress has a 32-character limit for taxonomy slugs
                    // Apply the same truncation logic as in post types registration
                    $suffix = '_category';
                    $max_length = 32;
                    $base_slug = $type->slug;

                    if (strlen($base_slug . $suffix) > $max_length) {
                        $max_base_length = $max_length - strlen($suffix);
                        $base_slug = substr($type->slug, 0, $max_base_length);
                    }

                    $taxonomy_slug = $base_slug . $suffix;
                    $listing_type_taxonomies[$taxonomy_slug] = sprintf(__('%s Categories', 'listeo_core'), $type->plural_name);
                }
            }
        }

        // Find and collect taxonomy menu items to remove
        $taxonomy_items = array();
        foreach ($submenu['edit.php?post_type=listing'] as $key => $item) {
            foreach ($listing_type_taxonomies as $taxonomy => $label) {
                if (strpos($item[2], 'taxonomy=' . $taxonomy) !== false) {
                    $taxonomy_items[$taxonomy] = $item;
                    unset($submenu['edit.php?post_type=listing'][$key]);
                    break;
                }
            }
        }

        // Only add the submenu if we have taxonomy items
        if (!empty($taxonomy_items)) {
            // Add main "Categories" submenu item
            add_submenu_page(
                'edit.php?post_type=listing',
                __('Categories', 'listeo_core'),
                __('Categories', 'listeo_core'),
                'manage_categories',
                'listeo-type-categories',
                array($this, 'listing_type_categories_page')
            );

            // Move Categories before Add Listing by manipulating the submenu array
            if (isset($submenu['edit.php?post_type=listing'])) {
                $listing_submenu = $submenu['edit.php?post_type=listing'];
                $categories_item = null;
                $new_submenu = array();
                
                // Find and remove the Categories item
                foreach ($listing_submenu as $key => $item) {
                    if (strpos($item[2], 'listeo-type-categories') !== false) {
                        $categories_item = $item;
                        unset($listing_submenu[$key]);
                        break;
                    }
                }
                
                // Rebuild submenu with Categories in position 1
                if ($categories_item) {
                    $position = 0;
                    foreach ($listing_submenu as $key => $item) {
                        if ($position == 1) {
                            $new_submenu[] = $categories_item; // Insert Categories
                        }
                        $new_submenu[] = $item;
                        $position++;
                    }
                    
                    // If we didn't insert it yet (submenu too short), add it at position 1
                    if (!in_array($categories_item, $new_submenu)) {
                        array_splice($new_submenu, 1, 0, array($categories_item));
                    }
                    
                    $submenu['edit.php?post_type=listing'] = $new_submenu;
                }
            }

            // Store taxonomy items for use in the page
            $this->taxonomy_items = $taxonomy_items;
        }
    }

    /**
     * Enqueue admin styles for categories page
     */
    public function enqueue_categories_admin_styles($hook) {
        // Only load on our categories page
        if (isset($_GET['page']) && $_GET['page'] === 'listeo-type-categories') {
            wp_enqueue_style(
                'listeo-admin-categories',
                LISTEO_CORE_URL . 'assets/css/admin-categories.css',
                array(),
                '1.0.0'
            );
        }
    }

    /**
     * Display the listing type categories page with links to all taxonomies
     */
    public function listing_type_categories_page() {
        ?>
        <div class="listeo-categories-wrap">
            <h1><?php _e('Categories', 'listeo_core'); ?></h1>
            <p class="subtitle"><?php _e('Manage categories for different listing types. Click on any category type below to manage its terms.', 'listeo_core'); ?></p>
            
            <?php if (!empty($this->taxonomy_items)): ?>
                
                <?php 
                // Separate global categories from listing type categories
                $global_categories = array();
                $listing_type_categories = array();
                
                foreach ($this->taxonomy_items as $taxonomy => $item) {
                    if ($taxonomy === 'listing_category') {
                        $global_categories[$taxonomy] = $item;
                    } else {
                        $listing_type_categories[$taxonomy] = $item;
                    }
                }
                ?>
                
                <?php if (!empty($global_categories)): ?>
                    <div class="listeo-global-section">
                        <h2><?php _e('Global Categories', 'listeo_core'); ?></h2>
                        <p class="listeo-section-description">
                            <?php _e('This approach works best when listing types have common categories between them.', 'listeo_core'); ?>
                            <a href="https://docs.purethemes.net/listeo/knowledge-base/how-main-and-sub-categories-work/#heading-2" target="_blank">
                                <?php _e('Read more in docs', 'listeo_core'); ?> →
                            </a>
                        </p>
                        <?php foreach ($global_categories as $taxonomy => $item): ?>
                            <?php
                            $count = wp_count_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
                            $display_name = __('Global Categories', 'listeo_core');
                            ?>
                            <div class="listeo-global-card">
                                <div class="listeo-global-card-content">
                                    <h3><?php echo esc_html($display_name); ?></h3>
                                    <p><?php printf(_n('%d category', '%d categories', $count, 'listeo_core'), $count); ?></p>
                                </div>
                                <a href="<?php echo esc_url(admin_url($item[2])); ?>" class="listeo-manage-btn">
                                    <?php _e('Manage Categories', 'listeo_core'); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($listing_type_categories)): ?>
                    <div class="listeo-listing-types-section">
                        <h2><?php _e('Listing Types Categories', 'listeo_core'); ?></h2>
                        <p class="listeo-section-description">
                            <?php _e('This approach works well for unrelated listing types with distinct category structures.', 'listeo_core'); ?>
                            <a href="https://docs.purethemes.net/listeo/knowledge-base/how-main-and-sub-categories-work/#heading-3" target="_blank">
                                <?php _e('Read more in docs', 'listeo_core'); ?> →
                            </a>
                        </p>
                        <div class="listeo-cards-grid">
                            <?php foreach ($listing_type_categories as $taxonomy => $item): ?>
                                <?php
                                $count = wp_count_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
                                
                                // Get the proper display name for the taxonomy
                            $display_name = $item[0]; // Default to original menu text
                            if ($taxonomy === 'event_category') {
                                $display_name = __('Events Categories', 'listeo_core');
                            } elseif ($taxonomy === 'service_category') {
                                $display_name = __('Service Categories', 'listeo_core');
                            } elseif ($taxonomy === 'rental_category') {
                                $display_name = __('Rentals Categories', 'listeo_core');
                            } elseif ($taxonomy === 'classifieds_category') {
                                $display_name = __('Classifieds Categories', 'listeo_core');
                            } else {
                                // For custom listing types, check if we can get a better name
                                if (class_exists('Listeo_Core_Custom_Listing_Types')) {
                                    $custom_types_manager = Listeo_Core_Custom_Listing_Types::instance();
                                    $custom_types = $custom_types_manager->get_listing_types(true);
                                    foreach ($custom_types as $type) {
                                        // Apply same truncation logic for matching
                                        $suffix = '_category';
                                        $max_length = 32;
                                        $base_slug = $type->slug;

                                        if (strlen($base_slug . $suffix) > $max_length) {
                                            $max_base_length = $max_length - strlen($suffix);
                                            $base_slug = substr($type->slug, 0, $max_base_length);
                                        }

                                        if ($taxonomy === $base_slug . $suffix) {
                                            $display_name = sprintf(__('%s Categories', 'listeo_core'), $type->plural_name);
                                            break;
                                        }
                                    }
                                }
                                }
                                ?>
                                <div class="listeo-category-card">
                                    <div class="listeo-card-header">
                                        <span class="listeo-card-title"><?php echo esc_html($display_name); ?></span>
                                    </div>
                                    <p class="listeo-card-count">
                                        <?php printf(_n('%d category', '%d categories', $count, 'listeo_core'), $count); ?>
                                    </p>
                                    <a href="<?php echo esc_url(admin_url($item[2])); ?>" class="listeo-manage-btn">
                                        <?php _e('Manage Categories', 'listeo_core'); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="listeo-other-management">
                    <h2><?php _e('Other Listing Management', 'listeo_core'); ?></h2>
                    <div class="listeo-management-buttons">
                        <a href="<?php echo admin_url('admin.php?page=listeo-listing-types'); ?>" class="listeo-types-btn">
                            <span class="dashicons dashicons-category"></span>
                            <?php _e('Manage Listing Types', 'listeo_core'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=listing_feature&post_type=listing'); ?>" class="listeo-feature-btn">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php _e('Manage Features', 'listeo_core'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=region&post_type=listing'); ?>" class="listeo-region-btn">
                            <span class="dashicons dashicons-location"></span>
                            <?php _e('Manage Regions', 'listeo_core'); ?>
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('No listing type categories found.', 'listeo_core'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Display Google API usage statistics
     */
    public function display_google_api_stats($field) {
        // Check if gateway class exists and is enabled
        if (!class_exists('Listeo_Core_Google_Reviews_Gateway')) {
            echo '<p>' . __('Gateway not available.', 'listeo_core') . '</p>';
            return;
        }

        $gateway = listeo_google_reviews_gateway();

        if (!$gateway->is_enabled()) {
            echo '<div class="notice notice-warning inline">';
            echo '<p>' . __('API Rate Limiting is disabled. Enable it above to see usage statistics.', 'listeo_core') . '</p>';
            echo '</div>';
            return;
        }

        // Get stats for past hour and 24 hours
        $hour_stats = $gateway->get_usage_stats('hour');
        $day_stats = $gateway->get_usage_stats('day');

        // Check bot protection status
        $bot_protection_enabled = (get_option('listeo_google_bot_protection_enabled') === 'on');

        // Get rate limits
        $limits = array(
            'per_hour' => get_option('listeo_google_limit_per_hour', 20),
            'per_day' => get_option('listeo_google_limit_per_day', 500),
        );


        ?>
        <div class="listeo-api-stats-container">
            <style>
                .listeo-api-stats-container {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 6px;
                    border: 1px solid #e1e5e9;
                    margin-top: 10px;
                }
                .listeo-stats-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin-bottom: 15px;
                }
                .listeo-stat-box {
                    background: white;
                    padding: 12px;
                    border-radius: 4px;
                    border: 1px solid #ddd;
                }
                .listeo-stat-box h4 {
                    margin: 0 0 10px 0;
                    color: #333;
                    font-size: 14px;
                }
                .listeo-stat-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 5px;
                    font-size: 13px;
                }
                .listeo-stat-value {
                    font-weight: bold;
                    color: #0073aa;
                }
                .listeo-stat-value.warning {
                    color: #0073aa;
                }
                .listeo-usage-bar {
                    width: 100%;
                    height: 6px;
                    background: #e1e5e9;
                    border-radius: 3px;
                    overflow: hidden;
                    margin: 5px 0;
                }
                .listeo-usage-fill {
                    height: 100%;
                    background: #00a32a;
                    transition: width 0.3s ease;
                }
                .listeo-usage-fill.warning {
                    background: #ffb900;
                }
                .listeo-usage-fill.danger {
                    background: #d63638;
                }
            </style>

            <div class="listeo-stats-grid">
                <div class="listeo-stat-box">
                    <h4><span class="dashicons dashicons-clock"></span> <?php _e('Past Hour', 'listeo_core'); ?></h4>
                    <?php if ($hour_stats): ?>
                        <?php
                        $hour_api_calls = $hour_stats->total_calls - $hour_stats->cached_calls - $hour_stats->blocked_calls;
                        $hour_percentage = ($hour_api_calls / $limits['per_hour']) * 100;
                        $hour_bar_class = $hour_percentage > 80 ? 'danger' : ($hour_percentage > 60 ? 'warning' : '');
                        $hour_value_class = $hour_stats->blocked_calls > 0 ? 'warning' : '';
                        ?>
                        <div class="listeo-stat-row">
                            <span><?php _e('API Calls:', 'listeo_core'); ?></span>
                            <span class="listeo-stat-value <?php echo $hour_value_class; ?>">
                                <?php echo intval($hour_api_calls); ?> / <?php echo $limits['per_hour']; ?>
                            </span>
                        </div>
                        <div class="listeo-usage-bar">
                            <div class="listeo-usage-fill <?php echo $hour_bar_class; ?>" style="width: <?php echo min(100, $hour_percentage); ?>%;"></div>
                        </div>
                        <div class="listeo-stat-row">
                            <span><?php _e('Cache Hits:', 'listeo_core'); ?></span>
                            <span class="listeo-stat-value"><?php echo intval($hour_stats->cached_calls); ?></span>
                        </div>
                        <div class="listeo-stat-row">
                            <span><?php _e('Blocked:', 'listeo_core'); ?></span>
                            <span class="listeo-stat-value <?php echo $hour_stats->blocked_calls > 0 ? 'warning' : ''; ?>">
                                <?php echo intval($hour_stats->blocked_calls); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No data available yet.', 'listeo_core'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="listeo-stat-box">
                    <h4><span class="dashicons dashicons-calendar-alt"></span> <?php _e('Past 24 Hours', 'listeo_core'); ?></h4>
                    <?php if ($day_stats): ?>
                        <?php
                        $day_api_calls = $day_stats->total_calls - $day_stats->cached_calls - $day_stats->blocked_calls;
                        $day_percentage = ($day_api_calls / $limits['per_day']) * 100;
                        $day_bar_class = $day_percentage > 80 ? 'danger' : ($day_percentage > 60 ? 'warning' : '');
                        $day_value_class = $day_stats->blocked_calls > 0 ? 'warning' : '';
                        ?>
                        <div class="listeo-stat-row">
                            <span><?php _e('API Calls:', 'listeo_core'); ?></span>
                            <span class="listeo-stat-value <?php echo $day_value_class; ?>">
                                <?php echo intval($day_api_calls); ?> / <?php echo $limits['per_day']; ?>
                            </span>
                        </div>
                        <div class="listeo-usage-bar">
                            <div class="listeo-usage-fill <?php echo $day_bar_class; ?>" style="width: <?php echo min(100, $day_percentage); ?>%;"></div>
                        </div>
                        <div class="listeo-stat-row">
                            <span><?php _e('Cache Hits:', 'listeo_core'); ?></span>
                            <span class="listeo-stat-value"><?php echo intval($day_stats->cached_calls); ?></span>
                        </div>
                        <div class="listeo-stat-row">
                            <span><?php _e('Blocked:', 'listeo_core'); ?></span>
                            <span class="listeo-stat-value <?php echo $day_stats->blocked_calls > 0 ? 'warning' : ''; ?>">
                                <?php echo intval($day_stats->blocked_calls); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No data available yet.', 'listeo_core'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin: 0 0 10px 0; border: 1px solid #e0e0e0; border-radius: 5px;" class="notice notice-notice inline">
                <p>
                    <strong><?php _e('When Google API calls are made?', 'listeo_core'); ?></strong> -
                    <?php _e('On listing page view but only when rating is not cached – transient cache expires by default: 1 day, configurable up to 999 days.', 'listeo_core'); ?>
                </p>
            </div>

            <!-- Bot Protection Status -->
            <?php if ($bot_protection_enabled): ?>
                <div style="margin: 0 0 10px 0; display: none;" class="notice notice-success inline">
                    <p>
                        <strong><?php _e('🤖 Active Bot Protection', 'listeo_core'); ?></strong> -
                        <?php _e('Known crawlers and bots are being blocked from triggering API calls. Note: Sites with many listings may still experience crawler-triggered API calls from unknown bots or user agents not in the detection list.', 'listeo_core'); ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="display: none;" class="notice notice-warning inline">
                    <p>
                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                        <strong><?php _e('Bot Protection: DISABLED', 'listeo_core'); ?></strong> -
                        <?php _e('Enable above to prevent crawlers from wasting API quota.', 'listeo_core'); ?>
                    </p>
                </div>
            <?php endif; ?>


            <p class="description">
                <strong><?php _e('Note:', 'listeo_core'); ?></strong>
                <?php _e('These statistics help monitor Google Places API usage to prevent unexpected charges. API calls are automatically cached to reduce costs.', 'listeo_core'); ?>
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#listeo-reset-api-stats').on('click', function(e) {
                e.preventDefault();

                if (!confirm('<?php _e('Are you sure you want to reset API usage statistics? This will clear all tracking data.', 'listeo_core'); ?>')) {
                    return;
                }

                var $button = $(this);
                var $icon = $button.find('.dashicons');

                // Show loading state
                $icon.removeClass('dashicons-update').addClass('dashicons-update spin');
                $button.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'listeo_reset_api_stats',
                    nonce: '<?php echo wp_create_nonce('listeo_reset_api_stats'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Reload the page to show updated stats
                        location.reload();
                    } else {
                        alert('<?php _e('Error resetting statistics. Please try again.', 'listeo_core'); ?>');
                        // Reset button state
                        $icon.removeClass('dashicons-update spin').addClass('dashicons-update');
                        $button.prop('disabled', false);
                    }
                }).fail(function() {
                    alert('<?php _e('Error resetting statistics. Please try again.', 'listeo_core'); ?>');
                    // Reset button state
                    $icon.removeClass('dashicons-update spin').addClass('dashicons-update');
                    $button.prop('disabled', false);
                });
            });
        });
        </script>

        <style>
        .dashicons.spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
    }

}

$settings = new Listeo_Core_Admin();
