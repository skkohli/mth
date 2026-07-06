<div class="edit-form-field" style="display:none;">
    <div id="listeo-field-<?php echo $field_key; ?>">

        <?php $editor_type = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'default'; ?>

        <?php if ($editor_type == 'listeo-user-fields-registration') { ?>
            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="<?php echo $field['type']; ?>">
        <?php } else { ?>
            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="<?php echo $field['type']; ?>">
            <?php if (in_array($field['name'], array('keyword_search'))) { ?>
                <input class="field-type-selector" type="text" name="type[<?php echo esc_attr($index); ?>]" value="text">
            <?php } ?>

            <?php if (in_array($field['name'], array('location'))) { ?>
                <input class="field-type-selector" type="text" name="type[<?php echo esc_attr($index); ?>]" value="location">
            <?php } ?>

            <!-- <?php if (in_array($field['type'], array('select', 'select_multiple', 'multicheck_split'))) { ?>
                <p class="type-container">
                    <label for="label">Type</label>

                    <select class="field-type-selector" name="type[<?php echo esc_attr($index); ?>]">
                        <option <?php selected($field['type'], 'multi-checkbox'); ?> value="multi-checkbox">Checkboxes in column</option>
                        <option <?php selected($field['type'], 'multi-checkbox-row'); ?> value="multi-checkbox-row">Checkboxes in row</option>
                        <option <?php selected($field['type'], 'select-taxonomy'); ?> value="select-taxonomy">Select Taxonomy</option>
                    </select>
                </p>
            <?php } ?> -->

            <?php if (in_array($field['type'], array('select', 'select_multiple', 'multicheck_split'))) { ?>

                <p class="type-container">
                    <label for="label">Type</label>

                    <select class="field-type-selector" name="type[<?php echo esc_attr($index); ?>]">
                        <option <?php selected($field['type'], 'multicheck_split'); ?> value="multi-checkbox">Checkboxes in column</option>
                        <option <?php selected($field['type'], 'select'); ?> value="select">Select</option>
                        <option <?php selected($field['type'], 'select_multiple'); ?> value="select_multiple">Select Multiple</option>
                    </select>
                </p>
            <?php } ?>

            <?php if (in_array($field['name'], array('radius', 'search_radius'))) { ?>
                <input class="field-type-selector" type="text" name="type[<?php echo esc_attr($index); ?>]" value="radius">
            <?php } ?>

            <?php if (in_array($field['name'], array('_price'))) { ?>
                <input class="field-type-selector" type="text" name="type[<?php echo esc_attr($index); ?>]" value="slider">
            <?php } ?>
            <?php if (in_array($field['name'], array('submit'))) { ?>
                <input class="field-type-selector" type="text" name="type[<?php echo esc_attr($index); ?>]" value="submit">
            <?php } ?>

            <?php if (in_array($field['id'], array('_listing_type', '_max_guest'))) { ?>
                <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="select">
            <?php } ?>
            <?php if (in_array($field['type'], array('date-range'))) { ?>
                <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="date-range">
            <?php } ?>
            <?php if (in_array($field['name'], array('ai_search')) || in_array($field['type'], array('ai_search'))) { ?>
                <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="ai_search">
            <?php } ?>

        <?php } ?>
        <p style="display:none;" class="name-container">
            <label for="label">Name</label>
            <input name="name[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['id'])) {
                                                                                echo esc_attr($field['id']);
                                                                            } ?>">
        </p>

        <p class="placeholder-container">
            <label for="label">Placeholder <span class="dashicons dashicons-editor-help" title="Text that is displayed in the input field before the user enters something"></span></label>
            <input name="placeholder[<?php echo $index; ?>]" type="text" value="<?php
                                                                                if (isset($field['placeholder'])) {
                                                                                    echo esc_attr($field['placeholder']);
                                                                                } else {
                                                                                    echo esc_attr($field['name']);
                                                                                } ?>">
        </p>
        <?php if (isset($field['type']) && $field['type'] == 'ai_search') { ?>
            <p class="button-action-container">
                <label for="button_action">AI Search Mode: <span class="dashicons dashicons-editor-help" title="Choose how AI search behaves: Full AI with button, popular searches only, or disabled"></span></label>
                <select name="button_action[<?php echo esc_attr($index); ?>]">
                    <?php 
                    $current_button_action = isset($field['button_action']) ? $field['button_action'] : 'quick_picks';
                    ?>
                    <option value="quick_picks" <?php selected($current_button_action, 'quick_picks'); ?>>AI Quick Picks + Suggestions</option>
                    <option value="popular_searches" <?php selected($current_button_action, 'popular_searches'); ?>>Show popular searches only</option>
                    <option value="disable" <?php selected($current_button_action, 'disable'); ?>>Disable</option>
                </select>
            </p>
        <?php } ?>

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
        <!--  <p class="multi-container">
            <label for="multi">Enable Multi Select</label>
            <input name="multi[<?php echo $index; ?>]" type="checkbox" <?php checked($multi, 1, true); ?> value="1">
        </p> -->

        <p class="priority-container" style="display: none">
            <label for="label">Priority</label>
            <input class="priority_field" name="priority[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['priority'])) {
                                                                                                        echo esc_attr($field['priority']);
                                                                                                    } ?>">
        </p>

        <?php if ($tab != 'search_on_home_page') : ?>
            <input type="hidden" class="place_hidden" name="place[<?php echo esc_attr($index); ?>]" value="main">
        <?php endif; ?>

        <p class="css-class-container">
            <label for="label">Custom CSS Class</label>
            <input name="css_class[<?php echo esc_attr($index); ?>]" type="text" value="<?php if (isset($field['class'])) {
                                                                                            echo esc_attr($field['class']);
                                                                                        } ?>">
        </p>

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

        <div class="field-options options-container" <?php if (in_array($field['type'], array('select', 'select_multiple', 'multicheck_split'))) { ?> style="display: block;" <?php } else { ?> style="display: none;" <?php } ?>>
            <label for="options">Options</label>

            <select name="options_source[<?php echo esc_attr($index); ?>]" class="field-options-data-source-choose">
                <option value="">--Select Option--</option>
                <option <?php selected($source, 'predefined'); ?> value="predefined">Predefined List</option>
                <option <?php selected($source, 'custom'); ?> value="custom">Custom Options list</option>
            </select>
            <div class="options ">

                <select <?php if ($source == 'predefined') { ?> style="display: block;" <?php } else { ?> style="display: none;" <?php } ?> class="field-options-predefined" name="options_cb[<?php echo esc_attr($index); ?>]" id="">
                    <option value="">--Select Option--</option>
                    <?php if (isset($predefined_options)) : ?>
                        <?php foreach ($predefined_options as $key => $value) { ?>
                            <option <?php if (isset($field['options_cb'])) {
                                        selected($field['options_cb'], $key);
                                    } ?> value="<?php echo esc_attr($key) ?>"><?php echo esc_html($value); ?></option>
                        <?php } ?>
                    <?php endif; ?>

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

                    <tbody data-field="<?php echo esc_attr('<tr><td><input type="text" class="input-text options" name="options[' . esc_attr($index) . '][-1][name]" /></td><td><input type="text" class="input-text options" name="options[' . esc_attr($index) . '][-1][value]" /></td><td>x</td></tr>'); ?>">
                        <?php if (isset($field['options']) && is_array($field['options'])) {
                            $i = 0;
                            foreach ($field['options'] as $key => $value) {
                        ?>
                                <tr>
                                    <td><input type="text" value="<?php echo esc_attr($key); ?>" class="input-text options" name="options[<?php echo esc_attr($index); ?>][<?php echo esc_attr($i); ?>][name]" /></td>
                                    <td>
                                        <input type="text" value="<?php echo esc_attr($value); ?>" class="input-text options" name="options[<?php echo esc_attr($index); ?>][<?php echo esc_attr($i); ?>][value]" />
                                    </td>
                                    <td class="remove_item">x</td>
                                </tr>
                        <?php
                                $i++;
                            }
                        }; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="taxonomy-container" style="display:none">
            <label for="label">or Taxonomy <span class="dashicons dashicons-editor-help" title="Populate element with one of thelisting taxonomy terms"></span></label>
            <?php $selected_tax = (isset($field['taxonomy']) && !empty($field['taxonomy'])) ? $field['taxonomy'] : false; ?>
            <select name="field_taxonomy[<?php echo $index; ?>]" id="">
                <option value="">--select--</option>
                <?php
                $taxonomy_objects = get_object_taxonomies('listing', 'objects');
                foreach ($taxonomy_objects as $tax) {
                    echo '<option ' . selected($selected_tax, $tax->name) . ' value="' . $tax->name . '">' . $tax->label . '</option>';
                }
                ?>
            </select>

        </p>

        <p class="max-container" <?php if ($field['type'] != 'input-select') {
                                        echo ' style="display:none"';
                                    } ?>>
            <label for="label">Max <span class="dashicons dashicons-editor-help" title="Use only for numeric types like area, price, etc."></span></label>
            <input name="max[<?php echo esc_attr($index); ?>]" type="text" value="<?php if (isset($field['max'])) {
                                                                                        echo esc_attr($field['max']);
                                                                                    } ?>">
            <small>Use "auto" to get minimum value from all properties.</small>
        </p>
        <p class="min-container" <?php if ($field['type'] != 'input-select') {
                                        echo ' style="display:none"';
                                    } ?>>
            <label for="label">Min <span class="dashicons dashicons-editor-help" title="Use only for numeric types like area, price, etc."></span></label>
            <input name="min[<?php echo esc_attr($index); ?>]" type="text" value="<?php if (isset($field['min'])) {
                                                                                        echo esc_attr($field['min']);
                                                                                    } ?>">
            <small>Use "auto" to get minimum value from all properties</small>
        </p>
        <p class="step-container" <?php if ($field['type'] != 'input-select') {
                                        echo ' style="display:none"';
                                    } ?>>
            <label for="label">Step</label>
            <input name="step[<?php echo esc_attr($index); ?>]" type="number" value="<?php if (isset($field['step'])) {
                                                                                            echo esc_attr($field['step']);
                                                                                        } ?>">
            <small>Set step value high enough to make less then 40 steps, longer lists are not recomended.</br>.</small>
        </p>
        <?php if (isset($field['key']) && in_array($field['key'], array('_price'))) { ?>

            <?php $state = false; ?>
            <p class="state-container">
                <label for="state">Check to make this filter enabled by default</label>
                <input name="state[<?php echo $index; ?>]" type="checkbox" <?php checked($state, 'on', true); ?> value="on">
            </p>
        <?php }   ?>

    </div>

</div>