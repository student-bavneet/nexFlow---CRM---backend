<?php
/**
 * NexFlow CRM - Reports & Analytics Backend API
 * Multi-tenant, strictly organization-scoped reporting engine.
 * Computes real KPIs, time-series charts, pipeline funnel, team leaderboard,
 * at-risk deals, activity metrics, real 7x24 heatmap, dynamic lookups, and CSV exports.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure clean JSON output for API actions (except CSV export)
$action = $_GET['action'] ?? $_POST['action'] ?? 'bootstrap';

if ($action !== 'export_csv') {
    header('Content-Type: application/json; charset=utf-8');
}

function report_json(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authenticate user strictly
$currentUser = nexflow_current_user();
if (!$currentUser) {
    if ($action === 'export_csv') {
        http_response_code(401);
        die('Unauthenticated. Please log in.');
    }
    report_json(false, 'Unauthenticated. Please log in.', [], 401);
}

$organizationId = (int)$currentUser['organization_id'];
$currentUserId = (int)$currentUser['id'];

// Permission check
if (!is_super_admin($currentUserId) && function_exists('has_permission') && !has_permission('reports', $currentUserId)) {
    if ($action === 'export_csv') {
        http_response_code(403);
        die('Permission denied.');
    }
    report_json(false, 'Access denied. You do not have permission to view reports.', [], 403);
}

try {
    $pdo = nexflow_db();
} catch (Throwable $e) {
    if ($action === 'export_csv') {
        http_response_code(500);
        die('Database connection failed.');
    }
    report_json(false, 'Database connection failed.', [], 500);
}

// Support GET, POST and JSON payload
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

// Read filter parameters
$dateRange = $_GET['dateRange'] ?? $input['dateRange'] ?? 'last30';
$customFrom = $_GET['dateFrom'] ?? $input['dateFrom'] ?? null;
$customTo = $_GET['dateTo'] ?? $input['dateTo'] ?? null;
$teamRep = $_GET['teamRep'] ?? $input['teamRep'] ?? 'all';
$pipelineFilter = $_GET['pipeline'] ?? $input['pipeline'] ?? 'all';
$leadSourceFilter = $_GET['leadSource'] ?? $input['leadSource'] ?? 'all';
$regionFilter = $_GET['region'] ?? $input['region'] ?? 'all';

/**
 * Compute accurate date boundaries for current and previous periods.
 */
function get_report_dates(string $range, ?string $customFrom = null, ?string $customTo = null): array
{
    $now = new DateTime('now');
    $todayStart = (clone $now)->setTime(0, 0, 0);
    $todayEnd = (clone $now)->setTime(23, 59, 59);

    switch ($range) {
        case 'today':
            $currStart = clone $todayStart;
            $currEnd = clone $todayEnd;
            $prevStart = (clone $currStart)->modify('-1 day');
            $prevEnd = (clone $currEnd)->modify('-1 day');
            $periodLabel = 'vs yesterday';
            break;

        case 'last7':
            $currStart = (clone $todayStart)->modify('-7 days');
            $currEnd = clone $todayEnd;
            $prevStart = (clone $currStart)->modify('-8 days');
            $prevEnd = (clone $currStart)->modify('-1 second');
            $periodLabel = 'vs prior 7 days';
            break;

        case 'thisMonth':
            $currStart = (clone $todayStart)->modify('first day of this month');
            $currEnd = clone $todayEnd;
            $prevStart = (clone $currStart)->modify('-1 month');
            $prevEnd = (clone $prevStart)->modify('+1 month -1 second');
            $periodLabel = 'vs last month';
            break;

        case 'lastMonth':
            $currStart = (clone $todayStart)->modify('first day of last month');
            $currEnd = (clone $todayStart)->modify('last day of last month')->setTime(23, 59, 59);
            $prevStart = (clone $currStart)->modify('-1 month');
            $prevEnd = (clone $prevStart)->modify('last day of this month')->setTime(23, 59, 59);
            $periodLabel = 'vs 2 months ago';
            break;

        case 'thisQuarter':
            $curMonth = (int)$now->format('n');
            $qStartMonth = (int)(floor(($curMonth - 1) / 3) * 3 + 1);
            $currStart = new DateTime($now->format('Y') . '-' . str_pad($qStartMonth, 2, '0', STR_PAD_LEFT) . '-01 00:00:00');
            $currEnd = clone $todayEnd;
            $prevStart = (clone $currStart)->modify('-3 months');
            $prevEnd = (clone $currStart)->modify('-1 second');
            $periodLabel = 'vs last quarter';
            break;

        case 'thisYear':
            $currStart = new DateTime($now->format('Y') . '-01-01 00:00:00');
            $currEnd = clone $todayEnd;
            $prevStart = (clone $currStart)->modify('-1 year');
            $prevEnd = (clone $currEnd)->modify('-1 year');
            $periodLabel = 'vs last year';
            break;

        case 'custom':
            $cStart = $customFrom ? new DateTime($customFrom . ' 00:00:00') : (clone $todayStart)->modify('-30 days');
            $cEnd = $customTo ? new DateTime($customTo . ' 23:59:59') : (clone $todayEnd);
            $currStart = $cStart;
            $currEnd = $cEnd;
            $daysDiff = max(1, (int)$currStart->diff($currEnd)->format('%r%a') + 1);
            $prevStart = (clone $currStart)->modify("-{$daysDiff} days");
            $prevEnd = (clone $currStart)->modify('-1 second');
            $periodLabel = 'vs prior period';
            break;

        case 'last30':
        default:
            $currStart = (clone $todayStart)->modify('-30 days');
            $currEnd = clone $todayEnd;
            $prevStart = (clone $currStart)->modify('-31 days');
            $prevEnd = (clone $currStart)->modify('-1 second');
            $periodLabel = 'vs prior 30 days';
            break;
    }

    return [
        'current_start'  => $currStart->format('Y-m-d H:i:s'),
        'current_end'    => $currEnd->format('Y-m-d H:i:s'),
        'previous_start' => $prevStart->format('Y-m-d H:i:s'),
        'previous_end'   => $prevEnd->format('Y-m-d H:i:s'),
        'period_label'   => $periodLabel
    ];
}

$dates = get_report_dates($dateRange, $customFrom, $customTo);

/**
 * Helper to calculate percentage change safely without NaN or Infinity.
 */
function calc_change(float $current, float $previous): array
{
    if ($previous <= 0.0) {
        if ($current > 0.0) {
            return ['pct' => 100.0, 'text' => '+100%', 'dir' => 'positive'];
        }
        return ['pct' => 0.0, 'text' => '0.0%', 'dir' => 'positive'];
    }

    $change = (($current - $previous) / $previous) * 100.0;
    $rounded = round($change, 1);
    $sign = $rounded > 0 ? '+' : '';
    $dir = $rounded >= 0 ? 'positive' : 'negative';

    return [
        'pct'  => $rounded,
        'text' => "{$sign}{$rounded}%",
        'dir'  => $dir
    ];
}

