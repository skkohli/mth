/**
 * Calendar view on single listing
 *
 * @since 1.8.24
 */

(function (window, undefined) {
  window.wp = window.wp || {};
  var document = window.document;
  var $ = window.jQuery;
  var wp = window.wp;
  var $document = $(document);
  var today = new Date().toISOString().slice(0, 10);
  var dailyPrices = {}; // Store daily prices
  var priceCache = {}; // Cache for fetched price ranges to avoid redundant requests
  var cacheExpiry = 5 * 60 * 1000; // 5 minutes cache expiry

  document.addEventListener("DOMContentLoaded", function () {
    var calendarEl = document.getElementById("calendar");
    
    if(calendarEl){
      var listingId = $("#calendar").data("listing-id");
      
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        locale: listeoCal.language,
        
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "",
        },
        validRange: {
          start: today,
        },
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
              action: "listeo_get_calendar_view_single_events",
              dates: fetchInfo,
              listing_id: $("#calendar").data("listing-id"),
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
        datesSet: function(info) {
          // Fetch prices when calendar view changes
          fetchDailyPrices(info.start, info.end, $("#calendar").data("listing-id"));
        },
        dayCellDidMount: function(info) {
          // Add price to each day cell if prices are already loaded
          var dateStr = info.date.toISOString().slice(0, 10);
          
          if (dailyPrices[dateStr]) {
            var priceText = '';
            var currencySymbol = listeoCal.currency_symbol || '$';
            var currencyPosition = listeoCal.currency_position || 'before';
            
            if (currencyPosition === 'before') {
              priceText = currencySymbol + dailyPrices[dateStr];
            } else {
              priceText = dailyPrices[dateStr] + currencySymbol;
            }
            var priceElement = $('<div class="fc-day-price">' + priceText + '</div>');
            $(info.el).append(priceElement);
          }
        },
        loading: function (isLoading) {
          if (isLoading) {
            $(".dashboard-list-box").addClass("loading");
          } else {
            $(".dashboard-list-box").removeClass("loading");
          }
        },
      });
      
      // Function to fetch daily prices for the visible date range
      function fetchDailyPrices(startDate, endDate, listingId) {
        var start = startDate.toISOString().slice(0, 10);
        var end = endDate.toISOString().slice(0, 10);
        var cacheKey = listingId + '_' + start + '_' + end;
        
        // Check if we have cached data that's still valid
        if (priceCache[cacheKey] && (Date.now() - priceCache[cacheKey].timestamp < cacheExpiry)) {
          console.log('Using cached price data for range:', start, 'to', end);
          dailyPrices = Object.assign(dailyPrices, priceCache[cacheKey].data);
          updateDayCellPrices();
          return;
        }
        
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: {
            action: "listeo_get_calendar_daily_prices",
            listing_id: listingId,
            start_date: start,
            end_date: end,
          },
          success: function (response) {
            if (response.success && response.data) {
              dailyPrices = Object.assign(dailyPrices, response.data);
              
              // Cache the fetched data
              priceCache[cacheKey] = {
                data: response.data,
                timestamp: Date.now()
              };
              
              updateDayCellPrices();
            }
          },
          error: function(xhr, status, error) {
            console.log('Error fetching daily prices:', error);
          }
        });
      }
      
      // Function to update day cells with prices
      function updateDayCellPrices() {
        setTimeout(function() {
          // Add prices to existing day cells
          $('.fc-daygrid-day').each(function() {
            var $dayEl = $(this);
            var dateAttr = $dayEl.attr('data-date');
            
            if (dateAttr && dailyPrices[dateAttr]) {
              // Remove any existing price element
              $dayEl.find('.fc-day-price').remove();
              
              // Add price element
              var currencySymbol = listeoCal.currency_symbol || '$';
              var currencyPosition = listeoCal.currency_position || 'before';
              var priceText = '';
              
              if (currencyPosition === 'before') {
                priceText = currencySymbol + dailyPrices[dateAttr];
              } else {
                priceText = dailyPrices[dateAttr] + currencySymbol;
              }
              
              var priceElement = $('<div class="fc-day-price">' + priceText + '</div>');
              $dayEl.append(priceElement);
            }
          });
        }, 100);
      }
      
      calendar.render();
    }
  });

})(window);