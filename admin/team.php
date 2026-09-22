<?php
// Team Management Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$page_title = "Team Management";
$current_page = "team";
$page_script = "team.js";

include __DIR__ . '/includes/header.php';
requirePermission('team');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$currentUser = nexflow_current_user();
$orgId = (int)($currentUser['organization_id'] ?? 1);
$pdo = nexflow_db();

// Real initial summary numbers
$totalMembersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE organization_id = $orgId AND status = 'active'")->fetchColumn();
$activeNowCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE organization_id = $orgId AND status = 'active' AND availability IN ('Available', 'Busy')")->fetchColumn();
$adminsCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE organization_id = $orgId AND (role LIKE '%admin%' OR role LIKE '%manager%')")->fetchColumn();
$totalPipelineVal = (float)$pdo->query("SELECT COALESCE(SUM(value), 0) FROM deals WHERE organization_id = $orgId AND status = 'open'")->fetchColumn();
$totalQuotaVal = (float)$pdo->query("SELECT COALESCE(SUM(quota), 0) FROM users WHERE organization_id = $orgId AND status = 'active'")->fetchColumn();
$totalWonRev = (float)$pdo->query("SELECT COALESCE(SUM(value), 0) FROM deals WHERE organization_id = $orgId AND status = 'won'")->fetchColumn();
$targetAchievedVal = $totalQuotaVal > 0 ? round(($totalWonRev / $totalQuotaVal) * 100, 1) : 0.0;
?>

