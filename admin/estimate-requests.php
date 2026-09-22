<?php
// Estimate Requests Management Page

$page_title = "Estimate Requests";
$current_page = "estimate-requests";
$page_script = "estimate-requests.js";

include __DIR__ . '/includes/header.php';
requirePermission('estimate-requests');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content est-req-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="est-req-header">
            <div class="est-req-header-title-area">
                <div class="est-req-title-row">
                    <h1 class="est-req-header-title">Estimate Requests</h1>
                    <span class="est-req-total-badge" id="requestsTotalCountBadge">0 requests</span>
                </div>
                <p class="est-req-header-subtitle">Collect, review and convert customer pricing requests into sales opportunities.</p>
            </div>
            <div class="est-req-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.estimateRequestsApp.openCreateModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Create Request
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Bar -->
        <div class="est-req-summary-bar">
            
            <div class="est-req-summary-card" onclick="window.estimateRequestsApp.filterByTab('All')">
                <div class="est-req-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="16" y1="14" x2="16" y2="18"/><path d="M16 10h.01M12 10h.01M8 10h.01M12 14h.01M8 14h.01M12 18h.01M8 18h.01"/></svg>
                </div>
                <div class="est-req-summary-info">
                    <span class="est-req-summary-val" id="kpiTotalRequests">0</span>
                    <span class="est-req-summary-lbl">Total Requests</span>
                </div>
            </div>

            <div class="est-req-summary-card" onclick="window.estimateRequestsApp.filterByKPI('New')">
                <div class="est-req-summary-icon" style="background-color: #EFF6FF; color: #1D4ED8;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="est-req-summary-info">
                    <span class="est-req-summary-val" id="kpiNewRequests">0</span>
                    <span class="est-req-summary-lbl">New Requests</span>
                </div>
            </div>

            <div class="est-req-summary-card" onclick="window.estimateRequestsApp.filterByKPI('In Review')">
                <div class="est-req-summary-icon" style="background-color: #FFFBEB; color: #B45309;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="est-req-summary-info">
                    <span class="est-req-summary-val" id="kpiInReview">0</span>
                    <span class="est-req-summary-lbl">In Review</span>
                </div>
            </div>

            <div class="est-req-summary-card" onclick="window.estimateRequestsApp.filterByKPI('Approved')">
                <div class="est-req-summary-icon" style="background-color: #ECFDF5; color: #047857;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="est-req-summary-info">
                    <span class="est-req-summary-val" id="kpiApproved">0</span>
                    <span class="est-req-summary-lbl">Approved Estimates</span>
                </div>
            </div>

            <div class="est-req-summary-card" onclick="window.estimateRequestsApp.filterByKPI('Converted')">
                <div class="est-req-summary-icon" style="background-color: #EEF2FF; color: #4338CA;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </div>
                <div class="est-req-summary-info">
                    <span class="est-req-summary-val" id="kpiConverted">0</span>
                    <span class="est-req-summary-lbl">Converted to Deals</span>
                </div>
            </div>

        </div>

        <!-- Aligned Responsive Toolbar (Single-Row Desktop) -->
        <div class="est-req-toolbar">
            <div class="est-req-toolbar-row1" style="display: flex; align-items: center; gap: 10px; width: 100%; flex-wrap: wrap;">
                
                <!-- 1. Search Field -->
                <div style="position: relative; flex: 1; min-width: 220px;">
                    <svg width="15" height="15" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm" id="reqSearchInput" placeholder="Search request ID, subject, customer..." style="padding-left: 32px; width: 100%;" oninput="window.estimateRequestsApp.handleSearch(this.value)">
                </div>

                <!-- 2. Filter Button -->
                <div style="position: relative;">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnEstReqFilter" onclick="window.estimateRequestsApp.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filter</span>
                    </button>

                    <div class="estimate-requests-filter-popover" id="estimateRequestsFilterPopover" onclick="event.stopPropagation()">
                        <div class="estimate-requests-filter-header">
                            <div class="estimate-requests-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Estimate Requests</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.estimateRequestsApp.clearFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="window.estimateRequestsApp.closeFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="estimate-requests-filter-body">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Service Type</label>
                                <select class="input-control input-sm" id="filterEstService">
                                    <option value="All">All Services</option>
                                    <option value="CRM Migration &amp; Custom Setup">CRM Migration &amp; Custom Setup</option>
                                    <option value="Enterprise API &amp; Data Pipelines">Enterprise API &amp; Data Pipelines</option>
                                    <option value="Predictive AI Lead Scoring">Predictive AI Lead Scoring</option>
                                    <option value="Custom Reporting &amp; Dashboard">Custom Reporting &amp; Dashboard</option>
                                    <option value="Multi-Currency Billing Portal">Multi-Currency Billing Portal</option>
                                    <option value="Custom SLA Support Tier">Custom SLA Support Tier</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Status</label>
                                <select class="input-control input-sm" id="filterEstStatus">
                                    <option value="All">All Statuses</option>
                                    <option value="New">New</option>
                                    <option value="In Review">In Review</option>
                                    <option value="More Info Needed">More Info Needed</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="Converted">Converted</option>
                                </select>
                            </div>
                        </div>
                        <div class="estimate-requests-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.estimateRequestsApp.applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- 3. Sort Dropdown -->
                <select class="input-control input-sm" style="width: 170px; flex-shrink: 0;" id="reqSortDropdown" onchange="window.estimateRequestsApp.handleSort(this.value)">
                    <option value="newest">Sort: Newest Date</option>
                    <option value="oldest">Sort: Oldest Date</option>
                    <option value="customer-asc">Sort: Customer (A-Z)</option>
                    <option value="budget-desc">Sort: Highest Budget</option>
                </select>

                <!-- 4. All Statuses Dropdown -->
                <select class="input-control input-sm" style="width: 175px; flex-shrink: 0;" id="reqStatusSelect" onchange="window.estimateRequestsApp.filterByStatus(this.value)">
                    <option value="All" id="optEstStatus_All">All Statuses (0)</option>
                    <option value="New" id="optEstStatus_New">New (0)</option>
                    <option value="In Review" id="optEstStatus_InReview">In Review (0)</option>
                    <option value="More Info Needed" id="optEstStatus_MoreInfoNeeded">More Info Needed (0)</option>
                    <option value="Approved" id="optEstStatus_Approved">Approved (0)</option>
                    <option value="Rejected" id="optEstStatus_Rejected">Rejected (0)</option>
                    <option value="Converted" id="optEstStatus_Converted">Converted (0)</option>
                </select>

                <!-- 5. Customize Columns Button -->
                <div class="table-columns-dropdown-wrapper" style="flex-shrink: 0;">
                    <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                        Customize Columns
                    </button>
                </div>

            </div>
        </div>

        <!-- Estimate Requests Table Card -->
        <div class="est-req-table-card">
            <div class="est-req-table-wrapper">
                <table class="est-req-table crm-table" id="estimateRequestsTable">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Request / Subject</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Requested Services</th>
                            <th>Budget Range</th>
                            <th>Assigned Owner</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Last Activity</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="estimateRequestsTableBody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-container">
                <div>Showing <span style="font-weight: 600; color: var(--text-body);" id="pagingRange">0–0</span> of <span style="font-weight: 600; color: var(--text-body);" id="pagingTotal">0</span></div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Details Drawer Overlay & Panel (width: 580px, z-index: 1010) -->
