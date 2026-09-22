<?php
/**
 * NexFlow CRM - Dashboard API Endpoint
 * Handles actions:
 *   - ?action=bootstrap (returns full dashboard state: KPIs, recent leads, pipeline, activities, analytics, currency)
 *   - ?action=analytics&period=monthly|quarterly (returns sales analytics dataset)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/dashboard-data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function dashboard_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    $payload = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authentication check
$currentUser = nexflow_current_user();
if (!$currentUser || empty($currentUser['organization_id'])) {
    dashboard_json(false, 'Unauthorized. Please sign in.', [], 401);
}

// 2. Derive organization_id strictly from session
$orgId  = (int)$currentUser['organization_id'];
$userId = (int)$currentUser['id'];

// 3. Permission check
if (!canView('dashboard', $userId)) {
    dashboard_json(false, 'Permission denied. You cannot view the Dashboard.', [], 403);
}

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    dashboard_json(false, 'Database connection error.', [], 500);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'bootstrap');

if ($action === 'bootstrap') {
    try {
        $bundle = get_dashboard_bootstrap_data($pdo, $orgId);
        dashboard_json(true, '', $bundle, 200);
    } catch (Throwable $e) {
        error_log('Dashboard bootstrap error: ' . $e->getMessage());
        dashboard_json(false, 'Failed to retrieve dashboard data.', [], 500);
    }
} elseif ($action === 'analytics') {
    try {
        $period   = strtolower(trim((string)($_GET['period'] ?? ($_POST['period'] ?? 'monthly'))));
        $currency = get_dashboard_currency($pdo, $orgId);
        $analytics = get_dashboard_analytics($pdo, $orgId, $currency);

        dashboard_json(true, '', [
            'period'          => $period,
            'currency'        => $currency,
            'currency_symbol' => $currency['symbol'],
            'analytics'       => $analytics,
            'data'            => $analytics[$period] ?? $analytics['monthly']
        ], 200);
    } catch (Throwable $e) {
        error_log('Dashboard analytics error: ' . $e->getMessage());
        dashboard_json(false, 'Failed to retrieve analytics data.', [], 500);
    }
} else {
    dashboard_json(false, 'Invalid action parameter.', [], 400);
}
