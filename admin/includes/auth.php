<?php
/**
 * NexFlow CRM - Database-backed admin authentication guard.
 */

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function nexflow_is_installed(): bool
{
    try {
        $pdo = nexflow_db();
        $stmt = $pdo->query("SELECT id FROM organizations WHERE setup_completed = 1 ORDER BY id ASC LIMIT 1");
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function nexflow_role_label($roleInput): string
{
    if (empty($roleInput)) {
        return 'Administrator';
    }

    if (is_numeric($roleInput)) {
        try {
            $pdo = nexflow_db();
            $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$roleInput]);
            $name = $stmt->fetchColumn();
            if ($name) return $name;
        } catch (Throwable $e) {}
    }

    $cleanKey = strtolower(trim((string)$roleInput));
    $slugMap = [
        'super_admin'       => 'Super Administrator',
        'admin'             => 'Administrator',
        'administrator'     => 'Administrator',
        'sales_manager'     => 'Sales Manager',
        'sales_rep'         => 'Sales Representative',
        'account_executive' => 'Account Executive',
        'sales_ops'         => 'Sales Operations',
        'sales_operations'  => 'Sales Operations',
        'viewer'            => 'Viewer'
    ];

    if (isset($slugMap[$cleanKey])) {
        return $slugMap[$cleanKey];
    }

    try {
        $pdo = nexflow_db();
        $stmt = $pdo->prepare("SELECT name FROM roles WHERE slug = ? LIMIT 1");
        $stmt->execute([$cleanKey]);
        $name = $stmt->fetchColumn();
        if ($name) return $name;
    } catch (Throwable $e) {}

    return ucwords(str_replace('_', ' ', $cleanKey));
}

function nexflow_current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $pdo = nexflow_db();
        $stmt = $pdo->prepare("SELECT u.*, o.name AS organization_name FROM users u JOIN organizations o ON o.id = u.organization_id WHERE u.id = ? AND u.status = 'active' LIMIT 1");
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $user['role_label'] = nexflow_role_label($user['role_id'] ?? $user['role'] ?? 'super_admin');
        }
        return $user ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function require_admin_auth(): array
{
    if (!nexflow_is_installed()) {
        header('Location: /nexFlow/admin/onboarding.php');
        exit;
    }

    $user = nexflow_current_user();
    if (!$user) {
        header('Location: /nexFlow/admin/admin-login.php');
        exit;
    }

    return $user;
}

function nexflow_user_preferences(?int $userId = null): array
{
    if (!$userId && !empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    }
    $defaults = [
        'language'          => 'en-US',
        'timezone'          => 'America/Los_Angeles',
        'date_format'       => 'MMM DD, YYYY',
        'time_format'       => '12h',
        'week_starts_on'    => 'Sunday',
        'currency'          => 'USD ($)',
        'number_format'     => '1,234.56',
        'landing_page'      => 'dashboard',
        'table_rows'        => 25,
        'interface_density' => 'comfortable'
    ];
    if (!$userId) {
        return $defaults;
    }

    try {
        $pdo = nexflow_db();
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pref) {
            $initStmt = $pdo->prepare("INSERT INTO user_preferences (user_id, language, timezone, date_format, time_format, week_starts_on, currency, number_format, landing_page, table_rows, interface_density, created_at, updated_at) VALUES (?, 'en-US', 'America/Los_Angeles', 'MMM DD, YYYY', '12h', 'Sunday', 'USD ($)', '1,234.56', 'dashboard', 25, 'comfortable', NOW(), NOW())");
            $initStmt->execute([$userId]);

            $stmt->execute([$userId]);
            $pref = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $pref ? array_merge($defaults, $pref) : $defaults;
    } catch (Throwable $e) {
        return $defaults;
    }
}

function nexflow_user_notifications(?int $userId = null): array
{
    if (!$userId && !empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    }
    $defaults = [
        'new_lead_assigned'     => 1,
        'lead_status_changed'   => 1,
        'follow_up_due'         => 1,
        'deal_assigned'         => 1,
        'task_due_soon'         => 1,
        'missed_simulated_call' => 1
    ];
    if (!$userId) {
        return $defaults;
    }

    try {
        $pdo = nexflow_db();
        $stmt = $pdo->prepare("SELECT * FROM user_notification_preferences WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notif) {
            $initStmt = $pdo->prepare("INSERT INTO user_notification_preferences (user_id, new_lead_assigned, lead_status_changed, follow_up_due, deal_assigned, task_due_soon, missed_simulated_call, created_at, updated_at) VALUES (?, 1, 1, 1, 1, 1, 1, NOW(), NOW())");
            $initStmt->execute([$userId]);

            $stmt->execute([$userId]);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $notif ? array_merge($defaults, $notif) : $defaults;
    } catch (Throwable $e) {
        return $defaults;
    }
}

function format_user_date($date, ?int $userId = null): string
{
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? (int)$date : strtotime($date);
    if (!$timestamp) return (string)$date;

    $prefs = nexflow_user_preferences($userId);
    $fmt = $prefs['date_format'] ?? 'MMM DD, YYYY';

    switch ($fmt) {
        case 'YYYY-MM-DD':
            return date('Y-m-d', $timestamp);
        case 'DD/MM/YYYY':
            return date('d/m/Y', $timestamp);
        case 'MM/DD/YYYY':
            return date('m/d/Y', $timestamp);
        case 'MMM DD, YYYY':
        default:
            return date('M j, Y', $timestamp);
    }
}

function format_user_time($time, ?int $userId = null): string
{
    if (empty($time)) return '';
    $timestamp = is_numeric($time) ? (int)$time : strtotime($time);
    if (!$timestamp) return (string)$time;

    $prefs = nexflow_user_preferences($userId);
    $fmt = $prefs['time_format'] ?? '12h';

    return ($fmt === '24h' || $fmt === '24-hour') ? date('H:i', $timestamp) : date('h:i A', $timestamp);
}

/**
 * Generate or fetch the Admin CSRF token.
 */
function admin_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

/**
 * Verify a submitted Admin CSRF token using timing-safe comparison.
 */
function verify_admin_csrf(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($token) || empty($_SESSION['admin_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['admin_csrf_token'], $token);
}


