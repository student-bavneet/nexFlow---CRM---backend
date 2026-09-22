<?php
// Invoices Management Page

$page_title = "Invoices";
$current_page = "invoices";
$page_script = "invoices.js";

include __DIR__ . '/includes/header.php';
requirePermission('invoices');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content invoices-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Invoices</h1>
                    <span class="projects-total-badge" id="invoicesTotalBadge">0 invoices</span>
                </div>
                <p class="projects-header-subtitle">Manage invoices, payment status, due dates, and outstanding balances.</p>
            </div>
            <div class="projects-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.invoicesApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.invoicesApp.openCreateDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Create Invoice
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Grid -->
        <div class="projects-summary-bar">
            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16l3-2 3 2 3-2 3 2 4-2.5V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiTotalInvoiced">0</span>
                    <span class="projects-summary-lbl">Total Invoiced</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiPaid">0</span>
                    <span class="projects-summary-lbl">Paid</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiOutstanding">0</span>
                    <span class="projects-summary-lbl">Outstanding</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiOverdue">0</span>
                    <span class="projects-summary-lbl">Overdue</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiDraftPending">0 Draft</span>
                    <span class="projects-summary-lbl">Draft / Pending</span>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="projects-toolbar">
            <div class="projects-toolbar-left">
                <div class="projects-search-wrapper">
                    <svg class="projects-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="projects-search-input" id="invoiceSearchInput" placeholder="Search invoices..." oninput="window.invoicesApp.handleSearch(this.value)">
                </div>

                <!-- Filter Button & Popover -->
                <div style="position: relative;">
                    <button class="btn btn-secondary btn-sm" id="btnInvoiceFilter" type="button" onclick="window.invoicesApp.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filter</span>
                    </button>

                    <div class="invoices-filter-popover" id="invoicesFilterPopover" onclick="event.stopPropagation()">
                        <div class="invoices-filter-header">
                            <div class="invoices-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Invoices</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.invoicesApp.clearFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="window.invoicesApp.closeFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="invoices-filter-body">
                            <div>
                                <label class="form-label">Customer</label>
                                <select class="input-control input-sm" id="filterCustomer">
                                    <option value="All">All Customers</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Assigned Owner</label>
                                <select class="input-control input-sm" id="filterOwner">
                                    <option value="All">All Owners</option>
                                </select>
                            </div>
                        </div>
                        <div class="invoices-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.invoicesApp.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.invoicesApp.applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- Sort Selector -->
                <select class="input-control input-sm" style="width: 170px;" id="invoiceSortSelect" onchange="window.invoicesApp.handleSort(this.value)">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="amount-desc">Amount: High to Low</option>
                    <option value="amount-asc">Amount: Low to High</option>
                </select>

                <!-- Status Filter Selector -->
                <select class="input-control input-sm" style="width: 170px;" id="invoiceStatusSelect" onchange="window.invoicesApp.filterByStatus(this.value)">
                    <option value="All" id="optStatus_All">All Invoices (0)</option>
                    <option value="Draft" id="optStatus_Draft">Draft (0)</option>
                    <option value="Sent" id="optStatus_Sent">Sent (0)</option>
                    <option value="Viewed" id="optStatus_Viewed">Viewed (0)</option>
                    <option value="Pending" id="optStatus_Pending">Pending (0)</option>
                    <option value="Partially Paid" id="optStatus_PartiallyPaid">Partially Paid (0)</option>
                    <option value="Paid" id="optStatus_Paid">Paid (0)</option>
                    <option value="Overdue" id="optStatus_Overdue">Overdue (0)</option>
                    <option value="Cancelled" id="optStatus_Cancelled">Cancelled (0)</option>
                </select>
            </div>

            <!-- Customize Columns -->
            <div class="table-columns-dropdown-wrapper">
                <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                    Customize Columns
                </button>
            </div>
        </div>

        <!-- Invoices Table Card -->
        <div class="card">
            <div class="crm-table-wrapper">
                <table class="crm-table" id="invoicesTable">
                    <thead>
                        <tr>
                            <th data-protected="true" data-column-id="number">INVOICE #</th>
                            <th data-column-id="customer">CUSTOMER</th>
                            <th data-column-id="deal">RELATED PROJECT / DEAL</th>
                            <th data-column-id="issue-date">ISSUE DATE</th>
                            <th data-column-id="due-date">DUE DATE</th>
                            <th data-column-id="total">TOTAL</th>
                            <th data-column-id="paid">AMOUNT PAID</th>
                            <th data-column-id="balance">BALANCE DUE</th>
                            <th data-column-id="status">STATUS</th>
                            <th data-column-id="owner">OWNER</th>
                            <th style="text-align: right;" data-protected="true" data-column-id="actions">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesTbody">
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

