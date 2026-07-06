class ListeoTaxonomyCarousel extends elementorModules.frontend.handlers.Base {
    getDefaultSettings() {
        return {
            selectors: {
                firstSelector: '.firstSelectorClass',
                secondSelector: '.secondSelectorClass',
            },
        };
    }

    getDefaultElements() {
        const selectors = this.getSettings( 'selectors' );
        return {
            $firstSelector: this.$element.find( selectors.firstSelector ),
            $secondSelector: this.$element.find( selectors.secondSelector ),
        };
    }

    bindEvents() {
        this.elements.$firstSelector.on( 'click', this.onFirstSelectorClick.bind( this ) );
    }

    onFirstSelectorClick( event ) {
        event.preventDefault();

        this.elements.$secondSelector.show();
   }
}

jQuery( window ).on( 'elementor/frontend/init', () => {
   const addHandler = ( $element ) => {
       elementorFrontend.elementsHandler.addHandler( ListeoTaxonomyCarousel, {
           $element,
       } );
   };

   elementorFrontend.hooks.addAction( 'frontend/element_ready/listeo-taxonomy-carousel.default', addHandler );
} );
