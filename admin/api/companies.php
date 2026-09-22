<?php
/**
 * NexFlow CRM - Companies Module API
 * Multi-tenant, organization-scoped controller for Companies, Contacts, Deals, Tasks, Notes, Activities, and Meetings.
 * Strictly 0 mock data, 100% MySQL source of truth.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function companies_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authenticate user
$currentUser = nexflow_current_user();
if (!$currentUser) {
    companies_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId = (int)$currentUser['id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    companies_json(false, 'Database connection failed: ' . $e->getMessage(), [], 500);
}

// Support GET, POST (form-data/urlencoded) and raw JSON
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true) ?? [];
$request = array_merge($_GET, $_POST, $jsonData);
$action = trim($request['action'] ?? 'list');

// Helper: check permissions
function require_company_perm(string $actionName): void
{
    $permMap = [
        'view'   => 'companies.view',
        'create' => 'companies.create',
        'edit'   => 'companies.edit',
        'delete' => 'companies.delete',
        'export' => 'companies.export',
    ];
    $perm = $permMap[$actionName] ?? 'companies.view';
    if (!has_permission($perm)) {
        companies_json(false, "Forbidden. Missing permission: {$perm}", [], 403);
    }
}

// Helper: format relative time
function company_time_ago(?string $datetime): string
{
    if (empty($datetime)) return 'Never';
    $time = strtotime($datetime);
    if (!$time) return 'Never';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

// Helper: generate initials
function company_initials(?string $name): string
{
    $trimmed = trim((string)$name);
    if ($trimmed === '') return 'CO';
    $parts = preg_split('/\s+/', $trimmed);
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($trimmed, 0, 2));
}

// Helper: deterministic color based on string
function company_color(?string $str): string
{
    $palette = ['#7C3AED', '#0284C7', '#059669', '#EA580C', '#DC2626', '#4F46E5', '#0891B2', '#D97706'];
    $hash = crc32((string)$str);
    return $palette[abs($hash) % count($palette)];
}

// -----------------------------------------------------------------------------
// ROUTE ACTIONS
// -----------------------------------------------------------------------------
switch ($action) {

    // -------------------------------------------------------------------------
    // 1. BOOTSTRAP / LIST
    // -------------------------------------------------------------------------
    case 'bootstrap':
    case 'list':
        require_company_perm('view');

        $search = trim($request['search'] ?? '');
        $relationship = trim($request['relationship'] ?? 'All');
        $cardFilter = trim($request['card_filter'] ?? 'All');
        $filterIndustry = trim($request['industry'] ?? 'All');
        $filterOwner = trim($request['owner'] ?? 'All');
        $filterDealsRange = trim($request['deals_range'] ?? 'All');
        $accountView = trim($request['account_view'] ?? 'all');
        $sortBy = trim($request['sort'] ?? 'name-asc');
        $page = max(1, (int)($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($request['page_size'] ?? 8)));

        // Base query
        $where = ['c.organization_id = :org_id'];
        $params = [':org_id' => $organizationId];

        // Search
        if ($search !== '') {
            $where[] = '(c.name LIKE :search1 OR c.domain LIKE :search2 OR c.industry LIKE :search3 OR c.location LIKE :search4)';
            $sParam = '%' . $search . '%';
            $params[':search1'] = $sParam;
            $params[':search2'] = $sParam;
            $params[':search3'] = $sParam;
            $params[':search4'] = $sParam;
        }

        // Card Filter
        if ($cardFilter === 'New') {
            $where[] = 'c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif ($cardFilter === 'OpenDeals') {
            $where[] = '(SELECT COUNT(*) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = \'open\') > 0';
        } elseif (in_array($cardFilter, ['Customer', 'Prospect', 'Partner'])) {
            $where[] = 'c.relationship = :card_rel';
            $params[':card_rel'] = $cardFilter;
        } elseif ($cardFilter === 'Inactive') {
            $where[] = 'c.is_active = 0';
        }

        // Industry Filter
        if ($filterIndustry !== '' && $filterIndustry !== 'All') {
            $where[] = 'c.industry = :ind';
            $params[':ind'] = $filterIndustry;
        }

        // Owner Filter
        if ($filterOwner !== '' && $filterOwner !== 'All') {
            if (is_numeric($filterOwner)) {
                $where[] = 'c.owner_id = :owner_id';
                $params[':owner_id'] = (int)$filterOwner;
            } else {
                $where[] = 'u.name = :owner_name';
                $params[':owner_name'] = $filterOwner;
            }
        }

        // Account View Filter
        if ($accountView === 'my') {
            $where[] = 'c.owner_id = :my_user_id';
            $params[':my_user_id'] = $currentUserId;
        } elseif ($accountView === 'key') {
            // Key accounts: annual revenue >= 50M or key tier
            $where[] = 'c.annual_revenue >= 50000000.00';
        } elseif ($accountView === 'high') {
            // High value: pipeline value >= 50,000
            $where[] = '(SELECT COALESCE(SUM(d.value), 0) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = \'open\') >= 50000.00';
        }

        // Deals Range Filter
        if ($filterDealsRange === 'has_deals') {
            $where[] = '(SELECT COUNT(*) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = \'open\') > 0';
        } elseif ($filterDealsRange === 'no_deals') {
            $where[] = '(SELECT COUNT(*) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = \'open\') = 0';
        } elseif ($filterDealsRange === 'multi_deals') {
            $where[] = '(SELECT COUNT(*) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = \'open\') >= 2';
        }

        $whereSql = implode(' AND ', $where);

        // Sorting
        $orderSql = match ($sortBy) {
            'name-desc'    => 'c.name DESC',
            'deals-desc'   => 'open_deals_value DESC, c.name ASC',
            'opps-desc'    => 'open_deals_count DESC, c.name ASC',
            'active-desc'  => 'last_activity_time DESC, c.id DESC',
            'created-desc' => 'c.created_at DESC',
            default        => 'c.name ASC',
        };

        // Total matching count
        $countQuery = "SELECT COUNT(*) FROM companies c 
                       LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = c.organization_id
                       WHERE {$whereSql}";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalCompanies = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($totalCompanies / $pageSize));
        $offset = ($page - 1) * $pageSize;

        // Query records
        $dataQuery = "
            SELECT 
                c.id,
                c.organization_id,
                c.company_code,
                c.name,
                c.legal_name,
                c.domain,
                c.industry,
                c.company_size,
                c.annual_revenue,
                c.location,
                c.founded_year,
                c.linkedin,
                c.tax_registration_number,
                c.relationship,
                c.owner_id,
                c.primary_contact_id,
                c.source,
                c.is_active,
                c.created_at,
                c.updated_at,
                u.name AS owner_name,
                u.email AS owner_email,
                cnt.name AS primary_contact_name,
                cnt.job_title AS primary_contact_title,
                cnt.phone AS primary_contact_phone,
                cnt.email AS primary_contact_email,
                (SELECT COUNT(*) FROM contact_companies cc JOIN contacts co ON co.id = cc.contact_id WHERE cc.company_id = c.id AND cc.organization_id = c.organization_id) AS contacts_count,
                (SELECT COUNT(*) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = 'open') AS open_deals_count,
                (SELECT COALESCE(SUM(d.value), 0.00) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = 'open') AS open_deals_value,
                COALESCE((SELECT act.created_at FROM team_activities act WHERE act.organization_id = c.organization_id AND act.related_entity = 'companies' AND act.related_entity_id = c.id ORDER BY act.created_at DESC LIMIT 1), c.created_at) AS last_activity_time,
                COALESCE((SELECT act.activity_type FROM team_activities act WHERE act.organization_id = c.organization_id AND act.related_entity = 'companies' AND act.related_entity_id = c.id ORDER BY act.created_at DESC LIMIT 1), 'company_created') AS last_activity_type
            FROM companies c
            LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = c.organization_id
            LEFT JOIN contacts cnt ON cnt.id = c.primary_contact_id AND cnt.organization_id = c.organization_id
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$pageSize} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($dataQuery);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $companies = [];
        foreach ($rows as $row) {
            $cName = $row['name'];
            $owner = $row['owner_name'] ?: 'Unassigned';
            $companies[] = [
                'id'                  => (int)$row['id'],
                'company_code'        => $row['company_code'] ?: ('COMP-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT)),
                'name'                => $cName,
                'legal_name'          => $row['legal_name'] ?: $cName,
                'domain'              => $row['domain'] ?: '',
                'logoInitials'        => company_initials($cName),
                'logoColor'           => company_color($cName),
                'industry'            => $row['industry'] ?: '',
                'size'                => $row['company_size'] ?: '',
                'annualRevenue'       => $row['annual_revenue'] !== null ? '$' . number_format((float)$row['annual_revenue'], 1) . 'M' : '',
                'annualRevenueRaw'    => (float)($row['annual_revenue'] ?? 0),
                'relationship'        => $row['relationship'] ?: '',
                'location'            => $row['location'] ?: '',
                'primaryContactName'  => $row['primary_contact_name'] ?: '',
                'primaryContactTitle' => $row['primary_contact_title'] ?: '',
                'primaryContactAvatar'=> company_initials($row['primary_contact_name']),
                'primaryContactPhone' => $row['primary_contact_phone'] ?: '',
                'primaryContactEmail' => $row['primary_contact_email'] ?: '',
                'owner'               => $owner,
                'ownerId'             => $row['owner_id'] ? (int)$row['owner_id'] : null,
                'ownerInitials'       => company_initials($owner),
                'ownerColor'          => company_color($owner),
                'contactsCount'       => (int)$row['contacts_count'],
                'openDealsCount'      => (int)$row['open_deals_count'],
                'openDealsValue'      => (float)$row['open_deals_value'],
                'lastActivity'        => company_time_ago($row['last_activity_time']),
                'lastActivityType'    => $row['last_activity_type'] ?: 'Activity',
                'foundedYear'         => $row['founded_year'] ? (int)$row['founded_year'] : null,
                'linkedin'            => $row['linkedin'] ?: '',
                'taxRegistrationNumber' => $row['tax_registration_number'] ?: '',
                'tax_registration_number' => $row['tax_registration_number'] ?: '',
                'source'              => $row['source'] ?: 'Manual Entry',
                'isActive'            => (bool)$row['is_active'],
                'createdAt'           => date('M Y', strtotime($row['created_at']))
            ];
        }

        // Calculate dynamic KPIs strictly from MySQL
        $kpiStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_companies,
                SUM(CASE WHEN relationship = 'Customer' THEN 1 ELSE 0 END) AS customers,
                SUM(CASE WHEN relationship = 'Prospect' THEN 1 ELSE 0 END) AS prospects,
                SUM(CASE WHEN relationship = 'Partner' THEN 1 ELSE 0 END) AS partners,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive
            FROM companies
            WHERE organization_id = :org_id
        ");
        $kpiStmt->execute([':org_id' => $organizationId]);
        $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // With open deals count
        $withDealsStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT c.id) 
            FROM companies c 
            JOIN deals d ON d.company = c.name AND d.organization_id = c.organization_id 
            WHERE c.organization_id = :org_id AND d.status = 'open'
        ");
        $withDealsStmt->execute([':org_id' => $organizationId]);
        $withOpenDeals = (int)$withDealsStmt->fetchColumn();

        // Active owners list
        $ownersStmt = $pdo->prepare("
            SELECT id, name, email 
            FROM users 
            WHERE organization_id = :org_id AND status = 'active' 
            ORDER BY name ASC
        ");
        $ownersStmt->execute([':org_id' => $organizationId]);
        $owners = $ownersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Active contacts list (for primary contact select dropdowns)
        $cntStmt = $pdo->prepare("
            SELECT id, name, job_title, email, phone, company_name 
            FROM contacts 
            WHERE organization_id = :org_id AND is_active = 1 
            ORDER BY name ASC
        ");
        $cntStmt->execute([':org_id' => $organizationId]);
        $contactsList = $cntStmt->fetchAll(PDO::FETCH_ASSOC);

        // Range info
        $rangeStart = $totalCompanies === 0 ? 0 : $offset + 1;
        $rangeEnd = min($offset + $pageSize, $totalCompanies);

        companies_json(true, 'Companies fetched successfully.', [
            'companies' => $companies,
            'kpis' => [
                'total'     => (int)($kpis['total'] ?? 0),
                'new'       => (int)($kpis['new_companies'] ?? 0),
                'openDeals' => $withOpenDeals,
                'customers' => (int)($kpis['customers'] ?? 0),
                'prospects' => (int)($kpis['prospects'] ?? 0),
                'partners'  => (int)($kpis['partners'] ?? 0),
                'inactive'  => (int)($kpis['inactive'] ?? 0)
            ],
            'owners'   => $owners,
            'contacts' => $contactsList,
            'pagination' => [
                'total'      => $totalCompanies,
                'page'       => $page,
                'pageSize'   => $pageSize,
                'totalPages' => $totalPages,
                'rangeStart' => $rangeStart,
                'rangeEnd'   => $rangeEnd
            ]
        ]);
        break;

    // -------------------------------------------------------------------------
    // 1b. SEARCH COMPANIES (AUTOCOMPLETE)
    // -------------------------------------------------------------------------
    case 'search':
        require_company_perm('view');
        $query = trim($request['q'] ?? ($request['query'] ?? ($_GET['q'] ?? '')));
        if (mb_strlen($query) < 2) {
            companies_json(true, 'Query too short.', ['companies' => []]);
        }

        $term = '%' . $query . '%';
        $searchStmt = $pdo->prepare("
            SELECT id, name, company_code, relationship, location
            FROM companies 
            WHERE organization_id = :org_id 
              AND is_active = 1 
              AND name LIKE :term 
            ORDER BY 
              CASE WHEN LOWER(TRIM(name)) = LOWER(TRIM(:exact)) THEN 0 
                   WHEN LOWER(name) LIKE LOWER(:prefix) THEN 1 
                   ELSE 2 END,
              name ASC 
            LIMIT 15
        ");
        $searchStmt->execute([
            ':org_id' => $organizationId,
            ':term'   => $term,
            ':exact'  => $query,
            ':prefix' => $query . '%'
        ]);
        $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);

        companies_json(true, 'Search completed.', ['companies' => $searchResults]);
        break;

    // -------------------------------------------------------------------------
    // 1c. SEARCH CONTACTS BY PHONE (COMPANY ADD/EDIT WORKFLOW)
    // -------------------------------------------------------------------------
    case 'search_contacts_by_phone':
        require_company_perm('view');
        $rawQuery = trim($request['q'] ?? ($request['query'] ?? ($request['phone'] ?? ($_GET['q'] ?? ($_GET['query'] ?? ($_GET['phone'] ?? ''))))));
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawQuery);

        if (strlen($cleanPhone) < 3) {
            companies_json(true, 'Minimum 3 digits required.', ['contacts' => []]);
        }

        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.name, 
                c.phone, 
                c.clean_phone,
                c.email,
                c.job_title,
                c.company_name
            FROM contacts c
            WHERE c.organization_id = :org_id 
              AND c.clean_phone LIKE :phone_term
              AND c.is_active = 1
            ORDER BY c.name ASC
            LIMIT 20
        ");
        $stmt->execute([
            ':org_id'     => $organizationId,
            ':phone_term' => '%' . $cleanPhone . '%'
        ]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch associated company names from contact_companies for context
        $contactsList = [];
        foreach ($matches as $cnt) {
            $compStmt = $pdo->prepare("
                SELECT comp.id, comp.name 
                FROM contact_companies cc
                JOIN companies comp ON comp.id = cc.company_id AND comp.organization_id = cc.organization_id
                WHERE cc.contact_id = :cid AND cc.organization_id = :org_id
                ORDER BY comp.name ASC
            ");
            $compStmt->execute([':cid' => $cnt['id'], ':org_id' => $organizationId]);
            $compRows = $compStmt->fetchAll(PDO::FETCH_ASSOC);
            $compNames = array_column($compRows, 'name');

            // Fallback to legacy company_name if junction row not yet populated
            if (empty($compNames) && !empty($cnt['company_name'])) {
                $compNames[] = $cnt['company_name'];
            }

            $contactsList[] = [
                'id'          => (int)$cnt['id'],
                'name'        => $cnt['name'],
                'phone'       => $cnt['phone'],
                'clean_phone' => $cnt['clean_phone'],
                'email'       => $cnt['email'],
                'job_title'   => $cnt['job_title'],
                'companies'   => $compNames
            ];
        }

        companies_json(true, 'Contacts found.', ['contacts' => $contactsList]);
        break;

    // -------------------------------------------------------------------------
    // 2. GET SINGLE COMPANY
    // -------------------------------------------------------------------------
    case 'get':
        require_company_perm('view');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        $stmt = $pdo->prepare("
            SELECT c.*, u.name AS owner_name, cnt.name AS primary_contact_name, cnt.job_title AS primary_contact_title
            FROM companies c
            LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = c.organization_id
            LEFT JOIN contacts cnt ON cnt.id = c.primary_contact_id AND cnt.organization_id = c.organization_id
            WHERE c.id = :id AND c.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            companies_json(false, 'Company not found.', [], 404);
        }

        companies_json(true, 'Company fetched successfully.', ['company' => $company]);
        break;

    // -------------------------------------------------------------------------
    // 3. CREATE COMPANY (ATOMIC TRANSACTION)
    // -------------------------------------------------------------------------
    case 'create':
        require_company_perm('create');

        $name = trim($request['name'] ?? '');
        if ($name === '') {
            companies_json(false, 'Company name is required.', [], 422);
        }

        $pdo->beginTransaction();
        try {
            // Normalized exact-name duplicate check within current organization
            $dupStmt = $pdo->prepare("SELECT id, name, company_code FROM companies WHERE organization_id = :org_id AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
            $dupStmt->execute([':org_id' => $organizationId, ':name' => $name]);
            $existingComp = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingComp) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                companies_json(true, 'Company already exists.', [
                    'id'           => (int)$existingComp['id'],
                    'name'         => $existingComp['name'],
                    'company_code' => $existingComp['company_code'],
                    'is_existing'  => true
                ]);
            }

            $legalName = trim($request['legal_name'] ?? '');
            $domain = trim($request['domain'] ?? '');
            $industry = trim($request['industry'] ?? '');
            $companySize = trim($request['company_size'] ?? '');
            $annualRevenueRaw = trim($request['annual_revenue'] ?? '');
            $annualRevenue = null;
            if ($annualRevenueRaw !== '') {
                $cleanRev = preg_replace('/[^0-9.]/', '', $annualRevenueRaw);
                if ($cleanRev !== '') $annualRevenue = (float)$cleanRev;
            }

            $location = trim($request['location'] ?? '');
            $foundedYear = !empty($request['founded_year']) ? (int)$request['founded_year'] : null;
            $linkedin = trim($request['linkedin'] ?? '');
            $taxRegistrationNumber = trim($request['tax_registration_number'] ?? '');
            $relationship = trim($request['relationship'] ?? '');
            $source = trim($request['source'] ?? '');

            // Validate owner_id
            $ownerId = !empty($request['owner_id']) ? (int)$request['owner_id'] : null;
            if ($ownerId !== null) {
                $chkOwner = $pdo->prepare("SELECT id FROM users WHERE id = :uid AND organization_id = :org_id AND status = 'active' LIMIT 1");
                $chkOwner->execute([':uid' => $ownerId, ':org_id' => $organizationId]);
                if (!$chkOwner->fetchColumn()) {
                    $ownerId = null;
                }
            }

            // Generate next company code
            $seqStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM companies WHERE organization_id = :org_id");
            $seqStmt->execute([':org_id' => $organizationId]);
            $nextId = (int)$seqStmt->fetchColumn();
            $companyCode = 'COMP-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            $insertSql = "
                INSERT INTO companies (
                    organization_id, company_code, name, legal_name, domain, industry,
                    company_size, annual_revenue, location, founded_year, linkedin, tax_registration_number,
                    relationship, owner_id, primary_contact_id, source, is_active,
                    created_by, created_at
                ) VALUES (
                    :org_id, :code, :name, :legal_name, :domain, :industry,
                    :company_size, :annual_revenue, :location, :founded_year, :linkedin, :tax_registration_number,
                    :relationship, :owner_id, NULL, :source, 1,
                    :created_by, NOW()
                )
            ";

            $insStmt = $pdo->prepare($insertSql);
            $insStmt->execute([
                ':org_id'                  => $organizationId,
                ':code'                    => $companyCode,
                ':name'                    => $name,
                ':legal_name'              => $legalName ?: null,
                ':domain'                  => $domain ?: null,
                ':industry'                => $industry ?: null,
                ':company_size'            => $companySize ?: null,
                ':annual_revenue'          => $annualRevenue,
                ':location'                => $location ?: null,
                ':founded_year'            => $foundedYear,
                ':linkedin'                => $linkedin ?: null,
                ':tax_registration_number' => $taxRegistrationNumber ?: null,
                ':relationship'            => $relationship ?: null,
                ':owner_id'                => $ownerId,
                ':source'                  => $source ?: null,
                ':created_by'              => $currentUserId
            ]);

            $newCompanyId = (int)$pdo->lastInsertId();
            $resolvedPrimaryContactId = null;

            // Handle Primary Contact selection or creation
            $primaryContactId = !empty($request['primary_contact_id']) ? (int)$request['primary_contact_id'] : null;
            if ($primaryContactId !== null && $primaryContactId > 0) {
                // CASE A: Existing contact selected
                $chkContact = $pdo->prepare("SELECT id FROM contacts WHERE id = :cid AND organization_id = :org_id AND is_active = 1 LIMIT 1");
                $chkContact->execute([':cid' => $primaryContactId, ':org_id' => $organizationId]);
                if ($chkContact->fetchColumn()) {
                    $resolvedPrimaryContactId = $primaryContactId;
                    // Link to contact_companies (DO NOT overwrite contacts.company_id!)
                    $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (?, ?, ?)")
                        ->execute([$organizationId, $resolvedPrimaryContactId, $newCompanyId]);
                }
            } elseif (!empty($request['new_contact_name'])) {
                // CASE B: New contact entered
                $newContactName = trim($request['new_contact_name']);
                $newContactPhone = trim($request['new_contact_phone'] ?? '');
                $newCleanPhone = preg_replace('/[^0-9]/', '', $newContactPhone);
                $newContactEmail = trim($request['new_contact_email'] ?? '');

                $nameParts = explode(' ', $newContactName, 2);
                $first = $nameParts[0];
                $last = isset($nameParts[1]) ? $nameParts[1] : '';

                $cntStmt = $pdo->prepare("
                    INSERT INTO contacts (
                        organization_id, first_name, last_name, name, company_id, company_name,
                        email, phone, clean_phone, relationship, owner_id, is_active, created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, 'Customer', ?, 1, ?, NOW()
                    )
                ");
                $cntStmt->execute([
                    $organizationId,
                    $first ?: null,
                    $last ?: null,
                    $newContactName,
                    $newCompanyId, // legacy fallback for newly created contacts
                    $name,
                    $newContactEmail !== '' ? $newContactEmail : null,
                    $newContactPhone !== '' ? $newContactPhone : null,
                    $newCleanPhone !== '' ? $newCleanPhone : null,
                    $ownerId,
                    $currentUserId
                ]);
                $resolvedPrimaryContactId = (int)$pdo->lastInsertId();
                $cntCode = 'CNT-' . str_pad($resolvedPrimaryContactId, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE contacts SET contact_code = ? WHERE id = ?")->execute([$cntCode, $resolvedPrimaryContactId]);

                // Link to contact_companies
                $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (?, ?, ?)")
                    ->execute([$organizationId, $resolvedPrimaryContactId, $newCompanyId]);
            }

            // Assign primary_contact_id if resolved
            if ($resolvedPrimaryContactId !== null) {
                $pdo->prepare("UPDATE companies SET primary_contact_id = ? WHERE id = ? AND organization_id = ?")
                    ->execute([$resolvedPrimaryContactId, $newCompanyId, $organizationId]);
            }

            // Log creation activity
            $actStmt = $pdo->prepare("
                INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
                VALUES (:org_id, :uid, 'company_created', :title, :desc, 'companies', :eid, :cb, NOW())
            ");
            $actStmt->execute([
                ':org_id' => $organizationId,
                ':uid'    => $currentUserId,
                ':title'  => 'Company created: ' . $name,
                ':desc'   => "Company {$name} ({$companyCode}) created by " . ($currentUser['name'] ?? 'User'),
                ':eid'    => $newCompanyId,
                ':cb'     => $currentUserId
            ]);

            $pdo->commit();
            companies_json(true, 'Company created successfully.', ['id' => $newCompanyId, 'company_code' => $companyCode]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            companies_json(false, 'Failed to create company: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // 4. UPDATE COMPANY
    // -------------------------------------------------------------------------
    case 'update':
        require_company_perm('edit');

        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        // Verify company exists in this tenant
        $chkStmt = $pdo->prepare("SELECT * FROM companies WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chkStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            companies_json(false, 'Company not found or access denied.', [], 404);
        }

        $name = trim($request['name'] ?? $existing['name']);
        if ($name === '') {
            companies_json(false, 'Company name is required.', [], 422);
        }

        // Duplicate name check within organization if name changed
        if (strcasecmp($name, $existing['name']) !== 0) {
            $dupStmt = $pdo->prepare("SELECT id FROM companies WHERE organization_id = :org_id AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) AND id != :id LIMIT 1");
            $dupStmt->execute([':org_id' => $organizationId, ':name' => $name, ':id' => $id]);
            if ($dupStmt->fetchColumn()) {
                companies_json(false, 'A company with this name already exists in your organization.', [], 409);
            }
        }

        $legalName = isset($request['legal_name']) ? trim($request['legal_name']) : $existing['legal_name'];
        $domain = isset($request['domain']) ? trim($request['domain']) : $existing['domain'];
        $industry = isset($request['industry']) ? trim($request['industry']) : $existing['industry'];
        $companySize = isset($request['company_size']) ? trim($request['company_size']) : $existing['company_size'];

        $annualRevenue = $existing['annual_revenue'];
        if (isset($request['annual_revenue'])) {
            $rawRev = trim($request['annual_revenue']);
            if ($rawRev === '') {
                $annualRevenue = null;
            } else {
                $cleanRev = preg_replace('/[^0-9.]/', '', $rawRev);
                $annualRevenue = $cleanRev !== '' ? (float)$cleanRev : null;
            }
        }

        $location = isset($request['location']) ? trim($request['location']) : $existing['location'];
        $foundedYear = isset($request['founded_year']) ? (!empty($request['founded_year']) ? (int)$request['founded_year'] : null) : $existing['founded_year'];
        $linkedin = isset($request['linkedin']) ? trim($request['linkedin']) : $existing['linkedin'];
        $taxRegistrationNumber = isset($request['tax_registration_number']) ? trim($request['tax_registration_number']) : ($existing['tax_registration_number'] ?? null);
        $relationship = isset($request['relationship']) ? trim($request['relationship']) : $existing['relationship'];
        $source = isset($request['source']) ? trim($request['source']) : $existing['source'];

        // Validate owner_id (Company Owner belongs to company; independent from Contact Owner)
        $ownerId = $existing['owner_id'];
        if (isset($request['owner_id'])) {
            $reqOwner = !empty($request['owner_id']) ? (int)$request['owner_id'] : null;
            if ($reqOwner !== null) {
                $chkOwner = $pdo->prepare("SELECT id FROM users WHERE id = :uid AND organization_id = :org_id AND status = 'active' LIMIT 1");
                $chkOwner->execute([':uid' => $reqOwner, ':org_id' => $organizationId]);
                $ownerId = $chkOwner->fetchColumn() ? $reqOwner : null;
            } else {
                $ownerId = null;
            }
        }

        $pdo->beginTransaction();
        try {
            $primaryContactId = $existing['primary_contact_id'];
            $createdContactId = null;

            if (!empty($request['primary_contact_id'])) {
                // CASE 1: Existing contact selected
                $reqContact = (int)$request['primary_contact_id'];
                $chkContact = $pdo->prepare("
                    SELECT id FROM contacts 
                    WHERE id = :cid AND organization_id = :org_id AND is_active = 1
                    LIMIT 1
                ");
                $chkContact->execute([
                    ':cid'     => $reqContact,
                    ':org_id'  => $organizationId
                ]);
                $validContact = $chkContact->fetchColumn();
                if ($validContact) {
                    $primaryContactId = $reqContact;
                    // Ensure relationship exists in authoritative contact_companies (DO NOT overwrite contacts.company_id!)
                    $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (:org_id, :cid, :comp_id)")
                        ->execute([':org_id' => $organizationId, ':cid' => $primaryContactId, ':comp_id' => $id]);
                } else {
                    $pdo->rollBack();
                    companies_json(false, 'Selected primary contact not found or access denied.', [], 422);
                }
            } elseif (!empty($request['new_contact_name'])) {
                // CASE 2: New contact entered in Edit Company form
                $newContactName = trim($request['new_contact_name']);
                $newContactPhone = trim($request['new_contact_phone'] ?? '');
                $newCleanPhone = preg_replace('/[^0-9]/', '', $newContactPhone);
                $newContactEmail = trim($request['new_contact_email'] ?? '');

                if ($newContactEmail !== '' && !filter_var($newContactEmail, FILTER_VALIDATE_EMAIL)) {
                    $pdo->rollBack();
                    companies_json(false, 'Invalid contact email address.', [], 422);
                }

                $nameParts = explode(' ', $newContactName, 2);
                $first = $nameParts[0] !== '' ? $nameParts[0] : null;
                $last = isset($nameParts[1]) && $nameParts[1] !== '' ? $nameParts[1] : null;

                // Contact rules:
                // - organization_id = authenticated user's organization
                // - owner_id = NULL (Company Owner and Contact Owner are completely separate)
                // - relationship = NULL (Company Relationship and Contact Relationship are separate)
                // - is_active = 1
                // - created_by = authenticated user
                $cntStmt = $pdo->prepare("
                    INSERT INTO contacts (
                        organization_id, first_name, last_name, name, company_id, company_name,
                        email, phone, clean_phone, relationship, owner_id, is_active, created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, NULL, NULL, 1, ?, NOW()
                    )
                ");
                $cntStmt->execute([
                    $organizationId,
                    $first,
                    $last,
                    $newContactName,
                    $id, // legacy fallback for newly created contacts
                    $name,
                    $newContactEmail !== '' ? $newContactEmail : null,
                    $newContactPhone !== '' ? $newContactPhone : null,
                    $newCleanPhone !== '' ? $newCleanPhone : null,
                    $currentUserId
                ]);

                $createdContactId = (int)$pdo->lastInsertId();
                $cntCode = 'CNT-' . str_pad($createdContactId, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE contacts SET contact_code = ? WHERE id = ? AND organization_id = ?")
                    ->execute([$cntCode, $createdContactId, $organizationId]);

                // Authoritative junction table: contact_companies
                $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (?, ?, ?)")
                    ->execute([$organizationId, $createdContactId, $id]);

                // Primary contact for THIS company
                $primaryContactId = $createdContactId;
            } elseif (array_key_exists('primary_contact_id', $request) && empty($request['primary_contact_id'])) {
                // CASE 3: Explicitly cleared
                $primaryContactId = null;
            }

            // If company name was updated, keep contacts company_name in sync for this company
            if ($name !== $existing['name']) {
                $pdo->prepare("UPDATE contacts SET company_name = :cname WHERE company_id = :comp_id AND organization_id = :org_id")
                    ->execute([':cname' => $name, ':comp_id' => $id, ':org_id' => $organizationId]);
            }

            $updSql = "
                UPDATE companies SET
                    name = :name,
                    legal_name = :legal_name,
                    domain = :domain,
                    industry = :industry,
                    company_size = :company_size,
                    annual_revenue = :annual_revenue,
                    location = :location,
                    founded_year = :founded_year,
                    linkedin = :linkedin,
                    tax_registration_number = :tax_registration_number,
                    relationship = :relationship,
                    owner_id = :owner_id,
                    primary_contact_id = :primary_contact_id,
                    source = :source,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id
            ";

            $updStmt = $pdo->prepare($updSql);
            $updStmt->execute([
                ':name'                    => $name,
                ':legal_name'              => $legalName ?: null,
                ':domain'                  => $domain ?: null,
                ':industry'                => $industry ?: null,
                ':company_size'            => $companySize ?: null,
                ':annual_revenue'          => $annualRevenue,
                ':location'                => $location ?: null,
                ':founded_year'            => $foundedYear,
                ':linkedin'                => $linkedin ?: null,
                ':tax_registration_number' => $taxRegistrationNumber ?: null,
                ':relationship'            => $relationship ?: null,
                ':owner_id'                => $ownerId,
                ':primary_contact_id'      => $primaryContactId,
                ':source'                  => $source ?: null,
                ':id'                      => $id,
                ':org_id'                  => $organizationId
            ]);

            // If company name changed, optionally sync deals and contacts matching old name
            if ($existing['name'] !== $name) {
                $pdo->prepare("UPDATE contacts SET company_name = :new_name WHERE organization_id = :org_id AND company_name = :old_name")
                    ->execute([':new_name' => $name, ':org_id' => $organizationId, ':old_name' => $existing['name']]);
                $pdo->prepare("UPDATE deals SET company = :new_name WHERE organization_id = :org_id AND company = :old_name")
                    ->execute([':new_name' => $name, ':org_id' => $organizationId, ':old_name' => $existing['name']]);
            }

            // Log activity for new contact creation if created
            if ($createdContactId !== null) {
                $cntActStmt = $pdo->prepare("
                    INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
                    VALUES (:org_id, :uid, 'contact_created', :title, :desc, 'contacts', :eid, :cb, NOW())
                ");
                $cntActStmt->execute([
                    ':org_id' => $organizationId,
                    ':uid'    => $currentUserId,
                    ':title'  => 'Contact created: ' . $newContactName,
                    ':desc'   => "Contact {$newContactName} created for company {$name}",
                    ':eid'    => $createdContactId,
                    ':cb'     => $currentUserId
                ]);
            }

            // Log company update activity
            $actStmt = $pdo->prepare("
                INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
                VALUES (:org_id, :uid, 'company_updated', :title, :desc, 'companies', :eid, :cb, NOW())
            ");
            $actStmt->execute([
                ':org_id' => $organizationId,
                ':uid'    => $currentUserId,
                ':title'  => 'Company updated: ' . $name,
                ':desc'   => "Company details for {$name} updated by " . ($currentUser['name'] ?? 'User'),
                ':eid'    => $id,
                ':cb'     => $currentUserId
            ]);

            $pdo->commit();
            companies_json(true, 'Company updated successfully.', [
                'id' => $id,
                'primary_contact_id' => $primaryContactId,
                'new_contact_id' => $createdContactId
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            companies_json(false, 'Failed to update company: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // 5. TOGGLE / SET STATUS (ACTIVE / INACTIVE)
    // -------------------------------------------------------------------------
    case 'toggle_status':
    case 'set_status':
    case 'archive':
        require_company_perm('edit');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        // Determine target active status:
        // If 'is_active' is explicitly passed, use it. Otherwise toggle current value.
        if (isset($request['is_active'])) {
            $targetActive = (int)$request['is_active'] ? 1 : 0;
        } else {
            $currStmt = $pdo->prepare("SELECT is_active FROM companies WHERE id = :id AND organization_id = :org_id");
            $currStmt->execute([':id' => $id, ':org_id' => $organizationId]);
            $currentVal = $currStmt->fetchColumn();
            if ($currentVal === false) {
                companies_json(false, 'Company not found.', [], 404);
            }
            $targetActive = ((int)$currentVal === 1) ? 0 : 1;
        }

        $stmt = $pdo->prepare("UPDATE companies SET is_active = :act, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([
            ':act'    => $targetActive,
            ':id'     => $id,
            ':org_id' => $organizationId
        ]);

        $chk = $pdo->prepare("SELECT id FROM companies WHERE id = :id AND organization_id = :org_id");
        $chk->execute([':id' => $id, ':org_id' => $organizationId]);
        if (!$chk->fetchColumn()) {
            companies_json(false, 'Company not found.', [], 404);
        }

        $msg = $targetActive === 1 ? 'Company marked as active.' : 'Company marked as inactive.';
        companies_json(true, $msg, ['id' => $id, 'is_active' => $targetActive]);
        break;

    // -------------------------------------------------------------------------
    // 6. DELETE COMPANY (Transactional Cascade Cleanup)
    // -------------------------------------------------------------------------
    case 'delete':
        require_company_perm('delete');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        // Verify company exists
        $chkStmt = $pdo->prepare("SELECT name FROM companies WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chkStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $compName = $chkStmt->fetchColumn();
        if (!$compName) {
            companies_json(false, 'Company not found or access denied.', [], 404);
        }

        $pdo->beginTransaction();
        try {
            // Delete polymorphic tasks
            $pdo->prepare("DELETE FROM tasks WHERE organization_id = :org_id AND related_type = 'companies' AND related_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete polymorphic notes
            $pdo->prepare("DELETE FROM team_notes WHERE organization_id = :org_id AND related_type = 'companies' AND related_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete polymorphic activities
            $pdo->prepare("DELETE FROM team_activities WHERE organization_id = :org_id AND related_entity = 'companies' AND related_entity_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete polymorphic calendar events
            $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = :org_id AND related_type = 'companies' AND related_id = :id")
                ->execute([':org_id' => $organizationId, ':id' => $id]);

            // Delete custom field values
            $pdo->prepare("DELETE FROM crm_custom_field_values WHERE entity_type = 'Companies' AND entity_id = :id")
                ->execute([':id' => $id]);

            // Clear legacy contact company pointer without deleting contacts
            $pdo->prepare("UPDATE contacts SET company_id = NULL, company_name = NULL WHERE company_id = :id AND organization_id = :org_id")
                ->execute([':id' => $id, ':org_id' => $organizationId]);

            // Delete company record
            $pdo->prepare("DELETE FROM companies WHERE id = :id AND organization_id = :org_id")
                ->execute([':id' => $id, ':org_id' => $organizationId]);

            $pdo->commit();
            companies_json(true, "Company '{$compName}' deleted successfully.", ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            companies_json(false, 'Failed to delete company: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // 7. BULK ACTIONS
    // -------------------------------------------------------------------------
    case 'bulk_action':
        $bulkAction = trim($request['bulk_action'] ?? '');
        $companyIds = $request['company_ids'] ?? [];
        if (!is_array($companyIds) || empty($companyIds)) {
            companies_json(false, 'No companies selected.', [], 400);
        }

        $sanitizedIds = array_filter(array_map('intval', $companyIds), fn($val) => $val > 0);
        if (empty($sanitizedIds)) {
            companies_json(false, 'No valid company IDs provided.', [], 400);
        }
        $inPlaceholders = implode(',', array_fill(0, count($sanitizedIds), '?'));

        if ($bulkAction === 'delete') {
            require_company_perm('delete');
            $pdo->beginTransaction();
            try {
                // Polymorphic cleanup
                $pdo->prepare("DELETE FROM tasks WHERE organization_id = ? AND related_type = 'companies' AND related_id IN ($inPlaceholders)")
                    ->execute(array_merge([$organizationId], $sanitizedIds));

                $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'companies' AND related_id IN ($inPlaceholders)")
                    ->execute(array_merge([$organizationId], $sanitizedIds));

                $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'companies' AND related_entity_id IN ($inPlaceholders)")
                    ->execute(array_merge([$organizationId], $sanitizedIds));

                $pdo->prepare("DELETE FROM calendar_events WHERE organization_id = ? AND related_type = 'companies' AND related_id IN ($inPlaceholders)")
                    ->execute(array_merge([$organizationId], $sanitizedIds));

                $pdo->prepare("DELETE FROM crm_custom_field_values WHERE entity_type = 'Companies' AND entity_id IN ($inPlaceholders)")
                    ->execute($sanitizedIds);

                $pdo->prepare("DELETE FROM companies WHERE organization_id = ? AND id IN ($inPlaceholders)")
                    ->execute(array_merge([$organizationId], $sanitizedIds));

                $pdo->commit();
                companies_json(true, count($sanitizedIds) . ' companies deleted successfully.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                companies_json(false, 'Bulk delete failed: ' . $e->getMessage(), [], 500);
            }
        } elseif ($bulkAction === 'archive') {
            require_company_perm('edit');
            $stmt = $pdo->prepare("UPDATE companies SET is_active = 0, updated_at = NOW() WHERE organization_id = ? AND id IN ($inPlaceholders)");
            $stmt->execute(array_merge([$organizationId], $sanitizedIds));
            companies_json(true, count($sanitizedIds) . ' companies archived successfully.');
        } elseif ($bulkAction === 'owner') {
            require_company_perm('edit');
            $newOwnerId = !empty($request['owner_id']) ? (int)$request['owner_id'] : null;
            if ($newOwnerId !== null) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
                $chk->execute([$newOwnerId, $organizationId]);
                if (!$chk->fetchColumn()) {
                    companies_json(false, 'Selected owner is not active in this organization.', [], 422);
                }
            }
            $stmt = $pdo->prepare("UPDATE companies SET owner_id = ?, updated_at = NOW() WHERE organization_id = ? AND id IN ($inPlaceholders)");
            $stmt->execute(array_merge([$newOwnerId, $organizationId], $sanitizedIds));
            companies_json(true, count($sanitizedIds) . ' companies reassigned successfully.');
        } elseif ($bulkAction === 'relationship') {
            require_company_perm('edit');
            $newRel = trim($request['relationship'] ?? 'Prospect');
            $stmt = $pdo->prepare("UPDATE companies SET relationship = ?, updated_at = NOW() WHERE organization_id = ? AND id IN ($inPlaceholders)");
            $stmt->execute(array_merge([$newRel, $organizationId], $sanitizedIds));
            companies_json(true, count($sanitizedIds) . ' companies updated to ' . $newRel . '.');
        } else {
            companies_json(false, 'Unknown bulk action: ' . $bulkAction, [], 400);
        }
        break;

    // -------------------------------------------------------------------------
    // 7b. COMPANY CONTACTS (FOR PRIMARY CONTACT SELECTION)
    // -------------------------------------------------------------------------
    case 'company_contacts':
        require_company_perm('view');
        $compTargetId = (int)($request['company_id'] ?? ($request['id'] ?? 0));
        if ($compTargetId <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        $chkCompStmt = $pdo->prepare("SELECT name FROM companies WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chkCompStmt->execute([':id' => $compTargetId, ':org_id' => $organizationId]);
        $targetCompName = $chkCompStmt->fetchColumn();
        if (!$targetCompName) {
            companies_json(false, 'Company not found.', [], 404);
        }

        $cContactsStmt = $pdo->prepare("
            SELECT co.id, co.name, co.job_title, co.email, co.phone 
            FROM contact_companies cc
            JOIN contacts co ON co.id = cc.contact_id AND co.organization_id = cc.organization_id
            WHERE cc.company_id = :cid AND cc.organization_id = :org_id 
              AND co.is_active = 1 
            ORDER BY co.name ASC
        ");
        $cContactsStmt->execute([':org_id' => $organizationId, ':cid' => $compTargetId]);
        $compContacts = $cContactsStmt->fetchAll(PDO::FETCH_ASSOC);

        companies_json(true, 'Company contacts fetched.', ['contacts' => $compContacts]);
        break;

    // -------------------------------------------------------------------------
    // 8. DRAWER DATA
    // -------------------------------------------------------------------------
    case 'drawer_data':
        require_company_perm('view');
        $id = (int)($request['id'] ?? 0);
        if ($id <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        // Fetch company
        $compStmt = $pdo->prepare("
            SELECT 
                c.*, 
                u.name AS owner_name, 
                u.email AS owner_email,
                cnt.name AS primary_contact_name, 
                cnt.job_title AS primary_contact_title,
                cnt.phone AS primary_contact_phone,
                cnt.email AS primary_contact_email
            FROM companies c
            LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = c.organization_id
            LEFT JOIN contacts cnt ON cnt.id = c.primary_contact_id AND cnt.organization_id = c.organization_id
            WHERE c.id = :id AND c.organization_id = :org_id
            LIMIT 1
        ");
        $compStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $company = $compStmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            companies_json(false, 'Company not found.', [], 404);
        }

        $cName = $company['name'];

        // Associated Contacts
        $cntStmt = $pdo->prepare("
            SELECT co.id, co.name, co.job_title, co.email, co.phone, co.relationship, co.is_active
            FROM contact_companies cc
            JOIN contacts co ON co.id = cc.contact_id AND co.organization_id = cc.organization_id
            WHERE cc.company_id = :cid AND cc.organization_id = :org_id 
            ORDER BY co.id ASC
        ");
        $cntStmt->execute([':org_id' => $organizationId, ':cid' => $id]);
        $associatedContacts = $cntStmt->fetchAll(PDO::FETCH_ASSOC);

        // Associated Deals
        $dealsStmt = $pdo->prepare("
            SELECT id, name, name AS title, stage, value, probability, close_date, status, assigned_to
            FROM deals 
            WHERE organization_id = :org_id AND company = :cname
            ORDER BY id DESC
        ");
        $dealsStmt->execute([':org_id' => $organizationId, ':cname' => $cName]);
        $associatedDeals = $dealsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Polymorphic Tasks
        $tasksStmt = $pdo->prepare("
            SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.assigned_to, u.name AS assigned_to_name
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to AND u.organization_id = t.organization_id
            WHERE t.organization_id = :org_id AND t.related_type = 'companies' AND t.related_id = :id
            ORDER BY t.due_date ASC, t.id DESC
        ");
        $tasksStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

        // Polymorphic Notes
        $notesStmt = $pdo->prepare("
            SELECT n.id, n.title, n.content, n.is_pinned, n.created_at, n.updated_at, u.name AS author_name
            FROM team_notes n
            LEFT JOIN users u ON u.id = n.created_by AND u.organization_id = n.organization_id
            WHERE n.organization_id = :org_id AND n.related_type = 'companies' AND n.related_id = :id
            ORDER BY n.is_pinned DESC, n.created_at DESC
        ");
        $notesStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Polymorphic Activities
        $actStmt = $pdo->prepare("
            SELECT a.id, a.activity_type, a.title, a.description, a.created_at, u.name AS user_name
            FROM team_activities a
            LEFT JOIN users u ON u.id = a.user_id AND u.organization_id = a.organization_id
            WHERE a.organization_id = :org_id AND a.related_entity = 'companies' AND a.related_entity_id = :id
            ORDER BY a.created_at DESC
        ");
        $actStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);

        // Polymorphic Calendar Events
        $calStmt = $pdo->prepare("
            SELECT id, title, description, event_type, start_time, end_time, status 
            FROM calendar_events 
            WHERE organization_id = :org_id AND related_type = 'companies' AND related_id = :id 
            ORDER BY start_time ASC
        ");
        $calStmt->execute([':org_id' => $organizationId, ':id' => $id]);
        $calendarEvents = $calStmt->fetchAll(PDO::FETCH_ASSOC);

        // Custom fields for Companies
        $cfStmt = $pdo->prepare("
            SELECT f.id, f.field_key, f.field_label, f.field_type, v.field_value
            FROM crm_custom_fields f
            LEFT JOIN crm_custom_field_values v ON v.custom_field_id = f.id AND v.entity_id = :id AND v.entity_type = 'Companies'
            WHERE f.organization_id = :org_id AND f.target_object = 'Companies' AND f.is_active = 1
              AND f.field_key != 'tax_registration_number'
            ORDER BY f.sort_order ASC
        ");
        $cfStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $customFields = $cfStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate performance metrics
        $openDeals = array_filter($associatedDeals, fn($d) => $d['status'] === 'open');
        $wonDeals = array_filter($associatedDeals, fn($d) => $d['status'] === 'won');
        $pipelineValue = array_reduce($openDeals, fn($sum, $d) => $sum + (float)$d['value'], 0.00);
        $wonValue = array_reduce($wonDeals, fn($sum, $d) => $sum + (float)$d['value'], 0.00);

        companies_json(true, 'Drawer data fetched successfully.', [
            'company'          => $company,
            'overview'         => $company,
            'contacts'         => $associatedContacts,
            'deals'            => $associatedDeals,
            'tasks'            => $tasks,
            'notes'            => $notes,
            'activities'       => $activities,
            'calendar_events'  => $calendarEvents,
            'custom_fields'    => $customFields,
            'performance'      => [
                'totalContacts'  => count($associatedContacts),
                'openDeals'      => count($openDeals),
                'pipelineValue'  => $pipelineValue,
                'wonValue'       => $wonValue,
                'active_deals'   => count($openDeals),
                'pipeline_value' => $pipelineValue,
                'total_contacts' => count($associatedContacts)
            ]
        ]);
        break;

    // -------------------------------------------------------------------------
    // 9. SUB-ENTITY: ADD CONTACT
    // -------------------------------------------------------------------------
    case 'add_contact':
        require_company_perm('create');
        $companyId = (int)($request['company_id'] ?? 0);
        if ($companyId <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        $chkStmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = :id AND organization_id = :org_id AND is_active = 1 LIMIT 1");
        $chkStmt->execute([':id' => $companyId, ':org_id' => $organizationId]);
        $compRow = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$compRow) {
            companies_json(false, 'Company not found.', [], 404);
        }
        $cName = $compRow['name'];

        $first = trim($request['first_name'] ?? '');
        $last = trim($request['last_name'] ?? '');
        $name = trim($request['name'] ?? '');
        if ($name === '') {
            $name = trim("{$first} {$last}");
        }
        if ($first === '' || $last === '' || $name === '') {
            companies_json(false, 'First name and last name are required.', [], 422);
        }

        $email = trim($request['email'] ?? '');
        if ($email === '') {
            companies_json(false, 'Email address is required.', [], 422);
        }

        $jobTitle = trim($request['job_title'] ?? ($request['job'] ?? ''));
        $phone = trim($request['phone'] ?? '');
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $relationship = trim($request['relationship'] ?? 'Customer');
        $ownerId = !empty($request['owner_id']) ? (int)$request['owner_id'] : null;
        if ($ownerId) {
            $chkOwner = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
            $chkOwner->execute([$ownerId, $organizationId]);
            if (!$chkOwner->fetchColumn()) {
                $ownerId = null;
            }
        }
        $source = trim($request['source'] ?? '');
        $location = trim($request['location'] ?? '');
        $preferredChannel = trim($request['preferred_channel'] ?? ($request['preferredChannel'] ?? ''));
        $linkedin = trim($request['linkedin'] ?? '');
        $isPrimary = !empty($request['is_primary']);

        $pdo->beginTransaction();
        try {
            $insCnt = $pdo->prepare("
                INSERT INTO contacts (
                    organization_id, first_name, last_name, name, company_id, company_name, job_title,
                    email, phone, clean_phone, whatsapp, preferred_channel, linkedin, relationship, owner_id,
                    source, location, is_active, created_by, created_at
                ) VALUES (
                    :org_id, :fname, :lname, :name, :comp_id, :cname, :title,
                    :email, :phone, :clean_phone, :whatsapp, :pref_channel, :linkedin, :rel, :owner_id,
                    :source, :location, 1, :cb, NOW()
                )
            ");
            $insCnt->execute([
                ':org_id'        => $organizationId,
                ':fname'         => $first ?: null,
                ':lname'         => $last ?: null,
                ':name'          => $name,
                ':comp_id'       => $companyId,
                ':cname'         => $cName,
                ':title'         => $jobTitle ?: null,
                ':email'         => $email ?: null,
                ':phone'         => $phone ?: null,
                ':clean_phone'   => $cleanPhone ?: null,
                ':whatsapp'      => $phone ?: null,
                ':pref_channel'  => $preferredChannel ?: null,
                ':linkedin'      => $linkedin ?: null,
                ':rel'           => $relationship ?: 'Customer',
                ':owner_id'      => $ownerId,
                ':source'        => $source ?: null,
                ':location'      => $location ?: null,
                ':cb'            => $currentUserId
            ]);

            $newContactId = (int)$pdo->lastInsertId();
            $cntCode = 'CNT-' . str_pad($newContactId, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE contacts SET contact_code = ? WHERE id = ? AND organization_id = ?")
                ->execute([$cntCode, $newContactId, $organizationId]);

            // Insert into authoritative junction table contact_companies
            $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (?, ?, ?)")
                ->execute([$organizationId, $newContactId, $companyId]);

            // If Set as Primary Contact is checked:
            // set companies.primary_contact_id = new_contact.id for THIS company
            if ($isPrimary) {
                $pdo->prepare("UPDATE companies SET primary_contact_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
                    ->execute([$newContactId, $companyId, $organizationId]);
            }

            // Log activity
            $pdo->prepare("
                INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
                VALUES (:org_id, :uid, 'contact_added', :title, :desc, 'companies', :eid, :cb, NOW())
            ")->execute([
                ':org_id' => $organizationId,
                ':uid'    => $currentUserId,
                ':title'  => 'Contact added: ' . $name,
                ':desc'   => "Added contact {$name} to {$cName}" . ($isPrimary ? " (Set as Primary Contact)" : ""),
                ':eid'    => $companyId,
                ':cb'     => $currentUserId
            ]);

            $pdo->commit();

            companies_json(true, 'Contact created successfully.', [
                'id'         => $newContactId,
                'contact_id' => $newContactId,
                'is_primary' => $isPrimary
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            companies_json(false, 'Failed to add contact: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // 10. SUB-ENTITY: CREATE DEAL
    // -------------------------------------------------------------------------
    case 'create_deal':
        require_company_perm('create');
        $companyId = (int)($request['company_id'] ?? 0);
        if ($companyId <= 0) {
            companies_json(false, 'Invalid company ID.', [], 400);
        }

        $chkStmt = $pdo->prepare("SELECT name FROM companies WHERE id = :id AND organization_id = :org_id LIMIT 1");
        $chkStmt->execute([':id' => $companyId, ':org_id' => $organizationId]);
        $cName = $chkStmt->fetchColumn();
        if (!$cName) {
            companies_json(false, 'Company not found.', [], 404);
        }

        $dealName = trim($request['name'] ?? $request['title'] ?? '');
        if ($dealName === '') {
            companies_json(false, 'Deal name is required.', [], 422);
        }

        $rawVal = trim($request['value'] ?? '0');
        $cleanVal = preg_replace('/[^0-9.]/', '', $rawVal);
        $val = $cleanVal !== '' ? (float)$cleanVal : 0.00;

        $stage = trim($request['stage'] ?? 'Proposal');
        $prob = !empty($request['probability']) ? (float)$request['probability'] : 50.00;
        $closeDate = !empty($request['close_date']) ? date('Y-m-d', strtotime($request['close_date'])) : null;

        $insDeal = $pdo->prepare("
            INSERT INTO deals (organization_id, name, company, stage, value, probability, close_date, status, assigned_to, created_at)
            VALUES (:org_id, :name, :cname, :stage, :value, :prob, :close_date, 'open', :assigned_to, NOW())
        ");
        $insDeal->execute([
            ':org_id'      => $organizationId,
            ':name'        => $dealName,
            ':cname'       => $cName,
            ':stage'       => $stage,
            ':value'       => $val,
            ':prob'        => $prob,
            ':close_date'  => $closeDate,
            ':assigned_to' => $currentUserId
        ]);
        $newDealId = (int)$pdo->lastInsertId();

        // Log activity
        $pdo->prepare("
            INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (:org_id, :uid, 'deal_created', :title, :desc, 'companies', :eid, :cb, NOW())
        ")->execute([
            ':org_id' => $organizationId,
            ':uid'    => $currentUserId,
            ':title'  => 'Deal created: ' . $dealName,
            ':desc'   => "Created deal {$dealName} (\${$val}) for {$cName}",
            ':eid'    => $companyId,
            ':cb'     => $currentUserId
        ]);

        companies_json(true, 'Deal created successfully.', ['id' => $newDealId]);
        break;

    // -------------------------------------------------------------------------
    // 11. SUB-ENTITY: LOG ACTIVITY
    // -------------------------------------------------------------------------
    case 'log_activity':
        require_company_perm('create');
        $companyId = (int)($request['company_id'] ?? 0);
        $type = trim($request['type'] ?? $request['activity_type'] ?? 'Call');
        $summary = trim($request['summary'] ?? $request['title'] ?? $request['description'] ?? '');
        $description = trim($request['description'] ?? $summary);

        if ($companyId <= 0 || $summary === '') {
            companies_json(false, 'Company ID and activity summary are required.', [], 422);
        }

        $insAct = $pdo->prepare("
            INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (:org_id, :uid, :type, :title, :desc, 'companies', :eid, :cb, NOW())
        ");
        $insAct->execute([
            ':org_id' => $organizationId,
            ':uid'    => $currentUserId,
            ':type'   => $type,
            ':title'  => $summary,
            ':desc'   => $description ?: null,
            ':eid'    => $companyId,
            ':cb'     => $currentUserId
        ]);

        companies_json(true, 'Activity logged successfully.', ['id' => (int)$pdo->lastInsertId()]);
        break;

    // -------------------------------------------------------------------------
    // 12. SUB-ENTITY: TASKS (ADD / EDIT / TOGGLE / DELETE)
    // -------------------------------------------------------------------------
    case 'add_task':
        require_company_perm('create');
        $companyId = (int)($request['company_id'] ?? 0);
        $title = trim($request['title'] ?? '');
        if ($companyId <= 0 || $title === '') {
            companies_json(false, 'Company ID and task title are required.', [], 422);
        }

        $priority = in_array(strtolower($request['priority'] ?? ''), ['low', 'medium', 'high', 'urgent']) ? strtolower($request['priority']) : 'medium';
        $dueDate = !empty($request['due_date']) ? date('Y-m-d H:i:s', strtotime($request['due_date'])) : null;
        $assignedTo = !empty($request['assigned_to']) ? (int)$request['assigned_to'] : $currentUserId;
        $notes = trim($request['description'] ?? $request['notes'] ?? '');

        $insTask = $pdo->prepare("
            INSERT INTO tasks (organization_id, title, description, status, priority, due_date, assigned_to, company_id, related_type, related_id, created_at)
            VALUES (:org_id, :title, :desc, 'pending', :prio, :due, :assigned, :cid, 'companies', :cid, NOW())
        ");
        $insTask->execute([
            ':org_id'   => $organizationId,
            ':title'    => $title,
            ':desc'     => $notes ?: null,
            ':prio'     => $priority,
            ':due'      => $dueDate,
            ':assigned' => $assignedTo,
            ':cid'      => $companyId
        ]);

        companies_json(true, 'Task created successfully.', ['id' => (int)$pdo->lastInsertId()]);
        break;

    case 'edit_task':
        require_company_perm('edit');
        $taskId = (int)($request['task_id'] ?? $request['id'] ?? 0);
        $title = trim($request['title'] ?? '');
        if ($taskId <= 0 || $title === '') {
            companies_json(false, 'Task ID and title are required.', [], 422);
        }

        $priority = in_array(strtolower($request['priority'] ?? ''), ['low', 'medium', 'high', 'urgent']) ? strtolower($request['priority']) : 'medium';
        $dueDate = !empty($request['due_date']) ? date('Y-m-d H:i:s', strtotime($request['due_date'])) : null;
        $notes = trim($request['description'] ?? $request['notes'] ?? '');

        $updTask = $pdo->prepare("
            UPDATE tasks SET title = :title, description = :desc, priority = :prio, due_date = :due, updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");
        $updTask->execute([
            ':title'  => $title,
            ':desc'   => $notes ?: null,
            ':prio'   => $priority,
            ':due'    => $dueDate,
            ':id'     => $taskId,
            ':org_id' => $organizationId
        ]);

        companies_json(true, 'Task updated successfully.', ['id' => $taskId]);
        break;

    case 'toggle_task':
        require_company_perm('edit');
        $taskId = (int)($request['task_id'] ?? $request['id'] ?? 0);
        $done = !empty($request['done']) || ($request['status'] ?? '') === 'completed';

        $status = $done ? 'completed' : 'pending';
        $upd = $pdo->prepare("UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $upd->execute([':status' => $status, ':id' => $taskId, ':org_id' => $organizationId]);

        companies_json(true, 'Task status updated.', ['id' => $taskId, 'status' => $status]);
        break;

    case 'delete_task':
        require_company_perm('delete');
        $taskId = (int)($request['task_id'] ?? $request['id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND organization_id = :org_id");
        $del->execute([':id' => $taskId, ':org_id' => $organizationId]);

        companies_json(true, 'Task deleted successfully.', ['id' => $taskId]);
        break;

    // -------------------------------------------------------------------------
    // 13. SUB-ENTITY: NOTES (ADD / EDIT / PIN / DELETE)
    // -------------------------------------------------------------------------
    case 'add_note':
        require_company_perm('create');
        $companyId = (int)($request['company_id'] ?? 0);
        $content = trim($request['content'] ?? '');
        $title = trim($request['title'] ?? 'Note');
        $isPinned = !empty($request['is_pinned']) ? 1 : 0;

        if ($companyId <= 0 || $content === '') {
            companies_json(false, 'Company ID and note content are required.', [], 422);
        }

        $insNote = $pdo->prepare("
            INSERT INTO team_notes (organization_id, related_type, related_id, created_by, title, content, is_pinned, created_at)
            VALUES (:org_id, 'companies', :cid, :cb, :title, :content, :pinned, NOW())
        ");
        $insNote->execute([
            ':org_id'  => $organizationId,
            ':cid'     => $companyId,
            ':cb'      => $currentUserId,
            ':title'   => $title,
            ':content' => $content,
            ':pinned'  => $isPinned
        ]);

        companies_json(true, 'Note added successfully.', ['id' => (int)$pdo->lastInsertId()]);
        break;

    case 'edit_note':
        require_company_perm('edit');
        $noteId = (int)($request['note_id'] ?? $request['id'] ?? 0);
        $content = trim($request['content'] ?? '');
        $title = trim($request['title'] ?? '');

        if ($noteId <= 0 || $content === '') {
            companies_json(false, 'Note ID and content are required.', [], 422);
        }

        $updNote = $pdo->prepare("
            UPDATE team_notes SET content = :content, title = COALESCE(NULLIF(:title, ''), title), updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");
        $updNote->execute([
            ':content' => $content,
            ':title'   => $title,
            ':id'      => $noteId,
            ':org_id'  => $organizationId
        ]);

        companies_json(true, 'Note updated successfully.', ['id' => $noteId]);
        break;

    case 'toggle_pin_note':
        require_company_perm('edit');
        $noteId = (int)($request['note_id'] ?? $request['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE team_notes SET is_pinned = NOT is_pinned, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $noteId, ':org_id' => $organizationId]);

        companies_json(true, 'Note pin toggled.', ['id' => $noteId]);
        break;

    case 'delete_note':
        require_company_perm('delete');
        $noteId = (int)($request['note_id'] ?? $request['id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM team_notes WHERE id = :id AND organization_id = :org_id");
        $del->execute([':id' => $noteId, ':org_id' => $organizationId]);

        companies_json(true, 'Note deleted successfully.', ['id' => $noteId]);
        break;

    // -------------------------------------------------------------------------
    // 14. EXPORT CSV
    // -------------------------------------------------------------------------
    case 'export_csv':
        require_company_perm('export');

        $search = trim($request['search'] ?? '');
        $relationship = trim($request['relationship'] ?? 'All');
        $industry = trim($request['industry'] ?? 'All');
        $owner = trim($request['owner'] ?? 'All');

        $where = ['c.organization_id = :org_id'];
        $params = [':org_id' => $organizationId];

        if ($search !== '') {
            $where[] = '(c.name LIKE :search OR c.domain LIKE :search OR c.industry LIKE :search OR c.location LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($relationship !== '' && $relationship !== 'All') {
            $where[] = 'c.relationship = :rel';
            $params[':rel'] = $relationship;
        }
        if ($industry !== '' && $industry !== 'All') {
            $where[] = 'c.industry = :ind';
            $params[':ind'] = $industry;
        }
        if ($owner !== '' && $owner !== 'All') {
            if (is_numeric($owner)) {
                $where[] = 'c.owner_id = :oid';
                $params[':oid'] = (int)$owner;
            } else {
                $where[] = 'u.name = :oname';
                $params[':oname'] = $owner;
            }
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                c.id,
                c.company_code,
                c.name,
                c.domain,
                c.industry,
                c.company_size,
                c.annual_revenue,
                c.location,
                c.relationship,
                u.name AS owner_name,
                cnt.name AS primary_contact_name,
                (SELECT COUNT(*) FROM contacts co WHERE co.organization_id = c.organization_id AND co.company_name = c.name) AS contacts_count,
                (SELECT COUNT(*) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = 'open') AS open_deals_count,
                (SELECT COALESCE(SUM(d.value), 0) FROM deals d WHERE d.organization_id = c.organization_id AND d.company = c.name AND d.status = 'open') AS open_deals_value,
                c.created_at
            FROM companies c
            LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = c.organization_id
            LEFT JOIN contacts cnt ON cnt.id = c.primary_contact_id AND cnt.organization_id = c.organization_id
            WHERE {$whereSql}
            ORDER BY c.name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="companies_export_' . date('Y-m-d') . '.csv"');

        $outHandle = fopen('php://output', 'w');
        fputcsv($outHandle, [
            'Company Code', 'Company Name', 'Domain', 'Industry', 'Size',
            'Annual Revenue ($)', 'Location', 'Relationship', 'Owner',
            'Primary Contact', 'Contacts Count', 'Open Deals Count',
            'Pipeline Value ($)', 'Created At'
        ]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($outHandle, [
                $row['company_code'] ?: ('COMP-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT)),
                $row['name'],
                $row['domain'],
                $row['industry'],
                $row['company_size'],
                $row['annual_revenue'] !== null ? number_format((float)$row['annual_revenue'], 2) : '',
                $row['location'],
                $row['relationship'],
                $row['owner_name'],
                $row['primary_contact_name'],
                $row['contacts_count'],
                $row['open_deals_count'],
                number_format((float)$row['open_deals_value'], 2),
                $row['created_at']
            ]);
        }
        fclose($outHandle);
        exit;

    // -------------------------------------------------------------------------
    // 15. IMPORT CSV
    // -------------------------------------------------------------------------
    case 'import_csv':
        require_company_perm('create');

        if (empty($_FILES['csv_file']['tmp_name'])) {
            companies_json(false, 'No CSV file uploaded.', [], 400);
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            companies_json(false, 'Unable to read uploaded CSV file.', [], 400);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            companies_json(false, 'CSV file is empty or corrupt.', [], 422);
        }

        // Normalize header keys
        $headerMap = [];
        foreach ($headers as $idx => $headerText) {
            $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace([' ', '-'], '_', $headerText))));
            $headerMap[$clean] = $idx;
        }

        $nameIdx = $headerMap['company_name'] ?? $headerMap['name'] ?? $headerMap['company'] ?? null;
        if ($nameIdx === null) {
            fclose($handle);
            companies_json(false, 'CSV must contain a "Company Name" or "Name" column.', [], 422);
        }

        $domainIdx = $headerMap['domain'] ?? $headerMap['website'] ?? null;
        $industryIdx = $headerMap['industry'] ?? null;
        $sizeIdx = $headerMap['size'] ?? $headerMap['company_size'] ?? null;
        $revenueIdx = $headerMap['revenue'] ?? $headerMap['annual_revenue'] ?? null;
        $locationIdx = $headerMap['location'] ?? $headerMap['city'] ?? null;
        $relIdx = $headerMap['relationship'] ?? null;
        $ownerIdx = $headerMap['owner'] ?? $headerMap['account_owner'] ?? null;

        $insertedCount = 0;
        $failedCount = 0;

        $pdo->beginTransaction();
        try {
            $seqStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM companies WHERE organization_id = ?");
            $seqStmt->execute([$organizationId]);
            $nextSeq = (int)$seqStmt->fetchColumn();

            $insStmt = $pdo->prepare("
                INSERT INTO companies (
                    organization_id, company_code, name, domain, industry, company_size,
                    annual_revenue, location, relationship, owner_id, is_active, created_by, created_at
                ) VALUES (
                    :org_id, :code, :name, :domain, :ind, :size,
                    :rev, :loc, :rel, :owner_id, 1, :cb, NOW()
                )
            ");

            while (($row = fgetcsv($handle)) !== false) {
                $compName = isset($row[$nameIdx]) ? trim($row[$nameIdx]) : '';
                if ($compName === '') {
                    $failedCount++;
                    continue;
                }

                $code = 'COMP-' . str_pad($nextSeq++, 6, '0', STR_PAD_LEFT);
                $dom = $domainIdx !== null && isset($row[$domainIdx]) ? trim($row[$domainIdx]) : null;
                $ind = $industryIdx !== null && isset($row[$industryIdx]) ? trim($row[$industryIdx]) : null;
                $sz  = $sizeIdx !== null && isset($row[$sizeIdx]) ? trim($row[$sizeIdx]) : null;
                $revStr = $revenueIdx !== null && isset($row[$revenueIdx]) ? trim($row[$revenueIdx]) : '';
                $cleanRev = preg_replace('/[^0-9.]/', '', $revStr);
                $rev = $cleanRev !== '' ? (float)$cleanRev : null;
                $loc = $locationIdx !== null && isset($row[$locationIdx]) ? trim($row[$locationIdx]) : null;
                $rel = $relIdx !== null && isset($row[$relIdx]) ? trim($row[$relIdx]) : 'Prospect';

                $ownerId = null;
                if ($ownerIdx !== null && isset($row[$ownerIdx]) && trim($row[$ownerIdx]) !== '') {
                    $chkO = $pdo->prepare("SELECT id FROM users WHERE organization_id = ? AND (name = ? OR email = ?) AND status = 'active' LIMIT 1");
                    $chkO->execute([$organizationId, trim($row[$ownerIdx]), trim($row[$ownerIdx])]);
                    $ownerId = $chkO->fetchColumn() ?: null;
                }

                $insStmt->execute([
                    ':org_id'   => $organizationId,
                    ':code'     => $code,
                    ':name'     => $compName,
                    ':domain'   => $dom ?: null,
                    ':ind'      => $ind ?: null,
                    ':size'     => $sz ?: null,
                    ':rev'      => $rev,
                    ':loc'      => $loc ?: null,
                    ':rel'      => $rel ?: 'Prospect',
                    ':owner_id' => $ownerId,
                    ':cb'       => $currentUserId
                ]);
                $insertedCount++;
            }
            fclose($handle);
            $pdo->commit();
            companies_json(true, "Successfully imported {$insertedCount} companies." . ($failedCount > 0 ? " ({$failedCount} rows skipped)" : ""));
        } catch (Throwable $e) {
            fclose($handle);
            $pdo->rollBack();
            companies_json(false, 'CSV import failed: ' . $e->getMessage(), [], 500);
        }
        break;

    default:
        companies_json(false, "Unknown action: {$action}", [], 400);
        break;
}
