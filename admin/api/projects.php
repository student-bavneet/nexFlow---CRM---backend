<?php
/**
 * NexFlow CRM - Projects Module API
 * Multi-tenant, organization-scoped controller for Projects, Tasks, Milestones, Files, and Activities.
 * Strictly 0 mock data, 100% MySQL source of truth.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function projects_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authenticate user
$currentUser = nexflow_current_user();
if (!$currentUser) {
    projects_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId = (int)$currentUser['id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    projects_json(false, 'Database connection failed: ' . $e->getMessage(), [], 500);
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

// Permission checks
$canView   = hasPermission('projects', 'view')   || is_super_admin($currentUserId);
$canCreate = hasPermission('projects', 'create') || is_super_admin($currentUserId);
$canEdit   = hasPermission('projects', 'edit')   || is_super_admin($currentUserId);
$canDelete = hasPermission('projects', 'delete') || is_super_admin($currentUserId);
$canExport = hasPermission('projects', 'export') || is_super_admin($currentUserId);

if (!$canView && $action !== 'export_csv') {
    projects_json(false, 'Permission denied. You cannot view Projects.', [], 403);
}

// Helper: initials
function get_project_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

// Helper: deterministic avatar color
function get_project_avatar_color(int $id): string
{
    $colors = ['#2563EB', '#7C3AED', '#0284C7', '#10B981', '#D97706', '#6366F1', '#EC4899', '#14B8A6'];
    return $colors[$id % count($colors)];
}

// Helper: Log activity
function log_project_activity(PDO $pdo, int $orgId, int $projectId, int $userId, string $type, string $title, ?string $desc = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities 
            (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'projects', ?, ?, NOW())
        ");
        $stmt->execute([$orgId, $userId, $type, $title, $desc, $projectId, $userId]);
    } catch (Throwable $e) {
        // Activity failure should not halt operation
    }
}

// ==================================================
// 1. BOOTSTRAP ACTION
// ==================================================
if ($action === 'bootstrap') {
    // 1. Fetch KPI metrics directly from MySQL
    $kpiStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_projects,
            COUNT(CASE WHEN status IN ('In Progress', 'Planning') THEN 1 END) AS active_projects,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) AS completed_projects,
            COUNT(CASE WHEN (status = 'Overdue' OR (status != 'Completed' AND due_date IS NOT NULL AND due_date < CURDATE())) THEN 1 END) AS overdue_projects,
            COUNT(CASE WHEN (status != 'Completed' AND due_date IS NOT NULL AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)) THEN 1 END) AS due_soon_projects
        FROM projects
        WHERE organization_id = ?
    ");
    $kpiStmt->execute([$organizationId]);
    $kpiRow = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $kpis = [
        'total'     => (int)($kpiRow['total_projects'] ?? 0),
        'active'    => (int)($kpiRow['active_projects'] ?? 0),
        'due_soon'  => (int)($kpiRow['due_soon_projects'] ?? 0),
        'completed' => (int)($kpiRow['completed_projects'] ?? 0),
        'overdue'   => (int)($kpiRow['overdue_projects'] ?? 0)
    ];

    // 2. Fetch active organization users (for managers)
    $usersStmt = $pdo->prepare("
        SELECT id, name, email, role, photo_path, status
        FROM users
        WHERE organization_id = ? AND status = 'active'
        ORDER BY name ASC
    ");
    $usersStmt->execute([$organizationId]);
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch all projects for organization
    $projectsStmt = $pdo->prepare("
        SELECT 
            p.*,
            u.name AS manager_name,
            u.photo_path AS manager_photo,
            d.name AS deal_name,
            d.deal_code AS deal_code
        FROM projects p
        LEFT JOIN users u ON u.id = p.manager_id AND u.organization_id = p.organization_id
        LEFT JOIN deals d ON d.id = p.deal_id AND d.organization_id = p.organization_id
        WHERE p.organization_id = ?
        ORDER BY p.id DESC
    ");
    $projectsStmt->execute([$organizationId]);
    $rawProjects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedProjects = [];
    foreach ($rawProjects as $p) {
        $mgrName = $p['manager_name'] ?: 'Unassigned';
        $mgrId = (int)($p['manager_id'] ?: 0);
        
        // Check if overdue dynamically
        $status = $p['status'] ?: 'Planning';
        if ($status !== 'Completed' && !empty($p['due_date']) && strtotime($p['due_date']) < strtotime('today')) {
            $status = 'Overdue';
        }

        $formattedProjects[] = [
            'id'              => (int)$p['id'],
            'project_code'    => $p['project_code'],
            'title'           => $p['name'],
            'client'          => $p['client_name'],
            'manager'         => $mgrName,
            'manager_id'      => $mgrId ? $mgrId : null,
            'managerInitials' => get_project_initials($mgrName),
            'managerColor'    => get_project_avatar_color($mgrId ?: $p['id']),
            'status'          => $status,
            'priority'        => $p['priority'] ?: 'Medium',
            'startDate'       => $p['start_date'] ?: '',
            'dueDate'         => $p['due_date'] ?: '',
            'budget'          => $p['budget'] !== null ? (float)$p['budget'] : null,
            'progress'        => $p['progress'] !== null ? (int)$p['progress'] : 0,
            'description'     => $p['description'] ?: '',
            'deal_id'         => $p['deal_id'] ? (int)$p['deal_id'] : null,
            'deal'            => $p['deal_name'] ? ($p['deal_name'] . ($p['deal_code'] ? ' (' . $p['deal_code'] . ')' : '')) : null,
            'created_at'      => $p['created_at']
        ];
    }

    // 4. Fetch active companies for autocomplete
    $compStmt = $pdo->prepare("
        SELECT id, name
        FROM companies
        WHERE organization_id = ?
        ORDER BY name ASC
    ");
    $compStmt->execute([$organizationId]);
    $companies = $compStmt->fetchAll(PDO::FETCH_ASSOC);

    projects_json(true, 'Projects bootstrapped successfully.', [
        'kpis'        => $kpis,
        'projects'    => $formattedProjects,
        'users'       => $users,
        'companies'   => $companies,
        'permissions' => [
            'can_create' => $canCreate,
            'can_edit'   => $canEdit,
            'can_delete' => $canDelete,
            'can_export' => $canExport
        ],
        'current_user' => [
            'id'              => $currentUserId,
            'name'            => $currentUser['name'],
            'organization_id' => $organizationId
        ]
    ]);
}

// ==================================================
// GET DEALS BY COMPANY
// ==================================================
if ($action === 'get_company_deals') {
    $company = trim($_GET['company'] ?? ($input['company'] ?? ''));
    if ($company === '') {
        projects_json(true, 'No company specified.', ['deals' => []]);
    }

    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.deal_code,
            d.name,
            d.company,
            d.stage,
            d.value,
            d.status
        FROM deals d
        WHERE d.organization_id = ?
          AND (
              LOWER(TRIM(d.company)) = LOWER(TRIM(?))
              OR d.contact_id IN (
                  SELECT cc.contact_id
                  FROM contact_companies cc
                  JOIN companies c ON c.id = cc.company_id AND c.organization_id = cc.organization_id
                  WHERE cc.organization_id = ? AND LOWER(TRIM(c.name)) = LOWER(TRIM(?))
              )
          )
        ORDER BY d.name ASC, d.id DESC
    ");
    $stmt->execute([$organizationId, $company, $organizationId, $company]);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($deals as $d) {
        $dealCode = $d['deal_code'] ?: ('DL-' . str_pad($d['id'], 3, '0', STR_PAD_LEFT));
        $formatted[] = [
            'id'        => (int)$d['id'],
            'deal_code' => $dealCode,
            'name'      => $d['name'],
            'company'   => $d['company'],
            'stage'     => $d['stage'],
            'status'    => $d['status'],
            'value'     => $d['value'] !== null ? (float)$d['value'] : null,
            'display'   => $d['name'] . ' (' . $dealCode . ')'
        ];
    }

    projects_json(true, 'Deals retrieved successfully.', ['deals' => $formatted]);
}

// ==================================================
// 2. CREATE PROJECT
// ==================================================
if ($action === 'create_project') {
    if (!$canCreate) {
        projects_json(false, 'Permission denied. You cannot create projects.', [], 403);
    }

    $title  = trim($input['name'] ?? ($input['title'] ?? ''));
    $client = trim($input['client_name'] ?? ($input['client'] ?? ''));

    if ($title === '' || $client === '') {
        projects_json(false, 'Project Name and Client/Company are required.', [], 422);
    }

    // Manager validation
    $managerId = !empty($input['manager_id']) ? (int)$input['manager_id'] : null;
    if ($managerId) {
        $chkMgr = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $chkMgr->execute([$managerId, $organizationId]);
        if (!$chkMgr->fetchColumn()) {
            $managerId = null;
        }
    }

    // Deal link validation
    $dealId = !empty($input['deal_id']) ? (int)$input['deal_id'] : null;
    if ($dealId) {
        $chkDeal = $pdo->prepare("SELECT id, company FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
        $chkDeal->execute([$dealId, $organizationId]);
        $dealRow = $chkDeal->fetch(PDO::FETCH_ASSOC);
        if (!$dealRow) {
            projects_json(false, 'Selected Deal does not exist in your organization.', [], 422);
        }

        $compMatches = (strcasecmp(trim((string)$dealRow['company']), $client) === 0);
        if (!$compMatches) {
            $chkComp = $pdo->prepare("
                SELECT 1 
                FROM deals d
                JOIN contact_companies cc ON cc.contact_id = d.contact_id AND cc.organization_id = d.organization_id
                JOIN companies c ON c.id = cc.company_id AND c.organization_id = cc.organization_id
                WHERE d.id = ? AND d.organization_id = ? AND LOWER(TRIM(c.name)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $chkComp->execute([$dealId, $organizationId, $client]);
            if ($chkComp->fetchColumn()) {
                $compMatches = true;
            }
        }
        if (!$compMatches) {
            projects_json(false, 'The selected Deal does not belong to the selected Client / Company.', [], 422);
        }
    }

    $status      = !empty($input['status']) ? trim($input['status']) : null;
    $priority    = !empty($input['priority']) ? trim($input['priority']) : null;
    $startDate   = !empty($input['start_date']) ? date('Y-m-d', strtotime($input['start_date'])) : null;
    $dueDate     = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : null;
    $budget      = isset($input['budget']) && $input['budget'] !== '' ? max(0.0, (float)$input['budget']) : null;
    $progress    = isset($input['progress']) && $input['progress'] !== '' ? min(100, max(0, (int)$input['progress'])) : null;
    $description = !empty($input['description']) ? trim($input['description']) : null;

    // Generate tenant-safe project code (PRJ-001, PRJ-002, ...)
    $codeStmt = $pdo->prepare("
        SELECT project_code 
        FROM projects 
        WHERE organization_id = ? AND project_code LIKE 'PRJ-%' 
        ORDER BY id DESC LIMIT 1
    ");
    $codeStmt->execute([$organizationId]);
    $lastCode = $codeStmt->fetchColumn();
    $nextNum = 1;
    if ($lastCode && preg_match('/PRJ-(\d+)/', $lastCode, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    $projectCode = 'PRJ-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    // Double check unique constraint per tenant
    $dupCheck = $pdo->prepare("SELECT id FROM projects WHERE organization_id = ? AND project_code = ? LIMIT 1");
    $dupCheck->execute([$organizationId, $projectCode]);
    if ($dupCheck->fetchColumn()) {
        $projectCode = 'PRJ-' . str_pad($nextNum + 1, 3, '0', STR_PAD_LEFT);
    }

    $insStmt = $pdo->prepare("
        INSERT INTO projects (
            organization_id, project_code, name, client_name, manager_id,
            status, priority, start_date, due_date, budget, progress,
            description, deal_id, created_by, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, NOW(), NOW()
        )
    ");
    $insStmt->execute([
        $organizationId,
        $projectCode,
        $title,
        $client,
        $managerId,
        $status,
        $priority,
        $startDate,
        $dueDate,
        $budget,
        $progress,
        $description,
        $dealId,
        $currentUserId
    ]);
    $newProjectId = (int)$pdo->lastInsertId();

    // Log activity
    log_project_activity($pdo, $organizationId, $newProjectId, $currentUserId, 'project_created', 'Project Created', "Project $title ($projectCode) created for $client.");

    projects_json(true, 'Project created successfully.', [
        'id'           => $newProjectId,
        'project_code' => $projectCode,
        'name'         => $title
    ], 201);
}

// ==================================================
// 3. EDIT PROJECT
// ==================================================
if ($action === 'update_project') {
    if (!$canEdit) {
        projects_json(false, 'Permission denied. You cannot edit projects.', [], 403);
    }

    $projectId = (int)($input['id'] ?? 0);
    if (!$projectId) {
        projects_json(false, 'Invalid Project ID.', [], 422);
    }

    // Verify tenant ownership
    $chkStmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
    $chkStmt->execute([$projectId, $organizationId]);
    $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        projects_json(false, 'Project not found in current organization.', [], 404);
    }

    $title  = trim($input['name'] ?? ($input['title'] ?? ''));
    $client = trim($input['client_name'] ?? ($input['client'] ?? ''));

    if ($title === '' || $client === '') {
        projects_json(false, 'Project Name and Client/Company are required.', [], 422);
    }

    // Manager validation
    $managerId = !empty($input['manager_id']) ? (int)$input['manager_id'] : null;
    if ($managerId) {
        $chkMgr = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $chkMgr->execute([$managerId, $organizationId]);
        if (!$chkMgr->fetchColumn()) {
            $managerId = null;
        }
    }

    // Deal link validation
    $dealId = isset($input['deal_id']) ? (!empty($input['deal_id']) ? (int)$input['deal_id'] : null) : ($existing['deal_id'] ? (int)$existing['deal_id'] : null);
    if ($dealId) {
        $chkDeal = $pdo->prepare("SELECT id, company FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
        $chkDeal->execute([$dealId, $organizationId]);
        $dealRow = $chkDeal->fetch(PDO::FETCH_ASSOC);
        if (!$dealRow) {
            projects_json(false, 'Selected Deal does not exist in your organization.', [], 422);
        }

        $compMatches = (strcasecmp(trim((string)$dealRow['company']), $client) === 0);
        if (!$compMatches) {
            $chkComp = $pdo->prepare("
                SELECT 1 
                FROM deals d
                JOIN contact_companies cc ON cc.contact_id = d.contact_id AND cc.organization_id = d.organization_id
                JOIN companies c ON c.id = cc.company_id AND c.organization_id = cc.organization_id
                WHERE d.id = ? AND d.organization_id = ? AND LOWER(TRIM(c.name)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $chkComp->execute([$dealId, $organizationId, $client]);
            if ($chkComp->fetchColumn()) {
                $compMatches = true;
            }
        }
        if (!$compMatches) {
            projects_json(false, 'The selected Deal does not belong to the selected Client / Company.', [], 422);
        }
    }

    $status      = !empty($input['status']) ? trim($input['status']) : null;
    $priority    = !empty($input['priority']) ? trim($input['priority']) : null;
    $startDate   = !empty($input['start_date']) ? date('Y-m-d', strtotime($input['start_date'])) : null;
    $dueDate     = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : null;
    $budget      = isset($input['budget']) && $input['budget'] !== '' ? max(0.0, (float)$input['budget']) : null;
    $progress    = isset($input['progress']) && $input['progress'] !== '' ? min(100, max(0, (int)$input['progress'])) : null;
    $description = !empty($input['description']) ? trim($input['description']) : null;

    $upStmt = $pdo->prepare("
        UPDATE projects SET
            name = ?,
            client_name = ?,
            manager_id = ?,
            status = ?,
            priority = ?,
            start_date = ?,
            due_date = ?,
            budget = ?,
            progress = ?,
            description = ?,
            deal_id = ?,
            updated_at = NOW()
        WHERE id = ? AND organization_id = ?
    ");
    $upStmt->execute([
        $title,
        $client,
        $managerId,
        $status,
        $priority,
        $startDate,
        $dueDate,
        $budget,
        $progress,
        $description,
        $dealId,
        $projectId,
        $organizationId
    ]);

    log_project_activity($pdo, $organizationId, $projectId, $currentUserId, 'project_updated', 'Project Updated', "Project $title updated.");

    projects_json(true, 'Project updated successfully.', [
        'id'           => $projectId,
        'project_code' => $existing['project_code'],
        'name'         => $title
    ]);
}

// ==================================================
// 4. DELETE PROJECT
// ==================================================
if ($action === 'delete_project') {
    if (!$canDelete) {
        projects_json(false, 'Permission denied. You cannot delete projects.', [], 403);
    }

    $projectId = (int)($input['id'] ?? 0);
    if (!$projectId) {
        projects_json(false, 'Invalid Project ID.', [], 422);
    }

    $chk = $pdo->prepare("SELECT id, name, project_code FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
    $chk->execute([$projectId, $organizationId]);
    $p = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        projects_json(false, 'Project not found.', [], 404);
    }

    // Delete linked files from disk if any
    $fileStmt = $pdo->prepare("SELECT file_path FROM project_files WHERE project_id = ? AND organization_id = ?");
    $fileStmt->execute([$projectId, $organizationId]);
    $files = $fileStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($files as $filePath) {
        $abs = 'c:/xampp/htdocs/nexFlow/' . ltrim($filePath, '/');
        if (file_exists($abs) && is_file($abs)) {
            @unlink($abs);
        }
    }

    // Clean up related polymorphic records
    $pdo->prepare("DELETE FROM tasks WHERE related_type = 'projects' AND related_id = ? AND organization_id = ?")->execute([$projectId, $organizationId]);
    $pdo->prepare("DELETE FROM team_activities WHERE related_entity = 'projects' AND related_entity_id = ? AND organization_id = ?")->execute([$projectId, $organizationId]);
    $pdo->prepare("DELETE FROM team_notes WHERE related_type = 'projects' AND related_id = ? AND organization_id = ?")->execute([$projectId, $organizationId]);
    $pdo->prepare("DELETE FROM calendar_events WHERE related_type = 'projects' AND related_id = ? AND organization_id = ?")->execute([$projectId, $organizationId]);
    $pdo->prepare("DELETE FROM project_milestones WHERE project_id = ? AND organization_id = ?")->execute([$projectId, $organizationId]);
    $pdo->prepare("DELETE FROM project_files WHERE project_id = ? AND organization_id = ?")->execute([$projectId, $organizationId]);

    // Delete project
    $pdo->prepare("DELETE FROM projects WHERE id = ? AND organization_id = ?")->execute([$projectId, $organizationId]);

    projects_json(true, "Project '{$p['name']}' ({$p['project_code']}) deleted successfully.");
}

// ==================================================
// 5. PROJECT DRAWER DETAILS
// ==================================================
if ($action === 'project_drawer') {
    $projectId = (int)($_GET['id'] ?? ($input['id'] ?? 0));
    if (!$projectId) {
        projects_json(false, 'Invalid Project ID.', [], 422);
    }

    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.name AS manager_name,
            u.email AS manager_email,
            u.photo_path AS manager_photo,
            d.name AS deal_name,
            d.deal_code AS deal_code
        FROM projects p
        LEFT JOIN users u ON u.id = p.manager_id AND u.organization_id = p.organization_id
        LEFT JOIN deals d ON d.id = p.deal_id AND d.organization_id = p.organization_id
        WHERE p.id = ? AND p.organization_id = ?
        LIMIT 1
    ");
    $stmt->execute([$projectId, $organizationId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        projects_json(false, 'Project not found in current organization.', [], 404);
    }

    // 1. Tasks
    $taskStmt = $pdo->prepare("
        SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.assigned_to, u.name AS assignee_name
        FROM tasks t
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE t.organization_id = ? AND t.related_type = 'projects' AND t.related_id = ?
        ORDER BY t.id DESC
    ");
    $taskStmt->execute([$organizationId, $projectId]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Milestones
    $msStmt = $pdo->prepare("
        SELECT id, name, status, sort_order, created_at
        FROM project_milestones
        WHERE organization_id = ? AND project_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $msStmt->execute([$organizationId, $projectId]);
    $milestones = $msStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Files (project_files + linked documents)
    $fileStmt = $pdo->prepare("
        SELECT f.id, f.file_name, f.file_path, f.file_size, f.mime_type, f.created_at, COALESCE(u.name, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) AS uploader_name, 'project_file' AS source
        FROM project_files f
        LEFT JOIN users u ON u.id = f.uploaded_by
        WHERE f.organization_id = ? AND f.project_id = ?
        UNION ALL
        SELECT d.id, COALESCE(d.original_filename, d.title) AS file_name, d.file_path, d.file_size, d.mime_type, d.created_at, COALESCE(u.name, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) AS uploader_name, 'document' AS source
        FROM documents d
        LEFT JOIN users u ON u.id = d.uploaded_by
        WHERE d.organization_id = ? AND d.project_id = ? AND d.is_archived = 0 AND d.file_path IS NOT NULL AND d.file_path != ''
        ORDER BY created_at DESC, id DESC
    ");
    $fileStmt->execute([$organizationId, $projectId, $organizationId, $projectId]);
    $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Activities
    $actStmt = $pdo->prepare("
        SELECT a.id, a.activity_type, a.title, a.description, a.created_at, u.name AS author_name
        FROM team_activities a
        LEFT JOIN users u ON u.id = a.created_by
        WHERE a.organization_id = ? AND a.related_entity = 'projects' AND a.related_entity_id = ?
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $actStmt->execute([$organizationId, $projectId]);
    $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Linked CRM Records (Strict existing database relationships only)
    // 5.1 Deal: projects.deal_id or documents.deal_id
    $dealId = $p['deal_id'] ? (int)$p['deal_id'] : null;
    $deal = $p['deal_name'] ? ($p['deal_name'] . ($p['deal_code'] ? ' (' . $p['deal_code'] . ')' : '')) : null;
    if (!$deal) {
        $dealDocStmt = $pdo->prepare("
            SELECT d.id, d.name, d.deal_code
            FROM deals d
            INNER JOIN documents doc ON doc.deal_id = d.id AND doc.organization_id = d.organization_id
            WHERE d.organization_id = ? AND doc.project_id = ?
            ORDER BY d.id DESC LIMIT 1
        ");
        $dealDocStmt->execute([$organizationId, $projectId]);
        $dealRow = $dealDocStmt->fetch(PDO::FETCH_ASSOC);
        if ($dealRow) {
            $dealId = (int)$dealRow['id'];
            $deal = $dealRow['name'] . ($dealRow['deal_code'] ? ' (' . $dealRow['deal_code'] . ')' : '');
        }
    }

    // 5.2 Proposal: proposals.project_id or documents.proposal_id
    $proposal = null;
    $proposalId = null;
    $propStmt = $pdo->prepare("
        SELECT id, proposal_number, title
        FROM proposals
        WHERE organization_id = ? AND project_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $propStmt->execute([$organizationId, $projectId]);
    $propRow = $propStmt->fetch(PDO::FETCH_ASSOC);

    if (!$propRow) {
        $propDocStmt = $pdo->prepare("
            SELECT p.id, p.proposal_number, p.title
            FROM proposals p
            INNER JOIN documents doc ON doc.proposal_id = p.id AND doc.organization_id = p.organization_id
            WHERE p.organization_id = ? AND doc.project_id = ?
            ORDER BY p.id DESC LIMIT 1
        ");
        $propDocStmt->execute([$organizationId, $projectId]);
        $propRow = $propDocStmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($propRow) {
        $proposalId = (int)$propRow['id'];
        $propNum = trim((string)($propRow['proposal_number'] ?? ''));
        $propTitle = trim((string)($propRow['title'] ?? ''));
        if ($propNum !== '' && $propTitle !== '') {
            $proposal = $propNum . ' — ' . $propTitle;
        } elseif ($propNum !== '') {
            $proposal = $propNum;
        } else {
            $proposal = $propTitle ?: null;
        }
    }

    // 5.3 Primary Invoice: invoices.project_id or documents.invoice_id
    $invoice = null;
    $invoiceId = null;
    $invStmt = $pdo->prepare("
        SELECT id, invoice_number, title
        FROM invoices
        WHERE organization_id = ? AND project_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $invStmt->execute([$organizationId, $projectId]);
    $invRow = $invStmt->fetch(PDO::FETCH_ASSOC);

    if (!$invRow) {
        $invDocStmt = $pdo->prepare("
            SELECT i.id, i.invoice_number, i.title
            FROM invoices i
            INNER JOIN documents doc ON doc.invoice_id = i.id AND doc.organization_id = i.organization_id
            WHERE i.organization_id = ? AND doc.project_id = ?
            ORDER BY i.id DESC LIMIT 1
        ");
        $invDocStmt->execute([$organizationId, $projectId]);
        $invRow = $invDocStmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($invRow) {
        $invoiceId = (int)$invRow['id'];
        $invNum = trim((string)($invRow['invoice_number'] ?? ''));
        $invTitle = trim((string)($invRow['title'] ?? ''));
        if ($invNum !== '' && $invTitle !== '') {
            $invoice = $invNum . ' — ' . $invTitle;
        } elseif ($invNum !== '') {
            $invoice = $invNum;
        } else {
            $invoice = $invTitle ?: null;
        }
    }

    // 5.4 Related Contract: documents.contract_id where documents.project_id = :projectId
    $contract = null;
    $contractId = null;
    $cntDocStmt = $pdo->prepare("
        SELECT c.id, c.contract_number, c.title
        FROM contracts c
        INNER JOIN documents doc ON doc.contract_id = c.id AND doc.organization_id = c.organization_id
        WHERE c.organization_id = ? AND doc.project_id = ?
        ORDER BY c.id DESC LIMIT 1
    ");
    $cntDocStmt->execute([$organizationId, $projectId]);
    $cntRow = $cntDocStmt->fetch(PDO::FETCH_ASSOC);

    if ($cntRow) {
        $contractId = (int)$cntRow['id'];
        $cntNum = trim((string)($cntRow['contract_number'] ?? ''));
        $cntTitle = trim((string)($cntRow['title'] ?? ''));
        if ($cntNum !== '' && $cntTitle !== '') {
            $contract = $cntNum . ' — ' . $cntTitle;
        } elseif ($cntNum !== '') {
            $contract = $cntNum;
        } else {
            $contract = $cntTitle ?: null;
        }
    }

    $mgrName = $p['manager_name'] ?: 'Unassigned';
    $mgrId = (int)($p['manager_id'] ?: 0);

    projects_json(true, 'Project details retrieved.', [
        'project' => [
            'id'              => (int)$p['id'],
            'project_code'    => $p['project_code'],
            'title'           => $p['name'],
            'client'          => $p['client_name'],
            'manager'         => $mgrName,
            'manager_id'      => $mgrId ? $mgrId : null,
            'managerInitials' => get_project_initials($mgrName),
            'managerColor'    => get_project_avatar_color($mgrId ?: $p['id']),
            'status'          => $p['status'] ?: 'Planning',
            'priority'        => $p['priority'] ?: 'Medium',
            'startDate'       => $p['start_date'] ?: '',
            'dueDate'         => $p['due_date'] ?: '',
            'budget'          => $p['budget'] !== null ? (float)$p['budget'] : null,
            'progress'        => $p['progress'] !== null ? (int)$p['progress'] : 0,
            'description'     => $p['description'] ?: '',
            'deal_id'         => $dealId,
            'deal'            => $deal,
            'contract_id'     => $contractId,
            'contract'        => $contract,
            'proposal_id'     => $proposalId,
            'proposal'        => $proposal,
            'invoice_id'      => $invoiceId,
            'invoice'         => $invoice
        ],
        'tasks'      => $tasks,
        'milestones' => $milestones,
        'files'      => $files,
        'activity'   => $activities
    ]);
}

// ==================================================
// 6. TASKS MANAGEMENT (CREATE, TOGGLE, DELETE)
// ==================================================
if ($action === 'create_task') {
    $projectId = (int)($input['project_id'] ?? 0);
    $title     = trim($input['title'] ?? '');
    $dueDate   = !empty($input['due_date']) ? date('Y-m-d H:i:s', strtotime($input['due_date'])) : null;
    $status    = !empty($input['status']) ? trim($input['status']) : 'pending';

    if (!$projectId || $title === '') {
        projects_json(false, 'Project ID and Task Title are required.', [], 422);
    }

    $chk = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
    $chk->execute([$projectId, $organizationId]);
    if (!$chk->fetchColumn()) {
        projects_json(false, 'Project not found.', [], 404);
    }

    $ins = $pdo->prepare("
        INSERT INTO tasks (organization_id, title, status, priority, due_date, assigned_to, related_type, related_id, created_at, updated_at)
        VALUES (?, ?, ?, 'medium', ?, ?, 'projects', ?, NOW(), NOW())
    ");
    $ins->execute([$organizationId, $title, $status, $dueDate, $currentUserId, $projectId]);
    $taskId = (int)$pdo->lastInsertId();

    log_project_activity($pdo, $organizationId, $projectId, $currentUserId, 'task_created', 'Task Added', "Added task: $title");

    projects_json(true, 'Task created successfully.', ['id' => $taskId]);
}

if ($action === 'update_task_status') {
    $taskId    = (int)($input['task_id'] ?? 0);
    $completed = !empty($input['completed']);
    $newStatus = $completed ? 'completed' : 'pending';

    if (!$taskId) {
        projects_json(false, 'Task ID is required.', [], 422);
    }

    $up = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ? AND related_type = 'projects'");
    $up->execute([$newStatus, $taskId, $organizationId]);

    projects_json(true, 'Task status updated.', ['status' => $newStatus]);
}

if ($action === 'delete_task') {
    $taskId = (int)($input['task_id'] ?? 0);
    if (!$taskId) {
        projects_json(false, 'Task ID is required.', [], 422);
    }

    $del = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND organization_id = ? AND related_type = 'projects'");
    $del->execute([$taskId, $organizationId]);

    projects_json(true, 'Task deleted successfully.');
}

// ==================================================
// 7. MILESTONES MANAGEMENT (CREATE, TOGGLE, DELETE)
// ==================================================
if ($action === 'create_milestone') {
    $projectId = (int)($input['project_id'] ?? 0);
    $name      = trim($input['name'] ?? '');
    $status    = trim($input['status'] ?? 'Pending');

    if (!$projectId || $name === '') {
        projects_json(false, 'Project ID and Milestone Name are required.', [], 422);
    }

    $chk = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
    $chk->execute([$projectId, $organizationId]);
    if (!$chk->fetchColumn()) {
        projects_json(false, 'Project not found.', [], 404);
    }

    // Get max sort_order
    $soStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM project_milestones WHERE project_id = ? AND organization_id = ?");
    $soStmt->execute([$projectId, $organizationId]);
    $sortOrder = (int)$soStmt->fetchColumn();

    $ins = $pdo->prepare("
        INSERT INTO project_milestones (organization_id, project_id, name, status, sort_order, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $ins->execute([$organizationId, $projectId, $name, $status, $sortOrder]);
    $msId = (int)$pdo->lastInsertId();

    log_project_activity($pdo, $organizationId, $projectId, $currentUserId, 'milestone_created', 'Milestone Added', "Added milestone: $name");

    projects_json(true, 'Milestone created successfully.', ['id' => $msId]);
}

if ($action === 'update_milestone_status') {
    $msId   = (int)($input['milestone_id'] ?? 0);
    $status = trim($input['status'] ?? 'Completed');

    if (!$msId) {
        projects_json(false, 'Milestone ID is required.', [], 422);
    }

    $up = $pdo->prepare("UPDATE project_milestones SET status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $up->execute([$status, $msId, $organizationId]);

    projects_json(true, 'Milestone status updated.', ['status' => $status]);
}

if ($action === 'delete_milestone') {
    $msId = (int)($input['milestone_id'] ?? 0);
    if (!$msId) {
        projects_json(false, 'Milestone ID is required.', [], 422);
    }

    $del = $pdo->prepare("DELETE FROM project_milestones WHERE id = ? AND organization_id = ?");
    $del->execute([$msId, $organizationId]);

    projects_json(true, 'Milestone deleted successfully.');
}

// ==================================================
// 8. FILE UPLOADS & ATTACHMENTS
// ==================================================
if ($action === 'upload_file') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!$projectId) {
        projects_json(false, 'Project ID is required.', [], 422);
    }

    // Verify tenant project
    $chk = $pdo->prepare("SELECT id, name FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
    $chk->execute([$projectId, $organizationId]);
    if (!$chk->fetchColumn()) {
        projects_json(false, 'Project not found.', [], 404);
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        projects_json(false, 'No file uploaded or upload error occurred.', [], 400);
    }

    $file = $_FILES['file'];
    $maxBytes = 25 * 1024 * 1024; // 25 MB
    if ($file['size'] > $maxBytes) {
        projects_json(false, 'File exceeds maximum allowed size of 25MB.', [], 422);
    }

    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    
    // Prohibited dangerous extensions
    $forbiddenExts = ['php', 'phtml', 'exe', 'bat', 'cmd', 'sh', 'js', 'py', 'pl', 'cgi'];
    if (in_array($ext, $forbiddenExts, true)) {
        projects_json(false, 'File type not permitted for security reasons.', [], 422);
    }

    // Safe storage path
    $targetDir = "c:/xampp/htdocs/nexFlow/uploads/projects/$organizationId/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $safeName = 'proj_' . $projectId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $targetDir . $safeName;
    $dbPath = "uploads/projects/$organizationId/$safeName";

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        projects_json(false, 'Failed to save uploaded file.', [], 500);
    }

    // Human readable size
    $bytes = (int)$file['size'];
    if ($bytes >= 1048576) {
        $formattedSize = number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        $formattedSize = number_format($bytes / 1024, 0) . ' KB';
    } else {
        $formattedSize = $bytes . ' B';
    }

    $mimeType = mime_content_type($destPath) ?: ($file['type'] ?: 'application/octet-stream');

    $insFile = $pdo->prepare("
        INSERT INTO project_files (organization_id, project_id, file_name, file_path, file_size, mime_type, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insFile->execute([$organizationId, $projectId, $origName, $dbPath, $formattedSize, $mimeType, $currentUserId]);
    $fileId = (int)$pdo->lastInsertId();

    log_project_activity($pdo, $organizationId, $projectId, $currentUserId, 'file_uploaded', 'File Uploaded', "Uploaded document: $origName ($formattedSize)");

    projects_json(true, 'File uploaded successfully.', [
        'id'        => $fileId,
        'file_name' => $origName,
        'file_path' => $dbPath,
        'file_size' => $formattedSize
    ], 201);
}

if ($action === 'delete_file') {
    $fileId = (int)($input['file_id'] ?? 0);
    $source = trim($input['source'] ?? '');
    if (!$fileId) {
        projects_json(false, 'File ID is required.', [], 422);
    }

    if ($source !== 'document') {
        $stmt = $pdo->prepare("SELECT file_path, file_name FROM project_files WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmt->execute([$fileId, $organizationId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($f) {
            $abs = 'c:/xampp/htdocs/nexFlow/' . ltrim($f['file_path'], '/');
            if (file_exists($abs) && is_file($abs)) {
                @unlink($abs);
            }
            $pdo->prepare("DELETE FROM project_files WHERE id = ? AND organization_id = ?")->execute([$fileId, $organizationId]);
            projects_json(true, 'File deleted successfully.');
        }
    }

    // Check if it's a linked document from documents table
    $docStmt = $pdo->prepare("SELECT id, title, file_path FROM documents WHERE id = ? AND organization_id = ? LIMIT 1");
    $docStmt->execute([$fileId, $organizationId]);
    $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
    if ($doc) {
        // Unlink project from document instead of destroying the document entirely
        $pdo->prepare("UPDATE documents SET project_id = NULL, related_type = CASE WHEN related_type = 'project' THEN NULL ELSE related_type END, related_id = CASE WHEN related_type = 'project' THEN NULL ELSE related_id END WHERE id = ? AND organization_id = ?")
            ->execute([$fileId, $organizationId]);
        projects_json(true, 'Document unlinked from project successfully.');
    }

    projects_json(false, 'File not found.', [], 404);
}

// ==================================================
// 9. LOG ACTIVITY ACTION
// ==================================================
if ($action === 'log_activity') {
    $projectId = (int)($input['project_id'] ?? 0);
    $text      = trim($input['text'] ?? ($input['title'] ?? ''));

    if (!$projectId || $text === '') {
        projects_json(false, 'Project ID and Activity text are required.', [], 422);
    }

    $chk = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
    $chk->execute([$projectId, $organizationId]);
    if (!$chk->fetchColumn()) {
        projects_json(false, 'Project not found.', [], 404);
    }

    log_project_activity($pdo, $organizationId, $projectId, $currentUserId, 'note', 'Manual Note', $text);

    projects_json(true, 'Activity logged successfully.');
}

// ==================================================
// 10. EXPORT CSV ACTION
// ==================================================
if ($action === 'export_csv') {
    if (!$canExport) {
        projects_json(false, 'Permission denied. You cannot export projects.', [], 403);
    }

    $stmt = $pdo->prepare("
        SELECT 
            p.project_code,
            p.name AS project_name,
            p.client_name,
            COALESCE(u.name, 'Unassigned') AS manager_name,
            COALESCE(p.status, 'Planning') AS status,
            COALESCE(p.priority, 'Medium') AS priority,
            p.start_date,
            p.due_date,
            COALESCE(p.progress, 0) AS progress,
            COALESCE(p.budget, 0.00) AS budget
        FROM projects p
        LEFT JOIN users u ON u.id = p.manager_id AND u.organization_id = p.organization_id
        WHERE p.organization_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->execute([$organizationId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="projects_export_' . date('Y-m-d') . '.csv"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Project ID', 'Project Name', 'Client / Company', 'Project Manager', 'Status', 'Priority', 'Start Date', 'Due Date', 'Progress (%)', 'Budget (INR)']);
    
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['project_code'],
            $r['project_name'],
            $r['client_name'],
            $r['manager_name'],
            $r['status'],
            $r['priority'],
            $r['start_date'] ?: '',
            $r['due_date'] ?: '',
            $r['progress'],
            number_format((float)$r['budget'], 2, '.', '')
        ]);
    }
    fclose($out);
    exit;
}

// Unknown action fallback
projects_json(false, "Unknown action: '$action'", [], 400);
