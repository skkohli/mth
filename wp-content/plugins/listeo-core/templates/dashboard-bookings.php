<?php $template_loader = new Listeo_Core_Template_Loader;
$current_user = wp_get_current_user();
$roles = $current_user->roles;
$role = array_shift($roles);
// if(!in_array($role,array('administrator','admin','owner'))) :
// 	$template_loader = new Listeo_Core_Template_Loader; 
// 	$template_loader->get_template_part( 'account/owner_only'); 
// 	return;
// endif;
$type = '';

if (isset($data->type)) {
	if ($data->type == 'user_booking') {
		$type = "user";
	}
} ?>
<div class="row">
	<!-- Listings -->
	<div class="col-lg-12 col-md-12">
		<div class="dashboard-list-box  margin-top-0">
			<div class="headline-with-filters list-view">


				<!-- Booking Requests Filters  -->
				<div class="booking-requests-filter">
					<?php if ($type == "user") : ?>
						<input type="hidden" id="dashboard_type" name="dashboard_type" value="user">
					<?php else : ?>
						<input type="hidden" id="dashboard_type" name="dashboard_type" value="owner">
						<!-- <input type="text" name="booking_author" id="dashboard-booking-author-search"> -->
						<div class="sort-by-booking-author">
							<div class="sort-by-select">
								<select data-placeholder="<?php esc_attr_e('All Users', 'listeo_core') ?>" class="select2-bookings-author" id="booking_author">
									<option value="show_all"><?php echo esc_html__('All Users', 'listeo_core') ?></option>
									<?php
									$users = listeo_get_bookings_author(get_current_user_id());
									if (!empty($users)) {
										foreach ($users as $key => $value) { ?>
											<option value="<?php echo $value[0]; ?>"><?php echo listeo_get_users_name($value[0]); ?></option>
									<?php }
									}
									?>
								</select>
							</div>
						</div>
					<?php endif; ?>
					<?php if (($type !== "user" && !isset($_GET['status'])) || ($type !== "user" && isset($_GET['status']) && $_GET['status'] == 'approved')) : ?>
						<!-- Sort by -->
						<div class="sort-by-status">
							<div class="sort-by-select">
								<select data-placeholder="<?php esc_attr_e('Default order', 'listeo_core') ?>" class="select2-bookings-status" id="listing_status">
									<option value="approved"><?php echo esc_html__('All Statuses', 'listeo_core') ?></option>
									<option value="confirmed"><?php echo esc_html__('Unpaid', 'listeo_core') ?></option>
									<option value="paid"><?php echo esc_html__('Paid', 'listeo_core') ?></option>
								</select>
							</div>
						</div>
					<?php endif; ?>

					<?php if (isset($_GET['status']) && $_GET['status'] != 'approved') { ?>
						<input type="hidden" id="listing_status" value="<?php echo esc_attr(sanitize_text_field($_GET['status'])); ?>">
					<?php } else { ?>
						<input type="hidden" id="listing_status" value="approved">
					<?php } ?>
					<?php if ($type !== "user" && isset($data->listings) && !empty($data->listings)) : ?>
						<!-- Sort by -->
						<div class="sort-by">
							<div class="sort-by-select">
								<select data-placeholder="Default order" class="select2-bookings" id="listing_id">
									<option value="show_all"><?php echo esc_html__('All Listings', 'listeo_core') ?></option>
									<?php foreach ($data->listings as $listing_id) { ?>
										<option value="<?php echo $listing_id; ?>"><?php echo get_the_title($listing_id); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
					<?php endif; ?>


					<!-- Date Range -->
					<div id="booking-date-range-enabler">
						<span><?php esc_html_e('Pick a Date', 'listeo_core'); ?></span>
					</div>

					<div id="booking-date-range" style="display: none;">
						<span></span>
					</div>


				</div>

				<!-- Reply to review popup -->


				<h4><?php ($type == "user") ? esc_html_e('Your Bookings', 'listeo_core') : esc_html_e('Booking Requests', 'listeo_core');
					?> <i class="fa fa-circle-o-notch fa-spin booking-loading-icon"></i> </h4>
			</div>

			<ul id="no-bookings-information" style="display: none">
				<?php esc_html_e('We haven\'t found any bookings for that criteria', 'listeo_core'); ?>
			</ul>
			<?php if (isset($data->bookings) && empty($data->bookings)) { ?>
				<ul id="no-bookings-information">
					<?php esc_html_e('You don\'t have any bookings yet', 'listeo_core'); ?>
				</ul>
			<?php } else { ?>
				<ul id="booking-requests">
					<?php
					
					foreach ($data->bookings as $key => $value) {
						$value['listing_title'] = get_the_title($value['listing_id']);
						if ($type == "user") {
							$template_loader->set_template_data($value)->get_template_part('booking/content-user-booking');
						} else {
							$template_loader->set_template_data($value)->get_template_part('booking/content-booking');
						}
					} ?>
				</ul>
			<?php } ?>

		</div>
		<div class="pagination-container ">
			<?php echo listeo_core_ajax_pagination($data->pages, 1); ?>
		</div>
		<div id="small-dialog" class="zoom-anim-dialog mfp-hide">
			<div class="small-dialog-header">
				<h3><?php esc_html_e('Send Message', 'listeo_core'); ?></h3>
			</div>
			<div class="message-reply margin-top-0">
				<form action="" id="send-message-from-widget" data-booking_id="">
					<textarea data-recipient="" data-referral="" required cols="40" id="contact-message" name="message" rows="3" placeholder="<?php esc_attr_e('Your message', 'listeo_core'); // echo $owner_data->first_name; 
																																				?>"></textarea>
					<button class="button">
						<i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i><?php esc_html_e('Send Message', 'listeo_core'); ?></button>
					<div class="notification closeable success margin-top-20"></div>

				</form>

			</div>
		</div>
		<a style="display:none;" href="#small-dialog" class="send-message-to-owner button popup-with-zoom-anim"><i class="sl sl-icon-envelope-open"></i> <?php esc_html_e('Send Message', 'listeo_core'); ?></a>
	</div>
</div>