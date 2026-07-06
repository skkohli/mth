<?php
/**
 * Kirki Compatibility Layer
 * 
 * This file ensures 100% backward compatibility with existing Kirki implementations.
 * Themes and plugins using Kirki will continue to work without any modifications.
 *
 * @package PureCustomizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kirki Compatibility Class
 * 
 * Provides all the static methods that existing Kirki implementations expect.
 * This class acts as a proxy to maintain compatibility while using PureCustomizer internally.
 */
if ( ! class_exists( 'Kirki' ) ) {
	class Kirki {
		
		/**
		 * Add a configuration.
		 *
		 * @static
		 * @access public
		 * @param string $config_id The configuration ID.
		 * @param array  $args      The configuration arguments.
		 */
		public static function add_config( $config_id, $args = [] ) {
			return \Kirki\Compatibility\Kirki::add_config( $config_id, $args );
		}

		/**
		 * Add a field.
		 *
		 * @static
		 * @access public
		 * @param string $config_id The configuration ID.
		 * @param array  $args      The field arguments.
		 */
		public static function add_field( $config_id, $args ) {
			return \Kirki\Compatibility\Kirki::add_field( $config_id, $args );
		}

		/**
		 * Add a section.
		 *
		 * @static
		 * @access public
		 * @param string $id   The section ID.
		 * @param array  $args The section arguments.
		 */
		public static function add_section( $id, $args ) {
			return \Kirki\Compatibility\Kirki::add_section( $id, $args );
		}

		/**
		 * Add a panel.
		 *
		 * @static
		 * @access public
		 * @param string $id   The panel ID.
		 * @param array  $args The panel arguments.
		 */
		public static function add_panel( $id, $args ) {
			return \Kirki\Compatibility\Kirki::add_panel( $id, $args );
		}

		/**
		 * Get the value of an option.
		 *
		 * @static
		 * @access public
		 * @param string $config_id The configuration ID. Leave empty for global.
		 * @param string $field_id  The field ID.
		 * @return mixed The option value.
		 */
		public static function get_option( $config_id = '', $field_id = '' ) {
			return \Kirki\Compatibility\Kirki::get_option( $config_id, $field_id );
		}

		/**
		 * Get all options.
		 *
		 * @static
		 * @access public
		 * @param string $config_id The configuration ID.
		 * @return array All options for the config.
		 */
		public static function get_options( $config_id = '' ) {
			return \Kirki\Compatibility\Kirki::get_options( $config_id );
		}
	}
}

/**
 * Maintain compatibility with function-style calls
 */
if ( ! function_exists( 'Kirki' ) ) {
	/**
	 * Returns an instance of the Kirki object.
	 */
	function Kirki() {
		return kirki();
	}
}

/**
 * Make sure all Kirki constants are available for backward compatibility
 */
if ( ! defined( 'KIRKI_PLUGIN_FILE' ) && defined( 'PURE_CUSTOMIZER_PLUGIN_FILE' ) ) {
	define( 'KIRKI_PLUGIN_FILE', PURE_CUSTOMIZER_PLUGIN_FILE );
}

if ( ! defined( 'KIRKI_VERSION' ) && defined( 'PURE_CUSTOMIZER_VERSION' ) ) {
	define( 'KIRKI_VERSION', PURE_CUSTOMIZER_VERSION );
}

if ( ! defined( 'KIRKI_PLUGIN_DIR' ) && defined( 'PURE_CUSTOMIZER_PLUGIN_DIR' ) ) {
	define( 'KIRKI_PLUGIN_DIR', PURE_CUSTOMIZER_PLUGIN_DIR );
}

if ( ! defined( 'KIRKI_PLUGIN_URL' ) && defined( 'PURE_CUSTOMIZER_PLUGIN_URL' ) ) {
	define( 'KIRKI_PLUGIN_URL', PURE_CUSTOMIZER_PLUGIN_URL );
}
