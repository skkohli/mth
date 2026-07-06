<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


$field = $data->field;
$key = $data->key;
// Get selected value
if ( isset( $field['value']) && !empty($field['value']) ) {
	$selected = array($field['value']);

} elseif ( isset( $field['default']) && is_int( $field['default'] ) ) {
		$selected = array($field['default']);

} elseif ( ! empty( $field['default'] ) && ( $term = get_term_by( 'slug', $field['default'], $field['taxonomy'] ) ) ) {

	$selected = array($term->term_id);
} else {
	$selected = array(array());
}

$dynamic_features = (get_option('listeo_dynamic_features') == 'on' && $field['taxonomy']== 'listing_feature') ? 'dynamic' : '' ;


if($dynamic_features == 'dynamic'){ ?>
	<div class="<?php echo esc_attr($dynamic_features); ?> checkboxes in-row listeo_core-term-checklist listeo_core-term-checklist-<?php echo $key ?>">
	<div class="notification warning"><p><?php esc_html_e('Please choose category to display available features','listeo_core') ?></p> </div>
</div>
<?php } else { ?>
	<div class="<?php echo esc_attr($dynamic_features); ?> checkboxes in-row listeo_core-term-checklist listeo_core-term-checklist-<?php echo $key ?>">
	<?php
		require_once( ABSPATH . '/wp-admin/includes/template.php' );

		if ( empty( $field['default'] ) ) {
			$field['default'] = '';
		}

		$taxonomy = $field['taxonomy'];
		$terms = get_terms( $taxonomy, array(
		    'hide_empty' => false,
		) );
		foreach ($terms as $key => $category) {
			echo '<input value="' . $category->term_id . '" type="checkbox" name="tax_input['.$taxonomy.'][]" id="in-'.$taxonomy.'-' . $category->term_id . '"' .
	            checked( in_array( $category->term_id, $selected[0] ), true, false ) . ' /> ' .
	            '<label for="in-'.$taxonomy.'-' . $category->term_id . '">'. esc_html( apply_filters( 'the_category', $category->name ) ) . '</label>';
		}
	?>
	</div>
<?php } ?>