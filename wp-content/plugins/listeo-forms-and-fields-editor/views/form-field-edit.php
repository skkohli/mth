<div class="edit-form-field" style="display: none;">
	<?php $editor_type = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'default'; ?>
	<?php $editor_tab_type = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'default'; ?>

	<div id="listeo-field-<?php echo $field_key; ?>">

		<p class="name-container">
			<label for="label">Name <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="This is the name/ID of the field."></span></label>
			<input type="text" class="input-text" name="name[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($field['name']); ?>" />
		</p>
		<?php
		$blocked_fileds = array('_price', '_price_per', '_offer_type', '_property_type', '_rental_period', '_area', '_friendly_address', '_address', '_geolocation_lat', '_geolocation_long');

		?>

		<p class="field-id" <?php if (isset($field['id']) && in_array($field['id'], $blocked_fileds)) {
								echo 'style="display:none"';
							} ?>>
			<label for="label">ID <span class="tooltip-icon dashicons dashicons-editor-help" title="Do not edit if you don't know what you are doing :)"></span></label>
			<input type="text" class="input-text" name="id[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr(isset($field['id']) ? $field['id'] : ''); ?>" />
		</p>
		<?php if (isset($field['type']) && $field['type'] === 'header') : ?>
			<input type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($field['type']); ?>" />
		<?php else : ?>
			<p class="field-type">
				<label for="type">Type <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Set the type of the field"></span></label>

				<select name="type[<?php echo esc_attr($index); ?>]">
					<?php
					foreach ($field_types as $key => $type) {
						echo '<option value="' . esc_attr($key) . '" ' . selected($field['type'], $key, false) . '>' . esc_html($type) . '</option>';
					}
					?>
				</select>
			</p>
		<?php endif;  ?>
		<p class="field-icon">
			<label for="icon"><?php esc_html_e('Icon', 'listeo_core'); ?><span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Optional Icon for field, will be used on frontend card and in details section."></span></label>
			<select class="listeo-icon-select" name="icon[<?php echo esc_attr($index); ?>]" id="icon">
				<option value=" ">Empty Icon</option>
				<?php
				$faicons = listeo_fa_icons_list();
				$icon   = (isset($field['icon'])) ? $field['icon'] : '';
				foreach ($faicons as $key => $value) {
					if ($key) {
						echo '<option value="' . $key . '" ';
						if ($icon == $key) {
							echo ' selected="selected"';
						}
						echo '>' . $value . '</option>';
					}
				}

				?>

			</select>

		</p>
		<?php if (isset($field['type']) && $field['type'] === 'header') : ?>
			<div style="display: none;" class="header-options">
			<?php endif;
			?>

			
				<p class="field-required">
					<label for="required">Required <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Set this fields are required"></span></label>
					<input name="required[<?php echo esc_attr($index); ?>]" type="checkbox" <?php if (isset($field['required'])) checked($field['required'], 1, true); ?> value="1">
				</p>
			
			<?php
			$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'contact_tab';
			$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
			?>
			<?php if ( $current_page === 'listeo-fields-builder' && ! in_array( $tab, array( 'contact_tab', 'locations_tab', 'custom_tab' ), true ) ) : ?>
				<p class="field-showonfront">
					<label for="showonfront">Show on front card <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Shows the field on list/grid view of all listings"></span></label>
					<input name="showonfront[<?php echo esc_attr($index); ?>]" type="checkbox" <?php
					if (isset($field['showonfront'])) {
						checked($field['showonfront'], 1, true);
					} elseif (strpos($tab, 'tax-') !== 0) {
						echo 'checked="checked"';
					}
				?> value="1">
				</p>
			<?php endif; ?>

			<?php if (strpos($tab, 'tax-') === 0) { ?>
				<?php if (isset($field['type']) && !in_array($field['type'], array('file', 'repeatable', 'textarea', 'datetime'))) : ?>
					<p class="field-addtosearch">
						<label for="addtosearch">Add to search form <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="This will make it a searchable field"></span></label>
						<input name="addtosearch[<?php echo esc_attr($index); ?>]" type="checkbox" <?php if (isset($field['addtosearch'])) checked($field['addtosearch'], 1, true); ?> value="1">
					</p>
				<?php else : ?>
					<p class="field-addtosearch disabled" style="">
						<label for="addtosearch">Add to search form <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="This will make it a searchable field"></span></label>
						<input name="addtosearch[<?php echo esc_attr($index); ?>]" type="checkbox" value="0" disabled>
					</p>

				<?php endif; ?>
			<?php } ?>
			<?php if (in_array($tab, array('events_tab', 'service_tab', 'rental_tab', 'classifieds_tab'))) : ?>
				<p class="invert-container">
					<label for="invert">Show value before label <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Reverse the display of the field value and label"></span></label>
					<input name="invert[<?php echo esc_attr($index); ?>]" type="checkbox" <?php if (isset($field['invert'])) checked($field['invert'], 1, true); ?> value="1">
				</p>
			<?php endif; ?>
			<p class="field-desc">
				<label for="desc">Decription <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Description for the field, displayed in back-end"></span></label>
				<textarea rows="4" cols="10" class="input-text" name="desc[<?php echo esc_attr($index); ?>]"><?php if (isset($field['desc'])) {
																													echo esc_attr($field['desc']);
																												} ?></textarea>
			</p>
			<p class="field-display-as-list">
				<label for="display_as_list"><?php esc_html_e('Display options as list', 'listeo_core'); ?> <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Show each option on its own line instead of comma-separated inline. Recommended when using per-option icons."></span></label>
				<input name="display_as_list[<?php echo esc_attr($index); ?>]" type="checkbox" <?php if (isset($field['display_as_list'])) checked($field['display_as_list'], 1, true); ?> value="1">
			</p>
			<div class="field-options">
				<label for="options">Options <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Set the options for the field"></span></label>
				<?php
				$source = '';
				if (!isset($field['options_source'])) {
					if (isset($field['options_cb']) && !empty($field['options_cb'])) {
						$source = 'predefined';
					};
				} else {
					$source = '';
				};

				if (isset($field['options_source']) && empty($field['options_source'])) {
					if (isset($field['options_cb']) && !empty($field['options_cb'])) {
						$source = 'predefined';
					};
				}
				if (isset($field['options_source']) && !empty($field['options_source'])) {
					$source = $field['options_source'];
				} ?>
				<!-- 	<select name="options_source[<?php echo esc_attr($index); ?>]" class="field-options-data-source-choose">
				<option  value="">--Select Option--</option>
				<option <?php selected($source, 'predefined'); ?> value="predefined">Predefined List</option>
				<option <?php selected($source, 'custom'); ?> value="custom">Custom Options list</option>
			</select> -->
				<div class="options">

					<table class="field-options-custom">
						<thead>
							<tr>
								<td>Name</td>
								<td>Value<span style="color: #999;display: block;font-size: 13px;font-weight: 500;"> do not edit unless necessary</span>
								</td>
								<td>Icon</td>
								<td></td>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<td colspan="4">
									<a class="add-new-option-table" href="#">Add</a>
								</td>
							</tr>
						</tfoot>
						<tbody data-field="<?php
						$icon_options_html = '<option value=\' \'>Empty Icon</option>';
						$faicons_list = listeo_fa_icons_list();
						$faicons_list = apply_filters('listeo_option_icon_libraries', $faicons_list);
						foreach ($faicons_list as $fa_key => $fa_value) {
							if ($fa_key) {
								$icon_options_html .= '<option value=\'' . esc_attr($fa_key) . '\'>' . esc_html($fa_value) . '</option>';
							}
						}
						echo esc_attr("
					<tr>
					<td>
							<input type='text' class='input-text options input-value' name='options[{$index}][-1][value]' />
						</td>
						<td>
							<input type='text' class='input-text options input-name' name='options[{$index}][-1][name]' />
						</td>
						<td>
							<select class='listeo-option-icon-select' name='options[{$index}][-1][icon]'>{$icon_options_html}</select>
						</td>
						<td class='remove_row'>x</td>
					</tr>"); ?>">
							<?php if (isset($field['options']) && is_array($field['options'])) {
								$i = 0;
								$options_icons = isset($field['options_icons']) ? $field['options_icons'] : array();
								if (!isset($faicons_list)) {
									$faicons_list = listeo_fa_icons_list();
									$faicons_list = apply_filters('listeo_option_icon_libraries', $faicons_list);
								}
								foreach ($field['options'] as $key => $value) {
									$current_icon = isset($options_icons[$key]) ? $options_icons[$key] : '';
							?>
									<tr>
										<td>
											<input type="text" value="<?php echo esc_attr($value); ?>" class="input-text options input-value" name="options[<?php echo esc_attr($index); ?>][<?php echo esc_attr($i); ?>][value]" />
										</td>
										<td>
											<input type="text" value="<?php echo esc_attr($key); ?>" class="input-text options input-name" name="options[<?php echo esc_attr($index); ?>][<?php echo esc_attr($i); ?>][name]" />
										</td>
										<td>
											<select class="listeo-option-icon-select" name="options[<?php echo esc_attr($index); ?>][<?php echo esc_attr($i); ?>][icon]">
												<option value=" "><?php esc_html_e('Empty Icon', 'listeo_core'); ?></option>
												<?php foreach ($faicons_list as $fa_key => $fa_value) {
													if ($fa_key) {
														echo '<option value="' . esc_attr($fa_key) . '"';
														if ($current_icon == $fa_key) {
															echo ' selected="selected"';
														}
														echo '>' . esc_html($fa_value) . '</option>';
													}
												} ?>
											</select>
										</td>
										<td class="remove_row">x</td>
									</tr>
							<?php
									$i++;
								}
							}; ?>
						</tbody>
					</table>
				</div>
			</div>
			<p class="listeo-editor-default-field">

				<?php if (isset($field['type']) && $field['type'] === 'checkbox') : ?>
					<label for="">Displayed value <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Displayed value for the checkbox field, default is 'Yes'"></span></label>
				<?php else : ?>
					<label for="">Default value <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Default value for the field, used if no other value is set"></span></label>
				<?php endif; ?>
				<input type="text" class="input-text" name="default[<?php echo esc_attr($index); ?>]" value="<?php if (isset($field['default'])) {
																													echo esc_attr($field['default']);
																												} ?>" />
			</p>
			<p class="listeo-editor-placeholder-field">
				<label for="">Placeholder value <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Placeholder value for the field, use as example"></span></label>
				<input type="text" class="input-text" name="placeholder[<?php echo esc_attr($index); ?>]" value="<?php if (isset($field['placeholder'])) {
																														echo esc_attr($field['placeholder']);
																													} ?>" />
			</p>
			<p class="listeo-editor-css-field">
				<label for="">CSS class <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Add custom CSS class for the field"></span></label>
				<input type="text" class="input-text" name="css[<?php echo esc_attr($index); ?>]" value="<?php if (isset($field['css'])) {
																												echo esc_attr($field['css']);
																											} ?>" />
			</p>
			<p class="listeo-editor-width-field">
				<label for="">Width <span class="tooltip-icon dashicons dashicons-editor-help" data-tooltip="Select the width of the field"></span></label>

				<select name="width[<?php echo esc_attr($index); ?>]" id="">
					<option <?php if (isset($field['width'])) selected($field['width'], 'col-md-6'); ?> value="col-md-6">Half</option>
					<option <?php if (isset($field['width'])) selected($field['width'], 'col-md-12'); ?> value="col-md-12">Full-width</option>
				</select>

			</p>
			<?php if (isset($field['type']) && $field['type'] === 'header') : ?>
			</div>
		<?php endif; ?>

	</div>
</div>