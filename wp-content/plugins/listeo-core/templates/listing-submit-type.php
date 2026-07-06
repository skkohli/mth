<?php


// check if current user has any listings
// $user_id = get_current_user_id(); //the logged in user's id
// $post_type = 'listing';
// $posts = count_user_posts($user_id, $post_type); //cout user's posts
// if ($posts > 0) {
// 	return;
// } 

/* Determine the type of form */
if (isset($_GET["action"])) {
	$form_type = $_GET["action"];
} else {
	$form_type = 'submit';
}
$current_user = wp_get_current_user();
$roles = $current_user->roles;
$role = array_shift($roles);
if (!in_array($role, array('administrator', 'admin', 'owner', 'seller'))) :
	$template_loader = new Listeo_Core_Template_Loader;
	$template_loader->get_template_part('account/owner_only');
	return;
endif;

// Get dynamic listing types
if (function_exists('listeo_core_custom_listing_types')) {
	$custom_types_manager = listeo_core_custom_listing_types();
	$available_types = $custom_types_manager->get_listing_types(true); // Get active types only
} else {
	// Fallback to default types
	$available_types = array(
		(object) array('slug' => 'service', 'name' => 'Service', 'description' => 'Service-based listings'),
		(object) array('slug' => 'rental', 'name' => 'Rental', 'description' => 'Rental listings'),
		(object) array('slug' => 'event', 'name' => 'Event', 'description' => 'Event listings'),
		(object) array('slug' => 'classifieds', 'name' => 'Classifieds', 'description' => 'Classified ads')
	);
}

?>
<form action="<?php echo esc_url($data->action); ?>" method="post" id="submit-listing-form" class="listing-manager-form" enctype="multipart/form-data">

	<div id="add-listing">

		<!-- Section -->
		<div class="add-listing-section type-selection">

			<!-- Headline -->
			<div class="add-listing-headline">
				<h3><?php esc_html_e('Choose Listing Type', 'listeo_core') ?></h3>
			</div>
			<div class="row">
				<div class="col-lg-12">
					<div class="listing-type-container">
						<?php foreach ($available_types as $type) :
							// Check for icon in DB first, only fallback to legacy option when DB value is NULL (not yet migrated)
							$type_icon = 0;
							if ($type->icon_id !== null) {
								$type_icon = intval($type->icon_id);
							} else {
								$type_icon = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
							}
						?>
							
							<a href="#" class="listing-type" data-type="<?php echo esc_attr($type->slug); ?>">
								<?php if ($type_icon) {
									if (get_post_mime_type($type_icon) === 'image/svg+xml') { ?>
										<span class="listing-type-icon">
											<?php
											// Use smart SVG renderer - auto-detects best method based on file size
											// Small files (< 3KB): inline SVG for CSS control
											// Large files (> 3KB): img tag for better caching
											echo listeo_smart_svg_render($type_icon);
											?>
										</span>
									<?php } else {
										echo wp_get_attachment_image($type_icon); ?>
									<?php } ?>
								<?php } else { ?>
									<span class="listing-type-icon">
										<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 315 315" style="enable-background:new 0 0 315 315;" xml:space="preserve">
											<g>
												<g>
													<g>
														<path d="M157.5,0C93.319,0,41.103,52.215,41.103,116.397c0,62.138,106.113,190.466,110.63,195.898
				c1.425,1.713,3.538,2.705,5.767,2.705c2.228,0,4.342-0.991,5.767-2.705c4.518-5.433,110.63-133.76,110.63-195.898
				C273.897,52.215,221.682,0,157.5,0z M157.5,295.598c-9.409-11.749-28.958-36.781-48.303-65.397
				c-34.734-51.379-53.094-90.732-53.094-113.804C56.103,60.486,101.59,15,157.5,15c55.91,0,101.397,45.486,101.397,101.397
				c0,23.071-18.359,62.424-53.094,113.804C186.457,258.817,166.909,283.849,157.5,295.598z" />
														<path d="M195.657,213.956c-3.432-2.319-8.095-1.415-10.413,2.017c-10.121,14.982-21.459,30.684-33.699,46.67
				c-2.518,3.289-1.894,7.996,1.395,10.514c1.36,1.042,2.963,1.546,4.554,1.546c2.254,0,4.484-1.013,5.96-2.941
				c12.42-16.22,23.933-32.165,34.219-47.392C199.992,220.938,199.09,216.275,195.657,213.956z" />
														<path d="M157.5,57.5C123.589,57.5,96,85.089,96,119s27.589,61.5,61.5,61.5S219,152.911,219,119S191.411,57.5,157.5,57.5z
				 M157.5,165.5c-25.64,0-46.5-20.86-46.5-46.5s20.86-46.5,46.5-46.5c25.641,0,46.5,20.86,46.5,46.5S183.141,165.5,157.5,165.5z" />
													</g>
												</g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
											<g>
											</g>
										</svg>

									</span>
								<?php } ?>

								<h3><?php echo esc_html($type->name); ?></h3>
								<!-- <?php if (!empty($type->description)) : ?>
									<p class="listing-type-description"><?php echo esc_html($type->description); ?></p>
								<?php endif; ?> -->
							</a>
						<?php endforeach; ?>
						<input type="hidden" id="listing_type" name="_listing_type">
					</div>
				</div>
			</div>

		</div>
		<div class="submit-page">

			<p>
				<input type="hidden" name="listeo_core_form" value="<?php echo $data->form; ?>" />
				<input type="hidden" name="listing_id" value="<?php echo esc_attr($data->listing_id); ?>" />
				<input type="hidden" name="step" value="<?php echo esc_attr($data->step); ?>" />
				<button type="submit" name="continue" style="display: none" class="button margin-top-20"><?php echo esc_attr($data->submit_button_text); ?> <i class="fa fa-arrow-circle-right"></i></button>

			</p>

</form>
</div>
</div>