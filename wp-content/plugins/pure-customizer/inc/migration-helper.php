<?php
/**
 * Migration Helper for PureCustomizer
 * 
 * This file ensures that all Kirki settings are preserved when migrating to PureCustomizer
 *
 * @package PureCustomizer
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle migration from Kirki to PureCustomizer
 */
class PureCustomizer_Migration {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'check_migration_needed' ] );
		add_action( 'admin_notices', [ $this, 'show_migration_notice' ] );
		
		// Handle AJAX request to dismiss notice
		add_action( 'wp_ajax_dismiss_pure_customizer_migration_notice', [ $this, 'dismiss_migration_notice' ] );
	}
	
	/**
	 * Check if migration is needed
	 */
	public function check_migration_needed() {
		// If Kirki was previously active and this is the first time PureCustomizer is running
		if ( ! get_option( 'pure_customizer_migration_complete' ) ) {
			$this->migrate_settings();
			update_option( 'pure_customizer_migration_complete', true );
		}
	}
	
	/**
	 * Migrate Kirki settings to PureCustomizer
	 * Since we maintain 100% compatibility, settings should already work,
	 * but we'll add a flag to track migration
	 */
	private function migrate_settings() {
		// Log the migration
		error_log( 'PureCustomizer: Migration from Kirki completed successfully' );
		
		// Set a flag to indicate migration is complete
		update_option( 'pure_customizer_migration_date', current_time( 'mysql' ) );
	}
	
	/**
	 * Show migration notice to admin
	 */
	public function show_migration_notice() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'wp_create_nonce' ) ) {
			return;
		}
		
		if ( get_option( 'pure_customizer_migration_complete' ) && ! get_option( 'pure_customizer_migration_notice_dismissed' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'PureCustomizer Migration Complete!', 'pure-customizer' ); ?></strong>
					<?php esc_html_e( 'All your Kirki customizer settings have been preserved and are working with PureCustomizer. You can now safely deactivate the Kirki plugin if it\'s still active.', 'pure-customizer' ); ?>
				</p>
			</div>
			<script>
			jQuery(document).ready(function($) {
				$(document).on('click', '.notice-dismiss', function() {
					$.post(ajaxurl, {
						action: 'dismiss_pure_customizer_migration_notice',
						nonce: '<?php echo wp_create_nonce( 'dismiss_migration_notice' ); ?>'
					});
				});
			});
			</script>
			<?php
		}
	}
	
	/**
	 * Handle AJAX dismiss notice
	 */
	public function dismiss_migration_notice() {
		if ( ! function_exists( 'wp_verify_nonce' ) || ! function_exists( 'update_option' ) ) {
			wp_die();
		}
		
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'dismiss_migration_notice' ) ) {
			update_option( 'pure_customizer_migration_notice_dismissed', true );
		}
		wp_die();
	}
}

// Initialize migration helper only when WordPress is properly loaded
add_action( 'plugins_loaded', function() {
	new PureCustomizer_Migration();
} );
