jQuery(document).ready(function($) {
	'use strict';

	// Add modal HTML to body on page load
	if ($('.listeo-reject-listing-button').length > 0) {
		$('body').append(
			'<div id="listeo-reject-modal" class="listeo-modal" style="display:none;">' +
				'<div class="listeo-modal-content">' +
					'<span class="listeo-modal-close">&times;</span>' +
					'<h2>' + listeo_reject_i18n.modal_title + '</h2>' +
					'<p>' + listeo_reject_i18n.modal_description + '</p>' +
					'<textarea id="listeo-rejection-reason" rows="4" placeholder="' + listeo_reject_i18n.placeholder + '"></textarea>' +
					'<div class="listeo-modal-actions">' +
						'<button id="listeo-confirm-reject" class="button button-primary">' + listeo_reject_i18n.confirm_button + '</button>' +
						'<button id="listeo-cancel-reject" class="button">' + listeo_reject_i18n.cancel_button + '</button>' +
					'</div>' +
				'</div>' +
			'</div>'
		);
	}

	// Show modal on reject button click
	$(document).on('click', '.listeo-reject-listing-button', function(e) {
		e.preventDefault();
		var listingId = $(this).data('listing-id');
		$('#listeo-reject-modal').data('listing-id', listingId).fadeIn();
	});

	// Close modal on X button or cancel
	$(document).on('click', '.listeo-modal-close, #listeo-cancel-reject', function() {
		$('#listeo-reject-modal').fadeOut();
		$('#listeo-rejection-reason').val('');
	});

	// Close modal on outside click
	$(document).on('click', '#listeo-reject-modal', function(e) {
		if (e.target.id === 'listeo-reject-modal') {
			$(this).fadeOut();
			$('#listeo-rejection-reason').val('');
		}
	});

	// Confirm rejection
	$(document).on('click', '#listeo-confirm-reject', function() {
		var listingId = $('#listeo-reject-modal').data('listing-id');
		var reason = $('#listeo-rejection-reason').val().trim();

		// Build the rejection URL
		var url = listeo_reject_i18n.admin_url +
			'?action=listeo_reject_listing' +
			'&post=' + listingId +
			'&reject_nonce=' + listeo_reject_i18n.nonce;

		// Add reason if provided
		if (reason) {
			url += '&rejection_reason=' + encodeURIComponent(reason);
		}

		// Redirect to rejection handler
		window.location.href = url;
	});
});
