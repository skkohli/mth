<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * WP_listing_Manager_Content class.
 */
class Listeo_Core_Reviews {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26
	 */
	private static $_instance = null;

	private $dashboard_message = '';
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

	/**
	 * Constructor
	 */
	public function __construct() {
	
		add_filter( 'comment_form_fields', array( $this,'comments_fields') ); // add new fields
		add_filter('comment_form_defaults', array( $this, 'comment_form_submit_button_label') ); // add new fields
		add_action( 'comment_post', array( $this,'save_comment_meta_data') ); // save new fields
		add_action( 'comment_form_logged_in_after', array( $this,'comments_logged_in_fields') ); // rating fields for logged-in users
		add_action( 'transition_comment_status', array($this,'transition_comment_callbacks'),10,3);
		add_action( 'comment_post', array($this,'add_comment_rating'), 10, 2 );

		add_action( 'add_meta_boxes_comment',  array( $this,'add_custom_comment_field_meta_boxes') );
		add_action( 'edit_comment', array( $this,'update_edit_comment') );
		add_action( 'load-edit-comments.php', array( $this,'add_custom_fields_to_edit_comment_screen'));
		add_action( 'manage_comments_custom_column', array( $this,'custom_rating_column'), 10, 2 );

		add_filter('preprocess_comment',  array($this, 'check_if_attachment_is_image'), 10, 1);
		if(get_option('listeo_recaptcha_reviews')) :
			add_filter('preprocess_comment',  array($this, 'validate_captcha_comment_field'), 10, 1);
			add_filter('comment_post_redirect', array($this, 'redirect_fail_captcha_comment'), 10, 2 );
		endif;

	 	add_action('comment_form_top',          array($this, 'make_form_multipart'));
        add_action('comment_post', array($this, 'save_comment_attachment'));
  		add_action('delete_comment', array($this, 'delete_comment_attachment'));
  		add_action('delete_comment', array($this, 'delete_comment_meta'));

		add_shortcode( 'listeo_reviews', array( $this, 'listeo_reviews' ) );

		add_action( 'wp_ajax_reload_reviews', array( $this, 'reload_reviews' ) );
		add_action( 'wp_ajax_reply_to_review', array( $this, 'reply_to_review' ) );
		add_action( 'wp_ajax_edit_reply_to_review', array( $this, 'edit_reply_to_review' ) );
		add_action( 'wp_ajax_edit_review', array( $this, 'edit_review' ) );

		add_action( 'wp', array( $this, 'reviews_action_handler' ) );


		add_action( 'wp_ajax_listeo_core_rate_review', array( $this, 'rate_review' ) );
		add_action( 'wp_ajax_nopriv_listeo_core_rate_review', array( $this, 'rate_review' ) );
		

		add_action( 'wp_ajax_get_comment_review_details', array( $this, 'get_comment_review_details' ) );

		add_action('template_redirect', array($this, 'show_pending_comment_message'));
		add_filter('comment_post_redirect', array($this, 'modify_comment_redirect'), 10, 2);

		// Add hooks to recalculate combined rating when Listeo reviews change
		add_action('comment_post', array($this, 'recalculate_on_comment_post'), 10, 3);
		add_action('wp_set_comment_status', array($this, 'recalculate_on_comment_status_change'), 10, 2);
		add_action('edit_comment', array($this, 'recalculate_on_comment_edit'), 10, 2);
		add_action('delete_comment', array($this, 'recalculate_on_comment_delete'), 10, 2);

		// Enable comment pagination for listings
		add_filter('option_page_comments', array($this, 'enable_comment_pagination_for_listings'));
		add_filter('option_comments_per_page', array($this, 'set_comments_per_page_for_listings'));

	}

	/**
	 * Enable comment pagination for listing post type
	 */
	public function enable_comment_pagination_for_listings($value) {
		if (is_singular('listing')) {
			return 1; // Force enable pagination for listings
		}
		return $value;
	}

	/**
	 * Set comments per page for listing post type
	 */
	public function set_comments_per_page_for_listings($value) {
		if (is_singular('listing')) {
			return apply_filters('listeo_reviews_per_page', 10);
		}
		return $value;
	}

	public function comment_form_submit_button_label($args)
	{		
		global $post;
		if($post && $post->post_type == 'listing'){
			$args['label_submit'] = esc_html__('Submit Review','listeo_core');
		}
		return $args;
	}

	/**
	 * Add this method to your Listeo_Core_Reviews class
	 */
	public function show_pending_comment_message()
	{
		
		// Check if we're on a listing page and have the unapproved comment hash
		if (is_singular('listing') && isset($_GET['unapproved']) && isset($_GET['moderation-hash'])) {
			
			$comment_id = absint($_GET['unapproved']);
			
			$comment = get_comment($comment_id);

			// Get the comment
			$comment = get_comment($comment_id);
			
			
			$valid_hash = wp_hash($comment->comment_date_gmt);
			
			if ($comment && hash_equals($_GET['moderation-hash'], $valid_hash)) {
				
				// Show message to the user
				add_action('listeo_before_comments', function () {
					echo '<div class="notification success closeable margin-bottom-35">
                    <p>' . esc_html__('Thank you for your review! Your comment is awaiting moderation and will be visible once approved.', 'listeo_core') . '</p>
                    <a class="close"></a>
                </div>';
				});
			}
		}
	}

	/**
	 * Modify comment posted redirect to include custom notification
	 */
	public function modify_comment_redirect($location, $comment)
	{
		if ($comment->comment_approved == 0 && get_post_type($comment->comment_post_ID) == 'listing') {
			$hash = substr(wp_hash($comment->comment_date_gmt, 'c'), 0, 32);

			// Build the new redirect URL
			$location = add_query_arg(
				array(
					'unapproved' => $comment->comment_ID,
					'moderation-hash' => $hash
				),
				get_permalink($comment->comment_post_ID)
			) . '#comment-' . $comment->comment_ID;
		}
		return $location;
	}

