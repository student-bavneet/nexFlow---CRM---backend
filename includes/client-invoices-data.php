<?php
/**
 * NexFlow CRM — Client Portal Invoices Data Loader
 * Server-side loader providing authoritative invoice data and KPIs
 * strictly isolated to the authenticated organization and company.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Dynamic status evaluator for client invoices.
 * Evaluates dynamic 'Overdue' when balance remains and due date has passed.
 */
function client_compute_invoice_display_status(array $invoice): string
{
    $st = $invoice['status'] ?? 'Draft';
    if (in_array($st, ['Draft', 'Cancelled', 'Paid'], true)) {
        return $st;
    }

    $balanceDue = (float)($invoice['balance_due'] ?? 0);
    $dueDate = (string)($invoice['due_date'] ?? '');

    if ($balanceDue > 0 && !empty($dueDate) && $dueDate < date('Y-m-d')) {
        return 'Overdue';
    }

    return $st;
}

/**
 * Determine payment badge state for client UI.
 */
function client_compute_invoice_payment_state(array $invoice): string
{
    $st = $invoice['status'] ?? 'Draft';
    $balanceDue = (float)($invoice['balance_due'] ?? 0);
    $amountPaid = (float)($invoice['amount_paid'] ?? 0);
    $total = (float)($invoice['total'] ?? 0);
    $dueDate = (string)($invoice['due_date'] ?? '');

    if ($st === 'Paid' || ($total > 0 && $balanceDue <= 0)) {
        return 'Paid';
    }
    if ($balanceDue > 0 && !empty($dueDate) && $dueDate < date('Y-m-d') && $st !== 'Cancelled') {
        return 'Overdue';
    }
    if ($amountPaid > 0 && $balanceDue > 0) {
        return 'Partially Paid';
    }
    return 'Unpaid';
}

/**
 * Extract currency symbol safely from string e.g. "USD ($)" -> "$", "INR (₹)" -> "₹"
 */
function client_extract_invoice_currency_symbol(string $currency): string
{
    if (preg_match('/\(([^)]+)\)/', $currency, $m)) {
        return $m[1];
    }
    if (strpos($currency, 'INR') !== false || strpos($currency, '₹') !== false) {
        return '₹';
    }
    if (strpos($currency, 'EUR') !== false || strpos($currency, '€') !== false) {
        return '€';
    }
    if (strpos($currency, 'GBP') !== false || strpos($currency, '£') !== false) {
        return '£';
    }
    return '$';
}

/**
 * Load server-side KPIs and invoices list for authenticated client company.
 */
