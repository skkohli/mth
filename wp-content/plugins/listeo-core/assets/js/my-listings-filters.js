(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize Select2 on category dropdown if it exists
        if ($('#my-listings-category').length > 0) {
            // Check if Select2 is available
            if ($.fn.select2) {
                $('#my-listings-category').select2({
                    width: '100%',
                    minimumResultsForSearch: 10,
                    dropdownPosition: "below",
                    placeholder: $('#my-listings-category option:first').text()
                });
            }
        }

        // Handle Enter key in search input to submit form
        $('#my-listings-search').on('keypress', function(e) {
            if (e.which === 13) {
                $('#my-listings-search-form').submit();
            }
        });
    });

})(jQuery);