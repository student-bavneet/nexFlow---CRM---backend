<?php
// Companies Management Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$currentUser = require_admin_auth();
$organizationId = (int)$currentUser['organization_id'];

$page_title = "Companies";
$current_page = "companies";
$page_script = "companies.js";

// Fetch dynamic initial counts and team members from MySQL
try {
    $pdo = nexflow_db();
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE organization_id = ?");
    $cntStmt->execute([$organizationId]);
    $totalCompaniesCount = (int)$cntStmt->fetchColumn();

    $ownersStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
    $ownersStmt->execute([$organizationId]);
    $activeOwners = $ownersStmt->fetchAll(PDO::FETCH_ASSOC);

    $contactsStmt = $pdo->prepare("SELECT id, name, job_title FROM contacts WHERE organization_id = ? AND is_active = 1 ORDER BY name ASC");
    $contactsStmt->execute([$organizationId]);
    $activeContacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $totalCompaniesCount = 0;
    $activeOwners = [];
    $activeContacts = [];
}

include __DIR__ . '/includes/header.php';
requirePermission('companies');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content companies-page">
    <div class="page-container">
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Companies</h1>
                    <span class="projects-total-badge" id="companiesTotalCountBadge"><?php echo $totalCompaniesCount; ?> companies</span>
                </div>
                <p class="projects-header-subtitle">Manage organizations, accounts, contacts, and business relationships.</p>
            </div>
            <div class="projects-header-actions">
                <input type="file" id="companiesImportFileInput" accept=".csv" style="display: none;" onchange="window.companiesApp.handleImportFile(event)">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('companiesImportFileInput').click()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Import
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.exportVisibleCompaniesCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.companiesApp.openAddDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Add Company
                </button>
            </div>
        </div>

        <!-- 7-Card KPI Summary Grid matching Leads Page Reference -->
        <div class="projects-summary-bar company-stat-bar">
            <div class="projects-summary-card company-kpi-card active" data-filter="All" onclick="window.companiesApp.filterByCard('All', this)">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryTotalCount"><?php echo $totalCompaniesCount; ?></span>
                    <span class="projects-summary-lbl">Total Companies</span>
                </div>
            </div>

            <div class="projects-summary-card company-kpi-card" data-filter="New" onclick="window.companiesApp.filterByCard('New', this)">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryNewCompanies">0</span>
                    <span class="projects-summary-lbl">New Companies</span>
                </div>
            </div>

            <div class="projects-summary-card company-kpi-card" data-filter="OpenDeals" onclick="window.companiesApp.filterByCard('OpenDeals', this)">
                <div class="projects-summary-icon" style="background-color: #F5F3FF; color: #7C3AED;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryOpenOpps">0</span>
                    <span class="projects-summary-lbl">With Open Deals</span>
                </div>
            </div>

            <div class="projects-summary-card company-kpi-card" data-filter="Customer" onclick="window.companiesApp.filterByCard('Customer', this)">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #1D4ED8;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryCustomersCount">0</span>
                    <span class="projects-summary-lbl">Customers</span>
                </div>
            </div>

            <div class="projects-summary-card company-kpi-card" data-filter="Prospect" onclick="window.companiesApp.filterByCard('Prospect', this)">
                <div class="projects-summary-icon" style="background-color: #FFF7ED; color: #EA580C;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryProspectsCount">0</span>
                    <span class="projects-summary-lbl">Prospects</span>
                </div>
            </div>

            <div class="projects-summary-card company-kpi-card" data-filter="Partner" onclick="window.companiesApp.filterByCard('Partner', this)">
                <div class="projects-summary-icon" style="background-color: #FAF5FF; color: #9333EA;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryPartnersCount">0</span>
                    <span class="projects-summary-lbl">Partners</span>
                </div>
            </div>

            <div class="projects-summary-card company-kpi-card" data-filter="Inactive" onclick="window.companiesApp.filterByCard('Inactive', this)">
                <div class="projects-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryInactiveCompanies">0</span>
                    <span class="projects-summary-lbl">Inactive</span>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar matching Leads Page Container -->
        <div class="projects-toolbar companies-search-toolbar">
            <div class="projects-toolbar-left">
                <div class="projects-search-wrapper" style="width: 340px;">
                    <svg class="projects-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="projects-search-input" id="companiesSearchInput" placeholder="Search companies...">
                </div>

                <div style="position: relative;">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnCompanyFilter" onclick="window.companiesApp.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filter</span>
                    </button>

                    <!-- Companies Filter Popover Panel -->
                    <div class="companies-filter-popover" id="companiesFilterPopover" onclick="event.stopPropagation()">
                        <div class="companies-filter-header">
                            <div class="companies-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Companies</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.companiesApp.resetFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="window.companiesApp.closeFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="companies-filter-body">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Industry</label>
                                <select class="input-control input-sm" id="filterIndustrySelect">
                                    <option value="All">All Industries</option>
                                    <option value="Enterprise Software">Enterprise Software</option>
                                    <option value="Telecommunications">Telecommunications</option>
                                    <option value="Data & AI">Data & AI</option>
                                    <option value="Logistics & Supply">Logistics & Supply</option>
                                    <option value="Fintech">Fintech</option>
                                    <option value="Cloud Infrastructure">Cloud Infrastructure</option>
                                    <option value="Banking & Finance">Banking & Finance</option>
                                    <option value="Artificial Intelligence">Artificial Intelligence</option>
                                    <option value="Financial Services">Financial Services</option>
                                    <option value="Venture Capital">Venture Capital</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Account Owner</label>
                                <select class="input-control input-sm" id="filterOwnerSelect">
                                    <option value="All">All Owners</option>
                                    <?php foreach ($activeOwners as $own): ?>
                                        <option value="<?php echo htmlspecialchars($own['name']); ?>"><?php echo htmlspecialchars($own['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Open Deals Range</label>
                                <select class="input-control input-sm" id="filterDealsRangeSelect">
                                    <option value="All">Any Deals</option>
                                    <option value="has_deals">Has Open Deals (&gt; 0)</option>
                                    <option value="no_deals">No Open Deals (0)</option>
                                    <option value="multi_deals">Multiple Deals (2+)</option>
                                </select>
                            </div>
                        </div>
                        <div class="companies-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.companiesApp.applyFilterDrawer()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <select class="input-control input-sm" id="companiesSortSelect" style="width: 170px;">
                    <option value="name-asc">Company Name A–Z</option>
                    <option value="name-desc">Company Name Z–A</option>
                    <option value="deals-desc">Highest Deal Value</option>
                    <option value="opps-desc">Most Open Deals</option>
                    <option value="active-desc">Recently Active</option>
                    <option value="created-desc">Recently Added</option>
                </select>

                <select class="input-control input-sm" id="companiesViewsSelect" style="width: 140px;">
                    <option value="all">All Accounts</option>
                    <option value="my">My Companies</option>
                    <option value="key">Key Accounts</option>
                    <option value="high">High Value</option>
                </select>
            </div>

            <div class="projects-toolbar-right" style="display: flex; align-items: center; gap: 10px;">
                <div class="companies-view-toggle">
                    <button type="button" class="companies-view-btn active" id="btnViewList" title="List View" onclick="window.companiesApp.setViewMode('list')">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </button>
                    <button type="button" class="companies-view-btn" id="btnViewGrid" title="Grid View" onclick="window.companiesApp.setViewMode('grid')">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </button>
                </div>

                <!-- Single Customize Columns Button in Search / Filter Block -->
                <div class="table-columns-dropdown-wrapper" data-table-dropdown-for="companiesTable">
                    <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns" title="Customize Columns">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                        Customize Columns
                    </button>
                </div>
            </div>
        </div>

        <!-- Removable Active Filter Chips -->
        <div class="companies-active-chips" id="companiesActiveChips">
            <!-- Populated dynamically by JS -->
        </div>

        <!-- Bulk Selection Toolbar -->
        <div class="companies-bulk-toolbar" id="companiesBulkToolbar">
            <span class="companies-bulk-text"><span id="companiesSelectedCountText">0</span> companies selected</span>
            <div style="display: flex; align-items: center; gap: 6px; margin-left: auto;">
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.companiesApp.executeBulkAction('owner')">Assign Owner</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.companiesApp.executeBulkAction('relationship')">Change Relationship</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.companiesApp.executeBulkAction('tag')">Add Tag</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.companiesApp.executeBulkAction('export')">Export</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.companiesApp.executeBulkAction('archive')">Archive</button>
                <button type="button" class="btn btn-secondary btn-xs" style="color: #F04438;" onclick="window.companiesApp.executeBulkAction('delete')">Delete</button>
            </div>
        </div>

        <!-- Main Desktop Table View Container -->
        <div class="companies-table-card" id="companiesListView">
            <div class="companies-table-wrapper">
                <table class="companies-table" id="companiesTable" data-table-id="companiesTable">
                    <thead>
                        <tr id="companiesHeaderRow">
                            <th data-protected="true" data-column-id="company">Company</th>
                            <th data-column-id="industry">Industry</th>
                            <th data-column-id="relationship">Relationship</th>
                            <th data-column-id="primary-contact">Primary Contact</th>
                            <th data-column-id="owner">Owner</th>
                            <th data-column-id="contacts">Contacts</th>
                            <th data-column-id="open-deals">Open Deals</th>
                            <th data-column-id="pipeline-value">Pipeline Value</th>
                            <th data-column-id="last-activity">Last Activity</th>
                            <th style="text-align: right;" data-protected="true" data-column-id="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="companiesTableBody">
                        <!-- Populated dynamically by JS -->
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

        <!-- Grid View Container -->
        <div class="companies-grid" id="companiesGridView" style="display: none;">
            <!-- Populated dynamically by JS -->
        </div>
    </div>
</main>

<!-- Drawer 1: Company Details Drawer (500px wide) -->
<div class="company-view-overlay" id="companyDetailsDrawer" role="dialog" aria-modal="true" aria-labelledby="compHeaderTitle">
    <div class="company-view-panel">
        <!-- Sticky Drawer Header -->
        <div class="company-view-header">
            <div>
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">Companies / <span id="compBreadcrumbName">Company Details</span></span>
                <h3 style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin: 1px 0 0;" id="compHeaderTitle">Company Details</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 4px;">
                <button type="button" class="btn btn-ghost btn-xs" id="compDrawerShareBtn" title="Share Company" aria-label="Share Company" onclick="window.NexFlowShare.openCompanyShare(window.companiesApp?.activeCompanyId, this)" style="padding: 4px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <button type="button" class="btn btn-ghost btn-xs" title="Edit Company" onclick="window.companiesApp.openEditFromDetails()" style="padding: 4px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <div class="company-view-dropdown-container">
                    <button type="button" class="btn btn-ghost btn-xs" title="More Actions" onclick="window.companiesApp.toggleMoreDropdown()" style="padding: 4px;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                    <div class="company-view-more-menu" id="compMoreMenu">
                        <button type="button" onclick="window.companiesApp.openEditFromDetails()">Edit Company</button>
                        <button type="button" onclick="window.companiesApp.switchDetailsTab('contacts')">Add Contact</button>
                        <button type="button" onclick="window.companiesApp.switchDetailsTab('deals')">Create Deal</button>
                        <button type="button" onclick="window.companiesApp.switchDetailsTab('tasks')">Add Task</button>
                        <button type="button" onclick="window.companiesApp.switchDetailsTab('notes')">Add Note</button>
                        <button type="button" id="compStatusToggleBtn" onclick="window.companiesApp.toggleActiveCompanyStatus()">Mark as Inactive</button>
                        <hr>
                        <button type="button" style="color: #F04438;" onclick="window.companiesApp.deleteActiveCompany()">Delete Company</button>
                    </div>
                </div>
                <button type="button" class="btn btn-ghost btn-xs" title="Close Drawer (Esc)" onclick="window.companiesApp.closeDetails()" style="padding: 4px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div class="company-view-body">
            <!-- Compact Profile Card -->
            <div class="company-view-profile">
                <div style="display: flex; align-items: center; gap: 14px; width: 100%;">
                    <div class="avatar avatar-lg" id="compAvatar" style="background-color: #7C3AED; font-size: 20px; flex-shrink: 0;">CO</div>
                    <div style="flex: 1; min-width: 0; text-align: left;">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <h2 style="font-size: 16px; font-weight: 700; color: var(--text-heading); margin: 0;" id="compName">--</h2>
                            <span class="companies-badge prospect" id="compRelBadge">--</span>
                        </div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 2px 0 4px;" id="compIndustrySize">--</p>
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-muted);">
                            <span>Owner: <strong style="color: var(--text-body);" id="compOwner">--</strong></span>
                            <span>•</span>
                            <span id="compLocation">--</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Details Tabs (WAI-ARIA) -->
            <div class="company-view-tabs" role="tablist" aria-label="Company Details Navigation">
                <button type="button" class="company-view-tab active" id="tab-comp-overview" role="tab" aria-selected="true" aria-controls="panel-comp-overview" tabindex="0" onclick="window.companiesApp.switchDetailsTab('overview')">Overview</button>
                <button type="button" class="company-view-tab" id="tab-comp-contacts" role="tab" aria-selected="false" aria-controls="panel-comp-contacts" tabindex="-1" onclick="window.companiesApp.switchDetailsTab('contacts')">Contacts</button>
                <button type="button" class="company-view-tab" id="tab-comp-deals" role="tab" aria-selected="false" aria-controls="panel-comp-deals" tabindex="-1" onclick="window.companiesApp.switchDetailsTab('deals')">Deals</button>
                <button type="button" class="company-view-tab" id="tab-comp-activity" role="tab" aria-selected="false" aria-controls="panel-comp-activity" tabindex="-1" onclick="window.companiesApp.switchDetailsTab('activity')">Activity</button>
                <button type="button" class="company-view-tab" id="tab-comp-tasks" role="tab" aria-selected="false" aria-controls="panel-comp-tasks" tabindex="-1" onclick="window.companiesApp.switchDetailsTab('tasks')">Tasks</button>
                <button type="button" class="company-view-tab" id="tab-comp-notes" role="tab" aria-selected="false" aria-controls="panel-comp-notes" tabindex="-1" onclick="window.companiesApp.switchDetailsTab('notes')">Notes</button>
            </div>

            <!-- Tab Content Dynamic Panel -->
            <div id="compTabContent" class="company-view-tab-content" role="tabpanel" id="panel-comp-overview" aria-labelledby="tab-comp-overview">
                <!-- Dynamically populated by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Drawer 2: + Add / Edit Company Form Drawer (480px) -->
<div class="companies-drawer-overlay" id="companiesAddDrawer" role="dialog" aria-modal="true" aria-labelledby="addCompanyModalTitle">
    <div class="companies-drawer-panel">
        <div class="companies-drawer-header">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--text-heading); margin: 0;" id="addCompanyModalTitle">Add New Company</h3>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.companiesApp.closeAddDrawer()" style="padding: 4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="addCompanyForm" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
            <input type="hidden" id="editCompanyId" value="">
            <div class="companies-drawer-body">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Company Information</div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Company Name *</label>
                    <input type="text" class="input-control input-sm" id="addCompanyName" required placeholder="e.g., Acme Corp" autocomplete="off">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Domain / Website</label>
                        <input type="text" class="input-control input-sm" id="addCompanyDomain" placeholder="acmecorp.io">
                    </div>
                    <div>
                        <label class="form-label">Industry</label>
                        <select class="input-control input-sm" id="addCompanyIndustry">
                            <option value="Enterprise Software">Enterprise Software</option>
                            <option value="Telecommunications">Telecommunications</option>
                            <option value="Data & AI">Data & AI</option>
                            <option value="Logistics & Supply">Logistics & Supply</option>
                            <option value="Fintech">Fintech</option>
                            <option value="Cloud Infrastructure">Cloud Infrastructure</option>
                            <option value="Banking & Finance">Banking & Finance</option>
                            <option value="Artificial Intelligence">Artificial Intelligence</option>
                            <option value="Financial Services">Financial Services</option>
                            <option value="Venture Capital">Venture Capital</option>
                            <option value="Healthcare Systems">Healthcare Systems</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">Company Size</label>
                        <select class="input-control input-sm" id="addCompanySize">
                            <option value="1–20 employees">1–20 employees</option>
                            <option value="20–50 employees">20–50 employees</option>
                            <option value="50–100 employees">50–100 employees</option>
                            <option value="100–250 employees" selected>100–250 employees</option>
                            <option value="250–500 employees">250–500 employees</option>
                            <option value="500–1000 employees">500–1000 employees</option>
                            <option value="1000+ employees">1000+ employees</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Annual Revenue</label>
                        <input type="text" class="input-control input-sm" id="addCompanyRevenue" placeholder="$45.0M">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Founded Year</label>
                        <input type="number" class="input-control input-sm" id="addCompanyFoundedYear" placeholder="e.g., 2018" min="1800" max="2100">
                    </div>
                    <div>
                        <label class="form-label">LinkedIn</label>
                        <input type="text" class="input-control input-sm" id="addCompanyLinkedin" placeholder="https://linkedin.com/company/...">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Tax Registration Number</label>
                    <input type="text" class="input-control input-sm" id="addCompanyTaxReg" placeholder="e.g., TAX-987654321">
                </div>

                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Location & CRM Info</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Location (City, State/Country)</label>
                        <input type="text" class="input-control input-sm" id="addCompanyLocation" placeholder="San Francisco, CA">
                    </div>
                    <div>
                        <label class="form-label">Relationship</label>
                        <select class="input-control input-sm" id="addCompanyRel">
                            <option value="Customer">Customer</option>
                            <option value="Prospect" selected>Prospect</option>
                            <option value="Partner">Partner</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Account Owner</label>
                    <select class="input-control input-sm" id="addCompanyOwner">
                        <option value="">Select Owner</option>
                        <?php foreach ($activeOwners as $own): ?>
                            <option value="<?php echo htmlspecialchars($own['name']); ?>" data-id="<?php echo (int)$own['id']; ?>"><?php echo htmlspecialchars($own['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Primary Contact</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div style="position: relative;">
                        <label class="form-label">Contact Number</label>
                        <input type="text" class="input-control input-sm" id="addCompanyContactPhone" placeholder="Search by phone..." autocomplete="off">
                        <div class="nexflow-autocomplete-dropdown" id="addCompanyPhoneDropdown" style="display: none;"></div>
                    </div>
                    <div>
                        <label class="form-label">Primary Contact</label>
                        <input type="text" class="input-control input-sm" id="addCompanyContactName" placeholder="Select or enter contact name" autocomplete="off">
                        <input type="hidden" id="addCompanySelectedContactId" value="">
                        <div id="addCompanyContactHelper" style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: none;">New contact will be created as Primary Contact</div>
                    </div>
                </div>
                <div class="form-group" id="addCompanyNewEmailGroup" style="margin-bottom: 12px; display: none;">
                    <label class="form-label">Contact Email</label>
                    <input type="email" class="input-control input-sm" id="addCompanyContactEmail" placeholder="e.g., contact@example.com" autocomplete="off">
                </div>
            </div>
            <div class="companies-drawer-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeAddDrawer()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnSaveCompanySubmit">Save Company</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: + Add Task Drawer Modal -->
<div class="companies-drawer-overlay" id="companyAddTaskModal" role="dialog" aria-modal="true" aria-labelledby="compAddTaskModalTitle">
    <div class="companies-drawer-panel" style="width: 400px;">
        <div class="companies-drawer-header">
            <h3 style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin: 0;" id="compAddTaskModalTitle">Add Task</h3>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.companiesApp.closeAddTaskModal()" style="padding: 4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="compAddTaskForm" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
            <div class="companies-drawer-body" style="padding: 16px;">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Task Title *</label>
                    <input type="text" class="input-control input-sm" id="compTaskTitleInput" required placeholder="e.g., Follow up on Q3 contract">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Type</label>
                        <select class="input-control input-sm" id="compTaskTypeSelect">
                            <option value="Call">Call</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Email">Email</option>
                            <option value="To-Do" selected>To-Do</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Priority</label>
                        <select class="input-control input-sm" id="compTaskPrioritySelect">
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Due Date</label>
                        <input type="text" class="input-control input-sm" id="compTaskDueDateInput" placeholder="Today, 5:00 PM">
                    </div>
                    <div>
                        <label class="form-label">Assigned Owner</label>
                        <select class="input-control input-sm" id="compTaskOwnerSelect">
                            <option value="">Select Owner</option>
                            <?php foreach ($activeOwners as $own): ?>
                                <option value="<?php echo htmlspecialchars($own['name']); ?>" data-id="<?php echo (int)$own['id']; ?>"><?php echo htmlspecialchars($own['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea class="input-control" id="compTaskNotesInput" style="height: 60px; font-size: 12px; resize: none;" placeholder="Additional task notes..."></textarea>
                </div>
            </div>
            <div class="companies-drawer-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeAddTaskModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Top-Level Action Modal for Company Drawer -->
<div id="companyActionModal">
    <div class="company-action-backdrop"></div>
    <div class="company-action-panel" id="companyActionModalPanel">
        <!-- Dynamic Modal Content injected by companies.js -->
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

