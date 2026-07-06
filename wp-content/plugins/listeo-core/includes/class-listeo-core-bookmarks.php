<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;
/**
 * Listeo_Core_Listing class
 */
class Listeo_Core_Bookmarks {
	public function __construct() {

		add_action('wp_ajax_listeo_core_bookmark_this', array($this, 'bookmark_this'));
		add_action('wp_ajax_nopriv_listeo_core_bookmark_this', array($this, 'bookmark_this'));

		add_action('wp_ajax_listeo_core_unbookmark_this', array($this, 'remove_bookmark'));
		add_action('wp_ajax_nopriv_listeo_core_unbookmark_this', array($this, 'remove_bookmark'));

		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_shortcode( 'listeo_bookmarks', array( $this, 'listeo_bookmarks' ) );
	}

	/**
	 * frontend_scripts function.
	 *
	 * @access public
	 * @return void
	 */
	public function frontend_scripts() {
	
	}


	public function bookmark_this() {

	    // if ( !wp_verify_nonce( $_REQUEST['nonce'], 'listeo_core_bookmark_this_nonce')) {
	    // 	exit('No naughty business please');
	    // }   
	    $post_id = $_REQUEST['post_id'];

	    if(is_user_logged_in()){
		   	$userID = $this->get_user_id();
		   	if($this->check_if_added($post_id)) {
				$result['type'] = 'error';
				$result['message'] = __( 'You\'ve already added that post' , 'listeo_core' );
		   	} 
		   	else {
		   		$bookmarked_posts =  (array) $this->get_bookmarked_posts();
		   		$bookmarked_posts[] = $post_id;
				$action = update_user_meta( $userID, 'listeo_core-bookmarked-posts', $bookmarked_posts );
				
				if($action === false) {
					$result['type'] = 'error';
					$result['message'] = __( 'Oops, something went wrong, please try again' , 'listeo_core' );

				} else {

					$bookmarks_counter = get_post_meta( $post_id, 'bookmarks_counter', true );
			   		$bookmarks_counter++;			   
			   		update_post_meta( $post_id, 'bookmarks_counter', $bookmarks_counter );

			   		$author_id 		= get_post_field( 'post_author', $post_id );
					$total_bookmarks = get_user_meta($author_id,'listeo_total_listing_bookmarks',true);
					$total_bookmarks = (int) $total_bookmarks + 1;
					update_user_meta($author_id, 'listeo_total_listing_bookmarks', $total_bookmarks);

			  		$bookmarked_posts[] = $post_id;
			  		do_action("listeo_listing_bookmarked", $post_id, $userID );
					$result['type'] = 'success';
					$result['message'] = __( 'Listing was bookmarked' , 'listeo_core' );
					
				}
			}
		   
		} 

		wp_send_json($result);
		die();

	}	  	

	public function remove_bookmark() {
		
	   // if ( !wp_verify_nonce( $_REQUEST['nonce'], 'listeo_core_remove_fav_nonce')) {
	   //    exit('No naughty business please');
	   // }   
	   $post_id = $_REQUEST['post_id'];
	   if(is_user_logged_in()){
		   	$userID = $this->get_user_id();
		
	   		$bookmarked_posts = $this->get_bookmarked_posts();
	   		$bookmarked_posts = array_diff($bookmarked_posts, array($post_id));
	        $bookmarked_posts = array_values($bookmarked_posts);

			$action = update_user_meta( $userID, 'listeo_core-bookmarked-posts', $bookmarked_posts, false );
			if($action === false) {
				$result['type'] = 'error';
				$result['message'] = __('Oops, something went wrong, please try again','listeo_core');
			} else {
		   		$bookmarks_counter = get_post_meta( $post_id, 'bookmarks_counter', true );
		   		$bookmarks_counter--;
		   		update_post_meta( $post_id, 'bookmarks_counter', $bookmarks_counter );

		   		$author_id 		= get_post_field( 'post_author', $post_id );
				$total_bookmarks = get_user_meta($author_id,'listeo_total_listing_bookmarks',true);
				$total_bookmarks = (int) $total_bookmarks - 1;
				update_user_meta($author_id, 'listeo_total_listing_bookmarks', $total_bookmarks);
		   		do_action("listeo_listing_unbookmarked", $post_id, $userID );
				$result['type'] = 'success';
				$result['message'] = esc_html__('Listing was removed from the list','listeo_core');
			}
		} 

	   
		wp_send_json($result);
		die();

	}

	function get_user_id() {
	    global $current_user;
	    wp_get_current_user();
	    return $current_user->ID;
	}

	function get_bookmarked_posts() {
		return get_user_meta($this->get_user_id(), 'listeo_core-bookmarked-posts', true);
	}

	function check_if_added($id) {
		$bookmarked_post_ids = $this->get_bookmarked_posts();
		if ($bookmarked_post_ids) {
            foreach ($bookmarked_post_ids as $bookmarked_id) {
                if ($bookmarked_id == $id) { 
                	return true; 
                }
            }
        } 
        return false;
	}
	

	/**
	 * User bookmarks shortcode
	 */
	public function listeo_bookmarks( $atts ) {
		
		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to manage your bookmarks.', 'listeo_core' );
		}

		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );

		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;

		
		$template_loader->set_template_data( array( 'ids' => $this->get_bookmarked_posts() ) )->get_template_part( 'account/bookmarks' ); 


		return ob_get_clean();
	}



}