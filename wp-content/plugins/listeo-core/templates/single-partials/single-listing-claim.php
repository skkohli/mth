<?php

$disable_claims = get_option('listeo_disable_claims', false);


if ($disable_claims) {
	return;
}
global $post;
$listing_type = get_post_meta(get_the_ID(), '_listing_type', true);
$post_id = get_the_ID();

if ($listing_type !== 'classifieds') { ?>


	<?php if (listeo_core_is_verified($post->ID)) : ?>
		<!-- Verified Badge -->
		<div class="verified-badge with-tip" data-tip-content="<?php esc_html_e('Listing has been verified and belongs to the business owner or manager.', 'listeo_core'); ?>">
			<i class="sl sl-icon-check"></i> <?php esc_html_e('Verified Listing', 'listeo_core') ?>
		</div>
	<?php else :  ?>

		<div class="claim-badge with-tip" data-tip-content="<?php esc_html_e('Click to claim this listing.', 'listeo_core'); ?>">

			<?php if (is_user_logged_in()) {
				// check if user has already have a claim request for this listing
				$claim_requests = get_posts(array(
					'post_type' => 'claim',
					'post_status' => 'publish',
					'author' => get_current_user_id(),
					'meta_query' => array(
						array(
							'key' => 'listing_id',
							'value' => $post_id,
							'compare' => '='
						)
					)
				));
				if ($claim_requests) {
					$claim_request = $claim_requests[0];
					$claim_status = get_post_meta($claim_request->ID, 'status', true);

					switch ($claim_status) {
						case 'approved':
							$button_text = esc_html__('Your claim request was approved', 'listeo_core');
							break;
						case 'pending':
							$button_text = esc_html__('Claim request pending', 'listeo_core');
							break;
						case 'rejected':
							$button_text = esc_html__('Your claim request was rejected', 'listeo_core');
							break;

						default:
							$button_text = esc_html__('Your claim request was approved', 'listeo_core');
							break;
					}
			?>
					<a href="#" class="claim-listing-button "><i class="sl sl-icon-question"></i> <span><?php echo $button_text; ?></span></a>
				<?php
				} else {
				?>
					<a href="#claim-dialog" class="claim-listing-button popup-with-zoom-anim"><i class="sl sl-icon-question"></i> <span><?php esc_html_e('Not verified. Claim this listing!', 'listeo_core') ?></span></a>
					<?php }
			} else {
				$enabled_registration = get_option('listeo_enable_registration_claims');
				// check if registration is ajax or redirect
				if (get_option('listeo_popup_login') == 'ajax') {
					if ($enabled_registration) { ?>
						<a href="#claim-dialog" class="claim-listing-button popup-with-zoom-anim"><i class="sl sl-icon-question"></i> <span><?php esc_html_e('Not verified. Claim this listing!', 'listeo_core') ?></span></a>
					<?php } else { ?>
						<a href="#sign-in-dialog" class="sign-in popup-with-zoom-anim"><i class="sl sl-icon-question"></i> <?php esc_html_e('Not verified. Claim this listing!', 'listeo_core') ?></a>

					<?php }
				} else {
					$login_page = get_option('listeo_profile_page');
					if ($enabled_registration) { ?>
						<a href="#claim-dialog" class="claim-listing-button popup-with-zoom-anim"><i class="sl sl-icon-question"></i> <span><?php esc_html_e('Not verified. Claim this listing!', 'listeo_core') ?></span></a>
					<?php } else { ?>
						<a href="<?php echo esc_url(get_permalink($login_page)); ?>" class="sign-in"><i class="sl sl-icon-question"></i> <?php esc_html_e('Not verified. Claim this listing!', 'listeo_core') ?></a>
			<?php }
				}
			} ?>

		</div>
	<?php endif; ?>
	<?php


	if (!listeo_core_is_verified($post->ID)) {
		$current_user = wp_get_current_user();

	?>
		<!-- Reply to review popup -->
		<div id="claim-dialog" class="zoom-anim-dialog mfp-hide">
			<div class="small-dialog-header">
				<h3><?php esc_html_e('Claim listing', 'listeo_core'); ?></h3>
			</div>
			<div class="message-reply message-claim-listing-form-popup margin-top-0">
				<form action="" enctype="multipart/form-data" id="claim-listing-form" data-listingid="<?php echo esc_attr($post_id); ?>">

					<input type="hidden" name="action" value="listeo_send_claim_request">
					<input type="hidden" name="listing_id" value="<?php echo esc_attr($post_id); ?>">
					<?php wp_nonce_field('listeo_send_claim', 'nonce'); ?>
					<div class="claim-info">
						<h4><?php esc_html_e('Take control of your listing!', 'listeo_core'); ?></h4>
						<?php esc_html_e('Customize your listing details, reply to reviews, upload photos and more to show customers what makes your business special.', 'listeo_core'); ?>
					</div>

					<?php if (!is_user_logged_in()) { ?>
						<div class="woocommerce-info margin-bottom-30">
							<?php _e('Your account will be created automatically based on data you provide below. If you already have an account, please', 'listeo_core'); ?>

							<?php
							$popup_login = get_option('listeo_popup_login', 'ajax');

							if ($popup_login == 'ajax') { ?>

								<a href="#sign-in-dialog" class="popup-with-zoom-anim">
									<?php esc_html_e('login', 'listeo_core') ?></span>.
								</a>

							<?php } else {

								$login_page = get_option('listeo_profile_page'); ?>
								<a href="<?php echo esc_url(get_permalink($login_page)); ?>"><?php esc_html_e('login', 'listeo_core') ?></span>.
								</a>
							<?php } ?>
						</div>
					<?php } ?>

					<?php if (get_option('listeo_enable_paid_claims')) {

						$exclude_packages = get_option('listeo_exclude_from_claim', array());
						if (!$exclude_packages) {
							$exclude_packages = array();
						}
					?>
						<h4><?php esc_html_e('Select Plan', 'listeo_core'); ?></h4>
						<div id="package-selection">
							<div class="owned-packages">
								<?php
								$packages = get_posts(array(
									'post_type'        => 'product',
									'posts_per_page'   => -1,
									'order'            => 'asc',
									'orderby'          => 'date',
									'suppress_filters' => false,
									'tax_query'        => array(
										'relation' => 'AND',
										array(
											'taxonomy' => 'product_type',
											'field'    => 'slug',
											'terms'    => array('listing_package', 'listing_package_subscription'),
											'operator' => 'IN',
										),
									),
									//'meta_query'       => WC()->query->get_meta_query(),
								));

								// Filter packages by the listing type being claimed
								if (!empty($listing_type) && !empty($packages)) {
									$packages = array_filter($packages, function ($p) use ($listing_type) {
										$allowed = get_post_meta($p->ID, '_allowed_listing_types', true);
										if (empty($allowed)) {
											return true; // no restrictions = allowed for all types
										}
										if (!is_array($allowed)) {
											$allowed = array($allowed);
										}
										return in_array($listing_type, $allowed);
									});
								}

								$i = 0;
								foreach ($packages as $key => $package) :
									$i++;
									$product = wc_get_product($package);
									if (in_array($product->get_id(), $exclude_packages)) {
										continue;
									}
									if (!$product->is_type(array('listing_package', 'listing_package_subscription')) || !$product->is_purchasable()) {
										continue;
									}
								?>
									<label for="user-package-<?php echo $product->get_id(); ?>">
										<input type="radio" <?php if ($i == 1) {
																echo "checked";
															} ?> name="package" value="<?php echo $product->get_id(); ?>" id="user-package-<?php echo $product->get_id(); ?>" />
										<span class="package-checkbox">
											<i class="fa fa-check"></i>
											<div class="owned-package-name claim-package-name">
												<strong><?php echo $product->get_title(); ?></strong>
												<div class="owned-package-desc">
													<?php
													// get product price and currency without html formatting
													echo $product->get_price_html();

													?>
												</div>
												<div class="claim-package-details">
													<ul>
														<li>
															<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																	<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																	<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																</g>
															</svg>

															<?php
															$listingslimit = $product->get_limit();
															if (!$listingslimit) {

																esc_html_e('Unlimited number of listings', 'listeo_core');
															} else { ?>

																<?php esc_html_e('This plan includes ', 'listeo_core');
																printf(_n('%d listing', '%s listings', $listingslimit, 'listeo_core') . ' ', $listingslimit); ?>

															<?php } ?>
														</li>
														<li>
															<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																	<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																	<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																</g>
															</svg>

															<?php $duration = $product->get_duration();
															if ($duration > 0) :
																esc_html_e('Listings are visible ', 'listeo_core');
																printf(_n('for %s day', 'for %s days', $product->get_duration(), 'listeo_core'), $product->get_duration());
															else :
																esc_html_e('Unlimited availability of listings', 'listeo_core');
															endif; ?>
														</li>
													</ul>
													<ul>


														<?php if (get_option('listeo_populate_listing_package_options')) : ?>
															<?php
															$bookingOptions = $product->has_listing_booking();
															if ($bookingOptions) : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Booking Module enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>


															<?php
															$reviewsOptions = $product->has_listing_reviews();
															if ($reviewsOptions) : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Reviews Module enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>

															<?php
															$sociallinksOptions = $product->has_listing_social_links();
															if ($sociallinksOptions) : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Social Links Module enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>

															<?php
															$openinghoursOptions = $product->has_listing_opening_hours();
															if ($openinghoursOptions) : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Opening Hours Module enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>

															<?php
															$vidosOptions = $product->has_listing_video();
															if ($vidosOptions) : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Video option enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>

															<?php
															$couponsOptions = $product->has_listing_coupons();
															if ($couponsOptions == 'yes') : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Coupons option enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>
															<?php
															$faqOptions = $product->has_listing_faq();
															if ($faqOptions == 'yes') : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('FAQ option enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>
															<?php
															$pricingMenuOptions = $product->has_listing_pricing_menu();
															if ($pricingMenuOptions == 'yes') : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Pricing Menu Module enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>
															<?php
															$galleryOptions = $product->has_listing_gallery();
															if ($galleryOptions == 'yes') : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php esc_html_e('Gallery Module enabled', 'listeo_core');  ?>
																</li>
															<?php endif; ?>
															<?php
															$gallery_limitOptions = $product->get_option_gallery_limit();
															if ($gallery_limitOptions) : ?>
																<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																		<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																			<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																			<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																		</g>
																	</svg>
																	<?php printf(esc_html__('Maximum  %s images in gallery', 'listeo_core'), $product->get_option_gallery_limit());  ?>
																</li>
															<?php endif; ?>
														<?php endif; ?>
														<?php
														$custom_listing_fields = get_post_meta($product->get_id(), 'package_items_group', true);
														if ($custom_listing_fields) {


															foreach ($custom_listing_fields as $key => $entry) {
																$title = esc_html($entry['title']);

																if (!empty($title)) { ?>
																	<li class="custom_listing_field"><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
																			<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
																				<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
																				<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
																			</g>
																		</svg>
																		<?php echo esc_html($title); ?>
																	</li>

														<?php }
															}
														}
														?>
													</ul>

												</div>
											</div>
										</span>
									</label>

								<?php endforeach; ?>
							</div>
						</div>
					<?php } ?>
					<h4 class="margin-bottom-15"><?php esc_html_e('Fill the form', 'listeo_core'); ?></h4>
					<div class="row">
						<?php if (!is_user_logged_in()) { ?>
							<input type="hidden" name="user_role" value="owner" checked />
							<?php if (!get_option('listeo_registration_hide_username')) : ?>
								<div class="col-md-6">

									<input placeholder="<?php esc_html_e('Username', 'listeo_core'); ?>" type="text" class="required input-text" name="username" id="username2" />
								</div>
							<?php endif; ?>
							<?php if (get_option('listeo_display_password_field')) : ?>
								<div class="col-md-6">
									<div class="">

										<input placeholder="<?php esc_html_e('Password', 'listeo_core'); ?>" class="required input-text" type="password" name="password" id="password1" />
										<span class="pwstrength_viewport_progress"></span>
									</div>
									<?php if (get_option('listeo_strong_password')) : ?>
										<div class="password-strength-requirements">
											<?php esc_html_e('Password must be at least', 'listeo_core'); ?> <strong class="req-length">8 <?php esc_html_e('characters', 'listeo_core'); ?></strong> <?php esc_html_e('and include:', 'listeo_core'); ?><br>
											<strong class="req-uppercase"><?php esc_html_e('One uppercase letter', 'listeo_core'); ?></strong>, <strong class="req-number"><?php esc_html_e('one number', 'listeo_core'); ?></strong>, <?php esc_html_e('and', 'listeo_core'); ?> <strong class="req-special"><?php esc_html_e('one special character', 'listeo_core'); ?></strong>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

						<?php } ?>
						<div class="col-md-6">
							<input placeholder="<?php esc_attr_e('First Name', 'listeo_core'); ?>" type="text" id="firstname" name="firstname" value="<?php echo $current_user->first_name; ?>">
						</div>
						<div class="col-md-6">
							<input placeholder="<?php esc_attr_e('Last Name', 'listeo_core'); ?>" type="text" id="lastname" name="lastname" value="<?php echo $current_user->last_name; ?>">
						</div>

						<div class="col-md-6">
							<input placeholder="<?php esc_attr_e('Email', 'listeo_core'); ?>" type="email" class="required" id="email" name="email" value="<?php echo $current_user->user_email; ?>">
						</div>
						<div class="col-md-6">
							<input placeholder="<?php esc_attr_e('Phone', 'listeo_core'); ?>" type="tel" id="phone" name="phone" value="<?php echo $current_user->phone; ?>">
						</div>

						<div class="col-md-12">

							<textarea class="required" cols="40" id="contact-message" name="message" rows="3" placeholder="<?php esc_attr_e('Provide verification details that will help us verify your business', 'listeo_core'); ?>"></textarea>
						</div>
						<?php $upload_file = get_option('listeo_file_upload_claims');
						if ($upload_file) { ?>
							<div class="col-md-12">
								<!-- Upload Button -->
								<div class="uploadButton margin-top-0">

									<input class="uploadButton-input" type="file" name="claim_file" id="claim_file" />
									<label class="uploadButton-button ripple-effect" for="claim_file"><?php esc_html_e('Upload Files', 'listeo_core'); ?></label>
									<span class="uploadButton-file-name"><?php printf(esc_html__('Maximum file size: %s.', 'listeo_core'), size_format(wp_max_upload_size())); ?></span>

								</div>
							</div>
						<?php } ?>

						<?php if (!is_user_logged_in() && !get_option('listeo_display_password_field')) : ?>
							<p style="margin:10px;" class="form-row password-notice-info form-row-wide margin-top-30 margin-bottom-30"><?php esc_html_e('Note: Your password will be generated automatically and sent to your email address.', 'listeo_core'); ?>
							</p>
						<?php endif; ?>

						<?php if (!is_user_logged_in()) { ?>
							<?php $recaptcha = get_option('listeo_recaptcha');
							$recaptcha_version = get_option('listeo_recaptcha_version', 'v2');


							if ($recaptcha && $recaptcha_version == 'v3') { ?>
								<input type="hidden" id="rc_action" name="rc_action" value="ws_register">
								<input type="hidden" id="token" name="token">
							<?php } ?>

							<?php if ($recaptcha && $recaptcha_version == 'v2') { ?>

								<div class="col-md-6 checkboxes margin-bottom-15" style="padding: 0px 20px">
									<div class="g-recaptcha" data-sitekey="<?php echo get_option('listeo_recaptcha_sitekey'); ?>"></div>
								</div>
							<?php } ?>
							<?php if ($recaptcha && $recaptcha_version == 'hcaptcha'): ?>
								<div class="h-captcha" data-sitekey="<?php echo esc_attr(get_option('listeo_hcaptcha_sitekey')); ?>"></div>
							<?php endif; ?>
							<?php if ($recaptcha && $recaptcha_version == 'turnstile'): ?>
								<div class="cf-turnstile" data-theme="light" data-sitekey="<?php echo esc_attr(get_option('listeo_turnstile_sitekey')); ?>"></div>
							<?php endif; ?>
							<?php
							$privacy_policy_status = get_option('listeo_privacy_policy');

							if ($privacy_policy_status && function_exists('the_privacy_policy_link')) { ?>
								<div class="col-md-12">
									<p class="checkboxes margin-bottom-10">
										<input type="checkbox" id="privacy_policy" name="privacy_policy">
										<label for="privacy_policy"><?php esc_html_e('I agree to the', 'listeo_core'); ?> <a target="_blank" href="<?php echo get_privacy_policy_url(); ?>"><?php esc_html_e('Privacy Policy', 'listeo_core'); ?></a> </label>

									</p>
								</div>


							<?php } ?>

							<?php
							$terms_and_condition_status = get_option('listeo_terms_and_conditions_req');
							$terms_and_condition_status_page = get_option('listeo_terms_and_conditions_page');

							if ($terms_and_condition_status) { ?>
								<div class="col-md-12">
									<p class="checkboxes margin-bottom-10">
										<input type="checkbox" id="terms_and_conditions" name="terms_and_conditions">
										<label for="terms_and_conditions"><?php esc_html_e('I agree to the', 'listeo_core'); ?> <a target="_blank" href="<?php echo get_permalink($terms_and_condition_status_page); ?>"><?php esc_html_e('Terms and Conditions', 'listeo_core'); ?></a> </label>

									</p>
								</div>
							<?php } ?>
						<?php } ?>
					</div>
					<button class="button"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i><?php esc_html_e('Claim Now', 'listeo_core'); ?></button>
					<div class="notification closeable success margin-top-20"></div>

				</form>
				<?php if (get_option('listeo_skip_claim_approval')) { ?>
					<div style="display: none" class="booking-confirmation-page claim-confirmation-box">
						<i class="fa fa-check-circle"></i>
						<p class="margin-top-30"><?php esc_html_e('Please click the link below to pay for the package and complete the claim', 'listeo_core'); ?></p>
						<a href="" class="button claim-listing-pay-button"><?php esc_html_e('Pay for claim', 'listeo_core'); ?></a>
					</div>

				<?php } ?>
			</div>
		</div>


<?php }
} ?>