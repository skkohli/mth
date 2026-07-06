<?php
header("Content-type: text/css; charset: UTF-8");
$maincolor = get_option('pp_main_color', '#f30c0c');
$maincolor_rgb = implode(",", sscanf($maincolor, "#%02x%02x%02x"));
?>

input[type='checkbox'].switch_1:checked,
.time-slot input:checked ~ label:hover,
div.datedropper:before,
div.datedropper .pick-submit,
div.datedropper .pick-lg-b .pick-sl:before,
div.datedropper .pick-m,
body.no-map-marker-icon .face.front,
body.no-map-marker-icon .face.front:after,
div.datedropper .pick-lg-h {
background-color: <?php echo $maincolor; ?> !important;
}

#booking-date-range-enabler:after,
.nav-links div a:hover, #posts-nav li a:hover,
.hosted-by-title a:hover,

.sort-by-select .select2-container--default .select2-selection--single .select2-selection__arrow b:after,
.claim-badge a i,
.search-input-icon:hover i,
.listing-features.checkboxes a:hover,
div.datedropper .pick-y.pick-jump,
div.datedropper .pick li span,
div.datedropper .pick-lg-b .pick-wke,
div.datedropper .pick-btn,
#listeo-coupon-link,
.total-discounted_costs span,
.widget_meta ul li a:hover, .widget_categories ul li a:hover, .widget_archive ul li a:hover, .widget_recent_comments ul li a:hover, .widget_recent_entries ul li a:hover,
.booking-estimated-discount-cost span {
color: <?php echo $maincolor; ?> !important;
}

.comment-by-listing a:hover,
.browse-all-user-listings a i,
.hosted-by-title h4 a:hover,
.style-2 .trigger.active a,
.style-2 .ui-accordion .ui-accordion-header-active:hover,
.style-2 .ui-accordion .ui-accordion-header-active,
#posts-nav li a:hover,
.plan.featured .listing-badge,
.post-content h3 a:hover,
.add-review-photos i,
.show-more-button i,
.listing-details-sidebar li a,
.star-rating .rating-counter a:hover,
.more-search-options-trigger:after,
.header-widget .sign-in:hover,
#footer a,
#footer .footer-links li a:hover,
#navigation.style-1 .current,
#navigation.style-1 ul li:hover a,
.user-menu.active .user-name:after,
.user-menu:hover .user-name:after,
.user-menu.active .user-name,
.user-menu:hover .user-name,
.main-search-input-item.location a:hover,
.chosen-container .chosen-results li.highlighted,
.input-with-icon.location a i:hover,
.sort-by .chosen-container-single .chosen-single div:after,
.sort-by .chosen-container-single .chosen-default,
.panel-dropdown a:after,
.post-content a.read-more,
.post-meta li a:hover,
.widget-text h5 a:hover,
.about-author a,
button.button.border.white:hover,
a.button.border.white:hover,
.icon-box-2 i,
button.button.border,
a.button.border,
.style-2 .ui-accordion .ui-accordion-header:hover,
.style-2 .trigger a:hover ,
.plan.featured .listing-badges .featured,
.list-4 li:before,
.list-3 li:before,
.list-2 li:before,
.list-1 li:before,
.info-box h4,
.testimonial-carousel .slick-slide.slick-active .testimonial:before,
.sign-in-form .tabs-nav li a:hover,
.sign-in-form .tabs-nav li.active a,
.lost_password:hover a,
#top-bar .social-icons li a:hover i,
.listing-share .social-icons li a:hover i,
.agent .social-icons li a:hover i,
#footer .social-icons li a:hover i,
.headline span i,
vc_tta.vc_tta-style-tabs-style-1 .vc_tta-tab.vc_active a,.vc_tta.vc_tta-style-tabs-style-2 .vc_tta-tab.vc_active a,.tabs-nav li.active a,.wc-tabs li.active a.custom-caption,#backtotop a,.trigger.active a,.post-categories li a,.vc_tta.vc_tta-style-tabs-style-3.vc_general .vc_tta-tab a:hover,.vc_tta.vc_tta-style-tabs-style-3.vc_general .vc_tta-tab.vc_active a,.wc-tabs li a:hover,.tabs-nav li a:hover,.tabs-nav li.active a,.wc-tabs li a:hover,.wc-tabs li.active a,.testimonial-author h4,.widget-button:hover,.widget-text h5 a:hover,a,a.button.border,a.button.border.white:hover,button.button.border,button.button.border.white:hover,.wpb-js-composer .vc_tta.vc_general.vc_tta-style-tabs-style-1 .vc_tta-tab.vc_active>a,.wpb-js-composer .vc_tta.vc_general.vc_tta-style-tabs-style-2 .vc_tta-tab.vc_active>a,
#add_payment_method .cart-collaterals .cart_totals tr th,
.woocommerce-cart .cart-collaterals .cart_totals tr th,
.woocommerce-checkout .cart-collaterals .cart_totals tr th,
#add_payment_method table.cart th,
.woocommerce-cart table.cart th,
.woocommerce-checkout table.cart th,
.woocommerce-checkout table.shop_table th,
.uploadButton .uploadButton-button:before,
.time-slot input ~ label:hover,
.time-slot label:hover span,
#titlebar.listing-titlebar span.listing-tag a,
.booking-loading-icon {
color: <?php echo $maincolor; ?>;
}


