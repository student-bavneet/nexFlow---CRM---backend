<?php
require_once __DIR__ . '/../config/database.php';
$_SESSION['user_id'] = 1;
require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../admin/includes/permissions.php';

echo "nexflow_current_user: " . json_encode(nexflow_current_user()) . "\n";

ob_start();
include __DIR__ . '/../admin/companies.php';
$cOut = ob_get_clean();
echo "admin/companies.php rendered cleanly: " . strlen($cOut) . " bytes\n";

ob_start();
include __DIR__ . '/../admin/contacts.php';
$cntOut = ob_get_clean();
echo "admin/contacts.php rendered cleanly: " . strlen($cntOut) . " bytes\n";