	function reviews_action_handler(){
		global $post;

		if ( is_page(get_option( 'listeo_reviews_page' ) ) ) {
			
			if ( ! empty( $_REQUEST['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'listeo_core_reviews_actions' ) ) {

			$action 		= sanitize_title( $_REQUEST['action'] );
			$comment_id 	= absint( $_REQUEST['comment_id'] );
			$current_user 	= wp_get_current_user();
    	

			try {
				// Get Job
				$comment    = get_comment( $comment_id );
	
				switch ( $action ) {
					
					case 'delete-comment' :
						// Trash it

			
						if($current_user->ID == $comment->user_id ){
							wp_trash_comment( $comment_id );

						// Message
							$this->dashboard_message =  '<div class="notification closeable success"><p>' . __( 'Review has been deleted', 'listeo_core' ) . '</p><a class="close" href="#"></a></div>';
	
						} else {
							$this->dashboard_message =  '<div class="notification closeable error"><p>' . __( 'You are trying to remove not your listing', 'listeo_core' ). '</p><a class="close" href="#"></a></div>';
						}
						
						break;
			
					default :
						do_action( 'listeo_core_dashboard_do_action_' . $action );
						break;
				}

				do_action( 'listeo_core_my_listing_do_action', $action, $comment_id );

			} catch ( Exception $e ) {
				$this->dashboard_message = '<div class="notification closeable error">' . $e->getMessage() . '</div>';
			}
		}
		}
	}
	function comments_fields($fields) {
		$type = get_post_type( get_the_ID() );

		$commenter = wp_get_current_commenter();
		$req = get_option( 'require_name_email' );
		$aria_req = ( $req ? " aria-required='true'" : '' );
		$consent  = empty( $commenter['comment_author_email'] ) ? '' : ' checked="checked"';

	    unset($fields['author']);
		unset($fields['email']);
		unset($fields['url']);
		unset($fields['cookies']);
		$comment_field = $fields['comment'];

		unset( $fields['comment'] );

		// Only add rating fields to $fields array for NON-logged-in users
		// Logged-in users get rating fields via comment_form_logged_in_after action
		// This prevents duplication if any code outputs fields for all users
		if($type == 'listing' && ! is_user_logged_in() ) {

			// Get criteria with context (per-type/per-taxonomy aware)
			$listing_id = get_the_ID();
			$context = array('listing_id' => $listing_id);
			$criteria_fields = listeo_get_reviews_criteria($context);
			ob_start();
			?>
			<!-- Subratings Container -->
					<div class="sub-ratings-container">
						<?php foreach ($criteria_fields as $key => $value) { ?>
							<!-- Subrating #1 -->
							<div class="add-sub-rating">
								<div class="sub-rating-title"><?php echo stripslashes(esc_html($value['label'])) ?>
									<?php if(isset($value['tooltip']) && !empty($value['tooltip'])) : ?><i class="tip" data-tip-content="<?php echo stripslashes(esc_html($value['tooltip'])); ?>"></i> <?php endif; ?>
								</div>
								<div class="sub-rating-stars">
									<!-- Leave Rating -->
									<div class="clearfix"></div>
									<div class="leave-rating">
										<?php for ($i=5; $i > 0; $i--) { ?>
											<input type="radio" name="<?php echo $key; ?>"
											id="rating-<?php echo $key.'-'.$i; ?>"
											value="<?php echo $i; ?>"/>
											<label for="rating-<?php echo $key.'-'.$i; ?>" class="fa fa-star"></label>
										<?php } ?>
										<span class="rating-error-message">
											<?php echo sprintf(__('Please rate %s', 'listeo_core'), $value['label']); ?>
										</span>
									</div>
								</div>
							</div>

						<?php }
						if(get_option('listeo_review_photos_disable')) {
							echo "</div>";
						}

			$rating_output = ob_get_clean();

			$fields['rating'] = $rating_output;
			if(!get_option('listeo_review_photos_disable')) {
			$fields['photo'] = '

						<!-- Uplaod Photos -->

							 <div class="uploadButton margin-top-15">
                                <input class="uploadButton-input" type="file"  name="attachments[]" accept="image/*, application/pdf" id="upload" multiple/>
                                <label class="uploadButton-button ripple-effect" for="upload">'.__('Add Photos','listeo_core').'</label>
                                <span class="uploadButton-file-name"></span>
                            </div>



				</div>';
			}
		}
		
	
		$fields['author'] = '
					<div class="row">
						<div class="col-md-6 comment-form-author">' . '<label for="author">' . __( 'Name','listeo_core' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
             '<input id="author" name="author" type="text"  ' . ( $req ? ' required' : '' ) . ' value="' . esc_attr( $commenter['comment_author'] ) . '" size="30"' . $aria_req . ' />
             			</div>';		

		$fields['email'] = '
						<div class="col-md-6 comment-form-email">' . '<label for="email">' . __( 'Email','listeo_core' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
             '<input id="email" name="email" type="email" ' . ( $req ? ' required' : '' ) . ' value="' . esc_attr(  $commenter['comment_author_email'] ) . '" size="30"' . $aria_req . ' />
             			</div>
             		</div>';				
		
		$fields['comment'] = $comment_field;
		if(get_option('listeo_recaptcha_reviews')) :

			$recaptcha_status = get_option('listeo_recaptcha');
			$recaptcha_version = get_option('listeo_recaptcha_version');

			if($recaptcha_status && $recaptcha_version == 'v2'){ 
				$fields['recaptcha'] = 
						'<div class="row">
							<div class="form-row col-md-12 captcha_wrapper">
								<div class="g-recaptcha" data-sitekey="'. get_option('listeo_recaptcha_sitekey').'"></div>
							</div>
						</div>';
			}

			if($recaptcha_status && $recaptcha_version == 'v3'){ 
                   $fields['recaptcha'] =  '
                  	 	<input type="hidden" id="rc_action" name="rc_action" value="ws_review">
                    	<input type="hidden" id="token" name="token">';
            }
			 if ($recaptcha_status && $recaptcha_version == 'hcaptcha'):
						$fields['recaptcha'] =  '<div class="h-captcha" data-sitekey="'.esc_attr(get_option('listeo_hcaptcha_sitekey')).'"></div>';
			endif;
			if ($recaptcha_status && $recaptcha_version == 'turnstile'):
						$fields['recaptcha'] =  '<div class="cf-turnstile" data-theme="light" data-sitekey="'.esc_attr(get_option('listeo_turnstile_sitekey')).'"></div>';
			endif;

		endif;
		$fields['cookies'] = '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' />' .
					 '<label for="wp-comment-cookies-consent">' . __( 'Save my name, email, and website in this browser for the next time I comment.','listeo_core' ) . '</label></p>';		
		return $fields;
	}

	function comments_logged_in_fields(){
		$type = get_post_type( get_the_ID() );
		if($type != 'listing') {
			return;
		}
		// Get criteria with context (per-type/per-taxonomy aware)
		$listing_id = get_the_ID();
		$context = array('listing_id' => $listing_id);
		$criteria_fields = listeo_get_reviews_criteria($context);
		?>
		<!-- Subratings Container -->
				<div class="sub-ratings-container">
					<?php foreach ($criteria_fields as $key => $value) { ?>
						<!-- Subrating #1 -->
						<div class="add-sub-rating">
							<div class="sub-rating-title"><?php echo stripslashes(esc_html($value['label'])) ?> 
								<?php if(isset($value['tooltip']) && !empty($value['tooltip'])) : ?><i class="tip" data-tip-content="<?php echo stripslashes(esc_html($value['tooltip'])); ?>"></i> <?php endif; ?>
							</div>
							<div class="sub-rating-stars">
								<!-- Leave Rating -->
								<div class="clearfix"></div>
								<div class="leave-rating">
									<?php for ($i=5; $i > 0; $i--) { ?>
										<input type="radio"  name="<?php echo $key; ?>" id="rating-<?php echo $key.'-'.$i; ?>" value="<?php echo $i; ?>"/>
										<label for="rating-<?php echo $key.'-'.$i; ?>" class="fa fa-star"></label>
									<?php } ?>
									
								</div>
							</div>
						</div>
					<?php } ?>
					<?php if(!get_option('listeo_review_photos_disable')) { ?>
					<!-- Uplaod Photos -->
	                <div class="uploadButton margin-top-15">
                        <input class="uploadButton-input" type="file"  name="attachments[]" accept="image/*, application/pdf" id="upload" multiple/>
                        <label class="uploadButton-button ripple-effect" for="upload"><?php esc_html_e('Add Photos','listeo_core') ?></label>
                        <span class="uploadButton-file-name"></span>
                    </div>
	                <!-- Uplaod Photos / End -->
	            	<?php } ?>

				</div>
				<!-- Subratings Container / End -->

		<?php
	}

	function save_comment_meta_data($comment_id){
		// Get comment data to determine listing ID
		$commentdata = get_comment($comment_id, ARRAY_A);
		$listing_id = $commentdata['comment_post_ID'];

		// Get criteria with context (per-type/per-taxonomy aware)
		$context = array('listing_id' => $listing_id);
		$criteria_fields = listeo_get_reviews_criteria($context);

		// Store criteria snapshot for historical reference
		// This ensures old reviews display their original criteria even if criteria change
		add_comment_meta($comment_id, '_review_criteria_snapshot', $criteria_fields);

		$count_criteria = 0;
		$total_criteria = 0;
		foreach ($criteria_fields as $key => $value) {

			if ( ( isset( $_POST[$key] ) ) && ( $_POST[$key] != '') ) {
				$count_criteria++;
			  	$rating = wp_filter_nohtml_kses( $_POST[$key] );
			  	$total_criteria = $total_criteria + (int) $rating;

			  	add_comment_meta( $comment_id, $key, $rating );

	  		}
		}
		if($total_criteria>0){
			$listeo_rating = (float) $total_criteria/$count_criteria;
			add_comment_meta( $comment_id, 'listeo-rating', $listeo_rating );
		}

	}

	function transition_comment_callbacks($new_status, $old_status, $comment) {
		
		if($old_status != $new_status) {
	        
	          	$commentdata = get_comment($comment->comment_ID, ARRAY_A); 
				$parent_post = get_post($commentdata['comment_post_ID']);
				if($parent_post){
					$reviews = $this->get_average_post_rating($parent_post->ID,'listeo-rating');

					update_post_meta( $parent_post->ID, 'listeo-avg-rating', $reviews['rating']);

					// Recalculate combined rating after local rating changes
					$this->get_combined_rating($parent_post->ID);

					// Get current criteria for this listing (context-aware)
					$context = array('listing_id' => $parent_post->ID);
					$criteria_fields = listeo_get_reviews_criteria($context);
					foreach ($criteria_fields as $key => $value) {
						$reviews = $this->get_average_post_rating($parent_post->ID,$key);
						if($reviews){
							update_post_meta( $parent_post->ID, $key.'-avg', $reviews['rating']);
						}

					}
				}
				
	    }
			
	  		
	}

	function add_comment_rating($comment_ID, $comment_approved){
		if( 1 === $comment_approved ){
				$commentdata = get_comment($comment_ID, ARRAY_A);
				$parent_post = get_post($commentdata['comment_post_ID']);

				// Get current criteria for this listing (context-aware)
				$context = array('listing_id' => $parent_post->ID);
				$criteria_fields = listeo_get_reviews_criteria($context);

				$reviews = $this->get_average_post_rating($parent_post->ID,'listeo-rating');
				update_post_meta( $parent_post->ID, 'listeo-avg-rating', $reviews['rating']);

				// Recalculate combined rating after local rating changes
				$this->get_combined_rating($parent_post->ID);

				foreach ($criteria_fields as $key => $value) {
					$reviews = $this->get_average_post_rating($parent_post->ID,$key);
					if(isset($reviews['rating']) && !empty($reviews['rating']))
					update_post_meta( $parent_post->ID, $key.'-avg', $reviews['rating']);
				}
		}
	}

	public function get_average_post_rating($id,$field){
		
		global $post;
		
		$overall_ratings = 0;
		$count_ratings = 0;

		if(empty($id)){
			$args = array(
				'post_id' => $post->ID,
				'status' => 'approve',
				'meta_key' => $field
			);
		} else {
			$args = array(
				'post_id' => $id,
				'status' => 'approve',
				'meta_key' => $field
			);
		}

		$ratings = get_comments( $args );
		$count_ratings = 0;
		foreach ( $ratings as $rating ) {
			$rating_value = get_comment_meta( $rating->comment_ID, $field, true );
			if($rating_value > 0 ) {
				$overall_ratings += $rating_value;
				$count_ratings++;
			}
		}

		if ( $overall_ratings == 0 || $count_ratings == 0 ) {
			return 0;
		} else {
			$average_count = $overall_ratings / $count_ratings ;
			//$average_count = round($average_count, 0, PHP_ROUND_HALF_UP);
			$reviews = array(
				'reviews' => $count_ratings,
				'rating' => $average_count
				);

			return $reviews;
		}
	}


	function add_custom_comment_field_meta_boxes() {
	    
		add_meta_box( 'listeo-rating', __( 'Rating','listeo_core' ), array( $this, 'listeo_comment_rating_field_meta_box'), 'comment', 'normal', 'high' );
	}

	function listeo_comment_rating_field_meta_box( $comment ) {
	    $rating = get_comment_meta( $comment->comment_ID, 'listeo-rating', true );
	    wp_nonce_field( 'update_comment_rating', 'update_comment_rating', false );
	    ?>
	    <p>
	        <label for="rating"><?php esc_html_e( 'General Rating' ,'listeo_core'); ?></label>
	        <input type="text" name="listeo-rating" disabled value="<?php echo esc_attr( $rating ); ?>" class="form-table editcomment" />
	    </p>
	    <?php
	    // Get criteria from review snapshot (shows original criteria)
	    $criteria_fields = listeo_get_review_criteria($comment->comment_ID);
	    foreach ($criteria_fields as $key => $value) {
	    	$key_rating = get_comment_meta( $comment->comment_ID, $key, true );
	    	?>
	    	<p>
		        <label for="rating"><?php echo esc_html($value['label']); ?></label>
		        <input type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr( $key_rating ); ?>" class="form-table editcomment" />
		    </p>
	    <?php }

	}


	function update_edit_comment( $comment_id ) {
	    if( ! isset( $_POST['update_comment_rating'] ) || ! wp_verify_nonce( $_POST['update_comment_rating'], 'update_comment_rating' ) ) return;
	    // if( isset( $_POST['listeo-rating'] ) )
		   //  update_comment_meta( $comment_id, 'listeo-rating', esc_attr( $_POST['listeo-rating'] ) );

		// Get criteria from review snapshot (edits use original criteria)
		$criteria_fields = listeo_get_review_criteria($comment_id);
		$count_criteria = 0;
		$total_criteria = 0;
		foreach ($criteria_fields as $key => $value) {

			if ( ( isset( $_POST[$key] ) ) && ( $_POST[$key] != '') ) {
				$count_criteria++;
			  	$rating = wp_filter_nohtml_kses( $_POST[$key] );
			  	$total_criteria = $total_criteria + (int) $rating;
			  	update_comment_meta( $comment_id, $key, $rating );
			  	 	
	  		}
		}
		if($total_criteria>0){
			$listeo_rating = (float) $total_criteria/$count_criteria;
			update_comment_meta( $comment_id, 'listeo-rating', $listeo_rating );
			
			$commentdata = get_comment($comment_id, ARRAY_A); 
			$parent_post = get_post($commentdata['comment_post_ID']);
			
			$reviews = $this->get_average_post_rating($parent_post->ID,'listeo-rating');
			update_post_meta( $parent_post->ID, 'listeo-avg-rating', $reviews['rating']);
		
			$criteria_fields = listeo_get_reviews_criteria();
			foreach ($criteria_fields as $key => $value) {
				$reviews = $this->get_average_post_rating($parent_post->ID,$key);
				update_post_meta( $parent_post->ID, $key.'-avg', $reviews['rating']);
			}
		}


		// $criteria_fields = listeo_get_reviews_criteria();
	 //    foreach ($criteria_fields as $key => $value) { 
	 //    	if( isset( $_POST[$key] ) ){
	 //    			update_comment_meta( $comment_id, $key, esc_attr( $_POST[$key] ) );		
	 //    	}
	 //    }
	}



	function add_custom_fields_to_edit_comment_screen() {
	    $screen = get_current_screen();
	    add_filter("manage_{$screen->id}_columns", array($this,'add_custom_comment_columns'));
	}

	function add_custom_comment_columns($cols) {
	    $cols['rating'] = __('Rating', 'listeo-core');
	    return $cols;
	}


	function custom_rating_column($col, $comment_id) {
	   
	    switch($col) {
	        case 'rating':
	            if($ind = get_comment_meta($comment_id, 'listeo-rating', true)){
	                echo esc_html($ind);
	            } else {
	                esc_html_e('No Rating Submitted','listeo_core');
	            }
	    }
	}


	/**
	 * User bookmarks shortcode
	 */
	public function listeo_reviews( $atts ) {
		
		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to manage your reviews.', 'listeo_core' );
		}

		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );

		ob_start();
		$template_loader = new Listeo_Core_Template_Loader;

		
		$template_loader->set_template_data( 
			array( 
				'message' => $this->dashboard_message, 
				'posts_per_page'  => $posts_per_page,
				//'user_reviews' => $this->get_reviews(),
			) 
		)->get_template_part( 'account/reviews' ); 


		return ob_get_clean();
	}

