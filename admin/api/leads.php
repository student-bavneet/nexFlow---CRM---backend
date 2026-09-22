<?php
/**
 * NexFlow CRM - Leads Management API
 * Multi-tenant, organization-scoped controller for Leads, Activities, Notes, Tasks,
 * Calendar Events, Deal Conversions, and CSV Exports.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function leads_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    $response = ['success' => $success, 'message' => $message, 'data' => $data];
    foreach ($data as $k => $v) {
        if (!isset($response[$k])) {
            $response[$k] = $v;
        }
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authenticate user
$currentUser = nexflow_current_user();
if (!$currentUser) {
    leads_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId = (int)$currentUser['id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    leads_json(false, 'Database connection failed: ' . $e->getMessage(), [], 500);
}

// Support GET, POST (form-data/urlencoded) and raw JSON
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }
}

$action = $_GET['action'] ?? ($input['action'] ?? 'bootstrap');

// Helper to calculate avatar initials
function get_initials(string $name, ?string $first = null, ?string $last = null): string
{
    if ($first && $last) {
        return strtoupper(substr(trim($first), 0, 1) . substr(trim($last), 0, 1));
    }
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

// Deterministic avatar colors
function get_avatar_color(int $id): string
{
    $colors = ['#7C3AED', '#0284C7', '#10B981', '#F59E0B', '#6366F1', '#EC4899', '#14B8A6', '#8B5CF6'];
    return $colors[$id % count($colors)];
}

// Format relative time
function format_relative_time(?string $datetime): string
{
    if (!$datetime) return 'Never';
    $time = strtotime($datetime);
    if (!$time) return (string)$datetime;
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

// Log lead activity in team_activities
function log_lead_activity(PDO $pdo, int $orgId, int $leadId, ?int $userId, string $type, string $title, ?string $desc = null, int $createdBy = 1): void
{
    try {
        $targetUserId = ($userId && $userId > 0) ? $userId : $createdBy;
        $stmt = $pdo->prepare("INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'leads', ?, ?, NOW())");
        $stmt->execute([$orgId, $targetUserId, $type, $title, $desc, $leadId, $createdBy]);
    } catch (Throwable $e) {
        // Silently skip activity logging failures so core mutations never fail
    }
}

// Resolve assignee user_id from string name or ID
function resolve_assignee_id(PDO $pdo, int $orgId, $assignee): ?int
{
    if (empty($assignee)) return null;
    if (is_numeric($assignee)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1");
        $check->execute([(int)$assignee, $orgId]);
        $id = $check->fetchColumn();
        return $id ? (int)$id : null;
    }

    $name = trim((string)$assignee);
    $check = $pdo->prepare("SELECT id FROM users WHERE organization_id = ? AND (LOWER(name) = LOWER(?) OR LOWER(CONCAT(first_name, ' ', last_name)) = LOWER(?)) LIMIT 1");
    $check->execute([$orgId, $name, $name]);
    $id = $check->fetchColumn();
    return $id ? (int)$id : null;
}

// Format lead record for UI
function format_lead_row(array $row): array
{
    $fullName = trim(($row['name'] ?? '') ?: (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
    if (empty($fullName)) {
        $fullName = 'Lead #' . $row['id'];
    }
    $assigneeName = $row['assignee_name'] ?? (trim(($row['assignee_first'] ?? '') . ' ' . ($row['assignee_last'] ?? '')) ?: 'Unassigned');
    $assigneeInitials = get_initials($assigneeName, $row['assignee_first'] ?? null, $row['assignee_last'] ?? null);
    $assigneeColor = get_avatar_color((int)($row['assigned_to'] ?? $row['id']));
    
    $lastAct = $row['last_activity_at'] ?? $row['updated_at'] ?? $row['created_at'];

    return [
        'id' => (string)$row['id'],
        'lead_code' => $row['lead_code'] ?? ('LD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)),
        'name' => $fullName,
        'firstName' => $row['first_name'] ?? '',
        'lastName' => $row['last_name'] ?? '',
        'email' => $row['email'] ?? '',
        'phone' => $row['phone'] ?? '',
        'whatsapp' => $row['whatsapp'] ?? '',
        'company' => $row['company'] ?? '',
        'jobTitle' => $row['job_title'] ?? '',
        'location' => $row['location'] ?? '',
        'status' => $row['status'] ?? 'New',
        'score' => (int)($row['score'] ?? 50),
        'value' => (float)($row['value'] ?? 0),
        'source' => $row['source'] ?? 'Website',
        'assignee' => $assigneeName,
        'assigneeId' => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
        'assigneeInitials' => $assigneeInitials,
        'assigneeColor' => $assigneeColor,
        'createdAt' => date('M j, Y', strtotime($row['created_at'])),
        'lastActivity' => format_relative_time($lastAct),
        'lastActivityRaw' => $lastAct,
        'isArchived' => (bool)($row['is_archived'] ?? 0)
    ];
}

// =========================================================================
// ROUTING
// =========================================================================

switch ($action) {

    // ---------------------------------------------------------------------
    // 1. BOOTSTRAP / LIST
    // ---------------------------------------------------------------------
    case 'bootstrap':
    case 'list':
        if (!hasPermission('leads', 'view')) {
            leads_json(false, 'You do not have permission to view leads.', [], 403);
        }

        $archiveStatus = trim($_GET['archive_status'] ?? ($input['archive_status'] ?? 'active'));
        if ($archiveStatus === 'archived') {
            $archiveClause = "l.is_archived = 1";
        } elseif ($archiveStatus === 'all') {
            $archiveClause = "1=1";
        } else {
            $archiveStatus = 'active';
            $archiveClause = "l.is_archived = 0";
        }

        // Fetch dynamic status counts for active leads only
        $statusCounts = [
            'All' => 0,
            'New' => 0,
            'Contacted' => 0,
            'Qualified' => 0,
            'Proposal' => 0,
            'Won' => 0,
            'Lost' => 0
        ];
        $countStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM leads WHERE organization_id = ? AND is_archived = 0 GROUP BY status");
        $countStmt->execute([$organizationId]);
        $totalAll = 0;
        while ($c = $countStmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($statusCounts[$c['status']])) {
                $statusCounts[$c['status']] = (int)$c['count'];
            }
            $totalAll += (int)$c['count'];
        }
        $statusCounts['All'] = $totalAll;

        // Archived leads count
        $archivedCountStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE organization_id = ? AND is_archived = 1");
        $archivedCountStmt->execute([$organizationId]);
        $archivedCount = (int)$archivedCountStmt->fetchColumn();

        // Fetch organization active users for assignment
        $userStmt = $pdo->prepare("SELECT id, name, first_name, last_name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
        $userStmt->execute([$organizationId]);
        $orgUsers = [];
        while ($u = $userStmt->fetch(PDO::FETCH_ASSOC)) {
            $uName = trim($u['name'] ?: ($u['first_name'] . ' ' . $u['last_name']));
            $orgUsers[] = [
                'id' => (int)$u['id'],
                'name' => $uName,
                'email' => $u['email'],
                'initials' => get_initials($uName, $u['first_name'], $u['last_name']),
                'color' => get_avatar_color((int)$u['id'])
            ];
        }

        // Fetch leads matching archive filter
        $leadSql = "
            SELECT l.*, 
                   u.name AS assignee_name, 
                   u.first_name AS assignee_first, 
                   u.last_name AS assignee_last,
                   (SELECT MAX(created_at) FROM team_activities WHERE organization_id = ? AND related_entity = 'leads' AND related_entity_id = l.id) AS last_activity_at
            FROM leads l
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE l.organization_id = ? AND {$archiveClause}
            ORDER BY l.id DESC
        ";
        $leadStmt = $pdo->prepare($leadSql);
        $leadStmt->execute([$organizationId, $organizationId]);
        $rawLeads = $leadStmt->fetchAll(PDO::FETCH_ASSOC);

        $leadsList = array_map('format_lead_row', $rawLeads);

        leads_json(true, 'Leads bootstrapped successfully.', [
            'leads' => $leadsList,
            'status_counts' => $statusCounts,
            'archived_count' => $archivedCount,
            'archive_status' => $archiveStatus,
            'users' => $orgUsers,
            'permissions' => [
                'view' => hasPermission('leads', 'view'),
                'create' => hasPermission('leads', 'create'),
                'edit' => hasPermission('leads', 'edit'),
                'delete' => hasPermission('leads', 'delete'),
                'assign' => hasPermission('leads', 'assign'),
                'export' => hasPermission('leads', 'export')
            ]
        ]);
        break;

    // ---------------------------------------------------------------------
    // 2. LEAD DRAWER DETAILS
    // ---------------------------------------------------------------------
    case 'lead_drawer':
        if (!hasPermission('leads', 'view')) {
            leads_json(false, 'You do not have permission to view lead details.', [], 403);
        }

        $leadId = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($leadId <= 0) {
            leads_json(false, 'Invalid Lead ID.', [], 400);
        }

        $stmt = $pdo->prepare("
            SELECT l.*, 
                   u.name AS assignee_name, 
                   u.first_name AS assignee_first, 
                   u.last_name AS assignee_last,
                   (SELECT MAX(created_at) FROM team_activities WHERE organization_id = ? AND related_entity = 'leads' AND related_entity_id = l.id) AS last_activity_at
            FROM leads l
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE l.id = ? AND l.organization_id = ?
            LIMIT 1
        ");
        $stmt->execute([$organizationId, $leadId, $organizationId]);
        $leadRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$leadRow) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $leadFormatted = format_lead_row($leadRow);

        // Fetch linked Deals
        $dealsStmt = $pdo->prepare("
            SELECT d.id, d.deal_code, d.name, d.company, d.stage, d.value, d.status, d.close_date, d.created_at,
                   u.name as assignee_name, u.first_name as assignee_first, u.last_name as assignee_last
            FROM deals d
            LEFT JOIN users u ON u.id = d.assigned_to
            WHERE d.organization_id = ? AND d.lead_id = ?
            ORDER BY d.id DESC
        ");
        $dealsStmt->execute([$organizationId, $leadId]);
        $deals = [];
        while ($d = $dealsStmt->fetch(PDO::FETCH_ASSOC)) {
            $dAssignee = $d['assignee_name'] ?: trim(($d['assignee_first'] ?? '') . ' ' . ($d['assignee_last'] ?? '')) ?: 'Unassigned';
            $deals[] = [
                'id' => (string)$d['id'],
                'deal_code' => $d['deal_code'] ?: ('DL-' . str_pad($d['id'], 4, '0', STR_PAD_LEFT)),
                'name' => $d['name'],
                'company' => $d['company'],
                'stage' => $d['stage'],
                'value' => (float)$d['value'],
                'status' => $d['status'],
                'closeDate' => $d['close_date'] ? date('M j, Y', strtotime($d['close_date'])) : 'TBD',
                'assignee' => $dAssignee,
                'createdAt' => date('M j, Y', strtotime($d['created_at']))
            ];
        }

        // Fetch linked Tasks
        $tasksStmt = $pdo->prepare("
            SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at,
                   u.name as assignee_name, u.first_name as assignee_first, u.last_name as assignee_last
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            WHERE t.organization_id = ? AND t.related_type = 'leads' AND t.related_id = ?
            ORDER BY t.due_date ASC, t.id DESC
        ");
        $tasksStmt->execute([$organizationId, $leadId]);
        $tasks = [];
        $nowTs = time();
        while ($t = $tasksStmt->fetch(PDO::FETCH_ASSOC)) {
            $tAssignee = $t['assignee_name'] ?: trim(($t['assignee_first'] ?? '') . ' ' . ($t['assignee_last'] ?? '')) ?: 'Unassigned';
            $dueTs = $t['due_date'] ? strtotime($t['due_date']) : null;
            $group = 'upcoming';
            if ($dueTs) {
                if (date('Y-m-d', $dueTs) === date('Y-m-d', $nowTs)) {
                    $group = 'today';
                } elseif ($dueTs < $nowTs && $t['status'] !== 'completed') {
                    $group = 'overdue';
                }
            }
            $tasks[] = [
                'id' => (string)$t['id'],
                'title' => $t['title'],
                'description' => $t['description'] ?? '',
                'status' => $t['status'],
                'completed' => ($t['status'] === 'completed'),
                'priority' => ucfirst($t['priority'] ?? 'Medium'),
                'dueDate' => $t['due_date'] ? date('M j, Y', $dueTs) : 'No date',
                'dueTime' => $t['due_date'] ? date('g:i A', $dueTs) : '',
                'group' => $group,
                'assignee' => $tAssignee,
                'createdAt' => date('M j, Y', strtotime($t['created_at']))
            ];
        }

        // Fetch linked Notes
        $notesStmt = $pdo->prepare("
            SELECT n.id, n.content, n.is_pinned, n.created_at, n.updated_at,
                   u.name as author_name, u.first_name as author_first, u.last_name as author_last
            FROM team_notes n
            LEFT JOIN users u ON u.id = n.created_by
            WHERE n.organization_id = ? AND n.related_type = 'leads' AND n.related_id = ?
            ORDER BY n.is_pinned DESC, n.created_at DESC
        ");
        $notesStmt->execute([$organizationId, $leadId]);
        $notes = [];
        while ($n = $notesStmt->fetch(PDO::FETCH_ASSOC)) {
            $author = $n['author_name'] ?: trim(($n['author_first'] ?? '') . ' ' . ($n['author_last'] ?? '')) ?: 'System';
            $notes[] = [
                'id' => (string)$n['id'],
                'author' => $author,
                'date' => format_relative_time($n['created_at']),
                'content' => $n['content'],
                'pinned' => (bool)$n['is_pinned'],
                'createdAt' => date('M j, Y g:i A', strtotime($n['created_at']))
            ];
        }

        // Fetch linked Activities
        $actStmt = $pdo->prepare("
            SELECT a.id, a.activity_type, a.title, a.description, a.created_at,
                   u.name as actor_name, u.first_name as actor_first, u.last_name as actor_last
            FROM team_activities a
            LEFT JOIN users u ON u.id = a.created_by
            WHERE a.organization_id = ? AND a.related_entity = 'leads' AND a.related_entity_id = ?
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $actStmt->execute([$organizationId, $leadId]);
        $activities = [];
        while ($a = $actStmt->fetch(PDO::FETCH_ASSOC)) {
            $actor = $a['actor_name'] ?: trim(($a['actor_first'] ?? '') . ' ' . ($a['actor_last'] ?? '')) ?: 'System';
            $activities[] = [
                'id' => (string)$a['id'],
                'type' => $a['activity_type'],
                'title' => $a['title'],
                'desc' => $a['description'] ?? '',
                'actor' => $actor,
                'time' => format_relative_time($a['created_at']),
                'createdAt' => date('M j, Y g:i A', strtotime($a['created_at']))
            ];
        }

        // Fetch linked Calendar Events (Meetings)
        $meetStmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, c.event_type, c.start_time, c.end_time, c.status,
                   u.name as host_name, u.first_name as host_first, u.last_name as host_last
            FROM calendar_events c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.organization_id = ? AND c.related_type = 'leads' AND c.related_id = ?
            ORDER BY c.start_time ASC
        ");
        $meetStmt->execute([$organizationId, $leadId]);
        $meetings = [];
        while ($m = $meetStmt->fetch(PDO::FETCH_ASSOC)) {
            $host = $m['host_name'] ?: trim(($m['host_first'] ?? '') . ' ' . ($m['host_last'] ?? '')) ?: 'Host';
            $meetings[] = [
                'id' => (string)$m['id'],
                'title' => $m['title'],
                'desc' => $m['description'] ?? '',
                'type' => $m['event_type'],
                'status' => $m['status'],
                'startTime' => date('M j, Y g:i A', strtotime($m['start_time'])),
                'endTime' => date('g:i A', strtotime($m['end_time'])),
                'host' => $host
            ];
        }

        leads_json(true, 'Lead details loaded.', [
            'lead' => $leadFormatted,
            'deals' => $deals,
            'tasks' => $tasks,
            'notes' => $notes,
            'activities' => $activities,
            'meetings' => $meetings
        ]);
        break;

    // ---------------------------------------------------------------------
    // 3. CREATE LEAD
    // ---------------------------------------------------------------------
    case 'create_lead':
        if (!hasPermission('leads', 'create')) {
            leads_json(false, 'You do not have permission to create leads.', [], 403);
        }

        $rawName = trim((string)($input['name'] ?? ''));
        $rawEmail = trim((string)($input['email'] ?? ''));
        $rawCompany = trim((string)($input['company'] ?? ''));

        if (empty($rawName)) {
            leads_json(false, 'Lead Full Name is required.', [], 422);
        }
        if (empty($rawEmail) || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
            leads_json(false, 'A valid email address is required.', [], 422);
        }
        if (empty($rawCompany)) {
            leads_json(false, 'Company Name is required.', [], 422);
        }

        // Duplicate email validation within organization
        $dupCheck = $pdo->prepare("SELECT id FROM leads WHERE organization_id = ? AND LOWER(email) = LOWER(?) LIMIT 1");
        $dupCheck->execute([$organizationId, $rawEmail]);
        if ($dupCheck->fetch()) {
            leads_json(false, "A lead with email '{$rawEmail}' already exists in your organization.", [], 409);
        }

        // Parse names
        $parts = preg_split('/\s+/', $rawName);
        $firstName = $parts[0] ?? '';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        $phone = trim((string)($input['phone'] ?? ''));
        $whatsapp = trim((string)($input['whatsapp'] ?? ''));
        $jobTitle = trim((string)($input['job_title'] ?? $input['jobTitle'] ?? ''));
        $location = trim((string)($input['location'] ?? ''));
        
        $validStatuses = ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'];
        $status = in_array($input['status'] ?? '', $validStatuses, true) ? $input['status'] : 'New';

        $score = isset($input['score']) ? max(0, min(100, (int)$input['score'])) : 50;
        $dealValue = isset($input['value']) ? max(0, (float)$input['value']) : 0.0;
        $source = trim((string)($input['source'] ?? 'Website')) ?: 'Website';

        // Resolve Assignee
        $assignedTo = resolve_assignee_id($pdo, $organizationId, $input['assigned_to'] ?? ($input['assignee'] ?? null)) ?: $currentUserId;

        // Auto-generate code
        $leadCode = 'LD-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $insStmt = $pdo->prepare("
            INSERT INTO leads (
                organization_id, lead_code, name, first_name, last_name, email, phone, whatsapp, 
                company, job_title, location, source, status, score, value, assigned_to, 
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?, ?, 
                NOW(), NOW()
            )
        ");
        $insStmt->execute([
            $organizationId, $leadCode, $rawName, $firstName, $lastName, $rawEmail, $phone, $whatsapp,
            $rawCompany, $jobTitle, $location, $source, $status, $score, $dealValue, $assignedTo
        ]);

        $newLeadId = (int)$pdo->lastInsertId();

        // Update code with padded ID
        $cleanCode = 'LD-' . str_pad($newLeadId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE leads SET lead_code = ? WHERE id = ?")->execute([$cleanCode, $newLeadId]);

        // Log initial activity
        log_lead_activity(
            $pdo, $organizationId, $newLeadId, $assignedTo,
            'created', 'Lead Created',
            "Lead {$rawName} ({$rawCompany}) was created by {$currentUser['name']}.",
            $currentUserId
        );

        // Fetch newly created lead with user joins
        $fetchStmt = $pdo->prepare("
            SELECT l.*, 
                   u.name AS assignee_name, 
                   u.first_name AS assignee_first, 
                   u.last_name AS assignee_last
            FROM leads l
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE l.id = ? AND l.organization_id = ?
        ");
        $fetchStmt->execute([$newLeadId, $organizationId]);
        $createdRow = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        leads_json(true, 'Lead created successfully.', [
            'lead' => format_lead_row($createdRow)
        ], 201);
        break;

    // ---------------------------------------------------------------------
    // 4. UPDATE LEAD
    // ---------------------------------------------------------------------
    case 'update_lead':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to edit leads.', [], 403);
        }

        $leadId = (int)($input['id'] ?? 0);
        if ($leadId <= 0) {
            leads_json(false, 'Invalid Lead ID.', [], 400);
        }

        $checkStmt = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $checkStmt->execute([$leadId, $organizationId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $rawName = trim((string)($input['name'] ?? ''));
        $rawEmail = trim((string)($input['email'] ?? ''));
        $rawCompany = trim((string)($input['company'] ?? ''));

        if (empty($rawName)) {
            leads_json(false, 'Lead Full Name is required.', [], 422);
        }
        if (empty($rawEmail) || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
            leads_json(false, 'A valid email address is required.', [], 422);
        }
        if (empty($rawCompany)) {
            leads_json(false, 'Company Name is required.', [], 422);
        }

        // Duplicate email check
        $dupCheck = $pdo->prepare("SELECT id FROM leads WHERE organization_id = ? AND LOWER(email) = LOWER(?) AND id != ? LIMIT 1");
        $dupCheck->execute([$organizationId, $rawEmail, $leadId]);
        if ($dupCheck->fetch()) {
            leads_json(false, "Another lead with email '{$rawEmail}' already exists in your organization.", [], 409);
        }

        $parts = preg_split('/\s+/', $rawName);
        $firstName = $parts[0] ?? '';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        $phone = trim((string)($input['phone'] ?? $existing['phone']));
        $whatsapp = trim((string)($input['whatsapp'] ?? $existing['whatsapp']));
        $jobTitle = trim((string)($input['job_title'] ?? $input['jobTitle'] ?? $existing['job_title']));
        $location = trim((string)($input['location'] ?? $existing['location']));
        
        $validStatuses = ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'];
        $status = in_array($input['status'] ?? '', $validStatuses, true) ? $input['status'] : $existing['status'];

        $score = isset($input['score']) ? max(0, min(100, (int)$input['score'])) : (int)$existing['score'];
        $dealValue = isset($input['value']) ? max(0, (float)$input['value']) : (float)($existing['value'] ?? 0);
        $source = trim((string)($input['source'] ?? $existing['source'])) ?: $existing['source'];

        $assignedTo = $existing['assigned_to'];
        if (isset($input['assigned_to']) || isset($input['assignee']) || isset($input['owner'])) {
            $cand = $input['assigned_to'] ?? ($input['assignee'] ?? $input['owner']);
            $newAssigned = resolve_assignee_id($pdo, $organizationId, $cand);
            if ($newAssigned) {
                $assignedTo = $newAssigned;
            } elseif ($cand === '' || $cand === null || $cand === 0 || $cand === '0') {
                $assignedTo = null;
            }
        }

        $upStmt = $pdo->prepare("
            UPDATE leads SET
                name = ?, first_name = ?, last_name = ?, email = ?, phone = ?, whatsapp = ?,
                company = ?, job_title = ?, location = ?, source = ?, status = ?,
                score = ?, value = ?, assigned_to = ?, updated_at = NOW()
            WHERE id = ? AND organization_id = ?
        ");
        $upStmt->execute([
            $rawName, $firstName, $lastName, $rawEmail, $phone, $whatsapp,
            $rawCompany, $jobTitle, $location, $source, $status,
            $score, $dealValue, $assignedTo, $leadId, $organizationId
        ]);

        log_lead_activity(
            $pdo, $organizationId, $leadId, $assignedTo,
            'updated', 'Lead Details Updated',
            "Lead details updated by {$currentUser['name']}.",
            $currentUserId
        );

        // Return updated lead
        $fetchStmt = $pdo->prepare("
            SELECT l.*, 
                   u.name AS assignee_name, 
                   u.first_name AS assignee_first, 
                   u.last_name AS assignee_last
            FROM leads l
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE l.id = ? AND l.organization_id = ?
        ");
        $fetchStmt->execute([$leadId, $organizationId]);
        $updatedRow = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        leads_json(true, 'Lead updated successfully.', [
            'lead' => format_lead_row($updatedRow)
        ]);
        break;

    // ---------------------------------------------------------------------
    // 5. QUICK STATUS UPDATE
    // ---------------------------------------------------------------------
    case 'update_status':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to edit lead status.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? $input['id'] ?? 0);
        $newStatus = trim((string)($input['status'] ?? ''));

        $validStatuses = ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'];
        if (!in_array($newStatus, $validStatuses, true)) {
            leads_json(false, 'Invalid lead status.', [], 422);
        }

        $check = $pdo->prepare("SELECT id, status, assigned_to FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $check->execute([$leadId, $organizationId]);
        $lead = $check->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $oldStatus = $lead['status'];
        $pdo->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$newStatus, $leadId, $organizationId]);

        log_lead_activity(
            $pdo, $organizationId, $leadId, (int)$lead['assigned_to'],
            'status_changed', 'Status Updated',
            "Status changed from {$oldStatus} to {$newStatus} by {$currentUser['name']}.",
            $currentUserId
        );

        leads_json(true, "Lead status updated to {$newStatus}.", [
            'leadId' => $leadId,
            'status' => $newStatus
        ]);
        break;

    // ---------------------------------------------------------------------
    // 6. QUICK OWNER / ASSIGNEE UPDATE
    // ---------------------------------------------------------------------
    case 'update_owner':
        if (!hasPermission('leads', 'assign')) {
            leads_json(false, 'You do not have permission to assign leads.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? $input['id'] ?? 0);
        $candOwner = $input['assigned_to'] ?? $input['assignee'] ?? ($input['owner'] ?? null);

        $check = $pdo->prepare("SELECT id, assigned_to FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $check->execute([$leadId, $organizationId]);
        $lead = $check->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $newOwnerId = resolve_assignee_id($pdo, $organizationId, $candOwner);
        if (!$newOwnerId) {
            leads_json(false, 'Target assignee not found in your organization.', [], 422);
        }

        $pdo->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$newOwnerId, $leadId, $organizationId]);

        $ownerNameStmt = $pdo->prepare("SELECT name, first_name, last_name FROM users WHERE id = ?");
        $ownerNameStmt->execute([$newOwnerId]);
        $oRow = $ownerNameStmt->fetch(PDO::FETCH_ASSOC);
        $ownerName = $oRow['name'] ?: trim(($oRow['first_name'] ?? '') . ' ' . ($oRow['last_name'] ?? '')) ?: 'User';

        log_lead_activity(
            $pdo, $organizationId, $leadId, $newOwnerId,
            'owner_reassigned', 'Lead Reassigned',
            "Assigned to {$ownerName} by {$currentUser['name']}.",
            $currentUserId
        );

        leads_json(true, "Lead assigned to {$ownerName}.", [
            'leadId' => $leadId,
            'assignee' => $ownerName,
            'assigneeId' => $newOwnerId,
            'assigneeInitials' => get_initials($ownerName, $oRow['first_name'] ?? null, $oRow['last_name'] ?? null),
            'assigneeColor' => get_avatar_color($newOwnerId)
        ]);
        break;

    // ---------------------------------------------------------------------
    // 7. DELETE LEAD
    // ---------------------------------------------------------------------
    case 'delete_lead':
        if (!hasPermission('leads', 'delete')) {
            leads_json(false, 'You do not have permission to delete leads.', [], 403);
        }

        $leadId = (int)($input['id'] ?? 0);
        $check = $pdo->prepare("SELECT id, first_name, last_name, company FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $check->execute([$leadId, $organizationId]);
        $lead = $check->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        // Clean up linked relations
        $pdo->prepare("DELETE FROM tasks WHERE organization_id = ? AND related_type = 'leads' AND related_id = ?")->execute([$organizationId, $leadId]);
        $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'leads' AND related_id = ?")->execute([$organizationId, $leadId]);
        $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'leads' AND related_entity_id = ?")->execute([$organizationId, $leadId]);
        $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = ? AND related_type = 'leads' AND related_id = ?")->execute([$organizationId, $leadId]);
        $pdo->prepare("UPDATE deals SET lead_id = NULL WHERE organization_id = ? AND lead_id = ?")->execute([$organizationId, $leadId]);

        // Delete lead
        $pdo->prepare("DELETE FROM leads WHERE id = ? AND organization_id = ?")->execute([$leadId, $organizationId]);

        leads_json(true, 'Lead and all associated records deleted successfully.', ['leadId' => $leadId]);
        break;

    // ---------------------------------------------------------------------
    // 8. BULK DELETE LEADS
    // ---------------------------------------------------------------------
    case 'bulk_delete':
        if (!hasPermission('leads', 'delete')) {
            leads_json(false, 'You do not have permission to delete leads.', [], 403);
        }

        $ids = $input['ids'] ?? [];
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                $ids = array_filter(array_map('trim', explode(',', $ids)));
            }
        }
        if (!is_array($ids) || empty($ids)) {
            leads_json(false, 'No lead IDs provided for bulk deletion.', [], 400);
        }

        $deletedCount = 0;
        foreach ($ids as $rawId) {
            $lid = (int)$rawId;
            if ($lid <= 0) continue;

            $check = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
            $check->execute([$lid, $organizationId]);
            if ($check->fetch()) {
                $pdo->prepare("DELETE FROM tasks WHERE organization_id = ? AND related_type = 'leads' AND related_id = ?")->execute([$organizationId, $lid]);
                $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'leads' AND related_id = ?")->execute([$organizationId, $lid]);
                $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'leads' AND related_entity_id = ?")->execute([$organizationId, $lid]);
                $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = ? AND related_type = 'leads' AND related_id = ?")->execute([$organizationId, $lid]);
                $pdo->prepare("UPDATE deals SET lead_id = NULL WHERE organization_id = ? AND lead_id = ?")->execute([$organizationId, $lid]);
                $pdo->prepare("DELETE FROM leads WHERE id = ? AND organization_id = ?")->execute([$lid, $organizationId]);
                $deletedCount++;
            }
        }

        leads_json(true, "Successfully deleted {$deletedCount} leads.", ['deletedCount' => $deletedCount, 'deleted' => $deletedCount]);
        break;

    // ---------------------------------------------------------------------
    // 9. TASK ACTIONS
    // ---------------------------------------------------------------------
    case 'create_task':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to create lead tasks.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $type = trim((string)($input['type'] ?? 'Follow-up'));
        $priority = strtolower(trim((string)($input['priority'] ?? 'medium')));
        $dueDate = trim((string)($input['due_date'] ?? date('Y-m-d')));
        $dueTime = trim((string)($input['due_time'] ?? '17:00'));

        if (empty($title)) {
            leads_json(false, 'Task Title is required.', [], 422);
        }

        $leadCheck = $pdo->prepare("SELECT id, assigned_to FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $leadCheck->execute([$leadId, $organizationId]);
        $lead = $leadCheck->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $assigneeId = resolve_assignee_id($pdo, $organizationId, $input['assignee'] ?? null) ?: ((int)$lead['assigned_to'] ?: $currentUserId);
        $dueDateTime = date('Y-m-d H:i:s', strtotime("{$dueDate} {$dueTime}"));
        $fullTitle = "[{$type}] {$title}";

        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'medium';
        }

        $tStmt = $pdo->prepare("
            INSERT INTO tasks (
                organization_id, title, description, status, priority, due_date, 
                assigned_to, related_type, related_id, created_at, updated_at
            ) VALUES (
                ?, ?, ?, 'pending', ?, ?, 
                ?, 'leads', ?, NOW(), NOW()
            )
        ");
        $tStmt->execute([
            $organizationId, $fullTitle, $type, $priority, $dueDateTime,
            $assigneeId, $leadId
        ]);
        $newTaskId = (int)$pdo->lastInsertId();

        log_lead_activity(
            $pdo, $organizationId, $leadId, $assigneeId,
            'task_added', 'Task Created',
            "Task '{$fullTitle}' created by {$currentUser['name']}.",
            $currentUserId
        );

        leads_json(true, 'Task created successfully.', [
            'task' => [
                'id' => (string)$newTaskId,
                'title' => $fullTitle,
                'status' => 'pending',
                'completed' => false,
                'priority' => ucfirst($priority),
                'dueDate' => date('M j, Y', strtotime($dueDateTime)),
                'dueTime' => date('g:i A', strtotime($dueDateTime)),
                'group' => (date('Y-m-d', strtotime($dueDateTime)) === date('Y-m-d')) ? 'today' : 'upcoming'
            ]
        ], 201);
        break;

    case 'toggle_task_status':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to update tasks.', [], 403);
        }

        $taskId = (int)($input['task_id'] ?? 0);
        $completed = !empty($input['completed']);

        $tCheck = $pdo->prepare("SELECT id, related_id FROM tasks WHERE id = ? AND organization_id = ? AND related_type = 'leads' LIMIT 1");
        $tCheck->execute([$taskId, $organizationId]);
        $task = $tCheck->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            leads_json(false, 'Task not found or access denied.', [], 404);
        }

        $newStatus = $completed ? 'completed' : 'pending';
        $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$newStatus, $taskId, $organizationId]);

        leads_json(true, "Task status updated to {$newStatus}.", ['taskId' => $taskId, 'status' => $newStatus]);
        break;

    case 'delete_task':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to delete tasks.', [], 403);
        }

        $taskId = (int)($input['task_id'] ?? 0);
        $tCheck = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND organization_id = ? AND related_type = 'leads' LIMIT 1");
        $tCheck->execute([$taskId, $organizationId]);
        if (!$tCheck->fetch()) {
            leads_json(false, 'Task not found or access denied.', [], 404);
        }

        $pdo->prepare("DELETE FROM tasks WHERE id = ? AND organization_id = ?")->execute([$taskId, $organizationId]);
        leads_json(true, 'Task deleted successfully.', ['taskId' => $taskId]);
        break;

    // ---------------------------------------------------------------------
    // 10. NOTE ACTIONS
    // ---------------------------------------------------------------------
    case 'create_note':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to add notes.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? 0);
        $content = trim((string)($input['content'] ?? ''));
        $pinned = !empty($input['pinned']) ? 1 : 0;

        if (empty($content)) {
            leads_json(false, 'Note content cannot be empty.', [], 422);
        }

        $lCheck = $pdo->prepare("SELECT id, assigned_to FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $lCheck->execute([$leadId, $organizationId]);
        $lead = $lCheck->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $nStmt = $pdo->prepare("
            INSERT INTO team_notes (
                organization_id, member_id, related_type, related_id, created_by, 
                content, is_pinned, created_at, updated_at
            ) VALUES (
                ?, NULL, 'leads', ?, ?, 
                ?, ?, NOW(), NOW()
            )
        ");
        $nStmt->execute([$organizationId, $leadId, $currentUserId, $content, $pinned]);
        $noteId = (int)$pdo->lastInsertId();

        log_lead_activity(
            $pdo, $organizationId, $leadId, (int)$lead['assigned_to'],
            'note_added', 'Note Added',
            "New note added by {$currentUser['name']}.",
            $currentUserId
        );

        leads_json(true, 'Note saved successfully.', [
            'note' => [
                'id' => (string)$noteId,
                'author' => $currentUser['name'],
                'date' => 'Just now',
                'content' => $content,
                'pinned' => (bool)$pinned
            ]
        ], 201);
        break;

    case 'update_note':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to edit notes.', [], 403);
        }

        $noteId = (int)($input['note_id'] ?? 0);
        $content = trim((string)($input['content'] ?? ''));
        $pinned = !empty($input['pinned']) ? 1 : 0;

        if (empty($content)) {
            leads_json(false, 'Note content cannot be empty.', [], 422);
        }

        $nCheck = $pdo->prepare("SELECT id FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'leads' LIMIT 1");
        $nCheck->execute([$noteId, $organizationId]);
        if (!$nCheck->fetch()) {
            leads_json(false, 'Note not found or access denied.', [], 404);
        }

        $pdo->prepare("UPDATE team_notes SET content = ?, is_pinned = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$content, $pinned, $noteId, $organizationId]);

        leads_json(true, 'Note updated successfully.', [
            'noteId' => $noteId,
            'content' => $content,
            'pinned' => (bool)$pinned
        ]);
        break;

    case 'toggle_note_pin':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to pin notes.', [], 403);
        }

        $noteId = (int)($input['note_id'] ?? 0);
        $pinned = !empty($input['pinned']) ? 1 : 0;

        $nCheck = $pdo->prepare("SELECT id FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'leads' LIMIT 1");
        $nCheck->execute([$noteId, $organizationId]);
        if (!$nCheck->fetch()) {
            leads_json(false, 'Note not found or access denied.', [], 404);
        }

        $pdo->prepare("UPDATE team_notes SET is_pinned = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$pinned, $noteId, $organizationId]);

        leads_json(true, 'Note pin status updated.', ['noteId' => $noteId, 'pinned' => (bool)$pinned]);
        break;

    case 'delete_note':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to delete notes.', [], 403);
        }

        $noteId = (int)($input['note_id'] ?? 0);
        $nCheck = $pdo->prepare("SELECT id FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'leads' LIMIT 1");
        $nCheck->execute([$noteId, $organizationId]);
        if (!$nCheck->fetch()) {
            leads_json(false, 'Note not found or access denied.', [], 404);
        }

        $pdo->prepare("DELETE FROM team_notes WHERE id = ? AND organization_id = ?")->execute([$noteId, $organizationId]);
        leads_json(true, 'Note deleted successfully.', ['noteId' => $noteId]);
        break;

    // ---------------------------------------------------------------------
    // 11. SCHEDULE MEETING
    // ---------------------------------------------------------------------
    case 'schedule_meeting':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to schedule meetings.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $date = trim((string)($input['date'] ?? date('Y-m-d')));
        $time = trim((string)($input['time'] ?? '10:00'));
        $duration = trim((string)($input['duration'] ?? '30 mins'));
        $type = trim((string)($input['type'] ?? 'Video Call'));
        $desc = trim((string)($input['description'] ?? ''));

        if (empty($title)) {
            leads_json(false, 'Meeting title is required.', [], 422);
        }

        $lCheck = $pdo->prepare("SELECT id, assigned_to FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $lCheck->execute([$leadId, $organizationId]);
        $lead = $lCheck->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $hostId = resolve_assignee_id($pdo, $organizationId, $input['assignee'] ?? null) ?: ((int)$lead['assigned_to'] ?: $currentUserId);

        $startDatetime = date('Y-m-d H:i:s', strtotime("{$date} {$time}"));
        $minutes = 30;
        if (strpos($duration, '15') !== false) $minutes = 15;
        elseif (strpos($duration, '45') !== false) $minutes = 45;
        elseif (strpos($duration, '1 hour') !== false) $minutes = 60;
        $endDatetime = date('Y-m-d H:i:s', strtotime("{$startDatetime} +{$minutes} minutes"));

        $mStmt = $pdo->prepare("
            INSERT INTO calendar_events (
                organization_id, user_id, related_type, related_id, title, description, 
                event_type, start_time, end_time, status, created_at, updated_at
            ) VALUES (
                ?, ?, 'leads', ?, ?, ?, 
                ?, ?, ?, 'scheduled', NOW(), NOW()
            )
        ");
        $mStmt->execute([
            $organizationId, $hostId, $leadId, $title, $desc,
            $type, $startDatetime, $endDatetime
        ]);
        $meetId = (int)$pdo->lastInsertId();

        log_lead_activity(
            $pdo, $organizationId, $leadId, $hostId,
            'meeting_scheduled', 'Meeting Scheduled',
            "Meeting '{$title}' scheduled for " . date('M j, Y g:i A', strtotime($startDatetime)) . " by {$currentUser['name']}.",
            $currentUserId
        );

        leads_json(true, 'Meeting scheduled successfully.', [
            'meeting' => [
                'id' => (string)$meetId,
                'title' => $title,
                'type' => $type,
                'startTime' => date('M j, Y g:i A', strtotime($startDatetime)),
                'endTime' => date('g:i A', strtotime($endDatetime))
            ]
        ], 201);
        break;

    // ---------------------------------------------------------------------
    // 12. CONVERT LEAD TO CONTACT & DEAL (TRANSACTIONAL)
    // ---------------------------------------------------------------------
    case 'convert_contact':
    case 'convert_lead':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to convert leads.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? ($_POST['lead_id'] ?? 0));
        if ($leadId <= 0) {
            leads_json(false, 'Invalid lead ID.', [], 400);
        }

        $pdo->beginTransaction();
        try {
            $lCheck = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND organization_id = ? LIMIT 1 FOR UPDATE");
            $lCheck->execute([$leadId, $organizationId]);
            $lead = $lCheck->fetch(PDO::FETCH_ASSOC);
            if (!$lead) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                leads_json(false, 'Lead not found or access denied.', [], 404);
            }

            $leadName = trim(($lead['name'] ?? '') ?: (($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')));
            $parts = preg_split('/\s+/', $leadName);
            $firstName = $lead['first_name'] ?: ($parts[0] ?? '');
            $lastName = $lead['last_name'] ?: (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '');
            $companyName = trim((string)($lead['company'] ?? ''));
            $email = trim((string)($lead['email'] ?? ''));
            $phone = trim((string)($lead['phone'] ?? ''));
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $whatsapp = trim((string)($lead['whatsapp'] ?? ''));
            $location = trim((string)($lead['location'] ?? ''));
            $jobTitle = trim((string)($lead['job_title'] ?? ''));
            $dealValue = (float)($lead['value'] ?? 0);
            $assignedTo = (int)($lead['assigned_to'] ?: $currentUserId);

            // 1. Resolve Company in companies table (if company name is provided)
            $companyId = null;
            if ($companyName !== '') {
                $chkComp = $pdo->prepare("
                    SELECT id, name FROM companies 
                    WHERE organization_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) AND is_active = 1 
                    LIMIT 1
                ");
                $chkComp->execute([$organizationId, $companyName]);
                $compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
                if ($compRow) {
                    $companyId = (int)$compRow['id'];
                } else {
                    $seqStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM companies WHERE organization_id = ?");
                    $seqStmt->execute([$organizationId]);
                    $nextCompId = (int)$seqStmt->fetchColumn();
                    $compCode = 'COMP-' . str_pad($nextCompId, 6, '0', STR_PAD_LEFT);

                    $insComp = $pdo->prepare("
                        INSERT INTO companies (
                            organization_id, company_code, name, is_active, owner_id, created_by, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, 1, ?, ?, NOW(), NOW()
                        )
                    ");
                    $insComp->execute([$organizationId, $compCode, $companyName, $assignedTo, $currentUserId]);
                    $companyId = (int)$pdo->lastInsertId();
                }
            }

            // 2. Check if Contact already exists for this lead or matching email in organization
            $contactStmt = $pdo->prepare("
                SELECT id, contact_code FROM contacts 
                WHERE organization_id = ? AND (lead_id = ? OR (email = ? AND email != '' AND email IS NOT NULL))
                LIMIT 1
            ");
            $contactStmt->execute([$organizationId, $leadId, $email]);
            $existingContact = $contactStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingContact) {
                $contactId = (int)$existingContact['id'];
                $contactCode = $existingContact['contact_code'];
                $pdo->prepare("UPDATE contacts SET lead_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
                    ->execute([$leadId, $contactId, $organizationId]);
            } else {
                $insContact = $pdo->prepare("
                    INSERT INTO contacts (
                        organization_id, first_name, last_name, name, company_id, company_name, job_title,
                        email, phone, clean_phone, whatsapp, preferred_channel, relationship, owner_id,
                        source, location, lead_id, is_active, created_by, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, 'Email & Calls', 'Prospect', ?,
                        ?, ?, ?, 1, ?, NOW(), NOW()
                    )
                ");
                $insContact->execute([
                    $organizationId,
                    $firstName !== '' ? $firstName : null,
                    $lastName !== '' ? $lastName : null,
                    $leadName,
                    $companyId,
                    $companyName !== '' ? $companyName : null,
                    $jobTitle !== '' ? $jobTitle : null,
                    $email !== '' ? $email : null,
                    $phone !== '' ? $phone : null,
                    $cleanPhone !== '' ? $cleanPhone : null,
                    $whatsapp !== '' ? $whatsapp : null,
                    $assignedTo,
                    $lead['source'] ?: 'Website',
                    $location !== '' ? $location : null,
                    $leadId,
                    $currentUserId
                ]);
                $contactId = (int)$pdo->lastInsertId();
                $contactCode = 'CNT-' . str_pad($contactId, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE contacts SET contact_code = ? WHERE id = ?")->execute([$contactCode, $contactId]);

                if ($companyId > 0) {
                    $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (?, ?, ?)")
                        ->execute([$organizationId, $contactId, $companyId]);
                }
            }

            // 3. Check if deal already created for this lead (Preserves Convert to Deal)
            $dealCheck = $pdo->prepare("SELECT id, deal_code, name, stage, value, status, close_date FROM deals WHERE organization_id = ? AND lead_id = ? LIMIT 1");
            $dealCheck->execute([$organizationId, $leadId]);
            $deal = $dealCheck->fetch(PDO::FETCH_ASSOC);

            if (!$deal) {
                $dealCode = 'DL-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $dealTitle = ($companyName ?: $leadName) . " - Sales Pipeline";
                $dIns = $pdo->prepare("
                    INSERT INTO deals (
                        organization_id, lead_id, deal_code, name, company, stage, 
                        value, status, assigned_to, close_date, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, 'Prospect', 
                        ?, 'open', ?, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), NOW(), NOW()
                    )
                ");
                $dIns->execute([
                    $organizationId, $leadId, $dealCode, $dealTitle, $companyName ?: $leadName,
                    $dealValue, $assignedTo
                ]);
                $dealId = (int)$pdo->lastInsertId();
                $finalDealCode = 'DL-' . str_pad($dealId, 4, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE deals SET deal_code = ? WHERE id = ?")->execute([$finalDealCode, $dealId]);

                $dealData = [
                    'id' => (string)$dealId,
                    'name' => $dealTitle,
                    'value' => $dealValue,
                    'stage' => 'Prospect',
                    'status' => 'open',
                    'closeDate' => date('M j, Y', strtotime('+30 days')),
                    'assignee' => $currentUser['name']
                ];
            } else {
                $dealId = (int)$deal['id'];
                $dealData = [
                    'id' => (string)$deal['id'],
                    'name' => $deal['name'],
                    'value' => (float)$deal['value'],
                    'stage' => $deal['stage'],
                    'status' => $deal['status'],
                    'closeDate' => $deal['close_date'] ? date('M j, Y', strtotime($deal['close_date'])) : date('M j, Y', strtotime('+30 days')),
                    'assignee' => $currentUser['name']
                ];
            }

            // 4. Update lead status to Qualified
            $pdo->prepare("UPDATE leads SET status = 'Qualified', updated_at = NOW() WHERE id = ? AND organization_id = ?")
                ->execute([$leadId, $organizationId]);

            // 5. Log activity
            log_lead_activity(
                $pdo, $organizationId, $leadId, $assignedTo,
                'converted', 'Lead Converted',
                "Lead converted to Contact #{$contactCode} and Sales Deal #{$dealId} by {$currentUser['name']}.",
                $currentUserId
            );

            try {
                $cActStmt = $pdo->prepare("
                    INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
                    VALUES (?, ?, 'contact_created', ?, ?, 'contacts', ?, ?, NOW())
                ");
                $cActStmt->execute([
                    $organizationId, $assignedTo,
                    'Contact created from lead',
                    "Contact created from lead #{$leadId} ({$leadName}) by {$currentUser['name']}.",
                    $contactId, $currentUserId
                ]);
            } catch (Throwable $e) {}

            $pdo->commit();

            leads_json(true, "Lead successfully converted to Contact ({$contactCode}) and Deal #{$dealId}.", [
                'leadId' => $leadId,
                'status' => 'Qualified',
                'contactId' => $contactId,
                'contact' => [
                    'id' => $contactId,
                    'code' => $contactCode,
                    'name' => $leadName,
                    'email' => $email,
                    'phone' => $phone,
                    'company' => $companyName
                ],
                'dealId' => $dealId,
                'deal' => $dealData,
                'activity' => [
                    'id' => 'act_' . time(),
                    'type' => 'converted',
                    'title' => 'Lead Converted',
                    'desc' => "Lead converted to Contact #{$contactCode} and Sales Deal #{$dealId} by {$currentUser['name']}.",
                    'actor' => $currentUser['name'],
                    'time' => 'Just now',
                    'createdAt' => date('M j, Y g:i A')
                ]
            ]);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            leads_json(false, 'Conversion failed: ' . $e->getMessage(), [], 500);
        }
        break;

    // ---------------------------------------------------------------------
    // 13. ARCHIVE LEAD
    // ---------------------------------------------------------------------
    case 'archive_lead':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to archive leads.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? ($_POST['lead_id'] ?? 0));
        if ($leadId <= 0) {
            leads_json(false, 'Invalid lead ID.', [], 400);
        }

        $chk = $pdo->prepare("SELECT id, name, assigned_to FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$leadId, $organizationId]);
        $lead = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $pdo->prepare("UPDATE leads SET is_archived = 1, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$leadId, $organizationId]);

        log_lead_activity(
            $pdo, $organizationId, $leadId, (int)($lead['assigned_to'] ?: $currentUserId),
            'lead_archived', 'Lead Archived',
            "Lead '{$lead['name']}' (#{$leadId}) archived by {$currentUser['name']}.",
            $currentUserId
        );

        leads_json(true, "Lead '{$lead['name']}' archived successfully.", [
            'leadId' => $leadId,
            'isArchived' => true
        ]);
        break;

    // ---------------------------------------------------------------------
    // 14. UNARCHIVE LEAD
    // ---------------------------------------------------------------------
    case 'unarchive_lead':
        if (!hasPermission('leads', 'edit')) {
            leads_json(false, 'You do not have permission to unarchive leads.', [], 403);
        }

        $leadId = (int)($input['lead_id'] ?? ($_POST['lead_id'] ?? 0));
        if ($leadId <= 0) {
            leads_json(false, 'Invalid lead ID.', [], 400);
        }

        $chk = $pdo->prepare("SELECT id, name, assigned_to FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$leadId, $organizationId]);
        $lead = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            leads_json(false, 'Lead not found or access denied.', [], 404);
        }

        $pdo->prepare("UPDATE leads SET is_archived = 0, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$leadId, $organizationId]);

        log_lead_activity(
            $pdo, $organizationId, $leadId, (int)($lead['assigned_to'] ?: $currentUserId),
            'lead_unarchived', 'Lead Unarchived',
            "Lead '{$lead['name']}' (#{$leadId}) unarchived by {$currentUser['name']}.",
            $currentUserId
        );

        leads_json(true, "Lead '{$lead['name']}' unarchived successfully.", [
            'leadId' => $leadId,
            'isArchived' => false
        ]);
        break;

    // ---------------------------------------------------------------------
    // 15. EXPORT CSV
    // ---------------------------------------------------------------------
    case 'export_csv':
        if (!hasPermission('leads', 'export')) {
            leads_json(false, 'You do not have permission to export leads.', [], 403);
        }

        $archiveStatus = trim($_GET['archive_status'] ?? ($input['archive_status'] ?? 'active'));
        if ($archiveStatus === 'archived') {
            $archiveClause = "l.is_archived = 1";
        } elseif ($archiveStatus === 'all') {
            $archiveClause = "1=1";
        } else {
            $archiveClause = "l.is_archived = 0";
        }

        $exportStmt = $pdo->prepare("
            SELECT l.lead_code, l.first_name, l.last_name, l.email, l.phone, l.whatsapp,
                   l.company, l.job_title, l.location, l.status, l.score, l.value as deal_value, l.source,
                   u.name as assignee_name, l.created_at
            FROM leads l
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE l.organization_id = ? AND {$archiveClause}
            ORDER BY l.id DESC
        ");
        $exportStmt->execute([$organizationId]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nexflow_leads_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // Add BOM for Excel UTF-8 recognition
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, [
            'Lead Code', 'Full Name', 'Email', 'Phone', 'WhatsApp', 
            'Company', 'Job Title', 'Location', 'Status', 'Score', 
            'Deal Value ($)', 'Source', 'Assignee', 'Created At'
        ]);

        while ($r = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            fputcsv($out, [
                $r['lead_code'] ?? '',
                $name,
                $r['email'] ?? '',
                $r['phone'] ?? '',
                $r['whatsapp'] ?? '',
                $r['company'] ?? '',
                $r['job_title'] ?? '',
                $r['location'] ?? '',
                $r['status'] ?? 'New',
                $r['score'] ?? 50,
                $r['deal_value'] ?? 0,
                $r['source'] ?? '',
                $r['assignee_name'] ?? 'Unassigned',
                date('Y-m-d H:i', strtotime($r['created_at']))
            ]);
        }

        fclose($out);
        exit;

    default:
        leads_json(false, "Unknown action '{$action}'.", [], 400);
        break;
}
