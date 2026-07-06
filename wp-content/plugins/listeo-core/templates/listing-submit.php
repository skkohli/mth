<?php

/**
 * listing Submission Form
 */
if (! defined('ABSPATH')) exit;

if (isset($_GET["action"]) && $_GET["action"] == 'edit' && !listeo_core_if_can_edit_listing($data->listing_id)) { ?>
	<div class="notification closeable notice">
		<?php esc_html_e('You can\'t edit that listing', 'listeo_core'); ?>
	</div>
<?php
	return;
}
$current_user = wp_get_current_user();
$roles = $current_user->roles;
$role = array_shift($roles);
if (!in_array($role, array('administrator', 'admin', 'owner', 'seller'))) :
	$template_loader = new Listeo_Core_Template_Loader;
	$template_loader->get_template_part('account/owner_only');
	return;
endif;

/* Get the form fields */
$fields = array();
if (isset($data)) :
	$fields	 	= (isset($data->fields)) ? $data->fields : '';
endif;

/* Determine the type of form */
if (isset($_GET["action"])) {
	$form_type = $_GET["action"];
} else {
	$form_type = 'submit';
}

?>

<?php
if (isset($_POST['_listing_type'])) {
	$listing_type = $_POST['_listing_type'];
} else {
	$listing_type = get_post_meta($data->listing_id, '_listing_type', true);
	if (empty($listing_type)) {
		// Get dynamic listing types
		if (function_exists('listeo_core_custom_listing_types')) {
			$custom_types_manager = listeo_core_custom_listing_types();
			$available_types = $custom_types_manager->get_listing_types(true); // Get active types only
			if (!empty($available_types)) {
				if (count($available_types) == 1) {
					$listing_type = $available_types[0]->slug;
				} else {
					$listing_type = $available_types[0]->slug; // Use first available type
				}
			} else {
				$listing_type = 'service'; // Final fallback if no types configured
			}
		} else {
			// Fallback to old system if custom types manager not available
			$listing_types = get_option('listeo_listing_types', array('service', 'rental', 'event'));
			if (is_array($listing_types) && sizeof($listing_types) == 1) {
				$listing_type = $listing_types[0];
			} else {
				$listing_type = 'service';
			}
		}
	}
}

$steps = get_option("listeo_submit_{$listing_type}form_steps");
if (empty($steps)) {
	$steps = get_option("listeo_submit_{$listing_type}_form_steps");
}

if (is_array($steps) && !empty($steps)) {
	$steps = json_encode($steps);
}

$steps_status = get_option("listeo_enable_{$listing_type}form_steps");


?>

