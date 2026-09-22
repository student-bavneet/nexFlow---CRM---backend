<?php
/**
 * Comprehensive Automated Verification for Client Portal Authentication & Root Routing
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';

$baseUrl = 'http://127.0.0.1/nexFlow';
$testsPassed = 0;
$testsFailed = 0;

function runTest(string $name, bool $result, string $details = ''): void {
    global $testsPassed, $testsFailed;
    if ($result) {
        $testsPassed++;
        echo "[PASS] $name\n";
        if ($details) echo "       $details\n";
    } else {
        $testsFailed++;
        echo "[FAIL] $name\n";
        if ($details) echo "       ERROR: $details\n";
    }
}

function curlReq(string $url, array $options = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if (isset($options['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
    }
    if (isset($options['cookie'])) {
        curl_setopt($ch, CURLOPT_COOKIE, $options['cookie']);
    }
    if (isset($options['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post']);
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $headerStr = (string)substr((string)$response, 0, $headerSize);
    $body = (string)substr((string)$response, $headerSize);

    // Extract headers
    $headers = [];
    foreach (explode("\r\n", $headerStr) as $line) {
        if (strpos($line, ': ') !== false) {
            [$k, $v] = explode(': ', $line, 2);
            $headers[strtolower($k)] = $v;
        }
    }

    // Extract Set-Cookie
    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headerStr, $cookieMatches);
    $cookies = $cookieMatches[1] ?? [];

    return [
        'code' => $httpCode,
        'headers' => $headers,
        'headerStr' => $headerStr,
        'body' => $body,
        'cookies' => $cookies,
        'error' => $err
    ];
}

echo "=== NexFlow Client Portal Authentication & Root Routing Verification ===\n\n";

// -----------------------------------------------------------------------------
// 1. Unit Tests: sanitize_client_return_url()
// -----------------------------------------------------------------------------
$badUrls = [
    'https://evil.com',
    'http://evil.com',
    '//evil.com/test',
    '/\\evil.com',
    'javascript:alert(1)',
    'data:text/html,bad',
    'vbscript:msgbox(1)',
    'admin/index.php',
    'admin/admin-login.php',
    'login.php',
    'index.php',
    'client-login.php',
    '../client-dashboard.php',
    'client-nonexistent.php',
    '',
    null,
    "client-dashboard.php\r\nSet-Cookie: evil",
    "http://localhost/nexFlow/client-documents.php"
];

$allBadRejected = true;
foreach ($badUrls as $bad) {
    $sanitized = sanitize_client_return_url($bad);
    if ($sanitized !== null) {
        $allBadRejected = false;
        echo "Bad URL was not rejected: " . var_export($bad, true) . " -> $sanitized\n";
    }
}
runTest("Return URL Unit: Reject unsafe, external, loop, and forbidden return URLs", $allBadRejected);

$validUrls = [
    'client-dashboard.php' => 'client-dashboard.php',
    'client-projects.php' => 'client-projects.php',
    'client-deals.php' => 'client-deals.php',
    'client-proposals.php' => 'client-proposals.php',
    'client-estimates.php' => 'client-estimates.php',
    'client-contracts.php' => 'client-contracts.php',
    'client-invoices.php' => 'client-invoices.php',
    'client-tasks.php' => 'client-tasks.php',
    'client-calendar.php' => 'client-calendar.php',
    'client-messages.php' => 'client-messages.php',
    'client-documents.php' => 'client-documents.php',
    'client-profile.php' => 'client-profile.php',
    'client-help.php' => 'client-help.php',
    'client-invoices.php?id=5&status=paid' => 'client-invoices.php?id=5&status=paid',
    '/nexFlow/client-documents.php' => 'client-documents.php',
    '/nexFlow/client-tasks.php?view=list' => 'client-tasks.php?view=list',
];

$allValidAccepted = true;
foreach ($validUrls as $input => $expected) {
    $sanitized = sanitize_client_return_url($input);
    if ($sanitized !== $expected) {
        $allValidAccepted = false;
        echo "Valid URL mismatch: input='$input', expected='$expected', got=" . var_export($sanitized, true) . "\n";
    }
}
runTest("Return URL Unit: Allow safe client portal pages and relative paths", $allValidAccepted);

// -----------------------------------------------------------------------------
// 2. HTTP: Root Route Unauthenticated
// -----------------------------------------------------------------------------
$resRoot = curlReq("$baseUrl/");
$rootLocation = $resRoot['headers']['location'] ?? '';
runTest("Root Route: Unauthenticated visitor to '/' redirects to login.php", 
    $resRoot['code'] === 302 && $rootLocation === 'login.php', 
    "Code: {$resRoot['code']}, Location: $rootLocation");

$resIndex = curlReq("$baseUrl/index.php");
$indexLocation = $resIndex['headers']['location'] ?? '';
runTest("Root Route: index.php unauthenticated redirects to login.php", 
    $resIndex['code'] === 302 && $indexLocation === 'login.php', 
    "Code: {$resIndex['code']}, Location: $indexLocation");

// -----------------------------------------------------------------------------
// 3. HTTP: Bogus Admin PHPSESSID cookie fallback
// -----------------------------------------------------------------------------
$resBogusAdmin = curlReq("$baseUrl/", [
    'cookie' => 'PHPSESSID=bogus_session_id_12345'
]);
$bogusLocation = $resBogusAdmin['headers']['location'] ?? '';
runTest("Root Route: Stale/Bogus PHPSESSID does NOT redirect to Admin; safely falls back to login.php",
    $resBogusAdmin['code'] === 302 && $bogusLocation === 'login.php',
    "Code: {$resBogusAdmin['code']}, Location: $bogusLocation");

// -----------------------------------------------------------------------------
// 4. HTTP: All 13 Client Portal Protected Pages Redirect Unauthenticated Users
// -----------------------------------------------------------------------------
$portalPages = [
    'client-calendar.php',
    'client-contracts.php',
    'client-dashboard.php',
    'client-deals.php',
    'client-documents.php',
    'client-estimates.php',
    'client-help.php',
    'client-invoices.php',
    'client-messages.php',
    'client-profile.php',
    'client-projects.php',
    'client-proposals.php',
    'client-tasks.php',
];

$allPagesRedirect = true;
$cacheHeadersOk = true;
foreach ($portalPages as $page) {
    $res = curlReq("$baseUrl/$page");
    $loc = $res['headers']['location'] ?? '';
    $cacheControl = $res['headers']['cache-control'] ?? '';

    $expectedLoc = "login.php?return_to=" . urlencode($page);
    if ($res['code'] !== 302 || $loc !== $expectedLoc) {
        $allPagesRedirect = false;
        echo "Failed redirect for $page: code={$res['code']}, loc=$loc, expected=$expectedLoc\n";
    }
    if (strpos($cacheControl, 'no-store') === false) {
        $cacheHeadersOk = false;
        echo "Missing no-store cache header for $page: $cacheControl\n";
    }
}
runTest("Security Check: All 13 Client Portal pages redirect unauthenticated users with return_to", $allPagesRedirect);
runTest("Security Check: Unauthenticated redirects include anti-caching headers (no-store)", $cacheHeadersOk);

// -----------------------------------------------------------------------------
// 5. HTTP: Sensitive file & directory protection (.htaccess)
// -----------------------------------------------------------------------------
$resSql = curlReq("$baseUrl/database.sql");
runTest("Apache Security: database.sql direct access denied (403)", 
    $resSql['code'] === 403, 
    "Code: {$resSql['code']}");

$resConfig = curlReq("$baseUrl/config/database.php");
runTest("Apache Security: config/database.php direct access denied (403)", 
    $resConfig['code'] === 403, 
    "Code: {$resConfig['code']}");

$resIncludes = curlReq("$baseUrl/includes/client-auth.php");
runTest("Apache Security: includes/client-auth.php direct access denied (403)", 
    $resIncludes['code'] === 403, 
    "Code: {$resIncludes['code']}");

$resScratch = curlReq("$baseUrl/scratch/test_client_auth_routing.php");
runTest("Apache Security: scratch/ directory direct web access denied (403)", 
    $resScratch['code'] === 403, 
    "Code: {$resScratch['code']}");

// -----------------------------------------------------------------------------
// 6. HTTP: Client Authentication Flow with Safe Password Preservation
// -----------------------------------------------------------------------------
$pdo = nexflow_db();
$originalHash = $pdo->query("SELECT password_hash FROM client_portal_users WHERE id = 1")->fetchColumn();

// Temporarily set a known test password hash for client user #1
$testPlainPass = 'ClientAuthTest2026!';
$tempTestHash = password_hash($testPlainPass, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE client_portal_users SET password_hash = ? WHERE id = 1")->execute([$tempTestHash]);

try {
    $loginPayload = json_encode([
        'email' => 'shweta@gmail.com',
        'password' => $testPlainPass,
        'return_to' => 'client-documents.php'
    ]);

    $resLogin = curlReq("$baseUrl/api/client-auth.php?action=login", [
        'headers' => ['Content-Type: application/json'],
        'post' => $loginPayload
    ]);

    $loginData = json_decode($resLogin['body'], true);
    $loginSuccess = ($resLogin['code'] === 200 && ($loginData['success'] ?? false) === true);
    $returnedRedirect = $loginData['data']['redirect'] ?? '';
    runTest("Client API Login: Authenticates active user shweta@gmail.com", 
        $loginSuccess, 
        "Code: {$resLogin['code']}, Body: {$resLogin['body']}");
    runTest("Client API Login: Returns safe return_to redirect ('client-documents.php')", 
        $returnedRedirect === 'client-documents.php', 
        "Redirect returned: $returnedRedirect");

    // Extract client session cookie (last cookie emitted is after regeneration)
    $clientCookie = '';
    foreach ($resLogin['cookies'] as $c) {
        if (strpos($c, 'nexflow_client_portal=') !== false) {
            $clientCookie = $c;
        }
    }
    runTest("Client API Login: Issues dedicated nexflow_client_portal cookie", !empty($clientCookie), "Cookie: $clientCookie");

    // -----------------------------------------------------------------------------
    // 7. Authenticated Client Access
    // -----------------------------------------------------------------------------
    // A. Protected page with cookie returns 200
    $resDoc = curlReq("$baseUrl/client-documents.php", ['cookie' => $clientCookie]);
    runTest("Client Auth Session: client-documents.php returns HTTP 200 for authenticated user", 
        $resDoc['code'] === 200, 
        "Code: {$resDoc['code']}");

    // B. Root route with client cookie redirects to client-dashboard.php
    $resAuthRoot = curlReq("$baseUrl/", ['cookie' => $clientCookie]);
    $authRootLoc = $resAuthRoot['headers']['location'] ?? '';
    runTest("Client Auth Session: Root route '/' redirects authenticated client to client-dashboard.php", 
        $resAuthRoot['code'] === 302 && $authRootLoc === 'client-dashboard.php', 
        "Code: {$resAuthRoot['code']}, Loc: $authRootLoc");

    // C. login.php with client cookie redirects to client-dashboard.php
    $resAuthLogin = curlReq("$baseUrl/login.php", ['cookie' => $clientCookie]);
    $authLoginLoc = $resAuthLogin['headers']['location'] ?? '';
    runTest("Client Auth Session: login.php redirects authenticated client to client-dashboard.php", 
        $resAuthLogin['code'] === 302 && $authLoginLoc === 'client-dashboard.php', 
        "Code: {$resAuthLogin['code']}, Loc: $authLoginLoc");

    // D. login.php?return_to=client-invoices.php redirects to client-invoices.php
    $resAuthLoginReturn = curlReq("$baseUrl/login.php?return_to=client-invoices.php", ['cookie' => $clientCookie]);
    $authLoginReturnLoc = $resAuthLoginReturn['headers']['location'] ?? '';
    runTest("Client Auth Session: login.php with safe return_to redirects to client-invoices.php", 
        $resAuthLoginReturn['code'] === 302 && $authLoginReturnLoc === 'client-invoices.php', 
        "Code: {$resAuthLoginReturn['code']}, Loc: $authLoginReturnLoc");

    // E. login.php?return_to=//evil.com redirects to client-dashboard.php (open redirect blocked)
    $resAuthEvil = curlReq("$baseUrl/login.php?return_to=%2F%2Fevil.com", ['cookie' => $clientCookie]);
    $authEvilLoc = $resAuthEvil['headers']['location'] ?? '';
    runTest("Client Auth Session: login.php with open-redirect payload defaults to client-dashboard.php", 
        $resAuthEvil['code'] === 302 && $authEvilLoc === 'client-dashboard.php', 
        "Code: {$resAuthEvil['code']}, Loc: $authEvilLoc");

    // -----------------------------------------------------------------------------
    // 8. Client Logout
    // -----------------------------------------------------------------------------
    $resLogout = curlReq("$baseUrl/logout.php", ['cookie' => $clientCookie]);
    $logoutLoc = $resLogout['headers']['location'] ?? '';
    runTest("Client Logout: logout.php redirects to login.php", 
        $resLogout['code'] === 302 && $logoutLoc === 'login.php', 
        "Code: {$resLogout['code']}, Loc: $logoutLoc");

    // Verify session destroyed after logout
    $resAfterLogout = curlReq("$baseUrl/client-dashboard.php", ['cookie' => $clientCookie]);
    runTest("Client Logout: Session invalidated, client-dashboard.php redirects to login.php", 
        $resAfterLogout['code'] === 302 && strpos($resAfterLogout['headers']['location'] ?? '', 'login.php') !== false, 
        "Code: {$resAfterLogout['code']}, Loc: " . ($resAfterLogout['headers']['location'] ?? ''));

} finally {
    // Restore original client password hash in database
    $pdo->prepare("UPDATE client_portal_users SET password_hash = ? WHERE id = 1")->execute([$originalHash]);
}

// -----------------------------------------------------------------------------
// 9. Admin Routes & Genuine Admin Session
// -----------------------------------------------------------------------------
// Verify Admin login page is accessible
$resAdminLogin = curlReq("$baseUrl/admin/admin-login.php");
runTest("Admin Route: admin/admin-login.php is accessible and untouched (200)", 
    $resAdminLogin['code'] === 200, 
    "Code: {$resAdminLogin['code']}");

// Test genuine admin login session via Admin API
$adminUser = $pdo->query("SELECT id, organization_id, email, password_hash, role FROM users WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($adminUser) {
    $origAdminHash = $adminUser['password_hash'];
    $tempAdminPass = 'AdminTest2026!';
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([
        password_hash($tempAdminPass, PASSWORD_DEFAULT),
        (int)$adminUser['id']
    ]);

    try {
        $resAdminAuth = curlReq("$baseUrl/admin/api/auth.php", [
            'headers' => ['Content-Type: application/json'],
            'post'    => json_encode([
                'action'   => 'login',
                'email'    => $adminUser['email'],
                'password' => $tempAdminPass
            ])
        ]);

        $adminAuthData = json_decode($resAdminAuth['body'], true);
        $adminLoginOk = ($resAdminAuth['code'] === 200 && ($adminAuthData['success'] ?? false) === true);
        runTest("Admin API Login: Authenticates active admin user ({$adminUser['email']})", 
            $adminLoginOk, 
            "Code: {$resAdminAuth['code']}, Body: {$resAdminAuth['body']}");

        // Extract regenerated PHPSESSID cookie
        $adminCookie = '';
        foreach ($resAdminAuth['cookies'] as $c) {
            if (strpos($c, 'PHPSESSID=') !== false) {
                $adminCookie = $c;
            }
        }

        $resAdminRoot = curlReq("$baseUrl/", [
            'cookie' => $adminCookie
        ]);
        $adminRootLoc = $resAdminRoot['headers']['location'] ?? '';
        runTest("Root Route: Genuine authenticated Admin session redirects to admin/index.php", 
            $resAdminRoot['code'] === 302 && $adminRootLoc === 'admin/index.php', 
            "Code: {$resAdminRoot['code']}, Loc: $adminRootLoc");

    } finally {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([
            $origAdminHash,
            (int)$adminUser['id']
        ]);
    }
} else {
    runTest("Root Route: Genuine authenticated Admin session redirects to admin/index.php", false, "No active admin user found in DB");
}

echo "\n======================================================\n";
echo "TEST RESULTS: $testsPassed PASSED | $testsFailed FAILED\n";
echo "======================================================\n";
exit($testsFailed > 0 ? 1 : 0);
