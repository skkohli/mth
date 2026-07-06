<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
$field = $data->field;
$key = $data->key;
?>

<?php if(isset($field['form_type']) && $field['form_type'] == 'registration') { ?>
	<label class="listeo_core-switch"><?php echo $field['placeholder']; ?></label>	
<?php } ?>

<!-- Rounded switch -->
<div class="switch_box box_1">
	<input type="checkbox" 
	class="input-checkbox switch_1" 

	name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>"
	
	id="<?php echo esc_attr( $key ); ?>" 
	value="on"
	<?php isset( $field['value'] ) ? checked($field['value'],'on') : ''; ?> 
	maxlength="<?php echo ! empty( $field['maxlength'] ) ? $field['maxlength'] : ''; ?>" 
	<?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> 
	<?php if ( isset( $field['unit'] ) ) echo 'data-unit="'.$field['unit'].'"'; ?> 

	/>
</div>

