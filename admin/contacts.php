<?php
// Contacts Management Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$page_title = "Contacts";
$current_page = "contacts";
$page_script = "contacts.js";

include __DIR__ . '/includes/header.php';
requirePermission('contacts');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$pdo = nexflow_db();
$currentUser = nexflow_current_user();
$organizationId = (int)($currentUser['organization_id'] ?? 1);

// Active owners for current organization
$ownerStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
$ownerStmt->execute([$organizationId]);
$active_owners = $ownerStmt->fetchAll(PDO::FETCH_ASSOC);

// Initial real KPI metrics from MySQL
$kpiStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_contacts,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) AS active_contacts,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) AS recent_contacts,
        COUNT(CASE WHEN owner_id IS NULL THEN 1 END) AS unassigned_contacts,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) AS inactive_contacts
    FROM contacts
    WHERE organization_id = ?
");
$kpiStmt->execute([$organizationId]);
$db_kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$kpi_total = (int)($db_kpis['total_contacts'] ?? 0);
$kpi_active = (int)($db_kpis['active_contacts'] ?? 0);
$kpi_recent = (int)($db_kpis['recent_contacts'] ?? 0);
$kpi_unassigned = (int)($db_kpis['unassigned_contacts'] ?? 0);
$kpi_inactive = (int)($db_kpis['inactive_contacts'] ?? 0);
?>

<main class="main-content contacts-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Contacts</h1>
                    <span class="projects-total-badge" id="contactsTotalCountBadge"><?php echo $kpi_total; ?> contacts</span>
                </div>
                <p class="projects-header-subtitle">Manage customer contacts, communication details, and relationships.</p>
            </div>
            <div class="projects-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.openImportModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Import
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.contactsApp.openAddContactModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Contact
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Bar -->
        <div class="projects-summary-bar">
            <div class="projects-summary-card" onclick="window.contactsApp.filterByCard('All')">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiTotalContacts"><?php echo $kpi_total; ?></span>
                    <span class="projects-summary-lbl">Total Contacts</span>
                </div>
            </div>

            <div class="projects-summary-card" onclick="window.contactsApp.filterByCard('Active')">
                <div class="projects-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiActiveContacts"><?php echo $kpi_active; ?></span>
                    <span class="projects-summary-lbl">Active Contacts</span>
                </div>
            </div>

            <div class="projects-summary-card" onclick="window.contactsApp.filterByCard('Recent')">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 16 14"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiRecentContacts"><?php echo $kpi_recent; ?></span>
                    <span class="projects-summary-lbl">Recently Added</span>
                </div>
            </div>

            <div class="projects-summary-card" onclick="window.contactsApp.filterByCard('Unassigned')">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiUnassignedContacts"><?php echo $kpi_unassigned; ?></span>
                    <span class="projects-summary-lbl">Unassigned</span>
                </div>
            </div>

            <div class="projects-summary-card" onclick="window.contactsApp.filterByCard('Inactive')">
                <div class="projects-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiInactiveContacts"><?php echo $kpi_inactive; ?></span>
                    <span class="projects-summary-lbl">Inactive</span>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="projects-toolbar">
            <div class="projects-toolbar-left">
                <div class="projects-search-wrapper" style="width: 350px;">
                    <svg class="projects-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="projects-search-input" id="contactSearchInput" placeholder="Search contact name, email, company..." oninput="window.contactsApp.handleSearch(this.value)">
                </div>

                <div style="position: relative;">
                    <button class="btn btn-secondary btn-sm" id="btnContactFilter" type="button" onclick="window.contactsApp.toggleFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filter</span>
                    </button>

                    <!-- Filter Popover Panel -->
                    <div class="contacts-filter-popover" id="contactsFilterPopover" onclick="event.stopPropagation()">
                        <div class="contacts-filter-header">
                            <div class="contacts-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Contacts</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="window.contactsApp.clearFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="window.contactsApp.closeFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="contacts-filter-body">
                            <div>
                                <label class="form-label">Relationship</label>
                                <select class="input-control input-sm" id="filterRelationship">
                                    <option value="All">All Relationships</option>
                                    <option value="Customer">Customer</option>
                                    <option value="Prospect">Prospect</option>
                                    <option value="Partner">Partner</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Assigned Owner</label>
                                <select class="input-control input-sm" id="filterOwner">
                                    <option value="All">All Owners</option>
                                    <?php foreach ($active_owners as $u): ?>
                                        <option value="<?php echo htmlspecialchars($u['name']); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="contacts-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.contactsApp.applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <select class="input-control input-sm" style="width: 170px;" id="contactSortDropdown" onchange="window.contactsApp.handleSort(this.value)">
                    <option value="name-asc">Name (A–Z)</option>
                    <option value="name-desc">Name (Z–A)</option>
                    <option value="rel-Customer">Customers</option>
                    <option value="rel-Partner">Partners</option>
                    <option value="rel-Prospect">Prospects</option>
                    <option value="interaction">Last Interaction</option>
                </select>
            </div>

            <!-- Customize Columns -->
            <div class="table-columns-dropdown-wrapper" data-table-dropdown-for="contactsTable">
                <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                    Customize Columns
                </button>
            </div>
        </div>

        <!-- Contacts Table Card -->
        <div class="card">
            <div class="crm-table-wrapper">
                <table class="crm-table" id="contactsTable">
                    <thead>
                        <tr>
                            <th data-protected="true" data-column-id="contact">CONTACT</th>
                            <th data-column-id="company">COMPANY</th>
                            <th data-column-id="relationship">RELATIONSHIP</th>
                            <th data-column-id="contact-info">CONTACT INFO</th>
                            <th data-column-id="owner">OWNER</th>
                            <th data-column-id="deals">DEALS</th>
                            <th data-column-id="last-interaction">LAST INTERACTION</th>
                            <th style="text-align: center;" data-protected="true" data-column-id="actions">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="contactsTbody">
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

