<div class="edit-form-field" style="display:none">
    <div id="listeo-field-<?php echo $field_key; ?>">

        <p class="type-container">
            <label for="label">Type</label>
            <select class="field-type-selector" name="type[<?php echo esc_attr($index); ?>]">
                <option selected="selected" value="drilldown-listing-types">Drilldown Listing Types</option>
            </select>
        </p>
        
        <p class="multi-container">
            <label for="multi">Enable Multi Select</label>
            <input name="multi[<?php echo $index; ?>]" type="checkbox" <?php checked($multi, 1, true); ?> value="1">
        </p>

        <?php $hide_all = (isset($field['hide_all'])) ? $field['hide_all'] : false; ?>
        <p class="hide-all-container">
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
            <input name="placeholder[<?php echo esc_attr($index); ?>]" type="text" value="<?php echo isset($field['placeholder']) ? esc_attr($field['placeholder']) : 'Choose Listing Type & Category'; ?>">
        </p>

        <p style="display:none;" class="name-container">
            <label for="label">Name</label>
            <input name="name[<?php echo esc_attr($index); ?>]" readonly type="text" value="drilldown-listing-types">
        </p>

        <?php if ($tab != 'search_on_home_page') : ?>
            <input type="hidden" class="place_hidden" name="place[<?php echo esc_attr($index); ?>]" value="main">
        <?php endif; ?>

        <?php if ($tab == 'search_on_half_map') : ?>
            <p class="class-container">
                <label for="label">Field Width <span class="dashicons dashicons-editor-help" title="Field's width using Bootstrap columns"></span> </label>
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

    </div>
</div>