<?php
/**
 * NexFlow CRM — Client Portal Tasks API Endpoint
 * Handles strictly read-only task summary, list, and details retrieval
 * with multi-tenant isolation and client_visible enforcement.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-tasks-data.php';

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

// 2. Database Connection
$pdo = nexflow_db();

// 3. Action Dispatcher
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));

// 4. Reject Any Mutating Action (Strictly Read-Only)
$disallowedActions = ['toggle_complete', 'complete', 'update', 'delete', 'create', 'change_status', 'reopen'];
if (in_array(strtolower($action), $disallowedActions, true) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    client_json_response(false, 'Forbidden: Client Portal tasks are strictly read-only. Statuses are managed by the NexFlow team.', [], 403);
}

// =========================================================================
// ACTION: summary (Group Counts)
// =========================================================================
if ($action === 'summary') {
    $data = client_get_tasks_data($pdo, $orgId, $companyId);
    client_json_response(true, 'Summary retrieved successfully.', [
        'kpis' => $data['kpis']
    ]);
}

// =========================================================================
// ACTION: list (Filtered & Searched Tasks)
// =========================================================================
if ($action === 'list') {
    $search      = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
    $groupFilter = trim((string)($_GET['group'] ?? $_GET['filter'] ?? 'All'));

    $data = client_get_tasks_data($pdo, $orgId, $companyId);
    $items = $data['tasks'];

    // Apply Client Search and Filter
    if ($search !== '' || ($groupFilter !== 'All' && $groupFilter !== '')) {
        $q = mb_strtolower($search);
        $items = array_values(array_filter($items, function ($t) use ($q, $groupFilter) {
            // Search matching
            $matchSearch = true;
            if ($q !== '') {
                $matchSearch = (
                    mb_stripos((string)$t['title'], $q) !== false ||
                    mb_stripos((string)$t['id'], $q) !== false ||
                    mb_stripos((string)($t['association_name'] ?? ''), $q) !== false ||
                    mb_stripos((string)($t['task_type'] ?? ''), $q) !== false
                );
            }

            // Group matching
            $matchGroup = true;
            if ($groupFilter === 'Completed') {
                $matchGroup = (bool)$t['completed'];
            } elseif ($groupFilter === 'Overdue') {
                $matchGroup = ($t['group'] === 'Overdue' && !$t['completed']);
            } elseif ($groupFilter === 'Today') {
                $matchGroup = ($t['group'] === 'Today' && !$t['completed']);
            } elseif ($groupFilter === 'Upcoming') {
                $matchGroup = ($t['group'] === 'Upcoming' && !$t['completed']);
            }

            return $matchSearch && $matchGroup;
        }));
    }

    client_json_response(true, 'Tasks retrieved successfully.', [
        'tasks' => $items,
        'total' => count($items),
        'kpis'  => $data['kpis'],
    ]);
}

// =========================================================================
// ACTION: get (Single Task Details)
// =========================================================================
if ($action === 'get') {
    $rawId = trim((string)($_GET['id'] ?? ''));
    $id = (int)preg_replace('/[^0-9]/', '', $rawId);

    if ($id <= 0) {
        client_json_response(false, 'Invalid task ID.', [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.organization_id,
            t.company_id,
            t.title,
            t.task_type,
            t.description,
            t.status,
            t.priority,
            t.due_date,
            t.client_visible,
            t.related_type,
            t.related_id,
            COALESCE(d.name, d_rel.name) AS deal_name,
            pr.name AS project_name
        FROM tasks t
        LEFT JOIN deals d ON d.id = t.deal_id
        LEFT JOIN deals d_rel ON (t.related_type = 'deals' AND d_rel.id = t.related_id)
        LEFT JOIN projects pr ON (t.related_type = 'projects' AND pr.id = t.related_id)
        WHERE t.id = :id
          AND t.organization_id = :org_id
          AND t.company_id = :company_id
          AND t.company_id IS NOT NULL
          AND t.client_visible = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':id'         => $id,
        ':org_id'     => $orgId,
        ':company_id' => $companyId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        client_json_response(false, 'Task not found or access denied.', [], 404);
    }

    $task = client_format_task_item($row);
    client_json_response(true, 'Task retrieved successfully.', [
        'task' => $task
    ]);
}

// Fallback for unknown actions
client_json_response(false, 'Invalid action requested.', [], 400);