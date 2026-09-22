<?php
// Sales Pipeline (Kanban) Page - Real MySQL Integration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$page_title = "Sales Pipeline";
$current_page = "pipeline";
$page_script = "pipeline.js";

include __DIR__ . '/includes/header.php';
requirePermission('pipeline');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$pdo = nexflow_db();
$currentUser = nexflow_current_user();
$organizationId = (int)($currentUser['organization_id'] ?? 1);
$currentUserId = (int)($currentUser['id'] ?? 1);

// 1. Fetch active pipeline stages for current organization
$stgStmt = $pdo->prepare("
    SELECT id, name, sort_order, color, probability, is_system
    FROM pipeline_stages
    WHERE organization_id = ? AND is_active = 1
    ORDER BY sort_order ASC, id ASC
");
$stgStmt->execute([$organizationId]);
$db_stages = $stgStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch active users for owner assignment
$usersStmt = $pdo->prepare("
    SELECT id, name, email, role, photo_path
    FROM users
    WHERE organization_id = ? AND status = 'active'
    ORDER BY name ASC
");
$usersStmt->execute([$organizationId]);
$active_users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
$user_names_map = [];
foreach ($active_users as $u) {
    $user_names_map[(int)$u['id']] = $u['name'];
}

// 2b. Fetch active contacts and companies for autocomplete using authoritative contact_companies
$contactsStmt = $pdo->prepare("
    SELECT id, name, email, phone
    FROM contacts
    WHERE organization_id = ?
    ORDER BY name ASC
");
$contactsStmt->execute([$organizationId]);
$raw_contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);

$ccStmt = $pdo->prepare("
    SELECT cc.contact_id, comp.id AS company_id, comp.name AS company_name
    FROM contact_companies cc
    JOIN companies comp ON comp.id = cc.company_id AND comp.organization_id = cc.organization_id
    WHERE cc.organization_id = ?
    ORDER BY comp.name ASC
");
$ccStmt->execute([$organizationId]);
$ccRows = $ccStmt->fetchAll(PDO::FETCH_ASSOC);

$companiesByContact = [];
foreach ($ccRows as $row) {
    $cid = (int)$row['contact_id'];
    if (!isset($companiesByContact[$cid])) {
        $companiesByContact[$cid] = [];
    }
    $companiesByContact[$cid][] = [
        'id'   => (int)$row['company_id'],
        'name' => $row['company_name']
    ];
}

$active_contacts = [];
foreach ($raw_contacts as $c) {
    $cid = (int)$c['id'];
    $comps = $companiesByContact[$cid] ?? [];
    $active_contacts[] = [
        'id'        => $cid,
        'name'      => $c['name'],
        'email'     => $c['email'] ?: '',
        'phone'     => $c['phone'] ?: '',
        'companies' => $comps
    ];
}

$companiesStmt = $pdo->prepare("
    SELECT id, name
    FROM companies
    WHERE organization_id = ?
    ORDER BY name ASC
");
$companiesStmt->execute([$organizationId]);
$active_companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch real deals for current organization
$dealsStmt = $pdo->prepare("
    SELECT d.*, u.name AS owner_name
    FROM deals d
    LEFT JOIN users u ON u.id = d.assigned_to AND u.organization_id = d.organization_id
    WHERE d.organization_id = ?
    ORDER BY d.id DESC
");
$dealsStmt->execute([$organizationId]);
$db_deals = $dealsStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Build kanban stages structure
$kanban_stages = [];
$stage_map = [];
foreach ($db_stages as $s) {
    $s_key = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $s['name']), '_'));
    $kanban_stages[$s_key] = [
        'id'          => $s_key,
        'label'       => $s['name'],
        'color'       => $s['color'] ?: '#2563EB',
        'probability' => (float)$s['probability'],
        'deals'       => []
    ];
    $stage_map[$s_key] = $s_key;
    $stage_map[strtolower($s['name'])] = $s_key;
}

// 5. Calculate real KPIs
$total_deals_count = count($db_deals);
$total_pipeline_value = 0.0;
$open_deals_count = 0;
$won_value = 0.0;
$weighted_value = 0.0;

$avatar_colors = ['#7C3AED', '#0284C7', '#10B981', '#F59E0B', '#6366F1', '#EC4899', '#14B8A6', '#8B5CF6'];

foreach ($db_deals as $index => $d) {
    $val = (float)$d['value'];
    $stgRaw = trim((string)$d['stage']);
    $stgKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $stgRaw), '_'));
    $matchedKey = $stage_map[$stgKey] ?? ($stage_map[strtolower($stgRaw)] ?? $stgKey);

    $stgProb = isset($kanban_stages[$matchedKey]) ? $kanban_stages[$matchedKey]['probability'] : 50.0;
    $effProb = ($d['probability'] !== null) ? (float)$d['probability'] : $stgProb;

    $status = strtolower($d['status'] ?: 'open');
    if ($status !== 'lost') {
        $total_pipeline_value += $val;
    }
    if ($status === 'open') {
        $open_deals_count++;
    } elseif ($status === 'won') {
        $won_value += $val;
    }
    $weighted_value += ($val * ($effProb / 100.0));

    $owner_name = $d['owner_name'] ?: ($user_names_map[(int)$d['assigned_to']] ?? 'Unassigned');
    $parts = preg_split('/\s+/', trim($owner_name));
    $initials = count($parts) >= 2 ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1)) : strtoupper(substr($owner_name, 0, min(2, strlen($owner_name))));
    $color = $avatar_colors[($d['assigned_to'] ?: $d['id']) % count($avatar_colors)];

    $deal_item = [
        'id'           => $d['id'],
        'deal_code'    => $d['deal_code'] ?: ('DL-' . str_pad($d['id'], 3, '0', STR_PAD_LEFT)),
        'name'         => $d['name'],
        'contact_name' => $d['contact_name'] ?: '',
        'company'      => $d['company'] ?: '',
        'value'        => $val,
        'initials'     => $initials,
        'color'        => $color,
        'tag'          => $d['source'] ?: 'Direct',
        'closeDate'    => !empty($d['close_date']) ? date('M j, Y', strtotime($d['close_date'])) : '',
        'isoCloseDate' => !empty($d['close_date']) ? date('Y-m-d', strtotime($d['close_date'])) : '',
        'prob'         => $effProb,
        'owner'        => $owner_name
    ];

    if (isset($kanban_stages[$matchedKey])) {
        $kanban_stages[$matchedKey]['deals'][] = $deal_item;
    }
}
?>