<div class="submit-page <?php echo esc_attr('type-' . $listing_type);
						if ($steps_status === 'on' && !empty($steps)) {
							echo ' multi-step-form';
						} ?>">
	<?php if ($form_type === 'edit') {
	?>
		<div class="notification closeable notice">
			<p><?php esc_html_e('You are currently editing:', 'listeo_core');
				if (isset($data->listing_id) && $data->listing_id != 0) {
					$listing = get_post($data->listing_id);
					echo ' <a href="' . get_permalink($data->listing_id) . '">' . $listing->post_title . '</a>';
				} ?></p>
		</div>
	<?php } ?>
	<?php
	if (isset($data->listing_edit) && $data->listing_edit) {
	?>
		<div class="notification closeable notice">
			<?php printf('<p><strong>' . __("You are editing an existing listing. %s", 'listeo_core') . '</strong></p>', '<a href="?new=1&key=' . $data->listing_edit . '">' . __('Add A New Listing', 'listeo_core') . '</a>'); ?>
		</div>
	<?php }

	/**
	 * Fires after the top notices and before the submit-listing form markup.
	 *
	 * Plugins can use this to render contextual info boxes (e.g. resource
	 * management shortcuts) between the "You are currently editing" notice
	 * and the form itself.
	 *
	 * @param object $data      Form data passed to the template.
	 * @param string $form_type 'submit' or 'edit'.
	 */
	do_action( 'listeo_submit_listing_before_form', $data, $form_type );

	?>
	<form action="<?php echo esc_url($data->action); ?>" method="post" id="submit-listing-form" class="listing-manager-form <?php if ($form_type === 'edit') {
																																echo "listeo-edit-form";
																															} ?>" enctype="multipart/form-data">

		<?php if (!empty($steps)) { ?> <input type="hidden" id="listeo_form_steps_json" name="listeo_form_steps_json" value="<?php echo esc_attr($steps); ?>"><?php } ?>


		<?php
		$section_count = 0;
		foreach ($fields as $key => $section) :
			$section_count++;
			$is_switchable = false;
			if (isset($section['onoff']) && $section['onoff'] == true) {
				$is_switchable = true;
			}

		?>
			<!-- Section -->
			<?php
			if (isset($data->listing_id)) {
				$switcher_value = get_post_meta($data->listing_id, '_' . $key . '_status', true);
				if (is_array($switcher_value)) {
					$switcher_value = array_shift($switcher_value);
				}
			} else {
				$switcher_value = false;
			}
			if ($section_count == 2) { ?>
				<!-- Section for dynamic custom fields that will be loaded via JS -->
				<div class="add-listing-section row custom-term-features" style="display: none;">
					<div class="add-listing-headline">
						<h3>
							<?php esc_html_e('Custom Field', 'listeo_core'); ?>
						</h3>
					</div>
					<div class="custom-term-features-content">

					</div>
				</div>

			<?php } ?>

			<div class="add-listing-section  row <?php echo esc_attr(' ' . $key . ' ');
													if (get_option('listeo_dynamic_features') == 'on') {
														echo "dynamic-features";
													}
													if ($is_switchable && !empty($switcher_value)) {
														echo esc_attr('switcher-on');
													}
													if ($is_switchable) {
														echo esc_attr(' has-switcher');
													}  ?>">

				<!-- Headlinea -->
				<div class="add-listing-headline <?php if (isset($section['class'])) echo esc_html($section['class']); ?>">
					<h3>
						<?php if (isset($section['icon']) && !empty($section['icon'])) : ?><i class="<?php echo esc_html($section['icon']); ?>"></i> <?php endif; ?>
						<?php
						/**
						 * Filters a submit-listing section title.
						 *
						 * @param string $title   Section title.
						 * @param string $key     Section key (e.g. 'basic_prices', 'slots').
						 * @param array  $section Full section definition.
						 */
						if (isset($section['title'])) echo esc_html(apply_filters('listeo_core_submit_section_title', $section['title'], $key, $section)); ?>
						<?php if ($key == "slots"): ?>
							<br><span id="add-listing-slots-notice"><?php esc_html_e("By default booking widget in your listing has time picker. Enable this section to configure time slots.", 'listeo_core'); ?> </span>
						<?php endif; ?>
						<?php if ($key == "availability_calendar"): ?>
							<br><span id="add-listing-slots-notice"><?php esc_html_e("Click date in calendar to mark the day as unavailable.", 'listeo_core'); ?> </span>
						<?php endif; ?>
					</h3>
					<?php if ($is_switchable) : ?>
						<!-- Switcher -->
						<?php
						if (isset($data->listing_id)) {
							$value = get_post_meta($data->listing_id, '_' . $key . '_status', true);
							//if value is array, take the first key value
							if (is_array($value)) {
								$value = array_shift($value);
							}
							if ($value === false && isset($section['onoff_state']) && $section['onoff_state'] == 'on') {
								$value = 'on';
							}
						} else {
							$value = false;

							if (isset($section['onoff_state']) && $section['onoff_state'] == 'on') {
								$value = 'on';
							}
						}

						?>
						<label class="switch"><input <?php checked($value, 'on') ?> id="_<?php echo esc_attr($key) . '_status'; ?>" name="_<?php echo esc_attr($key) . '_status'; ?>" type="checkbox"><span class="slider round"></span></label>
					<?php endif; ?>

				</div>
				<?php if ($key == "booking"): ?>
					<div class="notification notice margin-top-40 margin-bottom-20">

						<p><?php esc_html_e("By turning on switch on the right, you'll enable booking feature, it will add Booking widget on your listing. You'll see more configuration settings below.", 'listeo_core'); ?> </p>

					</div>
				<?php endif; ?>
				<?php if ($key == "location"): ?>


					<div class="col-md-12">
						<div id="submit_map"></div>
					</div>

				<?php endif; ?>
				<?php
				/**
				 * Fires after a section's headline and built-in notices,
				 * before the switcher content / fields.
				 *
				 * Use to inject contextual info boxes (e.g. "these act as defaults").
				 *
				 * @param string $key     Section key.
				 * @param array  $section Section definition.
				 * @param object $data    Form data passed to the template.
				 */
				do_action('listeo_core_submit_section_notice', $key, $section, $data);
				?>
				<?php if ($is_switchable) : ?>
					<div class="switcher-content">
					<?php endif; ?>
					<?php foreach ($section['fields'] as $key => $field) :

					?>

						<?php if (isset($field['type']) && $field['type'] == "skipped") {
							continue;
						}
						$field['submit_type'] = $listing_type;

						// if( isset($field['before_row']) ) : 
						// 	echo $field['before_row'].' <!-- before row '.$field['label'].' -->';
						// endif; 
						?>
						<?php
						if (isset($field['name'])) {

							if (isset($field['render_row_col']) && !empty($field['render_row_col'])) :
								listeo_core_render_column($field['render_row_col'], $field['name'], $field['type']);
							else:
								listeo_core_render_column(12, $field['name'], $field['type']);
							endif;
						}
						?>
						<?php if (isset($field['type']) && $field['type'] != 'hidden') : ?>

							<label class="label-<?php echo esc_attr($key); ?>" for="<?php echo esc_attr($key); ?>">
								<?php echo stripslashes($field['label']) . apply_filters('submit_listing_form_required_label', (isset($field['required']) && !empty($field['required'])) ? '<i>*</i>' : ' <small>' . esc_html__('(optional)', 'listeo_core') . '</small>', $field); ?>
								<?php if (isset($field['tooltip']) && !empty($field['tooltip'])) { ?>
									<i class="tip" data-tip-content="<?php (esc_attr_e(stripslashes($field['tooltip']))); ?>"></i>
								<?php } ?>
							</label>
						<?php endif; ?>

						<?php
						$template_loader = new Listeo_Core_Template_Loader;
						if (!isset($field['type'])) {
							$field['type'] = 'hidden';
						}
						$template_loader->set_template_data(array('key' => $key, 'field' => $field,))->get_template_part('form-fields/' . $field['type']);
						
						if(isset($field['name'])) { ?>
					</div>
					<?php }
						// if( isset($field['render_row_col']) && !empty($field['render_row_col']) ) : 
						// 	echo "</div>  <!-- close row ".$field['name']." -->";
						// else:
						// 	echo "</div>  <!-- close row ".$field['name']." -->";

						// endif; 
					?>


				<?php endforeach; ?>
				<?php if ($is_switchable) : ?>
			</div>
		<?php endif; ?>
</div> <!-- end section  -->


<?php endforeach; ?>


<div class="divider margin-top-40"></div>

<p>
	<input type="hidden" name="_listing_type" value="<?php echo esc_attr($listing_type); ?>">
	<input type="hidden" name="listeo_core_form" value="<?php echo $data->form; ?>" />
	<input type="hidden" name="listing_id" value="<?php echo esc_attr($data->listing_id); ?>" />
	<input type="hidden" name="step" value="<?php echo esc_attr($data->step); ?>" />



	<button type="submit" value="<?php echo esc_attr($data->submit_button_text); ?>" name="submit_listing" class="button margin-top-20"><i class="fa fa-arrow-circle-right"></i> <?php echo esc_attr($data->submit_button_text); ?></button>

</p>

</form>
</div>