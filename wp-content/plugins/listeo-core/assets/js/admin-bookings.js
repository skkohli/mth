/**
 * Listeo Bookings Admin JavaScript
 */

(function($) {
    'use strict';

    const BookingsAdmin = {

        init: function() {
            this.initModal();
            this.initQuickEdit();
            this.initTabs();
            this.initDatePickers();
            this.initAjaxActions();
        },

        /**
         * Initialize booking detail modal
         */
        initModal: function() {
            // Open modal on view click
            $(document).on('click', '.view-booking-details', function(e) {
                e.preventDefault();
                const bookingId = $(this).data('booking-id');
                BookingsAdmin.loadBookingDetails(bookingId);
            });

            // Close modal
            $(document).on('click', '.modal-close, .booking-modal', function(e) {
                if (e.target === this) {
                    $('.booking-modal').removeClass('active');
                }
            });

            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.booking-modal').removeClass('active');
                }
            });
        },

        /**
         * Load booking details via AJAX
         */
        loadBookingDetails: function(bookingId) {
            const modal = $('.booking-modal');
            const modalBody = modal.find('.booking-modal-body');

            // Show loading
            modalBody.html('<div style="text-align: center; padding: 2rem;"><span class="spinner is-active"></span></div>');
            modal.addClass('active');

            // AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'listeo_get_booking_details',
                    booking_id: bookingId,
                    nonce: listeoBookingsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        modalBody.html(response.data.html);
                    } else {
                        modalBody.html('<p>Error loading booking details.</p>');
                    }
                },
                error: function() {
                    modalBody.html('<p>Error loading booking details.</p>');
                }
            });
        },

        /**
         * Initialize quick edit functionality
         */
        initQuickEdit: function() {
            $(document).on('change', '.quick-status-change', function() {
                const bookingId = $(this).data('booking-id');
                const newStatus = $(this).val();
                const $select = $(this);

                // Confirm change
                if (!confirm(listeoBookingsAdmin.confirmStatusChange)) {
                    $select.val($select.data('original-status'));
                    return;
                }

                // Update status
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_update_booking_status',
                        booking_id: bookingId,
                        status: newStatus,
                        nonce: listeoBookingsAdmin.nonce
                    },
                    beforeSend: function() {
                        $select.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update badge
                            const $badge = $select.closest('tr').find('.status-badge');
                            $badge.removeClass().addClass('status-badge ' + newStatus);
                            $badge.text(newStatus.replace('_', ' '));

                            // Show success notice
                            BookingsAdmin.showNotice('Status updated successfully', 'success');

                            // Store new status
                            $select.data('original-status', newStatus);
                        } else {
                            BookingsAdmin.showNotice(response.data.message || 'Error updating status', 'error');
                            $select.val($select.data('original-status'));
                        }
                    },
                    error: function() {
                        BookingsAdmin.showNotice('Error updating status', 'error');
                        $select.val($select.data('original-status'));
                    },
                    complete: function() {
                        $select.prop('disabled', false);
                    }
                });
            });
        },

        /**
         * Initialize edit form tabs (removed - using sequential layout now)
         */
        initTabs: function() {
            // No longer needed - form uses sequential card layout
        },

        /**
         * Initialize date pickers
         */
        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('.booking-date-picker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },

        /**
         * Initialize AJAX actions (delete, export, etc)
         */
        initAjaxActions: function() {
            // Quick delete
            $(document).on('click', '.quick-delete-booking', function(e) {
                e.preventDefault();

                if (!confirm(listeoBookingsAdmin.confirmDelete)) {
                    return;
                }

                const bookingId = $(this).data('booking-id');
                const $row = $(this).closest('tr');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_delete_booking',
                        booking_id: bookingId,
                        nonce: listeoBookingsAdmin.nonce
                    },
                    beforeSend: function() {
                        $row.css('opacity', '0.5');
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                            });
                            BookingsAdmin.showNotice('Booking deleted successfully', 'success');
                        } else {
                            $row.css('opacity', '1');
                            BookingsAdmin.showNotice(response.data.message || 'Error deleting booking', 'error');
                        }
                    },
                    error: function() {
                        $row.css('opacity', '1');
                        BookingsAdmin.showNotice('Error deleting booking', 'error');
                    }
                });
            });

            // Export to CSV
            $(document).on('click', '.export-bookings-csv', function(e) {
                e.preventDefault();

                const filters = BookingsAdmin.getActiveFilters();

                window.location.href = ajaxurl + '?' + $.param({
                    action: 'listeo_export_bookings_csv',
                    nonce: listeoBookingsAdmin.nonce,
                    ...filters
                });
            });
        },

        /**
         * Get active filters from the form
         */
        getActiveFilters: function() {
            const filters = {};

            if ($('[name="listing_id"]').val()) {
                filters.listing_id = $('[name="listing_id"]').val();
            }
            if ($('[name="status"]').val()) {
                filters.status = $('[name="status"]').val();
            }
            if ($('[name="guest"]').val()) {
                filters.guest = $('[name="guest"]').val();
            }
            if ($('[name="owner"]').val()) {
                filters.owner = $('[name="owner"]').val();
            }
            if ($('[name="date_from"]').val()) {
                filters.date_from = $('[name="date_from"]').val();
            }
            if ($('[name="date_to"]').val()) {
                filters.date_to = $('[name="date_to"]').val();
            }

            return filters;
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

            $('.wrap h1').after($notice);

            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BookingsAdmin.init();
    });

})(jQuery);