	// Comments attachments
	public function make_form_multipart(){
			$action = site_url( '/wp-comments-post.php' );
            echo '</form><form action="'. $action .'" method="POST" enctype="multipart/form-data" id="commentform" class="comment-form" >';
    }

    public function save_comment_attachment($comment_id){
    	$id = $_POST['comment_post_ID'];
    	if ( $_FILES ) { 
	    	$files = $_FILES["attachments"];  
	    	foreach ($files['name'] as $key => $value) {            
	            if ($files['name'][$key]) { 
	                $file = array( 
	                    'name' => $files['name'][$key],
	                    'type' => $files['type'][$key], 
	                    'tmp_name' => $files['tmp_name'][$key], 
	                    'error' => $files['error'][$key],
	                    'size' => $files['size'][$key]
	                ); 
	                $_FILES = array ("attachments" => $file); 
	                foreach ($_FILES as $file => $array) {
	                    $attachId = $this->insert_attachment($file, $id);
	                    add_comment_meta($comment_id, 'listeo-attachment-id', $attachId);         
	                    
	                }
	            } 
	        } 
	        unset($_FILES);
	    }
    }

    public function insert_attachment($fileHandler, $post_id){
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            require_once(ABSPATH . "wp-admin" . '/includes/file.php');
            require_once(ABSPATH . "wp-admin" . '/includes/media.php');
            return media_handle_upload($fileHandler, $post_id);
        }


