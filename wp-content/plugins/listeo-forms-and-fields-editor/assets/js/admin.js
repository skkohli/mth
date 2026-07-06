(function ( $ ) {
	"use strict";

	$(function () {
    $("#listeo-fafe-fields-editor").sortable({
      items: ".form_item",
      handle: ".handle",
      cursor: "move",
      containment: "parent",
      placeholder: "my-placeholder",
      start: function (event, ui) {
        // Set the initial width of the placeholder to match the helper
        ui.placeholder.width(ui.item.outerWidth() - 2);
      },
      /*stop: function(event, ui) {
		        $(".form_item").each(function(i, el){
		        	
		            $(this).find('input').attr('name').replace(/\d+/, $(el).index())
		             
		        });
		    }*/
    });

    $(".field-options-custom tbody").sortable();

    $(".listeo-forms-builder").on(
      "click",
      ".listeo-fafe-section-move-down",
      function (event) {
        event.preventDefault();
        var section = $(this).parents(".listeo-fafe-row-section");
        var next = $(this).parents(".listeo-fafe-row-section").next();
        section.insertAfter(next);
      }
    );

    $(".listeo-forms-builder").on(
      "click",
      ".listeo-fafe-section-move-up",
      function (event) {
        event.preventDefault();
        var section = $(this).parents(".listeo-fafe-row-section");
        var prev = $(this).parents(".listeo-fafe-row-section").prev();
        section.insertBefore(prev);
      }
    );

    $("#listeo-fafe-forms-editor,#listeo-fafe-forms-editor-adv").sortable({
      items: ".form_item",
      handle: ".handle",
      cursor: "move",
      containment: "parent",
      placeholder: "my-placeholder",
      connectWith: "#listeo-fafe-forms-editor,#listeo-fafe-forms-editor-adv",
      stop: function (event, ui) {
        $(".form_item").each(function (i, el) {
          $(this).find(".priority_field").val($(el).index());
        });
      },
      receive: function (e, ui) {
        ui.sender.data("copied", true);
        console.log(ui);
      },
    });

    function randomIntFromInterval(min, max) {
      return Math.floor(Math.random() * (max - min + 1) + min);
    }

    $(".form-editor-available-elements-container").sortable({
      items: ".form_item",
      handle: ".handle",
      connectWith: ".form-editor-container",
      helper: function (e, li) {
        if (li.hasClass("form_item_header")) {
          var copy = li.clone();
          var formRowCount =
            $("#listeo-fafe-forms-editor .form_item").length + 25;
          $(".name-container input", copy).val(
            "header" + randomIntFromInterval(20, 990)
          );
          $("input", copy)
            .attr("name")
            .replace(/^(\[)\d+(\].+)$/, "$1" + formRowCount + "$2");
          copy.find("input,select").each(function () {
            var $this = $(this);

            $this.attr(
              "name",
              $this.attr("name").replace(/\[(\d+)\]/, "[" + formRowCount + "]")
            );
          });
          formRowCount++;
          this.copyHelper = copy.insertAfter(li);
          $(this).data("copied", false);
          return li.clone();
        } else {
          return li.data("copied", true);
        }
      },
      stop: function (event, ui) {
        var copied = $(this).data("copied");

        if (!copied) {
          this.copyHelper.remove();
        }

        this.copyHelper = null;
        $(".form_item").each(function (i, el) {
          var i = $(el).index();
          if ($(el).parent().hasClass("adv")) {
            if ($(el).parent().hasClass("panel")) {
              $(this).find(".place_hidden").val("panel");
            } else {
              $(this).find(".place_hidden").val("adv");
            }
          } else {
            $(this).find(".place_hidden").val("main");
          }
          if ($(this).find(".priority_field").lenght > 0) {
            $(this)
              .find(".priority_field")
              .attr("name")
              .replace(/(\[\d\])/, "[" + $(el).index() + "]");
          }
        });
      },
    });

    $(".listeo-forms-builder,.listeo-forms-builder-right").on(
      "click",
      "#listeo-show-names",
      function () {
        $(".name-container").show();
      }
    );

    $(".form-editor-container").on("click", ".element_title", function () {
      $(this).next().slideToggle();
    });

    $(".listeo-forms-builder,.listeo-form-editor").on(
      "click",
      ".remove_item",
      function (event) {
        event.preventDefault();
        if (window.confirm("Are you sure?")) {
          $(this)
            .parent()
            .fadeOut(300, function () {
              $(this).remove();
            });
        }
      }
    );

    $(".field-options-custom").on("click", ".remove_row", function (event) {
      event.preventDefault();
      if (window.confirm("Are you sure?")) {
        $(this)
          .parent()
          .fadeOut(300, function () {
            $(this).remove();
          });
      }
    });

    /*fields editor*/
    $(
      "#listeo-fafe-fields-editor, #listeo-fafe-forms-editor,#listeo-fafe-forms-editor-adv"
    )
      .on("init", function () {
        $(".step-error-too-many").hide();
        $(".step-error-exceed").hide();
        $(this).find(".field-type-selector").change();
        $(this).find(".field-type select").change();
        $(this).find(".field-edit-class-select").change();
        $(this).find(".field-options-data-source-choose").change();
      })
      .on("change", ".field-type select", function () {
        $(this).parent().parent().find(".field-options").hide();
        $(this).parent().parent().find(".field-display-as-list").hide();

        var type = $(this).val();

        switch (type) {
          case "file":
          case "repeatable":
          case "textarea":
          case "datetime":
              $(this)
                .parent()
                .parent()
                .find('.field-addtosearch').addClass("disabled")
                .find("input").prop("disabled", false).prop("checked", false)
                
            break;
          default:
              $(this)
                .parent()
                .parent()
                .find(".field-addtosearch")
                .removeClass("disabled");
              $(this)
                .parent()
                .parent()
                .find(".field-addtosearch input")
                .prop("disabled", false);
            
        }


        if (
          "repeatable" === $(this).val() ||
          "select" === $(this).val() ||
          "select" === $(this).val() ||
          "select_multiple" === $(this).val() ||
          "multicheck_split" === $(this).val() ||
          "radio" === $(this).val()
        ) {
          $(this).parent().parent().find(".field-options").show();
          $(this).parent().parent().find(".field-display-as-list").show();
        }
      })
      .on("change", ".field-options-data-source-choose", function () {
        if ("predefined" === $(this).val()) {
          $(this).parent().find(".field-options-predefined").show();
          $(this).parent().find(".field-options-custom").hide();
        }
        if ("custom" === $(this).val()) {
          $(this).parent().find(".field-options-predefined").hide().val("");
          $(this).parent().find(".field-options-custom").show();
        }
        if ("" === $(this).val()) {
          $(this).parent().find(".field-options-predefined").hide().val("");
          $(this).parent().find(".field-options-custom").hide();
        }
      })
      .on("change", ".field-edit-class-select", function () {
        if ("col-md-12" === $(this).val()) {
          $(this)
            .parent()
            .parent()
            .find(".open_row-container")
            .hide()
            .find("input")
            .prop("checked", true);
          $(this)
            .parent()
            .parent()
            .find(".close_row-container")
            .hide()
            .find("input")
            .prop("checked", true);
        } else {
          $(this).parent().parent().find(".open_row-container").show();
          $(this).parent().parent().find(".close_row-container").show();
        }
        if ("" === $(this).val()) {
          $(this)
            .parent()
            .parent()
            .find(".open_row-container")
            .hide()
            .find("input")
            .prop("checked", false);
          $(this)
            .parent()
            .parent()
            .find(".close_row-container")
            .hide()
            .find("input")
            .prop("checked", false);
        }
        if ("custom" === $(this).val()) {
          $(this).parent().find(".field-options-predefined").hide().val("");
          $(this).parent().find(".field-options-custom").show();
        }
      })
      .on("click", ".remove-row", function (e) {
        e.preventDefault();

        if (window.confirm("Are you sure?")) {
          $(this)
            .parent()
            .parent()
            .fadeOut(300, function () {
              $(this).remove();
            });
        }
      })
      // .on("click", ".add-new-option-table", function (e) {
      //   e.preventDefault();
      //   var $tbody = $(this).closest("table").find("tbody");
      //   var row = $tbody.data("field");
      //   row = row.replace(/\[-1\]/g, "[" + $tbody.find("tr").size() + "]");
      //   $tbody.append(row);
      // })
      .on("change", ".step-container", function () {
        var form = $(this).parent().parent();
        var max = form.find(".max-container input").val();
        var min = form.find(".min-container input").val();
        var step = $(this).find("input").val();
        $(".step-error-too-many").hide();
        $(".step-error-exceed").hide();
        if (step > max - min) {
          form.find(".step-error-exceed").show();
        }
        var offset = 0;
        var len = (Math.abs(max - min) + (offset || 0) * 2) / (step || 1) + 1;

        if (len > 30) {
          form.find(".step-error-too-many").show();
        }
      })
      .on("change", ".min-container", function () {
        var form = $(this).parent().parent();
        var max = form.find(".max-container input").val();
        var min = form.find(".min-container input").val();
        var step = $(this).find("input").val();
        $(".step-error-too-many").hide();
        $(".step-error-exceed").hide();
        if (step > max - min) {
          form.find(".step-error-exceed").show();
        }
        var offset = 0;
        var len = (Math.abs(max - min) + (offset || 0) * 2) / (step || 1) + 1;
        console.log(len);
        if (len > 30) {
          form.find(".step-error-too-many").show();
        }
      })
      .on("change", ".max-container", function () {
        var form = $(this).parent().parent();
        var max = form.find(".max-container input").val();
        var min = form.find(".min-container input").val();
        var step = $(this).find("input").val();
        $(".step-error-too-many").hide();
        $(".step-error-exceed").hide();
        if (step > max - min) {
          form.find(".step-error-exceed").show();
        }
        var offset = 0;
        var len = (Math.abs(max - min) + (offset || 0) * 2) / (step || 1) + 1;
        console.log(len);
        if (len > 30) {
          form.find(".step-error-too-many").show();
        }
      })
      .on("change", ".field-type-selector", function () {
        var form = $(this).parent().parent();
        var type = $(this).val();

        switch (type) {
          case "file":
          case "repeatable":
          case "textarea":
          case "datetime":
            form.find(".field-addtosearch").addClass("disabled");
            break;
          default:
            form.find(".field-addtosearch").removeClass("disabled");
            form.find(".field-addtosearch input").prop("disabled", false).prop("checked", false);
            form.find(".field-addtosearch").css({
              opacity: 1,
              "pointer-events": "auto",
            });
        }

        // Hide drilldown-only options by default, show in drilldown cases
        form.find(".hide-all-container").hide();

        switch (type) {
          case "select":
          case "radio":
          case "multicheck_split":
          case "multi-select":
            form.find(".options-container").show();
            form.find(".multi-container").show();
            form.find(".max-container").hide();
            form.find(".min-container").hide();
            form.find(".step-container").hide();
            form.find(".unit-container").hide();
            form.find(".taxonomy-container").hide();
            break;
          case "select-taxonomy":
          case "term-select":
            form.find(".multi-container").show();
            form.find(".taxonomy-container").show();
            form.find(".options-container").hide();
            form.find(".max-container").hide();
            form.find(".min-container").hide();
            form.find(".step-container").hide();
            form.find(".unit-container").hide();
            break;
          case "drilldown-taxonomy":
            form.find(".multi-container").show();
            form.find(".hide-all-container").show();
            form.find(".max-container").hide();
            form.find(".min-container").hide();

            form.find(".step-container").hide();
            form.find(".unit-container").show();
            form.find(".options-container").hide();
            form.find(".taxonomy-container").hide();
            break;
          case "drilldown-listing-types":
            form.find(".multi-container").show();
            form.find(".hide-all-container").show();
            form.find(".max-container").hide();
            form.find(".min-container").hide();
            form.find(".step-container").hide();
            form.find(".unit-container").hide();
            form.find(".options-container").hide();
            form.find(".taxonomy-container").hide();
            break;
          case "input-select":
          case "slider":
          case "double-input":
            form.find(".options-container").hide();
            form.find(".multi-container").hide();
            form.find(".max-container").show();
            form.find(".min-container").show();
            form.find(".step-container").show();
            form.find(".unit-container").show();
            break;
          case "multi-checkbox":
          case "multi-checkbox-row":
            form.find(".options-container").show();
            form.find(".taxonomy-container").show();
            form.find(".multi-container").hide();
            form.find(".max-container").hide();
            form.find(".min-container").hide();
            form.find(".step-container").hide();
            form.find(".unit-container").hide();
            break;
          case "header":
            form.find(".max-container").hide();
            form.find(".min-container").hide();
            form.find(".multi-container").hide();
            form.find(".step-container").hide();
            form.find(".unit-container").hide();
            form.find(".options-container").hide();
            form.find(".taxonomy-container").hide();
            break;
          case "radius":
            form.find(".max-container").show();
            form.find(".min-container").show();

            form.find(".multi-container").hide();
            form.find(".step-container").hide();
            form.find(".unit-container").hide();
            form.find(".options-container").hide();
            form.find(".taxonomy-container").hide();
            break;
          default:
            form.find(".max-container").hide();
            form.find(".min-container").hide();
            form.find(".multi-container").hide();
            form.find(".step-container").hide();
            form.find(".unit-container").show();
            form.find(".options-container").hide();
            form.find(".taxonomy-container").hide();
        }

        // Does some stuff and logs the event to the console
      });
    // Function to convert name to slug format
    function nameToSlug(name) {
      return name
        .toLowerCase() // Convert to lowercase
        .trim() // Remove leading/trailing spaces
        .replace(/[^\w\s-]/g, "") // Remove special characters except spaces and hyphens
        .replace(/[\s_-]+/g, "-") // Replace spaces, underscores, and multiple hyphens with single hyphen
        .replace(/^-+|-+$/g, ""); // Remove leading/trailing hyphens
    }

    // Event handler for name field input
    $(document).on("input", "input.input-value", function () {
      var $nameInput = $(this);
      var $row = $nameInput.closest("tr");
      var $valueInput = $row.find("input.input-name");
      // Find the field id from the closest .form_item
      var $formItem = $row.closest(".form_item");
      var fieldId = $formItem.find("p.field-id input").val() || "";
      // Only auto-generate if the value field hasn't been manually edited
      if (!$valueInput.data("manually-edited")) {
      var slug = nameToSlug($nameInput.val());
        $valueInput.val(fieldId + '_' + slug);
      }
    });

    // Track manual edits to value field
    $(document).on("input", "input.input-name", function () {
      $(this).data("manually-edited", true);
      $(this).removeClass("auto-generated").addClass("manually-edited");
    });

    // Style value fields to look disabled but keep them functional
    $(document).on("focus", "input.input-name", function () {
      // Add a subtle visual indication when focused
      $(this).addClass("value-field-focused");
    });

    $(document).on("blur", "input.input-name", function () {
      $(this).removeClass("value-field-focused");
    });

    // Initialize existing name fields
    $(document).ready(function () {
      $("input.input-name").each(function () {
        $(this).addClass("auto-generated-style");
      });

      // Initialize fontIconPicker on existing option icon selects
      $(".listeo-option-icon-select").each(function () {
        if (!$(this).data("fontIconPicker")) {
          $(this).fontIconPicker({
            iconsPerPage: 32,
            emptyIcon: false,
          });
        }
      });
    });

    // Update your existing add row function
    $(".listeo-form-editor,.listeo-editor-wrap").on("click", ".add-new-option-table", function (e) {
      e.preventDefault();
      var $tbody = $(this).closest("table").find("tbody");
      var row = $tbody.data("field");
      row = row.replace(/\[-1\]/g, "[" + $tbody.find("tr").size() + "]");
      $tbody.append(row);

      // Style the newly added value field
      var $newRow = $tbody.find("tr").last();
      var $valueInput = $newRow.find("input[name*='[name]']");
      $valueInput.addClass("auto-generated-style");

      // Initialize fontIconPicker on the new row's option icon select
      $newRow.find(".listeo-option-icon-select").each(function () {
        if (!$(this).data("fontIconPicker")) {
          $(this).fontIconPicker({
            iconsPerPage: 32,
            emptyIcon: false,
          });
        }
      });
    });

    $("#listeo-fafe-fields-editor").trigger("init");
    $("#listeo-fafe-forms-editor").trigger("init");
    $("#listeo-fafe-forms-editor-adv").trigger("init");

    let fieldClone = $("#listeo-fafe-fields-editor").data("clone");

    // Open the Add Field modal
    $(".listeo-forms-builder-top").on("click", ".add-field", function (e) {
      e.preventDefault();
      // Clear any previous value
      document.getElementById("new-field-name").value = "";
      MicroModal.show("listeo-add-field-modal");
    });

    // Handle Add Field modal form submission
    const form = document.getElementById("listeo-add-field-form");
    if (form) {
      document
        .getElementById("listeo-add-field-form")
        .addEventListener("submit", function (e) {
          e.preventDefault();
          const name = document.getElementById("new-field-name").value.trim();

          if (name.length < 2) {
            alert("Please enter a valid field name.");
            return;
          }
            const id = string_to_slug(name);
            const index = $(".form_item").length + 1;

          let clone = fieldClone
            .replace(/\[-2\]/g, "[" + index + "]")
            .replace(/clone/g, name);

          $("#listeo-fafe-fields-editor").append(clone);
          const $lastItem = $(
            "#listeo-fafe-fields-editor .form_item:last-child"
          );
          $lastItem.find(".edit-form-field").toggle();
          $lastItem.find(".field-id input").val("_" + id);
          $lastItem.find(".field-options").hide();
          $lastItem.find(".field-display-as-list").hide();

          MicroModal.close("listeo-add-field-modal");
          $(".listeo-icon-select, .listeo-option-icon-select").each(function () {
            // Check if this element already has the plugin initialized
            if (!$(this).data("fontIconPicker")) {
              $(this).fontIconPicker({
                iconsPerPage: 32,
                emptyIcon: false,
              });
            }
          });
        });
    }
    // Open modal on click
    $(".listeo-forms-builder-top").on("click", ".add-headline", function (e) {
      e.preventDefault();
      $("#new-headline-title").val(""); // Clear previous value
      MicroModal.show("listeo-add-headline-modal");
    });

    // Handle headline form submission
    $("#listeo-add-headline-form").on("submit", function (e) {
      e.preventDefault();

      var name = $.trim($("#new-headline-title").val());
      if (name.length < 2) {
        alert("Please enter a valid headline title.");
        return;
      }

      var fieldClone = $("#listeo-fafe-fields-editor").data("clone");
      var id = string_to_slug(name);
      var index = $(".form_item").length + 1;

      var clone = fieldClone
        .replace(/\[-2\]/g, "[" + index + "]")
        .replace(/clone/g, name);

      $("#listeo-fafe-fields-editor").append(clone);

      var $last = $("#listeo-fafe-fields-editor .form_item:last-child");
      var $edit = $last.find(".edit-form-field");

      $edit.toggle();
      $edit.find(".field-id input").val("_" + id);

      // Hide everything not needed for headlines
      $edit.find(".field-options").hide();
      $edit.find(".field-display-as-list").hide();
      $edit.find(".field-required").hide();
      $edit.find(".invert-container").hide();
      $edit.find(".listeo-editor-placeholder-field").hide();
      $edit.find(".listeo-editor-css-field").hide();
      $edit.find(".field-type").hide();
      $edit.find(".listeo-editor-default-field").hide();
      $edit.find(".listeo-editor-width-field").hide();

      // set the class to form_item_type_header
      $last.addClass("form_item_type_header");
      // Replace select with hidden input to force "header" type
      $last
        .find(".field-type select")
        .replaceWith(
          '<input type="hidden" name="type[' + index + ']" value="header" />'
        );
      $(".listeo-icon-select, .listeo-option-icon-select").each(function () {
        // Check if this element already has the plugin initialized
        if (!$(this).data("fontIconPicker")) {
          $(this).fontIconPicker({
            iconsPerPage: 32,
            emptyIcon: false,
          });
        }
      });
      MicroModal.close("listeo-add-headline-modal");
    });

    // $(".listeo-forms-builder-top").on("click", ".add-headline", function (e) {
    //   e.preventDefault();
    //   var name;
    //   do {
    //     name = prompt("Please enter headline title");
    //   } while (name.length < 2);
    //   var clone = $("#listeo-fafe-fields-editor").data("clone");
    //   var id = string_to_slug(name);
    //   var index = $(".form_item").size() + 1;
    //   clone = clone
    //     .replace(/\[-2\]/g, "[" + index + "]")
    //     .replace(/clone/g, name);
    //   $("#listeo-fafe-fields-editor").append(clone);
    //   $("#listeo-fafe-fields-editor .form_item:last-child .edit-form-field")
    //     .toggle()
    //     .find(".field-id input")
    //     .val("_" + id);
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .field-options"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .field-required"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .invert-container"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .field-options"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .listeo-editor-placeholder-field"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .listeo-editor-css-field"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .field-type"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .listeo-editor-default-field"
    //   ).hide();
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .edit-form-field .listeo-editor-width-field"
    //   ).hide();
    //   // the field-type select has to be switchet to hidden text input with value "header"
    //   $(
    //     "#listeo-fafe-fields-editor .form_item:last-child .field-type select"
    //   ).replaceWith(
    //     '<input type="text" name="type[' + index + ']" value="header" />'
    //   );
    // });

    $(".listeo-form-editor table")
      .on("click", ".add-new-main-option", function (e) {
        e.preventDefault();
        var $tbody = $(this).closest("table").find("tbody");
        var row = $tbody.data("field");

        row = row.replace(/\[-1\]/g, "[" + $tbody.find("tr").size() + "]");

        $tbody.append(row);
      })
      .on("click", ".remove-row", function (e) {
        e.preventDefault();

        if (window.confirm("Are you sure?")) {
          $(this)
            .parent()
            .parent()
            .fadeOut(300, function () {
              $(this).remove();
            });
        }
      });

    function string_to_slug(str) {
      str = str.replace(/^\s+|\s+$/g, ""); // trim
      str = str.toLowerCase();

      // remove accents, swap ñ for n, etc
      var from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
      var to = "aaaaeeeeiiiioooouuuunc------";
      for (var i = 0, l = from.length; i < l; i++) {
        str = str.replace(new RegExp(from.charAt(i), "g"), to.charAt(i));
      }

      str = str
        .replace(/[^a-z0-9 -]/g, "") // remove invalid chars
        .replace(/\s+/g, "_") // collapse whitespace and replace by -
        .replace(/-+/g, "_"); // collapse dashes

      return str;
    }

    // Submit Form Editor

    $(".row-container")
      .sortable({
        items: ".editor-block:not(.preconfigured-field)",
        // handle: '.handle',
        cursor: "move",
        connectWith: ".row-container:not(.preconfigured-section .row-container)",
        placeholder: "my-placeholder",
        /*stop: function(event, ui) {
		        $(".form_item").each(function(i, el){
		            $(this).find('input').attr('name').replace(/\d+/, $(el).index())
		        });
		    }*/
        receive: function (event, ui) {
          // Prevent dropping fields into preconfigured sections
          if ($(this).closest(".preconfigured-section").length) {
            ui.sender.sortable("cancel");
            return;
          }
        },
        update: function (event, ui) {
          if (ui.sender) {
            var section_old = ui.sender.data("section");
            var section_new = $(this).data("section");

            $(ui.item)
              .find("input,select")
              .each(function () {
                var newname = this.name.replace(section_old, section_new);
                this.name = newname;
                //$(this).attr('name',newname);
              });
          }
        },
        start: function (event, ui) {
          // Set the initial width of the placeholder to match the helper
          ui.placeholder.width(ui.item.outerWidth() - 2);
        },
        // stop: function(event, ui) {

        //     // $(".form_item").each(function(i, el){
        //     //     $(this).find('.priority_field').val( $(el).index() );
        //     // });
        // },
        // receive: function (e, ui) {
        //     ui.sender.data('copied', true);
        // }
      })
      .disableSelection();

    var widths = [
      "block-width-3",
      "block-width-4",
      "block-width-6",
      "block-width-12",
    ];
    var widths_nr = ["3", "4", "6", "12"];
    $(".form-editor-container").on("click", ".block-wider a", function (e) {
      e.preventDefault();
      var className = $(this)
        .parents()
        .eq(3)
        .attr("class")
        .match(/block-width-\d+/);
      if (className) {
        var cur_width_index = widths.indexOf(className[0]);
        console.log(cur_width_index);
        if (cur_width_index < 3) {
          console.log($(this).parents(".editor-block"));
          $(this)
            .parents(".editor-block")
            .removeClass(widths[cur_width_index])
            .addClass(widths[cur_width_index + 1]);
          $(this)
            .parents(".editor-block")
            .find(".block-width-input")
            .val(widths_nr[cur_width_index + 1]);
        }
      }
    });
    $(".form-editor-container").on("click", ".block-narrower a", function (e) {
      e.preventDefault();
      var className = $(this)
        .parents()
        .eq(3)
        .attr("class")
        .match(/block-width-\d+/);
      if (className) {
        var cur_width_index = widths.indexOf(className[0]);
        console.log(cur_width_index);
        if (cur_width_index > 0) {
          console.log($(this).parents(".editor-block"));
          $(this)
            .parents(".editor-block")
            .removeClass(widths[cur_width_index])
            .addClass(widths[cur_width_index - 1]);
          $(this)
            .parents(".editor-block")
            .find(".block-width-input")
            .val(widths_nr[cur_width_index - 1]);
        }
      }
    });

    $(".form-editor-container").on("click", ".block-edit a", function (e) {
      var form_fields;
      e.preventDefault();
      $(".listeo-editor-modal-title").html("Edit Field");
      $(".listeo-editor-modal-footer .button-primary").html("Save Field");
      form_fields = $(this)
        .parents(".editor-block")
        .find(".editor-block-form-fields")
        .addClass("edited-now")
        .html();
      $(".listeo-modal-form").html(form_fields);

      $(".edited-now")
        .find("select")
        .each(function (i) {
          var value = $(this).val();
          console.log(value);
          $(".listeo-modal-form").find("select").eq(i).val(value);
        });
      $(".edited-now")
        .find('input[type="checkbox"]')
        .each(function (i) {
          if ($(this).is(":checked")) {
            $(".listeo-modal-form")
              .find('input[type="checkbox"]')
              .eq(i)
              .prop("checked", true);
          } else {
            $(".listeo-modal-form")
              .find('input[type="checkbox"]')
              .eq(i)
              .prop("checked", false);
          }
        });

      $(".listeo-editor-modal").show();
    });

    $(".form-editor-container").on("click", ".block-delete a", function (e) {
      $(this).parents(".editor-block").remove();
      e.preventDefault();
    });

    $(".form-editor-container").on("click", ".block-add-new a", function (e) {
      e.preventDefault();
      $(".listeo-editor-modal-title").html("Add New Field");
      $(".listeo-editor-modal-footer .button-primary").html("Add Field");
      var section = $(this).data("section");
      var tab = $("#mainform").data("tab");
      var ajax_data = {
        action: "listeo_editor_get_items",
        section: section,
        tab: tab,
        //'nonce': nonce
      };

      $.ajax({
        type: "POST",
        dataType: "json",
        url: ajaxurl,
        data: ajax_data,

        success: function (data) {
          console.log(data);
          var content =
            '<div class="modal-search-container">' +
            '<input type="text" id="modal-element-search" placeholder="Search elements..." class="modal-element-search-input">' +
            "</div>" +
            data.data.items;

          $(".listeo-modal-form").html(content);
          initModalSearch();
          $(".listeo-editor-modal").show();
        },
      });
    });

    $(".listeo-modal-close, .listeo-cancel").on("click", function (e) {
      e.preventDefault();
      $(".listeo-editor-modal").hide();
      $(".listeo-modal-form").html("");
      $(".editor-block-form-fields").removeClass("edited-now");
    });

    // Function to initialize the search functionality
    function initModalSearch() {
      $("#modal-element-search").on("input", function () {
        var searchTerm = $(this).val().toLowerCase();

        $(
          ".listeo-modal-form .listeo-fafe-forms-editor-new-elements-container"
        ).each(function () {
          var elementTitle = $(this).find(".insert-field").text().toLowerCase();

          if (elementTitle.includes(searchTerm)) {
            $(this).show();
          } else {
            $(this).hide();
          }
        });
      });
    }

    $("#listeo-save-field").on("click", function (e) {
      e.preventDefault();

      $(".listeo-modal-form input").each(function () {
        $(this).attr("value", $(this).val());
      });

      var new_fields = $(".listeo-modal-form").html();
      $(".edited-now").html(new_fields);

      $(".listeo-modal-form")
        .find('input[type="checkbox"]')
        .each(function (i) {
          if ($(this).is(":checked")) {
            $(".edited-now")
              .find('input[type="checkbox"]')
              .eq(i)
              .prop("checked", true);
          } else {
            $(".edited-now")
              .find('input[type="checkbox"]')
              .eq(i)
              .prop("checked", false);
          }
        });

      $(".listeo-modal-form")
        .find("select")
        .each(function (i) {
          var value = $(this).val();
          $(".edited-now").find("select").eq(i).val(value);
        });

      $(".listeo-editor-modal").hide();
      //$('.listeo-modal-form').html('');
      $(".editor-block-form-fields").removeClass("edited-now");
      $(".section_options").removeClass("edited-now");
    });

    $(".listeo-modal-form").on("click", ".insert-field", function (e) {
      e.preventDefault();
      var section = $(this).data("section");
      var field = $(this).parent().find(".editor-block").clone();

      field.show();

      // console.log(section);
      // console.log($("div").find("[data-section='" + section + "']"));
      //$(".row-"+section).append(field).show();
      // $(field).appendTo($(".row-"+section)).show();
      // $("TEEST").appendTo($(".row-"+section));s

      if ($(".row-" + section + " .editor-block").length > 0) {
        $(".row-" + section + " .editor-block:last").after(field);
      } else {
        $(".row-" + section + "").append(field);
      }

      $(".row-container").sortable("refresh");
      $(".listeo-editor-modal").hide();
    });

    $(".form-editor-container").on(
      "click",
      ".listeo-fafe-section-edit",
      function (e) {
        e.preventDefault();
        var form_fields;
        $(".listeo-editor-modal-title").html("Edit Section");
        $(".listeo-editor-modal-footer .button-primary").html("Save Changes");
        form_fields = $(this)
          .parent()
          .parent()
          .find(".section_options")
          .addClass("edited-now")
          .html();

        $(".listeo-modal-form").html(form_fields);

        $(".edited-now")
          .find("select")
          .each(function (i) {
            var value = $(this).val();
            $(".listeo-modal-form").find("select").eq(i).val(value);
          });

        $(".edited-now")
          .find('input[type="checkbox"]')
          .each(function (i) {
            if ($(this).is(":checked")) {
              $(".listeo-modal-form")
                .find('input[type="checkbox"]')
                .eq(i)
                .prop("checked", true);
            } else {
              $(".listeo-modal-form")
                .find('input[type="checkbox"]')
                .eq(i)
                .prop("checked", false);
            }
          });

        $(".listeo-editor-modal").show();
      }
    );

    $(".listeo-fafe-new-section").on("click", function (e) {
      e.preventDefault();
      var name;
      do {
        name = prompt("Please enter section name");
      } while (name.length < 2);
      var clone = $(".form-editor-container").data("section");
      var id = string_to_slug(name);

      clone = clone.replace("{section_org}", name).replace(/{section}/g, id);

      $(".form-editor-container").append(clone);
      // 🟡 Ensure the new section has a unique selector
      $(".form-editor-container .listeo-fafe-row-section")
        .last()
        .attr("data-selector", ".section_" + id);

      $(".row-container").sortable();

      // 🟢 Trigger custom event so step dropdowns can refresh
      $(".form-editor-container").trigger("sectionAdded");
      //$('#listeo-fafe-fields-editor .form_item:last-child .edit-form-field').toggle().find('.field-id input').val('_'+id);
    });

    $(".form-editor-container").on("sectionAdded sectionRemoved", function () {
      updateStepSelectors();
    });

  $(".form-editor-container").on(
    "click",
    ".listeo-fafe-section-remove-section",
    function (e) {
      e.preventDefault();

      var $section = $(this).parents(".listeo-fafe-row-section");
      var sectionSelector = $section.data("selector");

      // Define critical booking-related sections
      var bookingSections = [
        ".booking",
        ".menu",
        ".slots",
        ".availability_calendar",
      ];
      var isBookingSection = bookingSections.includes(sectionSelector);

      if (isBookingSection) {
        // First confirmation for booking sections
        var sectionName = getSectionDisplayName(sectionSelector);
        var firstConfirm = window.confirm(
          "⚠️ WARNING: You are about to remove a booking-related section (" +
            sectionName +
            ").\n\n" +
            "This section is critical for booking functionality and cannot be easily recreated later.\n\n" +
            "Are you sure you want to continue?"
        );

        if (!firstConfirm) {
          return; // User cancelled
        }

        // Second confirmation for booking sections
        var secondConfirm = window.confirm(
          "🚨 FINAL WARNING!\n\n" +
            "Removing the '" +
            sectionName +
            "' section will:\n" +
            "• Break booking functionality for this listing type\n" +
            "• Require manual recreation of complex booking fields\n" +
            "• Potentially cause issues with existing listings\n\n" +
            "This action cannot be easily undone.\n\n" +
            "Type 'DELETE' in the next prompt to confirm removal."
        );

        if (!secondConfirm) {
          return; // User cancelled
        }

        // Third confirmation - require typing "DELETE"
        var typeConfirm = window.prompt(
          "To confirm removal of the '" +
            sectionName +
            "' section, please type 'DELETE' (case sensitive):"
        );

        if (typeConfirm !== "DELETE") {
          alert(
            "Section removal cancelled. The '" +
              sectionName +
              "' section has been preserved."
          );
          return;
        }

        // All confirmations passed - proceed with removal
        alert(
          "⚠️ Booking section '" +
            sectionName +
            "' has been removed. Please ensure you have alternative booking functionality in place."
        );
      } else {
        // Standard confirmation for non-booking sections
        if (!window.confirm("Are you sure you want to remove this section?")) {
          return;
        }
      }

      // Remove the section
      $section.next().remove();
      $section.remove();

      // Trigger section removed event for step updates
      $(".form-editor-container").trigger("sectionRemoved");
    }
  );

    /**
     * Get display name for section selector
     */
    function getSectionDisplayName(selector) {
      var displayNames = {
        ".booking": "Booking Settings",
        ".menu": "Menu/Services",
        ".slots": "Time Slots",
        ".availability_calendar": "Availability Calendar",
      };

      return displayNames[selector] || selector;
    }


    $(".editor-block").each(function (i, el) {
      var css_class = $(this).find("select#field_for_type").val();
      $(this).addClass("type-" + css_class);
    });

    $(".show-fields-type-service").on("click", function (e) {
      e.preventDefault();
      $(".listeo-editor-listing-types a").removeClass("active");
      $(this).addClass("active");
      $(".type-event").hide();
      $(".type-rental").hide();
      $(".type-service").show();
      $(".type-classifieds").hide();
    });

    $(".show-fields-type-rentals").on("click", function (e) {
      e.preventDefault();
      $(".listeo-editor-listing-types a").removeClass("active");
      $(this).addClass("active");
      $(".type-event").hide();
      $(".type-rental").show();
      $(".type-service").hide();
      $(".type-classifieds").hide();
    });

    $(".show-fields-type-event").on("click", function (e) {
      e.preventDefault();
      $(".listeo-editor-listing-types a").removeClass("active");
      $(this).addClass("active");
      $(".type-event").show();
      $(".type-rental").hide();
      $(".type-service").hide();
      $(".type-classifieds").hide();
    });

    $(".show-fields-type-classifieds").on("click", function (e) {
      e.preventDefault();
      $(".listeo-editor-listing-types a").removeClass("active");
      $(this).addClass("active");
      $(".type-classifieds").show();
      $(".type-event").hide();
      $(".type-rental").hide();
      $(".type-service").hide();
    });

    $(".show-fields-type-all").on("click", function (e) {
      e.preventDefault();
      $(".listeo-editor-listing-types a").removeClass("active");
      $(this).addClass("active");
      $(".type-event").show();
      $(".type-rental").show();
      $(".type-service").show();
      $(".type-classifieds").show();
    });

    // $("#listeo-new-search-form-dialog").dialog({
    //   title: "Add New Seach Form",
    //   dialogClass: "wp-dialog",
    //   autoOpen: false,
    //   draggable: false,
    //   width: "auto",
    //   modal: true,
    //   resizable: false,
    //   closeOnEscape: true,
    //   position: {
    //     my: "center",
    //     at: "center",
    //     of: window,
    //   },
    //   open: function () {
    //     // close dialog by clicking the overlay behind it
    //     $(".ui-widget-overlay").bind("click", function () {
    //       $("#listeo-new-search-form-dialog").dialog("close");
    //     });
    //   },
    //   create: function () {
    //     // style fix for WordPress admin
    //     $(".ui-dialog-titlebar-close").addClass("ui-button");
    //   },
    // });
    // Initialize MicroModal
    document.addEventListener("DOMContentLoaded", function () {
      MicroModal.init();

      // Open modal when clicking the button
      document
        .querySelector("a#add-new-listeo-search-form")
        .addEventListener("click", function (e) {
          e.preventDefault();
          MicroModal.show("listeo-new-search-form-dialog");
        });

      // Handle form submission
      document
        .getElementById("listeo-new-search-form")
        .addEventListener("submit", function (e) {
          e.preventDefault();

          const name = document.getElementById(
            "listeo-new-search-form-name"
          ).value;
          const type = document.getElementById(
            "listeo-new-search-form-type"
          ).value;

          console.log("Submitting:", { name, type });

          // Add your AJAX here if needed...

          // Close the modal
          MicroModal.close("listeo-new-search-form-dialog");
        });
    });

    // bind a button or a link to open the dialog
    $("a#add-new-listeo-search-form").click(function (e) {
      e.preventDefault();
      MicroModal.show("listeo-new-search-form-dialog"); // modal ID without the #
    });
    //  Search form new handle

    $(document).on("submit", "#listeo-new-search-form", function (e) {
      const defaultforms = [
        "sidebar_search",
        "search_on_half_map",
        "search_on_home_page",
        "search_on_homebox_page",
      ];
      $("#listeo-new-search-form .spinner").addClass("is-active");
      e.preventDefault();
      var name = $("#listeo-new-search-form-name").val();
      var newname = string_to_slug(name);

      if (defaultforms.includes(newname)) {
        alert("use different name");
      } else {
        var type = $("#listeo-new-search-form-type").val();
        var ajax_data = {
          action: "listeo_form_builder_addnewform",
          name: name,
          type: type,
          //'nonce': nonce
        };

        $.ajax({
          type: "POST",
          dataType: "json",
          url: ajaxurl,
          data: ajax_data,

          success: function (data) {
            if (data.success) {
              location.reload();
            } else {
              alert("Please use different name");
            }
            $("#listeo-new-search-form .spinner").removeClass("is-active");
          },
          error: function (data) {
            console.log(data);
            alert("Please use different name");
            $("#listeo-new-search-form .spinner").removeClass("is-active");
          },
        });
      }
    });

    $(".listeo-forms-builder .reset.button-secondary").on(
      "click",
      function (e) {
        e.preventDefault(); // Prevent default action

        if (
          window.confirm(
            "Are you sure you want to reset to default state? This cannot be undone."
          )
        ) {
          // If confirmed, follow the reset link
          window.location = $(this).attr("href");
        }
        // If not confirmed, do nothing
      }
    );

    // $("#listeo-new-term-fields-form-dialog").dialog({
    //   title: "Add New Term fields",
    //   dialogClass: "wp-dialog",
    //   autoOpen: false,
    //   draggable: false,
    //   width: "auto",
    //   modal: true,
    //   resizable: false,
    //   closeOnEscape: true,
    //   position: {
    //     my: "center",
    //     at: "center",
    //     of: window,
    //   },
    //   open: function () {
    //     // close dialog by clicking the overlay behind it
    //     $(".ui-widget-overlay").bind("click", function () {
    //       $("#listeo-new-term-fields-form-dialog").dialog("close");
    //     });
    //   },
    //   create: function () {
    //     // style fix for WordPress admin
    //     $(".ui-dialog-titlebar-close").addClass("ui-button");
    //   },
    // });

    // use ajax function ajax_get_terms to get list of taxonomies and fill the select box  #listeo-new-term-fields-term when the #listeo-new-term-fields-taxonomy is choosen

    $("a#add-new-listeo-term-fields").click(function (e) {
      e.preventDefault();

      MicroModal.show("listeo-new-term-fields-form-dialog");
    });

    document.addEventListener("DOMContentLoaded", function () {
      MicroModal.init();

      // Bind Term Fields modal
      const termBtn = document.querySelector("#add-new-listeo-term-fields");
      if (termBtn) {
        termBtn.addEventListener("click", function (e) {
          e.preventDefault();
          MicroModal.show("listeo-new-term-fields-form-dialog");
        });
      }

      // Handle Term Fields form submission
      const termForm = document.getElementById("listeo-new-term-fields-form");
      if (termForm) {
        termForm.addEventListener("submit", function (e) {
          e.preventDefault();

          const taxonomy = document.getElementById(
            "listeo-new-term-fields-taxonomy"
          ).value;
          const term = document.getElementById(
            "listeo-new-term-fields-term"
          ).value;

          console.log("Submitting Term Fields:", { taxonomy, term });

          // TODO: Add AJAX or saving logic here

          MicroModal.close("listeo-new-term-fields-form-dialog");
        });
      }
    });

    $(document).on("submit", "#listeo-new-term-fields-form", function (e) {
      e.preventDefault();
      var taxonomy = $("#listeo-new-term-fields-taxonomy").val();
      var term = $("#listeo-new-term-fields-term").val();

      var ajax_data = {
        action: "listeo_new_term_fields_add",
        taxonomy: taxonomy,
        term: term,
        //'nonce': nonce
      };

      $.ajax({
        type: "POST",
        dataType: "json",
        url: ajaxurl,
        data: ajax_data,

        success: function (data) {
          if (data.success) {
            location.reload();
          } else {
          }
          $("#listeo-new-term-fields-form .spinner").removeClass("is-active");
        },
        error: function (data) {
          console.log(data);

          $("#listeo-new-term-fields-form .spinner").removeClass("is-active");
        },
      });
    });

    $("#listeo-new-term-fields-form").on(
      "change",
      "#listeo-new-term-fields-taxonomy",
      function (e) {
        e.preventDefault();
        $("#listeo-new-term-fields-form .spinner").addClass("is-active");
        // make submit button disabled
        $("#listeo-new-term-fields-form #submit").prop("disabled", true);
        const taxonomy = $(this).val();
        const $termSelect = $("#listeo-new-term-fields-term");

        $termSelect.html("<option>Loading...</option>");

        $.ajax({
          url: ajaxurl,
          method: "POST",
          dataType: "json",
          data: {
            action: "listeo_get_terms",
            taxonomy: taxonomy,
            //_ajax_nonce: listeoTermUpdater.nonce,
          },
          success: function (response) {
            $termSelect.empty();
            console.log(response);
            $("#listeo-new-term-fields-form .spinner").removeClass("is-active");
            if (
              response.success &&
              Array.isArray(response.data.terms) &&
              response.data.terms.length > 0
            ) {
              // make submit button enabled
              $("#listeo-new-term-fields-form #submit").prop("disabled", false);

              $.each(response.data.terms, function (i, term) {
                $termSelect.append(
                  $("<option>", {
                    value: term.id,
                    text: term.name,
                  })
                );
              });
            } else {
              $("#listeo-new-term-fields-form .spinner").removeClass(
                "is-active"
              );
              $termSelect.append("<option>No terms found</option>");
            }
          },
          error: function () {
            $("#listeo-new-term-fields-form .spinner").removeClass("is-active");
            $termSelect.html("<option>Error loading terms</option>");
          },
        });
      }
    );

    function getAvailableSections() {
      const sections = [];
      const bookingSelectors = [
        ".booking",
        ".slots",
        ".basic_prices",
        ".availability_calendar",
      ];
      let bookingGroupAdded = false;

      $(".form-editor-container .listeo-fafe-row-section").each(function () {
        const selector = $(this).data("selector");
        const label = $(this).find(".section-label").val().trim() || selector;

        if (bookingSelectors.includes(selector)) {
          if (!bookingGroupAdded) {
            sections.push({
              selector: bookingSelectors.join(","), // Combine selectors
              label: "Booking & Availability (all booking related sections)", // New descriptive label
            });
            bookingGroupAdded = true;
          }
          // Do not add the individual booking-related sections
        } else if (selector) {
          sections.push({ selector, label });
        }
      });
      // Add sections that exist in templates but not in editor
      const additionalSections = [
        {
          selector: ".coupon_section",
          label: "Coupons",
        },
        {
          selector: ".my_listings_section",
          label: "My Listings",
        },
        {
          selector: ".faq",
          label: "FAQ",
        },
        {
          selector: ".store_section",
          label: "Store Settings",
        },
        {
          selector: ".menu",
          label: "Pricing Menu",
        },
        {
          selector: ".opening_hours",
          label: "Opening Hours",
        },
      ];

      // Only add Event Date section if booking type is 'tickets' (Event Tickets)
      if (typeof currentListingTypeBookingType !== 'undefined' && currentListingTypeBookingType === 'tickets') {
        additionalSections.push({
          selector: ".event",
          label: "Event Date",
        });
      }
      // Add additional sections to the list
      additionalSections.forEach((section) => {
        // Check if this section selector doesn't already exist
        const exists = sections.some(
          (existingSection) => existingSection.selector === section.selector
        );

        if (!exists) {
          sections.push(section);
        }
      });

      return sections;
    }

    function updateStepSelectors() {
      const usedSelectors = [];

      // Collect all currently checked values from all steps
      $(".form-step-blocks .editor-block .step-selectors").each(function () {
        $(this)
          .find("input[type='checkbox']:checked")
          .each(function () {
            usedSelectors.push($(this).val());
          });
      });

      const sections = getAvailableSections();

      $(".form-step-blocks .editor-block").each(function () {
        const $container = $(this).find(".step-selectors");
        const currentSelected = [];

        // Remember which values were selected in this step
        $container.find("input[type='checkbox']").each(function () {
          if ($(this).is(":checked")) {
            currentSelected.push($(this).val());
          }
        });

        $container.empty();

        sections.forEach((section) => {
          const alreadyUsed = usedSelectors.includes(section.selector);
          const isChecked = currentSelected.includes(section.selector);

          // Only allow sections that are not used elsewhere or are already selected here
          if (!alreadyUsed || isChecked) {
            const $checkbox = $(`
              <label>
                <input type="checkbox" value="${section.selector}" ${
              isChecked ? "checked" : ""
            }>
                ${section.label}
            </label>
            `);
            $container.append($checkbox);
          }
        });
      });
    }

    function insertNewStep(stepTitle = "", stepSelectors = []) {
      $(".no-steps-message").hide();
      const stepIndex = $(".form-step-blocks .editor-block").length + 1;
      const stepId = "step_" + stepIndex;

      let template = $("#listeo-step-block-template").html();
      template = template
        .replace(/{step_id}/g, stepId)
        .replace(/{step_title}/g, stepTitle || "Untitled Step");

      const $block = $(template);
      $(".form-step-blocks").append($block);

      $block.find(".step-title").val(stepTitle);
      $block.find(".step-display-title").text(stepTitle);

      const $selectorsContainer = $block.find(".step-selectors");
      const sections = getAvailableSections();

      sections.forEach((section) => {
        const isChecked = stepSelectors.includes(section.selector);
        const $checkbox = $(`
      <label>
        <input type="checkbox" value="${section.selector}" ${
          isChecked ? "checked" : ""
        }>
        ${section.label}
      </label>
    `);
        $selectorsContainer.append($checkbox);
      });

      updateStepSelectors();
    }
    // Handle modal submit
    $("#listeo-add-step-form").on("submit", function (e) {
      e.preventDefault();

      const stepTitle = $.trim($("#new-step-title").val());
      if (stepTitle.length < 2) {
        alert("Please enter a valid step title.");
        return;
      }

      insertNewStep(stepTitle); // call logic
      MicroModal.close("listeo-add-step-modal");
    });

    $("#add-step").on("click", function (e) {
      e.preventDefault();
      $("#new-step-title").val("");
      MicroModal.show("listeo-add-step-modal");
    });

    $(".form-step-blocks").on("click", ".block-delete a", function (e) {
      e.preventDefault();
      $(this).closest(".editor-block").remove();
      updateStepSelectors();
    });

    $(".form-step-blocks").on("input", ".step-title", function () {
      const newTitle = $(this).val();
      console.log("New Step Title:", newTitle);
      $(this)
        .closest(".editor-block")
        .find(".step-display-title")
        .text(newTitle);
    });

    $(".form-step-blocks").on("click", ".block-edit a", function (e) {
      e.preventDefault();
      $(this)
        .closest(".editor-block")
        .find(".editor-block-form-fields")
        .toggleClass("active-block");
    });

    $(".listeo-fafe-new-section").on("click", function (e) {
      setTimeout(() => {
        $(".form-editor-container").trigger("sectionAdded");
      }, 100);
    });

    $(".form-editor-container").on("sectionAdded sectionRemoved", function () {
      updateStepSelectors();
    });

    $(".form-step-blocks").on("change", ".step-selectors", function () {
      updateStepSelectors();
    });

    if (
      typeof savedStepConfiguration !== "undefined" &&
      savedStepConfiguration.length
    ) {
      const bookingSelectors = [
        ".booking",
        ".slots",
        ".basic_prices",
        ".availability_calendar",
      ];
      const compositeBookingSelector = bookingSelectors.join(",");

      // Transform the saved data to match the UI's grouped representation
      savedStepConfiguration.forEach((step) => {
        // Check if the step's selectors include any of the individual booking sections
        const hasBookingSection = step.selectors.some((sel) =>
          bookingSelectors.includes(sel)
        );

        if (hasBookingSection) {
          // If it does, filter out all individual booking selectors...
          step.selectors = step.selectors.filter(
            (sel) => !bookingSelectors.includes(sel)
          );
          // ...and add the single composite selector instead.
          // We also ensure it's not added twice if it somehow already exists.
          if (!step.selectors.includes(compositeBookingSelector)) {
            step.selectors.push(compositeBookingSelector);
          }
        }
      });

      // Now that the data is transformed, create the steps in the UI
      savedStepConfiguration.forEach((step) => {
        addNewStep(step.title, step.selectors);
      });
    }

    function addNewStep(stepTitle = "", stepSelectors = []) {
      // prompt that ask for step title
      if (!stepTitle) {
        stepTitle = prompt("Please enter the step title:", "Untitled Step");
        if (!stepTitle) {
          return; // Exit if no title is provided
        }
      }
      // hide no-steps-message
      $(".no-steps-message").hide();
      const stepIndex = $(".form-step-blocks .editor-block").length + 1;
      const stepId = "step_" + stepIndex;

      let template = $("#listeo-step-block-template").html();
      template = template
        .replace(/{step_id}/g, stepId)
        .replace(/{step_title}/g, stepTitle || "Untitled Step");

      const $block = $(template);
      $(".form-step-blocks").append($block);

      $block.find(".step-title").val(stepTitle);
      $block.find(".step-display-title").text(stepTitle);

      const $selectorsContainer = $block.find(".step-selectors");
      const sections = getAvailableSections();

      sections.forEach((section) => {
        const isChecked = stepSelectors.includes(section.selector);
        const $checkbox = $(`
          <label>
            <input type="checkbox" value="${section.selector}" ${
          isChecked ? "checked" : ""
        }>
            ${section.label}
          </label>
        `);
        $selectorsContainer.append($checkbox);
      });

      updateStepSelectors();
    }
    $("form").on("submit", function () {
      const config = getStepConfiguration();
      $("#listeo_form_steps_json").val(JSON.stringify(config));
    });

    window.getStepConfiguration = function () {
      const steps = [];
      const bookingSelectors = [
        ".booking",
        ".slots",
        ".basic_prices",
        ".availability_calendar",
      ];
      const compositeBookingSelector = bookingSelectors.join(",");

      $(".form-step-blocks .editor-block").each(function () {
        const title = $(this).find(".step-title").val();
        const selectors = [];

        $(this)
          .find(".step-selectors input[type='checkbox']:checked")
          .each(function () {
            const value = $(this).val();
            if (value === compositeBookingSelector) {
              // If it's our special combined selector, split it back into individual ones
              selectors.push(...value.split(","));
            } else {
              selectors.push(value);
            }
          });

        if (title && selectors.length) {
          steps.push({ title, selectors });
        }
      });

      console.log("Step Configuration:", steps);
      return steps;
    };

    // if checkbox with id listeo-enable-form-steps is checked, show the form steps container
    const defaultStepConfiguration = [
      {
        title: "Listing Essentials",
        selectors: [".basic_info", ".location", ".gallery", ".details"],
      },
      {
        title: "Offerings & Schedule",
        selectors: [
          ".opening_hours",
          ".menu",
          ".booking",
          ".slots",
          ".basic_prices",
          ".availability_calendar",
        ],
      },
      {
        title: "Final Details",
        selectors: [
          ".faq",
          ".coupon_section",
          ".my_listings_section",
          ".store_section",
          ".event",
          ".classifieds",
        ],
      },
    ];

    $("#listeo-enable-form-steps").on("change", function () {
      if ($(this).is(":checked")) {
        $(".form-step-blocks-wrapper").show();
        const existingSteps = $(".form-step-blocks .editor-block").length;
        if (existingSteps === 0) {
          defaultStepConfiguration.forEach((step) => {
            addNewStep(step.title, step.selectors);
          });
        }
      } else {
        $(".form-step-blocks-wrapper").hide();
      }
    });
  });

  // Reviews Criteria - Add Criteria Modal
  $(document).ready(function () {
    // Open modal
    $(document).on('click', '#add-new-reviews-criteria', function (e) {
      e.preventDefault();
      MicroModal.show('listeo-add-criteria-modal');
    });

    // Show/hide fields based on criteria type selection
    $('#listeo-criteria-type').on('change', function () {
      var type = $(this).val();

      $('#listing-type-field, #taxonomy-field, #term-field').hide();
      $('#listeo-term-select').html('<option value="">-- First select taxonomy --</option>');

      if (type === 'listing_type') {
        $('#listing-type-field').show();
      } else if (type === 'taxonomy_term') {
        $('#taxonomy-field').show();
      }
    });

    // Load terms when taxonomy is selected
    $('#listeo-taxonomy-select').on('change', function () {
      var taxonomy = $(this).val();

      if (!taxonomy) {
        $('#term-field').hide();
        return;
      }

      // Load terms via AJAX
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'listeo_get_taxonomy_terms',
          taxonomy: taxonomy,
          nonce: listeo_admin.nonce
        },
        success: function (response) {
          if (response.success) {
            var $select = $('#listeo-term-select');
            $select.html('<option value="">-- Select Term --</option>');

            $.each(response.data.terms, function (i, term) {
              $select.append($('<option>', {
                value: term.id,
                text: term.name
              }));
            });

            $('#term-field').show();
          } else {
            alert(response.data || 'Error loading terms');
          }
        },
        error: function () {
          alert('Error loading terms');
        }
      });
    });

    // Submit form
    $(document).on('submit', '#listeo-add-criteria-form', function (e) {
      e.preventDefault();

      var formData = {
        action: 'listeo_add_custom_criteria',
        nonce: listeo_admin.nonce,
        criteria_type: $('#listeo-criteria-type').val(),
        listing_type: $('#listeo-listing-type-select').val(),
        taxonomy: $('#listeo-taxonomy-select').val(),
        term_id: $('#listeo-term-select').val()
      };

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        success: function (response) {
          if (response.success) {
            // Redirect to new tab
            window.location.href = response.data.redirect;
          } else {
            alert(response.data || 'Error creating criteria');
          }
        },
        error: function () {
          alert('Error creating criteria');
        }
      });
    });
  });

}(jQuery));
