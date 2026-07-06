/**
 * Listeo Analytics Dashboard JavaScript
 *
 * Handles chart rendering and interactions
 *
 * @package Listeo_Core
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // WordPress admin color scheme
    const colors = {
        primary: '#2271b1',
        secondary: '#72aee6',
        success: '#46b450',
        warning: '#dba617',
        error: '#dc3232',
        gray: '#646970',
        lightGray: '#dcdcde'
    };

    // Chart.js default config
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
    Chart.defaults.color = colors.gray;

    /**
     * Initialize all charts on page load
     */
    $(document).ready(function() {
        // Initialize instant tab switching
        initTabSwitching();

        // Initialize Select2 for filters
        initListingSelect2();
        initDaysSelect2();

        // Initialize main analytics charts
        initViewsChart();
        initTopListingsChart();
        initContactMethodsChart();
        initSocialPlatformsChart();

        // Initialize AI search charts (if available)
        initAISearchQueriesChart();

        // Initialize booking charts
        initBookingsOverTimeChart();
        initBookingStatusChart();

        // Handle AI queries limit change
        $('#ai-queries-limit').on('change', function() {
            const limit = $(this).val();
            const days = $('select[name="days"]').val() || 30;
            loadAISearchQueries(limit, days);
        });

        // Handle top listings limit change
        $('#top-listings-limit').on('change', function() {
            const limit = $(this).val();
            const days = $('select[name="days"]').val() || 30;
            loadTopListings(limit, days);
        });

        // Handle conversation view modal
        initConversationModal();
    });

    /**
     * Initialize instant tab switching
     */
    function initTabSwitching() {
        // Tab switching on click
        $('.listeo-analytics-tab').on('click', function(e) {
            e.preventDefault();

            const targetTab = $(this).data('tab');

            // Update tab navigation
            $('.listeo-analytics-tab').removeClass('active');
            $(this).addClass('active');

            // Update tab content
            $('.listeo-tab-panel').removeClass('active');
            $('[data-tab-content="' + targetTab + '"]').addClass('active');

            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', targetTab);
            window.history.pushState({}, '', url);

            // Trigger chart resize for any charts in the newly visible tab
            setTimeout(function() {
                if (typeof Chart !== 'undefined') {
                    Chart.helpers.each(Chart.instances, function(instance) {
                        instance.resize();
                    });
                }
            }, 100);
        });

        // Handle initial tab from URL on page load
        const urlParams = new URLSearchParams(window.location.search);
        const initialTab = urlParams.get('tab');

        if (initialTab && initialTab !== 'overview') {
            $('.listeo-analytics-tab[data-tab="' + initialTab + '"]').trigger('click');
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'overview';

            // Update tabs without triggering click event (to avoid infinite loop)
            $('.listeo-analytics-tab').removeClass('active');
            $('.listeo-analytics-tab[data-tab="' + tab + '"]').addClass('active');

            $('.listeo-tab-panel').removeClass('active');
            $('[data-tab-content="' + tab + '"]').addClass('active');
        });
    }

    /**
     * Initialize Select2 for listing dropdown with AJAX search
     */
    function initListingSelect2() {
        const $select = $('.listeo-listing-select2');
        if (!$select.length) return;

        $select.select2({
            ajax: {
                url: listeoAnalyticsDashboard.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'listeo_search_listings',
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data) {
                    return data;
                },
                cache: true
            },
            minimumInputLength: 0,
            placeholder: listeoAnalyticsDashboard.i18n.searchListings,
            allowClear: true
        });

        // Handle selection change - submit form
        $select.on('change', function() {
            $(this).closest('form').submit();
        });
    }

    /**
     * Initialize Select2 for days/time range dropdown
     */
    function initDaysSelect2() {
        const $select = $('.listeo-days-select2');
        if (!$select.length) return;

        $select.select2({
            minimumResultsForSearch: Infinity, // Hide search box
            width: '200px'
        });

        // Handle selection change - submit form
        $select.on('change', function() {
            $(this).closest('form').submit();
        });
    }

    /**
     * Views Over Time Line Chart
     */
    function initViewsChart() {
        const canvas = document.getElementById('viewsChart');
        if (!canvas) return;

        const dataElement = document.getElementById('viewsChartData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noViewData + '</p>');
            return;
        }

        const labels = data.map(item => item.date);
        const totalViews = data.map(item => parseInt(item.total_views) || 0);
        const uniqueViews = data.map(item => parseInt(item.unique_views) || 0);

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: listeoAnalyticsDashboard.i18n.totalViews,
                    data: totalViews,
                    borderColor: colors.primary,
                    backgroundColor: hexToRgba(colors.primary, 0.08),
                    borderWidth: 3,
                    tension: 0.4,
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round',
                    pointRadius: 5,
                    pointHoverRadius: 6,
                    pointHitRadius: 10,
                    pointBackgroundColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointBorderColor: colors.primary,
                    fill: true
                }, {
                    label: listeoAnalyticsDashboard.i18n.uniqueVisitors,
                    data: uniqueViews,
                    borderColor: colors.success,
                    backgroundColor: hexToRgba(colors.success, 0.08),
                    borderWidth: 3,
                    tension: 0.4,
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round',
                    pointRadius: 5,
                    pointHoverRadius: 6,
                    pointHitRadius: 10,
                    pointBackgroundColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointBorderColor: colors.success,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [6, 10],
                            color: 'rgba(216, 216, 216, 0.5)',
                            lineWidth: 1,
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 5
                        }
                    }
                },
                elements: {
                    line: {
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round'
                    },
                    point: {
                        hoverBorderWidth: 3
                    }
                }
            }
        });
    }

    /**
     * Top Listings Horizontal Bar Chart
     */
    function initTopListingsChart() {
        const canvas = document.getElementById('topListingsChart');
        if (!canvas) return;

        const dataElement = document.getElementById('topListingsChartData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noListingData + '</p>');
            return;
        }

        renderTopListingsChart(data);
    }

    /**
     * Render top listings chart with data
     */
    function renderTopListingsChart(data) {
        const canvas = document.getElementById('topListingsChart');
        if (!canvas) return;

        const labels = data.map(item => truncateText(item.post_title, 30));
        const views = data.map(item => parseInt(item.unique_views) || 0);

        // Set dynamic height based on number of items (minimum 40px per item)
        const minHeight = Math.max(300, data.length * 40);
        canvas.style.height = minHeight + 'px';

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: listeoAnalyticsDashboard.i18n.uniqueViews,
                    data: views,
                    backgroundColor: colors.primary,
                    borderColor: colors.primary,
                    borderWidth: 0,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8,
                        callbacks: {
                            label: function(context) {
                                return listeoAnalyticsDashboard.i18n.views + ': ' + context.parsed.x.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [6, 10],
                            color: 'rgba(216, 216, 216, 0.5)',
                            lineWidth: 1,
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            padding: 10
                        }
                    },
                    y: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
            }
        });
    }

    /**
     * Contact Methods Doughnut Chart
     */
    function initContactMethodsChart() {
        const canvas = document.getElementById('contactMethodsChart');
        if (!canvas) return;

        const dataElement = document.getElementById('contactMethodsChartData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noContactData + '</p>');
            return;
        }

        // Brand colors for contact methods
        const contactColors = {
            'whatsapp': '#25D366',    // WhatsApp green
            'phone': '#2271b1',       // Blue
            'email': '#ea4335',       // Gmail red
            'website': '#9b51e0',     // Purple
            'contact': '#f39c12'      // Orange for "Send Message button"
        };

        // Label mapping
        const labelMap = {
            'contact': listeoAnalyticsDashboard.i18n.sendMessageButton
        };

        const labels = data.map(item => {
            const method = item.contact_method.toLowerCase();
            return labelMap[method] || capitalizeFirst(item.contact_method);
        });

        const clicks = data.map(item => parseInt(item.clicks) || 0);

        // Get colors based on method name
        const backgroundColors = data.map(item => {
            const method = item.contact_method.toLowerCase();
            return contactColors[method] || '#646970'; // Default gray
        });

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: clicks,
                    backgroundColor: backgroundColors,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 4,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Social Platforms Doughnut Chart
     */
    function initSocialPlatformsChart() {
        const canvas = document.getElementById('socialPlatformsChart');
        if (!canvas) return;

        const dataElement = document.getElementById('socialPlatformsChartData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noSocialData + '</p>');
            return;
        }

        // Brand colors for social media platforms
        const socialColors = {
            'facebook': '#1877F2',     // Facebook blue
            'instagram': '#E4405F',    // Instagram pink/red
            'twitter': '#1DA1F2',      // Twitter blue (X)
            'linkedin': '#0A66C2',     // LinkedIn blue
            'youtube': '#FF0000',      // YouTube red
            'telegram': '#0088cc',     // Telegram blue
            'skype': '#00AFF0',        // Skype blue
            'viber': '#7360F2',        // Viber purple
            'tiktok': '#000000',       // TikTok black
            'snapchat': '#FFFC00',     // Snapchat yellow
            'pinterest': '#E60023',    // Pinterest red
            'reddit': '#FF4500',       // Reddit orange
            'tumblr': '#35465C',       // Tumblr blue-gray
            'medium': '#00AB6C',       // Medium green
            'twitch': '#9146FF',       // Twitch purple
            'mixcloud': '#52AAD8',     // Mixcloud blue
            'soundcloud': '#FF5500',   // SoundCloud orange
            'line': '#00B900',         // LINE green
            'whatsapp': '#25D366'      // WhatsApp green
        };

        const labels = data.map(item => capitalizeFirst(item.platform));
        const clicks = data.map(item => parseInt(item.clicks) || 0);

        // Get colors based on platform name
        const backgroundColors = data.map(item => {
            const platform = item.platform.toLowerCase();
            return socialColors[platform] || '#646970'; // Default gray
        });

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: clicks,
                    backgroundColor: backgroundColors,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 4,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Generate color palette for charts
     */
    function generateColors(count) {
        const palette = [
            colors.primary,
            colors.success,
            colors.secondary,
            colors.warning,
            '#9b51e0',
            '#f39c12',
            '#e74c3c',
            '#1abc9c',
            '#34495e',
            '#95a5a6'
        ];

        const result = [];
        for (let i = 0; i < count; i++) {
            result.push(palette[i % palette.length]);
        }
        return result;
    }

    /**
     * Truncate text to max length
     */
    function truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    /**
     * Capitalize first letter
     */
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Convert hex color to rgba
     */
    function hexToRgba(hex, alpha) {
        // Remove # if present
        hex = hex.replace('#', '');

        // Parse hex values
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);

        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    /**
     * AI Search Queries Horizontal Bar Chart
     */
    let aiQueriesChartInstance = null;

    function initAISearchQueriesChart() {
        const canvas = document.getElementById('aiSearchQueriesChart');
        if (!canvas) return;

        const dataElement = document.getElementById('aiSearchQueriesData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || Object.keys(data).length === 0) {
            $('#aiSearchQueriesChartContainer').html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noSearchQueryData + '</p>');
            return;
        }

        renderAIQueriesChart(data);
    }

    /**
     * Render AI queries chart with data
     */
    function renderAIQueriesChart(data) {
        const canvas = document.getElementById('aiSearchQueriesChart');
        if (!canvas) return;

        // Destroy existing chart if it exists
        if (aiQueriesChartInstance) {
            aiQueriesChartInstance.destroy();
        }

        // Convert object to arrays
        const labels = Object.keys(data).map(query => truncateText(query, 40));
        const counts = Object.values(data);

        // Set dynamic height based on number of items (minimum 40px per item)
        const minHeight = Math.max(300, labels.length * 40);
        canvas.style.height = minHeight + 'px';

        aiQueriesChartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: listeoAnalyticsDashboard.i18n.searchCount,
                    data: counts,
                    backgroundColor: '#9b51e0',
                    borderColor: '#9b51e0',
                    borderWidth: 0,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8,
                        callbacks: {
                            label: function(context) {
                                return listeoAnalyticsDashboard.i18n.searches + ': ' + context.parsed.x.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [6, 10],
                            color: 'rgba(216, 216, 216, 0.5)',
                            lineWidth: 1,
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            padding: 10
                        }
                    },
                    y: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
            }
        });
    }

    /**
     * Load AI search queries via AJAX
     */
    function loadAISearchQueries(limit, days) {
        const $container = $('#aiSearchQueriesChartContainer');

        // Show loading state
        $container.html('<p style="text-align:center;color:#666;padding:40px 0;"><i class="fa fa-spinner fa-spin"></i> ' + listeoAnalyticsDashboard.i18n.loading + '</p>');

        $.ajax({
            url: listeoAnalyticsDashboard.ajax_url,
            type: 'GET',
            data: {
                action: 'listeo_get_ai_search_queries',
                days: days,
                limit: limit
            },
            success: function(response) {
                if (response.success && response.data.queries) {
                    // Restore canvas
                    $container.html('<canvas id="aiSearchQueriesChart"></canvas>');

                    // Render chart with new data
                    renderAIQueriesChart(response.data.queries);

                    // Update title
                    const title = listeoAnalyticsDashboard.i18n.topAISearchQueries.replace('%d', limit);
                    $container.closest('.listeo-chart-box').find('h3').text(title);
                } else {
                    $container.html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noSearchQueryData + '</p>');
                }
            },
            error: function() {
                $container.html('<p style="text-align:center;color:#dc3232;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.errorLoading + '</p>');
            }
        });
    }

    /**
     * Load top listings via AJAX
     */
    function loadTopListings(limit, days) {
        const $container = $('#topListingsChartContainer');
        if (!$container.length) return;

        // Show loading state
        $container.html('<p style="text-align:center;color:#666;padding:40px 0;"><i class="fa fa-spinner fa-spin"></i> ' + listeoAnalyticsDashboard.i18n.loading + '</p>');

        $.ajax({
            url: listeoAnalyticsDashboard.ajax_url,
            type: 'GET',
            data: {
                action: 'listeo_get_top_listings',
                days: days,
                limit: limit
            },
            success: function(response) {
                if (response.success && response.data.listings) {
                    // Restore canvas
                    $container.html('<canvas id="topListingsChart"></canvas>');

                    // Render chart with new data
                    renderTopListingsChart(response.data.listings);
                } else {
                    $container.html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noListingData + '</p>');
                }
            },
            error: function() {
                $container.html('<p style="text-align:center;color:#dc3232;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.errorLoading + '</p>');
            }
        });
    }

    /**
     * Bookings Over Time Line Chart
     */
    function initBookingsOverTimeChart() {
        const canvas = document.getElementById('bookingsOverTimeChart');
        if (!canvas) return;

        const dataElement = document.getElementById('bookingsOverTimeData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noBookingData + '</p>');
            return;
        }

        const labels = data.map(item => item.date);
        const totalBookings = data.map(item => parseInt(item.total_bookings) || 0);
        const confirmedBookings = data.map(item => parseInt(item.confirmed_bookings) || 0);

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: listeoAnalyticsDashboard.i18n.totalBookings,
                    data: totalBookings,
                    borderColor: colors.primary,
                    backgroundColor: hexToRgba(colors.primary, 0.08),
                    borderWidth: 3,
                    tension: 0.4,
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round',
                    pointRadius: 5,
                    pointHoverRadius: 6,
                    pointHitRadius: 10,
                    pointBackgroundColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointBorderColor: colors.primary,
                    fill: true
                }, {
                    label: listeoAnalyticsDashboard.i18n.confirmedBookings,
                    data: confirmedBookings,
                    borderColor: colors.success,
                    backgroundColor: hexToRgba(colors.success, 0.08),
                    borderWidth: 3,
                    tension: 0.4,
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round',
                    pointRadius: 5,
                    pointHoverRadius: 6,
                    pointHitRadius: 10,
                    pointBackgroundColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointBorderColor: colors.success,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [6, 10],
                            color: 'rgba(216, 216, 216, 0.5)',
                            lineWidth: 1,
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 5
                        }
                    }
                },
                elements: {
                    line: {
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round'
                    },
                    point: {
                        hoverBorderWidth: 3
                    }
                }
            }
        });
    }

    /**
     * Booking Status Breakdown Doughnut Chart
     */
    function initBookingStatusChart() {
        const canvas = document.getElementById('bookingStatusChart');
        if (!canvas) return;

        const dataElement = document.getElementById('bookingStatusData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noBookingStatusData + '</p>');
            return;
        }

        // Status colors
        const statusColors = {
            'confirmed': '#46b450',   // Green
            'paid': '#46b450',        // Green
            'completed': '#46b450',   // Green
            'pending': '#ffb900',     // Yellow/Orange
            'waiting': '#00a0d2',     // Blue
            'cancelled': '#dc3232',   // Red
            'expired': '#646970'      // Gray
        };

        const labels = data.map(item => capitalizeFirst(item.status));
        const counts = data.map(item => parseInt(item.count) || 0);

        // Get colors based on status
        const backgroundColors = data.map(item => {
            const status = item.status.toLowerCase();
            return statusColors[status] || '#646970'; // Default gray
        });

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: backgroundColors,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 4,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Messages Over Time Line Chart
     */
    function initMessagesOverTimeChart() {
        const canvas = document.getElementById('messagesOverTimeChart');
        if (!canvas) return;

        const dataElement = document.getElementById('messagesOverTimeData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noMessageData + '</p>');
            return;
        }

        const labels = data.map(item => item.date);
        const totalMessages = data.map(item => parseInt(item.total_messages) || 0);
        const activeConversations = data.map(item => parseInt(item.active_conversations) || 0);

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: listeoAnalyticsDashboard.i18n.totalMessages,
                    data: totalMessages,
                    borderColor: colors.primary,
                    backgroundColor: hexToRgba(colors.primary, 0.08),
                    borderWidth: 3,
                    tension: 0.4,
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round',
                    pointRadius: 5,
                    pointHoverRadius: 6,
                    pointHitRadius: 10,
                    pointBackgroundColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointBorderColor: colors.primary,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: listeoAnalyticsDashboard.i18n.activeConversations,
                    data: activeConversations,
                    borderColor: colors.success,
                    backgroundColor: hexToRgba(colors.success, 0.08),
                    borderWidth: 3,
                    tension: 0.4,
                    borderCapStyle: 'round',
                    borderJoinStyle: 'round',
                    pointRadius: 5,
                    pointHoverRadius: 6,
                    pointHitRadius: 10,
                    pointBackgroundColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointBorderColor: colors.success,
                    fill: true,
                    yAxisID: 'y'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: {
                            borderDash: [6, 10],
                            color: 'rgba(216, 216, 216, 0.5)',
                            lineWidth: 1,
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 5
                        }
                    }
                }
            }
        });
    }

    /**
     * Conversation Sources Doughnut Chart
     */
    function initConversationSourcesChart() {
        const canvas = document.getElementById('conversationSourcesChart');
        if (!canvas) return;

        const dataElement = document.getElementById('conversationSourcesData');
        if (!dataElement) return;

        const data = JSON.parse(dataElement.textContent);

        if (!data || data.length === 0) {
            $(canvas).parent().html('<p style="text-align:center;color:#666;padding:40px 0;">' + listeoAnalyticsDashboard.i18n.noConversationSourceData + '</p>');
            return;
        }

        const labels = data.map(item => {
            const source = item.source || 'unknown';
            // Capitalize and format source names
            if (source.startsWith('listing_')) {
                return 'Listing: ' + source.replace('listing_', '').replace(/_/g, ' ');
            }
            return source.charAt(0).toUpperCase() + source.slice(1).replace(/_/g, ' ');
        });
        const counts = data.map(item => parseInt(item.count) || 0);

        // Generate colors
        const backgroundColors = [
            hexToRgba(colors.primary, 0.8),
            hexToRgba(colors.success, 0.8),
            hexToRgba(colors.warning, 0.8),
            hexToRgba(colors.error, 0.8),
            hexToRgba(colors.secondary, 0.8),
            hexToRgba('#9b59b6', 0.8),
            hexToRgba('#1abc9c', 0.8),
            hexToRgba('#e74c3c', 0.8),
            hexToRgba('#34495e', 0.8),
            hexToRgba('#16a085', 0.8)
        ];

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    label: listeoAnalyticsDashboard.i18n.conversations,
                    data: counts,
                    backgroundColor: backgroundColors.slice(0, labels.length),
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 13
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(51, 51, 51, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        caretPadding: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Conversation Modal
     */
    function initConversationModal() {
        const $modal = $('#listeo-conversation-modal');
        if (!$modal.length) return;

        // Open modal on View button click
        $(document).on('click', '.listeo-view-conversation', function(e) {
            e.preventDefault();
            const conversationId = $(this).data('conversation-id');
            openConversationModal(conversationId);
        });

        // Close modal
        $('.listeo-modal-close, .listeo-modal-overlay').on('click', function() {
            closeConversationModal();
        });

        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                closeConversationModal();
            }
        });
    }

    /**
     * Open conversation modal and load data
     */
    function openConversationModal(conversationId) {
        const $modal = $('#listeo-conversation-modal');
        const $loading = $modal.find('.listeo-modal-loading');
        const $content = $modal.find('.listeo-modal-conversation-content');

        // Show modal
        $modal.fadeIn(200);
        $loading.show();
        $content.html('');

        // Load conversation via AJAX
        $.ajax({
            url: listeoAnalyticsDashboard.ajax_url,
            type: 'GET',
            data: {
                action: 'listeo_get_conversation_detail',
                conversation_id: conversationId,
                nonce: listeoAnalyticsDashboard.nonce
            },
            success: function(response) {
                $loading.hide();
                if (response.success) {
                    $content.html(response.data.html);
                } else {
                    $content.html('<p class="error">' + (response.data.message || listeoAnalyticsDashboard.i18n.failedToLoadConversation) + '</p>');
                }
            },
            error: function() {
                $loading.hide();
                $content.html('<p class="error">' + listeoAnalyticsDashboard.i18n.conversationLoadError + '</p>');
            }
        });
    }

    /**
     * Close conversation modal
     */
    function closeConversationModal() {
        $('#listeo-conversation-modal').fadeOut(200);
    }

})(jQuery);
