/* ----------------- Start Document ----------------- */
(function($){
"use strict";

$(document).ready(function(){
  var inputClicked = false;
  /*----------------------------------------------------*/
  /*  Booking widget and confirmation form
	/*----------------------------------------------------*/
  $("a.booking-confirmation-btn").on("click", function (e) {
    e.preventDefault();
    var bookingForm = $("#booking-confirmation");
    if (bookingForm[0].checkValidity()) {
      var button = $(this);
      button.addClass("loading");
      $("#booking-confirmation").submit();
    } else {
      bookingForm[0].reportValidity();
    }
  });

  // reload states based on country
  function loadStatesForCountry(country) {
    if (!country) {
      return;
    }

    $.ajax({
      url: listeo.ajaxurl, // WordPress AJAX
      type: "POST",
      data: {
        action: "listeo_get_booking_states",
        country: country,
        nonce: listeoBookings.states_nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          var states = response.data;
          var stateField = $('[name="billing_state"]');

          if (Object.keys(states).length > 0) {
            // Country has states - create/replace with select dropdown
            var selectHtml = '<select name="billing_state" id="billing_state" class="address-field" required>';
            selectHtml += '<option value="">' + listeoBookings.select_state_text + '</option>';

            $.each(states, function (code, name) {
              selectHtml += '<option value="' + code + '">' + name + '</option>';
            });
            selectHtml += '</select>';

            stateField.replaceWith(selectHtml);
          } else {
            // No states available - create/replace with text input
            var inputHtml = '<input type="text" name="billing_state" id="billing_state" class="input-text" value="" placeholder="' + listeoBookings.state_placeholder + '">';
            stateField.replaceWith(inputHtml);
          }
        }
      },
    });
  }

  // Handle country change event (when dropdown is visible)
  $("#billing_country").change(function () {
    loadStatesForCountry($(this).val());
  });

  // Handle page load - check if country is pre-selected or hidden
  $(document).ready(function () {
    var countryField = $("#billing_country");
    if (countryField.length > 0) {
      var country = countryField.val();

      // If country has a value (either hidden field or pre-selected dropdown)
      // and state field is currently a text input, load states
      if (country && $('[name="billing_state"]').is('input[type="text"]')) {
        loadStatesForCountry(country);
      }
    }
  });
  $(document).bind("change", function (e) {
    if (e.target.checkValidity && !e.target.checkValidity()) {
      $(e.target).parent().addClass("invalid");
    } else {
      $(e.target).parent().removeClass("invalid");
    }
  });

  $("#listeo-coupon-link").on("click", function (e) {
    e.preventDefault();
    $(".coupon-form").toggle();
  });

  // ------------------------------------------------------------------
  // Itemized booking-price breakdown
  //
  // Renders into `#booking-price-breakdown` (inside `.booking-estimated-cost`)
  // from the `breakdown` key on the AJAX response. Backward-compatible:
  // when the response is from an older Core that didn't include the
  // key, the static `#booking-mandatory-fees` ul stays in place and
  // this is a no-op.
  //
  // The breakdown's `coupon` is rendered as its own line, separate
  // from the existing `.booking-estimated-discount-cost` block (which
  // shows the post-discount total as a strong "Final Cost" line). Both
  // can coexist — the breakdown shows the math, the discount block
  // shows the bottom line.
  // ------------------------------------------------------------------
  function renderBookingBreakdown(breakdown) {
    var $list = $("#booking-price-breakdown");
    if (!$list.length) return;
    if (!breakdown || !breakdown.lines || !breakdown.lines.length) {
      $list.hide();
      return;
    }

    // Suppress the static fees ul once we have a full breakdown — the
    // breakdown already includes mandatory_fee lines, so leaving the
    // static ul visible would double them.
    $("#booking-mandatory-fees").hide();

    var html = "";
    breakdown.lines.forEach(function (line) {
      var keyClass = line.key ? " booking-breakdown-line--" + line.key : "";
      var discountClass = line.is_discount ? " booking-breakdown-line--discount" : "";
      // Two-line layout: bold title on top, dim sublabel below.
      // `amount_formatted` is trusted server output (already includes
      // currency formatting). Title + sublabel are user-controllable
      // (fee titles, service names) → escape those.
      var sublabel = line.sublabel
        ? '<span class="booking-breakdown-line-sublabel">' + escapeHtml(line.sublabel) + "</span>"
        : "";
      html +=
        '<li class="booking-breakdown-line' + keyClass + discountClass + '">' +
        '<div class="booking-breakdown-line-text">' +
            '<span class="booking-breakdown-line-label">' + escapeHtml(line.label || "") + "</span>" +
            sublabel +
        "</div>" +
        '<span class="booking-breakdown-line-amount">' + (line.amount_formatted || "") + "</span>" +
        "</li>";
    });

    if (breakdown.coupon && breakdown.coupon.amount_formatted) {
      html +=
        '<li class="booking-breakdown-line booking-breakdown-line--coupon booking-breakdown-line--discount">' +
        '<div class="booking-breakdown-line-text">' +
            '<span class="booking-breakdown-line-label">' + escapeHtml(breakdown.coupon.label || "Coupon") + "</span>" +
        "</div>" +
        '<span class="booking-breakdown-line-amount">' + (breakdown.coupon.amount_formatted || "") + "</span>" +
        "</li>";
    }

    $list.html(html).show();
  }

  // Local escape — keeps the helper self-contained without depending
  // on jQuery's text() (which would force us to build a DOM tree per
  // line item).
  function escapeHtml(str) {
    if (str === null || typeof str === "undefined") return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // Optional mandatory fees — customer-toggleable lines. The checkbox
  // state is submitted with the form (name="optional_fees[]"), so the
  // server-side fees engine honors the opt-out automatically via the
  // request-context bridge. Here we just keep the visible total in
  // sync without a round-trip.
  //
  // Two render contexts:
  //   1. Step-1 booking widget on listing-single (event/tickets type) —
  //      `.listeo-widget-total-cost[data-price]`.
  //   2. Step-2 confirmation summary — `li.total-costs[data-price]`.
  // We bind once and resolve the right target lazily on each toggle so
  // both flows work without depending on which DOM is present.
  (function () {
    if (!$("#booking-mandatory-fees").length) return;

    function formatPrice(value) {
      var decimals =
        (typeof listeoBookings !== "undefined" && listeoBookings.number_decimals) || 2;
      return Number(value).toFixed(decimals);
    }

    function findTotalNode() {
      // Prefer the step-2 confirmation total when it's present;
      // otherwise fall back to the step-1 widget total. Both expose
      // their numeric value via data-price.
      var $step2 = $("li.total-costs[data-price]");
      if ($step2.length) {
        return { $el: $step2, $span: $step2.find("span").first() };
      }
      var $step1 = $(".listeo-widget-total-cost[data-price]");
      if ($step1.length) {
        return { $el: $step1, $span: $step1 };
      }
      return null;
    }

    function getOptionalAmounts() {
      var unchecked = 0;
      $("#booking-mandatory-fees")
        .find(".listeo-fee-row-optional input.listeo-optional-fee-checkbox")
        .each(function () {
          if (!$(this).is(":checked")) {
            var $row = $(this).closest(".listeo-fee-row");
            var amt = parseFloat($row.attr("data-fee-amount"));
            if (!isNaN(amt)) unchecked += amt;
          }
        });
      return unchecked;
    }

    function updateTotal() {
      var target = findTotalNode();
      if (!target) return;
      var basePrice = parseFloat(target.$el.attr("data-price"));
      if (isNaN(basePrice)) return;
      var newTotal = basePrice - getOptionalAmounts();
      target.$span.text(function (_, current) {
        // Preserve the leading currency symbol (e.g. "$ 123.00").
        var prefix = current.match(/^\s*\D+/);
        var suffix = current.match(/\D+\s*$/);
        var left = prefix ? prefix[0] : "";
        var right = suffix ? suffix[0] : "";
        return left + formatPrice(newTotal) + (left ? "" : right);
      });
    }

    $(document).on("change", ".listeo-optional-fee-checkbox", function () {
      var $row = $(this).closest(".listeo-fee-row");
      $row.toggleClass("listeo-fee-row-disabled", !this.checked);
      updateTotal();
    });
  })();

  function validate_coupon(listing_id, price) {
    var current_codes = $("#coupon_code").val();
    if (current_codes) {
      var codes = current_codes.split(",");
      $.each(codes, function (index, item) {
        console.log(item);
        var ajax_data = {
          listing_id: listing_id,
          coupon: item,
          coupons: codes,
          price: price,
          action: "listeo_validate_coupon",
          nonce: listeo.coupon_nonce,
        };
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: ajax_data,

          success: function (data) {
            if (data.success) {
            } else {
              $("#coupon-widget-wrapper-output div.error")
                .html(data.message)
                .show();
              $(
                '#coupon-widget-wrapper-applied-coupons span[data-coupon="' +
                  item +
                  '"] i'
              ).trigger("click");
              $("#apply_new_coupon").val("");
              $("#coupon-widget-wrapper-output .error").delay(3500).hide(500);
            }
            $("a.listeo-booking-widget-apply_new_coupon").removeClass("active");
          },
        });
      });
    }
  }

  // Apply new coupon
  $("a.listeo-booking-widget-apply_new_coupon").on("click", function (e) {
    e.preventDefault();
    $(this).addClass("active");
    $("#coupon-widget-wrapper-output div").hide();

   

    //check if it was already addd
    var price = $(".booking-estimated-cost").data("price");
    if (typeof price === "undefined" || price === null) {
      price = $(".total-costs").data("price");
    }

    var current_codes = $("#coupon_code").val();
    var result = current_codes.split(",");
    var arraycontainscoupon = result.indexOf($("#apply_new_coupon").val()) > -1;
    var ajax_data = {
      listing_id: $("#listing_id").val(),
      coupon: $("#apply_new_coupon").val(),
      coupons: current_codes,
      price: price,
      action: "listeo_validate_coupon",
      nonce: listeo.coupon_nonce,
    };
    $("#coupon-widget-wrapper-output div").hide();
    if (arraycontainscoupon) {
      $(this).removeClass("active");
      $("input#apply_new_coupon").removeClass("bounce").addClass("bounce");
      return;
    }
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: ajax_data,

      success: function (data) {
        if (data.success) {
          if (current_codes.length > 0) {
            $("#coupon_code").val(current_codes + "," + data.coupon);
          } else {
            $("#coupon_code").val(data.coupon);
          }
          $("#apply_new_coupon").val("");
          $("#coupon-widget-wrapper-applied-coupons").append(
            "<span data-coupon=" +
              data.coupon +
              ">" +
              data.coupon +
              "<i class='fa fa-times'></i></span>"
          );
          $("#coupon-widget-wrapper-output .success").show();
          if ($("#booking-confirmation-summary").length > 0) {
            calculate_booking_form_price();
          } else {
            if ($("#form-booking").hasClass("form-booking-tickets")) {
              calculate_price();
            } else {
              check_booking();
            }
          }
          $("#coupon-widget-wrapper-output .success").delay(3500).hide(500);
        } else {
          $("input#apply_new_coupon").removeClass("bounce").addClass("bounce");
          $("#coupon-widget-wrapper-output div.error")
            .html(data.message)
            .show();

          $("#apply_new_coupon").val("");
          $("#coupon-widget-wrapper-output .error").delay(3500).hide(500);
        }
        $("a.listeo-booking-widget-apply_new_coupon").removeClass("active");
      },
    });
  });

  // Remove coupon from widget and calculate price again
  $("#coupon-widget-wrapper-applied-coupons").on(
    "click",
    "span i",
    function (e) {
      var coupon = $(this).parent().data("coupon");

      var coupons = $("#coupon_code").val();
      var coupons_array = coupons.split(",");
      coupons_array = coupons_array.filter(function (item) {
        return item !== coupon;
      });

      $("#coupon_code").val(coupons_array.join(","));
      $(this).parent().remove();
      if ($("#booking-confirmation-summary").length > 0) {
        calculate_booking_form_price();
      } else {
        check_booking();
        calculate_price();
      }
    }
  );

  // Shared event date resolver: tries ISO attr, displayed text, hidden input value
  // Returns YYYY-MM-DD string or null
  var _eventDateFormats = ["DD.MM.YYYY HH:mm", "DD/MM/YYYY HH:mm", "MM/DD/YYYY HH:mm", "YYYY-MM-DD HH:mm:ss", "DD.MM.YYYY", "DD/MM/YYYY", "MM/DD/YYYY", "YYYY-MM-DD", "DD-MM-YYYY"];
  function listeo_resolve_event_date() {
    var $span = $(".booking-event-date span");
    var result;

    // 1. ISO data attribute (most reliable, from PHP timestamp)
    if ($span.length && $span.attr("data-date-iso")) {
      result = $span.attr("data-date-iso");
      if (result && /^\d{4}-\d{2}-\d{2}/.test(result)) return result.substring(0, 10);
    }

    // 2. Parse displayed text
    if ($span.length && $span.text().trim()) {
      var text = $span.text().trim();
      var wpFormat = (typeof wordpress_date_format !== "undefined" && wordpress_date_format.date) ? wordpress_date_format.date : null;
      if (wpFormat) {
        var m = moment(text, wpFormat);
        if (m.isValid()) return m.format("YYYY-MM-DD");
      }
      for (var i = 0; i < _eventDateFormats.length; i++) {
        var m2 = moment(text, _eventDateFormats[i], true);
        if (m2.isValid()) return m2.format("YYYY-MM-DD");
      }
    }

    // 3. Parse hidden date-picker input value
    var pickerVal = $("#date-picker").val();
    if (pickerVal) {
      for (var j = 0; j < _eventDateFormats.length; j++) {
        var m3 = moment(pickerVal, _eventDateFormats[j], true);
        if (m3.isValid()) return m3.format("YYYY-MM-DD");
      }
    }

    return null;
  }

  window._listeo_booking_submitted = false;

  //Book now button
  $(".listing-widget").on("click", "a.book-now", function (e) {
    var button = $(this);

    if ($("#date-picker").val()) {
      inputClicked = true;

      if ($(".time-picker").length && !$(".time-picker").val()) {
        inputClicked = false;
      }
      if ($("#slot").length && !$("#slot").val()) {
        inputClicked = false;
      }
      if (inputClicked) {
        check_booking();
      }
    }

    if (inputClicked == false) {
      $(
        ".time-picker,.time-slots-dropdown,.date-picker-listing-date_range"
      ).addClass("bounce");
    } else {
      button.addClass("loading");
    }
    e.preventDefault();

    var freeplaces = button.data("freeplaces");

    // var checked = $(".bookable-services input[type=checkbox]:checked").length;

    // if (!checked) {
    // 	alert("You must select at least one extra service.");
    // 	return false;
    // }

    setTimeout(function () {
      button.removeClass("loading");
      $(
        ".time-picker,.time-slots-dropdown,.date-picker-listing-date_range"
      ).removeClass("bounce");
    }, 3000);

    try {
      // Calculate total guests
      var adults = parseInt($(".adults").val()) || 0;
      var children = parseInt($("input[name=childrenQtyInput]").val()) || 0;
      var infants = parseInt($("input[name=infantsQtyInput]").val()) || 0;
      var animals = parseInt($("input[name=animalsQtyInput]").val()) || 0;
      
      // Get max guests from the form
      var maxGuests = parseInt($(".adults").data("max"));
      
      // Calculate total counting guests (adults + children count towards max, infants and animals don't)
      var totalCountingGuests = adults + children;
      
      if (totalCountingGuests > maxGuests) {
        $("#booking-widget-message").show().html(listeo_core.exceed_guests_limit);
        return false;
      }

      if (freeplaces > 0) {
        // preparing data for ajax to send to booking form

        if ($("#date-picker").data("rental-timepicker")) {
          // var startDataSQL need to have hour
          var timeFormat = is24Hour ? "HH:mm" : "hh:mm A";
          var startDataSql = moment(
            $("#date-picker").data("daterangepicker").startDate,
            ["MM/DD/YYYY " + timeFormat]
          ).format("YYYY-MM-DD HH:mm:ss");
          var endDataSql = moment(
            $("#date-picker").data("daterangepicker").endDate,
            ["MM/DD/YYYY " + timeFormat]
          ).format("YYYY-MM-DD HH:mm:ss");
        } else {
          var startDataSql = moment(
            $("#date-picker").data("daterangepicker").startDate,
            ["MM/DD/YYYY"]
          ).format("YYYY-MM-DD");
          var endDataSql = moment(
            $("#date-picker").data("daterangepicker").endDate,
            ["MM/DD/YYYY"]
          ).format("YYYY-MM-DD");
        }

        var ajax_data = {
          listing_type: $("#listing_type").val(),
          listing_id: $("#listing_id").val(),
          //'nonce': nonce
        };
        var invalid = false;
        if (startDataSql) ajax_data.date_start = startDataSql;
        if (endDataSql) ajax_data.date_end = endDataSql;
        if ($("input#slot").val()) ajax_data.slot = $("input#slot").val();
        if ($(".time-picker#_hour").val())
          ajax_data._hour = $(".time-picker#_hour").val();
        if ($(".time-picker#_hour_end").val())
          ajax_data._hour_end = $(".time-picker#_hour_end").val();
        if ($(".adults").val()) ajax_data.adults = $(".adults").val();
        if ($("input[name=childrenQtyInput]").val()) ajax_data.children = $("input[name=childrenQtyInput]").val();
        if ($("input[name=infantsQtyInput]").val()) ajax_data.infants = $("input[name=infantsQtyInput]").val();
        if ($("input[name=animalsQtyInput]").val()) ajax_data.animals = $("input[name=animalsQtyInput]").val();
        if ($("#tickets").val()) ajax_data.tickets = $("#tickets").val();
        if ($("#coupon_code").val()) ajax_data.coupon = $("#coupon_code").val();

        // Check if listing type supports time slots
        var listing_type = $("#listing_type").val();
        var type_config = listeo_core.listing_types_config && listeo_core.listing_types_config[listing_type];
        var supports_time_slots = type_config ? type_config.supports_time_slots : (listing_type == "single_day"); // fallback
        
        if (supports_time_slots) {
          if (
            $("input#slot").val() == undefined ||
            $("input#slot").val() == ""
          ) {
            inputClicked = false;
            invalid = true;
          }
          if ($(".time-picker").length) {
            invalid = false;
          }
        }

        if (invalid == false) {
          var services = [];
          // $.each($("input[name='_service[]']:checked"), function(){
          //           		services.push($(this).val());
          //       		});
          $.each($("input.bookable-service-checkbox:checked"), function () {
            var quantity = $(this)
              .parent()
              .find("input.bookable-service-quantity")
              .val();
            services.push({ service: $(this).val(), value: quantity });
          });
          ajax_data.services = services;
          $("input#booking").val(JSON.stringify(ajax_data));
       
          $("#form-booking").submit();
        }
      }
    } catch (e) {
      console.log(e);
    }

    // Check if listing type is event-based (no time slots support)
    var listing_type = $("#listing_type").val();
    var type_config = listeo_core.listing_types_config && listeo_core.listing_types_config[listing_type];
    var is_event_type = type_config ? !type_config.supports_time_slots : (listing_type == "event" || listing_type == "tickets");

    if (is_event_type && !window._listeo_booking_submitted) {
      var eventDateParsed = listeo_resolve_event_date();

      if (!eventDateParsed) {
        alert(listeo_core.dateParseError || "Could not determine event date. Please contact support.");
        return;
      }

      var ajax_data = {
        listing_type: listing_type,
        listing_id: $("input#listing_id").val(),
        date_start: eventDateParsed,
        date_end: eventDateParsed,
      };
      if ($("#coupon_code").val()) ajax_data.coupon = $("#coupon_code").val();
      var services = [];
      $.each($("input.bookable-service-checkbox:checked"), function () {
        var quantity = $(this)
          .parent()
          .find("input.bookable-service-quantity")
          .val();
        services.push({ service: $(this).val(), value: quantity });
      });
      ajax_data.services = services;

      if ($("#tickets").val()) ajax_data.tickets = $("#tickets").val();
      $("input#booking").val(JSON.stringify(ajax_data));

      $("#form-booking").submit();
    }
  });

  if (Boolean(listeo_core.clockformat)) {
    var dateformat_even = wordpress_date_format.date + " HH:mm";
  } else {
    var dateformat_even = wordpress_date_format.date + " hh:mm A";
  }

  function updateCounter() {
    var len = $(".bookable-services input[type='checkbox']:checked").length;
    if (len > 0) {
      $(".booking-services span.services-counter").text("" + len + "");
      $(".booking-services span.services-counter").addClass("counter-visible");
    } else {
      $(".booking-services span.services-counter").removeClass(
        "counter-visible"
      );
      $(".booking-services span.services-counter").text("0");
    }
  }

  $(".single-service").on("click", function () {
    updateCounter();
    $(".booking-services span.services-counter").addClass("rotate-x");

    setTimeout(function () {
      $(".booking-services span.services-counter").removeClass("rotate-x");
    }, 300);
  });

  // $( ".input-datetime" ).each(function( index ) {
  // 	var $this = $(this);
  // 	var input = $(this).next('input');
  //   	var date =  parseInt(input.val());
  //   	if(date){
  // 	  	var a = new Date(date);
  // 		var timestamp = moment(a);
  // 		$this.val(timestamp.format(dateformat_even));
  //   	}

  // });

  //$('#_event_date').val(timestamp.format(dateformat_even));

  // there are two fields with id _event_date and _event_date_end, make sure the event_date_end is not before event_date
  // $('#_event_date_end').on('change', function() {
  // 	alert('test');
  // 	var start_date = $('#_event_date').val();
  // 	var end_date = $('#_event_date_end').val();
  // 	if(end_date < start_date){
  // 		$('#_event_date_end').val(start_date);
  // 	}
  // });
