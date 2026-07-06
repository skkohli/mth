/* ----------------- Start Document ----------------- */
(function ($) {
  "use strict";

  $(document).ready(function () {
    $(document).on("click", ".disconnect-stripe-button", function (e) {
      e.preventDefault();
      if (window.confirm(listeo_core.areyousure)) {
        var $this = $(this);
        

        // preparing data for ajax
        var ajax_data = {
          action: "listeo_disconnect_stripe",
          nonce: listeo_stripe.nonce,
        };
        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo.ajaxurl,
          data: ajax_data,

          success: function (data) {
            // display loader class
            location.reload();
          },
        });
      }
    });
  });
})(this.jQuery);
