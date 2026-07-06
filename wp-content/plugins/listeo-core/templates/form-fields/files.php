<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
	$field = $data->field;
	$key = $data->key;

if(isset($field['description'])){
	echo '<div class="notification closeable notice"><p class="description" id="'.$key.'-description">'. stripslashes($field['description']).'</p></div>';
}

?>

<?php 
if(isset($field['value']) && is_array(($field['value']))){ 
	$i = 0;
	foreach($field['value'] as $key => $value) { 
		$images[$i]['src'] = wp_get_attachment_image_src( $key, 'full');
		$thumb = wp_get_attachment_image_src( $key);
		//if thumb is not set skip
		if(!isset($thumb[0])) continue;
		$images[$i]['thumb'] = $thumb[0];
		$images[$i]['attachment_id'] = $key;
        $images[$i]['size'] = filesize( get_attached_file( $key ) );
        $images[$i]['name'] = basename($images[$i]['src'][0]); 
        $i++;
	}
?>
<script>
	var images = '<?php echo json_encode($images); ?>';
</script>
<?php }  ?>
<div id="media-uploader"  <?php  if(isset($field['required']) && $field['required'] == 1) { ?> data-required="required" <?php }
   if(isset($field['max_files']) && $field['max_files']>0) { ?> data-maxfiles="<?php echo $field['max_files']; ?>" <?php } ?> class="dropzone <?php echo esc_attr($data->key); ?>">
	<div class="dz-default dz-message"><span><i class="sl sl-icon-plus"></i> <?php esc_html_e('Click here or drop files to upload','listeo_core') ?></span></div>
</div>
<div data-elementkey="<?php echo esc_attr($key); ?>" id="media-uploader-ids">
	<?php 
		if(isset($field['value']) && is_array(($field['value']))){ 
			foreach($field['value'] as $key => $value) { ?>
	                <input id="_gallery<?php echo esc_attr($key); ?>" type="hidden" name="_gallery[<?php echo esc_attr($key); ?>]"  value="<?php echo esc_attr($value); ?>">
			<?php } 
		} ?>
</div>
<?php 
if(isset($field['required']) && $field['required'] == 1) { ?>
<div class="msf-gallery-error-message" style="display:none;">
	<?php esc_html_e('Gallery is required. Please upload at least one image.','listeo_core'); ?>
</div>
<?php } ?>

