<?php
// client-proposals.php
$page_title = "Proposals — NexFlow Client Portal";
$active_nav = "proposals";
$page_heading = "Proposals";

require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-proposals-data.php';

$client = require_client_auth();
$orgId = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];
$contactId = (int)$client['contact_id'];
$contactName = (string)($client['contact_name'] ?? 'Client');


$proposalsData = client_get_proposals_data(nexflow_db(), $orgId, $companyId);
$kpis = $proposalsData['kpis'];
$initialProposals = $proposalsData['proposals'];
$currencySymbol = $proposalsData['currencySymbol'];

include __DIR__ . '/includes/client-header.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(client_csrf_token()); ?>">
<link rel="stylesheet" href="assets/css/client-proposals.css">
<?php
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <!-- Page Header Description -->
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Review proposals, track their status, and manage your submitted proposals.</p>
        </div>

        <!-- Summary KPI Stat Cards -->
        <div class="client-portal-kpi-grid">
            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon blue">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiTotalProposals"><?php echo (int)$kpis['total']; ?></div>
                    <div class="client-portal-kpi-label">Total Proposals</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon amber">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiPendingProposals"><?php echo (int)$kpis['pending']; ?></div>
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
                    <div class="client-portal-kpi-val" id="kpiAcceptedProposals"><?php echo (int)$kpis['accepted']; ?></div>
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
                    <div class="client-portal-kpi-val" id="kpiDeclinedProposals"><?php echo (int)$kpis['declined']; ?></div>
                    <div class="client-portal-kpi-label">Declined / Expired</div>
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
                    <input type="text" class="client-portal-search-input" id="proposalsSearchInput" placeholder="Search proposal, project or deal..." oninput="window.renderClientProposals()">
                </div>

                <select class="input-control" id="proposalsStatusFilter" style="width:170px;height:36px;" onchange="window.renderClientProposals()">
                    <option value="All">All Statuses</option>
                    <option value="Pending">Pending Review</option>
                    <option value="Sent">Sent</option>
                    <option value="Viewed">Viewed</option>
                    <option value="Changes Requested">Changes Requested</option>
                    <option value="Accepted">Accepted</option>
                    <option value="Declined">Declined</option>
                    <option value="Expired">Expired</option>
                </select>
            </div>

            <div class="client-portal-toolbar-right">
                <select class="input-control" id="proposalsSortSelect" style="width:170px;height:36px;" onchange="window.renderClientProposals()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="amount">Highest Amount</option>
                    <option value="expiry">Expiry Date</option>
                    <option value="name">Proposal Title</option>
                    <option value="status">Status</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Proposal Question')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contact Account Lead
                </button>
            </div>
        </div>

        <!-- Proposals Table Card -->
        <div class="client-portal-card" id="clientProposalsTableCard" style="<?php echo empty($initialProposals) ? 'display:none;' : ''; ?>">
            <div class="client-portal-table-wrapper">
                <table class="client-portal-table">
                    <thead>
                        <tr>
                            <th class="col-proposal-info">Proposal ID &amp; Title</th>
                            <th class="col-amount">Amount</th>
                            <th class="col-status">Status</th>
                            <th class="col-issue-date">Issue Date</th>
                            <th class="col-expiry-date">Expiry Date</th>
                            <th class="col-actions" style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="clientProposalsTbody">
                        <?php foreach ($initialProposals as $p): 
                            $status = $p['display_status'];
                            $statusClass = 'amber';
                            if ($status === 'Accepted') $statusClass = 'green';
                            elseif (in_array($status, ['Pending', 'Sent', 'Viewed'], true)) $statusClass = 'blue';
                            elseif ($status === 'Changes Requested') $statusClass = 'amber';
                            elseif (in_array($status, ['Declined', 'Expired'], true)) $statusClass = 'red';
                            $isAct = $p['is_actionable'];
                        ?>
                            <tr>
                                <td class="cell-proposal-info">
                                    <span class="client-proposal-id"><?php echo htmlspecialchars($p['proposal_number']); ?></span>
                                    <div style="font-weight:600;color:#0F172A;font-size:13.5px;margin-top:2px;"><?php echo htmlspecialchars($p['title']); ?></div>
                                    <div style="font-size:11.5px;color:#64748B;">
                                        <?php if (!empty($p['project_name'])): ?>Project: <?php echo htmlspecialchars($p['project_name']); ?> • <?php endif; ?>
                                        <?php if (!empty($p['deal_name'])): ?>Deal: <?php echo htmlspecialchars($p['deal_name']); ?><?php endif; ?>
                                    </div>
                                </td>
                                <td class="cell-amount" style="font-weight:700;color:#0F172A;font-size:14px;"><?php echo htmlspecialchars($p['formatted_total']); ?></td>
                                <td class="cell-status">
                                    <span class="client-portal-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td class="cell-issue-date" style="font-size:12.5px;color:#334155;"><?php echo htmlspecialchars($p['issue_date']); ?></td>
                                <td class="cell-expiry-date" style="font-size:12.5px;color:#334155;"><?php echo htmlspecialchars($p['expiry_date']); ?></td>
                                <td class="cell-actions" style="text-align:right;">
                                    <div class="proposal-actions">
                                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientProposals.openProposalDrawer('<?php echo (int)$p['id']; ?>', this)">
                                            View Proposal
                                        </button>
                                        <?php if ($isAct): ?>
                                            <button type="button" class="btn btn-primary btn-xs" onclick="window.clientProposals.promptAccept('<?php echo (int)$p['id']; ?>')">
                                                Accept
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-xs btn-request-changes" onclick="window.clientProposals.promptRequestChanges('<?php echo (int)$p['id']; ?>')">
                                                Request Changes
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-xs btn-decline" onclick="window.clientProposals.promptDecline('<?php echo (int)$p['id']; ?>')">
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
        <div id="clientProposalsEmptyState" style="<?php echo empty($initialProposals) ? 'display:block;' : 'display:none;'; ?>margin-top:20px;">
            <div style="text-align:center;padding:48px 20px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;">
                <svg width="48" height="48" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px auto;display:block;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
                <h3 style="font-size:16px;font-weight:700;color:#0F172A;margin:0 0 6px 0;">No Proposals Found</h3>
                <p style="font-size:13px;color:#64748B;margin:0;">You currently don't have any proposals matching your search or status criteria.</p>
            </div>
        </div>

    </main>
