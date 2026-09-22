<?php
/**
 * NexFlow CRM — Client Portal Profile & Security API Controller
 * Secure API endpoints for profile data, profile updates, password change, and notification preferences.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-profile-data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

/**
 * Helper to emit consistent JSON response and exit.
 */
function client_profile_json(bool $success, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Method Guard: Only GET and POST permitted
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    client_profile_json(false, 'Method Not Allowed. Only GET and POST are supported.', [], 405);
}

// 2. Authentication Guard: Must have active client session
$client = client_current_user();
if (!$client) {
    client_profile_json(false, 'Unauthorized. Please sign in to your client portal account.', [], 401);
}

$orgId        = (int)$client['organization_id'];
$companyId    = (int)$client['company_id'];
$contactId    = (int)$client['contact_id'];
$portalUserId = (int)$client['portal_user_id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    error_log('Database connection error in api/client-profile.php: ' . $e->getMessage());
    client_profile_json(false, 'Database connection error.', [], 500);
}

// 3. CSRF Verification for all POST requests
if ($method === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_client_csrf($csrfToken)) {
        client_profile_json(false, 'Invalid or expired CSRF security token. Please refresh the page and try again.', [], 403);
    }
}

// Route actions
$action = trim((string)($_GET['action'] ?? ($_POST['action'] ?? 'get')));

if ($method === 'GET') {
    if ($action === 'get' || $action === '') {
        $profile = client_get_full_profile($pdo, $orgId, $companyId, $contactId, $portalUserId);
        if (!$profile) {
            client_profile_json(false, 'Client profile not found.', [], 404);
        }
        client_profile_json(true, 'Profile retrieved successfully.', $profile, 200);
    }
    client_profile_json(false, 'Invalid action specified.', [], 400);
}

if ($method === 'POST') {
    // Disallowed company modification
    if ($action === 'update_company') {
        client_profile_json(false, 'Company legal records are managed by your assigned Account Executive. Please use the Account Lead Support action to request changes.', [], 403);
    }

    // Action: update_profile
    if ($action === 'update_profile') {
        $result = client_update_personal_profile($pdo, $orgId, $companyId, $contactId, $portalUserId, $_POST);
        client_profile_json(
            $result['success'],
            $result['message'],
            $result['data'] ?? [],
            $result['status'] ?? ($result['success'] ? 200 : 400)
        );
    }

    // Action: change_password
    if ($action === 'change_password') {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass     = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        $result = client_change_portal_password(
            $pdo,
            $orgId,
            $companyId,
            $contactId,
            $portalUserId,
            $currentPass,
            $newPass,
            $confirmPass
        );

        if ($result['success']) {
            // Regenerate session ID upon successful credential change
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        }

        client_profile_json(
            $result['success'],
            $result['message'],
            [],
            $result['status'] ?? ($result['success'] ? 200 : 400)
        );
    }

    // Action: update_notifications
    if ($action === 'update_notifications') {
        $emailSummaries = isset($_POST['email_summaries'])
            ? filter_var($_POST['email_summaries'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
            : false;
        $inPortal = isset($_POST['in_portal'])
            ? filter_var($_POST['in_portal'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
            : false;

        $result = client_update_portal_notifications(
            $pdo,
            $orgId,
            $portalUserId,
            $emailSummaries,
            $inPortal
        );

        client_profile_json(
            $result['success'],
            $result['message'],
            $result['data'] ?? [],
            $result['status'] ?? 200
        );
    }

    client_profile_json(false, 'Invalid action specified.', [], 400);
}
