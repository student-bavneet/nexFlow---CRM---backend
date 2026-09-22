<?php
/**
 * NexFlow CRM — Client Portal Proposals API Endpoint
 * Handles secure listing, details retrieval, and client lifecycle state mutations
 * (View, Accept, Decline, Request Changes) with strict multi-tenant isolation,
 * CSRF verification, and atomic database transaction safety.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-proposals-data.php';

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
$mutatingActions = ['mark_viewed', 'accept', 'decline', 'request_changes'];
if (in_array($action, $mutatingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        client_json_response(false, 'Method Not Allowed. POST is required.', [], 405);
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!verify_client_csrf($token)) {
        client_json_response(false, 'Invalid or expired CSRF token. Please refresh the page and try again.', [], 403);
    }
}

// =========================================================================
// ACTION: summary (KPI Statistics)
// =========================================================================
if ($action === 'summary') {
    $data = client_get_proposals_data($pdo, $orgId, $companyId);
    client_json_response(true, 'Summary retrieved successfully.', [
        'kpi'            => $data['kpis'],
        'currency'       => $data['currency'],
        'currencySymbol' => $data['currencySymbol'],
    ]);
}

// =========================================================================
// ACTION: list (Filtered & Sorted Proposals)
// =========================================================================
if ($action === 'list') {
    $search       = trim($_GET['q'] ?? $_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? 'All');
    $sortBy       = trim($_GET['sort'] ?? 'newest');

    // Organization Currency
    $stmtOrg = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmtOrg->execute([$orgId]);
    $orgCurrency = (string)($stmtOrg->fetchColumn() ?: 'USD ($)');

    $where   = [
        'p.organization_id = ?',
        'p.company_id = ?',
        "p.status != 'Draft'" // Internal drafts are never visible
    ];
    $params  = [$orgId, $companyId];

    // Search filter
    if ($search !== '') {
        $where[] = '(p.title LIKE ? OR p.proposal_number LIKE ? OR p.deal_name LIKE ? OR p.project_name LIKE ?)';
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    // Status Filter (Mapping UI filter to real database statuses)
    if ($statusFilter !== '' && $statusFilter !== 'All') {
        if ($statusFilter === 'Pending') {
            $where[] = "p.status IN ('Sent', 'Viewed', 'Changes Requested') AND (p.expiry_date >= CURDATE() OR p.expiry_date IS NULL)";
        } elseif ($statusFilter === 'Expired') {
            $where[] = "(p.status = 'Expired' OR (p.status NOT IN ('Accepted', 'Declined', 'Draft') AND p.expiry_date < CURDATE()))";
        } elseif ($statusFilter === 'Accepted') {
            $where[] = "p.status = 'Accepted'";
        } elseif ($statusFilter === 'Declined') {
            $where[] = "p.status = 'Declined'";
        } elseif ($statusFilter === 'Sent') {
            $where[] = "p.status = 'Sent' AND (p.expiry_date >= CURDATE() OR p.expiry_date IS NULL)";
        } elseif ($statusFilter === 'Viewed') {
            $where[] = "p.status = 'Viewed' AND (p.expiry_date >= CURDATE() OR p.expiry_date IS NULL)";
        } elseif ($statusFilter === 'Changes Requested') {
            $where[] = "p.status = 'Changes Requested' AND (p.expiry_date >= CURDATE() OR p.expiry_date IS NULL)";
        } else {
            // Any other filter that doesn't match returns empty safely
            $where[] = 'p.status = ?';
            $params[] = $statusFilter;
        }
    }

    $whereSql = implode(' AND ', $where);

    // Sorting
    $orderSql = 'p.issue_date DESC, p.id DESC';
    if ($sortBy === 'oldest') {
        $orderSql = 'p.issue_date ASC, p.id ASC';
    } elseif ($sortBy === 'amount') {
        $orderSql = 'p.total DESC, p.id DESC';
    } elseif ($sortBy === 'expiry') {
        $orderSql = 'p.expiry_date ASC, p.id ASC';
    } elseif ($sortBy === 'name') {
        $orderSql = 'p.title ASC, p.id DESC';
    } elseif ($sortBy === 'status') {
        $orderSql = 'p.status ASC, p.id DESC';
    }

    $stmtList = $pdo->prepare("
        SELECT 
            p.id,
            p.organization_id,
            p.proposal_number,
            p.title,
            p.company_id,
            COALESCE(p.company_name, '') AS company_name,
            p.contact_id,
            COALESCE(p.contact_name, '') AS contact_name,
            p.deal_id,
            COALESCE(p.deal_name, '') AS deal_name,
            p.project_id,
            COALESCE(p.project_name, '') AS project_name,
            p.prepared_by,
            COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email, 'Account Lead') AS prepared_by_name,
            u.email AS prepared_by_email,
            p.issue_date,
            p.expiry_date,
            p.scope,
            p.subtotal,
            p.discount,
            p.tax_rate,
            p.tax,
            p.total,
            COALESCE(p.currency, ?) AS currency,
            p.payment_terms,
            p.terms,
            p.status,
            p.change_request_type,
            p.change_request_message,
            p.change_requested_by,
            p.change_requested_at,
            p.created_at,
            p.updated_at
        FROM proposals p
        LEFT JOIN users u 
            ON u.id = p.prepared_by 
           AND u.organization_id = ?
        WHERE $whereSql
        ORDER BY $orderSql
    ");

    $executeParams = array_merge([$orgCurrency, $orgId], $params);
    $stmtList->execute($executeParams);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $curr = $row['currency'] ?: $orgCurrency;
        $sym = client_extract_currency_symbol($curr);
        $displayStatus = client_compute_proposal_display_status($row);
        $tot = (float)($row['total'] ?? 0.00);

        $row['display_status']  = $displayStatus;
        $row['currency_symbol'] = $sym;
        $row['formatted_total'] = $sym . number_format($tot, 2);
        $row['is_actionable']   = in_array($displayStatus, ['Sent', 'Viewed', 'Changes Requested'], true);
        $items[] = $row;
    }

    client_json_response(true, 'Proposals retrieved successfully.', [
        'items'     => $items,
        'proposals' => $items,
        'count'     => count($items),
    ]);
}

// =========================================================================
// ACTION: get (Proposal Details with Deliverables & Client-Safe Timeline)
// =========================================================================
if ($action === 'get') {
    $proposalId = (int)($_GET['id'] ?? 0);
    if ($proposalId <= 0) {
        client_json_response(false, 'Valid proposal ID is required.', [], 400);
    }

    // 1. Authorize Proposal strictly for authenticated org and company
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email, 'Account Lead') AS prepared_by_name,
            u.email AS prepared_by_email
        FROM proposals p
        LEFT JOIN users u 
            ON u.id = p.prepared_by 
           AND u.organization_id = ?
        WHERE p.id = ? 
          AND p.organization_id = ? 
          AND p.company_id = ?
          AND p.status != 'Draft'
        LIMIT 1
    ");
    $stmt->execute([$orgId, $proposalId, $orgId, $companyId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        client_json_response(false, 'Proposal not found or access denied.', [], 404);
    }

    // Organization Currency
    $stmtOrg = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmtOrg->execute([$orgId]);
    $orgCurrency = (string)($stmtOrg->fetchColumn() ?: 'USD ($)');

    $curr = $proposal['currency'] ?: $orgCurrency;
    $sym  = client_extract_currency_symbol($curr);
    $displayStatus = client_compute_proposal_display_status($proposal);
    $isActionable  = in_array($displayStatus, ['Sent', 'Viewed', 'Changes Requested'], true);

    // 2. Query Itemized Deliverables (only after proposal has been authorized)
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
        WHERE organization_id = ? 
          AND proposal_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtItems->execute([$orgId, $proposalId]);
    $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $deliverables = array_map(function($it) {
        return [
            'id'          => (int)$it['id'],
            'name'        => $it['name'],
            'desc'        => $it['description'] ?: '',
            'description' => $it['description'] ?: '',
            'qty'         => (float)$it['qty'],
            'rate'        => (float)$it['rate'],
            'amount'      => (float)$it['amount'],
            'sort_order'  => (int)$it['sort_order'],
        ];
    }, $rawItems);

    // 3. Query Client-Safe Timeline History with Content Privacy Sanitization
    $stmtAct = $pdo->prepare("
        SELECT 
            a.id,
            a.activity_type,
            a.title,
            a.description,
            a.created_at,
            DATE_FORMAT(a.created_at, '%b %d, %Y') AS date,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS author_name
        FROM team_activities a
        LEFT JOIN users u 
            ON u.id = a.created_by 
           AND u.organization_id = ?
        WHERE a.organization_id = ? 
          AND a.related_entity = 'proposals' 
          AND a.related_entity_id = ?
          AND a.activity_type IN (
              'proposal_created', 
              'proposal_sent', 
              'proposal_revised_sent', 
              'proposal_viewed', 
              'proposal_accepted', 
              'proposal_declined', 
              'proposal_change_requested'
          )
        ORDER BY a.id ASC
    ");
    $stmtAct->execute([$orgId, $orgId, $proposalId]);
    $rawTimeline = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    $timeline = [];
    foreach ($rawTimeline as $act) {
        $type = $act['activity_type'];
        $cleanText = '';
        $cleanTitle = '';

        if ($type === 'proposal_created') {
            $cleanTitle = 'Proposal Created';
            $cleanText  = "Proposal was created by account lead ({$proposal['prepared_by_name']})";
        } elseif ($type === 'proposal_sent') {
            $cleanTitle = 'Proposal Sent';
            $cleanText  = 'Proposal was sent to client for review';
        } elseif ($type === 'proposal_revised_sent') {
            $cleanTitle = 'Revised Proposal Sent';
            $cleanText  = 'A revised proposal was sent to client for review';
        } elseif ($type === 'proposal_viewed') {
            $cleanTitle = 'Proposal Viewed';
            $cleanText  = 'Proposal was viewed in Client Portal';
        } elseif ($type === 'proposal_accepted') {
            $cleanTitle = 'Proposal Accepted';
            $cleanText  = 'Proposal was accepted by client';
        } elseif ($type === 'proposal_declined') {
            $cleanTitle = 'Proposal Declined';
            $cleanText  = !empty($act['description']) ? htmlspecialchars($act['description']) : 'Proposal was declined by client';
        } elseif ($type === 'proposal_change_requested') {
            $cleanTitle = 'Change Request Submitted';
            $cleanText  = !empty($act['description']) ? htmlspecialchars($act['description']) : 'Changes were requested by client';
        }

        if ($cleanText !== '') {
            $timeline[] = [
                'id'    => (int)$act['id'],
                'title' => $cleanTitle,
                'text'  => $cleanText,
                'date'  => $act['date'],
            ];
        }
    }

    $proposal['display_status']  = $displayStatus;
    $proposal['currency_symbol'] = $sym;
    $proposal['formatted_total'] = $sym . number_format((float)$proposal['total'], 2);
    $proposal['formattedTotal']  = $proposal['formatted_total'];
    $proposal['is_actionable']   = $isActionable;
    $proposal['deliverables']    = $deliverables;
    $proposal['items']           = $deliverables;
    $proposal['timeline']        = $timeline;

    client_json_response(true, 'Proposal details retrieved.', $proposal);
}

// =========================================================================
// ACTION: mark_viewed (Auto-transition Sent -> Viewed with transaction)
// =========================================================================
if ($action === 'mark_viewed') {
    $proposalId = (int)($_POST['id'] ?? 0);
    if ($proposalId <= 0) {
        client_json_response(false, 'Valid proposal ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        // Authorize proposal for company
        $stmtSel = $pdo->prepare("
            SELECT id, proposal_number, status 
            FROM proposals 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
            FOR UPDATE
        ");
        $stmtSel->execute([$proposalId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Proposal not found or access denied.', [], 404);
        }

        if ($row['status'] === 'Sent') {
            $stmtUpd = $pdo->prepare("
                UPDATE proposals 
                SET status = 'Viewed', updated_at = NOW() 
                WHERE id = ? AND organization_id = ? AND company_id = ?
            ");
            $stmtUpd->execute([$proposalId, $orgId, $companyId]);

            // Log client-safe activity
            $stmtAct = $pdo->prepare("
                INSERT INTO team_activities (
                    organization_id, user_id, activity_type, title, description,
                    related_entity, related_entity_id, created_by, created_at
                ) VALUES (
                    ?, ?, 'proposal_viewed', 'Proposal Viewed', ?,
                    'proposals', ?, ?, NOW()
                )
            ");
            $stmtAct->execute([
                $orgId,
                $contactId,
                "Proposal {$row['proposal_number']} viewed by client ({$contactName})",
                $proposalId,
                $contactId
            ]);
        }

        $pdo->commit();
        client_json_response(true, 'Proposal marked as viewed.', [
            'id'     => $proposalId,
            'status' => 'Viewed'
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to update proposal state: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: accept (Client Accepts Proposal)
// =========================================================================
if ($action === 'accept') {
    $proposalId = (int)($_POST['id'] ?? 0);
    if ($proposalId <= 0) {
        client_json_response(false, 'Valid proposal ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        // Authorize proposal for company
        $stmtSel = $pdo->prepare("
            SELECT id, proposal_number, title, total, status, expiry_date 
            FROM proposals 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
              AND status != 'Draft'
            FOR UPDATE
        ");
        $stmtSel->execute([$proposalId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Proposal not found or access denied.', [], 404);
        }

        // Actionable State Verification
        if (!in_array($row['status'], ['Sent', 'Viewed', 'Changes Requested'], true)) {
            $pdo->rollBack();
            client_json_response(false, 'Proposal cannot be accepted in its current state (' . $row['status'] . ').', [], 400);
        }

        // Server-Side Expiration Verification
        if (!empty($row['expiry_date']) && $row['expiry_date'] < date('Y-m-d')) {
            $pdo->rollBack();
            client_json_response(false, 'This proposal expired on ' . $row['expiry_date'] . ' and can no longer be accepted.', [], 400);
        }

        // Transition to Accepted
        $stmtUpd = $pdo->prepare("
            UPDATE proposals 
            SET status = 'Accepted', updated_at = NOW() 
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([$proposalId, $orgId, $companyId]);

        // Log client-safe activity
        $stmtAct = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, 'proposal_accepted', 'Proposal Accepted', ?,
                'proposals', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Proposal {$row['proposal_number']} accepted by client ({$contactName})",
            $proposalId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, "Proposal {$row['proposal_number']} successfully accepted!", [
            'id'     => $proposalId,
            'status' => 'Accepted'
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to accept proposal: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: decline (Client Declines Proposal)
// =========================================================================
if ($action === 'decline') {
    $proposalId = (int)($_POST['id'] ?? 0);
    $reason     = trim((string)($_POST['reason'] ?? ''));

    if ($proposalId <= 0) {
        client_json_response(false, 'Valid proposal ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        $stmtSel = $pdo->prepare("
            SELECT id, proposal_number, title, status, expiry_date 
            FROM proposals 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
              AND status != 'Draft'
            FOR UPDATE
        ");
        $stmtSel->execute([$proposalId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Proposal not found or access denied.', [], 404);
        }

        if (!in_array($row['status'], ['Sent', 'Viewed', 'Changes Requested'], true)) {
            $pdo->rollBack();
            client_json_response(false, 'Proposal cannot be declined in its current state (' . $row['status'] . ').', [], 400);
        }

        if (!empty($row['expiry_date']) && $row['expiry_date'] < date('Y-m-d')) {
            $pdo->rollBack();
            client_json_response(false, 'This proposal expired on ' . $row['expiry_date'] . ' and cannot be modified.', [], 400);
        }

        $stmtUpd = $pdo->prepare("
            UPDATE proposals 
            SET status = 'Declined', updated_at = NOW() 
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([$proposalId, $orgId, $companyId]);

        // Log client-safe activity
        $reasonText = $reason !== '' ? ": " . $reason : '';
        $stmtAct = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, 'proposal_declined', 'Proposal Declined', ?,
                'proposals', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Proposal {$row['proposal_number']} declined by client ({$contactName}){$reasonText}",
            $proposalId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, "Proposal {$row['proposal_number']} declined.", [
            'id'     => $proposalId,
            'status' => 'Declined'
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to decline proposal: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: request_changes (Client Submits Change Request)
// =========================================================================
if ($action === 'request_changes') {
    $proposalId = (int)($_POST['id'] ?? 0);
    $message    = trim((string)($_POST['message'] ?? ''));
    $changeType = trim((string)($_POST['change_type'] ?? 'Scope of Work'));
    $priority   = trim((string)($_POST['priority'] ?? 'Normal'));

    if ($proposalId <= 0) {
        client_json_response(false, 'Valid proposal ID is required.', [], 400);
    }
    if ($message === '') {
        client_json_response(false, 'Please describe the changes you would like to request.', [], 422);
    }

    $validTypes = ['Pricing', 'Payment Terms', 'Deliverables', 'Scope of Work', 'Timeline', 'Other'];
    if (!in_array($changeType, $validTypes, true)) {
        $changeType = 'Scope of Work';
    }

    $pdo->beginTransaction();
    try {
        $stmtSel = $pdo->prepare("
            SELECT id, proposal_number, title, status, expiry_date 
            FROM proposals 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
              AND status != 'Draft'
            FOR UPDATE
        ");
        $stmtSel->execute([$proposalId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Proposal not found or access denied.', [], 404);
        }

        if (!in_array($row['status'], ['Sent', 'Viewed', 'Changes Requested'], true)) {
            $pdo->rollBack();
            client_json_response(false, 'Changes cannot be requested for proposal in state (' . $row['status'] . ').', [], 400);
        }

        if (!empty($row['expiry_date']) && $row['expiry_date'] < date('Y-m-d')) {
            $pdo->rollBack();
            client_json_response(false, 'This proposal expired on ' . $row['expiry_date'] . ' and cannot be modified.', [], 400);
        }

        // Update proposal change request fields
        $stmtUpd = $pdo->prepare("
            UPDATE proposals 
            SET status = 'Changes Requested',
                change_request_type = ?,
                change_request_message = ?,
                change_requested_by = ?,
                change_requested_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([
            $changeType,
            $message,
            $contactName,
            $proposalId,
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
                ?, ?, 'proposal_change_requested', 'Change Request Submitted', ?,
                'proposals', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Client ({$contactName}) requested changes ({$changeType}): \"{$shortMsg}\"",
            $proposalId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, 'Change request submitted — Your request has been sent to the account team.', [
            'id'     => $proposalId,
            'status' => 'Changes Requested',
            'changeRequest' => [
                'type'        => $changeType,
                'message'     => $message,
                'requestedBy' => $contactName,
                'date'        => date('M d, Y')
            ]
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to submit change request: ' . $e->getMessage(), [], 500);
    }
}

// Fallback: Unknown action
client_json_response(false, "Unknown action: {$action}", [], 400);