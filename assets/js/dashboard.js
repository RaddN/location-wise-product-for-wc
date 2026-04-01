/**
 * Location Wise Products Dashboard JavaScript
 */
(function ($) {
    'use strict';
    const LIVE_POLL_INTERVAL = 30000;
    const LIVE_RATE_LIMIT_MS = 5000;

    // Store chart instances globally for updates
    window.locationProductsChart = null;
    window.locationStockChart = null;
    window.ordersByLocationChart = null;
    window.revenueByLocationChart = null;
    window.newProductsChart = null;
    window.investmentChart = null;
    window.mulopimfwcFiltersApplied = false;

    $(document).ready(function () {
        initDashboardCharts();
        initFilterHandlers();
        initExportHandlers();
        initProfitabilityPanel();
        startRealtimeSync();
    });

    /**
     * Initialize all dashboard charts
     */
    function initDashboardCharts() {
        // Get data from the localized script
        const data = window.mulopimfwc_DashboardData;

        if (!data) {
            console.error('Dashboard data not available');
            return;
        }

        initProductsChart();
        initStockChart();
        initNewProductsChart();
        initInvestmentChart();
        initOrdersChart();
        initRevenueChart();
    }

    /**
     * Initialize filter handlers
     */
    function initFilterHandlers() {
        // Check for saved filter state
        const filterState = localStorage.getItem('lwp_filter_state');
        if (filterState === 'open') {
            $('.lwp-dashboard-filters').addClass('show');
            $('.filter_toggle_btn').addClass('active');
        }

        // Filter toggle button
        $('.filter_toggle_btn').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $filters = $('.lwp-dashboard-filters');
            const $btn = $(this);

            if ($filters.hasClass('show')) {
                $filters.removeClass('show');
                $btn.removeClass('active');
                localStorage.setItem('lwp_filter_state', 'closed');
            } else {
                $filters.addClass('show');
                $btn.addClass('active');
                localStorage.setItem('lwp_filter_state', 'open');
            }
        });

        // Quick date range buttons
        $('.lwp-quick-btn').on('click', function () {
            $('.lwp-quick-btn').removeClass('active');
            $(this).addClass('active');

            const days = $(this).data('days');
            const period = $(this).data('period');
            const today = new Date();

            let dateFrom, dateTo;

            if (days) {
                dateTo = formatDate(today);
                const fromDate = new Date(today);
                fromDate.setDate(today.getDate() - (days - 1));
                dateFrom = formatDate(fromDate);
            } else if (period === 'this-month') {
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                dateFrom = formatDate(firstDay);
                dateTo = formatDate(today);
            } else if (period === 'last-month') {
                const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
                dateFrom = formatDate(firstDay);
                dateTo = formatDate(lastDay);
            }

            $('#filter-date-from').val(dateFrom);
            $('#filter-date-to').val(dateTo);
        });

        // Apply filters
        $('#apply-filters').on('click', function () {
            const dateFrom = $('#filter-date-from').val();
            const dateTo = $('#filter-date-to').val();
            const location = $('#filter-location').val();
            const status = $('#filter-status').val();
            const filtersActive = isFilterActiveInput(dateFrom, dateTo, location, status);

            if (dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)) {
                alert('Date From cannot be later than Date To');
                return;
            }

            $('#lwp-loading-overlay').fadeIn(200);

            $.ajax({
                url: mulopimfwc_DashboardData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_apply_filters',
                    nonce: mulopimfwc_DashboardData.dashboard_nonce,
                    date_from: dateFrom,
                    date_to: dateTo,
                    location: location,
                    status: status
                },
                success: function (response) {
                    if (response.success) {
                        updateDashboardData(response.data);
                        if (response.data && response.data.comparison) {
                            updateComparisonIndicators(response.data.comparison);
                        } else {
                            clearComparisonIndicators();
                        }
                        window.mulopimfwcFiltersApplied = filtersActive;
                        if (filtersActive) {
                            showFilterBadge();
                            updateConnectionStatus('paused');
                        } else {
                            $('.lwp-filter-active-badge').remove();
                        }

                        loadProfitabilityPanel({
                            location: location
                        });
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Filter error:', error);
                },
                complete: function () {
                    $('#lwp-loading-overlay').fadeOut(200);
                }
            });
        });

        // Reset filters
        $('#reset-filters').on('click', function () {
            $('#filter-date-from').val('');
            $('#filter-date-to').val('');
            $('#filter-location').val('all');
            $('#filter-status').val('all');
            $('.lwp-quick-btn').removeClass('active');
            $('.lwp-filter-active-badge').remove();
            clearComparisonIndicators();
            window.mulopimfwcFiltersApplied = false;
            location.reload();
        });
    }

    /**
     * Initialize export handlers
     */
    function initExportHandlers() {
        const $dropdown = $('.export_report_dropdown');
        const $toggleBtn = $dropdown.find('.export_toggle_btn');
        const $menu = $dropdown.find('.dropdown_menu');

        // Toggle dropdown
        $toggleBtn.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close if already open
            if ($dropdown.hasClass('active')) {
                $dropdown.removeClass('active');
                $menu.removeClass('show');
            } else {
                $dropdown.addClass('active');
                $menu.addClass('show');
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function (e) {
            if (!$dropdown.is(e.target) && $dropdown.has(e.target).length === 0) {
                $dropdown.removeClass('active');
                $menu.removeClass('show');
            }
        });

        // Prevent menu from closing when clicking inside
        $menu.on('click', function (e) {
            e.stopPropagation();
        });

        // Export report button click
        $('.export_report').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(this);
            const originalHTML = $button.html();
            const format = $button.data('format');

            // Close dropdown
            $dropdown.removeClass('active');
            $menu.removeClass('show');

            // Disable button and show loading state
            $button.prop('disabled', true).text('Exporting...');

            // Create form and submit
            const $form = $('<form>', {
                method: 'POST',
                action: mulopimfwc_DashboardData.ajaxurl
            }).append(
                $('<input>', { type: 'hidden', name: 'action', value: 'mulopimfwc_export_dashboard_report' }),
                $('<input>', { type: 'hidden', name: 'nonce', value: mulopimfwc_DashboardData.export_nonce }),
                $('<input>', { type: 'hidden', name: 'format', value: format })
            );

            $('body').append($form);
            $form.submit();

            // Re-enable button after delay
            setTimeout(function () {
                $button.prop('disabled', false).html(originalHTML);
                $form.remove();
            }, 2000);
        });
    }

    /**
     * Update dashboard with filtered data
     */
    function updateDashboardData(data) {
        syncDashboardCurrencyFromResponse(data);

        if (data.summary) {
            $('.lwp-stat-item').each(function () {
                const metric = $(this).find('.lwp-stat-progress').data('metric') || '';
                const $label = $(this).find('.lwp-stat-label');
                const $value = $(this).find('.lwp-stat-value');

                if (metric === 'products' && data.summary.total_products !== undefined) {
                    $value.text(data.summary.total_products.toLocaleString());
                } else if (metric === 'locations') {
                    if (data.summary.location_card_label !== undefined) {
                        $label.text(data.summary.location_card_label);
                    }

                    if (data.summary.location_card_value !== undefined) {
                        if (typeof data.summary.location_card_value === 'number') {
                            $value.text(data.summary.location_card_value.toLocaleString());
                        } else {
                            $value.text(data.summary.location_card_value);
                        }
                    } else if (data.summary.total_locations !== undefined) {
                        $value.text(data.summary.total_locations.toLocaleString());
                    }
                } else if (metric === 'orders' && data.summary.total_orders !== undefined) {
                    $value.text(data.summary.total_orders.toLocaleString());
                } else if (metric === 'revenue' && data.summary.total_revenue !== undefined) {
                    $value.html(formatCurrency(data.summary.total_revenue));
                } else if (metric === 'stock' && data.summary.total_stock !== undefined) {
                    $value.text(data.summary.total_stock.toLocaleString());
                } else if (metric === 'investment' && data.summary.total_investment !== undefined) {
                    $value.html(formatCurrency(data.summary.total_investment));
                }
            });
        }

        if (data.productCounts) {
            refreshProductsChart(data.productCounts, data.previousProductCounts);
        }

        if (data.stockLevels) {
            refreshStockChart(data.stockLevels, data.previousStockLevels);
        }

        if (data.orders) {
            refreshOrdersChart(data.orders, data.previousOrders);
        }

        if (data.revenue) {
            refreshRevenueChart(data.revenue, data.previousRevenue);
        }

        if (data.dateLabels && data.dateCounts) {
            refreshNewProductsChart(data);
        }

        if (data.monthlyInvestmentLabels && data.monthlyInvestmentData) {
            refreshInvestmentChart(data);
        }

        if (data.low_stock) {
            updateLowStockTable(data.low_stock);
        }
    }

    function initProfitabilityPanel() {
        const queueInitialLoad = function () {
            loadProfitabilityPanel(getCurrentDashboardFilters());
        };

        if (document.readyState === 'complete') {
            window.setTimeout(queueInitialLoad, 0);
            return;
        }

        $(window).one('load', queueInitialLoad);
    }

    function getCurrentDashboardFilters() {
        return {
            dateFrom: $('#filter-date-from').val() || '',
            dateTo: $('#filter-date-to').val() || '',
            location: $('#filter-location').val() || 'all',
            status: $('#filter-status').val() || 'all'
        };
    }

    function setProfitabilityPanelState(state, message) {
        const $panel = $('#lwp-profitability-panel');
        if ($panel.length === 0) {
            return;
        }

        if (state === 'loading') {
            const loadingText = message || mulopimfwc_DashboardData.i18n.loadingProfitability || 'Loading profitability data...';
            $panel.html(`<div class="lwp-profitability-state is-loading">${escapeHtml(loadingText)}</div>`);
            return;
        }

        if (state === 'error') {
            const errorText = message || mulopimfwc_DashboardData.i18n.profitabilityLoadError || 'Unable to load profitability data right now.';
            $panel.html(`<div class="lwp-profitability-state is-error">${escapeHtml(errorText)}</div>`);
        }
    }

    function loadProfitabilityPanel(filters) {
        const $panel = $('#lwp-profitability-panel');
        if ($panel.length === 0) {
            return;
        }

        if (window.mulopimfwcProfitabilityRequest && typeof window.mulopimfwcProfitabilityRequest.abort === 'function') {
            window.mulopimfwcProfitabilityRequest.abort();
        }

        setProfitabilityPanelState('loading');

        const request = $.ajax({
            url: mulopimfwc_DashboardData.ajaxurl,
            type: 'POST',
            data: {
                action: 'mulopimfwc_dashboard_profitability',
                nonce: mulopimfwc_DashboardData.dashboard_nonce,
                location: (filters && filters.location) ? filters.location : 'all',
                dead_stock_days: parseInt($panel.data('dead-stock-days'), 10) || parseInt(mulopimfwc_DashboardData.deadStockDays || 90, 10) || 90
            }
        });

        window.mulopimfwcProfitabilityRequest = request;

        request.done(function (response) {
            if (response && response.success && response.data && typeof response.data.html === 'string') {
                $panel.html(response.data.html);
                return;
            }

            setProfitabilityPanelState('error');
        });

        request.fail(function (xhr, status) {
            if (status === 'abort') {
                return;
            }

            setProfitabilityPanelState('error');
        });

        request.always(function () {
            if (window.mulopimfwcProfitabilityRequest === request) {
                window.mulopimfwcProfitabilityRequest = null;
            }
        });
    }

    /**
     * Update low stock products table
     */
    function updateLowStockTable(products) {
        const tbody = $('.lwp-low-stock-table tbody');

        if (!products || products.length === 0) {
            tbody.html('<tr><td colspan="4" style="text-align: center;">No low stock products found.</td></tr>');
            return;
        }

        tbody.empty();

        products.forEach(function (item) {
            const isOutOfStock = item.status === 'out_of_stock';
            const stockClass = item.status_class || (isOutOfStock ? 'out-of-stock' : 'low-stock');
            const statusText = item.status_label || (isOutOfStock ? 'Out of Stock' : 'Low Stock');
            const editPostId = item.edit_post_id || item.product_id;

            const row = `
                <tr>
                    <td><a href="/wp-admin/post.php?post=${editPostId}&action=edit">${escapeHtml(item.product_title)}</a></td>
                    <td>${escapeHtml(item.location_name)}</td>
                    <td><span class="stock-quantity ${stockClass}">${item.stock}</span></td>
                    <td><span class="stock-status ${stockClass}">${statusText}</span></td>
                </tr>
            `;

            tbody.append(row);
        });
    }

    /**
     * Show filter active badge
     */
    function showFilterBadge() {
        if ($('.lwp-filter-active-badge').length === 0) {
            const badge = `
            <span class="lwp-filter-active-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Filters Active
            </span>
        `;
            $('.lwp-dashboard-overview h1').append(badge);
        }
    }

    function updateComparisonIndicators(comparison) {
        if (!comparison) {
            return;
        }

        const label = comparison.label || '';
        setComparisonMetric('products', comparison.products, label);
        setComparisonMetric('locations', comparison.locations, label);
        setComparisonMetric('orders', comparison.orders, label);
        setComparisonMetric('revenue', comparison.revenue, label);
        setComparisonMetric('investment', comparison.investment, label);
    }

    function setComparisonMetric(metric, data, label) {
        const $el = $(`.lwp-stat-progress[data-metric="${metric}"]`);

        if ($el.length === 0) {
            return;
        }

        if (!data) {
            $el.removeClass('is-visible').empty().removeAttr('title').removeAttr('aria-label');
            return;
        }

        let badgeText = '';
        let badgeClass = 'is-flat';
        let iconType = 'flat';

        if (data.direction === 'new') {
            badgeText = 'New';
            badgeClass = 'is-new';
            iconType = 'new';
        } else {
            const percent = typeof data.percent === 'number' ? data.percent.toFixed(1) : null;

            if (data.direction === 'down') {
                badgeClass = 'is-down';
                badgeText = percent !== null ? `-${percent}%` : '-';
                iconType = 'down';
            } else if (data.direction === 'up') {
                badgeClass = 'is-up';
                badgeText = percent !== null ? `+${percent}%` : '+';
                iconType = 'up';
            } else {
                badgeClass = 'is-flat';
                badgeText = percent !== null ? `${percent}%` : '0.0%';
                iconType = 'flat';
            }
        }

        const cleanedLabel = label ? label.replace(/^vs\s+/i, '') : '';
        const fallbackLabel = mulopimfwc_DashboardData.i18n.previousPeriod || 'previous period';
        const tooltipText = cleanedLabel
            ? `Based on ${cleanedLabel}`
            : `Based on ${fallbackLabel.toLowerCase()}`;

        $el.attr('title', tooltipText);
        $el.attr('aria-label', tooltipText);
        $el.html(`
            <span class="lwp-stat-progress-badge ${badgeClass}">
                ${getProgressIcon(iconType)}
                <span class="lwp-stat-progress-value">${badgeText}</span>
            </span>
        `);
        $el.addClass('is-visible');
    }

    function clearComparisonIndicators() {
        $('.lwp-stat-progress').removeClass('is-visible').empty().removeAttr('title').removeAttr('aria-label');
    }

    function getProgressIcon(type) {
        switch (type) {
            case 'up':
                return '<svg class="lwp-stat-progress-icon" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M6 2L10 6H7V10H5V6H2L6 2Z" fill="currentColor"/></svg>';
            case 'down':
                return '<svg class="lwp-stat-progress-icon" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M6 10L2 6H5V2H7V6H10L6 10Z" fill="currentColor"/></svg>';
            case 'new':
                return '<svg class="lwp-stat-progress-icon" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M5 2H7V5H10V7H7V10H5V7H2V5H5V2Z" fill="currentColor"/></svg>';
            default:
                return '<svg class="lwp-stat-progress-icon" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2 6H10V7H2V6Z" fill="currentColor"/></svg>';
        }
    }

    function isFilterActiveInput(dateFrom, dateTo, location, status) {
        return Boolean(dateFrom || dateTo || location !== 'all' || status !== 'all');
    }

    function filtersAreActive() {
        return window.mulopimfwcFiltersApplied === true;
    }

    function getDashboardCurrencyCode() {
        const currencyCode = mulopimfwc_DashboardData.currency_code;
        if (typeof currencyCode === 'string' && currencyCode.trim() !== '') {
            return currencyCode.trim().toUpperCase();
        }
        return 'USD';
    }

    function resolveCurrencySymbolFromCode(currencyCode) {
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currencyCode,
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(0).replace(/\d/g, '').trim();
        } catch (error) {
            return '$';
        }
    }

    function getDashboardCurrencySymbol() {
        const symbol = mulopimfwc_DashboardData.currency;
        if (typeof symbol === 'string' && symbol !== '') {
            return symbol;
        }
        return resolveCurrencySymbolFromCode(getDashboardCurrencyCode());
    }

    function updateCurrencyLabelsOnCharts() {
        const currencySymbol = resolveCurrencySymbolFromCode(getDashboardCurrencyCode());

        if (window.investmentChart && window.investmentChart.options && window.investmentChart.options.scales && window.investmentChart.options.scales.y) {
            const investmentLabel = mulopimfwc_DashboardData.i18n.investment || 'Investment';
            window.investmentChart.options.scales.y.title.text = `${investmentLabel} (${currencySymbol})`;
            window.investmentChart.update();
        }

        if (window.revenueByLocationChart && window.revenueByLocationChart.options && window.revenueByLocationChart.options.scales && window.revenueByLocationChart.options.scales.y) {
            const revenueLabel = mulopimfwc_DashboardData.i18n.revenue || 'Revenue';
            window.revenueByLocationChart.options.scales.y.title.text = `${revenueLabel} (${currencySymbol})`;
            window.revenueByLocationChart.update();
        }
    }

    function syncDashboardCurrencyFromResponse(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        let changed = false;

        if (typeof data.currency === 'string' && data.currency !== '') {
            if (mulopimfwc_DashboardData.currency !== data.currency) {
                mulopimfwc_DashboardData.currency = data.currency;
                changed = true;
            }
        }

        if (typeof data.currency_code === 'string' && data.currency_code !== '') {
            const normalizedCode = data.currency_code.toUpperCase();
            if (mulopimfwc_DashboardData.currency_code !== normalizedCode) {
                mulopimfwc_DashboardData.currency_code = normalizedCode;
                changed = true;
            }
        }

        if (changed) {
            updateCurrencyLabelsOnCharts();
        }
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        const symbol = getDashboardCurrencySymbol();
        return symbol + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    /**
     * Helper function to format date as YYYY-MM-DD
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    function startRealtimeSync() {
        if (!mulopimfwc_DashboardData || !mulopimfwc_DashboardData.realtime_nonce) {
            return;
        }

        // Initial fetch
        fetchRealtimeData();

        // Set up interval polling
        const pollInterval = parseInt(mulopimfwc_DashboardData.poll_interval || LIVE_POLL_INTERVAL, 10);

        if (!window.mulopimfwcDashboardRealtimeTimer) {
            window.mulopimfwcDashboardRealtimeTimer = setInterval(function () {
                fetchRealtimeData();
            }, pollInterval);
        }

        // Add connection status indicator
        addConnectionStatusIndicator();

        // Handle visibility change - pause when tab is hidden, resume when visible
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                // Tab is hidden, can pause or reduce frequency
                if (window.mulopimfwcDashboardRealtimeTimer) {
                    clearInterval(window.mulopimfwcDashboardRealtimeTimer);
                    window.mulopimfwcDashboardRealtimeTimer = null;
                }
            } else {
                // Tab is visible, resume polling
                if (!window.mulopimfwcDashboardRealtimeTimer) {
                    fetchRealtimeData(); // Immediate fetch
                    window.mulopimfwcDashboardRealtimeTimer = setInterval(function () {
                        fetchRealtimeData();
                    }, pollInterval);
                }
            }
        });
    }

    function scheduleRealtimeRetry(delayMs) {
        if (window.mulopimfwcLiveRetryTimer) {
            return;
        }

        const delay = Math.max((delayMs || 0) + 250, 250);
        window.mulopimfwcLiveRetryTimer = setTimeout(function () {
            window.mulopimfwcLiveRetryTimer = null;
            fetchRealtimeData();
        }, delay);
    }

    function fetchRealtimeData() {
        if (filtersAreActive()) {
            updateConnectionStatus('paused');
            return;
        }

        const now = Date.now();
        const lastRequestAt = window.mulopimfwcLiveRequestAt || 0;
        if (lastRequestAt && (now - lastRequestAt) < LIVE_RATE_LIMIT_MS) {
            scheduleRealtimeRetry(LIVE_RATE_LIMIT_MS - (now - lastRequestAt));
            return;
        }

        if (window.mulopimfwcRealtimeActive) {
            return;
        }

        window.mulopimfwcRealtimeActive = true;
        updateConnectionStatus('syncing');

        const startTime = Date.now();
        window.mulopimfwcLiveRequestAt = startTime;

        $.post(mulopimfwc_DashboardData.ajaxurl, {
            action: 'mulopimfwc_dashboard_live_data',
            nonce: mulopimfwc_DashboardData.realtime_nonce
        })
            .done(function (response) {
                const duration = Date.now() - startTime;

                if (response.success && response.data) {
                    updateDashboardData(response.data);
                    document.dispatchEvent(new CustomEvent('mulopimfwcRealtimeData', { detail: response.data }));
                    updateConnectionStatus('connected', duration);
                } else {
                    updateConnectionStatus('error');
                    console.warn('Dashboard sync failed:', response);
                }
            })
            .fail(function (xhr, status, error) {
                updateConnectionStatus('error');
                console.error('Dashboard sync error:', error);
            })
            .always(function () {
                window.mulopimfwcRealtimeActive = false;
            });
    }

    function addConnectionStatusIndicator() {
        if ($('#mulopimfwc-connection-status').length > 0) {
            return;
        }

        const indicator = $('<div id="mulopimfwc-connection-status" style="position: fixed; bottom: 20px; right: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 10000; display: flex; align-items: center; gap: 8px;">' +
            '<span class="status-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span>' +
            '<span class="status-text">Connected</span>' +
            '</div>');

        $('body').append(indicator);

        // Hide after 3 seconds if connected
        setTimeout(function () {
            if (indicator.find('.status-dot').css('background-color') === 'rgb(16, 185, 129)') {
                indicator.fadeOut();
            }
        }, 3000);
    }

    function updateConnectionStatus(status, duration) {
        const indicator = $('#mulopimfwc-connection-status');
        if (indicator.length === 0) {
            return;
        }

        const dot = indicator.find('.status-dot');
        const text = indicator.find('.status-text');

        indicator.show();

        switch (status) {
            case 'connected':
                dot.css('background', '#10b981');
                text.text(duration ? `Synced (${duration}ms)` : 'Connected');
                setTimeout(function () {
                    indicator.fadeOut();
                }, 2000);
                break;
            case 'syncing':
                dot.css('background', '#f59e0b');
                text.text('Syncing...');
                break;
            case 'paused':
                dot.css('background', '#6b7280');
                text.text('Paused (filters)');
                break;
            case 'error':
                dot.css('background', '#ef4444');
                text.text('Connection error');
                setTimeout(function () {
                    indicator.fadeOut();
                }, 5000);
                break;
            default:
                dot.css('background', '#6b7280');
                text.text('Disconnected');
        }
    }

    function refreshProductsChart(counts, previousCounts) {
        if (!window.locationProductsChart) {
            return;
        }

        const labels = Object.keys(counts);
        const originalValues = getValuesForLabels(counts, labels);
        const values = addValueOffsets(originalValues);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label] || 'rgba(37, 99, 235, 0.7)');
        const previousColors = labels.map(label => {
            const color = mulopimfwc_DashboardData.locationBorderColors[label] || 'rgba(37, 99, 235, 1)';
            return hexToRgba(color, 0.35);
        });
        window.locationProductsChart.data.labels = labels;
        window.locationProductsChart.data.datasets[0].data = values;
        window.locationProductsChart.data.datasets[0].backgroundColor = bgColors;
        // Store original values in dataset for tooltip/display
        window.locationProductsChart.data.datasets[0].originalValues = originalValues;

        if (previousCounts) {
            const previousValues = getValuesForLabels(previousCounts, labels);
            const previousOffsets = addValueOffsets(previousValues);
            if (!window.locationProductsChart.data.datasets[1]) {
                window.locationProductsChart.data.datasets[1] = {
                    data: [],
                    backgroundColor: previousColors,
                    borderColor: '#f8f9fa',
                    borderWidth: 1,
                    hoverOffset: 0,
                    weight: 0.6,
                    originalValues: [],
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period'
                };
            }
            window.locationProductsChart.data.datasets[1].data = previousOffsets;
            window.locationProductsChart.data.datasets[1].backgroundColor = previousColors;
            window.locationProductsChart.data.datasets[1].originalValues = previousValues;
            window.locationProductsChart.data.datasets[1].hidden = false;
        } else if (window.locationProductsChart.data.datasets[1]) {
            window.locationProductsChart.data.datasets[1].data = [];
            window.locationProductsChart.data.datasets[1].originalValues = [];
            window.locationProductsChart.data.datasets[1].hidden = true;
        }
        window.locationProductsChart.update();
    }

    function refreshStockChart(levels, previousLevels) {
        if (!window.locationStockChart) {
            return;
        }

        const labels = Object.keys(levels);
        const values = getValuesForLabels(levels, labels);
        window.locationStockChart.data.labels = labels;
        window.locationStockChart.data.datasets[0].data = values;

        if (previousLevels) {
            const previousValues = getValuesForLabels(previousLevels, labels);
            if (!window.locationStockChart.data.datasets[1]) {
                window.locationStockChart.data.datasets[1] = {
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period',
                    data: [],
                    borderColor: '#94a3b8',
                    backgroundColor: 'rgba(148, 163, 184, 0.2)',
                    borderDash: [6, 4],
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: '#94a3b8',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: false,
                    hidden: false
                };
            }
            window.locationStockChart.data.datasets[1].data = previousValues;
            window.locationStockChart.data.datasets[1].hidden = false;
        } else if (window.locationStockChart.data.datasets[1]) {
            window.locationStockChart.data.datasets[1].data = [];
            window.locationStockChart.data.datasets[1].hidden = true;
        }
        window.locationStockChart.update();
    }

    function refreshOrdersChart(orders, previousOrders) {
        if (!window.ordersByLocationChart) {
            return;
        }

        const labels = Object.keys(orders);
        const originalValues = getValuesForLabels(orders, labels);
        const values = addValueOffsets(originalValues);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label] || 'rgba(153, 102, 255, 0.7)');
        const previousColors = labels.map(label => {
            const color = mulopimfwc_DashboardData.locationBorderColors[label] || 'rgba(153, 102, 255, 0.7)';
            return hexToRgba(color, 0.35);
        });
        window.ordersByLocationChart.data.labels = labels;
        window.ordersByLocationChart.data.datasets[0].data = values;
        window.ordersByLocationChart.data.datasets[0].backgroundColor = bgColors;
        // Store original values in dataset for tooltip/display
        window.ordersByLocationChart.data.datasets[0].originalValues = originalValues;

        if (previousOrders) {
            const previousValues = getValuesForLabels(previousOrders, labels);
            const previousOffsets = addValueOffsets(previousValues);
            if (!window.ordersByLocationChart.data.datasets[1]) {
                window.ordersByLocationChart.data.datasets[1] = {
                    data: [],
                    backgroundColor: previousColors,
                    borderColor: '#f8f9fa',
                    borderWidth: 1,
                    hoverOffset: 0,
                    weight: 0.6,
                    originalValues: [],
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period'
                };
            }
            window.ordersByLocationChart.data.datasets[1].data = previousOffsets;
            window.ordersByLocationChart.data.datasets[1].backgroundColor = previousColors;
            window.ordersByLocationChart.data.datasets[1].originalValues = previousValues;
            window.ordersByLocationChart.data.datasets[1].hidden = false;
        } else if (window.ordersByLocationChart.data.datasets[1]) {
            window.ordersByLocationChart.data.datasets[1].data = [];
            window.ordersByLocationChart.data.datasets[1].originalValues = [];
            window.ordersByLocationChart.data.datasets[1].hidden = true;
        }
        window.ordersByLocationChart.update();
    }

    function refreshRevenueChart(revenue, previousRevenue) {
        if (!window.revenueByLocationChart) {
            return;
        }

        const labels = Object.keys(revenue);
        const values = getValuesForLabels(revenue, labels);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label] || 'rgba(75, 192, 192, 0.7)');
        const borderColors = labels.map(label => mulopimfwc_DashboardData.locationBorderColors[label] || 'rgba(75, 192, 192, 1)');
        window.revenueByLocationChart.data.labels = labels;
        window.revenueByLocationChart.data.datasets[0].data = values;
        window.revenueByLocationChart.data.datasets[0].backgroundColor = bgColors;
        window.revenueByLocationChart.data.datasets[0].borderColor = borderColors;

        if (previousRevenue) {
            const previousValues = getValuesForLabels(previousRevenue, labels);
            const previousColors = borderColors.map(color => hexToRgba(color, 0.35));
            if (!window.revenueByLocationChart.data.datasets[1]) {
                window.revenueByLocationChart.data.datasets[1] = {
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period',
                    data: [],
                    backgroundColor: previousColors,
                    borderColor: previousColors,
                    borderWidth: 1,
                    barThickness: 30,
                    maxBarThickness: 40
                };
            }
            window.revenueByLocationChart.data.datasets[1].data = previousValues;
            window.revenueByLocationChart.data.datasets[1].backgroundColor = previousColors;
            window.revenueByLocationChart.data.datasets[1].borderColor = previousColors;
            window.revenueByLocationChart.data.datasets[1].hidden = false;
        } else if (window.revenueByLocationChart.data.datasets[1]) {
            window.revenueByLocationChart.data.datasets[1].data = [];
            window.revenueByLocationChart.data.datasets[1].hidden = true;
        }
        window.revenueByLocationChart.update();
    }

    function refreshNewProductsChart(data) {
        if (!window.newProductsChart) {
            return;
        }

        window.newProductsChart.data.labels = data.dateLabels;
        window.newProductsChart.data.datasets[0].data = data.dateCounts;
        if (data.previousDateCounts) {
            if (!window.newProductsChart.data.datasets[1]) {
                window.newProductsChart.data.datasets[1] = {
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period',
                    data: [],
                    fill: false,
                    borderColor: '#94a3b8',
                    backgroundColor: 'rgba(148, 163, 184, 0.2)',
                    borderDash: [6, 4],
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    borderWidth: 2,
                    hidden: false
                };
            }
            window.newProductsChart.data.datasets[1].data = data.previousDateCounts;
            window.newProductsChart.data.datasets[1].hidden = false;
        } else if (window.newProductsChart.data.datasets[1]) {
            window.newProductsChart.data.datasets[1].data = [];
            window.newProductsChart.data.datasets[1].hidden = true;
        }
        window.newProductsChart.update();
    }

    function refreshInvestmentChart(data) {
        if (!window.investmentChart) {
            return;
        }

        window.investmentChart.data.labels = data.monthlyInvestmentLabels;
        window.investmentChart.data.datasets[0].data = data.monthlyInvestmentData;
        if (data.previousInvestmentData) {
            if (!window.investmentChart.data.datasets[1]) {
                window.investmentChart.data.datasets[1] = {
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period',
                    data: [],
                    fill: false,
                    borderColor: '#94a3b8',
                    backgroundColor: 'rgba(148, 163, 184, 0.2)',
                    borderDash: [6, 4],
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    borderWidth: 2,
                    hidden: false
                };
            }
            window.investmentChart.data.datasets[1].data = data.previousInvestmentData;
            window.investmentChart.data.datasets[1].hidden = false;
        } else if (window.investmentChart.data.datasets[1]) {
            window.investmentChart.data.datasets[1].data = [];
            window.investmentChart.data.datasets[1].hidden = true;
        }
        window.investmentChart.update();
    }

    window.mulopimfwc_update_dashboard = updateDashboardData;

    /**
     * Add small offsets to duplicate values to prevent overlapping in charts
     */
    function addValueOffsets(values) {
        const numericValues = values.map(value => {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : 0;
        });
        const total = numericValues.reduce((sum, value) => sum + value, 0);

        if (total <= 0) {
            return numericValues;
        }

        const valueMap = {};
        const offsetValues = [...numericValues];

        // Group indices by value
        numericValues.forEach((value, index) => {
            if (value <= 0) {
                return;
            }
            const key = String(value);
            if (!valueMap[key]) {
                valueMap[key] = [];
            }
            valueMap[key].push(index);
        });

        // Add tiny offsets to duplicate values
        Object.keys(valueMap).forEach(key => {
            const indices = valueMap[key];
            if (indices.length > 1) {
                // Multiple items with same value - add small offsets
                indices.forEach((index, offsetIndex) => {
                    // Add very small offset (0.0001 * offsetIndex) to ensure different angles
                    offsetValues[index] = parseFloat(key) + (offsetIndex * 0.0001);
                });
            }
        });

        return offsetValues;
    }

    function getValuesForLabels(source, labels) {
        if (!source) {
            return labels.map(() => 0);
        }

        return labels.map(label => {
            const value = source[label];
            return value !== undefined ? value : 0;
        });
    }

    function hexToRgba(color, alpha) {
        if (!color || typeof color !== 'string') {
            return color;
        }

        if (color.startsWith('rgba')) {
            return color;
        }

        if (color.startsWith('rgb')) {
            return color.replace('rgb(', 'rgba(').replace(')', `, ${alpha})`);
        }

        if (color[0] !== '#') {
            return color;
        }

        let hex = color.slice(1);
        if (hex.length === 3) {
            hex = hex.split('').map(char => char + char).join('');
        }

        if (hex.length !== 6) {
            return color;
        }

        const num = parseInt(hex, 16);
        const r = (num >> 16) & 255;
        const g = (num >> 8) & 255;
        const b = num & 255;

        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    /**
     * Initialize Products by Location Chart
     */
    function initProductsChart() {
        const ctx = document.getElementById('locationProductsChart');
        if (!ctx) return;

        const labels = Object.keys(mulopimfwc_DashboardData.productCounts);
        const originalValues = Object.values(mulopimfwc_DashboardData.productCounts);
        const values = addValueOffsets(originalValues);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label] || 'rgba(37, 99, 235, 0.7)');
        const borderColors = labels.map(label => mulopimfwc_DashboardData.locationBorderColors[label] || 'rgba(37, 99, 235, 1)');
        const previousColors = borderColors.map(color => hexToRgba(color, 0.35));

        window.locationProductsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: '#f8f9fa',
                    borderWidth: 2,
                    // Store original values in dataset metadata
                    originalValues: originalValues
                }, {
                    data: [],
                    backgroundColor: previousColors,
                    borderColor: '#f8f9fa',
                    borderWidth: 1,
                    hoverOffset: 0,
                    weight: 0.6,
                    originalValues: [],
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period',
                    hidden: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '45%',
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                // Use original value for display, not the offset value
                                const origValues = context.dataset.originalValues || originalValues;
                                const value = origValues[context.dataIndex];
                                const total = origValues.reduce((sum, v) => sum + v, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                const datasetLabel = context.dataset.label ? `${context.dataset.label} - ` : '';
                                return `${datasetLabel}${context.label}: ${value} (${percentage}%)`;
                            }
                        },
                        bodyFont: { size: 14 },
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6
                    },
                    datalabels: false
                },
                layout: {
                    padding: { top: 40, bottom: 40, left: 120, right: 120 }
                }
            },
            plugins: [{
                afterDraw: function (chart) {
                    const ctx = chart.ctx;
                    const chartArea = chart.chartArea;
                    const centerX = (chartArea.left + chartArea.right) / 2;
                    const centerY = (chartArea.top + chartArea.bottom) / 2;
                    const radius = Math.min(
                        (chartArea.right - chartArea.left) / 2,
                        (chartArea.bottom - chartArea.top) / 2
                    );

                    const origValues = chart.data.datasets[0].originalValues || originalValues;
                    const total = origValues.reduce((sum, value) => sum + value, 0);
                    if (total <= 0) {
                        const emptyLabel = (mulopimfwc_DashboardData.i18n && mulopimfwc_DashboardData.i18n.noOrders)
                            ? mulopimfwc_DashboardData.i18n.noOrders
                            : 'No orders yet';
                        ctx.fillStyle = '#9ca3af';
                        ctx.font = '14px Arial, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(emptyLabel, centerX, centerY);
                        return;
                    }

                    const percentages = origValues.map(v => total > 0 ? ((v / total) * 100).toFixed(1) : '0.0');
                    const datasetColors = chart.data.datasets[0].backgroundColor || bgColors;
                    const chartData = chart.data.datasets[0].data;

                    // Calculate initial label positions
                    const labelPositions = [];
                    chartData.forEach((value, index) => {
                        const meta = chart.getDatasetMeta(0);
                        const arc = meta.data[index];
                        const angle = (arc.startAngle + arc.endAngle) / 2;

                        const lineLength = 30;
                        const x2 = centerX + Math.cos(angle) * (radius + lineLength);
                        const y2 = centerY + Math.sin(angle) * (radius + lineLength);
                        const horizontalLength = 20;
                        const x3 = x2 + (Math.cos(angle) > 0 ? horizontalLength : -horizontalLength);

                        labelPositions.push({
                            index,
                            angle,
                            x1: centerX + Math.cos(angle) * radius,
                            y1: centerY + Math.sin(angle) * radius,
                            x2, y2, x3,
                            y3: y2,
                            label: `${chart.data.labels[index]}: ${percentages[index]}%`,
                            color: datasetColors[index],
                            side: Math.cos(angle) > 0 ? 'right' : 'left'
                        });
                    });

                    // Adjust overlapping labels
                    const leftLabels = labelPositions.filter(p => p.side === 'left').sort((a, b) => a.y3 - b.y3);
                    const rightLabels = labelPositions.filter(p => p.side === 'right').sort((a, b) => a.y3 - b.y3);
                    const minSpacing = 20;

                    [leftLabels, rightLabels].forEach(labels => {
                        for (let i = 1; i < labels.length; i++) {
                            if (labels[i].y3 - labels[i - 1].y3 < minSpacing) {
                                labels[i].y3 = labels[i - 1].y3 + minSpacing;
                                labels[i].y2 = labels[i].y3;
                            }
                        }
                    });

                    // Draw all labels
                    [...leftLabels, ...rightLabels].forEach(pos => {
                        ctx.beginPath();
                        ctx.strokeStyle = pos.color;
                        ctx.lineWidth = 1;
                        ctx.moveTo(pos.x1, pos.y1);
                        ctx.lineTo(pos.x2, pos.y2);
                        ctx.lineTo(pos.x3, pos.y3);
                        ctx.stroke();

                        ctx.fillStyle = '#333';
                        ctx.font = '12px Arial, sans-serif';
                        ctx.textAlign = pos.side === 'right' ? 'left' : 'right';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(pos.label, pos.x3 + (pos.side === 'right' ? 5 : -5), pos.y3);
                    });
                }
            }]
        });
    }

    /**
     * Initialize Stock Levels by Location Chart
     */
    function initStockChart() {
        const ctx = document.getElementById('locationStockChart');
        if (!ctx) return;

        const labels = Object.keys(mulopimfwc_DashboardData.stockLevels);
        const values = Object.values(mulopimfwc_DashboardData.stockLevels);

        const canvas = ctx;
        const chartCtx = canvas.getContext('2d');

        const gradient = chartCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(255, 77, 77, 0.6)');
        gradient.addColorStop(1, 'rgba(255, 242, 242, 0)');

        window.locationStockChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: mulopimfwc_DashboardData.i18n.totalStock,
                    data: values,
                    backgroundColor: gradient,
                    borderColor: '#ff4d4d',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#ff4d4d',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }, {
                    label: mulopimfwc_DashboardData.i18n.previousPeriod || 'Previous period',
                    data: [],
                    borderColor: '#94a3b8',
                    backgroundColor: 'rgba(148, 163, 184, 0.2)',
                    borderDash: [6, 4],
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3,
                    pointBackgroundColor: '#94a3b8',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    hidden: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                        ticks: { font: { size: 11 }, color: '#666' },
                        title: { display: true, text: 'Stock Level', font: { size: 12 }, color: '#666' }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { font: { size: 11 }, color: '#333' }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: { usePointStyle: true, pointStyle: 'circle', font: { size: 11 }, color: '#666', padding: 15 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }

    /**
     * Initialize New Products Chart
     */
    function initNewProductsChart() {
        const ctx = document.getElementById('newProductsChart');
        if (!ctx) return;

        window.newProductsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: mulopimfwc_DashboardData.dateLabels,
                datasets: [{
                    label: mulopimfwc_DashboardData.i18n.newProducts,
                    data: mulopimfwc_DashboardData.dateCounts,
                    fill: { target: 'origin', above: 'rgba(37, 99, 235, 0.08)' },
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#6b7280', font: { size: 12 }, padding: 8 }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#f3f4f6', drawTicks: false },
                        title: {
                            display: true,
                            text: 'Number of Products',
                            color: '#374151',
                            font: { size: 13, weight: '500' },
                            padding: { bottom: 10 }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: { size: 12 },
                            padding: 10,
                            stepSize: 1,
                            precision: 0,
                            callback: function (value) {
                                if (Math.floor(value) === value) return value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                const count = context.raw;
                                const label = mulopimfwc_DashboardData.i18n.newProducts || 'Products';
                                const datasetLabel = context.dataset.label && context.dataset.label !== label
                                    ? context.dataset.label + ' - '
                                    : '';
                                return datasetLabel + label + ': ' + count + (count === 1 ? ' product' : ' products');
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Investment Chart
     */
    function initInvestmentChart() {
        const ctx = document.getElementById('investment-30day');
        if (!ctx) return;

        const currencySymbol = resolveCurrencySymbolFromCode(getDashboardCurrencyCode());

        window.investmentChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: mulopimfwc_DashboardData.monthlyInvestmentLabels,
                datasets: [{
                    label: mulopimfwc_DashboardData.i18n.investment,
                    data: mulopimfwc_DashboardData.monthlyInvestmentData,
                    fill: { target: 'origin', above: 'rgba(37, 99, 235, 0.08)' },
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#6b7280', font: { size: 12 }, padding: 8 }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#f3f4f6', drawTicks: false },
                        title: {
                            display: true,
                            text: `Investment (${currencySymbol})`,
                            color: '#374151',
                            font: { size: 13, weight: '500' },
                            padding: { bottom: 10 }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: { size: 12 },
                            padding: 10,
                            callback: function (value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                                else if (value >= 1000) return (value / 1000) + 'k';
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const baseLabel = mulopimfwc_DashboardData.i18n.investment || 'Investment';
                                const datasetLabel = context.dataset.label && context.dataset.label !== baseLabel
                                    ? context.dataset.label + ' - '
                                    : '';
                                const currencyCode = getDashboardCurrencyCode();
                                return datasetLabel + baseLabel + ': ' +
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: currencyCode
                                    }).format(context.raw);
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Orders by Location Chart
     */
    function initOrdersChart() {
        const ctx = document.getElementById('ordersByLocationChart');
        if (!ctx) return;

        const labels = Object.keys(mulopimfwc_DashboardData.ordersByLocation);
        const originalValues = Object.values(mulopimfwc_DashboardData.ordersByLocation);
        const values = addValueOffsets(originalValues);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label] || 'rgba(153, 102, 255, 0.7)');

        window.ordersByLocationChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: '#f8f9fa',
                    borderWidth: 2,
                    hoverOffset: 10,
                    // Store original values in dataset metadata
                    originalValues: originalValues
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                // Use original value for display, not the offset value
                                const origValues = context.dataset.originalValues || originalValues;
                                const value = origValues[context.dataIndex];
                                const total = origValues.reduce((sum, v) => sum + v, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                const datasetLabel = context.dataset.label ? `${context.dataset.label} - ` : '';
                                return `${datasetLabel}${context.label}: ${value} ${mulopimfwc_DashboardData.i18n.orders} (${percentage}%)`;
                            }
                        }
                    }
                },
                layout: { padding: { top: 40, bottom: 40, left: 120, right: 120 } }
            },
            plugins: [{
                afterDraw: function (chart) {
                    const ctx = chart.ctx;
                    const chartArea = chart.chartArea;
                    const centerX = (chartArea.left + chartArea.right) / 2;
                    const centerY = (chartArea.top + chartArea.bottom) / 2;
                    const radius = Math.min(
                        (chartArea.right - chartArea.left) / 2,
                        (chartArea.bottom - chartArea.top) / 2
                    );

                    const origValues = chart.data.datasets[0].originalValues || originalValues;
                    const total = origValues.reduce((sum, value) => sum + value, 0);
                    const percentages = origValues.map(v => total > 0 ? ((v / total) * 100).toFixed(1) : '0.0');
                    const datasetColors = chart.data.datasets[0].backgroundColor || bgColors;
                    const chartData = chart.data.datasets[0].data;

                    // Calculate initial label positions
                    const labelPositions = [];
                    chartData.forEach((value, index) => {
                        const meta = chart.getDatasetMeta(0);
                        const arc = meta.data[index];
                        const angle = (arc.startAngle + arc.endAngle) / 2;

                        const lineLength = 30;
                        const x2 = centerX + Math.cos(angle) * (radius + lineLength);
                        const y2 = centerY + Math.sin(angle) * (radius + lineLength);
                        const horizontalLength = 20;
                        const x3 = x2 + (Math.cos(angle) > 0 ? horizontalLength : -horizontalLength);

                        labelPositions.push({
                            index,
                            angle,
                            x1: centerX + Math.cos(angle) * radius,
                            y1: centerY + Math.sin(angle) * radius,
                            x2, y2, x3,
                            y3: y2,
                            label: `${chart.data.labels[index]}: ${percentages[index]}%`,
                            color: datasetColors[index],
                            side: Math.cos(angle) > 0 ? 'right' : 'left'
                        });
                    });

                    // Adjust overlapping labels
                    const leftLabels = labelPositions.filter(p => p.side === 'left').sort((a, b) => a.y3 - b.y3);
                    const rightLabels = labelPositions.filter(p => p.side === 'right').sort((a, b) => a.y3 - b.y3);
                    const minSpacing = 20;

                    [leftLabels, rightLabels].forEach(labels => {
                        for (let i = 1; i < labels.length; i++) {
                            if (labels[i].y3 - labels[i - 1].y3 < minSpacing) {
                                labels[i].y3 = labels[i - 1].y3 + minSpacing;
                                labels[i].y2 = labels[i].y3;
                            }
                        }
                    });

                    // Draw all labels
                    [...leftLabels, ...rightLabels].forEach(pos => {
                        ctx.beginPath();
                        ctx.strokeStyle = pos.color;
                        ctx.lineWidth = 1;
                        ctx.moveTo(pos.x1, pos.y1);
                        ctx.lineTo(pos.x2, pos.y2);
                        ctx.lineTo(pos.x3, pos.y3);
                        ctx.stroke();

                        ctx.fillStyle = '#333';
                        ctx.font = '12px Arial, sans-serif';
                        ctx.textAlign = pos.side === 'right' ? 'left' : 'right';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(pos.label, pos.x3 + (pos.side === 'right' ? 5 : -5), pos.y3);
                    });
                }
            }]
        });
    }

    /**
     * Initialize Revenue by Location Chart
     */
    function initRevenueChart() {
        const ctx = document.getElementById('revenueByLocationChart');
        if (!ctx) return;

        const labels = Object.keys(mulopimfwc_DashboardData.revenueByLocation);
        const values = Object.values(mulopimfwc_DashboardData.revenueByLocation);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label] || 'rgba(75, 192, 192, 0.7)');
        const borderColors = labels.map(label => mulopimfwc_DashboardData.locationBorderColors[label] || 'rgba(75, 192, 192, 1)');
        const currencySymbol = resolveCurrencySymbolFromCode(getDashboardCurrencyCode());

        window.revenueByLocationChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: mulopimfwc_DashboardData.i18n.revenue,
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: 0,
                    borderRadius: 6,
                    barThickness: 50,
                    maxBarThickness: 60
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#6b7280', font: { size: 12 } }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#f3f4f6', drawTicks: false },
                        title: {
                            display: true,
                            text: `Revenue (${currencySymbol})`,
                            color: '#374151',
                            font: { size: 13, weight: '500' },
                            padding: { bottom: 10 }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: { size: 12 },
                            padding: 10,
                            callback: function (value) {
                                if (value >= 1000) return (value / 1000) + 'k';
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const baseLabel = mulopimfwc_DashboardData.i18n.revenue || 'Revenue';
                                const datasetLabel = context.dataset.label && context.dataset.label !== baseLabel
                                    ? context.dataset.label + ' - '
                                    : '';
                                const currencyCode = getDashboardCurrencyCode();
                                return datasetLabel + baseLabel + ': ' +
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: currencyCode
                                    }).format(context.raw);
                            }
                        },
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false
                    }
                }
            }
        });
    }

})(jQuery);
