<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Listeo_Core_Admin_Packages class.
 *
 * @extends WP_List_Table
 */
class Listeo_Core_Admin_Packages_Listings extends WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'package',
			'plural'   => 'packages',
			'ajax'     => false,
		) );
	}


	/**
	 * Get column default
	 *
	 * @param object $item
	 * @param string $column_name
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		global $wpdb;

		switch ( $column_name ) {
			case 'listing_id' :
			
				$product = get_post( $item );
				// get author of listing
				
				return $product ? '<a href="' . admin_url( 'post.php?post=' . absint( $item  ) . '&action=edit' ) . '">' . esc_html( get_the_title($item ) ) . '</a>' : __( 'n/a', 'listeo_core' );
		
			case 'author':
				
				// get author of post
				$product = get_post( $item );
				$post_author_id = get_post_field( 'post_author', $item );
				$user = get_userdata($post_author_id);
				$author = $user->display_name;

				return $author;
				
			case 'user_package':
				  
		    	$post_author_id = get_post_field( 'post_author', $item );
		    	$user_package = get_post_meta($item,'_user_package_id',true);
		    	//echo $user_package;
		    	//$user_packages = listeo_core_available_packages($post_author_id,$user_package);
		    	if($user_package){
		    		$package = listeo_core_get_package_by_id($user_package);	
		    		
		    		if($package && $package->product_id){
		    			return get_the_title($package->product_id);
		    		};
		    		//return $package->get_title();
		    	}
		    	
		    	
				//return get_post_meta($item,'_user_package_id',true);
				break;
			case 'listing_actions' :
				$user_package = get_post_meta($item,'_user_package_id',true);
				$edit_url = esc_url( add_query_arg( array(
					'action' => 'edit',
					'listing_id' => $item,
					'package_id' => $user_package,
				), admin_url( 'admin.php?page=listeo_core_paid_listings_package_editor' ) ) );
				return '<div class="actions">' .
					'<a class="button button-icon icon-edit" href="' . $edit_url . '">' . __( 'Change package', 'listeo_core' ) . '</a>' .
					'</div>' .
					'</div>';
		}// End switch().
	}

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'listing_id'      => __( 'Listing', 'listeo_core' ),
			'author'      => __( 'Author', 'listeo_core' ),
			'user_package'      => __( 'Package', 'listeo_core' ),
			'listing_actions'  => __( 'Actions', 'listeo_core' ),
		);
		return $columns;

	
	}

	function extra_tablenav( $which ) {
		?><div class="alignleft actions"><?php
			
	        if ( 'top' === $which && !is_singular() ) {
		        
	            ob_start();
	            
	           // $this->dates_dropdown();
	            
	          // $this->listings_dropdown();
	          
	            $this->author_dropdown('owner',"Select User");
	            /**
	             * Fires before the Filter button on the Productions list table.
	             *
	             * Syntax resembles 'restrict_manage_posts' filter in 'wp-admin/includes/class-wp-posts-list-table.php'.
	             *
	             * @since 0.15.17
	             *
	             * @param string $post_type The post type slug.
	             * @param string $which     The location of the extra table nav markup:
	             *                          'top' or 'bottom'.
	             */
	            do_action( 'restrict_manage_productions', $this->screen->post_type, $which );
	 
	            $output = ob_get_clean();
	 
	            if ( ! empty( $output ) ) {
	                echo $output;
	                submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
	            }
	            
	        }
        
        	if ( isset( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] === 'trash' ) {
				submit_button( __( 'Empty Trash' ), 'apply', 'delete_all', false );
			}
			
		?></div><?php
		do_action( 'manage_posts_extra_tablenav', $which );
	}

	function listings_dropdown( ) {


		$string = '<select name="listing_id">
            <option  value="" selected>Select Listing</option>';
 
		$args = array( 'numberposts' => '-1', 'post_status' => 'publish', 'post_type' => 'listing' );
		 
		$recent_posts = wp_get_recent_posts($args);
		    foreach( $recent_posts as $recent ){
		    	$selected_id = empty( $_REQUEST['listing_id'] ) ? '' :  $_REQUEST['listing_id'];
		    	
		    	if($recent['ID'] == $selected_id ) {

		    		$selected = 'selected';
		    	} else {
		    		$selected = '';
		    	}
		        
		        $string .= '<option '.$selected.' value="' .$recent["ID"] . '">' .   $recent["post_title"].'</option> ';
		    }
		 
		$string .= '</select>';

		
		echo '<label class="screen-reader-text" for="cat">' . __( 'Filter by category' ) . '</label>';
		echo $string;
		
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'listing_id'      => array( 'listing_id', true ),
		
		);
		return $sortable_columns;
	}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @param string $which
	 */
	public function display_tablenav( $which ) {
		
    $this->search_box( __( 'Search Listings' ), 'search-listeo_core_paid_listings_package_editor' ); 
    ?>
    <input type="hidden" name="page" value="<?= esc_attr($_REQUEST['page']) ?>"/>

	<?php
	parent::display_tablenav( $which );
	}


	/**
	 * Prepares the list of items for displaying.
	 *
	 * @uses WP_List_Table::set_pagination_args()
	 *
	 * @access public
	 */
	public function prepare_items() {
		global $wpdb;

		$current_page          = $this->get_pagenum();
		$per_page              = 30;
		$order                 = empty( $_REQUEST['order'] ) || $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';
		$order_id              = ! empty( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : '';
		$listing_id              = ! empty( $_REQUEST['listing_id'] ) ? absint( $_REQUEST['listing_id'] ) : '';
		$search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : false;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		

		$where                 = array( 'WHERE post_type="listing"' );


		$paged = ( isset( $_REQUEST['paged'] ) ) ? intval( $_REQUEST['paged'] ) : 1;
		
	

		$args = array(
				'paged'          => $paged,
				'posts_per_page' => $per_page,
				'post_type'      => 'listing',
				'post_status'    => 'any',
				'orderby'        => 'title',
				'order'          => 'ASC',
				's'          => $search
			);


		if ( isset( $_REQUEST['orderby'] ) ) {
			switch ( $_REQUEST['orderby'] ) {
				case 'display_name':
					$args['orderby'] = 'title';
					break;
				
			}
		}
		if ( isset( $_REQUEST['listing_id'] ) ) {
			$args['p'] =  $_REQUEST['listing_id'];
		}
		if ( isset( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ) ) ) {
			$args['order'] = strtoupper( $_REQUEST['order'] );
		}
		
		$listings = new WP_Query( $args );

		$items = array();
		foreach ( $listings->get_posts() as $listing ) {
			$items[] = $listing->ID;
		}
	

		$this->items = $items;
		$max = $listings->found_posts;

		$this->set_pagination_args( array(
			'total_items' => $max,
			'per_page'    => $per_page,
			'total_pages' => ceil( $max / $per_page ),
		) );
	}
}
