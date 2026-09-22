<?php
/**
 * NexFlow CRM — Subscriptions Management API Controller
 * 
 * Handles multi-tenant Subscription CRUD, dynamic KPI metrics, financial calculations (MRR/ARR/ARPU),
 * invoice recording, polymorphic notes & activities, status management, and CSV export.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// JSON helper
function subscriptions_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
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

// 1. Session Authentication
$currentUser = nexflow_current_user();
if (!$currentUser) {
    subscriptions_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    subscriptions_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission helper
function require_subscription_perm(string $action = 'view'): void
{
    if (!hasPermission('subscriptions', $action)) {
        subscriptions_json(false, "Forbidden: You do not have permission to {$action} subscriptions.", [], 403);
    }
}

$pdo = nexflow_db();

// 3. Request parsing
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$request = array_merge($_GET, $_POST, is_array($jsonInput) ? $jsonInput : []);
$action = trim($request['action'] ?? ($requestMethod === 'POST' ? 'create' : 'list'));

switch ($action) {

    // -------------------------------------------------------------------------
    // 1. BOOTSTRAP / LIST
    // -------------------------------------------------------------------------
    case 'bootstrap':
    case 'list':
        require_subscription_perm('view');

        $search       = trim($request['search'] ?? '');
        $statusFilter = trim($request['status'] ?? 'All');
        $kpiFilter    = trim($request['kpi_filter'] ?? 'All');
        $planFilter   = trim($request['plan'] ?? 'All');
        $cycleFilter  = trim($request['billing_cycle'] ?? 'All');
        $ownerFilter  = trim($request['owner'] ?? 'All');
        $renewalFilter= trim($request['renewal'] ?? 'All');
        $sortBy       = trim($request['sort'] ?? 'name-asc');
        $page         = max(1, (int)($request['page'] ?? 1));
        $pageSize     = max(1, min(100, (int)($request['page_size'] ?? 10)));

        // Base tenant condition
        $where = ['s.organization_id = :org_id'];
        $params = [':org_id' => $organizationId];

        // Search
        if ($search !== '') {
            $where[] = '(s.name LIKE :search1 OR s.subscription_code LIKE :search2 OR s.company_name LIKE :search3 OR s.plan_tier LIKE :search4 OR s.project_name LIKE :search5)';
            $sParam = '%' . $search . '%';
            $params[':search1'] = $sParam;
            $params[':search2'] = $sParam;
            $params[':search3'] = $sParam;
            $params[':search4'] = $sParam;
            $params[':search5'] = $sParam;
        }

        // Status filter from dropdown
        if ($statusFilter !== '' && $statusFilter !== 'All') {
            $where[] = 's.status = :status_filter';
            $params[':status_filter'] = $statusFilter;
        }

        // KPI Card quick filter
        if ($kpiFilter === 'Active') {
            $where[] = "s.status = 'Active'";
        } elseif ($kpiFilter === 'Renewing Soon') {
            $where[] = "s.status = 'Active' AND s.next_billing_date IS NOT NULL AND s.next_billing_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($kpiFilter === 'Past Due') {
            $where[] = "s.status = 'Past Due'";
        } elseif ($kpiFilter === 'Trial') {
            $where[] = "s.status = 'Trial'";
        } elseif ($kpiFilter === 'Paused') {
            $where[] = "s.status = 'Paused'";
        } elseif ($kpiFilter === 'Cancelled') {
            $where[] = "s.status = 'Cancelled'";
        }

        // Plan filter
        if ($planFilter !== '' && $planFilter !== 'All') {
            $where[] = 's.plan_tier = :plan_filter';
            $params[':plan_filter'] = $planFilter;
        }

        // Billing cycle filter
        if ($cycleFilter !== '' && $cycleFilter !== 'All') {
            $where[] = 's.billing_cycle = :cycle_filter';
            $params[':cycle_filter'] = $cycleFilter;
        }

        // Owner filter
        if ($ownerFilter !== '' && $ownerFilter !== 'All') {
            if (is_numeric($ownerFilter)) {
                $where[] = 's.owner_id = :owner_filter';
                $params[':owner_filter'] = (int)$ownerFilter;
            } else {
                $where[] = 'u.name = :owner_filter_name';
                $params[':owner_filter_name'] = $ownerFilter;
            }
        }

        // Renewal type filter
        if ($renewalFilter !== '' && $renewalFilter !== 'All') {
            $where[] = 's.renewal_type = :renewal_filter';
            $params[':renewal_filter'] = $renewalFilter;
        }

        $whereSql = implode(' AND ', $where);

        // Sorting
        $orderSql = match ($sortBy) {
            'name-asc'     => 's.name ASC',
            'name-desc'    => 's.name DESC',
            'amount-desc'  => 's.amount DESC',
            'amount-asc'   => 's.amount ASC',
            'date-asc'     => 's.next_billing_date ASC',
            'date-desc'    => 's.next_billing_date DESC',
            'created-desc' => 's.id DESC',
            default        => 's.name ASC',
        };

        // Total matching count
        $countQuery = "
            SELECT COUNT(*) 
            FROM subscriptions s 
            LEFT JOIN users u ON u.id = s.owner_id AND u.organization_id = s.organization_id
            WHERE {$whereSql}
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalSubscriptions = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($totalSubscriptions / $pageSize));
        $offset = ($page - 1) * $pageSize;

        // Records query
        $dataQuery = "
            SELECT 
                s.id,
                s.organization_id,
                s.subscription_code,
                s.name,
                s.company_id,
                s.company_name,
                s.contact_id,
                s.project_id,
                s.project_name,
                s.plan_tier,
                s.billing_cycle,
                s.amount,
                s.currency,
                s.status,
                s.start_date,
                s.trial_end_date,
                s.next_billing_date,
                s.renewal_type,
                s.payment_method,
                s.billing_address,
                s.contract_scope,
                s.owner_id,
                s.is_active,
                s.created_by,
                s.created_at,
                s.updated_at,
                u.name AS owner_name,
                cnt.name AS contact_name,
                c.name AS linked_company_name,
                p.name AS linked_project_name,
                (
                    SELECT COUNT(*) 
                    FROM subscription_invoices si 
                    WHERE si.organization_id = s.organization_id AND si.subscription_id = s.id
                ) AS invoices_count
            FROM subscriptions s
            LEFT JOIN users u ON u.id = s.owner_id AND u.organization_id = s.organization_id
            LEFT JOIN contacts cnt ON cnt.id = s.contact_id AND cnt.organization_id = s.organization_id
            LEFT JOIN companies c ON c.id = s.company_id AND c.organization_id = s.organization_id
            LEFT JOIN projects p ON p.id = s.project_id AND p.organization_id = s.organization_id
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$pageSize} OFFSET {$offset}
        ";
        $dataStmt = $pdo->prepare($dataQuery);
        $dataStmt->execute($params);
        $subscriptions = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format and camelCase aliases for smooth frontend rendering
        $formattedSubscriptions = array_map(function ($sub) {
            $billingCycle = $sub['billing_cycle'];
            $amount = (float)$sub['amount'];

            // Calculate normalized MRR
            $mrr = match ($billingCycle) {
                'Quarterly' => round($amount / 3, 2),
                'Yearly'    => round($amount / 12, 2),
                default     => $amount,
            };
            $arr = round($mrr * 12, 2);

            return array_merge($sub, [
                'id'              => (int)$sub['id'],
                'subscription_id' => $sub['subscription_code'] ?: ('SUB-' . str_pad($sub['id'], 6, '0', STR_PAD_LEFT)),
                'company'         => $sub['company_name'],
                'project'         => $sub['project_name'] ?: ($sub['linked_project_name'] ?: 'Standard Account'),
                'plan'            => $sub['plan_tier'],
                'billingCycle'    => $sub['billing_cycle'],
                'amount'          => $amount,
                'startDate'       => $sub['start_date'],
                'nextBillingDate' => $sub['next_billing_date'],
                'renewalType'     => $sub['renewal_type'],
                'paymentMethod'   => $sub['payment_method'] ?: 'Not Specified',
                'owner'           => $sub['owner_name'] ?: 'Unassigned',
                'notes'           => $sub['contract_scope'] ?: '',
                'billingAddress'  => $sub['billing_address'] ?: '',
                'mrr'             => $mrr,
                'arr'             => $arr,
            ]);
        }, $subscriptions);

        // Dynamic KPI Overview Counts
        $kpiStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_count,
                COUNT(CASE WHEN status = 'Active' THEN 1 END) AS active_count,
                COUNT(CASE WHEN status = 'Active' AND next_billing_date IS NOT NULL AND next_billing_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS renewing_soon_count,
                COUNT(CASE WHEN status = 'Past Due' THEN 1 END) AS past_due_count,
                COUNT(CASE WHEN status = 'Trial' THEN 1 END) AS trial_count,
                COUNT(CASE WHEN status = 'Paused' THEN 1 END) AS paused_count,
                COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) AS cancelled_count,
                COUNT(DISTINCT CASE WHEN status = 'Active' THEN company_name END) AS active_customers_count
            FROM subscriptions
            WHERE organization_id = :org_id
        ");
        $kpiStmt->execute([':org_id' => $organizationId]);
        $kpiRaw = $kpiStmt->fetch(PDO::FETCH_ASSOC);

        // Financial Overview (Calculated dynamically)
        // Normalized MRR across all Active and Trial subscriptions
        $finStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(
                    CASE 
                        WHEN billing_cycle = 'Quarterly' THEN amount / 3
                        WHEN billing_cycle = 'Yearly' THEN amount / 12
                        ELSE amount 
                    END
                ), 0) AS total_mrr,
                COUNT(*) AS active_subscription_count
            FROM subscriptions
            WHERE organization_id = :org_id AND status IN ('Active', 'Trial')
        ");
        $finStmt->execute([':org_id' => $organizationId]);
        $finData = $finStmt->fetch(PDO::FETCH_ASSOC);

        $mrr = (float)($finData['total_mrr'] ?? 0);
        $arr = $mrr * 12;
        $activeSubCount = (int)($finData['active_subscription_count'] ?? 0);
        $arpu = $activeSubCount > 0 ? round($mrr / $activeSubCount, 2) : 0.00;

        $kpis = [
            'total'            => (int)($kpiRaw['total_count'] ?? 0),
            'active'           => (int)($kpiRaw['active_count'] ?? 0),
            'active_customers' => (int)($kpiRaw['active_customers_count'] ?? 0),
            'renewing_soon'    => (int)($kpiRaw['renewing_soon_count'] ?? 0),
            'past_due'         => (int)($kpiRaw['past_due_count'] ?? 0),
            'trial'            => (int)($kpiRaw['trial_count'] ?? 0),
            'paused'           => (int)($kpiRaw['paused_count'] ?? 0),
            'cancelled'        => (int)($kpiRaw['cancelled_count'] ?? 0),
            'mrr'              => $mrr,
            'arr'              => $arr,
            'arpu'             => $arpu,
        ];

        // Bootstrap lists for dropdowns
        $activeOwners = [];
        $activeCompanies = [];
        $activeProjects = [];

        if ($action === 'bootstrap') {
            $ownersStmt = $pdo->prepare("
                SELECT id, name, email, role, job_title 
                FROM users 
                WHERE organization_id = :org_id AND status = 'active' 
                ORDER BY name ASC
            ");
            $ownersStmt->execute([':org_id' => $organizationId]);
            $activeOwners = $ownersStmt->fetchAll(PDO::FETCH_ASSOC);

            $compsStmt = $pdo->prepare("
                SELECT id, name, company_code, domain, location 
                FROM companies 
                WHERE organization_id = :org_id AND is_active = 1 
                ORDER BY name ASC
            ");
            $compsStmt->execute([':org_id' => $organizationId]);
            $activeCompanies = $compsStmt->fetchAll(PDO::FETCH_ASSOC);

            $projsStmt = $pdo->prepare("
                SELECT id, project_code, name, client_name 
                FROM projects 
                WHERE organization_id = :org_id 
                ORDER BY name ASC
            ");
            $projsStmt->execute([':org_id' => $organizationId]);
            $activeProjects = $projsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        subscriptions_json(true, 'Subscriptions fetched successfully.', [
            'subscriptions' => $formattedSubscriptions,
            'pagination'    => [
                'total'        => $totalSubscriptions,
                'page'         => $page,
                'page_size'    => $pageSize,
                'total_pages'  => $totalPages,
                'range_start'  => $totalSubscriptions > 0 ? $offset + 1 : 0,
                'range_end'    => min($offset + $pageSize, $totalSubscriptions),
            ],
            'kpis'          => $kpis,
            'owners'        => $activeOwners,
            'companies'     => $activeCompanies,
            'projects'      => $activeProjects,
        ]);
        break;

    // -------------------------------------------------------------------------
    // 2. GET SINGLE SUBSCRIPTION
    // -------------------------------------------------------------------------
    case 'get':
        require_subscription_perm('view');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                u.name AS owner_name,
                cnt.name AS contact_name,
                c.name AS linked_company_name,
                p.name AS linked_project_name
            FROM subscriptions s
            LEFT JOIN users u ON u.id = s.owner_id AND u.organization_id = s.organization_id
            LEFT JOIN contacts cnt ON cnt.id = s.contact_id AND cnt.organization_id = s.organization_id
            LEFT JOIN companies c ON c.id = s.company_id AND c.organization_id = s.organization_id
            LEFT JOIN projects p ON p.id = s.project_id AND p.organization_id = s.organization_id
            WHERE s.id = :id AND s.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            subscriptions_json(false, 'Subscription not found or access denied.', [], 404);
        }

        $amount = (float)$sub['amount'];
        $billingCycle = $sub['billing_cycle'];
        $mrr = match ($billingCycle) {
            'Quarterly' => round($amount / 3, 2),
            'Yearly'    => round($amount / 12, 2),
            default     => $amount,
        };
        $arr = round($mrr * 12, 2);

        $formatted = array_merge($sub, [
            'id'              => (int)$sub['id'],
            'subscription_id' => $sub['subscription_code'] ?: ('SUB-' . str_pad($sub['id'], 6, '0', STR_PAD_LEFT)),
            'company'         => $sub['company_name'],
            'project'         => $sub['project_name'] ?: ($sub['linked_project_name'] ?: 'Standard Account'),
            'plan'            => $sub['plan_tier'],
            'billingCycle'    => $sub['billing_cycle'],
            'amount'          => $amount,
            'startDate'       => $sub['start_date'],
            'nextBillingDate' => $sub['next_billing_date'],
            'renewalType'     => $sub['renewal_type'],
            'paymentMethod'   => $sub['payment_method'] ?: 'Not Specified',
            'owner'           => $sub['owner_name'] ?: 'Unassigned',
            'notes'           => $sub['contract_scope'] ?: '',
            'billingAddress'  => $sub['billing_address'] ?: '',
            'mrr'             => $mrr,
            'arr'             => $arr,
        ]);

        subscriptions_json(true, 'Subscription fetched.', ['subscription' => $formatted]);
        break;

    // -------------------------------------------------------------------------
    // 3. CREATE SUBSCRIPTION
    // -------------------------------------------------------------------------
    case 'create':
        require_subscription_perm('create');

        $name = trim($request['name'] ?? '');
        if ($name === '') {
            subscriptions_json(false, 'Subscription Name is required.', [], 422);
        }

        $companyInput = trim($request['company'] ?? $request['company_name'] ?? '');
        $companyId = !empty($request['company_id']) ? (int)$request['company_id'] : null;

        // Company validation
        $finalCompanyName = $companyInput;
        if ($companyId !== null && $companyId > 0) {
            $chkComp = $pdo->prepare("SELECT id, name FROM companies WHERE id = :cid AND organization_id = :org_id LIMIT 1");
            $chkComp->execute([':cid' => $companyId, ':org_id' => $organizationId]);
            $compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
            if (!$compRow) {
                subscriptions_json(false, 'Selected company is invalid or does not belong to your organization.', [], 422);
            }
            $finalCompanyName = $compRow['name'];
        }

        if ($finalCompanyName === '') {
            subscriptions_json(false, 'Customer / Company is required.', [], 422);
        }

        // Contact validation (optional)
        $contactId = !empty($request['contact_id']) ? (int)$request['contact_id'] : null;
        if ($contactId !== null && $contactId > 0) {
            $chkCnt = $pdo->prepare("SELECT id FROM contacts WHERE id = :cid AND organization_id = :org_id LIMIT 1");
            $chkCnt->execute([':cid' => $contactId, ':org_id' => $organizationId]);
            if (!$chkCnt->fetchColumn()) {
                subscriptions_json(false, 'Selected contact does not belong to your organization.', [], 422);
            }
        }

        // Project validation (optional)
        $projectId = !empty($request['project_id']) ? (int)$request['project_id'] : null;
        $projectName = trim($request['project'] ?? $request['project_name'] ?? '');
        if ($projectId !== null && $projectId > 0) {
            $chkProj = $pdo->prepare("SELECT id, name FROM projects WHERE id = :pid AND organization_id = :org_id LIMIT 1");
            $chkProj->execute([':pid' => $projectId, ':org_id' => $organizationId]);
            $projRow = $chkProj->fetch(PDO::FETCH_ASSOC);
            if (!$projRow) {
                subscriptions_json(false, 'Selected project does not belong to your organization.', [], 422);
            }
            if ($projectName === '') {
                $projectName = $projRow['name'];
            }
        }

        $planTier = trim($request['plan'] ?? $request['plan_tier'] ?? 'Enterprise Tier');
        $billingCycle = trim($request['billing_cycle'] ?? $request['billingCycle'] ?? 'Monthly');
        if (!in_array($billingCycle, ['Monthly', 'Quarterly', 'Yearly'])) {
            $billingCycle = 'Monthly';
        }

        $rawAmount = trim((string)($request['amount'] ?? ''));
        if ($rawAmount === '' || !is_numeric($rawAmount)) {
            subscriptions_json(false, 'Amount must be a valid numeric value.', [], 422);
        }

        $amount = round((float)$rawAmount, 2);
        if ($amount < 0) {
            subscriptions_json(false, 'Amount cannot be negative.', [], 422);
        }

        $status = trim($request['status'] ?? 'Active');
        if (!in_array($status, ['Active', 'Trial', 'Past Due', 'Paused', 'Cancelled'])) {
            $status = 'Active';
        }

        if ($status !== 'Trial' && $amount <= 0) {
            subscriptions_json(false, "Amount must be greater than zero for {$status} subscriptions.", [], 422);
        }

        $startDate = !empty($request['start_date']) ? date('Y-m-d', strtotime($request['start_date'])) : date('Y-m-d');
        $nextBillingDate = !empty($request['next_billing_date']) ? date('Y-m-d', strtotime($request['next_billing_date'])) : null;
        if (!$nextBillingDate) {
            $nextBillingDate = match ($billingCycle) {
                'Quarterly' => date('Y-m-d', strtotime($startDate . ' +3 months')),
                'Yearly'    => date('Y-m-d', strtotime($startDate . ' +1 year')),
                default     => date('Y-m-d', strtotime($startDate . ' +1 month')),
            };
        }

        $renewalType = trim($request['renewal_type'] ?? $request['renewal'] ?? 'Automatic');
        if (!in_array($renewalType, ['Automatic', 'Manual'])) {
            $renewalType = 'Automatic';
        }

        $paymentMethod = trim($request['payment_method'] ?? $request['paymentMethod'] ?? '');
        $billingAddress = trim($request['billing_address'] ?? $request['billingAddress'] ?? '');
        $contractScope = trim($request['notes'] ?? $request['contract_scope'] ?? '');

        // Owner validation
        $ownerId = null;
        $reqOwner = $request['owner_id'] ?? $request['owner'] ?? null;
        if ($reqOwner !== null && $reqOwner !== '') {
            if (is_numeric($reqOwner)) {
                $chkOwner = $pdo->prepare("SELECT id FROM users WHERE id = :uid AND organization_id = :org_id AND status = 'active' LIMIT 1");
                $chkOwner->execute([':uid' => (int)$reqOwner, ':org_id' => $organizationId]);
                $ownerId = $chkOwner->fetchColumn() ? (int)$reqOwner : null;
            } else {
                $chkOwner = $pdo->prepare("SELECT id FROM users WHERE name = :uname AND organization_id = :org_id AND status = 'active' LIMIT 1");
                $chkOwner->execute([':uname' => $reqOwner, ':org_id' => $organizationId]);
                $ownerId = $chkOwner->fetchColumn() ? (int)$chkOwner->fetchColumn() : null;
            }
        }

        // Generate next deterministic subscription code
        $codeStmt = $pdo->prepare("SELECT MAX(id) FROM subscriptions");
        $codeStmt->execute();
        $nextNum = ((int)$codeStmt->fetchColumn()) + 1;
        $subscriptionCode = 'SUB-' . date('Y') . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $insSql = "
            INSERT INTO subscriptions (
                organization_id,
                subscription_code,
                name,
                company_id,
                company_name,
                contact_id,
                project_id,
                project_name,
                plan_tier,
                billing_cycle,
                amount,
                currency,
                status,
                start_date,
                trial_end_date,
                next_billing_date,
                renewal_type,
                payment_method,
                billing_address,
                contract_scope,
                owner_id,
                is_active,
                created_by,
                created_at
            ) VALUES (
                :org_id,
                :sub_code,
                :name,
                :company_id,
                :company_name,
                :contact_id,
                :project_id,
                :project_name,
                :plan_tier,
                :billing_cycle,
                :amount,
                'USD ($)',
                :status,
                :start_date,
                NULL,
                :next_billing_date,
                :renewal_type,
                :payment_method,
                :billing_address,
                :contract_scope,
                :owner_id,
                1,
                :created_by,
                NOW()
            )
        ";
        $insStmt = $pdo->prepare($insSql);
        $insStmt->execute([
            ':org_id'            => $organizationId,
            ':sub_code'          => $subscriptionCode,
            ':name'              => $name,
            ':company_id'        => $companyId,
            ':company_name'      => $finalCompanyName,
            ':contact_id'        => $contactId,
            ':project_id'        => $projectId,
            ':project_name'      => $projectName ?: null,
            ':plan_tier'         => $planTier,
            ':billing_cycle'     => $billingCycle,
            ':amount'            => $amount,
            ':status'            => $status,
            ':start_date'        => $startDate,
            ':next_billing_date' => $nextBillingDate,
            ':renewal_type'      => $renewalType,
            ':payment_method'    => $paymentMethod ?: null,
            ':billing_address'   => $billingAddress ?: null,
            ':contract_scope'    => $contractScope ?: null,
            ':owner_id'          => $ownerId,
            ':created_by'        => $currentUserId,
        ]);
        $newSubId = (int)$pdo->lastInsertId();

        // Initial invoice creation
        $invNumStmt = $pdo->prepare("SELECT MAX(id) FROM subscription_invoices");
        $invNumStmt->execute();
        $nextInvNum = ((int)$invNumStmt->fetchColumn()) + 1;
        $initialInvoiceNumber = 'INV-' . date('Y') . '-' . str_pad($nextInvNum, 3, '0', STR_PAD_LEFT);

        $initialInvStatus = ($status === 'Active') ? 'Paid' : (($status === 'Trial') ? 'Trial' : 'Pending');
        $paidDate = ($initialInvStatus === 'Paid') ? $startDate : null;

        $insInv = $pdo->prepare("
            INSERT INTO subscription_invoices (
                organization_id, subscription_id, invoice_number, amount, status, issue_date, paid_date, payment_method, notes, created_by, created_at
            ) VALUES (
                :org_id, :sub_id, :inv_num, :amount, :status, :issue_date, :paid_date, :pay_method, :notes, :created_by, NOW()
            )
        ");
        $insInv->execute([
            ':org_id'     => $organizationId,
            ':sub_id'     => $newSubId,
            ':inv_num'    => $initialInvoiceNumber,
            ':amount'     => $amount,
            ':status'     => $initialInvStatus,
            ':issue_date' => $startDate,
            ':paid_date'  => $paidDate,
            ':pay_method' => $paymentMethod ?: null,
            ':notes'      => 'Initial invoice generated upon subscription creation.',
            ':created_by' => $currentUserId,
        ]);

        // Log creation to polymorphic team_activities
        $actStmt = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'subscription_created', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ");
        $actStmt->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => 'Subscription Created: ' . $name,
            ':desc'       => "Subscription {$subscriptionCode} was created for {$finalCompanyName} ({$planTier}).",
            ':rel_id'     => $newSubId,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, 'Subscription created successfully.', [
            'id'                => $newSubId,
            'subscription_code' => $subscriptionCode,
            'name'              => $name,
        ], 201);
        break;

    // -------------------------------------------------------------------------
    // 4. UPDATE SUBSCRIPTION
    // -------------------------------------------------------------------------
    case 'update':
        require_subscription_perm('edit');

        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        // Verify exists in tenant
        $chk = $pdo->prepare("SELECT * FROM subscriptions WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chk->execute([':id' => $id, ':org_id' => $organizationId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            subscriptions_json(false, 'Subscription not found or access denied.', [], 404);
        }

        $name = trim($request['name'] ?? $existing['name']);
        if ($name === '') {
            subscriptions_json(false, 'Subscription Name is required.', [], 422);
        }

        $companyInput = trim($request['company'] ?? $request['company_name'] ?? $existing['company_name']);
        $companyId = isset($request['company_id']) ? (!empty($request['company_id']) ? (int)$request['company_id'] : null) : $existing['company_id'];
        $finalCompanyName = $companyInput;

        if ($companyId !== null && $companyId > 0) {
            $chkComp = $pdo->prepare("SELECT id, name FROM companies WHERE id = :cid AND organization_id = :org_id LIMIT 1");
            $chkComp->execute([':cid' => $companyId, ':org_id' => $organizationId]);
            $compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
            if ($compRow) {
                $finalCompanyName = $compRow['name'];
            }
        }

        $projectId = isset($request['project_id']) ? (!empty($request['project_id']) ? (int)$request['project_id'] : null) : $existing['project_id'];
        $projectName = isset($request['project']) ? trim($request['project']) : (isset($request['project_name']) ? trim($request['project_name']) : $existing['project_name']);

        $planTier = trim($request['plan'] ?? $request['plan_tier'] ?? $existing['plan_tier']);
        $billingCycle = trim($request['billing_cycle'] ?? $request['billingCycle'] ?? $existing['billing_cycle']);
        if (!in_array($billingCycle, ['Monthly', 'Quarterly', 'Yearly'])) {
            $billingCycle = $existing['billing_cycle'];
        }

        $status = trim($request['status'] ?? $existing['status']);
        if (!in_array($status, ['Active', 'Trial', 'Past Due', 'Paused', 'Cancelled'])) {
            $status = $existing['status'];
        }

        $amount = (float)$existing['amount'];
        if (isset($request['amount'])) {
            $rawAmount = trim((string)$request['amount']);
            if ($rawAmount === '' || !is_numeric($rawAmount)) {
                subscriptions_json(false, 'Amount must be a valid numeric value.', [], 422);
            }
            $parsedAmount = round((float)$rawAmount, 2);
            if ($parsedAmount < 0) {
                subscriptions_json(false, 'Amount cannot be negative.', [], 422);
            }
            if ($status !== 'Trial' && $parsedAmount <= 0) {
                subscriptions_json(false, "Amount must be greater than zero for {$status} subscriptions.", [], 422);
            }
            $amount = $parsedAmount;
        } else {
            if ($status !== 'Trial' && $amount <= 0) {
                subscriptions_json(false, "Amount must be greater than zero for {$status} subscriptions.", [], 422);
            }
        }

        $startDate = !empty($request['start_date']) ? date('Y-m-d', strtotime($request['start_date'])) : $existing['start_date'];
        $nextBillingDate = !empty($request['next_billing_date']) ? date('Y-m-d', strtotime($request['next_billing_date'])) : $existing['next_billing_date'];

        $renewalType = trim($request['renewal_type'] ?? $request['renewal'] ?? $existing['renewal_type']);
        $paymentMethod = isset($request['payment_method']) ? trim($request['payment_method']) : (isset($request['paymentMethod']) ? trim($request['paymentMethod']) : $existing['payment_method']);
        $billingAddress = isset($request['billing_address']) ? trim($request['billing_address']) : (isset($request['billingAddress']) ? trim($request['billingAddress']) : $existing['billing_address']);
        $contractScope = isset($request['notes']) ? trim($request['notes']) : (isset($request['contract_scope']) ? trim($request['contract_scope']) : $existing['contract_scope']);

        // Owner validation
        $ownerId = $existing['owner_id'];
        if (isset($request['owner_id']) || isset($request['owner'])) {
            $reqOwner = $request['owner_id'] ?? $request['owner'];
            if ($reqOwner !== null && $reqOwner !== '') {
                if (is_numeric($reqOwner)) {
                    $chkOwner = $pdo->prepare("SELECT id FROM users WHERE id = :uid AND organization_id = :org_id AND status = 'active' LIMIT 1");
                    $chkOwner->execute([':uid' => (int)$reqOwner, ':org_id' => $organizationId]);
                    $ownerId = $chkOwner->fetchColumn() ? (int)$reqOwner : null;
                } else {
                    $chkOwner = $pdo->prepare("SELECT id FROM users WHERE name = :uname AND organization_id = :org_id AND status = 'active' LIMIT 1");
                    $chkOwner->execute([':uname' => $reqOwner, ':org_id' => $organizationId]);
                    $ownerId = $chkOwner->fetchColumn() ? (int)$chkOwner->fetchColumn() : null;
                }
            } else {
                $ownerId = null;
            }
        }

        $updSql = "
            UPDATE subscriptions SET
                name = :name,
                company_id = :company_id,
                company_name = :company_name,
                project_id = :project_id,
                project_name = :project_name,
                plan_tier = :plan_tier,
                billing_cycle = :billing_cycle,
                amount = :amount,
                status = :status,
                start_date = :start_date,
                next_billing_date = :next_billing_date,
                renewal_type = :renewal_type,
                payment_method = :payment_method,
                billing_address = :billing_address,
                contract_scope = :contract_scope,
                owner_id = :owner_id,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ";
        $updStmt = $pdo->prepare($updSql);
        $updStmt->execute([
            ':name'              => $name,
            ':company_id'        => $companyId,
            ':company_name'      => $finalCompanyName,
            ':project_id'        => $projectId,
            ':project_name'      => $projectName ?: null,
            ':plan_tier'         => $planTier,
            ':billing_cycle'     => $billingCycle,
            ':amount'            => $amount,
            ':status'            => $status,
            ':start_date'        => $startDate,
            ':next_billing_date' => $nextBillingDate,
            ':renewal_type'      => $renewalType,
            ':payment_method'    => $paymentMethod ?: null,
            ':billing_address'   => $billingAddress ?: null,
            ':contract_scope'    => $contractScope ?: null,
            ':owner_id'          => $ownerId,
            ':id'                => $id,
            ':org_id'            => $organizationId,
        ]);

        // Log activity
        $actStmt = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'subscription_updated', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ");
        $actStmt->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => 'Subscription Updated: ' . $name,
            ':desc'       => "Subscription details updated for {$name}.",
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, 'Subscription updated successfully.', ['id' => $id]);
        break;

    // -------------------------------------------------------------------------
    // 5. CHANGE STATUS
    // -------------------------------------------------------------------------
    case 'change_status':
        require_subscription_perm('edit');
        $id = (int)($request['id'] ?? 0);
        $newStatus = trim($request['status'] ?? '');

        if ($id <= 0 || !in_array($newStatus, ['Active', 'Trial', 'Past Due', 'Paused', 'Cancelled'])) {
            subscriptions_json(false, 'Valid subscription ID and status are required.', [], 400);
        }

        $chk = $pdo->prepare("SELECT id, amount, status FROM subscriptions WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chk->execute([':id' => $id, ':org_id' => $organizationId]);
        $subRow = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$subRow) {
            subscriptions_json(false, 'Subscription not found or access denied.', [], 404);
        }

        if ($newStatus !== 'Trial' && (float)$subRow['amount'] <= 0) {
            subscriptions_json(false, "Cannot change status to {$newStatus} because this subscription has an amount of 0. Please edit the subscription and set a valid amount first.", [], 422);
        }

        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = :status, updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id
        ");
        $stmt->execute([':status' => $newStatus, ':id' => $id, ':org_id' => $organizationId]);

        // Log status change
        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'status_changed', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ")->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => "Status Changed → {$newStatus}",
            ':desc'       => "Subscription status was changed to {$newStatus}.",
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, "Subscription status changed to {$newStatus}.", ['id' => $id, 'status' => $newStatus]);
        break;

    // -------------------------------------------------------------------------
    // 6. CHANGE PLAN
    // -------------------------------------------------------------------------
    case 'change_plan':
        require_subscription_perm('edit');
        $id = (int)($request['id'] ?? 0);
        $newPlan = trim($request['plan'] ?? $request['plan_tier'] ?? '');
        $rawAmount = trim($request['amount'] ?? '');
        $cleanAmount = preg_replace('/[^0-9.]/', '', $rawAmount);
        $newAmount = $cleanAmount !== '' ? (float)$cleanAmount : null;

        if ($id <= 0 || $newPlan === '') {
            subscriptions_json(false, 'Subscription ID and Plan Tier are required.', [], 400);
        }

        $sql = "UPDATE subscriptions SET plan_tier = :plan" . ($newAmount !== null && $newAmount > 0 ? ", amount = :amt" : "") . ", updated_at = NOW() WHERE id = :id AND organization_id = :org_id";
        $stmt = $pdo->prepare($sql);
        $params = [':plan' => $newPlan, ':id' => $id, ':org_id' => $organizationId];
        if ($newAmount !== null && $newAmount > 0) {
            $params[':amt'] = $newAmount;
        }
        $stmt->execute($params);

        // Log plan change
        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'plan_changed', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ")->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => "Plan Updated → {$newPlan}",
            ':desc'       => "Subscription plan updated to {$newPlan}" . ($newAmount ? " (\${$newAmount})" : "") . ".",
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, "Plan tier updated to {$newPlan}.", ['id' => $id, 'plan' => $newPlan]);
        break;

    // -------------------------------------------------------------------------
    // 7. TOGGLE PAUSE / RESUME
    // -------------------------------------------------------------------------
    case 'toggle_pause':
        require_subscription_perm('edit');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        $chk = $pdo->prepare("SELECT status FROM subscriptions WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chk->execute([':id' => $id, ':org_id' => $organizationId]);
        $currentStatus = $chk->fetchColumn();
        if (!$currentStatus) {
            subscriptions_json(false, 'Subscription not found.', [], 404);
        }

        $newStatus = ($currentStatus === 'Paused') ? 'Active' : 'Paused';
        $pdo->prepare("UPDATE subscriptions SET status = :status, updated_at = NOW() WHERE id = :id AND organization_id = :org_id")
            ->execute([':status' => $newStatus, ':id' => $id, ':org_id' => $organizationId]);

        $actionWord = ($newStatus === 'Paused') ? 'Paused' : 'Resumed';
        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'subscription_toggled', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ")->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => "Subscription {$actionWord}",
            ':desc'       => "Subscription was {$actionWord} by " . ($currentUser['name'] ?? 'User') . ".",
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, "Subscription {$actionWord} successfully.", ['id' => $id, 'status' => $newStatus]);
        break;

    // -------------------------------------------------------------------------
    // 8. CANCEL SUBSCRIPTION
    // -------------------------------------------------------------------------
    case 'cancel':
        require_subscription_perm('edit');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        $stmt = $pdo->prepare("UPDATE subscriptions SET status = 'Cancelled', renewal_type = 'Manual', updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        if ($stmt->rowCount() === 0) {
            subscriptions_json(false, 'Subscription not found or already cancelled.', [], 404);
        }

        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'subscription_cancelled', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ")->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => 'Subscription Cancelled',
            ':desc'       => 'Subscription was marked as Cancelled.',
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, 'Subscription cancelled successfully.', ['id' => $id, 'status' => 'Cancelled', 'renewal_type' => 'Manual']);
        break;

    // -------------------------------------------------------------------------
    // 9. DELETE SUBSCRIPTION (Transactional Cascade Cleanup)
    // -------------------------------------------------------------------------
    case 'delete':
        require_subscription_perm('delete');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        $chk = $pdo->prepare("SELECT name FROM subscriptions WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chk->execute([':id' => $id, ':org_id' => $organizationId]);
        $subName = $chk->fetchColumn();
        if (!$subName) {
            subscriptions_json(false, 'Subscription not found or access denied.', [], 404);
        }

        $pdo->beginTransaction();
        try {
            // Delete subscription invoices
            $pdo->prepare("DELETE FROM subscription_invoices WHERE organization_id = :org_id AND subscription_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete polymorphic notes
            $pdo->prepare("DELETE FROM team_notes WHERE organization_id = :org_id AND related_type = 'subscriptions' AND related_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete polymorphic activities
            $pdo->prepare("DELETE FROM team_activities WHERE organization_id = :org_id AND related_entity = 'subscriptions' AND related_entity_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete polymorphic tasks
            $pdo->prepare("DELETE FROM tasks WHERE organization_id = :org_id AND related_type = 'subscriptions' AND related_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete polymorphic calendar events
            $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = :org_id AND related_type = 'subscriptions' AND related_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete subscription
            $pdo->prepare("DELETE FROM subscriptions WHERE id = :id AND organization_id = :org_id")
                ->execute([':id' => $id, ':org_id' => $organizationId]);

            $pdo->commit();
            subscriptions_json(true, "Subscription '{$subName}' deleted successfully.", ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            subscriptions_json(false, 'Failed to delete subscription: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // 10. DRAWER DATA (Deep payload for all 5 tabs)
    // -------------------------------------------------------------------------
    case 'drawer_data':
        require_subscription_perm('view');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        // Fetch subscription
        $subStmt = $pdo->prepare("
            SELECT 
                s.*,
                u.name AS owner_name,
                cnt.name AS contact_name,
                c.name AS linked_company_name,
                p.name AS linked_project_name
            FROM subscriptions s
            LEFT JOIN users u ON u.id = s.owner_id AND u.organization_id = s.organization_id
            LEFT JOIN contacts cnt ON cnt.id = s.contact_id AND cnt.organization_id = s.organization_id
            LEFT JOIN companies c ON c.id = s.company_id AND c.organization_id = s.organization_id
            LEFT JOIN projects p ON p.id = s.project_id AND p.organization_id = s.organization_id
            WHERE s.id = :id AND s.organization_id = :org_id
            LIMIT 1
        ");
        $subStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $sub = $subStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sub) {
            subscriptions_json(false, 'Subscription not found.', [], 404);
        }

        $amount = (float)$sub['amount'];
        $billingCycle = $sub['billing_cycle'];
        $mrr = match ($billingCycle) {
            'Quarterly' => round($amount / 3, 2),
            'Yearly'    => round($amount / 12, 2),
            default     => $amount,
        };
        $arr = round($mrr * 12, 2);

        $formattedSub = array_merge($sub, [
            'id'              => (int)$sub['id'],
            'subscription_id' => $sub['subscription_code'] ?: ('SUB-' . str_pad($sub['id'], 6, '0', STR_PAD_LEFT)),
            'company'         => $sub['company_name'],
            'project'         => $sub['project_name'] ?: ($sub['linked_project_name'] ?: 'Standard Account'),
            'plan'            => $sub['plan_tier'],
            'billingCycle'    => $sub['billing_cycle'],
            'amount'          => $amount,
            'startDate'       => $sub['start_date'],
            'nextBillingDate' => $sub['next_billing_date'],
            'renewalType'     => $sub['renewal_type'],
            'paymentMethod'   => $sub['payment_method'] ?: 'Not Specified',
            'owner'           => $sub['owner_name'] ?: 'Unassigned',
            'notes'           => $sub['contract_scope'] ?: '',
            'billingAddress'  => $sub['billing_address'] ?: '',
            'mrr'             => $mrr,
            'arr'             => $arr,
        ]);

        // Invoices from subscription_invoices
        $invStmt = $pdo->prepare("
            SELECT id, invoice_number, amount, status, issue_date, paid_date, payment_method, notes, created_at
            FROM subscription_invoices
            WHERE organization_id = :org_id AND subscription_id = :id
            ORDER BY issue_date DESC, id DESC
        ");
        $invStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $invoicesRaw = $invStmt->fetchAll(PDO::FETCH_ASSOC);

        $invoices = array_map(function ($inv) {
            return [
                'id'             => $inv['invoice_number'],
                'invoice_id'     => (int)$inv['id'],
                'invoice_number' => $inv['invoice_number'],
                'date'           => $inv['issue_date'],
                'amount'         => (float)$inv['amount'],
                'status'         => $inv['status'],
                'paid_date'      => $inv['paid_date'],
                'payment_method' => $inv['payment_method'],
            ];
        }, $invoicesRaw);

        // Activities from team_activities
        $actStmt = $pdo->prepare("
            SELECT a.id, a.activity_type, a.title, a.description, a.created_at, u.name AS user_name
            FROM team_activities a
            LEFT JOIN users u ON u.id = a.user_id AND u.organization_id = a.organization_id
            WHERE a.organization_id = :org_id AND a.related_entity = 'subscriptions' AND a.related_entity_id = :id
            ORDER BY a.created_at DESC
        ");
        $actStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $activitiesRaw = $actStmt->fetchAll(PDO::FETCH_ASSOC);

        $activities = array_map(function ($a) {
            return [
                'id'          => $a['id'],
                'type'        => $a['activity_type'] ?: 'Subscription',
                'title'       => $a['title'],
                'description' => $a['description'],
                'actor'       => $a['user_name'] ?: 'System Automated',
                'date'        => date('M d, Y • h:i A', strtotime($a['created_at'])),
                'created_at'  => $a['created_at'],
            ];
        }, $activitiesRaw);

        // Notes from team_notes
        $notesStmt = $pdo->prepare("
            SELECT n.id, n.title, n.content, n.is_pinned, n.created_at, n.updated_at, u.name AS author_name
            FROM team_notes n
            LEFT JOIN users u ON u.id = n.created_by AND u.organization_id = n.organization_id
            WHERE n.organization_id = :org_id AND n.related_type = 'subscriptions' AND n.related_id = :id
            ORDER BY n.is_pinned DESC, n.created_at DESC
        ");
        $notesStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $notesRaw = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

        $notes = array_map(function ($n) {
            return [
                'id'         => (int)$n['id'],
                'author'     => $n['author_name'] ?: 'Staff',
                'date'       => date('M d, Y • h:i A', strtotime($n['created_at'])),
                'text'       => $n['content'],
                'content'    => $n['content'],
                'title'      => $n['title'],
                'pinned'     => (bool)$n['is_pinned'],
                'is_pinned'  => (int)$n['is_pinned'],
                'created_at' => $n['created_at'],
            ];
        }, $notesRaw);

        subscriptions_json(true, 'Drawer data fetched successfully.', [
            'subscription' => $formattedSub,
            'overview'     => $formattedSub,
            'invoices'     => $invoices,
            'activity'     => $activities,
            'activities'   => $activities,
            'customNotes'  => $notes,
            'notes'        => $notes,
            'billing'      => [
                'mrr'            => $mrr,
                'arr'            => $arr,
                'paymentMethod'  => $formattedSub['paymentMethod'],
                'billingAddress' => $formattedSub['billingAddress'],
                'billingCycle'   => $formattedSub['billingCycle'],
                'currentAmount'  => $formattedSub['amount'],
                'nextBillingDate'=> $formattedSub['nextBillingDate'],
                'renewalType'    => $formattedSub['renewalType'],
                'paymentStatus'  => $formattedSub['status'],
            ],
        ]);
        break;

    // -------------------------------------------------------------------------
    // 11. RECORD PAYMENT
    // -------------------------------------------------------------------------
    case 'record_payment':
        require_subscription_perm('edit');
        $id = (int)($request['subscription_id'] ?? $request['id'] ?? 0);
        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        $chk = $pdo->prepare("SELECT name, company_name, amount, status FROM subscriptions WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chk->execute([':id' => $id, ':org_id' => $organizationId]);
        $sub = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$sub) {
            subscriptions_json(false, 'Subscription not found.', [], 404);
        }

        $rawAmount = trim($request['amount'] ?? (string)$sub['amount']);
        $cleanAmount = preg_replace('/[^0-9.]/', '', $rawAmount);
        $amount = $cleanAmount !== '' ? (float)$cleanAmount : (float)$sub['amount'];
        $paymentDate = !empty($request['date']) ? date('Y-m-d', strtotime($request['date'])) : (!empty($request['payment_date']) ? date('Y-m-d', strtotime($request['payment_date'])) : date('Y-m-d'));
        $paymentMethod = trim($request['payment_method'] ?? $request['paymentMethod'] ?? 'Credit Card');

        // Generate invoice number
        $invNumStmt = $pdo->prepare("SELECT MAX(id) FROM subscription_invoices");
        $invNumStmt->execute();
        $nextInvNum = ((int)$invNumStmt->fetchColumn()) + 1;
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($nextInvNum, 3, '0', STR_PAD_LEFT);

        // Insert paid invoice
        $insInv = $pdo->prepare("
            INSERT INTO subscription_invoices (
                organization_id, subscription_id, invoice_number, amount, status, issue_date, paid_date, payment_method, notes, created_by, created_at
            ) VALUES (
                :org_id, :sub_id, :inv_num, :amount, 'Paid', :issue_date, :paid_date, :pay_method, :notes, :created_by, NOW()
            )
        ");
        $insInv->execute([
            ':org_id'     => $organizationId,
            ':sub_id'     => $id,
            ':inv_num'    => $invoiceNumber,
            ':amount'     => $amount,
            ':issue_date' => $paymentDate,
            ':paid_date'  => $paymentDate,
            ':pay_method' => $paymentMethod,
            ':notes'      => 'Manual payment recorded via Subscriptions module.',
            ':created_by' => $currentUserId,
        ]);
        $newInvId = (int)$pdo->lastInsertId();

        // If subscription was Past Due, resume to Active
        if ($sub['status'] === 'Past Due') {
            $pdo->prepare("UPDATE subscriptions SET status = 'Active', updated_at = NOW() WHERE id = :id AND organization_id = :org_id")
                ->execute([':id' => $id, ':org_id' => $organizationId]);
        }

        // Log activity
        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'payment_received', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ")->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => "Payment Received (\${$amount})",
            ':desc'       => "Payment of \${$amount} received via {$paymentMethod} for invoice {$invoiceNumber}.",
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, "Payment of \${$amount} recorded successfully for {$sub['company_name']}.", [
            'invoice_id'     => $newInvId,
            'invoice_number' => $invoiceNumber,
            'amount'         => $amount,
            'status'         => 'Paid',
        ]);
        break;

    // -------------------------------------------------------------------------
    // 12. EDIT SCOPE
    // -------------------------------------------------------------------------
    case 'edit_scope':
        require_subscription_perm('edit');
        $id = (int)($request['id'] ?? 0);
        $scope = trim($request['notes'] ?? $request['contract_scope'] ?? '');

        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        $stmt = $pdo->prepare("UPDATE subscriptions SET contract_scope = :scope, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':scope' => $scope, ':id' => $id, ':org_id' => $organizationId]);

        subscriptions_json(true, 'Internal contract scope updated.', ['id' => $id, 'notes' => $scope]);
        break;

    // -------------------------------------------------------------------------
    // 13. CHANGE PAYMENT METHOD
    // -------------------------------------------------------------------------
    case 'change_payment_method':
        require_subscription_perm('edit');
        $id = (int)($request['id'] ?? 0);
        $method = trim($request['payment_method'] ?? $request['paymentMethod'] ?? '');

        if ($id <= 0 || $method === '') {
            subscriptions_json(false, 'Subscription ID and payment method are required.', [], 400);
        }

        $stmt = $pdo->prepare("UPDATE subscriptions SET payment_method = :method, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':method' => $method, ':id' => $id, ':org_id' => $organizationId]);

        // Log activity
        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, 'payment_method_updated', :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ")->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':title'      => 'Payment Method Updated',
            ':desc'       => "Payment method was updated to {$method}.",
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, 'Payment method updated.', ['id' => $id, 'payment_method' => $method]);
        break;

    // -------------------------------------------------------------------------
    // 14. EDIT BILLING ADDRESS
    // -------------------------------------------------------------------------
    case 'edit_billing_address':
        require_subscription_perm('edit');
        $id = (int)($request['id'] ?? 0);
        $address = trim($request['billing_address'] ?? $request['billingAddress'] ?? '');

        if ($id <= 0) {
            subscriptions_json(false, 'Invalid subscription ID.', [], 400);
        }

        $stmt = $pdo->prepare("UPDATE subscriptions SET billing_address = :addr, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':addr' => $address, ':id' => $id, ':org_id' => $organizationId]);

        subscriptions_json(true, 'Billing address updated.', ['id' => $id, 'billing_address' => $address]);
        break;

    // -------------------------------------------------------------------------
    // 15. SUB-ENTITY: ADD NOTE
    // -------------------------------------------------------------------------
    case 'add_note':
        require_subscription_perm('edit');
        $id = (int)($request['subscription_id'] ?? $request['id'] ?? 0);
        $content = trim($request['content'] ?? $request['text'] ?? '');
        $isPinned = !empty($request['pinned']) || !empty($request['is_pinned']) ? 1 : 0;

        if ($id <= 0 || $content === '') {
            subscriptions_json(false, 'Subscription ID and note content are required.', [], 422);
        }

        // Verify subscription belongs to tenant
        $chk = $pdo->prepare("SELECT id FROM subscriptions WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chk->execute([':id' => $id, ':org_id' => $organizationId]);
        if (!$chk->fetchColumn()) {
            subscriptions_json(false, 'Subscription not found.', [], 404);
        }

        $ins = $pdo->prepare("
            INSERT INTO team_notes (
                organization_id, member_id, related_type, related_id, created_by, title, content, is_pinned, created_at, updated_at
            ) VALUES (
                :org_id, NULL, 'subscriptions', :rel_id, :created_by, NULL, :content, :pinned, NOW(), NOW()
            )
        ");
        $ins->execute([
            ':org_id'     => $organizationId,
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
            ':content'    => $content,
            ':pinned'     => $isPinned,
        ]);
        $newNoteId = (int)$pdo->lastInsertId();

        subscriptions_json(true, 'Note added successfully.', [
            'note_id'   => $newNoteId,
            'id'        => $newNoteId,
            'content'   => $content,
            'is_pinned' => $isPinned,
            'author'    => $currentUser['name'] ?? 'User',
            'date'      => date('M d, Y • h:i A'),
        ]);
        break;

    // -------------------------------------------------------------------------
    // 16. SUB-ENTITY: EDIT NOTE
    // -------------------------------------------------------------------------
    case 'edit_note':
        require_subscription_perm('edit');
        $noteId = (int)($request['note_id'] ?? 0);
        $content = trim($request['content'] ?? $request['text'] ?? '');

        if ($noteId <= 0 || $content === '') {
            subscriptions_json(false, 'Note ID and content are required.', [], 422);
        }

        $stmt = $pdo->prepare("
            UPDATE team_notes 
            SET content = :content, updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id AND related_type = 'subscriptions'
        ");
        $stmt->execute([':content' => $content, ':id' => $noteId, ':org_id' => $organizationId]);

        if ($stmt->rowCount() === 0) {
            subscriptions_json(false, 'Note not found or access denied.', [], 404);
        }

        subscriptions_json(true, 'Note updated successfully.', ['note_id' => $noteId, 'content' => $content]);
        break;

    // -------------------------------------------------------------------------
    // 17. SUB-ENTITY: TOGGLE PIN NOTE
    // -------------------------------------------------------------------------
    case 'toggle_pin_note':
        require_subscription_perm('edit');
        $noteId = (int)($request['note_id'] ?? 0);

        if ($noteId <= 0) {
            subscriptions_json(false, 'Invalid note ID.', [], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE team_notes 
            SET is_pinned = 1 - is_pinned, updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id AND related_type = 'subscriptions'
        ");
        $stmt->execute([':id' => $noteId, ':org_id' => $organizationId]);

        // Get new pinned state
        $chk = $pdo->prepare("SELECT is_pinned FROM team_notes WHERE id = :id AND organization_id = :org_id");
        $chk->execute([':id' => $noteId, ':org_id' => $organizationId]);
        $newPinned = (int)$chk->fetchColumn();

        subscriptions_json(true, $newPinned ? 'Note pinned.' : 'Note unpinned.', ['note_id' => $noteId, 'is_pinned' => $newPinned]);
        break;

    // -------------------------------------------------------------------------
    // 18. SUB-ENTITY: DELETE NOTE
    // -------------------------------------------------------------------------
    case 'delete_note':
        require_subscription_perm('edit');
        $noteId = (int)($request['note_id'] ?? 0);

        if ($noteId <= 0) {
            subscriptions_json(false, 'Invalid note ID.', [], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM team_notes WHERE id = :id AND organization_id = :org_id AND related_type = 'subscriptions'");
        $stmt->execute([':id' => $noteId, ':org_id' => $organizationId]);

        if ($stmt->rowCount() === 0) {
            subscriptions_json(false, 'Note not found or access denied.', [], 404);
        }

        subscriptions_json(true, 'Note deleted successfully.', ['note_id' => $noteId]);
        break;

    // -------------------------------------------------------------------------
    // 19. SUB-ENTITY: LOG ACTIVITY
    // -------------------------------------------------------------------------
    case 'log_activity':
        require_subscription_perm('create');
        $id = (int)($request['subscription_id'] ?? $request['id'] ?? 0);
        $type = trim($request['type'] ?? $request['activity_type'] ?? 'Subscription');
        $title = trim($request['title'] ?? $request['summary'] ?? '');
        $description = trim($request['description'] ?? $title);

        if ($id <= 0 || $title === '') {
            subscriptions_json(false, 'Subscription ID and title are required.', [], 422);
        }

        $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                :org_id, :user_id, :type, :title, :desc, 'subscriptions', :rel_id, :created_by, NOW()
            )
        ")->execute([
            ':org_id'     => $organizationId,
            ':user_id'    => $currentUserId,
            ':type'       => $type,
            ':title'      => $title,
            ':desc'       => $description,
            ':rel_id'     => $id,
            ':created_by' => $currentUserId,
        ]);

        subscriptions_json(true, 'Activity logged.', ['id' => $id]);
        break;

    // -------------------------------------------------------------------------
    // 20. CSV EXPORT
    // -------------------------------------------------------------------------
    case 'export_csv':
        require_subscription_perm('export');

        $search       = trim($request['search'] ?? '');
        $statusFilter = trim($request['status'] ?? 'All');
        $planFilter   = trim($request['plan'] ?? 'All');
        $cycleFilter  = trim($request['billing_cycle'] ?? 'All');

        $where = ['s.organization_id = :org_id'];
        $params = [':org_id' => $organizationId];

        if ($search !== '') {
            $where[] = '(s.name LIKE :s1 OR s.subscription_code LIKE :s2 OR s.company_name LIKE :s3 OR s.plan_tier LIKE :s4)';
            $sParam = '%' . $search . '%';
            $params[':s1'] = $sParam;
            $params[':s2'] = $sParam;
            $params[':s3'] = $sParam;
            $params[':s4'] = $sParam;
        }

        if ($statusFilter !== '' && $statusFilter !== 'All') {
            $where[] = 's.status = :status';
            $params[':status'] = $statusFilter;
        }

        if ($planFilter !== '' && $planFilter !== 'All') {
            $where[] = 's.plan_tier = :plan';
            $params[':plan'] = $planFilter;
        }

        if ($cycleFilter !== '' && $cycleFilter !== 'All') {
            $where[] = 's.billing_cycle = :cycle';
            $params[':cycle'] = $cycleFilter;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                s.id,
                s.subscription_code,
                s.name,
                s.company_name,
                s.plan_tier,
                s.billing_cycle,
                s.amount,
                s.status,
                s.start_date,
                s.next_billing_date,
                s.renewal_type,
                s.payment_method,
                u.name AS owner_name,
                s.created_at
            FROM subscriptions s
            LEFT JOIN users u ON u.id = s.owner_id AND u.organization_id = s.organization_id
            WHERE {$whereSql}
            ORDER BY s.name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subscriptions_export_' . date('Y-m-d') . '.csv"');

        $outHandle = fopen('php://output', 'w');
        fputcsv($outHandle, [
            'Subscription Code', 'Subscription Name', 'Company', 'Plan',
            'Billing Cycle', 'Amount ($)', 'Status', 'Start Date',
            'Next Billing Date', 'Renewal Type', 'Payment Method',
            'Owner', 'Created At'
        ]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($outHandle, [
                $row['subscription_code'] ?: ('SUB-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT)),
                $row['name'],
                $row['company_name'],
                $row['plan_tier'],
                $row['billing_cycle'],
                number_format((float)$row['amount'], 2),
                $row['status'],
                $row['start_date'],
                $row['next_billing_date'] ?: '',
                $row['renewal_type'],
                $row['payment_method'] ?: '',
                $row['owner_name'] ?: 'Unassigned',
                $row['created_at'],
            ]);
        }
        fclose($outHandle);
        exit;

    default:
        subscriptions_json(false, "Unknown action: '{$action}'.", [], 400);
}