<main class="main-content team-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Team</h1>
                    <span class="projects-total-badge" id="teamHeaderTotalBadge"><?php echo $totalMembersCount; ?> members</span>
                </div>
                <p class="projects-header-subtitle">Manage team members, roles, permissions, and access.</p>
            </div>
            
            <div class="projects-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.teamApp.switchTab('roles')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Manage Roles
                </button>

                <!-- Export Menu -->
                <div class="team-dropdown-wrapper" id="teamExportDropdownWrapper">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnTeamExportDropdown">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="team-dropdown-menu team-dropdown-menu-right" id="teamExportMenu">
                        <button type="button" class="team-dropdown-item" onclick="window.teamApp.exportCSV()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Export Members as CSV
                        </button>
                        <button type="button" class="team-dropdown-item" onclick="window.teamApp.printPDF()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Print / Save as PDF
                        </button>
                    </div>
                </div>

                <!-- Primary Action -->
                <button type="button" class="btn btn-primary btn-sm" onclick="window.teamApp.openInviteDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Invite Member
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Grid -->
        <div class="projects-summary-bar">
            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryTotalMembers"><?php echo $totalMembersCount; ?></span>
                    <span class="projects-summary-lbl">Total Members</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryActiveNow"><?php echo $activeNowCount; ?></span>
                    <span class="projects-summary-lbl">Active Now</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #F5F3FF; color: #7C3AED;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryAdmins"><?php echo $adminsCount; ?></span>
                    <span class="projects-summary-lbl">Admins &amp; Managers</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12V7H5a2 2 0 010-4h14v4"/><path d="M3 5v14a2 2 0 002 2h16v-5"/><path d="M18 12a2 2 0 100 4 2 2 0 000-4z"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryPipelineVal">$<?php echo number_format($totalPipelineVal); ?></span>
                    <span class="projects-summary-lbl">Team Pipeline</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryTargetAchieved"><?php echo $targetAchievedVal; ?>%</span>
                    <span class="projects-summary-lbl">Target Quota</span>
                </div>
            </div>
        </div>

        <!-- 3. Team Tab Navigation -->
        <div class="team-tabs-bar">
            <div class="team-tabs-list" role="tablist">
                <button type="button" class="team-tab-btn active" role="tab" aria-selected="true" data-tab="members" onclick="window.teamApp.switchTab('members', this)">Members</button>
                <button type="button" class="team-tab-btn" role="tab" aria-selected="false" data-tab="roles" onclick="window.teamApp.switchTab('roles', this)">Roles &amp; Permissions</button>
                <button type="button" class="team-tab-btn" role="tab" aria-selected="false" data-tab="workload" onclick="window.teamApp.switchTab('workload', this)">Workload</button>
                <button type="button" class="team-tab-btn" role="tab" aria-selected="false" data-tab="performance" onclick="window.teamApp.switchTab('performance', this)">Performance</button>
            </div>
        </div>

        <!-- 4. TAB CONTENTS -->
        <div class="team-tab-body">

            <!-- TAB 1: MEMBERS -->
            <div class="team-tab-pane active" id="pane-members">
                <!-- Toolbar -->
                <div class="team-toolbar">
                    <div class="team-toolbar-left">
                        <!-- Search Box -->
                        <div class="team-search-box">
                            <input type="text" class="input-control input-sm" id="teamSearchInput" placeholder="Search team members by name, email, or role..." oninput="window.teamApp.handleSearchFilter()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="team-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>

                        <!-- Department Select -->
                        <select class="input-control input-sm" id="teamDeptSelect" onchange="window.teamApp.handleSearchFilter()">
                            <option value="all">All Departments</option>
                            <option value="Sales">Sales</option>
                            <option value="Account Management">Account Management</option>
                            <option value="Business Development">Business Development</option>
                            <option value="Operations">Operations</option>
                            <option value="Customer Success">Customer Success</option>
                        </select>

                        <!-- Role Select -->
                        <select class="input-control input-sm" id="teamRoleSelect" onchange="window.teamApp.handleSearchFilter()">
                            <option value="all">All Roles</option>
                            <option value="Sales Manager">Sales Manager</option>
                            <option value="Senior Sales Representative">Senior Sales Rep</option>
                            <option value="Sales Representative">Sales Rep</option>
                            <option value="Account Executive">Account Executive</option>
                            <option value="Business Development Rep">Business Dev Rep</option>
                            <option value="Sales Operations">Sales Operations</option>
                        </select>

                        <!-- Availability Select -->
                        <select class="input-control input-sm" id="teamAvailSelect" onchange="window.teamApp.handleSearchFilter()">
                            <option value="all">All Availability</option>
                            <option value="Available">Available</option>
                            <option value="Busy">Busy</option>
                            <option value="Away">Away</option>
                            <option value="Offline">Offline</option>
                        </select>

                        <!-- Sort Select -->
                        <select class="input-control input-sm" id="teamSortSelect" onchange="window.teamApp.handleSearchFilter()">
                            <option value="name">Name A–Z</option>
                            <option value="revenue">Highest Revenue</option>
                            <option value="target">Target Completion</option>
                            <option value="deals">Most Open Deals</option>
                            <option value="workload">Highest Workload</option>
                        </select>
                    </div>

                    <div class="team-toolbar-right">
                        <!-- View Mode Switcher -->
                        <div class="team-view-toggle">
                            <button type="button" class="team-view-btn active" id="btnViewList" onclick="window.teamApp.setViewMode('list')" title="List View">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            </button>
                            <button type="button" class="team-view-btn" id="btnViewGrid" onclick="window.teamApp.setViewMode('grid')" title="Grid View">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active Chips Row -->
                <div class="team-chips-row" id="teamActiveChipsRow">
                    <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Active Filters:</span>
                    <div id="teamChipsContainer" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;"></div>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.teamApp.resetFilters()" style="margin-left: auto;">Reset Filters</button>
                </div>

                <!-- Floating Bulk Toolbar (Shown when >= 1 checkbox checked) -->
                <div class="team-bulk-toolbar" id="teamBulkToolbar">
                    <span style="font-size: 12.5px; font-weight: 600; color: #FFFFFF;" id="teamBulkSelectedCount">0 selected</span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="showTeamToast('Bulk change role...')">Change Role</button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="showTeamToast('Bulk change availability...')">Change Availability</button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="showTeamToast('Bulk assign leads...')">Assign Leads</button>
                        <button type="button" class="btn btn-ghost btn-xs" style="color: #F87171;" onclick="window.teamApp.bulkDeactivate()">Deactivate</button>
                    </div>
                </div>

                <!-- List View Container -->
                <div class="team-view-container" id="teamListView">
                    <div class="team-table-wrapper">
                        <table class="team-table" id="teamMembersTable">
                            <thead>
                                <tr>
                                    <th data-protected="true" data-column-id="team-member">Team Member</th>
                                    <th data-column-id="role">Role &amp; Department</th>
                                    <th data-column-id="availability">Availability</th>
                                    <th data-column-id="assigned-leads">Assigned Leads</th>
                                    <th data-column-id="open-deals">Open Deals</th>
                                    <th data-column-id="pipeline-value">Pipeline Value</th>
                                    <th data-column-id="revenue">Revenue</th>
                                    <th data-column-id="target-progress">Target Progress</th>
                                    <th data-column-id="workload">Workload</th>
                                    <th data-column-id="last-active">Last Active</th>
                                    <th style="text-align: right;" data-protected="true" data-column-id="actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="teamMembersTableBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grid View Container (Hidden by default) -->
                <div class="team-view-container" id="teamGridView" style="display: none;">
                    <div class="team-cards-grid" id="teamMembersCardsGrid">
                        <!-- Populated dynamically by JS -->
                    </div>
                </div>

                <!-- Empty State (Hidden when items exist) -->
                <div class="team-empty-state" id="teamMembersEmptyState" style="display: none;">
                    <div style="font-size: 28px; margin-bottom: 8px;">👥</div>
                    <div style="font-size: 14px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px;">No Team Members Found</div>
                    <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 14px;">No members match your search query or selected filter criteria.</p>
                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.resetFilters()">Reset All Filters</button>
                </div>
            </div>

            <!-- TAB 2: ROLES & PERMISSIONS -->
            <div class="team-tab-pane" id="pane-roles">
                <div class="team-notice-banner">
                    💡 <strong>Frontend Permission Preview:</strong> These settings represent a frontend role design. Real permission enforcement requires backend authorization.
                </div>

                <!-- Role Cards Overview -->
                <div class="team-roles-grid" id="teamRoleCardsGrid">
                    <!-- Populated dynamically by JS -->
                </div>

                <!-- Permissions Matrix Table -->
                <div class="team-card" style="margin-top: 18px;">
                    <div class="team-card-header">
                        <div>
                            <h3 class="team-card-title">Feature Permission Matrix</h3>
                            <p class="team-card-subtitle">Access rights breakdown across CRM modules.</p>
                        </div>
                    </div>
                    <div class="team-table-wrapper">
                        <table class="team-table" id="teamPermissionMatrixTable">
                            <thead>
                                <tr>
                                    <th data-protected="true" data-column-id="module">CRM Module / Feature</th>
                                    <th data-column-id="administrator">Administrator</th>
                                    <th data-column-id="sales-manager">Sales Manager</th>
                                    <th data-column-id="senior-sales-rep">Senior Sales Rep</th>
                                    <th data-column-id="sales-rep">Sales Rep</th>
                                    <th data-column-id="account-executive">Account Executive</th>
                                    <th data-column-id="sales-operations">Sales Operations</th>
                                    <th data-column-id="viewer">Viewer</th>
                                </tr>
                            </thead>
                            <tbody id="teamPermissionMatrixBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: WORKLOAD -->
            <div class="team-tab-pane" id="pane-workload">
                <div class="team-workload-summary" id="teamWorkloadSummaryGrid">
                    <!-- 4 Summary items rendered by JS -->
                </div>

                <div class="team-card" style="margin-top: 18px;">
                    <div class="team-card-header">
                        <div>
                            <h3 class="team-card-title">Team Member Workload &amp; Capacity</h3>
                            <p class="team-card-subtitle">Lead and deal distribution across team members.</p>
                        </div>
                    </div>
                    <div class="team-table-wrapper">
                        <table class="team-table" id="teamWorkloadTable">
                            <thead>
                                <tr>
                                    <th data-protected="true" data-column-id="team-member">Team Member</th>
                                    <th data-column-id="assigned-leads">Assigned Leads</th>
                                    <th data-column-id="open-deals">Open Deals</th>
                                    <th data-column-id="tasks-due">Tasks Due</th>
                                    <th data-column-id="meetings">Meetings</th>
                                    <th data-column-id="est-capacity">Est. Capacity</th>
                                    <th data-column-id="workload-status">Workload Status</th>
                                    <th style="text-align: right;" data-protected="true" data-column-id="actions">Action</th>
                                </tr>
                            </thead>
                            <tbody id="teamWorkloadTableBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PERFORMANCE -->
            <div class="team-tab-pane" id="pane-performance">
                <div class="team-summary-grid">
                    <div class="team-summary-card">
                        <div class="team-summary-label">Total Team Revenue</div>
                        <div class="team-summary-val" id="perfTotalRevenue" style="color: #10B981;">$<?php echo number_format($totalWonRev); ?></div>
                        <div class="team-summary-sub">Closed Won total</div>
                    </div>
                    <div class="team-summary-card">
                        <div class="team-summary-label">Team Quota Target</div>
                        <div class="team-summary-val" id="perfTeamQuota">$<?php echo number_format($totalQuotaVal); ?></div>
                        <div class="team-summary-sub" id="perfQuotaAchievedSub"><?php echo $targetAchievedVal; ?>% achieved overall</div>
                    </div>
                    <div class="team-summary-card">
                        <div class="team-summary-label">Average Win Rate</div>
                        <div class="team-summary-val" id="perfAvgWinRate">0.0%</div>
                        <div class="team-summary-sub">Cross-team average</div>
                    </div>
                    <div class="team-summary-card">
                        <div class="team-summary-label">Avg Sales Cycle</div>
                        <div class="team-summary-val" id="perfAvgSalesCycle">26 days</div>
                        <div class="team-summary-sub">From lead to win</div>
                    </div>
                </div>

                <div class="team-card" style="margin-top: 18px;">
                    <div class="team-card-header">
                        <h3 class="team-card-title">Sales Performance Leaderboard</h3>
                    </div>
                    <div class="team-table-wrapper">
                        <table class="team-table" id="teamPerformanceLeaderboardTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" data-protected="true" data-column-id="rank">Rank</th>
                                    <th data-protected="true" data-column-id="representative">Representative</th>
                                    <th data-column-id="role">Role</th>
                                    <th data-column-id="revenue-won">Revenue Won</th>
                                    <th data-column-id="quota-target">Quota Target</th>
                                    <th data-column-id="attainment">Attainment</th>
                                    <th data-column-id="deals-won">Deals Won</th>
                                    <th data-column-id="win-rate">Win Rate</th>
                                    <th data-column-id="activities">Activities</th>
                                </tr>
                            </thead>
                            <tbody id="teamPerformanceLeaderboardBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div> <!-- End team-tab-body -->
    </div> <!-- End page-container -->
