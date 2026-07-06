<?php

if (isset($data->options_cb) && !empty($data->options_cb)) {

	switch ($data->options_cb) {
		case 'listeo_core_get_offer_types':
			$data->options = listeo_core_get_offer_types_flat(false);
			break;

		case 'listeo_get_listing_types':
			$data->options = listeo_core_get_listing_types();
			break;

		case 'listeo_core_get_rental_period':
			$data->options = listeo_core_get_rental_period();
			break;

		default:
			# code...
			break;
	}
}


if (isset($_GET[$data->name])) {
	// if multi select is enabled and the value is an array then set the selected value to the array
	if (is_array($_GET[$data->name])) {
		$selected = $_GET[$data->name];
	} else {
		$selected = sanitize_text_field($_GET[$data->name]);
	}
} else {
	$selected = '';
	if (isset($data->default) && !empty($data->default)) {
		$selected = $data->default;
	} else {
		$selected = '';
	}
}
$count = 0;
// count number of options
if (is_array($data->options)) :
$count = count($data->options);
endif;

?>
<div class="<?php if (isset($data->class)) {
				echo esc_attr($data->class);
			} ?> <?php if (isset($data->css_class)) {
						echo esc_attr($data->css_class);
					} ?>">
	<select <?php if ($count > 8) echo 'data-live-search="true"'; ?> <?php if (isset($data->multi) && $data->multi == '1') { echo 'multiple class="selectpicker"'; } else { echo 'class="selectpicker"'; } ?> data-size="10" name="<?php echo esc_attr($data->name);if (isset($data->multi) && $data->multi == '1') { echo "[]";} ?>" id="<?php echo esc_attr($data->name) . '-' . uniqid(); ?>" title="<?php echo esc_attr($data->placeholder); ?>" data-placeholder="<?php echo esc_attr($data->placeholder); ?>">

		<?php
		if (is_array($data->options)) :
			foreach ($data->options as $key => $value) { ?>
				<option 
				<?php 
				$is_selected = '';
				if(is_array($selected)){
					$is_selected = in_array($key, $selected) ? ' selected="selected" ' : '';
				} else {
					$is_selected = selected($selected, $key, false);
				}
				echo $is_selected;
					?> 
				value="<?php echo esc_html($key); ?>"><?php echo esc_html($value); ?></option>
		<?php }
		endif;
		?>
	</select>
</div>