<div class="edit-form-field " style="display:none">
    <div id="listeo-field-<?php echo $field_key; ?>">

        <?php $editor_type = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'default'; ?>

        <input class="field-type-selector" type="hidden" name="type[<?php echo esc_attr($index); ?>]" value="<?php echo $field['type']; ?>">


        <p class="placeholder-container">
            <label for="label">Placeholder <span class="dashicons dashicons-editor-help" title="Text that is displayed in the input field before the user enters something"></span></label>
            <input name="placeholder[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['placeholder'])) {
                                                                                    echo esc_attr($field['placeholder']);
                                                                                } ?>">
        </p>

        <?php if ($editor_type == 'listeo-user-fields-registration') {
        ?>
            <?php $required = (isset($field['required'])) ? $field['required'] : false; ?>
            <p class="required-container">
                <label for="required">Required field</label>
                <input name="required[<?php echo $index; ?>]" type="checkbox" <?php checked($required, 1, true); ?> value="1">
            </p>


            <p class="type-container">
                <label for="label">Icon</label>

                <select class="listeo-icon-select" name="icon[<?php echo esc_attr($index); ?>]">
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
                    // if (!get_option('listeo_iconsmind') != 'hide') {
                    //     $imicons = vc_iconpicker_type_iconsmind(array());

                    //     foreach ($imicons as $key => $icon_array) {
                    //         $key = key($icon_array);
                    //         $value = $icon_array[$key];
                    //         echo '<option value="' . $key . '" ';
                    //         if (isset($icon) && $icon == $key) {
                    //             echo ' selected="selected"';
                    //         }
                    //         echo '>' . $value . '</option>';
                    //     }
                    // }
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



        <?php $multi = (isset($field['multi'])) ? $field['multi'] : false; ?>
        <p style="display: none" class="multi-container">
            <label for="multi">Enable Multi Select</label>
            <input name="multi[<?php echo $index; ?>]" type="checkbox" <?php checked($multi, 1, true); ?> value="1">
        </p>





        <p class="css-class-container">
            <label for="label">Custom CSS Class</label>
            <input name="css_class[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['css_class'])) {
                                                                                    echo esc_attr($field['css_class']);
                                                                                } ?>">
        </p>
        
        <?php if (in_array($field['type'], array('text', 'location', 'select', 'hidden','radio'))) {  ?>
            <p class="css-class-container">
                <label for="label">Default value</label>
                <input name="default[<?php echo $index; ?>]" type="text" value="<?php if (isset($field['default'])) {
                                                                                    echo esc_attr($field['default']);
                                                                                } ?>">
            </p>
        <?php } ?>

        <?php if (isset($field['options']) && is_array($field['options'])) {  ?>

            <div class="field-options options-container">
                <label for="options">Options <span class="dashicons dashicons-editor-help" title="Choose preset list or create your own list of options"></span></label>

                <table class="field-options-custom widefat fixed">
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
        <?php } ?>



    </div>

</div>