</main>

<!-- 5. Member Details Drawer (500px wide right-side slide-over) -->
<div class="team-member-drawer" id="teamMemberDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="memberDrawerTitle">
    <div class="team-drawer-panel">
        <!-- Drawer Header -->
        <div class="team-drawer-header">
            <div>
                <div style="font-size: 11px; color: var(--text-muted); font-weight: 500; margin-bottom: 2px;">
                    Team / <span id="drawerMemberBreadcrumb">Member Details</span>
                </div>
                <h3 class="team-drawer-title" id="memberDrawerTitle">Member Profile</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <button type="button" class="btn btn-secondary btn-xs" id="btnEditCurrentMember">Edit Member</button>
                <button type="button" class="btn btn-ghost btn-xs" onclick="window.teamApp.closeMemberDrawer()" aria-label="Close drawer" style="padding: 4px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Drawer Content Body -->
        <div class="team-drawer-body">
            <!-- Profile Top Card -->
            <div class="team-drawer-profile-card" id="drawerProfileCard">
                <!-- Rendered dynamically by JS -->
            </div>

            <!-- Drawer Tabs Bar -->
            <div class="team-drawer-tabs">
                <button type="button" class="team-drawer-tab-btn active" data-dtab="overview" onclick="window.teamApp.switchDrawerTab('overview', this)">Overview</button>
                <button type="button" class="team-drawer-tab-btn" data-dtab="performance" onclick="window.teamApp.switchDrawerTab('performance', this)">Performance</button>
                <button type="button" class="team-drawer-tab-btn" data-dtab="assignments" onclick="window.teamApp.switchDrawerTab('assignments', this)">Assignments</button>
                <button type="button" class="team-drawer-tab-btn" data-dtab="activity" onclick="window.teamApp.switchDrawerTab('activity', this)">Activity</button>
                <button type="button" class="team-drawer-tab-btn" data-dtab="notes" onclick="window.teamApp.switchDrawerTab('notes', this)">Notes</button>
            </div>

            <!-- Drawer Tab Panes -->
            <div class="team-drawer-panes">
                <!-- Drawer Tab: Overview -->
                <div class="team-drawer-pane active" id="dpane-overview">
                    <!-- Rendered by JS -->
                </div>

                <!-- Drawer Tab: Performance -->
                <div class="team-drawer-pane" id="dpane-performance">
                    <!-- Rendered by JS -->
                </div>

                <!-- Drawer Tab: Assignments -->
                <div class="team-drawer-pane" id="dpane-assignments">
                    <!-- Rendered by JS -->
                </div>

                <!-- Drawer Tab: Activity -->
                <div class="team-drawer-pane" id="dpane-activity">
                    <!-- Rendered by JS -->
                </div>

                <!-- Drawer Tab: Notes -->
                <div class="team-drawer-pane" id="dpane-notes">
                    <!-- Rendered by JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 6. Invite / Edit Member Drawer (460px wide) -->
