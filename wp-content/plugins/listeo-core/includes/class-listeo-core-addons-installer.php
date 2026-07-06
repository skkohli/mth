<?php
/**
 * Listeo Core — Add-ons installer.
 *
 * AJAX endpoint that downloads and installs (and optionally activates) an
 * official Listeo add-on via the purethemes.net license endpoint.
 *
 * Endpoint contract (see Phase 2 spec):
 *   GET https://purethemes.net/license/verify.php
 *     ?action=listeo_dashboard_plugin_download
 *     &slug={plugin_slug}
 *     &license_key={listeo_theme_license_key}
 *   → 302 → signed ZIP URL (5-minute TTL, 10 generations/hr per license+plugin).
 *
 * The Listeo theme license key lives in option `Listeo_lic_Key`.
 *
 * @package Listeo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listeo_Core_Addons_Installer {

	const ENDPOINT_URL = 'https://purethemes.net/license/verify.php';
	const AJAX_ACTION  = 'listeo_addons_install';
	const NONCE_NAME   = 'listeo_addons_install';
	const DOWNLOAD_TIMEOUT = 300;

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_install' ) );
	}

	/**
	 * AJAX entry point. Expects POST { nonce, slug, activate (0|1) }.
	 * Responds with wp_send_json_success / wp_send_json_error.
	 */
	public function handle_install() {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'forbidden',
					'message' => __( 'You do not have permission to install add-ons.', 'listeo_core' ),
				),
				403
			);
		}

		check_ajax_referer( self::NONCE_NAME, 'nonce' );

		$slug     = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$activate = ! empty( $_POST['activate'] );

		if ( '' === $slug ) {
			wp_send_json_error(
				array(
					'code'    => 'missing_slug',
					'message' => __( 'No add-on slug supplied.', 'listeo_core' ),
				),
				400
			);
		}

		$addon = $this->find_addon( $slug );
		if ( null === $addon ) {
			wp_send_json_error(
				array(
					'code'    => 'unknown_addon',
					'message' => __( 'This add-on is not listed in the dashboard catalog.', 'listeo_core' ),
				),
				404
			);
		}

		if ( 'paid_separate' === $addon['type'] ) {
			wp_send_json_error(
				array(
					'code'    => 'paid_separate',
					'message' => __( 'This add-on must be purchased separately. Please visit its homepage.', 'listeo_core' ),
				),
				400
			);
		}

		$license_key = trim( (string) get_option( 'Listeo_lic_Key', '' ) );
		if ( '' === $license_key ) {
			wp_send_json_error(
				array(
					'code'    => 'no_license',
					'message' => __( 'Activate your Listeo license first to install add-ons.', 'listeo_core' ),
					'license_url' => admin_url( 'admin.php?page=listeo_license' ),
				),
				400
			);
		}

		// Also reject when the licenser's cache says the license is disabled.
		// Without this, the user clicks Install and we burn a verify.php
		// quota slot for a key that's already known to be invalid.
		if ( ! $this->is_license_valid_locally( $license_key ) ) {
			wp_send_json_error(
				array(
					'code'        => 'license_invalid',
					'message'     => __( 'License is inactive. Please open the License page.', 'listeo_core' ),
					'license_url' => admin_url( 'admin.php?page=listeo_license' ),
				),
				400
			);
		}

		// Short-circuit if already installed.
		$install_state = Listeo_Core_Addons_Catalog::get_install_state( $addon['plugin_file'] );
		if ( 'active' === $install_state ) {
			wp_send_json_success(
				array(
					'state'   => 'active',
					'message' => __( 'Add-on is already active.', 'listeo_core' ),
				)
			);
		}
		if ( 'inactive' === $install_state ) {
			if ( ! $activate ) {
				wp_send_json_success(
					array(
						'state'   => 'inactive',
						'message' => __( 'Add-on is already installed.', 'listeo_core' ),
					)
				);
			}
			$activation = $this->activate_plugin( $addon['plugin_file'] );
			if ( is_wp_error( $activation ) ) {
				wp_send_json_error(
					array(
						'code'    => 'activate_failed',
						'message' => sanitize_text_field( $activation->get_error_message() ),
					)
				);
			}
			wp_send_json_success(
				array(
					'state'   => 'active',
					'message' => __( 'Add-on activated.', 'listeo_core' ),
				)
			);
		}

		// Make sure WP install bits are available.
		$this->load_upgrader();

		// add_query_arg URL-encodes values internally — do not pre-encode.
		$download_url = add_query_arg(
			array(
				'action'      => 'listeo_dashboard_plugin_download',
				'slug'        => $slug,
				'license_key' => $license_key,
			),
			self::ENDPOINT_URL
		);

		$zip_file = download_url( $download_url, self::DOWNLOAD_TIMEOUT );
		if ( is_wp_error( $zip_file ) ) {
			$detail = $this->translate_download_error( $zip_file );
			$response = array(
				'code'    => $detail['code'],
				'message' => $detail['message'],
			);
			if ( ! empty( $detail['retry_after'] ) ) {
				$response['retry_after'] = (int) $detail['retry_after'];
			}
			wp_send_json_error( $response );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $zip_file );

		// Best-effort cleanup. download_url() puts the file in the temp dir.
		if ( file_exists( $zip_file ) ) {
			@unlink( $zip_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => 'install_failed',
					'message' => $result->get_error_message(),
				)
			);
		}

		if ( is_wp_error( $skin->result ) ) {
			wp_send_json_error(
				array(
					'code'    => 'install_failed',
					'message' => $skin->result->get_error_message(),
				)
			);
		}

		if ( false === $result ) {
			// install() returns false when the source bundle can't be unpacked.
			wp_send_json_error(
				array(
					'code'    => 'install_failed',
					'message' => __( 'The add-on could not be installed. Please try again.', 'listeo_core' ),
				)
			);
		}

		$installed_plugin = $upgrader->plugin_info();
		if ( ! $installed_plugin ) {
			$installed_plugin = $addon['plugin_file'];
		}

		$final_state = 'inactive';
		if ( $activate ) {
			$activation = $this->activate_plugin( $installed_plugin );
			if ( is_wp_error( $activation ) ) {
				wp_send_json_success(
					array(
						'state'         => 'inactive',
						'plugin_file'   => $installed_plugin,
						'activate_warn' => sanitize_text_field( $activation->get_error_message() ),
						'message'       => __( 'Add-on installed. Activation failed — try activating it from the Plugins page.', 'listeo_core' ),
					)
				);
			}
			$final_state = 'active';
		}

		wp_send_json_success(
			array(
				'state'       => $final_state,
				'plugin_file' => $installed_plugin,
				'message'     => 'active' === $final_state
					? __( 'Add-on installed and activated.', 'listeo_core' )
					: __( 'Add-on installed.', 'listeo_core' ),
			)
		);
	}

	/**
	 * Mirror of Listeo_Core_Addons_Dashboard::is_license_valid() —
	 * reads the licenser's persistent cache so we don't burn verify.php
	 * quota on a license already known to be disabled.
	 *
	 * @param string $license_key Trimmed key from option Listeo_lic_Key.
	 * @return bool
	 */
	protected function is_license_valid_locally( $license_key ) {
		if ( function_exists( 'listeo_is_staging_environment' ) && listeo_is_staging_environment() ) {
			return true;
		}
		$email     = trim( (string) get_option( 'Listeo_lic_email', '' ) );
		$cache_key = 'listeo_license_cache_' . md5( site_url() . $license_key . $email );

		$cached = get_option( $cache_key, null );
		if ( ! is_array( $cached ) ) {
			$cached = get_transient( $cache_key );
		}
		if ( ! is_array( $cached ) ) {
			// No cache yet — license has not been validated by the licenser.
			// Be conservative: block install rather than guess.
			return false;
		}
		return ! empty( $cached['is_valid'] );
	}

	/**
	 * Look up an add-on by slug in the cached catalog.
	 *
	 * @param string $slug
	 * @return array|null
	 */
	protected function find_addon( $slug ) {
		$config = Listeo_Core_Addons_Catalog::get_config();
		if ( empty( $config['addons'] ) ) {
			return null;
		}
		foreach ( $config['addons'] as $addon ) {
			if ( isset( $addon['slug'] ) && $addon['slug'] === $slug ) {
				return $addon;
			}
		}
		return null;
	}

	/**
	 * Ensure Plugin_Upgrader + WP_Ajax_Upgrader_Skin are loaded.
	 */
	protected function load_upgrader() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}
	}

	/**
	 * Activate an installed plugin.
	 *
	 * @param string $plugin_file
	 * @return true|WP_Error
	 */
	protected function activate_plugin( $plugin_file ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = plugin_basename( $plugin_file );
		$result      = activate_plugin( $plugin_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	/**
	 * Map download_url() / endpoint failures to a user-friendly message.
	 *
	 * @param WP_Error $error
	 * @return array { code: string, message: string }
	 */
	protected function translate_download_error( $error ) {
		$msg = $error->get_error_message();
		$data = $error->get_error_data();

		// The verify endpoint may have returned a JSON error body that
		// download_url() surfaces as a non-200 status. Try to decode it.
		if ( is_array( $data ) && ! empty( $data['body'] ) ) {
			$decoded = json_decode( $data['body'], true );
			if ( is_array( $decoded ) && isset( $decoded['code'] ) ) {
				return array(
					'code'        => sanitize_key( $decoded['code'] ),
					'message'     => isset( $decoded['error'] )
						? sanitize_text_field( wp_strip_all_tags( (string) $decoded['error'] ) )
						: sanitize_text_field( $msg ),
					'retry_after' => isset( $decoded['retry_after'] ) ? (int) $decoded['retry_after'] : null,
				);
			}
		}

		return array(
			'code'        => 'download_failed',
			'message'     => sprintf(
				/* translators: %s: WP_Error message from download_url. */
				__( 'Could not download the add-on: %s', 'listeo_core' ),
				sanitize_text_field( $msg )
			),
			'retry_after' => null,
		);
	}
}
