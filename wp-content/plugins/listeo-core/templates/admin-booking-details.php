<?php
/**
 * Admin Booking Details Template
 * Used in the modal popup
 */

if (!defined('ABSPATH')) exit;

$currency_abbr = get_option('listeo_currency');
$currency_position = get_option('listeo_currency_postion');
$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
$decimals = get_option('listeo_number_decimals', 2);
?>

<!-- Listing Information -->
<div class="booking-detail-section">
	<h3><?php _e('Listing Information', 'listeo_core'); ?></h3>
	<div class="detail-grid">
		<div class="detail-item">
			<span class="detail-label"><?php _e('Listing', 'listeo_core'); ?></span>
			<span class="detail-value">
				<a href="<?php echo get_permalink($booking['listing_id']); ?>" target="_blank">
					<?php echo esc_html(get_the_title($booking['listing_id'])); ?>
				</a>
			</span>
		</div>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Type', 'listeo_core'); ?></span>
			<span class="detail-value"><?php echo ucfirst($booking['type']); ?></span>
		</div>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Status', 'listeo_core'); ?></span>
			<span class="detail-value">
				<span class="status-badge <?php echo esc_attr($booking['status']); ?>">
					<?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
				</span>
			</span>
		</div>
	</div>
</div>

<!-- Booking Dates -->
<div class="booking-detail-section">
	<h3><?php _e('Booking Dates', 'listeo_core'); ?></h3>
	<div class="detail-grid">
		<div class="detail-item">
			<span class="detail-label"><?php _e('Start Date', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking['date_start'])); ?>
			</span>
		</div>
		<div class="detail-item">
			<span class="detail-label"><?php _e('End Date', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking['date_end'])); ?>
			</span>
		</div>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Created', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking['created'])); ?>
			</span>
		</div>
		<?php if ($booking['expiring'] && $booking['expiring'] != '0000-00-00 00:00:00' && $booking['expiring'] != $booking['created']): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Payment Due', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking['expiring'])); ?>
			</span>
		</div>
		<?php endif; ?>
	</div>
</div>

<!-- Client Information -->
<?php if ($booking['bookings_author'] != 0): ?>
<div class="booking-detail-section">
	<h3><?php _e('Client Information', 'listeo_core'); ?></h3>
	<div class="detail-grid">
		<?php if (isset($details->first_name) || isset($details->last_name)): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Name', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php
				echo esc_html(stripslashes($details->first_name ?? '')) . ' ' . esc_html(stripslashes($details->last_name ?? ''));
				?>
			</span>
		</div>
		<?php endif; ?>

		<?php if (isset($details->email)): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Email', 'listeo_core'); ?></span>
			<span class="detail-value">
				<a href="mailto:<?php echo esc_attr($details->email); ?>">
					<?php echo esc_html($details->email); ?>
				</a>
			</span>
		</div>
		<?php endif; ?>

		<?php if (isset($details->phone)): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Phone', 'listeo_core'); ?></span>
			<span class="detail-value">
				<a href="tel:<?php echo esc_attr($details->phone); ?>">
					<?php echo esc_html($details->phone); ?>
				</a>
			</span>
		</div>
		<?php endif; ?>

		<?php if (isset($details->billing_address_1)): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Address', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php echo esc_html(stripslashes($details->billing_address_1)); ?><br>
				<?php if (isset($details->billing_postcode)): ?>
					<?php echo esc_html(stripslashes($details->billing_postcode)); ?><br>
				<?php endif; ?>
				<?php if (isset($details->billing_city)): ?>
					<?php echo esc_html(stripslashes($details->billing_city)); ?><br>
				<?php endif; ?>
				<?php if (isset($details->billing_country)): ?>
					<?php echo esc_html(stripslashes($details->billing_country)); ?>
				<?php endif; ?>
			</span>
		</div>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<!-- Booking Details -->
