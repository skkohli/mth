/* ----------------- Start Document ----------------- */
(function ($) {
  "use strict";

  /*----------------------------------------------------*/
  /*  Elementor Smooth Loading
    /*----------------------------------------------------*/
  $(document).ready(function () {
    $(".main-search-container").after(
      '<div class="search-banner-placeholder"><div class="search-banner-placeholder-loader"></div></div>'
    );
    setTimeout(function () {
      $("body").addClass("theme-loaded");
      // Only initialize selectpicker on Listeo-specific selects, not Elementor controls
      $(".main-search-container select, .listeo-search-form select").selectpicker();
      $(".search-banner-placeholder").fadeOut();
    }, 1100);
    // Only initialize selectpicker on Listeo-specific selects, not Elementor controls
    $(".main-search-container select, .listeo-search-form select").selectpicker();
  });

  $(window).on("load", function () {
    $("body").addClass("theme-loaded");
    $(".search-banner-placeholder").fadeOut();
  });

  function starsOutput(
    firstStar,
    secondStar,
    thirdStar,
    fourthStar,
    fifthStar
  ) {
    return (
      "" +
      '<span class="' +
      firstStar +
      '"></span>' +
      '<span class="' +
      secondStar +
      '"></span>' +
      '<span class="' +
      thirdStar +
      '"></span>' +
      '<span class="' +
      fourthStar +
      '"></span>' +
      '<span class="' +
      fifthStar +
      '"></span>'
    );
  }

  $.fn.numericalRating = function () {
    this.each(function () {
      var dataRating = $(this).attr("data-rating");

      // Rules
      if (dataRating >= 4.0) {
        $(this).addClass("high");
      } else if (dataRating >= 3.0) {
        $(this).addClass("mid");
      } else if (dataRating < 3.0) {
        $(this).addClass("low");
      }
    });
  };

  /*  Star Rating
/*--------------------------*/
  $.fn.starRating = function () {
    this.each(function () {
      var dataRating = $(this).attr("data-rating");
      if (dataRating > 0) {
        // Rating Stars Output

        var fiveStars = starsOutput("star", "star", "star", "star", "star");

        var fourHalfStars = starsOutput(
          "star",
          "star",
          "star",
          "star",
          "star half"
        );
        var fourStars = starsOutput(
          "star",
          "star",
          "star",
          "star",
          "star empty"
        );

        var threeHalfStars = starsOutput(
          "star",
          "star",
          "star",
          "star half",
          "star empty"
        );
        var threeStars = starsOutput(
          "star",
          "star",
          "star",
          "star empty",
          "star empty"
        );

        var twoHalfStars = starsOutput(
          "star",
          "star",
          "star half",
          "star empty",
          "star empty"
        );
        var twoStars = starsOutput(
          "star",
          "star",
          "star empty",
          "star empty",
          "star empty"
        );

        var oneHalfStar = starsOutput(
          "star",
          "star half",
          "star empty",
          "star empty",
          "star empty"
        );
        var oneStar = starsOutput(
          "star",
          "star empty",
          "star empty",
          "star empty",
          "star empty"
        );

        // Rules
        if (dataRating >= 4.75) {
          $(this).append(fiveStars);
        } else if (dataRating >= 4.25) {
          $(this).append(fourHalfStars);
        } else if (dataRating >= 3.75) {
          $(this).append(fourStars);
        } else if (dataRating >= 3.25) {
          $(this).append(threeHalfStars);
        } else if (dataRating >= 2.75) {
          $(this).append(threeStars);
        } else if (dataRating >= 2.25) {
          $(this).append(twoHalfStars);
        } else if (dataRating >= 1.75) {
          $(this).append(twoStars);
        } else if (dataRating >= 1.25) {
          $(this).append(oneHalfStar);
        } else if (dataRating < 1.25) {
          $(this).append(oneStar);
        }
      }
    });
  };
})(jQuery);

