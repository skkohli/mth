<?php

global $post;
global $wpdb;


// Get current page number for pagination
$page = get_query_var('cpage') ? get_query_var('cpage') : 1;

// Reviews per page setting (filterable for customization)
$reviews_per_page = apply_filters('listeo_reviews_per_page', 10);

// Get all approved comments for this listing
$comments = get_comments(array(
	'post_id' => $post->ID,
	'status' => 'approve'
));


// Get total count
$total_reviews = count($comments);
do_action('listeo_before_comments');

// Always output the anchor ID so links have a target
// This ensures #listing-reviews always exists in the DOM
if ($comments) : ?>
	<div id="listing-reviews" class="listing-section">
		<h2 class="listing-desc-headline margin-top-75 margin-bottom-20"><?php
																			printf( // WPCS: XSS OK.
																				esc_html(_nx('Review %1$s', 'Reviews %1$s', listeo_get_reviews_number(), 'comments title', 'listeo_core')),
																				'<span class="reviews-amount">(' . number_format_i18n(listeo_get_reviews_number()) . ')</span>'
																			);
																			?></h2>

		<!-- Rating Overview -->
		<?php 
		$rating_data = listeo_get_rating_display($post->ID);
		$rating_value = $rating_data['rating'];
		if ($rating_value) { ?>

			<div class="rating-overview">
				<div class="rating-overview-box">
					<span class="rating-overview-box-total"><?php esc_attr(round($rating_value, 1));
															printf("%0.1f", $rating_value);  ?></span>
					<span class="rating-overview-box-percent"><?php esc_html_e('out of 5.0', 'listeo_core'); ?></span>
					<div class="star-rating" data-rating="<?php echo esc_attr(round($rating_value, 2)); ?>"></div>
				</div>

				<div class="rating-bars">
					<?php
					// Get criteria with context (per-type/per-taxonomy aware)
					$context = array('listing_id' => $post->ID);
					$criteria_fields = listeo_get_reviews_criteria($context);
					?>
					<?php foreach ($criteria_fields as $key => $value) {
						$rating = get_post_meta($post->ID, $key . '-avg', true);
						if ($rating) { ?>
							<div class="rating-bars-item">
								<span class="rating-bars-name"><?php echo stripslashes(esc_html($value['label'])) ?>
									<?php if (isset($value['tooltip']) && !empty($value['tooltip'])) : ?><i class="tip" data-tip-content="<?php echo stripslashes(esc_html($value['tooltip'])); ?>"></i> <?php endif; ?></span>
								<span class="rating-bars-inner">
									<span class="rating-bars-rating" data-rating="<?php echo esc_attr($rating); ?>">
										<span class="rating-bars-rating-inner"></span>
									</span>
									<strong><?php esc_attr(round($rating, 1));
											printf("%0.1f", $rating);  ?></strong>
								</span>
							</div>
					<?php }
					} ?>

				</div>
			</div>
			<!-- Rating Overview / End -->
		<?php } ?>

		<div class="clearfix"></div>

		<!-- Reviews -->
		<section id="comments" class="comments listing-reviews">

			<ul class="comment-list">
				<?php
				// Use WordPress built-in pagination with per_page and page parameters
				wp_list_comments(array(
					'style'      	=> 'ul',
					'short_ping' 	=> true,
					'callback' 		=> 'listeo_comment_review',
					'per_page'      => $reviews_per_page,
					'page'          => $page,
				), $comments);
				?>
			</ul><!-- .comment-list -->
		</section>

		<!-- Pagination -->
		<div class="clearfix"></div>
		<?php


		?>
		<?php
		// Calculate max pages
		$max_page = ceil($total_reviews / $reviews_per_page);

		if ($max_page > 1) : // Are there comments to navigate through?
		?>

			<div class="row">
				<div class="col-md-12">
					<!-- Pagination -->
					<div class="pagination-container margin-top-30">
						<ul class="pagination">

							<?php
							global $wp_rewrite;

							$defaults = array(
								//'base'         => add_query_arg('cpage', '%#%'),
								'format'       => '',
								'total'        => $max_page,
								'current'      => $page,
								'echo'         => true,
								'type'         => 'list',
								'prev_text'         => '«',
								'next_text'         => '»',
								'add_fragment' => '#listing-reviews',
							);
							if ($wp_rewrite->using_permalinks()) {
								$defaults['base'] = user_trailingslashit(trailingslashit(get_permalink()) . $wp_rewrite->comments_pagination_base . '-%#%', 'commentpaged');
							} else {
								$defaults['base'] = add_query_arg('cpage', '%#%');
							}

							$page_links = paginate_links($defaults);
							echo $page_links;
							?>

						</ul>
					</div>
				</div>
			</div>
			<div class="clearfix"></div>
			<!-- Pagination / End -->
		<?php endif; // Check for comment navigation.
		?>
	</div>

	<?php if ($max_page > 1) : // Add smooth scroll only if there's pagination ?>
	<script>
	jQuery(document).ready(function($) {
		// Check if we're coming from a pagination link
		if(window.location.hash === '#listing-reviews') {
			// Smooth scroll to reviews section
			setTimeout(function() {
				$('html, body').animate({
					scrollTop: $("#listing-reviews").offset().top - 100
				}, 500);
			}, 100);
		}
	});
	</script>
	<?php endif; ?>

