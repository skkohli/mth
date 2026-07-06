<!-- Listings -->
<?php 

$ajax_browsing  = get_option('listeo_ajax_browsing');
$search_data = '';


if(isset($data)) :
	switch ($data->style) {
		case 'list':
			$container_class = $data->style . '-layout';
			break;
		case 'list_old':
		case 'grid_old':
			$container_class = $data->style . '-layout';
			break;

		case 'compact':
		case 'grid_old':
			$container_class = $data->style . '-layout row';
			break;
		default:
			$container_class = 'list-layout';
			break;
	}
	
	$style 			= (isset($data->style)) ? $data->style : 'list-layout' ;
	$custom_class 	= (isset($data->class)) ? $data->class : '' ;
	$in_rows	 	= (isset($data->in_rows)) ? $data->in_rows : '' ;
	$grid_columns	= (isset($data->grid_columns)) ? $data->grid_columns : '' ;
	$per_page		= (isset($data->per_page)) ? $data->per_page : get_option('listeo_listings_per_page',10) ;
	$ajax_browsing  = (isset($data->ajax_browsing)) ? $data->ajax_browsing : get_option('listeo_ajax_browsing');

	if(isset($data->{'tax-region'} )) {
		$search_data .= ' data-region="'.esc_attr($data->{'tax-region'}).'" ';
	} 
	// check if there's region in URL 
	
	if(isset($_GET['tax-region'])) {
		$search_data .= ' data-region="'.esc_attr($_GET['tax-region']).'" ';
	}
	
	if(isset($data->{'_listing_type'} ) && $data->{'_listing_type'} != '') {
		$search_data .= ' data-_listing_type="'.esc_attr($data->{'_listing_type'}).'" ';
	}
	
	if(isset($data->{'tax-listing_category'} )) {
		$search_data .= ' data-category="'.esc_attr($data->{'tax-listing_category'}).'" ';
	}

	if(isset($data->{'tax-listing_feature'} )) {
		$search_data .= ' data-feature="'.esc_attr($data->{'tax-listing_feature'}).'" ';
	}
	
	// Add data attributes for all listing type taxonomies (standard + custom)
	$all_listing_types = listeo_core_get_listing_types(true);

	foreach ($all_listing_types as $type_key => $type_data) {
		$taxonomy_name = $type_key . '_category';
		$data_property = 'tax-' . $taxonomy_name;

		if(isset($data->{$data_property})) {
			$search_data .= ' data-' . $taxonomy_name . '="' . esc_attr($data->{$data_property}) . '" ';
		}
	}
	$search_data = apply_filters('listeo/listings-list-data-tags',$search_data);

endif; 
if($style == "grid"){
	$custom_class .= 'new-grid-layout-nl ';
}
 ?>

<div id="listeo-listings-container" 
data-counter="<?php echo esc_attr($data->counter); ?>" 
data-style="<?php echo esc_attr($style); ?>"  
data-custom_class="<?php echo esc_attr($custom_class); ?>" 
data-per_page="<?php echo esc_attr($per_page); ?>" 
data-grid_columns="<?php echo esc_attr($grid_columns); ?>" 
<?php echo $search_data; ?>
class=" <?php echo $container_class.' '; if($style != 'grid') { ?>row  <?php } echo esc_attr($custom_class); if( isset($ajax_browsing) && $ajax_browsing == 'on' ) { echo esc_attr('ajax-search'); } ?>">
	<div class="loader-ajax-container" > <div class="loader-ajax"></div> </div>