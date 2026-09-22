<?php
/**
 * NexFlow CRM — Expenses Management API Controller
 * 
 * Handles multi-tenant Expense CRUD, live SQL KPI aggregations, receipt file upload & safe streaming,
 * polymorphic activities (team_activities) & notes (team_notes), CSV import/export, and reference lookups.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// JSON Response Helper
function expenses_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
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
    expenses_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    expenses_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Helper
function require_expense_perm(string $action): void
{
    if (!hasPermission('expenses', $action)) {
        expenses_json(false, "Forbidden: You do not have permission to {$action} expenses.", [], 403);
    }
}

$pdo = nexflow_db();

// Helper: Generate sequential, unique reference number per organization
function generate_expense_reference(PDO $pdo, int $orgId): string
{
    $year = date('Y');
    $prefix = "EXP-{$year}-";
    $stmt = $pdo->prepare("
        SELECT reference_number 
        FROM expenses 
        WHERE organization_id = ? AND reference_number LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$orgId, "{$prefix}%"]);
    $lastRef = $stmt->fetchColumn();
    $nextNum = 1;
    if ($lastRef && preg_match('/EXP-\d{4}-(\d+)/', $lastRef, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    return sprintf("EXP-%s-%04d", $year, $nextNum);
}

// Helper: Get organization currency code
function get_org_currency(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $curr = $stmt->fetchColumn();
    return !empty($curr) ? trim($curr) : 'USD ($)';
}

// Helper: Log polymorphic activity to team_activities
function log_expense_activity(PDO $pdo, int $orgId, ?int $userId, string $type, string $title, ?string $desc, int $expenseId): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities 
            (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'expenses', ?, ?, NOW())
        ");
        $stmt->execute([
            $orgId,
            $userId,
            $type,
            $title,
            $desc,
            $expenseId,
            $userId
        ]);
    } catch (Throwable $e) {
        // Suppress activity logging failure to not break primary transaction
    }
}

// Helper: Receipt file upload handler
function handle_receipt_upload(int $orgId, ?array $file): ?array
{
    if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/webp'
    ];

    $maxBytes = 10 * 1024 * 1024; // 10 MB
    if ($file['size'] > $maxBytes) {
        expenses_json(false, 'Receipt file exceeds maximum 10MB limit.', [], 422);
    }

    $clientName = basename($file['name']);
    $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        expenses_json(false, 'Invalid file type. Allowed: PDF, JPG, JPEG, PNG, WEBP.', [], 422);
    }

    // Verify MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realMime, $allowedMimes, true)) {
        expenses_json(false, 'Invalid file content. Real MIME: ' . $realMime, [], 422);
    }

    $targetDir = dirname(__DIR__, 2) . "/uploads/expenses/{$orgId}";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $randomName = 'rcpt_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $targetPath = "{$targetDir}/{$randomName}";
    $relPath = "uploads/expenses/{$orgId}/{$randomName}";

    $saved = is_uploaded_file($file['tmp_name'])
        ? move_uploaded_file($file['tmp_name'], $targetPath)
        : copy($file['tmp_name'], $targetPath);

    if (!$saved) {
        expenses_json(false, 'Failed to save receipt file to storage.', [], 500);
    }

    return [
        'filename' => $clientName,
        'path'     => $relPath,
        'mime'     => $realMime,
        'size'     => (int)$file['size']
    ];
}

// 3. Request Parsing
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$request = array_merge($_GET, $_POST, is_array($jsonInput) ? $jsonInput : []);
$action = trim($request['action'] ?? ($requestMethod === 'POST' ? 'create' : 'list'));

switch ($action) {

    // -------------------------------------------------------------------------
    // 1. KPI SUMMARY METRICS
    // -------------------------------------------------------------------------
    case 'kpis':
        require_expense_perm('view');

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(amount), 0) AS total_amount,
                
                SUM(CASE WHEN billing_type = 'Billable' THEN 1 ELSE 0 END) AS billable_count,
                COALESCE(SUM(CASE WHEN billing_type = 'Billable' THEN amount ELSE 0 END), 0) AS billable_amount,
                
                SUM(CASE WHEN billing_type = 'Non-Billable' THEN 1 ELSE 0 END) AS non_billable_count,
                COALESCE(SUM(CASE WHEN billing_type = 'Non-Billable' THEN amount ELSE 0 END), 0) AS non_billable_amount,
                
                SUM(CASE WHEN billing_type = 'Billable' AND invoice_status = 'Not Invoiced' THEN 1 ELSE 0 END) AS pending_invoice_count,
                COALESCE(SUM(CASE WHEN billing_type = 'Billable' AND invoice_status = 'Not Invoiced' THEN amount ELSE 0 END), 0) AS pending_invoice_amount,
                
                SUM(CASE WHEN billing_type = 'Billable' AND invoice_status = 'Invoiced' THEN 1 ELSE 0 END) AS invoiced_count,
                COALESCE(SUM(CASE WHEN billing_type = 'Billable' AND invoice_status = 'Invoiced' THEN amount ELSE 0 END), 0) AS invoiced_amount
            FROM expenses
            WHERE organization_id = :org_id
        ");
        $stmt->execute([':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $kpis = [
            'total_expenses' => [
                'count'  => (int)($row['total_count'] ?? 0),
                'amount' => (float)($row['total_amount'] ?? 0.0),
            ],
            'billable' => [
                'count'  => (int)($row['billable_count'] ?? 0),
                'amount' => (float)($row['billable_amount'] ?? 0.0),
            ],
            'non_billable' => [
                'count'  => (int)($row['non_billable_count'] ?? 0),
                'amount' => (float)($row['non_billable_amount'] ?? 0.0),
            ],
            'pending_invoice' => [
                'count'  => (int)($row['pending_invoice_count'] ?? 0),
                'amount' => (float)($row['pending_invoice_amount'] ?? 0.0),
            ],
            'invoiced' => [
                'count'  => (int)($row['invoiced_count'] ?? 0),
                'amount' => (float)($row['invoiced_amount'] ?? 0.0),
            ],
        ];

        expenses_json(true, 'KPI metrics retrieved successfully.', $kpis);
        break;

    // -------------------------------------------------------------------------
    // 2. REFERENCE OPTIONS (Users, Companies, Projects, Currency)
    // -------------------------------------------------------------------------
    case 'reference_options':
        require_expense_perm('view');

        // Users in organization
        $uStmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, role 
            FROM users 
            WHERE organization_id = ? AND status = 'active' 
            ORDER BY first_name ASC, last_name ASC
        ");
        $uStmt->execute([$organizationId]);
        $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

        // Companies in organization
        $cStmt = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? ORDER BY name ASC");
        $cStmt->execute([$organizationId]);
        $companies = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        // Projects in organization
        $pStmt = $pdo->prepare("SELECT id, name, client_name FROM projects WHERE organization_id = ? ORDER BY name ASC");
        $pStmt->execute([$organizationId]);
        $projects = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        $orgCurrency = get_org_currency($pdo, $organizationId);

        expenses_json(true, 'Reference options loaded.', [
            'users'        => $users,
            'companies'    => $companies,
            'projects'     => $projects,
            'org_currency' => $orgCurrency
        ]);
        break;

    // -------------------------------------------------------------------------
    // 3. LIST EXPENSES
    // -------------------------------------------------------------------------
    case 'list':
        require_expense_perm('view');

        $search       = trim($request['search'] ?? '');
        $category     = trim($request['category'] ?? 'All');
        $billingType  = trim($request['billing_type'] ?? 'All');
        $invoiceStat  = trim($request['invoice_status'] ?? 'All');
        $reimburseStat= trim($request['reimbursement_status'] ?? 'All');
        $summaryFilter= trim($request['summary_filter'] ?? 'All');
        $ownerFilter  = trim($request['owner'] ?? 'All');
        $dateRange    = trim($request['date_range'] ?? 'All');
        $startDate    = trim($request['start_date'] ?? '');
        $endDate      = trim($request['end_date'] ?? '');
        $minAmount    = isset($request['min_amount']) && is_numeric($request['min_amount']) ? (float)$request['min_amount'] : null;
        $maxAmount    = isset($request['max_amount']) && is_numeric($request['max_amount']) ? (float)$request['max_amount'] : null;
        $sortVal      = trim($request['sort'] ?? 'date-desc');
        $page         = max(1, (int)($request['page'] ?? 1));
        $pageSize     = max(1, min(100, (int)($request['page_size'] ?? 8)));

        $where = ['e.organization_id = :org_id'];
        $params = [':org_id' => $organizationId];

        // Search
        if ($search !== '') {
            $where[] = '(e.title LIKE :s1 OR e.reference_number LIKE :s2 OR e.merchant LIKE :s3 OR e.description LIKE :s4 OR e.invoice_reference LIKE :s5 OR c.name LIKE :s6 OR p.name LIKE :s7 OR CONCAT(u.first_name, " ", u.last_name) LIKE :s8)';
            $sParam = '%' . $search . '%';
            $params[':s1'] = $sParam;
            $params[':s2'] = $sParam;
            $params[':s3'] = $sParam;
            $params[':s4'] = $sParam;
            $params[':s5'] = $sParam;
            $params[':s6'] = $sParam;
            $params[':s7'] = $sParam;
            $params[':s8'] = $sParam;
        }

        // Category Filter
        if ($category !== '' && $category !== 'All') {
            $where[] = 'e.category = :category';
            $params[':category'] = $category;
        }

        // Billing Type Filter
        if ($billingType !== '' && $billingType !== 'All') {
            $where[] = 'e.billing_type = :billing_type';
            $params[':billing_type'] = $billingType;
        }

        // Invoice Status Filter
        if ($invoiceStat !== '' && $invoiceStat !== 'All') {
            $where[] = 'e.invoice_status = :invoice_status';
            $params[':invoice_status'] = $invoiceStat;
        }

        // Reimbursement Status Filter
        if ($reimburseStat !== '' && $reimburseStat !== 'All') {
            $where[] = 'e.reimbursement_status = :reimburse_status';
            $params[':reimburse_status'] = $reimburseStat;
        }

        // Summary Card Quick Filter
        if ($summaryFilter === 'Billable') {
            $where[] = "e.billing_type = 'Billable'";
        } elseif ($summaryFilter === 'NonBillable') {
            $where[] = "e.billing_type = 'Non-Billable'";
        } elseif ($summaryFilter === 'PendingInvoice') {
            $where[] = "e.billing_type = 'Billable' AND e.invoice_status = 'Not Invoiced'";
        } elseif ($summaryFilter === 'Invoiced') {
            $where[] = "e.billing_type = 'Billable' AND e.invoice_status = 'Invoiced'";
        }

        // Owner Filter
        if ($ownerFilter !== '' && $ownerFilter !== 'All') {
            if (is_numeric($ownerFilter)) {
                $where[] = 'e.owner_id = :owner_id';
                $params[':owner_id'] = (int)$ownerFilter;
            } else {
                $where[] = 'CONCAT(u.first_name, " ", u.last_name) = :owner_name';
                $params[':owner_name'] = $ownerFilter;
            }
        }

        // Date Range Presets
        if ($dateRange === 'today') {
            $where[] = 'e.expense_date = CURDATE()';
        } elseif ($dateRange === 'this_week') {
            $where[] = 'YEARWEEK(e.expense_date, 1) = YEARWEEK(CURDATE(), 1)';
        } elseif ($dateRange === 'this_month') {
            $where[] = 'e.expense_date BETWEEN DATE_FORMAT(CURDATE(), "%Y-%m-01") AND LAST_DAY(CURDATE())';
        } elseif ($dateRange === 'last_month') {
            $where[] = 'e.expense_date BETWEEN DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), "%Y-%m-01") AND LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))';
        } elseif ($dateRange === 'this_quarter') {
            $where[] = 'QUARTER(e.expense_date) = QUARTER(CURDATE()) AND YEAR(e.expense_date) = YEAR(CURDATE())';
        } elseif ($dateRange === 'this_year') {
            $where[] = 'YEAR(e.expense_date) = YEAR(CURDATE())';
        } elseif ($startDate !== '' && $endDate !== '') {
            $where[] = 'e.expense_date BETWEEN :start_date AND :end_date';
            $params[':start_date'] = $startDate;
            $params[':end_date']   = $endDate;
        }

        // Min / Max Amount
        if ($minAmount !== null) {
            $where[] = 'e.amount >= :min_amount';
            $params[':min_amount'] = $minAmount;
        }
        if ($maxAmount !== null) {
            $where[] = 'e.amount <= :max_amount';
            $params[':max_amount'] = $maxAmount;
        }

        $whereSql = implode(' AND ', $where);

        // Count Total
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM expenses e
            LEFT JOIN companies c ON c.id = e.company_id AND c.organization_id = e.organization_id
            LEFT JOIN projects p ON p.id = e.project_id AND p.organization_id = e.organization_id
            LEFT JOIN users u ON u.id = e.owner_id AND u.organization_id = e.organization_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        // Sorting
        $orderSql = 'e.expense_date DESC, e.id DESC';
        switch ($sortVal) {
            case 'date-asc':
                $orderSql = 'e.expense_date ASC, e.id ASC';
                break;
            case 'name-asc':
                $orderSql = 'e.title ASC';
                break;
            case 'name-desc':
                $orderSql = 'e.title DESC';
                break;
            case 'amount-desc':
                $orderSql = 'e.amount DESC';
                break;
            case 'amount-asc':
                $orderSql = 'e.amount ASC';
                break;
            case 'category-asc':
                $orderSql = 'e.category ASC';
                break;
            case 'company-asc':
                $orderSql = 'c.name ASC, e.id DESC';
                break;
            default:
                $orderSql = 'e.expense_date DESC, e.id DESC';
                break;
        }

        // Pagination
        $totalPages = (int)ceil($totalRecords / $pageSize);
        $offset = ($page - 1) * $pageSize;

        // Data Query
        $dataStmt = $pdo->prepare("
            SELECT 
                e.*,
                COALESCE(c.name, 'Internal') AS company_name,
                COALESCE(p.name, 'General') AS project_title,
                u.first_name AS owner_first_name,
                u.last_name AS owner_last_name,
                u.email AS owner_email,
                inv_u.first_name AS invoiced_by_first,
                inv_u.last_name AS invoiced_by_last
            FROM expenses e
            LEFT JOIN companies c ON c.id = e.company_id AND c.organization_id = e.organization_id
            LEFT JOIN projects p ON p.id = e.project_id AND p.organization_id = e.organization_id
            LEFT JOIN users u ON u.id = e.owner_id AND u.organization_id = e.organization_id
            LEFT JOIN users inv_u ON inv_u.id = e.invoiced_by AND inv_u.organization_id = e.organization_id
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$pageSize} OFFSET {$offset}
        ");
        $dataStmt->execute($params);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format items to match UI expectations
        $items = [];
        foreach ($rows as $r) {
            $ownerName = trim(($r['owner_first_name'] ?? '') . ' ' . ($r['owner_last_name'] ?? ''));
            if (empty($ownerName)) $ownerName = 'Unassigned';

            $isBillable = ($r['billing_type'] === 'Billable');
            $billingStatus = $isBillable ? $r['invoice_status'] : 'Non-Billable';

            $items[] = [
                'id'                  => (int)$r['id'],
                'refNumber'           => $r['reference_number'],
                'title'               => $r['title'],
                'category'            => $r['category'],
                'amount'              => (float)$r['amount'],
                'currency'            => $r['currency'],
                'tax_amount'          => (float)$r['tax_amount'],
                'date'                => $r['expense_date'],
                'company_id'          => $r['company_id'] ? (int)$r['company_id'] : null,
                'company'             => $r['company_name'],
                'project_id'          => $r['project_id'] ? (int)$r['project_id'] : null,
                'project'             => $r['project_title'],
                'billable'            => $isBillable,
                'billing_type'        => $r['billing_type'],
                'billingStatus'       => $billingStatus,
                'invoiceStatus'       => $r['invoice_status'],
                'invoiceId'           => $r['invoice_reference'] ?? '',
                'invoiced_at'         => $r['invoiced_at'],
                'reimbursementStatus' => $r['reimbursement_status'],
                'reimbursed_at'       => $r['reimbursed_at'],
                'paymentMethod'       => $r['payment_method'] ?? '',
                'merchant'            => $r['merchant'],
                'description'         => $r['description'] ?? '',
                'receipt'             => $r['receipt_filename'] ?? '',
                'receipt_path'        => $r['receipt_path'] ?? '',
                'receipt_mime'        => $r['receipt_mime'] ?? '',
                'receipt_size'        => $r['receipt_size'] ? (int)$r['receipt_size'] : 0,
                'owner_id'            => $r['owner_id'] ? (int)$r['owner_id'] : null,
                'owner'               => $ownerName,
                'created_at'          => $r['created_at'],
                'updated_at'          => $r['updated_at']
            ];
        }

        expenses_json(true, 'Expenses retrieved successfully.', [
            'items'         => $items,
            'total'         => $totalRecords,
            'page'          => $page,
            'page_size'     => $pageSize,
            'total_pages'   => $totalPages,
            'has_more'      => $page < $totalPages
        ]);
        break;

    // -------------------------------------------------------------------------
    // 4. GET SINGLE EXPENSE (With Drawer Details & Activities)
    // -------------------------------------------------------------------------
    case 'get':
        require_expense_perm('view');

        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            expenses_json(false, 'Invalid expense ID.', [], 400);
        }

        $stmt = $pdo->prepare("
            SELECT 
                e.*,
                COALESCE(c.name, 'Internal') AS company_name,
                COALESCE(p.name, 'General') AS project_title,
                u.first_name AS owner_first_name,
                u.last_name AS owner_last_name,
                u.email AS owner_email,
                cu.first_name AS creator_first,
                cu.last_name AS creator_last
            FROM expenses e
            LEFT JOIN companies c ON c.id = e.company_id AND c.organization_id = e.organization_id
            LEFT JOIN projects p ON p.id = e.project_id AND p.organization_id = e.organization_id
            LEFT JOIN users u ON u.id = e.owner_id AND u.organization_id = e.organization_id
            LEFT JOIN users cu ON cu.id = e.created_by AND cu.organization_id = e.organization_id
            WHERE e.id = :id AND e.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exp) {
            expenses_json(false, 'Expense not found or access denied.', [], 404);
        }

        // Fetch Timeline Activities from team_activities
        $actStmt = $pdo->prepare("
            SELECT 
                a.id, a.activity_type, a.title, a.description, a.created_at,
                CONCAT(COALESCE(u.first_name, 'Team'), ' ', COALESCE(u.last_name, 'Member')) AS user_name
            FROM team_activities a
            LEFT JOIN users u ON u.id = a.user_id AND u.organization_id = a.organization_id
            WHERE a.organization_id = :org_id AND a.related_entity = 'expenses' AND a.related_entity_id = :id
            ORDER BY a.created_at DESC, a.id DESC
        ");
        $actStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Notes from team_notes
        $noteStmt = $pdo->prepare("
            SELECT 
                n.id, n.title, n.content, n.created_at,
                CONCAT(COALESCE(u.first_name, 'Team'), ' ', COALESCE(u.last_name, 'Member')) AS author_name
            FROM team_notes n
            LEFT JOIN users u ON u.id = n.created_by AND u.organization_id = n.organization_id
            WHERE n.organization_id = :org_id AND n.related_type = 'expenses' AND n.related_id = :id
            ORDER BY n.created_at DESC, n.id DESC
        ");
        $noteStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $notes = $noteStmt->fetchAll(PDO::FETCH_ASSOC);

        $ownerName = trim(($exp['owner_first_name'] ?? '') . ' ' . ($exp['owner_last_name'] ?? ''));
        if (empty($ownerName)) $ownerName = 'Unassigned';

        $isBillable = ($exp['billing_type'] === 'Billable');
        $billingStatus = $isBillable ? $exp['invoice_status'] : 'Non-Billable';

        $detail = [
            'id'                  => (int)$exp['id'],
            'refNumber'           => $exp['reference_number'],
            'title'               => $exp['title'],
            'category'            => $exp['category'],
            'amount'              => (float)$exp['amount'],
            'currency'            => $exp['currency'],
            'tax_amount'          => (float)$exp['tax_amount'],
            'date'                => $exp['expense_date'],
            'company_id'          => $exp['company_id'] ? (int)$exp['company_id'] : null,
            'company'             => $exp['company_name'],
            'project_id'          => $exp['project_id'] ? (int)$exp['project_id'] : null,
            'project'             => $exp['project_title'],
            'billable'            => $isBillable,
            'billing_type'        => $exp['billing_type'],
            'billingStatus'       => $billingStatus,
            'invoiceStatus'       => $exp['invoice_status'],
            'invoiceId'           => $exp['invoice_reference'] ?? '',
            'invoiced_at'         => $exp['invoiced_at'],
            'reimbursementStatus' => $exp['reimbursement_status'],
            'reimbursed_at'       => $exp['reimbursed_at'],
            'paymentMethod'       => $exp['payment_method'] ?? '',
            'merchant'            => $exp['merchant'],
            'description'         => $exp['description'] ?? '',
            'receipt'             => $exp['receipt_filename'] ?? '',
            'receipt_path'        => $exp['receipt_path'] ?? '',
            'receipt_mime'        => $exp['receipt_mime'] ?? '',
            'receipt_size'        => $exp['receipt_size'] ? (int)$exp['receipt_size'] : 0,
            'owner_id'            => $exp['owner_id'] ? (int)$exp['owner_id'] : null,
            'owner'               => $ownerName,
            'created_at'          => $exp['created_at'],
            'updated_at'          => $exp['updated_at'],
            'activity'            => array_map(function($a) {
                return [
                    'id'   => (int)$a['id'],
                    'date' => date('M d, Y • h:i A', strtotime($a['created_at'])),
                    'user' => $a['user_name'],
                    'text' => $a['description'] ?: $a['title']
                ];
            }, $activities),
            'notes'               => array_map(function($n) {
                return [
                    'id'      => (int)$n['id'],
                    'content' => $n['content'],
                    'date'    => date('M d, Y • h:i A', strtotime($n['created_at'])),
                    'author'  => $n['author_name']
                ];
            }, $notes)
        ];

        expenses_json(true, 'Expense details retrieved.', $detail);
        break;

    // -------------------------------------------------------------------------
    // 5. CREATE EXPENSE
    // -------------------------------------------------------------------------
    case 'create':
        require_expense_perm('create');

        $title        = trim($request['title'] ?? $request['name'] ?? '');
        $category     = trim($request['category'] ?? '');
        $amount       = isset($request['amount']) ? (float)$request['amount'] : 0.0;
        $date         = trim($request['date'] ?? $request['expense_date'] ?? date('Y-m-d'));
        $billingType  = trim($request['billing_type'] ?? 'Billable');
        $invoiceStat  = trim($request['invoice_status'] ?? 'Not Invoiced');
        $reimburseStat= trim($request['reimbursement_status'] ?? 'N/A');
        $merchant     = trim($request['merchant'] ?? 'Direct Vendor');
        $companyId    = !empty($request['company_id']) ? (int)$request['company_id'] : null;
        $projectId    = !empty($request['project_id']) ? (int)$request['project_id'] : null;
        $ownerId      = !empty($request['owner_id']) ? (int)$request['owner_id'] : null;
        $ownerName    = trim($request['owner'] ?? '');
        $companyName  = trim($request['company'] ?? '');
        $projectName  = trim($request['project'] ?? '');
        $paymentMethod= trim($request['payment_method'] ?? 'Corporate Card');
        $description  = trim($request['description'] ?? '');
        $taxAmount    = isset($request['tax_amount']) ? max(0.0, (float)$request['tax_amount']) : 0.0;
        $currency     = trim($request['currency'] ?? '');

        if ($currency === '') {
            $currency = get_org_currency($pdo, $organizationId);
        }

        // Server-Side Validations
        if ($title === '') {
            expenses_json(false, 'Expense Name is required.', [], 422);
        }
        if ($category === '') {
            expenses_json(false, 'Category is required.', [], 422);
        }
        if ($amount <= 0) {
            expenses_json(false, 'Amount must be a positive number greater than 0.', [], 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            expenses_json(false, 'Valid expense date is required (YYYY-MM-DD).', [], 422);
        }
        if (!in_array($billingType, ['Billable', 'Non-Billable'], true)) {
            expenses_json(false, 'Billing Type must be either "Billable" or "Non-Billable".', [], 422);
        }
        if (!in_array($reimburseStat, ['N/A', 'Pending', 'Reimbursed'], true)) {
            $reimburseStat = 'N/A';
        }

        // Non-Billable Rule: Invoiced status not allowed for non-billable
        if ($billingType === 'Non-Billable') {
            $invoiceStat = 'Not Invoiced';
            $invoiceRef = null;
        } else {
            if (!in_array($invoiceStat, ['Not Invoiced', 'Invoiced'], true)) {
                $invoiceStat = 'Not Invoiced';
            }
            $invoiceRef = trim($request['invoice_reference'] ?? $request['invoiceId'] ?? '');
            if ($invoiceStat === 'Invoiced' && empty($invoiceRef)) {
                $invoiceRef = 'INV-' . date('Y') . '-' . substr((string)time(), -4);
            }
        }

        // Resolve Owner within Tenant
        if ($ownerId) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
            $chk->execute([$ownerId, $organizationId]);
            if (!$chk->fetchColumn()) {
                expenses_json(false, 'Selected owner does not belong to your organization.', [], 422);
            }
        } elseif ($ownerName !== '') {
            $chk = $pdo->prepare("SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) = ? AND organization_id = ? LIMIT 1");
            $chk->execute([$ownerName, $organizationId]);
            $ownerId = (int)$chk->fetchColumn() ?: null;
        }
        if (!$ownerId) {
            // Default to current user if not specified
            $ownerId = $currentUserId;
        }

        // Resolve Company within Tenant
        if ($companyId) {
            $chk = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
            $chk->execute([$companyId, $organizationId]);
            if (!$chk->fetchColumn()) {
                expenses_json(false, 'Selected company does not belong to your organization.', [], 422);
            }
        } elseif ($companyName !== '' && strtolower($companyName) !== 'internal') {
            $chk = $pdo->prepare("SELECT id FROM companies WHERE name = ? AND organization_id = ? LIMIT 1");
            $chk->execute([$companyName, $organizationId]);
            $companyId = (int)$chk->fetchColumn() ?: null;
        }

        // Resolve Project within Tenant
        if ($projectId) {
            $chk = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
            $chk->execute([$projectId, $organizationId]);
            if (!$chk->fetchColumn()) {
                expenses_json(false, 'Selected project does not belong to your organization.', [], 422);
            }
        } elseif ($projectName !== '' && strtolower($projectName) !== 'general') {
            $chk = $pdo->prepare("SELECT id FROM projects WHERE name = ? AND organization_id = ? LIMIT 1");
            $chk->execute([$projectName, $organizationId]);
            $projectId = (int)$chk->fetchColumn() ?: null;
        }

        // Generate Unique Reference Number
        $customRef = trim($request['reference_number'] ?? $request['refNumber'] ?? '');
        if ($customRef !== '') {
            // Check uniqueness in tenant
            $chkRef = $pdo->prepare("SELECT id FROM expenses WHERE organization_id = ? AND reference_number = ? LIMIT 1");
            $chkRef->execute([$organizationId, $customRef]);
            if ($chkRef->fetchColumn()) {
                $referenceNumber = generate_expense_reference($pdo, $organizationId);
            } else {
                $referenceNumber = $customRef;
            }
        } else {
            $referenceNumber = generate_expense_reference($pdo, $organizationId);
        }

        // File Upload
        $receiptInfo = null;
        if (!empty($_FILES['receipt_file'])) {
            $receiptInfo = handle_receipt_upload($organizationId, $_FILES['receipt_file']);
        } elseif (!empty($_FILES['receipt'])) {
            $receiptInfo = handle_receipt_upload($organizationId, $_FILES['receipt']);
        }

        // Timestamps & actors
        $invoicedAt = ($invoiceStat === 'Invoiced') ? date('Y-m-d H:i:s') : null;
        $invoicedBy = ($invoiceStat === 'Invoiced') ? $currentUserId : null;
        $reimbursedAt = ($reimburseStat === 'Reimbursed') ? date('Y-m-d H:i:s') : null;

        // Insert Record
        $insStmt = $pdo->prepare("
            INSERT INTO expenses (
                organization_id, reference_number, title, expense_date, merchant, category,
                amount, currency, tax_amount, billing_type, invoice_status, invoice_reference,
                invoiced_at, invoiced_by, reimbursement_status, reimbursed_at,
                company_id, project_id, payment_method, description,
                receipt_filename, receipt_path, receipt_mime, receipt_size,
                owner_id, created_by, updated_by, created_at, updated_at
            ) VALUES (
                :org_id, :ref_no, :title, :exp_date, :merchant, :category,
                :amount, :currency, :tax_amount, :billing_type, :inv_status, :inv_ref,
                :inv_at, :inv_by, :reimb_status, :reimb_at,
                :company_id, :project_id, :payment_method, :description,
                :rcpt_fn, :rcpt_path, :rcpt_mime, :rcpt_size,
                :owner_id, :created_by, :updated_by, NOW(), NOW()
            )
        ");

        $insStmt->execute([
            ':org_id'        => $organizationId,
            ':ref_no'        => $referenceNumber,
            ':title'         => $title,
            ':exp_date'      => $date,
            ':merchant'      => $merchant,
            ':category'      => $category,
            ':amount'        => $amount,
            ':currency'      => $currency,
            ':tax_amount'    => $taxAmount,
            ':billing_type'  => $billingType,
            ':inv_status'    => $invoiceStat,
            ':inv_ref'       => $invoiceRef,
            ':inv_at'        => $invoicedAt,
            ':inv_by'        => $invoicedBy,
            ':reimb_status'  => $reimburseStat,
            ':reimb_at'      => $reimbursedAt,
            ':company_id'    => $companyId,
            ':project_id'    => $projectId,
            ':payment_method'=> $paymentMethod,
            ':description'   => $description,
            ':rcpt_fn'       => $receiptInfo['filename'] ?? null,
            ':rcpt_path'     => $receiptInfo['path'] ?? null,
            ':rcpt_mime'     => $receiptInfo['mime'] ?? null,
            ':rcpt_size'     => $receiptInfo['size'] ?? null,
            ':owner_id'      => $ownerId,
            ':created_by'    => $currentUserId,
            ':updated_by'    => $currentUserId,
        ]);

        $newId = (int)$pdo->lastInsertId();

        // Log Activity in team_activities
        log_expense_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'created',
            "Recorded expense {$referenceNumber}",
            "Expense '{$title}' recorded for amount $" . number_format($amount, 2) . " ({$merchant}).",
            $newId
        );

        expenses_json(true, "Expense '{$title}' recorded successfully.", [
            'id'               => $newId,
            'reference_number' => $referenceNumber
        ], 201);
        break;

    // -------------------------------------------------------------------------
    // 6. UPDATE EXPENSE
    // -------------------------------------------------------------------------
    case 'update':
        require_expense_perm('edit');

        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            expenses_json(false, 'Invalid expense ID.', [], 400);
        }

        // Verify tenant ownership
        $chk = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$id, $organizationId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            expenses_json(false, 'Expense record not found or access denied.', [], 404);
        }

        $title        = trim($request['title'] ?? $existing['title']);
        $category     = trim($request['category'] ?? $existing['category']);
        $amount       = isset($request['amount']) ? (float)$request['amount'] : (float)$existing['amount'];
        $date         = trim($request['date'] ?? $request['expense_date'] ?? $existing['expense_date']);
        $billingType  = trim($request['billing_type'] ?? $existing['billing_type']);
        $invoiceStat  = trim($request['invoice_status'] ?? $existing['invoice_status']);
        $invoiceRef   = trim($request['invoice_reference'] ?? $request['invoiceId'] ?? ($existing['invoice_reference'] ?? ''));
        $reimburseStat= trim($request['reimbursement_status'] ?? $existing['reimbursement_status']);
        $merchant     = trim($request['merchant'] ?? $existing['merchant']);
        $paymentMethod= trim($request['payment_method'] ?? ($existing['payment_method'] ?? ''));
        $description  = trim($request['description'] ?? ($existing['description'] ?? ''));
        $taxAmount    = isset($request['tax_amount']) ? max(0.0, (float)$request['tax_amount']) : (float)$existing['tax_amount'];

        if ($title === '') {
            expenses_json(false, 'Expense Name is required.', [], 422);
        }
        if ($amount <= 0) {
            expenses_json(false, 'Amount must be a positive number greater than 0.', [], 422);
        }
        if (!in_array($billingType, ['Billable', 'Non-Billable'], true)) {
            $billingType = 'Billable';
        }

        // Non-billable rule
        if ($billingType === 'Non-Billable') {
            $invoiceStat = 'Not Invoiced';
            $invoiceRef = null;
        }

        // Resolve Company
        $companyId = $existing['company_id'];
        if (isset($request['company_id'])) {
            $cId = (int)$request['company_id'];
            if ($cId > 0) {
                $cChk = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
                $cChk->execute([$cId, $organizationId]);
                $companyId = $cChk->fetchColumn() ? $cId : null;
            } else {
                $companyId = null;
            }
        } elseif (!empty($request['company'])) {
            $cChk = $pdo->prepare("SELECT id FROM companies WHERE name = ? AND organization_id = ? LIMIT 1");
            $cChk->execute([trim($request['company']), $organizationId]);
            $cId = (int)$cChk->fetchColumn();
            $companyId = $cId ?: null;
        }

        // Resolve Project
        $projectId = $existing['project_id'];
        if (isset($request['project_id'])) {
            $pId = (int)$request['project_id'];
            if ($pId > 0) {
                $pChk = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
                $pChk->execute([$pId, $organizationId]);
                $projectId = $pChk->fetchColumn() ? $pId : null;
            } else {
                $projectId = null;
            }
        } elseif (!empty($request['project'])) {
            $pChk = $pdo->prepare("SELECT id FROM projects WHERE name = ? AND organization_id = ? LIMIT 1");
            $pChk->execute([trim($request['project']), $organizationId]);
            $pId = (int)$pChk->fetchColumn();
            $projectId = $pId ?: null;
        }

        // Resolve Owner
        $ownerId = $existing['owner_id'];
        if (isset($request['owner_id']) && (int)$request['owner_id'] > 0) {
            $uChk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
            $uChk->execute([(int)$request['owner_id'], $organizationId]);
            if ($uChk->fetchColumn()) {
                $ownerId = (int)$request['owner_id'];
            }
        } elseif (!empty($request['owner'])) {
            $uChk = $pdo->prepare("SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) = ? AND organization_id = ? LIMIT 1");
            $uChk->execute([trim($request['owner']), $organizationId]);
            $uId = (int)$uChk->fetchColumn();
            if ($uId) $ownerId = $uId;
        }

        // Receipt Upload (optional replacement)
        $receiptFilename = $existing['receipt_filename'];
        $receiptPath     = $existing['receipt_path'];
        $receiptMime     = $existing['receipt_mime'];
        $receiptSize     = $existing['receipt_size'];

        $receiptInfo = null;
        if (!empty($_FILES['receipt_file'])) {
            $receiptInfo = handle_receipt_upload($organizationId, $_FILES['receipt_file']);
        } elseif (!empty($_FILES['receipt'])) {
            $receiptInfo = handle_receipt_upload($organizationId, $_FILES['receipt']);
        }

        if ($receiptInfo) {
            // Delete old file if present
            if ($receiptPath && file_exists(dirname(__DIR__, 2) . '/' . $receiptPath)) {
                @unlink(dirname(__DIR__, 2) . '/' . $receiptPath);
            }
            $receiptFilename = $receiptInfo['filename'];
            $receiptPath     = $receiptInfo['path'];
            $receiptMime     = $receiptInfo['mime'];
            $receiptSize     = $receiptInfo['size'];
        }

        // Invoiced At / Invoiced By timestamps
        $invoicedAt = $existing['invoiced_at'];
        $invoicedBy = $existing['invoiced_by'];
        if ($invoiceStat === 'Invoiced' && $existing['invoice_status'] !== 'Invoiced') {
            $invoicedAt = date('Y-m-d H:i:s');
            $invoicedBy = $currentUserId;
        } elseif ($invoiceStat === 'Not Invoiced') {
            $invoicedAt = null;
            $invoicedBy = null;
            $invoiceRef = null;
        }

        // Reimbursement timestamps
        $reimbursedAt = $existing['reimbursed_at'];
        if ($reimburseStat === 'Reimbursed' && $existing['reimbursement_status'] !== 'Reimbursed') {
            $reimbursedAt = date('Y-m-d H:i:s');
        } elseif ($reimburseStat !== 'Reimbursed') {
            $reimbursedAt = null;
        }

        // Update
        $upStmt = $pdo->prepare("
            UPDATE expenses SET
                title = :title,
                category = :category,
                amount = :amount,
                expense_date = :exp_date,
                merchant = :merchant,
                tax_amount = :tax_amount,
                billing_type = :billing_type,
                invoice_status = :inv_status,
                invoice_reference = :inv_ref,
                invoiced_at = :inv_at,
                invoiced_by = :inv_by,
                reimbursement_status = :reimb_status,
                reimbursed_at = :reimb_at,
                company_id = :company_id,
                project_id = :project_id,
                owner_id = :owner_id,
                payment_method = :payment_method,
                description = :description,
                receipt_filename = :rcpt_fn,
                receipt_path = :rcpt_path,
                receipt_mime = :rcpt_mime,
                receipt_size = :rcpt_size,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $upStmt->execute([
            ':title'         => $title,
            ':category'      => $category,
            ':amount'        => $amount,
            ':exp_date'      => $date,
            ':merchant'      => $merchant,
            ':tax_amount'    => $taxAmount,
            ':billing_type'  => $billingType,
            ':inv_status'    => $invoiceStat,
            ':inv_ref'       => $invoiceRef,
            ':inv_at'        => $invoicedAt,
            ':inv_by'        => $invoicedBy,
            ':reimb_status'  => $reimburseStat,
            ':reimb_at'      => $reimbursedAt,
            ':company_id'    => $companyId,
            ':project_id'    => $projectId,
            ':owner_id'      => $ownerId,
            ':payment_method'=> $paymentMethod,
            ':description'   => $description,
            ':rcpt_fn'       => $receiptFilename,
            ':rcpt_path'     => $receiptPath,
            ':rcpt_mime'     => $receiptMime,
            ':rcpt_size'     => $receiptSize,
            ':updated_by'    => $currentUserId,
            ':id'            => $id,
            ':org_id'        => $organizationId
        ]);

        // Log Activity
        log_expense_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'updated',
            "Updated expense {$existing['reference_number']}",
            "Expense parameters updated for '{$title}'.",
            $id
        );

        expenses_json(true, 'Expense updated successfully.', ['id' => $id]);
        break;

    // -------------------------------------------------------------------------
    // 7. DELETE SINGLE EXPENSE
    // -------------------------------------------------------------------------
    case 'delete':
        require_expense_perm('delete');

        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            expenses_json(false, 'Invalid expense ID.', [], 400);
        }

        // Verify tenant ownership
        $stmt = $pdo->prepare("SELECT id, reference_number, receipt_path FROM expenses WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmt->execute([$id, $organizationId]);
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exp) {
            expenses_json(false, 'Expense not found or access denied.', [], 404);
        }

        // Remove receipt file if present
        if (!empty($exp['receipt_path'])) {
            $fullPath = dirname(__DIR__, 2) . '/' . $exp['receipt_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        // Clean up polymorphic shared records
        $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'expenses' AND related_entity_id = ?")->execute([$organizationId, $id]);
        $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'expenses' AND related_id = ?")->execute([$organizationId, $id]);
        $pdo->prepare("DELETE FROM tasks WHERE organization_id = ? AND related_type = 'expenses' AND related_id = ?")->execute([$organizationId, $id]);
        $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = ? AND related_type = 'expenses' AND related_id = ?")->execute([$organizationId, $id]);

        // Delete expense
        $pdo->prepare("DELETE FROM expenses WHERE id = ? AND organization_id = ?")->execute([$id, $organizationId]);

        expenses_json(true, "Expense {$exp['reference_number']} deleted successfully.", ['id' => $id]);
        break;

    // -------------------------------------------------------------------------
    // 8. BULK DELETE EXPENSES
    // -------------------------------------------------------------------------
    case 'bulk_delete':
        require_expense_perm('delete');

        $ids = $request['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            expenses_json(false, 'No expense IDs provided for deletion.', [], 400);
        }

        $cleanIds = array_filter(array_map('intval', $ids), fn($v) => $v > 0);
        if (empty($cleanIds)) {
            expenses_json(false, 'No valid expense IDs provided.', [], 400);
        }

        // Fetch verified IDs strictly belonging to this tenant
        $inClause = implode(',', array_fill(0, count($cleanIds), '?'));
        $fetchParams = array_merge([$organizationId], $cleanIds);
        $fetchStmt = $pdo->prepare("SELECT id, reference_number, receipt_path FROM expenses WHERE organization_id = ? AND id IN ({$inClause})");
        $fetchStmt->execute($fetchParams);
        $expensesToDelete = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($expensesToDelete)) {
            expenses_json(false, 'No matching expenses found for this organization.', [], 404);
        }

        $deletedCount = 0;
        foreach ($expensesToDelete as $exp) {
            $expId = (int)$exp['id'];

            if (!empty($exp['receipt_path'])) {
                $fullPath = dirname(__DIR__, 2) . '/' . $exp['receipt_path'];
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'expenses' AND related_entity_id = ?")->execute([$organizationId, $expId]);
            $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'expenses' AND related_id = ?")->execute([$organizationId, $expId]);
            $pdo->prepare("DELETE FROM tasks WHERE organization_id = ? AND related_type = 'expenses' AND related_id = ?")->execute([$organizationId, $expId]);
            $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = ? AND related_type = 'expenses' AND related_id = ?")->execute([$organizationId, $expId]);

            $pdo->prepare("DELETE FROM expenses WHERE id = ? AND organization_id = ?")->execute([$expId, $organizationId]);
            $deletedCount++;
        }

        expenses_json(true, "Successfully deleted {$deletedCount} expense records.", ['deleted_count' => $deletedCount]);
        break;

    // -------------------------------------------------------------------------
    // 9. LINK INVOICE / UPDATE INVOICE STATUS
    // -------------------------------------------------------------------------
    case 'link_invoice':
        require_expense_perm('edit');

        $id = (int)($request['id'] ?? 0);
        $status = trim($request['invoice_status'] ?? 'Invoiced');
        $invoiceRef = trim($request['invoice_reference'] ?? $request['invoiceId'] ?? '');

        if ($id <= 0) {
            expenses_json(false, 'Invalid expense ID.', [], 400);
        }

        // Verify expense belongs to tenant
        $chk = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$id, $organizationId]);
        $exp = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$exp) {
            expenses_json(false, 'Expense not found or access denied.', [], 404);
        }

        // Critical rule: non-billable cannot be invoiced
        if ($exp['billing_type'] !== 'Billable') {
            expenses_json(false, 'Non-Billable expenses cannot be linked to an invoice.', [], 422);
        }

        if ($status === 'Invoiced') {
            if (empty($invoiceRef)) {
                $invoiceRef = 'INV-' . date('Y') . '-' . substr((string)time(), -4);
            }
            $invoicedAt = date('Y-m-d H:i:s');
            $invoicedBy = $currentUserId;
        } else {
            $status = 'Not Invoiced';
            $invoiceRef = null;
            $invoicedAt = null;
            $invoicedBy = null;
        }

        $upStmt = $pdo->prepare("
            UPDATE expenses 
            SET invoice_status = ?, invoice_reference = ?, invoiced_at = ?, invoiced_by = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ? AND organization_id = ?
        ");
        $upStmt->execute([$status, $invoiceRef, $invoicedAt, $invoicedBy, $currentUserId, $id, $organizationId]);

        log_expense_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'invoice_status_changed',
            "Invoice status changed to {$status}",
            $status === 'Invoiced' ? "Linked to client invoice #{$invoiceRef}." : "Marked as Not Invoiced.",
            $id
        );

        expenses_json(true, "Expense marked as {$status}.", [
            'id'             => $id,
            'invoice_status' => $status,
            'invoice_ref'    => $invoiceRef
        ]);
        break;

    // -------------------------------------------------------------------------
    // 10. UPDATE REIMBURSEMENT STATUS
    // -------------------------------------------------------------------------
    case 'update_reimbursement':
        require_expense_perm('edit');

        $id = (int)($request['id'] ?? 0);
        $status = trim($request['reimbursement_status'] ?? '');

        if ($id <= 0 || !in_array($status, ['N/A', 'Pending', 'Reimbursed'], true)) {
            expenses_json(false, 'Invalid expense ID or reimbursement status.', [], 400);
        }

        $chk = $pdo->prepare("SELECT id, reference_number FROM expenses WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$id, $organizationId]);
        $exp = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$exp) {
            expenses_json(false, 'Expense not found or access denied.', [], 404);
        }

        $reimbAt = ($status === 'Reimbursed') ? date('Y-m-d H:i:s') : null;

        $upStmt = $pdo->prepare("
            UPDATE expenses 
            SET reimbursement_status = ?, reimbursed_at = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ? AND organization_id = ?
        ");
        $upStmt->execute([$status, $reimbAt, $currentUserId, $id, $organizationId]);

        log_expense_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'reimbursement_changed',
            "Reimbursement status: {$status}",
            "Expense reimbursement updated to {$status}.",
            $id
        );

        expenses_json(true, "Reimbursement status updated to {$status}.", [
            'id'                   => $id,
            'reimbursement_status' => $status
        ]);
        break;

    // -------------------------------------------------------------------------
    // 11. ADD NOTE / COMMENT (team_notes)
    // -------------------------------------------------------------------------
    case 'add_note':
        require_expense_perm('edit');

        $id = (int)($request['id'] ?? 0);
        $content = trim($request['content'] ?? $request['note'] ?? '');

        if ($id <= 0 || $content === '') {
            expenses_json(false, 'Expense ID and note content are required.', [], 422);
        }

        $chk = $pdo->prepare("SELECT id, reference_number, title FROM expenses WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$id, $organizationId]);
        $exp = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$exp) {
            expenses_json(false, 'Expense not found or access denied.', [], 404);
        }

        $insNote = $pdo->prepare("
            INSERT INTO team_notes (organization_id, created_by, title, content, related_type, related_id, is_pinned, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'expenses', ?, 0, NOW(), NOW())
        ");
        $insNote->execute([$organizationId, $currentUserId, 'Expense Note', $content, $id]);
        $noteId = (int)$pdo->lastInsertId();

        // Also log activity in team_activities
        log_expense_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'note_added',
            'Added note',
            $content,
            $id
        );

        expenses_json(true, 'Note saved successfully.', [
            'note_id'    => $noteId,
            'expense_id' => $id,
            'date'       => date('M d, Y • h:i A')
        ]);
        break;

    // -------------------------------------------------------------------------
    // 12. DOWNLOAD RECEIPT (Secure file stream)
    // -------------------------------------------------------------------------
    case 'download_receipt':
        require_expense_perm('view');

        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            expenses_json(false, 'Invalid expense ID.', [], 400);
        }

        $stmt = $pdo->prepare("
            SELECT receipt_filename, receipt_path, receipt_mime, receipt_size 
            FROM expenses 
            WHERE id = ? AND organization_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$id, $organizationId]);
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exp || empty($exp['receipt_path'])) {
            expenses_json(false, 'No receipt file attached to this expense.', [], 404);
        }

        $fullPath = dirname(__DIR__, 2) . '/' . $exp['receipt_path'];
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            expenses_json(false, 'Receipt file could not be found on server.', [], 404);
        }

        $mime = $exp['receipt_mime'] ?: 'application/octet-stream';
        $filename = $exp['receipt_filename'] ?: basename($fullPath);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: private, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;

    // -------------------------------------------------------------------------
    // 13. EXPORT CSV
    // -------------------------------------------------------------------------
    case 'export_csv':
        require_expense_perm('export');

        $stmt = $pdo->prepare("
            SELECT 
                e.reference_number,
                e.title,
                e.category,
                e.amount,
                e.currency,
                e.tax_amount,
                e.expense_date,
                COALESCE(c.name, 'Internal') AS company_name,
                COALESCE(p.name, 'General') AS project_name,
                e.merchant,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
                e.billing_type,
                e.invoice_status,
                e.invoice_reference,
                e.reimbursement_status,
                e.payment_method,
                e.description
            FROM expenses e
            LEFT JOIN companies c ON c.id = e.company_id AND c.organization_id = e.organization_id
            LEFT JOIN projects p ON p.id = e.project_id AND p.organization_id = e.organization_id
            LEFT JOIN users u ON u.id = e.owner_id AND u.organization_id = e.organization_id
            WHERE e.organization_id = ?
            ORDER BY e.expense_date DESC, e.id DESC
        ");
        $stmt->execute([$organizationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="NexFlow_expenses_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Reference #', 'Expense Name', 'Category', 'Amount', 'Currency', 'Tax Amount',
            'Date', 'Company / Client', 'Project', 'Merchant', 'Owner',
            'Billing Type', 'Invoice Status', 'Invoice Reference', 'Reimbursement Status',
            'Payment Method', 'Description'
        ]);

        foreach ($rows as $r) {
            fputcsv($output, [
                $r['reference_number'],
                $r['title'],
                $r['category'],
                $r['amount'],
                $r['currency'],
                $r['tax_amount'],
                $r['expense_date'],
                $r['company_name'],
                $r['project_name'],
                $r['merchant'],
                trim($r['owner_name']),
                $r['billing_type'],
                $r['invoice_status'],
                $r['invoice_reference'],
                $r['reimbursement_status'],
                $r['payment_method'],
                $r['description']
            ]);
        }
        fclose($output);
        exit;

    // -------------------------------------------------------------------------
    // 14. IMPORT CSV
    // -------------------------------------------------------------------------
    case 'import_csv':
        require_expense_perm('create');

        if (empty($_FILES['file']) && empty($_FILES['csv_file'])) {
            expenses_json(false, 'No CSV file uploaded.', [], 400);
        }

        $file = $_FILES['file'] ?? $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            expenses_json(false, 'File upload error code: ' . $file['error'], [], 400);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            expenses_json(false, 'Could not open CSV file.', [], 500);
        }

        // Read Header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            expenses_json(false, 'Empty CSV file.', [], 422);
        }

        // Normalize header
        $cleanHeader = array_map(function($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$h)));
        }, $header);

        // Required columns check (fuzzy match)
        $titleIdx = array_search('title', $cleanHeader, true);
        if ($titleIdx === false) $titleIdx = array_search('expensename', $cleanHeader, true);
        if ($titleIdx === false) $titleIdx = array_search('name', $cleanHeader, true);

        $amountIdx = array_search('amount', $cleanHeader, true);
        $dateIdx = array_search('date', $cleanHeader, true);
        if ($dateIdx === false) $dateIdx = array_search('expensedate', $cleanHeader, true);

        $categoryIdx = array_search('category', $cleanHeader, true);
        $merchantIdx = array_search('merchant', $cleanHeader, true);
        $companyIdx = array_search('company', $cleanHeader, true);
        $projectIdx = array_search('project', $cleanHeader, true);
        $ownerIdx = array_search('owner', $cleanHeader, true);
        $billingIdx = array_search('billingtype', $cleanHeader, true);

        if ($titleIdx === false || $amountIdx === false) {
            fclose($handle);
            expenses_json(false, 'CSV must contain at least "Title" (or "Expense Name") and "Amount" columns.', [], 422);
        }

        $orgCurrency = get_org_currency($pdo, $organizationId);

        // Preload companies and users in this tenant for fast lookup
        $compStmt = $pdo->prepare("SELECT id, LOWER(name) AS lname FROM companies WHERE organization_id = ?");
        $compStmt->execute([$organizationId]);
        $companiesMap = [];
        foreach ($compStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $companiesMap[$c['lname']] = (int)$c['id'];
        }

        $userStmt = $pdo->prepare("SELECT id, LOWER(CONCAT(first_name, ' ', last_name)) AS fullname, LOWER(email) AS lemail FROM users WHERE organization_id = ?");
        $userStmt->execute([$organizationId]);
        $usersMap = [];
        foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $usersMap[$u['fullname']] = (int)$u['id'];
            $usersMap[$u['lemail']] = (int)$u['id'];
        }

        $importedCount = 0;
        $pdo->beginTransaction();

        try {
            $insStmt = $pdo->prepare("
                INSERT INTO expenses (
                    organization_id, reference_number, title, expense_date, merchant, category,
                    amount, currency, tax_amount, billing_type, invoice_status, invoice_reference,
                    reimbursement_status, company_id, project_id, payment_method, description,
                    owner_id, created_by, updated_by, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, 0.00, ?, 'Not Invoiced', NULL,
                    'N/A', ?, NULL, 'Corporate Card', 'Imported via CSV file upload.',
                    ?, ?, ?, NOW(), NOW()
                )
            ");

            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || count($row) < 2) continue;

                $rowTitle = trim($row[$titleIdx] ?? '');
                $rowAmount = (float)str_replace(['$', ','], '', trim($row[$amountIdx] ?? '0'));
                if ($rowTitle === '' || $rowAmount <= 0) continue;

                $rowDate = ($dateIdx !== false && !empty($row[$dateIdx])) ? date('Y-m-d', strtotime(trim($row[$dateIdx]))) : date('Y-m-d');
                $rowCategory = ($categoryIdx !== false && !empty($row[$categoryIdx])) ? trim($row[$categoryIdx]) : 'Other';
                $rowMerchant = ($merchantIdx !== false && !empty($row[$merchantIdx])) ? trim($row[$merchantIdx]) : 'Vendor';
                
                $rowBilling = ($billingIdx !== false && strtolower(trim($row[$billingIdx])) === 'non-billable') ? 'Non-Billable' : 'Billable';

                // Company lookup
                $rowCompanyId = null;
                if ($companyIdx !== false && !empty($row[$companyIdx])) {
                    $cKey = strtolower(trim($row[$companyIdx]));
                    if (isset($companiesMap[$cKey])) {
                        $rowCompanyId = $companiesMap[$cKey];
                    }
                }

                // Owner lookup
                $rowOwnerId = $currentUserId;
                if ($ownerIdx !== false && !empty($row[$ownerIdx])) {
                    $oKey = strtolower(trim($row[$ownerIdx]));
                    if (isset($usersMap[$oKey])) {
                        $rowOwnerId = $usersMap[$oKey];
                    }
                }

                $refNo = generate_expense_reference($pdo, $organizationId);

                $insStmt->execute([
                    $organizationId,
                    $refNo,
                    $rowTitle,
                    $rowDate,
                    $rowMerchant,
                    $rowCategory,
                    $rowAmount,
                    $orgCurrency,
                    $rowBilling,
                    $rowCompanyId,
                    $rowOwnerId,
                    $currentUserId,
                    $currentUserId
                ]);

                $importedCount++;
            }

            fclose($handle);
            $pdo->commit();

            expenses_json(true, "Successfully imported {$importedCount} expense records.", [
                'imported_count' => $importedCount
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fclose($handle);
            expenses_json(false, 'Import failed: ' . $e->getMessage(), [], 500);
        }
        break;

    default:
        expenses_json(false, "Unknown action: {$action}", [], 400);
        break;
}
