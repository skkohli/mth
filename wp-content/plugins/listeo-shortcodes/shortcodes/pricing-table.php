<?php
function listeo_pricing_table($atts, $content) {
    extract(shortcode_atts(array(
        "type"          => 'color-1',
        "width"         => 'four',
        "color"         => '',
        "title"         => '',
        "subtitle"      => '',
        "price"         => '',
        "discounted"    => '',
        "per"           => '',
        "buttonstyle"   => '',
        "buttonlink"    => '',
        "buttontext"    => 'Sign Up',
        "place"         =>'',
        "from_vs"       => 'no'
        ), $atts));

    switch ( $place ) {
        case "last" :
        $p = "omega";
        break;

        case "center" :
        $p = "alpha omega";
        break;

        case "none" :
        $p = " ";
        break;

        case "first" :
        $p = "alpha";
        break;
        default :
        $p = ' ';
    }
    $output = '';
    if($from_vs == 'yes') {
        $output .= '
        <div class="'.$type.' plan">';
        } else {
            $output .= '
        <div class="'.$type.' plan '.$width.' '.$p.' columns">';
        }
    if($type == 'featured') {
        $output .= '<div class="listing-badge">
                        <span class="featured">'.esc_html__('Featured','listeo-shortcodes').'</span>
                    </div>';
    }
    $output .= '
        <div class="plan-price" style="background-color: '.$color.';">
            <h3>'.$title.'</h3>';
            if(!empty($discounted)){ 
                    $output .= '<span class="value"> <del><span class="woocommerce-Price-amount amount"><bdi>'.$price.'</bdi></span></del> <ins><span class="woocommerce-Price-amount amount"><bdi>'.$discounted.'</bdi></span></ins></span>';     

            } else {

                $output .= '<div class="value">'.$price.'</div>';
            }
            if(!empty($per)){
                $output .= '<span class="period">'.$per.'</span>';
            }
        $output .= '</div>
        <div class="plan-features">'.do_shortcode( $content );
        if($buttonlink) {
            $output .=' <a class="button"  style="background-color: '.$color.';" href="'.$buttonlink.'"><i class="fa fa-shopping-cart"></i> '.$buttontext.'</a>';
        }
    $output .=' </div>
    </div>';
    return $output;
}

?>