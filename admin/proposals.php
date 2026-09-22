<?php
// Admin Proposals Page
$page_title = "Proposals";
$current_page = "proposals";
$page_script = "proposals.js";

include __DIR__ . '/includes/header.php';
requirePermission('proposals');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
<script>
window.adminCsrfToken = <?php echo json_encode(admin_csrf_token()); ?>;
</script>

<main class="main-content">
    <div class="page-container">
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Proposals</h1>
                    <span class="projects-total-badge" id="proposalsTotalBadge">0 proposals</span>
                </div>
                <p class="projects-header-subtitle">Create, manage, track, and monitor sales proposals.</p>
            </div>
            <div class="projects-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.adminProposals.exportProposals()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.adminProposals.openCreateDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Create Proposal
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Grid -->
        <div class="projects-summary-bar">
            <div class="projects-summary-card active" data-kpi="All" onclick="window.adminProposals.selectKpi('All')">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiTotalProposals">0</span>
                    <span class="projects-summary-lbl">Total Proposals</span>
                </div>
            </div>

            <div class="projects-summary-card" data-kpi="Draft" onclick="window.adminProposals.selectKpi('Draft')">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiDraftProposals">0</span>
                    <span class="projects-summary-lbl">Draft</span>
                </div>
            </div>

            <div class="projects-summary-card" data-kpi="SentViewed" onclick="window.adminProposals.selectKpi('SentViewed')">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiSentProposals">0</span>
                    <span class="projects-summary-lbl">Sent / Viewed</span>
                </div>
            </div>

            <div class="projects-summary-card" data-kpi="Accepted" onclick="window.adminProposals.selectKpi('Accepted')">
                <div class="projects-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiAcceptedProposals">0</span>
                    <span class="projects-summary-lbl">Accepted</span>
                </div>
            </div>

            <div class="projects-summary-card" data-kpi="DeclinedExpired" onclick="window.adminProposals.selectKpi('DeclinedExpired')">
                <div class="projects-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiDeclinedProposals">0</span>
                    <span class="projects-summary-lbl">Declined / Expired</span>
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
                    <input type="text" class="projects-search-input" id="proposalSearchInput" placeholder="Search proposals, clients, deals..." oninput="window.adminProposals.render()">
                </div>

                <!-- Filter Popover Button -->
                <div style="position: relative;">
                    <button class="btn btn-secondary btn-sm" id="btnProposalFilter" type="button" onclick="window.adminProposals.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span> Filters</span>
                        <span class="leads-filter-badge" id="proposalsFilterBadge" style="display:none;"></span>
                    </button>

                    <!-- Filter Popover -->
                    <div class="leads-filter-popover" id="proposalsFilterPopover" style="display:none; width: 340px; right: auto; left: 0;">
                        <div class="leads-filter-header">
                            <div class="leads-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Proposals</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.adminProposals.clearFilters()">Clear all</button>
                                <button type="button" class="modal-close-btn" onclick="window.adminProposals.closeFilterPopover()" aria-label="Close filter">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="leads-filter-body">
                            <!-- Filter Status -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Status</label>
                                <div class="leads-filter-checkbox-grid">
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Draft" class="filter-status-cb"> Draft</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Sent" class="filter-status-cb"> Sent</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Viewed" class="filter-status-cb"> Viewed</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Changes Requested" class="filter-status-cb"> Changes Requested</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Accepted" class="filter-status-cb"> Accepted</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Declined" class="filter-status-cb"> Declined</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Expired" class="filter-status-cb"> Expired</label>
                                </div>
                            </div>
                            <!-- Filter Prepared By -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Prepared By</label>
                                <select id="filterPreparedBy" class="input-control input-sm" style="width: 100%;">
                                    <option value="all">All Team Members</option>
                                </select>
                            </div>
                            <!-- Amount Range -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Amount (<span class="currency-symbol-label">$</span>)</label>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="number" id="filterAmountMin" class="input-control input-sm" placeholder="Min" min="0">
                                    <span style="color: var(--text-muted); font-size: 12px;">to</span>
                                    <input type="number" id="filterAmountMax" class="input-control input-sm" placeholder="Max" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="leads-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.adminProposals.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.adminProposals.applyPopoverFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- Status Dropdown -->
                <select id="proposalsStatusSelect" class="input-control input-sm" style="width: 160px; height:36px;" onchange="window.adminProposals.onStatusDropdownChange(this.value)">
                    <option value="All">All Statuses</option>
                    <option value="Draft">Draft</option>
                    <option value="Sent">Sent</option>
                    <option value="Viewed">Viewed</option>
                    <option value="Changes Requested">Changes Requested</option>
                    <option value="Accepted">Accepted</option>
                    <option value="Declined">Declined</option>
                    <option value="Expired">Expired</option>
                </select>

                <!-- Sort Dropdown -->
                <select id="proposalsSortSelect" class="input-control input-sm" style="width: 160px; height:36px;" onchange="window.adminProposals.render()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="amount-desc">Amount: High to Low</option>
                    <option value="amount-asc">Amount: Low to High</option>
                    <option value="title-asc">Title: A to Z</option>
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

        <!-- Table Card -->
        <div class="card" id="adminProposalsTableCard">
            <div class="crm-table-wrapper">
                <table class="crm-table" id="proposalsTable">
                    <thead>
                        <tr>
                            <th data-protected="true" data-column-id="proposal" class="col-proposal-info">PROPOSAL</th>
                            <th data-column-id="client">CLIENT</th>
                            <th data-column-id="deal">RELATED DEAL</th>
                            <th data-column-id="amount" class="col-amount">AMOUNT</th>
                            <th data-column-id="status" class="col-status">STATUS</th>
                            <th data-column-id="issueDate" class="col-issue-date">ISSUE DATE</th>
                            <th data-column-id="expiryDate" class="col-expiry-date">EXPIRY DATE</th>
                            <th data-column-id="preparedBy">PREPARED BY</th>
                            <th style="text-align: right;" data-protected="true" data-column-id="actions" class="col-actions">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="adminProposalsTbody">
                        <!-- Dynamically rendered table rows -->
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

        <!-- Empty State Container -->
        <div id="adminProposalsEmptyState" style="display:none; margin-top:30px; text-align:center; padding:40px 20px; background:#FFF; border:1px solid var(--border-card); border-radius:var(--radius-lg);">
            <div style="width:52px; height:52px; border-radius:50%; background:#F1F5F9; color:#64748B; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <h3 style="font-size:16px; font-weight:700; color:var(--text-heading); margin:0 0 6px 0;">No Proposals Found</h3>
            <p style="font-size:13px; color:var(--text-secondary); margin:0 0 16px 0;">No proposals matched your current search or filter criteria.</p>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.adminProposals.clearFilters()">Clear Filters</button>
        </div>
    </div>
