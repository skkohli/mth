<!-- Content -->
<?php
if (isset($data)) :
	$commissions = $data->commissions;
	$payouts = $data->payouts;
endif;

$user_id = get_current_user_id();
$current_user = wp_get_current_user();

	$secret = false;
	$client_id = '';
	$stripe_connect_activation = get_option('listeo_stripe_connect_activation');

	if ($stripe_connect_activation) {
		$stripe_mode = get_option('listeo_stripe_connect_mode');

		$testmode       = (!empty($stripe_mode) && 'test' === $stripe_mode) ? true : false;

		if ($testmode) {
			$client_id = get_option('listeo_stripe_connect_test_client_id');
		} else {
			$client_id = get_option('listeo_stripe_connect_live_client_id');
		}
		$secret_key    = 'listeo_stripe_connect_' . ($testmode ? 'test_' : 'live_') . 'secret_key';
		$secret        = !empty(get_option($secret_key)) ? get_option($secret_key) : false;
	}

// Note: stripe-express-reauth handling moved to class-listeo-core-commissions.php
// in the listeo_wallet() shortcode function to allow redirect before any output

if(isset($_GET['stripe-express-setup']) && $_GET['stripe-express-setup']=='yes'){
	// Verify the return nonce to prevent CSRF
	$stored_nonce = get_user_meta($user_id, 'listeo_stripe_express_return_nonce', true);
	$incoming_nonce = isset($_GET['_nonce']) ? sanitize_text_field(wp_unslash($_GET['_nonce'])) : '';
	if (!empty($stored_nonce) && hash_equals($stored_nonce, $incoming_nonce)) {
		delete_user_meta($user_id, 'listeo_stripe_express_return_nonce');

		$account_id = get_user_meta($current_user->ID, 'listeo_stripe_express_account_id', true);
		if (!empty($secret) && !empty($account_id)) {
			$stripe = new \Stripe\StripeClient($secret);
			$account = $stripe->accounts->retrieve(
				$account_id,
				[]
			);

			if($account->charges_enabled){
				update_user_meta($user_id, 'vendor_connected', 1);
				update_user_meta($user_id, 'stripe_user_id', $account_id);
				update_user_meta($user_id, 'listeo_core_payment_type', 'stripe');
			}
		}
	}
}
if (get_option('listeo_stripe_connect_account_type') == 'standard') {
	if (isset($_GET['code'])) {

		if (!is_user_logged_in()) {
			// User must be logged in to complete OAuth — reject unauthenticated callbacks
			wp_safe_redirect(wp_login_url(get_permalink()));
			exit;
		}

		// Validate OAuth state parameter to prevent CSRF
		if (!isset($_GET['state'])) {
			wp_die(esc_html__('Missing OAuth state parameter.', 'listeo_core'));
		}
		$state_parts = explode(':', sanitize_text_field(wp_unslash($_GET['state'])), 2);
		if (count($state_parts) !== 2) {
			wp_die(esc_html__('Invalid OAuth state parameter.', 'listeo_core'));
		}
		list($state_nonce, $state_user_id) = $state_parts;
		$state_user_id = absint($state_user_id);
		if ($state_user_id !== get_current_user_id()) {
			wp_die(esc_html__('OAuth state user mismatch.', 'listeo_core'));
		}
		$expected_nonce = get_transient('listeo_stripe_oauth_state_' . $state_user_id);
		if (!$expected_nonce || !hash_equals($expected_nonce, $state_nonce)) {
			wp_die(esc_html__('OAuth state verification failed.', 'listeo_core'));
		}
		delete_transient('listeo_stripe_oauth_state_' . $state_user_id);

		$code = wc_clean(wp_unslash($_GET['code']));

		$token_request_body = array(
			'grant_type' => 'authorization_code',
			'client_id' => $client_id,
			'code' => $code,
			'client_secret' => $secret
		);

		$target_url = 'https://connect.stripe.com/oauth/token';
		$headers = array(
			'User-Agent'    => 'Listeo Stripe Split Pay',
			'Authorization' => 'Bearer ' . $secret,
		);
		$response    = wp_remote_post(
			$target_url,
			array(
				'sslverify'   => apply_filters('https_local_ssl_verify', false),
				'timeout'     => 70,
				'redirection' => 5,
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => $token_request_body
			)
		);

		if (!is_wp_error($response)) {
			$resp = (array) json_decode($response['body']);
			if (!isset($resp['error'])) {
				update_user_meta($user_id, 'vendor_connected', 1);
				update_user_meta($user_id, 'access_token', $resp['access_token']);
				update_user_meta($user_id, 'refresh_token', $resp['refresh_token']);
				update_user_meta($user_id, 'stripe_publishable_key', $resp['stripe_publishable_key']);
				update_user_meta($user_id, 'stripe_user_id', $resp['stripe_user_id']);
				update_user_meta($user_id, 'listeo_core_payment_type', 'stripe');
			} else {
				
				error_log($resp['error_description']);
				echo esc_html__('Stripe OAuth connection error', 'listeo_core');
			}
		}
	}
}
?>
<div class="row" id="waller-row" data-numberFormat=<?php if (wc_get_price_decimal_separator() == ',') {
														echo 'euro';
													} ?>>
	<?php
	$balance = 0;

	foreach ($commissions as $commission) {
		if ($commission['status'] == "unpaid") :

			$order = wc_get_order($commission['order_id']);
			if ($order) {
				$total = $order->get_total();
				$earning = (float) $total - $commission['amount'];
				$balance = $balance + $earning;
			}

		endif;
	}
	$currency_abbr = get_option('listeo_currency');

	$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);


	// if (wc_get_price_decimal_separator() == ',') {
	// 	$data->earnings_total = number_format( $data->earnings_total, 2, ',', ' ' );
	// }
	// echo $data->earnings_total;

	?>
	<!-- Item -->
	<div class="col-lg-4 col-md-6">
		<div class="dashboard-stat color-1">
			<div class="dashboard-stat-content wallet-totals">
				<h4><?php echo wc_price($balance, array('currency' => ' ', 'decimal_separator' => '.')); ?></h4> <span><?php esc_html_e('Withdrawable Balance', 'listeo_core') ?> <strong class="wallet-currency"><?php echo $currency_symbol; ?></strong></span>
			</div>
			<div class="dashboard-stat-icon"><svg viewBox="0 0 22.16 22.16" xmlns="http://www.w3.org/2000/svg">

					<g data-name="Camada 2" id="Camada_2">
						<g data-name="Camada 1" id="Camada_1-2">
							<path d="M4.35,7.79A2.2,2.2,0,0,0,5.2,8v.48a.16.16,0,0,0,0,.12.16.16,0,0,0,.12,0h.43a.16.16,0,0,0,.12,0,.16.16,0,0,0,0-.12V8a2.05,2.05,0,0,0,1.16-.45,1.22,1.22,0,0,0,.43-1,1.2,1.2,0,0,0-.17-.67,1.27,1.27,0,0,0-.53-.43,4.79,4.79,0,0,0-1-.31A5.36,5.36,0,0,1,5.19,5a.89.89,0,0,1-.34-.22.46.46,0,0,1-.11-.31A.47.47,0,0,1,5,4a1.06,1.06,0,0,1,.6-.14A1,1,0,0,1,6.12,4a.56.56,0,0,1,.27.37.2.2,0,0,0,.2.12h.65a.14.14,0,0,0,.14-.14,1.1,1.1,0,0,0-.18-.52,1.53,1.53,0,0,0-.49-.47A1.9,1.9,0,0,0,6,3.14V2.65a.15.15,0,0,0-.17-.17H5.36a.16.16,0,0,0-.12,0,.16.16,0,0,0,0,.12v.48a1.8,1.8,0,0,0-1.06.45,1.21,1.21,0,0,0-.39.91,1.12,1.12,0,0,0,.38.91,2.92,2.92,0,0,0,1.19.51q.49.13.74.22a1,1,0,0,1,.38.22.42.42,0,0,1,.12.31.51.51,0,0,1-.24.44,1.31,1.31,0,0,1-.72.16,1.22,1.22,0,0,1-.67-.16.74.74,0,0,1-.32-.39.31.31,0,0,0-.09-.1.22.22,0,0,0-.13,0H3.77a.15.15,0,0,0-.11,0,.14.14,0,0,0,0,.1,1.16,1.16,0,0,0,.2.6A1.44,1.44,0,0,0,4.35,7.79Z" />
							<path d="M5.58,11.16A5.58,5.58,0,1,0,0,5.58,5.59,5.59,0,0,0,5.58,11.16ZM5.58,1A4.57,4.57,0,1,1,1,5.58,4.57,4.57,0,0,1,5.58,1Z" />
							<path d="M17.84,16.52a4.75,4.75,0,0,0-1-.31,5.29,5.29,0,0,1-.68-.21.89.89,0,0,1-.34-.22.46.46,0,0,1-.11-.31.47.47,0,0,1,.22-.42,1.06,1.06,0,0,1,.6-.14,1,1,0,0,1,.57.15.56.56,0,0,1,.27.37.2.2,0,0,0,.2.12h.65a.14.14,0,0,0,.14-.14,1.1,1.1,0,0,0-.18-.52,1.53,1.53,0,0,0-.49-.47,1.91,1.91,0,0,0-.77-.27v-.49a.15.15,0,0,0-.17-.17h-.43a.17.17,0,0,0-.12,0,.16.16,0,0,0,0,.12v.47a1.81,1.81,0,0,0-1.06.45,1.21,1.21,0,0,0-.39.91,1.12,1.12,0,0,0,.38.91,2.93,2.93,0,0,0,1.19.51q.49.13.74.22a1,1,0,0,1,.38.22.42.42,0,0,1,.12.31.51.51,0,0,1-.24.44,1.31,1.31,0,0,1-.72.16,1.22,1.22,0,0,1-.67-.16.74.74,0,0,1-.32-.39.32.32,0,0,0-.09-.1.22.22,0,0,0-.13,0h-.62a.14.14,0,0,0-.11,0,.14.14,0,0,0,0,.1,1.16,1.16,0,0,0,.2.6,1.45,1.45,0,0,0,.54.47,2.22,2.22,0,0,0,.85.24v.48a.16.16,0,0,0,0,.12.17.17,0,0,0,.12,0h.43a.15.15,0,0,0,.17-.17V19a2.05,2.05,0,0,0,1.16-.45,1.22,1.22,0,0,0,.43-1,1.19,1.19,0,0,0-.17-.67A1.27,1.27,0,0,0,17.84,16.52Z" />
							<path d="M16.59,11a5.58,5.58,0,1,0,5.58,5.58A5.58,5.58,0,0,0,16.59,11Zm0,10.15a4.57,4.57,0,1,1,4.57-4.57A4.57,4.57,0,0,1,16.59,21.15Z" />
							<path d="M13,6.09h7.41L19.12,7.4a.51.51,0,0,0,.72.72L22,5.94h0a.49.49,0,0,0,.14-.35.51.51,0,0,0,0-.19A.52.52,0,0,0,22,5.22L19.83,3a.51.51,0,0,0-.72.72l1.32,1.32H13a.51.51,0,1,0,0,1Z" />
							<path d="M9.14,16.08H1.73L3,14.76A.51.51,0,0,0,2.33,14L.15,16.23a.5.5,0,0,0-.11.16.5.5,0,0,0,0,.39.5.5,0,0,0,.1.16h0l2.18,2.18A.51.51,0,1,0,3,18.41L1.73,17.09H9.14a.51.51,0,0,0,0-1Z" />
						</g>
					</g>
				</svg></div>
		</div>
	</div>
	<!-- Item -->
	<div class="col-lg-4 col-md-6">
		<div class="dashboard-stat color-3">
			<div class="dashboard-stat-content wallet-totals">
				<h4><?php echo wc_price($data->earnings_total, array('currency' => ' ', 'decimal_separator' => '.')); ?></h4> <span><?php esc_html_e('Total Earnings', 'listeo_core'); ?> <strong class="wallet-currency"><?php echo $currency_symbol; ?></strong></span>
			</div>
			<div class="dashboard-stat-icon"><svg id="Capa_1" style="enable-background:new 0 0 59.998 59.998;" version="1.1" viewBox="0 0 59.998 59.998" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
					<g>
						<path d="M16.586,10.028c0.407,1.178,1.504,1.97,2.73,1.97h7.431c1.226,0,2.323-0.792,2.73-1.97   c0.388-1.126,1.607-3.645,2.553-4.445c0.829-0.703,1.218-1.792,1.014-2.842c-0.2-1.033-0.921-1.849-1.928-2.182   c-3.001-0.995-5.663-0.66-7.698,0.967c-0.365,0.292-0.855,0.292-1.22,0C20.163-0.1,17.503-0.435,14.5,0.56   c-1.007,0.333-1.729,1.148-1.929,2.182c-0.204,1.05,0.185,2.139,1.014,2.842C14.656,6.49,16.296,9.188,16.586,10.028z    M14.535,3.123c0.036-0.187,0.162-0.521,0.594-0.664c0.93-0.308,1.797-0.461,2.599-0.461c1.234,0,2.312,0.364,3.221,1.091   c1.095,0.877,2.625,0.875,3.718,0c1.501-1.2,3.458-1.412,5.82-0.63c0.432,0.143,0.557,0.478,0.593,0.664   c0.067,0.347-0.061,0.696-0.343,0.935c-1.528,1.294-2.894,4.576-3.15,5.319c-0.128,0.372-0.466,0.622-0.839,0.622h-7.431   c-0.374,0-0.711-0.25-0.839-0.622c-0.417-1.208-2.272-4.194-3.599-5.319C14.596,3.818,14.467,3.469,14.535,3.123z" />
						<path d="M31.532,13.998c0-0.552-0.448-1-1-1c-0.186,0-0.351,0.065-0.5,0.153c-0.149-0.089-0.314-0.153-0.5-0.153h-12   c-0.552,0-1,0.448-1,1s0.448,1,1,1h12v1h-11c-0.552,0-1,0.448-1,1s0.448,1,1,1h11.925c0.896,0.746,2,1.173,3.179,1.173   c1.335,0,2.591-0.521,3.536-1.466c0.391-0.391,0.391-1.023,0-1.414c-0.391-0.391-1.023-0.391-1.414,0   c-1.13,1.13-3.088,1.133-4.226,0.014V13.998z" />
						<path d="M21.532,25.998c-6.411,0-14,8.131-14,15c0,0.552,0.448,1,1,1s1-0.448,1-1c0-5.71,6.729-13,12-13c0.552,0,1-0.448,1-1   S22.084,25.998,21.532,25.998z" />
						<path d="M49.532,31.998c-1.031,0-2.259,0.06-3.497,0.201c-1.347-2.466-3.123-4.587-5.365-6.304L33.468,21   c-1.191-0.914-3.068-2.002-5.246-2.002h-7.777c-1.924,0-3.816,0.655-5.3,1.822l-7.078,5.157c-7.377,5.8-9.712,15.964-5.551,24.168   c1.514,2.985,4.043,6.592,7.501,6.826c0.271,0.018,0.545,0.028,0.821,0.028h19.459c1.468,1.826,3.715,3,6.235,3   c2.898,0,5.434-1.555,6.838-3.869c1.787,0.555,3.978,0.869,6.162,0.869c4.809,0,10-1.529,10-4v-5.5h-0.079   c0.048-0.162,0.079-0.328,0.079-0.5v-6v-5.5C59.532,32.735,53.246,31.998,49.532,31.998z M57.456,35.489   c-0.021,0.021-0.038,0.042-0.067,0.065c-0.039,0.031-0.086,0.064-0.139,0.098c-0.066,0.042-0.146,0.086-0.233,0.13   c-0.071,0.036-0.145,0.073-0.23,0.11c-0.105,0.047-0.229,0.094-0.356,0.142c-0.102,0.038-0.205,0.076-0.321,0.115   c-0.146,0.048-0.314,0.096-0.483,0.143c-0.132,0.037-0.262,0.075-0.408,0.111c-0.19,0.047-0.404,0.09-0.617,0.133   c-0.161,0.033-0.314,0.067-0.488,0.098c-0.24,0.042-0.508,0.078-0.773,0.114c-0.181,0.025-0.35,0.053-0.543,0.075   c-0.315,0.036-0.662,0.061-1.008,0.086c-0.176,0.013-0.336,0.031-0.52,0.042c-0.546,0.03-1.124,0.048-1.741,0.048   s-1.195-0.018-1.741-0.048c-0.184-0.01-0.344-0.028-0.52-0.042c-0.346-0.026-0.693-0.051-1.008-0.086   c-0.193-0.022-0.362-0.05-0.543-0.075c-0.265-0.036-0.533-0.072-0.773-0.114c-0.174-0.031-0.327-0.065-0.488-0.098   c-0.213-0.044-0.427-0.087-0.617-0.133c-0.146-0.036-0.275-0.073-0.408-0.111c-0.169-0.047-0.336-0.095-0.483-0.143   c-0.116-0.038-0.219-0.077-0.321-0.115c-0.127-0.048-0.251-0.095-0.356-0.142c-0.085-0.037-0.159-0.074-0.23-0.11   c-0.087-0.045-0.167-0.089-0.233-0.13c-0.053-0.034-0.1-0.066-0.139-0.098c-0.027-0.021-0.042-0.04-0.062-0.06   c0.012-0.01,0.02-0.02,0.034-0.031c0.037-0.031,0.09-0.066,0.142-0.1c0.081-0.053,0.182-0.109,0.301-0.169   c0.065-0.033,0.129-0.065,0.205-0.098c0.079-0.035,0.17-0.07,0.261-0.106c0.108-0.042,0.226-0.084,0.351-0.126   c0.09-0.03,0.177-0.061,0.277-0.091c0.224-0.068,0.469-0.135,0.738-0.2c0.124-0.03,0.268-0.057,0.403-0.085   c0.163-0.035,0.33-0.069,0.507-0.101c0.195-0.035,0.402-0.068,0.616-0.1c1.127-0.17,2.476-0.288,4.085-0.288   C54.253,33.998,56.896,34.973,57.456,35.489z M42.207,43.716c0.09,0.038,0.183,0.074,0.277,0.111   c0.174,0.068,0.352,0.132,0.536,0.195c0.097,0.033,0.192,0.065,0.291,0.096c0.211,0.066,0.428,0.127,0.65,0.186   c0.078,0.021,0.153,0.044,0.232,0.063c0.303,0.076,0.613,0.146,0.931,0.208c0.066,0.013,0.135,0.023,0.202,0.036   c0.255,0.048,0.513,0.092,0.775,0.131c0.112,0.017,0.225,0.031,0.338,0.046c0.225,0.03,0.451,0.057,0.68,0.081   c0.12,0.012,0.24,0.024,0.36,0.035c0.236,0.021,0.473,0.037,0.711,0.051c0.106,0.006,0.212,0.014,0.318,0.019   c0.342,0.015,0.683,0.025,1.023,0.025c0.34,0,0.681-0.01,1.023-0.025c0.108-0.005,0.216-0.013,0.324-0.019   c0.235-0.014,0.47-0.03,0.704-0.05c0.123-0.011,0.246-0.023,0.368-0.036c0.225-0.023,0.448-0.05,0.67-0.08   c0.116-0.015,0.232-0.03,0.347-0.047c0.258-0.038,0.512-0.082,0.763-0.129c0.071-0.013,0.143-0.024,0.214-0.038   c0.316-0.063,0.625-0.132,0.926-0.207c0.084-0.021,0.164-0.045,0.247-0.067c0.217-0.058,0.43-0.118,0.636-0.183   c0.101-0.032,0.198-0.065,0.296-0.098c0.183-0.062,0.361-0.127,0.534-0.194c0.093-0.036,0.186-0.073,0.276-0.111   c0.18-0.076,0.351-0.156,0.516-0.238c0.052-0.026,0.111-0.048,0.161-0.074l0.006,3.524c-0.235,0.569-2.819,1.837-6.896,2.04   c-0.366,0.013-0.72,0.031-1.115,0.031c-2.203,0-4.379-0.338-6.041-0.932c-0.006-0.01-0.013-0.02-0.019-0.03   c-0.118-0.206-0.243-0.407-0.378-0.601c-0.02-0.029-0.043-0.056-0.064-0.085c-0.125-0.175-0.254-0.346-0.393-0.51   c-0.042-0.049-0.087-0.094-0.13-0.143c-0.123-0.139-0.247-0.276-0.379-0.406c-0.068-0.067-0.141-0.127-0.211-0.192   c-0.116-0.106-0.23-0.212-0.351-0.311c-0.012-0.01-0.022-0.021-0.034-0.03c0-0.391,0.001-1.26,0.002-2.353   c0.051,0.027,0.112,0.05,0.164,0.076C41.862,43.562,42.03,43.641,42.207,43.716z M28.996,54.998H10.838   c-0.231,0-0.461-0.008-0.688-0.023c-1.965-0.133-4.043-2.17-5.851-5.735c-3.731-7.356-1.626-16.479,4.974-21.669l7.078-5.157   c1.162-0.913,2.616-1.416,4.093-1.416h7.777c1.617,0,3.083,0.864,4.076,1.623l7.202,4.894c1.789,1.372,3.28,3.05,4.445,5.001   c-1.266,0.261-2.49,0.659-3.331,1.243c-0.023,0.016-0.046,0.032-0.069,0.048c-0.127,0.092-0.243,0.189-0.349,0.291   c-0.045,0.043-0.085,0.087-0.126,0.132c-0.079,0.087-0.155,0.175-0.217,0.269c-0.052,0.077-0.09,0.16-0.13,0.242   c-0.035,0.074-0.077,0.145-0.102,0.223c-0.055,0.169-0.089,0.346-0.089,0.534v5.5v3.589c-0.337-0.137-0.686-0.247-1.044-0.337   c-0.047-0.012-0.094-0.025-0.141-0.036c-0.35-0.082-0.707-0.144-1.072-0.178c-0.041-0.004-0.082-0.003-0.123-0.006   c-0.205-0.016-0.411-0.031-0.62-0.031c-0.11,0-0.216,0.012-0.325,0.016c-0.085,0.003-0.17,0.007-0.255,0.013   c-0.356,0.026-0.708,0.067-1.05,0.138c-0.007,0.001-0.013,0.001-0.02,0.003c-0.027,0.006-0.052,0.017-0.079,0.023   c-0.313,0.069-0.618,0.16-0.916,0.266c-0.108,0.038-0.216,0.076-0.322,0.118c-0.297,0.119-0.587,0.251-0.866,0.404   c-0.105,0.057-0.203,0.123-0.305,0.185c-0.195,0.119-0.385,0.244-0.568,0.379c-0.108,0.079-0.217,0.156-0.321,0.24   c-0.235,0.19-0.459,0.393-0.671,0.609c-0.093,0.094-0.178,0.194-0.266,0.293c-0.15,0.168-0.293,0.343-0.428,0.523   c-0.073,0.098-0.15,0.191-0.219,0.292c-0.176,0.257-0.333,0.527-0.478,0.804c-0.056,0.108-0.107,0.218-0.159,0.329   c-0.114,0.245-0.216,0.495-0.305,0.753c-0.026,0.075-0.058,0.146-0.081,0.222c-0.103,0.328-0.178,0.668-0.238,1.012   c-0.008,0.044-0.024,0.086-0.031,0.13c-0.008,0.054-0.008,0.112-0.015,0.166c-0.032,0.235-0.052,0.473-0.063,0.714   c-0.004,0.086-0.012,0.171-0.013,0.258c0,0.037-0.006,0.073-0.006,0.11c0,0.159,0.015,0.314,0.024,0.47   C28.594,53.333,28.735,54.201,28.996,54.998z M36.532,57.998c-3.285,0-5.958-2.654-5.996-5.929c0-0.078,0.002-0.155,0.005-0.233   c0.003-0.11,0.008-0.219,0.017-0.328c0.007-0.092,0.018-0.183,0.029-0.274c0.012-0.093,0.026-0.185,0.042-0.276   c0.045-0.26,0.103-0.517,0.182-0.766c0.008-0.027,0.02-0.052,0.029-0.078c0.084-0.251,0.185-0.496,0.302-0.733   c0.011-0.023,0.022-0.046,0.033-0.068c0.539-1.058,1.39-1.952,2.421-2.541c0.004-0.002,0.007-0.005,0.011-0.007   c0.234-0.133,0.48-0.245,0.73-0.345c0.065-0.026,0.131-0.049,0.196-0.072c0.222-0.079,0.448-0.148,0.681-0.199   c0.032-0.007,0.062-0.018,0.094-0.024c0.289-0.059,0.579-0.091,0.868-0.108c0.055-0.003,0.11-0.007,0.166-0.008   c0.295-0.009,0.589-0.001,0.879,0.033c0.017,0.002,0.033,0.005,0.049,0.008c0.306,0.038,0.609,0.099,0.907,0.185   c0.004,0.001,0.009,0.002,0.013,0.003c0.307,0.089,0.608,0.204,0.902,0.343c0.27,0.128,0.533,0.271,0.779,0.436l0.04,0.028   c0.035,0.024,0.067,0.051,0.101,0.075c0.166,0.119,0.325,0.245,0.478,0.38c0.037,0.032,0.073,0.066,0.109,0.099   c0.153,0.142,0.299,0.29,0.436,0.446c0.014,0.016,0.028,0.03,0.042,0.046c0.318,0.37,0.592,0.779,0.812,1.218   c0.051,0.1,0.102,0.201,0.147,0.304c0.076,0.175,0.142,0.352,0.201,0.531c0.004,0.013,0.01,0.026,0.014,0.039   c0.058,0.181,0.105,0.365,0.145,0.55c0.003,0.015,0.008,0.029,0.011,0.043c0.038,0.184,0.066,0.37,0.087,0.557   c0.002,0.017,0.006,0.032,0.008,0.049c0.021,0.204,0.032,0.409,0.032,0.616c0,0.212-0.012,0.423-0.034,0.633   c-0.003,0.031-0.01,0.06-0.014,0.091c-0.022,0.181-0.049,0.361-0.088,0.538c-0.004,0.02-0.011,0.039-0.015,0.059   c-0.043,0.19-0.094,0.378-0.156,0.564c-0.003,0.008-0.006,0.016-0.009,0.024c-0.066,0.194-0.142,0.385-0.228,0.574   C41.041,56.552,38.954,57.998,36.532,57.998z M49.532,54.998c-1.898,0-3.787-0.253-5.339-0.706   c0.001-0.002,0.001-0.004,0.001-0.006c0.097-0.324,0.168-0.658,0.224-0.998c0.013-0.081,0.026-0.161,0.037-0.243   c0.045-0.344,0.077-0.691,0.077-1.047c0-0.259-0.014-0.516-0.039-0.771c-0.009-0.092-0.025-0.181-0.037-0.272   c-0.017-0.129-0.029-0.259-0.052-0.386c1.517,0.28,3.279,0.429,5.128,0.429c2.39,0,5.845-0.305,8.004-1.259l0.006,3.188   C57.286,53.546,54.251,54.998,49.532,54.998z M49.532,42.998c-4.703,0-7.731-1.441-7.995-1.994c0.001-1.14,0.002-2.297,0.002-3.261   c0.025,0.011,0.055,0.02,0.081,0.031c0.239,0.102,0.489,0.198,0.755,0.286c0.014,0.005,0.028,0.01,0.043,0.014   c0.272,0.088,0.559,0.167,0.852,0.24c0.096,0.024,0.195,0.046,0.293,0.068c0.214,0.049,0.43,0.096,0.651,0.138   c0.107,0.021,0.213,0.041,0.321,0.06c0.243,0.043,0.487,0.081,0.734,0.117c0.078,0.011,0.155,0.024,0.233,0.035   c0.323,0.043,0.646,0.081,0.967,0.112c0.081,0.008,0.16,0.014,0.241,0.021c0.247,0.022,0.491,0.042,0.733,0.058   c0.105,0.007,0.207,0.013,0.31,0.019c0.224,0.013,0.442,0.023,0.658,0.031c0.091,0.003,0.182,0.007,0.271,0.01   c0.293,0.008,0.579,0.013,0.849,0.013c0.269,0,0.555-0.005,0.848-0.013c0.091-0.003,0.183-0.007,0.275-0.01   c0.214-0.008,0.431-0.018,0.654-0.031c0.105-0.006,0.208-0.012,0.314-0.019c0.242-0.016,0.487-0.036,0.734-0.059   c0.08-0.007,0.158-0.013,0.238-0.021c0.322-0.032,0.645-0.069,0.968-0.113c0.079-0.011,0.157-0.024,0.236-0.035   c0.246-0.035,0.49-0.074,0.732-0.117c0.109-0.019,0.215-0.04,0.322-0.06c0.221-0.043,0.438-0.089,0.653-0.139   c0.098-0.023,0.196-0.044,0.292-0.068c0.299-0.075,0.591-0.155,0.868-0.245c0.014-0.005,0.026-0.01,0.04-0.014   c0.261-0.086,0.508-0.181,0.743-0.281c0.028-0.012,0.061-0.022,0.089-0.034l0.006,3.188C57.286,41.546,54.251,42.998,49.532,42.998   z" />
					</g>
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
					<g />
				</svg></div>
		</div>
	</div>

	<!-- Item -->
	<div class="col-lg-4 col-md-6">
		<div class="dashboard-stat color-2">
			<div class="dashboard-stat-content">
				<h4><?php echo absint($data->total_orders); ?></h4> <span><?php esc_html_e('Total Orders', 'listeo_core'); ?></span>
			</div>
			<div class="dashboard-stat-icon"><svg height="100%" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:1.41421;" version="1.1" viewBox="0 0 129 128" width="100%" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:serif="http://www.serif.com/" xmlns:xlink="http://www.w3.org/1999/xlink">
					<rect height="128" id="Cart" style="fill:none;" width="128" x="0.161" y="0" />
					<path d="M55.303,88.501c7.246,0.141 13.207,8.644 9.772,15.768c-2.882,5.976 -11.716,8.111 -17.012,3.633c-6.552,-5.541 -3.507,-19.198 6.956,-19.401c0.143,-0.001 0.142,-0.001 0.284,0Zm43.507,0c7.246,0.141 13.207,8.644 9.772,15.768c-2.881,5.976 -11.715,8.111 -17.011,3.633c-6.553,-5.541 -3.507,-19.198 6.955,-19.401c0.142,-0.001 0.142,-0.001 0.284,0Zm-43.739,4c-5.313,0.103 -9.241,7.669 -4.799,12.007c4.366,4.262 14.869,-0.429 11.199,-8.04c-1.152,-2.389 -3.716,-3.984 -6.4,-3.967Zm43.507,0c-5.305,0.103 -9.241,7.669 -4.798,12.007c4.365,4.262 14.868,-0.429 11.199,-8.04c-1.152,-2.389 -3.715,-3.984 -6.401,-3.967Zm18.075,-7.001l-75.394,0c-3.002,-0.058 -4.882,-4.291 -2.361,-6.491l7.554,-6.331c-3.368,-15.116 -6.489,-30.288 -10.105,-45.346c-0.836,-3.323 -4.022,-5.813 -7.445,-5.832l-17.234,0c-2.127,-0.152 -2.496,-3.821 0,-4c5.789,0 11.578,-0.031 17.367,0.001c5.187,0.084 9.994,3.866 11.227,9l1.563,7.015c0.053,-0.007 0.107,-0.013 0.162,-0.016c23.368,0 46.736,-0.147 70.104,0c3.412,0.065 6.453,3.039 6.56,6.477c0.152,7.757 0.003,15.517 0.003,23.275c-0.042,3.313 -2.705,6.276 -6.059,6.61l-63.125,5.505l-7.317,6.133l74.5,0c0.008,0.001 1.151,0.09 1.683,0.919c0.765,1.191 -0.152,2.971 -1.683,3.081Zm-73.941,-48l7.528,33.786c20.688,-1.792 41.397,-3.361 62.061,-5.415c1.271,-0.156 2.306,-1.28 2.352,-2.566c0.096,-7.753 0.241,-15.511 -0.001,-23.26c-0.061,-1.345 -1.247,-2.519 -2.612,-2.545l-69.328,0Z" style="fill-rule:nonzero;" />
				</svg></div>
		</div>
	</div>

