/* ----------------- Start Document ----------------- */
(function ($) {
  "use strict";

  $(document).ready(function () {
    
      // Global default categories if none are provided (optional)
      var defaultCategories = [
        {
          label: "Default",
          children: [{ label: "Item 1" }, { label: "Item 2" }],
        },
      ];

      // Helper function to check if an item has real children (not just duplicates)
      function hasRealChildren(item) {
        if (!item.children || item.children.length === 0) {
          return false;
        }
        
        // If there's only one child and it's essentially the same as the parent, treat as leaf
        if (item.children.length === 1) {
          var child = item.children[0];
          // Check if the child is a duplicate of the parent
          if (child.value === item.value && child.id === item.id) {
            return false;
          }
          // Also check if it's an "All in X" pattern
          if (child.label === "All in " + item.label) {
            return false;
          }
        }
        
        return true;
      }

      // Set up each drilldown menu instance
      $(".drilldown-menu").each(function () {
        var $menu = $(this);
        var selectedItems = [];
        var menuStack = []; // Array to keep track of drilldown levels
        var initialized = false; //
        // Add this option - read from data attribute or default to false
        var singleSelect = $menu.data("single-select") === true;

        // Read categories from the data attribute; fallback to defaultCategories if needed.
        var categories = $menu.data("categories");
        if (typeof categories === "string") {
          try {
            categories = JSON.parse(categories);
          } catch (e) {
            categories = defaultCategories;
          }
        } else if (!categories) {
          categories = defaultCategories;
        }

        // Cache commonly used elements within this menu
        var $menuToggle = $menu.find(".menu-toggle");
        var $menuPanel = $menu.find(".menu-panel");
        var $menuLevelsContainer = $menu.find(".menu-levels");
        var $menuLabel = $menu.find(".menu-label");
        var $menuLabelText = $menu.data("label");
        var $resetButton = $menu.find(".reset-button");

        // Recursive function to check if an item (or any descendant) matches the search term
        function itemMatchesSearch(item, searchTerm) {
          if ($.trim(searchTerm) === "") return true;
          var lowerSearch = searchTerm.toLowerCase();
          if (item.label.toLowerCase().indexOf(lowerSearch) !== -1) {
            return true;
          }
          if (item.children && item.children.length > 0) {
            for (var i = 0; i < item.children.length; i++) {
              if (itemMatchesSearch(item.children[i], searchTerm)) {
                return true;
              }
            }
          }
          return false;
        }

        // Initialize the menu at the root level
        function initMenu() {
          menuStack = [];
          menuStack.push({ data: categories, parent: null });
          $menuLevelsContainer.empty();
          var $levelElement = createMenuLevel(categories, 0);
          $menuLevelsContainer.append($levelElement);
          updateMenuLevelPosition();
          updateMenuHeight();
          initializePreselectedValues();
        }

        // Create a new menu level element for the given data
        function createMenuLevel(data, levelIndex) {
          var $levelDiv = $("<div/>")
            .addClass("menu-level")
            .attr("data-level", levelIndex);

          // Add a "Back" button if not at the root level
          if (levelIndex > 0) {
            var $backButton = $("<button/>")
              .addClass("back-button")
              .text(listeo_core.back)
              .on("click", function (e) {
                e.stopPropagation();
                drillUp();
              });
            $levelDiv.append($backButton);
          }

          // Add a search input field
          var $searchInput = $("<input/>", {
            type: "text",
            placeholder: listeo_core.search,
            class: "menu-search",
          }).on("input", function () {
            filterMenuLevel($levelDiv, $searchInput.val());
          });
          $levelDiv.append($searchInput);

          // Create a container for menu items
          var $itemsContainer = $("<div/>").addClass("menu-items");
          $levelDiv.append($itemsContainer);

          // Iterate over the items and create each menu item element
          $.each(data, function (i, item) {
            var $itemDiv = $("<div/>")
              .addClass("menu-item")
              .attr("data-label", item.label);

            // Add value attribute if it exists
            if (item.value) {
              $itemDiv.attr("data-value", item.value);
            }
            if (item.id) {
              $itemDiv.attr("data-id", item.id);
            }
            // Store the entire item object for use in search filtering
            $itemDiv.data("item", item);
            var $labelSpan = $("<span/>")
              .addClass("item-label")
              .text(item.label);
            $itemDiv.append($labelSpan);

            // FIXED: Use hasRealChildren instead of just checking if children exist
            if (hasRealChildren(item)) {
              // Item with subcategories: add an arrow and set up drilldown
              var $arrowSpan = $("<span/>").addClass("arrow");
              $itemDiv.append($arrowSpan);
              $itemDiv.on("click", function (e) {
                e.stopPropagation();
                drillDown(item);
              });
            } else {
              // Leaf item: toggle selection on click
              $itemDiv.on("click", function (e) {
                e.stopPropagation();
                // Remove 'active' class from any .category-item
                $(".category-item").removeClass("active");

                // Remove slider's hidden input IF it exists (for this specific taxonomy)
                var menuId = $menu.attr("id");
                // Extract taxonomy name from drilldown ID (e.g., "listeo-drilldown-tax-listing_category")
                if (menuId && menuId.startsWith("listeo-drilldown-tax-")) {
                  var taxonomyName = menuId.replace("listeo-drilldown-tax-", "");
                  var sliderInputName = "tax-" + taxonomyName;
                  // Remove non-array inputs created by slider
                  $("#listeo_core-search-form")
                    .find('input[name="' + sliderInputName + '"]:not([name*="["])')
                    .remove();
                }

                toggleSelection(item, $itemDiv);
              });
              if (isSelected(item)) {
                $itemDiv.addClass("selected");
              }
            }
            $itemsContainer.append($itemDiv);
          });

          return $levelDiv;
        }

        // Modified filter function that checks parent items and their descendants.
        // It also highlights matches using <mark>.
        function filterMenuLevel($levelDiv, searchTerm) {
          var $itemsContainer = $levelDiv.find(".menu-items");
          var $items = $itemsContainer.find(".menu-item");
          var anyVisible = false;
          $levelDiv.find(".no-results").remove();

          $items.each(function () {
            var $item = $(this);
            var itemObj = $item.data("item"); // get the complete data object
            var label = itemObj.label;
            var lowerSearch = $.trim(searchTerm).toLowerCase();
            // Determine if there is a direct match in the label
            var directMatch =
              lowerSearch !== "" &&
              label.toLowerCase().indexOf(lowerSearch) > -1;
            // Determine if the item or any descendant matches
            var matches = itemMatchesSearch(itemObj, searchTerm);
            if (matches) {
              $item.css("display", "flex");
              anyVisible = true;
              var $labelSpan = $item.find(".item-label");
              // Reset any previous highlighting and classes
              $item.removeClass("child-match");
              $labelSpan.text(label);
              if ($.trim(searchTerm) !== "") {
                if (directMatch) {
                  // Highlight the matching substring in the label
                  var regex = new RegExp(
                    "(" + escapeRegExp(searchTerm) + ")",
                    "gi"
                  );
                  $labelSpan.html(label.replace(regex, "<mark>$1</mark>"));
                } else {
                  // No direct match—but a descendant matches: add a special class
                  $item.addClass("child-match");
                }
              }
            } else {
              $item.css("display", "none");
            }
          });
          if (!anyVisible) {
            var $noResults = $("<div/>")
              .addClass("no-results")
              .text("No results");
            $itemsContainer.append($noResults);
          }
          updateMenuHeight();
        }

        // Utility function to escape regex special characters
        function escapeRegExp(string) {
          return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        }

        // Drill down into a submenu for the given category.
        // Also, propagate the parent's search term to the child's search field.
        function drillDown(category) {
          if (!category.children || category.children.length === 0) return;
          // Get parent's search term from the current active level.
          var $currentLevel = $menuLevelsContainer.children().last();
          var parentSearchTerm = $currentLevel.find(".menu-search").val();
          menuStack.push({ data: category.children, parent: category });
          var levelIndex = menuStack.length - 1;
          var $newLevel = createMenuLevel(category.children, levelIndex);
          // Propagate parent's search term to child's search input and filter.
          $newLevel.find(".menu-search").val(parentSearchTerm);
          filterMenuLevel($newLevel, parentSearchTerm);
          $menuLevelsContainer.append($newLevel);
          updateMenuLevelPosition();
          setTimeout(updateMenuHeight, 0);
        }

        // Return to the previous menu level
        function drillUp() {
          if (menuStack.length <= 1) return;
          menuStack.pop();
          $menuLevelsContainer.children().last().remove();
          updateMenuLevelPosition();
          updateMenuHeight();
        }

        function findItemByValue(categories, value) {
    
          for (var i = 0; i < categories.length; i++) {
            var item = categories[i];
            // Check if this item matches
            if ($("#submit-listing-form").length) {
              if (item.id == value) {
                return item;
              }
            } else {
               if (item.id == value) {
                 return item;
               }
              // Handle different value formats:
              // 1. Direct match: item.value === "restaurants"
              // 2. Prefixed match: item.value === "listing_category:restaurants" when searching for "restaurants"
              // 3. Label fallback: item.label === value when no item.value
              var directMatch = item.value === value;
              var prefixedMatch = item.value === ("listing_category:" + value);
              var labelMatch = !item.value && item.label === value;

              if (directMatch || prefixedMatch || labelMatch) {
                return item;
              }
            }

            // Check children if they exist
            if (item.children && item.children.length > 0) {
              var found = findItemByValue(item.children, value);
              if (found) return found;
            }
          }
          return null;
        }

        // Make drilldown control functions available globally
        if (!window.ListeoDrilldown) window.ListeoDrilldown = {};

        window.ListeoDrilldown[$menu.attr("id")] = {
          initMenu: initMenu,
          selectById: function (categoryId) {
            initMenu();

            setTimeout(function () {
              const item = findItemByValue(categories, categoryId);
              if (item) {
                // Fake a jQuery element just to pass to toggleSelection
                const $fake = $("<div>")
                  .addClass("menu-item")
                  .attr("data-id", categoryId);
                toggleSelection(item, $fake);
              } else {
                // reset the menu if no item found
                resetSelections();
              }
            }, 20);
          },
          selectListingType: function (slug) {
            // ensure the menu is built
            initMenu();

            setTimeout(function () {
              var typeValue = "all_" + slug;

              // find the parent node that matches the slug by value
              var parent =
                findItemByValue(categories, typeValue) ||
                findItemByValue(categories, slug);

              if (parent) {
                var target = parent;

                // If there’s a child that starts with 'all_in_' + slug, prefer it
                if (parent.children && parent.children.length) {
                  var allChild = parent.children.find(function (c) {
                    return c.value === "type" + slug;
                  });
                  if (allChild) {
                    target = allChild;
                  }
                }

                // Toggle selection as if user clicked it
                var $fake = jQuery("<div>").addClass("menu-item");
                toggleSelection(target, $fake);
              } else {
                // fallback: clear selection if nothing matches
                resetSelections();
              }
            }, 20);
          },
        };

        // Update visual selection state for all currently selected items
        function updateVisualSelection() {
          // First, clear all existing selections
          $menu.find(".menu-item.selected").removeClass("selected");

          // Then apply selection classes to all currently selected items
          selectedItems.forEach(function(item) {
            // Add selection class by data-value attribute
            $menu
              .find('.menu-item[data-value="' + (item.value || item.label) + '"]')
              .addClass("selected");

            // Also add by data-id if available
            if (item.id) {
              $menu
                .find('.menu-item[data-id="' + item.id + '"]')
                .addClass("selected");
            }
          });
        }

        function initializePreselectedValues() {
          selectedItems = []; // Clear existing selections

          var inputName = $menu.data("name");
console.log("Initializing preselected values for input name:", inputName);
          // Get all existing inputs with drilldown-values class (both single and multiple)
          var $existingInputs = $menu.find("input.drilldown-values");
console.log("Found existing inputs:", $existingInputs.length);
          $existingInputs.each(function () {
            var value = $(this).val();
            if (value && value.trim() !== '') {
              console.log("Found existing input value:", value);
              var item = findItemByValue(categories, value.trim());
              if (item) {
                selectedItems.push(item);
              }
            }
          });

          if (selectedItems.length > 0) {
            updateMainButton();
            // Ensure hidden inputs are created for preselected items
            updateHiddenInput();
            // Update visual state for all preselected items
            updateVisualSelection();
          }
        }

        function updateHiddenInput() {
          var inputName = $menu.data("name");

          // Remove any existing inputs (both original drilldown-values and generated ones)
          $menu.find("input.drilldown-values").remove();
          $menu.find("input.drilldown-generated").remove();

          // Create new hidden inputs for each selected value
          selectedItems.forEach(function (item) {
            if ($("#submit-listing-form").length) {
              var value = item.id;
            } else {
              var value = item.value || item.label;
            }

            // For single taxonomy drilldowns (not listing-types), strip taxonomy prefix
            var menuId = $menu.attr("id");
            if (menuId && menuId.startsWith("listeo-drilldown-tax-") && !menuId.includes("listing-types")) {
              // This is a single taxonomy drilldown, strip any taxonomy prefix
              if (typeof value === 'string' && value.includes(':')) {
                var parts = value.split(':');
                if (parts.length === 2) {
                  value = parts[1]; // Use only the term part, not the taxonomy part
                }
              }
            }

            $("<input>", {
              type: "hidden",
              name: inputName + "[]",
              value: value,
              "data-label": item.label,
              class: "drilldown-values drilldown-generated", // Keep both classes for compatibility
            }).appendTo($menu);
          });

          var target = $("div#listeo-listings-container");
          target.triggerHandler("update_results", [1, false]);

          $menu.trigger("drilldown-updated");
        }

        // Update the container's transform to slide to the active level
        function updateMenuLevelPosition() {
          var levelIndex = menuStack.length - 1;
          $menuLevelsContainer.css(
            "transform",
            "translateX(-" + levelIndex * 100 + "%)"
          );
        }

        // Update the panel height to match the active level's natural height
        function updateMenuHeight() {
          var $levels = $menuLevelsContainer.children();
          if ($levels.length === 0) return;
          var $activeLevel = $levels.last();
          $menuPanel.height($activeLevel[0].scrollHeight);
        }

        // Toggle selection of a leaf item.
        // Also update the main button with highlighting of the current search term.
        function toggleSelection(item, $itemDiv) {
          var index = selectedItems.findIndex(function (selected) {
            if ($("#submit-listing-form").length) {
              if (item.id && selected.id) {
                return selected.id === item.id;
              }
            } else {
              if (item.value && selected.value) {
                return selected.value === item.value;
              }
            }
            return selected.label === item.label;
          });

          if (index > -1) {
            // Deselect
            selectedItems.splice(index, 1);
            $itemDiv.removeClass("selected");

            // Also remove from any other instances of this item in other levels
            $menu
              .find(
                '.menu-item[data-value="' + (item.value || item.label) + '"]'
              )
              .removeClass("selected");
            if (item.id) {
              $menu
                .find('.menu-item[data-id="' + item.id + '"]')
                .removeClass("selected");
            }
          } else {
            // Select
            if (singleSelect) {
              // Remove 'selected' class from all items
              $menu.find(".menu-item.selected").removeClass("selected");
              // Clear the array
              selectedItems = [];
            }

            // Check if item already exists before pushing
            if (!isSelected(item)) {
              selectedItems.push(item);
            }
            $itemDiv.addClass("selected");

            // Also add to any other instances of this item in other levels
            $menu
              .find(
                '.menu-item[data-value="' + (item.value || item.label) + '"]'
              )
              .addClass("selected");
            if (item.id) {
              $menu
                .find('.menu-item[data-id="' + item.id + '"]')
                .addClass("selected");
            }
          }

          updateMainButton();
          updateHiddenInput();
        }

        // Check if an item is already selected
        // function isSelected(item) {
        //   var exists = false;
        //   $.each(selectedItems, function (i, sel) {
        //     if (sel.label === item.label) {
        //       exists = true;
        //       return false;
        //     }
        //   });
        //   return exists;
        // }

        function isSelected(item) {
          return selectedItems.some(function (selected) {
            if ($("#submit-listing-form").length) {
              if (item.id && selected.id) {
                return selected.id === item.id;
              }
            } else {
              if (item.value && selected.value) {
                return selected.value === item.value;
              }
            }

            return selected.label === item.label;
          });
        }

        // Update the main button text.
        // If a search term is active in the current level, highlight it in the label.
        function updateMainButton() {
          var searchTerm =
            $menuLevelsContainer.children().last().find(".menu-search").val() ||
            "";
          if (selectedItems.length === 0) {
            $menuLabel.html($menuLabelText);
            $resetButton.hide();
            $menuToggle.removeClass("dd-chosen"); // Remove class when no selection
          } else if (selectedItems.length === 1) {
            var label = selectedItems[0].label;
            if ($.trim(searchTerm) !== "") {
              var regex = new RegExp(
                "(" + escapeRegExp(searchTerm) + ")",
                "gi"
              );
              label = label.replace(regex, "<mark>$1</mark>");
            }
            $menuLabel.html(label);
            $resetButton.show();
            $menuToggle.addClass("dd-chosen"); // Add class when selection exists
          } else {
            var label = selectedItems[0].label;
            if ($.trim(searchTerm) !== "") {
              var regex = new RegExp(
                "(" + escapeRegExp(searchTerm) + ")",
                "gi"
              );
              label = label.replace(regex, "<mark>$1</mark>");
            }
            $menuLabel.html(label + " +" + (selectedItems.length - 1));
            $resetButton.show();
            $menuToggle.addClass("dd-chosen"); // Add class when selection exists
          }
        }

        // Replace the existing resetSelections function with this fixed version:
        function resetSelections() {
          selectedItems = [];

          // Remove selected class from ALL menu items in ALL levels, not just the current panel
          $menu.find(".menu-item.selected").removeClass("selected");

          // Also remove from any cached/hidden levels
          $menuLevelsContainer
            .find(".menu-item.selected")
            .removeClass("selected");

          // Debug logging
          console.log("$menuLabel:", $menuLabel);
          console.log("$menuLabelText:", $menuLabelText);
          console.log("Current label HTML:", $menuLabel.html());

          // Force update the main button label to original text
          if ($menuLabelText) {
            $menuLabel.html($menuLabelText);
          } else {
            // Fallback - try to get original text from data attribute or use default
            var originalText =
              $menu.data("label") ||
              $menu.attr("data-label") ||
              "Select an option";
            console.log("Using fallback text:", originalText);
            $menuLabel.html(originalText);
          }
          $resetButton.hide();
          $menuToggle.removeClass("dd-chosen");

          updateHiddenInput();

          // Optional: Close the menu after reset
          // closeMenu();
        }

        // Open the menu and initialize it; also close any other open menus
        function openMenu() {
          // Close all other menus on the page

          $(".drilldown-menu")
            .not($menu)
            .each(function () {
              $(this).find(".menu-panel").removeClass("open");
              $(this).find(".menu-toggle").removeClass("dd-active"); // Remove class from other menus
            });

          if ($.fn.selectpicker) {
            // For Bootstrap 4+
            $(".bootstrap-select.show").each(function () {
              $(this).removeClass("show");
              $(this).find(".dropdown-menu").removeClass("show");
            });

            // For older Bootstrap versions
            $(".bootstrap-select.open").each(function () {
              $(this).removeClass("open");
              $(this).find(".dropdown-menu").removeClass("open");
            });
          }
          $menuPanel.addClass("open");
          $menuToggle.addClass("dd-active"); // Add class when menu is opened
          if (!initialized) {
            initMenu();
            initialized = true;
          } else {
            // Just restore the selected state without rebuilding
            restoreSelectedState();
          }
        }

        // NEW: Function to restore visual selected state
        function restoreSelectedState() {
          selectedItems.forEach(function (selectedItem) {
            // Find and mark all matching items as selected
            var selector = "";
            if (selectedItem.id) {
              selector = '[data-id="' + selectedItem.id + '"]';
            } else if (selectedItem.value) {
              selector = '[data-value="' + selectedItem.value + '"]';
            } else {
              selector = '[data-label="' + selectedItem.label + '"]';
            }

            $menu.find(".menu-item" + selector).addClass("selected");
          });

          // Update the main button to reflect current selections
          updateMainButton();
        }
        // Close the menu
        function closeMenu() {
          $menuPanel.removeClass("open");
          $menuToggle.removeClass("dd-active"); // Remove class when menu is closed
        }

        // Toggle the menu when clicking the main button
        $menuToggle.on("click", function (e) {
          e.stopPropagation();
          if ($menuPanel.hasClass("open")) {
            closeMenu();
          } else {
            openMenu();
          }
        });

        // Reset selections when clicking the reset button
        $resetButton.on("click", function (e) {
          e.stopPropagation();
          resetSelections();
          $(".category-item").removeClass("active");
        });

        // Close this menu if clicking outside it
        $(document).on("click", function (e) {
          if (!$menu.is(e.target) && $menu.has(e.target).length === 0) {
            closeMenu();
          }
        });

        // Initialize menu for preselection on page load
        if ($menu.find("input.drilldown-values[value!='']").length > 0) {
          initMenu();
          initialized = true;
        }
      });

      window.selectDrilldownCategoryById = function (categoryId) {
        $(".drilldown-menu").each(function () {
          var $menu = $(this);
          var $matchingItem = $menu.find('.menu-item[data-id="${categoryId}"]');
          console.log("Matching item:", $matchingItem);
          if ($matchingItem.length) {
            $matchingItem.trigger("click");
            console.log("Item clicked:", $matchingItem.text());
            // Open the menu if needed and close it after selection
         
          }
        });
      };
    });
  
})(this.jQuery);