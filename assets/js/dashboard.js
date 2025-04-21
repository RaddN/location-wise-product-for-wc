/**
 * Location Wise Products Dashboard JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initDashboardCharts();
    });
    
    /**
     * Initialize all dashboard charts
     */
    function initDashboardCharts() {
        // Get data from the localized script
        const data = window.lwpDashboardData;
        
        if (!data) {
            console.error('Dashboard data not available');
            return;
        }
        
        initProductsChart();
        initStockChart();
        initNewProductsChart();
        initOrdersChart();
        initRevenueChart();
    }
    
    /**
     * Initialize Products by Location Chart
     */
    function initProductsChart() {
        const ctx = document.getElementById('locationProductsChart');
        
        if (!ctx) return;
        
        const labels = Object.keys(lwpDashboardData.productCounts);
        const values = Object.values(lwpDashboardData.productCounts);
        const bgColors = labels.map(label => lwpDashboardData.locationColors[label]);
        const borderColors = labels.map(label => lwpDashboardData.locationBorderColors[label]);
        
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
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
                    }
                }
            }
        });
    }
    
    /**
     * Initialize Stock Levels by Location Chart
     */
    function initStockChart() {
        const ctx = document.getElementById('locationStockChart');
        
        if (!ctx) return;
        
        const labels = Object.keys(lwpDashboardData.stockLevels);
        const values = Object.values(lwpDashboardData.stockLevels);
        const bgColors = labels.map(label => lwpDashboardData.locationColors[label]);
        const borderColors = labels.map(label => lwpDashboardData.locationBorderColors[label]);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,

                datasets: [{
                    label: lwpDashboardData.i18n.totalStock,
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                fill: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6
                    }
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
                labels: lwpDashboardData.dateLabels,
                datasets: [{
                    label: lwpDashboardData.i18n.newProducts,
                    data: lwpDashboardData.dateCounts,
                    fill: {
                        target: 'origin',
                        above: 'rgba(78, 84, 200, 0.1)'
                    },
                    borderColor: '#4e54c8',
                    tension: 0.4,
                    pointBackgroundColor: '#4e54c8',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6
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
        
        const labels = Object.keys(lwpDashboardData.ordersByLocation);
        const values = Object.values(lwpDashboardData.ordersByLocation);
        const bgColors = labels.map(label => lwpDashboardData.locationColors[label] || 'rgba(153, 102, 255, 0.7)');
        const borderColors = labels.map(label => lwpDashboardData.locationBorderColors[label] || 'rgba(153, 102, 255, 1)');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: 1,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} ${lwpDashboardData.i18n.orders} (${percentage}%)`;
                            }
                        },
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6
                    }
                }
            }
        });
    }
    
    /**
     * Initialize Revenue by Location Chart
     */
    function initRevenueChart() {
        const ctx = document.getElementById('revenueByLocationChart');
        
        if (!ctx) return;
        
        const labels = Object.keys(lwpDashboardData.revenueByLocation);
        const values = Object.values(lwpDashboardData.revenueByLocation);
        const bgColors = labels.map(label => lwpDashboardData.locationColors[label] || 'rgba(75, 192, 192, 0.7)');
        const borderColors = labels.map(label => lwpDashboardData.locationBorderColors[label] || 'rgba(75, 192, 192, 1)');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: lwpDashboardData.i18n.revenue,
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            // Format as currency - assumes USD, update as needed
                            callback: function(value) {
                                return new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return lwpDashboardData.i18n.revenue + ': ' + 
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD'
                                    }).format(context.raw);
                            }
                        },
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6
                    }
                }
            }
        });
    }
    
})(jQuery);