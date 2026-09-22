<?php
// Projects Management Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$page_title = "Projects";
$current_page = "projects";

include __DIR__ . '/includes/header.php';
requirePermission('projects');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$pdo = nexflow_db();
$currentUser = nexflow_current_user();
$organizationId = (int)($currentUser['organization_id'] ?? 1);

// Active managers for current organization
$mgrStmt = $pdo->prepare("SELECT id, name FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
$mgrStmt->execute([$organizationId]);
$active_managers = $mgrStmt->fetchAll(PDO::FETCH_ASSOC);

// Initial real KPI metrics from MySQL
$kpiStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_projects,
        COUNT(CASE WHEN status IN ('In Progress', 'Planning') THEN 1 END) AS active_projects,
        COUNT(CASE WHEN status = 'Completed' THEN 1 END) AS completed_projects,
        COUNT(CASE WHEN (status = 'Overdue' OR (status != 'Completed' AND due_date IS NOT NULL AND due_date < CURDATE())) THEN 1 END) AS overdue_projects,
        COUNT(CASE WHEN (status != 'Completed' AND due_date IS NOT NULL AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)) THEN 1 END) AS due_soon_projects
    FROM projects
    WHERE organization_id = ?
");
$kpiStmt->execute([$organizationId]);
$db_kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$kpi_total = (int)($db_kpis['total_projects'] ?? 0);
$kpi_active = (int)($db_kpis['active_projects'] ?? 0);
$kpi_due_soon = (int)($db_kpis['due_soon_projects'] ?? 0);
$kpi_completed = (int)($db_kpis['completed_projects'] ?? 0);
$kpi_overdue = (int)($db_kpis['overdue_projects'] ?? 0);
?>

