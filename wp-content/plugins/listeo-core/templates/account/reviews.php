<?php 
$ids = '';
if(isset($data)) :
	$ids	 	= (isset($data->ids)) ? $data->ids : '' ;
endif; 
$no_bookmarks = false;
$message = $data->message;
$current_user = wp_get_current_user();	
$roles = $current_user->roles;
$role = array_shift( $roles );
$limit = 2;
?> 
<div class="row">
<!-- Listings -->
	<?php if(!empty($message )) { echo $message; } ?>

<?php if(in_array($role,array('administrator','admin','owner', 'seller'))) : ?>
<div class="col-lg-12 col-md-12">

	<div class="dashboard-list-box reviews-visitior-box margin-top-0 <?php if(in_array($role,array('administrator','admin'))) : ?> margin-bottom-30<?php endif; ?>">

		<!-- Sort by -->
		<div class="sort-by">
			<div class="sort-by-select">
				<?php 
					$post_ids = get_posts(array(
    					'author'        =>  $current_user->ID, // I could also use $user_ID, right?
    					'orderby'       =>  'post_date',
    					'order'         =>  'ASC',
    					'numberposts'   => -1, // get all posts.
    					'post_type' 	=> 'listing',
    					'fields'        => 'ids', // Only get post IDs
    				)); ?>
				<select id="sort-reviews-by" data-placeholder="Default order" class="select2-single">
						<option><?php esc_html_e('All Listings', 'listeo_core'); ?></option>	
						<?php foreach ($post_ids as  $id) { ?>
							<option value="<?php echo esc_attr($id); ?>"><?php echo get_the_title($id) ?></option>
						<?php } ?>
				</select>
			</div>
		</div>

		<h4><?php esc_html_e('Visitor Reviews','listeo_core') ?></h4> 

		<!-- Reply to review popup -->
		<div id="small-dialog" class="zoom-anim-dialog mfp-hide">
			<div class="small-dialog-header">
				<h3><?php esc_html_e('Reply to review','listeo_core') ?></h3>
			</div>
			<form action="" id="send-comment-reply">
				<div class="message-reply margin-top-0">
					<input type="hidden" id="reply-review-id" name="review_id" >
					<input type="hidden" id="reply-post-id" name="post_id" >
					<textarea id="comment_reply" required name="comment_reply" cols="40" rows="3"></textarea>
					<button id="send-comment-reply" class="button"><i class="fa fa-circle-o-notch fa-spin"></i><?php esc_html_e('Reply','listeo_core') ?></button>
				</div>
			</form>
			
		</div>

		<!-- Edit reply to review popup -->
		<div id="small-dialog-edit" class="zoom-anim-dialog mfp-hide">
			<div class="small-dialog-header">
				<h3><?php esc_html_e('Edit your reply','listeo_core') ?></h3>
			</div>
			<form action="" id="send-comment-edit-reply">
				<div class="message-reply margin-top-0">
					
					<input type="hidden" id="reply_id" name="reply_id" >
					<textarea id="comment_reply" required name="comment_reply" cols="40" rows="3"></textarea>
					<button id="send-comment-edit-reply" class="button"><i class="fa fa-circle-o-notch fa-spin"></i><?php esc_html_e('Save changes','listeo_core') ?></button>
				</div>
			</form>
			
		</div>

		<?php 


		
	    $visitor_reviews_page = (isset($_GET['visitor-reviews-page'])) ? $_GET['visitor-reviews-page'] : 1;
		add_filter( 'comments_clauses', 'listeo_top_comments_only' );
		$visitor_reviews_offset = ($visitor_reviews_page * $limit) - $limit;
		$total_visitor_reviews = get_comments(
				array(
					'orderby' 	=> 'post_date' ,
            		'order' 	=> 'DESC',
           			'status' 	=> 'approve',
            		'post_author' => $current_user->ID,
					'parent'    => 0,
					'post_type' => 'listing',
            	)
			);
	  
		$visitor_reviews_args = array(

			'post_author' 	=> $current_user->ID,
			'parent'      	=> 0,
			'status' 	=> 'approve',
			'post_type' 	=> 'listing',
			'number' 		=> $limit,
			'offset' 		=> $visitor_reviews_offset,
		);
		$visitor_reviews_pages = ceil(count($total_visitor_reviews)/$limit);
		
		$visitor_reviews = get_comments( $visitor_reviews_args ); 
		remove_filter( 'comments_clauses', 'listeo_top_comments_only' );

		if(empty($visitor_reviews)) : ?>
		<ul><li><p style="margin-bottom: 0px;"><?php esc_html_e('You don\'t have any reviews','listeo_core') ?></p></li></ul>
		<?php else : ?>
		
		<ul id="reviews_list_visitors" data-page="1">
			<?php 
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
		</ul>
		
		<?php endif; ?>
	</div>


	<!-- Pagination -->
	<?php if($visitor_reviews_pages>1) { ?>
	<div class="clearfix"></div>
	<div id="visitor-reviews-pagination" class="pagination-container margin-top-30 margin-bottom-30">
		<nav class="pagination">
			<?php 
				 echo listeo_core_ajax_pagination( $visitor_reviews_pages, $visitor_reviews_page );?>
		</nav>
	</div>
	<?php } ?>
	<!-- Pagination / End -->

