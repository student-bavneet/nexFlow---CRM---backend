<?php
// Contracts Management Page

$page_title = "Contracts";
$current_page = "contracts";
$page_script = "contracts.js";

include __DIR__ . '/includes/header.php';
requirePermission('contracts');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
<script>
window.adminCsrfToken = <?php echo json_encode(admin_csrf_token()); ?>;
</script>

<main class="main-content contracts-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="contracts-header">
            <div class="contracts-header-title-area">
                <div class="contracts-title-row">
                    <h1 class="contracts-header-title">Contracts</h1>
                    <span class="contracts-total-badge" id="contractsTotalCountBadge">0 contracts</span>
                </div>
                <p class="contracts-header-subtitle">Create, track and manage customer agreements and renewals.</p>
            </div>
            <div class="contracts-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.contractsApp.openNewContractModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     New Contract
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Bar -->
        <div class="contracts-summary-bar">
            
            <div class="contracts-summary-card" onclick="window.contractsApp.filterByKPI('Active')">
                <div class="contracts-summary-icon" style="background-color: #ECFDF5; color: #047857;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="contracts-summary-info">
                    <span class="contracts-summary-val" id="kpiActiveContracts">0</span>
                    <span class="contracts-summary-lbl">Active Contracts</span>
                </div>
            </div>

            <div class="contracts-summary-card" onclick="window.contractsApp.filterByKPI('Expiring Soon')">
                <div class="contracts-summary-icon" style="background-color: #FFF7ED; color: #C2410C;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="contracts-summary-info">
                    <span class="contracts-summary-val" id="kpiExpiringSoon">0</span>
                    <span class="contracts-summary-lbl">Expiring Soon (30d)</span>
                </div>
            </div>

            <div class="contracts-summary-card" onclick="window.contractsApp.filterByKPI('Draft')">
                <div class="contracts-summary-icon" style="background-color: #F1F5F9; color: #475569;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div class="contracts-summary-info">
                    <span class="contracts-summary-val" id="kpiDraftContracts">0</span>
                    <span class="contracts-summary-lbl">Draft Agreements</span>
                </div>
            </div>

            <div class="contracts-summary-card" onclick="window.contractsApp.filterByKPI('Pending Signature')">
                <div class="contracts-summary-icon" style="background-color: #FFFBEB; color: #B45309;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </div>
                <div class="contracts-summary-info">
                    <span class="contracts-summary-val" id="kpiPendingSignature">0</span>
                    <span class="contracts-summary-lbl">Pending Signature</span>
                </div>
            </div>

            <div class="contracts-summary-card" onclick="window.contractsApp.filterByTab('All')">
                <div class="contracts-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="contracts-summary-info">
                    <span class="contracts-summary-val" id="kpiTotalContractValue">0.00</span>
                    <span class="contracts-summary-lbl">Total Contract Value</span>
                </div>
            </div>

        </div>

        <!-- 2 Compact Analytics Chart Cards -->
        <div class="contracts-analytics-row">
            <div class="contracts-chart-card">
                <div class="contracts-chart-header">
                    <h3 class="contracts-chart-title">Contracts by Type</h3>
                    <span style="font-size:11px;color:var(--text-secondary);">Count</span>
                </div>
                <div class="contracts-chart-container">
                    <canvas id="contractsTypeChart"></canvas>
                </div>
            </div>

            <div class="contracts-chart-card">
                <div class="contracts-chart-header">
                    <h3 class="contracts-chart-title">Contract Value by Type</h3>
                    <span style="font-size:11px;color:var(--text-secondary);">Volume ($)</span>
                </div>
                <div class="contracts-chart-container">
                    <canvas id="contractsValueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Aligned Responsive Toolbar -->
        <div class="contracts-toolbar">
            <div class="contracts-toolbar-row1" style="display: flex; align-items: center; gap: 10px; width: 100%; flex-wrap: wrap;">
                
                <!-- 1. Search Field -->
                <div style="position: relative; flex: 1; min-width: 220px;">
                    <svg width="15" height="15" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm" id="cntSearchInput" placeholder="Search contract, ref, company..." style="padding-left: 32px; width: 100%;" oninput="window.contractsApp.handleSearch(this.value)">
                </div>

                <!-- 2. Filter Button -->
                <div style="position: relative;">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnCntFilter" onclick="window.contractsApp.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filter</span>
                    </button>

                    <div class="contracts-filter-popover" id="contractsFilterPopover" onclick="event.stopPropagation()">
                        <div class="contracts-filter-header">
                            <div class="contracts-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Contracts</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.contractsApp.clearFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="window.contractsApp.closeFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="contracts-filter-body">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Contract Type</label>
                                <select class="input-control input-sm" id="filterCntType">
                                    <option value="All">All Types</option>
                                    <option value="Master Services Agreement">Master Services Agreement (MSA)</option>
                                    <option value="Statement of Work">Statement of Work (SOW)</option>
                                    <option value="Non-Disclosure Agreement">Non-Disclosure Agreement (NDA)</option>
                                    <option value="Software License Agreement">Software License Agreement</option>
                                    <option value="Service Level Agreement">Service Level Agreement (SLA)</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Status</label>
                                <select class="input-control input-sm" id="filterCntStatus">
                                    <option value="All">All Statuses</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Sent">Sent</option>
                                    <option value="Viewed">Viewed</option>
                                    <option value="Pending Signature">Pending Signature</option>
                                    <option value="Changes Requested">Changes Requested</option>
                                    <option value="Signed">Signed</option>
                                    <option value="Active">Active</option>
                                    <option value="Expiring Soon">Expiring Soon</option>
                                    <option value="Expired">Expired</option>
                                    <option value="Declined">Declined</option>
                                </select>
                            </div>
                        </div>
                        <div class="contracts-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.contractsApp.applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- 3. View: All Contracts Dropdown -->
                <select class="input-control input-sm" style="width: 165px;" id="cntSavedViewDropdown" onchange="window.contractsApp.handleSavedView(this.value)">
                    <option value="all">View: All Contracts</option>
                    <option value="active">View: Active Only</option>
                    <option value="expiring">View: Expiring (30d)</option>
                    <option value="high_val">View: High Value (&gt;$20k)</option>
                </select>

                <!-- 4. All Statuses Dropdown -->
                <select class="input-control input-sm" style="width: 175px;" id="cntStatusSelect" onchange="window.contractsApp.filterByStatus(this.value)">
                    <option value="All" id="optCntStatus_All">All Statuses</option>
                    <option value="Draft" id="optCntStatus_Draft">Draft</option>
                    <option value="Sent" id="optCntStatus_Sent">Sent</option>
                    <option value="Viewed" id="optCntStatus_Viewed">Viewed</option>
                    <option value="Pending Signature" id="optCntStatus_PendingSignature">Pending Signature</option>
                    <option value="Changes Requested" id="optCntStatus_ChangesRequested">Changes Requested</option>
                    <option value="Signed" id="optCntStatus_Signed">Signed</option>
                    <option value="Active" id="optCntStatus_Active">Active</option>
                    <option value="Expiring Soon" id="optCntStatus_ExpiringSoon">Expiring Soon</option>
                    <option value="Expired" id="optCntStatus_Expired">Expired</option>
                    <option value="Declined" id="optCntStatus_Declined">Declined</option>
                </select>

                <!-- 5. Sort: Name (A-Z) Dropdown -->
                <select class="input-control input-sm" style="width: 165px;" id="cntSortDropdown" onchange="window.contractsApp.handleSort(this.value)">
                    <option value="name-asc">Sort: Name (A-Z)</option>
                    <option value="name-desc">Sort: Name (Z-A)</option>
                    <option value="value-desc">Sort: Highest Value</option>
                    <option value="value-asc">Sort: Lowest Value</option>
                    <option value="date-asc">Sort: Ending Soonest</option>
                </select>

                <!-- 6. Customize Columns Button -->
                <div class="table-columns-dropdown-wrapper">
                    <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                        Customize Columns
                    </button>
                </div>

            </div>
        </div>

        <!-- Contracts Table Card -->
        <div class="contracts-table-card">
            <div class="contracts-table-wrapper">
                <table class="contracts-table crm-table" id="contractsTable">
                    <thead>
                        <tr>
                            <th>Contract</th>
                            <th>Company</th>
                            <th>Contract Type</th>
                            <th>Contract Value</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Signature</th>
                            <th>Owner</th>
                            <th>Last Activity</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contractsTableBody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-container">
                <div>Showing <span style="font-weight: 600; color: var(--text-body);" id="pagingRange">1–8</span> of <span style="font-weight: 600; color: var(--text-body);" id="pagingTotal">0</span></div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Details Drawer Overlay & Panel (width: 540px, z-index: 1010) -->
