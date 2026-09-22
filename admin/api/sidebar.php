<?php
/**
 * NexFlow CRM - Sidebar Badge Counts API
 * Returns real, database-driven, organization-scoped counts for sidebar navigation badges.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function sidebar_json(bool $success, string $message = '', array $counts = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'counts'  => $counts
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authentication check
$currentUser = nexflow_current_user();
if (!$currentUser || empty($currentUser['organization_id'])) {
    sidebar_json(false, 'Unauthorized access. Please sign in.', [], 401);
}

// 2. Derive organization_id STRICTLY from authenticated session/user
$orgId = (int)$currentUser['organization_id'];
$userId = (int)$currentUser['id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'counts';

if ($action !== 'counts') {
    sidebar_json(false, 'Invalid action parameter.', [], 400);
}

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    sidebar_json(false, 'Database connection error.', [], 500);
}

try {
    $counts = [];

    // Leads: Active non-archived leads for this organization
    if (canView('leads', $userId)) {
        $stmtLeads = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE organization_id = :org_id AND is_archived = 0");
        $stmtLeads->execute([':org_id' => $orgId]);
        $counts['leads'] = (int)$stmtLeads->fetchColumn();
    } else {
        $counts['leads'] = 0;
    }

    // Tasks: Incomplete / pending tasks for this organization
    if (canView('tasks', $userId)) {
        $stmtTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = :org_id AND status != 'completed'");
        $stmtTasks->execute([':org_id' => $orgId]);
        $counts['tasks'] = (int)$stmtTasks->fetchColumn();
    } else {
        $counts['tasks'] = 0;
    }

    // Inbox: Unread, non-archived, non-snoozed conversations for this organization
    if (canView('inbox', $userId)) {
        $stmtInbox = $pdo->prepare("SELECT COUNT(*) FROM inbox_conversations WHERE organization_id = :org_id AND is_unread = 1 AND status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW())");
        $stmtInbox->execute([':org_id' => $orgId]);
        $counts['inbox'] = (int)$stmtInbox->fetchColumn();
    } else {
        $counts['inbox'] = 0;
    }

    sidebar_json(true, 'Sidebar counts loaded successfully.', $counts, 200);

} catch (Throwable $e) {
    error_log('Sidebar counts API error: ' . $e->getMessage());
    sidebar_json(false, 'Failed to fetch sidebar counts.', [], 500);
}