<?php
/**
 * NexFlow CRM — Documents Management API Controller
 *
 * Multi-tenant documents CRUD, real file storage, KPI aggregation,
 * authenticated streaming downloads, CSV export, and polymorphic
 * activity logging via team_activities.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// ── JSON response helper ─────────────────────────────────────────────────
function docs_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
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

// ── Auth guard ───────────────────────────────────────────────────────────
$currentUser = nexflow_current_user();
if (!$currentUser) {
    docs_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    docs_json(false, 'Invalid organization context.', [], 400);
}

// ── Permission helper ────────────────────────────────────────────────────
function require_doc_perm(string $action): void
{
    if (!hasPermission('documents', $action)) {
        docs_json(false, "Forbidden: You do not have permission to {$action} documents.", [], 403);
    }
}

$pdo = nexflow_db();

// ── Route on action ──────────────────────────────────────────────────────
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if (empty($action)) {
    docs_json(false, 'No action specified.', [], 400);
}

// ── Related Record Resolver & Validator ──────────────────────────────────
function resolve_and_validate_related_record(PDO $pdo, int $organizationId, string $relatedType, ?int $relatedId): array
{
    $res = [
        'project_id'     => null,
        'deal_id'        => null,
        'proposal_id'    => null,
        'contract_id'    => null,
        'invoice_id'     => null,
        'related_type'   => null,
        'related_id'     => null,
        'related_record' => null
    ];

    if (!$relatedType || !$relatedId) {
        return $res;
    }

    $t = strtolower($relatedType);

    if ($t === 'project') {
        $stmt = $pdo->prepare("SELECT id, project_code, name FROM projects WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmt->execute([$relatedId, $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            docs_json(false, 'Related project not found or does not belong to your organization.', [], 422);
        }
        $code = $row['project_code'] ?? '';
        $name = $row['name'] ?? '';
        $res['project_id']     = (int)$row['id'];
        $res['related_type']   = 'project';
        $res['related_id']     = (int)$row['id'];
        $res['related_record'] = trim(($code ? $code . ' — ' : '') . $name);
    } elseif ($t === 'deal') {
        $stmt = $pdo->prepare("SELECT id, deal_code, name FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmt->execute([$relatedId, $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            docs_json(false, 'Related deal not found or does not belong to your organization.', [], 422);
        }
        $code = $row['deal_code'] ?? '';
        $name = $row['name'] ?? '';
        $res['deal_id']        = (int)$row['id'];
        $res['related_type']   = 'deal';
        $res['related_id']     = (int)$row['id'];
        $res['related_record'] = trim(($code ? $code . ' — ' : '') . $name);
    } elseif ($t === 'proposal') {
        $stmt = $pdo->prepare("SELECT id, proposal_number, title FROM proposals WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmt->execute([$relatedId, $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            docs_json(false, 'Related proposal not found or does not belong to your organization.', [], 422);
        }
        $code = $row['proposal_number'] ?? '';
        $name = $row['title'] ?? '';
        $res['proposal_id']    = (int)$row['id'];
        $res['related_type']   = 'proposal';
        $res['related_id']     = (int)$row['id'];
        $res['related_record'] = trim(($code ? $code . ' — ' : '') . $name);
    } elseif ($t === 'contract') {
        $stmt = $pdo->prepare("SELECT id, contract_number, title FROM contracts WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmt->execute([$relatedId, $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            docs_json(false, 'Related contract not found or does not belong to your organization.', [], 422);
        }
        $code = $row['contract_number'] ?? '';
        $name = $row['title'] ?? '';
        $res['contract_id']    = (int)$row['id'];
        $res['related_type']   = 'contract';
        $res['related_id']     = (int)$row['id'];
        $res['related_record'] = trim(($code ? $code . ' — ' : '') . $name);
    } elseif ($t === 'invoice') {
        $stmt = $pdo->prepare("SELECT id, invoice_number, title FROM invoices WHERE id = ? AND organization_id = ? LIMIT 1");
        $stmt->execute([$relatedId, $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            docs_json(false, 'Related invoice not found or does not belong to your organization.', [], 422);
        }
        $code = $row['invoice_number'] ?? '';
        $name = $row['title'] ?? '';
        $res['invoice_id']     = (int)$row['id'];
        $res['related_type']   = 'invoice';
        $res['related_id']     = (int)$row['id'];
        $res['related_record'] = trim(($code ? $code . ($name ? ' — ' . $name : '') : $name));
    } else {
        docs_json(false, 'Unsupported related record type.', [], 422);
    }

    return $res;
}

// ============================================================================
// ACTION: reference_options
// ============================================================================
if ($action === 'reference_options') {
    require_doc_perm('view');

    $stmtCo = $pdo->prepare("SELECT id, name FROM companies WHERE organization_id = ? ORDER BY name ASC");
    $stmtCo->execute([$organizationId]);
    $companies = $stmtCo->fetchAll(PDO::FETCH_ASSOC);

    $stmtUsr = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE organization_id = ? AND status = 'active' ORDER BY first_name ASC, last_name ASC");
    $stmtUsr->execute([$organizationId]);
    $users = $stmtUsr->fetchAll(PDO::FETCH_ASSOC);

    docs_json(true, 'Reference options loaded.', [
        'companies'      => $companies,
        'users'          => $users,
        'document_types' => ['PDF', 'DOCX', 'XLSX', 'PPTX', 'Image', 'Other'],
        'categories'     => ['Contracts', 'Proposals', 'Invoices', 'Projects', 'Reports', 'General'],
        'statuses'       => ['Shared', 'Signed', 'Available', 'Pending Review', 'Internal', 'Archived'],
    ]);
}

// ============================================================================
// ACTION: related_options
// ============================================================================
if ($action === 'related_options') {
    require_doc_perm('view');

    $category  = trim($_GET['category'] ?? '');
    $companyId = (int)($_GET['company_id'] ?? 0) ?: null;

    $options = [];
    $catLower = strtolower($category);

    if ($catLower === 'projects' || $catLower === 'project') {
        // Projects: organization-scoped, NO company_id filter (per audit & correction 1)
        $stmt = $pdo->prepare("
            SELECT id, project_code, name 
            FROM projects 
            WHERE organization_id = ? 
            ORDER BY id DESC
        ");
        $stmt->execute([$organizationId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = $row['project_code'] ?? '';
            $name = $row['name'] ?? '';
            $label = trim(($code ? $code . ' — ' : '') . $name);
            $options[] = [
                'id'    => (int)$row['id'],
                'type'  => 'project',
                'code'  => $code,
                'title' => $name,
                'label' => $label
            ];
        }
    } elseif ($catLower === 'deals' || $catLower === 'deal') {
        // Deals: organization-scoped (deals has no company_id FK, per audit)
        $stmt = $pdo->prepare("
            SELECT id, deal_code, name 
            FROM deals 
            WHERE organization_id = ? 
            ORDER BY id DESC
        ");
        $stmt->execute([$organizationId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = $row['deal_code'] ?? '';
            $name = $row['name'] ?? '';
            $label = trim(($code ? $code . ' — ' : '') . $name);
            $options[] = [
                'id'    => (int)$row['id'],
                'type'  => 'deal',
                'code'  => $code,
                'title' => $name,
                'label' => $label
            ];
        }
    } elseif ($catLower === 'proposals' || $catLower === 'proposal') {
        // Proposals: has company_id FK
        if ($companyId) {
            $stmt = $pdo->prepare("
                SELECT id, proposal_number, title 
                FROM proposals 
                WHERE organization_id = ? AND company_id = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$organizationId, $companyId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, proposal_number, title 
                FROM proposals 
                WHERE organization_id = ? 
                ORDER BY id DESC
            ");
            $stmt->execute([$organizationId]);
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = $row['proposal_number'] ?? '';
            $name = $row['title'] ?? '';
            $label = trim(($code ? $code . ' — ' : '') . $name);
            $options[] = [
                'id'    => (int)$row['id'],
                'type'  => 'proposal',
                'code'  => $code,
                'title' => $name,
                'label' => $label
            ];
        }
    } elseif ($catLower === 'contracts' || $catLower === 'contract') {
        // Contracts: has company_id FK
        if ($companyId) {
            $stmt = $pdo->prepare("
                SELECT id, contract_number, title 
                FROM contracts 
                WHERE organization_id = ? AND company_id = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$organizationId, $companyId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, contract_number, title 
                FROM contracts 
                WHERE organization_id = ? 
                ORDER BY id DESC
            ");
            $stmt->execute([$organizationId]);
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = $row['contract_number'] ?? '';
            $name = $row['title'] ?? '';
            $label = trim(($code ? $code . ' — ' : '') . $name);
            $options[] = [
                'id'    => (int)$row['id'],
                'type'  => 'contract',
                'code'  => $code,
                'title' => $name,
                'label' => $label
            ];
        }
    } elseif ($catLower === 'invoices' || $catLower === 'invoice') {
        // Invoices: has company_id FK
        if ($companyId) {
            $stmt = $pdo->prepare("
                SELECT id, invoice_number, title 
                FROM invoices 
                WHERE organization_id = ? AND company_id = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$organizationId, $companyId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, invoice_number, title 
                FROM invoices 
                WHERE organization_id = ? 
                ORDER BY id DESC
            ");
            $stmt->execute([$organizationId]);
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = $row['invoice_number'] ?? '';
            $name = $row['title'] ?? '';
            $label = trim(($code ? $code . ($name ? ' — ' . $name : '') : $name));
            $options[] = [
                'id'    => (int)$row['id'],
                'type'  => 'invoice',
                'code'  => $code,
                'title' => $name,
                'label' => $label
            ];
        }
    }

    docs_json(true, 'Related options loaded.', ['options' => $options]);
}

// ============================================================================
// ACTION: summary
// ============================================================================
if ($action === 'summary') {
    require_doc_perm('view');

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status IN ('Shared','Signed','Available') THEN 1 ELSE 0 END) AS shared,
            SUM(CASE WHEN status = 'Pending Review' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS recent,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) AS archived
        FROM documents
        WHERE organization_id = ?
    ");
    $stmt->execute([$organizationId]);
    $kpi = $stmt->fetch(PDO::FETCH_ASSOC);

    docs_json(true, 'Summary loaded.', [
        'total'    => (int)$kpi['total'],
        'shared'   => (int)$kpi['shared'],
        'pending'  => (int)$kpi['pending'],
        'recent'   => (int)$kpi['recent'],
        'archived' => (int)$kpi['archived'],
    ]);
}

// ============================================================================
// ACTION: list
// ============================================================================
if ($action === 'list') {
    require_doc_perm('view');

    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = min(100, max(1, (int)($_GET['per_page'] ?? 8)));
    $offset   = ($page - 1) * $perPage;

    $search        = trim($_GET['search'] ?? '');
    $typeFilter    = trim($_GET['type'] ?? '');
    $companyFilter = trim($_GET['company_id'] ?? '');
    $categoryFilter= trim($_GET['category'] ?? '');
    $ownerFilter   = trim($_GET['owner_id'] ?? '');
    $kpiFilter     = trim($_GET['kpi'] ?? 'All');

    $where  = ['d.organization_id = ?'];
    $params = [$organizationId];

    if ($kpiFilter === 'Shared') {
        $where[] = "d.status IN ('Shared','Signed','Available')";
    } elseif ($kpiFilter === 'Pending' || $kpiFilter === 'Pending Review') {
        $where[] = "d.status = 'Pending Review'";
    } elseif ($kpiFilter === 'Recent') {
        $where[] = "DATE(d.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($kpiFilter === 'Archived') {
        $where[] = "d.is_archived = 1";
    }

    if ($kpiFilter !== 'Archived') {
        $where[] = "d.is_archived = 0";
    }

    if ($typeFilter && $typeFilter !== 'All') {
        $where[] = "d.document_type = ?";
        $params[] = $typeFilter;
    }
    if ($companyFilter && $companyFilter !== 'All') {
        $where[] = "d.company_id = ?";
        $params[] = (int)$companyFilter;
    }
    if ($categoryFilter && $categoryFilter !== 'All') {
        $where[] = "d.category = ?";
        $params[] = $categoryFilter;
    }
    if ($ownerFilter && $ownerFilter !== 'All') {
        $where[] = "d.owner_id = ?";
        $params[] = (int)$ownerFilter;
    }

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = "(d.title LIKE ? OR d.document_code LIKE ? OR d.original_filename LIKE ? OR d.related_record LIKE ? OR co.name LIKE ? OR TRIM(CONCAT(COALESCE(own.first_name,''),' ',COALESCE(own.last_name,''))) LIKE ? OR prj.name LIKE ? OR dl.name LIKE ? OR prop.title LIKE ? OR ctr.title LIKE ? OR inv.title LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like]);
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $stmtCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM documents d
        LEFT JOIN companies co   ON co.id = d.company_id AND co.organization_id = d.organization_id
        LEFT JOIN users own      ON own.id = d.owner_id   AND own.organization_id = d.organization_id
        LEFT JOIN projects prj   ON prj.id = d.project_id AND prj.organization_id = d.organization_id
        LEFT JOIN deals dl       ON dl.id = d.deal_id AND dl.organization_id = d.organization_id
        LEFT JOIN proposals prop ON prop.id = d.proposal_id AND prop.organization_id = d.organization_id
        LEFT JOIN contracts ctr  ON ctr.id = d.contract_id AND ctr.organization_id = d.organization_id
        LEFT JOIN invoices inv   ON inv.id = d.invoice_id AND inv.organization_id = d.organization_id
        {$whereSQL}
    ");
    $stmtCount->execute($params);
    $totalCount = (int)$stmtCount->fetchColumn();

    $listParams   = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;

    $stmtList = $pdo->prepare("
        SELECT
            d.id, d.document_code, d.title, d.original_filename, d.file_path,
            d.file_size, d.mime_type, d.document_type, d.category,
            d.project_id, d.deal_id, d.proposal_id, d.contract_id, d.invoice_id,
            d.related_type, d.related_id,
            COALESCE(
                d.related_record,
                CASE
                    WHEN d.project_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prj.project_code, ''), CASE WHEN prj.project_code IS NOT NULL AND prj.project_code != '' THEN ' — ' ELSE '' END, COALESCE(prj.name, '')))
                    WHEN d.deal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(dl.deal_code, ''), CASE WHEN dl.deal_code IS NOT NULL AND dl.deal_code != '' THEN ' — ' ELSE '' END, COALESCE(dl.name, '')))
                    WHEN d.proposal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prop.proposal_number, ''), CASE WHEN prop.proposal_number IS NOT NULL AND prop.proposal_number != '' THEN ' — ' ELSE '' END, COALESCE(prop.title, '')))
                    WHEN d.contract_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(ctr.contract_number, ''), CASE WHEN ctr.contract_number IS NOT NULL AND ctr.contract_number != '' THEN ' — ' ELSE '' END, COALESCE(ctr.title, '')))
                    WHEN d.invoice_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(inv.invoice_number, ''), CASE WHEN inv.invoice_number IS NOT NULL AND inv.invoice_number != '' THEN ' — ' ELSE '' END, COALESCE(inv.title, '')))
                    ELSE NULL
                END
            ) AS display_related_record,
            d.related_record, d.status, d.is_archived, d.description,
            d.created_at, d.updated_at,
            d.company_id, COALESCE(co.name, '') AS company_name,
            d.owner_id,
            TRIM(CONCAT(COALESCE(own.first_name,''),' ',COALESCE(own.last_name,''))) AS owner_name,
            d.uploaded_by,
            TRIM(CONCAT(COALESCE(upl.first_name,''),' ',COALESCE(upl.last_name,''))) AS uploader_name
        FROM documents d
        LEFT JOIN companies co   ON co.id = d.company_id AND co.organization_id = d.organization_id
        LEFT JOIN users own      ON own.id = d.owner_id   AND own.organization_id = d.organization_id
        LEFT JOIN users upl      ON upl.id = d.uploaded_by AND upl.organization_id = d.organization_id
        LEFT JOIN projects prj   ON prj.id = d.project_id AND prj.organization_id = d.organization_id
        LEFT JOIN deals dl       ON dl.id = d.deal_id AND dl.organization_id = d.organization_id
        LEFT JOIN proposals prop ON prop.id = d.proposal_id AND prop.organization_id = d.organization_id
        LEFT JOIN contracts ctr  ON ctr.id = d.contract_id AND ctr.organization_id = d.organization_id
        LEFT JOIN invoices inv   ON inv.id = d.invoice_id AND inv.organization_id = d.organization_id
        {$whereSQL}
        ORDER BY d.created_at DESC, d.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmtList->execute($listParams);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1;

    docs_json(true, 'Documents loaded.', [
        'documents'  => $rows,
        'pagination' => [
            'total'       => $totalCount,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ],
    ]);
}

// ============================================================================
// ACTION: get
// ============================================================================
if ($action === 'get') {
    require_doc_perm('view');

    $docId = (int)($_GET['id'] ?? 0);
    if ($docId <= 0) {
        docs_json(false, 'Document ID is required.', [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT d.*,
            COALESCE(co.name, '') AS company_name,
            TRIM(CONCAT(COALESCE(own.first_name,''),' ',COALESCE(own.last_name,''))) AS owner_name,
            TRIM(CONCAT(COALESCE(upl.first_name,''),' ',COALESCE(upl.last_name,''))) AS uploader_name,
            COALESCE(
                d.related_record,
                CASE
                    WHEN d.project_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prj.project_code, ''), CASE WHEN prj.project_code IS NOT NULL AND prj.project_code != '' THEN ' — ' ELSE '' END, COALESCE(prj.name, '')))
                    WHEN d.deal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(dl.deal_code, ''), CASE WHEN dl.deal_code IS NOT NULL AND dl.deal_code != '' THEN ' — ' ELSE '' END, COALESCE(dl.name, '')))
                    WHEN d.proposal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prop.proposal_number, ''), CASE WHEN prop.proposal_number IS NOT NULL AND prop.proposal_number != '' THEN ' — ' ELSE '' END, COALESCE(prop.title, '')))
                    WHEN d.contract_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(ctr.contract_number, ''), CASE WHEN ctr.contract_number IS NOT NULL AND ctr.contract_number != '' THEN ' — ' ELSE '' END, COALESCE(ctr.title, '')))
                    WHEN d.invoice_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(inv.invoice_number, ''), CASE WHEN inv.invoice_number IS NOT NULL AND inv.invoice_number != '' THEN ' — ' ELSE '' END, COALESCE(inv.title, '')))
                    ELSE NULL
                END
            ) AS display_related_record
        FROM documents d
        LEFT JOIN companies co   ON co.id = d.company_id AND co.organization_id = d.organization_id
        LEFT JOIN users own      ON own.id = d.owner_id   AND own.organization_id = d.organization_id
        LEFT JOIN users upl      ON upl.id = d.uploaded_by AND upl.organization_id = d.organization_id
        LEFT JOIN projects prj   ON prj.id = d.project_id AND prj.organization_id = d.organization_id
        LEFT JOIN deals dl       ON dl.id = d.deal_id AND dl.organization_id = d.organization_id
        LEFT JOIN proposals prop ON prop.id = d.proposal_id AND prop.organization_id = d.organization_id
        LEFT JOIN contracts ctr  ON ctr.id = d.contract_id AND ctr.organization_id = d.organization_id
        LEFT JOIN invoices inv   ON inv.id = d.invoice_id AND inv.organization_id = d.organization_id
        WHERE d.id = ? AND d.organization_id = ?
        LIMIT 1
    ");
    $stmt->execute([$docId, $organizationId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        docs_json(false, 'Document not found or access denied.', [], 404);
    }

    docs_json(true, 'Document loaded.', ['document' => $doc]);
}

// ============================================================================
// ACTION: upload
// ============================================================================
if ($action === 'upload') {
    require_doc_perm('create');

    $title    = trim($_POST['title'] ?? '');
    $status   = trim($_POST['status'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if (empty($title)) {
        docs_json(false, 'Document name is required.', [], 422);
    }
    if (empty($status)) {
        docs_json(false, 'Status is required.', [], 422);
    }

    $validStatuses = ['Shared', 'Signed', 'Available', 'Pending Review', 'Internal', 'Archived'];
    if (!in_array($status, $validStatuses, true)) {
        docs_json(false, 'Invalid status value.', [], 422);
    }

    $documentType  = trim($_POST['document_type'] ?? '');
    $relatedRecord = trim($_POST['related_record'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $companyId     = (int)($_POST['company_id'] ?? 0) ?: null;
    $contactId     = (int)($_POST['contact_id'] ?? 0) ?: null;
    $leadId        = (int)($_POST['lead_id'] ?? 0) ?: null;
    $dealId        = (int)($_POST['deal_id'] ?? 0) ?: null;
    $projectId     = (int)($_POST['project_id'] ?? 0) ?: null;
    $proposalId    = (int)($_POST['proposal_id'] ?? 0) ?: null;
    $contractId    = (int)($_POST['contract_id'] ?? 0) ?: null;
    $invoiceId     = (int)($_POST['invoice_id'] ?? 0) ?: null;
    $ownerId       = (int)($_POST['owner_id'] ?? 0) ?: null;

    $relatedType   = trim($_POST['related_type'] ?? '');
    $relatedId     = (int)($_POST['related_id'] ?? 0) ?: null;

    if (!$relatedType && $projectId) {
        $relatedType = 'project';
        $relatedId   = $projectId;
    } elseif (!$relatedType && $dealId) {
        $relatedType = 'deal';
        $relatedId   = $dealId;
    } elseif (!$relatedType && $proposalId) {
        $relatedType = 'proposal';
        $relatedId   = $proposalId;
    } elseif (!$relatedType && $contractId) {
        $relatedType = 'contract';
        $relatedId   = $contractId;
    } elseif (!$relatedType && $invoiceId) {
        $relatedType = 'invoice';
        $relatedId   = $invoiceId;
    }

    $relData = resolve_and_validate_related_record($pdo, $organizationId, $relatedType, $relatedId);

    $projectId    = $relData['project_id'] ?? $projectId;
    $dealId       = $relData['deal_id'] ?? $dealId;
    $proposalId   = $relData['proposal_id'] ?? $proposalId;
    $contractId   = $relData['contract_id'] ?? $contractId;
    $invoiceId    = $relData['invoice_id'] ?? $invoiceId;
    $finalRelType = $relData['related_type'];
    $finalRelId   = $relData['related_id'];
    $finalRelRec  = $relData['related_record'] ?: ($relatedRecord ?: null);

    if ($companyId !== null) {
        $chk = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$companyId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Company not found or does not belong to your organization.', [], 422);
        }
    }
    if ($ownerId !== null) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $chk->execute([$ownerId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Owner user not found or not active in your organization.', [], 422);
        }
    }
    if ($contactId !== null) {
        $chk = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$contactId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Contact not found or does not belong to your organization.', [], 422);
        }
    }
    if ($leadId !== null) {
        $chk = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$leadId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Lead not found or does not belong to your organization.', [], 422);
        }
    }

    // File handling
    $origName = $storedName = $dbRelPath = $sizeFormatted = $mimeType = $detectedExt = null;
    $fileSizeBytes = 0;
    $hasFile = !empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasFile) {
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            docs_json(false, 'File upload error. Please try again.', [], 400);
        }
        $file = $_FILES['file'];
        if ($file['size'] > 25 * 1024 * 1024) {
            docs_json(false, 'File size exceeds the 25MB limit.', [], 422);
        }

        $origName    = basename($file['name']);
        $detectedExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['pdf','doc','docx','txt','rtf','png','jpg','jpeg','zip','xlsx','xls','pptx','ppt','csv','xml','fig'];
        if (!in_array($detectedExt, $allowedExts, true)) {
            docs_json(false, 'File type not allowed. Supported: PDF, DOC, DOCX, XLSX, PPTX, Images, ZIP, etc.', [], 422);
        }

        $mimeType      = $file['type'] ?: 'application/octet-stream';
        $fileSizeBytes = (int)$file['size'];
        $sizeFormatted = $fileSizeBytes > 1048576 ? round($fileSizeBytes / 1048576, 1) . ' MB' : round($fileSizeBytes / 1024, 1) . ' KB';

        if (empty($documentType)) {
            if ($detectedExt === 'pdf') $documentType = 'PDF';
            elseif (in_array($detectedExt, ['doc','docx','rtf','txt'])) $documentType = 'DOCX';
            elseif (in_array($detectedExt, ['xls','xlsx','csv'])) $documentType = 'XLSX';
            elseif (in_array($detectedExt, ['ppt','pptx'])) $documentType = 'PPTX';
            elseif (in_array($detectedExt, ['png','jpg','jpeg','gif','fig'])) $documentType = 'Image';
            else $documentType = 'Other';
        }
    } elseif (empty($documentType)) {
        $documentType = null;
    }

    // Generate sequential document code
    $stmtMaxNum = $pdo->prepare("SELECT document_code FROM documents WHERE organization_id = ? ORDER BY id DESC LIMIT 200");
    $stmtMaxNum->execute([$organizationId]);
    $existingCodes = $stmtMaxNum->fetchAll(PDO::FETCH_COLUMN);
    $maxNum = 0;
    foreach ($existingCodes as $code) {
        if (preg_match('/DOC[-_]?0*(\d+)/i', $code, $m)) {
            $val = (int)$m[1];
            if ($val > $maxNum) $maxNum = $val;
        }
    }
    $docNumber = 'DOC-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);

    $isShared   = ($status === 'Shared') ? 1 : 0;
    $isArchived = ($status === 'Archived') ? 1 : 0;

    $pdo->beginTransaction();
    try {
        $stmtIns = $pdo->prepare("
            INSERT INTO documents (
                organization_id, document_code, title,
                original_filename, stored_filename, file_path,
                file_size, file_size_bytes, mime_type, file_extension,
                document_type, category, related_record, description,
                company_id, contact_id, lead_id, deal_id, project_id,
                proposal_id, contract_id, invoice_id,
                related_type, related_id,
                owner_id, uploaded_by, status, is_archived, is_shared,
                created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
        ");
        $stmtIns->execute([
            $organizationId, $docNumber, $title,
            $origName, null, null,
            $sizeFormatted, $fileSizeBytes, $mimeType, $detectedExt,
            $documentType ?: null, $category ?: null, $finalRelRec, $description ?: null,
            $companyId, $contactId, $leadId, $dealId, $projectId,
            $proposalId, $contractId, $invoiceId,
            $finalRelType, $finalRelId,
            $ownerId, $currentUserId, $status, $isArchived, $isShared
        ]);
        $docId = (int)$pdo->lastInsertId();

        if ($hasFile) {
            $uploadDir = __DIR__ . '/../../uploads/documents/' . $organizationId . '/' . $docId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $safeName   = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $origName);
            $storedName = time() . '_' . $safeName;
            $targetPath = $uploadDir . '/' . $storedName;
            $dbRelPath  = 'uploads/documents/' . $organizationId . '/' . $docId . '/' . $storedName;

            $saved = is_uploaded_file($_FILES['file']['tmp_name'])
                ? move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)
                : copy($_FILES['file']['tmp_name'], $targetPath);

            if (!$saved) {
                throw new RuntimeException('Failed to save uploaded file to disk.');
            }

            $pdo->prepare("UPDATE documents SET stored_filename = ?, file_path = ? WHERE id = ? AND organization_id = ?")
                ->execute([$storedName, $dbRelPath, $docId, $organizationId]);
        }

        $pdo->prepare("INSERT INTO team_activities (organization_id, user_id, related_entity, related_entity_id, activity_type, description, created_at) VALUES (?,?,'documents',?,'created',?,NOW())")
            ->execute([$organizationId, $currentUserId, $docId, "Document '{$docNumber}' — '{$title}' was uploaded."]);

        $pdo->commit();

        docs_json(true, 'Document uploaded successfully.', [
            'id'            => $docId,
            'document_code' => $docNumber,
            'title'         => $title,
            'has_file'      => $hasFile,
            'file_size'     => $sizeFormatted,
        ], 201);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        docs_json(false, 'Failed to upload document: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================================
// ACTION: update
// ============================================================================
if ($action === 'update') {
    require_doc_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $docId = (int)($data['id'] ?? 0);
    if ($docId <= 0) {
        docs_json(false, 'Document ID is required.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT document_code, title FROM documents WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$docId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        docs_json(false, 'Document not found or access denied.', [], 404);
    }

    $title         = trim($data['title'] ?? $existing['title']);
    $status        = trim($data['status'] ?? '');
    $category      = trim($data['category'] ?? '');
    $documentType  = trim($data['document_type'] ?? '');
    $description   = trim($data['description'] ?? '');

    if (empty($title)) {
        docs_json(false, 'Document name is required.', [], 422);
    }

    $validStatuses = ['Shared', 'Signed', 'Available', 'Pending Review', 'Internal', 'Archived'];
    if (!empty($status) && !in_array($status, $validStatuses, true)) {
        docs_json(false, 'Invalid status value.', [], 422);
    }

    $companyId = isset($data['company_id']) ? ((int)$data['company_id'] ?: null) : null;
    $ownerId   = isset($data['owner_id'])   ? ((int)$data['owner_id']   ?: null) : null;
    $contactId = isset($data['contact_id']) ? ((int)$data['contact_id'] ?: null) : null;
    $leadId    = isset($data['lead_id'])    ? ((int)$data['lead_id']    ?: null) : null;

    if ($companyId !== null) {
        $chk = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$companyId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Company not found or does not belong to your organization.', [], 422);
        }
    }
    if ($ownerId !== null) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND status = 'active' LIMIT 1");
        $chk->execute([$ownerId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Owner user not found or not active in your organization.', [], 422);
        }
    }
    if ($contactId !== null) {
        $chk = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$contactId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Contact not found or does not belong to your organization.', [], 422);
        }
    }
    if ($leadId !== null) {
        $chk = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND organization_id = ? LIMIT 1");
        $chk->execute([$leadId, $organizationId]);
        if (!$chk->fetchColumn()) {
            docs_json(false, 'Lead not found or does not belong to your organization.', [], 422);
        }
    }

    $hasRelatedInput = array_key_exists('related_id', $data) || array_key_exists('related_type', $data) ||
                       array_key_exists('project_id', $data) || array_key_exists('deal_id', $data) ||
                       array_key_exists('proposal_id', $data) || array_key_exists('contract_id', $data) ||
                       array_key_exists('invoice_id', $data) || array_key_exists('related_record', $data);

    $relData = null;
    if ($hasRelatedInput) {
        $relatedType = trim($data['related_type'] ?? '');
        $relatedId   = (int)($data['related_id'] ?? 0) ?: null;

        if (!$relatedType && !empty($data['project_id'])) {
            $relatedType = 'project';
            $relatedId   = (int)$data['project_id'];
        } elseif (!$relatedType && !empty($data['deal_id'])) {
            $relatedType = 'deal';
            $relatedId   = (int)$data['deal_id'];
        } elseif (!$relatedType && !empty($data['proposal_id'])) {
            $relatedType = 'proposal';
            $relatedId   = (int)$data['proposal_id'];
        } elseif (!$relatedType && !empty($data['contract_id'])) {
            $relatedType = 'contract';
            $relatedId   = (int)$data['contract_id'];
        } elseif (!$relatedType && !empty($data['invoice_id'])) {
            $relatedType = 'invoice';
            $relatedId   = (int)$data['invoice_id'];
        }

        $relData = resolve_and_validate_related_record($pdo, $organizationId, $relatedType, $relatedId);
    }

    $pdo->beginTransaction();
    try {
        $setClauses   = ['title = ?', 'category = ?', 'description = ?', 'updated_at = NOW()'];
        $updateParams = [$title, $category ?: null, $description ?: null];

        if (!empty($status)) {
            $setClauses[] = 'status = ?';
            $updateParams[] = $status;
            if ($status === 'Shared') {
                $setClauses[] = 'is_shared = 1';
            } elseif ($status === 'Archived') {
                $setClauses[] = 'is_archived = 1';
            }
        }
        if (!empty($documentType)) {
            $setClauses[] = 'document_type = ?';
            $updateParams[] = $documentType;
        }
        if (array_key_exists('company_id', $data)) {
            $setClauses[] = 'company_id = ?';
            $updateParams[] = $companyId;
        }
        if (array_key_exists('owner_id', $data)) {
            $setClauses[] = 'owner_id = ?';
            $updateParams[] = $ownerId;
        }
        if (array_key_exists('contact_id', $data)) {
            $setClauses[] = 'contact_id = ?';
            $updateParams[] = $contactId;
        }
        if (array_key_exists('lead_id', $data)) {
            $setClauses[] = 'lead_id = ?';
            $updateParams[] = $leadId;
        }

        if ($hasRelatedInput && $relData !== null) {
            $setClauses[]   = 'project_id = ?';
            $updateParams[] = $relData['project_id'];

            $setClauses[]   = 'deal_id = ?';
            $updateParams[] = $relData['deal_id'];

            $setClauses[]   = 'proposal_id = ?';
            $updateParams[] = $relData['proposal_id'];

            $setClauses[]   = 'contract_id = ?';
            $updateParams[] = $relData['contract_id'];

            $setClauses[]   = 'invoice_id = ?';
            $updateParams[] = $relData['invoice_id'];

            $setClauses[]   = 'related_type = ?';
            $updateParams[] = $relData['related_type'];

            $setClauses[]   = 'related_id = ?';
            $updateParams[] = $relData['related_id'];

            $setClauses[]   = 'related_record = ?';
            $updateParams[] = $relData['related_record'] ?: ($data['related_record'] ?? null);
        }

        // File update if provided
        $hasNewFile = !empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($hasNewFile) {
            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                docs_json(false, 'File upload error. Please try again.', [], 400);
            }
            $file = $_FILES['file'];
            if ($file['size'] > 25 * 1024 * 1024) {
                docs_json(false, 'File size exceeds the 25MB limit.', [], 422);
            }

            $origName    = basename($file['name']);
            $detectedExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf','doc','docx','txt','rtf','png','jpg','jpeg','zip','xlsx','xls','pptx','ppt','csv','xml','fig'];
            if (!in_array($detectedExt, $allowedExts, true)) {
                docs_json(false, 'File type not allowed.', [], 422);
            }

            $mimeType      = $file['type'] ?: 'application/octet-stream';
            $fileSizeBytes = (int)$file['size'];
            $sizeFormatted = $fileSizeBytes > 1048576 ? round($fileSizeBytes / 1048576, 1) . ' MB' : round($fileSizeBytes / 1024, 1) . ' KB';

            $uploadDir = __DIR__ . '/../../uploads/documents/' . $organizationId . '/' . $docId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $safeName   = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $origName);
            $storedName = time() . '_' . $safeName;
            $targetPath = $uploadDir . '/' . $storedName;
            $dbRelPath  = 'uploads/documents/' . $organizationId . '/' . $docId . '/' . $storedName;

            $saved = is_uploaded_file($_FILES['file']['tmp_name'])
                ? move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)
                : copy($_FILES['file']['tmp_name'], $targetPath);

            if (!$saved) {
                throw new RuntimeException('Failed to save uploaded file to disk.');
            }

            $setClauses[]   = 'original_filename = ?';
            $updateParams[] = $origName;
            $setClauses[]   = 'stored_filename = ?';
            $updateParams[] = $storedName;
            $setClauses[]   = 'file_path = ?';
            $updateParams[] = $dbRelPath;
            $setClauses[]   = 'file_size = ?';
            $updateParams[] = $sizeFormatted;
            $setClauses[]   = 'file_size_bytes = ?';
            $updateParams[] = $fileSizeBytes;
            $setClauses[]   = 'mime_type = ?';
            $updateParams[] = $mimeType;
            $setClauses[]   = 'file_extension = ?';
            $updateParams[] = $detectedExt;
        }

        $updateParams[] = $docId;
        $updateParams[] = $organizationId;

        $pdo->prepare("UPDATE documents SET " . implode(', ', $setClauses) . " WHERE id = ? AND organization_id = ?")
            ->execute($updateParams);

        $pdo->prepare("INSERT INTO team_activities (organization_id, user_id, related_entity, related_entity_id, activity_type, description, created_at) VALUES (?,?,'documents',?,'updated',?,NOW())")
            ->execute([$organizationId, $currentUserId, $docId, "Document '{$existing['document_code']}' — '{$title}' was updated."]);

        $pdo->commit();
        docs_json(true, 'Document updated successfully.', ['id' => $docId]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        docs_json(false, 'Failed to update document: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================================
// ACTION: share — sets status = 'Shared' (Share button in view drawer)
// ============================================================================
if ($action === 'share') {
    require_doc_perm('send');

    $rawInput = file_get_contents('php://input');
    $data  = json_decode($rawInput, true) ?: $_POST;
    $docId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if ($docId <= 0) {
        docs_json(false, 'Document ID is required.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT document_code, title FROM documents WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$docId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        docs_json(false, 'Document not found or access denied.', [], 404);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE documents SET status = 'Shared', is_shared = 1, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$docId, $organizationId]);

        $pdo->prepare("INSERT INTO team_activities (organization_id, user_id, related_entity, related_entity_id, activity_type, description, created_at) VALUES (?,?,'documents',?,'shared',?,NOW())")
            ->execute([$organizationId, $currentUserId, $docId, "Document '{$existing['document_code']}' — '{$existing['title']}' was shared."]);

        $pdo->commit();
        docs_json(true, 'Document status set to Shared.', ['id' => $docId, 'status' => 'Shared']);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        docs_json(false, 'Failed to share document: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================================
// ACTION: archive — toggle is_archived
// ============================================================================
if ($action === 'archive') {
    require_doc_perm('edit');

    $rawInput = file_get_contents('php://input');
    $data  = json_decode($rawInput, true) ?: $_POST;
    $docId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if ($docId <= 0) {
        docs_json(false, 'Document ID is required.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT document_code, title, is_archived FROM documents WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$docId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        docs_json(false, 'Document not found or access denied.', [], 404);
    }

    $newState = $existing['is_archived'] ? 0 : 1;
    $verb     = $newState ? 'archived' : 'unarchived';
    $newStatus = $newState ? 'Archived' : 'Available';

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE documents SET is_archived = ?, status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?")
            ->execute([$newState, $newStatus, $docId, $organizationId]);

        $pdo->prepare("INSERT INTO team_activities (organization_id, user_id, related_entity, related_entity_id, activity_type, description, created_at) VALUES (?,?,'documents',?,'updated',?,NOW())")
            ->execute([$organizationId, $currentUserId, $docId, "Document '{$existing['document_code']}' — '{$existing['title']}' was {$verb}."]);

        $pdo->commit();
        docs_json(true, 'Document ' . $verb . ' successfully.', ['id' => $docId, 'is_archived' => $newState]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        docs_json(false, 'Failed to archive/unarchive document: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================================
// ACTION: delete
// ============================================================================
if ($action === 'delete') {
    require_doc_perm('delete');

    $rawInput = file_get_contents('php://input');
    $data  = json_decode($rawInput, true) ?: $_POST;
    $docId = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if ($docId <= 0) {
        docs_json(false, 'Document ID is required.', [], 400);
    }

    $stmtEx = $pdo->prepare("SELECT document_code, title, file_path FROM documents WHERE id = ? AND organization_id = ?");
    $stmtEx->execute([$docId, $organizationId]);
    $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        docs_json(false, 'Document not found or access denied.', [], 404);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM documents WHERE id = ? AND organization_id = ?")->execute([$docId, $organizationId]);

        if (!empty($existing['file_path'])) {
            $fullPath = __DIR__ . '/../../' . ltrim($existing['file_path'], '/\\');
            if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
                $dir = dirname($fullPath);
                if (is_dir($dir)) {
                    @rmdir($dir);
                }
            }
        }

        // Per proposals delete policy: clean up activities and notes
        $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'documents' AND related_entity_id = ?")->execute([$organizationId, $docId]);
        $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'documents' AND related_id = ?")->execute([$organizationId, $docId]);

        $pdo->commit();
        docs_json(true, 'Document deleted successfully.', ['id' => $docId, 'document_code' => $existing['document_code']]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        docs_json(false, 'Failed to delete document: ' . $e->getMessage(), [], 500);
    }
}

// ============================================================================
// ACTION: download — authenticated file streaming
// ============================================================================
if ($action === 'download') {
    require_doc_perm('download');

    $docId = (int)($_GET['id'] ?? 0);
    if ($docId <= 0) {
        docs_json(false, 'Document ID is required.', [], 400);
    }

    $stmt = $pdo->prepare("SELECT original_filename, file_path, mime_type FROM documents WHERE id = ? AND organization_id = ? LIMIT 1");
    $stmt->execute([$docId, $organizationId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        docs_json(false, 'Document not found or access denied.', [], 404);
    }
    if (empty($doc['file_path'])) {
        docs_json(false, 'No file is attached to this document record.', [], 404);
    }

    $fullPath = __DIR__ . '/../../' . ltrim($doc['file_path'], '/\\');
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        docs_json(false, 'Physical file not found on disk.', [], 404);
    }

    $mime     = !empty($doc['mime_type']) ? $doc['mime_type'] : 'application/octet-stream';
    $filename = !empty($doc['original_filename']) ? $doc['original_filename'] : basename($fullPath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($fullPath);
    exit;
}

// ============================================================================
// ACTION: preview — inline file streaming for browser preview
// ============================================================================
if ($action === 'preview') {
    require_doc_perm('view');

    $docId = (int)($_GET['id'] ?? 0);
    if ($docId <= 0) {
        docs_json(false, 'Document ID is required.', [], 400);
    }

    $stmt = $pdo->prepare("SELECT original_filename, file_path, mime_type FROM documents WHERE id = ? AND organization_id = ? LIMIT 1");
    $stmt->execute([$docId, $organizationId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        docs_json(false, 'Document not found or access denied.', [], 404);
    }
    if (empty($doc['file_path'])) {
        docs_json(false, 'No file is attached to this document record.', [], 404);
    }

    $fullPath = __DIR__ . '/../../' . ltrim($doc['file_path'], '/\\');
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        docs_json(false, 'Physical file not found on disk.', [], 404);
    }

    $mime     = !empty($doc['mime_type']) ? $doc['mime_type'] : 'application/octet-stream';
    $filename = !empty($doc['original_filename']) ? $doc['original_filename'] : basename($fullPath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// ============================================================================
// ACTION: export / export_csv
// ============================================================================
if ($action === 'export' || $action === 'export_csv') {
    require_doc_perm('export');

    $stmtList = $pdo->prepare("
        SELECT
            d.document_code, d.title, d.original_filename, d.document_type,
            d.file_size, d.category,
            COALESCE(
                d.related_record,
                CASE
                    WHEN d.project_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prj.project_code, ''), CASE WHEN prj.project_code IS NOT NULL AND prj.project_code != '' THEN ' — ' ELSE '' END, COALESCE(prj.name, '')))
                    WHEN d.deal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(dl.deal_code, ''), CASE WHEN dl.deal_code IS NOT NULL AND dl.deal_code != '' THEN ' — ' ELSE '' END, COALESCE(dl.name, '')))
                    WHEN d.proposal_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(prop.proposal_number, ''), CASE WHEN prop.proposal_number IS NOT NULL AND prop.proposal_number != '' THEN ' — ' ELSE '' END, COALESCE(prop.title, '')))
                    WHEN d.contract_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(ctr.contract_number, ''), CASE WHEN ctr.contract_number IS NOT NULL AND ctr.contract_number != '' THEN ' — ' ELSE '' END, COALESCE(ctr.title, '')))
                    WHEN d.invoice_id IS NOT NULL THEN TRIM(CONCAT(COALESCE(inv.invoice_number, ''), CASE WHEN inv.invoice_number IS NOT NULL AND inv.invoice_number != '' THEN ' — ' ELSE '' END, COALESCE(inv.title, '')))
                    ELSE NULL
                END
            ) AS display_related_record,
            COALESCE(co.name, '') AS company_name,
            TRIM(CONCAT(COALESCE(own.first_name,''),' ',COALESCE(own.last_name,''))) AS owner_name,
            d.status, d.is_archived,
            DATE_FORMAT(d.created_at, '%Y-%m-%d') AS uploaded_date,
            TRIM(CONCAT(COALESCE(upl.first_name,''),' ',COALESCE(upl.last_name,''))) AS uploader_name
        FROM documents d
        LEFT JOIN companies co   ON co.id = d.company_id AND co.organization_id = d.organization_id
        LEFT JOIN users own      ON own.id = d.owner_id   AND own.organization_id = d.organization_id
        LEFT JOIN users upl      ON upl.id = d.uploaded_by AND upl.organization_id = d.organization_id
        LEFT JOIN projects prj   ON prj.id = d.project_id AND prj.organization_id = d.organization_id
        LEFT JOIN deals dl       ON dl.id = d.deal_id AND dl.organization_id = d.organization_id
        LEFT JOIN proposals prop ON prop.id = d.proposal_id AND prop.organization_id = d.organization_id
        LEFT JOIN contracts ctr  ON ctr.id = d.contract_id AND ctr.organization_id = d.organization_id
        LEFT JOIN invoices inv   ON inv.id = d.invoice_id AND inv.organization_id = d.organization_id
        WHERE d.organization_id = ?
        ORDER BY d.created_at DESC, d.id DESC
    ");
    $stmtList->execute([$organizationId]);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'documents_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Document Code', 'Title', 'File Name', 'Type', 'File Size', 'Category', 'Related Record', 'Client / Company', 'Owner', 'Status', 'Archived', 'Uploaded Date', 'Uploaded By']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['document_code'], $r['title'], $r['original_filename'], $r['document_type'],
            $r['file_size'], $r['category'], $r['display_related_record'], $r['company_name'],
            $r['owner_name'], $r['status'], $r['is_archived'] ? 'Yes' : 'No',
            $r['uploaded_date'], $r['uploader_name'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Fallback ─────────────────────────────────────────────────────────────
docs_json(false, "Unknown action: {$action}", [], 400);