<!-- Section -->
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$field = $data->field;
$key = $data->key;

if(isset($field['value'])) : ?>

	<input type="hidden" class="listeo-calendar-avail" value="<?php if( isset($field['value']['dates']) && !empty($field['value']['dates'])) { 
		echo esc_attr( $field['value']['dates'] );
		 } ?>" name="<?php echo esc_attr($key); ?>[dates]">
	<input type="hidden" class="listeo-calendar-price" value="<?php if( isset($field['value']['price'] ) && !empty($field['value']['price'])) { echo esc_attr($field['value']['price']); } ?>" name="<?php echo esc_attr($key); ?>[price]">
 	
<?php else : ?>

	<input type="hidden" class="listeo-calendar-avail" name="<?php echo esc_attr($key); ?>[dates]">
 	
 	<input type="hidden" class="listeo-calendar-price" name="<?php echo esc_attr($key); ?>[price]">

<?php endif; ?>
<?php 
	$calendar = new Listeo_Core_Calendar;
	echo $calendar->getCalendarHTML();
?>