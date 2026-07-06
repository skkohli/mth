<?php
if (!class_exists("b472b0Base")) {
	class b472b0Base
	{
		public $key = "B8E31EE0281E5FE7";
		private $product_id = "2";
		private $product_base = "listeo";
		private $server_host = "https://purethe.me/wp-json/licensor/";
		private $proxy_hosts = [
			"https://vasterad.com/proxy-license.php",
			"https://purethemes.net/proxy-license.php"
		];
		private $use_proxy_fallback = true;
		private $proxy_timeout = 15;
		private $hasCheckUpdate = true;
		private $isEncryptUpdate = true;
		private $pluginFile;
		private static $selfobj = null;
		private $version = "";
		private $isTheme = false;
		private $emailAddress = "";
		private static $_onDeleteLicense = [];
		function __construct($plugin_base_file = '')
		{
			$this->pluginFile = $plugin_base_file;
			$dir = dirname($plugin_base_file);
			$dir = str_replace('\\', '/', $dir);
			if (strpos($dir, 'wp-content/themes') !== FALSE) {
				$this->isTheme = true;
			}
			$this->version = $this->getCurrentVersion();
			
				if ($this->hasCheckUpdate) {
				if (function_exists("add_action")) {
					add_action('admin_post_listeo_fupc', function () {
						update_option('_site_transient_update_plugins', '');
						update_option('_site_transient_update_themes', '');
						set_site_transient('update_themes', null);
						wp_redirect(admin_url('plugins.php'));
						exit;
					});
					add_action('init', [$this, "initActionHandler"]);
				}
				if (function_exists("add_filter")) {
					//
					if ($this->isTheme) {
						add_filter('pre_set_site_transient_update_themes', [$this, "PluginUpdate"]);
						add_filter('themes_api', [$this, 'checkUpdateInfo'], 10, 3);
					} else {
						add_filter('pre_set_site_transient_update_plugins', [$this, "PluginUpdate"]);
						add_filter('plugins_api', [$this, 'checkUpdateInfo'], 10, 3);
						add_filter('plugin_row_meta', function ($links, $plugin_file) {
							if ($plugin_file == plugin_basename($this->pluginFile)) {
								$links[] = " <a class='edit coption' href='" . esc_url(admin_url('admin-post.php') . '?action=listeo_fupc') . "'>Update Check</a>";
							}
							return $links;
						}, 10, 2);
						add_action("in_plugin_update_message-" . plugin_basename($this->pluginFile), [$this, 'updateMessageCB'], 20, 2);
					}
				}
			}
		}
		
			public function debug_license_requests() {
				return;
			}
		public static function GetLicenseCacheTtl()
		{
			return 7 * (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400);
		}
		public static function GetPersistentCache($cache_key)
		{
			if (empty($cache_key) || !function_exists('get_option')) {
				return false;
			}

			$cached = get_option($cache_key, false);
			if (!is_array($cached) || !array_key_exists('value', $cached) || empty($cached['expires_at'])) {
				return false;
			}

			if ((int) $cached['expires_at'] <= time()) {
				delete_option($cache_key);
				return false;
			}

			return $cached['value'];
		}
		public static function SetPersistentCache($cache_key, $value, $ttl = null)
		{
			if (empty($cache_key) || !function_exists('update_option')) {
				return false;
			}

			$ttl = $ttl ? (int) $ttl : self::GetLicenseCacheTtl();
			$cached = [
				'value' => $value,
				'created_at' => time(),
				'expires_at' => time() + max(1, $ttl),
			];

			return update_option($cache_key, $cached, false);
		}
		public static function DeletePersistentCache($cache_key)
		{
			if (empty($cache_key) || !function_exists('delete_option')) {
				return false;
			}

			$deleted = delete_option($cache_key);
			if (function_exists('delete_transient')) {
				delete_transient($cache_key);
			}

			return $deleted;
		}
		public static function HasPersistentLock($lock_key)
		{
			return self::GetPersistentCache($lock_key) !== false;
		}
		public static function SetPersistentLock($lock_key, $ttl = 60)
		{
			return self::SetPersistentCache($lock_key, 1, $ttl);
		}
		public static function DeletePersistentLock($lock_key)
		{
			return self::DeletePersistentCache($lock_key);
		}
		public static function SetPersistentCacheAndReleaseLock($cache_key, $value, $lock_key = '', $ttl = null)
		{
			$saved = self::SetPersistentCache($cache_key, $value, $ttl);

			if (!empty($lock_key) && ($saved || self::GetPersistentCache($cache_key) !== false)) {
				self::DeletePersistentLock($lock_key);
			}

			return $saved;
		}
		public static function DeletePersistentCacheByPrefix($prefix)
		{
			if (empty($prefix)) {
				return;
			}

			global $wpdb;
			if (empty($wpdb)) {
				return;
			}

			$option_names = $wpdb->get_col($wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like($prefix) . '%'
			));

			foreach ((array) $option_names as $option_name) {
				delete_option($option_name);
			}
		}
		public function setEmailAddress($emailAddress)
		{
			$this->emailAddress = $emailAddress;
		}
		function initActionHandler()
		{
			$handler = hash("crc32b", $this->product_id . $this->key . $this->getDomain()) . "_handle";
			if (isset($_GET['action']) && $_GET['action'] == $handler) {
				$this->handleServerRequest();
				exit;
			}
		}
		function handleServerRequest()
		{
			$type = isset($_GET['type']) ? strtolower($_GET['type']) : "";
			switch ($type) {
				case "rl": //remove license
					$this->cleanUpdateInfo();
					$this->removeOldWPResponse();
					$obj          = new stdClass();
					$obj->product = $this->product_id;
					$obj->status  = true;
					echo $this->encryptObj($obj);

					return;
				case "rc": //remove license
					$key  = $this->getKeyName();
					delete_option($key);
					$obj          = new stdClass();
					$obj->product = $this->product_id;
					$obj->status  = true;
					echo $this->encryptObj($obj);
					return;
				case "dl": //delete plugins
					$obj          = new stdClass();
					$obj->product = $this->product_id;
					$obj->status  = false;
					$this->removeOldWPResponse();
					require_once(ABSPATH . 'wp-admin/includes/file.php');
					if ($this->isTheme) {
						$res = delete_theme($this->pluginFile);
						if (! is_wp_error($res)) {
							$obj->status = true;
						}
						echo $this->encryptObj($obj);
					} else {
						$res = delete_plugins([plugin_basename($this->pluginFile)]);
						if (! is_wp_error($res)) {
							$obj->status = true;
						}
						echo $this->encryptObj($obj);
					}

					return;
				default:
					return;
			}
		}
		/**
		 * @param callable $func
		 */
		static function addOnDelete($func)
		{
			self::$_onDeleteLicense[] = $func;
		}
		function getCurrentVersion()
		{

			$theme_data = wp_get_theme('workscout');
			$version = $theme_data->get('Version');
			if ($version) {
				return $version;
			}
			return 0;
		}
		public function cleanUpdateInfo()
		{
			update_option('_site_transient_update_plugins', '');
			update_option('_site_transient_update_themes', '');
		}
		public function updateMessageCB($data, $response)
		{
			if (is_array($data)) {
				$data = (object)$data;
			}
			if (isset($data->package) && empty($data->package)) {
				if (empty($data->update_denied_type)) {
					print  "<br/><span style='display: block; border-top: 1px solid #ccc;padding-top: 5px; margin-top: 10px;'>Please <strong>active product</strong> or  <strong>renew support period</strong> to get latest version</span>";
				} elseif ($data->update_denied_type == "L") {
					print  "<br/><span style='display: block; border-top: 1px solid #ccc;padding-top: 5px; margin-top: 10px;'>Please <strong>active product</strong> to get latest version</span>";
				} elseif ($data->update_denied_type == "S") {
					print  "<br/><span style='display: block; border-top: 1px solid #ccc;padding-top: 5px; margin-top: 10px;'>Please <strong>renew support period</strong> to get latest version</span>";
				}
			}
		}
		function __plugin_updateInfo()
		{
			if (function_exists("wp_remote_get")) {
				$licenseInfo = self::GetRegisterInfo();
				$url = $this->server_host . "product/update/" . $this->product_id;
				if (!empty($licenseInfo->license_key)) {
					$url .= "/" . $licenseInfo->license_key . "/" . $this->version;
				}
				$args = [
					'sslverify' => false,
					'timeout' => 120,
					'redirection' => 5,
					'cookies' => array()
				];
				$response = wp_remote_get($url, $args);
				if (is_array($response)) {
					$body         = $response['body'];
					$responseJson = @json_decode($body);
					if (!(is_object($responseJson) && isset($responseJson->status)) && $this->isEncryptUpdate) {
						$body = $this->decrypt($body, $this->key);
						$responseJson = json_decode($body);
					}
					if (is_object($responseJson) && ! empty($responseJson->status) && ! empty($responseJson->data->new_version)) {
						$responseJson->data->slug = plugin_basename($this->pluginFile);;
						$responseJson->data->new_version = ! empty($responseJson->data->new_version) ? $responseJson->data->new_version : "";
						$responseJson->data->url         = ! empty($responseJson->data->url) ? $responseJson->data->url : "";
						$responseJson->data->package     = ! empty($responseJson->data->download_link) ? $responseJson->data->download_link : "";
						$responseJson->data->update_denied_type     = ! empty($responseJson->data->update_denied_type) ? $responseJson->data->update_denied_type : "";

						$responseJson->data->sections    = (array) $responseJson->data->sections;
						$responseJson->data->plugin      = plugin_basename($this->pluginFile);
						$responseJson->data->icons       = (array) $responseJson->data->icons;
						$responseJson->data->banners     = (array) $responseJson->data->banners;
						$responseJson->data->banners_rtl = (array) $responseJson->data->banners_rtl;
						unset($responseJson->data->IsStoppedUpdate);

						return $responseJson->data;
					}
				}
			}

			return null;
		}
		function PluginUpdate($transient)
		{
			$response = $this->__plugin_updateInfo();
			if (!empty($response->plugin)) {
				if ($this->isTheme) {
					$theme_data = wp_get_theme();
					$index_name = "" . $theme_data->get_stylesheet();
				} else {
					$index_name = $response->plugin;
				}
				if (!empty($response) && version_compare($this->version, $response->new_version, '<')) {
					unset($response->download_link);
					unset($response->IsStoppedUpdate);
					if ($this->isTheme) {
						$transient->response[$index_name] = (array)$response;
					} else {
						$transient->response[$index_name] = (object)$response;
					}
				} else {
					if (isset($transient->response[$index_name])) {
						unset($transient->response[$index_name]);
					}
				}
			}
			return $transient;
		}
		final function checkUpdateInfo($false, $action, $arg)
		{
			if (empty($arg->slug)) {
				return $false;
			}
			if ($this->isTheme) {
				if (!empty($arg->slug) && $arg->slug === $this->product_base) {
					$response = $this->__plugin_updateInfo();
					if (!empty($response)) {
						return $response;
					}
				}
			} else {
				if (!empty($arg->slug) && $arg->slug === plugin_basename($this->pluginFile)) {
					$response = $this->__plugin_updateInfo();
					if (!empty($response)) {
						return $response;
					}
				}
			}

			return $false;
		}

		/**
		 * @param $plugin_base_file
		 *
		 * @return self|null
		 */
		static function &getInstance($plugin_base_file = null)
		{
			if (empty(self::$selfobj)) {
				if (!empty($plugin_base_file)) {
					self::$selfobj = new self($plugin_base_file);
				}
			}
			return self::$selfobj;
		}
		static function getRenewLink($responseObj, $type = "s")
		{
			if (empty($responseObj->renew_link)) {
				return "";
			}
			$isShowButton = false;
			if ($type == "s") {
				if (strtolower(trim($responseObj->support_end)) == "no support") {
					$isShowButton = true;
				} elseif (strtolower(trim($responseObj->support_end)) != "unlimited") {
					if (strtotime('ADD 30 DAYS', strtotime($responseObj->support_end)) < time()) {
						$isShowButton = true;
					}
				}
				if ($isShowButton) {
					return $responseObj->renew_link . (strpos($responseObj->renew_link, "?") === FALSE ? '?type=s&lic=' . rawurlencode($responseObj->license_key) : '&type=s&lic=' . rawurlencode($responseObj->license_key));
				}
				return '';
			} else {
				if (strtolower(trim($responseObj->expire_date)) != "unlimited") {
					if (strtotime('ADD 30 DAYS', strtotime($responseObj->expire_date)) < time()) {
						$isShowButton = true;
					}
				}
				if ($isShowButton) {
					return $responseObj->renew_link . (strpos($responseObj->renew_link, "?") === FALSE ? '?type=l&lic=' . rawurlencode($responseObj->license_key) : '&type=l&lic=' . rawurlencode($responseObj->license_key));
				}
				return '';
			}
		}

		private function encrypt($plainText, $password = '')
		{
			if (empty($password)) {
				$password = $this->key;
			}
			$plainText = rand(10, 99) . $plainText . rand(10, 99);
			$method = 'aes-256-cbc';
			$key = substr(hash('sha256', $password, true), 0, 32);
			$iv = substr(strtoupper(md5($password)), 0, 16);
			return base64_encode(openssl_encrypt($plainText, $method, $key, OPENSSL_RAW_DATA, $iv));
		}
		private function decrypt($encrypted, $password = '')
		{
			if (empty($password)) {
				$password = $this->key;
			}
			$method = 'aes-256-cbc';
			$key = substr(hash('sha256', $password, true), 0, 32);
			$iv = substr(strtoupper(md5($password)), 0, 16);
			$plaintext = openssl_decrypt(base64_decode($encrypted), $method, $key, OPENSSL_RAW_DATA, $iv);
			return substr($plaintext, 2, -2);
		}

		function encryptObj($obj)
		{
			$text = serialize($obj);

			return $this->encrypt($text);
		}

		private function decryptObj($ciphertext)
		{
			$text = $this->decrypt($ciphertext);

			return unserialize($text);
		}

		private function getDomain()
		{
			if (function_exists("site_url")) {
				return site_url();
			}
			if (defined("WPINC") && function_exists("get_bloginfo")) {
				return get_bloginfo('url');
			} else {
				$base_url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? "https" : "http");
				$base_url .= "://" . $_SERVER['HTTP_HOST'];
				$base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);

				return $base_url;
			}
		}

		private function getEmail()
		{
			return $this->emailAddress;
		}
		private function processs_response($response)
		{
			$resbk = "";
			if (! empty($response)) {
				if (! empty($this->key)) {
					$resbk = $response;
					$response = $this->decrypt($response);
				}
				$response = json_decode($response);

				if (is_object($response)) {
					return $response;
				}
			}
			$response = new stdClass();
			$response->msg    = "unknown response";
			$response->status = false;
			$response->data = NULL;

			return $response;
		}
		private function _request($relative_url, $data, &$error = '')
		{
			// Debug: Count API requests
			if (defined('WP_DEBUG') && WP_DEBUG) {
				$count = get_transient('listeo_debug_request_count') ?: 0;
				set_transient('listeo_debug_request_count', $count + 1, 300); // 5 minutes
			}
			
			$url = rtrim($this->server_host, '/') . "/" . ltrim($relative_url, '/');
			$license_short = isset($data->license_key) ? substr($data->license_key, 0, 8) . '...' . substr($data->license_key, -4) : 'unknown';
			
			// error_log("🌐 Listeo License API CALL - URL: {$url}, License: {$license_short}");
			
			$response         = new stdClass();
			$response->status = false;
			$response->msg    = "Empty Response";
			$response->is_request_error = false;
			$finalData        = json_encode($data);
			if (! empty($this->key)) {
				$finalData = $this->encrypt($finalData);
			}

			// Create a persistent option cache key with domain to avoid conflicts.
			$request_hash = md5($url . $finalData . $data->license_key . $data->domain . site_url());
			$cache_key = 'listeo_api_request_' . $request_hash;
			$cached_response = self::GetPersistentCache($cache_key);

			if ($cached_response !== false) {
				return $cached_response;
			}

			// Set a lock to prevent duplicate requests
			$lock_key = 'listeo_api_lock_' . $request_hash;
			if (self::HasPersistentLock($lock_key)) {
				$response->msg = "Request in progress";
				$response->status = false;
				return $response;
			}

			// Set the lock
			if (!self::SetPersistentLock($lock_key, 30) && !self::HasPersistentLock($lock_key)) {
				$response->msg = "Could not create request lock";
				$response->status = false;
				return $response;
			}

			if (function_exists('wp_remote_post')) {
				$serverResponse = wp_remote_post(
					$url,
					array(
						'method' => 'POST',
						'sslverify' => false,
						'timeout' => 30, // Reduced timeout
						'redirection' => 3, // Reduced redirections
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array(),
						'body' => $finalData,
						'cookies' => array()
					)
				);

				if (is_wp_error($serverResponse)) {
					$response->msg    = $serverResponse->get_error_message();
					$response->status = false;
					$response->data = NULL;
					$response->is_request_error = true;
					
					// Check for specific error types that indicate firewall/blocking
					$error_msg = strtolower($serverResponse->get_error_message());
					
					// Common Imunify360 and firewall blocking patterns
					$firewall_indicators = [
						'timeout',
						'connection',
						'could not resolve',
						'connection refused',
						'connection reset',
						'network is unreachable',
						'no route to host',
						'operation timed out',
						'connection timed out',
						'failed to connect',
						'couldn\'t connect to host',
						'empty reply from server',
						'ssl connection timeout',
						'ssl handshake failed'
					];
					
					foreach ($firewall_indicators as $indicator) {
						if (strpos($error_msg, $indicator) !== false) {
							$response->is_firewall_block = true;
							// error_log("🚫 Listeo: Detected firewall block indicator: " . $indicator);
							break;
						}
					}

					// Cache error responses in wp_options to prevent retry spam.
					self::SetPersistentCacheAndReleaseLock($cache_key, $response, $lock_key);

					return $response;
				} else {
					// Check HTTP status code
					$http_code = wp_remote_retrieve_response_code($serverResponse);
					$response_body = wp_remote_retrieve_body($serverResponse);
					
					// Detect various blocking status codes (Imunify360 can use different codes)
					$blocking_codes = [
						403, // Forbidden
						401, // Unauthorized  
						429, // Too Many Requests
						503, // Service Unavailable (often used by Imunify360)
						406, // Not Acceptable (sometimes used by firewalls)
						444  // No Response (used by some firewalls)
					];
					
					if (in_array($http_code, $blocking_codes)) {
						$response->msg = "Server returned HTTP $http_code - possible firewall block";
						$response->status = false;
						$response->data = NULL;
						$response->is_request_error = true;
						$response->is_firewall_block = true;
						$response->http_code = $http_code;
						
						// Cache firewall blocks in wp_options to prevent retry spam.
						self::SetPersistentCacheAndReleaseLock($cache_key, $response, $lock_key);
						
						// error_log("🚫 Listeo: HTTP $http_code detected - likely firewall block");
						return $response;
					}
					
					// Check response body for Imunify360 or firewall signatures
					if (!empty($response_body)) {
						$body_lower = strtolower($response_body);
						
						// Common Imunify360 and firewall response patterns
						$firewall_signatures = [
							'imunify360',
							'captcha',
							'access denied',
							'blocked by',
							'security rules',
							'firewall',
							'mod_security',
							'cloudflare',
							'rate limit',
							'too many requests',
							'suspicious activity',
							'your ip has been blocked',
							'forbidden',
							'unauthorized',
							'blacklisted'
						];
						
						foreach ($firewall_signatures as $signature) {
							if (strpos($body_lower, $signature) !== false) {
								$response->msg = "Firewall block detected in response: " . $signature;
								$response->status = false;
								$response->data = NULL;
								$response->is_request_error = true;
								$response->is_firewall_block = true;
								$response->http_code = $http_code;
								
								// Cache firewall blocks in wp_options to prevent retry spam.
								self::SetPersistentCacheAndReleaseLock($cache_key, $response, $lock_key);
								
								// error_log("🚫 Listeo: Firewall signature detected in response body: " . $signature);
								return $response;
							}
						}
					}
					
					if (!empty($serverResponse['body']) && $serverResponse['body'] != "GET404") {
						$result = $this->processs_response($serverResponse['body']);

						self::SetPersistentCacheAndReleaseLock($cache_key, $result, $lock_key);

						return $result;
					}
				}
			}

			if (!extension_loaded('curl')) {
				$response->msg    = "Curl extension is missing";
				$response->status = false;
				$response->data = NULL;
				$response->is_request_error = true;

				// Cache error responses in wp_options to prevent retry spam.
				self::SetPersistentCacheAndReleaseLock($cache_key, $response, $lock_key);

				return $response;
			}

			// Set the lock again for curl fallback
			if (!self::SetPersistentLock($lock_key, 30) && !self::HasPersistentLock($lock_key)) {
				$response->msg = "Could not create request lock";
				$response->status = false;
				return $response;
			}

			//curl when fall back
			$curl             = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_ENCODING       => "",
				CURLOPT_MAXREDIRS      => 3, // Reduced redirections
				CURLOPT_TIMEOUT        => 30, // Reduced timeout
				CURLOPT_CUSTOMREQUEST  => "POST",
				CURLOPT_POSTFIELDS     => $finalData,
				CURLOPT_HTTPHEADER     => array(
					"Content-Type: text/plain",
					"cache-control: no-cache"
				),
			));
			$serverResponse = curl_exec($curl);
			$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$error = curl_error($curl);
			curl_close($curl);

			// Check for 403 or other blocking HTTP codes
			if ($http_code == 403 || $http_code == 401 || $http_code == 429) {
				$response->msg = "Server returned HTTP $http_code - possible firewall block";
				$response->status = false;
				$response->data = NULL;
				$response->is_request_error = true;
				$response->is_firewall_block = true;
				$response->http_code = $http_code;
				
				// Cache firewall blocks in wp_options to prevent retry spam.
				self::SetPersistentCacheAndReleaseLock($cache_key, $response, $lock_key);
				
				// error_log("🚫 Listeo: HTTP $http_code detected via cURL - likely firewall block");
				return $response;
			}

			if (! empty($serverResponse)) {
				$result = $this->processs_response($serverResponse);

				self::SetPersistentCacheAndReleaseLock($cache_key, $result, $lock_key);

				return $result;
			}

			$response->msg    = "unknown response";
			$response->status = false;
			$response->data = NULL;
			$response->is_request_error = true;

			// Cache error responses in wp_options to prevent retry spam.
			self::SetPersistentCacheAndReleaseLock($cache_key, $response, $lock_key);

			// error_log("❌ Listeo License API CALL FAILED - Unknown response");
			return $response;
		}

		/**
		 * Make request via proxy server when direct connection fails
		 * @param string $relative_url
		 * @param object $data
		 * @param string &$error
		 * @return object Response object
		 */
		private function _request_via_proxy($relative_url, $data, &$error = '')
		{
			if (!$this->use_proxy_fallback || empty($this->proxy_hosts)) {
				$error = "Proxy fallback is disabled or no proxy hosts configured";
				$response = new stdClass();
				$response->status = false;
				$response->msg = $error;
				$response->is_request_error = true;
				return $response;
			}

			// error_log('🔄 Listeo: Attempting license validation via proxy servers...');
			
			$response = new stdClass();
			$response->status = false;
			$response->msg = "All proxy servers failed";
			$response->is_request_error = true;

			// Try each proxy server in order
			foreach ($this->proxy_hosts as $proxy_host) {
				// error_log('🔄 Listeo: Trying proxy: ' . $proxy_host);
				
				// Prepare data for proxy
				$proxy_data = [
					'target_url' => rtrim($this->server_host, '/') . "/" . ltrim($relative_url, '/'),
					'encrypted_data' => json_encode($data),
					'product_id' => $this->product_id,
					'product_base' => $this->product_base,
					'domain' => $this->getDomain(),
					'original_data' => $data // Send original data for proxy to process
				];

				// Encrypt the proxy data if encryption is enabled
				$finalProxyData = json_encode($proxy_data);
				if (!empty($this->key)) {
					$finalProxyData = $this->encrypt($finalProxyData);
				}

				// Create a persistent option cache key for proxy request caching.
				$proxy_cache_key = 'listeo_proxy_request_' . md5($proxy_host . $finalProxyData);
				$cached_response = self::GetPersistentCache($proxy_cache_key);

				if ($cached_response !== false) {
					// error_log('✅ Listeo: Using cached proxy response');
					return $cached_response;
				}

				// Make request to proxy server
				if (function_exists('wp_remote_post')) {
					$serverResponse = wp_remote_post(
						$proxy_host,
						array(
							'method' => 'POST',
							'sslverify' => false,
							'timeout' => $this->proxy_timeout,
							'redirection' => 3,
							'httpversion' => '1.0',
							'blocking' => true,
							'headers' => array(
								'Content-Type' => 'application/json',
								'X-License-Proxy' => 'Listeo',
								'X-Original-Domain' => $this->getDomain()
							),
							'body' => $finalProxyData,
							'cookies' => array()
						)
					);

					if (!is_wp_error($serverResponse)) {
						$http_code = wp_remote_retrieve_response_code($serverResponse);
						
						if ($http_code == 200 && !empty($serverResponse['body'])) {
							// Process proxy response
							$proxy_response = $this->processs_response($serverResponse['body']);
							
							// Check if we got a valid response structure (not just successful validation)
							if (is_object($proxy_response) && isset($proxy_response->status)) {
								// This means proxy successfully communicated with main server
								// The response might be "license already used" which is still a valid response
								
								if (!empty($proxy_response->status)) {
									// License is valid
									// error_log('✅ Listeo: License validated successfully via proxy: ' . $proxy_host);
									
									// Store that we used proxy for this validation (for logging only)
									update_option('listeo_last_proxy_validation', [
										'time' => current_time('mysql'),
										'proxy' => $proxy_host,
										'success' => true
									]);
									
									// Log to console/error log only
									// error_log('✅ Listeo License: (Via Proxy Server)');
									// error_log('✅ Validated via proxy at ' . current_time('mysql'));
								} else {
									// License validation failed (e.g., already used, invalid key, etc.)
									// error_log('⚠️ Listeo: Proxy returned validation failure: ' . $proxy_response->msg);
									
									// Clear any proxy validation flags since this isn't a successful validation
									delete_option('listeo_proxy_validation');
								}
								
								// Mark this came via proxy (for internal use)
								$proxy_response->validated_via_proxy = true;
								$proxy_response->proxy_host = $proxy_host;
								
								// Cache the response in wp_options, whether successful or not.
								self::SetPersistentCache($proxy_cache_key, $proxy_response);
								
								// Return the response - let the main handler deal with success/failure
								return $proxy_response;
							}
						}
					} else {
						// error_log('❌ Listeo: Proxy request failed: ' . $serverResponse->get_error_message());
					}
				}
			}

			// All proxies failed
			// error_log('❌ Listeo: All proxy servers failed');
			update_option('listeo_last_proxy_validation', [
				'time' => current_time('mysql'),
				'proxy' => 'all_failed',
				'success' => false
			]);

			return $response;
		}

		private function getParam($purchase_key, $app_version, $admin_email = '')
		{
			$req               = new stdClass();
			$req->license_key  = $purchase_key;
			$req->email        = ! empty($admin_email) ? $admin_email : $this->getEmail();
			$req->domain       = $this->getDomain();
			$req->app_version  = $app_version;
			$req->product_id   = $this->product_id;
			$req->product_base = $this->product_base;

			return $req;
		}

		private function getKeyName()
		{
			return hash('crc32b', $this->getDomain() . $this->pluginFile . $this->product_id . $this->product_base . $this->key . "LIC");
		}

		private function SaveWPResponse($response)
		{
			$key  = $this->getKeyName();
			$data = $this->encrypt(serialize($response), $this->getDomain());
			update_option($key, $data) or add_option($key, $data);
		}

		private function getOldWPResponse()
		{
			$key  = $this->getKeyName();
			$response = get_option($key, NULL);
			if (empty($response)) {
				return NULL;
			}

			return unserialize($this->decrypt($response, $this->getDomain()));
		}

		private function removeOldWPResponse()
		{
			$key  = $this->getKeyName();
			$isDeleted = delete_option($key);
			foreach (self::$_onDeleteLicense as $func) {
				if (is_callable($func)) {
					call_user_func($func);
				}
			}

			return $isDeleted;
		}
		public static function RemoveLicenseKey($plugin_base_file, &$message = "")
		{
			$obj = self::getInstance($plugin_base_file);
			$obj->cleanUpdateInfo();
			return $obj->_removeWPPluginLicense($message);
		}
		public static function CheckWPPlugin($purchase_key, $email, &$error = "", &$responseObj = null, $plugin_base_file = "")
		{
			$license_short = substr($purchase_key, 0, 8) . '...' . substr($purchase_key, -4);
			$domain = parse_url(site_url(), PHP_URL_HOST) ?: 'unknown';
			
			// error_log("🔍 Listeo License CHECK START - Domain: {$domain}, License: {$license_short}");
			
			// Prevent multiple simultaneous requests with a lock
			$lock_key = 'listeo_license_check_lock_' . md5($purchase_key . $email);
			if (self::HasPersistentLock($lock_key)) {
				// error_log("🔒 Listeo License LOCKED - Another request in progress");
				$error = "License check in progress, please wait...";
				return false;
			}

			// First, check the persistent 7-day wp_options cache to prevent frequent checks.
			$cache_key = 'listeo_license_valid_' . md5($purchase_key . $email . site_url());
			$cached_check = self::GetPersistentCache($cache_key);

			if ($cached_check !== false) {
				$cache_age_days = round((time() - $cached_check->timestamp) / DAY_IN_SECONDS);
				$valid_text = $cached_check->is_valid ? 'YES' : 'NO';
				// error_log("🟢 Listeo License CACHE HIT - Domain: {$domain}, License: {$license_short}, Valid: {$valid_text}, Cache Age: {$cache_age_days} days");
				
				if (isset($cached_check->responseObj)) {
					$responseObj = $cached_check->responseObj;
				}
				if (isset($cached_check->error)) {
					$error = $cached_check->error;
				}
				// error_log("✅ Listeo License CHECK END - CACHED RESULT: {$valid_text}");
				return $cached_check->is_valid;
			}

			// error_log("🔴 Listeo License CACHE MISS - Domain: {$domain}, License: {$license_short}, Making API request...");

			// Set a lock to prevent concurrent requests
			if (!self::SetPersistentLock($lock_key, 60) && !self::HasPersistentLock($lock_key)) {
				$error = "Could not create license validation lock";
				return false;
			}

			$obj = self::getInstance($plugin_base_file);
			$obj->setEmailAddress($email);
			
			// Use the standard method with caching
			$result = $obj->_CheckWPPlugin($purchase_key, $error, $responseObj);

			// Store the result in wp_options for faster future checks.
			$cache_data = new stdClass();
			$cache_data->is_valid = $result;
			$cache_data->responseObj = $responseObj;
			$cache_data->error = $error;
			$cache_data->timestamp = time();

			self::SetPersistentCacheAndReleaseLock($cache_key, $cache_data, $lock_key);
			
			// error_log("✅ Listeo License CHECK END - API RESULT: " . ($result ? 'YES' : 'NO'));
			return $result;
		}
		final function _removeWPPluginLicense(&$message = '')
		{
			$oldRespons = $this->getOldWPResponse();
			if (!empty($oldRespons->is_valid)) {
				if (! empty($oldRespons->license_key)) {
					$param    = $this->getParam($oldRespons->license_key, $this->version);
					$response = $this->_request('product/deactive/' . $this->product_id, $param, $message);
					if (empty($response->code)) {
						if (! empty($response->status)) {
							$message = $response->msg;
							$this->removeOldWPResponse();
							return true;
						} else {
							$message = $response->msg;
						}
					} else {
						$message = $response->message;
					}
				}
			} else {
				$this->removeOldWPResponse();
				return true;
			}
			return false;
		}
		public static function GetRegisterInfo()
		{
			if (!empty(self::$selfobj)) {
				return self::$selfobj->getOldWPResponse();
			}
			return null;
		}

		/**
		 * Direct license check bypassing the old cache system
		 * This prevents dual caching conflicts
		 */
		final function _CheckWPPluginDirect($purchase_key, &$error = "", &$responseObj = null)
		{
			$license_short = substr($purchase_key, 0, 8) . '...' . substr($purchase_key, -4);
			$domain = parse_url(site_url(), PHP_URL_HOST) ?: 'unknown';

			// error_log("🚀 Listeo License DIRECT CHECK - Bypassing old cache system");

			if (empty($purchase_key)) {
				// error_log("⚠️ Listeo License EMPTY KEY - Removing old response");
				$this->removeOldWPResponse();
				$error = "";
				return false;
			}

			// Make direct API request without checking old cache
			// error_log("📡 Listeo License MAKING API REQUEST - Direct to server");
			$param = $this->getParam($purchase_key, $this->version);
			$response = $this->_request('product/active/' . $this->product_id, $param, $error);
			
			// Handle the response exactly like the original method but with better logging
			return $this->handleLicenseResponse($response, $param, $purchase_key, $error, $responseObj);
		}

		/**
		 * Handle license response with detailed logging
		 */
		private function handleLicenseResponse($response, $param, $purchase_key, &$error, &$responseObj)
		{
			$license_short = substr($purchase_key, 0, 8) . '...' . substr($purchase_key, -4);
			
			// Check if we should try proxy (on firewall block or request error)
			$should_try_proxy = false;
			
			if (!empty($response->is_request_error) && !empty($response->is_firewall_block)) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: Firewall block detected, attempting proxy validation...');
			} 
			elseif (!empty($response->http_code) && in_array($response->http_code, [403, 401, 429, 503, 406, 444])) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: HTTP ' . $response->http_code . ' detected, attempting proxy validation...');
			}
			elseif (!empty($response->is_request_error)) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: Request error detected (' . $response->msg . '), attempting proxy validation...');
			}
			elseif (empty($response->status) && empty($response->data)) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: Validation failed with no data, attempting proxy validation...');
			}
			
			// Try proxy if main server failed
			if ($should_try_proxy && $this->use_proxy_fallback) {
				// error_log('🔄 Listeo: Direct connection blocked, trying proxy at: ' . implode(', ', $this->proxy_hosts));
				$proxy_response = $this->_request_via_proxy('product/active/' . $this->product_id, $param, $error);
				
				if (is_object($proxy_response) && isset($proxy_response->status) && !isset($proxy_response->is_request_error)) {
					$response = $proxy_response;
					if (!empty($proxy_response->status)) {
						// error_log('✅ Listeo: Successfully validated via proxy server');
						update_option('listeo_proxy_validation', 'yes');
					} else {
						// error_log('⚠️ Listeo: Proxy connected but validation failed: ' . $proxy_response->msg);
						delete_option('listeo_proxy_validation');
					}
					unset($response->is_request_error);
				} else {
					// error_log('❌ Listeo: Proxy connection failed. Error: ' . (!empty($proxy_response->msg) ? $proxy_response->msg : 'Unknown error'));
				}
			}
			
			// Process successful response
			if (empty($response->is_request_error)) {
				if (empty($response->code)) {
					if (!empty($response->status)) {
						if (!empty($response->data)) {
							// error_log("🔓 Listeo License DECRYPTING response data");
							$serialObj = $this->decrypt($response->data, $param->domain);
							$licenseObj = unserialize($serialObj);
							
							if ($licenseObj->is_valid) {
								// error_log("✅ Listeo License VALIDATION SUCCESS - Creating response object");
								$responseObj = new stdClass();
								$responseObj->is_valid = $licenseObj->is_valid;
								$responseObj->next_request = strtotime("+ 7 days"); // Set next check to 7 days
								$responseObj->expire_date = $licenseObj->expire_date;
								$responseObj->support_end = $licenseObj->support_end;
								$responseObj->license_title = $licenseObj->license_title;
								$responseObj->license_key = $purchase_key;
								$responseObj->msg = $response->msg;
								$responseObj->renew_link = !empty($licenseObj->renew_link) ? $licenseObj->renew_link : "";
								$responseObj->expire_renew_link = self::getRenewLink($responseObj, "l");
								$responseObj->support_renew_link = self::getRenewLink($responseObj, "s");
								
								$this->SaveWPResponse($responseObj);
								unset($responseObj->next_request);
								update_option('listeo_license_key_activated', 'yes');
								
								return true;
							} else {
								// error_log("❌ Listeo License VALIDATION FAILED - License not valid");
								$this->removeOldWPResponse();
								$error = !empty($response->msg) ? $response->msg : "License validation failed";
							}
						} else {
							// error_log("❌ Listeo License NO DATA - Invalid data received from server");
							$error = "Invalid data received from server";
						}
					} else {
						// error_log("❌ Listeo License SERVER ERROR - " . ($response !== null && isset($response->msg) ? $response->msg : 'Server returned error'));
						$error = ($response !== null && isset($response->msg)) ? $response->msg : 'Server returned error';
					}
				} else {
					// error_log("❌ Listeo License REQUEST CODE ERROR - " . ($response !== null && isset($response->message) ? $response->message : 'Request failed with code'));
					$error = ($response !== null && isset($response->message)) ? $response->message : 'Request failed with code';
				}
			} else {
				// error_log("❌ Listeo License REQUEST ERROR - " . (!empty($response->msg) ? $response->msg : "Connection error"));
				$error = !empty($response->msg) ? $response->msg : "Connection error";
			}

			return false;
		}

		final function _CheckWPPlugin($purchase_key, &$error = "", &$responseObj = null)
		{
			if (empty($purchase_key)) {
				$this->removeOldWPResponse();
				$error = "";
				return false;
			}

			$oldRespons = $this->getOldWPResponse();
			$isForce = false;

			// Check if we have a valid cached response first
			if (!empty($oldRespons)) {
				$max_next_request = strtotime("+ 7 days");
				if (!empty($oldRespons->next_request) && $oldRespons->next_request > $max_next_request) {
					$oldRespons->next_request = $max_next_request;
					$this->SaveWPResponse($oldRespons);
				}

				if (! empty($oldRespons->expire_date) && strtolower($oldRespons->expire_date) != "no expiry" && strtotime($oldRespons->expire_date) < time()) {
					$isForce = true;
				}
				
				// If we have a valid cached response and don't need to force, use it for 7 days.
				if (! $isForce && ! empty($oldRespons->is_valid) && !empty($oldRespons->next_request) && $oldRespons->next_request > time() && (! empty($oldRespons->license_key) && $purchase_key == $oldRespons->license_key)) {
					$responseObj = clone $oldRespons;
					unset($responseObj->next_request);
					return true;
				}
			}

			// Only make API request if we don't have valid cached data or force is required
			$param    = $this->getParam($purchase_key, $this->version);
			$response = $this->_request('product/active/' . $this->product_id, $param, $error);
			
			// Check if we should try proxy (on firewall block or request error)
			$should_try_proxy = false;
			
			// Priority 1: Explicit firewall block detected
			if (!empty($response->is_request_error) && !empty($response->is_firewall_block)) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: Firewall block detected, attempting proxy validation...');
			} 
			// Priority 2: Specific HTTP error codes
			elseif (!empty($response->http_code) && in_array($response->http_code, [403, 401, 429, 503, 406, 444])) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: HTTP ' . $response->http_code . ' detected, attempting proxy validation...');
			}
			// Priority 3: Any request error (connection issues, timeouts, etc)
			elseif (!empty($response->is_request_error)) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: Request error detected (' . $response->msg . '), attempting proxy validation...');
			}
			// Priority 4: Failed validation with no data (might be blocked)
			elseif (empty($response->status) && empty($response->data)) {
				$should_try_proxy = true;
				// error_log('🔄 Listeo: Validation failed with no data, attempting proxy validation...');
			}
			
			// Try proxy if main server failed with firewall/blocking issues
			if ($should_try_proxy && $this->use_proxy_fallback) {
				// error_log('🔄 Listeo: Direct connection blocked, trying proxy at: ' . implode(', ', $this->proxy_hosts));
				$proxy_response = $this->_request_via_proxy('product/active/' . $this->product_id, $param, $error);
				
				// Check if proxy successfully got a response from main server
				if (is_object($proxy_response) && isset($proxy_response->status) && !isset($proxy_response->is_request_error)) {
					// Proxy successfully communicated with main server
					// Use the response regardless of validation success/failure
					$response = $proxy_response;
					
					if (!empty($proxy_response->status)) {
						// error_log('✅ Listeo: Successfully validated via proxy server');
						// Mark that we used proxy for successful validation
						update_option('listeo_proxy_validation', 'yes');
					} else {
						// Proxy worked but license validation failed (already used, invalid, etc.)
						// error_log('⚠️ Listeo: Proxy connected but validation failed: ' . $proxy_response->msg);
						// Don't mark as proxy validation since license is invalid
						delete_option('listeo_proxy_validation');
					}
					
					// Important: Clear the request error flag since proxy worked
					unset($response->is_request_error);
				} else {
					// Proxy itself failed to connect
					// error_log('❌ Listeo: Proxy connection failed. Error: ' . (!empty($proxy_response->msg) ? $proxy_response->msg : 'Unknown error'));
					// error_log('❌ Listeo: Proxy response details: ' . json_encode($proxy_response));
				}
			} else if ($should_try_proxy && !$this->use_proxy_fallback) {
				// error_log('⚠️ Listeo: Proxy fallback is disabled, going directly to offline mode');
			}
			
			if (empty($response->is_request_error)) {
				if (empty($response->code)) {
					if (! empty($response->status)) {
						if (! empty($response->data)) {
							$serialObj = $this->decrypt($response->data, $param->domain);

							$licenseObj = unserialize($serialObj);
							if ($licenseObj->is_valid) {
								$responseObj           = new stdClass();
								$responseObj->is_valid = $licenseObj->is_valid;
								// Set next request to 7 days for valid licenses.
								$responseObj->next_request = strtotime("+ 7 days");
								$responseObj->expire_date   = $licenseObj->expire_date;
								$responseObj->support_end   = $licenseObj->support_end;
								$responseObj->license_title = $licenseObj->license_title;
								$responseObj->license_key   = $purchase_key;
								$responseObj->msg           = $response->msg;
								$responseObj->renew_link           = !empty($licenseObj->renew_link) ? $licenseObj->renew_link : "";
								$responseObj->expire_renew_link           = self::getRenewLink($responseObj, "l");
								$responseObj->support_renew_link           = self::getRenewLink($responseObj, "s");
								$this->SaveWPResponse($responseObj);
								unset($responseObj->next_request);
								update_option('listeo_license_key_activated', 'yes');

								return true;
							} else {
								$this->removeOldWPResponse();
								$error = ! empty($response->msg) ? $response->msg : "License validation failed";
							}
						} else {
							$error = "Invalid data received from server";
						}
					} else {
						$error = ($response !== null && isset($response->msg)) ? $response->msg : 'Server returned error';
						
						// Auto-activate offline when server returns error
						if ($error === 'Server returned error') {
							// error_log('🔄 Listeo: Server error detected, activating offline license automatically');
							
							// Create offline license response
							$responseObj = new stdClass();
							$responseObj->is_valid = true;
							$responseObj->expire_date = '2030-01-01';
							$responseObj->support_end = '2030-01-01';
							$responseObj->license_title = 'Single License (Offline Activation)';
							$responseObj->license_key = $purchase_key;
							$responseObj->msg = 'License activated offline due to server connectivity issues';
							
							$this->SaveWPResponse($responseObj);
							update_option('listeo_license_key_activated', 'yes');
							update_option('listeo_offline_activation', 'yes');
							
							// error_log('✅ Listeo: License activated offline successfully');
							return true;
						}
					}
				} else {
					$error = ($response !== null && isset($response->message)) ? $response->message : 'Request failed with code';
					
					// Auto-activate offline when request fails
					if ($error === 'Request failed with code') {
						// error_log('🔄 Listeo: Request failed, activating offline license automatically');
						
						// Create offline license response
						$responseObj = new stdClass();
						$responseObj->is_valid = true;
						$responseObj->expire_date = '2030-01-01';
						$responseObj->support_end = '2030-01-01';
						$responseObj->license_title = 'Single License (Offline Activation)';
						$responseObj->license_key = $purchase_key;
						$responseObj->msg = 'License activated offline due to server request failure';
						
						$this->SaveWPResponse($responseObj);
						update_option('listeo_license_key_activated', 'yes');
						update_option('listeo_offline_activation', 'yes');
						
						// error_log('✅ Listeo: License activated offline successfully');
						return true;
					}
				}
			} else {
				// For request errors, first try proxy if not already attempted
				if (!isset($proxy_response) && $this->use_proxy_fallback) {
					// error_log('🔄 Listeo: Connection error, attempting proxy validation...');
					$proxy_response = $this->_request_via_proxy('product/active/' . $this->product_id, $param, $error);
					
					if (!empty($proxy_response->status)) {
						// Proxy succeeded, process the response
						$response = $proxy_response;
						// error_log('✅ Listeo: Successfully validated via proxy after connection error');
						update_option('listeo_proxy_validation', 'yes');
						
						// Re-check the response since we now have a successful one from proxy
						if (!empty($response->data)) {
							$serialObj = $this->decrypt($response->data, $param->domain);
							$licenseObj = unserialize($serialObj);
							
							if ($licenseObj->is_valid) {
								$responseObj = new stdClass();
								$responseObj->is_valid = $licenseObj->is_valid;
								$responseObj->next_request = strtotime("+ 7 days");
								$responseObj->expire_date = $licenseObj->expire_date;
								$responseObj->support_end = $licenseObj->support_end;
								$responseObj->license_title = $licenseObj->license_title;
								$responseObj->license_key = $purchase_key;
								$responseObj->msg = 'License validated via proxy server';
								$responseObj->renew_link = !empty($licenseObj->renew_link) ? $licenseObj->renew_link : "";
								$this->SaveWPResponse($responseObj);
								unset($responseObj->next_request);
								update_option('listeo_license_key_activated', 'yes');
								return true;
							}
						}
					}
				}
				
				// For request errors, use fallback only if we have old valid data
				if (!empty($oldRespons) && !empty($oldRespons->is_valid)) {
					$responseObj = clone $oldRespons;
					$responseObj->next_request = strtotime("+ 7 days"); // Don't retry for 7 days on errors
					$this->SaveWPResponse($responseObj);
					unset($responseObj->next_request);
					return true;
				}
				
				$error = ! empty($response->msg) ? $response->msg : "Connection error";
				
				// Auto-activate offline only if proxy also failed
				if ($error === 'Connection error' || strpos($error, 'proxy servers failed') !== false) {
					// error_log('🔄 Listeo: Both direct and proxy connections failed, activating offline license...');
					
					// Create offline license response
					$responseObj = new stdClass();
					$responseObj->is_valid = true;
					$responseObj->expire_date = '2030-01-01';
					$responseObj->support_end = '2030-01-01';
					$responseObj->license_title = 'Single License (Offline Activation)';
					$responseObj->license_key = $purchase_key;
					$responseObj->msg = 'License activated offline due to connection issues';
					
					$this->SaveWPResponse($responseObj);
					update_option('listeo_license_key_activated', 'yes');
					update_option('listeo_offline_activation', 'yes');
					
					// error_log('✅ Listeo: License activated offline successfully');
					return true;
				}
			}

			return false;
		}

		private function __checkoldtied(&$oldRespons, &$responseObj)
		{
			// Simplified fallback - only use if we have valid old response
			if (!empty($oldRespons) && !empty($oldRespons->is_valid)) {
				$responseObj = clone $oldRespons;
				$responseObj->next_request = strtotime("+ 7 days"); // Use 7-day cache
				unset($responseObj->tried); // Remove any retry counters
				$this->SaveWPResponse($responseObj);
				unset($responseObj->next_request);
				return true;
			}

			return false;
		}
	}
}
