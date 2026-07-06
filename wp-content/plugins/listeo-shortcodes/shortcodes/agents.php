<?php 

function listeo_agents( $atts, $content ) {
	   extract(shortcode_atts(array(
	            'from_vs' => '',
	            'role' => '',
				'orderby' => 'display_name',
				'include' => '',
				'exclude' => '',
	    ), $atts));
      	ob_start(); 

      	// prepare arguments
		$args  = array(
			'role' => $role,
			'orderby' => $orderby,
		);
		if(!empty($role)) {
			$args['role'] = $role;
		}
		if(!empty($include)) {
            $include = is_array( $include ) ? $include : array_filter( array_map( 'trim', explode( ',', $include ) ) );
            $args['include'] = $include;
        }	
        if(!empty($exclude)) {
            $exclude = is_array( $exclude ) ? $exclude : array_filter( array_map( 'trim', explode( ',', $exclude ) ) );
            $args['exclude'] = $exclude;
        }
		
		$wp_user_query = new WP_User_Query($args);
		
		$agents = $wp_user_query->get_results();
		
		if (!empty($agents)) {	
			?>
			<div class="row">
			<div class="agents-grid-container">
			<?php foreach ($agents as $agent) {
				$url = get_author_posts_url( $agent->ID );
				$agent_info = get_userdata($agent->ID);	
				$email = $agent_info->user_email; 
				?>
				<!-- Agent -->
				<div class="grid-item col-lg-3 col-md-4 col-sm-6 col-xs-12">
					<div class="agent">

						<div class="agent-avatar">
							<a href="<?php echo esc_url($url); ?>">
								<?php echo get_avatar($agent->ID,590); ?>
								<span class="view-profile-btn"><?php esc_html_e('View Profile','listeo-shortcodes'); ?></span>
							</a>
						</div>

						<div class="agent-content">
							<div class="agent-name">
								<h4><a href="<?php echo esc_url($url); ?>"><?php echo $agent_info->first_name; ?> <?php echo $agent_info->last_name; ?></a></h4>
								<span><?php echo esc_html($agent_info->agent_title); ?></span>
							</div>

							<ul class="agent-contact-details">
								<?php 

								if(isset($agent_info->phone) && !empty($agent_info->phone)): ?><li><i class="sl sl-icon-call-in"></i><?php echo esc_html($agent_info->phone); ?></li><?php endif; ?>
								<?php if(isset($agent_info->user_email)): ?>
								<li><i class="fa fa-envelope-o "></i><a href="mailto:<?php echo esc_attr($email);?>"><?php echo esc_html($email);?></a></li>
							<?php endif; ?>
							</ul>

							<ul class="social-icons">
									<?php
									$socials = array('facebook','twitter','gplus','linkedin');
									foreach ($socials as $social) {
										$social_value = get_user_meta($agent->ID, $social, true);
										if(!empty($social_value)){ ?>
											<li><a class="<?php echo esc_attr($social); ?>" href="<?php echo esc_url($social_value); ?>"><i class="icon-<?php echo esc_attr($social); ?>"></i></a></li>
										<?php }
									}
									?>
								</ul>
							<div class="clearfix"></div>
						</div>

					</div>
				</div>
				<!-- Agent / End -->
			<?php } ?>
			</div>	
			</div>
	    <?php
		}
	    $output =  ob_get_clean() ;
       	return  $output ;
   }

	?>