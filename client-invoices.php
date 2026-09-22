<?php
// client-invoices.php
$page_title = "Invoices — NexFlow Client Portal";
$active_nav = "invoices";
$page_heading = "Invoices";

require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-invoices-data.php';

$client = require_client_auth();
$orgId = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];
$contactId = (int)$client['contact_id'];
$contactName = (string)($client['contact_name'] ?? 'Client');

$invoicesData = client_get_invoices_data(nexflow_db(), $orgId, $companyId);
$kpis = $invoicesData['kpis'];
$initialInvoices = $invoicesData['invoices'];
$currencySymbol = $invoicesData['currencySymbol'];

include __DIR__ . '/includes/client-header.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(client_csrf_token()); ?>">
<link rel="stylesheet" href="assets/css/client-invoices.css">
<?php
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <!-- Page Header Description -->
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">View your invoices, payment status, due dates, and billing details.</p>
        </div>

        <!-- Summary KPI Stat Cards -->
        <div class="client-portal-kpi-grid">
            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon blue">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiTotalInvoices"><?php echo (int)($kpis['total'] ?? 0); ?></div>
                    <div class="client-portal-kpi-label">Total Invoices</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon amber">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiOutstandingInvoices"><?php echo htmlspecialchars($currencySymbol . number_format((float)($kpis['outstanding'] ?? 0), 2)); ?></div>
                    <div class="client-portal-kpi-label">Outstanding Amount</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon green">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiPaidInvoices"><?php echo htmlspecialchars($currencySymbol . number_format((float)($kpis['paid'] ?? 0), 2)); ?></div>
                    <div class="client-portal-kpi-label">Total Paid</div>
                </div>
            </div>

            <div class="client-portal-kpi-card">
                <div class="client-portal-kpi-icon red">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="kpiOverdueInvoices"><?php echo htmlspecialchars($currencySymbol . number_format((float)($kpis['overdue'] ?? 0), 2)); ?></div>
                    <div class="client-portal-kpi-label">Overdue Amount</div>
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
                    <input type="text" class="client-portal-search-input" id="invoicesSearchInput" placeholder="Search invoice, project or deal..." oninput="window.renderClientInvoices()">
                </div>

                <select class="input-control" id="invoicesStatusFilter" style="width:140px;height:36px;" onchange="window.renderClientInvoices()">
                    <option value="All">All Statuses</option>
                    <option value="Draft">Draft</option>
                    <option value="Sent">Sent</option>
                    <option value="Viewed">Viewed</option>
                    <option value="Pending">Pending</option>
                    <option value="Paid">Paid</option>
                    <option value="Partially Paid">Partially Paid</option>
                    <option value="Overdue">Overdue</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <select class="input-control" id="invoicesPaymentFilter" style="width:150px;height:36px;" onchange="window.renderClientInvoices()">
                    <option value="All">All Payment States</option>
                    <option value="Unpaid">Unpaid</option>
                    <option value="Partially Paid">Partially Paid</option>
                    <option value="Paid">Paid</option>
                    <option value="Overdue">Overdue</option>
                </select>
            </div>

            <div class="client-portal-toolbar-right">
                <select class="input-control" id="invoicesSortSelect" style="width:150px;height:36px;" onchange="window.renderClientInvoices()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="due">Due Date</option>
                    <option value="amount">Highest Amount</option>
                    <option value="number">Invoice Number</option>
                    <option value="status">Status</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Billing Inquiry')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Billing Inquiry
                </button>
            </div>
        </div>

        <!-- Invoices Table Card -->
        <div class="client-portal-card" id="clientInvoicesTableCard">
            <div class="client-portal-table-wrapper">
                <table class="client-portal-table">
                    <thead>
                        <tr>
                            <th>Invoice Number &amp; Title</th>
                            <th>Total &amp; Balance</th>
                            <th>Status &amp; Payment</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="clientInvoicesTbody">
                        <!-- Populated dynamically by client-invoices.js -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State Container -->
        <div id="clientInvoicesEmptyState" style="display:none;margin-top:20px;">
            <div style="text-align:center;padding:48px 20px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;">
                <svg width="48" height="48" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px auto;display:block;">
                    <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
                <h3 style="font-size:16px;font-weight:700;color:#0F172A;margin:0 0 6px 0;">No Invoices Found</h3>
                <p style="font-size:13px;color:#64748B;margin:0;">You currently don't have any invoices matching your search or filter criteria.</p>
            </div>
        </div>

    </main>
</div>

<!-- Dedicated Right-Side Invoice Details Drawer -->
<div class="client-portal-drawer-overlay" id="clientInvoiceDrawer" role="dialog" aria-modal="true" aria-labelledby="cidInvoiceTitle" onclick="if(event.target===this) window.clientInvoices.closeInvoiceDrawer()">
    <div class="client-portal-drawer-panel" onclick="event.stopPropagation()">
        <div class="client-portal-drawer-header">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <div class="client-portal-drawer-badge" id="cidInvoiceStatusBadge">Pending</div>
                </div>
                <h3 class="client-portal-drawer-title" id="cidInvoiceTitle">Invoice Details</h3>
            </div>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientInvoices.closeInvoiceDrawer()" aria-label="Close drawer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        
        <!-- Drawer Tab Switcher -->
        <div class="client-portal-drawer-tabs" role="tablist" aria-label="Invoice details navigation">
            <button type="button" class="client-portal-drawer-tab active" role="tab" aria-selected="true" data-tab="overview" onclick="window.clientInvoices.switchInvoiceDrawerTab('overview')">Overview &amp; Billing</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="items" onclick="window.clientInvoices.switchInvoiceDrawerTab('items')">Line Items &amp; Pricing</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="payments" onclick="window.clientInvoices.switchInvoiceDrawerTab('payments')">Payment Info &amp; History</button>
            <button type="button" class="client-portal-drawer-tab" role="tab" aria-selected="false" data-tab="document" onclick="window.clientInvoices.switchInvoiceDrawerTab('document')">Document &amp; History</button>
        </div>

        <!-- Drawer Scrollable Body -->
        <div class="client-portal-drawer-body" id="clientInvoiceDrawerBody" tabindex="0">
            <!-- Dynamically injected tab panels by assets/js/client-invoices.js -->
        </div>

        <div class="client-portal-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientInvoices.closeInvoiceDrawer()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Invoice Query')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Contact Billing Support
            </button>
        </div>
    </div>
</div>

<script>
window.clientPortalCsrf = <?php echo json_encode(client_csrf_token()); ?>;
window.INITIAL_INVOICE_KPIS = <?php echo json_encode($kpis); ?>;
window.INITIAL_INVOICES = <?php echo json_encode($initialInvoices); ?>;
window.CURRENCY_SYMBOL = <?php echo json_encode($currencySymbol); ?>;
</script>
<script src="assets/js/client-invoices.js"></script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>

