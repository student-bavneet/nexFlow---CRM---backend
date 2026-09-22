<?php
/**
 * NexFlow CRM - Centralized Dashboard Data Engine
 * Computes database-driven, organization-scoped metrics for KPIs, Recent Leads,
 * Pipeline Widget, Activity Feed, and Sales Analytics.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

/**
 * Resolve organization currency and symbol from organizations table
 */
function get_dashboard_currency(PDO $pdo, int $orgId): array {
    $stmt = $pdo->prepare("SELECT currency FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $curr = $stmt->fetchColumn();
    $currStr = !empty($curr) ? trim($curr) : 'USD ($)';
    $symbol = '$';

    if (preg_match('/\((.*?)\)/', $currStr, $m)) {
        $symbol = $m[1];
    } elseif (in_array(strtoupper($currStr), ['USD', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD'])) {
        $symbol = '$';
    } elseif (strtoupper($currStr) === 'EUR') {
        $symbol = '€';
    } elseif (strtoupper($currStr) === 'GBP') {
        $symbol = '£';
    } elseif (strtoupper($currStr) === 'INR') {
        $symbol = '₹';
    } elseif (strtoupper($currStr) === 'JPY') {
        $symbol = '¥';
    }

    return [
        'code'   => $currStr,
        'symbol' => $symbol
    ];
}

/**
 * Safe percentage change calculation without NaN or division by zero
 */
function calc_dashboard_pct_change(float $curr, float $prev): array {
    if ($prev <= 0.0) {
        if ($curr <= 0.0) {
            return ['text' => '0%', 'dir' => 'up'];
        }
        return ['text' => '+100%', 'dir' => 'up'];
    }
    $diff = (($curr - $prev) / $prev) * 100.0;
    $dir = $diff >= 0 ? 'up' : 'down';
    $text = ($diff >= 0 ? '+' : '') . round($diff, 1) . '%';
    return ['text' => $text, 'dir' => $dir];
}

/**
 * Deterministic avatar color picker
 */
function get_dashboard_avatar_color(int $id): string {
    $colors = ['#7C3AED', '#0284C7', '#059669', '#F59E0B', '#6366F1', '#EC4899', '#14B8A6', '#8B5CF6'];
    return $colors[$id % count($colors)];
}

/**
 * Initials from full name
 */
function get_dashboard_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, min(2, strlen($name))));
}

/**
 * Human-readable relative time string
 */
