<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
$field = $data->field;
$key = $data->key;

$default = (isset($field['default'])) ? $field['default'] : '' ;
$value = isset( $field['value'] ) ? esc_attr( $field['value'] ) : $default; 



?>


<?php if ( isset( $field['unit'] ) ) { ?> <i class="data-unit"><?php echo $field['unit']; ?></i><?php } ?>
<?php if( $field['name']=='_address') { ?>
	<div id="_address_wrapper" style="position: relative;">
<?php } ?>
<input type="text" 
	class="input-text <?php echo esc_attr( isset( $field['class'] ) ? $field['class'] : $key ); ?>" 

	name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>"
	<?php if ( isset( $field['autocomplete'] ) && false === $field['autocomplete'] ) { echo ' autocomplete="none"'; } ?> 
	id="<?php echo esc_attr( $key ); ?>" 
	placeholder="<?php if(isset( $field['placeholder'] )) { echo esc_attr( $field['placeholder'] ); } ?>" 
	value="<?php echo esc_attr($value); ?>" 
	
	<?php if( isset( $field['class'])  && $field['class'] == 'input-datetime') {
		?>
		readonly="readonly"
		<?php } ?>
	maxlength="<?php echo ! empty( $field['maxlength'] ) ? $field['maxlength'] : ''; ?>" 
	<?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> 
	<?php if ( isset( $field['unit'] ) ) echo 'data-unit="'.$field['unit'].'"'; ?> 

	/>

<?php if( $field['name']=='_address') { ?>
		<a href="#"><i title="<?php esc_html_e('Find My Location','listeo_core') ?>" class="tooltip left fa fa-map-marker"></i></a>
	<span class="type-and-click-btn"><?php esc_html_e('type and click here','listeo_core') ?></span>
	<span class="type-and-hit-enter"><?php esc_html_e('type and hit enter','listeo_core') ?></span>
</div>
<?php } ?>