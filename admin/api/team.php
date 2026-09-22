<?php
/**
 * NexFlow CRM - Team Management API
 * Organization-scoped backend controller for Team Members, Roles, Workload,
 * Performance, Member Profiles, Notes, Assignments, Activities, and Reassignments.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function team_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authenticate user
$currentUser = nexflow_current_user();
if (!$currentUser) {
    team_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId = (int)$currentUser['id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    team_json(false, 'Database connection failed.', [], 500);
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

// Log team activity
function log_team_activity(PDO $pdo, int $orgId, int $memberId, string $type, string $title, ?string $desc = null, ?string $entity = null, ?int $entityId = null, int $createdBy = 1): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$orgId, $memberId, $type, $title, $desc, $entity, $entityId, $createdBy]);
    } catch (Throwable $e) {}
}

// Fetch single member rich details
function fetch_member_details(PDO $pdo, int $orgId, array $u): array
{
    $uid = (int)$u['id'];
    $fullName = trim($u['name'] ?: (($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')));
    $firstName = $u['first_name'] ?: explode(' ', $fullName)[0];
    $lastName = $u['last_name'] ?: (explode(' ', $fullName)[1] ?? '');

    // Assigned leads count
    $leadStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE organization_id = ? AND assigned_to = ?");
    $leadStmt->execute([$orgId, $uid]);
    $assignedLeads = (int)$leadStmt->fetchColumn();

    // New leads count
    $newLeadStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE organization_id = ? AND assigned_to = ? AND status = 'New'");
    $newLeadStmt->execute([$orgId, $uid]);
    $newLeads = (int)$newLeadStmt->fetchColumn();

    // Open deals count & pipeline value
    $dealStmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(value), 0) FROM deals WHERE organization_id = ? AND assigned_to = ? AND status = 'open'");
    $dealStmt->execute([$orgId, $uid]);
    [$openDeals, $pipelineVal] = $dealStmt->fetch(PDO::FETCH_NUM);
    $openDeals = (int)$openDeals;
    $pipelineVal = (float)$pipelineVal;

    // Highest deal stage
    $stageStmt = $pdo->prepare("SELECT stage FROM deals WHERE organization_id = ? AND assigned_to = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stageStmt->execute([$orgId, $uid]);
    $highestStage = $stageStmt->fetchColumn() ?: 'N/A';

    // Closed won deals count & won revenue
    $wonStmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(value), 0) FROM deals WHERE organization_id = ? AND assigned_to = ? AND status = 'won'");
    $wonStmt->execute([$orgId, $uid]);
    [$dealsWon, $wonRevenue] = $wonStmt->fetch(PDO::FETCH_NUM);
    $dealsWon = (int)$dealsWon;
    $wonRevenue = (float)$wonRevenue;

    // Total closed deals (won + lost) for win rate
    $closedStmt = $pdo->prepare("SELECT COUNT(*) FROM deals WHERE organization_id = ? AND assigned_to = ? AND status IN ('won', 'lost')");
    $closedStmt->execute([$orgId, $uid]);
    $totalClosed = (int)$closedStmt->fetchColumn();
    $winRateVal = $totalClosed > 0 ? round(($dealsWon / $totalClosed) * 100, 1) : 0.0;
    $winRateStr = $totalClosed > 0 ? $winRateVal . '%' : '0.0%';

    // Quota and Target completion
    $target = (float)($u['quota'] ?? 50000);
    $targetCompletion = $target > 0 ? round(($wonRevenue / $target) * 100, 1) : 0.0;

    // Open tasks count
    $taskStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND assigned_to = ? AND status != 'completed'");
    $taskStmt->execute([$orgId, $uid]);
    $tasksCount = (int)$taskStmt->fetchColumn();

    // Scheduled meetings count
    $meetStmt = $pdo->prepare("SELECT COUNT(*) FROM calendar_events WHERE organization_id = ? AND user_id = ? AND status = 'scheduled'");
    $meetStmt->execute([$orgId, $uid]);
    $meetingsCount = (int)$meetStmt->fetchColumn();

    // Workload calculation
    // Capacity score = assigned leads (1) + open deals (2) + tasks (1) + meetings (1)
    $workloadScore = ($assignedLeads * 1) + ($openDeals * 2) + ($tasksCount * 1) + ($meetingsCount * 1);
    // Baseline 20 units = 100% capacity
    $workloadLevel = min(100, max(5, round(($workloadScore / 20) * 100)));
    if ($workloadLevel < 50) {
        $workloadStatus = 'Low';
    } elseif ($workloadLevel <= 80) {
        $workloadStatus = 'Balanced';
    } elseif ($workloadLevel <= 90) {
        $workloadStatus = 'High';
    } else {
        $workloadStatus = 'Overloaded';
    }

    // Manager name if reports_to is set
    $managerName = 'Executive Director';
    if (!empty($u['reports_to'])) {
        $mgrStmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        $mgrStmt->execute([(int)$u['reports_to']]);
        $mName = $mgrStmt->fetchColumn();
        if ($mName) $managerName = $mName;
    }

    // Role display
    $roleName = nexflow_role_label($u['role_id'] ?? $u['role'] ?? 'super_admin');

    return [
        'id'               => (string)$u['id'],
        'numericId'        => (int)$u['id'],
        'name'             => $fullName,
        'firstName'        => $firstName,
        'lastName'         => $lastName,
        'email'            => $u['email'],
        'phone'            => $u['phone'] ?: '',
        'role'             => $roleName,
        'roleId'           => strtolower(str_replace(' ', '_', $u['role'] ?: 'sales_rep')),
        'roleIdNumeric'    => (int)($u['role_id'] ?? 0),
        'department'       => $u['department'] ?: 'Sales',
        'location'         => $u['location'] ?: 'Headquarters',
        'timezone'         => $u['timezone'] ?: 'PST (UTC-8)',
        'avatar'           => get_initials($fullName, $firstName, $lastName),
        'avatarBg'         => get_avatar_color($uid),
        'availability'     => $u['availability'] ?: 'Available',
        'status'           => ucfirst($u['status'] ?? 'Active'),
        'joinedDate'       => date('M d, Y', strtotime($u['created_at'])),
        'manager'          => $managerName,
        'reportsToId'      => (int)($u['reports_to'] ?? 0),
        'assignedLeads'    => $assignedLeads,
        'newLeads'         => $newLeads,
        'openDeals'        => $openDeals,
        'highestStage'     => $highestStage,
        'pipelineValue'    => $pipelineVal,
        'revenue'          => $wonRevenue,
        'target'           => $target,
        'targetCompletion' => $targetCompletion,
        'dealsWon'         => $dealsWon,
        'winRate'          => $winRateStr,
        'winRateVal'       => $winRateVal,
        'workload'         => $workloadStatus,
        'workloadLevel'    => $workloadLevel,
        'lastActive'       => format_relative_time($u['last_login_at'] ?? $u['updated_at'] ?? $u['created_at']),
        'tasksCount'       => $tasksCount,
        'meetingsCount'    => $meetingsCount,
        'responseTime'     => $assignedLeads > 0 ? '1.2h' : 'N/A'
    ];
}

// -------------------------------------------------------------
// ROUTE HANDLERS
// -------------------------------------------------------------

// ACTION: BOOTSTRAP / FULL DATA LOAD
if ($action === 'bootstrap' || $action === 'data') {
    // 1. Fetch all members in current organization
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE organization_id = ? ORDER BY id ASC");
    $userStmt->execute([$organizationId]);
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    $members = [];
    foreach ($users as $u) {
        $members[] = fetch_member_details($pdo, $organizationId, $u);
    }

    // 2. Dynamic KPI Summary Cards
    $totalMembers = count($members);
    $activeNow = 0;
    $adminsCount = 0;
    $totalPipeline = 0.0;
    $totalWonRevenue = 0.0;
    $totalQuota = 0.0;

    foreach ($members as $m) {
        if (in_array(strtolower($m['availability']), ['available', 'busy'])) {
            $activeNow++;
        }
        $rClean = strtolower($m['role']);
        if (strpos($rClean, 'admin') !== false || strpos($rClean, 'manager') !== false || strpos($rClean, 'executive') !== false) {
            $adminsCount++;
        }
        $totalPipeline += $m['pipelineValue'];
        $totalWonRevenue += $m['revenue'];
        $totalQuota += $m['target'];
    }

    $targetAchievedPct = $totalQuota > 0 ? round(($totalWonRevenue / $totalQuota) * 100, 1) : 0.0;

    // 3. Roles and Permissions Matrix
    $roleStmt = $pdo->prepare("SELECT * FROM roles WHERE organization_id = ? OR is_system = 1 ORDER BY id ASC");
    $roleStmt->execute([$organizationId]);
    $rolesRows = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

    // Permission definitions
    $allPerms = $pdo->query("SELECT * FROM permissions ORDER BY module_key ASC, action ASC")->fetchAll(PDO::FETCH_ASSOC);
    $rolePermsStmt = $pdo->query("SELECT * FROM role_permissions");
    $rolePermsRows = $rolePermsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map role_id => array of permission_id
    $rolePermMap = [];
    foreach ($rolePermsRows as $rp) {
        $rolePermMap[(int)$rp['role_id']][] = (int)$rp['permission_id'];
    }

    $rolesData = [];
    foreach ($rolesRows as $r) {
        $rid = (int)$r['id'];
        // Count members in this role
        $mCount = 0;
        foreach ($members as $mem) {
            if ($mem['roleIdNumeric'] === $rid || strtolower($mem['role']) === strtolower($r['name'])) {
                $mCount++;
            }
        }

        $badgeBg = '#F1F5F9';
        $badgeColor = '#334155';
        $rslug = strtolower($r['slug']);
        if (strpos($rslug, 'admin') !== false) {
            $badgeBg = '#FEE2E2'; $badgeColor = '#991B1B';
        } elseif (strpos($rslug, 'manager') !== false) {
            $badgeBg = '#F3E8FF'; $badgeColor = '#6B21A8';
        } elseif (strpos($rslug, 'rep') !== false) {
            $badgeBg = '#E0F2FE'; $badgeColor = '#075985';
        } elseif (strpos($rslug, 'executive') !== false) {
            $badgeBg = '#FEF3C7'; $badgeColor = '#92400E';
        } elseif (strpos($rslug, 'ops') !== false) {
            $badgeBg = '#FCE7F3'; $badgeColor = '#9D174D';
        }

        $rolesData[] = [
            'id'           => $r['slug'],
            'numericId'    => $rid,
            'title'        => $r['name'],
            'description'  => $r['description'] ?: '',
            'membersCount' => $mCount,
            'badgeBg'      => $badgeBg,
            'badgeColor'   => $badgeColor
        ];
    }

    // 4. Performance Summary
    $wonDealsTotal = 0;
    $closedDealsTotal = 0;
    foreach ($members as $m) {
        $wonDealsTotal += $m['dealsWon'];
    }
    $avgWinRate = count($members) > 0 ? round(array_sum(array_column($members, 'winRateVal')) / count($members), 1) : 0.0;

    $summaryData = [
        'totalMembers'      => $totalMembers,
        'activeNow'         => $activeNow,
        'adminsManagers'    => $adminsCount,
        'teamPipeline'      => $totalPipeline,
        'teamQuota'         => $totalQuota,
        'targetAchievedPct' => $targetAchievedPct,
        'totalRevenue'      => $totalWonRevenue,
        'avgWinRate'        => $avgWinRate . '%',
        'avgSalesCycle'     => '26 days'
    ];

    $statsData = [
        'total_members'     => $totalMembers,
        'active_members'    => $activeNow,
        'admins_managers'   => $adminsCount,
        'team_pipeline'     => $totalPipeline,
        'team_quota'        => $totalQuota,
        'target_achieved'   => $targetAchievedPct,
        'total_revenue'     => $totalWonRevenue,
        'avg_win_rate'      => $avgWinRate
    ];

    $allPermsStmt = $pdo->query("SELECT id, module_key, action, description FROM permissions ORDER BY module_key, id");
    $allPerms = $allPermsStmt->fetchAll(PDO::FETCH_ASSOC);

    team_json(true, 'Team data retrieved.', [
        'members'     => $members,
        'summary'     => $summaryData,
        'stats'       => $statsData,
        'roles'       => $rolesData,
        'permissions' => $allPerms
    ]);
}

// ACTION: MEMBER PROFILE DETAILS (Overview, Performance, Assignments, Activity, Notes)
if ($action === 'member') {
    $memberId = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if ($memberId <= 0) {
        team_json(false, 'Valid member ID required.', [], 422);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND organization_id = ? LIMIT 1");
    $stmt->execute([$memberId, $organizationId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        team_json(false, 'Member not found in this organization.', [], 404);
    }

    $details = fetch_member_details($pdo, $organizationId, $u);

    // 1. Assigned Records (leads & deals)
    $leadStmt = $pdo->prepare("SELECT id, name, company, email, phone, status, value, created_at FROM leads WHERE organization_id = ? AND assigned_to = ? ORDER BY id DESC LIMIT 20");
    $leadStmt->execute([$organizationId, $memberId]);
    $assignedLeads = $leadStmt->fetchAll(PDO::FETCH_ASSOC);

    $dealStmt = $pdo->prepare("SELECT id, name, company, stage, status, value, close_date FROM deals WHERE organization_id = ? AND assigned_to = ? ORDER BY id DESC LIMIT 20");
    $dealStmt->execute([$organizationId, $memberId]);
    $assignedDeals = $dealStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Activity History
    $actStmt = $pdo->prepare("SELECT * FROM team_activities WHERE organization_id = ? AND user_id = ? ORDER BY id DESC LIMIT 30");
    $actStmt->execute([$organizationId, $memberId]);
    $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Notes
    $noteStmt = $pdo->prepare("SELECT tn.*, u.name as author_name FROM team_notes tn JOIN users u ON u.id = tn.created_by WHERE tn.organization_id = ? AND tn.member_id = ? ORDER BY tn.is_pinned DESC, tn.id DESC");
    $noteStmt->execute([$organizationId, $memberId]);
    $notes = $noteStmt->fetchAll(PDO::FETCH_ASSOC);

    team_json(true, 'Member details retrieved.', [
        'profile'     => $details,
        'leads'       => $assignedLeads,
        'deals'       => $assignedDeals,
        'activities'  => $activities,
        'notes'       => $notes
    ]);
}

// ACTION: CREATE / INVITE MEMBER
if ($action === 'create_member' || $action === 'invite_member') {
    $firstName = trim((string)($input['first_name'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $phone = trim((string)($input['phone'] ?? ''));
    $jobTitle = trim((string)($input['job_title'] ?? $input['title'] ?? ''));
    $department = trim((string)($input['department'] ?? 'Sales'));
    $location = trim((string)($input['location'] ?? 'San Francisco, CA'));
    $roleName = trim((string)($input['role'] ?? 'Sales Representative'));
    $managerName = trim((string)($input['manager'] ?? ''));
    $availability = trim((string)($input['availability'] ?? 'Available'));
    $quota = isset($input['quota']) ? (float)$input['quota'] : 50000.00;
    $fullAccess = !empty($input['full_access']);
    $permissions = $input['permissions'] ?? $input['custom_permissions'] ?? [];

    if ($firstName === '' || $lastName === '' || $email === '' || $jobTitle === '') {
        team_json(false, 'Please fill in all required fields (First Name, Last Name, Work Email, Job Title).', [], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        team_json(false, 'Please enter a valid work email address.', [], 422);
    }

    // Check duplicate email
    $dupCheck = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = ? AND organization_id = ? LIMIT 1");
    $dupCheck->execute([$email, $organizationId]);
    if ($dupCheck->fetchColumn()) {
        team_json(false, 'A team member with this email address already exists in your organization.', [], 409);
    }

    // Find role_id
    $roleSlug = strtolower(str_replace(' ', '_', $roleName));
    $rStmt = $pdo->prepare("SELECT id FROM roles WHERE (LOWER(name) = ? OR slug = ?) AND (organization_id = ? OR is_system = 1) LIMIT 1");
    $rStmt->execute([strtolower($roleName), $roleSlug, $organizationId]);
    $roleId = (int)$rStmt->fetchColumn();
    if (!$roleId) {
        $roleId = 3; // Default Sales Rep
    }

    // Find manager reports_to ID if matching member exists
    $reportsToId = null;
    if ($managerName !== '') {
        $mgrStmt = $pdo->prepare("SELECT id FROM users WHERE organization_id = ? AND (LOWER(name) = ? OR id = ?) LIMIT 1");
        $mgrStmt->execute([$organizationId, strtolower($managerName), (int)$managerName]);
        $reportsToId = $mgrStmt->fetchColumn() ?: null;
    }

    try {
        $pdo->beginTransaction();

        $fullName = $firstName . ' ' . $lastName;
        $passToHash = !empty($input['password']) ? (string)$input['password'] : ('NexFlow' . bin2hex(random_bytes(4)) . '!');
        $tempPass = password_hash($passToHash, PASSWORD_BCRYPT);

        $ins = $pdo->prepare("INSERT INTO users (organization_id, name, first_name, last_name, email, phone, job_title, department, location, role, role_id, availability, reports_to, quota, status, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())");
        $ins->execute([
            $organizationId,
            $fullName,
            $firstName,
            $lastName,
            $email,
            $phone,
            $jobTitle,
            $department,
            $location,
            $roleSlug,
            $roleId,
            $availability,
            $reportsToId,
            $quota,
            $tempPass
        ]);
        $newUserId = (int)$pdo->lastInsertId();

        // Save custom permissions
        save_user_permissions($newUserId, $permissions, $fullAccess);

        // Activity log
        log_team_activity($pdo, $organizationId, $newUserId, 'member_invited', 'Team Member Invited', "Invited $fullName as $jobTitle", 'users', $newUserId, $currentUserId);

        $pdo->commit();

        team_json(true, "Team member '$fullName' invited successfully.", ['id' => $newUserId], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        team_json(false, 'Failed to create team member: ' . $e->getMessage(), [], 500);
    }
}

// ACTION: UPDATE / EDIT MEMBER
if ($action === 'update_member') {
    $memberId = (int)($input['id'] ?? 0);
    if ($memberId <= 0) {
        team_json(false, 'Valid member ID required.', [], 422);
    }

    // Verify member belongs to this organization
    $check = $pdo->prepare("SELECT * FROM users WHERE id = ? AND organization_id = ? LIMIT 1");
    $check->execute([$memberId, $organizationId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        team_json(false, 'Team member not found.', [], 404);
    }

    $firstName = trim((string)($input['first_name'] ?? $existing['first_name']));
    $lastName = trim((string)($input['last_name'] ?? $existing['last_name']));
    $email = strtolower(trim((string)($input['email'] ?? $existing['email'])));
    $phone = trim((string)($input['phone'] ?? $existing['phone']));
    $jobTitle = trim((string)($input['job_title'] ?? $input['title'] ?? $existing['job_title']));
    $department = trim((string)($input['department'] ?? $existing['department']));
    $location = trim((string)($input['location'] ?? $existing['location']));
    $roleName = trim((string)($input['role'] ?? $existing['role']));
    $managerName = trim((string)($input['manager'] ?? ''));
    $availability = trim((string)($input['availability'] ?? $existing['availability']));
    $quota = isset($input['quota']) ? (float)$input['quota'] : (float)($existing['quota'] ?? 50000.00);
    $fullAccess = !empty($input['full_access']);
    $permissions = $input['permissions'] ?? $input['custom_permissions'] ?? [];

    if ($firstName === '' || $lastName === '' || $email === '' || $jobTitle === '') {
        team_json(false, 'Please fill in all required fields.', [], 422);
    }

    // Check duplicate email if changed
    if ($email !== strtolower($existing['email'])) {
        $dup = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = ? AND organization_id = ? AND id != ? LIMIT 1");
        $dup->execute([$email, $organizationId, $memberId]);
        if ($dup->fetchColumn()) {
            team_json(false, 'This email is already in use by another team member.', [], 409);
        }
    }

    // Role lookup
    $roleSlug = strtolower(str_replace(' ', '_', $roleName));
    $rStmt = $pdo->prepare("SELECT id FROM roles WHERE (LOWER(name) = ? OR slug = ?) AND (organization_id = ? OR is_system = 1) LIMIT 1");
    $rStmt->execute([strtolower($roleName), $roleSlug, $organizationId]);
    $roleId = (int)$rStmt->fetchColumn() ?: $existing['role_id'];

    $reportsToId = $existing['reports_to'];
    if ($managerName !== '') {
        $mgrStmt = $pdo->prepare("SELECT id FROM users WHERE organization_id = ? AND (LOWER(name) = ? OR id = ?) LIMIT 1");
        $mgrStmt->execute([$organizationId, strtolower($managerName), (int)$managerName]);
        $mFound = $mgrStmt->fetchColumn();
        if ($mFound) $reportsToId = (int)$mFound;
    }

    try {
        $pdo->beginTransaction();

        $fullName = $firstName . ' ' . $lastName;
        $upd = $pdo->prepare("UPDATE users SET name = ?, first_name = ?, last_name = ?, email = ?, phone = ?, job_title = ?, department = ?, location = ?, role = ?, role_id = ?, availability = ?, reports_to = ?, quota = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upd->execute([
            $fullName,
            $firstName,
            $lastName,
            $email,
            $phone,
            $jobTitle,
            $department,
            $location,
            $roleSlug,
            $roleId,
            $availability,
            $reportsToId,
            $quota,
            $memberId,
            $organizationId
        ]);

        save_user_permissions($memberId, $permissions, $fullAccess);

        log_team_activity($pdo, $organizationId, $memberId, 'member_updated', 'Profile Updated', "Updated details for $fullName", 'users', $memberId, $currentUserId);

        $pdo->commit();

        team_json(true, "Team member '$fullName' updated successfully.", ['id' => $memberId]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        team_json(false, 'Failed to update member: ' . $e->getMessage(), [], 500);
    }
}

// ACTION: DELETE / DEACTIVATE MEMBER
if ($action === 'delete_member' || $action === 'deactivate_member') {
    $memberId = (int)($input['id'] ?? 0);
    if ($memberId <= 0) {
        team_json(false, 'Valid member ID required.', [], 422);
    }

    // Protect primary administrator (Olivia Carter / super_admin)
    if ($memberId === 1 || $memberId === $currentUserId) {
        team_json(false, 'You cannot delete or deactivate your own administrator account.', [], 403);
    }

    try {
        $upd = $pdo->prepare("UPDATE users SET status = 'inactive', availability = 'Offline', updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upd->execute([$memberId, $organizationId]);

        log_team_activity($pdo, $organizationId, $memberId, 'member_deactivated', 'Member Deactivated', "Member ID $memberId deactivated", 'users', $memberId, $currentUserId);

        team_json(true, 'Member successfully deactivated.');
    } catch (Throwable $e) {
        team_json(false, 'Failed to deactivate member.', [], 500);
    }
}

// ACTION: BULK DEACTIVATE
if ($action === 'bulk_deactivate') {
    $ids = $input['member_ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        team_json(false, 'No members selected.', [], 422);
    }

    $count = 0;
    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', availability = 'Offline', updated_at = NOW() WHERE id = ? AND organization_id = ? AND id != 1 AND id != ?");
    foreach ($ids as $id) {
        $mid = (int)$id;
        if ($mid > 0) {
            $stmt->execute([$mid, $organizationId, $currentUserId]);
            $count += $stmt->rowCount();
        }
    }

    team_json(true, "$count members deactivated.");
}

// ACTION: NOTES - CREATE
if ($action === 'create_note') {
    $memberId = (int)($input['member_id'] ?? 0);
    $content = trim((string)($input['content'] ?? ''));
    $isPinned = !empty($input['is_pinned']) ? 1 : 0;

    if ($memberId <= 0 || $content === '') {
        team_json(false, 'Member ID and note content are required.', [], 422);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO team_notes (organization_id, member_id, created_by, content, is_pinned, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$organizationId, $memberId, $currentUserId, $content, $isPinned]);
        $noteId = (int)$pdo->lastInsertId();

        log_team_activity($pdo, $organizationId, $memberId, 'note_created', 'Note Added', substr($content, 0, 80), 'team_notes', $noteId, $currentUserId);

        team_json(true, 'Note created.', ['id' => $noteId], 201);
    } catch (Throwable $e) {
        team_json(false, 'Failed to save note.', [], 500);
    }
}

// ACTION: NOTES - UPDATE
if ($action === 'update_note') {
    $noteId = (int)($input['note_id'] ?? $input['id'] ?? 0);
    $content = trim((string)($input['content'] ?? ''));
    $isPinned = isset($input['is_pinned']) ? (!empty($input['is_pinned']) ? 1 : 0) : null;

    if ($noteId <= 0) {
        team_json(false, 'Valid note ID required.', [], 422);
    }

    try {
        if ($isPinned !== null && $content !== '') {
            $stmt = $pdo->prepare("UPDATE team_notes SET content = ?, is_pinned = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
            $stmt->execute([$content, $isPinned, $noteId, $organizationId]);
        } elseif ($content !== '') {
            $stmt = $pdo->prepare("UPDATE team_notes SET content = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
            $stmt->execute([$content, $noteId, $organizationId]);
        } elseif ($isPinned !== null) {
            $stmt = $pdo->prepare("UPDATE team_notes SET is_pinned = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
            $stmt->execute([$isPinned, $noteId, $organizationId]);
        }

        team_json(true, 'Note updated.');
    } catch (Throwable $e) {
        team_json(false, 'Failed to update note.', [], 500);
    }
}

// ACTION: NOTES - TOGGLE PIN
if ($action === 'toggle_note_pin') {
    $noteId = (int)($input['note_id'] ?? $input['id'] ?? 0);
    if ($noteId <= 0) {
        team_json(false, 'Valid note ID required.', [], 422);
    }

    try {
        $stmt = $pdo->prepare("UPDATE team_notes SET is_pinned = IF(is_pinned = 1, 0, 1), updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $stmt->execute([$noteId, $organizationId]);
        team_json(true, 'Note pin status toggled.');
    } catch (Throwable $e) {
        team_json(false, 'Failed to toggle pin.', [], 500);
    }
}

// ACTION: NOTES - DELETE
if ($action === 'delete_note') {
    $noteId = (int)($input['note_id'] ?? $input['id'] ?? 0);
    if ($noteId <= 0) {
        team_json(false, 'Valid note ID required.', [], 422);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM team_notes WHERE id = ? AND organization_id = ?");
        $stmt->execute([$noteId, $organizationId]);
        team_json(true, 'Note deleted.');
    } catch (Throwable $e) {
        team_json(false, 'Failed to delete note.', [], 500);
    }
}

// ACTION: GET LEADS FOR ASSIGNMENT
if ($action === 'leads_for_assignment') {
    $stmt = $pdo->prepare("SELECT id, name, company, email, phone, status, value, assigned_to FROM leads WHERE organization_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$organizationId]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    team_json(true, 'Leads retrieved.', ['leads' => $leads]);
}

// ACTION: ASSIGN LEAD
if ($action === 'assign_lead') {
    $memberId = (int)($input['member_id'] ?? 0);
    $leadId = (int)($input['lead_id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if ($memberId <= 0 || $leadId <= 0) {
        team_json(false, 'Member ID and Lead ID required.', [], 422);
    }

    // Ensure lead belongs to this organization
    $lStmt = $pdo->prepare("SELECT name, company FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
    $lStmt->execute([$leadId, $organizationId]);
    $lead = $lStmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) {
        team_json(false, 'Lead not found in this organization.', [], 404);
    }

    // Ensure member belongs to this organization
    $mStmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND organization_id = ? LIMIT 1");
    $mStmt->execute([$memberId, $organizationId]);
    $memberName = $mStmt->fetchColumn();
    if (!$memberName) {
        team_json(false, 'Member not found in this organization.', [], 404);
    }

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upd->execute([$memberId, $leadId, $organizationId]);

        $leadTitle = "{$lead['name']} ({$lead['company']})";

        log_team_activity($pdo, $organizationId, $memberId, 'lead_assigned', 'Lead Assigned', "Assigned $leadTitle to $memberName" . ($note ? " — $note" : ''), 'leads', $leadId, $currentUserId);

        $pdo->commit();
        team_json(true, "Lead successfully assigned to $memberName.");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        team_json(false, 'Failed to assign lead: ' . $e->getMessage(), [], 500);
    }
}

// ACTION: REASSIGN WORKLOAD
if ($action === 'reassign_work') {
    $sourceId = (int)($input['source_id'] ?? $input['from_user_id'] ?? 0);
    $targetId = (int)($input['target_id'] ?? $input['to_user_id'] ?? 0);
    $type = strtolower(trim((string)($input['record_type'] ?? $input['item_type'] ?? 'leads')));
    $reason = trim((string)($input['reason'] ?? ''));

    if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
        team_json(false, 'Please select different source and destination members.', [], 422);
    }

    try {
        $pdo->beginTransaction();

        $affectedCount = 0;
        if ($type === 'leads' || $type === 'all') {
            $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE organization_id = ? AND assigned_to = ?");
            $stmt->execute([$targetId, $organizationId, $sourceId]);
            $affectedCount += $stmt->rowCount();
        }
        if ($type === 'deals' || $type === 'all') {
            $stmt = $pdo->prepare("UPDATE deals SET assigned_to = ?, updated_at = NOW() WHERE organization_id = ? AND assigned_to = ?");
            $stmt->execute([$targetId, $organizationId, $sourceId]);
            $affectedCount += $stmt->rowCount();
        }
        if ($type === 'tasks' || $type === 'all') {
            $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ?, updated_at = NOW() WHERE organization_id = ? AND assigned_to = ?");
            $stmt->execute([$targetId, $organizationId, $sourceId]);
            $affectedCount += $stmt->rowCount();
        }

        // Get names
        $sName = $pdo->query("SELECT name FROM users WHERE id = $sourceId LIMIT 1")->fetchColumn() ?: 'Source';
        $tName = $pdo->query("SELECT name FROM users WHERE id = $targetId LIMIT 1")->fetchColumn() ?: 'Target';

        log_team_activity($pdo, $organizationId, $targetId, 'work_reassigned', 'Workload Reassigned', "Reassigned $affectedCount $type from $sName to $tName" . ($reason ? " ($reason)" : ''), 'users', $targetId, $currentUserId);

        $pdo->commit();
        team_json(true, "Successfully reassigned $affectedCount $type to $tName.", ['count' => $affectedCount]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        team_json(false, 'Failed to reassign work: ' . $e->getMessage(), [], 500);
    }
}

// ACTION: EXPORT CSV
if ($action === 'export_csv') {
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE organization_id = ? ORDER BY id ASC");
    $userStmt->execute([$organizationId]);
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="team_members_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'First Name', 'Last Name', 'Name', 'Email', 'Phone', 'Role', 'Department', 'Location', 'Availability', 'Status', 'Assigned Leads', 'Pipeline Value', 'Revenue Won', 'Target Quota']);

    foreach ($users as $u) {
        $details = fetch_member_details($pdo, $organizationId, $u);
        fputcsv($output, [
            $details['id'],
            $details['firstName'],
            $details['lastName'],
            $details['name'],
            $details['email'],
            $details['phone'],
            $details['role'],
            $details['department'],
            $details['location'],
            $details['availability'],
            $details['status'],
            $details['assignedLeads'],
            $details['pipelineValue'],
            $details['revenue'],
            $details['target']
        ]);
    }

    fclose($output);
    exit;
}

team_json(false, 'Unknown team action requested.', [], 400);