function format_dashboard_time(string $datetime): string {
    $time = strtotime($datetime);
    if (!$time) return '';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

/**
 * 1. KPI Cards (New Leads, Pipeline Value, Win Rate, Average Deal Size)
 */
function get_dashboard_kpis(PDO $pdo, int $orgId, array $currency): array {
    $symbol = $currency['symbol'];

    $currStart = (new DateTime('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
    $currEnd   = (new DateTime('first day of next month 00:00:00'))->format('Y-m-d H:i:s');
    $prevStart = (new DateTime('first day of last month 00:00:00'))->format('Y-m-d H:i:s');

    // A. Leads KPI: current month active vs previous month active
    $stmtLeads = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN created_at >= ? AND created_at < ? THEN 1 END) AS curr_cnt,
            COUNT(CASE WHEN created_at >= ? AND created_at < ? THEN 1 END) AS prev_cnt,
            COUNT(*) AS total_active
        FROM leads 
        WHERE organization_id = ? AND is_archived = 0
    ");
    $stmtLeads->execute([$currStart, $currEnd, $prevStart, $currStart, $orgId]);
    $leadData = $stmtLeads->fetch(PDO::FETCH_ASSOC);

    $currLeads = (int)($leadData['curr_cnt'] ?? 0);
    $prevLeads = (int)($leadData['prev_cnt'] ?? 0);
    $leadsChange = calc_dashboard_pct_change((float)$currLeads, (float)$prevLeads);

    // B. Deals KPI: Total Pipeline Value & Average Deal Size
    $stmtDeals = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status != 'lost' THEN value ELSE 0 END), 0) AS total_pipeline_val,
            COUNT(CASE WHEN status != 'lost' THEN 1 END) AS total_deals_cnt,
            COALESCE(SUM(CASE WHEN status != 'lost' AND created_at >= ? AND created_at < ? THEN value ELSE 0 END), 0) AS curr_pipeline_val,
            COALESCE(SUM(CASE WHEN status != 'lost' AND created_at >= ? AND created_at < ? THEN value ELSE 0 END), 0) AS prev_pipeline_val,
            COUNT(CASE WHEN status != 'lost' AND created_at >= ? AND created_at < ? THEN 1 END) AS curr_deals_cnt,
            COUNT(CASE WHEN status != 'lost' AND created_at >= ? AND created_at < ? THEN 1 END) AS prev_deals_cnt
        FROM deals 
        WHERE organization_id = ?
    ");
    $stmtDeals->execute([$currStart, $currEnd, $prevStart, $currStart, $currStart, $currEnd, $prevStart, $currStart, $orgId]);
    $dealsData = $stmtDeals->fetch(PDO::FETCH_ASSOC);

    $totalPipelineVal = (float)($dealsData['total_pipeline_val'] ?? 0);
    $totalDealsCnt    = (int)($dealsData['total_deals_cnt'] ?? 0);
    $currPipelineVal  = (float)($dealsData['curr_pipeline_val'] ?? 0);
    $prevPipelineVal  = (float)($dealsData['prev_pipeline_val'] ?? 0);
    $pipelineChange   = calc_dashboard_pct_change($currPipelineVal, $prevPipelineVal);

    $avgDealSize   = $totalDealsCnt > 0 ? ($totalPipelineVal / $totalDealsCnt) : 0.0;
    $currAvgDeal   = (int)$dealsData['curr_deals_cnt'] > 0 ? ($currPipelineVal / (int)$dealsData['curr_deals_cnt']) : 0.0;
    $prevAvgDeal   = (int)$dealsData['prev_deals_cnt'] > 0 ? ($prevPipelineVal / (int)$dealsData['prev_deals_cnt']) : 0.0;
    $avgDealChange = calc_dashboard_pct_change($currAvgDeal, $prevAvgDeal);

    // C. Win Rate: Won Deals / (Won Deals + Lost Deals) * 100
    $stmtWin = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'won' THEN 1 END) AS won_cnt,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) AS lost_cnt,
            COUNT(CASE WHEN status = 'won' AND close_date >= ? AND close_date < ? THEN 1 END) AS curr_won,
            COUNT(CASE WHEN status = 'lost' AND close_date >= ? AND close_date < ? THEN 1 END) AS curr_lost,
            COUNT(CASE WHEN status = 'won' AND close_date >= ? AND close_date < ? THEN 1 END) AS prev_won,
            COUNT(CASE WHEN status = 'lost' AND close_date >= ? AND close_date < ? THEN 1 END) AS prev_lost
        FROM deals 
        WHERE organization_id = ?
    ");
    $stmtWin->execute([$currStart, $currEnd, $currStart, $currEnd, $prevStart, $currStart, $prevStart, $currStart, $orgId]);
    $winData = $stmtWin->fetch(PDO::FETCH_ASSOC);

    $totalWon    = (int)($winData['won_cnt'] ?? 0);
    $totalLost   = (int)($winData['lost_cnt'] ?? 0);
    $closedTotal = $totalWon + $totalLost;
    $winRate     = $closedTotal > 0 ? round(($totalWon / $closedTotal) * 100, 1) : 0.0;

    $currClosed  = (int)$winData['curr_won'] + (int)$winData['curr_lost'];
    $currWinRate = $currClosed > 0 ? round(((int)$winData['curr_won'] / $currClosed) * 100, 1) : 0.0;
    $prevClosed  = (int)$winData['prev_won'] + (int)$winData['prev_lost'];
    $prevWinRate = $prevClosed > 0 ? round(((int)$winData['prev_won'] / $prevClosed) * 100, 1) : 0.0;
    $winRateChange = calc_dashboard_pct_change($currWinRate, $prevWinRate);

    // Format Pipeline Value display
    if ($totalPipelineVal >= 1000000) {
        $pipelineValDisplay = $symbol . round($totalPipelineVal / 1000000, 1) . 'M';
    } elseif ($totalPipelineVal >= 1000) {
        $pipelineValDisplay = $symbol . round($totalPipelineVal / 1000, 1) . 'K';
    } else {
        $pipelineValDisplay = $symbol . number_format($totalPipelineVal);
    }

    // Format Avg Deal Size display
    if ($avgDealSize >= 1000000) {
        $avgDealDisplay = $symbol . round($avgDealSize / 1000000, 1) . 'M';
    } elseif ($avgDealSize >= 1000) {
        $avgDealDisplay = $symbol . round($avgDealSize / 1000, 1) . 'K';
    } else {
        $avgDealDisplay = $symbol . number_format($avgDealSize);
    }

    return [
        [
            'id'        => 'new_leads',
            'label'     => 'New Leads',
            'value'     => (string)$currLeads,
            'change'    => $leadsChange['text'],
            'changeDir' => $leadsChange['dir'],
            'icon'      => 'users',
            'color'     => '#2563EB',
            'target_url'=> 'leads.php'
        ],
        [
            'id'        => 'pipeline_value',
            'label'     => 'Pipeline Value',
            'value'     => $pipelineValDisplay,
            'change'    => $pipelineChange['text'],
            'changeDir' => $pipelineChange['dir'],
            'icon'      => 'dollar-sign',
            'color'     => '#7C3AED',
            'target_url'=> 'pipeline.php'
        ],
        [
            'id'        => 'win_rate',
            'label'     => 'Win Rate',
            'value'     => $winRate . '%',
            'change'    => $winRateChange['text'],
            'changeDir' => $winRateChange['dir'],
            'icon'      => 'target',
            'color'     => '#059669',
            'target_url'=> 'pipeline.php'
        ],
        [
            'id'        => 'avg_deal_size',
            'label'     => 'Average Deal Size',
            'value'     => $avgDealDisplay,
            'change'    => $avgDealChange['text'],
            'changeDir' => $avgDealChange['dir'],
            'icon'      => 'trending-up',
            'color'     => '#F79009',
            'target_url'=> 'pipeline.php'
        ]
    ];
}

