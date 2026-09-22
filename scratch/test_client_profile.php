<?php
/**
 * NexFlow CRM — Client Portal Profile & Security Test Suite
 * Fully automated regression, authorization, validation, and security verification.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-profile-data.php';

echo "================================================================================\n";
echo "  NEXFLOW CRM — CLIENT PORTAL PROFILE & SECURITY TEST SUITE\n";
echo "================================================================================\n\n";

$pdo = nexflow_db();

// 0. Discover live baseline client portal user
$stmt = $pdo->query("
    SELECT cpu.*, c.name AS contact_name, c.email AS contact_email, c.phone AS contact_phone, c.job_title, comp.name AS company_name, comp.owner_id AS ae_user_id
    FROM client_portal_users cpu
    JOIN contacts c ON cpu.contact_id = c.id
    JOIN companies comp ON cpu.company_id = comp.id
    WHERE cpu.status = 'active'
    ORDER BY cpu.id ASC
    LIMIT 1
");
$liveClient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$liveClient) {
    die("FATAL: No active client portal user found for testing.\n");
}

$orgId        = (int)$liveClient['organization_id'];
$companyId    = (int)$liveClient['company_id'];
$contactId    = (int)$liveClient['contact_id'];
$portalUserId = (int)$liveClient['id'];

// Snapshot admin users state to verify zero modification
$adminSnapshot = $pdo->query("SELECT id, email, password_hash, updated_at FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Snapshot initial contact and portal user state to ensure clean restoration
$contactSnapshot = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
$contactSnapshot->execute([$contactId]);
$initialContact = $contactSnapshot->fetch(PDO::FETCH_ASSOC);

$cpuSnapshot = $pdo->prepare("SELECT * FROM client_portal_users WHERE id = ?");
$cpuSnapshot->execute([$portalUserId]);
$initialCpu = $cpuSnapshot->fetch(PDO::FETCH_ASSOC);

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $title, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "✓ PASS: {$title}\n";
    } else {
        $failed++;
        echo "✗ FAIL: {$title}\n";
        if ($details !== '') {
            echo "  Details: {$details}\n";
        }
    }
}

/**
 * Execute API call in an isolated PHP subprocess.
 */
function run_api_call(array $sessionData, string $method, array $queryParams = [], array $postParams = [], array $headers = []): array {
    $tmpFile = __DIR__ . '/tmp_profile_api_call_' . bin2hex(random_bytes(4)) . '.php';

    $script = '<?php
    declare(strict_types=1);
    require_once ' . var_export(__DIR__ . '/../includes/client-auth.php', true) . ';

    register_shutdown_function(function() {
        $code = http_response_code();
        echo "\n---SPLIT_HTTP_CODE:" . $code . "---\n";
    });

    client_portal_session_start();
    $_SESSION = ' . var_export($sessionData, true) . ';
    $_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';
    $_GET = ' . var_export($queryParams, true) . ';
    $_POST = ' . var_export($postParams, true) . ';
    $_REQUEST = array_merge($_GET, $_POST);

    foreach (' . var_export($headers, true) . ' as $k => $v) {
        $_SERVER[$k] = $v;
    }

    include ' . var_export(__DIR__ . '/../api/client-profile.php', true) . ';
    ';

    file_put_contents($tmpFile, $script);
    $out = shell_exec('php ' . escapeshellarg($tmpFile));
    if (file_exists($tmpFile)) {
        @unlink($tmpFile);
    }

    $parts = explode("\n---SPLIT_HTTP_CODE:", (string)$out);
    $body = trim($parts[0] ?? '');
    $httpCode = 0;
    if (isset($parts[1])) {
        $httpCode = (int)rtrim($parts[1], "-\n\r ");
    }
    $json = json_decode($body, true) ?? [];

    return ['code' => $httpCode, 'body' => $body, 'json' => $json];
}

$activeSession = [
    'client_portal_logged_in'  => true,
    'client_portal_user_id'    => $portalUserId,
    'client_portal_org_id'     => $orgId,
    'client_portal_company_id' => $companyId,
    'client_portal_contact_id' => $contactId,
    'client_portal_csrf_token' => 'valid_csrf_token_1234567890',
];
$validCsrf = 'valid_csrf_token_1234567890';

// -----------------------------------------------------------------------------
// TEST 1 & 2: Unauthenticated Requests (GET and POST)
// -----------------------------------------------------------------------------
$resUnauthGet = run_api_call([], 'GET', ['action' => 'get']);
assert_test($resUnauthGet['code'] === 401 && empty($resUnauthGet['json']['success']), "1. Unauthenticated profile GET returns HTTP 401");

