<?php
/**
 * NexFlow CRM - Reports & Analytics Centralized Mock Data
 */

$reports_kpi_cards = [
    [
        'id' => 'total_revenue',
        'label' => 'Total Revenue',
        'value' => '$486,200',
        'rawValue' => 486200,
        'change' => '+12.4%',
        'changeDir' => 'up',
        'periodText' => 'vs previous period',
        'icon' => 'dollar-sign',
        'color' => '#10B981',
        'tooltip' => 'Total closed-won revenue within the selected time period.'
    ],
    [
        'id' => 'pipeline_value',
        'label' => 'Pipeline Value',
        'value' => '$1.24M',
        'rawValue' => 1240000,
        'change' => '+8.6%',
        'changeDir' => 'up',
        'periodText' => 'vs previous period',
        'icon' => 'trending-up',
        'color' => '#0284C7',
        'tooltip' => 'Total value of all active open opportunities in the sales pipeline.'
    ],
    [
        'id' => 'deals_won',
        'label' => 'Deals Won',
        'value' => '48',
        'rawValue' => 48,
        'change' => '+9 deals',
        'changeDir' => 'up',
        'periodText' => 'vs previous period',
        'icon' => 'award',
        'color' => '#7C3AED',
        'tooltip' => 'Number of deals successfully moved to Closed Won status.'
    ],
    [
        'id' => 'win_rate',
        'label' => 'Win Rate',
        'value' => '34.2%',
        'rawValue' => 34.2,
        'change' => '+2.8%',
        'changeDir' => 'up',
        'periodText' => 'percentage points',
        'icon' => 'pie-chart',
        'color' => '#F59E0B',
        'tooltip' => 'Percentage of closed deals that were won (Won / Total Closed).'
    ],
    [
        'id' => 'avg_deal_size',
        'label' => 'Average Deal Size',
        'value' => '$31,400',
        'rawValue' => 31400,
        'change' => '+5.7%',
        'changeDir' => 'up',
        'periodText' => 'vs previous period',
        'icon' => 'briefcase',
        'color' => '#EC4899',
        'tooltip' => 'Total won revenue divided by the total number of won deals.'
    ],
    [
        'id' => 'avg_sales_cycle',
        'label' => 'Average Sales Cycle',
        'value' => '27 days',
        'rawValue' => 27,
        'change' => '-3 days',
        'changeDir' => 'up', // 'up' in performance context means faster cycle
        'periodText' => 'faster than before',
        'icon' => 'clock',
        'color' => '#6366F1',
        'tooltip' => 'Average duration in days from lead creation to deal closure.'
    ]
];

$reports_monthly_trend = [
    ['month' => 'Jan', 'revenue' => 32000, 'target' => 35000, 'prevRevenue' => 28000, 'wonDeals' => 3, 'lostDeals' => 5, 'avgDealSize' => 28500, 'cycleDays' => 32],
    ['month' => 'Feb', 'revenue' => 38500, 'target' => 35000, 'prevRevenue' => 31000, 'wonDeals' => 4, 'lostDeals' => 4, 'avgDealSize' => 29000, 'cycleDays' => 30],
    ['month' => 'Mar', 'revenue' => 42000, 'target' => 40000, 'prevRevenue' => 34000, 'wonDeals' => 5, 'lostDeals' => 6, 'avgDealSize' => 30200, 'cycleDays' => 29],
    ['month' => 'Apr', 'revenue' => 39000, 'target' => 40000, 'prevRevenue' => 36000, 'wonDeals' => 4, 'lostDeals' => 5, 'avgDealSize' => 29800, 'cycleDays' => 28],
    ['month' => 'May', 'revenue' => 45500, 'target' => 42000, 'prevRevenue' => 38000, 'wonDeals' => 5, 'lostDeals' => 4, 'avgDealSize' => 31000, 'cycleDays' => 28],
    ['month' => 'Jun', 'revenue' => 51000, 'target' => 45000, 'prevRevenue' => 41000, 'wonDeals' => 6, 'lostDeals' => 5, 'avgDealSize' => 32400, 'cycleDays' => 27],
    ['month' => 'Jul', 'revenue' => 48000, 'target' => 45000, 'prevRevenue' => 42500, 'wonDeals' => 5, 'lostDeals' => 3, 'avgDealSize' => 31500, 'cycleDays' => 27],
    ['month' => 'Aug', 'revenue' => 54200, 'target' => 48000, 'prevRevenue' => 44000, 'wonDeals' => 6, 'lostDeals' => 4, 'avgDealSize' => 33000, 'cycleDays' => 26],
    ['month' => 'Sep', 'revenue' => 49000, 'target' => 48000, 'prevRevenue' => 43000, 'wonDeals' => 5, 'lostDeals' => 4, 'avgDealSize' => 31800, 'cycleDays' => 27],
    ['month' => 'Oct', 'revenue' => 58000, 'target' => 50000, 'prevRevenue' => 46000, 'wonDeals' => 7, 'lostDeals' => 3, 'avgDealSize' => 33500, 'cycleDays' => 25],
    ['month' => 'Nov', 'revenue' => 52000, 'target' => 50000, 'prevRevenue' => 48000, 'wonDeals' => 6, 'lostDeals' => 4, 'avgDealSize' => 32000, 'cycleDays' => 26],
    ['month' => 'Dec', 'revenue' => 64000, 'target' => 55000, 'prevRevenue' => 50500, 'wonDeals' => 8, 'lostDeals' => 3, 'avgDealSize' => 34500, 'cycleDays' => 24],
];

