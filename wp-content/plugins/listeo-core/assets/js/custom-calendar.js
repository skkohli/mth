/**
 * Custom Calendar JS for Listeo
 * Handles the calendar UI for blocking dates and setting date ranges
 */
jQuery(document).ready(function($) {
    // Initialize variables
    var blockedDates = [];
    var blockedRanges = [];
    var selectedDates = [];
    var specialPrices = {};

    // Initialize the fullcalendar element
    var calendar = $("#fullcalendar").fullCalendar({
      locale: listeoCal.language,
      header: {
        left: "prev,next today",
        center: "title",
        right: "month",
      },
      selectable: true,
      selectHelper: true,
      editable: true,
      eventLimit: true,
      events: [],

      // When user selects a date, add it to the selected dates
      select: function (start, end, jsEvent, view) {
        // Format selected range for display
        var startDate = $.fullCalendar.formatDate(start, "YYYY-MM-DD");
        var endDate = $.fullCalendar.formatDate(end, "YYYY-MM-DD");

        // Add to selected dates array
        var date = startDate;
        while (date < endDate) {
          if (selectedDates.indexOf(date) === -1) {
            selectedDates.push(date);
          }
          date = moment(date).add(1, "days").format("YYYY-MM-DD");
        }

        // Highlight selected dates
        renderCalendar();
      },

      // When a day is clicked, toggle it in the selected dates
      dayClick: function (date, jsEvent, view) {
        var dateStr = $.fullCalendar.formatDate(date, "YYYY-MM-DD");
        var index = selectedDates.indexOf(dateStr);

        if (index === -1) {
          selectedDates.push(dateStr);
        } else {
          selectedDates.splice(index, 1);
        }

        // Highlight selected dates
        renderCalendar();
      },

      // Apply custom rendering for days
      dayRender: function (date, cell) {
        var dateStr = $.fullCalendar.formatDate(date, "YYYY-MM-DD");

        // Check if date is in blocked dates
        if (blockedDates.indexOf(dateStr) !== -1) {
          cell.addClass("blocked-date");
        }

        // Check if date is in a blocked range
        var inRange = false;
        if (blockedRanges.length > 0) {
          blockedRanges.forEach(function (range) {
            var rangeStart = moment(range.start);
            var rangeEnd = moment(range.end);
            if (moment(dateStr).isBetween(rangeStart, rangeEnd, null, "[]")) {
              inRange = true;
            }
          });

          if (inRange) {
            cell.addClass("blocked-date");
          }
        }

        // Check if date is selected
        if (selectedDates.indexOf(dateStr) !== -1) {
          cell.addClass("fc-day-selected");
        }

        // Check if date has special price
        if (specialPrices[dateStr]) {
          cell.addClass("has-price");
          $(
            '<div class="date-price-indicator">' +
              specialPrices[dateStr] +
              "</div>"
          ).appendTo(cell);
        }
      },
    });
    
    // Button handlers
    
    // Block selected dates
    $('#block-dates-btn').click(function() {
        if (selectedDates.length === 0) {
            alert('Please select at least one date to block');
            return;
        }
        
        // Add to blocked dates array and store
        selectedDates.forEach(function(date) {
            if (blockedDates.indexOf(date) === -1) {
                blockedDates.push(date);
            }
        });
        
        // Update hidden input with pipe separated dates
        $('#fullcalendar-blocked-dates').val(blockedDates.join('|'));
        
        // Clear selection and re-render
        selectedDates = [];
        renderCalendar();
    });
    
    // Clear selected dates
    $('#clear-selection-btn').click(function() {
        selectedDates = [];
        renderCalendar();
    });
    
    // Set price for selected dates
    $('#set-price-btn').click(function() {
        if (selectedDates.length === 0) {
            alert('Please select at least one date to set price for');
            return;
        }
        
        // Show the price dialog
        $('#price-dialog').show();
    });
    
    // Block date range button
    $('#block-range-btn').click(function() {
        // Show the range dialog
        $('#range-dialog').show();
        
        // Initialize date pickers
        $('.date-picker').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0
        });
    });
    
    // Price dialog confirm button
    $('#price-confirm').click(function() {
        var price = $('#price-input').val();
        
        if (!price || isNaN(price) || price <= 0) {
            alert('Please enter a valid price');
            return;
        }
        
        // Add price to special prices object
        selectedDates.forEach(function(date) {
            specialPrices[date] = price;
        });
        
        // Update hidden input with JSON stringified special prices
        $('#fullcalendar-price-data').val(JSON.stringify(specialPrices));
        
        // Hide dialog and clear input
        $('#price-dialog').hide();
        $('#price-input').val('');
        
        // Clear selection and re-render
        selectedDates = [];
        renderCalendar();
    });
    
    // Price dialog cancel button
    $('#price-cancel').click(function() {
        $('#price-dialog').hide();
        $('#price-input').val('');
    });
    
    // Range dialog confirm button
    $('#range-confirm').click(function() {
        var startDate = $('#range-start').val();
        var endDate = $('#range-end').val();
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
        
        if (moment(startDate).isAfter(moment(endDate))) {
            alert('End date must be after start date');
            return;
        }
        
        // Add to blocked ranges
        blockedRanges.push({
            start: startDate,
            end: endDate
        });
        
        // Update hidden input with JSON stringified ranges
        $('#fullcalendar-blocked-ranges').val(JSON.stringify(blockedRanges));
        
        // Hide dialog and clear inputs
        $('#range-dialog').hide();
        $('#range-start').val('');
        $('#range-end').val('');
        
        // Re-render
        renderCalendar();
    });
    
    // Range dialog cancel button
    $('#range-cancel').click(function() {
        $('#range-dialog').hide();
        $('#range-start').val('');
        $('#range-end').val('');
    });
    
    // Initialize calendar based on existing data
    function initializeCalendar() {
        // Load blocked dates from input
        var datesValue = $('#fullcalendar-blocked-dates').val();
        if (datesValue) {
            blockedDates = datesValue.split('|').filter(Boolean);
        }
        
        // Load price data from input
        var pricesValue = $('#fullcalendar-price-data').val();
        if (pricesValue) {
            try {
                specialPrices = JSON.parse(pricesValue);
            } catch(e) {
                console.error('Error parsing price data:', e);
                specialPrices = {};
            }
        }
        
        // Load range data from input
        var rangesValue = $('#fullcalendar-blocked-ranges').val();
        if (rangesValue) {
            try {
                blockedRanges = JSON.parse(rangesValue);
            } catch(e) {
                console.error('Error parsing range data:', e);
                blockedRanges = [];
            }
        }
        
        renderCalendar();
    }
    
    // Re-render the calendar with updated data
    function renderCalendar() {
        calendar.fullCalendar('render');
    }
    
    // Style dialogs as modals
    $('#price-dialog, #range-dialog').dialog({
        autoOpen: false,
        modal: true,
        width: 350,
        resizable: false,
        draggable: false
    });
    
    // Initialize the calendar
    initializeCalendar();
});