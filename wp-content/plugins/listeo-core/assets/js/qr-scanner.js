/* ----------------- Start Document ----------------- */
(function ($) {
  "use strict";

  $(document).ready(function () {
    $(document).on("click", "#mark-paid-btn", function (e) {
      e.preventDefault();
      var $this = $(this);
      //add class loading to the button
        $this.addClass("loading");
        var $success_message = $this.data("success");
        var status = "paid";

      // preparing data for ajax
      var ajax_data = {
        action: "listeo_bookings_manage",
        booking_id: $(this).data("booking"),
        status: status,
        //'nonce': nonce
      };
      $.ajax({
        type: "POST",
        dataType: "json",
        url: listeo.ajaxurl,
        data: ajax_data,

        success: function (data) {
            
            // remove class loading from the button
            $this.removeClass("loading");
            // display success message
            $this.html($success_message);
        },
      });
    });
  });
})(this.jQuery);
