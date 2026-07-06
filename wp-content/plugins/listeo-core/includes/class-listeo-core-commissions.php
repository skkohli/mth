<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;
/**
 * Listeo_Core_Listing class
 */
class Listeo_Core_Commissions {

	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	//fixed/percentage

	public function __construct() {

		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_change' ), 10, 3 );
		add_action( 'woocommerce_order_refunded', array( $this, 'register_commission_refund' ), 20, 2 );

		add_shortcode( 'listeo_wallet', array( $this, 'listeo_wallet' ) );

		// Handle Stripe Express reauth redirect before any output
		add_action( 'template_redirect', array( $this, 'handle_stripe_express_reauth' ) );

	}

	/**
	 * Handle Stripe Express reauth redirect before any output
	 * This fires on template_redirect hook, before headers are sent
	 */
	public function handle_stripe_express_reauth() {
		// Only process on wallet page with reauth parameter
		if ( ! isset( $_REQUEST['stripe-express-reauth'] ) || $_REQUEST['stripe-express-reauth'] !== 'yes' ) {
			return;
		}

		// Check if we're on the wallet page
		$wallet_page_id = get_option('listeo_wallet_page');
		if ( ! is_page( $wallet_page_id ) ) {
			return;
		}

		// User must be logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();

		// Clear stale account link - it's single-use and has been invalidated by Stripe
		delete_user_meta( $current_user->ID, 'listeo_stripe_express_account_url' );
		delete_user_meta( $current_user->ID, 'listeo_stripe_express_account_url_expiration' );

		// Redirect to clean URL to remove query parameter and trigger new link generation
		wp_safe_redirect( get_permalink( $wallet_page_id ) );
		exit;
	}

