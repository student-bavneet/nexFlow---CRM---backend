/**
 * NexFlow CRM Dashboard Script
 * Initializes Chart.js analytics chart with real database data,
 * interactive Monthly & Quarterly view switching,
 * localStorage persistence (NexFlow.dashboard.salesAnalytics.period.v1),
 * accessibility (aria-pressed), and global refresh mechanism.
 */

document.addEventListener("DOMContentLoaded", function () {
    const STORAGE_KEY = "NexFlow.dashboard.salesAnalytics.period.v1";
    const ctx = document.getElementById('salesAnalyticsChart');
    if (!ctx) return;

    let currencySymbol = window.__DASHBOARD_CURRENCY_SYMBOL__ || '$';
    let analyticsData = window.__DASHBOARD_INITIAL_ANALYTICS__ || {
        monthly: { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'], pipeline: [0, 0, 0, 0, 0, 0, 0, 0], won: [0, 0, 0, 0, 0, 0, 0, 0] },
        quarterly: { labels: ['Q1', 'Q2', 'Q3', 'Q4'], pipeline: [0, 0, 0, 0], won: [0, 0, 0, 0] }
    };

    let salesChart = null;

    // Helper: Read stored period safely
    function getStoredPeriod() {
        try {
            const val = localStorage.getItem(STORAGE_KEY);
            if (val === 'quarterly') return 'quarterly';
        } catch (e) {}
        return 'monthly';
    }

    // Helper: Save period safely
    function savePeriod(period) {
        try {
            localStorage.setItem(STORAGE_KEY, period);
        } catch (e) {}
    }

    // Initialize Chart.js Instance
    function initChart() {
        const initialPeriod = getStoredPeriod();
        const activeData = (initialPeriod === 'quarterly') ? analyticsData.quarterly : analyticsData.monthly;

        salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: activeData.labels,
                datasets: [
                    {
                        label: 'Pipeline Value (' + currencySymbol + 'K)',
                        data: activeData.pipeline,
                        borderColor: '#2563EB',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#2563EB',
                        pointRadius: 4
                    },
                    {
                        label: 'Closed Won (' + currencySymbol + 'K)',
                        data: activeData.won,
                        borderColor: '#12B76A',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [4, 4],
                        tension: 0.35,
                        pointBackgroundColor: '#12B76A',
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function (e, activeElements) {
                    if (activeElements && activeElements.length > 0) {
                        window.location.href = 'pipeline.php';
                    }
                },
                onHover: function (e, activeElements) {
                    if (e.native && e.native.target) {
                        e.native.target.style.cursor = (activeElements && activeElements.length > 0) ? 'pointer' : 'default';
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: { family: 'Inter', size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#101828',
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Inter', size: 13, weight: '600' },
                        bodyFont: { family: 'Inter', size: 12 },
                        callbacks: {
                            title: function (tooltipItems) {
                                return tooltipItems[0].label;
                            },
                            label: function (tooltipItem) {
                                return tooltipItem.dataset.label + ': ' + currencySymbol + tooltipItem.formattedValue + 'K';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 }, color: '#667085' }
                    },
                    y: {
                        grid: { color: '#F2F4F7' },
                        ticks: {
                            font: { family: 'Inter', size: 11 },
                            color: '#667085',
                            callback: function (val) { return currencySymbol + val + 'K'; }
                        }
                    }
                }
            }
        });
    }

    // Update Period View (Monthly vs Quarterly)
    function updateAnalyticsPeriod(period) {
        const isQuarterly = period === 'quarterly';
        const targetPeriod = isQuarterly ? 'quarterly' : 'monthly';

        const btnMonthly = document.getElementById('btnAnalyticsMonthly');
        const btnQuarterly = document.getElementById('btnAnalyticsQuarterly');
        const subtitleEl = document.getElementById('salesAnalyticsSubtitle');

        // Update button states & accessibility attributes
        if (btnMonthly && btnQuarterly) {
            if (isQuarterly) {
                btnMonthly.className = 'btn btn-ghost btn-xs';
                btnMonthly.setAttribute('aria-pressed', 'false');

                btnQuarterly.className = 'btn btn-secondary btn-xs';
                btnQuarterly.setAttribute('aria-pressed', 'true');
            } else {
                btnMonthly.className = 'btn btn-secondary btn-xs';
                btnMonthly.setAttribute('aria-pressed', 'true');

                btnQuarterly.className = 'btn btn-ghost btn-xs';
                btnQuarterly.setAttribute('aria-pressed', 'false');
            }
        }

        // Update Subtitle
        if (subtitleEl) {
            subtitleEl.textContent = isQuarterly
                ? 'Lead conversion and quarterly deal pipeline performance'
                : 'Lead conversion and monthly deal pipeline performance';
        }

        // Update Chart Data & Labels
        if (salesChart && salesChart.data) {
            const activeData = isQuarterly ? analyticsData.quarterly : analyticsData.monthly;
            salesChart.data.labels = activeData.labels;
            salesChart.data.datasets[0].label = 'Pipeline Value (' + currencySymbol + 'K)';
            salesChart.data.datasets[0].data = activeData.pipeline;
            salesChart.data.datasets[1].label = 'Closed Won (' + currencySymbol + 'K)';
            salesChart.data.datasets[1].data = activeData.won;
            salesChart.update();
        }

        savePeriod(targetPeriod);
    }

    // Attach click listeners to period buttons
    const btnMonthly = document.getElementById('btnAnalyticsMonthly');
    const btnQuarterly = document.getElementById('btnAnalyticsQuarterly');

    if (btnMonthly) {
        btnMonthly.addEventListener('click', function () {
            updateAnalyticsPeriod('monthly');
        });
    }

    if (btnQuarterly) {
        btnQuarterly.addEventListener('click', function () {
            updateAnalyticsPeriod('quarterly');
        });
    }

    // Initialize chart
    initChart();
    updateAnalyticsPeriod(getStoredPeriod());

    // Expose global refresh function to sync dashboard via AJAX
    window.refreshDashboard = function () {
        fetch('api/dashboard.php?action=bootstrap')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    if (data.currency_symbol) {
                        currencySymbol = data.currency_symbol;
                    }
                    if (data.analytics) {
                        analyticsData = data.analytics;
                        updateAnalyticsPeriod(getStoredPeriod());
                    }
                }
            })
            .catch(function (err) {
                console.error('Failed to refresh dashboard data:', err);
            });
    };
});
