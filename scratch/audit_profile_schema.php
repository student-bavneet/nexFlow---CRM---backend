<?php
require_once __DIR__ . '/../config/database.php';

$pdo = nexflow_db();

function descTable($pdo, $table) {
    echo "=== $table ===\n";
    $stmt = $pdo->query("DESCRIBE $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-20s %-25s %-6s %-6s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key']);
    }
    echo "\n";
}

descTable($pdo, 'client_portal_users');
descTable($pdo, 'contacts');
descTable($pdo, 'companies');
descTable($pdo, 'users');

descTable($pdo, 'user_notification_preferences');
descTable($pdo, 'user_preferences');
descTable($pdo, 'crm_settings');
descTable($pdo, 'organization_settings');
descTable($pdo, 'organizations');

echo "=== SAMPLE USER_NOTIFICATION_PREFERENCES ===\n";
$stmt = $pdo->query("SELECT * FROM user_notification_preferences LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}

echo "=== COMPANY 13 ===\n";
$stmt = $pdo->query("SELECT * FROM companies WHERE id = 13");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "=== CONTACT 39 ===\n";
$stmt = $pdo->query("SELECT * FROM contacts WHERE id = 39");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "=== DESCRIBE contact_companies ===\n";
$stmt = $pdo->query("DESCRIBE contact_companies");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " | " . $r['Type'] . "\n";
}

echo "=== CREATE TABLE contacts ===\n";
$stmt = $pdo->query("SHOW CREATE TABLE contacts");
echo $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

echo "=== CREATE TABLE crm_settings ===\n";
$stmt = $pdo->query("SHOW CREATE TABLE crm_settings");
echo $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

echo "=== CRM SETTINGS ===\n";
$stmt = $pdo->query("SELECT * FROM crm_settings");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}

echo "\n=== ALL TABLES IN DB ===\n";
$stmt = $pdo->query("SHOW TABLES");
while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $r[0] . ", ";
}
echo "\n";
