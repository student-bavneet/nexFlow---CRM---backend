<?php
/**
 * NexFlow CRM - Administrator Logout Endpoint
 * Clears and destroys the PHP authentication session and redirects to admin-login.php
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /nexFlow/admin/admin-login.php');
exit;
