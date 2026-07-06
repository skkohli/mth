/**
 * PurioChat - locked AI Auto Config promo flow.
 */
(function($) {
    'use strict';

    var config = window.listeoAiAutoConfig || {};

    function getModal() {
        return $('#listeo-ai-auto-config-modal');
    }

    function openModal() {
        $('#listeo-ai-auto-config-message').hide();
        $('#listeo-ai-auto-config-analyze')
            .prop('disabled', true)
            .attr('aria-disabled', 'true');
        getModal().fadeIn(160);
    }

    function closeModal() {
        getModal().fadeOut(160);
    }

    $(function() {
        if (!config.locked) {
            return;
        }

        $('#listeo-ai-auto-config-open').on('click', openModal);
        $('#listeo-ai-auto-config-close, #listeo-ai-auto-config-cancel, #listeo-ai-auto-config-modal .airs-modal-overlay').on('click', closeModal);
        $('#listeo-ai-auto-config-analyze').on('click', function(event) {
            event.preventDefault();
            return false;
        });
    });

})(jQuery);
