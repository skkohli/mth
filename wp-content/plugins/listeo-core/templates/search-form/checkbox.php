<?php
if (isset($_GET[$data->name])) {
	$value = sanitize_text_field($_GET[$data->name]);
} else {
	if (isset($data->default) && !empty($data->default)) {
		$value = $data->default;
	} else {
		$value = '';
	}
} ?>
<div class="checkboxes-single checkboxes margin-top-10 <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>">
	<input id="<?php echo esc_attr($data->name); ?>" name="<?php echo esc_attr($data->name); ?>" type="checkbox" placeholder="<?php echo esc_attr($data->placeholder); ?>" value="<?php if (isset($value)) { echo esc_attr($value);} ?>" />
	<label for="<?php echo esc_attr($data->name); ?>"><?php echo esc_attr($data->placeholder); ?></label>
</div>