/**
 * Build shared WHERE clauses for deals based on active filters.
 */
function build_deal_filters(string $teamRep, string $pipelineFilter, string $leadSourceFilter, array &$params): string
{
    $sql = "";
    if ($teamRep !== 'all') {
        if (is_numeric($teamRep)) {
            $sql .= " AND d.assigned_to = :deal_rep";
            $params[':deal_rep'] = (int)$teamRep;
        } else {
            $sql .= " AND (d.assigned_to IN (SELECT id FROM users WHERE name = :deal_rep_name))";
            $params[':deal_rep_name'] = $teamRep;
        }
    }
    if ($pipelineFilter !== 'all') {
        $sql .= " AND (d.stage = :deal_pipeline OR d.stage IN (SELECT name FROM pipeline_stages WHERE name = :deal_pipeline))";
        $params[':deal_pipeline'] = $pipelineFilter;
    }
    if ($leadSourceFilter !== 'all') {
        $sql .= " AND d.source = :deal_source";
        $params[':deal_source'] = $leadSourceFilter;
    }
    return $sql;
}

// ==========================================
// 1. ACTION: BOOTSTRAP (Full Data Payload)
// ==========================================
if ($action === 'bootstrap' || $action === 'get_all') {
    // --------------------------------------------------
    // A. KPIs (Overview)
    // --------------------------------------------------
    // Revenue Rule: deals.value WHERE status = 'won'
    $kpiParamsCurr = [
        ':org' => $organizationId,
        ':c_start' => $dates['current_start'],
        ':c_end' => $dates['current_end']
    ];
    $dealFilterSqlCurr = build_deal_filters($teamRep, $pipelineFilter, $leadSourceFilter, $kpiParamsCurr);

    $kpiParamsPrev = [
        ':org' => $organizationId,
        ':p_start' => $dates['previous_start'],
        ':p_end' => $dates['previous_end']
    ];
    $dealFilterSqlPrev = build_deal_filters($teamRep, $pipelineFilter, $leadSourceFilter, $kpiParamsPrev);

    // Current period won deals summary
    $stmtWonCurr = $pdo->prepare("
        SELECT 
            COALESCE(SUM(d.value), 0) AS total_revenue,
            COUNT(d.id) AS won_count,
            COALESCE(AVG(d.value), 0) AS avg_deal_size,
            COALESCE(AVG(CASE WHEN d.close_date >= DATE(d.created_at) THEN DATEDIFF(d.close_date, DATE(d.created_at)) ELSE 0 END), 0) AS avg_cycle_days
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status = 'won'
          AND COALESCE(d.close_date, DATE(d.created_at)) BETWEEN DATE(:c_start) AND DATE(:c_end)
          {$dealFilterSqlCurr}
    ");
    $stmtWonCurr->execute($kpiParamsCurr);
    $wonCurr = $stmtWonCurr->fetch(PDO::FETCH_ASSOC);

    // Previous period won deals summary
    $stmtWonPrev = $pdo->prepare("
        SELECT 
            COALESCE(SUM(d.value), 0) AS total_revenue,
            COUNT(d.id) AS won_count,
            COALESCE(AVG(d.value), 0) AS avg_deal_size,
            COALESCE(AVG(CASE WHEN d.close_date >= DATE(d.created_at) THEN DATEDIFF(d.close_date, DATE(d.created_at)) ELSE 0 END), 0) AS avg_cycle_days
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status = 'won'
          AND COALESCE(d.close_date, DATE(d.created_at)) BETWEEN DATE(:p_start) AND DATE(:p_end)
          {$dealFilterSqlPrev}
    ");
    $stmtWonPrev->execute($kpiParamsPrev);
    $wonPrev = $stmtWonPrev->fetch(PDO::FETCH_ASSOC);

    // Won vs Lost for Win Rate (Current)
    $stmtWinCurr = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN d.status = 'won' THEN 1 ELSE 0 END) AS won_cnt,
            SUM(CASE WHEN d.status = 'lost' THEN 1 ELSE 0 END) AS lost_cnt
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status IN ('won', 'lost')
          AND COALESCE(d.close_date, DATE(d.created_at)) BETWEEN DATE(:c_start) AND DATE(:c_end)
          {$dealFilterSqlCurr}
    ");
    $stmtWinCurr->execute($kpiParamsCurr);
    $winCurr = $stmtWinCurr->fetch(PDO::FETCH_ASSOC);
    $wCountCurr = (int)($winCurr['won_cnt'] ?? 0);
    $lCountCurr = (int)($winCurr['lost_cnt'] ?? 0);
    $winRateCurr = ($wCountCurr + $lCountCurr) > 0 ? round(($wCountCurr / ($wCountCurr + $lCountCurr)) * 100, 1) : 0.0;

    // Won vs Lost for Win Rate (Previous)
    $stmtWinPrev = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN d.status = 'won' THEN 1 ELSE 0 END) AS won_cnt,
            SUM(CASE WHEN d.status = 'lost' THEN 1 ELSE 0 END) AS lost_cnt
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status IN ('won', 'lost')
          AND COALESCE(d.close_date, DATE(d.created_at)) BETWEEN DATE(:p_start) AND DATE(:p_end)
          {$dealFilterSqlPrev}
    ");
    $stmtWinPrev->execute($kpiParamsPrev);
    $winPrev = $stmtWinPrev->fetch(PDO::FETCH_ASSOC);
    $wCountPrev = (int)($winPrev['won_cnt'] ?? 0);
    $lCountPrev = (int)($winPrev['lost_cnt'] ?? 0);
    $winRatePrev = ($wCountPrev + $lCountPrev) > 0 ? round(($wCountPrev / ($wCountPrev + $lCountPrev)) * 100, 1) : 0.0;

    // Open Pipeline Value (Snapshot of current active open deals)
    $pipeParams = [':org' => $organizationId];
    $dealPipeSql = build_deal_filters($teamRep, $pipelineFilter, $leadSourceFilter, $pipeParams);
    $stmtPipe = $pdo->prepare("
        SELECT 
            COALESCE(SUM(d.value), 0) AS pipeline_value,
            COUNT(d.id) AS pipeline_count
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status = 'open'
          {$dealPipeSql}
    ");
    $stmtPipe->execute($pipeParams);
    $pipeData = $stmtPipe->fetch(PDO::FETCH_ASSOC);
    $pipelineVal = (float)($pipeData['pipeline_value'] ?? 0);
    $pipelineCount = (int)($pipeData['pipeline_count'] ?? 0);

    // Formulate Overview KPI Cards array matching the existing UI contract
    $revCurr = (float)$wonCurr['total_revenue'];
    $revPrev = (float)$wonPrev['total_revenue'];
    $revChange = calc_change($revCurr, $revPrev);

    $dealsCurr = (int)$wonCurr['won_count'];
    $dealsPrev = (int)$wonPrev['won_count'];
    $dealsChange = calc_change((float)$dealsCurr, (float)$dealsPrev);

    $winRateChange = calc_change($winRateCurr, $winRatePrev);

    $avgDealCurr = (float)$wonCurr['avg_deal_size'];
    $avgDealPrev = (float)$wonPrev['avg_deal_size'];
    $avgDealChange = calc_change($avgDealCurr, $avgDealPrev);

    $cycleDaysCurr = round((float)$wonCurr['avg_cycle_days'], 1);
    $cycleDaysPrev = round((float)$wonPrev['avg_cycle_days'], 1);
    $cycleChange = calc_change($cycleDaysCurr, $cycleDaysPrev);
    $cycleChange['dir'] = $cycleDaysCurr <= $cycleDaysPrev ? 'positive' : 'negative';

    $kpis = [
        [
            'id' => 'total_revenue',
            'label' => 'Total Revenue',
            'value' => '$' . number_format($revCurr, 0),
            'rawValue' => $revCurr,
            'change' => $revChange['text'],
            'changeDir' => $revChange['dir'],
            'periodText' => $dates['period_label'],
            'color' => '#7C3AED',
            'icon' => 'dollar-sign'
        ],
        [
            'id' => 'pipeline_value',
            'label' => 'Pipeline Value',
            'value' => '$' . number_format($pipelineVal, 0),
            'rawValue' => $pipelineVal,
            'change' => "{$pipelineCount} deals",
            'changeDir' => 'positive',
            'periodText' => 'Active open pipeline',
            'color' => '#0284C7',
            'icon' => 'briefcase'
        ],
        [
            'id' => 'deals_won',
            'label' => 'Won Deals',
            'value' => number_format($dealsCurr),
            'rawValue' => $dealsCurr,
            'change' => $dealsChange['text'],
            'changeDir' => $dealsChange['dir'],
            'periodText' => $dates['period_label'],
            'color' => '#10B981',
            'icon' => 'award'
        ],
        [
            'id' => 'win_rate',
            'label' => 'Win Rate',
            'value' => $winRateCurr . '%',
            'rawValue' => $winRateCurr,
            'change' => $winRateChange['text'],
            'changeDir' => $winRateChange['dir'],
            'periodText' => $dates['period_label'],
            'color' => '#F59E0B',
            'icon' => 'pie-chart'
        ],
        [
            'id' => 'avg_deal_size',
            'label' => 'Average Deal Size',
            'value' => '$' . number_format($avgDealCurr, 0),
            'rawValue' => $avgDealCurr,
            'change' => $avgDealChange['text'],
            'changeDir' => $avgDealChange['dir'],
            'periodText' => $dates['period_label'],
            'color' => '#8B5CF6',
            'icon' => 'trending-up'
        ],
        [
            'id' => 'avg_sales_cycle',
            'label' => 'Sales Cycle',
            'value' => $cycleDaysCurr . ' days',
            'rawValue' => $cycleDaysCurr,
            'change' => $cycleChange['text'],
            'changeDir' => $cycleChange['dir'],
            'periodText' => $dates['period_label'],
            'color' => '#EC4899',
            'icon' => 'clock'
        ]
    ];

    // --------------------------------------------------
    // B. Monthly Revenue Trend (Last 12 months)
    // --------------------------------------------------
    $stmtQuota = $pdo->prepare("SELECT COALESCE(SUM(quota), 0) FROM users WHERE organization_id = ? AND status = 'active'");
    $stmtQuota->execute([$organizationId]);
    $orgTotalQuota = (float)$stmtQuota->fetchColumn();

    $monthlyTrend = [];
    $startMonth = new DateTime('first day of this month');
    $startMonth->modify('-11 months');

    $trendParams = [
        ':org' => $organizationId,
        ':trend_start' => $startMonth->format('Y-m-01 00:00:00')
    ];
    $trendFilterSql = build_deal_filters($teamRep, $pipelineFilter, $leadSourceFilter, $trendParams);

    $stmtTrend = $pdo->prepare("
        SELECT 
            DATE_FORMAT(COALESCE(d.close_date, DATE(d.created_at)), '%Y-%m') AS ym,
            COALESCE(SUM(d.value), 0) AS monthly_revenue,
            COUNT(d.id) AS won_deals,
            COALESCE(AVG(d.value), 0) AS avg_deal_size,
            COALESCE(AVG(CASE WHEN d.close_date >= DATE(d.created_at) THEN DATEDIFF(d.close_date, DATE(d.created_at)) ELSE 0 END), 0) AS cycle_days
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status = 'won'
          AND COALESCE(d.close_date, DATE(d.created_at)) >= DATE(:trend_start)
          {$trendFilterSql}
        GROUP BY ym
    ");
    $stmtTrend->execute($trendParams);
    $trendRows = $stmtTrend->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    $stmtLost = $pdo->prepare("
        SELECT 
            DATE_FORMAT(COALESCE(d.close_date, DATE(d.created_at)), '%Y-%m') AS ym,
            COUNT(d.id) AS lost_deals
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status = 'lost'
          AND COALESCE(d.close_date, DATE(d.created_at)) >= DATE(:trend_start)
          {$trendFilterSql}
        GROUP BY ym
    ");
    $stmtLost->execute($trendParams);
    $lostRows = $stmtLost->fetchAll(PDO::FETCH_KEY_PAIR);

    $currCursor = clone $startMonth;
    for ($i = 0; $i < 12; $i++) {
        $ym = $currCursor->format('Y-m');
        $label = $currCursor->format('M Y');
        $revVal = isset($trendRows[$ym]) ? (float)$trendRows[$ym]['monthly_revenue'] : 0.0;
        $wonCnt = isset($trendRows[$ym]) ? (int)$trendRows[$ym]['won_deals'] : 0;
        $lostCnt = isset($lostRows[$ym]) ? (int)$lostRows[$ym] : 0;
        $avgSize = isset($trendRows[$ym]) ? round((float)$trendRows[$ym]['avg_deal_size'], 0) : 0;
        $cycle = isset($trendRows[$ym]) ? round((float)$trendRows[$ym]['cycle_days'], 1) : 0;

        $monthlyTrend[] = [
            'month' => $label,
            'ym' => $ym,
            'revenue' => $revVal,
            'target' => $orgTotalQuota,
            'prevRevenue' => 0.0,
            'wonDeals' => $wonCnt,
            'lostDeals' => $lostCnt,
            'avgDealSize' => $avgSize,
            'cycleDays' => $cycle
        ];
        $currCursor->modify('+1 month');
    }

    // --------------------------------------------------
    // C. Pipeline by Stage
    // --------------------------------------------------
    $stmtStages = $pdo->prepare("
        SELECT 
            ps.id,
            ps.name,
            ps.color,
            ps.sort_order,
            COUNT(d.id) AS count,
            COALESCE(SUM(d.value), 0) AS value,
            COALESCE(AVG(CASE WHEN d.created_at IS NOT NULL THEN DATEDIFF(NOW(), d.created_at) ELSE 0 END), 0) AS avg_days
        FROM pipeline_stages ps
        LEFT JOIN deals d ON (d.stage = ps.name) 
                         AND d.organization_id = ps.organization_id 
                         AND d.status = 'open'
        WHERE ps.organization_id = :org AND ps.is_active = 1
        GROUP BY ps.id, ps.name, ps.color, ps.sort_order
        ORDER BY ps.sort_order ASC
    ");
    $stmtStages->execute([':org' => $organizationId]);
    $stageRows = $stmtStages->fetchAll(PDO::FETCH_ASSOC);

    $pipelineStages = [];
    $totalPipelineSum = array_sum(array_column($stageRows, 'value'));
    foreach ($stageRows as $st) {
        $val = (float)$st['value'];
        $pct = $totalPipelineSum > 0 ? round(($val / $totalPipelineSum) * 100, 1) : 0.0;
        $pipelineStages[] = [
            'stage' => $st['name'],
            'count' => (int)$st['count'],
            'value' => $val,
            'convRate' => $pct . '%',
            'avgDays' => round((float)$st['avg_days'], 0),
            'color' => !empty($st['color']) ? $st['color'] : '#2563EB'
        ];
    }

    // --------------------------------------------------
    // D. Lead Sources Breakdown
    // --------------------------------------------------
    $leadSourceParams = [':org' => $organizationId];
    $stmtLeadSources = $pdo->prepare("
        SELECT 
            COALESCE(NULLIF(TRIM(l.source), ''), 'Unspecified') AS source_name,
            COUNT(l.id) AS total_leads,
            SUM(CASE WHEN l.status IN ('Qualified', 'qualified') THEN 1 ELSE 0 END) AS qualified_leads
        FROM leads l
        WHERE l.organization_id = :org
        GROUP BY source_name
        ORDER BY total_leads DESC
    ");
    $stmtLeadSources->execute($leadSourceParams);
    $leadSourceRows = $stmtLeadSources->fetchAll(PDO::FETCH_ASSOC);

    $stmtWonSources = $pdo->prepare("
        SELECT 
            COALESCE(NULLIF(TRIM(d.source), ''), 'Unspecified') AS source_name,
            COUNT(d.id) AS won_deals,
            COALESCE(SUM(d.value), 0) AS revenue
        FROM deals d
        WHERE d.organization_id = :org AND d.status = 'won'
        GROUP BY source_name
    ");
    $stmtWonSources->execute([':org' => $organizationId]);
    $wonSourceMap = $stmtWonSources->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    $palette = ['#7C3AED', '#0284C7', '#10B981', '#F59E0B', '#EC4899', '#64748B', '#6366F1'];
    $leadSources = [];
    $colorIndex = 0;

    if (empty($leadSourceRows)) {
        $leadSources[] = [
            'source' => 'Direct / CRM Deals',
            'leads' => $pipelineCount,
            'qualified' => $pipelineCount,
            'opps' => $pipelineCount,
            'won' => $dealsCurr,
            'revenue' => $revCurr,
            'convRate' => $pipelineCount > 0 ? round(($dealsCurr / $pipelineCount) * 100, 1) . '%' : '0%',
            'color' => $palette[0]
        ];
    } else {
        foreach ($leadSourceRows as $ls) {
            $src = $ls['source_name'];
            $lCount = (int)$ls['total_leads'];
            $qCount = (int)$ls['qualified_leads'];
            $wonCnt = isset($wonSourceMap[$src]) ? (int)$wonSourceMap[$src]['won_deals'] : 0;
            $srcRev = isset($wonSourceMap[$src]) ? (float)$wonSourceMap[$src]['revenue'] : 0.0;
            $conv = $lCount > 0 ? round(($wonCnt / $lCount) * 100, 1) . '%' : '0.0%';

            $leadSources[] = [
                'source' => $src,
                'leads' => $lCount,
                'qualified' => $qCount,
                'opps' => $qCount,
                'won' => $wonCnt,
                'revenue' => $srcRev,
                'convRate' => $conv,
                'color' => $palette[$colorIndex % count($palette)]
            ];
            $colorIndex++;
        }
    }

    // --------------------------------------------------
    // E. Lead Conversion Funnel
    // --------------------------------------------------
    $totalLeadsCnt = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE organization_id = {$organizationId}")->fetchColumn();
    $qualifiedLeadsCnt = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE organization_id = {$organizationId} AND status IN ('Qualified', 'qualified')")->fetchColumn();
    $proposalDealsCnt = (int)$pdo->query("SELECT COUNT(*) FROM deals WHERE organization_id = {$organizationId} AND (stage LIKE '%Proposal%' OR stage LIKE '%Negotiation%')")->fetchColumn();

    $funnelBase = max($totalLeadsCnt, 1);
    $funnelItems = [
        [
            'stage' => 'Total Leads',
            'count' => $totalLeadsCnt,
            'rate' => '100%',
            'width' => 100,
            'drop' => '0% drop'
        ],
        [
            'stage' => 'Qualified Leads',
            'count' => $qualifiedLeadsCnt,
            'rate' => round(($qualifiedLeadsCnt / $funnelBase) * 100, 1) . '%',
            'width' => max(15, min(100, round(($qualifiedLeadsCnt / $funnelBase) * 100))),
            'drop' => '-' . round((1 - ($qualifiedLeadsCnt / $funnelBase)) * 100, 1) . '% drop'
        ],
        [
            'stage' => 'Active Opportunities',
            'count' => $pipelineCount,
            'rate' => round(($pipelineCount / $funnelBase) * 100, 1) . '%',
            'width' => max(12, min(100, round(($pipelineCount / $funnelBase) * 100))),
            'drop' => '-' . round(max(0, 100 - (($pipelineCount / $funnelBase) * 100)), 1) . '% drop'
        ],
        [
            'stage' => 'Proposal & Negotiation',
            'count' => $proposalDealsCnt,
            'rate' => round(($proposalDealsCnt / $funnelBase) * 100, 1) . '%',
            'width' => max(10, min(100, round(($proposalDealsCnt / $funnelBase) * 100))),
            'drop' => '-' . round(max(0, 100 - (($proposalDealsCnt / $funnelBase) * 100)), 1) . '% drop'
        ],
        [
            'stage' => 'Closed Won Deals',
            'count' => $dealsCurr,
            'rate' => round(($dealsCurr / $funnelBase) * 100, 1) . '%',
            'width' => max(8, min(100, round(($dealsCurr / $funnelBase) * 100))),
            'drop' => '-' . round(max(0, 100 - (($dealsCurr / $funnelBase) * 100)), 1) . '% drop'
        ]
    ];

    // --------------------------------------------------
    // F. Top High-Value Won Deals
    // --------------------------------------------------
    $topParams = [':org' => $organizationId];
    $stmtTopDeals = $pdo->prepare("
        SELECT 
            d.id,
            d.name AS deal,
            COALESCE(NULLIF(d.company, ''), 'Direct Client') AS company,
            COALESCE(u.name, 'Unassigned') AS owner,
            d.stage,
            d.value,
            COALESCE(d.close_date, DATE(d.created_at)) AS closeDate
        FROM deals d
        LEFT JOIN users u ON u.id = d.assigned_to
        WHERE d.organization_id = :org AND d.status = 'won'
        ORDER BY d.value DESC
        LIMIT 10
    ");
    $stmtTopDeals->execute($topParams);
    $topDeals = $stmtTopDeals->fetchAll(PDO::FETCH_ASSOC);

    if (empty($topDeals)) {
        $stmtTopOpen = $pdo->prepare("
            SELECT 
                d.id,
                d.name AS deal,
                COALESCE(NULLIF(d.company, ''), 'Direct Client') AS company,
                COALESCE(u.name, 'Unassigned') AS owner,
                d.stage,
                d.value,
                COALESCE(d.close_date, DATE(d.created_at)) AS closeDate
            FROM deals d
            LEFT JOIN users u ON u.id = d.assigned_to
            WHERE d.organization_id = :org
            ORDER BY d.value DESC
            LIMIT 10
        ");
        $stmtTopOpen->execute($topParams);
        $topDeals = $stmtTopOpen->fetchAll(PDO::FETCH_ASSOC);
    }

    // --------------------------------------------------
    // G. At-Risk Deals
    // --------------------------------------------------
    $stmtRisk = $pdo->prepare("
        SELECT 
            d.id,
            d.name AS deal,
            COALESCE(NULLIF(d.company, ''), 'Direct Client') AS company,
            d.stage,
            d.value,
            DATEDIFF(NOW(), COALESCE(d.updated_at, d.created_at)) AS daysInStage,
            CASE 
                WHEN d.close_date IS NOT NULL AND d.close_date < CURDATE() THEN 'Close date overdue'
                WHEN DATEDIFF(NOW(), COALESCE(d.updated_at, d.created_at)) >= 14 THEN 'No update for 14+ days'
                ELSE 'Low probability stalled deal'
            END AS riskReason
        FROM deals d
        WHERE d.organization_id = :org 
          AND d.status = 'open'
          AND (
              (d.close_date IS NOT NULL AND d.close_date < CURDATE())
              OR DATEDIFF(NOW(), COALESCE(d.updated_at, d.created_at)) >= 14
          )
        ORDER BY d.value DESC
        LIMIT 10
    ");
    $stmtRisk->execute([':org' => $organizationId]);
    $atRiskDeals = $stmtRisk->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // H. Product Revenue
    // --------------------------------------------------
    $stmtProduct = $pdo->prepare("
        SELECT 
            COALESCE(NULLIF(TRIM(d.product), ''), 'Core Services') AS product,
            COALESCE(SUM(d.value), 0) AS revenue,
            COUNT(d.id) AS count
        FROM deals d
        WHERE d.organization_id = :org AND d.status = 'won'
        GROUP BY product
        ORDER BY revenue DESC
    ");
    $stmtProduct->execute([':org' => $organizationId]);
    $productRevenue = $stmtProduct->fetchAll(PDO::FETCH_ASSOC);

    if (empty($productRevenue)) {
        $productRevenue = [
            ['product' => 'General Deals', 'revenue' => $revCurr, 'count' => $dealsCurr]
        ];
    }

    // --------------------------------------------------
    // I. Team Performance Leaderboard
    // --------------------------------------------------
    $stmtTeamUsers = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            COALESCE(NULLIF(u.job_title, ''), u.role) AS role,
            COALESCE(u.quota, 50000.00) AS target
        FROM users u
        WHERE u.organization_id = :org AND u.status = 'active'
        ORDER BY u.name ASC
    ");
    $stmtTeamUsers->execute([':org' => $organizationId]);
    $activeUsers = $stmtTeamUsers->fetchAll(PDO::FETCH_ASSOC);

    $teamMembers = [];
    $repColors = ['#7C3AED', '#0284C7', '#10B981', '#F59E0B', '#EC4899', '#6366F1'];
    $uIndex = 0;

    foreach ($activeUsers as $u) {
        $uId = (int)$u['id'];

        $uDealParams = [
            ':org' => $organizationId,
            ':uid' => $uId,
            ':c_start' => $dates['current_start'],
            ':c_end' => $dates['current_end']
        ];
        $stmtUWon = $pdo->prepare("
            SELECT 
                COALESCE(SUM(d.value), 0) AS revenue,
                COUNT(d.id) AS won_cnt,
                COALESCE(AVG(d.value), 0) AS avg_deal_size
            FROM deals d
            WHERE d.organization_id = :org 
              AND d.assigned_to = :uid 
              AND d.status = 'won'
              AND COALESCE(d.close_date, DATE(d.created_at)) BETWEEN DATE(:c_start) AND DATE(:c_end)
        ");
        $stmtUWon->execute($uDealParams);
        $uWon = $stmtUWon->fetch(PDO::FETCH_ASSOC);

        $stmtUWin = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN d.status = 'won' THEN 1 ELSE 0 END) AS won_cnt,
                SUM(CASE WHEN d.status = 'lost' THEN 1 ELSE 0 END) AS lost_cnt
            FROM deals d
            WHERE d.organization_id = :org AND d.assigned_to = :uid AND d.status IN ('won', 'lost')
        ");
        $stmtUWin->execute([':org' => $organizationId, ':uid' => $uId]);
        $uWin = $stmtUWin->fetch(PDO::FETCH_ASSOC);
        $uWonAll = (int)($uWin['won_cnt'] ?? 0);
        $uLostAll = (int)($uWin['lost_cnt'] ?? 0);
        $uWinRate = ($uWonAll + $uLostAll) > 0 ? round(($uWonAll / ($uWonAll + $uLostAll)) * 100, 1) . '%' : '0.0%';

        $stmtUAct = $pdo->prepare("
            SELECT COUNT(*) 
            FROM team_activities 
            WHERE organization_id = :org AND user_id = :uid
              AND created_at BETWEEN :c_start AND :c_end
        ");
        $stmtUAct->execute([
            ':org' => $organizationId,
            ':uid' => $uId,
            ':c_start' => $dates['current_start'],
            ':c_end' => $dates['current_end']
        ]);
        $uActivities = (int)$stmtUAct->fetchColumn();

        $uRev = (float)$uWon['revenue'];
        $uTarget = (float)$u['target'];
        $completion = $uTarget > 0 ? round(($uRev / $uTarget) * 100, 1) : 0.0;

        $parts = explode(' ', trim($u['name'] ?? 'U'));
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));

        $teamMembers[] = [
            'id' => $uId,
            'name' => $u['name'],
            'role' => ucwords(str_replace('_', ' ', (string)$u['role'])),
            'initials' => $initials ?: 'U',
            'avatarBg' => $repColors[$uIndex % count($repColors)],
            'revenue' => $uRev,
            'target' => $uTarget,
            'completion' => $completion,
            'won' => (int)$uWon['won_cnt'],
            'winRate' => $uWinRate,
            'avgDealSize' => round((float)$uWon['avg_deal_size'], 0),
            'activities' => $uActivities
        ];
        $uIndex++;
    }

    usort($teamMembers, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

    // --------------------------------------------------
    // J. Activity Metrics & Real 7x24 Heatmap
    // --------------------------------------------------
    $actParams = [
        ':org' => $organizationId,
        ':c_start' => $dates['current_start'],
        ':c_end' => $dates['current_end']
    ];

    $stmtActCounts = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN LOWER(activity_type) IN ('call', 'phone_call') THEN 1 ELSE 0 END) AS calls,
            SUM(CASE WHEN LOWER(activity_type) IN ('email', 'inbox_sent', 'inbox_received') THEN 1 ELSE 0 END) AS emails,
            SUM(CASE WHEN LOWER(activity_type) LIKE '%whatsapp%' THEN 1 ELSE 0 END) AS whatsapp,
            SUM(CASE WHEN LOWER(activity_type) IN ('meeting', 'meeting_scheduled') THEN 1 ELSE 0 END) AS meetings,
            SUM(CASE WHEN LOWER(activity_type) IN ('task', 'task_added', 'task_created') THEN 1 ELSE 0 END) AS tasks,
            COUNT(id) AS total_activities
        FROM team_activities
        WHERE organization_id = :org AND created_at BETWEEN :c_start AND :c_end
    ");
    $stmtActCounts->execute($actParams);
    $actCounts = $stmtActCounts->fetch(PDO::FETCH_ASSOC);

    $stmtPrevAct = $pdo->prepare("
        SELECT COUNT(id) FROM team_activities 
        WHERE organization_id = :org AND created_at BETWEEN :p_start AND :p_end
    ");
    $stmtPrevAct->execute([
        ':org' => $organizationId,
        ':p_start' => $dates['previous_start'],
        ':p_end' => $dates['previous_end']
    ]);
    $prevActTotal = (int)$stmtPrevAct->fetchColumn();
    $actTotalChange = calc_change((float)($actCounts['total_activities'] ?? 0), (float)$prevActTotal);

    $activitySummary = [
        [
            'label' => 'Total Activities',
            'value' => number_format((int)($actCounts['total_activities'] ?? 0)),
            'change' => $actTotalChange['text']
        ],
        [
            'label' => 'Calls Logged',
            'value' => number_format((int)($actCounts['calls'] ?? 0)),
            'change' => $dates['period_label']
        ],
        [
            'label' => 'Emails Exchanged',
            'value' => number_format((int)($actCounts['emails'] ?? 0)),
            'change' => $dates['period_label']
        ],
        [
            'label' => 'Meetings Held',
            'value' => number_format((int)($actCounts['meetings'] ?? 0)),
            'change' => $dates['period_label']
        ],
        [
            'label' => 'Tasks Executed',
            'value' => number_format((int)($actCounts['tasks'] ?? 0)),
            'change' => $dates['period_label']
        ]
    ];

    $actTimeline = [];
    $todayCur = new DateTime('today');
    $todayCur->modify('-6 days');
    for ($d = 0; $d < 7; $d++) {
        $dayStr = $todayCur->format('Y-m-d');
        $label = $todayCur->format('D, M j');

        $stmtDayAct = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN LOWER(activity_type) IN ('call', 'phone_call') THEN 1 ELSE 0 END) AS calls,
                SUM(CASE WHEN LOWER(activity_type) IN ('email', 'inbox_sent') THEN 1 ELSE 0 END) AS emails,
                SUM(CASE WHEN LOWER(activity_type) LIKE '%whatsapp%' THEN 1 ELSE 0 END) AS whatsapp,
                SUM(CASE WHEN LOWER(activity_type) IN ('meeting', 'meeting_scheduled') THEN 1 ELSE 0 END) AS meetings
            FROM team_activities
            WHERE organization_id = ? AND DATE(created_at) = ?
        ");
        $stmtDayAct->execute([$organizationId, $dayStr]);
        $dayRes = $stmtDayAct->fetch(PDO::FETCH_ASSOC);

        $actTimeline[] = [
            'day' => $label,
            'calls' => (int)($dayRes['calls'] ?? 0),
            'emails' => (int)($dayRes['emails'] ?? 0),
            'whatsapp' => (int)($dayRes['whatsapp'] ?? 0),
            'meetings' => (int)($dayRes['meetings'] ?? 0)
        ];
        $todayCur->modify('+1 day');
    }

    $stmtHeatmap = $pdo->prepare("
        SELECT 
            DAYOFWEEK(created_at) - 1 AS day_idx,
            HOUR(created_at) AS hour_idx,
            COUNT(*) AS act_count
        FROM team_activities
        WHERE organization_id = ?
        GROUP BY day_idx, hour_idx
    ");
    $stmtHeatmap->execute([$organizationId]);
    $heatRows = $stmtHeatmap->fetchAll(PDO::FETCH_ASSOC);

    $heatmapMatrix = [];
    for ($d = 0; $d < 7; $d++) {
        $heatmapMatrix[$d] = array_fill(0, 24, 0);
    }
    foreach ($heatRows as $hr) {
        $dIdx = (int)$hr['day_idx'];
        $hIdx = (int)$hr['hour_idx'];
        if ($dIdx >= 0 && $dIdx < 7 && $hIdx >= 0 && $hIdx < 24) {
            $heatmapMatrix[$dIdx][$hIdx] = (int)$hr['act_count'];
        }
    }

    // --------------------------------------------------
    // K. Sales Forecast & Insights
    // --------------------------------------------------
    $stmtForecast = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN probability >= 70 THEN value * (probability / 100) ELSE 0 END), 0) AS commit_forecast,
            COALESCE(SUM(value * (COALESCE(probability, 50) / 100)), 0) AS best_case_forecast,
            COALESCE(SUM(value), 0) AS open_pipeline
        FROM deals
        WHERE organization_id = :org AND status = 'open'
    ");
    $stmtForecast->execute([':org' => $organizationId]);
    $forecastRow = $stmtForecast->fetch(PDO::FETCH_ASSOC);

    $targetAttainment = $orgTotalQuota > 0 ? round(($revCurr / $orgTotalQuota) * 100, 1) : 0.0;

    $forecastData = [
        'commit' => '$' . number_format((float)$forecastRow['commit_forecast'], 0),
        'bestCase' => '$' . number_format((float)$forecastRow['best_case_forecast'], 0),
        'openPipeline' => '$' . number_format((float)$forecastRow['open_pipeline'], 0),
        'targetAttainmentPct' => $targetAttainment,
        'targetAttainmentText' => "{$targetAttainment}% of $" . number_format($orgTotalQuota / 1000, 0) . 'K Target'
    ];

    $topRepName = !empty($teamMembers) ? $teamMembers[0]['name'] : 'Sales Team';
    $topLeadSource = !empty($leadSources) ? $leadSources[0]['source'] : 'Direct';
    $insightsList = [
        "<strong>{$topLeadSource}</strong> is currently the leading acquisition channel with <strong>" . (!empty($leadSources) ? $leadSources[0]['convRate'] : '0%') . "</strong> conversion rate.",
        "Total active open pipeline stands at <strong>$" . number_format($pipelineVal, 0) . "</strong> across <strong>{$pipelineCount}</strong> opportunities.",
        "Average sales cycle length is currently <strong>{$cycleDaysCurr} days</strong> from creation to Closed Won.",
        "Top performer <strong>{$topRepName}</strong> generated <strong>$" . number_format(!empty($teamMembers) ? $teamMembers[0]['revenue'] : 0, 0) . "</strong> in closed-won business."
    ];

    // --------------------------------------------------
    // L. Lookups (Active Users, Stages, Sources)
    // --------------------------------------------------
    $stmtLookupsUsers = $pdo->prepare("SELECT id, name FROM users WHERE organization_id = ? AND status = 'active' ORDER BY name ASC");
    $stmtLookupsUsers->execute([$organizationId]);
    $lookupUsers = $stmtLookupsUsers->fetchAll(PDO::FETCH_ASSOC);

    $stmtLookupStages = $pdo->prepare("SELECT id, name FROM pipeline_stages WHERE organization_id = ? AND is_active = 1 ORDER BY sort_order ASC");
    $stmtLookupStages->execute([$organizationId]);
    $lookupStages = $stmtLookupStages->fetchAll(PDO::FETCH_ASSOC);

    $stmtLookupSources = $pdo->prepare("SELECT DISTINCT source FROM leads WHERE organization_id = ? AND source IS NOT NULL AND TRIM(source) != '' ORDER BY source ASC");
    $stmtLookupSources->execute([$organizationId]);
    $lookupSources = $stmtLookupSources->fetchAll(PDO::FETCH_COLUMN);

    // --------------------------------------------------
    // M. Saved Presets & Custom Reports
    // --------------------------------------------------
    $stmtSavedPresets = $pdo->prepare("SELECT id, name AS title, category, tab, filter_config, DATE_FORMAT(created_at, '%b %d, %Y') AS updated FROM report_saved_presets WHERE organization_id = ? ORDER BY id DESC");
    $stmtSavedPresets->execute([$organizationId]);
    $savedPresets = $stmtSavedPresets->fetchAll(PDO::FETCH_ASSOC);

    if (empty($savedPresets)) {
        $savedPresets = [
            ['id' => 'exec-summary', 'title' => 'Executive Performance Summary', 'category' => 'Executive', 'tab' => 'overview', 'updated' => date('M d, Y')],
            ['id' => 'pipeline-health', 'title' => 'Pipeline Health & Velocity', 'category' => 'Pipeline', 'tab' => 'pipeline', 'updated' => date('M d, Y')],
            ['id' => 'rep-benchmark', 'title' => 'Sales Representative Benchmark', 'category' => 'Team', 'tab' => 'team', 'updated' => date('M d, Y')],
            ['id' => 'lead-attribution', 'title' => 'Lead Attribution & ROI', 'category' => 'Leads', 'tab' => 'conversion', 'updated' => date('M d, Y')]
        ];
    }

    $stmtCustomReports = $pdo->prepare("SELECT id, name, category, data_source, metrics, group_by, chart_type, DATE_FORMAT(created_at, '%b %d, %Y') AS date FROM custom_reports WHERE organization_id = ? ORDER BY id DESC");
    $stmtCustomReports->execute([$organizationId]);
    $customReportsList = $stmtCustomReports->fetchAll(PDO::FETCH_ASSOC);

    report_json(true, 'Reports bootstrap data loaded.', [
        'organization_id' => $organizationId,
        'date_boundaries' => $dates,
        'kpis' => $kpis,
        'monthlyTrend' => $monthlyTrend,
        'pipelineStages' => $pipelineStages,
        'leadSources' => $leadSources,
        'funnel' => $funnelItems,
        'topDeals' => $topDeals,
        'atRiskDeals' => $atRiskDeals,
        'productRevenue' => $productRevenue,
        'teamMembers' => $teamMembers,
        'activityStats' => [
            'summary' => $activitySummary,
            'timeline' => $actTimeline,
            'heatmap' => $heatmapMatrix
        ],
        'forecast' => $forecastData,
        'insights' => $insightsList,
        'lookups' => [
            'users' => $lookupUsers,
            'stages' => $lookupStages,
            'sources' => $lookupSources
        ],
        'savedPresets' => $savedPresets,
        'customReports' => $customReportsList
    ]);
}

// ==========================================
// 2. ACTION: SAVE CUSTOM REPORT (POST)
// ==========================================
if ($action === 'save_custom_report') {
    if ($method !== 'POST') {
        report_json(false, 'POST request method required.', [], 405);
    }

    $name = trim($input['name'] ?? '');
    $category = trim($input['category'] ?? 'Sales');
    $dataSource = trim($input['data_source'] ?? 'Deals');
    $metrics = isset($input['metrics']) ? json_encode($input['metrics']) : json_encode(['Revenue']);
    $groupBy = trim($input['group_by'] ?? 'Month');
    $chartType = trim($input['chart_type'] ?? 'line');
    $config = isset($input['config']) ? json_encode($input['config']) : null;

    if (empty($name)) {
        report_json(false, 'Report Name is required.', [], 400);
    }

    try {
        $stmtInsert = $pdo->prepare("
            INSERT INTO custom_reports 
            (organization_id, created_by, name, category, data_source, metrics, group_by, chart_type, config, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtInsert->execute([
            $organizationId,
            $currentUserId,
            $name,
            $category,
            $dataSource,
            $metrics,
            $groupBy,
            $chartType,
            $config
        ]);
        $newId = (int)$pdo->lastInsertId();

        report_json(true, "Custom report '{$name}' saved successfully.", [
            'id' => $newId,
            'name' => $name,
            'category' => $category,
            'date' => date('M d, Y')
        ]);
    } catch (Throwable $e) {
        report_json(false, 'Failed to save custom report: ' . $e->getMessage(), [], 500);
    }
}

// ==========================================
// 3. ACTION: SAVE PRESET (POST)
// ==========================================
if ($action === 'save_preset') {
    if ($method !== 'POST') {
        report_json(false, 'POST request method required.', [], 405);
    }

    $name = trim($input['name'] ?? '');
    $category = trim($input['category'] ?? 'Custom');
    $tab = trim($input['tab'] ?? 'overview');
    $filterConfig = isset($input['filter_config']) ? json_encode($input['filter_config']) : json_encode([]);

    if (empty($name)) {
        report_json(false, 'Preset Name is required.', [], 400);
    }

    try {
        $stmtPreset = $pdo->prepare("
            INSERT INTO report_saved_presets 
            (organization_id, user_id, name, category, tab, filter_config, is_system, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmtPreset->execute([
            $organizationId,
            $currentUserId,
            $name,
            $category,
            $tab,
            $filterConfig
        ]);
        $newId = (int)$pdo->lastInsertId();

        report_json(true, "Preset '{$name}' saved.", [
            'id' => $newId,
            'title' => $name,
            'category' => $category,
            'tab' => $tab,
            'updated' => date('M d, Y')
        ]);
    } catch (Throwable $e) {
        report_json(false, 'Failed to save preset: ' . $e->getMessage(), [], 500);
    }
}

// ==========================================
// 4. ACTION: EXPORT CSV (GET)
// ==========================================
if ($action === 'export_csv') {
    $tab = $_GET['tab'] ?? 'overview';
    $filename = "NexFlow_Report_{$tab}_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    $exportParams = [
        ':org' => $organizationId,
        ':c_start' => $dates['current_start'],
        ':c_end' => $dates['current_end']
    ];
    $dealFilterSql = build_deal_filters($teamRep, $pipelineFilter, $leadSourceFilter, $exportParams);

    if ($tab === 'sales' || $tab === 'overview') {
        fputcsv($out, ['Deal Name', 'Company', 'Owner', 'Stage', 'Value ($)', 'Status', 'Close Date', 'Created At']);
        $stmtExp = $pdo->prepare("
            SELECT 
                d.name,
                COALESCE(NULLIF(d.company, ''), 'Direct Client') AS company,
                COALESCE(u.name, 'Unassigned') AS owner,
                d.stage,
                d.value,
                d.status,
                COALESCE(d.close_date, '') AS close_date,
                d.created_at
            FROM deals d
            LEFT JOIN users u ON u.id = d.assigned_to
            WHERE d.organization_id = :org
              AND COALESCE(d.close_date, DATE(d.created_at)) BETWEEN DATE(:c_start) AND DATE(:c_end)
              {$dealFilterSql}
            ORDER BY d.value DESC
        ");
        $stmtExp->execute($exportParams);
        while ($row = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['name'],
                $row['company'],
                $row['owner'],
                $row['stage'],
                number_format((float)$row['value'], 2, '.', ''),
                $row['status'],
                $row['close_date'],
                $row['created_at']
            ]);
        }
    } elseif ($tab === 'pipeline') {
        fputcsv($out, ['Stage Name', 'Sort Order', 'Open Deals Count', 'Total Stage Value ($)']);
        $stmtExpPipe = $pdo->prepare("
            SELECT 
                ps.name,
                ps.sort_order,
                COUNT(d.id) AS deal_count,
                COALESCE(SUM(d.value), 0) AS total_val
            FROM pipeline_stages ps
            LEFT JOIN deals d ON (d.stage = ps.name) AND d.organization_id = ps.organization_id AND d.status = 'open'
            WHERE ps.organization_id = ? AND ps.is_active = 1
            GROUP BY ps.id, ps.name, ps.sort_order
            ORDER BY ps.sort_order ASC
        ");
        $stmtExpPipe->execute([$organizationId]);
        while ($row = $stmtExpPipe->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['name'],
                $row['sort_order'],
                $row['deal_count'],
                number_format((float)$row['total_val'], 2, '.', '')
            ]);
        }
    } elseif ($tab === 'conversion') {
        fputcsv($out, ['Lead Name', 'Company', 'Source', 'Status', 'Estimated Value ($)', 'Created At']);
        $stmtExpLeads = $pdo->prepare("
            SELECT name, company, source, status, value, created_at 
            FROM leads 
            WHERE organization_id = ? AND created_at BETWEEN ? AND ?
            ORDER BY id DESC
        ");
        $stmtExpLeads->execute([$organizationId, $dates['current_start'], $dates['current_end']]);
        while ($row = $stmtExpLeads->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['name'],
                $row['company'],
                $row['source'] ?: 'Unspecified',
                $row['status'],
                number_format((float)$row['value'], 2, '.', ''),
                $row['created_at']
            ]);
        }
    } elseif ($tab === 'team') {
        fputcsv($out, ['Representative', 'Role', 'Quota Target ($)', 'Won Deals Count', 'Revenue Won ($)', 'Completion (%)']);
        $stmtExpTeam = $pdo->prepare("
            SELECT 
                u.name,
                COALESCE(u.job_title, u.role) AS role,
                COALESCE(u.quota, 50000.00) AS quota,
                COUNT(d.id) AS won_cnt,
                COALESCE(SUM(d.value), 0) AS won_rev
            FROM users u
            LEFT JOIN deals d ON d.assigned_to = u.id AND d.organization_id = u.organization_id AND d.status = 'won'
            WHERE u.organization_id = ? AND u.status = 'active'
            GROUP BY u.id, u.name, u.role, u.job_title, u.quota
            ORDER BY won_rev DESC
        ");
        $stmtExpTeam->execute([$organizationId]);
        while ($row = $stmtExpTeam->fetch(PDO::FETCH_ASSOC)) {
            $quota = (float)$row['quota'];
            $rev = (float)$row['won_rev'];
            $pct = $quota > 0 ? round(($rev / $quota) * 100, 1) : 0.0;
            fputcsv($out, [
                $row['name'],
                $row['role'],
                number_format($quota, 2, '.', ''),
                $row['won_cnt'],
                number_format($rev, 2, '.', ''),
                $pct . '%'
            ]);
        }
    } elseif ($tab === 'activity') {
        fputcsv($out, ['Activity Title', 'Activity Type', 'User ID', 'Related Entity', 'Created At']);
        $stmtExpAct = $pdo->prepare("
            SELECT title, activity_type, user_id, related_entity, created_at 
            FROM team_activities 
            WHERE organization_id = ? AND created_at BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        $stmtExpAct->execute([$organizationId, $dates['current_start'], $dates['current_end']]);
        while ($row = $stmtExpAct->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['title'],
                $row['activity_type'],
                $row['user_id'],
                $row['related_entity'],
                $row['created_at']
            ]);
        }
    }

    fclose($out);
    exit;
}

// Default fallback
report_json(false, "Unknown action: '{$action}'", [], 400);
