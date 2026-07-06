<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$field = $data;
$selected = false;

// Get selected value from GET parameters - handle both array and object field data
$field_name = is_array($field) ? $field['name'] : $field->name;
if (isset($_GET[$field_name])) {
    $selected = $_GET[$field_name];
}

$multi = false;
if (isset($data->multi) && $data->multi) {
    $multi = true;
}

if (isset($selected) && !is_array($selected)) {
    $selected = array($selected);
}

$menu_label = is_array($field)
    ? (isset($field['placeholder']) ? $field['placeholder'] : __('Choose Listing Type & Category', 'listeo_core'))
    : (isset($field->placeholder) ? $field->placeholder : __('Choose Listing Type & Category', 'listeo_core'));

// Get listing types and their taxonomies data
$hide_all = isset($data->hide_all) && $data->hide_all;
$listing_types_data = listeo_get_listing_types_with_taxonomies($hide_all);
$categories_json = json_encode($listing_types_data);

?>

<!-- Drilldown menu for listing types and categories in search form -->
<div data-label="<?php echo esc_attr($menu_label); ?>" <?php if (!$multi) { ?> data-single-select="true" <?php } ?> data-name="<?php echo esc_attr($field_name); ?>" id="listeo-drilldown-listing-types" class="drilldown-menu drilldown-listing-types search-field" data-categories='<?php echo esc_attr($categories_json); ?>'>
    <?php if (is_array($selected) && !empty($selected)) {
        foreach ($selected as $key => $value) { ?>
            <input type="hidden" class="drilldown-values" name="<?php echo esc_attr($field_name); ?>[]" value="<?php echo esc_html($value); ?>">
        <?php }
    } else { ?>
        <input type="hidden" class="drilldown-values" name="<?php echo esc_attr($field_name); ?>">
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