	function register_commission_refund( $order_id, $refund_id ) {

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Only reverse Stripe Connect transfers
		$transfer_id = $order->get_meta( 'listeo_stripe_transfer_id', true );
		if ( empty( $transfer_id ) ) {
			return;
		}

		// Don't reverse the same transfer twice
		if ( $order->get_meta( 'listeo_stripe_transfer_reversed', true ) === 'yes' ) {
			return;
		}

		$stripe_mode = get_option( 'listeo_stripe_connect_mode' );
		$testmode    = ( ! empty( $stripe_mode ) && 'test' === $stripe_mode );
		$secret_key  = 'listeo_stripe_connect_' . ( $testmode ? 'test_' : 'live_' ) . 'secret_key';
		$secret      = get_option( $secret_key );

		if ( empty( $secret ) ) {
			error_log( 'Listeo Stripe Connect: Cannot reverse transfer — secret key not configured.' );
			return;
		}

		try {
			\Stripe\Stripe::setApiKey( $secret );
			$reversal = \Stripe\Transfer::createReversal( $transfer_id );

			$order->update_meta_data( 'listeo_stripe_transfer_reversed', 'yes' );
			$order->update_meta_data( 'listeo_stripe_reversal_id', $reversal->id );
			$order->save_meta_data();

			// Update commission status
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'listeo_core_commissions',
				array( 'status' => 'refunded' ),
				array( 'order_id' => $order_id )
			);

			if ( class_exists( 'WC_Stripe_Logger' ) ) {
				WC_Stripe_Logger::info( 'Listeo Stripe Connect: Transfer reversed for order #' . $order_id . ' — reversal ID: ' . $reversal->id );
			}
		} catch ( Exception $e ) {
			error_log( 'Listeo Stripe Connect: Transfer reversal failed for order #' . $order_id . ' — ' . $e->getMessage() );
		}
	}

		/**
	 * Register commissions based on order status
	 *
	 * @param $order_id
	 * @param $old_status
	 * @param $new_status
	 */
	//}


	public function order_status_change( $order_id, $old_status, $new_status ) {
			switch ( $new_status ) {

				case 'completed' :
					$this->register_commission( $order_id );
					
					break;

				// case 'refunded' :
				// 	$this->register_commissions_refunded( $order_id );
				// 	break;

				case 'refunded' :
				case 'cancelled' :
				case 'failed' :
					$this->delete_commission( $order_id );
					break;

				// case 'pending':
				// case 'on-hold':
				// 	$this->register_commissions_pending( $order_id );
				// 	break;

			}
	}

	function delete_commission($order_id){
		if ( ! $order_id ) {
				return;
			}

			global $wpdb;
			$wpdb->delete(  $wpdb->prefix . "listeo_core_commissions", array( 'order_id' => $order_id ) );
	}

	function register_commission($order_id){
		$order = wc_get_order( $order_id );
		if(!$order){
			return;
		}

		// check payment method based on order id
		$payment_method = $order->get_payment_method();
		//skip if payment method is cod
		$skip_cod = apply_filters('listeo_commission_skip_cod', true, $order_id, $payment_method);
		if($payment_method == 'cod' && $skip_cod){
			return;
		}
		$processed = $order->get_meta( '_listeo_commissions_processed', true );

		if ( $processed && $processed == 'yes' ) {
			return;
		}

		

		$order_data = $order->get_data();
		
		
		$args['order_id'] = $order_id;
		$args['user_id'] = $order->get_meta('owner_id');
		$args['booking_id'] = $order->get_meta('booking_id');
		$args['listing_id'] = $order->get_meta('listing_id');

		// Get commission type (per-user or global)
		$commission_type = get_user_meta($args['user_id'], 'listeo_commission_type', true);
		if(empty($commission_type)){
			$commission_type = get_option('listeo_commission_type', 'percentage');
		}

		// Get commission value (per-user or global)
		$commission_value = get_user_meta($args['user_id'], 'listeo_commission_rate', true);
		if(empty($commission_value)){
			$commission_value = get_option('listeo_commission_rate', 10);
		}

		// Get calculation method (per-user or global)
		$calculation_method = get_user_meta($args['user_id'], 'listeo_commission_calculation_method', true);
		if(empty($calculation_method)){
			$calculation_method = get_option('listeo_commission_calculation_method', 'deduct');
		}

		$order_total = (float) $order->get_total();
		$calculated_commission = 0;

		// Calculate commission amount based on type
		if ($commission_type == 'fixed') {
			// Fixed amount commission
			$args['rate'] = 0; // Not applicable for fixed
			$calculated_commission = (float) $commission_value;
		} else {
			// Percentage commission
			$args['rate'] = (float) $commission_value / 100;
			$calculated_commission = $order_total * $args['rate'];
		}

		// Prevent commissions from exceeding the order total or going negative
		$args['amount'] = max(0, min($calculated_commission, $order_total));

		$args['type'] = $commission_type;
		$args['calculation_method'] = $calculation_method;

		$connect_processed = $order->get_meta('listeo_stripe_connect_processed', true);

		if ($connect_processed) {
			$args['status'] = 'paid';
		} else {
			$args['status'] = 'unpaid';
		}	

		$commission_id = $this->insert_commission( $args );
		if($commission_id){
			$order->add_meta_data( '_listeo_commissions_id', $commission_id, true );
			$order->add_meta_data( '_listeo_commissions_processed', 'yes', true );
	        $order->save_meta_data();
		}
		// Mark commissions as processed
		
	}

	function insert_commission($args){
		global $wpdb;
		
		$defaults = array(
		    'type'          => 'percentage',
            'date'			=> current_time('mysql')
		);

		$args = wp_parse_args( $args, $defaults );

		$wpdb->insert( $wpdb->prefix . "listeo_core_commissions", (array) $args );

		return $wpdb->insert_id;

	}

	function get_commissions($args){

		global $wpdb;

			$default_args = array(
				
				'order_id'     => 0,
				'user_id'      => 0,
				'booking_id'    => 0,
				'status'       => 'unpaid',
				'm'            => false,
				'date_query'   => false,
				'number'       => '',
				'offset'       => '',
				'paged'        => '',
				'orderby'      => 'ID',
				'order'        => 'DESC',
				'fields'       => 'ids',
                'table'        => $wpdb->prefix . "listeo_core_commissions"
			);

			$args = wp_parse_args( $args, $default_args );

			$table = $args['table'];

		

			// First let's clear some variables
			$where = '';
			$limits = '';
			$join = '';
			$groupby = '';
			$orderby = '';

			// query parts initializating
			$pieces = array( 'where', 'groupby', 'join', 'orderby', 'limits' );

			
			if ( ! empty( $args['order_id'] ) ) {
				$where .= $wpdb->prepare( " AND c.order_id = %d", $args['order_id'] );
			}
			if ( ! empty( $args['user_id'] ) ) {
				$where .= $wpdb->prepare( " AND c.user_id = %d", $args['user_id'] );
			}
			if ( ! empty( $args['booking_id'] ) ) {
				$where .= $wpdb->prepare( " AND c.booking_id = %d", $args['booking_id'] );
			}
            if ( ! empty( $args['type'] ) && 'all' != $args['type'] ) {
                $where .= $wpdb->prepare( " AND c.type = %s", $args['type'] );
            }
			if ( ! empty( $args['status'] ) && 'all' != $args['status'] ) {
				$allowed_statuses = array( 'unpaid', 'paid', 'refunded', 'pending' );
				$statuses = is_array( $args['status'] ) ? $args['status'] : array( $args['status'] );
				$statuses = array_values( array_intersect( $statuses, $allowed_statuses ) );
				if ( ! empty( $statuses ) ) {
					$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
					$where .= $wpdb->prepare( " AND c.status IN ( $placeholders )", $statuses );
				}
			}

			// Order
			if ( ! is_string( $args['order'] ) || empty( $args['order'] ) ) {
				$args['order'] = 'DESC';
			}

			if ( 'ASC' === strtoupper( $args['order'] ) ) {
				$args['order'] = 'ASC';
			} else {
				$args['order'] = 'DESC';
			}

			// Order by — whitelist valid column names to prevent SQL injection
			$allowed_orderby_columns = array( 'ID', 'order_id', 'user_id', 'booking_id', 'listing_id', 'amount', 'rate', 'type', 'status', 'date' );

			if ( empty( $args['orderby'] ) ) {
				if ( isset( $args['orderby'] ) && ( is_array( $args['orderby'] ) || false === $args['orderby'] ) ) {
					$orderby = '';
				} else {
					$orderby = "c.ID " . $args['order'];
				}
			} elseif ( 'none' == $args['orderby'] ) {
				$orderby = '';
			} else {
				if ( is_array( $args['orderby'] ) ) {
					$orderby_array = array();
					foreach ( $args['orderby'] as $_orderby => $order ) {
						$_orderby = sanitize_key( $_orderby );
						if ( ! in_array( $_orderby, $allowed_orderby_columns, true ) ) {
							continue;
						}

						if ( 'ASC' === strtoupper( (string) $order ) ) {
							$order = 'ASC';
						} else {
							$order = 'DESC';
						}

						$orderby_array[] = 'c.' . $_orderby . ' ' . $order;
					}
					$orderby = implode( ', ', $orderby_array );
				} else {
					$requested = sanitize_key( $args['orderby'] );
					if ( in_array( $requested, $allowed_orderby_columns, true ) ) {
						$orderby = 'c.' . $requested . ' ' . $args['order'];
					} else {
						$orderby = "c.ID " . $args['order'];
					}
				}
			}

			// Paging
			if ( ! empty($args['paged']) && ! empty($args['number']) ) {
				$page = absint($args['paged']);
				if ( !$page )
					$page = 1;

				if ( empty( $args['offset'] ) ) {
					$pgstrt = absint( ( $page - 1 ) * $args['number'] ) . ', ';
				}
				else { // we're ignoring $page and using 'offset'
					$args['offset'] = absint( $args['offset'] );
					$pgstrt      = $args['offset'] . ', ';
				}
				$limits = 'LIMIT ' . $pgstrt . $args['number'];
			}

			$clauses = compact( $pieces );

			$where   = isset( $clauses['where'] ) ? $clauses['where'] : '';
			$groupby = isset( $clauses['groupby'] ) ? $clauses['groupby'] : '';
			$join    = isset( $clauses['join'] ) ? $clauses['join'] : '';
			$orderby = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
			$limits  = isset( $clauses['limits'] ) ? $clauses['limits'] : '';

			if ( ! empty($groupby) )
				$groupby = 'GROUP BY ' . $groupby;
			if ( !empty( $orderby ) )
				$orderby = 'ORDER BY ' . $orderby;

			$found_rows = '';
			if ( ! empty( $limits ) ) {
				$found_rows = 'SQL_CALC_FOUND_ROWS';
			}

			$fields = 'c.ID';

			if( 'count' != $args['fields'] && 'ids' != $args['fields'] ){
				if( is_array( $args['fields'] ) ){
					$fields = implode( ',', $args['fields'] );
				}

				else {
					$fields = $args['fields'];
				}
			}

			$res = $wpdb->get_col( "SELECT $found_rows DISTINCT $fields FROM $table c $join WHERE 1=1 $where $groupby $orderby $limits" );

			// return count
			if ( 'count' == $args['fields'] ) {
				return ! empty( $limits ) ? $wpdb->get_var( 'SELECT FOUND_ROWS()' ) : count( $res );
			}

			return $res;
		}

	/**
	 * Return the count of posts in base of query
	 *
	 * @param array $q
	 *
	 * @return int
	 * @since 1.0
	 */
	public function count_commissions( $q = array() ) {
		if ( 'last-query' == $q ) {
			global $wpdb;
			return $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		}

		$q['fields'] = 'count';
		return $this->get_commissions( $q );
	}


	function get_commission($commission_id){
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ". $wpdb->prefix . "listeo_core_commissions WHERE ID = %d", $commission_id ), ARRAY_A );
	}
	/**
	 * User wallet page shortcode
	 */
	public function listeo_wallet( $atts ) {

		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to access your wallet.', 'listeo_core' );
		}

		// Note: stripe-express-reauth handling is done in handle_stripe_express_reauth()
		// via template_redirect hook to ensure redirect happens before any output

		extract( shortcode_atts( array(
			//'posts_per_page' => '25',
		), $atts ) );

		$commissions_ids = $this->get_commissions( array( 'user_id'=>get_current_user_id(),'status' => 'all' ) );
		$commissions_count = $this->count_commissions(array( 'user_id'=>get_current_user_id(),'status' => 'all'  ) );
		
		$earnings_total = $this->calculate_totals(array( 'user_id'=>get_current_user_id(), 'status' => 'all' ) );
		
		$commissions = array();
		foreach ($commissions_ids as $id) {
			$commissions[$id] = $this->get_commission($id);
		}


		$payouts_class = new Listeo_Core_Payouts;
		$payouts_ids = $payouts_class->get_payouts( array( 'user_id'=>get_current_user_id(), 'status' => 'all'  ) );
		
		$payouts = array();
		$total_earnings_ever = 0;
		foreach ($payouts_ids as $id) {
			$payouts[$id] = $payouts_class->get_payout($id);
			//$total_earnings_ever = (float) $total_earnings_ever + $payouts[$id]['amount'];

		}

		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;		
		$template_loader->set_template_data( 
			array( 
				'commissions' => $commissions,
				'total_orders' => $commissions_count,
				'earnings_total' => $earnings_total,
				//'total_earnings_ever' => $total_earnings_ever,
				'payouts' => $payouts,
			) )->get_template_part( 'account/wallet' ); 


		return ob_get_clean();
	}	

	public function calculate_totals($args){

		if(!isset($args['status'])) { $args['status'] = 'all'; }

		$q = array(
			'user_id' => $args['user_id'],
			'status' => $args['status']
		);

		$total_earnings = 0;
		$commissions_ids = $this->get_commissions( $q );
		$commissions = array();
		foreach ($commissions_ids as $id) {
			$commissions[$id] = $this->get_commission($id);
		}

		foreach ($commissions as $commission) {
			$order = wc_get_order( $commission['order_id'] );
			if($order){
				$total = $order->get_total();
				$calculation_method = isset($commission['calculation_method']) ? $commission['calculation_method'] : 'deduct';

				if ($calculation_method == 'add') {
					// Commission was ADDED to total, owner gets total minus commission
					$earning = $total - $commission['amount'];
				} else {
					// Commission is DEDUCTED from total (default/legacy behavior)
					$earning = $total - $commission['amount'];
				}

				$total_earnings = $total_earnings + $earning;
			}
		}
		return $total_earnings;
	}
}




