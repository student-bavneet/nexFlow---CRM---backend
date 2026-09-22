<?php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-dashboard-data.php';

$client = require_client_auth();
$pdo = nexflow_db();

// Fetch server-side rendered dashboard data scoped strictly to authenticated client context
$dashData = client_get_dashboard_data(
    $pdo,
    (int)$client['organization_id'],
    (int)$client['company_id'],
    (int)$client['contact_id'],
    (string)$client['company_name']
);

$primaryDeal = $dashData['primary_deal'];
$accountTeam = $dashData['account_team'];

$page_title = "Client Dashboard — NexFlow Client Portal";
$active_nav = "dashboard";
$page_heading = "Client Workspace Dashboard";
include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        <!-- Welcome Hero Banner -->
        <div class="client-portal-welcome-hero">
            <div class="client-portal-welcome-text">
                <h2>Welcome back, <span id="heroClientName"><?php echo htmlspecialchars(!empty($client['first_name']) ? $client['first_name'] : $client['contact_name']); ?></span> 👋</h2>
                <p>Here is an overview of your active contracts, open tasks, shared documents, and upcoming meetings with NexFlow.</p>
            </div>
            <div class="client-portal-quick-actions">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Send Message
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.openScheduleRequestModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                    Request Meeting
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.openUploadDocModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Document
                </button>
            </div>
        </div>

        <!-- 4 KPI Stat Cards -->
        <div class="client-portal-kpi-grid">
            <div class="client-portal-kpi-card" onclick="window.location.href='client-deals.php'" style="cursor:pointer;">
                <div class="client-portal-kpi-icon blue">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="dashDealsCount"><?php echo (int)$dashData['counts']['active_deals']; ?></div>
                    <div class="client-portal-kpi-label">Active &amp; Renewed Deals</div>
                </div>
            </div>

            <div class="client-portal-kpi-card" onclick="window.location.href='client-tasks.php'" style="cursor:pointer;">
                <div class="client-portal-kpi-icon amber">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="dashTasksCount"><?php echo (int)$dashData['counts']['pending_tasks']; ?></div>
                    <div class="client-portal-kpi-label">Pending Action Tasks</div>
                </div>
            </div>

            <div class="client-portal-kpi-card" onclick="window.location.href='client-calendar.php'" style="cursor:pointer;">
                <div class="client-portal-kpi-icon emerald">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="dashMeetingsCount"><?php echo (int)$dashData['counts']['upcoming_meetings']; ?></div>
                    <div class="client-portal-kpi-label">Upcoming Meetings</div>
                </div>
            </div>

            <div class="client-portal-kpi-card" onclick="window.location.href='client-documents.php'" style="cursor:pointer;">
                <div class="client-portal-kpi-icon purple">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div>
                    <div class="client-portal-kpi-val" id="dashDocsCount"><?php echo (int)$dashData['counts']['shared_documents']; ?></div>
                    <div class="client-portal-kpi-label">Shared Documents</div>
                </div>
            </div>
        </div>

        <!-- 2-Column Responsive Dashboard Layout -->
        <div class="client-portal-dash-grid">
            
            <!-- Left Column: Active Deal Progress & Action Items -->
            <div class="client-portal-dash-col">
                
                <!-- Primary Deal Progress Card -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">
                            <svg width="18" height="18" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>
                            Key Contract Progress
                        </h3>
                        <?php if ($primaryDeal): ?>
                            <button type="button" class="btn btn-ghost btn-xs" onclick="window.location.href='client-deals.php'">View Details</button>
                        <?php endif; ?>
                    </div>
                    <div class="client-portal-card-body">
                        <?php if ($primaryDeal): ?>
                            <div class="client-portal-deal-progress">
                                <div class="client-portal-deal-title-row">
                                    <div>
                                        <div style="font-size:14px;font-weight:700;color:#0F172A;"><?php echo htmlspecialchars($primaryDeal['name']); ?></div>
                                        <div style="font-size:12px;color:#64748B;">Lead: <?php echo htmlspecialchars($primaryDeal['owner_name'] ?: 'Account Team'); ?> • Expected Closing: <?php echo htmlspecialchars(!empty($primaryDeal['close_date']) ? date('M j, Y', strtotime($primaryDeal['close_date'])) : 'In Progress'); ?></div>
                                    </div>
                                    <span class="client-portal-badge blue"><?php echo htmlspecialchars($primaryDeal['stage']); ?> Stage (<?php echo (int)$primaryDeal['probability_val']; ?>%)</span>
                                </div>
                                <div class="client-portal-pipeline-track">
                                    <?php foreach ($primaryDeal['track_steps'] as $step): ?>
                                        <div class="client-portal-pipeline-step <?php echo htmlspecialchars($step['status']); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="client-portal-pipeline-labels">
                                    <?php foreach ($primaryDeal['track_steps'] as $idx => $step): ?>
                                        <span><?php echo ($idx + 1) . '. ' . htmlspecialchars($step['name']) . ($step['status'] === 'active' ? ' (Current)' : ''); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div style="font-size:13px;color:#475569;line-height:1.5;">
                                <strong>Latest Update:</strong> <?php echo $dashData['latest_update']; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding:24px;text-align:center;color:#64748B;font-size:13px;">No active deals in pipeline.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Client Action Tasks Card (Read-only presentation) -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">
                            <svg width="18" height="18" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            Pending Tasks for You
                        </h3>
                        <a href="client-tasks.php" class="btn btn-ghost btn-xs">View All Tasks</a>
                    </div>
                    <div class="client-portal-card-body" style="padding:0;" id="dashTasksContainer">
                        <?php if (empty($dashData['pending_tasks'])): ?>
                            <div style="padding:20px;text-align:center;color:#64748B;font-size:13px;">All tasks completed! Great work.</div>
                        <?php else: ?>
                            <?php foreach ($dashData['pending_tasks'] as $task): ?>
                                <div class="client-portal-task-item">
                                    <div class="client-portal-task-left">
                                        <input type="checkbox" class="client-portal-task-checkbox" aria-label="Task status" style="cursor:default;">
                                        <div>
                                            <div class="client-portal-task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <div class="client-portal-task-meta">
                                                <span>Due <?php echo htmlspecialchars($task['dueDate']); ?></span>
                                                <span>•</span>
                                                <span class="client-portal-badge <?php echo htmlspecialchars($task['priority_class']); ?>"><?php echo htmlspecialchars($task['priority']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="client-tasks.php" class="btn btn-ghost btn-xs">View</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shared Documents List -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">
                            <svg width="18" height="18" fill="none" stroke="#7C3AED" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Recently Shared Documents
                        </h3>
                        <a href="client-documents.php" class="btn btn-ghost btn-xs">All Documents</a>
                    </div>
                    <div class="client-portal-card-body" style="padding:0;">
                        <div class="client-portal-table-wrapper">
                            <table class="client-portal-table">
                                <thead>
                                    <tr>
                                        <th>Document Name</th>
                                        <th>Type</th>
                                        <th>Shared By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="dashDocsTableBody">
                                    <?php if (empty($dashData['shared_documents'])): ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center;padding:24px;color:#64748B;font-size:13px;">No documents shared yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dashData['shared_documents'] as $doc): ?>
                                            <tr>
                                                <td style="font-weight:600;color:#0F172A;"><?php echo htmlspecialchars($doc['name']); ?></td>
                                                <td><span class="client-portal-badge blue"><?php echo htmlspecialchars($doc['type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($doc['uploadedBy']); ?></td>
                                                <td>
                                                    <a href="client-documents.php" class="btn btn-ghost btn-xs">Preview</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Account Team & Upcoming Meetings -->
            <div class="client-portal-dash-col">
                
                <!-- Account Manager Card -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">Your Dedicated Account Team</h3>
                    </div>
                    <div class="client-portal-card-body">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                            <div class="client-portal-avatar" style="background-color:#7C3AED;width:44px;height:44px;font-size:16px;"><?php echo htmlspecialchars($accountTeam['initials']); ?></div>
                            <div>
                                <div style="font-size:15px;font-weight:700;color:#0F172A;"><?php echo htmlspecialchars($accountTeam['name']); ?></div>
                                <div style="font-size:12px;color:#64748B;"><?php echo htmlspecialchars($accountTeam['role']); ?></div>
                                <div style="font-size:11.5px;color:#2563EB;margin-top:2px;"><?php echo htmlspecialchars($accountTeam['email']); ?></div>
                            </div>
                        </div>
                        <div style="background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12.5px;color:#475569;">
                            "<?php echo htmlspecialchars($accountTeam['quote']); ?>"
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="btn btn-primary btn-sm" style="flex:1;" onclick="window.clientPortal.openQuickMessageModal()">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                Message
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="window.clientPortal.openScheduleRequestModal()">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                                Schedule
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Scheduled Meetings -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">
                            <svg width="18" height="18" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Upcoming Meetings
                        </h3>
                        <a href="client-calendar.php" class="btn btn-ghost btn-xs">Calendar</a>
                    </div>
                    <div class="client-portal-card-body" style="padding:14px 16px;" id="dashMeetingsContainer">
                        <?php if (empty($dashData['upcoming_meetings'])): ?>
                            <div style="padding:20px;text-align:center;color:#64748B;font-size:13px;">No upcoming meetings scheduled.</div>
                        <?php else: ?>
                            <?php foreach ($dashData['upcoming_meetings'] as $m): ?>
                                <div style="padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin-bottom:8px;">
                                    <div style="font-size:13px;font-weight:600;color:#0F172A;"><?php echo htmlspecialchars($m['title']); ?></div>
                                    <div style="font-size:11.5px;color:#64748B;margin-top:2px;"><?php echo htmlspecialchars($m['date']); ?> • <?php echo htmlspecialchars($m['time']); ?></div>
                                    <div style="font-size:11px;color:#059669;font-weight:600;margin-top:4px;"><?php echo htmlspecialchars($m['location']); ?> (<?php echo htmlspecialchars($m['status']); ?>)</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Messages Feed -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">
                            <svg width="18" height="18" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Recent Messages
                        </h3>
                        <a href="client-messages.php" class="btn btn-ghost btn-xs">Open Inbox</a>
                    </div>
                    <div class="client-portal-card-body" style="padding:14px 16px;" id="dashMessagesContainer">
                        <?php if (empty($dashData['recent_messages'])): ?>
                            <div style="padding:20px;text-align:center;color:#64748B;font-size:13px;">No recent messages.</div>
                        <?php else: ?>
                            <?php foreach ($dashData['recent_messages'] as $msg): ?>
                                <div style="padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin-bottom:8px;">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                        <div class="client-portal-avatar" style="background-color:#7C3AED;width:24px;height:24px;font-size:10px;"><?php echo htmlspecialchars($msg['initials']); ?></div>
                                        <strong style="font-size:12.5px;color:#0F172A;"><?php echo htmlspecialchars($msg['sender']); ?></strong>
                                        <span style="font-size:11px;color:#94A3B8;margin-left:auto;"><?php echo htmlspecialchars($msg['time']); ?></span>
                                    </div>
                                    <div style="font-size:12.5px;color:#475569;line-height:1.4;"><?php echo htmlspecialchars($msg['text']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

    </main>
</div>

<script>
// Expose server-side verified dashboard data for client-side interactivity
window.__CLIENT_DASHBOARD_DATA__ = <?php echo json_encode($dashData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function renderDashboardTasks() {
    const data = window.__CLIENT_DASHBOARD_DATA__ || {};
    const container = document.getElementById("dashTasksContainer");
    if (!container) return;

    const pending = Array.isArray(data.pending_tasks) ? data.pending_tasks.slice(0, 5) : [];
    if (pending.length === 0) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:#64748B;font-size:13px;">All tasks completed! Great work.</div>';
        return;
    }

    container.innerHTML = pending.map(t => `
        <div class="client-portal-task-item">
            <div class="client-portal-task-left">
                <input type="checkbox" class="client-portal-task-checkbox" aria-label="Task status" style="cursor:default;">
                <div>
                    <div class="client-portal-task-title">${t.title}</div>
                    <div class="client-portal-task-meta">
                        <span>Due ${t.dueDate}</span>
                        <span>•</span>
                        <span class="client-portal-badge ${t.priority_class || 'gray'}">${t.priority}</span>
                    </div>
                </div>
            </div>
            <a href="client-tasks.php" class="btn btn-ghost btn-xs">View</a>
        </div>
    `).join("");
}

function renderDashboardExtra() {
    const data = window.__CLIENT_DASHBOARD_DATA__ || {};
    
    // Meetings
    const meetCont = document.getElementById("dashMeetingsContainer");
    if (meetCont) {
        const meetings = Array.isArray(data.upcoming_meetings) ? data.upcoming_meetings.slice(0, 4) : [];
        if (meetings.length === 0) {
            meetCont.innerHTML = '<div style="padding:20px;text-align:center;color:#64748B;font-size:13px;">No upcoming meetings scheduled.</div>';
        } else {
            meetCont.innerHTML = meetings.map(m => `
                <div style="padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin-bottom:8px;">
                    <div style="font-size:13px;font-weight:600;color:#0F172A;">${m.title}</div>
                    <div style="font-size:11.5px;color:#64748B;margin-top:2px;">${m.date} • ${m.time}</div>
                    <div style="font-size:11px;color:#059669;font-weight:600;margin-top:4px;">${m.location} (${m.status})</div>
                </div>
            `).join("");
        }
    }

    // Docs
    const docTbody = document.getElementById("dashDocsTableBody");
    if (docTbody) {
        const docs = Array.isArray(data.shared_documents) ? data.shared_documents.slice(0, 4) : [];
        if (docs.length === 0) {
            docTbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:24px;color:#64748B;font-size:13px;">No documents shared yet.</td></tr>';
        } else {
            docTbody.innerHTML = docs.map(d => `
                <tr>
                    <td style="font-weight:600;color:#0F172A;">${d.name}</td>
                    <td><span class="client-portal-badge blue">${d.type}</span></td>
                    <td>${d.uploadedBy}</td>
                    <td>
                        <a href="client-documents.php" class="btn btn-ghost btn-xs">Preview</a>
                    </td>
                </tr>
            `).join("");
        }
    }

    // Messages
    const msgCont = document.getElementById("dashMessagesContainer");
    if (msgCont) {
        const msgs = Array.isArray(data.recent_messages) ? data.recent_messages.slice(0, 3) : [];
        if (msgs.length === 0) {
            msgCont.innerHTML = '<div style="padding:20px;text-align:center;color:#64748B;font-size:13px;">No recent messages.</div>';
        } else {
            msgCont.innerHTML = msgs.map(msg => `
                <div style="padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                        <div class="client-portal-avatar" style="background-color:#7C3AED;width:24px;height:24px;font-size:10px;">${msg.initials}</div>
                        <strong style="font-size:12.5px;color:#0F172A;">${msg.sender}</strong>
                        <span style="font-size:11px;color:#94A3B8;margin-left:auto;">${msg.time}</span>
                    </div>
                    <div style="font-size:12.5px;color:#475569;line-height:1.4;">${msg.text}</div>
                </div>
            `).join("");
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    renderDashboardTasks();
    renderDashboardExtra();
});
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>
