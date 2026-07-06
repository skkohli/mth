<?php

/**
 * listing Submission Form
 */

if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$roles = $current_user->roles;
$role = array_shift($roles);
if (!in_array($role, array('administrator', 'admin', 'owner', 'seller'))) :
	$template_loader = new Listeo_Core_Template_Loader;
	$template_loader->get_template_part('account/owner_only');
	return;
endif;

$fields = array();
if (isset($data)) :
	$fields	 	= (isset($data->fields)) ? $data->fields : '';
endif;
if (isset($_GET["action"])) {
	$form_type = $_GET["action"];
} else {
	$form_type = 'submit';
}

$packages = isset($data->packages) ? $data->packages : array();

$user_packages = isset($data->user_packages) ? $data->user_packages : array();

global $woocommerce;
// $woocommerce->cart->empty_cart();
// Determine selected listing type
$selected_type = '';
if (isset($_REQUEST['_listing_type'])) {
	$selected_type = sanitize_title(wp_unslash($_REQUEST['_listing_type']));
}
if (!$selected_type && !empty($data->listing_id)) {
	$selected_type = get_post_meta($data->listing_id, '_listing_type', true);
}


// Filter purchasable packages by selected type
if (!empty($selected_type) && !empty($packages)) {
	$packages = array_filter($packages, function ($p) use ($selected_type) {
		$allowed = get_post_meta($p->ID, '_allowed_listing_types', true);
		if (empty($allowed)) {
			return true; // no restrictions = allowed for all
		}
		if (!is_array($allowed)) {
			$allowed = (array) $allowed;
		}
		return in_array($selected_type, $allowed, true);
	});
}



// Filter owned user packages by selected type
if (!empty($selected_type) && !empty($user_packages)) {
	
	$user_packages = array_filter($user_packages, function ($up) use ($selected_type) {
		$product_id = is_object($up) && isset($up->product_id) ? $up->product_id : 0;
		if (!$product_id) return true;
		$allowed = get_post_meta($product_id, '_allowed_listing_types', true);
		if (empty($allowed)) {
			return true;
		}
		if (!is_array($allowed)) {
			$allowed = (array) $allowed;
		}
		return in_array($selected_type, $allowed, true);
	});
}

// Build a list of owned product IDs that are usable (unlimited or with remaining capacity)
$owned_available_product_ids = function_exists('listeo_get_owned_available_product_ids')
	? listeo_get_owned_available_product_ids($user_packages)
	: array();

// Admin option: when enabled, show purchasable same-type packages even if user already owns one
$allow_same_type_purchasables = (bool) get_option('listeo_show_same_type_purchasables');

// Compute purchasable product IDs that remain available after applying
// listing type restrictions, optional ownership exclusions, subscription limits,
// and single-buy constraints.
$available_purchasable_ids = function_exists('listeo_get_available_purchasable_product_ids')
	? listeo_get_available_purchasable_product_ids(
		$packages,
		array(
			'selected_type'       => $selected_type,
			'exclude_product_ids' => $allow_same_type_purchasables ? array() : $owned_available_product_ids,
			'user_id'             => get_current_user_id(),
			'single_buy_products' => get_option('listeo_buy_only_once'),
		)
	)
	: array();

			
			?>
