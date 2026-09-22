<?php
// client-deals.php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-deals-data.php';

$client = require_client_auth();
$pdo = nexflow_db();

$orgId       = (int)$client['organization_id'];
$companyId   = (int)$client['company_id'];
$contactId   = (int)$client['contact_id'];
$companyName = (string)$client['company_name'];

$deals    = client_get_deals_list($pdo, $orgId, $companyName);
$currency = client_get_deals_currency($pdo, $orgId);

$page_title   = "My Deals & Contracts — NexFlow Client Portal";
$active_nav   = "deals";
$page_heading = "My Deals & Active Contracts";
include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <!-- Page Header & Summary -->
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Review your active proposals, contract expansions, and renewal milestones with your NexFlow account team.</p>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="client-portal-toolbar">
            <div class="client-portal-toolbar-left">
                <div class="client-portal-search-wrapper" style="width:280px;">
                    <svg class="client-portal-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="client-portal-search-input" id="dealsSearchInput" placeholder="Search contracts or stages..." oninput="window.renderClientDeals()">
                </div>

                <select class="input-control" id="dealsStatusFilter" style="width:160px;height:36px;" onchange="window.renderClientDeals()">
                    <option value="All">All Statuses</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Under Review">Under Review</option>
                    <option value="Active">Active / Won</option>
                </select>
            </div>

            <div class="client-portal-toolbar-right">
                <select class="input-control" id="dealsSortSelect" style="width:170px;height:36px;" onchange="window.renderClientDeals()">
                    <option value="value-desc">Highest Value</option>
                    <option value="value-asc">Lowest Value</option>
                    <option value="date-asc">Closing Soonest</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Contract Question')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contact Account Lead
                </button>
            </div>
        </div>

        <!-- Deals Table Card -->
        <div class="client-portal-card">
            <div class="client-portal-table-wrapper">
                <table class="client-portal-table">
                    <thead>
                        <tr>
                            <th>Deal / Contract Name</th>
                            <th>Value</th>
                            <th>Current Stage</th>
                            <th>Confidence</th>
                            <th>Expected Close</th>
                            <th>NexFlow Owner</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="clientDealsTbody">
                        <?php if (empty($deals)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:32px;color:#64748B;">No matching contracts found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($deals as $d): ?>
                                <?php
                                $stageStr = strtolower((string)$d['stage']);
                                $stageBadgeClass = 'amber';
                                if (strpos($stageStr, 'won') !== false || strpos($stageStr, 'active') !== false) {
                                    $stageBadgeClass = 'green';
                                } elseif (strpos($stageStr, 'proposal') !== false || strpos($stageStr, 'review') !== false) {
                                    $stageBadgeClass = 'blue';
                                }
                                $probInt = (int)round((float)$d['probability']);
                                $probColor = ($probInt === 100) ? '#10B981' : '#2563EB';
                                $descSnippet = mb_substr((string)$d['description'], 0, 70);
                                $hasMore = mb_strlen((string)$d['description']) > 70;
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600;color:#0F172A;font-size:13.5px;"><?php echo htmlspecialchars((string)$d['name']); ?></div>
                                        <div style="font-size:11.5px;color:#64748B;margin-top:2px;"><?php echo htmlspecialchars($descSnippet . ($hasMore ? '...' : '')); ?></div>
                                    </td>
                                    <td style="font-weight:700;color:#0F172A;font-size:14px;"><?php echo htmlspecialchars((string)$d['formattedValue']); ?></td>
                                    <td>
                                        <span class="client-portal-badge <?php echo $stageBadgeClass; ?>"><?php echo htmlspecialchars((string)$d['stage']); ?></span>
                                    </td>
                                    <td style="width:130px;">
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="flex:1;height:6px;background:#E2E8F0;border-radius:3px;overflow:hidden;">
                                                <div style="width:<?php echo min(100, max(0, $probInt)); ?>%;height:100%;background:<?php echo $probColor; ?>;"></div>
                                            </div>
                                            <span style="font-size:11.5px;font-weight:600;color:#64748B;"><?php echo $probInt; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="font-size:13px;color:#334155;"><?php echo htmlspecialchars((string)$d['closeDate']); ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div class="client-portal-avatar" style="background-color:#7C3AED;width:22px;height:22px;font-size:9px;"><?php echo htmlspecialchars((string)$d['ownerInitials']); ?></div>
                                            <span style="font-size:12.5px;color:#334155;"><?php echo htmlspecialchars((string)$d['owner']); ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientDeals.openDealDrawer(<?php echo (int)$d['id']; ?>, this)">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
window.__CLIENT_DEALS_DATA__ = <?php echo json_encode([
    'deals'    => $deals,
    'currency' => $currency,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>
<script src="assets/js/client-deals.js"></script>