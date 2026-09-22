<?php
/** NexFlow CRM - Admin authentication API */
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

function auth_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    auth_json(false, 'Database connection failed. Please check your database configuration.', [], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(false, 'Invalid request method.', [], 405);
}

// Support both standard POST data and raw JSON payloads
$input = $_POST;
if (empty($input)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $input = $json;
        }
    }
}

$action = $input['action'] ?? '';

if ($action === 'login') {
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        auth_json(false, 'Please enter a valid email and password.', [], 422);
    }

    $stmt = $pdo->prepare("SELECT u.*, o.setup_completed FROM users u LEFT JOIN organizations o ON o.id = u.organization_id WHERE LOWER(TRIM(u.email)) = ? AND u.status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        auth_json(false, 'Invalid email or password.', [], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['organization_id'] = (int)$user['organization_id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['authenticated'] = true;
    $_SESSION['login_time'] = time();

    $update = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $update->execute([(int)$user['id']]);

    $redirect = 'index.php';
    try {
        $prefStmt = $pdo->prepare("SELECT landing_page FROM user_preferences WHERE user_id = ? LIMIT 1");
        $prefStmt->execute([(int)$user['id']]);
        $landingPage = $prefStmt->fetchColumn();
        if ($landingPage && $landingPage !== 'dashboard') {
            $pageMap = [
                'leads'          => 'leads.php',
                'pipeline'       => 'pipeline.php',
                'sales-pipeline' => 'pipeline.php',
                'projects'       => 'projects.php',
                'contacts'       => 'contacts.php',
                'companies'      => 'companies.php',
                'tasks'          => 'tasks.php',
                'inbox'          => 'inbox.php',
                'reports'        => 'reports.php'
            ];
            if (isset($pageMap[$landingPage])) {
                $redirect = $pageMap[$landingPage];
            }
        }
    } catch (Throwable $e) {
        $redirect = 'index.php';
    }

    auth_json(true, 'Login successful.', ['redirect' => $redirect]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    auth_json(true, 'Logged out.', ['redirect' => 'admin-login.php']);
}

auth_json(false, 'Invalid authentication action.', [], 400);