<div class="contracts-drawer-overlay" id="contractsDrawer" onclick="if(event.target===this) window.contractsApp.closeDrawer()">
    <div class="contracts-drawer-panel" onclick="event.stopPropagation()">
        
        <!-- Sticky Header -->
        <div class="contracts-drawer-header">
            <div class="contracts-drawer-title-area">
                <span class="contracts-drawer-subtitle" id="cntDrawerSubtitle">—</span>
                <h3 class="contracts-drawer-title" id="cntDrawerTitle">Contract Details</h3>
            </div>
            <div class="contracts-drawer-header-actions">
                <span class="badge-contract badge-active" id="cntDrawerBadge">● Active</span>
                <button type="button" class="btn btn-ghost btn-xs" id="cntDrawerShareBtn" title="Share Contract" aria-label="Share Contract" onclick="event.stopPropagation(); if(window.NexFlowShare && window.NexFlowShare.openContractShare) { window.NexFlowShare.openContractShare(window.contractsApp?.activeContractId, this); }">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <button type="button" class="btn btn-ghost btn-xs" id="cntDrawerEditBtn" title="Edit Contract" aria-label="Edit Contract" onclick="event.stopPropagation(); window.contractsApp.openEditModal(window.contractsApp?.activeContractId)">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <div class="drawer-dropdown-wrapper" style="position: relative;">
                    <button type="button" class="btn btn-ghost btn-xs" id="cntDrawerMoreBtn" title="More Actions" aria-label="More Actions" onclick="event.stopPropagation(); window.contractsApp.toggleDrawerMoreMenu(event)">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    </button>
                    <div class="contracts-header-menu" id="cntDrawerMoreMenu">
                        <!-- Populated dynamically -->
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="event.stopPropagation(); window.contractsApp.closeDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Sticky Tabs Row -->
        <div class="contracts-drawer-tabs" id="cntDrawerTabsContainer">
            <button type="button" class="contracts-drawer-tab active" data-tab="overview" onclick="window.contractsApp.switchDrawerTab('overview')">Overview</button>
            <button type="button" class="contracts-drawer-tab" data-tab="parties" onclick="window.contractsApp.switchDrawerTab('parties')">Parties</button>
            <button type="button" class="contracts-drawer-tab" data-tab="timeline" onclick="window.contractsApp.switchDrawerTab('timeline')">Timeline</button>
            <button type="button" class="contracts-drawer-tab" data-tab="renewals" onclick="window.contractsApp.switchDrawerTab('renewals')">Renewals</button>
            <button type="button" class="contracts-drawer-tab" data-tab="files" onclick="window.contractsApp.switchDrawerTab('files')">Files</button>
            <button type="button" class="contracts-drawer-tab" data-tab="notes" onclick="window.contractsApp.switchDrawerTab('notes')">Notes</button>
        </div>

        <!-- Drawer Content Body -->
        <div class="contracts-drawer-body" id="cntDrawerBody">
            <!-- Dynamically populated -->
        </div>

        <!-- Drawer Footer -->
        <div class="contracts-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.closeDrawer()">Close</button>
        </div>

    </div>