$reports_pipeline_stages = [
    ['stage' => 'Prospect', 'count' => 42, 'value' => 320000, 'convRate' => '64%', 'avgDays' => 5, 'color' => '#64748B'],
    ['stage' => 'Qualified', 'count' => 28, 'value' => 290000, 'convRate' => '52%', 'avgDays' => 8, 'color' => '#0284C7'],
    ['stage' => 'Proposal', 'count' => 18, 'value' => 240000, 'convRate' => '48%', 'avgDays' => 12, 'color' => '#7C3AED'],
    ['stage' => 'Negotiation', 'count' => 12, 'value' => 210000, 'convRate' => '72%', 'avgDays' => 9, 'color' => '#F59E0B'],
    ['stage' => 'Closed Won', 'count' => 48, 'value' => 486200, 'convRate' => '100%', 'avgDays' => 27, 'color' => '#10B981'],
];

$reports_lead_sources = [
    ['source' => 'Website', 'leads' => 840, 'qualified' => 310, 'opps' => 112, 'won' => 46, 'convRate' => '5.47%', 'revenue' => 148000, 'color' => '#7C3AED'],
    ['source' => 'Referral', 'leads' => 420, 'qualified' => 230, 'opps' => 98, 'won' => 48, 'convRate' => '11.42%', 'revenue' => 162000, 'color' => '#10B981'],
    ['source' => 'LinkedIn', 'leads' => 510, 'qualified' => 165, 'opps' => 54, 'won' => 24, 'convRate' => '4.70%', 'revenue' => 78500, 'color' => '#0284C7'],
    ['source' => 'Email Campaign', 'leads' => 390, 'qualified' => 98, 'opps' => 36, 'won' => 15, 'convRate' => '3.84%', 'revenue' => 48200, 'color' => '#F59E0B'],
    ['source' => 'Events', 'leads' => 185, 'qualified' => 42, 'opps' => 18, 'won' => 9, 'convRate' => '4.86%', 'revenue' => 31500, 'color' => '#EC4899'],
    ['source' => 'Cold Outreach', 'leads' => 141, 'qualified' => 19, 'opps' => 8, 'won' => 6, 'convRate' => '4.25%', 'revenue' => 18000, 'color' => '#64748B'],
];

$reports_team_members = [
    [
        'name' => 'Olivia Martin',
        'role' => 'Sales Manager',
        'avatarBg' => '#7C3AED',
        'initials' => 'OM',
        'revenue' => 194500,
        'target' => 175000,
        'completion' => 111.1,
        'won' => 19,
        'winRate' => '38.5%',
        'avgDealSize' => 32400,
        'activities' => 412,
        'responseTime' => '1.1h'
    ],
    [
        'name' => 'Sarah Chen',
        'role' => 'Senior Account Executive',
        'avatarBg' => '#0284C7',
        'initials' => 'SC',
        'revenue' => 168200,
        'target' => 150000,
        'completion' => 112.1,
        'won' => 17,
        'winRate' => '35.4%',
        'avgDealSize' => 31200,
        'activities' => 385,
        'responseTime' => '1.3h'
    ],
    [
        'name' => 'James Wu',
        'role' => 'Account Executive',
        'avatarBg' => '#10B981',
        'initials' => 'JW',
        'revenue' => 123500,
        'target' => 125000,
        'completion' => 98.8,
        'won' => 12,
        'winRate' => '28.6%',
        'avgDealSize' => 29500,
        'activities' => 310,
        'responseTime' => '1.8h'
    ]
];

