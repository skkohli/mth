<?php
$ids = '';
if (isset($data)) :
	$ids	 	= (isset($data->ids)) ? $data->ids : '';
endif;
$message = $data->message;

$date_format = get_option('date_format');

?>
<?php if (!empty($message)) {
	echo $message;
} ?>

<?php if (!empty($ids)) :
	$currency_abbr = get_option('listeo_currency');
	$currency_postion = get_option('listeo_currency_postion');
	$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
?>
	<div class="woocommerce dashboard-list-box margin-top-0">
		<ul>
		<?php
			$nonce = wp_create_nonce("listeo_core_remove_fav_nonce");
			foreach ($ids as $ad_id) {
				$listing_id = get_post_meta($ad_id->ID, 'listing_id', true);
			?>
				<li class="" id="booking-list-<?php echo $ad_id->ID; ?>">
					<div class="list-box-listing ads">
						<div class="list-box-listing-content">
							<div class="inner">
								<h3>
									<?php
									$type = get_post_meta($ad_id->ID, 'type', true);
									
									if ($type == 'ppv') {
										$title_type = esc_html__('Pay Per View', 'listeo_core');
									} else {
										$title_type = esc_html__('Pay Per Click', 'listeo_core');
									}
									$title = sprintf(__('%s Ad Campaign for', 'listeo_core'), $title_type);
									echo $title
									?>
									<a href="<?php the_permalink($listing_id) ?>">
										<?php echo get_the_title($listing_id); ?>
									</a>
									<?php $status = get_post_meta($ad_id->ID, 'ad_status', true);
									switch ($status) {
										case 'active':
											echo '<span class="listeo-ad-status active">' . __('Active', 'listeo_core') . '</span>';
											break;
										case "paused":
											echo '<span class="listeo-ad-status paused">' . __('Paused', 'listeo_core') . '</span>';
											break;
										case "waiting":
											echo '<span class="listeo-ad-status waiting">' . __('Waiting for payment', 'listeo_core') . '</span>';
											break;
										case 'paid_and_waiting':
											echo '<span class="listeo-ad-status waiting">' . __('Paid, waiting for start', 'listeo_core') . '</span>';
											break;
										case "expired":
											echo '<span class="listeo-ad-status pending">' . __('Expired', 'listeo_core') . '</span>';
											break;
										case "completed":
											echo '<span class="listeo-ad-status completed">' . __('Completed', 'listeo_core') . '</span>';
											break;

										default:
											echo '<span class="listeo-ad-status paused">' . __('Paused', 'listeo_core') . '</span>';
											break;
									}
									
									?>
								</h3>



								<span class="field"><?php esc_html_e('Budget', 'listeo_core'); ?>:
									<?php echo listeo_output_price(get_post_meta($ad_id->ID, 'budget', true)); ?>
								</span>

								<?php $remaining = get_post_meta($ad_id->ID, 'remaining_budget', true);
								if($remaining){ ?>
								<span class="field"><?php esc_html_e('Remaining Budget', 'listeo_core'); ?>:
									<?php echo listeo_output_price($remaining); ?>
								</span>
								<?php } ?>

								<?php
								//placement
								$placement = get_post_meta($ad_id->ID, 'placement', false);
							
								if(is_array($placement)){
									echo '<span class="field">' . esc_html__('Placement', 'listeo_core') . ': ';
									$output = array();
									foreach ($placement as $place) {
										switch ($place) {
											case 'home':
												$output[] = esc_html__('Home', 'listeo_core');
												break;
											case 'sidebar':
												$output[] = esc_html__('Sidebar', 'listeo_core');
												break;
											case 'search':
												$output[] = esc_html__('Search', 'listeo_core');
												break;
											
											default:
												# code...
												break;
										}		
									}
									echo implode(', ', $output);
									echo '</span>';
								}
								?>


								<?php
								global $wpdb;
								$table_name = $wpdb->prefix . 'listeo_core_ad_stats';

								$total_views = $wpdb->get_var($wpdb->prepare(
									"SELECT SUM(views) FROM $table_name WHERE ad_id = %d",
									$ad_id->ID
								));

								$total_clicks = $wpdb->get_var($wpdb->prepare(
									"SELECT SUM(clicks) FROM $table_name WHERE ad_id = %d",
									$ad_id->ID
								));

								?>
								<?php if ($type == 'ppv') { ?>
									<span class="field field-budget"><?php esc_html_e('Views', 'listeo_core'); ?>:
										<?php
										$views =  intval($total_views);
										if ($views == '') {
											$views = 0;
										}
										echo $views;
										?>
									</span>
								<?php } else { ?>
									<span class="field field-status"><?php esc_html_e('Clicks', 'listeo_core'); ?>:
										<?php
										// Get the number of clicks from database


										$clicks =  intval($total_clicks);
										if ($clicks == '') {
											$clicks = 0;
										}
										echo $clicks; ?>
									</span>
								<?php } ?>
								<?php

								$start_date = get_post_meta($ad_id->ID, 'start_date', true);
								if ($start_date) {
									echo '<span class="field">' . esc_html__('Start Date', 'listeo_core') . ': ';

									// Convert the timestamp to a human-readable date using WordPress date format
									$start_date = date_i18n($date_format, $start_date);

									// Output the human-readable date
									echo $start_date;
									echo '</span>';
								}
								// disaply filters from the ad meta fiedls 'ad_category_filter' and 'taxonomy-region' and _address and "loggedin"
								$category = get_post_meta($ad_id->ID, 'ad_category_filter', true);

								$region = get_post_meta($ad_id->ID, 'taxonomy-region', true);
								$address = get_post_meta($ad_id->ID, '_address', true);
								$loggedin = get_post_meta($ad_id->ID, 'only_loggedin', true);
								// Category is stored as "taxonomy:term_id" (e.g. "listing_category:19").
								// Fall back to legacy numeric (term id) / slug values for older ads.
								$listing_category = false;
								if ($category && $category !== '0') {
									if (strpos($category, ':') !== false) {
										list($category_taxonomy, $category_term_id) = array_pad(explode(':', $category, 2), 2, '');
										if ($category_taxonomy && $category_term_id) {
											$listing_category = get_term_by('id', (int) $category_term_id, $category_taxonomy);
										}
									} elseif (is_numeric($category)) {
										$listing_category = get_term_by('id', $category, 'listing_category');
									} else {
										$listing_category = get_term_by('slug', $category, 'listing_category');
									}
								}
								// if region is number, get the term by id
								if (is_numeric($region)) {
									$region_term = get_term_by('id', $region, 'region');
								} else {
									$region_term = get_term_by('slug', $region, 'region');
								}

								if ($listing_category) {
									echo '<span class="field">' . esc_html__('Category', 'listeo_core') . ': ';
									echo $listing_category->name;
									echo '</span> ';
								}
								if ($region) {
									echo '<span class="field">' . esc_html__('Region', 'listeo_core') . ': ';
									echo $region_term->name;
									echo '</span> ';
								}
								if ($address) {
									echo '<span class="field">' . esc_html__('Address', 'listeo_core') . ': ';
									echo $address;
									echo '</span> ';
								}
								if ($loggedin) {
									echo '<span class="field">' . esc_html__('Only for logged in users', 'listeo_core');

									echo '</span> ';
								}

								?>

							</div>
						</div>
					</div>
					<div class="buttons-to-right booking-buttons-actions">
						<?php $actions = array();

						$actions['delete'] = array(
							'label' => __('Delete', 'listeo_core'),
							'icon' => 'sl sl-icon-close',
							'nonce' => true,
							'css'	=> 'cancel'
						);

						$actions           = apply_filters('listeo_core_ads_actions', $actions, $ad_id);

						foreach ($actions as $action => $value) {
							if ($action == 'edit') {
								$action_url = add_query_arg(array('action' => $action,  'ad_id' => $ad_id->ID), get_permalink(get_option('listeo_ad_page')));
							} else {
								$action_url = add_query_arg(array('action' => $action,  'ad_id' => $ad_id->ID));
							}
							if ($value['nonce']) {
								$action_url = wp_nonce_url($action_url, 'listeo_core_ads_actions');
							}

							echo '<a href="' . esc_url($action_url) . '" class="woocommerce-button button ' . esc_attr($value['css']) . ' listeo_core-dashboard-action-' . esc_attr($action) . '">';

							if (isset($value['icon']) && !empty($value['icon'])) {
								echo '<i class="' . $value['icon'] . '"></i>';
							}

							echo esc_html($value['label']) . '</a>';
						}
						$order_id = get_post_meta($ad_id->ID, 'order_id', true);
						$order = wc_get_order($order_id);

						if ($order) {
							$payment_url = $order->get_checkout_payment_url();

							$order_data = $order->get_data();

							$order_status = $order_data['status'];
							if ($order_status == 'pending') {
								echo '<a href="' . esc_url($payment_url) . '" class="woocommerce-button button listeo_core-dashboard-action-payment">';
								echo '<i class="sl sl-icon-credit-card"></i>';
								echo esc_html__('Pay now', 'listeo_core') . '</a>';
							}
						}
						?>





					<?php } ?>

					</div>
				</li>





		</ul>
	</div>

<?php else: ?>
	<div class="notification notice ">
		<p> <?php esc_html_e('You haven\'t created any ads yet.', 'listeo_core'); ?></p>

	</div>

<?php endif;
?>

<a href="<?php echo get_permalink(get_option('listeo_ad_campaigns_page')); ?>/?add_new_ad=true" class="margin-top-35 button"><?php esc_html_e('Create new Campaign', 'listeo_core'); ?></a>