</div>
<!-- Invoices -->
<div class="row">
	<div class="col-lg-6 col-md-12">
		<div class="dashboard-list-box invoices with-icons margin-top-20">
			<?php
				$listeo_commission_rate = get_user_meta($user_id, 'listeo_commission_rate', true);
				if (empty($listeo_commission_rate)) {
					$listeo_commission_rate = get_option('listeo_commission_rate', 10);
				}
				$listeo_commission_type = get_user_meta($user_id, 'listeo_commission_type', true);
				if (empty($listeo_commission_type)) {
					$listeo_commission_type = get_option('listeo_commission_type', 'percentage');
				}
				if ($listeo_commission_type === 'fixed') {
					$fee_display = Listeo_Core_Listing::get_currency_symbol(get_option('listeo_currency')) . esc_html($listeo_commission_rate);
				} else {
					$fee_display = esc_html($listeo_commission_rate) . '%';
				}
			?>
			<h4><?php esc_html_e('Earnings', 'listeo_core') ?> <div class="comission-taken"><?php esc_html_e('Fee', 'listeo_core'); ?>: <strong><?php echo $fee_display; ?></strong></div>
			</h4>
			<?php if ($commissions) { ?>
				<ul class="commissions-list">
					<?php
					foreach ($commissions as $commission) {

						$order = wc_get_order($commission['order_id']);
						if ($order) :
							$total = $order->get_total();
							$earning = $total - $commission['amount'];
					?>
							<li class="commission-<?php echo esc_attr($commission['status']); ?>"><i class="list-box-icon sl sl-icon-basket"></i>
								<strong><?php echo esc_html(get_the_title($commission['listing_id'])); ?></strong>
								<?php if ($commission['status'] == 'paid') { ?> <span class="commission-tag-paid"><?php esc_html_e('Processed', 'listeo_core'); ?></span> <?php } ?>
								<ul>
									<li class="paid"><?php echo wc_price($total); ?></li>
									<li class="unpaid"><?php esc_html_e('Fee', 'listeo_core'); ?>: <?php echo wc_price($commission['amount']); ?></li>
									<li class="paid"><?php esc_html_e('Your Earning', 'listeo_core'); ?>: <span><?php echo wc_price($earning); ?></span></li>
									<li><?php esc_html_e('Order', 'listeo_core'); ?>: #<?php echo absint($commission['order_id']); ?></li>
									<li><?php esc_html_e('Date', 'listeo_core'); ?>: <?php echo date_i18n(get_option('date_format'), strtotime($commission['date']));  ?></li>
								</ul>
							</li>
					<?php endif;
					} ?>
				</ul>
			<?php } else { ?>
				<ul>
					<li class="wallet-empty-list"><i class="list-box-icon sl sl-icon-basket"></i><?php esc_html_e('You don\'t have any earnings yet', 'listeo_core'); ?></li>
				</ul>
			<?php } ?>
		</div>
	</div>


	<!-- Invoices -->
	<div class="col-lg-6 col-md-12">
		<?php
		$payouts_options = get_option('listeo_payout_options', array('bank', 'paypal'));
		$stripe_connect_status = get_option('listeo_stripe_connect_activation');
		if (!empty($payouts_options) || $stripe_connect_status == 'yes') {

			$payment_type =  (isset($current_user->listeo_core_payment_type)) ? $current_user->listeo_core_payment_type : ''; ?>

			<div class="dashboard-list-box payouts-method with-icons margin-top-20">
				<h4 style="position: relative;"><?php esc_html_e('Payout Methods', 'listeo_core') ?></h4>

				<ul>
					<li style="padding-left: 30px; background: #fff;">
						<div class="payouts-method-content">

							<form method="post" id="edit_user" action="<?php the_permalink(); ?>">
								<div class="payment payout-method-tabs">
									<!-- Payment Methods Accordion -->
									<?php
									if (!empty($payouts_options)) {


										if (in_array('paypal', $payouts_options)) { ?>

											<?php if (!listeo_is_payout_active()) {  ?>
												<div class="payment-tab <?php if ($payment_type == 'paypal') { ?>payment-tab-active <?php } ?>">
													<div class="payment-tab-trigger">
														<input <?php checked($payment_type, 'paypal') ?> id="paypal" name="payment_type" type="radio" value="paypal">
														<label for="paypal"><?php esc_html_e('PayPal', 'listeo_core'); ?></label>
													</div>

													<div class="payment-tab-content">
														<div class="row">
															<div class="col-md-12">
																<div class="card-label">
																	<label for="ppemail"><?php esc_html_e('PayPal Email', 'listeo_core'); ?></label>
																	<input id="ppemail" name="ppemail" value="<?php if (isset($current_user->listeo_core_ppemail)) {
																													echo esc_attr($current_user->listeo_core_ppemail);
																												} ?>" type="email">
																</div>
															</div>
														</div>
													</div>
												</div>
											<?php } ?>
											<?php if (listeo_is_payout_active()) {  ?>
												<div class="payment-tab <?php if ($payment_type == 'paypal_payout') { ?>payment-tab-active <?php } ?> ">
													<div class="payment-tab-trigger">
														<input <?php checked($payment_type, 'paypal_payout') ?> type="radio" name="payment_type" id="paypal_payout" value="paypal_payout">
														<label for="paypal_payout"><?php esc_html_e('PayPal Payout', 'listeo_core'); ?></label>
													</div>

													<div class="payment-tab-content">
														<div class="row">
															<div class="col-md-12">
																<div class="card-label">
																	<label for="paypal_payout_email"><?php esc_html_e('PayPal Payout Email', 'listeo_core'); ?></label>
																	<input id="listeo_paypal_payout_email" name="listeo_paypal_payout_email" value="<?php if (isset($current_user->listeo_paypal_payout_email)) {
																																						echo esc_attr($current_user->listeo_paypal_payout_email);
																																					} ?>" type="email">
																</div>
															</div>
														</div>
													</div>
												</div>
											<?php } ?>
										<?php } ?>
										<?php if (in_array('bank', $payouts_options)) { ?>
											<div class="payment-tab <?php if ($payment_type == 'banktransfer') { ?>payment-tab-active <?php } ?> ">
												<div class="payment-tab-trigger">
													<input <?php checked($payment_type, 'banktransfer') ?> type="radio" name="payment_type" id="creditCart" value="banktransfer">
													<label for="creditCart"><?php esc_html_e('Bank Transfer', 'listeo_core'); ?></label>
												</div>

												<div class="payment-tab-content">
													<div class="row">

														<div class="col-md-12">
															<div class="notice notification payout-method-notification"><strong><?php esc_html_e('Add following bank transfer details:', 'listeo_core'); ?></strong> <?php esc_html_e('account', 'listeo_core'); ?> <?php esc_html_e('holders name & address, account number, bank name, IBAN, BIC/SWIFT', 'listeo_core'); ?></div>
															<div class="card-label">
																<label for="cvv"><?php esc_html_e('Bank Transfer Details', 'listeo_core'); ?></label>
																<textarea id="cvv" name="bank_details" type="text"><?php if (isset($current_user->listeo_core_bank_details)) {
																														echo esc_textarea($current_user->listeo_core_bank_details);
																													} ?></textarea>
															</div>
														</div>

													</div>
												</div>
											</div>
									<?php }
									} ?>
									<?php if (get_option('listeo_stripe_connect_activation') == 'yes') { ?>

										<div class="payment-tab <?php if (!in_array($payment_type, array('paypal', 'banktransfer'))) { ?>payment-tab-active <?php } ?> ">
											<div class="payment-tab-trigger">
												<input <?php checked($payment_type, 'stripe') ?> type="radio" name="payment_type" id="stripe" value="stripe">
												<label for="stripe"><?php esc_html_e('Stripe Connect', 'listeo_core'); ?></label>
											</div>

											<div class="payment-tab-content">
												<div class="row">

													<div class="col-md-12">
														<div class="card-label stripe-card-label">
															<?php

															if (get_user_meta($user_id, 'vendor_connected', true) == 1) { ?>
																<label style="padding: 0; margin: 0;"><?php _e('You are connected with Stripe', 'listeo_core'); ?></label><br>
																<a class="stripe-btn disconnect-stripe-button button" href="#"><?php _e('Disconnect Stripe Account', 'listeo_core'); ?></a>
																<?php
																$is_stripe_connected = true;
															} else {
																if (get_option('listeo_stripe_connect_account_type') == 'standard') {
																	$user_email = $current_user->user_email;

																	// Show OAuth link — use cryptographic state token to prevent CSRF
																	$state_nonce = wp_create_nonce('listeo_stripe_oauth_' . $user_id);
																	set_transient('listeo_stripe_oauth_state_' . $user_id, $state_nonce, 10 * MINUTE_IN_SECONDS);
																	$authorize_request_body = apply_filters('listeo_stripe_authorize_request_params', array(
																		'response_type' => 'code',
																		'scope' => 'read_write',
																		'client_id' => $client_id,
																		'redirect_uri' => add_query_arg(array('stripe-setup' => 'yes'), get_permalink()),
																		'state' => $state_nonce . ':' . $user_id,
																		'stripe_user' => array(
																			'email'         => $user_email,
																			'url'           => $current_user->user_url,
																			'business_name' => $current_user->first_name,
																			'first_name'    => $current_user->first_name,
																			'last_name'     => $current_user->last_name,

																		)
																	), $user_id);
																	if (get_option('listeo_stripe_connect_account_type') == 'express') {
																		$is_allow_stripe_express_api = true;
																	} else {
																		$is_allow_stripe_express_api = false;
																	}

																	if ($is_allow_stripe_express_api == true) {
																		$authorize_request_body['suggested_capabilities'] = array('transfers', 'card_payments');
																		$url = 'https://connect.stripe.com/express/oauth/authorize?' . http_build_query($authorize_request_body);
																	} else {
																		$url = 'https://connect.stripe.com/oauth/authorize?' . http_build_query($authorize_request_body);
																	}



																?><a href=<?php echo esc_url($url); ?> target="_self" class="conntect-w-stripe-btn"><?php _e('Connect with Stripe', 'listeo_core'); ?></a>
																	<?php  } else {
																	$account_id = get_user_meta($current_user->ID, 'listeo_stripe_express_account_id', true);
																	// Always generate fresh link via AJAX - don't use cached links
																	// Stripe account links are single-use and become invalid after use/abort
																	?>
																		<a href="#" target="_self" class="listeo-create-stripe-express-link-account conntect-w-stripe-btn">
																			<i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i><?php _e('Create Stripe Account', 'listeo_core'); ?>
																		</a>
																		<a style="display:none" href="#" target="_self" class="real-conntect-w-stripe-btn conntect-w-stripe-btn"><?php _e('Connect with Stripe', 'listeo_core'); ?></a>
																<?php } ?>
															<?php } ?>

														</div>
													</div>

												</div>
											</div>
										</div>
									<?php } ?>

								</div>
								<!-- Payment Methods Accordion / End -->

								<button class="button margin-top-15"><?php esc_html_e('Save', 'listeo_core') ?></button>
								<?php wp_nonce_field('listeo_wallet_form', 'wallet_nonce'); ?>
								<input type="hidden" name="my-account-submission" value="1" />
							</form>


						</div>
					</li>
				</ul>
			</div>
		<?php } ?>

		<?php if (get_user_meta($user_id, 'vendor_connected', true) == 1) { ?>
			<!-- Stripe Details -->

			<?php


			\Stripe\Stripe::setApiKey($secret);

			try {
				$stripe = new \Stripe\StripeClient(
					$secret
				);
				$stripe_user_id = get_user_meta($user_id, 'stripe_user_id', true);

				if ($stripe_user_id) {
					$stripe_user_data = ($stripe->accounts->retrieve(
						$stripe_user_id
					));
					$accountLinks = $stripe->accountLinks->create([
						'account' => $stripe_user_id,
						'refresh_url' =>
						add_query_arg(array('stripe-setup' => 'yes'), get_permalink()),
						'return_url' =>
						add_query_arg(array('stripe-setup' => 'yes'), get_permalink()),
						'type' => 'account_onboarding',
					]);

			?>
					<div class="dashboard-list-box invoices with-icons margin-top-20">
						<h4 style="position: relative;"><?php esc_html_e('Stripe Account Details', 'listeo_core') ?>
							<?php if ($stripe_user_data['type'] == 'express') {
								$login_link = $stripe->accounts->createLoginLink(
									$stripe_user_id,
									[]
								);

							?>
								<a class="payout-method button" href="<?php echo esc_url($login_link['url']); ?>"><?php esc_html_e('Go to Stripe Dashboard', 'listeo_core') ?></a>
							<?php }
							if ($stripe_user_data['type'] == 'standard') { ?>
								<a class="payout-method button" href="https://dashboard.stripe.com/b/<?php echo esc_attr($stripe_user_data['id']); ?>"><?php esc_html_e('Edit Account', 'listeo_core') ?></a>
							<?php } ?>

						</h4>
						<ul>
							<li><i class="list-box-icon sl sl-icon-user-following"></i>
								<strong><?php esc_html_e('Account Information', 'listeo_core') ?></strong>
								<ul>
									<li>ID: <?php echo esc_html($stripe_user_data['id']); ?></li>
									<li>Type: <?php echo esc_html(ucfirst($stripe_user_data['type'])); ?></li>
									<?php if ($stripe_user_data['payouts_enabled']) { ?>
										<li><?php esc_html_e('Payouts Enabled', 'listeo_core') ?></li>
									<?php } else { ?>
										<li><?php esc_html_e('Payouts Disabled', 'listeo_core') ?></li>
									<?php } ?>
									<li></li>
								</ul>
							</li>
							<?php
							try {
								$balance = \Stripe\Balance::retrieve(
									['stripe_account' => get_user_meta($user_id, 'stripe_user_id', true)]
								); ?>
								<li><i class="list-box-icon sl sl-icon-wallet"></i>
									<strong><?php esc_html_e('Payments', 'listeo_core') ?></strong>
									<ul>
										<?php
										foreach ($balance['available'] as $key => $value) { ?>
											<li class="paid"><?php esc_html_e('Available Balance:', 'listeo_core') ?> <?php echo wc_price(($value->amount) / 100); ?> </li>

										<?php } ?>
										<?php
										foreach ($balance['pending'] as $key => $value) { ?>
											<li class="paid"><?php esc_html_e('Pending Balance:', 'listeo_core') ?> <?php echo wc_price(($value->amount) / 100); ?> </li>

										<?php } ?>
									</ul>
								</li>
							<?php
							} catch (Exception $e) { ?>
								<li><i class="list-box-icon sl sl-icon-user-following"></i>
									<?php echo esc_html($e->getError()->message); ?>
								</li>
							<?php }; ?>

						</ul>
					<?php }
			} catch (Exception $e) {
					?>
					<div class="dashboard-list-box invoices with-icons margin-top-20">
						<h4 style="position: relative;"><?php esc_html_e('Stripe Account Details', 'listeo_core') ?></h4>
						<ul>
							<li><i class="list-box-icon sl sl-icon-user-following"></i>
								<?php echo esc_html($e->getError()->message); ?>
							</li>
						</ul>
					</div>
				<?php
			} // if stripe user id

				?>

			<?php } ?>

			<div class="dashboard-list-box invoices with-icons margin-top-20">


				<h4 style="position: relative;"><?php esc_html_e('Payout History', 'listeo_core') ?>

				</h4>
				<?php if ($payouts) { ?>
					<ul>
						<?php
						foreach ($payouts as $payout) {

							if ($payout['payment_method'] === 'paypal') {
								$payment_method =  __('PayPal', 'listeo_core');
							} else if ($payout['payment_method'] === 'PayPal Payout') {
								$payment_method = __('PayPal Payout', 'listeo_core');
							} else {
								$payment_method = __('Bank Transfer', 'listeo_core');
							}


						?>
							<li><i class="list-box-icon sl sl-icon-wallet"></i>
								<strong><?php echo wc_price($payout['amount']) ?></strong>
								<ul>
									<li class="payment_method"><?php echo $payment_method; ?></li>
									<li><?php esc_html_e('Date', 'listeo_core') ?>: <?php echo date_i18n(get_option('date_format'), strtotime($payout['date']));  ?></li>
								</ul>
							</li>


						<?php } ?>
					</ul>
				<?php } else { ?>
					<ul>
						<li class="wallet-empty-list"><i class="list-box-icon sl sl-icon-wallet"></i> <?php esc_html_e('You don\'t have any payouts yet.', 'listeo_core') ?></li>
					</ul>
				<?php } ?>
			</div>
					</div>
	</div>