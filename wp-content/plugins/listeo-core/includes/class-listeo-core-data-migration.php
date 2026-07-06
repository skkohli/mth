<?php
/**
 * Listeo Core Data Migration Class
 * Handles migration of serialized multi-value custom fields to separate database records
 * 
 * @since 1.9.51
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listeo_Core_Data_Migration {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Admin interface for manual migration
		// Use priority 15 to ensure parent menus are registered first
		add_action( 'admin_menu', array( $this, 'add_migration_menu' ), 15 );
		add_action( 'wp_ajax_listeo_migrate_serialized_fields', array( $this, 'ajax_migrate_serialized_fields' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		
		// Automatic migration hooks
		add_action( 'wp', array( $this, 'maybe_migrate_on_listing_view' ) );
		add_action( 'load-post.php', array( $this, 'maybe_migrate_on_admin_edit' ) );
		add_action( 'load-post-new.php', array( $this, 'maybe_migrate_on_admin_edit' ) );
		
		// Batch migration for admin users viewing listing lists
		add_action( 'pre_get_posts', array( $this, 'maybe_batch_migrate_on_listing_query' ) );
	}

	/**
	 * Add migration menu under Listeo Editor (with fallback)
	 */
	public function add_migration_menu() {
		global $menu;
		
		// Check if Listeo Forms and Fields Editor plugin is active and has registered its menu
		$parent_slug = 'edit.php?post_type=listing'; // Default fallback
		
		if ( class_exists( 'Listeo_Forms_And_Fields_Editor' ) ) {
			// Double-check if the parent menu actually exists
			$parent_exists = false;
			if ( ! empty( $menu ) ) {
				foreach ( $menu as $menu_item ) {
					if ( isset( $menu_item[2] ) && $menu_item[2] === 'listeo-fields-and-form' ) {
						$parent_exists = true;
						break;
					}
				}
			}
			
			if ( $parent_exists ) {
				$parent_slug = 'listeo-fields-and-form';
			}
		}
		
		// Register the submenu page
		$hook_suffix = add_submenu_page(
			$parent_slug,
			__( 'Data Migration', 'listeo_core' ),
			__( 'Data Migration', 'listeo_core' ),
			'manage_options',
			'listeo-data-migration',
			array( $this, 'migration_page' )
		);
		
		// Add admin scripts for this page
		if ( $hook_suffix ) {
			add_action( 'admin_print_scripts-' . $hook_suffix, array( $this, 'admin_scripts' ) );
		}
	}

	/**
	 * Enqueue admin scripts for the migration page
	 */
	public function admin_scripts() {
		// Add any specific scripts needed for the migration page
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Display admin notices for migration status
	 */
	public function admin_notices() {
		if ( isset( $_GET['listeo_migration'] ) ) {
			$message = '';
			$type = 'success';

			switch ( $_GET['listeo_migration'] ) {
				case 'completed':
					$message = __( 'Data migration completed successfully!', 'listeo_core' );
					break;
				case 'no_data':
					$message = __( 'No serialized multi-value fields found that need migration.', 'listeo_core' );
					break;
				case 'error':
					$message = __( 'An error occurred during migration. Please check the logs.', 'listeo_core' );
					$type = 'error';
					break;
			}

			if ( $message ) {
				echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
	}

	/**
	 * Migration admin page
	 */
	public function migration_page() {
		// Check for serialized data that needs migration
		$serialized_count = $this->count_serialized_multi_value_fields();
		$multi_value_fields = $this->get_all_multi_value_fields();
		?>
		<div class="wrap">
			<h1><?php _e( 'Listeo Core Data Migration', 'listeo_core' ); ?></h1>
			
			<div class="card">
				<h2><?php _e( 'Multi-Value Field Migration', 'listeo_core' ); ?></h2>
				<p><?php _e( 'This tool will migrate serialized multi-value custom fields to separate database records for better search performance.', 'listeo_core' ); ?></p>
				
				<div class="notice notice-info inline">
					<p><strong><?php _e( 'Automatic Migration Active:', 'listeo_core' ); ?></strong> <?php _e( 'Serialized fields are automatically migrated when listings are viewed or edited. This manual tool is for bulk migration.', 'listeo_core' ); ?></p>
				</div>
				
				<?php if ( ! empty( $multi_value_fields ) ) : ?>
					<div class="notice notice-success inline">
						<p><strong><?php printf( _n( '%d multi-value field detected:', '%d multi-value fields detected:', count( $multi_value_fields ), 'listeo_core' ), count( $multi_value_fields ) ); ?></strong></p>
						<p><code><?php echo esc_html( implode( ', ', $multi_value_fields ) ); ?></code></p>
						<p class="description"><?php _e( 'These field names were found in your taxonomy custom field configurations and will be checked for migration.', 'listeo_core' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-warning inline">
						<p><strong><?php _e( 'No multi-value fields detected.', 'listeo_core' ); ?></strong></p>
						<p><?php _e( 'No multi-value field types (select_multiple, multicheck, etc.) were found in your taxonomy custom field configurations.', 'listeo_core' ); ?></p>
					</div>
				<?php endif; ?>
				
				<?php if ( $serialized_count > 0 ) : ?>
					<p><strong><?php printf( _n( '%d field needs migration.', '%d fields need migration.', $serialized_count, 'listeo_core' ), $serialized_count ); ?></strong></p>
					<p class="description"><?php _e( 'This process will:', 'listeo_core' ); ?></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php _e( 'Find all serialized multi-value custom fields', 'listeo_core' ); ?></li>
						<li><?php _e( 'Convert them to separate database records', 'listeo_core' ); ?></li>
						<li><?php _e( 'Backup the original serialized data (renamed with _backup suffix)', 'listeo_core' ); ?></li>
						<li><?php _e( 'Improve search performance for multi-value fields', 'listeo_core' ); ?></li>
					</ul>
					
					<p><button type="button" class="button button-primary" id="start-migration"><?php _e( 'Start Migration', 'listeo_core' ); ?></button></p>
					<div id="migration-progress" style="display: none;">
						<p><?php _e( 'Migration in progress...', 'listeo_core' ); ?></p>
						<progress id="migration-progress-bar" max="100" value="0" style="width: 100%;"></progress>
						<div id="migration-status"></div>
					</div>
				<?php else : ?>
					<p><?php _e( 'No serialized multi-value fields found. Migration is not needed.', 'listeo_core' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="card">
				<h3><?php _e( 'Migration Details', 'listeo_core' ); ?></h3>
				<p><?php _e( 'This migration is necessary because:', 'listeo_core' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php _e( 'Serialized arrays cannot be efficiently searched with MySQL queries', 'listeo_core' ); ?></li>
					<li><?php _e( 'Separate database records allow proper indexing and fast meta_query searches', 'listeo_core' ); ?></li>
					<li><?php _e( 'CMB2 admin fields work better with separate records', 'listeo_core' ); ?></li>
				</ul>
				
				<h4><?php _e( 'Safety Measures', 'listeo_core' ); ?></h4>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php _e( 'Original data is backed up before migration', 'listeo_core' ); ?></li>
					<li><?php _e( 'Process can be run multiple times safely', 'listeo_core' ); ?></li>
					<li><?php _e( 'Only affects multi-value custom fields (select_multiple, multicheck_split)', 'listeo_core' ); ?></li>
				</ul>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#start-migration').on('click', function() {
				var button = $(this);
				var progress = $('#migration-progress');
				var progressBar = $('#migration-progress-bar');
				var status = $('#migration-status');
				
				button.prop('disabled', true);
				progress.show();
				status.html('<?php _e( 'Initializing migration...', 'listeo_core' ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'listeo_migrate_serialized_fields',
						nonce: '<?php echo wp_create_nonce( 'listeo_migration_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							progressBar.val(100);
							status.html('<strong style="color: green;">' + response.data.message + '</strong>');
							setTimeout(function() {
								window.location.href = window.location.href + '&listeo_migration=completed';
							}, 2000);
						} else {
							status.html('<strong style="color: red;">Error: ' + response.data + '</strong>');
						}
					},
					error: function() {
						status.html('<strong style="color: red;"><?php _e( 'AJAX error occurred', 'listeo_core' ); ?></strong>');
					},
					complete: function() {
						button.prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Count serialized multi-value fields that need migration
	 */
	public function count_serialized_multi_value_fields() {
		global $wpdb;

		$multi_value_fields = $this->get_all_multi_value_fields();
		
		if ( empty( $multi_value_fields ) ) {
			return 0;
		}

		// Create placeholders for prepared statement
		$placeholders = implode( ',', array_fill( 0, count( $multi_value_fields ), '%s' ) );
		
		// Look for meta values that are serialized arrays for the identified multi-value fields
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'listing'
			AND pm.meta_value LIKE 'a:%%'
			AND pm.meta_value REGEXP '^a:[0-9]+:\\{.*\\}$'
			AND pm.meta_key IN ($placeholders)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2 
				WHERE pm2.post_id = pm.post_id 
				AND pm2.meta_key = CONCAT(pm.meta_key, '_backup')
			)",
			...$multi_value_fields
		) );

		return intval( $count );
	}

	/**
	 * Get all multi-value fields defined in taxonomy custom fields
	 */
	private function get_all_multi_value_fields() {
		global $wpdb;
		
		$multi_value_types = array( 'select_multiple', 'multicheck', 'multi_checkbox', 'checkboxes', 'multiselect' );
		$multi_value_fields = array();

		// Get all taxonomy custom field configurations
		$options = $wpdb->get_results(
			"SELECT option_name, option_value 
			FROM {$wpdb->options} 
			WHERE option_name LIKE 'listeo_tax-%_fields'"
		);

		foreach ( $options as $option ) {
			$field_config = maybe_unserialize( $option->option_value );
			
			if ( ! is_array( $field_config ) ) {
				continue;
			}

			foreach ( $field_config as $field_key => $field_data ) {
				// Check if this is a multi-value field type
				if ( isset( $field_data['type'] ) && in_array( $field_data['type'], $multi_value_types ) ) {
					$multi_value_fields[] = $field_key;
					
					// Debug logging for development
					
				}
			}
		}

		$unique_fields = array_unique( $multi_value_fields );
		
		// Debug logging for development
		

		return $unique_fields;
	}

	/**
	 * AJAX handler for migration
	 */
	public function ajax_migrate_serialized_fields() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'listeo_migration_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$result = $this->migrate_serialized_fields();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Perform the actual migration
	 */
	public function migrate_serialized_fields() {
		global $wpdb;

		$migrated_count = 0;
		$error_count = 0;
		$errors = array();

		$multi_value_fields = $this->get_all_multi_value_fields();
		
		if ( empty( $multi_value_fields ) ) {
			return array(
				'success' => true,
				'message' => __( 'No multi-value fields defined in taxonomy configurations.', 'listeo_core' ),
				'migrated' => 0,
				'errors' => 0
			);
		}

		// Create placeholders for prepared statement
		$placeholders = implode( ',', array_fill( 0, count( $multi_value_fields ), '%s' ) );

		// Find all serialized multi-value fields based on actual field definitions
		$serialized_fields = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'listing'
			AND pm.meta_value LIKE 'a:%%'
			AND pm.meta_value REGEXP '^a:[0-9]+:\\{.*\\}$'
			AND pm.meta_key IN ($placeholders)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2 
				WHERE pm2.post_id = pm.post_id 
				AND pm2.meta_key = CONCAT(pm.meta_key, '_backup')
			)",
			...$multi_value_fields
		) );

		if ( empty( $serialized_fields ) ) {
			return array(
				'success' => true,
				'message' => __( 'No serialized fields found that need migration.', 'listeo_core' ),
				'migrated' => 0,
				'errors' => 0
			);
		}

		foreach ( $serialized_fields as $field ) {
			try {
				// Unserialize the data
				$unserialized_data = maybe_unserialize( $field->meta_value );
				
				if ( ! is_array( $unserialized_data ) || empty( $unserialized_data ) ) {
					continue;
				}

				// Create backup of original data
				$backup_key = $field->meta_key . '_backup';
				add_post_meta( $field->post_id, $backup_key, $field->meta_value, true );

				// Delete existing meta (this will remove the serialized version)
				delete_post_meta( $field->post_id, $field->meta_key );

				// Add each value as separate record
				foreach ( $unserialized_data as $value ) {
					if ( ! empty( $value ) ) {
						add_post_meta( $field->post_id, $field->meta_key, sanitize_text_field( $value ) );
					}
				}

				$migrated_count++;

				// Log the migration
				

			} catch ( Exception $e ) {
				$error_count++;
				$errors[] = sprintf(
					__( 'Error migrating field %s for post %d: %s', 'listeo_core' ),
					$field->meta_key,
					$field->post_id,
					$e->getMessage()
				);
				
			}
		}

		return array(
			'success' => true,
			'message' => sprintf(
				__( 'Migration completed! %d fields migrated, %d errors.', 'listeo_core' ),
				$migrated_count,
				$error_count
			),
			'migrated' => $migrated_count,
			'errors' => $error_count,
			'error_details' => $errors
		);
	}

	/**
	 * Get field types that should be multi-value
	 * This helps identify which fields should have been saved as separate records
	 */
	public function get_multi_value_field_types() {
		$multi_value_types = array();

		// Get all listing categories and their custom fields
		$listing_categories = get_terms( array(
			'taxonomy' => 'listing_category',
			'hide_empty' => false,
		) );

		if ( ! is_wp_error( $listing_categories ) ) {
			foreach ( $listing_categories as $category ) {
				$fields = get_option( "_tax_listing_category_fields_{$category->term_id}" );
				if ( is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						if ( isset( $field['type'] ) && in_array( $field['type'], array( 'select_multiple', 'multicheck_split' ) ) ) {
							$field_key = "_tax_listing_category_{$field['slug']}";
							$multi_value_types[] = $field_key;
						}
					}
				}
			}
		}

		return $multi_value_types;
	}

	/**
	 * Automatic migration when viewing a single listing (frontend)
	 */
	public function maybe_migrate_on_listing_view() {
		// Only run on single listing pages
		if ( ! is_singular( 'listing' ) ) {
			return;
		}

		$listing_id = get_the_ID();
		if ( ! $listing_id ) {
			return;
		}

		$this->migrate_single_listing( $listing_id );
	}

	/**
	 * Automatic migration when editing listing in admin
	 */
	public function maybe_migrate_on_admin_edit() {
		global $post;
		
		// Only for listing post type
		if ( ! isset( $post->post_type ) || $post->post_type !== 'listing' ) {
			return;
		}

		if ( ! isset( $post->ID ) ) {
			return;
		}

		$this->migrate_single_listing( $post->ID );
	}

	/**
	 * Batch migration when admin users view listing lists (limited to prevent performance issues)
	 */
	public function maybe_batch_migrate_on_listing_query( $query ) {
		// Only for admin users viewing listing archives
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Only for main queries on listing post type
		if ( ! $query->is_main_query() || $query->get( 'post_type' ) !== 'listing' ) {
			return;
		}

		// Prevent infinite loops and heavy processing
		if ( defined( 'LISTEO_MIGRATION_RUNNING' ) ) {
			return;
		}

		define( 'LISTEO_MIGRATION_RUNNING', true );

		// Limit batch size to prevent timeouts
		$this->migrate_batch_listings( 10 );
	}

	/**
	 * Migrate serialized fields for a single listing
	 */
	public function migrate_single_listing( $listing_id ) {
		if ( ! $listing_id ) {
			return false;
		}

		global $wpdb;

		$multi_value_fields = $this->get_all_multi_value_fields();
		
		if ( empty( $multi_value_fields ) ) {
			return false; // No multi-value fields defined
		}

		// Create placeholders for prepared statement
		$placeholders = implode( ',', array_fill( 0, count( $multi_value_fields ), '%s' ) );

		// Find serialized fields for this specific listing based on actual field definitions
		$query_params = array_merge( array( $listing_id ), $multi_value_fields, array( $listing_id ) );
		
		$serialized_fields = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id = %d
			AND meta_value LIKE 'a:%%'
			AND meta_value REGEXP '^a:[0-9]+:\\{.*\\}$'
			AND meta_key IN ($placeholders)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2 
				WHERE pm2.post_id = %d 
				AND pm2.meta_key = CONCAT({$wpdb->postmeta}.meta_key, '_backup')
			)",
			...$query_params
		) );

		// If no serialized fields found, no migration needed
		if ( empty( $serialized_fields ) ) {
			return false;
		}

		$migrated = false;

		foreach ( $serialized_fields as $field ) {
			if ( $this->migrate_single_field( $listing_id, $field->meta_key, $field->meta_value ) ) {
				$migrated = true;
			}
		}

		if ( $migrated ) {
			// Log successful migration
		
		}

		return $migrated;
	}

	/**
	 * Migrate a batch of listings (for admin list views)
	 */
	public function migrate_batch_listings( $limit = 10 ) {
		global $wpdb;

		$multi_value_fields = $this->get_all_multi_value_fields();
		
		if ( empty( $multi_value_fields ) ) {
			return 0; // No multi-value fields defined
		}

		// Create placeholders for prepared statement
		$placeholders = implode( ',', array_fill( 0, count( $multi_value_fields ), '%s' ) );
		$query_params = array_merge( $multi_value_fields, array( $limit ) );

		// Find listings with serialized fields based on actual field definitions, limit to prevent performance issues
		$listings_with_serialized = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'listing'
			AND pm.meta_value LIKE 'a:%%'
			AND pm.meta_value REGEXP '^a:[0-9]+:\\{.*\\}$'
			AND pm.meta_key IN ($placeholders)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2 
				WHERE pm2.post_id = pm.post_id 
				AND pm2.meta_key = CONCAT(pm.meta_key, '_backup')
			)
			LIMIT %d",
			...$query_params
		) );

		$migrated_count = 0;
		foreach ( $listings_with_serialized as $listing_id ) {
			if ( $this->migrate_single_listing( $listing_id ) ) {
				$migrated_count++;
			}
		}

		if ( $migrated_count > 0 ) {
			error_log( "Listeo Batch Auto-Migration: Migrated {$migrated_count} listings" );
		}

		return $migrated_count;
	}

	/**
	 * Migrate a single field for a listing
	 */
	public function migrate_single_field( $listing_id, $meta_key, $meta_value ) {
		try {
			// Unserialize the data
			$unserialized_data = maybe_unserialize( $meta_value );
			
			if ( ! is_array( $unserialized_data ) || empty( $unserialized_data ) ) {
				return false;
			}

			// Create backup of original data
			$backup_key = $meta_key . '_backup';
			add_post_meta( $listing_id, $backup_key, $meta_value, true );

			// Delete existing meta (this will remove the serialized version)
			delete_post_meta( $listing_id, $meta_key );

			// Add each value as separate record
			foreach ( $unserialized_data as $value ) {
				if ( ! empty( $value ) ) {
					add_post_meta( $listing_id, $meta_key, sanitize_text_field( $value ) );
				}
			}

			return true;

		} catch ( Exception $e ) {
			error_log( "Listeo Auto-Migration Error for listing {$listing_id}, field {$meta_key}: " . $e->getMessage() );
			return false;
		}
	}

}

// Initialize the migration class
new Listeo_Core_Data_Migration();