    public function delete_comment_attachment($comment_id) {
        $attachments = get_comment_meta($comment_id, 'listeo-attachment-id', false);
        foreach ($attachments as $key => $attachment_id) {
        	if(is_numeric($attachment_id) && !empty($attachment_id) ){
                wp_delete_attachment($attachment_id, TRUE);
            }
        }
    }

	public function delete_comment_meta($comment_id) {
		delete_comment_meta( $comment_id, 'listeo-rating' );
		$commentdata = get_comment($comment_id, ARRAY_A);
		$parent_post = get_post($commentdata['comment_post_ID']);

		$reviews = $this->get_average_post_rating($parent_post->ID,'listeo-rating');
		update_post_meta( $parent_post->ID, 'listeo-avg-rating', $reviews['rating']);

		// Delete criteria meta from deleted review (use review's snapshot)
		$review_criteria = listeo_get_review_criteria($comment_id);
		foreach ($review_criteria as $key => $value) {
			delete_comment_meta( $comment_id, $key );
		}

		// Recalculate listing averages (use listing's current criteria)
		$context = array('listing_id' => $parent_post->ID);
		$listing_criteria = listeo_get_reviews_criteria($context);
		foreach ($listing_criteria as $key => $value) {
			$reviews = $this->get_average_post_rating($parent_post->ID,$key);
			update_post_meta( $parent_post->ID, $key.'-avg', $reviews['rating']);
		}


    }
    


