<?php 

if ( ! defined( 'ABSPATH' )) exit; //  Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Bookings_Admin_List extends WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Booking', 'listeo_core' ), // singular name of the listed records
			'plural'   => __( 'Bookings', 'listeo_core' ), // plural name of the listed records
			'ajax'     => false // does this table support ajax?
		] );

	}


	/**
	 * Retrieve bookings data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 * @param int $id
	 *
	 * @return mixed
	 */
	public  function get_bookings( $args, $page_number ) {

		global $wpdb;
		if(!$page_number) {
			$page_number = 1;
		}
		
		$sql = "SELECT * FROM {$wpdb->prefix}bookings_calendar";
		$sql .= ' WHERE `status` IS NOT NULL';
		$prepare_values = array();

		if( isset($args['listing_id']) && !empty($args['listing_id']) ){
			$sql .= ' AND `listing_id` = %d';
			$prepare_values[] = $args['listing_id'];
		}

		if( isset($args['owner']) && !empty($args['owner']) ){
			$sql .= ' AND `owner_id` = %d';
			$prepare_values[] = $args['owner'];
		}
		if( isset($args['guest']) && !empty($args['guest']) ){
			$sql .= ' AND `bookings_author` = %d';
			$prepare_values[] = $args['guest'];
		}
		if( isset($args['status']) && !empty($args['status']) ){
			$sql .= ' AND `status` = %s';
			$prepare_values[] = $args['status'];
		}

		if ( isset($args['id']) ) {
			$sql .= ' AND `ID` = %d';
			$prepare_values[] = $args['id'];
		}

		/**
		 * Let add-ons append additional `WHERE` clauses (already-prepared)
		 * and prepare values. The filter receives the SQL fragment built
		 * so far plus the request args, and must return an array of
		 * `[fragment, values_array]`. Returning an empty fragment leaves
		 * the query unchanged.
		 *
		 * Example use: filter bookings by an assigned resource id stored
		 * inside the JSON `comment` column.
		 */
		$extra = apply_filters( 'listeo_bookings_admin_query_where', array( '', array() ), $args, $_REQUEST );
		if ( is_array( $extra ) && ! empty( $extra[0] ) ) {
			$sql .= ' ' . $extra[0];
			if ( ! empty( $extra[1] ) && is_array( $extra[1] ) ) {
				$prepare_values = array_merge( $prepare_values, $extra[1] );
			}
		}

		if (!empty($_REQUEST['orderby'])) {
			$allowed_orderby = array(
				'Client'     => 'bookings_author',
				'Owner'      => 'owner_id',
				'Listing'    => 'listing_id',
				'Start date' => 'date_start',
				'End date'   => 'date_end',
				'Type'       => 'type',
				'Status'     => 'status',
				'Created'    => 'created',
				'Price'      => 'price',
			);
			$orderby = isset($allowed_orderby[$_REQUEST['orderby']]) ? $allowed_orderby[$_REQUEST['orderby']] : '';
			if ($orderby) {
				$order = (!empty($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), array('ASC', 'DESC'))) ? strtoupper($_REQUEST['order']) : 'ASC';
				$sql .= " ORDER BY `{$orderby}` {$order}";
			}
		}

		if ( !isset($args['id']) ) {
			$sql .= " LIMIT %d OFFSET %d";
			$prepare_values[] = $args['per_page'];
			$prepare_values[] = ( $page_number - 1 ) * $args['per_page'];
		}

		if (!empty($prepare_values)) {
			$sql = $wpdb->prepare($sql, $prepare_values);
		}

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}


	/**
	 * Delete a booking record.
	 *
	 * @param int $id booking ID
	 */
	public static function delete_booking( $id ) {

		global $wpdb;

		$wpdb->delete(
			"{$wpdb->prefix}bookings_calendar",
			[ 'ID' => $id ],
			[ '%d' ]
		);

	}

	/**
	 * Update a booking record.
	 *
	 * @param array $values to change
	 * 
	 * @return number $records that was changed
	 */
	public static function update_booking( $values ) {

		global $wpdb;

		return $wpdb->update ( "{$wpdb->prefix}bookings_calendar", $values, array('ID' => $values['ID']) );

	}

	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count($args) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}bookings_calendar";
		$sql .= ' WHERE `status` IS NOT NULL';
		$prepare_values = array();

		if( isset($args['listing_id']) && !empty($args['listing_id']) ){
			$sql .= ' AND `listing_id` = %d';
			$prepare_values[] = $args['listing_id'];
		}

		if( isset($args['owner']) && !empty($args['owner']) ){
			$sql .= ' AND `owner_id` = %d';
			$prepare_values[] = $args['owner'];
		}
		if( isset($args['guest']) && !empty($args['guest']) ){
			$sql .= ' AND `bookings_author` = %d';
			$prepare_values[] = $args['guest'];
		}
		if( isset($args['status']) && !empty($args['status']) ){
			$sql .= ' AND `status` = %s';
			$prepare_values[] = $args['status'];
		}

		// Mirror the WHERE filter from `get_bookings()` so the row
		// count stays in sync with the displayed page after add-ons
		// narrow the result set.
		$extra = apply_filters( 'listeo_bookings_admin_query_where', array( '', array() ), $args, $_REQUEST );
		if ( is_array( $extra ) && ! empty( $extra[0] ) ) {
			$sql .= ' ' . $extra[0];
			if ( ! empty( $extra[1] ) && is_array( $extra[1] ) ) {
				$prepare_values = array_merge( $prepare_values, $extra[1] );
			}
		}

		if (!empty($prepare_values)) {
			$sql = $wpdb->prepare($sql, $prepare_values);
		}

		return $wpdb->get_var( $sql );
	}


	/** Text displayed when no booking data is available */
	public function no_items() {
		_e( 'No bookings avaliable.', 'listeo_core' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {
			case 'ID':
				return sprintf(
					'<strong>#%s</strong><br><a href="#" class="view-booking-details" data-booking-id="%s">%s</a>',
					$item[$column_name],
					$item['ID'],
					__('View Details', 'listeo_core')
				);

			case 'date_start':
			case 'date_end':
				return date_i18n(get_option('date_format'), strtotime($item[$column_name]));

			case 'order_id':
				if ($item[$column_name]) {
					return sprintf(
						'<a href="%s" target="_blank">#%s</a>',
						admin_url('post.php?post=' . $item[$column_name] . '&action=edit'),
						$item[$column_name]
					);
				}
				return '—';

			case 'status':
				$status = $item[$column_name];
				$status_label = ucfirst(str_replace('_', ' ', $status));
				return sprintf(
					'<span class="status-badge %s">%s</span>',
					esc_attr($status),
					esc_html($status_label)
				);

			case 'type':
				return ucfirst($item[$column_name]);

			case 'price':
				if ($item[$column_name]) {
					$currency_abbr = get_option('listeo_currency');
					$currency_position = get_option('listeo_currency_postion');
					$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
					$decimals = get_option('listeo_number_decimals', 2);

					if ($currency_position == 'before') {
						return $currency_symbol . ' ' . number_format_i18n($item[$column_name], $decimals);
					} else {
						return number_format_i18n($item[$column_name], $decimals) . ' ' . $currency_symbol;
					}
				}
				return '—';

			case 'expiring':
			case 'created':
				if ($item[$column_name] && $item[$column_name] != '0000-00-00 00:00:00') {
					return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item[$column_name]));
				}
				return '—';

			case 'listing_id':
				$title = get_the_title($item[$column_name]);
				return sprintf(
					'<a href="%s" target="_blank"><strong>%s</strong></a>',
					get_permalink($item[$column_name]),
					esc_html($title)
				);

			case 'owner_id':
				if ($item[$column_name] != 0) {
					$user_data = get_userdata($item[$column_name]);
					if ($user_data) {
						$avatar = get_avatar($item[$column_name], 32);
						return sprintf(
							'<div class="user-display">%s<a href="%s">%s</a></div>',
							$avatar,
							get_edit_user_link($user_data->ID),
							esc_html($user_data->display_name)
						);
					}
					return __('Unknown user', 'listeo_core');
				} else {
					return '<em>' . esc_html__('iCal import', 'listeo_core') . '</em>';
				}

			case 'bookings_author':
				if ($item[$column_name] != 0) {
					$user_data = get_userdata($item[$column_name]);
					if ($user_data) {
						$avatar = get_avatar($item[$column_name], 32);
						return sprintf(
							'<div class="user-display">%s<a href="%s">%s</a></div>',
							$avatar,
							get_edit_user_link($user_data->ID),
							esc_html($user_data->display_name)
						);
					}
					return __('Unknown user', 'listeo_core');
				} else {
					return '<em>' . esc_html__('iCal import', 'listeo_core') . '</em>';
				}

			case 'action':
				return sprintf(
					'<div class="quick-actions">
						<button class="quick-action-btn view-booking-details" data-booking-id="%s" title="%s">👁</button>
						<a href="?page=%s&action=edit&id=%s" class="quick-action-btn" title="%s">✏️</a>
						<button class="quick-action-btn delete quick-delete-booking" data-booking-id="%s" title="%s">🗑</button>
					</div>',
					$item['ID'],
					__('View Details', 'listeo_core'),
					$_REQUEST['page'],
					$item['ID'],
					__('Edit', 'listeo_core'),
					$item['ID'],
					__('Delete', 'listeo_core')
				);
		}

		/**
		 * Fallback for columns added by add-on plugins via the
		 * `listeo_bookings_admin_columns` filter. Returning an empty
		 * string here when nothing hooks the filter keeps the cell
		 * rendering harmless instead of WP_List_Table printing the
		 * raw column key.
		 */
		return apply_filters( 'listeo_bookings_admin_column_value', '', $item, $column_name );
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']
		);

	}
	function author_dropdown($role,$label){
		wp_dropdown_users(array(
	        'show_option_all' => $label,
	        'selected'        => get_query_var($role, 0),
	        'name'            => $role,
	       // 'role'			 => $role
	    ));
	}

	function status_dropdown(){
		$selected = empty($_REQUEST['status']) ? '' :  $_REQUEST['status'];
		$string = '<select name="status">
            <option  value="" selected>Select Status</option>';
	
		$string .= '<option '.selected($selected,'confirmed', false).' value="confirmed" >Confirmed</option>';
		$string .= '<option ' . selected($selected, 'waiting', false) . '  value="waiting" >Waiting</option>';
		$string .= '<option ' . selected($selected, 'approved', false) . '   value="approved" >Approved</option>';
		$string .= '<option ' . selected($selected, 'paid', false) . '   value="paid" >Paid</option>';
		$string .= '<option ' . selected($selected, 'pay_to_confrim', false) . '   value="pay_to_confrim" >Pay to Confirm</option>';
		$string .= '<option ' . selected($selected, 'cancelled', false) . '   value="cancelled" >Cancelled</option>';
		$string .= '<option ' . selected($selected, 'expired', false) . '   value="expired" >Expired</option>';
		$string .= '</select>';


		echo '<label class="screen-reader-text" for="cat">' . __('Filter by status') . '</label>';
		echo $string;
	}
	function listings_dropdown( ) {

		$selected = empty($_REQUEST['listing_id']) ? '' :  $_REQUEST['listing_id'];
	
		$title =empty($_REQUEST['listing_id']) ? '' :  get_the_title($_REQUEST['listing_id']);
		$string = '<label class="screen-reader-text" for="cat">' . __( 'Select listing' ) . '</label>';
		
			$string .= '<input type="text" value="'.$title.'" placeholder="Type a listing title"   id="booking_admin-listing_id_autocomplete"  size="20" />';
			$string .= '<input type="hidden" value="'.$selected.'" name="listing_id" id="booking_admin-listing_id"  size="20" />';
		
		echo $string;
		
	}	


	/**
	 * Displays a dates drop-down for filtering on the Events list table.
	 *
	 * @since 0.16
	 */
	function dates_dropdown( ) {

		$options = array (
			'0' => __( 'All dates' ),
			'upcoming' => __( 'Upcoming bookings', 'listeo_core' ),
			'past' => __( 'Past bookings', 'listeo_core' ),			
			'today' => __( 'Today', 'listeo_core' ),			
			'last7days' => __( 'Last 7 days', 'listeo_core' ),			
		);

		$date = false;
		if ( !empty( $_REQUEST['date'] ) ) {
			$date = $_REQUEST['date'];
		}

		?><label class="screen-reader-text" for="date"><?php
			_e( 'Filter by date', 'listeo_core' ); 
		?></label>
		<select id="date" name="date"><?php
			foreach( $options as $key => $value ) {
				?><option value="<?php echo $key; ?>" <?php selected( $date, $key, true );?>><?php 
					echo $value;
				?></option><?php				
			}
		?></select><?php
					
	}


	function extra_tablenav( $which ) {
		?><div class="alignleft actions"><?php
			
	        if ( 'top' === $which && !is_singular() ) {
		        
	            ob_start();
	            
	           // $this->dates_dropdown();
	            
	            $this->listings_dropdown();
	            $this->status_dropdown();
	            $this->author_dropdown('guest',"Select Client");
	            $this->author_dropdown('owner',"Select Owner");
				/**
				 * Let add-on plugins render extra filter widgets next
				 * to the built-in dropdowns. Use this to add things
				 * like "Filter by Resource".
				 *
				 * @param string $which 'top' or 'bottom' tablenav location.
				 */
				do_action( 'listeo_bookings_admin_extra_tablenav', $which );
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


	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {

		$columns = [
			'cb'      			=> '<input type="checkbox" />',
			'ID'    			=> __( 'ID', 'listeo_core' ),
			'bookings_author' 	=> __( 'Client', 'listeo_core' ),
			'owner_id'    		=> __( 'Owner', 'listeo_core' ),
			'listing_id' 		=> __( 'Listing', 'listeo_core' ),
			'date_start' 		=> __( 'Start date', 'listeo_core' ),
			'date_end' 			=> __( 'End date', 'listeo_core' ),
			'type' 				=> __( 'Type', 'listeo_core' ),
			'status' 				=> __( 'Status', 'listeo_core' ),
			'created' 			=> __( 'Created', 'listeo_core' ),
			'price' 			=> __( 'Price', 'listeo_core' ),
			'action' 			=> __( 'Action', 'listeo_core' )
		];

		/**
		 * Allow add-on plugins (e.g. Listeo Booking Plus) to inject
		 * extra columns into the admin Bookings list. The return value
		 * MUST keep the `cb` first and `action` last for the table
		 * chrome to look right — consumers usually splice their
		 * column(s) somewhere in the middle.
		 */
		return apply_filters( 'listeo_bookings_admin_columns', $columns );
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'ID' 			=> array( 'ID', true ),
			'city' 			=> array( 'city', false ),
			'bookings_author' => array( 'Client', true ),
			'owner_id' 		=> array( 'Owner', true ),
			'listing_id' 	=> array( 'Listing', true ),
			'date_start' 	=> array( 'Start date', true ),
			'date_end' 		=> array( 'End date', true ),
			'type' 			=> array( 'Type', true ),
			'created' 		=> array( 'Created', true ),
			'price' 		=> array( 'Price', true ),
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete'
		];

		return $actions;
	}

	
	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		
	    $columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/** Process bulk action */
		$this->process_bulk_action();


	    if ( ! empty( $_REQUEST['listing_id'] ) ) {
		    $args['listing_id'] = sanitize_text_field( $_REQUEST['listing_id'] );
	    } 

	    if ( ! empty( $_REQUEST['id'] ) ) {
		    $args['id'] = sanitize_text_field( $_REQUEST['id'] );
	    } 
	    if ( ! empty( $_REQUEST['owner'] ) ) {
		    $args['owner'] = sanitize_text_field( $_REQUEST['owner'] );
	    } 
	    if ( ! empty( $_REQUEST['guest'] ) ) {
		    $args['guest'] = sanitize_text_field( $_REQUEST['guest'] );
	    }
	    if ( ! empty( $_REQUEST['status'] ) ) {
		    $args['status'] = sanitize_text_field( $_REQUEST['status'] );
	    }


		$args['per_page']     = $this->get_items_per_page( 'per_page', 20 );
		
		$current_page 	= $this->get_pagenum();
  		$columns 		= $this->get_columns();

		$total_items  	= self::record_count($args);



		$this->set_pagination_args( [
			'total_items' => $total_items, // WE have to calculate the total number of items
			'per_page'    => $args['per_page'] // WE have to determine how many items to show on a page
		] );

		$this->items = self::get_bookings( $args, $current_page );
	}

	public function process_bulk_action() {

		// Edit action
		// Detect when a bulk action is being triggered
		if ('delete' === $this->current_action()) {


			self::delete_booking(absint($_GET['id']));

			//  esc_url_raw() is used to prevent converting ampersand in url to "#038;"
			//  add_query_arg() return the current url
			wp_redirect(admin_url('admin.php?page=listeo_bookings_manage'));
			// exit;


		}

		//  If the delete bulk action is triggered
		if ('bulk-delete' == $this->current_action()) {

			$delete_ids = esc_sql($_GET['bulk-delete']);
			
			//  lvoop over the array of record IDs and delete them
			foreach ($delete_ids as $id) {
				self::delete_booking($id);
			}

			//  esc_url_raw() is used to prevent converting ampersand in url to "#038;"
			//  add_query_arg() return the current url
			//   wp_redirect( esc_url_raw(add_query_arg()) );
			//	exit;
		}


		if ( 'view' === $this->current_action()) { 
			$args['id'] = $_GET['id'];
			$booking = self::get_bookings( $args, NULL);
			
			?>
			<style>
	
			</style>
			
			<div class="list-box-listing bookings">
			<div class="list-box-listing-img"><a href="<?php echo get_author_posts_url($booking[0]['bookings_author']); ?>"><?php echo get_avatar($booking[0]['bookings_author'], '70') ?></a></div>
			<div class="list-box-listing-content">
			<div class="inner">
				<h3 id="title"><a href="<?php echo get_permalink($booking[0]['listing_id']); ?>"><?php echo get_the_title($booking[0]['listing_id']); ?></a></h3>

				<div class="inner-booking-list">
					<h5><?php esc_html_e('Booking Date:', 'listeo_core'); ?></h5>
					<ul class="booking-list">
						<?php 
						//get post type to show proper date
						$listing_type = get_post_meta($booking[0]['listing_id'],'_listing_type', true);

						if(listeo_get_booking_type($booking[0]['id']) == 'date_range') { ?>
							<li class="highlighted" id="date"><?php echo date_i18n(get_option( 'date_format' ), strtotime($booking[0]['date_start'])); ?> - <?php echo date_i18n(get_option( 'date_format' ), strtotime($booking[0]['date_end'])); ?></li>
						
						<?php } 
							else if(listeo_get_booking_type($booking[0]['id']) == 'single_day') { 
						?>
							<li class="highlighted" id="date">
								<?php echo date_i18n(get_option( 'date_format' ), strtotime($booking[0]['date_start'])); ?> <?php esc_html_e('at','listeo_core'); ?> 
								<?php 
									$time_start = date_i18n(get_option( 'time_format' ), strtotime($booking[0]['date_start']));
									$time_end = date_i18n(get_option( 'time_format' ), strtotime($booking[0]['date_end']));?>

								<?php echo $time_start ?> <?php if($time_start != $time_end) echo '- '.$time_end; ?></li>
						
						<?php } else { 
							//event ?>
							<li class="highlighted" id="date">
							<?php 
							$meta_value = get_post_meta($booking[0]['listing_id'],'_event_date',true);
							$meta_value_date = explode(' ', $meta_value,2); 

							$meta_value_date[0] = str_replace('/','-',$meta_value_date[0]);
							$meta_value = date_i18n(get_option( 'date_format' ), strtotime($meta_value_date[0])); 
							
						
							//echo strtotime(end($meta_value_date));
							//echo date( get_option( 'time_format' ), strtotime(end($meta_value_date)));
							if( isset($meta_value_date[1]) ) { 
								$time = str_replace('-','',$meta_value_date[1]);
								$meta_value .= esc_html__(' at ','listeo_core'); 
								$meta_value .= date_i18n(get_option( 'time_format' ), strtotime($time));

							} echo $meta_value;

							$meta_value = get_post_meta($booking[0]['listing_id'],'_event_date_end',true);
							if(isset($meta_value) && !empty($meta_value))  : 
							
							$meta_value_date = explode(' ', $meta_value,2); 

							$meta_value_date[0] = str_replace('/','-',$meta_value_date[0]);
							$meta_value = date_i18n(get_option( 'date_format' ), strtotime($meta_value_date[0])); 
							
						
							//echo strtotime(end($meta_value_date));
							//echo date( get_option( 'time_format' ), strtotime(end($meta_value_date)));
							if( isset($meta_value_date[1]) ) { 
								$time = str_replace('-','',$meta_value_date[1]);
								$meta_value .= esc_html__(' at ','listeo_core'); 
								$meta_value .= date_i18n(get_option( 'time_format' ), strtotime($time));

							} echo ' - '.$meta_value; ?>
							<?php endif; ?>
							</li>
						<?php }
						 ?>

					</ul>
				</div>

				<?php $details = json_decode($booking[0]['comment']); 

				
				if (
				 	(isset($details->children) && $details->children > 0)
				 	||
				 	(isset($details->adults) && $details->adults > 0)
				 	||
				 	(isset($details->tickets) && $details->tickets > 0)
				) { ?>			
				<div class="inner-booking-list">
					<h5><?php esc_html_e('Booking Details:', 'listeo_core'); ?></h5>
					<ul class="booking-list">
						<li class="highlighted" id="details">
						<?php if( isset($details->children) && $details->children > 0) : ?>
							<?php printf( _n( '%d Child', '%d Children', $details->children, 'listeo_core' ), $details->children ) ?>
						<?php endif; ?>
						<?php if( isset($details->adults)  && $details->adults > 0) : ?>
							<?php printf( _n( '%d Guest', '%d Guests', $details->adults, 'listeo_core' ), $details->adults ) ?>
						<?php endif; ?>
						<?php if( isset($details->tickets)  && $details->tickets > 0) : ?>
							<?php printf( _n( '%d Ticket', '%d Tickets', $details->tickets, 'listeo_core' ), $details->tickets ) ?>
						<?php endif; ?>
						</li>
					</ul>
				</div>	
				<?php } ?>	
				
				<?php
				$currency_abbr = get_option( 'listeo_currency' );
				$currency_postion = get_option( 'listeo_currency_postion' );
				$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
				$decimals = get_option('listeo_number_decimals',2);

				if($booking[0]['price']): ?>
				<div class="inner-booking-list">
					<h5><?php esc_html_e('Price:', 'listeo_core'); ?></h5>
					<ul class="booking-list">
						<li class="highlighted" id="price">
							<?php if($currency_postion == 'before') { echo $currency_symbol.' '; } 
							?>
							<?php 	
							if(is_numeric($booking[0]['price'])){
							 	echo number_format_i18n($booking[0]['price'],$decimals);
							} else {
								echo esc_html($booking[0]['price']);
							}; ?>
							<?php if($currency_postion == 'after') { echo ' '.$currency_symbol; }  ?>
						</li>
					</ul>
				</div>	
				<?php endif; ?>	
				
				<div class="inner-booking-list">
					
					<h5><?php esc_html_e('Client:', 'listeo_core'); ?></h5>
					<ul class="booking-list" id="client">
						<?php if( isset($details->first_name) || isset($details->last_name) ) : ?>
						<li id="name">
							<a href="<?php echo get_author_posts_url($booking[0]['bookings_author']); ?>"><?php if(isset($details->first_name)) echo esc_html(stripslashes($details->first_name)); ?> <?php if(isset($details->last_name)) echo esc_html(stripslashes($details->last_name)); ?></a></li>
						<?php endif; ?>
						<?php if( isset($details->email)) : ?><li id="email"><a href="mailto:<?php echo esc_attr($details->email) ?>"><?php echo esc_html($details->email); ?></a></li>
						<?php endif; ?>
						<?php if( isset($details->phone)) : ?><li id="phone"><a href="tel:<?php echo esc_attr($details->phone) ?>"><?php echo esc_html($details->phone); ?></a></li>
						<?php endif; ?>
					</ul>
					
				</div>
				<?php if( isset($details->billing_address_1) ) : ?>
				<div class="inner-booking-list">
					
					<h5><?php esc_html_e('Address:', 'listeo_core'); ?></h5>
					<ul class="booking-list" id="client">
		
						<?php if( isset($details->billing_address_1) ) : ?>
							<li id="billing_address_1"><?php echo esc_html(stripslashes($details->billing_address_1)); ?> </li>
						<?php endif; ?>
						<?php if( isset($details->billing_address_1) ) : ?>
							<li id="billing_postcode"><?php echo esc_html(stripslashes($details->billing_postcode)); ?> </li>
						<?php endif; ?>	
						<?php if( isset($details->billing_city) ) : ?>
							<li id="billing_city"><?php echo esc_html(stripslashes($details->billing_city)); ?> </li>
						<?php endif; ?>
						<?php if( isset($details->billing_country) ) : ?>
							<li id="billing_country"><?php echo esc_html(stripslashes($details->billing_country)); ?> </li>
						<?php endif; ?>
						
					</ul>
				</div>
			<?php endif; ?>  
				<?php if( isset($details->service) && !empty($details->service)) : ?>
					<div class="inner-booking-list">
						<h5><?php esc_html_e('Extra Services:', 'listeo_core'); ?></h5>
						<?php echo listeo_get_extra_services_html($details->service); //echo wpautop( $details->service); ?>
					</div>	
				<?php endif; ?>
				<?php if( isset($details->message) && !empty($details->message)) : ?>
					<div class="inner-booking-list">
						<h5><?php esc_html_e('Message:', 'listeo_core'); ?></h5>
						<?php echo wpautop( esc_html(stripslashes($details->message))); ?>
					</div>	
				<?php endif; ?>


				<div class="inner-booking-list">
					<h5><?php esc_html_e('Request sent:', 'listeo_core'); ?></h5>
					<ul class="booking-list">
						<li class="highlighted" id="price">
							<?php echo date_i18n(get_option( 'date_format' ), strtotime($booking[0]['created'])); ?>
							<?php 
								$date_created = explode(' ', $booking[0]['created']); 
									if( isset($date_created[1]) ) { ?>
									<?php esc_html_e('at','listeo_core'); ?>
									
							<?php echo date_i18n(get_option( 'time_format' ), strtotime($date_created[1])); } ?>
						</li>
					</ul>
				</div>	

				<?php if(isset($booking[0]['expiring']) && $booking[0]['expiring'] != '0000-00-00 00:00:00' && $booking[0]['expiring'] != $booking[0]['created']) { ?>
				<div class="inner-booking-list">
					<h5><?php esc_html_e('Payment due:', 'listeo_core'); ?></h5>
					<ul class="booking-list">
						<li class="highlighted" id="price">
							<?php echo date_i18n(get_option( 'date_format' ), strtotime($booking[0]['expiring'])); ?>
							<?php 
								$date_expiring = explode(' ', $booking[0]['expiring']); 
									if( isset($date_expiring[1]) ) { ?>
									<?php esc_html_e('at','listeo_core'); ?>
									
							<?php echo date_i18n(get_option( 'time_format' ), strtotime($date_expiring[1])); } ?>
						</li>
					</ul>
				</div>	
				<?php } ?>

			
			</div>
		</div>
	</div>
		<?php 
		exit();
		}
		
	}

}


