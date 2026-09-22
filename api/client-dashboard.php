<?php
/**
 * NexFlow CRM — Client Portal Dashboard API
 * Read-only, multi-tenant authenticated endpoint for Client Dashboard data.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-dashboard-data.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Enforce GET method only (read-only)
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
// User-supplied query parameters are strictly ignored.
$orgId       = (int)$client['organization_id'];
$companyId   = (int)$client['company_id'];
$contactId   = (int)$client['contact_id'];
$companyName = (string)$client['company_name'];

// 4. Fetch safe, multi-company isolated dashboard data
$dashboardData = client_get_dashboard_data($pdo, $orgId, $companyId, $contactId, $companyName);

client_json_response(true, 'Dashboard data retrieved successfully.', $dashboardData);