(function ($) {
    "use stict";
    $(window).on('elementor/frontend/init', function () {

        // elementorFrontend.hooks.addAction('frontend/element_ready/coco-portfolio.default', function () {
        //     isotopeSetUp();
        // });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-taxonomy-carousel.default', function () {
            runSlickSlider();
        });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-taxonomy-grid.default', function () {
            runSlickSlider();
        });
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-woocommerce-products-carousel.default', function () {
            runSlickSlider();
          //  runListingCarousel();
        });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-imagebox.default', function () {
            runImageBoxes();
        }); 

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-listings-carousel.default', function () {
           // runListingCarousel();
        });

            elementorFrontend.hooks.addAction('frontend/element_ready/listeo-coupons-display.default', function ($scope) {
        // Let theme's custom.js handle coupons carousel initialization
        // Just trigger the overall carousel initialization which includes coupons
     //   runListingCarousel();
    });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-flip-banner.default', function () {
            parallaxBG();
        });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-testimonials.default', function () {
            runTestimonials();
        });    

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-logo-slider.default', function () {
            runLogoSlider();
        }); 
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-taxonomy-wide.default', function () {
            fullGridCarousel();
        }); 
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-listings-wide.default', function () {
            fullGridCarousel();
        }); 

        elementorFrontend.hooks.addAction(
          "frontend/element_ready/listeo-homesearchslider.default",
          function () {
            inlineCSS();
          }
        );
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-homebanner-slider.default', function () {
            inlineCSS();
        });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-homebanner-simple-slider.default', function () {
            inlineCSS();
        });
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-homebanner.default', function ($scope) {
            inlineCSS();
            initTypedEffect($scope);
        });
        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-homebanner-boxed.default', function () {
            inlineCSS();
        });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-homesearchslider.default', function () {
            homecarousel();
            shapes();
        });

        elementorFrontend.hooks.addAction('frontend/element_ready/listeo-reviews-carousel.default', function () {
            runReviewCarousel();
             $(".star-rating").starRating();
             $(".stars-nl").starRating();
        });
        elementorFrontend.hooks.addAction(
          "frontend/element_ready/listeo-listing-gallery.default",
          function () {
            
            singlelistinggallery();
            inlineCSS();
          }
        );
        
        // Fix for map in Elementor editor
        elementorFrontend.hooks.addAction(
          "frontend/element_ready/listeo-homesearchmap.default",
          function () {
            initializeMapInEditor();
          }
        );
    });

    function fullGridCarousel() {
      $(".fullgrid-slick-carousel").not('.slick-initialized').each(function () {
        $(this).slick({
          centerMode: true,
          centerPadding: "20%",
          slidesToShow: 2,
          dots: true,
          arrows: true,
          slide: ".fw-carousel-item",
          appendDots: $(this).find(".slide-m-dots"),
          prevArrow: $(this).find(".slide-m-prev"),
          nextArrow: $(this).find(".slide-m-next"),
          responsive: [
            {
              breakpoint: 1920,
              settings: {
                centerPadding: "15%",
                slidesToShow: 2,
              },
            },
            {
              breakpoint: 1441,
              settings: {
                centerPadding: "10%",
                slidesToShow: 2,
              },
            },
            {
              breakpoint: 1025,
              settings: {
                centerPadding: "10px",
                slidesToShow: 2,
              },
            },
            {
              breakpoint: 767,
              settings: {
                centerPadding: "10px",
                slidesToShow: 1,
              },
            },
          ],
        });
      });
      
    }
  
   function singlelistinggallery() {
     $(".listing-slider").not('.slick-initialized').slick({
      centerMode: true,
      centerPadding: "20%",
      slidesToShow: 2,
      responsive: [
        {
          breakpoint: 1367,
          settings: {
            centerPadding: "15%",
          },
        },
        {
          breakpoint: 1025,
          settings: {
            centerPadding: "0",
          },
        },
        {
          breakpoint: 767,
          settings: {
            centerPadding: "0",
            slidesToShow: 1,
          },
        },
      ],
    });
     $(".listing-slider-small").not('.slick-initialized').slick({
       centerMode: true,
       centerPadding: "0",
       slidesToShow: 3,
       responsive: [
         {
           breakpoint: 767,
           settings: {
             slidesToShow: 1,
           },
         },
       ],
     });
  }
    function homecarousel(){

        // New Carousel Nav With Arrows
        $('.home-search-carousel, .simple-slick-carousel').append(""+
        "<div class='slider-controls-container'>"+
          "<div class='slider-controls'>"+
            "<button type='button' class='slide-m-prev'></button>"+
            "<div class='slide-m-dots'></div>"+
            "<button type='button' class='slide-m-next'></button>"+
          "</div>"+
        "</div>");

        // New Homepage Carousel
        $('.home-search-carousel').not('.slick-initialized').slick({
          slide: '.home-search-slide',
          centerMode: true,
          centerPadding: '15%',
          slidesToShow: 1,
            dots: true,
            arrows: true,
            appendDots: $(".home-search-carousel .slide-m-dots"),
            prevArrow: $(".home-search-carousel .slide-m-prev"),
            nextArrow: $(".home-search-carousel .slide-m-next"),

          responsive: [
          {
            breakpoint: 1940,
            settings: {
              centerPadding: '13%',
              slidesToShow: 1,
            }
          },
          {
            breakpoint: 1640,
            settings: {
              centerPadding: '8%',
              slidesToShow: 1,
            }
          },
          {
            breakpoint: 1430,
            settings: {
              centerPadding: '50px',
              slidesToShow: 1,
            }
          },
          {
            breakpoint: 1370,
            settings: {
              centerPadding: '20px',
              slidesToShow: 1,
            }
          },
          {
            breakpoint: 767,
            settings: {
              centerPadding: '20px',
              slidesToShow: 1
            }
          }
          ]
        });
        // New Homepage Carousel Positioning
     
          $(".home-search-slider-headlines").each(function() {
            var carouselHeadlineHeight = $(this).height();
            $(this).css('padding-bottom', carouselHeadlineHeight + 30);
          });
          $('.home-search-carousel').removeClass('carousel-not-ready');
      

    }

    
    function runLogoSlider(){
      $('.logo-slick-carousel').not('.slick-initialized').slick({
        infinite: true,
        slidesToShow: 5,
        slidesToScroll: 4,
        dots: true,
        arrows: true,
        responsive: [
            {
              breakpoint: 992,
              settings: {
                slidesToShow: 3,
                slidesToScroll: 3
              }
            },
            {
              breakpoint: 769,
              settings: {
                slidesToShow: 1,
                slidesToScroll: 1
              }
            }
        ]
      });
    }
    // function isotopeSetUp() {
    //     $('.grid').imagesLoaded(function () {
    //         $('.grid').isotope({
    //             itemSelector: '.grid-item',
    //             transitionDuration: 0,
    //             masonry: {
    //                 columnWidth: '.grid-sizer'
    //             }
    //         });
    //         $('.grid').isotope('layout');
    //     });
    // }
    // 
    // 
    function runImageBoxes(){
          /*----------------------------------------------------*/
            /*  Image Box
            /*----------------------------------------------------*/
          $('.category-box').each(function(){

            // add a photo container
            $(this).append('<div class="category-box-background"></div>');

            // set up a background image for each tile based on data-background-image attribute
            $(this).children('.category-box-background').css({'background-image': 'url('+ $(this).attr('data-background-image') +')'});

            
          });


            /*----------------------------------------------------*/
            /*  Image Box
            /*----------------------------------------------------*/
          $('.img-box').each(function(){
            $(this).append('<div class="img-box-background"></div>');
            $(this).children('.img-box-background').css({'background-image': 'url('+ $(this).attr('data-background-image') +')'});
          });


    }



    function runListingCarousel() {
      $('.simple-fw-slick-carousel').not('.slick-initialized').slick({
          infinite: true,
          slidesToShow: 5,
          slidesToScroll: 1,
          dots: true,
          arrows: false,

          responsive: [
          {
            breakpoint: 1610,
            settings: {
            slidesToShow: 4,
            }
          },
          {
            breakpoint: 1365,
            settings: {
            slidesToShow: 3,
            }
          },
          {
            breakpoint: 1024,
            settings: {
            slidesToShow: 2,
            }
          },
          {
            breakpoint: 767,
            settings: {
            slidesToShow: 1,
            }
          }
          ]
        }).on("init", function(e, slick) {

          console.log(slick);
                  //slideautplay = $('div[data-slick-index="'+ slick.currentSlide + '"]').data("time");
                  //$s.slick("setOption", "autoplaySpeed", slideTime);
          });


        $('.simple-slick-carousel').not('.listeo-coupons-carousel').not('.slick-initialized').slick({
            infinite: true,
            slidesToShow: 3,
            slidesToScroll: 3,
            dots: true,
            arrows: true,
            responsive: [
                {
                  breakpoint: 992,
                  settings: {
                    slidesToShow: 2,
                    slidesToScroll: 2
                  }
                },
                {
                  breakpoint: 769,
                  settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1
                  }
                }
            ]
          }).on("init", function(e, slick) {
            
            console.log(slick);
                    //slideautplay = $('div[data-slick-index="'+ slick.currentSlide + '"]').data("time");
                    //$s.slick("setOption", "autoplaySpeed", slideTime);
            });



    }


    function parallaxBG() {

      $('.parallax,.vc_parallax').prepend('<div class="parallax-overlay"></div>');

      $('.parallax,.vc_parallax').each(function() {
        var attrImage = $(this).attr('data-background');
        var attrColor = $(this).attr('data-color');
        var attrOpacity = $(this).attr('data-color-opacity');

            if(attrImage !== undefined) {
                $(this).css('background-image', 'url('+attrImage+')');
            }

            if(attrColor !== undefined) {
                $(this).find(".parallax-overlay").css('background-color', ''+attrColor+'');
            }

            if(attrOpacity !== undefined) {
                $(this).find(".parallax-overlay").css('opacity', ''+attrOpacity+'');
            }

      });
    }



    function runSlickSlider() {
      $('.fullwidth-slick-carousel').not('.slick-initialized').slick({
          centerMode: true,
          centerPadding: '20%',
          slidesToShow: 3,
          dots: true,
          arrows: false,
          responsive: [
            {
              breakpoint: 1920,
              settings: {
                centerPadding: '15%',
                slidesToShow: 3
              }
            },
            {
              breakpoint: 1441,
              settings: {
                centerPadding: '10%',
                slidesToShow: 3
              }
            },
            {
              breakpoint: 1025,
              settings: {
                centerPadding: '10px',
                slidesToShow: 2,
              }
            },
            {
              breakpoint: 767,
              settings: {
                centerPadding: '10px',
                slidesToShow: 1
              }
            }
          ]
        });
        // $(".image-slider").each(function () {
        //     var speed_value = $(this).data('speed');
        //     var auto_value = $(this).data('auto');
        //     var hover_pause = $(this).data('hover');
        //     if (auto_value === true) {
        //         $(this).owlCarousel({
        //             loop: true,
        //             autoHeight: true,
        //             smartSpeed: 1000,
        //             autoplay: auto_value,
        //             autoplayHoverPause: hover_pause,
        //             autoplayTimeout: speed_value,
        //             responsiveClass: true,
        //             items: 1
        //         });
        //         $(this).on('mouseleave', function () {
        //             $(this).trigger('stop.owl.autoplay');
        //             $(this).trigger('play.owl.autoplay', [auto_value]);
        //         });
        //     } else {
        //         $(this).owlCarousel({
        //             loop: true,
        //             autoHeight: true,
        //             smartSpeed: 1000,
        //             autoplay: false,
        //             responsiveClass: true,
        //             items: 1
        //         });
        //     }
        // });
    }

    function runReviewCarousel(){
        $(".reviews-slick-carousel").not('.slick-initialized').each(function () {
          $(this).slick({
            centerMode: true,
            centerPadding: "0%",
            slidesToShow: 5,
            dots: true,
            arrows: true,
            slide: ".fw-carousel-item",
            appendDots: $(this).find(".slide-m-dots"),
            prevArrow: $(this).find(".slide-m-prev"),
            nextArrow: $(this).find(".slide-m-next"),
            responsive: [
              {
                breakpoint: 1920,
                settings: {
                  centerPadding: "0%",
                  slidesToShow: 4,
                },
              },
              {
                breakpoint: 1441,
                settings: {
                  centerPadding: "0%",
                  slidesToShow: 3,
                },
              },
              {
                breakpoint: 1025,
                settings: {
                  centerPadding: "0px",
                  slidesToShow: 2,
                },
              },
              {
                breakpoint: 767,
                settings: {
                  centerPadding: "0px",
                  slidesToShow: 1,
                },
              },
            ],
          });
        });
    }
    function runTestimonials(){

        $('.testimonial-carousel').not('.slick-initialized').slick({
            centerMode: true,
            centerPadding: '34%',
            slidesToShow: 1,
            dots: true,
            arrows: false,
            responsive: [
            {
              breakpoint: 1025,
              settings: {
                centerPadding: '10px',
                slidesToShow: 2,
              }
            },
            {
              breakpoint: 767,
              settings: {
                centerPadding: '10px',
                slidesToShow: 1
              }
            }
            ]
          });

      }


        // Typed effect for Home Search Banner with Elementor preview support
        function initTypedEffect($scope) {
            var $container = $scope.find('.main-search-container');
            if (!$container.length) return;

            var settingsData = $container.attr('data-typed-settings');
            if (!settingsData) return;

            var settings;
            try {
                settings = JSON.parse(settingsData);
            } catch (e) {
                console.error('Failed to parse typed settings:', e);
                return;
            }

            if (!settings.enabled || !settings.words || !settings.words.length) return;

            var $typedElement = $scope.find('.typed-words');
            if (!$typedElement.length) return;

            // Destroy existing typed instance if any
            if ($typedElement.data('typed-instance')) {
                var existingInstance = $typedElement.data('typed-instance');
                if (existingInstance.destroy) {
                    existingInstance.destroy();
                }
                $typedElement.removeData('typed-instance');
            }

            // Clear existing content
            $typedElement.empty();

            // Remove any existing cursor
            $scope.find('.typed-cursor').remove();

            // Background slide change function with lazy loading support
            function changeSyncedBackground(index) {
                if (!settings.syncedBg) return;

                var $slides = $scope.find('.synced-bg-slide');
                if (!$slides.length) return;

                var safeIndex = index % $slides.length;

                $slides.each(function(i) {
                    var $slide = $(this);
                    if (i === safeIndex) {
                        // Lazy load: if slide has data-bg but no background-image, load it now
                        var lazyBg = $slide.attr('data-bg');
                        if (lazyBg && !$slide[0].style.backgroundImage) {
                            $slide.css('background-image', 'url(' + lazyBg + ')');
                        }
                        $slide.addClass('active');
                    } else {
                        $slide.removeClass('active');
                    }
                });
            }

            // Check if Typed library is available
            if (typeof Typed !== 'undefined') {
                // Set initial background
                changeSyncedBackground(0);

                var typedInstance = new Typed($typedElement[0], {
                    strings: settings.words,
                    typeSpeed: settings.typeSpeed || 70,
                    backSpeed: settings.backSpeed || 80,
                    backDelay: settings.backDelay || 4000,
                    startDelay: settings.startDelay || 1000,
                    loop: true,
                    showCursor: true,
                    preStringTyped: function(arrayPos, self) {
                        changeSyncedBackground(arrayPos);
                    }
                });

                // Store instance for cleanup
                $typedElement.data('typed-instance', typedInstance);
            } else {
                // Fallback: Simple word swapper for Elementor preview
                var currentIndex = 0;
                var isRunning = false;

                function showWord(index) {
                    var word = settings.words[index % settings.words.length];
                    $typedElement.html(word + '<span class="typed-cursor">|</span>');
                    changeSyncedBackground(index);
                }

                function swapWord() {
                    if (!isRunning) return;

                    $typedElement.css('opacity', '0');

                    setTimeout(function() {
                        currentIndex = (currentIndex + 1) % settings.words.length;
                        showWord(currentIndex);
                        $typedElement.css('opacity', '1');

                        setTimeout(swapWord, settings.backDelay || 4000);
                    }, 300);
                }

                // Add transition style
                $typedElement.css('transition', 'opacity 0.3s ease');

                // Show first word
                showWord(0);

                // Start swapping after delay
                setTimeout(function() {
                    isRunning = true;
                    setTimeout(swapWord, settings.backDelay || 4000);
                }, settings.startDelay || 1000);

                // Store cleanup function
                $typedElement.data('typed-instance', {
                    destroy: function() {
                        isRunning = false;
                        $typedElement.empty();
                    }
                });
            }
        }

        function inlineCSS() {
  // Only initialize selectpicker on Listeo-specific selects, not Elementor controls
  $(".main-search-container select, .listeo-search-form select").selectpicker();
          // Common Inline CSS
          $(".main-search-container, section.fullwidth, .listing-slider .item, .listing-slider-small .item, .address-container, .img-box-background, .image-edge, .edge-bg").each(function() {
            var attrImageBG = $(this).attr('data-background-image');
            var attrColorBG = $(this).attr('data-background-color');

                if(attrImageBG !== undefined) {
                    $(this).css('background-image', 'url('+attrImageBG+')');
                }

                if(attrColorBG !== undefined) {
                    $(this).css('background', ''+attrColorBG+'');
                }
          });

        }
        
        // Function to initialize the map in Elementor editor
        function initializeMapInEditor() {
          // Make sure Leaflet.js is loaded
          if (typeof L === 'undefined') {
            console.error('Leaflet not loaded yet');
            return;
          }
          
          var mapContainer = document.getElementById('map');
          if (!mapContainer) return;
          
          // Check if map is already initialized
          if (mapContainer._leaflet_id) return;
          
          // Center coordinates
          var latlngStr = [0, 0]; // Default coordinates
          if (typeof listeo_core !== 'undefined' && listeo_core.centerPoint) {
            latlngStr = listeo_core.centerPoint.split(",", 2);
          }
          var lat = parseFloat(latlngStr[0]) || 51.505;
          var lng = parseFloat(latlngStr[1]) || -0.09;
          
          var mapOptions = {
            center: [lat, lng],
            zoom: 8,
            zoomControl: true,
            gestureHandling: true
          };
          
          // Initialize the map
          window.map = L.map('map', mapOptions);
          
          // Add tile layer based on provider
          var mapProvider = 'osm';
          if (typeof listeo_core !== 'undefined') {
            mapProvider = listeo_core.map_provider || 'osm';
          }
          
          // Default to OSM
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          }).addTo(map);
          
          // Add zoom control with custom icons
          var zoomOptions = {
            zoomInText: '<i class="fa fa-plus" aria-hidden="true"></i>',
            zoomOutText: '<i class="fa fa-minus" aria-hidden="true"></i>',
          };
          var zoom = L.control.zoom(zoomOptions);
          zoom.addTo(map);
          
          // Add some sample markers for preview purpose
          var markers = L.markerClusterGroup({
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
          });
          
          // Add a sample marker
          var listeoIcon = L.divIcon({
            iconAnchor: [20, 51],
            popupAnchor: [0, -51],
            className: "listeo-marker-icon",
            html: '<div class="marker-container"><div class="marker-card"><div class="front face"><i class="sl sl-icon-location"></i></div><div class="back face"><i class="sl sl-icon-location"></i></div><div class="marker-arrow"></div></div></div>',
          });
          
          // Add a marker at the center point
          var marker = new L.marker([lat, lng], {
            icon: listeoIcon
          });
          
          markers.addLayer(marker);
          map.addLayer(markers);
          
          // Make sure the map renders correctly by triggering a resize after it's visible
          setTimeout(function() {
            if (map) map.invalidateSize();
          }, 500);
        }


})(jQuery);