<!-- Create / Edit Invoice Right-Side Drawer (width: 680px, z-index: 1010) -->
<div class="drawer-overlay" id="invoiceFormDrawer" onclick="if(event.target===this) window.invoicesApp.closeFormDrawer()">
    <div class="drawer-content" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <div>
                <h3 class="drawer-title" id="formInvDrawerTitle"> Create New Invoice</h3>
            </div>
            <button class="modal-close-btn" onclick="window.invoicesApp.closeFormDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="drawer-body">
            <!-- 1. Invoice Details -->
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin: 0 0 12px 0; border-bottom: 1px solid var(--border-divider); padding-bottom: 6px;">
                    1. Invoice Details
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Invoice Number *</label>
                        <input type="text" class="input-control" id="formInvNumber" required>
                    </div>
                    <div>
                        <label class="form-label">Customer Name *</label>
                        <input type="text" class="input-control" id="formInvCustomer" list="invoiceCompanyList" placeholder="Select or enter customer..." required autocomplete="off">
                        <datalist id="invoiceCompanyList"></datalist>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Contact Person</label>
                        <input type="text" class="input-control" id="formInvContact" list="invoiceContactList" placeholder="Contact person..." autocomplete="off">
                        <datalist id="invoiceContactList"></datalist>
                    </div>
                    <div>
                        <label class="form-label">Related Project / Deal</label>
                        <input type="text" class="input-control" id="formInvDeal" list="invoiceDealProjectList" placeholder="e.g. E-Commerce Platform" autocomplete="off">
                        <datalist id="invoiceDealProjectList"></datalist>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px;">
                    <div>
                        <label class="form-label">Issue Date</label>
                        <input type="date" class="input-control" id="formInvIssueDate" required>
                    </div>
                    <div>
                        <label class="form-label">Due Date</label>
                        <input type="date" class="input-control" id="formInvDueDate" required>
                    </div>
                    <div>
                        <label class="form-label">Terms</label>
                        <select class="input-control" id="formInvTerms">
                            <option value="Net 30" selected>Net 30</option>
                            <option value="Net 15">Net 15</option>
                            <option value="Due on Receipt">Due on Receipt</option>
                            <option value="Net 60">Net 60</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Currency</label>
                        <select class="input-control" id="formInvCurrency">
                        </select>
                    </div>
                </div>
            </div>

            <!-- 2. Line Items Table -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin: 0;">
                        2. Itemized Deliverables
                    </h4>
                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.invoicesApp.addFormLineItem()">
                        + Add Item
                    </button>
                </div>

                <div style="border: 1px solid var(--border-divider); border-radius: 8px; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border-divider);">
                            <tr>
                                <th style="padding: 8px 10px; text-align: left;">Item Description</th>
                                <th style="padding: 8px; text-align: center; width: 60px;">Qty</th>
                                <th style="padding: 8px; text-align: right; width: 90px;" id="thRateHeader">Rate</th>
                                <th style="padding: 8px; text-align: right; width: 100px;" id="thAmountHeader">Amount</th>
                                <th style="padding: 8px; text-align: center; width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="formItemsTbody">
                            <!-- Items added dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. Invoice Summary Card -->
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin: 0 0 10px 0;">
                    3. Invoice Summary
                </h4>
                <div style="background: #F8FAFC; border: 1px solid var(--border-divider); border-radius: 8px; padding: 14px; display: flex; justify-content: flex-end;">
                    <div style="width: 270px; display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                        <div style="display: flex; justify-content: space-between; color: var(--text-secondary);">
                            <span>Subtotal:</span>
                            <strong id="formSubtotalVal" style="color: var(--text-heading);">$0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; color: var(--text-secondary); align-items: center;">
                            <span id="lblFormDiscount">Discount:</span>
                            <input type="number" id="formDiscountInput" min="0" step="any" class="input-control input-sm" style="width: 90px; text-align: right;" value="0" oninput="window.invoicesApp.recalculateFormTotals()">
                        </div>
                        <div style="display: flex; justify-content: space-between; color: var(--text-secondary); align-items: center;">
                            <span id="lblFormTax">Tax:</span>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <input type="number" id="formTaxInput" min="0" step="any" class="input-control input-sm" style="width: 54px; text-align: right;" value="0" placeholder="0" oninput="window.invoicesApp.recalculateFormTotals()">
                                <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">%</span>
                                <span id="formTaxVal" style="font-weight: 600; color: var(--text-heading); min-width: 65px; text-align: right;">$0.00</span>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: 700; color: var(--text-heading); border-top: 1px solid var(--border-divider); padding-top: 6px; font-size: 14px;">
                            <span>Grand Total:</span>
                            <strong id="formGrandTotalVal" style="color: var(--primary);">$0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Notes & Terms -->
            <div>
                <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin: 0 0 10px 0;">
                    4. Notes &amp; Payment Terms
                </h4>
                <div style="margin-bottom: 10px;">
                    <label class="form-label">Customer Visible Notes</label>
                    <textarea class="input-control" id="formInvCustomerNote" rows="2" placeholder="Notes printed on invoice..."></textarea>
                </div>
                <div>
                    <label class="form-label">Internal Notes (Private)</label>
                    <textarea class="input-control" id="formInvInternalNote" rows="2" placeholder="Internal CRM notes..."></textarea>
                </div>
            </div>
        </div>

        <div class="drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.invoicesApp.closeFormDrawer()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnSaveInvoice" onclick="window.invoicesApp.saveInvoiceFromDrawer()">Save Invoice</button>
        </div>
    </div>
