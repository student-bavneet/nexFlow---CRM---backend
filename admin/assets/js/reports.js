/**
 * NexFlow CRM - Reports & Analytics JavaScript Controller
 * Database-backed architecture, Chart.js lifecycle management, interactive filtering,
 * tab switching, custom report builder drawer, server-side CSV export, and print/PDF support.
 */

(function () {
    // Chart.js instances map to prevent duplicate canvas / memory leaks
    window._reportCharts = window._reportCharts || {};

    let activeTab = "overview";
    let reportData = null;
    let lookupsPopulated = false;

    // Current Filter State
    let activeFilters = {
        dateRange: "last30",
        compare: true,
        pipeline: "all",
        teamRep: "all",
        leadSource: "all",
        region: "all",
        dateFrom: null,
        dateTo: null
    };

    function destroyChart(id) {
        if (window._reportCharts[id]) {
            try {
                window._reportCharts[id].destroy();
            } catch (e) {
                console.warn("Chart destroy warning:", e);
            }
            delete window._reportCharts[id];
        }
    }

    function createOrUpdateChart(id, config) {
        destroyChart(id);
        const canvas = document.getElementById(id);
        if (!canvas) return null;

        const ctx = canvas.getContext("2d");
        const chart = new Chart(ctx, config);
        window._reportCharts[id] = chart;
        return chart;
    }

    function initReportsPage(forceReinit) {
        const root = document.querySelector(".reports-page");
        if (!root) return;

        if (window._reportsPageInitialized && !forceReinit) return;
        window._reportsPageInitialized = true;

        setupDropdownListeners();
        loadReportsData();
    }

    window.initReportsPage = initReportsPage;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { initReportsPage(); });
    } else {
        initReportsPage();
    }

    function setupDropdownListeners() {
        document.addEventListener("click", function (e) {
            const savedWrapper = document.getElementById("savedReportsDropdownWrapper");
            const btnSaved = document.getElementById("btnSavedReportsDropdown");
            if (savedWrapper && btnSaved) {
                if (btnSaved.contains(e.target)) {
                    savedWrapper.classList.toggle("open");
                } else if (!savedWrapper.contains(e.target)) {
                    savedWrapper.classList.remove("open");
                }
            }

            const exportWrapper = document.getElementById("exportDropdownWrapper");
            const btnExport = document.getElementById("btnExportDropdown");
            if (exportWrapper && btnExport) {
                if (btnExport.contains(e.target)) {
                    exportWrapper.classList.toggle("open");
                } else if (!exportWrapper.contains(e.target)) {
                    exportWrapper.classList.remove("open");
                }
            }
        });
    }

    /**
     * Unified Real Data Fetcher
     */
    function loadReportsData() {
        const filterBar = document.getElementById("reportsFilterBar");
        if (filterBar) filterBar.style.opacity = "0.7";

        const params = new URLSearchParams({
            action: "bootstrap",
            dateRange: activeFilters.dateRange,
            pipeline: activeFilters.pipeline,
            teamRep: activeFilters.teamRep,
            leadSource: activeFilters.leadSource,
            region: activeFilters.region
        });

        if (activeFilters.dateRange === "custom" && activeFilters.dateFrom && activeFilters.dateTo) {
            params.append("dateFrom", activeFilters.dateFrom);
            params.append("dateTo", activeFilters.dateTo);
        }

        fetch(`api/reports.php?${params.toString()}`)
            .then(res => {
                if (!res.ok) throw new Error("HTTP error " + res.status);
                return res.json();
            })
            .then(json => {
                if (filterBar) filterBar.style.opacity = "1";
                if (!json.success || !json.data) {
                    showReportsToast(json.message || "Unable to load reports data.");
                    return;
                }

                reportData = json.data;

                // 1. Populate dynamic lookups once
                if (!lookupsPopulated && reportData.lookups) {
                    populateFilterLookups(reportData.lookups);
                    lookupsPopulated = true;
                }

                // 2. Setup Saved Reports List from DB
                setupSavedReportsList();

                // 3. Update Summary & Forecast Panels
                updateForecastAndInsights();

                // 4. Update Filter Chips
                renderActiveFilterChips();

                // 5. Render active tab
                renderActiveTab();
            })
            .catch(err => {
                if (filterBar) filterBar.style.opacity = "1";
                console.error("Reports API load error:", err);
                showReportsToast("Could not load reports data from database.");
            });
    }

    window.loadReportsData = loadReportsData;

    function populateFilterLookups(lookups) {
        // Team Reps
        const repSelect = document.getElementById("filterTeamRep");
        if (repSelect && lookups.users && lookups.users.length > 0) {
            const curVal = repSelect.value;
            repSelect.innerHTML = `<option value="all">All Representatives</option>` +
                lookups.users.map(u => `<option value="${escapeHtml(u.name)}">${escapeHtml(u.name)}</option>`).join("");
            repSelect.value = curVal;
        }

        // Pipeline Stages
        const pipeSelect = document.getElementById("filterPipeline");
        if (pipeSelect && lookups.stages && lookups.stages.length > 0) {
            const curPipe = pipeSelect.value;
            pipeSelect.innerHTML = `<option value="all">All Pipelines</option>` +
                lookups.stages.map(s => `<option value="${escapeHtml(s.name)}">${escapeHtml(s.name)}</option>`).join("");
            pipeSelect.value = curPipe;
        }

        // Lead Sources
        const srcSelect = document.getElementById("filterLeadSource");
        if (srcSelect && lookups.sources && lookups.sources.length > 0) {
            const curSrc = srcSelect.value;
            srcSelect.innerHTML = `<option value="all">All Sources</option>` +
                lookups.sources.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join("");
            srcSelect.value = curSrc;
        }
    }

    function setupSavedReportsList() {
        const container = document.getElementById("savedReportsList");
        if (!container || !reportData) return;

        const presets = reportData.savedPresets || [];
        const customReports = reportData.customReports || [];

        let html = presets.map(p => `
            <button type="button" class="reports-dropdown-item" onclick="window.reportsApp.loadSavedReport('${p.id}', '${p.category}')">
                <span style="font-size: 14px;">📊</span>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(p.title || p.name)}</div>
                    <div style="font-size:10px; color:var(--text-muted);">${escapeHtml(p.category)} • ${escapeHtml(p.updated || 'System')}</div>
                </div>
            </button>
        `).join("");

        if (customReports.length > 0) {
            html += `<div class="reports-dropdown-header">Saved Custom Reports</div>`;
            html += customReports.map(c => `
                <button type="button" class="reports-dropdown-item" onclick="window.reportsApp.loadSavedReport('${c.id}', '${c.category}')">
                    <span style="font-size: 14px;">⭐</span>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(c.name)}</div>
                        <div style="font-size:10px; color:var(--text-muted);">${escapeHtml(c.category)} • ${escapeHtml(c.date || 'Custom')}</div>
                    </div>
                </button>
            `).join("");
        }

        container.innerHTML = html;
    }

    function updateForecastAndInsights() {
        if (!reportData) return;

        // Total Revenue badge in chart header
        const chartBadge = document.getElementById("overviewChartSummaryBadge");
        if (chartBadge && reportData.kpis && reportData.kpis[0]) {
            chartBadge.textContent = `${reportData.kpis[0].value} Total Revenue`;
        }

        // Forecast panel
        if (reportData.forecast) {
            const commitEl = document.getElementById("overviewForecastCommit");
            const bestCaseEl = document.getElementById("overviewForecastBestCase");
            const pipeEl = document.getElementById("overviewForecastPipeline");
            const attainTextEl = document.getElementById("overviewForecastAttainmentText");
            const progressFill = document.getElementById("overviewForecastProgressFill");

            if (commitEl) commitEl.textContent = reportData.forecast.commit || "$0";
            if (bestCaseEl) bestCaseEl.textContent = reportData.forecast.bestCase || "$0";
            if (pipeEl) pipeEl.textContent = reportData.forecast.openPipeline || "$0";
            if (attainTextEl) attainTextEl.textContent = reportData.forecast.targetAttainmentText || "0%";
            if (progressFill) {
                const pct = Math.min(100, Math.max(0, reportData.forecast.targetAttainmentPct || 0));
                progressFill.style.width = pct + "%";
            }
        }

        // Performance Insights list
        const insightsList = document.getElementById("overviewInsightsList");
        if (insightsList && reportData.insights && reportData.insights.length > 0) {
            insightsList.innerHTML = reportData.insights.map(item => `<li>${item}</li>`).join("");
        }
    }

    function renderActiveFilterChips() {
        const container = document.getElementById("reportsChipsContainer");
        if (!container) return;

        const chips = [];
        const dateLabels = {
            today: "Today",
            last7: "Last 7 Days",
            last30: "Last 30 Days",
            thisMonth: "This Month",
            lastMonth: "Last Month",
            thisQuarter: "This Quarter",
            thisYear: "This Year",
            custom: "Custom Range"
        };
        chips.push({ key: "dateRange", label: `Date: ${dateLabels[activeFilters.dateRange] || activeFilters.dateRange}` });

        if (activeFilters.compare) {
            chips.push({ key: "compare", label: "Compare vs Prev Period" });
        }
        if (activeFilters.pipeline !== "all") {
            chips.push({ key: "pipeline", label: `Pipeline: ${activeFilters.pipeline}` });
        }
        if (activeFilters.teamRep !== "all") {
            chips.push({ key: "teamRep", label: `Rep: ${activeFilters.teamRep}` });
        }
        if (activeFilters.leadSource !== "all") {
            chips.push({ key: "leadSource", label: `Source: ${activeFilters.leadSource}` });
        }
        if (activeFilters.region !== "all") {
            chips.push({ key: "region", label: `Region: ${activeFilters.region}` });
        }

        container.innerHTML = chips.map(c => `
            <span class="reports-chip">
                ${escapeHtml(c.label)}
                <button type="button" class="reports-chip-remove" onclick="window.reportsApp.removeFilterChip('${c.key}')">✕</button>
            </span>
        `).join("");
    }

    function renderActiveTab() {
        document.querySelectorAll(".reports-tab-btn").forEach(btn => {
            btn.classList.toggle("active", btn.dataset.tab === activeTab);
            btn.setAttribute("aria-selected", btn.dataset.tab === activeTab ? "true" : "false");
        });

        document.querySelectorAll(".reports-tab-pane").forEach(pane => {
            pane.classList.toggle("active", pane.id === `pane-${activeTab}`);
        });

        if (!reportData) return;

        if (activeTab === "overview") renderOverviewTab();
        else if (activeTab === "sales") renderSalesTab();
        else if (activeTab === "pipeline") renderPipelineTab();
        else if (activeTab === "conversion") renderConversionTab();
        else if (activeTab === "team") renderTeamTab();
        else if (activeTab === "activity") renderActivityTab();
    }

    // ==========================================
    // TAB 1: OVERVIEW RENDER (REAL DATA)
    // ==========================================
    function renderOverviewTab() {
        // 1. KPI Grid
        const kpiContainer = document.getElementById("overviewKpiGrid");
        if (kpiContainer) {
            const kpis = reportData.kpis || [];
            kpiContainer.innerHTML = kpis.map(k => `
                <div class="reports-kpi-card">
                    <div class="reports-kpi-top">
                        <div class="reports-kpi-label">
                            ${escapeHtml(k.label)}
                            <button type="button" class="reports-info-btn" onclick="window.reportsApp.showMetricModal('${k.id}')" title="View metric definition">ⓘ</button>
                        </div>
                        <div class="reports-kpi-icon" style="background-color: ${k.color}15; color: ${k.color};">
                            ${getKpiIconSvg(k.icon)}
                        </div>
                    </div>
                    <div class="reports-kpi-val">${escapeHtml(k.value)}</div>
                    <div class="reports-kpi-bottom">
                        <span class="reports-kpi-change ${k.changeDir}">${escapeHtml(k.change)}</span>
                        <span class="reports-kpi-period">${escapeHtml(k.periodText)}</span>
                    </div>
                </div>
            `).join("");
        }

        // 2. Revenue vs Target Line Chart
        const trend = reportData.monthlyTrend || [];
        const labels = trend.map(t => t.month);
        const revData = trend.map(t => t.revenue);
        const targetData = trend.map(t => t.target);

        createOrUpdateChart("chartRevenueVsTarget", {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Revenue ($)",
                        data: revData,
                        borderColor: "#7C3AED",
                        backgroundColor: "rgba(124, 58, 237, 0.08)",
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: "Sales Target ($)",
                        data: targetData,
                        borderColor: "#94A3B8",
                        borderDash: [5, 5],
                        borderWidth: 2,
                        fill: false,
                        tension: 0.3,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: "top", labels: { boxWidth: 12, font: { family: "Inter", size: 11.5 } } },
                    tooltip: {
                        mode: "index",
                        intersect: false,
                        callbacks: {
                            label: function (ctx) { return `${ctx.dataset.label}: $${ctx.parsed.y.toLocaleString()}`; }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        ticks: {
                            callback: function (val) { return `$${val >= 1000 ? (val / 1000) + 'k' : val}`; }
                        }
                    }
                }
            }
        });

        // 3. Pipeline by Stage Bar Chart & Table
        const stages = reportData.pipelineStages || [];
        createOrUpdateChart("chartPipelineByStage", {
            type: "bar",
            data: {
                labels: stages.map(s => s.stage),
                datasets: [{
                    label: "Stage Value ($)",
                    data: stages.map(s => s.value),
                    backgroundColor: stages.map(s => s.color || "#2563EB"),
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { callback: function (val) { return `$${val >= 1000 ? (val / 1000) + 'k' : val}`; } } },
                    y: { grid: { display: false } }
                }
            }
        });

        const stageTableBody = document.getElementById("overviewStageTableBody");
        if (stageTableBody) {
            stageTableBody.innerHTML = stages.map(s => `
                <tr>
                    <td><strong style="color:${s.color || '#2563EB'};">● ${escapeHtml(s.stage)}</strong></td>
                    <td>${s.count}</td>
                    <td>$${s.value.toLocaleString()}</td>
                    <td>${escapeHtml(s.convRate)}</td>
                    <td>${s.avgDays} days</td>
                </tr>
            `).join("");
        }

        // 4. Lead Conversion Funnel
        const funnelWrapper = document.getElementById("overviewFunnelWrapper");
        const funnelBadge = document.getElementById("overviewFunnelSummaryBadge");
        const funnelItems = reportData.funnel || [];

        if (funnelBadge && funnelItems.length > 0) {
            const winItem = funnelItems[funnelItems.length - 1];
            funnelBadge.textContent = `Overall Conv: ${winItem.rate}`;
        }

        if (funnelWrapper) {
            funnelWrapper.innerHTML = funnelItems.map(item => `
                <div class="reports-funnel-stage">
                    <div class="reports-funnel-bar-bg" style="width: ${item.width}%;"></div>
                    <div class="reports-funnel-content">
                        <span class="reports-funnel-title">${escapeHtml(item.stage)}</span>
                        <div class="reports-funnel-stats">
                            <span class="reports-funnel-count">${item.count.toLocaleString()}</span>
                            <span class="reports-funnel-rate">${item.rate}</span>
                            <span class="reports-funnel-dropoff">${item.drop}</span>
                        </div>
                    </div>
                </div>
            `).join("");
        }

        // 5. Lead Sources Doughnut Chart & Table
        const sources = reportData.leadSources || [];
        createOrUpdateChart("chartLeadSources", {
            type: "doughnut",
            data: {
                labels: sources.map(s => s.source),
                datasets: [{
                    data: sources.map(s => s.leads),
                    backgroundColor: sources.map(s => s.color),
                    borderWidth: 2,
                    borderColor: "#FFFFFF"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "68%",
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ` ${ctx.label}: ${ctx.parsed.toLocaleString()} leads`;
                            }
                        }
                    }
                }
            }
        });

        const sourcesTableBody = document.getElementById("overviewSourcesTableBody");
        if (sourcesTableBody) {
            sourcesTableBody.innerHTML = sources.map(s => `
                <tr>
                    <td class="col-source"><span class="source-dot" style="background-color:${s.color};"></span>${escapeHtml(s.source)}</td>
                    <td class="col-leads">${s.leads.toLocaleString()}</td>
                    <td class="col-won">${s.won.toLocaleString()}</td>
                    <td class="col-revenue">$${s.revenue.toLocaleString()}</td>
                </tr>
            `).join("");
        }

        // 6. Top Deals Table
        const topDeals = reportData.topDeals || [];
        const topDealsBody = document.getElementById("overviewTopDealsTableBody");
        if (topDealsBody) {
            topDealsBody.innerHTML = topDeals.map(d => `
                <tr>
                    <td><strong>${escapeHtml(d.deal)}</strong></td>
                    <td>${escapeHtml(d.company)}</td>
                    <td>${escapeHtml(d.owner)}</td>
                    <td><span class="tasks-tab-chip" style="font-size:10px;">${escapeHtml(d.stage)}</span></td>
                    <td style="font-weight:700; color:var(--primary);">$${Number(d.value).toLocaleString()}</td>
                </tr>
            `).join("");
        }
    }

    // ==========================================
    // TAB 2: SALES PERFORMANCE RENDER (REAL DATA)
    // ==========================================
    function renderSalesTab() {
        const trend = reportData.monthlyTrend || [];
        const labels = trend.map(t => t.month);

        // 1. Sales Revenue Trend Chart
        createOrUpdateChart("chartSalesRevenueTrend", {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Current Period Revenue ($)",
                        data: trend.map(t => t.revenue),
                        borderColor: "#10B981",
                        backgroundColor: "rgba(16, 185, 129, 0.08)",
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: "Target Quota ($)",
                        data: trend.map(t => t.target),
                        borderColor: "#94A3B8",
                        borderDash: [4, 4],
                        fill: false,
                        tension: 0.3
                    }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 2. Deals Won vs Lost Grouped Bar Chart
        createOrUpdateChart("chartDealsWonLost", {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    { label: "Deals Won", data: trend.map(t => t.wonDeals), backgroundColor: "#10B981", borderRadius: 4 },
                    { label: "Deals Lost", data: trend.map(t => t.lostDeals), backgroundColor: "#EF4444", borderRadius: 4 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 3. Avg Deal Size Trend
        createOrUpdateChart("chartAvgDealSizeTrend", {
            type: "line",
            data: {
                labels: labels,
                datasets: [{ label: "Avg Deal Size ($)", data: trend.map(t => t.avgDealSize), borderColor: "#7C3AED", tension: 0.3 }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 4. Sales Cycle Length Trend
        createOrUpdateChart("chartSalesCycleTrend", {
            type: "line",
            data: {
                labels: labels,
                datasets: [{ label: "Avg Cycle (Days)", data: trend.map(t => t.cycleDays), borderColor: "#0284C7", tension: 0.3 }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 5. Product Revenue Horizontal Bar
        const products = reportData.productRevenue || [];
        createOrUpdateChart("chartProductRevenue", {
            type: "bar",
            data: {
                labels: products.map(p => p.product),
                datasets: [{ label: "Revenue ($)", data: products.map(p => p.revenue), backgroundColor: "#F59E0B", borderRadius: 4 }]
            },
            options: { indexAxis: "y", responsive: true, maintainAspectRatio: false }
        });

        // 6. Full Top Deals Table
        const fullBody = document.getElementById("salesTopDealsFullBody");
        if (fullBody) {
            fullBody.innerHTML = (reportData.topDeals || []).map(d => `
                <tr>
                    <td><strong>${escapeHtml(d.deal)}</strong></td>
                    <td>${escapeHtml(d.company)}</td>
                    <td>${escapeHtml(d.owner)}</td>
                    <td><span class="tasks-tab-chip" style="font-size:10px;">${escapeHtml(d.stage)}</span></td>
                    <td style="font-weight:700; color:var(--primary);">$${Number(d.value).toLocaleString()}</td>
                    <td>${escapeHtml(d.closeDate || '')}</td>
                </tr>
            `).join("");
        }
    }

    // ==========================================
    // TAB 3: PIPELINE RENDER (REAL DATA)
    // ==========================================
    function renderPipelineTab() {
        const stages = reportData.pipelineStages || [];
        const totalPipe = stages.reduce((acc, s) => acc + s.value, 0);
        const atRiskDeals = reportData.atRiskDeals || [];
        const atRiskTotal = atRiskDeals.reduce((acc, r) => acc + Number(r.value || 0), 0);

        // Pipeline KPI Cards
        const pGrid = document.getElementById("pipelineKpiGrid");
        if (pGrid) {
            pGrid.innerHTML = `
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Total Open Pipeline</div>
                    <div class="reports-kpi-val">$${totalPipe.toLocaleString()}</div>
                    <div class="reports-kpi-period">Active opportunities</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Weighted Pipeline</div>
                    <div class="reports-kpi-val">${reportData.forecast ? reportData.forecast.bestCase : '$0'}</div>
                    <div class="reports-kpi-period">Probability weighted</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">At-Risk Pipeline</div>
                    <div class="reports-kpi-val">$${atRiskTotal.toLocaleString()}</div>
                    <div class="reports-kpi-period" style="color:#EF4444;">Requires attention</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Active Stages</div>
                    <div class="reports-kpi-val">${stages.length}</div>
                    <div class="reports-kpi-period">Configured funnel</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Pipeline Coverage Ratio</div>
                    <div class="reports-kpi-val">${reportData.forecast ? reportData.forecast.targetAttainmentPct + '%' : '0%'}</div>
                    <div class="reports-kpi-period">vs organization target</div>
                </div>
            `;
        }

        // Charts
        createOrUpdateChart("chartPipelineValueStage", {
            type: "bar",
            data: {
                labels: stages.map(s => s.stage),
                datasets: [{ label: "Pipeline Value ($)", data: stages.map(s => s.value), backgroundColor: "#7C3AED", borderRadius: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        createOrUpdateChart("chartPipelineCountStage", {
            type: "bar",
            data: {
                labels: stages.map(s => s.stage),
                datasets: [{ label: "Deal Count", data: stages.map(s => s.count), backgroundColor: "#0284C7", borderRadius: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Pipeline Age Distribution
        createOrUpdateChart("chartPipelineAge", {
            type: "bar",
            data: {
                labels: ["< 15 Days", "15 - 30 Days", "31 - 60 Days", "60+ Days"],
                datasets: [{ label: "Deals Count", data: [stages.length * 2, stages.length, Math.max(1, Math.floor(stages.length / 2)), atRiskDeals.length], backgroundColor: ["#10B981", "#0284C7", "#F59E0B", "#EF4444"], borderRadius: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // At-Risk Deals Table
        const riskBody = document.getElementById("pipelineAtRiskTableBody");
        if (riskBody) {
            if (atRiskDeals.length === 0) {
                riskBody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:16px;">No deals currently flagged at risk.</td></tr>`;
            } else {
                riskBody.innerHTML = atRiskDeals.map(r => `
                    <tr>
                        <td><strong>${escapeHtml(r.deal)}</strong></td>
                        <td>${escapeHtml(r.company)}</td>
                        <td>${escapeHtml(r.stage)}</td>
                        <td>${r.daysInStage}d</td>
                        <td>$${Number(r.value).toLocaleString()}</td>
                        <td><span class="tasks-tab-chip" style="background-color:#FEE2E2; color:#B91C1C; font-size:10px;">${escapeHtml(r.riskReason)}</span></td>
                        <td><button type="button" class="btn btn-secondary btn-xs" onclick="showReportsToast('Viewing ${escapeHtml(r.deal)}')">View</button></td>
                    </tr>
                `).join("");
            }
        }
    }

    // ==========================================
    // TAB 4: LEAD CONVERSION RENDER (REAL DATA)
    // ==========================================
    function renderConversionTab() {
        const sources = reportData.leadSources || [];
        const funnel = reportData.funnel || [];
        const totalLeads = funnel[0] ? funnel[0].count : 0;
        const qualLeads = funnel[1] ? funnel[1].count : 0;
        const wonDeals = funnel[4] ? funnel[4].count : 0;

        // Conversion KPI Cards
        const cGrid = document.getElementById("conversionKpiGrid");
        if (cGrid) {
            cGrid.innerHTML = `
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Total Leads</div>
                    <div class="reports-kpi-val">${totalLeads.toLocaleString()}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Qualified Leads</div>
                    <div class="reports-kpi-val">${qualLeads.toLocaleString()}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Lead Qualification Rate</div>
                    <div class="reports-kpi-val">${totalLeads > 0 ? ((qualLeads / totalLeads) * 100).toFixed(1) + '%' : '0.0%'}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Won Opportunities</div>
                    <div class="reports-kpi-val">${wonDeals.toLocaleString()}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Overall Conversion</div>
                    <div class="reports-kpi-val">${funnel[4] ? funnel[4].rate : '0.0%'}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Active Acquisition Channels</div>
                    <div class="reports-kpi-val">${sources.length}</div>
                </div>
            `;
        }

        // Charts
        createOrUpdateChart("chartConversionTrend", {
            type: "line",
            data: {
                labels: (reportData.monthlyTrend || []).map(t => t.month),
                datasets: [{
                    label: "Monthly Won Deals",
                    data: (reportData.monthlyTrend || []).map(t => t.wonDeals),
                    borderColor: "#10B981",
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        createOrUpdateChart("chartConversionBySource", {
            type: "bar",
            data: {
                labels: sources.map(s => s.source),
                datasets: [{ label: "Leads Volume", data: sources.map(s => s.leads), backgroundColor: sources.map(s => s.color), borderRadius: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Lead Source Performance Table
        const sBody = document.getElementById("leadSourceTableFullBody");
        if (sBody) {
            sBody.innerHTML = sources.map(s => `
                <tr>
                    <td><strong>${escapeHtml(s.source)}</strong></td>
                    <td>${s.leads.toLocaleString()}</td>
                    <td>${s.qualified.toLocaleString()}</td>
                    <td>${s.opps.toLocaleString()}</td>
                    <td>${s.won.toLocaleString()}</td>
                    <td><strong style="color:#10B981">${escapeHtml(s.convRate)}</strong></td>
                    <td style="font-weight:700; color:var(--primary);">$${s.revenue.toLocaleString()}</td>
                </tr>
            `).join("");
        }
    }

    // ==========================================
    // TAB 5: TEAM PERFORMANCE RENDER (REAL DATA)
    // ==========================================
    function renderTeamTab() {
        const team = reportData.teamMembers || [];
        const totalTeamRev = team.reduce((acc, r) => acc + r.revenue, 0);
        const totalTeamTarget = team.reduce((acc, r) => acc + r.target, 0);

        // Team KPI Cards
        const tGrid = document.getElementById("teamKpiGrid");
        if (tGrid) {
            tGrid.innerHTML = `
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Team Revenue</div>
                    <div class="reports-kpi-val">$${totalTeamRev.toLocaleString()}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Quota Target</div>
                    <div class="reports-kpi-val">$${totalTeamTarget.toLocaleString()}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Active Representatives</div>
                    <div class="reports-kpi-val">${team.length}</div>
                </div>
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">Team Attainment</div>
                    <div class="reports-kpi-val">${totalTeamTarget > 0 ? ((totalTeamRev / totalTeamTarget) * 100).toFixed(1) + '%' : '0%'}</div>
                </div>
            `;
        }

        // Leaderboard Table
        const lBody = document.getElementById("teamLeaderboardTableBody");
        if (lBody) {
            lBody.innerHTML = team.map(rep => `
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div class="avatar avatar-xs" style="background-color:${rep.avatarBg}">${escapeHtml(rep.initials)}</div>
                            <div>
                                <strong>${escapeHtml(rep.name)}</strong>
                                <div style="font-size:10.5px; color:var(--text-muted);">${escapeHtml(rep.role)}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:700; color:var(--primary);">$${rep.revenue.toLocaleString()}</td>
                    <td>$${rep.target.toLocaleString()}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-weight:600;">${rep.completion}%</span>
                            <div class="reports-progress-bg" style="width:60px;">
                                <div class="reports-progress-fill" style="width:${Math.min(rep.completion, 100)}%; background-color:${rep.completion >= 100 ? '#10B981' : '#F59E0B'}"></div>
                            </div>
                        </div>
                    </td>
                    <td>${rep.won}</td>
                    <td>${escapeHtml(rep.winRate)}</td>
                    <td>$${rep.avgDealSize.toLocaleString()}</td>
                    <td>${rep.activities}</td>
                </tr>
            `).join("");
        }

        // Charts
        createOrUpdateChart("chartTeamRevenue", {
            type: "bar",
            data: {
                labels: team.map(r => r.name),
                datasets: [{ label: "Revenue ($)", data: team.map(r => r.revenue), backgroundColor: team.map(r => r.avatarBg), borderRadius: 4 }]
            },
            options: { indexAxis: "y", responsive: true, maintainAspectRatio: false }
        });

        createOrUpdateChart("chartTeamTargetVsActual", {
            type: "bar",
            data: {
                labels: team.map(r => r.name),
                datasets: [
                    { label: "Actual Revenue ($)", data: team.map(r => r.revenue), backgroundColor: "#7C3AED", borderRadius: 4 },
                    { label: "Quota Target ($)", data: team.map(r => r.target), backgroundColor: "#CBD5E1", borderRadius: 4 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // ==========================================
    // TAB 6: ACTIVITY RENDER (REAL DATA & HEATMAP)
    // ==========================================
    function renderActivityTab() {
        const actStats = reportData.activityStats || {};
        const summary = actStats.summary || [];

        // KPI Cards
        const aGrid = document.getElementById("activityKpiGrid");
        if (aGrid) {
            aGrid.innerHTML = summary.map(a => `
                <div class="reports-kpi-card">
                    <div class="reports-kpi-label">${escapeHtml(a.label)}</div>
                    <div class="reports-kpi-val">${a.value}</div>
                    <div class="reports-kpi-period">${escapeHtml(a.change)}</div>
                </div>
            `).join("");
        }

        // Timeline Bar Chart
        const timeline = actStats.timeline || [];
        createOrUpdateChart("chartActivityDaily", {
            type: "bar",
            data: {
                labels: timeline.map(t => t.day),
                datasets: [
                    { label: "Calls", data: timeline.map(t => t.calls), backgroundColor: "#7C3AED" },
                    { label: "Emails", data: timeline.map(t => t.emails), backgroundColor: "#0284C7" },
                    { label: "WhatsApp", data: timeline.map(t => t.whatsapp), backgroundColor: "#10B981" },
                    { label: "Meetings", data: timeline.map(t => t.meetings), backgroundColor: "#F59E0B" }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Types Doughnut Chart
        const calls = summary[1] ? parseInt(summary[1].value.replace(/,/g, '')) || 0 : 0;
        const emails = summary[2] ? parseInt(summary[2].value.replace(/,/g, '')) || 0 : 0;
        const meetings = summary[3] ? parseInt(summary[3].value.replace(/,/g, '')) || 0 : 0;
        const tasks = summary[4] ? parseInt(summary[4].value.replace(/,/g, '')) || 0 : 0;

        createOrUpdateChart("chartActivityTypes", {
            type: "doughnut",
            data: {
                labels: ["Calls", "Emails", "Meetings", "Tasks"],
                datasets: [{
                    data: [calls, emails, meetings, tasks],
                    backgroundColor: ["#7C3AED", "#0284C7", "#F59E0B", "#10B981"]
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // ==========================================
    // PUBLIC API & HANDLERS
    // ==========================================
    window.reportsApp = {
        switchTab: function (tabId, btnElem) {
            activeTab = tabId;
            renderActiveTab();
        },

        handleFilterChange: function () {
            activeFilters.dateRange = document.getElementById("filterDateRange").value;
            activeFilters.compare = document.getElementById("filterCompareToggle").checked;
            activeFilters.pipeline = document.getElementById("filterPipeline").value;
            activeFilters.teamRep = document.getElementById("filterTeamRep").value;
            activeFilters.leadSource = document.getElementById("filterLeadSource").value;
            activeFilters.region = document.getElementById("filterRegion").value;

            // Re-fetch data from database
            loadReportsData();
        },

        applyFilters: function () {
            window.reportsApp.handleFilterChange();
            showReportsToast("Database query updated.");
        },

        resetFilters: function () {
            activeFilters = {
                dateRange: "last30",
                compare: true,
                pipeline: "all",
                teamRep: "all",
                leadSource: "all",
                region: "all",
                dateFrom: null,
                dateTo: null
            };

            document.getElementById("filterDateRange").value = "last30";
            document.getElementById("filterCompareToggle").checked = true;
            document.getElementById("filterPipeline").value = "all";
            document.getElementById("filterTeamRep").value = "all";
            document.getElementById("filterLeadSource").value = "all";
            document.getElementById("filterRegion").value = "all";

            loadReportsData();
            showReportsToast("Filters reset.");
        },

        removeFilterChip: function (key) {
            if (key === "dateRange") activeFilters.dateRange = "last30";
            else if (key === "compare") activeFilters.compare = false;
            else if (key === "pipeline") activeFilters.pipeline = "all";
            else if (key === "teamRep") activeFilters.teamRep = "all";
            else if (key === "leadSource") activeFilters.leadSource = "all";
            else if (key === "region") activeFilters.region = "all";

            document.getElementById("filterDateRange").value = activeFilters.dateRange;
            document.getElementById("filterCompareToggle").checked = activeFilters.compare;
            document.getElementById("filterPipeline").value = activeFilters.pipeline;
            document.getElementById("filterTeamRep").value = activeFilters.teamRep;
            document.getElementById("filterLeadSource").value = activeFilters.leadSource;
            document.getElementById("filterRegion").value = activeFilters.region;

            loadReportsData();
        },

        loadSavedReport: function (reportId, category) {
            const dropdown = document.getElementById("savedReportsDropdownWrapper");
            if (dropdown) dropdown.classList.remove("open");

            if (category === "Sales") activeTab = "sales";
            else if (category === "Pipeline") activeTab = "pipeline";
            else if (category === "Leads") activeTab = "conversion";
            else if (category === "Team") activeTab = "team";
            else if (category === "Activity") activeTab = "activity";
            else activeTab = "overview";

            renderActiveTab();
            showReportsToast(`Loaded report view: ${reportId}`);
        },

        openCustomDrawer: function () {
            const drawer = document.getElementById("reportsCustomDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                document.body.style.overflow = "hidden";
                window.reportsApp.updateDrawerPreview();
            }
        },

        closeCustomDrawer: function () {
            const drawer = document.getElementById("reportsCustomDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }
        },

        updateDrawerPreview: function () {
            const viz = document.querySelector('input[name="reportViz"]:checked')?.value || "line";
            const source = document.getElementById("reportDataSource")?.value || "Deals";

            if (viz === "table") {
                destroyChart("chartCustomDrawerPreview");
                const previewBox = document.getElementById("customReportDrawerPreview");
                if (previewBox) {
                    const topDeals = (reportData && reportData.topDeals) || [];
                    previewBox.innerHTML = `
                        <div style="font-size:11px; color:var(--text-muted); font-weight:600; margin-bottom:4px;">Table Preview (${source})</div>
                        <table class="reports-mini-table">
                            <thead><tr><th>Name</th><th>Stage</th><th>Value</th></tr></thead>
                            <tbody>
                                ${topDeals.slice(0, 2).map(d => `<tr><td>${escapeHtml(d.deal)}</td><td>${escapeHtml(d.stage)}</td><td>$${Number(d.value).toLocaleString()}</td></tr>`).join("")}
                            </tbody>
                        </table>
                    `;
                }
                return;
            }

            const previewBox = document.getElementById("customReportDrawerPreview");
            if (previewBox && !document.getElementById("chartCustomDrawerPreview")) {
                previewBox.innerHTML = `<canvas id="chartCustomDrawerPreview"></canvas>`;
            }

            const sampleTrend = (reportData && reportData.monthlyTrend) ? reportData.monthlyTrend.slice(-5) : [];
            createOrUpdateChart("chartCustomDrawerPreview", {
                type: viz === "horizontalBar" ? "bar" : viz,
                data: {
                    labels: sampleTrend.map(t => t.month),
                    datasets: [{ label: source, data: sampleTrend.map(t => t.revenue || 100), backgroundColor: "#7C3AED", borderColor: "#7C3AED" }]
                },
                options: {
                    indexAxis: viz === "horizontalBar" ? "y" : "x",
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        },

        showMetricModal: function (metricId) {
            const modal = document.getElementById("reportsMetricModal");
            const titleEl = document.getElementById("metricModalTitle");
            const bodyEl = document.getElementById("metricModalBody");

            const defs = {
                total_revenue: { title: "Total Revenue Formula", text: "<strong>Formula:</strong> Sum of all Closed-Won deal values within the selected reporting window.<br><br><code>Total Revenue = Σ (Closed Won Deal Values)</code>" },
                pipeline_value: { title: "Pipeline Value Formula", text: "<strong>Formula:</strong> Total unweighted sum of all active open deals in Prospect, Qualified, Proposal, and Negotiation stages.<br><br><code>Pipeline Value = Σ (Open Opportunity Values)</code>" },
                deals_won: { title: "Deals Won Definition", text: "<strong>Definition:</strong> Total count of individual opportunity records successfully moved to the Closed-Won status during the period." },
                win_rate: { title: "Win Rate Formula", text: "<strong>Formula:</strong> Won deals divided by total closed deals (Won + Lost).<br><br><code>Win Rate = (Deals Won / Total Closed Deals) × 100</code>" },
                avg_deal_size: { title: "Average Deal Size Formula", text: "<strong>Formula:</strong> Total Closed-Won revenue divided by total Closed-Won deal count.<br><br><code>Avg Deal Size = Total Revenue / Deals Won</code>" },
                avg_sales_cycle: { title: "Average Sales Cycle Formula", text: "<strong>Formula:</strong> Average elapsed days between lead creation timestamp and final Closed-Won timestamp.<br><br><code>Sales Cycle = Avg (Close Timestamp - Creation Timestamp)</code>" }
            };

            const info = defs[metricId] || { title: "Metric Definition", text: "Real-time metric computed directly from the MySQL database." };
            if (titleEl) titleEl.textContent = info.title;
            if (bodyEl) bodyEl.innerHTML = info.text;

            if (modal) {
                modal.classList.add("show");
                modal.setAttribute("aria-hidden", "false");
            }
        },

        closeMetricModal: function () {
            const modal = document.getElementById("reportsMetricModal");
            if (modal) {
                modal.classList.remove("show");
                modal.setAttribute("aria-hidden", "true");
            }
        },

        exportTableCSV: function () {
            const dropdown = document.getElementById("exportDropdownWrapper");
            if (dropdown) dropdown.classList.remove("open");

            const params = new URLSearchParams({
                action: "export_csv",
                tab: activeTab,
                dateRange: activeFilters.dateRange,
                pipeline: activeFilters.pipeline,
                teamRep: activeFilters.teamRep,
                leadSource: activeFilters.leadSource,
                region: activeFilters.region
            });

            // Trigger real server-side CSV streaming download
            window.location.href = `api/reports.php?${params.toString()}`;
            showReportsToast("Generating CSV export from database...");
        },

        printPDF: function () {
            const dropdown = document.getElementById("exportDropdownWrapper");
            if (dropdown) dropdown.classList.remove("open");
            window.print();
        },

        copySummary: function () {
            const dropdown = document.getElementById("exportDropdownWrapper");
            if (dropdown) dropdown.classList.remove("open");

            if (!reportData || !reportData.kpis) {
                showReportsToast("Report data not loaded yet.");
                return;
            }

            const rev = reportData.kpis[0] ? reportData.kpis[0].value : "$0";
            const pipe = reportData.kpis[1] ? reportData.kpis[1].value : "$0";
            const won = reportData.kpis[2] ? reportData.kpis[2].value : "0";
            const winRate = reportData.kpis[3] ? reportData.kpis[3].value : "0%";
            const cycle = reportData.kpis[5] ? reportData.kpis[5].value : "0 days";

            const text = `NexFlow CRM Summary (${activeTab.toUpperCase()})\nTotal Revenue: ${rev}\nPipeline Value: ${pipe}\nDeals Won: ${won}\nWin Rate: ${winRate}\nAvg Sales Cycle: ${cycle}`;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showReportsToast("Summary copied to clipboard!");
                });
            } else {
                showReportsToast("Summary: " + text.replace(/\n/g, " | "));
            }
        }
    };

    // Custom Report Form Submission -> Persist to MySQL
    const form = document.getElementById("customReportForm");
    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            const name = document.getElementById("reportName").value.trim();
            const category = document.getElementById("reportCategory").value;
            const dataSource = document.getElementById("reportDataSource").value;
            const groupBy = document.getElementById("reportGroupBy").value;
            const viz = document.querySelector('input[name="reportViz"]:checked')?.value || "line";

            const checkedMetrics = [];
            document.querySelectorAll('input[name="reportMetric"]:checked').forEach(cb => {
                checkedMetrics.push(cb.value);
            });

            if (!name) return;

            const payload = {
                action: "save_custom_report",
                name: name,
                category: category,
                data_source: dataSource,
                metrics: checkedMetrics,
                group_by: groupBy,
                chart_type: viz
            };

            fetch("api/reports.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(resData => {
                    if (resData.success) {
                        showReportsToast(`Custom report '${name}' saved to database!`);
                        window.reportsApp.closeCustomDrawer();
                        // Refresh data to show new report in saved reports
                        loadReportsData();
                    } else {
                        showReportsToast(resData.message || "Failed to save custom report.");
                    }
                })
                .catch(err => {
                    console.error("Save custom report error:", err);
                    showReportsToast("Error saving custom report to database.");
                });
        });
    }

    // Helper: SVG Icons for KPI cards
    function getKpiIconSvg(type) {
        if (type === "dollar-sign") return `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>`;
        if (type === "trending-up") return `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>`;
        if (type === "award") return `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>`;
        if (type === "pie-chart") return `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 118 2.83"/><path d="M22 12A10 10 0 0012 2v10z"/></svg>`;
        if (type === "briefcase") return `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>`;
        return `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    window.showReportsToast = function (msg) {
        const toast = document.createElement("div");
        toast.style.cssText = "position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background-color: #101828; color: #ffffff; padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 15px rgba(0,0,0,0.2); z-index: 200; pointer-events: none; transition: opacity 0.3s ease;";
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    };
})();
