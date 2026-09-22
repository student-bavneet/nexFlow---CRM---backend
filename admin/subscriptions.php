<?php
// Subscriptions Management Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$currentUser = require_admin_auth();
$organizationId = (int)$currentUser['organization_id'];

$page_title = "Subscriptions";
$current_page = "subscriptions";
$page_script = "subscriptions.js";

include __DIR__ . '/includes/header.php';
requirePermission('subscriptions');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content subscriptions-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="subscriptions-header">
            <div class="subscriptions-header-title-area">
                <div class="subscriptions-title-row">
                    <h1 class="subscriptions-header-title">Subscriptions</h1>
                    <span class="subscriptions-total-badge" id="subscriptionsTotalCountBadge">0 subscriptions</span>
                </div>
                <p class="subscriptions-header-subtitle">Manage customer plans, billing cycles, renewals and payment status.</p>
            </div>
            <div class="subscriptions-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.subscriptionsApp.openNewSubscriptionModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     New Subscription
                </button>
            </div>
        </div>

        <!-- SUBSCRIPTION STATUS OVERVIEW SECTION -->
        <div class="subscriptions-section-group" style="margin-bottom: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px;">Subscription Status Overview</div>
            
            <div class="subscriptions-status-grid">
                <div class="subscriptions-summary-card active" data-kpi="All" onclick="window.subscriptionsApp.filterByKpiCard('All', this)">
                    <div class="subscriptions-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/><path d="M7 15h.01M11 15h2"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiTotalSubs">0</span>
                        <span class="subscriptions-summary-lbl">Total Subscriptions</span>
                    </div>
                </div>

                <div class="subscriptions-summary-card" data-kpi="Active" onclick="window.subscriptionsApp.filterByKpiCard('Active', this)">
                    <div class="subscriptions-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiActiveSubs">0</span>
                        <span class="subscriptions-summary-lbl">Active Customers</span>
                    </div>
                </div>

                <div class="subscriptions-summary-card" data-kpi="Renewing Soon" onclick="window.subscriptionsApp.filterByKpiCard('Renewing Soon', this)">
                    <div class="subscriptions-summary-icon" style="background-color: #FFFAEB; color: #B54708;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 16 14"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiRenewSoon">0</span>
                        <span class="subscriptions-summary-lbl">Renewing Soon</span>
                    </div>
                </div>

                <div class="subscriptions-summary-card" data-kpi="Past Due" onclick="window.subscriptionsApp.filterByKpiCard('Past Due', this)">
                    <div class="subscriptions-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiPastDue">0</span>
                        <span class="subscriptions-summary-lbl">Past Due Accounts</span>
                    </div>
                </div>

                <div class="subscriptions-summary-card" data-kpi="Trial" onclick="window.subscriptionsApp.filterByKpiCard('Trial', this)">
                    <div class="subscriptions-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 16 14"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiTrialSubs">0</span>
                        <span class="subscriptions-summary-lbl">Trial</span>
                    </div>
                </div>

                <div class="subscriptions-summary-card" data-kpi="Paused" onclick="window.subscriptionsApp.filterByKpiCard('Paused', this)">
                    <div class="subscriptions-summary-icon" style="background-color: #FFF7ED; color: #EA580C;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiPausedSubs">0</span>
                        <span class="subscriptions-summary-lbl">Paused</span>
                    </div>
                </div>

                <div class="subscriptions-summary-card" data-kpi="Cancelled" onclick="window.subscriptionsApp.filterByKpiCard('Cancelled', this)">
                    <div class="subscriptions-summary-icon" style="background-color: #F1F5F9; color: #64748B;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiCancelledSubs">0</span>
                        <span class="subscriptions-summary-lbl">Cancelled</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FINANCIAL OVERVIEW SECTION -->
        <div class="subscriptions-section-group" style="margin-bottom: 24px;">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px;">Financial Overview</div>
            
            <div class="subscriptions-financial-grid">
                <div class="subscriptions-financial-card">
                    <div class="subscriptions-summary-icon" style="background-color: #F5F3FF; color: #7C3AED;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiMRR">$0</span>
                        <span class="subscriptions-summary-lbl">Monthly Recurring Revenue (MRR)</span>
                    </div>
                </div>

                <div class="subscriptions-financial-card">
                    <div class="subscriptions-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 6l-9.5 9.5-5-5L1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiARR">$0</span>
                        <span class="subscriptions-summary-lbl">Annual Recurring Revenue (ARR)</span>
                    </div>
                </div>

                <div class="subscriptions-financial-card">
                    <div class="subscriptions-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="subscriptions-summary-info">
                        <span class="subscriptions-summary-val" id="kpiARPU">$0</span>
                        <span class="subscriptions-summary-lbl">Average Revenue / Subscription</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aligned Single Control Toolbar: [ Search ] [ Filter ] [ Sort ] [ Customize Columns ] -->
        <div class="subscriptions-toolbar">
            <div class="subscriptions-toolbar-row">
                <!-- 1. Search Subscriptions Field -->
                <div class="subscriptions-search-wrapper">
                    <svg width="15" height="15" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" class="subscriptions-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm subscriptions-search-input" id="subSearchInput" placeholder="Search subscriptions, company, plan..." oninput="window.subscriptionsApp.handleSearch(this.value)">
                </div>

                <!-- 2. Filter Button & Popover -->
                <div class="subscriptions-filter-wrapper">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnSubFilter" onclick="window.subscriptionsApp.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filter</span>
                        <span class="filter-badge" id="subFilterBadge" style="display:none;"></span>
                    </button>

                    <div class="subscriptions-filter-popover" id="subscriptionsFilterPopover" onclick="event.stopPropagation()">
                        <div class="subscriptions-filter-header">
                            <div class="subscriptions-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Subscriptions</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.subscriptionsApp.clearFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="window.subscriptionsApp.closeFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="subscriptions-filter-body">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="form-label">Status</label>
                                <select class="input-control input-sm" id="filterSubStatus">
                                    <option value="All">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Trial">Trial</option>
                                    <option value="Past Due">Past Due</option>
                                    <option value="Paused">Paused</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="form-label">Plan</label>
                                <select class="input-control input-sm" id="filterSubPlan">
                                    <option value="All">All Plans</option>
                                    <option value="Enterprise Tier">Enterprise Tier</option>
                                    <option value="Growth Plan">Growth Plan</option>
                                    <option value="Professional Suite">Professional Suite</option>
                                    <option value="Starter Cloud">Starter Cloud</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Billing Cycle</label>
                                <select class="input-control input-sm" id="filterSubCycle">
                                    <option value="All">All Cycles</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div class="subscriptions-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.subscriptionsApp.applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- 3. Sort Select -->
                <div class="subscriptions-sort-wrapper">
                    <select class="input-control input-sm" id="subSortDropdown" onchange="window.subscriptionsApp.handleSort(this.value)">
                        <option value="name-asc">Sort: Name (A-Z)</option>
                        <option value="name-desc">Sort: Name (Z-A)</option>
                        <option value="amount-desc">Sort: Amount (High → Low)</option>
                        <option value="amount-asc">Sort: Amount (Low → High)</option>
                        <option value="date-asc">Sort: Next Billing (Soonest)</option>
                        <option value="date-desc">Sort: Next Billing (Latest)</option>
                    </select>
                </div>

                <!-- 4. Customize Columns Button (Moved from table area into toolbar) -->
                <div class="table-columns-dropdown-wrapper">
                    <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                        <span>Customize Columns</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Subscriptions Table Card -->
        <div class="subscriptions-table-card">
            <div class="subscriptions-table-wrapper">
                <table class="subscriptions-table crm-table" id="subscriptionsTable">
                    <thead>
                        <tr>
                            <th>Subscription</th>
                            <th>Customer / Company</th>
                            <th>Plan</th>
                            <th>Billing Cycle</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Next Billing Date</th>
                            <th>Renewal</th>
                            <th>Payment Method</th>
                            <th>Owner</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="subscriptionsTableBody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-container">
                <div>Showing <span style="font-weight: 600; color: var(--text-body);" id="pagingRange">0</span> of <span style="font-weight: 600; color: var(--text-body);" id="pagingTotal">0</span></div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Details Drawer Overlay & Panel -->