</main>

<!-- RIGHT-SIDE CREATE / EDIT PROPOSAL DRAWER -->
<div class="drawer-overlay" id="proposalFormDrawer" role="dialog" aria-modal="true" onclick="if(event.target===this) window.adminProposals.closeFormDrawer()">
    <div class="drawer-content" style="max-width: 640px; display: flex; flex-direction: column; height: 100%;" onclick="event.stopPropagation()">
        <!-- Fixed Header -->
        <div class="drawer-header" style="flex-shrink: 0;">
            <div>
                <div class="drawer-breadcrumb">Proposals / Management</div>
                <h3 class="drawer-title" id="formDrawerTitle">Create Proposal</h3>
                <p style="font-size:12px; color:var(--text-muted); margin:2px 0 0 0;" id="formDrawerSubtitle">Create a proposal for a client deal.</p>
            </div>
            <div class="drawer-header-actions">
                <button type="button" class="modal-close-btn" onclick="window.adminProposals.closeFormDrawer()" aria-label="Close drawer" title="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <form id="proposalDrawerForm" onsubmit="window.adminProposals.saveForm(event)" style="display:flex; flex-direction:column; flex:1; height: calc(100% - 65px); overflow: hidden;">
            <!-- Scrollable Form Body -->
            <div class="drawer-body" style="padding: 20px 24px 36px 24px; overflow-y: auto; flex: 1;">
                <input type="hidden" id="pfProposalId" value="">

                <!-- Field Grid -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">Client *</label>
                        <select class="input-control input-sm" id="pfClientSelect" required>
                            <option value="">Select client...</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Related Deal</label>
                        <select class="input-control input-sm" id="pfDealSelect">
                            <option value="">Select related deal (optional)...</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">Related Project</label>
                        <select class="input-control input-sm" id="pfProjectSelect">
                            <option value="">No project selected (optional)...</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Prepared By *</label>
                        <select class="input-control input-sm" id="pfPreparedBySelect" required>
                            <option value="">Select team member...</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label class="form-label">Proposal Title *</label>
                    <input type="text" class="input-control input-sm" id="pfTitleInput" placeholder="e.g. E-Commerce Platform Development" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">Issue Date *</label>
                        <input type="date" class="input-control input-sm" id="pfIssueDate" required>
                    </div>
                    <div>
                        <label class="form-label">Expiry Date *</label>
                        <input type="date" class="input-control input-sm" id="pfExpiryDate" required>
                    </div>
                </div>

                <!-- Scope Summary -->
                <div style="margin-bottom: 20px;">
                    <label class="form-label">Proposal Scope</label>
                    <textarea class="input-control" id="pfScopeInput" rows="3" style="height: 75px; resize: none; font-size: 13px; padding: 8px 12px;" placeholder="Describe the proposal scope, objectives, and key outcomes..."></textarea>
                </div>

                <!-- Itemized Deliverables -->
                <div style="border-top: 1px solid var(--border-divider); padding-top: 16px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin: 0;">Itemized Deliverables</h4>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.adminProposals.addDeliverableRow()">+ Add Deliverable</button>
                    </div>

                    <div style="border: 1px solid var(--border-card); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 14px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;" id="pfDeliverablesTable">
                            <thead>
                                <tr style="background: var(--bg-hover); border-bottom: 1px solid var(--border-card);">
                                    <th style="padding: 8px 10px; text-align: left;">Deliverable &amp; Description</th>
                                    <th style="padding: 8px 10px; text-align: center; width: 60px;">Qty</th>
                                    <th style="padding: 8px 10px; text-align: right; width: 95px;">Rate (<span class="currency-symbol-label">$</span>)</th>
                                    <th style="padding: 8px 10px; text-align: right; width: 100px;">Amount (<span class="currency-symbol-label">$</span>)</th>
                                    <th style="padding: 8px 10px; width: 35px;"></th>
                                </tr>
                            </thead>
                            <tbody id="pfDeliverablesTbody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pricing Summary Box -->
                    <div style="background: var(--bg-hover); border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 14px 16px;">
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; color:var(--text-secondary); margin-bottom:6px;">
                            <span>Subtotal</span>
                            <strong style="color:var(--text-heading);" id="pfSubtotalText"><span class="currency-symbol-label">$</span>0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:12.5px; color:var(--text-secondary); margin-bottom:6px;">
                            <span>Discount</span>
                            <input type="number" id="pfDiscountInput" class="input-control input-sm" style="width: 100px; text-align: right; height: 26px; padding: 2px 6px;" value="0" min="0" oninput="window.adminProposals.recalculateTotals()">
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; color:var(--text-secondary); margin-bottom:8px;">
                            <span>Tax (18% GST)</span>
                            <span id="pfTaxText"><span class="currency-symbol-label">$</span>0.00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:700; color:var(--text-heading); border-top:1px dashed var(--border-card); padding-top:8px;">
                            <span>Total Payable Amount</span>
                            <span style="color:var(--primary);" id="pfTotalPayableText"><span class="currency-symbol-label">$</span>0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Terms & Conditions -->
                <div style="border-top: 1px solid var(--border-divider); padding-top: 16px;">
                    <div style="margin-bottom: 12px;">
                        <label class="form-label">Payment Terms</label>
                        <textarea class="input-control" id="pfPaymentTermsInput" rows="2" style="height: 50px; resize: none; font-size: 12.5px; padding: 6px 10px;" placeholder="50% advance upon agreement signing, 50% upon final UAT deployment."></textarea>
                    </div>
                    <div>
                        <label class="form-label">Terms &amp; Conditions</label>
                        <textarea class="input-control" id="pfTermsInput" rows="2" style="height: 50px; resize: none; font-size: 12.5px; padding: 6px 10px;" placeholder="This proposal is valid for 30 days. All rates are subject to standard enterprise SLA terms."></textarea>
                    </div>
                </div>
            </div>

            <!-- Sticky Bottom Footer Actions -->
            <div class="drawer-footer" style="flex-shrink: 0; padding: 14px 24px; border-top: 1px solid var(--border-card); background: #FFF; display: flex; align-items: center; justify-content: space-between; gap: 10px; z-index: 10;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.adminProposals.closeFormDrawer()">Cancel</button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnSaveDraftProposal" onclick="window.adminProposals.saveFormDraft()">Save Draft</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitProposalForm">Create Proposal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- RIGHT-SIDE VIEW PROPOSAL DETAILS DRAWER -->
