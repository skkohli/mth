<div class="row <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?> ">
<?php if(isset($data->dynamic) && $data->dynamic=='yes'){ ?>
	<div class="notification warning"><p><?php esc_html_e('Please choose category to display filters','listeo_core') ?></p> </div>
<?php } else {


if(isset($_GET[$data->name])) {
	$selected = $_GET[$data->name];
} else {
	$selected = array();
} 

if(isset($data->taxonomy) && !empty($data->taxonomy)) {
	$data->options = listeo_core_get_options_array('taxonomy',$data->taxonomy);
	$groups = array_chunk($data->options, 4, true);
	if(is_tax($data->taxonomy)){
		$selected[get_query_var($data->taxonomy)] = 'on';
	}
	
	?>
	<div class="panel-checkboxes-container">
	<?php
	
	if(!is_array($selected)){

		$selected_arr = array();
		$selected_arr[$selected] = 'on';
		$selected = $selected_arr;
	}
	

	foreach ($groups as $group) { ?>
		
	
	<?php foreach ($group as $key => $value) { 
		// check if $selected array has keys as number or text
		if (array_key_exists($value['slug'], $selected)) {
			$checked = 'checked="checked"';
		} elseif (in_array($value['slug'], $selected)) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		
		?>
		
		<div class="panel-checkbox-wrap">
			<input <?php  echo $checked;  ?> id="<?php echo esc_html($value['slug']) ?>-<?php echo esc_attr($data->name); ?>" value="<?php echo esc_html($value['slug']) ?>" type="checkbox" name="<?php echo $data->name.'['.esc_html($value['slug']).']'; ?>">
			<label for="<?php echo esc_html($value['slug']) ?>-<?php echo esc_attr($data->name); ?>"><?php echo esc_html($value['name']) ?></label>	
		</div>
	<?php } ?>
		
<?php } ?>
	</div>
<?php }

if(isset($data->options_source) && empty($data->taxonomy) ) {
	if(isset($data->options_cb) && !empty($data->options_cb) ){
		switch ($data->options_cb) {
			case 'listeo_core_get_offer_types':
				$data->options = listeo_core_get_offer_types_flat(false);
				break;

			case 'listeo_core_get_listing_types':
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

	if($data->options_source == 'custom') {
		$data->options = array_flip($data->options);
	}
	$groups = array_chunk($data->options, 4, true); ?>
<div class="panel-checkboxes-container">
	<?php
	
	if(!is_array($selected)){

		$selected_arr = array();
		$selected_arr[$selected] = 'on';
		$selected = $selected_arr;
	}

	foreach ($groups as $group) { ?>
		
	
	<?php foreach ($group as $key => $value) { 	?>

		<div class="panel-checkbox-wrap">
			<input <?php if ( array_key_exists ($key, $selected) ) { echo 'checked="checked"'; } ?> id="<?php echo esc_html($key) ?>-<?php echo esc_attr($data->name); ?>" type="checkbox" name="<?php echo $data->name.'['.esc_html($key).']'; ?>">
			<label for="<?php echo esc_html($key) ?>-<?php echo esc_attr($data->name); ?>"><?php echo esc_html($value) ?></label>
		</div>
	<?php } ?>
		
	<?php } ?>
	</div>
<?php
}
}
?>


</div>