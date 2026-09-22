<?php
/**
 * NexFlow CRM - User Preferences API Endpoint
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function pref_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    pref_json(false, 'Unauthorized. Please sign in.', [], 401);
}

$userId = (int) $_SESSION['user_id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    pref_json(false, 'Database connection failed.', [], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $prefs = nexflow_user_preferences($userId);
    pref_json(true, 'Preferences retrieved successfully.', $prefs);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle Reset Action
    if ($action === 'reset') {
        $sql = "UPDATE user_preferences SET 
                    language = 'en-US',
                    timezone = 'America/Los_Angeles',
                    date_format = 'MMM DD, YYYY',
                    time_format = '12h',
                    week_starts_on = 'Sunday',
                    currency = 'USD ($)',
                    number_format = '1,234.56',
                    landing_page = 'dashboard',
                    table_rows = 25,
                    interface_density = 'comfortable',
                    updated_at = NOW()
                WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        $resetPrefs = nexflow_user_preferences($userId);
        pref_json(true, 'Preferences reset to default values.', $resetPrefs);
    }

    // Standard Save Action
    $language         = trim((string)($_POST['language'] ?? 'en-US'));
    $timezone         = trim((string)($_POST['timezone'] ?? 'America/Los_Angeles'));
    $dateFormat       = trim((string)($_POST['date_format'] ?? 'MMM DD, YYYY'));
    $timeFormat       = trim((string)($_POST['time_format'] ?? '12h'));
    $weekStartsOn     = trim((string)($_POST['week_starts_on'] ?? 'Sunday'));
    $currency         = trim((string)($_POST['currency'] ?? 'USD ($)'));
    $numberFormat     = trim((string)($_POST['number_format'] ?? '1,234.56'));
    $landingPage      = trim((string)($_POST['landing_page'] ?? 'dashboard'));
    $tableRows        = (int)($_POST['table_rows'] ?? 25);
    $interfaceDensity = trim((string)($_POST['interface_density'] ?? 'comfortable'));

    // Whitelist Validation Rules
    $allowedLanguages = ['en-US', 'en-GB', 'es', 'de', 'es-ES', 'fr-FR'];
    if (!in_array($language, $allowedLanguages, true)) {
        pref_json(false, 'Invalid language selection.', [], 422);
    }

    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true) && !in_array($timezone, ['America/Los_Angeles', 'America/New_York', 'Europe/London', 'Asia/Kolkata', 'Asia/Tokyo'], true)) {
        pref_json(false, 'Invalid timezone selection.', [], 422);
    }

    $allowedDateFormats = ['MMM DD, YYYY', 'YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY'];
    if (!in_array($dateFormat, $allowedDateFormats, true)) {
        pref_json(false, 'Invalid date format selection.', [], 422);
    }

    $allowedTimeFormats = ['12h', '24h', '12-hour', '24-hour'];
    if (!in_array($timeFormat, $allowedTimeFormats, true)) {
        pref_json(false, 'Invalid time format selection.', [], 422);
    }

    $allowedWeekStarts = ['Sunday', 'Monday'];
    if (!in_array($weekStartsOn, $allowedWeekStarts, true)) {
        pref_json(false, 'Invalid week start selection.', [], 422);
    }

    $allowedCurrencies = ['USD ($)', 'EUR (€)', 'GBP (£)', 'USD', 'EUR', 'GBP'];
    if (!in_array($currency, $allowedCurrencies, true)) {
        pref_json(false, 'Invalid currency selection.', [], 422);
    }

    $allowedNumberFormats = ['1,234.56', '1.234,56'];
    if (!in_array($numberFormat, $allowedNumberFormats, true)) {
        pref_json(false, 'Invalid number format selection.', [], 422);
    }

    $allowedLandingPages = ['dashboard', 'leads', 'pipeline', 'sales-pipeline', 'tasks', 'inbox', 'projects', 'contacts', 'companies', 'reports'];
    if (!in_array($landingPage, $allowedLandingPages, true)) {
        pref_json(false, 'Invalid landing page selection.', [], 422);
    }

    $allowedTableRows = [10, 25, 50, 100];
    if (!in_array($tableRows, $allowedTableRows, true)) {
        pref_json(false, 'Invalid table rows selection.', [], 422);
    }

    $allowedDensities = ['comfortable', 'compact'];
    if (!in_array($interfaceDensity, $allowedDensities, true)) {
        pref_json(false, 'Invalid interface density selection.', [], 422);
    }

    $sql = "INSERT INTO user_preferences (
                user_id, language, timezone, date_format, time_format, week_starts_on,
                currency, number_format, landing_page, table_rows, interface_density,
                created_at, updated_at
            ) VALUES (
                :user_id, :language, :timezone, :date_format, :time_format, :week_starts_on,
                :currency, :number_format, :landing_page, :table_rows, :interface_density,
                NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                language = VALUES(language),
                timezone = VALUES(timezone),
                date_format = VALUES(date_format),
                time_format = VALUES(time_format),
                week_starts_on = VALUES(week_starts_on),
                currency = VALUES(currency),
                number_format = VALUES(number_format),
                landing_page = VALUES(landing_page),
                table_rows = VALUES(table_rows),
                interface_density = VALUES(interface_density),
                updated_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id'           => $userId,
        ':language'          => $language,
        ':timezone'          => $timezone,
        ':date_format'       => $dateFormat,
        ':time_format'       => $timeFormat,
        ':week_starts_on'    => $weekStartsOn,
        ':currency'          => $currency,
        ':number_format'     => $numberFormat,
        ':landing_page'      => $landingPage,
        ':table_rows'        => $tableRows,
        ':interface_density' => $interfaceDensity
    ]);

    $updatedPrefs = nexflow_user_preferences($userId);
    pref_json(true, 'Preferences saved successfully.', $updatedPrefs);
}

pref_json(false, 'Invalid request method.', [], 405);
