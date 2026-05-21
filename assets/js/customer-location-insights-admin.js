(function($) {
    'use strict';

    var config = window.mulopimfwcAnalyticsAdmin || {};

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function formatNumber(value, decimals) {
        var num = Number(value || 0);
        var precision = typeof decimals === 'number' ? decimals : 0;
        return num.toLocaleString(undefined, {
            minimumFractionDigits: precision,
            maximumFractionDigits: precision
        });
    }

    function updateProcessingBubble(count) {
        var bubble = document.getElementById('mulopimfwc-processing-bubble');
        var span;
        if (!bubble) {
            return;
        }

        bubble.classList.forEach(function(className) {
            if (className.indexOf('count-') === 0) {
                bubble.classList.remove(className);
            }
        });
        bubble.classList.add('count-' + count);
        span = bubble.querySelector('.processing-count');
        if (span) {
            span.textContent = formatNumber(count);
        }
    }

    function updateGlobalMetrics(globalStats) {
        var mapping = {
            total_views: 'total_views',
            total_purchases: 'total_purchases',
            total_users: 'total_users',
            conversion_rate: 'conversion_rate'
        };

        if (!globalStats) {
            return;
        }

        Object.keys(mapping).forEach(function(key) {
            var el = document.querySelector('[data-analytics-metric="' + mapping[key] + '"]');
            if (!el) {
                return;
            }
            if (key === 'conversion_rate') {
                el.textContent = formatNumber(globalStats[key], 2) + '%';
            } else {
                el.textContent = formatNumber(globalStats[key]);
            }
        });
    }

    function updateTopLocation(data) {
        var nameEl = document.getElementById('mulopimfwc-top-location-name');
        var mappings;

        if (!data || !data.stats) {
            if (nameEl) {
                nameEl.textContent = config.noDataText || 'No data available yet';
            }
            return;
        }

        if (nameEl) {
            nameEl.textContent = data.name || '';
        }

        mappings = {
            views: data.stats.total_views,
            purchases: data.stats.total_purchases,
            users: data.stats.unique_users
        };
        Object.keys(mappings).forEach(function(key) {
            var el = document.querySelector('[data-top-location="' + key + '"]');
            if (el) {
                el.textContent = formatNumber(mappings[key]);
            }
        });
    }

    function updateTopProduct(data) {
        var nameEl = document.getElementById('mulopimfwc-top-product-name');
        var linkEl = document.getElementById('mulopimfwc-top-product-link');
        var locationsEl = document.getElementById('mulopimfwc-top-product-locations');
        var mappings;

        if (!data) {
            if (nameEl) {
                nameEl.textContent = config.noDataText || 'No data available yet';
            }
            return;
        }

        if (nameEl) {
            nameEl.textContent = data.name || '';
        }
        if (linkEl && data.id) {
            linkEl.href = (config.postEditUrl || '') + '?post=' + encodeURIComponent(data.id) + '&action=edit';
        }

        mappings = {
            views: data.views,
            purchases: data.purchases,
            'locations-count': data.locations_count
        };
        Object.keys(mappings).forEach(function(key) {
            var el = document.querySelector('[data-top-product="' + key + '"]');
            if (el) {
                el.textContent = formatNumber(mappings[key]);
            }
        });
        if (locationsEl) {
            locationsEl.textContent = (Array.isArray(data.locations) ? data.locations.slice(0, 3) : []).join(', ');
        }
    }

    function renderRankings(rankings) {
        var tbody = document.getElementById('mulopimfwc-rankings-body');
        var html = '';

        if (!tbody || !Array.isArray(rankings)) {
            return;
        }

        rankings.forEach(function(row, index) {
            var rank = index + 1;
            var badgeClass = '';
            if (rank === 1) {
                badgeClass = 'gold';
            } else if (rank === 2) {
                badgeClass = 'silver';
            } else if (rank === 3) {
                badgeClass = 'bronze';
            }
            html += '<tr>' +
                '<td class="rank-column"><span class="rank-badge' + (badgeClass ? ' ' + badgeClass : '') + '">' + formatNumber(rank) + '</span></td>' +
                '<td><strong>' + escapeHtml(row.name || '') + '</strong></td>' +
                '<td class="num-column">' + formatNumber(row.stats ? row.stats.total_views : 0) + '</td>' +
                '<td class="num-column">' + formatNumber(row.stats ? row.stats.total_purchases : 0) + '</td>' +
                '<td class="num-column">' + formatNumber(row.stats ? row.stats.unique_users : 0) + '</td>' +
                '<td class="num-column">' + formatNumber(row.conversion || 0, 2) + '%</td>' +
                '<td class="num-column"><strong>' + formatNumber(row.score || 0) + '</strong></td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
    }

    function buildTopProductsRows(products) {
        var html = '';

        if (!Array.isArray(products) || products.length === 0) {
            return '<tr><td colspan="5">' + escapeHtml(config.noProductDataText || 'No product data available yet for this location.') + '</td></tr>';
        }

        products.forEach(function(product, index) {
            var rank = index + 1;
            var productId = product.id ? parseInt(product.id, 10) : 0;
            var editLink = productId ? ((config.postEditUrl || '') + '?post=' + encodeURIComponent(productId) + '&action=edit') : '#';
            html += '<tr>' +
                '<td class="rank-col">' + formatNumber(rank) + '</td>' +
                '<td><a href="' + escapeHtml(editLink) + '">' + escapeHtml(product.name || '') + '</a></td>' +
                '<td class="num-col">' + formatNumber(product.view_count || 0) + '</td>' +
                '<td class="num-col"><strong>' + formatNumber(product.purchase_count || 0) + '</strong></td>' +
                '<td class="num-col">' + formatNumber(product.popularity_score || 0) + '</td>' +
                '</tr>';
        });

        return html;
    }

    function updateLocationCards(locations) {
        var container = document.querySelector('.mulopimfwc-analytics-dashboard');
        if (!Array.isArray(locations) || !container) {
            return;
        }

        locations.forEach(function(location) {
            var card = container.querySelector('.mulopimfwc-location-analytics-card[data-location-card="' + location.slug + '"]');
            var stats;
            var metrics;
            var tbody;

            if (!card) {
                return;
            }

            stats = location.stats || {};
            metrics = {
                unique_users: stats.unique_users,
                unique_sessions: stats.unique_sessions,
                total_views: stats.total_views,
                total_purchases: stats.total_purchases,
                conversion: location.conversion,
                score: location.score
            };
            Object.keys(metrics).forEach(function(key) {
                var el = card.querySelector('[data-loc-metric="' + key + '"]');
                if (!el) {
                    return;
                }
                if (key === 'conversion') {
                    el.textContent = formatNumber(metrics[key] || 0, 2) + '%';
                } else {
                    el.textContent = formatNumber(metrics[key] || 0);
                }
            });

            tbody = card.querySelector('tbody[data-loc-top-products="' + location.slug + '"]');
            if (tbody) {
                tbody.innerHTML = buildTopProductsRows(location.top_products || []);
            }
        });
    }

    function applyAnalyticsPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        updateGlobalMetrics(payload.global_stats || payload.globalStats);
        updateTopLocation(payload.top_location || payload.topLocation);
        updateTopProduct(payload.top_product || payload.topProduct);
        renderRankings(payload.location_rankings || payload.locationRankings || []);
        updateLocationCards(payload.locations || []);
        if (payload.processing_count !== undefined) {
            updateProcessingBubble(payload.processing_count);
        }
    }

    function fetchLiveAnalytics() {
        if (!config.liveNonce) {
            return;
        }

        $.post(config.ajaxUrl || window.ajaxurl, {
            action: 'mulopimfwc_analytics_live_data',
            nonce: config.liveNonce,
            location: config.location,
            date_range: config.dateRange,
            date_from: config.dateFrom,
            date_to: config.dateTo
        }).done(function(response) {
            if (response && response.success) {
                applyAnalyticsPayload(response.data);
            }
        });
    }

    function submitExport(locationSlug) {
        var form = $('<form>', {
            method: 'POST',
            action: config.ajaxUrl || window.ajaxurl
        });

        form.css('display', 'none');
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'mulopimfwc_export_analytics'
        }));
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: config.exportNonce || ''
        }));
        if (locationSlug) {
            form.append($('<input>', {
                type: 'hidden',
                name: 'location',
                value: locationSlug
            }));
        }
        $('body').append(form);
        form.trigger('submit');
        setTimeout(function() {
            form.remove();
        }, 1000);
    }

    function initFilters() {
        var filterPanel = $('.mulopimfwc-analytics-filter-panel');
        var filterToggle = $('.mulopimfwc-analytics-filter-toggle');
        var dateRangeInput = $('#mulopimfwc_analytics_date_range');
        var dateFromInput = $('#mulopimfwc_analytics_date_from');
        var dateToInput = $('#mulopimfwc_analytics_date_to');

        function formatDate(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function setQuickRange(range) {
            var today = new Date();
            var from = new Date(today);
            var to = new Date(today);

            if (range === 'last_7_days') {
                from.setDate(today.getDate() - 6);
            } else if (range === 'this_month') {
                from = new Date(today.getFullYear(), today.getMonth(), 1);
            } else if (range === 'last_month') {
                from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                to = new Date(today.getFullYear(), today.getMonth(), 0);
            } else if (range === 'this_year') {
                from = new Date(today.getFullYear(), 0, 1);
            }

            dateRangeInput.val(range);
            dateFromInput.val(formatDate(from));
            dateToInput.val(formatDate(to));
            $('.mulopimfwc-analytics-filters .lwp-quick-btn').removeClass('active');
            $('.mulopimfwc-analytics-filters .lwp-quick-btn[data-range="' + range + '"]').addClass('active');
        }

        filterToggle.on('click', function() {
            $(this).toggleClass('active');
            filterPanel.toggleClass('show');
        });

        $('.mulopimfwc-analytics-filters .lwp-quick-btn').on('click', function() {
            setQuickRange($(this).data('range'));
        });

        $('.mulopimfwc-analytics-filters input[type="date"]').on('change', function() {
            dateRangeInput.val('custom');
            $('.mulopimfwc-analytics-filters .lwp-quick-btn').removeClass('active');
        });
    }

    function initExport() {
        if (!config.canExport) {
            return;
        }

        $(document).on('click', '.export-location-btn', function(event) {
            event.preventDefault();
            submitExport($(this).data('location'));
        });

        if ($('.mulopimfwc-export-all').length === 0) {
            $('.wrap h1').append(
                $('<button>', {
                    type: 'button',
                    class: 'button button-primary mulopimfwc-export-all',
                    text: config.exportAllText || 'Export All Data'
                })
            );
        }

        $(document).on('click', '.mulopimfwc-export-all', function(event) {
            event.preventDefault();
            submitExport('');
        });
    }

    $(function() {
        initFilters();
        initExport();
        fetchLiveAnalytics();
        if (config.pollInterval) {
            window.setInterval(fetchLiveAnalytics, parseInt(config.pollInterval, 10));
        }
    });
})(jQuery);
