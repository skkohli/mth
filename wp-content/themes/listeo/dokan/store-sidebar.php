<div id="dokan-secondary" class="col-lg-3 col-md-4 col-sidebar dokan-store-sidebar" role="complementary">
    

        <div class="dokan-widget-area widget-collapse">
            <?php do_action('dokan_sidebar_store_before', $store_user->data, $store_info); ?>
            <?php
            if (!dynamic_sidebar('sidebar-store')) {
                $args = [
                    'before_widget' => '<aside class="widget dokan-store-widget %s">',
                    'after_widget'  => '</aside>',
                    'before_title'  => '<h3 class="widget-title">',
                    'after_title'   => '</h3>',
                ];

                dokan_store_category_widget();

                if (!empty($map_location)) {
                    dokan_store_location_widget();
                }

                dokan_store_time_widget();
                dokan_store_contact_widget();
            }
            ?>

            <?php do_action('dokan_sidebar_store_after', $store_user->data, $store_info); ?>
        </div>


</div><!-- #secondary .widget-area -->