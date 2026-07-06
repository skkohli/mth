<!-- Features -->
<?php

$taxonomies = get_option('listeo_single_taxonomies_checkbox_list', array('listing_feature'));
$icon = false;
if (empty($taxonomies)) {
	return;
}
foreach ($taxonomies as $tax) {
	$term_list = get_the_terms($post->ID, $tax);
	$tax_obj = get_taxonomy($tax);

	// Safety check: ensure taxonomy exists before getting labels
	if (!$tax_obj) {
		// Log missing taxonomy for debugging
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("Listeo: Taxonomy '{$tax}' not found when displaying listing features for post ID: " . $post->ID);
		}
		continue;
	}

	$taxonomy = get_taxonomy_labels($tax_obj);


	if (!empty($term_list)) { ?>
		<h2 class="listing-desc-headline margin-top-70 margin-bottom-30"><?php echo $taxonomy->name; ?></h2>

		<ul class="listing-features checkboxes margin-top-0">
			<?php
			foreach ($term_list as $term) {
				$svg_flag = false;
				echo '';
				$term_link = get_term_link($term);
				if (is_wp_error($term_link))
					continue;
				$t_id = $term->term_id;
				if (isset($t_id)) {
					$_icon_svg = get_term_meta($t_id, '_icon_svg', true);
					$_icon_svg_image = wp_get_attachment_image_src($_icon_svg, 'medium');
				}

				if (isset($_icon_svg_image) && !empty($_icon_svg_image)) {
					$svg_flag = true;
					$icon = listeo_render_svg_icon($_icon_svg);
					//$icon = '<img class="listeo-map-svg-icon" src="'.$_icon_svg_image[0].'"/>';


				} else {

					if (!$icon) {

						$icon = get_term_meta($t_id, 'icon', true);
					}
				}

				if (!empty($icon)) {

					if ($svg_flag == true) {
						echo '<li class="feature-has-icon"><span class="feature-svg-icon">' . $icon . '</span><a href="' . esc_url($term_link) . '">' . $term->name . '</a></li>';
					} else {
						echo '<li class="feature-has-icon faicon"><i class="' . $icon . '"></i> <a href="' . esc_url($term_link) . '">' . $term->name . '</a></li>';
					}
				} else {
					echo '<li class="feature-no-icon"><a href="' . esc_url($term_link) . '">' . $term->name . '</a></li>';
				}
				$icon = false;
			}
			?>
		</ul>

<?php }
};

?>