    public function captcha_verification() {


		$recaptcha_version = get_option('listeo_recaptcha_version');
		
		if($recaptcha_version == 'v2'){
			$response = isset( $_POST['g-recaptcha-response'] ) ? esc_attr( $_POST['g-recaptcha-response'] ) : '';

			$remote_ip = $_SERVER["REMOTE_ADDR"];

			// make a GET request to the Google reCAPTCHA Server
			$request = wp_remote_get(
				'https://www.google.com/recaptcha/api/siteverify?secret='.get_option('listeo_recaptcha_secretkey').'&response=' . $response
			);

			// get the request response body
			$response_body = wp_remote_retrieve_body( $request );

			$result = json_decode( $response_body, true );

			return $result['success'];
		}

		if($recaptcha_version == 'v3'){
			if(isset($_POST['token']) && !empty($_POST['token'])):
			        //your site secret key
			        $secret = get_option('listeo_recaptcha_secretkey3');
			        //get verify response data
			        $verifyResponse = wp_remote_get('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['token']);
			        $responseData_w = wp_remote_retrieve_body( $verifyResponse );
		        	$responseData = json_decode($responseData_w);
		        	
					if($responseData->success == '1' && $responseData->action == 'login' && $responseData->score >= 0.5) :
						$result['success'];
		        	else:
		        		$result['success'] = false;
	        		endif;
	        endif;
		}

		if($recaptcha_version == 'hcaptcha'){
			
			$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ? esc_attr( $_POST['h-captcha-response'] ) : '';
			$secret = get_option('listeo_hcaptcha_secretkey');

			$verify_url = 'https://hcaptcha.com/siteverify';
			$data = array(
				'secret' => $secret,
				'response' => $hcaptcha_response
			);
			$verify = wp_remote_post($verify_url, array('body' => $data));
			$response = json_decode(wp_remote_retrieve_body($verify));
			if ($response->success) :
				$result['success'] = true;
			else:
				$result['success'] = false;
			endif;
			
		}

		if($recaptcha_version == 'turnstile'){
			
			$turnstile_response = isset( $_POST['cf-turnstile-response'] ) ? esc_attr( $_POST['cf-turnstile-response'] ) : '';
			$secret = get_option('listeo_turnstile_secretkey');

			$verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
			$data = array(
				'secret' => $secret,
				'response' => $turnstile_response
			);
			$verify = wp_remote_post($verify_url, array('body' => $data));
			$response = json_decode(wp_remote_retrieve_body($verify));
			if ($response->success) :
				$result['success'] = true;
			else:
				$result['success'] = false;
			endif;
			
		}
	
		
	}

    public function validate_captcha_comment_field( $commentdata ) {
    		global $captcha_error;

    		if(get_option('listeo_recaptcha_reviews')) :

				$recaptcha_status = get_option('listeo_recaptcha');
				$recaptcha_version = get_option('listeo_recaptcha_version');
				if($recaptcha_version = 'v2' && $recaptcha_status){
					if ( isset( $_POST['g-recaptcha-response'] ) && ! ($this->captcha_verification()) ) {
						$captcha_error = 'failed';
					}
				}
				if($recaptcha_version = 'v3' && $recaptcha_status){
					if(isset($_POST['token']) && !empty($_POST['token']) && ! ($this->captcha_verification()) ) {
							$captcha_error = 'failed';
					}
				}
			endif;

		return $commentdata;
    }

    public function redirect_fail_captcha_comment( $location, $comment ) {
		global $captcha_error;

		if ( ! empty( $captcha_error ) ) {

			// delete the failed captcha comment
			wp_delete_comment( absint( $comment->comment_ID ) );

			// add failed query string for @parent::display_captcha to display error message
			$location = add_query_arg( 'captcha', 'failed', $location );

		}

		return $location;
	}
     /**
     * Checks attachment, size, and type and throws error if something goes wrong.
     *
     * @param $data
     * @return mixed
     */

    public function check_if_attachment_is_image($data) {
    	
    	$image_mime_types = array(
                'image/jpeg',
                'image/jpg',
                'image/jp_',
				'image/webp',
                'application/jpg',
                'application/x-jpg',
                'image/pjpeg',
                'image/pipeg',
                'image/vnd.swiftview-jpeg',
                'image/x-xbitmap',
                'image/gif',
                'image/x-xbitmap',
                'image/gi_',
                'image/png',
                'application/png',
                'application/x-png'
            );
    	$image_file_types = array(
    			'jpg',
    			'jpeg',
				'webp',
				'gif',
				'png',
			);
    	$orginal_files = $_FILES;
    	if ( $_FILES ) { 
	    	$files = $_FILES["attachments"];  
	    	foreach ($files['name'] as $key => $value) {            
	            if ($files['name'][$key]) { 
	                $file = array( 
	                    'name' => $files['name'][$key],
	                    'type' => $files['type'][$key], 
	                    'tmp_name' => $files['tmp_name'][$key], 
	                    'error' => $files['error'][$key],
	                    'size' => $files['size'][$key]
	                ); 
	                $_FILES = array ("attachments" => $file); 
	                foreach ($_FILES as $file => $array) {
	                	if($array['size'] > 0 && $array['error'] == 0){

		                	$fileInfo = pathinfo($array['name']);

				            $fileExtension = strtolower($fileInfo['extension']);

				            if(function_exists('finfo_file')){
				                $fileType = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $array['tmp_name']);
				            } elseif(function_exists('mime_content_type')) {
				                $fileType = mime_content_type($array['tmp_name']);
				            } else {
				                $fileType = $array['type'];
				            }

							// Is: allowed mime type / file extension, and size? extension making lowercase, just to make sure
				            if (!in_array($fileType, $image_mime_types) || !in_array(strtolower($fileExtension),$image_file_types) ) { // file size from admin
				                wp_die(__('<strong>ERROR:</strong> File you upload must be valid file type,','listeo-core'));
				            }
				        } elseif($array['error']  == 1) {
				            wp_die(__('<strong>ERROR:</strong> The uploaded file exceeds the upload_max_filesize directive in php.ini.','listeo_core'));
				        } elseif($array['error']  == 2) {
				            wp_die(__('<strong>ERROR:</strong> The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.','listeo_core'));
				        } elseif($array['error']  == 3) {
				            wp_die(__('<strong>ERROR:</strong> The uploaded file was only partially uploaded. Please try again later.','listeo_core'));
				        } elseif($array['error']  == 6) {
				            wp_die(__('<strong>ERROR:</strong> Missing a temporary folder.','listeo_core'));
				        } elseif($array['error']  == 7) {
				            wp_die(__('<strong>ERROR:</strong> Failed to write file to disk.','listeo_core'));
				        } elseif($array['error']  == 7) {
				            wp_die(__('<strong>ERROR:</strong> A PHP extension stopped the file upload.','listeo_core'));
				        }                    
	                }
	            } 
	        } 
	    }
    	$_FILES = $orginal_files;
        return $data;
    }
	
