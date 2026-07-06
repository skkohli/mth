<?php
$multi = false;
if(isset($data->multi) && $data->multi) {
	$multi = true;
}


if(isset($_GET[$data->name])) {
	if(is_array($_GET[$data->name])){
		$selected = $_GET[$data->name];
	} else {
		$selected = sanitize_text_field($_GET[$data->name]);	
	}
} else {
	$selected = '';

	if(is_tax($data->taxonomy)){
		$selected = get_query_var($data->taxonomy);
	}

} 
if( empty($selected) && isset($data->default) ) {
	$selected = $data->default;
};

// Fetch terms once, ordered alphabetically, and reuse for both the count
// and the rendered <option> list below. orderby/order are passed explicitly
// so the order is deterministic - relying on the get_terms default lets it be
// overridden (e.g. by term-order plugins via get_terms_args) or flipped by
// whatever request first primes the object cache, which caused the
// intermittent non-alphabetical ordering.
$terms = get_terms($data->taxonomy, array(
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
));
// check for errors
if ( is_wp_error( $terms ) ) {
	$count = 0;
}	else {
	// Re-sort accent-insensitively in PHP. DB collation (or byte ordering)
	// pushes accented initials like "Écija" to the very end of the list
	// instead of grouping them with the plain "E" entries. remove_accents()
	// is always available in WP (É -> E, ó -> o, etc.); term_id is a stable
	// tie-breaker so the order is fully deterministic.
	usort( $terms, function ( $a, $b ) {
		$cmp = strcasecmp( remove_accents( $a->name ), remove_accents( $b->name ) );
		return $cmp !== 0 ? $cmp : ( $a->term_id <=> $b->term_id );
	} );
	$count = count($terms);
}


?>

<div class="<?php if(isset($data->class)) { echo esc_attr($data->class); } ?> <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?> <?php if(isset($data->dynamic) && $data->dynamic=='yes'){ echo esc_attr('dynamic'); }?>" id="listeo-search-form_<?php echo esc_attr($data->name);?>">
	<select <?php if($count > 8) echo 'data-live-search="true"'; ?> id="<?php echo esc_attr($data->name); ?>" 
	<?php if($multi) : ?> 
		multiple name="<?php echo esc_attr($data->name);?>[]"  class="selectpicker"  data-size="10"
	<?php else : ?>
		name="<?php echo esc_attr($data->name);?>"  class="selectpicker"  data-size="10"
	<?php endif; ?>
	title="<?php echo esc_attr($data->placeholder);?>">
	 <?php if(!$multi) : ?>
		<option value="0"><?php echo esc_attr($data->placeholder);?></option>
	<?php endif; ?>
		<?php
		// Reuse $terms fetched above (already ordered by name ASC).
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
			
			$options = listeo_core_get_options_array_hierarchical($terms,$selected);
			echo $options;
		}
		//$options = listeo_core_get_options_array('taxonomy',$data->taxonomy);
		//echo get_listeo_core_options_dropdown($options,$selected) ?>
	</select>
</div>