</div>

<!-- Top-Level Child Modals Overlay (z-index: 1100, above Drawer) -->
<div class="contracts-modal-overlay" id="cntActionModal" onclick="if(event.target===this) window.contractsApp.closeModal()">
    <div class="contracts-modal-card" id="cntActionModalCard" onclick="event.stopPropagation()">
        <!-- Injected dynamically -->
    </div>
</div>

<!-- ADMIN MODAL: SEND CONTRACT CONFIRMATION -->
<div class="modal-overlay" id="adminSendContractModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); z-index:1100; align-items:center; justify-content:center;" onclick="if(event.target===this) window.contractsApp.closeSendModal()">
    <div style="background:#FFF; border-radius:12px; width:100%; max-width:440px; padding:24px; box-shadow:var(--shadow-xl); border:1px solid var(--border-card);" onclick="event.stopPropagation()">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="font-size:16px; font-weight:700; color:var(--text-heading); margin:0;" id="ascModalTitle">Send Contract?</h3>
            <button type="button" class="modal-close-btn" onclick="window.contractsApp.closeSendModal()" aria-label="Close modal">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13.5px; color:var(--text-secondary); margin:0 0 16px 0;" id="ascModalSub">Are you sure you want to send this contract to the client?</p>
        <div style="background:var(--bg-hover); border:1px solid var(--border-card); border-radius:var(--radius-md); padding:12px 14px; font-size:12.5px; color:var(--text-heading); margin-bottom:20px;">
            <div style="margin-bottom:4px;"><strong>Contract:</strong> <span id="ascCntTitle">—</span></div>
            <div style="margin-bottom:4px;"><strong>Company:</strong> <span id="ascCntCompany">—</span></div>
            <div><strong>Contract Value:</strong> <span id="ascCntValue">—</span></div>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.closeSendModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnConfirmSendContract" onclick="window.contractsApp.confirmSendContract()">Send Contract</button>
        </div>
    </div>
</div>

<!-- Floating Row Action Dropdown Menu -->
<div class="contracts-row-dropdown" id="contractsRowDropdown"></div>

<!-- Toast Notifications Container -->
<div class="contracts-toast-container" id="contractsToastContainer"></div>

<?php include __DIR__ . '/includes/footer.php'; ?>