$reports_at_risk_deals = [
    [
        'deal' => 'Global Logistics Expansion',
        'company' => 'Acme Corp',
        'owner' => 'Sarah Chen',
        'stage' => 'Proposal',
        'daysInStage' => 24,
        'value' => 85000,
        'riskReason' => 'No activity > 14 days',
        'riskColor' => '#EF4444'
    ],
    [
        'deal' => 'Cloud Infrastructure Migration',
        'company' => 'Novatel Systems',
        'owner' => 'James Wu',
        'stage' => 'Negotiation',
        'daysInStage' => 18,
        'value' => 42000,
        'riskReason' => 'Close date passed',
        'riskColor' => '#F59E0B'
    ],
    [
        'deal' => 'Enterprise Security Upgrade',
        'company' => 'Brightpath Tech',
        'owner' => 'Olivia Martin',
        'stage' => 'Qualified',
        'daysInStage' => 31,
        'value' => 18000,
        'riskReason' => 'Budget unconfirmed',
        'riskColor' => '#64748B'
    ]
];

$reports_top_deals = [
    ['deal' => 'Enterprise Expansion Suite', 'company' => 'Acme Corp', 'owner' => 'Sarah Chen', 'stage' => 'Closed Won', 'value' => 94000, 'closeDate' => 'Dec 14, 2025'],
    ['deal' => 'Annual SaaS Multi-Year License', 'company' => 'Novatel Systems', 'owner' => 'James Wu', 'stage' => 'Closed Won', 'value' => 68500, 'closeDate' => 'Dec 02, 2025'],
    ['deal' => 'Global Fleet Analytics Rollout', 'company' => 'Omni Logistics', 'owner' => 'Olivia Martin', 'stage' => 'Closed Won', 'value' => 54000, 'closeDate' => 'Nov 28, 2025'],
    ['deal' => 'Fintech Compliance Platform', 'company' => 'Vanguard Financial', 'owner' => 'Olivia Martin', 'stage' => 'Negotiation', 'value' => 48000, 'closeDate' => 'Jan 15, 2026'],
    ['deal' => 'Healthcare Security Integration', 'company' => 'Apex Healthcare', 'owner' => 'Sarah Chen', 'stage' => 'Proposal', 'value' => 38000, 'closeDate' => 'Jan 22, 2026'],
];

$reports_product_revenue = [
    ['product' => 'Enterprise CRM License', 'revenue' => 218000, 'deals' => 22],
    ['product' => 'Custom API Integration', 'revenue' => 114000, 'deals' => 11],
    ['product' => 'Premium Dedicated Support', 'revenue' => 86000, 'deals' => 14],
    ['product' => 'Data Migration Services', 'revenue' => 42200, 'deals' => 8],
    ['product' => 'Staff Training & Onboarding', 'revenue' => 26000, 'deals' => 6],
];

$reports_activity_stats = [
    'summary' => [
        ['label' => 'Calls Logged', 'value' => '342', 'change' => '+14.2%', 'icon' => 'phone'],
        ['label' => 'Emails Opened', 'value' => '1,280', 'change' => '+8.5%', 'icon' => 'mail'],
        ['label' => 'WhatsApp Conversations', 'value' => '194', 'change' => '+22.1%', 'icon' => 'message-square'],
        ['label' => 'Meetings Held', 'value' => '88', 'change' => '+6.0%', 'icon' => 'calendar'],
        ['label' => 'Tasks Completed', 'value' => '215', 'change' => '+11.8%', 'icon' => 'check-square'],
        ['label' => 'Follow-ups Completed', 'value' => '156', 'change' => '+4.3%', 'icon' => 'rotate-cw']
    ],
    'timeline' => [
        ['day' => 'Mon', 'calls' => 64, 'emails' => 240, 'whatsapp' => 38, 'meetings' => 18, 'tasks' => 42],
        ['day' => 'Tue', 'calls' => 78, 'emails' => 290, 'whatsapp' => 44, 'meetings' => 22, 'tasks' => 48],
        ['day' => 'Wed', 'calls' => 82, 'emails' => 310, 'whatsapp' => 46, 'meetings' => 20, 'tasks' => 51],
        ['day' => 'Thu', 'calls' => 70, 'emails' => 260, 'whatsapp' => 39, 'meetings' => 16, 'tasks' => 45],
        ['day' => 'Fri', 'calls' => 48, 'emails' => 180, 'whatsapp' => 27, 'meetings' => 12, 'tasks' => 29],
    ]
];

$reports_saved_presets = [
    ['id' => 'saved-1', 'title' => 'Monthly Sales Performance', 'category' => 'Sales', 'updated' => '2 days ago'],
    ['id' => 'saved-2', 'title' => 'Pipeline Health & Velocity', 'category' => 'Pipeline', 'updated' => 'Yesterday'],
    ['id' => 'saved-3', 'title' => 'Lead Source Conversion', 'category' => 'Leads', 'updated' => 'Today'],
    ['id' => 'saved-4', 'title' => 'Team Target Progress', 'category' => 'Team', 'updated' => '3 days ago'],
    ['id' => 'saved-5', 'title' => 'Activity Summary', 'category' => 'Activity', 'updated' => 'Just now'],
];