<div class="drawer-overlay" id="proposalViewDrawer" role="dialog" aria-modal="true" onclick="if(event.target===this) window.adminProposals.closeViewDrawer()">
    <div class="drawer-content" style="max-width: 620px; display: flex; flex-direction: column; height: 100%;" onclick="event.stopPropagation()">
        <div class="drawer-header" style="flex-shrink: 0;">
            <div>
                <div class="drawer-breadcrumb" id="vdBreadcrumb">Proposals / Proposal Details</div>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 2px;">
                    <h3 class="drawer-title" id="vdTitle">Proposal Details</h3>
                    <span class="client-portal-badge" id="vdStatusBadge">Draft</span>
                </div>
            </div>
            <div class="drawer-header-actions">
                <!-- Share Button -->
                <button type="button" class="btn btn-ghost btn-xs" onclick="window.adminProposals.openShareModal()" title="Share Proposal">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <!-- Edit Icon Button -->
                <button type="button" class="btn btn-ghost btn-xs" onclick="window.adminProposals.editCurrentProposal()" title="Edit Proposal">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <!-- More (...) Actions Dropdown Button -->
                <div class="drawer-dropdown-wrapper" style="position: relative;">
                    <button type="button" class="btn btn-ghost btn-xs" id="btnProposalMoreActions" onclick="window.adminProposals.toggleMoreMenu(event)" title="More Actions">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    </button>
                    <div class="dropdown-menu drawer-header-menu" id="proposalMoreMenu" style="display: none; position: absolute; right: 0; top: calc(100% + 4px); z-index: 50; width: 180px; background: #FFF; border: 1px solid var(--border-card); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); padding: 4px 0;">
                        <button type="button" class="dropdown-item" onclick="window.adminProposals.switchViewTab('activity'); window.adminProposals.closeMoreMenu();" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12.5px; display: flex; align-items: center; gap: 8px; background: none; border: none; cursor: pointer; color: var(--text-heading);">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> View Activity
                        </button>
                        <button type="button" class="dropdown-item" onclick="window.adminProposals.duplicateCurrentProposal(); window.adminProposals.closeMoreMenu();" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12.5px; display: flex; align-items: center; gap: 8px; background: none; border: none; cursor: pointer; color: var(--text-heading);">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Duplicate Proposal
                        </button>
                        <div style="border-top: 1px solid var(--border-divider); margin: 4px 0;"></div>
                        <button type="button" class="dropdown-item" onclick="window.adminProposals.promptDeleteCurrent(); window.adminProposals.closeMoreMenu();" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12.5px; display: flex; align-items: center; gap: 8px; background: none; border: none; cursor: pointer; color: #F04438;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete Proposal
                        </button>
                    </div>
                </div>
                <!-- Close Button -->
                <button type="button" class="modal-close-btn" onclick="window.adminProposals.closeViewDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- View Drawer Tab Switcher -->
        <div class="drawer-tabs-wrapper" style="flex-shrink: 0;">
            <div class="drawer-tabs" role="tablist">
                <button type="button" class="drawer-tab active" data-tab="overview" onclick="window.adminProposals.switchViewTab('overview')">Overview</button>
                <button type="button" class="drawer-tab" data-tab="items" onclick="window.adminProposals.switchViewTab('items')">Itemized Deliverables</button>
                <button type="button" class="drawer-tab" data-tab="terms" onclick="window.adminProposals.switchViewTab('terms')">Terms &amp; Conditions</button>
                <button type="button" class="drawer-tab" data-tab="activity" onclick="window.adminProposals.switchViewTab('activity')">Activity History</button>
            </div>
        </div>

        <!-- Scrollable Tab Content Body -->
        <div class="drawer-body" id="vdBody" style="padding: 20px 24px 36px 24px; overflow-y: auto; flex: 1;">
            <!-- Rendered dynamically by JS -->
        </div>

        <!-- View Drawer Footer -->
        <div class="drawer-footer" style="flex-shrink: 0; padding: 14px 24px; border-top: 1px solid var(--border-card); background: #FFF; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
            <button type="button" class="btn btn-ghost btn-sm" style="color: #F04438;" onclick="window.adminProposals.promptDeleteCurrent()">Delete Proposal</button>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.adminProposals.closeViewDrawer()">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnVdAction" onclick="window.adminProposals.triggerViewPrimaryAction()">Send Proposal</button>
            </div>
        </div>
    </div>
