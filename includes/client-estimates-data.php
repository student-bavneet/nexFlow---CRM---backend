<?php
/**
 * NexFlow CRM — Client Portal Estimates Data Loader
 * Server-side loader providing authoritative estimate request data and KPIs
 * strictly isolated to the authenticated organization and company.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

/**
 * Extract currency symbol safely from organization currency string
 */
function client_get_estimate_currency_symbol(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $curr = (string)($stmt->fetchColumn() ?: 'USD ($)');

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

/**
 * Status mapping for Client Portal badge display
 */
function client_get_estimate_status_meta(string $status): array
{
    switch ($status) {
        case 'Approved':
            return ['label' => 'Quotation Issued', 'badgeClass' => 'blue', 'is_actionable' => true];
        case 'Converted':
            return ['label' => 'Accepted', 'badgeClass' => 'green', 'is_actionable' => false];
        case 'Rejected':
            return ['label' => 'Declined', 'badgeClass' => 'red', 'is_actionable' => false];
        case 'In Review':
            return ['label' => 'In Review', 'badgeClass' => 'amber', 'is_actionable' => false];
        case 'More Info Needed':
            return ['label' => 'More Info Needed', 'badgeClass' => 'amber', 'is_actionable' => false];
        case 'New':
        default:
            return ['label' => 'Pending Review', 'badgeClass' => 'blue', 'is_actionable' => false];
    }
}

/**
 * Calculate KPI summary counts for authenticated client company
 */

function validate_estimate_budget(?string $budgetInput, ?string &$cleanBudget, string &$error): bool {
    $raw = trim((string)$budgetInput);
    if ($raw === '' || $raw === '$0' || $raw === '0') {
        $cleanBudget = '';
        return true;
    }

    if (preg_match('/(?:^|\s+)-\s*\$?\s*\d+/', $raw) && !preg_match('/\d+\s*-\s*\$?\s*\d+/', $raw)) {
        $error = 'Budget cannot be negative.';
        return false;
    }

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
function client_estimates_get_kpis(PDO $pdo, int $orgId, int $companyId): array
{
    $stmtKpi = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END), 0) AS count_approved,
            COALESCE(SUM(CASE WHEN status IN ('New', 'In Review', 'More Info Needed') THEN 1 ELSE 0 END), 0) AS count_in_review,
            COALESCE(SUM(CASE WHEN status IN ('New', 'In Review', 'More Info Needed', 'Approved') THEN 1 ELSE 0 END), 0) AS count_pending,
            COALESCE(SUM(CASE WHEN status = 'Converted' THEN 1 ELSE 0 END), 0) AS count_converted,
            COALESCE(SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END), 0) AS count_rejected
        FROM estimate_requests
        WHERE organization_id = ? 
          AND company_id = ?
    ");
    $stmtKpi->execute([$orgId, $companyId]);
    $kpiRow = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total'     => (int)($kpiRow['total_count'] ?? 0),
        'approved'  => (int)($kpiRow['count_approved'] ?? 0),
        'in_review' => (int)($kpiRow['count_in_review'] ?? 0),
        'pending'   => (int)($kpiRow['count_pending'] ?? 0),
        'converted' => (int)($kpiRow['count_converted'] ?? 0),
        'accepted'  => (int)($kpiRow['count_converted'] ?? 0),
        'rejected'  => (int)($kpiRow['count_rejected'] ?? 0),
        'declined'  => (int)($kpiRow['count_rejected'] ?? 0),
    ];
}

/**
 * Fetch estimates list strictly isolated to authenticated organization and company
 */
