<?php
$payment_url = '';
$total_value = 0;
if (isset($data->order_id) && $data->order_id) {

	$order = wc_get_order($data->order_id);

	if ($order) {
		$payment_url = $order->get_checkout_payment_url();
		$total_value = 	$order->get_total();
	} 
}
$account_created = false;
if (isset($data->new_user_with_booking) && $data->new_user_with_booking == true) {
	$account_created = true;
}
$payment_method = '';
if (isset($data->listing_id) && !empty($data->listing_id)) {
	$payment_method = get_post_meta($data->listing_id, '_payment_option', true);
};

//echo '<div class="notification closeable success">' . $data->message . '</div>';
if (isset($data->error) && $data->error == true) {  ?>
	<div class="booking-confirmation-page booking-confrimation-error">
		<i class="fa fa-exclamation-circle"></i>
		<h2 class="margin-top-30"><?php esc_html_e('Oops, we have some problem.', 'listeo_core'); ?></h2>
		<p><?php echo  $data->message  ?></p>
	</div>

<?php } else { ?>

	<?php if ($data->status == "pay_to_confirm" && $payment_method != 'pay_cash') { ?>
		<div class="booking-confirmation-page">
			<?php if ($total_value == 0) { ?>
				<i class="fa fa-check-circle"></i>
			<?php } else { ?>
				<i class="fas fa-circle-notch fa-spin"></i>
			<?php } ?>


			<h2 class="margin-top-30"><?php esc_html_e('Thank you for your booking!', 'listeo_core'); ?></h2>
			<?php if ($payment_url) : ?>
				<meta http-equiv="refresh" content="3; url=<?php echo $payment_url; ?>">
				<p><?php printf(__('Please wait while you are redirected to payment page...or <a href="%s">Click Here</a> if you do not want to wait.', 'listeo_core'), $payment_url); ?>
				</p>
			<?php endif; ?>
		<?php } else { ?>

			<div class="booking-confirmation-page">
				<i class="fa fa-check-circle"></i>
				<h2 class="margin-top-30"><?php esc_html_e('Thank you for your booking!', 'listeo_core'); ?></h2>
				<p><?php echo $data->message  ?></p>

				<?php
				if ($account_created) {
					if (get_option('listeo_display_password_field')) { ?>
						<p class="registration-confirmation-info"><?php esc_html_e('You have been successfully registered, you can login now.', 'listeo_core'); ?></p>
					<?php } else { ?>
						<p class="registration-confirmation-info"> <?php esc_html_e('You have been successfully registered. Please check your email for the password.', 'listeo_core'); ?></p>

							<?php }
					}
					if ($payment_method == 'pay_cash') {
					} else {
						if (isset($payment_url) && $total_value > 0) {
							if (!get_option('listeo_disable_payments')) { ?>
								<a href="<?php echo esc_url($payment_url); ?>" class="button color"><?php esc_html_e('Pay now', 'listeo_core'); ?></a>
					<?php }
						}
					} ?>

					<?php $user_bookings_page = get_option('listeo_user_bookings_page');
					if ($user_bookings_page) : ?>
						<a href="<?php echo esc_url(get_permalink($user_bookings_page)); ?>" class="button"><?php esc_html_e('Go to My Bookings', 'listeo_core'); ?></a>
					<?php endif; ?>

			</div>

	<?php }
} ?>