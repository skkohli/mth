<?php

// Load environment validator
require_once get_template_directory() . '/inc/class-environment-validator.php';

/**
 * Check if the current site is a staging/development environment
 * Result is cached in wp_options with an explicit expiry.
 *
 * @return bool True if staging/dev environment, false otherwise
 */
function listeo_is_staging_environment() {
    
    // Cache key includes domain hash to auto-invalidate on migration
    $cache_key = 'listeo_env_' . md5(site_url());
    $cached = b472b0Base::GetPersistentCache($cache_key);

    if ($cached !== false) {
        return $cached === '1';
    }

    $result = class_exists('Listeo_Environment_Validator')
        ? Listeo_Environment_Validator::validate()
        : false;

    // Get cache TTL from validator
    $ttl = class_exists('Listeo_Environment_Validator')
        ? Listeo_Environment_Validator::get_cache_ttl()
        : 12 * HOUR_IN_SECONDS;

    b472b0Base::SetPersistentCache($cache_key, $result ? '1' : '0', $ttl);

    return $result;
}

class Listeo {
	
  public $plugin_file = __FILE__;
	
  public $responseObj;
	
  public $licenseMessage;
	
  public $showMessage = false;
	
  public $slug = "listeo";
  public $_token = "listeo";
	
  public $settings = array();


  /**
   * Check if running in staging environment (public method for external use)
   */
  public $is_staging = false;

  function __construct() {

		add_action( 'admin_print_styles', [ $this, 'SetAdminStyle' ] );
    add_action( 'admin_post_Listeo_el_activate_license', [ $this, 'action_activate_license' ] );
    add_action( 'wp_ajax_listeo_revalidate_license_ajax', [ $this, 'ajax_revalidate_license' ] );
    add_action( 'wp_ajax_listeo_remove_license_ajax', [ $this, 'ajax_remove_license' ] );

    // Clear staging cache on license page visit to ensure fresh detection after migration
    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'listeo_license') {
        $cache_key = 'listeo_env_' . md5(site_url());
        b472b0Base::DeletePersistentCache($cache_key);
    }