function client_get_invoices_data(PDO $pdo, int $orgId, int $companyId): array
{
    // 1. Resolve Organization Currency
    $stmtOrg = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmtOrg->execute([$orgId]);
    $orgCurrency = (string)($stmtOrg->fetchColumn() ?: 'USD ($)');
    $defaultSymbol = client_extract_invoice_currency_symbol($orgCurrency);

    // 2. Real SQL KPI Aggregations (Strictly excluding internal drafts)
    $stmtKpi = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN status NOT IN ('Draft', 'Cancelled', 'Paid') THEN balance_due ELSE 0 END), 0) AS total_outstanding,
            COALESCE(SUM(CASE WHEN status != 'Draft' THEN amount_paid ELSE 0 END), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN (status = 'Overdue' OR (due_date < CURDATE() AND balance_due > 0)) AND status NOT IN ('Draft', 'Cancelled', 'Paid') THEN balance_due ELSE 0 END), 0) AS total_overdue
        FROM invoices
        WHERE organization_id = ? 
          AND company_id = ? 
          AND status != 'Draft'
    ");
    $stmtKpi->execute([$orgId, $companyId]);
    $kpiRow = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

    $kpis = [
        'total'       => (int)($kpiRow['total_count'] ?? 0),
        'outstanding' => (float)($kpiRow['total_outstanding'] ?? 0.0),
        'paid'        => (float)($kpiRow['total_paid'] ?? 0.0),
        'overdue'     => (float)($kpiRow['total_overdue'] ?? 0.0),
    ];

    // 3. Client-Visible Invoices List (Strict company-level isolation, no internal_notes)
    $stmtList = $pdo->prepare("
        SELECT 
            i.id,
            i.organization_id,
            i.invoice_number,
            i.title,
            i.company_id,
            comp.name AS company_name,
            i.contact_id,
            CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, '')) AS contact_name,
            cont.email AS contact_email,
            i.project_id,
            p.name AS project_name,
            i.deal_id,
            d.name AS deal_title,
            i.contract_reference,
            i.issue_date,
            i.due_date,
            i.payment_terms,
            COALESCE(i.currency, ?) AS currency,
            i.subtotal,
            i.discount,
            i.tax,
            i.total,
            i.amount_paid,
            i.balance_due,
            i.status,
            i.customer_notes,
            i.created_at,
            i.updated_at
        FROM invoices i
        LEFT JOIN companies comp ON comp.id = i.company_id AND comp.organization_id = ?
        LEFT JOIN contacts cont ON cont.id = i.contact_id AND cont.organization_id = ?
        LEFT JOIN projects p ON p.id = i.project_id AND p.organization_id = ?
        LEFT JOIN deals d ON d.id = i.deal_id AND d.organization_id = ?
        WHERE i.organization_id = ? 
          AND i.company_id = ? 
          AND i.status != 'Draft'
        ORDER BY i.issue_date DESC, i.id DESC
    ");
    $stmtList->execute([$orgCurrency, $orgId, $orgId, $orgId, $orgId, $orgId, $companyId]);
    $rawInvoices = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $invoices = [];
    $today = new DateTime('today');

    foreach ($rawInvoices as $row) {
        $curr = $row['currency'] ?: $orgCurrency;
        $sym = client_extract_invoice_currency_symbol($curr);
        $displayStatus = client_compute_invoice_display_status($row);
        $paymentState = client_compute_invoice_payment_state($row);

        $tot = (float)($row['total'] ?? 0.0);
        $pd  = (float)($row['amount_paid'] ?? 0.0);
        $bal = (float)($row['balance_due'] ?? ($tot - $pd));

        $daysOverdue = 0;
        if (!empty($row['due_date'])) {
            $due = new DateTime($row['due_date']);
            if ($today > $due && $bal > 0) {
                $daysOverdue = (int)$due->diff($today)->format('%r%a');
            }
        }

        $invoices[] = [
            'id'                => (int)$row['id'],
            'number'            => (string)$row['invoice_number'],
            'title'             => $row['title'] ?: "Invoice {$row['invoice_number']}",
            'customer'          => $row['company_name'] ?: 'My Company',
            'billedTo'          => $row['company_name'] ?: 'My Company',
            'contact'           => trim((string)$row['contact_name']) ?: null,
            'contactName'       => trim((string)$row['contact_name']) ?: null,
            'contactEmail'      => $row['contact_email'] ?: null,
            'project'           => $row['project_name'] ?: null,
            'deal'              => $row['deal_title'] ?: null,
            'contract'          => $row['contract_reference'] ?: null,
            'issueDate'         => $row['issue_date'],
            'invoiceDate'       => $row['issue_date'],
            'dueDate'           => $row['due_date'],
            'paymentTerms'      => $row['payment_terms'],
            'currency'          => $curr,
            'currencySymbol'    => $sym,
            'status'            => $displayStatus,
            'db_status'         => $row['status'],
            'paymentStatus'     => $paymentState,
            'paymentState'      => $paymentState,
            'subtotal'          => (float)$row['subtotal'],
            'discount'          => (float)$row['discount'],
            'tax'               => (float)$row['tax'],
            'total'             => $tot,
            'amount'            => $tot,
            'paid'              => $pd,
            'amountPaid'        => $pd,
            'balance'           => $bal,
            'balanceDue'        => $bal,
            'formattedTotal'    => $sym . number_format($tot, 2),
            'formattedPaid'     => $sym . number_format($pd, 2),
            'formattedBalance'  => $sym . number_format($bal, 2),
            'daysOverdue'       => $daysOverdue,
            'customer_notes'    => $row['customer_notes'] ?: '',
            'created_at'        => $row['created_at'],
            'updated_at'        => $row['updated_at'],
        ];
    }

    return [
        'kpis'           => $kpis,
        'invoices'       => $invoices,
        'currency'       => $orgCurrency,
        'currencySymbol' => $defaultSymbol,
    ];
}