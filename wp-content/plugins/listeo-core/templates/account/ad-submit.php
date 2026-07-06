<?php

/**
 * Coupon Submission Form
 */
if (!defined('ABSPATH')) exit;


$current_user = wp_get_current_user();
$roles = $current_user->roles;
$role = array_shift($roles);
if (!in_array($role, array('administrator', 'admin', 'owner', 'seller'))) :
    $template_loader = new Listeo_Core_Template_Loader;
    $template_loader->get_template_part('account/owner_only');
    return;
endif;

$ad_campaign_product_id = get_option('listeo_ad_campaign_product_id');
if (empty($ad_campaign_product_id) || false === get_post_status($ad_campaign_product_id)) : ?>
    <div class="row">
        <div class="col-md-12">
            <div class="notification notice">
                <p><?php esc_html_e('Ad campaigns are not available at the moment. Please contact the administrator.', 'listeo_core'); ?></p>
            </div>
        </div>
    </div>
    <?php return;
endif;

if (isset($data->add_data)) {
    $ad_id = $data->add_data->ID;
} else {
    $ad_id = '';
}
$currency_abbr = get_option('listeo_currency');
$currency_postion = get_option('listeo_currency_postion');
$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
?>



<div class="row">
    <div class="submit-page submit-ad col-md-12">

        <?php $ads_page = get_option('listeo_ad_campaigns_page'); ?>
        <form action="<?php echo get_permalink($ads_page); ?>" method="post" id="submit-ad-form" class="listing-manager-form" enctype="multipart/form-data">

            <input type="hidden" name="listeo-ad-submission" value="1">


            <div class="add-listing-section row">
                <div class="add-listing-headline">
                    <h3><i class="sl sl-icon-settings"></i> <?php esc_html_e('General Ad Settings', 'listeo_core'); ?></h3>
                </div>

                <div class="col-md-6">
                    <div class="campaign-form-field form-field">

                        <label><?php esc_html_e('For Listing:', 'listeo_core'); ?><i class="tip" data-tip-content="<?php esc_html_e('Leave empty to apply for all your listings', 'listeo_core'); ?>"></i></label>
                        <?php
                        // count user listings
                        // save counter in transient
                        if (false === ($counter = get_transient('listeo_user_listings_count_' . $current_user->ID))) {
                            $counter = count_user_posts($current_user->ID, 'listing');
                            set_transient('listeo_user_listings_count_' . $current_user->ID, $counter, DAY_IN_SECONDS);
                        }
                        // check if there's transient


                        if ($counter > 40) {  ?>
                            <input type="text" id="post-autocomplete" name="post_title" placeholder="<?php esc_html_e('Search for a listing...', 'listeo_core'); ?>">
                            <input type="hidden" id="post-id" name="listing_id">


                        <?php } else {  ?>


                            <select required class="select2-single" style="width: 50%;" name="listing_id" data-placeholder="<?php esc_html_e('Search for a listing', 'listeo_core'); ?>" data-action="" tabindex="-1" aria-hidden="true">
                                <?php

                                $args = array(
                                    'author'            =>  $current_user->ID,
                                    'posts_per_page'      => -1,
                                    'post_type'              => 'listing',
                                    'post_status'          => 'publish'
                                );

                                // exclude listings that already have ads
                                $args['meta_query'] = array(
                                    array(
                                        'key'     => 'ad_id',
                                        'compare' => 'NOT EXISTS'
                                    )
                                );
                                $posts = get_posts($args);

                                foreach ($posts as $post) : setup_postdata($post); ?>
                                    <option value="<?php echo $post->ID; ?>"><?php the_title(); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php } ?>

                    </div>
                </div>
                <?php
                $types_option = get_option('listeo_ad_campaigns_type', array('ppv', 'ppc'));

                // ensure it's always an array
                if (!is_array($types_option)) {
                    $types_option = array($types_option);
                }

                // if array is empty, show ppc
                if (empty($types_option)) {
                    $types_option = array('ppc');
                }


                // if array has two items, show the field, if no, show hidden input with the value
                if (count($types_option) > 1) : ?>
                    <div class="col-md-6">
                        <div class="campaign-form-field form-field ">
                            <label for="ad_campaign_type"><?php esc_html_e('Ad Campaign Type', 'listeo_core'); ?></label>
                            <select name="ad_campaign_type" id="ad_campaign_type" class="select2-single" style="width: 50%;">
                                <option value="ppv"><?php esc_html_e('Pay Per View', 'listeo_core'); ?></option>
                                <option value="ppc"><?php esc_html_e('Pay Per Click', 'listeo_core'); ?></option>
                            </select>
                        </div>
                    </div>
                <?php else : ?>
                    <input type="hidden" id="ad_campaign_type" name="ad_campaign_type" value="<?php echo $types_option[0]; ?>">
                <?php endif; ?>



                <div class="col-md-6">
                    <div class="campaign-form-field form-field expiry_date_field ">
                        <label for="start_date"><?php esc_html_e('Campaign start date', 'listeo_core'); ?><i class="tip" data-tip-content="<?php esc_html_e('Leave empty to start right after payment', 'listeo_core'); ?>"></i></label>

                        <input type="text" class="input-date" style="" name="start_date" autocomplete="off" id="start_date" value="<?php if (isset($data->coupon_edit) && $date_expires) echo date('Y-m-d', $date_expires);  ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
                    </div>
                </div>

                <div class="col-md-6">

                    <div class="campaign-form-field form-field">
                        <label for="budget"><?php esc_html_e('Budget', 'listeo_core'); ?></label>
                        <div style="position: relative;">
                            <i class="data-unit"><?php echo esc_attr($currency_symbol) ?></i>
                            <input type="number" value=200 step="1" name="budget" placeholder="" data-unit="<?php echo esc_attr($currency_symbol) ?>">
                        </div>
                    </div>

                </div>

            </div>
            <div class="add-listing-section row">
                <div class="add-listing-headline">
                    <h3><i class="sl sl-icon-settings"></i> <?php esc_html_e('Campaign Filters', 'listeo_core'); ?></h3>
                </div>
                <div class="col-md-12">
                    <div class="notification closeable notice">
                        <p class="description" id="_gallery-description"><?php esc_html_e('Filters apply only to sidebar and search results placement options', 'listeo_core'); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="campaign-form-field form-field">

                        <label><?php esc_html_e('Display only if category', 'listeo_core'); ?><i class="tip" data-tip-content="<?php esc_html_e('Leave empty to apply for all categories', 'listeo_core'); ?>"></i></label>
                        <?php
                        // Get all listing taxonomies and filter for category taxonomies
                        $taxonomy_objects = get_object_taxonomies('listing', 'objects');
                        $category_taxonomies = array();
                        $all_terms = array();

                        // Filter for category taxonomies (those ending with '_category' or named 'listing_category')
                        foreach ($taxonomy_objects as $taxonomy) {
                            if ($taxonomy->name === 'listing_category' || strpos($taxonomy->name, '_category') !== false) {
                                // Skip 'region' as it's handled separately
                                if ($taxonomy->name !== 'region') {
                                    $category_taxonomies[] = $taxonomy;

                                    // Get terms for this taxonomy
                                    $terms = get_terms($taxonomy->name, array('hide_empty' => true));
                                    if (!empty($terms) && !is_wp_error($terms)) {
                                        foreach ($terms as $term) {
                                            // Prefix term value with taxonomy name for uniqueness
                                            $term->prefixed_value = $taxonomy->name . ':' . $term->term_id;
                                            $term->taxonomy_label = $taxonomy->labels->singular_name;
                                            $all_terms[] = $term;
                                        }
                                    }
                                }
                            }
                        }

                        $count = count($all_terms);
                        ?>
                        <select <?php if ($count > 8) echo 'data-live-search="true"'; ?> required class="select2-single" style="width: 50%;" name="ad_category_filter" data-placeholder="<?php esc_html_e('Choose Category', 'listeo_core'); ?>" data-action="" tabindex="-1" aria-hidden="true">
                            <option value="0"><?php esc_html_e('Choose Category', 'listeo_core'); ?></option>
                            <?php
                            if (!empty($all_terms)) {
                                // Group terms by taxonomy for better organization
                                $terms_by_taxonomy = array();
                                foreach ($all_terms as $term) {
                                    $terms_by_taxonomy[$term->taxonomy][] = $term;
                                }

                                foreach ($terms_by_taxonomy as $taxonomy_name => $terms) {
                                    $taxonomy_obj = get_taxonomy($taxonomy_name);
                                    if ($taxonomy_obj) {
                                        echo '<optgroup label="' . esc_attr($taxonomy_obj->labels->name) . '">';
                                        foreach ($terms as $term) {
                                            echo '<option value="' . esc_attr($term->prefixed_value) . '">' . esc_html($term->name) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                }
                            } ?>
                        </select>

                    </div>
                </div>

                <div class="col-md-6">
                    <div class="campaign-form-field form-field">

                        <label><?php esc_html_e('Display only if region', 'listeo_core'); ?><i class="tip" data-tip-content="<?php esc_html_e('Leave empty to apply for all regions', 'listeo_core'); ?>"></i></label>
                        <?php
                        $terms = get_terms('region', array('hide_empty' => true));
                        $count = count($terms);
                        ?>
                        <select <?php if ($count > 8) echo 'data-live-search="true"'; ?> required class="select2-single" style="width: 50%;" name="taxonomy-region" data-placeholder="<?php esc_html_e('Choose Region', 'listeo_core'); ?>" data-action="" tabindex="-1" aria-hidden="true">
                            <option value="0"><?php esc_html_e('Choose region', 'listeo_core'); ?></option>
                            <?php
                            if (! empty($terms) && ! is_wp_error($terms)) {
                                $options = listeo_core_get_options_array_hierarchical($terms, '');
                                echo $options;
                            } ?>
                        </select>

                    </div>
                </div>
                <div class="col-md-6">
                    <div class="campaign-form-field form-field-_address-container form-field">

                        <label><?php esc_html_e('Display only for location search', 'listeo_core'); ?><i class="tip" data-tip-content="<?php esc_html_e('Leave empty to apply for all regions', 'listeo_core'); ?>"></i></label>
                        <div id="_address_wrapper" style="position: relative;">
                            <input type="text" class="input-text " name="_address" id="_address" placeholder="" value="" maxlength="" autocomplete="off">
                            <div id="leaflet-geocode-cont">
                                <ul></ul>
                            </div>

                            <a href="#"><i title="<?php esc_html_e('Find My Location', 'listeo_core') ?>" class="tooltip left fa fa-map-marker"></i></a>
                            <span class="type-and-click-btn"><?php esc_html_e('type and click here', 'listeo_core') ?></span>
                            <span class="type-and-hit-enter"><?php esc_html_e('type and hit enter', 'listeo_core') ?></span>
                        </div>


                    </div>
                </div>
                <div class="col-md-6">
                    <div class="campaign-form-field form-field">
                        <label class="label-_email_contact_widget" for="_email_contact_widget">
                            <?php esc_html_e('Enable only for logged in users', 'listeo_core'); ?> <i class="tip" data-tip-content="<?php esc_html_e('With this option enabled ad will be shown only to logged in users', 'listeo_core'); ?>">
                                <div class="tip-content"><?php esc_html_e('Ads will be displayed only for logged in users', 'listeo_core'); ?></div>
                            </i>
                        </label>

                        <!-- Rounded switch -->
                        <div class="switch_box box_1">
                            <input type="checkbox" class="input-checkbox switch_1" name="only_loggedin" id="only_loggedin" value="on">
                        </div>

                    </div>


                </div>
            </div>

            <div class="add-listing-section row campaign-placement">
                <div class="add-listing-headline">
                    <h3><i class="sl sl-icon-chart"></i> <?php esc_html_e('Select Ad Placement', 'listeo_core'); ?></h3>
                </div>
                <div class="col-md-16">
                    <?php
                    $price_home_click = get_option('listeo_ad_campaigns_price_home_click');
                    $price_search_click = get_option('listeo_ad_campaigns_price_search_click');
                    $price_sidebar_click = get_option('listeo_ad_campaigns_price_sidebar_click');
                    $price_home_view = get_option('listeo_ad_campaigns_price_home_view');
                    $price_search_view = get_option('listeo_ad_campaigns_price_search_view');
                    $price_sidebar_view = get_option('listeo_ad_campaigns_price_sidebar_view');


                    $placements = get_option('listeo_ad_campaigns_placement', array('home', 'search', 'sidebar'));

                    // if array is empty, show search  placement
                    if (empty($placements)) {
                        $placements = array('search');
                    }
                    ?>
                    <div class="placement-grid">
                        <?php
                        // if array has home item, show it
                        if (in_array('home', $placements)) : ?>

                            <label class="card">
                                <input class="card__input" type="checkbox" name="placement[]" value="home" id="home" />
                                <div class="card__body">
                                    <div class="card__body-cover">
                                        <img class="card__body-cover-image" src="/wp-content/themes/listeo/images/home-ad-icon.svg" /><span class="card__body-cover-checkbox">
                                            <svg class="card__body-cover-checkbox--svg" viewBox="0 0 12 10">
                                                <polyline points="1.5 6 4.5 9 10.5 1"></polyline>
                                            </svg></span>
                                    </div>
                                    <header class="card__body-header">
                                        <h2 class="card__body-header-title"><?php esc_html_e('Homepage', 'listeo_core'); ?> <i class="tip" data-tip-content="<?php esc_html_e('Ads will be displayed on top of home page content ', 'listeo_core'); ?>"></i></h2>
                                        <p class="card__body-header-subtitle">
                                            <span class="ppv-price">
                                                <?php echo listeo_output_price($price_home_view); ?>
                                            </span>
                                            <span class="ppc-price">
                                                <?php echo listeo_output_price($price_home_click); ?>
                                            </span>

                                        </p>
                                    </header>
                                </div>
                            </label>
                        <?php endif; ?>
                        <?php
                        // if array has home item, show it
                        if (in_array('search', $placements)) : ?>
                            <label class="card">
                                <input class="card__input" type="checkbox" name="placement[]" value="search" id="search" />
                                <div class="card__body">
                                    <div class="card__body-cover"><img class="card__body-cover-image" src="/wp-content/themes/listeo/images/search-results-ad-icon.svg" /><span class="card__body-cover-checkbox">
                                            <svg class="card__body-cover-checkbox--svg" viewBox="0 0 12 10">
                                                <polyline points="1.5 6 4.5 9 10.5 1"></polyline>
                                            </svg></span></div>
                                    <header class="card__body-header">
                                        <h2 class="card__body-header-title"><?php esc_html_e('Top Of Search Results', 'listeo_core'); ?> <i class="tip" data-tip-content="<?php esc_html_e('Ads will be displayed before the search results ', 'listeo_core'); ?>"></i></h2>
                                        <p class="card__body-header-subtitle">
                                            <span class="ppv-price">
                                                <?php echo listeo_output_price($price_search_view); ?>
                                            </span>
                                            <span class="ppc-price">
                                                <?php echo listeo_output_price($price_search_click); ?>
                                            </span>
                                        </p>
                                    </header>
                                </div>
                            </label>
                        <?php endif; ?>
                        <?php
                        // if array has home item, show it
                        if (in_array('sidebar', $placements)) : ?>
                            <label class="card">
                                <input class="card__input" type="checkbox" name="placement[]" value="sidebar" id="sidebar" />
                                <div class="card__body">
                                    <div class="card__body-cover"><img class="card__body-cover-image" src="/wp-content/themes/listeo/images/sidebar-ad-icon.svg" /><span class="card__body-cover-checkbox">
                                            <svg class="card__body-cover-checkbox--svg" viewBox="0 0 12 10">
                                                <polyline points="1.5 6 4.5 9 10.5 1"></polyline>
                                            </svg></span></div>
                                    <header class="card__body-header">
                                        <h2 class="card__body-header-title"><?php esc_html_e('Sidebar', 'listeo_core'); ?> <i class="tip" data-tip-content="<?php esc_html_e('Ads will be displayed in sidebar or selected pages or listings ', 'listeo_core'); ?>"></i></h2>
                                        <p class="card__body-header-subtitle">
                                            <span class="ppv-price">
                                                <?php echo listeo_output_price($price_sidebar_view); ?>
                                            </span>
                                            <span class="ppc-price">
                                                <?php echo listeo_output_price($price_sidebar_click); ?>
                                            </span>
                                        </p>
                                    </header>
                                </div>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div id="ad-price-summary" style="display: none;">
                        <h4><?php esc_html_e('With your budget you can get up to:', 'listeo_core'); ?></h4>
                        <ul>

                            <li class="price-box home-campaign">
                                <strong><?php esc_html_e('Home Section', 'listeo_core'); ?></strong>
                                <span class="price"></span>
                                <span class="ad-type"></span>

                            </li>
                            <li class="price-box sidebar-campaign">
                                <strong><?php esc_html_e('Sidebar Section', 'listeo_core'); ?></strong>
                                <span class="price"></span>
                                <span class="ad-type"></span>

                            </li>
                            <li class="price-box search-campaign">
                                <strong><?php esc_html_e('Search Results', 'listeo_core'); ?></strong>
                                <span class="price"></span>
                                <span class="ad-type"></span>
                            </li>
                        </ul>
                    </div>

                    <div id="placement-error" class="notification  error" style="display: none;"><?php esc_html_e('Please select at least one placement', 'listeo_core'); ?></div>

                    <?php
                    // add field for ad campaign type
                    $ad_campaign_type = get_post_meta($ad_id, 'ad_campaign_type', true);

                    ?>
                </div>
            </div>

            <div class="divider margin-top-40"></div>

            <p>

                <input type="hidden" name="listeo_core_form" value="submit_coupon" />

                <button type="submit" value="Submit Add" name="submit_coupon" class="button margin-top-20"><i class="fa fa-arrow-circle-right"></i>
                    <?php esc_html_e('Submit Ad', 'listeo_core'); ?>
                </button>

            </p>

        </form>



    </div>
</div>