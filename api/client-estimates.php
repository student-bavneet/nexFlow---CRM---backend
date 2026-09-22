<?php
/**
 * NexFlow CRM — Client Portal Estimates API Endpoint
 * Handles secure listing, details retrieval, and client lifecycle state mutations
 * (View, Accept, Decline) with strict multi-tenant isolation,
 * CSRF verification, and atomic database transaction safety.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-estimates-data.php';

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

$pdo = nexflow_db();

// 2. Action Dispatcher
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'list');

// 3. CSRF & Method Guard for Mutating Actions
$mutatingActions = ['accept', 'decline'];
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
    $data = client_get_estimates_data($pdo, $orgId, $companyId);
    client_json_response(true, 'Summary retrieved successfully.', [
        'kpi'            => $data['kpis'],
        'currencySymbol' => $data['currencySymbol'],
    ]);
}

// =========================================================================
// ACTION: list (Filtered & Sorted Estimates)
// =========================================================================
if ($action === 'list') {
    $search       = trim($_GET['q'] ?? $_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? 'All');
    $sortBy       = trim($_GET['sort'] ?? 'newest');
    $currencySymbol = client_get_estimate_currency_symbol($pdo, $orgId);

    $where = [
        'er.organization_id = ?',
        'er.company_id = ?'
    ];
    $params = [$orgId, $companyId];

    // Search filter
    if ($search !== '') {
        $where[] = '(er.subject LIKE ? OR er.request_code LIKE ? OR er.services LIKE ? OR d.name LIKE ?)';
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    // Status Filter (Mapping UI filter to real database status)
    if ($statusFilter !== '' && $statusFilter !== 'All') {
        if ($statusFilter === 'Pending') {
            $where[] = "er.status IN ('New', 'In Review', 'More Info Needed', 'Approved')";
        } elseif ($statusFilter === 'Accepted') {
            $where[] = "er.status = 'Converted'";
        } elseif ($statusFilter === 'Declined' || $statusFilter === 'Expired') {
            $where[] = "er.status = 'Rejected'";
        } elseif ($statusFilter === 'Approved') {
            $where[] = "er.status = 'Approved'";
        } elseif ($statusFilter === 'In Review') {
            $where[] = "er.status = 'In Review'";
        } elseif ($statusFilter === 'New') {
            $where[] = "er.status = 'New'";
        } else {
            // Unknown filter returns nothing safely
            $where[] = 'er.status = ?';
            $params[] = $statusFilter;
        }
    }

    $whereSql = implode(' AND ', $where);

    // Sorting
    $orderSql = 'er.created_at DESC, er.id DESC';
    if ($sortBy === 'oldest') {
        $orderSql = 'er.created_at ASC, er.id ASC';
    } elseif ($sortBy === 'name') {
        $orderSql = 'er.subject ASC, er.id DESC';
    } elseif ($sortBy === 'status') {
        $orderSql = 'er.status ASC, er.id DESC';
    }

    $stmtList = $pdo->prepare("
        SELECT 
            er.id,
            er.organization_id,
            er.request_code,
            er.subject,
            er.company_id,
            COALESCE(er.company_name, '') AS company_name,
            er.contact_id,
            COALESCE(er.contact_name, '') AS contact_name,
            er.email,
            er.phone,
            er.services,
            er.budget,
            er.timeline,
            er.source,
            er.owner_id,
            er.status,
            er.description,
            er.requirements,
            er.preferred_start_date,
            er.attachments,
            er.deal_id,
            er.created_at,
            er.updated_at,
            COALESCE(NULLIF(TRIM(u.name), ''), u.email, 'Account Lead') AS owner_name,
            u.email AS owner_email,
            d.name AS deal_name,
            d.deal_code,
            d.value AS deal_value,
            p.id AS project_id,
            p.name AS project_name,
            p.project_code
        FROM estimate_requests er
        LEFT JOIN users u 
            ON u.id = er.owner_id 
           AND u.organization_id = er.organization_id
        LEFT JOIN deals d 
            ON d.id = er.deal_id 
           AND d.organization_id = er.organization_id
        LEFT JOIN projects p 
            ON p.deal_id = d.id 
           AND p.organization_id = er.organization_id
        WHERE $whereSql
        ORDER BY $orderSql
    ");

    $stmtList->execute($params);
    $rawEstimates = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rawEstimates as $row) {
        $meta = client_get_estimate_status_meta($row['status']);
        $row['display_status']   = $meta['label'];
        $row['badge_class']      = $meta['badgeClass'];
        $row['is_actionable']    = $meta['is_actionable'];
        $row['currency_symbol']  = $currencySymbol;
        $bMeta = format_estimate_budget_display($row['budget'], $currencySymbol);
        $row['formatted_amount']   = $bMeta['display'];
        $row['raw_budget']         = $bMeta['raw'];
        $row['is_budget_valid']    = $bMeta['is_valid'];
        $parsedTimeline              = parse_estimate_timeline($row['timeline']);
        $row['formatted_timeline'] = $parsedTimeline;
        $row['delivery_timeline']  = $parsedTimeline;
        $row['timeline']           = $parsedTimeline;
        $row['raw_timeline']       = $row['timeline'];
        $row['issue_date']         = date('Y-m-d', strtotime($row['created_at']));
        $row['expiry_date']        = !empty($row['preferred_start_date']) ? $row['preferred_start_date'] : 'Flexible';
        $items[] = $row;
    }

    client_json_response(true, 'Estimates retrieved successfully.', [
        'items'     => $items,
        'estimates' => $items,
        'count'     => count($items),
    ]);
}

// =========================================================================
// ACTION: get (Estimate Details with Verified Project & Client-Safe Timeline)
// =========================================================================
if ($action === 'get') {
    $estimateId = (int)($_GET['id'] ?? 0);
    if ($estimateId <= 0) {
        client_json_response(false, 'Valid estimate ID is required.', [], 400);
    }

    $currencySymbol = client_get_estimate_currency_symbol($pdo, $orgId);

    // Verified query: Company and Organization strictly checked
    $stmt = $pdo->prepare("
        SELECT 
            er.*,
            COALESCE(NULLIF(TRIM(u.name), ''), u.email, 'Account Lead') AS owner_name,
            u.email AS owner_email,
            d.name AS deal_name,
            d.deal_code,
            d.value AS deal_value,
            p.id AS project_id,
            p.name AS project_name,
            p.project_code
        FROM estimate_requests er
        LEFT JOIN users u 
            ON u.id = er.owner_id 
           AND u.organization_id = er.organization_id
        LEFT JOIN deals d 
            ON d.id = er.deal_id 
           AND d.organization_id = er.organization_id
        LEFT JOIN projects p 
            ON p.deal_id = d.id 
           AND p.organization_id = er.organization_id
        WHERE er.id = ? 
          AND er.organization_id = ? 
          AND er.company_id = ?
        LIMIT 1
    ");
    $stmt->execute([$estimateId, $orgId, $companyId]);
    $est = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$est) {
        client_json_response(false, 'Estimate not found or access denied.', [], 404);
    }

    $meta = client_get_estimate_status_meta($est['status']);
    $est['display_status']   = $meta['label'];
    $est['badge_class']      = $meta['badgeClass'];
    $est['is_actionable']    = $meta['is_actionable'];
    $est['currency_symbol']  = $currencySymbol;
    $bMeta = format_estimate_budget_display($est['budget'], $currencySymbol);
    $est['formatted_amount']   = $bMeta['display'];
    $est['raw_budget']         = $bMeta['raw'];
    $est['is_budget_valid']    = $bMeta['is_valid'];
    $parsedTimeline              = parse_estimate_timeline($est['timeline']);
    $est['formatted_timeline'] = $parsedTimeline;
    $est['delivery_timeline']  = $parsedTimeline;
    $est['timeline']           = $parsedTimeline;
    $est['raw_timeline']       = $est['timeline'];
    $est['issue_date']         = date('Y-m-d', strtotime($est['created_at']));
    $est['expiry_date']        = !empty($est['preferred_start_date']) ? $est['preferred_start_date'] : 'Flexible';

    // Client-safe activity trail (internal notes team_notes are never queried)
    $stmtAct = $pdo->prepare("
        SELECT 
            a.id,
            a.activity_type,
            a.title,
            a.description,
            a.created_at,
            DATE_FORMAT(a.created_at, '%b %d, %Y') AS date
        FROM team_activities a
        WHERE a.organization_id = ? 
          AND a.related_entity = 'estimate_requests' 
          AND a.related_entity_id = ?
          AND a.activity_type IN (
              'request_created', 
              'status_changed', 
              'estimate_created', 
              'converted_to_deal', 
              'request_accepted', 
              'request_declined'
          )
        ORDER BY a.id ASC
    ");
    $stmtAct->execute([$orgId, $estimateId]);
    $rawTimeline = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    $timeline = [];
    foreach ($rawTimeline as $act) {
        $type = $act['activity_type'];
        $cleanTitle = '';
        $cleanText  = '';

        if ($type === 'request_created') {
            $cleanTitle = 'Estimate Request Created';
            $cleanText  = 'Quotation request was submitted';
        } elseif ($type === 'status_changed') {
            $cleanTitle = 'Status Updated';
            $cleanText  = 'Quotation status updated by account team';
        } elseif ($type === 'estimate_created') {
            $cleanTitle = 'Quotation Issued';
            $cleanText  = 'Formal pricing estimate issued to client for approval';
        } elseif ($type === 'converted_to_deal') {
            $cleanTitle = 'Converted to Sales Deal';
            $cleanText  = 'Quotation converted to active opportunity';
        } elseif ($type === 'request_accepted') {
            $cleanTitle = 'Estimate Accepted';
            $cleanText  = 'Estimate accepted by client';
        } elseif ($type === 'request_declined') {
            $cleanTitle = 'Estimate Declined';
            $cleanText  = !empty($act['description']) ? htmlspecialchars($act['description']) : 'Estimate declined by client';
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

    $est['activities']       = $timeline;
    $est['activity_history'] = $timeline;
    $est['timelineLog']      = $timeline;
    // $est['timeline'] remains the clean human-readable delivery timeline string!

    client_json_response(true, 'Estimate details retrieved.', $est);
}

// =========================================================================
// ACTION: accept (Client Accepts Estimate -> Approved to Converted)
// =========================================================================
if ($action === 'accept') {
    $estimateId = (int)($_POST['id'] ?? 0);
    if ($estimateId <= 0) {
        client_json_response(false, 'Valid estimate ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        // Authorize strictly for authenticated company
        $stmtSel = $pdo->prepare("
            SELECT id, request_code, subject, status, deal_id 
            FROM estimate_requests 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
            FOR UPDATE
        ");
        $stmtSel->execute([$estimateId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Estimate not found or access denied.', [], 404);
        }

        // Must be exactly 'Approved' to accept
        if ($row['status'] !== 'Approved') {
            $pdo->rollBack();
            $msg = in_array($row['status'], ['Converted', 'Rejected'], true)
                ? "This estimate has already been finalized ({$row['status']}) and cannot be accepted."
                : "This estimate cannot be accepted in its current state ({$row['status']}).";
            client_json_response(false, $msg, [], 400);
        }

        // Transition to Converted (DO NOT create duplicate deal, preserve existing deal_id)
        $stmtUpd = $pdo->prepare("
            UPDATE estimate_requests 
            SET status = 'Converted', updated_at = NOW() 
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([$estimateId, $orgId, $companyId]);

        // Log client acceptance in existing team_activities architecture
        $stmtAct = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, 'request_accepted', 'Estimate Accepted', ?,
                'estimate_requests', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Client ({$contactName}) accepted estimate {$row['request_code']}",
            $estimateId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, "Estimate {$row['request_code']} successfully accepted!", [
            'id'     => $estimateId,
            'status' => 'Converted'
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to accept estimate: ' . $e->getMessage(), [], 500);
    }
}

// =========================================================================
// ACTION: decline (Client Declines Estimate -> Approved to Rejected)
// =========================================================================
if ($action === 'decline') {
    $estimateId = (int)($_POST['id'] ?? 0);
    $reason     = trim((string)($_POST['reason'] ?? ''));

    if ($estimateId <= 0) {
        client_json_response(false, 'Valid estimate ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        $stmtSel = $pdo->prepare("
            SELECT id, request_code, subject, status 
            FROM estimate_requests 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
            FOR UPDATE
        ");
        $stmtSel->execute([$estimateId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Estimate not found or access denied.', [], 404);
        }

        if ($row['status'] !== 'Approved') {
            $pdo->rollBack();
            $msg = in_array($row['status'], ['Converted', 'Rejected'], true)
                ? "This estimate has already been finalized ({$row['status']}) and cannot be declined."
                : "This estimate cannot be declined in its current state ({$row['status']}).";
            client_json_response(false, $msg, [], 400);
        }

        // Update status to Rejected (DO NOT add new DB column for reason)
        $stmtUpd = $pdo->prepare("
            UPDATE estimate_requests 
            SET status = 'Rejected', updated_at = NOW() 
            WHERE id = ? AND organization_id = ? AND company_id = ?
        ");
        $stmtUpd->execute([$estimateId, $orgId, $companyId]);

        // Record decline reason in existing team_activities architecture
        $reasonText = $reason !== '' ? ": " . $reason : '';
        $stmtAct = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, user_id, activity_type, title, description,
                related_entity, related_entity_id, created_by, created_at
            ) VALUES (
                ?, ?, 'request_declined', 'Estimate Declined', ?,
                'estimate_requests', ?, ?, NOW()
            )
        ");
        $stmtAct->execute([
            $orgId,
            $contactId,
            "Client ({$contactName}) declined estimate {$row['request_code']}{$reasonText}",
            $estimateId,
            $contactId
        ]);

        $pdo->commit();

        client_json_response(true, "Estimate {$row['request_code']} declined.", [
            'id'     => $estimateId,
            'status' => 'Rejected'
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to decline estimate: ' . $e->getMessage(), [], 500);
    }
}

// Fallback: Unknown action
client_json_response(false, "Unknown action: {$action}", [], 400);