<?php else :
	// No Listeo reviews exist - output a placeholder anchor so #listing-reviews links still work
	// This handles the case when only Google reviews exist or when the review form should be shown
	?>
	<div id="listing-reviews" class="listing-section listing-reviews-placeholder"></div>
	<script>
	jQuery(document).ready(function($) {
		// If page loads with #listing-reviews hash but no actual reviews section,
		// redirect to Google reviews section if it exists
		if (window.location.hash === '#listing-reviews') {
			var $googleReviews = $('#listing-google-reviews');
			var $addReview = $('#add-review');

			if ($googleReviews.length) {
				// Scroll to Google reviews section
				setTimeout(function() {
					$('html, body').animate({
						scrollTop: $googleReviews.offset().top - 100
					}, 500);
				}, 100);
			} else if ($addReview.length) {
				// Scroll to add review form if no Google reviews
				setTimeout(function() {
					$('html, body').animate({
						scrollTop: $addReview.offset().top - 100
					}, 500);
				}, 100);
			}
		}
	});
	</script>
<?php endif; // Check for have_comments().


// If comments are closed and there are comments, let's leave a little note, shall we?
if (!comments_open()) : ?>
	<p class="no-comments"><?php esc_html_e('Reviews are closed.', 'listeo_core'); ?></p>
	<?php
else :
	$owners_can_review = get_option('listeo_owners_can_review');
	if ($owners_can_review) {
		$show_form = true;
		if ((int) $post->post_author == get_current_user_id()) {
		//	$show_form = false;
		}
	} else {
		$show_form = true;
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			$role = (array) $user->roles;

			if (in_array('owner', $role)) {
				$show_form = false; ?>
				<div class="notification notice margin-bottom-50 margin-top-50">
					<p><?php esc_html_e("You can't review listings as an owner", 'listeo_core'); ?></p>
				</div>
			<?php }
		}
		if ((int) $post->post_author == get_current_user_id()) {
			$show_form = false;    ?>
			<div class="margin-top-50"></div>
		<?php }
	}

	// Get the comments for the logged in user.
	$usercomment = false;
	if (is_user_logged_in()) {
		$usercomment = get_comments(array(
			'user_id' => get_current_user_id(),
			'post_id' => $post->ID,
		));
	}
	if (get_option('listeo_reviews_only_booked')) {

		$table_name = $wpdb->prefix . 'bookings_calendar';
		$has_booked = $wpdb->get_results($wpdb->prepare("
	            SELECT * FROM {$table_name}
	            WHERE bookings_author = %d
	            AND listing_id = %d
		
	            
		", get_current_user_id(), $post->ID));

		// AND status = 'paid'
		if (!is_user_logged_in()) {
			$show_form = false;
		?>
			<div id="add-review" class="notification notice margin-bottom-50 margin-top-50">
				<p><?php esc_html_e("Only guests who have booked can leave a review.", 'listeo_core'); ?></p>
			</div>
			<?php
		} else {
			if (!empty($has_booked)) {
				$show_form = true;
			} else {
				$show_form = false;
			?>
				<div id="add-review" class="notification notice margin-bottom-50 margin-top-50">
					<p><?php esc_html_e("Only guests who have booked can leave a review.", 'listeo_core'); ?></p>
				</div>
			<?php
			}
		}
	}

	// Check for anonymous user pending review (from URL parameters)
	if (!is_user_logged_in() && isset($_GET['unapproved']) && isset($_GET['moderation-hash'])) {
		$show_form = false; ?>
		<div class="notification notice margin-bottom-50 margin-top-50">
			<p><?php esc_html_e("Thank you for your review! It is now pending approval and will be visible once approved.", 'listeo_core'); ?></p>
		</div>
	<?php }

	if ($usercomment) {

		$show_form = false;
		//check if has pending
		$usercomment_pending = get_comments(array(
			'user_id' => get_current_user_id(),
			'post_id' => $post->ID,
			'status'  => 'hold'
		));

		if ($usercomment_pending) { ?>
			<div class="notification notice margin-bottom-50 margin-top-50">
				<p><?php esc_html_e("You've already reviewed this listing, your review is waiting for approval.", 'listeo_core'); ?></p>
			</div>

		<?php } else { ?>
			<div class="notification notice margin-bottom-50 margin-top-50">
				<p><?php esc_html_e("Thank you for your review.", 'listeo_core'); ?></p>
			</div>
		<?php }
	}





	//check if user has bought

	//get_current_user_id(),

	if ($show_form) { ?>
		<div id="add-review" class="add-review-box">

			<!-- Add Review -->
			<h2 class="listing-desc-headline margin-bottom-20"><?php esc_html_e('Add Review', 'listeo_core') ?></h2>

			<?php if (isset($_GET['captcha']) && $_GET['captcha'] == 'failed') :  ?>
				<div class="notification error margin-top-10 margin-bottom-30">
					<p><?php esc_html_e("Please check reCAPTCHA checbox to post your review", 'listeo_core'); ?></p>
				</div>
			<?php endif; ?>
			<?php comment_form(); ?>
		</div>
	<?php } ?>
	<!-- Add Review Box / End -->

<?php endif; ?>