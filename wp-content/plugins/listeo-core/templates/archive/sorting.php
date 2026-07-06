<div class="col-md-6 col-xs-6">
	<!-- Sort by -->
	<div class="sort-by">
		<div class="sort-by-select">
			<?php $default = isset( $_GET['listeo_core_order'] ) ? (string) $_GET['listeo_core_order']  : ''; ?>
			<?php 
			// Check if AI search plugin is active to show "Best Match" instead of "Default Order"
			$is_ai_search_active = class_exists('Listeo_AI_Search') || function_exists('listeo_ai_search_init');
			$default_order_text = $is_ai_search_active ? 
				__('Best Match', 'listeo_core') : 
				__('Default Order', 'listeo_core');
			?>
			<select name="listeo_core_order" data-placeholder="<?php esc_attr_e('Default order', 'listeo_core'); ?>" class="chosen-select-no-single orderby" >
				<option <?php selected($default,'default'); ?> value="default"><?php echo esc_html($default_order_text); ?></option>	
				<option <?php selected($default,'price-asc'); ?> value="price-asc"><?php esc_html_e( 'Price Low to High' , 'listeo_core' ); ?></option>
				<option <?php selected($default,'price-desc'); ?> value="price-desc"><?php esc_html_e( 'Price High to Low' , 'listeo_core' ); ?></option>
				<option <?php selected($default,'date-desc'); ?> value="date-desc"><?php esc_html_e( 'Newest Properties' , 'listeo_core' ); ?></option>
				<option <?php selected($default,'date-asc'); ?> value="date-asc"><?php esc_html_e( 'Oldest Properties' , 'listeo_core' ); ?></option>
				<option <?php selected($default,'featured'); ?> value="featured"><?php esc_html_e( 'Featured' , 'listeo_core' ); ?></option>
				<option <?php selected($default,'rand'); ?> value="rand"><?php esc_html_e( 'Random' , 'listeo_core' ); ?></option>
			</select>
		</div>
	</div>
</div>