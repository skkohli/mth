<?php 
	
	/**
	* Headline shortcode
	* Usage: [iconbox title="Service Title" url="#" icon="37"] test [/headline]
	*/
	function listeo_iconbox( $atts, $content ) {
	  extract(shortcode_atts(array(
		    'title' 		=> 'Service Title',
		    'url' 			=> '',
		    'url_title' 	=> '',
		    'url2' 			=> '',
		    'url2_title'	=> '',
		   	'icon'          => 'im im-icon-Office',
		    'type'			=> 'box-1', // 'box-1, box-1 rounded, box-2, box-3, box-4'
		    'with_line' 	=> 'yes',
		    'from_vs' 		=> 'no',
	    ), $atts));

        ob_start();

  		if(!empty($url) && $url != "|||") {

  			if(defined( 'WPB_VC_VERSION' ) && $from_vs == "yes") 
  			{ 
		  		$link = vc_build_link( $url );
		        $a_href = $link['url']; 
		        $a_title = $link['title']; 
		        $a_target = $link['target'];
		        $link_1 = '<a href="'.esc_url( $a_href ).'" title="'.esc_attr( $a_title ).'"'; 
		        if(!empty($a_target)) { 
		        	$link_1 .= 'target="'.esc_attr($a_target).'"'; 
		        } 
		        $link_1 .= '>';
		    } else {
		    	$link_1 = '<a href="'.esc_url( $url ).'">';	
		    }
		    echo $link_1;
		}

		 ?>
		 <div class="icon-box-2 <?php if($with_line=='yes') : ?> with-line <?php endif; ?>">
				<i class="<?php echo esc_attr($icon); ?>"></i>
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php echo do_shortcode( $content ); ?></p>
		</div>

		<?php if(!empty($url) && $url != "|||") { ?>
			</a>
		<?php }?>
		

	    <?php
	    $output =  ob_get_clean() ;
       	return  $output ;
	}

?>