<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Simple toggle for enabling/disabling custom permalinks feature
 * This is always loaded, unlike the main permalink settings class
 *
 * @package listeo-core
 * @since 1.27.0
 */
class Listeo_Core_Permalink_Toggle {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.27.0
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.27.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'setup_toggle_field' ) );
		add_action( 'admin_notices', array( $this, 'show_feature_notices' ) );
	}

	/**
	 * Add the toggle field to permalink settings.
	 */
	public function setup_toggle_field() {
		add_settings_field(
			'listeo_enable_custom_permalinks',
			__( 'Listeo Custom Permalinks', 'listeo-core' ),
			array( $this, 'toggle_field_input' ),
			'permalink',
			'optional'
		);
		
		// Handle saving
		if ( isset( $_POST['listeo_enable_custom_permalinks'] ) ) {
			update_option( 'listeo_enable_custom_permalinks', true );
			// When enabling, flush rewrite rules
			flush_rewrite_rules( true );
		} elseif ( isset( $_POST['permalink_structure'] ) ) {
			// Only update if we're on the permalink page (when permalink_structure is posted)
			update_option( 'listeo_enable_custom_permalinks', false );
			// When disabling, flush rewrite rules to restore default
			flush_rewrite_rules( true );
		}
	}

	/**
	 * Show the toggle field.
	 */
	public function toggle_field_input() {
		$enabled = get_option( 'listeo_enable_custom_permalinks', false );
		?>
		<div id="listeo-permalink-toggle">
			<label>
				<input type="checkbox" name="listeo_enable_custom_permalinks" value="1" <?php checked( $enabled ); ?> />
				<strong><?php _e( 'Enable Listeo custom permalink structures', 'listeo-core' ); ?></strong>
			</label>
			<br><br>
			
			<?php if ( ! $enabled ) : ?>
				<div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #6c757d; margin: 10px 0;">
					<h4 style="margin-top: 0;"><?php _e( '📋 Custom Permalinks Disabled', 'listeo-core' ); ?></h4>
					<p style="margin: 0;"><?php _e( 'Listeo listings use WordPress default permalinks. Enable this feature to access custom permalink structures and advanced URL options.', 'listeo-core' ); ?></p>
				</div>
			<?php else : ?>
				<div style="background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0;">
					<h4 style="margin-top: 0;"><?php _e( '✅ Custom Permalinks Enabled', 'listeo-core' ); ?></h4>
					<p style="margin: 0;"><?php _e( 'Advanced permalink options are now available below. You can create custom URL structures for your listings.', 'listeo-core' ); ?></p>
				</div>
			<?php endif; ?>
			
			<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0;">
				<h4 style="margin-top: 0;"><?php _e( '⚠️ Important:', 'listeo-core' ); ?></h4>
				<ul style="margin: 0;">
					<li><?php _e( 'This feature is completely optional - your listings work fine without it', 'listeo-core' ); ?></li>
					<li><?php _e( 'Only enable if you want to customize how listing URLs look', 'listeo-core' ); ?></li>
					<li><?php _e( 'Disabling this will restore default WordPress permalink behavior', 'listeo-core' ); ?></li>
					<li><?php _e( 'Changes take effect immediately after saving', 'listeo-core' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Show admin notices about the feature status.
	 */
	public function show_feature_notices() {
		// Only show on permalink settings page
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'options-permalink' ) {
			return;
		}
		
		$enabled = get_option( 'listeo_enable_custom_permalinks', false );
		
		// Show success message after enabling/disabling
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
			if ( $enabled ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php _e( '✅ Listeo Custom Permalinks Enabled', 'listeo-core' ); ?></strong><br>
						<?php _e( 'Advanced permalink options are now available below. Rewrite rules have been flushed.', 'listeo-core' ); ?>
					</p>
				</div>
				<?php
			} else {
				?>
				<div class="notice notice-info is-dismissible">
					<p>
						<strong><?php _e( 'ℹ️ Listeo Custom Permalinks Disabled', 'listeo-core' ); ?></strong><br>
						<?php _e( 'Listings now use default WordPress permalinks. Rewrite rules have been restored.', 'listeo-core' ); ?>
					</p>
				</div>
				<?php
			}
		}
	}
}

// Always load the toggle (unlike the main permalink settings)
Listeo_Core_Permalink_Toggle::instance();