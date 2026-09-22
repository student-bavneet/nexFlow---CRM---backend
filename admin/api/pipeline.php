<?php
/**
 * NexFlow CRM - Sales Pipeline API
 * Multi-tenant, organization-scoped controller for Deals, Pipeline Stages,
 * Drag-and-Drop Stage Transitions, Activities, Tasks, Notes, Meetings, and CSV Exports.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function pipeline_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authenticate user
$currentUser = nexflow_current_user();
if (!$currentUser) {
    pipeline_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId = (int)$currentUser['id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    pipeline_json(false, 'Database connection failed: ' . $e->getMessage(), [], 500);
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
function get_deal_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

// Deterministic avatar colors
function get_deal_avatar_color(int $id): string
{
    $colors = ['#7C3AED', '#0284C7', '#10B981', '#F59E0B', '#6366F1', '#EC4899', '#14B8A6', '#8B5CF6'];
    return $colors[$id % count($colors)];
}

// Log deal activity into team_activities
function log_deal_activity(PDO $pdo, int $orgId, int $dealId, ?int $userId, string $type, string $title, ?string $desc = null, int $createdBy = 1, ?string $createdAt = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities 
            (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'deals', ?, ?, ?)
        ");
        $dateVal = $createdAt ?: date('Y-m-d H:i:s');
        $stmt->execute([$orgId, $userId ?? $createdBy, $type, $title, $desc, $dealId, $createdBy, $dateVal]);
    } catch (Throwable $e) {
        // Activity logging failure should not break main operation
    }
}

// Normalize stage key (e.g. 'Closed Won' -> 'closed_won', 'Prospect' -> 'prospect')
function normalize_stage_key(string $name): string
{
    return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_'));
}

// Permission shortcuts
$canView   = hasPermission('pipeline', 'view')   || hasPermission('deals', 'view');
$canCreate = hasPermission('pipeline', 'create') || hasPermission('deals', 'create');
$canEdit   = hasPermission('pipeline', 'edit')   || hasPermission('deals', 'edit');
$canDelete = hasPermission('pipeline', 'delete') || hasPermission('deals', 'delete');
$canAssign = hasPermission('pipeline', 'assign') || hasPermission('deals', 'assign');
$canExport = hasPermission('deals', 'export')     || hasPermission('pipeline', 'view');

if (!$canView && $action !== 'export_csv') {
    pipeline_json(false, 'Permission denied. You cannot view Sales Pipeline.', [], 403);
}

// ==========================================
// 1. BOOTSTRAP ACTION
// ==========================================
if ($action === 'bootstrap') {
    // 1. Fetch real active stages for the current organization
    $stagesStmt = $pdo->prepare("
        SELECT id, organization_id, name, sort_order, color, probability, is_system, is_active
        FROM pipeline_stages
        WHERE organization_id = ? AND is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $stagesStmt->execute([$organizationId]);
    $stagesRows = $stagesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map stages with key and empty deals bucket
    $stages = [];
    $stageKeyMap = []; // Maps normalized key and stage name to stage info
    foreach ($stagesRows as $s) {
        $key = normalize_stage_key($s['name']);
        $stageObj = [
            'id'          => $key,
            'db_id'       => (int)$s['id'],
            'name'        => $s['name'],
            'label'       => $s['name'],
            'color'       => $s['color'] ?: '#2563EB',
            'probability' => (float)$s['probability'],
            'is_system'   => (int)$s['is_system'],
            'sort_order'  => (int)$s['sort_order'],
            'deals'       => [],
            'count'       => 0,
            'total_value' => 0.0
        ];
        $stages[$key] = $stageObj;
        $stageKeyMap[$key] = $key;
        $stageKeyMap[strtolower($s['name'])] = $key;
    }

    // 2. Fetch all real active users for owner dropdowns
    $usersStmt = $pdo->prepare("
        SELECT id, name, email, role, photo_path, status
        FROM users
        WHERE organization_id = ? AND status = 'active'
        ORDER BY name ASC
    ");
    $usersStmt->execute([$organizationId]);
    $activeUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    $usersMap = [];
    foreach ($activeUsers as $u) {
        $usersMap[(int)$u['id']] = $u['name'];
    }

    // 3. Fetch real deals for this organization
    $dealsStmt = $pdo->prepare("
        SELECT d.*, u.name AS owner_name, u.email AS owner_email
        FROM deals d
        LEFT JOIN users u ON u.id = d.assigned_to AND u.organization_id = d.organization_id
        WHERE d.organization_id = ?
        ORDER BY d.id DESC
    ");
    $dealsStmt->execute([$organizationId]);
    $allDeals = $dealsStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalDealsCount = count($allDeals);
    $totalPipelineValue = 0.0;
    $openDealsCount = 0;
    $wonValue = 0.0;
    $weightedValue = 0.0;

    $formattedDeals = [];

    foreach ($allDeals as $deal) {
        $val = (float)$deal['value'];
        $stgRaw = trim((string)$deal['stage']);
        $stgKey = normalize_stage_key($stgRaw);

        // Match to configured stage key, fallback to raw key
        $matchedKey = $stageKeyMap[$stgKey] ?? ($stageKeyMap[strtolower($stgRaw)] ?? $stgKey);

        // Effective probability: deal's probability if set, else stage's probability, else 50%
        $stageProb = isset($stages[$matchedKey]) ? (float)$stages[$matchedKey]['probability'] : 50.0;
        $effProb = ($deal['probability'] !== null) ? (float)$deal['probability'] : $stageProb;

        $status = strtolower($deal['status'] ?: 'open');

        // Total pipeline value includes all non-lost deals (or all open/won deals)
        if ($status !== 'lost') {
            $totalPipelineValue += $val;
        }

        if ($status === 'open') {
            $openDealsCount++;
        } elseif ($status === 'won') {
            $wonValue += $val;
        }

        // Weighted value calculation
        $weightedValue += ($val * ($effProb / 100.0));

        $ownerName = $deal['owner_name'] ?: ($usersMap[(int)$deal['assigned_to']] ?? 'Unassigned');
        $initials = get_deal_initials($ownerName);
        $avatarColor = get_deal_avatar_color((int)($deal['assigned_to'] ?: $deal['id']));

        $closeDateText = !empty($deal['close_date']) ? date('M j, Y', strtotime($deal['close_date'])) : '';
        $isoCloseDate = !empty($deal['close_date']) ? date('Y-m-d', strtotime($deal['close_date'])) : '';

        $dealItem = [
            'id'           => (int)$deal['id'],
            'deal_code'    => $deal['deal_code'] ?: ('DL-' . str_pad($deal['id'], 3, '0', STR_PAD_LEFT)),
            'name'         => $deal['name'],
            'contact_id'   => $deal['contact_id'] ? (int)$deal['contact_id'] : null,
            'contact_name' => $deal['contact_name'] ?: '',
            'company'      => $deal['company'] ?: '',
            'value'        => $val,
            'stage'        => $matchedKey,
            'stage_name'   => $stages[$matchedKey]['name'] ?? $stgRaw,
            'status'       => $status,
            'probability'  => $effProb,
            'source'       => $deal['source'] ?: '',
            'priority'     => $deal['priority'] ?: 'Medium',
            'product'      => $deal['product'] ?: '',
            'description'  => $deal['description'] ?: '',
            'assigned_to'  => $deal['assigned_to'] ? (int)$deal['assigned_to'] : null,
            'owner_name'   => $ownerName,
            'initials'     => $initials,
            'avatar_color' => $avatarColor,
            'close_date'   => $closeDateText,
            'iso_close_date' => $isoCloseDate,
            'created_at'   => $deal['created_at'],
            'updated_at'   => $deal['updated_at']
        ];

        $formattedDeals[] = $dealItem;

        // Group into stage if matched
        if (isset($stages[$matchedKey])) {
            $stages[$matchedKey]['deals'][] = $dealItem;
            $stages[$matchedKey]['count']++;
            $stages[$matchedKey]['total_value'] += $val;
        }
    }

    // Convert stages to indexed list for frontend
    $stagesList = array_values($stages);

    // Fetch active contacts with their phone, email, and authoritative contact_companies
    $contactsStmt = $pdo->prepare("
        SELECT id, name, email, phone
        FROM contacts
        WHERE organization_id = ?
        ORDER BY name ASC
    ");
    $contactsStmt->execute([$organizationId]);
    $rawContacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);

    $ccStmt = $pdo->prepare("
        SELECT cc.contact_id, comp.id AS company_id, comp.name AS company_name
        FROM contact_companies cc
        JOIN companies comp ON comp.id = cc.company_id AND comp.organization_id = cc.organization_id
        WHERE cc.organization_id = ?
        ORDER BY comp.name ASC
    ");
    $ccStmt->execute([$organizationId]);
    $ccRows = $ccStmt->fetchAll(PDO::FETCH_ASSOC);

    $companiesByContact = [];
    foreach ($ccRows as $row) {
        $cid = (int)$row['contact_id'];
        if (!isset($companiesByContact[$cid])) {
            $companiesByContact[$cid] = [];
        }
        $companiesByContact[$cid][] = [
            'id'   => (int)$row['company_id'],
            'name' => $row['company_name']
        ];
    }

    $activeContacts = [];
    foreach ($rawContacts as $c) {
        $cid = (int)$c['id'];
        $activeContacts[] = [
            'id'        => $cid,
            'name'      => $c['name'],
            'email'     => $c['email'] ?: '',
            'phone'     => $c['phone'] ?: '',
            'companies' => $companiesByContact[$cid] ?? []
        ];
    }

    // Fetch active companies for autocomplete
    $companiesStmt = $pdo->prepare("
        SELECT id, name
        FROM companies
        WHERE organization_id = ?
        ORDER BY name ASC
    ");
    $companiesStmt->execute([$organizationId]);
    $activeCompanies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

    pipeline_json(true, 'Sales Pipeline bootstrapped successfully.', [
        'kpis' => [
            'total_deals'    => $totalDealsCount,
            'pipeline_value' => $totalPipelineValue,
            'open_deals'     => $openDealsCount,
            'won_value'      => $wonValue,
            'weighted_value' => $weightedValue
        ],
        'stages'       => $stagesList,
        'deals'        => $formattedDeals,
        'users'        => $activeUsers,
        'contacts'     => $activeContacts,
        'companies'    => $activeCompanies,
        'permissions'  => [
            'can_create' => $canCreate,
            'can_edit'   => $canEdit,
            'can_delete' => $canDelete,
            'can_assign' => $canAssign,
            'can_export' => $canExport
        ],
        'current_user' => [
            'id'              => $currentUserId,
            'name'            => $currentUser['name'],
            'organization_id' => $organizationId
        ]
    ]);
}

// ==========================================
// 2. CREATE DEAL ACTION
// ==========================================
if ($action === 'create_deal') {
    if (!$canCreate) {
        pipeline_json(false, 'Permission denied. You cannot create deals.', [], 403);
    }

    $name = trim($input['name'] ?? '');
    if ($name === '') {
        pipeline_json(false, 'Deal Name is required.', [], 422);
    }

    $contactId   = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
    $contactName = trim($input['contact_name'] ?? '');
    $company     = trim($input['company'] ?? '');
    $value       = max(0.0, (float)($input['value'] ?? 0));
    $stageInput  = trim($input['stage'] ?? '');
    $probability = isset($input['probability']) && $input['probability'] !== '' ? min(100.0, max(0.0, (float)$input['probability'])) : null;
    $closeDate   = !empty($input['close_date']) ? date('Y-m-d', strtotime($input['close_date'])) : null;
    $source      = !empty($input['source']) ? trim($input['source']) : null;
    $priority    = !empty($input['priority']) ? trim($input['priority']) : null;
    $product     = !empty($input['product']) ? trim($input['product']) : null;
    $description = !empty($input['description']) ? trim($input['description']) : null;

    // Resolve contact_id and consistency with contact_name & company
    if ($contactId) {
        $cCheck = $pdo->prepare("SELECT id, name, company_name FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
        $cCheck->execute([$contactId, $organizationId]);
        $foundContact = $cCheck->fetch(PDO::FETCH_ASSOC);
        if ($foundContact) {
            $contactName = $foundContact['name'];
            if ($company === '' && !empty($foundContact['company_name'])) {
                $company = $foundContact['company_name'];
            }
        } else {
            $contactId = null;
        }
    }
    if (!$contactId && $contactName !== '') {
        $cCheck = $pdo->prepare("SELECT id, name, company_name FROM contacts WHERE organization_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        $cCheck->execute([$organizationId, $contactName]);
        $foundContact = $cCheck->fetch(PDO::FETCH_ASSOC);
        if ($foundContact) {
            $contactId = (int)$foundContact['id'];
            $contactName = $foundContact['name'];
            if ($company === '' && !empty($foundContact['company_name'])) {
                $company = $foundContact['company_name'];
            }
        }
    }

    // Validate owner belongs to current organization
    $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null;
    if ($assignedTo) {
        $ownerCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $ownerCheck->execute([$assignedTo, $organizationId]);
        if (!$ownerCheck->fetchColumn()) {
            $assignedTo = null; // Unassigned or fallback
        }
    }

    // Determine actual stage name in DB
    $stageName = 'Prospect';
    if ($stageInput !== '') {
        $stgStmt = $pdo->prepare("SELECT name FROM pipeline_stages WHERE organization_id = ? AND (LOWER(name) = ? OR LOWER(REPLACE(name, ' ', '_')) = ?) LIMIT 1");
        $stgStmt->execute([$organizationId, strtolower($stageInput), strtolower(str_replace(' ', '_', $stageInput))]);
        $foundStage = $stgStmt->fetchColumn();
        if ($foundStage) {
            $stageName = $foundStage;
        } else {
            $stageName = $stageInput;
        }
    }

    // Determine status
    $status = 'open';
    if (stripos($stageName, 'won') !== false) {
        $status = 'won';
    } elseif (stripos($stageName, 'lost') !== false) {
        $status = 'lost';
    }

    // Insert into deals
    $stmt = $pdo->prepare("
        INSERT INTO deals 
        (organization_id, name, contact_id, contact_name, company, stage, value, probability, source, priority, product, description, status, assigned_to, close_date, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $organizationId,
        $name,
        $contactId,
        $contactName ?: null,
        $company ?: null,
        $stageName,
        $value,
        $probability,
        $source,
        $priority,
        $product,
        $description,
        $status,
        $assignedTo,
        $closeDate
    ]);
    $newDealId = (int)$pdo->lastInsertId();

    // Generate deal code
    $dealCode = 'DL-' . str_pad($newDealId, 3, '0', STR_PAD_LEFT);
    $upCode = $pdo->prepare("UPDATE deals SET deal_code = ? WHERE id = ?");
    $upCode->execute([$dealCode, $newDealId]);

    // Log activity
    log_deal_activity(
        $pdo,
        $organizationId,
        $newDealId,
        $currentUserId,
        'Deal Created',
        "Deal created: $name",
        "Initial stage: $stageName, Value: $" . number_format($value),
        $currentUserId
    );

    pipeline_json(true, 'Deal created successfully.', [
        'id'        => $newDealId,
        'deal_code' => $dealCode,
        'name'      => $name
    ], 201);
}

// ==========================================
// 3. UPDATE DEAL ACTION
// ==========================================
if ($action === 'update_deal') {
    if (!$canEdit) {
        pipeline_json(false, 'Permission denied. You cannot edit deals.', [], 403);
    }

    $dealId = (int)($input['id'] ?? 0);
    if (!$dealId) {
        pipeline_json(false, 'Invalid Deal ID.', [], 422);
    }

    // Verify tenant ownership
    $checkStmt = $pdo->prepare("SELECT * FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
    $checkStmt->execute([$dealId, $organizationId]);
    $existingDeal = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingDeal) {
        pipeline_json(false, 'Deal not found in current organization.', [], 404);
    }

    $name        = trim($input['name'] ?? $existingDeal['name']);
    $contactName = isset($input['contact_name']) ? trim($input['contact_name']) : $existingDeal['contact_name'];
    $contactId   = isset($input['contact_id']) ? ((int)$input['contact_id'] ?: null) : ($existingDeal['contact_id'] ? (int)$existingDeal['contact_id'] : null);
    $company     = isset($input['company']) ? trim($input['company']) : $existingDeal['company'];
    $value       = isset($input['value']) ? max(0.0, (float)$input['value']) : (float)$existingDeal['value'];
    $stageInput  = isset($input['stage']) ? trim($input['stage']) : $existingDeal['stage'];
    $probability = isset($input['probability']) && $input['probability'] !== '' ? min(100.0, max(0.0, (float)$input['probability'])) : $existingDeal['probability'];
    $closeDate   = !empty($input['close_date']) ? date('Y-m-d', strtotime($input['close_date'])) : $existingDeal['close_date'];
    $source      = isset($input['source']) ? trim($input['source']) : $existingDeal['source'];
    $priority    = isset($input['priority']) ? trim($input['priority']) : $existingDeal['priority'];
    $product     = isset($input['product']) ? trim($input['product']) : $existingDeal['product'];
    $description = isset($input['description']) ? trim($input['description']) : $existingDeal['description'];

    // Resolve contact_id if changed or needed
    if ($contactId) {
        $cCheck = $pdo->prepare("SELECT id, name FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
        $cCheck->execute([$contactId, $organizationId]);
        $foundContact = $cCheck->fetch(PDO::FETCH_ASSOC);
        if ($foundContact) {
            $contactName = $foundContact['name'];
        } else {
            $contactId = null;
        }
    }
    if (!$contactId && $contactName !== '') {
        $cCheck = $pdo->prepare("SELECT id, name FROM contacts WHERE organization_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        $cCheck->execute([$organizationId, $contactName]);
        $foundContact = $cCheck->fetch(PDO::FETCH_ASSOC);
        if ($foundContact) {
            $contactId = (int)$foundContact['id'];
            $contactName = $foundContact['name'];
        }
    }

    // Resolve stage name
    $stageName = $existingDeal['stage'];
    if ($stageInput !== '') {
        $stgStmt = $pdo->prepare("SELECT name FROM pipeline_stages WHERE organization_id = ? AND (LOWER(name) = ? OR LOWER(REPLACE(name, ' ', '_')) = ?) LIMIT 1");
        $stgStmt->execute([$organizationId, strtolower($stageInput), strtolower(str_replace(' ', '_', $stageInput))]);
        $foundStage = $stgStmt->fetchColumn();
        if ($foundStage) {
            $stageName = $foundStage;
        } else {
            $stageName = $stageInput;
        }
    }

    // Owner check
    $assignedTo = isset($input['assigned_to']) ? (int)$input['assigned_to'] : $existingDeal['assigned_to'];
    if ($assignedTo) {
        $ownerCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $ownerCheck->execute([$assignedTo, $organizationId]);
        if (!$ownerCheck->fetchColumn()) {
            $assignedTo = $existingDeal['assigned_to'];
        }
    }

    // Status check
    $status = $existingDeal['status'];
    if (stripos($stageName, 'won') !== false) {
        $status = 'won';
    } elseif (stripos($stageName, 'lost') !== false) {
        $status = 'lost';
    } elseif ($status !== 'open' && stripos($stageName, 'won') === false && stripos($stageName, 'lost') === false) {
        $status = 'open';
    }

    $updateStmt = $pdo->prepare("
        UPDATE deals SET
            name = ?,
            contact_id = ?,
            contact_name = ?,
            company = ?,
            stage = ?,
            value = ?,
            probability = ?,
            source = ?,
            priority = ?,
            product = ?,
            description = ?,
            status = ?,
            assigned_to = ?,
            close_date = ?,
            updated_at = NOW()
        WHERE id = ? AND organization_id = ?
    ");
    $updateStmt->execute([
        $name,
        $contactId,
        $contactName ?: null,
        $company ?: null,
        $stageName,
        $value,
        $probability,
        $source ?: null,
        $priority ?: null,
        $product ?: null,
        $description ?: null,
        $status,
        $assignedTo ?: null,
        $closeDate,
        $dealId,
        $organizationId
    ]);

    // Log change
    if ($existingDeal['stage'] !== $stageName) {
        log_deal_activity(
            $pdo,
            $organizationId,
            $dealId,
            $currentUserId,
            'Stage Change',
            "Stage updated to $stageName",
            "Transitioned from {$existingDeal['stage']} → $stageName",
            $currentUserId
        );
    } else {
        log_deal_activity(
            $pdo,
            $organizationId,
            $dealId,
            $currentUserId,
            'Deal Updated',
            "Deal details updated",
            "Updated deal properties",
            $currentUserId
        );
    }

    pipeline_json(true, 'Deal updated successfully.');
}

// ==========================================
// 4. UPDATE STAGE (DRAG & DROP / MODAL)
// ==========================================
if ($action === 'update_stage') {
    if (!$canEdit) {
        pipeline_json(false, 'Permission denied. You cannot move deals between stages.', [], 403);
    }

    $dealId = (int)($input['id'] ?? 0);
    $targetStageInput = trim($input['stage'] ?? '');

    if (!$dealId || $targetStageInput === '') {
        pipeline_json(false, 'Deal ID and target stage are required.', [], 422);
    }

    // Verify tenant deal
    $stmt = $pdo->prepare("SELECT * FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
    $stmt->execute([$dealId, $organizationId]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$deal) {
        pipeline_json(false, 'Deal not found.', [], 404);
    }

    // Verify target stage in organization
    $stgStmt = $pdo->prepare("SELECT name FROM pipeline_stages WHERE organization_id = ? AND (LOWER(name) = ? OR LOWER(REPLACE(name, ' ', '_')) = ?) LIMIT 1");
    $stgStmt->execute([$organizationId, strtolower($targetStageInput), strtolower(str_replace(' ', '_', $targetStageInput))]);
    $targetStageName = $stgStmt->fetchColumn();
    if (!$targetStageName) {
        $targetStageName = $targetStageInput; // fallback
    }

    $oldStageName = $deal['stage'];
    $newStatus = 'open';
    $closeDate = $deal['close_date'];
    $lostReason = $deal['lost_reason'];

    // Handle Closed Won
    if (stripos($targetStageName, 'won') !== false || normalize_stage_key($targetStageName) === 'closed_won') {
        $newStatus = 'won';
        if (!empty($input['close_date'])) {
            $closeDate = date('Y-m-d', strtotime($input['close_date']));
        } elseif (empty($closeDate)) {
            $closeDate = date('Y-m-d');
        }

        // Optional closing note
        $closingNote = trim($input['note'] ?? '');
        if ($closingNote !== '') {
            $noteStmt = $pdo->prepare("INSERT INTO team_notes (organization_id, related_type, related_id, created_by, title, content, is_pinned, created_at, updated_at) VALUES (?, 'deals', ?, ?, 'Closing Note', ?, 0, NOW(), NOW())");
            $noteStmt->execute([$organizationId, $dealId, $currentUserId, $closingNote]);
        }
    } elseif (stripos($targetStageName, 'lost') !== false || normalize_stage_key($targetStageName) === 'closed_lost') {
        $newStatus = 'lost';
        if (!empty($input['lost_reason'])) {
            $lostReason = trim($input['lost_reason']);
        }
    } else {
        $newStatus = 'open';
    }

    $upStmt = $pdo->prepare("
        UPDATE deals SET
            stage = ?,
            status = ?,
            close_date = ?,
            lost_reason = ?,
            updated_at = NOW()
        WHERE id = ? AND organization_id = ?
    ");
    $upStmt->execute([$targetStageName, $newStatus, $closeDate, $lostReason, $dealId, $organizationId]);

    // Log activity
    log_deal_activity(
        $pdo,
        $organizationId,
        $dealId,
        $currentUserId,
        'Stage Change',
        "Stage changed to $targetStageName",
        "Moved from $oldStageName → $targetStageName",
        $currentUserId
    );

    pipeline_json(true, "Deal moved to $targetStageName.", [
        'id'          => $dealId,
        'old_stage'   => $oldStageName,
        'new_stage'   => $targetStageName,
        'stage_key'   => normalize_stage_key($targetStageName),
        'status'      => $newStatus
    ]);
}

// ==========================================
// 5. UPDATE OWNER
// ==========================================
if ($action === 'update_owner') {
    if (!$canAssign) {
        pipeline_json(false, 'Permission denied. You cannot assign deal owners.', [], 403);
    }

    $dealId  = (int)($input['id'] ?? 0);
    $ownerId = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : (!empty($input['user_id']) ? (int)$input['user_id'] : null);

    if (!$dealId) {
        pipeline_json(false, 'Invalid Deal ID.', [], 422);
    }

    // Verify tenant deal
    $checkDeal = $pdo->prepare("SELECT id, assigned_to FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
    $checkDeal->execute([$dealId, $organizationId]);
    if (!$checkDeal->fetch()) {
        pipeline_json(false, 'Deal not found.', [], 404);
    }

    $newOwnerName = 'Unassigned';
    if ($ownerId) {
        $checkUser = $pdo->prepare("SELECT name FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $checkUser->execute([$ownerId, $organizationId]);
        $foundName = $checkUser->fetchColumn();
        if (!$foundName) {
            pipeline_json(false, 'Selected owner is not an active user in this organization.', [], 422);
        }
        $newOwnerName = $foundName;
    }

    $upStmt = $pdo->prepare("UPDATE deals SET assigned_to = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
    $upStmt->execute([$ownerId, $dealId, $organizationId]);

    log_deal_activity(
        $pdo,
        $organizationId,
        $dealId,
        $currentUserId,
        'Owner Reassigned',
        "Deal assigned to $newOwnerName",
        "Owner updated to $newOwnerName",
        $currentUserId
    );

    pipeline_json(true, "Owner updated to $newOwnerName.", [
        'id'          => $dealId,
        'assigned_to' => $ownerId,
        'owner_name'  => $newOwnerName
    ]);
}

// ==========================================
// 6. DELETE DEAL
// ==========================================
if ($action === 'delete_deal') {
    if (!$canDelete) {
        pipeline_json(false, 'Permission denied. You cannot delete deals.', [], 403);
    }

    $dealId = (int)($input['id'] ?? 0);
    if (!$dealId) {
        pipeline_json(false, 'Invalid Deal ID.', [], 422);
    }

    // Check deal exists and belongs to tenant
    $checkStmt = $pdo->prepare("SELECT id, name FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
    $checkStmt->execute([$dealId, $organizationId]);
    $deal = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$deal) {
        pipeline_json(false, 'Deal not found in current organization.', [], 404);
    }

    // Clean up related polymorphic records in team_activities, tasks, team_notes, calendar_events
    $delAct = $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'deals' AND related_entity_id = ?");
    $delAct->execute([$organizationId, $dealId]);

    $delTasks = $pdo->prepare("DELETE FROM tasks WHERE organization_id = ? AND related_type = 'deals' AND related_id = ?");
    $delTasks->execute([$organizationId, $dealId]);

    $delNotes = $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'deals' AND related_id = ?");
    $delNotes->execute([$organizationId, $dealId]);

    $delCal = $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = ? AND related_type = 'deals' AND related_id = ?");
    $delCal->execute([$organizationId, $dealId]);

    // Delete deal record
    $delDeal = $pdo->prepare("DELETE FROM deals WHERE id = ? AND organization_id = ?");
    $delDeal->execute([$dealId, $organizationId]);

    pipeline_json(true, "Deal '{$deal['name']}' deleted successfully.");
}

// ==========================================
// 7. DEAL DRAWER (DETAILS & TABS)
// ==========================================
if ($action === 'deal_drawer') {
    $dealId = (int)($_GET['id'] ?? ($input['id'] ?? 0));
    if (!$dealId) {
        pipeline_json(false, 'Invalid Deal ID.', [], 422);
    }

    $stmt = $pdo->prepare("
        SELECT d.*, u.name AS owner_name, u.email AS owner_email, u.photo_path AS owner_avatar
        FROM deals d
        LEFT JOIN users u ON u.id = d.assigned_to AND u.organization_id = d.organization_id
        WHERE d.id = ? AND d.organization_id = ?
        LIMIT 1
    ");
    $stmt->execute([$dealId, $organizationId]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$deal) {
        pipeline_json(false, 'Deal not found in current organization.', [], 404);
    }

    $dealCompanyId = null;
    if (!empty($deal['contact_id'])) {
        $ccStmt = $pdo->prepare("SELECT company_id FROM contact_companies WHERE contact_id = ? AND organization_id = ? LIMIT 1");
        $ccStmt->execute([(int)$deal['contact_id'], $organizationId]);
        $dealCompanyId = $ccStmt->fetchColumn() ?: null;
        if (!$dealCompanyId) {
            $cStmt = $pdo->prepare("SELECT company_id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
            $cStmt->execute([(int)$deal['contact_id'], $organizationId]);
            $dealCompanyId = $cStmt->fetchColumn() ?: null;
        }
    }

    // 1. Activities
    $actStmt = $pdo->prepare("
        SELECT a.id, a.activity_type, a.title, a.description, a.created_at, COALESCE(u.name, u2.name) AS author_name, a.user_id, a.created_by
        FROM team_activities a
        LEFT JOIN users u ON u.id = a.user_id
        LEFT JOIN users u2 ON u2.id = a.created_by
        WHERE a.organization_id = ? AND a.related_entity = 'deals' AND a.related_entity_id = ?
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $actStmt->execute([$organizationId, $dealId]);
    $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Tasks
    $taskStmt = $pdo->prepare("
        SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.assigned_to, u.name AS assignee_name
        FROM tasks t
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE t.organization_id = ? AND t.related_type = 'deals' AND t.related_id = ?
        ORDER BY t.due_date ASC, t.id DESC
    ");
    $taskStmt->execute([$organizationId, $dealId]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Notes
    $noteStmt = $pdo->prepare("
        SELECT n.id, n.title, n.content, n.is_pinned, n.created_at, u.name AS author_name
        FROM team_notes n
        LEFT JOIN users u ON u.id = n.created_by
        WHERE n.organization_id = ? AND n.related_type = 'deals' AND n.related_id = ?
        ORDER BY n.is_pinned DESC, n.created_at DESC, n.id DESC
    ");
    $noteStmt->execute([$organizationId, $dealId]);
    $notes = $noteStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Meetings / Calendar Events
    $calStmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.event_type, c.start_time, c.end_time, c.status, u.name AS user_name
        FROM calendar_events c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.organization_id = ? AND c.related_type = 'deals' AND c.related_id = ?
        ORDER BY c.start_time ASC, c.id DESC
    ");
    $calStmt->execute([$organizationId, $dealId]);
    $meetings = $calStmt->fetchAll(PDO::FETCH_ASSOC);

    $ownerName = $deal['owner_name'] ?: 'Unassigned';

    pipeline_json(true, 'Deal details retrieved.', [
        'deal' => [
            'id'             => (int)$deal['id'],
            'deal_code'      => $deal['deal_code'] ?: ('DL-' . str_pad($deal['id'], 3, '0', STR_PAD_LEFT)),
            'name'           => $deal['name'],
            'contact_id'     => $deal['contact_id'] ? (int)$deal['contact_id'] : null,
            'contact_name'   => $deal['contact_name'] ?: '',
            'company'        => $deal['company'] ?: '',
            'company_id'     => $dealCompanyId ? (int)$dealCompanyId : null,
            'value'          => (float)$deal['value'],
            'stage'          => normalize_stage_key($deal['stage']),
            'stage_name'     => $deal['stage'],
            'status'         => $deal['status'] ?: 'open',
            'probability'    => $deal['probability'] !== null ? (float)$deal['probability'] : null,
            'source'         => $deal['source'] ?: '',
            'priority'       => $deal['priority'] ?: 'Medium',
            'product'        => $deal['product'] ?: '',
            'description'    => $deal['description'] ?: '',
            'assigned_to'    => $deal['assigned_to'] ? (int)$deal['assigned_to'] : null,
            'owner_name'     => $ownerName,
            'initials'       => get_deal_initials($ownerName),
            'close_date'     => !empty($deal['close_date']) ? date('M j, Y', strtotime($deal['close_date'])) : '',
            'iso_close_date' => !empty($deal['close_date']) ? date('Y-m-d', strtotime($deal['close_date'])) : '',
            'created_at'     => date('M j, Y g:i A', strtotime($deal['created_at'])),
            'updated_at'     => date('M j, Y g:i A', strtotime($deal['updated_at']))
        ],
        'activities' => $activities,
        'tasks'      => $tasks,
        'notes'      => $notes,
        'meetings'   => $meetings
    ]);
}

// ==========================================
// 8. LOG ACTIVITY ACTION
// ==========================================
if ($action === 'log_activity') {
    $dealId      = (int)($input['deal_id'] ?? 0);
    $type        = trim($input['type'] ?? 'Note');
    $title       = trim($input['title'] ?? '');
    $desc        = trim($input['description'] ?? '');
    $performedBy = !empty($input['performed_by']) ? (int)$input['performed_by'] : (!empty($input['user_id']) ? (int)$input['user_id'] : $currentUserId);

    if (!$dealId || $title === '') {
        pipeline_json(false, 'Deal ID and activity title are required.', [], 422);
    }

    // Verify deal belongs to tenant
    $checkStmt = $pdo->prepare("SELECT id FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
    $checkStmt->execute([$dealId, $organizationId]);
    if (!$checkStmt->fetch()) {
        pipeline_json(false, 'Deal not found in current organization.', [], 404);
    }

    // Validate tenant isolation: user must belong to current organization and be active
    $uCheck = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
    $uCheck->execute([$performedBy, $organizationId]);
    $validUser = $uCheck->fetch(PDO::FETCH_ASSOC);
    if (!$validUser) {
        $performedBy = $currentUserId;
    }

    $createdAt = null;
    if (!empty($input['date'])) {
        $timeStr = !empty($input['time']) ? $input['time'] : date('H:i:s');
        $parsedTime = strtotime($input['date'] . ' ' . $timeStr);
        if ($parsedTime !== false) {
            $createdAt = date('Y-m-d H:i:s', $parsedTime);
        }
    }

    log_deal_activity($pdo, $organizationId, $dealId, $performedBy, $type, $title, $desc, $currentUserId, $createdAt);
    pipeline_json(true, 'Activity logged successfully.');
}

// ==========================================
// 9. TASKS ACTIONS (CREATE, TOGGLE, DELETE)
// ==========================================
if ($action === 'create_task') {
    $dealId      = (int)($input['deal_id'] ?? 0);
    $title       = trim($input['title'] ?? '');
    $desc        = trim($input['description'] ?? '');
    $dueDate     = !empty($input['due_date']) ? date('Y-m-d H:i:s', strtotime($input['due_date'] . ' ' . ($input['due_time'] ?? '09:00:00'))) : null;
    $priority    = strtolower(trim($input['priority'] ?? 'medium'));
    $assignedTo  = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : $currentUserId;

    if (!$dealId || $title === '') {
        pipeline_json(false, 'Deal ID and Task title are required.', [], 422);
    }

    $checkStmt = $pdo->prepare("SELECT id, contact_id, company FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
    $checkStmt->execute([$dealId, $organizationId]);
    $dealRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$dealRow) {
        pipeline_json(false, 'Deal not found.', [], 404);
    }

    $dealContactId = !empty($dealRow['contact_id']) ? (int)$dealRow['contact_id'] : null;
    $dealCompanyId = null;

    if ($dealContactId) {
        $cStmt = $pdo->prepare("SELECT company_id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
        $cStmt->execute([$dealContactId, $organizationId]);
        $foundCompanyId = $cStmt->fetchColumn();
        if ($foundCompanyId) {
            $dealCompanyId = (int)$foundCompanyId;
        }
    }
    if (!$dealCompanyId && !empty($dealRow['company'])) {
        $compStmt = $pdo->prepare("SELECT id FROM companies WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND organization_id = ? LIMIT 1");
        $compStmt->execute([$dealRow['company'], $organizationId]);
        $foundCompId = $compStmt->fetchColumn();
        if ($foundCompId) {
            $dealCompanyId = (int)$foundCompId;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO tasks (organization_id, title, description, status, priority, due_date, assigned_to, deal_id, contact_id, company_id, related_type, related_id, created_at, updated_at)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, 'deals', ?, NOW(), NOW())
    ");
    $stmt->execute([$organizationId, $title, $desc ?: null, $priority, $dueDate, $assignedTo, $dealId, $dealContactId, $dealCompanyId, $dealId]);

    pipeline_json(true, 'Task added successfully.');
}

if ($action === 'update_task_status') {
    $taskId    = (int)($input['task_id'] ?? 0);
    $completed = !empty($input['completed']);
    $newStatus = $completed ? 'completed' : 'pending';

    $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ? AND related_type = 'deals'");
    $stmt->execute([$newStatus, $taskId, $organizationId]);

    pipeline_json(true, 'Task status updated.');
}

if ($action === 'delete_task') {
    $taskId = (int)($input['task_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND organization_id = ? AND related_type = 'deals'");
    $stmt->execute([$taskId, $organizationId]);

    pipeline_json(true, 'Task deleted successfully.');
}

// ==========================================
// 10. NOTES ACTIONS (CREATE, PIN, DELETE)
// ==========================================
if ($action === 'create_note') {
    $dealId  = (int)($input['deal_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    $type    = trim($input['type'] ?? 'General');

    if (!$dealId || $content === '') {
        pipeline_json(false, 'Deal ID and Note content are required.', [], 422);
    }

    $checkStmt = $pdo->prepare("SELECT id FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
    $checkStmt->execute([$dealId, $organizationId]);
    if (!$checkStmt->fetch()) {
        pipeline_json(false, 'Deal not found.', [], 404);
    }

    $stmt = $pdo->prepare("
        INSERT INTO team_notes (organization_id, related_type, related_id, created_by, title, content, is_pinned, created_at, updated_at)
        VALUES (?, 'deals', ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $stmt->execute([$organizationId, $dealId, $currentUserId, $type, $content]);

    pipeline_json(true, 'Note saved successfully.');
}

if ($action === 'toggle_note_pin') {
    $noteId = (int)($input['note_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE team_notes SET is_pinned = NOT is_pinned, updated_at = NOW() WHERE id = ? AND organization_id = ? AND related_type = 'deals'");
    $stmt->execute([$noteId, $organizationId]);

    pipeline_json(true, 'Note pin toggled.');
}

if ($action === 'delete_note') {
    $noteId = (int)($input['note_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'deals'");
    $stmt->execute([$noteId, $organizationId]);

    pipeline_json(true, 'Note deleted successfully.');
}

// ==========================================
// 11. EXPORT CSV
// ==========================================
if ($action === 'export_csv') {
    if (!$canExport) {
        pipeline_json(false, 'Permission denied. You cannot export deals.', [], 403);
    }

    $stmt = $pdo->prepare("
        SELECT d.deal_code, d.name, d.contact_name, d.company, d.stage, d.value, d.probability, d.source, d.priority, d.product, d.status, u.name AS owner_name, d.close_date, d.created_at
        FROM deals d
        LEFT JOIN users u ON u.id = d.assigned_to AND u.organization_id = d.organization_id
        WHERE d.organization_id = ?
        ORDER BY d.id DESC
    ");
    $stmt->execute([$organizationId]);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_pipeline_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Deal Code', 'Deal Name', 'Contact', 'Company', 'Stage', 'Value ($)', 'Probability (%)', 'Source', 'Priority', 'Product', 'Status', 'Owner', 'Expected Close Date', 'Created At']);

    foreach ($deals as $d) {
        fputcsv($output, [
            $d['deal_code'] ?: '',
            $d['name'] ?: '',
            $d['contact_name'] ?: '',
            $d['company'] ?: '',
            $d['stage'] ?: '',
            $d['value'] ?: '0',
            $d['probability'] ?: '',
            $d['source'] ?: '',
            $d['priority'] ?: '',
            $d['product'] ?: '',
            $d['status'] ?: '',
            $d['owner_name'] ?: 'Unassigned',
            $d['close_date'] ?: '',
            $d['created_at'] ?: ''
        ]);
    }
    fclose($output);
    exit;
}

// ==========================================
// 12. SCHEDULE / CREATE MEETING (CALENDAR EVENT)
// ==========================================
if ($action === 'create_meeting' || $action === 'schedule_meeting') {
    if (!$canEdit) {
        pipeline_json(false, 'Permission denied.', [], 403);
    }

    $dealId       = (int)($input['deal_id'] ?? 0);
    $title        = trim($input['title'] ?? '');
    $date         = trim($input['date'] ?? '');
    $startTimeRaw = trim($input['start_time'] ?? $input['time'] ?? '');
    $endTimeRaw   = trim($input['end_time'] ?? '');
    $duration     = (int)($input['duration'] ?? 30);
    $location     = trim($input['location'] ?? '');
    $desc         = trim($input['description'] ?? '');

    if (!$dealId) {
        pipeline_json(false, 'Deal ID is required.', [], 422);
    }
    if ($title === '') {
        pipeline_json(false, 'Meeting title is required.', [], 422);
    }
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        pipeline_json(false, 'Valid meeting date (YYYY-MM-DD) is required.', [], 422);
    }
    if ($startTimeRaw === '') {
        pipeline_json(false, 'Start time is required.', [], 422);
    }

    // Handle end time or duration
    $startTs = strtotime("{$date} {$startTimeRaw}");
    if ($startTs === false) {
        pipeline_json(false, 'Invalid start time format.', [], 422);
    }

    if ($endTimeRaw !== '') {
        $endTs = strtotime("{$date} {$endTimeRaw}");
        if ($endTs === false) {
            pipeline_json(false, 'Invalid end time format.', [], 422);
        }
    } else {
        $endTs = $startTs + (max(1, $duration) * 60);
    }

    if ($endTs <= $startTs) {
        pipeline_json(false, 'End time must be later than start time.', [], 422);
    }

    $startTime = date('Y-m-d H:i:s', $startTs);
    $endTime   = date('Y-m-d H:i:s', $endTs);

    // ATOMIC TRANSACTION
    $pdo->beginTransaction();
    try {
        // 1. Validate Deal belongs to organization
        $dStmt = $pdo->prepare("SELECT id, contact_id, assigned_to FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
        $dStmt->execute([$dealId, $organizationId]);
        $deal = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$deal) {
            $pdo->rollBack();
            pipeline_json(false, 'Deal not found or access denied.', [], 404);
        }

        // 2. Validate Contact belongs to organization (using exact ID, no name matching)
        $dealContactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : (!empty($deal['contact_id']) ? (int)$deal['contact_id'] : null);
        if ($dealContactId !== null) {
            $ctStmt = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
            $ctStmt->execute([$dealContactId, $organizationId]);
            if (!$ctStmt->fetchColumn()) {
                $pdo->rollBack();
                pipeline_json(false, 'Associated contact does not belong to your organization.', [], 422);
            }
        }

        // 3. Validate Company belongs to organization (using exact ID, no name matching)
        $dealCompanyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;
        if ($dealCompanyId === null && $dealContactId !== null) {
            $ccStmt = $pdo->prepare("SELECT company_id FROM contact_companies WHERE contact_id = ? AND organization_id = ? LIMIT 1");
            $ccStmt->execute([$dealContactId, $organizationId]);
            $foundCompId = $ccStmt->fetchColumn();
            if ($foundCompId) {
                $dealCompanyId = (int)$foundCompId;
            }
        }
        if ($dealCompanyId !== null) {
            $compStmt = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
            $compStmt->execute([$dealCompanyId, $organizationId]);
            if (!$compStmt->fetchColumn()) {
                $pdo->rollBack();
                pipeline_json(false, 'Associated company does not belong to your organization.', [], 422);
            }
        }

        // 4. Validate Owner belongs to organization (using exact ID, no name matching)
        $hostId = !empty($input['user_id']) ? (int)$input['user_id'] : (!empty($deal['assigned_to']) ? (int)$deal['assigned_to'] : $currentUserId);
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $uStmt->execute([$hostId, $organizationId]);
        if (!$uStmt->fetchColumn()) {
            $pdo->rollBack();
            pipeline_json(false, 'Assigned owner does not belong to your organization.', [], 422);
        }

        // 5. Insert ONE calendar_events record
        $stmt = $pdo->prepare("
            INSERT INTO calendar_events (
                organization_id, user_id, contact_id, company_id, deal_id, related_type, related_id,
                title, description, event_type, start_time, end_time, status, location, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 'deals', ?,
                ?, ?, 'Meeting', ?, ?, 'Scheduled', ?, NOW(), NOW()
            )
        ");
        $stmt->execute([
            $organizationId, $hostId, $dealContactId, $dealCompanyId, $dealId, $dealId,
            $title, $desc !== '' ? $desc : null, $startTime, $endTime, $location !== '' ? $location : null
        ]);
        $meetId = (int)$pdo->lastInsertId();

        // 6. Commit transaction
        $pdo->commit();

        // Log deal activity (non-fatal if logging activity fails)
        try {
            log_deal_activity($pdo, $organizationId, $dealId, $hostId, 'Meeting', "Scheduled meeting: {$title} on {$date} at " . date('g:i A', $startTs), $desc, $currentUserId);
        } catch (Throwable $e) {}

        pipeline_json(true, 'Meeting scheduled successfully.', [
            'event_id'   => $meetId,
            'id'         => 'evt_' . $meetId,
            'deal_id'    => $dealId,
            'title'      => $title,
            'date'       => $date,
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'status'     => 'Scheduled'
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        pipeline_json(false, 'Failed to schedule meeting: ' . $e->getMessage(), [], 500);
    }
}

// Default fallback
pipeline_json(false, "Unknown action: '$action'", [], 400);