</div>
<?php endif;?>
<?php if(in_array($role,array('administrator','admin','guest','subscriber'))) : ?>
<!-- Listings -->
<div class="col-lg-12 col-md-12">
	<div class="dashboard-list-box your-reviews-box margin-top-0">
		<h4><?php esc_html_e('Your Reviews','listeo_core'); ?></h4>
		<!-- Edit reply to review popup -->
		<div id="small-dialog-edit-review" class="zoom-anim-dialog mfp-hide">
			<div class="small-dialog-header">
				<h3><?php esc_html_e('Edit your review','listeo_core') ?></h3>
			</div>
			<form action="" id="send-comment-edit-review">
				<div class="message-reply margin-top-0">
					
					<input type="hidden" id="reply_id" name="reply_id" >
						<?php $criteria_fields = listeo_get_reviews_criteria(); ?>
						<!-- Subratings Container -->
						<div class="sub-ratings-container">
							
						</div>
						<!-- Leave Rating -->
						
						<div class="clearfix"></div>
					
					<textarea id="comment_reply" required name="comment_reply" cols="40" rows="3"></textarea>
					<button id="send-comment-edit-review" class="button"><i class="fa fa-circle-o-notch fa-spin"></i><?php esc_html_e('Save changes','listeo_core') ?></button>
				</div>
			</form>
			
		</div>
		<?php 

		$your_reviews_page = (isset($_GET['your-reviews-page'])) ? $_GET['your-reviews-page'] : 1;
		$your_reviews_offset = ($your_reviews_page * $limit) - $limit;
		$total_your_reviews = get_comments(
				array(
					'orderby' 	=> 'post_date' ,
            		'order' 	=> 'DESC',
           			'status' 	=> 'all',
            		'author__in' => array($current_user->ID),
					'post_type' => 'listing',
					'parent'      => 0,
            	)
			);
		$your_reviews_args = array(
			'author__in' 	=> array($current_user->ID),
			'post_type' 	=> 'listing',
			'status' 		=> 'all',
			'parent'      	=> 0,
			'number' 		=> $limit,
		 	'offset' 		=> $your_reviews_offset,
			
		);
		$your_reviews_pages = ceil(count($total_your_reviews)/$limit);
		$your_reviews = get_comments( $your_reviews_args ); 
		if(empty($your_reviews)) : ?>
		<ul><li><p  style="margin-bottom: 0px;"><?php esc_html_e('You haven\'t reviewed anything','listeo_core') ?></p></li></ul>
		<?php else : ?>
		<ul data-page="1">
			<?php 
			foreach($your_reviews as $review) : 
				?>
				<li>
					<div class="comments listing-reviews">
						<ul>
							<li>
								<div class="avatar"><?php echo get_avatar( $review, 70 ); ?></div>
								<div class="comment-content"><div class="arrow-comment"></div>
									<div class="comment-by"><?php esc_html_e('Your review','listeo_core'); ?> 
									<div class="comment-by-listing"><?php esc_html_e('on','listeo_core'); ?> 
										<a href="<?php echo esc_url(get_permalink($review->comment_post_ID)); ?>"><?php echo get_the_title(
										$review->comment_post_ID) ?></a>
											<?php if(wp_get_comment_status($review->comment_ID) == 'unapproved'){?>
											<?php esc_html_e(' is waiting for approval','listeo_core') ?>
										<?php } ?>
									</div> 
										<span class="date"><?php echo date_i18n(  get_option( 'date_format' ),  strtotime($review->comment_date), false ); ?></span>
										<?php $star_rating = get_comment_meta( $review->comment_ID, 'listeo-rating', true );
										if($star_rating) : ?>
										<div class="star-rating" data-rating="<?php echo get_comment_meta( $review->comment_ID, 'listeo-rating', true ); ?>"></div>
										<?php endif; ?>
									</div>
									<?php echo wpautop( $review->comment_content ); ?>
									
									<?php 
						            $photos = get_comment_meta( $review->comment_post_ID, 'listeo-attachment-id', false );

						            if($photos) : ?>
						            <div class="review-images mfp-gallery-container">
						            	<?php foreach ($photos as $key => $attachment_id) {
						            		$image = wp_get_attachment_image_src( $attachment_id, 'listeo-gallery' );
						            		$image_thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
						            	 ?>
										<a href="<?php echo esc_attr($image[0]); ?>" class="mfp-gallery"><img src="<?php echo esc_attr($image_thumb[0]); ?>" alt=""></a>
										<?php } ?>
									</div>
									<?php endif; ?>
									<?php
								// Get criteria from review snapshot (shows original criteria)
								$criteria_fields = listeo_get_review_criteria($review->comment_ID);
									
									?>
									<a href="#small-dialog-edit-review" class="edit-reply edit-review rate-review  popup-with-zoom-anim" 
										data-comment-id="<?php echo $review->comment_ID; ?>"
										data-comment-content="<?php echo $review->comment_content; ?>"
										data-comment-rating="<?php echo get_comment_meta( $review->comment_ID, 'listeo-rating', true );?>"
										<?php foreach ($criteria_fields as $key => $value) {
											$this_rating = get_comment_meta( $review->comment_ID, $key, true );
											echo ' data-comment-'.$key.'="'.$this_rating.'"';
										}
										?>"
										
										><i class="sl sl-icon-pencil"></i> <?php esc_html_e('Edit your review','listeo_core') ?></a>
										<?php 
										$action_url = add_query_arg( array( 'action' => 'delete-comment',  'comment_id' => $review->comment_ID ) ); 
										$action_url = wp_nonce_url( $action_url, 'listeo_core_reviews_actions' );?>
										<a class="button color  listeo_core-dashboard-delete-review" href="<?php echo esc_url($action_url); ?>"><i class="sl sl-icon-close"></i><?php esc_html_e('Delete your review','listeo_core') ?></a>
									
								</div>
							</li>
						</ul>
					</div>
				</li>
				<!-- //echo($review->comment_author . '<br />' . $review->comment_content); -->
				
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
	<?php if($your_reviews_pages>1): ?>
	<!-- Pagination -->
	<div class="clearfix"></div>
	<div id="your-reviews-pagination" class="pagination-container margin-top-30 margin-bottom-0">
		<nav class="pagination">
			<?php 
				
				echo paginate_links( array(
					'base'         	=> @add_query_arg('your-reviews-page','%#%'),
					'format'       	=> '?your-reviews-page=%#%',
					'current' 		=> $your_reviews_page,
					'total' 		=> $your_reviews_pages,
					'type' 			=> 'list',
					'prev_next'    	=> true,
			        'prev_text'    	=> '<i class="sl sl-icon-arrow-left"></i>',
			        'next_text'    	=> '<i class="sl sl-icon-arrow-right"></i>',
			         'add_args'     => false,
   					 'add_fragment' => ''
				    
				) );?>
		</nav>
	</div>
	<!-- Pagination / End -->
	<?php endif; ?>
</div>
<?php endif; ?>
</div>