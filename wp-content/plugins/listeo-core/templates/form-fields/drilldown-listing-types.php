<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$field = $data->field;
$key = $data->key;
$selected = false;

// Get selected value
if (isset($field['value'])) {
    $selected = $field['value'];
} elseif (isset($field['default'])) {
    $selected = $field['default'];
}

if (isset($selected) && !is_array($selected)) {
    $selected = array($selected);
}

$menu_label = isset($field['placeholder']) ? $field['placeholder'] : __('Choose Listing Type & Category', 'listeo_core');

// Get listing types and their taxonomies data
$hide_all = isset($field['hide_all']) && $field['hide_all'];
$listing_types_data = listeo_get_listing_types_with_taxonomies($hide_all);
$categories_json = json_encode($listing_types_data);

?>

<!-- Drilldown menu for listing types and categories -->
<div data-label="<?php echo esc_attr($menu_label); ?>" data-name="<?php echo esc_attr($field['name']); ?>" class="drilldown-menu drilldown-listing-types" data-categories='<?php echo esc_attr($categories_json); ?>'>
    <?php if (is_array($selected) && !empty($selected)) {
        foreach ($selected as $key => $value) { ?>
            <input type="hidden" class=" drilldown-values" name="<?php echo esc_attr($field['name']); ?>[]" value="<?php echo esc_html($value); ?>">
        <?php }
    } else { ?>
        <input type="hidden"  class=" drilldown-values" name="<?php echo esc_attr($field['name']); ?>">
    <?php } ?>
    <div class="menu-toggle">
        <span class="menu-label"><?php echo esc_html($menu_label); ?></span>
        <span class="reset-button" style="display:none;">&times;</span>
    </div>
    <div class="menu-panel">
        <div class="menu-levels">
            <!-- Levels will be injected here by JavaScript -->
        </div>
    </div>
</div>