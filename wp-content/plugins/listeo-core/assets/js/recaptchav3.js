	/* ----------------- Start Document ----------------- */
(function($) {


   	window.getRecaptcha = function() {
    grecaptcha.ready(function() {
        grecaptcha.execute(listeo_core.recaptcha_sitekey3, {action: 'login'}).then(function(token) {
            $('.listeo-registration-form #token').val(token);
            $("#booking-confirmation #token").val(token);
            $("#claim-dialog #token").val(token);
        });
    });
	}
	
	$(document).ready(function(){ 
	    if(listeo_core.recaptcha_status){
	        if(listeo_core.recaptcha_version == 'v3'){
	            getRecaptcha();   
				     
	        }
	    }
	});
    
// ------------------ End Document ------------------ //


})(jQuery);