</div>

<!-- SHARE PROPOSAL MODAL -->
<div class="modal-overlay" id="shareProposalModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); z-index:1100; align-items:center; justify-content:center;" onclick="if(event.target===this) window.adminProposals.closeShareModal()">
    <div style="background:#FFF; border-radius:var(--radius-lg); width:100%; max-width:440px; padding:24px; box-shadow:var(--shadow-xl); border:1px solid var(--border-card);" onclick="event.stopPropagation()">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="font-size:16px; font-weight:700; color:var(--text-heading); margin:0;">Share Proposal</h3>
            <button type="button" class="modal-close-btn" onclick="window.adminProposals.closeShareModal()" aria-label="Close modal">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13px; color:var(--text-secondary); margin:0 0 12px 0;">Share this proposal link directly with the client.</p>
        <div style="margin-bottom:16px;">
            <label class="form-label" style="font-size:11.5px; font-weight:600; color:var(--text-muted);">Proposal</label>
            <div style="font-size:13.5px; font-weight:600; color:var(--text-heading); margin-bottom:10px;" id="shareModalProposalTitle">Enterprise CRM Suite</div>
            <div style="display:flex; gap:8px;">
                <input type="text" id="shareProposalUrlInput" class="input-control input-sm" readonly style="flex:1; font-size:12px; background:var(--bg-app); cursor:text;">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.adminProposals.copyShareLink()">Copy Proposal Link</button>
            </div>
        </div>
    </div>
