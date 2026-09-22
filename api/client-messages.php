<?php
/**
 * NexFlow CRM — Client Portal Messages API Endpoint
 * Provides company-isolated conversations, message threads, client replies,
 * and new conversation initialization strictly scoped to authenticated client session.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-messages-data.php';

// 1. Session Authentication Guard (HTTP 401 if unauthenticated)
$client = client_current_user();
if (!$client) {
    client_json_response(false, 'Unauthorized access. Please log in.', [], 401);
}

$orgId     = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];
$contactId = (int)$client['contact_id'];

if ($orgId <= 0 || $companyId <= 0 || $contactId <= 0) {
    client_json_response(false, 'Invalid client session context.', [], 400);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// 2. HTTP Method Gate (Only GET and POST are supported)
if (!in_array($method, ['GET', 'POST'], true)) {
    client_json_response(false, "Method Not Allowed. '{$method}' is not supported.", [], 405);
}

// 3. Database Connection
$pdo = nexflow_db();

// 4. Action Dispatcher
$action = trim((string)($_GET['action'] ?? ($_POST['action'] ?? 'list')));

// Read vs Write separation
$readActions   = ['list', 'thread'];
$writeActions  = ['send', 'new_conversation'];

if (in_array($action, $readActions, true)) {
    if ($method !== 'GET') {
        client_json_response(false, "Method Not Allowed. Action '{$action}' requires GET.", [], 405);
    }
} elseif (in_array($action, $writeActions, true)) {
    if ($method !== 'POST') {
        client_json_response(false, "Method Not Allowed. Action '{$action}' requires POST.", [], 405);
    }

    // CSRF Protection for mutating actions
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $rawInput = file_get_contents('php://input');
    $input = $_POST;

    if (!empty($rawInput) && (empty($input) || empty($token))) {
        $parsed = json_decode($rawInput, true);
        if (is_array($parsed)) {
            $input = array_merge($input, $parsed);
            if (empty($token) && !empty($parsed['csrf_token'])) {
                $token = (string)$parsed['csrf_token'];
            }
        }
    }

    if (empty($token) || !verify_client_csrf($token)) {
        client_json_response(false, 'Invalid or missing CSRF token. Please refresh the page and try again.', [], 403);
    }
} else {
    client_json_response(false, "Unsupported action '{$action}'.", [], 400);
}

// =========================================================================
// ACTION: list (Conversations for Authenticated Company)
// =========================================================================
if ($action === 'list') {
    $data = client_get_conversations_data($pdo, $orgId, $companyId);

    client_json_response(true, 'Conversations retrieved successfully.', [
        'conversations' => $data['conversations'],
        'total'         => $data['total'],
        'unread_count'  => $data['unread_count']
    ]);
}

// =========================================================================
// ACTION: thread (Messages for a Specific Conversation)
// =========================================================================
if ($action === 'thread') {
    $rawId = trim((string)($_GET['id'] ?? ''));
    if ($rawId === '') {
        client_json_response(false, 'Conversation ID is required.', [], 400);
    }

    $id = (int)preg_replace('/[^0-9]/', '', $rawId);
    if ($id <= 0) {
        client_json_response(false, 'Invalid conversation ID format.', [], 400);
    }

    $thread = client_get_conversation_thread($pdo, $orgId, $companyId, $id);
    if (!$thread) {
        client_json_response(false, 'Conversation not found or access denied.', [], 404);
    }

    client_json_response(true, 'Conversation thread retrieved successfully.', [
        'thread' => $thread
    ]);
}

// =========================================================================
// ACTION: send (Reply to an Existing Conversation)
// =========================================================================
if ($action === 'send') {
    $rawId = trim((string)($input['conversation_id'] ?? ($input['id'] ?? ($_GET['id'] ?? ''))));
    $convId = (int)preg_replace('/[^0-9]/', '', $rawId);

    if ($convId <= 0) {
        client_json_response(false, 'Valid conversation ID is required.', [], 422);
    }

    $body = trim((string)($input['body'] ?? ($input['message'] ?? '')));

    if ($body === '') {
        client_json_response(false, 'Message body cannot be empty.', [], 422);
    }

    if (!mb_check_encoding($body, 'UTF-8')) {
        client_json_response(false, 'Message body must be valid UTF-8 text.', [], 422);
    }

    if (mb_strlen($body) > 10000) {
        client_json_response(false, 'Message body cannot exceed 10,000 characters.', [], 422);
    }

    $result = client_send_message_reply($pdo, $client, $convId, $body);
    client_json_response($result['success'], $result['message'], $result['data'] ?? [], $result['http_code'] ?? 200);
}

// =========================================================================
// ACTION: new_conversation (Start a New Conversation)
// =========================================================================
if ($action === 'new_conversation') {
    $subject = trim((string)($input['subject'] ?? ''));

    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 200) {
        client_json_response(false, 'Subject must be between 3 and 200 characters.', [], 422);
    }

    $body = trim((string)($input['body'] ?? ($input['message'] ?? '')));

    if ($body === '') {
        client_json_response(false, 'Message body cannot be empty.', [], 422);
    }

    if (!mb_check_encoding($body, 'UTF-8')) {
        client_json_response(false, 'Message body must be valid UTF-8 text.', [], 422);
    }

    if (mb_strlen($body) > 10000) {
        client_json_response(false, 'Message body cannot exceed 10,000 characters.', [], 422);
    }

    $result = client_start_new_conversation($pdo, $client, $subject, $body);
    client_json_response($result['success'], $result['message'], $result['data'] ?? [], $result['http_code'] ?? 200);
}