<div class="team-invite-drawer" id="teamInviteDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="inviteModalTitle">
    <div class="team-invite-panel">
        <div class="team-drawer-header">
            <div>
                <h3 class="team-drawer-title" id="inviteModalTitle">Invite Team Member</h3>
                <p class="team-drawer-subtitle">Add a new member to your sales organization.</p>
            </div>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.teamApp.closeInviteDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="inviteMemberForm" style="display: flex; flex-direction: column; height: calc(100% - 65px);">
            <div class="team-drawer-body">
                <input type="hidden" id="inviteEditMemberId" value="">

                <!-- Member Info Section -->
                <div class="team-form-section">
                    <div class="team-form-section-title">Member Information</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div class="form-group">
                            <label class="form-label" for="inviteFirstName">First Name *</label>
                            <input type="text" class="input-control input-sm" id="inviteFirstName" required placeholder="e.g. Alex">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="inviteLastName">Last Name *</label>
                            <input type="text" class="input-control input-sm" id="inviteLastName" required placeholder="e.g. Morgan">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="inviteEmail">Work Email *</label>
                        <input type="email" class="input-control input-sm" id="inviteEmail" required placeholder="alex@NexFlowcrm.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="invitePhone">Phone</label>
                        <input type="text" class="input-control input-sm" id="invitePhone" placeholder="+1 (415) 555-0199">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div class="form-group">
                            <label class="form-label" for="inviteJobTitle">Job Title *</label>
                            <input type="text" class="input-control input-sm" id="inviteJobTitle" required placeholder="e.g. Account Executive">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="inviteDepartment">Department *</label>
                            <select class="input-control input-sm" id="inviteDepartment" required>
                                <option value="Sales">Sales</option>
                                <option value="Account Management">Account Management</option>
                                <option value="Business Development">Business Development</option>
                                <option value="Operations">Operations</option>
                                <option value="Customer Success">Customer Success</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="inviteLocation">Location</label>
                        <input type="text" class="input-control input-sm" id="inviteLocation" placeholder="San Francisco, CA">
                    </div>
                </div>

                <!-- Access Setup Section -->
                <div class="team-form-section">
                    <div class="team-form-section-title">Access &amp; Role Setup</div>
                    <div class="form-group">
                        <label class="form-label" for="inviteRole">Role *</label>
                        <select class="input-control input-sm" id="inviteRole" required onchange="window.teamApp.updateInviteRolePreview(this.value)">
                            <option value="Sales Representative">Sales Representative</option>
                            <option value="Senior Sales Representative">Senior Sales Representative</option>
                            <option value="Sales Manager">Sales Manager</option>
                            <option value="Account Executive">Account Executive</option>
                            <option value="Business Development Rep">Business Development Rep</option>
                            <option value="Sales Operations">Sales Operations</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div class="form-group">
                            <label class="form-label" for="inviteManager">Reports To</label>
                            <select class="input-control input-sm" id="inviteManager">
                                <option value="Olivia Martin">Olivia Martin (Sales Manager)</option>
                                <option value="Executive Director">Executive Director</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="inviteAvailability">Initial Status</label>
                            <select class="input-control input-sm" id="inviteAvailability">
                                <option value="Available">Available</option>
                                <option value="Busy">Busy</option>
                                <option value="Away">Away</option>
                                <option value="Offline">Offline</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Permissions & Access Section -->
                <div class="team-form-section">
                    <div class="perm-header-bar">
                        <div class="perm-header-title-box">
                            <h4 class="perm-header-title">Permissions &amp; Access</h4>
                            <p class="perm-header-subtitle">Choose which areas this employee can access and what actions they can perform.</p>
                        </div>
                        <span class="perm-summary-badge" id="permSummaryBadge">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            0 Modules • 0 Permissions Enabled
                        </span>
                    </div>

                    <div class="perm-toolbar" style="margin-top: 8px;">
                        <div class="perm-toolbar-buttons">
                            <button type="button" class="btn-full-access" id="btnToggleFullAccess" onclick="window.teamApp.toggleFullAccess()">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                Enable Full Access
                            </button>
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.selectAllPermissions()">Select All</button>
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.clearAllPermissions()">Clear All</button>
                        </div>
                        <input type="text" class="perm-search-control" id="permSearchInput" placeholder="Search permissions..." oninput="window.teamApp.filterPermissions(this.value)">
                    </div>

                    <div class="perm-section-container" id="permListContainer">
                        <!-- Rendered dynamically by JavaScript -->
                    </div>
                </div>

                <div class="team-notice-small">
                    💡 This frontend demo adds the member locally. Real invitations and account creation require authentication, email services and backend functionality.
                </div>
            </div>

            <div class="team-drawer-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.teamApp.closeInviteDrawer()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitInviteForm">Add Demo Member</button>
            </div>
        </form>
    </div>