	function reload_reviews(){
		
		$id = sanitize_text_field(trim($_POST['id']));
		$current_user = wp_get_current_user();
		$limit = 2;

		
	    $visitor_reviews_page = (isset($_POST['page'])) ? $_POST['page'] : 1;
		add_filter( 'comments_clauses', 'listeo_top_comments_only' );
		$visitor_reviews_offset = ($visitor_reviews_page * $limit) - $limit;
		$total_visitor_reviews = get_comments(
				array(
					'orderby' 	=> 'post_date',
            		'order' 	=> 'DESC',
           			'status' 	=> 'approve',
            		'post_author' => $current_user->ID,
					'parent'    => 0,
					'post_id'    => $id,
					'post_type' => 'listing',
            	)
			);
	  
		$visitor_reviews_args = array(

			'post_author' 	=> $current_user->ID,
			'parent'      	=> 0,
			'status' 		=> 'approve',
			'post_type' 	=> 'listing',
			'post_id'    	=> $id,
			'number' 		=> $limit,
			'offset' 		=> $visitor_reviews_offset,
		);
		$visitor_reviews_pages = ceil(count($total_visitor_reviews)/$limit);
		
		$visitor_reviews = get_comments( $visitor_reviews_args ); 
		remove_filter( 'comments_clauses', 'listeo_top_comments_only' );
		

		ob_start();
		
		
		if(empty($visitor_reviews)) : ?>
			
				<li>
					<p><?php esc_html_e('You don\'t have any reviews for ','listeo_core'); 
					echo get_the_title( $id ); ?></p>
				</li>
			
		<?php else :  
			
			foreach($visitor_reviews as $review) :
				?>
				<li class="review-li" data-review="<?php echo esc_attr($review->comment_ID); ?>" id="review-<?php echo esc_attr($review->comment_ID); ?>">
					<div class="comments listing-reviews">
						<ul>
							<li>
								<div class="avatar"><?php echo get_avatar( $review, 70 ); ?></div>
								<div class="comment-content"><div class="arrow-comment"></div>
									<div class="comment-by"><?php echo esc_html($review->comment_author); ?>
									<div class="comment-by-listing"><?php esc_html_e('on','listeo_core'); ?> 
										<a href="<?php echo esc_url(get_permalink($review->comment_post_ID)); ?>"><?php echo get_the_title(
										$review->comment_post_ID) ?></a></div> 
										<span class="date"><?php echo date_i18n(  get_option( 'date_format' ),  strtotime($review->comment_date), false ); ?></span>
										<?php 
										$star_rating = get_comment_meta( $review->comment_ID, 'listeo-rating', true );  
										if($star_rating) : ?>
										<div class="star-rating" data-rating="<?php echo get_comment_meta( $review->comment_ID, 'listeo-rating', true ); ?>"></div>
										<?php endif; ?>
									</div>
									<?php echo wpautop( $review->comment_content ); ?>
									
									<?php 
						            $photos = get_comment_meta( $review->comment_ID, 'listeo-attachment-id', false );
						            if($photos) : ?>
						            <div class="review-images mfp-gallery-container">
						            	<?php foreach ($photos as $key => $attachment_id) {
						            		$image = wp_get_attachment_image_src( $attachment_id, 'listeo-gallery' );
						            		$image_thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
						            	 ?>
										<a href="<?php echo esc_attr($image[0]); ?>" class="mfp-gallery"><img src="<?php echo esc_attr($image_thumb[0]); ?>" alt=""></a>
										<?php } ?>
									</div>
									<?php endif;

									if(listeo_check_if_review_replied($review->comment_ID,$current_user->ID)) { 
										$reply = listeo_get_review_reply($review->comment_ID,$current_user->ID);
										
										?>
										<a href="#small-dialog-edit" class="rate-review edit-reply  popup-with-zoom-anim" 
										<?php if(!empty($reply)): ?>
										data-comment-id="<?php echo $reply[0]->comment_ID; ?>"
										data-comment-content="<?php echo $reply[0]->comment_content; ?>"
										<?php endif; ?>
										><i class="sl sl-icon-pencil"></i> <?php esc_html_e('Edit your reply','listeo_core') ?></a>
										
									<?php } else { ?>
									<a data-replyid="<?php echo esc_attr($review->comment_ID); ?>" data-postid="<?php echo esc_attr($review->comment_post_ID); ?>" href="#small-dialog" class="reply-to-review-link rate-review popup-with-zoom-anim"><i class="sl sl-icon-action-undo"></i> <?php esc_html_e('Reply to this review','listeo_core') ?></a>
									<?php } ?>
								</div>
							</li>
						</ul>
					</div>
				</li>
				
			<?php endforeach; ?>
		
		<?php endif; 
		$output = ob_get_clean();
		$pagination = listeo_core_ajax_pagination( $visitor_reviews_pages, $visitor_reviews_page );
		

		echo json_encode(array('success'=>true, 'comments'=>$output, 'pagination' => $pagination));
		die();
		
	}

	function reply_to_review(){

		global $post; //for this example only :)
		$current_user = wp_get_current_user();	
		$post_id = sanitize_text_field(trim($_POST['post_id']));
		$review_id = sanitize_text_field(trim($_POST['review_id']));
		$content = sanitize_text_field(trim($_POST['content']));

		$commentdata = array(
			'comment_post_ID' => $post_id, // to which post the comment will show up
			'comment_author' => $current_user->display_name, //fixed value - can be dynamic 
			'comment_author_email' => $current_user->user_email, //fixed value - can be dynamic 
			'comment_content' => $content, //fixed value - can be dynamic 
			'comment_type' => '', //empty for regular comments, 'pingback' for pingbacks, 'trackback' for trackbacks
			'comment_parent' => $review_id, //0 if it's not a reply to another comment; if it's a reply, mention the parent comment ID here
			'comment_date' => date('Y-m-d H:i:s'),
			'comment_date_gmt' => date('Y-m-d H:i:s'),
			'comment_approved' => 1,
			'user_id' => $current_user->ID, //passing current user ID or any predefined as per the demand
		);

		//Insert new comment and get the comment ID
		$comment_id = wp_new_comment( $commentdata );
		if ($comment_id !== 0) {
		   echo json_encode(array('success'=>true));
		} else {
		   echo json_encode(array('success'=>false));
		}
		
		die();
	}


