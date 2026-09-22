<?php
/**
 * Backend API Endpoint to Save Member Permissions
 */
header('Content-Type: application/json');
require_once __DIR__ . '/includes/permissions.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$user_id = trim($data['user_id']);
$full_access = !empty($data['full_access']);
$permissions = is_array($data['permissions']) ? $data['permissions'] : [];

save_user_permissions($user_id, $permissions, $full_access);

echo json_encode([
    'success' => true,
    'message' => 'Permissions saved successfully for member ' . $user_id,
    'user_id' => $user_id
]);
exit();
