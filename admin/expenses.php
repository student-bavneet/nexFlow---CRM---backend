<?php
// Expenses Management Page
require_once __DIR__ . '/data/mock-data.php';

$page_title = "Expenses";
$current_page = "expenses";
$page_script = "expenses.js";

include __DIR__ . '/includes/header.php';
requirePermission('expenses');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content expenses-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="expenses-header">
            <div class="expenses-header-title-area">
                <div class="expenses-title-row">
                    <h1 class="expenses-header-title">Expenses</h1>
                    <span class="expenses-total-badge" id="expensesTotalCountBadge">0 expenses</span>
                </div>
                <p class="expenses-header-subtitle">Track business spending, billable costs and reimbursement activity.</p>
            </div>
            <div class="expenses-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.openImportModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Import Expenses
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.expensesApp.openRecordModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Record Expense
                </button>
            </div>
        </div>

        <!-- 5-Card Summary Bar -->
        <div class="expenses-summary-bar">
            
            <div class="expenses-summary-card" id="cardTotalExpenses">
                <div class="expenses-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="expenses-summary-info">
                    <span class="expenses-summary-val" id="kpiTotalExpenses">$0.00</span>
                    <span class="expenses-summary-lbl">Total Expenses</span>
                </div>
            </div>

            <div class="expenses-summary-card" id="cardBillable">
                <div class="expenses-summary-icon" style="background-color: #EFF6FF; color: #1D4ED8;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="expenses-summary-info">
                    <span class="expenses-summary-val" id="kpiBillable">$0.00</span>
                    <span class="expenses-summary-lbl">Billable to Clients</span>
                </div>
            </div>

            <div class="expenses-summary-card" id="cardNonBillable">
                <div class="expenses-summary-icon" style="background-color: #F1F5F9; color: #475569;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="expenses-summary-info">
                    <span class="expenses-summary-val" id="kpiNonBillable">$0.00</span>
                    <span class="expenses-summary-lbl">Non-Billable Internal</span>
                </div>
            </div>

            <div class="expenses-summary-card" id="cardPendingInvoice">
                <div class="expenses-summary-icon" style="background-color: #FFFBEB; color: #B45309;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="expenses-summary-info">
                    <span class="expenses-summary-val" id="kpiNotInvoiced">$0.00</span>
                    <span class="expenses-summary-lbl">Pending Invoice</span>
                </div>
            </div>

            <div class="expenses-summary-card" id="cardInvoiced">
                <div class="expenses-summary-icon" style="background-color: #ECFDF5; color: #047857;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>
                </div>
                <div class="expenses-summary-info">
                    <span class="expenses-summary-val" id="kpiInvoiced">$0.00</span>
                    <span class="expenses-summary-lbl">Invoiced to Clients</span>
                </div>
            </div>

        </div>

        <!-- Single-Row Toolbar (Search -> Filter -> All Owners -> Newest Date -> Customize Columns) -->
        <div class="expenses-toolbar">
            <div class="expenses-toolbar-row1" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                
                <!-- 1. Search Input (~380px width) -->
                <div style="position: relative; width: 380px; max-width: 100%;">
                    <svg width="15" height="15" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm" id="expSearchInput" placeholder="Search expense, merchant, company..." style="padding-left: 34px; height: 36px; font-size: 13px;" oninput="window.expensesApp.handleSearch(this.value)">
                </div>

                <!-- 2. Filter Button & Popover -->
                <div style="position: relative;">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnExpFilter" style="height: 36px;" onclick="window.expensesApp.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filter</span>
                        <span id="filterActiveBadge" class="filter-active-dot" style="display:none;width:6px;height:6px;border-radius:50%;background:#0284C7;margin-left:2px;"></span>
                    </button>

                    <div class="expenses-filter-popover" id="expensesFilterPopover" onclick="event.stopPropagation()">
                        <div class="expenses-filter-header">
                            <div class="expenses-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Expenses</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.expensesApp.clearFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="window.expensesApp.closeFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="expenses-filter-body" style="display:flex;flex-direction:column;gap:12px;padding:16px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Billing Type</label>
                                <select class="input-control input-sm" id="filterExpBillable">
                                    <option value="All">All Types</option>
                                    <option value="Billable">Billable</option>
                                    <option value="Non-Billable">Non-Billable</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Invoice Status</label>
                                <select class="input-control input-sm" id="filterExpInvoiceStatus">
                                    <option value="All">All Statuses</option>
                                    <option value="Not Invoiced">Not Invoiced</option>
                                    <option value="Invoiced">Invoiced</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Reimbursement Status</label>
                                <select class="input-control input-sm" id="filterExpReimbursedStatus">
                                    <option value="All">All Statuses</option>
                                    <option value="Pending">Pending Reimbursement</option>
                                    <option value="Reimbursed">Reimbursed</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Category</label>
                                <select class="input-control input-sm" id="filterExpCategory">
                                    <option value="All">All Categories</option>
                                    <option value="Travel">Travel &amp; Lodging</option>
                                    <option value="Meals">Client Entertainment &amp; Meals</option>
                                    <option value="Software">Software &amp; Cloud Services</option>
                                    <option value="Telephone">Telephone &amp; Telephony</option>
                                    <option value="Office Supplies">Office Supplies &amp; Hardware</option>
                                    <option value="Advertising">Marketing &amp; Advertising</option>
                                    <option value="Automobile">Automobile &amp; Rental</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Date Range</label>
                                <select class="input-control input-sm" id="filterExpDatePreset">
                                    <option value="All">All Time</option>
                                    <option value="Today">Today</option>
                                    <option value="This Week">This Week</option>
                                    <option value="This Month">This Month</option>
                                    <option value="Last Month">Last Month</option>
                                </select>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                <div>
                                    <label class="form-label">Min Amount ($)</label>
                                    <input type="number" step="0.01" class="input-control input-sm" id="filterExpMinAmount" placeholder="0.00">
                                </div>
                                <div>
                                    <label class="form-label">Max Amount ($)</label>
                                    <input type="number" step="0.01" class="input-control input-sm" id="filterExpMaxAmount" placeholder="10000.00">
                                </div>
                            </div>
                        </div>
                        <div class="expenses-filter-footer" style="padding:12px 16px;border-top:1px solid var(--border-divider);display:flex;justify-content:flex-end;gap:8px;background:#F8FAFC;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.expensesApp.applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- 3. All Owners Dropdown -->
                <select class="input-control input-sm" style="width: 160px; height: 36px; font-size: 13px;" id="expOwnerDropdown" onchange="window.expensesApp.handleOwnerFilter(this.value)">
                    <option value="All">All Owners</option>
                    <option value="Olivia Martin">Olivia Martin</option>
                    <option value="Daniel Reyes">Daniel Reyes</option>
                    <option value="Priya Sharma">Priya Sharma</option>
                </select>

                <!-- 4. Sort Dropdown -->
                <select class="input-control input-sm" style="width: 170px; height: 36px; font-size: 13px;" id="expSortDropdown" onchange="window.expensesApp.handleSort(this.value)">
                    <option value="date-desc">Newest Date</option>
                    <option value="date-asc">Oldest Date</option>
                    <option value="name-asc">Expense Name A–Z</option>
                    <option value="name-desc">Expense Name Z–A</option>
                    <option value="amount-desc">Amount High → Low</option>
                    <option value="amount-asc">Amount Low → High</option>
                    <option value="category-asc">Category A–Z</option>
                    <option value="company-asc">Company A–Z</option>
                </select>

                <!-- 5. Customize Columns Button & Popover -->
                <div class="table-columns-dropdown-wrapper" style="position: relative;">
                    <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns" style="height: 36px;" onclick="window.expensesApp.toggleColumnsPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                        <span>Customize Columns</span>
                    </button>
                    
                    <div class="table-columns-dropdown-menu" id="expensesColumnsPopover" onclick="event.stopPropagation()">
                        <div class="table-columns-dropdown-header">Reorder &amp; Visibility</div>
                        <div class="table-columns-list" id="expenseColumnList">
                            <!-- Populated dynamically by JavaScript -->
                        </div>
                        <div class="table-columns-dropdown-footer">
                            <button type="button" class="table-columns-footer-btn btn-show-all" onclick="window.expensesApp.showAllColumns()">Show All</button>
                            <button type="button" class="table-columns-footer-btn btn-hide-optional" onclick="window.expensesApp.hideOptionalColumns()">Hide Optional</button>
                            <button type="button" class="table-columns-footer-btn btn-reset" onclick="window.expensesApp.resetColumns()">Reset</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Active Filter Notice Bar -->
        <div id="activeFilterNotice" style="display:none;margin-bottom:14px;padding:8px 14px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;font-size:12.5px;color:#1D4ED8;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:6px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <span id="activeFilterNoticeText">Active Filter applied</span>
            </div>
            <button type="button" style="background:none;border:none;color:#1D4ED8;font-weight:600;cursor:pointer;font-size:12px;" onclick="window.expensesApp.clearFilters()">Clear Filters ✕</button>
        </div>

        <!-- Expenses Table Card -->
        <div class="expenses-table-card">
            <div class="expenses-table-wrapper">
                <table class="expenses-table crm-table" id="expensesTable" data-column-manager="disabled">
                    <thead id="expensesTableHead">
                        <!-- Populated by JavaScript according to column settings -->
                    </thead>
                    <tbody id="expensesTableBody">
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

