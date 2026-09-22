<?php
// Leads Management Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$page_title = "Leads";
$current_page = "leads";
$page_script = "leads.js";

include __DIR__ . '/includes/header.php';
requirePermission('leads');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$currentUser = nexflow_current_user();
$orgId = (int)($currentUser['organization_id'] ?? 1);
$pdo = nexflow_db();

if (!function_exists('leads_get_initials')) {
    function leads_get_initials(string $name, ?string $first = null, ?string $last = null): string {
        if ($first && $last) {
            return strtoupper(substr(trim($first), 0, 1) . substr(trim($last), 0, 1));
        }
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($name, 0, min(2, strlen($name))));
    }
}
if (!function_exists('leads_avatar_color')) {
    function leads_avatar_color(int $id): string {
        $colors = ['#7C3AED', '#0284C7', '#10B981', '#F59E0B', '#6366F1', '#EC4899', '#14B8A6', '#8B5CF6'];
        return $colors[$id % count($colors)];
    }
}
if (!function_exists('leads_relative_time')) {
    function leads_relative_time(?string $datetime): string {
        if (!$datetime) return 'Never';
        $time = strtotime($datetime);
        if (!$time) return (string)$datetime;
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $time);
    }
}

// 1. Dynamic status counts
$status_counts = ['All' => 0, 'New' => 0, 'Contacted' => 0, 'Qualified' => 0, 'Proposal' => 0, 'Won' => 0, 'Lost' => 0];
$countStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM leads WHERE organization_id = ? AND is_archived = 0 GROUP BY status");
$countStmt->execute([$orgId]);
$totalLeads = 0;
while ($c = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($status_counts[$c['status']])) {
        $status_counts[$c['status']] = (int)$c['cnt'];
    }
    $totalLeads += (int)$c['cnt'];
}
$status_counts['All'] = $totalLeads;