function client_estimates_get_list(PDO $pdo, int $orgId, int $companyId, array $filters = []): array
{
    $currencySymbol = client_get_estimate_currency_symbol($pdo, $orgId);

    $where = [
        'er.organization_id = ?',
        'er.company_id = ?'
    ];
    $params = [$orgId, $companyId];

    $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
    if ($search !== '') {
        $where[] = '(er.subject LIKE ? OR er.request_code LIKE ? OR er.services LIKE ? OR d.name LIKE ?)';
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $statusFilter = trim((string)($filters['status'] ?? 'All'));
    if ($statusFilter !== '' && $statusFilter !== 'All') {
        if ($statusFilter === 'Pending') {
            $where[] = "er.status IN ('New', 'In Review', 'More Info Needed', 'Approved')";
        } elseif ($statusFilter === 'Accepted' || $statusFilter === 'Converted') {
            $where[] = "er.status = 'Converted'";
        } elseif ($statusFilter === 'Declined' || $statusFilter === 'Rejected' || $statusFilter === 'Expired') {
            $where[] = "er.status = 'Rejected'";
        } elseif ($statusFilter === 'Approved') {
            $where[] = "er.status = 'Approved'";
        } elseif ($statusFilter === 'In Review') {
            $where[] = "er.status = 'In Review'";
        } elseif ($statusFilter === 'New') {
            $where[] = "er.status = 'New'";
        } else {
            $where[] = 'er.status = ?';
            $params[] = $statusFilter;
        }
    }

    $whereSql = implode(' AND ', $where);

    $sortBy = trim((string)($filters['sort'] ?? 'newest'));
    $orderSql = 'er.created_at DESC, er.id DESC';
    if ($sortBy === 'oldest' || $sortBy === 'date_asc') {
        $orderSql = 'er.created_at ASC, er.id ASC';
    } elseif ($sortBy === 'name') {
        $orderSql = 'er.subject ASC, er.id DESC';
    } elseif ($sortBy === 'status') {
        $orderSql = 'er.status ASC, er.id DESC';
    } elseif ($sortBy === 'budget_desc') {
        $orderSql = 'er.budget DESC, er.id DESC';
    } elseif ($sortBy === 'budget_asc') {
        $orderSql = 'er.budget ASC, er.id ASC';
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

    $estimates = [];
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
        $estimates[] = $row;
    }

    return $estimates;
}

/**
 * Fetch a single estimate detail strictly isolated to authenticated organization and company
 */
function client_estimates_get_detail(PDO $pdo, int $orgId, int $companyId, int $estimateId): ?array
{
    if ($estimateId <= 0) {
        return null;
    }

    $currencySymbol = client_get_estimate_currency_symbol($pdo, $orgId);

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
            p.project_code,
            p.status AS project_status,
            p.progress AS project_progress
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
        return null;
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
    $est['deal_title']         = $est['deal_name'] ?? '';

    // Client-safe activity history (kept separate from delivery timeline)
    $activities = client_estimates_get_activities($pdo, $orgId, $estimateId);
    $est['activities']       = $activities;
    $est['activity_history'] = $activities;
    $est['timelineLog']      = $activities;

    return $est;
}

/**
 * Fetch client-safe activity timeline for an estimate request
 */
function client_estimates_get_activities(PDO $pdo, int $orgId, int $estimateId): array
{
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
                'id'             => (int)$act['id'],
                'activity_title' => $cleanTitle,
                'title'          => $cleanTitle,
                'description'    => $act['description'] ?? '',
                'text'           => $cleanText,
                'date'           => $act['date'],
            ];
        }
    }

    return $timeline;
}

/**
 * Log client portal estimate activity in team_activities
 */
function client_estimates_log_activity(
    PDO $pdo, 
    int $orgId, 
    int $contactId, 
    string $entity, 
    int $entityId, 
    string $title, 
    string $description
): bool {
    try {
        $type = 'status_changed';
        if (strpos($title, 'Accepted') !== false) {
            $type = 'request_accepted';
        } elseif (strpos($title, 'Declined') !== false) {
            $type = 'request_declined';
        }

        $stmt = $pdo->prepare("
            INSERT INTO team_activities (
                organization_id, 
                user_id, 
                activity_type, 
                title, 
                description, 
                related_entity, 
                related_entity_id, 
                created_by,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $orgId,
            $contactId,
            $type,
            $title,
            $description,
            $entity,
            $entityId,
            $contactId
        ]);
    } catch (Throwable $e) {
        error_log("client_estimates_log_activity error: " . $e->getMessage());
        return false;
    }
}

/**
 * Load server-side KPIs and estimates list for authenticated client company.
 */
function client_get_estimates_data(PDO $pdo, int $orgId, int $companyId): array
{
    $currencySymbol = client_get_estimate_currency_symbol($pdo, $orgId);
    $kpis = client_estimates_get_kpis($pdo, $orgId, $companyId);
    $estimates = client_estimates_get_list($pdo, $orgId, $companyId);

    return [
        'kpis'           => $kpis,
        'estimates'      => $estimates,
        'currencySymbol' => $currencySymbol,
    ];
}