/**
 * Listeo FAQ Widget JavaScript
 */
(function($) {
    'use strict';

    var ListeoFAQ = function($scope, $) {
        var $container = $scope.find('.faq-container');
        
        if ($container.length === 0) {
            return;
        }

        var $faqItems = $container.find('.faq-item');
        var behavior = $container.data('behavior') || 'single';

        // Initialize
        init();

        function init() {
            bindEvents();
            setupAccessibility();
        }

        function bindEvents() {
            $faqItems.each(function() {
                var $item = $(this);
                var $question = $item.find('.faq-question');
                
                $question.on('click', function(e) {
                    e.preventDefault();
                    toggleFAQ($item);
                });

                // Keyboard navigation
                $question.on('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleFAQ($item);
                    }
                });
            });
        }

        function toggleFAQ($item) {
            var isActive = $item.hasClass('active');
            var $question = $item.find('.faq-question');
            
            if (behavior === 'single') {
                // Close all other items
                $faqItems.not($item).each(function() {
                    var $otherItem = $(this);
                    var $otherQuestion = $otherItem.find('.faq-question');
                    
                    $otherItem.removeClass('active');
                    $otherQuestion.attr('aria-expanded', 'false');
                });
            }
            
            // Toggle current item
            if (isActive) {
                $item.removeClass('active');
                $question.attr('aria-expanded', 'false');
            } else {
                $item.addClass('active');
                $question.attr('aria-expanded', 'true');
            }
        }

        function setupAccessibility() {
            $faqItems.each(function(index) {
                var $item = $(this);
                var $question = $item.find('.faq-question');
                var $answer = $item.find('.faq-answer');
                
                // Set unique IDs for ARIA
                var questionId = 'faq-question-' + index;
                var answerId = 'faq-answer-' + index;
                
                $question.attr({
                    'id': questionId,
                    'aria-controls': answerId,
                    'aria-expanded': $item.hasClass('active') ? 'true' : 'false'
                });
                
                $answer.attr({
                    'id': answerId,
                    'aria-labelledby': questionId,
                    'role': 'region'
                });
            });
        }
    };

    // Run when Elementor frontend is loaded
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-faq.default', ListeoFAQ);
    });

    // Run on document ready for non-Elementor pages
    $(document).ready(function() {
        $('.faq-container').each(function() {
            ListeoFAQ($(this), $);
        });
    });

})(jQuery);
