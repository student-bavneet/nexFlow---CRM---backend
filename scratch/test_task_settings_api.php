<?php
/**
 * NexFlow CRM - Integration Test Suite for Task Settings API
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$_SESSION['user_id'] = 1;
$_SESSION['organization_id'] = 1;
$_SESSION['role'] = 'super_admin';

require_once __DIR__ . '/../config/database.php';

echo "==================================================\n";
echo "NEXFLOW CRM - TASK SETTINGS API TEST SUITE\n";
echo "==================================================\n\n";

$pdo = nexflow_db();

// 1. Authenticate user in session
$stmt = $pdo->prepare("SELECT id, organization_id, email, name, role FROM users WHERE id = 1 AND status = 'active'");
$stmt->execute();
$adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminUser) {
    echo "❌ ERROR: Super Admin user ID 1 not found in database.\n";
    exit(1);
}

echo "✔ Authenticated Session: {$adminUser['name']} ({$adminUser['email']}), Org ID: {$adminUser['organization_id']}\n\n";

// Helper function to test API endpoints via ob_start buffer
function call_settings_api(string $method, array $params): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_REQUEST = $params;
    $_GET = ($method === 'GET') ? $params : [];
    $_POST = ($method === 'POST') ? $params : [];

    ob_start();
    try {
        include __DIR__ . '/../admin/api/settings.php';
    } catch (Throwable $e) {
        // Exit handled gracefully by catching thrown termination
    }
    $output = ob_get_clean();

    $data = json_decode($output, true);
    return [
        'code' => http_response_code() ?: 200,
        'body' => $data ?: $output
    ];
}

// -------------------------------------------------------------
// TEST 1: GET action=task_settings
// -------------------------------------------------------------
echo "[TEST 1] GET action=task_settings\n";
$res1 = call_settings_api('GET', ['action' => 'task_settings']);
echo "HTTP Code: {$res1['code']}\n";
echo "Response: " . json_encode($res1['body'], JSON_PRETTY_PRINT) . "\n";

if ($res1['code'] === 200 && !empty($res1['body']['success'])) {
    $data = $res1['body']['data'];
    if (isset($data['task_default_status'], $data['task_default_priority'], $data['task_default_reminder'], $data['task_default_view'], $data['task_show_completed'])) {
        echo "✔ PASS: Task settings fetched successfully via GET API.\n\n";
    } else {
        echo "❌ FAIL: Response structure incomplete.\n\n";
        exit(1);
    }
} else {
    echo "❌ FAIL: GET task_settings failed.\n\n";
    exit(1);
}

// -------------------------------------------------------------
// TEST 2: POST action=task_settings (Update values)
// -------------------------------------------------------------
echo "[TEST 2] POST action=task_settings (Updating Task Settings)\n";
$postPayload = [
    'action'               => 'task_settings',
    'task_default_status'   => 'In Progress',
    'task_default_priority' => 'High',
    'task_default_reminder' => '30 minutes before',
    'task_default_view'     => 'board',
    'task_show_completed'   => '0'
];

$res2 = call_settings_api('POST', $postPayload);
echo "HTTP Code: {$res2['code']}\n";
echo "Response: " . json_encode($res2['body'], JSON_PRETTY_PRINT) . "\n";

if ($res2['code'] === 200 && !empty($res2['body']['success'])) {
    $data = $res2['body']['data'];
    if ($data['task_default_status'] === 'In Progress' &&
        $data['task_default_priority'] === 'High' &&
        $data['task_default_reminder'] === '30 minutes before' &&
        $data['task_default_view'] === 'board' &&
        (int)$data['task_show_completed'] === 0) {
        echo "✔ PASS: Task settings updated successfully via POST API.\n\n";
    } else {
        echo "❌ FAIL: Updated task settings mismatch.\n\n";
        exit(1);
    }
} else {
    echo "❌ FAIL: POST task_settings failed.\n\n";
    exit(1);
}

// -------------------------------------------------------------
// TEST 3: Validation Error Check (Invalid status)
// -------------------------------------------------------------
echo "[TEST 3] Validation Check (Invalid Status)\n";
$invalidPayload = [
    'action'               => 'task_settings',
    'task_default_status'   => 'INVALID_STATUS',
    'task_default_priority' => 'High',
    'task_default_reminder' => '30 minutes before',
    'task_default_view'     => 'board',
    'task_show_completed'   => '1'
];

$res3 = call_settings_api('POST', $invalidPayload);
echo "HTTP Code: {$res3['code']} (Expected 422)\n";
echo "Response: " . json_encode($res3['body'], JSON_PRETTY_PRINT) . "\n";

if ($res3['code'] === 422 && empty($res3['body']['success'])) {
    echo "✔ PASS: Invalid status rejected with HTTP 422.\n\n";
} else {
    echo "❌ FAIL: Validation failed to reject invalid status.\n\n";
    exit(1);
}

// -------------------------------------------------------------
// TEST 4: Restore Application Defaults
// -------------------------------------------------------------
echo "[TEST 4] Restore Task Settings to Application Defaults\n";
$restorePayload = [
    'action'               => 'task_settings',
    'task_default_status'   => 'Pending',
    'task_default_priority' => 'Medium',
    'task_default_reminder' => '15 minutes before',
    'task_default_view'     => 'list',
    'task_show_completed'   => '1'
];

$res4 = call_settings_api('POST', $restorePayload);
echo "HTTP Code: {$res4['code']}\n";
if ($res4['code'] === 200 && !empty($res4['body']['success'])) {
    echo "✔ PASS: Task settings restored to application defaults.\n\n";
} else {
    echo "❌ FAIL: Failed to restore default task settings.\n\n";
    exit(1);
}

echo "==================================================\n";
echo "ALL TASK SETTINGS API TESTS PASSED SUCCESSFULLY! ✅\n";
echo "==================================================\n";