</div>

<!-- 7. Reassign Work Drawer (420px wide) -->
<div class="team-reassign-drawer" id="teamReassignDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="reassignModalTitle">
    <div class="team-reassign-panel">
        <div class="team-drawer-header">
            <div>
                <h3 class="team-drawer-title" id="reassignModalTitle">Reassign Workload</h3>
                <p class="team-drawer-subtitle">Reassign leads or deals to balance capacity.</p>
            </div>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.teamApp.closeReassignDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="reassignWorkForm" style="display: flex; flex-direction: column; height: calc(100% - 65px);">
            <div class="team-drawer-body">
                <div class="form-group">
                    <label class="form-label" for="reassignSourceRep">Reassign From (Source) *</label>
                    <select class="input-control input-sm" id="reassignSourceRep" required>
                        <!-- Populated by JS -->
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reassignTargetRep">Reassign To (Destination) *</label>
                    <select class="input-control input-sm" id="reassignTargetRep" required>
                        <!-- Populated by JS -->
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reassignRecordType">Record Type</label>
                    <select class="input-control input-sm" id="reassignRecordType">
                        <option value="leads">Assigned Leads (3 records)</option>
                        <option value="deals">Open Deals (2 records)</option>
                        <option value="tasks">Open Tasks (4 records)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Reassignment</label>
                    <input type="text" class="input-control input-sm" placeholder="e.g. Balancing overloaded capacity">
                </div>
            </div>

            <div class="team-drawer-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.teamApp.closeReassignDrawer()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Reassign Demo Records</button>
            </div>
        </form>
    </div>
