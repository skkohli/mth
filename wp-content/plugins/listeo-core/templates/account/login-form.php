<ul class="tabs-nav">
	<li id="listeo-logintab-btn" class=""><a href="#tab1"><?php esc_html_e('Log In', 'listeo_core'); ?></a></li>
	<li id="listeo-registration-btn"><a href="#tab2"><?php esc_html_e('Register', 'listeo_core'); ?></a></li>
</ul>

<div class="tabs-container alt">

	<!-- Login -->
	<div class="tab-content" id="tab1" style="display: none;">
		<?php


		/*WPEngine compatibility*/
		if (defined('PWP_NAME')) { ?>
			<form method="post" id="login" class="login" action="<?php echo wp_login_url() . '?wpe-login=' . PWP_NAME; ?>">
			<?php } else { ?>
				<form method="post" id="login" class="login" action="<?php echo wp_login_url(); ?>">
				<?php } ?>

				<?php do_action('listeo_before_login_form'); ?>
				<p class="form-row form-row-wide">
					<label for="user_login">
						<i class="sl sl-icon-user"></i>
						<input placeholder="<?php esc_attr_e('Username/Email', 'listeo_core'); ?>" type="text" class="input-text" name="log" id="user_login" value="" />
					</label>
				</p>


				<p class="form-row form-row-wide">
					<label for="user_pass">
						<i class="sl sl-icon-lock"></i>
						<input placeholder="<?php esc_attr_e('Password', 'listeo_core'); ?>" class="input-text" type="password" name="pwd" id="user_pass" />

					</label>
					<span class="lost_password">
						<a href="<?php echo wp_lostpassword_url(); ?>"><?php esc_html_e('Lost Your Password?', 'listeo_core'); ?></a>
					</span>
				</p>

				<div class="form-row">
					<?php wp_nonce_field('listeo-ajax-login-nonce', 'login_security'); ?>
					<input type="submit" class="button border margin-top-5" name="login" value="<?php esc_html_e('Login', 'listeo_core') ?>" />
					<div class="checkboxes margin-top-10">
						<input name="rememberme" type="checkbox" id="remember-me" value="forever" />
						<label for="remember-me"><?php esc_html_e('Remember Me', 'listeo_core'); ?></label>

					</div>
				</div>
				<div class="notification error closeable" style="display: none; margin-top: 20px; margin-bottom: 0px;">
					<p></p>
				</div>
				</form>
	</div>

	<!-- Register -->
	<div class="tab-content" id="tab2" style="display: none;">
		<?php
		if (!get_option('users_can_register')) : ?>
			<div class="notification error closeable" style="display: block">
				<p><?php esc_html_e('Registration is disabled', 'listeo_core') ?></p>
			</div>
			<?php else :
			/*WPEngine compatibility*/
			if (defined('PWP_NAME')) { ?>
				<form enctype="multipart/form-data" class="register listeo-registration-form" id="register" action="<?php echo wp_registration_url() . '&wpe-login=';
																													echo PWP_NAME; ?>" method="post">
				<?php } else { ?>
					<form enctype="multipart/form-data" class="register listeo-registration-form" id="register" action="<?php echo wp_registration_url(); ?>" method="post">
					<?php }
					$sms_otp = get_option('listeo_otp_status');
					$email_otp = get_option('listeo_email_otp_verification');
					if ($sms_otp || $email_otp) {
						$otp = true;
						if($sms_otp) {
							$otp_type = 'sms';
						} else {
							$otp_type = 'email';
						}
					} else {
						$otp = false;
						$otp_type = false;
					}
					?>
					<div class="listeo-register-form-fields-container" data-otp-type="<?php echo esc_attr($otp_type); ?>">
						<?php
						$default_role = get_option('listeo_registration_form_default_role', 'guest');
					

						if (!get_option('listeo_registration_hide_role')) : ?>
							<div class="account-type">
								<div>
									<input type="radio" name="user_role" id="freelancer-radio" value="guest" class="account-type-radio" <?php if ($default_role == 'guest') { ?> checked <?php  } ?> />
									<label for="freelancer-radio"><i class="sl sl-icon-user"></i> <?php echo esc_html_x('Guest', 'User role', 'listeo_core'); ?></label>
								</div>
								<?php if (class_exists('WeDevs_Dokan')  && get_option('listeo_role_dokan') == 'seller') : ?>
									<div>
										<input type="radio" name="user_role" id="employer-radio" value="seller" class="account-type-radio" <?php if ($default_role == 'owner') { ?> checked <?php  } ?> />
										<label for="employer-radio"><i class="sl sl-icon-briefcase"></i> <?php esc_html_e('Owner', 'listeo_core') ?></label>
									</div>
								<?php else : ?>
									<div>
										<input type="radio" name="user_role" id="employer-radio" value="owner" class="account-type-radio" <?php if ($default_role == 'owner') { ?> checked <?php  } ?> />
										<label for="employer-radio"><i class="sl sl-icon-briefcase"></i> <?php esc_html_e('Owner', 'listeo_core') ?></label>
									</div>
								<?php endif; ?>
							</div>
							<div class="clearfix"></div>
						<?php endif; ?>
						<?php if (!get_option('listeo_registration_hide_username')) : ?>
							<p class="form-row form-row-wide">
								<label for="username2">
									<i class="sl sl-icon-user"></i>
									<input required placeholder="<?php esc_html_e('Username', 'listeo_core'); ?>" type="text" class="input-text" name="username" id="username2" value="" />
								</label>
							</p>
						<?php endif; ?>

						<p class="form-row form-row-wide">
							<label for="email">
								<i class="sl sl-icon-envelope-open"></i>
								<input required type="email" placeholder="<?php esc_html_e('Email Address', 'listeo_core'); ?>" class="input-text" name="email" id="email" value="" />
							</label>
						</p>

						<?php if (get_option('listeo_display_password_field')) : ?>
							<div class="password-field-wrapper">
								<p class="form-row form-row-wide" id="password-row">
									<label for="password1">
										<i class="sl sl-icon-lock"></i>
										<input required placeholder="<?php esc_html_e('Password', 'listeo_core'); ?>" class="input-text" type="password" name="password" id="password1" />
										<span class="pwstrength_viewport_progress"></span>
									</label>
								</p>
								<?php if (get_option('listeo_strong_password')) : ?>
									<div class="password-strength-requirements">
										<?php esc_html_e('Password must be at least', 'listeo_core'); ?> <strong class="req-length">8 <?php esc_html_e('characters', 'listeo_core'); ?></strong> <?php esc_html_e('and include:', 'listeo_core'); ?><br>
										<strong class="req-uppercase"><?php esc_html_e('One uppercase letter', 'listeo_core'); ?></strong>, <strong class="req-number"><?php esc_html_e('one number', 'listeo_core'); ?></strong>, <?php esc_html_e('and', 'listeo_core'); ?> <strong class="req-special"><?php esc_html_e('one special character', 'listeo_core'); ?></strong>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if (get_option('listeo_display_first_last_name')) : ?>
							<p class="form-row form-row-wide">
								<label for="first-name">
									<i class="sl sl-icon-pencil"></i>
									<input <?php if (get_option('listeo_display_first_last_name_required')) { ?>required <?php } ?> placeholder="<?php esc_html_e('First Name', 'listeo_core'); ?>" type="text" name="first_name" id="first-name"></label>
							</p>

							<p class="form-row form-row-wide">
								<label for="last-name">
									<i class="sl sl-icon-pencil"></i>
									<input <?php if (get_option('listeo_display_first_last_name_required')) { ?>required <?php } ?> placeholder="<?php esc_html_e('Last Name', 'listeo_core'); ?>" type="text" name="last_name" id="last-name">
								</label>
							</p>
						<?php endif; ?>

						<?php if (get_option('listeo_otp_status') && get_option('listeo_twilio_sid')) { ?>
							<p class="form-row form-row-wide">
								<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.3/build/js/intlTelInput.min.js"></script>
								<script>
									document.addEventListener('DOMContentLoaded', (event) => {
										const input = document.querySelector("#phone");
										const form = document.querySelector("#register");
										const otpSubmitButton = document.querySelector("#otp_submit");

										if (input) {
											const iti = window.intlTelInput(input, {
												initialCountry: "auto",
												nationalMode: true,
												hiddenInput: () => "full_phone",
												geoIpLookup: callback => {
													fetch("https://ipapi.co/json")
														.then(res => res.json())
														.then(data => callback(data.country_code))
														.catch(() => callback("us"));
												},
												utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.3/build/js/utils.js" // just for formatting/placeholders etc
											});
											const fullPhoneInput = document.querySelector("input[name='full_phone']");
											// check if the number is valid on focus out
											input.addEventListener('blur', () => {

												if (!iti.isValidNumber()) {
													// handle error
													input.classList.add("error");
												} else {
													// if number is valid, submit the form
													input.classList.add("validphone");
													fullPhoneInput.value = iti.getNumber();
												}
											});
											otpSubmitButton.addEventListener('click', (event) => {
												event.preventDefault();
												if (!iti.isValidNumber()) {
													// handle error
													input.classList.add("error");

												} else {
													// if number is valid, submit the form
													input.classList.add("validphone");
													fullPhoneInput.value = iti.getNumber();
												}
											});

											input.addEventListener('input', () => {
												if (input.classList.contains("error")) {
													input.classList.remove("error");
												}
											});
										};

										// listen to "keyup", but also "change" to update when the user selects a country


									});
								</script>
								<label for="phone">
									<i class="sl sl-icon-phone"></i>
									<input required type="tel" placeholder="<?php esc_html_e('Phone', 'listeo_core'); ?>" class="input-text" name="phone" id="phone" value="" />
								</label>
							</p>
						<?php } ?>


						<!-- //extra fields -->
						<div id="listeo-core-registration-fields">
							<?php echo listeo_get_extra_registration_fields($default_role); ?>
						</div>


						<!-- eof custom fields -->

						<?php if (!get_option('listeo_display_password_field')) : ?>
							<p class="form-row form-row-wide margin-top-30 margin-bottom-30"><?php esc_html_e('Note: Your password will be generated automatically and sent to your email address.', 'listeo_core'); ?>
							</p>
						<?php endif; ?>

						<?php $recaptcha = get_option('listeo_recaptcha');
						$recaptcha_version = get_option('listeo_recaptcha_version', 'v2');
						if ($recaptcha && $recaptcha_version == 'v2') { ?>

							<p class="form-row captcha_wrapper">
							<div class="g-recaptcha" data-sitekey="<?php echo get_option('listeo_recaptcha_sitekey'); ?>"></div>
							</p>
						<?php }

						if ($recaptcha && $recaptcha_version == 'v3') { ?>
							<input type="hidden" id="rc_action" name="rc_action" value="ws_register">
							<input type="hidden" id="token" name="token">
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
							<p class="form-row margin-top-10 checkboxes margin-bottom-10">
								<input type="checkbox" id="privacy_policy" name="privacy_policy">
								<label for="privacy_policy"><?php esc_html_e('I agree to the', 'listeo_core'); ?> <a target="_blank" href="<?php echo get_privacy_policy_url(); ?>"><?php esc_html_e('Privacy Policy', 'listeo_core'); ?></a> </label>

							</p>

						<?php } ?>

						<?php
						$terms_and_condition_status = get_option('listeo_terms_and_conditions_req');
						$terms_and_condition_status_page = get_option('listeo_terms_and_conditions_page');

						if ($terms_and_condition_status) { ?>
							<p class="form-row margin-top-10 checkboxes margin-bottom-10">
								<input type="checkbox" id="terms_and_conditions" name="terms_and_conditions">
								<label for="terms_and_conditions"><?php esc_html_e('I agree to the', 'listeo_core'); ?> <a target="_blank" href="<?php echo get_permalink($terms_and_condition_status_page); ?>"><?php esc_html_e('Terms and Conditions', 'listeo_core'); ?></a> </label>

							</p>
						<?php } ?>
					</div>
					<?php if (get_option('listeo_otp_status') || get_option('listeo_email_otp_verification')) { ?>
						<div class="otp_registration-wrapper" style="display: none">
							<?php do_action('listeo_before_otp_form'); ?>
							<div class="otp_registration">
								<h4><?php esc_html_e('Verification Code', 'listeo_core'); ?></h4>

								<p class="form-row margin-top-10 otp_code margin-bottom-10">

									<?php if($otp_type == 'email') esc_html_e('Please enter the 4 digit code sent to your email.', 'listeo_core'); ?>
									<?php if ($otp_type == 'sms') esc_html_e('Please enter the 4 digit code sent to your mobile number.', 'listeo_core'); ?>
									<span class="otp-countdown-valid-text"><?php esc_html_e('The code is valid for', 'listeo_core'); ?> </span> <span class="otp-countdown"></span>
									<a id="resend_otp" class="hidden" href=" #"><?php esc_html_e('Time has passed, click here to resend code', 'listeo_core'); ?></a>
								</p>



								<div id="listeo_otp-inputs">
									<input type=" tel" name="listeo_otp[token][code_1]" required="required" class="field__token" autocomplete="off" data-error="error_second_step_authentication_token" data-input-type="user">
									<input type="tel" name="listeo_otp[token][code_2]" required="required" class="field__token" autocomplete="off" data-error="error_second_step_authentication_token" data-input-type="user">
									<input type="tel" name="listeo_otp[token][code_3]" required="required" class="field__token" autocomplete="off" data-error="error_second_step_authentication_token" data-input-type="user">
									<input type="tel" name="listeo_otp[token][code_4]" required="required" class="field__token" autocomplete="off" data-error="error_second_step_authentication_token" data-input-type="user">

								</div>

								<span id="error_listeo_otp" class="hidden"><?php esc_html_e('Wrong authorization code. Try again later.', 'listeo_core'); ?></span>
								<span id="error_listeo_otp_time" class="hidden"><?php esc_html_e('The code has expired. Try again later.', 'listeo_core'); ?></span>
								<span id="error_listeo_otp_general" class="hidden"><?php esc_html_e('Something went wrong. Try again later.', 'listeo_core'); ?></span>


							</div>
							<input type="submit" class="button border fw margin-top-10" name="register" value="<?php esc_html_e('Register', 'listeo_core'); ?>" />
							<?php do_action('listeo_after_otp_form'); ?>
						</div>
					<?php } ?>

					<?php wp_nonce_field('listeo-ajax-login-nonce', 'register_security'); ?>

					<?php if (get_option('listeo_otp_status') || get_option('listeo_email_otp_verification')) { ?>
						<a class="button fw margin-top-10" id="otp_submit" name="otp"><?php esc_html_e('Register', 'listeo_core'); ?></a>
					<?php } else { ?>
						<input type="submit" class="button border fw margin-top-10" name="register" value="<?php esc_html_e('Register', 'listeo_core'); ?>" />
					<?php } ?>
					<div class="notification error closeable" style="display: none;margin-top: 20px; margin-bottom: 0px;">
						<p></p>
					</div>

					</form>

					<div class="listeo-custom-fields-wrapper">
						<?php echo listeo_get_extra_registration_fields('owner'); ?>
						<?php echo listeo_get_extra_registration_fields('guest'); ?>
					</div>
				<?php endif; ?>
	</div>

</div>
<?php if (function_exists('_wsl_e')) { ?>
	<div class="social-login-separator"><span><?php esc_html_e('Sign In with Social Network', 'listeo_core'); ?></span></div>
	<?php do_action('wordpress_social_login'); ?>

<?php } ?>

<?php
if (function_exists('mo_openid_initialize_social_login')) { ?>
	<div class="social-miniorange-container">
		<div class="social-login-separator"><span><?php esc_html_e('Sign In with Social Network', 'listeo_core'); ?></span></div><?php echo do_shortcode('[miniorange_social_login  view="horizontal" heading=""]');
																																	?>
	</div>
<?php } ?>

<?php
if (class_exists('NextendSocialLogin', false)) {
	echo NextendSocialLogin::renderButtonsWithContainer();
}
?>