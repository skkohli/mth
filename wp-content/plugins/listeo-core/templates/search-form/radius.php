<?php
// Don't show radius field in viewport mode
$search_mode = get_option('listeo_location_search_mode', 'radius');
if ($search_mode === 'viewport') {
	return; // Hide radius field when using viewport-based search
}

$flag_enabled = false;

if(isset($_GET[$data->name.'_min']) && !empty($_GET[$data->name.'_min']) && $_GET[$data->name.'_min'] != 'NaN') {
	$min = sanitize_text_field($_GET[''.$data->name.'_min']);
	$min = (int)preg_replace('/[^0-9]/', '', $min);
	if($data->name == '_price'){
		$data->min = Listeo_Core_Search::get_min_meta_value($data->name);
	} else {
		$data->min = Listeo_Core_Search::get_min_meta_value($data->name);
	}
} else {
	if($data->min == 'auto') {
		if($data->name == '_price'){
			$min = Listeo_Core_Search::get_min_meta_value($data->name);
		} else {
			$min = Listeo_Core_Search::get_min_meta_value($data->name);
			$data->min = Listeo_Core_Search::get_min_meta_value($data->name);
		}
		
	} else {
		$min = $data->min;	
	}
} 

if(isset($_GET[$data->name.'_max']) && !empty($_GET[$data->name.'_max']) && $_GET[$data->name.'_max'] != 'NaN') {
	$max = sanitize_text_field($_GET[$data->name.'_max']);
	$max = (int)preg_replace('/[^0-9]/', '', $max);
	if($data->name == '_price'){
		$data->max = Listeo_Core_Search::get_max_meta_value($data->name,'sale');
	} else {
		$data->max = Listeo_Core_Search::get_max_meta_value($data->name);
	}
} else {
	if($data->max == 'auto') {
		if($data->name == '_price'){
			$max = Listeo_Core_Search::get_max_meta_value($data->name,'sale');
		} else {
			$max = Listeo_Core_Search::get_max_meta_value($data->name);
			$data->max = Listeo_Core_Search::get_max_meta_value($data->name);
		}
	} else {
		$max = $data->max;	
	}
	
} 

?>
<!-- Area Range -->
<div class="<?php if(isset($data->class)) { echo esc_attr($data->class); } ?> <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>" >
	<div class="range-slider">
		<input name="<?php echo esc_attr($data->name); ?>" class="distance-radius" type="range" min="<?php echo esc_attr($data->min); ?>" max="<?php echo esc_attr($data->max); ?>" step="1" value="<?php echo get_option('listeo_maps_default_radius'); ?>" data-title="<?php echo esc_html($data->placeholder) ?>">
	</div>

	<?php
	
	if( isset($data->place) && $data->place != 'panel' ) { ?>
		<span class="panel-disable" data-disable="<?php echo esc_attr_e( 'Disable Radius', 'listeo_core' ); ?>" data-enable="<?php echo esc_attr_e( 'Enable Radius', 'listeo_core' ); ?>"><?php esc_html_e('Disable Radius', 'listeo_core'); ?></span>
	<?php } ?>
</div>