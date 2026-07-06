<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
$field = $data->field;
$key = $data->key;

$default = (isset($field['default'])) ? $field['default'] : '' ;
$value = isset( $field['value'] ) ? esc_attr( $field['value'] ) : ''; 

?>

	<?php if ( isset( $field['unit'] ) ) { ?> <i class="data-unit"><?php echo $field['unit']; ?></i><?php } ?>
<input type="text" 
	class="input-text <?php echo esc_attr( isset( $field['class'] ) ? $field['class'] : $key ); ?>" 

	name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>"
	<?php if ( isset( $field['autocomplete'] ) && false === $field['autocomplete'] ) { echo ' autocomplete="off"'; } ?> 
	id="<?php echo esc_attr( $key ); ?>" 
	placeholder="<?php if(isset( $field['placeholder'] )) { echo esc_attr( $field['placeholder'] ); } ?>" 
	value="<?php echo esc_attr($value); ?>" 
	maxlength="<?php echo ! empty( $field['maxlength'] ) ? $field['maxlength'] : ''; ?>" 
	<?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> 
	<?php if ( isset( $field['unit'] ) ) echo 'data-unit="'.$field['unit'].'"'; ?> 

	/>
	
	