// 2. Active organization team members for assignees
$userStmt = $pdo->prepare("SELECT id, name, first_name, last_name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
$userStmt->execute([$orgId]);
$orgUsers = [];
while ($u = $userStmt->fetch(PDO::FETCH_ASSOC)) {
    $uName = trim($u['name'] ?: ($u['first_name'] . ' ' . $u['last_name']));
    $orgUsers[] = [
        'id' => (int)$u['id'],
        'name' => $uName,
        'email' => $u['email'],
        'initials' => leads_get_initials($uName, $u['first_name'], $u['last_name']),
        'color' => leads_avatar_color((int)$u['id'])
    ];
}

// 3. Initial leads list
$leadsStmt = $pdo->prepare("
    SELECT l.*, 
           u.name AS assignee_name, 
           u.first_name AS assignee_first, 
           u.last_name AS assignee_last,
           (SELECT MAX(created_at) FROM team_activities WHERE organization_id = ? AND related_entity = 'leads' AND related_entity_id = l.id) AS last_activity_at
    FROM leads l
    LEFT JOIN users u ON u.id = l.assigned_to
    WHERE l.organization_id = ? AND l.is_archived = 0
    ORDER BY l.id DESC
");
$leadsStmt->execute([$orgId, $orgId]);
$rawLeads = $leadsStmt->fetchAll(PDO::FETCH_ASSOC);

$leads = [];
foreach ($rawLeads as $row) {
    $fullName = trim(($row['name'] ?? '') ?: (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
    if (empty($fullName)) $fullName = 'Lead #' . $row['id'];
    $assigneeName = $row['assignee_name'] ?? (trim(($row['assignee_first'] ?? '') . ' ' . ($row['assignee_last'] ?? '')) ?: 'Unassigned');
    $assigneeInitials = leads_get_initials($assigneeName, $row['assignee_first'] ?? null, $row['assignee_last'] ?? null);
    $assigneeColor = leads_avatar_color((int)($row['assigned_to'] ?? $row['id']));
    $lastAct = $row['last_activity_at'] ?? $row['updated_at'] ?? $row['created_at'];

    $leads[] = [
        'id' => (string)$row['id'],
        'lead_code' => $row['lead_code'] ?? ('LD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)),
        'name' => $fullName,
        'firstName' => $row['first_name'] ?? '',
        'lastName' => $row['last_name'] ?? '',
        'email' => $row['email'] ?? '',
        'phone' => $row['phone'] ?? '',
        'whatsapp' => $row['whatsapp'] ?? '',
        'company' => $row['company'] ?? '',
        'jobTitle' => $row['job_title'] ?? '',
        'location' => $row['location'] ?? '',
        'status' => $row['status'] ?? 'New',
        'score' => (int)($row['score'] ?? 50),
        'value' => (float)($row['value'] ?? 0),
        'source' => $row['source'] ?? 'Website',
        'assignee' => $assigneeName,
        'assigneeId' => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
        'assigneeInitials' => $assigneeInitials,
        'assigneeColor' => $assigneeColor,
        'createdAt' => date('M j, Y', strtotime($row['created_at'])),
        'lastActivity' => leads_relative_time($lastAct),
        'isArchived' => (bool)($row['is_archived'] ?? 0)
    ];
}
?>

<main class="main-content">
    <div class="page-container">
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Leads</h1>
                    <span class="projects-total-badge" id="leadsTotalCountBadge"><?php echo count($leads); ?> leads</span>
                </div>
                <p class="projects-header-subtitle">Manage prospects, qualification, follow-ups, and sales opportunities.</p>
            </div>
            <div class="projects-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="exportLeadsCsv()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddLeadModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Add Lead
                </button>
            </div>
        </div>

        <!-- 6-Card KPI Summary Bar & Primary Status Filters -->
        <div class="leads-summary-bar">
            <div class="projects-summary-card lead-kpi-card" data-status="All">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiLeadTotal"><?php echo count($leads); ?></span>
                    <span class="projects-summary-lbl">Total Leads</span>
                </div>
            </div>

            <div class="projects-summary-card lead-kpi-card" data-status="New">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiLeadNew"><?php echo $status_counts['New'] ?? 0; ?></span>
                    <span class="projects-summary-lbl">New</span>
                </div>
            </div>

            <div class="projects-summary-card lead-kpi-card" data-status="Contacted">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiLeadContacted"><?php echo $status_counts['Contacted'] ?? 0; ?></span>
                    <span class="projects-summary-lbl">Contacted</span>
                </div>
            </div>

            <div class="projects-summary-card lead-kpi-card" data-status="Qualified">
                <div class="projects-summary-icon" style="background-color: #F0FDF4; color: #16A34A;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiLeadQualified"><?php echo $status_counts['Qualified'] ?? 0; ?></span>
                    <span class="projects-summary-lbl">Qualified</span>
                </div>
            </div>

            <div class="projects-summary-card lead-kpi-card" data-status="Won">
                <div class="projects-summary-icon" style="background-color: #FAF5FF; color: #9333EA;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiLeadWon"><?php echo $status_counts['Won'] ?? 0; ?></span>
                    <span class="projects-summary-lbl">Won</span>
                </div>
            </div>

            <div class="projects-summary-card lead-kpi-card" data-status="Lost">
                <div class="projects-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="kpiLeadLost"><?php echo $status_counts['Lost'] ?? 0; ?></span>
                    <span class="projects-summary-lbl">Lost</span>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="projects-toolbar">
            <div class="projects-toolbar-left">
                <div class="projects-search-wrapper" style="width: 340px;">
                    <svg class="projects-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="projects-search-input" id="leadSearchInput" placeholder="Search lead name, email, company...">
                </div>

                <div style="position: relative;">
                    <button class="btn btn-secondary btn-sm" id="btnLeadFilter" type="button" aria-expanded="false" aria-controls="leadsFilterPopover" aria-label="Filter leads" onclick="toggleLeadFilterPopover(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filters</span>
                        <span class="leads-filter-badge" id="leadsFilterBadge" style="display:none;"></span>
                    </button>

                    <!-- Filter Popover -->
                    <div class="leads-filter-popover" id="leadsFilterPopover" role="dialog" aria-modal="false" aria-label="Filter Leads">
                        <div class="leads-filter-header">
                            <div class="leads-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Leads</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="clearDraftLeadFilters()">Clear all</button>
                                <button type="button" class="leads-filter-close" onclick="closeLeadFilterPopover()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="leads-filter-body">
                            <!-- 0. Lead State (Active / Archived / All) -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Lead State</label>
                                <div class="leads-filter-checkbox-grid" style="grid-template-columns: repeat(3, 1fr);">
                                    <label class="leads-filter-checkbox-item"><input type="radio" name="filterLeadState" value="active" checked class="filter-archive-radio"> Active Leads</label>
                                    <label class="leads-filter-checkbox-item"><input type="radio" name="filterLeadState" value="archived" class="filter-archive-radio"> Archived</label>
                                    <label class="leads-filter-checkbox-item"><input type="radio" name="filterLeadState" value="all" class="filter-archive-radio"> All Leads</label>
                                </div>
                            </div>

                            <!-- 1. Status -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Status</label>
                                <div class="leads-filter-checkbox-grid" id="filterStatusOptions">
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="New" class="filter-status-cb"> New</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Contacted" class="filter-status-cb"> Contacted</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Qualified" class="filter-status-cb"> Qualified</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Proposal" class="filter-status-cb"> Proposal</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Won" class="filter-status-cb"> Won</label>
                                    <label class="leads-filter-checkbox-item"><input type="checkbox" value="Lost" class="filter-status-cb"> Lost</label>
                                </div>
                            </div>

                            <!-- 2. Assignee -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Assignee</label>
                                <div class="leads-filter-checkbox-grid" id="filterAssigneeOptions">
                                    <!-- Dynamically populated from lead data -->
                                </div>
                            </div>

                            <!-- 3. Lead Score -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Lead Score (0 – 100)</label>
                                <div class="leads-filter-quick-chips">
                                    <button type="button" class="filter-score-chip" data-min="80" data-max="100" onclick="applyScoreQuickChip(80, 100, this)">Hot (80-100)</button>
                                    <button type="button" class="filter-score-chip" data-min="50" data-max="79" onclick="applyScoreQuickChip(50, 79, this)">Warm (50-79)</button>
                                    <button type="button" class="filter-score-chip" data-min="0" data-max="49" onclick="applyScoreQuickChip(0, 49, this)">Cold (0-49)</button>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center; margin-top: 4px;">
                                    <input type="number" id="filterScoreMin" class="input-control input-sm" placeholder="Min (0)" min="0" max="100">
                                    <span style="color: var(--text-muted); font-size: 12px;">to</span>
                                    <input type="number" id="filterScoreMax" class="input-control input-sm" placeholder="Max (100)" min="0" max="100">
                                </div>
                                <div class="leads-filter-error" id="filterScoreError" style="display:none; color: #F04438; font-size: 11px; margin-top: 4px;">Min score cannot exceed max score.</div>
                            </div>

                            <!-- 4. Deal Value -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Deal Value ($)</label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="number" id="filterValueMin" class="input-control input-sm" placeholder="Min ($)" min="0">
                                    <span style="color: var(--text-muted); font-size: 12px;">to</span>
                                    <input type="number" id="filterValueMax" class="input-control input-sm" placeholder="Max ($)" min="0">
                                </div>
                                <div class="leads-filter-error" id="filterValueError" style="display:none; color: #F04438; font-size: 11px; margin-top: 4px;">Min value cannot exceed max value.</div>
                            </div>

                            <!-- 5. Lead Source -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Lead Source</label>
                                <div class="leads-filter-checkbox-grid" id="filterSourceOptions">
                                    <!-- Dynamically populated from lead data -->
                                </div>
                            </div>

                            <!-- 6. Last Activity -->
                            <div class="leads-filter-group">
                                <label class="leads-filter-label">Last Activity</label>
                                <select id="filterLastActivity" class="input-control input-sm" style="width:100%;">
                                    <option value="any">Any Time</option>
                                    <option value="today">Today</option>
                                    <option value="7days">Last 7 Days</option>
                                    <option value="30days">Last 30 Days</option>
                                    <option value="over30days">More Than 30 Days Ago</option>
                                </select>
                            </div>
                        </div>

                        <div class="leads-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="closeLeadFilterPopover()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="applyLeadFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <div id="bulkActions" style="display: none; align-items: center; gap: 8px; margin-left: 8px;">
                    <span style="font-size: 13px; color: var(--text-secondary);"><span id="selectedCountText" style="font-weight: 600; color: var(--text-heading);">0</span> selected</span>
                    <button class="btn btn-ghost btn-sm" style="color: #F04438;" onclick="deleteSelectedLeads()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        Delete
                    </button>
                </div>
            </div>

            <!-- Customize Columns -->
            <div class="table-columns-dropdown-wrapper" data-table-dropdown-for="leadsTable">
                <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                    Customize Columns
                </button>
            </div>
        </div>

        <!-- Leads Table Card -->
        <div class="card">
            <div class="crm-table-wrapper">
                <table class="crm-table" id="leadsTable">
                    <thead>
                        <tr>
                            <th data-protected="true" data-column-id="lead">LEAD</th>
                            <th data-column-id="company">COMPANY</th>
                            <th data-column-id="status">STATUS</th>
                            <th data-column-id="score">SCORE</th>
                            <th data-column-id="value">VALUE</th>
                            <th data-column-id="source">SOURCE</th>
                            <th data-column-id="assignee">ASSIGNEE</th>
                            <th data-column-id="last-activity">LAST ACTIVITY</th>
                            <th style="text-align: center;" data-protected="true" data-column-id="actions">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="leadsTbody">
                        <?php if (empty($leads)): ?>
                            <tr id="emptyLeadsRow">
                                <td colspan="9" style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                                        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color: var(--text-muted); opacity: 0.6;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        <div style="font-weight: 600; font-size: 15px; color: var(--text-heading);">No leads found</div>
                                        <div style="font-size: 13px; max-width: 320px;">Get started by adding your first sales lead or importing existing contacts.</div>
                                        <?php if (canCreate('leads')): ?>
                                        <button type="button" class="btn btn-primary btn-sm" style="margin-top: 6px;" onclick="openAddLeadModal()">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            Add Lead
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $lead): ?>
                                <tr data-id="<?php echo htmlspecialchars($lead['id']); ?>" data-status="<?php echo htmlspecialchars($lead['status']); ?>" data-name="<?php echo htmlspecialchars($lead['name']); ?>" data-company="<?php echo htmlspecialchars($lead['company']); ?>" data-score="<?php echo htmlspecialchars($lead['score']); ?>" data-value="<?php echo htmlspecialchars($lead['value']); ?>" data-source="<?php echo htmlspecialchars($lead['source']); ?>" data-assignee="<?php echo htmlspecialchars($lead['assignee']); ?>" data-last-activity="<?php echo htmlspecialchars($lead['lastActivity']); ?>" data-archived="<?php echo $lead['isArchived'] ? '1' : '0'; ?>">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="avatar avatar-sm" style="background-color: <?php echo $lead['assigneeColor']; ?>;">
                                                <?php 
                                                    $parts = explode(' ', $lead['name']);
                                                    echo strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                                ?>
                                            </div>
                                            <div>
                                                <div class="lead-name-clickable" style="font-weight: 600; color: var(--text-heading); cursor: pointer;" onclick="openLeadDrawer('<?php echo $lead['id']; ?>')">
                                                    <?php echo htmlspecialchars($lead['name']); ?>
                                                </div>
                                                <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($lead['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-body); font-weight: 500;"><?php echo htmlspecialchars($lead['company']); ?></td>
                                    <td>
                                        <?php $status_slug = strtolower($lead['status']); ?>
                                        <span class="status-badge status-<?php echo $status_slug; ?>">
                                            <span class="status-dot"></span>
                                            <?php echo htmlspecialchars($lead['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $score = $lead['score'];
                                            $bar_color = ($score >= 80) ? '#12B76A' : (($score >= 55) ? '#F79009' : '#F04438');
                                        ?>
                                        <div class="score-bar-wrapper">
                                            <div class="score-bar-track">
                                                <div class="score-bar-fill" style="width: <?php echo $score; ?>%; background-color: <?php echo $bar_color; ?>;"></div>
                                            </div>
                                            <span class="score-bar-text"><?php echo $score; ?></span>
                                        </div>
                                    </td>
                                    <td style="font-weight: 600; color: var(--text-body); font-variant-numeric: tabular-nums;">
                                        $<?php echo number_format($lead['value']); ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 12px; color: var(--text-secondary); background-color: var(--border-divider); padding: 2px 8px; border-radius: var(--radius-sm);">
                                            <?php echo htmlspecialchars($lead['source']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <div class="avatar avatar-xs" style="background-color: <?php echo $lead['assigneeColor']; ?>;">
                                                <?php echo htmlspecialchars($lead['assigneeInitials']); ?>
                                            </div>
                                            <span style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($lead['assignee']); ?></span>
                                        </div>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;"><?php echo htmlspecialchars($lead['lastActivity']); ?></td>
                                    <td style="white-space: nowrap; text-align: center;">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                            <button class="btn btn-ghost btn-xs" title="Call Lead" style="padding: 4px; color: #12B76A;" onclick="window.leadComm.openCallChoice('<?php echo $lead['id']; ?>')">
                                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                                            </button>
                                            <button class="btn btn-ghost btn-xs" title="WhatsApp Message" style="padding: 4px; color: #25D366;" onclick="window.leadComm.openWhatsApp('<?php echo $lead['id']; ?>')">
                                                <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                            </button>
                                            <button class="btn btn-ghost btn-xs" title="Send Email" style="padding: 4px; color: #2563EB;" onclick="window.leadComm.openEmail('<?php echo $lead['id']; ?>')">
                                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-container">
                <div>Showing <span style="font-weight: 600; color: var(--text-body);" id="pagingRange">1–8</span> of <span style="font-weight: 600; color: var(--text-body);" id="pagingTotal"><?php echo count($leads); ?></span></div>
                <div class="pagination-controls" id="paginationControls">
                    <button class="pagination-btn" id="prevPageBtn" disabled>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn" id="nextPageBtn">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Lead Modal -->
<div class="modal-overlay" id="addLeadModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Lead</h3>
            <button class="modal-close-btn" onclick="closeAddLeadModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="addLeadForm">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="input-control" name="name" required placeholder="e.g. Alex Morgan">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Job Title</label>
                        <input type="text" class="input-control" name="job_title" placeholder="e.g. VP of Operations">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Company *</label>
                        <input type="text" class="input-control" name="company" required placeholder="e.g. Acme Corp">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" class="input-control" name="email" required placeholder="alex@company.com">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="input-control" name="phone" placeholder="+1 (555) 000-0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">WhatsApp Number</label>
                        <input type="text" class="input-control" name="whatsapp" placeholder="+1 (555) 000-0000">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" class="input-control" name="location" placeholder="e.g. San Francisco, CA">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="input-control" name="status">
                            <option value="New" selected>New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Proposal">Proposal</option>
                            <option value="Won">Won</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Lead Score (0-100)</label>
                        <input type="number" class="input-control" name="score" min="0" max="100" placeholder="50" value="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Value ($)</label>
                        <input type="number" class="input-control" name="value" placeholder="25000" value="25000">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <input type="text" class="input-control" name="source" placeholder="e.g. Website" value="Website">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Owner / Assignee</label>
                        <select class="input-control" name="assigned_to" id="addLeadAssignee">
                            <?php foreach ($orgUsers as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['id']); ?>" <?php echo ($u['id'] == $currentUser['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closeAddLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Create Lead</button>
            </div>
        </form>
    </div>
</div>

<!-- Schedule Meeting Modal -->
<div class="modal-overlay lead-action-modal" id="scheduleMeetingModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="scheduleMeetingTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="scheduleMeetingTitle">Schedule Meeting</h3>
            <button class="modal-close-btn" onclick="closeScheduleMeetingModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="scheduleMeetingForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Meeting Title *</label>
                    <input type="text" class="input-control" id="meetingTitle" required placeholder="e.g. Product Demo & Architecture Review">
                </div>
                <div class="form-group">
                    <label class="form-label">Lead Name</label>
                    <input type="text" class="input-control" id="meetingLeadName" readonly style="background-color: var(--bg-app); cursor: not-allowed;">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" class="input-control" id="meetingDate" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Time *</label>
                        <input type="time" class="input-control" id="meetingTime" value="10:00" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Duration</label>
                        <select class="input-control" id="meetingDuration">
                            <option value="15 mins">15 mins</option>
                            <option value="30 mins" selected>30 mins</option>
                            <option value="45 mins">45 mins</option>
                            <option value="1 hour">1 hour</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meeting Type</label>
                        <select class="input-control" id="meetingType">
                            <option value="Video Call" selected>Video Call (Google Meet)</option>
                            <option value="Phone Call">Phone Call</option>
                            <option value="In Person">In Person Meeting</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Assignee</label>
                    <select class="input-control" id="meetingAssignee">
                        <?php foreach ($orgUsers as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['id']); ?>" <?php echo ($u['id'] == $currentUser['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description / Agenda</label>
                    <textarea class="input-control" id="meetingDesc" style="height: 64px; padding: 8px 12px;" placeholder="Add agenda or preparation notes..."></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Reminder</label>
                    <select class="input-control" id="meetingReminder">
                        <option value="15 mins before" selected>15 minutes before</option>
                        <option value="30 mins before">30 minutes before</option>
                        <option value="1 hour before">1 hour before</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closeScheduleMeetingModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Schedule Meeting</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal-overlay lead-action-modal" id="addDrawerTaskModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addDrawerTaskTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="addDrawerTaskTitle">Add Task</h3>
            <button class="modal-close-btn" onclick="closeAddDrawerTaskModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="addDrawerTaskForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" class="input-control" id="taskTitleInput" required placeholder="e.g. Send updated pricing proposal">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Task Type</label>
                        <select class="input-control" id="taskTypeInput">
                            <option value="Follow-up" selected>Follow-up</option>
                            <option value="Call">Call</option>
                            <option value="Email">Email</option>
                            <option value="Demo">Demo</option>
                            <option value="Meeting">Meeting</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select class="input-control" id="taskPriorityInput">
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Normal">Normal</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Due Date *</label>
                        <input type="date" class="input-control" id="taskDueDateInput" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Time</label>
                        <input type="time" class="input-control" id="taskDueTimeInput" value="17:00">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Assignee</label>
                    <select class="input-control" id="taskAssigneeInput">
                        <?php foreach ($orgUsers as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['id']); ?>" <?php echo ($u['id'] == $currentUser['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Linked Lead</label>
                    <input type="text" class="input-control" id="taskLinkedLeadInput" readonly style="background-color: var(--bg-app); cursor: not-allowed;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closeAddDrawerTaskModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal-overlay lead-action-modal" id="addDrawerNoteModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addDrawerNoteTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="addDrawerNoteTitle">Add Note</h3>
            <button class="modal-close-btn" onclick="closeAddDrawerNoteModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="addDrawerNoteForm">
            <div class="modal-body">
                <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px;" id="noteLeadNameBanner">
                    Note for <strong>Marcus Thompson</strong>
                </div>
                <div class="form-group">
                    <label class="form-label">Note Content *</label>
                    <textarea class="input-control" id="noteContentInput" required style="height: 100px; padding: 10px 12px;" placeholder="Write a note about this lead..."></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-body);cursor:pointer;">
                        <input type="checkbox" id="notePinCheckbox"> Pin this note to top
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closeAddDrawerNoteModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md" id="noteSubmitBtn">Save Note</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal-overlay lead-action-modal" id="editLeadModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editLeadModalTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="editLeadModalTitle">Edit Lead</h3>
            <button class="modal-close-btn" onclick="closeEditLeadModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="editLeadForm">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="input-control" id="editLeadName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Job Title</label>
                        <input type="text" class="input-control" id="editLeadJobTitle" name="job_title" placeholder="e.g. VP of Operations">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Company *</label>
                        <input type="text" class="input-control" id="editLeadCompany" name="company" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" class="input-control" id="editLeadEmail" name="email" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="input-control" id="editLeadPhone" name="phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">WhatsApp Number</label>
                        <input type="text" class="input-control" id="editLeadWhatsapp" name="whatsapp">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" class="input-control" id="editLeadLocation" name="location" placeholder="San Francisco, CA">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="input-control" id="editLeadStatus" name="status">
                            <option value="New">New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Proposal">Proposal</option>
                            <option value="Won">Won</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Lead Score (0-100)</label>
                        <input type="number" class="input-control" id="editLeadScore" name="score" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Value ($)</label>
                        <input type="number" class="input-control" id="editLeadValue" name="value">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <input type="text" class="input-control" id="editLeadSource" name="source">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Owner / Assignee</label>
                        <select class="input-control" id="editLeadOwner" name="assigned_to">
                            <?php foreach ($orgUsers as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['id']); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closeEditLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Convert to Contact Modal -->
<div class="modal-overlay lead-action-modal" id="convertContactModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="convertContactModalTitle">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title" id="convertContactModalTitle">Convert Lead to Contact</h3>
            <button class="modal-close-btn" onclick="closeConvertContactModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;color:var(--text-body);line-height:1.5;margin-bottom:12px;">
                Are you sure you want to convert <strong id="convertLeadName">this lead</strong> into a CRM Contact and create an associated deal?
            </p>
            <div style="background-color:var(--primary-light);border:1px solid rgba(37,99,235,0.15);border-radius:var(--radius-md);padding:10px 12px;font-size:12.5px;color:#1E40AF;">
                💡 Converting will update the lead status to Qualified and create an associated deal.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closeConvertContactModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" onclick="confirmConvertContact()">Convert to Contact</button>
        </div>
    </div>
</div>

<!-- Quick Status Modal -->
<div class="modal-overlay lead-action-modal" id="quickStatusModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="quickStatusModalTitle">
    <div class="modal-content" style="max-width: 380px;">
        <div class="modal-header">
            <h3 class="modal-title" id="quickStatusModalTitle">Change Status</h3>
            <button class="modal-close-btn" onclick="closeQuickStatusModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Select New Status</label>
                <select class="input-control" id="quickStatusSelect">
                    <option value="New">New</option>
                    <option value="Contacted">Contacted</option>
                    <option value="Qualified">Qualified</option>
                    <option value="Proposal">Proposal</option>
                    <option value="Won">Won</option>
                    <option value="Lost">Lost</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closeQuickStatusModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" onclick="saveQuickStatus()">Update Status</button>
        </div>
    </div>
</div>

<!-- Quick Owner Modal -->
<div class="modal-overlay lead-action-modal" id="quickOwnerModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="quickOwnerModalTitle">
    <div class="modal-content" style="max-width: 380px;">
        <div class="modal-header">
            <h3 class="modal-title" id="quickOwnerModalTitle">Change Owner</h3>
            <button class="modal-close-btn" onclick="closeQuickOwnerModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Select Lead Owner</label>
                <select class="input-control" id="quickOwnerSelect">
                    <?php foreach ($orgUsers as $u): ?>
                        <option value="<?php echo htmlspecialchars($u['id']); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closeQuickOwnerModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" onclick="saveQuickOwner()">Update Owner</button>
        </div>
    </div>
</div>

<!-- Custom Delete Confirmation Modal -->
<div class="modal-overlay lead-action-modal" id="leadDeleteConfirmModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="leadDeleteConfirmTitle">
    <div class="modal-content" style="max-width: 420px; border-radius: var(--radius-lg);">
        <div class="modal-header">
            <h3 class="modal-title" id="leadDeleteConfirmTitle">Delete Item</h3>
            <button class="modal-close-btn" onclick="closeLeadDeleteConfirmModal()" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p id="leadDeleteConfirmMessage" style="font-size: 14px; font-weight: 500; color: var(--text-heading); margin-bottom: 6px;">Are you sure you want to delete this item?</p>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0;">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closeLeadDeleteConfirmModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" id="leadDeleteConfirmSubmitBtn" style="background-color: #D92D20; border-color: #D92D20; color: #FFFFFF;" onclick="executeLeadItemDeletion()">Delete</button>
        </div>
    </div>
</div>

<!-- Lead Detail Right-Side Drawer -->
<div class="drawer-overlay" id="leadDrawer">
    <div class="drawer-content">
        <!-- Sticky Header -->
        <div class="drawer-header">
            <div>
                <div class="drawer-breadcrumb" id="drawerBreadcrumb">Leads / Marcus Thompson</div>
                <h3 class="drawer-title" id="drawerLeadTitle">Lead Details</h3>
            </div>
            <div class="drawer-header-actions">
                <!-- Share Button -->
                <button class="btn btn-ghost btn-xs" id="drawerLeadShareBtn" title="Share Lead" aria-label="Share Lead" onclick="window.NexFlowShare.openLeadShare(currentOpenLeadId || (typeof activeLead !== 'undefined' ? activeLead.id : null), this)">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <!-- Edit Icon Button -->
                <button class="btn btn-ghost btn-xs" id="drawerEditBtn" title="Edit Lead" onclick="openEditLeadModal()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <!-- Three-Dot Header Actions Button -->
                <div class="drawer-dropdown-wrapper">
                    <button class="btn btn-ghost btn-xs" id="drawerHeaderActionsBtn" title="More Actions" onclick="toggleDrawerHeaderMenu(event)">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    </button>
                    <div class="dropdown-menu drawer-header-menu" id="drawerHeaderMenu">
                        <button class="dropdown-item" onclick="openEditLeadModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Lead
                        </button>
                        <button class="dropdown-item" onclick="openQuickStatusModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg> Change Status
                        </button>
                        <button class="dropdown-item" onclick="openQuickOwnerModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Change Owner
                        </button>
                        <button class="dropdown-item" onclick="openConvertContactModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg> Convert to Contact
                        </button>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item" id="drawerArchiveBtn" onclick="toggleArchiveCurrentLead()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg> <span id="drawerArchiveBtnText">Archive Lead</span>
                        </button>
                        <button class="dropdown-item danger" onclick="deleteCurrentLeadFromDrawer()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg> Delete Lead
                        </button>
                    </div>
                </div>
                <!-- Close Button -->
                <button class="modal-close-btn" onclick="closeLeadDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Sticky Tab Row -->
        <div class="drawer-tabs-wrapper">
            <div class="drawer-tabs scrollbar-hide" id="drawerTabsContainer">
                <button class="drawer-tab active" data-tab="overview" onclick="switchDrawerTab('overview')">Overview</button>
                <button class="drawer-tab" data-tab="activity" onclick="switchDrawerTab('activity')">Activity</button>
                <button class="drawer-tab" data-tab="deals" onclick="switchDrawerTab('deals')">Deals</button>
                <button class="drawer-tab" data-tab="tasks" onclick="switchDrawerTab('tasks')">Tasks</button>
                <button class="drawer-tab" data-tab="notes" onclick="switchDrawerTab('notes')">Notes</button>
            </div>
        </div>

        <!-- Drawer Content Body -->
        <div class="drawer-body" id="drawerLeadBody">
            <!-- Dynamic tab content populated by JS -->
        </div>
    </div>
</div>

<script>
    window.INITIAL_LEADS_DATA = <?php echo json_encode($leads); ?>;
    window.ORG_USERS = <?php echo json_encode($orgUsers); ?>;
    window.LEADS_PERMISSIONS = {
        view: <?php echo canView('leads') ? 'true' : 'false'; ?>,
        create: <?php echo canCreate('leads') ? 'true' : 'false'; ?>,
        edit: <?php echo canEdit('leads') ? 'true' : 'false'; ?>,
        delete: <?php echo canDelete('leads') ? 'true' : 'false'; ?>,
        assign: <?php echo hasPermission('leads', 'assign') ? 'true' : 'false'; ?>,
        export: <?php echo canExport('leads') ? 'true' : 'false'; ?>
    };
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

