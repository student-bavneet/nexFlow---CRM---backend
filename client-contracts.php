<?php
// client-contracts.php
$page_title = "Contracts — NexFlow Client Portal";
$active_nav = "contracts";
$page_heading = "Contracts";

require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-contracts-data.php';

$client = require_client_auth();
$orgId = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];
$contactId = (int)$client['contact_id'];
$contactName = (string)($client['contact_name'] ?? 'Client');

$contractsData = client_get_contracts_data(nexflow_db(), $orgId, $companyId);
$kpis = $contractsData['kpis'];
$initialContracts = $contractsData['contracts'];
$currencySymbol = $contractsData['currencySymbol'];

include __DIR__ . '/includes/client-header.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(client_csrf_token()); ?>">
<link rel="stylesheet" href="assets/css/client-contracts.css">
<?php
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <!-- Page Header Description -->
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Review your contracts, agreement details, important dates, and contract status.</p>
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
                    <div class="client-portal-kpi-val" id="kpiTotalContracts"><?php echo (int)$kpis['total']; ?></div>
                    <div class="client-portal-kpi-label">Total Contracts</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon green">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiActiveContracts"><?php echo (int)$kpis['active']; ?></div>
                    <div class="client-portal-kpi-label">Active Contracts</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon amber">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiPendingContracts"><?php echo (int)$kpis['pending']; ?></div>
                    <div class="client-portal-kpi-label">Pending Signature</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon red">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiExpiringContracts"><?php echo (int)$kpis['expiring']; ?></div>
                    <div class="client-portal-kpi-label">Expiring Soon / Expired</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="client-portal-toolbar">
            <div class="client-portal-toolbar-left" style="flex-wrap:wrap;gap:10px;">
                <div class="client-portal-search-wrapper" style="width:240px;">
                    <svg class="client-portal-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="client-portal-search-input" id="contractsSearchInput" placeholder="Search contract, project or type..." oninput="window.renderClientContracts()">
                </div>

                <select class="input-control" id="contractsStatusFilter" style="width:150px;height:36px;" onchange="window.renderClientContracts()">
                    <option value="All">All Statuses</option>
                    <option value="Draft">Draft</option>
                    <option value="Sent">Sent</option>
                    <option value="Viewed">Viewed</option>
                    <option value="Pending Signature">Pending Signature</option>
                    <option value="Active">Active</option>
                    <option value="Expired">Expired</option>
                    <option value="Terminated">Terminated</option>
                </select>

                <select class="input-control" id="contractsTypeFilter" style="width:170px;height:36px;" onchange="window.renderClientContracts()">
                    <option value="All">All Contract Types</option>
                    <option value="Development Agreement">Development Agreement</option>
                    <option value="Maintenance Agreement">Maintenance Agreement</option>
                    <option value="Service Agreement">Service Agreement</option>
                    <option value="NDA">NDA</option>
                    <option value="Consulting Agreement">Consulting Agreement</option>
                    <option value="Retainer Agreement">Retainer Agreement</option>
                </select>
            </div>

            <div class="client-portal-toolbar-right">
                <select class="input-control" id="contractsSortSelect" style="width:150px;height:36px;" onchange="window.renderClientContracts()">
                    <option value="newest">Newest Start</option>
                    <option value="oldest">Oldest Start</option>
                    <option value="value">Highest Value</option>
                    <option value="end">End Date</option>
                    <option value="name">Contract Name</option>
                    <option value="status">Status</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Contract Renewal / Query')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contract Inquiry
                </button>
            </div>
        </div>

        <!-- Contracts Table Card -->
        <div class="client-portal-card" id="clientContractsTableCard">
            <div class="client-portal-table-wrapper">
                <table class="client-portal-table">
                    <thead>
                        <tr>
                            <th>Contract ID &amp; Title</th>
                            <th>Contract Value</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="clientContractsTbody">
                        <!-- Populated dynamically by client-contracts.js -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State Container -->
        <div id="clientContractsEmptyState" style="display:none;margin-top:20px;">
            <div style="text-align:center;padding:48px 20px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;">
                <svg width="48" height="48" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px auto;display:block;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
                <h3 style="font-size:16px;font-weight:700;color:#0F172A;margin:0 0 6px 0;">No Contracts Found</h3>
                <p style="font-size:13px;color:#64748B;margin:0;">You currently don't have any contracts matching your search or filter criteria.</p>
            </div>
        </div>

    </main>
</div>

