/* ----------------- Start Document ----------------- */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // $( 'body' ).on( 'keyup', 'input[name=password]', function( event ) {
    //     $('.pwstrength_viewport_progress').addClass('password-indicator-visible');

    //   });

    document
      .querySelectorAll(".field__token")
      .forEach((element, index, array) => {
        element.addEventListener("input", function (event) {
          let inputValue = event.target.value;
          inputValue = inputValue.replace(/[^0-9]/g, "");
          inputValue = inputValue.slice(0, 1);
          event.target.value = inputValue;

          if (inputValue !== "") {
            // Move focus to the next input field
            if (index < array.length - 1) {
              array[index + 1].focus();
            }
          } else {
            // Move focus to the previous input field
            if (index > 0) {
              array[index - 1].focus();
            }
          }
        });

        // Add a blur event listener to handle cases where the user clicks or tabs away
        element.addEventListener("blur", function () {
          // If the input is empty, move focus to the previous input field
          if (element.value === "" && index > 0) {
            array[index - 1].focus();
          }
        });
      });
      
    $("input[name=password]").keypress(function () {
      $(".pwstrength_viewport_progress")
        .addClass("password-strength-visible")
        .animate({ opacity: 1 });
    });




    var options = {};
    options.ui = {
      //container: "#password-row",
      viewports: {
        progress: ".pwstrength_viewport_progress",
        //  verdict: ".pwstrength_viewport_verdict"
      },
      colorClasses: ["bad", "short", "normal", "good", "good", "strong"],
      showVerdicts: false,
      minChar: 8,
      //useVerdictCssClass
    };
    options.common = {
      debug: true,
      onLoad: function () {
        $("#messages").text("Start typing password");
      },
    };
    $(":password").pwstrength(options);

    // Real-time password strength validation
    // Use delegated event handlers to work on both static pages and AJAX popups
    if ($(".password-strength-requirements").length) {
      // Show tooltip on focus
      $(document).on("focus", "#password1", function () {
        var $passwordField = $(this);
        var $requirements = $passwordField.closest("form, .sign-in-form").find(".password-strength-requirements");

        var password = $passwordField.val();
        var hasLength = password.length >= 8;
        var hasUppercase = /[A-Z]/.test(password);
        var hasNumber = /[0-9]/.test(password);
        var hasSpecial = /[^\w]/.test(password);

        // Only show if not all requirements are met
        if (!(hasLength && hasUppercase && hasNumber && hasSpecial)) {
          $requirements.addClass("visible");
        }
      });

      // Hide tooltip on blur (when clicking outside)
      $(document).on("blur", "#password1", function () {
        var $passwordField = $(this);
        var $requirements = $passwordField.closest("form, .sign-in-form").find(".password-strength-requirements");
        $requirements.removeClass("visible");
      });

      // Update requirements on input
      $(document).on("input", "#password1", function () {
        var $passwordField = $(this);
        var $requirements = $passwordField.closest("form, .sign-in-form").find(".password-strength-requirements");

        var password = $passwordField.val();
        var hasLength = password.length >= 8;
        var hasUppercase = /[A-Z]/.test(password);
        var hasNumber = /[0-9]/.test(password);
        var hasSpecial = /[^\w]/.test(password);

        // Update requirement text with visual feedback
        $requirements.find(".req-length").css("color", hasLength ? "#66b574" : "#e74c3c");
        $requirements.find(".req-uppercase").css("color", hasUppercase ? "#66b574" : "#e74c3c");
        $requirements.find(".req-number").css("color", hasNumber ? "#66b574" : "#e74c3c");
        $requirements.find(".req-special").css("color", hasSpecial ? "#66b574" : "#e74c3c");

        // Hide tooltip when all requirements are met
        if (hasLength && hasUppercase && hasNumber && hasSpecial) {
          $requirements.removeClass("visible");
        }
      });
    }

    // Perform AJAX login on form submit
    
    // on click of button with name 'otp' hide the fields in form and show the input to type the OTP
   
    
    var interval;

    function startInterval() {
      $(".otp-countdown").removeClass("hidden");
      $(".otp-countdown-valid-text").removeClass("hidden");
      var timer2 = "5:01";
      interval = setInterval(function () {
        var timer = timer2.split(":");
        var minutes = parseInt(timer[0], 10);
        var seconds = parseInt(timer[1], 10);

        // If time has run out
        if (minutes === 0 && seconds === 0) {
          clearInterval(interval); // Stop the interval
          $("#resend_otp").removeClass("hidden");
          $(".otp-countdown").addClass("hidden");
          $(".otp-countdown-valid-text").addClass("hidden");
          return; // Exit the function
        }

        --seconds;
        minutes = seconds < 0 ? --minutes : minutes;
        if (minutes < 0) clearInterval(interval);
        seconds = seconds < 0 ? 59 : seconds;
        seconds = seconds < 10 ? "0" + seconds : seconds;
        $(".otp-countdown").html(minutes + ":" + seconds);
        timer2 = minutes + ":" + seconds;
      }, 1000);
    }



  $("#otp_submit").on("click", function (e) {
    // force form validation of certain fields
    e.preventDefault(); // prevent the form from submitting
    var otpType = $(".listeo-register-form-fields-container").data("otp-type");
    var formValid = true;

    if (otpType == "sms") {
      var phoneInput = document.getElementById("phone");
      var hasValidPhoneClass = phoneInput.classList.contains("validphone");
      if (!hasValidPhoneClass) {
        phoneInput.reportValidity();
        formValid = false;
        return;
      }
    } else if (otpType == "email") {
      // Validate email for Email OTP
      var emailInput = document.getElementById("email");
      if (!emailInput.checkValidity()) {
        emailInput.reportValidity();
        formValid = false;
      }
    }
    // Check required fields regardless of OTP type
    var requiredFields = document.querySelectorAll(
      "#register [required]:not(.field__token)"
    );
     requiredFields.forEach(function (field) {
       console.log(field);
       if (!field.checkValidity()) {
         // If the field is invalid, show the error message
         field.setCustomValidity("Please fill out this field.");
         field.reportValidity();
         formValid = false;
       }
     });

    if (!formValid) {
      return; // Stop execution if validation fails
    }
    // Send the OTP to the user

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo_login.ajaxurl,
      data: {
        action: "listeoajaxsendotp" + otpType,
        phone: $("form#register input[name='full_phone']").val(),
        email: $("form#register input[name='email']").val(),
        nonce: listeo_login.otp_nonce,
      },
      success: function (data) {
        console.log(data);
        if (data.success == true) {
          $("#otp_submit").addClass("hidden");
          $(".otp_registration-wrapper").show();
          // Start the interval when the page loads
          startInterval();
        } else {
          $(".otp_registration-wrapper").hide();
          $("#otp_submit").show();
        }
      },
    });
  });

  //resend the OTP
  $("#resend_otp").on("click", function (e) {
      e.preventDefault(); // prevent the form from submitting
      // if input with ID phone doesn't have class validphone, show error message

      var otpType = $(".listeo-register-form-fields-container").data(
        "otp-type"
      );
      if (otpType == "sms") {
        var phoneInput = document.getElementById("phone");
        var hasValidPhoneClass = phoneInput.classList.contains("validphone");
        if (!hasValidPhoneClass) {
          phoneInput.reportValidity();
          return;
        }
      } else if (otpType == "email") {
        // Validate email for Email OTP
        var emailInput = document.getElementById("email");
        if (!emailInput.checkValidity()) {
          emailInput.reportValidity();
          formValid = false;
        }
      }
    

    $.ajax({
      type: "POST",
      dataType: "json",
      url: listeo_login.ajaxurl,
      data: {
        action: "listeoajaxsendotp" + otpType,
        phone: $("form#register input[name='full_phone']").val(),
        email: $("form#register input[name='email']").val(),
        nonce: listeo_login.otp_nonce,
      },
      success: function (data) {
        console.log(data);
        if (data.success == true) {
          $("#otp_submit").hide();
          $("#resend_otp").addClass("hidden");
          $(".otp_registration-wrapper").show();
          clearInterval(interval); // Stop the current interval
          // Start the interval when the page loads
          startInterval();
        } else {
        }
      },
    });
    // Send the OTP to the user
  });

  });
})(this.jQuery);
