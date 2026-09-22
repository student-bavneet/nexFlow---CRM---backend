<?php
/**
 * NexFlow CRM — Client Portal Contracts API Endpoint
 * Handles secure listing, details retrieval, and client lifecycle state mutations
 * (View, Sign, Decline, Request Changes) with strict multi-tenant isolation,
 * CSRF verification, and atomic database transaction safety.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-contracts-data.php';

// 1. Session Authentication Guard (401 if unauthenticated)
$client = client_current_user();
if (!$client) {
    client_json_response(false, 'Unauthorized access. Please log in.', [], 401);
}

$orgId       = (int)$client['organization_id'];
$companyId   = (int)$client['company_id'];
$contactId   = (int)$client['contact_id'];
$contactName = trim((string)($client['contact_name'] ?? 'Client'));

if ($orgId <= 0 || $companyId <= 0) {
    client_json_response(false, 'Invalid client session context.', [], 400);
}

// 2. Database Connection
$pdo = nexflow_db();

// 3. Action Dispatcher
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'list');

// 4. CSRF & Method Guard for Mutating Actions
$mutatingActions = ['sign', 'request_changes', 'decline', 'mark_viewed'];
if (in_array($action, $mutatingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        client_json_response(false, 'Method Not Allowed. POST is required.', [], 405);
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($token)) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $parsed = json_decode($raw, true);
            if (is_array($parsed) && !empty($parsed['csrf_token'])) {
                $token = $parsed['csrf_token'];
            }
        }
    }
    if (!verify_client_csrf($token)) {
        client_json_response(false, 'Invalid or expired CSRF token. Please refresh the page and try again.', [], 403);
    }
}

// =========================================================================
// ACTION: summary (KPI Statistics)
// =========================================================================
if ($action === 'summary') {
    $data = client_get_contracts_data($pdo, $orgId, $companyId);
    client_json_response(true, 'Summary retrieved successfully.', [
        'kpi'            => $data['kpis'],
        'currency'       => $data['currency'],
        'currencySymbol' => $data['currencySymbol'],
    ]);
}

// =========================================================================
// ACTION: list (Filtered & Sorted Contracts)
// =========================================================================
if ($action === 'list') {
    $search       = trim($_GET['q'] ?? $_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? 'All');
    $typeFilter   = trim($_GET['type'] ?? 'All');
    $sortBy       = trim($_GET['sort'] ?? 'newest');

    // Organization Currency
    $stmtOrg = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmtOrg->execute([$orgId]);
    $orgCurrency = (string)($stmtOrg->fetchColumn() ?: 'USD ($)');

    $where   = [
        'c.organization_id = ?',
        'c.company_id = ?',
        "c.status != 'Draft'" // Internal drafts are never visible
    ];
    $params  = [$orgId, $companyId];

    // Search filter
    if ($search !== '') {
        $where[] = '(c.title LIKE ? OR c.contract_number LIKE ? OR c.reference_number LIKE ? OR c.type LIKE ?)';
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    // Contract Type filter
    if ($typeFilter !== '' && $typeFilter !== 'All') {
        $where[] = 'c.type = ?';
        $params[] = $typeFilter;
    }

    // Status Filter (Mapping UI filter to real database statuses)
    if ($statusFilter !== '' && $statusFilter !== 'All') {
        if ($statusFilter === 'Pending Signature') {
            $where[] = "c.is_client_signed = 0 AND c.status IN ('Sent', 'Viewed', 'Pending Signature', 'Changes Requested') AND (c.end_date >= CURDATE() OR c.end_date IS NULL)";
        } elseif ($statusFilter === 'Active') {
            $where[] = "c.status IN ('Active', 'Signed') AND (c.end_date >= CURDATE() OR c.end_date IS NULL)";
        } elseif ($statusFilter === 'Expired') {
            $where[] = "(c.status = 'Expired' OR (c.end_date < CURDATE()))";
        } elseif ($statusFilter === 'Sent') {
            $where[] = "c.status = 'Sent'";
        } elseif ($statusFilter === 'Viewed') {
            $where[] = "c.status = 'Viewed'";
        } elseif ($statusFilter === 'Terminated') {
            $where[] = "c.status = 'Terminated'";
        } elseif ($statusFilter === 'Draft') {
            // Client never has draft access, return empty safely
            $where[] = "1 = 0";
        } else {
            $where[] = 'c.status = ?';
            $params[] = $statusFilter;
        }
    }

    $whereSql = implode(' AND ', $where);

    // Sorting
    $orderSql = 'c.start_date DESC, c.id DESC';
    if ($sortBy === 'oldest') {
        $orderSql = 'c.start_date ASC, c.id ASC';
    } elseif ($sortBy === 'value') {
        $orderSql = 'c.value DESC, c.id DESC';
    } elseif ($sortBy === 'end') {
        $orderSql = 'c.end_date ASC, c.id ASC';
    } elseif ($sortBy === 'name') {
        $orderSql = 'c.title ASC, c.id DESC';
    } elseif ($sortBy === 'status') {
        $orderSql = 'c.status ASC, c.id DESC';
    }

    $stmtList = $pdo->prepare("
        SELECT 
            c.id,
            c.organization_id,
            c.contract_number,
            c.title,
            c.reference_number,
            c.company_id,
            COALESCE(comp.name, c.company_name) AS company_name,
            c.contact_id,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, ''))), ''), c.contact_name) AS contact_name,
            COALESCE(cont.email, c.client_email) AS client_email,
            c.type,
            c.value,
            COALESCE(c.currency, ?) AS currency,
            c.start_date,
            c.end_date,
            c.status,
            c.signature_status,
            c.is_client_signed,
            c.signed_date,
            c.owner_id,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email, 'Account Lead') AS owner_name,
            u.email AS owner_email,
            c.renewal_type,
            c.notice_days,
            c.next_renewal_date,
            c.overview,
            c.terms,
            c.payment_terms,
            c.change_request_text,
            c.change_request_contact,
            c.change_request_date,
            c.created_at,
            c.updated_at
        FROM contracts c
        LEFT JOIN companies comp ON comp.id = c.company_id AND comp.organization_id = ?
        LEFT JOIN contacts cont ON cont.id = c.contact_id AND cont.organization_id = ?
        LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = ?
        WHERE $whereSql
        ORDER BY $orderSql
    ");

    $executeParams = array_merge([$orgCurrency, $orgId, $orgId, $orgId], $params);
    $stmtList->execute($executeParams);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $today = new DateTime('today');
    $items = [];
    foreach ($rows as $row) {
        $curr = $row['currency'] ?: $orgCurrency;
        $sym = client_extract_contract_currency_symbol($curr);
        $displayStatus = client_compute_contract_display_status($row);
        $val = (float)($row['value'] ?? 0.00);

        $daysRemaining = null;
        if (!empty($row['end_date'])) {
            $end = new DateTime($row['end_date']);
            $daysRemaining = (int)$today->diff($end)->format('%r%a');
        }

        $isPendingSig = ((int)$row['is_client_signed'] === 0) && in_array($displayStatus, ['Sent', 'Viewed', 'Pending Signature', 'Changes Requested'], true);

        $row['display_status']       = $displayStatus;
        $row['currency_symbol']      = $sym;
        $row['formatted_value']      = $sym . number_format($val, 2);
        $row['days_remaining']       = $daysRemaining;
        $row['is_actionable']        = $isPendingSig;
        $row['start_date_formatted'] = !empty($row['start_date']) ? date('M d, Y', strtotime($row['start_date'])) : '—';
        $row['end_date_formatted']   = !empty($row['end_date']) ? date('M d, Y', strtotime($row['end_date'])) : '—';

        $items[] = $row;
    }

    client_json_response(true, 'Contracts retrieved successfully.', [
        'items'     => $items,
        'contracts' => $items,
        'count'     => count($items),
    ]);
}

// =========================================================================
// ACTION: get (Contract Details with Files & Client-Safe Timeline)
// =========================================================================
if ($action === 'get') {
    $contractId = (int)($_GET['id'] ?? 0);
    $contractNum = trim((string)($_GET['contract_number'] ?? ''));

    if ($contractId <= 0 && $contractNum === '') {
        client_json_response(false, 'Valid contract identifier is required.', [], 400);
    }

    // 1. Authorize Contract strictly for authenticated org and company
    $sql = "
        SELECT 
            c.*,
            COALESCE(comp.name, c.company_name) AS company_name,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, ''))), ''), c.contact_name) AS contact_name,
            COALESCE(cont.email, c.client_email) AS client_email,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email, 'Account Lead') AS owner_name,
            u.email AS owner_email
        FROM contracts c
        LEFT JOIN companies comp ON comp.id = c.company_id AND comp.organization_id = ?
        LEFT JOIN contacts cont ON cont.id = c.contact_id AND cont.organization_id = ?
        LEFT JOIN users u ON u.id = c.owner_id AND u.organization_id = ?
        WHERE c.organization_id = ? 
          AND c.company_id = ?
          AND c.status != 'Draft'
    ";

    $params = [$orgId, $orgId, $orgId, $orgId, $companyId];
    if ($contractId > 0) {
        $sql .= " AND c.id = ? LIMIT 1";
        $params[] = $contractId;
    } else {
        $sql .= " AND c.contract_number = ? LIMIT 1";
        $params[] = $contractNum;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        client_json_response(false, 'Contract not found or access denied.', [], 404);
    }

    $resolvedContractId = (int)$contract['id'];

    // Auto-transition Sent -> Viewed if currently Sent
    if ($contract['status'] === 'Sent') {
        try {
            $stmtUpdView = $pdo->prepare("
                UPDATE contracts 
                SET status = 'Viewed', signature_status = 'Viewed', updated_at = NOW() 
                WHERE id = ? AND organization_id = ? AND company_id = ?
            ");
            $stmtUpdView->execute([$resolvedContractId, $orgId, $companyId]);
            $contract['status'] = 'Viewed';
            $contract['signature_status'] = 'Viewed';

            $stmtAct = $pdo->prepare("
                INSERT INTO team_activities (
                    organization_id, user_id, activity_type, title, description,
                    related_entity, related_entity_id, created_by, created_at
                ) VALUES (
                    ?, ?, 'contract_viewed', 'Contract Viewed', ?,
                    'contracts', ?, ?, NOW()
                )
            ");
            $stmtAct->execute([
                $orgId,
                $contactId,
                "Contract {$contract['contract_number']} viewed in Client Portal by {$contactName}",
                $resolvedContractId,
                $contactId
            ]);
        } catch (Throwable $e) {
            // Non-blocking
        }
    }

    // Organization Currency
    $stmtOrg = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmtOrg->execute([$orgId]);
    $orgCurrency = (string)($stmtOrg->fetchColumn() ?: 'USD ($)');

    $curr = $contract['currency'] ?: $orgCurrency;
    $sym  = client_extract_contract_currency_symbol($curr);
    $displayStatus = client_compute_contract_display_status($contract);
    $val = (float)($contract['value'] ?? 0.00);

    $today = new DateTime('today');
    $daysRemaining = null;
    if (!empty($contract['end_date'])) {
        $end = new DateTime($contract['end_date']);
        $daysRemaining = (int)$today->diff($end)->format('%r%a');
    }

    $isPendingSig = ((int)$contract['is_client_signed'] === 0) && in_array($displayStatus, ['Sent', 'Viewed', 'Pending Signature', 'Changes Requested'], true);

    // 2. Query Attached Documents
    $stmtFiles = $pdo->prepare("
        SELECT 
            id, file_name, file_size, mime_type, created_at
        FROM contract_files
        WHERE organization_id = ? AND contract_id = ?
        ORDER BY id DESC
    ");
    $stmtFiles->execute([$orgId, $resolvedContractId]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

    // 3. Query Client-Safe Activity Timeline (Sanitized: NO team_notes or internal ops)
    $stmtAct = $pdo->prepare("
        SELECT 
            a.id,
            a.activity_type,
            a.title,
            a.description,
            a.created_at,
            DATE_FORMAT(a.created_at, '%b %d, %Y') AS date,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))),
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email, 'Account Lead') AS author_name
        FROM team_activities a
        LEFT JOIN users u 
            ON u.id = a.created_by 
           AND u.organization_id = ?
        WHERE a.organization_id = ? 
          AND a.related_entity = 'contracts' 
          AND a.related_entity_id = ?
          AND a.activity_type IN (
              'contract_created', 
              'contract_sent', 
              'contract_viewed', 
              'contract_signed', 
              'contract_declined', 
              'contract_change_requested',
              'contract_updated'
          )
        ORDER BY a.id ASC
    ");
    $stmtAct->execute([$orgId, $orgId, $resolvedContractId]);
    $rawTimeline = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    $timeline = [];
    foreach ($rawTimeline as $act) {
        $type = $act['activity_type'];
        $cleanTitle = '';
        $cleanText = '';

        if ($type === 'contract_created') {
            $cleanTitle = 'Contract Created';
            $cleanText  = "Contract agreement was created by account lead ({$contract['owner_name']})";
        } elseif ($type === 'contract_sent') {
            $cleanTitle = 'Contract Sent';
            $cleanText  = 'Contract was sent to client for review and signature';
        } elseif ($type === 'contract_viewed') {
            $cleanTitle = 'Contract Viewed';
            $cleanText  = 'Contract was viewed in Client Portal';
        } elseif ($type === 'contract_signed') {
            $cleanTitle = 'Contract Signed';
            $cleanText  = 'Contract was electronically signed by client';
        } elseif ($type === 'contract_declined') {
            $cleanTitle = 'Contract Declined';
            $cleanText  = !empty($act['description']) ? htmlspecialchars($act['description']) : 'Contract was declined by client';
        } elseif ($type === 'contract_change_requested') {
            $cleanTitle = 'Change Request Submitted';
            $cleanText  = !empty($act['description']) ? htmlspecialchars($act['description']) : 'Changes were requested by client';
        } elseif ($type === 'contract_updated') {
            $cleanTitle = 'Contract Revised';
            $cleanText  = 'Contract details were updated by account team';
        }

        if ($cleanTitle !== '') {
            $timeline[] = [
                'id'    => (int)$act['id'],
                'title' => $cleanTitle,
                'text'  => $cleanText,
                'date'  => $act['date'],
            ];
        }
    }

    $contract['display_status']       = $displayStatus;
    $contract['currency_symbol']      = $sym;
    $contract['formatted_value']      = $sym . number_format($val, 2);
    $contract['days_remaining']       = $daysRemaining;
    $contract['is_actionable']        = $isPendingSig;
    $contract['start_date_formatted'] = !empty($contract['start_date']) ? date('M d, Y', strtotime($contract['start_date'])) : '—';
    $contract['end_date_formatted']   = !empty($contract['end_date']) ? date('M d, Y', strtotime($contract['end_date'])) : '—';
    $contract['files']                = $files;
    $contract['timeline']             = $timeline;

    client_json_response(true, 'Contract details retrieved.', [
        'contract' => $contract,
    ]);
}

// =========================================================================
// ACTION: mark_viewed
// =========================================================================
if ($action === 'mark_viewed') {
    $contractId = (int)($_POST['id'] ?? 0);
    if ($contractId <= 0) {
        client_json_response(false, 'Valid contract ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        $stmtSel = $pdo->prepare("
            SELECT id, contract_number, status 
            FROM contracts 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
            FOR UPDATE
        ");
        $stmtSel->execute([$contractId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Contract not found or access denied.', [], 404);
        }

        if ($row['status'] === 'Sent') {
            $stmtUpd = $pdo->prepare("
                UPDATE contracts 
                SET status = 'Viewed', signature_status = 'Viewed', updated_at = NOW() 
                WHERE id = ? AND organization_id = ? AND company_id = ?
            ");
            $stmtUpd->execute([$contractId, $orgId, $companyId]);

            $stmtAct = $pdo->prepare("
                INSERT INTO team_activities (
                    organization_id, user_id, activity_type, title, description,
                    related_entity, related_entity_id, created_by, created_at
                ) VALUES (
                    ?, ?, 'contract_viewed', 'Contract Viewed', ?,
                    'contracts', ?, ?, NOW()
                )
            ");
            $stmtAct->execute([
                $orgId,
                $contactId,
                "Contract {$row['contract_number']} viewed in Client Portal by {$contactName}",
                $contractId,
                $contactId
            ]);
        }

        $pdo->commit();
        client_json_response(true, 'Contract marked as viewed.', [
            'id'     => $contractId,
            'status' => 'Viewed'
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to update contract state: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: sign (Client Electronically Signs Contract)
// =========================================================================
if ($action === 'sign') {
    $contractId    = (int)($_POST['id'] ?? 0);
    $signatoryName = trim((string)($_POST['signatory_name'] ?? $contactName));

    if ($contractId <= 0) {
        client_json_response(false, 'Valid contract ID is required.', [], 400);
    }

    if ($signatoryName === '') {
        $signatoryName = $contactName;
    }

    $pdo->beginTransaction();
    try {
        $stmtSel = $pdo->prepare("
            SELECT id, contract_number, title, value, status, end_date, is_client_signed 
            FROM contracts 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
              AND status != 'Draft'
            FOR UPDATE
        ");
        $stmtSel->execute([$contractId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Contract not found or access denied.', [], 404);
        }

        if ((int)$row['is_client_signed'] === 1) {
            $pdo->rollBack();
            client_json_response(false, 'This contract has already been signed.', [], 400);
        }

        if (!in_array($row['status'], ['Sent', 'Viewed', 'Pending Signature', 'Changes Requested'], true)) {
            $pdo->rollBack();
            client_json_response(false, 'Contract cannot be signed in its current state (' . $row['status'] . ').', [], 400);
        }

        // Server-Side Expiration Verification
        if (!empty($row['end_date']) && $row['end_date'] < date('Y-m-d')) {
            $pdo->rollBack();
            client_json_response(false, 'This contract expired on ' . $row['end_date'] . ' and can no longer be signed.', [], 400);
        }

        // Transition to Active & Signed
        $stmtUpd = $pdo->prepare("
            UPDATE contracts 
            SET status = 'Active',
                signature_status = 'Signed',
                is_client_signed = 1,
                signed_date = NOW(),
                updated_at = NOW() 
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([$contractId, $orgId, $companyId]);

        // Log client-safe activity
        $stmtAct = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, 'contract_signed', 'Contract Signed', ?,
                'contracts', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Contract {$row['contract_number']} signed electronically by {$signatoryName}",
            $contractId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, "Contract {$row['contract_number']} signed successfully!", [
            'id'     => $contractId,
            'status' => 'Active',
            'signature_status' => 'Signed',
            'is_client_signed' => 1,
            'signed_date' => date('Y-m-d H:i:s'),
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to sign contract: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: request_changes (Client Submits Change Request)
// =========================================================================
if ($action === 'request_changes') {
    $contractId = (int)($_POST['id'] ?? 0);
    $message    = trim((string)($_POST['message'] ?? ''));

    if ($contractId <= 0) {
        client_json_response(false, 'Valid contract ID is required.', [], 400);
    }
    if ($message === '') {
        client_json_response(false, 'Please describe the changes you would like to request.', [], 422);
    }

    $pdo->beginTransaction();
    try {
        $stmtSel = $pdo->prepare("
            SELECT id, contract_number, title, status, end_date, is_client_signed 
            FROM contracts 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
              AND status != 'Draft'
            FOR UPDATE
        ");
        $stmtSel->execute([$contractId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Contract not found or access denied.', [], 404);
        }

        if ((int)$row['is_client_signed'] === 1) {
            $pdo->rollBack();
            client_json_response(false, 'Changes cannot be requested for an already signed contract.', [], 400);
        }

        if (!in_array($row['status'], ['Sent', 'Viewed', 'Pending Signature', 'Changes Requested'], true)) {
            $pdo->rollBack();
            client_json_response(false, 'Changes cannot be requested for contract in state (' . $row['status'] . ').', [], 400);
        }

        if (!empty($row['end_date']) && $row['end_date'] < date('Y-m-d')) {
            $pdo->rollBack();
            client_json_response(false, 'This contract expired on ' . $row['end_date'] . ' and cannot be modified.', [], 400);
        }

        // Update contract change request fields
        $stmtUpd = $pdo->prepare("
            UPDATE contracts 
            SET status = 'Changes Requested',
                change_request_text = ?,
                change_request_contact = ?,
                change_request_date = NOW(),
                updated_at = NOW()
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([
            $message,
            $contactName,
            $contractId,
            $orgId,
            $companyId
        ]);

        // Log client-safe activity
        $shortMsg = mb_strlen($message) > 60 ? mb_substr($message, 0, 57) . '...' : $message;
        $stmtAct = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, 'contract_change_requested', 'Change Request Submitted', ?,
                'contracts', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Client ({$contactName}) requested changes for contract {$row['contract_number']}: \"{$shortMsg}\"",
            $contractId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, 'Change request submitted — Your account team has been notified.', [
            'id'                  => $contractId,
            'status'              => 'Changes Requested',
            'change_request_text' => $message,
            'change_request_contact' => $contactName,
            'change_request_date' => date('Y-m-d H:i:s'),
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to submit change request: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: decline (Client Declines Contract)
// =========================================================================
if ($action === 'decline') {
    $contractId = (int)($_POST['id'] ?? 0);
    $reason     = trim((string)($_POST['reason'] ?? ''));

    if ($contractId <= 0) {
        client_json_response(false, 'Valid contract ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        $stmtSel = $pdo->prepare("
            SELECT id, contract_number, title, status, end_date, is_client_signed 
            FROM contracts 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
              AND status != 'Draft'
            FOR UPDATE
        ");
        $stmtSel->execute([$contractId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Contract not found or access denied.', [], 404);
        }

        if ((int)$row['is_client_signed'] === 1) {
            $pdo->rollBack();
            client_json_response(false, 'An already signed contract cannot be declined.', [], 400);
        }

        if (!in_array($row['status'], ['Sent', 'Viewed', 'Pending Signature', 'Changes Requested'], true)) {
            $pdo->rollBack();
            client_json_response(false, 'Contract cannot be declined in its current state (' . $row['status'] . ').', [], 400);
        }

        if (!empty($row['end_date']) && $row['end_date'] < date('Y-m-d')) {
            $pdo->rollBack();
            client_json_response(false, 'This contract expired on ' . $row['end_date'] . ' and cannot be modified.', [], 400);
        }

        $stmtUpd = $pdo->prepare("
            UPDATE contracts 
            SET status = 'Declined', signature_status = 'Declined', updated_at = NOW() 
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([$contractId, $orgId, $companyId]);

        // Log client-safe activity
        $reasonText = $reason !== '' ? ": " . $reason : '';
        $stmtAct = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, 'contract_declined', 'Contract Declined', ?,
                'contracts', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Contract {$row['contract_number']} was declined by client ({$contactName}){$reasonText}",
            $contractId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, "Contract {$row['contract_number']} declined.", [
            'id'     => $contractId,
            'status' => 'Declined'
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to decline contract: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: download_file (Download Attached Contract Document)
// =========================================================================
if ($action === 'download_file') {
    $fileId = (int)($_GET['file_id'] ?? 0);
    $contractId = (int)($_GET['contract_id'] ?? 0);

    if ($fileId <= 0 || $contractId <= 0) {
        client_json_response(false, 'Valid contract ID and file ID are required.', [], 400);
    }

    // Verify file belongs to contract and contract belongs to client org & company
    $stmt = $pdo->prepare("
        SELECT f.* 
        FROM contract_files f
        INNER JOIN contracts c ON c.id = f.contract_id
        WHERE f.id = ? 
          AND f.contract_id = ? 
          AND f.organization_id = ?
          AND c.company_id = ?
          AND c.status != 'Draft'
        LIMIT 1
    ");
    $stmt->execute([$fileId, $contractId, $orgId, $companyId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        client_json_response(false, 'Document not found or access denied.', [], 404);
    }

    $rawFilePath = (string)($file['file_path'] ?? '');
    if (trim($rawFilePath) === '') {
        client_json_response(false, 'File record contains no path.', [], 404);
    }

    // 1. Prevent ../ path traversal early
    if (strpos($rawFilePath, '..') !== false) {
        client_json_response(false, 'Access denied: Invalid file path.', [], 403);
    }

    // 2. Resolve permitted base upload directory
    $uploadBaseDir = realpath(__DIR__ . '/../uploads');
    if ($uploadBaseDir === false || !is_dir($uploadBaseDir)) {
        client_json_response(false, 'Upload directory is not accessible.', [], 500);
    }

    // 3. Resolve requested file path relative to root
    $candidatePath = __DIR__ . '/../' . ltrim($rawFilePath, '/\\');
    $resolvedPath = realpath($candidatePath);

    // 4. Ensure file exists and is a regular file
    if ($resolvedPath === false || !is_file($resolvedPath)) {
        client_json_response(false, 'File not found on server.', [], 404);
    }

    // 5. Safe directory-boundary check: must begin with upload directory plus DIRECTORY_SEPARATOR
    $allowedPrefix = rtrim($uploadBaseDir, '\\/') . DIRECTORY_SEPARATOR;
    if ($resolvedPath !== $uploadBaseDir && !str_starts_with($resolvedPath, $allowedPrefix)) {
        client_json_response(false, 'Access denied: File outside allowed directory.', [], 403);
    }

    $mime = !empty($file['mime_type']) ? $file['mime_type'] : 'application/octet-stream';
    $fileName = basename($file['file_name']);

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($resolvedPath));
    readfile($resolvedPath);
    exit;
}

// Fallback: Unknown action
client_json_response(false, "Unknown action: {$action}", [], 400);