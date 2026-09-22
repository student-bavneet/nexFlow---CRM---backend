<?php
/**
 * NexFlow CRM — Calendar API Controller
 * Multi-tenant, organization-scoped controller for Calendar Events,
 * Task Deadlines integration, Contacts/Companies/Deals associations,
 * CRUD operations, Drag-and-Drop rescheduling, and Sidebar upcoming activities.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Response Helper
function cal_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 1. Session Authentication Guard
$currentUser = nexflow_current_user();
if (!$currentUser) {
    cal_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    cal_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Guard
function require_cal_perm(string $action = 'view'): void {
    global $currentUser;
    $uid = $currentUser['id'] ?? ($_SESSION['user_id'] ?? 1);
    if ($uid && is_super_admin($uid)) {
        return;
    }
    if (function_exists('hasPermission')) {
        if (!hasPermission('calendar', $action) && !hasPermission('calendar')) {
            cal_json(false, "Forbidden: Missing 'calendar' permission.", [], 403);
        }
    }
}

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    cal_json(false, 'Database connection failed: ' . $e->getMessage(), [], 500);
}

// Support GET, POST, JSON input
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$inputData = $_POST;
if (empty($inputData)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $inputData = $decoded;
        }
    }
}

$action = $_GET['action'] ?? ($inputData['action'] ?? 'events');

// Helpers for avatar & formatting
function get_cal_avatar_meta(?string $name, ?int $userId): array {
    if (empty($name)) {
        return ['initials' => '??', 'color' => '#64748B'];
    }
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    if (count($parts) >= 2) {
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    } else {
        $initials = strtoupper(substr($parts[0], 0, min(2, strlen($parts[0]))));
    }
    $colors = ['#7C3AED', '#2563EB', '#059669', '#D97706', '#DC2626', '#4F46E5', '#0D9488', '#E11D48'];
    $idx = ($userId ?? crc32($name)) % count($colors);
    return [
        'initials' => $initials,
        'color'    => $colors[abs($idx)]
    ];
}

function get_cal_type_meta(string $type): array {
    $map = [
        'Call'          => ['icon' => '📞', 'color' => '#059669'],
        'Meeting'       => ['icon' => '📅', 'color' => '#2563EB'],
        'Product Demo'  => ['icon' => '💻', 'color' => '#7C3AED'],
        'Follow-up'     => ['icon' => '🔔', 'color' => '#F79009'],
        'Proposal'      => ['icon' => '📝', 'color' => '#4F46E5'],
        'Task Deadline' => ['icon' => '📋', 'color' => '#D92D20'],
        'Team Event'    => ['icon' => '👥', 'color' => '#0D9488']
    ];
    $norm = 'Meeting';
    foreach ($map as $key => $meta) {
        if (strcasecmp($key, $type) === 0 || strcasecmp(str_replace(' ', '', $key), str_replace([' ', '-', '_'], '', $type)) === 0) {
            $norm = $key;
            break;
        }
    }
    return [
        'normalized' => $norm,
        'icon'       => $map[$norm]['icon'],
        'color'      => $map[$norm]['color']
    ];
}

function get_cal_canonical_status(?string $status): ?string {
    if ($status === null || trim($status) === '') {
        return 'Scheduled';
    }
    $valid = [
        'Scheduled'   => 'Scheduled',
        'Completed'   => 'Completed',
        'Cancelled'   => 'Cancelled',
        'Rescheduled' => 'Rescheduled',
        'No Show'     => 'No Show',
    ];
    foreach ($valid as $key => $val) {
        if (strcasecmp($key, trim($status)) === 0 || strcasecmp(str_replace([' ', '-', '_'], '', $key), str_replace([' ', '-', '_'], '', trim($status))) === 0) {
            return $val;
        }
    }
    return null;
}

function validate_cal_event_type(?string $type): ?string {
    if ($type === null || trim($type) === '') {
        return 'Meeting';
    }
    $valid = ['Call', 'Meeting', 'Product Demo', 'Follow-up', 'Proposal', 'Task Deadline', 'Team Event'];
    foreach ($valid as $vt) {
        if (strcasecmp($vt, trim($type)) === 0 || strcasecmp(str_replace([' ', '-', '_'], '', $vt), str_replace([' ', '-', '_'], '', trim($type))) === 0) {
            return $vt;
        }
    }
    return null;
}

// ROUTER
try {
    switch ($action) {

        // =========================================================
        // ACTION: BOOTSTRAP / REFERENCE_OPTIONS
        // =========================================================
        case 'bootstrap':
        case 'reference_options':
            require_cal_perm('view');

            // 1. Users
            $uStmt = $pdo->prepare("
                SELECT id, name, display_name, email, role, photo_path 
                FROM users 
                WHERE organization_id = ? AND status = 'active' 
                ORDER BY name ASC
            ");
            $uStmt->execute([$organizationId]);
            $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Companies
            $cStmt = $pdo->prepare("
                SELECT id, name, industry 
                FROM companies 
                WHERE organization_id = ? 
                ORDER BY name ASC
            ");
            $cStmt->execute([$organizationId]);
            $companies = $cStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Contacts
            $ctStmt = $pdo->prepare("
                SELECT id, name, email, phone, job_title AS title
                FROM contacts 
                WHERE organization_id = ? 
                ORDER BY name ASC
            ");
            $ctStmt->execute([$organizationId]);
            $contacts = $ctStmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Contact Companies mapping (Authoritative multi-company relationship)
            $ccStmt = $pdo->prepare("
                SELECT cc.contact_id, cc.company_id, comp.name AS company_name
                FROM contact_companies cc
                JOIN companies comp ON comp.id = cc.company_id
                WHERE cc.organization_id = ?
            ");
            $ccStmt->execute([$organizationId]);
            $contactCompanies = $ccStmt->fetchAll(PDO::FETCH_ASSOC);

            $contactCompanyMap = [];
            foreach ($contactCompanies as $row) {
                $cid = (int)$row['contact_id'];
                if (!isset($contactCompanyMap[$cid])) {
                    $contactCompanyMap[$cid] = [];
                }
                $contactCompanyMap[$cid][] = [
                    'company_id'   => (int)$row['company_id'],
                    'company_name' => $row['company_name']
                ];
            }

            foreach ($contacts as &$ct) {
                $ct['companies'] = $contactCompanyMap[(int)$ct['id']] ?? [];
            }
            unset($ct);

            // 5. Deals
            $dStmt = $pdo->prepare("
                SELECT id, name, deal_code, company, contact_id, contact_name, value 
                FROM deals 
                WHERE organization_id = ? 
                ORDER BY name ASC
            ");
            $dStmt->execute([$organizationId]);
            $deals = $dStmt->fetchAll(PDO::FETCH_ASSOC);

            cal_json(true, 'Reference options loaded successfully.', [
                'users'        => $users,
                'companies'    => $companies,
                'contacts'     => $contacts,
                'deals'        => $deals,
                'current_user' => [
                    'id'              => $currentUserId,
                    'name'            => $currentUser['name'] ?? '',
                    'email'           => $currentUser['email'] ?? '',
                    'organization_id' => $organizationId
                ]
            ]);
            break;

        // =========================================================
        // ACTION: EVENTS / LIST (Calendar Events + Dynamic Task Deadlines)
        // =========================================================
        case 'events':
        case 'list':
            require_cal_perm('view');

            $startFilter = trim($_GET['start'] ?? $inputData['start'] ?? '');
            $endFilter   = trim($_GET['end'] ?? $inputData['end'] ?? '');
            $searchQuery = trim($_GET['search'] ?? $inputData['search'] ?? '');

            // 1. Fetch Calendar Events
            $calSql = "
                SELECT 
                    c.id,
                    c.organization_id,
                    c.title,
                    c.description,
                    c.event_type,
                    c.start_time,
                    c.end_time,
                    c.user_id,
                    c.contact_id,
                    c.company_id,
                    c.deal_id,
                    c.related_type,
                    c.related_id,
                    c.status,
                    c.location,
                    c.client_visible,
                    c.created_at,
                    c.updated_at,
                    u.name AS owner_name,
                    u.photo_path AS owner_photo,
                    COALESCE(ct.name, (CASE WHEN c.related_type = 'contacts' THEN ct_rel.name END), l.name) AS contact_name,
                    COALESCE(ct.phone, (CASE WHEN c.related_type = 'contacts' THEN ct_rel.phone END), l.phone) AS contact_phone,
                    COALESCE(ct.email, (CASE WHEN c.related_type = 'contacts' THEN ct_rel.email END), l.email) AS contact_email,
                    COALESCE(comp.name, (CASE WHEN c.related_type = 'companies' THEN comp_rel.name END), l.company, d.company) AS company_name,
                    COALESCE(d.name, (CASE WHEN c.related_type = 'deals' THEN d_rel.name END)) AS deal_name,
                    COALESCE(d.deal_code, (CASE WHEN c.related_type = 'deals' THEN d_rel.deal_code END)) AS deal_code,
                    l.name AS lead_name,
                    pr.name AS project_name
                FROM calendar_events c
                LEFT JOIN users u ON u.id = c.user_id
                LEFT JOIN contacts ct ON ct.id = c.contact_id
                LEFT JOIN contacts ct_rel ON (c.related_type = 'contacts' AND ct_rel.id = c.related_id)
                LEFT JOIN companies comp ON comp.id = c.company_id
                LEFT JOIN companies comp_rel ON (c.related_type = 'companies' AND comp_rel.id = c.related_id)
                LEFT JOIN deals d ON d.id = c.deal_id
                LEFT JOIN deals d_rel ON (c.related_type = 'deals' AND d_rel.id = c.related_id)
                LEFT JOIN leads l ON (c.related_type = 'leads' AND l.id = c.related_id)
                LEFT JOIN projects pr ON (c.related_type = 'projects' AND pr.id = c.related_id)
                WHERE c.organization_id = :org_id
            ";

            $calParams = [':org_id' => $organizationId];

            if (!empty($startFilter)) {
                $calSql .= " AND c.end_time >= :start_filter";
                $calParams[':start_filter'] = date('Y-m-d 00:00:00', strtotime($startFilter));
            }
            if (!empty($endFilter)) {
                $calSql .= " AND c.start_time <= :end_filter";
                $calParams[':end_filter'] = date('Y-m-d 23:59:59', strtotime($endFilter));
            }
            if (!empty($searchQuery)) {
                $calSql .= " AND (
                    c.title LIKE :search 
                    OR c.description LIKE :search 
                    OR c.location LIKE :search 
                    OR comp.name LIKE :search 
                    OR ct.name LIKE :search 
                    OR d.name LIKE :search 
                    OR u.name LIKE :search
                )";
                $calParams[':search'] = '%' . $searchQuery . '%';
            }

            $calSql .= " ORDER BY c.start_time ASC, c.id ASC";

            $stmtCal = $pdo->prepare($calSql);
            $stmtCal->execute($calParams);
            $calRows = $stmtCal->fetchAll(PDO::FETCH_ASSOC);

            $events = [];

            foreach ($calRows as $r) {
                $startTs = strtotime($r['start_time']);
                $endTs   = strtotime($r['end_time'] ?: $r['start_time']);
                if ($endTs < $startTs) $endTs = $startTs + 3600;

                $typeMeta = get_cal_type_meta($r['event_type'] ?: 'Meeting');
                $ownerMeta = get_cal_avatar_meta($r['owner_name'], (int)($r['user_id'] ?: 0));

                $isSales = (
                    !empty($r['deal_id']) ||
                    $r['related_type'] === 'deals' ||
                    $r['related_type'] === 'leads' ||
                    in_array($typeMeta['normalized'], ['Call', 'Product Demo', 'Proposal', 'Follow-up'])
                );

                $events[] = [
                    'id'             => 'evt_' . $r['id'],
                    'db_id'          => (int)$r['id'],
                    'event_source'   => 'calendar_event',
                    'is_task'        => false,
                    'title'          => $r['title'],
                    'type'           => $typeMeta['normalized'],
                    'typeIcon'       => $typeMeta['icon'],
                    'color'          => $typeMeta['color'],
                    'status'         => get_cal_canonical_status($r['status'] ?: 'Scheduled') ?: 'Scheduled',
                    'date'           => date('Y-m-d', $startTs),
                    'startTime'      => date('g:i A', $startTs),
                    'endTime'        => date('g:i A', $endTs),
                    'startHour'      => (int)date('G', $startTs),
                    'start_time_iso' => $r['start_time'],
                    'end_time_iso'   => $r['end_time'],
                    'contact'        => $r['contact_name'] ?: '',
                    'contact_id'     => $r['contact_id'] ? (int)$r['contact_id'] : null,
                    'contact_phone'  => $r['contact_phone'] ?: '',
                    'contact_email'  => $r['contact_email'] ?: '',
                    'company'        => $r['company_name'] ?: '',
                    'company_id'     => $r['company_id'] ? (int)$r['company_id'] : null,
                    'deal'           => $r['deal_name'] ?: '',
                    'deal_id'        => $r['deal_id'] ? (int)$r['deal_id'] : null,
                    'deal_code'      => $r['deal_code'] ?: '',
                    'owner'          => $r['owner_name'] ?: 'Unassigned',
                    'owner_id'       => $r['user_id'] ? (int)$r['user_id'] : null,
                    'ownerInitials'  => $ownerMeta['initials'],
                    'ownerColor'     => $ownerMeta['color'],
                    'location'       => $r['location'] ?: 'Video Meeting',
                    'description'    => $r['description'] ?: '',
                    'is_mine'        => ((int)$r['user_id'] === $currentUserId),
                    'is_sales'       => $isSales,
                    'client_visible' => (int)($r['client_visible'] ?? 0),
                    'created_at'     => $r['created_at'],
                    'updated_at'     => $r['updated_at']
                ];
            }

            // 2. Fetch Tasks with due_date (Requirement 13: Task -> Calendar connection)
            $taskSql = "
                SELECT 
                    t.id,
                    t.organization_id,
                    t.title,
                    t.description,
                    t.task_type,
                    t.status,
                    t.priority,
                    t.due_date,
                    t.assigned_to,
                    t.company_id,
                    t.contact_id,
                    t.deal_id,
                    t.related_type,
                    t.related_id,
                    u.name AS assignee_name,
                    u.photo_path AS assignee_photo,
                    COALESCE(comp.name, comp_rel.name, d.company) AS company_name,
                    COALESCE(ct.name, ct_rel.name) AS contact_name,
                    COALESCE(d.name, d_rel.name) AS deal_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN companies comp ON comp.id = t.company_id
                LEFT JOIN companies comp_rel ON (t.related_type = 'companies' AND comp_rel.id = t.related_id)
                LEFT JOIN contacts ct ON ct.id = t.contact_id
                LEFT JOIN contacts ct_rel ON (t.related_type = 'contacts' AND ct_rel.id = t.related_id)
                LEFT JOIN deals d ON d.id = t.deal_id
                LEFT JOIN deals d_rel ON (t.related_type = 'deals' AND d_rel.id = t.related_id)
                WHERE t.organization_id = :org_id
                  AND t.due_date IS NOT NULL 
                  AND t.due_date != '0000-00-00 00:00:00'
            ";

            $taskParams = [':org_id' => $organizationId];

            if (!empty($startFilter)) {
                $taskSql .= " AND t.due_date >= :t_start_filter";
                $taskParams[':t_start_filter'] = date('Y-m-d 00:00:00', strtotime($startFilter));
            }
            if (!empty($endFilter)) {
                $taskSql .= " AND t.due_date <= :t_end_filter";
                $taskParams[':t_end_filter'] = date('Y-m-d 23:59:59', strtotime($endFilter));
            }
            if (!empty($searchQuery)) {
                $taskSql .= " AND (
                    t.title LIKE :t_search 
                    OR t.description LIKE :t_search 
                    OR comp.name LIKE :t_search 
                    OR ct.name LIKE :t_search 
                    OR d.name LIKE :t_search 
                    OR u.name LIKE :t_search
                )";
                $taskParams[':t_search'] = '%' . $searchQuery . '%';
            }

            $stmtTasks = $pdo->prepare($taskSql);
            $stmtTasks->execute($taskParams);
            $taskRows = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

            foreach ($taskRows as $tr) {
                $dueTs = strtotime($tr['due_date']);
                $endTs = $dueTs + 1800; // 30 minutes block
                $assigneeMeta = get_cal_avatar_meta($tr['assignee_name'], (int)($tr['assigned_to'] ?: 0));

                $isDone = (strtolower($tr['status']) === 'completed');

                $events[] = [
                    'id'             => 'task_' . $tr['id'],
                    'db_id'          => (int)$tr['id'],
                    'event_source'   => 'task_deadline',
                    'is_task'        => true,
                    'title'          => 'Deadline: ' . $tr['title'],
                    'type'           => 'Task Deadline',
                    'typeIcon'       => '📋',
                    'color'          => '#D92D20',
                    'status'         => $isDone ? 'Completed' : 'Scheduled',
                    'date'           => date('Y-m-d', $dueTs),
                    'startTime'      => date('g:i A', $dueTs),
                    'endTime'        => date('g:i A', $endTs),
                    'startHour'      => (int)date('G', $dueTs),
                    'start_time_iso' => $tr['due_date'],
                    'end_time_iso'   => date('Y-m-d H:i:s', $endTs),
                    'contact'        => $tr['contact_name'] ?: '',
                    'contact_id'     => $tr['contact_id'] ? (int)$tr['contact_id'] : null,
                    'contact_phone'  => '',
                    'contact_email'  => '',
                    'company'        => $tr['company_name'] ?: '',
                    'company_id'     => $tr['company_id'] ? (int)$tr['company_id'] : null,
                    'deal'           => $tr['deal_name'] ?: '',
                    'deal_id'        => $tr['deal_id'] ? (int)$tr['deal_id'] : null,
                    'deal_code'      => '',
                    'owner'          => $tr['assignee_name'] ?: 'Unassigned',
                    'owner_id'       => $tr['assigned_to'] ? (int)$tr['assigned_to'] : null,
                    'ownerInitials'  => $assigneeMeta['initials'],
                    'ownerColor'     => $assigneeMeta['color'],
                    'location'       => 'CRM Task System',
                    'description'    => $tr['description'] ?: 'Task deadline from Tasks module.',
                    'is_mine'        => ((int)$tr['assigned_to'] === $currentUserId),
                    'is_sales'       => (!empty($tr['deal_id']) || $tr['related_type'] === 'deals'),
                    'created_at'     => $tr['due_date'],
                    'updated_at'     => $tr['due_date']
                ];
            }

            // Sort all items chronologically
            usort($events, function($a, $b) {
                return strcmp($a['start_time_iso'], $b['start_time_iso']);
            });

            cal_json(true, 'Events loaded successfully.', [
                'events' => $events,
                'count'  => count($events)
            ]);
            break;

        // =========================================================
        // ACTION: GET (Single Event)
        // =========================================================
        case 'get':
            require_cal_perm('view');
            $idRaw = trim($_GET['id'] ?? $inputData['id'] ?? '');

            if (empty($idRaw)) {
                cal_json(false, 'Event ID is required.', [], 400);
            }

            if (str_starts_with($idRaw, 'task_')) {
                // Task deadline lookup
                $taskId = (int)substr($idRaw, 5);
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name AS assignee_name, comp.name AS company_name, ct.name AS contact_name, d.name AS deal_name
                    FROM tasks t
                    LEFT JOIN users u ON u.id = t.assigned_to
                    LEFT JOIN companies comp ON comp.id = t.company_id
                    LEFT JOIN contacts ct ON ct.id = t.contact_id
                    LEFT JOIN deals d ON d.id = t.deal_id
                    WHERE t.id = ? AND t.organization_id = ? LIMIT 1
                ");
                $stmt->execute([$taskId, $organizationId]);
                $t = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$t) {
                    cal_json(false, 'Task deadline not found.', [], 404);
                }
                cal_json(true, 'Task deadline details loaded.', [
                    'event' => [
                        'id'           => 'task_' . $t['id'],
                        'db_id'        => (int)$t['id'],
                        'event_source' => 'task_deadline',
                        'is_task'      => true,
                        'title'        => 'Deadline: ' . $t['title'],
                        'type'         => 'Task Deadline',
                        'status'       => (strtolower($t['status']) === 'completed') ? 'Completed' : 'Scheduled',
                        'start_time'   => $t['due_date'],
                        'end_time'     => date('Y-m-d H:i:s', strtotime($t['due_date']) + 1800),
                        'contact'      => $t['contact_name'] ?: '',
                        'company'      => $t['company_name'] ?: '',
                        'deal'         => $t['deal_name'] ?: '',
                        'owner'        => $t['assignee_name'] ?: 'Unassigned',
                        'description'  => $t['description'] ?: '',
                        'location'     => 'CRM Task System'
                    ]
                ]);
            }

            $dbId = (int)preg_replace('/[^0-9]/', '', $idRaw);
            $stmt = $pdo->prepare("
                SELECT c.*, u.name AS owner_name, comp.name AS company_name, ct.name AS contact_name, d.name AS deal_name
                FROM calendar_events c
                LEFT JOIN users u ON u.id = c.user_id
                LEFT JOIN companies comp ON comp.id = c.company_id
                LEFT JOIN contacts ct ON ct.id = c.contact_id
                LEFT JOIN deals d ON d.id = c.deal_id
                WHERE c.id = ? AND c.organization_id = ? LIMIT 1
            ");
            $stmt->execute([$dbId, $organizationId]);
            $evt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$evt) {
                cal_json(false, 'Calendar event not found.', [], 404);
            }

            cal_json(true, 'Calendar event loaded.', [
                'event' => [
                    'id'           => 'evt_' . $evt['id'],
                    'db_id'        => (int)$evt['id'],
                    'event_source' => 'calendar_event',
                    'is_task'      => false,
                    'title'        => $evt['title'],
                    'type'         => $evt['event_type'],
                    'status'       => get_cal_canonical_status($evt['status']) ?: 'Scheduled',
                    'start_time'   => $evt['start_time'],
                    'end_time'     => $evt['end_time'],
                    'date'         => date('Y-m-d', strtotime($evt['start_time'])),
                    'startTime'    => date('g:i A', strtotime($evt['start_time'])),
                    'endTime'      => date('g:i A', strtotime($evt['end_time'])),
                    'contact_id'   => $evt['contact_id'] ? (int)$evt['contact_id'] : null,
                    'contact'      => $evt['contact_name'] ?: '',
                    'company_id'   => $evt['company_id'] ? (int)$evt['company_id'] : null,
                    'company'      => $evt['company_name'] ?: '',
                    'deal_id'      => $evt['deal_id'] ? (int)$evt['deal_id'] : null,
                    'deal'         => $evt['deal_name'] ?: '',
                    'owner_id'     => $evt['user_id'] ? (int)$evt['user_id'] : null,
                    'owner'        => $evt['owner_name'] ?: 'Unassigned',
                    'location'     => $evt['location'] ?: '',
                    'description'    => $evt['description'] ?: '',
                    'client_visible' => (int)($evt['client_visible'] ?? 0),
                    'created_at'     => $evt['created_at'],
                    'updated_at'     => $evt['updated_at']
                ]
            ]);
            break;

        // =========================================================
        // ACTION: CREATE (Save new calendar event to MySQL)
        // =========================================================
        case 'create':
            require_cal_perm('create');

            $title        = trim($inputData['title'] ?? '');
            $typeInput    = $inputData['type'] ?? ($inputData['event_type'] ?? 'Meeting');
            $statusInput  = $inputData['status'] ?? 'Scheduled';
            $date         = trim($inputData['date'] ?? '');
            $startTimeRaw = trim($inputData['start_time'] ?? '');
            $endTimeRaw   = trim($inputData['end_time'] ?? '');
            $location     = trim($inputData['location'] ?? '');
            $desc         = trim($inputData['description'] ?? '');

            if ($title === '') {
                cal_json(false, 'Event title is required.', [], 422);
            }
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                cal_json(false, 'Valid event date (YYYY-MM-DD) is required.', [], 422);
            }
            if ($startTimeRaw === '') {
                cal_json(false, 'Start time is required.', [], 422);
            }
            if ($endTimeRaw === '') {
                cal_json(false, 'End time is required.', [], 422);
            }

            // Validate time format & relationship
            $startTs = strtotime("{$date} {$startTimeRaw}");
            $endTs   = strtotime("{$date} {$endTimeRaw}");
            if ($startTs === false) {
                cal_json(false, 'Invalid start time format.', [], 422);
            }
            if ($endTs === false) {
                cal_json(false, 'Invalid end time format.', [], 422);
            }
            if ($endTs <= $startTs) {
                cal_json(false, 'End time must be later than start time.', [], 422);
            }

            $startFormatted = date('Y-m-d H:i:s', $startTs);
            $endFormatted   = date('Y-m-d H:i:s', $endTs);

            // Canonical type & status validation
            $canonicalType = validate_cal_event_type($typeInput);
            if (!$canonicalType) {
                cal_json(false, 'Invalid event type specified.', [], 422);
            }

            $canonicalStatus = get_cal_canonical_status($statusInput);
            if (!$canonicalStatus) {
                cal_json(false, 'Invalid event status specified.', [], 422);
            }

            // Validate Owner belongs to tenant
            $ownerId = !empty($inputData['user_id']) ? (int)$inputData['user_id'] : (!empty($inputData['owner_id']) ? (int)$inputData['owner_id'] : $currentUserId);
            $uCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
            $uCheck->execute([$ownerId, $organizationId]);
            if (!$uCheck->fetchColumn()) {
                cal_json(false, 'Assigned owner does not belong to your organization.', [], 422);
            }

            // Validate Contact belongs to tenant
            $contactId = !empty($inputData['contact_id']) ? (int)$inputData['contact_id'] : null;
            if ($contactId !== null) {
                $ctCheck = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
                $ctCheck->execute([$contactId, $organizationId]);
                if (!$ctCheck->fetchColumn()) {
                    cal_json(false, 'Selected contact does not belong to your organization.', [], 422);
                }
            }

            // Validate Company belongs to tenant
            $companyId = !empty($inputData['company_id']) ? (int)$inputData['company_id'] : null;
            if ($companyId !== null) {
                $cCheck = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
                $cCheck->execute([$companyId, $organizationId]);
                if (!$cCheck->fetchColumn()) {
                    cal_json(false, 'Selected company does not belong to your organization.', [], 422);
                }
            }

            // Validate Deal belongs to tenant
            $dealId = !empty($inputData['deal_id']) ? (int)$inputData['deal_id'] : null;
            if ($dealId !== null) {
                $dCheck = $pdo->prepare("SELECT id, contact_id FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
                $dCheck->execute([$dealId, $organizationId]);
                $dRow = $dCheck->fetch(PDO::FETCH_ASSOC);
                if (!$dRow) {
                    cal_json(false, 'Selected deal does not belong to your organization.', [], 422);
                }
                if (!$contactId && !empty($dRow['contact_id'])) {
                    $contactId = (int)$dRow['contact_id'];
                }
            }

            $clientVisible = !empty($inputData['client_visible']) ? 1 : 0;

            $stmtIns = $pdo->prepare("
                INSERT INTO calendar_events (
                    organization_id, title, description, event_type, start_time, end_time,
                    user_id, contact_id, company_id, deal_id, status, location, client_visible,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    NOW(), NOW()
                )
            ");
            $stmtIns->execute([
                $organizationId, $title, $desc !== '' ? $desc : null, $canonicalType, $startFormatted, $endFormatted,
                $ownerId, $contactId, $companyId, $dealId, $canonicalStatus, $location !== '' ? $location : null,
                $clientVisible
            ]);
            $newEventId = (int)$pdo->lastInsertId();

            // Log activity
            try {
                $actStmt = $pdo->prepare("
                    INSERT INTO team_activities (
                        organization_id, user_id, activity_type, title, description,
                        related_entity, related_entity_id, created_by, created_at
                    ) VALUES (
                        ?, ?, 'meeting_scheduled', ?, ?,
                        'calendar_events', ?, ?, NOW()
                    )
                ");
                $actStmt->execute([
                    $organizationId, $ownerId, "Scheduled {$canonicalType}: '{$title}'",
                    "Event scheduled for " . date('M j, Y g:i A', strtotime($startFormatted)),
                    $newEventId, $currentUserId
                ]);
            } catch (Throwable $e) {}

            cal_json(true, "Event '{$title}' created successfully.", [
                'id'             => $newEventId,
                'event_id'       => $newEventId,
                'status'         => $canonicalStatus,
                'title'          => $title,
                'client_visible' => $clientVisible
            ], 201);
            break;

        // =========================================================
        // ACTION: UPDATE (Edit existing calendar event in MySQL)
        // =========================================================
        case 'update':
            require_cal_perm('edit');

            $idRaw = trim($inputData['id'] ?? $inputData['event_id'] ?? '');
            if (empty($idRaw)) {
                cal_json(false, 'Event ID is required.', [], 400);
            }
            if (str_starts_with($idRaw, 'task_')) {
                cal_json(false, 'This is a task deadline. To edit this task, please use the Tasks module.', [], 400);
            }

            $dbId = (int)preg_replace('/[^0-9]/', '', $idRaw);

            // Verify event belongs to current organization
            $checkStmt = $pdo->prepare("SELECT id FROM calendar_events WHERE id = ? AND organization_id = ? LIMIT 1");
            $checkStmt->execute([$dbId, $organizationId]);
            if (!$checkStmt->fetchColumn()) {
                cal_json(false, 'Calendar event not found or access denied.', [], 404);
            }

            $title        = trim($inputData['title'] ?? '');
            $typeInput    = $inputData['type'] ?? ($inputData['event_type'] ?? 'Meeting');
            $statusInput  = $inputData['status'] ?? 'Scheduled';
            $date         = trim($inputData['date'] ?? '');
            $startTimeRaw = trim($inputData['start_time'] ?? '');
            $endTimeRaw   = trim($inputData['end_time'] ?? '');
            $location     = trim($inputData['location'] ?? '');
            $desc         = trim($inputData['description'] ?? '');

            if ($title === '') {
                cal_json(false, 'Event title is required.', [], 422);
            }
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                cal_json(false, 'Valid event date (YYYY-MM-DD) is required.', [], 422);
            }
            if ($startTimeRaw === '') {
                cal_json(false, 'Start time is required.', [], 422);
            }
            if ($endTimeRaw === '') {
                cal_json(false, 'End time is required.', [], 422);
            }

            $startTs = strtotime("{$date} {$startTimeRaw}");
            $endTs   = strtotime("{$date} {$endTimeRaw}");
            if ($startTs === false) {
                cal_json(false, 'Invalid start time format.', [], 422);
            }
            if ($endTs === false) {
                cal_json(false, 'Invalid end time format.', [], 422);
            }
            if ($endTs <= $startTs) {
                cal_json(false, 'End time must be later than start time.', [], 422);
            }

            $startFormatted = date('Y-m-d H:i:s', $startTs);
            $endFormatted   = date('Y-m-d H:i:s', $endTs);

            $canonicalType = validate_cal_event_type($typeInput);
            if (!$canonicalType) {
                cal_json(false, 'Invalid event type specified.', [], 422);
            }

            $canonicalStatus = get_cal_canonical_status($statusInput);
            if (!$canonicalStatus) {
                cal_json(false, 'Invalid event status specified.', [], 422);
            }

            $ownerId = !empty($inputData['user_id']) ? (int)$inputData['user_id'] : (!empty($inputData['owner_id']) ? (int)$inputData['owner_id'] : $currentUserId);
            $uCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
            $uCheck->execute([$ownerId, $organizationId]);
            if (!$uCheck->fetchColumn()) {
                cal_json(false, 'Assigned owner does not belong to your organization.', [], 422);
            }

            $contactId = !empty($inputData['contact_id']) ? (int)$inputData['contact_id'] : null;
            if ($contactId !== null) {
                $ctCheck = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
                $ctCheck->execute([$contactId, $organizationId]);
                if (!$ctCheck->fetchColumn()) {
                    cal_json(false, 'Selected contact does not belong to your organization.', [], 422);
                }
            }

            $companyId = !empty($inputData['company_id']) ? (int)$inputData['company_id'] : null;
            if ($companyId !== null) {
                $cCheck = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
                $cCheck->execute([$companyId, $organizationId]);
                if (!$cCheck->fetchColumn()) {
                    cal_json(false, 'Selected company does not belong to your organization.', [], 422);
                }
            }

            $dealId = !empty($inputData['deal_id']) ? (int)$inputData['deal_id'] : null;
            if ($dealId !== null) {
                $dCheck = $pdo->prepare("SELECT id FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
                $dCheck->execute([$dealId, $organizationId]);
                if (!$dCheck->fetchColumn()) {
                    cal_json(false, 'Selected deal does not belong to your organization.', [], 422);
                }
            }

            $clientVisible = !empty($inputData['client_visible']) ? 1 : 0;

            $stmtUpd = $pdo->prepare("
                UPDATE calendar_events SET
                    title = ?,
                    description = ?,
                    event_type = ?,
                    start_time = ?,
                    end_time = ?,
                    user_id = ?,
                    contact_id = ?,
                    company_id = ?,
                    deal_id = ?,
                    status = ?,
                    location = ?,
                    client_visible = ?,
                    updated_at = NOW()
                WHERE id = ? AND organization_id = ?
            ");
            $stmtUpd->execute([
                $title, $desc !== '' ? $desc : null, $canonicalType, $startFormatted, $endFormatted,
                $ownerId, $contactId, $companyId, $dealId, $canonicalStatus, $location !== '' ? $location : null,
                $clientVisible,
                $dbId, $organizationId
            ]);

            cal_json(true, "Event '{$title}' updated successfully.", [
                'event_id'       => $dbId,
                'id'             => 'evt_' . $dbId,
                'status'         => $canonicalStatus,
                'title'          => $title,
                'client_visible' => $clientVisible
            ]);
            break;

        // =========================================================
        // ACTION: RESCHEDULE (Drag-and-Drop or slot change)
        // =========================================================
        case 'reschedule':
            require_cal_perm('edit');

            $idRaw   = trim($inputData['id'] ?? '');
            $newDate = trim($inputData['date'] ?? '');

            if (empty($idRaw) || empty($newDate)) {
                cal_json(false, 'Event ID and new date are required.', [], 400);
            }

            // Protect Task Deadlines from accidental reschedule as calendar events
            if (str_starts_with(strtolower($idRaw), 'task')) {
                cal_json(false, 'Task deadlines cannot be rescheduled from the Calendar API. Please update the due date in Tasks.', [], 400);
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
                cal_json(false, 'Invalid new date format (YYYY-MM-DD).', [], 422);
            }

            $dbId = (int)preg_replace('/[^0-9]/', '', $idRaw);

            $stmt = $pdo->prepare("SELECT id, title, start_time, end_time FROM calendar_events WHERE id = ? AND organization_id = ? LIMIT 1");
            $stmt->execute([$dbId, $organizationId]);
            $orig = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orig) {
                cal_json(false, 'Calendar event not found.', [], 404);
            }

            $startTs = strtotime($orig['start_time']);
            $endTs   = strtotime($orig['end_time']);

            // Keep start_time and end_time identical: ONLY the date changes!
            $startTimePart = date('H:i:s', $startTs);
            $endTimePart   = date('H:i:s', $endTs);

            $newStart = "{$newDate} {$startTimePart}";
            $newEnd   = "{$newDate} {$endTimePart}";

            $upd = $pdo->prepare("UPDATE calendar_events SET start_time = ?, end_time = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
            $upd->execute([$newStart, $newEnd, $dbId, $organizationId]);

            cal_json(true, "Event rescheduled to {$newDate}.", [
                'event_id'   => $dbId,
                'id'         => 'evt_' . $dbId,
                'date'       => $newDate,
                'start_time' => $newStart,
                'end_time'   => $newEnd
            ]);
            break;


        // =========================================================
        // ACTION: DELETE (Permanently delete event from MySQL)
        // =========================================================
        case 'delete':
            require_cal_perm('delete');

            $idRaw = trim($_GET['id'] ?? $inputData['id'] ?? '');
            if (empty($idRaw)) {
                cal_json(false, 'Event ID is required.', [], 400);
            }

            // Protect Task Deadlines from accidental deletion as calendar events
            if (str_starts_with(strtolower($idRaw), 'task')) {
                cal_json(false, 'Task deadlines cannot be deleted from the calendar. Please manage tasks in the Tasks module.', [], 403);
            }

            $dbId = (int)preg_replace('/[^0-9]/', '', $idRaw);

            $delStmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ? AND organization_id = ?");
            $delStmt->execute([$dbId, $organizationId]);

            if ($delStmt->rowCount() === 0) {
                cal_json(false, 'Event not found or already deleted.', [], 404);
            }

            cal_json(true, "Event deleted successfully.");
            break;

        // =========================================================
        // ACTION: UPCOMING (Sidebar Next 5 Activities)
        // =========================================================
        case 'upcoming':
            require_cal_perm('view');

            // Next real upcoming events from now onwards
            $stmt = $pdo->prepare("
                SELECT 
                    c.id, c.title, c.event_type, c.start_time, c.end_time, c.status,
                    comp.name AS company_name, ct.name AS contact_name
                FROM calendar_events c
                LEFT JOIN companies comp ON comp.id = c.company_id
                LEFT JOIN contacts ct ON ct.id = c.contact_id
                WHERE c.organization_id = ?
                  AND c.start_time >= NOW()
                  AND c.status != 'cancelled'
                ORDER BY c.start_time ASC
                LIMIT 5
            ");
            $stmt->execute([$organizationId]);
            $rawUpcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $upcoming = [];
            foreach ($rawUpcoming as $u) {
                $typeMeta = get_cal_type_meta($u['event_type']);
                $ts = strtotime($u['start_time']);
                $upcoming[] = [
                    'id'           => 'evt_' . $u['id'],
                    'event_source' => 'calendar_event',
                    'title'        => $u['title'],
                    'type'         => $typeMeta['normalized'],
                    'typeIcon'     => $typeMeta['icon'],
                    'color'        => $typeMeta['color'],
                    'date_text'    => date('M j', $ts),
                    'time_text'    => date('g:i A', $ts),
                    'company'      => $u['company_name'] ?: '',
                    'contact'      => $u['contact_name'] ?: ''
                ];
            }

            cal_json(true, 'Upcoming activities loaded.', [
                'activities' => $upcoming
            ]);
            break;

        default:
            cal_json(false, "Unknown action: '$action'", [], 400);
    }
} catch (Throwable $e) {
    cal_json(false, 'Internal server error: ' . $e->getMessage(), [], 500);
}