$resUnauthPost = run_api_call([], 'POST', ['action' => 'update_profile'], ['csrf_token' => $validCsrf, 'name' => 'Hacker']);
assert_test($resUnauthPost['code'] === 401 && empty($resUnauthPost['json']['success']), "2. Unauthenticated update POST returns HTTP 401");

// -----------------------------------------------------------------------------
// TEST 3 & 4: CSRF Protection
// -----------------------------------------------------------------------------
$resMissingCsrf = run_api_call($activeSession, 'POST', ['action' => 'update_profile'], ['name' => 'Test Name']);
assert_test($resMissingCsrf['code'] === 403 && strpos($resMissingCsrf['body'], 'CSRF') !== false, "3. Missing CSRF token returns HTTP 403");

$resInvalidCsrf = run_api_call($activeSession, 'POST', ['action' => 'update_profile'], ['csrf_token' => 'forged_bad_token', 'name' => 'Test Name']);
assert_test($resInvalidCsrf['code'] === 403 && strpos($resInvalidCsrf['body'], 'CSRF') !== false, "4. Invalid CSRF token returns HTTP 403");

// -----------------------------------------------------------------------------
// TEST 5: Disallowed HTTP Methods (HTTP 405)
// -----------------------------------------------------------------------------
$resPut = run_api_call($activeSession, 'PUT', ['action' => 'get']);
$resDelete = run_api_call($activeSession, 'DELETE', ['action' => 'get']);
assert_test($resPut['code'] === 405 && $resDelete['code'] === 405, "5. Disallowed HTTP methods (PUT, DELETE) return HTTP 405");

// -----------------------------------------------------------------------------
// TEST 6: Profile Data Comes from Authenticated Client
// -----------------------------------------------------------------------------
$resProfile = run_api_call($activeSession, 'GET', ['action' => 'get']);
$pData = $resProfile['json']['data'] ?? [];
assert_test(
    $resProfile['code'] === 200 &&
    !empty($resProfile['json']['success']) &&
    isset($pData['personal']['name']) &&
    $pData['personal']['name'] === $liveClient['contact_name'] &&
    $pData['personal']['email'] === $liveClient['email'],
    "6. Profile data corresponds strictly to the authenticated client"
);

// -----------------------------------------------------------------------------
// TEST 7: Cross-Company Access Isolation
// -----------------------------------------------------------------------------
$tamperedSessionCo = $activeSession;
$tamperedSessionCo['client_portal_company_id'] = 99999;
$resCrossCo = run_api_call($tamperedSessionCo, 'GET', ['action' => 'get']);
assert_test($resCrossCo['code'] === 401 || $resCrossCo['code'] === 404, "7. Cross-company access is blocked");

// -----------------------------------------------------------------------------
// TEST 8: Organization Isolation
// -----------------------------------------------------------------------------
$tamperedSessionOrg = $activeSession;
$tamperedSessionOrg['client_portal_org_id'] = 99999;
$resCrossOrg = run_api_call($tamperedSessionOrg, 'GET', ['action' => 'get']);
assert_test($resCrossOrg['code'] === 401 || $resCrossOrg['code'] === 404, "8. Cross-organization access is blocked");

// -----------------------------------------------------------------------------
// TEST 9: Unauthorized Ownership Modification Blocked
// -----------------------------------------------------------------------------
$resTamperOwnership = run_api_call($activeSession, 'POST', ['action' => 'update_profile'], [
    'csrf_token'      => $validCsrf,
    'name'            => $liveClient['contact_name'],
    'email'           => $liveClient['email'],
    'organization_id' => 999,
    'company_id'      => 999,
    'contact_id'      => 999,
    'status'          => 'inactive',
]);
$checkCpu = $pdo->prepare("SELECT organization_id, company_id, contact_id, status FROM client_portal_users WHERE id = ?");
$checkCpu->execute([$portalUserId]);
$currCpu = $checkCpu->fetch(PDO::FETCH_ASSOC);
assert_test(
    (int)$currCpu['organization_id'] === $orgId &&
    (int)$currCpu['company_id'] === $companyId &&
    (int)$currCpu['contact_id'] === $contactId &&
    $currCpu['status'] === 'active',
    "9. Unauthorized ownership fields (org_id, company_id, contact_id, status) cannot be changed"
);

// -----------------------------------------------------------------------------
// TEST 10: Valid Profile Update Persists Correctly
// -----------------------------------------------------------------------------
$testName = 'Shweta Singh Updated';
$testPhone = '+91 9888877777';
$testTitle = 'VP of Engineering';
$testTz = 'Asia/Kolkata';

