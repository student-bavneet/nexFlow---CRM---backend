<?php
/**
 * NexFlow CRM — Client Portal Contracts Data Loader
 * Server-side loader providing authoritative contract data and KPIs
 * strictly isolated to the authenticated organization and company.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Dynamic status evaluator for client contracts.
 */
function client_compute_contract_display_status(array $contract): string
{
    $st = $contract['status'] ?? 'Draft';
    if (in_array($st, ['Draft', 'Declined', 'Terminated'], true)) {
        return $st;
    }
    if (!empty($contract['end_date'])) {
        $today = date('Y-m-d');
        if ($today > $contract['end_date']) {
            return 'Expired';
        }
        // If within 30 days and Active / Signed
        $end = new DateTime($contract['end_date']);
        $diff = (int)(new DateTime('today'))->diff($end)->format('%r%a');
        if ($diff >= 0 && $diff <= 30 && in_array($st, ['Active', 'Signed'], true)) {
            return 'Expiring Soon';
        }
    }
    if ((int)($contract['is_client_signed'] ?? 0) === 0 && in_array($st, ['Sent', 'Viewed', 'Pending Signature', 'Changes Requested'], true)) {
        if ($st === 'Changes Requested') {
            return 'Changes Requested';
        }
        return 'Pending Signature';
    }
    return $st;
}

/**
 * Extract currency symbol safely from string e.g. "USD ($)" -> "$", "INR (₹)" -> "₹"
 */
function client_extract_contract_currency_symbol(string $currency): string
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
 * Load server-side KPIs and contracts list for authenticated client company.
 */
function client_get_contracts_data(PDO $pdo, int $orgId, int $companyId): array
{
    // 1. Resolve Organization Currency
    $stmtOrg = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmtOrg->execute([$orgId]);
    $orgCurrency = (string)($stmtOrg->fetchColumn() ?: 'USD ($)');
    $defaultSymbol = client_extract_contract_currency_symbol($orgCurrency);

    // 2. Real SQL KPI Aggregations (Strictly excluding internal drafts)
    $stmtKpi = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE 
                WHEN status IN ('Active', 'Signed') 
                 AND (end_date >= CURDATE() OR end_date IS NULL) 
                THEN 1 ELSE 0 
            END), 0) AS count_active,
            COALESCE(SUM(CASE 
                WHEN is_client_signed = 0 
                 AND status IN ('Sent', 'Viewed', 'Pending Signature', 'Changes Requested') 
                 AND (end_date >= CURDATE() OR end_date IS NULL) 
                THEN 1 ELSE 0 
            END), 0) AS count_pending,
            COALESCE(SUM(CASE 
                WHEN status = 'Expired' 
                  OR (end_date < CURDATE())
                  OR (end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status IN ('Active', 'Signed'))
                THEN 1 ELSE 0 
            END), 0) AS count_expiring
        FROM contracts
        WHERE organization_id = ? 
          AND company_id = ?
          AND status != 'Draft'
    ");
    $stmtKpi->execute([$orgId, $companyId]);
    $kpiRow = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

    $kpis = [
        'total'    => (int)($kpiRow['total_count'] ?? 0),
        'active'   => (int)($kpiRow['count_active'] ?? 0),
        'pending'  => (int)($kpiRow['count_pending'] ?? 0),
        'expiring' => (int)($kpiRow['count_expiring'] ?? 0),
    ];

    // 3. Client-Visible Contracts List
    $stmtList = $pdo->prepare("
        SELECT 
            c.id,
            c.organization_id,
            c.contract_number,
            c.title,
            c.reference_number,
            c.company_id,
            COALESCE(comp.name, c.company_name) AS company_name,
            c.contact_id,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, ''))), ''), c.contact_name) AS contact_name,
            COALESCE(cont.email, c.client_email) AS client_email,
            c.type,
            c.value,
            COALESCE(c.currency, ?) AS currency,
            c.start_date,
            c.end_date,
            c.status,
            c.signature_status,
            c.is_client_signed,
            c.signed_date,
            c.owner_id,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email, 'Account Lead') AS owner_name,
            u.email AS owner_email,
            c.renewal_type,
            c.notice_days,
            c.next_renewal_date,
            c.overview,
            c.terms,
            c.payment_terms,
            c.change_request_text,
            c.change_request_contact,
            c.change_request_date,
            c.created_at,
            c.updated_at
        FROM contracts c
        LEFT JOIN companies comp ON comp.id = c.company_id AND comp.organization_id = ?
        LEFT JOIN contacts cont ON cont.id = c.contact_id AND cont.organization_id = ?
        LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = ?
        WHERE c.organization_id = ?
          AND c.company_id = ?
          AND c.status != 'Draft'
        ORDER BY c.start_date DESC, c.id DESC
    ");
    $stmtList->execute([$orgCurrency, $orgId, $orgId, $orgId, $orgId, $companyId]);
    $rawContracts = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $contracts = [];
    $today = new DateTime('today');

    foreach ($rawContracts as $row) {
        $curr = $row['currency'] ?: $orgCurrency;
        $sym = client_extract_contract_currency_symbol($curr);
        $displayStatus = client_compute_contract_display_status($row);
        $val = (float)($row['value'] ?? 0.00);

        $daysRemaining = null;
        if (!empty($row['end_date'])) {
            $end = new DateTime($row['end_date']);
            $daysRemaining = (int)$today->diff($end)->format('%r%a');
        }

        $isPendingSig = ((int)$row['is_client_signed'] === 0) && in_array($displayStatus, ['Sent', 'Viewed', 'Pending Signature', 'Changes Requested'], true);

        $row['display_status']       = $displayStatus;
        $row['currency_symbol']      = $sym;
        $row['formatted_value']      = $sym . number_format($val, 2);
        $row['days_remaining']       = $daysRemaining;
        $row['is_actionable']        = $isPendingSig;
        $row['start_date_formatted'] = !empty($row['start_date']) ? date('M d, Y', strtotime($row['start_date'])) : '—';
        $row['end_date_formatted']   = !empty($row['end_date']) ? date('M d, Y', strtotime($row['end_date'])) : '—';

        $contracts[] = $row;
    }

    return [
        'kpis'           => $kpis,
        'contracts'      => $contracts,
        'currency'       => $orgCurrency,
        'currencySymbol' => $defaultSymbol,
    ];
}