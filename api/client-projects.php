<?php
/**
 * NexFlow CRM — Client Portal Projects API
 * Read-only, multi-tenant authenticated endpoint for Client Projects data.
 * Supports action=list and action=detail with strict multi-company contact isolation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-projects-data.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Enforce GET method only (strictly read-only in Phase 3)
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
// User-supplied query parameters (e.g. company_id, organization_id, contact_id, deal_id) are strictly ignored.
$orgId       = (int)$client['organization_id'];
$companyId   = (int)$client['company_id'];
$contactId   = (int)$client['contact_id'];
$companyName = (string)$client['company_name'];

$action = strtolower(trim((string)($_GET['action'] ?? 'list')));

if ($action === 'list') {
    $kpis = client_get_projects_kpis($pdo, $orgId, $companyName, $contactId);
    $projects = client_get_projects_list($pdo, $orgId, $companyName, $contactId);
    $currency = client_get_projects_currency($pdo, $orgId);

    client_json_response(true, 'Projects retrieved successfully.', [
        'kpis'     => $kpis,
        'projects' => $projects,
        'currency' => $currency,
    ], 200);
} elseif ($action === 'detail') {
    $projectId = (int)($_GET['id'] ?? 0);
    if ($projectId <= 0) {
        client_json_response(false, 'Invalid project ID specified.', [], 400);
    }

    $detail = client_get_project_detail($pdo, $orgId, $companyName, $contactId, $projectId);
    if (!$detail) {
        // Return 404/403 for unauthorized project ID or non-existent project
        client_json_response(false, 'Project not found or access denied.', [], 404);
    }

    client_json_response(true, 'Project details retrieved successfully.', $detail, 200);
} else {
    client_json_response(false, 'Invalid action specified.', [], 400);
}