    // STAGING BYPASS: Check if this is a staging/development environment
    if (listeo_is_staging_environment()) {
        $this->is_staging = true;

        // Create a fake valid license response for staging
        // Note: Don't use __() here - translations aren't loaded yet at this point
        $stored_license_key = trim((string) get_option("Listeo_lic_Key", ""));
        $this->responseObj = new stdClass();
        $this->responseObj->is_valid = true;
        $this->responseObj->license_title = 'Staging License (Auto-Activated)';
        $this->responseObj->license_key = $stored_license_key ? $stored_license_key : 'STAGING-' . substr(md5(site_url()), 0, 16);
        $this->responseObj->expire_date = 'No Expiry';
        $this->responseObj->support_end = 'N/A - Staging';
        $this->responseObj->msg = 'Staging environment - license validation bypassed';

        // Register as activated for staging
        add_action('admin_menu', [$this, 'ActiveAdminMenu'], 99999);
        add_action('admin_post_listeo_reset_license_data', [$this, 'action_reset_license_data']);
        add_action('admin_post_Listeo_el_deactivate_license', [$this, 'action_staging_recheck']);
        add_action('wp_ajax_listeo_deactivate_license_ajax', [$this, 'ajax_staging_recheck']);

        // Show staging notice in admin (translations are safe here - runs after init)
        add_action('admin_notices', function() {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'listeo') !== false) {
                $host = parse_url(site_url(), PHP_URL_HOST);
                echo '<div class="notice notice-info"><p><strong>' .
                     esc_html__('Staging Environment:', 'listeo') . '</strong> ' .
                     esc_html__('License validation is bypassed for', 'listeo') . ' <code>' .
                     esc_html($host) . '</code>. ' .
                     esc_html__('This will not consume your license activation slot.', 'listeo') . '</p></div>';
            }
        });

        return; // Exit early - no license check needed for staging
    }

    $licenseKey   = get_option("Listeo_lic_Key","");
    $liceEmail    = get_option( "Listeo_lic_email","");

    $templateDir  = get_template_directory(); //or dirname(__FILE__);

    // First check the persistent 7-day wp_options cache.
    $cache_key = 'listeo_license_cache_' . md5(site_url() . $licenseKey . $liceEmail);
    $cached_result = b472b0Base::GetPersistentCache($cache_key);
    
    // If we have cached result, use it without making any API calls
    if ($cached_result !== false && is_array($cached_result) && !empty($licenseKey)) {
        if (isset($cached_result['is_valid']) && $cached_result['is_valid']) {
            // License is valid from cache
            $this->responseObj = isset($cached_result['response']) ? $cached_result['response'] : null;
            $this->licenseMessage = isset($cached_result['message']) ? $cached_result['message'] : '';
            
            add_action( 'admin_menu', [$this,'ActiveAdminMenu'],99999);
            add_action( 'admin_post_Listeo_el_deactivate_license', [ $this, 'action_deactivate_license' ] );
            add_action( 'admin_post_listeo_reset_license_data', [ $this, 'action_reset_license_data' ] );
            add_action( 'admin_post_listeo_deactivate_license', [ $this, 'action_deactivate_license_simple' ] );
            add_action( 'wp_ajax_listeo_deactivate_license_ajax', [ $this, 'ajax_deactivate_license' ] );
        } else {
            // License is invalid from cache
            $this->responseObj = isset($cached_result['response']) ? $cached_result['response'] : null;
            $this->licenseMessage = isset($cached_result['message']) ? $cached_result['message'] : '';
            $this->showMessage = !empty($this->licenseMessage);
            
            add_action( 'admin_post_Listeo_el_activate_license', [ $this, 'action_activate_license' ] );
            add_action( 'admin_post_listeo_reset_license_data', [ $this, 'action_reset_license_data' ] );
            add_action( 'admin_post_listeo_deactivate_license', [ $this, 'action_deactivate_license_simple' ] );
            add_action( 'wp_ajax_listeo_deactivate_license_ajax', [ $this, 'ajax_deactivate_license' ] );
            add_action( 'admin_menu', [$this,'InactiveMenu']);
        }
        return; // Exit early - no API call needed
    }
    
    // Only check license in very specific cases
    $should_check_license = false;
    
    // NEVER check during AJAX (except license activation)
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        // Only for license activation actions
        if ($action === 'Listeo_el_activate_license') {
            $should_check_license = true;
        }
    }
    // NEVER check during CRON
    elseif (defined('DOING_CRON') && DOING_CRON) {
        $should_check_license = false;
    }
    // NEVER check during AUTOSAVE
    elseif (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        $should_check_license = false;
    }
    // Only check on license page or when explicitly activating
    elseif (is_admin()) {
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        // Only on license activation page
        if ($page === 'listeo_license' || $page === 'listeo_settings') {
            // But still respect cache if it exists
            if ($cached_result === false && !empty($licenseKey)) {
                $should_check_license = true;
            }
        }
    }
    
    if($should_check_license && b472b0Base::CheckWPPlugin( $licenseKey, $liceEmail, $this->licenseMessage, $this->responseObj, $templateDir."/style.css")){
        b472b0Base::SetPersistentCache($cache_key, [
            'is_valid' => true,
            'response' => $this->responseObj,
            'message' => $this->licenseMessage,
            'time' => time()
        ]);
        
    	add_action( 'admin_menu', [$this,'ActiveAdminMenu'],99999);
			add_action( 'admin_post_Listeo_el_deactivate_license', [ $this, 'action_deactivate_license' ] );
			add_action( 'admin_post_listeo_reset_license_data', [ $this, 'action_reset_license_data' ] );
			add_action( 'admin_post_listeo_deactivate_license', [ $this, 'action_deactivate_license_simple' ] );
			add_action( 'wp_ajax_listeo_deactivate_license_ajax', [ $this, 'ajax_deactivate_license' ] );
			//$this->licenselMessage=$this->mess;
			//***Write you plugin's code here***

		} else {
			
      // Only show license activation UI if we actually checked and it failed
	      if($should_check_license && !empty($licenseKey) && !empty($this->licenseMessage)){
				b472b0Base::SetPersistentCache($cache_key, [
					'is_valid' => false,
					'response' => $this->responseObj,
					'message' => $this->licenseMessage,
					'time' => time()
				]);
				
				$this->showMessage=true;

			}
			
     // update_option("Listeo_lic_Key","") || add_option("Listeo_lic_Key","");
			
      add_action( 'admin_post_Listeo_el_activate_license', [ $this, 'action_activate_license' ] );
			add_action( 'admin_post_listeo_reset_license_data', [ $this, 'action_reset_license_data' ] );
			add_action( 'admin_post_listeo_deactivate_license', [ $this, 'action_deactivate_license_simple' ] );
			add_action( 'wp_ajax_listeo_deactivate_license_ajax', [ $this, 'ajax_deactivate_license' ] );
			
      add_action( 'admin_menu', [$this,'InactiveMenu']);
		}
  }



	function SetAdminStyle() {
		  
      wp_register_style( "ListeoLic", get_theme_file_uri("/css/admin.css"),10);
		  wp_enqueue_style( "ListeoLic" );

		  $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		  
		  // Load modern admin CSS for license page
		  $plugin_css_path = plugins_url('/listeo-core/assets/css/listeo-modern-admin.css');
		  if (file_exists(WP_PLUGIN_DIR . '/listeo-core/assets/css/listeo-modern-admin.css')) {
		      wp_register_style( "ListeoModernAdmin", $plugin_css_path, array(), '1.0.0');
		      wp_enqueue_style( "ListeoModernAdmin" );
		  }

		}

	  function ActiveAdminMenu(){
		 
		//add_menu_page (  "Listeo", "Listeo", "activate_plugins", $this->slug, [$this,"Activated"], " dashicons-star-filled ");
		//add_submenu_page(  $this->slug, "Listeo License", "License Info", "activate_plugins",  $this->slug."_license", [$this,"Activated"] );
    add_submenu_page('listeo_settings', 'License', 'License', 'manage_options', $this->slug."_license",  array( $this, 'Activated' ) ); 
	}

	function InactiveMenu() {
		  //add_menu_page( "Listeo", "Listeo", 'activate_plugins', $this->slug,  [$this,"LicenseForm"], " dashicons-star-filled " );
	   add_submenu_page('listeo_settings', 'License', 'License', 'manage_options', $this->slug."_license",  array( $this, 'LicenseForm' ) ); 	
	}
	
	function action_activate_license(){

		check_admin_referer( 'el-license' );
		
		$licenseKey=!empty($_POST['el_license_key'])?sanitize_text_field($_POST['el_license_key']):"";
		$licenseEmail=!empty($_POST['el_license_email'])?sanitize_email($_POST['el_license_email']):"";
		$templateDir = get_template_directory();
		
		// Prevent duplicate submissions
		$submission_key = 'listeo_license_submission_' . md5($licenseKey . $licenseEmail . get_current_user_id());
		if (b472b0Base::HasPersistentLock($submission_key)) {
			wp_safe_redirect(admin_url( 'admin.php?page=listeo_license&message=processing'));
			exit;
		}

		// Set submission lock for 60 seconds
		b472b0Base::SetPersistentLock($submission_key, 60);
		
		// Store original values to check if activation succeeded
		update_option("Listeo_lic_Key",$licenseKey);
		update_option("Listeo_lic_email",$licenseEmail);
		update_option('_site_transient_update_themes','');
		$this->clear_theme_update_cache();
		
		// Clear persistent and legacy cache before fresh activation.
		$this->clear_legacy_license_response();
		$cache_key = 'listeo_license_cache_' . md5(site_url() . $licenseKey . $licenseEmail);
		b472b0Base::DeletePersistentCache($cache_key);
		delete_transient('listeo_license_180_' . md5(site_url() . $licenseKey . $licenseEmail));

		$validation_cache_key = 'listeo_license_valid_' . md5($licenseKey . $licenseEmail . site_url());
		b472b0Base::DeletePersistentCache($validation_cache_key);
		delete_transient($validation_cache_key);

		b472b0Base::DeletePersistentCacheByPrefix('listeo_api_request_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_proxy_request_');

		// Clear legacy transient caches.
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_license_180_%' OR option_name LIKE '_transient_listeo_license_valid_%' OR option_name LIKE '_transient_listeo_license_check_lock_%' OR option_name LIKE '_transient_listeo_api_%' OR option_name LIKE '_transient_listeo_proxy_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listeo_license_180_%' OR option_name LIKE '_transient_timeout_listeo_license_valid_%' OR option_name LIKE '_transient_timeout_listeo_license_check_lock_%' OR option_name LIKE '_transient_timeout_listeo_api_%' OR option_name LIKE '_transient_timeout_listeo_proxy_%'");
		
		// Add debugging
		update_option('listeo_last_license_attempt', array(
			'key' => substr($licenseKey, 0, 5) . '...' . substr($licenseKey, -5),
			'email' => $licenseEmail,
			'time' => current_time('mysql'),
		));

		$activationMessage = "";
		$activationResponse = null;
		b472b0Base::CheckWPPlugin($licenseKey, $licenseEmail, $activationMessage, $activationResponse, $templateDir . "/style.css");
		delete_option('listeo_core_remote_state');
		delete_option('listeo_core_remote_fallback');
		
		// Clear the submission lock
		b472b0Base::DeletePersistentLock($submission_key);
		
		wp_safe_redirect(admin_url( 'admin.php?page=listeo_license'));
	}


	function action_deactivate_license() {
	
  	check_admin_referer( 'el-license' );

		$this->remove_license_locally();
    	wp_safe_redirect(admin_url( 'admin.php?page=listeo_license'));
    }
    
    private function clear_license_transients() {
		global $wpdb;
		
		// Clear persistent wp_options caches.
		b472b0Base::DeletePersistentCacheByPrefix('listeo_license_cache_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_license_valid_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_api_request_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_proxy_request_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_api_lock_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_license_check_lock_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_license_submission_');
		b472b0Base::DeletePersistentCacheByPrefix('listeo_env_');

		// Clear legacy license-related transients.
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_license_%' OR option_name LIKE '_transient_listeo_api_%' OR option_name LIKE '_transient_listeo_proxy_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listeo_license_%' OR option_name LIKE '_transient_timeout_listeo_api_%' OR option_name LIKE '_transient_timeout_listeo_proxy_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_debug_%'");
		
		// Clear the current legacy 180-day transient cache.
		$licenseKey = get_option("Listeo_lic_Key", "");
		$liceEmail = get_option("Listeo_lic_email", "");
		if (!empty($licenseKey) && !empty($liceEmail)) {
			$cache_key = 'listeo_license_180_' . md5(site_url() . $licenseKey . $liceEmail);
			delete_transient($cache_key);
		}
		
		// Also clear potential old 180-day transient caches (in case license key changed).
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_license_180_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listeo_license_180_%'");
		
		// Clear API request locks
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_api_lock_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listeo_api_lock_%'");
		
		// Clear license check locks
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_license_check_lock_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listeo_license_check_lock_%'");

		// Clear staging environment cache
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_env_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listeo_env_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'listeo_core_environment_sync_%'");

		$this->clear_theme_update_cache();
		}

	private function clear_theme_update_cache() {
		if (class_exists('Listeo_Theme_Updater') && method_exists('Listeo_Theme_Updater', 'clear_update_cache')) {
			Listeo_Theme_Updater::clear_update_cache();
			return;
		}

		delete_transient('listeo_update_manifest');
		delete_transient('listeo_update_error');
		delete_site_transient('update_themes');

		if (function_exists('wp_clean_themes_cache')) {
			wp_clean_themes_cache();
		}
	}

	private function clear_legacy_license_response() {
		delete_option('Listeo_license_response_obj');

		$base_files = array(
			get_template_directory() . "/style.css",
			__FILE__,
		);

		foreach (array_unique(array_filter($base_files)) as $base_file) {
			$key = hash('crc32b', site_url() . $base_file . '2' . 'listeo' . 'B8E31EE0281E5FE7' . 'LIC');
			delete_option($key);
		}
	}

	private function remove_license_locally() {
		delete_option('Listeo_lic_Key');
		delete_option('Listeo_lic_email');
		delete_option('listeo_license_key_activated');
		delete_option('listeo_offline_activation');
		delete_option('listeo_proxy_validation');
		delete_option('listeo_last_proxy_validation');
		delete_option('listeo_activation_date');
		delete_option('listeo_last_license_attempt');
		delete_option('listeo_core_remote_state');
		delete_option('listeo_core_remote_fallback');
		delete_option('_site_transient_update_themes');
		delete_option('_site_transient_update_plugins');

		$this->clear_legacy_license_response();
		$this->clear_license_transients();
	}

    /**
     * Handle staging recheck - forces environment recheck
     */
    function action_staging_recheck() {
        check_admin_referer('el-license');

        // Clear staging environment cache to force fresh detection
        $cache_key = 'listeo_env_' . md5(site_url());
        b472b0Base::DeletePersistentCache($cache_key);

        // Also clear license caches
        $this->clear_license_transients();

        wp_safe_redirect(admin_url('admin.php?page=listeo_license&recheck=1'));
        exit;
    }

    /**
     * AJAX handler for staging recheck - forces environment recheck
     */
    function ajax_staging_recheck() {
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_deactivate_license_ajax')) {
            wp_send_json_error('Security verification failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Clear staging environment cache
        $cache_key = 'listeo_env_' . md5(site_url());
        b472b0Base::DeletePersistentCache($cache_key);

        // Clear license caches
        $this->clear_license_transients();

        wp_send_json_success('Environment will be rechecked on next page load');
    }
    
    function action_reset_license_data() {
		check_admin_referer( 'listeo_reset_license_nonce_action', 'listeo_reset_license_nonce' );
		
		if (!current_user_can('manage_options')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		
		$this->remove_license_locally();
		
		wp_safe_redirect(admin_url( 'admin.php?page=listeo_license&reset=success'));
	}
	
    /**
     * Simple local license removal - removes license data from this site only.
     */
    function action_deactivate_license_simple() {
        check_admin_referer( 'listeo_deactivate_license_nonce_action', 'listeo_deactivate_license_nonce' );
        
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        $this->remove_license_locally();
        
        wp_safe_redirect(admin_url( 'themes.php?page=listeo_license&removed=success'));
        exit;
    }
    
    /**
     * Legacy AJAX handler for local license removal.
     */
    function ajax_deactivate_license() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_deactivate_license_ajax')) {
            wp_send_json_error('Security verification failed');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        try {
            $this->remove_license_locally();
            
            wp_send_json_success('License removed from this site');
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to remove local license: ' . $e->getMessage());
        }
    }

    function ajax_revalidate_license() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_revalidate_license_ajax')) {
            wp_send_json_error(array('message' => 'Security verification failed'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        if (!class_exists('Listeo_Core_Environment_Sync')) {
            wp_send_json_error(array('message' => 'License revalidation is not available'));
            return;
        }

        $state = Listeo_Core_Environment_Sync::revalidate_now();

        if (Listeo_Core_Environment_Sync::is_available()) {
            wp_send_json_success(array(
                'message' => 'License revalidated successfully',
                'state' => $state,
            ));
            return;
        }

        $message = !empty($state['last_error']) ? $state['last_error'] : '';
        if (!$message && !empty($state['message'])) {
            $message = $state['message'];
        }
        if (!$message && !empty($state['reason'])) {
            $message = ucfirst(str_replace('_', ' ', $state['reason']));
        }

        wp_send_json_error(array(
            'message' => $message ? $message : 'License revalidation failed',
            'state' => $state,
        ));
    }

    function ajax_remove_license() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'listeo_remove_license_ajax')) {
            wp_send_json_error(array('message' => 'Security verification failed'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $this->remove_license_locally();

        wp_send_json_success(array(
            'message' => 'License removed from this site',
        ));
    }
	
  function Activated(){
        
        $tab = '';
        if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
            $tab .= $_GET['tab'];
        }
        if ('' === trim((string) get_option('Listeo_lic_Key', ''))) {
            $this->LicenseForm();
            return;
        }
        $remote_state_available = class_exists('Listeo_Core_Environment_Sync') ? Listeo_Core_Environment_Sync::is_available() : true;

        // Build modern license status page
        ob_start(); ?>
        
        <div class="listeo-license-modern">
            <div class="listeo-license-container">
                <div class="listeo-license-header">
                    <h1 class="listeo-license-title">License Information</h1>
                    <p class="listeo-license-subtitle">Your Listeo theme license is active and ready to use</p>
                </div>

                <?php
                // Show success message if license was reset
                if (isset($_GET['reset']) && $_GET['reset'] == 'success') { ?>
                    <div class="listeo-license-notification listeo-license-success">
                        <strong>✅ License data has been successfully reset!</strong> You can now test the setup wizard or activate a new license.
                    </div>
                <?php }
                
                // Show success message if license was removed locally.
                if (isset($_GET['removed']) && $_GET['removed'] == 'success') { ?>
                    <div class="listeo-license-notification listeo-license-success">
                        <strong>License has been removed locally.</strong> To move this license to another domain, use the PureThemes license page.
                    </div>
                <?php } ?>

                <div class="listeo-license-card">
                    <div class="listeo-license-icon-container">
                        <div class="listeo-license-icon"></div>
                    </div>
                    
                    <h2 class="listeo-license-card-title">License Status</h2>
                    <p class="listeo-license-card-subtitle">Your license details and current status</p>

                    <ul class="listeo-license-status-list">
                        <li class="listeo-license-status-item">
                            <span class="listeo-license-status-label">Status</span>
                            <span class="listeo-license-status-value">
                                <?php if ( $this->responseObj->is_valid ) : ?>
                                    <span class="listeo-license-valid">Valid</span>
                                    <?php if ( get_option('listeo_offline_activation') === 'yes' ) : ?>
                                        <span style="color: #FF8C00; font-weight: normal; margin-left: 5px;">(Offline Activation)</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="listeo-license-invalid">Invalid</span>
                                <?php endif; ?>
                            </span>
                        </li>

                        <li class="listeo-license-status-item">
                            <span class="listeo-license-status-label">Your License Key</span>
                            <span class="listeo-license-status-value" style="font-family: monospace;">
                                <?php echo esc_attr( substr($this->responseObj->license_key,0,9)."XXXXXXXX-XXXXXXXX".substr($this->responseObj->license_key,-9) ); ?>
                            </span>
                        </li>
                    </ul>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="Listeo_el_deactivate_license"/>
                        <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
                        <?php wp_nonce_field( 'el-license' ); ?>

                        <div>
                            <a href="<?php echo esc_url( add_query_arg( 'purchase', $this->responseObj->license_key, 'https://purethemes.net/license/' ) ); ?>" target="_blank"
                               class="listeo-license-action-btn listeo-license-transfer-btn">
                                Transfer License to Another Domain
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-left: 0; vertical-align: -3px;"><path d="M7 17 17 7"></path><path d="M9 7h8v8"></path></svg>
                            </a>
                        </div>

                        <?php if (!$remote_state_available) : ?>
                            <button type="button" id="revalidate-license-btn"
                                    class="listeo-license-action-btn listeo-license-revalidate-btn"
                                    data-nonce="<?php echo wp_create_nonce('listeo_revalidate_license_ajax'); ?>">
                                Revalidate License
                            </button>
                        <?php endif; ?>
                        <button type="button" id="remove-license-btn"
                                class="listeo-license-action-btn listeo-license-deactivate-btn"
                                style="margin-top: 12px;"
                                data-nonce="<?php echo wp_create_nonce('listeo_remove_license_ajax'); ?>">
                            Remove license locally
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-left: 0; vertical-align: -3px;"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v5"></path><path d="M14 11v5"></path></svg>
                        </button>
                        <span id="deactivate-status" style="margin-left: 10px;"></span>
                    </form>
                </div>
            </div>
        </div>

        <?php
        // Log proxy validation info to console if available
        $last_proxy = get_option('listeo_last_proxy_validation');
        if ( get_option('listeo_proxy_validation') === 'yes' && $last_proxy && !empty($last_proxy['success'])) : ?>
            <script>
                console.log('✅ Listeo License: Validated via proxy server');
                console.log('Proxy used: <?php echo esc_js($last_proxy['proxy']); ?>');
                console.log('Validation time: <?php echo esc_js($last_proxy['time']); ?>');
            </script>
        <?php endif; ?>
        
	        <!-- AJAX License Actions Script -->
	        <script>
	        jQuery(document).ready(function($) {
	            function setStatus($status, message, color) {
	                $status.empty().append($("<span>").css("color", color).text(message));
	            }

	            function getResponseMessage(response, fallback) {
	                if (response && response.data && response.data.message) {
	                    return response.data.message;
	                }

	                if (response && typeof response.data === "string") {
	                    return response.data;
	                }

	                return fallback;
	            }

	            $("#revalidate-license-btn").click(function() {
	                var $button = $(this);
	                var $status = $("#deactivate-status");

	                $button.prop("disabled", true).text("Revalidating...");
	                $status.empty();

	                $.ajax({
	                    url: ajaxurl,
	                    type: "POST",
	                    data: {
	                        action: "listeo_revalidate_license_ajax",
	                        nonce: $button.data("nonce")
	                    },
	                    success: function(response) {
	                        if (response.success) {
	                            setStatus($status, "License revalidated successfully.", "#28a745");
	                            setTimeout(function() {
	                                location.reload();
	                            }, 1000);
	                        } else {
	                            var message = getResponseMessage(response, "License revalidation failed");
	                            setStatus($status, "Error: " + message, "#dc3545");
	                            $button.prop("disabled", false).text("Revalidate License");
	                        }
	                    },
	                    error: function() {
	                        setStatus($status, "Connection error occurred", "#dc3545");
	                        $button.prop("disabled", false).text("Revalidate License");
	                    }
	                });
	            });

	            $("#remove-license-btn").click(function() {
	                if (!confirm("Remove the stored license from this site?\\n\\nThis will clear the local license key and email only. It will not contact the license server or free the activation slot.")) {
	                    return;
	                }

	                var $button = $(this);
	                var $status = $("#deactivate-status");

	                $button.prop("disabled", true).text("Removing...");
	                $status.empty();

	                $.ajax({
	                    url: ajaxurl,
	                    type: "POST",
	                    data: {
	                        action: "listeo_remove_license_ajax",
	                        nonce: $button.data("nonce")
	                    },
	                    success: function(response) {
	                        if (response.success) {
	                            setStatus($status, "License removed from this site.", "#28a745");
	                            setTimeout(function() {
	                                location.reload();
	                            }, 1000);
	                        } else {
	                            var message = getResponseMessage(response, "License removal failed");
	                            setStatus($status, "Error: " + message, "#dc3545");
	                            $button.prop("disabled", false).text("Remove license locally");
	                        }
	                    },
	                    error: function() {
	                        setStatus($status, "Connection error occurred", "#dc3545");
	                        $button.prop("disabled", false).text("Remove license locally");
	                    }
	                });
	            });
	        });
	        </script>

        <?php
        echo ob_get_clean();
    
	}
	
	function LicenseForm() {
        
        $tab = '';
        if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
            $tab .= $_GET['tab'];
        }

        // Build modern license activation page
        ob_start(); ?>
        
        <div class="listeo-license-modern">
            <div class="listeo-license-container">
                <div class="listeo-license-header">
                    <h1 class="listeo-license-title">License Activation</h1>
                    <p class="listeo-license-subtitle">Activate your Listeo theme license to unlock all features</p>
                </div>

                <div class="listeo-license-card">
                    <div class="listeo-license-icon-container">
                        <div class="listeo-license-icon"></div>
                    </div>
                    
                    <h2 class="listeo-license-card-title">Activate Your License</h2>
                    <p class="listeo-license-card-subtitle">Enter your purchase details to activate your license</p>

                    <div class="listeo-license-info-box">
                        <div class="listeo-license-info-icon"></div>
                        <div class="listeo-license-info-text">
                            <div class="listeo-license-info-title">Single Site License:</div>
                            This license can only be used on one finished website. To move it to another domain, use the PureThemes license page.
                        </div>
                    </div>

                    <?php
                    // Show error messages if any
	                    if(!empty($this->showMessage) && !empty($this->licenseMessage)){ ?>
	                        <div class="listeo-license-notification">
	                           <?php 
		                            if($this->licenseMessage == 'You license key has been waiting for manual approval, Please contact with license author'){
		                              echo 'Provided license key is already assigned to other domain. Deactivate it for that domain or purchase new license. If you want to activate it on dev/staging environment, please contact us about it via Support Tab on ThemeForest.';
		                            } else {
		                              echo esc_html( $this->licenseMessage );
		                            }
		                          ?>
	                        </div>
	                    <?php }  ?>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="Listeo_el_activate_license"/>
                        <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
                        
                        <div class="listeo-license-form-group">
                            <label class="listeo-license-form-label">
                                <div class="listeo-license-key-icon"></div>
                                Purchase Code
                            </label>
                            <input type="text" class="listeo-license-form-input" name="el_license_key" 
                                   placeholder="xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx" required="required">
                            <p class="listeo-license-help-text">Find your purchase code in your ThemeForest account</p>
                        </div>

                        <div class="listeo-license-form-group">
                            <label class="listeo-license-form-label">
                                <div class="listeo-license-email-icon"></div>
                                ThemeForest Email Address
                            </label>
                            <?php $purchaseEmail = get_option( "Listeo_lic_email", get_bloginfo( 'admin_email' )); ?>
                            <input type="email" class="listeo-license-form-input" name="el_license_email" 
                                   value="<?php echo esc_attr($purchaseEmail); ?>" placeholder="your-email@example.com" required="required">
                            <p class="listeo-license-help-text">The email address associated with your ThemeForest account</p>
                        </div>

                        <?php wp_nonce_field( 'el-license' ); ?>

                        <button type="submit" class="listeo-license-activate-btn">
                            <div class="listeo-license-btn-icon"></div>
                            Activate License
                        </button>
                    </form>

                    <a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" 
                       target="_blank" class="listeo-license-help-link">
                        <div class="listeo-license-help-link-icon"></div>
                        Need help finding your purchase code?
                    </a>
                </div>
            </div>
        </div>

        <?php
        // Log proxy validation attempt info to console
        $last_proxy = get_option('listeo_last_proxy_validation');
        if ($last_proxy && !$last_proxy['success']) : ?>
            <script>
                console.warn('⚠️ Listeo License: Proxy validation attempted but failed');
                console.log('Last attempt: <?php echo esc_js($last_proxy['time']); ?>');
                console.log('Proxy tried: <?php echo esc_js($last_proxy['proxy']); ?>');
            </script>
        <?php endif; ?>

        <?php
        echo ob_get_clean();
		?>


        
		<?php
	}
}

new Listeo();
