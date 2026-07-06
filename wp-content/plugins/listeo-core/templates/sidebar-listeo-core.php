<div class="sidebar sticky right">
		<?php 
		$sidebar = false;
		$sidebar = apply_filters( 'listeo_core_listings_sidebar', $sidebar );
		
		if( ! $sidebar ) {
			$sidebar = 'sidebar-listings';			
		}
			
		if( is_active_sidebar( $sidebar ) ) {
			dynamic_sidebar( $sidebar );
		} ?>
		
	</div>
</div>