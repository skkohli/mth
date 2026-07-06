/**
 * Hierarchical Taxonomy Display Widget JavaScript
 * Simple static display - no expand/collapse functionality
 */

(function($) {
    'use strict';

    // Hierarchical Taxonomy Display Handler
    var HierarchicalTaxonomyHandler = {
        
        init: function() {
            // Simple initialization - no interactive functionality needed
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        HierarchicalTaxonomyHandler.init();
    });

    // Re-initialize when Elementor widgets are loaded (for preview mode)
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-hierarchical-taxonomy.default', function($scope) {
            HierarchicalTaxonomyHandler.init();
        });
    });

    // Expose handler for external use
    window.HierarchicalTaxonomyHandler = HierarchicalTaxonomyHandler;

})(jQuery);
