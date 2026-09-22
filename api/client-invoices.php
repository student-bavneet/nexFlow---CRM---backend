<?php
/**
 * NexFlow CRM — Client Portal Invoices API Endpoint
 * Handles secure listing, details retrieval, line items, payments, and 'mark_viewed'
 * lifecycle transition with strict multi-tenant isolation, CSRF verification,
 * and database transaction safety.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/client-auth.php';
require_once __DIR__ . '/../includes/client-helpers.php';
require_once __DIR__ . '/../includes/client-invoices-data.php';

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
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));

// 4. CSRF & Method Guard for Mutating Actions
$mutatingActions = ['mark_viewed'];
if (in_array($action, $mutatingActions, true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
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
    $data = client_get_invoices_data($pdo, $orgId, $companyId);
    client_json_response(true, 'Summary retrieved successfully.', [
        'kpis'           => $data['kpis'],
        'currency'       => $data['currency'],
        'currencySymbol' => $data['currencySymbol'],
    ]);
}

// =========================================================================
// ACTION: list (Filtered & Sorted Invoices)
// =========================================================================
if ($action === 'list') {
    $search         = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
    $statusFilter   = trim((string)($_GET['status'] ?? 'All'));
    $paymentFilter  = trim((string)($_GET['payment'] ?? 'All'));
    $sortBy         = trim((string)($_GET['sort'] ?? 'newest'));

    // Fetch full data set for authenticated company
    $data = client_get_invoices_data($pdo, $orgId, $companyId);
    $items = $data['invoices'];

    // Apply Client-Side/Search Filtering
    if ($search !== '' || $statusFilter !== 'All' || $paymentFilter !== 'All') {
        $q = mb_strtolower($search);
        $items = array_values(array_filter($items, function ($inv) use ($q, $statusFilter, $paymentFilter) {
            // Search match
            $matchSearch = true;
            if ($q !== '') {
                $matchSearch = (
                    mb_stripos((string)$inv['number'], $q) !== false ||
                    mb_stripos((string)$inv['title'], $q) !== false ||
                    mb_stripos((string)($inv['project'] ?? ''), $q) !== false ||
                    mb_stripos((string)($inv['deal'] ?? ''), $q) !== false ||
                    mb_stripos((string)($inv['contract'] ?? ''), $q) !== false
                );
            }

            // Status filter match
            $matchStatus = true;
            if ($statusFilter !== 'All') {
                $matchStatus = ($inv['status'] === $statusFilter || $inv['db_status'] === $statusFilter);
            }

            // Payment filter match
            $matchPayment = true;
            if ($paymentFilter !== 'All') {
                $matchPayment = ($inv['paymentStatus'] === $paymentFilter || $inv['paymentState'] === $paymentFilter);
            }

            return $matchSearch && $matchStatus && $matchPayment;
        }));
    }

    // Sorting
    if ($sortBy === 'oldest') {
        usort($items, function ($a, $b) {
            return strcmp((string)$a['issueDate'], (string)$b['issueDate']) ?: ($a['id'] <=> $b['id']);
        });
    } elseif ($sortBy === 'due') {
        usort($items, function ($a, $b) {
            return strcmp((string)$a['dueDate'], (string)$b['dueDate']);
        });
    } elseif ($sortBy === 'amount') {
        usort($items, function ($a, $b) {
            return ($b['total'] <=> $a['total']);
        });
    } elseif ($sortBy === 'number') {
        usort($items, function ($a, $b) {
            return strcmp((string)$a['number'], (string)$b['number']);
        });
    } elseif ($sortBy === 'status') {
        usort($items, function ($a, $b) {
            return strcmp((string)$a['status'], (string)$b['status']);
        });
    } else {
        // Default newest
        usort($items, function ($a, $b) {
            return strcmp((string)$b['issueDate'], (string)$a['issueDate']) ?: ($b['id'] <=> $a['id']);
        });
    }

    client_json_response(true, 'Invoices retrieved successfully.', [
        'items'          => $items,
        'invoices'       => $items,
        'count'          => count($items),
        'currency'       => $data['currency'],
        'currencySymbol' => $data['currencySymbol'],
    ]);
}

// =========================================================================
// ACTION: get (Single Invoice Details with Line Items & Payment History)
// =========================================================================
if ($action === 'get') {
    $invoiceId  = (int)($_GET['id'] ?? 0);
    $invoiceNum = trim((string)($_GET['number'] ?? $_GET['invoice_number'] ?? ''));

    if ($invoiceId <= 0 && $invoiceNum === '') {
        client_json_response(false, 'Valid invoice identifier is required.', [], 400);
    }

    // 1. Authorize Invoice strictly for authenticated org and company (Exclude Draft)
    $sql = "
        SELECT 
            i.id,
            i.organization_id,
            i.invoice_number,
            i.title,
            i.company_id,
            comp.name AS company_name,
            i.contact_id,
            CONCAT(COALESCE(cont.first_name, ''), ' ', COALESCE(cont.last_name, '')) AS contact_name,
            cont.email AS contact_email,
            cont.phone AS contact_phone,
            i.project_id,
            p.name AS project_name,
            i.deal_id,
            d.name AS deal_title,
            i.contract_reference,
            i.issue_date,
            i.due_date,
            i.payment_terms,
            i.currency,
            i.subtotal,
            i.discount,
            i.tax,
            i.total,
            i.amount_paid,
            i.balance_due,
            i.status,
            i.customer_notes,
            i.created_at,
            i.updated_at
        FROM invoices i
        LEFT JOIN companies comp ON comp.id = i.company_id AND comp.organization_id = ?
        LEFT JOIN contacts cont ON cont.id = i.contact_id AND cont.organization_id = ?
        LEFT JOIN projects p ON p.id = i.project_id AND p.organization_id = ?
        LEFT JOIN deals d ON d.id = i.deal_id AND d.organization_id = ?
        WHERE i.organization_id = ? 
          AND i.company_id = ?
          AND i.status != 'Draft'
    ";

    $params = [$orgId, $orgId, $orgId, $orgId, $orgId, $companyId];
    if ($invoiceId > 0) {
        $sql .= " AND i.id = ?";
        $params[] = $invoiceId;
    } else {
        $sql .= " AND i.invoice_number = ?";
        $params[] = $invoiceNum;
    }
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        client_json_response(false, 'Invoice not found or access denied.', [], 404);
    }

    $actualId = (int)$inv['id'];

    // 2. Fetch Itemized Deliverables
    $stmtItems = $pdo->prepare("
        SELECT id, item_title, item_description, quantity, unit_price, amount, sort_order
        FROM invoice_items
        WHERE organization_id = ? AND invoice_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtItems->execute([$orgId, $actualId]);
    $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rawItems as $it) {
        $items[] = [
            'id'     => (int)$it['id'],
            'title'  => $it['item_title'],
            'name'   => $it['item_title'],
            'desc'   => $it['item_description'] ?: '',
            'qty'    => (float)$it['quantity'],
            'price'  => (float)$it['unit_price'],
            'rate'   => (float)$it['unit_price'],
            'amount' => (float)$it['amount'],
            'total'  => (float)$it['amount'],
        ];
    }

    // 3. Fetch Client-Safe Payment Transactions (NO internal admin/staff fields)
    $stmtPay = $pdo->prepare("
        SELECT id, payment_number, amount, payment_date, payment_method, transaction_reference
        FROM invoice_payments
        WHERE organization_id = ? AND invoice_id = ?
        ORDER BY payment_date DESC, id DESC
    ");
    $stmtPay->execute([$orgId, $actualId]);
    $rawPays = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

    $payments = [];
    foreach ($rawPays as $py) {
        $transRef = ($py['transaction_reference'] !== null && trim((string)$py['transaction_reference']) !== '') ? trim((string)$py['transaction_reference']) : null;
        $payments[] = [
            'id'                    => (int)$py['id'],
            'number'                => $py['payment_number'],
            'payment_number'        => $py['payment_number'],
            'date'                  => $py['payment_date'],
            'amount'                => (float)$py['amount'],
            'method'                => $py['payment_method'],
            'payment_method'        => $py['payment_method'],
            'transaction_reference' => $transRef,
            'ref'                   => $transRef,
            'status'                => 'Paid',
        ];
    }

    // 4. Fetch Client-Safe Timeline Activities
    $stmtAct = $pdo->prepare("
        SELECT title, description, created_at
        FROM team_activities
        WHERE organization_id = ? AND related_entity = 'invoices' AND related_entity_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmtAct->execute([$orgId, $actualId]);
    $rawTimeline = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    $timeline = [];
    foreach ($rawTimeline as $act) {
        $timeline[] = [
            'date' => date('Y-m-d', strtotime($act['created_at'])),
            'text' => $act['title'] . ($act['description'] ? " — {$act['description']}" : '')
        ];
    }
    if (empty($timeline)) {
        $timeline[] = [
            'date' => date('Y-m-d', strtotime($inv['created_at'])),
            'text' => "Invoice issued"
        ];
    }

    // 5. Format Display Metrics
    $displayStatus = client_compute_invoice_display_status($inv);
    $paymentState  = client_compute_invoice_payment_state($inv);
    $curr          = (string)($inv['currency'] ?: 'USD ($)');
    $sym           = client_extract_invoice_currency_symbol($curr);

    $tot = (float)($inv['total'] ?? 0.0);
    $pd  = (float)($inv['amount_paid'] ?? 0.0);
    $bal = (float)($inv['balance_due'] ?? ($tot - $pd));

    $response = [
        'id'                => $actualId,
        'number'            => $inv['invoice_number'],
        'title'             => $inv['title'] ?: "Invoice {$inv['invoice_number']}",
        'customer'          => $inv['company_name'] ?: 'My Company',
        'billedTo'          => $inv['company_name'] ?: 'My Company',
        'company_id'        => (int)$inv['company_id'],
        'contact'           => trim((string)$inv['contact_name']) ?: null,
        'contactName'       => trim((string)$inv['contact_name']) ?: null,
        'contactEmail'      => $inv['contact_email'] ?: null,
        'contactPhone'      => $inv['contact_phone'] ?: null,
        'project'           => $inv['project_name'] ?: null,
        'deal'              => $inv['deal_title'] ?: null,
        'contract'          => $inv['contract_reference'] ?: null,
        'issueDate'         => $inv['issue_date'],
        'invoiceDate'       => $inv['issue_date'],
        'dueDate'           => $inv['due_date'],
        'paymentTerms'      => $inv['payment_terms'],
        'currency'          => $curr,
        'currencySymbol'    => $sym,
        'status'            => $displayStatus,
        'db_status'         => $inv['status'],
        'paymentStatus'     => $paymentState,
        'paymentState'      => $paymentState,
        'subtotal'          => (float)$inv['subtotal'],
        'discount'          => (float)$inv['discount'],
        'discountTotal'     => (float)$inv['discount'],
        'tax'               => (float)$inv['tax'],
        'taxTotal'          => (float)$inv['tax'],
        'total'             => $tot,
        'amount'            => $tot,
        'paid'              => $pd,
        'amountPaid'        => $pd,
        'balance'           => $bal,
        'balanceDue'        => $bal,
        'formattedTotal'    => $sym . number_format($tot, 2),
        'formattedPaid'     => $sym . number_format($pd, 2),
        'formattedBalance'  => $sym . number_format($bal, 2),
        'customer_notes'    => $inv['customer_notes'] ?: '',
        'documentName'      => "{$inv['invoice_number']}.pdf",
        'items'             => $items,
        'payments'          => $payments,
        'timeline'          => $timeline,
    ];

    client_json_response(true, 'Invoice details retrieved.', $response);
}

// =========================================================================
// ACTION: mark_viewed (Auto-transition Sent -> Viewed with transaction)
// =========================================================================
if ($action === 'mark_viewed') {
    $invoiceId = (int)($_POST['id'] ?? 0);
    if ($invoiceId <= 0) {
        client_json_response(false, 'Valid invoice ID is required.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        // Authorize invoice strictly for company
        $stmtSel = $pdo->prepare("
            SELECT id, invoice_number, status 
            FROM invoices 
            WHERE id = ? 
              AND organization_id = ? 
              AND company_id = ?
            FOR UPDATE
        ");
        $stmtSel->execute([$invoiceId, $orgId, $companyId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            client_json_response(false, 'Invoice not found or access denied.', [], 404);
        }

        // CORRECTION 1: Only transition when current status is 'Sent'
        // Never modify Paid, Partially Paid, Overdue, Viewed, or Cancelled statuses!
        if ($row['status'] === 'Sent') {
            $stmtUpd = $pdo->prepare("
                UPDATE invoices 
                SET status = 'Viewed', updated_at = NOW() 
                WHERE id = ? AND organization_id = ? AND company_id = ? AND status = 'Sent'
            ");
            $stmtUpd->execute([$invoiceId, $orgId, $companyId]);

            // CORRECTION 2: Log client-safe activity using safe try/catch
            try {
                $stmtAct = $pdo->prepare("
                    INSERT INTO team_activities (
                        organization_id, user_id, activity_type, title, description,
                        related_entity, related_entity_id, created_by, created_at
                    ) VALUES (
                        ?, ?, 'invoice_viewed', 'Invoice Viewed', ?,
                        'invoices', ?, ?, NOW()
                    )
                ");
                $stmtAct->execute([
                    $orgId,
                    $contactId,
                    "Invoice {$row['invoice_number']} viewed in Client Portal by {$contactName}",
                    $invoiceId,
                    $contactId
                ]);
            } catch (Throwable $actEx) {
                // Suppress non-critical activity log error
            }

            $pdo->commit();
            client_json_response(true, 'Invoice marked as viewed.', [
                'id'     => $invoiceId,
                'status' => 'Viewed'
            ]);
        }

        // If not 'Sent' (e.g. already 'Paid', 'Partially Paid', 'Viewed'), commit without changing status
        $pdo->commit();
        client_json_response(true, 'Invoice already processed.', [
            'id'     => $invoiceId,
            'status' => $row['status']
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        client_json_response(false, 'Failed to update invoice state: ' . $e->getMessage(), [], 500);
    }
}

client_json_response(false, "Unknown action: {$action}", [], 400);