<!-- Contact Details Right-Side Drawer (width: 580px, z-index: 1010) -->
<div class="drawer-overlay" id="contactDrawer" onclick="if(event.target===this) window.contactsApp.closeDrawer()">
    <div class="drawer-content" onclick="event.stopPropagation()">
        
        <!-- Sticky Header -->
        <div class="drawer-header">
            <div>
                <div class="drawer-breadcrumb" id="drawerBreadcrumb">Contacts</div>
                <h3 class="drawer-title" id="drawerContactTitle">Contact Details</h3>
            </div>
            <div class="drawer-header-actions" style="position: relative;">
                <button class="btn btn-ghost btn-xs" id="drawerContactShareBtn" title="Share Contact" aria-label="Share Contact" onclick="if(window.NexFlowShare) window.NexFlowShare.openContactShare(window.contactsApp.getActiveContactId(), this)">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <button class="btn btn-ghost btn-xs" id="drawerEditBtn" title="Edit Contact" onclick="window.contactsApp.openEditModal()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                
                <button class="btn btn-ghost btn-xs" id="drawerHeaderActionsBtn" title="More Actions" onclick="window.contactsApp.toggleDrawerHeaderMenu(event)">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                </button>
                <div class="contacts-drawer-dropdown" id="contactsDrawerMenu">
                    <button class="dropdown-item" onclick="window.contactsApp.openEditModal()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Contact
                    </button>
                    <button class="dropdown-item" onclick="window.contactsApp.openChangeRelationshipModal()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        Change Relationship
                    </button>
                    <button class="dropdown-item" onclick="window.contactsApp.openChangeOwnerModal()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Change Owner
                    </button>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item danger" onclick="window.contactsApp.openDeleteModal()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Delete Contact
                    </button>
                </div>

                <button class="modal-close-btn" onclick="window.contactsApp.closeDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Sticky Tab Row -->
        <div class="drawer-tabs-wrapper">
            <div class="drawer-tabs scrollbar-hide">
                <button class="drawer-tab active" data-tab="overview" onclick="window.contactsApp.switchDrawerTab('overview')">Overview</button>
                <button class="drawer-tab" data-tab="activity" onclick="window.contactsApp.switchDrawerTab('activity')">Activity</button>
                <button class="drawer-tab" data-tab="deals" onclick="window.contactsApp.switchDrawerTab('deals')">Deals</button>
                <button class="drawer-tab" data-tab="tasks" onclick="window.contactsApp.switchDrawerTab('tasks')">Tasks</button>
                <button class="drawer-tab" data-tab="notes" onclick="window.contactsApp.switchDrawerTab('notes')">Notes</button>
            </div>
        </div>

        <!-- Drawer Content Body -->
        <div class="drawer-body" id="drawerContactBody">
            <!-- Dynamic tab content populated by JS -->
        </div>

    </div>
</div>

<!-- Top-Level Child Modals Overlay (z-index: 1100, above Drawer) -->
<div class="contacts-modal-overlay" id="contactsActionModal" onclick="if(event.target===this) window.contactsApp.closeModal()">
    <div class="contacts-modal-card" id="contactsActionModalCard" onclick="event.stopPropagation()">
        <!-- Injected dynamically -->
    </div>
</div>

<!-- Floating Row Action Dropdown Menu -->
<div class="contacts-row-dropdown" id="contactsRowDropdown"></div>

<!-- Toast Notifications Container -->
<div class="contacts-toast-container" id="contactsToastContainer"></div>

<?php include __DIR__ . '/includes/footer.php'; ?>

