<?php
/**
 * NexFlow CRM — Estimate Requests Management API Controller
 * Multi-tenant backend supporting CRUD, KPIs, status transitions,
 * deal conversions, notes, activities, attachments, and CSV export.
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// JSON Response Helper
function est_json(bool $success, string $message = '', array $data = [], int $httpCode = 200): void {
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
    est_json(false, 'Unauthorized access. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId  = (int)$currentUser['id'];

if ($organizationId <= 0) {
    est_json(false, 'Invalid organization context.', [], 400);
}

// 2. Permission Guard
function require_estimate_perm(string $action): void {
    global $currentUser;

    // Super Admin Bypass
    if (is_super_admin($currentUser['id'])) {
        return;
    }

    if (function_exists('hasPermission')) {
        if (!hasPermission('estimates', $action) && !hasPermission('estimate-requests', $action)) {
            est_json(false, "Forbidden: Missing 'estimates.{$action}' permission.", [], 403);
        }
    }
}

$pdo = nexflow_db();

// Helper to safely generate next request_code inside a transaction
function generate_next_request_code(PDO $pdo, int $organizationId): string {
    $year = date('Y');
    $prefix = "REQ-{$year}-";

    // Lock rows for this org & year to prevent race conditions
    $stmt = $pdo->prepare("SELECT request_code FROM estimate_requests 
                           WHERE organization_id = ? AND request_code LIKE ? 
                           ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$organizationId, $prefix . '%']);
    $lastCode = $stmt->fetchColumn();

    $nextNum = 1;
    if ($lastCode) {
        $parts = explode('-', $lastCode);
        $lastNum = (int)end($parts);
        if ($lastNum > 0) {
            $nextNum = $lastNum + 1;
        }
    }

    $candidate = sprintf('REQ-%s-%03d', $year, $nextNum);

    // Ensure candidate is truly unique
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM estimate_requests WHERE organization_id = ? AND request_code = ?");
    while (true) {
        $checkStmt->execute([$organizationId, $candidate]);
        if ($checkStmt->fetchColumn() == 0) {
            break;
        }
        $nextNum++;
        $candidate = sprintf('REQ-%s-%03d', $year, $nextNum);
    }

    return $candidate;
}

// Log activity into polymorphic team_activities

// Currency Helpers
function get_organization_currency(PDO $pdo, int $orgId): string {
    static $cache = [];
    if (isset($cache[$orgId])) return $cache[$orgId];
    $stmt = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $curr = (string)($stmt->fetchColumn() ?: 'USD ($)');
    $cache[$orgId] = $curr;
    return $curr;
}

function get_organization_currency_symbol(PDO $pdo, int $orgId): string {
    $curr = get_organization_currency($pdo, $orgId);
    if (preg_match('/\(([^)]+)\)/', $curr, $m)) {
        return $m[1];
    }
    if (strpos($curr, 'INR') !== false || strpos($curr, '₹') !== false) {
        return '₹';
    }
    if (strpos($curr, 'EUR') !== false || strpos($curr, '€') !== false) {
        return '€';
    }
    if (strpos($curr, 'GBP') !== false || strpos($curr, '£') !== false) {
        return '£';
    }
    return '$';
}

// Budget Validation & Formatting
function validate_estimate_budget(?string $budgetInput, ?string &$cleanBudget, string &$error): bool {
    $raw = trim((string)$budgetInput);
    if ($raw === '' || $raw === '$0' || $raw === '0') {
        $cleanBudget = '';
        return true;
    }

    // Check for negative signs
    if (preg_match('/(?:^|\s+)-\s*\$?\s*\d+/', $raw) && !preg_match('/\d+\s*-\s*\$?\s*\d+/', $raw)) {
        $error = 'Budget cannot be negative.';
        return false;
    }

    // Check if range (e.g. "$20,000 - $30,000" or "20000 to 30000")
    if (preg_match('/^([^\-]+?)(?:\s+-\s+|\s+to\s+)(.+)$/i', $raw, $matches)) {
        $minRaw = trim($matches[1]);
        $maxRaw = trim($matches[2]);

        if (preg_match('/^-\s*\d+/', $minRaw) || preg_match('/^-\s*\d+/', $maxRaw)) {
            $error = 'Budget cannot be negative.';
            return false;
        }

        $minClean = preg_replace('/[^\d.]/', '', $minRaw);
        $maxClean = preg_replace('/[^\d.]/', '', $maxRaw);

        $minAlpha = preg_replace('/[\d.,\s\$\€\£\₹USDINREURGBP]/i', '', $minRaw);
        $maxAlpha = preg_replace('/[\d.,\s\$\€\£\₹USDINREURGBP]/i', '', $maxRaw);

        if ($minAlpha !== '' || $maxAlpha !== '' || !is_numeric($minClean) || !is_numeric($maxClean)) {
            $error = 'Invalid budget range. Both minimum and maximum must be numeric amounts.';
            return false;
        }

        $minVal = (float)$minClean;
        $maxVal = (float)$maxClean;

        if ($minVal < 0 || $maxVal < 0) {
            $error = 'Budget cannot be negative.';
            return false;
        }

        if ($minVal > $maxVal) {
            $error = 'Minimum budget cannot be greater than maximum budget.';
            return false;
        }

        $cleanBudget = $raw;
        return true;
    }

    // Single value
    $clean = preg_replace('/[^\d.]/', '', $raw);
    $alpha = preg_replace('/[\d.,\s\$\€\£\₹USDINREURGBP]/i', '', $raw);

    if ($alpha !== '' || !is_numeric($clean)) {
        $error = 'Invalid budget value. Budget must be numeric or a valid budget range (e.g. 25,000 or 20,000 - 30,000).';
        return false;
    }

    $val = (float)$clean;
    if ($val < 0) {
        $error = 'Budget cannot be negative.';
        return false;
    }

    $cleanBudget = $raw;
    return true;
}

function format_budget_with_currency(string $raw, string $currencySymbol = '$'): string {
    $raw = trim($raw);
    if ($raw === '') return 'Not specified';

    if (preg_match('/^([^\-]+?)(?:\s+-\s+|\s+to\s+)(.+)$/i', $raw, $matches)) {
        $minRaw = trim($matches[1]);
        $maxRaw = trim($matches[2]);
        $minClean = preg_replace('/[^\d.]/', '', $minRaw);
        $maxClean = preg_replace('/[^\d.]/', '', $maxRaw);
        if (is_numeric($minClean) && is_numeric($maxClean)) {
            $minFormatted = number_format((float)$minClean, ((float)$minClean == (int)$minClean ? 0 : 2));
            $maxFormatted = number_format((float)$maxClean, ((float)$maxClean == (int)$maxClean ? 0 : 2));
            return "{$currencySymbol}{$minFormatted} - {$currencySymbol}{$maxFormatted}";
        }
    }

    $clean = preg_replace('/[^\d.]/', '', $raw);
    if (is_numeric($clean)) {
        $formatted = number_format((float)$clean, ((float)$clean == (int)$clean ? 0 : 2));
        return "{$currencySymbol}{$formatted}";
    }

    return $raw;
}

function format_estimate_budget_display(?string $rawBudget, string $currencySymbol = '$'): array {
    $raw = trim((string)$rawBudget);
    if ($raw === '' || $raw === '$0' || $raw === '0') {
        return [
            'display'   => 'Not specified',
            'raw'       => $rawBudget ?? '',
            'is_valid'  => true,
        ];
    }

    $clean = '';
    $err = '';
    if (validate_estimate_budget($raw, $clean, $err)) {
        return [
            'display'   => format_budget_with_currency($raw, $currencySymbol),
            'raw'       => $rawBudget ?? '',
            'is_valid'  => true,
        ];
    }

    // Invalid stored budget (e.g. 'fbbfxbfd')
    return [
        'display'   => 'Needs review',
        'raw'       => $rawBudget ?? '',
        'is_valid'  => false,
    ];
}

function parse_estimate_timeline($rawTimeline): string {
    if (empty($rawTimeline)) {
        return 'Not specified';
    }

    if (is_array($rawTimeline)) {
        $parts = [];
        foreach ($rawTimeline as $k => $item) {
            if (is_array($item)) {
                $label = $item['label'] ?? $item['phase'] ?? $item['name'] ?? $item['title'] ?? (is_string($k) ? $k : '');
                $duration = $item['duration'] ?? $item['days'] ?? $item['value'] ?? $item['time'] ?? '';
                if ($label !== '' && $duration !== '') {
                    $parts[] = "{$label}: {$duration}";
                } elseif ($duration !== '') {
                    $parts[] = (string)$duration;
                } elseif ($label !== '') {
                    $parts[] = (string)$label;
                }
            } elseif (is_scalar($item)) {
                $s = trim((string)$item);
                if ($s !== '' && $s !== '[object Object]' && $s !== 'undefined' && $s !== 'null') {
                    $parts[] = is_string($k) ? "{$k}: {$s}" : $s;
                }
            }
        }
        return !empty($parts) ? implode(' • ', $parts) : 'Not specified';
    }

    $trimmed = trim((string)$rawTimeline);
    if ($trimmed === '' || $trimmed === '[object Object]' || $trimmed === 'undefined' || $trimmed === 'null') {
        return 'Not specified';
    }

    if ($trimmed[0] === '[' || $trimmed[0] === '{') {
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            return parse_estimate_timeline($decoded);
        }
    }

    return $trimmed;
}

function validate_estimate_date(?string $dateStr, string &$error): bool {
    $str = trim((string)$dateStr);
    if ($str === '') return true;

    $d = DateTime::createFromFormat('Y-m-d', $str);
    if ($d && $d->format('Y-m-d') === $str) {
        return true;
    }

    $ts = strtotime($str);
    if ($ts !== false && $ts > 0) {
        return true;
    }

    $error = 'Preferred start date must be a valid date format (e.g. YYYY-MM-DD).';
    return false;
}
function log_estimate_activity(PDO $pdo, int $organizationId, int $userId, int $requestId, string $type, string $title, string $desc): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO team_activities 
            (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'estimate_requests', ?, ?, NOW())");
        $stmt->execute([$organizationId, $userId, $type, $title, $desc, $requestId, $userId]);
    } catch (Throwable $e) {
        // Log failure gracefully without aborting main transaction
    }
}

// 3. Action Dispatcher
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
if (empty($action)) {
    est_json(false, 'No action specified.', [], 400);
}

// --------------------------------------------------------------------------
// ACTION: reference_options
// --------------------------------------------------------------------------
if ($action === 'reference_options') {
    require_estimate_perm('view');

    try {
        // Companies
        $compStmt = $pdo->prepare("SELECT id, name, company_code FROM companies WHERE organization_id = ? AND is_active = 1 ORDER BY name ASC");
        $compStmt->execute([$organizationId]);
        $companies = $compStmt->fetchAll(PDO::FETCH_ASSOC);

        // Contacts
        $contStmt = $pdo->prepare("SELECT id, name, email, phone, company_name FROM contacts WHERE organization_id = ? AND is_active = 1 ORDER BY name ASC");
        $contStmt->execute([$organizationId]);
        $contacts = $contStmt->fetchAll(PDO::FETCH_ASSOC);

        // Users / Assignable Owners
        $userStmt = $pdo->prepare("SELECT id, name, display_name, email, role FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
        $userStmt->execute([$organizationId]);
        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

        // Available Deals
        $dealStmt = $pdo->prepare("SELECT id, name, deal_code FROM deals WHERE organization_id = ? ORDER BY name ASC");
        $dealStmt->execute([$organizationId]);
        $deals = $dealStmt->fetchAll(PDO::FETCH_ASSOC);

        est_json(true, 'Reference options retrieved.', [
            'companies' => $companies,
            'contacts'  => $contacts,
            'users'     => $users,
            'deals'     => $deals,
            'statuses'  => ['New', 'In Review', 'More Info Needed', 'Approved', 'Rejected', 'Converted'],
            'services'  => [
                'CRM Migration & Custom Setup',
                'Enterprise API & Data Pipelines',
                'Predictive AI Lead Scoring',
                'Custom Reporting & Dashboard',
                'Multi-Currency Billing Portal',
                'Custom SLA Support Tier',
                'Enterprise Sales Automation & SSO Add-on',
                'Cloud Migration & Webhooks'
            ],
            'current_user' => [
                'id' => $currentUserId,
                'name' => $currentUser['name'] ?? 'Admin',
                'organization_id' => $organizationId
            ]
        ]);
    } catch (Throwable $e) {
        est_json(false, 'Failed to fetch reference options: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: summary (KPIs)
// --------------------------------------------------------------------------
if ($action === 'summary') {
    require_estimate_perm('view');

    try {
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'New' THEN 1 ELSE 0 END) AS count_new,
            SUM(CASE WHEN status = 'In Review' THEN 1 ELSE 0 END) AS count_in_review,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS count_approved,
            SUM(CASE WHEN status = 'Converted' THEN 1 ELSE 0 END) AS count_converted,
            SUM(CASE WHEN status = 'More Info Needed' THEN 1 ELSE 0 END) AS count_more_info_needed,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS count_rejected
            FROM estimate_requests
            WHERE organization_id = ?");
        $stmt->execute([$organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $summary = [
            'total'             => (int)($row['total'] ?? 0),
            'new'               => (int)($row['count_new'] ?? 0),
            'in_review'         => (int)($row['count_in_review'] ?? 0),
            'approved'          => (int)($row['count_approved'] ?? 0),
            'converted'         => (int)($row['count_converted'] ?? 0),
            'more_info_needed'  => (int)($row['count_more_info_needed'] ?? 0),
            'rejected'          => (int)($row['count_rejected'] ?? 0),
        ];

        $currSymbol = get_organization_currency_symbol($pdo, $organizationId);
        $summary['currency_symbol'] = $currSymbol;
        $summary['currency']        = get_organization_currency($pdo, $organizationId);

        est_json(true, 'Summary metrics retrieved.', $summary);
    } catch (Throwable $e) {
        est_json(false, 'Failed to fetch summary: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: list
// --------------------------------------------------------------------------
if ($action === 'list') {
    require_estimate_perm('view');

    try {
        $search   = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
        $status   = trim($_GET['status'] ?? 'All');
        $service  = trim($_GET['service'] ?? 'All');
        $sort     = trim($_GET['sort'] ?? 'newest');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $perPage  = max(1, min(100, (int)($_GET['per_page'] ?? 10)));
        $offset   = ($page - 1) * $perPage;

        $where = ["er.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($search !== '') {
            $sClause = [
                "er.request_code LIKE :s_code",
                "er.subject LIKE :s_subj",
                "er.company_name LIKE :s_comp",
                "er.contact_name LIKE :s_cont",
                "er.email LIKE :s_email",
                "er.services LIKE :s_serv"
            ];
            $sTerm = "%{$search}%";
            $params[':s_code']  = $sTerm;
            $params[':s_subj']  = $sTerm;
            $params[':s_comp']  = $sTerm;
            $params[':s_cont']  = $sTerm;
            $params[':s_email'] = $sTerm;
            $params[':s_serv']  = $sTerm;

            if (is_numeric($search)) {
                $sClause[] = "er.id = :s_id";
                $params[':s_id'] = (int)$search;
            }

            $where[] = "(" . implode(' OR ', $sClause) . ")";
        }

        if ($status !== '' && $status !== 'All') {
            $where[] = "er.status = :status";
            $params[':status'] = $status;
        }

        if ($service !== '' && $service !== 'All') {
            $where[] = "er.services LIKE :service";
            $params[':service'] = "%{$service}%";
        }

        $whereSql = implode(' AND ', $where);

        // Count Total Records Matching
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM estimate_requests er WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        // Sort Order
        $orderBy = "er.created_at DESC, er.id DESC";
        if ($sort === 'oldest') {
            $orderBy = "er.created_at ASC, er.id ASC";
        } elseif ($sort === 'customer-asc') {
            $orderBy = "er.company_name ASC, er.contact_name ASC";
        } elseif ($sort === 'budget-desc') {
            $orderBy = "er.budget DESC, er.id DESC";
        }

        $query = "SELECT er.*, 
                         u.name AS owner_name, 
                         u.display_name AS owner_display_name,
                         c.company_code,
                         ct.contact_code,
                         d.deal_code, d.name AS deal_name,
                         (SELECT COUNT(*) FROM team_notes tn WHERE tn.related_type = 'estimate_requests' AND tn.related_id = er.id) AS notes_count,
                         (SELECT MAX(created_at) FROM team_activities ta WHERE ta.related_entity = 'estimate_requests' AND ta.related_entity_id = er.id) AS last_activity_at
                  FROM estimate_requests er
                  LEFT JOIN users u ON u.id = er.owner_id
                  LEFT JOIN companies c ON c.id = er.company_id
                  LEFT JOIN contacts ct ON ct.id = er.contact_id
                  LEFT JOIN deals d ON d.id = er.deal_id
                  WHERE {$whereSql}
                  ORDER BY {$orderBy}
                  LIMIT :offset, :per_page";

        $stmt = $pdo->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $currencySymbol = get_organization_currency_symbol($pdo, $organizationId);

        // Format for frontend
        $formatted = [];
        foreach ($records as $r) {
            $attachments = [];
            if (!empty($r['attachments'])) {
                $dec = json_decode($r['attachments'], true);
                if (is_array($dec)) $attachments = $dec;
            }

            $lastAct = $r['last_activity_at'] ? date('Y-m-d', strtotime($r['last_activity_at'])) : date('Y-m-d', strtotime($r['created_at']));

            $formatted[] = [
                'id'                 => $r['request_code'],
                'raw_id'             => (int)$r['id'],
                'subject'            => $r['subject'],
                'company'            => $r['company_name'] ?: 'No Company',
                'company_id'         => $r['company_id'] ? (int)$r['company_id'] : null,
                'contact'            => $r['contact_name'] ?: 'No Contact',
                'contact_id'         => $r['contact_id'] ? (int)$r['contact_id'] : null,
                'email'              => $r['email'] ?: '',
                'phone'              => $r['phone'] ?: '',
                'services'           => $r['services'] ?: '',
                'budget'             => ($bMeta = format_estimate_budget_display($r['budget'], $currencySymbol))['display'],
                'raw_budget'         => $bMeta['raw'],
                'is_budget_valid'    => $bMeta['is_valid'],
                'timeline'           => parse_estimate_timeline($r['timeline']),
                'raw_timeline'       => $r['timeline'] ?: '',
                'source'             => $r['source'] ?: 'Direct Admin Input',
                'owner'              => $r['owner_display_name'] ?: ($r['owner_name'] ?: 'Unassigned'),
                'owner_id'           => $r['owner_id'] ? (int)$r['owner_id'] : null,
                'status'             => $r['status'],
                'createdDate'        => date('Y-m-d', strtotime($r['created_at'])),
                'lastActivity'       => $lastAct,
                'description'        => $r['description'] ?: '',
                'requirements'       => $r['requirements'] ?: '',
                'preferredStartDate' => $r['preferred_start_date'] ?: '',
                'attachments'        => $attachments,
                'deal_id'            => $r['deal_id'] ? (int)$r['deal_id'] : null,
                'deal_code'          => $r['deal_code'] ?: '',
                'deal_name'          => $r['deal_name'] ?: '',
                'notes_count'        => (int)$r['notes_count']
            ];
        }

        est_json(true, 'Estimate requests fetched.', [
            'total'       => $totalRecords,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages'      => ceil($totalRecords / $perPage),
            'currency_symbol'  => $currencySymbol,
            'requests'         => $formatted
        ]);
    } catch (Throwable $e) {
        est_json(false, 'Failed to fetch list: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: get (Single Request Details + Notes + Activities)
// --------------------------------------------------------------------------
if ($action === 'get') {
    require_estimate_perm('view');

    $idParam = trim($_GET['id'] ?? $_POST['id'] ?? '');
    if (empty($idParam)) {
        est_json(false, 'Request ID required.', [], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT er.*, 
                                     u.name AS owner_name, 
                                     u.display_name AS owner_display_name,
                                     c.company_code,
                                     ct.contact_code,
                                     d.deal_code, d.name AS deal_name
                              FROM estimate_requests er
                              LEFT JOIN users u ON u.id = er.owner_id
                              LEFT JOIN companies c ON c.id = er.company_id
                              LEFT JOIN contacts ct ON ct.id = er.contact_id
                              LEFT JOIN deals d ON d.id = er.deal_id
                              WHERE (er.id = :num_id OR er.request_code = :code) 
                                AND er.organization_id = :org_id
                              LIMIT 1");
        $stmt->execute([
            ':num_id'  => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'    => $idParam,
            ':org_id'  => $organizationId
        ]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$r) {
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$r['id'];

        // Fetch polymorphic team_activities
        $actStmt = $pdo->prepare("SELECT ta.*, u.name AS user_name, u.display_name AS user_display_name 
                                  FROM team_activities ta
                                  LEFT JOIN users u ON u.id = ta.user_id
                                  WHERE ta.organization_id = ? 
                                    AND ta.related_entity = 'estimate_requests' 
                                    AND ta.related_entity_id = ?
                                  ORDER BY ta.created_at DESC");
        $actStmt->execute([$organizationId, $rawId]);
        $actRows = $actStmt->fetchAll(PDO::FETCH_ASSOC);

        $timelineLog = [];
        foreach ($actRows as $act) {
            $userLabel = $act['user_display_name'] ?: ($act['user_name'] ?: 'System');
            $timelineLog[] = [
                'id'    => (int)$act['id'],
                'date'  => date('Y-m-d h:i A', strtotime($act['created_at'])),
                'user'  => $userLabel,
                'title' => $act['title'],
                'text'  => $act['description']
            ];
        }

        // Fetch polymorphic team_notes
        $noteStmt = $pdo->prepare("SELECT tn.*, u.name AS author_name, u.display_name AS author_display_name
                                   FROM team_notes tn
                                   LEFT JOIN users u ON u.id = tn.created_by
                                   WHERE tn.organization_id = ? 
                                     AND tn.related_type = 'estimate_requests' 
                                     AND tn.related_id = ?
                                   ORDER BY tn.is_pinned DESC, tn.created_at DESC");
        $noteStmt->execute([$organizationId, $rawId]);
        $noteRows = $noteStmt->fetchAll(PDO::FETCH_ASSOC);

        $notes = [];
        foreach ($noteRows as $n) {
            $notes[] = [
                'id'      => (int)$n['id'],
                'date'    => date('M d, Y', strtotime($n['created_at'])),
                'author'  => $n['author_display_name'] ?: ($n['author_name'] ?: 'Admin'),
                'title'   => $n['title'] ?: '',
                'text'    => $n['content'],
                'content' => $n['content'],
                'pinned'  => (bool)$n['is_pinned']
            ];
        }

        $attachments = [];
        if (!empty($r['attachments'])) {
            $dec = json_decode($r['attachments'], true);
            if (is_array($dec)) $attachments = $dec;
        }

        $lastAct = !empty($timelineLog) ? $timelineLog[0]['date'] : date('Y-m-d', strtotime($r['created_at']));

        $currencySymbol = get_organization_currency_symbol($pdo, $organizationId);
        $bMeta = format_estimate_budget_display($r['budget'], $currencySymbol);
        $parsedTimeline = parse_estimate_timeline($r['timeline']);

        $result = [
            'id'                 => $r['request_code'],
            'raw_id'             => $rawId,
            'subject'            => $r['subject'],
            'company'            => $r['company_name'] ?: '',
            'company_id'         => $r['company_id'] ? (int)$r['company_id'] : null,
            'contact'            => $r['contact_name'] ?: '',
            'contact_id'         => $r['contact_id'] ? (int)$r['contact_id'] : null,
            'email'              => $r['email'] ?: '',
            'phone'              => $r['phone'] ?: '',
            'services'           => $r['services'] ?: '',
            'budget'             => $bMeta['display'],
            'raw_budget'         => $bMeta['raw'],
            'is_budget_valid'    => $bMeta['is_valid'],
            'timeline'           => $parsedTimeline,
            'delivery_timeline'  => $parsedTimeline,
            'raw_timeline'       => $r['timeline'] ?: '',
            'currency_symbol'    => $currencySymbol,
            'source'             => $r['source'] ?: 'Direct Admin Input',
            'owner'              => $r['owner_display_name'] ?: ($r['owner_name'] ?: 'Unassigned'),
            'owner_id'           => $r['owner_id'] ? (int)$r['owner_id'] : null,
            'status'             => $r['status'],
            'createdDate'        => date('Y-m-d', strtotime($r['created_at'])),
            'lastActivity'       => $lastAct,
            'description'        => $r['description'] ?: '',
            'requirements'       => $r['requirements'] ?: '',
            'preferredStartDate' => $r['preferred_start_date'] ?: '',
            'attachments'        => $attachments,
            'deal_id'            => $r['deal_id'] ? (int)$r['deal_id'] : null,
            'deal_code'          => $r['deal_code'] ?: '',
            'deal_name'          => $r['deal_name'] ?: '',
            'timelineLog'        => $timelineLog,
            'notes'              => $notes
        ];

        est_json(true, 'Estimate request details retrieved.', $result);
    } catch (Throwable $e) {
        est_json(false, 'Failed to fetch details: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: create
// --------------------------------------------------------------------------
if ($action === 'create') {
    require_estimate_perm('create');

    $subject    = trim($_POST['subject'] ?? '');
    $services   = trim($_POST['services'] ?? '');
    $company    = trim($_POST['company'] ?? '');
    $companyId  = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;
    $contact    = trim($_POST['contact'] ?? '');
    $contactId  = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $budget     = trim($_POST['budget'] ?? '');
    $timeline   = trim($_POST['timeline'] ?? 'Flexible Exploration');
    $ownerId    = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $ownerName  = trim($_POST['owner'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $reqs       = trim($_POST['requirements'] ?? '');
    $startDate  = trim($_POST['preferred_start_date'] ?? '');
    $source     = trim($_POST['source'] ?? 'Direct Admin Input');

    if (empty($subject)) {
        est_json(false, 'Request subject is required.', [], 422);
    }
    if (empty($services)) {
        est_json(false, 'Requested services field is required.', [], 422);
    }
    if (empty($company)) {
        est_json(false, 'Customer Company is required.', [], 422);
    }
    if (empty($contact)) {
        est_json(false, 'Contact Person is required.', [], 422);
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        est_json(false, 'Please provide a valid email address.', [], 422);
    }

    if (!empty($budget)) {
        $cleanBudget = '';
        $budgetErr = '';
        if (!validate_estimate_budget($budget, $cleanBudget, $budgetErr)) {
            est_json(false, $budgetErr, [], 422);
        }
    }

    if (!empty($startDate)) {
        $dateErr = '';
        if (!validate_estimate_date($startDate, $dateErr)) {
            est_json(false, $dateErr, [], 422);
        }
    }

    try {
        $pdo->beginTransaction();

        // Validate or resolve company
        if ($companyId) {
            $cChk = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND organization_id = ?");
            $cChk->execute([$companyId, $organizationId]);
            $cRow = $cChk->fetch(PDO::FETCH_ASSOC);
            if (!$cRow) {
                $pdo->rollBack();
                est_json(false, 'Selected company does not belong to your organization.', [], 403);
            }
            $company = $cRow['name'];
        } else {
            // Try match existing company by name
            $cChk = $pdo->prepare("SELECT id FROM companies WHERE LOWER(name) = LOWER(?) AND organization_id = ? LIMIT 1");
            $cChk->execute([$company, $organizationId]);
            $cId = $cChk->fetchColumn();
            if ($cId) $companyId = (int)$cId;
        }

        // Validate or resolve contact
        if ($contactId) {
            $ctChk = $pdo->prepare("SELECT id, name, email, phone FROM contacts WHERE id = ? AND organization_id = ?");
            $ctChk->execute([$contactId, $organizationId]);
            $ctRow = $ctChk->fetch(PDO::FETCH_ASSOC);
            if (!$ctRow) {
                $pdo->rollBack();
                est_json(false, 'Selected contact does not belong to your organization.', [], 403);
            }
            $contact = $ctRow['name'];
            if (empty($email) && !empty($ctRow['email'])) $email = $ctRow['email'];
            if (empty($phone) && !empty($ctRow['phone'])) $phone = $ctRow['phone'];
        }

        // Validate or resolve owner
        if ($ownerId) {
            $uChk = $pdo->prepare("SELECT id, name, display_name FROM users WHERE id = ? AND organization_id = ?");
            $uChk->execute([$ownerId, $organizationId]);
            $uRow = $uChk->fetch(PDO::FETCH_ASSOC);
            if (!$uRow) {
                $pdo->rollBack();
                est_json(false, 'Selected owner does not belong to your organization.', [], 403);
            }
        } elseif (!empty($ownerName)) {
            $uChk = $pdo->prepare("SELECT id FROM users WHERE (LOWER(name) = LOWER(?) OR LOWER(display_name) = LOWER(?)) AND organization_id = ? LIMIT 1");
            $uChk->execute([$ownerName, $ownerName, $organizationId]);
            $uId = $uChk->fetchColumn();
            if ($uId) $ownerId = (int)$uId;
        }

        // If still no owner, assign authenticated user
        if (!$ownerId) {
            $ownerId = $currentUserId;
        }

        $requestCode = generate_next_request_code($pdo, $organizationId);

        $insStmt = $pdo->prepare("INSERT INTO estimate_requests 
            (organization_id, request_code, subject, company_id, company_name, contact_id, contact_name, email, phone, services, budget, timeline, source, owner_id, status, description, requirements, preferred_start_date, attachments, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?, ?, ?, '[]', ?, NOW())");

        $insStmt->execute([
            $organizationId,
            $requestCode,
            $subject,
            $companyId,
            $company,
            $contactId,
            $contact,
            $email,
            $phone,
            $services,
            $budget ?: '$0',
            $timeline,
            $source,
            $ownerId,
            $desc,
            $reqs,
            !empty($startDate) ? $startDate : null,
            $currentUserId
        ]);

        $newId = (int)$pdo->lastInsertId();

        // Log creation activity
        log_estimate_activity($pdo, $organizationId, $currentUserId, $newId, 'request_created', 'Estimate Request Created', "Estimate request {$requestCode} created for {$company}.");

        $pdo->commit();

        est_json(true, "Estimate request {$requestCode} created successfully.", [
            'id'           => $requestCode,
            'raw_id'       => $newId,
            'request_code' => $requestCode,
            'subject'      => $subject,
            'company'      => $company,
            'status'       => 'New'
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to create request: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: update
// --------------------------------------------------------------------------
if ($action === 'update') {
    require_estimate_perm('edit');

    $idParam   = trim($_POST['id'] ?? '');
    $company   = trim($_POST['company'] ?? '');
    $companyId = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;
    $contact   = trim($_POST['contact'] ?? '');
    $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $services  = trim($_POST['services'] ?? '');
    $budget    = trim($_POST['budget'] ?? '');
    $timeline  = trim($_POST['timeline'] ?? '');
    $status    = trim($_POST['status'] ?? '');
    $ownerId   = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $ownerName = trim($_POST['owner'] ?? '');
    $desc      = trim($_POST['description'] ?? '');

    if (empty($idParam)) {
        est_json(false, 'Request ID required for update.', [], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM estimate_requests WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id LIMIT 1 FOR UPDATE");
        $stmt->execute([
            ':num_id' => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'   => $idParam,
            ':org_id' => $organizationId
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$existing['id'];

        // Validate required fields if submitted in POST
        if (isset($_POST['subject']) && trim($_POST['subject']) === '') {
            $pdo->rollBack();
            est_json(false, 'Request subject is required.', [], 422);
        }
        if (isset($_POST['services']) && trim($_POST['services']) === '') {
            $pdo->rollBack();
            est_json(false, 'Requested services field is required.', [], 422);
        }
        if (isset($_POST['company']) && trim($_POST['company']) === '') {
            $pdo->rollBack();
            est_json(false, 'Customer Company is required.', [], 422);
        }
        if (isset($_POST['contact']) && trim($_POST['contact']) === '') {
            $pdo->rollBack();
            est_json(false, 'Contact Person is required.', [], 422);
        }

        // Determine fields: use submitted values or retain existing
        $newCompany  = ($company !== '') ? $company : $existing['company_name'];
        $newContact  = ($contact !== '') ? $contact : $existing['contact_name'];
        $newEmail    = isset($_POST['email']) ? $email : $existing['email'];
        $newPhone    = isset($_POST['phone']) ? $phone : $existing['phone'];
        $newSubject  = ($subject !== '') ? $subject : $existing['subject'];
        $newServices = ($services !== '') ? $services : $existing['services'];
        $newBudget   = isset($_POST['budget']) ? ($budget !== '' ? $budget : '$0') : $existing['budget'];
        $newTimeline = ($timeline !== '') ? $timeline : $existing['timeline'];
        $newDesc     = isset($_POST['description']) ? $desc : $existing['description'];

        if (empty($newSubject)) {
            $pdo->rollBack();
            est_json(false, 'Request subject is required.', [], 422);
        }
        if (empty($newServices)) {
            $pdo->rollBack();
            est_json(false, 'Requested services field is required.', [], 422);
        }
        if (empty($newCompany)) {
            $pdo->rollBack();
            est_json(false, 'Customer Company is required.', [], 422);
        }
        if (empty($newContact)) {
            $pdo->rollBack();
            est_json(false, 'Contact Person is required.', [], 422);
        }
        if (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $pdo->rollBack();
            est_json(false, 'Please provide a valid email address.', [], 422);
        }

        if (isset($_POST['budget']) && trim($_POST['budget']) !== '') {
            $cleanBudget = '';
            $budgetErr = '';
            if (!validate_estimate_budget($_POST['budget'], $cleanBudget, $budgetErr)) {
                $pdo->rollBack();
                est_json(false, $budgetErr, [], 422);
            }
        }

        if (!empty($_POST['preferred_start_date'])) {
            $dateErr = '';
            if (!validate_estimate_date($_POST['preferred_start_date'], $dateErr)) {
                $pdo->rollBack();
                est_json(false, $dateErr, [], 422);
            }
        }

        // Validate or resolve company
        $newCompanyId = $companyId ?: $existing['company_id'];
        if ($companyId) {
            $cChk = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND organization_id = ?");
            $cChk->execute([$companyId, $organizationId]);
            $cRow = $cChk->fetch(PDO::FETCH_ASSOC);
            if ($cRow) {
                $newCompany = $cRow['name'];
            } else {
                $newCompanyId = null;
            }
        }
        if (!$newCompanyId && !empty($newCompany)) {
            $cChk = $pdo->prepare("SELECT id FROM companies WHERE LOWER(name) = LOWER(?) AND organization_id = ? LIMIT 1");
            $cChk->execute([$newCompany, $organizationId]);
            $cId = $cChk->fetchColumn();
            if ($cId) $newCompanyId = (int)$cId;
        }

        // Validate or resolve contact
        $newContactId = $contactId ?: $existing['contact_id'];
        if ($contactId) {
            $ctChk = $pdo->prepare("SELECT id, name, email, phone FROM contacts WHERE id = ? AND organization_id = ?");
            $ctChk->execute([$contactId, $organizationId]);
            $ctRow = $ctChk->fetch(PDO::FETCH_ASSOC);
            if ($ctRow) {
                $newContact = $ctRow['name'];
                if (empty($newEmail) && !empty($ctRow['email'])) $newEmail = $ctRow['email'];
                if (empty($newPhone) && !empty($ctRow['phone'])) $newPhone = $ctRow['phone'];
            } else {
                $newContactId = null;
            }
        }
        if (!$newContactId && !empty($newContact)) {
            $ctChk = $pdo->prepare("SELECT id FROM contacts WHERE LOWER(name) = LOWER(?) AND organization_id = ? LIMIT 1");
            $ctChk->execute([$newContact, $organizationId]);
            $ctId = $ctChk->fetchColumn();
            if ($ctId) $newContactId = (int)$ctId;
        }

        // Validate owner if provided
        if ($ownerId) {
            $uChk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ?");
            $uChk->execute([$ownerId, $organizationId]);
            if (!$uChk->fetchColumn()) {
                $pdo->rollBack();
                est_json(false, 'Assigned owner does not belong to your organization.', [], 403);
            }
        } elseif (!empty($ownerName)) {
            $uChk = $pdo->prepare("SELECT id FROM users WHERE (LOWER(name) = LOWER(?) OR LOWER(display_name) = LOWER(?)) AND organization_id = ? LIMIT 1");
            $uChk->execute([$ownerName, $ownerName, $organizationId]);
            $uId = $uChk->fetchColumn();
            if ($uId) $ownerId = (int)$uId;
            else $ownerId = (int)$existing['owner_id'];
        } else {
            $ownerId = isset($_POST['owner_id']) ? null : (int)$existing['owner_id'];
        }

        $allowedStatuses = ['New', 'In Review', 'More Info Needed', 'Approved', 'Rejected', 'Converted'];
        $newStatus = in_array($status, $allowedStatuses, true) ? $status : $existing['status'];

        $upStmt = $pdo->prepare("UPDATE estimate_requests SET
            company_id = ?,
            company_name = ?,
            contact_id = ?,
            contact_name = ?,
            email = ?,
            phone = ?,
            subject = ?,
            services = ?,
            budget = ?,
            timeline = ?,
            status = ?,
            owner_id = ?,
            description = ?,
            updated_at = NOW()
            WHERE id = ? AND organization_id = ?");
        $upStmt->execute([
            $newCompanyId,
            $newCompany,
            $newContactId,
            $newContact,
            $newEmail,
            $newPhone,
            $newSubject,
            $newServices,
            $newBudget,
            $newTimeline,
            $newStatus,
            $ownerId,
            $newDesc,
            $rawId,
            $organizationId
        ]);

        log_estimate_activity($pdo, $organizationId, $currentUserId, $rawId, 'request_updated', 'Request Updated', "Estimate request {$existing['request_code']} parameters updated.");

        if ($newStatus !== $existing['status']) {
            log_estimate_activity($pdo, $organizationId, $currentUserId, $rawId, 'status_changed', 'Status Changed', "Status changed from {$existing['status']} to {$newStatus}.");
        }

        $pdo->commit();

        est_json(true, "Estimate request updated successfully.", [
            'id'       => $existing['request_code'],
            'raw_id'   => $rawId,
            'subject'  => $newSubject,
            'company'  => $newCompany,
            'contact'  => $newContact,
            'email'    => $newEmail,
            'phone'    => $newPhone,
            'services' => $newServices,
            'budget'   => $newBudget,
            'timeline' => $newTimeline,
            'status'   => $newStatus,
            'owner_id' => $ownerId
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to update request: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: change_status
// --------------------------------------------------------------------------
if ($action === 'change_status') {
    require_estimate_perm('edit');

    $idParam = trim($_POST['id'] ?? '');
    $newStatus = trim($_POST['status'] ?? '');

    $allowedStatuses = ['New', 'In Review', 'More Info Needed', 'Approved', 'Rejected', 'Converted'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        est_json(false, 'Invalid status provided.', [], 422);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, request_code, status, company_name FROM estimate_requests 
                               WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id 
                               LIMIT 1 FOR UPDATE");
        $stmt->execute([
            ':num_id' => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'   => $idParam,
            ':org_id' => $organizationId
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$existing['id'];
        $oldStatus = $existing['status'];

        $upStmt = $pdo->prepare("UPDATE estimate_requests SET status = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upStmt->execute([$newStatus, $rawId, $organizationId]);

        log_estimate_activity($pdo, $organizationId, $currentUserId, $rawId, 'status_changed', 'Status Changed', "Request status changed from {$oldStatus} to {$newStatus}.");

        $pdo->commit();

        est_json(true, "Status updated to {$newStatus}.", [
            'id'         => $existing['request_code'],
            'raw_id'     => $rawId,
            'old_status' => $oldStatus,
            'status'     => $newStatus
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to change status: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: assign_owner
// --------------------------------------------------------------------------
if ($action === 'assign_owner') {
    require_estimate_perm('edit');

    $idParam   = trim($_POST['id'] ?? '');
    $ownerId   = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $ownerName = trim($_POST['owner'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, request_code, owner_id FROM estimate_requests 
                               WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id 
                               LIMIT 1 FOR UPDATE");
        $stmt->execute([
            ':num_id' => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'   => $idParam,
            ':org_id' => $organizationId
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$existing['id'];

        if ($ownerId) {
            $uChk = $pdo->prepare("SELECT id, name, display_name FROM users WHERE id = ? AND organization_id = ?");
            $uChk->execute([$ownerId, $organizationId]);
            $uRow = $uChk->fetch(PDO::FETCH_ASSOC);
            if (!$uRow) {
                $pdo->rollBack();
                est_json(false, 'Target owner does not belong to your organization.', [], 403);
            }
            $targetOwnerName = $uRow['display_name'] ?: $uRow['name'];
        } elseif (!empty($ownerName)) {
            $uChk = $pdo->prepare("SELECT id, name, display_name FROM users WHERE (LOWER(name) = LOWER(?) OR LOWER(display_name) = LOWER(?)) AND organization_id = ? LIMIT 1");
            $uChk->execute([$ownerName, $ownerName, $organizationId]);
            $uRow = $uChk->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $ownerId = (int)$uRow['id'];
                $targetOwnerName = $uRow['display_name'] ?: $uRow['name'];
            } else {
                $pdo->rollBack();
                est_json(false, 'Specified user name not found in organization.', [], 404);
            }
        } else {
            $pdo->rollBack();
            est_json(false, 'No valid owner specified.', [], 422);
        }

        $upStmt = $pdo->prepare("UPDATE estimate_requests SET owner_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upStmt->execute([$ownerId, $rawId, $organizationId]);

        log_estimate_activity($pdo, $organizationId, $currentUserId, $rawId, 'owner_assigned', 'Owner Assigned', "Reassigned request to {$targetOwnerName}.");

        $pdo->commit();

        est_json(true, "Request reassigned to {$targetOwnerName}.", [
            'id'       => $existing['request_code'],
            'raw_id'   => $rawId,
            'owner_id' => $ownerId,
            'owner'    => $targetOwnerName
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to assign owner: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: convert_to_deal
// --------------------------------------------------------------------------
if ($action === 'convert_to_deal') {
    require_estimate_perm('edit');

    $idParam = trim($_POST['id'] ?? '');
    if (empty($idParam)) {
        est_json(false, 'Request ID required.', [], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM estimate_requests 
                               WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id 
                               LIMIT 1 FOR UPDATE");
        $stmt->execute([
            ':num_id' => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'   => $idParam,
            ':org_id' => $organizationId
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$existing['id'];

        // Prevent accidental duplicate conversion if deal already exists
        if (!empty($existing['deal_id'])) {
            $chkDeal = $pdo->prepare("SELECT id, deal_code, name, stage FROM deals WHERE id = ? AND organization_id = ? LIMIT 1");
            $chkDeal->execute([(int)$existing['deal_id'], $organizationId]);
            $existingDeal = $chkDeal->fetch(PDO::FETCH_ASSOC);
            if ($existingDeal) {
                $pdo->commit();
                est_json(true, 'Request has already been converted to a deal.', [
                    'id'                => $existing['request_code'],
                    'raw_id'            => $rawId,
                    'deal_id'           => (int)$existingDeal['id'],
                    'deal_code'         => $existingDeal['deal_code'],
                    'deal_name'         => $existingDeal['name'],
                    'stage'             => $existingDeal['stage'],
                    'already_converted' => true,
                    'status'            => 'Converted'
                ]);
            }
        }

        // Dynamically determine the initial active pipeline stage for this organization
        $stgStmt = $pdo->prepare("SELECT name, probability FROM pipeline_stages WHERE organization_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $stgStmt->execute([$organizationId]);
        $initStageRow = $stgStmt->fetch(PDO::FETCH_ASSOC);
        $stage = $initStageRow ? $initStageRow['name'] : 'Prospect';
        $probability = $initStageRow && isset($initStageRow['probability']) ? (float)$initStageRow['probability'] : 10.0;

        // Try to match or resolve contact_id if empty
        $contactId = !empty($existing['contact_id']) ? (int)$existing['contact_id'] : null;
        if (!$contactId && (!empty($existing['email']) || !empty($existing['contact_name']))) {
            $cStmt = $pdo->prepare("SELECT id FROM contacts WHERE organization_id = ? AND (email = ? OR name = ?) LIMIT 1");
            $cStmt->execute([$organizationId, $existing['email'] ?? '', $existing['contact_name'] ?? '']);
            $foundCId = $cStmt->fetchColumn();
            if ($foundCId) $contactId = (int)$foundCId;
        }

        // Generate next deal_code for deals table
        $year = date('Y');
        $dealPrefix = "DEAL-{$year}-";
        $dealCodeStmt = $pdo->prepare("SELECT deal_code FROM deals WHERE organization_id = ? AND deal_code LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $dealCodeStmt->execute([$organizationId, $dealPrefix . '%']);
        $lastDealCode = $dealCodeStmt->fetchColumn();

        $nextDealNum = 1;
        if ($lastDealCode) {
            $parts = explode('-', $lastDealCode);
            $lastNum = (int)end($parts);
            if ($lastNum > 0) $nextDealNum = $lastNum + 1;
        }
        $dealCode = sprintf('DEAL-%s-%03d', $year, $nextDealNum);

        // Estimate monetary value from budget string if possible
        $dealValue = 0.00;
        if (!empty($existing['budget'])) {
            preg_match_all('/[\d,]+/', $existing['budget'], $matches);
            if (!empty($matches[0])) {
                $nums = array_map(function($v) { return (float)str_replace(',', '', $v); }, $matches[0]);
                $dealValue = max($nums);
            }
        }

        // Create deal in existing deals table with lowercase status 'open' and matching pipeline stage
        $dealStmt = $pdo->prepare("INSERT INTO deals 
            (organization_id, deal_code, name, contact_id, contact_name, company, stage, value, probability, source, priority, product, description, status, assigned_to, close_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Medium', ?, ?, 'open', ?, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), NOW())");

        $dealStmt->execute([
            $organizationId,
            $dealCode,
            $existing['subject'],
            $contactId,
            $existing['contact_name'],
            $existing['company_name'],
            $stage,
            $dealValue,
            $probability,
            $existing['source'] ?: 'Estimate Request',
            $existing['services'] ?: 'General Services',
            $existing['description'] ?: 'Converted from Estimate Request ' . $existing['request_code'],
            $existing['owner_id'] ?: $currentUserId
        ]);

        $newDealId = (int)$pdo->lastInsertId();

        // Update estimate request
        $upStmt = $pdo->prepare("UPDATE estimate_requests SET status = 'Converted', deal_id = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upStmt->execute([$newDealId, $rawId, $organizationId]);

        // Polymorphic deal creation activity
        try {
            $dealActStmt = $pdo->prepare("INSERT INTO team_activities (organization_id, user_id, activity_type, title, description, related_entity, related_entity_id, created_by, created_at) VALUES (?, ?, 'deal_created', 'Deal Created', ?, 'deals', ?, ?, NOW())");
            $dealActStmt->execute([$organizationId, $currentUserId, "Deal converted from Estimate Request {$existing['request_code']}.", $newDealId, $currentUserId]);
        } catch (Throwable $e) {
            // activity logging failure shouldn't abort
        }

        log_estimate_activity($pdo, $organizationId, $currentUserId, $rawId, 'converted_to_deal', 'Converted to Deal', "Converted to active sales opportunity: {$existing['subject']} ({$dealCode}).");

        $pdo->commit();

        est_json(true, "Request converted to Sales Opportunity for {$existing['company_name']}.", [
            'id'        => $existing['request_code'],
            'raw_id'    => $rawId,
            'deal_id'   => $newDealId,
            'deal_code' => $dealCode,
            'stage'     => $stage,
            'status'    => 'Converted'
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to convert to deal: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: create_estimate
// --------------------------------------------------------------------------
if ($action === 'create_estimate') {
    require_estimate_perm('create');

    $idParam = trim($_POST['id'] ?? '');
    if (empty($idParam)) {
        est_json(false, 'Request ID required.', [], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, request_code, company_name FROM estimate_requests 
                               WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id 
                               LIMIT 1 FOR UPDATE");
        $stmt->execute([
            ':num_id' => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'   => $idParam,
            ':org_id' => $organizationId
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$existing['id'];

        // Set status to Approved per the existing workflow
        $upStmt = $pdo->prepare("UPDATE estimate_requests SET status = 'Approved', updated_at = NOW() WHERE id = ? AND organization_id = ?");
        $upStmt->execute([$rawId, $organizationId]);

        log_estimate_activity($pdo, $organizationId, $currentUserId, $rawId, 'estimate_created', 'Estimate Issued', "Issued formal pricing estimate to client.");

        $pdo->commit();

        est_json(true, "Estimate issued for {$existing['company_name']}.", [
            'id'     => $existing['request_code'],
            'raw_id' => $rawId,
            'status' => 'Approved'
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to issue estimate: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: duplicate
// --------------------------------------------------------------------------
if ($action === 'duplicate') {
    require_estimate_perm('create');

    $idParam = trim($_POST['id'] ?? '');
    if (empty($idParam)) {
        est_json(false, 'Request ID required.', [], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM estimate_requests 
                               WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id 
                               LIMIT 1 FOR UPDATE");
        $stmt->execute([
            ':num_id' => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'   => $idParam,
            ':org_id' => $organizationId
        ]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orig) {
            $pdo->rollBack();
            est_json(false, 'Original request not found.', [], 404);
        }

        $newCode = generate_next_request_code($pdo, $organizationId);
        $newSubject = $orig['subject'] . ' (Copy)';

        $insStmt = $pdo->prepare("INSERT INTO estimate_requests 
            (organization_id, request_code, subject, company_id, company_name, contact_id, contact_name, email, phone, services, budget, timeline, source, owner_id, status, description, requirements, preferred_start_date, attachments, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?, ?, ?, ?, ?, NOW())");

        $insStmt->execute([
            $organizationId,
            $newCode,
            $newSubject,
            $orig['company_id'],
            $orig['company_name'],
            $orig['contact_id'],
            $orig['contact_name'],
            $orig['email'],
            $orig['phone'],
            $orig['services'],
            $orig['budget'],
            $orig['timeline'],
            $orig['source'],
            $orig['owner_id'],
            $orig['description'],
            $orig['requirements'],
            $orig['preferred_start_date'],
            $orig['attachments'] ?: '[]',
            $currentUserId
        ]);

        $newId = (int)$pdo->lastInsertId();

        log_estimate_activity($pdo, $organizationId, $currentUserId, $newId, 'request_duplicated', 'Request Duplicated', "Duplicated from {$orig['request_code']}.");

        $pdo->commit();

        est_json(true, "Duplicated request as \"{$newSubject}\".", [
            'id'           => $newCode,
            'raw_id'       => $newId,
            'request_code' => $newCode,
            'subject'      => $newSubject
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to duplicate request: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: delete
// --------------------------------------------------------------------------
if ($action === 'delete') {
    require_estimate_perm('delete');

    $idParam = trim($_POST['id'] ?? '');
    if (empty($idParam)) {
        est_json(false, 'Request ID required for deletion.', [], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, request_code FROM estimate_requests 
                               WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id 
                               LIMIT 1 FOR UPDATE");
        $stmt->execute([
            ':num_id' => is_numeric($idParam) ? (int)$idParam : 0,
            ':code'   => $idParam,
            ':org_id' => $organizationId
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$existing['id'];

        // 1. Delete associated polymorphic notes
        $delNotes = $pdo->prepare("DELETE FROM team_notes WHERE organization_id = ? AND related_type = 'estimate_requests' AND related_id = ?");
        $delNotes->execute([$organizationId, $rawId]);

        // 2. Delete associated polymorphic activities
        $delActs = $pdo->prepare("DELETE FROM team_activities WHERE organization_id = ? AND related_entity = 'estimate_requests' AND related_entity_id = ?");
        $delActs->execute([$organizationId, $rawId]);

        // 3. Delete estimate request itself
        $delReq = $pdo->prepare("DELETE FROM estimate_requests WHERE id = ? AND organization_id = ?");
        $delReq->execute([$rawId, $organizationId]);

        $pdo->commit();

        // 4. Clean up uploaded files on disk if any
        $uploadDir = dirname(dirname(__DIR__)) . "/uploads/estimate_requests/{$organizationId}/{$rawId}";
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) @unlink($f);
            }
            @rmdir($uploadDir);
        }

        est_json(true, "Estimate request removed.", ['id' => $existing['request_code'], 'raw_id' => $rawId]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        est_json(false, 'Failed to delete request: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: notes_list, notes_create, notes_update, notes_toggle_pin, notes_delete
// --------------------------------------------------------------------------
if (strpos($action, 'notes_') === 0) {
    $subAction = substr($action, 6);

    if ($subAction === 'list') {
        require_estimate_perm('view');
        $idParam = trim($_GET['request_id'] ?? '');

        try {
            $rStmt = $pdo->prepare("SELECT id FROM estimate_requests WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id LIMIT 1");
            $rStmt->execute([':num_id' => is_numeric($idParam) ? (int)$idParam : 0, ':code' => $idParam, ':org_id' => $organizationId]);
            $rawId = (int)$rStmt->fetchColumn();

            if (!$rawId) {
                est_json(false, 'Estimate request not found.', [], 404);
            }

            $stmt = $pdo->prepare("SELECT tn.*, u.name AS author_name, u.display_name AS author_display_name
                                   FROM team_notes tn
                                   LEFT JOIN users u ON u.id = tn.created_by
                                   WHERE tn.organization_id = ? AND tn.related_type = 'estimate_requests' AND tn.related_id = ?
                                   ORDER BY tn.is_pinned DESC, tn.created_at DESC");
            $stmt->execute([$organizationId, $rawId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notes = [];
            foreach ($rows as $n) {
                $notes[] = [
                    'id'      => (int)$n['id'],
                    'date'    => date('M d, Y', strtotime($n['created_at'])),
                    'author'  => $n['author_display_name'] ?: ($n['author_name'] ?: 'Admin'),
                    'title'   => $n['title'] ?: '',
                    'text'    => $n['content'],
                    'content' => $n['content'],
                    'pinned'  => (bool)$n['is_pinned']
                ];
            }
            est_json(true, 'Notes retrieved.', $notes);
        } catch (Throwable $e) {
            est_json(false, 'Failed to fetch notes: ' . $e->getMessage(), [], 500);
        }
    }

    if ($subAction === 'create') {
        require_estimate_perm('edit');
        $idParam = trim($_POST['request_id'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $pinned  = !empty($_POST['pinned']) ? 1 : 0;

        if (empty($content)) {
            est_json(false, 'Note content cannot be empty.', [], 422);
        }

        try {
            $rStmt = $pdo->prepare("SELECT id FROM estimate_requests WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id LIMIT 1");
            $rStmt->execute([':num_id' => is_numeric($idParam) ? (int)$idParam : 0, ':code' => $idParam, ':org_id' => $organizationId]);
            $rawId = (int)$rStmt->fetchColumn();

            if (!$rawId) {
                est_json(false, 'Estimate request not found.', [], 404);
            }

            $insStmt = $pdo->prepare("INSERT INTO team_notes 
                (organization_id, member_id, related_type, related_id, created_by, title, content, is_pinned, created_at, updated_at)
                VALUES (?, ?, 'estimate_requests', ?, ?, 'Note', ?, ?, NOW(), NOW())");
            $insStmt->execute([$organizationId, $currentUserId, $rawId, $currentUserId, $content, $pinned]);
            $noteId = (int)$pdo->lastInsertId();

            est_json(true, 'Note added to request.', [
                'id'      => $noteId,
                'date'    => date('M d, Y'),
                'author'  => $currentUser['display_name'] ?: ($currentUser['name'] ?: 'Admin'),
                'text'    => $content,
                'content' => $content,
                'pinned'  => (bool)$pinned
            ]);
        } catch (Throwable $e) {
            est_json(false, 'Failed to add note: ' . $e->getMessage(), [], 500);
        }
    }

    if ($subAction === 'update') {
        require_estimate_perm('edit');
        $noteId  = (int)($_POST['note_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $pinned  = isset($_POST['pinned']) ? ((bool)$_POST['pinned'] ? 1 : 0) : null;

        if ($noteId <= 0 || empty($content)) {
            est_json(false, 'Valid note ID and content required.', [], 422);
        }

        try {
            $nStmt = $pdo->prepare("SELECT id, is_pinned FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'estimate_requests' LIMIT 1");
            $nStmt->execute([$noteId, $organizationId]);
            $curNote = $nStmt->fetch(PDO::FETCH_ASSOC);

            if (!$curNote) {
                est_json(false, 'Note not found.', [], 404);
            }

            $pinVal = $pinned !== null ? $pinned : (int)$curNote['is_pinned'];

            $upStmt = $pdo->prepare("UPDATE team_notes SET content = ?, is_pinned = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
            $upStmt->execute([$content, $pinVal, $noteId, $organizationId]);

            est_json(true, 'Note updated successfully.', ['id' => $noteId, 'content' => $content, 'pinned' => (bool)$pinVal]);
        } catch (Throwable $e) {
            est_json(false, 'Failed to update note: ' . $e->getMessage(), [], 500);
        }
    }

    if ($subAction === 'toggle_pin') {
        require_estimate_perm('edit');
        $noteId = (int)($_POST['note_id'] ?? 0);
        if ($noteId <= 0) {
            est_json(false, 'Valid note ID required.', [], 400);
        }

        try {
            $nStmt = $pdo->prepare("SELECT id, is_pinned FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'estimate_requests' LIMIT 1");
            $nStmt->execute([$noteId, $organizationId]);
            $cur = $nStmt->fetch(PDO::FETCH_ASSOC);
            if (!$cur) {
                est_json(false, 'Note not found.', [], 404);
            }

            $newPin = $cur['is_pinned'] ? 0 : 1;
            $upStmt = $pdo->prepare("UPDATE team_notes SET is_pinned = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
            $upStmt->execute([$newPin, $noteId, $organizationId]);

            est_json(true, $newPin ? 'Note pinned to top.' : 'Note unpinned.', ['id' => $noteId, 'pinned' => (bool)$newPin]);
        } catch (Throwable $e) {
            est_json(false, 'Failed to toggle pin: ' . $e->getMessage(), [], 500);
        }
    }

    if ($subAction === 'delete') {
        require_estimate_perm('edit');
        $noteId = (int)($_POST['note_id'] ?? 0);
        if ($noteId <= 0) {
            est_json(false, 'Valid note ID required.', [], 400);
        }

        try {
            $delStmt = $pdo->prepare("DELETE FROM team_notes WHERE id = ? AND organization_id = ? AND related_type = 'estimate_requests'");
            $delStmt->execute([$noteId, $organizationId]);

            est_json(true, 'Note deleted.', ['id' => $noteId]);
        } catch (Throwable $e) {
            est_json(false, 'Failed to delete note: ' . $e->getMessage(), [], 500);
        }
    }
}

// --------------------------------------------------------------------------
// ACTION: log_activity
// --------------------------------------------------------------------------
if ($action === 'log_activity') {
    require_estimate_perm('edit');

    $idParam = trim($_POST['id'] ?? '');
    $text    = trim($_POST['text'] ?? '');

    if (empty($idParam) || empty($text)) {
        est_json(false, 'Request ID and activity text required.', [], 422);
    }

    try {
        $rStmt = $pdo->prepare("SELECT id, request_code FROM estimate_requests WHERE (id = :num_id OR request_code = :code) AND organization_id = :org_id LIMIT 1");
        $rStmt->execute([':num_id' => is_numeric($idParam) ? (int)$idParam : 0, ':code' => $idParam, ':org_id' => $organizationId]);
        $row = $rStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            est_json(false, 'Estimate request not found.', [], 404);
        }

        $rawId = (int)$row['id'];

        log_estimate_activity($pdo, $organizationId, $currentUserId, $rawId, 'manual_activity', 'Activity Logged', $text);

        est_json(true, 'Activity logged successfully.', [
            'id'   => $row['request_code'],
            'date' => date('Y-m-d h:i A'),
            'user' => $currentUser['display_name'] ?: ($currentUser['name'] ?: 'Admin'),
            'text' => $text
        ]);
    } catch (Throwable $e) {
        est_json(false, 'Failed to log activity: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// ACTION: export_csv
// --------------------------------------------------------------------------
if ($action === 'export_csv') {
    require_estimate_perm('export');

    try {
        $search  = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
        $status  = trim($_GET['status'] ?? 'All');
        $service = trim($_GET['service'] ?? 'All');
        $sort    = trim($_GET['sort'] ?? 'newest');

        $where = ["er.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($search !== '') {
            $sClause = [
                "er.request_code LIKE :s_code",
                "er.subject LIKE :s_subj",
                "er.company_name LIKE :s_comp",
                "er.contact_name LIKE :s_cont",
                "er.email LIKE :s_email",
                "er.services LIKE :s_serv"
            ];
            $sTerm = "%{$search}%";
            $params[':s_code']  = $sTerm;
            $params[':s_subj']  = $sTerm;
            $params[':s_comp']  = $sTerm;
            $params[':s_cont']  = $sTerm;
            $params[':s_email'] = $sTerm;
            $params[':s_serv']  = $sTerm;

            if (is_numeric($search)) {
                $sClause[] = "er.id = :s_id";
                $params[':s_id'] = (int)$search;
            }

            $where[] = "(" . implode(' OR ', $sClause) . ")";
        }

        if ($status !== '' && $status !== 'All') {
            $where[] = "er.status = :status";
            $params[':status'] = $status;
        }

        if ($service !== '' && $service !== 'All') {
            $where[] = "er.services LIKE :service";
            $params[':service'] = "%{$service}%";
        }

        $whereSql = implode(' AND ', $where);

        $orderBy = "er.created_at DESC, er.id DESC";
        if ($sort === 'oldest') $orderBy = "er.created_at ASC, er.id ASC";
        elseif ($sort === 'customer-asc') $orderBy = "er.company_name ASC, er.contact_name ASC";
        elseif ($sort === 'budget-desc') $orderBy = "er.budget DESC, er.id DESC";

        $query = "SELECT er.*, u.name AS owner_name, u.display_name AS owner_display_name
                  FROM estimate_requests er
                  LEFT JOIN users u ON u.id = er.owner_id
                  WHERE {$whereSql}
                  ORDER BY {$orderBy}";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="NexFlow_estimate_requests_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Subject', 'Company', 'Contact', 'Email', 'Services', 'Budget', 'Owner', 'Status', 'CreatedDate']);

        foreach ($rows as $r) {
            $owner = $r['owner_display_name'] ?: ($r['owner_name'] ?: 'Unassigned');
            fputcsv($out, [
                $r['request_code'],
                $r['subject'],
                $r['company_name'],
                $r['contact_name'],
                $r['email'],
                $r['services'],
                $r['budget'],
                $owner,
                $r['status'],
                date('Y-m-d', strtotime($r['created_at']))
            ]);
        }
        fclose($out);
        exit;
    } catch (Throwable $e) {
        est_json(false, 'CSV export failed: ' . $e->getMessage(), [], 500);
    }
}

// --------------------------------------------------------------------------
// Unknown Action Fallback
// --------------------------------------------------------------------------
est_json(false, "Unknown action: {$action}", [], 400);
