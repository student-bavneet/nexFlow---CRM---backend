<?php
/**
 * NexFlow CRM — Contracts Management API Controller
 * 
 * Multi-tenant Contracts CRUD, live SQL KPI aggregations, Chart.js analytics,
 * status transitions, lifecycle audit logging in team_activities, polymorphic
 * internal notes via team_notes, document attachments via contract_files,
 * and tenant-scoped CSV export.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// JSON Response Helper
function contracts_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
{
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
    contracts_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    contracts_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Helper
function require_contract_perm(string $action): void
{
    if (!hasPermission('contracts', $action)) {
        contracts_json(false, "Forbidden: You do not have permission to {$action} contracts.", [], 403);
    }
}

$pdo = nexflow_db();

// Helper: Get organization currency code from live organizations table
function get_org_currency(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $curr = $stmt->fetchColumn();
    return !empty($curr) ? trim($curr) : 'USD ($)';
}

// Helper: Generate sequential contract number per organization
function generate_contract_number(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare("
        SELECT contract_number 
        FROM contracts 
        WHERE organization_id = ? 
        ORDER BY id DESC 
        LIMIT 100
    ");
    $stmt->execute([$orgId]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 0;
    foreach ($existing as $num) {
        if (preg_match('/CON[-_]?0*(\d+)/i', $num, $m)) {
            $val = (int)$m[1];
            if ($val > $maxNum) $maxNum = $val;
        }
    }
    return sprintf("CON-%03d", $maxNum + 1);
}

// Helper: Log polymorphic activity in team_activities
function log_contract_activity(PDO $pdo, int $orgId, ?int $userId, string $type, string $title, ?string $desc, int $contractId): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities 
            (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'contracts', ?, ?, NOW())
        ");
        $stmt->execute([
            $orgId,
            $userId,
            $type,
            $title,
            $desc,
            $contractId,
            $userId ?: 1
        ]);
    } catch (Throwable $e) {
        // Silently log activity errors to not block primary workflow
    }
}

// Helper: Compute dynamic display status based on real dates
function compute_display_status(array $contract): string
{
    $baseStatus = $contract['status'] ?? 'Draft';
    if (in_array($baseStatus, ['Draft', 'Declined'])) {
        return $baseStatus;
    }
    if (!empty($contract['end_date'])) {
        $today = new DateTime('today');
        $end = new DateTime($contract['end_date']);
        $diff = (int)$today->diff($end)->format('%r%a');
        if ($diff < 0) {
            return 'Expired';
        }
        if ($diff <= 30 && !in_array($baseStatus, ['Signed'])) {
            return 'Expiring Soon';
        }
    }
    return $baseStatus;
}

// Resolve request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if (empty($action)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json) && !empty($json['action'])) {
            $action = trim($json['action']);
        }
    }
}

// CSRF & Method Guard on mutating actions
$mutatingActions = ['create', 'update', 'send', 'duplicate', 'delete', 'add_note', 'delete_note', 'toggle_pin_note', 'upload_file', 'delete_file'];
if (in_array($action, $mutatingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        contracts_json(false, 'Method Not Allowed. POST is required.', [], 405);
    }
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfToken)) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $parsed = json_decode($raw, true);
            if (is_array($parsed) && !empty($parsed['csrf_token'])) {
                $csrfToken = $parsed['csrf_token'];
            }
        }
    }
    if (!verify_admin_csrf($csrfToken)) {
        contracts_json(false, 'Invalid or expired CSRF token.', [], 403);
    }
}

// =========================================================================
// ACTION: reference_options
// =========================================================================
if ($action === 'reference_options') {
    require_contract_perm('view');

    $stmtComp = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? ORDER BY name ASC");
    $stmtComp->execute([$organizationId]);
    $companies = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

    $stmtCont = $pdo->prepare("SELECT id, company_name, first_name, last_name, email, phone FROM contacts WHERE organization_id = ? ORDER BY first_name ASC, last_name ASC");
    $stmtCont->execute([$organizationId]);
    $contacts = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    $stmtUsers = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY first_name ASC");
    $stmtUsers->execute([$organizationId]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $orgCurrency = get_org_currency($pdo, $organizationId);
    $nextContractNum = generate_contract_number($pdo, $organizationId);

    $types = [
        'Master Services Agreement (MSA)',
        'Statement of Work (SOW)',
        'Service Level Agreement (SLA)',
        'Non-Disclosure Agreement (NDA)',
        'Software License Agreement',
        'Development Agreement'
    ];

    $statuses = [
        'Draft', 'Sent', 'Viewed', 'Pending Signature', 'Changes Requested',
        'Revised', 'Signed', 'Active', 'Expiring Soon', 'Expired', 'Declined'
    ];

    contracts_json(true, 'Reference options retrieved.', [
        'companies'            => $companies,
        'contacts'             => $contacts,
        'users'                => $users,
        'currency'             => $orgCurrency,
        'next_contract_number' => $nextContractNum,
        'contract_types'       => $types,
        'statuses'             => $statuses,
    ]);
}

// =========================================================================
// ACTION: summary (KPI cards & Chart.js aggregated analytics)
// =========================================================================
if ($action === 'summary') {
    require_contract_perm('view');

    $orgCurrency = get_org_currency($pdo, $organizationId);

    // KPI Aggregations
    $stmtKpi = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END), 0) AS count_active,
            COALESCE(SUM(CASE WHEN end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status NOT IN ('Draft', 'Declined') THEN 1 ELSE 0 END), 0) AS count_expiring_soon,
            COALESCE(SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END), 0) AS count_draft,
            COALESCE(SUM(CASE WHEN status = 'Pending Signature' THEN 1 ELSE 0 END), 0) AS count_pending_signature,
            COALESCE(SUM(value), 0) AS total_value
        FROM contracts
        WHERE organization_id = ?
    ");
    $stmtKpi->execute([$organizationId]);
    $kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [
        'total_count' => 0, 'count_active' => 0, 'count_expiring_soon' => 0,
        'count_draft' => 0, 'count_pending_signature' => 0, 'total_value' => 0
    ];

    // Status Tab Counts
    $stmtTabs = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS cnt
        FROM contracts
        WHERE organization_id = ?
        GROUP BY status
    ");
    $stmtTabs->execute([$organizationId]);
    $rawStatusCounts = $stmtTabs->fetchAll(PDO::FETCH_KEY_PAIR);

    $tabCounts = [
        'All'               => (int)$kpi['total_count'],
        'Draft'             => (int)($rawStatusCounts['Draft'] ?? 0),
        'Sent'              => (int)($rawStatusCounts['Sent'] ?? 0),
        'Viewed'            => (int)($rawStatusCounts['Viewed'] ?? 0),
        'Pending Signature' => (int)($rawStatusCounts['Pending Signature'] ?? 0),
        'Changes Requested' => (int)($rawStatusCounts['Changes Requested'] ?? 0),
        'Signed'            => (int)($rawStatusCounts['Signed'] ?? 0),
        'Active'            => (int)($rawStatusCounts['Active'] ?? 0),
        'Expiring Soon'     => (int)$kpi['count_expiring_soon'],
        'Expired'           => (int)($rawStatusCounts['Expired'] ?? 0),
        'Declined'          => (int)($rawStatusCounts['Declined'] ?? 0),
    ];

    // Chart.js Aggregations: By Type
    // Buckets: MSA, SOW, SLA, NDA, License
    $stmtChart = $pdo->prepare("
        SELECT type, value
        FROM contracts
        WHERE organization_id = ?
    ");
    $stmtChart->execute([$organizationId]);
    $allTypes = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

    $typeCounts = ['MSA' => 0, 'SOW' => 0, 'SLA' => 0, 'NDA' => 0, 'License' => 0];
    $typeValues = ['MSA' => 0.0, 'SOW' => 0.0, 'SLA' => 0.0, 'NDA' => 0.0, 'License' => 0.0];

    foreach ($allTypes as $row) {
        $t = $row['type'] ?? '';
        $v = (float)$row['value'];
        $bucket = 'SOW';

        if (stripos($t, 'MSA') !== false || stripos($t, 'Master') !== false) {
            $bucket = 'MSA';
        } elseif (stripos($t, 'SOW') !== false || stripos($t, 'Statement') !== false || stripos($t, 'Development') !== false) {
            $bucket = 'SOW';
        } elseif (stripos($t, 'SLA') !== false || stripos($t, 'Maintenance') !== false || stripos($t, 'Service Level') !== false) {
            $bucket = 'SLA';
        } elseif (stripos($t, 'NDA') !== false || stripos($t, 'Non-Disclosure') !== false) {
            $bucket = 'NDA';
        } elseif (stripos($t, 'License') !== false || stripos($t, 'Software') !== false) {
            $bucket = 'License';
        }

        $typeCounts[$bucket]++;
        $typeValues[$bucket] += $v;
    }

    contracts_json(true, 'Summary loaded successfully.', [
        'kpi' => [
            'total_count'            => (int)$kpi['total_count'],
            'active_contracts'       => (int)$kpi['count_active'],
            'expiring_soon'          => (int)$kpi['count_expiring_soon'],
            'draft_agreements'       => (int)$kpi['count_draft'],
            'pending_signature'      => (int)$kpi['count_pending_signature'],
            'total_contract_value'   => (float)$kpi['total_value'],
        ],
        'tab_counts' => $tabCounts,
        'charts' => [
            'labels'      => ['MSA', 'SOW', 'SLA', 'NDA', 'License'],
            'type_counts' => array_values($typeCounts),
            'type_values' => array_values($typeValues),
        ],
        'currency' => $orgCurrency
    ]);
}

// =========================================================================
// ACTION: list
// =========================================================================
if ($action === 'list') {
    require_contract_perm('view');

    $page       = max(1, (int)($_GET['page'] ?? 1));
    $perPage    = max(1, min(100, (int)($_GET['per_page'] ?? 8)));
    $status     = trim($_GET['status'] ?? 'All');
    $type       = trim($_GET['type'] ?? 'All');
    $savedView  = trim($_GET['saved_view'] ?? 'all');
    $search     = trim($_GET['search'] ?? '');
    $sort       = trim($_GET['sort'] ?? 'name-asc');

    $where   = ["c.organization_id = :org_id"];
    $params  = [':org_id' => $organizationId];

    // Status filter
    if (!empty($status) && $status !== 'All') {
        if ($status === 'Expiring Soon') {
            $where[] = "(c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND c.status NOT IN ('Draft', 'Declined'))";
        } elseif ($status === 'Expired') {
            $where[] = "(c.status = 'Expired' OR (c.end_date < CURDATE() AND c.status NOT IN ('Draft', 'Declined')))";
        } else {
            $where[] = "c.status = :status_filter";
            $params[':status_filter'] = $status;
        }
    }

    // Type filter
    if (!empty($type) && $type !== 'All') {
        $where[] = "c.type LIKE :type_filter";
        $params[':type_filter'] = "%{$type}%";
    }

    // Saved views
    if ($savedView === 'active') {
        $where[] = "c.status = 'Active'";
    } elseif ($savedView === 'expiring') {
        $where[] = "(c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND c.status NOT IN ('Draft', 'Declined'))";
    } elseif ($savedView === 'high_val') {
        $where[] = "c.value >= 20000";
    }

    // Search query with unique placeholders
    if (!empty($search)) {
        $where[] = "(
            c.contract_number LIKE :s1
            OR c.title LIKE :s2
            OR c.reference_number LIKE :s3
            OR COALESCE(comp.name, c.company_name) LIKE :s4
            OR CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, ''), ' ', COALESCE(c.contact_name, '')) LIKE :s5
            OR c.type LIKE :s6
            OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE :s7
        )";
        $sTerm = "%{$search}%";
        $params[':s1'] = $sTerm;
        $params[':s2'] = $sTerm;
        $params[':s3'] = $sTerm;
        $params[':s4'] = $sTerm;
        $params[':s5'] = $sTerm;
        $params[':s6'] = $sTerm;
        $params[':s7'] = $sTerm;
    }

    $whereClause = implode(' AND ', $where);

    // Sorting
    $orderClause = "c.title ASC, c.id ASC";
    if ($sort === 'name-desc') {
        $orderClause = "c.title DESC, c.id DESC";
    } elseif ($sort === 'value-desc') {
        $orderClause = "c.value DESC, c.id DESC";
    } elseif ($sort === 'value-asc') {
        $orderClause = "c.value ASC, c.id ASC";
    } elseif ($sort === 'date-asc') {
        $orderClause = "c.end_date ASC, c.id ASC";
    }

    // Total items count query
    $countSql = "
        SELECT COUNT(*)
        FROM contracts c
        LEFT JOIN companies comp ON comp.id = c.company_id
        LEFT JOIN contacts cont ON cont.id = c.contact_id
        LEFT JOIN users u ON u.id = c.owner_id
        WHERE {$whereClause}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    // Items list query
    $listSql = "
        SELECT 
            c.*,
            COALESCE(comp.name, c.company_name) AS resolved_company_name,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, ''))), ''), c.contact_name) AS resolved_contact_name,
            cont.email AS contact_email,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name
        FROM contracts c
        LEFT JOIN companies comp ON comp.id = c.company_id
        LEFT JOIN contacts cont ON cont.id = c.contact_id
        LEFT JOIN users u ON u.id = c.owner_id
        WHERE {$whereClause}
        ORDER BY {$orderClause}
        LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
    ";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute dynamic display status and days remaining
    foreach ($items as &$item) {
        $item['display_status'] = compute_display_status($item);
        if (!empty($item['end_date'])) {
            $today = new DateTime('today');
            $end = new DateTime($item['end_date']);
            $item['days_remaining'] = (int)$today->diff($end)->format('%r%a');
        } else {
            $item['days_remaining'] = null;
        }
    }
    unset($item);

    contracts_json(true, 'Contracts retrieved.', [
        'items'      => $items,
        'pagination' => [
            'total'       => $totalItems,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ]
    ]);
}

// =========================================================================
// ACTION: get (Full details for drawer)
// =========================================================================
if ($action === 'get') {
    require_contract_perm('view');

    $id = (int)($_GET['id'] ?? 0);
    $num = trim($_GET['contract_number'] ?? '');

    if ($id <= 0 && empty($num)) {
        contracts_json(false, 'Contract identifier is required.', [], 400);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                COALESCE(comp.name, c.company_name) AS resolved_company_name,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, ''))), ''), c.contact_name) AS resolved_contact_name,
                cont.email AS contact_email,
                cont.phone AS contact_phone,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
                u.email AS owner_email
            FROM contracts c
            LEFT JOIN companies comp ON comp.id = c.company_id
            LEFT JOIN contacts cont ON cont.id = c.contact_id
            LEFT JOIN users u ON u.id = c.owner_id
            WHERE c.id = ? AND c.organization_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $organizationId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                COALESCE(comp.name, c.company_name) AS resolved_company_name,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, ''))), ''), c.contact_name) AS resolved_contact_name,
                cont.email AS contact_email,
                cont.phone AS contact_phone,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
                u.email AS owner_email
            FROM contracts c
            LEFT JOIN companies comp ON comp.id = c.company_id
            LEFT JOIN contacts cont ON cont.id = c.contact_id
            LEFT JOIN users u ON u.id = c.owner_id
            WHERE c.contract_number = ? AND c.organization_id = ?
            LIMIT 1
        ");
        $stmt->execute([$num, $organizationId]);
    }

    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contract) {
        contracts_json(false, 'Contract not found or access denied.', [], 404);
    }

    $contractId = (int)$contract['id'];

    // Timeline from team_activities
    $stmtAct = $pdo->prepare("
        SELECT 
            id,
            activity_type,
            title,
            description,
            created_at,
            created_by
        FROM team_activities
        WHERE organization_id = ? AND related_entity = 'contracts' AND related_entity_id = ?
        ORDER BY id DESC
    ");
    $stmtAct->execute([$organizationId, $contractId]);
    $timeline = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    // Notes from team_notes
    $stmtNotes = $pdo->prepare("
        SELECT 
            n.id,
            n.title,
            n.content,
            n.is_pinned,
            n.created_at,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS author_name
        FROM team_notes n
        LEFT JOIN users u ON u.id = n.created_by
        WHERE n.organization_id = ? AND n.related_type = 'contracts' AND n.related_id = ?
        ORDER BY n.is_pinned DESC, n.id DESC
    ");
    $stmtNotes->execute([$organizationId, $contractId]);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

    // Files from contract_files
    $stmtFiles = $pdo->prepare("
        SELECT 
            f.id,
            f.file_name,
            f.file_size,
            f.mime_type,
            f.created_at,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS uploader_name
        FROM contract_files f
        LEFT JOIN users u ON u.id = f.uploaded_by
        WHERE f.organization_id = ? AND f.contract_id = ?
        ORDER BY f.id DESC
    ");
    $stmtFiles->execute([$organizationId, $contractId]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

    // Compute dynamic fields
    $contract['display_status'] = compute_display_status($contract);
    if (!empty($contract['end_date'])) {
        $today = new DateTime('today');
        $end = new DateTime($contract['end_date']);
        $contract['days_remaining'] = (int)$today->diff($end)->format('%r%a');
    } else {
        $contract['days_remaining'] = null;
    }

    contracts_json(true, 'Contract details retrieved.', [
        'contract' => $contract,
        'timeline' => $timeline,
        'notes'    => $notes,
        'files'    => $files,
    ]);
}

// =========================================================================
// ACTION: create
// =========================================================================
if ($action === 'create') {
    require_contract_perm('create');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $title        = trim($data['title'] ?? '');
    $refNumber    = trim($data['reference_number'] ?? $data['contract_number'] ?? $data['refNumber'] ?? '');
    $companyName  = trim($data['company_name'] ?? $data['client_name'] ?? $data['company'] ?? '');
    $companyId    = !empty($data['company_id']) ? (int)$data['company_id'] : null;
    $contactName  = trim($data['contact_name'] ?? $data['primaryContact'] ?? $data['contact'] ?? '');
    $contactId    = !empty($data['contact_id']) ? (int)$data['contact_id'] : null;
    $clientEmail  = trim($data['client_email'] ?? $data['email'] ?? '');
    $type         = trim($data['type'] ?? $data['contract_type'] ?? '');
    $value        = (float)($data['value'] ?? $data['amount'] ?? 0);
    $startDate    = trim($data['start_date'] ?? $data['startDate'] ?? '');
    $endDate      = trim($data['end_date'] ?? $data['endDate'] ?? '');
    $ownerId      = !empty($data['owner_id']) ? (int)$data['owner_id'] : (!empty($data['assigned_to']) ? (int)$data['assigned_to'] : $currentUserId);
    $renewalType  = trim($data['renewal_type'] ?? $data['renewalType'] ?? '');
    $validRenewalTypes = ['Auto-renew', 'Manual Renewal', 'No Renewal'];
    if (!in_array($renewalType, $validRenewalTypes)) {
        $renewalType = 'Auto-renew';
    }
    $noticeDays   = isset($data['notice_days']) ? (int)$data['notice_days'] : (isset($data['notice_period_days']) ? (int)$data['notice_period_days'] : (isset($data['noticeDays']) ? (int)$data['noticeDays'] : 30));
    $overview     = trim($data['overview'] ?? $data['description'] ?? '');
    $terms        = trim($data['terms'] ?? '');
    $paymentTerms = trim($data['payment_terms'] ?? $data['paymentTerms'] ?? '');

    // Validation
    if (empty($title)) {
        contracts_json(false, 'Contract title is required.', [], 422);
    }
    if (empty($companyName) && empty($companyId)) {
        contracts_json(false, 'Company name or client is required.', [], 422);
    }
    if (empty($type)) {
        contracts_json(false, 'Contract type is required.', [], 422);
    }
    if ($value < 0) {
        contracts_json(false, 'Contract value cannot be negative.', [], 422);
    }
    if (empty($startDate) || empty($endDate)) {
        contracts_json(false, 'Start date and End date are required.', [], 422);
    }
    if ($endDate < $startDate) {
        contracts_json(false, 'End date cannot be earlier than start date.', [], 422);
    }

    // Tenant validation: Company
    if ($companyId) {
        $stmtC = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND organization_id = ?");
        $stmtC->execute([$companyId, $organizationId]);
        $cName = $stmtC->fetchColumn();
        if (!$cName) {
            contracts_json(false, 'The selected company does not exist in your organization.', [], 422);
        }
        if (empty($companyName)) $companyName = $cName;
    }

    // Tenant validation: Contact
    if ($contactId) {
        $stmtCt = $pdo->prepare("SELECT first_name, last_name, email FROM contacts WHERE id = ? AND organization_id = ?");
        $stmtCt->execute([$contactId, $organizationId]);
        $ctRow = $stmtCt->fetch(PDO::FETCH_ASSOC);
        if (!$ctRow) {
            contracts_json(false, 'The selected contact does not exist in your organization.', [], 422);
        }
        if (empty($contactName)) {
            $contactName = trim($ctRow['first_name'] . ' ' . $ctRow['last_name']);
        }
        if (empty($clientEmail) && !empty($ctRow['email'])) {
            $clientEmail = $ctRow['email'];
        }
    }

    // Validate contact-to-company link strictly via authoritative contact_companies table
    if ($companyId && $contactId) {
        $stmtRel = $pdo->prepare("
            SELECT 1 
            FROM contact_companies cc
            INNER JOIN contacts c ON c.id = cc.contact_id AND c.organization_id = ?
            INNER JOIN companies comp ON comp.id = cc.company_id AND comp.organization_id = ?
            WHERE cc.contact_id = ? 
              AND cc.company_id = ? 
              AND cc.organization_id = ?
            LIMIT 1
        ");
        $stmtRel->execute([$organizationId, $organizationId, $contactId, $companyId, $organizationId]);
        if (!$stmtRel->fetchColumn()) {
            contracts_json(false, 'The selected contact is not associated with the selected company.', [], 422);
        }
    }

    // Tenant validation: Owner
    if (!empty($data['owner_id']) || !empty($data['assigned_to'])) {
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
        $stmtU->execute([$ownerId, $organizationId]);
        if (!$stmtU->fetchColumn()) {
            contracts_json(false, 'The selected owner does not exist or is inactive in your organization.', [], 422);
        }
    } else {
        $ownerId = $currentUserId;
    }

    $orgCurrency = get_org_currency($pdo, $organizationId);
    $contractNumber = generate_contract_number($pdo, $organizationId);
    $nextRenewalDate = $endDate;

    $status = 'Draft';
    $validStatuses = [
        'draft' => 'Draft', 'sent' => 'Sent', 'viewed' => 'Viewed',
        'pending signature' => 'Pending Signature', 'changes requested' => 'Changes Requested',
        'revised' => 'Revised', 'signed' => 'Signed', 'active' => 'Active',
        'expiring soon' => 'Expiring Soon', 'expired' => 'Expired', 'declined' => 'Declined'
    ];
    if (!empty($data['status'])) {
        $normSt = strtolower(trim($data['status']));
        if (isset($validStatuses[$normSt])) {
            $status = $validStatuses[$normSt];
        }
    }

    $stmtIns = $pdo->prepare("
        INSERT INTO contracts (
            organization_id, contract_number, title, reference_number,
            company_id, company_name, contact_id, contact_name, client_email,
            type, value, currency, start_date, end_date,
            status, signature_status, is_client_signed, owner_id,
            renewal_type, notice_days, next_renewal_date,
            overview, terms, payment_terms, created_by, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, 'Not Sent', 0, ?,
            ?, ?, ?,
            ?, ?, ?, ?, NOW(), NOW()
        )
    ");

    $stmtIns->execute([
        $organizationId, $contractNumber, $title, $refNumber ?: null,
        $companyId, $companyName, $contactId, $contactName ?: null, $clientEmail ?: null,
        $type, $value, $orgCurrency, $startDate, $endDate,
        $status, $ownerId,
        $renewalType ?: 'Auto-renew', $noticeDays, $nextRenewalDate,
        $overview ?: null, $terms ?: null, $paymentTerms ?: null, $currentUserId
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Log activity in team_activities
    log_contract_activity(
        $pdo,
        $organizationId,
        $currentUserId,
        'contract_created',
        'Contract Created',
        "Contract {$contractNumber} (\"{$title}\") was created as Draft.",
        $newId
    );

    contracts_json(true, "Contract \"{$title}\" created successfully as Draft.", [
        'id'              => $newId,
        'contract_number' => $contractNumber,
    ], 201);
}

// =========================================================================
// ACTION: update
// =========================================================================
if ($action === 'update') {
    require_contract_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $contractId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    $contractNum = trim($data['contract_number'] ?? $_GET['contract_number'] ?? '');

    if ($contractId <= 0 && !empty($contractNum)) {
        $stmtFind = $pdo->prepare("SELECT id FROM contracts WHERE contract_number = ? AND organization_id = ?");
        $stmtFind->execute([$contractNum, $organizationId]);
        $contractId = (int)$stmtFind->fetchColumn();
    }

    if ($contractId <= 0) {
        contracts_json(false, 'Valid contract ID is required for update.', [], 400);
    }

    // Verify existing contract belongs to tenant
    $stmtEx = $pdo->prepare("SELECT * FROM contracts WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$contractId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        contracts_json(false, 'Contract not found or access denied.', [], 404);
    }

    // Extract updated fields
    $title        = isset($data['title']) ? trim($data['title']) : $existing['title'];
    $refNumber    = isset($data['reference_number']) ? trim($data['reference_number']) : (isset($data['refNumber']) ? trim($data['refNumber']) : $existing['reference_number']);
    $companyName  = isset($data['company_name']) ? trim($data['company_name']) : (isset($data['company']) ? trim($data['company']) : $existing['company_name']);
    $companyId    = isset($data['company_id']) ? (!empty($data['company_id']) ? (int)$data['company_id'] : null) : $existing['company_id'];
    $contactName  = isset($data['contact_name']) ? trim($data['contact_name']) : (isset($data['primaryContact']) ? trim($data['primaryContact']) : $existing['contact_name']);
    $contactId    = isset($data['contact_id']) ? (!empty($data['contact_id']) ? (int)$data['contact_id'] : null) : $existing['contact_id'];
    $clientEmail  = isset($data['client_email']) ? trim($data['client_email']) : (isset($data['email']) ? trim($data['email']) : $existing['client_email']);
    $type         = isset($data['type']) ? trim($data['type']) : $existing['type'];
    $value        = isset($data['value']) ? (float)$data['value'] : (isset($data['amount']) ? (float)$data['amount'] : (float)$existing['value']);
    $startDate    = isset($data['start_date']) ? trim($data['start_date']) : (isset($data['startDate']) ? trim($data['startDate']) : $existing['start_date']);
    $endDate      = isset($data['end_date']) ? trim($data['end_date']) : (isset($data['endDate']) ? trim($data['endDate']) : $existing['end_date']);
    $status       = isset($data['status']) ? trim($data['status']) : $existing['status'];
    $ownerId      = isset($data['owner_id']) ? (!empty($data['owner_id']) ? (int)$data['owner_id'] : null) : $existing['owner_id'];
    $renewalType  = isset($data['renewal_type']) ? trim($data['renewal_type']) : (isset($data['renewalType']) ? trim($data['renewalType']) : $existing['renewal_type']);
    if (!in_array($renewalType, ['Auto-renew', 'Manual Renewal', 'No Renewal'])) {
        $renewalType = $existing['renewal_type'] ?: 'Auto-renew';
    }
    $noticeDays   = isset($data['notice_days']) ? (int)$data['notice_days'] : (isset($data['notice_period_days']) ? (int)$data['notice_period_days'] : (isset($data['noticeDays']) ? (int)$data['noticeDays'] : (int)$existing['notice_days']));
    $overview     = isset($data['overview']) ? trim($data['overview']) : (isset($data['description']) ? trim($data['description']) : $existing['overview']);
    $terms        = isset($data['terms']) ? trim($data['terms']) : $existing['terms'];
    $paymentTerms = isset($data['payment_terms']) ? trim($data['payment_terms']) : (isset($data['paymentTerms']) ? trim($data['paymentTerms']) : $existing['payment_terms']);

    if (empty($title)) {
        contracts_json(false, 'Contract title cannot be empty.', [], 422);
    }
    if ($value < 0) {
        contracts_json(false, 'Contract value cannot be negative.', [], 422);
    }
    if (!empty($startDate) && !empty($endDate) && $endDate < $startDate) {
        contracts_json(false, 'End date cannot be earlier than start date.', [], 422);
    }

    // Tenant validation: Company
    if ($companyId) {
        $stmtC = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND organization_id = ?");
        $stmtC->execute([$companyId, $organizationId]);
        $cName = $stmtC->fetchColumn();
        if (!$cName) {
            contracts_json(false, 'The selected company does not exist in your organization.', [], 422);
        }
        if (empty($companyName)) $companyName = $cName;
    }

    // Tenant validation: Contact
    if ($contactId) {
        $stmtCt = $pdo->prepare("SELECT first_name, last_name, email FROM contacts WHERE id = ? AND organization_id = ?");
        $stmtCt->execute([$contactId, $organizationId]);
        $ctRow = $stmtCt->fetch(PDO::FETCH_ASSOC);
        if (!$ctRow) {
            contracts_json(false, 'The selected contact does not exist in your organization.', [], 422);
        }
        if (empty($contactName)) {
            $contactName = trim($ctRow['first_name'] . ' ' . $ctRow['last_name']);
        }
        if (empty($clientEmail) && !empty($ctRow['email'])) {
            $clientEmail = $ctRow['email'];
        }
    }

    // Validate contact-to-company link strictly via authoritative contact_companies table
    if ($companyId && $contactId) {
        $stmtRel = $pdo->prepare("
            SELECT 1 
            FROM contact_companies cc
            INNER JOIN contacts c ON c.id = cc.contact_id AND c.organization_id = ?
            INNER JOIN companies comp ON comp.id = cc.company_id AND comp.organization_id = ?
            WHERE cc.contact_id = ? 
              AND cc.company_id = ? 
              AND cc.organization_id = ?
            LIMIT 1
        ");
        $stmtRel->execute([$organizationId, $organizationId, $contactId, $companyId, $organizationId]);
        if (!$stmtRel->fetchColumn()) {
            contracts_json(false, 'The selected contact is not associated with the selected company.', [], 422);
        }
    }

    // Tenant validation: Owner
    if (!empty($data['owner_id']) || !empty($data['assigned_to'])) {
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
        $stmtU->execute([$ownerId, $organizationId]);
        if (!$stmtU->fetchColumn()) {
            contracts_json(false, 'The selected owner does not exist or is inactive in your organization.', [], 422);
        }
    } elseif (!$ownerId) {
        $ownerId = $existing['owner_id'] ?: $currentUserId;
    }

    // Handle signature transition if marked Signed
    $sigStatus = $existing['signature_status'];
    $isSigned = (int)$existing['is_client_signed'];
    $signedDate = $existing['signed_date'];

    if ($status === 'Signed' && $existing['status'] !== 'Signed') {
        $sigStatus = 'Signed';
        $isSigned = 1;
        $signedDate = date('Y-m-d H:i:s');
    }

    $stmtUpd = $pdo->prepare("
        UPDATE contracts SET
            title = ?, reference_number = ?, company_id = ?, company_name = ?,
            contact_id = ?, contact_name = ?, client_email = ?, type = ?,
            value = ?, start_date = ?, end_date = ?, status = ?,
            signature_status = ?, is_client_signed = ?, signed_date = ?,
            owner_id = ?, renewal_type = ?, notice_days = ?, next_renewal_date = ?,
            overview = ?, terms = ?, payment_terms = ?, updated_at = NOW()
        WHERE id = ? AND organization_id = ?
    ");

    $stmtUpd->execute([
        $title, $refNumber ?: null, $companyId, $companyName,
        $contactId, $contactName ?: null, $clientEmail ?: null, $type,
        $value, $startDate, $endDate, $status,
        $sigStatus, $isSigned, $signedDate,
        $ownerId, $renewalType, $noticeDays, $endDate,
        $overview ?: null, $terms ?: null, $paymentTerms ?: null,
        $contractId, $organizationId
    ]);

    // Log activity
    $actTitle = ($existing['status'] === 'Changes Requested' && $status === 'Revised') ? 'Contract Revised' : 'Contract Updated';
    log_contract_activity(
        $pdo,
        $organizationId,
        $currentUserId,
        'contract_updated',
        $actTitle,
        "Contract {$existing['contract_number']} was updated.",
        $contractId
    );

    contracts_json(true, "Contract {$existing['contract_number']} updated successfully.", [
        'id'              => $contractId,
        'contract_number' => $existing['contract_number'],
        'status'          => $status,
    ]);
}

// =========================================================================
// ACTION: send (Send contract / revised contract to client)
// =========================================================================
if ($action === 'send') {
    require_contract_perm('send');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $contractId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    $contractNum = trim($data['contract_number'] ?? $_GET['contract_number'] ?? '');

    if ($contractId <= 0 && !empty($contractNum)) {
        $stmtFind = $pdo->prepare("SELECT id FROM contracts WHERE contract_number = ? AND organization_id = ?");
        $stmtFind->execute([$contractNum, $organizationId]);
        $contractId = (int)$stmtFind->fetchColumn();
    }

    if ($contractId <= 0) {
        contracts_json(false, 'Valid contract ID is required.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT * FROM contracts WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$contractId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        contracts_json(false, 'Contract not found or access denied.', [], 404);
    }

    $isRevised = ($existing['status'] === 'Changes Requested' || $existing['status'] === 'Revised');
    $newStatus = 'Sent';
    $sigStatus = 'Sent';

    $stmtUpd = $pdo->prepare("
        UPDATE contracts 
        SET status = ?, signature_status = ?, updated_at = NOW() 
        WHERE id = ? AND organization_id = ?
    ");
    $stmtUpd->execute([$newStatus, $sigStatus, $contractId, $organizationId]);

    log_contract_activity(
        $pdo,
        $organizationId,
        $currentUserId,
        'contract_sent',
        $isRevised ? 'Revised Contract Sent' : 'Contract Sent',
        $isRevised ? "Revised contract {$existing['contract_number']} was sent to client." : "Contract {$existing['contract_number']} was sent to client.",
        $contractId
    );

    contracts_json(true, $isRevised ? 'Revised contract sent successfully.' : 'Contract sent successfully.', [
        'id'              => $contractId,
        'contract_number' => $existing['contract_number'],
        'status'          => $newStatus,
    ]);
}

// =========================================================================
// ACTION: duplicate
// =========================================================================
if ($action === 'duplicate') {
    require_contract_perm('create');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $sourceId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if ($sourceId <= 0) {
        contracts_json(false, 'Source contract ID is required.', [], 400);
    }

    $stmtSrc = $pdo->prepare("SELECT * FROM contracts WHERE id = ? AND organization_id = ?");
    $stmtSrc->execute([$sourceId, $organizationId]);
    $src = $stmtSrc->fetch(PDO::FETCH_ASSOC);

    if (!$src) {
        contracts_json(false, 'Source contract not found or access denied.', [], 404);
    }

    $newContractNumber = generate_contract_number($pdo, $organizationId);
    $newTitle = 'Copy of ' . $src['title'];

    $stmtDup = $pdo->prepare("
        INSERT INTO contracts (
            organization_id, contract_number, title, reference_number,
            company_id, company_name, contact_id, contact_name, client_email,
            type, value, currency, start_date, end_date,
            status, signature_status, is_client_signed, owner_id,
            renewal_type, notice_days, next_renewal_date,
            overview, terms, payment_terms, created_by, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            'Draft', 'Not Sent', 0, ?,
            ?, ?, ?,
            ?, ?, ?, ?, NOW(), NOW()
        )
    ");

    $stmtDup->execute([
        $organizationId, $newContractNumber, $newTitle, $newContractNumber,
        $src['company_id'], $src['company_name'], $src['contact_id'], $src['contact_name'], $src['client_email'],
        $src['type'], $src['value'], $src['currency'], $src['start_date'], $src['end_date'],
        $currentUserId,
        $src['renewal_type'], $src['notice_days'], $src['end_date'],
        $src['overview'], $src['terms'], $src['payment_terms'], $currentUserId
    ]);

    $newId = (int)$pdo->lastInsertId();

    log_contract_activity(
        $pdo,
        $organizationId,
        $currentUserId,
        'contract_duplicated',
        'Contract Duplicated',
        "Contract {$newContractNumber} was duplicated from {$src['contract_number']}.",
        $newId
    );

    contracts_json(true, "Contract duplicated successfully as {$newContractNumber}.", [
        'id'              => $newId,
        'contract_number' => $newContractNumber,
    ], 201);
}

// =========================================================================
// ACTION: delete
// =========================================================================
if ($action === 'delete') {
    require_contract_perm('delete');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $contractId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    $contractNum = trim($data['contract_number'] ?? $_GET['contract_number'] ?? '');

    if ($contractId <= 0 && !empty($contractNum)) {
        $stmtFind = $pdo->prepare("SELECT id FROM contracts WHERE contract_number = ? AND organization_id = ?");
        $stmtFind->execute([$contractNum, $organizationId]);
        $contractId = (int)$stmtFind->fetchColumn();
    }

    if ($contractId <= 0) {
        contracts_json(false, 'Valid contract ID is required for deletion.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT id, contract_number FROM contracts WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$contractId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        contracts_json(false, 'Contract not found or access denied.', [], 404);
    }

    // Clean up physical files from disk
    $stmtFiles = $pdo->prepare("SELECT file_path FROM contract_files WHERE contract_id = ? AND organization_id = ?");
    $stmtFiles->execute([$contractId, $organizationId]);
    $paths = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);
    foreach ($paths as $p) {
        $fullPath = __DIR__ . '/../../' . ltrim($p, '/\\');
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    // Delete contract (FK cascade will remove contract_files)
    $stmtDel = $pdo->prepare("DELETE FROM contracts WHERE id = ? AND organization_id = ?");
    $stmtDel->execute([$contractId, $organizationId]);

    // Clean up polymorphic notes and activities
    $stmtDelNotes = $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'contracts' AND related_id = ?");
    $stmtDelNotes->execute([$organizationId, $contractId]);

    $stmtDelAct = $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'contracts' AND related_entity_id = ?");
    $stmtDelAct->execute([$organizationId, $contractId]);

    contracts_json(true, "Contract {$existing['contract_number']} deleted successfully.");
}

// =========================================================================
// ACTION: export_csv
// =========================================================================
if ($action === 'export_csv') {
    require_contract_perm('export');

    $status    = trim($_GET['status'] ?? 'All');
    $type      = trim($_GET['type'] ?? 'All');
    $search    = trim($_GET['search'] ?? '');
    $sort      = trim($_GET['sort'] ?? 'name-asc');

    $where   = ["c.organization_id = :org_id"];
    $params  = [':org_id' => $organizationId];

    if (!empty($status) && $status !== 'All') {
        if ($status === 'Expiring Soon') {
            $where[] = "(c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND c.status NOT IN ('Draft', 'Declined'))";
        } elseif ($status === 'Expired') {
            $where[] = "(c.status = 'Expired' OR (c.end_date < CURDATE() AND c.status NOT IN ('Draft', 'Declined')))";
        } else {
            $where[] = "c.status = :status_filter";
            $params[':status_filter'] = $status;
        }
    }

    if (!empty($type) && $type !== 'All') {
        $where[] = "c.type LIKE :type_filter";
        $params[':type_filter'] = "%{$type}%";
    }

    if (!empty($search)) {
        $where[] = "(
            c.contract_number LIKE :s1
            OR c.title LIKE :s2
            OR c.reference_number LIKE :s3
            OR COALESCE(comp.name, c.company_name) LIKE :s4
            OR c.type LIKE :s5
        )";
        $sTerm = "%{$search}%";
        $params[':s1'] = $sTerm;
        $params[':s2'] = $sTerm;
        $params[':s3'] = $sTerm;
        $params[':s4'] = $sTerm;
        $params[':s5'] = $sTerm;
    }

    $whereClause = implode(' AND ', $where);

    $orderClause = "c.title ASC, c.id ASC";
    if ($sort === 'name-desc') $orderClause = "c.title DESC, c.id DESC";
    elseif ($sort === 'value-desc') $orderClause = "c.value DESC, c.id DESC";
    elseif ($sort === 'value-asc') $orderClause = "c.value ASC, c.id ASC";
    elseif ($sort === 'date-asc') $orderClause = "c.end_date ASC, c.id ASC";

    $sql = "
        SELECT 
            c.contract_number,
            c.title,
            c.reference_number,
            COALESCE(comp.name, c.company_name) AS company_name,
            c.type,
            c.value,
            c.start_date,
            c.end_date,
            c.status,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name
        FROM contracts c
        LEFT JOIN companies comp ON comp.id = c.company_id
        LEFT JOIN users u ON u.id = c.owner_id
        WHERE {$whereClause}
        ORDER BY {$orderClause}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "contracts_export_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Title', 'RefNumber', 'Company', 'Type', 'Value', 'StartDate', 'EndDate', 'Status', 'Owner']);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['contract_number'],
            $r['title'],
            $r['reference_number'] ?: $r['contract_number'],
            $r['company_name'],
            $r['type'],
            $r['value'],
            $r['start_date'],
            $r['end_date'],
            $r['status'],
            $r['owner_name'],
        ]);
    }
    fclose($output);
    exit;
}

// =========================================================================
// ACTION: add_note (Polymorphic team_notes)
// =========================================================================
if ($action === 'add_note') {
    require_contract_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $contractId = (int)($data['contract_id'] ?? $data['id'] ?? 0);
    $content = trim($data['content'] ?? '');
    $isPinned = !empty($data['is_pinned']) ? 1 : 0;

    if ($contractId <= 0 || empty($content)) {
        contracts_json(false, 'Contract ID and note content are required.', [], 422);
    }

    $stmtEx = $pdo->prepare("SELECT id FROM contracts WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$contractId, $organizationId]);
    if (!$stmtEx->fetchColumn()) {
        contracts_json(false, 'Contract not found or access denied.', [], 404);
    }

    $stmtNote = $pdo->prepare("
        INSERT INTO team_notes (
            organization_id, member_id, related_type, related_id,
            created_by, title, content, is_pinned, created_at, updated_at
        ) VALUES (
            ?, ?, 'contracts', ?,
            ?, ?, ?, ?, NOW(), NOW()
        )
    ");
    $stmtNote->execute([
        $organizationId, $currentUserId, $contractId,
        $currentUserId, 'Internal Contract Note', $content, $isPinned
    ]);

    $noteId = (int)$pdo->lastInsertId();

    contracts_json(true, 'Note added successfully.', [
        'id'        => $noteId,
        'content'   => $content,
        'is_pinned' => $isPinned,
    ], 201);
}

// =========================================================================
// ACTION: delete_note
// =========================================================================
if ($action === 'delete_note') {
    require_contract_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $noteId = (int)($data['note_id'] ?? $data['id'] ?? 0);
    if ($noteId <= 0) {
        contracts_json(false, 'Valid note ID is required.', [], 400);
    }

    $stmtDel = $pdo->prepare("
        DELETE FROM team_notes 
        WHERE id = ? AND organization_id = ? AND related_type = 'contracts'
    ");
    $stmtDel->execute([$noteId, $organizationId]);

    if ($stmtDel->rowCount() === 0) {
        contracts_json(false, 'Note not found or access denied.', [], 404);
    }

    contracts_json(true, 'Note deleted successfully.');
}

// =========================================================================
// ACTION: toggle_pin_note
// =========================================================================
if ($action === 'toggle_pin_note') {
    require_contract_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $noteId = (int)($data['note_id'] ?? $data['id'] ?? 0);
    if ($noteId <= 0) {
        contracts_json(false, 'Valid note ID is required.', [], 400);
    }

    $stmtGet = $pdo->prepare("
        SELECT is_pinned FROM team_notes 
        WHERE id = ? AND organization_id = ? AND related_type = 'contracts'
    ");
    $stmtGet->execute([$noteId, $organizationId]);
    $currentPin = $stmtGet->fetchColumn();

    if ($currentPin === false) {
        contracts_json(false, 'Note not found or access denied.', [], 404);
    }

    $newPin = $currentPin ? 0 : 1;
    $stmtUpd = $pdo->prepare("
        UPDATE team_notes 
        SET is_pinned = ?, updated_at = NOW() 
        WHERE id = ? AND organization_id = ?
    ");
    $stmtUpd->execute([$newPin, $noteId, $organizationId]);

    contracts_json(true, $newPin ? 'Note pinned.' : 'Note unpinned.', [
        'id'        => $noteId,
        'is_pinned' => $newPin,
    ]);
}

// =========================================================================
// ACTION: upload_file
// =========================================================================
if ($action === 'upload_file') {
    require_contract_perm('edit');

    $contractId = (int)($_POST['contract_id'] ?? 0);
    if ($contractId <= 0) {
        contracts_json(false, 'Contract ID is required.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT id FROM contracts WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$contractId, $organizationId]);
    if (!$stmtEx->fetchColumn()) {
        contracts_json(false, 'Contract not found or access denied.', [], 404);
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        contracts_json(false, 'No file was uploaded or an upload error occurred.', [], 400);
    }

    $file = $_FILES['file'];
    $maxSize = 25 * 1024 * 1024; // 25MB
    if ($file['size'] > $maxSize) {
        contracts_json(false, 'File size exceeds 25MB limit.', [], 422);
    }

    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'png', 'jpg', 'jpeg', 'zip'];

    if (!in_array($ext, $allowedExts)) {
        contracts_json(false, 'File type not allowed. Supported formats: PDF, DOC, DOCX, TXT, Images, ZIP.', [], 422);
    }

    $uploadDir = __DIR__ . '/../../uploads/contracts/' . $organizationId . '/' . $contractId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeFileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $origName);
    $targetName = time() . '_' . $safeFileName;
    $targetPath = $uploadDir . '/' . $targetName;
    $dbRelativePath = 'uploads/contracts/' . $organizationId . '/' . $contractId . '/' . $targetName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        contracts_json(false, 'Failed to save uploaded file.', [], 500);
    }

    // Format file size
    $bytes = (int)$file['size'];
    $sizeFormatted = $bytes > 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';

    $mimeType = $file['type'] ?: 'application/octet-stream';

    $stmtFileIns = $pdo->prepare("
        INSERT INTO contract_files (
            organization_id, contract_id, file_name, file_path,
            file_size, mime_type, uploaded_by, created_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, NOW()
        )
    ");
    $stmtFileIns->execute([
        $organizationId, $contractId, $origName, $dbRelativePath,
        $sizeFormatted, $mimeType, $currentUserId
    ]);

    $fileId = (int)$pdo->lastInsertId();

    contracts_json(true, 'File uploaded successfully.', [
        'id'        => $fileId,
        'file_name' => $origName,
        'file_size' => $sizeFormatted,
    ], 201);
}

// =========================================================================
// ACTION: download_file
// =========================================================================
if ($action === 'download_file') {
    require_contract_perm('download');

    $fileId = (int)($_GET['file_id'] ?? $_GET['id'] ?? 0);
    $contractId = (int)($_GET['contract_id'] ?? 0);

    if ($fileId <= 0) {
        contracts_json(false, 'File ID is required.', [], 400);
    }

    $stmtFile = $pdo->prepare("
        SELECT f.* 
        FROM contract_files f
        JOIN contracts c ON c.id = f.contract_id
        WHERE f.id = ? AND f.organization_id = ?
        LIMIT 1
    ");
    $stmtFile->execute([$fileId, $organizationId]);
    $fileRow = $stmtFile->fetch(PDO::FETCH_ASSOC);

    if (!$fileRow) {
        contracts_json(false, 'File not found or access denied.', [], 404);
    }

    $fullPath = __DIR__ . '/../../' . ltrim($fileRow['file_path'], '/\\');
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        contracts_json(false, 'Physical file not found on disk.', [], 404);
    }

    $mime = !empty($fileRow['mime_type']) ? $fileRow['mime_type'] : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . addslashes($fileRow['file_name']) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// =========================================================================
// ACTION: delete_file
// =========================================================================
if ($action === 'delete_file') {
    require_contract_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $fileId = (int)($data['file_id'] ?? $data['id'] ?? 0);
    if ($fileId <= 0) {
        contracts_json(false, 'File ID is required.', [], 400);
    }

    $stmtFile = $pdo->prepare("
        SELECT * FROM contract_files 
        WHERE id = ? AND organization_id = ?
    ");
    $stmtFile->execute([$fileId, $organizationId]);
    $fileRow = $stmtFile->fetch(PDO::FETCH_ASSOC);

    if (!$fileRow) {
        contracts_json(false, 'File not found or access denied.', [], 404);
    }

    $fullPath = __DIR__ . '/../../' . ltrim($fileRow['file_path'], '/\\');
    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
    }

    $stmtDel = $pdo->prepare("DELETE FROM contract_files WHERE id = ? AND organization_id = ?");
    $stmtDel->execute([$fileId, $organizationId]);

    contracts_json(true, 'File deleted successfully.');
}

// If action unrecognized:
contracts_json(false, "Invalid action specified: '{$action}'", [], 400);