if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if ( ! class_exists( 'Listeo_Balances_List_Table' ) ) {
    /**
     *
     *
     * @class class.yith-commissions-list-table
     * @package    Yithemes
     * @since      Version 1.0.0
     * @author     Your Inspiration Themes
     *
     */
    class Listeo_Balances_List_Table extends WP_List_Table {
    /** Class constructor */
        public function __construct() {

            parent::__construct( [
                'singular' => __( 'User Balance', 'listeo_core' ), // singular name of the listed records
                'plural'   => __( 'Users Balance', 'listeo_core' ), // plural name of the listed records
                'ajax'     => false // does this table support ajax?
            ] );

        }


        /**
         * Returns columns available in table
         *
         * @return array Array of columns of the table
         * @since 1.0.0
         */
        public function get_columns() {
            $columns = array(
                    'user_id'   => __( 'User ID', 'listeo_core' ),
                    'user_name' => __( 'User Name', 'listeo_core' ),
                    'balance'   => __( 'Balance to pay', 'listeo_core' ),
                    'orders'    => __( 'Orders counter', 'listeo_core' ),
                    'actions'      => __( 'Actions', 'listeo_core' ),
                
            );

            return $columns;
        }

        public function prepare_items() {            

                $columns = $this->get_columns();
                $hidden = $this->get_hidden_columns();
                $sortable = $this->get_sortable_columns();
                
                $data = $this->table_data();
                usort( $data, array( &$this, 'sort_data' ) );
                
                $perPage = 8;
                
                $currentPage = $this->get_pagenum();
                $totalItems = count($data);
                
                $this->set_pagination_args( array(
                    'total_items' => $totalItems,
                    'per_page'    => $perPage
                ) );
                
                $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
                
                $this->_column_headers = array($columns, $hidden, $sortable);
                $this->items = $data;


        }

        /**
        * Define which columns are hidden
         *
         * @return Array
         */
        public function get_hidden_columns() {
            return array();
        }

         /**
         * Get the table data
         *
         * @return Array
         */
        private function table_data() {

            $data = array();

            $args = array(
                //'role'           => 'owner',
                'fields'         => 'all',
            );
            $user_query = new WP_User_Query( $args );
            $commission = new Listeo_Core_Commissions;
            if ( ! empty( $user_query->get_results() ) ) {
                foreach ( $user_query->get_results() as $user ) {
                    
                    $balance = $commission->calculate_totals( array( 'user_id'=> $user->ID,'status' => 'unpaid' ) );
                    $orders =  $commission->get_commissions( array( 'user_id'=> $user->ID ) );
                    if($balance>0){
                       $data[] = array(
                        'user_id' => $user->ID,
                        'user_name' => $user->display_name,
                        'balance' => $balance,
                        'orders' => $orders,
                        ); 
                    }
                    
                  
                }
            } 
            return $data;
        }
         /**
         * Define what data to show on each column of the table
         *
         * @param  Array $item        Data
         * @param  String $column_name - Current column name
         *
         * @return Mixed
         */
        public function column_default( $item, $column_name )
        {
            switch( $column_name ) {
                case 'user_id':
                    return $item[ $column_name ];
                break;
                
                case 'balance':
                    if(function_exists('wc_price')) {
                        echo wc_price($item[ $column_name ]);
                    } else { echo $item[ $column_name ]; };
                break;
                
                case 'user_name':
                    return '<a href="'.esc_url( get_author_posts_url($item['user_id'])).'">'.esc_html($item[ $column_name ]).'</a>';
                break;

                case 'orders':
                    echo count($item['orders']);
                break;
                
                case 'actions':
                $url = admin_url( 'admin.php?page=listeo_payouts');
                
                $payout_url = esc_url( add_query_arg( 'make_payout', $item['user_id'], $url ) );
               
                printf( '<a class="button-primary view" href="%1$s" data-tip="%2$s">%2$s</a>', $payout_url, __( 'Make Payout', 'listeo_core' ) );
                break;

                default:
                    return print_r( $item, true ) ;
            }
        }
        function no_items() {
            _e( 'No users found.','listeo_core' );
        }

    }
}
