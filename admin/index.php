<?php
// Dashboard Page
require_once __DIR__ . '/includes/auth.php';
$user = require_admin_auth();
require_once __DIR__ . '/includes/dashboard-data.php';

$pdo = nexflow_db();
$orgId = (int)$user['organization_id'];

$dashboard_currency   = get_dashboard_currency($pdo, $orgId);
$currency_symbol      = $dashboard_currency['symbol'];
$kpi_cards            = get_dashboard_kpis($pdo, $orgId, $dashboard_currency);
$leads                = get_dashboard_recent_leads($pdo, $orgId, $dashboard_currency, 6);
$pipeline_bundle      = get_dashboard_pipeline($pdo, $orgId, $dashboard_currency);
$pipeline_summary     = $pipeline_bundle['stages'];
$total_pipeline_value = $pipeline_bundle['total_value'];
$activity_feed        = get_dashboard_activities($pdo, $orgId, 6);
$sales_analytics      = get_dashboard_analytics($pdo, $orgId, $dashboard_currency);

$page_title = "Dashboard";
$current_page = "dashboard";
$page_script = "dashboard.js";

include __DIR__ . '/includes/header.php';
requirePermission('dashboard');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>



<main class="main-content">
    <div class="page-container">
        <!-- KPI Metric Cards Grid -->
        <div class="kpi-grid">
            <?php if (!empty($kpi_cards) && is_array($kpi_cards)): ?>
            <?php foreach ($kpi_cards as $kpi): 
                $target_url = 'pipeline.php';
                if (isset($kpi['label']) && stripos($kpi['label'], 'lead') !== false) {
                    $target_url = 'leads.php';
                } elseif (isset($kpi['icon']) && $kpi['icon'] === 'users') {
                    $target_url = 'leads.php';
                }
            ?>
                <div class="kpi-card" onclick="window.location.href='<?php echo $target_url; ?>';" style="cursor: pointer;">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label"><?php echo htmlspecialchars($kpi['label']); ?></div>
                            <div class="kpi-value"><?php echo htmlspecialchars($kpi['value']); ?></div>
                        </div>
                        <div class="kpi-icon-box" style="background-color: <?php echo $kpi['color']; ?>18; color: <?php echo $kpi['color']; ?>;">
                            <?php if ($kpi['icon'] === 'users'): ?>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                            <?php elseif ($kpi['icon'] === 'dollar-sign'): ?>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                            <?php elseif ($kpi['icon'] === 'target'): ?>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                            <?php else: ?>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="kpi-footer">
                        <?php if ($kpi['changeDir'] === 'up'): ?>
                            <span class="kpi-change-up">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
                                <?php echo htmlspecialchars($kpi['change']); ?>
                            </span>
                        <?php else: ?>
                            <span class="kpi-change-down">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                <?php echo htmlspecialchars($kpi['change']); ?>
                            </span>
                        <?php endif; ?>
                        <span class="kpi-period">vs last month</span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Dashboard Main Grid: Left Recent Leads & Chart, Right Pipeline & Activity -->
        <div class="dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Recent Leads Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Leads</h2>
                        <a href="leads.php" class="card-action-link">View all →</a>
                    </div>
                    <div class="crm-table-wrapper">
                        <table class="crm-table" id="dashboardRecentLeadsTable" data-column-manager="disabled">
                            <thead>
                                <tr>
                                    <th data-protected="true" data-column-id="name">NAME</th>
                                    <th data-column-id="company">COMPANY</th>
                                    <th data-column-id="status">STATUS</th>
                                    <th data-column-id="value">VALUE</th>
                                    <th data-column-id="assignee">ASSIGNEE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leads) && is_array($leads)): ?>
                                <?php foreach (array_slice($leads, 0, 6) as $lead): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="avatar avatar-sm" style="background-color: <?php echo $lead['assigneeColor']; ?>;">
                                                    <?php 
                                                        $parts = explode(' ', $lead['name']);
                                                        echo strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                                    ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-heading); font-size: 13.5px;"><?php echo htmlspecialchars($lead['name']); ?></div>
                                                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($lead['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="color: var(--text-body); font-weight: 500;"><?php echo htmlspecialchars($lead['company']); ?></td>
                                        <td>
                                            <?php $status_slug = strtolower($lead['status']); ?>
                                            <span class="status-badge status-<?php echo $status_slug; ?>">
                                                <span class="status-dot"></span>
                                                <?php echo htmlspecialchars($lead['status']); ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: var(--text-body); font-variant-numeric: tabular-nums;">
                                            <?php echo htmlspecialchars($currency_symbol . number_format($lead['value'])); ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <div class="avatar avatar-xs" style="background-color: <?php echo $lead['assigneeColor']; ?>;">
                                                    <?php echo htmlspecialchars($lead['assigneeInitials']); ?>
                                                </div>
                                                <span style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($lead['assignee']); ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 32px 16px; color: var(--text-muted); font-size: 13px;">
                                            No recent leads found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sales Performance Analytics Chart.js Card -->
                <div class="card card-padding">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <div>
                            <h2 class="card-title">Sales Analytics</h2>
                            <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;" id="salesAnalyticsSubtitle">Lead conversion and monthly deal pipeline performance</p>
                        </div>
                        <div style="display: flex; gap: 8px;" role="group" aria-label="Sales analytics period">
                            <button type="button" class="btn btn-secondary btn-xs" id="btnAnalyticsMonthly" data-period="monthly" aria-pressed="true">Monthly</button>
                            <button type="button" class="btn btn-ghost btn-xs" id="btnAnalyticsQuarterly" data-period="quarterly" aria-pressed="false">Quarterly</button>
                        </div>
                    </div>
                    <div style="height: 240px; position: relative;">
                        <canvas id="salesAnalyticsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Column: Pipeline Summary & Activity Panel -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Pipeline Summary Card -->
                <div class="card card-padding">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <h2 class="card-title">Pipeline</h2>
                        <a href="pipeline.php" class="card-action-link">View →</a>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <?php if (!empty($pipeline_summary) && is_array($pipeline_summary)): ?>
                        <?php foreach ($pipeline_summary as $stage): ?>
                            <div>
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 12px;">
                                    <span style="font-weight: 500; color: var(--text-body);"><?php echo htmlspecialchars($stage['name']); ?></span>
                                    <div>
                                        <span style="color: var(--text-secondary);"><?php echo (int)$stage['count']; ?> deals</span>
                                        <span style="color: var(--border-input); margin: 0 4px;">·</span>
                                        <span style="font-weight: 600; color: var(--text-body);"><?php echo htmlspecialchars($stage['value_formatted']); ?></span>
                                    </div>
                                </div>
                                <div style="height: 8px; border-radius: 999px; background-color: var(--border-divider); overflow: hidden;">
                                    <div style="height: 100%; border-radius: 999px; width: <?php echo (int)$stage['pct']; ?>%; background-color: <?php echo htmlspecialchars($stage['color']); ?>; transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 24px 12px; color: var(--text-muted); font-size: 13px;">
                                No pipeline stages configured.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-divider); display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 12px; color: var(--text-secondary);">Total pipeline</span>
                        <span style="font-size: 15px; font-weight: 700; color: var(--text-heading);"><?php echo htmlspecialchars($currency_symbol . number_format($total_pipeline_value)); ?></span>
                    </div>
                </div>

                <!-- Recent Activity Feed Card -->
                <div class="card card-padding">
                    <h2 class="card-title" style="margin-bottom: 16px;">Activity</h2>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <?php if (!empty($activity_feed) && is_array($activity_feed)): ?>
                        <?php foreach ($activity_feed as $act): 
                            $target_link = 'pipeline.php';
                            $action_lower = strtolower($act['action']);
                            if (strpos($action_lower, 'lead') !== false || strpos($action_lower, 'note') !== false) {
                                $target_link = 'leads.php';
                            } elseif (strpos($action_lower, 'task') !== false) {
                                $target_link = 'tasks.php';
                            } elseif (strpos($action_lower, 'email') !== false || strpos($action_lower, 'contact') !== false) {
                                $target_link = 'contacts.php';
                            } elseif (strpos($action_lower, 'deal') !== false || strpos($action_lower, 'proposal') !== false || strpos($action_lower, 'won') !== false) {
                                $target_link = 'sales-pipeline.php';
                            }
                        ?>
                            <div class="dashboard-activity-item" onclick="window.location.href='<?php echo $target_link; ?>';" style="display: flex; align-items: flex-start; gap: 10px; padding: 6px 8px; border-radius: 6px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background-color: <?php echo $act['color']; ?>18; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                                    <?php if ($act['icon'] === 'user-plus'): ?>
                                        <svg width="14" height="14" fill="none" stroke="<?php echo $act['color']; ?>" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                                    <?php elseif ($act['icon'] === 'git-branch'): ?>
                                        <svg width="14" height="14" fill="none" stroke="<?php echo $act['color']; ?>" stroke-width="2" viewBox="0 0 24 24"><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/></svg>
                                    <?php elseif ($act['icon'] === 'check'): ?>
                                        <svg width="14" height="14" fill="none" stroke="<?php echo $act['color']; ?>" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php elseif ($act['icon'] === 'message-square'): ?>
                                        <svg width="14" height="14" fill="none" stroke="<?php echo $act['color']; ?>" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                                    <?php elseif ($act['icon'] === 'star'): ?>
                                        <svg width="14" height="14" fill="none" stroke="<?php echo $act['color']; ?>" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    <?php else: ?>
                                        <svg width="14" height="14" fill="none" stroke="<?php echo $act['color']; ?>" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 12.5px; color: var(--text-body); line-height: 1.4;">
                                        <?php echo htmlspecialchars($act['action']); ?> <span style="font-weight: 600; color: var(--text-heading);"><?php echo htmlspecialchars($act['subject']); ?></span>
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); margin-top: 2px; display: block;"><?php echo htmlspecialchars($act['time']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 32px 16px; color: var(--text-muted); font-size: 13px;">
                                No recent activities recorded.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    window.__DASHBOARD_INITIAL_ANALYTICS__ = <?php echo json_encode($sales_analytics, JSON_UNESCAPED_UNICODE); ?>;
    window.__DASHBOARD_CURRENCY_SYMBOL__ = <?php echo json_encode($currency_symbol, JSON_UNESCAPED_UNICODE); ?>;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