class Bookings_Admin_Plugin {

	//  class instance
	static $instance;

	//  booking WP_List_Table object
	public $bookings_obj;

	//  class constructor
	public function __construct() {

		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// AJAX handlers
		add_action( 'wp_ajax_listeo_get_booking_details', [ $this, 'ajax_get_booking_details' ] );
		add_action( 'wp_ajax_listeo_update_booking_status', [ $this, 'ajax_update_booking_status' ] );
		add_action( 'wp_ajax_listeo_delete_booking', [ $this, 'ajax_delete_booking' ] );
		add_action( 'wp_ajax_listeo_export_bookings_csv', [ $this, 'ajax_export_bookings_csv' ] );
	}
	// public function ajax_listing_search(){	
			
	// 	$s = wp_unslash($_GET['q']);

	// 		$comma = _x(',', 'page delimiter');
	// 		if (',' !== $comma
	// 		)
	// 		$s = str_replace($comma, ',', $s);
	// 		if (false !== strpos($s, ',')) {
	// 			$s = explode(',', $s);
	// 			$s = $s[count($s) - 1];
	// 		}
	// 		$s = trim($s);

	// 		$term_search_min_chars = 2;

	// 		$the_query = new WP_Query(
	// 				array(
	// 					's' => $s,
	// 					'posts_per_page' => 5,
	// 					'post_type' => 'page'
	// 				)
	// 			);

