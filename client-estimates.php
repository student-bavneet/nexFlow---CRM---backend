<?php
// client-estimates.php
$page_title = "Estimates — NexFlow Client Portal";
$active_nav = "estimates";
$page_heading = "Estimates";

require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-estimates-data.php';

$client = require_client_auth();
$orgId = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];
$contactId = (int)$client['contact_id'];
$contactName = (string)($client['contact_name'] ?? 'Client');

$estimatesData = client_get_estimates_data(nexflow_db(), $orgId, $companyId);
$kpis = $estimatesData['kpis'];
$initialEstimates = $estimatesData['estimates'];
$currencySymbol = $estimatesData['currencySymbol'];

include __DIR__ . '/includes/client-header.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(client_csrf_token()); ?>">
<link rel="stylesheet" href="assets/css/client-estimates.css">
<?php
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <!-- Page Header Description -->
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Review estimates, check pricing details, and manage your quotations.</p>
        </div>

        <!-- Summary KPI Stat Cards -->
        <div class="client-portal-kpi-grid">
            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon blue">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="16" y1="14" x2="16" y2="18"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiTotalEstimates"><?php echo (int)$kpis['total']; ?></div>
                    <div class="client-portal-kpi-label">Total Estimates</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon amber">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiPendingEstimates"><?php echo (int)$kpis['pending']; ?></div>
                    <div class="client-portal-kpi-label">Pending Review</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon green">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiAcceptedEstimates"><?php echo (int)$kpis['accepted']; ?></div>
                    <div class="client-portal-kpi-label">Accepted</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon red">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiExpiredEstimates"><?php echo (int)$kpis['declined']; ?></div>
                    <div class="client-portal-kpi-label">Expired / Declined</div>
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
                    <input type="text" class="client-portal-search-input" id="estimatesSearchInput" placeholder="Search estimate ID, title, or project..." oninput="window.renderClientEstimates()">
                </div>

                <select class="input-control" id="estimatesStatusFilter" style="width:160px;height:36px;" onchange="window.renderClientEstimates()">
                    <option value="All">All Statuses</option>
                    <option value="Pending">Pending Review</option>
                    <option value="Approved">Quotation Issued</option>
                    <option value="In Review">In Review</option>
                    <option value="Accepted">Accepted</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>

            <div class="client-portal-toolbar-right">
                <select class="input-control" id="estimatesSortSelect" style="width:170px;height:36px;" onchange="window.renderClientEstimates()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name">Estimate Name</option>
                    <option value="status">Status</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Quotation Request')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Request New Quote
                </button>
            </div>
        </div>

        <!-- Estimates Table Card -->
        <div class="client-portal-card" id="clientEstimatesTableCard" style="<?php echo empty($initialEstimates) ? 'display:none;' : ''; ?>">
            <div class="client-portal-table-wrapper">
                <table class="client-portal-table">
                    <thead>
                        <tr>
                            <th>Estimate ID &amp; Title</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Issue Date</th>
                            <th>Expiry Date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="clientEstimatesTbody">
                        <?php foreach ($initialEstimates as $e): 
                            $status = $e['display_status'];
                            $statusClass = $e['badge_class'];
                            $isAct = $e['is_actionable'];
                        ?>
                            <tr>
                                <td>
                                    <span class="client-estimate-id"><?php echo htmlspecialchars($e['request_code']); ?></span>
                                    <div style="font-weight:600;color:#0F172A;font-size:13.5px;margin-top:2px;"><?php echo htmlspecialchars($e['subject']); ?></div>
                                    <div style="font-size:11.5px;color:#64748B;">
                                        <?php if (!empty($e['project_name'])): ?>Project: <?php echo htmlspecialchars($e['project_name']); ?> • <?php endif; ?>
                                        <?php if (!empty($e['deal_name'])): ?>Deal: <?php echo htmlspecialchars($e['deal_name']); ?><?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-weight:700;color:#0F172A;font-size:14px;"><?php echo htmlspecialchars($e['formatted_amount']); ?></td>
                                <td>
                                    <span class="client-portal-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td style="font-size:12.5px;color:#334155;"><?php echo htmlspecialchars($e['issue_date']); ?></td>
                                <td style="font-size:12.5px;color:#334155;"><?php echo htmlspecialchars($e['expiry_date']); ?></td>
                                <td style="text-align:right;">
                                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientEstimates.openEstimateDrawer(<?php echo (int)$e['id']; ?>, this)">
                                            View Estimate
                                        </button>
                                        <?php if ($isAct): ?>
                                            <button type="button" class="btn btn-primary btn-xs" onclick="window.clientEstimates.promptAccept(<?php echo (int)$e['id']; ?>)">
                                                Accept
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-xs" style="color:#B91C1C;" onclick="window.clientEstimates.promptDecline(<?php echo (int)$e['id']; ?>)">
                                                Decline
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State Container -->
        <div id="clientEstimatesEmptyState" style="<?php echo empty($initialEstimates) ? 'display:block;' : 'display:none;'; ?>margin-top:20px;">
            <div style="text-align:center;padding:48px 20px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;">
                <svg width="48" height="48" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px auto;display:block;">
                    <rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="8" y1="6" x2="16" y2="6"/>
                </svg>
                <h3 style="font-size:16px;font-weight:700;color:#0F172A;margin:0 0 6px 0;">No Estimates Found</h3>
                <p style="font-size:13px;color:#64748B;margin:0;">You currently don't have any estimates matching your search or status criteria.</p>
            </div>
        </div>

    </main>
</div>

<!-- Dedicated Right-Side Estimate Details Drawer -->
<div class="client-portal-drawer-overlay" id="clientEstimateDrawer" role="dialog" aria-modal="true" aria-labelledby="cedEstimateTitle" onclick="if(event.target===this) window.clientEstimates.closeEstimateDrawer()">
    <div class="client-portal-drawer-panel" onclick="event.stopPropagation()">
        <div class="client-portal-drawer-header">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <div class="client-portal-drawer-badge" id="cedEstimateStatusBadge">Pending</div>
                </div>
                <h3 class="client-portal-drawer-title" id="cedEstimateTitle">Estimate Details</h3>
            </div>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientEstimates.closeEstimateDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        
        <!-- Drawer Tab Switcher -->
        <div class="client-portal-drawer-tabs" role="tablist" aria-label="Estimate details navigation">
            <button type="button" class="client-portal-drawer-tab active" role="tab" aria-selected="true" data-tab="overview" onclick="window.clientEstimates.switchEstimateDrawerTab('overview')">Overview</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="items" onclick="window.clientEstimates.switchEstimateDrawerTab('items')">Line Items &amp; Pricing</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="terms" onclick="window.clientEstimates.switchEstimateDrawerTab('terms')">Terms &amp; Conditions</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="timeline" onclick="window.clientEstimates.switchEstimateDrawerTab('timeline')">Activity History</button>
        </div>

        <!-- Drawer Scrollable Body -->
        <div class="client-portal-drawer-body" id="clientEstimateDrawerBody" tabindex="0">
            <!-- Dynamically injected tab panels by assets/js/client-estimates.js -->
        </div>

        <div class="client-portal-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientEstimates.closeEstimateDrawer()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Estimate Question')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Contact Account Lead
            </button>
        </div>
    </div>
</div>

<script>
window.clientPortalCsrf = <?php echo json_encode(client_csrf_token()); ?>;
</script>
<script src="assets/js/client-estimates.js"></script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>