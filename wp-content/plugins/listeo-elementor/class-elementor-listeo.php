<?php


if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Main Elementor Awesomesauce Class
 *
 * The init class that runs the Elementor Awesomesauce plugin.
 * Intended To make sure that the plugin's minimum requirements are met.
 *
 * You should only modify the constants to match your plugin's needs.
 *
 * Any custom code should go inside Plugin Class in the plugin.php file.
 */
final class Elementor_Listeo {

	/**
	 * Plugin Version
	 *
	 * @since 1.0.0
	 * @var string The plugin version.
	 */
	const VERSION = '1.0.0';

	/**
	 * Minimum Elementor Version
	 *
	 * @since 1.0.0
	 * @var string Minimum Elementor version required to run the plugin.
	 */
	const MINIMUM_ELEMENTOR_VERSION = '2.0.0';

	/**
	 * Minimum PHP Version
	 *
	 * @since 1.0.0
	 * @var string Minimum PHP version required to run the plugin.
	 */
	const MINIMUM_PHP_VERSION = '7.0';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function __construct() {
		// Load the translation.
		//add_action( 'init', array( $this, 'i18n' ) );
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// Initialize the plugin.
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		// AJAX handlers for listing search
		add_action( 'wp_ajax_listeo_elementor_search_listings', array( $this, 'ajax_search_listings' ) );
		add_action( 'wp_ajax_listeo_elementor_get_listing_titles', array( $this, 'ajax_get_listing_titles' ) );

	}

	/**
	 * AJAX handler for searching listings
	 * Used by SELECT2 controls in widgets for large datasets
	 *
	 * @since 2.0.11
	 * @access public
	 */
	public function ajax_search_listings() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'listeo_elementor_search' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$search = isset( $_POST['q'] ) ? sanitize_text_field( $_POST['q'] ) : '';
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 20;

		$args = array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => $search,
		);

		$query = new WP_Query( $args );
		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$results[] = array(
					'id'   => get_the_ID(),
					'text' => get_the_title() . ' (ID: ' . get_the_ID() . ')',
				);
			}
			wp_reset_postdata();
		}

		$more = ( $page * $per_page ) < $query->found_posts;

		wp_send_json( array(
			'results'    => $results,
			'pagination' => array(
				'more' => $more,
			),
		) );
	}

	/**
	 * AJAX handler for getting listing titles by IDs
	 * Used to restore previously selected values in SELECT2
	 *
	 * @since 2.0.11
	 * @access public
	 */
	public function ajax_get_listing_titles() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'listeo_elementor_search' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json( array( 'results' => array() ) );
		}

		$results = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && $post->post_type === 'listing' ) {
				$results[] = array(
					'id'   => $id,
					'text' => $post->post_title . ' (ID: ' . $id . ')',
				);
			}
		}

		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * Load Textdomain
	 *
	 * Load plugin localization files.
	 * Fired by `init` action hook.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function load_localisation() {
		
		load_plugin_textdomain( 'listeo_elementor', false,  basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Initialize the plugin
	 *
	 * Validates that Elementor is already loaded.
	 * Checks for basic plugin requirements, if one check fail don't continue,
	 * if all check have passed include the plugin class.
	 *
	 * Fired by `plugins_loaded` action hook.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function init() {

		// Check if Elementor installed and activated.
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_main_plugin' ) );
			return;
		}

		// Check for required Elementor version.
		if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_elementor_version' ) );
			return;
		}

		// Check for required PHP version.
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_php_version' ) );
			return;
		}

		// Once we get here, We have passed all validation checks so we can safely include our widgets.
		require_once 'class-widgets.php';
		require_once 'class-dynamictags.php';

		// Enqueue editor scripts for AJAX SELECT2
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
	}

	/**
	 * Enqueue scripts for Elementor editor
	 * Adds AJAX configuration for listing search SELECT2
	 *
	 * @since 2.0.11
	 * @access public
	 */
	public function enqueue_editor_scripts() {
		wp_enqueue_script(
			'listeo-elementor-editor',
			plugin_dir_url( ELEMENTOR_LISTEO ) . 'assets/js/editor.js',
			array( 'jquery' ),
			'2.0.14',
			true
		);

		wp_localize_script( 'listeo-elementor-editor', 'listeoElementor', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'listeo_elementor_search' ),
		) );
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have Elementor installed or activated.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function admin_notice_missing_main_plugin() {
		deactivate_plugins( plugin_basename( ELEMENTOR_LISTEO ) );

		return sprintf(
			wp_kses(
				'<div class="notice notice-warning is-dismissible"><p><strong>"%1$s"</strong> requires <strong>"%2$s"</strong> to be installed and activated.</p></div>',
				array(
					'div' => array(
						'class'  => array(),
						'p'      => array(),
						'strong' => array(),
					),
				)
			),
			'Listeo Elementor',
			'Elementor'
		);
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required Elementor version.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function admin_notice_minimum_elementor_version() {
		deactivate_plugins( plugin_basename( ELEMENTOR_AWESOMESAUCE ) );

		return sprintf(
			wp_kses(
				'<div class="notice notice-warning is-dismissible"><p><strong>"%1$s"</strong> requires <strong>"%2$s"</strong> version %3$s or greater.</p></div>',
				array(
					'div' => array(
						'class'  => array(),
						'p'      => array(),
						'strong' => array(),
					),
				)
			),
			'Listeo Elementor',
			'Elementor',
			self::MINIMUM_ELEMENTOR_VERSION
		);
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required PHP version.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function admin_notice_minimum_php_version() {
		deactivate_plugins( plugin_basename( ELEMENTOR_AWESOMESAUCE ) );

		return sprintf(
			wp_kses(
				'<div class="notice notice-warning is-dismissible"><p><strong>"%1$s"</strong> requires <strong>"%2$s"</strong> version %3$s or greater.</p></div>',
				array(
					'div' => array(
						'class'  => array(),
						'p'      => array(),
						'strong' => array(),
					),
				)
			),
			'Listeo Elementor',
			'Elementor',
			self::MINIMUM_ELEMENTOR_VERSION
		);
	}

	
}

// Instantiate Elementor_Awesomesauce.
new Elementor_Listeo();