/**
 * 2. Recent Leads Table (Up to 6 newest active leads)
 */
function get_dashboard_recent_leads(PDO $pdo, int $orgId, array $currency, int $limit = 6): array {
    $symbol = $currency['symbol'];
    $stmt = $pdo->prepare("
        SELECT l.id, l.name, l.first_name, l.last_name, l.email, l.company, l.status, l.value, l.created_at, l.assigned_to,
               u.name AS assignee_name, u.photo_path
        FROM leads l
        LEFT JOIN users u ON u.id = l.assigned_to AND u.organization_id = l.organization_id
        WHERE l.organization_id = ? AND l.is_archived = 0
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT " . (int)$limit . "
    ");
    $stmt->execute([$orgId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leads = [];
    foreach ($rows as $r) {
        $name = trim($r['name'] ?: ($r['first_name'] . ' ' . $r['last_name']));
        if (empty($name)) $name = 'Unnamed Lead';
        $assigneeName = trim($r['assignee_name'] ?: 'Unassigned');
        $initials = get_dashboard_initials($assigneeName !== 'Unassigned' ? $assigneeName : $name);
        $color = get_dashboard_avatar_color((int)($r['assigned_to'] ?: $r['id']));
        $val = (float)($r['value'] ?? 0);

        $leads[] = [
            'id'               => (int)$r['id'],
            'name'             => $name,
            'email'            => $r['email'] ?: '',
            'company'          => $r['company'] ?: '—',
            'status'           => $r['status'] ?: 'New',
            'status_slug'      => strtolower(trim((string)$r['status'] ?: 'new')),
            'value'            => $val,
            'value_formatted'  => $symbol . number_format($val),
            'assignee'         => $assigneeName,
            'assigneeInitials' => $initials,
            'assigneeColor'    => $color,
            'createdAt'        => date('M j, Y', strtotime($r['created_at']))
        ];
    }
    return $leads;
}

/**
 * 3. Pipeline Widget (Stages from pipeline_stages, real deal counts & values)
 */
function get_dashboard_pipeline(PDO $pdo, int $orgId, array $currency): array {
    $symbol = $currency['symbol'];

    // Real active stages
    $stgStmt = $pdo->prepare("
        SELECT id, name, color, probability, sort_order
        FROM pipeline_stages
        WHERE organization_id = ? AND is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $stgStmt->execute([$orgId]);
    $stagesRows = $stgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Deals by stage
    $dealsStmt = $pdo->prepare("
        SELECT stage, COUNT(*) as deal_count, COALESCE(SUM(value), 0) as total_val
        FROM deals
        WHERE organization_id = ? AND status != 'lost'
        GROUP BY stage
    ");
    $dealsStmt->execute([$orgId]);
    $dealRows = $dealsStmt->fetchAll(PDO::FETCH_ASSOC);

    $stageDealMap = [];
    $totalPipelineVal = 0.0;
    foreach ($dealRows as $dr) {
        $stgKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$dr['stage']), '_'));
        $val = (float)$dr['total_val'];
        $cnt = (int)$dr['deal_count'];
        $stageDealMap[$stgKey] = ['count' => $cnt, 'value' => $val];
        $stageDealMap[strtolower(trim((string)$dr['stage']))] = ['count' => $cnt, 'value' => $val];
        $totalPipelineVal += $val;
    }

    $stages = [];
    foreach ($stagesRows as $s) {
        $sKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $s['name']), '_'));
        $match = $stageDealMap[$sKey] ?? ($stageDealMap[strtolower(trim($s['name']))] ?? ['count' => 0, 'value' => 0.0]);
        $val = $match['value'];
        $cnt = $match['count'];
        $pct = $totalPipelineVal > 0 ? round(($val / $totalPipelineVal) * 100) : 0;

        $stages[] = [
            'id'             => (int)$s['id'],
            'name'           => $s['name'],
            'count'          => $cnt,
            'value'          => $val,
            'value_formatted'=> $symbol . ($val >= 1000 ? round($val / 1000) . 'K' : number_format($val)),
            'color'          => $s['color'] ?: '#2563EB',
            'pct'            => $pct
        ];
    }

    return [
        'stages'                => $stages,
        'total_value'           => $totalPipelineVal,
        'total_value_formatted' => $symbol . number_format($totalPipelineVal)
    ];
}

