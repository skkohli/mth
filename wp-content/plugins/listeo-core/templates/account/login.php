<div class="sign-in-form">
	<?php /*WPEngine compatibility*/
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
			} ?>
</div>