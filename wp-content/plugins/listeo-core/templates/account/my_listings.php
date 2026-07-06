<?php
$ids = '';
if (isset($data)) :
	$ids	 	= (isset($data->ids->posts)) ? $data->ids->posts : '';
	$status	 	= (isset($data->status)) ? $data->status : '';
endif;


$message = $data->message;
$current_user = wp_get_current_user();
$roles = $current_user->roles;
$role = array_shift($roles);
if (!in_array($role, array('administrator', 'admin', 'owner', 'seller'))) :
	$template_loader = new Listeo_Core_Template_Loader;
	$template_loader->get_template_part('account/owner_only');
	return;
endif;

$max_num_pages = $data->ids->max_num_pages;
?>
<div class="row">


	<div class="col-lg-12 col-md-12">

		<?php if (empty($ids)) : ?>
			<?php if ($status == 'active') : ?>
				<div class="notification notice margin-bottom-20">
					<p><?php printf(__('You haven\'t submitted any listings yet, you can add your first one <a href="%s">below</a>', 'listeo_core'), get_permalink(get_option('listeo_submit_page'))); ?></p>
				</div>
				<a href="<?php echo get_permalink(get_option('listeo_submit_page')); ?>" class="margin-top-35 button"><?php esc_html_e('Submit New Listing', 'listeo_core'); ?></a>
			<?php else : ?>
				<div class="notification notice margin-bottom-20">
					<p><?php esc_html_e('You don\'t have any listings here', 'listeo_core');	 ?></p>
				</div>
			<?php endif; ?>
		<?php else : ?>

			<?php if (!empty($message)) {
				echo $message;
			} ?>

			<?php

			$search_value = isset($_GET['search']) ? $_GET['search'] : '';
			$category_value = isset($_GET['category']) ? $_GET['category'] : '';

			// Get all listing categories for the dropdown
			$categories = get_terms(array(
				'taxonomy' => 'listing_category',
				'hide_empty' => false,
			));
			?>
			<div class="dashboard-list-box margin-top-0">
				<form id="my-listings-search-form" action="">
					<input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
					<div class="dashboard-list-box-search-filters">
						<input type="text" name="search" id="my-listings-search" placeholder="<?php esc_html_e('Search listing', 'listeo_core');	 ?>" value="<?php echo esc_attr($search_value); ?>">
						<select name="category" id="my-listings-category" class="select2-single">
							<option value=""><?php esc_html_e('All Categories', 'listeo_core'); ?></option>
							<?php if (!empty($categories) && !is_wp_error($categories)) : ?>
								<?php foreach ($categories as $category) : ?>
									<option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($category_value, $category->term_id); ?>>
										<?php echo esc_html($category->name); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
						<button type="submit" class="button gray"><i class="fa fa-search"></i></button>
					</div>
				</form>
				<h4>
					<?php switch ($status) {
						case 'active':
							esc_html_e('Active Listings', 'listeo_core');
							break;
						case 'pending':
							esc_html_e('Pending Listings', 'listeo_core');
							break;
						case 'expired':
							esc_html_e('Expired Listings', 'listeo_core');
							break;
						case 'rejected':


							esc_html_e('Rejected Listings', 'listeo_core');
							break;


						default:
							esc_html_e('Active Listings', 'listeo_core');
							break;
					} ?>

				</h4>
				<ul>
					<?php
					// Prime meta and term caches in bulk to avoid N+1 queries
					update_meta_cache('post', $ids);
					update_object_term_cache($ids, 'listing');

					foreach ($ids as $listing_id) {
						$listing = get_post($listing_id);
					?>
						<li>
							<div class="list-box-listing">
								<div class="list-box-listing-img">
									<a href="<?php echo get_permalink($listing) ?>">
										<?php
										if (has_post_thumbnail($listing_id)) {
											echo get_the_post_thumbnail($listing_id, 'listeo_core-preview');
										} else {
											$gallery = (array) get_post_meta($listing_id, '_gallery', true);

											$ids = array_keys($gallery);
											if (!empty($ids[0]) && $ids[0] !== 0) {
												$image_url = wp_get_attachment_image_url($ids[0], 'listeo_core-preview');
											} else {
												$image_url = get_listeo_core_placeholder_image();
											}
										?>
											<img src="<?php echo esc_attr($image_url); ?>" alt="">
										<?php } ?>
									</a>
								</div>
								<div class="list-box-listing-content">

									<div class="inner">
										<h3 class="inner-title">
											<!-- <?php if ($listing->post_status == 'rejected') : ?>
												<span><i class="fa fa-exclamation-triangle"></i> <?php esc_html_e('This listing was rejected', 'listeo_core'); ?></span>
											<?php endif; ?> -->
											<?php echo get_the_title($listing); //echo listeo_core_get_post_status($listing_id)
											?>
										</h3>

										<?php
										// Display listing categories using existing tag styles
										$listing_categories = wp_get_post_terms($listing_id, 'listing_category');
										if (!empty($listing_categories) && !is_wp_error($listing_categories)) : ?>
											<div class="listing-category-tags">
												<?php foreach ($listing_categories as $cat) : ?>
													<span class="listing-category-tag-nl"><?php echo esc_html($cat->name); ?></span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>

										<?php if (get_the_listing_address($listing)) : ?>
											<span class="listing-address"><?php the_listing_address($listing); ?></span>
										<?php
										endif;

										/**
										 * Fires after the listing meta fields (views, expiration) in My Listings dashboard.
										 *
										 * @param WP_Post $listing The listing post object.
										 * @param int     $listing_id The listing post ID.
										 */
										do_action('listeo_core_my_listings_before_meta', $listing, $listing_id);

										$views = get_post_meta($listing_id, '_listing_views_count', true);
										if ($views) { ?>
											<span class="field"><?php esc_html_e('Views: ', 'listeo_core'); ?> <?php echo $views; ?></span>
										<?php } ?>

										<span class="expiration-date"><?php esc_html_e('Expiring: ', 'listeo_core'); ?> <?php echo listeo_core_get_expiration_date($listing_id); ?></span>



										<?php
										/**
										 * Fires after the listing meta fields (views, expiration) in My Listings dashboard.
										 *
										 * @param WP_Post $listing The listing post object.
										 * @param int     $listing_id The listing post ID.
										 */
										do_action( 'listeo_core_my_listings_after_meta', $listing, $listing_id );
										?>

										<?php
										$user_package = get_post_meta($listing_id, '_user_package_id', true);

										if ($user_package) {
											$package = listeo_core_get_package_by_id($user_package);

											if ($package && $package->product_id) { ?>
												<span class="package-type"><?php esc_html_e('Paid Package: ', 'listeo_core'); ?>
													<?php echo get_the_title($package->product_id); ?>
												</span>
											<?php };
											//return $package->get_title();
										}
										if ($listing->post_status == 'rejected') :
											$rejection_reason = get_post_meta($listing_id, '_listing_rejection_reason', true);
											if ($rejection_reason) : ?>


												<a href="#rejection-reason-dialog-<?php echo esc_attr($listing_id); ?>" class="popup-with-zoom-anim">
													<i class="fa fa-info-circle"></i> <?php esc_html_e('View Rejection Reason', 'listeo_core'); ?>
												</a>


										<?php endif;
										endif;
										?>


										<?php
										// Use the new combined rating display function
										$rating_data = listeo_get_rating_display($listing_id);
										$rating = $rating_data['rating'];
										$number = $rating_data['count'];

										if (isset($rating) && $rating > 0) :  $rating_type = get_option('listeo_rating_type', 'star');
											if ($rating_type == 'numerical') { ?>
												<div class="numerical-rating" data-rating="<?php $rating = str_replace(',', '.', $rating);
																							$rating_value = esc_attr(round($rating, 1));
																							printf("%0.1f", $rating_value); ?>">
												<?php } else { ?>
													<div class="star-rating" data-rating="<?php echo $rating; ?>">
													<?php } ?>
													<?php if ($number > 0) { ?>
														<div class="rating-counter">(<?php printf(_n('%s review', '%s reviews', $number, 'listeo_core'), number_format_i18n($number));  ?>)</div>
													<?php } ?>
													</div>
												<?php endif; ?>

												</div>
												<?php if (get_option('listeo_ical_page')) : ?>
													<div id="ical-export-dialog-<?php echo esc_attr($listing_id); ?>" class="listeo-dialog ical-export-dialog zoom-anim-dialog mfp-hide">

														<div class="small-dialog-header">
															<h3>
																<?php printf(__("iCal file for %s", 'listeo_core'), get_the_title($listing_id)); ?>
															</h3>
														</div>
														<!--Tabs -->
														<div class="sign-in-form style-1">


															<div><input type="text" class="listeo-export-ical-input" value="<?php echo listeo_ical_export_url($listing_id); ?>"></div>

														</div>
													</div>
													<div id="ical-import-dialog-<?php echo esc_attr($listing_id); ?>" class="listeo-dialog ical-import-dialog zoom-anim-dialog  mfp-hide">

														<div class="small-dialog-header">
															<h3><?php esc_html_e('iCal Import', 'listeo_core'); ?></h3>
														</div>
														<!--Tabs -->
														<div class="sign-in-form style-1">

															<div class="saved-icals">
																<?php echo listeo_get_saved_icals($listing_id); ?>
															</div>


															<h4><?php esc_html_e('Import New Calendar', 'listeo_core'); ?></h4>

															<form action="" data-listing-id="<?php echo esc_attr($listing_id); ?>" class="ical-import-form" id="ical-import-form-<?php echo esc_attr($listing_id); ?>">
																<p>
																	<input required placeholder="<?php esc_html_e('Name', 'listeo_core'); ?>" type="text" class="import_ical_name" name="import_ical_name">
																</p>
																<p>
																	<input required placeholder="<?php esc_html_e('URL to .ical, .ics, .ifb or .icalendar file', 'listeo_core'); ?>" type="text" class="import_ical_url" name="import_ical_url">
																</p>
																<div class="checkboxes in-row margin-bottom-20">
																	<input type="checkbox" name="import_ical_force_update" class="import_ical_force_update input-checkbox" id="import_ical_force_update">
																	<label for="import_ical_force_update"><?php esc_html_e('Force Update conflicting ical events', 'listeo_core'); ?></label>
																</div>
																<button class="button"><i class="fa fa-circle-o-notch fa-spin"></i><?php esc_html_e('Save', 'listeo_core'); ?></button>
															</form>
															<div class="notification notice margin-top-20" style="display: none">
																<p></p>
															</div>

														</div>
													</div>
												<?php endif; ?>
									</div>
								</div>
								<?php if ($listing->post_status == 'rejected') :
									$rejection_reason = get_post_meta($listing_id, '_listing_rejection_reason', true);
									if ($rejection_reason) : ?>
										<!-- Rejection Reason Modal -->
										<div id="rejection-reason-dialog-<?php echo esc_attr($listing_id); ?>" class="listeo-dialog zoom-anim-dialog mfp-hide">
											<div class="small-dialog-header">
												<h3>
													<i class="fa fa-exclamation-triangle"></i> <?php esc_html_e('Rejection Reason', 'listeo_core'); ?>
												</h3>
											</div>
											<div class="sign-in-form style-1">
												<div>
													<p style="font-size: 15px; line-height: 1.6; color: #333; margin-bottom: 15px;">
														<?php echo nl2br(esc_html($rejection_reason)); ?>
													</p>
													<div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 20px;">
														<strong style="display: block; margin-bottom: 8px; color: #555;">
															<?php esc_html_e('What to do next:', 'listeo_core'); ?>
														</strong>
														<ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #666;">
															<li><?php esc_html_e('Review the feedback above', 'listeo_core'); ?></li>
															<li><?php esc_html_e('Click "Edit" to make necessary changes', 'listeo_core'); ?></li>
															<li><?php esc_html_e('Resubmit your listing for review', 'listeo_core'); ?></li>
														</ul>
													</div>
												</div>
											</div>
										</div>
								<?php endif;
								endif; ?>
								<div class="buttons-to-right">
									<?php if (get_option('listeo_ical_page')) : ?>
										<div class="ical-dropdown-btn">
											<?php esc_html_e('iCal', 'listeo_core') ?>
											<ul>
												<li>
													<a href="#ical-export-dialog-<?php echo esc_attr($listing_id); ?>" class="button popup-with-zoom-anim"><?php esc_html_e('iCal Export', 'listeo_core') ?></a>
												</li>

												<li>
													<a href="#ical-import-dialog-<?php echo esc_attr($listing_id); ?>" class="button popup-with-zoom-anim"><?php esc_html_e('iCal Import', 'listeo_core') ?></a>
												</li>
											</ul>
										</div>
									<?php endif; ?>

									<?php
									$actions = array();

									switch ($listing->post_status) {
										case 'publish':
											$actions['edit'] = array('label' => __('Edit', 'listeo_core'), 'icon' => 'sl sl-icon-note', 'nonce' => false);
											if (!get_option('listeo_skip_package_if_user_has_one')) {
												if (get_option('listeo_new_listing_requires_purchase')) {
													$actions['renew'] = array(
														'label' => __('Change Package', 'listeo_core'),
														'icon' => 'sl sl-icon-wrench',
														'nonce' => false,
														'type' => 'package'
													);
												}
											}

											//$actions['unpublish'] = array( 'label' => __( 'Hide', 'listeo_core' ), 'icon' => 'sl sl-icon-ban', 'nonce' => true );
											break;

										case 'pending_payment':
										case 'preview':
											// For incomplete submissions, offer both continue and edit options
											$actions['continue'] = array('label' => __('Continue', 'listeo_core'), 'icon' => 'sl sl-icon-arrow-right', 'nonce' => false);
											$actions['edit'] = array('label' => __('Edit', 'listeo_core'), 'icon' => 'sl sl-icon-pencil', 'nonce' => false);
											break;

										case 'pending':
											$actions['edit'] = array('label' => __('Edit', 'listeo_core'), 'icon' => 'sl sl-icon-pencil', 'nonce' => false);
											break;

										case 'expired':

											$actions['renew'] = array('label' => __('Renew', 'listeo_core'), 'icon' => 'refresh', 'nonce' => true);

											break;

										case 'rejected':
											$actions['edit'] = array('label' => __('Edit & Resubmit', 'listeo_core'), 'icon' => 'sl sl-icon-note', 'nonce' => false);
											break;
									}


									$actions['delete'] = array('label' => __('Delete', 'listeo_core'), 'icon' => 'sl sl-icon-close', 'nonce' => true);

									$actions           = apply_filters('listeo_core_my_listings_actions', $actions, $listing);

									foreach ($actions as $action => $value) {

										if (isset($value['url'])) {
											$action_url = $value['url'];
										} elseif ($action == 'edit' || $action == 'renew' || $action == 'continue') {
											$action_url = add_query_arg(array('action' => $action,  'listing_id' => $listing->ID), get_permalink(get_option('listeo_submit_page')));
										} else {
											$action_url = add_query_arg(array('action' => $action,  'listing_id' => $listing->ID));
										}
										if (!get_option('listeo_new_listing_requires_purchase') && $action == 'renew') {
											$action_url = add_query_arg(array('action' => $action,  'listing_id' => $listing->ID));
										}
										if ($value['nonce']) {
											$action_url = wp_nonce_url($action_url, 'listeo_core_my_listings_actions');
										}
										if (isset($value['type']) && $value['type'] == 'package') {
											$action_url = add_query_arg('package_action', 'change_package', $action_url);
										}

										echo '<a  href="' . esc_url($action_url) . '" class="button gray ' . esc_attr($action) . ' listeo_core-dashboard-action-' . esc_attr($action) . '">';

										if (isset($value['icon']) && !empty($value['icon'])) {
											echo '<i class="' . $value['icon'] . '"></i> ';
										}

										echo esc_html($value['label']) . '</a>';
									}

									?>

								</div>
						</li>

					<?php } ?>
				</ul>
			</div>
			<?php

			$paged = (isset($_GET['listings_paged'])) ? $_GET['listings_paged'] : 1;

			?>
			<div class="clearfix"></div>
			<div class="pagination-container margin-top-30 margin-bottom-0">
				<nav class="pagination">
					<?php
					$big = 999999999;
					echo paginate_links(array(
						'base'      => add_query_arg('listings_paged', '%#%'),
						'format' 	=> '?listings_paged=%#%',
						'current' 	=> max(1, $paged),
						'total' 	=> $max_num_pages,
						'type' 		=> 'list',
						'prev_next'    => true,
						'prev_text'    => '<i class="sl sl-icon-arrow-left"></i>',
						'next_text'    => '<i class="sl sl-icon-arrow-right"></i>',
						'add_args'        => false,
						'add_fragment'    => ''

					)); ?>
				</nav>
			</div>

			<?php if (get_option('listeo_submit_page')) { ?>
				<a href="<?php echo get_permalink(get_option('listeo_submit_page')); ?>" class="margin-top-35 button"><?php esc_html_e('Submit New Listing', 'listeo_core'); ?></a>
			<?php } ?>

		<?php endif; ?>

	</div>
</div>