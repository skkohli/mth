<!-- Section -->
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
	$field = $data->field;
	$key = $data->key;
	$name_prefix = isset($field['name_prefix']) ? $field['name_prefix'] : '_';

$days = listeo_get_days();

?>
<!-- Day -->
<?php

 foreach ($days as $id => $dayname) { 
 	
 		$opening_val = (isset($field['value'][$id.'_opening'])) ? $field['value'][$id.'_opening'] : false;
 		$closing_val = (isset($field['value'][$id.'_closing'])) ? $field['value'][$id.'_closing'] : false;
 		$count = 0;

 		?>
		<div class="row opening-day">
			<div class="row">
			<div class="col-md-2">
				<h5><?php echo esc_html($dayname) ?></h5>
				<span class='day_hours_reset'><?php esc_html_e('Clear Time','listeo_core') ?></span>
				<a href="#" data-count="<?php echo $count; ?>" data-id="<?php echo $id; ?>" data-dayname="<?php echo sanitize_title($dayname); ?>" data-name-prefix="<?php echo esc_attr($name_prefix); ?>" class="opening-day-add-hours"><?php esc_html_e('Add More','listeo_core'); ?></a>
			</div>
			<?php 
			if(is_array($opening_val)) { 
				
				foreach ($opening_val as $key => $opening) { 
				 if($count>0) { 
				 	?>
				 	<div class="row">
					 	<div class="col-md-2 opening-day-tools"><br><a class="opening-day-remove button" data-remove="<?php echo sanitize_title($dayname); ?>-opening-hours-row<?php echo $count; ?>" href="#"><?php esc_html_e('Remove','listeo_core'); ?></a>
					 	</div>
				 <?php } ?>
				 	<div class="col-md-5 <?php echo sanitize_title($dayname); ?>-opening-hours-row<?php echo $count; ?>">
					
						<input type="text" class="listeo-flatpickr" name="<?php echo esc_attr($name_prefix . $id); ?>_opening_hour[]" placeholder="<?php esc_html_e('Opening Time','listeo_core'); ?>" value="<?php echo esc_attr($opening); ?>">

					</div>

					<div class="col-md-5 <?php echo sanitize_title($dayname); ?>-opening-hours-row<?php echo $count; ?>" >

						<input type="text" class="listeo-flatpickr" name="<?php echo esc_attr($name_prefix . $id); ?>_closing_hour[]" placeholder="<?php esc_html_e('Closing Time','listeo_core'); ?>" value="<?php echo esc_attr($closing_val[$key]); ?>">
					
					</div>	
				</div>	
				<?php 
				$count++;
				} 
			} else { ?>
				
					<div class="col-md-5">
						<input type="text" class="listeo-flatpickr" name="<?php echo esc_attr($name_prefix . $id); ?>_opening_hour[]" placeholder="<?php esc_html_e('Opening Time','listeo_core'); ?>" value="<?php echo esc_attr($opening_val); ?>">

					</div>
					<div class="col-md-5">
						<input type="text" class="listeo-flatpickr" name="<?php echo esc_attr($name_prefix . $id); ?>_closing_hour[]" placeholder="<?php esc_html_e('Closing Time','listeo_core'); ?>" value="<?php echo esc_attr($closing_val); ?>">
						
					</div>	
				</div>	
			<?php } ?>
 
			
				
			
		</div>
		<!-- Day / End -->
<?php } ?>
							