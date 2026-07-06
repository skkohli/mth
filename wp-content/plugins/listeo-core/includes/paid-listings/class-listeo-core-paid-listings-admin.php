<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin
 */
class Listeo_Core_Paid_Listings_Admin {

	/** @var object Class Instance */
	private static $instance;

	/**
	 * Get the class instance
	 *
	 * @return static
	 */
	public static function get_instance() {
		return null === self::$instance ? ( self::$instance = new self ) : self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_filter( 'woocommerce_screen_ids', array( $this, 'add_screen_ids' ) );
		add_filter( 'job_manager_admin_screen_ids', array( $this, 'add_screen_ids' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_listeo_save_package_listing_types', array( $this, 'ajax_save_listing_types' ) );

		add_filter( 'parse_query', array( $this, 'parse_query' ) );
	}

	/**
	 * Screen IDS
	 *
	 * @param  array $ids
	 * @return array
	 */
	public function add_screen_ids( $ids ) {
		$wc_screen_id = sanitize_title( __( 'WooCommerce', 'woocommerce' ) );
		return array_merge( $ids, array(
			'users_page_listeo_core_paid_listings_packages'
		) );
	}

	

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'listeo-editor_page_listeo_core_paid_listings_packages' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'listeo-package-products-manager',
			plugins_url( 'assets/css/package-products-manager.css', __FILE__ ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'listeo-package-products-manager',
			plugins_url( 'assets/js/package-products-manager.js', __FILE__ ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script( 'listeo-package-products-manager', 'listeoPackageManager', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'listeo_package_manager' ),
			'saving'   => __( 'Saving...', 'listeo_core' ),
			'save'     => __( 'Save Changes', 'listeo_core' ),
		));
	}

	/**
	 * Add menu items
	 */
	public function admin_menu() {
		add_submenu_page( 'listeo-fields-and-form', __( 'Packages Manager', 'listeo_core' ), __( 'Packages Manager', 'listeo_core' ), 'manage_options', 'listeo_core_paid_listings_packages' , array( $this, 'packages_page' ) );
	}

	/**
	 * Manage Packages
	 */
	public function packages_page() {
		global $wpdb;

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

		if ( 'delete' === $action && ! empty( $_GET['delete_nonce'] ) && wp_verify_nonce( $_GET['delete_nonce'], 'delete' ) ) {
			$package_id = absint( $_REQUEST['package_id'] );
			$wpdb->delete( "{$wpdb->prefix}listeo_core_user_packages", array(
				'id' => $package_id,
			) );
			$wpdb->delete( $wpdb->postmeta, array(
				'meta_key' => '_user_package_id',
				'meta_value' => $package_id,
			) );
			echo sprintf( '<div class="updated"><p>%s</p></div>', __( 'Package successfully deleted', 'listeo_core' ) );
		}

		if ( 'add' === $action || 'edit' === $action ) {
			$this->add_package_page();
		} else {
			include_once( dirname( __FILE__ ) . '/class-listeo-core-paid-listings-admin-packages.php' );
			$table = new Listeo_Core_Admin_Packages();
			$table->prepare_items();
			?>
			<div class="wrap listeo-package-products-manager">
				<h2><?php _e( 'Listing Packages', 'listeo_core' ); ?> <a href="<?php echo esc_url( add_query_arg( 'action', 'add', admin_url( 'admin.php?page=listeo_core_paid_listings_packages' ) ) ); ?>" class="add-new-h2"><?php _e( 'Add User Package', 'listeo_core' ); ?></a></h2>
				<form id="package-management" method="get">
					<input type="hidden" name="page" value="listeo_core_paid_listings_packages" />
					<?php $table->display() ?>
					<?php wp_nonce_field( 'save', 'listeo_core_paid_listings_packages_nonce' ); ?>
				</form>
			</div>
			<?php
		}
	}

	/**
	 * Add package
	 */
	public function add_package_page() {
		include_once( dirname( __FILE__ ) . '/class-listeo-core-paid-listings-admin-add-package.php' );
		$add_package = new Listeo_Core_Admin_Add_Package();
		?>
		<div class="woocommerce wrap">
			<h2><?php _e( 'Add User Package', 'listeo_core' ); ?></h2>
			<form id="package-add-form" method="post">
				<input type="hidden" name="page" value="listeo_core_paid_listings_packages" />
				<?php $add_package->form() ?>
				<?php wp_nonce_field( 'save', 'listeo_core_paid_listings_packages_nonce' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Filters and sorting handler
	 *
	 * @param  WP_Query $query
	 * @return WP_Query
	 */
	public function parse_query( $query ) {
		global $typenow;

		if ( 'listing' === $typenow  ) {
			if ( isset( $_GET['package'] ) ) {
				$query->query_vars['meta_key']   = '_user_package_id';
				$query->query_vars['meta_value'] = absint( $_GET['package'] );
			}
		}

		return $query;
	}

	/**
	 * AJAX handler to save listing types
	 */
	public function ajax_save_listing_types() {
		check_ajax_referer( 'listeo_package_manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'listeo_core' ) ) );
		}

		$package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
		$allowed_types = isset( $_POST['allowed_types'] ) ? $_POST['allowed_types'] : array();

		if ( ! $package_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package ID', 'listeo_core' ) ) );
		}

		// Sanitize the allowed types
		if ( ! empty( $allowed_types ) && is_array( $allowed_types ) ) {
			$allowed_types = array_map( 'sanitize_title', $allowed_types );
			update_post_meta( $package_id, '_allowed_listing_types', $allowed_types );
		} else {
			delete_post_meta( $package_id, '_allowed_listing_types' );
		}

		// Get the updated listing type names for response
		include_once( dirname( __FILE__ ) . '/class-listeo-core-paid-listings-admin-packages.php' );
		$table = new Listeo_Core_Admin_Packages();
		$listing_types = $table->get_listing_type_options();
		$type_names = array();

		if ( empty( $allowed_types ) ) {
			$type_names[] = array(
				'slug' => 'all',
				'name' => __( 'All Types', 'listeo_core' ),
				'is_all' => true,
			);
		} else {
			foreach ( $allowed_types as $slug ) {
				if ( isset( $listing_types[ $slug ] ) ) {
					$type_names[] = array(
						'slug' => $slug,
						'name' => $listing_types[ $slug ],
						'is_all' => false,
					);
				}
			}
		}

		wp_send_json_success( array(
			'message' => __( 'Listing types updated successfully', 'listeo_core' ),
			'types' => $type_names,
		) );
	}
}
Listeo_Core_Paid_Listings_Admin::get_instance();
