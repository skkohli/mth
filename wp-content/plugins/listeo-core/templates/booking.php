<?php

// get user email
if (is_user_logged_in()) {

	$current_user = wp_get_current_user();
	$email = $current_user->user_email;
	$first_name =  $current_user->first_name;
	if ( empty( $first_name ) ) {
		$first_name = get_user_meta( $current_user->ID, 'billing_first_name', true );
	}
	$last_name =  $current_user->last_name;
	if ( empty( $last_name ) ) {
		$last_name = get_user_meta( $current_user->ID, 'billing_last_name', true );
	}
	$phone =  get_user_meta($current_user->ID, 'billing_phone', true);
	$billing_address_1 =  get_user_meta($current_user->ID, 'billing_address_1', true);
	$billing_address_2 =  get_user_meta($current_user->ID, 'billing_address_2', true);
	$billing_postcode =  get_user_meta($current_user->ID, 'billing_postcode', true);
	$billing_company =  get_user_meta($current_user->ID, 'billing_company', true);
	$billing_state =  get_user_meta($current_user->ID, 'billing_state', true);
	$billing_city =  get_user_meta($current_user->ID, 'billing_city', true);
	$message =  false;
} else {

	$email  = (isset($_POST['email'])) ? $_POST['email'] : false;
	$first_name =  (isset($_POST['firstname'])) ? $_POST['firstname'] : false;
	$last_name =  (isset($_POST['lastname'])) ? $_POST['lastname'] : false;
	$phone =  (isset($_POST['phone'])) ? $_POST['phone'] : false;
	$message =  (isset($_POST['message'])) ? $_POST['message'] : false;
	$billing_address_1 =  (isset($_POST['billing_address_1'])) ? $_POST['billing_address_1'] : false;
	$billing_address_2 =  (isset($_POST['billing_address_2'])) ? $_POST['billing_address_1'] : false;
	$billing_company =  (isset($_POST['billing_company'])) ? $_POST['billing_company'] : false;
	$billing_postcode =  (isset($_POST['billing_postcode'])) ? $_POST['billing_postcode'] : false;
	$billing_state =  (isset($_POST['billing_state'])) ? $_POST['billing_state'] : false;
	$billing_city =  (isset($_POST['billing_city'])) ? $_POST['billing_city'] : false;
}

// get meta of listing

// get first images
$gallery = get_post_meta($data->listing_id, '_gallery', true);
$instant_booking = get_post_meta($data->listing_id, '_instant_booking', true);
$listing_type = get_post_meta($data->listing_id, '_listing_type', true);
$payment_option = get_post_meta($data->listing_id, '_payment_option', true);
$decimals = get_option('listeo_number_decimals', 2);
foreach ((array) $gallery as $attachment_id => $attachment_url) {
	$image = wp_get_attachment_image_src($attachment_id, 'listeo-gallery');
	break;
}

if (!$image) {
	$image = wp_get_attachment_image_src(get_post_thumbnail_id($data->listing_id), 'listeo-gallery', false);
}
$email_required = get_option('listeo_booking_email_required');
$first_name_required = get_option('listeo_booking_first_name_required');
$last_name_required = get_option('listeo_booking_last_name_required');

if (isset($data->registration_errors) && !empty($data->registration_errors)) {
	$registration_errors = $data->registration_errors;
} else {
	$registration_errors = array();
}

