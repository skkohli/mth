 jQuery(document).ready(function($) {
    // Form submission
    $('#report-listing-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        var notification = form.siblings('.notification');

        submitButton.prop('disabled', true).text('Processing...');

        $.ajax({
            url: listeo_core.ajax_url,
            type: 'POST',
            data: {
                action: 'listeo_report_listing',
                listing_id: form.find('input[name="listing_id"]').val(),
                report_reason: form.find('select[name="report_reason"]').val(),
                report_description: form.find('textarea[name="report_description"]').val(),
                report_nonce: form.find('input[name="report_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    notification.removeClass('error').addClass('success').text(response.data.message).show();
                    setTimeout(function() {
                        $(".mfp-close").trigger("click");
                        form[0].reset();
                        notification.hide();
                    }, 2000);
                } else {
                    notification.removeClass('success').addClass('error').text(response.data.message).show();
                    setTimeout(function() {
                        notification.hide();
                    }, 2000);
                }
            },
            error: function() {
                notification.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Submit Report');
                    
            }
        });
    });
});