<?php
/**
 * Listeo Core — Add-ons Dashboard catalog.
 *
 * Fetches the remote dashboard.json that drives the Add-ons admin page,
 * caches it in wp_options (48h), and falls back to the last-known-good
 * copy if the remote is unreachable. A bundled
 * default ships with the plugin so the dashboard renders before the
 * remote file exists.
 *
 * @package Listeo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listeo_Core_Addons_Catalog {

	const REMOTE_URL        = 'https://purethemes.net/dashboard.json';
	const TRANSIENT_KEY     = 'listeo_addons_dashboard_config';
	const CACHE_OPTION      = 'listeo_addons_dashboard_config_cache';
	const FALLBACK_OPTION   = 'listeo_addons_dashboard_config_fallback';
	const DEFAULT_TTL       = 172800; // 48h
	const REMOTE_TIMEOUT    = 8;
	const SCHEMA_VERSION    = 1;

	/**
	 * Return the dashboard config (catalog + global banner).
	 *
	 * @param bool $force_refresh Bypass cache and re-fetch.
	 * @return array Normalized config.
	 */
	public static function get_config( $force_refresh = false ) {

		if ( ! $force_refresh ) {
			$cached = self::get_option_cache();
			if ( is_array( $cached ) && isset( $cached['addons'] ) ) {
				return $cached;
			}
		}

		$remote = self::fetch_remote();

		if ( is_array( $remote ) && isset( $remote['addons'] ) ) {
			$normalized = self::normalize( $remote );
			$ttl        = max( 300, (int) apply_filters( 'listeo_addons_dashboard_ttl', self::DEFAULT_TTL ) );
			self::set_option_cache( $normalized, $ttl );
			update_option( self::FALLBACK_OPTION, $normalized, false );
			return $normalized;
		}

		// Remote failed — try last-known-good, then the bundled default.
		// Cache the result for a short window so a missing remote endpoint
		// doesn't trigger an 8s HTTP timeout on every admin page load.
		$fallback = get_option( self::FALLBACK_OPTION );
		if ( is_array( $fallback ) && isset( $fallback['addons'] ) ) {
			self::set_option_cache( $fallback, 15 * MINUTE_IN_SECONDS );
			return $fallback;
		}

		$bundled = self::bundled_default();
		self::set_option_cache( $bundled, 15 * MINUTE_IN_SECONDS );
		return $bundled;
	}

	/**
	 * Wipe the cache so the next page load refetches.
	 */
	public static function flush() {
		delete_option( self::CACHE_OPTION );
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Read the catalog option cache.
	 *
	 * @return array|null Cached catalog.
	 */
	protected static function get_option_cache() {
		$cached = get_option( self::CACHE_OPTION );
		if ( ! is_array( $cached ) || ! array_key_exists( 'value', $cached ) || empty( $cached['expires_at'] ) ) {
			return null;
		}

		if ( (int) $cached['expires_at'] <= time() ) {
			return null;
		}

		return is_array( $cached['value'] ) ? $cached['value'] : null;
	}

	/**
	 * Store the catalog option cache without autoloading it.
	 *
	 * @param array $value Catalog data.
	 * @param int   $ttl Cache TTL in seconds.
	 */
	protected static function set_option_cache( $value, $ttl ) {
		update_option(
			self::CACHE_OPTION,
			array(
				'value'      => $value,
				'created_at' => time(),
				'expires_at' => time() + max( 300, (int) $ttl ),
			),
			false
		);
	}

	/**
	 * Fetch the remote dashboard.json.
	 *
	 * @return array|null Decoded array, or null on failure.
	 */
	protected static function fetch_remote() {
		$response = wp_remote_get(
			self::REMOTE_URL,
			array(
				'timeout' => self::REMOTE_TIMEOUT,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}

	/**
	 * Normalize raw config from any source so the renderer never has to
	 * guard against missing keys. Also drops expired discounts.
	 *
	 * @param array $raw Raw decoded config.
	 * @return array Normalized config.
	 */
	protected static function normalize( $raw ) {
		$out = array(
			'version'       => isset( $raw['version'] ) ? (int) $raw['version'] : self::SCHEMA_VERSION,
			'updated_at'    => isset( $raw['updated_at'] ) ? sanitize_text_field( (string) $raw['updated_at'] ) : '',
			'addons'        => array(),
			'global_banner' => array(
				'active'   => false,
				'message'  => '',
				'cta_text' => '',
				'cta_url'  => '',
			),
		);

		if ( ! empty( $raw['global_banner'] ) && is_array( $raw['global_banner'] ) ) {
			$banner = wp_parse_args( $raw['global_banner'], $out['global_banner'] );
			$out['global_banner'] = array(
				'active'   => ! empty( $banner['active'] ),
				'message'  => sanitize_text_field( (string) $banner['message'] ),
				'cta_text' => sanitize_text_field( (string) $banner['cta_text'] ),
				'cta_url'  => esc_url_raw( (string) $banner['cta_url'] ),
			);
		}

		$addons = isset( $raw['addons'] ) && is_array( $raw['addons'] ) ? $raw['addons'] : array();
		foreach ( $addons as $addon ) {
			$normalized = self::normalize_addon( $addon );
			if ( $normalized ) {
				$out['addons'][] = $normalized;
			}
		}

		return $out;
	}

	/**
	 * Normalize a single add-on entry.
	 *
	 * @param mixed $addon Raw add-on entry.
	 * @return array|null Normalized entry, or null if it lacks a slug.
	 */
	protected static function normalize_addon( $addon ) {
		if ( ! is_array( $addon ) || empty( $addon['slug'] ) ) {
			return null;
		}

		$discount = array(
			'active' => false,
			'value'  => 0,
			'code'   => '',
		);

		if ( ! empty( $addon['discount'] ) && is_array( $addon['discount'] ) ) {
			$raw_discount = $addon['discount'];
			$active       = ! empty( $raw_discount['active'] );

			// Honor expiry server-side so a stale transient never shows a dead promo.
			// Comparison in UTC because expires_at is treated as a UTC date string.
			if ( $active && ! empty( $raw_discount['expires_at'] ) ) {
				$expires_ts = strtotime( (string) $raw_discount['expires_at'] );
				if ( $expires_ts && $expires_ts < time() ) {
					$active = false;
				}
			}

			$raw_code = isset( $raw_discount['code'] ) ? (string) $raw_discount['code'] : '';
			// Promo codes are alphanumeric with dash/underscore — strip anything else.
			$code = preg_replace( '/[^A-Za-z0-9_\-]/', '', $raw_code );

			$discount = array(
				'active' => $active,
				'value'  => isset( $raw_discount['value'] ) ? (int) $raw_discount['value'] : 0,
				'code'   => $code,
			);
		}

		$homepage = isset( $addon['homepage'] ) ? esc_url_raw( (string) $addon['homepage'] ) : '';

		return array(
			'slug'        => sanitize_key( $addon['slug'] ),
			'name'        => isset( $addon['name'] ) ? sanitize_text_field( (string) $addon['name'] ) : '',
			'description' => isset( $addon['description'] ) ? sanitize_text_field( (string) $addon['description'] ) : '',
			'icon'        => self::normalize_addon_icon( $addon, $homepage ),
			'homepage'    => $homepage,
			'plugin_file' => isset( $addon['plugin_file'] ) ? self::sanitize_plugin_file( (string) $addon['plugin_file'] ) : '',
			'type'        => isset( $addon['type'] ) ? sanitize_key( $addon['type'] ) : 'license_included',
			'featured'    => ! empty( $addon['featured'] ),
			'price'       => isset( $addon['price'] ) ? sanitize_text_field( (string) $addon['price'] ) : '',
			'discount'    => $discount,
		);
	}

	/**
	 * Resolve an add-on icon/thumbnail from supported catalog field shapes.
	 *
	 * @param array  $addon Raw add-on entry.
	 * @param string $homepage Add-on homepage URL, used for relative image paths.
	 * @return string
	 */
	protected static function normalize_addon_icon( $addon, $homepage ) {
		$candidates = array();

		foreach ( array( 'icon', 'thumbnail', 'thumbnail_url', 'image', 'image_url' ) as $key ) {
			if ( ! empty( $addon[ $key ] ) && is_string( $addon[ $key ] ) ) {
				$candidates[] = $addon[ $key ];
			}
		}

		if ( ! empty( $addon['icons'] ) && is_array( $addon['icons'] ) ) {
			foreach ( array( '1x', '2x', 'default', 'thumbnail' ) as $key ) {
				if ( ! empty( $addon['icons'][ $key ] ) && is_string( $addon['icons'][ $key ] ) ) {
					$candidates[] = $addon['icons'][ $key ];
				}
			}
		}

		foreach ( $candidates as $candidate ) {
			$url = self::resolve_addon_asset_url( $candidate, $homepage );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Resolve absolute, protocol-relative, or homepage-relative asset URLs.
	 *
	 * @param string $value Raw URL/path.
	 * @param string $homepage Homepage URL.
	 * @return string
	 */
	protected static function resolve_addon_asset_url( $value, $homepage ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, '//' ) ) {
			return esc_url_raw( 'https:' . $value );
		}

		if ( preg_match( '#^https?://#i', $value ) ) {
			return esc_url_raw( $value );
		}

		if ( '' === $homepage ) {
			return '';
		}

		$base = trailingslashit( $homepage );
		return esc_url_raw( $base . ltrim( $value, '/' ) );
	}

	/**
	 * Constrain a plugin_file to the WP "folder/file.php" pattern. Anything
	 * with a parent-directory traversal or unexpected characters is rejected.
	 *
	 * @param string $value Raw plugin_file from remote JSON.
	 * @return string
	 */
	protected static function sanitize_plugin_file( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		// Reject traversal outright.
		if ( false !== strpos( $value, '..' ) ) {
			return '';
		}
		// Allow only the canonical "folder/sub-file.php" shape.
		if ( ! preg_match( '#^[A-Za-z0-9_\-]+/[A-Za-z0-9_\-./]+\.php$#', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Bundled default config — used until the remote endpoint exists.
	 *
	 * @return array
	 */
	protected static function bundled_default() {
		$path = LISTEO_PLUGIN_DIR . 'assets/dashboard-default.json';

		if ( file_exists( $path ) ) {
			$contents = file_get_contents( $path );
			$decoded  = json_decode( $contents, true );
			if ( is_array( $decoded ) ) {
				return self::normalize( $decoded );
			}
		}

		// Hard-coded last resort so the page never blank-renders.
		return self::normalize(
			array(
				'version' => self::SCHEMA_VERSION,
				'addons'  => array(
					array(
						'slug'        => 'listeo-booking-plus',
						'name'        => 'Listeo Booking Plus',
						'description' => 'Advanced booking: resources, events, recurrence, multi-slot, service durations and constraints.',
						'homepage'    => 'https://purethemes.net/listeo-booking-plus/',
						'plugin_file' => 'listeo-booking-plus/listeo-booking-plus.php',
						'type'        => 'paid_separate',
						'featured'    => true,
					),
				),
			)
		);
	}

	/**
	 * Local install state for a given plugin_file path.
	 * Returns one of: 'active', 'inactive', 'not_installed'.
	 *
	 * @param string $plugin_file Relative path under wp-content/plugins/.
	 * @return string
	 */
	public static function get_install_state( $plugin_file ) {
		if ( empty( $plugin_file ) ) {
			return 'not_installed';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin_file ] ) ) {
			return 'not_installed';
		}

		return is_plugin_active( $plugin_file ) ? 'active' : 'inactive';
	}
}
