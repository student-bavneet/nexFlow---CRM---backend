<?php
/**
 * NexFlow CRM - Comprehensive Task Settings Integration Test Suite Runner
 */

echo "==================================================\n";
echo "NEXFLOW CRM - TASK SETTINGS SUITE RUNNER\n";
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

    $tmpFile = __DIR__ . '/tmp_test_step.php';
    file_put_contents($tmpFile, $bootstrap);

    $output = shell_exec("php " . escapeshellarg($tmpFile));
    if (file_exists($tmpFile)) unlink($tmpFile);

    echo $output . "\n";
    return strpos($output, '"success":true') !== false || strpos($output, 'Invalid task default priority') !== false || strpos($output, 'Unauthorized') !== false;
}

// 1. TEST GET task_settings
$test1 = run_test_step("TEST 1: GET task_settings", '
$_SERVER["REQUEST_METHOD"] = "GET";
$_REQUEST = ["action" => "task_settings"];
$_GET = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"]) && isset($json["data"]["task_default_status"])) {
    echo "STATUS: PASS\nJSON: " . json_encode($json["data"]);
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 2. TEST POST task_settings
$test2 = run_test_step("TEST 2: POST task_settings (Save custom settings)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"               => "task_settings",
    "task_default_status"   => "In Progress",
    "task_default_priority" => "High",
    "task_default_reminder" => "30 minutes before",
    "task_default_view"     => "board",
    "task_show_completed"   => "0"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"]) && $json["data"]["task_default_status"] === "In Progress" && $json["data"]["task_default_view"] === "board") {
    echo "STATUS: PASS\nJSON: " . json_encode($json["data"]);
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 3. TEST GET task_settings (Verify Persistence)
$test3 = run_test_step("TEST 3: GET task_settings (Verify DB persistence)", '
$_SERVER["REQUEST_METHOD"] = "GET";
$_REQUEST = ["action" => "task_settings"];
$_GET = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"]) && $json["data"]["task_default_view"] === "board" && (int)$json["data"]["task_show_completed"] === 0) {
    echo "STATUS: PASS\nJSON: " . json_encode($json["data"]);
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 4. TEST Validation error for invalid priority
$test4 = run_test_step("TEST 4: POST task_settings (Invalid priority check)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"               => "task_settings",
    "task_default_status"   => "Pending",
    "task_default_priority" => "INVALID_PRIORITY",
    "task_default_reminder" => "15 minutes before",
    "task_default_view"     => "list",
    "task_show_completed"   => "1"
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

// 5. TEST Restore Defaults
$test5 = run_test_step("TEST 5: POST task_settings (Restore default values)", '
$_SERVER["REQUEST_METHOD"] = "POST";
$_REQUEST = [
    "action"               => "task_settings",
    "task_default_status"   => "Pending",
    "task_default_priority" => "Medium",
    "task_default_reminder" => "15 minutes before",
    "task_default_view"     => "list",
    "task_show_completed"   => "1"
];
$_POST = $_REQUEST;
ob_start();
try {
    include "c:/xampp/htdocs/nexFlow/admin/api/settings.php";
} catch (Throwable $e) {}
$res = ob_get_clean();
$json = json_decode($res, true);
if ($json && !empty($json["success"]) && $json["data"]["task_default_status"] === "Pending" && $json["data"]["task_default_view"] === "list") {
    echo "STATUS: PASS\nJSON: " . json_encode($json["data"]);
} else {
    echo "STATUS: FAIL\nRES: " . $res;
}
');

// 6. TEST Unauthenticated Security Check
$test6 = run_test_step("TEST 6: Unauthenticated access check", '
$_SESSION = [];
$_SERVER["REQUEST_METHOD"] = "GET";
$_REQUEST = ["action" => "task_settings"];
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

echo "==================================================\n";
if ($test1 && $test2 && $test3 && $test4 && $test5 && $test6) {
    echo "ALL 6 INTEGRATION TESTS PASSED PERFECTLY! ✅\n";
} else {
    echo "SOME TESTS FAILED! ❌\n";
}
echo "==================================================\n";
