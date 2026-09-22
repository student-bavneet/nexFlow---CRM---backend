<?php
/**
 * NexFlow CRM - Contacts Module API
 * Multi-tenant, organization-scoped controller for Contacts, Tasks, Notes, Activities, Meetings, and Deals.
 * Strictly 0 mock data, 100% MySQL source of truth.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function contacts_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authenticate user
$currentUser = nexflow_current_user();
if (!$currentUser) {
    contacts_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId = (int)$currentUser['id'];

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    contacts_json(false, 'Database connection failed: ' . $e->getMessage(), [], 500);
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
$canView   = hasPermission('contacts', 'view')   || is_super_admin($currentUserId);
$canCreate = hasPermission('contacts', 'create') || is_super_admin($currentUserId);
$canEdit   = hasPermission('contacts', 'edit')   || is_super_admin($currentUserId);
$canDelete = hasPermission('contacts', 'delete') || is_super_admin($currentUserId);
$canExport = hasPermission('contacts', 'export') || is_super_admin($currentUserId);

if (!$canView && $action !== 'export_csv') {
    contacts_json(false, 'Permission denied. You cannot view Contacts.', [], 403);
}

// Helpers
function get_contact_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

function get_owner_color(int $userId): string
{
    $colors = ['#7C3AED', '#2563EB', '#059669', '#D97706', '#DC2626', '#4F46E5', '#0284C7', '#0D9488'];
    return $colors[$userId % count($colors)];
}

// Helper: Log team activity
function log_contact_activity(PDO $pdo, int $orgId, int $userId, int $contactId, string $type, string $title, string $desc = ''): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by)
            VALUES (?, ?, ?, ?, ?, 'contacts', ?, ?)
        ");
        $stmt->execute([$orgId, $userId, $type, $title, $desc, $contactId, $userId]);
    } catch (Throwable $e) {
        // Silently skip activity logging failures
    }
}

// Helper: Batch get associated companies for contacts from contact_companies
function get_contact_companies_batch(PDO $pdo, int $orgId, array $contactIds): array
{
    if (empty($contactIds)) return [];
    $inPlaceholders = implode(',', array_fill(0, count($contactIds), '?'));
    $stmt = $pdo->prepare("
        SELECT cc.contact_id, comp.id AS company_id, comp.name AS company_name, comp.location AS company_location,
               (CASE WHEN comp.primary_contact_id = cc.contact_id THEN 1 ELSE 0 END) AS is_comp_primary
        FROM contact_companies cc
        JOIN companies comp ON comp.id = cc.company_id AND comp.organization_id = cc.organization_id
        WHERE cc.contact_id IN ($inPlaceholders) AND cc.organization_id = ?
        ORDER BY comp.name ASC
    ");
    $stmt->execute(array_merge($contactIds, [$orgId]));
    $res = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $res[(int)$row['contact_id']][] = [
            'id'        => (int)$row['company_id'],
            'name'      => $row['company_name'],
            'location'  => $row['company_location'] ?: '',
            'isPrimary' => (bool)$row['is_comp_primary']
        ];
    }
    return $res;
}

// ROUTER
switch ($action) {

    // -------------------------------------------------------------
    // 1. BOOTSTRAP / LIST
    // -------------------------------------------------------------
    case 'bootstrap':
    case 'list':
        $search = trim($_GET['search'] ?? ($input['search'] ?? ''));
        $relationship = trim($_GET['relationship'] ?? ($input['relationship'] ?? 'All'));
        $owner = trim($_GET['owner'] ?? ($input['owner'] ?? 'All'));
        $cardFilter = trim($_GET['card_filter'] ?? ($input['card_filter'] ?? 'All'));
        $sort = trim($_GET['sort'] ?? ($input['sort'] ?? 'name-asc'));
        $page = max(1, (int)($_GET['page'] ?? ($input['page'] ?? 1)));
        $pageSize = max(1, min(100, (int)($_GET['page_size'] ?? ($input['page_size'] ?? 8))));
        $offset = ($page - 1) * $pageSize;

        $where = ["c.organization_id = ?"];
        $params = [$organizationId];

        // Search
        if ($search !== '') {
            $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.company_name LIKE ? OR c.job_title LIKE ? OR c.phone LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // Relationship filter
        if ($relationship !== 'All' && $relationship !== '') {
            $where[] = "c.relationship = ?";
            $params[] = $relationship;
        }

        // Owner filter
        if ($owner !== 'All' && $owner !== '') {
            if (is_numeric($owner)) {
                $where[] = "c.owner_id = ?";
                $params[] = (int)$owner;
            } else {
                $where[] = "u.name = ?";
                $params[] = $owner;
            }
        }

        // Card filter
        if ($cardFilter === 'Active') {
            $where[] = "c.is_active = 1";
        } elseif ($cardFilter === 'Recent') {
            $where[] = "c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif ($cardFilter === 'Unassigned') {
            $where[] = "c.owner_id IS NULL";
        } elseif ($cardFilter === 'Inactive') {
            $where[] = "c.is_active = 0";
        }

        $whereSql = implode(' AND ', $where);

        // Sorting
        $orderBy = "c.name ASC";
        switch ($sort) {
            case 'name-desc':
                $orderBy = "c.name DESC";
                break;
            case 'rel-Customer':
                $orderBy = "CASE WHEN c.relationship = 'Customer' THEN 0 ELSE 1 END, c.name ASC";
                break;
            case 'rel-Partner':
                $orderBy = "CASE WHEN c.relationship = 'Partner' THEN 0 ELSE 1 END, c.name ASC";
                break;
            case 'rel-Prospect':
                $orderBy = "CASE WHEN c.relationship = 'Prospect' THEN 0 ELSE 1 END, c.name ASC";
                break;
            case 'interaction':
                $orderBy = "c.updated_at DESC";
                break;
            case 'name-asc':
            default:
                $orderBy = "c.name ASC";
                break;
        }

        // Count total matching
        $countSql = "
            SELECT COUNT(*)
            FROM contacts c
            LEFT JOIN users u ON c.owner_id = u.id
            WHERE {$whereSql}
        ";
        $cStmt = $pdo->prepare($countSql);
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        // Fetch contacts
        $listSql = "
            SELECT 
                c.id,
                c.contact_code,
                c.first_name,
                c.last_name,
                c.name,
                c.company_id,
                c.company_name,
                c.job_title,
                c.email,
                c.phone,
                c.clean_phone,
                c.whatsapp,
                c.preferred_channel,
                c.linkedin,
                c.relationship,
                c.owner_id,
                c.source,
                c.location,
                c.lead_id,
                c.is_active,
                c.created_at,
                c.updated_at,
                u.name AS owner_name,
                u.email AS owner_email,
                (SELECT COUNT(*) FROM deals d WHERE (d.contact_id = c.id OR (d.contact_id IS NULL AND LOWER(TRIM(d.contact_name)) = LOWER(TRIM(c.name)))) AND d.organization_id = c.organization_id) AS deals_count,
                (SELECT COALESCE(SUM(d.value), 0) FROM deals d WHERE (d.contact_id = c.id OR (d.contact_id IS NULL AND LOWER(TRIM(d.contact_name)) = LOWER(TRIM(c.name)))) AND d.organization_id = c.organization_id) AS deals_value,
                (SELECT created_at FROM team_activities a WHERE a.related_entity = 'contacts' AND a.related_entity_id = c.id AND a.organization_id = c.organization_id ORDER BY created_at DESC LIMIT 1) AS last_activity_at,
                (SELECT activity_type FROM team_activities a WHERE a.related_entity = 'contacts' AND a.related_entity_id = c.id AND a.organization_id = c.organization_id ORDER BY created_at DESC LIMIT 1) AS last_activity_type,
                (CASE WHEN c.company_id IS NOT NULL AND comp.primary_contact_id = c.id THEN 1 ELSE 0 END) AS is_primary,
                comp.location AS company_location
            FROM contacts c
            LEFT JOIN users u ON c.owner_id = u.id
            LEFT JOIN companies comp ON comp.id = c.company_id AND comp.organization_id = c.organization_id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT {$pageSize} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($listSql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $contactIds = array_column($rawRows, 'id');
        $companiesByContact = get_contact_companies_batch($pdo, $organizationId, $contactIds);

        $contacts = [];
        foreach ($rawRows as $row) {
            $cid = (int)$row['id'];
            $assocComps = $companiesByContact[$cid] ?? [];
            $compNames = array_column($assocComps, 'name');
            $primaryCompanyNames = [];
            foreach ($assocComps as $ac) {
                if ($ac['isPrimary']) $primaryCompanyNames[] = $ac['name'];
            }
            $isPrimary = !empty($primaryCompanyNames) || !empty($row['is_primary']);
            $primaryCompanyStr = !empty($primaryCompanyNames) ? implode(', ', $primaryCompanyNames) : ($row['company_name'] ?: '');
            $displayCompany = !empty($compNames) ? implode(', ', $compNames) : ($row['company_name'] ?: '');
            $firstCompanyId = !empty($assocComps) ? $assocComps[0]['id'] : ($row['company_id'] ? (int)$row['company_id'] : null);
            $firstCompanyLocation = !empty($assocComps) ? ($assocComps[0]['location'] ?? '') : ($row['company_location'] ?: '');

            $ownerName = $row['owner_name'] ?: 'Unassigned';
            $ownerId = (int)($row['owner_id'] ?? 0);
            
            // Format last activity
            $lastActStr = '—';
            $lastActType = 'None';
            if (!empty($row['last_activity_at'])) {
                $timeDiff = time() - strtotime($row['last_activity_at']);
                if ($timeDiff < 3600) {
                    $lastActStr = max(1, round($timeDiff / 60)) . 'm ago';
                } elseif ($timeDiff < 86400) {
                    $lastActStr = round($timeDiff / 3600) . 'h ago';
                } elseif ($timeDiff < 604800) {
                    $lastActStr = round($timeDiff / 86400) . 'd ago';
                } else {
                    $lastActStr = date('M j, Y', strtotime($row['last_activity_at']));
                }
                $lastActType = ucfirst($row['last_activity_type'] ?: 'Activity');
            }

            $contacts[] = [
                'id'                => (int)$row['id'],
                'code'              => $row['contact_code'] ?: ('CNT-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT)),
                'firstName'         => $row['first_name'] ?: '',
                'lastName'          => $row['last_name'] ?: '',
                'name'              => $row['name'],
                'companyId'         => $firstCompanyId,
                'company'           => $displayCompany,
                'companyLocation'   => $firstCompanyLocation,
                'companies'         => $assocComps,
                'companiesCount'    => count($assocComps),
                'isPrimary'         => $isPrimary,
                'primaryCompanyName'=> $primaryCompanyStr,
                'jobTitle'          => $row['job_title'] ?: '',
                'email'             => $row['email'] ?: '',
                'phone'             => $row['phone'] ?: '',
                'cleanPhone'        => $row['clean_phone'] ?: preg_replace('/[^0-9]/', '', $row['phone'] ?: ''),
                'whatsapp'          => $row['whatsapp'] ?: '',
                'preferredChannel'  => $row['preferred_channel'] ?: '',
                'preferred_channel' => $row['preferred_channel'] ?: '',
                'linkedin'          => $row['linkedin'] ?: '',
                'relationship'      => $row['relationship'] ?: 'Prospect',
                'owner'             => $ownerName,
                'ownerId'           => $ownerId ?: null,
                'ownerInitials'     => $ownerId ? get_contact_initials($ownerName) : 'UN',
                'ownerColor'        => $ownerId ? get_owner_color($ownerId) : '#94A3B8',
                'source'            => $row['source'] ?: '',
                'location'          => $row['location'] ?: '',
                'leadId'            => $row['lead_id'] ? (int)$row['lead_id'] : null,
                'isActive'          => (bool)$row['is_active'],
                'dealsCount'        => (int)$row['deals_count'],
                'dealsValue'        => (float)$row['deals_value'],
                'lastActivity'      => $lastActStr,
                'lastActivityType'  => $lastActType,
                'createdAt'         => date('M d, Y', strtotime($row['created_at']))
            ];
        }

        // Real KPI metrics from MySQL
        $kpiStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS total_contacts,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) AS active_contacts,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) AS recent_contacts,
                COUNT(CASE WHEN owner_id IS NULL THEN 1 END) AS unassigned_contacts,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) AS inactive_contacts
            FROM contacts
            WHERE organization_id = ?
        ");
        $kpiStmt->execute([$organizationId]);
        $dbKpis = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis = [
            'total'      => (int)($dbKpis['total_contacts'] ?? 0),
            'active'     => (int)($dbKpis['active_contacts'] ?? 0),
            'recent'     => (int)($dbKpis['recent_contacts'] ?? 0),
            'unassigned' => (int)($dbKpis['unassigned_contacts'] ?? 0),
            'inactive'   => (int)($dbKpis['inactive_contacts'] ?? 0)
        ];

        // Active owners for filter and modals
        $ownerStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
        $ownerStmt->execute([$organizationId]);
        $owners = $ownerStmt->fetchAll(PDO::FETCH_ASSOC);

        // Active companies for company linking / autocomplete
        $compStmt = $pdo->prepare("SELECT id, name, company_code FROM companies WHERE organization_id = ? AND is_active = 1 ORDER BY name ASC");
        $compStmt->execute([$organizationId]);
        $companiesList = $compStmt->fetchAll(PDO::FETCH_ASSOC);

        contacts_json(true, 'Contacts fetched successfully.', [
            'contacts'    => $contacts,
            'kpis'        => $kpis,
            'owners'      => $owners,
            'companies'   => $companiesList,
            'pagination'  => [
                'total'       => $total,
                'page'        => $page,
                'pageSize'    => $pageSize,
                'totalPages'  => max(1, (int)ceil($total / $pageSize)),
                'rangeStart'  => $total > 0 ? $offset + 1 : 0,
                'rangeEnd'    => min($offset + $pageSize, $total)
            ]
        ]);
        break;

    // -------------------------------------------------------------
    // 2. GET SINGLE CONTACT
    // -------------------------------------------------------------
    case 'get':
        $contactId = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($contactId <= 0) {
            contacts_json(false, 'Invalid contact ID.', [], 400);
        }

        $stmt = $pdo->prepare("
            SELECT c.*, u.name AS owner_name, u.email AS owner_email,
                (CASE WHEN c.company_id IS NOT NULL AND comp.primary_contact_id = c.id THEN 1 ELSE 0 END) AS is_primary
            FROM contacts c
            LEFT JOIN users u ON c.owner_id = u.id
            LEFT JOIN companies comp ON comp.id = c.company_id AND comp.organization_id = c.organization_id
            WHERE c.id = ? AND c.organization_id = ?
        ");
        $stmt->execute([$contactId, $organizationId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contact) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $assocComps = get_contact_companies_batch($pdo, $organizationId, [$contactId])[$contactId] ?? [];
        $compNames = array_column($assocComps, 'name');
        $primaryCompanyNames = [];
        foreach ($assocComps as $ac) {
            if ($ac['isPrimary']) $primaryCompanyNames[] = $ac['name'];
        }
        $isPrimary = !empty($primaryCompanyNames) || !empty($contact['is_primary']);
        $contact['isPrimary'] = $isPrimary;
        $contact['companies'] = $assocComps;
        $contact['companiesCount'] = count($assocComps);
        $contact['primaryCompanyName'] = !empty($primaryCompanyNames) ? implode(', ', $primaryCompanyNames) : ($contact['company_name'] ?: '');
        if (!empty($compNames)) {
            $contact['company_name'] = implode(', ', $compNames);
        }

        contacts_json(true, 'Contact details fetched.', ['contact' => $contact]);
        break;

    // -------------------------------------------------------------
    // 3. CREATE CONTACT
    // -------------------------------------------------------------
    case 'create':
        if (!$canCreate) {
            contacts_json(false, 'Permission denied. You cannot create contacts.', [], 403);
        }

        $first = trim($input['first_name'] ?? '');
        $last = trim($input['last_name'] ?? '');
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            $name = trim("{$first} {$last}");
        }
        if ($name === '') {
            contacts_json(false, 'Contact name is required.', [], 400);
        }

        $email = trim($input['email'] ?? '');
        if ($email === '') {
            contacts_json(false, 'Email address is required.', [], 400);
        }

        $pdo->beginTransaction();
        try {
            // First resolve contact owner so that if a brand-new company is created,
            // its account owner matches the selected contact owner (or NULL if unassigned).
            $ownerId = !empty($input['owner_id']) ? (int)$input['owner_id'] : null;
            if (!$ownerId && !empty($input['owner'])) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND organization_id = ? AND status = 'active' LIMIT 1");
                $uStmt->execute([trim($input['owner']), $organizationId]);
                $foundUid = $uStmt->fetchColumn();
                if ($foundUid) $ownerId = (int)$foundUid;
            }
            if ($ownerId) {
                $checkOwner = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
                $checkOwner->execute([$ownerId, $organizationId]);
                if (!$checkOwner->fetchColumn()) {
                    $ownerId = null;
                }
            }

            $isPrimary = !empty($input['is_primary']);

            $company = trim($input['company_name'] ?? ($input['company'] ?? ''));
            $companyId = !empty($input['company_id']) ? (int)$input['company_id'] : null;

            // Organization-scoped company resolution
            if ($companyId > 0) {
                $chkComp = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND organization_id = ? AND is_active = 1 LIMIT 1");
                $chkComp->execute([$companyId, $organizationId]);
                $compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
                if (!$compRow) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    contacts_json(false, 'The selected company does not exist, is inactive, or does not belong to your organization.', [], 422);
                }
                $companyId = (int)$compRow['id'];
                $company = $compRow['name'];
                // NOTE: DO NOT change existing company owner_id!
            } elseif ($company !== '') {
                // Server-side normalized duplicate check within current organization
                $chkComp = $pdo->prepare("
                    SELECT id, name 
                    FROM companies 
                    WHERE organization_id = ? 
                      AND LOWER(TRIM(name)) = LOWER(TRIM(?)) 
                      AND is_active = 1 
                    LIMIT 1
                ");
                $chkComp->execute([$organizationId, $company]);
                $compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
                if ($compRow) {
                    $companyId = (int)$compRow['id'];
                    $company = $compRow['name'];
                    // NOTE: Existing company found - DO NOT change existing company owner_id!
                } else {
                    // Pending company creation within the same atomic transaction
                    $seqStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM companies WHERE organization_id = ?");
                    $seqStmt->execute([$organizationId]);
                    $nextCompId = (int)$seqStmt->fetchColumn();
                    $compCode = 'COMP-' . str_pad($nextCompId, 6, '0', STR_PAD_LEFT);

                    $insComp = $pdo->prepare("
                        INSERT INTO companies (
                            organization_id, company_code, name, is_active, created_by, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, 1, ?, NOW(), NOW()
                        )
                    ");
                    $insComp->execute([
                        $organizationId,
                        $compCode,
                        $company,
                        $currentUserId
                    ]);
                    $companyId = (int)$pdo->lastInsertId();

                    // Log company creation activity
                    $actStmt = $pdo->prepare("
                        INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
                        VALUES (?, ?, 'company_created', ?, ?, 'companies', ?, ?, NOW())
                    ");
                    $actStmt->execute([
                        $organizationId,
                        $currentUserId,
                        'Company created: ' . $company,
                        "Company {$company} ({$compCode}) created by " . ($currentUser['name'] ?? 'User'),
                        $companyId,
                        $currentUserId
                    ]);
                }
            } else {
                $companyId = null;
                $company = null;
            }

            $jobTitle = trim($input['job_title'] ?? ($input['job'] ?? ''));
            $phone = trim($input['phone'] ?? '');
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $whatsapp = trim($input['whatsapp'] ?? ($phone !== '' ? $phone : ''));
            $preferredChannel = trim($input['preferred_channel'] ?? ($input['preferredChannel'] ?? ''));
            $linkedin = trim($input['linkedin'] ?? '');
            $relationship = trim($input['relationship'] ?? '');

            $source = trim($input['source'] ?? '');
            $location = trim($input['location'] ?? '');
            $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;

            $insertSql = "
                INSERT INTO contacts (
                    organization_id, first_name, last_name, name, company_id, company_name, job_title,
                    email, phone, clean_phone, whatsapp, preferred_channel, linkedin, relationship, owner_id, source, location,
                    lead_id, is_active, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                $organizationId,
                $first !== '' ? $first : null,
                $last !== '' ? $last : null,
                $name,
                $companyId,
                $company !== '' ? $company : null,
                $jobTitle !== '' ? $jobTitle : null,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $cleanPhone !== '' ? $cleanPhone : null,
                $whatsapp !== '' ? $whatsapp : null,
                $preferredChannel !== '' ? $preferredChannel : null,
                $linkedin !== '' ? $linkedin : null,
                $relationship !== '' ? $relationship : null,
                $ownerId,
                $source !== '' ? $source : null,
                $location !== '' ? $location : null,
                $leadId,
                $currentUserId
            ]);

            $newId = (int)$pdo->lastInsertId();
            $code = 'CNT-' . str_pad($newId, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE contacts SET contact_code = ? WHERE id = ?")->execute([$code, $newId]);

            // Authoritative junction insertion
            if ($companyId > 0) {
                $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (?, ?, ?)")
                    ->execute([$organizationId, $newId, $companyId]);
            }

            // If Set as Primary Contact is checked and contact has a company, assign primary_contact_id
            if ($isPrimary && $companyId > 0) {
                $pdo->prepare("UPDATE companies SET primary_contact_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
                    ->execute([$newId, $companyId, $organizationId]);
            }

            log_contact_activity($pdo, $organizationId, $currentUserId, $newId, 'Create', "Created contact {$name}");

            $pdo->commit();

            contacts_json(true, 'Contact created successfully.', ['id' => $newId, 'code' => $code]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            contacts_json(false, 'Failed to create contact: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------
    // 4. UPDATE CONTACT
    // -------------------------------------------------------------
    case 'update':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied. You cannot edit contacts.', [], 403);
        }

        $contactId = (int)($input['id'] ?? 0);
        if ($contactId <= 0) {
            contacts_json(false, 'Invalid contact ID.', [], 400);
        }

        // Verify existence in current org
        $chk = $pdo->prepare("SELECT id, name, company_id, company_name, owner_id, source, location, preferred_channel, linkedin FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            $name = $existing['name'];
        }

        $pdo->beginTransaction();
        try {
            // Resolve owner for company if a new company is created during update
            $ownerId = isset($input['owner_id']) ? (!empty($input['owner_id']) ? (int)$input['owner_id'] : null) : (!empty($existing['owner_id']) ? (int)$existing['owner_id'] : null);
            if ($ownerId) {
                $checkOwner = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
                $checkOwner->execute([$ownerId, $organizationId]);
                if (!$checkOwner->fetchColumn()) {
                    $ownerId = null;
                }
            }

            $isPrimaryPassed = isset($input['is_primary']);
            $isPrimary = !empty($input['is_primary']);

            $company = trim($input['company_name'] ?? ($input['company'] ?? ''));
            $companyId = isset($input['company_id']) ? (!empty($input['company_id']) ? (int)$input['company_id'] : null) : null;

            // Organization-scoped company resolution
            if ($companyId > 0) {
                $chkComp = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND organization_id = ? AND is_active = 1 LIMIT 1");
                $chkComp->execute([$companyId, $organizationId]);
                $compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
                if (!$compRow) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    contacts_json(false, 'The selected company does not exist, is inactive, or does not belong to your organization.', [], 422);
                }
                $companyId = (int)$compRow['id'];
                $company = $compRow['name'];
            } elseif ($company !== '') {
                // Server-side normalized duplicate check within current organization
                $chkComp = $pdo->prepare("
                    SELECT id, name 
                    FROM companies 
                    WHERE organization_id = ? 
                      AND LOWER(TRIM(name)) = LOWER(TRIM(?)) 
                      AND is_active = 1 
                    LIMIT 1
                ");
                $chkComp->execute([$organizationId, $company]);
                $compRow = $chkComp->fetch(PDO::FETCH_ASSOC);
                if ($compRow) {
                    $companyId = (int)$compRow['id'];
                    $company = $compRow['name'];
                } else {
                    // Pending company creation within the same atomic transaction
                    $seqStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM companies WHERE organization_id = ?");
                    $seqStmt->execute([$organizationId]);
                    $nextCompId = (int)$seqStmt->fetchColumn();
                    $compCode = 'COMP-' . str_pad($nextCompId, 6, '0', STR_PAD_LEFT);

                    $insComp = $pdo->prepare("
                        INSERT INTO companies (
                            organization_id, company_code, name, is_active, created_by, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, 1, ?, NOW(), NOW()
                        )
                    ");
                    $insComp->execute([
                        $organizationId,
                        $compCode,
                        $company,
                        $currentUserId
                    ]);
                    $companyId = (int)$pdo->lastInsertId();

                    // Log company creation activity
                    $actStmt = $pdo->prepare("
                        INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
                        VALUES (?, ?, 'company_created', ?, ?, 'companies', ?, ?, NOW())
                    ");
                    $actStmt->execute([
                        $organizationId,
                        $currentUserId,
                        'Company created: ' . $company,
                        "Company {$company} ({$compCode}) created by " . ($currentUser['name'] ?? 'User'),
                        $companyId,
                        $currentUserId
                    ]);
                }
            } else {
                $companyId = null;
                $company = null;
            }

            // If contact is assigned to a company, ensure relationship exists in contact_companies
            if ($companyId > 0) {
                $pdo->prepare("INSERT IGNORE INTO contact_companies (organization_id, contact_id, company_id) VALUES (?, ?, ?)")
                    ->execute([$organizationId, $contactId, $companyId]);
            }

            // Update primary contact on this company if is_primary was passed
            if ($companyId > 0 && $isPrimaryPassed) {
                if ($isPrimary) {
                    $pdo->prepare("UPDATE companies SET primary_contact_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
                        ->execute([$contactId, $companyId, $organizationId]);
                } else {
                    $pdo->prepare("UPDATE companies SET primary_contact_id = NULL, updated_at = NOW() WHERE id = ? AND primary_contact_id = ? AND organization_id = ?")
                        ->execute([$companyId, $contactId, $organizationId]);
                }
            }

            $jobTitle = trim($input['job_title'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $relationship = trim($input['relationship'] ?? '');

            // Data-preservation rules for owner_id, source, location
            if (array_key_exists('owner_id', $input)) {
                $rawOwner = $input['owner_id'];
                if ($rawOwner !== null && $rawOwner !== '') {
                    $valOwnerId = (int)$rawOwner;
                    $chkOwner = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
                    $chkOwner->execute([$valOwnerId, $organizationId]);
                    $savedOwnerId = $chkOwner->fetchColumn() ? $valOwnerId : null;
                } else {
                    $savedOwnerId = null;
                }
            } else {
                $savedOwnerId = !empty($existing['owner_id']) ? (int)$existing['owner_id'] : null;
            }

            if (array_key_exists('source', $input)) {
                $rawSource = trim($input['source'] ?? '');
                $savedSource = $rawSource !== '' ? $rawSource : null;
            } else {
                $savedSource = ($existing['source'] !== null && $existing['source'] !== '') ? $existing['source'] : null;
            }

            if (array_key_exists('location', $input)) {
                $rawLoc = trim($input['location'] ?? '');
                $savedLocation = $rawLoc !== '' ? $rawLoc : null;
            } else {
                $savedLocation = ($existing['location'] !== null && $existing['location'] !== '') ? $existing['location'] : null;
            }

            if (array_key_exists('preferred_channel', $input)) {
                $rawPrefChannel = trim($input['preferred_channel'] ?? '');
                $savedPreferredChannel = $rawPrefChannel !== '' ? $rawPrefChannel : null;
            } elseif (array_key_exists('preferredChannel', $input)) {
                $rawPrefChannel = trim($input['preferredChannel'] ?? '');
                $savedPreferredChannel = $rawPrefChannel !== '' ? $rawPrefChannel : null;
            } else {
                $savedPreferredChannel = ($existing['preferred_channel'] !== null && $existing['preferred_channel'] !== '') ? $existing['preferred_channel'] : null;
            }

            if (array_key_exists('linkedin', $input)) {
                $rawLinkedin = trim($input['linkedin'] ?? '');
                $savedLinkedin = $rawLinkedin !== '' ? $rawLinkedin : null;
            } else {
                $savedLinkedin = ($existing['linkedin'] !== null && $existing['linkedin'] !== '') ? $existing['linkedin'] : null;
            }

            // Preserve legacy company_id if already set, or initialize if newly assigned
            $existingCompId = !empty($existing['company_id']) ? (int)$existing['company_id'] : null;
            $savedCompanyId = $existingCompId ?: ($companyId ?: null);
            $savedCompanyName = $existingCompId ? ($existing['company_name'] ?? null) : ($company !== '' ? $company : null);

            $updateSql = "
                UPDATE contacts
                SET name = ?,
                    company_id = ?,
                    company_name = ?,
                    job_title = ?,
                    email = ?,
                    phone = ?,
                    clean_phone = ?,
                    preferred_channel = ?,
                    linkedin = ?,
                    relationship = ?,
                    owner_id = ?,
                    source = ?,
                    location = ?
                WHERE id = ? AND organization_id = ?
            ";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                $name,
                $savedCompanyId,
                $savedCompanyName,
                $jobTitle !== '' ? $jobTitle : null,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $cleanPhone !== '' ? $cleanPhone : null,
                $savedPreferredChannel,
                $savedLinkedin,
                $relationship !== '' ? $relationship : null,
                $savedOwnerId,
                $savedSource,
                $savedLocation,
                $contactId,
                $organizationId
            ]);

            log_contact_activity($pdo, $organizationId, $currentUserId, $contactId, 'Update', "Updated contact details for {$name}");

            $pdo->commit();

            contacts_json(true, 'Contact updated successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            contacts_json(false, 'Failed to update contact: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------
    // 5. CHANGE RELATIONSHIP
    // -------------------------------------------------------------
    case 'change_relationship':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $contactId = (int)($input['id'] ?? 0);
        $newRel = trim($input['relationship'] ?? '');
        if ($contactId <= 0 || $newRel === '') {
            contacts_json(false, 'Invalid parameters.', [], 400);
        }

        $stmt = $pdo->prepare("UPDATE contacts SET relationship = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $stmt->execute([$newRel, $contactId, $organizationId]);

        log_contact_activity($pdo, $organizationId, $currentUserId, $contactId, 'Relationship', "Changed relationship to {$newRel}");

        contacts_json(true, 'Relationship updated successfully.');
        break;

    // -------------------------------------------------------------
    // 6. CHANGE OWNER
    // -------------------------------------------------------------
    case 'change_owner':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $contactId = (int)($input['id'] ?? 0);
        $ownerId = !empty($input['owner_id']) ? (int)$input['owner_id'] : null;

        // If owner was supplied as name string
        if (!$ownerId && !empty($input['owner'])) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND organization_id = ? AND status = 'active' LIMIT 1");
            $uStmt->execute([trim($input['owner']), $organizationId]);
            $foundUid = $uStmt->fetchColumn();
            if ($foundUid) $ownerId = (int)$foundUid;
        }

        if ($ownerId) {
            $checkOwner = $pdo->prepare("SELECT name FROM users WHERE id = ? AND organization_id = ? AND status = 'active'");
            $checkOwner->execute([$ownerId, $organizationId]);
            $ownerName = $checkOwner->fetchColumn();
            if (!$ownerName) {
                contacts_json(false, 'Invalid owner selected.', [], 400);
            }
        } else {
            $ownerName = 'Unassigned';
            $ownerId = null;
        }

        $stmt = $pdo->prepare("UPDATE contacts SET owner_id = ? WHERE id = ? AND organization_id = ?");
        $stmt->execute([$ownerId, $contactId, $organizationId]);

        log_contact_activity($pdo, $organizationId, $currentUserId, $contactId, 'Owner', "Reassigned contact to {$ownerName}");

        contacts_json(true, 'Owner updated successfully.', ['owner' => $ownerName]);
        break;

    // -------------------------------------------------------------
    // 7. TOGGLE ACTIVE
    // -------------------------------------------------------------
    case 'toggle_active':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $contactId = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT is_active, relationship FROM contacts WHERE id = ? AND organization_id = ?");
        $stmt->execute([$contactId, $organizationId]);
        $cur = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cur) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        if (isset($input['is_active'])) {
            $newActive = (int)$input['is_active'] ? 1 : 0;
        } else {
            $newActive = $cur['is_active'] ? 0 : 1;
        }

        $upd = $pdo->prepare("UPDATE contacts SET is_active = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upd->execute([$newActive, $contactId, $organizationId]);

        $msg = $newActive === 1 ? 'Contact marked as active.' : 'Contact marked as inactive.';
        contacts_json(true, $msg, ['isActive' => (bool)$newActive, 'relationship' => $cur['relationship']]);
        break;

    // -------------------------------------------------------------
    // 8. DELETE CONTACT (TRANSACTIONAL)
    // -------------------------------------------------------------
    case 'delete':
        if (!$canDelete) {
            contacts_json(false, 'Permission denied. You cannot delete contacts.', [], 403);
        }

        $contactId = (int)($input['id'] ?? 0);
        if ($contactId <= 0) {
            contacts_json(false, 'Invalid contact ID.', [], 400);
        }

        // Verify existence in current org
        $chk = $pdo->prepare("SELECT id, name FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $pdo->beginTransaction();
        try {
            // 1. Delete polymorphic tasks
            $pdo->prepare("DELETE FROM tasks WHERE related_type = 'contacts' AND related_id = ? AND organization_id = ?")->execute([$contactId, $organizationId]);

            // 2. Delete polymorphic activities
            $pdo->prepare("DELETE FROM team_activities WHERE related_entity = 'contacts' AND related_entity_id = ? AND organization_id = ?")->execute([$contactId, $organizationId]);

            // 3. Delete polymorphic notes
            $pdo->prepare("DELETE FROM team_notes WHERE related_type = 'contacts' AND related_id = ? AND organization_id = ?")->execute([$contactId, $organizationId]);

            // 4. Delete polymorphic calendar events
            $pdo->prepare("DELETE FROM calendar_events WHERE related_type = 'contacts' AND related_id = ? AND organization_id = ?")->execute([$contactId, $organizationId]);

            // 5. Unlink associated deals
            $pdo->prepare("UPDATE deals SET contact_id = NULL WHERE contact_id = ? AND organization_id = ?")->execute([$contactId, $organizationId]);

            // 6. Clear company primary_contact_id if this was the primary contact
            $pdo->prepare("UPDATE companies SET primary_contact_id = NULL, updated_at = NOW() WHERE primary_contact_id = ? AND organization_id = ?")->execute([$contactId, $organizationId]);

            // 7. Delete contact record
            $pdo->prepare("DELETE FROM contacts WHERE id = ? AND organization_id = ?")->execute([$contactId, $organizationId]);

            $pdo->commit();
            contacts_json(true, "Contact '{$existing['name']}' deleted successfully.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            contacts_json(false, 'Failed to delete contact: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------
    // 9. BULK DELETE
    // -------------------------------------------------------------
    case 'delete_bulk':
        if (!$canDelete) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $ids = $input['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            contacts_json(false, 'No contact IDs provided.', [], 400);
        }

        $deletedCount = 0;
        $pdo->beginTransaction();
        try {
            foreach ($ids as $cid) {
                $cInt = (int)$cid;
                if ($cInt <= 0) continue;

                // Verify org
                $chk = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ?");
                $chk->execute([$cInt, $organizationId]);
                if (!$chk->fetchColumn()) continue;

                $pdo->prepare("DELETE FROM tasks WHERE related_type = 'contacts' AND related_id = ? AND organization_id = ?")->execute([$cInt, $organizationId]);
                $pdo->prepare("DELETE FROM team_activities WHERE related_entity = 'contacts' AND related_entity_id = ? AND organization_id = ?")->execute([$cInt, $organizationId]);
                $pdo->prepare("DELETE FROM team_notes WHERE related_type = 'contacts' AND related_id = ? AND organization_id = ?")->execute([$cInt, $organizationId]);
                $pdo->prepare("DELETE FROM calendar_events WHERE related_type = 'contacts' AND related_id = ? AND organization_id = ?")->execute([$cInt, $organizationId]);
                $pdo->prepare("UPDATE deals SET contact_id = NULL WHERE contact_id = ? AND organization_id = ?")->execute([$cInt, $organizationId]);
                $pdo->prepare("UPDATE companies SET primary_contact_id = NULL, updated_at = NOW() WHERE primary_contact_id = ? AND organization_id = ?")->execute([$cInt, $organizationId]);
                $pdo->prepare("DELETE FROM contacts WHERE id = ? AND organization_id = ?")->execute([$cInt, $organizationId]);

                $deletedCount++;
            }
            $pdo->commit();
            contacts_json(true, "{$deletedCount} contacts deleted successfully.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            contacts_json(false, 'Failed bulk delete: ' . $e->getMessage(), [], 500);
        }
        break;

    // -------------------------------------------------------------
    // 10. DRAWER DATA (OVERVIEW, ACTIVITIES, DEALS, TASKS, NOTES)
    // -------------------------------------------------------------
    case 'drawer_data':
        $contactId = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($contactId <= 0) {
            contacts_json(false, 'Invalid contact ID.', [], 400);
        }

        // Fetch contact details
        $cStmt = $pdo->prepare("
            SELECT c.*, u.name AS owner_name, u.email AS owner_email,
                (CASE WHEN c.company_id IS NOT NULL AND comp.primary_contact_id = c.id THEN 1 ELSE 0 END) AS is_primary
            FROM contacts c
            LEFT JOIN users u ON c.owner_id = u.id
            LEFT JOIN companies comp ON comp.id = c.company_id AND comp.organization_id = c.organization_id
            WHERE c.id = ? AND c.organization_id = ?
        ");
        $cStmt->execute([$contactId, $organizationId]);
        $row = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $assocComps = get_contact_companies_batch($pdo, $organizationId, [$contactId])[$contactId] ?? [];
        $compNames = array_column($assocComps, 'name');
        $primaryCompanyNames = [];
        foreach ($assocComps as $ac) {
            if ($ac['isPrimary']) $primaryCompanyNames[] = $ac['name'];
        }
        $isPrimary = !empty($primaryCompanyNames) || !empty($row['is_primary']);
        $primaryCompanyStr = !empty($primaryCompanyNames) ? implode(', ', $primaryCompanyNames) : ($row['company_name'] ?: '');
        $displayCompany = !empty($compNames) ? implode(', ', $compNames) : ($row['company_name'] ?: '');
        $firstCompanyId = !empty($assocComps) ? $assocComps[0]['id'] : ($row['company_id'] ? (int)$row['company_id'] : null);

        $ownerName = $row['owner_name'] ?: 'Unassigned';
        $ownerId = (int)($row['owner_id'] ?? 0);

        // Format contact object
        $contact = [
            'id'                => (int)$row['id'],
            'code'              => $row['contact_code'] ?: ('CNT-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT)),
            'firstName'         => $row['first_name'] ?: '',
            'lastName'          => $row['last_name'] ?: '',
            'name'              => $row['name'],
            'companyId'         => $firstCompanyId,
            'company'           => $displayCompany,
            'companies'         => $assocComps,
            'companiesCount'    => count($assocComps),
            'isPrimary'         => $isPrimary,
            'primaryCompanyName'=> $primaryCompanyStr,
            'jobTitle'          => $row['job_title'] ?: '',
            'email'             => $row['email'] ?: '',
            'phone'             => $row['phone'] ?: '',
            'cleanPhone'        => $row['clean_phone'] ?: preg_replace('/[^0-9]/', '', $row['phone'] ?: ''),
            'whatsapp'          => $row['whatsapp'] ?: '',
            'preferredChannel'  => $row['preferred_channel'] ?: '',
            'preferred_channel' => $row['preferred_channel'] ?: '',
            'linkedin'          => $row['linkedin'] ?: '',
            'relationship'      => $row['relationship'] ?: 'Prospect',
            'owner'             => $ownerName,
            'ownerId'           => $ownerId ?: null,
            'ownerInitials'     => $ownerId ? get_contact_initials($ownerName) : 'UN',
            'ownerColor'        => $ownerId ? get_owner_color($ownerId) : '#94A3B8',
            'source'            => $row['source'] ?: '',
            'location'          => $row['location'] ?: '',
            'leadId'            => $row['lead_id'] ? (int)$row['lead_id'] : null,
            'isActive'          => (bool)$row['is_active'],
            'createdAt'         => date('M d, Y', strtotime($row['created_at']))
        ];

        // Fetch activities
        $actStmt = $pdo->prepare("
            SELECT a.id, a.activity_type, a.title, a.description, a.created_at, u.name AS user_name
            FROM team_activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.organization_id = ? AND a.related_entity = 'contacts' AND a.related_entity_id = ?
            ORDER BY a.created_at DESC
        ");
        $actStmt->execute([$organizationId, $contactId]);
        $activities = [];
        foreach ($actStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $activities[] = [
                'id'    => (int)$a['id'],
                'user'  => $a['user_name'] ?: 'User',
                'type'  => $a['activity_type'],
                'title' => $a['title'],
                'text'  => $a['description'] ?: $a['title'],
                'date'  => date('M d, Y • g:i A', strtotime($a['created_at']))
            ];
        }

        // Fetch associated deals
        $dealStmt = $pdo->prepare("
            SELECT id, name, value, stage, probability, close_date, status
            FROM deals
            WHERE organization_id = ? AND (contact_id = ? OR (contact_id IS NULL AND LOWER(TRIM(contact_name)) = LOWER(TRIM(?))))
            ORDER BY created_at DESC
        ");
        $dealStmt->execute([$organizationId, $contactId, $contact['name']]);
        $deals = [];
        foreach ($dealStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $deals[] = [
                'id'        => (int)$d['id'],
                'name'      => $d['name'],
                'value'     => (float)$d['value'],
                'stage'     => $d['stage'],
                'prob'      => $d['probability'] !== null ? (float)$d['probability'] : null,
                'closeDate' => $d['close_date'] ? date('M d, Y', strtotime($d['close_date'])) : 'Open',
                'status'    => $d['status']
            ];
        }

        // Fetch tasks
        $taskStmt = $pdo->prepare("
            SELECT t.id, t.title, t.priority, t.due_date, t.status, u.name AS assignee
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.organization_id = ? AND t.related_type = 'contacts' AND t.related_id = ?
            ORDER BY t.created_at DESC
        ");
        $taskStmt->execute([$organizationId, $contactId]);
        $tasks = [];
        foreach ($taskStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $rawDueDate = $t['due_date'] ? date('Y-m-d', strtotime($t['due_date'])) : null;
            $tasks[] = [
                'id'        => (int)$t['id'],
                'title'     => $t['title'],
                'priority'  => ucfirst(strtolower($t['priority'] ?: 'medium')),
                'due_date'  => $rawDueDate,
                'dueDate'   => $t['due_date'] ? date('M d, Y', strtotime($t['due_date'])) : 'No due date',
                'completed' => $t['status'] === 'completed'
            ];
        }

        // Fetch notes
        $noteStmt = $pdo->prepare("
            SELECT n.id, n.content, n.is_pinned, n.created_at, u.name AS author_name
            FROM team_notes n
            LEFT JOIN users u ON n.created_by = u.id
            WHERE n.organization_id = ? AND n.related_type = 'contacts' AND n.related_id = ?
            ORDER BY n.is_pinned DESC, n.created_at DESC
        ");
        $noteStmt->execute([$organizationId, $contactId]);
        $notes = [];
        foreach ($noteStmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
            $notes[] = [
                'id'      => (int)$n['id'],
                'author'  => $n['author_name'] ?: 'User',
                'text'    => $n['content'],
                'content' => $n['content'],
                'pinned'  => (bool)$n['is_pinned'],
                'date'    => date('M d, Y • g:i A', strtotime($n['created_at']))
            ];
        }

        $contact['activities'] = $activities;
        $contact['deals'] = $deals;
        $contact['tasks'] = $tasks;
        $contact['notes'] = $notes;

        contacts_json(true, 'Contact drawer data loaded.', ['contact' => $contact]);
        break;

    // -------------------------------------------------------------
    // 11. LOG ACTIVITY
    // -------------------------------------------------------------
    case 'log_activity':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $contactId = (int)($input['contact_id'] ?? 0);
        $type = trim($input['type'] ?? 'Call');
        $summary = trim($input['summary'] ?? ($input['text'] ?? ''));

        if ($contactId <= 0 || $summary === '') {
            contacts_json(false, 'Activity summary is required.', [], 400);
        }

        // Verify contact belongs to org
        $chk = $pdo->prepare("SELECT name FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        $cName = $chk->fetchColumn();
        if (!$cName) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $stmt = $pdo->prepare("
            INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by)
            VALUES (?, ?, ?, ?, ?, 'contacts', ?, ?)
        ");
        $stmt->execute([$organizationId, $currentUserId, $type, "{$type}: {$summary}", $summary, $contactId, $currentUserId]);

        // Touch contact updated_at
        $pdo->prepare("UPDATE contacts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$contactId]);

        contacts_json(true, 'Activity logged successfully.');
        break;

    // -------------------------------------------------------------
    // 12. ADD TASK
    // -------------------------------------------------------------
    case 'add_task':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $contactId = (int)($input['contact_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $priority = strtolower(trim($input['priority'] ?? 'medium'));
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            $priority = 'medium';
        }
        $dueDate = !empty($input['due_date']) ? date('Y-m-d H:i:s', strtotime($input['due_date'])) : null;

        if ($contactId <= 0 || $title === '') {
            contacts_json(false, 'Task title is required.', [], 400);
        }

        // Verify contact
        $chk = $pdo->prepare("SELECT name, company_id FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        $contactRow = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$contactRow) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tasks (organization_id, title, priority, status, due_date, assigned_to, contact_id, company_id, related_type, related_id, created_at, updated_at)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, 'contacts', ?, NOW(), NOW())
        ");
        $stmt->execute([$organizationId, $title, $priority, $dueDate, $currentUserId, $contactId, $contactRow['company_id'] ?: null, $contactId]);

        log_contact_activity($pdo, $organizationId, $currentUserId, $contactId, 'Task', "Added task: {$title}");

        contacts_json(true, 'Task created successfully.');
        break;

    // -------------------------------------------------------------
    // 13. EDIT TASK
    // -------------------------------------------------------------
    case 'edit_task':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $taskId = (int)($input['task_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $priority = strtolower(trim($input['priority'] ?? 'medium'));
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            $priority = 'medium';
        }
        $dueDate = !empty($input['due_date']) ? date('Y-m-d H:i:s', strtotime($input['due_date'])) : null;

        if ($taskId <= 0 || $title === '') {
            contacts_json(false, 'Task title is required.', [], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET title = ?, priority = ?, due_date = ?
            WHERE id = ? AND organization_id = ? AND related_type = 'contacts'
        ");
        $stmt->execute([$title, $priority, $dueDate, $taskId, $organizationId]);

        contacts_json(true, 'Task updated successfully.');
        break;

    // -------------------------------------------------------------
    // 14. TOGGLE TASK STATUS
    // -------------------------------------------------------------
    case 'toggle_task':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $taskId = (int)($input['task_id'] ?? 0);
        $completed = !empty($input['completed']);

        $status = $completed ? 'completed' : 'pending';
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET status = ?
            WHERE id = ? AND organization_id = ? AND related_type = 'contacts'
        ");
        $stmt->execute([$status, $taskId, $organizationId]);

        contacts_json(true, 'Task status updated.', ['status' => $status]);
        break;

    // -------------------------------------------------------------
    // 15. DELETE TASK
    // -------------------------------------------------------------
    case 'delete_task':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $taskId = (int)($input['task_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND organization_id = ? AND related_type = 'contacts'");
        $stmt->execute([$taskId, $organizationId]);

        contacts_json(true, 'Task deleted successfully.');
        break;

    // -------------------------------------------------------------
    // 16. ADD NOTE
    // -------------------------------------------------------------
    case 'add_note':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $contactId = (int)($input['contact_id'] ?? 0);
        $content = trim($input['content'] ?? ($input['text'] ?? ''));
        $pinned = !empty($input['is_pinned']) || !empty($input['pinned']) ? 1 : 0;

        if ($contactId <= 0 || $content === '') {
            contacts_json(false, 'Note content is required.', [], 400);
        }

        // Verify contact
        $chk = $pdo->prepare("SELECT name FROM contacts WHERE id = ? AND organization_id = ?");
        $chk->execute([$contactId, $organizationId]);
        if (!$chk->fetchColumn()) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $stmt = $pdo->prepare("
            INSERT INTO team_notes (organization_id, related_type, related_id, content, is_pinned, created_by)
            VALUES (?, 'contacts', ?, ?, ?, ?)
        ");
        $stmt->execute([$organizationId, $contactId, $content, $pinned, $currentUserId]);

        log_contact_activity($pdo, $organizationId, $currentUserId, $contactId, 'Note', 'Added a note');

        contacts_json(true, 'Note added successfully.');
        break;

    // -------------------------------------------------------------
    // 17. EDIT NOTE
    // -------------------------------------------------------------
    case 'edit_note':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $noteId = (int)($input['note_id'] ?? 0);
        $content = trim($input['content'] ?? ($input['text'] ?? ''));
        $pinned = !empty($input['is_pinned']) || !empty($input['pinned']) ? 1 : 0;

        if ($noteId <= 0 || $content === '') {
            contacts_json(false, 'Note content is required.', [], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE team_notes
            SET content = ?, is_pinned = ?
            WHERE id = ? AND organization_id = ? AND related_type = 'contacts'
        ");
        $stmt->execute([$content, $pinned, $noteId, $organizationId]);

        contacts_json(true, 'Note updated successfully.');
        break;

    // -------------------------------------------------------------
    // 18. TOGGLE PIN NOTE
    // -------------------------------------------------------------
    case 'toggle_pin_note':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $noteId = (int)($input['note_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT is_pinned FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'contacts'");
        $stmt->execute([$noteId, $organizationId]);
        $curPin = $stmt->fetchColumn();

        if ($curPin === false) {
            contacts_json(false, 'Note not found.', [], 404);
        }

        $newPin = $curPin ? 0 : 1;
        $pdo->prepare("UPDATE team_notes SET is_pinned = ? WHERE id = ? AND organization_id = ?")->execute([$newPin, $noteId, $organizationId]);

        contacts_json(true, 'Note pin toggled.', ['pinned' => (bool)$newPin]);
        break;

    // -------------------------------------------------------------
    // 19. DELETE NOTE
    // -------------------------------------------------------------
    case 'delete_note':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $noteId = (int)($input['note_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'contacts'");
        $stmt->execute([$noteId, $organizationId]);

        contacts_json(true, 'Note deleted successfully.');
        break;

    // -------------------------------------------------------------
    // 20. SCHEDULE MEETING (CALENDAR EVENT)
    // -------------------------------------------------------------
    case 'schedule_meeting':
        if (!$canEdit) {
            contacts_json(false, 'Permission denied.', [], 403);
        }

        $contactId = (int)($input['contact_id'] ?? 0);
        $title = trim($input['title'] ?? 'Meeting');
        $date = trim($input['date'] ?? '');
        $time = trim($input['time'] ?? '10:00');
        $duration = (int)($input['duration'] ?? 30);
        $desc = trim($input['description'] ?? '');

        if ($contactId <= 0 || $title === '' || $date === '') {
            contacts_json(false, 'Meeting title and date are required.', [], 400);
        }

        // Verify contact belongs to tenant
        $cStmt = $pdo->prepare("SELECT id, name, company_id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
        $cStmt->execute([$contactId, $organizationId]);
        $contactRow = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contactRow) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        // Resolve company_id from input, or contact_companies, or contactRow
        $resolvedCompanyId = null;
        if (!empty($input['company_id'])) {
            $compCheck = $pdo->prepare("SELECT company_id FROM contact_companies WHERE contact_id = ? AND company_id = ? AND organization_id = ? LIMIT 1");
            $compCheck->execute([$contactId, (int)$input['company_id'], $organizationId]);
            if ($compCheck->fetchColumn()) {
                $resolvedCompanyId = (int)$input['company_id'];
            }
        }
        if (!$resolvedCompanyId) {
            $ccStmt = $pdo->prepare("SELECT company_id FROM contact_companies WHERE contact_id = ? AND organization_id = ? ORDER BY id ASC LIMIT 1");
            $ccStmt->execute([$contactId, $organizationId]);
            $resolvedCompanyId = $ccStmt->fetchColumn() ?: null;
        }
        if (!$resolvedCompanyId && !empty($contactRow['company_id'])) {
            $resolvedCompanyId = (int)$contactRow['company_id'];
        }

        $startStr = "{$date} {$time}:00";
        $startTime = date('Y-m-d H:i:s', strtotime($startStr));
        $endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($duration * 60));

        $stmt = $pdo->prepare("
            INSERT INTO calendar_events (
                organization_id, title, description, event_type, start_time, end_time, user_id, contact_id, company_id, related_type, related_id, status, created_at, updated_at
            ) VALUES (?, ?, ?, 'Meeting', ?, ?, ?, ?, ?, 'contacts', ?, 'scheduled', NOW(), NOW())
        ");
        $stmt->execute([$organizationId, $title, $desc, $startTime, $endTime, $currentUserId, $contactId, $resolvedCompanyId ? (int)$resolvedCompanyId : null, $contactId]);
        $meetId = (int)$pdo->lastInsertId();

        log_contact_activity($pdo, $organizationId, $currentUserId, $contactId, 'Meeting', "Scheduled meeting: {$title} on {$date} at {$time}");

        contacts_json(true, 'Meeting scheduled successfully.', ['id' => $meetId]);
        break;

    // -------------------------------------------------------------
    // 21. CREATE DEAL FROM CONTACT
    // -------------------------------------------------------------
    case 'create_deal':
        $contactId = (int)($input['contact_id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $val = (float)($input['value'] ?? 0);
        $stage = trim($input['stage'] ?? 'Prospect');
        $prob = isset($input['probability']) ? (float)$input['probability'] : null;
        $closeDate = !empty($input['close_date']) ? date('Y-m-d', strtotime($input['close_date'])) : null;

        if ($contactId <= 0 || $name === '') {
            contacts_json(false, 'Deal name and valid contact are required.', [], 400);
        }

        // Fetch contact details
        $cStmt = $pdo->prepare("SELECT name, company_name, owner_id FROM contacts WHERE id = ? AND organization_id = ?");
        $cStmt->execute([$contactId, $organizationId]);
        $c = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            contacts_json(false, 'Contact not found.', [], 404);
        }

        $dealCode = 'DL-' . strtoupper(substr(md5(uniqid()), 0, 6));

        $stmt = $pdo->prepare("
            INSERT INTO deals (
                organization_id, lead_id, contact_id, deal_code, name, contact_name, company, stage, value,
                probability, source, priority, status, assigned_to, close_date
            ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'Contact Drawer', 'Medium', 'open', ?, ?)
        ");
        $stmt->execute([
            $organizationId,
            $contactId,
            $dealCode,
            $name,
            $c['name'],
            $c['company_name'],
            $stage,
            $val,
            $prob,
            $c['owner_id'] ?: $currentUserId,
            $closeDate
        ]);

        $dealId = (int)$pdo->lastInsertId();
        log_contact_activity($pdo, $organizationId, $currentUserId, $contactId, 'Deal', "Created deal '{$name}' valued at $" . number_format($val, 2));

        contacts_json(true, 'Deal created successfully.', ['id' => $dealId]);
        break;

    // -------------------------------------------------------------
    // 22. EXPORT CSV
    // -------------------------------------------------------------
    case 'export_csv':
        if (!$canExport) {
            contacts_json(false, 'Permission denied. You cannot export contacts.', [], 403);
        }

        $stmt = $pdo->prepare("
            SELECT 
                c.contact_code,
                c.name,
                c.job_title,
                c.company_name,
                c.relationship,
                c.email,
                c.phone,
                u.name AS owner_name,
                c.source,
                c.location,
                c.created_at
            FROM contacts c
            LEFT JOIN users u ON c.owner_id = u.id
            WHERE c.organization_id = ?
            ORDER BY c.name ASC
        ");
        $stmt->execute([$organizationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="NexFlow_Contacts_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Contact Code', 'Name', 'Job Title', 'Company', 'Relationship', 'Email', 'Phone', 'Owner', 'Source', 'Location', 'Created Date']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['contact_code'] ?: '',
                $r['name'] ?: '',
                $r['job_title'] ?: '',
                $r['company_name'] ?: '',
                $r['relationship'] ?: '',
                $r['email'] ?: '',
                $r['phone'] ?: '',
                $r['owner_name'] ?: 'Unassigned',
                $r['source'] ?: '',
                $r['location'] ?: '',
                date('Y-m-d', strtotime($r['created_at']))
            ]);
        }
        fclose($out);
        exit;

    // -------------------------------------------------------------
    // 23. IMPORT CSV
    // -------------------------------------------------------------
    case 'import_csv':
        if (!$canCreate) {
            contacts_json(false, 'Permission denied. You cannot import contacts.', [], 403);
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            contacts_json(false, 'No valid CSV file was uploaded.', [], 400);
        }

        $tmpPath = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            contacts_json(false, 'Could not open uploaded file.', [], 500);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            contacts_json(false, 'Uploaded CSV is empty.', [], 400);
        }

        // Map column indices
        $map = [];
        foreach ($header as $idx => $col) {
            $c = strtolower(trim($col));
            if (strpos($c, 'first') !== false) $map['first_name'] = $idx;
            elseif (strpos($c, 'last') !== false) $map['last_name'] = $idx;
            elseif (strpos($c, 'name') !== false && !isset($map['name'])) $map['name'] = $idx;
            elseif (strpos($c, 'company') !== false) $map['company'] = $idx;
            elseif (strpos($c, 'title') !== false || strpos($c, 'job') !== false) $map['job_title'] = $idx;
            elseif (strpos($c, 'email') !== false) $map['email'] = $idx;
            elseif (strpos($c, 'phone') !== false) $map['phone'] = $idx;
            elseif (strpos($c, 'rel') !== false) $map['relationship'] = $idx;
            elseif (strpos($c, 'owner') !== false) $map['owner'] = $idx;
            elseif (strpos($c, 'source') !== false) $map['source'] = $idx;
            elseif (strpos($c, 'loc') !== false) $map['location'] = $idx;
        }

        // Cache active users in org
        $uStmt = $pdo->prepare("SELECT id, LOWER(name) AS l_name, LOWER(email) AS l_email FROM users WHERE organization_id = ? AND status = 'active'");
        $uStmt->execute([$organizationId]);
        $orgUsers = $uStmt->fetchAll(PDO::FETCH_ASSOC);

        $insertedCount = 0;
        $rowNum = 1;
        $errors = [];

        $insertStmt = $pdo->prepare("
            INSERT INTO contacts (
                organization_id, first_name, last_name, name, company_name, job_title,
                email, phone, clean_phone, relationship, owner_id, source, location,
                is_active, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($data))) continue; // skip empty line

            $first = isset($map['first_name']) ? trim($data[$map['first_name']] ?? '') : '';
            $last = isset($map['last_name']) ? trim($data[$map['last_name']] ?? '') : '';
            $name = isset($map['name']) ? trim($data[$map['name']] ?? '') : '';
            if ($name === '') {
                $name = trim("{$first} {$last}");
            }
            if ($name === '') {
                $errors[] = "Row {$rowNum}: Name is required.";
                continue;
            }

            $company = isset($map['company']) ? trim($data[$map['company']] ?? '') : '';
            $jobTitle = isset($map['job_title']) ? trim($data[$map['job_title']] ?? '') : '';
            $email = isset($map['email']) ? trim($data[$map['email']] ?? '') : '';
            $phone = isset($map['phone']) ? trim($data[$map['phone']] ?? '') : '';
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $relationship = isset($map['relationship']) ? trim($data[$map['relationship']] ?? '') : 'Prospect';
            $source = isset($map['source']) ? trim($data[$map['source']] ?? '') : 'Import';
            $location = isset($map['location']) ? trim($data[$map['location']] ?? '') : '';

            // Match owner
            $ownerId = null;
            if (isset($map['owner'])) {
                $ownerVal = strtolower(trim($data[$map['owner']] ?? ''));
                if ($ownerVal !== '') {
                    foreach ($orgUsers as $u) {
                        if ($u['l_name'] === $ownerVal || $u['l_email'] === $ownerVal) {
                            $ownerId = (int)$u['id'];
                            break;
                        }
                    }
                }
            }

            $insertStmt->execute([
                $organizationId,
                $first !== '' ? $first : null,
                $last !== '' ? $last : null,
                $name,
                $company !== '' ? $company : null,
                $jobTitle !== '' ? $jobTitle : null,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $cleanPhone !== '' ? $cleanPhone : null,
                $relationship !== '' ? $relationship : 'Prospect',
                $ownerId,
                $source !== '' ? $source : null,
                $location !== '' ? $location : null,
                $currentUserId
            ]);

            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE contacts SET contact_code = ? WHERE id = ?")->execute(['CNT-' . str_pad($newId, 3, '0', STR_PAD_LEFT), $newId]);
            $insertedCount++;
        }

        fclose($handle);

        contacts_json(true, "Successfully imported {$insertedCount} contacts.", [
            'inserted' => $insertedCount,
            'errors'   => $errors
        ]);
        break;

    default:
        contacts_json(false, "Unknown action: '{$action}'.", [], 400);
        break;
}