.listing-details li i {
background-color: <?php echo $maincolor; ?>26;
color: <?php echo $maincolor; ?>;
}


body .icon-box-2 svg g,
body .icon-box-2 svg circle,
body .icon-box-2 svg rect,
body .icon-box-2 svg path,
body .listeo-svg-icon-box-grid svg g,
body .listeo-svg-icon-box-grid svg circle,
body .listeo-svg-icon-box-grid svg rect,
body .listeo-svg-icon-box-grid svg path,
.listing-type:hover .listing-type-icon svg g,
.listing-type:hover .listing-type-icon svg circle,
.listing-type:hover .listing-type-icon svg rect,
.listing-type:hover .listing-type-icon svg path,
.marker-container .front.face svg g,
.marker-container .front.face svg circle,
.marker-container .front.face svg rect,
.marker-container .front.face svg path { fill: <?php echo $maincolor; ?>; }

.qtyTotal,
.mm-menu em.mm-counter,
.mm-counter,
.category-small-box:hover,
.option-set li a.selected,
.pricing-list-container h4:after,
#backtotop a,
.chosen-container-multi .chosen-choices li.search-choice,
.select-options li:hover,
button.panel-apply,
.layout-switcher a:hover,
.listing-features.checkboxes li:before,
.comment-by a.comment-reply-link:hover,
.add-review-photos:hover,
.office-address h3:after,
.post-img:before,
button.button,
.booking-confirmation-page a.button.color,
input[type=\"button\"],
input[type=\"submit\"],
a.button,
a.button.border:hover,
button.button.border:hover,
table.basic-table th,
.plan.featured .plan-price,
mark.color,
.style-4 .tabs-nav li.active a,
.style-5 .tabs-nav li.active a,
.dashboard-list-box .button.gray:hover,
.change-photo-btn:hover,
.dashboard-list-box a.rate-review:hover,
input:checked + .slider,
.add-pricing-submenu.button:hover,
.add-pricing-list-item.button:hover,
.custom-zoom-in:hover,
.custom-zoom-out:hover,
#geoLocation:hover,
#streetView:hover,
#scrollEnabling:hover,
.code-button:hover,
.category-small-box-alt:hover .category-box-counter-alt,
#scrollEnabling.enabled,
#mapnav-buttons a:hover,
#sign-in-dialog .mfp-close:hover,
.button.listeo-booking-widget-apply_new_coupon:before,
#small-dialog .mfp-close:hover,
.daterangepicker td.end-date.in-range.available,
.radio input[type='radio'] + label .radio-label:after,
.radio input[type='radio']:checked + label .radio-label,
.daterangepicker .ranges li.active, .day-slot-headline, .add-slot-btn button:hover, .daterangepicker td.available:hover, .daterangepicker th.available:hover, .time-slot input:checked ~ label, .daterangepicker td.active, .daterangepicker td.active:hover, .daterangepicker .drp-buttons button.applyBtn,.uploadButton .uploadButton-button:hover {
background-color: <?php echo $maincolor; ?>;
}


.rangeslider__fill,
span.blog-item-tag ,
.testimonial-carousel .slick-slide.slick-active .testimonial-box,
.listing-item-container.list-layout span.tag,
.tip,
.search .panel-dropdown.active a,
#getDirection:hover,
.home-search-slide h3 a:before, .home-search-slide h3 strong:before,
.loader-ajax-container,
.mfp-arrow:hover {
background: <?php echo $maincolor; ?>;
}
.icon-box-v3 .ibv3-icon i, .icon-box-v3 .ibv3-icon svg g, .icon-box-v3 .ibv3-icon svg circle, .icon-box-v3 .ibv3-icon svg rect, .icon-box-v3 .ibv3-icon svg path{
fill: <?php echo $maincolor; ?>;
}

#titlebar.listing-titlebar span.listing-tag { background: <?php echo $maincolor; ?>12; }


.ibv3-icon {
background: <?php echo $maincolor; ?>10;
}

.icon-box-v3:hover .ibv3-icon {
background: <?php echo $maincolor; ?>;
box-shadow: 0 3px 8px <?php echo $maincolor; ?>50;
}
.radio input[type='radio']:checked + label .radio-label,
.rangeslider__handle { border-color: <?php echo $maincolor; ?>; }

.layout-switcher a.active {
color: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}

#titlebar.listing-titlebar span.listing-tag a,
#titlebar.listing-titlebar span.listing-tag {
border-color: <?php echo $maincolor; ?>;

}
.woocommerce .widget_price_filter .ui-slider .ui-slider-handle,
.woocommerce .widget_price_filter .ui-slider .ui-slider-range,

.single-service .qtyInc:hover, .single-service .qtyDec:hover,
.services-counter,
.listing-slider .slick-next:hover,
.listing-slider .slick-prev:hover {
background-color: <?php echo $maincolor; ?>;
}
.single-service .qtyInc:hover, .single-service .qtyDec:hover{
-webkit-text-stroke: 1px <?php echo $maincolor; ?>;
}


.listing-nav-container.cloned .listing-nav li:first-child a.active,
.listing-nav-container.cloned .listing-nav li:first-child a:hover,
.listing-nav li:first-child a,
.listing-nav li a.active,
.listing-nav li a:hover {
border-color: <?php echo $maincolor; ?>;
color: <?php echo $maincolor; ?>;
}

.pricing-list-container h4 {
color: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}

.sidebar-textbox ul.contact-details li a { color: <?php echo $maincolor; ?>; }

button.button.border,
a.button.border {
color: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}

.trigger.active a,
.ui-accordion .ui-accordion-header-active:hover,
.ui-accordion .ui-accordion-header-active {
background-color: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}

.numbered.color ol > li::before {
border-color: <?php echo $maincolor; ?>;;
color: <?php echo $maincolor; ?>;
}

.numbered.color.filled ol > li::before {
border-color: <?php echo $maincolor; ?>;
background-color: <?php echo $maincolor; ?>;
}

.info-box {
border-top: 2px solid <?php echo $maincolor; ?>;
background: linear-gradient(to bottom, rgba(255,255,255,0.98), rgba(255,255,255,0.95));
background-color: <?php echo $maincolor; ?>;
color: <?php echo $maincolor; ?>;
}

.info-box.no-border {
background: linear-gradient(to bottom, rgba(255,255,255,0.96), rgba(255,255,255,0.93));
background-color: <?php echo $maincolor; ?>;
}

.tabs-nav li a:hover { border-color: <?php echo $maincolor; ?>; }
.tabs-nav li a:hover,
.tabs-nav li.active a {
border-color: <?php echo $maincolor; ?>;
color: <?php echo $maincolor; ?>;
}

.style-3 .tabs-nav li a:hover,
.style-3 .tabs-nav li.active a {
border-color: <?php echo $maincolor; ?>;
background-color: <?php echo $maincolor; ?>;
}
.woocommerce-cart .woocommerce table.shop_table th,
.vc_tta.vc_general.vc_tta-style-style-1 .vc_active .vc_tta-panel-heading,
.wpb-js-composer .vc_tta.vc_general.vc_tta-style-tabs-style-2 .vc_tta-tab.vc_active>a,
.wpb-js-composer .vc_tta.vc_general.vc_tta-style-tabs-style-2 .vc_tta-tab:hover>a,
.wpb-js-composer .vc_tta.vc_general.vc_tta-style-tabs-style-1 .vc_tta-tab.vc_active>a,
.wpb-js-composer .vc_tta.vc_general.vc_tta-style-tabs-style-1 .vc_tta-tab:hover>a{
border-bottom-color: <?php echo $maincolor; ?>
}

.checkboxes input[type=checkbox]:checked + label:before {
background-color: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}

.listing-item-container.compact .listing-item-content span.tag { background-color: <?php echo $maincolor; ?>; }

.dashboard-nav ul li.active,
.dashboard-nav ul li:hover { border-color: <?php echo $maincolor; ?>; }

.dashboard-list-box .comment-by-listing a:hover { color: <?php echo $maincolor; ?>; }

.opening-day:hover h5 { color: <?php echo $maincolor; ?> !important; }

.map-box h4 a:hover { color: <?php echo $maincolor; ?>; }
.infoBox-close:hover {
background-color: <?php echo $maincolor; ?>;
-webkit-text-stroke: 1px <?php echo $maincolor; ?>;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice,
body .select2-container--default .select2-results__option--highlighted[aria-selected],
body .select2-container--default .select2-results__option--highlighted[data-selected],
body .woocommerce .cart .button,
body .woocommerce .cart input.button,
body .woocommerce #respond input#submit,
body .woocommerce a.button,
body .woocommerce button.button,
body .woocommerce input.button,
body .woocommerce #respond input#submit.alt:hover,
body .woocommerce a.button.alt:hover,
body .woocommerce button.button.alt:hover,
body .woocommerce input.button.alt:hover,
.marker-cluster-small div, .marker-cluster-medium div, .marker-cluster-large div,
.cluster-visible {
background-color: <?php echo $maincolor; ?> !important;
}

.marker-cluster div:before {
border: 7px solid <?php echo $maincolor; ?>;
opacity: 0.2;
box-shadow: inset 0 0 0 4px <?php echo $maincolor; ?>;
}

.cluster-visible:before {
border: 7px solid <?php echo $maincolor; ?>;
box-shadow: inset 0 0 0 4px <?php echo $maincolor; ?>;
}

.marker-arrow {
border-color: <?php echo $maincolor; ?> transparent transparent;
}

.face.front {
border-color: <?php echo $maincolor; ?>;
color: <?php echo $maincolor; ?>;
}

.face.back {
background: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}

.custom-zoom-in:hover:before,
.custom-zoom-out:hover:before { -webkit-text-stroke: 1px <?php echo $maincolor; ?>; }

.category-box-btn:hover {
background-color: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}

.message-bubble.me .message-text {
color: <?php echo $maincolor; ?>;
background-color: rgba({$maincolor_rgb},0.05);
}


.time-slot input ~ label:hover {
background-color: rgba({$maincolor_rgb},0.08);
}

.message-bubble.me .message-text:before {
color: rgba({$maincolor_rgb},0.05);
}
.booking-widget i, .opening-hours i, .message-vendor i {
color: <?php echo $maincolor; ?>;
}
.opening-hours.summary li:hover,
.opening-hours.summary li.total-costs span { color: <?php echo $maincolor; ?>; }
.payment-tab-trigger > input:checked ~ label::before { border-color: <?php echo $maincolor; ?>; }
.payment-tab-trigger > input:checked ~ label::after { background-color: <?php echo $maincolor; ?>; }
#navigation.style-1 > ul > li.current-menu-ancestor > a,
#navigation.style-1 > ul > li.current-menu-item > a,
#navigation.style-1 > ul > li:hover > a {
background: rgba(<?php echo $maincolor_rgb; ?>, 0.06);
color: <?php echo $maincolor; ?>;
}

.img-box:hover span { background-color: <?php echo $maincolor; ?>; }

body #navigation.style-1 ul ul li:hover a:after,
body #navigation.style-1 ul li:hover ul li:hover a,
body #navigation.style-1 ul li:hover ul li:hover li:hover a,
body #navigation.style-1 ul li:hover ul li:hover li:hover li:hover a,
body #navigation.style-1 ul ul li:hover ul li a:hover { color: <?php echo $maincolor; ?>; }

.headline.headline-box span:before {
background: <?php echo $maincolor; ?>;
}

.main-search-inner .highlighted-category {
background-color:<?php echo $maincolor; ?>;
box-shadow: 0 2px 8px rgba({$maincolor_rgb}, 0.2);
}

.category-box:hover .category-box-content span {
background-color: <?php echo $maincolor; ?>;
}

.user-menu ul li a:hover {
color: <?php echo $maincolor; ?>;
}

.icon-box-2 i {
background-color: <?php echo $maincolor; ?>;
}

@keyframes iconBoxAnim {
0%,100% {
box-shadow: 0 0 0 9px rgba({$maincolor_rgb}, 0.08);
}
50% {
box-shadow: 0 0 0 15px rgba({$maincolor_rgb}, 0.08);
}
}
.listing-type:hover {
box-shadow: 0 3px 12px rgba(0,0,0,0.1);
background-color: <?php echo $maincolor; ?>;
}
.listing-type:hover .listing-type-icon {
color: <?php echo $maincolor; ?>;
}

.listing-type-icon {
background-color: <?php echo $maincolor; ?>;
box-shadow: 0 0 0 8px rgb({$maincolor_rgb}, 0.1);
}

#footer ul.menu li a:hover {
color: <?php echo $maincolor; ?>;
}

