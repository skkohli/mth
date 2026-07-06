<?php 

if(isset($data)) :
	$in_rows	 	= (isset($data->in_rows)) ? $data->in_rows : '' ;
	$ajax_browsing  = (isset($data->ajax_browsing)) ? $data->ajax_browsing : get_option('listeo_ajax_browsing');
endif; ?>
<div class="clearfix"></div>
<!-- <?php if(!empty($in_rows)): ?>
	</div>
<?php endif; ?> -->
</div>
<?php
$infinite_scroll = get_option('listeo_listeo_infinite_scroll', 'off');
if($data->max_num_pages > 1) :
	if( $infinite_scroll == 'on' && isset($ajax_browsing) && $ajax_browsing == 'on' ) : ?>
		<div class="listeo-load-more-container">
			<button class="listeo-load-more-button button loading" data-next-page="2">
				<span class="button-text"><?php esc_html_e('Loading...', 'listeo_core'); ?></span>
				<i class="fa fa-spinner fa-spin loading-icon" style="margin-left: 8px;"></i>
			</button>
		</div>
	<?php else : ?>
		<div class="pagination-container margin-top-20 margin-bottom-20 <?php if( isset($ajax_browsing) && $ajax_browsing == 'on' ) { echo esc_attr('ajax-search'); } ?>">
			<nav class="pagination">
				<?php listeo_core_pagination(  $data->max_num_pages ); ?>
			</nav>
		</div>
	<?php endif;
endif; ?>