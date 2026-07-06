<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$field = $data->field;
$key = $data->key;
$multi = false;
$css_class = 'select2-single';
if(isset($field['multi']) && $field['multi']) {
	$multi = true;
	$css_class = 'select2-multiple';
}

$selected = '';

// Get selected value
if ( isset( $field['value'] ) ) {
	$selected = $field['value'];
} elseif ( isset( $field['default']) && is_int( $field['default'] ) ) {
	$selected = $field['default'];
} elseif ( ! empty( $field['default'] ) && ( $term = get_term_by( 'slug', $field['default'], $field['taxonomy'] ) ) ) {

	$selected = $term->term_id;
} 

// Select only supports 1 value
if ( is_array( $selected ) && $multi == false ) {
	$selected = current( $selected );
	
}
if(!is_array($selected)){
	$selected = (int) $selected;
}
$taxonomy = get_taxonomy($field['taxonomy']);

//if selected is array with just one value, convert it to int
if(is_array($selected) && count($selected) == 1){
	$selected = (int) current($selected);
}

$dropdown_args = array(
	'taxonomy'         => $field['taxonomy'],
	'hierarchical'     => 1,
	'multiple'   	   => $multi,
	'show_option_all'  => false,
	'echo'			   => false,
	'name'             => (isset( $field['name'] ) ? $field['name'] : $key),
	'orderby'          => 'name',
	'selected'         => $selected,
	'class'			   => $css_class,
	'hide_empty'       => false,
	'walker'  		   => new Willy_Walker_CategoryDropdown()
);
if($field['taxonomy'] == 'listing_category' && get_option('listeo_dynamic_taxonomies')=="on" ){
	$submit_type = $field['submit_type'];
	
	$terms_to_include = get_terms(array(
	    'taxonomy' => $field['taxonomy'],
	    'hide_empty' => false,
	    'fields'   => 'ids',
	    'meta_query' => array(
	         array(
	            'key'       => 'listeo_taxonomy_type',
	            'value'     => '"' . $submit_type . '"',
	            'compare'   => 'LIKE'
	         )
	    )
	));

	if(!empty($terms_to_include)){
		$dropdown_args['include'] = $terms_to_include;
	}

} 

if($multi){

} else {
	//$dropdown_args['show_option_none'] = (isset($field['required']) && $field['required'] == true) ? '' : __('Choose ','listeo_core'). $taxonomy->labels->singular_name;
	$dropdown_args['show_option_none'] = __('Choose ','listeo_core'). $taxonomy->labels->singular_name;
	
}
if(isset($field['placeholder']) && !empty($field['placeholder'])){
	$placeholder_data = $field['placeholder'];
} else {
	$placeholder_data = __('Choose ', 'listeo_core') . $taxonomy->labels->singular_name;
}

//$dropdown_args['show_option_none'] = $placeholder_data;

$dropdown_output = wp_dropdown_categories( apply_filters( 'listeo_core_term_select_field_wp_dropdown_categories_args', $dropdown_args , $key, $field ) );
 if ( ! empty( $field['required'] ) ) {
	$dropdown_output = str_replace('<select', '<select required data-taxonomy="'.$field['taxonomy'].'" data-placeholder="'.$placeholder_data.'" ', $dropdown_output);
 }  else {
 	$dropdown_output = str_replace('<select', '<select data-taxonomy="'.$field['taxonomy'].'" data-placeholder="'.$placeholder_data.'" ', $dropdown_output);
 }

echo $dropdown_output;