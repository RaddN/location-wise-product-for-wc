/**
 * Location Wise Products Dashboard JavaScript
 */
(function ($) {
    'use strict';

    // Store chart instances globally for updates
    window.ordersByLocationChart = null;
    window.revenueByLocationChart = null;
    window.newProductsChart = null;

    $(document).ready(function () {
        initDashboardCharts();
        initFilterHandlers();
        initExportHandlers();
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
                fromDate.setDate(today.getDate() - days);
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
                        showFilterBadge();
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
        // Update summary statistics
        $('.lwp-stat-item').each(function () {
            const label = $(this).find('.lwp-stat-label').text();

            if (label.includes('Orders') || label.includes('orders')) {
                $(this).find('.lwp-stat-value').text(data.summary.total_orders.toLocaleString());
            } else if (label.includes('Revenue') || label.includes('revenue')) {
                $(this).find('.lwp-stat-value').html(formatCurrency(data.summary.total_revenue));
            }
        });

        // Update orders chart
        if (window.ordersByLocationChart && data.orders) {
            window.ordersByLocationChart.data.datasets[0].data = Object.values(data.orders);
            window.ordersByLocationChart.update();
        }

        // Update revenue chart
        if (window.revenueByLocationChart && data.revenue) {
            window.revenueByLocationChart.data.datasets[0].data = Object.values(data.revenue);
            window.revenueByLocationChart.update();
        }

        // Update new products chart
        if (window.newProductsChart && data.recent_products) {
            window.newProductsChart.data.labels = data.recent_products.labels;
            window.newProductsChart.data.datasets[0].data = data.recent_products.counts;
            window.newProductsChart.update();
        }

        // Update low stock table
        if (data.low_stock) {
            updateLowStockTable(data.low_stock);
        }
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
            const stockClass = item.stock == 0 ? 'out-of-stock' : 'low-stock';
            const statusText = item.stock == 0 ? 'Out of Stock' : 'Low Stock';

            const row = `
                <tr>
                    <td><a href="/wp-admin/post.php?post=${item.product_id}&action=edit">${escapeHtml(item.product_title)}</a></td>
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

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        const symbol = mulopimfwc_DashboardData.currency || '$';
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

    /**
     * Initialize Products by Location Chart
     */
    function initProductsChart() {
        const ctx = document.getElementById('locationProductsChart');
        if (!ctx) return;

        const labels = Object.keys(mulopimfwc_DashboardData.productCounts);
        const values = Object.values(mulopimfwc_DashboardData.productCounts);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label]);
        const borderColors = labels.map(label => mulopimfwc_DashboardData.locationBorderColors[label]);

        const total = values.reduce((a, b) => a + b, 0);
        const percentages = values.map(v => ((v / total) * 100).toFixed(1));

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: '#f8f9fa',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.raw;
                                const percentage = percentages[context.dataIndex];
                                return `${context.label}: ${value} (${percentage}%)`;
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

                    chart.data.datasets[0].data.forEach((value, index) => {
                        const meta = chart.getDatasetMeta(0);
                        const arc = meta.data[index];
                        const angle = (arc.startAngle + arc.endAngle) / 2;

                        const x1 = centerX + Math.cos(angle) * radius;
                        const y1 = centerY + Math.sin(angle) * radius;

                        const lineLength = 30;
                        const x2 = centerX + Math.cos(angle) * (radius + lineLength);
                        const y2 = centerY + Math.sin(angle) * (radius + lineLength);

                        const horizontalLength = 20;
                        const x3 = x2 + (Math.cos(angle) > 0 ? horizontalLength : -horizontalLength);
                        const y3 = y2;

                        ctx.beginPath();
                        ctx.strokeStyle = bgColors[index];
                        ctx.lineWidth = 1;
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                        ctx.lineTo(x3, y3);
                        ctx.stroke();

                        const label = `${chart.data.labels[index]}: ${percentages[index]}%`;
                        ctx.fillStyle = '#333';
                        ctx.font = '12px Arial, sans-serif';
                        ctx.textAlign = Math.cos(angle) > 0 ? 'left' : 'right';
                        ctx.textBaseline = 'middle';

                        const labelX = x3 + (Math.cos(angle) > 0 ? 5 : -5);
                        ctx.fillText(label, labelX, y3);
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

        new Chart(ctx, {
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
                                return label + ': ' + count + (count === 1 ? ' product' : ' products');
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

        const currency_code = mulopimfwc_DashboardData.currency_code;
        const currencySymbol = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency_code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(0).replace(/\d/g, '').trim();

        new Chart(ctx, {
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
                                return mulopimfwc_DashboardData.i18n.investment + ': ' +
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: currency_code
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
        const values = Object.values(mulopimfwc_DashboardData.ordersByLocation);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label] || 'rgba(153, 102, 255, 0.7)');

        const total = values.reduce((a, b) => a + b, 0);
        const percentages = values.map(v => ((v / total) * 100).toFixed(1));

        window.ordersByLocationChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: '#f8f9fa',
                    borderWidth: 2,
                    hoverOffset: 10
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
                                const value = context.raw;
                                const percentage = percentages[context.dataIndex];
                                return `${context.label}: ${value} ${mulopimfwc_DashboardData.i18n.orders} (${percentage}%)`;
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

                    chart.data.datasets[0].data.forEach((value, index) => {
                        const meta = chart.getDatasetMeta(0);
                        const arc = meta.data[index];
                        const angle = (arc.startAngle + arc.endAngle) / 2;

                        const x1 = centerX + Math.cos(angle) * radius;
                        const y1 = centerY + Math.sin(angle) * radius;
                        const lineLength = 30;
                        const x2 = centerX + Math.cos(angle) * (radius + lineLength);
                        const y2 = centerY + Math.sin(angle) * (radius + lineLength);
                        const horizontalLength = 20;
                        const x3 = x2 + (Math.cos(angle) > 0 ? horizontalLength : -horizontalLength);
                        const y3 = y2;

                        ctx.beginPath();
                        ctx.strokeStyle = bgColors[index];
                        ctx.lineWidth = 1;
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                        ctx.lineTo(x3, y3);
                        ctx.stroke();

                        const label = `${chart.data.labels[index]}: ${percentages[index]}%`;
                        ctx.fillStyle = '#333';
                        ctx.font = '12px Arial, sans-serif';
                        ctx.textAlign = Math.cos(angle) > 0 ? 'left' : 'right';
                        ctx.textBaseline = 'middle';
                        const labelX = x3 + (Math.cos(angle) > 0 ? 5 : -5);
                        ctx.fillText(label, labelX, y3);
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
        const currency_code = mulopimfwc_DashboardData.currency_code;

        const currencySymbol = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency_code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(0).replace(/\d/g, '').trim();

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
                                return mulopimfwc_DashboardData.i18n.revenue + ': ' +
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: currency_code
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