#booking-date-range span::after, .time-slot label:hover span, .daterangepicker td.in-range, .time-slot input ~ label:hover, .booking-estimated-cost span, .time-slot label:hover span {
color: <?php echo $maincolor; ?>;
}

.daterangepicker td.in-range {
background-color: rgba({$maincolor_rgb}, 0.05);
color: <?php echo $maincolor; ?>;
}

.leaflet-control-zoom-in:hover, .leaflet-control-zoom-out:hover {
background-color: <?php echo $maincolor; ?>;;
-webkit-text-stroke: 1px <?php echo $maincolor; ?>;
}

.transparent-header #header:not(.cloned) #navigation.style-1 > ul > li.current-menu-ancestor > a,
.transparent-header #header:not(.cloned) #navigation.style-1 > ul > li.current-menu-item > a,
.transparent-header #header:not(.cloned) #navigation.style-1 > ul > li:hover > a {
background: <?php echo $maincolor; ?>;
}

.transparent-header #header:not(.cloned) .header-widget .button:hover,
.transparent-header #header:not(.cloned) .header-widget .button.border:hover {
background: <?php echo $maincolor; ?>;
}
.sign-in-form .button,
.transparent-header.user_not_logged_in #header:not(.cloned) .header-widget .sign-in:hover {
background: <?php echo $maincolor; ?>;
}