<?php if ((isset($details->children) && $details->children > 0) || (isset($details->adults) && $details->adults > 0) || (isset($details->tickets) && $details->tickets > 0)): ?>
<div class="booking-detail-section">
	<h3><?php _e('Booking Details', 'listeo_core'); ?></h3>
	<div class="detail-grid">
		<?php if (isset($details->adults) && $details->adults > 0): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Adults', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php printf(_n('%d Guest', '%d Guests', $details->adults, 'listeo_core'), $details->adults); ?>
			</span>
		</div>
		<?php endif; ?>

		<?php if (isset($details->children) && $details->children > 0): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Children', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php printf(_n('%d Child', '%d Children', $details->children, 'listeo_core'), $details->children); ?>
			</span>
		</div>
		<?php endif; ?>

		<?php if (isset($details->tickets) && $details->tickets > 0): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Tickets', 'listeo_core'); ?></span>
			<span class="detail-value">
				<?php printf(_n('%d Ticket', '%d Tickets', $details->tickets, 'listeo_core'), $details->tickets); ?>
			</span>
		</div>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<!-- Price & Payment -->
<?php if ($booking['price']): ?>
<div class="booking-detail-section">
	<h3><?php _e('Price & Payment', 'listeo_core'); ?></h3>
	<div class="detail-grid">
		<div class="detail-item">
			<span class="detail-label"><?php _e('Total Price', 'listeo_core'); ?></span>
			<span class="detail-value" style="font-size: 1.25rem; font-weight: 700; color: #10b981;">
				<?php
				if ($currency_position == 'before') {
					echo $currency_symbol . ' ' . number_format_i18n($booking['price'], $decimals);
				} else {
					echo number_format_i18n($booking['price'], $decimals) . ' ' . $currency_symbol;
				}
				?>
			</span>
		</div>
		<?php if ($booking['order_id']): ?>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Order ID', 'listeo_core'); ?></span>
			<span class="detail-value">
				<a href="<?php echo admin_url('post.php?post=' . $booking['order_id'] . '&action=edit'); ?>" target="_blank">
					#<?php echo $booking['order_id']; ?>
				</a>
			</span>
		</div>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<!-- Extra Services -->
<?php if (isset($details->service) && !empty($details->service)): ?>
<div class="booking-detail-section">
	<h3><?php _e('Extra Services', 'listeo_core'); ?></h3>
	<div class="detail-value">
		<?php echo listeo_get_extra_services_html($details->service); ?>
	</div>
</div>
<?php endif; ?>

<!-- Message -->
<?php if (isset($details->message) && !empty($details->message)): ?>
<div class="booking-detail-section">
	<h3><?php _e('Client Message', 'listeo_core'); ?></h3>
	<div class="detail-value" style="background: #f8fafc; padding: 1rem; border-radius: 5px; border-left: 3px solid #667eea;">
		<?php echo wpautop(esc_html(stripslashes($details->message))); ?>
	</div>
</div>
<?php endif; ?>

<!-- Owner Information -->
<?php if ($booking['owner_id'] != 0): ?>
<div class="booking-detail-section">
	<h3><?php _e('Owner Information', 'listeo_core'); ?></h3>
	<?php $owner = get_userdata($booking['owner_id']); ?>
	<?php if ($owner): ?>
	<div class="detail-grid">
		<div class="detail-item">
			<span class="detail-label"><?php _e('Name', 'listeo_core'); ?></span>
			<span class="detail-value">
				<a href="<?php echo get_edit_user_link($owner->ID); ?>" target="_blank">
					<?php echo esc_html($owner->display_name); ?>
				</a>
			</span>
		</div>
		<div class="detail-item">
			<span class="detail-label"><?php _e('Email', 'listeo_core'); ?></span>
			<span class="detail-value">
				<a href="mailto:<?php echo esc_attr($owner->user_email); ?>">
					<?php echo esc_html($owner->user_email); ?>
				</a>
			</span>
		</div>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>
