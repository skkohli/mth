<?php
$faq_status = get_post_meta($post->ID, '_faq_status', true);
if ( $faq_status !== 'on' ) {
    return;
}
$faqs = get_post_meta($post->ID, '_faq_list', true);

if ($faqs) :
    
?>
    <!-- Video -->

    <div id="listing-faq" class="listing-section">
        <h2 class="listing-desc-headline margin-top-60 margin-bottom-30"><?php esc_html_e('FAQ', 'listeo_core'); ?></h2>

        <div class="style-1 fp-accordion">

            <div class="accordion">
                <?php foreach ( (array) $faqs as $key => $faq ) { 
                    if ( !isset( $faq['question']) || empty($faq['question']) )  {
                        continue;
                    }
                    if ( isset( $faq['answer'] ) ) {
                        $desc = wpautop( $faq['answer'] );
                    }
                ?>
                <h3><?php echo esc_html( $faq['question'] ); ?><i class="fa fa-angle-down"></i></h3>
                <div>
                    <?php echo $desc; ?>
                </div>
                <?php } ?>
            </div>
        </div>
      

    </div>
<?php endif; ?>