.category-small-box-alt i,
.category-small-box i {
color: <?php echo $maincolor; ?>;
}

.account-type input.account-type-radio:checked ~ label {
background-color: <?php echo $maincolor; ?>;
}

.category-small-box:hover {
box-shadow: 0 3px 12px rgba({$maincolor_rgb}, 0.22);
}


.transparent-header.user_not_logged_in #header.cloned .header-widget .sign-in:hover,
.user_not_logged_in .header-widget .sign-in:hover {
background: <?php echo $maincolor; ?>;
}
.nav-links div.nav-next a:hover:before,
.nav-links div.nav-previous a:hover:before,
#posts-nav li.next-post a:hover:before,
#posts-nav li.prev-post a:hover:before { background: <?php echo $maincolor; ?>; }

.slick-current .testimonial-author h4 span {
background: rgba({$maincolor_rgb}, 0.06);
color: <?php echo $maincolor; ?>;
}

body .icon-box-2 i {
background-color: rgba({$maincolor_rgb}, 0.07);
color: <?php echo $maincolor; ?>;
}

.headline.headline-box:after,
.headline.headline-box span:after {
background: <?php echo $maincolor; ?>;
}
.listing-item-content span.tag {
background: <?php echo $maincolor; ?>;
}

.message-vendor div.wpcf7 .ajax-loader,
body .message-vendor input[type='submit'],
body .message-vendor input[type='submit']:focus,
body .message-vendor input[type='submit']:active {
background-color: <?php echo $maincolor; ?>;
}

