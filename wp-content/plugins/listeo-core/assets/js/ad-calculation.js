/* ----------------- Start Document ----------------- */
(function ($) {
  ("use strict");

  // code that return the ad calculation from ajax request
  // to be used in the ad calculation page

  $(document).ready(function () {

     $("#post-autocomplete").autocomplete({
       source: function (request, response) {
         $.ajax({
           url: ad_calculation.ajax_url,
           dataType: "json",
           data: {
             action: "post_title_autocomplete",
             term: request.term,
           },
           success: function (data) {
             response(data);
           },
         });
       },
       minLength: 2,
       select: function (event, ui) {
         $("#post-id").val(ui.item.id);
       },
     });

    $("#submit-ad-form").on("change", "input, select, textarea", function () {
      var budget = $("input[name='budget']").val();
      $(".ad-price-calc").addClass("loading");
      // get value of checkboxes with name placement
      var placement = $("input[name='placement[]']:checked")
        .map(function () {
          return this.value;
        })
        .get();
      var campaign_type = $("#ad_campaign_type").val();

      var ad = {
        budget: budget,
        placement: placement,
        campaign_type: campaign_type,
      };
      $.ajax({
        url: ad_calculation.ajax_url,
        data: {
          action: "listeo_get_ad_price",
          ad: ad,
        },
        type: "POST",
        success: function (response) {
          // change response.data json string to object
          $("#ad-price-summary").show();
          if (response.data.home) {
            $(".price-box.home-campaign").show();
            // set the value of the div with class .ad-price-home to the response.data
            $(".price-box.home-campaign .price").html(response.data.home);
            $(".ad-type").html(response.data.type);
          } else {
            $(".price-box.home-campaign").hide();
          }
          if (response.data.sidebar) {
            $(".price-box.sidebar-campaign").show();
            // set the value of the div with class .ad-price-home to the response.data
            $(".price-box.sidebar-campaign  .price").html(response.data.sidebar);
            $(".ad-type").html(response.data.type);
          } else {
            $(".price-box.sidebar-campaign").hide();
          }
          if (response.data.search) {
            $(".price-box.search-campaign").show();
            // set the value of the div with class .ad-price-home to the response.data
            $(".price-box.search-campaign  .price").html(response.data.search);
            $(".ad-type").html(response.data.type);
          } else {
            $(".price-box.search-campaign").hide();
          }
          $(".ad-price-calc").removeClass("loading");
          // if response data is has only one value hide the other divs
          if (!response.data.home && !response.data.sidebar && !response.data.search) {
            $("#ad-price-summary").hide();
            
          }
        },
        error: function (error) {
          console.log(error);
          $(".ad-price-calc").removeClass("loading");
        },
      });
    });

    // if value of select ad_campaign_type is changed show/hide the div with class .ad-price-click or .ad-price-views

    $("#ad_campaign_type").on("change", function () {
      var ad_campaign_type = $(this).val();

      if (ad_campaign_type == "ppc") {
        $(".ppc-price").show();
        $(".ppv-price").hide();
      } else {
        $(".ppc-price").hide();
        $(".ppv-price").show();
      }
    });

    var ad_campaign_type = $("#ad_campaign_type").val();
    if (ad_campaign_type == "ppc") {
       $(".ppc-price").show();
       $(".ppv-price").hide();
    } else {
      $(".ppc-price").hide();
      $(".ppv-price").show();
    }

    // add verificaiton and validation to the ad form, to check if the user has selected a placement and listing id
    $("#submit-ad-form").on("submit", function (e) {
      e.preventDefault();
      
      var placement = $("input[name='placement[]']:checked").length;
     // scroll to the top of the page
        $("html, body").animate({ scrollTop: 0 }, "slow");
      if (placement == 0) {
        $("#placement-error").show();
        return;
      } else {
        $("#placement-error").hide();
      }
      

      $(this).unbind("submit").submit();
    


    });
    });
})(this.jQuery);