	// 		if ($the_query->have_posts()) {
	// 			while (
	// 				$the_query->have_posts()
	// 			) {
	// 				$the_query->the_post();
	// 				$results[] = get_the_title();
	// 			}
	// 			/* Restore original Post Data */
	// 			wp_reset_postdata();
	// 		} else {
	// 			$results = 'No results';
	// 		}

	// 		echo join($results, "\n");
	// 		wp_die();
	// 	});
	
	public static function set_screen( $status, $option, $value ) {

		return $value;

	}

	public function add_package_page() {
		$args['id'] = $_GET['id'];

		// Handle form submission
		if (isset($_POST['ID']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'edit_booking_' . $_POST['ID'])) {

			$service = isset($_POST['comment']['service']) ? json_decode(stripslashes($_POST['comment']['service'])) : array();

			$update_data = array(
				'bookings_author' => isset($_POST['bookings_author']) ? absint($_POST['bookings_author']) : 0,
				'owner_id' => isset($_POST['owner_id']) ? absint($_POST['owner_id']) : 0,
				'listing_id' => isset($_POST['listing_id']) ? absint($_POST['listing_id']) : 0,
				'date_start' => isset($_POST['date_start']) ? sanitize_text_field($_POST['date_start']) : '',
				'date_end' => isset($_POST['date_end']) ? sanitize_text_field($_POST['date_end']) : '',
				'order_id' => isset($_POST['order_id']) ? absint($_POST['order_id']) : 0,
				'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
				'expiring' => isset($_POST['expiring']) ? sanitize_text_field($_POST['expiring']) : '',
				'price' => isset($_POST['price']) ? sanitize_text_field($_POST['price']) : '',
				'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '',
				'created' => isset($_POST['created']) ? sanitize_text_field($_POST['created']) : '',
				'comment' => json_encode(array(
					'first_name' => isset($_POST['comment']['first_name']) ? stripslashes($_POST['comment']['first_name']) : '',
					'last_name' => isset($_POST['comment']['last_name']) ? stripslashes($_POST['comment']['last_name']) : '',
					'email' => isset($_POST['comment']['email']) ? stripslashes($_POST['comment']['email']) : '',
					'phone' => isset($_POST['comment']['phone']) ? stripslashes($_POST['comment']['phone']) : '',
					'adults' => isset($_POST['comment']['adults']) ? stripslashes($_POST['comment']['adults']) : '',
					'children' => isset($_POST['comment']['children']) ? stripslashes($_POST['comment']['children']) : '',
					'tickets' => isset($_POST['comment']['tickets']) ? stripslashes($_POST['comment']['tickets']) : '',
					'message' => isset($_POST['comment']['message']) ? stripslashes($_POST['comment']['message']) : '',
					'service' => $service,
					'billing_address_1' => isset($_POST['comment']['billing_address_1']) ? stripslashes($_POST['comment']['billing_address_1']) : '',
					'billing_postcode' => isset($_POST['comment']['billing_postcode']) ? stripslashes($_POST['comment']['billing_postcode']) : '',
					'billing_city' => isset($_POST['comment']['billing_city']) ? stripslashes($_POST['comment']['billing_city']) : '',
					'billing_country' => isset($_POST['comment']['billing_country']) ? stripslashes($_POST['comment']['billing_country']) : '',
					'coupon' => isset($_POST['comment']['coupon']) ? stripslashes($_POST['comment']['coupon']) : '',
					'price' => isset($_POST['comment']['price']) ? stripslashes($_POST['comment']['price']) : ''
				))
			);

			global $wpdb;
			$updated = $wpdb->update(
				$wpdb->prefix . 'bookings_calendar',
				$update_data,
				array('ID' => $_POST['ID']),
				array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'),
				array('%d')
			);

			if (false === $updated) {
				echo '<div class="notice notice-error"><p>' . __('Error while updating booking', 'listeo_core') . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . __('Booking successfully updated', 'listeo_core') . '</p></div>';
			}
		}

		$bookings_admin_list = new Bookings_Admin_List();
		$booking = $bookings_admin_list->get_bookings($args, NULL);

		if (empty($booking)) {
			wp_die(__('Booking not found', 'listeo_core'));
		}

		$booking = $booking[0];
		$details = json_decode($booking['comment']);
		?>
		<div class="wrap airs-admin-wrap">
			<div class="airs-header">
				<div class="airs-header-content">
					<div class="airs-header-icon">✏️</div>
					<div class="airs-header-text">
						<h1><?php printf(__('Edit Booking #%s', 'listeo_core'), $booking['ID']); ?></h1>
						<p><?php _e('Modify booking details, client information, and status', 'listeo_core'); ?></p>
					</div>
				</div>
			</div>

		<form action="" method="POST" class="booking-edit-form" style="margin-left: 20px; width: calc(100% - 25px);">
			<?php wp_nonce_field('edit_booking_' . $booking['ID']); ?>
			<input type="hidden" name="ID" value="<?php echo esc_attr($booking['ID']); ?>"/>

			<!-- Basic Info Section -->
			<div class="airs-card" style="margin-bottom: 2rem;">
				<div class="airs-card-header">
					<h3>📋 <?php _e('Basic Information', 'listeo_core'); ?></h3>
					<p><?php _e('Listing and booking type details', 'listeo_core'); ?></p>
				</div>
				<div class="airs-card-body">
					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Listing', 'listeo_core'); ?></label>
							<input type="number" name="listing_id" value="<?php echo esc_attr($booking['listing_id']); ?>" class="form-input"/>
							<p class="airs-help-text"><?php echo esc_html(get_the_title($booking['listing_id'])); ?></p>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('Booking Type', 'listeo_core'); ?></label>
							<select name="type" class="form-select">
								<option value="reservation" <?php selected($booking['type'], 'reservation'); ?>><?php _e('Reservation', 'listeo_core'); ?></option>
								<option value="rental" <?php selected($booking['type'], 'rental'); ?>><?php _e('Rental', 'listeo_core'); ?></option>
								<option value="service" <?php selected($booking['type'], 'service'); ?>><?php _e('Service', 'listeo_core'); ?></option>
								<option value="event" <?php selected($booking['type'], 'event'); ?>><?php _e('Event', 'listeo_core'); ?></option>
							</select>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Owner', 'listeo_core'); ?></label>
							<?php
							wp_dropdown_users(array(
								'selected' => $booking['owner_id'],
								'name' => 'owner_id',
								'class' => 'form-select',
								'show_option_all' => __('No owner (iCal import)', 'listeo_core')
							));
							?>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('Order ID', 'listeo_core'); ?></label>
							<input type="number" name="order_id" value="<?php echo esc_attr($booking['order_id']); ?>" class="form-input"/>
						</div>
					</div>

					<?php if (isset($details->adults) || isset($details->children) || isset($details->tickets)): ?>
					<div class="form-row">
						<?php if (isset($details->adults)): ?>
						<div class="form-group">
							<label class="form-label"><?php _e('Adults', 'listeo_core'); ?></label>
							<input type="number" name="comment[adults]" value="<?php echo esc_attr($details->adults); ?>" class="form-input"/>
						</div>
						<?php endif; ?>
						<?php if (isset($details->children)): ?>
						<div class="form-group">
							<label class="form-label"><?php _e('Children', 'listeo_core'); ?></label>
							<input type="number" name="comment[children]" value="<?php echo esc_attr($details->children); ?>" class="form-input"/>
						</div>
						<?php endif; ?>
						<?php if (isset($details->tickets)): ?>
						<div class="form-group">
							<label class="form-label"><?php _e('Tickets', 'listeo_core'); ?></label>
							<input type="number" name="comment[tickets]" value="<?php echo esc_attr($details->tickets); ?>" class="form-input"/>
						</div>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<?php if (isset($details->message) && !empty($details->message)): ?>
					<div class="form-group">
						<label class="form-label"><?php _e('Client Message', 'listeo_core'); ?></label>
						<textarea name="comment[message]" rows="4" class="form-textarea"><?php echo esc_textarea(stripslashes($details->message)); ?></textarea>
					</div>
					<?php endif; ?>

					<?php if (isset($details->service) && !empty($details->service)): ?>
					<div class="form-group">
						<label class="form-label"><?php _e('Extra Services (JSON)', 'listeo_core'); ?></label>
						<textarea name="comment[service]" rows="4" class="form-textarea"><?php echo esc_textarea(json_encode($details->service)); ?></textarea>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Client Details Section -->
			<div class="airs-card" style="margin-bottom: 2rem;">
				<div class="airs-card-header">
					<h3>👤 <?php _e('Client Information', 'listeo_core'); ?></h3>
					<p><?php _e('Client and billing details', 'listeo_core'); ?></p>
				</div>
				<div class="airs-card-body">
					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Client User', 'listeo_core'); ?></label>
							<?php
							wp_dropdown_users(array(
								'selected' => $booking['bookings_author'],
								'name' => 'bookings_author',
								'class' => 'form-select',
								'show_option_all' => __('No client (iCal import)', 'listeo_core')
							));
							?>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('First Name', 'listeo_core'); ?></label>
							<input type="text" name="comment[first_name]" value="<?php echo isset($details->first_name) ? esc_attr(stripslashes($details->first_name)) : ''; ?>" class="form-input"/>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('Last Name', 'listeo_core'); ?></label>
							<input type="text" name="comment[last_name]" value="<?php echo isset($details->last_name) ? esc_attr(stripslashes($details->last_name)) : ''; ?>" class="form-input"/>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Email', 'listeo_core'); ?></label>
							<input type="email" name="comment[email]" value="<?php echo isset($details->email) ? esc_attr($details->email) : ''; ?>" class="form-input"/>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('Phone', 'listeo_core'); ?></label>
							<input type="text" name="comment[phone]" value="<?php echo isset($details->phone) ? esc_attr($details->phone) : ''; ?>" class="form-input"/>
						</div>
					</div>

					<div class="form-group">
						<label class="form-label"><?php _e('Address', 'listeo_core'); ?></label>
						<input type="text" name="comment[billing_address_1]" value="<?php echo isset($details->billing_address_1) ? esc_attr(stripslashes($details->billing_address_1)) : ''; ?>" class="form-input"/>
					</div>

					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Postcode', 'listeo_core'); ?></label>
							<input type="text" name="comment[billing_postcode]" value="<?php echo isset($details->billing_postcode) ? esc_attr(stripslashes($details->billing_postcode)) : ''; ?>" class="form-input"/>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('City', 'listeo_core'); ?></label>
							<input type="text" name="comment[billing_city]" value="<?php echo isset($details->billing_city) ? esc_attr(stripslashes($details->billing_city)) : ''; ?>" class="form-input"/>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('Country', 'listeo_core'); ?></label>
							<input type="text" name="comment[billing_country]" value="<?php echo isset($details->billing_country) ? esc_attr(stripslashes($details->billing_country)) : ''; ?>" class="form-input"/>
						</div>
					</div>
				</div>
			</div>

			<!-- Dates & Status Section -->
			<div class="airs-card" style="margin-bottom: 2rem;">
				<div class="airs-card-header">
					<h3>📅 <?php _e('Dates & Status', 'listeo_core'); ?></h3>
					<p><?php _e('Booking dates and current status', 'listeo_core'); ?></p>
				</div>
				<div class="airs-card-body">
					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Start Date', 'listeo_core'); ?></label>
							<input type="text" name="date_start" value="<?php echo esc_attr($booking['date_start']); ?>" class="form-input booking-date-picker"/>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('End Date', 'listeo_core'); ?></label>
							<input type="text" name="date_end" value="<?php echo esc_attr($booking['date_end']); ?>" class="form-input booking-date-picker"/>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Created Date', 'listeo_core'); ?></label>
							<input type="text" name="created" value="<?php echo esc_attr($booking['created']); ?>" class="form-input"/>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('Payment Due Date', 'listeo_core'); ?></label>
							<input type="text" name="expiring" value="<?php echo esc_attr($booking['expiring']); ?>" class="form-input booking-date-picker"/>
						</div>
					</div>

					<div class="form-group">
						<label class="form-label"><?php _e('Booking Status', 'listeo_core'); ?></label>
						<select name="status" class="form-select">
							<option value="confirmed" <?php selected($booking['status'], 'confirmed'); ?>><?php _e('Confirmed', 'listeo_core'); ?></option>
							<option value="waiting" <?php selected($booking['status'], 'waiting'); ?>><?php _e('Waiting', 'listeo_core'); ?></option>
							<option value="approved" <?php selected($booking['status'], 'approved'); ?>><?php _e('Approved', 'listeo_core'); ?></option>
							<option value="paid" <?php selected($booking['status'], 'paid'); ?>><?php _e('Paid', 'listeo_core'); ?></option>
							<option value="pay_to_confirm" <?php selected($booking['status'], 'pay_to_confirm'); ?>><?php _e('Pay to Confirm', 'listeo_core'); ?></option>
							<option value="cancelled" <?php selected($booking['status'], 'cancelled'); ?>><?php _e('Cancelled', 'listeo_core'); ?></option>
							<option value="expired" <?php selected($booking['status'], 'expired'); ?>><?php _e('Expired', 'listeo_core'); ?></option>
						</select>
					</div>
				</div>
			</div>

			<!-- Payment Section -->
			<div class="airs-card" style="margin-bottom: 2rem;">
				<div class="airs-card-header">
					<h3>💰 <?php _e('Payment Information', 'listeo_core'); ?></h3>
					<p><?php _e('Pricing and payment details', 'listeo_core'); ?></p>
				</div>
				<div class="airs-card-body">
					<div class="form-row">
						<div class="form-group">
							<label class="form-label"><?php _e('Price', 'listeo_core'); ?></label>
							<input type="text" name="price" value="<?php echo esc_attr($booking['price']); ?>" class="form-input"/>
						</div>
						<div class="form-group">
							<label class="form-label"><?php _e('Coupon', 'listeo_core'); ?></label>
							<input type="text" name="comment[coupon]" value="<?php echo isset($details->coupon) ? esc_attr(stripslashes($details->coupon)) : ''; ?>" class="form-input"/>
						</div>
					</div>
				</div>
			</div>

			<p class="submit" style="margin-left: 0;">
				<input type="submit" class="button button-primary button-large" value="<?php _e('Save Changes', 'listeo_core'); ?>"/>
				<a href="<?php echo admin_url('admin.php?page=listeo_bookings_manage'); ?>" class="button button-secondary button-large"><?php _e('Cancel', 'listeo_core'); ?></a>
			</p>
		</form>
		</div>
		<?php
		exit();
	}

