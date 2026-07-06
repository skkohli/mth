/* ----------------- Start Document ----------------- */
(function ($) {
  "use strict";

  $(document).ready(function () {
    $(document).on("click", ".listeo-health-check-table-pages .button", function (e) {
      e.preventDefault();
      if (window.confirm("Are you sure?")) {
        var $this = $(this);

        // preparing data for ajax
        var ajax_data = {
          action: "listeo_recreate_page",
          page: $this.data("page"),
          //'nonce': nonce
        };
        $.ajax({
          type: "POST",
          dataType: "json",
          url: ajaxurl,
          data: ajax_data,

          success: function (data) {
            // display loader class
            location.reload();
          },
        });
      }
    });

    // Handle memory limit update button
    $(document).on("click", ".listeo-memory-limit-fix", function (e) {
      e.preventDefault();
      
      var $this = $(this);
      var memoryLimit = $this.data("memory-limit") || "256M";
      
      if (window.confirm("Are you sure you want to update the WordPress memory limit to " + memoryLimit + "? A backup of wp-config.php will be created.")) {
        // Show loading state
        $this.prop('disabled', true).text('Updating...');
        
        // Preparing data for AJAX
        var ajax_data = {
          action: "listeo_update_memory_limit",
          memory_limit: memoryLimit,
          nonce: listeo_site_health_vars.memory_limit_nonce
        };
        
        $.ajax({
          type: "POST",
          dataType: "json",
          url: ajaxurl,
          data: ajax_data,
          
          success: function (response) {
            if (response.success) {
              alert("Success: " + response.data.message + "\nBackup created: " + response.data.backup_created);
              location.reload();
            } else {
              alert("Error: " + (response.data.message || "Unknown error occurred"));
              $this.prop('disabled', false).text('Fix Memory Limit');
            }
          },
          
          error: function () {
            alert("Error: Failed to communicate with server");
            $this.prop('disabled', false).text('Fix Memory Limit');
          }
        });
      }
    });

    // Handle granular debug control buttons
    $(document).on("click", ".listeo-debug-control", function (e) {
      e.preventDefault();
      
      var $this = $(this);
      var debugAction = $this.data("debug-action");
      var originalText = $this.text();
      
      // Create user-friendly confirmation messages
      var confirmMessages = {
        'enable_full': 'enable full debug mode (includes frontend error display)',
        'disable_all': 'turn off all debug features',
        'enable_logging': 'enable error logging only (recommended for production)',
        'disable_display': 'hide errors from frontend visitors'
      };
      
      var confirmText = confirmMessages[debugAction] || 'update debug settings';
      
      if (window.confirm("Are you sure you want to " + confirmText + "? A backup of wp-config.php will be created.")) {
        // Show loading state
        $this.prop('disabled', true).text('Updating...');
        
        // Preparing data for AJAX
        var ajax_data = {
          action: "listeo_toggle_debug_mode",
          debug_action: debugAction,
          nonce: listeo_site_health_vars.debug_toggle_nonce
        };
        
        $.ajax({
          type: "POST",
          dataType: "json",
          url: ajaxurl,
          data: ajax_data,
          
          success: function (response) {
            if (response.success) {
              alert("Success: " + response.data.message + "\nBackup created: " + response.data.backup_created);
              location.reload();
            } else {
              alert("Error: " + (response.data.message || "Unknown error occurred"));
              $this.prop('disabled', false).text(originalText);
            }
          },
          
          error: function () {
            alert("Error: Failed to communicate with server");
            $this.prop('disabled', false).text(originalText);
          }
        });
      }
    });

    // Handle test email button
    $(document).on("click", ".listeo-test-email", function (e) {
      e.preventDefault();
      
      var $this = $(this);
      var originalText = $this.text();
      
      // Get email from input field
      var testEmail = $('#test_email_input').val().trim();
      
      // Basic email validation
      if (!testEmail) {
        alert('Please enter an email address');
        return;
      }
      
      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(testEmail)) {
        alert('Please enter a valid email address');
        return;
      }
      
      if (window.confirm('Send test email to ' + testEmail + '?')) {
        // Show loading state
        $this.prop('disabled', true).text('Sending...');
        
        // Preparing data for AJAX
        var ajax_data = {
          action: "listeo_test_email",
          test_email: testEmail,
          nonce: listeo_site_health_vars.test_email_nonce
        };
        
        $.ajax({
          type: "POST",
          dataType: "json",
          url: ajaxurl,
          data: ajax_data,
          
          success: function (response) {
            if (response.success) {
              alert("Success: " + response.data.message);
            } else {
              alert("Error: " + (response.data.message || "Unknown error occurred"));
            }
            $this.prop('disabled', false).text(originalText);
          },
          
          error: function () {
            alert("Error: Failed to communicate with server");
            $this.prop('disabled', false).text(originalText);
          }
        });
      }
    });

    // Handle booking email test button
    $(document).on("click", ".listeo-test-booking-email", function (e) {
      e.preventDefault();

      var $this = $(this);
      var originalText = $this.text();
      var $resultDiv = $('#booking-email-test-result');

      // Get selected email type
      var emailType = $('#booking_email_type').val();

      // Validation
      if (!emailType) {
        $resultDiv.html('<div class="notice notice-error" style="padding: 12px; margin-top: 15px;"><strong>Error:</strong> Please select an email type first.</div>');
        return;
      }

      var emailTypeName = $('#booking_email_type option:selected').text();

      if (window.confirm('Send test booking email "' + emailTypeName + '" to ' + listeo_site_health_vars.admin_email + '?')) {
        // Show loading state
        $this.prop('disabled', true).text('Sending...');
        $resultDiv.html('<div class="notice notice-info" style="padding: 12px; margin-top: 15px;"><strong>Sending...</strong> Please wait while the test email is being sent.</div>');

        // Preparing data for AJAX
        var ajax_data = {
          action: "listeo_test_booking_email",
          email_type: emailType,
          nonce: listeo_site_health_vars.booking_email_test_nonce
        };

        $.ajax({
          type: "POST",
          dataType: "json",
          url: listeo_site_health_vars.ajax_url,
          data: ajax_data,

          success: function (response) {
            if (response.success) {
              $resultDiv.html('<div class="notice notice-success" style="padding: 12px; margin-top: 15px;"><strong>Success!</strong> ' + response.data.message + '</div>');
            } else {
              $resultDiv.html('<div class="notice notice-error" style="padding: 12px; margin-top: 15px;"><strong>Error:</strong> ' + (response.data.message || "Unknown error occurred") + '</div>');
            }
            $this.prop('disabled', false).text(originalText);
          },

          error: function (xhr, status, error) {
            $resultDiv.html('<div class="notice notice-error" style="padding: 12px; margin-top: 15px;"><strong>Error:</strong> Failed to communicate with server. ' + error + '</div>');
            $this.prop('disabled', false).text(originalText);
          }
        });
      }
    });

    // Heartbeat monitoring functionality
    var heartbeatMonitor = {
      init: function() {
        this.loadHeartbeatData();
        this.bindEvents();
      },

      bindEvents: function() {
        var self = this;

        // Heartbeat control buttons
        $(document).on('click', '.heartbeat-btn', function(e) {
          e.preventDefault();
          var action = $(this).data('action');
          if (action) {
            self.updateHeartbeatSettings(action, $(this));
          }
        });
      },

      loadHeartbeatData: function() {
        var self = this;
        
        // Show loading state
        $('#heartbeat-interval').text('--');
        $('#heartbeat-verdict').text('--');
        $('#heartbeat-message').text('Loading...');
        
        // AJAX call to get heartbeat status
        $.ajax({
          type: 'POST',
          dataType: 'json',
          url: listeo_site_health_vars.ajax_url,
          data: {
            action: 'listeo_get_heartbeat_status',
            nonce: listeo_site_health_vars.heartbeat_nonce
          },
          success: function(response) {
            if (response.success) {
              self.updateHeartbeatDisplay(response.data.heartbeat);
            } else {
              self.showError('Failed to load heartbeat data: ' + (response.data.message || 'Unknown error'));
            }
          },
          error: function() {
            self.showError('Failed to communicate with server');
          }
        });
      },

      updateHeartbeatDisplay: function(heartbeat) {
        // Update heartbeat interval display
        $('#heartbeat-interval').text(heartbeat.current_interval + 's');
        $('#heartbeat-message').text(heartbeat.message);
        
        // Update status indicator
        var indicator = $('#heartbeat-indicator');
        indicator.removeClass('status-good status-warning status-critical')
                 .addClass('status-' + heartbeat.status);
        
        // Update combined box status
        var statusBox = $('#heartbeat-status-banner');
        statusBox.removeClass('status-good status-warning status-critical')
                 .addClass('status-' + heartbeat.status);
        
        // Update verdict text based on thresholds
        var verdict = '';
        if (heartbeat.status === 'critical') {
          verdict = '⚠️ Problematic Setting - Can Cause Server Issues';
        } else if (heartbeat.status === 'warning') {
          verdict = '⚡ Could Be Optimized';
        } else {
          verdict = '✅ Well Optimized';
        }
        $('#heartbeat-verdict').text(verdict);
      },

      updateHeartbeatSettings: function(action, button) {
        var self = this;
        var originalText = button.text();
        
        var actionTexts = {
          'normal': 'set heartbeat to Normal mode (60s)',
          'optimize': 'set heartbeat to Safe mode (120s)',
          'development': 'set heartbeat to Super Safe mode (360s)',
          'disable_frontend': 'disable WordPress Heartbeat completely'
        };
        
        var confirmText = actionTexts[action] || 'update heartbeat settings';
        
        if (!confirm('Are you sure you want to ' + confirmText + '? This will modify wp-config.php and create a backup.')) {
          return;
        }
        
        button.prop('disabled', true).text('Updating...');
        
        $.ajax({
          type: 'POST',
          dataType: 'json',
          url: listeo_site_health_vars.ajax_url,
          data: {
            action: 'listeo_update_heartbeat_settings',
            action_type: action,
            nonce: listeo_site_health_vars.heartbeat_nonce
          },
          success: function(response) {
            if (response.success) {
              alert('Success: ' + response.data.message + '\nBackup created: ' + response.data.backup_created);
              self.loadHeartbeatData(); // Refresh heartbeat data
            } else {
              alert('Error: ' + (response.data.message || 'Unknown error'));
            }
          },
          error: function() {
            alert('Error: Failed to communicate with server');
          },
          complete: function() {
            button.prop('disabled', false).text(originalText);
          }
        });
      },

      showError: function(message) {
        $('#heartbeat-interval').text('--');
        $('#heartbeat-verdict').text('Error');
        $('#heartbeat-message').text('❌ ' + message);
      }
    };

    // Initialize heartbeat monitoring if on Listeo site health tab
    if ($('.listeo-heartbeat-section').length > 0) {
      heartbeatMonitor.init();
    }
    
    // Database health monitoring functionality
    var databaseHealthMonitor = {
      init: function() {
        this.loadDatabaseStats();
        this.bindEvents();
      },

      bindEvents: function() {
        var self = this;

        // Transient cleanup buttons
        $(document).on('click', '.listeo-cleanup-transients', function(e) {
          e.preventDefault();
          var cleanupType = $(this).data('cleanup-type');
          var confirmMessage = self.getConfirmMessage('transients', cleanupType);
          
          if (window.confirm(confirmMessage)) {
            self.performTransientCleanup(cleanupType, $(this));
          }
        });

        // Revision cleanup buttons
        $(document).on('click', '.listeo-cleanup-revisions', function(e) {
          e.preventDefault();
          var cleanupType = $(this).data('cleanup-type');
          var keepRevisions = $('#keep-revisions-count').val() || 2;
          var confirmMessage = self.getConfirmMessage('revisions', cleanupType, keepRevisions);
          
          if (window.confirm(confirmMessage)) {
            self.performRevisionCleanup(cleanupType, keepRevisions, $(this));
          }
        });

        // Refresh database stats button (if added later)
        $(document).on('click', '.refresh-database-stats', function(e) {
          e.preventDefault();
          self.loadDatabaseStats();
        });
      },

      loadDatabaseStats: function() {
        var self = this;
        
        // Show loading states
        $('#database-stats-loading').show();
        $('#database-stats-content').hide();
        $('#transient-cleanup-actions').hide();
        $('#revision-cleanup-actions').hide();
        
        // AJAX call to get database statistics
        $.ajax({
          type: 'POST',
          dataType: 'json',
          url: listeo_site_health_vars.ajax_url,
          data: {
            action: 'listeo_get_database_stats',
            nonce: listeo_site_health_vars.database_nonce
          },
          success: function(response) {
            if (response.success) {
              self.displayDatabaseStats(response.data);
            } else {
              self.showError('Failed to load database stats: ' + (response.data.message || 'Unknown error'));
            }
          },
          error: function() {
            self.showError('Failed to communicate with server');
          }
        });
      },

      displayDatabaseStats: function(data) {
        var self = this;
        
        // Hide loading, show content
        $('#database-stats-loading').hide();
        $('#database-stats-content').show();
        
        // Display transient stats
        self.displayTransientStats(data.transients);
        
        // Display revision stats
        self.displayRevisionStats(data.revisions);
        
        // Display database overview
        self.displayDatabaseOverview(data.database);
        
        // Show cleanup actions
        $('#transient-cleanup-actions').show();
        $('#revision-cleanup-actions').show();
      },

      displayTransientStats: function(stats) {
        var statusClass = 'status-' + stats.status;
        var statusIcon = stats.status === 'good' ? '✅' : (stats.status === 'warning' ? '⚠️' : '❌');

        var html = '<div class="transient-stats ' + statusClass + '">';
        html += '<div class="stats-summary">';
        html += '<div class="stats-info-message" style="padding: 12px 15px; margin: 0 0 15px 0; background: #e5f5fa; border-left: 4px solid #00a0d2; border-radius: 4px;">';
        html += '<p style="margin: 0; font-size: 14px; line-height: 1.6; color: #045a75;">';
        html += '<strong>What are transients?</strong><br>';
        html += 'Transients are temporary data stored by plugins to speed up your site. Think of them like sticky notes that remember search results, listing data, and API responses. Over time, expired transients can accumulate and slow down your database. Cleaning them is safe and can improve performance.';
        html += '</p></div>';
        html += '</div>';
        
        // Grid-style stats display
        html += '<div class="db-stats-grid transient-stats-grid">';
        html += '<div class="db-stat-card"><h5>Total Transients</h5><span class="db-size">' + stats.total.toLocaleString() + '</span><small>All plugin cache & temporary data</small></div>';
        html += '<div class="db-stat-card"><h5>Expired</h5><span class="db-size">' + stats.expired.toLocaleString() + '</span><small>Old cache entries (safe to remove)</small></div>';
        html += '<div class="db-stat-card"><h5>Listeo Specific</h5><span class="db-size">' + stats.listeo_specific.toLocaleString() + '</span><small>Listeo search & listing cache</small></div>';
        html += '<div class="db-stat-card"><h5>Autoloaded</h5><span class="db-size">' + stats.autoloaded.toLocaleString() + '</span><small>Cache loaded on every page visit</small></div>';
        html += '<div class="db-stat-card"><h5>Total Size</h5><span class="db-size">' + stats.total_size_formatted + '</span><small>Database space used by cache</small></div>';
        html += '</div>';
        
        html += '</div>';
        
        $('#transient-stats-display').html(html);
      },

      displayRevisionStats: function(stats) {
        var statusClass = 'status-' + stats.status;
        var statusIcon = stats.status === 'good' ? '✅' : (stats.status === 'warning' ? '⚠️' : '❌');
        
        var html = '<div class="revision-stats ' + statusClass + '">';
        html += '<div class="stats-summary">';
        html += '<div class="stats-message">' + statusIcon + ' ' + stats.message + '</div>';
        html += '</div>';
        
        // Grid-style stats display
        html += '<div class="db-stats-grid revision-stats-grid">';
        html += '<div class="db-stat-card"><h5>Total Revisions</h5><span class="db-size">' + stats.total.toLocaleString() + '</span><small>All pages, posts & listing history</small></div>';
        html += '<div class="db-stat-card"><h5>Listing Revisions</h5><span class="db-size">' + stats.listing_revisions.toLocaleString() + '</span><small>Listeo business listing versions</small></div>';
        html += '<div class="db-stat-card"><h5>Meta Entries</h5><span class="db-size">' + stats.meta_entries.toLocaleString() + '</span><small>Elementor & custom field history</small></div>';
        html += '<div class="db-stat-card"><h5>Total Size</h5><span class="db-size">' + stats.total_size_formatted + '</span><small>Database space used by revisions</small></div>';
        html += '</div>';
        
        html += '</div>';
        
        $('#revision-stats-display').html(html);
      },

      displayDatabaseOverview: function(stats) {
        var html = '<div class="database-overview">';
        html += '<div class="db-stats-grid">';
        html += '<div class="db-stat-card"><h5>Total Database</h5><span class="db-size">' + stats.total_size_mb + ' MB</span><small>Complete WordPress database</small></div>';
        html += '<div class="db-stat-card"><h5>Options Table</h5><span class="db-size">' + stats.options_size_mb + ' MB</span><small>Settings & cache data</small></div>';
        html += '<div class="db-stat-card"><h5>Posts Table</h5><span class="db-size">' + stats.posts_size_mb + ' MB</span><small>Pages, posts & revisions</small></div>';
        html += '<div class="db-stat-card"><h5>Post Meta</h5><span class="db-size">' + stats.postmeta_size_mb + ' MB</span><small>Elementor & custom fields</small></div>';
        html += '</div></div>';
        
        $('#database-stats-content').html(html);
      },

      getConfirmMessage: function(type, cleanupType, keepRevisions) {
        var messages = {
          transients: {
            expired: "Remove expired transients only? This is the safest option and will improve performance.",
            listeo_only: "Remove all Listeo-specific transients? These will be regenerated when needed.",
            all: "⚠️ WARNING: Remove ALL transients? This will delete cache for all plugins and may temporarily slow your site."
          },
          revisions: {
            keep_recent: "Keep " + keepRevisions + " revision(s) per post and delete the rest? This will reduce database size.",
            listing_only: "Delete ALL listing revisions? This will remove version history for listings only.",
            all: "⚠️ WARNING: Delete ALL post revisions? This cannot be undone and removes all version history."
          }
        };
        
        return messages[type][cleanupType] || "Are you sure you want to proceed?";
      },

      performTransientCleanup: function(cleanupType, button) {
        var self = this;
        var originalText = button.text();
        
        // Show loading state
        button.prop('disabled', true).text('Cleaning...');
        $('#transient-cleanup-results').hide();
        
        $.ajax({
          type: 'POST',
          dataType: 'json',
          url: listeo_site_health_vars.ajax_url,
          data: {
            action: 'listeo_cleanup_transients',
            cleanup_type: cleanupType,
            nonce: listeo_site_health_vars.cleanup_nonce
          },
          success: function(response) {
            if (response.success) {
              var html = '<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>';
              $('#transient-cleanup-results').html(html).show();
              
              // Refresh stats after a short delay
              setTimeout(function() {
                self.loadDatabaseStats();
              }, 1000);
            } else {
              var html = '<div class="notice notice-error"><p>❌ ' + (response.data.message || 'Unknown error') + '</p></div>';
              $('#transient-cleanup-results').html(html).show();
            }
          },
          error: function() {
            var html = '<div class="notice notice-error"><p>❌ Failed to communicate with server</p></div>';
            $('#transient-cleanup-results').html(html).show();
          },
          complete: function() {
            button.prop('disabled', false).text(originalText);
          }
        });
      },

      performRevisionCleanup: function(cleanupType, keepRevisions, button) {
        var self = this;
        var originalText = button.text();
        
        // Show loading state
        button.prop('disabled', true).text('Cleaning...');
        $('#revision-cleanup-results').hide();
        
        $.ajax({
          type: 'POST',
          dataType: 'json',
          url: listeo_site_health_vars.ajax_url,
          data: {
            action: 'listeo_cleanup_revisions',
            cleanup_type: cleanupType,
            keep_revisions: keepRevisions,
            nonce: listeo_site_health_vars.cleanup_nonce
          },
          success: function(response) {
            if (response.success) {
              var html = '<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>';
              $('#revision-cleanup-results').html(html).show();
              
              // Refresh stats after a short delay
              setTimeout(function() {
                self.loadDatabaseStats();
              }, 1000);
            } else {
              var html = '<div class="notice notice-error"><p>❌ ' + (response.data.message || 'Unknown error') + '</p></div>';
              $('#revision-cleanup-results').html(html).show();
            }
          },
          error: function() {
            var html = '<div class="notice notice-error"><p>❌ Failed to communicate with server</p></div>';
            $('#revision-cleanup-results').html(html).show();
          },
          complete: function() {
            button.prop('disabled', false).text(originalText);
          }
        });
      },

      showError: function(message) {
        $('#database-stats-loading').hide();
        var html = '<div class="notice notice-error"><p>❌ ' + message + '</p></div>';
        $('#database-stats-content').html(html).show();
      }
    };

    // Initialize database health monitoring if on Listeo site health tab
    if ($('.listeo-database-section').length > 0) {
      databaseHealthMonitor.init();
    }
    
  });
})(this.jQuery);
