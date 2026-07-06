<?php


if(isset($_GET[$data->name])) {
	if ($data->name === 'keyword_search' && class_exists('Listeo_Core_Search')) {
		$value = Listeo_Core_Search::sanitize_keyword_search($_GET[$data->name]);
	} else {
		$value = stripslashes(sanitize_text_field($_GET[$data->name]));
	}
} else {
	if(isset($data->default) && !empty($data->default)){
		$value = $data->default;
	} else {
		$value = '';	
	}
}

$maxlength = 0;
if (isset($data->maxlength)) {
	$maxlength = absint($data->maxlength);
} elseif ($data->name === 'keyword_search' && class_exists('Listeo_Core_Search')) {
	$maxlength = Listeo_Core_Search::get_keyword_search_max_length();
}

?>

<div class="<?php if(isset($data->class)) { echo esc_attr($data->class); } ?> <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>">
		<input  autocomplete="off" name="<?php echo esc_attr($data->name);?>" id="<?php echo esc_attr($data->name);?>" class="<?php echo esc_attr($data->name);?>" type="text" placeholder="<?php echo esc_attr($data->placeholder);?>" value="<?php if(isset($value)){ echo esc_attr($value);  } ?>"<?php if ($maxlength) : ?> maxlength="<?php echo esc_attr($maxlength); ?>"<?php endif; ?>/>
		<?php
		if ($data->name === 'keyword_search' && class_exists('Listeo_Core_Search')) {
			Listeo_Core_Search::render_keyword_search_bot_fields();
		}
		?>
</div>