?>
<div class="row">

	<!-- Content
		================================================== -->
	<div class="col-lg-8 col-md-8 padding-right-30">

		<h3 class="margin-top-0 margin-bottom-30"><?php esc_html_e('Personal Details', 'listeo_core'); ?></h3>

		<?php
		/**
		 * Hook: listeo_before_booking_confirmation_form
		 *
		 * Allows you to add custom content before the booking confirmation form
		 *
		 * @param object $data Booking data object
		 */
		do_action('listeo_before_booking_confirmation_form', $data);
		?>

		<form id="booking-confirmation" action="" method="POST" enctype="multipart/form-data">
			<input type="hidden" name="confirmed" value="yessir" />
			<input type="hidden" name="value" value="<?php echo esc_html($data->submitteddata); ?>" />
			<input type="hidden" name="listing_id" id="listing_id" value="<?php echo $data->listing_id; ?>">
			<input type="hidden" name="coupon_code" class="input-text" id="coupon_code" value="<?php if (isset($data->coupon)) echo $data->coupon; ?>" placeholder="<?php esc_html_e('Coupon code', 'listeo_core'); ?>">
			<div class="row">
				<?php
				if (!is_user_logged_in()) :
					$email_required = true;
					$booking_without_login = get_option('listeo_booking_without_login', 'off');

					$popup_login = get_option('listeo_popup_login', 'ajax');

					if ($booking_without_login == 'on') { ?>
						<?php if ($registration_errors) {
							foreach ($registration_errors as $key => $error) {
								switch ($error) {
									case 'email':
										$errors[] = esc_html__('The email address you entered is not valid.', 'listeo_core');
										break;
									case 'email_exists':
										$errors[] = esc_html__('An account exists with this email address.', 'listeo_core');
										break;
									case 'closed':
										$errors[] = esc_html__('Registering new users is currently not allowed.', 'listeo_core');
										break;
									case 'captcha-no':
										$errors[] = esc_html__('Please check reCAPTCHA checbox to register.', 'listeo_core');
										break;
									case 'username_exists':
										$errors[] =  esc_html__('This username already exists.', 'listeo_core');
										break;
									case 'captcha-fail':
										$errors[] = esc_html__("You're a bot, aren't you?.", 'listeo_core');
										break;
									case 'policy-fail':
										$errors[] = esc_html__("Please accept the Privacy Policy to register account.", 'listeo_core');
										break;
									case 'terms-fail':
										$errors[] = esc_html__("Please accept the Terms and Conditions to register account.", 'listeo_core');
										break;
									case 'otp-fail':
										$errors[] = esc_html__("Your one time verification code was not correct, please try again.", 'listeo_core');
										break;
									case 'first_name':
										$errors[] = esc_html__("Please provide your first name", 'listeo_core');
										break;
									case 'last_name':
										$errors[] = esc_html__("Please provide your last name", 'listeo_core');
										break;
									case 'empty_user_login':
										$errors[] = esc_html__("Please provide your user login", 'listeo_core');
										break;
									case 'password-no':
										$errors[] = esc_html__("You have forgot about password.", 'listeo_core');
										break;
									case 'strong_password':
										$errors[] = esc_html__("Password is too weak.", 'listeo_core');
										break;
									case 'registration_closed':
										$errors[] = esc_html__("Registration is closed.", 'listeo_core');
										break;
									case 'incorrect_password':
										$err = __(
											"The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
											'listeo_core'
										);
										$errors[] =  sprintf($err, wp_lostpassword_url());
										break;

									default:
										# code...
										break;
								}
							}
						} ?>
						<?php if (isset($errors) && is_array($errors) && count($errors) > 0) : ?>
							<?php foreach ($errors  as $error) : ?>
								<div class="col-md-12">
									<div class="notification error closeable">
										<p><?php echo ($error); ?></p>
										<a class="close"></a>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
						<div class="col-md-12">

							<div class="woocommerce-info margin-bottom-30">
								<?php _e('Your account will be created automatically based on data you provide below. <br> If you already have an account, please', 'listeo_core'); ?>
								<?php if ($popup_login == 'ajax') { ?>

									<a href="#sign-in-dialog" class="popup-with-zoom-anim">
										<?php esc_html_e('login', 'listeo_core') ?></span>.
									</a>

								<?php } else {

									$login_page = get_option('listeo_profile_page'); ?>
									<a href="<?php echo esc_url(get_permalink($login_page)); ?>"><?php esc_html_e('login', 'listeo_core') ?></span>.
									</a>
								<?php } ?>
							</div>
						</div>
						<input type="hidden" name="user_role" value="guest" checked />
						<?php if (!get_option('listeo_registration_hide_username')) : ?>
							<div class="col-md-6 booking-registration-field">
								<div class="input-with-icon medium-icons">
									<label><?php esc_html_e('Username', 'listeo_core'); ?> <i class="fas fa-asterisk"></i></label>
									<input required type="text" class="input-text" name="username" id="username2" value="<?php if (isset($_POST['username']) && !empty($_POST['username'])) {
																																echo esc_attr(sanitize_text_field($_POST['username']));
																															} ?>" />
									<i class="sl sl-icon-user"></i>
								</div>
							</div>
						<?php endif; ?>

						<?php if (get_option('listeo_display_password_field')) : ?>
							<div class="col-md-6 booking-registration-field">
								<div class="">
									<label for="password1"><?php esc_html_e('Password', 'listeo_core'); ?></label>
									<input required class="input-text" type="password" name="password" id="password1" />
									<span class="pwstrength_viewport_progress"></span>
								</div>
							</div>
						<?php endif; ?>


						<?php $recaptcha = get_option('listeo_recaptcha');
						$recaptcha_version = get_option('listeo_recaptcha_version', 'v2');


						if ($recaptcha && $recaptcha_version == 'v3') { ?>
							<input type="hidden" id="rc_action" name="rc_action" value="ws_register">
							<input type="hidden" id="token" name="token">
						<?php } ?>
				<?php }
				endif; ?>
				<div class="col-md-6">
					<div class="input-without-icon">
						<label><?php esc_html_e('First Name', 'listeo_core'); ?><?php if ($first_name_required) {
																					echo '<i class="fas fa-asterisk"></i>';
																				} ?></label>
						<input type="text" <?php if ($first_name_required) {
												echo "required";
											} ?> id="firstname" name="firstname" value="<?php esc_html_e($first_name); ?>">
					</div>
				</div>

				<div class="col-md-6">
					<div class="input-without-icon">
						<label><?php esc_html_e('Last Name', 'listeo_core'); ?><?php if ($last_name_required) {
																					echo '<i class="fas fa-asterisk"></i>';
																				} ?></label>
						<input type="text" <?php if ($last_name_required) {
												echo "required";
											} ?> name="lastname" id="lastname" value="<?php esc_html_e($last_name); ?>">
					</div>
				</div>

				<?php ?>
				<div class="col-md-6">
					<div class="input-with-icon medium-icons">
						<label><?php esc_html_e('E-Mail Address', 'listeo_core'); ?><?php if ($email_required) {
																						echo '<i class="fas fa-asterisk"></i>';
																					} ?></label>
						<input type="text" <?php if ($email_required) {
												echo "required";
											} ?> name="email" id="email" value="<?php esc_html_e($email); ?>">
						<i class="sl sl-icon-envelope-open"></i>
					</div>
				</div>

				<?php $phone_required = get_option('listeo_booking_phone_required'); ?>
				<div class="col-md-6">
					<div class="input-with-icon medium-icons">
						<label><?php esc_html_e('Phone', 'listeo_core'); ?><?php if ($phone_required) {
																				echo '<i class="fas fa-asterisk"></i>';
																			} ?> </label>
						<input type="text" <?php if ($phone_required) {
												echo "required";
											} ?> name="phone" id="phone" value="<?php esc_html_e($phone); ?>">
						<i class="sl sl-icon-phone"></i>
					</div>
				</div>
				<!-- /// -->

				<?php if (get_option('listeo_add_address_fields_booking_form')) :
					$address_fields = get_option('listeo_booking_address_displayed', array('billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_country', 'billing_state'));
					$address_fields_requirements = get_option('listeo_booking_address_required', array('billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_country', 'billing_state'));
					if (empty($address_fields_requirements)) {
						$address_fields_requirements = array();
					} ?>

					<?php if (in_array('billing_company', $address_fields)) {
						$billing_company_required = in_array('billing_company', $address_fields_requirements);
					?>
						<div class="col-md-6">
							<div class="input-without-icon">
								<label><?php esc_html_e('Company Name', 'listeo_core'); ?><?php if ($billing_company_required) { ?><i class="fas fa-asterisk"></i> <?php } ?></label>
								<input <?php if ($billing_company_required) { ?>required<?php } ?> type="text" id="billing_company" name="billing_company" value="<?php esc_html_e($billing_company); ?>">
							</div>
						</div>
					<?php } ?>
					<?php if (in_array('billing_address_1', $address_fields)) {
						$billing_address_1_required = in_array('billing_address_1', $address_fields_requirements); ?>
						<div class="col-md-6">
							<div class="input-without-icon">
								<label><?php esc_html_e('Street Address', 'listeo_core'); ?><?php if ($billing_address_1_required) { ?><i class="fas fa-asterisk"></i> <?php } ?></label>
								<input <?php if ($billing_address_1_required) { ?>required<?php } ?> type="text" id="billing_address_1" name="billing_address_1" value="<?php esc_html_e($billing_address_1); ?>">

							</div>
						</div>
					<?php } ?>
					<?php if (in_array('billing_address_2', $address_fields)) {
						$billing_address_2_required = in_array('billing_address_2', $address_fields_requirements); ?>
						<div class="col-md-6">
							<div class="input-without-icon">
								<label><?php esc_html_e('Apartment, suite, unit etc. (optional)', 'listeo_core'); ?><?php if ($billing_address_2_required) { ?><i class="fas fa-asterisk"></i> <?php } ?></label>
								<input <?php if ($billing_address_2_required) { ?>required<?php } ?> type="text" name="billing_address_2" value="<?php esc_html_e($billing_address_2); ?>">

							</div>
						</div>
					<?php } ?>
					<?php if (in_array('billing_postcode', $address_fields)) {
						$billing_postcode_required = in_array('billing_postcode', $address_fields_requirements); ?>
						<div class="col-md-6">
							<div class="input-without-icon">
								<label><?php esc_html_e('Postcode/ZIP', 'listeo_core'); ?><?php if ($billing_postcode_required) { ?><i class="fas fa-asterisk"></i> <?php } ?></label>
								<input type="text" <?php if ($billing_postcode_required) { ?>required<?php } ?> name="billing_postcode" id="billing_postcode" value="<?php esc_html_e($billing_postcode); ?>">

							</div>
						</div>
					<?php } ?>
					<?php if (in_array('billing_city', $address_fields)) {
						$billing_city_required = in_array('billing_city', $address_fields_requirements); ?>
						<div class="col-md-6">
							<div class="input-without-icon">
								<label><?php esc_html_e('Town', 'listeo_core'); ?><?php if ($billing_city_required) { ?><i class="fas fa-asterisk"></i> <?php } ?></label>
								<input type="text" <?php if ($billing_city_required) { ?>required<?php } ?> name="billing_city" value="<?php esc_html_e($billing_city); ?>">

							</div>
						</div>
					<?php } ?>
					<?php if (in_array('billing_country', $address_fields)) {
						$billing_country_required = in_array('billing_country', $address_fields_requirements); ?>
						<div class="col-md-6">
							<div class="input-without-icon">
								<label><?php esc_html_e('Country', 'listeo_core'); ?><?php if ($billing_country_required) { ?><i class="fas fa-asterisk"></i> <?php } ?></label>
								<?php
								global $woocommerce;
								// get user meta billing_country
								if (is_user_logged_in()) {
									$billing_country = get_user_meta($current_user->ID, 'billing_country', true);
								} else {
									$billing_country = '';
								}

								$billing_country_args = array(
									'type' => 'country',

									'class' => ['address-field'],
									'validate' => ['country'],
									'default' => $billing_country,
									'return' => false
								);
								if ($billing_country_required) {
									$billing_country_args['required'] = true;
								}
								$billing_country_field = woocommerce_form_field('billing_country', $billing_country_args);

								// parse the field to add required attribute
								if ($billing_country_required && !empty($billing_country_field)) {
									$billing_country_field = str_replace('<select ', '<select required ', $billing_country_field);
								}
								echo $billing_country_field; ?>
							</div>
						</div>
					<?php } ?>
					<?php if (in_array('billing_state', $address_fields)) {
						$billing_state_required = in_array('billing_state', $address_fields_requirements); ?>
						<div class="col-md-6 state-field">

							<div class="input-without-icon">
								<label><?php esc_html_e('State', 'listeo_core'); ?><?php if ($billing_state_required) { ?><i class="fas fa-asterisk"></i> <?php } ?></label>
								<?php
								global $woocommerce;
								// get user meta billing_country
								if (is_user_logged_in()) {
									$billing_state = get_user_meta(get_current_user_id(), 'billing_state', true);
									$billing_country = get_user_meta(get_current_user_id(), 'billing_country', true);
								} else {
									$billing_state = isset($_POST['billing_state']) ? $_POST['billing_state'] : '';
									$billing_country = isset($_POST['billing_country']) ? $_POST['billing_country'] : '';
								}
								// Generate the appropriate field based on country
								$states = WC()->countries->get_states($billing_country);

								if (empty($states)) {
									// No states - show text input
									echo '<input type="text" name="billing_state" id="billing_state" class="input-text" value="' . esc_attr($billing_state) . '" placeholder="' . __('State', 'listeo_core') . '"' . ($billing_state_required ? ' required' : '') . '>';
								} else {
									// Has states - show select
									echo '<select name="billing_state" id="billing_state" class="address-field"' . ($billing_state_required ? ' required' : '') . '>';
									echo '<option value="">' . __('Select a state…', 'listeo_core') . '</option>';

									foreach ($states as $state_code => $state_name) {
										$selected = selected($billing_state, $state_code, false);
										echo '<option value="' . esc_attr($state_code) . '"' . $selected . '>' . esc_html($state_name) . '</option>';
									}

									echo '</select>';
								}

								?>


							</div>
						</div>

					<?php } ?>
				<?php endif; ?>

				<!-- Custom fields for booking form -->
				<div class="listeo-custom-booking-fields-wrapper">
					<?php
					// Allow plugins to add custom fields (e.g., selected resource display)
					do_action('listeo_booking_confirmation_custom_fields', $data);

					echo listeo_get_extra_booking_fields($listing_type);
					?>

				</div>
				<!-- /// -->
				<div class="col-md-12 margin-top-15">
					<label><?php esc_html_e('Message', 'listeo_core'); ?></label>
					<textarea maxlength="200" name="message" placeholder="<?php esc_html_e('Your short message to the listing owner (optional)', 'listeo_core'); ?>" id="booking_message" cols="20" rows="3"><?php echo $message; ?></textarea>
				</div>

				<?php if (!is_user_logged_in()) :

					$booking_without_login = get_option('listeo_booking_without_login', 'off');
					if ($booking_without_login == 'on') { ?>
						<?php if ($recaptcha && $recaptcha_version == 'v2') { ?>

							<div class="col-md-6 checkboxes margin-bottom-15" style="padding: 0px 20px">
								<div class="g-recaptcha" data-sitekey="<?php echo get_option('listeo_recaptcha_sitekey'); ?>"></div>
							</div>
						<?php }

						if ($recaptcha && $recaptcha_version == 'hcaptcha'): ?>
							<div class="h-captcha" data-sitekey="<?php echo esc_attr(get_option('listeo_hcaptcha_sitekey')); ?>"></div>
						<?php endif;
						if ($recaptcha && $recaptcha_version == 'turnstile'): ?>
							<div class="cf-turnstile" data-theme="light" data-sitekey="<?php echo esc_attr(get_option('listeo_turnstile_sitekey')); ?>"></div>
						<?php endif;
						$privacy_policy_status = get_option('listeo_privacy_policy');

						if ($privacy_policy_status && function_exists('the_privacy_policy_link')) { ?>
							<div class="col-md-6 booking-registration-field">
								<div class="margin-top-10 checkboxes margin-bottom-10">
									<input type="checkbox" id="privacy_policy_booking" name="privacy_policy">
									<label for="privacy_policy_booking"><?php esc_html_e('I agree to the', 'listeo_core'); ?> <a target="_blank" href="<?php echo get_privacy_policy_url(); ?>"><?php esc_html_e('Privacy Policy', 'listeo_core'); ?></a> </label>

								</div>
							</div>
						<?php } ?>

						<?php
						$terms_and_condition_status = get_option('listeo_terms_and_conditions_req');
						$terms_and_condition_status_page = get_option('listeo_terms_and_conditions_page');

						if ($terms_and_condition_status) { ?>
							<div class="col-md-6 booking-registration-field">
								<div class="margin-top-10 checkboxes margin-bottom-10">
									<input type="checkbox" id="terms_and_conditions_booking" name="terms_and_conditions">
									<label for="terms_and_conditions_booking"><?php esc_html_e('I agree to the', 'listeo_core'); ?> <a target="_blank" href="<?php echo get_permalink($terms_and_condition_status_page); ?>"><?php esc_html_e('Terms and Conditions', 'listeo_core'); ?></a> </label>

								</div>
							</div>
				<?php }
					}
				endif;
				?>

				<?php
				/**
				 * Hook: listeo_before_booking_confirmation_form_end
				 *
				 * Allows you to add custom form fields before the form ends
				 * This is useful for adding custom fields that will be submitted with the form
				 *
				 * @param object $data Booking data object
				 */
				do_action('listeo_before_booking_confirmation_form_end', $data);
				?>
		</form>

		<?php
		/**
		 * Hook: listeo_after_booking_confirmation_form
		 *
		 * Allows you to add custom content after the form but before the submit button
		 * This is useful for adding GDPR text, terms acceptance, or other notices
		 *
		 * @param object $data Booking data object
		 */
		do_action('listeo_after_booking_confirmation_form', $data);
		?>
	</div>


	<a href="#" class="button booking-confirmation-btn margin-top-20">
		<div class="loadingspinner"></div><span class="book-now-text">
			<?php
			if (get_option('listeo_disable_payments') || $payment_option ==  'pay_cash' || $payment_option ==  'pay_maybe') {
				($instant_booking == 'on') ? esc_html_e('Confirm', 'listeo_core') : esc_html_e('Confirm', 'listeo_core');
			} else {
				($instant_booking == 'on') ? esc_html_e('Confirm and Pay', 'listeo_core') : esc_html_e('Confirm and Book', 'listeo_core');
			}
			?></span>
	</a>

