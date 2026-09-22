<?php
/**
 * NexFlow CRM — Client Portal Authentication API Controller
 * Provides login, logout, and session context endpoints.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Parse JSON input if sent as application/json
$input = $_POST;
$rawBody = file_get_contents('php://input');
if (!empty($rawBody)) {
    $json = json_decode($rawBody, true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
        if (empty($action) && isset($json['action'])) {
            $action = (string)$json['action'];
        }
    }
}

switch ($action) {
    case 'login':
        handle_client_login($input);
        break;

    case 'logout':
        handle_client_logout();
        break;

    case 'me':
        handle_client_me();
        break;

    default:
        client_json_response(false, 'Invalid or missing action parameter.', [], 400);
}

/**
 * Handle client portal credentials verification and session creation.
 */
function handle_client_login(array $input): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        client_json_response(false, 'Method Not Allowed. Use POST for login.', [], 405);
    }

    $email    = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if (empty($email) || empty($password)) {
        client_json_response(false, 'Please enter both your email address and password.', [], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        client_json_response(false, 'Please enter a valid email address format.', [], 422);
    }

    try {
        $pdo = nexflow_db();

        // Query active portal user joined through contact_companies (authoritative)
        $stmt = $pdo->prepare("
            SELECT 
                cpu.id AS portal_user_id,
                cpu.organization_id,
                cpu.company_id,
                cpu.contact_id,
                cpu.password_hash,
                cpu.status AS portal_status,
                c.first_name,
                c.last_name,
                c.name AS contact_name,
                c.email AS contact_email,
                comp.name AS company_name,
                comp.is_active AS company_active,
                c.is_active AS contact_active
            FROM client_portal_users cpu
            JOIN contacts c ON cpu.contact_id = c.id
            JOIN contact_companies cc ON (c.id = cc.contact_id AND cpu.company_id = cc.company_id)
            JOIN companies comp ON cc.company_id = comp.id
            WHERE cpu.email = ? 
              AND cpu.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            client_json_response(false, 'Invalid email or password.', [], 401);
        }

        if (empty($user['contact_active']) || empty($user['company_active'])) {
            client_json_response(false, 'This account is currently inactive. Please contact your account manager.', [], 403);
        }

        // Verify password hash
        if (!password_verify($password, $user['password_hash'])) {
            client_json_response(false, 'Invalid email or password.', [], 401);
        }

        // Establish dedicated client session
        client_portal_session_start();
        session_regenerate_id(true);

        $_SESSION['client_portal_logged_in']  = true;
        $_SESSION['client_portal_user_id']    = (int)$user['portal_user_id'];
        $_SESSION['client_portal_org_id']     = (int)$user['organization_id'];
        $_SESSION['client_portal_company_id'] = (int)$user['company_id'];
        $_SESSION['client_portal_contact_id'] = (int)$user['contact_id'];
        $_SESSION['client_portal_csrf_token'] = bin2hex(random_bytes(32));

        // Update login audit timestamp
        $updateStmt = $pdo->prepare("
            UPDATE client_portal_users 
            SET last_login_at = NOW(), 
                last_login_ip = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            (int)$user['portal_user_id']
        ]);

        $rawReturn = $input['return_to'] ?? $_GET['return_to'] ?? null;
        $safeRedirect = sanitize_client_return_url(is_string($rawReturn) ? $rawReturn : null);

        client_json_response(true, 'Sign in successful. Redirecting to workspace...', [
            'redirect'     => $safeRedirect ?: 'client-dashboard.php',
            'contact_name' => $user['contact_name'],
            'company_name' => $user['company_name'],
        ], 200);

    } catch (Throwable $e) {
        error_log('Client login error: ' . $e->getMessage());
        client_json_response(false, 'An error occurred during authentication. Please try again later.', [], 500);
    }
}

/**
 * Terminate client portal session.
 */
function handle_client_logout(): void
{
    client_logout();
    client_json_response(true, 'Signed out of Client Portal.', [
        'redirect' => 'login.php'
    ]);
}

/**
 * Return current client identity context.
 */
function handle_client_me(): void
{
    $user = client_current_user();
    if (!$user) {
        client_json_response(false, 'Unauthenticated client session.', [], 401);
    }

    // Do not expose sensitive internal columns
    unset($user['password_hash']);

    client_json_response(true, 'Authenticated client profile.', [
        'user' => $user
    ]);
}
