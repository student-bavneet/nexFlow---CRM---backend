<?php
// client-projects.php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-projects-data.php';

$client = require_client_auth();
$pdo = nexflow_db();

$orgId       = (int)$client['organization_id'];
$companyId   = (int)$client['company_id'];
$contactId   = (int)$client['contact_id'];
$companyName = (string)$client['company_name'];

$kpis     = client_get_projects_kpis($pdo, $orgId, $companyName, $contactId);
$projects = client_get_projects_list($pdo, $orgId, $companyName, $contactId);
$currency = client_get_projects_currency($pdo, $orgId);

$page_title   = "Projects — NexFlow Client Portal";
$active_nav   = "projects";
$page_heading = "Projects";
include __DIR__ . '/includes/client-header.php';
?>
<link rel="stylesheet" href="assets/css/client-projects.css">
<?php
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <!-- Page Header Description -->
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Track your active projects, progress, deadlines, and project details.</p>
        </div>

        <!-- Summary KPI Cards -->
        <div class="client-portal-kpi-grid">
            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon blue">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiTotalProjects"><?php echo (int)$kpis['total']; ?></div>
                    <div class="client-portal-kpi-label">Total Projects</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon emerald">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiActiveProjects"><?php echo (int)$kpis['active']; ?></div>
                    <div class="client-portal-kpi-label">Active Projects</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon green">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiCompletedProjects"><?php echo (int)$kpis['completed']; ?></div>
                    <div class="client-portal-kpi-label">Completed Projects</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon amber">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiUpcomingDeadlines"><?php echo (int)$kpis['upcoming']; ?></div>
                    <div class="client-portal-kpi-label">Upcoming Deadlines</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="client-portal-toolbar">
            <div class="client-portal-toolbar-left">
                <div class="client-portal-search-wrapper" style="width:280px;">
                    <svg class="client-portal-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="client-portal-search-input" id="projectsSearchInput" placeholder="Search projects or manager..." oninput="window.renderClientProjects()">
                </div>

                <select class="input-control" id="projectsStatusFilter" style="width:160px;height:36px;" onchange="window.renderClientProjects()">
                    <option value="All">All Statuses</option>
                    <option value="Not Started">Not Started</option>
                    <option value="Planning">Planning</option>
                    <option value="In Progress">In Progress</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <div class="client-portal-toolbar-right">
                <select class="input-control" id="projectsSortSelect" style="width:170px;height:36px;" onchange="window.renderClientProjects()">
                    <option value="newest">Newest Start Date</option>
                    <option value="oldest">Oldest Start Date</option>
                    <option value="deadline">Upcoming Deadline</option>
                    <option value="progress">Highest Progress</option>
                    <option value="name">Project Name (A-Z)</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('New Project Request')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Request New Project
                </button>
            </div>
        </div>

        <!-- Projects Cards Grid -->
        <div class="client-projects-grid" id="clientProjectsGrid">
            <!-- Populated dynamically by assets/js/client-projects.js from live authorized dataset -->
        </div>

        <!-- Empty State Container -->
        <div id="clientProjectsEmptyState" style="display:none;margin-top:20px;">
            <div style="text-align:center;padding:48px 20px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;">
                <svg width="48" height="48" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px auto;display:block;">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
                <h3 style="font-size:16px;font-weight:700;color:#0F172A;margin:0 0 6px 0;">No Projects Found</h3>
                <p style="font-size:13px;color:#64748B;margin:0;">You currently don't have any projects matching your search or status criteria.</p>
            </div>
        </div>

    </main>
</div>

<!-- Dedicated Right-Side Project Details Drawer -->
<div class="client-portal-drawer-overlay" id="clientProjectDrawer" role="dialog" aria-modal="true" aria-labelledby="cpdProjectTitle" onclick="if(event.target===this) window.clientProjects.closeProjectDrawer()">
    <div class="client-portal-drawer-panel" onclick="event.stopPropagation()">
        <div class="client-portal-drawer-header">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <div class="client-portal-drawer-badge" id="cpdStatusBadge">In Progress</div>
                    <div class="client-portal-drawer-badge amber" id="cpdPriorityBadge">High Priority</div>
                </div>
                <h3 class="client-portal-drawer-title" id="cpdProjectTitle">Project Details</h3>
            </div>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientProjects.closeProjectDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        
        <!-- Drawer Tab Switcher -->
        <div class="client-portal-drawer-tabs" role="tablist" aria-label="Project details navigation">
            <button type="button" class="client-portal-drawer-tab active" role="tab" aria-selected="true" data-tab="overview" onclick="window.clientProjects.switchProjectDrawerTab('overview')">Overview</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="milestones" onclick="window.clientProjects.switchProjectDrawerTab('milestones')">Milestones</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="tasks" onclick="window.clientProjects.switchProjectDrawerTab('tasks')">Tasks</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="files" onclick="window.clientProjects.switchProjectDrawerTab('files')">Files</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="activity" onclick="window.clientProjects.switchProjectDrawerTab('activity')">Activity</button>
        </div>

        <!-- Drawer Scrollable Body -->
        <div class="client-portal-drawer-body" id="clientProjectDrawerBody" tabindex="0">
            <!-- Dynamically injected tab panels by assets/js/client-projects.js -->
        </div>

        <div class="client-portal-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientProjects.closeProjectDrawer()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Project Discussion')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Message Project Lead
            </button>
        </div>
    </div>
</div>

<script>
window.__CLIENT_PROJECTS_DATA__ = <?php echo json_encode([
    'kpis'     => $kpis,
    'projects' => $projects,
    'currency' => $currency,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="assets/js/client-projects.js"></script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>