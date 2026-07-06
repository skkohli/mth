<?php
if(isset($_GET[$data->name])) {
	$value = sanitize_text_field($_GET[$data->name]);
} else {
	if(isset($data->default) && !empty($data->default)){
		$value = $data->default;
	} else {
		$value = '';	
	}
} ?>
<input id="<?php echo esc_attr($data->name);?>" name="<?php echo esc_attr($data->name);?>" type="hidden" placeholder="<?php echo esc_attr($data->placeholder);?>" value="<?php if(isset($value)){ echo esc_attr($value);  } ?>"/>
