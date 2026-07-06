<?php

/**
 * Coupon Submission Form
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

if (isset($data->coupon_data)) {
	$coupon_id = $data->coupon_data->ID;
}
?>

<!-- <div class="switch_box box_1">
	<input type="checkbox" class="input-checkbox switch_1" name="_email_contact_widget" id="_email_contact_widget" value="on" maxlength="">
</div> -->

<div class="submit-page submit-coupon">

	<!-- Security notice about coupon usage -->
	<div class="notification notice margin-bottom-30">
		<p>
			<strong><?php esc_html_e('Important: Coupon Usage Restrictions', 'listeo_core'); ?></strong><br>
			<?php esc_html_e('Coupons you create can ONLY be used for bookings on your own listings. They cannot be used to purchase listing packages or for bookings on other users\' listings.', 'listeo_core'); ?>
		</p>
	</div>

	<?php
	if (isset($data->coupon_edit) && $data->coupon_edit) {
	?>
		<div class="notification row closeable notice">
			<p><strong><?php esc_html_e('You are editing coupon.', 'listeo_core'); ?></strong></p>
		</div>
	<?php }
	$coupons_page = get_option('listeo_coupons_page');

	?>
	<form action="<?php echo get_permalink($coupons_page); ?>" method="post" id="submit-listing-form" class="listing-manager-form" enctype="multipart/form-data">
		<?php if (isset($data->coupon_edit) && $data->coupon_edit) { ?>
			<input type="hidden" name="listeo-coupon-edit" value="1">
			<input type="hidden" name="listeo-coupon-id" value="<?php echo $coupon_id; ?>">
		<?php } else { ?>
			<input type="hidden" name="listeo-coupon-submission" value="1">
		<?php } ?>

		<div class="add-listing-section row">
			<div class="add-listing-headline">
				<h3><i class="sl sl-icon-doc"></i> <?php esc_html_e('General Coupon Settings', 'listeo_core'); ?></h3>
			</div>
			<div class="col-md-12">
				<p class="form-field coupon_amount_field ">
					<label for="coupon_amount"><?php esc_html_e('Coupon code', 'listeo_core'); ?></label>
					<input type="text" required class="short wc_input_decimal" style="" value="<?php if (isset($coupon_id)) echo esc_html($data->coupon_data->post_title); ?>" name="title" id="coupon_title">
				</p>
			</div>
			<div class="col-md-12">
				<p class="form-field coupon_amount_field ">
					<label for="coupon_amount"><?php esc_html_e('Coupon Description', 'listeo_core'); ?></label>
					<textarea style="min-height: 100px;" id="woocommerce-coupon-description" name="excerpt" cols="5" rows="1" placeholder="<?php esc_html_e('Description (optional)', 'listeo_core'); ?>"><?php if (isset($coupon_id)) echo esc_html($data->coupon_data->post_excerpt); ?></textarea>
				</p>
			</div>
			<div class="col-md-12">

				<?php
				$coupon_bg = false;
				if (isset($coupon_id)) {


					$coupon_bg = get_post_meta($coupon_id, 'coupon_bg-uploader-id', true);
					$coupon_bg_url = wp_get_attachment_url($coupon_bg);
				}

				if (isset($coupon_bg) && !empty($coupon_bg)) { ?>
					<div data-photo="<?php echo $coupon_bg_url; ?>" data-name="<?php esc_html_e('Coupon Widget Background', 'listeo_core'); ?>" data-size="<?php echo filesize(get_attached_file($coupon_bg)); ?>" class="edit-coupon-photo">

					<?php } else { ?>
						<div class="edit-coupon-photo">
						<?php } ?>

						<div id="coupon_bg-uploader" class="dropzone">
							<div class="dz-message" data-dz-message><span><?php esc_html_e('Upload Widget Background', 'listeo_core'); ?></span></div>
						</div>
						<input class="hidden" name="listeo_coupon_bg_id" type="text" id="coupon_bg-uploader-id" value="<?php echo $coupon_bg; ?>" />
						</div>



					</div>
					<div id="general_coupon_data" class="panel woocommerce_options_panel" style="display: block;">
						<div class="col-md-4">
							<p class=" form-field discount_type_field">
								<label for="discount_type"><?php esc_html_e('Discount type', 'listeo_core'); ?></label>
								<select class="select2-single" id="discount_type" name="discount_type">
									<?php $discount_type_value = (isset($coupon_id)) ? get_post_meta($coupon_id, 'discount_type', true) : ''; ?>
									<option <?php selected($discount_type_value, 'percent'); ?> value="percent"><?php esc_html_e('Percentage discount', 'listeo_core'); ?></option>
									<option <?php selected($discount_type_value, 'fixed_product'); ?> value="fixed_product"><?php esc_html_e('Fixed product discount', 'listeo_core'); ?></option>

								</select>
							</p>
						</div>

						<div class="col-md-4">
							<p class="form-field coupon_amount_field ">
								<label for="coupon_amount"><?php esc_html_e('Coupon amount', 'listeo_core'); ?></label>
								<input type="number" min=1 class="short wc_input_decimal" style="" required name="coupon_amount" id="coupon_amount" value="<?php if (isset($coupon_id)) echo esc_html(get_post_meta($coupon_id, 'coupon_amount', true)); ?>" placeholder="0">
							</p>
						</div>

						<div class="col-md-4">
							<p class="form-field expiry_date_field ">
								<label for="expiry_date"><?php esc_html_e('Coupon expiry date', 'listeo_core'); ?></label>
								<?php
								if (isset($data->coupon_edit) && $data->coupon_edit) {
									$date_expires = get_post_meta($coupon_id, 'date_expires', true);
								} ?>
								<input type="text" class="input-date" style="" name="expiry_date" autocomplete="off" id="expiry_date" value="<?php if (isset($data->coupon_edit) && $date_expires) echo date('Y-m-d', $date_expires);  ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
							</p>
						</div>
					</div>
			</div>

			<div class="add-listing-section row">
				<div class="add-listing-headline">
					<h3><i class="sl sl-icon-doc"></i> <?php esc_html_e('Usage restrictions', 'listeo_core'); ?></h3>
				</div>
				<div id="usage_restriction_coupon_data" class="panel woocommerce_options_panel" style="display: block;">


					<div class="col-md-6">
						<p class="form-field minimum_amount_field ">
							<label for="minimum_amount"><?php esc_html_e('Minimum spend', 'listeo_core'); ?></label>

							<input type="number" class="short wc_input_price" style="" name="minimum_amount" id="minimum_amount" value="<?php if (isset($coupon_id)) echo esc_html(get_post_meta($coupon_id, 'minimum_amount', true)); ?>" placeholder="<?php esc_html_e('No minimum', 'listeo_core'); ?>">
						</p>
					</div>
					<div class="col-md-6">
						<p class="form-field maximum_amount_field ">
							<label for="maximum_amount"><?php esc_html_e('Maximum spend', 'listeo_core'); ?></label>
							<input type="number" class="short wc_input_price" style="" name="maximum_amount" id="maximum_amount" value="<?php if (isset($coupon_id)) echo esc_html(get_post_meta($coupon_id, 'maximum_amount', true)); ?>" placeholder="<?php esc_html_e('No maximum', 'listeo_core'); ?>">
						</p>
					</div>
					<div class="col-md-6">
						<p class="form-field">
							<?php
							if (isset($coupon_id)) {

								$listing_ids = get_post_meta($coupon_id, 'listing_ids', true);

								if (!empty($listing_ids)) {
									$listing_ids_array = explode(',', $listing_ids);
								} else {
									$listing_ids_array = array();
								}
							} else {

								$listing_ids_array = array();
							}
							?>
							<label><?php esc_html_e('For products:', 'listeo_core'); ?><i class="tip" data-tip-content="<?php esc_html_e('Select which of YOUR listings this coupon can be used for bookings. Leave empty to apply for all your listings.', 'listeo_core'); ?>"></i></label>
							<select class="select2-single" multiple="" style="width: 50%;" name="listing_ids[]" data-placeholder="<?php esc_html_e('Search for your listing', 'listeo_core'); ?>" data-action="" tabindex="-1" aria-hidden="true">

								<?php

								$args = array(
									'author'        	=>  $current_user->ID,
									'posts_per_page'  	=> -1,
									'post_type'		  	=> 'listing',
									'post_status'	  	=> 'publish'

								);
								$posts = get_posts($args);

								foreach ($posts as $post) : setup_postdata($post); ?>
									<option <?php if (in_array($post->ID, $listing_ids_array)) {
												echo "selected";
											} ?> value="<?php echo $post->ID; ?>"><?php the_title(); ?></option>
								<?php endforeach; ?>
							</select>

							</select>


						</p>
					</div>
					<div class="col-md-6">
						<p class="form-field individual_use_field ">
							<label for="individual_use"><?php esc_html_e('Individual use only', 'listeo_core'); ?> <i class="tip" data-tip-content="<?php esc_html_e('Check this box if the coupon cannot be used in conjunction with other coupons.', 'listeo_core'); ?>"></i></label>

						<div class="switch_box box_1"><input <?php if (isset($data->coupon_edit) && get_post_meta($coupon_id, 'individual_use', true) == "yes") {
																	echo 'checked="checked"';
																} ?>type="checkbox" class="input-checkbox switch_1" style="" name="individual_use" id="individual_use" value="yes"> </div>


						</p>
					</div>


					<div class="col-md-12">
						<p class="form-field customer_email_field ">
							<label for="customer_email"><?php esc_html_e('Allowed emails ', 'listeo_core'); ?><i class="tip" data-tip-content="<?php esc_html_e('Separate by coma', 'listeo_core'); ?>"></i></label>
							<?php
							if (isset($coupon_id) && !empty($coupon_id)) {
								$customer_emails = get_post_meta($coupon_id, 'customer_email', true);
							} else {
								$customer_emails  = array();
							}
							if(!is_array($customer_emails)){
								$customer_emails = explode(',', $customer_emails);
							}
							$saved_emails = implode(', ', $customer_emails);
							?>
							<input type="text" class="" style="" name="customer_email" id="customer_email" value="<?php echo $saved_emails; ?>" placeholder="<?php esc_html_e('No restrictions', 'listeo_core'); ?>">
						</p>
					</div>

				</div>
			</div>

			<div class=" add-listing-section row">
						<div class="add-listing-headline">
							<h3><i class="sl sl-icon-doc"></i> <?php esc_html_e('Usage limits', 'listeo_core'); ?></h3>
						</div>
						<div id="usage_limit_coupon_data" class="panel woocommerce_options_panel" style="display: block;">
							<div class="col-md-6">
								<p class="form-field usage_limit_field ">
									<label for="usage_limit"><?php esc_html_e('Usage limit per coupon', 'listeo_core'); ?></label>
									<input type="number" class="short" style="" name="usage_limit" id="usage_limit" value="<?php if (isset($coupon_id)) echo esc_html(get_post_meta($coupon_id, 'usage_limit', true)); ?>" placeholder="<?php esc_html_e('Unlimited usage', 'listeo_core'); ?>" step="1" min="0">
								</p>
							</div>

							<div class="col-md-6">
								<p class="form-field usage_limit_per_user_field ">
									<label for="usage_limit_per_user"><?php esc_html_e('Usage limit per user', 'listeo_core'); ?></label>
									<input type="number" class="short" style="" name="usage_limit_per_user" id="usage_limit_per_user" value="<?php if (isset($coupon_id)) echo esc_html(get_post_meta($coupon_id, 'usage_limit_per_user', true)); ?>" placeholder="<?php esc_html_e('Unlimited usage', 'listeo_core'); ?>" step="1" min="0">
								</p>
							</div>
						</div>
					</div>

					<div class="divider margin-top-40"></div>

					<p>

						<input type="hidden" name="listeo_core_form" value="submit_coupon" />

						<button type="submit" value="Submit Coupon" name="submit_coupon" class="button margin-top-20"><i class="fa fa-arrow-circle-right"></i>
							<?php if (isset($data->coupon_edit) && $data->coupon_edit) {
								esc_html_e('Update Coupon', 'listeo_core');
							} else {
								esc_html_e('Submit Coupon', 'listeo_core');
							} ?>
						</button>

					</p>

	</form>