/**
 * 4. Activity Feed (Up to 6 newest activities from team_activities)
 */
function get_dashboard_activities(PDO $pdo, int $orgId, int $limit = 6): array {
    $stmt = $pdo->prepare("
        SELECT a.id, a.user_id, a.activity_type, a.title, a.description, a.related_entity, a.related_entity_id, a.created_at,
               u.name AS user_name
        FROM team_activities a
        LEFT JOIN users u ON u.id = a.user_id AND u.organization_id = a.organization_id
        WHERE a.organization_id = ?
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT " . (int)$limit . "
    ");
    $stmt->execute([$orgId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activities = [];
    foreach ($rows as $r) {
        $actType = strtolower(trim((string)$r['activity_type']));
        $title   = trim((string)$r['title']);
        $desc    = trim((string)$r['description']);
        $entity  = strtolower(trim((string)$r['related_entity']));

        $icon   = 'bell';
        $color  = '#64748B';
        $action = 'Activity recorded';
        $subject = $title ?: $desc;

        if (strpos($actType, 'lead') !== false || $entity === 'leads') {
            $icon   = 'user-plus';
            $color  = '#2563EB';
            $action = 'Lead activity:';
        } elseif (strpos($actType, 'deal') !== false || strpos($actType, 'stage') !== false || $entity === 'deals') {
            $icon   = 'git-branch';
            $color  = '#F79009';
            $action = 'Deal updated:';
        } elseif (strpos($actType, 'task') !== false || $entity === 'tasks') {
            $icon   = 'check';
            $color  = '#12B76A';
            $action = 'Task completed:';
        } elseif (strpos($actType, 'email') !== false || strpos($actType, 'inbox') !== false) {
            $icon   = 'message-square';
            $color  = '#0284C7';
            $action = 'Email:';
        } elseif (strpos($actType, 'note') !== false) {
            $icon   = 'message-square';
            $color  = '#7C3AED';
            $action = 'Note:';
        }

        // Custom action keyword parsing if present
        if (preg_match('/^(Composed conversation|Replied to conversation|New lead submitted|Task completed|Deal moved to|Deal closed|Note added to|Email sent to)[:\s]+(.*)$/i', $title, $m)) {
            $action  = trim($m[1]);
            $subject = trim($m[2]);
            if (stripos($action, 'won') !== false || stripos($action, 'closed') !== false) {
                $icon = 'star';
                $color = '#12B76A';
            }
        }

        $activities[] = [
            'id'      => (int)$r['id'],
            'action'  => $action,
            'subject' => $subject,
            'time'    => format_dashboard_time($r['created_at']),
            'icon'    => $icon,
            'color'   => $color
        ];
    }
    return $activities;
}

/**
 * 5. Sales Analytics Trends (Monthly & Quarterly deal aggregations)
 */
function get_dashboard_analytics(PDO $pdo, int $orgId, array $currency): array {
    $nowYear = (int)date('Y');

    // Display 8 to 12 months up to current month
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'];
    $maxM = max(8, (int)date('n'));
    if ($maxM > 8) {
        $allMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthNames = array_slice($allMonths, 0, $maxM);
    }

    $stmt = $pdo->prepare("
        SELECT 
            MONTH(COALESCE(close_date, DATE(created_at))) AS m_num,
            COALESCE(SUM(CASE WHEN status != 'lost' THEN value ELSE 0 END), 0) / 1000 AS pipeline_k,
            COALESCE(SUM(CASE WHEN status = 'won' THEN value ELSE 0 END), 0) / 1000 AS won_k
        FROM deals
        WHERE organization_id = ? AND YEAR(COALESCE(close_date, DATE(created_at))) = ?
        GROUP BY m_num
    ");
    $stmt->execute([$orgId, $nowYear]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $monthData = [];
    foreach ($rows as $r) {
        $monthData[(int)$r['m_num']] = [
            'pipeline' => round((float)$r['pipeline_k'], 1),
            'won'      => round((float)$r['won_k'], 1)
        ];
    }

    $monthlyPipeline = [];
    $monthlyWon = [];
    foreach ($monthNames as $idx => $mName) {
        $mNum = $idx + 1;
        $monthlyPipeline[] = $monthData[$mNum]['pipeline'] ?? 0.0;
        $monthlyWon[] = $monthData[$mNum]['won'] ?? 0.0;
    }

    // Quarterly calculation from quarterly quarters Q1..Q4
    $qLabels = ['Q1', 'Q2', 'Q3', 'Q4'];
    $quarterlyPipeline = [0.0, 0.0, 0.0, 0.0];
    $quarterlyWon = [0.0, 0.0, 0.0, 0.0];

    for ($i = 0; $i < count($monthNames); $i++) {
        $qIdx = (int)floor($i / 3);
        if ($qIdx < 4) {
            $quarterlyPipeline[$qIdx] += $monthlyPipeline[$i];
            $quarterlyWon[$qIdx] += $monthlyWon[$i];
        }
    }

    return [
        'monthly' => [
            'labels'   => $monthNames,
            'pipeline' => $monthlyPipeline,
            'won'      => $monthlyWon
        ],
        'quarterly' => [
            'labels'   => $qLabels,
            'pipeline' => array_map(function($v) { return round($v, 1); }, $quarterlyPipeline),
            'won'      => array_map(function($v) { return round($v, 1); }, $quarterlyWon)
        ]
    ];
}

/**
 * 6. Master Bootstrap Data Bundle
 */
function get_dashboard_bootstrap_data(PDO $pdo, int $orgId): array {
    $currency   = get_dashboard_currency($pdo, $orgId);
    $kpis       = get_dashboard_kpis($pdo, $orgId, $currency);
    $leads      = get_dashboard_recent_leads($pdo, $orgId, $currency, 6);
    $pipeline   = get_dashboard_pipeline($pdo, $orgId, $currency);
    $activities = get_dashboard_activities($pdo, $orgId, 6);
    $analytics  = get_dashboard_analytics($pdo, $orgId, $currency);

    return [
        'success'         => true,
        'currency'        => $currency,
        'currency_symbol' => $currency['symbol'],
        'kpis'            => $kpis,
        'recent_leads'    => $leads,
        'pipeline'        => $pipeline,
        'activities'      => $activities,
        'analytics'       => $analytics
    ];
}
