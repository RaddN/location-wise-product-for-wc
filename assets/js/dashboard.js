/**
 * Location Wise Products Dashboard JavaScript
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        initDashboardCharts();
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
     * Initialize Products by Location Chart
     */
    function initProductsChart() {
        const ctx = document.getElementById('locationProductsChart');

        if (!ctx) return;

        const labels = Object.keys(mulopimfwc_DashboardData.productCounts);
        const values = Object.values(mulopimfwc_DashboardData.productCounts);
        const bgColors = labels.map(label => mulopimfwc_DashboardData.locationColors[label]);
        const borderColors = labels.map(label => mulopimfwc_DashboardData.locationBorderColors[label]);

        // Calculate percentages
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
                    legend: {
                        display: false // Hide default legend
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.raw;
                                const percentage = percentages[context.dataIndex];
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        },
                        bodyFont: {
                            size: 14
                        },
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6,
                        caretSize: 6,
                        boxPadding: 6
                    },
                    // Custom plugin to draw labels outside the chart
                    datalabels: false
                },
                layout: {
                    padding: {
                        top: 40,
                        bottom: 40,
                        left: 120,
                        right: 120
                    }
                }
            },
            plugins: [{
                // Custom plugin to draw labels with lines
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

                        // Line start point (at the edge of the pie)
                        const x1 = centerX + Math.cos(angle) * radius;
                        const y1 = centerY + Math.sin(angle) * radius;

                        // Line end point (extended outward)
                        const lineLength = 30;
                        const x2 = centerX + Math.cos(angle) * (radius + lineLength);
                        const y2 = centerY + Math.sin(angle) * (radius + lineLength);

                        // Horizontal line extension
                        const horizontalLength = 20;
                        const x3 = x2 + (Math.cos(angle) > 0 ? horizontalLength : -horizontalLength);
                        const y3 = y2;

                        // Draw the line
                        ctx.beginPath();
                        ctx.strokeStyle = bgColors[index];
                        ctx.lineWidth = 1;
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                        ctx.lineTo(x3, y3);
                        ctx.stroke();

                        // Draw the label
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

        // Create gradient - vertical from top to bottom
        const canvas = ctx;
        const chartCtx = canvas.getContext('2d');

        const gradient = chartCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(255, 77, 77, 0.6)'); // Red at top
        gradient.addColorStop(1, 'rgba(255, 242, 242, 0)'); // Transparent at bottom

        const borderColor = '#ff4d4d';

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: mulopimfwc_DashboardData.i18n.totalStock,
                    data: values,
                    backgroundColor: gradient,
                    borderColor: borderColor,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#ff4d4d',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#ff4d4d',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#666'
                        },
                        title: {
                            display: true,
                            text: 'Stock Level',
                            font: {
                                size: 12
                            },
                            color: '#666'
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#333'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 11
                            },
                            color: '#666',
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6,
                        titleFont: {
                            size: 13
                        },
                        bodyFont: {
                            size: 12
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    /**
     * Initialize New Products Chart
     */
    function initNewProductsChart() {
        const ctx = document.getElementById('newProductsChart');

        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: mulopimfwc_DashboardData.dateLabels,
                datasets: [{
                    label: mulopimfwc_DashboardData.i18n.newProducts,
                    data: mulopimfwc_DashboardData.dateCounts,
                    fill: {
                        target: 'origin',
                        above: 'rgba(37, 99, 235, 0.08)'
                    },
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#2563eb',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            },
                            padding: 8
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false
                        },
                        grid: {
                            color: '#f3f4f6',
                            drawTicks: false
                        },
                        title: {
                            display: true,
                            text: 'Number of Products',
                            color: '#374151',
                            font: {
                                size: 13,
                                weight: '500'
                            },
                            padding: {
                                bottom: 10
                            }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            },
                            padding: 10,
                            stepSize: 1,
                            precision: 0,
                            callback: function (value) {
                                // Only show whole numbers
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
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
  * Initialize the monthly investment chart
  */
    function initInvestmentChart() {
        const ctx = document.getElementById('investment-30day');
        if (!ctx) return;

        const currency_code = mulopimfwc_DashboardData.currency_code;

        // Get currency symbol
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
                    fill: {
                        target: 'origin',
                        above: 'rgba(37, 99, 235, 0.08)'
                    },
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#2563eb',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            },
                            padding: 8
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false,
                            dash: [5, 5]
                        },
                        grid: {
                            color: '#f3f4f6',
                            drawTicks: false
                        },
                        title: {
                            display: true,
                            text: `Investment (${currencySymbol})`,
                            color: '#374151',
                            font: {
                                size: 13,
                                weight: '500'
                            },
                            padding: {
                                bottom: 10
                            }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            },
                            padding: 10,
                            callback: function (value) {
                                // Format with k for thousands, M for millions
                                if (value >= 1000000) {
                                    return (value / 1000000).toFixed(1) + 'M';
                                } else if (value >= 1000) {
                                    return (value / 1000) + 'k';
                                }
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
                        callbacks: {
                            label: function (context) {
                                return mulopimfwc_DashboardData.i18n.investment + ': ' +
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: currency_code,
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
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
        const borderColors = labels.map(label => mulopimfwc_DashboardData.locationBorderColors[label] || 'rgba(153, 102, 255, 1)');

        // Calculate percentages
        const total = values.reduce((a, b) => a + b, 0);
        const percentages = values.map(v => ((v / total) * 100).toFixed(1));

        new Chart(ctx, {
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
                    legend: {
                        display: false // Hide default legend
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.raw;
                                const percentage = percentages[context.dataIndex];
                                return `${context.label}: ${value} ${mulopimfwc_DashboardData.i18n.orders} (${percentage}%)`;
                            }
                        },
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                layout: {
                    padding: {
                        top: 40,
                        bottom: 40,
                        left: 120,
                        right: 120
                    }
                }
            },
            plugins: [{
                // Custom plugin to draw labels with lines
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

                        // Line start point (at the edge of the doughnut)
                        const x1 = centerX + Math.cos(angle) * radius;
                        const y1 = centerY + Math.sin(angle) * radius;

                        // Line end point (extended outward)
                        const lineLength = 30;
                        const x2 = centerX + Math.cos(angle) * (radius + lineLength);
                        const y2 = centerY + Math.sin(angle) * (radius + lineLength);

                        // Horizontal line extension
                        const horizontalLength = 20;
                        const x3 = x2 + (Math.cos(angle) > 0 ? horizontalLength : -horizontalLength);
                        const y3 = y2;

                        // Draw the line
                        ctx.beginPath();
                        ctx.strokeStyle = bgColors[index];
                        ctx.lineWidth = 1;
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                        ctx.lineTo(x3, y3);
                        ctx.stroke();

                        // Draw the label
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

        // Get currency symbol
        const currencySymbol = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency_code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(0).replace(/\d/g, '').trim();

        new Chart(ctx, {
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
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false
                        },
                        grid: {
                            color: '#f3f4f6',
                            drawTicks: false
                        },
                        title: {
                            display: true,
                            text: `Revenue (${currencySymbol})`,
                            color: '#374151',
                            font: {
                                size: 13,
                                weight: '500'
                            },
                            padding: {
                                bottom: 10
                            }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            },
                            padding: 10,
                            callback: function (value) {
                                // Format with k for thousands
                                if (value >= 1000) {
                                    return (value / 1000) + 'k';
                                }
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
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










jQuery(document).ready(function($) {
    // Handle Export Report button click
    $('.export_report').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const originalHTML = button.html();
        const format = button.data('format');
        
        // Disable button and show loading state
        button.prop('disabled', true).text('Exporting...');
        
        // Make AJAX request
        $.ajax({
            url: mulopimfwc_DashboardData.ajaxurl,
            type: 'POST',
            data: {
                action: 'mulopimfwc_export_dashboard_report',
                nonce: mulopimfwc_DashboardData.export_nonce,
                format: format
            },
            xhrFields: {
                responseType: 'blob' // Important for file download
            },
            success: function(blob, status, xhr) {
                // Get filename from response header or use default
                const disposition = xhr.getResponseHeader('Content-Disposition');
                let filename = 'location-wise-report-' + new Date().getTime() + '.csv';
                
                if (disposition && disposition.indexOf('filename=') !== -1) {
                    const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                    const matches = filenameRegex.exec(disposition);
                    if (matches != null && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }
                
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                
                // Trigger download
                document.body.appendChild(a);
                a.click();
                
                // Cleanup
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                // Reset button
                button.prop('disabled', false).html(originalHTML);
            },
            error: function(xhr, status, error) {
                console.error('Export error:', error);
                alert('Failed to export report. Please try again.');
                button.prop('disabled', false).html(originalHTML);
            }
        });
    });
});