	public function plugin_menu() {

		$hook = add_menu_page(
			'Manage bookings',
			'Bookings',
			'manage_options',
			'listeo_bookings_manage',
			[ $this, 'plugin_settings_page' ],
			'dashicons-calendar-alt',
			56
		);

		add_action( "load-$hook", [ $this, 'screen_option' ] );

	}


	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

		if ( 'add' === $action || 'edit' === $action ) {
			$this->add_package_page();
		} else {
			// Get statistics
			$stats = $this->get_bookings_statistics();
			?>
			<div class="wrap airs-admin-wrap">
				<!-- Header -->
				<div class="airs-header">
					<div class="airs-header-content">
						<div class="airs-header-icon">
							📅
						</div>
						<div class="airs-header-text">
							<h1><?php _e('Bookings Manager', 'listeo_core'); ?></h1>
							<p><?php _e('Complete overview and management of all bookings', 'listeo_core'); ?></p>
						</div>
					</div>
				</div>

				<!-- Statistics Cards -->
				<div class="bookings-stats-grid">
					<div class="stat-card">
						<div class="stat-card-header">
							<div class="stat-icon revenue">💰</div>
							<div>
								<p class="stat-card-title"><?php _e('Total Revenue', 'listeo_core'); ?></p>
							</div>
						</div>
						<h2 class="stat-card-value"><?php echo esc_html($stats['revenue']); ?></h2>
						<p class="stat-card-change positive">
							<span>↑</span> <?php echo esc_html($stats['revenue_change']); ?>
						</p>
					</div>

					<div class="stat-card">
						<div class="stat-card-header">
							<div class="stat-icon confirmed">✓</div>
							<div>
								<p class="stat-card-title"><?php _e('Confirmed Bookings', 'listeo_core'); ?></p>
							</div>
						</div>
						<h2 class="stat-card-value"><?php echo esc_html($stats['confirmed_count']); ?></h2>
						<p class="stat-card-change"><?php _e('This month', 'listeo_core'); ?></p>
					</div>

					<div class="stat-card">
						<div class="stat-card-header">
							<div class="stat-icon pending">⏳</div>
							<div>
								<p class="stat-card-title"><?php _e('Pending Bookings', 'listeo_core'); ?></p>
							</div>
						</div>
						<h2 class="stat-card-value"><?php echo esc_html($stats['pending_count']); ?></h2>
						<p class="stat-card-change"><?php _e('Awaiting action', 'listeo_core'); ?></p>
					</div>

					<div class="stat-card">
						<div class="stat-card-header">
							<div class="stat-icon cancelled">✕</div>
							<div>
								<p class="stat-card-title"><?php _e('Cancelled', 'listeo_core'); ?></p>
							</div>
						</div>
						<h2 class="stat-card-value"><?php echo esc_html($stats['cancelled_count']); ?></h2>
						<p class="stat-card-change"><?php _e('This month', 'listeo_core'); ?></p>
					</div>
				</div>


				<!-- Bookings Table -->
				<form method="GET">
					<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
					<?php
					$this->bookings_obj->prepare_items();
					$this->bookings_obj->display();
					?>
				</form>

				<!-- Modal for booking details -->
				<div class="booking-modal" id="booking-detail-modal">
					<div class="booking-modal-content">
						<div class="booking-modal-header">
							<h2><?php _e('Booking Details', 'listeo_core'); ?></h2>
							<button class="modal-close">×</button>
						</div>
						<div class="booking-modal-body">
							<!-- Content loaded via AJAX -->
						</div>
					</div>
				</div>
			</div>
		<?php
		}
	}

	/**
	 * Get booking statistics
	 */
	private function get_bookings_statistics() {
		global $wpdb;

		$currency_symbol = Listeo_Core_Listing::get_currency_symbol(get_option('listeo_currency'));
		$currency_position = get_option('listeo_currency_postion');

		// Total revenue (confirmed + paid bookings)
		$revenue = $wpdb->get_var("
			SELECT COALESCE(SUM(price), 0)
			FROM {$wpdb->prefix}bookings_calendar
			WHERE status IN ('confirmed', 'paid', 'approved')
			AND price IS NOT NULL
		");

		// Revenue this month
		$revenue_this_month = $wpdb->get_var("
			SELECT COALESCE(SUM(price), 0)
			FROM {$wpdb->prefix}bookings_calendar
			WHERE status IN ('confirmed', 'paid', 'approved')
			AND MONTH(created) = MONTH(CURRENT_DATE())
			AND YEAR(created) = YEAR(CURRENT_DATE())
		");

		// Revenue last month
		$revenue_last_month = $wpdb->get_var("
			SELECT COALESCE(SUM(price), 0)
			FROM {$wpdb->prefix}bookings_calendar
			WHERE status IN ('confirmed', 'paid', 'approved')
			AND MONTH(created) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
			AND YEAR(created) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
		");

		// Calculate growth percentage
		$revenue_change = '0%';
		if ($revenue_last_month > 0) {
			$percentage = (($revenue_this_month - $revenue_last_month) / $revenue_last_month) * 100;
			$revenue_change = number_format($percentage, 1) . '% vs last month';
		}

		// Format revenue
		if ($currency_position == 'before') {
			$formatted_revenue = $currency_symbol . number_format_i18n($revenue, 2);
		} else {
			$formatted_revenue = number_format_i18n($revenue, 2) . ' ' . $currency_symbol;
		}

		// Count by status
		$confirmed_count = $wpdb->get_var("
			SELECT COUNT(*)
			FROM {$wpdb->prefix}bookings_calendar
			WHERE status IN ('confirmed', 'paid', 'approved')
			AND MONTH(created) = MONTH(CURRENT_DATE())
			AND YEAR(created) = YEAR(CURRENT_DATE())
		");

		$pending_count = $wpdb->get_var("
			SELECT COUNT(*)
			FROM {$wpdb->prefix}bookings_calendar
			WHERE status IN ('waiting', 'pay_to_confirm')
		");

		$cancelled_count = $wpdb->get_var("
			SELECT COUNT(*)
			FROM {$wpdb->prefix}bookings_calendar
			WHERE status IN ('cancelled', 'expired')
			AND MONTH(created) = MONTH(CURRENT_DATE())
			AND YEAR(created) = YEAR(CURRENT_DATE())
		");

		return array(
			'revenue' => $formatted_revenue,
			'revenue_change' => $revenue_change,
			'confirmed_count' => $confirmed_count,
			'pending_count' => $pending_count,
			'cancelled_count' => $cancelled_count
		);
	}

	/**
	 * Screen options
	 */
	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => __( 'Bookings per page', 'listeo_core'),
			'default' => 20,
			'option'  => 'per_page'
		];

		add_screen_option( $option, $args );

		$this->bookings_obj = new Bookings_Admin_List();

	}


	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets($hook) {
		// Only load on bookings page
		if ($hook !== 'toplevel_page_listeo_bookings_manage') {
			return;
		}

		// Enqueue jQuery UI Datepicker
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css');

		// Enqueue CSS
		wp_enqueue_style(
			'listeo-bookings-admin',
			plugins_url('../assets/css/admin-bookings.css', __FILE__),
			array('jquery-ui-css'),
			'1.0.0'
		);

		// Enqueue JavaScript
		wp_enqueue_script(
			'listeo-bookings-admin',
			plugins_url('../assets/js/admin-bookings.js', __FILE__),
			array('jquery', 'jquery-ui-datepicker'),
			'1.0.0',
			true
		);

		// Localize script
		wp_localize_script('listeo-bookings-admin', 'listeoBookingsAdmin', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('listeo_bookings_admin'),
			'confirmStatusChange' => __('Are you sure you want to change the booking status?', 'listeo_core'),
			'confirmDelete' => __('Are you sure you want to delete this booking?', 'listeo_core')
		));
	}

	/**
	 * AJAX: Get booking details
	 */
	public function ajax_get_booking_details() {
		check_ajax_referer('listeo_bookings_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo_core')));
		}

		$booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

		if (!$booking_id) {
			wp_send_json_error(array('message' => __('Invalid booking ID', 'listeo_core')));
		}

		$args = array('id' => $booking_id);
		$bookings_list = new Bookings_Admin_List();
		$booking = $bookings_list->get_bookings($args, null);

		if (empty($booking)) {
			wp_send_json_error(array('message' => __('Booking not found', 'listeo_core')));
		}

		$booking = $booking[0];
		$details = json_decode($booking['comment']);

		ob_start();
		include(plugin_dir_path(__FILE__) . '../templates/admin-booking-details.php');
		$html = ob_get_clean();

		wp_send_json_success(array('html' => $html));
	}

	/**
	 * AJAX: Update booking status
	 */
	public function ajax_update_booking_status() {
		check_ajax_referer('listeo_bookings_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo_core')));
		}

		$booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

		if (!$booking_id || !$status) {
			wp_send_json_error(array('message' => __('Invalid parameters', 'listeo_core')));
		}

		$allowed_statuses = array('confirmed', 'waiting', 'approved', 'paid', 'pay_to_confirm', 'cancelled', 'expired');
		if (!in_array($status, $allowed_statuses)) {
			wp_send_json_error(array('message' => __('Invalid status', 'listeo_core')));
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_calendar',
			array('status' => $status),
			array('ID' => $booking_id),
			array('%s'),
			array('%d')
		);

		if ($updated === false) {
			wp_send_json_error(array('message' => __('Failed to update status', 'listeo_core')));
		}

		wp_send_json_success(array('message' => __('Status updated successfully', 'listeo_core')));
	}

	/**
	 * AJAX: Delete booking
	 */
	public function ajax_delete_booking() {
		check_ajax_referer('listeo_bookings_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo_core')));
		}

		$booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

		if (!$booking_id) {
			wp_send_json_error(array('message' => __('Invalid booking ID', 'listeo_core')));
		}

		Bookings_Admin_List::delete_booking($booking_id);

		wp_send_json_success(array('message' => __('Booking deleted successfully', 'listeo_core')));
	}

	/**
	 * AJAX: Export bookings to CSV
	 */
	public function ajax_export_bookings_csv() {
		check_ajax_referer('listeo_bookings_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_die(__('Permission denied', 'listeo_core'));
		}

		global $wpdb;

		// Build query based on filters
		$sql = "SELECT * FROM {$wpdb->prefix}bookings_calendar WHERE status IS NOT NULL";

		if (isset($_GET['listing_id']) && !empty($_GET['listing_id'])) {
			$sql .= $wpdb->prepare(' AND listing_id = %d', absint($_GET['listing_id']));
		}
		if (isset($_GET['status']) && !empty($_GET['status'])) {
			$sql .= $wpdb->prepare(' AND status = %s', sanitize_text_field($_GET['status']));
		}
		if (isset($_GET['owner']) && !empty($_GET['owner'])) {
			$sql .= $wpdb->prepare(' AND owner_id = %d', absint($_GET['owner']));
		}
		if (isset($_GET['guest']) && !empty($_GET['guest'])) {
			$sql .= $wpdb->prepare(' AND bookings_author = %d', absint($_GET['guest']));
		}

		$sql .= ' ORDER BY ID DESC';

		$bookings = $wpdb->get_results($sql, ARRAY_A);

		// Set headers for CSV download
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=bookings-' . date('Y-m-d') . '.csv');

		// Create file pointer
		$output = fopen('php://output', 'w');

		// Add CSV headers
		fputcsv($output, array('ID', 'Client', 'Owner', 'Listing', 'Start Date', 'End Date', 'Type', 'Status', 'Price', 'Created'));

		// Add data rows
		foreach ($bookings as $booking) {
			$client = $booking['bookings_author'] ? get_userdata($booking['bookings_author'])->display_name : 'iCal import';
			$owner = $booking['owner_id'] ? get_userdata($booking['owner_id'])->display_name : 'iCal import';
			$listing = get_the_title($booking['listing_id']);

			fputcsv($output, array(
				$booking['ID'],
				$client,
				$owner,
				$listing,
				$booking['date_start'],
				$booking['date_end'],
				$booking['type'],
				$booking['status'],
				$booking['price'],
				$booking['created']
			));
		}

		fclose($output);
		exit;
	}

	/** Singleton instance */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

}


add_action( 'plugins_loaded', function () {

	Bookings_Admin_Plugin::get_instance();

} );