.message-vendor .wpcf7-form .wpcf7-radio input[type=radio]:checked + span:before {
border-color: <?php echo $maincolor; ?>;
}

.message-vendor .wpcf7-form .wpcf7-radio input[type=radio]:checked + span:after {
background: <?php echo $maincolor; ?>;
}
#show-map-button,
.slider-selection {
background-color:<?php echo $maincolor; ?>;
}

.listeo-cart-container:hover .mini-cart-button{
color: <?php echo $maincolor; ?>;
background: <?php echo $maincolor; ?>1f;
}
.listeo-cart-container .mini-cart-button .badge {
background: <?php echo $maincolor; ?>;
}
.transparent-header #header:not(.cloned) .header-widget .woocommerce-mini-cart__buttons a.button.checkout, .listeo-cart-container .woocommerce-mini-cart__buttons a.button.checkout {background: <?php echo $maincolor; ?>;}

.slider-handle {
border-color:<?php echo $maincolor; ?>;
}
.bookable-services .single-service:hover h5,
.bookable-services .single-service:hover .single-service-price {
color: <?php echo $maincolor; ?>;
}

.bookable-services .single-service:hover .single-service-price {
background-color: rgba({$maincolor_rgb}, 0.08);
color: <?php echo $maincolor; ?>;
}