<div class="est-req-drawer-overlay" id="estimateRequestsDrawer" onclick="if(event.target===this) window.estimateRequestsApp.closeDrawer()">
    <div class="est-req-drawer-panel" onclick="event.stopPropagation()">
        
        <div class="est-req-drawer-header">
            <div class="est-req-drawer-title-area">
                <span class="est-req-drawer-subtitle" id="estDrawerSubtitle"></span>
                <h3 class="est-req-drawer-title" id="estDrawerTitle"></h3>
            </div>
            <div class="est-req-drawer-header-actions">
                <span class="badge-req badge-new" id="estDrawerBadge">● New</span>
                <button type="button" class="btn btn-ghost btn-xs" id="estDrawerShareBtn" title="Share Estimate Request" aria-label="Share Estimate Request" onclick="window.NexFlowShare.openEstimateRequestShare(window.estimateRequestsApp?.activeReqId, this)">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeDrawer()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div class="est-req-drawer-tabs">
            <button type="button" class="est-req-drawer-tab active" data-tab="overview" onclick="window.estimateRequestsApp.switchDrawerTab('overview')">Overview</button>
            <button type="button" class="est-req-drawer-tab" data-tab="requirements" onclick="window.estimateRequestsApp.switchDrawerTab('requirements')">Requirements</button>
            <button type="button" class="est-req-drawer-tab" data-tab="activity" onclick="window.estimateRequestsApp.switchDrawerTab('activity')">Activity</button>
            <button type="button" class="est-req-drawer-tab" data-tab="notes" onclick="window.estimateRequestsApp.switchDrawerTab('notes')">Notes</button>
        </div>

        <div class="est-req-drawer-body" id="estDrawerBody">
            <!-- Dynamically populated -->
        </div>

        <div class="est-req-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeDrawer()">Close</button>
        </div>

    </div>
</div>

<!-- Top-Level Child Modals Overlay (z-index: 1100, above Drawer) -->
<div class="est-req-modal-overlay" id="estActionModal" onclick="if(event.target===this) window.estimateRequestsApp.closeModal()">
    <div class="est-req-modal-card" id="estActionModalCard" onclick="event.stopPropagation()">
        <!-- Injected dynamically -->
    </div>
</div>

<!-- Floating Row Action Dropdown Menu -->
<div class="est-req-row-dropdown" id="estimateRequestsRowDropdown"></div>

<!-- Toast Notifications Container -->
<div class="est-req-toast-container" id="estimateRequestsToastContainer"></div>

<?php include __DIR__ . '/includes/footer.php'; ?>