<main class="main-content">
    <div class="page-container">
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Sales Pipeline</h1>
                    <span class="projects-total-badge" id="globalTotalDealsBadge"><?php echo $total_deals_count; ?> deals</span>
                </div>
                <p class="projects-header-subtitle">Manage deals, pipeline stages, values, and sales progress.</p>
            </div>
            <div class="projects-header-actions">
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddDealModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Add Deal
                </button>
            </div>
        </div>

        <!-- 5-Card KPI Summary Bar -->
        <div class="projects-summary-bar">
            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="globalTotalDeals"><?php echo $total_deals_count; ?></span>
                    <span class="projects-summary-lbl">Total Deals</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="globalTotalValue">$<?php echo ($total_pipeline_value >= 1000) ? number_format($total_pipeline_value / 1000, 1) . 'K' : number_format($total_pipeline_value); ?></span>
                    <span class="projects-summary-lbl">Pipeline Value</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="globalOpenDeals"><?php echo $open_deals_count; ?></span>
                    <span class="projects-summary-lbl">Open Deals</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #FAF5FF; color: #9333EA;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="globalWonValue">$<?php echo ($won_value >= 1000) ? number_format($won_value / 1000, 1) . 'K' : number_format($won_value); ?></span>
                    <span class="projects-summary-lbl">Won Value</span>
                </div>
            </div>

            <div class="projects-summary-card">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="globalWeightedValue">$<?php echo ($weighted_value >= 1000) ? number_format($weighted_value / 1000, 1) . 'K' : number_format($weighted_value); ?></span>
                    <span class="projects-summary-lbl">Weighted Value</span>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="projects-toolbar" style="margin-bottom: 20px;">
            <div class="projects-toolbar-left">
                <div class="projects-search-wrapper" style="width: 350px;">
                    <svg class="projects-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="projects-search-input" id="pipelineSearchInput" placeholder="Search deal name, company, contact...">
                </div>

                <!-- Filter Button -->
                <div style="position: relative;">
                    <button class="btn btn-secondary btn-sm" id="pipelineFilterBtn" onclick="togglePipelineFilterPanel(event)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Filters</span>
                        <span class="status-badge" id="activeFilterBadge" style="display:none; background:var(--primary); color:#FFF; font-size:10px; padding:1px 5px; margin-left:4px;">0</span>
                    </button>

                    <!-- Pipeline Filter Popover Panel -->
                    <div class="pipeline-filter-popover" id="pipelineFilterPopover" onclick="event.stopPropagation()">
                        <div class="pipeline-filter-header">
                            <div class="pipeline-filter-title">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter Deals</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-clear-filter-text" onclick="clearPipelineFilters()">Clear all</button>
                                <button type="button" class="filter-popover-close" onclick="closePipelineFilterPanel()" aria-label="Close filter panel">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="pipeline-filter-body">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Stage</label>
                                <select class="input-control input-sm" id="filterStageSelect">
                                    <option value="All" selected>All Stages</option>
                                    <?php foreach ($kanban_stages as $stg): ?>
                                        <option value="<?php echo htmlspecialchars($stg['id']); ?>"><?php echo htmlspecialchars($stg['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Owner</label>
                                <select class="input-control input-sm" id="filterOwnerSelect">
                                    <option value="All" selected>All Owners</option>
                                    <?php foreach ($active_users as $u): ?>
                                        <option value="<?php echo htmlspecialchars($u['name']); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Source Tag</label>
                                <select class="input-control input-sm" id="filterSourceSelect">
                                    <option value="All" selected>All Sources</option>
                                    <option value="Website">Website</option>
                                    <option value="Referral">Referral</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="Event">Event</option>
                                    <option value="Inbound">Inbound</option>
                                    <option value="Outreach">Outreach</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Min Value ($)</label>
                                <input type="number" class="input-control input-sm" id="filterMinValue" placeholder="Min ($)" min="0">
                            </div>
                        </div>
                        <div class="pipeline-filter-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="closePipelineFilterPanel()">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="applyPipelineFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <!-- Sort Dropdown -->
                <select class="input-control input-sm" id="pipelineSortSelect" style="width: 175px; height:36px;" aria-label="Sort Deals">
                    <option value="default" selected>Default Sort</option>
                    <option value="val_high">Value: High to Low</option>
                    <option value="val_low">Value: Low to High</option>
                    <option value="date_earliest">Close Date: Earliest</option>
                    <option value="date_latest">Close Date: Latest</option>
                    <option value="name_az">Name: A–Z</option>
                    <option value="name_za">Name: Z–A</option>
                </select>

                <!-- Collapse/Expand All Toggle -->
                <button class="btn btn-secondary btn-sm" id="collapseAllStagesBtn" title="Collapse or Expand All Stages">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/></svg>
                    <span>Collapse All</span>
                </button>

                <!-- Board Density Toggle -->
                <button class="btn btn-secondary btn-sm" id="densityToggleBtn" title="Toggle Board Density">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Compact</span>
                </button>
            </div>
        </div>

        <!-- Removable Active Filter Chips -->
        <div class="pipeline-filter-chips-bar" id="pipelineActiveFilterChips" style="display: none;"></div>

        <!-- Board Viewport Container -->
        <div class="pipeline-board-container" id="pipelineBoardViewport">
            <!-- Kanban Board Container -->
            <div class="kanban-board" id="kanbanBoard">
                <?php foreach ($kanban_stages as $stage): ?>
                    <?php 
                        $col_count = count($stage['deals']);
                        $col_sum = array_sum(array_column($stage['deals'], 'value'));
                        $col_sum_formatted = ($col_sum >= 1000) ? ('$' . round($col_sum / 1000) . 'K') : ('$' . $col_sum);
                    ?>
                    <div class="kanban-column" data-stage-id="<?php echo htmlspecialchars($stage['id']); ?>" data-collapsed="false" aria-expanded="true" tabindex="0">
                        <!-- Stage Header -->
                        <div class="kanban-column-header">
                            <div class="kanban-column-title-group">
                                <div style="width: 10px; height: 10px; border-radius: 50%; background-color: <?php echo $stage['color']; ?>;"></div>
                                <span class="kanban-column-title"><?php echo htmlspecialchars($stage['label']); ?></span>
                                <span class="kanban-column-badge column-count"><?php echo $col_count; ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <span class="kanban-column-value column-total-value"><?php echo $col_sum_formatted; ?></span>
                                <!-- Add Deal Icon -->
                                <button class="btn btn-ghost btn-xs stage-add-btn" style="padding: 2px; color: var(--text-muted);" title="Add deal to <?php echo htmlspecialchars($stage['label']); ?>" onclick="event.stopPropagation(); openAddDealModal('<?php echo $stage['id']; ?>')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                                <!-- Direct Stage Collapse/Expand Toggle Button -->
                                <button type="button" class="btn btn-ghost btn-xs stage-collapse-btn" data-action="toggle-stage-collapse" data-stage-id="<?php echo htmlspecialchars($stage['id']); ?>" title="Collapse <?php echo htmlspecialchars($stage['label']); ?> stage" aria-label="Collapse <?php echo htmlspecialchars($stage['label']); ?> stage" style="padding: 2px; color: var(--text-muted);">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="stage-collapse-icon"><polyline points="15 18 9 12 15 6"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Cards Container -->
                        <div class="kanban-cards-container" data-stage="<?php echo htmlspecialchars($stage['id']); ?>">
                            <?php foreach ($stage['deals'] as $index => $deal): ?>
                                <?php 
                                    $close_date = isset($deal['closeDate']) ? $deal['closeDate'] : 'Aug 18, 2026';
                                    $iso_date = isset($deal['isoCloseDate']) ? $deal['isoCloseDate'] : '2026-08-18';
                                    $prob = isset($deal['prob']) ? $deal['prob'] : 75;
                                ?>
                                <div class="kanban-card" draggable="true" 
                                     data-deal-id="<?php echo htmlspecialchars($deal['id']); ?>" 
                                     data-original-index="<?php echo $index; ?>"
                                     data-value="<?php echo $deal['value']; ?>"
                                     data-name="<?php echo htmlspecialchars($deal['name']); ?>"
                                     data-contact="<?php echo htmlspecialchars($deal['contact_name']); ?>"
                                     data-company="<?php echo htmlspecialchars($deal['company']); ?>"
                                     data-source="<?php echo htmlspecialchars($deal['tag']); ?>"
                                     data-assignee="<?php echo htmlspecialchars($deal['initials']); ?>"
                                     data-stage="<?php echo htmlspecialchars($stage['id']); ?>"
                                     data-prob="<?php echo $prob; ?>"
                                     data-close-date="<?php echo htmlspecialchars($iso_date); ?>"
                                     data-close-date-text="<?php echo htmlspecialchars($close_date); ?>"
                                     tabindex="0"
                                     onclick="openDealDetailsDrawer('<?php echo htmlspecialchars($deal['id']); ?>')">
                                    
                                    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px;">
                                        <div style="flex: 1; min-width: 0; padding-right: 6px;">
                                            <div class="kanban-card-title"><?php echo htmlspecialchars($deal['name']); ?></div>
                                            <div class="kanban-card-company"><?php echo htmlspecialchars($deal['company']); ?></div>
                                        </div>
                                    </div>

                                    <!-- Sub-meta info (Date & Probability) -->
                                    <div class="card-sub-meta">
                                        <span>📅 <?php echo htmlspecialchars($close_date); ?></span>
                                        <span class="card-prob-badge"><?php echo $prob; ?>%</span>
                                    </div>

                                    <div class="kanban-card-footer" style="margin-top: 8px;">
                                        <div class="kanban-card-value">$<?php echo number_format($deal['value']); ?></div>
                                        <div class="kanban-card-meta">
                                            <span class="kanban-source-tag"><?php echo htmlspecialchars($deal['tag']); ?></span>
                                            <div class="avatar avatar-xs" style="background-color: <?php echo $deal['color']; ?>;" title="Assignee">
                                                <?php echo htmlspecialchars($deal['initials']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Dashed Add Deal Button -->
                        <button class="add-deal-btn" onclick="openAddDealModal('<?php echo $stage['id']; ?>')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add deal
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<!-- ==========================================
     MODALS & DRAWERS SYSTEM
     ========================================== -->

<!-- 1. Mark as Closed Won Confirmation Modal -->
<div class="modal-overlay pipeline-action-modal" id="closedWonConfirmModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="closedWonTitle">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title" id="closedWonTitle">Mark this deal as won?</h3>
            <button class="modal-close-btn" onclick="cancelClosedWonMove()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div style="background-color: #F8FAFC; border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 12px; margin-bottom: 14px;">
                <div style="font-weight: 600; font-size: 14px; color: var(--text-heading);" id="wonModalDealName">Acme Corp License</div>
                <div style="font-size: 13px; color: #12B76A; font-weight: 700; margin-top: 4px;" id="wonModalDealValue">$24,000</div>
            </div>
            <div class="form-group">
                <label class="form-label">Actual Closing Date</label>
                <input type="date" class="input-control" id="wonModalCloseDate">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Closing Note (Optional)</label>
                <textarea class="input-control" id="wonModalNote" style="height: 64px;" placeholder="Add details about winning this deal..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="cancelClosedWonMove()">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" style="background-color: #12B76A; border-color: #12B76A;" onclick="confirmClosedWonMove()">Mark as Won</button>
        </div>
    </div>
</div>

<!-- 2. Reopen Won Deal Confirmation Modal -->
<div class="modal-overlay pipeline-action-modal" id="reopenDealConfirmModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="reopenTitle">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <h3 class="modal-title" id="reopenTitle">Reopen this won deal?</h3>
            <button class="modal-close-btn" onclick="cancelReopenDealMove()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13.5px; color: var(--text-body); line-height: 1.5; margin: 0;">
                Are you sure you want to reopen <strong id="reopenModalDealName">this deal</strong> and move it back into an active pipeline stage?
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="cancelReopenDealMove()">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" onclick="confirmReopenDealMove()">Reopen Deal</button>
        </div>
    </div>
</div>

<!-- 3. Add Deal Modal (All 12 Fields) -->
<div class="modal-overlay pipeline-action-modal" id="addDealModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addDealTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="addDealTitle">Add New Deal</h3>
            <button class="modal-close-btn" onclick="closePipelineModal('addDealModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="addDealForm">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Deal Name *</label>
                        <input type="text" class="input-control" id="addDealName" required placeholder="e.g. Acme Corp Enterprise License">
                    </div>
                    <div class="form-group" style="position: relative;">
                        <label class="form-label">Associated Contact *</label>
                        <input type="text" class="input-control" id="addDealContact" required placeholder="e.g. Marcus Thompson" autocomplete="off">
                        <input type="hidden" id="addDealContactId" value="">
                        <div class="nexflow-autocomplete-dropdown" id="addDealContactDropdown" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group" style="position: relative;">
                        <label class="form-label">Company Name *</label>
                        <input type="text" class="input-control" id="addDealCompany" required placeholder="e.g. Acme Corp" autocomplete="off">
                        <input type="hidden" id="addDealCompanyId" value="">
                        <div class="nexflow-autocomplete-dropdown" id="addDealCompanyDropdown" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deal Value ($) *</label>
                        <input type="number" class="input-control" id="addDealValue" required placeholder="25000" value="25000">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Pipeline Stage</label>
                        <select class="input-control" id="addDealStage">
                            <?php foreach ($kanban_stages as $stg): ?>
                                <option value="<?php echo htmlspecialchars($stg['id']); ?>"><?php echo htmlspecialchars($stg['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Probability (%)</label>
                        <input type="number" class="input-control" id="addDealProb" min="0" max="100" value="75">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Expected Close Date</label>
                        <input type="date" class="input-control" id="addDealCloseDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lead Source</label>
                        <select class="input-control" id="addDealSource">
                            <option value="Website" selected>Website</option>
                            <option value="Referral">Referral</option>
                            <option value="LinkedIn">LinkedIn</option>
                            <option value="Inbound">Inbound</option>
                            <option value="Outreach">Outreach</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Deal Owner</label>
                        <select class="input-control" id="addDealOwner">
                            <?php foreach ($active_users as $idx => $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo $idx === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select class="input-control" id="addDealPriority">
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Normal">Normal</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Product or Service</label>
                    <input type="text" class="input-control" id="addDealProduct" placeholder="e.g. Enterprise CRM License Suite">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Description</label>
                    <textarea class="input-control" id="addDealDesc" style="height:64px;padding:8px 12px" placeholder="Add deal requirements or notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('addDealModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Add Deal</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Edit Deal Modal -->
<div class="modal-overlay pipeline-action-modal" id="editDealModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editDealTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="editDealTitle">Edit Deal</h3>
            <button class="modal-close-btn" onclick="closePipelineModal('editDealModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="editDealForm">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Deal Name *</label>
                        <input type="text" class="input-control" id="editDealName" required>
                    </div>
                    <div class="form-group" style="position: relative;">
                        <label class="form-label">Associated Contact</label>
                        <input type="text" class="input-control" id="editDealContact" autocomplete="off" placeholder="e.g. Marcus Thompson">
                        <input type="hidden" id="editDealContactId" value="">
                        <div class="nexflow-autocomplete-dropdown" id="editDealContactDropdown" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group" style="position: relative;">
                        <label class="form-label">Company Name *</label>
                        <input type="text" class="input-control" id="editDealCompany" required autocomplete="off" placeholder="e.g. Acme Corp">
                        <input type="hidden" id="editDealCompanyId" value="">
                        <div class="nexflow-autocomplete-dropdown" id="editDealCompanyDropdown" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deal Value ($) *</label>
                        <input type="number" class="input-control" id="editDealValue" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Pipeline Stage</label>
                        <select class="input-control" id="editDealStage">
                            <?php foreach ($kanban_stages as $stg): ?>
                                <option value="<?php echo htmlspecialchars($stg['id']); ?>"><?php echo htmlspecialchars($stg['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Probability (%)</label>
                        <input type="number" class="input-control" id="editDealProb" min="0" max="100">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Expected Close Date</label>
                        <input type="date" class="input-control" id="editDealCloseDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lead Source</label>
                        <select class="input-control" id="editDealSource">
                            <option value="Website">Website</option>
                            <option value="Referral">Referral</option>
                            <option value="LinkedIn">LinkedIn</option>
                            <option value="Inbound">Inbound</option>
                            <option value="Outreach">Outreach</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Deal Owner</label>
                        <select class="input-control" id="editDealOwner">
                            <?php foreach ($active_users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select class="input-control" id="editDealPriority">
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Normal">Normal</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Product or Service</label>
                    <input type="text" class="input-control" id="editDealProduct" placeholder="e.g. Enterprise CRM License Suite">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Description</label>
                    <textarea class="input-control" id="editDealDesc" style="height:64px;padding:8px 12px" placeholder="Add deal requirements or notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-danger btn-md" style="background-color: #DC2626; color: #FFFFFF; border: none; font-weight: 600;" onclick="promptDeletePipelineDeal(activePipelineDealId)">Delete Deal</button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('editDealModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-md">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 5. Add / Edit Pipeline Task Modal -->
<div class="modal-overlay pipeline-action-modal" id="addPipelineTaskModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addTaskTitle">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title" id="addTaskTitle">Add Task for Deal</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('addPipelineTaskModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="addPipelineTaskForm" onsubmit="executeSavePipelineTask(event)">
            <div class="modal-body">
                <!-- Deal Context (Read-Only) -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.04em;">Associated Deal</label>
                    <input type="text" class="input-control" id="pipelineTaskDealContext" readonly style="background-color: #F8FAFC; color: var(--text-muted); font-weight: 600;">
                </div>

                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" class="input-control" id="pipelineTaskTitle" required placeholder="e.g. Follow up on proposal feedback">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="input-control" id="pipelineTaskDesc" style="height: 80px;" placeholder="Add task details or instructions..."></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Due Date *</label>
                        <input type="date" class="input-control" id="pipelineTaskDate" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Time</label>
                        <input type="time" class="input-control" id="pipelineTaskTime">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select class="input-control" id="pipelineTaskPriority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assignee</label>
                        <select class="input-control" id="pipelineTaskAssignee">
                            <?php foreach ($active_users as $idx => $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo $idx === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Reminder</label>
                        <select class="input-control" id="pipelineTaskReminder">
                            <option value="None" selected>None</option>
                            <option value="At due time">At due time</option>
                            <option value="15 minutes before">15 minutes before</option>
                            <option value="30 minutes before">30 minutes before</option>
                            <option value="1 hour before">1 hour before</option>
                            <option value="1 day before">1 day before</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="input-control" id="pipelineTaskStatus">
                            <option value="Open" selected>Open</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('addPipelineTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md" id="btnSubmitPipelineTask">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- 5b. Delete Pipeline Task Confirmation Modal -->
<div class="modal-overlay pipeline-action-modal" id="deletePipelineTaskConfirmModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="deleteTaskConfirmTitle">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteTaskConfirmTitle">Delete Task?</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('deletePipelineTaskConfirmModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; margin: 0;">
                Are you sure you want to delete this task? This action cannot be undone.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('deletePipelineTaskConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-danger btn-md" style="background-color: #DC2626; color: #FFFFFF; border: none; font-weight: 600;" onclick="confirmDeletePipelineTask()">Delete Task</button>
        </div>
    </div>
</div>

<!-- 6. Add / Edit Pipeline Note Modal -->
<div class="modal-overlay pipeline-action-modal" id="addPipelineNoteModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addNoteTitle">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="addNoteTitle">Add Note for Deal</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('addPipelineNoteModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="addPipelineNoteForm" onsubmit="executeSavePipelineNote(event)">
            <div class="modal-body">
                <!-- Associated Deal (Read-Only) -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.04em;">Associated Deal</label>
                    <input type="text" class="input-control" id="pipelineNoteDealContext" readonly style="background-color: #F8FAFC; color: var(--text-heading); font-weight: 600;">
                </div>

                <div class="form-group">
                    <label class="form-label">Note Content *</label>
                    <textarea class="input-control" id="pipelineNoteContent" required style="height: 110px;" placeholder="Write a note about this deal..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Note Type</label>
                    <select class="input-control" id="pipelineNoteType">
                        <option value="General" selected>General</option>
                        <option value="Meeting">Meeting</option>
                        <option value="Call">Call</option>
                        <option value="Email">Email</option>
                        <option value="Follow-up">Follow-up</option>
                        <option value="Internal">Internal</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('addPipelineNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md" id="btnSubmitPipelineNote">Save Note</button>
            </div>
        </form>
    </div>
</div>

<!-- 6b. Delete Pipeline Note Confirmation Modal -->
<div class="modal-overlay pipeline-action-modal" id="deletePipelineNoteConfirmModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="deleteNoteConfirmTitle">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteNoteConfirmTitle">Delete Note?</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('deletePipelineNoteConfirmModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; margin: 0;">
                Are you sure you want to delete this note? This action cannot be undone.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('deletePipelineNoteConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-danger btn-md" style="background-color: #DC2626; color: #FFFFFF; border: none; font-weight: 600;" onclick="confirmDeletePipelineNote()">Delete Note</button>
        </div>
    </div>
</div>

<!-- 7. Filter Panel Popover -->
<!-- 7. Move Deal to Stage Modal -->
<div class="modal-overlay pipeline-action-modal" id="moveStageModal" role="dialog" aria-modal="true" aria-labelledby="moveStageModalTitle" aria-hidden="true">
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="modal-icon-badge" style="background-color: var(--primary-light); color: var(--primary); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                </div>
                <div>
                    <h3 class="modal-title" id="moveStageModalTitle">Move Deal to Stage</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 1px;">Choose a new pipeline stage for this deal</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('moveStageModal')" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="modal-body" style="padding: 18px 20px;">
            <!-- Selected Deal Summary Card -->
            <div id="moveStageDealSummary" style="margin-bottom: 16px;">
                <!-- Populated dynamically by JS -->
            </div>

            <!-- Stage Selection Label -->
            <label style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 10px;">Select Destination Stage</label>

            <!-- Selectable Stage Options -->
            <div class="move-stage-options-list" role="radiogroup" aria-label="Pipeline stages" style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($kanban_stages as $stg): ?>
                    <label class="move-stage-option-card" data-stage-id="<?php echo htmlspecialchars($stg['id']); ?>" onclick="selectMoveStageOption('<?php echo htmlspecialchars($stg['id']); ?>')">
                        <input type="radio" name="moveStageRadio" value="<?php echo htmlspecialchars($stg['id']); ?>" class="move-stage-radio">
                        <span class="stage-color-dot" style="background-color: <?php echo $stg['color']; ?>;"></span>
                        <div style="flex: 1; min-width: 0;">
                            <div class="stage-option-name"><?php echo htmlspecialchars($stg['label']); ?></div>
                            <div class="stage-option-desc">Win Probability: <?php echo (int)$stg['probability']; ?>%</div>
                        </div>
                        <span class="stage-check-icon">✓</span>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Transition Summary Pill -->
            <div id="moveStageTransitionSummary" style="margin-top: 14px; padding: 10px 12px; background-color: #F8FAFC; border: 1px solid var(--border-card); border-radius: var(--radius-md); font-size: 12.5px; display: flex; align-items: center; justify-content: space-between;">
                <span style="color: var(--text-muted); font-size: 11.5px; font-weight: 600;">STAGE TRANSITION</span>
                <span id="moveStageTransitionText" style="font-weight: 700; color: var(--primary);">Prospect → Proposal</span>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('moveStageModal')">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" id="btnConfirmMoveStage" onclick="confirmMoveDealToStage()">Move Deal</button>
        </div>
    </div>
</div>

<!-- 8. Log Deal Activity Modal -->
<div class="modal-overlay pipeline-action-modal" id="logDealActivityModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="logActivityTitle">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" id="logActivityTitle">Log Activity</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('logDealActivityModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="logDealActivityForm" onsubmit="executeLogDealActivity(event)">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Activity Type *</label>
                        <select class="input-control" id="logActivityType" required>
                            <option value="Call">Call</option>
                            <option value="Email" selected>Email</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Note">Note</option>
                            <option value="Stage Change">Stage Change</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Performed By</label>
                        <select class="input-control" id="logActivityUser">
                            <?php foreach ($active_users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$u['id'] === $currentUserId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Activity Title *</label>
                    <input type="text" class="input-control" id="logActivityTitleInput" required placeholder="e.g. Discovery Call with Prospect">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="input-control" id="logActivityDesc" style="height: 80px;padding:8px 12px" placeholder="Add notes or details of this activity..."></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" class="input-control" id="logActivityDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" class="input-control" id="logActivityTime">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('logDealActivityModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Log Activity</button>
            </div>
        </form>
    </div>
</div>

<!-- 9. Delete Deal Activity Confirmation Modal -->
<div class="modal-overlay pipeline-action-modal" id="deleteDealActivityConfirmModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="deleteActivityTitle">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteActivityTitle">Delete Activity?</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('deleteDealActivityConfirmModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; margin: 0;">
                Are you sure you want to delete this activity? This action cannot be undone.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('deleteDealActivityConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-danger btn-md" style="background-color: #DC2626; color: #FFFFFF; border: none; font-weight: 600;" onclick="confirmDeleteDealActivity()">Delete Activity</button>
        </div>
    </div>
</div>

<!-- 9b. Schedule Deal Meeting Modal (Calendar Integration) -->
<div class="modal-overlay pipeline-action-modal" id="scheduleDealMeetingModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="scheduleDealMeetingTitle">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="scheduleDealMeetingTitle">Schedule Meeting</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('scheduleDealMeetingModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="scheduleDealMeetingForm" onsubmit="executeScheduleDealMeeting(event)">
            <input type="hidden" id="dealMeetingDealId" value="">
            <input type="hidden" id="dealMeetingContactId" value="">
            <input type="hidden" id="dealMeetingCompanyId" value="">
            <div class="modal-body" style="padding: 20px;">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Meeting Title *</label>
                    <input type="text" class="input-control" id="dealMeetingTitle" required placeholder="e.g. Discovery Call with Client">
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">Date *</label>
                        <input type="date" class="input-control" id="dealMeetingDate" required>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">Host / Assigned Owner</label>
                        <select class="input-control" id="dealMeetingUser">
                            <?php foreach ($active_users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$u['id'] === $currentUserId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">Start Time *</label>
                        <input type="time" class="input-control" id="dealMeetingStartTime" value="10:00" required>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">End Time *</label>
                        <input type="time" class="input-control" id="dealMeetingEndTime" value="10:30" required>
                    </div>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">Associated Contact</label>
                        <input type="text" class="input-control" id="dealMeetingContactDisplay" readonly style="background: var(--bg-card); cursor: default;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">Associated Company</label>
                        <input type="text" class="input-control" id="dealMeetingCompanyDisplay" readonly style="background: var(--bg-card); cursor: default;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Location / Meeting Link</label>
                    <input type="text" class="input-control" id="dealMeetingLocation" placeholder="e.g. Video Meeting (Google Meet / Zoom) or Office">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Description / Agenda</label>
                    <textarea class="input-control" id="dealMeetingDesc" style="height: 70px; padding: 8px 12px; resize: none;" placeholder="Add agenda items, notes, or discussion points..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('scheduleDealMeetingModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md" id="btnSubmitDealMeeting">Schedule Meeting</button>
            </div>
        </form>
    </div>
</div>

<!-- 10. Deal Details Right-Side Drawer (4 Tabs: Overview, Activity, Tasks, Notes) -->
<div class="drawer-overlay" id="pipelineDealDrawer" role="dialog" aria-modal="true" aria-labelledby="dealDrawerTitle">
    <div class="drawer-content">
        <!-- Sticky Header -->
        <div class="drawer-header">
            <div>
                <div class="drawer-breadcrumb" id="dealDrawerBreadcrumb">Sales Pipeline / Marcus Thompson</div>
                <h3 class="drawer-title" id="dealDrawerTitle">Deal Details</h3>
            </div>
            <div class="drawer-header-actions">
                <button type="button" class="btn btn-secondary btn-xs drawer-action-btn" id="dealDrawerScheduleMeetingBtn" title="Schedule Meeting" aria-label="Schedule Meeting" onclick="openScheduleDealMeetingModal()" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; font-weight: 500;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Schedule Meeting
                </button>
                <button class="btn btn-ghost btn-xs drawer-action-btn" id="dealDrawerShareBtn" title="Share Deal" aria-label="Share Deal" onclick="window.NexFlowShare.openDealShare(currentOpenDealId, this)">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>
                <button class="btn btn-ghost btn-xs drawer-action-btn" id="dealDrawerEditBtn" title="Edit Deal" aria-label="Edit Deal" onclick="openEditDealModalFromDrawer()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn btn-ghost btn-xs drawer-action-btn" id="dealDrawerDeleteBtn" title="Delete Deal" aria-label="Delete Deal" style="color: #EF4444;" onclick="promptDeletePipelineDeal(activePipelineDealId)">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
                <button class="modal-close-btn drawer-close-btn" onclick="closePipelineDealDrawer()" aria-label="Close drawer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Sticky Tabs -->
        <div class="drawer-tabs-wrapper">
            <div class="drawer-tabs scrollbar-hide" role="tablist" aria-label="Deal details tabs">
                <button class="drawer-tab active" id="tab-pipeline-overview" role="tab" aria-selected="true" aria-controls="pipelineDrawerBody" data-tab="overview" onclick="switchPipelineDrawerTab('overview')">Overview</button>
                <button class="drawer-tab" id="tab-pipeline-activity" role="tab" aria-selected="false" aria-controls="pipelineDrawerBody" data-tab="activity" onclick="switchPipelineDrawerTab('activity')">Activity</button>
                <button class="drawer-tab" id="tab-pipeline-tasks" role="tab" aria-selected="false" aria-controls="pipelineDrawerBody" data-tab="tasks" onclick="switchPipelineDrawerTab('tasks')">Tasks</button>
                <button class="drawer-tab" id="tab-pipeline-notes" role="tab" aria-selected="false" aria-controls="pipelineDrawerBody" data-tab="notes" onclick="switchPipelineDrawerTab('notes')">Notes</button>
            </div>
        </div>
 
        <!-- Drawer Content Body -->
        <div class="drawer-body" id="pipelineDrawerBody">
            <!-- Dynamic deal details loaded by JS -->
        </div>
    </div>
</div>

<!-- 11. Delete Deal Confirmation Modal -->
<div class="modal-overlay pipeline-action-modal" id="deleteDealConfirmModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="deleteDealConfirmTitle">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteDealConfirmTitle" style="color: #DC2626;">Delete Deal?</h3>
            <button type="button" class="modal-close-btn" onclick="closePipelineModal('deleteDealConfirmModal')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="font-size: 13.5px; color: var(--text-body); line-height: 1.5; margin: 0 0 8px 0;">
                Are you sure you want to delete <strong id="deleteDealModalName">this deal</strong>?
            </p>
            <p style="font-size: 12px; color: var(--text-muted); margin: 0;">
                This action cannot be undone. Associated company and contact records will remain intact.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="closePipelineModal('deleteDealConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-danger btn-md" style="background-color: #DC2626; color: #FFFFFF; border: none; font-weight: 600;" onclick="confirmDeletePipelineDeal()">Delete Deal</button>
        </div>
    </div>
</div>

<script>
    window.pipelineInitialCurrentUser = { id: <?php echo $currentUserId; ?>, name: <?php echo json_encode($currentUser['name'] ?? 'User'); ?> };
    window.pipelineInitialContacts = <?php echo json_encode($active_contacts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.pipelineInitialCompanies = <?php echo json_encode($active_companies, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