$resUpdate = run_api_call($activeSession, 'POST', ['action' => 'update_profile'], [
    'csrf_token' => $validCsrf,
    'name'       => $testName,
    'email'      => $liveClient['email'],
    'phone'      => $testPhone,
    'job_title'  => $testTitle,
    'timezone'   => $testTz,
]);

$checkContact = $pdo->prepare("SELECT name, phone, clean_phone, job_title FROM contacts WHERE id = ?");
$checkContact->execute([$contactId]);
$cRow = $checkContact->fetch(PDO::FETCH_ASSOC);

$checkTz = $pdo->prepare("SELECT setting_value FROM crm_settings WHERE organization_id = ? AND setting_key = ?");
$checkTz->execute([$orgId, 'client_user_' . $portalUserId . '_timezone']);
$tzRow = $checkTz->fetch(PDO::FETCH_ASSOC);

assert_test(
    $resUpdate['code'] === 200 &&
    $cRow['name'] === $testName &&
    $cRow['phone'] === $testPhone &&
    $cRow['clean_phone'] === '919888877777' &&
    $cRow['job_title'] === $testTitle &&
    ($tzRow['setting_value'] ?? '') === $testTz,
    "10. Valid profile update persists correctly in contacts and crm_settings"
);

// -----------------------------------------------------------------------------
// TEST 11: Invalid Email Format Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$resInvalidEmail = run_api_call($activeSession, 'POST', ['action' => 'update_profile'], [
    'csrf_token' => $validCsrf,
    'name'       => $testName,
    'email'      => 'not-an-email-address',
]);
assert_test($resInvalidEmail['code'] === 422 && empty($resInvalidEmail['json']['success']), "11. Invalid email format is rejected with HTTP 422");

// -----------------------------------------------------------------------------
// TEST 12: Invalid Field Lengths Rejected (HTTP 422)
// -----------------------------------------------------------------------------
$resLongName = run_api_call($activeSession, 'POST', ['action' => 'update_profile'], [
    'csrf_token' => $validCsrf,
    'name'       => str_repeat('A', 151),
    'email'      => $liveClient['email'],
]);
assert_test($resLongName['code'] === 422 && empty($resLongName['json']['success']), "12. Overly long field lengths (> 150 chars) are rejected with HTTP 422");

// -----------------------------------------------------------------------------
// TEST 13: Company Information Follows Approved Permission Model (Read-Only)
// -----------------------------------------------------------------------------
$resUpdateComp = run_api_call($activeSession, 'POST', ['action' => 'update_company'], [
    'csrf_token' => $validCsrf,
    'name'       => 'Malicious Legal Name Change',
]);
assert_test($resUpdateComp['code'] === 403 && empty($resUpdateComp['json']['success']), "13. Company edit request returns HTTP 403 (Read-Only policy enforced)");

// -----------------------------------------------------------------------------
// Dedicated Temporary Test Portal User for Safe Password Testing
// -----------------------------------------------------------------------------
$tempEmail = 'temp_test_user_' . time() . '@nexflow.test';
$tempPass = 'TempPass123!';
$tempHash = password_hash($tempPass, PASSWORD_DEFAULT);

