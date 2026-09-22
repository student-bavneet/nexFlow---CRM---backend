<?php
/**
 * NexFlow CRM - Workspace Settings API Endpoint
 * Handles Company Profile, Team Defaults, and Roles & Permissions Preview
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function settings_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    settings_json(false, 'Unauthorized. Please sign in.', [], 401);
}

$userId = (int) $_SESSION['user_id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    settings_json(false, 'Database connection failed.', [], 500);
}

// Fetch authenticated user & organization_id
$stmtUser = $pdo->prepare("SELECT id, organization_id, role, role_id FROM users WHERE id = :id AND status = 'active'");
$stmtUser->execute([':id' => $userId]);
$authUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$authUser || empty($authUser['organization_id'])) {
    settings_json(false, 'User session invalid or organization not found.', [], 403);
}

$orgId = (int) $authUser['organization_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    settings_json(false, 'Missing action parameter.', [], 400);
}

// Helper to check administrative permission
function can_manage_workspace_settings(array $authUser): bool
{
    if (is_super_admin()) {
        return true;
    }
    return has_permission('settings') || has_permission('settings.manage');
}

function nexflow_seed_default_pipeline_stages(PDO $pdo, int $orgId): void
{
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM pipeline_stages WHERE organization_id = :org_id");
    $stmtCheck->execute([':org_id' => $orgId]);
    $count = (int)$stmtCheck->fetchColumn();

    if ($count === 0) {
        $defaultStages = [
            ['name' => 'Prospect', 'color' => '#64748B', 'probability' => 10.00, 'sort_order' => 1, 'is_system' => 0],
            ['name' => 'Qualified', 'color' => '#2563EB', 'probability' => 30.00, 'sort_order' => 2, 'is_system' => 0],
            ['name' => 'Proposal', 'color' => '#F59E0B', 'probability' => 60.00, 'sort_order' => 3, 'is_system' => 0],
            ['name' => 'Negotiation', 'color' => '#8B5CF6', 'probability' => 80.00, 'sort_order' => 4, 'is_system' => 0],
            ['name' => 'Closed Won', 'color' => '#10B981', 'probability' => 100.00, 'sort_order' => 5, 'is_system' => 1],
            ['name' => 'Closed Lost', 'color' => '#EF4444', 'probability' => 0.00, 'sort_order' => 6, 'is_system' => 1],
        ];

        $stmtIns = $pdo->prepare("INSERT INTO pipeline_stages (organization_id, name, color, probability, win_rate_pct, sort_order, is_system, is_active, created_at, updated_at) VALUES (:org_id, :name, :color, :probability, :win_rate, :sort_order, :is_system, 1, NOW(), NOW())");

        foreach ($defaultStages as $stage) {
            $stmtIns->execute([
                ':org_id'       => $orgId,
                ':name'         => $stage['name'],
                ':color'        => $stage['color'],
                ':probability'  => $stage['probability'],
                ':win_rate'     => (int)$stage['probability'],
                ':sort_order'   => $stage['sort_order'],
                ':is_system'    => $stage['is_system']
            ]);
        }
    }
}

function nexflow_get_pipeline_stages(PDO $pdo, int $orgId): array
{
    nexflow_seed_default_pipeline_stages($pdo, $orgId);

    $stmt = $pdo->prepare("SELECT id, name, color, probability, win_rate_pct, sort_order, is_system, is_active FROM pipeline_stages WHERE organization_id = :org_id AND is_active = 1 ORDER BY sort_order ASC, id ASC");
    $stmt->execute([':org_id' => $orgId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($rows as $row) {
        $prob = isset($row['probability']) && (float)$row['probability'] > 0 ? (float)$row['probability'] : (float)$row['win_rate_pct'];
        if ($row['name'] === 'Closed Lost') $prob = 0.0;
        $wonLost = 'normal';
        if ($row['name'] === 'Closed Won') $wonLost = 'won';
        if ($row['name'] === 'Closed Lost') $wonLost = 'lost';

        $formatted[] = [
            'id'          => (int)$row['id'],
            'name'        => $row['name'],
            'color'       => !empty($row['color']) ? $row['color'] : '#3B82F6',
            'prob'        => (int)$prob,
            'probability' => $prob,
            'sort_order'  => (int)$row['sort_order'],
            'is_system'   => (int)$row['is_system'],
            'wonLost'     => $wonLost
        ];
    }
    return $formatted;
}

// -------------------------------------------------------------
// CRM SETTINGS DB HELPERS & LEAD SETTINGS UTILITIES
// -------------------------------------------------------------
function nexflow_ensure_crm_settings_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;
    $sql = "CREATE TABLE IF NOT EXISTS `crm_settings` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `organization_id` INT UNSIGNED NOT NULL DEFAULT 1,
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_org_key` (`organization_id`, `setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    $ensured = true;
}

function nexflow_get_crm_setting(PDO $pdo, int $orgId, string $key, $default = null)
{
    nexflow_ensure_crm_settings_table($pdo);
    $stmt = $pdo->prepare("SELECT setting_value FROM crm_settings WHERE organization_id = :org_id AND setting_key = :key LIMIT 1");
    $stmt->execute([':org_id' => $orgId, ':key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['setting_value'] !== null) {
        return $row['setting_value'];
    }
    return $default;
}

function nexflow_set_crm_setting(PDO $pdo, int $orgId, string $key, $value): void
{
    nexflow_ensure_crm_settings_table($pdo);
    $stmt = $pdo->prepare("INSERT INTO crm_settings (organization_id, setting_key, setting_value, created_at, updated_at) 
        VALUES (:org_id, :key, :val, NOW(), NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    $stmt->execute([':org_id' => $orgId, ':key' => $key, ':val' => (string)$value]);
}

function nexflow_get_lead_settings(PDO $pdo, int $orgId): array
{
    $stmtUsers = $pdo->prepare("SELECT id, name, email FROM users WHERE organization_id = :org_id AND status = 'active' ORDER BY name ASC");
    $stmtUsers->execute([':org_id' => $orgId]);
    $usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $stmtTD = $pdo->prepare("SELECT default_lead_owner_id FROM team_defaults WHERE organization_id = :org_id LIMIT 1");
    $stmtTD->execute([':org_id' => $orgId]);
    $tdRow = $stmtTD->fetch(PDO::FETCH_ASSOC);
    $fallbackOwnerId = $tdRow['default_lead_owner_id'] ?? ($usersList[0]['id'] ?? 1);

    $status = nexflow_get_crm_setting($pdo, $orgId, 'default_lead_status', 'New');
    $source = nexflow_get_crm_setting($pdo, $orgId, 'default_lead_source', 'Website');
    $ownerId = (int)nexflow_get_crm_setting($pdo, $orgId, 'default_lead_owner', $fallbackOwnerId);
    $scoreThreshold = (int)nexflow_get_crm_setting($pdo, $orgId, 'lead_score_threshold', 70);
    $inactivityDays = (int)nexflow_get_crm_setting($pdo, $orgId, 'inactivity_threshold_days', 14);
    $scoreEnabled = (int)nexflow_get_crm_setting($pdo, $orgId, 'lead_scoring_enabled', 1) === 1;
    $dupDetectionEnabled = (int)nexflow_get_crm_setting($pdo, $orgId, 'duplicate_detection_enabled', 1) === 1;
    $autoFollowupEnabled = (int)nexflow_get_crm_setting($pdo, $orgId, 'auto_followup_enabled', 1) === 1;

    $availableStatuses = ['New', 'Contacted', 'Qualified', 'Unqualified'];
    if (!in_array($status, $availableStatuses)) {
        array_unshift($availableStatuses, $status);
    }
    $availableSources = ['Website', 'Referral', 'LinkedIn', 'Cold Outreach', 'Email Campaign', 'Events'];
    if (!in_array($source, $availableSources)) {
        array_unshift($availableSources, $source);
    }

    return [
        'default_lead_status'          => $status,
        'default_lead_source'          => $source,
        'default_lead_owner_id'        => $ownerId,
        'lead_score_threshold'        => $scoreThreshold,
        'inactivity_threshold_days'    => $inactivityDays,
        'lead_scoring_enabled'        => $scoreEnabled,
        'duplicate_detection_enabled' => $dupDetectionEnabled,
        'auto_followup_enabled'        => $autoFollowupEnabled,
        'users'                        => $usersList,
        'available_statuses'           => $availableStatuses,
        'available_sources'            => $availableSources
    ];
}

function nexflow_get_default_lead_status(PDO $pdo, int $orgId): string
{
    return nexflow_get_crm_setting($pdo, $orgId, 'default_lead_status', 'New');
}

function nexflow_get_default_lead_source(PDO $pdo, int $orgId): string
{
    return nexflow_get_crm_setting($pdo, $orgId, 'default_lead_source', 'Website');
}

function nexflow_get_default_lead_owner_id(PDO $pdo, int $orgId): int
{
    return (int)nexflow_get_crm_setting($pdo, $orgId, 'default_lead_owner', 1);
}

function nexflow_get_lead_score_threshold(PDO $pdo, int $orgId): int
{
    return (int)nexflow_get_crm_setting($pdo, $orgId, 'lead_score_threshold', 70);
}

function nexflow_get_inactivity_threshold_days(PDO $pdo, int $orgId): int
{
    return (int)nexflow_get_crm_setting($pdo, $orgId, 'inactivity_threshold_days', 14);
}

function nexflow_is_lead_scoring_enabled(PDO $pdo, int $orgId): bool
{
    return (int)nexflow_get_crm_setting($pdo, $orgId, 'lead_scoring_enabled', 1) === 1;
}

function nexflow_is_duplicate_detection_enabled(PDO $pdo, int $orgId): bool
{
    return (int)nexflow_get_crm_setting($pdo, $orgId, 'duplicate_detection_enabled', 1) === 1;
}

function nexflow_is_auto_followup_enabled(PDO $pdo, int $orgId): bool
{
    return (int)nexflow_get_crm_setting($pdo, $orgId, 'auto_followup_enabled', 1) === 1;
}

function nexflow_get_task_settings(PDO $pdo, int $orgId): array
{
    $status = nexflow_get_crm_setting($pdo, $orgId, 'task_default_status', 'Pending');
    $priority = nexflow_get_crm_setting($pdo, $orgId, 'task_default_priority', 'Medium');
    $reminder = nexflow_get_crm_setting($pdo, $orgId, 'task_default_reminder', '15 minutes before');
    $view = nexflow_get_crm_setting($pdo, $orgId, 'task_default_view', 'list');
    $showCompleted = (int)nexflow_get_crm_setting($pdo, $orgId, 'task_show_completed', 1) === 1;

    $availableStatuses = ['Pending', 'Not Started', 'In Progress', 'Waiting', 'Completed'];
    if (!in_array($status, $availableStatuses, true)) {
        array_unshift($availableStatuses, $status);
    }

    $availablePriorities = ['Low', 'Medium', 'High', 'Urgent'];
    if (!in_array($priority, $availablePriorities, true)) {
        array_unshift($availablePriorities, $priority);
    }

    $availableReminders = ['none', '5 minutes before', '15 minutes before', '30 minutes before', '1 hour before', '1 day before'];
    if (!in_array($reminder, $availableReminders, true)) {
        array_unshift($availableReminders, $reminder);
    }

    $availableViews = ['list', 'board', 'calendar'];
    if (!in_array($view, $availableViews, true)) {
        array_unshift($availableViews, $view);
    }

    return [
        'task_default_status'   => $status,
        'task_default_priority' => $priority,
        'task_default_reminder' => $reminder,
        'task_default_view'     => $view,
        'task_show_completed'   => $showCompleted ? 1 : 0,
        'available_statuses'   => $availableStatuses,
        'available_priorities' => $availablePriorities,
        'available_reminders'  => $availableReminders,
        'available_views'       => $availableViews
    ];
}

function nexflow_get_default_task_status(PDO $pdo, int $orgId): string
{
    return nexflow_get_crm_setting($pdo, $orgId, 'task_default_status', 'Pending');
}

function nexflow_get_default_task_priority(PDO $pdo, int $orgId): string
{
    return nexflow_get_crm_setting($pdo, $orgId, 'task_default_priority', 'Medium');
}

function nexflow_get_default_task_reminder(PDO $pdo, int $orgId): string
{
    return nexflow_get_crm_setting($pdo, $orgId, 'task_default_reminder', '15 minutes before');
}

function nexflow_get_default_task_view(PDO $pdo, int $orgId): string
{
    return nexflow_get_crm_setting($pdo, $orgId, 'task_default_view', 'list');
}

function nexflow_get_task_show_completed(PDO $pdo, int $orgId): bool
{
    return (int)nexflow_get_crm_setting($pdo, $orgId, 'task_show_completed', 1) === 1;
}

function nexflow_get_email_preferences(PDO $pdo, int $orgId): array
{
    $senderName = nexflow_get_crm_setting($pdo, $orgId, 'email_sender_display_name', '');
    $replyTo = nexflow_get_crm_setting($pdo, $orgId, 'email_reply_to', '');
    $signature = nexflow_get_crm_setting($pdo, $orgId, 'email_signature', '');
    $includeSigRaw = nexflow_get_crm_setting($pdo, $orgId, 'email_include_signature', 1);

    return [
        'email_sender_display_name' => $senderName !== null ? (string)$senderName : '',
        'email_reply_to'            => $replyTo !== null ? (string)$replyTo : '',
        'email_signature'           => $signature !== null ? (string)$signature : '',
        'email_include_signature'   => (int)$includeSigRaw
    ];
}

function nexflow_get_email_preference(PDO $pdo, int $orgId, string $key, $default = null)
{
    $allowedKeys = [
        'email_sender_display_name',
        'email_reply_to',
        'email_signature',
        'email_include_signature'
    ];

    if (!in_array($key, $allowedKeys, true)) {
        return $default;
    }

    return nexflow_get_crm_setting($pdo, $orgId, $key, $default);
}

function nexflow_save_email_preferences(PDO $pdo, int $orgId, array $settings): void
{
    nexflow_ensure_crm_settings_table($pdo);

    if (array_key_exists('email_sender_display_name', $settings)) {
        nexflow_set_crm_setting($pdo, $orgId, 'email_sender_display_name', (string)$settings['email_sender_display_name']);
    }
    if (array_key_exists('email_reply_to', $settings)) {
        nexflow_set_crm_setting($pdo, $orgId, 'email_reply_to', (string)$settings['email_reply_to']);
    }
    if (array_key_exists('email_signature', $settings)) {
        nexflow_set_crm_setting($pdo, $orgId, 'email_signature', (string)$settings['email_signature']);
    }
    if (array_key_exists('email_include_signature', $settings)) {
        nexflow_set_crm_setting($pdo, $orgId, 'email_include_signature', (int)$settings['email_include_signature']);
    }
}

function nexflow_get_calling_preferences(PDO $pdo, int $orgId): array
{
    $countryCode = nexflow_get_crm_setting($pdo, $orgId, 'calling_default_country_code', '');
    $status = nexflow_get_crm_setting($pdo, $orgId, 'calling_default_status', '');
    $autoOpenNotes = nexflow_get_crm_setting($pdo, $orgId, 'calling_auto_open_notes', null);
    $showFloatingWidget = nexflow_get_crm_setting($pdo, $orgId, 'calling_show_floating_widget', null);

    return [
        'calling_default_country_code' => $countryCode !== null ? (string)$countryCode : '',
        'calling_default_status'       => $status !== null ? (string)$status : '',
        'calling_auto_open_notes'      => $autoOpenNotes !== null ? (int)$autoOpenNotes : 0,
        'calling_show_floating_widget' => $showFloatingWidget !== null ? (int)$showFloatingWidget : 0
    ];
}

function nexflow_get_calling_preference(PDO $pdo, int $orgId, string $key, $default = null)
{
    $allowedKeys = [
        'calling_default_country_code',
        'calling_default_status',
        'calling_auto_open_notes',
        'calling_show_floating_widget'
    ];

    if (!in_array($key, $allowedKeys, true)) {
        return $default;
    }

    return nexflow_get_crm_setting($pdo, $orgId, $key, $default);
}

function nexflow_save_calling_preferences(PDO $pdo, int $orgId, array $data): void
{
    nexflow_ensure_crm_settings_table($pdo);

    if (array_key_exists('calling_default_country_code', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'calling_default_country_code', (string)$data['calling_default_country_code']);
    }
    if (array_key_exists('calling_default_status', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'calling_default_status', (string)$data['calling_default_status']);
    }
    if (array_key_exists('calling_auto_open_notes', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'calling_auto_open_notes', (int)$data['calling_auto_open_notes']);
    }
    if (array_key_exists('calling_show_floating_widget', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'calling_show_floating_widget', (int)$data['calling_show_floating_widget']);
    }
}

function nexflow_get_inbox_preferences(PDO $pdo, int $orgId): array
{
    $defaultFolder = nexflow_get_crm_setting($pdo, $orgId, 'inbox_default_folder', '');
    $sortOrder = nexflow_get_crm_setting($pdo, $orgId, 'inbox_sort_order', '');
    $markReadRaw = nexflow_get_crm_setting($pdo, $orgId, 'inbox_mark_read_on_open', null);
    $showContextRaw = nexflow_get_crm_setting($pdo, $orgId, 'inbox_show_contact_context', null);

    return [
        'inbox_default_folder'       => $defaultFolder !== null ? (string)$defaultFolder : '',
        'inbox_sort_order'           => $sortOrder !== null ? (string)$sortOrder : '',
        'inbox_mark_read_on_open'    => $markReadRaw !== null ? (int)$markReadRaw : 0,
        'inbox_show_contact_context' => $showContextRaw !== null ? (int)$showContextRaw : 0
    ];
}

function nexflow_get_inbox_preference(PDO $pdo, int $orgId, string $key, $default = null)
{
    $allowedKeys = [
        'inbox_default_folder',
        'inbox_sort_order',
        'inbox_mark_read_on_open',
        'inbox_show_contact_context'
    ];

    if (!in_array($key, $allowedKeys, true)) {
        return $default;
    }

    return nexflow_get_crm_setting($pdo, $orgId, $key, $default);
}

function nexflow_save_inbox_preferences(PDO $pdo, int $orgId, array $data): void
{
    nexflow_ensure_crm_settings_table($pdo);

    if (array_key_exists('inbox_default_folder', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'inbox_default_folder', (string)$data['inbox_default_folder']);
    }
    if (array_key_exists('inbox_sort_order', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'inbox_sort_order', (string)$data['inbox_sort_order']);
    }
    if (array_key_exists('inbox_mark_read_on_open', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'inbox_mark_read_on_open', (int)$data['inbox_mark_read_on_open']);
    }
    if (array_key_exists('inbox_show_contact_context', $data)) {
        nexflow_set_crm_setting($pdo, $orgId, 'inbox_show_contact_context', (int)$data['inbox_show_contact_context']);
    }
}

// -------------------------------------------------------------
// INTEGRATIONS DB HELPERS & REUSABLE FUNCTIONS
// -------------------------------------------------------------
function nexflow_ensure_crm_integrations_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;
    $sql = "CREATE TABLE IF NOT EXISTS `crm_integrations` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `organization_id` INT UNSIGNED NOT NULL,
        `integration_key` VARCHAR(50) NOT NULL,
        `integration_name` VARCHAR(100) NOT NULL,
        `integration_type` VARCHAR(50) NOT NULL,
        `status` ENUM('not_connected', 'pending', 'connected', 'disconnected', 'error') NOT NULL DEFAULT 'not_connected',
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `config_json` TEXT NULL,
        `connected_at` DATETIME NULL,
        `disconnected_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_org_integration` (`organization_id`, `integration_key`),
        KEY `idx_org_status` (`organization_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    $ensured = true;
}

function nexflow_get_default_integrations(): array
{
    return [
        'google_calendar' => [
            'integration_key'  => 'google_calendar',
            'integration_name' => 'Google Calendar',
            'integration_type' => 'calendar',
            'category'         => 'Calendar',
            'description'      => 'Sync meetings and sales schedule automatically.'
        ],
        'outlook_calendar' => [
            'integration_key'  => 'outlook_calendar',
            'integration_name' => 'Outlook Calendar',
            'integration_type' => 'calendar',
            'category'         => 'Calendar',
            'description'      => 'Integrate Microsoft 365 calendar events.'
        ],
        'gmail' => [
            'integration_key'  => 'gmail',
            'integration_name' => 'Gmail Integration',
            'integration_type' => 'email',
            'category'         => 'Email',
            'description'      => 'Send and receive customer emails in Inbox.'
        ],
        'whatsapp_business' => [
            'integration_key'  => 'whatsapp_business',
            'integration_name' => 'WhatsApp Business API',
            'integration_type' => 'messaging',
            'category'         => 'Messaging',
            'description'      => 'Connect official WhatsApp Business account.'
        ],
        'twilio' => [
            'integration_key'  => 'twilio',
            'integration_name' => 'Twilio Softphone',
            'integration_type' => 'calling',
            'category'         => 'Calling',
            'description'      => 'Power real telephony calls and SMS messaging.'
        ],
        'zapier' => [
            'integration_key'  => 'zapier',
            'integration_name' => 'Zapier Webhooks',
            'integration_type' => 'automation',
            'category'         => 'Automation',
            'description'      => 'Automate workflows with over 5,000+ apps.'
        ]
    ];
}

function nexflow_ensure_org_integrations(PDO $pdo, int $orgId): void
{
    nexflow_ensure_crm_integrations_table($pdo);
    $defaults = nexflow_get_default_integrations();

    $stmt = $pdo->prepare("INSERT IGNORE INTO crm_integrations 
        (organization_id, integration_key, integration_name, integration_type, status, is_enabled, created_at, updated_at)
        VALUES (:org_id, :key, :name, :type, 'not_connected', 0, NOW(), NOW())");

    foreach ($defaults as $key => $def) {
        $stmt->execute([
            ':org_id' => $orgId,
            ':key'    => $key,
            ':name'   => $def['integration_name'],
            ':type'   => $def['integration_type']
        ]);
    }
}

function nexflow_get_integrations(PDO $pdo, int $orgId): array
{
    nexflow_ensure_org_integrations($pdo, $orgId);
    $defaults = nexflow_get_default_integrations();

    $stmt = $pdo->prepare("SELECT id, organization_id, integration_key, integration_name, integration_type, status, is_enabled, connected_at, disconnected_at, updated_at 
        FROM crm_integrations 
        WHERE organization_id = :org_id 
        ORDER BY id ASC");
    $stmt->execute([':org_id' => $orgId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $key = $row['integration_key'];
        if (!isset($defaults[$key])) {
            continue;
        }
        $result[] = [
            'id'               => (int)$row['id'],
            'organization_id'  => (int)$row['organization_id'],
            'integration_key'  => $key,
            'integration_name' => $row['integration_name'] ?: $defaults[$key]['integration_name'],
            'integration_type' => $row['integration_type'] ?: $defaults[$key]['integration_type'],
            'status'           => $row['status'] ?: 'not_connected',
            'is_enabled'       => (bool)(int)$row['is_enabled'],
            'connected_at'     => $row['connected_at'],
            'disconnected_at'  => $row['disconnected_at'],
            'category'         => $defaults[$key]['category'],
            'description'      => $defaults[$key]['description']
        ];
    }

    return $result;
}

function nexflow_get_integration(PDO $pdo, int $orgId, string $key): ?array
{
    nexflow_ensure_org_integrations($pdo, $orgId);
    $defaults = nexflow_get_default_integrations();
    if (!isset($defaults[$key])) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, organization_id, integration_key, integration_name, integration_type, status, is_enabled, connected_at, disconnected_at, updated_at 
        FROM crm_integrations 
        WHERE organization_id = :org_id AND integration_key = :key 
        LIMIT 1");
    $stmt->execute([':org_id' => $orgId, ':key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'id'               => (int)$row['id'],
        'organization_id'  => (int)$row['organization_id'],
        'integration_key'  => $key,
        'integration_name' => $row['integration_name'] ?: $defaults[$key]['integration_name'],
        'integration_type' => $row['integration_type'] ?: $defaults[$key]['integration_type'],
        'status'           => $row['status'] ?: 'not_connected',
        'is_enabled'       => (bool)(int)$row['is_enabled'],
        'connected_at'     => $row['connected_at'],
        'disconnected_at'  => $row['disconnected_at'],
        'category'         => $defaults[$key]['category'],
        'description'      => $defaults[$key]['description']
    ];
}

function nexflow_connect_integration(PDO $pdo, int $orgId, string $key): array
{
    nexflow_ensure_org_integrations($pdo, $orgId);
    $defaults = nexflow_get_default_integrations();
    if (!isset($defaults[$key])) {
        return [
            'success'     => false,
            'code'        => 'INVALID_KEY',
            'message'     => 'Invalid integration key provided.',
            'http_status' => 422
        ];
    }

    // In this frontend phase without real external API services/credentials configured,
    // do NOT pretend it connected. Retain status = not_connected.
    return [
        'success'     => false,
        'code'        => 'INTEGRATION_NOT_CONFIGURED',
        'message'     => 'This integration requires external credentials and backend configuration before it can be connected.',
        'http_status' => 200,
        'data'        => [
            'code'            => 'INTEGRATION_NOT_CONFIGURED',
            'integration_key' => $key,
            'status'          => 'not_connected'
        ]
    ];
}

function nexflow_disconnect_integration(PDO $pdo, int $orgId, string $key): array
{
    nexflow_ensure_org_integrations($pdo, $orgId);
    $defaults = nexflow_get_default_integrations();
    if (!isset($defaults[$key])) {
        return [
            'success'     => false,
            'code'        => 'INVALID_KEY',
            'message'     => 'Invalid integration key provided.',
            'http_status' => 422
        ];
    }

    $existing = nexflow_get_integration($pdo, $orgId, $key);
    $currentStatus = $existing ? $existing['status'] : 'not_connected';

    if ($currentStatus !== 'connected') {
        // Safe, idempotent response
        return [
            'success'     => true,
            'message'     => 'Integration is not connected.',
            'http_status' => 200,
            'data'        => [
                'integration_key' => $key,
                'status'          => $currentStatus,
                'is_enabled'      => false
            ]
        ];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE crm_integrations 
            SET status = 'disconnected', is_enabled = 0, disconnected_at = NOW(), updated_at = NOW() 
            WHERE organization_id = :org_id AND integration_key = :key");
        $stmt->execute([':org_id' => $orgId, ':key' => $key]);
        $pdo->commit();

        return [
            'success'     => true,
            'message'     => 'Integration disconnected successfully.',
            'http_status' => 200,
            'data'        => [
                'integration_key' => $key,
                'status'          => 'disconnected',
                'is_enabled'      => false
            ]
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success'     => false,
            'message'     => 'Failed to disconnect integration: ' . $e->getMessage(),
            'http_status' => 500
        ];
    }
}

function nexflow_toggle_integration(PDO $pdo, int $orgId, string $key, int $isEnabled): array
{
    nexflow_ensure_org_integrations($pdo, $orgId);
    $defaults = nexflow_get_default_integrations();
    if (!isset($defaults[$key])) {
        return [
            'success'     => false,
            'message'     => 'Invalid integration key provided.',
            'http_status' => 422
        ];
    }

    if ($isEnabled !== 0 && $isEnabled !== 1) {
        return [
            'success'     => false,
            'message'     => 'Invalid value for is_enabled. Must be 1 or 0.',
            'http_status' => 422
        ];
    }

    $existing = nexflow_get_integration($pdo, $orgId, $key);
    $currentStatus = $existing ? $existing['status'] : 'not_connected';

    if ($currentStatus === 'not_connected' && $isEnabled === 1) {
        return [
            'success'     => false,
            'message'     => 'Cannot enable an integration that is not connected.',
            'http_status' => 422
        ];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE crm_integrations 
            SET is_enabled = :enabled, updated_at = NOW() 
            WHERE organization_id = :org_id AND integration_key = :key");
        $stmt->execute([
            ':enabled' => $isEnabled,
            ':org_id'  => $orgId,
            ':key'     => $key
        ]);
        $pdo->commit();

        return [
            'success'     => true,
            'message'     => 'Integration status updated successfully.',
            'http_status' => 200,
            'data'        => [
                'integration_key' => $key,
                'is_enabled'      => (bool)$isEnabled
            ]
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success'     => false,
            'message'     => 'Failed to toggle integration: ' . $e->getMessage(),
            'http_status' => 500
        ];
    }
}



function nexflow_check_duplicate_lead(PDO $pdo, int $orgId, ?string $email, ?string $phone, ?int $excludeLeadId = null): array
{
    if (!nexflow_is_duplicate_detection_enabled($pdo, $orgId)) {
        return ['is_duplicate' => false, 'message' => 'Duplicate detection is disabled.'];
    }

    $normalizedEmail = $email ? strtolower(trim($email)) : null;
    $normalizedPhone = $phone ? preg_replace('/\D+/', '', $phone) : null;

    if (!$normalizedEmail && !$normalizedPhone) {
        return ['is_duplicate' => false, 'message' => 'No email or phone provided.'];
    }

    $sqlCheck = "SHOW TABLES LIKE 'leads'";
    $tables = $pdo->query($sqlCheck)->fetchAll();
    if (empty($tables)) {
        return ['is_duplicate' => false, 'message' => 'Leads table does not exist yet.'];
    }

    $conditions = [];
    $params = [':org_id' => $orgId];

    if ($normalizedEmail) {
        $conditions[] = "LOWER(TRIM(email)) = :email";
        $params[':email'] = $normalizedEmail;
    }
    if ($normalizedPhone && strlen($normalizedPhone) >= 7) {
        $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE :phone";
        $params[':phone'] = '%' . $normalizedPhone . '%';
    }

    if (empty($conditions)) {
        return ['is_duplicate' => false, 'message' => 'Insufficient data for duplicate check.'];
    }

    $query = "SELECT id, name, email, phone FROM leads WHERE organization_id = :org_id AND (" . implode(" OR ", $conditions) . ")";
    if ($excludeLeadId) {
        $query .= " AND id != :exclude_id";
        $params[':exclude_id'] = $excludeLeadId;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($match) {
        $field = ($normalizedEmail && strtolower(trim($match['email'] ?? '')) === $normalizedEmail) ? 'email' : 'phone';
        return [
            'is_duplicate'    => true,
            'duplicate_field' => $field,
            'existing_lead'   => $match,
            'message'         => "A lead with this {$field} already exists (" . ($match['name'] ?? 'ID #' . $match['id']) . ")."
        ];
    }

    return ['is_duplicate' => false, 'message' => 'No duplicate found.'];
}

function nexflow_handle_lead_status_change_followup(PDO $pdo, int $orgId, int $leadId, string $newStatus, int $userId): ?array
{
    if (!nexflow_is_auto_followup_enabled($pdo, $orgId)) {
        return ['created' => false, 'reason' => 'Automatic follow-ups disabled.'];
    }

    $sqlCheck = "SHOW TABLES LIKE 'tasks'";
    $tables = $pdo->query($sqlCheck)->fetchAll();
    if (!empty($tables)) {
        $stmtIns = $pdo->prepare("INSERT INTO tasks (organization_id, lead_id, title, description, assigned_to_user_id, status, due_date, created_at, updated_at)
            VALUES (:org_id, :lead_id, :title, :desc, :user_id, 'Pending', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW(), NOW())");
        $stmtIns->execute([
            ':org_id'  => $orgId,
            ':lead_id' => $leadId,
            ':title'   => "Follow up on lead status change: " . $newStatus,
            ':desc'    => "Lead status changed to {$newStatus}. Automatically scheduled follow-up reminder.",
            ':user_id' => $userId
        ]);
        return ['task_id' => $pdo->lastInsertId(), 'created' => true];
    }
    return ['created' => false, 'reason' => 'Tasks table not available'];
}

// -------------------------------------------------------------
// CUSTOM FIELDS DB HELPERS & UTILITIES
// -------------------------------------------------------------
function nexflow_ensure_custom_fields_tables(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;

    $sql1 = "CREATE TABLE IF NOT EXISTS `crm_custom_fields` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `organization_id` INT UNSIGNED NOT NULL DEFAULT 1,
        `field_key` VARCHAR(100) NOT NULL,
        `field_label` VARCHAR(150) NOT NULL,
        `target_object` VARCHAR(50) NOT NULL,
        `field_type` VARCHAR(50) NOT NULL DEFAULT 'Text',
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_by` INT UNSIGNED NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_org_target_key` (`organization_id`, `target_object`, `field_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql1);

    $sql2 = "CREATE TABLE IF NOT EXISTS `crm_custom_field_options` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `custom_field_id` INT UNSIGNED NOT NULL,
        `option_value` VARCHAR(255) NOT NULL,
        `option_label` VARCHAR(255) NOT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`custom_field_id`) REFERENCES `crm_custom_fields` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql2);

    $sql3 = "CREATE TABLE IF NOT EXISTS `crm_custom_field_values` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `custom_field_id` INT UNSIGNED NOT NULL,
        `entity_type` VARCHAR(50) NOT NULL,
        `entity_id` INT UNSIGNED NOT NULL,
        `field_value` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_field_entity` (`custom_field_id`, `entity_type`, `entity_id`),
        FOREIGN KEY (`custom_field_id`) REFERENCES `crm_custom_fields` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql3);

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM crm_custom_fields WHERE organization_id = 1");
    $stmtCheck->execute();
    if ((int)$stmtCheck->fetchColumn() === 0) {
        nexflow_seed_default_custom_fields($pdo);
    }

    $ensured = true;
}

function nexflow_seed_default_custom_fields(PDO $pdo): void
{
    $defaultFields = [
        [
            'field_label'   => 'Budget Range',
            'field_key'     => 'budget_range',
            'target_object' => 'Leads',
            'field_type'    => 'Dropdown',
            'is_required'   => 0,
            'is_active'     => 1,
            'sort_order'    => 1,
            'options'       => ['Under $10,000', '$10,000 - $25,000', '$25,000 - $50,000', '$50,000+']
        ],
        [
            'field_label'   => 'Contract Start Date',
            'field_key'     => 'contract_start_date',
            'target_object' => 'Deals',
            'field_type'    => 'Date',
            'is_required'   => 1,
            'is_active'     => 1,
            'sort_order'    => 2,
            'options'       => []
        ],
        [
            'field_label'   => 'Tax Registration Number',
            'field_key'     => 'tax_registration_number',
            'target_object' => 'Companies',
            'field_type'    => 'Text',
            'is_required'   => 0,
            'is_active'     => 1,
            'sort_order'    => 3,
            'options'       => []
        ],
        [
            'field_label'   => 'Decision Maker Score',
            'field_key'     => 'decision_maker_score',
            'target_object' => 'Contacts',
            'field_type'    => 'Number',
            'is_required'   => 0,
            'is_active'     => 1,
            'sort_order'    => 4,
            'options'       => []
        ],
    ];

    foreach ($defaultFields as $df) {
        $stmtIns = $pdo->prepare("INSERT INTO crm_custom_fields (organization_id, field_key, field_label, target_object, field_type, is_required, is_active, sort_order, created_at, updated_at) 
            VALUES (1, :key, :label, :target, :type, :req, :active, :sort, NOW(), NOW())");
        $stmtIns->execute([
            ':key'    => $df['field_key'],
            ':label'  => $df['field_label'],
            ':target' => $df['target_object'],
            ':type'   => $df['field_type'],
            ':req'    => $df['is_required'],
            ':active' => $df['is_active'],
            ':sort'   => $df['sort_order']
        ]);
        $fieldId = (int)$pdo->lastInsertId();

        if (!empty($df['options'])) {
            $stmtOpt = $pdo->prepare("INSERT INTO crm_custom_field_options (custom_field_id, option_value, option_label, sort_order, created_at) VALUES (:field_id, :val, :lbl, :sort, NOW())");
            foreach ($df['options'] as $idx => $opt) {
                $stmtOpt->execute([
                    ':field_id' => $fieldId,
                    ':val'      => $opt,
                    ':lbl'      => $opt,
                    ':sort'     => $idx + 1
                ]);
            }
        }
    }
}

function nexflow_generate_custom_field_key(PDO $pdo, int $orgId, string $targetObject, string $fieldLabel, ?int $excludeId = null): string
{
    $slug = strtolower(trim($fieldLabel));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    if (empty($slug)) {
        $slug = 'custom_field';
    }

    $baseKey = $slug;
    $key = $baseKey;
    $counter = 2;

    while (true) {
        $sql = "SELECT id FROM crm_custom_fields WHERE organization_id = :org_id AND LOWER(target_object) = LOWER(:target) AND field_key = :key";
        $params = [':org_id' => $orgId, ':target' => $targetObject, ':key' => $key];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            break;
        }
        $key = $baseKey . '_' . $counter;
        $counter++;
    }
    return $key;
}

function nexflow_get_custom_fields(PDO $pdo, int $orgId, ?string $targetObject = null, bool $activeOnly = false): array
{
    nexflow_ensure_custom_fields_tables($pdo);

    $sql = "SELECT id, organization_id, field_key, field_label, target_object, field_type, is_required, is_active, sort_order, created_at, updated_at 
            FROM crm_custom_fields WHERE organization_id = :org_id";
    $params = [':org_id' => $orgId];

    if (!empty($targetObject)) {
        $sql .= " AND LOWER(target_object) = LOWER(:target)";
        $params[':target'] = $targetObject;
    }

    if ($activeOnly) {
        $sql .= " AND is_active = 1";
    }

    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fieldIds = array_column($fields, 'id');
    $optionsMap = [];
    if (!empty($fieldIds)) {
        $inClause = implode(',', array_map('intval', $fieldIds));
        $stmtOpts = $pdo->query("SELECT id, custom_field_id, option_value, option_label, sort_order FROM crm_custom_field_options WHERE custom_field_id IN ($inClause) ORDER BY sort_order ASC, id ASC");
        $opts = $stmtOpts->fetchAll(PDO::FETCH_ASSOC);
        foreach ($opts as $o) {
            $optionsMap[$o['custom_field_id']][] = [
                'id'           => (int)$o['id'],
                'option_value' => $o['option_value'],
                'option_label' => $o['option_label'],
                'sort_order'   => (int)$o['sort_order']
            ];
        }
    }

    $result = [];
    foreach ($fields as $f) {
        $fId = (int)$f['id'];
        $result[] = [
            'id'            => $fId,
            'name'          => $f['field_label'],
            'field_label'   => $f['field_label'],
            'field_key'     => $f['field_key'],
            'object'        => $f['target_object'],
            'target_object' => $f['target_object'],
            'type'          => $f['field_type'],
            'field_type'    => $f['field_type'],
            'required'      => (bool)$f['is_required'],
            'is_required'   => (int)$f['is_required'],
            'active'        => (bool)$f['is_active'],
            'is_active'     => (int)$f['is_active'],
            'sort_order'    => (int)$f['sort_order'],
            'options'       => $optionsMap[$fId] ?? []
        ];
    }

    return $result;
}

function nexflow_delete_custom_field(PDO $pdo, int $orgId, int $fieldId): bool
{
    nexflow_ensure_custom_fields_tables($pdo);
    $stmt = $pdo->prepare("DELETE FROM crm_custom_fields WHERE id = :id AND organization_id = :org_id");
    return $stmt->execute([':id' => $fieldId, ':org_id' => $orgId]);
}

function nexflow_get_entity_custom_values(PDO $pdo, int $orgId, string $entityType, int $entityId): array
{
    nexflow_ensure_custom_fields_tables($pdo);
    $stmt = $pdo->prepare("SELECT v.custom_field_id, v.field_value, f.field_key, f.field_label, f.field_type 
        FROM crm_custom_field_values v 
        JOIN crm_custom_fields f ON v.custom_field_id = f.id
        WHERE f.organization_id = :org_id AND LOWER(v.entity_type) = LOWER(:entity_type) AND v.entity_id = :entity_id");
    $stmt->execute([':org_id' => $orgId, ':entity_type' => $entityType, ':entity_id' => $entityId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function nexflow_save_entity_custom_values(PDO $pdo, int $orgId, string $entityType, int $entityId, array $values): void
{
    nexflow_ensure_custom_fields_tables($pdo);
    $stmtIns = $pdo->prepare("INSERT INTO crm_custom_field_values (custom_field_id, entity_type, entity_id, field_value, created_at, updated_at)
        VALUES (:field_id, :entity_type, :entity_id, :val, NOW(), NOW())
        ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()");

    foreach ($values as $fieldId => $val) {
        if (is_array($val)) {
            $val = json_encode($val);
        }
        $stmtIns->execute([
            ':field_id'    => (int)$fieldId,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':val'         => $val !== null ? (string)$val : null
        ]);
    }
}

function nexflow_validate_entity_custom_fields(PDO $pdo, int $orgId, string $entityType, array $submittedValues): array
{
    $fields = nexflow_get_custom_fields($pdo, $orgId, $entityType, true);
    $errors = [];

    foreach ($fields as $f) {
        if ($f['is_required']) {
            $fId = $f['id'];
            $fKey = $f['field_key'];
            $val = $submittedValues[$fId] ?? $submittedValues[$fKey] ?? null;

            if ($val === null || (is_string($val) && trim($val) === '') || (is_array($val) && empty($val))) {
                $errors[] = "The field '{$f['field_label']}' is required.";
            }
        }
    }
    return $errors;
}

// -------------------------------------------------------------
// ACTION: COMPANY PROFILE
// -------------------------------------------------------------
if ($action === 'company_profile') {
    if ($method === 'GET') {
        $stmtOrg = $pdo->prepare("SELECT id, name, website, industry_id, phone, business_email, country, city, state_region, postal_code FROM organizations WHERE id = :org_id");
        $stmtOrg->execute([':org_id' => $orgId]);
        $org = $stmtOrg->fetch(PDO::FETCH_ASSOC);

        if (!$org) {
            settings_json(false, 'Organization record not found.', [], 404);
        }

        $industryName = '';
        if (!empty($org['industry_id'])) {
            $stmtInd = $pdo->prepare("SELECT name FROM industries WHERE id = :ind_id");
            $stmtInd->execute([':ind_id' => $org['industry_id']]);
            $industryName = $stmtInd->fetchColumn() ?: '';
        }

        $stmtSet = $pdo->prepare("SELECT company_size, fiscal_year_start, business_hours FROM organization_settings WHERE organization_id = :org_id");
        $stmtSet->execute([':org_id' => $orgId]);
        $settings = $stmtSet->fetch(PDO::FETCH_ASSOC) ?: [];

        $profileData = [
            'name'              => $org['name'] ?? '',
            'website'           => $org['website'] ?? '',
            'industry'          => $industryName,
            'company_size'      => $settings['company_size'] ?? '50-200 employees',
            'phone'             => $org['phone'] ?? '',
            'business_email'    => $org['business_email'] ?? '',
            'country'           => $org['country'] ?? '',
            'city'              => $org['city'] ?? '',
            'state_region'      => $org['state_region'] ?? '',
            'postal_code'       => $org['postal_code'] ?? '',
            'fiscal_year_start' => $settings['fiscal_year_start'] ?? 'January',
            'business_hours'    => $settings['business_hours'] ?? '09:00 - 17:00 PST'
        ];

        settings_json(true, 'Company profile retrieved successfully.', $profileData);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $name            = trim((string)($_POST['name'] ?? $_POST['compName'] ?? ''));
        $website         = trim((string)($_POST['website'] ?? $_POST['compWebsite'] ?? ''));
        $industryStr     = trim((string)($_POST['industry'] ?? $_POST['compIndustry'] ?? ''));
        $companySize     = trim((string)($_POST['company_size'] ?? $_POST['compSize'] ?? '50-200 employees'));
        $phone           = trim((string)($_POST['phone'] ?? $_POST['compPhone'] ?? ''));
        $businessEmail   = trim((string)($_POST['business_email'] ?? $_POST['compEmail'] ?? ''));
        $country         = trim((string)($_POST['country'] ?? $_POST['compCountry'] ?? ''));
        $city            = trim((string)($_POST['city'] ?? $_POST['compCity'] ?? ''));
        $stateRegion     = trim((string)($_POST['state_region'] ?? $_POST['state'] ?? $_POST['compState'] ?? ''));
        $postalCode      = trim((string)($_POST['postal_code'] ?? $_POST['zip'] ?? $_POST['compZip'] ?? ''));
        $fiscalYearStart = trim((string)($_POST['fiscal_year_start'] ?? $_POST['compFiscalStart'] ?? 'January'));
        $businessHours   = trim((string)($_POST['business_hours'] ?? $_POST['compHours'] ?? '09:00 - 17:00 PST'));

        if (empty($name)) {
            settings_json(false, 'Company name is required.', [], 422);
        }

        $industryId = null;
        if (!empty($industryStr)) {
            $stmtInd = $pdo->prepare("SELECT id FROM industries WHERE name = :name OR `key` = :key");
            $indKey = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $industryStr));
            $stmtInd->execute([':name' => $industryStr, ':key' => trim($indKey, '_')]);
            $industryId = $stmtInd->fetchColumn();

            if (!$industryId) {
                $stmtInsInd = $pdo->prepare("INSERT INTO industries (`key`, name, icon, description) VALUES (:key, :name, 'briefcase', :desc)");
                $stmtInsInd->execute([':key' => trim($indKey, '_'), ':name' => $industryStr, ':desc' => $industryStr]);
                $industryId = (int)$pdo->lastInsertId();
            } else {
                $industryId = (int)$industryId;
            }
        }

        $sqlUpdateOrg = "UPDATE organizations SET 
                            name = :name,
                            website = :website,
                            industry_id = :industry_id,
                            phone = :phone,
                            business_email = :business_email,
                            country = :country,
                            city = :city,
                            state_region = :state_region,
                            postal_code = :postal_code,
                            updated_at = NOW()
                        WHERE id = :org_id";
        $stmtUpOrg = $pdo->prepare($sqlUpdateOrg);
        $stmtUpOrg->execute([
            ':name'           => $name,
            ':website'        => $website,
            ':industry_id'     => $industryId,
            ':phone'          => $phone,
            ':business_email' => $businessEmail,
            ':country'        => $country,
            ':city'           => $city,
            ':state_region'   => $stateRegion,
            ':postal_code'    => $postalCode,
            ':org_id'         => $orgId
        ]);

        $sqlUpsertSet = "INSERT INTO organization_settings (
                            organization_id, company_size, fiscal_year_start, business_hours, created_at, updated_at
                        ) VALUES (
                            :org_id, :company_size, :fiscal_year_start, :business_hours, NOW(), NOW()
                        ) ON DUPLICATE KEY UPDATE 
                            company_size = VALUES(company_size),
                            fiscal_year_start = VALUES(fiscal_year_start),
                            business_hours = VALUES(business_hours),
                            updated_at = NOW()";
        $stmtUpSet = $pdo->prepare($sqlUpsertSet);
        $stmtUpSet->execute([
            ':org_id'            => $orgId,
            ':company_size'      => $companySize,
            ':fiscal_year_start' => $fiscalYearStart,
            ':business_hours'    => $businessHours
        ]);

        $updatedProfile = [
            'name'              => $name,
            'website'           => $website,
            'industry'          => $industryStr,
            'company_size'      => $companySize,
            'phone'             => $phone,
            'business_email'    => $businessEmail,
            'country'           => $country,
            'city'              => $city,
            'state_region'      => $stateRegion,
            'postal_code'       => $postalCode,
            'fiscal_year_start' => $fiscalYearStart,
            'business_hours'    => $businessHours
        ];

        settings_json(true, 'Company profile updated successfully.', $updatedProfile);
    }
}

// -------------------------------------------------------------
// ACTION: TEAM DEFAULTS
// -------------------------------------------------------------
if ($action === 'team_defaults') {
    if ($method === 'GET') {
        $stmtUsers = $pdo->prepare("SELECT id, name, first_name, last_name, email, role FROM users WHERE organization_id = :org_id AND status = 'active' ORDER BY name ASC");
        $stmtUsers->execute([':org_id' => $orgId]);
        $usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        $stmtTeams = $pdo->prepare("SELECT id, name AS team_name, status FROM teams WHERE organization_id = :org_id AND status = 'active' ORDER BY name ASC");
        $stmtTeams->execute([':org_id' => $orgId]);
        $teamsList = $stmtTeams->fetchAll(PDO::FETCH_ASSOC);

        $stmtDefaults = $pdo->prepare("SELECT * FROM team_defaults WHERE organization_id = :org_id LIMIT 1");
        $stmtDefaults->execute([':org_id' => $orgId]);
        $td = $stmtDefaults->fetch(PDO::FETCH_ASSOC);

        $findUserName = function ($id) use ($usersList) {
            foreach ($usersList as $u) {
                if ((int)$u['id'] === (int)$id) return $u['name'];
            }
            return '';
        };

        $findTeamName = function ($id) use ($teamsList) {
            foreach ($teamsList as $t) {
                if ((int)$t['id'] === (int)$id) return $t['team_name'];
            }
            return '';
        };

        if (!$td) {
            $tdData = [
                'default_lead_owner_id'      => $usersList[0]['id'] ?? null,
                'default_lead_owner_name'    => $usersList[0]['name'] ?? '',
                'default_deal_owner_id'      => $usersList[0]['id'] ?? null,
                'default_deal_owner_name'    => $usersList[0]['name'] ?? '',
                'default_task_assignee_id'   => $usersList[0]['id'] ?? null,
                'default_task_assignee_name' => $usersList[0]['name'] ?? '',
                'default_team_id'            => $teamsList[0]['id'] ?? null,
                'default_team_name'          => $teamsList[0]['team_name'] ?? '',
                'default_availability'       => 'Available',
                'working_hours'              => '09:00 - 17:00',
                'sales_target_period'        => 'Monthly',
                'default_monthly_quota'      => '$50,000'
            ];
        } else {
            $tdData = [
                'default_lead_owner_id'      => $td['default_lead_owner_id'],
                'default_lead_owner_name'    => $findUserName($td['default_lead_owner_id']),
                'default_deal_owner_id'      => $td['default_deal_owner_id'],
                'default_deal_owner_name'    => $findUserName($td['default_deal_owner_id']),
                'default_task_assignee_id'   => $td['default_task_assignee_id'],
                'default_task_assignee_name' => $findUserName($td['default_task_assignee_id']),
                'default_team_id'            => $td['default_team_id'],
                'default_team_name'          => $findTeamName($td['default_team_id']),
                'default_availability'       => $td['default_availability'] ?? 'Available',
                'working_hours'              => $td['working_hours'] ?? '09:00 - 17:00',
                'sales_target_period'        => $td['sales_target_period'] ?? 'Monthly',
                'default_monthly_quota'      => $td['default_monthly_quota'] ?? '$50,000'
            ];
        }

        settings_json(true, 'Team defaults retrieved successfully.', [
            'defaults' => $tdData,
            'users'    => $usersList,
            'teams'    => $teamsList
        ]);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $leadOwnerVal    = trim((string)($_POST['default_lead_owner_id'] ?? $_POST['tdOwner'] ?? ''));
        $dealOwnerVal    = trim((string)($_POST['default_deal_owner_id'] ?? $_POST['tdDealOwner'] ?? ''));
        $taskAssigneeVal = trim((string)($_POST['default_task_assignee_id'] ?? $_POST['tdTaskAssignee'] ?? ''));
        $teamVal         = trim((string)($_POST['default_team_id'] ?? $_POST['tdTeam'] ?? ''));

        $availability = trim((string)($_POST['default_availability'] ?? $_POST['tdStatus'] ?? 'Available'));
        $workingHours = trim((string)($_POST['working_hours'] ?? $_POST['tdHours'] ?? '09:00 - 17:00'));
        $targetPeriod = trim((string)($_POST['sales_target_period'] ?? $_POST['tdQuotaPeriod'] ?? 'Monthly'));
        $monthlyQuota = trim((string)($_POST['default_monthly_quota'] ?? $_POST['tdQuota'] ?? '$50,000'));

        $resolveUserId = function ($val) use ($pdo, $orgId) {
            if (empty($val)) return null;
            if (is_numeric($val)) return (int)$val;
            $stmt = $pdo->prepare("SELECT id FROM users WHERE organization_id = :org_id AND name = :name LIMIT 1");
            $stmt->execute([':org_id' => $orgId, ':name' => $val]);
            $res = $stmt->fetchColumn();
            return $res ? (int)$res : null;
        };

        $resolveTeamId = function ($val) use ($pdo, $orgId) {
            if (empty($val)) return null;
            if (is_numeric($val)) return (int)$val;
            $stmt = $pdo->prepare("SELECT id FROM teams WHERE organization_id = :org_id AND name = :name LIMIT 1");
            $stmt->execute([':org_id' => $orgId, ':name' => $val]);
            $res = $stmt->fetchColumn();
            return $res ? (int)$res : null;
        };

        $leadOwnerId    = $resolveUserId($leadOwnerVal);
        $dealOwnerId    = $resolveUserId($dealOwnerVal);
        $taskAssigneeId = $resolveUserId($taskAssigneeVal);
        $teamId         = $resolveTeamId($teamVal);

        $sqlUpsert = "INSERT INTO team_defaults (
                        organization_id, default_lead_owner_id, default_deal_owner_id,
                        default_task_assignee_id, default_team_id, default_availability,
                        working_hours, sales_target_period, default_monthly_quota,
                        created_at, updated_at
                    ) VALUES (
                        :org_id, :lead_owner, :deal_owner, :task_assignee, :team_id,
                        :availability, :working_hours, :target_period, :monthly_quota,
                        NOW(), NOW()
                    ) ON DUPLICATE KEY UPDATE
                        default_lead_owner_id = VALUES(default_lead_owner_id),
                        default_deal_owner_id = VALUES(default_deal_owner_id),
                        default_task_assignee_id = VALUES(default_task_assignee_id),
                        default_team_id = VALUES(default_team_id),
                        default_availability = VALUES(default_availability),
                        working_hours = VALUES(working_hours),
                        sales_target_period = VALUES(sales_target_period),
                        default_monthly_quota = VALUES(default_monthly_quota),
                        updated_at = NOW()";

        $stmtUp = $pdo->prepare($sqlUpsert);
        $stmtUp->execute([
            ':org_id'        => $orgId,
            ':lead_owner'    => $leadOwnerId,
            ':deal_owner'    => $dealOwnerId,
            ':task_assignee' => $taskAssigneeId,
            ':team_id'       => $teamId,
            ':availability'  => $availability,
            ':working_hours' => $workingHours,
            ':target_period' => $targetPeriod,
            ':monthly_quota' => $monthlyQuota
        ]);

        settings_json(true, 'Team defaults updated successfully.', [
            'default_lead_owner_id'    => $leadOwnerId,
            'default_deal_owner_id'    => $dealOwnerId,
            'default_task_assignee_id' => $taskAssigneeId,
            'default_team_id'          => $teamId,
            'default_availability'     => $availability,
            'working_hours'            => $workingHours,
            'sales_target_period'      => $targetPeriod,
            'default_monthly_quota'    => $monthlyQuota
        ]);
    }
}

// -------------------------------------------------------------
// ACTION: ROLES PREVIEW
// -------------------------------------------------------------
if ($action === 'roles_preview') {
    if ($method === 'GET') {
        $stmtRoles = $pdo->prepare("SELECT id, name, slug, description, is_system FROM roles WHERE organization_id = :org_id AND slug != 'admin' ORDER BY id ASC");
        $stmtRoles->execute([':org_id' => $orgId]);
        $rolesList = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

        $roleAccessMatrix = [
            'super_admin' => [
                'leads_contacts'  => 'Full Access',
                'pipeline_deals'  => 'Full Access',
                'reports'         => 'Full Access',
                'team_management' => 'Full Access',
                'settings'        => 'Full Access'
            ],
            'admin' => [
                'leads_contacts'  => 'Full Access',
                'pipeline_deals'  => 'Full Access',
                'reports'         => 'Full Access',
                'team_management' => 'Full Access',
                'settings'        => 'Full Access'
            ],
            'sales_manager' => [
                'leads_contacts'  => 'Full Access',
                'pipeline_deals'  => 'Full Access',
                'reports'         => 'Full Access',
                'team_management' => 'Edit Assigned',
                'settings'        => 'View Only'
            ],
            'sales_rep' => [
                'leads_contacts'  => 'Assigned Only',
                'pipeline_deals'  => 'Assigned Only',
                'reports'         => 'View Own',
                'team_management' => 'No Access',
                'settings'        => 'No Access'
            ],
            'account_executive' => [
                'leads_contacts'  => 'Full Access',
                'pipeline_deals'  => 'Full Access',
                'reports'         => 'View Own',
                'team_management' => 'No Access',
                'settings'        => 'Edit Config'
            ],
            'sales_ops' => [
                'leads_contacts'  => 'Full Access',
                'pipeline_deals'  => 'Full Access',
                'reports'         => 'Full Access',
                'team_management' => 'Full Access',
                'settings'        => 'Edit Config'
            ],
            'viewer' => [
                'leads_contacts'  => 'View Only',
                'pipeline_deals'  => 'View Only',
                'reports'         => 'View Only',
                'team_management' => 'No Access',
                'settings'        => 'No Access'
            ]
        ];

        $rolesPreview = [];
        foreach ($rolesList as $role) {
            $slug = $role['slug'];
            $matrix = $roleAccessMatrix[$slug] ?? [
                'leads_contacts'  => 'View Only',
                'pipeline_deals'  => 'View Only',
                'reports'         => 'View Only',
                'team_management' => 'No Access',
                'settings'        => 'No Access'
            ];

            $rolesPreview[] = [
                'id'              => (int)$role['id'],
                'name'            => $role['name'],
                'slug'            => $role['slug'],
                'description'     => $role['description'],
                'is_system'       => (bool)$role['is_system'],
                'leads_contacts'  => $matrix['leads_contacts'],
                'pipeline_deals'  => $matrix['pipeline_deals'],
                'reports'         => $matrix['reports'],
                'team_management' => $matrix['team_management'],
                'settings'        => $matrix['settings']
            ];
        }

        settings_json(true, 'Roles preview retrieved successfully.', $rolesPreview);
    }
}

// -------------------------------------------------------------
// ACTION: PIPELINE STAGES (GET & POST)
// -------------------------------------------------------------
if ($action === 'pipeline_stages') {
    if ($method === 'GET') {
        $stages = nexflow_get_pipeline_stages($pdo, $orgId);
        settings_json(true, 'Pipeline stages retrieved successfully.', $stages);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $rawStages = $_POST['stages'] ?? $_POST['stages_json'] ?? null;
        if (is_string($rawStages)) {
            $stagesInput = json_decode($rawStages, true);
        } elseif (is_array($rawStages)) {
            $stagesInput = $rawStages;
        } else {
            $stagesInput = [];
        }

        if (empty($stagesInput) || !is_array($stagesInput)) {
            settings_json(false, 'Invalid pipeline stages payload.', [], 400);
        }

        // Validate server-side
        $stageNamesSeen = [];
        foreach ($stagesInput as $s) {
            $name = trim((string)($s['name'] ?? ''));
            if ($name === '') {
                settings_json(false, 'All pipeline stages must have a valid name.', [], 422);
            }

            $lowerName = strtolower($name);
            if (in_array($lowerName, $stageNamesSeen, true)) {
                settings_json(false, "Duplicate stage name '{$name}' is not allowed.", [], 422);
            }
            $stageNamesSeen[] = $lowerName;

            $prob = isset($s['prob']) ? (float)$s['prob'] : (isset($s['probability']) ? (float)$s['probability'] : 0);
            if ($prob < 0 || $prob > 100) {
                settings_json(false, "Stage probability for '{$name}' must be between 0% and 100%.", [], 422);
            }
        }

        $pdo->beginTransaction();

        try {
            $stmtExisting = $pdo->prepare("SELECT id, name, is_system FROM pipeline_stages WHERE organization_id = :org_id AND is_active = 1");
            $stmtExisting->execute([':org_id' => $orgId]);
            $existingDbStages = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);

            $existingIdsInDb = array_column($existingDbStages, 'id');
            $processedIds = [];

            foreach ($stagesInput as $idx => $s) {
                $sortOrder = $idx + 1;
                $name      = trim((string)$s['name']);
                $color     = trim((string)($s['color'] ?? '#3B82F6'));
                $prob      = isset($s['prob']) ? (float)$s['prob'] : (isset($s['probability']) ? (float)$s['probability'] : 0);
                $isSystem  = in_array($name, ['Closed Won', 'Closed Lost'], true) ? 1 : (!empty($s['is_system']) ? 1 : 0);

                $stageId = isset($s['id']) && is_numeric($s['id']) ? (int)$s['id'] : 0;

                if ($stageId > 0 && in_array($stageId, $existingIdsInDb, true)) {
                    $stmtUp = $pdo->prepare("UPDATE pipeline_stages SET 
                                                name = :name,
                                                color = :color,
                                                probability = :prob,
                                                win_rate_pct = :win_rate,
                                                sort_order = :sort_order,
                                                is_system = :is_system,
                                                updated_at = NOW()
                                            WHERE id = :id AND organization_id = :org_id");
                    $stmtUp->execute([
                        ':name'       => $name,
                        ':color'      => $color,
                        ':prob'       => $prob,
                        ':win_rate'   => (int)$prob,
                        ':sort_order' => $sortOrder,
                        ':is_system'  => $isSystem,
                        ':id'         => $stageId,
                        ':org_id'     => $orgId
                    ]);
                    $processedIds[] = $stageId;
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO pipeline_stages (
                                                organization_id, name, color, probability, win_rate_pct,
                                                sort_order, is_system, is_active, created_at, updated_at
                                            ) VALUES (
                                                :org_id, :name, :color, :prob, :win_rate,
                                                :sort_order, :is_system, 1, NOW(), NOW()
                                            )");
                    $stmtIns->execute([
                        ':org_id'     => $orgId,
                        ':name'       => $name,
                        ':color'      => $color,
                        ':prob'       => $prob,
                        ':win_rate'   => (int)$prob,
                        ':sort_order' => $sortOrder,
                        ':is_system'  => $isSystem
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    $processedIds[] = $newId;
                }
            }

            // Remove stages that were deleted in UI (unless system stage)
            foreach ($existingDbStages as $dbStage) {
                $dbId = (int)$dbStage['id'];
                if (!in_array($dbId, $processedIds, true)) {
                    if ((int)$dbStage['is_system'] === 1 || in_array($dbStage['name'], ['Closed Won', 'Closed Lost'], true)) {
                        $pdo->rollBack();
                        settings_json(false, "System stages (Closed Won and Closed Lost) cannot be deleted.", [], 422);
                    }

                    $stmtDel = $pdo->prepare("DELETE FROM pipeline_stages WHERE id = :id AND organization_id = :org_id AND is_system = 0");
                    $stmtDel->execute([':id' => $dbId, ':org_id' => $orgId]);
                }
            }

            $pdo->commit();

            $updatedStages = nexflow_get_pipeline_stages($pdo, $orgId);
            settings_json(true, 'Pipeline settings saved successfully.', $updatedStages);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            settings_json(false, 'Failed to save pipeline settings: ' . $e->getMessage(), [], 500);
        }
    }
}

// -------------------------------------------------------------
// ACTION: DELETE PIPELINE STAGE
// -------------------------------------------------------------
if ($action === 'delete_pipeline_stage') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $stageId = (int)($_POST['stage_id'] ?? $_POST['id'] ?? 0);
    if ($stageId <= 0) {
        settings_json(false, 'Invalid stage ID.', [], 400);
    }

    $stmtStage = $pdo->prepare("SELECT id, name, is_system FROM pipeline_stages WHERE id = :id AND organization_id = :org_id");
    $stmtStage->execute([':id' => $stageId, ':org_id' => $orgId]);
    $stage = $stmtStage->fetch(PDO::FETCH_ASSOC);

    if (!$stage) {
        settings_json(false, 'Pipeline stage not found.', [], 404);
    }

    if ((int)$stage['is_system'] === 1 || in_array($stage['name'], ['Closed Won', 'Closed Lost'], true)) {
        settings_json(false, 'System stages (Closed Won and Closed Lost) cannot be deleted.', [], 422);
    }

    $isUsed = false;
    $usageTables = ['deals', 'leads', 'opportunities'];
    foreach ($usageTables as $table) {
        try {
            $stmtCheckUse = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE organization_id = :org_id AND (stage_id = :id OR stage = :name)");
            $stmtCheckUse->execute([':org_id' => $orgId, ':id' => $stageId, ':name' => $stage['name']]);
            if ((int)$stmtCheckUse->fetchColumn() > 0) {
                $isUsed = true;
                break;
            }
        } catch (Throwable $e) {
            // Table may not exist yet
        }
    }

    if ($isUsed) {
        settings_json(false, "Cannot delete stage '{$stage['name']}' because it is currently being used by CRM records.", [], 422);
    }

    $stmtDel = $pdo->prepare("DELETE FROM pipeline_stages WHERE id = :id AND organization_id = :org_id AND is_system = 0");
    $stmtDel->execute([':id' => $stageId, ':org_id' => $orgId]);

    $remainingStages = nexflow_get_pipeline_stages($pdo, $orgId);
    settings_json(true, 'Pipeline stage deleted successfully.', $remainingStages);
}

// -------------------------------------------------------------
// ACTION: RESET PIPELINE STAGES
// -------------------------------------------------------------
if ($action === 'reset_pipeline_stages') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $pdo->beginTransaction();
    try {
        $stmtDel = $pdo->prepare("DELETE FROM pipeline_stages WHERE organization_id = :org_id");
        $stmtDel->execute([':org_id' => $orgId]);

        $defaultStages = [
            ['name' => 'Prospect', 'color' => '#64748B', 'probability' => 10.00, 'sort_order' => 1, 'is_system' => 0],
            ['name' => 'Qualified', 'color' => '#2563EB', 'probability' => 30.00, 'sort_order' => 2, 'is_system' => 0],
            ['name' => 'Proposal', 'color' => '#F59E0B', 'probability' => 60.00, 'sort_order' => 3, 'is_system' => 0],
            ['name' => 'Negotiation', 'color' => '#8B5CF6', 'probability' => 80.00, 'sort_order' => 4, 'is_system' => 0],
            ['name' => 'Closed Won', 'color' => '#10B981', 'probability' => 100.00, 'sort_order' => 5, 'is_system' => 1],
            ['name' => 'Closed Lost', 'color' => '#EF4444', 'probability' => 0.00, 'sort_order' => 6, 'is_system' => 1],
        ];

        $stmtIns = $pdo->prepare("INSERT INTO pipeline_stages (organization_id, name, color, probability, win_rate_pct, sort_order, is_system, is_active, created_at, updated_at) VALUES (:org_id, :name, :color, :probability, :win_rate, :sort_order, :is_system, 1, NOW(), NOW())");

        foreach ($defaultStages as $stage) {
            $stmtIns->execute([
                ':org_id'       => $orgId,
                ':name'         => $stage['name'],
                ':color'        => $stage['color'],
                ':probability'  => $stage['probability'],
                ':win_rate'     => (int)$stage['probability'],
                ':sort_order'   => $stage['sort_order'],
                ':is_system'    => $stage['is_system']
            ]);
        }

        $pdo->commit();

        $stages = nexflow_get_pipeline_stages($pdo, $orgId);
        settings_json(true, 'Pipeline reset to default stages.', $stages);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        settings_json(false, 'Failed to reset pipeline stages: ' . $e->getMessage(), [], 500);
    }
}

// -------------------------------------------------------------
// ACTION: LEAD SETTINGS (GET & POST)
// -------------------------------------------------------------
if ($action === 'lead_settings') {
    if ($method === 'GET') {
        $settings = nexflow_get_lead_settings($pdo, $orgId);
        settings_json(true, 'Lead settings retrieved successfully.', $settings);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $status = trim((string)($_POST['default_lead_status'] ?? $_POST['lsDefaultStatus'] ?? 'New'));
        $source = trim((string)($_POST['default_lead_source'] ?? $_POST['lsDefaultSource'] ?? 'Website'));
        $ownerIdRaw = $_POST['default_lead_owner_id'] ?? $_POST['default_lead_owner'] ?? $_POST['lsDefaultOwner'] ?? null;
        $scoreThresholdRaw = $_POST['lead_score_threshold'] ?? $_POST['lsScoreThreshold'] ?? null;
        $inactivityDaysRaw = $_POST['inactivity_threshold_days'] ?? $_POST['lsInactivityDays'] ?? null;
        $scoreEnabledRaw = $_POST['lead_scoring_enabled'] ?? $_POST['lsScoreEnabled'] ?? null;
        $dupDetectionRaw = $_POST['duplicate_detection_enabled'] ?? $_POST['lsDuplicateDetection'] ?? null;
        $autoFollowupRaw = $_POST['auto_followup_enabled'] ?? $_POST['lsAutoFollowup'] ?? null;

        if (empty($status)) {
            settings_json(false, 'Default lead status cannot be empty.', [], 422);
        }
        if (empty($source)) {
            settings_json(false, 'Default lead source cannot be empty.', [], 422);
        }

        if ($scoreThresholdRaw === null || !is_numeric($scoreThresholdRaw)) {
            settings_json(false, 'Lead score threshold must be a valid number between 0 and 100.', [], 422);
        }
        $scoreThreshold = (int)$scoreThresholdRaw;
        if ($scoreThreshold < 0 || $scoreThreshold > 100) {
            settings_json(false, 'Lead score threshold must be between 0 and 100.', [], 422);
        }

        if ($inactivityDaysRaw === null || !is_numeric($inactivityDaysRaw)) {
            settings_json(false, 'Inactivity threshold days must be a non-negative integer.', [], 422);
        }
        $inactivityDays = (int)$inactivityDaysRaw;
        if ($inactivityDays < 0) {
            settings_json(false, 'Inactivity threshold days cannot be negative.', [], 422);
        }

        $ownerId = (int)$ownerIdRaw;
        $stmtOwner = $pdo->prepare("SELECT id FROM users WHERE id = :id AND organization_id = :org_id AND status = 'active'");
        $stmtOwner->execute([':id' => $ownerId, ':org_id' => $orgId]);
        if (!$stmtOwner->fetch()) {
            settings_json(false, 'Selected default lead owner is invalid or inactive.', [], 422);
        }

        $parseBool = function($val) {
            if ($val === true || $val === 1 || $val === '1' || $val === 'true' || $val === 'on') return 1;
            return 0;
        };

        $scoreEnabled = $parseBool($scoreEnabledRaw);
        $dupDetectionEnabled = $parseBool($dupDetectionRaw);
        $autoFollowupEnabled = $parseBool($autoFollowupRaw);

        nexflow_ensure_crm_settings_table($pdo);
        $pdo->beginTransaction();
        try {
            nexflow_set_crm_setting($pdo, $orgId, 'default_lead_status', $status);
            nexflow_set_crm_setting($pdo, $orgId, 'default_lead_source', $source);
            nexflow_set_crm_setting($pdo, $orgId, 'default_lead_owner', $ownerId);
            nexflow_set_crm_setting($pdo, $orgId, 'lead_score_threshold', $scoreThreshold);
            nexflow_set_crm_setting($pdo, $orgId, 'inactivity_threshold_days', $inactivityDays);
            nexflow_set_crm_setting($pdo, $orgId, 'lead_scoring_enabled', $scoreEnabled);
            nexflow_set_crm_setting($pdo, $orgId, 'duplicate_detection_enabled', $dupDetectionEnabled);
            nexflow_set_crm_setting($pdo, $orgId, 'auto_followup_enabled', $autoFollowupEnabled);

            $stmtTD = $pdo->prepare("UPDATE team_defaults SET default_lead_owner_id = :owner_id WHERE organization_id = :org_id");
            $stmtTD->execute([':owner_id' => $ownerId, ':org_id' => $orgId]);

            $pdo->commit();

            $updated = nexflow_get_lead_settings($pdo, $orgId);
            settings_json(true, 'Lead settings saved successfully.', $updated);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            settings_json(false, 'Failed to save lead settings: ' . $e->getMessage(), [], 500);
        }
    }
}

// -------------------------------------------------------------
// ACTION: TASK SETTINGS (GET & POST)
// -------------------------------------------------------------
if ($action === 'task_settings') {
    if ($method === 'GET') {
        $settings = nexflow_get_task_settings($pdo, $orgId);
        settings_json(true, 'Task settings retrieved successfully.', $settings);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $status = trim((string)($_POST['task_default_status'] ?? $_POST['tsDefaultStatus'] ?? 'Pending'));
        $priority = trim((string)($_POST['task_default_priority'] ?? $_POST['tsDefaultPriority'] ?? 'Medium'));
        $reminder = trim((string)($_POST['task_default_reminder'] ?? $_POST['tsDefaultReminder'] ?? '15 minutes before'));
        $view = trim((string)($_POST['task_default_view'] ?? $_POST['tsDefaultView'] ?? 'list'));
        $showCompletedRaw = $_POST['task_show_completed'] ?? $_POST['tsShowCompleted'] ?? null;

        $allowedStatuses = ['Pending', 'Not Started', 'In Progress', 'Waiting', 'Completed'];
        if (empty($status) || !in_array($status, $allowedStatuses, true)) {
            settings_json(false, 'Invalid task default status. Allowed values: Pending, Not Started, In Progress, Waiting, Completed.', [], 422);
        }

        $allowedPriorities = ['Low', 'Medium', 'High', 'Urgent'];
        if (empty($priority) || !in_array($priority, $allowedPriorities, true)) {
            settings_json(false, 'Invalid task default priority. Allowed values: Low, Medium, High, Urgent.', [], 422);
        }

        $allowedReminders = [
            'none', 'None',
            '5_minutes', '5 minutes before',
            '15_minutes', '15 minutes before',
            '30_minutes', '30 minutes before',
            '1_hour', '1 hour before',
            '1_day', '1 day before'
        ];
        if (empty($reminder) || !in_array($reminder, $allowedReminders, true)) {
            settings_json(false, 'Invalid task default reminder setting.', [], 422);
        }

        $allowedViews = ['list', 'board', 'calendar'];
        if (empty($view) || !in_array(strtolower($view), $allowedViews, true)) {
            settings_json(false, 'Invalid task default view. Allowed values: list, board, calendar.', [], 422);
        }
        $view = strtolower($view);

        $parseBool = function($val) {
            if ($val === true || $val === 1 || $val === '1' || $val === 'true' || $val === 'on') return 1;
            return 0;
        };

        $showCompleted = $parseBool($showCompletedRaw);

        nexflow_ensure_crm_settings_table($pdo);
        $pdo->beginTransaction();
        try {
            nexflow_set_crm_setting($pdo, $orgId, 'task_default_status', $status);
            nexflow_set_crm_setting($pdo, $orgId, 'task_default_priority', $priority);
            nexflow_set_crm_setting($pdo, $orgId, 'task_default_reminder', $reminder);
            nexflow_set_crm_setting($pdo, $orgId, 'task_default_view', $view);
            nexflow_set_crm_setting($pdo, $orgId, 'task_show_completed', $showCompleted);

            $pdo->commit();

            $updated = nexflow_get_task_settings($pdo, $orgId);
            settings_json(true, 'Task settings saved successfully.', $updated);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            settings_json(false, 'Failed to save task settings: ' . $e->getMessage(), [], 500);
        }
    }
}

// -------------------------------------------------------------
// ACTION: EMAIL PREFERENCES (GET & POST)
// -------------------------------------------------------------
if ($action === 'email_preferences') {
    if ($method === 'GET') {
        $settings = nexflow_get_email_preferences($pdo, $orgId);
        $responseData = array_merge($settings, ['settings' => $settings]);
        settings_json(true, 'Email preferences retrieved successfully.', $responseData);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $senderName = trim((string)($_POST['email_sender_display_name'] ?? $_POST['emailSenderName'] ?? ''));
        $replyTo = trim((string)($_POST['email_reply_to'] ?? $_POST['emailReplyTo'] ?? ''));
        $signature = (string)($_POST['email_signature'] ?? $_POST['emailSignature'] ?? '');
        $includeSigRaw = $_POST['email_include_signature'] ?? $_POST['emailIncludeSig'] ?? null;

        // Validation 1: Sender Display Name max length (150 chars)
        if (mb_strlen($senderName) > 150) {
            settings_json(false, 'Sender display name cannot exceed 150 characters.', [], 422);
        }

        // Validation 2: Reply-to Email Address
        if ($replyTo !== '') {
            if (mb_strlen($replyTo) > 150 || !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                settings_json(false, 'Please enter a valid reply-to email address.', [], 422);
            }
        }

        // Validation 3: Signature max length (5000 chars)
        if (mb_strlen($signature) > 5000) {
            settings_json(false, 'Email signature cannot exceed 5000 characters.', [], 422);
        }

        // Validation 4: Include Signature by Default (0 or 1)
        if ($includeSigRaw === null) {
            $includeSig = 1;
        } else {
            if ($includeSigRaw === '1' || $includeSigRaw === 1 || $includeSigRaw === true || $includeSigRaw === 'true' || $includeSigRaw === 'on') {
                $includeSig = 1;
            } elseif ($includeSigRaw === '0' || $includeSigRaw === 0 || $includeSigRaw === false || $includeSigRaw === 'false' || $includeSigRaw === 'off') {
                $includeSig = 0;
            } else {
                settings_json(false, 'Invalid value for include signature by default. Must be 1 or 0.', [], 422);
            }
        }

        nexflow_ensure_crm_settings_table($pdo);
        $pdo->beginTransaction();
        try {
            nexflow_set_crm_setting($pdo, $orgId, 'email_sender_display_name', $senderName);
            nexflow_set_crm_setting($pdo, $orgId, 'email_reply_to', $replyTo);
            nexflow_set_crm_setting($pdo, $orgId, 'email_signature', $signature);
            nexflow_set_crm_setting($pdo, $orgId, 'email_include_signature', $includeSig);

            $pdo->commit();

            $updated = nexflow_get_email_preferences($pdo, $orgId);
            $responseData = array_merge($updated, ['settings' => $updated]);
            settings_json(true, 'Email preferences saved successfully.', $responseData);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            settings_json(false, 'Failed to save email preferences: ' . $e->getMessage(), [], 500);
        }
    }
}

// -------------------------------------------------------------
// ACTION: CALLING PREFERENCES (GET & POST)
// -------------------------------------------------------------
if ($action === 'calling_preferences') {
    if ($method === 'GET') {
        $settings = nexflow_get_calling_preferences($pdo, $orgId);
        $responseData = array_merge($settings, ['settings' => $settings]);
        settings_json(true, 'Calling preferences retrieved successfully.', $responseData);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $countryCodeRaw = $_POST['calling_default_country_code'] ?? $_POST['callingDefaultCountryCode'] ?? $_POST['callCountryCode'] ?? '';
        $countryCode = trim((string)$countryCodeRaw);
        $allowedCountryCodes = ['', '+1', '+44', '+33', '+91', '+61', '+49', '+81', '+86', '+34', '+39', '+55', '+52', '+27', '+47', '+46', '+31', '+41'];
        if (!in_array($countryCode, $allowedCountryCodes, true)) {
            settings_json(false, 'Invalid default country code selected.', [], 422);
        }

        $statusRaw = $_POST['calling_default_status'] ?? $_POST['callingDefaultStatus'] ?? $_POST['callAvailability'] ?? '';
        $status = trim((string)$statusRaw);
        $allowedStatuses = ['', 'Available', 'Busy', 'Away', 'Offline'];
        if (!in_array($status, $allowedStatuses, true)) {
            settings_json(false, 'Invalid default status selected.', [], 422);
        }

        $autoNotesRaw = $_POST['calling_auto_open_notes'] ?? $_POST['callingAutoOpenNotes'] ?? $_POST['callAutoNotes'] ?? null;
        if ($autoNotesRaw === null) {
            $autoNotes = 0;
        } else {
            if ($autoNotesRaw === '1' || $autoNotesRaw === 1 || $autoNotesRaw === true || $autoNotesRaw === 'true' || $autoNotesRaw === 'on') {
                $autoNotes = 1;
            } elseif ($autoNotesRaw === '0' || $autoNotesRaw === 0 || $autoNotesRaw === false || $autoNotesRaw === 'false' || $autoNotesRaw === 'off') {
                $autoNotes = 0;
            } else {
                settings_json(false, 'Invalid value for auto-open call notes. Must be 1 or 0.', [], 422);
            }
        }

        $floatingWidgetRaw = $_POST['calling_show_floating_widget'] ?? $_POST['callingShowFloatingWidget'] ?? $_POST['callFloatingWidget'] ?? null;
        if ($floatingWidgetRaw === null) {
            $floatingWidget = 0;
        } else {
            if ($floatingWidgetRaw === '1' || $floatingWidgetRaw === 1 || $floatingWidgetRaw === true || $floatingWidgetRaw === 'true' || $floatingWidgetRaw === 'on') {
                $floatingWidget = 1;
            } elseif ($floatingWidgetRaw === '0' || $floatingWidgetRaw === 0 || $floatingWidgetRaw === false || $floatingWidgetRaw === 'false' || $floatingWidgetRaw === 'off') {
                $floatingWidget = 0;
            } else {
                settings_json(false, 'Invalid value for show floating widget. Must be 1 or 0.', [], 422);
            }
        }

        nexflow_ensure_crm_settings_table($pdo);
        $pdo->beginTransaction();
        try {
            nexflow_set_crm_setting($pdo, $orgId, 'calling_default_country_code', $countryCode);
            nexflow_set_crm_setting($pdo, $orgId, 'calling_default_status', $status);
            nexflow_set_crm_setting($pdo, $orgId, 'calling_auto_open_notes', $autoNotes);
            nexflow_set_crm_setting($pdo, $orgId, 'calling_show_floating_widget', $floatingWidget);

            $pdo->commit();

            $updated = nexflow_get_calling_preferences($pdo, $orgId);
            $responseData = array_merge($updated, ['settings' => $updated]);
            settings_json(true, 'Calling preferences saved successfully.', $responseData);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            settings_json(false, 'Failed to save calling preferences: ' . $e->getMessage(), [], 500);
        }
    }
}


// -------------------------------------------------------------
// ACTION: INBOX PREFERENCES (GET & POST)
// -------------------------------------------------------------
if ($action === 'inbox_preferences') {
    if ($method === 'GET') {
        $settings = nexflow_get_inbox_preferences($pdo, $orgId);
        $responseData = array_merge($settings, ['settings' => $settings]);
        settings_json(true, 'Inbox preferences retrieved successfully.', $responseData);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $folderRaw = $_POST['inbox_default_folder'] ?? $_POST['inboxDefaultFolder'] ?? '';
        $folder = trim((string)$folderRaw);
        $allowedFolders = ['', 'inbox', 'assigned', 'unread'];
        if (!in_array($folder, $allowedFolders, true)) {
            settings_json(false, 'Invalid default folder selected.', [], 422);
        }

        $sortRaw = $_POST['inbox_sort_order'] ?? $_POST['inboxSortOrder'] ?? '';
        $sort = trim((string)$sortRaw);
        $allowedSorts = ['', 'newest', 'oldest'];
        if (!in_array($sort, $allowedSorts, true)) {
            settings_json(false, 'Invalid sort order selected.', [], 422);
        }

        $markReadRaw = $_POST['inbox_mark_read_on_open'] ?? $_POST['inboxMarkReadOnOpen'] ?? null;
        if ($markReadRaw === null) {
            $markRead = 0;
        } else {
            if ($markReadRaw === '1' || $markReadRaw === 1 || $markReadRaw === true || $markReadRaw === 'true' || $markReadRaw === 'on') {
                $markRead = 1;
            } elseif ($markReadRaw === '0' || $markReadRaw === 0 || $markReadRaw === false || $markReadRaw === 'false' || $markReadRaw === 'off') {
                $markRead = 0;
            } else {
                settings_json(false, 'Invalid value for mark read on open. Must be 1 or 0.', [], 422);
            }
        }

        $showContextRaw = $_POST['inbox_show_contact_context'] ?? $_POST['inboxShowContextOnClick'] ?? null;
        if ($showContextRaw === null) {
            $showContext = 0;
        } else {
            if ($showContextRaw === '1' || $showContextRaw === 1 || $showContextRaw === true || $showContextRaw === 'true' || $showContextRaw === 'on') {
                $showContext = 1;
            } elseif ($showContextRaw === '0' || $showContextRaw === 0 || $showContextRaw === false || $showContextRaw === 'false' || $showContextRaw === 'off') {
                $showContext = 0;
            } else {
                settings_json(false, 'Invalid value for show contact context. Must be 1 or 0.', [], 422);
            }
        }

        nexflow_ensure_crm_settings_table($pdo);
        $pdo->beginTransaction();
        try {
            nexflow_set_crm_setting($pdo, $orgId, 'inbox_default_folder', $folder);
            nexflow_set_crm_setting($pdo, $orgId, 'inbox_sort_order', $sort);
            nexflow_set_crm_setting($pdo, $orgId, 'inbox_mark_read_on_open', $markRead);
            nexflow_set_crm_setting($pdo, $orgId, 'inbox_show_contact_context', $showContext);

            $pdo->commit();

            $updated = nexflow_get_inbox_preferences($pdo, $orgId);
            $responseData = array_merge($updated, ['settings' => $updated]);
            settings_json(true, 'Inbox preferences saved successfully.', $responseData);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            settings_json(false, 'Failed to save inbox preferences: ' . $e->getMessage(), [], 500);
        }
    }
}

// -------------------------------------------------------------
// ACTION: INTEGRATIONS (GET)
// -------------------------------------------------------------
if ($action === 'integrations') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $integrations = nexflow_get_integrations($pdo, $orgId);
    http_response_code(200);
    echo json_encode([
        'success'      => true,
        'message'      => 'Integrations retrieved successfully.',
        'integrations' => $integrations,
        'data'         => $integrations
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// ACTION: CONNECT INTEGRATION (POST)
// -------------------------------------------------------------
if ($action === 'connect_integration') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $key = trim((string)($_POST['integration_key'] ?? $_POST['key'] ?? $_REQUEST['integration_key'] ?? ''));
    $allowedKeys = ['google_calendar', 'outlook_calendar', 'gmail', 'whatsapp_business', 'twilio', 'zapier'];
    if (!in_array($key, $allowedKeys, true)) {
        settings_json(false, 'Invalid integration key provided.', [], 422);
    }

    $res = nexflow_connect_integration($pdo, $orgId, $key);
    http_response_code($res['http_status'] ?? 200);
    echo json_encode([
        'success' => $res['success'],
        'code'    => $res['code'] ?? 'INTEGRATION_NOT_CONFIGURED',
        'message' => $res['message'],
        'data'    => $res['data'] ?? []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// ACTION: DISCONNECT INTEGRATION (POST)
// -------------------------------------------------------------
if ($action === 'disconnect_integration') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $key = trim((string)($_POST['integration_key'] ?? $_POST['key'] ?? $_REQUEST['integration_key'] ?? ''));
    $allowedKeys = ['google_calendar', 'outlook_calendar', 'gmail', 'whatsapp_business', 'twilio', 'zapier'];
    if (!in_array($key, $allowedKeys, true)) {
        settings_json(false, 'Invalid integration key provided.', [], 422);
    }

    $res = nexflow_disconnect_integration($pdo, $orgId, $key);
    settings_json($res['success'], $res['message'], $res['data'] ?? [], $res['http_status'] ?? 200);
}

// -------------------------------------------------------------
// ACTION: TOGGLE INTEGRATION (POST)
// -------------------------------------------------------------
if ($action === 'toggle_integration') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $key = trim((string)($_POST['integration_key'] ?? $_POST['key'] ?? $_REQUEST['integration_key'] ?? ''));
    $allowedKeys = ['google_calendar', 'outlook_calendar', 'gmail', 'whatsapp_business', 'twilio', 'zapier'];
    if (!in_array($key, $allowedKeys, true)) {
        settings_json(false, 'Invalid integration key provided.', [], 422);
    }

    $enabledRaw = $_POST['is_enabled'] ?? $_REQUEST['is_enabled'] ?? null;
    if ($enabledRaw !== '0' && $enabledRaw !== '1' && $enabledRaw !== 0 && $enabledRaw !== 1) {
        settings_json(false, 'Invalid value for is_enabled. Must be 1 or 0.', [], 422);
    }

    $res = nexflow_toggle_integration($pdo, $orgId, $key, (int)$enabledRaw);
    settings_json($res['success'], $res['message'], $res['data'] ?? [], $res['http_status'] ?? 200);
}

// -------------------------------------------------------------
// ACTION: CHECK DUPLICATE LEAD
// -------------------------------------------------------------
if ($action === 'check_duplicate_lead') {
    $email = $_REQUEST['email'] ?? null;
    $phone = $_REQUEST['phone'] ?? null;
    $excludeId = isset($_REQUEST['exclude_id']) ? (int)$_REQUEST['exclude_id'] : null;
    $res = nexflow_check_duplicate_lead($pdo, $orgId, $email, $phone, $excludeId);
    settings_json($res['is_duplicate'] ? false : true, $res['message'], $res);
}

// -------------------------------------------------------------
// ACTION: CUSTOM FIELDS (GET & POST)
// -------------------------------------------------------------
if ($action === 'custom_fields') {
    nexflow_ensure_custom_fields_tables($pdo);

    if ($method === 'GET') {
        $targetObject = $_GET['target_object'] ?? $_GET['object'] ?? null;
        $fields = nexflow_get_custom_fields($pdo, $orgId, $targetObject);
        settings_json(true, 'Custom fields retrieved successfully.', $fields);
    }

    if ($method === 'POST') {
        if (!can_manage_workspace_settings($authUser)) {
            settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
        }

        $label = trim((string)($_POST['field_label'] ?? $_POST['name'] ?? $_POST['label'] ?? ''));
        $targetObject = trim((string)($_POST['target_object'] ?? $_POST['object'] ?? 'Leads'));
        $type = trim((string)($_POST['field_type'] ?? $_POST['type'] ?? 'Text'));
        $isRequiredRaw = $_POST['is_required'] ?? $_POST['required'] ?? 0;
        $isActiveRaw = $_POST['is_active'] ?? $_POST['active'] ?? 1;
        $rawOptions = $_POST['options'] ?? null;

        if (empty($label)) {
            settings_json(false, 'Field label is required.', [], 422);
        }

        $allowedObjects = ['Leads', 'Contacts', 'Companies', 'Deals', 'Tasks'];
        $canonicalObject = null;
        foreach ($allowedObjects as $ao) {
            if (strtolower($ao) === strtolower($targetObject)) {
                $canonicalObject = $ao;
                break;
            }
        }
        if (!$canonicalObject) {
            settings_json(false, 'Invalid target object. Must be Leads, Contacts, Companies, Deals, or Tasks.', [], 422);
        }

        $allowedTypes = ['Text', 'Text Area', 'Number', 'Email', 'Phone', 'Date', 'Date & Time', 'Dropdown', 'Multi Select', 'Checkbox', 'Radio', 'URL', 'Currency'];
        $canonicalType = null;
        foreach ($allowedTypes as $at) {
            if (strtolower($at) === strtolower($type)) {
                $canonicalType = $at;
                break;
            }
        }
        if (!$canonicalType) {
            $canonicalType = 'Text';
        }

        $parseBool = function($val) {
            if ($val === true || $val === 1 || $val === '1' || $val === 'true' || $val === 'on') return 1;
            return 0;
        };

        $isRequired = $parseBool($isRequiredRaw);
        $isActive = $parseBool($isActiveRaw);

        $fieldKey = nexflow_generate_custom_field_key($pdo, $orgId, $canonicalObject, $label);

        $optionsList = [];
        if (is_string($rawOptions)) {
            $optionsList = array_map('trim', explode(',', $rawOptions));
        } elseif (is_array($rawOptions)) {
            $optionsList = array_map('trim', $rawOptions);
        }
        $optionsList = array_filter($optionsList, function($o) { return $o !== ''; });

        nexflow_ensure_custom_fields_tables($pdo);
        $pdo->beginTransaction();
        try {
            $stmtSort = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM crm_custom_fields WHERE organization_id = :org_id AND LOWER(target_object) = LOWER(:target)");
            $stmtSort->execute([':org_id' => $orgId, ':target' => $canonicalObject]);
            $nextSort = (int)$stmtSort->fetchColumn();

            $stmtIns = $pdo->prepare("INSERT INTO crm_custom_fields (organization_id, field_key, field_label, target_object, field_type, is_required, is_active, sort_order, created_by, created_at, updated_at)
                VALUES (:org_id, :key, :label, :target, :type, :req, :active, :sort, :created_by, NOW(), NOW())");
            $stmtIns->execute([
                ':org_id'     => $orgId,
                ':key'        => $fieldKey,
                ':label'      => $label,
                ':target'     => $canonicalObject,
                ':type'       => $canonicalType,
                ':req'        => $isRequired,
                ':active'     => $isActive,
                ':sort'       => $nextSort,
                ':created_by' => $authUser['id'] ?? null
            ]);
            $fieldId = (int)$pdo->lastInsertId();

            if (!empty($optionsList)) {
                $stmtOpt = $pdo->prepare("INSERT INTO crm_custom_field_options (custom_field_id, option_value, option_label, sort_order, created_at) VALUES (:field_id, :val, :lbl, :sort, NOW())");
                foreach (array_values($optionsList) as $idx => $optVal) {
                    $stmtOpt->execute([
                        ':field_id' => $fieldId,
                        ':val'      => $optVal,
                        ':lbl'      => $optVal,
                        ':sort'     => $idx + 1
                    ]);
                }
            }

            $pdo->commit();

            $allFields = nexflow_get_custom_fields($pdo, $orgId, $canonicalObject);
            $newField = null;
            foreach ($allFields as $af) {
                if ($af['id'] === $fieldId) {
                    $newField = $af;
                    break;
                }
            }

            settings_json(true, 'Custom field created successfully.', [
                'field'  => $newField,
                'fields' => $allFields
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            settings_json(false, 'Failed to create custom field: ' . $e->getMessage(), [], 500);
        }
    }
}

// -------------------------------------------------------------
// ACTION: DELETE CUSTOM FIELD
// -------------------------------------------------------------
if ($action === 'delete_custom_field') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_POST['custom_field_id']) ? (int)$_POST['custom_field_id'] : 0);
    if ($id <= 0) {
        settings_json(false, 'Invalid custom field ID.', [], 422);
    }

    $stmtCheck = $pdo->prepare("SELECT target_object FROM crm_custom_fields WHERE id = :id AND organization_id = :org_id");
    $stmtCheck->execute([':id' => $id, ':org_id' => $orgId]);
    $field = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$field) {
        settings_json(false, 'Custom field not found.', [], 404);
    }

    $targetObject = $field['target_object'];
    nexflow_delete_custom_field($pdo, $orgId, $id);

    $remaining = nexflow_get_custom_fields($pdo, $orgId, $targetObject);
    settings_json(true, 'Custom field deleted successfully.', $remaining);
}

// -------------------------------------------------------------
// ACTION: TOGGLE CUSTOM FIELD STATUS
// -------------------------------------------------------------
if ($action === 'toggle_custom_field_status') {
    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    $stmtUp = $pdo->prepare("UPDATE crm_custom_fields SET is_active = :active WHERE id = :id AND organization_id = :org_id");
    $stmtUp->execute([':active' => $isActive, ':id' => $id, ':org_id' => $orgId]);

    settings_json(true, 'Custom field status updated.', ['id' => $id, 'is_active' => $isActive]);
}

// -------------------------------------------------------------
// ACTION: CUSTOM FIELD VALUES (GET & POST)
// -------------------------------------------------------------
if ($action === 'custom_field_values') {
    $entityType = $_REQUEST['entity_type'] ?? $_REQUEST['object'] ?? null;
    $entityId = isset($_REQUEST['entity_id']) ? (int)$_REQUEST['entity_id'] : 0;

    if (empty($entityType) || $entityId <= 0) {
        settings_json(false, 'Entity type and entity ID are required.', [], 422);
    }

    if ($method === 'GET') {
        $values = nexflow_get_entity_custom_values($pdo, $orgId, $entityType, $entityId);
        $definitions = nexflow_get_custom_fields($pdo, $orgId, $entityType, true);
        settings_json(true, 'Custom field values retrieved.', [
            'definitions' => $definitions,
            'values'      => $values
        ]);
    }

    if ($method === 'POST') {
        $rawValues = $_POST['values'] ?? [];
        if (is_string($rawValues)) {
            $rawValues = json_decode($rawValues, true) ?? [];
        }

        $errors = nexflow_validate_entity_custom_fields($pdo, $orgId, $entityType, $rawValues);
        if (!empty($errors)) {
            settings_json(false, implode(' ', $errors), ['errors' => $errors], 422);
        }

        nexflow_save_entity_custom_values($pdo, $orgId, $entityType, $entityId, $rawValues);
        $saved = nexflow_get_entity_custom_values($pdo, $orgId, $entityType, $entityId);
        settings_json(true, 'Custom field values saved successfully.', $saved);
    }
}

// -------------------------------------------------------------
// ACTION: RESET ALL SETTINGS TO FACTORY DEFAULT
// -------------------------------------------------------------
if ($action === 'reset_all_settings') {
    if ($method !== 'POST') {
        settings_json(false, 'POST method required.', [], 405);
    }

    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    try {
        $pdo->beginTransaction();

        // 1. Reset crm_settings: clear organization-specific settings
        $stmtDelCrm = $pdo->prepare("DELETE FROM crm_settings WHERE organization_id = :org_id");
        $stmtDelCrm->execute([':org_id' => $orgId]);

        // Re-seed standard factory default settings
        $defaultCrmSettings = [
            'default_lead_status'          => 'New',
            'default_lead_source'          => 'Website',
            'lead_score_threshold'         => '70',
            'inactivity_threshold_days'    => '14',
            'lead_scoring_enabled'         => '1',
            'duplicate_detection_enabled'  => '1',
            'auto_followup_enabled'        => '1',
            'task_default_status'          => 'Pending',
            'task_default_priority'        => 'Medium',
            'task_default_reminder'        => '15 minutes before',
            'task_default_view'            => 'list',
            'task_show_completed'          => '1',
            'calling_default_country_code' => '+44',
            'calling_default_status'       => 'Busy',
            'calling_auto_open_notes'      => '1',
            'calling_show_floating_widget' => '1',
            'email_include_signature'      => '1',
            'inbox_mark_read_on_open'      => '0',
            'inbox_show_contact_context'   => '0'
        ];
        $stmtInsCrm = $pdo->prepare("INSERT INTO crm_settings (organization_id, setting_key, setting_value, created_at, updated_at) VALUES (:org_id, :key, :val, NOW(), NOW())");
        foreach ($defaultCrmSettings as $sKey => $sVal) {
            $stmtInsCrm->execute([
                ':org_id' => $orgId,
                ':key'    => $sKey,
                ':val'    => $sVal
            ]);
        }

        // 2. Reset organization_settings: terminology, size, hours
        $defaultTerms = json_encode([
            'leads'     => 'Leads',
            'pipeline'  => 'Sales Pipeline',
            'contacts'  => 'Contacts',
            'companies' => 'Companies',
            'deals'     => 'Deals'
        ], JSON_UNESCAPED_UNICODE);

        $stmtOrgSettings = $pdo->prepare("INSERT INTO organization_settings 
            (organization_id, terminology_json, company_size, fiscal_year_start, business_hours, created_at, updated_at) 
            VALUES (:org_id, :terms, '1-10 employees', 'January', '09:00 - 18:00 EST', NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                terminology_json = VALUES(terminology_json),
                company_size = VALUES(company_size),
                fiscal_year_start = VALUES(fiscal_year_start),
                business_hours = VALUES(business_hours),
                updated_at = NOW()");
        $stmtOrgSettings->execute([
            ':org_id' => $orgId,
            ':terms'  => $defaultTerms
        ]);

        // 3. Reset team_defaults: neutral defaults
        $stmtTeamDefaults = $pdo->prepare("UPDATE team_defaults SET 
            default_lead_owner_id = NULL,
            default_deal_owner_id = NULL,
            default_task_assignee_id = NULL,
            default_team_id = NULL,
            default_availability = 'Available',
            working_hours = '09:00 - 17:00',
            sales_target_period = 'Monthly',
            default_monthly_quota = '$50,000',
            updated_at = NOW()
            WHERE organization_id = :org_id");
        $stmtTeamDefaults->execute([':org_id' => $orgId]);

        // 4. Reset user_preferences for users of this organization
        $stmtUserPref = $pdo->prepare("UPDATE user_preferences SET 
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
            WHERE user_id IN (SELECT id FROM users WHERE organization_id = :org_id)");
        $stmtUserPref->execute([':org_id' => $orgId]);

        // 5. Reset user_notification_preferences for users of this organization
        $stmtUserNotif = $pdo->prepare("UPDATE user_notification_preferences SET 
            new_lead_assigned = 1,
            lead_status_changed = 1,
            follow_up_due = 1,
            deal_assigned = 1,
            task_due_soon = 1,
            missed_simulated_call = 1,
            updated_at = NOW()
            WHERE user_id IN (SELECT id FROM users WHERE organization_id = :org_id)");
        $stmtUserNotif->execute([':org_id' => $orgId]);

        // 6. Reset organization_modules: enable standard modules
        $stmtModules = $pdo->prepare("UPDATE organization_modules SET enabled = 1, updated_at = NOW() WHERE organization_id = :org_id");
        $stmtModules->execute([':org_id' => $orgId]);

        // 7. Reset crm_integrations
        $stmtIntegrations = $pdo->prepare("UPDATE crm_integrations SET 
            status = 'not_connected',
            is_enabled = 0,
            config_json = NULL,
            connected_at = NULL,
            disconnected_at = NULL,
            updated_at = NOW()
            WHERE organization_id = :org_id");
        $stmtIntegrations->execute([':org_id' => $orgId]);

        // 8. Reset pipeline_stages: restore standard 6 stages and deactivate unused custom stages
        $defaultStages = [
            ['name' => 'Prospect', 'color' => '#64748B', 'probability' => 10.00, 'win_rate' => 10, 'sort_order' => 1, 'is_system' => 0],
            ['name' => 'Qualified', 'color' => '#2563EB', 'probability' => 30.00, 'win_rate' => 30, 'sort_order' => 2, 'is_system' => 0],
            ['name' => 'Proposal', 'color' => '#F59E0B', 'probability' => 60.00, 'win_rate' => 60, 'sort_order' => 3, 'is_system' => 0],
            ['name' => 'Negotiation', 'color' => '#8B5CF6', 'probability' => 80.00, 'win_rate' => 80, 'sort_order' => 4, 'is_system' => 0],
            ['name' => 'Closed Won', 'color' => '#10B981', 'probability' => 100.00, 'win_rate' => 100, 'sort_order' => 5, 'is_system' => 1],
            ['name' => 'Closed Lost', 'color' => '#EF4444', 'probability' => 0.00, 'win_rate' => 0, 'sort_order' => 6, 'is_system' => 1],
        ];

        $stmtCheckStage = $pdo->prepare("SELECT id FROM pipeline_stages WHERE organization_id = :org_id AND name = :name LIMIT 1");
        $stmtUpdateStage = $pdo->prepare("UPDATE pipeline_stages SET 
            color = :color, probability = :prob, win_rate_pct = :win_rate, sort_order = :sort_order, is_system = :is_system, is_active = 1, updated_at = NOW() 
            WHERE id = :id");
        $stmtInsertStage = $pdo->prepare("INSERT INTO pipeline_stages (organization_id, name, color, probability, win_rate_pct, sort_order, is_system, is_active, created_at, updated_at) 
            VALUES (:org_id, :name, :color, :prob, :win_rate, :sort_order, :is_system, 1, NOW(), NOW())");

        foreach ($defaultStages as $stage) {
            $stmtCheckStage->execute([':org_id' => $orgId, ':name' => $stage['name']]);
            $existingId = $stmtCheckStage->fetchColumn();

            if ($existingId) {
                $stmtUpdateStage->execute([
                    ':color'      => $stage['color'],
                    ':prob'       => $stage['probability'],
                    ':win_rate'   => $stage['win_rate'],
                    ':sort_order' => $stage['sort_order'],
                    ':is_system'  => $stage['is_system'],
                    ':id'         => $existingId
                ]);
            } else {
                $stmtInsertStage->execute([
                    ':org_id'     => $orgId,
                    ':name'       => $stage['name'],
                    ':color'      => $stage['color'],
                    ':prob'       => $stage['probability'],
                    ':win_rate'   => $stage['win_rate'],
                    ':sort_order' => $stage['sort_order'],
                    ':is_system'  => $stage['is_system']
                ]);
            }
        }

        // Custom stages: check if deals reference them
        $stmtCustomStages = $pdo->prepare("SELECT id, name FROM pipeline_stages WHERE organization_id = :org_id AND name NOT IN ('Prospect','Qualified','Proposal','Negotiation','Closed Won','Closed Lost')");
        $stmtCustomStages->execute([':org_id' => $orgId]);
        $customStages = $stmtCustomStages->fetchAll(PDO::FETCH_ASSOC);

        $stmtDealsCount = $pdo->prepare("SELECT COUNT(*) FROM deals WHERE organization_id = :org_id AND stage = :stage_name");
        $stmtDeactStage = $pdo->prepare("UPDATE pipeline_stages SET is_active = 0, updated_at = NOW() WHERE id = :id");
        $stmtDelStage = $pdo->prepare("DELETE FROM pipeline_stages WHERE id = :id");

        foreach ($customStages as $cs) {
            $stmtDealsCount->execute([':org_id' => $orgId, ':stage_name' => $cs['name']]);
            $dealsCount = (int)$stmtDealsCount->fetchColumn();
            if ($dealsCount > 0) {
                // Keep stage safe to protect existing deal references, but mark inactive
                $stmtDeactStage->execute([':id' => $cs['id']]);
            } else {
                // Safe to remove unreferenced custom stage
                $stmtDelStage->execute([':id' => $cs['id']]);
            }
        }

        $pdo->commit();
        settings_json(true, 'All workspace settings have been reset to factory default.', [
            'organization_id' => $orgId
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Reset All Settings error: ' . $e->getMessage());
        settings_json(false, 'Failed to reset settings: ' . $e->getMessage(), [], 500);
    }
}

// -------------------------------------------------------------
// ACTION: DELETE WORKSPACE (PERMANENT & IRREVERSIBLE)
// -------------------------------------------------------------
if ($action === 'delete_workspace') {
    if ($method !== 'POST') {
        settings_json(false, 'POST method required.', [], 405);
    }

    if (!can_manage_workspace_settings($authUser)) {
        settings_json(false, 'Access denied. Administrator privileges required.', [], 403);
    }

    $confirmText = trim($_POST['confirm_text'] ?? '');
    if ($confirmText !== 'DELETE') {
        settings_json(false, 'Confirmation text must be DELETE in all capitals.', [], 400);
    }

    // Double-check organization exists
    $stmtOrg = $pdo->prepare("SELECT id, name FROM organizations WHERE id = :org_id LIMIT 1");
    $stmtOrg->execute([':org_id' => $orgId]);
    $orgRow = $stmtOrg->fetch(PDO::FETCH_ASSOC);
    if (!$orgRow) {
        settings_json(false, 'Workspace organization does not exist.', [], 404);
    }

    try {
        $pdo->beginTransaction();

        // 1. Break circular FK dependencies
        $pdo->prepare("UPDATE companies SET primary_contact_id = NULL WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("UPDATE contacts SET company_id = NULL, lead_id = NULL WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 2. Custom fields values & options & custom fields
        $pdo->prepare("DELETE FROM crm_custom_field_values WHERE custom_field_id IN (SELECT id FROM crm_custom_fields WHERE organization_id = :org_id)")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM crm_custom_field_options WHERE custom_field_id IN (SELECT id FROM crm_custom_fields WHERE organization_id = :org_id)")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM crm_custom_fields WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM custom_fields WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 3. Saved reports & presets
        $pdo->prepare("DELETE FROM report_saved_presets WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM custom_reports WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 4. Inbox
        $pdo->prepare("DELETE FROM inbox_drafts WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM inbox_messages WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM inbox_conversations WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 5. Invoices & Payments
        $pdo->prepare("DELETE FROM invoice_payments WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM invoice_items WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM invoices WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 6. Subscriptions
        $pdo->prepare("DELETE FROM subscription_invoices WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM subscriptions WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 7. Proposals & Contracts
        $pdo->prepare("DELETE FROM proposal_items WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM proposals WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM contract_files WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM contracts WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 8. Documents, Expenses, Estimate Requests
        $pdo->prepare("DELETE FROM documents WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM expenses WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM estimate_requests WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 9. Projects
        $pdo->prepare("DELETE FROM project_files WHERE project_id IN (SELECT id FROM projects WHERE organization_id = :org_id)")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM project_milestones WHERE project_id IN (SELECT id FROM projects WHERE organization_id = :org_id)")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM projects WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 10. Tasks & Deals
        $pdo->prepare("DELETE FROM tasks WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM deals WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 11. Contact Companies, Contacts, Leads, Companies
        $pdo->prepare("DELETE FROM contact_companies WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM contacts WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM leads WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM companies WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 12. Calendar, Team Activities, Team Notes, Team Defaults, Teams
        $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM team_activities WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM team_notes WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM team_defaults WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM teams WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 13. Users & User Preferences / Notifications / Role Permissions
        $pdo->prepare("DELETE FROM user_notification_preferences WHERE user_id IN (SELECT id FROM users WHERE organization_id = :org_id)")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM user_preferences WHERE user_id IN (SELECT id FROM users WHERE organization_id = :org_id)")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM role_permissions WHERE role_id IN (SELECT id FROM roles WHERE organization_id = :org_id)")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM users WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM roles WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 14. Workspace Configuration & Stages
        $pdo->prepare("DELETE FROM pipeline_stages WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM crm_integrations WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM crm_settings WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM organization_settings WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);
        $pdo->prepare("DELETE FROM organization_modules WHERE organization_id = :org_id")->execute([':org_id' => $orgId]);

        // 15. The Organization record itself
        $pdo->prepare("DELETE FROM organizations WHERE id = :org_id")->execute([':org_id' => $orgId]);

        $pdo->commit();

        // 16. Determine redirect target based on remaining active organizations
        $stmtRemaining = $pdo->query("SELECT COUNT(*) FROM organizations WHERE setup_completed = 1");
        $remainingCount = (int)$stmtRemaining->fetchColumn();
        $redirectUrl = ($remainingCount > 0) ? '/nexFlow/admin/admin-login.php' : '/nexFlow/admin/onboarding.php';

        // 17. Destroy session and clear cookie
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        settings_json(true, 'Workspace and all associated records permanently deleted.', [
            'redirect_url' => $redirectUrl
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Delete Workspace error: ' . $e->getMessage());
        settings_json(false, 'Failed to delete workspace: ' . $e->getMessage(), [], 500);
    }
}

settings_json(false, 'Invalid action or request method.', [], 400);

