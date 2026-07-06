<?php
/**
 * PureCustomizer Version Check and Debug Info
 * 
 * This file provides version information and debug details
 *
 * @package PureCustomizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PureCustomizer Debug Information
 */
class PureCustomizer_Debug {
	
	/**
	 * Get plugin information
	 */
	public static function get_info() {
		return [
			'plugin_name' => 'PureCustomizer Framework',
			'version' => defined( 'PURE_CUSTOMIZER_VERSION' ) ? PURE_CUSTOMIZER_VERSION : '1.0.0',
			'kirki_compatibility' => class_exists( 'Kirki' ),
			'pure_customizer_class' => class_exists( 'PureCustomizer' ),
			'plugin_dir' => defined( 'PURE_CUSTOMIZER_PLUGIN_DIR' ) ? PURE_CUSTOMIZER_PLUGIN_DIR : '',
			'plugin_url' => defined( 'PURE_CUSTOMIZER_PLUGIN_URL' ) ? PURE_CUSTOMIZER_PLUGIN_URL : '',
			'migration_complete' => get_option( 'pure_customizer_migration_complete', false ),
			'wp_version' => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
		];
	}
	
	/**
	 * Display debug information in admin
	 */
	public static function admin_debug_info() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$info = self::get_info();
		echo '<div style="background: #f1f1f1; padding: 10px; margin: 10px 0; border-left: 4px solid #00a0d2;">';
		echo '<h4>PureCustomizer Debug Info</h4>';
		echo '<ul>';
		foreach ( $info as $key => $value ) {
			$display_value = is_bool( $value ) ? ( $value ? 'Yes' : 'No' ) : $value;
			echo '<li><strong>' . ucwords( str_replace( '_', ' ', $key ) ) . ':</strong> ' . esc_html( $display_value ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}
}

// Add debug info to admin footer for administrators - only when WordPress is fully loaded
// Commented out to hide debug info in admin
/*
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	add_action( 'admin_footer', function() {
		if ( is_admin() && function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
			PureCustomizer_Debug::admin_debug_info();
		}
	} );
}
*/
