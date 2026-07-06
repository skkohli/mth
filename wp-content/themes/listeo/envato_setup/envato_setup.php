<?php

/**
 * Envato Theme Setup Wizard Class
 *
 * Takes new users through some basic steps to setup their ThemeForest theme.
 *
 * @author      dtbaker
 * @author      vburlak
 * @package     envato_wizard
 * @version     1.3.13
 *
 *
 * 1.2.0 - added custom_logo
 * 1.2.1 - ignore post revisioins
 * 1.2.2 - elementor widget data replace on import
 * 1.2.3 - auto export of content.
 * 1.2.4 - fix category menu links
 * 1.2.5 - post meta un json decode
 * 1.2.6 - post meta un json decode
 * 1.2.7 - elementor generate css on import
 * 1.2.8 - backwards compat with old meta format
 * 1.2.9 - theme setup auth
 * 1.3.0 - ob_start fix
 *
 * Based off the WooThemes installer.
 *
 *
 *
 */
if (!defined('ABSPATH')) {
	exit;
}




if (!class_exists('Envato_Theme_Setup_Wizard')) {
	/**
	 * Envato_Theme_Setup_Wizard class
	 */
	class Envato_Theme_Setup_Wizard
	{

		/**
		 * The class version number.
		 *
		 * @since 1.1.1
		 * @access private
		 *
		 * @var string
		 */
		protected $version = '1.3.13';

		/** @var string Current theme name, used as namespace in actions. */
		protected $theme_name = '';

		/** @var string Theme author username, used in check for oauth. */
		protected $envato_username = '';

		/** @var string Full url to server-script.php (available from https://gist.github.com/dtbaker ) */
		protected $oauth_script = '';

		/** @var string Current Step */
		protected $step = '';

		protected $parent_slug;
		/** @var array Steps for the setup wizard */
		protected $steps = array();

		/**
		 * Relative plugin path
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $plugin_path = '';

		/**
		 * Relative plugin url for this plugin folder, used when enquing scripts
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $plugin_url = '';

		/**
		 * The slug name to refer to this menu
		 *
		 * @since 1.1.1
		 *
		 * @var string
		 */
		protected $page_slug;

		/**
		 * TGMPA instance storage
		 *
		 * @var object
		 */
		protected $tgmpa_instance;

		/**
		 * TGMPA Menu slug
		 *
		 * @var string
		 */
		protected $tgmpa_menu_slug = 'tgmpa-install-plugins';

		/**
		 * TGMPA Menu url
		 *
		 * @var string
		 */
		protected $tgmpa_url = 'themes.php?page=tgmpa-install-plugins';

		/**
		 * The slug name for the parent menu
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $page_parent;

		/**
		 * Complete URL to Setup Wizard
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $page_url;

		/**
		 * @since 1.1.8
		 *
		 */
		public $site_styles = array();

		/**
		 * License validation message
		 *
		 * @var string
		 */
		protected $licenseMessage = '';

		/**
		 * License response object
		 *
		 * @var object|null
		 */
		protected $responseObj = null;

		/**
		 * Holds the current instance of the theme manager
		 *
		 * @since 1.1.3
		 * @var Envato_Theme_Setup_Wizard
		 */
		private static $instance = null;

		/**
		 * @since 1.1.3
		 *
		 * @return Envato_Theme_Setup_Wizard
		 */
		public static function get_instance()
		{
			if (!self::$instance) {
				self::$instance = new self;
			}

			return self::$instance;
		}


		/**
		 * A dummy constructor to prevent this class from being loaded more than once.
		 *
		 * @see Envato_Theme_Setup_Wizard::instance()
		 *
		 * @since 1.1.1
		 * @access private
		 */
		public function __construct()
		{
			$this->init_globals();
			$this->init_actions();
		}

		/**
		 * Get the default style. Can be overriden by theme init scripts.
		 *
		 * @see Envato_Theme_Setup_Wizard::instance()
		 *
		 * @since 1.1.7
		 * @access public
		 */
			public function get_default_theme_style()
			{
				return 'style1';
			}

			private function listeo_demo_package_expected_files()
			{
				return array(
					'default.json',
					'options.json',
					'menu.json',
					'widget_options.json',
					'widget_positions.json',
				);
			}

			private function listeo_get_demo_package_endpoint()
			{
				return apply_filters(
					'listeo_demo_package_endpoint',
					'https://purethe.me/wp-json/purethemes-license-proxy/v1/listeo-demo/package'
				);
			}

			private function listeo_get_demo_package_cache_key()
			{
				$license_key = (string) get_option('Listeo_lic_Key', '');
				$license_email = (string) get_option('Listeo_lic_email', '');
				$theme_version = (string) wp_get_theme()->get('Version');

				return 'listeo_demo_package_' . md5($license_key . '|' . $license_email . '|' . $theme_version);
			}

			private function listeo_get_demo_package_cache_ttl()
			{
				return 15 * MINUTE_IN_SECONDS;
			}

			private function listeo_get_demo_package_file_cache_locations()
			{
				$cache_file = $this->listeo_get_demo_package_cache_key() . '.json';
				$locations = array();
				$temp_dir = trailingslashit(get_temp_dir()) . 'listeo-demo-cache';

				$locations[] = array(
					'dir' => $temp_dir,
					'file' => trailingslashit($temp_dir) . $cache_file,
				);

				$upload_dir = wp_upload_dir(null, false);
				if (empty($upload_dir['error']) && !empty($upload_dir['basedir'])) {
					$upload_cache_dir = trailingslashit($upload_dir['basedir']) . 'listeo-demo-cache';
					$locations[] = array(
						'dir' => $upload_cache_dir,
						'file' => trailingslashit($upload_cache_dir) . $cache_file,
					);
				}

				return $locations;
			}

			private function listeo_delete_demo_package_file_cache($file)
			{
				if (is_string($file) && $file !== '' && file_exists($file)) {
					@unlink($file);
				}
			}

			private function listeo_prepare_demo_package_file_cache_dir($dir)
			{
				if (!is_dir($dir) && !wp_mkdir_p($dir)) {
					return false;
				}

				if (!is_writable($dir)) {
					return false;
				}

				$htaccess_file = trailingslashit($dir) . '.htaccess';
				if (!file_exists($htaccess_file)) {
					@file_put_contents($htaccess_file, "Deny from all\nRequire all denied\n");
				}

				$index_file = trailingslashit($dir) . 'index.html';
				if (!file_exists($index_file)) {
					@file_put_contents($index_file, '');
				}

				return true;
			}

			private function listeo_get_file_cached_demo_package()
			{
				foreach ($this->listeo_get_demo_package_file_cache_locations() as $location) {
					if (empty($location['file']) || !is_readable($location['file'])) {
						continue;
					}

					$contents = file_get_contents($location['file']);
					if (!is_string($contents) || $contents === '') {
						$this->listeo_delete_demo_package_file_cache($location['file']);
						continue;
					}

					$data = json_decode($contents, true);
					if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
						$this->listeo_delete_demo_package_file_cache($location['file']);
						continue;
					}

					if (empty($data['expires']) || absint($data['expires']) < time() || !isset($data['package'])) {
						$this->listeo_delete_demo_package_file_cache($location['file']);
						continue;
					}

					$validation = $this->listeo_validate_demo_package($data['package']);
					if (is_wp_error($validation)) {
						$this->listeo_delete_demo_package_file_cache($location['file']);
						continue;
					}

					return $data['package'];
				}

				return false;
			}

			private function listeo_set_file_cached_demo_package($package)
			{
				$contents = wp_json_encode(array(
					'expires' => time() + $this->listeo_get_demo_package_cache_ttl(),
					'package' => $package,
				));

				if ($contents === false) {
					return new WP_Error('listeo_demo_package_file_cache_encode_failed', __('Your site could not prepare temporary demo import data.', 'listeo'), array('status' => 500));
				}

				foreach ($this->listeo_get_demo_package_file_cache_locations() as $location) {
					if (empty($location['dir']) || empty($location['file'])) {
						continue;
					}

					if (!$this->listeo_prepare_demo_package_file_cache_dir($location['dir'])) {
						continue;
					}

					if (@file_put_contents($location['file'], $contents, LOCK_EX) === false) {
						continue;
					}

					$cached_package = $this->listeo_get_file_cached_demo_package();
					if ($cached_package !== false && !is_wp_error($cached_package)) {
						return true;
					}

					$this->listeo_delete_demo_package_file_cache($location['file']);
				}

				return new WP_Error(
					'listeo_demo_package_cache_failed',
					__('Your site could not store temporary demo import data. Please ask your hosting provider to allow WordPress to write temporary files or disable persistent object cache during demo import.', 'listeo'),
					array('status' => 500)
				);
			}

			private function listeo_validate_demo_package($package)
			{
				if (!is_array($package)) {
					return new WP_Error('listeo_demo_package_invalid', __('The demo import package is invalid.', 'listeo'), array('status' => 500));
				}

				foreach ($this->listeo_demo_package_expected_files() as $file) {
					if (!array_key_exists($file, $package) || !is_array($package[$file])) {
						return new WP_Error('listeo_demo_package_missing_file', __('The demo import package is incomplete.', 'listeo'), array('status' => 500));
					}
				}

				return true;
			}

			private function listeo_get_demo_package_error_status($error, $default_status = 403)
			{
				if (!is_wp_error($error)) {
					return $default_status;
				}

				$error_data = $error->get_error_data();
				if (is_array($error_data) && !empty($error_data['status'])) {
					return absint($error_data['status']);
				}

				return $default_status;
			}

			private function listeo_clear_demo_package_cache()
			{
				delete_transient($this->listeo_get_demo_package_cache_key());
				delete_transient($this->listeo_get_demo_package_cache_key() . '_error');

				foreach ($this->listeo_get_demo_package_file_cache_locations() as $location) {
					if (!empty($location['file']) && file_exists($location['file'])) {
						$this->listeo_delete_demo_package_file_cache($location['file']);
					}
				}
			}

			private function listeo_get_demo_package_http_error($status_code, $body)
			{
				$remote_code = '';
				$remote_message = '';
				$decoded_body = json_decode($body, true);

				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_body)) {
					$remote_code = !empty($decoded_body['code']) ? sanitize_key($decoded_body['code']) : '';
					$remote_message = !empty($decoded_body['message']) ? sanitize_text_field($decoded_body['message']) : '';
				}

				if ($status_code === 403) {
					$message = __('The demo import server rejected this license for demo content. Please contact PureThemes support.', 'listeo');
				} elseif ($status_code === 429) {
					$message = __('The demo import server received too many requests for this license. Please wait and try again later.', 'listeo');
				} elseif ($status_code === 404) {
					$message = __('The demo import package is not available on the server. Please contact PureThemes support.', 'listeo');
				} else {
					$message = sprintf(
						/* translators: %d: HTTP status code returned by the demo import server */
						__('The demo import server rejected the package request with HTTP %d. Please contact PureThemes support.', 'listeo'),
						$status_code
					);
				}

				return new WP_Error(
					'listeo_demo_package_http_error',
					$message,
					array(
						'status' => $status_code,
						'remote_code' => $remote_code,
						'remote_message' => $remote_message,
					)
				);
			}

			private function listeo_record_demo_package_error($message)
			{
				$this->errors[] = $message;
				$this->error($message);
			}

			private function listeo_get_cached_demo_package()
			{
				$package = get_transient($this->listeo_get_demo_package_cache_key());
				if ($package === false) {
					return $this->listeo_get_file_cached_demo_package();
				}

				$validation = $this->listeo_validate_demo_package($package);
				if (is_wp_error($validation)) {
					delete_transient($this->listeo_get_demo_package_cache_key());
					return $validation;
				}

				return $package;
			}

			private function listeo_fetch_demo_package()
			{
				if (
					current_user_can('manage_options')
					&& isset($_GET['listeo_clear_demo_cache'])
					&& '1' === sanitize_text_field(wp_unslash($_GET['listeo_clear_demo_cache']))
				) {
					$this->listeo_clear_demo_package_cache();
				}

				$cached_package = $this->listeo_get_cached_demo_package();
				if ($cached_package !== false && !is_wp_error($cached_package)) {
					return $cached_package;
				}

				$license_key = (string) get_option('Listeo_lic_Key', '');
				$license_email = (string) get_option('Listeo_lic_email', '');

				if (empty($license_key)) {
					return new WP_Error('listeo_demo_package_missing_license', __('Please activate your Listeo license before importing demo content.', 'listeo'), array('status' => 403));
				}

				$response = wp_remote_post($this->listeo_get_demo_package_endpoint(), array(
					'timeout' => 60,
					'redirection' => 3,
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body' => wp_json_encode(array(
						'license_key' => $license_key,
						'email' => $license_email,
						'theme_version' => wp_get_theme()->get('Version'),
					)),
				));

				if (is_wp_error($response)) {
					$error = new WP_Error(
						'listeo_demo_package_request_failed',
						__('Could not connect to the demo import server.', 'listeo'),
						array(
							'status' => 503,
							'remote_code' => $response->get_error_code(),
							'remote_message' => $response->get_error_message(),
						)
					);
					return $error;
				}

				$status_code = absint(wp_remote_retrieve_response_code($response));
				$body = wp_remote_retrieve_body($response);

				if ($status_code !== 200) {
					return $this->listeo_get_demo_package_http_error($status_code, $body);
				}

				$package = json_decode($body, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					return new WP_Error('listeo_demo_package_json_error', __('The demo import server returned invalid JSON.', 'listeo'), array('status' => 502));
				}

				$validation = $this->listeo_validate_demo_package($package);
				if (is_wp_error($validation)) {
					return $validation;
				}

				set_transient($this->listeo_get_demo_package_cache_key(), $package, $this->listeo_get_demo_package_cache_ttl());
				$cached_package = $this->listeo_get_cached_demo_package();
				if ($cached_package === false || is_wp_error($cached_package)) {
					$file_cache = $this->listeo_set_file_cached_demo_package($package);
					if (is_wp_error($file_cache)) {
						return $file_cache;
					}
				}

				delete_transient($this->listeo_get_demo_package_cache_key() . '_error');
				return $package;
			}

			private function listeo_require_demo_package()
			{
				return $this->listeo_fetch_demo_package();
			}

			private function listeo_validate_demo_import_prerequisites()
			{
				if (!function_exists('is_plugin_active')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				if (!is_plugin_active('listeo-core/listeo-core.php')) {
					return new WP_Error(
						'listeo_demo_import_missing_listeo_core',
						__('Please install and activate Listeo Core before importing demo content.', 'listeo')
					);
				}

				if (!post_type_exists('listing')) {
					return new WP_Error(
						'listeo_demo_import_missing_listing_post_type',
						__('Listeo Core is active, but the listing post type is not available yet. Please refresh the setup wizard and try again.', 'listeo')
					);
				}

				return true;
			}

			/**
			 * Get the default style. Can be overriden by theme init scripts.
		 *
		 * @see Envato_Theme_Setup_Wizard::instance()
		 *
		 * @since 1.1.9
		 * @access public
		 */
		public function get_header_logo_width()
		{
			return '127px';
		}


		/**
		 * Get the default style. Can be overriden by theme init scripts.
		 *
		 * @see Envato_Theme_Setup_Wizard::instance()
		 *
		 * @since 1.1.9
		 * @access public
		 */
			public function get_logo_image()
			{
				$image_url = get_template_directory_uri() . '/envato_setup/images/listeo.svg';

				return apply_filters('envato_setup_logo_image', $image_url);
			}

		public function get_logo_image2()
		{
			$logo_image_id = get_theme_mod('custom_logo');
			if ($logo_image_id) {
				$logo_image_object = wp_get_attachment_image_src($logo_image_id, 'full');
				$image_url         = $logo_image_object[0];
			} else {
				$image_url = get_theme_mod('logo_header_image', get_template_directory_uri() . '/images/logo.png');
			}

			return apply_filters('envato_setup_logo_image', $image_url);
		}

		/**
		 * Setup the class globals.
		 *
		 * @since 1.1.1
		 * @access public
		 */
		public function init_globals()
		{
			$current_theme         = wp_get_theme();
			$this->theme_name      = strtolower(preg_replace('#[^a-zA-Z]#', '', $current_theme->get('Name')));
			$this->envato_username = apply_filters($this->theme_name . '_theme_setup_wizard_username', 'purethemes');
			$this->oauth_script    = apply_filters($this->theme_name . '_theme_setup_wizard_oauth_script', 'http://purethemes.net/envato/api/server-script.php');
			$this->page_slug       = apply_filters($this->theme_name . '_theme_setup_wizard_page_slug', $this->theme_name . '-setup');
			$this->parent_slug     = apply_filters($this->theme_name . '_theme_setup_wizard_parent_slug', '');

			// create an images/styleX/ folder for each style here.
			/*$this->site_styles = array(
                'style1' => 'Style 1',
                'style2' => 'Style 2',
            );
*/
			//If we have parent slug - set correct url
			if ($this->parent_slug !== '') {
				$this->page_url = 'admin.php?page=' . $this->page_slug;
			} else {
				$this->page_url = 'themes.php?page=' . $this->page_slug;
			}
			$this->page_url = apply_filters($this->theme_name . '_theme_setup_wizard_page_url', $this->page_url);

			//set relative plugin path url
			$this->plugin_path = trailingslashit($this->cleanFilePath(dirname(__FILE__)));
			$relative_url      = str_replace($this->cleanFilePath(get_template_directory()), '', $this->plugin_path);
			$this->plugin_url  = trailingslashit(get_template_directory_uri() . '/envato_setup/');
		}

		/**
		 * Setup the hooks, actions and filters.
		 *
		 * @uses add_action() To add actions.
		 * @uses add_filter() To add filters.
		 *
		 * @since 1.1.1
		 * @access public
		 */
		public function init_actions()
		{
			if (apply_filters($this->theme_name . '_enable_setup_wizard', true) && current_user_can('manage_options')) {
				add_action('after_switch_theme', array($this, 'switch_theme'));

				if (class_exists('TGM_Plugin_Activation') && isset($GLOBALS['tgmpa'])) {
					add_action('init', array($this, 'get_tgmpa_instanse'), 30);
					add_action('init', array($this, 'set_tgmpa_url'), 40);
				}

				add_action('admin_menu', array($this, 'admin_menus'));
				add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
				add_action('admin_init', array($this, 'admin_redirects'), 30);
				add_action('admin_init', array($this, 'init_wizard_steps'), 30);
				add_action('admin_init', array($this, 'setup_wizard'), 30);
				add_filter('tgmpa_load', array($this, 'tgmpa_load'), 10, 1);
					add_action('wp_ajax_envato_setup_plugins', array($this, 'ajax_plugins'));
					add_action('wp_ajax_envato_setup_plugin_log', array($this, 'ajax_plugin_log'));
					add_action('wp_ajax_envato_setup_content', array($this, 'ajax_content'));
					add_action('wp_ajax_listeo_submit_feedback', array($this, 'handle_feedback_submission'));
				}
			/*if ( function_exists( 'envato_market' ) ) {
				add_action( 'admin_init', array( $this, 'envato_market_admin_init' ), 20 );
				add_filter( 'http_request_args', array( $this, 'envato_market_http_request_args' ), 10, 2 );
				add_action( 'wp_ajax_dtbwp_update_notice_handler', array($this,'ajax_notice_handler') );
				add_action( 'admin_notices', array($this,'admin_theme_auth_notice') );
			}*/
			add_action('upgrader_post_install', array($this, 'upgrader_post_install'), 10, 2);
			add_filter('woocommerce_prevent_automatic_wizard_redirect', array($this, 'wc_subscriber_auto_redirect'), 20, 1);
		}

		/**
		 * After a theme update we clear the setup_complete option. This prompts the user to visit the update page again.
		 *
		 * @since 1.1.8
		 * @access public
		 */
		public function upgrader_post_install($return, $theme)
		{
			if (is_wp_error($return)) {
				return $return;
			}
			if ($theme != get_stylesheet()) {
				return $return;
			}
			update_option('envato_setup_complete', false);

			return $return;
		}

		/**
		 * We determine if the user already has theme content installed. This can happen if swapping from a previous theme or updated the current theme. We change the UI a bit when updating / swapping to a new theme.
		 *
		 * @since 1.1.8
		 * @access public
		 */
		public function is_possible_upgrade()
		{
			return false;
		}

		public function enqueue_scripts()
		{
		}

		public function tgmpa_load($status)
		{
			return is_admin() || current_user_can('install_themes');
		}

		public function switch_theme()
		{
			set_transient('_' . $this->theme_name . '_activation_redirect', 1);
		}

		public function admin_redirects()
		{
			if (!get_transient('_' . $this->theme_name . '_activation_redirect') || get_option('envato_setup_complete', false)) {
				return;
			}
			delete_transient('_' . $this->theme_name . '_activation_redirect');
			wp_safe_redirect(self_admin_url($this->page_url));
			exit;
		}

		/**
		 * Get configured TGMPA instance
		 *
		 * @access public
		 * @since 1.1.2
		 */
		public function get_tgmpa_instanse()
		{
			$this->tgmpa_instance = call_user_func(array(get_class($GLOBALS['tgmpa']), 'get_instance'));
		}

		/**
		 * Update $tgmpa_menu_slug and $tgmpa_parent_slug from TGMPA instance
		 *
		 * @access public
		 * @since 1.1.2
		 */
		public function set_tgmpa_url()
		{

			$this->tgmpa_menu_slug = (property_exists($this->tgmpa_instance, 'menu')) ? $this->tgmpa_instance->menu : $this->tgmpa_menu_slug;
			$this->tgmpa_menu_slug = apply_filters($this->theme_name . '_theme_setup_wizard_tgmpa_menu_slug', $this->tgmpa_menu_slug);

			$tgmpa_parent_slug = (property_exists($this->tgmpa_instance, 'parent_slug') && $this->tgmpa_instance->parent_slug !== 'themes.php') ? 'admin.php' : 'themes.php';

			$this->tgmpa_url = apply_filters($this->theme_name . '_theme_setup_wizard_tgmpa_url', $tgmpa_parent_slug . '?page=' . $this->tgmpa_menu_slug);
		}

		/**
		 * Add admin menus/screens.
		 */
		public function admin_menus()
		{

			if ($this->is_submenu_page()) {
				//prevent Theme Check warning about "themes should use add_theme_page for adding admin pages"
				$add_subpage_function = 'add_submenu' . '_page';
				$add_subpage_function($this->parent_slug, esc_html__('Setup Wizard', 'listeo'), esc_html__('Setup Wizard', 'listeo'), 'manage_options', $this->page_slug, array(
					$this,
					'setup_wizard',
				));
			} else {
				add_theme_page(esc_html__('Setup Wizard', 'listeo'), esc_html__('Setup Wizard', 'listeo'), 'manage_options', $this->page_slug, array(
					$this,
					'setup_wizard',
				));
			}
		}


		/**
		 * Setup steps.
		 *
		 * @since 1.1.1
		 * @access public
		 * @return array
		 */
		public function init_wizard_steps()
		{

			$this->steps = array(
				'introduction' => array(
					'name'    => esc_html__('Introduction', 'listeo'),
					'view'    => array($this, 'envato_setup_introduction'),
					'handler' => array($this, 'envato_setup_introduction_save'),
				),
			);
			$this->steps['license_activation'] = array(
				'name'    => esc_html__('License Activation'),
				'view'    => array($this, 'envato_setup_license_activation'),
				'handler' => array($this, 'envato_setup_license_activation_save'),
			);
			if (class_exists('TGM_Plugin_Activation') && isset($GLOBALS['tgmpa'])) {
				$this->steps['default_plugins'] = array(
					'name'    => esc_html__('Plugins', 'listeo'),
					'view'    => array($this, 'envato_setup_default_plugins'),
					'handler' => '',
				);
			}
			/*$this->steps['updates']         = array(
				'name'    => esc_html__( 'Updates','listeo' ),
				'view'    => array( $this, 'envato_setup_updates' ),
				'handler' => array( $this, 'envato_setup_updates_save' ),
			);*/

			$this->steps['default_content'] = array(
				'name'    => esc_html__('Content', 'listeo'),
				'view'    => array($this, 'envato_setup_default_content'),
				'handler' => '',
			);
			// $this->steps['design']          = array(
			// 	'name'    => esc_html__( 'Logo','listeo' ),
			// 	'view'    => array( $this, 'envato_setup_logo_design' ),
			// 	'handler' => array( $this, 'envato_setup_logo_design_save' ),
			// );
			/* $this->steps['customize']       = array(
				'name'    => esc_html__('Customize', 'listeo'),
				'view'    => array($this, 'envato_setup_customize'),
				'handler' => '',
			); */
			$this->steps['help_support']    = array(
				'name'    => esc_html__('Support', 'listeo'),
				'view'    => array($this, 'envato_setup_help_support'),
				'handler' => '',
			);
			$this->steps['vendor']    = array(
				'name'    => esc_html__('Mutli Vendor', 'listeo'),
				'view'    => array($this, 'envato_setup_multi_vendor'),
				'handler' => '',
			);
			$this->steps['next_steps']      = array(
				'name'    => esc_html__('Ready!', 'listeo'),
				'view'    => array($this, 'envato_setup_ready'),
				'handler' => '',
			);

			$this->steps = apply_filters($this->theme_name . '_theme_setup_wizard_steps', $this->steps);
		}

		/**
		 * Show the setup wizard
		 */
		public function setup_wizard()
		{
			if (empty($_GET['page']) || $this->page_slug !== $_GET['page']) {
				return;
			}
			if (ob_get_length()) ob_end_clean();

			$this->step = isset($_GET['step']) ? sanitize_key($_GET['step']) : current(array_keys($this->steps));

			wp_register_script('jquery-blockui', $this->plugin_url . 'js/jquery.blockUI.js', array('jquery'), '2.70', true);
			wp_register_script('envato-setup', $this->plugin_url . 'js/envato-setup.js', array(
				'jquery',
				'jquery-blockui',
			), $this->version);
			wp_localize_script('envato-setup', 'envato_setup_params', array(
				'tgm_plugin_nonce' => array(
					'update'  => wp_create_nonce('tgmpa-update'),
					'install' => wp_create_nonce('tgmpa-install'),
				),
				'tgm_bulk_url'     => self_admin_url($this->tgmpa_url),
				'ajaxurl'          => admin_url('admin-ajax.php'),
				'wpnonce'          => wp_create_nonce('envato_setup_nonce'),
				'verify_text'      => esc_html__('...verifying', 'listeo'),
			));

			//wp_enqueue_style( 'envato_wizard_admin_styles', $this->plugin_url . '/css/admin.css', array(), $this->version );
			wp_enqueue_style('envato-setup', $this->plugin_url . 'css/envato-setup.css', array(
				'wp-admin',
				'dashicons',
				'install',
			), $this->version);

			//enqueue style for admin notices
			wp_enqueue_style('wp-admin');

			wp_enqueue_media();
			wp_enqueue_script('media');

			ob_start();
			$this->envato_setup_wizard_header();
			$this->setup_wizard_steps();
			$show_content = true;
			echo '<div class="envato-setup-content">';
			if (!empty($_REQUEST['save_step']) && isset($this->steps[$this->step]['handler'])) {
				$show_content = call_user_func($this->steps[$this->step]['handler']);
			}
			if ($show_content) {
				$this->setup_wizard_content();
			}
			echo '</div>';
			$this->setup_wizard_footer();
			exit;
		}

		public function get_step_link($step)
		{
			return add_query_arg('step', $step, self_admin_url('admin.php?page=' . $this->page_slug));
		}

		public function get_next_step_link()
		{
			$keys = array_keys($this->steps);

			return add_query_arg('step', $keys[array_search($this->step, array_keys($this->steps)) + 1], remove_query_arg('translation_updated'));
		}

		/**
		 * Setup Wizard Header
		 */
		public function envato_setup_wizard_header()
		{
?>
			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

			<head>
				<meta name="viewport" content="width=device-width" />
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<?php
				// avoid theme check issues.
				echo '<t';
				echo 'itle>' . esc_html__('Theme &rsaquo; Setup Wizard', 'listeo') . '</ti' . 'tle>'; ?>
				<?php wp_print_scripts('envato-setup'); ?>
				<?php 
				// Remove deprecated print_emoji_styles to avoid warning in WP 6.4+
				remove_action('wp_print_styles', 'print_emoji_styles');
				// Use the new method to enqueue emoji styles
				if (function_exists('wp_enqueue_emoji_styles')) {
					wp_enqueue_emoji_styles();
				}
				do_action('admin_print_styles'); 
				?>
				<?php do_action('admin_print_scripts'); ?>
				<?php //do_action( 'admin_head' ); 
				?>
			</head>

			<body class="envato-setup wp-core-ui">
				<h1 id="wc-logo">
					<a href="https://purethemes.net/listeo/" target="_blank"><?php
																								$image_url = $this->get_logo_image();
																								if ($image_url) {
																									$image = '<img class="site-logo" src="%s" alt="%s" style="width:%s; height:auto" />';
																									printf(
																										$image,
																										$image_url,
																										get_bloginfo('name'),
																										$this->get_header_logo_width()
																									);
																								} else { ?>
							<img src="<?php echo esc_url($this->plugin_url . 'images/logo.png'); ?>" alt="Envato install wizard" /><?php
																																} ?></a>
				</h1>
			<?php
		}

		/**
		 * Setup Wizard Footer
		 */
		public function setup_wizard_footer()
		{
			?>
				<?php if ('next_steps' === $this->step) : ?>
					<a class="wc-return-to-dashboard" href="<?php echo esc_url(self_admin_url()); ?>"><?php esc_html_e('Return to the WordPress Dashboard', 'listeo'); ?></a>
				<?php endif; ?>
			</body>
			<?php
			@do_action('admin_footer'); // this was spitting out some errors in some admin templates. quick @ fix until I have time to find out what's causing errors.
			do_action('admin_print_footer_scripts');
			?>

			</html>
		<?php
		}

		/**
		 * Output the steps
		 */
		public function setup_wizard_steps()
		{
			$ouput_steps = $this->steps;
			array_shift($ouput_steps);
		?>
			<ol class="envato-setup-steps">
				<?php foreach ($ouput_steps as $step_key => $step) : ?>
					<li class="<?php
								$show_link = false;
								if ($step_key === $this->step) {
									echo 'active';
								} elseif (array_search($this->step, array_keys($this->steps)) > array_search($step_key, array_keys($this->steps))) {
									echo 'done';
									$show_link = true;
								}
								?>"><?php
									if ($show_link) {
									?>
							<a href="<?php echo esc_url($this->get_step_link($step_key)); ?>"><?php echo esc_html($step['name']); ?></a>
						<?php
									} else {
										echo esc_html($step['name']);
									}
						?>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php
		}

		/**
		 * Output the content for the current step
		 */
		public function setup_wizard_content()
		{
			isset($this->steps[$this->step]) ? call_user_func($this->steps[$this->step]['view']) : false;
		}

		/**
		 * Introduction step
		 */
		public function envato_setup_introduction()
		{

			if (strnatcmp(phpversion(), '5.6.0') >= 0) {
			} else { ?>
				<h1>Houston, we have a problem! 🚀 ✋ 👇</h1>
				<p>It looks like <strong>your server runs on PHP version older than 5.6</strong> which is not compatible with our theme and plugins. If you are not able to update it on your own, please contact your hosting provider and ask them for an update. We recommend using PHP7 for best results.</p>
				<p>Your current PHP Version is <?php echo phpversion(); ?></p>
				<p>If you wish you can run the Setup Wizard but that will not work correctly and you won't be able to use most of the features, including the core plugin, so please come back here when your PHP is updated.</p>
			<?php }
			if (false && isset($_REQUEST['debug'])) {
				echo '<pre>';
				// debug inserting a particular post so we can see what's going on
				$post_type = 'nav_menu_item';
				$post_id   = 239; // debug this particular import post id.
				$all_data  = $this->_get_json('default.json');
				if (!$post_type || !isset($all_data[$post_type])) {
					echo "Post type $post_type not found.";
				} else {
					echo "Looking for post id $post_id \n";
					foreach ($all_data[$post_type] as $post_data) {

						if ($post_data['post_id'] == $post_id) {
							//print_r( $post_data );
							$this->_process_post_data($post_type, $post_data, 0, true);
						}
					}
				}
				$this->_handle_delayed_posts();
				print_r($this->logs);

				echo '</pre>';
			} else if (isset($_REQUEST['export'])) {

				@include('envato-setup-export.php');
			} else if ($this->is_possible_upgrade()) {
			?>
				<h1><?php printf(esc_html__('Welcome to the setup wizard for %s.', 'listeo'), wp_get_theme()); ?></h1>
				<p><?php esc_html_e('It looks like you may have recently upgraded to this theme. Great! This setup wizard will help ensure all the default settings are correct. It will also show some information about your new website and support options.', 'listeo'); ?></p>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-large button-next"><?php esc_html_e('Let\'s Go!', 'listeo'); ?></a>
					<a href="<?php echo esc_url(wp_get_referer() && !strpos(wp_get_referer(), 'update.php') ? wp_get_referer() : self_admin_url('')); ?>" class="button button-large"><?php esc_html_e('Not right now', 'listeo'); ?></a>
				</p>
			<?php
			} else if (get_option('envato_setup_complete', false)) {
			?>
				<h1><?php printf(esc_html__('Welcome to the setup wizard for %s.', 'listeo'), wp_get_theme()); ?></h1>
				<p><?php esc_html_e('It looks like you have already run the setup wizard. Below are some options: ', 'listeo'); ?></p>
				<ul>
					<li>
						<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-next button-large"><?php esc_html_e('Run Setup Wizard Again', 'listeo'); ?></a>
					</li>

				</ul>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url(wp_get_referer() && !strpos(wp_get_referer(), 'update.php') ? wp_get_referer() : self_admin_url('')); ?>" class="button button-large"><?php esc_html_e('Cancel', 'listeo'); ?></a>
				</p>
			<?php
			} else {

			?>
				<h1><?php printf(esc_html__('Welcome to the setup wizard for %s.', 'listeo'), wp_get_theme()); ?></h1>
				<p><?php printf(esc_html__('Thank you for choosing the %s theme from Purethemes. This quick setup wizard will help you configure your new website. This wizard will install the required WordPress plugins, default content, logo and tell you a little about Help &amp; Support options. It should only take 5 minutes.', 'listeo'), wp_get_theme()); ?></p>
				<p><?php esc_html_e('No time right now? If you don\'t want to go through the wizard, you can skip and return to the WordPress dashboard. Come back anytime if you change your mind!', 'listeo'); ?></p>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-large button-next"><?php esc_html_e('Let\'s Go!', 'listeo'); ?></a>
					<a href="<?php echo esc_url(wp_get_referer() && !strpos(wp_get_referer(), 'update.php') ? wp_get_referer() : self_admin_url('')); ?>" class="button button-large"><?php esc_html_e('Not right now', 'listeo'); ?></a>
				</p>
			<?php
			}
		}

		public function filter_options($options)
		{
			return $options;
		}

		/**
		 *
		 * Handles save button from welcome page. This is to perform tasks when the setup wizard has already been run. E.g. reset defaults
		 *
		 * @since 1.2.5
		 */
		public function envato_setup_introduction_save()
		{

			check_admin_referer('envato-setup');

			if (!empty($_POST['reset-font-defaults']) && $_POST['reset-font-defaults'] == 'yes') {

				// clear font options
				update_option('tt_font_theme_options', array());

				// do other reset options here.

				// reset site color
				remove_theme_mod('dtbwp_site_color');

				if (class_exists('dtbwp_customize_save_hook')) {
					$site_color_defaults = new dtbwp_customize_save_hook();
					$site_color_defaults->save_color_options();
				}

				$file_name = get_template_directory() . '/style.custom.css';
				if (file_exists($file_name)) {
					require_once(ABSPATH . 'wp-admin/includes/file.php');
					WP_Filesystem();
					global $wp_filesystem;
					$wp_filesystem->put_contents($file_name, '');
				}
			?>
				<p>
					<strong><?php esc_html_e('Options have been reset. Please go to Appearance > Customize in the WordPress backend.', 'listeo'); ?></strong>
				</p>
			<?php
				return true;
			}

			return false;
		}


		private function _get_plugins()
		{
			$instance = call_user_func(array(get_class($GLOBALS['tgmpa']), 'get_instance'));
			$plugins  = array(
				'all'      => array(), // Meaning: all plugins which still have open actions.
				'install'  => array(),
				'update'   => array(),
				'activate' => array(),
			);

			foreach ($instance->plugins as $slug => $plugin) {
				if ($instance->is_plugin_active($slug) && false === $instance->does_plugin_have_update($slug)) {
					// No need to display plugins if they are installed, up-to-date and active.
					continue;
				} else {
					$plugins['all'][$slug] = $plugin;

					if (!$instance->is_plugin_installed($slug)) {
						$plugins['install'][$slug] = $plugin;
					} else {
						if (false !== $instance->does_plugin_have_update($slug)) {
							$plugins['update'][$slug] = $plugin;
						}

						if ($instance->can_plugin_activate($slug)) {
							$plugins['activate'][$slug] = $plugin;
						}
					}
				}
			}

			return $plugins;
		}

		/**
		 * Mask sensitive data in response objects for logging
		 *
		 * @param object $responseObj The response object to mask
		 * @return object Masked response object
		 */
			private function mask_sensitive_response($responseObj) {
				if (!$responseObj) {
					return $responseObj;
				}
			
			// Create a copy to avoid modifying the original
			$masked = clone $responseObj;
			
			// Mask license_key if present
			if (isset($masked->license_key) && !empty($masked->license_key)) {
				$key = $masked->license_key;
				if (strlen($key) > 8) {
					$masked->license_key = substr($key, 0, 4) . '****-****-****-****' . substr($key, -4);
				} else {
					$masked->license_key = '****';
				}
			}
				
				return $masked;
			}

			public function envato_setup_license_activation()
			{
			// Don't process POST data here - let the handler do it
			// This was causing the handler to not receive POST data

			// Staging/dev sites still need the regular license form.
			$is_staging = function_exists('listeo_is_staging_environment') && listeo_is_staging_environment();

			$licenseKey   = get_option("Listeo_lic_Key", "");
			$liceEmail    = get_option("Listeo_lic_email", "");

			$templateDir  = get_template_directory(); //or dirname(__FILE__);

			if (b472b0Base::CheckWPPlugin($licenseKey, $liceEmail, $this->licenseMessage, $this->responseObj, $templateDir . "/style.css")) {			?>
				<div class="listeo-setup-activated">
					<svg width="133px" height="133px" viewBox="0 0 133 133" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
						<g id="check-group" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
							<circle id="filled-circle" fill="#78B348" cx="66.5" cy="66.5" r="54.5"></circle>
							<circle id="white-circle" fill="#FFFFFF" cx="66.5" cy="66.5" r="55.5"></circle>
							<circle id="outline" stroke="#78B348" stroke-width="4" cx="66.5" cy="66.5" r="54.5"></circle>
							<polyline id="check" stroke="#FFFFFF" stroke-width="4" points="41 70 56 85 92 49"></polyline>
						</g>
					</svg>

					<h1><?php printf(esc_html__('Thank you for activating your %s license.'), wp_get_theme()); ?></h1>
					<?php if ( get_option('listeo_offline_activation') === 'yes' ) : ?>
						<!-- <p style="color: #FF8C00; font-weight: 500; margin-top: 10px;">
							License activated offline due to server connectivity issues. <br>Your theme is fully functional.
						</p> -->
					<?php endif; ?>
			</div>

			<?php
				$feedback_debug_mode = false;

			// Check if user already submitted feedback
			$feedback_submitted = get_option('listeo_setup_feedback_submitted_' . md5(site_url()), false);

			if (!$feedback_submitted || $feedback_debug_mode) : ?>
				<div class="listeo-feedback-poll" style="margin-top: 40px; max-width: 600px; margin-left: auto; margin-right: auto;">
					<h2 style="text-align: center; margin-bottom: 10px;"><?php esc_html_e('One more thing...', 'listeo'); ?></h2>
					<p style="text-align: center; color: #666; margin-bottom: 30px;">
						<?php esc_html_e('How did you discover Listeo? This helps us understand our customers better.', 'listeo'); ?>
					</p>

					<form method="post" id="listeo-feedback-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
						<div class="feedback-poll-options" style="margin: 30px 0;">
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;">
								<input type="radio" name="how_found_listeo" value="AI (ChatGPT, Gemini, Perplexity or other)" required style="margin-right: 10px;">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="8" width="16" height="12" rx="3"></rect><path d="M12 4v4"></path><path d="M8 4h8"></path><circle cx="9" cy="14" r="1"></circle><circle cx="15" cy="14" r="1"></circle><path d="M9 18h6"></path></svg>
									<span><?php esc_html_e('AI (ChatGPT, Gemini, Perplexity or other)', 'listeo'); ?></span>
								</span>
							</label>
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;">
								<input type="radio" name="how_found_listeo" value="Google Search" required style="margin-right: 10px;">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
									<span><?php esc_html_e('Google Search', 'listeo'); ?></span>
								</span>
							</label>
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;">
								<input type="radio" name="how_found_listeo" value="ThemeForest Browse" required style="margin-right: 10px;">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 8h12l-1 12H7L6 8z"></path><path d="M9 8a3 3 0 0 1 6 0"></path></svg>
									<span><?php esc_html_e('Browsing ThemeForest', 'listeo'); ?></span>
								</span>
							</label>
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;">
								<input type="radio" name="how_found_listeo" value="Social Media" required style="margin-right: 10px;">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.6 10.7 6.8-4.4"></path><path d="m8.6 13.3 6.8 4.4"></path></svg>
									<span><?php esc_html_e('Social Media', 'listeo'); ?></span>
								</span>
							</label>
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;">
								<input type="radio" name="how_found_listeo" value="YouTube" required style="margin-right: 10px;">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="6" width="18" height="12" rx="3"></rect><path d="m10 9.5 6 2.5-6 2.5z" fill="currentColor" stroke="none"></path></svg>
									<span><?php esc_html_e('YouTube', 'listeo'); ?></span>
								</span>
							</label>
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;">
								<input type="radio" name="how_found_listeo" value="Recommendation" required style="margin-right: 10px;">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
									<span><?php esc_html_e('Friend/Colleague Recommendation', 'listeo'); ?></span>
								</span>
							</label>
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;">
								<input type="radio" name="how_found_listeo" value="Blog/Review" required style="margin-right: 10px;">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h6"></path></svg>
									<span><?php esc_html_e('Blog or Review Site', 'listeo'); ?></span>
								</span>
							</label>
							<label style="display: block; margin-bottom: 15px; font-size: 16px; cursor: pointer; padding: 12px; border: 2px solid #ddd; border-radius: 6px; transition: all 0.2s;" id="other_label">
								<input type="radio" name="how_found_listeo" value="Other" required style="margin-right: 10px;" id="how_found_other">
								<span class="listeo-feedback-option-content">
									<svg class="listeo-feedback-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h.01"></path><path d="M12 12h.01"></path><path d="M16 12h.01"></path></svg>
									<span><?php esc_html_e('Other', 'listeo'); ?></span>
								</span>
							</label>
							<div id="other_text_field_wrapper" style="display: none; margin-top: 10px; margin-bottom: 15px; padding-left: 0; padding-right: 0; opacity: 0; transform: translateY(-10px); transition: opacity 0.3s ease, transform 0.3s ease;">
								<input type="text" name="how_found_other_text" id="how_found_other_text" placeholder="<?php esc_attr_e('Please specify...', 'listeo'); ?>" minlength="5" maxlength="200" style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; display: block;">
							</div>
						</div>

						<!-- Newsletter opt-in checkbox -->
						<div style="margin: -15px 0 20px -10px;">
							<label class="listeo-newsletter-optin" style="display: block; font-size: 14px; cursor: pointer; padding: 10px; transition: all 0.2s;">
								<input type="checkbox" name="newsletter_optin" value="yes" class="listeo-newsletter-checkbox">
								<span><?php esc_html_e('I want to get notified about updates and new products (we don\'t spam)', 'listeo'); ?></span>
							</label>
						</div>

						<input type="hidden" name="feedback_submitted" value="1">
						<input type="hidden" name="action" value="listeo_submit_feedback">
						<?php wp_nonce_field('listeo_feedback_poll_nonce', 'listeo_feedback_poll_nonce'); ?>

<p class="envato-setup-actions step" style="overflow: visible;text-align: center;">


<script>
document.addEventListener('DOMContentLoaded', function() {
	var btn = document.getElementById('submit-feedback-btn');
	var wrapper = document.getElementById('tooltip-wrapper');
	var tooltipText = document.getElementById('tooltip-text');
	var tooltipArrow = document.getElementById('tooltip-arrow');
	
	if (btn && wrapper && tooltipText && tooltipArrow) {
		wrapper.addEventListener('mouseenter', function() {
			if (btn.disabled) {
				tooltipText.classList.add('show');
				tooltipArrow.classList.add('show');
			}
		});
		
		wrapper.addEventListener('mouseleave', function() {
			tooltipText.classList.remove('show');
			tooltipArrow.classList.remove('show');
		});
	}
});
</script>

<span id="tooltip-wrapper" class="env-set">
	<span id="tooltip-text" class="tooltip-text"><?php esc_html_e('Select one of the options above', 'listeo'); ?></span>
	<span id="tooltip-arrow" class="tooltip-arrow"></span>
	<button type="submit" class="button-primary button button-large button" style="margin-right: 10px;" id="submit-feedback-btn" name="submit_feedback" disabled>
		<?php esc_html_e('Continue', 'listeo'); ?>
	</button>
</span>
</p>
					</form>

				<!-- Original "Let's Go!" button that always works -->
				<p class="envato-setup-actions step" style="display: none !important; text-align: center; margin-top: 20px;">
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-large button-next"><?php esc_html_e('Skip & Continue', 'listeo'); ?></a>
				</p>

<script>
(function() {
	// DEBUG MODE: Set to true to allow multiple submissions for testing
	const DEBUG_MODE = false; // CHANGE TO false IN PRODUCTION!

	const RATE_LIMIT_KEY = 'listeo_feedback_submitted';
	const RATE_LIMIT_DURATION = 24 * 60 * 60 * 1000;
	const form = document.getElementById('listeo-feedback-form');
	const submitBtn = document.getElementById('submit-feedback-btn');

	const formActionUrl = form?.getAttribute('action');

	function checkRateLimit() {
		if (DEBUG_MODE) return true; // Skip rate limiting in debug mode
		const lastSubmit = localStorage.getItem(RATE_LIMIT_KEY);
		if (lastSubmit) {
			const timeDiff = Date.now() - parseInt(lastSubmit);
			if (timeDiff < RATE_LIMIT_DURATION) return false;
		}
		return true;
	}

	if (!checkRateLimit()) {
		submitBtn.disabled = true;
		submitBtn.textContent = 'Already submitted';
		setTimeout(function() {
			const skipBtn = document.querySelector('a.button-next[href*="step="]');
			if (skipBtn) {
				window.location.href = skipBtn.href;
			}
		}, 2000);
	}

	// Handle "Other" text field visibility with slide-in effect
	const otherRadio = document.getElementById('how_found_other');
	const otherTextField = document.getElementById('how_found_other_text');
	const otherTextWrapper = document.getElementById('other_text_field_wrapper');
	const allRadios = document.querySelectorAll('input[name="how_found_listeo"]');

	allRadios.forEach(function(radio) {
		radio.addEventListener('change', function() {
			if (otherRadio.checked) {
				// Show and slide in
				otherTextWrapper.style.display = 'block';
				setTimeout(function() {
					otherTextWrapper.style.opacity = '1';
					otherTextWrapper.style.transform = 'translateY(0)';
				}, 10);
				setTimeout(function() {
					otherTextField.focus();
				}, 320);
			} else {
				// Slide out and hide
				otherTextWrapper.style.opacity = '0';
				otherTextWrapper.style.transform = 'translateY(-10px)';
				setTimeout(function() {
					otherTextWrapper.style.display = 'none';
					otherTextField.value = '';
				}, 300);
			}
		});
	});

	form.addEventListener('submit', function(e) {
		e.preventDefault(); // Always prevent default, we'll use AJAX

		// Check if an option is selected
		const selectedOption = form.querySelector('input[name="how_found_listeo"]:checked');
		if (!selectedOption) {
			alert('Please select an option before continuing.');
			// Highlight all labels briefly to draw attention
			document.querySelectorAll('.feedback-poll-options label').forEach(function(label) {
				label.style.borderColor = '#dc3232';
				setTimeout(function() {
					label.style.borderColor = '#ddd';
				}, 1500);
			});
			return false;
		}

		// If "Other" is selected, validate text field
		if (otherRadio.checked) {
			const otherText = otherTextField.value.trim();
			if (!otherText) {
				alert('Please specify what "Other" means.');
				otherTextField.focus();
				otherTextField.style.borderColor = '#dc3232';
				setTimeout(function() {
					otherTextField.style.borderColor = '#ddd';
				}, 1500);
				return false;
			}
			if (otherText.length < 5) {
				alert('Please provide at least 5 characters.');
				otherTextField.focus();
				otherTextField.style.borderColor = '#dc3232';
				setTimeout(function() {
					otherTextField.style.borderColor = '#ddd';
				}, 1500);
				return false;
			}
		}

		// Check rate limit
		if (!checkRateLimit()) {
			alert('You have already submitted feedback recently.');
			return false;
		}

		// Update button state
		submitBtn.disabled = true;
		submitBtn.textContent = 'Submitting...';

		// Submit via AJAX
		const formData = new FormData(form);

		// If "Other" is selected, combine with text field
		if (otherRadio.checked) {
			const otherText = otherTextField.value.trim();
			formData.set('how_found_listeo', 'Other (' + otherText + ')');
		}

		const submitUrl = form.getAttribute('action');

		fetch(submitUrl, {
			method: 'POST',
			body: formData
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (data.success) {
				submitBtn.textContent = '✓ Submitted!';
				if (!DEBUG_MODE) {
					localStorage.setItem(RATE_LIMIT_KEY, Date.now().toString());
				}
				// Auto-click the "Skip & Continue" button after 500ms
				setTimeout(function() {
					const skipBtn = document.querySelector('a.button-next[href*="step="]');
					if (skipBtn) {
						window.location.href = skipBtn.href;
					}
				}, 500);
			} else {
				submitBtn.textContent = 'Error - Try Skip';
				submitBtn.disabled = false;
			}
		})
		.catch(function(error) {
			submitBtn.textContent = 'Error - Try Skip';
			submitBtn.disabled = false;
		});
	});
	
	const radioInputs = document.querySelectorAll('.feedback-poll-options input[type="radio"]');
	radioInputs.forEach(function(input) {
		input.addEventListener('change', function() {
			document.querySelectorAll('.feedback-poll-options label').forEach(function(label) {
				label.style.borderColor = '#ddd';
				label.style.backgroundColor = 'transparent';
			});
			if (this.checked) {
				this.parentElement.style.borderColor = '#78B348';
				this.parentElement.style.backgroundColor = '#f0f8ed';
				// Enable submit button when option is selected
				submitBtn.disabled = false;
			}
		});
	});
})();
</script>
				</div>
			<?php else : ?>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-large button-next"><?php esc_html_e('Let\'s Go!'); ?></a>
				</p>
			<?php endif; ?>
		<?php } else {
				if (!empty($licenseKey) && !empty($this->licenseMessage)) {

					$this->showMessage = true;
				}
			?>
				<form method="post">
					<h1><?php printf(esc_html__('🚀 Welcome to the setup wizard for %s.'), wp_get_theme()); ?></h1>
					<p>

						Setup Wizard requires activating your license. Single license allows you to install theme on one domain and one dev/staging site.
					</p>

					<?php if ($is_staging) { ?>
						<div class="license-notification notice listeo-staging-license-notice">
							<p><?php esc_html_e('Staging environment detected. Production slot will not be taken.', 'listeo'); ?></p>
						</div>
					<?php } ?>

					<h3><a href="https://docs.purethemes.net/listeo/knowledge-base/how-to-find-my-license-key/" target="_blank"><?php esc_html_e('How to find your license key', 'listeo'); ?> &rarr;</a></h3>

					<?php
					// Check for error messages from the setup wizard handler
					$setup_error = get_transient('listeo_setup_license_error');
					if ($setup_error) {
							delete_transient('listeo_setup_license_error'); ?>
							<div class="license-notification error">
								<p><?php echo esc_html($setup_error); ?></p>
							</div>
						<?php } ?>
					<table class="form-table">
						<tbody>
							<tr class="listeo_settings_text">
								<th class="listeo_settings_text" scope="row"><?php _e("License code", 'listeo_core'); ?>

								</th>
								<td>
									<input type="text" class="regular-text code" name="el_license_key" size="50" placeholder="xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx" required="required">
								</td>
							</tr>
							<tr class="listeo_settings_text">
								<th class="listeo_settings_text" scope="row"><?php _e("Email address", 'listeo_core'); ?>
								<br><small>used to buy Listeo</small>
								</th>
								<td>
									<?php $purchaseEmail   = get_option("Listeo_lic_email"); ?>
									<input type="text" class="regular-text code" name="el_license_email" size="50" value="<?php echo $purchaseEmail; ?>" placeholder="" required="required">
								</td>
							</tr>
						</tbody>
					</table>
					<?php wp_nonce_field('listeo_action_verification_nonce', 'listeo_action_verification_nonce'); ?>
					
					<p class="envato-setup-actions step">
						<input type="submit" class="button-primary button button-large button-next" value="Activate License" name="save_step" />
					</p>

				</form>
			<?php } ?>

		<?php
		}

		/**
		 * Handle license activation form submission
		 */
		public function envato_setup_license_activation_save()
		{
			// Add both console and error log debugging
		if (isset($_POST['how_found_listeo'])) {
		}
		
			
		// Check if feedback was submitted - if so, just send it and proceed
		$nonce_valid = isset($_POST['listeo_feedback_poll_nonce']) && wp_verify_nonce($_POST['listeo_feedback_poll_nonce'], 'listeo_feedback_poll_nonce');
		
		if (isset($_POST['feedback_submitted']) && isset($_POST['how_found_listeo']) && wp_verify_nonce($_POST['listeo_feedback_poll_nonce'], 'listeo_feedback_poll_nonce')) {

			try {
				$how_found = sanitize_text_field($_POST['how_found_listeo']);
				$license_email = get_option("Listeo_lic_email", "");

				$feedback_data = array(
					'response' => $how_found,
					'site_url' => site_url(),
					'timestamp' => current_time('mysql'),
					'theme_version' => wp_get_theme()->get('Version'),
					'license_email' => $license_email,
				);

				// Send to endpoint (non-blocking)
				wp_remote_post('https://purethe.me/poll/setup-feedback-endpoint.php', array(
					'body' => json_encode($feedback_data),
					'headers' => array('Content-Type' => 'application/json'),
					'timeout' => 5,
					'blocking' => false,
					'sslverify' => false,
				));

				// Save locally
				try {
					$feedback_log = get_option('listeo_feedback_responses', array());
					if (!is_array($feedback_log)) $feedback_log = array();
					$feedback_log[] = $feedback_data;
					update_option('listeo_feedback_responses', $feedback_log, false);
					update_option('listeo_setup_feedback_submitted_' . md5(site_url()), true, false);
				} catch (Exception $e) {
					error_log('Feedback save failed: ' . $e->getMessage());
				}

				error_log('✅ Feedback submitted: ' . $how_found);
			} catch (Exception $e) {
				error_log('⚠️ Feedback error (non-critical): ' . $e->getMessage());
			}

			// Proceed to next step
			wp_redirect($this->get_next_step_link());
			exit;
		}

		// Regular license activation flow continues below...
		// Only check license nonce if this is NOT a feedback submission
		if (isset($_POST['feedback_submitted'])) {
			error_log('❌ UNEXPECTED: Feedback condition failed but feedback_submitted is set');
			error_log('POST data: ' . print_r($_POST, true));
			wp_redirect($this->get_next_step_link());
			exit;
		}

			// Verify nonce for security
			if (!isset($_POST['listeo_action_verification_nonce']) || !wp_verify_nonce($_POST['listeo_action_verification_nonce'], 'listeo_action_verification_nonce')) {
				error_log('❌ Setup Wizard - Nonce verification failed');
				set_transient('listeo_setup_license_error', 'Security verification failed. Please try again.', 60);
				wp_redirect($this->get_step_link($this->step));
				exit;
			}

			// Get form data
			$license_key = !empty($_POST['el_license_key']) ? sanitize_text_field($_POST['el_license_key']) : "";
			$license_email = !empty($_POST['el_license_email']) ? sanitize_email($_POST['el_license_email']) : "";

			// Add console logging for debugging

			if (empty($license_key)) {
				set_transient('listeo_setup_license_error', 'Please enter a valid license key.', 60);
				wp_redirect($this->get_step_link($this->step));
				exit;
			}

			if (empty($license_email)) {
				set_transient('listeo_setup_license_error', 'Please enter a valid email address.', 60);
				wp_redirect($this->get_step_link($this->step));
				exit;
			}

			// Save license data to database FIRST
			update_option("Listeo_lic_Key", $license_key);
			update_option("Listeo_lic_email", $license_email);
			$this->listeo_clear_demo_package_cache();

			error_log('✅ Setup Wizard - License data saved to database');

			// IMPORTANT: Clear all license-related caches before validation
			// This prevents cached "already used" messages from persisting
			error_log('🧹 Setup Wizard - Clearing all license caches for fresh validation');
			
			global $wpdb;

			if (class_exists('b472b0Base')) {
				b472b0Base::DeletePersistentCache('listeo_license_cache_' . md5(site_url() . $license_key . $license_email));
				b472b0Base::DeletePersistentCache('listeo_license_valid_' . md5($license_key . $license_email . site_url()));
				b472b0Base::DeletePersistentCacheByPrefix('listeo_api_request_');
				b472b0Base::DeletePersistentCacheByPrefix('listeo_proxy_request_');
			}

			delete_transient('listeo_license_180_' . md5(site_url() . $license_key . $license_email));
			delete_transient('listeo_license_valid_' . md5($license_key . $license_email . site_url()));
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listeo_license_180_%' OR option_name LIKE '_transient_listeo_license_valid_%' OR option_name LIKE '_transient_listeo_license_check_lock_%' OR option_name LIKE '_transient_listeo_api_%' OR option_name LIKE '_transient_listeo_proxy_%'");
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listeo_license_180_%' OR option_name LIKE '_transient_timeout_listeo_license_valid_%' OR option_name LIKE '_transient_timeout_listeo_license_check_lock_%' OR option_name LIKE '_transient_timeout_listeo_api_%' OR option_name LIKE '_transient_timeout_listeo_proxy_%'");
			

			// Try to activate the license using the b472b0Base class
			if (class_exists('b472b0Base')) {
				try {
					error_log('🔍 Setup Wizard - Attempting license verification');
					
					// Use the same method as the main license system
					$message = '';
					$responseObj = null;
					$result = b472b0Base::CheckWPPlugin($license_key, $license_email, $message, $responseObj, get_template_directory() . "/style.css");
					
					if ($responseObj) {
					}
					
					error_log('License validation result: ' . ($result ? 'VALID' : 'INVALID'));
					error_log('License message: ' . $message);
					error_log('Response object: ' . print_r($this->mask_sensitive_response($responseObj), true));
					
					if ($result) {
						error_log('✅ Setup Wizard - License activation successful');
						
						// Update the verification status like the main theme does
						update_option('license_verification_status', 'verified');
						

						error_log('✅ Setup Wizard - License status updated, proceeding to next step');
						
						// License is valid, proceed to next step
						return true;
					} else {
						// Provide more detailed error information
						$detailed_error = '';
						if (empty($message)) {
							$detailed_error = 'License validation failed. Possible reasons: Invalid license key, license already used on another domain, expired license, or server connection issues. Please verify your license key and email address.';
						} else {
							// Improve specific error messages
							if (strpos(strtolower($message), 'temporary inactivated') !== false) {
								$detailed_error = 'Your license key has been temporarily deactivated. Please contact support for assistance.';
							} elseif (strpos(strtolower($message), 'invalid license code') !== false) {
								$detailed_error = 'Invalid license code. This usually means the license key is already registered on another domain. If you want to use it on this domain, please deactivate it from the previous domain first, or contact support if you need help transferring your license.';
							} elseif (strpos(strtolower($message), 'expired') !== false) {
								$detailed_error = 'Your license has expired. Please renew your license to continue using the theme.';
							} else {
								$detailed_error = $message;
							}
						}
						
						error_log('❌ Setup Wizard - License activation failed: ' . $detailed_error);
						
						set_transient('listeo_setup_license_error', $detailed_error, 60);
						
						error_log('❌ Setup Wizard - Staying on current step to show error');
						
						// Redirect back to the same step to show the error message
						wp_redirect($this->get_step_link($this->step));
						exit;
					}
				} catch (Exception $e) {
					set_transient('listeo_setup_license_error', 'License activation failed: ' . $e->getMessage(), 60);
					wp_redirect($this->get_step_link($this->step));
					exit;
				}
			} else {
				set_transient('listeo_setup_license_error', 'License system is not available. Please try activating from Appearance → Theme Options → License.', 60);
				wp_redirect($this->get_step_link($this->step));
				exit;
			}
		}


	/**
	 * Handle feedback form submission via admin-post.php
	 */
	public function handle_feedback_submission()
	{

		// Verify nonce
		if (!isset($_POST['listeo_feedback_poll_nonce']) || 
		    !wp_verify_nonce($_POST['listeo_feedback_poll_nonce'], 'listeo_feedback_poll_nonce')) {
			wp_die('Security check failed', 'Security Error', array('response' => 403));
		}

		// Check if feedback data exists
		if (!isset($_POST['how_found_listeo'])) {
			wp_die('No feedback data provided', 'Error', array('response' => 400));
		}

		$how_found = sanitize_text_field($_POST['how_found_listeo']);
		$license_email = get_option("Listeo_lic_email", "");
		$newsletter_optin = isset($_POST['newsletter_optin']) && $_POST['newsletter_optin'] === 'yes' ? 'yes' : 'no';

		$feedback_data = array(
			'response' => $how_found,
			'site_url' => site_url(),
			'timestamp' => current_time('mysql'),
			'theme_version' => wp_get_theme()->get('Version'),
			'license_email' => $license_email,
			'newsletter' => $newsletter_optin,
		);

		// Send to endpoint (non-blocking)
		wp_remote_post('https://purethe.me/poll/setup-feedback-endpoint.php', array(
			'body' => json_encode($feedback_data),
			'headers' => array('Content-Type' => 'application/json'),
			'timeout' => 5,
			'blocking' => false,
			'sslverify' => false,
		));

		// Save locally
		try {
			$feedback_log = get_option('listeo_feedback_responses', array());
			if (!is_array($feedback_log)) $feedback_log = array();
			$feedback_log[] = $feedback_data;
			update_option('listeo_feedback_responses', $feedback_log, false);
			update_option('listeo_setup_feedback_submitted_' . md5(site_url()), true, false);
		} catch (Exception $e) {
		}

		// Return success (AJAX will handle redirect)
		wp_send_json_success(array(
			'message' => 'Feedback submitted successfully',
			'response' => $how_found
		));
	}
		/**
		 * Page setup
		 */
		public function envato_setup_default_plugins()
		{

			tgmpa_load_bulk_installer();
			// install plugins with TGM.
			if (!class_exists('TGM_Plugin_Activation') || !isset($GLOBALS['tgmpa'])) {
				die('Failed to find TGM');
			}
			$url     = wp_nonce_url(add_query_arg(array('plugins' => 'go')), 'envato-setup');
			$plugins = $this->_get_plugins();

			// copied from TGM

			$method = ''; // Leave blank so WP_Filesystem can populate it as necessary.
			$fields = array_keys($_POST); // Extra fields to pass to WP_Filesystem.

			if (false === ($creds = request_filesystem_credentials(esc_url_raw($url), $method, false, false, $fields))) {
				return true; // Stop the normal page form from displaying, credential request form will be shown.
			}

			// Now we have some credentials, setup WP_Filesystem.
			if (!WP_Filesystem($creds)) {
				// Our credentials were no good, ask the user for them again.
				request_filesystem_credentials(esc_url_raw($url), $method, true, false, $fields);

				return true;
			}

			/* If we arrive here, we have the filesystem */

		?>
			<h1><?php esc_html_e('Default Plugins', 'listeo'); ?></h1>
			<form method="post">

				<?php
				$plugins = $this->_get_plugins();
				if (count($plugins['all'])) {
				?>
					<p><?php esc_html_e('Your website needs a few essential plugins. The following plugins will be installed or updated:', 'listeo'); ?></p>
					<ul class="envato-wizard-plugins">
						<?php foreach ($plugins['all'] as $slug => $plugin) { ?>
							<li data-slug="<?php echo esc_attr($slug); ?>" data-required="<?php echo !empty($plugin['required']) ? '1' : '0'; ?>"><?php echo esc_html($plugin['name']); ?>
								<span>
									<?php
									$keys = array();
									if (isset($plugins['install'][$slug])) {
										$keys[] = 'Installation';
									}
									if (isset($plugins['update'][$slug])) {
										$keys[] = 'Update';
									}
									if (isset($plugins['activate'][$slug])) {
										$keys[] = 'Activation';
									}
									echo implode(' and ', $keys) . ' required';
									?>
								</span>
								<div class="spinner"></div>
							</li>
						<?php } ?>
					</ul>
				<?php
				} else {
					echo '<p style="    color: #0091cd;
    background: #0091cd10;
    margin: 0;
    padding: 2px 9px;
    border-radius: 5px;
    display: flex
;
    margin-bottom: 8px;"><strong>' . esc_html__('Good news! All plugins are already installed and up to date. Please continue.', 'listeo') . '</strong></p>';
				} ?>

				<p><?php esc_html_e('You can add and remove plugins later on from within WordPress.', 'listeo'); ?></p>

				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-large button-next" data-callback="install_plugins"><?php esc_html_e('Continue', 'listeo'); ?></a>
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'listeo'); ?></a>
					<?php wp_nonce_field('envato-setup'); ?>
				</p>
			</form>
		<?php
		}


			public function ajax_plugins()
			{
				if (!check_ajax_referer('envato_setup_nonce', 'wpnonce') || empty($_POST['slug'])) {
					wp_send_json_error(array('error' => 1, 'message' => esc_html__('No Slug Found', 'listeo')));
					}
					$requested_slug = sanitize_key(wp_unslash($_POST['slug']));
					$this->listeo_setup_wizard_clear_plugin_redirects('ajax_plugins_' . $requested_slug);
					$json = array();
					// send back some json we use to hit up TGM
					$plugins = $this->_get_plugins();
				$this->listeo_setup_wizard_log_event('ajax_plugins_state', array(
					'slug'     => $requested_slug,
					'install'  => array_keys($plugins['install']),
					'update'   => array_keys($plugins['update']),
					'activate' => array_keys($plugins['activate']),
					'debug'    => $this->listeo_setup_wizard_get_plugin_debug($requested_slug),
				));
				// what are we doing with this plugin?
				foreach ($plugins['activate'] as $slug => $plugin) {
					if ($requested_slug == $slug) {
						$json = array(
							'url'           => self_admin_url($this->tgmpa_url),
							'plugin'        => array($slug),
						'tgmpa-page'    => $this->tgmpa_menu_slug,
						'plugin_status' => 'all',
						'_wpnonce'      => wp_create_nonce('bulk-plugins'),
						'action'        => 'tgmpa-bulk-activate',
						'action2'       => -1,
						'message'       => esc_html__('Activating Plugin', 'listeo'),
					);
					break;
				}
				}
				foreach ($plugins['update'] as $slug => $plugin) {
					if ($requested_slug == $slug) {
						$json = array(
							'url'           => self_admin_url($this->tgmpa_url),
							'plugin'        => array($slug),
						'tgmpa-page'    => $this->tgmpa_menu_slug,
						'plugin_status' => 'all',
						'_wpnonce'      => wp_create_nonce('bulk-plugins'),
						'action'        => 'tgmpa-bulk-update',
						'action2'       => -1,
						'message'       => esc_html__('Updating Plugin', 'listeo'),
					);
					break;
				}
				}
				foreach ($plugins['install'] as $slug => $plugin) {
					if ($requested_slug == $slug) {
						$json = array(
							'url'           => self_admin_url($this->tgmpa_url),
							'plugin'        => array($slug),
						'tgmpa-page'    => $this->tgmpa_menu_slug,
						'plugin_status' => 'all',
						'_wpnonce'      => wp_create_nonce('bulk-plugins'),
						'action'        => 'tgmpa-bulk-install',
						'action2'       => -1,
						'message'       => esc_html__('Installing Plugin', 'listeo'),
					);
					break;
				}
			}

				if ($json) {
					$json['hash'] = md5(serialize($json)); // used for checking if duplicates happen, move to next plugin
					$this->listeo_setup_wizard_log_event('ajax_plugins_action', array(
						'slug'   => $requested_slug,
						'action' => $json['action'],
						'hash'   => $json['hash'],
						'debug'  => $this->listeo_setup_wizard_get_plugin_debug($requested_slug),
					));
					wp_send_json($json);
				} else {
					$this->listeo_setup_wizard_log_event('ajax_plugins_done', array(
						'slug'  => $requested_slug,
						'debug' => $this->listeo_setup_wizard_get_plugin_debug($requested_slug),
					));
					wp_send_json(array('done' => 1, 'message' => esc_html__('Success', 'listeo')));
				}
				exit;
			}

			public function ajax_plugin_log()
			{
				if (!check_ajax_referer('envato_setup_nonce', 'wpnonce', false)) {
					wp_send_json_error();
				}

				$event = isset($_POST['event']) ? sanitize_key(wp_unslash($_POST['event'])) : 'unknown';
				$slug  = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
				$data  = isset($_POST['data']) && is_array($_POST['data']) ? wp_unslash($_POST['data']) : array();

				$this->listeo_setup_wizard_log_event($event, array(
					'slug' => $slug,
					'data' => $data,
				));

					wp_send_json_success();
				}

				private function listeo_setup_wizard_clear_plugin_redirects($context = '')
				{
					$redirect_transients = array(
						'elementor_activation_redirect',
						'_wc_activation_redirect',
					);
					$cleared = array();

					foreach ($redirect_transients as $transient) {
						if (get_transient($transient)) {
							delete_transient($transient);
							$cleared[] = $transient;
						}
					}

					if ($cleared) {
						$this->listeo_setup_wizard_log_event('cleared_plugin_redirects', array(
							'context'    => $context,
							'transients' => $cleared,
						));
					}
				}

				private function listeo_setup_wizard_log_event($event, $data = array())
				{
					if (!defined('WP_DEBUG') || !WP_DEBUG) {
					return;
				}

				error_log('Listeo Setup Wizard: ' . wp_json_encode(array(
					'event' => sanitize_key($event),
					'data'  => $this->listeo_setup_wizard_sanitize_log_data($data),
				)));
			}

			private function listeo_setup_wizard_sanitize_log_data($data)
			{
				if (is_array($data)) {
					$sanitized = array();
					foreach ($data as $key => $value) {
						$sanitized[sanitize_key($key)] = $this->listeo_setup_wizard_sanitize_log_data($value);
					}
					return $sanitized;
				}

				if (is_scalar($data) || null === $data) {
					return substr(sanitize_text_field((string) $data), 0, 1000);
				}

				return '';
			}

			private function listeo_setup_wizard_get_plugin_debug($slug)
			{
				if (!function_exists('get_plugins')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$debug = array(
					'slug'                  => $slug,
					'plugin_dir_exists'     => is_dir(WP_PLUGIN_DIR . '/' . $slug) ? 1 : 0,
					'slug_main_file_exists' => file_exists(WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php') ? 1 : 0,
					'wp_plugins_match'      => array(),
					'registered'            => 0,
				);

				foreach (get_plugins() as $file => $plugin_data) {
					if (0 === strpos($file, $slug . '/')) {
						$debug['wp_plugins_match'][$file] = isset($plugin_data['Version']) ? $plugin_data['Version'] : '';
					}
				}

				if (empty($GLOBALS['tgmpa'])) {
					$debug['tgmpa_loaded'] = 0;
					return $debug;
				}

				$debug['tgmpa_loaded'] = 1;
				$instance = call_user_func(array(get_class($GLOBALS['tgmpa']), 'get_instance'));

				if (empty($instance->plugins[$slug])) {
					return $debug;
				}

				$plugin = $instance->plugins[$slug];
				$file_path = isset($plugin['file_path']) ? $plugin['file_path'] : '';
				$source = isset($plugin['source']) ? $plugin['source'] : '';

				$debug['registered'] = 1;
				$debug['registered_file_path'] = $file_path;
				$debug['registered_source_type'] = isset($plugin['source_type']) ? $plugin['source_type'] : '';
				$debug['registered_source'] = $source;
				$debug['registered_source_exists'] = $source && file_exists($source) ? 1 : 0;
				$debug['registered_source_size'] = $source && file_exists($source) ? filesize($source) : 0;
				$debug['registered_file_exists'] = $file_path && file_exists(WP_PLUGIN_DIR . '/' . $file_path) ? 1 : 0;
				$debug['registered_is_active'] = $file_path && is_plugin_active($file_path) ? 1 : 0;
				$debug['active_plugins_contains_match'] = in_array($file_path, (array) get_option('active_plugins', array()), true) ? 1 : 0;
				$debug['tgmpa_is_installed'] = $instance->is_plugin_installed($slug) ? 1 : 0;
				$debug['tgmpa_is_active'] = $instance->is_plugin_active($slug) ? 1 : 0;
				$debug['tgmpa_can_activate'] = $instance->can_plugin_activate($slug) ? 1 : 0;
				$debug['tgmpa_has_update'] = false !== $instance->does_plugin_have_update($slug) ? 1 : 0;

				if ($file_path) {
					$all_plugins = get_plugins();
					$debug['registered_installed_version'] = isset($all_plugins[$file_path]['Version']) ? $all_plugins[$file_path]['Version'] : '';
				}

				return $debug;
			}


			private function _content_default_get()
			{

			$content = array();

			// find out what content is in our default json file.
			$available_content = $this->_get_json('default.json');
			foreach ($available_content as $post_type => $post_data) {
				if (count($post_data)) {
					$first           = current($post_data);
					$post_type_title = !empty($first['type_title']) ? $first['type_title'] : ucwords($post_type) . 's';
					if ($post_type_title == 'Navigation Menu Items') {
						$post_type_title = 'Navigation';
					}
					$content[$post_type] = array(
						'title'            => $post_type_title,
						'description'      => sprintf(esc_html__('This will create default %s as seen in the demo.', 'listeo'), $post_type_title),
						'pending'          => esc_html__('Pending.', 'listeo'),
						'installing'       => esc_html__('Installing.', 'listeo'),
						'success'          => esc_html__('Success.', 'listeo'),
						'install_callback' => array($this, '_content_install_type'),
						'checked'          => $this->is_possible_upgrade() ? 0 : 1,
						// dont check if already have content installed.
					);
				}
			}

			$content['widgets'] = array(
				'title'            => esc_html__('Widgets', 'listeo'),
				'description'      => esc_html__('Insert default sidebar widgets as seen in the demo.', 'listeo'),
				'pending'          => esc_html__('Pending.', 'listeo'),
				'installing'       => esc_html__('Installing Default Widgets.', 'listeo'),
				'success'          => esc_html__('Success.', 'listeo'),
				'install_callback' => array($this, '_content_install_widgets'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				// dont check if already have content installed.
			);
			$content['settings'] = array(
				'title'            => esc_html__('Settings', 'listeo'),
				'description'      => esc_html__('Configure default settings.', 'listeo'),
				'pending'          => esc_html__('Pending.', 'listeo'),
				'installing'       => esc_html__('Installing Default Settings.', 'listeo'),
				'success'          => esc_html__('Success.', 'listeo'),
				'install_callback' => array($this, '_content_install_settings'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				// dont check if already have content installed.
			);

			$content = apply_filters($this->theme_name . '_theme_setup_wizard_content', $content);

			return $content;
		}

		/**
		 * Page setup
		 */
			public function envato_setup_default_content()
			{
			?>
				<h1><?php esc_html_e('Default Content', 'listeo'); ?></h1>
				<?php
				$prerequisites = $this->listeo_validate_demo_import_prerequisites();
				if (is_wp_error($prerequisites)) {
					?>
					<div class="notice notice-error">
						<p><?php echo esc_html($prerequisites->get_error_message()); ?></p>
					</div>
					<p class="envato-setup-actions step">
						<a href="<?php echo esc_url($this->get_step_link('default_plugins')); ?>" class="button-primary button button-large"><?php esc_html_e('Back to plugin installation', 'listeo'); ?></a>
						<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'listeo'); ?></a>
					</p>
					<?php
					return;
				}

				$demo_package = $this->listeo_require_demo_package();
				if (is_wp_error($demo_package)) {
					?>
					<div class="notice notice-error">
						<p><?php echo esc_html($demo_package->get_error_message()); ?></p>
					</div>
					<p class="envato-setup-actions step">
						<a href="<?php echo esc_url($this->get_step_link('license_activation')); ?>" class="button-primary button button-large"><?php esc_html_e('Back to license activation', 'listeo'); ?></a>
						<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'listeo'); ?></a>
					</p>
					<?php
					return;
				}

				$current_kit = get_option('elementor_active_kit');
				//remove current kit page
				//remove page with id $current_kit

			// if ($homepage) {
			// 	update_option('page_on_front', $homepage->ID);
			// 	update_option('show_on_front', 'page');
			// }

			if ($current_kit) {

				global $wpdb;
				$kit_options = serialize(array(
					'system_colors' =>
					array(
						0 =>
						array(
							'_id' => 'primary',
							'title' => 'Primary',
							'color' => '#222222',
						),
						1 =>
						array(
							'_id' => 'secondary',
							'title' => 'Secondary',
							'color' => '#54595F',
						),
						2 =>
						array(
							'_id' => 'text',
							'title' => 'Text',
							'color' => '#7A7A7A',
						),
						3 =>
						array(
							'_id' => 'accent',
							'title' => 'Accent',
							'color' => '#61CE70',
						),
					),
					'custom_colors' =>
					array(),
					'system_typography' =>
					array(
						0 =>
						array(
							'_id' => 'primary',
							'title' => 'Primary',
							'typography_typography' => 'custom',
							'typography_font_family' => 'Roboto',
							'typography_font_weight' => '600',
						),
						1 =>
						array(
							'_id' => 'secondary',
							'title' => 'Secondary',
							'typography_typography' => 'custom',
							'typography_font_family' => 'Roboto Slab',
							'typography_font_weight' => '400',
						),
						2 =>
						array(
							'_id' => 'text',
							'title' => 'Text',
							'typography_typography' => 'custom',
							'typography_font_family' => 'Roboto',
							'typography_font_weight' => '400',
						),
						3 =>
						array(
							'_id' => 'accent',
							'title' => 'Accent',
							'typography_typography' => 'custom',
							'typography_font_family' => 'Roboto',
							'typography_font_weight' => '500',
						),
					),
					'custom_typography' =>
					array(),
					'default_generic_fonts' => 'Sans-serif',
					'site_name' => 'Listeo',
					'site_description' => 'Directory &amp; Listings WP Theme',
					'container_width' =>
					array(
						'unit' => 'px',
						'size' => 1180,
						'sizes' =>
						array(),
					),
					'page_title_selector' => 'h1.entry-title',
					'viewport_md' => 768,
					'viewport_lg' => 1025,
					'active_breakpoints' =>
					array(
						0 => 'viewport_mobile',
						1 => 'viewport_tablet',
						2 => 'viewport_widescreen',
					),
					'viewport_widescreen' => 1700,
					'container_width_widescreen' =>
					array(
						'unit' => 'px',
						'size' => 1340,
						'sizes' =>
						array(),
					),
					'colors_enable_styleguide_preview' => 'yes',
					'activeItemIndex' => 1,
				));

				// set kit_option as value of meta key '_elementor_page_settings' for page with id $current_kit

				// Check if the meta key '_elementor_page_settings' exists for the page with id $current_kit
				$meta_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = '_elementor_page_settings'", $current_kit));

				if ($meta_exists) {
					// Update the meta key
					$wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta SET meta_value = %s WHERE post_id = %d AND meta_key = '_elementor_page_settings'", $kit_options, $current_kit));
				} else {
					// Insert the meta key
					$wpdb->query($wpdb->prepare("INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) VALUES (%d, '_elementor_page_settings', %s)", $current_kit, $kit_options));
				}
			}
			?>
			<form method="post">
				<?php if ($this->is_possible_upgrade()) { ?>
					<p><?php esc_html_e('It looks like you already have content installed on this website. If you would like to install the default demo content as well you can select it below. Otherwise just choose the upgrade option to ensure everything is up to date.', 'listeo'); ?></p>
				<?php } else { ?>
					<p><?php printf(esc_html__('Insert default content for your new site. Choose what to import below and click Continue. You can manage it later from the WordPress dashboard.', 'listeo'), '<a href="' . esc_url(self_admin_url('edit.php?post_type=page')) . '" target="_blank">', '</a>'); ?></p>
					<?php } ?>
				<ul class="envato-setup-pages envato-default-content-list">
					<?php foreach ($this->_content_default_get() as $slug => $default) { ?>
						<li class="envato_default_content envato-default-content-item" data-content="<?php echo esc_attr($slug); ?>">
							<label class="envato-default-content-check" for="default_content_<?php echo esc_attr($slug); ?>">
								<input type="checkbox" name="default_content[<?php echo esc_attr($slug); ?>]" class="envato_default_content" id="default_content_<?php echo esc_attr($slug); ?>" value="1" <?php echo (!isset($default['checked']) || $default['checked']) ? ' checked' : ''; ?>>
								<span class="envato-default-content-check-icon" aria-hidden="true">
									<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M3 7.1L5.7 9.8L11 4.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</span>
							</label>
							<div class="envato-default-content-main">
								<span class="envato-default-content-title-row">
									<label class="envato-default-content-title" for="default_content_<?php echo esc_attr($slug); ?>"><?php echo esc_html($default['title']); ?></label>
									<span class="envato-default-content-tooltip">
										<button type="button" class="envato-default-content-help" aria-expanded="false" aria-describedby="default_content_tooltip_<?php echo esc_attr($slug); ?>" aria-label="<?php echo esc_attr(sprintf(__('What is %s?', 'listeo'), $default['title'])); ?>">?</button>
										<span class="envato-default-content-tooltip-panel" id="default_content_tooltip_<?php echo esc_attr($slug); ?>" role="tooltip"><?php echo esc_html($default['description']); ?></span>
									</span>
								</span>
							</div>
							<div class="status"><span><?php echo esc_html($default['pending']); ?></span>
								<div class="spinner"></div>
							</div>
						</li>
					<?php } ?>
				</ul>
								<br>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-large button-next" data-callback="install_content"><?php esc_html_e('Continue', 'listeo'); ?></a>
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'listeo'); ?></a>
					<?php wp_nonce_field('envato-setup'); ?>
				</p>
			</form>
		<?php
		}


			public function ajax_content()
			{
				$content_slug = isset($_POST['content']) && is_scalar($_POST['content']) ? sanitize_key(wp_unslash($_POST['content'])) : '';
				error_log("ENVATO DEBUG: AJAX content request started for: " . ($content_slug ? $content_slug : 'UNKNOWN'));
				
				// Attempt to increase PHP limits for import reliability
				@ini_set('max_execution_time', 300);
				@ini_set('memory_limit', '512M');

				if (!current_user_can('manage_options')) {
					wp_send_json_error(array('error' => 1, 'message' => esc_html__('Unauthorized request.', 'listeo')), 403);
				}

				$ajax_nonce = '';
				if (isset($_POST['wpnonce']) && is_scalar($_POST['wpnonce'])) {
					$ajax_nonce = sanitize_text_field(wp_unslash($_POST['wpnonce']));
				} elseif (isset($_POST['_wpnonce']) && is_scalar($_POST['_wpnonce'])) {
					$ajax_nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
				}

				if (!wp_verify_nonce($ajax_nonce, 'envato_setup_nonce')) {
					wp_send_json_error(array('error' => 1, 'message' => esc_html__('Security verification failed.', 'listeo')), 403);
				}

				$prerequisites = $this->listeo_validate_demo_import_prerequisites();
				if (is_wp_error($prerequisites)) {
					wp_send_json_error(array('error' => 1, 'message' => esc_html($prerequisites->get_error_message())), 428);
				}

				$demo_package = $this->listeo_require_demo_package();
				if (is_wp_error($demo_package)) {
					wp_send_json_error(
						array('error' => 1, 'message' => esc_html($demo_package->get_error_message())),
						$this->listeo_get_demo_package_error_status($demo_package, 403)
					);
				}
				
				$content = $this->_content_default_get();
				if (empty($content_slug) || !isset($content[$content_slug])) {
					error_log("ENVATO DEBUG: AJAX content request FAILED - nonce or content issue");
					wp_send_json_error(array('error' => 1, 'message' => esc_html__('No content found.', 'listeo')), 400);
				}

				$json         = false;
				$this_content = $content[$content_slug];
				$_POST['content'] = $content_slug;

				if (isset($_POST['proceed'])) {
					// install the content!
					error_log("ENVATO DEBUG: Processing content type: " . $content_slug . " with proceed=true");

					$this->log(' -!! STARTING SECTION for ' . $content_slug);

				// init delayed posts from transient.
				$this->delay_posts = get_transient('delayed_posts');
				if (!is_array($this->delay_posts)) {
					$this->delay_posts = array();
				}

				if (!empty($this_content['install_callback'])) {
					if ($result = call_user_func($this_content['install_callback'])) {

						$this->log(' -- FINISH. Writing ' . count($this->delay_posts, COUNT_RECURSIVE) . ' delayed posts to transient ');
						set_transient('delayed_posts', $this->delay_posts, 60 * 60 * 24);

						if (is_array($result) && isset($result['retry'])) {
							// we split the stuff up again.
							$json = array(
								'url'          => admin_url('admin-ajax.php'),
								'action'       => 'envato_setup_content',
								'proceed'      => 'true',
								'retry'        => $result['retry_offset'],
								'retry_count'  => $result['retry_count'],
								'retry_offset' => $result['retry_offset'],
								'content'      => $content_slug,
								'wpnonce'      => wp_create_nonce('envato_setup_nonce'),
								'_wpnonce'     => wp_create_nonce('envato_setup_nonce'),
								'message'      => $this_content['installing'],
								'logs'         => $this->logs,
								'errors'       => $this->errors,
							);
						} else {
							$json = array(
								'done'    => 1,
								'message' => $this_content['success'],
								'debug'   => $result,
								'logs'    => $this->logs,
								'errors'  => $this->errors,
							);
						}
					}
				}
			} else {

				$json = array(
						'url'      => admin_url('admin-ajax.php'),
						'action'   => 'envato_setup_content',
						'proceed'  => 'true',
						'content'  => $content_slug,
						'wpnonce'  => wp_create_nonce('envato_setup_nonce'),
						'_wpnonce' => wp_create_nonce('envato_setup_nonce'),
					'message'  => $this_content['installing'],
					'logs'     => $this->logs,
					'errors'   => $this->errors,
				);
			}

			if ($json) {
				$json['hash'] = md5(serialize($json)); // used for checking if duplicates happen, move to next plugin
				wp_send_json($json);
			} else {
				wp_send_json(array(
					'error'   => 1,
					'message' => esc_html__('Error', 'listeo'),
					'logs'    => $this->logs,
					'errors'  => $this->errors,
				));
			}

			exit;
		}


		private function _imported_term_id($original_term_id, $new_term_id = false)
		{
			$terms = get_transient('importtermids');
			if (!is_array($terms)) {
				$terms = array();
			}
			if ($new_term_id) {
				if (!isset($terms[$original_term_id])) {
					$this->log('Insert old TERM ID ' . $original_term_id . ' as new TERM ID: ' . $new_term_id);
				} else if ($terms[$original_term_id] != $new_term_id) {
					$this->error('Replacement OLD TERM ID ' . $original_term_id . ' overwritten by new TERM ID: ' . $new_term_id);
				}
				$terms[$original_term_id] = $new_term_id;
				set_transient('importtermids', $terms, 60 * 60 * 24);
			} else if ($original_term_id && isset($terms[$original_term_id])) {
				return $terms[$original_term_id];
			}

			return false;
		}


		public function vc_post($post_id = false)
		{

			$vc_post_ids = get_transient('import_vc_posts');
			if (!is_array($vc_post_ids)) {
				$vc_post_ids = array();
			}
			if ($post_id) {
				$vc_post_ids[$post_id] = $post_id;
				set_transient('import_vc_posts', $vc_post_ids, 60 * 60 * 24);
			} else {

				$this->log('Processing vc pages 2: ');

				return;
				if (class_exists('Vc_Manager') && class_exists('Vc_Post_Admin')) {
					$this->log($vc_post_ids);
					$vc_manager = Vc_Manager::getInstance();
					$vc_base    = $vc_manager->vc();
					$post_admin = new Vc_Post_Admin();
					foreach ($vc_post_ids as $vc_post_id) {
						$this->log('Save ' . $vc_post_id);
						$vc_base->buildShortcodesCustomCss($vc_post_id);
						$post_admin->save($vc_post_id);
						$post_admin->setSettings($vc_post_id);
						//twice? bug?
						$vc_base->buildShortcodesCustomCss($vc_post_id);
						$post_admin->save($vc_post_id);
						$post_admin->setSettings($vc_post_id);
					}
				}
			}
		}


		public function elementor_post($post_id = false)
		{

			// regenrate the CSS for this Elementor post
			if (class_exists('Elementor\Post_CSS_File')) {
				$post_css = new Elementor\Post_CSS_File($post_id);
				$post_css->update();
			} elseif (class_exists('\Elementor\Core\Files\CSS\Post')) {
				$post_css = new \Elementor\Core\Files\CSS\Post($post_id);
				$post_css->update();
			}
		}



		private function _imported_post_id($original_id = false, $new_id = false)
		{
			if (is_array($original_id) || is_object($original_id)) {
				return false;
			}
			$post_ids = get_transient('importpostids');
			if (!is_array($post_ids)) {
				$post_ids = array();
			}
			if ($new_id) {
				if (!isset($post_ids[$original_id])) {
					$this->log('Insert old ID ' . $original_id . ' as new ID: ' . $new_id);
				} else if ($post_ids[$original_id] != $new_id) {
					$this->error('Replacement OLD ID ' . $original_id . ' overwritten by new ID: ' . $new_id);
				}
				$post_ids[$original_id] = $new_id;
				set_transient('importpostids', $post_ids, 60 * 60 * 24);
			} else if ($original_id && isset($post_ids[$original_id])) {
				if (is_numeric($post_ids[$original_id])) {
					$mapped_post_id = absint($post_ids[$original_id]);
					$mapped_post    = $mapped_post_id ? get_post($mapped_post_id) : false;
					if (!$mapped_post || $mapped_post->post_status === 'trash') {
						$this->log('Ignoring stale imported post ID mapping for old ID ' . $original_id . '.');
						unset($post_ids[$original_id]);
						set_transient('importpostids', $post_ids, 60 * 60 * 24);
						return false;
					}
				}
				return $post_ids[$original_id];
			} else if ($original_id === false) {
				return $post_ids;
			}

			return false;
		}

		private function _post_orphans($original_id = false, $missing_parent_id = false)
		{
			$post_ids = get_transient('postorphans');
			if (!is_array($post_ids)) {
				$post_ids = array();
			}
			if ($missing_parent_id) {
				$post_ids[$original_id] = $missing_parent_id;
				set_transient('postorphans', $post_ids, 60 * 60 * 24);
			} else if ($original_id && isset($post_ids[$original_id])) {
				return $post_ids[$original_id];
			} else if ($original_id === false) {
				return $post_ids;
			}

			return false;
		}

		private function _cleanup_imported_ids()
		{
			// loop over all attachments and assign the correct post ids to those attachments.

		}

		private $delay_posts = array();

		private function _delay_post_process($post_type, $post_data)
		{
			if (!isset($this->delay_posts[$post_type])) {
				$this->delay_posts[$post_type] = array();
			}
			$this->delay_posts[$post_type][$post_data['post_id']] = $post_data;
		}


		// return the difference in length between two strings
		public function cmpr_strlen($a, $b)
		{
			return strlen($b) - strlen($a);
		}

		private function listeo_normalize_import_meta_value($meta_val)
		{
			if (is_array($meta_val) && count($meta_val) === 1) {
				$single_meta = current($meta_val);
				if (!is_array($single_meta)) {
					return $single_meta;
				}
			}

			return $meta_val;
		}

		private function listeo_prepare_imported_elementor_meta($meta_key, $meta_val)
		{
			$meta_val = $this->listeo_normalize_import_meta_value($meta_val);

			if ($meta_key !== '_elementor_data') {
				return $meta_val;
			}

			if (is_array($meta_val)) {
				$encoded_meta = wp_json_encode($meta_val);
				return false === $encoded_meta ? '' : wp_slash($encoded_meta);
			}

			if (is_string($meta_val)) {
				$meta_val = trim($meta_val);
				if ($meta_val === '') {
					return '';
				}

				if (is_serialized($meta_val)) {
					$unserialized_meta = maybe_unserialize($meta_val);
					if (is_array($unserialized_meta)) {
						$encoded_meta = wp_json_encode($unserialized_meta);
						return false === $encoded_meta ? '' : wp_slash($encoded_meta);
					}
				}

				$decoded_meta = json_decode($meta_val, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_meta)) {
					$encoded_meta = wp_json_encode($decoded_meta);
					return false === $encoded_meta ? '' : wp_slash($encoded_meta);
				}

				return wp_slash($meta_val);
			}

			return $meta_val;
		}

		private function listeo_existing_elementor_post_needs_repair($post_id)
		{
			$elementor_data = get_post_meta($post_id, '_elementor_data', true);

			if (is_string($elementor_data)) {
				$elementor_data = trim($elementor_data);
				if ($elementor_data === '') {
					return true;
				}

				$decoded_data = json_decode($elementor_data, true);
				return json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_data);
			}

			return empty($elementor_data) || !is_array($elementor_data);
		}

		private function listeo_refresh_existing_elementor_post($post_id, $post_data)
		{
			if (empty($post_data['meta']) || !is_array($post_data['meta']) || empty($post_data['meta']['_elementor_data'])) {
				return;
			}

			if (!$this->listeo_existing_elementor_post_needs_repair($post_id)) {
				return;
			}

			foreach ($post_data['meta'] as $meta_key => $meta_val) {
				if (strpos($meta_key, '_elementor') !== 0 && $meta_key !== '_wp_page_template') {
					continue;
				}

				update_post_meta($post_id, $meta_key, $this->listeo_prepare_imported_elementor_meta($meta_key, $meta_val));
			}

			$this->elementor_post($post_id);
			$this->log('Refreshed Elementor data for existing post ID ' . $post_id);
		}

		private function _process_post_data($post_type, $post_data, $delayed = 0, $debug = false)
		{

			$this->log(" Processing $post_type " . $post_data['post_id']);
			$original_post_data = $post_data;

			if ($debug) {
				echo "HERE\n";
			}
			if (!post_type_exists($post_type)) {
				return false;
			}
			$already_imported_post_id = !$debug ? $this->_imported_post_id($post_data['post_id']) : false;
			if ($already_imported_post_id) {
				$this->listeo_refresh_existing_elementor_post($already_imported_post_id, $post_data);
				return true; // already done :)
			}
			/*if ( 'nav_menu_item' == $post_type ) {
				$this->process_menu_item( $post );
				continue;
			}*/

			if (empty($post_data['post_title']) && empty($post_data['post_name'])) {
				// this is menu items
				$post_data['post_name'] = $post_data['post_id'];
			}

			$post_data['post_type'] = $post_type;

			$post_parent = (int) $post_data['post_parent'];
			if ($post_parent) {
				// if we already know the parent, map it to the new local ID
				if ($this->_imported_post_id($post_parent)) {
					$post_data['post_parent'] = $this->_imported_post_id($post_parent);
					// otherwise record the parent for later
				} else {
					$this->_post_orphans(intval($post_data['post_id']), $post_parent);
					$post_data['post_parent'] = 0;
				}
			}

			// check if already exists
			if (!$debug) {
				if (empty($post_data['post_title']) && !empty($post_data['post_name'])) {
					global $wpdb;
					$sql     = "
					SELECT ID, post_name, post_parent, post_type
					FROM $wpdb->posts
					WHERE post_name = %s
					AND post_type = %s
				";
					$pages   = $wpdb->get_results($wpdb->prepare($sql, array(
						$post_data['post_name'],
						$post_type,
					)), OBJECT_K);
					$foundid = 0;
					foreach ((array) $pages as $page) {
						if ($page->post_name == $post_data['post_name'] && empty($page->post_title)) {
							$foundid = $page->ID;
						}
					}
					if ($foundid) {
						$this->_imported_post_id($post_data['post_id'], $foundid);
						$this->listeo_refresh_existing_elementor_post($foundid, $post_data);

						return true;
					}
				}
				// dont use post_exists because it will dupe up on media with same name but different slug
				if (!empty($post_data['post_title']) && !empty($post_data['post_name'])) {
					global $wpdb;
					$sql     = "
					SELECT ID, post_name, post_parent, post_type
					FROM $wpdb->posts
					WHERE post_name = %s
					AND post_title = %s
					AND post_type = %s
					";
					$pages   = $wpdb->get_results($wpdb->prepare($sql, array(
						$post_data['post_name'],
						$post_data['post_title'],
						$post_type,
					)), OBJECT_K);
					$foundid = 0;
					foreach ((array) $pages as $page) {
						if ($page->post_name == $post_data['post_name']) {
							$foundid = $page->ID;
						}
					}
					if ($foundid) {
						$this->_imported_post_id($post_data['post_id'], $foundid);
						$this->listeo_refresh_existing_elementor_post($foundid, $post_data);

						return true;
					}
				}
			}

			// backwards compat with old import format.
			if (isset($post_data['meta'])) {
				foreach ($post_data['meta'] as $key => $meta) {
					if (is_array($meta) && count($meta) == 1) {
						$single_meta = current($meta);
						if (!is_array($single_meta)) {
							$post_data['meta'][$key] = $single_meta;
						}
					}
				}
			}

			switch ($post_type) {
				case 'attachment':
					// import media via url
					if (!empty($post_data['guid'])) {
						error_log("ENVATO DEBUG: Processing attachment: " . $post_data['post_title'] . " (ID: " . $post_data['post_id'] . ")");

						// Force GD image editor for better reliability on shared hosting
						add_filter('wp_image_editors', function($editors) {
							return ['WP_Image_Editor_GD'];
						}, 999);

						// check if this has already been imported.
						$old_guid = $post_data['guid'];
						if ($this->_imported_post_id($old_guid)) {
							error_log("ENVATO DEBUG: Attachment already imported: " . $post_data['post_title']);
							return true; // alrady done;
						}
						// ignore post parent, we haven't imported those yet.
						//                          $file_data = wp_remote_get($post_data['guid']);
						$remote_url = $post_data['guid'];

						$post_data['upload_date'] = date('Y/m', strtotime($post_data['post_date_gmt']));
						if (isset($post_data['meta'])) {
							foreach ($post_data['meta'] as $key => $meta) {
								if ($key == '_wp_attached_file') {
									foreach ((array) $meta as $meta_val) {
										if (preg_match('%^[0-9]{4}/[0-9]{2}%', $meta_val, $matches)) {
											$post_data['upload_date'] = $matches[0];
										}
									}
								}
							}
						}

						$upload = $this->_fetch_remote_file($remote_url, $post_data);

						if (!is_array($upload) || is_wp_error($upload)) {
							error_log("ENVATO DEBUG: Attachment FAILED: " . ($upload && is_wp_error($upload) ? $upload->get_error_message() : 'Unknown error') . " for " . $post_data['post_title']);
							return false;
						}

						if ($info = wp_check_filetype($upload['file'])) {
							$post['post_mime_type'] = $info['type'];
						} else {
							return false;
						}

						$post_data['guid'] = $upload['url'];

						// as per wp-admin/includes/upload.php
						$post_id = wp_insert_attachment($post_data, $upload['file']);
						if ($post_id) {
							error_log("ENVATO DEBUG: Attachment SUCCESS: Created ID $post_id for " . $post_data['post_title']);

							if (!empty($post_data['meta'])) {
								foreach ($post_data['meta'] as $meta_key => $meta_val) {
									if ($meta_key != '_wp_attached_file' && !empty($meta_val)) {
									update_post_meta($post_id, $meta_key, $meta_val);
									}
								}
							}

							wp_update_attachment_metadata($post_id, wp_generate_attachment_metadata($post_id, $upload['file']));

							// remap resized image URLs, works by stripping the extension and remapping the URL stub.
							if (preg_match('!^image/!', $info['type'])) {
								$parts = pathinfo($remote_url);
								$name  = basename($parts['basename'], ".{$parts['extension']}"); // PATHINFO_FILENAME in PHP 5.2

								$parts_new = pathinfo($upload['url']);
								$name_new  = basename($parts_new['basename'], ".{$parts_new['extension']}");

								$this->_imported_post_id($parts['dirname'] . '/' . $name, $parts_new['dirname'] . '/' . $name_new);
							}
							$this->_imported_post_id($post_data['post_id'], $post_id);
						}
					}
					break;
				default:
					// work out if we have to delay this post insertion

					$replace_meta_vals = array();

					if (!empty($post_data['meta']) && is_array($post_data['meta'])) {

						// replace any elementor post data:

						// fix for double json encoded stuff:
						foreach ($post_data['meta'] as $meta_key => $meta_val) {
							if (is_string($meta_val) && strlen($meta_val) && $meta_val[0] == '[') {
								$test_json = @json_decode($meta_val, true);
								if (is_array($test_json)) {
									$post_data['meta'][$meta_key] = $test_json;
								}
							}
						}

						array_walk_recursive($post_data['meta'], array($this, '_elementor_id_import'));

						// replace menu data:
						// work out what we're replacing. a tax, page, term etc..

						if (!empty($post_data['meta']['_menu_item_menu_item_parent'])) {
							$new_parent_id = $this->_imported_post_id($post_data['meta']['_menu_item_menu_item_parent']);
							if (!$new_parent_id) {
								if ($delayed) {
									// already delayed, unable to find this meta value, skip inserting it
									$this->error('Unable to find replacement. Continue anyway.... content will most likely break..');
								} else {
									$this->error('Unable to find replacement. Delaying.... ');
									$this->_delay_post_process($post_type, $original_post_data);
									return false;
								}
							}
							$post_data['meta']['_menu_item_menu_item_parent'] = $new_parent_id;
						}
						switch ($post_data['meta']['_menu_item_type']) {
							case 'post_type':
								if (!empty($post_data['meta']['_menu_item_object_id'])) {
									$new_parent_id = $this->_imported_post_id($post_data['meta']['_menu_item_object_id']);
									if (!$new_parent_id) {
										if ($delayed) {
											// already delayed, unable to find this meta value, skip inserting it
											$this->error('Unable to find replacement. Continue anyway.... content will most likely break..');
										} else {
											$this->error('Unable to find replacement. Delaying.... ');
											$this->_delay_post_process($post_type, $original_post_data);
											return false;
										}
									}
									$post_data['meta']['_menu_item_object_id'] = $new_parent_id;
								}
								break;
							case 'taxonomy':
								if (!empty($post_data['meta']['_menu_item_object_id'])) {
									$new_parent_id = $this->_imported_term_id($post_data['meta']['_menu_item_object_id']);
									if (!$new_parent_id) {
										if ($delayed) {
											// already delayed, unable to find this meta value, skip inserting it
											$this->error('Unable to find replacement. Continue anyway.... content will most likely break..');
										} else {
											$this->error('Unable to find replacement. Delaying.... ');
											$this->_delay_post_process($post_type, $original_post_data);
											return false;
										}
									}
									$post_data['meta']['_menu_item_object_id'] = $new_parent_id;
								}
								break;
						}

						// please ignore this horrible loop below:
						// it was an attempt to automate different visual composer meta key replacements
						// but I'm not using visual composer any more, so ignoring it.
						foreach ($replace_meta_vals as $meta_key_to_replace => $meta_values_to_replace) {

							$meta_keys_to_replace   = explode('|', $meta_key_to_replace);
							$success                = false;
							$trying_to_find_replace = false;
							foreach ($meta_keys_to_replace as $meta_key) {

								if (!empty($post_data['meta'][$meta_key])) {

									$meta_val = $post_data['meta'][$meta_key];

									// export gets meta straight from the DB so could have a serialized string

									if ($debug) {
										echo "Meta key: $meta_key \n";
										print_r($meta_val);
									}

									// if we're replacing a single post/tax value.
									if (isset($meta_values_to_replace['post']) && $meta_values_to_replace['post'] && (int) $meta_val > 0) {
										$trying_to_find_replace = true;
										$new_meta_val           = $this->_imported_post_id($meta_val);
										if ($new_meta_val) {
											$post_data['meta'][$meta_key] = $new_meta_val;
											$success                        = true;
										} else {
											$success = false;
											break;
										}
									}
									if (isset($meta_values_to_replace['taxonomy']) && $meta_values_to_replace['taxonomy'] && (int) $meta_val > 0) {
										$trying_to_find_replace = true;
										$new_meta_val           = $this->_imported_term_id($meta_val);
										if ($new_meta_val) {
											$post_data['meta'][$meta_key] = $new_meta_val;
											$success                        = true;
										} else {
											$success = false;
											break;
										}
									}
									if (is_array($meta_val) && isset($meta_values_to_replace['posts'])) {

										foreach ($meta_values_to_replace['posts'] as $post_array_key) {

											$this->log('Trying to find/replace "' . $post_array_key . '"" in the ' . $meta_key . ' sub array:');
											//$this->log(var_export($meta_val,true));

											$this_success = false;
											array_walk_recursive($meta_val, function (&$item, $key) use (&$trying_to_find_replace, $post_array_key, &$success, &$this_success, $post_type, $original_post_data, $meta_key, $delayed) {
												if ($key == $post_array_key && (int) $item > 0) {
													$trying_to_find_replace = true;
													$new_insert_id          = $this->_imported_post_id($item);
													if ($new_insert_id) {
														$success      = true;
														$this_success = true;
														$this->log('Found' . $meta_key . ' -> ' . $post_array_key . ' replacement POST ID insert for ' . $item . ' ( as ' . $new_insert_id . ' ) ');
														$item = $new_insert_id;
													} else {
														$this->error('Unable to find ' . $meta_key . ' -> ' . $post_array_key . ' POST ID insert for ' . $item . ' ');
													}
												}
											});
											if ($this_success) {
												$post_data['meta'][$meta_key] = $meta_val;
											}
										}
										foreach ($meta_values_to_replace['taxonomies'] as $post_array_key) {

											$this->log('Trying to find/replace "' . $post_array_key . '"" TAXONOMY in the ' . $meta_key . ' sub array:');
											//$this->log(var_export($meta_val,true));

											$this_success = false;
											array_walk_recursive($meta_val, function (&$item, $key) use (&$trying_to_find_replace, $post_array_key, &$success, &$this_success, $post_type, $original_post_data, $meta_key, $delayed) {
												if ($key == $post_array_key && (int) $item > 0) {
													$trying_to_find_replace = true;
													$new_insert_id          = $this->_imported_term_id($item);
													if ($new_insert_id) {
														$success      = true;
														$this_success = true;
														$this->log('Found' . $meta_key . ' -> ' . $post_array_key . ' replacement TAX ID insert for ' . $item . ' ( as ' . $new_insert_id . ' ) ');
														$item = $new_insert_id;
													} else {
														$this->error('Unable to find ' . $meta_key . ' -> ' . $post_array_key . ' TAX ID insert for ' . $item . ' ');
													}
												}
											});

											if ($this_success) {
												$post_data['meta'][$meta_key] = $meta_val;
											}
										}
									}

									if ($success) {
										if ($debug) {
											echo "Meta key AFTER REPLACE: $meta_key \n";
											print_r($post_data['meta']);
										}
									}
								}
							}
							if ($trying_to_find_replace) {
								$this->log('Trying to find/replace postmeta "' . $meta_key_to_replace . '" ');
								if (!$success) {
									// failed to find a replacement.
									if ($delayed) {
										// already delayed, unable to find this meta value, skip inserting it
										$this->error('Unable to find replacement. Continue anyway.... content will most likely break..');
									} else {
										$this->error('Unable to find replacement. Delaying.... ');
										$this->_delay_post_process($post_type, $original_post_data);

										return false;
									}
								} else {
									$this->log('SUCCESSSS ');
								}
							}
						}
					}

					$post_data['post_content'] = $this->_parse_gallery_shortcode_content($post_data['post_content']);

					// we have to fix up all the visual composer inserted image ids
					$replace_post_id_keys = array(
						'parallax_image',
						'dtbwp_row_image_top',
						'dtbwp_row_image_bottom',
						'image',
						'imagebox',
						'logo',
						'item', // vc grid
						'post_id',
					);
					foreach ($replace_post_id_keys as $replace_key) {
						if (preg_match_all('# ' . $replace_key . '="(\d+)"#', $post_data['post_content'], $matches)) {
							foreach ($matches[0] as $match_id => $string) {
								$new_id = $this->_imported_post_id($matches[1][$match_id]);
								if ($new_id) {
									$post_data['post_content'] = str_replace($string, ' ' . $replace_key . '="' . $new_id . '"', $post_data['post_content']);
								} else {
									$this->error('Unable to find POST replacement for ' . $replace_key . '="' . $matches[1][$match_id] . '" in content.');
									if ($delayed) {
										// already delayed, unable to find this meta value, insert it anyway.

									} else {

										$this->error('Adding ' . $post_data['post_id'] . ' to delay listing.');
										//                                      echo "Delaying post id ".$post_data['post_id']."... \n\n";
										$this->_delay_post_process($post_type, $original_post_data);

										return false;
									}
								}
							}
						}
					}
					$replace_tax_id_keys = array(
						'taxonomies',
						'category',
					);
					foreach ($replace_tax_id_keys as $replace_key) {
						if (preg_match_all('# ' . $replace_key . '="(\d+)"#', $post_data['post_content'], $matches)) {
							foreach ($matches[0] as $match_id => $string) {
								$new_id = $this->_imported_term_id($matches[1][$match_id]);
								if ($new_id) {
									$post_data['post_content'] = str_replace($string, ' ' . $replace_key . '="' . $new_id . '"', $post_data['post_content']);
								} else {
									$this->error('Unable to find TAXONOMY replacement for ' . $replace_key . '="' . $matches[1][$match_id] . '" in content.');
									if ($delayed) {
										// already delayed, unable to find this meta value, insert it anyway.
									} else {
										//                                      echo "Delaying post id ".$post_data['post_id']."... \n\n";
										$this->_delay_post_process($post_type, $original_post_data);

										return false;
									}
								}
							}
						}
					}




					$post_id = wp_insert_post($post_data, true);
					if (!is_wp_error($post_id)) {
						$this->_imported_post_id($post_data['post_id'], $post_id);
						// add/update post meta
						if (!empty($post_data['meta'])) {
							foreach ($post_data['meta'] as $meta_key => $meta_val) {

								// if the post has a featured image, take note of this in case of remap
								if ('_thumbnail_id' == $meta_key) {
									/// find this inserted id and use that instead.
									$inserted_id = $this->_imported_post_id(intval($meta_val));
									if ($inserted_id) {
										$meta_val = $inserted_id;
									}
								}

								if ('_gallery' == $meta_key) {
									$new_meta_val = array();
									foreach ($meta_val as $id => $key) {
										$inserted_id = $this->_imported_post_id(intval($id));
										$new_meta_val[$inserted_id] = $key;
									}
									$meta_val = $new_meta_val;
								}
									if (strpos($meta_key, '_elementor') === 0 || $meta_key === '_wp_page_template') {
										$meta_val = $this->listeo_prepare_imported_elementor_meta($meta_key, $meta_val);
									}
									//                                  echo "Post meta $meta_key was $meta_val \n\n";

									update_post_meta($post_id, $meta_key, $meta_val);
								}
							}
							if (!empty($post_data['terms'])) {
								$terms_to_set = array();
								foreach ($post_data['terms'] as $term_slug => $terms) {
								foreach ($terms as $term) {

									$taxonomy = $term['taxonomy'];
									if (taxonomy_exists($taxonomy)) {
										$term_exists = term_exists($term['slug'], $taxonomy);
										$term_id     = is_array($term_exists) ? $term_exists['term_id'] : $term_exists;
										if (!$term_id) {
											if (!empty($term['parent'])) {
												// see if we have imported this yet?
												$term['parent'] = $this->_imported_term_id($term['parent']);
											}
											$t = wp_insert_term($term['name'], $taxonomy, $term);
											if (!is_wp_error($t)) {
												$term_id = $t['term_id'];
											} else {
												// todo - error
												continue;
											}
										}
										$this->_imported_term_id($term['term_id'], $term_id);
										// add the term meta.
										if ($term_id && !empty($term['meta']) && is_array($term['meta'])) {
											foreach ($term['meta'] as $meta_key => $meta_val) {
											
												// we have to replace certain meta_key/meta_val
												// e.g. thumbnail id from woocommerce product categories.
												switch ($meta_key) {
													case 'thumbnail_id':
														if ($new_meta_val = $this->_imported_post_id($meta_val)) {
															// use this new id.
															$meta_val = $new_meta_val;
														}
														break;
													case '_cover':
											
														if ($new_meta_val = $this->_imported_post_id($meta_val[0])) {
														
															// use this new id.
															$meta_val = $new_meta_val;
														}
														break;
												}

												$meta_val      = maybe_unserialize($meta_val);
												if (is_array($meta_val)) {
												
													foreach ($meta_val as $_meta_key => $_meta_val) {
														
														update_term_meta($term_id, $meta_key, $_meta_val);
													}
												} else {
													update_term_meta($term_id, $meta_key, $meta_val);
												}
											}
										}
										$terms_to_set[$taxonomy][] = intval($term_id);
									}
								}
							}
							foreach ($terms_to_set as $tax => $ids) {
								wp_set_post_terms($post_id, $ids, $tax);
							}
						}

						// procses visual composer just to be sure.
						if (strpos($post_data['post_content'], '[vc_') !== false) {
							$this->vc_post($post_id);
						}
						if (!empty($post_data['meta']['_elementor_data']) || !!empty($post_data['meta']['_elementor_css'])) {
							$this->elementor_post($post_id);
						}
					}

					break;
			}

			return true;
		}

		private function _parse_gallery_shortcode_content($content)
		{
			// we have to format the post content. rewriting images and gallery stuff
			$replace      = $this->_imported_post_id();
			$urls_replace = array();
			foreach ($replace as $key => $val) {
				if ($key && $val && !is_numeric($key) && !is_numeric($val)) {
					$urls_replace[$key] = $val;
				}
			}
			if ($urls_replace) {
				uksort($urls_replace, array(&$this, 'cmpr_strlen'));
				foreach ($urls_replace as $from_url => $to_url) {
					$content = str_replace($from_url, $to_url, $content);
				}
			}
			if (preg_match_all('#\[gallery[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#ids="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'ids="' . $new_ids . '"', $content);
					}
				}
			}

			if (preg_match_all('#\[projects-grid[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#include_posts="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'include_posts="' . $new_ids . '"', $content);
					}
				}
			}
			if (preg_match_all('#\[before-after[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#before="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'before="' . $new_ids . '"', $content);
					}
				}
			}
			if (preg_match_all('#\[before-after[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#after="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'after="' . $new_ids . '"', $content);
					}
				}
			}
			if (preg_match_all('#\[logo-slider[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#images="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'images="' . $new_ids . '"', $content);
					}
				}
			}
			if (preg_match_all('#\[owl-slider[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#images="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'images="' . $new_ids . '"', $content);
					}
				}
			}
			if (preg_match_all('#\[shop-categories[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#ids="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'ids="' . $new_ids . '"', $content);
					}
				}
			}
			if (preg_match_all('#\[posts-carousel[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#categories="([^"]+)"#', $string, $ids_matches)) {
						$ids = explode(',', $ids_matches[1]);
						foreach ($ids as $key => $val) {
							$new_id = $val ? $this->_imported_post_id($val) : false;
							if (!$new_id) {
								unset($ids[$key]);
							} else {
								$ids[$key] = $new_id;
							}
						}
						$new_ids                   = implode(',', $ids);
						$content = str_replace($ids_matches[0], 'categories="' . $new_ids . '"', $content);
					}
				}
			}
			// contact form 7 id fixes.
			if (preg_match_all('#\[contact-form-7[^\]]*\]#', $content, $matches)) {
				foreach ($matches[0] as $match_id => $string) {
					if (preg_match('#id="(\d+)"#', $string, $id_match)) {
						$new_id = $this->_imported_post_id($id_match[1]);
						if ($new_id) {
							$content = str_replace($id_match[0], 'id="' . $new_id . '"', $content);
						} else {
							// no imported ID found. remove this entry.
							$content = str_replace($matches[0], '(insert contact form here)', $content);
						}
					}
				}
			}
			return $content;
		}

		private function _elementor_id_import(&$item, $key)
		{
			if ($key == 'id' && !empty($item) && is_numeric($item)) {
				// check if this has been imported before
				$new_meta_val = $this->_imported_post_id($item);
				if ($new_meta_val) {
					$item = $new_meta_val;
				}
			}
			if ($key == 'page' && !empty($item)) {

				if (false !== strpos($item, 'p.')) {
					$new_id = str_replace('p.', '', $item);
					// check if this has been imported before
					$new_meta_val = $this->_imported_post_id($new_id);
					if ($new_meta_val) {
						$item = 'p.' . $new_meta_val;
					}
				} else if (is_numeric($item)) {
					// check if this has been imported before
					$new_meta_val = $this->_imported_post_id($item);
					if ($new_meta_val) {
						$item = $new_meta_val;
					}
				}
			}
			if ($key == 'post_id' && !empty($item) && is_numeric($item)) {
				// check if this has been imported before
				$new_meta_val = $this->_imported_post_id($item);
				if ($new_meta_val) {
					$item = $new_meta_val;
				}
			}
			if ($key == 'url' && !empty($item) && strstr($item, 'ocalhost')) {
				// check if this has been imported before
				$new_meta_val = $this->_imported_post_id($item);
				if ($new_meta_val) {
					$item = $new_meta_val;
				}
			}
			if (($key == 'shortcode' || $key == 'editor') && !empty($item)) {
				// we have to fix the [contact-form-7 id=133] shortcode issue.
				$item = $this->_parse_gallery_shortcode_content($item);
			}
		}

		private function _content_install_type()
		{
			$post_type = !empty($_POST['content']) ? $_POST['content'] : false;
			$all_data  = $this->_get_json('default.json');
			if (!$post_type || !isset($all_data[$post_type])) {
				return false;
			}

			$batch_size = 6;
			$offset = isset($_REQUEST['retry_offset']) ? absint($_REQUEST['retry_offset']) : 0;
			if (!$offset && isset($_REQUEST['retry_count'])) {
				$offset = absint($_REQUEST['retry_count']);
			}

			$items = array_values($all_data[$post_type]);
			$total = count($items);
			$processed = 0;

			for ($index = $offset; $index < $total; $index++) {
				$post_data = $items[$index];
				$this->_process_post_data($post_type, $post_data);
				$processed++;

				if ($processed >= $batch_size && ($index + 1) < $total) {
					return array(
						'retry' => 1,
						'retry_count' => $index + 1,
						'retry_offset' => $index + 1,
					);
				}
			}

			$this->_handle_delayed_posts();

			$this->_handle_post_orphans();

			// now we have to handle any custom SQL queries. This is needed for the events manager to store location and event details.
			$sql = $this->_get_sql(basename($post_type) . '.sql');
			if ($sql) {
				global $wpdb;
				// do a find-replace with certain keys.
				if (preg_match_all('#__POSTID_(\d+)__#', $sql, $matches)) {
					foreach ($matches[0] as $match_id => $match) {
						$new_id = $this->_imported_post_id($matches[1][$match_id]);
						if (!$new_id) {
							$new_id = 0;
						}
						$sql = str_replace($match, $new_id, $sql);
					}
				}
				$sql  = str_replace('__DBPREFIX__', $wpdb->prefix, $sql);
				$bits = preg_split("/;(\s*\n|$)/", $sql);
				foreach ($bits as $bit) {
					$bit = trim($bit);
					if ($bit) {
						$wpdb->query($bit);
					}
				}
			}

			return true;
		}

		private function _handle_post_orphans()
		{
			$orphans = $this->_post_orphans();
			foreach ($orphans as $original_post_id => $original_post_parent_id) {
				if ($original_post_parent_id) {
					if ($this->_imported_post_id($original_post_id) && $this->_imported_post_id($original_post_parent_id)) {
						$post_data                = array();
						$post_data['ID']          = $this->_imported_post_id($original_post_id);
						$post_data['post_parent'] = $this->_imported_post_id($original_post_parent_id);
						wp_update_post($post_data);
						$this->_post_orphans($original_post_id, 0); // ignore future
					}
				}
			}
		}

		private function _handle_delayed_posts($last_delay = false)
		{

			$this->log(' ---- Processing ' . count($this->delay_posts, COUNT_RECURSIVE) . ' delayed posts');
			for ($x = 1; $x < 4; $x++) {
				foreach ($this->delay_posts as $delayed_post_type => $delayed_post_datas) {
					foreach ($delayed_post_datas as $delayed_post_id => $delayed_post_data) {
						if ($this->_imported_post_id($delayed_post_data['post_id'])) {
							$this->log($x . ' - Successfully processed ' . $delayed_post_type . ' ID ' . $delayed_post_data['post_id'] . ' previously.');
							unset($this->delay_posts[$delayed_post_type][$delayed_post_id]);
							$this->log(' ( ' . count($this->delay_posts, COUNT_RECURSIVE) . ' delayed posts remain ) ');
						} else if ($this->_process_post_data($delayed_post_type, $delayed_post_data, $last_delay)) {
							$this->log($x . ' - Successfully found delayed replacement for ' . $delayed_post_type . ' ID ' . $delayed_post_data['post_id'] . '.');
							// successfully inserted! don't try again.
							unset($this->delay_posts[$delayed_post_type][$delayed_post_id]);
							$this->log(' ( ' . count($this->delay_posts, COUNT_RECURSIVE) . ' delayed posts remain ) ');
						}
					}
				}
			}
		}

		private function _fetch_remote_file($url, $post)
		{
			// extract the file name and extension from the url
			$file_name  = basename($url);

			$local_file = trailingslashit(get_template_directory()) . 'envato_setup/images/' . $file_name;

			$upload     = false;
			if (is_file($local_file) && filesize($local_file) > 0) {
				error_log("ENVATO DEBUG: Found local file: $local_file (" . filesize($local_file) . " bytes)");
				require_once(ABSPATH . 'wp-admin/includes/file.php');
				WP_Filesystem();
				global $wp_filesystem;
				$file_data = $wp_filesystem->get_contents($local_file);
				$upload    = wp_upload_bits($file_name, 0, $file_data, $post['upload_date']);
				if ($upload['error']) {
					error_log("ENVATO DEBUG: wp_upload_bits FAILED for $file_name: " . $upload['error']);
					return new WP_Error('upload_dir_error', $upload['error']);
				} else {
					error_log("ENVATO DEBUG: wp_upload_bits SUCCESS for $file_name -> " . $upload['url']);
				}
			}

			if (!$upload || $upload['error']) {
				// get placeholder file in the upload dir with a unique, sanitized filename
				$upload = wp_upload_bits($file_name, 0, '', $post['upload_date']);
				if ($upload['error']) {
					return new WP_Error('upload_dir_error', $upload['error']);
				}

				// fetch the remote url and write it to the placeholder file
				//$headers = wp_get_http( $url, $upload['file'] );

				$max_size = (int) apply_filters('import_attachment_size_limit', 0);

				// we check if this file is uploaded locally in the source folder.
				$response = wp_remote_get($url);
				if (is_array($response) && !empty($response['body']) && $response['response']['code'] == '200') {
					require_once(ABSPATH . 'wp-admin/includes/file.php');
					$headers = $response['headers'];
					WP_Filesystem();
					global $wp_filesystem;
					$wp_filesystem->put_contents($upload['file'], $response['body']);
					//
				} else {
					// required to download file failed.
					/*$upload     = false;*/
					@unlink($upload['file']);

					return new WP_Error('import_file_error', esc_html__('Remote server did not respond', 'listeo'));
				}

				$filesize = filesize($upload['file']);

				if (isset($headers['content-length']) && $filesize != $headers['content-length']) {
					@unlink($upload['file']);

					return new WP_Error('import_file_error', esc_html__('Remote file is incorrect size', 'listeo'));
				}

				if (0 == $filesize) {
					@unlink($upload['file']);

					return new WP_Error('import_file_error', esc_html__('Zero size file downloaded', 'listeo'));
				}

				if (!empty($max_size) && $filesize > $max_size) {
					@unlink($upload['file']);

					return new WP_Error('import_file_error', sprintf(esc_html__('Remote file is too large, limit is %s', 'listeo'), size_format($max_size)));
				}
			}

			// keep track of the old and new urls so we can substitute them later
			$this->_imported_post_id($url, $upload['url']);
			$this->_imported_post_id($post['guid'], $upload['url']);
			// keep track of the destination if the remote url is redirected somewhere else
			if (isset($headers['x-final-location']) && $headers['x-final-location'] != $url) {
				$this->_imported_post_id($headers['x-final-location'], $upload['url']);
			}

			return $upload;
		}


		private function _content_install_widgets()
		{
			// todo: pump these out into the 'content/' folder along with the XML so it's a little nicer to play with
			$import_widget_positions = $this->_get_json('widget_positions.json');
			$import_widget_options   = $this->_get_json('widget_options.json');

			// importing.
			$widget_positions = get_option('sidebars_widgets');
			if (!is_array($widget_positions)) {
				$widget_positions = array();
			}

			foreach ($import_widget_options as $widget_name => $widget_options) {
				// replace certain elements with updated imported entries.
				foreach ($widget_options as $widget_option_id => $widget_option) {

					// replace TERM ids in widget settings.
					foreach (array('nav_menu') as $key_to_replace) {
						if (!empty($widget_option[$key_to_replace])) {
							// check if this one has been imported yet.
							$new_id = $this->_imported_term_id($widget_option[$key_to_replace]);
							if (!$new_id) {
								// do we really clear this out? nah. well. maybe.. hmm.
							} else {
								$widget_options[$widget_option_id][$key_to_replace] = $new_id;
							}
						}
					}
					// replace POST ids in widget settings.
					foreach (array('image_id', 'post_id') as $key_to_replace) {
						if (!empty($widget_option[$key_to_replace])) {
							// check if this one has been imported yet.
							$new_id = $this->_imported_post_id($widget_option[$key_to_replace]);
							if (!$new_id) {
								// do we really clear this out? nah. well. maybe.. hmm.
							} else {
								$widget_options[$widget_option_id][$key_to_replace] = $new_id;
							}
						}
					}
				}
				$existing_options = get_option('widget_' . $widget_name, array());
				if (!is_array($existing_options)) {
					$existing_options = array();
				}
				$new_options = $existing_options + $widget_options;
				update_option('widget_' . $widget_name, $new_options);
			}
			update_option('sidebars_widgets', array_merge($widget_positions, $import_widget_positions));

			return true;
		}

		public function _content_install_settings()
		{

			$this->_handle_delayed_posts(true); // final wrap up of delayed posts.
			//$this->vc_post(); // final wrap of vc posts.

			$custom_options = $this->_get_json('options.json');

			// we also want to update the widget area manager options.
			foreach ($custom_options as $option => $value) {
				// we have to update widget page numbers with imported page numbers.

				// if (
				// 	preg_match('#(wam__position_)(\d+)_#', $option, $matches) ||
				// 	preg_match('#(wam__area_)(\d+)_#', $option, $matches)
				// ) {
				// 	$new_page_id = $this->_imported_post_id($matches[2]);
				// 	if ($new_page_id) {
				// 		// we have a new page id for this one. import the new setting value.
				// 		$option = str_replace($matches[1] . $matches[2] . '_', $matches[1] . $new_page_id . '_', $option);
				// 	}
				// }
				// if ($value && !empty($value['custom_logo'])) {
				// 	$new_logo_id = $this->_imported_post_id($value['custom_logo']);
				// 	if ($new_logo_id) {
				// 		$value['custom_logo'] = $new_logo_id;
				// 	}
				// }
				if (in_array($option, array('pp_body_font','listeo_ad_campaigns_placement','listeo_side_social_icons', 'pp_footericons','listeo_listing_types', 'listeo_single_taxonomies_checkbox_list', 'listeo_listings_top_buttons_conf', 'listeo_home_slider', 'listeo_submit_classifieds_form_fields', 'listeo_submit_service_form_fields', 'listeo_submit_events_form_fields', 'listeo_submit_rental_form_fields'))) {
					$value      = (array) maybe_unserialize($value);
					
					$new_values = array();
					if (is_array($value)) {
						foreach ($value as $option => $id) {

							$new_id = $this->_imported_post_id($id);
							if ($new_id) {
								$new_values[$option] = $new_id;
							} else {
								$new_values[$option] = $id;
							}
						}
					}
					$value = $new_values;
				}
				update_option($option, $value);
			}

			$menu_ids = $this->_get_json('menu.json');
			$save     = array();
			foreach ($menu_ids as $menu_id => $term_id) {
				$new_term_id = $this->_imported_term_id($term_id);
				if ($new_term_id) {
					$save[$menu_id] = $new_term_id;
				}
			}

			// Fallback: if term ID mapping failed, try to find menus by slug
			if (empty($save)) {
				$nav_menus = wp_get_nav_menus();
				if (!empty($nav_menus)) {
					$slug_to_location = array(
						'main-menu' => 'primary',
					);
					foreach ($nav_menus as $nav_menu) {
						if (isset($slug_to_location[$nav_menu->slug])) {
							$location = $slug_to_location[$nav_menu->slug];
							$save[$location] = $nav_menu->term_id;
							$this->log('Menu fallback: assigned "' . $nav_menu->name . '" (slug: ' . $nav_menu->slug . ') to location "' . $location . '"');
						}
					}
				}
			}

			if ($save) {
				set_theme_mod('nav_menu_locations', array_map('absint', $save));
			}
			/*realteo_pages":{"my_account_page":"246","bookmarks_page":"253","my_properties_page":"258","submit_property_page":"260","change_password_page":"263","property_packages_page":"507"}*/
			// $my_account_page 		= $this->get_page_by_title('My Profile');
			// $bookmarks_page 		= $this->get_page_by_title('Bookmarks');
			// $my_properties_page 	= $this->get_page_by_title('My Properties');
			// $compare_page 			= $this->get_page_by_title('Compare Properties');
			// $submit_property_page 	= $this->get_page_by_title('Submit Property');
			// $change_password_page 	= $this->get_page_by_title('Change Password');
			// $property_packages_page = $this->get_page_by_title('My Packages');
			// $lost_password_page 	= $this->get_page_by_title('Lost Password');
			// $reset_password_page 	= $this->get_page_by_title('Reset Password');
			// $realteo_pages =
			// 	array(
			// 		'my_account_page' 			=> $my_account_page->ID,
			// 		'bookmarks_page' 			=> $bookmarks_page->ID,
			// 		'my_properties_page' 		=> $my_properties_page->ID,
			// 		'compare_page' 				=> $compare_page->ID,
			// 		'submit_property_page' 		=> $submit_property_page->ID,
			// 		'change_password_page' 		=> $change_password_page->ID,
			// 		'property_packages_page' 	=> $property_packages_page->ID,
			// 		'lost_password_page' 		=> $property_packages_page->ID,
			// 		'reset_password_page' 		=> $reset_password_page->ID,
			// 	);



			update_option('listeo_page_builder', 'elementor');
			update_option('listeo_iconsmind', 'hide');
			// set the blog page and the home page.
			$shoppage = $this->get_page_by_title('Shop');
			if ($shoppage) {
				update_option('woocommerce_shop_page_id', $shoppage->ID);
			}
			$shoppage = $this->get_page_by_title('Cart');
			if ($shoppage) {
				update_option('woocommerce_cart_page_id', $shoppage->ID);
			}
			$shoppage = $this->get_page_by_title('Checkout');
			if ($shoppage) {
				update_option('woocommerce_checkout_page_id', $shoppage->ID);
			}
			$shoppage = $this->get_page_by_title('My Account');
			if ($shoppage) {
				update_option('woocommerce_myaccount_page_id', $shoppage->ID);
			}
			$homepage = $this->get_page_by_title('Home 1');
			if ($homepage) {
				update_option('page_on_front', $homepage->ID);
				update_option('show_on_front', 'page');
			}
			$dashboardpage = $this->get_page_by_title('Dashboard');
			if ($dashboardpage) {
				update_option('listeo_dashboard_page', $dashboardpage->ID);
			}
			$listeo_messages_page = $this->get_page_by_title('Messages');
			if ($listeo_messages_page) {
				update_option('listeo_messages_page', $listeo_messages_page->ID);
			}
			$listeo_bookings_page = $this->get_page_by_title('Bookings');
			if ($listeo_bookings_page) {
				update_option('listeo_bookings_page', $listeo_bookings_page->ID);
			}
			$listeo_bookings_calendar_page = $this->get_page_by_title('Calendar View');
			if ($listeo_bookings_calendar_page) {
				update_option('listeo_bookings_calendar_page', $listeo_bookings_calendar_page->ID);
			}
			$listeo_user_bookings_page = $this->get_page_by_title('My Bookings');
			if ($listeo_user_bookings_page) {
				update_option('listeo_user_bookings_page', $listeo_user_bookings_page->ID);
			}
			$listeo_booking_confirmation_page = $this->get_page_by_title('Booking Confirmation');
			if ($listeo_booking_confirmation_page) {
				update_option('listeo_booking_confirmation_page', $listeo_booking_confirmation_page->ID);
			}

			$listeo_listings_page = $this->get_page_by_title('My Listings');
			if ($listeo_listings_page) {
				update_option('listeo_listings_page', $listeo_listings_page->ID);
			}

			$listeo_ads_page = $this->get_page_by_title('Ad Manager');
			if ($listeo_ads_page) {
				update_option('listeo_ad_campaigns_page', $listeo_ads_page->ID);
			}

			$ticket_check_page = $this->get_page_by_title('QR Scan');
			if ($ticket_check_page) {
				update_option('listeo_ticket_check_page', $ticket_check_page->ID);
			}
			$listeo_statistics_page = $this->get_page_by_title('Statistics');
			if ($listeo_statistics_page) {
				update_option('listeo_stats_page', $listeo_statistics_page->ID);
			}
			$listeo_wallet_page = $this->get_page_by_title('Wallet');
			if ($listeo_wallet_page) {
				update_option('listeo_wallet_page', $listeo_wallet_page->ID);
			}
			$listeo_alerts_page = $this->get_page_by_title('Search Alerts');
			if ($listeo_alerts_page) {
				update_option('listeo_saved_searches_page', $listeo_alerts_page->ID);
			}
			$listeo_coupon_page = $this->get_page_by_title('Coupons');
			if ($listeo_coupon_page) {
				update_option('listeo_coupons_page', $listeo_coupon_page->ID);
			}
			$listeo_reviews_page = $this->get_page_by_title('Reviews');
			if ($listeo_reviews_page) {
				update_option('listeo_reviews_page', $listeo_reviews_page->ID);
			}

			$listeo_bookmarks_page = $this->get_page_by_title('Bookmarks');
			if ($listeo_bookmarks_page) {
				update_option('listeo_bookmarks_page', $listeo_bookmarks_page->ID);
			}

			$listeo_submit_page = $this->get_page_by_title('Add Listing');
			if ($listeo_submit_page) {
				update_option('listeo_submit_page', $listeo_submit_page->ID);
			}

			$listeo_profile_page = $this->get_page_by_title('My Profile');
			if ($listeo_profile_page) {
				update_option('listeo_profile_page', $listeo_profile_page->ID);
			}
			$listeo_claim_page = $this->get_page_by_title('Claim Listing');
			if ($listeo_claim_page) {
				update_option('listeo_claim_page', $listeo_claim_page->ID);
			}

			// lost password page
			$listeo_lost_password_page = $this->get_page_by_title('Lost Password');
			if ($listeo_lost_password_page) {
				update_option('listeo_lost_password_page', $listeo_lost_password_page->ID);
			}
			//reset password
			$listeo_reset_password_page = $this->get_page_by_title('Reset Password');
			if ($listeo_reset_password_page) {
				update_option('listeo_reset_password_page', $listeo_reset_password_page->ID);
			}
			$listeo_ical_page = $this->get_page_by_title('iCal');
			if ($listeo_ical_page) {
				update_option('listeo_ical_page', $listeo_ical_page->ID);
			}

			$logo2 = $this->get_attachment_url_by_title('logo2');

			update_option('pp_dashboard_logo_upload', $logo2);

			$logo3 = $this->get_attachment_url_by_title('logo');
			update_option('pp_sticky_logo_upload', $logo3);
			update_option('pp_logo_upload', $logo3);

			update_option('elementor_experiment-e_dom_optimization', 'inactive');

			$home_banner = $this->get_attachment_url_by_title('main-search-background-01');

			update_option('listeo_search_bg', $home_banner);

			$dokan_store_listing = $this->get_page_by_title('Store List');
			$dokan_my_orders = $this->get_page_by_title('My Orders');
			$dokan_dashboard = $this->get_page_by_title('Store Panel');
			$dokanpages = array();
			if ($dokan_store_listing) {
				$dokanpages['store_listing']  = $dokan_store_listing->ID;
				$dokanpages['my_orders']  = $dokan_my_orders->ID;
				$dokanpages['dashboard']  = $dokan_dashboard->ID;
				$dokanpages['reg_tc_page']  = '';
			}
			update_option('pp_body_font', array(
				'font-family' => 'Raleway',
			));
			update_option('listeo_side_social_icons', array(
				0 =>
				array(
					'icons_service' => 'twitter',
					'icons_url' => '#',
				),
				1 =>
				array(
					'icons_service' => 'linkedin',
					'icons_url' => '#',
				),
				2 =>
				array(
					'icons_service' => 'facebook-messenger',
					'icons_url' => '#',
				),
				3 =>
				array(
					'icons_service' => 'instagram',
					'icons_url' => '#',
				),
			));
			update_option('pp_footericons', array(
				0 =>
				array(
					'icons_service' => 'twitter',
					'icons_url' => '#',
				),
				1 =>
				array(
					'icons_service' => 'linkedin',
					'icons_url' => '#',
				),
				2 =>
				array(
					'icons_service' => 'facebook-messenger',
					'icons_url' => '#',
				),
				3 =>
				array(
					'icons_service' => 'instagram',
					'icons_url' => '#',
				),
			));
			update_option('dokan_pages', $dokanpages);
			//update_option('dokan_pages_created', 1);
			update_option('listeo_stats_type', array('unique', 'booking_click'));
			update_option('listeo_listings_sortby_options', array('highest-rated', 'reviewed', 'date-desc', 'date-asc', 'title', 'featured', 'views', 'verified', 'upcoming-event', 'rand'));
			// global $wp_rewrite;
			// $wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
			// update_option('rewrite_rules', false);
			// $wp_rewrite->flush_rules(true);

			return true;
		}
		public function get_page_by_title($title)
		{
			$query = new WP_Query(
				array(
					'post_type'              => 'page',
					'title'                  => $title,
					'post_status'            => 'all',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
					'orderby'                => 'post_date ID',
					'order'                  => 'ASC',
				)
			);

			if (!empty($query->post)) {
				$page_got_by_title = $query->post;
			} else {
				$page_got_by_title = null;
			}
			return $page_got_by_title;
		}
			public function _get_json($file)
			{
				$file = basename($file);
				$package = $this->listeo_require_demo_package();

				if (is_wp_error($package)) {
					$this->listeo_record_demo_package_error($package->get_error_message());
					return array();
				}

				if (isset($package[$file]) && is_array($package[$file])) {
					return $package[$file];
				}

				$this->listeo_record_demo_package_error(
					sprintf(
						/* translators: %s: demo package file name */
						__('The demo import package does not include %s.', 'listeo'),
						$file
					)
				);

				return array();
			}

		private function _get_sql($file)
		{
			if (is_file(__DIR__ . '/content/' . basename($file))) {
				WP_Filesystem();
				global $wp_filesystem;
				$file_name = __DIR__ . '/content/' . basename($file);
				if (file_exists($file_name)) {
					return $wp_filesystem->get_contents($file_name);
				}
			}

			return false;
		}


		public $logs = array();

		public function log($message)
		{
			$this->logs[] = $message;
		}

		public $errors = array();

		public function error($message)
		{
			$this->logs[] = 'ERROR!!!! ' . $message;
		}

		public function envato_setup_color_style()
		{

		?>
			<h1><?php esc_html_e('Site Style', 'listeo'); ?></h1>
			<form method="post">
				<p><?php esc_html_e('Please choose your site style below.', 'listeo'); ?></p>



				<input type="hidden" name="new_style" id="new_style" value="">

				<p><em>Please Note: Advanced changes to website graphics/colors may require extensive PhotoShop and Web
						Development knowledge. We recommend hiring an expert from <a href="http://studiotracking.envato.com/aff_c?offer_id=4&aff_id=1564&source=DemoInstall" target="_blank">Envato Studio</a> to assist with any advanced website changes.</em></p>
				<div style="display: none;">
					<img src="http://studiotracking.envato.com/aff_i?offer_id=4&aff_id=1564&source=DemoInstall" width="1" height="1" />
				</div>

				<p class="envato-setup-actions step">
					<input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e('Continue', 'listeo'); ?>" name="save_step" />
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'listeo'); ?></a>
					<?php wp_nonce_field('envato-setup'); ?>
				</p>
			</form>
		<?php
		}

		/**
		 * Save logo & design options
		 */
		public function envato_setup_color_style_save()
		{
			check_admin_referer('envato-setup');

			$new_style = isset($_POST['new_style']) ? $_POST['new_style'] : false;
			if ($new_style) {
				set_theme_mod('dtbwp_site_style', $new_style);
			}

			wp_redirect(esc_url_raw($this->get_next_step_link()));
			exit;
		}


		/**
		 * Logo & Design
		 */
		public function envato_setup_logo_design()
		{

		?>
			<h1><?php esc_html_e('Logo', 'listeo'); ?></h1>
			<form method="post">
				<p><?php printf(esc_html__('Please add your logo below. For best results, the logo should be a transparent PNG . The logo can be changed at any time from the Appearance > Customize area in your dashboard. Try %sEnvato Studio%s if you need a new logo designed.', 'listeo'), '<a href="http://studiotracking.envato.com/aff_c?offer_id=4&aff_id=1564&source=DemoInstall" target="_blank">', '</a>'); ?></p>

				<table>
					<tr>
						<td>
							<div id="current-logo" style="background: #ddd; padding:10px;">
								<?php
								$image_url = $this->get_logo_image2();
								if ($image_url) {
									$image = '<img class="site-logo" src="%s" alt="%s" style="width:%s; height:auto" />';
									printf(
										$image,
										$image_url,
										get_bloginfo('name'),
										$this->get_header_logo_width()
									);
								} ?>
							</div>
						</td>
						<td>
							<a href="#" class="button button-upload"><?php esc_html_e('Upload New Logo', 'listeo'); ?></a>
						</td>
					</tr>
				</table>

				<p><em>Please Note: Advanced changes to website graphics/colors may require extensive PhotoShop and Web
						Development knowledge. We recommend hiring an expert from <a href="http://studiotracking.envato.com/aff_c?offer_id=4&aff_id=1564&source=DemoInstall" target="_blank">Envato Studio</a> to assist with any advanced website changes.</em></p>
				<div style="display: none;">
					<img src="http://studiotracking.envato.com/aff_i?offer_id=4&aff_id=1564&source=DemoInstall" width="1" height="1" />
				</div>

				<input type="hidden" name="new_logo_id" id="new_logo_id" value="">

				<p class="envato-setup-actions step">
					<input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e('Continue', 'listeo'); ?>" name="save_step" />
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'listeo'); ?></a>
					<?php wp_nonce_field('envato-setup'); ?>
				</p>
			</form>
		<?php
		}

		/**
		 * Save logo & design options
		 */
		public function envato_setup_logo_design_save()
		{
			check_admin_referer('envato-setup');

			$new_logo_id = (int) $_POST['new_logo_id'];
			// save this new logo url into the database and calculate the desired height based off the logo width.
			// copied from dtbaker.theme_options.php
			if ($new_logo_id) {
				$attr = wp_get_attachment_image_src($new_logo_id, 'full');
				if ($attr && !empty($attr[1]) && !empty($attr[2])) {

					set_theme_mod('custom_logo', $new_logo_id);
					set_theme_mod('header_textcolor', 'blank');
					set_theme_mod('logo_header_image', $attr[0]);
					// we have a width and height for this image. awesome.
					$logo_width  = (int) get_theme_mod('logo_header_image_width', '467');
					$scale       = $logo_width / $attr[1];
					$logo_height = intval($attr[2] * $scale);
					if ($logo_height > 0) {
						set_theme_mod('logo_header_image_height', $logo_height);
					}
				}
			}

			$new_style = isset($_POST['new_site_color']) ? $_POST['new_site_color'] : false;
			if ($new_style) {
				$demo_styles = apply_filters('dtbwp_default_styles', array());
				if (isset($demo_styles[$new_style])) {
					set_theme_mod('dtbwp_site_color', $new_style);
					if (class_exists('dtbwp_customize_save_hook')) {
						$site_color_defaults = new dtbwp_customize_save_hook();
						$site_color_defaults->save_color_options($new_style);
					}
				}
			}

			wp_redirect(esc_url_raw($this->get_next_step_link()));
			exit;
		}

		/**
		 * Payments Step
		 */
		public function envato_setup_updates()
		{
		?>
			<h1><?php esc_html_e('Theme Updates', 'listeo'); ?></h1>
			<?php if (function_exists('envato_market')) { ?>
				<form method="post">
					<?php
					$option = envato_market()->get_options();

					$my_items = array();
					if ($option && !empty($option['items'])) {
						foreach ($option['items'] as $item) {
							if (!empty($item['oauth']) && !empty($item['token_data']['expires']) && $item['oauth'] == $this->envato_username && $item['token_data']['expires'] >= time()) {
								// token exists and is active
								$my_items[] = $item;
							}
						}
					}
					if (count($my_items)) {
					?>
						<p>Thanks! Theme updates have been enabled for the following items: </p>
						<ul>
							<?php foreach ($my_items as $item) { ?>
								<li><?php echo esc_html($item['name']); ?></li>
							<?php } ?>
						</ul>
						<p>When an update becomes available it will show in the Dashboard with an option to install.</p>
						<p>Change settings from the 'Envato Market' menu in the WordPress Dashboard.</p>

						<p class="envato-setup-actions step">
							<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next button-primary"><?php esc_html_e('Continue', 'listeo'); ?></a>
						</p>
					<?php
					} else {
					?>
						<p><?php esc_html_e('Please login using your ThemeForest account to enable Theme Updates. We update themes when a new feature is added or a bug is fixed. It is highly recommended to enable Theme Updates.', 'listeo'); ?></p>
						<p>When an update becomes available it will show in the Dashboard with an option to install.</p>
						<p>
							<em>On the next page you will be asked to Login with your ThemeForest account and grant
								permissions to enable Automatic Updates. If you have any questions please <a href="http://dtbaker.net/envato/" target="_blank">contact us</a>.</em>
						</p>
						<p class="envato-setup-actions step">
							<input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e('Login with Envato', 'listeo'); ?>" name="save_step" />
							<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'listeo'); ?></a>
							<?php wp_nonce_field('envato-setup'); ?>
						</p>
					<?php } ?>
				</form>
			<?php } else { ?>
				Please ensure the Envato Market plugin has been installed correctly. <a href="<?php echo esc_url($this->get_step_link('default_plugins')); ?>">Return to Required
					Plugins installer</a>.
			<?php } ?>
		<?php
		}

		/**
		 * Payments Step save
		 */
		public function envato_setup_updates_save()
		{
			check_admin_referer('envato-setup');

			// redirect to our custom login URL to get a copy of this token.
			$url = $this->get_oauth_login_url($this->get_step_link('updates'));

			wp_redirect(esc_url_raw($url));
			exit;
		}


		public function envato_setup_customize() {
			?>
				<h1>Theme Customization</h1>
				<p>
					You can customize your site under <strong>Appearance → Customize</strong> – for logo, colors, background, layout, and more.
				</p>
			
				<p>
					<strong>Need help?</strong> See our <a href="https://docs.purethemes.net/listeo/knowledge-base-category/getting-started/" target="_blank">Getting Started Docs</a>  
					for guides on <a href="https://docs.purethemes.net/listeo/knowledge-base/theme-installation/" target="_blank">installation</a>,  
					<a href="https://docs.purethemes.net/listeo/knowledge-base/demo-content-import/" target="_blank">demo import</a>,  
					<a href="https://docs.purethemes.net/listeo/knowledge-base/theme-translation/" target="_blank">translation</a>,  
					<a href="https://docs.purethemes.net/listeo/knowledge-base/how-booking-works-in-listeo/" target="_blank">booking setup</a>,  
					and <a href="https://docs.purethemes.net/listeo/knowledge-base/setting-up-woocommerce-payment-gateways/" target="_blank">payments</a>.
				</p>
				<p>
					<a href="https://docs.purethemes.net/listeo/ai-assistant/"><img style="    width: 100%;" src="https://purethemes.net/images/ai.png"></a>
					<a href="https://www.facebook.com/groups/856478084819791/"><img style="    width: 100%;" src="https://purethemes.net/images/fb.png"></a>
				</p>
			
				<p>
					<em>Making code changes? Use a 
					<a href="https://codex.wordpress.org/Child_Themes" target="_blank">Child Theme</a> to avoid losing changes during updates.  
					Find <code>listeo-child.zip</code> in the “All files & documentation” download on ThemeForest.</em>
				</p>
			
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-primary button-large button-next">
						<?php esc_html_e('Continue', 'listeo'); ?>
					</a>
				</p>
			<?php
			}
			

		public function envato_setup_help_support()
		{
			if (class_exists('\Elementor\Plugin') && !empty(\Elementor\Plugin::$instance)) {

				\Elementor\Plugin::$instance->files_manager->clear_cache();
				//	\Elementor\Plugin::$instance->kits_manager->create_default();

			}

		?>
<h1>Help & Support</h1>

<p style="    background: #f4f4f4;
    padding: 10px 16px;
    border-radius: 5px;
    line-height: 24px;">
	Each license is valid for one website only.  
	To use this theme on another site, please purchase an additional license.
</p>

<p>
	<?php esc_html_e('You can access support through PureThemes support documentation.', 'listeo'); ?>
</p>
<p>
	<a href="https://docs.purethemes.net/listeo/support/" target="_blank" rel="noopener" class="button button-primary button-large listeo-support-button"><svg class="listeo-support-button-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg><?php esc_html_e('PureThemes Support', 'listeo'); ?></a>
</p>
<p><?php esc_html_e('Support includes:', 'listeo'); ?></p>

<ul class="listeo-support-list listeo-support-list-included">
	<li><span class="listeo-support-icon listeo-support-icon-success" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M3.5 8.2 6.6 11.3 12.7 4.8"></path></svg></span><span><?php esc_html_e('Author availability to answer questions', 'listeo'); ?></span></li>
	<li><span class="listeo-support-icon listeo-support-icon-success" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M3.5 8.2 6.6 11.3 12.7 4.8"></path></svg></span><span><?php esc_html_e('Technical help with theme features', 'listeo'); ?></span></li>
	<li><span class="listeo-support-icon listeo-support-icon-success" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M3.5 8.2 6.6 11.3 12.7 4.8"></path></svg></span><span><?php esc_html_e('Bug fixes and issue reporting', 'listeo'); ?></span></li>
	<li><span class="listeo-support-icon listeo-support-icon-success" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M3.5 8.2 6.6 11.3 12.7 4.8"></path></svg></span><span><?php esc_html_e('Help with bundled third-party plugins', 'listeo'); ?></span></li>
</ul>

<p>
	<strong><?php esc_html_e('Support does not include:', 'listeo'); ?></strong>
</p>
<ul class="listeo-support-list listeo-support-list-excluded">
	<li><span class="listeo-support-icon listeo-support-icon-error" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M4.5 4.5 11.5 11.5"></path><path d="M11.5 4.5 4.5 11.5"></path></svg></span><span><?php esc_html_e('Customization services', 'listeo'); ?> (<a href="https://codeable.io/?ref=MzT0b" target="_blank"><?php esc_html_e('available via Codeable', 'listeo'); ?></a>)</span></li>
	<li><span class="listeo-support-icon listeo-support-icon-error" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M4.5 4.5 11.5 11.5"></path><path d="M11.5 4.5 4.5 11.5"></path></svg></span><span><?php esc_html_e('Help with third-party plugins you add yourself', 'listeo'); ?></span></li>
</ul>

<p class="envato-setup-actions step">
	<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-primary button-large button-next">
		<?php esc_html_e('Continue', 'listeo'); ?>
	</a>
	<?php wp_nonce_field('envato-setup'); ?>
</p>

		<?php
		}

		public function envato_setup_multi_vendor() { ?>
			<h1>Multi-Vendor Marketplace</h1>
		
			<p>
				You're almost ready — just one last thing.
			</p>
		
			<p>
				Do you want to enable the <strong>multi-vendor marketplace</strong> feature?  
				This allows users to sell their own products or services through your site.
			</p>
		
			<p>
				<a target="_blank" href="https://docs.purethemes.net/listeo/knowledge-base/do-i-need-dokan-multi-vendor-marketplace-feature/" target="_blank">
					Do I need Dokan & the multi-vendor feature?
				</a>
			</p>
		
			<p><strong>Note:</strong> Dokan is <strong><u>not required</u></strong> for the listings or booking features in Listeo.  
			You can skip this step if you don’t plan to run a marketplace.</p>
		
			<img src="<?php echo get_template_directory_uri() . '/envato_setup/css/dokan.jpeg'; ?>" alt="Dokan Integration" width="420" style="margin: 0 auto 40px; display: block;">
		
			<p class="envato-setup-actions step">
				<a href="<?php echo self_admin_url('plugin-install.php?tab=plugin-information&plugin=dokan-lite&TB_iframe=true&width=772&height=459'); ?>" class="button button-primary button-large button-next">
					Yes, install Dokan
				</a>
				<a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-secondary button-large button-next">
					Skip this step
				</a>
				<?php wp_nonce_field('envato-setup'); ?>
			</p>
		<?php }
		
		/**
		 * Final step
		 */
		public function envato_setup_ready()
		{

			update_option('envato_setup_complete', time());
			update_option('dtbwp_update_notice', strtotime('-4 days'));
		?>
			<script>
				! function(d, s, id) {
					var js, fjs = d.getElementsByTagName(s)[0];
					if (!d.getElementById(id)) {
						js = d.createElement(s);
						js.id = id;
						js.src = "//platform.twitter.com/widgets.js";
						fjs.parentNode.insertBefore(js, fjs);
					}
				}(document, "script", "twitter-wjs");
			</script>
			<h1><?php esc_html_e('Your Website is Ready! 🚀', 'listeo'); ?></h1>
				<p>
					You can customize your site under <strong>Appearance → Customize</strong> – for logo, colors, background, layout, and more.
				</p>
			
				<p>
					<strong>Need help?</strong> See our <a href="https://docs.purethemes.net/listeo/knowledge-base-category/getting-started/" target="_blank">Getting Started Docs</a>  
					for guides on <a href="https://docs.purethemes.net/listeo/knowledge-base/theme-installation/" target="_blank">installation</a>,  
					<a href="https://docs.purethemes.net/listeo/knowledge-base/demo-content-import/" target="_blank">demo import</a>,  
					<a href="https://docs.purethemes.net/listeo/knowledge-base/theme-translation/" target="_blank">translation</a>,  
					<a href="https://docs.purethemes.net/listeo/knowledge-base/how-booking-works-in-listeo/" target="_blank">booking setup</a>,  
					and <a href="https://docs.purethemes.net/listeo/knowledge-base/setting-up-woocommerce-payment-gateways/" target="_blank">payments</a>.
				</p>
				<p>
					<a href="https://docs.purethemes.net/listeo/ai-assistant/"><img style="    width: 100%;" src="https://purethemes.net/images/ai.png"></a>
					<a href="https://www.facebook.com/groups/856478084819791/"><img style="    width: 100%;" src="https://purethemes.net/images/fb.png"></a>
				</p>
			
				<p>
					<em>Making code changes? Use a 
					<a href="https://codex.wordpress.org/Child_Themes" target="_blank">Child Theme</a> to avoid losing changes during updates.  
					Find <code>listeo-child.zip</code> in the “All files & documentation” download on ThemeForest.</em>
				</p>
			
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button button-primary button-large">
						<?php esc_html_e('Finish', 'listeo'); ?>
					</a>
				</p>


	</ul>
</div>

			<?php
		}

		function get_attachment_url_by_title($title)
		{
			global $wpdb;

			$attachments = $wpdb->get_results($wpdb->prepare("SELECT guid FROM $wpdb->posts WHERE post_title = %s AND post_type = 'attachment'", $title), OBJECT);
			if ($attachments) {

				$attachment_url = $attachments[0]->guid;
			} else {
				return 'image-not-found';
			}

			return $attachment_url;
		}

		public function envato_market_admin_init()
		{

			if (!function_exists('envato_market')) {
				return;
			}

			global $wp_settings_sections;
			if (!isset($wp_settings_sections[envato_market()->get_slug()])) {
				// means we're running the admin_init hook before envato market gets to setup settings area.
				// good - this means our oauth prompt will appear first in the list of settings blocks
				register_setting(envato_market()->get_slug(), envato_market()->get_option_name());
			}

			// pull our custom options across to envato.
			$option         = get_option('envato_setup_wizard', array());
			$envato_options = envato_market()->get_options();
			$envato_options = $this->_array_merge_recursive_distinct($envato_options, $option);
			if (!empty($envato_options['items'])) {
				foreach ($envato_options['items'] as $key => $item) {
					if (!empty($item['id']) && is_string($item['id'])) {
						$envato_options['items'][$key]['id'] = (int)$item['id'];
					}
				}
			}
			update_option(envato_market()->get_option_name(), $envato_options);

			//add_thickbox();

			if (!empty($_POST['oauth_session']) && !empty($_POST['bounce_nonce']) && wp_verify_nonce($_POST['bounce_nonce'], 'envato_oauth_bounce_' . $this->envato_username)) {
				// request the token from our bounce url.
				$my_theme    = wp_get_theme();
				$oauth_nonce = get_option('envato_oauth_' . $this->envato_username);
				if (!$oauth_nonce) {
					// this is our 'private key' that is used to request a token from our api bounce server.
					// only hosts with this key are allowed to request a token and a refresh token
					// the first time this key is used, it is set and locked on the server.
					$oauth_nonce = wp_create_nonce('envato_oauth_nonce_' . $this->envato_username);
					update_option('envato_oauth_' . $this->envato_username, $oauth_nonce);
				}
				$response = wp_remote_post(
					$this->oauth_script,
					array(
						'method'      => 'POST',
						'timeout'     => 15,
						'redirection' => 1,
						'httpversion' => '1.0',
						'blocking'    => true,
						'headers'     => array(),
						'body'        => array(
							'oauth_session' => $_POST['oauth_session'],
							'oauth_nonce'   => $oauth_nonce,
							'get_token'     => 'yes',
							'url'           => home_url(),
							'theme'         => $my_theme->get('Name'),
							'version'       => $my_theme->get('Version'),
						),
						'cookies'     => array(),
					)
				);
				if (is_wp_error($response)) {
					$error_message = $response->get_error_message();
					$class         = 'error';
					echo "<div class=\"$class\"><p>" . sprintf(esc_html__('Something went wrong while trying to retrieve oauth token: %s', 'listeo'), $error_message) . '</p></div>';
				} else {
					$token  = @json_decode(wp_remote_retrieve_body($response), true);
					$result = false;
					if (is_array($token) && !empty($token['access_token'])) {
						$token['oauth_session'] = $_POST['oauth_session'];
						$result                 = $this->_manage_oauth_token($token);
					}
					if ($result !== true) {
						echo 'Failed to get oAuth token. Please go back and try again';
						exit;
					}
				}
			}

			add_settings_section(
				envato_market()->get_option_name() . '_' . $this->envato_username . '_oauth_login',
				sprintf(esc_html__('Login for %s updates', 'listeo'), $this->envato_username),
				array($this, 'render_oauth_login_description_callback'),
				envato_market()->get_slug()
			);
			// Items setting.
			add_settings_field(
				$this->envato_username . 'oauth_keys',
				esc_html__('oAuth Login', 'listeo'),
				array($this, 'render_oauth_login_fields_callback'),
				envato_market()->get_slug(),
				envato_market()->get_option_name() . '_' . $this->envato_username . '_oauth_login'
			);
		}

		private static $_current_manage_token = false;

		private function _manage_oauth_token($token)
		{
			if (is_array($token) && !empty($token['access_token'])) {
				if (self::$_current_manage_token == $token['access_token']) {
					return false; // stop loops when refresh auth fails.
				}
				self::$_current_manage_token = $token['access_token'];
				// yes! we have an access token. store this in our options so we can get a list of items using it.
				$option = get_option('envato_setup_wizard', array());
				if (!is_array($option)) {
					$option = array();
				}
				if (empty($option['items'])) {
					$option['items'] = array();
				}
				// check if token is expired.
				if (empty($token['expires'])) {
					$token['expires'] = time() + 3600;
				}
				if ($token['expires'] < time() + 120 && !empty($token['oauth_session'])) {
					// time to renew this token!
					$my_theme    = wp_get_theme();
					$oauth_nonce = get_option('envato_oauth_' . $this->envato_username);
					$response    = wp_remote_post(
						$this->oauth_script,
						array(
							'method'      => 'POST',
							'timeout'     => 10,
							'redirection' => 1,
							'httpversion' => '1.0',
							'blocking'    => true,
							'headers'     => array(),
							'body'        => array(
								'oauth_session' => $token['oauth_session'],
								'oauth_nonce'   => $oauth_nonce,
								'refresh_token' => 'yes',
								'url'           => home_url(),
								'theme'         => $my_theme->get('Name'),
								'version'       => $my_theme->get('Version'),
							),
							'cookies'     => array(),
						)
					);
					if (is_wp_error($response)) {
						$error_message = $response->get_error_message();
						// we clear any stored tokens which prompts the user to re-auth with the update server.
						$this->_clear_oauth();
					} else {
						$new_token = @json_decode(wp_remote_retrieve_body($response), true);
						$result    = false;
						if (is_array($new_token) && !empty($new_token['new_token'])) {
							$token['access_token'] = $new_token['new_token'];
							$token['expires']      = time() + 3600;
						} else {
							//refresh failed, we clear any stored tokens which prompts the user to re-register.
							$this->_clear_oauth();
						}
					}
				}
				// use this token to get a list of purchased items
				// add this to our items array.
				$response                    = envato_market()->api()->request('https://api.envato.com/v3/market/buyer/purchases', array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token['access_token'],
					),
				));
				self::$_current_manage_token = false;
				if (is_array($response) && is_array($response['purchases'])) {
					// up to here, add to items array
					foreach ($response['purchases'] as $purchase) {
						// check if this item already exists in the items array.
						$exists = false;
						foreach ($option['items'] as $id => $item) {
							if (!empty($item['id']) && $item['id'] == $purchase['item']['id']) {
								$exists = true;
								// update token.
								$option['items'][$id]['token']      = $token['access_token'];
								$option['items'][$id]['token_data'] = $token;
								$option['items'][$id]['oauth']      = $this->envato_username;
								if (!empty($purchase['code'])) {
									$option['items'][$id]['purchase_code'] = $purchase['code'];
								}
							}
						}
						if (!$exists) {
							$option['items'][] = array(
								'id'            => '' . $purchase['item']['id'],
								// item id needs to be a string for market download to work correctly.
								'name'          => $purchase['item']['name'],
								'token'         => $token['access_token'],
								'token_data'    => $token,
								'oauth'         => $this->envato_username,
								'type'          => !empty($purchase['item']['wordpress_theme_metadata']) ? 'theme' : 'plugin',
								'purchase_code' => !empty($purchase['code']) ? $purchase['code'] : '',
							);
						}
					}
				} else {
					return false;
				}
				if (!isset($option['oauth'])) {
					$option['oauth'] = array();
				}
				// store our 1 hour long token here. we can refresh this token when it comes time to use it again (i.e. during an update)
				$option['oauth'][$this->envato_username] = $token;
				update_option('envato_setup_wizard', $option);

				$envato_options = envato_market()->get_options();
				$envato_options = $this->_array_merge_recursive_distinct($envato_options, $option);
				update_option(envato_market()->get_option_name(), $envato_options);
				envato_market()->items()->set_themes(true);
				envato_market()->items()->set_plugins(true);

				return true;
			} else {
				return false;
			}
		}

		public function _clear_oauth()
		{
			$envato_options = envato_market()->get_options();
			unset($envato_options['oauth']);
			update_option(envato_market()->get_option_name(), $envato_options);
		}



		public function ajax_notice_handler()
		{
			check_ajax_referer('dtnwp-ajax-nonce', 'security');
			// Store it in the options table
			update_option('dtbwp_update_notice', time());
		}

		public function admin_theme_auth_notice()
		{


			if (function_exists('envato_market')) {
				$option = envato_market()->get_options();

				$envato_items = get_option('envato_setup_wizard', array());

				if (!$option || empty($option['oauth']) || empty($option['oauth'][$this->envato_username]) || empty($envato_items) || empty($envato_items['items']) || !envato_market()->items()->themes('purchased')) {

					// we show an admin notice if it hasn't been dismissed
					$dissmissed_time = get_option('dtbwp_update_notice', false);

					if (!$dissmissed_time || $dissmissed_time < strtotime('-7 days')) {
						// Added the class "notice-my-class" so jQuery pick it up and pass via AJAX,
						// and added "data-notice" attribute in order to track multiple / different notices
						// multiple dismissible notice states 
			?>
						<div class="notice notice-warning notice-dtbwp-themeupdates is-dismissible">
							<p><?php
								_e('Please activate ThemeForest updates to ensure you have the latest version of this theme.', 'listeo');
								?></p>
							<p>
								<?php printf(__('<a class="button button-primary" href="%s">Activate Updates</a>', 'listeo'),  esc_url($this->get_oauth_login_url(self_admin_url('admin.php?page=' . envato_market()->get_slug() . '')))); ?>
							</p>
						</div>
						<script type="text/javascript">
							jQuery(function($) {
								$(document).on('click', '.notice-dtbwp-themeupdates .notice-dismiss', function() {
									$.ajax(ajaxurl, {
										type: 'POST',
										data: {
											action: 'dtbwp_update_notice_handler',
											security: '<?php echo wp_create_nonce("dtnwp-ajax-nonce"); ?>'
										}
									});
								});
							});
						</script>
			<?php }
				}
			}
		}
		/**
		 * @param $array1
		 * @param $array2
		 *
		 * @return mixed
		 *
		 *
		 * @since    1.1.4
		 */
		private function _array_merge_recursive_distinct($array1, $array2)
		{
			$merged = $array1;
			foreach ($array2 as $key => &$value) {
				if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
					$merged[$key] = $this->_array_merge_recursive_distinct($merged[$key], $value);
				} else {
					$merged[$key] = $value;
				}
			}

			return $merged;
		}

		/**
		 * @param $args
		 * @param $url
		 *
		 * @return mixed
		 *
		 * Filter the WordPress HTTP call args.
		 * We do this to find any queries that are using an expired token from an oAuth bounce login.
		 * Since these oAuth tokens only last 1 hour we have to hit up our server again for a refresh of that token before using it on the Envato API.
		 * Hacky, but only way to do it.
		 */
		public function envato_market_http_request_args($args, $url)
		{
			if (strpos($url, 'api.envato.com') && function_exists('envato_market')) {
				// we have an API request.
				// check if it's using an expired token.
				if (!empty($args['headers']['Authorization'])) {
					$token = str_replace('Bearer ', '', $args['headers']['Authorization']);
					if ($token) {
						// check our options for a list of active oauth tokens and see if one matches, for this envato username.
						$option = envato_market()->get_options();
						if ($option && !empty($option['oauth'][$this->envato_username]) && $option['oauth'][$this->envato_username]['access_token'] == $token && $option['oauth'][$this->envato_username]['expires'] < time() + 120) {
							// we've found an expired token for this oauth user!
							// time to hit up our bounce server for a refresh of this token and update associated data.
							$this->_manage_oauth_token($option['oauth'][$this->envato_username]);
							$updated_option = envato_market()->get_options();
							if ($updated_option && !empty($updated_option['oauth'][$this->envato_username]['access_token'])) {
								// hopefully this means we have an updated access token to deal with.
								$args['headers']['Authorization'] = 'Bearer ' . $updated_option['oauth'][$this->envato_username]['access_token'];
							}
						}
					}
				}
			}

			return $args;
		}

		public function render_oauth_login_description_callback()
		{
			echo 'If you have purchased items from ' . esc_html($this->envato_username) . ' on ThemeForest or CodeCanyon please login here for quick and easy updates.';
		}

		public function render_oauth_login_fields_callback()
		{
			$option = envato_market()->get_options();
			?>
			<div class="oauth-login" data-username="<?php echo esc_attr($this->envato_username); ?>">
				<a href="<?php echo esc_url($this->get_oauth_login_url(self_admin_url('admin.php?page=' . envato_market()->get_slug() . '#settings'))); ?>" class="oauth-login-button button button-primary">Login with Envato to activate updates</a>
			</div>
<?php
		}

		/// a better filter would be on the post-option get filter for the items array.
		// we can update the token there.

		public function get_oauth_login_url($return)
		{
			return $this->oauth_script . '?bounce_nonce=' . wp_create_nonce('envato_oauth_bounce_' . $this->envato_username) . '&wp_return=' . urlencode($return);
		}

		/**
		 * Helper function
		 * Take a path and return it clean
		 *
		 * @param string $path
		 *
		 * @since    1.1.2
		 */
		public static function cleanFilePath($path)
		{
			$path = str_replace('', '', str_replace(array('\\', '\\\\', '//'), '/', $path));
			if ($path[strlen($path) - 1] === '/') {
				$path = rtrim($path, '/');
			}

			return $path;
		}

		public function is_submenu_page()
		{
			return ($this->parent_slug == '') ? false : true;
		}


		function wc_subscriber_auto_redirect($boolean)
		{
			return true;
		}
	}
} // if !class_exists

/**
 * Loads the main instance of Envato_Theme_Setup_Wizard to have
 * ability extend class functionality
 *
 * @since 1.1.1
 * @return object Envato_Theme_Setup_Wizard
 */
add_action('after_setup_theme', 'envato_theme_setup_wizard', 10);
if (!function_exists('envato_theme_setup_wizard')) :
	function envato_theme_setup_wizard()
	{
		Envato_Theme_Setup_Wizard::get_instance();
	}
endif;
