<?php 
$flag_enabled = false;

if(isset($data->state) && $data->state == 'on'){
	$flag_enabled = true;
}
if(isset($_GET[$data->name.'_range']) && !empty($_GET[$data->name.'_range']) && $_GET[$data->name.'_range'] != 'NaN') {
	$min = sanitize_text_field($_GET[''.$data->name.'_range']);
	$min = array_map( 'absint', explode( ',', $min ) );
	$min = (int)preg_replace('/[^0-9]/', '', $min[0]);
	$flag_enabled = true;
	if($data->name == '_price'){
		$data->min = Listeo_Core_Search::get_min_meta_value($data->name);
	} else {
		$data->min = Listeo_Core_Search::get_min_meta_value($data->name);
	}
} else {
	if($data->min == 'auto') {

		if($data->name == '_price'){

			$min = Listeo_Core_Search::get_min_meta_value($data->name);
			$data->min = $min;
		} else {
			$min = Listeo_Core_Search::get_min_meta_value($data->name);
			$data->min = Listeo_Core_Search::get_min_meta_value($data->name);
		}
		
	} else {
		$min = $data->min;	
	}
} 

if(isset($_GET[$data->name.'_range']) && !empty($_GET[$data->name.'_range']) && $_GET[$data->name.'_range'] != 'NaN') {
	$max = sanitize_text_field($_GET[$data->name.'_range']);
	$max = array_map( 'absint', explode( ',', $max ) );
	$max = (int)preg_replace('/[^0-9]/', '', $max[1]);
	$flag_enabled = true;
	if($data->name == '_price'){
		$data->max = Listeo_Core_Search::get_max_meta_value($data->name);
	} else {
		$data->max = Listeo_Core_Search::get_max_meta_value($data->name);
	}
} else {
	if($data->max == 'auto') {
		if($data->name == '_price'){
			$max = Listeo_Core_Search::get_max_meta_value($data->name);
			$data->max = $max;
		} else {
			$max = Listeo_Core_Search::get_max_meta_value($data->name);
			$data->max = Listeo_Core_Search::get_max_meta_value($data->name);
		}
	} else {
		$max = $data->max;	
	}
	
} 
if(!$max){
	$max = 1;
}
if(!$min){
	$min = 0;
}
?>

<!-- Range Slider -->

<div class="<?php if(isset($data->class)) { echo esc_attr($data->class); } ?> <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>" >
	<!-- Range Slider -->
	<?php 
		$currency_abbr = get_option( 'listeo_currency' );
		$currency_postion = get_option( 'listeo_currency_postion' );
		$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
	?>				
				
	<div class="range-slider-container <?php if($flag_enabled) { echo 'no-to-disable'; } ?>">
		<span class="range-slider-headline"><?php echo esc_html($data->placeholder) ?> </span>
		<div class="range-slider-subtitle"><?php esc_html_e('Select min and max price range','listeo_core') ?></div>
		<input  id="<?php echo esc_attr($data->name); ?>_range"  name="<?php echo esc_attr($data->name); ?>_range"  class="bootstrap-range-slider" type="text" value="" data-slider-currency="<?php echo $currency_symbol ?>" data-slider-min="<?php echo esc_attr($data->min); ?>" data-slider-max="<?php echo esc_attr($data->max); ?>" data-slider-step="1" data-slider-value="[<?php echo esc_attr($min); ?>,<?php echo esc_attr($max); ?>]"/>
	</div>
	<span class="slider-disable" data-disable="<?php esc_html_e('Disable','listeo_core');?> <?php echo esc_html($data->placeholder) ?> " data-enable="<?php esc_html_e('Enable','listeo_core');?> <?php echo esc_html($data->placeholder) ?> "><?php esc_html_e('Enable','listeo_core');?> <?php echo esc_html($data->placeholder) ?> </span>
</div>