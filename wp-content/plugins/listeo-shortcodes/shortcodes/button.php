<?php 

	function listeo_button($atts, $content = null) {
        extract(shortcode_atts(array(
            "url" => '',
            "color" => 'color',  //gray color light
            "customcolor" => '',
            "iconcolor" => 'white',
            "icon" => '',
            "size" => '',
            "target" => '',
            "customclass" => '',
            "from_vs" => 'no',
            ), $atts));
       if($from_vs == 'yes') {
	        $link = vc_build_link( $url );
	        $a_href = $link['url'];
	        $a_title = $link['title'];
	        $a_target = $link['target'];
	        $output = '<a class="button '.$color.' '.$size.' '.$customclass.'" href="'.$a_href.'" title="'.esc_attr( $a_title ).'"';
	        if(!empty($a_target)){
	        	$output .= 'target="'.$a_target.'"';
	        }
	        
	        if(!empty($customcolor)) { $output .= 'style="background-color:'.$customcolor.'"'; }
	        $output .= '>';
	        if(!empty($icon)) { $output .= '<i class="'.$icon.'  '.$iconcolor.'"></i> '; }
	        $output .= $a_title.'</a>';
	    } else {
	        $output = '<a class="button '.$color.'  '.$size.' '.$customclass.'" href="'.$url.'" ';
	        if(!empty($target)) { $output .= 'target="'.$target.'"'; }
	        if(!empty($customcolor)) { $output .= 'style="background-color:'.$customcolor.'"'; }
	        $output .= '>';
	        if(!empty($icon)) { $output .= '<i class="fa fa-'.$icon.'  '.$iconcolor.'"></i> '; }
	        $output .= $content.'</a>';
	    }

        return $output;  
    }


    function listeo_signin_button($atts, $content = null) {
			extract(shortcode_atts(array(
            "login_text" => 'Sign In',
            "target_url" => '',
            "target_text" => 'Add Listing',
            
            ), $atts));

            if(is_user_logged_in()){
            	$output = '<a href="#sign-in-dialog" class="sign-in popup-with-zoom-anim">'.$login_text.'</a>';
            } else {
				$output = '<a href="'.$target_url.'" class="">'.$target_text.'</a>';
            }
			return $output;
    }
       add_shortcode( 'listeo_signin_button', 'listeo_signin_button');
       //usage [listeo_signin_button login_text="Sign In" target_url="https://www.tradeamigo.es/add-listing/" target_text="Add Listing"]
?>