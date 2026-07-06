<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="dashboard-list-box dashboard-calendar-view  margin-top-0">
            <div class="headline-with-filters">
                <!-- Booking Requests Filters  -->
                <div class=" booking-requests-filter">

                    <div class="chart-filters calendar-filters">

                        <!-- Sort by -->
                        <div class="sort-by">
                            <div class="sort-by-select">
                                <select data-placeholder="Default order" class="select2-bookings" id="listing_id">
                                    <option value="show_all"><?php echo esc_html__('All Listings', 'listeo_core') ?></option>
                                    <?php foreach ($data->listings as $listing_id) { ?>
                                        <option value="<?php echo $listing_id; ?>"><?php echo get_the_title($listing_id); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>


                        <div class="sort-by-booking-author">
                            <div class="sort-by-select">
                                <select data-placeholder="<?php esc_attr_e('All Users', 'listeo_core') ?>" class="select2-bookings-author" id="booking_author">
                                    <option value="show_all"><?php echo esc_html__('All Users', 'listeo_core') ?></option>
                                    <?php
                                    $users = listeo_get_bookings_author(get_current_user_id());
                                    if (!empty($users)) {
                                        foreach ($users as $key => $value) { ?>
                                            <option value="<?php echo $value[0]; ?>"><?php echo listeo_get_users_name($value[0]); ?></option>
                                    <?php }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>


                        <!-- Sort by -->
                        <div class="sort-by-status chart-sort-by">
                            <div class="sort-by-select">
                                <select data-placeholder="<?php esc_attr_e('Default order', 'listeo_core') ?>" class="select2-bookings-status" id="listing_status">
                                    <option value="show_all"><?php echo esc_html__('All Statuses', 'listeo_core') ?></option>
                                    <option value="confirmed"><?php echo esc_html__('Confirmed', 'listeo_core') ?></option>
                                    <option value="paid"><?php echo esc_html__('Paid', 'listeo_core') ?></option>
                                    <option value="waiting"><?php echo esc_html__('Pending', 'listeo_core') ?></option>
                                    <!-- <option value="expired"><?php echo esc_html__('Expired', 'listeo_core') ?></option> -->

                                </select>
                            </div>
                        </div>


                        <?php if (isset($_GET['status']) && $_GET['status'] != 'approved') { ?>
                            <input type="hidden" id="listing_status" value="<?php echo esc_attr(sanitize_text_field($_GET['status'])); ?>">
                        <?php } else { ?>
                            <input type="hidden" id="listing_status" value="approved">
                        <?php } ?>





                    </div>
                </div>

                <h4><?php esc_html_e('Bookings View', 'listeo_core');
                    ?> <i class="fa fa-circle-o-notch fa-spin booking-loading-icon"></i> </h4>
            </div>
            <div id="small-dialog" class="zoom-anim-dialog mfp-hide booking-calendar-view-popup">
                <div class="small-dialog-header">
                    <h3><?php esc_html_e('Booking details', 'listeo_core') ?></h3>
                </div>
                <div class="small-dialog-booking-content">
                    <ul></ul>
                </div>
            </div>
            <a style="display:none;" class="popup-with-zoom-anim" href="#small-dialog">Open popup</a>
            <div id="calendar-wrapper">
                <div id='calendar'></div>
                <div id='calendar-legend'>
                    <h4><?php esc_html_e('Legend', 'listeo_core') ?></h4>
                  
                    <ul>
                        <li class="confirmed"><?php echo esc_html__('Pending', 'listeo_core') ?></li>
                        <li class="waiting"><?php echo esc_html__('Confirmed / Waiting for payment', 'listeo_core') ?></li>
                        <li class="paid"><?php echo esc_html__('Paid', 'listeo_core') ?></li>
                        <li class="expired"><?php echo esc_html__('Expired', 'listeo_core') ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>