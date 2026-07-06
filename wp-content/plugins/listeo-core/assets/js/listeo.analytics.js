/**
 * Listeo Analytics - Enhanced Tracking
 *
 * Extends the existing stats system with additional tracking for:
 * - WhatsApp, phone, email, website buttons
 * - All social media platforms
 *
 * @package Listeo_Core
 * @since 1.0.0
 */

(function(window, $) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {

        // Prevent multiple initialization
        if (window.listeoAnalyticsInitialized) {
            // console.log('[Listeo Analytics] Already initialized, skipping duplicate');
            return;
        }

        if (typeof window.listeoAnalytics === 'undefined' || !window.listeoAnalytics.enabled) {
            // console.log('[Listeo Analytics] Disabled or not configured');
            return; // Analytics disabled
        }

        // Mark as initialized
        window.listeoAnalyticsInitialized = true;

        var postId = window.listeoAnalytics.listing_id;
        // console.log('[Listeo Analytics] Initialized for listing ID:', postId);

        /**
         * Track a stat using WordPress AJAX
         */
        function trackStat(statType) {
            if (typeof wp === 'undefined' || typeof wp.ajax === 'undefined') {
                // console.warn('[Listeo Analytics] WordPress AJAX not available');
                return;
            }

            wp.ajax.post('listeo_stat_' + statType, {
                post_id: postId,
                stat: statType
            }).done(function(response) {
                // console.log('[Listeo Analytics] Tracked:', statType, response);
            }).fail(function(error) {
                // console.log('[Listeo Analytics] Failed to track:', statType, error);
            });
        }

        /**
         * WhatsApp button tracking
         * NOTE: Disabled - WhatsApp is now handled by the social media handler below
         * to prevent duplicate tracking
         */
        /* DISABLED
        $('body').on('click', 'a[href*="wa.me"], a[href*="whatsapp.com"], a[href*="api.whatsapp"], .whatsapp-link, .listing-whatsapp, .listing-links-whatsapp', function(e) {
            var $this = $(this);
            if (!$this.hasClass('tracked-whatsapp')) {
                trackStat('whatsapp_click');
                $this.addClass('tracked-whatsapp');
            }
            e.stopPropagation(); // Prevent event bubbling
        });
        */

        /**
         * Phone button tracking
         */
        $('body').on('click', 'a[href^="tel:"], .listing-phone, .phone-link, .call-btn', function(e) {
            var $this = $(this);
            if (!$this.hasClass('tracked-phone')) {
                trackStat('phone_click');
                $this.addClass('tracked-phone');
            }
            e.stopPropagation();
        });

        /**
         * Email button tracking
         */
        $('body').on('click', 'a[href^="mailto:"], .listing-email, .email-link', function(e) {
            var $this = $(this);
            if (!$this.hasClass('tracked-email')) {
                trackStat('email_click');
                $this.addClass('tracked-email');
            }
            e.stopPropagation();
        });

        /**
         * Website button tracking
         */
        $('body').on('click', '.listing-website, a.website-link', function(e) {
            var $this = $(this);
            // Don't track if this is a social link (has a more specific class)
            if (!$this.hasClass('tracked-website') && !$this.attr('class').match(/listing-links-(fb|yt|ig|tt|whatsapp|skype|viber|tiktok|snapchat|telegram|tumblr|reddit|medium|twitch|mixcloud|tripadvisor|yelp|foursquare|line|soundcloud|pinterest|linkedit)/)) {
                trackStat('website_click');
                $this.addClass('tracked-website');
            }
            e.stopPropagation();
        });

        /**
         * External booking button tracking
         */
        $('body').on('click', '.booking-external-widget a', function(e) {
            var $this = $(this);
            if (!$this.hasClass('tracked-external-booking')) {
                trackStat('external_booking_click');
                $this.addClass('tracked-external-booking');
            }
            e.stopPropagation();
        });

        /**
         * WhatsApp chat button tracking (sidebar widget)
         * Track using .listeo-track-whatsapp class and prevent contact_click tracking
         */
        $('body').on('click', '.listeo-track-whatsapp', function(e) {
            var $this = $(this);

            // Track as whatsapp_click only (not contact_click)
            if (!$this.hasClass('tracked-whatsapp-analytics')) {
                trackStat('whatsapp_click');
                $this.addClass('tracked-whatsapp-analytics');
            }

            // Prevent the old .send-message-to-owner handler from also tracking this
            // by adding the class it checks for
            if (!$this.hasClass('contact-now_clicked')) {
                $this.addClass('contact-now_clicked');
            }
        });

        /**
         * Social media tracking - href-based detection
         */
        var socialPlatforms = {
            'facebook.com': 'facebook_click',
            'instagram.com': 'instagram_click',
            'twitter.com': 'twitter_click',
            'x.com': 'twitter_click',
            'linkedin.com': 'linkedin_click',
            'youtube.com': 'youtube_click',
            't.me': 'telegram_click',
            'telegram.me': 'telegram_click',
            'skype.com': 'skype_click',
            'skype:': 'skype_click',
            'viber.com': 'viber_click',
            'tiktok.com': 'tiktok_click',
            'snapchat.com': 'snapchat_click',
            'pinterest.com': 'pinterest_click',
            'whatsapp.com': 'whatsapp_click',
            'wa.me': 'whatsapp_click',
            'api.whatsapp': 'whatsapp_click'
        };

        /**
         * Map CSS classes to stat types (additional detection method)
         * Ensures tracking works even if href doesn't match expected pattern
         */
        var classToStat = {
            // Sidebar profile classes
            'facebook-profile': 'facebook_click',
            'instagram-profile': 'instagram_click',
            'twitter-profile': 'twitter_click',
            'linkedin-profile': 'linkedin_click',
            'youtube-profile': 'youtube_click',
            'whatsapp-profile': 'whatsapp_click',
            'telegram-profile': 'telegram_click',
            'skype-profile': 'skype_click',
            'viber-profile': 'viber_click',
            'tiktok-profile': 'tiktok_click',
            'snapchat-profile': 'snapchat_click',
            'pinterest-profile': 'pinterest_click',

            // Listing links classes
            'listing-links-fb': 'facebook_click',
            'listing-links-ig': 'instagram_click',
            'listing-links-twitter': 'twitter_click',
            'listing-links-linkedit': 'linkedin_click',
            'listing-links-linkedin': 'linkedin_click',
            'listing-links-yt': 'youtube_click',
            'listing-links-whatsapp': 'whatsapp_click',
            'listing-links-telegram': 'telegram_click',
            'listing-links-skype': 'skype_click',
            'listing-links-viber': 'viber_click',
            'listing-links-tiktok': 'tiktok_click',
            'listing-links-tt': 'tiktok_click',
            'listing-links-snapchat': 'snapchat_click',
            'listing-links-pinterest': 'pinterest_click'
        };

        $('body').on('click', 'a[class*="listing-links-"], a[class*="-profile"]', function(e) {
            var $this = $(this);

            // Only track if this is the actual link element, not a child (icon/text inside it)
            if (e.target !== this && !$(e.target).is('i, span')) {
                // console.log('[Listeo Analytics] Click on child element, ignoring');
                return;
            }

            var href = $this.attr('href') || '';
            var classes = $this.attr('class') || '';
            // console.log('[Listeo Analytics] Social link clicked:', href, 'Classes:', classes);

            // Check if already being tracked (prevent race conditions)
            if ($this.data('tracking-in-progress')) {
                // console.log('[Listeo Analytics] Already tracking this click, skipping');
                return;
            }

            var statType = null;

            // METHOD 1: Check href-based detection (primary method)
            for (var platform in socialPlatforms) {
                if (href.indexOf(platform) !== -1) {
                    statType = socialPlatforms[platform];
                    // console.log('[Listeo Analytics] Matched href platform:', platform, '→', statType);
                    break;
                }
            }

            // METHOD 2: Check class-based detection (additional method)
            if (!statType) {
                for (var className in classToStat) {
                    if ($this.hasClass(className)) {
                        statType = classToStat[className];
                        // console.log('[Listeo Analytics] Matched class:', className, '→', statType);
                        break;
                    }
                }
            }

            // Track if we found a match from either method
            if (statType) {
                if (!$this.hasClass('tracked-' + statType)) {
                    // Mark as in-progress to prevent race conditions
                    $this.data('tracking-in-progress', true);

                    trackStat(statType);
                    $this.addClass('tracked-' + statType);

                    // Clear in-progress flag after tracking
                    setTimeout(function() {
                        $this.removeData('tracking-in-progress');
                    }, 100);
                } else {
                    // console.log('[Listeo Analytics] Already tracked this session');
                }
                e.stopPropagation();
                e.preventDefault(); // Prevent default briefly

                // Allow link to work after tracking
                setTimeout(function() {
                    if ($this.attr('target') === '_blank') {
                        window.open($this.attr('href'), '_blank');
                    } else {
                        window.location.href = $this.attr('href');
                    }
                }, 50);

                return false; // Stop propagation completely
            }
            // console.log('[Listeo Analytics] No platform matched for href:', href, 'or classes:', classes);
        });

    });

})(window, jQuery);
