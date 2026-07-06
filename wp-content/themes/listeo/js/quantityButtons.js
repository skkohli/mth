/*----------------------------------------------------*/
/*  Quantity Buttons with Total Value Counter
/*
/*  Author: Vasterad
/*  Version: 1.0
/*----------------------------------------------------*/

jQuery(document).ready(function ($) {
  function qtySum() {
    // Get adults count from qtyInput
    var tot = parseInt($('input[name="qtyInput"]').val()) || 0;

    // Check if children input exists and add to total if it does
    var $childrenInput = $('input[name="childrenQtyInput"]');
    if ($childrenInput.length > 0) {
      tot += parseInt($childrenInput.val()) || 0;
    }

    var cardQty = $(".qtyTotal");
    cardQty.html(tot);
  }
  qtySum();

  $(".qtyButtons input").after('<div class="qtyInc"></div>');
  $(".qtyButtons input").before('<div class="qtyDec"></div>');

  $(".qtyDec, .qtyInc").on("click", function () {
    var $button = $(this);
    var oldValue = $button.parent().find("input").val();
    var max = $button.parent().find("input").data("max");
    var min = $button.parent().find("input").data("min");

    if ($button.hasClass("qtyInc")) {
      // Get the panel and its max guests limit
      var $panel = $button.closest(".panel-guests-dropdown");
      if ($panel.data("maxguests")) {
        var maxGuests = parseInt($panel.data("maxguests"));
      } else {
        var maxGuests = 999;
      }

      // Calculate current total guests (adults + children)
      var currentAdults = parseInt($('input[name="qtyInput"]').val()) || 0;
      var currentChildren =
        parseInt($('input[name="childrenQtyInput"]').val()) || 0;
      var totalGuests = currentAdults + currentChildren;

      // For adults or children, check if increasing would exceed maxGuests
      var canIncrease = true;
      if (
        $button
          .parent()
          .find("input")
          .is('[name="qtyInput"], [name="childrenQtyInput"]')
      ) {
        canIncrease = totalGuests < maxGuests;
      }

      if (canIncrease) {
        if (max) {
          if (oldValue < max) {
            var newVal = parseFloat(oldValue) + 1;
          } else {
            newVal = oldValue;
          }
        } else {
          var newVal = parseFloat(oldValue) + 1;
        }
      } else {
        newVal = oldValue;
      }
    } else {
      var inputName = $button.parent().find("input").attr("name");
      if (min) {
        if (oldValue > min) {
          var newVal = parseFloat(oldValue) - 1;
        } else {
          newVal = oldValue;
        }
      } else {
        if (inputName === "qtyInput") {
          newVal = oldValue > 1 ? parseFloat(oldValue) - 1 : 1;
        } else {
          newVal = oldValue > 0 ? parseFloat(oldValue) - 1 : 0;
        }
      }
    }

    $button.parent().find("input").val(newVal).trigger("change");
    qtySum();
    $(".qtyTotal").addClass("rotate-x");
  });

  // Total Value Counter Animation
  function removeAnimation() {
    $(".qtyTotal").removeClass("rotate-x");
  }

  const counter = $(".qtyTotal");
  counter.on("animationend", removeAnimation);

  // Adjusting Panel Dropdown Width
  $(window).on("load resize", function () {
    var panelTrigger = $(".booking-widget .panel-dropdown a");
    $(".booking-widget .panel-dropdown .panel-dropdown-content").css({
      width: panelTrigger.outerWidth(),
    });
  });
});
