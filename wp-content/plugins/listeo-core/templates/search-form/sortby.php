<div class="sort-by">
	<div class="sort-by-select">
		<?php $default = isset( $_GET['listeo_core_order'] ) ? (string) $_GET['listeo_core_order']  : get_option( 'listeo_sort_by','date' ); ?>
		<?php 
		// Check if AI search plugin is active
		$is_ai_search_active = class_exists('Listeo_AI_Search') || function_exists('listeo_ai_search_init');
		$list_of_order = get_option('listeo_listings_sortby_options', array('highest-rated', 'reviewed', 'date-desc', 'date-asc', 'title', 'featured', 'views', 'verified', 'upcoming-event', 'rand', 'best-match'));
		?>
		<select form="listeo_core-search-form" name="listeo_core_order" data-placeholder="<?php esc_attr_e('Default order', 'listeo_core'); ?>" class="select2-single orderby" >
			<option <?php selected($default,'default'); ?> value="default"><?php esc_html_e('Default Order', 'listeo_core'); ?></option>
			<?php
			// Add Best Match option only when AI Search is active
			if ($is_ai_search_active && in_array('best-match', $list_of_order)) : ?>
				<option <?php selected($default, 'best-match'); ?> value="best-match">
					<?php esc_html_e('Best Match', 'listeo_core'); ?>
				</option>
			<?php endif; ?>
			<?php
			// Add Nearest First option (always available)
			if (in_array('distance', $list_of_order)) : ?>
				<option <?php selected($default, 'distance'); ?> value="distance" class="distance-sort">
					<?php esc_html_e('Nearest First', 'listeo_core'); ?>
				</option>
			<?php endif; ?>
			<?php if (in_array('price-asc', $list_of_order)) { ?><option <?php selected($default,'price-asc'); ?> value="price-asc"><?php esc_html_e( 'Price Low to High' , 'listeo_core' ); ?></option><?php } ?>
			<?php if (in_array('price-desc', $list_of_order)) { ?><option <?php selected($default,'price-desc'); ?> value="price-desc"><?php esc_html_e( 'Price High to Low' , 'listeo_core' ); ?></option><?php } ?>
			<option <?php selected($default,'highest-rated'); ?> value="highest-rated"><?php esc_html_e( 'Highest Rated' , 'listeo_core' ); ?></option>
			<option <?php selected($default,'reviewed'); ?> value="reviewed"><?php esc_html_e( 'Most Reviewed' , 'listeo_core' ); ?></option>
			<option <?php selected($default,'date-desc'); ?> value="date-desc"><?php esc_html_e( 'Newest Listings' , 'listeo_core' ); ?></option>
			<option <?php selected($default,'date-asc'); ?> value="date-asc"><?php esc_html_e( 'Oldest Listings' , 'listeo_core' ); ?></option>

			<option <?php selected($default,'featured'); ?> value="featured"><?php esc_html_e( 'Featured' , 'listeo_core' ); ?></option>
			<option <?php selected($default,'views'); ?> value="views"><?php esc_html_e( 'Most Views' , 'listeo_core' ); ?></option>
			<option <?php selected($default,'rand'); ?> value="rand"><?php esc_html_e( 'Random' , 'listeo_core' ); ?></option>
		</select>
	</div>
</div>