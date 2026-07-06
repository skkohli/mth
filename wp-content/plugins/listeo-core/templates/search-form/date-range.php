<?php
if(isset($_GET[$data->name])) {
	$value = sanitize_text_field($_GET[$data->name]);
} else {
	$value = '';
}

$date_range_type = (isset($data->date_range_type)) ? $data->date_range_type : 'rental' ;
?>


<div class="search-input-icon <?php if(isset($data->class)) { echo esc_attr($data->class); } ?> <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>">
	<input readonly="readonly" autocomplete="off" name="<?php echo esc_attr($data->name);?>" id="<?php echo esc_attr($data->name);?>" class="<?php echo esc_attr($data->name);?>" type="text" placeholder="<?php echo esc_attr($data->placeholder);?>" value="<?php if(isset($value)){ echo $value;  } ?>"/>
	<i class="fa fa-calendar"></i>
</div>
<?php if($date_range_type != 'custom') { ?>
<input type="hidden" <?php if(!isset($_GET['_listing_type'])) { ?> disabled="disabled" <?php } ?> name="_listing_type" value="<?php echo esc_attr($date_range_type); ?>">
<?php } ?>
