/**
 * Listeo Custom Permalinks Admin JavaScript
 * 
 * Handles the admin interface for custom permalink structures
 */
jQuery(document).ready(function($) {
    'use strict';

    // Initialize the custom permalinks interface
    var CustomPermalinks = {
        
        init: function() {
            this.bindEvents();
            this.updatePreview();
            this.checkCompatibility();
            this.initPermalinkWarning();
            this.checkInitialPermalinkSettings();
        },

        bindEvents: function() {
            // Toggle advanced options when checkbox is changed
            $('#listeo_custom_permalinks_enabled').on('change', this.toggleAdvancedOptions);
            
            // Safe Mode toggle with confirmation dialog
            $('#listeo_permalink_safe_mode').on('change', function() {
                var $checkbox = $(this);
                var isChecked = $checkbox.is(':checked');

                // If user is disabling Safe Mode, show confirmation
                if (!isChecked) {
                    var confirmed = CustomPermalinks.confirmDisableSafeMode();
                    if (!confirmed) {
                        // Re-enable the checkbox if user cancels
                        $checkbox.prop('checked', true);
                        return false;
                    }
                }

                // Mark as user-set when manually changed
                $checkbox.data('user-set', true);
                CustomPermalinks.toggleSafeMode();
            });
            
            // Preset selection
            $('.listeo-preset-option').on('click', this.selectPreset);
            
            // Add token when card is clicked
            $('.listeo-token-card').on('click', this.addToken);
            
            // Update preview when structure changes
            $('#listeo_custom_structure').on('input', this.updatePreview);
            $('#listeo_custom_structure').on('blur', this.validateStructure);
            
            // Show/hide options based on current state
            if ($('#listeo_custom_permalinks_enabled').is(':checked')) {
                $('#listeo-custom-permalinks-options').show();
                $('.listeo-permalink-card').addClass('active');
            }
        },

        toggleAdvancedOptions: function() {
            var $options = $('#listeo-custom-permalinks-options');
            var $checkbox = $('#listeo_custom_permalinks_enabled');
            var $card = $('.listeo-permalink-card');
            var $modeField = $('#listeo_permalink_mode');
            var $safeModeCheckbox = $('#listeo_permalink_safe_mode');
            
            if ($checkbox.is(':checked')) {
                $options.slideDown(300);
                $card.addClass('active');
                $modeField.val('custom');
                
                // Auto-enable Safe Mode for better default protection (only if not already set by user)
                if (!$safeModeCheckbox.is(':checked') && !$safeModeCheckbox.data('user-set')) {
                    $safeModeCheckbox.prop('checked', true);
                }
                
                // Force "Day and name" permalink setting when custom permalinks are enabled
                CustomPermalinks.forceDayAndNamePermalinks();
            } else {
                $options.slideUp(300);
                $card.removeClass('active');
                $modeField.val('default');
                // Clear the structure when disabling custom permalinks
                $('#listeo_custom_structure').val('');
            }
            
            // Update preview to reflect changes
            CustomPermalinks.updatePreview();
        },

        toggleSafeMode: function() {
            // Update preview when Safe Mode is toggled
            CustomPermalinks.updatePreview();
            
            // Show a brief feedback that settings will be applied on save
            var $checkbox = $('#listeo_permalink_safe_mode');
            var $notice = $('.listeo-safe-mode-notice');
            
            if ($checkbox.is(':checked')) {
                $notice.fadeIn(200);
            } else {
                $notice.fadeIn(200);
            }
        },

        selectPreset: function() {
            var $preset = $(this);
            var structure = $preset.data('structure');
            
            // Update visual selection
            $('.listeo-preset-option').removeClass('selected');
            $preset.addClass('selected');
            
            // Handle "default" option specially
            if (structure === 'default') {
                // Uncheck the custom permalinks checkbox
                $('#listeo_custom_permalinks_enabled').prop('checked', false);
                $('#listeo_custom_structure').val('');
                
                // Update the hidden permalink mode field to indicate default WordPress permalinks
                $('#listeo_permalink_mode').val('default');
                
                // Hide the custom options
                $('#listeo-custom-permalinks-options').slideUp(300);
                $('.listeo-permalink-card').removeClass('active');
                
                // Update preview to show default
                CustomPermalinks.updatePreview();
            } else {
                // Regular preset selection
                if (structure) {
                    var wasCustomPermalinksDisabled = !$('#listeo_custom_permalinks_enabled').is(':checked');
                    var $safeModeCheckbox = $('#listeo_permalink_safe_mode');
                    
                    // Check the custom permalinks checkbox
                    $('#listeo_custom_permalinks_enabled').prop('checked', true);
                    $('#listeo_custom_structure').val(structure);
                    
                    // Update the hidden permalink mode field to indicate custom permalinks
                    $('#listeo_permalink_mode').val('custom');
                    
                    // Show the custom options
                    $('#listeo-custom-permalinks-options').slideDown(300);
                    $('.listeo-permalink-card').addClass('active');
                    
                    // Auto-enable Safe Mode when switching from disabled to custom preset (only if not already set by user)
                    if (wasCustomPermalinksDisabled && !$safeModeCheckbox.is(':checked') && !$safeModeCheckbox.data('user-set')) {
                        $safeModeCheckbox.prop('checked', true);
                    }
                    
                    // Force "Day and name" permalink setting when custom preset is selected
                    CustomPermalinks.forceDayAndNamePermalinks();
                    
                    CustomPermalinks.updatePreview();
                    CustomPermalinks.validateStructure();
                }
            }
        },

        addToken: function(e) {
            e.preventDefault();
            
            var $tokenCard = $(this);
            var token = $tokenCard.data('token');
            var $input = $('#listeo_custom_structure');
            var currentValue = $input.val();
            var cursorPos = $input.prop('selectionStart');
            
            // Add visual feedback
            $tokenCard.addClass('clicked');
            setTimeout(function() {
                $tokenCard.removeClass('clicked');
            }, 200);
            
            // Determine if we need separators
            var tokenToInsert = token;
            var beforeChar = '';
            var afterChar = '';
            
            if (typeof cursorPos !== 'undefined') {
                // Get characters before and after cursor
                beforeChar = cursorPos > 0 ? currentValue.charAt(cursorPos - 1) : '';
                afterChar = cursorPos < currentValue.length ? currentValue.charAt(cursorPos) : '';
                
                // Add separator before token if needed
                if (beforeChar && beforeChar !== '/' && !beforeChar.match(/[\s\-_]/)) {
                    tokenToInsert = '/' + tokenToInsert;
                }
                
                // Add separator after token if needed  
                if (afterChar && afterChar !== '/' && !afterChar.match(/[\s\-_]/)) {
                    tokenToInsert = tokenToInsert + '/';
                }
                
                var newValue = currentValue.substring(0, cursorPos) + tokenToInsert + currentValue.substring(cursorPos);
                $input.val(newValue);
                
                // Set cursor after inserted token
                var newCursorPos = cursorPos + tokenToInsert.length;
                $input.prop('selectionStart', newCursorPos);
                $input.prop('selectionEnd', newCursorPos);
            } else {
                // Fallback: append with separator if needed
                var separator = (currentValue && !currentValue.endsWith('/') && !currentValue.match(/[\s\-_]$/)) ? '/' : '';
                $input.val(currentValue + separator + token);
            }
            
            $input.focus().trigger('input');
            CustomPermalinks.updatePreview();
            CustomPermalinks.validateStructure();
        },

        updatePreview: function() {
            var structure = $('#listeo_custom_structure').val();
            if (!structure) {
                structure = '%listing%'; // Default fallback
            }
            
            var preview = CustomPermalinks.generatePreviewUrl(structure);
            $('#listeo-structure-preview').text(preview);
        },

        generatePreviewUrl: function(structure) {
            var homeUrl = window.listeoCustomPermalinks ? window.listeoCustomPermalinks.homeUrl : 'http://example.com';
            
            // Sample data for preview
            var sampleData = {
                '%listing%': 'amazing-restaurant',
                '%listing_category%': 'restaurants',
                '%region%': 'new-york',
                '%listing_id%': '123',
                '%listing_type%': 'service',
                '%year%': new Date().getFullYear().toString(),
                '%monthnum%': ('0' + (new Date().getMonth() + 1)).slice(-2),
                '%author%': 'john-smith'
            };
            
            var preview = structure;
            for (var token in sampleData) {
                preview = preview.replace(new RegExp(token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), sampleData[token]);
            }
            
            // Handle empty tokens (replace with dash)
            preview = preview.replace(/%[^%]+%/g, '-');
            
            // Clean up slashes
            preview = preview.replace(/\/+/g, '/');
            preview = preview.replace(/^\/|\/$/g, '');
            
            // Apply Safe Mode prefix if enabled
            var safeModeEnabled = $('#listeo_permalink_safe_mode').is(':checked');
            if (safeModeEnabled) {
                // Get listing base from the existing WordPress permalink settings (don't hardcode)
                var listingBase = 'listing'; // fallback
                // Try to get the actual listing base from the current permalink settings
                var $listingBaseInput = $('input[name="listeo_listing_base_slug"]');
                if ($listingBaseInput.length && $listingBaseInput.val()) {
                    listingBase = $listingBaseInput.val();
                }
                
                // Ensure example starts with the listing base for Safe Mode
                if (preview.indexOf(listingBase + '/') !== 0) {
                    preview = listingBase + '/' + preview;
                }
            }
            
            return homeUrl + '/' + preview;
        },

        validateStructure: function() {
            var structure = $('#listeo_custom_structure').val();
            var $validation = $('#listeo-structure-validation');
            
            if (!structure) {
                $validation.html('');
                return;
            }
            
            var validation = CustomPermalinks.quickValidate(structure);
            
            if (validation.valid) {
                $validation.html('<span style="color: green;">✓ ' + validation.message + '</span>');
            } else {
                $validation.html('<span style="color: red;">✗ ' + validation.message + '</span>');
            }
        },

        quickValidate: function(structure) {
            if (!structure) {
                return {
                    valid: false,
                    message: 'Structure cannot be empty'
                };
            }
            
            if (structure.indexOf('%listing%') === -1) {
                return {
                    valid: false,
                    message: 'Structure must contain %listing%'
                };
            }
            
            // Check for unmatched % characters
            var percentCount = (structure.match(/%/g) || []).length;
            if (percentCount % 2 !== 0) {
                return {
                    valid: false,
                    message: 'Unmatched % characters'
                };
            }
            
            // Check for invalid characters
            var invalidChars = /[<>"'|?*\\]/;
            if (invalidChars.test(structure)) {
                return {
                    valid: false,
                    message: 'Contains invalid characters'
                };
            }
            
            return {
                valid: true,
                message: 'Structure looks valid'
            };
        },

        checkCompatibility: function() {
            var $warnings = $('#listeo-compatibility-warnings');
            var $list = $('#listeo-compatibility-list');
            var structure = $('#listeo_custom_structure').val();
            var warnings = [];
            
            // Clear previous warnings
            $list.empty();
            $warnings.hide();
            
            if (!structure) {
                return;
            }
            
            // Check for potential compatibility issues
            // This would ideally be done via AJAX to the server for accurate checks
            
            // For now, show basic client-side warnings
            if (structure.indexOf('%region%') === -1) {
                // Check if this might conflict with region_in_links
                warnings.push('Structure doesn\'t include %region% - may conflict with "Region in Links" setting if enabled.');
            }
            
            if (structure.indexOf('%listing_category%') === -1) {
                warnings.push('Structure doesn\'t include %listing_category% - URLs won\'t include category information.');
            }
            
            if (warnings.length > 0) {
                warnings.forEach(function(warning) {
                    $list.append('<li>' + warning + '</li>');
                });
                $warnings.show();
            }
        },

        // Initialize permalink warning functionality
        initPermalinkWarning: function() {
            this.checkPermalinkWarning();
            this.bindPermalinkWarningEvents();
        },

        // Check if WordPress permalink structure is set to "Plain" and show/hide warning
        checkPermalinkWarning: function() {
            var $warning = $('#listeo-permalink-warning');
            var isPlainPermalinks = this.isPlainPermalinksSelected();
            
            if (isPlainPermalinks) {
                $warning.show();
            } else {
                $warning.hide();
            }
        },

        // Check if Plain permalinks is selected by looking at the radio buttons
        isPlainPermalinksSelected: function() {
            // Check if Plain permalinks radio button is selected
            var $plainRadio = $('input[name="selection"][value=""]');
            return $plainRadio.length > 0 && $plainRadio.is(':checked');
        },

        // Bind events to check permalink structure changes
        bindPermalinkWarningEvents: function() {
            var self = this;
            
            // Listen for changes to permalink structure radio buttons
            $('input[name="selection"]').on('change', function() {
                self.checkPermalinkWarning();
            });
            
            // Also check when page loads
            $(document).ready(function() {
                self.checkPermalinkWarning();
            });
        },

        // Force "Day and name" permalink setting for compatibility with custom structures
        forceDayAndNamePermalinks: function() {
            // Look for the "Day and name" radio button (/%year%/%monthnum%/%day%/%postname%/)
            var $dayAndNameRadio = $('input[name="selection"][value="/%year%/%monthnum%/%day%/%postname%/"]');
            
            if ($dayAndNameRadio.length > 0 && !$dayAndNameRadio.is(':checked')) {
                // Select the "Day and name" option
                $dayAndNameRadio.prop('checked', true);
                
                // Trigger change event to update WordPress settings
                $dayAndNameRadio.trigger('change');
                
                // Show a brief notification to user
                this.showPermalinkChangeNotification();
                
                // Hide the warning since we're now using pretty permalinks
                $('#listeo-permalink-warning').hide();
            }
        },

        // Show notification that permalink structure was automatically changed
        showPermalinkChangeNotification: function() {
            // Remove any existing notification
            $('.listeo-permalink-auto-change').remove();
            
            // Create and show notification
            var $notification = $('<div class="listeo-permalink-auto-change notice notice-info is-dismissible" style="margin: 10px 0; padding: 12px; background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; border-radius: 4px;">' +
                '<p><span class="dashicons dashicons-info" style="margin-right: 8px;"></span>' +
                '<strong>Auto-updated:</strong> Permalink structure changed to "Day and name" for compatibility with custom listing permalinks.</p>' +
                '</div>');
            
            // Insert notification above the permalink settings
            $('table.form-table').first().before($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Check initial permalink settings on page load
        checkInitialPermalinkSettings: function() {
            // If custom permalinks are already enabled, ensure we have proper WordPress permalink structure
            if ($('#listeo_custom_permalinks_enabled').is(':checked')) {
                // Small delay to ensure all elements are loaded
                var self = this;
                setTimeout(function() {
                    self.forceDayAndNamePermalinks();
                }, 100);
            }

            // Mark Safe Mode as user-set if it's already checked on page load (existing installations)
            if ($('#listeo_permalink_safe_mode').is(':checked')) {
                $('#listeo_permalink_safe_mode').data('user-set', true);
            }
        },

        // Show confirmation dialog when disabling Safe Mode
        confirmDisableSafeMode: function() {
            var message = 'Are you sure you want to disable Safe Mode?\n\n' +
                         'Safe Mode helps prevent URL conflicts with your existing pages and posts. ' +
                         'Disabling it may cause some URLs to stop working properly.\n\n' +
                         'Click OK to disable or Cancel to keep Safe Mode enabled.';

            return confirm(message);
        }
    };

    // Initialize when DOM is ready
    CustomPermalinks.init();
    
    // Update compatibility check when structure changes
    $('#listeo_custom_structure').on('input', function() {
        setTimeout(CustomPermalinks.checkCompatibility, 500);
    });

    // CSS for enhanced styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            /* Main card styling */
            .listeo-permalink-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                transition: all 0.3s ease;
            }
            .listeo-permalink-card.active {
                border-color: #0073aa;
                box-shadow: 0 4px 12px rgba(0,115,170,0.1);
            }

            /* Header section */
            .listeo-permalink-header {
                display: flex;
                align-items: flex-start;
                gap: 15px;
                margin-bottom: 15px;
            }
            .listeo-permalink-icon svg {
                transition: transform 0.2s ease;
            }
            .listeo-permalink-card.active .listeo-permalink-icon svg {
                transform: scale(1.1);
            }
            .listeo-permalink-title {
                flex: 1;
            }
            .listeo-permalink-main-label {
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                margin: 0;
            }
            .listeo-permalink-label-text {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 16px;
                font-weight: 600;
                color: #23282d;
            }
            .listeo-permalink-badge {
                background: linear-gradient(135deg, #0073aa, #005177);
                color: white;
                font-size: 11px;
                font-weight: 500;
                padding: 2px 8px;
                border-radius: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .listeo-permalink-description {
                margin: 8px 0 0 0;
                color: #666;
                font-size: 14px;
                line-height: 1.4;
            }

            /* Content sections */
            .listeo-permalink-content {
                border-top: 1px solid #f0f0f1;
                padding-top: 20px;
            }
            .listeo-permalink-section {
                margin-bottom: 0px;
            }
            .listeo-permalink-section-title {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0 0 15px 0;
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
                border-bottom: 2px solid #f0f0f1;
                padding-bottom: 8px;
            }
            .listeo-permalink-section-title .dashicons {
                color: #0073aa;
                font-size: 18px;
            }

            /* Preset grid */
            .listeo-preset-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 12px;
                margin-bottom: 15px;
            }
            .listeo-preset-option {
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                padding: 12px 16px;
                cursor: pointer;
                transition: all 0.2s ease;
                background: #fafbfc;
            }
            .listeo-preset-option:hover {
                border-color: #0073aa;
                background: #f6f9fc;
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(0,115,170,0.1);
            }
            .listeo-preset-option.selected {
                border-color: #0073aa;
                background: #e7f3ff;
                box-shadow: 0 0 0 1px rgba(0,115,170,0.2);
            }
            .listeo-preset-label {
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 4px;
            }
            .listeo-preset-example {
                font-family: Monaco, Consolas, monospace;
                font-size: 12px;
                color: #0073aa;
                background: white;
                padding: 4px 8px;
                border-radius: 4px;
                margin-bottom: 6px;
            }
            .listeo-preset-description {
                font-size: 12px;
                color: #666;
                line-height: 1.3;
            }

            /* Token grid */
            .listeo-token-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 10px;
                margin-bottom: 15px;
            }
            .listeo-token-card {
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                padding: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
                background: #fafbfc;
                text-align: center;
            }
            .listeo-token-card:hover {
                border-color: #0073aa;
                background: #f6f9fc;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,115,170,0.15);
            }
            .listeo-token-card.clicked {
                animation: tokenClick 0.2s ease;
            }
            @keyframes tokenClick {
                0% { transform: translateY(-2px) scale(1); }
                50% { transform: translateY(-2px) scale(0.95); }
                100% { transform: translateY(-2px) scale(1); }
            }
            .listeo-token-name {
                font-weight: 600;
                color: #1d2327;
                font-size: 13px;
                margin-bottom: 4px;
            }
            .listeo-token-required {
                color: #dc3232;
                font-weight: bold;
            }
            .listeo-token-example {
                font-size: 11px;
                color: #0073aa;
                margin-bottom: 4px;
            }
            .listeo-token-code {
                font-family: Monaco, Consolas, monospace;
                font-size: 10px;
                color: #666;
                background: white;
                padding: 2px 6px;
                border-radius: 3px;
                border: 1px solid #ddd;
            }
            .listeo-token-help {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
                color: #666;
                margin: 0;
            }

            /* Structure builder */
            .listeo-structure-builder {
                display: grid;
                grid-template-columns: 1fr 300px;
                gap: 20px;
                align-items: start;
            }
            @media (max-width: 900px) {
                .listeo-structure-builder {
                    grid-template-columns: 1fr;
                    gap: 15px;
                }
            }
            .listeo-structure-input-wrapper {
                position: relative;
            }
            .listeo-structure-input {
                width: 100%;
                padding: 12px 16px;
                font-family: Monaco, Consolas, monospace;
                font-size: 14px;
                border: 2px solid #ddd;
                border-radius: 6px;
                transition: border-color 0.2s ease;
                background: #fafbfc;
            }
            .listeo-structure-input:focus {
                border-color: #0073aa;
                background: white;
                box-shadow: 0 0 0 2px rgba(0,115,170,0.1);
            }
            .listeo-structure-validation {
                margin-top: 8px;
                font-size: 13px;
                min-height: 20px;
            }
            .listeo-structure-preview-wrapper {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                padding: 16px;
            }
            .listeo-preview-label {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 8px;
            }
            .listeo-structure-preview {
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 10px;
                font-family: Monaco, Consolas, monospace;
                font-size: 12px;
                color: #0073aa;
                word-break: break-all;
                line-height: 1.4;
            }

            /* Advanced options */
            .listeo-advanced-options {
                background: #f8f9fa;
                border-radius: 6px;
                padding: 16px;
            }
            .listeo-checkbox-label {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                cursor: pointer;
                margin: 0;
            }
            .listeo-checkbox-text {
                flex: 1;    margin-top: -5px;
            }
            .listeo-checkbox-desc {
                display: block;
                font-weight: normal;
                color: #666;
                font-size: 13px;
                margin-top: 2px;
            }

            /* Compatibility warnings */
            .listeo-compatibility-notice {
                background: #fff8e5;
                border: 1px solid #ffcc00;
                border-radius: 6px;
                padding: 16px;
                margin-top: 15px;
            }
            .listeo-compatibility-header {
                display: flex;
                align-items: center;
                gap: 8px;
                color: #b8860b;
                margin-bottom: 10px;
            }
            .listeo-compatibility-list {
                margin: 0;
                padding-left: 20px;
            }
        `)
        .appendTo('head');
});