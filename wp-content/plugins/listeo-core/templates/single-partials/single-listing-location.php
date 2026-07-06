<!-- Location -->
<?php 
// if $data is not 

if (!empty($data)) {
	// extract $data as variables
	if(isset($data->show_title))
	{
		$show_title = $data->show_title;
	} else {
		$show_title = true;
	}
} else {
	$show_title = true;
}

$latitude = get_post_meta( $post->ID, '_geolocation_lat', true );
$longitude = get_post_meta( $post->ID, '_geolocation_long', true );
$address = get_post_meta( $post->ID, '_address', true );
$disable_address = get_option('listeo_disable_address');

// Validate coordinates before displaying map
if (!function_exists('listeo_validate_coordinates') || !listeo_validate_coordinates($latitude, $longitude)) {
	// Don't show map section if coordinates are invalid
	return; // Exit template - don't render map section
}

if(!empty($latitude) && $disable_address) {
	/**
	 * Filter the amount of random offset applied to coordinates when address is disabled
	 *
	 * @param float $dither The dither amount in degrees (0.001 = ~100-500m, 0.01 = ~1-5km)
	 */
	$dither = apply_filters('listeo_coordinate_dither_amount', 0.001);

	/**
	 * Filter the random range multiplier for dithering
	 *
	 * @param int $min Minimum random value
	 * @param int $max Maximum random value
	 */
	$min = apply_filters('listeo_coordinate_dither_min', 5);
	$max = apply_filters('listeo_coordinate_dither_max', 15);

	$latitude = (float) $latitude + (rand($min, $max) - 0.5) * $dither;

	// Apply dither to longitude as well for more realistic randomization
	if (!empty($longitude)) {
		$longitude = (float) $longitude + (rand($min, $max) - 0.5) * $dither;
	}
}
if(!empty($latitude)) :

	// New way (using the function):
	$icons = get_listing_marker_icons($post);
	
	$icon = $icons['icon'];
	$icon_svg = $icons['icon_svg'];
	$has_svg = $icons['has_svg'];
	
?>
<!-- Location -->
<div id="listing-location" class="listing-section">
	<?php if($show_title) { ?>
	<h2 class="listing-desc-headline margin-top-60 margin-bottom-30"><?php esc_html_e('Location','listeo_core'); ?></h2>
	<?php } ?>
	<div id="singleListingMap-container" class="<?php if($disable_address) { echo 'circle-point'; } ?> " >
		<div id="singleListingMap" data-latitude="<?php echo esc_attr($latitude); ?>" data-longitude="<?php echo esc_attr($longitude); ?>" data-map-icon="<?php echo esc_attr($icon); ?>" <?php if(isset($icon_svg)) { ?> data-map-icon-svg="<?php echo esc_attr($icon_svg); ?>"<?php } ?>></div>
		<?php if(get_option('listeo_map_provider') == 'google_not_valid_anymore') { ?><a href="#" id="streetView"><?php esc_html_e('Street View','listeo_core'); ?></a> <?php } ?>
		<?php if(!$disable_address) { ?>
		<a target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($latitude.','.$longitude); ?>" id="getDirection"><?php esc_html_e('Get Directions','listeo_core'); ?></a>
		<?php }?>
	</div>
</div>

<?php endif;  ?>

