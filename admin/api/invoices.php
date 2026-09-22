<?php
/**
 * NexFlow CRM — Invoices Management API Controller
 * 
 * Handles multi-tenant Invoice CRUD, live SQL KPI aggregations, itemized deliverables,
 * payment recording, server-side financial calculations, polymorphic activity logging,
 * and tenant-scoped CSV export.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// JSON Response Helper
function invoices_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
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
    invoices_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    invoices_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Helper
function require_invoice_perm(string $action): void
{
    if (!hasPermission('invoices', $action)) {
        invoices_json(false, "Forbidden: You do not have permission to {$action} invoices.", [], 403);
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

// Helper: Generate sequential invoice number per organization
function generate_invoice_number(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare("
        SELECT invoice_number 
        FROM invoices 
        WHERE organization_id = ? 
        ORDER BY id DESC 
        LIMIT 100
    ");
    $stmt->execute([$orgId]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 0;
    foreach ($existing as $num) {
        if (preg_match('/INV[-_]?0*(\d+)/i', $num, $m)) {
            $val = (int)$m[1];
            if ($val > $maxNum) $maxNum = $val;
        }
    }
    return sprintf("INV-%03d", $maxNum + 1);
}

// Helper: Generate sequential payment number per organization
function generate_payment_number(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare("
        SELECT payment_number 
        FROM invoice_payments 
        WHERE organization_id = ? 
        ORDER BY id DESC 
        LIMIT 100
    ");
    $stmt->execute([$orgId]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 100;
    foreach ($existing as $num) {
        if (preg_match('/PAY[-_]?0*(\d+)/i', $num, $m)) {
            $val = (int)$m[1];
            if ($val > $maxNum) $maxNum = $val;
        }
    }
    return sprintf("PAY-%03d", $maxNum + 1);
}

// Helper: Log polymorphic activity in team_activities
function log_invoice_activity(PDO $pdo, int $orgId, ?int $userId, string $type, string $title, ?string $desc, int $invoiceId): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities 
            (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'invoices', ?, ?, NOW())
        ");
        $stmt->execute([
            $orgId,
            $userId,
            $type,
            $title,
            $desc,
            $invoiceId,
            $userId
        ]);
    } catch (Throwable $e) {
        // Suppress activity logging failure to not break primary transaction
    }
}

// Helper: Format initials and color for owners
function get_avatar_meta(?string $firstName, ?string $lastName, ?int $userId): array
{
    $colors = ['#2563EB', '#7C3AED', '#059669', '#C2410C', '#4F46E5', '#0891B2', '#D97706'];
    $fn = trim((string)$firstName);
    $ln = trim((string)$lastName);
    $initials = '';
    if (!empty($fn)) $initials .= mb_strtoupper(mb_substr($fn, 0, 1));
    if (!empty($ln)) $initials .= mb_strtoupper(mb_substr($ln, 0, 1));
    if (empty($initials)) $initials = 'US';

    $idx = ($userId ?? 1) % count($colors);
    return [
        'initials' => $initials,
        'color'    => $colors[$idx]
    ];
}

// Request Method & Action Routing
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if (empty($action)) {
    // Also parse JSON body if applicable
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json) && !empty($json['action'])) {
            $action = trim($json['action']);
        }
    }
}

// =========================================================================
// ACTION: reference_options
// =========================================================================
if ($action === 'reference_options') {
    require_invoice_perm('view');

    $stmtComp = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? ORDER BY name ASC");
    $stmtComp->execute([$organizationId]);
    $companies = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

    $stmtCont = $pdo->prepare("SELECT id, company_name, first_name, last_name, email, phone FROM contacts WHERE organization_id = ? ORDER BY first_name ASC, last_name ASC");
    $stmtCont->execute([$organizationId]);
    $contacts = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    $stmtProj = $pdo->prepare("SELECT id, project_code, name, client_name FROM projects WHERE organization_id = ? ORDER BY name ASC");
    $stmtProj->execute([$organizationId]);
    $projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

    $stmtDeals = $pdo->prepare("SELECT id, name AS title, company AS company_name, value FROM deals WHERE organization_id = ? ORDER BY name ASC");
    $stmtDeals->execute([$organizationId]);
    $deals = $stmtDeals->fetchAll(PDO::FETCH_ASSOC);

    $stmtUsers = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY first_name ASC");
    $stmtUsers->execute([$organizationId]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $orgCurrency = get_org_currency($pdo, $organizationId);
    $nextInvNum  = generate_invoice_number($pdo, $organizationId);

    invoices_json(true, 'Reference options retrieved.', [
        'companies'           => $companies,
        'contacts'            => $contacts,
        'projects'            => $projects,
        'deals'               => $deals,
        'users'               => $users,
        'currency'            => $orgCurrency,
        'next_invoice_number' => $nextInvNum,
    ]);
}

// =========================================================================
// ACTION: summary
// =========================================================================
if ($action === 'summary') {
    require_invoice_perm('view');

    $orgCurrency = get_org_currency($pdo, $organizationId);

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS count_total,
            COALESCE(SUM(total), 0) AS total_invoiced,
            COALESCE(SUM(amount_paid), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN status NOT IN ('Draft', 'Cancelled') THEN balance_due ELSE 0 END), 0) AS total_outstanding,
            COALESCE(SUM(CASE WHEN (status = 'Overdue' OR (due_date < CURDATE() AND balance_due > 0)) AND status NOT IN ('Draft', 'Cancelled') THEN balance_due ELSE 0 END), 0) AS total_overdue,
            COUNT(CASE WHEN status IN ('Draft', 'Pending') THEN 1 END) AS count_draft_pending,
            COUNT(CASE WHEN status = 'Paid' THEN 1 END) AS count_paid,
            COUNT(CASE WHEN balance_due > 0 AND status NOT IN ('Draft', 'Cancelled') THEN 1 END) AS count_outstanding,
            COUNT(CASE WHEN (status = 'Overdue' OR (due_date < CURDATE() AND balance_due > 0)) AND status NOT IN ('Draft', 'Cancelled') THEN 1 END) AS count_overdue
        FROM invoices
        WHERE organization_id = ?
    ");
    $stmt->execute([$organizationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Status counts map
    $statusMap = [
        'All'            => (int)($row['count_total'] ?? 0),
        'Draft'          => 0,
        'Sent'           => 0,
        'Viewed'         => 0,
        'Pending'        => 0,
        'Partially Paid' => 0,
        'Paid'           => (int)($row['count_paid'] ?? 0),
        'Overdue'        => (int)($row['count_overdue'] ?? 0),
        'Cancelled'      => 0
    ];

    $stmtStatus = $pdo->prepare("
        SELECT status, COUNT(*) as cnt 
        FROM invoices 
        WHERE organization_id = ? 
        GROUP BY status
    ");
    $stmtStatus->execute([$organizationId]);
    while ($r = $stmtStatus->fetch(PDO::FETCH_ASSOC)) {
        if (isset($statusMap[$r['status']])) {
            $statusMap[$r['status']] = (int)$r['cnt'];
        }
    }

    invoices_json(true, 'Invoice summary calculated.', [
        'total_invoiced'      => (float)($row['total_invoiced'] ?? 0),
        'total_paid'          => (float)($row['total_paid'] ?? 0),
        'total_outstanding'   => (float)($row['total_outstanding'] ?? 0),
        'total_overdue'       => (float)($row['total_overdue'] ?? 0),
        'count_draft_pending' => (int)($row['count_draft_pending'] ?? 0),
        'count_total'         => (int)($row['count_total'] ?? 0),
        'count_paid'          => (int)($row['count_paid'] ?? 0),
        'count_outstanding'   => (int)($row['count_outstanding'] ?? 0),
        'count_overdue'       => (int)($row['count_overdue'] ?? 0),
        'status_counts'       => $statusMap,
        'currency'            => $orgCurrency,
    ]);
}

// =========================================================================
// ACTION: list
// =========================================================================
if ($action === 'list') {
    require_invoice_perm('view');

    $page       = max(1, (int)($_GET['page'] ?? 1));
    $perPage    = max(1, min(100, (int)($_GET['per_page'] ?? 8)));
    $search     = trim((string)($_GET['search'] ?? ''));
    $status     = trim((string)($_GET['status'] ?? 'All'));
    $customer   = trim((string)($_GET['customer'] ?? 'All'));
    $owner      = trim((string)($_GET['owner'] ?? 'All'));
    $sort       = trim((string)($_GET['sort'] ?? 'newest'));
    $savedView  = trim((string)($_GET['saved_view'] ?? 'all'));

    $where   = ["i.organization_id = :org_id"];
    $params  = [':org_id' => $organizationId];

    // Status filter
    if (!empty($status) && $status !== 'All') {
        if ($status === 'Overdue') {
            $where[] = "(i.status = 'Overdue' OR (i.due_date < CURDATE() AND i.balance_due > 0 AND i.status NOT IN ('Draft', 'Cancelled')))";
        } else {
            $where[] = "i.status = :status";
            $params[':status'] = $status;
        }
    }

    // Saved views filter
    if ($savedView === 'my-invoices') {
        $where[] = "i.assigned_to = :my_user_id";
        $params[':my_user_id'] = $currentUserId;
    } elseif ($savedView === 'due-soon') {
        $where[] = "(i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND i.balance_due > 0 AND i.status NOT IN ('Draft', 'Cancelled'))";
    } elseif ($savedView === 'overdue') {
        $where[] = "(i.status = 'Overdue' OR (i.due_date < CURDATE() AND i.balance_due > 0 AND i.status NOT IN ('Draft', 'Cancelled')))";
    }

    // Customer filter
    if (!empty($customer) && $customer !== 'All') {
        if (is_numeric($customer)) {
            $where[] = "i.company_id = :cust_id";
            $params[':cust_id'] = (int)$customer;
        } else {
            $where[] = "c.name = :cust_name";
            $params[':cust_name'] = $customer;
        }
    }

    // Owner filter
    if (!empty($owner) && $owner !== 'All') {
        if (is_numeric($owner)) {
            $where[] = "i.assigned_to = :owner_id";
            $params[':owner_id'] = (int)$owner;
        } else {
            $where[] = "CONCAT(u.first_name, ' ', u.last_name) = :owner_name";
            $params[':owner_name'] = $owner;
        }
    }

    // Search query
    if (!empty($search)) {
        $where[] = "(
            i.invoice_number LIKE :s1
            OR i.title LIKE :s2
            OR c.name LIKE :s3
            OR CONCAT(ct.first_name, ' ', ct.last_name) LIKE :s4
            OR p.name LIKE :s5
            OR d.name LIKE :s6
        )";
        $sTerm = "%{$search}%";
        $params[':s1'] = $sTerm;
        $params[':s2'] = $sTerm;
        $params[':s3'] = $sTerm;
        $params[':s4'] = $sTerm;
        $params[':s5'] = $sTerm;
        $params[':s6'] = $sTerm;
    }

    $whereClause = implode(' AND ', $where);

    // Sorting
    $orderClause = "i.issue_date DESC, i.id DESC";
    if ($sort === 'oldest') {
        $orderClause = "i.issue_date ASC, i.id ASC";
    } elseif ($sort === 'amount-desc') {
        $orderClause = "i.total DESC, i.id DESC";
    } elseif ($sort === 'amount-asc') {
        $orderClause = "i.total ASC, i.id ASC";
    }

    // Total items count query
    $countSql = "
        SELECT COUNT(*) 
        FROM invoices i
        LEFT JOIN companies c ON c.id = i.company_id
        LEFT JOIN contacts ct ON ct.id = i.contact_id
        LEFT JOIN projects p ON p.id = i.project_id
        LEFT JOIN deals d ON d.id = i.deal_id
        LEFT JOIN users u ON u.id = i.assigned_to
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
            i.*,
            c.name AS company_name,
            CONCAT(COALESCE(ct.first_name, ''), ' ', COALESCE(ct.last_name, '')) AS contact_name,
            ct.email AS contact_email,
            p.name AS project_name,
            d.name AS deal_title,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
            u.first_name AS owner_first_name,
            u.last_name AS owner_last_name
        FROM invoices i
        LEFT JOIN companies c ON c.id = i.company_id
        LEFT JOIN contacts ct ON ct.id = i.contact_id
        LEFT JOIN projects p ON p.id = i.project_id
        LEFT JOIN deals d ON d.id = i.deal_id
        LEFT JOIN users u ON u.id = i.assigned_to
        WHERE {$whereClause}
        ORDER BY {$orderClause}
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $avatar = get_avatar_meta($row['owner_first_name'], $row['owner_last_name'], $row['assigned_to']);
        $ownerName = trim((string)$row['owner_name']);
        if (empty($ownerName)) $ownerName = 'Unassigned';

        // Check if overdue dynamically
        $effStatus = $row['status'];
        if ($row['balance_due'] > 0 && strtotime($row['due_date']) < strtotime(date('Y-m-d')) && !in_array($effStatus, ['Draft', 'Cancelled', 'Paid'])) {
            $effStatus = 'Overdue';
        }

        $items[] = [
            'id'                => (int)$row['id'],
            'number'            => $row['invoice_number'],
            'title'             => $row['title'] ?: "Invoice {$row['invoice_number']}",
            'customer'          => $row['company_name'] ?: 'Unknown Client',
            'company_id'        => $row['company_id'] ? (int)$row['company_id'] : null,
            'contact'           => trim((string)$row['contact_name']) ?: null,
            'contact_email'     => $row['contact_email'] ?: null,
            'deal'              => $row['deal_title'] ?: ($row['project_name'] ?: null),
            'project'           => $row['project_name'] ?: null,
            'contract'          => $row['contract_reference'] ?: null,
            'issueDate'         => $row['issue_date'],
            'dueDate'           => $row['due_date'],
            'paymentTerms'      => $row['payment_terms'],
            'currency'          => $row['currency'],
            'status'            => $effStatus,
            'owner'             => $ownerName,
            'ownerInitials'     => $avatar['initials'],
            'ownerColor'        => $avatar['color'],
            'subtotal'          => (float)$row['subtotal'],
            'discount'          => (float)$row['discount'],
            'tax'               => (float)$row['tax'],
            'total'             => (float)$row['total'],
            'paid'              => (float)$row['amount_paid'],
            'amountPaid'        => (float)$row['amount_paid'],
            'balance'           => (float)$row['balance_due'],
            'balanceDue'        => (float)$row['balance_due'],
            'customer_notes'    => $row['customer_notes'],
            'internal_notes'    => $row['internal_notes'],
        ];
    }

    invoices_json(true, 'Invoices retrieved.', [
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
// ACTION: get
// =========================================================================
if ($action === 'get') {
    require_invoice_perm('view');

    $invId = trim((string)($_GET['id'] ?? ''));
    if (empty($invId)) {
        invoices_json(false, 'Missing invoice ID.', [], 400);
    }

    // Look up by integer ID or invoice_number
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            c.name AS company_name,
            CONCAT(COALESCE(ct.first_name, ''), ' ', COALESCE(ct.last_name, '')) AS contact_name,
            ct.email AS contact_email,
            ct.phone AS contact_phone,
            p.name AS project_name,
            d.name AS deal_title,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
            u.first_name AS owner_first_name,
            u.last_name AS owner_last_name
        FROM invoices i
        LEFT JOIN companies c ON c.id = i.company_id
        LEFT JOIN contacts ct ON ct.id = i.contact_id
        LEFT JOIN projects p ON p.id = i.project_id
        LEFT JOIN deals d ON d.id = i.deal_id
        LEFT JOIN users u ON u.id = i.assigned_to
        WHERE i.organization_id = :org_id AND (i.id = :id OR i.invoice_number = :num)
        LIMIT 1
    ");
    $stmt->execute([
        ':org_id' => $organizationId,
        ':id'     => is_numeric($invId) ? (int)$invId : 0,
        ':num'    => $invId
    ]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        invoices_json(false, 'Invoice not found.', [], 404);
    }

    $actualId = (int)$inv['id'];

    // Fetch line items
    $stmtItems = $pdo->prepare("
        SELECT id, item_title, item_description, quantity, unit_price, amount, sort_order
        FROM invoice_items
        WHERE organization_id = ? AND invoice_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtItems->execute([$organizationId, $actualId]);
    $itemsRaw = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($itemsRaw as $it) {
        $items[] = [
            'id'     => (int)$it['id'],
            'title'  => $it['item_title'],
            'name'   => $it['item_title'],
            'desc'   => $it['item_description'] ?: '',
            'qty'    => (float)$it['quantity'],
            'price'  => (float)$it['unit_price'],
            'rate'   => (float)$it['unit_price'],
            'total'  => (float)$it['amount'],
            'amount' => (float)$it['amount'],
        ];
    }

    // Fetch payment transactions
    $stmtPay = $pdo->prepare("
        SELECT id, payment_number, amount, payment_date, payment_method, transaction_reference, notes, created_at
        FROM invoice_payments
        WHERE organization_id = ? AND invoice_id = ?
        ORDER BY payment_date DESC, id DESC
    ");
    $stmtPay->execute([$organizationId, $actualId]);
    $paysRaw = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

    $payments = [];
    foreach ($paysRaw as $py) {
        $transRef = ($py['transaction_reference'] !== null && trim((string)$py['transaction_reference']) !== '') ? trim((string)$py['transaction_reference']) : null;
        $payments[] = [
            'id'                    => (int)$py['id'],
            'number'                => $py['payment_number'],
            'payment_number'        => $py['payment_number'],
            'date'                  => $py['payment_date'],
            'payment_date'          => $py['payment_date'],
            'amount'                => (float)$py['amount'],
            'method'                => $py['payment_method'],
            'payment_method'        => $py['payment_method'],
            'transaction_reference' => $transRef,
            'ref'                   => $transRef,
            'reference'             => $transRef,
            'notes'                 => $py['notes'] ?: '',
        ];
    }

    // Fetch timeline activities from team_activities
    $stmtAct = $pdo->prepare("
        SELECT title, description, created_at
        FROM team_activities
        WHERE organization_id = ? AND related_entity = 'invoices' AND related_entity_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmtAct->execute([$organizationId, $actualId]);
    $timelineRaw = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    $timeline = [];
    foreach ($timelineRaw as $act) {
        $timeline[] = [
            'date' => date('Y-m-d', strtotime($act['created_at'])),
            'text' => $act['title'] . ($act['description'] ? " — {$act['description']}" : '')
        ];
    }
    if (empty($timeline)) {
        $timeline[] = [
            'date' => date('Y-m-d', strtotime($inv['created_at'])),
            'text' => "Invoice created"
        ];
    }

    $avatar = get_avatar_meta($inv['owner_first_name'], $inv['owner_last_name'], $inv['assigned_to']);
    $ownerName = trim((string)$inv['owner_name']);
    if (empty($ownerName)) $ownerName = 'Unassigned';

    $effStatus = $inv['status'];
    if ($inv['balance_due'] > 0 && strtotime($inv['due_date']) < strtotime(date('Y-m-d')) && !in_array($effStatus, ['Draft', 'Cancelled', 'Paid'])) {
        $effStatus = 'Overdue';
    }

    $taxable = max(0.0, (float)$inv['subtotal'] - (float)$inv['discount']);
    $taxRate = $taxable > 0 ? round(((float)$inv['tax'] / $taxable) * 100, 2) : 0.0;

    $response = [
        'id'             => (int)$inv['id'],
        'number'         => $inv['invoice_number'],
        'title'          => $inv['title'] ?: "Invoice {$inv['invoice_number']}",
        'customer'       => $inv['company_name'] ?: 'Unknown Client',
        'billedTo'       => $inv['company_name'] ?: 'Unknown Client',
        'company_id'     => $inv['company_id'] ? (int)$inv['company_id'] : null,
        'contact'        => trim((string)$inv['contact_name']) ?: null,
        'contactName'    => trim((string)$inv['contact_name']) ?: null,
        'contactEmail'   => $inv['contact_email'] ?: null,
        'contactPhone'   => $inv['contact_phone'] ?: null,
        'project'        => $inv['project_name'] ?: null,
        'deal'           => $inv['deal_title'] ?: null,
        'contract'       => $inv['contract_reference'] ?: null,
        'issueDate'      => $inv['issue_date'],
        'invoiceDate'    => $inv['issue_date'],
        'dueDate'        => $inv['due_date'],
        'paymentTerms'   => $inv['payment_terms'],
        'currency'       => $inv['currency'],
        'status'         => $effStatus,
        'owner'          => $ownerName,
        'ownerInitials'  => $avatar['initials'],
        'ownerColor'     => $avatar['color'],
        'subtotal'       => (float)$inv['subtotal'],
        'discount'       => (float)$inv['discount'],
        'discountTotal'  => (float)$inv['discount'],
        'tax'            => (float)$inv['tax'],
        'taxTotal'       => (float)$inv['tax'],
        'tax_rate'       => $taxRate,
        'taxRate'        => $taxRate,
        'total'          => (float)$inv['total'],
        'amount'         => (float)$inv['total'],
        'paid'           => (float)$inv['amount_paid'],
        'amountPaid'     => (float)$inv['amount_paid'],
        'balance'        => (float)$inv['balance_due'],
        'balanceDue'     => (float)$inv['balance_due'],
        'customer_notes' => $inv['customer_notes'],
        'internal_notes' => $inv['internal_notes'],
        'items'          => $items,
        'payments'       => $payments,
        'timeline'       => $timeline,
    ];

    invoices_json(true, 'Invoice details retrieved.', $response);
}

// =========================================================================
// ACTION: create
// =========================================================================
if ($action === 'create' && $method === 'POST') {
    require_invoice_perm('create');

    $payload = [];
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $payload = json_decode($rawInput, true) ?: [];
    }
    if (empty($payload)) {
        $payload = $_POST;
    }

    $invoiceNumber = trim((string)($payload['invoice_number'] ?? $payload['number'] ?? ''));
    if (empty($invoiceNumber)) {
        $invoiceNumber = generate_invoice_number($pdo, $organizationId);
    }

    // Check unique invoice number within this organization
    $stmtChk = $pdo->prepare("SELECT id FROM invoices WHERE organization_id = ? AND invoice_number = ? LIMIT 1");
    $stmtChk->execute([$organizationId, $invoiceNumber]);
    if ($stmtChk->fetchColumn()) {
        invoices_json(false, "An invoice with number '{$invoiceNumber}' already exists in your organization.", [], 400);
    }

    // Customer / Company resolution
    $companyId = !empty($payload['company_id']) ? (int)$payload['company_id'] : null;
    $customerName = trim((string)($payload['customer'] ?? $payload['company_name'] ?? ''));

    if ($companyId) {
        // Validate company belongs to current organization
        $stmtC = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmtC->execute([$companyId, $organizationId]);
        $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$cRow) {
            invoices_json(false, 'The selected company does not belong to your organization.', [], 400);
        }
        $customerName = $cRow['name'];
    } elseif (!empty($customerName)) {
        // Try looking up company by name within this org
        $stmtC = $pdo->prepare("SELECT id FROM companies WHERE organization_id = ? AND name = ? LIMIT 1");
        $stmtC->execute([$organizationId, $customerName]);
        $compFound = $stmtC->fetchColumn();
        if ($compFound) {
            $companyId = (int)$compFound;
        } else {
            // Auto-create company in CRM database for this organization
            $insComp = $pdo->prepare("INSERT INTO companies (organization_id, name, relationship, is_active, created_by, created_at, updated_at) VALUES (?, ?, 'Customer', 1, ?, NOW(), NOW())");
            $insComp->execute([$organizationId, $customerName, $currentUserId]);
            $companyId = (int)$pdo->lastInsertId();
        }
    } else {
        invoices_json(false, 'Customer name / company is required.', [], 400);
    }

    // Contact resolution & validation
    $contactId = !empty($payload['contact_id']) ? (int)$payload['contact_id'] : null;
    $contactName = trim((string)($payload['contact'] ?? $payload['contact_person'] ?? ''));
    if ($contactId) {
        $stmtCt = $pdo->prepare("SELECT id, company_name FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmtCt->execute([$contactId, $organizationId]);
        $ctRow = $stmtCt->fetch(PDO::FETCH_ASSOC);
        if (!$ctRow) {
            invoices_json(false, 'The selected contact does not belong to your organization.', [], 400);
        }
    } elseif (!empty($contactName)) {
        // Try resolving contact by name under this company or org
        $stmtCt = $pdo->prepare("SELECT id FROM contacts WHERE organization_id = ? AND CONCAT(first_name, ' ', last_name) LIKE ? LIMIT 1");
        $stmtCt->execute([$organizationId, "%{$contactName}%"]);
        $fContact = $stmtCt->fetchColumn();
        if ($fContact) {
            $contactId = (int)$fContact;
        }
    }

    // Project resolution & validation
    $projectId = !empty($payload['project_id']) ? (int)$payload['project_id'] : null;
    $projectName = trim((string)($payload['project'] ?? ''));
    if ($projectId) {
        $stmtP = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmtP->execute([$projectId, $organizationId]);
        if (!$stmtP->fetchColumn()) {
            invoices_json(false, 'The selected project does not belong to your organization.', [], 400);
        }
    } elseif (!empty($projectName)) {
        $stmtP = $pdo->prepare("SELECT id FROM projects WHERE organization_id = ? AND name = ? LIMIT 1");
        $stmtP->execute([$organizationId, $projectName]);
        $pFound = $stmtP->fetchColumn();
        if ($pFound) $projectId = (int)$pFound;
    }

    // Deal resolution & validation
    $dealId = !empty($payload['deal_id']) ? (int)$payload['deal_id'] : null;
    $dealName = trim((string)($payload['deal'] ?? ''));
    if ($dealId) {
        $stmtD = $pdo->prepare("SELECT id FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmtD->execute([$dealId, $organizationId]);
        if (!$stmtD->fetchColumn()) {
            invoices_json(false, 'The selected deal does not belong to your organization.', [], 400);
        }
    } elseif (!empty($dealName)) {
        $stmtD = $pdo->prepare("SELECT id FROM deals WHERE organization_id = ? AND name = ? LIMIT 1");
        $stmtD->execute([$organizationId, $dealName]);
        $dFound = $stmtD->fetchColumn();
        if ($dFound) $dealId = (int)$dFound;
    }

    // Owner / Assigned To
    $assignedTo = !empty($payload['assigned_to']) ? (int)$payload['assigned_to'] : $currentUserId;
    if ($assignedTo) {
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmtU->execute([$assignedTo, $organizationId]);
        if (!$stmtU->fetchColumn()) {
            $assignedTo = $currentUserId;
        }
    }

    // Dates
    $issueDate = trim((string)($payload['issue_date'] ?? $payload['issueDate'] ?? date('Y-m-d')));
    $dueDate   = trim((string)($payload['due_date'] ?? $payload['dueDate'] ?? date('Y-m-d', strtotime('+30 days'))));
    if (!strtotime($issueDate) || !strtotime($dueDate)) {
        invoices_json(false, 'Invalid issue date or due date provided.', [], 400);
    }

    $terms = trim((string)($payload['payment_terms'] ?? $payload['terms'] ?? 'Net 30'));
    $allowedTerms = ['Net 30', 'Net 15', 'Due on Receipt', 'Net 60'];
    if (!in_array($terms, $allowedTerms, true)) {
        $terms = 'Net 30';
    }

    $orgCurrency = get_org_currency($pdo, $organizationId);
    $currency    = !empty($payload['currency']) ? trim($payload['currency']) : $orgCurrency;

    $contractRef = trim((string)($payload['contract_reference'] ?? $payload['contract'] ?? ''));
    $title       = trim((string)($payload['title'] ?? ($dealName ?: ($projectName ?: "Services Invoice"))));

    $custNotes   = trim((string)($payload['customer_notes'] ?? $payload['customerNote'] ?? ''));
    $intNotes    = trim((string)($payload['internal_notes'] ?? $payload['internalNote'] ?? ''));

    // Process Line Items and Calculate Totals Server-Side
    $rawItems = $payload['items'] ?? [];
    if (!is_array($rawItems) || empty($rawItems)) {
        invoices_json(false, 'Please provide at least one itemized deliverable line item.', [], 400);
    }

    $validatedItems = [];
    $subtotal = 0.0;
    foreach ($rawItems as $idx => $it) {
        $itemTitle = trim((string)($it['item_title'] ?? $it['title'] ?? $it['name'] ?? ''));
        if (empty($itemTitle)) {
            $itemTitle = "Item #" . ($idx + 1);
        }
        $itemDesc  = trim((string)($it['item_description'] ?? $it['desc'] ?? ''));
        $qty       = max(0.01, (float)($it['quantity'] ?? $it['qty'] ?? 1));
        $unitPrice = max(0.0, (float)($it['unit_price'] ?? $it['price'] ?? $it['rate'] ?? 0));
        $lineAmount = round($qty * $unitPrice, 2);

        $subtotal += $lineAmount;
        $validatedItems[] = [
            'item_title'       => $itemTitle,
            'item_description' => $itemDesc,
            'quantity'         => $qty,
            'unit_price'       => $unitPrice,
            'amount'           => $lineAmount,
            'sort_order'       => $idx
        ];
    }

    $discount      = max(0.0, (float)($payload['discount'] ?? 0));
    $taxRate       = max(0.0, (float)($payload['tax_rate'] ?? $payload['tax'] ?? 0));
    $taxableAmount = max(0.0, round($subtotal - $discount, 2));
    $taxAmount     = round($taxableAmount * ($taxRate / 100), 2);
    $grandTotal    = max(0.0, round($taxableAmount + $taxAmount, 2));
    $tax           = $taxAmount;

    $amountPaid = 0.0;
    $balanceDue = $grandTotal;

    $initialStatus = 'Pending';
    if ($balanceDue > 0 && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        $initialStatus = 'Overdue';
    }

    // Insert Invoice in DB Transaction
    try {
        $pdo->beginTransaction();

        $stmtIns = $pdo->prepare("
            INSERT INTO invoices 
            (organization_id, invoice_number, title, company_id, contact_id, project_id, deal_id, 
             contract_reference, issue_date, due_date, payment_terms, currency, subtotal, discount, 
             tax, total, amount_paid, balance_due, status, customer_notes, internal_notes, assigned_to, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmtIns->execute([
            $organizationId,
            $invoiceNumber,
            $title,
            $companyId,
            $contactId,
            $projectId,
            $dealId,
            $contractRef ?: null,
            $issueDate,
            $dueDate,
            $terms,
            $currency,
            $subtotal,
            $discount,
            $tax,
            $grandTotal,
            $amountPaid,
            $balanceDue,
            $initialStatus,
            $custNotes ?: null,
            $intNotes ?: null,
            $assignedTo,
            $currentUserId
        ]);
        $newInvoiceId = (int)$pdo->lastInsertId();

        // Insert items
        $stmtIt = $pdo->prepare("
            INSERT INTO invoice_items 
            (organization_id, invoice_id, item_title, item_description, quantity, unit_price, amount, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        foreach ($validatedItems as $vItem) {
            $stmtIt->execute([
                $organizationId,
                $newInvoiceId,
                $vItem['item_title'],
                $vItem['item_description'] ?: null,
                $vItem['quantity'],
                $vItem['unit_price'],
                $vItem['amount'],
                $vItem['sort_order']
            ]);
        }

        // Log Activity
        log_invoice_activity(
            $pdo, 
            $organizationId, 
            $currentUserId, 
            'create', 
            "Invoice {$invoiceNumber} created", 
            "Total amount {$currency} " . number_format($grandTotal, 2), 
            $newInvoiceId
        );

        $pdo->commit();

        invoices_json(true, "Invoice {$invoiceNumber} created successfully.", [
            'id'             => $newInvoiceId,
            'invoice_number' => $invoiceNumber,
            'total'          => $grandTotal,
            'balance_due'    => $balanceDue,
            'status'         => $initialStatus
        ], 201);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        invoices_json(false, 'Failed to create invoice: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: update
// =========================================================================
if ($action === 'update' && $method === 'POST') {
    require_invoice_perm('edit');

    $payload = [];
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $payload = json_decode($rawInput, true) ?: [];
    }
    if (empty($payload)) {
        $payload = $_POST;
    }

    $invId = !empty($payload['id']) ? (int)$payload['id'] : (!empty($_GET['id']) ? (int)$_GET['id'] : 0);
    if ($invId <= 0) {
        invoices_json(false, 'Missing or invalid invoice ID.', [], 400);
    }

    // Verify invoice belongs to current tenant
    $stmtCheck = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND organization_id = ? LIMIT 1");
    $stmtCheck->execute([$invId, $organizationId]);
    $currentInv = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if (!$currentInv) {
        invoices_json(false, 'Invoice not found.', [], 404);
    }

    // Extract updated fields
    $companyId   = !empty($payload['company_id']) ? (int)$payload['company_id'] : $currentInv['company_id'];
    $contactId   = isset($payload['contact_id']) ? (!empty($payload['contact_id']) ? (int)$payload['contact_id'] : null) : $currentInv['contact_id'];
    $projectId   = isset($payload['project_id']) ? (!empty($payload['project_id']) ? (int)$payload['project_id'] : null) : $currentInv['project_id'];
    $dealId      = isset($payload['deal_id']) ? (!empty($payload['deal_id']) ? (int)$payload['deal_id'] : null) : $currentInv['deal_id'];
    $assignedTo  = isset($payload['assigned_to']) ? (!empty($payload['assigned_to']) ? (int)$payload['assigned_to'] : null) : $currentInv['assigned_to'];
    $issueDate   = !empty($payload['issue_date']) ? trim($payload['issue_date']) : $currentInv['issue_date'];
    $dueDate     = !empty($payload['due_date']) ? trim($payload['due_date']) : $currentInv['due_date'];
    $terms       = !empty($payload['payment_terms']) ? trim($payload['payment_terms']) : $currentInv['payment_terms'];
    $title       = isset($payload['title']) ? trim($payload['title']) : $currentInv['title'];
    $contractRef = isset($payload['contract_reference']) ? trim($payload['contract_reference']) : $currentInv['contract_reference'];
    $custNotes   = isset($payload['customer_notes']) ? trim($payload['customer_notes']) : $currentInv['customer_notes'];
    $intNotes    = isset($payload['internal_notes']) ? trim($payload['internal_notes']) : $currentInv['internal_notes'];
    $reqStatus   = !empty($payload['status']) ? trim($payload['status']) : $currentInv['status'];

    // If items provided, recalculate
    $rawItems = $payload['items'] ?? null;
    $validatedItems = null;
    $subtotal = (float)$currentInv['subtotal'];
    $discount = isset($payload['discount']) ? max(0.0, (float)$payload['discount']) : (float)$currentInv['discount'];
    if (isset($payload['tax_rate'])) {
        $taxRate = max(0.0, (float)$payload['tax_rate']);
    } elseif (isset($payload['tax'])) {
        $taxRate = max(0.0, (float)$payload['tax']);
    } else {
        $currTaxable = max(0.0, (float)$currentInv['subtotal'] - (float)$currentInv['discount']);
        $taxRate = $currTaxable > 0 ? round(((float)$currentInv['tax'] / $currTaxable) * 100, 2) : 0.0;
    }

    if (is_array($rawItems) && !empty($rawItems)) {
        $validatedItems = [];
        $subtotal = 0.0;
        foreach ($rawItems as $idx => $it) {
            $itemTitle = trim((string)($it['item_title'] ?? $it['title'] ?? $it['name'] ?? ''));
            if (empty($itemTitle)) $itemTitle = "Item #" . ($idx + 1);
            $itemDesc  = trim((string)($it['item_description'] ?? $it['desc'] ?? ''));
            $qty       = max(0.01, (float)($it['quantity'] ?? $it['qty'] ?? 1));
            $unitPrice = max(0.0, (float)($it['unit_price'] ?? $it['price'] ?? $it['rate'] ?? 0));
            $lineAmount = round($qty * $unitPrice, 2);

            $subtotal += $lineAmount;
            $validatedItems[] = [
                'item_title'       => $itemTitle,
                'item_description' => $itemDesc,
                'quantity'         => $qty,
                'unit_price'       => $unitPrice,
                'amount'           => $lineAmount,
                'sort_order'       => $idx
            ];
        }
    }

    $taxableAmount = max(0.0, round($subtotal - $discount, 2));
    $taxAmount     = round($taxableAmount * ($taxRate / 100), 2);
    $grandTotal    = max(0.0, round($taxableAmount + $taxAmount, 2));
    $tax           = $taxAmount;

    try {
        $pdo->beginTransaction();

        // Get actual payments recorded so far
        $stmtP = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE organization_id = ? AND invoice_id = ?");
        $stmtP->execute([$organizationId, $invId]);
        $actualPaid = (float)$stmtP->fetchColumn();

        $balanceDue = max(0.0, round($grandTotal - $actualPaid, 2));

        // Recalculate status
        $newStatus = $reqStatus;
        if ($grandTotal > 0 && $balanceDue <= 0) {
            $newStatus = 'Paid';
        } elseif ($actualPaid > 0 && $balanceDue > 0) {
            $newStatus = 'Partially Paid';
        } elseif ($balanceDue > 0 && strtotime($dueDate) < strtotime(date('Y-m-d')) && !in_array($reqStatus, ['Draft', 'Cancelled'])) {
            $newStatus = 'Overdue';
        }

        $stmtUp = $pdo->prepare("
            UPDATE invoices
            SET company_id = ?, contact_id = ?, project_id = ?, deal_id = ?, assigned_to = ?,
                title = ?, contract_reference = ?, issue_date = ?, due_date = ?, payment_terms = ?,
                subtotal = ?, discount = ?, tax = ?, total = ?, amount_paid = ?, balance_due = ?,
                status = ?, customer_notes = ?, internal_notes = ?, updated_at = NOW()
            WHERE id = ? AND organization_id = ?
        ");
        $stmtUp->execute([
            $companyId,
            $contactId,
            $projectId,
            $dealId,
            $assignedTo,
            $title,
            $contractRef ?: null,
            $issueDate,
            $dueDate,
            $terms,
            $subtotal,
            $discount,
            $tax,
            $grandTotal,
            $actualPaid,
            $balanceDue,
            $newStatus,
            $custNotes ?: null,
            $intNotes ?: null,
            $invId,
            $organizationId
        ]);

        // If items were passed, replace them
        if ($validatedItems !== null) {
            $pdo->prepare("DELETE FROM invoice_items WHERE organization_id = ? AND invoice_id = ?")->execute([$organizationId, $invId]);
            $stmtIt = $pdo->prepare("
                INSERT INTO invoice_items 
                (organization_id, invoice_id, item_title, item_description, quantity, unit_price, amount, sort_order, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            foreach ($validatedItems as $vItem) {
                $stmtIt->execute([
                    $organizationId,
                    $invId,
                    $vItem['item_title'],
                    $vItem['item_description'] ?: null,
                    $vItem['quantity'],
                    $vItem['unit_price'],
                    $vItem['amount'],
                    $vItem['sort_order']
                ]);
            }
        }

        // Log Activity
        log_invoice_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'update',
            "Invoice {$currentInv['invoice_number']} updated",
            "Updated totals: Total {$grandTotal}, Balance {$balanceDue}",
            $invId
        );

        $pdo->commit();

        invoices_json(true, "Invoice {$currentInv['invoice_number']} updated successfully.", [
            'id'          => $invId,
            'total'       => $grandTotal,
            'amount_paid' => $actualPaid,
            'balance_due' => $balanceDue,
            'status'      => $newStatus
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        invoices_json(false, 'Failed to update invoice: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: delete
// =========================================================================
if ($action === 'delete' && $method === 'POST') {
    require_invoice_perm('delete');

    $payload = [];
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $payload = json_decode($rawInput, true) ?: [];
    }
    if (empty($payload)) {
        $payload = $_POST;
    }

    $invId = !empty($payload['id']) ? (int)$payload['id'] : (!empty($_GET['id']) ? (int)$_GET['id'] : 0);
    if ($invId <= 0) {
        invoices_json(false, 'Missing or invalid invoice ID.', [], 400);
    }

    // Verify invoice belongs to current tenant
    $stmtCheck = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE id = ? AND organization_id = ? LIMIT 1");
    $stmtCheck->execute([$invId, $organizationId]);
    $invRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if (!$invRow) {
        invoices_json(false, 'Invoice not found.', [], 404);
    }

    try {
        $pdo->beginTransaction();

        // Dependent invoice_items and invoice_payments are deleted automatically via CASCADE
        $stmtDel = $pdo->prepare("DELETE FROM invoices WHERE id = ? AND organization_id = ?");
        $stmtDel->execute([$invId, $organizationId]);

        // Clean up polymorphic relationships
        $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'invoices' AND related_entity_id = ?")->execute([$organizationId, $invId]);
        $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'invoices' AND related_id = ?")->execute([$organizationId, $invId]);
        $pdo->prepare("DELETE FROM tasks WHERE organization_id = ? AND related_type = 'invoices' AND related_id = ?")->execute([$organizationId, $invId]);
        $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = ? AND related_type = 'invoices' AND related_id = ?")->execute([$organizationId, $invId]);

        $pdo->commit();

        invoices_json(true, "Invoice {$invRow['invoice_number']} deleted successfully.");

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        invoices_json(false, 'Failed to delete invoice: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: record_payment
// =========================================================================
if ($action === 'record_payment' && $method === 'POST') {
    require_invoice_perm('edit');

    $payload = [];
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $payload = json_decode($rawInput, true) ?: [];
    }
    if (empty($payload)) {
        $payload = $_POST;
    }

    $invId = !empty($payload['invoice_id']) ? (int)$payload['invoice_id'] : (!empty($payload['id']) ? (int)$payload['id'] : 0);
    if ($invId <= 0) {
        invoices_json(false, 'Missing invoice ID.', [], 400);
    }

    // Verify invoice belongs to current tenant
    $stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND organization_id = ? LIMIT 1");
    $stmtInv->execute([$invId, $organizationId]);
    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
    if (!$inv) {
        invoices_json(false, 'Invoice not found.', [], 404);
    }

    $amount = round((float)($payload['amount'] ?? 0), 2);
    if ($amount <= 0) {
        invoices_json(false, 'Payment amount must be greater than zero.', [], 400);
    }

    $currentBalance = (float)$inv['balance_due'];
    if ($currentBalance <= 0) {
        invoices_json(false, 'This invoice is already fully paid.', [], 400);
    }
    if ($amount > $currentBalance) {
        invoices_json(false, "Payment amount ({$amount}) exceeds the remaining balance due ({$currentBalance}).", [], 400);
    }

    $method = trim((string)($payload['payment_method'] ?? $payload['method'] ?? 'Bank Transfer'));
    $allowedMethods = ['Bank Transfer', 'Credit Card', 'Wire Transfer', 'Cheque'];
    if (!in_array($method, $allowedMethods, true)) {
        $method = 'Bank Transfer';
    }

    $paymentDate = trim((string)($payload['payment_date'] ?? $payload['date'] ?? date('Y-m-d')));
    if (!strtotime($paymentDate)) {
        $paymentDate = date('Y-m-d');
    }

    $ref       = trim((string)($payload['transaction_reference'] ?? $payload['ref'] ?? ''));
    $refToSave = ($ref !== '') ? $ref : null;
    $notes     = trim((string)($payload['notes'] ?? ''));

    $payNum = generate_payment_number($pdo, $organizationId);

    try {
        $pdo->beginTransaction();

        // Insert payment
        $stmtPay = $pdo->prepare("
            INSERT INTO invoice_payments 
            (organization_id, invoice_id, payment_number, amount, payment_date, payment_method, transaction_reference, notes, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtPay->execute([
            $organizationId,
            $invId,
            $payNum,
            $amount,
            $paymentDate,
            $method,
            $refToSave,
            $notes ?: null,
            $currentUserId
        ]);
        $newPaymentId = (int)$pdo->lastInsertId();

        // Recalculate total amount paid & balance due
        $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE organization_id = ? AND invoice_id = ?");
        $stmtSum->execute([$organizationId, $invId]);
        $totalPaid = round((float)$stmtSum->fetchColumn(), 2);

        $newBalance = max(0.0, round((float)$inv['total'] - $totalPaid, 2));

        $newStatus = ($newBalance <= 0) ? 'Paid' : 'Partially Paid';

        $stmtUp = $pdo->prepare("
            UPDATE invoices 
            SET amount_paid = ?, balance_due = ?, status = ?, updated_at = NOW() 
            WHERE id = ? AND organization_id = ?
        ");
        $stmtUp->execute([$totalPaid, $newBalance, $newStatus, $invId, $organizationId]);

        // Log Activity
        log_invoice_activity(
            $pdo,
            $organizationId,
            $currentUserId,
            'payment',
            "Payment {$payNum} recorded for {$inv['invoice_number']}",
            "Amount: {$inv['currency']} " . number_format($amount, 2) . " via {$method}. Remaining balance: {$inv['currency']} " . number_format($newBalance, 2),
            $invId
        );

        $pdo->commit();

        invoices_json(true, "Payment of {$amount} recorded successfully for {$inv['invoice_number']}.", [
            'payment_id'            => $newPaymentId,
            'payment_number'        => $payNum,
            'transaction_reference' => $refToSave,
            'ref'                   => $refToSave,
            'amount'                => $amount,
            'amount_paid'           => $totalPaid,
            'balance_due'           => $newBalance,
            'status'                => $newStatus
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        invoices_json(false, 'Failed to record payment: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: export_csv
// =========================================================================
if ($action === 'export_csv') {
    require_invoice_perm('export');

    $search   = trim((string)($_GET['search'] ?? ''));
    $status   = trim((string)($_GET['status'] ?? 'All'));
    $customer = trim((string)($_GET['customer'] ?? 'All'));
    $owner    = trim((string)($_GET['owner'] ?? 'All'));
    $sort     = trim((string)($_GET['sort'] ?? 'newest'));

    $where   = ["i.organization_id = :org_id"];
    $params  = [':org_id' => $organizationId];

    if (!empty($status) && $status !== 'All') {
        if ($status === 'Overdue') {
            $where[] = "(i.status = 'Overdue' OR (i.due_date < CURDATE() AND i.balance_due > 0 AND i.status NOT IN ('Draft', 'Cancelled')))";
        } else {
            $where[] = "i.status = :status";
            $params[':status'] = $status;
        }
    }

    if (!empty($customer) && $customer !== 'All') {
        if (is_numeric($customer)) {
            $where[] = "i.company_id = :cust_id";
            $params[':cust_id'] = (int)$customer;
        } else {
            $where[] = "c.name = :cust_name";
            $params[':cust_name'] = $customer;
        }
    }

    if (!empty($owner) && $owner !== 'All') {
        if (is_numeric($owner)) {
            $where[] = "i.assigned_to = :owner_id";
            $params[':owner_id'] = (int)$owner;
        } else {
            $where[] = "CONCAT(u.first_name, ' ', u.last_name) = :owner_name";
            $params[':owner_name'] = $owner;
        }
    }

    if (!empty($search)) {
        $where[] = "(
            i.invoice_number LIKE :s1
            OR i.title LIKE :s2
            OR c.name LIKE :s3
            OR CONCAT(ct.first_name, ' ', ct.last_name) LIKE :s4
            OR p.name LIKE :s5
            OR d.name LIKE :s6
        )";
        $sTerm = "%{$search}%";
        $params[':s1'] = $sTerm;
        $params[':s2'] = $sTerm;
        $params[':s3'] = $sTerm;
        $params[':s4'] = $sTerm;
        $params[':s5'] = $sTerm;
        $params[':s6'] = $sTerm;
    }

    $whereClause = implode(' AND ', $where);

    $orderClause = "i.issue_date DESC, i.id DESC";
    if ($sort === 'oldest') $orderClause = "i.issue_date ASC, i.id ASC";
    elseif ($sort === 'amount-desc') $orderClause = "i.total DESC, i.id DESC";
    elseif ($sort === 'amount-asc') $orderClause = "i.total ASC, i.id ASC";

    $sql = "
        SELECT 
            i.invoice_number,
            c.name AS company_name,
            CONCAT(COALESCE(ct.first_name, ''), ' ', COALESCE(ct.last_name, '')) AS contact_name,
            COALESCE(d.name, p.name, '—') AS deal_or_project,
            i.issue_date,
            i.due_date,
            i.currency,
            i.total,
            i.amount_paid,
            i.balance_due,
            i.status,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name
        FROM invoices i
        LEFT JOIN companies c ON c.id = i.company_id
        LEFT JOIN contacts ct ON ct.id = i.contact_id
        LEFT JOIN projects p ON p.id = i.project_id
        LEFT JOIN deals d ON d.id = i.deal_id
        LEFT JOIN users u ON u.id = i.assigned_to
        WHERE {$whereClause}
        ORDER BY {$orderClause}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "invoices_export_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'Invoice Number',
        'Customer',
        'Contact Person',
        'Related Project / Deal',
        'Issue Date',
        'Due Date',
        'Currency',
        'Total',
        'Amount Paid',
        'Balance Due',
        'Status',
        'Owner'
    ]);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['invoice_number'],
            $r['company_name'] ?: 'Unknown Client',
            trim((string)$r['contact_name']) ?: '—',
            $r['deal_or_project'],
            $r['issue_date'],
            $r['due_date'],
            $r['currency'],
            number_format((float)$r['total'], 2, '.', ''),
            number_format((float)$r['amount_paid'], 2, '.', ''),
            number_format((float)$r['balance_due'], 2, '.', ''),
            $r['status'],
            trim((string)$r['owner_name']) ?: 'Unassigned'
        ]);
    }
    fclose($output);
    exit;
}

invoices_json(false, "Unknown action: {$action}", [], 400);
