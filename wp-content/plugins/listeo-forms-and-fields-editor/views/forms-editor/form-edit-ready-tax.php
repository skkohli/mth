<div class="edit-form-field" style="display:none">
    <div id="listeo-field-<?php echo $field_key; ?>">

        <p class="type-container">
            <label for="label">Type</label>
            <select class="field-type-selector" name="type[<?php echo esc_attr($index); ?>]">
                <option value="multi-checkbox">Checkboxes in column</option>
                <option value="multi-checkbox-row">Checkboxes in row</option>
                <option selected="selected" value="select-taxonomy">Select Taxonomy</option>
                <option value="drilldown-taxonomy">Drilldown Taxonomy</option>
            </select>
        </p>
        <p class="multi-container">
            <label for="multi">Enable Multi Select</label>
            <input name="multi[<?php echo $index; ?>]" type="checkbox" <?php checked($multi, 1, true); ?> value="1">
        </p>

        <?php $hide_all = (isset($field['hide_all'])) ? $field['hide_all'] : false; ?>
        <p class="hide-all-container drilldown-only" style="<?php echo (isset($field['type']) && strpos($field['type'], 'drilldown') !== false) ? '' : 'display:none;'; ?>">
            <label for="hide_all"><?php esc_html_e('Hide "All" option', 'listeo_core'); ?> <span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('Remove the "All in..." option from drilldown. Users will have to select a specific category.', 'listeo_core'); ?>"></span></label>
            <input name="hide_all[<?php echo $index; ?>]" type="checkbox" <?php checked($hide_all, 1, true); ?> value="1">
        </p>
        <p>
            <label for="">Default value</label>
            <input type="text" class="input-text" name="default[<?php echo esc_attr($index); ?>]" value="<?php if (isset($field['default'])) {
                                                                                                                echo esc_attr($field['default']);
                                                                                                            } ?>" />
        </p>
        <p class="placeholder-container">
            <label for="label">Placeholder <span class="dashicons dashicons-editor-help" title="Text that is displayed in the input field before the user enters something"></span></label>
            <input name="placeholder[<?php echo esc_attr($index); ?>]" type="text" value="Any <?php echo esc_attr($tax->label); ?>">
        </p>

        <p style="display:none;" class="name-container">
            <label for="label">Name</label>
            <input name="name[<?php echo esc_attr($index); ?>]" readonly type="text" value="<?php if (isset($tax->name)) {
                                                                                                    echo esc_attr('tax-' . $tax->name);
                                                                                                } ?>">
        </p>

        <?php if ($tab != 'search_on_home_page') : ?>
            <input type="hidden" class="place_hidden" name="place[<?php echo esc_attr($index); ?>]" value="main">
        <?php endif; ?>

        <?php if ($tab == 'search_on_half_map') : ?>
            <p class="class-container">
                <label for="label">Field Width <span class="dashicons dashicons-editor-help" title="Field’s width using Bootstrap columns"></span> </label>
                <select class="field-edit-class-select" name="class[<?php echo esc_attr($index); ?>]">
                    <option value=" col-fs-6">50%</option>
                    <option value=" col-fs-12">100%</option>
                </select>
            </p>
        <?php endif; ?>

        <?php $multi = false; ?>
        <p class="priority-container" style="display: none">
            <label for="label">Priority</label>
            <input class="priority_field" name="priority[<?php echo esc_attr($index); ?>]" type="text" value="99">
        </p>


        <p class="css-class-container">
            <label for="label">Custom CSS Class</label>
            <input name="css_class[<?php echo esc_attr($index); ?>]" type="text" value="">
        </p>

        <div class="field-options options-container" style="display: none;">
            <label for="options">Options <span class="dashicons dashicons-editor-help" title="Choose preset list or create your own list of options"></span></label>
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
            <select name="options_source[<?php echo esc_attr($index); ?>]" class="field-options-data-source-choose">
                <option value="">--Select Option--</option>
                <option <?php selected($source, 'predefined'); ?> value="predefined">Predefined List</option>
                <option <?php selected($source, 'custom'); ?> value="custom">Custom Options list</option>
            </select>
            <div class="options ">

                <select style="display: none" class="field-options-predefined" name="options_cb[<?php echo esc_attr($index); ?>]" id="">
                    <option value="">--Select Option--</option>
                    <?php foreach ($predefined_options as $key => $value) { ?>
                        <option <?php if (isset($field['options_cb'])) {
                                    selected($field['options_cb'], $key);
                                } ?> value="<?php echo esc_attr($key) ?>"><?php echo esc_html($value); ?></option>
                    <?php } ?>

                </select>
                <table style="display: none" class="field-options-custom widefat fixed">
                    <thead>
                        <tr>
                            <td>Displayed Name</td>
                            <td>Searched Value</td>
                            <td></td>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="3">
                                <a class="add-new-option-table button-primary" href="#">Add row</a>
                            </td>
                        </tr>
                    </tfoot>

                    <tbody data-field="<?php echo esc_attr('<tr><td><input type="text" class="input-text options" name="options[' . esc_attr($index) . '][<?php echo esc_attr( $index ); ?>][name]" /></td><td><input type="text" class="input-text options" name="options[' . esc_attr($index) . '][<?php echo esc_attr( $index ); ?>][value]" /></td><td><a class="remove-row button" href="#">Remove</a></td></tr>'); ?>">

                    </tbody>
                </table>
            </div>

        </div>
        <p class="taxonomy-container" style="display:block;">
            <label for="label">or Taxonomy <span class="dashicons dashicons-editor-help" title="Populate element with one of thelisting taxonomy terms"></span></label>
            <?php $selected_tax = $tax->name; ?>
            <select name="field_taxonomy[<?php echo esc_attr($index); ?>]" id="">
                <option value="">--select--</option>
                <?php
                $taxonomy_objects = get_object_taxonomies('listing', 'objects');
                foreach ($taxonomy_objects as $tax) {
                    echo '<option ' . selected($selected_tax, $tax->name) . ' value="' . $tax->name . '">' . $tax->label . '</option>';
                }
                ?>
            </select>
        </p>

        <p class="max-container" style="display:none">
            <label for="label">Max <span class="dashicons dashicons-editor-help" title="Use only for numeric types like area, price, etc."></span></label>
            <input name="max[<?php echo esc_attr($index); ?>]" type="text" value="<?php if (isset($field['max'])) {
                                                                                        echo esc_attr($field['max']);
                                                                                    } ?>">
            <small>Use "auto" to get minimum value from all properties.</small>
        </p>
        <p class="min-container" style="display:none">
            <label for="label">Min <span class="dashicons dashicons-editor-help" title="Use only for numeric types like area, price, etc."></span></label>
            <input name="min[<?php echo esc_attr($index); ?>]" type="text" value="<?php if (isset($field['min'])) {
                                                                                        echo esc_attr($field['min']);
                                                                                    } ?>">
            <small>Use "auto" to get minimum value from all properties</small>
        </p>
        <p class="step-container" style="display:none">
            <label for="label">Step</label>
            <input name="step[<?php echo esc_attr($index); ?>]" type="number" value="<?php if (isset($field['step'])) {
                                                                                            echo esc_attr($field['step']);
                                                                                        } ?>">
            <small>Set step value high enough to make less then 40 steps, longer lists are not recomended.</br>.</small>
        </p>

    </div>

</div>