<form method="post" id="package_selection" class="listing-package-selection">

	<?php if ($packages || $user_packages) :
		$checked = 1;
	?>

		<?php
		// Build normalized, displayable owned packages (hide used up)
		$displayable_owned = function_exists('listeo_get_displayable_owned_packages')
			? listeo_get_displayable_owned_packages($user_packages, array('hide_used_up' => true))
			: array();
			
		?>
		<?php if (!empty($displayable_owned)) : ?>
			<?php
			// Order owned packages by underlying product price (desc)
			uasort($displayable_owned, function ($a, $b) {
				$ap = (method_exists($a, 'get_product_id')) ? wc_get_product((int) $a->get_product_id()) : null;
				$bp = (method_exists($b, 'get_product_id')) ? wc_get_product((int) $b->get_product_id()) : null;
				$aprice = ($ap && method_exists($ap, 'get_price')) ? (float) $ap->get_price() : 0.0;
				$bprice = ($bp && method_exists($bp, 'get_price')) ? (float) $bp->get_price() : 0.0;
				if ($aprice === $bprice) {
					return 0;
				}
				return ($aprice > $bprice) ? -1 : 1; // desc
			});
			?>
			<h3 class="buy-package-headline"><?php _e('Use Package You Own', 'listeo_core'); ?></h3>
			<div class="owned-packages">
				<?php foreach ($displayable_owned as $key => $package) : ?>
					<label for="user-package-<?php echo $package->get_id(); ?>">
						<input type="radio" <?php checked($checked, 1); ?> name="package" value="user-<?php echo $key; ?>" id="user-package-<?php echo $package->get_id(); ?>" />
						<span class="package-checkbox">
							<i class="fa fa-check"></i>
							<div class="owned-package-name">
								<strong><?php echo $package->get_title(); ?></strong>
								<div class="owned-package-desc"><?php
																echo esc_html(function_exists('listeo__format_owned_package_usage') ? listeo__format_owned_package_usage($package) : '');
																$checked = 0; ?>
								</div>
							</div>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>


		<?php if (!empty($available_purchasable_ids)) : ?>
			<h3 class="buy-package-headline"><?php esc_html_e('Buy New Package', 'listeo_core'); ?></h3>
			<div class="new-pricing-packages-container">
				<?php
				// Build and order purchasable products by price (asc)
				$sorted_products = array();
				foreach ($packages as $key => $p) {
					$product = wc_get_product($p);
					if (!$product) {
						continue;
					}
					// Only render products that are actually available to purchase for this user
					if (!in_array((int) $product->get_id(), $available_purchasable_ids, true)) {
						continue;
					}
					if (!$product->is_type(array('listing_package', 'listing_package_subscription')) || !$product->is_purchasable()) {
						continue;
					}
					// Skip if user already owns an available package for this product (unless allowed by admin option)
					if (!$allow_same_type_purchasables && !empty($owned_available_product_ids) && in_array((int) $product->get_id(), $owned_available_product_ids, true)) {
						continue;
					}
					$user_id = get_current_user_id();
					if ($product->is_type(array('listing_package_subscription')) && function_exists('wcs_is_product_limited_for_user') && wcs_is_product_limited_for_user($product, $user_id)) {
						continue;
					}
					$sorted_products[] = $product;
				}
				usort($sorted_products, function ($a, $b) {
					$ap = (method_exists($a, 'get_price')) ? (float) $a->get_price() : 0.0;
					$bp = (method_exists($b, 'get_price')) ? (float) $b->get_price() : 0.0;
					if ($ap === $bp) {
						return 0;
					}
					return ($ap < $bp) ? -1 : 1; // asc
				});

				$counter = 0;
				foreach ($sorted_products as $product) :
				?>
					<div class="pricing-package  <?php echo ($product->is_featured()) ? 'best-value-plan' : ''; ?>">
						<div class="pricing-package-header">
							<h4><?php echo $product->get_title(); ?></h4>
							<?php if ($product->is_featured()) { ?><span><?php esc_html_e('Best Value', 'listeo_core') ?></span> <?php } ?>
						</div>
						<?php if ($product->get_short_description()) { ?><div class="pricing-package-text"><?php echo $product->get_short_description(); ?></div><?php } ?>

						<div class="pricing-package-price">
							<strong><?php echo $product->get_price_html(); ?></strong>
						</div>
						<div class="pricing-package-details">
							<?php
							$package_subtitle = get_post_meta($product->get_id(), '_package_subtitle', true);
							if ($package_subtitle) {
								echo '<h6>' . $package_subtitle . '</h6>';
							} else { ?>
								<h6><?php echo $product->get_title(); ?> <?php esc_html_e('features:', 'listeo_core') ?></h6>
							<?php } ?>

							<ul class="plan-features-auto-wc">
								<li>
									<svg xmlns=" http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
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
										printf(_n('%d listing', '%d listings', $listingslimit, 'listeo_core') . ' ', $listingslimit); ?>
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
									<?php if ($product->has_listing_booking()) : ?>
										<li>
											<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg>
											<?php esc_html_e('Booking Module enabled', 'listeo_core'); ?>
										</li>
									<?php endif; ?>
									<?php if ($product->has_listing_reviews()) : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('Reviews Module enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->has_listing_social_links()) : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('Social Links Module enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->has_listing_opening_hours()) : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('Opening Hours Module enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->has_listing_video()) : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('Video option enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->has_listing_coupons() == 'yes') : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('Coupons option enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->has_listing_faq() == 'yes') : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('FAQ option enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->has_listing_pricing_menu() == 'yes') : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('Pricing Menu Module enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->has_listing_gallery() == 'yes') : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php esc_html_e('Gallery Module enabled', 'listeo_core'); ?></li>
									<?php endif; ?>
									<?php if ($product->get_option_gallery_limit()) : ?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg><?php printf(esc_html__('Maximum  %s images in gallery', 'listeo_core'), $product->get_option_gallery_limit()); ?></li>
									<?php endif; ?>
									<?php
									// Dokan Store Access
									$dokanStoreOption = $product->has_listing_dokan_store();
									if ($dokanStoreOption) :
										$storeDuration = $product->get_dokan_store_duration();
									?>
										<li><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
												<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
													<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
													<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
												</g>
											</svg>
											<?php
											if ($storeDuration > 0) {
												printf(
													esc_html__('Dokan Store Access (%s days)', 'listeo_core'),
													$storeDuration
												);
											} else {
												esc_html_e('Unlimited Dokan Store Access', 'listeo_core');
											}
											?>
										</li>
									<?php endif; ?>
								<?php endif; ?>
								<?php $custom_listing_fields = get_post_meta($product->get_id(), 'package_items_group', true);
								if ($custom_listing_fields) {
									foreach ($custom_listing_fields as $key => $entry) {
										$title = esc_html($entry['title']);
										if (!empty($title)) { ?>
											<li class="custom_listing_field"><svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 42 42">
													<g id="Group_33" data-name="Group 33" transform="translate(-1122 -2972.25)">
														<circle id="Ellipse_4" data-name="Ellipse 4" cx="21" cy="21" r="21" transform="translate(1122 2972.25)" fill="rgba(248,0,68,0.11)" />
														<path id="Vector" d="M6,12.655l4.9,4.9,9.795-9.8" transform="translate(1129.5 2979.993)" fill="none" stroke="#f80044" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
													</g>
												</svg><?php echo esc_html($title); ?></li>
								<?php }
									}
								}
								?>
							</ul>
							<?php echo $product->get_description(); ?>
						</div>
						<div class="pricing-package-select">
							<input type="radio" <?php if (!$user_packages && $counter == 0) : ?> checked="checked" <?php endif; ?> name="package" value="<?php echo $product->get_id(); ?>" id="package-<?php echo $product->get_id(); ?>" />
							<label for="package-<?php echo $product->get_id(); ?>">
								<span class="plan-unchecked"><i class="fa fa-check"></i> <?php esc_html_e('Select This Package', 'listeo_core') ?> </span>
								<span class="plan-checked"> <i class="fa fa-check"></i> <?php esc_html_e('Selected', 'listeo_core') ?></span>
							</label>
						</div>
					</div>
				<?php $counter++;
				endforeach; ?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<p><?php _e('No packages found', 'listeo_core'); ?></p>
	<?php endif; ?>

	<div class="submit-page">
		<p>
			<?php if (! empty($selected_type)) : ?>
				<input type="hidden" name="_listing_type" value="<?php echo esc_attr($selected_type); ?>" />
			<?php endif; ?>
			<input type="hidden" name="listeo_core_form" value="<?php echo $data->form; ?>" />
			<input type="hidden" name="listing_id" value="<?php echo esc_attr($data->listing_id); ?>" />
			<input type="hidden" name="step" value="<?php echo esc_attr($data->step); ?>" />
			<button type="submit" name="continue" class="button"><?php echo esc_attr($data->submit_button_text); ?> <i class="fa fa-arrow-circle-right"></i></button>
		</p>
	</div>
</form>