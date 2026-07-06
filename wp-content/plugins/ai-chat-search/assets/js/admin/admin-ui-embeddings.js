/**
 * AI Chat Search - Admin Embeddings Module
 *
 * Handles embedding generation, batch processing, viewer, and search.
 *
 * @package AI_Chat_Search
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var AIRS = window.AIRS || {};
    var i18n = window.listeo_ai_search_i18n || {};

    // Batch processing state
    var regenerationRunning = false;
    var currentOffset = 0;
    var totalListings = 0;
    var activeBatchLogEntry = null;

    function getAjaxErrorMessage(response, fallback) {
        if (response && response.message) {
            return response.message;
        }
        if (response && response.data) {
            if (typeof response.data === 'string') {
                return response.data;
            }
            if (response.data.message) {
                return response.data.message;
            }
            if (response.data.error) {
                return response.data.error;
            }
        }
        return fallback || 'Unknown error';
    }

    function escapeMessage(message) {
        if (AIRS.escapeHtml) {
            return AIRS.escapeHtml(message);
        }
        return String(message || '').replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[match];
        });
    }

    /**
     * Initialize batch embedding generation
     */
    function initBatchGeneration() {
        // Start button - check API key first, then show appropriate modal
        $('#start-regeneration').on('click', function() {
            var ajaxVars = window.listeo_ai_search_ajax || {};
            if (!ajaxVars.has_api_key) {
                $('#api-key-missing-modal').fadeIn(200);
                return;
            }
            $('#training-confirm-modal').fadeIn(200);
        });

        // API key missing modal - close
        $('#api-key-missing-close-btn, #api-key-missing-modal .airs-modal-overlay').on('click', function() {
            $('#api-key-missing-modal').fadeOut(200);
        });

        // API key missing modal - go to settings
        $('#api-key-missing-settings-btn').on('click', function() {
            var ajaxVars = window.listeo_ai_search_ajax || {};
            window.location.href = ajaxVars.settings_url || '?page=ai-chat-search&tab=ai-chat';
        });

        // Modal cancel
        $('#training-cancel-btn, #training-confirm-modal .airs-modal-overlay').on('click', function() {
            $('#training-confirm-modal').fadeOut(200);
        });

        // Modal confirm - start batch processing
        $('#training-confirm-btn').on('click', function() {
            $('#training-confirm-modal').fadeOut(200);

            regenerationRunning = true;
            currentOffset = 0;
            totalListings = 0;

            $('#start-regeneration').hide();
            $('#stop-regeneration').show();
            $('#regeneration-progress').show();
            $('#regeneration-log').show();
            $('#log-content').empty();
            activeBatchLogEntry = null;

            logMessage(i18n.startingGeneration || 'Preparing training run');
            runRegenerationBatch();
        });

        // Stop button
        $('#stop-regeneration').on('click', function() {
            regenerationRunning = false;
            $('#start-regeneration').show();
            $('#stop-regeneration').hide();
            finishActiveBatchLog(i18n.stoppedByUser || 'Training stopped by user.', 'warning');
        });
    }

    /**
     * Run a single batch of embedding generation
     */
    function runRegenerationBatch() {
        if (!regenerationRunning) return;

        var batchSize = 20; // Reduced to prevent PHP timeout

        startActiveBatchLog(getBatchRangeLabel(batchSize));

        AIRS.ajax({
            action: 'listeo_ai_manage_database',
            data: {
                database_action: 'start_regeneration',
                batch_size: batchSize,
                start_offset: currentOffset
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;

                    // Set total from first response
                    if (data.total_listings && totalListings === 0) {
                        totalListings = data.total_listings;
                    }

                    // Update offset
                    if (typeof data.next_offset !== 'undefined') {
                        currentOffset = data.next_offset;
                    }

                    // Log progress only for real work; processed 0 is the completion sentinel.
                    var processed = data.processed || 0;
                    var progressPercent = totalListings > 0 ? Math.round((currentOffset / totalListings) * 100) : 0;
                    if (processed > 0) {
                        finishActiveBatchLog(
                            processed + ' ' + (i18n.itemsProcessed || 'items trained.') +
                            ' ' + (i18n.progress || 'Complete:') + ' ' + progressPercent + '% (' +
                            currentOffset + '/' + totalListings + ')',
                            'success'
                        );
                    } else {
                        removeActiveBatchLog();
                    }

                    // Log errors if any
                    if (data.errors && data.errors.length > 0) {
                        logMessage((i18n.batchHadErrors || 'Batch had') + ' ' +
                            data.errors.length + ' ' + (i18n.errors || 'errors:'), 'error');

                        data.errors.slice(0, 3).forEach(function(error) {
                            logMessage(error, 'error');
                        });

                        if (data.errors.length > 3) {
                            logMessage((i18n.andMore || 'and') + ' ' +
                                (data.errors.length - 3) + ' ' + (i18n.moreErrors || 'more errors'), 'error');
                        }
                    }

                    if (processed === 0 && data.errors && data.errors.length > 0) {
                        finishActiveBatchLog((i18n.batchFailed || 'Batch failed:') + ' ' + data.errors[0], 'error');
                        finishRegeneration(i18n.generationFailed || 'Training failed.', 'error');
                        return;
                    }

                    // Check completion
                    if (data.status === 'complete' || data.processed === 0) {
                        finishRegeneration(i18n.generationComplete || 'Training completed successfully!', 'success');
                        if (typeof AIRS.refreshDatabaseStatus === 'function') {
                            AIRS.refreshDatabaseStatus();
                        }
                    } else if (regenerationRunning) {
                        setTimeout(runRegenerationBatch, 1000);
                    }
                } else {
                    finishActiveBatchLog((i18n.batchFailed || 'Batch failed:') + ' ' +
                        getAjaxErrorMessage(response), 'error');
                    finishRegeneration(i18n.generationFailed || 'Training failed.', 'error');
                }
            },
            error: function(xhr, status, error) {
                finishActiveBatchLog((i18n.ajaxError || 'AJAX error:') + ' ' + error, 'error');
                finishRegeneration(i18n.connectionError || 'Training failed due to connection error.', 'error');
            }
        });
    }

    /**
     * Finish batch regeneration
     */
    function finishRegeneration(message, type) {
        regenerationRunning = false;
        $('#start-regeneration').show();
        $('#stop-regeneration').hide();
        $('#regeneration-progress').hide();
        logMessage(message, type || 'success');
    }

    /**
     * Log message to regeneration log
     */
    function logMessage(message, type) {
        var $entry = buildLogEntry(message, type || 'info');

        $('#log-content').append($entry);
        scrollLogToBottom();

        return $entry;
    }

    /**
     * Add or replace the currently running batch row.
     */
    function startActiveBatchLog(message) {
        if (activeBatchLogEntry && activeBatchLogEntry.length) {
            activeBatchLogEntry.remove();
        }

        activeBatchLogEntry = logMessage(message, 'running');
    }

    /**
     * Convert the running batch row into a final status.
     */
    function finishActiveBatchLog(message, type) {
        if (!activeBatchLogEntry || !activeBatchLogEntry.length) {
            logMessage(message, type);
            return;
        }

        updateLogEntry(activeBatchLogEntry, message, type);
        activeBatchLogEntry = null;
        scrollLogToBottom();
    }

    /**
     * Remove the running batch row without logging it.
     */
    function removeActiveBatchLog() {
        if (activeBatchLogEntry && activeBatchLogEntry.length) {
            activeBatchLogEntry.remove();
        }

        activeBatchLogEntry = null;
    }

    /**
     * Build the visible range for the next batch.
     */
    function getBatchRangeLabel(batchSize) {
        var start = currentOffset + 1;
        var end = currentOffset + batchSize;

        if (totalListings > 0) {
            end = Math.min(end, totalListings);
        }

        return (i18n.processingBatch || 'Training items') + ' ' + start + '-' + end;
    }

    /**
     * Build a single structured log row.
     */
    function buildLogEntry(message, type) {
        var timestamp = new Date().toLocaleTimeString();
        var $entry = $('<div>', {
            class: 'airs-log-entry airs-log-entry--' + type
        });

        $entry.append($('<span>', {
            class: 'airs-log-time',
            text: timestamp
        }));
        $entry.append($('<span>', {
            class: 'airs-log-icon',
            html: getLogIcon(type),
            'aria-hidden': 'true'
        }));
        $entry.append($('<span>', {
            class: 'airs-log-message',
            text: message
        }));

        if (type === 'running') {
            appendLoadingDots($entry.find('.airs-log-message'));
        }

        return $entry;
    }

    /**
     * Update an existing log row with a new final status.
     */
    function updateLogEntry($entry, message, type) {
        $entry
            .removeClass('airs-log-entry--info airs-log-entry--running airs-log-entry--success airs-log-entry--warning airs-log-entry--error')
            .addClass('airs-log-entry--' + type);
        $entry.find('.airs-log-time').text(new Date().toLocaleTimeString());
        $entry.find('.airs-log-icon').html(getLogIcon(type));
        $entry.find('.airs-log-message').text(message);

        if (type === 'running') {
            appendLoadingDots($entry.find('.airs-log-message'));
        }
    }

    /**
     * Append animated loading dots to running rows.
     */
    function appendLoadingDots($message) {
        var $dots = $('<span>', {
            class: 'airs-log-dots',
            'aria-hidden': 'true'
        });

        $dots.append($('<span>').text('.'));
        $dots.append($('<span>').text('.'));
        $dots.append($('<span>').text('.'));
        $message.append($dots);
    }

    /**
     * Return a static SVG icon for a log status.
     */
    function getLogIcon(type) {
        var icons = {
            running: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M12 18v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m16.24 16.24 2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="m16.24 7.76 2.83-2.83"/></svg>',
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
        };

        return icons[type] || icons.info;
    }

    /**
     * Auto-scroll log to bottom.
     */
    function scrollLogToBottom() {
        var logElement = $('#log-content')[0];
        if (logElement) {
            logElement.scrollTop = logElement.scrollHeight;
        }
    }

    /**
     * Initialize check/regenerate single embedding
     */
    function initSingleEmbedding() {
        // Check embedding button
        $('#check-embedding').on('click', function() {
            var listingId = $('#listing-id-input').val();
            if (!listingId) {
                alert(i18n.enterListingId || 'Please enter a listing ID');
                return;
            }
            checkEmbeddingData(listingId);
        });

        // Regenerate embedding button
        $('#regenerate-embedding').on('click', function() {
            var listingId = $('#regenerate-listing-id-input').val();
            if (!listingId || listingId <= 0) {
                alert(i18n.enterValidListingId || 'Please enter a valid listing ID');
                return;
            }

            var $button = $(this);
            var $result = $('#regenerate-embedding-result');

            AIRS.setButtonState($button, 'loading');
            $result.removeClass('success error').html('').hide();


            AIRS.ajax({
                action: 'listeo_ai_regenerate_embedding',
                data: { listing_id: listingId },
                success: function(response) {

                    if (response.success) {
                        $result.removeClass('error').addClass('success')
                            .html(response.data.message)
                            .css('color', '#46b450')
                            .show();
                        $('#regenerate-listing-id-input').val('');
                    } else {
                        $result.removeClass('success').addClass('error')
                            .html(response.data.message || i18n.regenerationFailed || 'Regeneration failed')
                            .css('color', '#dc3232')
                            .show();
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('success').addClass('error')
                        .html('\u274c ' + (i18n.connectionFailed || 'Connection failed:') + ' ' + error)
                        .css('color', '#dc3232')
                        .show();
                },
                complete: function() {
                    AIRS.setButtonState($button, 'reset');
                }
            });
        });

        // Close embedding viewer
        $('#close-embedding').on('click', function() {
            $('#embedding-viewer').hide();
        });
    }

    /**
     * Check embedding data for a listing
     */
    function checkEmbeddingData(listingId) {
        $('#embedding-content').html('<p><span class="airs-spinner" style="margin-right: 6px;"></span>' +
            (i18n.loadingEmbedding || 'Loading embedding data for listing') + ' ' + listingId + '...</p>');
        $('#embedding-viewer').show();

        $('html, body').animate({
            scrollTop: $('#embedding-viewer').offset().top - 50
        }, 300);

        AIRS.ajax({
            action: 'listeo_ai_manage_database',
            data: {
                database_action: 'get_embedding',
                listing_id: listingId
            },
            success: function(response) {
                if (response.success) {
                    displayEmbeddingData(response.data);
                } else {
                    $('#embedding-content').html('<p style="color: red;">' +
                        (i18n.error || 'Error:') + ' ' + escapeMessage(getAjaxErrorMessage(response)) + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Embedding AJAX Error:', xhr, status, error);
                $('#embedding-content').html('<p style="color: red;">' +
                    (i18n.ajaxError || 'AJAX error:') + ' ' + error + '</p>');
            }
        });
    }

    /**
     * Display embedding data in viewer
     */
    function displayEmbeddingData(data) {
        var html = '';

        // Header for chunks vs regular listings
        if (data.is_chunk && data.parent_id) {
            html += '<h4>Chunk #' + data.listing_id + ' (Part ' + data.chunk_number + '/' + data.chunk_total + ')</h4>';
            html += '<p><strong>' + (i18n.parent || 'Parent') + ':</strong> <a href="#" class="embedding-link" data-id="' +
                data.parent_id + '">' + AIRS.escapeHtml(data.parent_title) + '</a> (#' + data.parent_id + ')</p>';
        } else {
            html += '<h4>Listing #' + data.listing_id + ': ' + AIRS.escapeHtml(data.title || 'Unknown Title') + '</h4>';
        }

        // Chunked content display
        if (data.has_chunks && data.chunks && data.chunks.length > 0) {
            html += '<div>';
            html += '<strong>' + (i18n.contentChunked || 'This content is chunked into') + ' ' +
                data.chunk_count + ' ' + (i18n.partsForBetter || 'parts for better embedding quality') + '</strong>';
            html += '<table class="widefat" style="margin-top: 10px;">';
            html += '<thead><tr><th>Chunk</th><th>ID</th><th>' + (i18n.words || 'Words') + '</th><th>' +
                (i18n.embedding || 'Embedding') + '</th><th>' + (i18n.created || 'Created') + '</th></tr></thead><tbody>';

            data.chunks.forEach(function(chunk) {
                var embeddingStatus = chunk.has_embedding ?
                    '<span style="color: green;">\u2713 ' + (i18n.yes || 'Yes') + '</span>' :
                    '<span style="color: red;">\u2717 ' + (i18n.no || 'No') + '</span>';

                html += '<tr>';
                html += '<td>' + chunk.chunk_number + '/' + chunk.chunk_total + '</td>';
                html += '<td><a href="#" class="embedding-link" data-id="' + chunk.chunk_id + '">' + chunk.chunk_id + '</a></td>';
                html += '<td>' + (chunk.word_count || 'N/A') + '</td>';
                html += '<td>' + embeddingStatus + '</td>';
                html += '<td>' + (chunk.embedding_created || 'N/A') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '<p class="description"><em>' + (i18n.clickChunkId || 'Click on a chunk ID to view its embedding details.') + '</em></p>';
            html += '</div>';
        }

        // Embedding details
        if (data.embedding_exists) {
            if (data.processed_content) {
                html += '<h5>' + (i18n.processedContent || 'Processed Content') + ':</h5>';

                if (data.word_count !== undefined || data.character_count !== undefined) {
                    html += '<p style="margin: 5px 0 10px 0; color: #666; font-size: 13px;">';
                    html += '<strong>' + (i18n.words || 'Words') + ':</strong> ' + (data.word_count || 0) + ' &nbsp;|&nbsp; ';
                    html += '<strong>' + (i18n.characters || 'Characters') + ':</strong> ' + (data.character_count || 0);
                    html += '</p>';
                }

                html += '<div style="background: white; padding: 10px; border: 1px solid #ddd; white-space: pre-wrap; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto;">';
                html += AIRS.escapeHtml(data.processed_content);
                html += '</div>';
            }

            if (data.embedding_preview) {
                html += '<h5>' + (i18n.embeddingVector || 'Embedding Vector (first 10 dimensions)') + ':</h5>';
                html += '<div style="background: white; padding: 10px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;">';
                html += '[' + data.embedding_preview.join(', ') + '...]';
                html += '</div>';
                html += '<p class="description">' + (i18n.fullVector || 'Full embedding vector contains') + ' ' +
                    data.vector_dimensions + ' ' + (i18n.dimensions || 'dimensions') + '.</p>';
            }

            // Delete button (not for chunks)
            if (!data.is_chunk) {
                html += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
                html += '<button type="button" id="delete-single-embedding" data-id="' + data.listing_id +
                    '" data-title="' + AIRS.escapeHtml(data.title || 'this item') +
                    '" style="background: #dc3545; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">';
                html += i18n.deleteEmbedding || 'Delete Embedding';
                html += '</button>';
                html += '</div>';
            }
        } else if (data.has_chunks) {
            html += '<p><em>' + (i18n.parentNoEmbedding || 'Parent post does not have its own embedding - content is stored in chunks above.') + '</em></p>';

            // Delete all chunks button
            html += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
            html += '<button type="button" id="delete-all-chunks" data-id="' + data.listing_id +
                '" data-title="' + AIRS.escapeHtml(data.title || 'this item') +
                '" data-chunks="' + data.chunk_count +
                '" style="background: #dc3545; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">';
            html += (i18n.deleteAllChunks || 'Delete All Chunks') + ' (' + data.chunk_count + ')';
            html += '</button>';
            html += '</div>';
        } else {
            html += '<p><strong>\u2717 ' + (i18n.noEmbeddingFound || 'No embedding found') + '</strong></p>';
            html += '<p>' + (i18n.notProcessedYet || 'This listing has not been processed for AI search yet.') + '</p>';
        }

        $('#embedding-content').html(html);

        // Re-bind click handlers
        $('#embedding-content .embedding-link').on('click', function(e) {
            e.preventDefault();
            checkEmbeddingData($(this).data('id'));
        });

        $('#delete-single-embedding').on('click', function(e) {
            e.preventDefault();
            deleteSingleEmbedding($(this).data('id'), $(this).data('title'));
        });

        $('#delete-all-chunks').on('click', function(e) {
            e.preventDefault();
            deleteAllChunks($(this).data('id'), $(this).data('title'), $(this).data('chunks'));
        });
    }

    /**
     * Delete a single embedding
     */
    function deleteSingleEmbedding(listingId, title) {
        var confirmMsg = (i18n.confirmDeleteEmbedding || 'Are you sure you want to delete the embedding for') +
            ' "' + title + '" (ID: ' + listingId + ')?\n\n' +
            (i18n.needToRegenerate || 'You will need to regenerate it to use AI search for this item.');

        if (!confirm(confirmMsg)) return;

        $('#embedding-content').html('<p><em>' + (i18n.deletingEmbedding || 'Deleting embedding...') + '</em></p>');

        AIRS.ajax({
            action: 'listeo_ai_manage_database',
            data: {
                database_action: 'delete_single',
                listing_id: listingId
            },
            success: function(response) {
                if (response.success) {
                    $('#embedding-content').html('<p style="color: green;"><strong>' +
                        (i18n.embeddingDeleted || 'Embedding deleted successfully.') + '</strong></p>');
                    setTimeout(function() {
                        if (typeof AIRS.refreshDatabaseStatus === 'function') {
                            AIRS.refreshDatabaseStatus();
                        }
                    }, 1500);
                } else {
                    $('#embedding-content').html('<p style="color: red;">' +
                        (i18n.error || 'Error:') + ' ' + escapeMessage(getAjaxErrorMessage(response)) + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#embedding-content').html('<p style="color: red;">' +
                    (i18n.ajaxError || 'AJAX error:') + ' ' + error + '</p>');
            }
        });
    }

    /**
     * Delete all chunks for a parent
     */
    function deleteAllChunks(parentId, title, chunkCount) {
        var confirmMsg = (i18n.confirmDeleteChunks || 'Are you sure you want to delete all') + ' ' +
            chunkCount + ' ' + (i18n.chunksFor || 'chunks for') + ' "' + title + '"?\n\n' +
            (i18n.needToRegenerate || 'You will need to regenerate embeddings to use AI search for this item.');

        if (!confirm(confirmMsg)) return;

        $('#embedding-content').html('<p><em>' + (i18n.deletingChunks || 'Deleting chunks...') + '</em></p>');

        AIRS.ajax({
            action: 'listeo_ai_manage_database',
            data: {
                database_action: 'delete_chunks',
                parent_id: parentId
            },
            success: function(response) {
                if (response.success) {
                    $('#embedding-content').html('<p style="color: green;"><strong>' +
                        (i18n.chunksDeleted || 'All chunks deleted successfully.') + '</strong></p>');
                    setTimeout(function() {
                        if (typeof AIRS.refreshDatabaseStatus === 'function') {
                            AIRS.refreshDatabaseStatus();
                        }
                    }, 1500);
                } else {
                    $('#embedding-content').html('<p style="color: red;">' +
                        (i18n.error || 'Error:') + ' ' + escapeMessage(getAjaxErrorMessage(response)) + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#embedding-content').html('<p style="color: red;">' +
                    (i18n.ajaxError || 'AJAX error:') + ' ' + error + '</p>');
            }
        });
    }

    /**
     * Generate single embedding for a listing
     */
    AIRS.generateSingleEmbedding = function(listingId, $button) {
        var originalText = $button.text();
        $button.text(i18n.generating || 'Generating...').prop('disabled', true);

        AIRS.ajax({
            action: 'listeo_ai_manage_database',
            data: {
                database_action: 'generate_single',
                listing_id: listingId
            },
            success: function(response) {
                if (response.success) {
                    $button.text('\u2713 ' + (i18n.done || 'Done'))
                        .removeClass('button-primary').addClass('button-secondary');

                    setTimeout(function() {
                        $button.closest('tr').fadeOut(500, function() {
                            $(this).remove();
                            AIRS.updateGenerateSelectedButton();
                            if (typeof AIRS.refreshDatabaseStatus === 'function') {
                                AIRS.refreshDatabaseStatus();
                            }
                        });
                    }, 1500);
                } else {
                    $button.text('\u2717 ' + (i18n.failed || 'Failed'))
                        .removeClass('button-primary').addClass('button-secondary');
                    alert((i18n.failedToGenerate || 'Failed to generate embedding:') + ' ' +
                        getAjaxErrorMessage(response));

                    setTimeout(function() {
                        $button.text(originalText).prop('disabled', false)
                            .removeClass('button-secondary').addClass('button-primary');
                    }, 3000);
                }
            },
            error: function(xhr, status, error) {
                $button.text('\u2717 ' + (i18n.error || 'Error'))
                    .removeClass('button-primary').addClass('button-secondary');
                alert((i18n.ajaxError || 'AJAX error:') + ' ' + error);

                setTimeout(function() {
                    $button.text(originalText).prop('disabled', false)
                        .removeClass('button-secondary').addClass('button-primary');
                }, 3000);
            }
        });
    };

    /**
     * Generate embeddings in bulk
     */
    AIRS.generateBulkEmbeddings = function(listingIds) {
        if (!listingIds || listingIds.length === 0) return;

        var $button = $('#generate-selected-missing');
        var originalText = $button.text();
        var totalIds = listingIds.length;
        var completedIds = 0;
        var failedIds = [];

        $button.text((i18n.generating || 'Generating') + ' ' + totalIds + ' ' +
            (i18n.embeddings || 'embeddings...')).prop('disabled', true);

        function processNext() {
            if (listingIds.length === 0) {
                // All done
                var successCount = completedIds - failedIds.length;
                var message = (i18n.completed || 'Completed:') + ' ' + successCount + ' ' + (i18n.success || 'success');
                if (failedIds.length > 0) {
                    message += ', ' + failedIds.length + ' ' + (i18n.failed || 'failed');
                }

                $button.text(message).prop('disabled', false);
                setTimeout(function() {
                    if (typeof AIRS.refreshDatabaseStatus === 'function') {
                        AIRS.refreshDatabaseStatus();
                    }
                    $button.text(i18n.generateSelected || 'Generate Selected').prop('disabled', true);
                }, 3000);
                return;
            }

            var currentId = listingIds.shift();
            completedIds++;

            $button.text((i18n.processing || 'Processing') + ' ' + completedIds + '/' + totalIds + '...');

            AIRS.ajax({
                action: 'listeo_ai_manage_database',
                data: {
                    database_action: 'generate_single',
                    listing_id: currentId
                },
                success: function(response) {
                    if (response.success) {
                        $('.missing-item-checkbox[data-id="' + currentId + '"]').closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        failedIds.push(currentId);
                    }
                    setTimeout(processNext, 1000);
                },
                error: function() {
                    failedIds.push(currentId);
                    setTimeout(processNext, 1000);
                }
            });
        }

        processNext();
    };

    /**
     * Update the "Generate Selected" button state
     */
    AIRS.updateGenerateSelectedButton = function() {
        var checkedCount = $('.missing-item-checkbox:checked').length;
        var $button = $('#generate-selected-missing');

        if (checkedCount > 0) {
            $button.prop('disabled', false).text((i18n.generateSelected || 'Generate Selected') + ' (' + checkedCount + ')');
        } else {
            $button.prop('disabled', true).text(i18n.generateSelected || 'Generate Selected');
        }
    };

    /**
     * Make checkEmbeddingData available globally
     */
    AIRS.checkEmbeddingData = checkEmbeddingData;

    /**
     * Initialize embedding model dropdown
     */
    function initEmbeddingModelDropdown() {
        var $select = $('#listeo_ai_embedding_model');
        if ($select.length === 0) {
            return;
        }

        var originalValue = $select.data('original-value') || $select.val();
        var ajax = window.listeo_ai_search_ajax || {};

        var embeddingDefaults = {
            openai: 'text-embedding-3-small',
            gemini: 'gemini-embedding-001',
            mistral: 'mistral-embed',
            openrouter: 'openai/text-embedding-3-small'
        };

        // Show/hide optgroups based on current provider
        function updateEmbeddingModelUI() {
            var provider = ajax.current_provider || 'openai';
            $('.embedding-model-group').hide();
            $('.embedding-model-group-' + provider).show();


            // Auto-select default if current value doesn't belong to this provider's optgroup
            var currentVal = $select.val();
            var $visibleOptions = $select.find('option').filter(function() {
                return $(this).closest('.embedding-model-group-' + provider).length;
            });
            var isValid = $visibleOptions.filter(function() { return $(this).val() === currentVal; }).length > 0;
            if (!isValid) {
                var newDefault = embeddingDefaults[provider] || $visibleOptions.first().val();
                $select.val(newDefault);
                originalValue = newDefault;
                $select.data('original-value', originalValue);
                AIRS.ajax({
                    action: 'listeo_ai_save_embedding_model',
                    data: { nonce: ajax.nonce, model: newDefault }
                });
            }
        }

        updateEmbeddingModelUI();

        // Handle change
        $select.on('change', function() {
            var newValue = $select.val();
            if (newValue === originalValue) {
                $('#embedding-model-retrain-notice').hide();
                return;
            }

            var totalEmbeddings = AIRS.getTotalEmbeddings();
            if (totalEmbeddings >= 1) {
                // Show confirmation modal
                $('#embedding-model-change-modal').fadeIn(200);
                $('#embedding-model-change-modal').data('pending-value', newValue);
            } else {
                // No embeddings - save immediately
                saveEmbeddingModel(newValue);
            }
        });

        $(document).on('listeo_ai_provider_changed', function(event, provider) {
            ajax.current_provider = provider || ajax.current_provider;
            updateEmbeddingModelUI();
        });

        // Modal cancel
        $('#embedding-model-cancel-btn, #embedding-model-change-modal .airs-modal-overlay').on('click', function() {
            $('#embedding-model-change-modal').fadeOut(200);
            $select.val(originalValue);
            $('#embedding-model-change-modal').removeData('pending-value');
            $('#embedding-model-retrain-notice').hide();
            $('#embedding-model-save-indicator').hide();
        });

        // Modal confirm
        $('#embedding-model-confirm-btn').on('click', function() {
            var $button = $(this);
            var pendingValue = $('#embedding-model-change-modal').data('pending-value');

            if (!pendingValue) {
                return;
            }

            AIRS.setButtonState($button, 'loading');

            // Step 1: Clear embeddings
            AIRS.ajax({
                action: 'listeo_ai_clear_embeddings_for_provider_switch',
                data: { nonce: ajax.clear_embeddings_nonce },
                success: function(response) {
                    if (response.success) {
                        // Step 2: Save new embedding model
                        saveEmbeddingModel(pendingValue, function() {
                            $('#embedding-model-change-modal').fadeOut(200);
                            $('#embedding-model-change-modal').removeData('pending-value');
                            originalValue = pendingValue;
                            $select.data('original-value', originalValue);
                        });
                    } else {
                        alert(response.data.message || (i18n.errorClearingEmbeddings || 'Error clearing embeddings.'));
                        $select.val(originalValue);
                    }
                },
                error: function() {
                    alert(i18n.ajaxError || 'An error occurred. Please try again.');
                    $select.val(originalValue);
                },
                complete: function() {
                    AIRS.setButtonState($button, 'reset');
                }
            });
        });

        // Save embedding model via AJAX
        function saveEmbeddingModel(model, callback) {
            var $indicator = $('#embedding-model-save-indicator');

            $indicator.html('<span class="airs-spinner airs-spinner--small" style="color: #0073aa;"></span>').show();

            AIRS.ajax({
                action: 'listeo_ai_save_embedding_model',
                data: {
                    nonce: ajax.nonce,
                    model: model
                },
                success: function(response) {
                    if (response.success) {
                        originalValue = model;
                        $select.data('original-value', originalValue);
                        $('#embedding-model-retrain-notice').hide();
                        $indicator.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#46b450" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><polyline points="20 6 9 17 4 12"></polyline></svg>' + (i18n.saved || 'Saved')).show();
                        setTimeout(function() {
                            $indicator.fadeOut(400);
                        }, 1500);
                        if (typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        $indicator.hide();
                        alert(response.data.message || (i18n.saveFailed || 'Failed to save embedding model.'));
                        $select.val(originalValue);
                    }
                },
                error: function() {
                    $indicator.hide();
                    alert(i18n.ajaxError || 'An error occurred. Please try again.');
                    $select.val(originalValue);
                }
            });
        }
    }

    /**
     * Initialize all embedding handlers
     */
    function init() {
        if (typeof window.listeo_ai_search_ajax === 'undefined') {
            console.error('AIRS Embeddings: AJAX variables not loaded');
            return;
        }

        initBatchGeneration();
        initSingleEmbedding();
        initEmbeddingModelDropdown();

    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
