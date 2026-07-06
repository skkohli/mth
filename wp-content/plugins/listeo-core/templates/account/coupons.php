<?php 
$ids = '';
if(isset($data)) :
	$ids	 	= (isset($data->ids)) ? $data->ids : '' ;
endif; 
$message = $data->message;
$no_coupons = array();


?> 
<?php if(!empty($message )) { echo $message; } ?>

<?php if(!empty($ids)) : ?>
<div class="woocommerce dashboard-list-box margin-top-0">


<table class="my_account_orders shop_table shop_table_responsive">
	<thead>
	<tr>
		<th><?php echo esc_html_e('Code','listeo_core'); ?></th>
		<th><?php echo esc_html_e('Coupon Type','listeo_core'); ?></th>
		<th><?php echo esc_html_e('Coupon Amount','listeo_core'); ?></th>
		<th><?php echo esc_html_e('Usage/Limit','listeo_core'); ?></th>
		<th><?php echo esc_html_e('Expiry date','listeo_core'); ?></th>
		<th><?php echo esc_html_e('Actions','listeo_core'); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
	
	$nonce = wp_create_nonce("listeo_core_remove_fav_nonce");
	foreach ($ids as $coupon_id) {
		
		$code = get_the_title( $coupon_id );
		$coupon = new WC_Coupon($coupon_id->ID);
	
		 ?>
		<tr>
			<td data-title="<?php echo esc_html_e('Code','listeo_core'); ?>" class="listeo-coupons-table-coupon-name"><pre><?php echo get_the_title( $coupon_id->ID );?></pre></td>
			<td data-title="<?php echo esc_html_e('Coupon Type','listeo_core'); ?>" ><?php echo esc_html( wc_get_coupon_type( $coupon->get_discount_type() ) ); ?></td>
			<td data-title="<?php echo esc_html_e('Coupon Amount','listeo_core'); ?>" ><?php echo esc_html( wc_format_localized_price( $coupon->get_amount() ) );?></td>
			<td data-title="<?php echo esc_html_e('Usage/Limit','listeo_core'); ?>" ><?php 
				$usage_count =  $coupon->get_usage_count();
				$usage_limit =  $coupon->get_usage_limit();

				printf(
					/* translators: 1: count 2: limit */
					__( '%1$s / %2$s', 'woocommerce' ),
					esc_html( $usage_count ),
					$usage_limit ? esc_html( $usage_limit ) : '&infin;'
				);
				 ?></td>
			<td data-title="<?php echo esc_html_e('Expiry Date','listeo_core'); ?>" >
				<?php $expiry_date = $coupon->get_date_expires();

				if ( $expiry_date ) {
					echo esc_html( $expiry_date->date_i18n( 'F j, Y' ) );
				} else {
					echo '&ndash;';
				} ?>
					
			</td>
			<td  data-title="<?php echo esc_html_e('Actions','listeo_core'); ?>">


				<?php $actions = array();

						$actions['coupon_edit'] = array( 
							'label' => __( 'Edit', 'listeo_core' ), 
							'icon' => 'sl sl-icon-note', 
							'nonce' => false,
							'css'	=> 'pay'
							);
						$actions['delete'] = array( 
							'label' => __( 'Delete', 'listeo_core' ), 
							'icon' => 'sl sl-icon-close', 
							'nonce' => true,
							'css'	=> 'cancel'
							 );

						$actions           = apply_filters( 'listeo_core_coupons_actions', $actions, $coupon_id );

						foreach ( $actions as $action => $value ) {
							if($action == 'edit' ){
								$action_url = add_query_arg( array( 'action' => $action,  'coupon_id' => $coupon_id->ID ), get_permalink( get_option( 'listeo_coupon_page' )) );
							} else {
								$action_url = add_query_arg( array( 'action' => $action,  'coupon_id' => $coupon_id->ID ) );
							}
							if ( $value['nonce'] ) {
								$action_url = wp_nonce_url( $action_url, 'listeo_core_coupons_actions' );
							}
					
							echo '<a href="' . esc_url( $action_url ) . '" class="woocommerce-button button ' . esc_attr( $value['css'] ) . ' listeo_core-dashboard-action-' . esc_attr( $action ) . '">';
							
							if(isset($value['icon']) && !empty($value['icon'])) {
								echo '<i class="'.$value['icon'].'"></i>';
							}

							 echo esc_html( $value['label'] ) . '</a>';
						} ?>	
							
			</td>
		</tr>
			
		
	<?php } ?>
		
</tbody>
	</table>
</div>

<?php else: ?>
	<div class="notification notice ">
		<p><span><?php esc_html_e('No coupons!','listeo_core'); ?></span> <?php esc_html_e('You haven\'t created any coupons yet.','listeo_core'); ?></p>
		
	</div>

<?php endif;
?>
	
		<a href="<?php echo get_permalink( get_option( 'listeo_coupons_page' ) ); ?>/?add_new_coupon=true" class="margin-top-35 button"><?php esc_html_e('Add New Coupon','listeo_core'); ?></a>

