<?php
/**
 * NexFlow CRM — Client Portal Calendar API Endpoint
 * Handles strictly read-only calendar meeting listing, summary, and details retrieval
 * with multi-tenant isolation, company isolation, and private event filtering.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-calendar-data.php';

// 1. Session Authentication Guard (401 if unauthenticated)
$client = client_current_user();
if (!$client) {
    client_json_response(false, 'Unauthorized access. Please log in.', [], 401);
}

$orgId     = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];

if ($orgId <= 0 || $companyId <= 0) {
    client_json_response(false, 'Invalid client session context.', [], 400);
}

// 2. Reject Any Mutating Method (Strictly Read-Only)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    client_json_response(false, 'Method Not Allowed. Client Portal Calendar is strictly read-only.', [], 405);
}

// 3. Database Connection
$pdo = nexflow_db();

// 4. Action Dispatcher
$action = trim((string)($_GET['action'] ?? 'list'));

// =========================================================================
// ACTION: summary (KPI Counts)
// =========================================================================
if ($action === 'summary') {
    $data = client_get_calendar_data($pdo, $orgId, $companyId);
    client_json_response(true, 'Calendar summary retrieved successfully.', [
        'kpis' => $data['kpis']
    ]);
}

// =========================================================================
// ACTION: list / events (Filtered by Month or Date Bounds)
// =========================================================================
if ($action === 'list' || $action === 'events') {
    $monthParam = trim((string)($_GET['month'] ?? ''));
    $startBound = trim((string)($_GET['start'] ?? ''));
    $endBound   = trim((string)($_GET['end'] ?? ''));

    if (!empty($monthParam) && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
        $startBound = "{$monthParam}-01";
        $endBound   = date('Y-m-t', strtotime($startBound));
    }

    $data = client_get_calendar_data($pdo, $orgId, $companyId, $startBound ?: null, $endBound ?: null);

    client_json_response(true, 'Meetings retrieved successfully.', [
        'meetings' => $data['meetings'],
        'total'    => count($data['meetings']),
        'kpis'     => $data['kpis']
    ]);
}

// =========================================================================
// ACTION: get (Single Meeting Details)
// =========================================================================
if ($action === 'get') {
    $rawId = trim((string)($_GET['id'] ?? ''));
    $id = (int)preg_replace('/[^0-9]/', '', $rawId);

    if ($id <= 0) {
        client_json_response(false, 'Invalid meeting event ID.', [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.organization_id,
            c.company_id,
            c.contact_id,
            c.deal_id,
            c.title,
            c.description,
            c.event_type,
            c.start_time,
            c.end_time,
            c.status,
            c.location,
            c.created_at,
            c.updated_at,
            u.name AS host_name,
            d.name AS deal_name
        FROM calendar_events c
        LEFT JOIN users u ON u.id = c.user_id
        LEFT JOIN deals d ON d.id = c.deal_id
        WHERE c.id = :id
          AND c.organization_id = :org_id
          AND c.company_id = :company_id
          AND c.company_id IS NOT NULL
          AND c.client_visible = 1
          AND LOWER(c.event_type) NOT IN ('team event', 'task deadline')
        LIMIT 1
    ");

    $stmt->execute([
        ':id'         => $id,
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        client_json_response(false, 'Meeting not found or access denied.', [], 404);
    }

    $meeting = client_format_calendar_item($row);
    client_json_response(true, 'Meeting details retrieved successfully.', [
        'meeting' => $meeting
    ]);
}

// Fallback for unknown actions
client_json_response(false, 'Invalid action requested.', [], 400);