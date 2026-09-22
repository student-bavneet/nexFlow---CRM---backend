<?php
/**
 * NexFlow CRM — Tasks Management API Controller
 * Multi-tenant backend supporting CRUD, KPIs, status transitions,
 * foreign key relationships, notes, activities, and bulk actions.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// JSON Response Helper
function task_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
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
    task_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    task_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Guard
function require_tasks_perm(string $action = 'view'): void {
    global $currentUser;
    $uid = $currentUser['id'] ?? ($_SESSION['user_id'] ?? 1);
    if ($uid && is_super_admin($uid)) {
        return;
    }
    if (function_exists('hasPermission')) {
        if (!hasPermission('tasks', $action) && !hasPermission('tasks')) {
            task_json(false, "Forbidden: Missing 'tasks' permission.", [], 403);
        }
    }
}

$pdo = nexflow_db();

// Helper: Format initials and color
function get_assignee_meta(?string $name, ?int $userId): array {
    if (empty($name)) {
        return ['initials' => '??', 'color' => '#64748B'];
    }
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        if (!empty($p)) {
            $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        }
    }
    if (mb_strlen($initials) > 2) {
        $initials = mb_substr($initials, 0, 2);
    }
    $colors = ['#2563EB', '#7C3AED', '#059669', '#D97706', '#DC2626', '#0891B2', '#4F46E5', '#EA580C'];
    $idx = ($userId ?? crc32($name)) % count($colors);
    return ['initials' => $initials ?: '?', 'color' => $colors[abs($idx)]];
}

// Helper: Determine task group ('Overdue', 'Today', 'Upcoming', 'Completed')
function calculate_task_group(string $status, ?string $dueDate): string {
    if ($status === 'completed') {
        return 'Completed';
    }
    if (empty($dueDate) || $dueDate === '0000-00-00 00:00:00') {
        return 'Upcoming';
    }
    $dueTs = strtotime($dueDate);
    $todayStart = strtotime(date('Y-m-d 00:00:00'));
    $todayEnd   = strtotime(date('Y-m-d 23:59:59'));

    if ($dueTs < $todayStart) {
        return 'Overdue';
    } elseif ($dueTs <= $todayEnd) {
        return 'Today';
    } else {
        return 'Upcoming';
    }
}

// Helper: Format task item for JSON
function format_task_item(array $row): array {
    $statusMap = [
        'pending'     => 'Not Started',
        'in_progress' => 'In Progress',
        'waiting'     => 'Waiting',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled'
    ];
    $displayStatus = $statusMap[$row['status']] ?? ucfirst($row['status']);

    $priorityMap = [
        'urgent' => 'Urgent',
        'high'   => 'High',
        'medium' => 'Medium',
        'low'    => 'Low'
    ];
    $displayPriority = $priorityMap[$row['priority']] ?? ucfirst($row['priority']);

    $assigneeName = $row['assignee_name'] ?? $row['assignee_display_name'] ?? 'Unassigned';
    $meta = get_assignee_meta($assigneeName, !empty($row['assigned_to']) ? (int)$row['assigned_to'] : null);
    $group = calculate_task_group($row['status'], $row['due_date']);

    $formattedDueDate = '';
    $rawDueDate = '';
    $rawDueTime = '';
    if (!empty($row['due_date']) && $row['due_date'] !== '0000-00-00 00:00:00') {
        $ts = strtotime($row['due_date']);
        $rawDueDate = date('Y-m-d', $ts);
        $rawDueTime = date('H:i', $ts);
        $isToday = (date('Y-m-d', $ts) === date('Y-m-d'));
        $timeStr = date('g:i A', $ts);
        if ($isToday) {
            $formattedDueDate = 'Today, ' . $timeStr;
        } else {
            $formattedDueDate = date('M j, Y', $ts) . ', ' . $timeStr;
        }
    }

    $typeIcons = [
        'Call'         => '📞',
        'Email'        => '✉️',
        'WhatsApp'     => '💬',
        'Meeting'      => '📅',
        'Follow-up'    => '🔔',
        'Proposal'     => '📝',
        'General Task' => '📋'
    ];
    $taskType = !empty($row['task_type']) ? $row['task_type'] : 'General Task';
    $typeIcon = $typeIcons[$taskType] ?? '📋';

    return [
        'id'               => (int)$row['id'],
        'task_code'        => sprintf('TASK-%03d', (int)$row['id']),
        'title'            => $row['title'],
        'type'             => $taskType,
        'typeIcon'         => $typeIcon,
        'priority'         => $displayPriority,
        'priority_raw'     => $row['priority'],
        'status'           => $displayStatus,
        'status_raw'       => $row['status'],
        'deal'             => $row['deal_name'] ?? '',
        'deal_id'          => !empty($row['effective_deal_id']) ? (int)$row['effective_deal_id'] : (!empty($row['deal_id']) ? (int)$row['deal_id'] : null),
        'contract'         => $row['deal_name'] ?? '',
        'company'          => $row['company_name'] ?? '',
        'company_id'       => !empty($row['effective_company_id']) ? (int)$row['effective_company_id'] : (!empty($row['company_id']) ? (int)$row['company_id'] : null),
        'contact'          => $row['contact_name'] ?? '',
        'contact_id'       => !empty($row['effective_contact_id']) ? (int)$row['effective_contact_id'] : (!empty($row['contact_id']) ? (int)$row['contact_id'] : null),
        'contact_email'    => $row['contact_email'] ?? '',
        'contact_phone'    => $row['contact_phone'] ?? '',
        'related_lead'     => $row['lead_name'] ?? '',
        'related_lead_id'  => !empty($row['lead_id']) ? (int)$row['lead_id'] : null,
        'related_project'  => $row['project_name'] ?? '',
        'related_project_id'=> !empty($row['project_id']) ? (int)$row['project_id'] : null,
        'related_type'     => $row['related_type'] ?? null,
        'related_id'       => !empty($row['related_id']) ? (int)$row['related_id'] : null,
        'dueDate'          => $formattedDueDate,
        'raw_due_date'     => $rawDueDate,
        'raw_due_time'     => $rawDueTime,
        'due_date_iso'     => $row['due_date'] ?? null,
        'group'            => $group,
        'completed'        => ($row['status'] === 'completed'),
        'done'             => ($row['status'] === 'completed'),
        'clientVisible'    => (bool)($row['client_visible'] ?? 0),
        'assignee'         => $assigneeName,
        'assigned_to'      => !empty($row['assigned_to']) ? (int)$row['assigned_to'] : null,
        'assigneeInitials' => $meta['initials'],
        'assigneeColor'    => $meta['color'],
        'assigneePhoto'    => $row['assignee_photo'] ?? null,
        'description'      => $row['description'] ?? '',
        'created_at'       => $row['created_at'] ?? '',
        'updated_at'       => $row['updated_at'] ?? ''
    ];
}

// Determine Request Method & Action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// If raw JSON body provided
$inputData = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
        if (empty($action) && isset($inputData['action'])) {
            $action = $inputData['action'];
        }
    }
}

// Fallback action based on method
if (empty($action)) {
    $action = ($method === 'POST') ? 'create' : 'list';
}

// -------------------------------------------------------------
// ROUTER
// -------------------------------------------------------------
try {
    switch ($action) {

        // =========================================================
        // ACTION: SUMMARY (KPI Counters from exact MySQL data)
        // =========================================================
        case 'summary':
            require_tasks_perm('view');

            // Total tasks in tenant
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = :org_id");
            $stmt->execute([':org_id' => $organizationId]);
            $totalTasks = (int)$stmt->fetchColumn();

            // My tasks (assigned to logged-in user)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = :org_id AND assigned_to = :uid");
            $stmt->execute([':org_id' => $organizationId, ':uid' => $currentUserId]);
            $myTasks = (int)$stmt->fetchColumn();

            // Due Today (DATE(due_date) = CURRENT_DATE)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = :org_id AND DATE(due_date) = CURRENT_DATE()");
            $stmt->execute([':org_id' => $organizationId]);
            $dueToday = (int)$stmt->fetchColumn();

            // Overdue (due_date < NOW() AND status != 'completed')
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = :org_id AND due_date < NOW() AND status != 'completed'");
            $stmt->execute([':org_id' => $organizationId]);
            $overdue = (int)$stmt->fetchColumn();

            // Upcoming (DATE(due_date) > CURRENT_DATE() AND status != 'completed')
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = :org_id AND DATE(due_date) > CURRENT_DATE() AND status != 'completed'");
            $stmt->execute([':org_id' => $organizationId]);
            $upcoming = (int)$stmt->fetchColumn();

            // Completed (status = 'completed')
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = :org_id AND status = 'completed'");
            $stmt->execute([':org_id' => $organizationId]);
            $completed = (int)$stmt->fetchColumn();

            task_json(true, 'Task KPI summary loaded successfully.', [
                'my_tasks'    => $myTasks,
                'total_tasks' => $totalTasks,
                'due_today'   => $dueToday,
                'overdue'     => $overdue,
                'upcoming'    => $upcoming,
                'completed'   => $completed,
            ]);
            break;

        // =========================================================
        // ACTION: REFERENCE OPTIONS (Active Users, Companies, Contacts, Deals)
        // =========================================================
        case 'reference_options':
            require_tasks_perm('view');

            // 1. Users
            $stmt = $pdo->prepare("SELECT id, name, display_name, email, photo_path FROM users WHERE organization_id = :org_id AND status = 'active' ORDER BY name ASC");
            $stmt->execute([':org_id' => $organizationId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Companies
            $stmt = $pdo->prepare("SELECT id, name, company_code FROM companies WHERE organization_id = :org_id AND is_active = 1 ORDER BY name ASC");
            $stmt->execute([':org_id' => $organizationId]);
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Contacts
            $stmt = $pdo->prepare("SELECT id, name, email, phone, company_id FROM contacts WHERE organization_id = :org_id AND is_active = 1 ORDER BY name ASC");
            $stmt->execute([':org_id' => $organizationId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Deals
            $stmt = $pdo->prepare("SELECT id, name, deal_code, contact_id, company FROM deals WHERE organization_id = :org_id ORDER BY name ASC");
            $stmt->execute([':org_id' => $organizationId]);
            $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            task_json(true, 'Reference options loaded.', [
                'users'     => $users,
                'companies' => $companies,
                'contacts'  => $contacts,
                'deals'     => $deals,
            ]);
            break;

        // =========================================================
        // ACTION: LIST (Tasks with joins, filtering, and sorting)
        // =========================================================
        case 'list':
            require_tasks_perm('view');

            $summaryFilter = $_GET['summary'] ?? 'All';
            $searchQuery   = trim($_GET['search'] ?? $_GET['q'] ?? '');
            $typeFilter     = trim($_GET['type'] ?? 'All');
            $priorityFilter = trim($_GET['priority'] ?? 'All');
            $statusFilter   = trim($_GET['status'] ?? 'All');
            $assigneeFilter = trim($_GET['assignee'] ?? 'All');
            $companyFilter  = trim($_GET['company'] ?? '');
            $contactFilter  = trim($_GET['contact'] ?? '');
            $sortBy         = $_GET['sort'] ?? 'due-asc';

            $sql = "
                SELECT 
                    t.*,
                    u.name AS assignee_name,
                    u.display_name AS assignee_display_name,
                    u.photo_path AS assignee_photo,
                    COALESCE(c.name, c_rel.name, d.company, d_rel.company, l.company, pr.client_name) AS company_name,
                    COALESCE(t.company_id, c_rel.id, (CASE WHEN t.related_type = 'companies' THEN t.related_id END)) AS effective_company_id,
                    COALESCE(ct.name, ct_rel.name, d.contact_name, d_rel.contact_name, l.name) AS contact_name,
                    COALESCE(t.contact_id, ct_rel.id, (CASE WHEN t.related_type = 'contacts' THEN t.related_id END)) AS effective_contact_id,
                    COALESCE(ct.email, ct_rel.email, l.email) AS contact_email,
                    COALESCE(ct.phone, ct_rel.phone, l.phone) AS contact_phone,
                    COALESCE(d.name, d_rel.name) AS deal_name,
                    COALESCE(t.deal_id, d_rel.id, (CASE WHEN t.related_type = 'deals' THEN t.related_id END)) AS effective_deal_id,
                    COALESCE(d.deal_code, d_rel.deal_code) AS deal_code,
                    l.id AS lead_id,
                    l.lead_code,
                    l.name AS lead_name,
                    pr.id AS project_id,
                    pr.project_code,
                    pr.name AS project_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN companies c ON c.id = t.company_id
                LEFT JOIN companies c_rel ON (t.related_type = 'companies' AND c_rel.id = t.related_id)
                LEFT JOIN contacts ct ON ct.id = t.contact_id
                LEFT JOIN contacts ct_rel ON (t.related_type = 'contacts' AND ct_rel.id = t.related_id)
                LEFT JOIN deals d ON d.id = t.deal_id
                LEFT JOIN deals d_rel ON (t.related_type = 'deals' AND d_rel.id = t.related_id)
                LEFT JOIN leads l ON (t.related_type = 'leads' AND l.id = t.related_id)
                LEFT JOIN projects pr ON (t.related_type = 'projects' AND pr.id = t.related_id)
                WHERE t.organization_id = :org_id
            ";
            $params = [':org_id' => $organizationId];

            // Summary card filter
            if ($summaryFilter === 'My') {
                $sql .= " AND t.assigned_to = :current_uid";
                $params[':current_uid'] = $currentUserId;
            } elseif ($summaryFilter === 'Today') {
                $sql .= " AND DATE(t.due_date) = CURRENT_DATE()";
            } elseif ($summaryFilter === 'Overdue') {
                $sql .= " AND t.due_date < NOW() AND t.status != 'completed'";
            } elseif ($summaryFilter === 'Upcoming') {
                $sql .= " AND DATE(t.due_date) > CURRENT_DATE() AND t.status != 'completed'";
            } elseif ($summaryFilter === 'Completed') {
                $sql .= " AND t.status = 'completed'";
            }

            // Search query
            if (!empty($searchQuery)) {
                $sql .= " AND (
                    t.title LIKE :search 
                    OR t.description LIKE :search 
                    OR c.name LIKE :search 
                    OR ct.name LIKE :search 
                    OR d.name LIKE :search 
                    OR u.name LIKE :search
                )";
                $params[':search'] = '%' . $searchQuery . '%';
            }

            // Type filter
            if ($typeFilter !== 'All' && !empty($typeFilter)) {
                $sql .= " AND t.task_type = :task_type";
                $params[':task_type'] = $typeFilter;
            }

            // Priority filter
            if ($priorityFilter !== 'All' && !empty($priorityFilter)) {
                $sql .= " AND t.priority = :priority";
                $params[':priority'] = strtolower($priorityFilter);
            }

            // Status filter
            if ($statusFilter !== 'All' && !empty($statusFilter)) {
                $statusDbMap = [
                    'Not Started'    => 'pending',
                    'Pending Action' => 'pending',
                    'In Progress'    => 'in_progress',
                    'Waiting'        => 'waiting',
                    'Completed'      => 'completed'
                ];
                $dbStat = $statusDbMap[$statusFilter] ?? strtolower(str_replace(' ', '_', $statusFilter));
                $sql .= " AND t.status = :status";
                $params[':status'] = $dbStat;
            }

            // Assignee filter
            if ($assigneeFilter !== 'All' && !empty($assigneeFilter)) {
                if (is_numeric($assigneeFilter)) {
                    $sql .= " AND t.assigned_to = :assignee_id";
                    $params[':assignee_id'] = (int)$assigneeFilter;
                } else {
                    $sql .= " AND (u.name LIKE :assignee_name OR u.display_name LIKE :assignee_name)";
                    $params[':assignee_name'] = '%' . $assigneeFilter . '%';
                }
            }

            // Company filter
            if (!empty($companyFilter)) {
                if (is_numeric($companyFilter)) {
                    $sql .= " AND t.company_id = :comp_id";
                    $params[':comp_id'] = (int)$companyFilter;
                } else {
                    $sql .= " AND c.name LIKE :comp_name";
                    $params[':comp_name'] = '%' . $companyFilter . '%';
                }
            }

            // Contact filter
            if (!empty($contactFilter)) {
                if (is_numeric($contactFilter)) {
                    $sql .= " AND t.contact_id = :cont_id";
                    $params[':cont_id'] = (int)$contactFilter;
                } else {
                    $sql .= " AND ct.name LIKE :cont_name";
                    $params[':cont_name'] = '%' . $contactFilter . '%';
                }
            }

            // Sorting
            switch ($sortBy) {
                case 'due-desc':
                    $sql .= " ORDER BY t.due_date DESC, t.id DESC";
                    break;
                case 'priority-desc':
                    $sql .= " ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.due_date ASC";
                    break;
                case 'created-desc':
                    $sql .= " ORDER BY t.id DESC";
                    break;
                case 'alpha-asc':
                    $sql .= " ORDER BY t.title ASC";
                    break;
                case 'due-asc':
                default:
                    $sql .= " ORDER BY (t.due_date IS NULL), t.due_date ASC, t.id DESC";
                    break;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tasks = [];
            foreach ($rawRows as $r) {
                $tasks[] = format_task_item($r);
            }

            task_json(true, 'Tasks loaded successfully.', [
                'tasks' => $tasks,
                'total' => count($tasks),
            ]);
            break;

        // =========================================================
        // ACTION: GET (Single task with details, notes, activities)
        // =========================================================
        case 'get':
            require_tasks_perm('view');
            $id = (int)($_GET['id'] ?? $inputData['id'] ?? 0);
            if ($id <= 0) {
                task_json(false, 'Invalid task ID.', [], 400);
            }

            $stmt = $pdo->prepare("
                SELECT 
                    t.*,
                    u.name AS assignee_name,
                    u.display_name AS assignee_display_name,
                    u.photo_path AS assignee_photo,
                    COALESCE(c.name, c_rel.name, d.company, d_rel.company, l.company, pr.client_name) AS company_name,
                    COALESCE(t.company_id, c_rel.id, (CASE WHEN t.related_type = 'companies' THEN t.related_id END)) AS effective_company_id,
                    COALESCE(ct.name, ct_rel.name, d.contact_name, d_rel.contact_name, l.name) AS contact_name,
                    COALESCE(t.contact_id, ct_rel.id, (CASE WHEN t.related_type = 'contacts' THEN t.related_id END)) AS effective_contact_id,
                    COALESCE(ct.email, ct_rel.email, l.email) AS contact_email,
                    COALESCE(ct.phone, ct_rel.phone, l.phone) AS contact_phone,
                    COALESCE(d.name, d_rel.name) AS deal_name,
                    COALESCE(t.deal_id, d_rel.id, (CASE WHEN t.related_type = 'deals' THEN t.related_id END)) AS effective_deal_id,
                    COALESCE(d.deal_code, d_rel.deal_code) AS deal_code,
                    l.id AS lead_id,
                    l.lead_code,
                    l.name AS lead_name,
                    pr.id AS project_id,
                    pr.project_code,
                    pr.name AS project_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN companies c ON c.id = t.company_id
                LEFT JOIN companies c_rel ON (t.related_type = 'companies' AND c_rel.id = t.related_id)
                LEFT JOIN contacts ct ON ct.id = t.contact_id
                LEFT JOIN contacts ct_rel ON (t.related_type = 'contacts' AND ct_rel.id = t.related_id)
                LEFT JOIN deals d ON d.id = t.deal_id
                LEFT JOIN deals d_rel ON (t.related_type = 'deals' AND d_rel.id = t.related_id)
                LEFT JOIN leads l ON (t.related_type = 'leads' AND l.id = t.related_id)
                LEFT JOIN projects pr ON (t.related_type = 'projects' AND pr.id = t.related_id)
                WHERE t.id = :id AND t.organization_id = :org_id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                task_json(false, 'Task not found.', [], 404);
            }

            $task = format_task_item($row);

            // Fetch notes from team_notes
            $notesStmt = $pdo->prepare("
                SELECT n.*, u.name as author_name, u.photo_path as author_photo
                FROM team_notes n
                LEFT JOIN users u ON u.id = n.created_by
                WHERE n.organization_id = :org_id AND n.related_type = 'tasks' AND n.related_id = :id
                ORDER BY n.created_at DESC
            ");
            $notesStmt->execute([':org_id' => $organizationId, ':id' => $id]);
            $notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch activities from team_activities
            $actStmt = $pdo->prepare("
                SELECT a.*, u.name as user_name
                FROM team_activities a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE a.organization_id = :org_id AND a.related_entity = 'tasks' AND a.related_entity_id = :id
                ORDER BY a.created_at DESC
            ");
            $actStmt->execute([':org_id' => $organizationId, ':id' => $id]);
            $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);

            task_json(true, 'Task details loaded.', [
                'task'       => $task,
                'notes'      => $notes,
                'activities' => $activities,
            ]);
            break;

        // =========================================================
        // ACTION: CREATE (New task with relational links)
        // =========================================================
        case 'create':
            require_tasks_perm('create');

            $data = !empty($inputData) ? $inputData : $_POST;

            $title = trim($data['title'] ?? '');
            if (empty($title)) {
                task_json(false, 'Task title is required.', [], 422);
            }

            $taskType    = trim($data['type'] ?? $data['task_type'] ?? 'General Task');
            $priorityRaw = strtolower(trim($data['priority'] ?? 'medium'));
            if (!in_array($priorityRaw, ['urgent', 'high', 'medium', 'low'])) {
                $priorityRaw = 'medium';
            }

            $statusRaw = strtolower(trim($data['status'] ?? 'pending'));
            $statusMap = [
                'not started'    => 'pending',
                'pending action' => 'pending',
                'in progress'    => 'in_progress',
                'waiting'        => 'waiting',
                'completed'      => 'completed',
                'cancelled'      => 'cancelled'
            ];
            $statusRaw = $statusMap[$statusRaw] ?? (in_array($statusRaw, ['pending','in_progress','waiting','completed','cancelled']) ? $statusRaw : 'pending');

            $description   = trim($data['description'] ?? '');
            $assignedTo    = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
            $companyId     = !empty($data['company_id']) ? (int)$data['company_id'] : null;
            $contactId     = !empty($data['contact_id']) ? (int)$data['contact_id'] : null;
            $dealId        = !empty($data['deal_id']) ? (int)$data['deal_id'] : null;
            $clientVisible = !empty($data['client_visible']) ? 1 : 0;

            // Handle date and time
            $dueDateStr = trim($data['due_date'] ?? '');
            $dueTimeStr = trim($data['due_time'] ?? '');
            $dueDateTime = null;

            if (!empty($dueDateStr)) {
                if (empty($dueTimeStr)) {
                    $dueDateTime = date('Y-m-d 09:00:00', strtotime($dueDateStr));
                } else {
                    $dueDateTime = date('Y-m-d H:i:s', strtotime($dueDateStr . ' ' . $dueTimeStr));
                }
            }

            // Fallback default assignee to current user if none selected
            if (empty($assignedTo)) {
                $assignedTo = $currentUserId;
            }

            // Polymorphic fallback
            $relatedType = null;
            $relatedId   = null;
            if ($dealId) {
                $relatedType = 'deals';
                $relatedId   = $dealId;
            } elseif ($companyId) {
                $relatedType = 'companies';
                $relatedId   = $companyId;
            } elseif ($contactId) {
                $relatedType = 'contacts';
                $relatedId   = $contactId;
            }

            $insStmt = $pdo->prepare("
                INSERT INTO tasks (
                    organization_id, title, task_type, description,
                    status, priority, due_date, assigned_to,
                    company_id, contact_id, deal_id, client_visible,
                    related_type, related_id, created_at, updated_at
                ) VALUES (
                    :org_id, :title, :task_type, :description,
                    :status, :priority, :due_date, :assigned_to,
                    :company_id, :contact_id, :deal_id, :client_visible,
                    :related_type, :related_id, NOW(), NOW()
                )
            ");
            $insStmt->execute([
                ':org_id'         => $organizationId,
                ':title'          => $title,
                ':task_type'      => $taskType,
                ':description'    => $description,
                ':status'         => $statusRaw,
                ':priority'       => $priorityRaw,
                ':due_date'       => $dueDateTime,
                ':assigned_to'    => $assignedTo,
                ':company_id'     => $companyId,
                ':contact_id'     => $contactId,
                ':deal_id'        => $dealId,
                ':client_visible' => $clientVisible,
                ':related_type'   => $relatedType,
                ':related_id'     => $relatedId,
            ]);

            $newTaskId = (int)$pdo->lastInsertId();

            // Log activity
            try {
                $actStmt = $pdo->prepare("
                    INSERT INTO team_activities (
                        organization_id, user_id, activity_type,
                        title, description, related_entity, related_entity_id,
                        created_by, created_at
                    ) VALUES (
                        :org_id, :uid, 'task_created',
                        :title, :desc, 'tasks', :tid,
                        :uid, NOW()
                    )
                ");
                $actStmt->execute([
                    ':org_id' => $organizationId,
                    ':uid'    => $currentUserId,
                    ':title'  => "Created task '{$title}'",
                    ':desc'   => "Task #{$newTaskId} created.",
                    ':tid'    => $newTaskId,
                ]);
            } catch (Throwable $e) {
                // Ignore activity log failure
            }

            // Fetch newly created task
            $fetchStmt = $pdo->prepare("
                SELECT t.*, u.name AS assignee_name, u.display_name AS assignee_display_name,
                       c.name AS company_name, ct.name AS contact_name, d.name AS deal_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN companies c ON c.id = t.company_id
                LEFT JOIN contacts ct ON ct.id = t.contact_id
                LEFT JOIN deals d ON d.id = t.deal_id
                WHERE t.id = :id AND t.organization_id = :org_id
            ");
            $fetchStmt->execute([':id' => $newTaskId, ':org_id' => $organizationId]);
            $createdTask = format_task_item($fetchStmt->fetch(PDO::FETCH_ASSOC));

            task_json(true, "Task 'TASK-{$newTaskId}' created successfully!", [
                'task' => $createdTask
            ]);
            break;

        // =========================================================
        // ACTION: UPDATE (Update existing task)
        // =========================================================
        case 'update':
            require_tasks_perm('edit');

            $data = !empty($inputData) ? $inputData : $_POST;

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                task_json(false, 'Invalid task ID for update.', [], 400);
            }

            // Ensure task exists and belongs to organization
            $chkStmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id AND organization_id = :org_id LIMIT 1");
            $chkStmt->execute([':id' => $id, ':org_id' => $organizationId]);
            $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                task_json(false, 'Task not found or unauthorized.', [], 404);
            }

            $title = trim($data['title'] ?? $existing['title']);
            if (empty($title)) {
                task_json(false, 'Task title cannot be empty.', [], 422);
            }

            $taskType    = trim($data['type'] ?? $data['task_type'] ?? $existing['task_type']);
            $priorityRaw = strtolower(trim($data['priority'] ?? $existing['priority']));
            if (!in_array($priorityRaw, ['urgent', 'high', 'medium', 'low'])) {
                $priorityRaw = $existing['priority'];
            }

            $statusRaw = strtolower(trim($data['status'] ?? $existing['status']));
            $statusMap = [
                'not started'    => 'pending',
                'pending action' => 'pending',
                'in progress'    => 'in_progress',
                'waiting'        => 'waiting',
                'completed'      => 'completed',
                'cancelled'      => 'cancelled'
            ];
            $statusRaw = $statusMap[$statusRaw] ?? (in_array($statusRaw, ['pending','in_progress','waiting','completed','cancelled']) ? $statusRaw : $existing['status']);

            $description   = isset($data['description']) ? trim($data['description']) : $existing['description'];
            $assignedTo    = array_key_exists('assigned_to', $data) ? (!empty($data['assigned_to']) ? (int)$data['assigned_to'] : null) : $existing['assigned_to'];
            $companyId     = array_key_exists('company_id', $data) ? (!empty($data['company_id']) ? (int)$data['company_id'] : null) : $existing['company_id'];
            $contactId     = array_key_exists('contact_id', $data) ? (!empty($data['contact_id']) ? (int)$data['contact_id'] : null) : $existing['contact_id'];
            $dealId        = array_key_exists('deal_id', $data) ? (!empty($data['deal_id']) ? (int)$data['deal_id'] : null) : $existing['deal_id'];
            $clientVisible = array_key_exists('client_visible', $data) ? (!empty($data['client_visible']) ? 1 : 0) : (int)$existing['client_visible'];

            // Due Date & Time
            $dueDateStr = trim($data['due_date'] ?? '');
            $dueTimeStr = trim($data['due_time'] ?? '');
            $dueDateTime = $existing['due_date'];

            if (!empty($dueDateStr)) {
                if (empty($dueTimeStr)) {
                    $dueDateTime = date('Y-m-d 09:00:00', strtotime($dueDateStr));
                } else {
                    $dueDateTime = date('Y-m-d H:i:s', strtotime($dueDateStr . ' ' . $dueTimeStr));
                }
            }

            // Update polymorphic
            $relatedType = $existing['related_type'];
            $relatedId   = $existing['related_id'];
            if ($dealId) {
                $relatedType = 'deals';
                $relatedId   = $dealId;
            } elseif ($companyId) {
                $relatedType = 'companies';
                $relatedId   = $companyId;
            } elseif ($contactId) {
                $relatedType = 'contacts';
                $relatedId   = $contactId;
            }

            $updStmt = $pdo->prepare("
                UPDATE tasks SET
                    title = :title,
                    task_type = :task_type,
                    description = :description,
                    status = :status,
                    priority = :priority,
                    due_date = :due_date,
                    assigned_to = :assigned_to,
                    company_id = :company_id,
                    contact_id = :contact_id,
                    deal_id = :deal_id,
                    client_visible = :client_visible,
                    related_type = :related_type,
                    related_id = :related_id,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id
            ");
            $updStmt->execute([
                ':title'          => $title,
                ':task_type'      => $taskType,
                ':description'    => $description,
                ':status'         => $statusRaw,
                ':priority'       => $priorityRaw,
                ':due_date'       => $dueDateTime,
                ':assigned_to'    => $assignedTo,
                ':company_id'     => $companyId,
                ':contact_id'     => $contactId,
                ':deal_id'        => $dealId,
                ':client_visible' => $clientVisible,
                ':related_type'   => $relatedType,
                ':related_id'     => $relatedId,
                ':id'             => $id,
                ':org_id'         => $organizationId,
            ]);

            // Log activity
            try {
                $actStmt = $pdo->prepare("
                    INSERT INTO team_activities (
                        organization_id, user_id, activity_type,
                        title, description, related_entity, related_entity_id,
                        created_by, created_at
                    ) VALUES (
                        :org_id, :uid, 'task_updated',
                        :title, :desc, 'tasks', :tid,
                        :uid, NOW()
                    )
                ");
                $actStmt->execute([
                    ':org_id' => $organizationId,
                    ':uid'    => $currentUserId,
                    ':title'  => "Updated task '{$title}'",
                    ':desc'   => "Task #{$id} details modified.",
                    ':tid'    => $id,
                ]);
            } catch (Throwable $e) {}

            // Fetch updated
            $fetchStmt = $pdo->prepare("
                SELECT t.*, u.name AS assignee_name, u.display_name AS assignee_display_name,
                       c.name AS company_name, ct.name AS contact_name, d.name AS deal_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN companies c ON c.id = t.company_id
                LEFT JOIN contacts ct ON ct.id = t.contact_id
                LEFT JOIN deals d ON d.id = t.deal_id
                WHERE t.id = :id AND t.organization_id = :org_id
            ");
            $fetchStmt->execute([':id' => $id, ':org_id' => $organizationId]);
            $updatedTask = format_task_item($fetchStmt->fetch(PDO::FETCH_ASSOC));

            task_json(true, "Task 'TASK-{$id}' updated successfully.", [
                'task' => $updatedTask
            ]);
            break;

        // =========================================================
        // ACTION: TOGGLE COMPLETE (Quick checkbox toggle)
        // =========================================================
        case 'toggle_complete':
            require_tasks_perm('edit');

            $data = !empty($inputData) ? $inputData : $_POST;
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);

            if ($id <= 0) {
                task_json(false, 'Invalid task ID.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT id, title, status FROM tasks WHERE id = :id AND organization_id = :org_id LIMIT 1");
            $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                task_json(false, 'Task not found.', [], 404);
            }

            $newStatus = ($task['status'] === 'completed') ? 'pending' : 'completed';

            $upd = $pdo->prepare("UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
            $upd->execute([':status' => $newStatus, ':id' => $id, ':org_id' => $organizationId]);

            // Activity
            try {
                $actType = ($newStatus === 'completed') ? 'task_completed' : 'task_reopened';
                $actTitle = ($newStatus === 'completed') ? "Completed task '{$task['title']}'" : "Reopened task '{$task['title']}'";
                $actStmt = $pdo->prepare("
                    INSERT INTO team_activities (
                        organization_id, user_id, activity_type,
                        title, description, related_entity, related_entity_id,
                        created_by, created_at
                    ) VALUES (
                        :org_id, :uid, :act_type,
                        :title, '', 'tasks', :tid,
                        :uid, NOW()
                    )
                ");
                $actStmt->execute([
                    ':org_id'   => $organizationId,
                    ':uid'      => $currentUserId,
                    ':act_type' => $actType,
                    ':title'    => $actTitle,
                    ':tid'      => $id,
                ]);
            } catch (Throwable $e) {}

            task_json(true, ($newStatus === 'completed') ? "Task marked as completed." : "Task marked as pending.", [
                'id'        => $id,
                'status'    => $newStatus,
                'completed' => ($newStatus === 'completed')
            ]);
            break;

        // =========================================================
        // ACTION: CHANGE STATUS (Kanban drag-and-drop or modal status select)
        // =========================================================
        case 'change_status':
            require_tasks_perm('edit');

            $data = !empty($inputData) ? $inputData : $_POST;
            $id = (int)($data['id'] ?? 0);
            $newStatusInput = trim($data['status'] ?? '');

            if ($id <= 0 || empty($newStatusInput)) {
                task_json(false, 'Task ID and new status are required.', [], 400);
            }

            $statusMap = [
                'not started'    => 'pending',
                'pending action' => 'pending',
                'in progress'    => 'in_progress',
                'waiting'        => 'waiting',
                'completed'      => 'completed',
                'cancelled'      => 'cancelled'
            ];
            $dbStatus = $statusMap[strtolower($newStatusInput)] ?? strtolower(str_replace(' ', '_', $newStatusInput));

            if (!in_array($dbStatus, ['pending', 'in_progress', 'waiting', 'completed', 'cancelled'])) {
                task_json(false, 'Invalid status value.', [], 422);
            }

            $upd = $pdo->prepare("UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
            $upd->execute([':status' => $dbStatus, ':id' => $id, ':org_id' => $organizationId]);

            task_json(true, 'Task status updated.', [
                'id'     => $id,
                'status' => $dbStatus
            ]);
            break;

        // =========================================================
        // ACTION: DELETE (Delete single task)
        // =========================================================
        case 'delete':
            require_tasks_perm('delete');

            $data = !empty($inputData) ? $inputData : $_POST;
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);

            if ($id <= 0) {
                task_json(false, 'Invalid task ID.', [], 400);
            }

            // Clean up notes and activities
            $delNotes = $pdo->prepare("DELETE FROM team_notes WHERE organization_id = :org_id AND related_type = 'tasks' AND related_id = :id");
            $delNotes->execute([':org_id' => $organizationId, ':id' => $id]);

            $delAct = $pdo->prepare("DELETE FROM team_activities WHERE organization_id = :org_id AND related_entity = 'tasks' AND related_entity_id = :id");
            $delAct->execute([':org_id' => $organizationId, ':id' => $id]);

            $del = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND organization_id = :org_id");
            $del->execute([':id' => $id, ':org_id' => $organizationId]);

            task_json(true, "Task #{$id} has been deleted.");
            break;

        // =========================================================
        // ACTION: BULK (Mark Complete, Change Status, Reassign, Delete)
        // =========================================================
        case 'bulk':
            $data = !empty($inputData) ? $inputData : $_POST;
            $ids = $data['ids'] ?? [];
            $bulkAction = trim($data['bulk_action'] ?? '');

            if (!is_array($ids) || empty($ids)) {
                task_json(false, 'No task IDs provided for bulk action.', [], 400);
            }

            // Filter valid integer IDs
            $cleanIds = [];
            foreach ($ids as $val) {
                $intVal = (int)$val;
                if ($intVal > 0) $cleanIds[] = $intVal;
            }

            if (empty($cleanIds)) {
                task_json(false, 'No valid task IDs found.', [], 400);
            }

            $inPlaceholders = implode(',', array_fill(0, count($cleanIds), '?'));

            switch ($bulkAction) {
                case 'complete':
                    require_tasks_perm('edit');
                    $sql = "UPDATE tasks SET status = 'completed', updated_at = NOW() WHERE organization_id = ? AND id IN ($inPlaceholders)";
                    $params = array_merge([$organizationId], $cleanIds);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    task_json(true, count($cleanIds) . ' task(s) marked as completed.');
                    break;

                case 'status':
                    require_tasks_perm('edit');
                    $newStatus = trim($data['new_status'] ?? 'pending');
                    $statusMap = [
                        'not started'    => 'pending',
                        'pending action' => 'pending',
                        'in progress'    => 'in_progress',
                        'waiting'        => 'waiting',
                        'completed'      => 'completed'
                    ];
                    $dbStatus = $statusMap[strtolower($newStatus)] ?? 'pending';
                    $sql = "UPDATE tasks SET status = ?, updated_at = NOW() WHERE organization_id = ? AND id IN ($inPlaceholders)";
                    $params = array_merge([$dbStatus, $organizationId], $cleanIds);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    task_json(true, count($cleanIds) . ' task(s) status updated.');
                    break;

                case 'assignee':
                    require_tasks_perm('edit');
                    $assigneeId = !empty($data['new_assignee']) ? (int)$data['new_assignee'] : null;
                    if ($assigneeId === null) {
                        task_json(false, 'New assignee ID is required.', [], 422);
                    }
                    $sql = "UPDATE tasks SET assigned_to = ?, updated_at = NOW() WHERE organization_id = ? AND id IN ($inPlaceholders)";
                    $params = array_merge([$assigneeId, $organizationId], $cleanIds);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    task_json(true, count($cleanIds) . ' task(s) reassigned.');
                    break;

                case 'priority':
                    require_tasks_perm('edit');
                    $newPriority = strtolower(trim($data['new_priority'] ?? 'medium'));
                    if (!in_array($newPriority, ['urgent', 'high', 'medium', 'low'])) {
                        $newPriority = 'medium';
                    }
                    $sql = "UPDATE tasks SET priority = ?, updated_at = NOW() WHERE organization_id = ? AND id IN ($inPlaceholders)";
                    $params = array_merge([$newPriority, $organizationId], $cleanIds);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    task_json(true, count($cleanIds) . ' task(s) priority updated.');
                    break;

                case 'delete':
                    require_tasks_perm('delete');
                    $delNotes = $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'tasks' AND related_id IN ($inPlaceholders)");
                    $delNotes->execute(array_merge([$organizationId], $cleanIds));

                    $delActs = $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'tasks' AND related_entity_id IN ($inPlaceholders)");
                    $delActs->execute(array_merge([$organizationId], $cleanIds));

                    $sql = "DELETE FROM tasks WHERE organization_id = ? AND id IN ($inPlaceholders)";
                    $params = array_merge([$organizationId], $cleanIds);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    task_json(true, count($cleanIds) . ' task(s) deleted.');
                    break;

                default:
                    task_json(false, 'Unsupported bulk action.', [], 400);
            }
            break;

        // =========================================================
        // ACTION: NOTES (List & Add Notes for Task)
        // =========================================================
        case 'notes':
            require_tasks_perm('view');
            $taskId = (int)($_GET['task_id'] ?? 0);
            if ($taskId <= 0) {
                task_json(false, 'Task ID required.', [], 400);
            }

            $stmt = $pdo->prepare("
                SELECT n.*, u.name as author_name, u.photo_path as author_photo
                FROM team_notes n
                LEFT JOIN users u ON u.id = n.created_by
                WHERE n.organization_id = :org_id AND n.related_type = 'tasks' AND n.related_id = :task_id
                ORDER BY n.created_at DESC
            ");
            $stmt->execute([':org_id' => $organizationId, ':task_id' => $taskId]);
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            task_json(true, 'Notes loaded.', ['notes' => $notes]);
            break;

        case 'add_note':
            require_tasks_perm('edit');
            $data = !empty($inputData) ? $inputData : $_POST;
            $taskId = (int)($data['task_id'] ?? 0);
            $content = trim($data['content'] ?? '');

            if ($taskId <= 0 || empty($content)) {
                task_json(false, 'Task ID and note content are required.', [], 422);
            }

            $stmt = $pdo->prepare("
                INSERT INTO team_notes (organization_id, related_type, related_id, created_by, content, created_at, updated_at)
                VALUES (:org_id, 'tasks', :task_id, :uid, :content, NOW(), NOW())
            ");
            $stmt->execute([
                ':org_id'  => $organizationId,
                ':task_id' => $taskId,
                ':uid'     => $currentUserId,
                ':content' => $content
            ]);

            task_json(true, 'Note added successfully.');
            break;

        // =========================================================
        // ACTION: EXPORT CSV
        // =========================================================
        case 'export_csv':
            require_tasks_perm('view');

            $stmt = $pdo->prepare("
                SELECT 
                    t.id,
                    t.title,
                    t.task_type,
                    t.priority,
                    t.status,
                    t.due_date,
                    u.name as assignee,
                    c.name as company,
                    ct.name as contact,
                    d.name as deal,
                    t.client_visible,
                    t.created_at
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN companies c ON c.id = t.company_id
                LEFT JOIN contacts ct ON ct.id = t.contact_id
                LEFT JOIN deals d ON d.id = t.deal_id
                WHERE t.organization_id = :org_id
                ORDER BY t.id DESC
            ");
            $stmt->execute([':org_id' => $organizationId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="tasks_export_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Task Title', 'Type', 'Priority', 'Status', 'Due Date', 'Assigned To', 'Company', 'Contact', 'Deal', 'Client Visible', 'Created At']);

            foreach ($rows as $r) {
                fputcsv($output, [
                    sprintf('TASK-%03d', $r['id']),
                    $r['title'],
                    $r['task_type'],
                    ucfirst($r['priority']),
                    ucfirst(str_replace('_', ' ', $r['status'])),
                    $r['due_date'],
                    $r['assignee'] ?? 'Unassigned',
                    $r['company'] ?? '',
                    $r['contact'] ?? '',
                    $r['deal'] ?? '',
                    $r['client_visible'] ? 'Yes' : 'No',
                    $r['created_at']
                ]);
            }
            fclose($output);
            exit;

        default:
            task_json(false, "Unknown action: '{$action}'", [], 400);
    }
} catch (Throwable $e) {
    task_json(false, 'Server error: ' . $e->getMessage(), [], 500);
}