<div class="subscriptions-drawer-overlay" id="subscriptionsDrawer" onclick="if(event.target===this) window.subscriptionsApp.closeDrawer()">
    <div class="subscriptions-drawer-panel" onclick="event.stopPropagation()">
        
        <div class="subscriptions-drawer-header">
            <div class="subscriptions-drawer-title-area">
                <span class="subscriptions-drawer-subtitle" id="subDrawerCompany"></span>
                <h3 class="subscriptions-drawer-title" id="subDrawerTitle"></h3>
            </div>
            <div class="subscriptions-drawer-header-actions">
                <span class="badge-sub badge-active" id="subDrawerBadge" style="cursor:pointer;" title="Click to change status" onclick="window.subscriptionsApp.openStatusChangeModal(window.subscriptionsApp.activeSubId)"></span>
                <button type="button" class="btn btn-ghost btn-xs" id="subDrawerShareBtn" title="Share Subscription" aria-label="Share Subscription" onclick="window.NexFlowShare.openSubscriptionShare(window.subscriptionsApp?.activeSubId, this)">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeDrawer()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div class="subscriptions-drawer-tabs">
            <button type="button" class="subscriptions-drawer-tab active" data-tab="overview" onclick="window.subscriptionsApp.switchDrawerTab('overview')">Overview</button>
            <button type="button" class="subscriptions-drawer-tab" data-tab="billing" onclick="window.subscriptionsApp.switchDrawerTab('billing')">Billing</button>
            <button type="button" class="subscriptions-drawer-tab" data-tab="invoices" onclick="window.subscriptionsApp.switchDrawerTab('invoices')">Invoices</button>
            <button type="button" class="subscriptions-drawer-tab" data-tab="activity" onclick="window.subscriptionsApp.switchDrawerTab('activity')">Activity</button>
            <button type="button" class="subscriptions-drawer-tab" data-tab="notes" onclick="window.subscriptionsApp.switchDrawerTab('notes')">Notes</button>
        </div>

        <div class="subscriptions-drawer-body" id="subDrawerBody">
            <div class="sub-tab-panel active" id="subscription-tab-overview"></div>
            <div class="sub-tab-panel" id="subscription-tab-billing" style="display:none;"></div>
            <div class="sub-tab-panel" id="subscription-tab-invoices" style="display:none;"></div>
            <div class="sub-tab-panel" id="subscription-tab-activity" style="display:none;"></div>
            <div class="sub-tab-panel" id="subscription-tab-notes" style="display:none;"></div>
        </div>

        <div class="subscriptions-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeDrawer()">Close</button>
        </div>

    </div>
</div>

<!-- Top-Level Child Modals Overlay (z-index: 1100, above Drawer) -->
<div class="subscriptions-modal-overlay" id="subActionModal" onclick="if(event.target===this) window.subscriptionsApp.closeModal()">
    <div class="subscriptions-modal-card" id="subActionModalCard" onclick="event.stopPropagation()">
        <!-- Injected dynamically -->
    </div>
</div>

<!-- Floating Row Action Dropdown Menu -->
<div class="subscriptions-row-dropdown" id="subscriptionsRowDropdown"></div>

<!-- Toast Notifications Container -->
<div class="subscriptions-toast-container" id="subscriptionsToastContainer"></div>

<?php include __DIR__ . '/includes/footer.php'; ?>