<!-- Dedicated Right-Side Contract Details Drawer -->
<div class="client-portal-drawer-overlay" id="clientContractDrawer" role="dialog" aria-modal="true" aria-labelledby="ccdContractTitle" onclick="if(event.target===this) window.clientContracts.closeContractDrawer()">
    <div class="client-portal-drawer-panel" onclick="event.stopPropagation()">
        <div class="client-portal-drawer-header">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <div class="client-portal-drawer-badge" id="ccdContractStatusBadge">Pending Signature</div>
                </div>
                <h3 class="client-portal-drawer-title" id="ccdContractTitle">Contract Details</h3>
            </div>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientContracts.closeContractDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        
        <!-- Drawer Tab Switcher -->
        <div class="client-portal-drawer-tabs" role="tablist" aria-label="Contract details navigation">
            <button type="button" class="client-portal-drawer-tab active" role="tab" aria-selected="true" data-tab="overview" onclick="window.clientContracts.switchContractDrawerTab('overview')">Overview &amp; Parties</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="terms" onclick="window.clientContracts.switchContractDrawerTab('terms')">Contract Terms</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="document" onclick="window.clientContracts.switchContractDrawerTab('document')">Document &amp; Signatures</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="timeline" onclick="window.clientContracts.switchContractDrawerTab('timeline')">Activity History</button>
        </div>

        <!-- Drawer Scrollable Body -->
        <div class="client-portal-drawer-body" id="clientContractDrawerBody" tabindex="0">
            <!-- Dynamically injected tab panels by assets/js/client-contracts.js -->
        </div>

        <div class="client-portal-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientContracts.closeContractDrawer()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Contract Help')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Contact Account Lead
            </button>
        </div>
    </div>
</div>

<!-- CLIENT MODAL: REQUEST CHANGES -->
<div class="modal-overlay" id="requestChangesModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); z-index:1100; align-items:center; justify-content:center;" onclick="if(event.target===this) window.clientContracts.closeRequestChangesModal()">
    <div style="background:#FFF; border-radius:12px; width:100%; max-width:480px; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); border:1px solid #E2E8F0;" onclick="event.stopPropagation()">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="font-size:16px; font-weight:700; color:#0F172A; margin:0;">Request Changes</h3>
            <button type="button" class="modal-close-btn" onclick="window.clientContracts.closeRequestChangesModal()" aria-label="Close modal">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13px; color:#64748B; margin:0 0 16px 0;">Tell your account team what you would like to change in this contract.</p>
        
        <div style="margin-bottom:18px;">
            <label class="form-label" style="font-size:12.5px; font-weight:600; color:#334155; margin-bottom:6px; display:block;">What would you like to change?</label>
            <textarea id="rcTextarea" class="input-control" rows="4" style="width:100%; font-size:13px; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px; resize:none;" placeholder="Example: Please change the payment terms to Net 30 days."></textarea>
        </div>
        
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientContracts.closeRequestChangesModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientContracts.submitRequestChanges()">Submit Request</button>
        </div>
    </div>
</div>

<!-- CLIENT MODAL: SIGN CONTRACT -->
<div class="modal-overlay" id="signContractModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); z-index:1100; align-items:center; justify-content:center;" onclick="if(event.target===this) window.clientContracts.closeSignContractModal()">
    <div style="background:#FFF; border-radius:12px; width:100%; max-width:480px; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); border:1px solid #E2E8F0;" onclick="event.stopPropagation()">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="font-size:16px; font-weight:700; color:#0F172A; margin:0;">Sign Contract</h3>
            <button type="button" class="modal-close-btn" onclick="window.clientContracts.closeSignContractModal()" aria-label="Close modal">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13px; color:#64748B; margin:0 0 16px 0;">Sign contract <strong id="scModalId">CON-002</strong> electronically.</p>
        
        <div style="margin-bottom:16px;">
            <label class="form-label" style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:6px;">Full Name (Digital Signature)</label>
            <input type="text" id="scSignatoryInput" class="input-control input-sm" style="width:100%;" value="<?php echo htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter your full legal name">
        </div>
        <div style="margin-bottom:20px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:12px; font-size:12px; color:#475569;">
            <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer;">
                <input type="checkbox" id="scConsentCheckbox" style="margin-top:2px;">
                <span>I confirm that I have reviewed the agreement terms and agree to digitally sign this binding contract.</span>
            </label>
        </div>
        
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientContracts.closeSignContractModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientContracts.confirmSignContract()">Confirm &amp; Sign Contract</button>
        </div>
    </div>
</div>

<!-- CLIENT MODAL: DECLINE CONTRACT -->
<div class="modal-overlay" id="declineContractModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); z-index:1100; align-items:center; justify-content:center;" onclick="if(event.target===this) window.clientContracts.closeDeclineContractModal()">
    <div style="background:#FFF; border-radius:12px; width:100%; max-width:440px; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); border:1px solid #E2E8F0;" onclick="event.stopPropagation()">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h3 style="font-size:16px; font-weight:700; color:#DC2626; margin:0;">Decline Contract?</h3>
            <button type="button" class="modal-close-btn" onclick="window.clientContracts.closeDeclineContractModal()" aria-label="Close modal">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:13.5px; color:#475569; margin:0 0 20px 0; line-height:1.5;">Are you sure you want to decline this contract? Your account team will be notified.</p>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientContracts.closeDeclineContractModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" style="background:#DC2626; border-color:#DC2626;" onclick="window.clientContracts.confirmDeclineContract()">Decline Contract</button>
        </div>
    </div>
</div>

<script>
window.clientPortalCsrf = <?php echo json_encode(client_csrf_token()); ?>;
window.clientContactName = <?php echo json_encode($contactName); ?>;
window.INITIAL_CONTRACT_KPIS = <?php echo json_encode($kpis); ?>;
window.INITIAL_CONTRACTS = <?php echo json_encode($initialContracts); ?>;
</script>
<script src="assets/js/client-contracts.js"></script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>