	public function edit_reply_to_review(){

		global $post; //for this example only :)
		$current_user = wp_get_current_user();	
		
		$reply_id = sanitize_text_field(trim($_POST['reply_id']));
		$content = sanitize_text_field(trim($_POST['content']));

		$commentdata = array(
			'comment_ID' => $reply_id,
			'comment_content' => $content, //fixed value - can be dynamic 
			'comment_date' => date('Y-m-d H:i:s'),
			'comment_date_gmt' => date('Y-m-d H:i:s'),
			'comment_approved' =>  !get_option( 'comment_moderation'),
			'user_id' => $current_user->ID, //passing current user ID or any predefined as per the demand
		);

		//Insert new comment and get the comment ID
		$result = wp_update_comment( $commentdata );
		if ($result == 1) {
		   echo json_encode(array('success'=>true));
		} else {
		   echo json_encode(array('success'=>false));
		}
		
		die();
	}

	public function edit_review(){

		global $post; //for this example only :)
		$current_user = wp_get_current_user();	
		
		$reply_id = sanitize_text_field(trim($_POST['reply_id']));
		$content = sanitize_textarea_field(trim($_POST['content']));

		$commentdata = array(
			'comment_ID' => $reply_id,
			'comment_content' => $content, //fixed value - can be dynamic 
			'comment_date' => date('Y-m-d H:i:s'),
			'comment_date_gmt' => date('Y-m-d H:i:s'),
			'comment_approved' => 0,
			'user_id' => $current_user->ID, //passing current user ID or any predefined as per the demand
		);

		//Insert new comment and get the comment ID
		$result = wp_update_comment( $commentdata );
		if ($result == 1) {
			// Get criteria from review snapshot (edit uses original criteria)
			$criteria_fields = listeo_get_review_criteria($reply_id);
			$count_criteria = 0;
			$total_criteria = 0;
			foreach ($criteria_fields as $key => $value) {

				if ( ( isset( $_POST['rating_'.$key] ) ) && ( $_POST['rating_'.$key] != '') ) {
					$count_criteria++;
				  	$rating = wp_filter_nohtml_kses( $_POST['rating_'.$key] );
				  	$total_criteria = $total_criteria + (int) $rating;

				  	update_comment_meta( $reply_id, $key, $rating );

		  		}
			}
			if($total_criteria>0){
				$listeo_rating = (float) $total_criteria/$count_criteria;
				update_comment_meta( $reply_id, 'listeo-rating', $listeo_rating );

				$commentdata = get_comment($reply_id, ARRAY_A);
				$parent_post = get_post($commentdata['comment_post_ID']);

				$reviews = $this->get_average_post_rating($parent_post->ID,'listeo-rating');
				if (!empty($reviews)) {
				update_post_meta( $parent_post->ID, 'listeo-avg-rating', $reviews['rating']);
				}

				// Recalculate listing averages (use listing's current criteria)
				$context = array('listing_id' => $parent_post->ID);
				$listing_criteria = listeo_get_reviews_criteria($context);
		  		foreach ($listing_criteria as $key => $value) {
					$reviews = $this->get_average_post_rating($parent_post->ID,$key);
					if(!empty($reviews)){
						update_post_meta($parent_post->ID, $key . '-avg', $reviews['rating']);
					}
					
				}
			}

			// if ( ( isset( $_POST['rating'] ) ) && ( $_POST['rating'] != '') ) {

			//   	$rating = wp_filter_nohtml_kses($_POST['rating']);
			//   	update_comment_meta( $reply_id, 'listeo-rating', $rating );

			//   	$commentdata = get_comment($reply_id, ARRAY_A); 
			// 	$parent_post = get_post($commentdata['comment_post_ID']);
				
			// 	$reviews = $this->get_average_post_rating($parent_post->ID);
			// 	update_post_meta( $parent_post->ID, 'listeo-avg-rating', $reviews['rating']);
		  		 	
	  	
		   echo json_encode(array('success'=>true));
		} else {
		   echo json_encode(array('success'=>false));
		}
		
		die();
	}

	function rate_review()
	{
		$comment_id = $_POST['comment'];

		// Check for existing vote cookie
		if (isset($_COOKIE['listeo_rate_review_' . $comment_id])) {
			$result['type'] = 'error';
			$result['output'] = '<i class="sl sl-icon-like"></i> ' . esc_html__('You already voted ', 'listeo_core');
			wp_send_json($result);
			die();
		}

		// Check for transient lock
		if (get_transient('listeo_rate_review_lock_' . $comment_id)) {
			$result['type'] = 'error';
			$result['output'] = '<i class="sl sl-icon-like"></i> ' . esc_html__('Please wait before voting again', 'listeo_core');
			wp_send_json($result);
			die();
		}

		// Set a transient lock for 5 seconds
		set_transient('listeo_rate_review_lock_' . $comment_id, true, 5);

		// Get current rating
		$rating = (int) get_comment_meta($comment_id, 'listeo-review-rating', true);
		$new_rating = $rating + 1;

		// Update the rating
		$update = update_comment_meta($comment_id, 'listeo-review-rating', $new_rating);

		if ($update) {
			$result['type'] = 'success';
			$result['output'] = '<i class="sl sl-icon-like"></i> ' . esc_html__('Helpful Review ', 'listeo_core') . '<span>' . $new_rating . '</span>';

			// Set the vote cookie
			setcookie('listeo_rate_review_' . $comment_id, $comment_id, 0, COOKIEPATH, COOKIE_DOMAIN, false, false);
		} else {
			$result['type'] = 'error';
			$result['output'] = '<i class="sl sl-icon-like"></i> ' . esc_html__('Helpful Review ', 'listeo_core') . '<span>' . $new_rating . '</span>';
		}

		// Delete the lock transient since we're done
		delete_transient('listeo_rate_review_lock_' . $comment_id);

		wp_send_json($result);
		die();
	}

