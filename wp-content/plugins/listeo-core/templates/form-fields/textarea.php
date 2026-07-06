<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
$field = $data->field;
$key = $data->key;


if(isset($field['description']) && !empty($field['description'])){
	echo '<div class="notification closeable notice"><p class="description" id="'.$key.'-description">'.$field['description'].'</p></div>';
}

?>
<textarea
	class="input-textarea <?php echo esc_attr( isset( $field['class'] ) ? $field['class'] : $key ); ?>" 

	name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>"
	id="<?php echo esc_attr( $key ); ?>" 
	placeholder="<?php if(isset( $field['placeholder'] )) { echo esc_attr( $field['placeholder'] ); } ?>" 
	maxlength="<?php echo ! empty( $field['maxlength'] ) ? $field['maxlength'] : ''; ?>" 
	rows="4" cols="50"><?php echo isset( $field['value'] ) ? wp_kses_post( $field['value'] ) : ''; ?></textarea>
<div class="margin-top-30"></div>