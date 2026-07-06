jQuery(document).ready(function ($) {


  // if body has class archive, add to each .listing-item-container a data attribute data-campaign-placement="archive"
  if ($("body").hasClass("archive"))
    $(".listing-item-container, .listing-card-container-nl").each(function () {
      $(this).attr("data-campaign-placement", "archive");
    });
    
    // if .listing-item-container is inside elementor widget, add to each .listing-item-container a data attribute data-campaign-placement="elementor"
    if ($(".elementor-widget").length)
    $(".listing-item-container, .listing-card-container-nl").each(function () {
      $(this).attr("data-campaign-placement", "home");
    });

    // if it's single post or page, add to each .listing-item-container a data attribute data-campaign-placement="single"
    if ($("body").hasClass("single"))
    $(".listing-item-container, .listing-card-container-nl").each(function () {
      $(this).attr("data-campaign-placement", "sidebar");
    });

    // run the same code for ajax loaded content
    $(document).ajaxComplete(function () {

        $(".listing-item-container, .listing-card-container-nl").each(function () {
          $(this).attr("data-campaign-placement", "archive");
        });
      if ($(".elementor-widget").length)
        $(".listing-item-container, .listing-card-container-nl").each(function () {
          $(this).attr("data-campaign-placement", "home");
        });
      if ($("body").hasClass("single"))
        $(".listing-item-container, .listing-card-container-nl").each(function () {
          $(this).attr("data-campaign-placement", "sidebar");
        });
    });




  // Track views for PPV ads
  $(
    '.listing-item-container[data-campaign-type="ppv"],.listing-card-container-nl[data-campaign-type="ppv"]'
  ).each(function () {
    var adId = $(this).data("ad-id");
    var campaignPlacement = $(this).data("campaign-placement");
    $.post(adTrackingAjax.ajax_url, {
      action: "track_ad_view",
      ad_id: adId,
      campaign_type: "ppv",
      campaign_placement: campaignPlacement,
      nonce: adTrackingAjax.nonce,
    });
  });

  // Track clicks for all ads
  $(
    '.listing-item-container[data-campaign-type="ppc"],.listing-card-container-nl[data-campaign-type="ppc"]'
  ).on("click", function (e) {
    e.preventDefault();
    var $ad = $(this);
    var adId = $ad.data("ad-id");
    var campaignType = $ad.data("campaign-type");
    var campaignPlacement = $ad.data("campaign-placement");
    // check if $ad is "div" or "a"
    var href = $ad.is("a") ? $ad.attr("href") : $ad.find("a").attr("href");
    // var href = $(this).attr("href");

    $.post(
      adTrackingAjax.ajax_url,
      {
        action: "track_ad_click",
        ad_id: adId,
        campaign_type: campaignType,
        campaign_placement: campaignPlacement,
        nonce: adTrackingAjax.nonce,
      },
      function (response) {
        // Always redirect, even if it's not a unique click
        window.location.href = href;
      }
    );
  });
});