window.initializeDateRangePicker = function ($context = $(document)) {
  $context.find(".input-datetime").each(function () {
    // Prevent double init
    if (!$(this).data("daterangepicker")) {
      $(this).daterangepicker({
        opens: "left",
        singleDatePicker: true,
        timePicker: true,
        autoUpdateInput: false,
        
        timePicker24Hour: Boolean(listeo_core.clockformat),
        minDate: moment().subtract(0, "days"),
        locale: {
          format: dateformat_even,
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
      });
    }
  });
};
initializeDateRangePicker();

  $(".input-datetime").on("apply.daterangepicker", function (ev, picker) {
    $(this).val(picker.startDate.format(dateformat_even));
    var end_date = $("#_event_date_end").length;
    if (end_date) {
      $(".input-datetime#_event_date_end")
        .data("daterangepicker")
        .setMinDate(picker.startDate.format(dateformat_even));
    }
  });

  $(".input-datetime").on("cancel.daterangepicker", function (ev, picker) {
    $(this).val("");
  });

  $(".input-date").daterangepicker({
    opens: "left",
    // checking attribute listing type and set type of calendar
    singleDatePicker: true,
    timePicker: false,
    autoUpdateInput: false,

    minDate: moment().subtract(0, "days"),

    locale: {
      format: dateformat_even,
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
  });

  $(".input-date").on("apply.daterangepicker", function (ev, picker) {
    $(this).val(picker.startDate.format("YYYY-MM-DD"));
  });

  $(".input-date").on("cancel.daterangepicker", function (ev, picker) {
    $(this).val("");
  });
  // $('.input-datetime').on( 'apply.daterangepicker', function(){

  // 	var picked_date = $(this).val();
  // 	var input = $(this).next('input');
  // 	input.val(moment(picked_date,dateformat_even).format('YYYY-MM-DD HH:MM:SS'));
  // } );

  function wpkGetThisDateSlots(date) {
    var slots = {
      isFirstSlotTaken: false,
      isSecondSlotTaken: false,
    };

    // Check if listing type supports time slots
    var listing_type = $("#listing_type").val();
    var type_config = listeo_core.listing_types_config && listeo_core.listing_types_config[listing_type];
    var supports_time_slots = type_config ? type_config.supports_time_slots : (listing_type != "event"); // fallback
    
    if (!supports_time_slots) return slots;

    var dateString = date.format("YYYY-MM-DD");

    // Check disabled dates first
    if (typeof disabledDates !== "undefined") {
      if (wpkIsDateInArray(date, disabledDates)) {
        slots.isFirstSlotTaken = slots.isSecondSlotTaken = true;
        console.log("Date " + dateString + " is in disabled dates");
        return slots;
      }
    }

    // Check booking dates
    if (typeof wpkStartDates != "undefined" && typeof wpkEndDates != "undefined") {
      slots.isSecondSlotTaken = wpkIsDateInArray(date, wpkStartDates);
      slots.isFirstSlotTaken = wpkIsDateInArray(date, wpkEndDates);
      
      // Debug logging
   
    }

    return slots;
  }

  function wpkIsDateInArray(date, array) {
    var dateString = date.format("YYYY-MM-DD");
    var isInArray = false;
    
    // Check if array is object (with numeric keys) or regular array
    if (Array.isArray(array)) {
      isInArray = array.indexOf(dateString) !== -1;
    } else if (typeof array === 'object') {
      // Convert object values to array and check
      var arrayValues = Object.values(array);
      isInArray = arrayValues.indexOf(dateString) !== -1;
    }
    
    
    return isInArray;
  }

  let startDate = Cookies.get("listeo_rental_startdate");
  let endDate = Cookies.get("listeo_rental_enddate");
  let minSpan = $("#date-picker").data("minspan");
  let maxSpan = $("#date-picker").data("maxspan");
  let availableHours = [];
  var is24Hour = Boolean(listeo_core.clockformat);
  var timePickerIncrement = $("#date-picker").data("time-increment");
  //format: "M/DD hh:mm A";

  // if startDate exist and is not empty, set autoUpdateInput to true
  let autoUpdateInput = true;
  if(startDate === undefined) {
    autoUpdateInput = false;
  }

  // Update the daterangepicker options to properly handle blocked dates
  let daterangepicker_options = {
    opens: "left",
    autoUpdateInput: autoUpdateInput,
    singleDatePicker: function() {
      var listing_type = $("#date-picker").data("listing_type");
      var type_config = listeo_core.listing_types_config && listeo_core.listing_types_config[listing_type];
      // Use single date picker for non-rental types (events, services)
      return type_config
        ? type_config.supports_time_slots
        : listing_type != "date_range";
    }(),
    timePicker: $("#date-picker").data("rental-timepicker") == true ? true : false,
    minDate: moment().subtract(0, "days"),
    timePicker24Hour: is24Hour,
    timePickerIncrement: timePickerIncrement,
    minSpan: { days: minSpan },
    startDate: startDate ? startDate : moment(),
    endDate: endDate ? endDate : moment().add(minSpan, "days"),
    locale: {
      format: $("#date-picker").data("rental-timepicker") == true
        ? wordpress_date_format.date + " " + (is24Hour ? "HH:mm" : "hh:mm A")
        : wordpress_date_format.date,
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

    isCustomDate: function (date) {
      if ($("#date-picker").data("rental-timepicker") == true) {
        var dateString = date.format("YYYY-MM-DD");

        if (partialBookedDates && partialBookedDates[dateString]) {
          var isFullyBooked = isDateFullyBooked(partialBookedDates[dateString]);
          if (isFullyBooked) {
            return "fully-booked";
          }
          return "partially-booked";
        }

        return "available";
      } else {
        var slots = wpkGetThisDateSlots(date);
    
        if (!slots.isFirstSlotTaken && !slots.isSecondSlotTaken) return [];

        if (slots.isFirstSlotTaken && !slots.isSecondSlotTaken) {
          return ["first-slot-taken"];
        }

        if (slots.isSecondSlotTaken && !slots.isFirstSlotTaken) {
          return ["second-slot-taken"];
        }

        // If both slots are taken, return fully booked class
        if (slots.isFirstSlotTaken && slots.isSecondSlotTaken) {
          return ["fully-booked"];
        }
      }
    },

    isInvalidDate: function (date) {
      // For event-based types, don't block any dates by default. Plugins
      // (e.g. Listeo Booking Plus recurring events) can register
      // `window.listeoEventIsInvalidDate(date)` to constrain selection to
      // a specific set of allowed dates.
      var listing_type = $("#listing_type").val();
      var type_config = listeo_core.listing_types_config && listeo_core.listing_types_config[listing_type];
      var is_event_type = type_config ? !type_config.supports_time_slots : (listing_type == "event"); // fallback

      if (is_event_type) {
        if (typeof window.listeoEventIsInvalidDate === "function") {
          return !!window.listeoEventIsInvalidDate(date);
        }
        return false;
      }
      
      // For services with disabled dates
      if ($("#listing_type").val() == "single_day" && typeof disabledDates !== "undefined") {
        if (jQuery.inArray(date.format("YYYY-MM-DD"), disabledDates) !== -1) {
          return true;
        }
      }

      // Handle time slots for services
      if ($(".time-slots-dropdown").length) {
        var timeslotdays = $(".time-slots-dropdown").data("slots-days").toString();
        var timeslotdaysArray = timeslotdays.split(",");
        
        if (timeslotdaysArray.includes(moment(date).day().toString())) {
          return false;
        } else {
          return true;
        }
      }

      // For rental listings
      if ($("#listing_type").val() == "date_range") {
        if ($("#date-picker").data("rental-timepicker") == true) {
          var dateString = date.format("YYYY-MM-DD");

          // Check disabled dates
          if (
            typeof disabledDates !== "undefined" &&
            disabledDates.indexOf(dateString) > -1
          ) {
            return true;
          }

          return false;
        } else {
          // Use the wpkGetThisDateSlots function to determine if date should be blocked
          var slots = wpkGetThisDateSlots(date);

          // Block dates where both slots are taken (fully booked)
          return slots.isFirstSlotTaken && slots.isSecondSlotTaken;
        }
      }

      return false;
    },
  };

  $("#date-picker").daterangepicker(daterangepicker_options);

  $("#date-picker").on("show.daterangepicker", function (ev, picker) {
    $(".daterangepicker").addClass("calendar-visible calendar-animated");
    $(".daterangepicker").removeClass("calendar-hidden");
  });
  $("#date-picker").on("hide.daterangepicker", function (ev, picker) {
    $(".daterangepicker").removeClass("calendar-visible");
    $(".daterangepicker").addClass("calendar-hidden");
  });

  // Handle date/time selection
  $(".date-picker-listing-date_range").on(
    "showCalendar.daterangepicker",
    function (ev, picker) {
      // Force update time picker whenever calendar is shown
      if ($("#date-picker").data("rental-timepicker") == true) {
        setTimeout(function () {
          updateTimePicker(picker);
        }, 50);
      }
    }
  );

  // Handle time picker changes
  $(".date-picker-listing-date_range").on(
    "show.daterangepicker",
    function (ev, picker) {
      if ($("#date-picker").data("rental-timepicker") == true) {
        // Initial time picker update
        updateTimePicker(picker);

        // Monitor for any changes to the time pickers
        $(picker.container)
          .find(".calendar-time")
          .on("mousedown", function () {
            //   setTimeout(function () {
            updateTimePicker(picker);
            // }, 50);
          });
      }
    }
  );

  $(".date-picker-listing-date_range").on(
    "show.daterangepicker",
    function (ev, picker) {
      if ($("#date-picker").data("rental-timepicker") == true) {
        setTimeout(function () {
          // Add labels to calendar times if they don't exist
          picker.container.find(".calendar-time").each(function (index) {
            var $timeContainer = $(this);
            if (!$timeContainer.prev().hasClass("calendar-time-label")) {
              var label =
                index === 0
                  ? listeo_core.start_time_label
                  : listeo_core.end_time_label;
              $timeContainer.before(
                '<div class="calendar-time-label">' + label + "</div>"
              );
            }
          });

          // Wrap selects in custom wrapper
        }, 0);
      }
    }
  );

  function updateTimePicker(picker) {
    var startDate = picker.startDate.format("YYYY-MM-DD");
    var endDate = picker.endDate.format("YYYY-MM-DD");

    // Get available times for start and end dates
    var startTimes = getAvailableTimes(
      startDate,
      partialBookedDates[startDate],
      "check-in"
    );
    var endTimes = getAvailableTimes(
      endDate,
      partialBookedDates[endDate],
      "check-out"
    );

    // Get the time selectors for both start and end
    var startContainer = $(picker.container).find(".calendar-time").first();
    var endContainer = $(picker.container).find(".calendar-time").last();

    // Update start time selectors
    updateTimeSelectors(startContainer, startTimes, picker.startDate);

    // Update end time selectors
    updateTimeSelectors(endContainer, endTimes, picker.endDate);
  }

  function updateTimeSelectors(container, availableTimes, currentDate) {
    var hourSelect = container.find(".hourselect");
    var minuteSelect = container.find(".minuteselect");

    // Get unique available hours
    var availableHours = [
      ...new Set(availableTimes.map((time) => parseInt(time.split(":")[0]))),
    ].sort((a, b) => a - b);

    // Clear and populate hour select
    hourSelect.empty();
    availableHours.forEach((hour) => {
      hourSelect.append(
        $("<option>", {
          value: hour,
          text: ("0" + hour).slice(-2),
        })
      );
    });

    // If current hour is not in available hours, select first available
    var currentHour = currentDate.hour();
    if (!availableHours.includes(currentHour)) {
      hourSelect.val(availableHours[0]);
      currentDate.hour(availableHours[0]);
    } else {
      hourSelect.val(currentHour);
    }

    // Update minutes for selected hour
    updateMinutes(hourSelect, minuteSelect, availableTimes, currentDate);

    // Add change handler for hours
    hourSelect.off("change").on("change", function () {
      updateMinutes(hourSelect, minuteSelect, availableTimes, currentDate);
    });
  }

  function updateMinutes(
    hourSelect,
    minuteSelect,
    availableTimes,
    currentDate
  ) {
    var selectedHour = parseInt(hourSelect.val());

    // Get available minutes for selected hour
    var availableMinutes = availableTimes
      .filter((time) => parseInt(time.split(":")[0]) === selectedHour)
      .map((time) => parseInt(time.split(":")[1]))
      .sort((a, b) => a - b);

    // Clear and populate minute select
    minuteSelect.empty();
    availableMinutes.forEach((minute) => {
      minuteSelect.append(
        $("<option>", {
          value: minute,
          text: ("0" + minute).slice(-2),
        })
      );
    });

    // If current minute is not in available minutes, select first available
    var currentMinute = currentDate.minute();
    if (!availableMinutes.includes(currentMinute)) {
      minuteSelect.val(availableMinutes[0]);
      currentDate.minute(availableMinutes[0]);
    } else {
      minuteSelect.val(currentMinute);
    }
  }

  function getAvailableTimes(date, bookedTimes, type) {
    var times = [];
    var startHour = type === "check-in" ? 7 : 10; // Check-in starts at 9 AM
    var endHour = type === "check-out" ? 20 : 17; // Check-out until 5 PM

    // Generate times in 15-minute increments
    for (var hour = startHour; hour <= endHour; hour++) {
      for (var minute = 0; minute < 60; minute += timePickerIncrement) {
        var timeStr = ("0" + hour).slice(-2) + ":" + ("0" + minute).slice(-2);
        if (!isTimeBooked(timeStr, bookedTimes)) {
          times.push(timeStr);
        }
      }
    }

    return times;
  }

  function isTimeBooked(timeStr, bookedTimes) {
    if (!bookedTimes) return false;

    return bookedTimes.some(function (booking) {
      return (
        timeToMinutes(timeStr) >= timeToMinutes(booking.start) &&
        timeToMinutes(timeStr) <= timeToMinutes(booking.end)
      );
    });
  }

  function isDateFullyBooked(bookings) {
    var timeBlocks = mergeTimeBlocks(bookings);
    return timeBlocks.some(function (block) {
      return block.start === "00:00" && block.end === "23:59";
    });
  }

  function mergeTimeBlocks(bookings) {
    if (!bookings || !bookings.length) return [];

    var sorted = bookings.sort(function (a, b) {
      return a.start.localeCompare(b.start);
    });

    var merged = [];
    var current = sorted[0];

    for (var i = 1; i < sorted.length; i++) {
      if (timeToMinutes(current.end) >= timeToMinutes(sorted[i].start)) {
        if (timeToMinutes(sorted[i].end) > timeToMinutes(current.end)) {
          current.end = sorted[i].end;
        }
      } else {
        merged.push(current);
        current = sorted[i];
      }
    }
    merged.push(current);

    return merged;
  }

  function timeToMinutes(timeStr) {
    var parts = timeStr.split(":");
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
  }

  $(".date-picker-listing-date_range").on(
    "apply.daterangepicker",
    function (ev, picker) {
      if ($("#date-picker").data("rental-timepicker") == true) {
        if (picker.startDate && picker.endDate) {
          var timeFormat = is24Hour ? "HH:mm" : "hh:mm A";
          var startFormatted =
            picker.startDate.format(wordpress_date_format.date) +
            " " +
            picker.startDate.format(timeFormat);
          var endFormatted =
            picker.endDate.format(wordpress_date_format.date) +
            " " +
            picker.endDate.format(timeFormat);
          $(this).val(startFormatted + " - " + endFormatted);
        }
      } else {
        $(this).val(
          picker.startDate.format(wordpress_date_format.date) +
            " - " +
            picker.endDate.format(wordpress_date_format.date)
        );
      }
      // if it's other listing type, set date format 
    }
  );

  $(".date-picker-listing-single_day").on(
    "apply.daterangepicker",
    function (ev, picker) {
      // For services we typically want just the selected date
      $(this).val(picker.startDate.format(wordpress_date_format.date));

   
    }
  );

  function updateTimePicker(picker) {
    var startDate = picker.startDate.format("YYYY-MM-DD");
    var endDate = picker.endDate.format("YYYY-MM-DD");

    var startTimes = getAvailableTimes(
      startDate,
      partialBookedDates[startDate],
      "check-in"
    );
    var endTimes = getAvailableTimes(
      endDate,
      partialBookedDates[endDate],
      "check-out"
    );

    var startContainer = $(picker.container).find(".calendar-time").first();
    var endContainer = $(picker.container).find(".calendar-time").last();

    updateTimeSelectors(startContainer, startTimes, picker.startDate);
    updateTimeSelectors(endContainer, endTimes, picker.endDate);
  }

  function updateTimeSelectors(container, availableTimes, currentDate) {
    var is24Hour = Boolean(listeo_core.clockformat);
    var hourSelect = container.find(".hourselect");
    var minuteSelect = container.find(".minuteselect");
    var ampmSelect = container.find(".ampmselect");

    // Get current state
    var currentHour24 = currentDate.hour();
    var currentMinute = currentDate.minute();
    var isPM = currentHour24 >= 12;

    function updateHourOptions() {
      var availableHours24 = [
        ...new Set(availableTimes.map((time) => parseInt(time.split(":")[0]))),
      ].sort((a, b) => a - b);

      // Filter hours based on AM/PM if in 12-hour mode
      if (!is24Hour) {
        var selectedIsPM = ampmSelect.val() === "PM";
        availableHours24 = availableHours24.filter((hour) => {
          return selectedIsPM ? hour >= 12 : hour < 12;
        });
      }

      hourSelect.empty();
      availableHours24.forEach((hour24) => {
        var displayHour = is24Hour ? hour24 : hour24 % 12 || 12;
        hourSelect.append(
          $("<option>", {
            value: hour24, // Store 24-hour value
            text: ("0" + displayHour).slice(-2),
          })
        );
      });

      // Set appropriate hour
      if (availableHours24.length > 0) {
        var currentHourExists = availableHours24.includes(currentHour24);
        var hourToSet = currentHourExists ? currentHour24 : availableHours24[0];
        hourSelect.val(hourToSet);
        currentDate.hour(hourToSet);
        return hourToSet;
      }
      return null;
    }

    // Initial hour update
    var selectedHour24 = updateHourOptions();

    // Update minutes for the selected hour
    function updateMinutes(hour24) {
      if (hour24 === null) return;

      var availableMinutes = availableTimes
        .filter((time) => parseInt(time.split(":")[0]) === hour24)
        .map((time) => parseInt(time.split(":")[1]))
        .sort((a, b) => a - b);

      minuteSelect.empty();
      availableMinutes.forEach((minute) => {
        minuteSelect.append(
          $("<option>", {
            value: minute,
            text: ("0" + minute).slice(-2),
          })
        );
      });

      // Set appropriate minute
      if (availableMinutes.length > 0) {
        if (availableMinutes.includes(currentMinute)) {
          minuteSelect.val(currentMinute);
        } else {
          minuteSelect.val(availableMinutes[0]);
          currentDate.minute(availableMinutes[0]);
        }
      }
    }

    updateMinutes(selectedHour24);

    // Event handlers
    if (!is24Hour) {
      ampmSelect.off("change").on("change", function () {
        var newHour24 = updateHourOptions();
        updateMinutes(newHour24);
      });
    }

    hourSelect.off("change").on("change", function () {
      var selectedHour24 = parseInt($(this).val());
      currentDate.hour(selectedHour24);
      updateMinutes(selectedHour24);
    });

    minuteSelect.off("change").on("change", function () {
      currentDate.minute(parseInt($(this).val()));
    });

    // Set initial AM/PM if in 12-hour mode
    if (!is24Hour) {
      ampmSelect.val(isPM ? "PM" : "AM");
    }
  }

  // Utility functions remain the same...
  function getAvailableTimes(date, bookedTimes, type) {
    var times = [];
    //if availableDays is not set, return empty array
    console.log(availableDays);
    if (
      typeof availableDays === "undefined" ||
      availableDays === null ||
      availableDays === ""
    ) {
      var listingOpeningHours = [];
    } else {
      var listingOpeningHours = JSON.parse(availableDays);
    }

    // Get day of week from the date (0 = Monday, 6 = Sunday)
    // var dayIndex = moment(date).day();
    // dayIndex = dayIndex === 0 ? 6 : dayIndex - 1;
    var dayOfWeek = moment(date).isoWeekday(); // Returns 1-7 (Monday-Sunday)

    // Get opening hours for this day
    var dayIndex = dayOfWeek - 1;
    // Get opening hours for this day
    var dayHours = listingOpeningHours[dayIndex];
    console.log(dayHours);
    // If no hours set for this day or empty values, use default full day availability
    if (
      !dayHours ||
      !dayHours.opening ||
      !dayHours.closing ||
      dayHours.opening[0] === "" ||
      dayHours.closing[0] === ""
    ) {
      // Use default hours based on check-in/check-out type
      // var defaultHours = {
      //     opening: [(type === 'check-in' ? "09:00" : "10:00")],
      //     closing: [(type === 'check-out' ? "20:00" : "17:00")]
      // };

      //dayHours = defaultHours;
      dayHours = {
        opening: ["00:00"],
        closing: ["23:59"],
      };
    }

    // Process each set of opening/closing hours
    for (var i = 0; i < dayHours.opening.length; i++) {
      var openTime = dayHours.opening[i];
      var closeTime = dayHours.closing[i];

      if (!openTime || !closeTime) continue;

      // Convert opening/closing times to hours and minutes
      var openHour = parseInt(openTime.split(":")[0]);
      var closeHour = parseInt(closeTime.split(":")[0]);
      var openMinute = parseInt(openTime.split(":")[1]);
      var closeMinute = parseInt(closeTime.split(":")[1]);

      // // Apply check-in/check-out restrictions
      // if (type === 'check-in') {
      //     // Don't allow check-in less than 1 hour before closing
      //     var closeTimeInMinutes = closeHour * 60 + closeMinute;
      //     closeTimeInMinutes -= 60; // Subtract 1 hour
      //     closeHour = Math.floor(closeTimeInMinutes / 60);
      //     closeMinute = closeTimeInMinutes % 60;
      // } else { // check-out
      //     // Don't allow check-out before 10 AM or after 5 PM
      //     if (openHour < 10) {
      //         openHour = 10;
      //         openMinute = 0;
      //     }
      //     if (closeHour > 17 || (closeHour === 17 && closeMinute > 0)) {
      //         closeHour = 17;
      //         closeMinute = 0;
      //     }
      // }

      // Generate available times within the valid range
      var currentHour = openHour;
      var currentMinute =
        currentHour === openHour
          ? Math.ceil(openMinute / timePickerIncrement) * timePickerIncrement
          : 0;

      var endTimeInMinutes = closeHour * 60 + closeMinute;

      while (currentHour * 60 + currentMinute <= endTimeInMinutes) {
        var timeStr =
          ("0" + currentHour).slice(-2) + ":" + ("0" + currentMinute).slice(-2);

        if (!isTimeBooked(timeStr, bookedTimes)) {
          times.push(timeStr);
        }

        currentMinute += timePickerIncrement;
        if (currentMinute >= 60) {
          currentMinute = 0;
          currentHour++;
        }
      }
    }

    return times.sort();
  }

  function isTimeBooked(timeStr, bookedTimes) {
    if (!bookedTimes) return false;

    return bookedTimes.some(function (booking) {
      return (
        timeToMinutes(timeStr) >= timeToMinutes(booking.start) &&
        timeToMinutes(timeStr) <= timeToMinutes(booking.end)
      );
    });
  }

  function timeToMinutes(timeStr) {
    var parts = timeStr.split(":");
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
  }

  function updateSelectOptions($select, options) {
    // Clear existing options
    $select.empty();

    // Add new options
    options.forEach(function (option) {
      $select.append(
        $("<option>", {
          value: option.value,
          text: option.text,
        })
      );
    });

    // Refresh bootstrap-select
    $select.selectpicker("refresh");
  }

  // $(".date-picker-listing-date_range").on(
  //   "apply.daterangepicker",
  //   function (ev, picker) {
  //     var startDate = picker.startDate.format("YYYY-MM-DD");
  //     var endDate = picker.endDate.format("YYYY-MM-DD");

  //     // Check if selected dates include any partial bookings
  //     var hasPartialBookings = false;
  //     var current = moment(startDate);
  //     var end = moment(endDate);

  //     while (current <= end) {
  //       var dateString = current.format("YYYY-MM-DD");
  //       if (partialBookedDates && partialBookedDates[dateString]) {
  //         hasPartialBookings = true;
  //         break;
  //       }
  //       current.add(1, "days");
  //     }

  //     if (hasPartialBookings) {
  //       // Show time selection dialog
  //       showTimeSelectionDialog(startDate, endDate, partialBookedDates);
  //     }

  //     $(this).val(
  //       picker.startDate.format(listeo_core.date_format) +
  //         " - " +
  //         picker.endDate.format(listeo_core.date_format)
  //     );
  //   }
  // );

  // $("#date-picker").on("apply.daterangepicker", function (ev, picker) {
  //   const listing_id = $("#listing_id").val();
  //   const startDate = picker.startDate.format("YYYY-MM-DD HH:mm:ss");
  //   const endDate = picker.endDate.format("YYYY-MM-DD HH:mm:ss");
  // 	$(".date-picker-listing-date_range").removeClass("add-slot-shake-error");
  //   $.ajax({
  //     url: listeo.ajaxurl,
  //     type: "POST",
  //     data: {
  //       action: "check_date_range_availability",
  //       listing_id: listing_id,
  //       start_date: startDate,
  //       end_date: endDate,
  //     },
  //     success: function (response) {
  //       if (response.success) {
  //         if (response.data.available) {
  //           check_booking();
  //           // wait 2s and fadeOut .booking-notice-message
  // 		  setTimeout(function () {
  // 			$(".booking-notice-message").fadeOut();
  // 		  }, 2000);
  //         } else {
  //           if (response.data.next_available) {
  //             // Parse dates from response
  //             const newStartDate = moment(response.data.next_available.start);
  //             const newEndDate = moment(response.data.next_available.end);
  // 			$(".date-picker-listing-date_range").addClass("add-slot-shake-error");
  //             $(".booking-notice-message")
  //               .html(
  //                 "Selected time is not available.\n\n" +
  //                   "Next available time is:\n" +
  //                   newStartDate.format("DD-MM-YYYY HH:mm") +
  //                   " to " +
  //                   newEndDate.format("DD-MM-YYYY HH:mm")
  //               )
  //               .fadeIn();

  //             // Update the date picker with new dates
  //             picker.setStartDate(newStartDate);
  //             picker.setEndDate(newEndDate);

  //             // Optional: Trigger the date picker to update its display
  //             $(picker.element).data("daterangepicker").updateView();
  //             $(picker.element).data("daterangepicker").updateCalendars();
  //           } else {
  //             alert(response.data.message || "No available time slots found.");
  //           }
  //         }
  //       }
  //     },
  //     error: function () {
  //       alert("There was an error checking availability. Please try again.");
  //     },
  //   });
  // });

  //   // When time is selected
  //   $("#date-picker").on("apply.daterangepicker", function (ev, picker) {
  //     const selectedDateTime = picker.startDate;
  //     let isValidSelection = false;

  //     if (availableHours && availableHours.length > 0) {
  //       for (let slot of availableHours) {
  //         const slotStart = moment(slot.start);
  //         const slotEnd = moment(slot.end);

  //         if (selectedDateTime.isBetween(slotStart, slotEnd, undefined, "[]")) {
  //           isValidSelection = true;
  //           break;
  //         }
  //       }

  //       if (!isValidSelection) {
  //         // Find first available time
  //         const firstSlot = availableHours[0];
  //         if (firstSlot) {
  //           picker.setStartDate(moment(firstSlot.start));
  //           alert(
  //             "Selected time is not available. Moving to first available time."
  //           );
  //         }
  //         return false;
  //       }
  //     }

  //     // If we get here, proceed with booking check
  //     check_booking();
  //   });
  function calculate_price() {
    var ajax_data = {
      action: "calculate_price",
      listing_type: $("#date-picker").data("listing_type"),
      listing_id: $("input#listing_id").val(),
      tickets: $("input#tickets").val(),
      coupon: $("input#coupon_code").val(),
      //'nonce': nonce
    };

    // Add children count if present
    if ($(".qtyButtons.children input").length) {
      ajax_data.children = $(".qtyButtons.children input").val();
    }

    // Add pets count if present
    if ($(".qtyButtons.animals input").length) {
      ajax_data.animals = $(".qtyButtons.animals input").val();
    }
    var services = [];

    $.each($("input.bookable-service-checkbox:checked"), function () {
      var quantity = $(this)
        .parent()
        .find("input.bookable-service-quantity")
        .val();
      services.push({ service: $(this).val(), value: quantity });
    });
    ajax_data.services = services;
    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: ajax_data,

      success: function (data) {
        $("#negative-feedback").fadeOut();
        $("a.book-now").removeClass("inactive");
        if (data.data.price) {
          if (listeo_core.currency_position == "before") {
            $(".booking-estimated-cost span").html(
              listeo_core.currency_symbol + " " + data.data.price
            );
          } else {
            $(".booking-estimated-cost span").html(
              data.data.price + " " + listeo_core.currency_symbol
            );
          }
          $(".booking-estimated-cost").data("price", data.data.price);
          $(".booking-estimated-cost").fadeIn();
          // Itemized breakdown — see renderBookingBreakdown below.
          renderBookingBreakdown(data.data.breakdown);
        }
        if (data.data.price_discount) {
          if (listeo_core.currency_position == "before") {
            $(".booking-estimated-discount-cost span").html(
              listeo_core.currency_symbol + " " + data.data.price_discount
            );
          } else {
            $(".booking-estimated-discount-cost span").html(
              data.data.price_discount + " " + listeo_core.currency_symbol
            );
          }
          $(".booking-estimated-cost").addClass("estimated-with-discount");
          $(".booking-estimated-discount-cost").fadeIn();
        } else {
          $(".booking-estimated-cost").removeClass("estimated-with-discount");
          $(".booking-estimated-discount-cost").fadeOut();
        }
      },
    });
  }

  function calculate_booking_form_price() {
    var ajax_data = {
      action: "listeo_calculate_booking_form_price",
      coupon: $("input#coupon_code").val(),
      price: $("li.total-costs").data("price"),
      nonce: listeo.coupon_nonce,
    };

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: ajax_data,

      success: function (data) {
        if (data.price >= 0) {
          if (listeo_core.currency_position == "before") {
            $(".total-discounted_costs span").html(
              listeo_core.currency_symbol + " " + data.price
            );
          } else {
            $(".total-discounted_costs span").html(
              data.price + " " + listeo_core.currency_symbol
            );
          }

          $(".total-discounted_costs").fadeIn();
          $(".total-costs").addClass("estimated-with-discount");
        } else {
          $(".total-discounted_costs ").fadeOut();
          $(".total-costs").removeClass("estimated-with-discount");
        }
      },
    });
  }

  // function when checking booking by widget
  function check_booking() {
    inputClicked = true;
    if (is_open === false) return 0;

    // if we not deal with services with slots or opening hours
    if (
      $("#date-picker").data("listing_type") == "service" &&
      !$("input#slot").val() &&
      !$(".time-picker").val()
    ) {
      $("#negative-feedback").fadeIn();

      return;
    }

    if ($("#date-picker").data("rental-timepicker")) {
      var timeFormat = is24Hour ? "HH:mm" : "hh:mm A";
      Cookies.set(
        "listeo_rental_picker_startdate",
        $("#date-picker")
          .data("daterangepicker")
          .startDate.format(wordpress_date_format.date + " " + timeFormat)
      );
      Cookies.set(
        "listeo_rental_picker_enddate",
        $("#date-picker")
          .data("daterangepicker")
          .endDate.format(wordpress_date_format.date + " " + timeFormat)
      );
      // var startDataSQL need to have hour
      var startDataSql = moment(
        $("#date-picker").data("daterangepicker").startDate,
        ["MM/DD/YYYY " + timeFormat]
      ).format("YYYY-MM-DD HH:mm:ss");
      var endDataSql = moment(
        $("#date-picker").data("daterangepicker").endDate,
        ["MM/DD/YYYY " + timeFormat]
      ).format("YYYY-MM-DD HH:mm:ss");
    } else {
      Cookies.set(
        "listeo_rental_startdate",
        $("#date-picker")
          .data("daterangepicker")
          .startDate.format(wordpress_date_format.date)
      );
      Cookies.set(
        "listeo_rental_enddate",
        $("#date-picker")
          .data("daterangepicker")
          .endDate.format(wordpress_date_format.date)
      );
      var startDataSql = moment(
        $("#date-picker").data("daterangepicker").startDate,
        ["MM/DD/YYYY"]
      ).format("YYYY-MM-DD");
      var endDataSql = moment(
        $("#date-picker").data("daterangepicker").endDate,
        ["MM/DD/YYYY"]
      ).format("YYYY-MM-DD");
    }

    //console.log($('#date-picker').data('daterangepicker').startDate);

    // preparing data for ajax
    var ajax_data = {
      action: "check_avaliabity",
      listing_type: $("#date-picker").data("listing_type"),
      listing_id: $("input#listing_id").val(),
      coupon: $("input#coupon_code").val(),
      date_start: startDataSql,
      date_end: endDataSql,
      //'nonce': nonce
    };
    var services = [];
    // $.each($("input.bookable-service-checkbox:checked"), function(){
    //   		services.push($(this).val());
    // });
    // $.each($("input.bookable-service-quantity"), function(){
    //   		services.push($(this).val());
    // });
    $.each($("input.bookable-service-checkbox:checked"), function () {
      var quantity = $(this)
        .parent()
        .find("input.bookable-service-quantity")
        .val();
      services.push({ service: $(this).val(), value: quantity });
    });

    ajax_data.services = services;

    if ($("input#slot").val()) ajax_data.slot = $("input#slot").val();
    if ($("input.adults").val()) ajax_data.adults = $("input.adults").val();

    if ($(".time-picker").val()) ajax_data.hour = $(".time-picker").val();
    if ($(".time-picker-end-hour").val())
      ajax_data.end_hour = $(".time-picker-end-hour").val();

    // Add children count if present
    if ($(".qtyButtons input.children ").length) {
      ajax_data.children = $(".qtyButtons input.children ").val();
    }

    // Add animals count if present
    if ($(".qtyButtons input.animals ").length) {
      ajax_data.animals = $(".qtyButtons input.animals").val();
    }

    // Add tickets count if present (for event/ticket listings)
    if ($("input#tickets").length) {
      ajax_data.tickets = $("input#tickets").val();
    }

    // loader class
    $("a.book-now").addClass("loading");
    $("a.book-now-notloggedin").addClass("loading");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: ajax_data,

      success: function (data) {
      var default_error_message = $(".booking-error-message").data("default-message");
        $(".booking-error-message").html(default_error_message);
        // loader clas
        if (
          data.success == true &&
          (!$(".time-picker").length || is_open != false)
        ) {
          if (data.data.free_places > 0) {
            $("a.book-now,a.book-now-notloggedin").data(
              "freeplaces",
              data.data.free_places
            );
            // if date picker doesn't have daat attribute rental-timepicker

            $(".booking-error-message").fadeOut();
          
            $("a.book-now").removeClass("inactive");
            if (data.data.price) {
              if (listeo_core.currency_position == "before") {
                $(".booking-estimated-cost span").html(
                  listeo_core.currency_symbol + " " + data.data.price
                );
              } else {
                $(".booking-estimated-cost span").html(
                  data.data.price + " " + listeo_core.currency_symbol
                );
              }
              $(".booking-estimated-cost").data("price", data.data.price);
              $(".booking-estimated-cost").fadeIn();
              // Itemized breakdown (Phase 3 — pricing transparency).
              // Backward-compatible: older responses with no breakdown
              // key leave the static fees ul in place.
              renderBookingBreakdown(data.data.breakdown);
            } else {
              $(".booking-estimated-cost span").html(
                "0 " + listeo_core.currency_symbol
              );
              $(".booking-estimated-cost").fadeOut();
            }
            if (data.data.price_discount) {
              if (listeo_core.currency_position == "before") {
                $(".booking-estimated-discount-cost span").html(
                  listeo_core.currency_symbol + " " + data.data.price_discount
                );
              } else {
                $(".booking-estimated-discount-cost span").html(
                  data.data.price_discount + " " + listeo_core.currency_symbol
                );
              }
              $(".booking-estimated-cost").addClass("estimated-with-discount");
              $(".booking-estimated-discount-cost").fadeIn();
            } else {
              $(".booking-estimated-cost").removeClass(
                "estimated-with-discount"
              );
              $(".booking-estimated-discount-cost").fadeOut();
            }
            validate_coupon($("input#listing_id").val(), data.data.price);
            $(".coupon-widget-wrapper").fadeIn();

            // Auto-submit for event/ticket listings (one-click booking)
            if ($("input#tickets").length) {
              var eventDate = listeo_resolve_event_date();

              if (!eventDate) {
                // Cannot determine event date, just set freeplaces and let Book Now handler deal with it
                $("a.book-now,a.book-now-notloggedin").data("freeplaces", data.data.free_places);
              } else {
                var booking_data = {
                  listing_type: $("#listing_type").val(),
                  listing_id: $("input#listing_id").val(),
                  date_start: eventDate,
                  date_end: eventDate,
                  tickets: $("#tickets").val()
                };

                if ($("#coupon_code").val()) booking_data.coupon = $("#coupon_code").val();

                if (ajax_data.services && ajax_data.services.length > 0) {
                  booking_data.services = ajax_data.services;
                }

                $("input#booking").val(JSON.stringify(booking_data));
                window._listeo_booking_submitted = true;
                $("#form-booking").submit();
              }
            }
          } else {
            $("a.book-now,a.book-now-notloggedin").data("freeplaces", 0);
            // if data.data.errro exists, set the message to .booking-error-message
            
            // if (!$("#date-picker").data("rental-timepicker")) {
            //   $(".booking-error-message").fadeIn();
            // }
            $(".booking-error-message").fadeIn();
            $(".booking-estimated-cost").fadeOut();

            $(".booking-estimated-cost span").html("");
          }
        } else {
          //   if (!$("#date-picker").data("rental-timepicker")) {
          //     $("a.book-now,a.book-now-notloggedin").data("freeplaces", 0);
          //     $(".booking-error-message").fadeIn();
          //   }
          
            if (data.error) {
              $(".booking-error-message").html(data.error);
            }

          $(".booking-error-message").fadeIn();
          $(".booking-estimated-cost").fadeOut();
        }
        $("a.book-now").removeClass("loading");
        $("a.book-now-notloggedin").removeClass("loading");
      },
    });
  }

  var is_open = true;
  var lastDayOfWeek;

  // update slots and check hours setted to this day
  function update_booking_widget() {
    // function only for services
    if ($("#date-picker").data("listing_type") != "single_day") return;

    $("a.book-now").addClass("loading");
    $("a.book-now-notloggedin").addClass("loading");
    // get day of week

    var date = $("#date-picker").data("daterangepicker").endDate._d;
    var dayOfWeek = date.getDay() - 1;

    if (date.getDay() == 0) {
      dayOfWeek = 6;
    }

    var startDataSql = moment(
      $("#date-picker").data("daterangepicker").startDate,
      ["MM/DD/YYYY"]
    ).format("YYYY-MM-DD");
    var endDataSql = moment($("#date-picker").data("daterangepicker").endDate, [
      "MM/DD/YYYY",
    ]).format("YYYY-MM-DD");

    var ajax_data = {
      action: "update_slots",
      listing_id: $("input#listing_id").val(),
      date_start: startDataSql,
      date_end: endDataSql,
      slot: dayOfWeek,
      //'nonce': nonce
    };

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo.ajaxurl,
      data: ajax_data,

      success: function (data) {
        $(".time-slots-dropdown .panel-dropdown-scrollable").html(data.data);

        // reset values of slot selector
        if (dayOfWeek != lastDayOfWeek) {
          $(".panel-dropdown-scrollable .time-slot input").prop(
            "checked",
            false
          );

          $(".panel-dropdown.time-slots-dropdown input#slot").val("");
          $(".panel-dropdown.time-slots-dropdown a").html(
            $(".panel-dropdown.time-slots-dropdown a").attr("placeholder")
          );
          $(" .booking-estimated-cost span").html(" ");
        }

        lastDayOfWeek = dayOfWeek;

        if (
          !$(".panel-dropdown-scrollable .time-slot[day='" + dayOfWeek + "']")
            .length
        ) {
          $(".no-slots-information").show();
          $(".panel-dropdown.time-slots-dropdown a").html(
            $(".no-slots-information").html()
          );
        } else {
          // when we dont have slots for this day reset cost and show no slots
          $(".no-slots-information").hide();
          $(".panel-dropdown.time-slots-dropdown a").html(
            $(".panel-dropdown.time-slots-dropdown a").attr("placeholder")
          );
          $(" .booking-estimated-cost span").html(" ");
        }
        // show only slots for this day
        $(".panel-dropdown-scrollable .time-slot").hide();

        $(
          ".panel-dropdown-scrollable .time-slot[day='" + dayOfWeek + "']"
        ).show();
        $(".time-slot").each(function () {
          var timeSlot = $(this);
          $(this)
            .find("input")
            .on("change", function () {
              var timeSlotVal = timeSlot.find("strong").text();
              var slotArray = [
                timeSlot.find("strong").text(),
                timeSlot.find("input").val(),
              ];

              $(".panel-dropdown.time-slots-dropdown input#slot").val(
                JSON.stringify(slotArray)
              );

              $(".panel-dropdown.time-slots-dropdown a").html(timeSlotVal);

              $(".panel-dropdown").removeClass("active");

              check_booking();
            });
        });
        $("a.book-now").removeClass("loading");
        $("a.book-now-notloggedin").removeClass("loading");
      },
    });

    // check if opening days are active
    if ($(".time-picker").length) {
      if (availableDays) {
        var availableDays = JSON.parse(availableDays);

        if (
          availableDays[dayOfWeek].opening == "Closed" ||
          availableDays[dayOfWeek].closing == "Closed"
        ) {
          $("#negative-feedback").fadeIn();

          //$('a.book-now').css('background-color','grey');

          is_open = false;
          //console.log('zamkniete tego dnia' + dayOfWeek);
          return;
        }

        // converent hours to 24h format
        var opening_hour = moment(availableDays[dayOfWeek].opening, [
          "h:mm A",
        ]).format("HH:mm");
        var closing_hour = moment(availableDays[dayOfWeek].closing, [
          "h:mm A",
        ]).format("HH:mm");

        // get hour in 24 format
        var current_hour = $(".time-picker").val();

        // check if currer hour bar is open
        if (current_hour >= opening_hour && current_hour <= closing_hour) {
          is_open = true;
          $("#negative-feedback").fadeOut();
          $("a.book-now").attr("href", "#").css("background-color", "#f30c0c");
          check_booking();
          //console.log('otwarte' + dayOfWeek);
        } else {
          is_open = false;
          $("#negative-feedback").fadeIn();
          //$('a.book-now').attr('href','#').css('background-color','grey');
          $(".booking-estimated-cost span").html("");
          //console.log('zamkniete');
        }
      } else {
        check_booking();
      }
    }
  }

  // if slots exist update them
  if ($(".time-slot").length) {
    
    update_booking_widget();
  }

  // show only services for actual day from datapicker
  $("#date-picker").on("apply.daterangepicker", update_booking_widget);
  $("#date-picker").on("change", function () {
    
    check_booking();
    //	update_booking_widget();
  });

  // when slot is selected check if there are avalible bookings
  $("#date-picker").on("apply.daterangepicker", check_booking);
  $("#date-picker").on("cancel.daterangepicker", check_booking);

  $(document).on(
    "change",
    "input#slot,input.adults,.panel-with-children input, input.bookable-service-quantity, .form-booking-single_day input.bookable-service-checkbox,.form-booking-date_range input.bookable-service-checkbox",
    function (event) {
      // For ticket forms, use calculate_price instead of check_booking
      // to prevent auto-submit when changing extra service quantities
      if ($("#form-booking").hasClass("form-booking-tickets")) {
        calculate_price();
        return;
      }
      check_booking();
    }
  );
  //$('input#slot').on( 'change', check_booking );

  $("input#tickets,.form-booking-tickets input.bookable-service-checkbox").on(
    "change",
    function (e) {
      //check_booking();
      calculate_price();
    }
  );

  // hours picker
  if ($(".time-picker").length) {
    var time24 = false;

    if (listeo_core.clockformat) {
      time24 = true;
    }
    const calendars = $(".time-picker").flatpickr({
      enableTime: true,
      noCalendar: true,
      dateFormat: "H:i",
      time_24hr: time24,
      disableMobile: "true",
      minuteIncrement: timePickerIncrement,

      // check if there are free days on change and calculate price
      onClose: function (selectedDates, dateStr, instance) {
        update_booking_widget();
        check_booking();
      },
    });

    if ($("#_hour_end").length) {
      calendars[0].config.onClose = [
        () => {
          setTimeout(() => calendars[1].open(), 1);
        },
      ];

      calendars[0].config.onChange = [
        (selDates) => {
          calendars[1].set("minDate", selDates[0]);
        },
      ];
      // 		 calendars[0].config.onChange = [
      //      (selDates) => {
      //        if (selDates[0]) {
      //          let minEndDate = new Date(selDates[0]);
      //          minEndDate.setHours(minEndDate.getHours() + 4);
      //          calendars[1].set("minDate", minEndDate);
      //        }
      //      },
      //    ];

      calendars[1].config.onChange = [
        (selDates) => {
          calendars[0].set("maxDate", selDates[0]);
        },
      ];
    }
  }

  /*----------------------------------------------------*/
  /*  Bookings Dashboard Script
/*----------------------------------------------------*/
  $(".booking-services").on("click", ".qtyInc", function () {
    var $button = $(this);

    var oldValue = $button.parent().find("input").val();
    //console.log(oldValue);
    if (oldValue == 2) {
      //$button.parents('.single-service').find('label').trigger('click');
      $button
        .parents(".single-service")
        .find("input.bookable-service-checkbox")
        .prop("checked", true);
      updateCounter();
    }
    if ($("#form-booking").hasClass("form-booking-tickets")) {
      calculate_price();
    } else {
      check_booking();
    }
    //calculate_price();
  });

  if ($("#booking-date-range").length) {
    // to update view with bookin

    var bookingsOffset = 0;

    // here we can set how many bookings per page
    var bookingsLimit = 5;

    // function when checking booking by widget
    function listeo_bookings_manage(page) {
      if ($("#booking-date-range").data("daterangepicker")) {
        var startDataSql = moment(
          $("#booking-date-range").data("daterangepicker").startDate,
          ["MM/DD/YYYY"]
        ).format("YYYY-MM-DD");
        var endDataSql = moment(
          $("#booking-date-range").data("daterangepicker").endDate,
          ["MM/DD/YYYY"]
        ).format("YYYY-MM-DD");
      } else {
        var startDataSql = "";
        var endDataSql = "";
      }
      if (!page) {
        page = 1;
      }
      if (page == "prev") {
        var current = $("div.pagination-container li.current").data("paged");
        page = current - 1;
      }
      if (page == "next") {
        var current = $("div.pagination-container li.current").data("paged");
        page = current + 1;
      }

      // preparing data for ajax
      var ajax_data = {
        action: "listeo_bookings_manage",
        date_start: startDataSql,
        date_end: endDataSql,
        listing_id: $("#listing_id").val(),
        listing_status: $("#listing_status").val(),
        booking_author: $("#booking_author").val(),
        dashboard_type: $("#dashboard_type").val(),
        limit: bookingsLimit,
        offset: bookingsOffset,
        page: page,
        //'nonce': nonce
      };

      // display loader class
      $(".dashboard-list-box").addClass("loading");

      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: ajax_data,

        success: function (data) {
          // display loader class
          $(".dashboard-list-box").removeClass("loading");

          if (data.data.html) {
            $("#no-bookings-information").hide();
            $("ul#booking-requests").html(data.data.html);
            $(".pagination-container").html(data.data.pagination);
          } else {
            $("ul#booking-requests").empty();
            $(".pagination-container").empty();
            $("#no-bookings-information").show();
          }
        },
      });
    }

    $("#dashboard-booking-author-search").autocomplete({
      source: function (req, response) {
        $.getJSON(
          listeo_core.ajax_url +
            "?callback=?&action=listeo_core_incremental_listing_suggest",
          req,
          response
        );
      },
      select: function (event, ui) {
        window.location.href = ui.item.link;
      },
      minLength: 3,
    });

    // hooks for get bookings into view
    $("#booking-date-range").on("apply.daterangepicker", function (e) {
      listeo_bookings_manage();
    });
    $("#listing_id").on("change", function (e) {
      listeo_bookings_manage();
    });
    $("#listing_status").on("change", function (e) {
      listeo_bookings_manage();
    });
    $("#booking_author").on("change", function (e) {
      listeo_bookings_manage();
    });

    $("div.pagination-container").on("click", "a", function (e) {
      e.preventDefault();

      var page = $(this).parent().data("paged");

      listeo_bookings_manage(page);

      $("body, html").animate(
        {
          scrollTop: $(".dashboard-list-box").offset().top,
        },
        600
      );

      return false;
    });

    $(document).on("click", ".reject, .cancel", function (e) {
      e.preventDefault();
      if (window.confirm(listeo_core.areyousure)) {
        var $this = $(this);
        $this.parents("li").addClass("loading");
        var status = "confirmed";
        if ($(this).hasClass("reject")) status = "cancelled";
        if ($(this).hasClass("cancel")) status = "cancelled";

        // preparing data for ajax
        var ajax_data = {
          action: "listeo_bookings_manage",
          booking_id: $(this).data("booking_id"),
          status: status,
          //'nonce': nonce
        };
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: ajax_data,

          success: function (data) {
            // display loader class
            $this.parents("li").removeClass("loading");

            listeo_bookings_manage();
          },
        });
      }
    });

    $(document).on("click", ".delete", function (e) {
      e.preventDefault();
      if (window.confirm(listeo_core.areyousure)) {
        var $this = $(this);
        $this.parents("li").addClass("loading");
        var status = "deleted";

        // preparing data for ajax
        var ajax_data = {
          action: "listeo_bookings_manage",
          booking_id: $(this).data("booking_id"),
          status: status,
          //'nonce': nonce
        };
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: ajax_data,

          success: function (data) {
            // display loader class
            $this.parents("li").removeClass("loading");

            listeo_bookings_manage();
          },
        });
      }
    });

    $(document).on("click", ".renew_booking", function (e) {
      e.preventDefault();
      if (window.confirm(listeo_core.areyousure)) {
        var $this = $(this);
        $this.parents("li").addClass("loading");

        // preparing data for ajax
        var ajax_data = {
          action: "listeo_bookings_renew_booking",
          booking_id: $(this).data("booking_id"),
          //'nonce': nonce
        };
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: ajax_data,

          success: function (data) {
            if (data.success) {
            } else {
              alert(listeo_core.booked_dates);
            }
            // display loader class
            $this.parents("li").removeClass("loading");

            listeo_bookings_manage();
          },
        });
      }
    });

    $(document).on("click", ".approve", function (e) {
      e.preventDefault();
      var $this = $(this);
      $this.parents("li").addClass("loading");
      var status = "confirmed";
      if ($(this).hasClass("reject")) status = "cancelled";
      if ($(this).hasClass("cancel")) status = "cancelled";

      // preparing data for ajax
      var ajax_data = {
        action: "listeo_bookings_manage",
        booking_id: $(this).data("booking_id"),
        status: status,
        //'nonce': nonce
      };
      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: ajax_data,

        success: function (data) {
          // display loader class
          $this.parents("li").removeClass("loading");

          listeo_bookings_manage();
        },
      });
    });
    $(document).on("click", ".mark-as-paid", function (e) {
      e.preventDefault();
      var $this = $(this);
      $this.parents("li").addClass("loading");
      var status = "paid";

      // preparing data for ajax
      var ajax_data = {
        action: "listeo_bookings_manage",
        booking_id: $(this).data("booking_id"),
        status: status,
               //'nonce': nonce
      };
      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: ajax_data,

        success: function (data) {
          // display loader class
          $this.parents("li").removeClass("loading");

          listeo_bookings_manage();
        },
      });
    });

    var start = moment().subtract(30, "days");
    var end = moment();

    function cb(start, end) {
      $("#booking-date-range span,#chart-date-range span").html(
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

    var today = listeo_core.today;

    $("#booking-date-range-enabler").on("click", function (e) {
      e.preventDefault();
      $(this).hide();
      cb(start, end);
      $("#booking-date-range,#chart-date-range")
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
        )
        .trigger("click");
      cb(start, end);
    });

    // Calendar animation and visual settings
    $("#booking-date-range").on("show.daterangepicker", function (ev, picker) {
      $(".daterangepicker").addClass(
        "calendar-visible calendar-animated bordered-style"
      );
      $(".daterangepicker").removeClass("calendar-hidden");
    });
    $("#booking-date-range").on("hide.daterangepicker", function (ev, picker) {
      $(".daterangepicker").removeClass("calendar-visible");
      $(".daterangepicker").addClass("calendar-hidden");
    });
  } // end if dashboard booking

  // $('a.reject').on('click', function() {

  // 	console.log(picker);

  // });

  /*----------------------------------------------------*/
  /*  Time Slots Carousel Functions
  /*----------------------------------------------------*/
  if ($('.time-slots-carousel-container').length) {

    var currentPosition = 0;
    var $track = $('.slot-carousel-track');
    var $columns = $('.slot-carousel-day-column');
    var columnWidth = 0;
    var visibleColumns = 3;
    var maxPosition = 0;

    // Set Moment.js locale from WordPress
    if (typeof moment !== 'undefined' && typeof listeo_core !== 'undefined' && listeo_core.wp_locale) {
      moment.locale(listeo_core.wp_locale);
    }

    // Initialize carousel on page load
    initializeCarousel();

    // Handle slot selection
    $('.time-slots-carousel-container').on('click', '.calendar-slot-available', function(e) {
      e.preventDefault();

      // Remove previous selection
      $('.calendar-slot').removeClass('selected');

      // Add selection to clicked slot
      $(this).addClass('selected');

      // Get slot data
      var slotValue = $(this).data('slot-value');
      var slotTime = $(this).data('slot-time');

      // Get the date from the parent column
      var selectedDate = $(this).closest('.slot-carousel-day-column').data('date');

      // Update hidden slot input
      $('#slot').val(JSON.stringify([slotTime, slotValue]));

      // Set the date picker value programmatically (required for check_booking to work)
      if ($('#date-picker').length && selectedDate) {
        var datePicker = $('#date-picker').data('daterangepicker');
        if (datePicker && typeof moment !== 'undefined') {
          var momentDate = moment(selectedDate);
          datePicker.setStartDate(momentDate);
          datePicker.setEndDate(momentDate);
        }
      }

      // Check booking availability
      if (typeof check_booking === 'function') {
        check_booking();
      }
    });

    // Navigation buttons - move by visible columns count
    $('.slot-carousel-nav-btn.prev-day').on('click', function() {
      if (currentPosition > 0) {
        currentPosition = Math.max(0, currentPosition - visibleColumns);
        updateCarouselPosition();
      }
    });

    $('.slot-carousel-nav-btn.next-day').on('click', function() {
      if (currentPosition < maxPosition) {
        currentPosition = Math.min(maxPosition, currentPosition + visibleColumns);
        updateCarouselPosition();

        // Check if we're within 7 days of the end, load more days if needed
        var totalColumns = $columns.length;
        var daysFromEnd = totalColumns - (currentPosition + visibleColumns);
        if (daysFromEnd <= 7) {
          appendMoreDays(14);
        }
      }
    });

    // Append more days to carousel dynamically using AJAX
    function appendMoreDays(daysToAdd) {
      if (typeof moment === 'undefined') return;

      // Get the last loaded date from data attribute
      var $container = $('.time-slots-carousel-container');
      var lastLoadedDate = $container.data('availability-loaded-until');

      if (!lastLoadedDate) {
        // Fallback to last column's date if attribute not found
        var $lastColumn = $('.slot-carousel-day-column').last();
        if (!$lastColumn.length) return;
        lastLoadedDate = $lastColumn.data('date');
      }

      var startDate = moment(lastLoadedDate).add(1, 'days');
      var endDate = moment(startDate).add(daysToAdd - 1, 'days');

      // Make AJAX request for availability
      $.ajax({
        type: 'POST',
        url: listeo.ajaxurl,
        data: {
          action: 'get_carousel_slots_availability',
          listing_id: $('#listing_id').val(),
          date_start: startDate.format('YYYY-MM-DD'),
          date_end: endDate.format('YYYY-MM-DD')
        },
        success: function(response) {
          if (response.success && response.data) {
            var availabilityData = response.data;

            // Iterate through each day in the response
            for (var dateStr in availabilityData) {
              if (!availabilityData.hasOwnProperty(dateStr)) continue;

              var currentDate = moment(dateStr);
              var dayOfWeek = (currentDate.isoWeekday() % 7);
              var daySlots = availabilityData[dateStr];
              var hasSlots = Array.isArray(daySlots) && daySlots.length > 0;

              // Get day name and date with translations
              var dayName = currentDate.format('ddd,');

              // Get translated month abbreviation
              var monthNames = [
                listeo_core.month_abbrev_jan, listeo_core.month_abbrev_feb, listeo_core.month_abbrev_mar,
                listeo_core.month_abbrev_apr, listeo_core.month_abbrev_may, listeo_core.month_abbrev_jun,
                listeo_core.month_abbrev_jul, listeo_core.month_abbrev_aug, listeo_core.month_abbrev_sep,
                listeo_core.month_abbrev_oct, listeo_core.month_abbrev_nov, listeo_core.month_abbrev_dec
              ];
              var monthIndex = currentDate.month();
              var dateShort = currentDate.format('D') + ' ' + monthNames[monthIndex];

              // Build HTML for the new column
              var columnHtml = '<div class="slot-carousel-day-column" data-day="' + dayOfWeek + '" data-date="' + dateStr + '">';
              columnHtml += '<div class="calendar-day ' + (hasSlots ? 'day-available' : 'day-placeholder') + '">';
              columnHtml += '<div class="calendar-day-date">';
              columnHtml += '<p class="day-name">' + dayName + '</p>';
              columnHtml += '<p class="day-date">' + dateShort + '</p>';
              columnHtml += '</div>';
              columnHtml += '<div class="calendar-day-slots">';
              columnHtml += '<ul class="calendar-day-slots-list">';

              if (hasSlots) {
                // Add actual available slots
                var maxVisible = 10;
                var totalSlots = daySlots.length;

                for (var j = 0; j < totalSlots; j++) {
                  var slot = daySlots[j];
                  var timeRange = slot.time;
                  var available = slot.available;
                  var slotKey = slot.slot_key;
                  var slotDayOfWeek = slot.day_of_week;
                  var isBooked = slot.is_booked || false;

                  var timeParts = timeRange.split('-');
                  var startTime = timeParts[0].trim();

                  // Hide slots after the 10th
                  var hiddenClass = (j >= maxVisible) ? ' hidden-slot' : '';

                  // Determine slot class based on booked status
                  var slotClass = isBooked ? 'calendar-slot calendar-slot-booked' : 'calendar-slot calendar-slot-available';
                  var slotDisabled = isBooked ? ' disabled' : '';

                  columnHtml += '<li class="calendar-day-slots-list-item' + hiddenClass + '">';
                  columnHtml += '<button type="button" class="' + slotClass + '"';
                  columnHtml += ' data-slot-value="' + slotDayOfWeek + '|' + slotKey + '"';
                  columnHtml += ' data-slot-time="' + timeRange + '"' + slotDisabled + '>';
                  columnHtml += startTime;
                  columnHtml += '</button>';
                  columnHtml += '</li>';
                }

                // Add "Show More" button if there are more than 10 slots
                if (totalSlots > maxVisible) {
                  var moreCount = totalSlots - maxVisible;
                  var showMoreText = (typeof listeo_core !== 'undefined' && listeo_core.show_more_slots)
                    ? listeo_core.show_more_slots.replace('%d', moreCount)
                    : 'Show ' + moreCount + ' more';
                  columnHtml += '<li class="calendar-day-slots-list-item show-more-item">';
                  columnHtml += '<button type="button" class="calendar-slot-show-more">';
                  columnHtml += showMoreText;
                  columnHtml += '</button>';
                  columnHtml += '</li>';
                }
              } else {
                // Add placeholder slots
                for (var p = 0; p < 6; p++) {
                  columnHtml += '<li class="calendar-day-slots-list-item">';
                  columnHtml += '<button type="button" class="calendar-slot calendar-slot-placeholder" disabled tabindex="-1">-</button>';
                  columnHtml += '</li>';
                }
              }

              columnHtml += '</ul>';
              columnHtml += '</div>';
              columnHtml += '</div>';
              columnHtml += '</div>';

              // Append to track
              $('.slot-carousel-track').append(columnHtml);
            }

            // Update the last loaded date
            $container.data('availability-loaded-until', endDate.format('YYYY-MM-DD'));
            $container.attr('data-availability-loaded-until', endDate.format('YYYY-MM-DD'));

            // Recalculate dimensions and height after new columns are fully rendered
            setTimeout(function() {
              $columns = $('.slot-carousel-day-column');
              calculateDimensions();
              updateCarouselHeight();
            }, 100);
          }
        },
        error: function(xhr, status, error) {
          console.error('Failed to load more carousel days:', error);
        }
      });
    }

    // Initialize carousel
    function initializeCarousel() {
      calculateDimensions();
      updateCarouselPosition();

      // Recalculate height and show carousel
      setTimeout(function() {
        updateCarouselHeight();

        // Add loaded class - CSS removes max-height restriction and hides loader
        $('.time-slots-carousel-container').addClass('slot-carousel-loaded');
      }, 100);

      // Recalculate on window resize
      $(window).on('resize', debounce(function() {
        calculateDimensions();
        updateCarouselPosition();
      }, 250));
    }

    // Calculate carousel dimensions
    function calculateDimensions() {
      var viewportWidth = $('.slot-carousel-viewport').width();

      // Determine visible columns based on screen size
      if ($(window).width() <= 1360) {
        visibleColumns = 2;
      } else {
        visibleColumns = 3;
      }

      // Get actual column width from rendered element (includes gap in flex layout)
      if ($columns.length > 0) {
        var firstColumn = $columns.first();
        var columnActualWidth = firstColumn.outerWidth(); // Gets the actual rendered width
        var gap = 15; // Gap between columns
        columnWidth = columnActualWidth + gap; // Width + gap = distance to move
      } else {
        columnWidth = viewportWidth / visibleColumns;
      }

      // Calculate max position
      var totalColumns = $columns.length;
      maxPosition = Math.max(0, totalColumns - visibleColumns);

      // Reset position if needed
      if (currentPosition > maxPosition) {
        currentPosition = maxPosition;
      }
    }

    // Update carousel position
    function updateCarouselPosition() {
      var translateX = -(currentPosition * columnWidth);
      $track.css('transform', 'translateX(' + translateX + 'px)');

      // Update navigation button states
      $('.slot-carousel-nav-btn.prev-day').prop('disabled', currentPosition === 0);
      $('.slot-carousel-nav-btn.next-day').prop('disabled', currentPosition >= maxPosition);

      // Update carousel height based on visible columns
      updateCarouselHeight();
    }

    // Calculate and set carousel height based on tallest visible column
    function updateCarouselHeight() {
      var maxHeight = 0;

      // Get visible column range
      var visibleStart = currentPosition;
      var visibleEnd = currentPosition + visibleColumns;

      // Refresh columns reference to ensure we have the latest DOM state
      $columns = $('.slot-carousel-day-column');

      // Find tallest column in visible range (avoid variable name collision)
      var $visibleColumnElements = $columns.slice(visibleStart, visibleEnd);

      if ($visibleColumnElements.length === 0) return;

      $visibleColumnElements.each(function() {
        var $calendarDay = $(this).find('.calendar-day');

        if (!$calendarDay.length || !$calendarDay[0]) return;

        // Force reflow to get accurate height
        var height = $calendarDay[0].offsetHeight;

        // Get computed margins
        var marginTop = parseInt($(this).css('margin-top')) || 0;
        var marginBottom = parseInt($(this).css('margin-bottom')) || 0;
        var columnHeight = height + marginTop + marginBottom;

        if (columnHeight > maxHeight) {
          maxHeight = columnHeight;
        }
      });

      // Account for viewport padding
      var $viewport = $('.slot-carousel-viewport');
      var viewportPaddingTop = parseInt($viewport.css('padding-top')) || 0;
      var viewportPaddingBottom = parseInt($viewport.css('padding-bottom')) || 0;
      var totalPadding = viewportPaddingTop + viewportPaddingBottom;

      // Set viewport height (content height + viewport padding)
      if (maxHeight > 0) {
        $viewport.css('height', (maxHeight + totalPadding) + 'px');
      }
    }

    // Debounce function for resize
    function debounce(func, wait) {
      var timeout;
      return function() {
        var context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
          func.apply(context, args);
        }, wait);
      };
    }

    // Handle "Show More" button clicks
    $('.time-slots-carousel-container').on('click', '.calendar-slot-show-more', function(e) {
      e.preventDefault();

      var $calendarDay = $(this).closest('.calendar-day');
      $calendarDay.addClass('expanded');

      // Recalculate height after expansion
      setTimeout(function() {
        updateCarouselHeight();
      }, 50);
    });

    // Sync with date picker if it exists
    $('#date-picker').on('apply.daterangepicker', function(ev, picker) {
      if ($('.time-slots-carousel-container').length) {
        var selectedDate = picker.startDate;
        var targetDateStr = selectedDate.format('YYYY-MM-DD');

        // Find the matching day column
        var $targetColumn = $('.carousel-day-column[data-date="' + targetDateStr + '"]');

        if ($targetColumn.length) {
          var targetIndex = $targetColumn.index();

          // Calculate position to show this column
          currentPosition = Math.max(0, Math.min(targetIndex, maxPosition));
          updateCarouselPosition();
        }
      }
    });
  }

});

})(this.jQuery);
