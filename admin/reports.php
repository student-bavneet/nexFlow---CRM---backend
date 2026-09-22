<?php
// Reports & Analytics Page
require_once __DIR__ . '/data/mock-data.php';

$page_title = "Reports & Analytics";
$current_page = "reports";
$page_script = "reports.js";

include __DIR__ . '/includes/header.php';
requirePermission('reports');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content reports-page">
    <div class="page-container">
        
        <!-- 1. Page Header -->
        <div class="reports-header">
            <div class="reports-header-left">
                <div class="reports-title-row">
                    <h1 class="reports-header-title">Reports & Analytics</h1>
                </div>
                <p class="reports-header-subtitle">Monitor sales performance, conversions and revenue forecasts.</p>
            </div>
            
            <div class="reports-header-right">
                <!-- Saved Reports Dropdown -->
                <div class="reports-dropdown-wrapper" id="savedReportsDropdownWrapper">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnSavedReportsDropdown">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
                        Saved Reports
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="reports-dropdown-menu" id="savedReportsMenu">
                        <div class="reports-dropdown-header">Preset & Custom Reports</div>
                        <div class="reports-dropdown-list" id="savedReportsList">
                            <!-- Populated dynamically by JS -->
                        </div>
                    </div>
                </div>

                <!-- Export Menu Dropdown -->
                <div class="reports-dropdown-wrapper" id="exportDropdownWrapper">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnExportDropdown">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="reports-dropdown-menu reports-dropdown-menu-right" id="exportMenu">
                        <button type="button" class="reports-dropdown-item" onclick="window.reportsApp.exportTableCSV()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Export Current Table as CSV
                        </button>
                        <button type="button" class="reports-dropdown-item" onclick="window.reportsApp.printPDF()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Print / Save as PDF
                        </button>
                        <button type="button" class="reports-dropdown-item" onclick="window.reportsApp.copySummary()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            Copy Summary to Clipboard
                        </button>
                    </div>
                </div>

                <!-- Share Report Action -->
                <button type="button" class="btn btn-secondary btn-sm" id="btnShareReport" onclick="window.NexFlowShare.openReportShare(this)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    Share Report
                </button>

                <!-- Primary Action -->
                <button type="button" class="btn btn-primary btn-sm" onclick="window.reportsApp.openCustomDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Custom Report
                </button>
            </div>
        </div>

        <!-- 2. Sticky Global Filter Bar -->
        <div class="reports-filter-bar" id="reportsFilterBar">
            <div class="reports-filter-controls"> 
                <!-- Date Range Presets -->
                <div class="reports-filter-group">
                    <label class="reports-filter-label" for="filterDateRange">Date Range</label>
                    <select class="input-control input-sm" id="filterDateRange" onchange="window.reportsApp.handleFilterChange()">
                        <option value="today">Today</option>
                        <option value="last7">Last 7 Days</option>
                        <option value="last30" selected>Last 30 Days</option>
                        <option value="thisMonth">This Month</option>
                        <option value="lastMonth">Last Month</option>
                        <option value="thisQuarter">This Quarter</option>
                        <option value="thisYear">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <!-- Compare Period Toggle -->
                <div class="reports-filter-group reports-filter-checkbox-group" style="margin-top: 20px;">
                    <label class="reports-checkbox-label">
                        <input type="checkbox" id="filterCompareToggle" checked onchange="window.reportsApp.handleFilterChange()">
                        <span>Compare vs Prev Period</span>
                    </label>
                </div>

                <!-- Pipeline Filter -->
                <div class="reports-filter-group">
                    <label class="reports-filter-label" for="filterPipeline">Pipeline</label>
                    <select class="input-control input-sm" id="filterPipeline" onchange="window.reportsApp.handleFilterChange()">
                        <option value="all">All Pipelines</option>
                        <option value="enterprise">Enterprise Sales</option>
                        <option value="smb">SMB Sales</option>
                        <option value="inbound">Inbound Leads</option>
                    </select>
                </div>

                <!-- Team Member -->
                <div class="reports-filter-group">
                    <label class="reports-filter-label" for="filterTeamRep">Team Member</label>
                    <select class="input-control input-sm" id="filterTeamRep" onchange="window.reportsApp.handleFilterChange()">
                        <option value="all">All Representatives</option>
                        <option value="Olivia Martin">Olivia Martin</option>
                        <option value="Sarah Chen">Sarah Chen</option>
                        <option value="James Wu">James Wu</option>
                    </select>
                </div>

                <!-- Lead Source -->
                <div class="reports-filter-group">
                    <label class="reports-filter-label" for="filterLeadSource">Lead Source</label>
                    <select class="input-control input-sm" id="filterLeadSource" onchange="window.reportsApp.handleFilterChange()">
                        <option value="all">All Sources</option>
                        <option value="Website">Website</option>
                        <option value="Referral">Referral</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="Email Campaign">Email Campaign</option>
                        <option value="Events">Events</option>
                        <option value="Cold Outreach">Cold Outreach</option>
                    </select>
                </div>

                <!-- Region -->
                <div class="reports-filter-group">
                    <label class="reports-filter-label" for="filterRegion">Region</label>
                    <select class="input-control input-sm" id="filterRegion" onchange="window.reportsApp.handleFilterChange()">
                        <option value="all">All Regions</option>
                        <option value="North America">North America</option>
                        <option value="Europe">Europe</option>
                        <option value="Asia Pacific">Asia Pacific</option>
                        <option value="Latin America">Latin America</option>
                    </select>
                </div>

                <!-- Filter Action Buttons -->
                <div class="reports-filter-actions">
                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.reportsApp.resetFilters()">Reset</button>
                    <button type="button" class="btn btn-primary btn-xs" onclick="window.reportsApp.applyFilters()">Apply</button>
                </div>
            </div>

            <!-- Active Filter Chips Bar -->
            <div class="reports-active-chips-bar" id="reportsActiveChipsBar">
                <span class="reports-chips-label">Active Filters:</span>
                <div class="reports-chips-container" id="reportsChipsContainer">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- 3. Report Tab Navigation -->
        <div class="reports-tabs-bar">
            <div class="reports-tabs-list" role="tablist">
                <button type="button" class="reports-tab-btn active" role="tab" aria-selected="true" data-tab="overview" onclick="window.reportsApp.switchTab('overview', this)">Overview</button>
                <button type="button" class="reports-tab-btn" role="tab" aria-selected="false" data-tab="sales" onclick="window.reportsApp.switchTab('sales', this)">Sales Performance</button>
                <button type="button" class="reports-tab-btn" role="tab" aria-selected="false" data-tab="pipeline" onclick="window.reportsApp.switchTab('pipeline', this)">Pipeline</button>
                <button type="button" class="reports-tab-btn" role="tab" aria-selected="false" data-tab="conversion" onclick="window.reportsApp.switchTab('conversion', this)">Lead Conversion</button>
                <button type="button" class="reports-tab-btn" role="tab" aria-selected="false" data-tab="team" onclick="window.reportsApp.switchTab('team', this)">Team Performance</button>
                <button type="button" class="reports-tab-btn" role="tab" aria-selected="false" data-tab="activity" onclick="window.reportsApp.switchTab('activity', this)">Activity</button>
            </div>
        </div>

        <!-- 4. TAB CONTENTS -->
        <div class="reports-tab-body">
            
            <!-- TAB 1: OVERVIEW -->
            <div class="reports-tab-pane active" id="pane-overview">
                <!-- 6 Compact KPI Cards Grid -->
                <div class="reports-kpi-grid" id="overviewKpiGrid">
                    <!-- Rendered dynamically by JS -->
                </div>

                <!-- Main Chart & Insights Row -->
                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <!-- Revenue vs Target Line Chart -->
                    <div class="reports-card reports-card-lg">
                        <div class="reports-card-header">
                            <div>
                                <h3 class="reports-card-title">Revenue vs Target</h3>
                                <p class="reports-card-subtitle">Monthly closed-won revenue compared to company quota targets.</p>
                            </div>
                            <div class="reports-chart-summary-badge" id="overviewChartSummaryBadge">$486.2K Total Revenue</div>
                        </div>
                        <div class="reports-chart-container">
                            <canvas id="chartRevenueVsTarget"></canvas>
                        </div>
                    </div>

                    <!-- Forecast & Demo Insights Panel -->
                    <div class="reports-card-stack">
                        <!-- Sales Forecast Card -->
                        <div class="reports-card">
                            <div class="reports-card-header">
                                <div>
                                    <h3 class="reports-card-title">Sales Forecast</h3>
                                    <p class="reports-card-subtitle">Commit vs Best-Case probability projection.</p>
                                </div>
                                <span class="reports-chip-demo">Calculated</span>
                            </div>
                            <div class="reports-forecast-body">
                                <div class="reports-forecast-metrics">
                                    <div class="reports-forecast-item">
                                        <span class="reports-forecast-label">Commit Forecast</span>
                                        <span class="reports-forecast-val" id="overviewForecastCommit">$0</span>
                                    </div>
                                    <div class="reports-forecast-item">
                                        <span class="reports-forecast-label">Best-Case Forecast</span>
                                        <span class="reports-forecast-val" id="overviewForecastBestCase">$0</span>
                                    </div>
                                    <div class="reports-forecast-item">
                                        <span class="reports-forecast-label">Total Open Pipeline</span>
                                        <span class="reports-forecast-val" id="overviewForecastPipeline">$0</span>
                                    </div>
                                </div>
                                <div style="margin-top: 12px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 600; margin-bottom: 4px;">
                                        <span>Target Attainment</span>
                                        <span style="color: var(--primary);" id="overviewForecastAttainmentText">0%</span>
                                    </div>
                                    <div class="reports-progress-bg">
                                        <div class="reports-progress-fill" id="overviewForecastProgressFill" style="width: 0%; background-color: var(--primary);"></div>
                                    </div>
                                </div>
                                <div class="reports-notice-small" id="overviewForecastNotice">
                                    💡 Forecast is calculated from probability-weighted open deals.
                                </div>
                            </div>
                        </div>

                        <!-- Performance Insights Panel -->
                        <div class="reports-card reports-insights-card">
                            <div class="reports-card-header">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <svg width="16" height="16" fill="none" stroke="#F59E0B" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    <h3 class="reports-card-title">Performance Insights</h3>
                                </div>
                            </div>
                            <ul class="reports-insights-list" id="overviewInsightsList">
                                <li>Loading performance insights...</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Stage Bar Chart & HTML Funnel Row -->
                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <!-- Horizontal Bar Chart: Pipeline by Stage -->
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <div>
                                <h3 class="reports-card-title">Pipeline by Stage</h3>
                                <p class="reports-card-subtitle">Active opportunity distribution across funnel stages.</p>
                            </div>
                        </div>
                        <div class="reports-chart-container" style="height: 240px;">
                            <canvas id="chartPipelineByStage"></canvas>
                        </div>
                        <!-- Stage Detail Table -->
                        <div style="margin-top: 12px; overflow-x: auto;">
                            <table class="reports-mini-table" id="reportsOverviewStageTable">
                                <thead>
                                    <tr>
                                        <th data-protected="true" data-column-id="stage">Stage</th>
                                        <th data-column-id="deals">Deals</th>
                                        <th data-column-id="total-value">Total Value</th>
                                        <th data-column-id="stage-conv">Stage Conv.</th>
                                        <th data-column-id="avg-time">Avg Time</th>
                                    </tr>
                                </thead>
                                <tbody id="overviewStageTableBody">
                                    <!-- Rendered dynamically by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Lead Conversion Funnel (Responsive HTML/CSS Visual) -->
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <div>
                                <h3 class="reports-card-title">Lead Conversion Funnel</h3>
                                <p class="reports-card-subtitle">Stage-by-stage progression and drop-off rate.</p>
                            </div>
                            <span class="reports-chip-demo" id="overviewFunnelSummaryBadge">Overall Conv: 0.0%</span>
                        </div>
                        <div class="reports-funnel-wrapper" id="overviewFunnelWrapper">
                            <!-- Rendered dynamically by JS -->
                        </div>
                    </div>
                </div>

                <!-- Lead Sources & Top Deals Row -->
                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <!-- Lead Sources Doughnut Chart -->
                    <div class="reports-card lead-sources-card">
                        <div class="reports-card-header">
                            <div>
                                <h3 class="reports-card-title">Lead Sources Breakdown</h3>
                                <p class="reports-card-subtitle">Lead volume &amp; revenue contribution by acquisition channel.</p>
                            </div>
                        </div>
                        <div class="lead-sources-layout">
                            <div class="lead-sources-chart-col">
                                <div class="lead-sources-chart-wrapper">
                                    <canvas id="chartLeadSources"></canvas>
                                </div>
                            </div>
                            <div class="lead-sources-table-col">
                                <table class="reports-mini-table lead-sources-table" id="reportsOverviewSourcesTable">
                                    <thead>
                                        <tr>
                                            <th class="col-source" data-protected="true" data-column-id="source">Source</th>
                                            <th class="col-leads" data-column-id="leads">Leads</th>
                                            <th class="col-won" data-column-id="won">Won</th>
                                            <th class="col-revenue" data-column-id="revenue">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody id="overviewSourcesTableBody">
                                        <!-- Rendered dynamically by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Top Closed & Open Deals Table -->
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <div>
                                <h3 class="reports-card-title">Top High-Value Deals</h3>
                                <p class="reports-card-subtitle">Highest valued opportunities in current reporting period.</p>
                            </div>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="reports-table" id="reportsOverviewTopDealsTable">
                                <thead>
                                    <tr>
                                        <th data-protected="true" data-column-id="deal-name">Deal Name</th>
                                        <th data-column-id="company">Company</th>
                                        <th data-column-id="owner">Owner</th>
                                        <th data-column-id="stage">Stage</th>
                                        <th data-column-id="value">Value</th>
                                    </tr>
                                </thead>
                                <tbody id="overviewTopDealsTableBody">
                                    <!-- Rendered dynamically by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: SALES PERFORMANCE -->
            <div class="reports-tab-pane" id="pane-sales">
                <div class="reports-grid-2col">
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Monthly Revenue Trend</h3>
                            <span class="reports-card-subtitle">Revenue progression vs prior period</span>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartSalesRevenueTrend"></canvas></div>
                    </div>

                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Deals Won vs Lost</h3>
                            <span class="reports-card-subtitle">Monthly win/loss outcome count</span>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartDealsWonLost"></canvas></div>
                    </div>
                </div>

                <div class="reports-grid-3col" style="margin-top: 18px;">
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Average Deal Size Trend</h3>
                        </div>
                        <div class="reports-chart-container" style="height: 200px;"><canvas id="chartAvgDealSizeTrend"></canvas></div>
                    </div>
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Sales Cycle Length (Days)</h3>
                        </div>
                        <div class="reports-chart-container" style="height: 200px;"><canvas id="chartSalesCycleTrend"></canvas></div>
                    </div>
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Revenue by Product / Service</h3>
                        </div>
                        <div class="reports-chart-container" style="height: 200px;"><canvas id="chartProductRevenue"></canvas></div>
                    </div>
                </div>

                <div class="reports-card" style="margin-top: 18px;">
                    <div class="reports-card-header">
                        <h3 class="reports-card-title">Top Sales Deals Detail</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="reports-table" id="reportsSalesTopDealsTable">
                            <thead>
                                <tr>
                                    <th data-protected="true" data-column-id="deal">Deal</th>
                                    <th data-column-id="company">Company</th>
                                    <th data-column-id="owner">Owner</th>
                                    <th data-column-id="stage">Stage</th>
                                    <th data-column-id="value">Value</th>
                                    <th data-column-id="close-date">Close Date</th>
                                </tr>
                            </thead>
                            <tbody id="salesTopDealsFullBody">
                                <!-- Rendered dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: PIPELINE -->
            <div class="reports-tab-pane" id="pane-pipeline">
                <div class="reports-kpi-grid" id="pipelineKpiGrid">
                    <!-- 5 Pipeline KPI cards rendered by JS -->
                </div>

                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Pipeline Value by Stage</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartPipelineValueStage"></canvas></div>
                    </div>
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Deal Count by Stage</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartPipelineCountStage"></canvas></div>
                    </div>
                </div>

                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Pipeline Age Distribution</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartPipelineAge"></canvas></div>
                    </div>

                    <!-- At-Risk Deals Table -->
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">⚠️ At-Risk Opportunities</h3>
                            <span class="reports-chip-demo">Requires Attention</span>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="reports-table" id="reportsPipelineRiskTable">
                                <thead>
                                    <tr>
                                        <th data-protected="true" data-column-id="deal">Deal</th>
                                        <th data-column-id="company">Company</th>
                                        <th data-column-id="stage">Stage</th>
                                        <th data-column-id="days">Days</th>
                                        <th data-column-id="value">Value</th>
                                        <th data-column-id="risk-reason">Risk Reason</th>
                                        <th data-protected="true" data-column-id="actions">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="pipelineAtRiskTableBody">
                                    <!-- Rendered dynamically by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: LEAD CONVERSION -->
            <div class="reports-tab-pane" id="pane-conversion">
                <div class="reports-kpi-grid" id="conversionKpiGrid">
                    <!-- Conversion KPI metrics rendered by JS -->
                </div>

                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Conversion Rate Trend</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartConversionTrend"></canvas></div>
                    </div>

                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Conversion by Lead Source</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartConversionBySource"></canvas></div>
                    </div>
                </div>

                <div class="reports-card" style="margin-top: 18px;">
                    <div class="reports-card-header">
                        <h3 class="reports-card-title">Lead Source Performance Breakdown</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="reports-table" id="reportsLeadSourceTable">
                            <thead>
                                <tr>
                                    <th data-protected="true" data-column-id="source">Source</th>
                                    <th data-column-id="total-leads">Total Leads</th>
                                    <th data-column-id="qualified">Qualified</th>
                                    <th data-column-id="opportunities">Opportunities</th>
                                    <th data-column-id="won-deals">Won Deals</th>
                                    <th data-column-id="win-conv">Win Conv. %</th>
                                    <th data-column-id="revenue">Revenue Generated</th>
                                </tr>
                            </thead>
                            <tbody id="leadSourceTableFullBody">
                                <!-- Rendered dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: TEAM PERFORMANCE -->
            <div class="reports-tab-pane" id="pane-team">
                <div class="reports-kpi-grid" id="teamKpiGrid">
                    <!-- Team KPI cards rendered by JS -->
                </div>

                <div class="reports-card" style="margin-top: 18px;">
                    <div class="reports-card-header">
                        <h3 class="reports-card-title">Sales Representative Leaderboard</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="reports-table" id="reportsTeamLeaderboardTable">
                            <thead>
                                <tr>
                                    <th data-protected="true" data-column-id="representative">Representative</th>
                                    <th data-column-id="revenue-won">Revenue Won</th>
                                    <th data-column-id="quota-target">Quota Target</th>
                                    <th data-column-id="attainment">Attainment</th>
                                    <th data-column-id="deals-won">Deals Won</th>
                                    <th data-column-id="win-rate">Win Rate</th>
                                    <th data-column-id="avg-deal-size">Avg Deal Size</th>
                                    <th data-column-id="activities">Activities</th>
                                </tr>
                            </thead>
                            <tbody id="teamLeaderboardTableBody">
                                <!-- Rendered dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Revenue by Representative</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartTeamRevenue"></canvas></div>
                    </div>
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Target vs Actual Revenue</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartTeamTargetVsActual"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- TAB 6: ACTIVITY -->
            <div class="reports-tab-pane" id="pane-activity">
                <div class="reports-kpi-grid" id="activityKpiGrid">
                    <!-- Activity KPI metrics rendered by JS -->
                </div>

                <div class="reports-grid-2col" style="margin-top: 18px;">
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Daily Activity Distribution</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartActivityDaily"></canvas></div>
                    </div>
                    <div class="reports-card">
                        <div class="reports-card-header">
                            <h3 class="reports-card-title">Activities by Type</h3>
                        </div>
                        <div class="reports-chart-container"><canvas id="chartActivityTypes"></canvas></div>
                    </div>
                </div>
            </div>

        </div> <!-- End reports-tab-body -->
    </div> <!-- End page-container -->