</div>

<!-- View Invoice Drawer (width: 720px, z-index: 1010) -->
<div class="drawer-overlay" id="invoiceViewDrawer" onclick="if(event.target===this) window.invoicesApp.closeViewDrawer()">
    <div class="drawer-content" style="max-width: 720px;" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <div>
                <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 500;">Invoices / View Preview</span>
                <h3 class="drawer-title" id="viewInvTitle">Invoice Preview</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.invoicesApp.downloadPDF(window.invoicesApp.activeInvoiceId)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    PDF
                </button>
                <button class="modal-close-btn" onclick="window.invoicesApp.closeViewDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div class="drawer-body" id="viewInvBody">
            <!-- Populated dynamically by JS -->
        </div>

        <div class="drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.invoicesApp.closeViewDrawer()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.invoicesApp.openRecordPaymentModal(window.invoicesApp.activeInvoiceId)">Record Payment</button>
        </div>
    </div>
</div>

<!-- Floating Action Dropdown Menu -->
<div class="contracts-row-dropdown" id="invoicesRowDropdown" onclick="event.stopPropagation()"></div>

<!-- Modal Container (Record Payment, Delete Confirmation) -->
<div class="invoices-modal-overlay drawer-overlay" id="invoicesActionModal" onclick="if(event.target===this) window.invoicesApp.closeModal()">
    <div class="invoices-modal-card" id="invoicesActionModalCard" onclick="event.stopPropagation()">
        <!-- Content injected dynamically -->
    </div>
</div>

<!-- Toast Container -->
<div id="invoicesToastContainer" style="position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px;"></div>

<?php include __DIR__ . '/includes/footer.php'; ?>
