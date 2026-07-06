	/* ----------------- Start Document ----------------- */
(function($){
"use strict";

$(document).ready(function(){
  $(".listeo_core-dashboard-action-delete").click(function (e) {
    e.preventDefault();
    if (window.confirm(listeo_core.areyousure)) {
      location.href = this.href;
    }
  });

  // $('.booking-widget').after("<div class='booking-widget-hook'></div>");

  //  $(window).on('load resize', function() {
  //        var winWidth = $(window).width();
  //        if (winWidth < 992) {
  //           $('.booking-widget').insertAfter("#titlebar");
  //        } else if (winWidth > 992) {
  //           $('.booking-widget').insertAfter(".booking-widget-hook");
  //        }
  //  });

  $("body").on("click", ".listeo_core-bookmark-it", function (e) {
    e.preventDefault();
    if ($(this).is(".clicked,.liked")) {
      return;
    }
    $(this).addClass("clicked");

    var post_id = $(this).data("post_id"),
      handler = $(this),
      nonce = $(this).data("nonce"),
      addedtolist = $(this).data("saved-title");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "listeo_core_bookmark_this",
        post_id: post_id,
        nonce: nonce,
      },
      success: function (response) {
        console.log(response);
        if (response.type == "success") {
          handler
            .removeClass("listeo_core-bookmark-it")
            .addClass("liked")
            .addClass("listeo_core-unbookmark-it")
            .removeClass("clicked");
          if (handler.hasClass("fa-regular")) {
            handler.removeClass("fa-regular");
            handler.addClass("fa-solid");
          }
          var confirmed = handler.data("confirm");
          handler
            .children(".like-icon")
            .addClass("liked")
            .removeClass("clicked")
            .parent()
            .html('<span class="like-icon liked"></span> ' + confirmed);
        } else {
          handler.removeClass("clicked");
          handler.children(".like-icon").removeClass("liked");
        }
      },
    });
  });

  $(".listeo_core-unbookmark-it").on("click", function (e) {
    e.preventDefault();
    var handler = $(this);
    var post_id = $(this).data("post_id");
    var nonce = $(this).data("nonce");
    handler.closest("li").addClass("opacity-05");
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "listeo_core_unbookmark_this",
        post_id: post_id,
        nonce: nonce,
      },
      success: function (response) {
        console.log(handler);
        console.log(response);
        if (response.type == "success") {
          handler.closest("li").fadeOut();
          if (handler.hasClass("fa-solid")) {
            handler.removeClass("fa-solid");
            handler.addClass("fa-regular");
          }
          handler.removeClass("clicked");
          handler.removeClass("liked");
          handler.children(".like-icon").removeClass("liked");
        } else {
          handler.closest("li").removeClass("opacity-05");
        }
      },
    });
  });

  // Choose listing type step
  $(".add-listing-section.type-selection a").on("click", function (e) {
    e.preventDefault();
    var type = $(this).data("type");
    $("#listing_type").val(type);
    $("#submit-listing-form").submit();
  });

  $(".add-listing-section.type-selection a").on("click", function (e) {
    e.preventDefault();
    var type = $(this).data("type");
    $("#listing_type").val(type);
    $("#submit-listing-form").submit();
  });

  var elements = document.querySelectorAll("input,select,textarea");

  for (var i = elements.length; i--; ) {
    elements[i].addEventListener("invalid", function () {
      this.scrollIntoView(false);
    });
  }

  // $(".add-listing-section.availability_calendar").on(
  //   "click",
  //   "span.calendar-day-date",
  //   function (e) {
  //     e.preventDefault();
  //     var td = $(this).parent();
  //     var timestamp = td.data("timestamp");
  //     var date = td.data("date");
  //     var $el = $(".listeo-calendar-avail");

  //     if (td.hasClass("not_active")) {
  //       td.removeClass("not_active");
  //       var current_dates = $el.val();
  //       console.log(current_dates);
  //       console.log(date + "|");
  //       current_dates = current_dates.replace(date + "|", "");
  //       console.log(current_dates);
  //       $el.val(current_dates);
  //     } else {
  //       td.addClass("not_active");
  //       $el.val($el.val() + date + "|");
  //     }
  //   }
  // );

  // $(".add-listing-section.availability_calendar").on(
  //   "click",
  //   "button",
  //   function (e) {
  //     e.preventDefault();
  //     var td = $(this).parent().parent();
  //     var timestamp = td.data("timestamp");
  //     var date = td.data("date");
  //     var $el = $(".listeo-calendar-avail");
  //     var current_price = $(this).prev("span").text();

  //     var price = (function ask() {
  //       var n = prompt(listeo_core.prompt_price);
  //       console.log(typeof n);
  //       if (n === null) {
  //         return n;
  //       } else if (n === "") {
  //         return current_price;
  //       } else {
  //         return isNaN(n) ? ask() : n;
  //       }
  //     })();
  //     var json = {};
  //     var reg_price;
  //     if (td.hasClass("weekend")) {
  //       reg_price = $("#_weekday_price").val();
  //     } else {
  //       reg_price = $("#_normal_price").val();
  //     }
  //     if (price != null && price != reg_price) {
  //       $(this).parent().find("span").html(price);
  //       // json.push({
  //       //   date : price
  //       // });
  //       var current_value = $(".listeo-calendar-price").val();
  //       if (current_value) {
  //         var json = jQuery.parseJSON($(".listeo-calendar-price").val());
  //       }
  //       json[date] = price;
  //       var stringit = JSON.stringify(json);
  //       $(".listeo-calendar-price").val(stringit);
  //     }
  //     if (price == reg_price) {
  //       $(this).parent().find("span").html(price);
  //       var current_value = $(".listeo-calendar-price").val();
  //       if (current_value) {
  //         var json = jQuery.parseJSON($(".listeo-calendar-price").val());
  //       }
  //       delete json[date];
  //       var stringit = JSON.stringify(json);
  //       $(".listeo-calendar-price").val(stringit);
  //     }
  //   }
  // );

  $("#_normal_price").on("input", function (e) {
    e.preventDefault();
    var price = $(this).val();
    $(".listeo-calendar-day:not(.weekend) .calendar-price span").html(price);
    submit_calendar_update_price();
  });

  $("#_weekend_price,#_weekday_price").on("input", function (e) {
    e.preventDefault();
    var price = $(this).val();
    $(".listeo-calendar-day.weekend .calendar-price span").html(price);
    submit_calendar_update_price();
  });

  $(".add-listing-section.availability_calendar").on(
    "click",
    ".prev",
    function (event) {
      var month = $(this).data("prev-month");
      var year = $(this).data("prev-year");
      getCalendar(month, year);
    }
  );
  $(".add-listing-section.availability_calendar").on(
    "click",
    ".next",
    function (event) {
      var month = $(this).data("next-month");
      var year = $(this).data("next-year");
      getCalendar(month, year);
    }
  );
  $(".add-listing-section.availability_calendar").on(
    "blur",
    "#currentYear",
    function (event) {
      var month = $("#currentMonth").text();
      var year = $("#currentYear").text();
      getCalendar(month, year);
    }
  );

  function getCalendar(month, year) {
    $.ajax({
      type: "post",
      dataType: "json",
      url: listeo_core.ajax_url,
      data: { action: "listeo_core_calendar", month: month, year: year },
      success: function (data) {
        $("#listeo-calendar-outer").html(data.response);
        var _normal_price = $("#_normal_price").val();
        $(".listeo-calendar-day:not(.weekend) .calendar-price span").html(
          _normal_price
        );
        var _weekend_price = $("#_weekday_price").val();
        $(".listeo-calendar-day.weekend .calendar-price span").html(
          _weekend_price
        );
        submit_calendar_update_price();
        submit_calendar_update_unav_days();
      },
    });
  }

  function submit_calendar_update_unav_days() {
    var days = $(".listeo-calendar-avail").val();
    if (days) {
      var array = days.split("|");

      $.each(array, function (key, day) {
        if (day) {
          $("td.listeo-calendar-day[data-date='" + day + "']").addClass(
            "not_active"
          );
        }
      });
    }
  }

  function submit_calendar_update_price() {
    var prices = $(".listeo-calendar-price").val();
    if (prices) {
      var obj = JSON.parse(prices);

      $.each(obj, function (day, price) {
        if (day) {
          $(
            "td.listeo-calendar-day[data-date='" +
              day +
              "'] .calendar-price span"
          ).text(price);
        }
      });
    }
  }
  var _normal_price = $("#_normal_price").val();
  $(".listeo-calendar-day:not(.weekend) .calendar-price span").html(
    _normal_price
  );
  var _weekend_price = $("#_weekday_price").val();
  $(".listeo-calendar-day.weekend .calendar-price span").html(_weekend_price);
  submit_calendar_update_price();
  submit_calendar_update_unav_days();

  // // Store the start and end dates
  // var startDate = null;
  // var endDate = null;

  // // Attach a click event listener to all table cells
  // $("table td")
  //   .click(function () {
  //     var cellIndex = $(this).index();
  //     var rowIndex = $(this).parent().index();

  //     // If start date is not set, set it
  //     if (startDate === null) {
  //       startDate = {
  //         row: rowIndex,
  //         cell: cellIndex,
  //       };
  //       $(this).addClass("selected");
  //     }
  //     // If end date is not set, set it
  //     else if (endDate === null) {
  //       endDate = {
  //         row: rowIndex,
  //         cell: cellIndex,
  //       };
  //       $(this).addClass("selected");

  //       // Swap start and end date if necessary
  //       if (
  //         startDate.row > endDate.row ||
  //         (startDate.row === endDate.row && startDate.cell > endDate.cell)
  //       ) {
  //         var tempDate = startDate;
  //         startDate = endDate;
  //         endDate = tempDate;
  //       }

  //       // Prompt user for value
  //       var value = prompt("Enter a value:");

  //       // Set value for selected range of dates
  //       $("table tr").each(function (row) {
  //         $(this)
  //           .find("td")
  //           .each(function (cell) {
  //             if (
  //               (row > startDate.row ||
  //                 (row === startDate.row && cell >= startDate.cell)) &&
  //               (row < endDate.row ||
  //                 (row === endDate.row && cell <= endDate.cell))
  //             ) {
  //               $(this).find("input").val(value);
  //             }
  //           });
  //       });
  //     }
  //     // If both start and end date are already set, reset the selection
  //     else {
  //       startDate = {
  //         row: rowIndex,
  //         cell: cellIndex,
  //       };
  //       endDate = null;
  //       $("table td").removeClass("selected");
  //       $(this).addClass("selected");
  //     }
  //   })
  //   .hover(
  //     function () {
  //       // If start date is already set, add class to all tds between start date and current hover
  //       if (startDate !== null && endDate === null) {
  //         var cellIndex = $(this).index();
  //         var rowIndex = $(this).parent().index();
  //         $("table tr").each(function (row) {
  //           $(this)
  //             .find("td")
  //             .each(function (cell) {
  //               if (
  //                 (row > startDate.row ||
  //                   (row === startDate.row && cell >= startDate.cell)) &&
  //                 (row < rowIndex || (row === rowIndex && cell <= cellIndex))
  //               ) {
  //                 $(this).addClass("selected");
  //               }
  //             });
  //         });
  //       }
  //     },
  //     function () {
  //       // If start date is already set, remove class from all tds between start date and current hover
  //       if (startDate !== null && endDate === null) {
  //         $("table tr").each(function (row) {
  //           $(this)
  //             .find("td")
  //             .each(function (cell) {
  //               if (
  //                 (row > startDate.row ||
  //                   (row === startDate.row && cell >= startDate.cell)) &&
  //                 (row < rowIndex || (row === rowIndex && cell <= cellIndex))
  //               ) {
  //                 $(this).removeClass("selected");
  //               }
  //             });
  //         });
  //       }
  //     }
  //   );

  // Serialize the slots editor UI into the hidden `#_slots` input as JSON
  // before any form containing it submits. Originally bound to the
  // frontend listing form (`#submit-listing-form`) only — that left the
  // Booking Plus resource manager (`#submit-resource-form` on
  // `/manage-resources/`) silently saving empty slots. Now bound as a
  // delegated handler on every `<form>` that contains an
  // `.availability-slots` editor so it also covers the resource form
  // and any future flow that reuses Core's slots template.
  $(document).on("submit", "form", function () {
    var $form = $(this);
    if (!$form.find(".availability-slots").length) return;

    var slot_container = 0;
    var slots = [];
    $form.find(".slots-container").each(function () {
      var inside_slots = [];
      var slot_number = 0;
      $(this)
        .find(".single-slot-time")
        .each(function () {
          var $row = $(this).closest(".single-slot");
          if ($row.hasClass("cloned")) return; // skip the hidden template row
          var timeText = $(this).text().replace(/-|-/g, "-");
          inside_slots[slot_number] =
            timeText + "|" + $row.find('input[name="slot-qty"]').val();
          slot_number++;
        });
      slots[slot_container] = inside_slots;
      slot_container++;
    });
    $form.find("#_slots, input[type='hidden'][name='_slots']")
      .val(JSON.stringify(slots));
  });

  /* var json = jQuery.parseJSON($(".listeo-calendar-price").val());  
    
    json[date] = price;
    var stringit = JSON.stringify(json);*/

  $("#listeo-activities-list a.close-list-item").on("click", function (e) {
    var $this = $(this),
      id = $(this).data("id"),
      nonce = $(this).data("nonce");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "remove_activity",
        id: id,
        nonce: nonce,
      },
      success: function (data) {
        if (data.success == true) {
          $this
            .parent()
            .addClass("wait")
            .fadeOut("normal", function () {
              $this.remove();
            });
        } else {
        }
      },
    });
    e.preventDefault();
  });

  $("#listeo-clear-activities").on("click", function (e) {
    var $this = $(this),
      nonce = $(this).data("nonce");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "remove_all_activities",
        nonce: nonce,
      },
      success: function (data) {
        if (data.success == true) {
          $("ul#listeo-activities-list li:not(.cleared)").remove();
          $("li.cleared").show();
          $this.parent().parent().find(".pagination-container").remove();
        } else {
        }
      },
    });
    e.preventDefault();
  });

  $("select#sort-reviews-by").on("change", function (e) {
    var button = $(this);
    button.parents(".dashboard-list-box").addClass("loading");
    var page = button.find("#reviews_list_visitors").data("page");
    var post_id = $(this).val();
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "reload_reviews",
        id: post_id,
        page: page,
        //'nonce': nonce
      },
      success: function (data) {
        button.parents(".dashboard-list-box").removeClass("loading");
        if (data.success == true) {
          $("#reviews_list_visitors").html(data.comments);
          $("#visitor-reviews-pagination").html(data.pagination);
          $(".popup-with-zoom-anim").magnificPopup({
            type: "inline",

            fixedContentPos: false,
            fixedBgPos: true,

            overflowY: "auto",

            closeBtnInside: true,
            preloader: false,

            midClick: true,
            removalDelay: 300,
            mainClass: "my-mfp-zoom-in",
          });
        } else {
          console.log("error");
        }
      },
    });
    e.preventDefault();
  });

  $("#visitor-reviews-pagination").on("click", "a", function (e) {
    var page = $(this).parent().data("paged");
    var post_id = $("#sort-reviews-by").val();
    $(".reviews-visitior-box").addClass("loading");
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "reload_reviews",
        id: post_id,
        page: page,
      },
      success: function (data) {
        $(".reviews-visitior-box").removeClass("loading");
        if (data.success == true) {
          $("#reviews_list_visitors").html(data.comments);
          $("#visitor-reviews-pagination").html(data.pagination);
          $(".popup-with-zoom-anim").magnificPopup({
            type: "inline",

            fixedContentPos: false,
            fixedBgPos: true,

            overflowY: "auto",

            closeBtnInside: true,
            preloader: false,

            midClick: true,
            removalDelay: 300,
            mainClass: "my-mfp-zoom-in",
          });
        } else {
          console.log("error");
        }
      },
    });
    e.preventDefault();
  });

  $(".reviews-visitior-box").on("click", ".reply-to-review-link", function (e) {
    $("#comment_reply").val();
    var post_id = $(this).data("postid");
    var review_id = $(this).data("replyid");

    $("#send-comment-reply input#reply-post-id").val(post_id);
    $("#send-comment-reply input#reply-review-id").val(review_id);
  });

  $(".reviews-visitior-box").on("click", ".edit-reply", function (e) {
    //var review_id = $(this).parents('.review-li').data('review');
    $("#send-comment-edit-reply textarea#comment_reply").val("");
    var comment_id = $(this).data("comment-id");
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "get_comment_review_details",
        comment: comment_id,
        //'nonce': nonce
      },
      success: function (data) {
        var comment_content = data.comment_content;
        $("#send-comment-edit-reply textarea#comment_reply").val(
          comment_content
        );
      },
    });

    $("#send-comment-edit-reply input#reply_id").val(comment_id);
  });

  $("#send-comment-edit-reply").on("submit", function (e) {
    $("#send-comment-edit-reply button").addClass("loading");
    var content = $(this).find("textarea#comment_reply").val();
    var reply_id = $(this).find("input#reply_id").val();
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "edit_reply_to_review",
        //'post_id':  $(this).find('input#reply-post-id').val(),
        reply_id: $(this).find("input#reply_id").val(),
        content: content,
        //'nonce': nonce
      },
      success: function (data) {
        if (data.success == true) {
          $("#send-comment-edit-reply button").removeClass("loading");
          $('.edit-reply[data-comment-id="' + reply_id + '"]').data(
            "comment-content",
            content
          );
          // $('#review-'+review_id+' .edit-reply')
          $(".mfp-close").trigger("click");
        } else {
          $("#send-comment-edit-reply button").removeClass("loading");
        }
      },
    });
    e.preventDefault();
  });

  $("#send-comment-reply").on("submit", function (e) {
    $("#send-comment-reply button").addClass("loading");
    var review_id = $(this).find("input#reply-review-id").val();

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "reply_to_review",
        post_id: $(this).find("input#reply-post-id").val(),
        review_id: review_id,
        content: $(this).find("textarea#comment_reply").val(),
        //'nonce': nonce
      },
      success: function (data) {
        console.log(data);
        if (data.success == true) {
          $("#send-comment-reply button").removeClass("loading");
          $(".mfp-close").trigger("click");
          $("#review-" + review_id + " .reply-to-review-link")
            .html('<i class="sl sl-icon-check"></i> ' + listeo_core.replied)
            .off("click");
          //location.reload();
        } else {
          $("#send-comment-reply button").removeClass("loading");
        }
      },
    });
    e.preventDefault();
  });

  var critera = listeo_core.review_criteria.split(",");

  $(".your-reviews-box").on("click", ".edit-review", function (e) {
    //var review_id = $(this).parents('.review-li').data('review');
    $("#send-comment-edit-review input[type=radio]").prop("checked", false);
    $("#send-comment-edit-review textarea#comment_reply").val("");
    $(".message-reply").addClass("loading");
    var comment_id = $(this).data("comment-id");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "get_comment_review_details",
        comment: comment_id,
        //'nonce': nonce
      },
      success: function (data) {
        $("#send-comment-edit-review input#reply_id").val(comment_id);
        $("#send-comment-edit-review input#rating-" + data.comment_rating).prop(
          "checked",
          true
        );
        $(".sub-ratings-container").html(data.ratings);
        // $.each( critera, function( index, value ){
        //     var rate_val = data[value];

        //     console.log(rate_val);

        //     $("#send-comment-edit-review input#rating-"+value+"-"+rate_val).prop( "checked", true );
        // });
        $("#send-comment-edit-review textarea#comment_reply").val(
          data.comment_content
        );
        $(".message-reply").removeClass("loading");
      },
    });

    // var button = $(this);
    // var comment_content = $(this).data('comment-content');
    // var comment_rating = $(this).data('comment-rating');

    // $('#send-comment-edit-review input#reply_id').val(comment_id);

    // $('#send-comment-edit-review input#rating-'+comment_rating).prop( "checked", true );
    // $.each( critera, function( index, value ){
    //     var rate_val = button.data('comment-'+value);
    //     $('#send-comment-edit-review input#rating-'+value+'-'+rate_val).prop( "checked", true );
    // });
    // $('#send-comment-edit-review textarea#comment_reply').val(comment_content);
  });

  $(".listeo_core-dashboard-delete-review").click(function (e) {
    e.preventDefault();
    if (window.confirm(listeo_core.areyousure)) {
      location.href = this.href;
    }
  });

  function get_url_extension(url) {
    return url.split(/\#|\?/)[0].split(".").pop().trim();
  }
$("body").on("submit", ".ical-import-form", function (e) {
  e.preventDefault();

  $(this).find("button").addClass("loading");
  $("input.import_ical_url").removeClass("bounce");

  var form = $(this);
  var listing_id = $(this).data("listing-id");
  var name = $(this).find("input.import_ical_name").val();
  var url = $(this).find("input.import_ical_url").val();
  var force_update = $(this)
    .find("input.import_ical_force_update")
    .prop("checked");
  var filetype = get_url_extension(url); //validate for .ical, .ics, .ifb, .icalendar

  var valid_filetypes = ["ical", "ics", "ifb", "icalendar", "calendar"];

  // Make URL checking case-insensitive for better compatibility
  var urlLower = url.toLowerCase();

  if (
    urlLower.indexOf("calendar") !== -1 ||
    urlLower.indexOf("accommodation_id") !== -1 ||
    urlLower.indexOf("ical") !== -1 ||
    urlLower.indexOf("airtable.com") !== -1 || // Explicit Airtable support
    $.inArray(filetype, valid_filetypes) > -1
  ) {
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "add_new_listing_ical",
        name: name,
        url: url,
        listing_id: listing_id,
        force_update: force_update,
        //'nonce': nonce
      },
      success: function (data) {
        if (data.type == "success") {
          form.find("button").removeClass("loading");
          form.find("input.import_ical_name").val("");
          form.find("input.import_ical_url").val("");
          form
            .parents(".ical-import-dialog")
            .find(".saved-icals")
            .html(data.output);
          $(".ical-import-dialog .notification")
            .removeClass("error notice")
            .addClass("success")
            .show()
            .html(data.notification);
        }

        if (data.type == "error") {
          form.find("button").removeClass("loading");

          $(".ical-import-dialog .notification")
            .removeClass("success notice")
            .addClass("error")
            .show()
            .html(data.notification);
        }
      },
    });
  } else {
    $(this).find("button").removeClass("loading");
    $("input.import_ical_url").addClass("bounce");
    window.setTimeout(function () {
      $("input.import_ical_url").removeClass("bounce");
    }, 1000);
  }
});

  $("body").on("click", "a.ical-remove", function (e) {
    e.preventDefault();
    var $this = $(this),
      index = $(this).data("remove"),
      nonce = $(this).data("nonce");
    var listing_id = $(this).data("listing-id");
    $this.parents(".saved-icals").addClass("loading");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "add_remove_listing_ical",
        index: index,
        listing_id: listing_id,
        //'nonce': nonce
      },
      success: function (data) {
        if (data.type == "success") {
          $this
            .parents(".saved-icals")
            .removeClass("loading")
            .html(data.output);
        }
        $(".ical-import-dialog .notification").show().html(data.notification);
      },
    });
  });

  $("body").on("click", "a.update-all-icals", function (e) {
    e.preventDefault();
    var $this = $(this),
      listing_id = $(this).data("listing-id");
    $this.addClass("loading");
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "refresh_listing_import_ical",
        listing_id: listing_id,
        //'nonce': nonce
      },
      success: function (data) {
        $this.removeClass("loading");
        if (data.type == "success") {
          $(".ical-import-dialog .notification")
            .removeClass("error notice")
            .addClass("success")
            .show()
            .html(data.notification);
        } else if (data.type == "error") {
          $(".ical-import-dialog .notification")
            .removeClass("success notice")
            .addClass("error")
            .show()
            .html(data.notification);
        }
      },
    });
  });

  $("#send-comment-edit-review").on("submit", function (e) {
    $("#send-comment-edit-review button").addClass("loading");
    var button = $(this);
    var content = $(this).find("textarea#comment_reply").val();
    var reply_id = $(this).find("input#reply_id").val();
    var reply_rating = $(this).find('input[type="radio"]:checked').val();

    var data = {
      action: "edit_review",
      //'post_id':  $(this).find('input#reply-post-id').val(),
      reply_id: $(this).find("input#reply_id").val(),
      content: content,
      //'nonce': nonce
    };
    $.each(critera, function (index, value) {
      data["rating_" + value] = button
        .find('input[type="radio"][name="' + value + '"]:checked')
        .val();
    });
    console.log(data);

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: data,
      success: function (data) {
        if (data.success == true) {
          $("#send-comment-edit-review button").removeClass("loading");

          // $('#review-'+review_id+' .edit-reply')
          $(".mfp-close").trigger("click");
        } else {
          $("#send-comment-edit-review button").removeClass("loading");
        }
      },
    });
    e.preventDefault();
  });

  $("a.listeo_core-rate-review").on("click", function (e) {
    e.preventDefault();
    var $this = $(this),
      comment = $(this).data("comment"),
      nonce = $(this).data("nonce");
    if ($this.data("processing")) {
      return false;
    }

    $this.data("processing", true);

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "listeo_core_rate_review",
        comment: comment,
        //'nonce': nonce
      },
      success: function (data) {
        $this.html(data.output);
      },

      complete: function () {
        // Reset processing flag after request completes
        $this.data("processing", false);
      },
    });
    e.preventDefault();
  });

  // Contact Form Ajax

  $("#send-message-from-widget").on("submit", function (e) {
    $("#send-message-from-widget button")
      .addClass("loading")
      .prop("disabled", true);

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "listeo_send_message",
        recipient: $(this).find("textarea#contact-message").data("recipient"),
        referral: $(this).find("textarea#contact-message").data("referral"),
        message: $(this).find("textarea#contact-message").val(),
        //'nonce': nonce
      },
      success: function (data) {
        if (data.type == "success") {
          $("#send-message-from-widget button").removeClass("loading");
          $("#send-message-from-widget .notification")
            .show()
            .html(data.message);
          window.setTimeout(closepopup, 3000);
          $(this).find("textarea#contact-message").val("");
        } else {
          $("#send-message-from-widget .notification")
            .removeClass("success")
            .addClass("error")
            .show()
            .html(data.message);
          $("#send-message-from-widget button")
            .removeClass("loading")
            .prop("disabled", false);
        }
      },
    });
    e.preventDefault();
  });

  function closepopup() {
    var magnificPopup = $.magnificPopup.instance;
    if (magnificPopup) {
      magnificPopup.close();
      $("#send-message-from-widget button")
        .removeClass("loading")
        .prop("disabled", false);
    }
  }

  // File attachment handling
  $(document).on("change", "#message-attachment", function (e) {
    var file = this.files[0];
    if (file) {
      var fileName = file.name;
      var fileSize = file.size;
      var maxSize = 10 * 1024 * 1024; // 10MB

      // Check file size
      if (fileSize > maxSize) {
        alert("File size exceeds 10MB limit");
        this.value = "";
        return;
      }

      // Show preview
      $(".attachment-preview").show();
      $(".attachment-preview-name").text(fileName);
      $(".attachment-label").hide();

      // Upload file via AJAX
      var formData = new FormData();
      formData.append("attachment", file);
      formData.append("conversation_id", $("#conversation_id").val());
      formData.append("nonce", $("#messages_nonce").val());
      formData.append("action", "listeo_upload_message_attachment");

      // Show progress
      $(".attachment-upload-progress").show();
      $(".attachment-preview").hide();

      $.ajax({
        url: listeo.ajaxurl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        xhr: function () {
          var xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener(
            "progress",
            function (evt) {
              if (evt.lengthComputable) {
                var percentComplete = (evt.loaded / evt.total) * 100;
                $(".progress-fill").css("width", percentComplete + "%");
              }
            },
            false
          );
          return xhr;
        },
        success: function (response) {
          if (response.success) {
            // Store attachment info
            $("#attachment_id").val(response.data.attachment_id);
            $(".attachment-upload-progress").hide();
            $(".attachment-preview").show();
          } else {
            alert(response.data);
            $(".attachment-upload-progress").hide();
            $(".attachment-label").show();
            $("#message-attachment").val("");
          }
        },
        error: function () {
          alert("Upload failed. Please try again.");
          $(".attachment-upload-progress").hide();
          $(".attachment-label").show();
          $("#message-attachment").val("");
        },
      });
    }
  });

  // Remove attachment
  $(document).on("click", ".remove-attachment", function (e) {
    e.preventDefault();
    $("#message-attachment").val("");
    $("#attachment_id").val("");
    $(".attachment-preview").hide();
    $(".attachment-label").show();
  });

  $("#send-message-from-chat").on("submit", function (e) {
    var message = $(this).find("textarea#contact-message").val();
    var attachment_id = $(this).find("input#attachment_id").val();

    if (message || attachment_id) {
      $(this).find("textarea#contact-message").removeClass("error");
      $(".loading").show();
      $(this).find("button").prop("disabled", true);
      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: {
          action: "listeo_send_message_chat",
          recipient: $(this).find("input#recipient").val(),
          conversation_id: $(this).find("input#conversation_id").val(),
          message: message,
          attachment_id: attachment_id,
          //'nonce': nonce
        },
        success: function (data) {
          if (data.type == "success") {
            $(this).addClass("success");
            refreshMessages();
            $("#send-message-from-chat textarea").val("");
            $("#send-message-from-chat button").prop("disabled", false);
            // Reset attachment
            $("#attachment_id").val("");
            $("#message-attachment").val("");
            $(".attachment-preview").hide();
            $(".attachment-label").show();
          } else {
            $(this).addClass("error");
          }
        },
      });
    } else {
      $(this).find("textarea#contact-message").addClass("error");
    }
    e.preventDefault();
  });

  $(document).on("click", ".booking-message", function (e) {
    var recipient = $(this).data("recipient");
    var referral = $(this).data("booking_id");
    $("#send-message-from-widget textarea").val("");
    $("#send-message-from-widget .notification").hide();

    $("#send-message-from-widget textarea")
      .data("referral", referral)
      .data("recipient", recipient);

    $(".send-message-to-owner").trigger("click");
  });

  function refreshMessages() {
    if ($(".message-bubbles").length) {
      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: {
          action: "listeo_get_conversation",
          conversation_id: $(
            "#send-message-from-chat input#conversation_id"
          ).val(),
          //'nonce': nonce
        },
        success: function (data) {
          if (data.type == "success") {
            $(".message-bubbles").html(data.message);
          }
          $(".loading").hide();
        },
        complete: function () {
          setTimeout(refreshMessages, 4000);
        },
      });
    }
  }
  setTimeout(refreshMessages, 4000);

  if ($("#coupon_bg-uploader").length > 0) {
    /* Upload using dropzone */
    Dropzone.autoDiscover = false;

    var couponDropzone = new Dropzone("#coupon_bg-uploader", {
      url: listeo_core.upload,
      maxFiles: 1,
      maxFilesize: listeo_core.maxFilesize,
      dictDefaultMessage: listeo_core.dictDefaultMessage,
      dictFallbackMessage: listeo_core.dictFallbackMessage,
      dictFallbackText: listeo_core.dictFallbackText,
      dictFileTooBig: listeo_core.dictFileTooBig,
      dictInvalidFileType: listeo_core.dictInvalidFileType,
      dictResponseError: listeo_core.dictResponseError,
      dictCancelUpload: listeo_core.dictCancelUpload,
      dictCancelUploadConfirmation: listeo_core.dictCancelUploadConfirmation,
      dictRemoveFile: listeo_core.dictRemoveFile,
      dictMaxFilesExceeded: listeo_core.dictMaxFilesExceeded,
      acceptedFiles: "image/*",
      accept: function (file, done) {
        done();
      },
      init: function () {
        this.on("addedfile", function () {
          if (this.files[1] != null) {
            this.removeFile(this.files[0]);
          }
        });
        this.on("sending", function(file, xhr, formData) {
          formData.append("nonce", listeo_core.upload_nonce);
        });
      },

      success: function (file, response) {
        file.previewElement.classList.add("dz-success");
        //remove any blank characters and empty lines from response
        response = response.replace(/(\r\n|\n|\r)/gm, "");
        file["attachment_id"] = response; // push the id for future reference

        $("#coupon_bg-uploader-id").val(file["attachment_id"]);
      },
      error: function (file, response) {
        file.previewElement.classList.add("dz-error");
      },
      // update the following section is for removing image from library
      addRemoveLinks: true,
      removedfile: function (file) {
        var attachment_id = file["attachment_id"];
        if (attachment_id == undefined) {
          attachment_id = $("#coupon_bg-uploader-id").val();
        }
        $("#coupon_bg-uploader-id").val("");
        $.ajax({
          type: "POST",
          url: listeo_core.delete,
          data: {
            media_id: attachment_id,
            nonce: listeo_core.upload_nonce
          },
          success: function (result) {
            console.log(result);
          },
          error: function () {
            console.log("delete error");
          },
        });
        var _ref;
        return (_ref = file.previewElement) != null
          ? _ref.parentNode.removeChild(file.previewElement)
          : void 0;
      },
    });

    couponDropzone.on("maxfilesexceeded", function (file) {
      this.removeFile(file);
    });
    if ($(".edit-coupon-photo").attr("data-photo")) {
      var mockFile = {
        name: $(".edit-coupon-photo").attr("data-name"),
        size: $(".edit-coupon-photo").attr("data-size"),
      };
      couponDropzone.emit("addedfile", mockFile);
      couponDropzone.emit(
        "thumbnail",
        mockFile,
        $(".edit-coupon-photo").attr("data-photo")
      );
      couponDropzone.emit("complete", mockFile);
      couponDropzone.files.push(mockFile);
      // If you use the maxFiles option, make sure you adjust it to the
      // correct amount:

      couponDropzone.options.maxFiles = 1;
    }
  }

  if ($("#avatar-uploader").length > 0) {
    /* Upload using dropzone */
    Dropzone.autoDiscover = false;

    var avatarDropzone = new Dropzone("#avatar-uploader", {
      url: listeo_core.upload,
      maxFiles: 1,
      maxFilesize: listeo_core.maxFilesize,
      dictDefaultMessage: listeo_core.dictDefaultMessage,
      dictFallbackMessage: listeo_core.dictFallbackMessage,
      dictFallbackText: listeo_core.dictFallbackText,
      dictFileTooBig: listeo_core.dictFileTooBig,
      dictInvalidFileType: listeo_core.dictInvalidFileType,
      dictResponseError: listeo_core.dictResponseError,
      dictCancelUpload: listeo_core.dictCancelUpload,
      dictCancelUploadConfirmation: listeo_core.dictCancelUploadConfirmation,
      dictRemoveFile: listeo_core.dictRemoveFile,
      dictMaxFilesExceeded: listeo_core.dictMaxFilesExceeded,
      acceptedFiles: "image/jpeg,image/png,image/gif,image/jpg",
      accept: function (file, done) {
        done();
      },
      init: function () {
        this.on("addedfile", function () {
          if (this.files[1] != null) {
            this.removeFile(this.files[0]);
          }
        });
        this.on("sending", function(file, xhr, formData) {
          formData.append("nonce", listeo_core.upload_nonce);
        });
      },

      success: function (file, response) {
        file.previewElement.classList.add("dz-success");
        file["attachment_id"] = response; // push the id for future reference
        $("#avatar-uploader-id").val(file["attachment_id"]);
      },
      error: function (file, response) {
        file.previewElement.classList.add("dz-error");
        $(file.previewElement).find(".dz-error-message").text(response);
      },
      // update the following section is for removing image from library
      addRemoveLinks: true,
      removedfile: function (file) {
        var attachment_id = file["attachment_id"];
        if (attachment_id == undefined) {
          attachment_id = $("#avatar-uploader-id").val();
        }
        $("#avatar-uploader-id").val("");
        $.ajax({
          type: "POST",
          url: listeo_core.delete,
          data: {
            media_id: attachment_id,
            nonce: listeo_core.upload_nonce
          },
          success: function (result) {
            console.log(result);
          },
          error: function () {
            console.log("delete error");
          },
        });
        var _ref;
        return (_ref = file.previewElement) != null
          ? _ref.parentNode.removeChild(file.previewElement)
          : void 0;
      },
    });

    avatarDropzone.on("maxfilesexceeded", function (file) {
      this.removeFile(file);
    });
    if ($(".edit-profile-photo").attr("data-photo")) {
      var mockFile = {
        name: $(".edit-profile-photo").attr("data-name"),
        size: $(".edit-profile-photo").attr("data-size"),
      };
      avatarDropzone.emit("addedfile", mockFile);
      avatarDropzone.emit(
        "thumbnail",
        mockFile,
        $(".edit-profile-photo").attr("data-photo")
      );
      avatarDropzone.emit("complete", mockFile);
      avatarDropzone.files.push(mockFile);
      // If you use the maxFiles option, make sure you adjust it to the
      // correct amount:

      avatarDropzone.options.maxFiles = 1;
    }
  }

  // Gallery uploader (#media-uploader) - for listing galleries
  if ($("#media-uploader").length > 0) {
    // Check if Dropzone is already attached (from auto-discovery)
    var mediaUploaderElement = document.querySelector("#media-uploader");
    var galleryDropzone;

    if (mediaUploaderElement.dropzone) {
      // Already attached by auto-discovery, reconfigure it
      galleryDropzone = mediaUploaderElement.dropzone;

      // Add nonce to uploads
      galleryDropzone.on("sending", function(file, xhr, formData) {
        formData.append("nonce", listeo_core.upload_nonce);
      });

      // Completely replace the removedfile callback to include nonce
      galleryDropzone.options.removedfile = function(file) {
        var attachment_id = file["attachment_id"];
        if (attachment_id) {
          $.ajax({
            type: "POST",
            url: listeo_core.delete,
            data: {
              media_id: attachment_id,
              nonce: listeo_core.upload_nonce
            },
            success: function (result) {
              console.log("File deleted:", result);
            },
            error: function (xhr, status, error) {
              console.log("delete error:", xhr.responseText);
            },
          });
          $('input[value="' + attachment_id + '"]').remove();
        }
        // Remove the preview element
        if (file.previewElement && file.previewElement.parentNode) {
          file.previewElement.parentNode.removeChild(file.previewElement);
        }
        // Trigger the Dropzone internal removal logic
        galleryDropzone._updateMaxFilesReachedClass();
      };

      // Also intercept clicks on remove links for existing files
      $("#media-uploader").off("click", "[data-dz-remove]").on("click", "[data-dz-remove]", function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var fileElement = $(this).closest(".dz-preview, .dz-file-preview");
        var file = null;

        // Find the file object associated with this preview
        for (var i = 0; i < galleryDropzone.files.length; i++) {
          if (galleryDropzone.files[i].previewElement === fileElement[0]) {
            file = galleryDropzone.files[i];
            break;
          }
        }

        if (file) {
          galleryDropzone.removeFile(file);
        }

        return false;
      });
    } else {
      // Not attached yet, create new instance
      Dropzone.autoDiscover = false;
      galleryDropzone = new Dropzone("#media-uploader", {
      url: listeo_core.upload,
      maxFiles: listeo_core.maxFiles || 10,
      maxFilesize: listeo_core.maxFilesize,
      dictDefaultMessage: listeo_core.dictDefaultMessage,
      dictFallbackMessage: listeo_core.dictFallbackMessage,
      dictFallbackText: listeo_core.dictFallbackText,
      dictFileTooBig: listeo_core.dictFileTooBig,
      dictInvalidFileType: listeo_core.dictInvalidFileType,
      dictResponseError: listeo_core.dictResponseError,
      dictCancelUpload: listeo_core.dictCancelUpload,
      dictCancelUploadConfirmation: listeo_core.dictCancelUploadConfirmation,
      dictRemoveFile: listeo_core.dictRemoveFile,
      dictMaxFilesExceeded: listeo_core.dictMaxFilesExceeded,
      acceptedFiles: "image/*",
      addRemoveLinks: true,
      init: function () {
        this.on("sending", function(file, xhr, formData) {
          formData.append("nonce", listeo_core.upload_nonce);
        });
      },
      success: function (file, response) {
        file.previewElement.classList.add("dz-success");
        response = response.replace(/(\r\n|\n|\r)/gm, "");
        file["attachment_id"] = response;

        var mediaUploaderIds = $("#media-uploader-ids");
        if (mediaUploaderIds.length) {
          var elementKey = mediaUploaderIds.attr("data-elementkey");
          mediaUploaderIds.append('<input type="hidden" name="_gallery[' + response + ']" value="' + response + '">');
        }
      },
      error: function (file, response) {
        file.previewElement.classList.add("dz-error");
      },
      removedfile: function (file) {
        var attachment_id = file["attachment_id"];
        if (attachment_id) {
          $.ajax({
            type: "POST",
            url: listeo_core.delete,
            data: {
              media_id: attachment_id,
              nonce: listeo_core.upload_nonce
            },
            success: function (result) {
              console.log("File deleted:", result);
            },
            error: function () {
              console.log("delete error");
            },
          });
          $('input[value="' + attachment_id + '"]').remove();
        }
        var _ref;
        return (_ref = file.previewElement) != null
          ? _ref.parentNode.removeChild(file.previewElement)
          : void 0;
      },
    });

      // Load existing images if any (only for manual initialization)
      if (typeof images !== 'undefined' && images) {
        var existingImages = JSON.parse(images);
        existingImages.forEach(function(image) {
          var mockFile = {
            name: image.name,
            size: image.size,
            attachment_id: image.attachment_id
          };
          galleryDropzone.emit("addedfile", mockFile);
          galleryDropzone.emit("thumbnail", mockFile, image.thumb);
          galleryDropzone.emit("complete", mockFile);
          galleryDropzone.files.push(mockFile);
        });
      }
    }
  }

  // Generic handler for all taxonomy category changes in search forms (supports all listing types)
  $(document).on("change", ".dynamic select[id*='_category'], .dynamic [id*='_category-panel'] input", function (e) {
    var cat_ids = [];

    $("#tax-listing_feature-panel .checkboxes").addClass("loading");
    $("#tax-listing_feature-panel .panel-buttons").hide();
    var panel = false;

    // Collect from drilldown-generated elements (for drilldown taxonomies)
    $(".drilldown-generated").each(function () {
      if ($(this).val()) {
        cat_ids.push($(this).val());
      }
    });

    // Collect from ALL taxonomy category selects (listing_category, service_category, rental_category, etc.)
    $(".dynamic select[id*='_category']").each(function() {
      var selectValue = $(this).val();
      if (selectValue && selectValue !== '' && selectValue !== 'all') {
        if ($(this).prop("multiple")) {
          // Handle multi-select
          var selectedValues = $(this).val();
          if (Array.isArray(selectedValues)) {
            selectedValues.forEach(function(val) {
              if (val && val !== '' && val !== 'all') {
                cat_ids.push(val);
              }
            });
          }
        } else {
          cat_ids.push(selectValue);
        }
      }
    });

    // Collect from ALL taxonomy category panels with checkboxes
    $(".dynamic [id*='_category-panel'] input[type=checkbox]:checked").each(function() {
      var checkboxValue = $(this).val();
      if (checkboxValue && checkboxValue !== '' && checkboxValue !== 'all') {
        cat_ids.push(checkboxValue);
      }
    });

    // Determine if we're using panel layout
    if ($("[id*='_category-panel']").length > 0) {
      panel = true;
    }

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "listeo_get_features_from_category",
        cat_ids: cat_ids,
        panel: panel,
        //'nonce': nonce
      },
      success: function (data) {
        $("#tax-listing_feature-panel .checkboxes").removeClass("loading");
        $("#tax-listing_feature-panel .checkboxes .row")
          .html(data["output"])
          .removeClass("loading");
        $("#tax-listing_feature").html(data["output"]).removeClass("loading");
        // $("#tax-listing_feature").empty().select2({
        //   data: data["data"],
        // });
        if (data["success"]) {
          $("#tax-listing_feature-panel .panel-buttons").show();
        }
      },
    });
  });
  $(".dynamic #tax-listing_category").trigger("change");
  //listeo_get_listing_types_from_categories

  $(
    ".dynamic-taxonomies #tax-listing_category,.dynamic-taxonomies #tax-listing_category-panel input"
  ).on("change", function (e) {
    var cat_ids = [];

    $(".dynamic-taxonomies #listeo-search-form_tax-service_category").hide();
    $(".dynamic-taxonomies #listeo-search-form_tax-rental_category").hide();
    $(".dynamic-taxonomies #listeo-search-form_tax-event_category").hide();
    $(
      ".dynamic-taxonomies #listeo-search-form_tax-classifieds_category"
    ).hide();
    $(
      ".dynamic-taxonomies #listeo-search-form_tax-service_category select, .dynamic-taxonomies #tax-service_category-panel select"
    ).val("");
    $(
      ".dynamic-taxonomies #listeo-search-form_tax-rental_category select, .dynamic-taxonomies #tax-rental_category-panel select"
    ).val("");
    $(
      ".dynamic-taxonomies #listeo-search-form_tax-event_category select, .dynamic-taxonomies #tax-event_category-panel select"
    ).val("");
    $(
      ".dynamic-taxonomies #listeo-search-form_tax-classifieds_category select, .dynamic-taxonomies #tax-classifieds_category-panel select"
    ).val("");
    $(".dynamic-taxonomies #tax-service_category-panel").hide();
    $(".dynamic-taxonomies #tax-rental_category-panel").hide();
    $(".dynamic-taxonomies #tax-event_category-panel").hide();
    $(".dynamic-taxonomies #tax-classifieds_category-panel").hide();
    $(".dynamic-taxonomies #tax-service_category-panel input:checkbox").prop(
      "checked",
      false
    );
    $(".dynamic-taxonomies #tax-rental_category-panel input:checkbox").prop(
      "checked",
      false
    );
    $(".dynamic-taxonomies #tax-event_category-panel input:checkbox").prop(
      "checked",
      false
    );
    $(
      ".dynamic-taxonomies #tax-classifieds_category-panel input:checkbox"
    ).prop("checked", false);

    $(
      ".dynamic-taxonomies #tax-event_category-panel input:checkbox"
    ).removeAttr("checked");
    var panel = false;
    if ($("#tax-listing_category-panel").length > 0) {
      panel = true;

      $("#tax-listing_category-panel input[type=checkbox]:checked").each(
        function () {
          cat_ids.push($(this).val());
        }
      );
    } else {
      if ($("#tax-listing_feature-panel").length > 0) {
        panel = true;
      }
      if ($(this).prop("multiple")) {
        $("#tax-listing_category :selected").each(function (i, sel) {
          cat_ids.push($(sel).val());
        });
      } else {
        cat_ids.push($(this).val());
      }
    }
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "listeo_get_listing_types_from_categories",
        cat_ids: cat_ids,
        panel: panel,
        //'nonce': nonce
      },
      success: function (data) {
        // $('#tax-listing_feature-panel .checkboxes').removeClass('loading');
        // $('#tax-listing_feature-panel .checkboxes .row').html(data['output']).removeClass('loading');
        // $('#tax-listing_feature').html(data['output']).removeClass('loading');
        // if(data['success']){
        //   $('#tax-listing_feature-panel .panel-buttons').show();
        // }
        //
        if (data["success"]) {
          var types = data["output"];
          
          // Dynamic handling for all listing types (including custom ones)
          types.forEach(function(type) {
            var categoryTaxonomy = type + '_category';
            
            // Show form elements for this type
            $(".dynamic-taxonomies #listeo-search-form_tax-" + categoryTaxonomy).show();
            $(".dynamic-taxonomies #tax-" + categoryTaxonomy + "-panel").css("display", "inline-block");
            
            // Also handle region taxonomy for backward compatibility
            if (type === 'region') {
              $(".dynamic-taxonomies #listeo-search-form_tax-region").show();
              $(".dynamic-taxonomies #tax-region-panel").css("display", "inline-block");
            }
          });
        }
      },
    });
  });

  // check if there's change in the input lists inside of .drilldown-menu

  $(document).on(
    "drilldown-updated",
    ".submit-page .drilldown-menu",
    function (e) {
      // Only trigger if dynamic features is enabled (check for .dynamic-features class)
      if (!$(this).closest(".add-listing-section").hasClass("dynamic-features")) {
        return;
      }

      var listing_id = $("input[name='listing_id']").val();

      var cat_ids = [];
      
      // Collect from drilldown-generated elements (for drilldown taxonomies)
      $(".drilldown-generated").each(function () {
        if ($(this).val()) {
          cat_ids.push($(this).val());
        }
      });
      
      // Collect from regular taxonomy dropdowns (service_category, rental_category, etc.)
      $(".submit-page select[name*='tax-'], .submit-page select[name*='_category']").each(function() {
        var selectValue = $(this).val();
        if (selectValue && selectValue !== '' && selectValue !== 'all') {
          cat_ids.push(selectValue);
        }
      });

      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: {
          action: "listeo_get_features_ids_from_category",
          cat_ids: cat_ids,
          listing_id: listing_id,
          selected: selected_listing_feature,
          panel: false,
          //'nonce': nonce
        },
        success: function (data) {
          $(
            ".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature"
          ).removeClass("loading");
          $(
            ".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature"
          )
            .html(data["output"])
            .removeClass("loading");
        },
      });
    }
  );

  $(document).on(
    "drilldown-updated",
    "#listeo_core-search-form .drilldown-menu",
    function (e) {
      var cat_ids = [];

      // Collect from drilldown-generated elements (for drilldown taxonomies)
      $(".drilldown-generated").each(function () {
        if ($(this).val()) {
          cat_ids.push($(this).val());
        }
      });

      // Collect from ALL taxonomy category selects (listing_category, service_category, rental_category, etc.)
      $("#listeo_core-search-form select[id*='_category']").each(function() {
        var selectValue = $(this).val();
        if (selectValue && selectValue !== '' && selectValue !== 'all') {
          if ($(this).prop("multiple")) {
            // Handle multi-select
            var selectedValues = $(this).val();
            if (Array.isArray(selectedValues)) {
              selectedValues.forEach(function(val) {
                if (val && val !== '' && val !== 'all') {
                  cat_ids.push(val);
                }
              });
            }
          } else {
            cat_ids.push(selectValue);
          }
        }
      });

      // Collect from ALL taxonomy category panels with checkboxes
      $("#listeo_core-search-form [id*='_category-panel'] input[type=checkbox]:checked").each(function() {
        var checkboxValue = $(this).val();
        if (checkboxValue && checkboxValue !== '' && checkboxValue !== 'all') {
          cat_ids.push(checkboxValue);
        }
      });

      $("#tax-listing_feature-panel .checkboxes").addClass("loading");
      $("#tax-listing_feature-panel .panel-buttons").hide();
      var panel = false;

      // Determine if we're using panel layout
      if ($("[id*='_category-panel']").length > 0) {
        panel = true;
      }

      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: {
          action: "listeo_get_features_from_category",
          cat_ids: cat_ids,
          panel: panel,
          //'nonce': nonce
        },
        success: function (data) {
          $("#tax-listing_feature-panel .checkboxes").removeClass("loading");
          $("#tax-listing_feature-panel .checkboxes .row")
            .html(data["output"])
            .removeClass("loading");
          $("#tax-listing_feature").html(data["output"]).removeClass("loading");
          // $("#tax-listing_feature").empty().select2({
          //   data: data["data"],
          // });
          if (data["success"]) {
            $("#tax-listing_feature-panel .panel-buttons").show();
          }
        },
      });
    }
  );

  $(
    ".add-listing-section.dynamic-features #listing_category,.add-listing-section.dynamic-features #tax-listing_category"
  ).on("change", function (e) {
    var listing_id = $("input[name='listing_id']").val();
    if ($(this).prop("multiple")) {
      var cat_ids;
      cat_ids = $(this).val();
    } else {
      var cat_ids = [];
      cat_ids.push($(this).val());
    }

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "listeo_get_features_ids_from_category",
        cat_ids: cat_ids,
        listing_id: listing_id,
        selected: selected_listing_feature,
        panel: false,
        //'nonce': nonce
      },
      success: function (data) {
        $(
          ".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature"
        ).removeClass("loading");
        $(
          ".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature"
        )
          .html(data["output"])
          .removeClass("loading");
      },
    });
  });

  // Handle regular taxonomy dropdown changes to update features
  $(document).on('change', '.submit-page select[name*="_category"], .submit-page select[name*="tax-"]', function(e) {
    // Only trigger if dynamic features is enabled (check for .dynamic-features class)
    if (!$(this).closest(".add-listing-section").hasClass("dynamic-features")) {
      return;
    }

    var listing_id = $("input[name='listing_id']").val();
    var cat_ids = [];
    
    // Collect from drilldown-generated elements (for drilldown taxonomies)
    $(".drilldown-generated").each(function () {
      if ($(this).val()) {
        cat_ids.push($(this).val());
      }
    });
    
    // Collect from regular taxonomy dropdowns (service_category, rental_category, etc.)
    $(".submit-page select[name*='tax-'], .submit-page select[name*='_category']").each(function() {
      var selectValue = $(this).val();
      if (selectValue && selectValue !== '' && selectValue !== 'all') {
        cat_ids.push(selectValue);
      }
    });

    if (cat_ids.length > 0) {
      var selected_listing_feature = [];
      $.each($("input[name='tax_input[listing_feature][]']:checked"), function () {
        selected_listing_feature.push($(this).val());
      });

      $(".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature").addClass("loading");

      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: {
          action: "listeo_get_features_ids_from_category",
          cat_ids: cat_ids,
          listing_id: listing_id,
          selected: selected_listing_feature,
          panel: false,
        },
        success: function (data) {
          $(".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature").removeClass("loading");
          $(".listeo_core-term-checklist-listing_feature,.listeo_core-term-checklist-tax-listing_feature")
            .html(data["output"])
            .removeClass("loading");
        },
      });
    }
  });

  var selected_listing_feature = [];
  if ($(".add-listing-section").length) {
    $.each(
      $("input[name='tax_input[listing_feature][]']:checked"),
      function () {
        selected_listing_feature.push($(this).val());
      }
    );

    $("select#listing_category").trigger("change");
    $("select#tax-listing_category").trigger("change");
  }

  if (
    $("body").hasClass("tax-listing_category") ||
    $("body").hasClass("post-type-archive-listing")
  ) {
    $("select#tax-listing_category").trigger("change");
    $("#tax-listing_category-panel input:checked").trigger("change");
  }
  $("#tax-listing_category-panel input:checked").trigger("change");
  // foreach
  // if($('.panel-dropdown-content .notification').length>0) {
  //   console.log($(this));
  //   $(this).find('.panel-buttons').hide();
  // }
  $(".panel-dropdown-content .notification").each(function (index) {
    $(this).parent().parent().find(".panel-buttons").hide();
  });

  // var uploadButton = {
  //   $button: $(".uploadButton-input"),
  //   $nameField: $(".uploadButton-file-name"),
  // };

  // uploadButton.$button.on("change", function () {
  //   _populateFileField($(this));
  // });

  // function _populateFileField($button) {
  //   var selectedFile = [];
  //   for (var i = 0; i < $button.get(0).files.length; ++i) {
  //     selectedFile.push($button.get(0).files[i].name + "<br>");
  //   }
  //   uploadButton.$nameField.html(selectedFile);
  // }

  // var uploadButton = {
  //   $button: $(".uploadButton-input"),
  //   $nameField: $(".uploadButton-file-name"),
  // };

  $(".add-listing-section").on("change", ".uploadButton-input", function (e) {
    _populateFileField($(this));
  });
  $(".dashboard-list-box-static").on(
    "change",
    ".uploadButton-input",
    function (e) {
      _populateFileField($(this));
    }
  );

  $(".zoom-anim-dialog").on("change", ".uploadButton-input", function (e) {
    _populateFileField($(this));
  });

  // Catch-all delegated handler for registration forms, review forms, and other contexts
  // This covers static pages that don't have the above container classes
  $(document).on("change", ".uploadButton-input", function (e) {
    _populateFileField($(this));
  });
  function _populateFileField($button) {
    console.log($button);
    var selectedFile = [];

    for (var i = 0; i < $button.get(0).files.length; ++i) {
      selectedFile.push($button.get(0).files[i].name + "<br>");
    }
    console.log(selectedFile);
    $($button).siblings(".uploadButton-file-name").html(selectedFile);
  }

  /*----------------------------------------------------*/
  /* Time Slots
  /*----------------------------------------------------*/
  /*----------------------------------------------------*/
  /* Time Slots
/*----------------------------------------------------*/

  // Add validation parts
  function updateCopySlotsDisplay(daySlots) {
    var dayIndex = daySlots.index(".day-slots");

    if (daySlots.find(".slots-container .single-slot").length > 0) {
      $(".copy-slots-item[data-value='" + dayIndex + "']").css(
        "display",
        "block"
      );
    } else {
      $(".copy-slots-item[data-value='" + dayIndex + "']").css(
        "display",
        "none"
      );
    }
    // Check if there are any slots in any day
    updateIcalDropdownVisibility();
  }

  // Function to check if any day has slots and update ical-dropdown-btn visibility
  function updateIcalDropdownVisibility() {
    // First check each individual day
    $(".day-slots").each(function (index) {
      var $currentDay = $(this);

      // Check if any OTHER day has slots (days we could copy from)
      var otherDaysWithSlots = false;
      $(".day-slots").each(function (otherIndex) {
        if (
          index !== otherIndex &&
          $(this).find(".slots-container .single-slot").length > 0
        ) {
          otherDaysWithSlots = true;
          return false; // Break the loop when we find at least one
        }
      });

      // Show Copy Day button only if other days have slots to copy from
      if (otherDaysWithSlots) {
        $currentDay.find(".ical-dropdown-btn").show();
      } else {
        $currentDay.find(".ical-dropdown-btn").hide();
      }
    });
  }

  function timeStringToMinutes(timeStr, ampm = null) {
    // Split the time string to get hours and minutes
    const [hours, minutes] = timeStr.split(":");
    let totalHours = parseInt(hours, 10);

    // Handle 12-hour format with AM/PM
    if (ampm) {
      // If it's 12 AM (midnight), we need to convert to 0 hours
      if (totalHours === 12 && ampm.toLowerCase() === "am") {
        totalHours = 0;
      }
      // If it's PM (but not 12 PM), we add 12 hours
      else if (ampm.toLowerCase() === "pm" && totalHours !== 12) {
        totalHours += 12;
      }
      // Note: 12 PM (noon) remains as 12 hours, which is correct
    }

    return totalHours * 60 + parseInt(minutes, 10);
  }

  function minutesToTimeString(minutes, use12HourFormat = false) {
    let totalHours = Math.floor(minutes / 60);
    let hours = totalHours % 24;
    let mins = minutes % 60;
    let ampm = "";

    if (hours < 0) {
      hours += 24;
    }

    if (mins < 0) {
      mins += 60;
      hours -= 1;
      if (hours < 0) {
        hours += 24;
      }
    }

    // Convert to 12-hour format if needed
    if (use12HourFormat) {
      // Determine AM/PM
      ampm = hours >= 12 ? "pm" : "am";

      // Convert to 12-hour clock
      if (hours > 12) {
        hours = hours - 12;
      } else if (hours === 0) {
        hours = 12; // 0 hours in 24-hour format is 12 AM in 12-hour format
      }
      // Note: hours = 12 stays as 12 (12 PM/noon)

      return {
        time: `${hours.toString().padStart(2, "0")}:${mins
          .toString()
          .padStart(2, "0")}`,
        ampm: ampm,
      };
    }

    // 24-hour format
    return `${hours.toString().padStart(2, "0")}:${mins
      .toString()
      .padStart(2, "0")}`;
  }

  $(document).ready(function () {
    // Check if we're using 12-hour or 24-hour format based on the listeo_core variable
    // listeo_core.clockformat = true means 24h format, false means 12h format
    var is12HourFormat =
      typeof listeo_core !== "undefined" &&
      listeo_core.clockformat !== undefined
        ? !listeo_core.clockformat
        : $(".availability-slots").data("clock-type") === "12hr";

    // Sync the slots editor UI into its containing hidden `_slots` input
    // as JSON. Mirrors the admin's `update_slots()` behaviour but scoped
    // to the `.availability-slots` editor that contains the changed
    // `.day-slots`. Called after every Add / Remove / quantity change so
    // the hidden input is always up-to-date — needed for forms whose own
    // submit handler doesn't run our serialisation pass (e.g. the
    // Booking Plus resource manager `#submit-resource-form`).
    function syncSlotsHiddenInput(daySlots) {
      var $editor = daySlots.closest(".availability-slots");
      if (!$editor.length) return;
      var slots = [];
      var slot_container = 0;
      $editor.find(".day-slots .slots-container").each(function () {
        var inside_slots = [];
        var slot_number = 0;
        $(this)
          .find(".single-slot-time")
          .each(function () {
            var $row = $(this).closest(".single-slot");
            if ($row.hasClass("cloned")) return;
            var timeText = $(this).text().replace(/-|-/g, "-");
            inside_slots[slot_number] =
              timeText + "|" + $row.find('input[name="slot-qty"]').val();
            slot_number++;
          });
        slots[slot_container] = inside_slots;
        slot_container++;
      });
      $editor
        .find("#_slots, input[type='hidden'][name='_slots']")
        .val(JSON.stringify(slots));
    }

    // Quantity-input change inside any single slot: re-serialise.
    $(document).on(
      "change input",
      ".availability-slots .single-slot input[name='slot-qty']",
      function () {
        var $daySlots = $(this).closest(".day-slots");
        if ($daySlots.length) {
          syncSlotsHiddenInput($daySlots);
        }
      }
    );

    // Delegated click safety net: regardless of whether the per-day-slots
    // bindings below fired, this captures every Add / Remove click and
    // re-serialises the hidden input on the next tick (after the per-day
    // handler has finished mutating the DOM). Belt-and-braces for forms
    // where the page-init `.day-slots.each(...)` binding doesn't reach
    // (e.g. dynamically rendered editors, race conditions).
    $(document).on(
      "click",
      ".availability-slots .add-slot-btn, .availability-slots .remove-slot",
      function () {
        var $daySlots = $(this).closest(".day-slots");
        if (!$daySlots.length) return;
        // Use rAF + a short timeout so the original per-day handler can
        // finish appending / removing rows before we serialise.
        setTimeout(function () { syncSlotsHiddenInput($daySlots); }, 50);
        setTimeout(function () { syncSlotsHiddenInput($daySlots); }, 500);
      }
    );

    // Belt-and-braces submit binding: also serialise on submit of every
    // form that contains an `.availability-slots` editor. The earlier
    // delegated handler at module top covers most cases, but a direct
    // bind here guarantees it for forms that exist at DOM ready
    // (`#submit-listing-form`, `#submit-resource-form`, `#post`, etc.).
    $("form").has(".availability-slots").on("submit", function () {
      var $form = $(this);
      $form.find(".availability-slots .day-slots").each(function () {
        syncSlotsHiddenInput($(this));
      });
    });

    $(".day-slots").each(function () {
      var daySlots = $(this);

      // Update the display of copy-slots-item elements initially
      updateCopySlotsDisplay(daySlots);

      // 12-hour format: when the user switches the START am/pm dropdown,
      // mirror the value into the END am/pm dropdown as long as the END
      // hasn't been explicitly touched yet. Catches the common mistake of
      // entering "1:00 pm – 2:00" without realising the END dropdown stayed
      // on its default "am", which silently produced a 13-hour slot
      // (1pm – 2am next day) instead of the intended 1-hour 1pm – 2pm.
      if (is12HourFormat) {
        var $endAmPm = daySlots.find(".add-slot-inputs select.time-slot-end");
        $endAmPm.on("change.lbpManual", function () {
          $(this).data("user-touched", true);
        });
        daySlots
          .find(".add-slot-inputs select.time-slot-start")
          .on("change", function () {
            if ($endAmPm.data("user-touched")) {
              return;
            }
            $endAmPm.val($(this).val());
          });
      }

      // Copy slots FROM another day
      daySlots.find(".copy-slots-item").on("click", function (e) {
        e.preventDefault();
        var targetDaySlots = $(this).closest(".day-slots");
        var sourceDayIndex = $(this).data("value");
        var sourceDaySlots = $(".day-slots").eq(sourceDayIndex);

        var slotsToCopy = sourceDaySlots
          .find(".slots-container .single-slot")
          .clone();

        // Remove existing slots with animation
        targetDaySlots
          .find(".slots-container .single-slot")
          .animate({ height: 0, opacity: 0 }, "fast", function () {
            $(this).remove();
          });

        // Add new slots with animation
        setTimeout(function () {
          targetDaySlots.find(".slots-container").append(slotsToCopy);
          // Re-initialize the numberPicker for copied slots
          slotsToCopy.find(".plusminus").each(function () {
            $(this).numberPicker();
          });
          slotsToCopy
            .hide()
            .animate({ height: "show", opacity: "show" }, "fast");
          // Re-serialise after the copied rows are in place.
          syncSlotsHiddenInput(targetDaySlots);
        }, 200);

        // Remove the "no-slots" message if it exists in the target day
        targetDaySlots
          .find(".no-slots")
          .addClass("no-slots-fadeout")
          .removeClass("no-slots-fadein");

        // Update the display of copy-slots-item elements after copying
        updateCopySlotsDisplay(sourceDaySlots);
        updateCopySlotsDisplay(targetDaySlots);
      });

      daySlots.find(".add-slot-btn").on("click", function (e) {
        e.preventDefault();

        var slotTime_Start = daySlots
          .find(".add-slot-inputs input.time-slot-start")
          .val();
        var slotTime_End = daySlots
          .find(".add-slot-inputs input.time-slot-end")
          .val();

        // Get AM/PM values for 12-hour format
        var slotTimePM_AM_Start = "";
        var slotTimePM_AM_End = "";

        if (is12HourFormat) {
          slotTimePM_AM_Start = daySlots
            .find(".add-slot-inputs select.time-slot-start")
            .val();
          slotTimePM_AM_End = daySlots
            .find(".add-slot-inputs select.time-slot-end")
            .val();
        }

        // Checks if input values are not blank
        if (slotTime_Start.length > 0 && slotTime_End.length > 0) {
          // New Time Slot Div
          var newTimeSlot = daySlots
            .find(".single-slot.cloned")
            .clone(true)
            .addClass("slot-animation")
            .removeClass("cloned");

          setTimeout(function () {
            newTimeSlot.removeClass("slot-animation");
          }, 300);

          newTimeSlot.find(".plusminus input").val("1");

          // Plus - Minus Init
          newTimeSlot.find(".plusminus").numberPicker();

          // Check if there's am/pm dropdown (12-hour format)
          if (is12HourFormat) {
            newTimeSlot
              .find(".single-slot-time")
              .html(
                slotTime_Start +
                  " " +
                  '<i class="am-pm">' +
                  slotTimePM_AM_Start +
                  "</i>" +
                  " - " +
                  slotTime_End +
                  " " +
                  '<i class="am-pm">' +
                  slotTimePM_AM_End +
                  "</i>"
              );
          } else {
            newTimeSlot
              .find(".single-slot-time")
              .html("" + slotTime_Start + " - " + slotTime_End);
          }

          // Appending new slot
          newTimeSlot.appendTo(daySlots.find(".slots-container"));

          // Refresh sortable script
          $(".slots-container").sortable("refresh");

          // Keep the hidden `_slots` input in lock-step with the UI.
          syncSlotsHiddenInput(daySlots);

          // Update the display of copy-slots-item elements after adding a new slot
          updateCopySlotsDisplay(daySlots);

          // Calculate hour difference
          var slotTime_StartInMinutes, slotTime_EndInMinutes;

          if (is12HourFormat) {
            slotTime_StartInMinutes = timeStringToMinutes(
              slotTime_Start,
              slotTimePM_AM_Start
            );
            slotTime_EndInMinutes = timeStringToMinutes(
              slotTime_End,
              slotTimePM_AM_End
            );
          } else {
            slotTime_StartInMinutes = timeStringToMinutes(slotTime_Start);
            slotTime_EndInMinutes = timeStringToMinutes(slotTime_End);
          }

          // If end time is on next day (crosses midnight), add 24 hours (1440 minutes) to the end time
          if (slotTime_EndInMinutes < slotTime_StartInMinutes) {
            slotTime_EndInMinutes += 24 * 60; // Add 24 hours in minutes
          }

          // Calculate the time difference
          const hourDifferenceInMinutes =
            slotTime_EndInMinutes - slotTime_StartInMinutes;

          if (hourDifferenceInMinutes > 0) {
            // Set the new start time to the previous end time
            if (is12HourFormat) {
              daySlots
                .find(".add-slot-inputs input.time-slot-start")
                .val(slotTime_End);
              daySlots
                .find(".add-slot-inputs select.time-slot-start")
                .val(slotTimePM_AM_End);

              // Calculate the new end time
              const newEndTimeInMinutes =
                slotTime_EndInMinutes + hourDifferenceInMinutes;
              const newEndTimeObj = minutesToTimeString(
                newEndTimeInMinutes,
                true
              );

              // Set new end time and AM/PM
              daySlots
                .find(".add-slot-inputs input.time-slot-end")
                .val(newEndTimeObj.time);
              daySlots
                .find(".add-slot-inputs select.time-slot-end")
                .val(newEndTimeObj.ampm);
            } else {
              // For 24-hour format
              daySlots
                .find(".add-slot-inputs input.time-slot-start")
                .val(slotTime_End);

              // Calculate the new end time
              const newEndTimeInMinutes =
                slotTime_EndInMinutes + hourDifferenceInMinutes;
              const newEndTime = minutesToTimeString(newEndTimeInMinutes);

              // Set new end time
              daySlots
                .find(".add-slot-inputs input.time-slot-end")
                .val(newEndTime);
            }
          } else {
            console.log(
              "End time is not larger than start time, not updating time inputs."
            );
          }
        } else {
          // Validation Error
          daySlots.find(".add-slot").addClass("add-slot-shake-error");
          setTimeout(function () {
            daySlots.find(".add-slot").removeClass("add-slot-shake-error");
          }, 600);
        }
      });

      // Removing "no slots" message
      function hideSlotInfo() {
        var slotCount = daySlots.find(".slots-container").children().length;
        if (slotCount < 1) {
          daySlots
            .find(".no-slots")
            .addClass("no-slots-fadein")
            .removeClass("no-slots-fadeout");
        }
      }
      hideSlotInfo();

      // Removing Slot
      daySlots.on("click touchstart", ".remove-slot", function (e) {
        e.preventDefault();
        $(this)
          .closest(".single-slot")
          .animate({ height: 0, opacity: 0 }, "fast", function () {
            $(this).remove();
            // Re-serialise after the row is actually gone from the DOM.
            syncSlotsHiddenInput(daySlots);
          });

        // Removing "no slots" message
        setTimeout(function () {
          hideSlotInfo();
        }, 400);

        // Update the display of copy-slots-item elements after removing a slot
        updateCopySlotsDisplay(daySlots);
      });

      // Showing "no slots" message
      daySlots.find(".add-slot-btn").on("click", function (e) {
        e.preventDefault();

        var slotCount = daySlots.find(".slots-container").children().length;

        if (slotCount >= 1) {
          daySlots
            .find(".no-slots")
            .removeClass("no-slots-fadein")
            .addClass("no-slots-fadeout");
        }
      });
    });
  });

  // Sotrable Script
  $(".slots-container").sortable();

  // 24 hour clock type switcher
  if ($(".availability-slots").attr("data-clock-type") == "24hr") {
    $(".availability-slots").addClass("twenty-four-clock");
    $(".availability-slots").find('input[type="time"]').attr({ max: "24:00" });
  }

  // Unified visibility controller function
  function shouldSectionBeVisible(section) {
    // Check if multi-step form is active
    const isMultiStepForm = document.querySelector(".submit-page")?.classList.contains("multi-step-form");
    
    if (!isMultiStepForm) {
      // Original behavior for non-multi-step forms
      const isBookingDependent = section.classList.contains('availability_calendar') ||
                                section.classList.contains('slots') ||
                                section.classList.contains('basic_prices');
      const isBookingEnabled = document.querySelector('#_booking_status')?.checked;
      
      if (isBookingDependent) {
        return isBookingEnabled;
      }
      return true;
    }
    
    // For multi-step forms, let the step controller handle visibility
    // Don't override with global booking visibility
    return null; // Let step system decide
  }

  // Switcher
  $(".add-listing-section").each(function () {
    var switcherSection = $(this);
    var switcherInput = $(this).find(".switch input");

    if (switcherInput.is(":checked")) {
      $(switcherSection).addClass("switcher-on");
    }

    switcherInput.change(function () {
      if (this.checked === true) {
        $(switcherSection).addClass("switcher-on");

        if (switcherInput.attr("id") == "_booking_status") {
          // Check if this is a multi-step form
          const isMultiStepForm = document.querySelector(".submit-page")?.classList.contains("multi-step-form");
          
          if (!isMultiStepForm) {
            // Only apply global visibility for non-multi-step forms
            $(
              ".add-listing-section.slots,.add-listing-section.basic_prices,.add-listing-section.availability_calendar"
            ).show();
          }
          // For multi-step forms, trigger an event for the step controller to handle
          else if (typeof window.listeoUpdateFormUI === 'function') {
            window.listeoUpdateFormUI();
          }
        }
      } else {
        $(switcherSection).removeClass("switcher-on");
        if (switcherInput.attr("id") == "_booking_status") {
          // Check if this is a multi-step form
          const isMultiStepForm = document.querySelector(".submit-page")?.classList.contains("multi-step-form");
          
          if (!isMultiStepForm) {
            // Only apply global visibility for non-multi-step forms
            $(
              ".add-listing-section.slots,.add-listing-section.basic_prices,.add-listing-section.availability_calendar"
            ).hide();
          }
          // For multi-step forms, trigger an event for the step controller to handle
          else if (typeof window.listeoUpdateFormUI === 'function') {
            window.listeoUpdateFormUI();
          }
        }
      }
    });
  });

  // Initial state setup - only for non-multi-step forms
  const isMultiStepForm = document.querySelector(".submit-page")?.classList.contains("multi-step-form");
  
  if (!isMultiStepForm) {
    if ($("#_booking_status").is(":checked")) {
      $(
        ".add-listing-section.slots,.add-listing-section.basic_prices,.add-listing-section.availability_calendar"
      ).show();
    } else {
      $(
        ".add-listing-section.slots,.add-listing-section.basic_prices,.add-listing-section.availability_calendar"
      ).hide();
    }
  }

  /*----------------------------------------------------*/
  /*  Booking Sticky Footer
  /*----------------------------------------------------*/
  $(".booking-sticky-footer a.button").on("click", function (e) {
    var $anchor = $(this);
    $("html, body").animate(
      { scrollTop: $($anchor.attr("href")).offset().top - 100 },
      1000
    );
  });

  /*----------------------------------------------------*/
  /* Opening Hours
  /*----------------------------------------------------*/

  $("body").on("click", ".opening-day-remove", function (e) {
    e.preventDefault();
    var div_class = $(this).data("remove");
    $(this).parent().parent().remove();
    $("div." + div_class).remove();
  });

  $("body").on("click", ".opening-day-add-hours", function (e) {
    e.preventDefault();
    var dayname = $(this).data("dayname");
    var count = $(this).parents(".opening-day").find(".row").length;
    var id = $(this).data("id");
    // Honor the form's name_prefix when present. Listing-side forms
    // use `_` (so name becomes `_sunday_opening_hour[]`), LBP's
    // resource form uses `_lbp_` (so name becomes
    // `_lbp_sunday_opening_hour[]`). Without this, added rows POST
    // under the wrong key and the save path silently drops them.
    var namePrefix = $(this).attr("data-name-prefix");
    if (typeof namePrefix !== "string" || namePrefix === "") {
      namePrefix = "_";
    }

    var newElem = $(
      "" +
        '<div class="row"><div class="col-md-2 opening-day-tools"><a class="opening-day-remove button" data-remove="' +
        dayname +
        "-opening-hours-row" +
        count +
        '" href="#">' +
        listeo_core.remove +
        "</a>" +
        '</div><div class="col-md-5 ' +
        dayname +
        "-opening-hours-row" +
        count +
        '">' +
        '<input type="text" class="listeo-flatpickr" name="' +
        namePrefix +
        id +
        '_opening_hour[]" placeholder="' +
        listeo_core.opening_time +
        '" value=""></div>' +
        '<div class="col-md-5 ' +
        dayname +
        "-opening-hours-row" +
        count +
        '" >' +
        '<input type="text" class="listeo-flatpickr" name="' +
        namePrefix +
        id +
        '_closing_hour[]" placeholder="' +
        listeo_core.closing_time +
        '" value="">' +
        "</div></div>"
    );

    newElem.appendTo($(this).parents(".opening-day"));
    var time24 = false;

    if (listeo_core.clockformat) {
      time24 = true;
    }
    // Display the time in 12h/24h depending on site setting. We keep
    // the underlying value as `H:i` (every backend path expects 24h
    // on the wire) but mount an altInput showing `h:i K` to the
    // customer when the site is on 12h time. Without altInput, the
    // input field shows the dateFormat ("H:i") and post-noon times
    // render as 13:00 even on a 12h site.
    var fpOpts = {
      enableTime: true,
      noCalendar: true,
      dateFormat: "H:i",
      time_24hr: time24,
      disableMobile: true,
    };
    if (!time24) {
      fpOpts.altInput = true;
      fpOpts.altFormat = "h:i K";
    }
    $(this)
      .parents(".opening-day")
      .find(".row:last .listeo-flatpickr")
      .flatpickr(fpOpts);
  });

  // On document ready, prevent select2 on registration fields
  $(document).ready(function() {
    // Trigger initial load for default selected role
    setTimeout(function() {
      var $defaultRadio = $("input[type=radio][name=user_role]:checked");
      if ($defaultRadio.length > 0) {
        $defaultRadio.trigger('change');
      }
    }, 100);
  });

  $("input[type=radio][name=user_role]").change(function () {
    // Destroy any existing select2 instances in registration fields
    $("#listeo-core-registration-fields").find(".select2-hidden-accessible").each(function() {
      try {
        $(this).select2("destroy");
      } catch(e) {}
    });

    $("#listeo-core-registration-fields").html("");
    if (
      this.value == "owner" ||
      this.value == "vendor" ||
      this.value == "seller"
    ) {
      $("#listeo-core-registration-fields").html(
        $(
          ".listeo-custom-fields-wrapper #listeo-core-registration-owner-fields"
        ).html()
      );
    } else {
      $("#listeo-core-registration-fields").html(
        $(
          ".listeo-custom-fields-wrapper #listeo-core-registration-guest-fields"
        ).html()
      );
    }

    // Remove select2 classes to ensure generic dropdowns
    $("#listeo-core-registration-fields").find("select").each(function() {
      $(this).removeClass("select2-single select2-multiple select2-hidden-accessible");
      $(this).removeAttr("data-select2-id");
      $(this).removeAttr("aria-hidden");
      $(this).removeAttr("tabindex");
    });

    // Remove any select2 containers
    $("#listeo-core-registration-fields").find(".select2-container").remove();
  });

  /*----------------------------------------------------*/
  /* Pricing List
  /*----------------------------------------------------*/
  function pricingCoverSwitcher() {
    var readURL = function (input) {
      if (input.files && input.files[0]) {
        var reader = new FileReader();
        var input_obj = $(input);

        reader.onload = function (e) {
          $(input).parent().find(".cover-pic").attr("src", e.target.result);
          $(input).parent().find(".menu-cover-id").val("");
        };

        reader.readAsDataURL(input.files[0]);
      }
    };
    $("#pricing-list-container").on("change", ".file-upload", function (e) {
      readURL(this);
    });
    $("#pricing-list-container").on("click", ".upload-button", function (e) {
      $(this).next(".file-upload").click();
    });

    // Allow-quantity toggle enables / disables the Max-quantity input on
    // the same row. The Max input stays visible at all times (so the
    // section structure stays stable) — just inactive when the toggle
    // is off. Delegated so it picks up rows added via newMenuItem().
    $("#pricing-list-container").on("change", ".pricing-quantity-enable", function () {
      var $maxInput = $(this).closest(".pricing-quantity-pair").find(".bookable_quantity_max");
      $maxInput.prop("disabled", !this.checked);
      if (!this.checked) {
        $maxInput.val("");
      }
    });
  }
  pricingCoverSwitcher();

  $(".remove-cover").on("click", function (e) {
    e.preventDefault();
    $(this).next(".menu-cover-id").val("");
    $(this)
      .parent()
      .find(".cover-pic")
      .attr("src", listeo.theme_url + "/images/pricing-cover-placeholder.png");
  });

  function newMenuItem() {
    // var newElem = $('tr.pricing-list-item:not(.pricing-submenu)').last().clone(true);
    // console.log(newElem.length);
    // if(!newElem){
    var extra_services = listeo_core.extra_services_options_type;

    //check if extra_services is an array
    var select_extra_service_types = "";
    if (typeof extra_services === "string") {
      extra_services = extra_services.split(",");
    }

    //  '<div class="fm-input pricing-bookable-options">' +
    //   '<select class="select2-single" name="_menu[0][menu_elements][0][bookable_options]" id="">' +
    //   select_extra_service_types +
    //   "</select>" +
    //   '<div class="checkboxes in-row pricing-quanity-buttons">' +
    //   '<input type="checkbox"  class="input-checkbox" name="_menu[0][menu_elements][0][bookable_quantity]" id="_menu[0][menu_elements][0][bookable_quantity]" />' +
    //   '<label for="_menu[0][menu_elements][0][bookable_quantity]">' +
    //   listeo_core.quantitybuttons +
    //   "</label>" +
    //   '<input type="number" class="bookable_quantity_max" step="1" name="_menu[0][menu_elements][0][bookable_quantity_max]" placeholder="' +
    //   listeo_core.bookable_quantity_max +
    //   '" />"' +
    //   "</div>" +
    // check if extra_services size is less then 4
    // Build the right-column blocks (charge type / quantity) only when
    // the site has fewer than 4 charge-type options disabled — same gate
    // the PHP template applies. Empty string when not applicable.
    var charge_type_block = "";
    var quantity_block = "";
    if (extra_services.length < 4) {
      charge_type_block =
        '<div class="fm-input pricing-bookable-options">' +
        '<label class="fm-input-label">' +
        ( listeo_core.pricing_label_charge_type || "Charge Type" ) +
        "</label>" +
        '<select class="select2-single" name="_menu[0][menu_elements][0][bookable_options]">';
      if (!extra_services.includes("onetime")) {
        charge_type_block +=
          '<option value="onetime">' + listeo_core.onetimefee + "</option>";
      }
      if (!extra_services.includes("byguest")) {
        charge_type_block +=
          '<option value="byguest">' + listeo_core.multiguest + "</option>";
      }
      if (!extra_services.includes("bydays")) {
        charge_type_block +=
          '<option value="bydays">' + listeo_core.multidays + "</option>";
      }
      if (!extra_services.includes("byguestanddays")) {
        charge_type_block +=
          '<option value="byguestanddays">' +
          listeo_core.multiguestdays +
          "</option>";
      }
      charge_type_block += "</select></div>";

      quantity_block =
        '<div class="pricing-row-pair pricing-quantity-pair">' +
          '<div class="fm-input pricing-quantity">' +
            '<div class="checkboxes in-row pricing-quantity-row">' +
              '<input type="checkbox" class="input-checkbox pricing-quantity-enable" name="_menu[0][menu_elements][0][bookable_quantity]" id="_menu_0_0_qty" value="on" />' +
              '<label for="_menu_0_0_qty">' + ( listeo_core.quantitybuttons || "Allow quantity" ) + "</label>" +
            "</div>" +
            '<p class="pricing-field-desc">' + ( listeo_core.allow_quantity_desc || "Customer can pick more" ) + "</p>" +
          "</div>" +
          '<div class="fm-input pricing-quantity-max-wrap">' +
            '<label class="fm-input-label">' + ( listeo_core.bookable_quantity_max || "Max quantity" ) + "</label>" +
            '<input type="number" class="bookable_quantity_max" step="1" min="1" disabled name="_menu[0][menu_elements][0][bookable_quantity_max]" placeholder="' +
            ( listeo_core.optional || "Optional" ) +
            '" />' +
          "</div>" +
        "</div>";
    }

    // 3-column markup — mirrors the PHP template's
    // `listeo_render_pricing_row()`. Col 1: image. Col 2: title +
    // description + bookable toggle. Col 3: price + charge type +
    // quantity + LBP extras (duration / individual).
    var newElem = $(
      '<tr class="pricing-list-item pattern" data-iterator="0">' +
        "<td>" +
          '<div class="fm-move"><i class="sl sl-icon-cursor-move"></i></div>' +
          '<div class="pricing-row-grid">' +

            '<div class="pricing-row-image">' +
              '<div class="fm-input pricing-cover">' +
               
                '<div class="pricing-cover-wrapper" data-tippy-placement="bottom" title="Change Cover">' +
                  '<img class="cover-pic" src="' + listeo.theme_url + '/images/pricing-cover-placeholder.png" alt="">' +
                  '<div class="upload-button"></div>' +
                  '<input class="file-upload" type="file" accept="image/*" name="_menu[0][menu_elements][0][cover]">' +
                "</div>" +
              "</div>" +
            "</div>" +

            '<div class="pricing-row-info pricing-row-section">' +
              '<h4 class="pricing-row-section-title">' +
                '<span class="pricing-row-section-num">1</span>' +
                ( listeo_core.section_basic_info || "Basic service info" ) +
              "</h4>" +

              '<div class="fm-input pricing-name">' +
                '<label class="fm-input-label">' + listeo_core.menu_title + "</label>" +
                '<input type="text" placeholder="' + listeo_core.menu_title + '" name="_menu[0][menu_elements][0][name]" />' +
              "</div>" +

              '<div class="fm-input pricing-ingredients">' +
                '<label class="fm-input-label">' + listeo_core.menu_desc + "</label>" +
                '<textarea name="_menu[0][menu_elements][0][description]" placeholder="' + listeo_core.menu_desc + '" rows="3"></textarea>' +
              "</div>" +

              '<div class="pricing-row-pair">' +
                '<div class="fm-input pricing-price">' +
                  '<label class="fm-input-label">' +
                  ( listeo_core.pricing_label_price || "Price" ) +
                  "</label>" +
                  '<input type="number" step="0.01" placeholder="' + listeo_core.menu_price + '" name="_menu[0][menu_elements][0][price]" data-unit="' + listeo_core.currency_symbol + '" />' +
                "</div>" +
                charge_type_block +
              "</div>" +
            "</div>" +

            '<div class="pricing-row-right pricing-row-section">' +
              '<h4 class="pricing-row-section-title">' +
                '<span class="pricing-row-section-num">2</span>' +
                ( listeo_core.section_booking_settings || "Booking & availability settings" ) +
              "</h4>" +

              '<div class="fm-input pricing-bookable">' +
                '<div class="switcher-tip" data-tip-content="' + listeo_core.pricingTooltip + '">' +
                  '<input type="checkbox" class="input-checkbox switch_1" id="_menu_0_0_bookable" value="on" name="_menu[0][menu_elements][0][bookable]" />' +
                  '<label for="_menu_0_0_bookable" class="pricing-bookable-label">' + ( listeo_core.bookable_label || "Bookable service" ) + "</label>" +
                "</div>" +
              "</div>" +

              quantity_block +
            "</div>" +

            '<div class="pricing-row-close">' +
              '<div class="fm-close"><a class="delete" href="#"><i class="fa fa-remove"></i></a></div>' +
            "</div>" +

          "</div>" +
        "</td>" +
      "</tr>"
    );
    //}

    newElem.find("input").val("");
    newElem.find("textarea").val("");

    var prev_category_number = $(".pricing-submenu").last().data("number");
    var prev_data_iterator = $("tr.pricing-list-item:not(.pricing-submenu)")
      .last()
      .data("iterator");

    if (prev_category_number == undefined) {
      prev_category_number = 0;
    }

    var next_data_iterator = prev_data_iterator + 1;

    var last_table_el = $("tr.pricing-list-item").last();

    newElem.find("input").each(function () {
      // replace 1st number with current category title number

      this.name = this.name.replace(
        /\[\d+\]/,
        "[" + prev_category_number + "]"
      );
      this.id = this.id.replace(/\[\d+\]/, "[" + prev_category_number + "]");
      //replace 2nd number / if it's new category start from 0, if not iterate
      if (last_table_el.hasClass("pricing-submenu")) {
        next_data_iterator = 0;
        // replace 2nd number
        this.name = replaceLast(
          this.name,
          "[0]",
          "[" + next_data_iterator + "]"
        );
        this.id = replaceLast(this.id, "[0]", "[" + next_data_iterator + "]");
      } else {
        // replace 2nd number
        this.name = replaceLast(
          this.name,
          "[0]",
          "[" + next_data_iterator + "]"
        );
        this.id = replaceLast(this.id, "[0]", "[" + next_data_iterator + "]");
      }
    });
    newElem.find("select").each(function () {
      // replace 1st number with current category title number

      this.name = this.name.replace(
        /\[\d+\]/,
        "[" + prev_category_number + "]"
      );
      this.id = this.id.replace(/\[\d+\]/, "[" + prev_category_number + "]");
      //replace 2nd number / if it's new category start from 0, if not iterate
      if (last_table_el.hasClass("pricing-submenu")) {
        next_data_iterator = 0;
        // replace 2nd number
        this.name = replaceLast(
          this.name,
          "[0]",
          "[" + next_data_iterator + "]"
        );
        this.id = replaceLast(this.id, "[0]", "[" + next_data_iterator + "]");
      } else {
        // replace 2nd number
        this.name = replaceLast(
          this.name,
          "[0]",
          "[" + next_data_iterator + "]"
        );
        this.id = replaceLast(this.id, "[0]", "[" + next_data_iterator + "]");
      }
    });

    // Description switched from <input type="text"> to <textarea> in the
    // two-column rebuild, so re-index its name attr the same way as inputs.
    newElem.find("textarea").each(function () {
      this.name = this.name.replace(
        /\[\d+\]/,
        "[" + prev_category_number + "]"
      );
      if (last_table_el.hasClass("pricing-submenu")) {
        next_data_iterator = 0;
      }
      this.name = replaceLast(
        this.name,
        "[0]",
        "[" + next_data_iterator + "]"
      );
    });

    newElem.find("label").each(function () {
      //replace 1st number with current category title number
      this.htmlFor = this.htmlFor.replace(
        /\[\d+\]/,
        "[" + prev_category_number + "]"
      );
      //console.log(this.htmlFor);

      //replace 2nd number / if it's new category start from 0, if not iterate
      if (last_table_el.hasClass("pricing-submenu")) {
        next_data_iterator = 0;
        // replace 2nd number
        this.htmlFor = replaceLast(
          this.htmlFor,
          "[0]",
          "[" + next_data_iterator + "]"
        );
      } else {
        // replace 2nd number
        this.htmlFor = replaceLast(
          this.htmlFor,
          "[0]",
          "[" + next_data_iterator + "]"
        );
      }
    });
    //console.log(newElem);

    // Second-pass rewrite for underscore-style IDs / htmlFors. The
    // earlier loops only touch `[0]`-bracketed name attributes, but
    // the markup also carries IDs in `_menu_<cat>_<iter>_<suffix>`
    // form (e.g. `_menu_0_0_qty`, `_menu_0_0_bookable`) plus matching
    // <label for="..."> targets. Without this pass every new row's
    // labels point back at the first row's checkbox IDs, so clicking
    // any new row's "Allow quantity" / "Bookable service" label
    // toggles the FIRST row's checkbox instead of its own.
    var underscoreSrc = /^_menu_\d+_\d+_/;
    var underscoreDst = "_menu_" + prev_category_number + "_" + next_data_iterator + "_";
    newElem.find('[id^="_menu_"]').each(function () {
      this.id = this.id.replace(underscoreSrc, underscoreDst);
    });
    newElem.find('label[for^="_menu_"]').each(function () {
      this.htmlFor = this.htmlFor.replace(underscoreSrc, underscoreDst);
    });

    newElem
      .appendTo("table#pricing-list-container")
      .data("iterator", next_data_iterator)
      .find("select")
      .trigger("chosen:updated");

    // Theme's `custom.js` only initialises `.select2-single` on
    // document ready, so dynamically added rows render as a plain
    // native <select>. Re-init here so the new charge-type dropdown
    // matches the styling of the existing rows.
    newElem.find(".select2-single").each(function () {
      var $sel = $(this);
      if (!$sel.hasClass("select2-hidden-accessible")) {
        $sel.select2({
          dropdownPosition: "below",
          minimumResultsForSearch: 20,
          width: "100%",
        });
      }
    });

    // Currency-prefix `<i class="data-unit">` is normally inserted on
    // page load (see the loop below near line 3195); that pass doesn't
    // touch dynamically added rows, leaving the new price field
    // without the prefix that gives it its rounded-border styling.
    newElem.find(".pricing-price > input[data-unit]").each(function () {
      var $input = $(this);
      if ($input.prev("i.data-unit").length === 0) {
        $input.before('<i class="data-unit">' + $input.attr("data-unit") + "</i>");
      }
    });

    $("#pricing-list-container").data("iterator", next_data_iterator);

    // Notify plugins (e.g. Listeo Booking Plus) so they can inject any
    // per-service fields registered via the `listeo_menu_element_extra_fields`
    // PHP hook into the freshly added row.
    $(document).trigger("listeo:pricing-row-added", [
      newElem,
      prev_category_number,
      next_data_iterator,
    ]);
  }

  var test = "_menu[0][menu_elements][0][bookable_quantity]";
  // console.log(test);
  // console.log(test.search(/\[\d+\]/));

  function replaceLast(string, search, replace) {
    // find the index of last time word was used
    var n = string.lastIndexOf(search);

    // slice the string in 2, one from the start to the lastIndexOf
    // and then replace the word in the rest
    return string.slice(0, n) + string.slice(n).replace(search, replace);
  }

  if ($("table#pricing-list-container").is("*")) {
    $(".add-pricing-list-item").on("click", function (e) {
      e.preventDefault();
      newMenuItem();
    });

    // remove ingredient
    $(document).on("click", "#pricing-list-container .delete", function (e) {
      e.preventDefault();
      // Walk up to the actual row. The legacy parent().parent() only reached
      // .pricing-row-close in the new pricing grid markup, so the click was
      // wiping the close button instead of the row.
      $(this).closest("tr.pricing-list-item").remove();
    });

    // add submenu
    $(".add-pricing-submenu").on("click", function (e) {
      e.preventDefault();
      var i = $(".pricing-submenu").length;

      var newElem = $(
        "" +
          '<tr class="pricing-list-item pricing-submenu" data-number="' +
          i +
          '">' +
          "<td>" +
          '<div class="fm-move"><i class="sl sl-icon-cursor-move"></i></div>' +
          '<div class="fm-input"><input name="_menu[' +
          i +
          '][menu_title]" type="text" placeholder="' +
          listeo_core.category_title +
          '" /></div>' +
          '<div class="fm-close"><a class="delete" href="#"><i class="fa fa-remove"></i></a></div>' +
          "</td>" +
          "</tr>"
      );

      newElem.appendTo("table#pricing-list-container");

      // Re-index all categories and their elements to prevent conflicts
      // This ensures existing extra services don't conflict with the new category
      updateNames($("table#pricing-list-container tbody"));
    });

    $("table#pricing-list-container tbody").sortable({
      forcePlaceholderSize: true,
      forceHelperSize: false,
      placeholder: "sortableHelper",
      zIndex: 999990,
      opacity: 0.6,
      tolerance: "pointer",
      start: function (e, ui) {
        ui.placeholder.height(ui.helper.outerHeight());
      },
      stop: function (event, ui) {
        updateNames($(this));
      },
    });

    $(window).on("load resize", function () {
      var winWidth = $(window).width();
      if (winWidth < 992) {
        $("table#pricing-list-container tbody").sortable("disable");
      } else if (winWidth > 992) {
        $("table#pricing-list-container tbody").sortable("enable");
      }
    });
  }

  //updates list name numbers
  function updateNames($list) {
    var cat_i = 0;
    var subcat_i = 0;
    $list.find("tr").each(function () {
      var prev_data_iterator = $(this).data("iterator");

      //category
      if ($(this).hasClass("pricing-submenu")) {
        var cat_input = $(this).find("input");
        cat_input.each(function () {
          this.name = this.name.replace(/(\[\d\])/, "[" + cat_i + "]");
        });
        var cat_select = $(this).find("select");

        cat_select.each(function () {
          this.name = this.name.replace(/(\[\d\])/, "[" + cat_i + "]");
        });
        $(this).data("number", cat_i);
      } else {
        var prev_category_number = $(this)
          .prevAll(".pricing-submenu")
          .first()
          .data("number");
        if (typeof prev_category_number === "undefined") {
          prev_category_number = 0;
        }
        var subcat_input = $(this).find("input");

        subcat_input.each(function () {
          // replace 1st number with current category title number
          this.name = this.name.replace(
            /\[\d+\]/,
            "[" + prev_category_number + "]"
          );
          this.name = replaceLast(
            this.name,
            "[" + prev_data_iterator + "]",
            "[" + subcat_i + "]"
          );
        });
        var subcat_select = $(this).find("select");

        subcat_select.each(function () {
          // replace 1st number with current category title number
          this.name = this.name.replace(
            /\[\d+\]/,
            "[" + prev_category_number + "]"
          );
          this.name = replaceLast(
            this.name,
            "[" + prev_data_iterator + "]",
            "[" + subcat_i + "]"
          );
        });
        $(this).data("iterator", subcat_i);
        subcat_i++;
      }

      if ($(this).hasClass("pricing-submenu")) {
        cat_i++;
        subcat_i = 0;
      }
      // $inp.each(function () {
      //     this.name = this.name.replace(/(\[\d\])/, '[' + idx + ']');
      // })
    });
  }

  // Unit character
  var fieldUnit = $(".pricing-price").children("input").attr("data-unit");
  $(".pricing-price")
    .children("input")
    .before('<i class="data-unit">' + fieldUnit + "</i>");

  // Unit character

  if (
    $("body").hasClass("page-template-template-home-search-splash") ||
    $("body").hasClass("page-template-template-home-search") ||
    $("body").hasClass("page-template-template-split-map")
  ) {
    var open_cal = "right";
  } else {
    var open_cal = "left";
  }

  $(document).on("click", ".add-repeatable-list-item", function (e) {
    e.preventDefault();
    newRepeatableItem($(this));
  });

  // remove ingredient
  $(document).on("click", "#repeatable-list-container .delete", function (e) {
    e.preventDefault();
    $(this).parent().parent().remove();
  });

  // ------------------------------------------------------------------
  // Mandatory Fees repeater — submit-listing frontend
  //
  // Self-contained add/remove/conditions handlers for the dedicated
  // `.listeo-mandatory-fees-list` markup (templates/form-fields/
  // mandatory-fees-form.php). We deliberately don't reuse the generic
  // `#repeatable-list-container` cloner because that one is hard-coded
  // around <table> markup and the fee row has its own grid layout
  // plus a collapsible conditions panel.
  // ------------------------------------------------------------------

  // Remove a fee row. Always leave at least one row so the owner has
  // somewhere to type; clearing the last row is more intuitive than
  // an empty list with just an "Add Fee" button.
  $(document).on(
    "click",
    ".listeo-mandatory-fees-list .listeo-fee-remove",
    function (e) {
      e.preventDefault();
      var $list = $(this).closest(".listeo-mandatory-fees-list");
      var $row = $(this).closest(".listeo-fee-row");
      if ($list.find(".listeo-fee-row").length > 1) {
        $row.remove();
        reindexMandatoryFees($list);
      } else {
        $row.find("input[type='text'], input[type='number'], textarea").val("");
        $row.find("select").each(function () {
          this.selectedIndex = 0;
        });
        $row.find('input[type="hidden"][name$="[id]"]').val(newFeeId());
      }
    }
  );

  // Add a new fee row by cloning the last one and clearing its fields.
  // The hidden `[id]` input is regenerated so the server-side
  // merge-by-id sanitizer treats it as new rather than overwriting an
  // existing stored row.
  $(document).on("click", ".listeo-mandatory-fees-list .listeo-fee-add", function (e) {
    e.preventDefault();
    var $list = $(this).closest(".listeo-mandatory-fees-list");
    var $template = $list.find(".listeo-fee-row").last();
    if (!$template.length) return;

    var $newRow = $template.clone();
    $newRow.find("input[type='text'], input[type='number'], textarea").val("");
    $newRow.find("select").each(function () {
      this.selectedIndex = 0;
    });
    $newRow.find('input[type="hidden"][name$="[id]"]').val(newFeeId());

    $newRow.insertBefore($(this));
    reindexMandatoryFees($list);
    // Sync the price unit on the new row — `selectedIndex = 0` above
    // resets type to the first option (flat), so the cloned percent
    // unit needs to revert to the currency glyph.
    syncFeeRowPriceUnit($newRow);
  });

  function newFeeId() {
    return "fee_" + Date.now().toString(36) + Math.floor(Math.random() * 1e4).toString(36);
  }

  // Mandatory fees: swap the price field's trailing glyph + placeholder
  // when the row's type changes (flat → currency symbol + "Price",
  // percent → "%" + "Rate"). Reads everything from data-attrs on the
  // price wrapper so localized labels & currency symbol come straight
  // from PHP without a second AJAX round-trip.
  function syncFeeRowPriceUnit($row) {
    var type = $row.find("select.listeo-fee-input-type").val();
    var $wrap = $row.find(".listeo-fee-input-price");
    if (!$wrap.length) return;
    var isPercent = type === "percent";
    var symbol = isPercent
      ? $wrap.data("percentSymbol") || "%"
      : $wrap.data("flatSymbol") || "";
    var placeholder = isPercent
      ? $wrap.data("percentPlaceholder") || "Rate"
      : $wrap.data("flatPlaceholder") || "Price";
    $wrap.find(".listeo-fee-currency").text(symbol);
    $wrap.find("input").attr("placeholder", placeholder);
  }

  $(document).on("change", ".listeo-mandatory-fees-list select.listeo-fee-input-type", function () {
    syncFeeRowPriceUnit($(this).closest(".listeo-fee-row"));
  });

  // Renumber `name="<field>[<i>][<sub>][...]"` on every row so the
  // server sees a clean 0..N sequence regardless of add/remove order.
  function reindexMandatoryFees($list) {
    var fieldName = $list.data("field-name");
    if (!fieldName) return;
    var prefix = String(fieldName).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    var re = new RegExp("^" + prefix + "\\[\\d+\\]");
    $list.find(".listeo-fee-row").each(function (index) {
      $(this).attr("data-iterator", index);
      $(this).find("input, select, textarea").each(function () {
        var name = $(this).attr("name");
        if (!name) return;
        $(this).attr("name", name.replace(re, fieldName + "[" + index + "]"));
      });
    });
  }

  function newRepeatableItem(el) {
    var newElem = el.parent().find(".repeatable-list-item").last().clone(true);
    var appendTo = el.parent().find("table");
    newElem.find("input").val("");
    newElem.find("select").val("");
    newElem.find("textarea").val("");

    
    
    var prev_data_iterator = el
      .parent()
      .find(".repeatable-list-item")
      .last()
      .data("iterator");

    var next_data_iterator = prev_data_iterator + 1;

    newElem.find("input").each(function () {
      // replace 1st number with current category title number

      this.name = this.name.replace(/\[\d+\]/, "[" + next_data_iterator + "]");
      this.id = this.id.replace(/\[\d+\]/, "[" + next_data_iterator + "]");
    });
    newElem.find("select").each(function () {
      this.name = this.name.replace(/\[\d+\]/, "[" + next_data_iterator + "]");
      this.id = this.id.replace(/\[\d+\]/, "[" + next_data_iterator + "]");
    });
    newElem.find("textarea").each(function () {
      this.name = this.name.replace(/\[\d+\]/, "[" + next_data_iterator + "]");
      this.id = this.id.replace(/\[\d+\]/, "[" + next_data_iterator + "]");
    });
    newElem
      .appendTo(appendTo)
      .removeClass("pattern")
      .data("iterator", next_data_iterator);
  }

  // loop all .fm-input and check if it has data-unit attribute
  $(".repeatable-list-item .fm-input").each(function () {
    var repeatableFieldUnit = $(this).children("input").data("unit");
    if (repeatableFieldUnit)
      $(this)
        .children("input")
        .before('<i class="data-unit">' + repeatableFieldUnit + "</i>");
  });

  // var repeatableFieldUnit = $(".repeatable-list-item .fm-input input").data("unit");
  // console.log($(".repeatable-list-item .fm-input input").data("unit"));
  // if (repeatableFieldUnit)
  //   $(".repeatable-list-item .fm-input")
  //     .children("input")
  //     .before('<i class="data-unit">' + repeatableFieldUnit + "</i>");

  $(".date_range").daterangepicker({
    opens: open_cal,
    // checking attribute listing type and set type of calendar
    autoUpdateInput: false,

    minDate: moment().subtract(0, "days"),
    locale: {
      format: wordpress_date_format.date,
      firstDay: parseInt(wordpress_date_format.day),
      applyLabel: listeo_core.applyLabel,
      cancelLabel: listeo_core.clearLabel,
      fromLabel: listeo_core.fromLabel,
      toLabel: listeo_core.toLabel,
      customRangeLabel: listeo_core.customRangeLabel,
      daysOfWeek: [
        listeo_core.day_short_su,
        listeo_core.day_short_mo,
        listeo_core.day_short_tu,
        listeo_core.day_short_we,
        listeo_core.day_short_th,
        listeo_core.day_short_fr,
        listeo_core.day_short_sa,
      ],
      monthNames: [
        listeo_core.january,
        listeo_core.february,
        listeo_core.march,
        listeo_core.april,
        listeo_core.may,
        listeo_core.june,
        listeo_core.july,
        listeo_core.august,
        listeo_core.september,
        listeo_core.october,
        listeo_core.november,
        listeo_core.december,
      ],
    },
  });

  $(".date_range").on("apply.daterangepicker", function (ev, picker) {
    $("input[name=_listing_type]").prop("disabled", false);
    $(this)
      .val(
        picker.startDate.format(wordpress_date_format.date) +
          " - " +
          picker.endDate.format(wordpress_date_format.date)
      )
      .trigger("change");
    Cookies.set(
      "listeo_rental_startdate",
      picker.startDate.format(wordpress_date_format.date)
    );
    Cookies.set(
      "listeo_rental_enddate",
      picker.endDate.format(wordpress_date_format.date)
    );
  });

  $(".date_range").on("cancel.daterangepicker", function (ev, picker) {
    $("input[name=_listing_type]").prop("disabled", true);
    $(this).val("").trigger("change");
  });

  $(".date_range").on("show.daterangepicker", function (ev, picker) {
    $(".daterangepicker").addClass(
      "calendar-visible calendar-animated bordered-alt-style"
    );
    $(".daterangepicker").removeClass("calendar-hidden");
    $("input[name=_listing_type]").prop("disabled", false);
  });
  $(".date_range").on("hide.daterangepicker", function (ev, picker) {
    $(".daterangepicker").removeClass("calendar-visible");
    $(".daterangepicker").addClass("calendar-hidden");
  });

  $("input.slot-time-input")
    .keydown(function (e) {
      if (e.ctrlKey || e.metaKey) {
        return true;
      }

      if (e.which >= 37 && e.which <= 40) {
        return true;
      }

      if (e.which !== 8 && e.which !== 0 && e.key.match(/[^:0-9]/)) {
        return false;
      }
    })
    .keyup(function (e) {
      var $this = $(this);

      if (
        e.ctrlKey ||
        e.metaKey ||
        e.which === 8 ||
        e.which === 0 ||
        (e.which >= 37 && e.which <= 40)
      ) {
        return true;
      }

      var ss = parseInt(this.selectionStart);

      var v = $this.val();
      var t = v.replace(/[^0-9]/g, "");
      if ($(".availability-slots").attr("data-clock-type") == "24hr") {
        var h = Math.max(0, Math.min(24, parseInt(t.substr(0, 2))));
      } else {
        var h = Math.max(0, Math.min(12, parseInt(t.substr(0, 2))));
      }
      var m = Math.max(0, Math.min(59, parseInt(t.substr(2))));

      if (t.length < 3) {
        m = "";
      }

      var r;

      if (v.length === 2) {
        r = String("0" + h).substr(String(h).length - 1) + ":";
        ss++;
      } else if (v.length >= 3 && v.length < 5) {
        r = String("0" + h).substr(String(h).length - 1) + ":" + m;
        ss++;
      } else if (v.length === 5) {
        r =
          String("0" + h).substr(String(h).length - 1) +
          ":" +
          String("0" + m).substr(String(m).length - 1);
      }

      if (r && r !== $this.val()) {
        $this.val(r);
        this.selectionStart = this.selectionEnd = ss;
      }
    })
    .blur(function (e) {
      var $this = $(this);

      var v = $this.val();
      var t = v.replace(/[^0-9]/g, "");
      var h = Math.max(0, Math.min(23, parseInt(t.substr(0, 2))));
      var m = Math.max(0, Math.min(59, parseInt(t.substr(2)))) || 0;
      var r = "";

      if (!isNaN(h)) {
        r =
          String("0" + h).substr(String(h).length - 1) +
          ":" +
          String("0" + m).substr(String(m).length - 1);
      }

      $this.val(r);
    });

  $(document.body).on("click", ".remove-uploaded-file", function () {
    $(this).closest(".job-manager-uploaded-file").remove();
    $(this).closest(".listeo-uploaded-file").remove();

    return false;
  });

  //select export ical
  //
  $("input.listeo-export-ical-input").blur(function () {
    if ($(this).attr("data-selected-all")) {
      //Remove atribute to allow select all again on focus
      $(this).removeAttr("data-selected-all");
    }
  });

  $("input.listeo-export-ical-input").click(function () {
    if (!$(this).attr("data-selected-all")) {
      try {
        $(this).selectionStart = 0;
        $(this).selectionEnd = $(this).value.length + 1;
        //add atribute allowing normal selecting post focus
        $(this).attr("data-selected-all", true);
      } catch (err) {
        $(this).select();
        //add atribute allowing normal selecting post focus
        $(this).attr("data-selected-all", true);
      }
    }
  });

  function updateDashboardChart(params) {
    console.log($("#chart-date-range").data("daterangepicker"));
    var startDataSql = moment(
      $("#chart-date-range").data("daterangepicker").startDate,
      ["MM/DD/YYYY"]
    ).format("YYYY-MM-DD");

    var endDataSql = moment(
      $("#chart-date-range").data("daterangepicker").endDate,
      ["MM/DD/YYYY"]
    ).format("YYYY-MM-DD");

    var listing_id = $("#listing_id").val();
    var stat_type = $("#stat_type").val();

    var ajax_data = {
      action: "listeo_chart_refresh",
      date_start: startDataSql,
      date_end: endDataSql,
      listing: listing_id,
      stat_type: stat_type,

      //'nonce': nonce
    };

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: ajax_data,

      success: function (data) {
        // loader clas

        let labels = new Array();
        Object.keys(data.labels).forEach((key) => {
          labels.push(data.labels[key]);
        });
        chart.data.labels = labels;

        let postdata = new Array();
        Object.keys(data.data).forEach((key) => {
          postdata.push(data.data[key]);
        });
        console.log(postdata);
        chart.data.datasets = postdata;

        chart.update();
      },
    });
  }

  if ($("#chart-date-range").length) {
    var start = moment().subtract(6, "days");
    var end = moment();

    function cb(start, end) {
      $("#chart-date-range span").html(
        start.format("MMMM D, YYYY") + " - " + end.format("MMMM D, YYYY")
      );
    }

    var ranges = new Object();
    ranges[listeo_core.today] = [moment().subtract(1, "days"), moment()];
    ranges[listeo_core.yesterday] = [
      moment().subtract(1, "days"),
      moment().subtract(1, "days"),
    ];
    ranges[listeo_core.last_7_days] = [moment().subtract(6, "days"), moment()];
    ranges[listeo_core.last_30_days] = [
      moment().subtract(29, "days"),
      moment(),
    ];
    ranges[listeo_core.this_month] = [
      moment().startOf("month"),
      moment().endOf("month"),
    ];
    ranges[listeo_core.last_month] = [
      moment().subtract(1, "month").startOf("month"),
      moment().subtract(1, "month").endOf("month"),
    ];

    cb(start, end);

    // $("#chart-date-range-enabler").on("click", function (e) {
    //   e.preventDefault();
    //   $(this).hide();
    //   cb(start, end);
    $("#chart-date-range")
      .show()
      .daterangepicker(
        {
          opens: "left",
          autoUpdateInput: false,
          alwaysShowCalendars: true,
          startDate: start,
          endDate: end,
          ranges: ranges,
          locale: {
            format: wordpress_date_format.date,
            firstDay: parseInt(wordpress_date_format.day),
            applyLabel: listeo_core.applyLabel,
            cancelLabel: listeo_core.cancelLabel,
            fromLabel: listeo_core.fromLabel,
            toLabel: listeo_core.toLabel,
            customRangeLabel: listeo_core.customRangeLabel,
            daysOfWeek: [
              listeo_core.day_short_su,
              listeo_core.day_short_mo,
              listeo_core.day_short_tu,
              listeo_core.day_short_we,
              listeo_core.day_short_th,
              listeo_core.day_short_fr,
              listeo_core.day_short_sa,
            ],
            monthNames: [
              listeo_core.january,
              listeo_core.february,
              listeo_core.march,
              listeo_core.april,
              listeo_core.may,
              listeo_core.june,
              listeo_core.july,
              listeo_core.august,
              listeo_core.september,
              listeo_core.october,
              listeo_core.november,
              listeo_core.december,
            ],
          },
        },
        cb
      );

    cb(start, end);
    // });

    // Calendar animation and visual settings
    $("#chart-date-range").on("show.daterangepicker", function (ev, picker) {
      $(".daterangepicker").addClass(
        "calendar-visible calendar-animated bordered-style"
      );
      $(".daterangepicker").removeClass("calendar-hidden");
    });
    $("#chart-date-range").on("hide.daterangepicker", function (ev, picker) {
      $(".daterangepicker").removeClass("calendar-visible");
      $(".daterangepicker").addClass("calendar-hidden");
    });
  }

  // hooks for get bookings into view
  $("#chart-date-range").on("apply.daterangepicker", function (e) {
    updateDashboardChart();
  });

  $(".dashboard-chart-full #listing_id").on("change", function (e) {
    updateDashboardChart();
  });
  $(".dashboard-chart-full #stat_type").on("change", function (e) {
    updateDashboardChart();
  });

  $(".listeo-create-stripe-express-link-account").on("click", function (e) {
    e.preventDefault();
    var $this = $(this);
    $(this).addClass("loading");
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "create_express_stripe_account",
        nonce: listeo_core.stripe_express_nonce,
      },
      success: function (data) {
        if (data.success) {
          get_listeo_stripe_account_link();
        } else {
          $this.removeClass("loading");
          $this.after($("<p>").text(data.data));
        }
      },
    });
  });

  function get_listeo_stripe_account_link() {
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: {
        action: "get_express_stripe_account_link",
        nonce: listeo_core.stripe_express_nonce,
      },
      success: function (data) {
        if (data.success) {
          $(".listeo-create-stripe-express-link-account").hide();
          $(".real-conntect-w-stripe-btn").attr("href", data.data).show();
        } else {
          $(".listeo-create-stripe-express-link-account").removeClass(
            "loading"
          );
          $(".listeo-create-stripe-express-link-account").after(
            $("<p>").text(data.data)
          );
        }
      },
    });
  }

  // claim listing

  $("#claim-listing-form").on("submit", function (e) {
    $("#claim-dialog button").addClass("loading").prop("disabled", true);

    var form = $("#claim-listing-form")[0]; // Get the DOM element of the form
    var formData = new FormData(form);

    // check if required fields are filled
    var required_fields = true;
    $("#claim-listing-form .required").each(function () {
      if (!$(this).val()) {
        required_fields = false;
        $(this).addClass("error");
      }
    });
    // if required fields are not filled
    if (!required_fields) {
      $("#claim-dialog button").removeClass("loading").prop("disabled", false);
      $("#claim-dialog .notification")
        .removeClass("success")
        .addClass("error")
        .show()
        .html(listeo_core.required_fields);
      e.preventDefault();
      return false;
    }

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo_core.ajax_url,
      data: formData,
      processData: false, // Set processData to false to prevent jQuery from automatically converting the data to a string
      contentType: false, // Set contentType to false, so jQuery does not set the Content-Type header
      success: function (data) {
        if (data.success) {
          // if data has a value payment and it's not empty
          if (data.payment_url && data.payment_url !== "") {
            //scroll to top
            $("html, body").animate({ scrollTop: 0 }, "slow");
            $("#claim-dialog button")
              .removeClass("loading")
              .prop("disabled", false);
            $("#claim-dialog form").hide();
            $(".claim-listing-pay-button")
              .attr("href", data.payment_url)
              .show();
            $(".claim-confirmation-box").show();

            // window.location.href = data.payment;
          } else {
            $("#claim-listing-form .required").each(function () {
              $(this).removeClass("error");
            });
            $("#claim-dialog button").removeClass("loading");
            $("#claim-dialog .notification")
              .removeClass("error")
              .addClass("success")
              .show()
              .html(data.message);
            window.setTimeout(closepopup, 3000);
            $(".claim-listing-button span").text(data.button_text);
            $(".claim-listing-button").removeClass("popup-with-zoom-anim");
            if (data.reload && listeo_core.autologin) {
              setTimeout(function () {
                window.location.reload(); // you can pass true to reload function to ignore the client cache and reload from the server
              }, 2000);
            }
          }
        } else {
          $("#claim-dialog .notification")
            .removeClass("success")
            .addClass("error")
            .show()
            .html(data.message);
          $("#claim-dialog button")
            .removeClass("loading")
            .prop("disabled", false);
        }
      },
    });
    e.preventDefault();
  });

  // review validation
  $(".add-sub-rating").each(function () {
    const ratingName = $(this).find('input[type="radio"]').first().attr("name");
    $(this).append(`<input type="text" 
            class="rating-validator" 
            style="position: absolute; opacity: 0; pointer-events: none;" 
            name="validate_${ratingName}" 
            required 
            data-rating-group="${ratingName}">`);
  });

  // Update validator input when stars are clicked
  $('.leave-rating input[type="radio"]').on("change", function () {
    const ratingGroup = $(this).attr("name");
    const validator = $(`input[data-rating-group="${ratingGroup}"]`);
    validator.val($(this).val());

    // Remove error styling if present
    $(this).closest(".add-sub-rating").removeClass("rating-validation-error");
    $(this).closest(".add-sub-rating").find(".rating-error-message").hide();
  });

  // Handle form submission
  $("#commentform").on("submit", function (e) {
    let isValid = true;
    let firstError = null;

    $(".add-sub-rating").each(function () {
      const ratingGroup = $(this);
      const hasRating =
        ratingGroup.find('input[type="radio"]:checked').length > 0;

      if (!hasRating) {
        isValid = false;
        ratingGroup.addClass("rating-validation-error");
        ratingGroup.find(".rating-error-message").show();

        if (!firstError) {
          firstError = ratingGroup;
        }
      }
    });

    if (!isValid) {
      e.preventDefault();
      if (firstError) {
        $("html, body").animate(
          {
            scrollTop: firstError.offset().top - 100,
          },
          500
        );
      }
    }
  });


  // ------------------ End Document ------------------ //
});

})(this.jQuery);
/**/