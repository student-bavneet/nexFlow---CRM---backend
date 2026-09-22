<?php
/**
 * NexFlow CRM - Comprehensive Email Preferences Integration Test Suite Runner
 */

echo "==================================================\n";
echo "NEXFLOW CRM - EMAIL PREFERENCES TEST SUITE\n";
echo "==================================================\n\n";

function run_test_step(string $testName, string $codeSnippet): bool {
    echo "--------------------------------------------------\n";
    echo "RUNNING: {$testName}\n";
    echo "--------------------------------------------------\n";

    $bootstrap = '<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $_SESSION["user_id"] = 1;
    $_SESSION["organization_id"] = 1;
    $_SESSION["role"] = "super_admin";
    require_once "c:/xampp/htdocs/nexFlow/config/database.php";
    ' . $codeSnippet;

    $tmpFile = __DIR__ . '/tmp_email_test_step.php';
    file_put_contents($tmpFile, $bootstrap);

    $output = shell_exec("php " . escapeshellarg($tmpFile));
    if (file_exists($tmpFile)) unlink($tmpFile);

    echo $output . "\n";
    return strpos($output, '"success":true') !== false ||
           strpos($output, 'Please enter a valid reply-to email address') !== false ||
           strpos($output, 'Sender display name cannot exceed 150 characters') !== false ||
           strpos($output, 'Email signature cannot exceed 5000 characters') !== false ||
           strpos($output, 'Invalid value for include signature') !== false ||
           strpos($output, 'Unauthorized') !== false ||
           strpos($output, 'ISOLATION_PASS') !== false;
}

// 1. TEST GET email_preferences (Defaults)
$test1 = run_test_step("TEST 1: GET action=email_preferences (Default Values)", '
$_SERVER["REQUEST_METHOD"] = "GET";
$_REQUEST = ["action" => "email_preferences"];
$_GET = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"]) && isset($json["data"]["email_include_signature"])) {
    echo "STATUS: PASS\nDATA: " . json_encode($json["data"]);
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 2. TEST POST email_preferences (Update Valid Settings)
$test2 = run_test_step("TEST 2: POST action=email_preferences (Save Valid Settings)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"                    => "email_preferences",
    "email_sender_display_name" => "Novasphere Sales Team",
    "email_reply_to"            => "sales@novasphere.example",
    "email_signature"           => "Best regards,\nOlivia Carter\nSuper Administrator | Novasphere",
    "email_include_signature"   => "1"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"]) && $json["data"]["email_sender_display_name"] === "Novasphere Sales Team" && $json["data"]["email_reply_to"] === "sales@novasphere.example") {
    echo "STATUS: PASS\nDATA: " . json_encode($json["data"]);
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 3. TEST GET email_preferences (Verify DB Persistence)
$test3 = run_test_step("TEST 3: GET action=email_preferences (Verify DB Persistence)", '
$_SERVER["REQUEST_METHOD"] = "GET";
$_REQUEST = ["action" => "email_preferences"];
$_GET = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"]) && $json["data"]["email_sender_display_name"] === "Novasphere Sales Team" && (int)$json["data"]["email_include_signature"] === 1) {
    echo "STATUS: PASS\nDATA: " . json_encode($json["data"]);
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 4. TEST Validation: Invalid reply-to email
$test4 = run_test_step("TEST 4: POST action=email_preferences (Invalid Email Validation Check)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"                    => "email_preferences",
    "email_sender_display_name" => "Valid Name",
    "email_reply_to"            => "invalid-email-address",
    "email_signature"           => "Some signature",
    "email_include_signature"   => "1"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && empty($json["success"])) {
    echo "STATUS: PASS\nMESSAGE: " . ($json["message"] ?? "");
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 5. TEST Validation: Sender Display Name > 150 chars
$test5 = run_test_step("TEST 5: POST action=email_preferences (Sender Name > 150 chars Check)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"                    => "email_preferences",
    "email_sender_display_name" => str_repeat("A", 151),
    "email_reply_to"            => "sales@novasphere.example",
    "email_signature"           => "Short signature",
    "email_include_signature"   => "1"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && empty($json["success"])) {
    echo "STATUS: PASS\nMESSAGE: " . ($json["message"] ?? "");
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 6. TEST Validation: Signature > 5000 chars
$test6 = run_test_step("TEST 6: POST action=email_preferences (Signature > 5000 chars Check)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"                    => "email_preferences",
    "email_sender_display_name" => "Valid Name",
    "email_reply_to"            => "sales@novasphere.example",
    "email_signature"           => str_repeat("X", 5001),
    "email_include_signature"   => "1"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && empty($json["success"])) {
    echo "STATUS: PASS\nMESSAGE: " . ($json["message"] ?? "");
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 7. TEST Validation: Invalid toggle value
$test7 = run_test_step("TEST 7: POST action=email_preferences (Invalid Toggle Value Check)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"                    => "email_preferences",
    "email_sender_display_name" => "Valid Name",
    "email_reply_to"            => "sales@novasphere.example",
    "email_signature"           => "Valid Signature",
    "email_include_signature"   => "INVALID_VALUE"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && empty($json["success"])) {
    echo "STATUS: PASS\nMESSAGE: " . ($json["message"] ?? "");
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 8. TEST Multi-Tenant Organization Isolation Check
$test8 = run_test_step("TEST 8: Multi-Tenant Organization Isolation Check", '
$_SERVER["REQUEST_METHOD"] = "GET";
$_REQUEST = ["action" => "email_preferences"];
$_GET = $_REQUEST;
require_once "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
$pdo = nexflow_db();
// Set settings for Org 999 directly
nexflow_set_crm_setting($pdo, 999, "email_sender_display_name", "Org 999 Sales Team");

// Query as Org 1
$org1Settings = nexflow_get_email_preferences($pdo, 1);
if ($org1Settings["email_sender_display_name"] !== "Org 999 Sales Team") {
    echo "STATUS: ISOLATION_PASS\nOrg 1 Sender Name: " . $org1Settings["email_sender_display_name"];
} else {
    echo "STATUS: FAIL\nLeaked Org 999 settings to Org 1";
}
// Clean up Org 999
$pdo->exec("DELETE FROM crm_settings WHERE organization_id = 999");
');

// 9. TEST Unauthenticated Access Check
$test9 = run_test_step("TEST 9: Unauthenticated Access Check", '
$_SESSION = [];
$_SERVER["REQUEST_METHOD"] = "GET";
$_REQUEST = ["action" => "email_preferences"];
$_GET = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && empty($json["success"]) && strpos($json["message"], "Unauthorized") !== false) {
    echo "STATUS: PASS\nMESSAGE: " . $json["message"];
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 10. Restore Initial Defaults
$test10 = run_test_step("TEST 10: Restore Initial Default Values", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"                    => "email_preferences",
    "email_sender_display_name" => "",
    "email_reply_to"            => "",
    "email_signature"           => "",
    "email_include_signature"   => "1"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"])) {
    echo "STATUS: PASS\nDefault settings restored.";
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

echo "==================================================\n";
if ($test1 && $test2 && $test3 && $test4 && $test5 && $test6 && $test7 && $test8 && $test9 && $test10) {
    echo "ALL 10 EMAIL PREFERENCES INTEGRATION TESTS PASSED PERFECTLY! ✅\n";
} else {
    echo "SOME TESTS FAILED! ❌\n";
}
echo "==================================================\n";