	function get_comment_review_details(){
		$comment_id = $_POST['comment'];
		$commentdata = get_comment($comment_id);

		$result = array();
		$result['comment_content'] = $commentdata->comment_content;
		$result['rating'] = get_comment_meta( $comment_id, 'listeo-rating', true );

		// Get criteria from review snapshot (shows original criteria)
		$criteria_fields = listeo_get_review_criteria($comment_id);

		ob_start();
		
		foreach ($criteria_fields as $key => $value) {
			$this_rating = get_comment_meta( $comment_id, $key, true ); ?>
			<!-- Subrating #1 -->
			<div class="add-sub-rating">
				<div class="sub-rating-title"><?php echo esc_html($value['label']) ?> 
					<?php if(isset($value['tooltip']) && !empty($value['tooltip'])) : ?><i class="tip" data-tip-content="<?php echo esc_html($value['tooltip']); ?>"></i> <?php endif; ?>
				</div>
				<div class="sub-rating-stars">
					<!-- Leave Rating -->
					<div class="clearfix"></div>
					<div class="leave-rating">
						<?php for ($i=5; $i > 0; $i--) { ?>
							<input <?php checked($this_rating,$i) ?> type="radio" name="<?php echo $key; ?>" id="rating-<?php echo $key.'-'.$i; ?>" value="<?php echo $i; ?>"/>
							<label for="rating-<?php echo $key.'-'.$i; ?>" class="fa fa-star"></label>
						<?php } ?>
						
					</div>
				</div>
			</div>
		<?php } 
		$result['ratings'] = ob_get_clean();
		// foreach ($criteria_fields as $key => $value) {

		
		
		wp_send_json($result);
		die();
	}

	/**
	 * Calculate combined rating from Google Places and Listeo reviews
	 * Uses weighted average based on review counts
	 * 
	 * @param int $post_id The post ID
	 * @return float Combined rating (0-5) or 0 if no reviews
	 */
	public function get_combined_rating($post_id) {
		// Get Google rating and count
		$google_rating = floatval(get_post_meta($post_id, '_google_rating', true));
		$google_count = intval(get_post_meta($post_id, '_google_review_count', true));

		// Get Listeo rating and count
		$listeo_rating = floatval(get_post_meta($post_id, 'listeo-avg-rating', true));
		// Only count comments that have ratings (excludes owner replies without ratings)
		$listeo_reviews = get_comments(array(
			'post_id' => $post_id,
			'status' => 'approve',
			'meta_key' => 'listeo-rating',
			'count' => true
		));
		$listeo_count = intval($listeo_reviews);
		
		// Calculate combined rating and count
		$combined_rating = 0;
		$total_reviews = $google_count + $listeo_count;
		
		if ($total_reviews > 0) {
			// Calculate weighted average when both types exist
			$total_stars = ($google_rating * $google_count) + ($listeo_rating * $listeo_count);
			$combined_rating = round($total_stars / $total_reviews, 1);
		} elseif ($google_count > 0) {
			// Only Google reviews exist
			$combined_rating = round($google_rating, 1);
			$total_reviews = $google_count;
		} elseif ($listeo_count > 0) {
			// Only Listeo reviews exist
			$combined_rating = round($listeo_rating, 1);
			$total_reviews = $listeo_count;
		}
		
		// Always cache the results, even if 0 (this ensures the meta field exists for filtering)
		update_post_meta($post_id, '_combined_rating', $combined_rating);
		update_post_meta($post_id, '_combined_review_count', $total_reviews);
		
		return $combined_rating;
	}

	/**
	 * Migrate all existing listings to use combined ratings
	 * This should be run once after implementing the combined rating system
	 * 
	 * @param int $batch_size Number of listings to process per batch
	 * @param int $offset Starting offset for batch processing
	 * @return array Results with 'processed', 'remaining', and 'completed' status
	 */
	public function migrate_to_combined_ratings($batch_size = 50, $offset = 0) {
		$args = array(
			'post_type' => 'listing',
			'post_status' => 'publish',
			'posts_per_page' => $batch_size,
			'offset' => $offset,
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_combined_rating',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key' => '_combined_rating',
					'value' => '',
					'compare' => '='
				)
			)
		);
		
		$listings = get_posts($args);
		$processed = 0;
		
		foreach ($listings as $listing) {
			$this->get_combined_rating($listing->ID);
			$processed++;
		}
		
		// Check if there are more listings to process
		$remaining_args = $args;
		$remaining_args['posts_per_page'] = 1;
		$remaining_args['offset'] = $offset + $batch_size;
		$remaining_listings = get_posts($remaining_args);
		
		return array(
			'processed' => $processed,
			'remaining' => !empty($remaining_listings),
			'completed' => empty($remaining_listings),
			'next_offset' => $offset + $batch_size
		);
	}

	/**
	 * Recalculate combined rating when a new comment is posted
	 */
	public function recalculate_on_comment_post($comment_id, $comment_approved, $commentdata) {
		$comment = get_comment($comment_id);
		if ($comment && get_post_type($comment->comment_post_ID) === 'listing') {
			$this->recalculate_combined_rating($comment->comment_post_ID);
		}
	}

	/**
	 * Recalculate combined rating when comment status changes (approved/unapproved)
	 */
	public function recalculate_on_comment_status_change($comment_id, $status) {
		$comment = get_comment($comment_id);
		if ($comment && get_post_type($comment->comment_post_ID) === 'listing') {
			$this->recalculate_combined_rating($comment->comment_post_ID);
		}
	}

	/**
	 * Recalculate combined rating when a comment is edited
	 */
	public function recalculate_on_comment_edit($comment_id, $data) {
		$comment = get_comment($comment_id);
		if ($comment && get_post_type($comment->comment_post_ID) === 'listing') {
			$this->recalculate_combined_rating($comment->comment_post_ID);
		}
	}

	/**
	 * Recalculate combined rating when a comment is deleted
	 */
	public function recalculate_on_comment_delete($comment_id, $comment) {
		if ($comment && get_post_type($comment->comment_post_ID) === 'listing') {
			$this->recalculate_combined_rating($comment->comment_post_ID);
		}
	}

	/**
	 * Helper method to recalculate combined rating for a listing
	 */
	private function recalculate_combined_rating($listing_id) {
		// Clear existing combined rating to force fresh calculation
		delete_post_meta($listing_id, '_combined_rating');
		delete_post_meta($listing_id, '_combined_review_count');
		
		// Recalculate with current data (Google cached + fresh Listeo)
		$new_rating = $this->get_combined_rating($listing_id);
		
		// Log for debugging
		
		
		return $new_rating;
	}

}