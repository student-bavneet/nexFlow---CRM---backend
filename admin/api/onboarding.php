<?php
/**
 * NexFlow CRM - Onboarding API
 * GET  ?action=bootstrap  -> industries, modules and recommendations from DB
 * POST action=finish      -> creates the organization/admin and persists setup
 */

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function api_response(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-') ?: 'custom';
}

function normalize_json_array($value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || $value === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    api_response(false, 'Database connection failed. Please check config/database.php and make sure MySQL is running.', [], 500);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'bootstrap') {
    $industries = $pdo->query("SELECT id, `key`, name, icon, description, recommendation_title, recommendation_subtitle FROM industries WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();
    $modules = $pdo->query("SELECT id, `key`, name, icon, description FROM modules WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();

    $terms = $pdo->query("SELECT industry_id, leads_label, pipeline_label, contacts_label, companies_label, deals_label FROM industry_terms")->fetchAll();
    $termMap = [];
    foreach ($terms as $row) {
        $termMap[(int)$row['industry_id']] = [
            'leads' => $row['leads_label'],
            'pipeline' => $row['pipeline_label'],
            'contacts' => $row['contacts_label'],
            'companies' => $row['companies_label'],
            'deals' => $row['deals_label'],
        ];
    }

    $stageRows = $pdo->query("SELECT industry_id, name, win_rate_pct, sort_order FROM industry_pipeline_stages ORDER BY industry_id, sort_order, id")->fetchAll();
    $stageMap = [];
    foreach ($stageRows as $row) {
        $stageMap[(int)$row['industry_id']][] = [
            'name' => $row['name'],
            'pct' => (int)$row['win_rate_pct'],
        ];
    }

    $defaultRows = $pdo->query("SELECT im.industry_id, m.`key` AS module_key FROM industry_modules im JOIN modules m ON m.id = im.module_id WHERE im.is_default = 1 ORDER BY im.sort_order, im.id")->fetchAll();
    $moduleMap = [];
    foreach ($defaultRows as $row) {
        $moduleMap[(int)$row['industry_id']][] = $row['module_key'];
    }

    $recommendations = [];
    foreach ($industries as $industry) {
        $id = (int)$industry['id'];
        $recommendations[$industry['key']] = [
            'title' => $industry['recommendation_title'] ?? ('Recommended Setup for ' . $industry['name']),
            'sub' => $industry['recommendation_subtitle'] ?? ('Pre-configured for ' . $industry['name'] . '.'),
            'modules' => $moduleMap[$id] ?? [],
            'terms' => $termMap[$id] ?? [
                'leads' => 'Leads', 'pipeline' => 'Sales Pipeline', 'contacts' => 'Contacts', 'companies' => 'Companies', 'deals' => 'Deals'
            ],
            'stages' => $stageMap[$id] ?? [],
        ];
    }

    $installed = (bool)$pdo->query("SELECT id FROM organizations WHERE setup_completed = 1 LIMIT 1")->fetchColumn();
    api_response(true, 'Onboarding configuration loaded.', [
        'installed' => $installed,
        'industries' => $industries,
        'modules' => $modules,
        'recommendations' => $recommendations,
    ]);
}

if ($method === 'POST' && $action === 'finish') {
    // This is intentionally a one-time installation flow for the current NexFlow build.
    $installed = (bool)$pdo->query("SELECT id FROM organizations WHERE setup_completed = 1 LIMIT 1")->fetchColumn();
    if ($installed) {
        api_response(false, 'NexFlow is already configured. Please sign in with your administrator credentials.', [], 409);
    }

    $payloadRaw = $_POST['payload'] ?? '';
    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) {
        api_response(false, 'Invalid onboarding data received.', [], 422);
    }

    $business = $payload['business'] ?? [];
    $admin = $payload['admin'] ?? [];
    $selectedIndustry = trim((string)($payload['selectedIndustry'] ?? ''));
    $customIndustryName = trim((string)($payload['customIndustryName'] ?? ''));
    $selectedModules = normalize_json_array($payload['selectedModules'] ?? []);
    $customModules = normalize_json_array($payload['customModules'] ?? []);
    $pipelineStages = normalize_json_array($payload['pipelineStages'] ?? []);
    $customFields = normalize_json_array($payload['customFields'] ?? []);
    $terminology = $payload['terminology'] ?? [];

    $businessName = trim((string)($business['name'] ?? ''));
    $businessEmail = trim((string)($business['email'] ?? ''));
    $businessCountry = trim((string)($business['country'] ?? ''));
    $businessCity = trim((string)($business['city'] ?? ''));
    $businessCurrency = trim((string)($business['currency'] ?? ''));
    $businessTimezone = trim((string)($business['timezone'] ?? ''));
    $businessWebsite = trim((string)($business['website'] ?? ''));

    $adminName = trim((string)($admin['name'] ?? ''));
    $adminEmail = trim((string)($admin['email'] ?? ''));
    $adminPassword = (string)($admin['password'] ?? '');
    $adminPasswordConfirm = (string)($admin['password_confirm'] ?? '');

    if ($businessName === '') api_response(false, 'Business name is required.', [], 422);
    if ($businessEmail === '' || !filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) api_response(false, 'Please enter a valid business email address.', [], 422);
    if ($businessCountry === '') api_response(false, 'Country is required.', [], 422);
    if ($businessCity === '') api_response(false, 'City is required.', [], 422);
    if ($businessCurrency === '') api_response(false, 'Currency is required.', [], 422);
    if ($businessTimezone === '') api_response(false, 'Time zone is required.', [], 422);
    if ($businessWebsite !== '') {
        $prepUrl = (strpos($businessWebsite, 'http://') === 0 || strpos($businessWebsite, 'https://') === 0) ? $businessWebsite : 'https://' . $businessWebsite;
        if (!filter_var($prepUrl, FILTER_VALIDATE_URL)) {
            api_response(false, 'Please enter a valid website URL.', [], 422);
        }
    }

    if ($adminName === '') api_response(false, 'Administrator name is required.', [], 422);
    if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) api_response(false, 'Please enter a valid administrator email address.', [], 422);
    if (strlen($adminPassword) < 8) api_response(false, 'Password must be at least 8 characters.', [], 422);
    if ($adminPassword !== $adminPasswordConfirm) api_response(false, 'Passwords do not match.', [], 422);
    if ($selectedIndustry === '') api_response(false, 'Please select your industry.', [], 422);
    if (count($selectedModules) === 0) api_response(false, 'Please select at least one module.', [], 422);

    $industryStmt = $pdo->prepare("SELECT * FROM industries WHERE `key` = ? AND is_active = 1 LIMIT 1");
    $industryStmt->execute([$selectedIndustry]);
    $industry = $industryStmt->fetch();
    if (!$industry) api_response(false, 'Selected industry was not found.', [], 422);

    if ($selectedIndustry === 'other') {
        if ($customIndustryName === '') api_response(false, 'Please enter your custom industry name.', [], 422);
        $industryName = $customIndustryName;
    } else {
        $industryName = $industry['name'];
    }

    $moduleKeys = array_values(array_unique(array_map('strval', $selectedModules)));
    $placeholders = implode(',', array_fill(0, count($moduleKeys), '?'));
    $moduleStmt = $pdo->prepare("SELECT id, `key` FROM modules WHERE `key` IN ($placeholders) AND is_active = 1");
    $moduleStmt->execute($moduleKeys);
    $moduleRows = $moduleStmt->fetchAll();
    $validModules = [];
    foreach ($moduleRows as $m) $validModules[$m['key']] = (int)$m['id'];

    $logoPath = null;
    $pdo->beginTransaction();

    try {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['logo'];
            if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Logo upload failed.');
            if ((int)$file['size'] > 2 * 1024 * 1024) throw new RuntimeException('Logo size must be 2 MB or less.');

            $original = $file['name'];
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg'];
            if (!in_array($ext, $allowed, true)) throw new RuntimeException('Please upload a valid logo file. Allowed formats: JPG, JPEG, PNG, SVG.');

            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) finfo_close($finfo);
            $allowedMime = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'text/xml', 'application/xml'];
            if ($mime && !in_array($mime, $allowedMime, true)) throw new RuntimeException('Please upload a valid logo file. Allowed formats: JPG, JPEG, PNG, SVG.');

            $uploadDir = __DIR__ . '/../../uploads/logos';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) throw new RuntimeException('Could not create logo upload directory.');
            $fileName = 'logo_' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $fileName)) {
                throw new RuntimeException('Could not save the uploaded logo.');
            }
            $logoPath = 'uploads/logos/' . $fileName;
        }

        $slugBase = slugify($businessName);
        $slug = $slugBase;
        $suffix = 2;
        while (true) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE slug = ?");
            $check->execute([$slug]);
            if ((int)$check->fetchColumn() === 0) break;
            $slug = $slugBase . '-' . $suffix++;
        }

        $orgStmt = $pdo->prepare("INSERT INTO organizations (name, slug, business_email, phone, website, country, state_region, city, postal_code, street_address, currency, timezone, logo_path, industry_id, industry_name, setup_completed, setup_completed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())");
        $orgStmt->execute([
            $businessName,
            $slug,
            $businessEmail,
            trim((string)($business['phone'] ?? '')) ?: null,
            trim((string)($business['website'] ?? '')) ?: null,
            $businessCountry ?: null,
            trim((string)($business['state'] ?? '')) ?: null,
            $businessCity ?: null,
            trim((string)($business['zip'] ?? '')) ?: null,
            trim((string)($business['address'] ?? '')) ?: null,
            $businessCurrency ?: 'USD ($)',
            $businessTimezone ?: 'UTC',
            $logoPath,
            (int)$industry['id'],
            $industryName,
        ]);
        $organizationId = (int)$pdo->lastInsertId();

        $userStmt = $pdo->prepare("INSERT INTO users (organization_id, name, email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'admin', 'active', NOW(), NOW())");
        $userStmt->execute([$organizationId, $adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();

        $settingsStmt = $pdo->prepare("INSERT INTO organization_settings (organization_id, terminology_json, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $settingsStmt->execute([$organizationId, json_encode([
            'leads' => trim((string)($terminology['leads'] ?? 'Leads')),
            'pipeline' => trim((string)($terminology['pipeline'] ?? 'Sales Pipeline')),
            'contacts' => trim((string)($terminology['contacts'] ?? 'Contacts')),
            'companies' => trim((string)($terminology['companies'] ?? 'Companies')),
            'deals' => trim((string)($terminology['deals'] ?? 'Deals')),
        ], JSON_UNESCAPED_UNICODE)]);

        $orgModuleStmt = $pdo->prepare("INSERT INTO organization_modules (organization_id, module_id, module_key, enabled, is_custom, custom_name, custom_description, custom_icon, created_at, updated_at) VALUES (?, ?, ?, 1, 0, NULL, NULL, NULL, NOW(), NOW())");
        foreach ($validModules as $key => $moduleId) {
            $orgModuleStmt->execute([$organizationId, $moduleId, $key]);
        }

        $customModuleStmt = $pdo->prepare("INSERT INTO organization_modules (organization_id, module_id, module_key, enabled, is_custom, custom_name, custom_description, custom_icon, created_at, updated_at) VALUES (?, NULL, ?, 1, 1, ?, ?, ?, NOW(), NOW())");
        foreach ($customModules as $custom) {
            $name = trim((string)($custom['name'] ?? ''));
            if ($name === '') continue;
            $key = 'custom_' . bin2hex(random_bytes(6));
            $customModuleStmt->execute([
                $organizationId,
                $key,
                $name,
                trim((string)($custom['desc'] ?? 'Custom module created for your business')),
                trim((string)($custom['icon'] ?? '✨')),
            ]);
        }

        $stageStmt = $pdo->prepare("INSERT INTO pipeline_stages (organization_id, name, sort_order, win_rate_pct, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
        $sort = 1;
        foreach ($pipelineStages as $stage) {
            $name = trim((string)($stage['name'] ?? ''));
            if ($name === '') continue;
            $pct = max(0, min(100, (int)($stage['pct'] ?? ($sort * 20))));
            $stageStmt->execute([$organizationId, $name, $sort++, $pct]);
        }

        $fieldStmt = $pdo->prepare("INSERT INTO custom_fields (organization_id, object_name, field_name, field_type, is_required, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        foreach ($customFields as $field) {
            $fieldName = trim((string)($field['name'] ?? ''));
            if ($fieldName === '') continue;
            $fieldStmt->execute([
                $organizationId,
                trim((string)($field['object'] ?? 'Leads')),
                $fieldName,
                trim((string)($field['type'] ?? 'Short Text')),
                !empty($field['req']) ? 1 : 0,
            ]);
        }

        $pdo->commit();

        // Create a real PHP session immediately after setup, like an installer that logs you in.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['organization_id'] = $organizationId;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();

        api_response(true, 'NexFlow setup completed successfully.', [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'redirect' => 'index.php',
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($logoPath && file_exists(__DIR__ . '/../../' . $logoPath)) {
            @unlink(__DIR__ . '/../../' . $logoPath);
        }
        api_response(false, $e->getMessage(), [], 500);
    }
}

api_response(false, 'Invalid onboarding request.', [], 400);