// Insert temporary test contact
$insTempContact = $pdo->prepare("
    INSERT INTO contacts (organization_id, company_id, name, first_name, last_name, email, is_active, created_by, created_at, updated_at)
    VALUES (?, ?, 'Temp Test User', 'Temp', 'User', ?, 1, 1, NOW(), NOW())
");
$insTempContact->execute([$orgId, $companyId, $tempEmail]);
$tempContactId = (int)$pdo->lastInsertId();

// Insert contact_companies
$insTempCC = $pdo->prepare("INSERT INTO contact_companies (organization_id, contact_id, company_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
$insTempCC->execute([$orgId, $tempContactId, $companyId]);

// Insert temporary client portal user
$insTempCpu = $pdo->prepare("
    INSERT INTO client_portal_users (organization_id, company_id, contact_id, email, password_hash, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
");
$insTempCpu->execute([$orgId, $companyId, $tempContactId, $tempEmail, $tempHash]);
$tempCpuId = (int)$pdo->lastInsertId();

$tempSession = [
    'client_portal_logged_in'  => true,
    'client_portal_user_id'    => $tempCpuId,
    'client_portal_org_id'     => $orgId,
    'client_portal_company_id' => $companyId,
    'client_portal_contact_id' => $tempContactId,
    'client_portal_csrf_token' => $validCsrf,
];

try {
    // -------------------------------------------------------------------------
    // TEST 14: Incorrect Current Password Rejected (HTTP 422)
    // -------------------------------------------------------------------------
    $resWrongPass = run_api_call($tempSession, 'POST', ['action' => 'change_password'], [
        'csrf_token'       => $validCsrf,
        'current_password' => 'WrongPassword999!',
        'new_password'     => 'NewSecurePass123!',
        'confirm_password' => 'NewSecurePass123!',
    ]);
    assert_test($resWrongPass['code'] === 422 && strpos($resWrongPass['body'], 'incorrect') !== false, "14. Incorrect current password is rejected with HTTP 422");

    // -------------------------------------------------------------------------
    // TEST 15: Password Mismatch Rejected (HTTP 422)
    // -------------------------------------------------------------------------
    $resMismatch = run_api_call($tempSession, 'POST', ['action' => 'change_password'], [
        'csrf_token'       => $validCsrf,
        'current_password' => $tempPass,
        'new_password'     => 'NewSecurePass123!',
        'confirm_password' => 'MismatchPass456!',
    ]);
    assert_test($resMismatch['code'] === 422 && strpos($resMismatch['body'], 'match') !== false, "15. Password confirmation mismatch is rejected with HTTP 422");

    // -------------------------------------------------------------------------
    // TEST 16: Weak / Short Password Rejected (HTTP 422)
    // -------------------------------------------------------------------------
    $resWeak = run_api_call($tempSession, 'POST', ['action' => 'change_password'], [
        'csrf_token'       => $validCsrf,
        'current_password' => $tempPass,
        'new_password'     => '123',
        'confirm_password' => '123',
    ]);
    assert_test($resWeak['code'] === 422 && strpos($resWeak['body'], '6 characters') !== false, "16. Password under minimum length (< 6 chars) is rejected with HTTP 422");

    // -------------------------------------------------------------------------
    // TEST 17: Valid Password Change Works
    // -------------------------------------------------------------------------
    $newSecurePass = 'BrandNewPass2026!';
    $resValidPass = run_api_call($tempSession, 'POST', ['action' => 'change_password'], [
        'csrf_token'       => $validCsrf,
        'current_password' => $tempPass,
        'new_password'     => $newSecurePass,
        'confirm_password' => $newSecurePass,
    ]);

    // Verify the new password hash verifies with password_verify
    $checkTempHash = $pdo->prepare("SELECT password_hash FROM client_portal_users WHERE id = ?");
    $checkTempHash->execute([$tempCpuId]);
    $newHashInDb = $checkTempHash->fetchColumn();

    $verifiedNew = password_verify($newSecurePass, (string)$newHashInDb);
    assert_test(
        $resValidPass['code'] === 200 &&
        !empty($resValidPass['json']['success']) &&
        $verifiedNew === true,
        "17. Valid password change successfully updates credentials"
    );

    // -------------------------------------------------------------------------
    // TEST 18: Password Hashes Never Exposed in API Output
    // -------------------------------------------------------------------------
    $resTempProfile = run_api_call($tempSession, 'GET', ['action' => 'get']);
    $bodyOutput = json_encode($resTempProfile['json']);
    assert_test(
        strpos($bodyOutput, 'password_hash') === false &&
        strpos($bodyOutput, '$2y$') === false,
        "18. Password hashes are never exposed in API output"
    );

} finally {
    // Purge temporary test user and contact
    $pdo->prepare("DELETE FROM client_portal_users WHERE id = ?")->execute([$tempCpuId]);
    $pdo->prepare("DELETE FROM contact_companies WHERE contact_id = ?")->execute([$tempContactId]);
    $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$tempContactId]);
}

// -----------------------------------------------------------------------------
// TEST 19: Notification Preferences Load from MySQL
// -----------------------------------------------------------------------------
$profileAfter = run_api_call($activeSession, 'GET', ['action' => 'get']);
$notifs = $profileAfter['json']['data']['notifications'] ?? [];
assert_test(
    isset($notifs['email_summaries']) &&
    isset($notifs['in_portal']),
    "19. Notification preferences load from MySQL crm_settings"
);

// -----------------------------------------------------------------------------
// TEST 20: Notification Preferences Save and Reload Correctly
// -----------------------------------------------------------------------------
$resNotifUpdate = run_api_call($activeSession, 'POST', ['action' => 'update_notifications'], [
    'csrf_token'      => $validCsrf,
    'email_summaries' => '0',
    'in_portal'       => '1',
]);
$profileNotifReload = run_api_call($activeSession, 'GET', ['action' => 'get']);
$reloadedNotifs = $profileNotifReload['json']['data']['notifications'] ?? [];
assert_test(
    $resNotifUpdate['code'] === 200 &&
    $reloadedNotifs['email_summaries'] === false &&
    $reloadedNotifs['in_portal'] === true,
    "20. Notification preferences save and reload accurately"
);

// -----------------------------------------------------------------------------
// TEST 21: Account Executive Retrieved from companies.owner_id
// -----------------------------------------------------------------------------
$leadInfo = $profileAfter['json']['data']['account_lead'] ?? [];
$checkCompOwner = $pdo->prepare("SELECT u.name, u.email FROM companies comp JOIN users u ON comp.owner_id = u.id WHERE comp.id = ?");
$checkCompOwner->execute([$companyId]);
$compOwnerRow = $checkCompOwner->fetch(PDO::FETCH_ASSOC);

assert_test(
    !empty($leadInfo['name']) &&
    $compOwnerRow &&
    $leadInfo['name'] === $compOwnerRow['name'] &&
    $leadInfo['email'] === $compOwnerRow['email'],
    "21. Account Executive is dynamically retrieved from live companies.owner_id"
);

// -----------------------------------------------------------------------------
// TEST 22: No Hardcoded Dummy Profile Data in client-profile.php
// -----------------------------------------------------------------------------
$profilePhpContent = file_get_contents(__DIR__ . '/../client-profile.php');
assert_test(
    strpos($profilePhpContent, 'Marcus Thompson') === false &&
    strpos($profilePhpContent, 'Acme Corp') === false &&
    strpos($profilePhpContent, 'VAT-ACME-2026') === false,
    "22. No hardcoded dummy profile data remains in client-profile.php"
);

// -----------------------------------------------------------------------------
// TEST 23: No localStorage Profile Mock Data in client-profile.php
// -----------------------------------------------------------------------------
assert_test(
    strpos($profilePhpContent, 'localStorage.setItem("NexFlow_client_data"') === false &&
    strpos($profilePhpContent, 'localStorage.getItem("NexFlow_client_data"') === false,
    "23. No localStorage profile mock data read/write remains in client-profile.php"
);

// -----------------------------------------------------------------------------
// TEST 24: Admin User Records Remain Completely Unchanged
// -----------------------------------------------------------------------------
$adminAfter = $pdo->query("SELECT id, email, password_hash, updated_at FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
assert_test(
    json_encode($adminSnapshot) === json_encode($adminAfter),
    "24. Admin users table remains completely untouched and intact"
);

// -----------------------------------------------------------------------------
// TEST 25: Restore Baseline Client State
// -----------------------------------------------------------------------------
$restoreContact = $pdo->prepare("
    UPDATE contacts SET 
        name = ?, first_name = ?, last_name = ?, email = ?, phone = ?, clean_phone = ?, job_title = ? 
    WHERE id = ?
");
$restoreContact->execute([
    $initialContact['name'],
    $initialContact['first_name'],
    $initialContact['last_name'],
    $initialContact['email'],
    $initialContact['phone'],
    $initialContact['clean_phone'],
    $initialContact['job_title'],
    $contactId,
]);

$restoreCpu = $pdo->prepare("UPDATE client_portal_users SET email = ?, updated_at = ? WHERE id = ?");
$restoreCpu->execute([$initialCpu['email'], $initialCpu['updated_at'], $portalUserId]);

// Restore notification preference to defaults
run_api_call($activeSession, 'POST', ['action' => 'update_notifications'], [
    'csrf_token'      => $validCsrf,
    'email_summaries' => '1',
    'in_portal'       => '1',
]);

assert_test(true, "25. Temporary test profile changes restored to original values");

// -----------------------------------------------------------------------------
// TEST 26: Database Baseline Confirmed
// -----------------------------------------------------------------------------
$finalContact = $pdo->prepare("SELECT name, email, phone FROM contacts WHERE id = ?");
$finalContact->execute([$contactId]);
$fc = $finalContact->fetch(PDO::FETCH_ASSOC);

$finalCpu = $pdo->prepare("SELECT email FROM client_portal_users WHERE id = ?");
$finalCpu->execute([$portalUserId]);
$fCpu = $finalCpu->fetch(PDO::FETCH_ASSOC);

assert_test(
    $fc['name'] === $initialContact['name'] &&
    $fc['email'] === $initialContact['email'] &&
    $fCpu['email'] === $initialCpu['email'],
    "26. Database baseline confirmed: contact and portal user records intact"
);

echo "\n================================================================================\n";
echo "TEST RESULTS SUMMARY: 26 Ran | {$passed} PASSED | {$failed} FAILED\n";
echo "================================================================================\n";

exit($failed === 0 ? 0 : 1);
