<?php
/**
 * NexFlow CRM — Client Portal Dedicated Authentication Guard
 * Enforces dedicated PHP session namespace and cookie: nexflow_client_portal.
 * Complete isolation from Admin CRM session.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const CLIENT_PORTAL_SESSION_NAME = 'nexflow_client_portal';

/**
 * Start or resume the dedicated client portal session.
 */
function client_portal_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(CLIENT_PORTAL_SESSION_NAME);

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/nexFlow',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

// Auto-start client portal session
client_portal_session_start();

/**
 * Generate or fetch the Client Portal CSRF token.
 */
function client_csrf_token(): string
{
    if (empty($_SESSION['client_portal_csrf_token'])) {
        $_SESSION['client_portal_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['client_portal_csrf_token'];
}

/**
 * Verify a submitted Client Portal CSRF token.
 */
function verify_client_csrf(?string $token): bool
{
    if (empty($token) || empty($_SESSION['client_portal_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['client_portal_csrf_token'], $token);
}

/**
 * Get the currently authenticated client portal user data.
 * Derives context strictly from live MySQL using session IDs.
 */
function client_current_user(): ?array
{
    if (empty($_SESSION['client_portal_logged_in']) || empty($_SESSION['client_portal_user_id'])) {
        return null;
    }

    $portalUserId = (int)$_SESSION['client_portal_user_id'];
    $orgId        = (int)($_SESSION['client_portal_org_id'] ?? 0);
    $companyId    = (int)($_SESSION['client_portal_company_id'] ?? 0);
    $contactId    = (int)($_SESSION['client_portal_contact_id'] ?? 0);

    if ($portalUserId <= 0 || $orgId <= 0 || $companyId <= 0 || $contactId <= 0) {
        return null;
    }

    try {
        $pdo = nexflow_db();
        $stmt = $pdo->prepare("
            SELECT 
                cpu.id AS portal_user_id,
                cpu.organization_id,
                cpu.company_id,
                cpu.contact_id,
                cpu.email AS portal_email,
                cpu.status AS portal_status,
                c.first_name,
                c.last_name,
                c.name AS contact_name,
                c.email AS contact_email,
                c.phone AS contact_phone,
                c.job_title,
                comp.name AS company_name,
                comp.industry AS company_industry,
                comp.owner_id AS ae_user_id,
                u.name AS ae_name,
                u.email AS ae_email,
                u.job_title AS ae_title,
                u.phone AS ae_phone,
                u.photo_path AS ae_photo
            FROM client_portal_users cpu
            JOIN contacts c ON cpu.contact_id = c.id
            JOIN contact_companies cc ON (c.id = cc.contact_id AND cpu.company_id = cc.company_id)
            JOIN companies comp ON cc.company_id = comp.id
            LEFT JOIN users u ON comp.owner_id = u.id
            WHERE cpu.id = ? 
              AND cpu.organization_id = ?
              AND cpu.company_id = ?
              AND cpu.contact_id = ?
              AND cpu.status = 'active'
              AND c.is_active = 1
              AND comp.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$portalUserId, $orgId, $companyId, $contactId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        // Compute initials
        $nameParts = preg_split('/\s+/', trim((string)$user['contact_name']));
        $initials = '';
        if (!empty($nameParts[0])) {
            $initials .= mb_strtoupper(mb_substr($nameParts[0], 0, 1));
        }
        if (!empty($nameParts[1])) {
            $initials .= mb_strtoupper(mb_substr($nameParts[1], 0, 1));
        }
        $user['avatar_initials'] = $initials ?: 'CP';

        // Compute Account Executive initials
        $aeParts = preg_split('/\s+/', trim((string)($user['ae_name'] ?? 'Account Exec')));
        $aeInitials = '';
        if (!empty($aeParts[0])) {
            $aeInitials .= mb_strtoupper(mb_substr($aeParts[0], 0, 1));
        }
        if (!empty($aeParts[1])) {
            $aeInitials .= mb_strtoupper(mb_substr($aeParts[1], 0, 1));
        }
        $user['ae_avatar_initials'] = $aeInitials ?: 'AE';

        return $user;
    } catch (Throwable $e) {
        error_log('client_current_user error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Sanitize and validate a candidate return URL for Client Portal redirection.
 * Only permits approved local Client Portal pages with optional safe query parameters.
 * Rejects external URLs, protocol-relative URLs, backslashes, loops, and malicious payloads.
 */
function sanitize_client_return_url(?string $url): ?string
{
    if ($url === null) {
        return null;
    }

    $trimmed = trim($url);
    if ($trimmed === '') {
        return null;
    }

    // Decode URL up to twice to catch double-encoding bypass attempts (%252f etc.)
    $decoded = rawurldecode($trimmed);
    $decoded = rawurldecode($decoded);

    // Reject control characters, null bytes, backslashes, and protocol/scheme indicators
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $decoded) ||
        preg_match('/^(https?:|\/\/|javascript:|data:|vbscript:)/i', $trimmed) ||
        preg_match('/^(https?:|\/\/|javascript:|data:|vbscript:)/i', $decoded)) {
        return null;
    }

    // Strip leading "/nexFlow/" or "/" prefix to get relative target
    $path = preg_replace('#^(\/nexFlow\/|\/)+#i', '', $trimmed);

    // Separate script path from query string
    $parts = explode('?', $path, 2);
    $file = strtolower(trim($parts[0]));
    $query = $parts[1] ?? null;

    $allowedPages = [
        'client-dashboard.php',
        'client-projects.php',
        'client-deals.php',
        'client-proposals.php',
        'client-estimates.php',
        'client-contracts.php',
        'client-invoices.php',
        'client-tasks.php',
        'client-calendar.php',
        'client-messages.php',
        'client-documents.php',
        'client-profile.php',
        'client-help.php',
    ];

    if (!in_array($file, $allowedPages, true)) {
        return null;
    }

    if ($query !== null && $query !== '') {
        // Query parameters must only contain alphanumeric characters, underscores, dashes, equals, ampersands, percent
        if (!preg_match('/^[a-zA-Z0-9_\-=&%.]+$/', $query)) {
            return $file;
        }
        return $file . '?' . $query;
    }

    return $file;
}

/**
 * Authentication guard for client portal pages.
 * Redirects unauthenticated visitors to login.php, safely preserving return destination.
 */
function require_client_auth(): array
{
    $client = client_current_user();
    if (!$client) {
        $requestedUri = $_SERVER['REQUEST_URI'] ?? '';
        $safeReturn = sanitize_client_return_url($requestedUri);

        $loginUrl = 'login.php';
        if ($safeReturn !== null) {
            $loginUrl .= '?return_to=' . urlencode($safeReturn);
        }

        header('Location: ' . $loginUrl);
        exit;
    }

    // Anti-caching headers on all authenticated pages to prevent browser back-navigation after logout
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    return $client;
}

/**
 * Log out the client portal user and clear dedicated session.
 */
function client_logout(): void
{
    client_portal_session_start();

    // Clear client-specific session variables
    $_SESSION['client_portal_logged_in'] = false;
    unset(
        $_SESSION['client_portal_logged_in'],
        $_SESSION['client_portal_user_id'],
        $_SESSION['client_portal_org_id'],
        $_SESSION['client_portal_company_id'],
        $_SESSION['client_portal_contact_id'],
        $_SESSION['client_portal_csrf_token']
    );

    if (session_id() !== '') {
        @session_destroy();
    }

    // Expire dedicated client cookie
    if (isset($_COOKIE[CLIENT_PORTAL_SESSION_NAME])) {
        setcookie(CLIENT_PORTAL_SESSION_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/nexFlow',
            'domain'   => '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[CLIENT_PORTAL_SESSION_NAME]);
    }
}
