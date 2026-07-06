<?php
/**
 * Listeo Core — Add-ons Dashboard admin page.
 *
 * Renders a grid of available add-ons, driven entirely by the catalog
 * (remote dashboard.json with option cache + bundled fallback).
 *
 * Phase 1 scope: render-only. License/install integration arrives in
 * Phase 2 once the verify endpoint is published.
 *
 * @package Listeo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listeo_Core_Addons_Dashboard {

	const PAGE_SLUG              = 'listeo_addons';
	const THEME_MANIFEST_URL     = 'https://purethe.me/themes/listeo.json';
	const THEME_STATUS_OPTION    = 'listeo_core_dashboard_theme_status';
	const SUPPORT_STATUS_PREFIX  = 'listeo_core_dashboard_support_status_';
	const ADDON_ICON_OPTION      = 'listeo_core_dashboard_addon_icon_urls';
	const ADDON_ICON_TTL         = 2592000; // 30 days.
	const SUPPORT_STATUS_TTL     = 2592000; // 30 days.
	const SUPPORT_STATUS_API_URL = 'https://purethe.me/wp-json/purethemes-license-proxy/v1/listeo-support-status';
	const THEME_ICON_RELATIVE    = 'resources/listeo-dashboard-icon.png';
	const ADDON_ICON_DIR         = 'resources/listeo-addons';

	/**
	 * Page hook returned by add_submenu_page() — used to gate asset loading.
	 *
	 * @var string|false
	 */
	protected $page_hook = false;

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'update_option_Listeo_lic_Key', array( $this, 'flush_support_status_cache' ) );
		add_action( 'delete_option_Listeo_lic_Key', array( $this, 'flush_support_status_cache' ) );
	}

	/**
	 * Store the top-level page hook when the dashboard owns the parent menu.
	 *
	 * @param string|false $page_hook Page hook returned by add_menu_page().
	 */
	public function set_page_hook( $page_hook ) {
		$this->page_hook = $page_hook;
	}

	/**
	 * Register the submenu page. Called from Listeo_Core_Admin::add_menu_item()
	 * BEFORE all other add_submenu_page calls so the Add-ons item shows first.
	 *
	 * @param string $parent_slug Parent menu slug.
	 */
	public function register_submenu( $parent_slug = 'listeo_settings' ) {
		$this->page_hook = add_submenu_page(
			$parent_slug,
			__( 'Listeo Core', 'listeo_core' ),
			__( 'Listeo Core', 'listeo_core' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue page-specific CSS, gated on screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->page_hook || $hook !== $this->page_hook ) {
			return;
		}

		$base_css_path = LISTEO_PLUGIN_DIR . 'assets/css/listeo-modern-admin.css';
		$css_path      = LISTEO_PLUGIN_DIR . 'assets/css/listeo-addons.css';
		$js_path       = LISTEO_PLUGIN_DIR . 'assets/js/listeo-addons.js';

		wp_enqueue_style(
			'listeo-modern-admin',
			LISTEO_CORE_URL . 'assets/css/listeo-modern-admin.css',
			array(),
			file_exists( $base_css_path ) ? filemtime( $base_css_path ) : null
		);

		wp_enqueue_style(
			'listeo-addons',
			LISTEO_CORE_URL . 'assets/css/listeo-addons.css',
			array( 'listeo-modern-admin' ),
			file_exists( $css_path ) ? filemtime( $css_path ) : null
		);

		wp_enqueue_script(
			'listeo-addons',
			LISTEO_CORE_URL . 'assets/js/listeo-addons.js',
			array(),
			file_exists( $js_path ) ? filemtime( $js_path ) : null,
			true
		);

		wp_localize_script(
			'listeo-addons',
			'listeoAddons',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'installAction'   => Listeo_Core_Addons_Installer::AJAX_ACTION,
				'installNonce'    => wp_create_nonce( Listeo_Core_Addons_Installer::NONCE_NAME ),
				'hasLicense'      => $this->has_license_key(),
				'licenseUrl'      => admin_url( 'admin.php?page=listeo_license' ),
				'i18n'            => array(
					'copied'           => __( 'Copied!', 'listeo_core' ),
					'copy'             => __( 'Copy', 'listeo_core' ),
					'installing'       => __( 'Installing…', 'listeo_core' ),
					'installSuccess'   => __( 'Installed!', 'listeo_core' ),
					'activate'         => __( 'Activate', 'listeo_core' ),
					'active'           => __( 'Active', 'listeo_core' ),
					'install'          => __( 'Install', 'listeo_core' ),
					'genericError'     => __( 'Something went wrong. Please try again.', 'listeo_core' ),
						'licensePrompt'    => __( 'Activate your Listeo license first.', 'listeo_core' ),
						'goToLicense'      => __( 'Go to License', 'listeo_core' ),
						'learnMore'        => __( 'Learn more', 'listeo_core' ),
					),
				)
			);
	}

	/**
	 * Whether the user can attempt to install add-ons — i.e. a license key
	 * is recorded AND the licenser's persistent cache says it's valid.
	 *
	 * The licenser (theme/listeo/inc/licenser.php) populates a 7-day cache
	 * keyed by site_url + key + email under option name
	 * `listeo_license_cache_<md5>`. We mirror that read here so a disabled
	 * license doesn't show as "activated" on the Add-ons page.
	 *
	 * Staging environments bypass validation (matches licenser behavior).
	 *
	 * @return bool
	 */
	protected function has_license_key() {
		return $this->is_license_valid();
	}

	/**
	 * Read the licenser's persistent cache. Returns the cached array (with
	 * keys is_valid, response, message) or null if nothing's cached yet.
	 *
	 * @return array|null
	 */
	protected function get_license_cache() {
		$key   = trim( (string) get_option( 'Listeo_lic_Key', '' ) );
		$email = trim( (string) get_option( 'Listeo_lic_email', '' ) );
		if ( '' === $key ) {
			return null;
		}
		$cache_key = 'listeo_license_cache_' . md5( site_url() . $key . $email );

		if ( class_exists( 'b472b0Base' ) && method_exists( 'b472b0Base', 'GetPersistentCache' ) ) {
			$cached = b472b0Base::GetPersistentCache( $cache_key );
			return is_array( $cached ) ? $cached : null;
		}

		$cached = get_option( $cache_key, null );
		if ( is_array( $cached ) && array_key_exists( 'value', $cached ) && ! empty( $cached['expires_at'] ) ) {
			if ( (int) $cached['expires_at'] <= time() ) {
				return null;
			}

			return is_array( $cached['value'] ) ? $cached['value'] : null;
		}

		return is_array( $cached ) && array_key_exists( 'is_valid', $cached ) ? $cached : null;
	}

	/**
	 * Is the license valid right now (per the licenser's cache)?
	 *
	 * @return bool
	 */
	protected function is_license_valid() {
		// Staging bypass — same condition the licenser uses.
		if ( function_exists( 'listeo_is_staging_environment' ) && listeo_is_staging_environment() ) {
			return true;
		}

		$cache = $this->get_license_cache();
		if ( null === $cache ) {
			return false;
		}
		return ! empty( $cache['is_valid'] );
	}

	/**
	 * Render the dashboard page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config         = Listeo_Core_Addons_Catalog::get_config();
			$license_status = $this->get_license_status();
			$theme_status   = $this->get_theme_update_status();
			$support_status = $this->get_support_status();

			$license_ok        = $this->is_license_valid();
			$license_admin_url = admin_url( 'admin.php?page=listeo_license' );

		// Sort + decorate every add-on with the local install state and the
		// derived "filter state" we surface in the UI (active / inactive /
		// available / paid). The license_ok flag rides on each card so the
		// template can disable Install buttons without re-querying.
		$addons = array();
		foreach ( $this->sort_addons( $config['addons'] ) as $addon ) {
			$addon['install_state'] = Listeo_Core_Addons_Catalog::get_install_state( $addon['plugin_file'] );
			$addon['filter_state']  = $this->derive_filter_state( $addon );
			$addon['license_ok']    = $license_ok;
			$addon['license_url']   = $license_admin_url;
			$addons[] = $addon;
		}
		$counts = $this->count_by_filter_state( $addons );
		?>
		<div class="wrap lba-root">

			<?php // wp-header-end keeps admin notices out of the design card. ?>
			<hr class="wp-header-end" />

			<header class="lba-pagehead">
				<div class="lba-pagehead__title">
					<span class="lba-pagehead__icon">
						<img src="<?php echo esc_url( self::get_theme_dashboard_icon_url() ); ?>" alt="" />
					</span>
					<div>
						<h1><?php esc_html_e( 'Listeo Core', 'listeo_core' ); ?></h1>
						<p><?php esc_html_e( 'Manage your Listeo installation, updates, support, and official add-ons.', 'listeo_core' ); ?></p>
					</div>
				</div>
			</header>

			<?php if ( ! empty( $config['global_banner']['active'] ) ) : ?>
				<div class="lba-banner">
					<p><?php echo esc_html( $config['global_banner']['message'] ); ?></p>
					<?php if ( ! empty( $config['global_banner']['cta_url'] ) && ! empty( $config['global_banner']['cta_text'] ) ) : ?>
						<a class="lba-btn lba-btn--primary" href="<?php echo esc_url( $config['global_banner']['cta_url'] ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $config['global_banner']['cta_text'] ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<section class="lba-section" aria-labelledby="lba-summary-heading">
				<h2 id="lba-summary-heading" class="lba-section__title"><?php esc_html_e( 'Summary', 'listeo_core' ); ?></h2>
				<div class="lba-summary">
					<?php $this->render_license_summary_card( $license_status ); ?>
					<?php $this->render_theme_summary_card( $theme_status ); ?>
					<?php $this->render_support_summary_card( $support_status ); ?>
				</div>
			</section>

			<?php if ( empty( $addons ) ) : ?>
				<div class="lba-empty">
					<p><?php esc_html_e( 'No add-ons available right now. Please try refreshing the catalog.', 'listeo_core' ); ?></p>
				</div>
			<?php else : ?>

					<section class="lba-section" aria-labelledby="lba-addons-heading">
						<h2 id="lba-addons-heading" class="lba-section__title"><?php esc_html_e( 'Add-ons', 'listeo_core' ); ?></h2>
						<nav class="lba-filters" aria-label="<?php esc_attr_e( 'Filter add-ons', 'listeo_core' ); ?>">
						<button type="button" class="lba-pill lba-pill--on" data-filter="all">
							<?php esc_html_e( 'All add-ons', 'listeo_core' ); ?> <em><?php echo (int) $counts['all']; ?></em>
						</button>
					<?php if ( $counts['active'] > 0 ) : ?>
						<button type="button" class="lba-pill" data-filter="active">
							<?php esc_html_e( 'Active', 'listeo_core' ); ?> <em><?php echo (int) $counts['active']; ?></em>
						</button>
					<?php endif; ?>
					<?php if ( $counts['inactive'] > 0 ) : ?>
						<button type="button" class="lba-pill" data-filter="inactive">
							<?php esc_html_e( 'Inactive', 'listeo_core' ); ?> <em><?php echo (int) $counts['inactive']; ?></em>
						</button>
					<?php endif; ?>
					<?php if ( $counts['available'] > 0 ) : ?>
						<button type="button" class="lba-pill" data-filter="available">
							<?php esc_html_e( 'Available', 'listeo_core' ); ?> <em><?php echo (int) $counts['available']; ?></em>
						</button>
					<?php endif; ?>
					<?php if ( $counts['paid'] > 0 ) : ?>
						<button type="button" class="lba-pill lba-pill--prem" data-filter="paid">
							<?php echo self::icon( 'spark', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php esc_html_e( 'Premium', 'listeo_core' ); ?> <em><?php echo (int) $counts['paid']; ?></em>
						</button>
					<?php endif; ?>
					</nav>

					<div class="lba-grid">
						<?php foreach ( $addons as $addon ) {
							$this->render_card( $addon );
						} ?>
					</div>
				</section>
			<?php endif; ?>

			<?php $this->render_help_support_section(); ?>

		</div>
		<?php
	}

	/**
	 * Reduce an add-on to its UI filter bucket.
	 *
	 * @param array $addon Normalized + install_state-decorated.
	 * @return string One of: active | inactive | available | paid.
	 */
	protected function derive_filter_state( $addon ) {
		if ( 'active' === $addon['install_state'] ) {
			return 'active';
		}
		if ( 'inactive' === $addon['install_state'] ) {
			return 'inactive';
		}
		if ( ! empty( $addon['type'] ) && 'paid_separate' === $addon['type'] ) {
			return 'paid';
		}
		return 'available';
	}

	/**
	 * Count add-ons per filter state.
	 *
	 * @param array $addons
	 * @return array { all:int, active:int, inactive:int, available:int, paid:int }
	 */
	protected function count_by_filter_state( $addons ) {
		$counts = array(
			'all'       => count( $addons ),
			'active'    => 0,
			'inactive'  => 0,
			'available' => 0,
			'paid'      => 0,
		);
		foreach ( $addons as $addon ) {
			$bucket = $addon['filter_state'];
			if ( isset( $counts[ $bucket ] ) ) {
				$counts[ $bucket ]++;
			}
		}
		return $counts;
	}

	/**
	 * Read a non-transient option cache.
	 *
	 * @param string $option Option name.
	 * @return array|null Cached value.
	 */
	protected function get_option_cache( $option ) {
		$cached = get_option( $option );
		if ( ! is_array( $cached ) || ! array_key_exists( 'value', $cached ) || empty( $cached['expires_at'] ) ) {
			return null;
		}

		if ( (int) $cached['expires_at'] <= time() ) {
			return null;
		}

		return is_array( $cached['value'] ) ? $cached['value'] : null;
	}

	/**
	 * Read the raw option cache, including expired values.
	 *
	 * @param string $option Option name.
	 * @return array|null Cache wrapper.
	 */
	protected function get_raw_option_cache( $option ) {
		$cached = get_option( $option );
		if ( ! is_array( $cached ) || ! array_key_exists( 'value', $cached ) ) {
			return null;
		}

		return $cached;
	}

	/**
	 * Store a non-autoloaded option cache.
	 *
	 * @param string $option Option name.
	 * @param array  $value Cache value.
	 * @param int    $ttl Cache TTL in seconds.
	 */
	protected function set_option_cache( $option, $value, $ttl ) {
		update_option(
			$option,
			array(
				'value'      => $value,
				'created_at' => time(),
				'expires_at' => time() + max( DAY_IN_SECONDS, (int) $ttl ),
			),
			false
		);
	}

	/**
	 * Clear all support-status option caches.
	 */
	public function flush_support_status_cache() {
		global $wpdb;

		if ( empty( $wpdb ) ) {
			return;
		}

		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::SUPPORT_STATUS_PREFIX ) . '%'
			)
		);

		foreach ( (array) $option_names as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Fetch and cache the Listeo theme manifest without using transients.
	 *
	 * @return array|null Normalized manifest data.
	 */
	protected function get_theme_manifest() {
		$cached = $this->get_option_cache( self::THEME_STATUS_OPTION );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$raw_cache = $this->get_raw_option_cache( self::THEME_STATUS_OPTION );
		$stale     = is_array( $raw_cache ) && isset( $raw_cache['value'] ) && is_array( $raw_cache['value'] ) ? $raw_cache['value'] : null;

		$response = wp_remote_get(
			self::THEME_MANIFEST_URL,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( is_array( $stale ) ) {
				$stale['stale'] = true;
				$this->set_option_cache( self::THEME_STATUS_OPTION, $stale, DAY_IN_SECONDS );
				return $stale;
			}

			$failed = array(
				'error' => true,
			);
			$this->set_option_cache( self::THEME_STATUS_OPTION, $failed, DAY_IN_SECONDS );
			return $failed;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			$failed = is_array( $stale ) ? $stale : array( 'error' => true );
			$failed['stale'] = is_array( $stale );
			$this->set_option_cache( self::THEME_STATUS_OPTION, $failed, DAY_IN_SECONDS );
			return $failed;
		}

		$manifest = array(
			'version'      => isset( $decoded['version'] ) ? sanitize_text_field( (string) $decoded['version'] ) : '',
			'homepage'     => isset( $decoded['homepage'] ) ? esc_url_raw( (string) $decoded['homepage'] ) : '',
			'last_updated' => isset( $decoded['last_updated'] ) ? sanitize_text_field( (string) $decoded['last_updated'] ) : '',
		);

		$this->set_option_cache( self::THEME_STATUS_OPTION, $manifest, DAY_IN_SECONDS );
		return $manifest;
	}

	/**
	 * Resolve the theme update card state.
	 *
	 * @return array
	 */
	protected function get_theme_update_status() {
		$theme           = wp_get_theme( 'listeo' );
		$current_version = $theme && $theme->exists() ? $theme->get( 'Version' ) : '';
		if ( '' === $current_version ) {
			$current_theme   = wp_get_theme();
			$current_version = $current_theme ? $current_theme->get( 'Version' ) : '';
		}

		$manifest       = $this->get_theme_manifest();
		$latest_version = is_array( $manifest ) && ! empty( $manifest['version'] ) ? $manifest['version'] : '';

		if ( '' === $current_version || '' === $latest_version || ! empty( $manifest['error'] ) ) {
			return array(
				'tone'    => 'warn',
				'label'   => __( 'Not checked', 'listeo_core' ),
				'version' => $current_version,
				'text'    => __( 'Theme update status is temporarily unavailable.', 'listeo_core' ),
				'detail'  => __( 'The result is cached for one day to avoid repeated remote checks.', 'listeo_core' ),
			);
		}

		if ( version_compare( $current_version, $latest_version, '<' ) ) {
			return array(
				'tone'       => 'warn',
				'label'      => __( 'Update Available', 'listeo_core' ),
				'version'    => $current_version,
				'text'       => sprintf(
					/* translators: 1: latest theme version, 2: current theme version. */
					__( 'Listeo %1$s is available. You are using %2$s.', 'listeo_core' ),
					$latest_version,
					$current_version
				),
				'detail'     => __( 'Keep your theme updated to ensure that your website is running smoothly.', 'listeo_core' ),
				'button_url' => admin_url( 'update-core.php' ),
				'button'     => __( 'View updates', 'listeo_core' ),
			);
		}

		return array(
			'tone'    => 'ok',
			'label'   => __( 'Latest Version', 'listeo_core' ),
			'version' => $current_version,
			'text'    => __( 'You are using the latest theme version.', 'listeo_core' ),
			'detail'  => __( 'Keep your theme updated to ensure that your website is running smoothly.', 'listeo_core' ),
		);
	}

	/**
	 * Build the support status cache key for the active license.
	 *
	 * @param string $license_key License key.
	 * @return string
	 */
	protected function get_support_status_cache_key( $license_key ) {
		return self::SUPPORT_STATUS_PREFIX . md5( site_url() . $license_key . '|v3' );
	}

	/**
	 * Resolve support status from cache or one guarded remote lookup.
	 *
	 * @return array
	 */
	protected function get_support_status() {
		$license_key = trim( (string) get_option( 'Listeo_lic_Key', '' ) );
		if ( '' === $license_key ) {
			return array(
				'tone'       => 'danger',
				'label'      => __( 'Not Registered', 'listeo_core' ),
				'text'       => __( 'Activate your license to check support status.', 'listeo_core' ),
				'detail'     => __( 'Support status is tied to the registered license key.', 'listeo_core' ),
				'button_url' => admin_url( 'admin.php?page=listeo_license' ),
				'button'     => __( 'Activate license', 'listeo_core' ),
			);
		}

		$cache_key = $this->get_support_status_cache_key( $license_key );
		$cached    = $this->get_option_cache( $cache_key );
		if ( is_array( $cached ) ) {
			return $this->decorate_support_status( $cached, $license_key );
		}

		$remote = $this->fetch_support_status( $license_key );
		if ( is_array( $remote ) ) {
			$this->set_option_cache( $cache_key, $remote, self::SUPPORT_STATUS_TTL );
			return $this->decorate_support_status( $remote, $license_key );
		}

		$unknown = array(
			'valid'         => null,
			'support_valid' => null,
			'checked_at'    => time(),
		);
		$this->set_option_cache( $cache_key, $unknown, 7 * DAY_IN_SECONDS );
		return $this->decorate_support_status( $unknown, $license_key );
	}

	/**
	 * Fetch support status via the public read-only proxy API.
	 * The result is cached by get_support_status().
	 *
	 * @param string $license_key License key.
	 * @return array|null
	 */
	protected function fetch_support_status( $license_key ) {
		$response = wp_remote_post(
			self::SUPPORT_STATUS_API_URL,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'license_key' => sanitize_text_field( $license_key ),
						'site_url'    => site_url(),
					)
				),
			)
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $decoded ) ) {
				return $this->normalize_support_payload( $decoded, ! empty( $decoded['valid'] ) );
			}
		}

		return null;
	}

	/**
	 * Normalize support metadata returned by either proxy API shape.
	 *
	 * @param array|object $payload Raw payload.
	 * @param bool|null    $is_valid License validity.
	 * @return array|null
	 */
	protected function normalize_support_payload( $payload, $is_valid = null ) {
		if ( is_object( $payload ) ) {
			$payload = get_object_vars( $payload );
		}
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$support_value           = '';
		$has_explicit_support_at = ! empty( $payload['support_expires_at'] );
		if ( ! empty( $payload['support_end'] ) ) {
			$support_value = sanitize_text_field( (string) $payload['support_end'] );
		} elseif ( ! empty( $payload['support_expires_at'] ) ) {
			$support_value = sanitize_text_field( (string) $payload['support_expires_at'] );
		}

		if ( '2030-01-01' === substr( $support_value, 0, 10 ) && ! $has_explicit_support_at ) {
			$support_value = '';
		}

		$support_valid = null;
		if ( array_key_exists( 'support_valid', $payload ) && null !== $payload['support_valid'] ) {
			$support_valid = (bool) $payload['support_valid'];
		}
		$timestamp     = 0;
		$normalized    = '';

		if ( '' !== $support_value ) {
			$lower = strtolower( $support_value );
			if ( in_array( $lower, array( 'unlimited', 'no expiry' ), true ) ) {
				$support_valid = true;
				$normalized    = $support_value;
			} elseif ( in_array( $lower, array( 'no support', 'expired' ), true ) ) {
				$support_valid = false;
				$normalized    = $support_value;
			} else {
				$timestamp = strtotime( $support_value . ' UTC' );
				if ( $timestamp ) {
					$support_valid = null === $support_valid ? $timestamp >= time() : $support_valid;
					$normalized    = gmdate( 'Y-m-d', $timestamp );
				}
			}
		}

		return array(
			'valid'              => null === $is_valid ? null : (bool) $is_valid,
			'support_end'        => $normalized,
			'support_timestamp'  => $timestamp,
			'support_valid'      => $support_valid,
			'status'             => isset( $payload['status'] ) ? sanitize_key( (string) $payload['status'] ) : '',
			'checked_at'         => time(),
		);
	}

	/**
	 * Decorate normalized support data for rendering.
	 *
	 * @param array  $status Normalized status.
	 * @param string $license_key License key.
	 * @return array
	 */
	protected function decorate_support_status( $status, $license_key ) {
		$renew_url = add_query_arg(
			'purchase',
			$license_key,
			'https://purethemes.net/license/'
		);

		if ( isset( $status['support_valid'] ) && true === $status['support_valid'] ) {
			$support_label = $this->format_support_end_label( $status );
			return array(
				'tone'       => 'ok',
				'label'      => __( 'Active', 'listeo_core' ),
				'text'       => $support_label
					? sprintf(
						/* translators: %s: support expiration date. */
						__( 'Your support is active until %s.', 'listeo_core' ),
						$support_label
					)
					: __( 'Your support subscription is active.', 'listeo_core' ),
				'detail'     => __( 'Need more time? You can renew support from your license page.', 'listeo_core' ),
				'button_url' => $renew_url,
				'button'     => __( 'Manage support', 'listeo_core' ),
			);
		}

		if ( isset( $status['support_valid'] ) && false === $status['support_valid'] ) {
			return array(
				'tone'       => 'danger',
				'label'      => __( 'Expired', 'listeo_core' ),
				'text'       => __( 'Your support subscription has expired.', 'listeo_core' ),
				'detail'     => __( 'Renew your support for 12 new months.', 'listeo_core' ),
				'button_url' => $renew_url,
				'button'     => __( 'Renew support', 'listeo_core' ),
			);
		}

		return array(
			'tone'       => 'warn',
			'label'      => __( 'Not checked', 'listeo_core' ),
			'text'       => __( 'Support status is not available yet.', 'listeo_core' ),
			'detail'     => __( 'The result is cached for a long period to avoid repeated license server checks.', 'listeo_core' ),
			'button_url' => $renew_url,
			'button'     => __( 'Manage support', 'listeo_core' ),
		);
	}

	/**
	 * Format the support end date using the site's date format.
	 *
	 * @param array $status Normalized support status.
	 * @return string
	 */
	protected function format_support_end_label( $status ) {
		if ( ! empty( $status['support_timestamp'] ) ) {
			return date_i18n( get_option( 'date_format' ), (int) $status['support_timestamp'] );
		}

		if ( ! empty( $status['support_end'] ) ) {
			return sanitize_text_field( (string) $status['support_end'] );
		}

		return '';
	}

	/**
	 * Render the License Settings summary card.
	 *
	 * @param array $license_status Header license status.
	 */
	protected function render_license_summary_card( $license_status ) {
		$is_ok      = isset( $license_status['tone'] ) && 'ok' === $license_status['tone'];
		$license_key = trim( (string) get_option( 'Listeo_lic_Key', '' ) );
		$button_url  = $license_key ? $this->get_license_management_url( $license_key ) : admin_url( 'admin.php?page=listeo_license' );
		$is_external = 0 === strpos( $button_url, 'https://purethemes.net/' );
		?>
		<div class="lba-panel lba-panel--<?php echo esc_attr( $license_status['tone'] ); ?>">
			<h3>
				<span class="lba-panel__icon"><?php echo self::icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<?php esc_html_e( 'License Settings', 'listeo_core' ); ?>
			</h3>
			<p>
				<span class="lba-status-text lba-status-text--<?php echo esc_attr( $is_ok ? 'active' : 'expired' ); ?>">
					<?php echo esc_html( $is_ok ? __( 'Your license is registered.', 'listeo_core' ) : $license_status['label'] ); ?>
				</span>
			</p>
			<hr>
			<p><?php echo esc_html( $is_ok ? __( 'Want to deactivate or manage the license for any reason?', 'listeo_core' ) : __( 'Open the license page to activate or verify your license.', 'listeo_core' ) ); ?></p>
			<a class="lba-btn lba-btn--secondary" href="<?php echo esc_url( $button_url ); ?>"<?php echo $is_external ? ' target="_blank" rel="noopener"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php esc_html_e( 'Manage license', 'listeo_core' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Build the PureThemes license management URL.
	 *
	 * @param string $license_key License key.
	 * @return string
	 */
	protected function get_license_management_url( $license_key ) {
		return add_query_arg(
			'purchase',
			$license_key,
			'https://purethemes.net/license/'
		);
	}

	/**
	 * Render the Theme Updates summary card.
	 *
	 * @param array $theme_status Theme update status.
	 */
	protected function render_theme_summary_card( $theme_status ) {
		$badge_text = ! empty( $theme_status['version'] ) ? sanitize_text_field( (string) $theme_status['version'] ) : $theme_status['label'];
		?>
		<div class="lba-panel lba-panel--<?php echo esc_attr( $theme_status['tone'] ); ?>">
			<h3>
				<span class="lba-panel__icon"><?php echo self::icon( 'update', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<?php esc_html_e( 'Theme Updates', 'listeo_core' ); ?>
			</h3>
			<p>
				<span class="lba-status-text lba-status-text--<?php echo esc_attr( 'ok' === $theme_status['tone'] ? 'active' : 'warning' ); ?>">
					<?php echo esc_html( $theme_status['text'] ); ?>
				</span>
			</p>
			<hr>
			<p><?php echo esc_html( $theme_status['detail'] ); ?></p>
			<span class="lba-support-badge lba-support-badge--<?php echo esc_attr( 'ok' === $theme_status['tone'] ? 'active' : 'warning' ); ?>">
				<?php echo esc_html( $badge_text ); ?>
			</span>
			<?php if ( ! empty( $theme_status['button_url'] ) && ! empty( $theme_status['button'] ) ) : ?>
				<a class="lba-btn lba-btn--secondary" href="<?php echo esc_url( $theme_status['button_url'] ); ?>">
					<?php echo esc_html( $theme_status['button'] ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Support Status summary card.
	 *
	 * @param array $support_status Support status.
	 */
	protected function render_support_summary_card( $support_status ) {
		$badge_class = 'ok' === $support_status['tone'] ? 'active' : ( 'danger' === $support_status['tone'] ? 'expired' : 'warning' );
		$is_external = ! empty( $support_status['button_url'] ) && 0 === strpos( $support_status['button_url'], 'https://purethemes.net/' );
		?>
		<div class="lba-panel lba-panel--<?php echo esc_attr( $support_status['tone'] ); ?>">
			<h3>
				<span class="lba-panel__icon"><?php echo self::icon( 'life', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<?php esc_html_e( 'Support Status', 'listeo_core' ); ?>
			</h3>
			<p>
				<span class="lba-status-text lba-status-text--<?php echo esc_attr( $badge_class ); ?>">
					<?php echo esc_html( $support_status['text'] ); ?>
				</span>
			</p>
			<hr>
			<p><?php echo esc_html( $support_status['detail'] ); ?></p>
			<?php if ( ! empty( $support_status['button_url'] ) && ! empty( $support_status['button'] ) ) : ?>
				<a class="lba-btn lba-btn--secondary" href="<?php echo esc_url( $support_status['button_url'] ); ?>"<?php echo $is_external ? ' target="_blank" rel="noopener"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo esc_html( $support_status['button'] ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Help & Support section.
	 */
	protected function render_help_support_section() {
		$links = array(
			array(
				'icon'  => 'book',
				'title' => __( 'View Documentation', 'listeo_core' ),
				'text'  => __( 'Helpful information about theme setup, capabilities, features, and options.', 'listeo_core' ),
				'url'   => 'https://docs.purethemes.net/listeo/',
				'cta'   => __( 'Read documentation', 'listeo_core' ),
			),
			array(
				'icon'  => 'life',
				'title' => __( 'Support Center', 'listeo_core' ),
				'text'  => __( 'Got a question or need help with the theme? You can submit a support ticket.', 'listeo_core' ),
				'url'   => 'https://docs.purethemes.net/listeo/support/',
				'cta'   => __( 'Submit a ticket', 'listeo_core' ),
			),
			array(
				'icon'  => 'users',
				'title' => __( 'Join the Community', 'listeo_core' ),
				'text'  => __( 'Share your expertise, seek guidance, and connect with other Listeo users.', 'listeo_core' ),
				'url'   => 'https://www.facebook.com/groups/856478084819791/',
				'cta'   => __( 'Join now', 'listeo_core' ),
			),
			array(
				'icon'  => 'health',
				'title' => __( 'Listeo Site Health', 'listeo_core' ),
				'text'  => __( 'Review Listeo diagnostics, required pages, email checks, and configuration status.', 'listeo_core' ),
				'url'   => add_query_arg( 'tab', 'listeo-site-health-tab', admin_url( 'site-health.php' ) ),
				'cta'   => __( 'Open health check', 'listeo_core' ),
			),
			array(
				'icon'  => 'spark',
				'title' => __( 'AI Assistant', 'listeo_core' ),
				'text'  => __( 'Learn how to configure and use Listeo AI Assistant features.', 'listeo_core' ),
				'url'   => 'https://docs.purethemes.net/listeo/ai-assistant/',
				'cta'   => __( 'View AI Assistant docs', 'listeo_core' ),
			),
		);
		?>
		<section class="lba-section lba-help" aria-labelledby="lba-help-heading">
			<h2 id="lba-help-heading" class="lba-section__title"><?php esc_html_e( 'Help & Support', 'listeo_core' ); ?></h2>
			<div class="lba-help-grid">
				<?php foreach ( $links as $link ) : ?>
					<div class="lba-panel lba-help-card">
						<h3>
							<span class="lba-panel__icon"><?php echo self::icon( $link['icon'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<?php echo esc_html( $link['title'] ); ?>
						</h3>
						<p><?php echo esc_html( $link['text'] ); ?></p>
						<?php
						$link_host  = wp_parse_url( $link['url'], PHP_URL_HOST );
						$admin_host = wp_parse_url( admin_url(), PHP_URL_HOST );
						$is_external = $link_host && $admin_host && strtolower( $link_host ) !== strtolower( $admin_host );
						?>
						<a class="lba-btn lba-btn--secondary" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $is_external ? ' target="_blank" rel="noopener"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo esc_html( $link['cta'] ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Inline SVG icon helper. Keeps the icon set in one place and avoids a
	 * separate sprite file. All icons inherit currentColor.
	 *
	 * @param string $name Icon name.
	 * @param int    $size Pixel size (square).
	 * @return string SVG markup.
	 */
	public static function icon( $name, $size = 18 ) {
		$paths = array(
			'sliders' => array( 'M4 21v-7', 'M4 10V3', 'M12 21v-9', 'M12 8V3', 'M20 21v-5', 'M20 12V3', 'M1 14h6', 'M9 8h6', 'M17 16h6' ),
			'shield'  => array( 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'M9 12l2 2 4-4' ),
			'refresh' => array( 'M23 4v6h-6', 'M1 20v-6h6', 'M3.5 9a9 9 0 0 1 14.9-3.4L23 10', 'M1 14l4.6 4.4A9 9 0 0 0 20.5 15' ),
			'spark'   => array( 'M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5 10.1 11.9 4.5 10l5.6-1.4L12 3Z' ),
			'check'   => array( 'M20 6 9 17l-5-5' ),
			'power'   => array( 'M12 2v10', 'M18.4 6.6a9 9 0 1 1-12.8 0' ),
			'down'    => array( 'M12 3v12', 'M7 10l5 5 5-5', 'M5 21h14' ),
			'bag'     => array( 'M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z', 'M3 6h18', 'M16 10a4 4 0 0 1-8 0' ),
			'book'    => array( 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20', 'M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5Z' ),
			'life'    => array( 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M4.9 4.9l4.2 4.2', 'M14.9 14.9l4.2 4.2', 'M14.9 9.1l4.2-4.2', 'M4.9 19.1l4.2-4.2', 'M9 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z' ),
			'users'   => array( 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75' ),
			'update'  => array( 'M21 12a9 9 0 1 1-2.64-6.36', 'M21 3v6h-6' ),
			'health'  => array( 'M22 12h-4l-3 8-6-16-3 8H2' ),
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		$size = (int) $size;
		$svg  = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
		foreach ( $paths[ $name ] as $d ) {
			$svg .= '<path d="' . esc_attr( $d ) . '" />';
		}
		$svg .= '</svg>';
		return $svg;
	}

	/**
	 * Local Listeo image used for dashboard branding and generic add-on fallback.
	 *
	 * @return string
	 */
	public static function get_theme_dashboard_icon_url() {
		$path = trailingslashit( get_template_directory() ) . self::THEME_ICON_RELATIVE;
		if ( file_exists( $path ) ) {
			return trailingslashit( get_template_directory_uri() ) . self::THEME_ICON_RELATIVE;
		}

		return LISTEO_CORE_URL . 'templates/images/listeo_placeholder.png';
	}

	/**
	 * Return a local icon URL for an add-on. Remote JSON icons are downloaded
	 * once into the theme resources directory and then served locally.
	 *
	 * @param array $addon Add-on data.
	 * @return string
	 */
	public static function get_addon_icon_url( $addon ) {
		$fallback = self::get_theme_dashboard_icon_url();
		if ( empty( $addon['slug'] ) ) {
			return $fallback;
		}

		$icon_url = ! empty( $addon['icon'] ) ? $addon['icon'] : self::get_known_addon_icon_url( $addon['slug'] );
		if ( empty( $icon_url ) ) {
			return $fallback;
		}

		$local_url = self::cache_remote_addon_icon( $icon_url, $addon['slug'] );
		return $local_url ? $local_url : $fallback;
	}

	/**
	 * Known icon fallbacks for add-ons whose current remote catalog entry still
	 * has an empty icon field.
	 *
	 * @param string $slug Add-on slug.
	 * @return string
	 */
	protected static function get_known_addon_icon_url( $slug ) {
		$slug = sanitize_key( $slug );
		$manifest_urls = array(
			'listeo-booking-plus' => 'https://purethe.me/plugins/listeo-booking-plus.json',
			'ai-chat-search'      => 'https://purethemes.net/license/plugins/ai-chat-search.json',
		);

		if ( ! isset( $manifest_urls[ $slug ] ) ) {
			return '';
		}

		$cached = get_option( self::ADDON_ICON_OPTION );
		if ( is_array( $cached ) && ! empty( $cached[ $slug ]['url'] ) && ! empty( $cached[ $slug ]['expires_at'] ) && (int) $cached[ $slug ]['expires_at'] > time() ) {
			return esc_url_raw( $cached[ $slug ]['url'] );
		}

		$response = wp_remote_get(
			$manifest_urls[ $slug ],
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return is_array( $cached ) && ! empty( $cached[ $slug ]['url'] ) ? esc_url_raw( $cached[ $slug ]['url'] ) : '';
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return is_array( $cached ) && ! empty( $cached[ $slug ]['url'] ) ? esc_url_raw( $cached[ $slug ]['url'] ) : '';
		}

		$icon_url = '';
		if ( ! empty( $decoded['icons'] ) && is_array( $decoded['icons'] ) ) {
			foreach ( array( '1x', '2x', 'default', 'thumbnail' ) as $key ) {
				if ( ! empty( $decoded['icons'][ $key ] ) && is_string( $decoded['icons'][ $key ] ) ) {
					$icon_url = esc_url_raw( $decoded['icons'][ $key ] );
					break;
				}
			}
		}

		if ( '' === $icon_url ) {
			foreach ( array( 'thumbnail', 'thumbnail_url', 'icon', 'image', 'image_url' ) as $key ) {
				if ( ! empty( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) ) {
					$icon_url = esc_url_raw( $decoded[ $key ] );
					break;
				}
			}
		}

		if ( '' === $icon_url ) {
			return is_array( $cached ) && ! empty( $cached[ $slug ]['url'] ) ? esc_url_raw( $cached[ $slug ]['url'] ) : '';
		}

		$cached = is_array( $cached ) ? $cached : array();
		$cached[ $slug ] = array(
			'url'        => $icon_url,
			'checked_at' => time(),
			'expires_at' => time() + self::ADDON_ICON_TTL,
		);
		update_option( self::ADDON_ICON_OPTION, $cached, false );

		return $icon_url;
	}

	/**
	 * Resolve the add-on landing/docs URL. The remote catalog can lag behind
	 * docs changes, so known add-ons get corrected here at render time.
	 *
	 * @param array $addon Add-on data.
	 * @return string
	 */
	public static function get_addon_homepage_url( $addon ) {
		$slug = ! empty( $addon['slug'] ) ? sanitize_key( $addon['slug'] ) : '';
		$urls = array(
			'listeo-booking-plus'       => 'https://purethemes.net/listeo-booking-plus/',
			'ai-chat-search'            => 'https://purethemes.net/ai-chatbot-for-wordpress/',
			'ai-review-highlights'      => 'https://docs.purethemes.net/listeo/knowledge-base/ai-reviews-highlights/',
			'listeo-data-scraper'       => 'https://docs.purethemes.net/listeo/knowledge-base/listing-data-importer/',
			'listeo-sms'                => 'https://docs.purethemes.net/listeo/knowledge-base/sms-notification-otp-verification/',
			'listeo-poi'                => 'https://docs.purethemes.net/listeo/knowledge-base/point-of-interests/',
			'wp-all-import-listeo-addon' => 'https://docs.purethemes.net/listeo/knowledge-base/importing-listings-using-wp-all-import/',
		);

		if ( isset( $urls[ $slug ] ) ) {
			return $urls[ $slug ];
		}

		return ! empty( $addon['homepage'] ) ? esc_url_raw( $addon['homepage'] ) : '';
	}

	/**
	 * Cache a remote icon into wp-content/themes/listeo/resources/listeo-addons.
	 *
	 * @param string $remote_url Remote image URL.
	 * @param string $slug Add-on slug.
	 * @return string Local image URL or empty string.
	 */
	protected static function cache_remote_addon_icon( $remote_url, $slug ) {
		$remote_url = esc_url_raw( (string) $remote_url );
		$parts      = wp_parse_url( $remote_url );
		if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return '';
		}

		$extension = strtolower( pathinfo( $parts['path'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'png', 'jpg', 'jpeg', 'webp', 'svg' ), true ) ) {
			return '';
		}

		$slug      = sanitize_key( $slug );
		$file_name = sanitize_file_name( $slug . '-' . substr( md5( $remote_url ), 0, 8 ) . '.' . $extension );
		$dir       = trailingslashit( get_template_directory() ) . self::ADDON_ICON_DIR;
		$file_path = trailingslashit( $dir ) . $file_name;
		$file_url  = trailingslashit( get_template_directory_uri() ) . self::ADDON_ICON_DIR . '/' . $file_name;

		if ( file_exists( $file_path ) ) {
			return $file_url;
		}

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		if ( ! is_writable( $dir ) ) {
			return '';
		}

		$response = wp_remote_get(
			$remote_url,
			array(
				'timeout'             => 8,
				'limit_response_size' => 1048576,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return '';
		}

		if ( false === file_put_contents( $file_path, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return '';
		}

		return $file_url;
	}

	/**
	 * Render a single add-on card.
	 *
	 * @param array $addon Normalized add-on entry.
	 */
	protected function render_card( $addon ) {
		$template = LISTEO_PLUGIN_DIR . 'templates/admin/addons/card.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Featured add-ons rendered first, original order preserved otherwise.
	 *
	 * @param array $addons
	 * @return array
	 */
	protected function sort_addons( $addons ) {
		$featured = array();
		$rest     = array();
		foreach ( $addons as $addon ) {
			if ( ! empty( $addon['featured'] ) ) {
				$featured[] = $addon;
			} else {
				$rest[] = $addon;
			}
		}
		return array_merge( $featured, $rest );
	}

	/**
	 * Resolve license status for the header pill.
	 *
	 * Reads the licenser's persistent cache to reflect *real* validity. A
	 * disabled-but-stored license must NOT appear green.
	 *
	 * @return array { tone: 'ok'|'warn'|'danger', label: string, detail?: string }
	 */
	protected function get_license_status() {
		// Staging shortcut.
		if ( function_exists( 'listeo_is_staging_environment' ) && listeo_is_staging_environment() ) {
			return array(
				'tone'  => 'ok',
				'label' => __( 'Staging — license bypassed', 'listeo_core' ),
			);
		}

		$key = trim( (string) get_option( 'Listeo_lic_Key', '' ) );
		if ( '' === $key ) {
			return array(
				'tone'  => 'danger',
				'label' => __( 'No license activated', 'listeo_core' ),
			);
		}

		$cache = $this->get_license_cache();

		// Key present, no cache → user has never opened the License page or
		// the cache expired. Surface as unknown rather than greenlighting.
		if ( null === $cache ) {
			return array(
				'tone'   => 'warn',
				'label'  => __( 'License not verified', 'listeo_core' ),
				'detail' => __( 'Open the License page to validate.', 'listeo_core' ),
			);
		}

		if ( empty( $cache['is_valid'] ) ) {
			$detail = '';
			if ( ! empty( $cache['message'] ) ) {
				$detail = sanitize_text_field( wp_strip_all_tags( (string) $cache['message'] ) );
			}
			return array(
				'tone'   => 'danger',
				'label'  => __( 'License inactive', 'listeo_core' ),
				'detail' => $detail,
			);
		}

		return array(
			'tone'  => 'ok',
			'label' => __( 'License activated', 'listeo_core' ),
		);
	}
}