.classifieds-widget-buttons a.call-btn {
border: 1px solid <?php echo $maincolor; ?>;
color: <?php echo $maincolor; ?>;
}

.bookable-services input[type='checkbox'] + label:hover {
background-color: rgba({$maincolor_rgb}, 0.08);
color: <?php echo $maincolor; ?>;
}
.services-counter,
.bookable-services input[type='checkbox']:checked + label {
background-color: <?php echo $maincolor; ?>;
}
.bookable-services input[type='checkbox']:checked + label .single-service-price {
color: <?php echo $maincolor; ?>;
}


input[type='submit'].dokan-btn-theme:hover, a.dokan-btn-theme:hover, .dokan-btn-theme:hover, input[type='submit'].dokan-btn-theme:focus, a.dokan-btn-theme:focus, .dokan-btn-theme:focus, input[type='submit'].dokan-btn-theme:active, a.dokan-btn-theme:active, .dokan-btn-theme:active, input[type='submit'].dokan-btn-theme.active, a.dokan-btn-theme.active, .dokan-btn-theme.active, .open .dropdown-toggleinput[type='submit'].dokan-btn-theme, .open .dropdown-togglea.dokan-btn-theme, .open .dropdown-toggle.dokan-btn-theme {

background-color: <?php echo $maincolor; ?> !important;
border-color: <?php echo $maincolor; ?> !important;
}
body.dokan-dashboard input[type='submit'].dokan-btn-theme, body.dokan-dashboard a.dokan-btn-theme, body.dokan-dashboard .dokan-btn-theme
{
background-color: <?php echo $maincolor; ?> !important;;
border-color: <?php echo $maincolor; ?> !important;;
}
body input[type='submit'].dokan-btn-theme,
body a.dokan-btn-theme,
body .dokan-btn-theme {
background-color: <?php echo $maincolor; ?>;
border-color: <?php echo $maincolor; ?>;
}
#dokan-store-listing-filter-wrap .right .toggle-view .active {
color: <?php echo $maincolor; ?>;
}
body #dokan-store-listing-filter-wrap .right .toggle-view .active {
border-color: <?php echo $maincolor; ?>;
}
.photo-box:hover .photo-box-content span{
background: <?php echo $maincolor; ?>;
}
#dokan-store-listing-filter-wrap .right .toggle-view .active {
color: <?php echo $maincolor; ?>;
}
.dokan-store-products-ordeby-select .select2-container--default .select2-selection--single .select2-selection__arrow b:after { color: <?php echo $maincolor; ?>;}