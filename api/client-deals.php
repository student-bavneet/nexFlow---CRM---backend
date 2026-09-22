<?php
/**
 * NexFlow CRM — Client Portal Deals API
 * Read-only, multi-tenant authenticated endpoint for Client Deals data.
 * Supports action=list and action=detail with strict company-authoritative isolation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-deals-data.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Enforce GET method only (strictly read-only in Phase 4)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    client_json_response(false, 'Method Not Allowed. Only GET is supported.', [], 405);
}

// 2. Enforce Client Portal Authentication
$pdo = nexflow_db();
$client = client_current_user($pdo);

if (!$client) {
    client_json_response(false, 'Unauthorized. Please log in to Client Portal.', [], 401);
}

// 3. Derive tenant context strictly from authenticated session
// User-supplied query parameters (e.g. company_id, organization_id, contact_id) are strictly ignored.
$orgId       = (int)$client['organization_id'];
$companyId   = (int)$client['company_id'];
$contactId   = (int)$client['contact_id'];
$companyName = (string)$client['company_name'];

$action = strtolower(trim((string)($_GET['action'] ?? 'list')));

if ($action === 'list') {
    $deals = client_get_deals_list($pdo, $orgId, $companyName);
    $currency = client_get_deals_currency($pdo, $orgId);

    client_json_response(true, 'Deals retrieved successfully.', [
        'deals'    => $deals,
        'currency' => $currency,
    ], 200);
} elseif ($action === 'detail') {
    $dealId = (int)($_GET['id'] ?? 0);
    if ($dealId <= 0) {
        client_json_response(false, 'Invalid deal ID specified.', [], 400);
    }

    $detail = client_get_deal_detail($pdo, $orgId, $companyName, $dealId);
    if (!$detail) {
        // Return 404 for unauthorized deal ID or non-existent deal
        client_json_response(false, 'Deal not found or access denied.', [], 404);
    }

    client_json_response(true, 'Deal details retrieved successfully.', $detail, 200);
} else {
    client_json_response(false, 'Invalid action specified.', [], 400);
}