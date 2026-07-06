<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin
 */
class Listeo_Core_Paid_Listings_Admin_Listings {

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
		//add_filter( 'woocommerce_screen_ids', array( $this, 'add_screen_ids' ) );
		//add_filter( 'job_manager_admin_screen_ids', array( $this, 'add_screen_ids' ) );
		
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
			'users_page_listeo_core_paid_listings_package_editor'
		) );
	}

	

	/**
	 * Add menu items
	 */
	public function admin_menu() {
		add_submenu_page( 'listeo-fields-and-form', __( 'Listings Packages', 'listeo_core' ), __( 'Listings Packages Manager', 'listeo_core' ), 'manage_options', 'listeo_core_paid_listings_package_editor' , array( $this, 'listing_packages_page' ) );
	}

	/**
	 * Manage Packages
	 */
	public function listing_packages_page() {
		global $wpdb;

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

	

		if (  'edit' === $action ) {
			$this->edit_listing_package_page();
		} else {
			include_once( dirname( __FILE__ ) . '/class-listeo-core-paid-listings-admin-listings-table.php' );
			$table = new Listeo_Core_Admin_Packages_Listings();
			$table->prepare_items();
			?>
			<div class="woocommerce wrap">
				<h2><?php _e( 'Listing\'s Packages', 'listeo_core' ); ?> </h2>
				<form id="listing-package-management" method="POST">
					<input type="hidden" name="page" value="listeo_core_paid_listings_package_editor" />
					<?php $table->display() ?>
					<?php wp_nonce_field( 'save', 'listeo_core_paid_listings_package_editor_nonce' ); ?>
				</form>
			</div>
			<?php
		}
	}

	/**
	 * Add package
	 */
	public function edit_listing_package_page() {
		include_once( dirname( __FILE__ ) . '/class-listeo-core-paid-listings-admin-edit-listing-package.php' );
		$add_package = new Listeo_Core_Admin_Edit_Listing_Package();
		?>
		<div class="woocommerce wrap">
			<h2><?php _e( 'Edit Listing Package', 'listeo_core' ); ?></h2>
			<form id="package-edit-listing-form" method="post">
				<input type="hidden" name="page" value="listeo_core_paid_listings_package_editor" />
				<?php $add_package->form() ?>
				<?php wp_nonce_field( 'save', 'listeo_core_paid_listings_package_editor_nonce' ); ?>
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
}
Listeo_Core_Paid_Listings_Admin_Listings::get_instance();
