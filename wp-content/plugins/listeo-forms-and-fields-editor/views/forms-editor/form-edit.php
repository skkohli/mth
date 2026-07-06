<div class="edit-form-field " style="display:none">
    <div id="listeo-field-<?php echo $field_key; ?>">


        <?php $editor_type = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'default'; ?>

        <?php if (in_array($field['name'], array('keyword_search'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="text">

        <?php } else if (in_array($field['name'], array('location', 'location_search'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="location">

        <?php } else if (substr($field['name'], 0, 3) == 'tax' && in_array($field['type'], array('multi-checkbox', 'multi-checkbox-row', 'select', 'select-taxonomy', 'drilldown-taxonomy'))) { ?>

            <p class="type-container">
                <label for="label">Type</label>

                <select class="field-type-selector" name="type[<?php echo esc_attr($index); ?>]">
                    <option <?php selected($field['type'], 'multi-checkbox'); ?> value="multi-checkbox">Checkboxes in column</option>
                    <option <?php selected($field['type'], 'multi-checkbox-row'); ?> value="multi-checkbox-row">Checkboxes in row</option>
                    <option <?php selected($field['type'], 'select-taxonomy'); ?> value="select-taxonomy">Select Taxonomy</option>
                    <option <?php selected($field['type'], 'drilldown-taxonomy'); ?> value="drilldown-taxonomy">Drilldown Taxonomy</option>
                </select>
            </p>

        <?php } else if (in_array($field['name'], array('radius', 'search_radius'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="radius">

        <?php } else if (in_array($field['name'], array('rating'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="rating">

        <?php } else if (in_array($field['name'], array('_price'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="slider">

        <?php } else if (in_array($field['name'], array('submit'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="submit">

        <?php } else if (in_array($field['name'], array('listing_type'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="select">

        <?php } else if (in_array($field['type'], array('date-range'))) { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="date-range">

        <?php } else { ?>

            <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="<?php echo $field['type']; ?>">

        <?php } ?>
        <?php if (in_array($field['type'], array('select', 'select_multiple', 'multicheck_split'))) { ?>

            <p class="type-container">
                <label for="label">Type</label>

                <select class="field-type-selector" name="type[<?php echo esc_attr($index); ?>]">
                    <option <?php selected($field['type'], 'multicheck_split'); ?> value="multi-checkbox">Checkboxes in column</option>
                    <option <?php selected($field['type'], 'select'); ?> value="select">Select</option>

                </select>
            </p>
        <?php } ?>
        <!-- 
       -->

        <p class="placeholder-container">
            <label for="label">Placeholder <span class="dashicons dashicons-editor-help" title="Text that is displayed in the input field before the user enters something"></span></label>
            <input name="placeholder[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['placeholder'])) {
                                                                                    echo esc_attr($field['placeholder']);
                                                                                } ?>">
        </p>

        <?php
        if ($field['type'] == 'date-range') { ?>
            <p class="date_range_type-container">
                <label for="label">Search date range search for listing type: <span class="dashicons dashicons-editor-help" title="Which type of listings you want to search using date selector"></span></label>
                <select name="date_range_type[<?php echo $index; ?>]" id="date_range_type">
                    <?php $current_date_range_type = isset($field['date_range_type']) ? $field['date_range_type'] : 'rental'; ?>
                    <option <?php selected($current_date_range_type, 'rental'); ?> value="rental">Rentals</option>
                    <option <?php selected($current_date_range_type, 'event'); ?> value="event">Event</option>
                    <option <?php selected($current_date_range_type, 'custom'); ?> value="custom">Allow user to decide</option>
                </select>
            </p>
        <?php } ?>
        <?php
        if ($field['type'] == 'ai_search') { ?>
            <p class="button_action-container">
                <label for="label">AI Search Mode: <span class="dashicons dashicons-editor-help" title="Choose how AI search behaves: Full AI with button, popular searches only, or disabled"></span></label>
                <select name="button_action[<?php echo $index; ?>]" id="button_action">
                    <?php 
                    $current_button_action = isset($field['button_action']) ? $field['button_action'] : 'quick_picks';
                    ?>
                    <option value="quick_picks" <?php selected($current_button_action, 'quick_picks'); ?>>AI Quick Picks + Suggestions</option>
                    <option value="popular_searches" <?php selected($current_button_action, 'popular_searches'); ?>>Show popular searches only</option>
                    <option value="disable" <?php selected($current_button_action, 'disable'); ?>>Disable</option>
                </select>
            </p>
        <?php } ?>



        <?php if ($editor_type == 'listeo-user-fields-registration') {
        ?>
            <?php $required = (isset($field['required'])) ? $field['required'] : false; ?>
            <p class="required-container">
                <label for="required">Required field</label>
                <input name="required[<?php echo $index; ?>]" type="checkbox" <?php checked($required, 1, true); ?> value="1">
            </p>


            <p class="type-container">
                <label for="label">Icon</label>

                <select class="field-type-selector" name="icon[<?php echo esc_attr($index); ?>]">
                    <option value="empty">Empty</option>
                    <?php
                    $icon = $field['icon'];

                    $faicons = listeo_fa_icons_list();

                    foreach ($faicons as $key => $value) {
                        if ($key) {
                            echo '<option value="fa fa-' . $key . '" ';
                            if ($icon == 'fa fa-' . $key) {
                                echo ' selected="selected"';
                            }
                            echo '>' . $value . '</option>';
                        }
                    }
                    $imicons = vc_iconpicker_type_iconsmind(array());

                    foreach ($imicons as $key => $icon_array) {
                        $key = key($icon_array);
                        $value = $icon_array[$key];
                        echo '<option value="' . $key . '" ';
                        if (isset($icon) && $icon == $key) {
                            echo ' selected="selected"';
                        }
                        echo '>' . $value . '</option>';
                    }
                    ?>

                </select>
            </p>
        <?php } ?>
        <p style="display:none;" class="name-container">
            <label for="label">Name</label>
            <input name="name[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['name'])) {
                                                                                echo esc_attr($field['name']);
                                                                            } ?>">
        </p>

        <?php if ($tab == 'search_on_half_map') : ?>
            <?php if (isset($field['class']) && $field['place'] != 'panel') : ?>
                <p class="class-container">
                    <label for="label">Field Width <span class="dashicons dashicons-editor-help" title="Field’s width using Bootstrap columns"></span> </label>
                    <select class="field-edit-class-select" name="class[<?php echo $index; ?>]">

                        <option <?php selected($field['class'], 'col-fs-6'); ?> value=" col-fs-6">50%</option>
                        <option <?php selected($field['class'], 'col-fs-12'); ?> value=" col-fs-12">100%</option>

                    </select>

                </p>


            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab == 'sidebar_search') : ?>
            <input type="hidden" name="class[<?php echo $index; ?>]" value="col-md-12">

        <?php endif; ?>

        <?php $multi = (isset($field['multi'])) ? $field['multi'] : false; ?>
        <p style="display: none" class="multi-container">
            <label for="multi">Enable Multi Select</label>
            <input name="multi[<?php echo $index; ?>]" type="checkbox" <?php checked($multi, 1, true); ?> value="1">
        </p>

        <?php $hide_all = (isset($field['hide_all'])) ? $field['hide_all'] : false; ?>
        <p style="<?php echo in_array($field['type'], array('drilldown-taxonomy', 'drilldown-listing-types')) ? '' : 'display:none;'; ?>" class="hide-all-container">
            <label for="hide_all"><?php esc_html_e('Hide "All" option', 'listeo_core'); ?> <span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('Remove the "All in..." option from drilldown. Users will have to select a specific category.', 'listeo_core'); ?>"></span></label>
            <input name="hide_all[<?php echo $index; ?>]" type="checkbox" <?php checked($hide_all, 1, true); ?> value="1">
        </p>
        <p>
            <label for="">Default value</label>
            <input type="text" class="input-text" name="default[<?php echo esc_attr($index); ?>]" value="<?php if (isset($field['default'])) {
                                                                                                                echo esc_attr($field['default']);
                                                                                                            } ?>" />
        </p>

        <p class="priority-container" style="display: none">
            <label for="label">Priority</label>
            <input class="priority_field" name="priority[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['priority'])) {
                                                                                                        echo esc_attr($field['priority']);
                                                                                                    } ?>">
        </p>

        <?php if ($tab != 'search_on_home_page') : ?>

            <input type="hidden" class="place_hidden" name="place[<?php echo esc_attr($index); ?>]" value="<?php echo $field['place']; ?>">
        <?php endif; ?>

        <p class="css-class-container">
            <label for="label">Custom CSS Class</label>
            <input name="css_class[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['css_class'])) {
                                                                                    echo esc_attr($field['css_class']);
                                                                                } ?>">
        </p>
        <?php if (in_array($field['type'], array('text', 'location', 'select', 'hidden'))) {  ?>
            <p class="css-class-container">
                <label for="label">Default value</label>
                <input name="default[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['default'])) {
                                                                                    echo esc_attr($field['default']);
                                                                                } ?>">
            </p>
        <?php } ?>

        <div class="field-options options-container" style="display: none">
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
                    <?php if ($predefined_options) : ?>
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

                            <td>Searched Value</td>
                            <td>Displayed Name</td>
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

                    <tbody data-field="<?php echo esc_attr('<tr><td><input type="text" class="input-text options" name="options[' . esc_attr($index) . '][-1][name]" /></td><td><input type="text" class="input-text options" name="options[' . esc_attr($index) . '][-1][value]" /></td><td><a class="remove-row button" href="#">Remove</a></td></tr>'); ?>">
                        <?php if (isset($field['options']) && is_array($field['options'])) {
                            $i = 0;
                            foreach ($field['options'] as $key => $value) {
                        ?>
                                <tr>
                                    <td><input type="text" value="<?php echo esc_attr($key); ?>" class="input-text options" name="options[<?php echo esc_attr($index); ?>][<?php echo esc_attr($i); ?>][name]" /></td>
                                    <td>
                                        <input type="text" value="<?php echo esc_attr($value); ?>" class="input-text options" name="options[<?php echo esc_attr($index); ?>][<?php echo esc_attr($i); ?>][value]" />
                                    </td>
                                    <td><a class="remove-row button" href="#">Remove</a></td>
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

        <p class="max-container" <?php if ($field['type'] != 'radius') { ?>style="display:none" <?php } ?>>
            <label for="label">Max <span class="dashicons dashicons-editor-help" title="Use only for numeric types like area, price, etc."></span></label>
            <input name="max[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['max'])) {
                                                                            echo esc_attr($field['max']);
                                                                        } ?>">
            <small>Use "auto" to get minimum value from all properties </br>.</small>
        </p>
        <p class="min-container" <?php if ($field['type'] != 'radius') { ?>style="display:none" <?php } ?>>
            <label for="label">Min <span class="dashicons dashicons-editor-help" title="Use only for numeric types like area, price, etc."></span></label>
            <input name="min[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['min'])) {
                                                                            echo esc_attr($field['min']);
                                                                        } ?>">
            <small>Use "auto" to get minimum value from all properties</small>
        </p>
        <?php if (in_array($field['name'], array('_price'))) { ?>

            <?php $state = (isset($field['state'])) ? $field['state'] : false; ?>
            <p class="state-container">
                <label for="state">Check to make this filter enabled by default</label>
                <input name="state[<?php echo $index; ?>]" type="checkbox" <?php checked($state, 'on', true); ?> value="on">
            </p>
        <?php }   ?>


    </div>

</div>