</div>

<!-- CONFIRMATION MODAL: SEND PROPOSAL -->
<div class="modal-overlay" id="sendProposalModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); z-index:1100; align-items:center; justify-content:center;" onclick="if(event.target===this) window.adminProposals.closeSendModal()">
    <div style="background:#FFF; border-radius:var(--radius-lg); width:100%; max-width:440px; padding:24px; box-shadow:var(--shadow-xl); border:1px solid var(--border-card);" onclick="event.stopPropagation()">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="font-size:16px; font-weight:700; color:var(--text-heading); margin:0;" id="spModalHeaderTitle">Send Proposal</h3>
            <button type="button" class="modal-close-btn" onclick="window.adminProposals.closeSendModal()" aria-label="Close modal">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13.5px; color:var(--text-secondary); margin:0 0 14px 0;" id="spModalHeaderSub">You're about to send this proposal to the client.</p>
        <div style="background:var(--bg-hover); border:1px solid var(--border-card); border-radius:var(--radius-md); padding:12px 14px; font-size:12.5px; color:var(--text-heading); margin-bottom:20px;">
            <div style="margin-bottom:4px;"><strong>Proposal:</strong> <span id="spModalTitle">E-Commerce Platform Development</span></div>
            <div style="margin-bottom:4px;"><strong>Client:</strong> <span id="spModalClient">Acme Corp</span></div>
            <div style="margin-bottom:4px;"><strong>Amount:</strong> <span id="spModalAmount">₹1,65,200</span></div>
            <div><strong>Expiry Date:</strong> <span id="spModalExpiry">2026-09-10</span></div>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.adminProposals.closeSendModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnConfirmSendProposal" onclick="window.adminProposals.confirmSend()">Send Proposal</button>
        </div>
    </div>
</div>

<!-- CONFIRMATION MODAL: DELETE PROPOSAL -->
<div class="modal-overlay" id="deleteProposalModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); z-index:1100; align-items:center; justify-content:center;" onclick="if(event.target===this) window.adminProposals.closeDeleteModal()">
    <div style="background:#FFF; border-radius:var(--radius-lg); width:100%; max-width:440px; padding:24px; box-shadow:var(--shadow-xl); border:1px solid var(--border-card);" onclick="event.stopPropagation()">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="font-size:16px; font-weight:700; color:#DC2626; margin:0;">Delete Proposal?</h3>
            <button type="button" class="modal-close-btn" onclick="window.adminProposals.closeDeleteModal()" aria-label="Close modal">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13.5px; color:var(--text-secondary); margin:0 0 20px 0; line-height:1.5;">Are you sure you want to delete this proposal? This action cannot be undone.</p>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.adminProposals.closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" style="background:#DC2626; border-color:#DC2626;" onclick="window.adminProposals.confirmDelete()">Delete Proposal</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
