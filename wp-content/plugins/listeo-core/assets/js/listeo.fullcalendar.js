/**
 * Stats
 *
 * @since 1.8.24
 */
(function (window, undefined) {
  window.wp = window.wp || {};
  var document = window.document;
  var $ = window.jQuery;
  var wp = window.wp;
  var $document = $(document);

  document.addEventListener("DOMContentLoaded", function () {
    var calendarEl = document.getElementById("calendar");
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: "dayGridMonth",

      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,listWeek",
      },
      locale: listeoCal.language,
      eventTimeFormat: {
        // like '14:30:00'
        hour: "2-digit",
        minute: "2-digit",
        meridiem: false,
      },
      events: function (fetchInfo, successCallback, failureCallback) {
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: {
            action: "listeo_get_calendar_view_events",
            dates: fetchInfo,
            listing_id: $("#listing_id").val(),
            listing_status: $("#listing_status").val(),
            booking_author: $("#booking_author").val(),
          },
          success: function (response) {
            var events = [];
            $.each(response, function (i, item) {
              events.push(item);
            });

            successCallback(events);
          },
        });
      },
      loading: function (isLoading) {
        if (isLoading) {
          $(".dashboard-list-box").addClass("loading");
        } else {
          $(".dashboard-list-box").removeClass("loading");
        }
      },
      eventClick: function (info) {
        $(".dashboard-list-box").addClass("loading");
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: {
            action: "listeo_get_calendar_view_event_details",
            id: info.event.id,
          },
          success: function (response) {
            $(".small-dialog-booking-content ul").html(response.data.html);
            $(".popup-with-zoom-anim").trigger("click");
            $(".dashboard-list-box").removeClass("loading");
          },
          error: function () {
            $(".dashboard-list-box").removeClass("loading");
          },
        });

        //alert("Event: " + info.event.extendedProps.description);
      },
    });
    calendar.render();

      if ($(".dashboard-calendar-view").length) {
        $("#listing_id").on("change", function (e) {
          calendar.refetchEvents();
        });
        $("#listing_status").on("change", function (e) {
          calendar.refetchEvents();
        });
        $("#booking_author").on("change", function (e) {
          calendar.refetchEvents();
        });
      };


    if ($(".dashboard-calendar-view").length) {
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
              calendar.refetchEvents();
               $(".mfp-close").trigger("click");
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
              calendar.refetchEvents();
               $(".mfp-close").trigger("click");
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
              calendar.refetchEvents();
               $(".mfp-close").trigger("click");
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
            calendar.refetchEvents();
             $(".mfp-close").trigger("click");
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
            calendar.refetchEvents();
             $(".mfp-close").trigger("click");
          },
        });
      });
    }
  });


})(window);