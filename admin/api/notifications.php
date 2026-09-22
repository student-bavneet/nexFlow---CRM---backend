<?php
/**
 * NexFlow CRM - User Notification Preferences API Endpoint
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function notif_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    notif_json(false, 'Unauthorized. Please sign in.', [], 401);
}

$userId = (int) $_SESSION['user_id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    notif_json(false, 'Database connection failed.', [], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $notifs = nexflow_user_notifications($userId);
    notif_json(true, 'Notification preferences retrieved successfully.', $notifs);
}

if ($method === 'POST') {
    $fields = [
        'new_lead_assigned',
        'lead_status_changed',
        'follow_up_due',
        'deal_assigned',
        'task_due_soon',
        'missed_simulated_call'
    ];

    $dataToSave = [];
    foreach ($fields as $field) {
        if (!isset($_POST[$field])) {
            // Default to current setting or 1 if not passed
            $currentNotifs = nexflow_user_notifications($userId);
            $dataToSave[$field] = isset($currentNotifs[$field]) ? (int)$currentNotifs[$field] : 1;
            continue;
        }

        $val = $_POST[$field];
        if (is_bool($val)) {
            $dataToSave[$field] = $val ? 1 : 0;
        } elseif (is_numeric($val) && ((int)$val === 0 || (int)$val === 1)) {
            $dataToSave[$field] = (int)$val;
        } elseif (is_string($val) && ($val === 'true' || $val === 'false' || $val === '1' || $val === '0')) {
            $dataToSave[$field] = ($val === 'true' || $val === '1') ? 1 : 0;
        } else {
            notif_json(false, "Invalid boolean value for field '{$field}'.", [], 422);
        }
    }

    $sql = "INSERT INTO user_notification_preferences (
                user_id, new_lead_assigned, lead_status_changed, follow_up_due,
                deal_assigned, task_due_soon, missed_simulated_call, created_at, updated_at
            ) VALUES (
                :user_id, :new_lead_assigned, :lead_status_changed, :follow_up_due,
                :deal_assigned, :task_due_soon, :missed_simulated_call, NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                new_lead_assigned = VALUES(new_lead_assigned),
                lead_status_changed = VALUES(lead_status_changed),
                follow_up_due = VALUES(follow_up_due),
                deal_assigned = VALUES(deal_assigned),
                task_due_soon = VALUES(task_due_soon),
                missed_simulated_call = VALUES(missed_simulated_call),
                updated_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id'               => $userId,
        ':new_lead_assigned'     => $dataToSave['new_lead_assigned'],
        ':lead_status_changed'   => $dataToSave['lead_status_changed'],
        ':follow_up_due'         => $dataToSave['follow_up_due'],
        ':deal_assigned'         => $dataToSave['deal_assigned'],
        ':task_due_soon'         => $dataToSave['task_due_soon'],
        ':missed_simulated_call' => $dataToSave['missed_simulated_call']
    ]);

    $updatedNotifs = nexflow_user_notifications($userId);
    notif_json(true, 'Notification preferences saved successfully.', $updatedNotifs);
}

notif_json(false, 'Invalid request method.', [], 405);