<!-- Details Drawer Overlay & Panel -->
<div class="drawer-overlay" id="expensesDrawer" onclick="if(event.target===this) window.expensesApp.closeDrawer()">
    <div class="drawer-content" id="expensesDrawerPanel" onclick="event.stopPropagation()">

        <!-- Sticky Header -->
        <div class="drawer-header">
            <div class="exp-drawer-title-area">
                <div class="drawer-breadcrumb" id="expDrawerSubtitle">Expenses / Details</div>
                <h3 class="drawer-title" id="expDrawerTitle">Expense Title</h3>
            </div>
            <div class="drawer-header-actions">
                <!-- Status Badge -->
                <span class="badge-exp badge-notinvoiced" id="expDrawerBadge">● Not Invoiced</span>
                <!-- Edit Icon Button -->
                <button class="btn btn-ghost btn-xs" id="expDrawerEditBtn" title="Edit Expense" onclick="window.expensesApp.openEditModal(window.expensesApp.getActiveExpenseId())">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <!-- Three-Dot Menu -->
                <div class="drawer-dropdown-wrapper">
                    <button class="btn btn-ghost btn-xs" id="expDrawerMoreBtn" title="More Actions" onclick="window.expensesApp.toggleDrawerMoreMenu(event)">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    </button>
                    <div class="drawer-header-menu" id="drawerMoreMenu">
                        <!-- Populated dynamically -->
                    </div>
                </div>
                <!-- Close Button -->
                <button class="modal-close-btn" onclick="window.expensesApp.closeDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Sticky Tab Row -->
        <div class="drawer-tabs-wrapper">
            <div class="drawer-tabs" id="expDrawerTabsContainer">
                <button class="drawer-tab active" data-tab="overview" onclick="window.expensesApp.switchDrawerTab('overview')">Overview</button>
                <button class="drawer-tab" data-tab="receipt" onclick="window.expensesApp.switchDrawerTab('receipt')">Receipt</button>
                <button class="drawer-tab" data-tab="activity" onclick="window.expensesApp.switchDrawerTab('activity')">Activity</button>
            </div>
        </div>

        <!-- Drawer Content Body -->
        <div class="drawer-body" id="expDrawerBody">
            <!-- Dynamic tab content populated by JS -->
        </div>

    </div>
</div>


<!-- Top-Level Child Modals Overlay (z-index: 1100) -->
<div class="expenses-modal-overlay" id="expActionModal" onclick="if(event.target===this) window.expensesApp.closeModal()">
    <div class="expenses-modal-card" id="expActionModalCard" onclick="event.stopPropagation()">
        <!-- Injected dynamically -->
    </div>
</div>

<!-- Floating Row Action Dropdown Menu -->
<div class="expenses-row-dropdown" id="expensesRowDropdown"></div>

<!-- Toast Notifications Container -->
<div class="expenses-toast-container" id="expensesToastContainer"></div>

<?php include __DIR__ . '/includes/footer.php'; ?>
