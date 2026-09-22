<?php
/**
 * NexFlow CRM — Proposals Management API Controller
 * 
 * Multi-tenant Proposals & Deliverables CRUD, live SQL KPI aggregations,
 * status transitions, lifecycle audit logging via polymorphic team_activities,
 * server-side decimal-safe financial calculations, and organization-scoped CSV export.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// JSON Response Helper
function proposals_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
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
    proposals_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    proposals_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Helper
function require_proposal_perm(string $action): void
{
    global $currentUser;
    $permKey = 'proposals.' . $action;

    // Super admin bypass
    if (($currentUser['role'] ?? '') === 'super_admin' || ($currentUser['role_id'] ?? 0) === 1) {
        return;
    }

    if (function_exists('hasPermission')) {
        if (!hasPermission($permKey) && !hasPermission('proposals') && !hasPermission('proposals.view')) {
            proposals_json(false, "Forbidden. Missing permission: {$permKey}", [], 403);
        }
    }
}

// 3. Database Connection
$pdo = nexflow_db();

// 4. Currency Helper
function get_org_currency(PDO $pdo, int $orgId): string
{
    static $currencyCache = [];
    if (isset($currencyCache[$orgId])) {
        return $currencyCache[$orgId];
    }
    $stmt = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $curr = $stmt->fetchColumn();
    $currencyCache[$orgId] = $curr ? (string)$curr : 'USD ($)';
    return $currencyCache[$orgId];
}

// 5. Sequential Proposal Number Generator (Organization Scoped)
function generate_proposal_number(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare("
        SELECT proposal_number 
        FROM proposals 
        WHERE organization_id = ? 
        ORDER BY id DESC 
        LIMIT 1 
        FOR UPDATE
    ");
    $stmt->execute([$orgId]);
    $lastNumber = $stmt->fetchColumn();

    $nextNum = 1;
    if ($lastNumber && preg_match('/PROP-(\d+)/', $lastNumber, $matches)) {
        $nextNum = ((int)$matches[1]) + 1;
    } else {
        // Fallback: count records
        $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE organization_id = ?");
        $stmtCnt->execute([$orgId]);
        $nextNum = ((int)$stmtCnt->fetchColumn()) + 1;
    }

    $candidate = 'PROP-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

    // Ensure collision-free
    $stmtCheck = $pdo->prepare("SELECT id FROM proposals WHERE organization_id = ? AND proposal_number = ? LIMIT 1");
    while (true) {
        $stmtCheck->execute([$orgId, $candidate]);
        if (!$stmtCheck->fetchColumn()) {
            break;
        }
        $nextNum++;
        $candidate = 'PROP-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
    }

    return $candidate;
}

// 6. Polymorphic Activity Logger
function log_proposal_activity(PDO $pdo, int $orgId, int $userId, string $type, string $title, string $description, int $proposalId): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                'proposals', ?, ?, NOW()
            )
        ");
        $stmt->execute([
            $orgId,
            $userId,
            $type,
            $title,
            $description,
            $proposalId,
            $userId
        ]);
    } catch (Throwable $e) {
        error_log("Failed to log proposal activity: " . $e->getMessage());
    }
}

// 7. Dynamic Status Evaluator
function compute_display_status(array $proposal): string
{
    $st = $proposal['status'] ?? 'Draft';
    if (!in_array($st, ['Accepted', 'Declined', 'Draft'])) {
        if (!empty($proposal['expiry_date'])) {
            $today = date('Y-m-d');
            if ($today > $proposal['expiry_date']) {
                return 'Expired';
            }
        }
    }
    return $st;
}

// Route Request Action
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'summary');

// CSRF & Method Guard on mutating actions
$mutatingActions = ['create', 'update', 'send', 'duplicate', 'delete'];
if (in_array($action, $mutatingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        proposals_json(false, 'Method Not Allowed. POST is required.', [], 405);
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
        proposals_json(false, 'Invalid or expired CSRF token.', [], 403);
    }
}

// =========================================================================
// ACTION: reference_options
// =========================================================================
if ($action === 'reference_options') {
    require_proposal_perm('view');

    $orgCurrency = get_org_currency($pdo, $organizationId);
    $currencySymbol = '$';
    if (preg_match('/\(([^)]+)\)/', $orgCurrency, $m)) {
        $currencySymbol = $m[1];
    } elseif (!empty($orgCurrency)) {
        $currencySymbol = $orgCurrency;
    }

    // Real Companies
    $stmtComp = $pdo->prepare("
        SELECT id, name, company_code, domain 
        FROM companies 
        WHERE organization_id = ? 
        ORDER BY name ASC
    ");
    $stmtComp->execute([$organizationId]);
    $companies = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

    // Real Contacts (including company_id for relationship linking)
    $stmtCont = $pdo->prepare("
        SELECT id, company_id, company_name, first_name, last_name, name, email, phone 
        FROM contacts 
        WHERE organization_id = ? 
        ORDER BY name ASC
    ");
    $stmtCont->execute([$organizationId]);
    $contacts = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    // Real Deals
    $stmtDeals = $pdo->prepare("
        SELECT id, name AS title, name, company, value, stage, status 
        FROM deals 
        WHERE organization_id = ? 
        ORDER BY id DESC
    ");
    $stmtDeals->execute([$organizationId]);
    $deals = $stmtDeals->fetchAll(PDO::FETCH_ASSOC);

    // Real Projects (including deal_id for consistency validation)
    $stmtProj = $pdo->prepare("
        SELECT id, project_code, name, client_name, deal_id 
        FROM projects 
        WHERE organization_id = ? 
        ORDER BY name ASC
    ");
    $stmtProj->execute([$organizationId]);
    $projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

    // Real Users
    $stmtUsers = $pdo->prepare("
        SELECT id, name, first_name, last_name, email, role 
        FROM users 
        WHERE organization_id = ? AND status = 'active' 
        ORDER BY name ASC
    ");
    $stmtUsers->execute([$organizationId]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Next sequential proposal number
    $nextPropNum = generate_proposal_number($pdo, $organizationId);

    proposals_json(true, 'Reference options retrieved.', [
        'companies'            => $companies,
        'contacts'             => $contacts,
        'deals'                => $deals,
        'projects'             => $projects,
        'users'                => $users,
        'currency'             => $orgCurrency,
        'currency_symbol'      => $currencySymbol,
        'next_proposal_number' => $nextPropNum,
        'statuses'             => ['Draft', 'Sent', 'Viewed', 'Changes Requested', 'Accepted', 'Declined', 'Expired'],
        'default_tax_rate'     => 18.00,
    ]);
}

// =========================================================================
// ACTION: summary (KPI cards)
// =========================================================================
if ($action === 'summary') {
    require_proposal_perm('view');

    $orgCurrency = get_org_currency($pdo, $organizationId);

    // Live SQL KPI aggregations
    $stmtKpi = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END), 0) AS count_draft,
            COALESCE(SUM(CASE WHEN status IN ('Sent', 'Viewed') AND (expiry_date >= CURDATE() OR expiry_date IS NULL) THEN 1 ELSE 0 END), 0) AS count_sent_viewed,
            COALESCE(SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END), 0) AS count_accepted,
            COALESCE(SUM(CASE WHEN status IN ('Declined', 'Expired') OR (status NOT IN ('Accepted', 'Declined', 'Draft') AND expiry_date < CURDATE()) THEN 1 ELSE 0 END), 0) AS count_declined_expired,
            COALESCE(SUM(total), 0) AS total_value
        FROM proposals
        WHERE organization_id = ?
    ");
    $stmtKpi->execute([$organizationId]);
    $kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [
        'total_count'            => 0,
        'count_draft'            => 0,
        'count_sent_viewed'      => 0,
        'count_accepted'         => 0,
        'count_declined_expired' => 0,
        'total_value'            => 0
    ];

    // Status counts
    $stmtStatus = $pdo->prepare("
        SELECT status, COUNT(*) as cnt
        FROM proposals
        WHERE organization_id = ?
        GROUP BY status
    ");
    $stmtStatus->execute([$organizationId]);
    $rawCounts = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR);

    $statusCounts = [
        'All'               => (int)$kpi['total_count'],
        'Draft'             => (int)($rawCounts['Draft'] ?? 0),
        'Sent'              => (int)($rawCounts['Sent'] ?? 0),
        'Viewed'            => (int)($rawCounts['Viewed'] ?? 0),
        'Changes Requested' => (int)($rawCounts['Changes Requested'] ?? 0),
        'Accepted'          => (int)($rawCounts['Accepted'] ?? 0),
        'Declined'          => (int)($rawCounts['Declined'] ?? 0),
        'Expired'           => (int)($rawCounts['Expired'] ?? 0),
    ];

    proposals_json(true, 'Summary loaded successfully.', [
        'kpi' => [
            'total_count'            => (int)$kpi['total_count'],
            'draft_proposals'        => (int)$kpi['count_draft'],
            'sent_viewed_proposals'  => (int)$kpi['count_sent_viewed'],
            'accepted_proposals'     => (int)$kpi['count_accepted'],
            'declined_expired'       => (int)$kpi['count_declined_expired'],
            'total_proposal_value'   => (float)$kpi['total_value'],
        ],
        'status_counts' => $statusCounts,
        'currency'      => $orgCurrency
    ]);
}

// =========================================================================
// ACTION: list
// =========================================================================
if ($action === 'list') {
    require_proposal_perm('view');

    $page       = max(1, (int)($_GET['page'] ?? 1));
    $perPage    = max(1, min(100, (int)($_GET['per_page'] ?? $_GET['pageSize'] ?? 8)));
    $offset     = ($page - 1) * $perPage;

    $search     = trim($_GET['search'] ?? $_GET['q'] ?? '');
    $status     = trim($_GET['status'] ?? '');
    $kpiKey     = trim($_GET['kpi'] ?? '');
    $preparedBy = trim($_GET['prepared_by'] ?? $_GET['preparedBy'] ?? '');
    $minAmount  = isset($_GET['amount_min']) && $_GET['amount_min'] !== '' ? (float)$_GET['amount_min'] : (isset($_GET['minAmt']) && $_GET['minAmt'] !== '' ? (float)$_GET['minAmt'] : null);
    $maxAmount  = isset($_GET['amount_max']) && $_GET['amount_max'] !== '' ? (float)$_GET['amount_max'] : (isset($_GET['maxAmt']) && $_GET['maxAmt'] !== '' ? (float)$_GET['maxAmt'] : null);
    $sortBy     = trim($_GET['sort'] ?? $_GET['sortBy'] ?? 'newest');

    $where   = ['p.organization_id = ?'];
    $params  = [$organizationId];

    // Search filter
    if ($search !== '') {
        $where[] = '(p.title LIKE ? OR p.proposal_number LIKE ? OR p.company_name LIKE ? OR p.deal_name LIKE ? OR p.project_name LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // KPI Card Filter
    if ($kpiKey !== '' && $kpiKey !== 'All') {
        if ($kpiKey === 'Draft') {
            $where[] = "p.status = 'Draft'";
        } elseif ($kpiKey === 'SentViewed') {
            $where[] = "p.status IN ('Sent', 'Viewed') AND (p.expiry_date >= CURDATE() OR p.expiry_date IS NULL)";
        } elseif ($kpiKey === 'Accepted') {
            $where[] = "p.status = 'Accepted'";
        } elseif ($kpiKey === 'DeclinedExpired') {
            $where[] = "(p.status IN ('Declined', 'Expired') OR (p.status NOT IN ('Accepted', 'Declined', 'Draft') AND p.expiry_date < CURDATE()))";
        }
    } elseif ($status !== '' && $status !== 'All') {
        // Status Dropdown filter
        if ($status === 'Expired') {
            $where[] = "(p.status = 'Expired' OR (p.status NOT IN ('Accepted', 'Declined', 'Draft') AND p.expiry_date < CURDATE()))";
        } else {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
    }

    // Multiple checked statuses
    if (!empty($_GET['checked_statuses'])) {
        $checked = is_array($_GET['checked_statuses']) ? $_GET['checked_statuses'] : explode(',', $_GET['checked_statuses']);
        $checked = array_filter(array_map('trim', $checked));
        if (!empty($checked)) {
            $inPlaceholders = implode(',', array_fill(0, count($checked), '?'));
            $where[] = "p.status IN ($inPlaceholders)";
            foreach ($checked as $chk) {
                $params[] = $chk;
            }
        }
    }

    // Prepared By filter
    if ($preparedBy !== '' && $preparedBy !== 'all') {
        if (is_numeric($preparedBy)) {
            $where[] = 'p.prepared_by = ?';
            $params[] = (int)$preparedBy;
        } else {
            $where[] = "(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ? OR u.name LIKE ?)";
            $params[] = '%' . $preparedBy . '%';
            $params[] = '%' . $preparedBy . '%';
        }
    }

    // Amount range
    if ($minAmount !== null) {
        $where[] = 'p.total >= ?';
        $params[] = $minAmount;
    }
    if ($maxAmount !== null) {
        $where[] = 'p.total <= ?';
        $params[] = $maxAmount;
    }

    $whereSql = implode(' AND ', $where);

    // Sorting
    $orderSql = 'p.issue_date DESC, p.id DESC';
    switch ($sortBy) {
        case 'oldest':
            $orderSql = 'p.issue_date ASC, p.id ASC';
            break;
        case 'amount-desc':
            $orderSql = 'p.total DESC, p.id DESC';
            break;
        case 'amount-asc':
            $orderSql = 'p.total ASC, p.id ASC';
            break;
        case 'title-asc':
            $orderSql = 'p.title ASC';
            break;
        default:
            $orderSql = 'p.issue_date DESC, p.id DESC';
            break;
    }

    // Count Total
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM proposals p
        LEFT JOIN users u ON u.id = p.prepared_by
        WHERE $whereSql
    ");
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalRecords / $perPage));

    // Fetch Records
    $stmtList = $pdo->prepare("
        SELECT 
            p.id,
            p.proposal_number,
            p.title,
            p.company_id,
            p.company_name,
            p.contact_id,
            p.contact_name,
            p.client_email,
            p.deal_id,
            p.deal_name,
            p.project_id,
            p.project_name,
            p.prepared_by,
            p.issue_date,
            p.expiry_date,
            p.scope,
            p.subtotal,
            p.discount,
            p.tax_rate,
            p.tax,
            p.total,
            p.currency,
            p.payment_terms,
            p.terms,
            p.status,
            p.change_request_type,
            p.change_request_message,
            p.change_requested_by,
            p.change_requested_at,
            p.created_at,
            p.updated_at,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS prepared_by_name
        FROM proposals p
        LEFT JOIN users u ON u.id = p.prepared_by
        WHERE $whereSql
        ORDER BY $orderSql
        LIMIT $perPage OFFSET $offset
    ");
    $stmtList->execute($params);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $row['display_status'] = compute_display_status($row);
        $curr = $row['currency'] ?: get_org_currency($pdo, $organizationId);
        
        // Extract currency symbol if enclosed in parens, e.g. "USD ($)" -> "$"
        $symbol = '$';
        if (preg_match('/\(([^)]+)\)/', $curr, $m)) {
            $symbol = $m[1];
        } elseif (!empty($curr)) {
            $symbol = $curr;
        }

        $row['formatted_total'] = $symbol . number_format((float)$row['total'], 2);

        // Compatible frontend mappings
        $row['client'] = $row['company_name'] ?: 'No Company';
        $row['deal'] = $row['deal_name'] ?: 'None';
        $row['project'] = $row['project_name'] ?: 'None';
        $row['amount'] = (float)$row['total'];
        $row['formattedTotal'] = $row['formatted_total'];
        $row['issueDate'] = $row['issue_date'];
        $row['expiryDate'] = $row['expiry_date'];
        $row['preparedBy'] = !empty($row['prepared_by_name']) ? $row['prepared_by_name'] : ($row['prepared_by'] ? 'User #' . $row['prepared_by'] : 'Unassigned');

        $items[] = $row;
    }

    proposals_json(true, 'Proposals retrieved successfully.', [
        'items'      => $items,
        'proposals'  => $items,
        'pagination' => [
            'total'       => $totalRecords,
            'total_items' => $totalRecords,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ],
    ]);
}

// =========================================================================
// ACTION: get
// =========================================================================
if ($action === 'get') {
    require_proposal_perm('view');

    $proposalId = (int)($_GET['id'] ?? 0);
    $propNumber = trim($_GET['proposal_number'] ?? $_GET['prop'] ?? '');

    if ($proposalId <= 0 && !empty($propNumber)) {
        $stmtFind = $pdo->prepare("SELECT id FROM proposals WHERE proposal_number = ? AND organization_id = ?");
        $stmtFind->execute([$propNumber, $organizationId]);
        $proposalId = (int)$stmtFind->fetchColumn();
    }

    if ($proposalId <= 0) {
        proposals_json(false, 'Valid proposal ID is required.', [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS prepared_by_name,
            u.email AS prepared_by_email
        FROM proposals p
        LEFT JOIN users u ON u.id = p.prepared_by
        WHERE p.id = ? AND p.organization_id = ?
        LIMIT 1
    ");
    $stmt->execute([$proposalId, $organizationId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        proposals_json(false, 'Proposal not found or access denied.', [], 404);
    }

    // Deliverables from proposal_items
    $stmtItems = $pdo->prepare("
        SELECT 
            id,
            name,
            description,
            qty,
            rate,
            amount,
            sort_order
        FROM proposal_items
        WHERE organization_id = ? AND proposal_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtItems->execute([$organizationId, $proposalId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Timeline / Activity History from polymorphic team_activities
    $stmtAct = $pdo->prepare("
        SELECT 
            a.id,
            a.activity_type,
            a.title,
            a.description,
            a.created_at,
            DATE_FORMAT(a.created_at, '%b %d, %Y') AS date,
            a.description AS text,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS author_name
        FROM team_activities a
        LEFT JOIN users u ON u.id = a.created_by
        WHERE a.organization_id = ? AND a.related_entity = 'proposals' AND a.related_entity_id = ?
        ORDER BY a.id DESC
    ");
    $stmtAct->execute([$organizationId, $proposalId]);
    $timeline = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    $proposal['display_status'] = compute_display_status($proposal);
    $curr = $proposal['currency'] ?: get_org_currency($pdo, $organizationId);
    $symbol = '$';
    if (preg_match('/\(([^)]+)\)/', $curr, $m)) {
        $symbol = $m[1];
    } elseif (!empty($curr)) {
        $symbol = $curr;
    }
    $proposal['formattedTotal'] = $symbol . number_format((float)$proposal['total'], 2);
    $proposal['formatted_total'] = $proposal['formattedTotal'];

    // Client and deal mappings for frontend
    $proposal['client'] = $proposal['company_name'] ?: ($proposal['contact_name'] ?: '');
    $proposal['deal'] = $proposal['deal_name'] ?: '';
    $proposal['project'] = $proposal['project_name'] ?: '';
    $proposal['preparedBy'] = !empty($proposal['prepared_by_name']) ? $proposal['prepared_by_name'] : ($proposal['prepared_by'] ? 'User #' . $proposal['prepared_by'] : '');
    $proposal['deliverables'] = array_map(function($it) {
        return [
            'id'          => $it['id'] ?? null,
            'name'        => $it['name'],
            'desc'        => $it['description'] ?: '',
            'description' => $it['description'] ?: '',
            'qty'         => (float)$it['qty'],
            'rate'        => (float)$it['rate'],
            'amount'      => (float)$it['amount']
        ];
    }, $items);
    $proposal['items'] = $proposal['deliverables'];
    $proposal['timeline'] = $timeline;
    $proposal['currency'] = $curr;

    if (!empty($proposal['change_request_message'])) {
        $proposal['changeRequest'] = [
            'type'        => $proposal['change_request_type'] ?: 'Scope',
            'message'     => $proposal['change_request_message'],
            'requestedBy' => $proposal['change_requested_by'] ?: 'Client',
            'date'        => !empty($proposal['change_requested_at']) ? date('M d, Y', strtotime($proposal['change_requested_at'])) : 'Recently'
        ];
    }

    proposals_json(true, 'Proposal details retrieved.', array_merge($proposal, [
        'proposal'     => $proposal,
        'deliverables' => $proposal['deliverables'],
        'items'        => $proposal['deliverables'],
        'timeline'     => $timeline,
        'currency'     => $curr
    ]));
}

// =========================================================================
// ACTION: create
// =========================================================================
if ($action === 'create') {
    require_proposal_perm('create');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $title        = trim($data['title'] ?? '');
    $companyId    = !empty($data['company_id']) ? (int)$data['company_id'] : null;
    $companyName  = trim($data['company_name'] ?? $data['client'] ?? '');
    $contactId    = !empty($data['contact_id']) ? (int)$data['contact_id'] : null;
    $contactName  = trim($data['contact_name'] ?? $data['contact'] ?? '');
    $clientEmail  = trim($data['client_email'] ?? $data['email'] ?? '');
    $dealId       = !empty($data['deal_id']) ? (int)$data['deal_id'] : null;
    $dealName     = trim($data['deal_name'] ?? $data['deal'] ?? '');
    $projectId    = !empty($data['project_id']) ? (int)$data['project_id'] : null;
    $projectName  = trim($data['project_name'] ?? $data['project'] ?? '');
    $preparedBy   = !empty($data['prepared_by']) ? (int)$data['prepared_by'] : (!empty($data['preparedById']) ? (int)$data['preparedById'] : null);
    $issueDate    = trim($data['issue_date'] ?? $data['issueDate'] ?? date('Y-m-d'));
    $expiryDate   = trim($data['expiry_date'] ?? $data['expiryDate'] ?? date('Y-m-d', strtotime('+30 days')));
    $scope        = trim($data['scope'] ?? '');
    $discount     = max(0.00, (float)($data['discount'] ?? 0));
    $taxRate      = isset($data['tax_rate']) ? (float)$data['tax_rate'] : 18.00;
    $paymentTerms = trim($data['payment_terms'] ?? $data['paymentTerms'] ?? '');
    $terms        = trim($data['terms'] ?? '');
    $targetStatus = trim($data['status'] ?? 'Draft');

    // Validation
    if (empty($title)) {
        proposals_json(false, 'Proposal title is required.', [], 422);
    }
    if (empty($issueDate) || empty($expiryDate)) {
        proposals_json(false, 'Issue date and Expiry date are required.', [], 422);
    }
    if ($expiryDate < $issueDate) {
        proposals_json(false, 'Expiry date cannot be earlier than issue date.', [], 422);
    }

    // Tenant Validation & Linkage: Contact & Company
    if ($contactId) {
        $stmtCt = $pdo->prepare("SELECT id, company_id, company_name, first_name, last_name, email FROM contacts WHERE id = ? AND organization_id = ?");
        $stmtCt->execute([$contactId, $organizationId]);
        $ctRow = $stmtCt->fetch(PDO::FETCH_ASSOC);
        if (!$ctRow) {
            proposals_json(false, 'The selected contact does not exist in your organization.', [], 422);
        }
        if (empty($contactName)) {
            $contactName = trim(($ctRow['first_name'] ?? '') . ' ' . ($ctRow['last_name'] ?? ''));
        }
        if (empty($clientEmail)) {
            $clientEmail = $ctRow['email'];
        }

        // If companyId is not explicitly provided, resolve from contact
        if (empty($companyId)) {
            if (!empty($ctRow['company_id'])) {
                $companyId = (int)$ctRow['company_id'];
            } else {
                $stmtJunc = $pdo->prepare("SELECT company_id FROM contact_companies WHERE contact_id = ? AND organization_id = ? LIMIT 1");
                $stmtJunc->execute([$contactId, $organizationId]);
                $juncCompId = $stmtJunc->fetchColumn();
                if ($juncCompId) {
                    $companyId = (int)$juncCompId;
                }
            }
            if (empty($companyId)) {
                proposals_json(false, 'The selected contact is not associated with any company. Please select a company.', [], 422);
            }
        } else {
            // Both contactId and companyId are provided: verify association
            $isAssociated = false;
            if (!empty($ctRow['company_id']) && (int)$ctRow['company_id'] === $companyId) {
                $isAssociated = true;
            } else {
                $stmtJunc = $pdo->prepare("SELECT 1 FROM contact_companies WHERE contact_id = ? AND company_id = ? AND organization_id = ? LIMIT 1");
                $stmtJunc->execute([$contactId, $companyId, $organizationId]);
                if ($stmtJunc->fetchColumn()) {
                    $isAssociated = true;
                }
            }
            if (!$isAssociated) {
                proposals_json(false, 'The selected contact is not associated with the selected company.', [], 422);
            }
        }
    }

    // Tenant Validation: Company
    if ($companyId) {
        $stmtC = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND organization_id = ?");
        $stmtC->execute([$companyId, $organizationId]);
        $cName = $stmtC->fetchColumn();
        if (!$cName) {
            proposals_json(false, 'The selected company does not exist in your organization.', [], 422);
        }
        $companyName = $cName;
    } else {
        proposals_json(false, 'Client / Company is required.', [], 422);
    }

    // Tenant Validation: Deal
    if ($dealId) {
        $stmtD = $pdo->prepare("SELECT name FROM deals WHERE id = ? AND organization_id = ?");
        $stmtD->execute([$dealId, $organizationId]);
        $dTitle = $stmtD->fetchColumn();
        if (!$dTitle) {
            proposals_json(false, 'The selected deal does not exist in your organization.', [], 422);
        }
        if (empty($dealName)) $dealName = $dTitle;
    }

    // Tenant Validation: Project
    if ($projectId) {
        $stmtP = $pdo->prepare("SELECT id, name, deal_id FROM projects WHERE id = ? AND organization_id = ?");
        $stmtP->execute([$projectId, $organizationId]);
        $pRow = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$pRow) {
            proposals_json(false, 'The selected project does not exist in your organization.', [], 422);
        }
        $projectName = $pRow['name'];
        if ($dealId && !empty($pRow['deal_id']) && (int)$pRow['deal_id'] !== $dealId) {
            proposals_json(false, 'The selected project does not belong to the selected deal.', [], 422);
        }
        if (!$dealId && !empty($pRow['deal_id'])) {
            $dealId = (int)$pRow['deal_id'];
            $stmtD = $pdo->prepare("SELECT name FROM deals WHERE id = ? AND organization_id = ?");
            $stmtD->execute([$dealId, $organizationId]);
            $dealName = (string)$stmtD->fetchColumn();
        }
    } else {
        $projectName = null;
    }

    // Tenant Validation: Prepared By (User)
    if ($preparedBy) {
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
        $stmtU->execute([$preparedBy, $organizationId]);
        if (!$stmtU->fetchColumn()) {
            proposals_json(false, 'The selected user for Prepared By is invalid or inactive.', [], 422);
        }
    } else {
        $preparedBy = $currentUserId;
    }

    // Deliverables calculation
    $submittedItems = $data['deliverables'] ?? $data['items'] ?? [];
    if (!is_array($submittedItems)) {
        $submittedItems = [];
    }

    $calcItems = [];
    $subtotal = 0.00;
    $order = 1;

    foreach ($submittedItems as $it) {
        $name = trim($it['name'] ?? '');
        if ($name === '') continue;
        $desc = trim($it['description'] ?? $it['desc'] ?? '');
        $qty  = max(0.01, (float)($it['qty'] ?? $it['quantity'] ?? 1.00));
        $rate = max(0.00, (float)($it['rate'] ?? $it['price'] ?? 0.00));
        $amt  = round($qty * $rate, 2);

        $calcItems[] = [
            'name'        => $name,
            'description' => $desc,
            'qty'         => $qty,
            'rate'        => $rate,
            'amount'      => $amt,
            'sort_order'  => $order++
        ];
        $subtotal += $amt;
    }

    $subtotal = round($subtotal, 2);
    $taxable  = max(0.00, round($subtotal - $discount, 2));
    $taxRate  = max(0.00, $taxRate);
    $tax      = round($taxable * ($taxRate / 100), 2);
    $total    = round($taxable + $tax, 2);

    $validStatuses = ['Draft', 'Sent', 'Viewed', 'Changes Requested', 'Accepted', 'Declined', 'Expired'];
    $finalStatus = in_array($targetStatus, $validStatuses) ? $targetStatus : 'Draft';
    $orgCurrency = get_org_currency($pdo, $organizationId);

    // Database Transaction
    $pdo->beginTransaction();
    try {
        $proposalNumber = generate_proposal_number($pdo, $organizationId);

        $stmtIns = $pdo->prepare("
            INSERT INTO proposals (
                organization_id, proposal_number, title,
                company_id, company_name, contact_id, contact_name, client_email,
                deal_id, deal_name, project_id, project_name, prepared_by,
                issue_date, expiry_date, scope,
                subtotal, discount, tax_rate, tax, total, currency,
                payment_terms, terms, status, created_by, created_at, updated_at
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, NOW(), NOW()
            )
        ");

        $stmtIns->execute([
            $organizationId,
            $proposalNumber,
            $title,
            $companyId,
            $companyName ?: null,
            $contactId,
            $contactName ?: null,
            $clientEmail ?: null,
            $dealId,
            $dealName ?: null,
            $projectId,
            $projectName ?: null,
            $preparedBy,
            $issueDate,
            $expiryDate,
            $scope ?: null,
            $subtotal,
            $discount,
            $taxRate,
            $tax,
            $total,
            $orgCurrency,
            $paymentTerms ?: null,
            $terms ?: null,
            $finalStatus,
            $currentUserId
        ]);

        $newProposalId = (int)$pdo->lastInsertId();

        // Insert Proposal Items
        if (!empty($calcItems)) {
            $stmtItemIns = $pdo->prepare("
                INSERT INTO proposal_items (
                    organization_id, proposal_id, name, description,
                    qty, rate, amount, sort_order, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, NOW()
                )
            ");

            foreach ($calcItems as $ci) {
                $stmtItemIns->execute([
                    $organizationId,
                    $newProposalId,
                    $ci['name'],
                    $ci['description'] ?: null,
                    $ci['qty'],
                    $ci['rate'],
                    $ci['amount'],
                    $ci['sort_order']
                ]);
            }
        }

        // Activity Logging in polymorphic team_activities
        $creatorName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?: ($currentUser['name'] ?? 'User');
        log_proposal_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'proposal_created',
            'Proposal Created',
            "Proposal {$proposalNumber} created as {$finalStatus} by {$creatorName}",
            $newProposalId
        );

        $pdo->commit();

        proposals_json(true, 'Proposal created successfully.', [
            'id'              => $newProposalId,
            'proposal_number' => $proposalNumber,
            'status'          => $finalStatus,
            'total'           => $total
        ], 201);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        proposals_json(false, 'Failed to create proposal: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: update
// =========================================================================
if ($action === 'update') {
    require_proposal_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $proposalId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    $propNumber = trim($data['proposal_number'] ?? $_GET['proposal_number'] ?? '');

    if ($proposalId <= 0 && !empty($propNumber)) {
        $stmtFind = $pdo->prepare("SELECT id FROM proposals WHERE proposal_number = ? AND organization_id = ?");
        $stmtFind->execute([$propNumber, $organizationId]);
        $proposalId = (int)$stmtFind->fetchColumn();
    }

    if ($proposalId <= 0) {
        proposals_json(false, 'Valid proposal ID is required for update.', [], 400);
    }

    // Verify existing record
    $stmtEx = $pdo->prepare("SELECT * FROM proposals WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$proposalId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        proposals_json(false, 'Proposal not found or access denied.', [], 404);
    }

    $title        = isset($data['title']) ? trim($data['title']) : $existing['title'];
    $companyId    = isset($data['company_id']) ? (!empty($data['company_id']) ? (int)$data['company_id'] : null) : $existing['company_id'];
    $companyName  = isset($data['company_name']) ? trim($data['company_name']) : (isset($data['client']) ? trim($data['client']) : $existing['company_name']);
    $contactId    = isset($data['contact_id']) ? (!empty($data['contact_id']) ? (int)$data['contact_id'] : null) : $existing['contact_id'];
    $contactName  = isset($data['contact_name']) ? trim($data['contact_name']) : (isset($data['contact']) ? trim($data['contact']) : $existing['contact_name']);
    $clientEmail  = isset($data['client_email']) ? trim($data['client_email']) : (isset($data['email']) ? trim($data['email']) : $existing['client_email']);
    $dealId       = isset($data['deal_id']) ? (!empty($data['deal_id']) ? (int)$data['deal_id'] : null) : $existing['deal_id'];
    $dealName     = isset($data['deal_name']) ? trim($data['deal_name']) : (isset($data['deal']) ? trim($data['deal']) : $existing['deal_name']);
    $projectId    = isset($data['project_id']) ? (!empty($data['project_id']) ? (int)$data['project_id'] : null) : $existing['project_id'];
    $projectName  = isset($data['project_name']) ? trim($data['project_name']) : (isset($data['project']) ? trim($data['project']) : $existing['project_name']);
    $preparedBy   = isset($data['prepared_by']) ? (!empty($data['prepared_by']) ? (int)$data['prepared_by'] : null) : $existing['prepared_by'];
    $issueDate    = isset($data['issue_date']) ? trim($data['issue_date']) : (isset($data['issueDate']) ? trim($data['issueDate']) : $existing['issue_date']);
    $expiryDate   = isset($data['expiry_date']) ? trim($data['expiry_date']) : (isset($data['expiryDate']) ? trim($data['expiryDate']) : $existing['expiry_date']);
    $scope        = isset($data['scope']) ? trim($data['scope']) : $existing['scope'];
    $discount     = isset($data['discount']) ? max(0.00, (float)$data['discount']) : (float)$existing['discount'];
    $taxRate      = isset($data['tax_rate']) ? (float)$data['tax_rate'] : (float)$existing['tax_rate'];
    $paymentTerms = isset($data['payment_terms']) ? trim($data['payment_terms']) : (isset($data['paymentTerms']) ? trim($data['paymentTerms']) : $existing['payment_terms']);
    $terms        = isset($data['terms']) ? trim($data['terms']) : $existing['terms'];
    $status       = isset($data['status']) ? trim($data['status']) : $existing['status'];

    if (empty($title)) {
        proposals_json(false, 'Proposal title cannot be empty.', [], 422);
    }
    if (!empty($issueDate) && !empty($expiryDate) && $expiryDate < $issueDate) {
        proposals_json(false, 'Expiry date cannot be earlier than issue date.', [], 422);
    }

    // Tenant Validation & Linkage: Contact & Company
    if ($contactId) {
        $stmtCt = $pdo->prepare("SELECT id, company_id, company_name, first_name, last_name, email FROM contacts WHERE id = ? AND organization_id = ?");
        $stmtCt->execute([$contactId, $organizationId]);
        $ctRow = $stmtCt->fetch(PDO::FETCH_ASSOC);
        if (!$ctRow) {
            proposals_json(false, 'The selected contact does not exist in your organization.', [], 422);
        }
        if (empty($contactName)) {
            $contactName = trim(($ctRow['first_name'] ?? '') . ' ' . ($ctRow['last_name'] ?? ''));
        }
        if (empty($clientEmail)) {
            $clientEmail = $ctRow['email'];
        }

        if (empty($companyId)) {
            if (!empty($ctRow['company_id'])) {
                $companyId = (int)$ctRow['company_id'];
            } else {
                $stmtJunc = $pdo->prepare("SELECT company_id FROM contact_companies WHERE contact_id = ? AND organization_id = ? LIMIT 1");
                $stmtJunc->execute([$contactId, $organizationId]);
                $juncCompId = $stmtJunc->fetchColumn();
                if ($juncCompId) {
                    $companyId = (int)$juncCompId;
                }
            }
            if (empty($companyId)) {
                proposals_json(false, 'The selected contact is not associated with any company. Please select a company.', [], 422);
            }
        } else {
            $isAssociated = false;
            if (!empty($ctRow['company_id']) && (int)$ctRow['company_id'] === $companyId) {
                $isAssociated = true;
            } else {
                $stmtJunc = $pdo->prepare("SELECT 1 FROM contact_companies WHERE contact_id = ? AND company_id = ? AND organization_id = ? LIMIT 1");
                $stmtJunc->execute([$contactId, $companyId, $organizationId]);
                if ($stmtJunc->fetchColumn()) {
                    $isAssociated = true;
                }
            }
            if (!$isAssociated) {
                proposals_json(false, 'The selected contact is not associated with the selected company.', [], 422);
            }
        }
    }

    // Tenant Validation: Company
    if ($companyId) {
        $stmtC = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND organization_id = ?");
        $stmtC->execute([$companyId, $organizationId]);
        $cName = $stmtC->fetchColumn();
        if (!$cName) {
            proposals_json(false, 'The selected company does not exist in your organization.', [], 422);
        }
        $companyName = $cName;
    } else {
        proposals_json(false, 'Client / Company is required.', [], 422);
    }

    // Tenant Validation: Deal
    if ($dealId) {
        $stmtD = $pdo->prepare("SELECT name FROM deals WHERE id = ? AND organization_id = ?");
        $stmtD->execute([$dealId, $organizationId]);
        $dTitle = $stmtD->fetchColumn();
        if (!$dTitle) {
            proposals_json(false, 'The selected deal does not exist in your organization.', [], 422);
        }
        if (empty($dealName)) $dealName = $dTitle;
    }

    // Tenant Validation: Project
    if ($projectId) {
        $stmtP = $pdo->prepare("SELECT id, name, deal_id FROM projects WHERE id = ? AND organization_id = ?");
        $stmtP->execute([$projectId, $organizationId]);
        $pRow = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$pRow) {
            proposals_json(false, 'The selected project does not exist in your organization.', [], 422);
        }
        $projectName = $pRow['name'];
        if ($dealId && !empty($pRow['deal_id']) && (int)$pRow['deal_id'] !== $dealId) {
            proposals_json(false, 'The selected project does not belong to the selected deal.', [], 422);
        }
        if (!$dealId && !empty($pRow['deal_id'])) {
            $dealId = (int)$pRow['deal_id'];
            $stmtD = $pdo->prepare("SELECT name FROM deals WHERE id = ? AND organization_id = ?");
            $stmtD->execute([$dealId, $organizationId]);
            $dealName = (string)$stmtD->fetchColumn();
        }
    } else {
        $projectName = null;
    }

    // Tenant Validation: Prepared By
    if ($preparedBy) {
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
        $stmtU->execute([$preparedBy, $organizationId]);
        if (!$stmtU->fetchColumn()) {
            proposals_json(false, 'The selected user for Prepared By is invalid or inactive.', [], 422);
        }
    } else {
        $preparedBy = $existing['prepared_by'] ?: $currentUserId;
    }

    // Deliverables recalculation if supplied
    $submittedItems = $data['deliverables'] ?? $data['items'] ?? null;
    $calcItems = [];
    $subtotal = 0.00;

    if (is_array($submittedItems)) {
        $order = 1;
        foreach ($submittedItems as $it) {
            $name = trim($it['name'] ?? '');
            if ($name === '') continue;
            $desc = trim($it['description'] ?? $it['desc'] ?? '');
            $qty  = max(0.01, (float)($it['qty'] ?? $it['quantity'] ?? 1.00));
            $rate = max(0.00, (float)($it['rate'] ?? $it['price'] ?? 0.00));
            $amt  = round($qty * $rate, 2);

            $calcItems[] = [
                'name'        => $name,
                'description' => $desc,
                'qty'         => $qty,
                'rate'        => $rate,
                'amount'      => $amt,
                'sort_order'  => $order++
            ];
            $subtotal += $amt;
        }
        $subtotal = round($subtotal, 2);
    } else {
        $subtotal = (float)$existing['subtotal'];
    }

    $taxable = max(0.00, round($subtotal - $discount, 2));
    $tax     = round($taxable * ($taxRate / 100), 2);
    $total   = round($taxable + $tax, 2);

    // Changes Requested transition rule:
    // If was Changes Requested and saving revised version, transition to Sent
    $wasChangesRequested = ($existing['status'] === 'Changes Requested');
    if ($wasChangesRequested && ($status === 'Draft' || $status === 'Changes Requested' || !empty($data['send_revised']))) {
        $status = 'Sent';
    }

    $pdo->beginTransaction();
    try {
        $stmtUpd = $pdo->prepare("
            UPDATE proposals SET
                title = ?, company_id = ?, company_name = ?, contact_id = ?,
                contact_name = ?, client_email = ?, deal_id = ?, deal_name = ?,
                project_id = ?, project_name = ?, prepared_by = ?,
                issue_date = ?, expiry_date = ?, scope = ?,
                subtotal = ?, discount = ?, tax_rate = ?, tax = ?, total = ?,
                payment_terms = ?, terms = ?, status = ?,
                updated_at = NOW()
            WHERE id = ? AND organization_id = ?
        ");

        $stmtUpd->execute([
            $title, $companyId, $companyName ?: null, $contactId,
            $contactName ?: null, $clientEmail ?: null, $dealId, $dealName ?: null,
            $projectId, $projectName ?: null, $preparedBy,
            $issueDate, $expiryDate, $scope ?: null,
            $subtotal, $discount, $taxRate, $tax, $total,
            $paymentTerms ?: null, $terms ?: null, $status,
            $proposalId, $organizationId
        ]);

        // If items were provided, delete old and replace
        if (is_array($submittedItems)) {
            $stmtDelItems = $pdo->prepare("DELETE FROM proposal_items WHERE organization_id = ? AND proposal_id = ?");
            $stmtDelItems->execute([$organizationId, $proposalId]);

            if (!empty($calcItems)) {
                $stmtItemIns = $pdo->prepare("
                    INSERT INTO proposal_items (
                        organization_id, proposal_id, name, description,
                        qty, rate, amount, sort_order, created_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, NOW()
                    )
                ");

                foreach ($calcItems as $ci) {
                    $stmtItemIns->execute([
                        $organizationId,
                        $proposalId,
                        $ci['name'],
                        $ci['description'] ?: null,
                        $ci['qty'],
                        $ci['rate'],
                        $ci['amount'],
                        $ci['sort_order']
                    ]);
                }
            }
        }

        // Activity log
        if ($wasChangesRequested && $status === 'Sent') {
            log_proposal_activity(
                $pdo,
                $organizationId,
                $currentUserId,
                'proposal_revised_sent',
                'Revised Proposal Sent',
                "Revised proposal {$existing['proposal_number']} sent to client.",
                $proposalId
            );
        } else {
            log_proposal_activity(
                $pdo,
                $organizationId,
                $currentUserId,
                'proposal_updated',
                'Proposal Updated',
                "Proposal {$existing['proposal_number']} details were updated.",
                $proposalId
            );
        }

        $pdo->commit();

        proposals_json(true, ($wasChangesRequested && $status === 'Sent') ? 'Revised proposal sent successfully.' : 'Proposal updated successfully.', [
            'id'              => $proposalId,
            'proposal_number' => $existing['proposal_number'],
            'status'          => $status,
            'total'           => $total
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        proposals_json(false, 'Failed to update proposal: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: send
// =========================================================================
if ($action === 'send') {
    require_proposal_perm('send');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $proposalId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    $propNumber = trim($data['proposal_number'] ?? $_GET['proposal_number'] ?? '');

    if ($proposalId <= 0 && !empty($propNumber)) {
        $stmtFind = $pdo->prepare("SELECT id FROM proposals WHERE proposal_number = ? AND organization_id = ?");
        $stmtFind->execute([$propNumber, $organizationId]);
        $proposalId = (int)$stmtFind->fetchColumn();
    }

    if ($proposalId <= 0) {
        proposals_json(false, 'Valid proposal ID is required.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT * FROM proposals WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$proposalId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        proposals_json(false, 'Proposal not found or access denied.', [], 404);
    }

    $wasChangesRequested = ($existing['status'] === 'Changes Requested');
    $newStatus = 'Sent';

    $stmtUpd = $pdo->prepare("
        UPDATE proposals 
        SET status = ?, updated_at = NOW() 
        WHERE id = ? AND organization_id = ?
    ");
    $stmtUpd->execute([$newStatus, $proposalId, $organizationId]);

    // Activity log
    log_proposal_activity(
        $pdo,
        $organizationId,
        $currentUserId,
        'proposal_sent',
        $wasChangesRequested ? 'Revised Proposal Sent' : 'Proposal Sent',
        $wasChangesRequested ? "Revised proposal {$existing['proposal_number']} was sent to client." : "Proposal {$existing['proposal_number']} was sent to client.",
        $proposalId
    );

    proposals_json(true, $wasChangesRequested ? 'Revised proposal sent successfully.' : 'The proposal has been sent to the client.', [
        'id'              => $proposalId,
        'proposal_number' => $existing['proposal_number'],
        'status'          => $newStatus,
    ]);
}

// =========================================================================
// ACTION: duplicate
// =========================================================================
if ($action === 'duplicate') {
    require_proposal_perm('create');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $proposalId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if ($proposalId <= 0) {
        proposals_json(false, 'Valid proposal ID is required to duplicate.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT * FROM proposals WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$proposalId, $organizationId]);
    $source = $stmtEx->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        proposals_json(false, 'Source proposal not found or access denied.', [], 404);
    }

    // Load source items
    $stmtItems = $pdo->prepare("
        SELECT name, description, qty, rate, amount, sort_order 
        FROM proposal_items 
        WHERE organization_id = ? AND proposal_id = ? 
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtItems->execute([$organizationId, $proposalId]);
    $sourceItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        $newPropNumber = generate_proposal_number($pdo, $organizationId);
        $newTitle = $source['title'] . ' (Copy)';

        $stmtIns = $pdo->prepare("
            INSERT INTO proposals (
                organization_id, proposal_number, title,
                company_id, company_name, contact_id, contact_name, client_email,
                deal_id, deal_name, project_id, project_name, prepared_by,
                issue_date, expiry_date, scope,
                subtotal, discount, tax_rate, tax, total, currency,
                payment_terms, terms, status,
                created_by, created_at, updated_at
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, 'Draft',
                ?, NOW(), NOW()
            )
        ");

        $stmtIns->execute([
            $organizationId,
            $newPropNumber,
            $newTitle,
            $source['company_id'],
            $source['company_name'],
            $source['contact_id'],
            $source['contact_name'],
            $source['client_email'],
            $source['deal_id'],
            $source['deal_name'],
            $source['project_id'],
            $source['project_name'],
            $currentUserId,
            $source['scope'],
            $source['subtotal'],
            $source['discount'],
            $source['tax_rate'],
            $source['tax'],
            $source['total'],
            $source['currency'],
            $source['payment_terms'],
            $source['terms'],
            $currentUserId
        ]);

        $duplicatedId = (int)$pdo->lastInsertId();

        // Copy items
        if (!empty($sourceItems)) {
            $stmtItemIns = $pdo->prepare("
                INSERT INTO proposal_items (
                    organization_id, proposal_id, name, description,
                    qty, rate, amount, sort_order, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, NOW()
                )
            ");

            foreach ($sourceItems as $si) {
                $stmtItemIns->execute([
                    $organizationId,
                    $duplicatedId,
                    $si['name'],
                    $si['description'],
                    $si['qty'],
                    $si['rate'],
                    $si['amount'],
                    $si['sort_order']
                ]);
            }
        }

        // Clean activity log for duplicated proposal
        log_proposal_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'proposal_duplicated',
            'Proposal Duplicated',
            "Proposal duplicated from {$source['proposal_number']}",
            $duplicatedId
        );

        $pdo->commit();

        proposals_json(true, 'Proposal duplicated successfully.', [
            'id'              => $duplicatedId,
            'proposal_number' => $newPropNumber,
            'title'           => $newTitle,
            'status'          => 'Draft'
        ], 201);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        proposals_json(false, 'Failed to duplicate proposal: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: delete
// =========================================================================
if ($action === 'delete') {
    require_proposal_perm('delete');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $proposalId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    $propNumber = trim($data['proposal_number'] ?? $_GET['proposal_number'] ?? '');

    if ($proposalId <= 0 && !empty($propNumber)) {
        $stmtFind = $pdo->prepare("SELECT id FROM proposals WHERE proposal_number = ? AND organization_id = ?");
        $stmtFind->execute([$propNumber, $organizationId]);
        $proposalId = (int)$stmtFind->fetchColumn();
    }

    if ($proposalId <= 0) {
        proposals_json(false, 'Valid proposal ID is required for deletion.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT proposal_number FROM proposals WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$proposalId, $organizationId]);
    $propNumber = $stmtEx->fetchColumn();

    if (!$propNumber) {
        proposals_json(false, 'Proposal not found or access denied.', [], 404);
    }

    $pdo->beginTransaction();
    try {
        // Cascade removes proposal_items via ON DELETE CASCADE
        $stmtDel = $pdo->prepare("DELETE FROM proposals WHERE id = ? AND organization_id = ?");
        $stmtDel->execute([$proposalId, $organizationId]);

        // Clean up polymorphic activities
        $stmtActDel = $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'proposals' AND related_entity_id = ?");
        $stmtActDel->execute([$organizationId, $proposalId]);

        // Clean up polymorphic notes if any
        $stmtNoteDel = $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'proposals' AND related_id = ?");
        $stmtNoteDel->execute([$organizationId, $proposalId]);

        $pdo->commit();

        proposals_json(true, 'Proposal has been deleted successfully.', [
            'id'              => $proposalId,
            'proposal_number' => $propNumber
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        proposals_json(false, 'Failed to delete proposal: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: export_csv
// =========================================================================
if ($action === 'export' || $action === 'export_csv') {
    require_proposal_perm('export');

    $stmtList = $pdo->prepare("
        SELECT 
            p.proposal_number,
            p.title,
            COALESCE(p.company_name, '') AS client_name,
            COALESCE(p.deal_name, '') AS deal_name,
            COALESCE(p.project_name, '') AS project_name,
            p.subtotal,
            p.discount,
            p.tax,
            p.total,
            p.status,
            p.issue_date,
            p.expiry_date,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS prepared_by_name
        FROM proposals p
        LEFT JOIN users u ON u.id = p.prepared_by
        WHERE p.organization_id = ?
        ORDER BY p.issue_date DESC, p.id DESC
    ");
    $stmtList->execute([$organizationId]);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'proposals_export_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Output BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");

    // CSV Headers
    fputcsv($out, [
        'Proposal Number',
        'Title',
        'Client',
        'Related Deal',
        'Related Project',
        'Subtotal',
        'Discount',
        'Tax',
        'Total Amount',
        'Status',
        'Issue Date',
        'Expiry Date',
        'Prepared By'
    ]);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['proposal_number'],
            $r['title'],
            $r['client_name'],
            $r['deal_name'],
            $r['project_name'],
            number_format((float)$r['subtotal'], 2, '.', ''),
            number_format((float)$r['discount'], 2, '.', ''),
            number_format((float)$r['tax'], 2, '.', ''),
            number_format((float)$r['total'], 2, '.', ''),
            $r['status'],
            $r['issue_date'],
            $r['expiry_date'],
            $r['prepared_by_name']
        ]);
    }

    fclose($out);
    exit;
}

// Fallback: Unknown Action
proposals_json(false, "Unknown action: {$action}", [], 400);