<main class="main-content projects-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Projects</h1>
                    <span class="projects-total-badge" id="projectsTotalCountBadge"><?php echo $kpi_total; ?> projects</span>
                </div>
                <p class="projects-header-subtitle">Manage internal project deliverables, client milestones, deadlines, and resources.</p>
            </div>
            <div class="projects-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.projectsApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.projectsApp.openCreateModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Create Project
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Bar -->
        <div class="projects-summary-bar">
            <div class="projects-summary-card active" id="kpiCardAll" onclick="window.projectsApp.filterByKPI('total')">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiTotalProjects"><?php echo $kpi_total; ?></span>
                    <span class="projects-summary-lbl">Total Projects</span>
                </div>
            </div>

            <div class="projects-summary-card" id="kpiCardInProgress" onclick="window.projectsApp.filterByKPI('active')">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiActiveProjects"><?php echo $kpi_active; ?></span>
                    <span class="projects-summary-lbl">Active Projects</span>
                </div>
            </div>

            <div class="projects-summary-card" id="kpiCardDueSoon" onclick="window.projectsApp.filterByKPI('due_soon')">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiDueSoonProjects"><?php echo $kpi_due_soon; ?></span>
                    <span class="projects-summary-lbl">Due Soon</span>
                </div>
            </div>

            <div class="projects-summary-card" id="kpiCardCompleted" onclick="window.projectsApp.filterByKPI('completed')">
                <div class="projects-summary-icon" style="background-color: #ECFDF5; color: #047857;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiCompletedProjects"><?php echo $kpi_completed; ?></span>
                    <span class="projects-summary-lbl">Completed</span>
                </div>
            </div>

            <div class="projects-summary-card" id="kpiCardOverdue" onclick="window.projectsApp.filterByKPI('overdue')">
                <div class="projects-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiOverdueProjects"><?php echo $kpi_overdue; ?></span>
                    <span class="projects-summary-lbl">Overdue</span>
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
                    <input type="text" class="projects-search-input" id="projectsSearchInput" placeholder="Search project name, ID, client, manager..." oninput="window.projectsApp.onSearchInput(this.value)">
                    <button type="button" class="projects-search-clear" id="projectsClearSearchBtn" onclick="window.projectsApp.clearSearch()">&times;</button>
                </div>

                <select class="projects-select-filter" id="projectsStatusFilter" style="width:150px;" onchange="window.projectsApp.onStatusFilterChange()">
                    <option value="All">All Statuses</option>
                    <option value="Planning">Planning</option>
                    <option value="In Progress">In Progress</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Completed">Completed</option>
                    <option value="Overdue">Overdue</option>
                </select>

                <select class="projects-select-filter" id="projectsPriorityFilter" style="width:145px;" onchange="window.projectsApp.onFilterChange()">
                    <option value="All">All Priorities</option>
                    <option value="Urgent">Urgent</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                </select>

                <select class="projects-select-filter" id="projectsManagerFilter" style="width:165px;" onchange="window.projectsApp.onFilterChange()">
                    <option value="All">All Managers</option>
                    <?php foreach ($active_managers as $mgr): ?>
                        <option value="<?php echo htmlspecialchars($mgr['name']); ?>"><?php echo htmlspecialchars($mgr['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Anchored Customize Columns Dropdown -->
            <div class="table-columns-dropdown-wrapper">
                <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns" onclick="window.projectsApp.toggleCustomizeColumnsMenu(event)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                    Customize Columns
                </button>
                
                <div class="table-columns-dropdown-menu" id="projectsColumnsDropdownMenu" onclick="event.stopPropagation()">
                    <div class="table-columns-dropdown-header">REORDER &amp; VISIBILITY</div>
                    <div class="table-columns-list" id="projectsColumnsList">
                        <!-- Populated dynamically by assets/js/projects.js -->
                    </div>
                    <div class="table-columns-dropdown-footer">
                        <button type="button" class="table-columns-footer-btn" onclick="window.projectsApp.selectAllColumns()">Show All</button>
                        <button type="button" class="table-columns-footer-btn" onclick="window.projectsApp.hideOptionalColumns()">Hide Optional</button>
                        <button type="button" class="table-columns-footer-btn" onclick="window.projectsApp.resetColumnsToDefault()">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Projects Table Container -->
        <div class="projects-table-container">
            <table class="projects-table">
                <thead id="projectsThead">
                    <!-- Populated dynamically by assets/js/projects.js -->
                </thead>
                <tbody id="projectsTbody">
                    <!-- Populated dynamically by assets/js/projects.js -->
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
</main>

<!-- Right-Side Project Details Drawer -->
<div class="projects-drawer-overlay" id="projectsDetailsDrawer" role="dialog" aria-modal="true" onclick="if(event.target===this) window.projectsApp.closeDetails()">
    <div class="projects-drawer-panel" onclick="event.stopPropagation()">
        <div class="projects-drawer-header">
            <div>
                <div class="projects-drawer-badges-row">
                    <div id="cpdStatusBadge"></div>
                    <div id="cpdPriorityBadge"></div>
                </div>
                <h2 class="projects-drawer-title" id="cpdTitle">Project Details</h2>
            </div>
            <button type="button" class="projects-drawer-close" onclick="window.projectsApp.closeDetails()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="projects-drawer-tabs">
            <button type="button" class="projects-drawer-tab active" data-tab="overview" onclick="window.projectsApp.switchDetailsTab('overview')">Overview</button>
            <button type="button" class="projects-drawer-tab" data-tab="tasks" onclick="window.projectsApp.switchDetailsTab('tasks')">Tasks</button>
            <button type="button" class="projects-drawer-tab" data-tab="milestones" onclick="window.projectsApp.switchDetailsTab('milestones')">Milestones</button>
            <button type="button" class="projects-drawer-tab" data-tab="files" onclick="window.projectsApp.switchDetailsTab('files')">Files</button>
            <button type="button" class="projects-drawer-tab" data-tab="activity" onclick="window.projectsApp.switchDetailsTab('activity')">Activity</button>
        </div>

        <div class="projects-drawer-body" id="projectsDrawerBody">
            <!-- Dynamically injected tab content -->
        </div>

        <div class="projects-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.projectsApp.closeDetails()">Close</button>
        </div>
    </div>
</div>

<!-- Create / Edit Project Drawer -->
<div class="projects-drawer-overlay" id="projectFormDrawer" role="dialog" aria-modal="true" onclick="if(event.target===this) window.projectsApp.closeFormDrawer()">
    <div class="projects-drawer-panel" onclick="event.stopPropagation()">
        <div class="projects-drawer-header">
            <div>
                <h2 class="projects-drawer-title" id="projectModalTitle">Create Project</h2>
                <div class="projects-drawer-subtitle">Specify project deliverables, client associations, timeline, and budget.</div>
            </div>
            <button type="button" class="projects-drawer-close" onclick="window.projectsApp.closeFormDrawer()">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="projects-drawer-body">
            <form id="projectForm" onsubmit="event.preventDefault(); window.projectsApp.saveProjectSubmit();">
                <input type="hidden" id="editProjectId" value="">

                <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Basic Information</div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Project Name *</label>
                    <input type="text" class="input-control input-sm" id="fieldProjectTitle" required placeholder="e.g. E-Commerce Platform">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div style="position:relative;">
                        <label class="form-label">Client / Company *</label>
                        <input type="text" class="input-control input-sm" id="fieldProjectClient" required placeholder="e.g. Acme Corp" autocomplete="off">
                        <div class="nexflow-autocomplete-dropdown" id="projectClientDropdown" style="display:none;"></div>
                    </div>
                    <div>
                        <label class="form-label">Project Manager</label>
                        <select class="input-control input-sm" id="fieldProjectManager">
                            <option value="">Select Manager (Optional)</option>
                            <?php foreach ($active_managers as $mgr): ?>
                                <option value="<?php echo htmlspecialchars($mgr['name']); ?>" data-id="<?php echo (int)$mgr['id']; ?>">
                                    <?php echo htmlspecialchars($mgr['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;" id="projectDealGroup">
                    <label class="form-label">Related Deal</label>
                    <div class="nexflow-autocomplete-wrapper" style="position:relative;">
                        <input type="text" class="input-control input-sm" id="fieldProjectDealSearch" placeholder="Select Deal (Optional)" autocomplete="off" style="padding-right:32px;cursor:pointer;">
                        <input type="hidden" id="fieldProjectDeal" value="">
                        <button type="button" id="btnClearProjectDeal" style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94A3B8;padding:2px;font-size:14px;line-height:1;" title="Clear Deal">✕</button>
                        <div class="nexflow-autocomplete-dropdown" id="projectDealDropdown" style="display:none;"></div>
                    </div>
                </div>

                <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-top:16px;margin-bottom:8px;">Lifecycle & Attributes</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="form-label">Status</label>
                        <select class="input-control input-sm" id="fieldProjectStatus">
                            <option value="Planning" selected>Planning</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Priority</label>
                        <select class="input-control input-sm" id="fieldProjectPriority">
                            <option value="Urgent">Urgent</option>
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="form-label">Start Date</label>
                        <input type="date" class="input-control input-sm" id="fieldProjectStartDate" value="">
                    </div>
                    <div>
                        <label class="form-label">Due Date</label>
                        <input type="date" class="input-control input-sm" id="fieldProjectDueDate" value="">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="form-label">Approved Budget (INR ₹)</label>
                        <input type="number" step="0.01" class="input-control input-sm" id="fieldProjectBudget" placeholder="0.00" value="">
                    </div>
                    <div>
                        <label class="form-label">Progress (%)</label>
                        <input type="number" class="input-control input-sm" id="fieldProjectProgress" min="0" max="100" placeholder="0" value="">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Project Description & Scope</label>
                    <textarea class="input-control" id="fieldProjectDesc" style="height:70px;font-size:12.5px;resize:none;" placeholder="Enter project scope..."></textarea>
                </div>
            </form>
        </div>

        <div class="projects-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.projectsApp.closeFormDrawer()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnSaveProjectSubmit" onclick="window.projectsApp.saveProjectSubmit()">Save Project</button>
        </div>
    </div>
</div>

<script src="assets/js/projects.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