</div>
<!-- 8. Assign Lead Modal -->
<div class="team-child-modal-layer" id="teamAssignLeadModal" role="dialog" aria-modal="true" aria-labelledby="assignLeadModalTitle">
    <div class="team-child-modal-backdrop" onclick="window.teamApp.closeAssignLeadModal()"></div>
    <div class="team-child-modal-panel">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="assignLeadModalTitle" style="margin:0;">Assign Lead</h3>
                <p style="margin:4px 0 0; font-size:13px; color:var(--text-muted);" id="assignLeadModalSubtitle">Assign a lead to member</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="window.teamApp.closeAssignLeadModal()" aria-label="Close modal">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body" style="display:flex; flex-direction:column; overflow:hidden; padding:24px;">
            <div id="assignLeadMemberSummary" style="display:flex; align-items:center; gap:12px; margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid var(--border-card); flex-shrink:0;">
                <!-- Populated dynamically by JS -->
            </div>
            
            <div style="margin-bottom:20px; flex-shrink:0;">
                <label class="form-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text-secondary);">Select Lead</label>
                <input type="text" class="input-control" id="assignLeadSearchInput" placeholder="Search leads by name, company or email..." onkeyup="window.teamApp.filterAssignLeads()">
            </div>
            
            <div class="assign-lead-list-container" id="assignLeadListContainer" style="flex:1; overflow-y:auto; border:1px solid var(--border-card); border-radius:8px; margin-bottom:20px;">
                <!-- Leads list populated dynamically by JS -->
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; flex-shrink:0;">
                <div>
                    <label class="form-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text-secondary);">Assignment Type</label>
                    <select class="input-control" id="assignLeadType">
                        <option value="Primary Owner">Primary Owner</option>
                        <option value="Collaborator">Collaborator</option>
                        <option value="Temporary Assignment">Temporary Assignment</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text-secondary);">Optional Note</label>
                    <input type="text" class="input-control" id="assignLeadNote" placeholder="e.g. Please follow up on proposal">
                </div>
            </div>
            <div style="margin-top:16px; flex-shrink:0;">
                <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; color:var(--text-secondary); cursor:pointer;">
                    <input type="checkbox" id="assignLeadNotifyCb" checked>
                    Notify member of this assignment
                </label>
            </div>
        </div>
        <div class="modal-footer" style="background:#F9FAFB;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.teamApp.closeAssignLeadModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnSubmitAssignLead" onclick="window.teamApp.submitAssignLead()" disabled>Assign Lead</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

