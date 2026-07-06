<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

                                    $multi = false;
                                    if (isset($data->field['multi']) && $data->field['multi']) {
                                        $multi = true;
                                    }
$field = $data->field;
$key = $data->key;
$selected = false;
$taxonomy = false;
// Get selected value
if (isset($field['value'])) {
    $selected = $field['value'];
} elseif (isset($field['default']) && is_int($field['default'])) {
    $selected = $field['default'];
} elseif (! empty($field['default']) && ($term = get_term_by('slug', $field['default'], $field['taxonomy']))) {

    $selected = $term->term_id;
}

if (isset($selected) && !is_array($selected)) {
    $selected = (int) $selected;
}
$taxonomy = get_taxonomy($field['taxonomy']);

$menu_label = __('Choose ', 'listeo_core') . $taxonomy->labels->singular_name;

// Get listing type for filtering if dynamic taxonomies are enabled
$listing_type = null;
if ($field['taxonomy'] == 'listing_category' && get_option('listeo_dynamic_taxonomies') == 'on') {
	$listing_type = isset($field['submit_type']) ? $field['submit_type'] : null;
}

$hide_all = isset($field['hide_all']) && $field['hide_all'];
$categories = listeo_get_nested_categories($taxonomy->name, $listing_type, $hide_all);
$categories_json = json_encode($categories);

?>

<!-- First drilldown menu instance with custom categories -->
<!-- First drilldown menu instance with custom categories and additional random data -->
<div <?php if (!$multi) { ?> data-single-select="true" <?php } ?>  data-label="<?php echo esc_attr($menu_label); ?>" data-name="<?php echo esc_attr($field['name']); ?>" class="drilldown-menu" data-taxonomy=<?php echo esc_attr($field['taxonomy']); ?> data-categories='<?php echo esc_attr($categories_json); ?>'>
    <?php if (is_array($selected) && !empty($selected)) {
        foreach ($selected as $key => $value) { ?>
            <input type="hidden" class=" drilldown-values" name="<?php echo esc_attr($field['name']); ?>[]" value="<?php echo $value; ?>">
        <?php }
    } else { ?>
        <input type="hidden" class="drilldown-values" name="<?php echo esc_attr($field['name']); ?>">
    <?php } ?>
    <div class=" menu-toggle">
        <span class="menu-label"><?php echo esc_html($menu_label); ?></span>
        <span class="reset-button" style="display:none;">&times;</span>
    </div>
    <div class="menu-panel">
        <div class="menu-levels">
            <!-- Levels will be injected here -->
        </div>
    </div>
</div>