</div>

<!-- Dedicated Right-Side Proposal Details Drawer -->
<div class="client-portal-drawer-overlay" id="clientProposalDrawer" role="dialog" aria-modal="true" aria-labelledby="cpdProposalTitle" onclick="if(event.target===this) window.clientProposals.closeProposalDrawer()">
    <div class="client-portal-drawer-panel" onclick="event.stopPropagation()">
        <div class="client-portal-drawer-header">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <div class="client-portal-drawer-badge" id="cpdProposalStatusBadge">Pending</div>
                </div>
                <h3 class="client-portal-drawer-title" id="cpdProposalTitle">Proposal Details</h3>
            </div>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientProposals.closeProposalDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        
        <!-- Drawer Tab Switcher -->
        <div class="client-portal-drawer-tabs" role="tablist" aria-label="Proposal details navigation">
            <button type="button" class="client-portal-drawer-tab active" role="tab" aria-selected="true" data-tab="overview" onclick="window.clientProposals.switchProposalDrawerTab('overview')">Overview</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="items" onclick="window.clientProposals.switchProposalDrawerTab('items')">Itemized Deliverables</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="terms" onclick="window.clientProposals.switchProposalDrawerTab('terms')">Terms &amp; Conditions</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="timeline" onclick="window.clientProposals.switchProposalDrawerTab('timeline')">Activity History</button>
        </div>

        <!-- Drawer Scrollable Body -->
        <div class="client-portal-drawer-body" id="clientProposalDrawerBody" tabindex="0">
            <!-- Dynamically populated via assets/js/client-proposals.js -->
        </div>

        <div class="client-portal-drawer-footer">
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientProposals.closeProposalDrawer()">Close</button>
                <button type="button" class="btn btn-secondary btn-sm" id="btnClientPrintProposal" onclick="window.clientProposals.printCurrentProposal()" title="Print or download proposal summary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:-2px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print / Download
                </button>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Proposal Discussion')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Contact Account Lead
            </button>
        </div>
    </div>
</div>

<script>
window.clientPortalCsrf = <?php echo json_encode(client_csrf_token()); ?>;
</script>
<script src="assets/js/client-proposals.js"></script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>