</main>

<!-- 5. Custom Report Drawer (500px right-side slide-over) -->
<div class="reports-custom-drawer" id="reportsCustomDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="customReportModalTitle">
    <div class="reports-drawer-panel">
        <div class="reports-drawer-header">
            <div>
                <h3 class="reports-drawer-title" id="customReportModalTitle">Create Custom Report</h3>
                <p class="reports-drawer-subtitle">Configure custom metrics, groupings &amp; visualizations.</p>
            </div>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.reportsApp.closeCustomDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="customReportForm" style="display: flex; flex-direction: column; height: calc(100% - 65px);">
            <div class="reports-drawer-body">
                <!-- Step 1: Report Details -->
                <div class="reports-form-section">
                    <div class="reports-form-section-title">Step 1 — Report Details</div>
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label class="form-label" for="reportName">Report Name *</label>
                        <input type="text" class="input-control input-sm" id="reportName" required placeholder="e.g. Q3 Regional Pipeline Growth">
                    </div>
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label class="form-label" for="reportCategory">Category</label>
                        <select class="input-control input-sm" id="reportCategory">
                            <option value="Sales">Sales Performance</option>
                            <option value="Pipeline">Pipeline &amp; Deals</option>
                            <option value="Leads">Lead Conversion</option>
                            <option value="Team">Team Performance</option>
                            <option value="Activity">Customer Activity</option>
                        </select>
                    </div>
                </div>

                <!-- Step 2: Data Source -->
                <div class="reports-form-section">
                    <div class="reports-form-section-title">Step 2 — Data Source</div>
                    <select class="input-control input-sm" id="reportDataSource" onchange="window.reportsApp.updateDrawerPreview()">
                        <option value="Deals">Sales Deals &amp; Opportunities</option>
                        <option value="Leads">Marketing Leads</option>
                        <option value="Contacts">Contacts &amp; Accounts</option>
                        <option value="Companies">Company Directory</option>
                        <option value="Tasks">Tasks &amp; To-Dos</option>
                        <option value="Activities">Sales Activities</option>
                    </select>
                </div>

                <!-- Step 3: Metrics -->
                <div class="reports-form-section">
                    <div class="reports-form-section-title">Step 3 — Metrics</div>
                    <div class="reports-checkbox-grid">
                        <label class="reports-checkbox-label"><input type="checkbox" name="reportMetric" value="Revenue" checked onchange="window.reportsApp.updateDrawerPreview()"> Revenue</label>
                        <label class="reports-checkbox-label"><input type="checkbox" name="reportMetric" value="Deal Value" checked onchange="window.reportsApp.updateDrawerPreview()"> Deal Value</label>
                        <label class="reports-checkbox-label"><input type="checkbox" name="reportMetric" value="Deal Count" checked onchange="window.reportsApp.updateDrawerPreview()"> Deal Count</label>
                        <label class="reports-checkbox-label"><input type="checkbox" name="reportMetric" value="Win Rate" onchange="window.reportsApp.updateDrawerPreview()"> Win Rate</label>
                        <label class="reports-checkbox-label"><input type="checkbox" name="reportMetric" value="Conversion Rate" onchange="window.reportsApp.updateDrawerPreview()"> Conversion Rate</label>
                        <label class="reports-checkbox-label"><input type="checkbox" name="reportMetric" value="Avg Deal Size" onchange="window.reportsApp.updateDrawerPreview()"> Avg Deal Size</label>
                    </div>
                </div>

                <!-- Step 4: Grouping -->
                <div class="reports-form-section">
                    <div class="reports-form-section-title">Step 4 — Group By</div>
                    <select class="input-control input-sm" id="reportGroupBy" onchange="window.reportsApp.updateDrawerPreview()">
                        <option value="Month">Date (Monthly)</option>
                        <option value="Stage">Sales Stage</option>
                        <option value="Owner">Sales Representative</option>
                        <option value="Source">Lead Source</option>
                        <option value="Region">Geographic Region</option>
                    </select>
                </div>

                <!-- Step 5: Visualization -->
                <div class="reports-form-section">
                    <div class="reports-form-section-title">Step 5 — Visualization</div>
                    <div class="reports-viz-selector">
                        <label class="reports-viz-option"><input type="radio" name="reportViz" value="line" checked onchange="window.reportsApp.updateDrawerPreview()"> 📈 Line Chart</label>
                        <label class="reports-viz-option"><input type="radio" name="reportViz" value="bar" onchange="window.reportsApp.updateDrawerPreview()"> 📊 Bar Chart</label>
                        <label class="reports-viz-option"><input type="radio" name="reportViz" value="horizontalBar" onchange="window.reportsApp.updateDrawerPreview()"> ⏸️ Horizontal Bar</label>
                        <label class="reports-viz-option"><input type="radio" name="reportViz" value="doughnut" onchange="window.reportsApp.updateDrawerPreview()"> 🍩 Doughnut</label>
                        <label class="reports-viz-option"><input type="radio" name="reportViz" value="table" onchange="window.reportsApp.updateDrawerPreview()"> 📋 Data Table</label>
                    </div>
                </div>

                <!-- Live Preview Box -->
                <div class="reports-form-section">
                    <div class="reports-form-section-title">Live Preview</div>
                    <div class="reports-drawer-preview" id="customReportDrawerPreview">
                        <canvas id="chartCustomDrawerPreview"></canvas>
                    </div>
                </div>
            </div>

            <div class="reports-drawer-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.reportsApp.closeCustomDrawer()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Custom Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Metric Info Calculation Modal / Popover -->
<div class="reports-modal-backdrop" id="reportsMetricModal" role="dialog" aria-hidden="true">
    <div class="reports-modal-card">
        <div class="reports-modal-header">
            <h4 class="reports-modal-title" id="metricModalTitle">Metric Definition</h4>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.reportsApp.closeMetricModal()">✕</button>
        </div>
        <div class="reports-modal-body" id="metricModalBody">
            <!-- Rendered by JS -->
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

