<?php
/**
 * NexFlow CRM — Root Application Router
 * Authoritative entry point for http://localhost/nexFlow/
 *
 * Routing Policy:
 * 1. If visitor has active Client Portal session -> client-dashboard.php
 * 2. If visitor has genuinely authenticated Admin CRM session -> admin/index.php
 * 3. Otherwise (unauthenticated visitor) -> login.php (Client Portal Login)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/client-auth.php';

// 1. Check for active Client Portal authentication
$client = client_current_user();
if ($client !== null) {
    header('Location: client-dashboard.php');
    exit;
}

// 2. Check for active Admin CRM session without corrupting either session
if (!empty($_COOKIE['PHPSESSID'])) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    session_name('PHPSESSID');
    $sessId = (string)$_COOKIE['PHPSESSID'];
    if (preg_match('/^[a-zA-Z0-9_\-,]{1,128}$/', $sessId)) {
        session_id($sessId);
    }
    session_start();

    // Include Admin auth helper to perform genuine DB verification
    require_once __DIR__ . '/admin/includes/auth.php';
    $adminUser = nexflow_current_user();

    session_write_close();

    // Only redirect to Admin Dashboard if genuine active admin session exists
    if ($adminUser !== null) {
        header('Location: admin/index.php');
        exit;
    }
}

// 3. Unauthenticated default: redirect to Client Portal Login
header('Location: login.php');
exit;
