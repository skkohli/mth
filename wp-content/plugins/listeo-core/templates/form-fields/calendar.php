<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

$field = $data->field;
$key = $data->key;

// Parse existing values
$dates_value = '';
$prices_value = '';

if (isset($field['value'])) {
	if (isset($field['value']['dates']) && !empty($field['value']['dates'])) {
		$dates_value = esc_attr($field['value']['dates']);
	}
	if (isset($field['value']['price']) && !empty($field['value']['price'])) {
		$prices_value = esc_attr($field['value']['price']);
	}
}

// Create hidden inputs to store the data in the same format as before
?>
<input type="hidden" class="listeo-calendar-avail" value="<?php echo $dates_value; ?>" name="<?php echo esc_attr($key); ?>[dates]" id="fullcalendar-blocked-dates">
<input type="hidden" class="listeo-calendar-price" value="<?php echo $prices_value; ?>" name="<?php echo esc_attr($key); ?>[price]" id="fullcalendar-price-data">

<div class="fullcalendar-container">
	<div class="calendar-toolbar" style="display: none;">

		<div class="calendar-actions">
			<button type="button" id="set-price-btn" class="button"><?php _e('Set Price for Selected Dates', 'listeo_core'); ?></button>
			<button type="button" id="block-dates-btn" class="button"><?php _e('Block Selected Dates', 'listeo_core'); ?></button>
			<button type="button" id="clear-selection-btn" class="button"><?php _e('Clear Selection', 'listeo_core'); ?></button>
		</div>
	</div>
	<div id="fullcalendar"></div>
</div>

<!-- Price Dialog -->
<div id="price-dialog" title="<?php _e('Set Price for Selected Dates', 'listeo_core'); ?>" style="display:none;">
	<p><?php _e('Enter price for the selected dates:', 'listeo_core'); ?></p>
	<input type="number" id="price-input" min="0" step="0.01" class="widefat">
	<div class="price-dialog-buttons">
		<button type="button" id="price-confirm" class="button"><?php _e('Set Price', 'listeo_core'); ?></button>
		<button type="button" id="price-cancel" class="button"><?php _e('Cancel', 'listeo_core'); ?></button>
	</div>
</div>
