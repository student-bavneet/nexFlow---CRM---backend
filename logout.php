<?php
/**
 * NexFlow CRM — Client Portal Logout Handler
 * Terminates the dedicated Client Portal session and redirects to login.php.
 * Preserves the separate Admin session.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/client-auth.php';

client_logout();

header('Location: login.php');
exit;
