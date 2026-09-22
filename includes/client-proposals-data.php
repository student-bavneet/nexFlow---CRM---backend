<?php
/**
 * NexFlow CRM — Client Portal Proposals Data Loader
 * Server-side loader providing authoritative proposal data and KPIs
 * strictly isolated to the authenticated organization and company.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Dynamic status evaluator: Computes whether an active proposal has passed expiry.
 */
function client_compute_proposal_display_status(array $proposal): string
{
    $st = $proposal['status'] ?? 'Draft';
    if (!in_array($st, ['Accepted', 'Declined', 'Draft'], true)) {
        if (!empty($proposal['expiry_date'])) {
            $today = date('Y-m-d');
            if ($today > $proposal['expiry_date']) {
                return 'Expired';
            }
        }
    }
    return $st;
}

/**
 * Extract currency symbol safely from string e.g. "USD ($)" -> "$", "INR (₹)" -> "₹"
 */
function client_extract_currency_symbol(string $currency): string
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
 * Load server-side KPIs and proposals list for authenticated client company.
 */
function client_get_proposals_data(PDO $pdo, int $orgId, int $companyId): array
{
    // 1. Resolve Organization Currency
    $stmtOrg = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmtOrg->execute([$orgId]);
    $orgCurrency = (string)($stmtOrg->fetchColumn() ?: 'USD ($)');
    $defaultSymbol = client_extract_currency_symbol($orgCurrency);

    // 2. Real SQL KPI Aggregations (Strictly excluding internal drafts)
    $stmtKpi = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE 
                WHEN status IN ('Sent', 'Viewed', 'Changes Requested') 
                 AND (expiry_date >= CURDATE() OR expiry_date IS NULL) 
                THEN 1 ELSE 0 
            END), 0) AS count_pending,
            COALESCE(SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END), 0) AS count_accepted,
            COALESCE(SUM(CASE 
                WHEN status IN ('Declined', 'Expired') 
                  OR (status NOT IN ('Accepted', 'Declined', 'Draft') AND expiry_date < CURDATE()) 
                THEN 1 ELSE 0 
            END), 0) AS count_declined_expired
        FROM proposals
        WHERE organization_id = ? 
          AND company_id = ?
          AND status != 'Draft'
    ");
    $stmtKpi->execute([$orgId, $companyId]);
    $kpiRow = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

    $kpis = [
        'total'     => (int)($kpiRow['total_count'] ?? 0),
        'pending'   => (int)($kpiRow['count_pending'] ?? 0),
        'accepted'  => (int)($kpiRow['count_accepted'] ?? 0),
        'declined'  => (int)($kpiRow['count_declined_expired'] ?? 0),
    ];

    // 3. Client-Visible Proposals List
    $stmtList = $pdo->prepare("
        SELECT 
            p.id,
            p.organization_id,
            p.proposal_number,
            p.title,
            p.company_id,
            COALESCE(p.company_name, '') AS company_name,
            p.contact_id,
            COALESCE(p.contact_name, '') AS contact_name,
            p.deal_id,
            COALESCE(p.deal_name, '') AS deal_name,
            p.project_id,
            COALESCE(p.project_name, '') AS project_name,
            p.prepared_by,
            COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email, 'Account Lead') AS prepared_by_name,
            u.email AS prepared_by_email,
            p.issue_date,
            p.expiry_date,
            p.scope,
            p.subtotal,
            p.discount,
            p.tax_rate,
            p.tax,
            p.total,
            COALESCE(p.currency, ?) AS currency,
            p.payment_terms,
            p.terms,
            p.status,
            p.change_request_type,
            p.change_request_message,
            p.change_requested_by,
            p.change_requested_at,
            p.created_at,
            p.updated_at
        FROM proposals p
        LEFT JOIN users u 
            ON u.id = p.prepared_by 
           AND u.organization_id = ?
        WHERE p.organization_id = ?
          AND p.company_id = ?
          AND p.status != 'Draft'
        ORDER BY p.issue_date DESC, p.id DESC
    ");
    $stmtList->execute([$orgCurrency, $orgId, $orgId, $companyId]);
    $rawProposals = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $proposals = [];
    foreach ($rawProposals as $row) {
        $curr = $row['currency'] ?: $orgCurrency;
        $sym = client_extract_currency_symbol($curr);
        $displayStatus = client_compute_proposal_display_status($row);
        $tot = (float)($row['total'] ?? 0.00);

        $row['display_status']  = $displayStatus;
        $row['currency_symbol'] = $sym;
        $row['formatted_total'] = $sym . number_format($tot, 2);
        $row['is_actionable']   = in_array($displayStatus, ['Sent', 'Viewed', 'Changes Requested'], true);
        $proposals[] = $row;
    }

    return [
        'kpis'           => $kpis,
        'proposals'      => $proposals,
        'currency'       => $orgCurrency,
        'currencySymbol' => $defaultSymbol,
    ];
}