</div>


<!-- Sidebar
		================================================== -->
<div class="col-lg-4 col-md-4 margin-top-0 margin-bottom-60">

	<!-- Booking Summary -->
	<div class="listing-item-container compact order-summary-widget">
		<div class="listing-item">
			<?php if (isset($image[0])) { ?>
				<img src="<?php echo $image[0]; ?>" alt="">
			<?php } ?>


			<div class="listing-item-content">
				<?php
				// Use the new combined rating display function
				$rating_data = listeo_get_rating_display($data->listing_id);
				$rating = $rating_data['rating'];

				if (isset($rating) && $rating > 0) : ?>
					<div class="numerical-rating" data-rating="<?php $rating_value = esc_attr(round($rating, 1));
																printf("%0.1f", $rating_value); ?>"></div>
				<?php endif; ?>
				<h3><?php echo get_the_title($data->listing_id); ?></h3>
				<?php if (get_the_listing_address($data->listing_id)) { ?><span><?php the_listing_address($data->listing_id); ?></span><?php } ?>
			</div>
		</div>
	</div>
	<div class="boxed-widget opening-hours summary margin-top-0">
		<h3><i class="fa fa-calendar-check"></i> <?php esc_html_e('Booking Summary', 'listeo_core'); ?></h3>
		<?php

		$currency_abbr = get_option('listeo_currency');
		$currency_postion = get_option('listeo_currency_postion');
		$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
		$_rental_timepicker = get_post_meta($data->listing_id, '_rental_timepicker', true);
		?>
		<ul id="booking-confirmation-summary">

			<?php

			if (listeo_get_booking_type($data->listing_id) == 'tickets') {

				/**
				 * Filter to override the event date row on the booking confirmation page.
				 * Booking Plus uses this to display the actually selected occurrence date
				 * (from $data->date_start) instead of the listing's _event_date anchor.
				 *
				 * @param string $html Override HTML. Empty to use legacy rendering.
				 * @param object $data Booking data object.
				 */
				$bp_date_row = apply_filters( 'listeo_event_booking_summary_date', '', $data );
				if ( ! empty( $bp_date_row ) ) {
					echo $bp_date_row;
				} else {
			?>
				<li id='booking-confirmation-summary-date'>
					<?php esc_html_e('Date Start', 'listeo_core'); ?>
					<span>
						<?php
						$event_start_raw = get_post_meta($data->listing_id, '_event_date', true);
						$event_start_timestamp = (int) get_post_meta($data->listing_id, '_event_date_timestamp', true);

						if (empty($event_start_timestamp) && !empty($event_start_raw)) {
							$event_start_timestamp = Listeo_Core_Post_Types::parse_event_date_to_timestamp($event_start_raw);
						}

						if (!empty($event_start_timestamp)) {
							echo esc_html(date_i18n(get_option('date_format'), $event_start_timestamp));
							if (!empty($event_start_raw) && strpos($event_start_raw, ':') !== false) {
								echo esc_html__(' at ', 'listeo_core');
								echo esc_html(date_i18n(get_option('time_format'), $event_start_timestamp));
							}
						}

						?>

					</span>
				</li>
				<?php
				$meta_value = get_post_meta($data->listing_id, '_event_date_end', true);

				if (isset($meta_value) && !empty($meta_value)) : ?>
					<li id='booking-confirmation-summary-date'>
						<?php esc_html_e('Date End', 'listeo_core'); ?>
						<span>
							<?php
							$event_end_raw = get_post_meta($data->listing_id, '_event_date_end', true);
							$event_end_timestamp = (int) get_post_meta($data->listing_id, '_event_date_end_timestamp', true);

							if (empty($event_end_timestamp) && !empty($event_end_raw)) {
								$event_end_timestamp = Listeo_Core_Post_Types::parse_event_date_to_timestamp($event_end_raw);
							}

							if (!empty($event_end_timestamp)) {
								echo esc_html(date_i18n(get_option('date_format'), $event_end_timestamp));
								if (!empty($event_end_raw) && strpos($event_end_raw, ':') !== false) {
									echo esc_html__(' at ', 'listeo_core');
									echo esc_html(date_i18n(get_option('time_format'), $event_end_timestamp));
								}
							}
							?>
						</span>
					</li>
				<?php endif; ?>
				<?php } // end legacy else for listeo_event_booking_summary_date ?>
			<?php } else {

				// rental/service
			?>

				<li id='booking-confirmation-summary-date'>
					<?php esc_html_e('Date', 'listeo_core'); ?>


					<span>
						<?php
						$_data_start_ts = strtotime($data->date_start);
						$_data_end_ts   = isset($data->date_end) ? strtotime($data->date_end) : $_data_start_ts;

						// Even when the listing has `_rental_timepicker`
						// enabled we only render the time portion if the
						// captured booking actually carries one. Falling
						// back to date-only when both ends are 00:00
						// avoids the "May 30, 2026 12:00 am" display
						// when the time was never captured / got dropped
						// upstream.
						$_start_hm = date('H:i', $_data_start_ts);
						$_end_hm   = date('H:i', $_data_end_ts);
						$_has_real_time = ('00:00' !== $_start_hm) || ('00:00' !== $_end_hm);

						if ($_rental_timepicker && $_has_real_time) {
							$clock_format = get_option('listeo_clock_format', '12');
							$time_format = ($clock_format == '24') ? 'H:i' : 'g:i a';

							echo date_i18n(get_option('date_format') . ' ' . $time_format, $_data_start_ts);
							if (isset($data->date_end) && $data->date_start != $data->date_end) {
								echo '<b> - </b>' . date_i18n(get_option('date_format') . ' ' . $time_format, $_data_end_ts);
							}
						} else {
							echo date_i18n(get_option('date_format'), $_data_start_ts);
							if (isset($data->date_end) && $data->date_start != $data->date_end) {
								echo '<b> - </b>'
									. date_i18n(get_option('date_format'), $_data_end_ts);
							}
						} ?>

					</span>
				</li>

				<?php
				//var_dump($data);

				if (isset($data->_hour) && !empty($data->_hour)) {
					$clock_format = get_option('listeo_clock_format');

					// Format start time
					$formatted_hour = $data->_hour;
					if ($clock_format == 12) {
						$time_obj = DateTime::createFromFormat('H:i', $data->_hour);
						if ($time_obj) {
							$formatted_hour = $time_obj->format('h:i A');
						}
					}

					// Format end time if exists
					$formatted_hour_end = '';
					if (isset($data->_hour_end)) {
						$formatted_hour_end = $data->_hour_end;
						if ($clock_format == 12) {
							$time_obj = DateTime::createFromFormat('H:i', $data->_hour_end);
							if ($time_obj) {
								$formatted_hour_end = $time_obj->format('h:i A');
							}
						}
					}
				?>
					<li id='booking-confirmation-summary-time'>
						<?php esc_html_e('Time', 'listeo_core'); ?> <span><?php echo $formatted_hour;
																			if (isset($data->_hour_end)) {
																				echo ' - ';
																				echo $formatted_hour_end;
																			}; ?></span>
					</li>
				<?php } ?>
				<?php if (listeo_get_booking_type($data->listing_id) == 'event') { ?>
					<li id='booking-confirmation-summary-time'>
						<?php

						$event_start = get_post_meta($data->listing_id, '_event_date', true);

						if (!empty($event_start)) {
							$event_start_date = explode(' ', $event_start, 2);

							if (isset($event_start_date[1]) && !empty($event_start_date[1])) {
								$time = str_replace('-', '', $event_start_date[1]);
								$event_hour_start = date_i18n(get_option('time_format'), strtotime($time));
							}
						}

						$event_end  = get_post_meta($data->listing_id, '_event_date_end', true);

						if (!empty($event_end)) {
							$event_start_end = explode(' ', $event_end, 2);

							if (isset($event_start_end[1]) && !empty($event_start_end[1])) {
								$time = str_replace('-', '', $event_start_end[1]);
								$event_hour_end = date_i18n(get_option('time_format'), strtotime($time));
							}
						}
						?>
						<?php esc_html_e('Time', 'listeo_core'); ?>
						<span><?php echo $event_hour_start; ?> <?php if (isset($event_hour_end) && $event_hour_start != $event_hour_end) echo '<b> - </b>' . $event_hour_end; ?></span>
					</li>
				<?php } ?>
			<?php } ?>
			<?php
			$children = get_post_meta($data->listing_id, "_children", true);
			$animals	= get_post_meta($data->listing_id, "_animals", true);
			$max_guests = get_post_meta($data->listing_id, "_max_guests", true);
			$normal_price = (float) get_post_meta($data->listing_id, '_normal_price', true);
			$weekend_price = (float) get_post_meta($data->listing_id, '_weekday_price', true);
			$children_discount = (float) get_post_meta($data->listing_id, '_children_price', true);
			$reservation_price = (float) get_post_meta($data->listing_id, '_reservation_price', true);
			$_count_per_guest = get_post_meta($data->listing_id, '_count_per_guest', true);
			$animal_fee = (float) get_post_meta($data->listing_id, '_animal_fee', true);
			$animal_fee_type = get_post_meta($data->listing_id, '_animal_fee_type', true);
			if (get_option('listeo_remove_guests')) {
				$max_guests = 1;
			}
			if (!get_option('listeo_remove_guests')) : ?>

				<?php if (isset($data->adults)) { ?>
					<li id='booking-confirmation-summary-guests'>
						<?php
						//if enabled option children, use Adults instead of Guests text
						if ($children) {
							esc_html_e('Adults', 'listeo_core');
						} else {
							esc_html_e('Guests', 'listeo_core');
						} ?>
						<span>
							<?php if (isset($data->adults)) echo $data->adults; ?>

						</span>
					</li>
				<?php }
				if (isset($data->children) && $data->children > 0) { ?>
					<li id='booking-confirmation-summary-guests'>
						<?php esc_html_e('Children (ages 2–12)', 'listeo_core'); ?>
						<span><?php if (isset($data->children)) echo $data->children; ?></span>
					</li>
				<?php }
				//infants
				if (isset($data->infants) && $data->infants > 0) { ?>
					<li id='booking-confirmation-summary-guests'>
						<?php esc_html_e('Infants (ages 0–2)', 'listeo_core'); ?>
						<span><?php if (isset($data->infants)) echo $data->infants; ?></span>
					</li>
				<?php }
				if (isset($data->animals) && $data->animals > 0) { ?>
					<li id='booking-confirmation-summary-guests'>
						<?php esc_html_e('Animals', 'listeo_core'); ?>
						<span><?php if (isset($data->animals)) echo $data->animals; ?>
							<?php if ($animal_fee_type == 'one_time') {
								echo ' x ' . listeo_output_price($animal_fee);
							} else if ($animal_fee_type == 'per_night') {
								echo ' x ' . listeo_output_price($animal_fee) . '/night';
							} ?>
						</span>
					</li>
				<?php }

			endif;

			/**
			 * Filter to replace the ticket summary block on the booking confirmation page.
			 * Booking Plus uses this to render a per-tier breakdown of selected ticket types.
			 *
			 * @param string $html Override HTML. Empty string to use the legacy single-line summary.
			 * @param object $data Booking data object.
			 */
			$bp_summary = apply_filters( 'listeo_event_booking_summary', '', $data );
			if ( ! empty( $bp_summary ) ) {
				echo $bp_summary; // already escaped by callee
			} elseif (isset($data->tickets)) { ?>
				<li id='booking-confirmation-summary-tickets'>
					<?php esc_html_e('Tickets', 'listeo_core'); ?> <span><?php if (isset($data->tickets)) echo $data->tickets; ?></span>
				</li>
			<?php } ?>
			<?php if ($reservation_price > 0) : ?>
				<li class="booking-confirmation-reservation-price">
					<?php esc_html_e('Reservation Fee', 'listeo_core'); ?> <span><?php echo listeo_output_price($reservation_price) ?></span>
				</li>
			<?php endif; ?>


			<?php
			$decimals = get_option('listeo_number_decimals', 2);

			if ($data->price > 0) : ?>

				<?php
				/*
				 * Itemized breakdown ABOVE the fees ul. Renders
				 * accommodation, reservation fee, services, and pet
				 * fees so the customer can see why the total is what
				 * it is. Mandatory fees are still itemized in the
				 * existing `#booking-mandatory-fees` ul below — those
				 * lines are filtered out of the breakdown to avoid
				 * double-rendering. The breakdown is best-effort: if
				 * the booking type or required inputs can't be
				 * resolved, nothing renders and we fall through to
				 * the existing summary.
				 */
				$booking_type_for_breakdown = listeo_get_booking_type( $data->listing_id );
				$breakdown_result = null;
				if ( method_exists( 'Listeo_Core_Bookings_Calendar', 'calculate_price_breakdown' )
					&& isset( $data->date_start, $data->date_end ) ) {
					$bd_adults   = isset( $data->adults )   ? (int) $data->adults   : 1;
					$bd_children = isset( $data->children ) ? (int) $data->children : 0;
					$bd_animals  = isset( $data->animals )  ? (int) $data->animals  : 0;
					$bd_services = isset( $data->services ) ? $data->services       : false;
					$bd_coupon   = isset( $data->coupon )   ? $data->coupon         : false;

					if ( in_array( $booking_type_for_breakdown, array( 'event', 'tickets' ), true ) ) {
						$bd_multiply = isset( $data->tickets ) ? (int) $data->tickets : 1;
						$breakdown_result = Listeo_Core_Bookings_Calendar::calculate_price_breakdown(
							$data->listing_id, $data->date_start, $data->date_end,
							$bd_multiply, 0, 0, $bd_services, $bd_coupon
						);
					} elseif ( ! empty( $data->hour_start ) && ! empty( $data->hour_end ) ) {
						$breakdown_result = Listeo_Core_Bookings_Calendar::calculate_price_per_hour_breakdown(
							$data->listing_id, $data->date_start, $data->date_end,
							$data->hour_start, $data->hour_end,
							$bd_adults, $bd_children, $bd_animals, $bd_services, $bd_coupon
						);
					} elseif ( isset( $data->hours ) && $data->hours > 0 ) {
						$breakdown_result = Listeo_Core_Bookings_Calendar::calculate_price_by_hours_breakdown(
							$data->listing_id, $data->date_start, $data->date_end,
							(int) $data->hours,
							$bd_adults, $bd_children, $bd_animals, $bd_services, $bd_coupon
						);
					} else {
						$breakdown_result = Listeo_Core_Bookings_Calendar::calculate_price_breakdown(
							$data->listing_id, $data->date_start, $data->date_end,
							$bd_adults, $bd_children, $bd_animals, $bd_services, $bd_coupon
						);
					}
				}

				if ( is_array( $breakdown_result ) && ! empty( $breakdown_result['lines'] ) ) {
					// Use the canonical Listeo currency triple so the
					// amount column matches every other booking-related
					// price block on the page. Building it inline (rather
					// than echoing the server's `amount_formatted`) lets
					// us emit the raw symbol — `Listeo_Core_Listing::get_currency_symbol`
					// returns HTML entities like `&#36;` for `$`, so
					// running `esc_html` over the pre-formatted string
					// would double-encode the entity into literal `&#36;`.
					$currency_abbr     = get_option( 'listeo_currency' );
					$currency_postion  = get_option( 'listeo_currency_postion' );
					$currency_symbol   = Listeo_Core_Listing::get_currency_symbol( $currency_abbr );
					$bd_decimals       = get_option( 'listeo_number_decimals', 2 );

					echo '<ul class="booking-price-breakdown">';
					foreach ( $breakdown_result['lines'] as $bl ) {
						// Skip mandatory fees — the existing
						// #booking-mandatory-fees ul renders those
						// with the optional-fee checkbox UI.
						if ( isset( $bl['key'] ) && 'mandatory_fee' === $bl['key'] ) {
							continue;
						}
						$cls = 'booking-breakdown-line';
						if ( ! empty( $bl['key'] ) ) {
							$cls .= ' booking-breakdown-line--' . sanitize_html_class( $bl['key'] );
						}
						if ( ! empty( $bl['is_discount'] ) ) {
							$cls .= ' booking-breakdown-line--discount';
						}
						$amount_num = number_format_i18n( (float) $bl['amount'], (int) $bd_decimals );
						echo '<li class="' . esc_attr( $cls ) . '">'
							. '<div class="booking-breakdown-line-text">'
							. '<span class="booking-breakdown-line-label">' . esc_html( $bl['label'] ) . '</span>';
						if ( ! empty( $bl['sublabel'] ) ) {
							echo '<span class="booking-breakdown-line-sublabel">' . esc_html( $bl['sublabel'] ) . '</span>';
						}
						echo '</div>'
							. '<span class="booking-breakdown-line-amount">';
						if ( 'before' === $currency_postion ) {
							echo $currency_symbol . esc_html( $amount_num );
						} else {
							echo esc_html( $amount_num ) . $currency_symbol;
						}
						echo '</span></li>';
					}
					echo '</ul>';
				}
				?>

			<?php endif; ?>

			<?php if (!get_option('listeo_remove_coupons')) : ?>
				<li class="booking-confirmation-coupons">
					<div class="coupon-booking-widget-wrapper">
						<a id="listeo-coupon-link" href="#"><?php esc_html_e('Have a coupon?', 'listeo_core'); ?></a>
						<div class="coupon-form">

							<input type="text" name="apply_new_coupon" class="input-text" id="apply_new_coupon" value="" placeholder="<?php esc_html_e('Coupon code', 'listeo_core'); ?>">
							<a href="#" class="button listeo-booking-widget-apply_new_coupon" name="apply_new_coupon"><?php esc_html_e('Apply', 'listeo_core'); ?></a>
						</div>
						<div id="coupon-widget-wrapper-output">
							<div class="notification error closeable"></div>
							<div class="notification success closeable" id="coupon_added"><?php esc_html_e('This coupon was added', 'listeo_core'); ?></div>
						</div>
						<div id="coupon-widget-wrapper-applied-coupons">
							<?php
							if (isset($data->coupon) && !empty($data->coupon)) {
								$coupons = explode(',', $data->coupon);
								foreach ($coupons as $key => $value) {
									echo "<span data-coupon='{$value}'>{$value} <i class=\"fa fa-times\"></i></span>";
								}
							}
							?>
						</div>
					</div>


				</li>
			<?php endif; ?>

			<?php if ($data->price > 0) : ?>

				<?php
				// Build a booking context for the repeatable fees engine
				// so each row shows its real, scaled amount (per-night,
				// per-guest, percent of subtotal, etc.) instead of the
				// raw `price` field.
				$fee_context = array(
					'listing_type' => isset( $listing_type ) ? $listing_type : get_post_meta( $data->listing_id, '_listing_type', true ),
					'subtotal'     => isset( $data->base_price ) ? (float) $data->base_price : (float) $data->price,
				);
				if ( isset( $data->date_start ) && isset( $data->date_end ) ) {
					try {
						$_first = new DateTime( $data->date_start );
						$_last  = new DateTime( $data->date_end );
						$_nights = (int) $_last->diff( $_first )->format( '%a' );
						$fee_context['nights']     = max( 1, $_nights );
						$fee_context['date_start'] = $data->date_start;
					} catch ( Exception $e ) {
						$fee_context['nights'] = 1;
					}
				}
				if ( isset( $data->adults ) ) {
					$fee_context['guests'] = (int) $data->adults + ( isset( $data->children ) ? (int) $data->children : 0 );
				} elseif ( isset( $data->tickets ) ) {
					$fee_context['guests']  = (int) $data->tickets;
					$fee_context['tickets'] = (int) $data->tickets;
				}
				if ( isset( $data->hours ) ) {
					$fee_context['hours'] = (int) $data->hours;
				}

				// Render optional fees with a context that includes every
				// optional id by default — so the customer sees them
				// checked, knows the price as of "include everything",
				// and can opt out from there. The JS layer captures the
				// checkbox state on toggle and triggers a recalc.
				$_all_optional_ids = array();
				if ( function_exists( 'listeo_get_listing_fees' ) ) {
					foreach ( listeo_get_listing_fees( $data->listing_id ) as $_f ) {
						if ( ! empty( $_f['optional'] ) ) {
							$_all_optional_ids[] = $_f['id'];
						}
					}
				}
				$fee_context['accepted_optional_fees'] = $_all_optional_ids;

				$applicable_fees = function_exists( 'listeo_get_applicable_listing_fees' )
					? listeo_get_applicable_listing_fees( $data->listing_id, $fee_context )
					: array();

				if ( ! empty( $applicable_fees ) ) {
					$currency_abbr    = get_option( 'listeo_currency' );
					$currency_postion = get_option( 'listeo_currency_postion' );
					$currency_symbol  = Listeo_Core_Listing::get_currency_symbol( $currency_abbr );
					$currency_args    = array(
						'symbol'   => $currency_symbol,
						'position' => $currency_postion,
						'decimals' => $decimals,
					);
					echo "<ul id='booking-mandatory-fees'>";
					foreach ( $applicable_fees as $fee ) {
						$line = listeo_format_fee_line( $fee, $fee_context, $currency_args );
						$is_optional = ! empty( $fee['optional'] );
						?>
						<li class="listeo-fee-row<?php echo $is_optional ? ' listeo-fee-row-optional' : ''; ?>" data-fee-id="<?php echo esc_attr( $fee['id'] ); ?>" data-fee-amount="<?php echo esc_attr( $line['amount'] ); ?>">
							<p>
								<?php if ( $is_optional ) : ?>
									<label class="listeo-optional-fee-toggle">
										<input type="checkbox" class="listeo-optional-fee-checkbox" name="optional_fees[]" value="<?php echo esc_attr( $fee['id'] ); ?>" checked>
										<?php echo esc_html( $line['title'] ); ?>
									</label>
								<?php else : ?>
									<?php echo esc_html( $line['title'] ); ?>
								<?php endif; ?>
								<?php if ( ! empty( $line['frequency_label'] ) ) : ?>
									<span class="listeo-fee-freq"><?php echo esc_html( $line['frequency_label'] ); ?></span>
								<?php endif; ?>
							</p>
							<strong><?php echo esc_html( $line['amount_formatted'] ); ?></strong>
						</li>
						<?php
					}
					echo "</ul>";
				}
				?>

				<?php
				// Display base price before commission if commission was added
				$base_price_sale = null; // Initialize variable for later use
				$show_commission_to_users = get_option('listeo_show_commission_to_users', 'on');
				if (isset($data->commission_added) && $data->commission_added && $show_commission_to_users == 'on') :
					// Calculate base price (total minus commission)
					$base_price = $data->price - $data->commission_amount;
					if (isset($data->price_sale) && !empty($data->price_sale)) {
						$base_price_sale = $data->price_sale - $data->commission_amount;
					}
				?>
					<li class="booking-base-cost"><?php esc_html_e('Subtotal', 'listeo_core'); ?><span>
							<?php if ($currency_postion == 'before') {
								echo $currency_symbol . ' ';
							}
							echo number_format_i18n($base_price, $decimals);
							if ($currency_postion == 'after') {
								echo ' ' . $currency_symbol;
							} ?></span></li>

					<li class="booking-commission-fee"><?php esc_html_e('Site Fee', 'listeo_core'); ?><span>
							<?php if ($currency_postion == 'before') {
								echo $currency_symbol . ' ';
							}
							echo number_format_i18n($data->commission_amount, $decimals);
							if ($currency_postion == 'after') {
								echo ' ' . $currency_symbol;
							} ?></span></li>
				<?php endif; ?>

				<li class="total-costs <?php if (isset($data->price_sale)) : ?> estimated-with-discount<?php endif; ?>" data-price="<?php echo esc_attr($data->price); ?>"><?php esc_html_e('Total Cost', 'listeo_core'); ?><span>
						<?php if ($currency_postion == 'before') {
							echo $currency_symbol . ' ';
						}
						echo number_format_i18n($data->price, $decimals);
						if ($currency_postion == 'after') {
							echo ' ' . $currency_symbol;
						} ?></span></li>
			<?php endif; ?>
			<?php if (isset($data->price_sale)) : ?>

				<?php $decimals = get_option('listeo_number_decimals', 2); ?>
				<?php
				// If commission was added and there's a discount, show the breakdown
				if (isset($data->commission_added) && $data->commission_added && isset($base_price_sale) && $show_commission_to_users == 'on') : ?>
					<li class="booking-base-cost-discount"><strong><?php esc_html_e('Discounted Subtotal', 'listeo_core'); ?></strong><span>
							<?php if ($currency_postion == 'before') {
								echo $currency_symbol . ' ';
							}
							echo number_format_i18n($base_price_sale, $decimals);
							if ($currency_postion == 'after') {
								echo ' ' . $currency_symbol;
							} ?></span></li>
				<?php endif; ?>

				<li class="total-discounted_costs"><strong><?php esc_html_e('Final Cost', 'listeo_core'); ?></strong><span>
						<?php if ($currency_postion == 'before') {
							echo $currency_symbol . ' ';
						}
						echo number_format_i18n($data->price_sale, $decimals);
						if ($currency_postion == 'after') {
							echo ' ' . $currency_symbol;
						} ?></span></li>

			<?php else : ?>
				<li style="display:none;" class="total-discounted_costs"><?php esc_html_e('Final Cost', 'listeo_core'); ?><span> </span></li>
			<?php endif; ?>
		</ul>

	</div>
	<!-- Booking Summary / End -->

</div>
</div>