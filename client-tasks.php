<?php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-tasks-data.php';

$client = require_client_auth();
$orgId = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];

$page_title = "Action Tasks — NexFlow Client Portal";
$active_nav = "tasks";
$page_heading = "Client Action Tasks";

// Preload live task data strictly isolated to client's organization and company
$tasksData = client_get_tasks_data(nexflow_db(), $orgId, $companyId);
$initialTasks = $tasksData['tasks'];
$initialKpis = $tasksData['kpis'];

include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Manage your checklist of pending onboarding, contract approvals, and technical verification items.</p>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="client-portal-toolbar">
            <div class="client-portal-toolbar-left">
                <div class="client-portal-search-wrapper" style="width:260px;">
                    <svg class="client-portal-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="client-portal-search-input" id="tasksSearchInput" placeholder="Search tasks..." oninput="renderClientTasks()">
                </div>

                <div style="display:flex;gap:6px;">
                    <button type="button" class="btn btn-secondary btn-sm active" id="filterTabAll" onclick="setTaskFilter('All', this)">All</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setTaskFilter('Overdue', this)">Overdue</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setTaskFilter('Today', this)">Today</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setTaskFilter('Upcoming', this)">Upcoming</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setTaskFilter('Completed', this)">Completed</button>
                </div>
            </div>

            <div class="client-portal-toolbar-right">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Task Support Question')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Ask Team About a Task
                </button>
            </div>
        </div>

        <!-- Grouped Tasks Card Container -->
        <div id="groupedTasksContainer" style="display:flex;flex-direction:column;gap:20px;">
            <!-- Rendered by JavaScript -->
        </div>

    </main>
</div>

<script>
let clientTasksList = <?php echo json_encode($initialTasks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
let currentTaskFilter = "All";

function setTaskFilter(filterName, btn) {
    currentTaskFilter = filterName;
    document.querySelectorAll(".client-portal-toolbar-left button").forEach(b => b.classList.remove("active"));
    if (btn) btn.classList.add("active");
    renderClientTasks();
}

function renderClientTasks() {
    const container = document.getElementById("groupedTasksContainer");
    if (!container) return;

    const query = (document.getElementById("tasksSearchInput")?.value || "").toLowerCase().trim();

    let tasks = clientTasksList.filter(t => {
        const matchesQuery = !query ||
            t.title.toLowerCase().includes(query) ||
            t.id.toLowerCase().includes(query) ||
            ((t.association_name || t.deal || '').toLowerCase().includes(query)) ||
            ((t.task_type || '').toLowerCase().includes(query));

        const matchesFilter = (currentTaskFilter === "All") ||
                              (currentTaskFilter === "Completed" && t.completed) ||
                              (currentTaskFilter === "Overdue" && t.group === "Overdue" && !t.completed) ||
                              (currentTaskFilter === "Today" && t.group === "Today" && !t.completed) ||
                              (currentTaskFilter === "Upcoming" && t.group === "Upcoming" && !t.completed);
        return matchesQuery && matchesFilter;
    });

    if (tasks.length === 0) {
        container.innerHTML = `
            <div class="client-portal-card" style="padding:40px;text-align:center;color:#64748B;">
                <svg width="36" height="36" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 8px auto;display:block;"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                No client action tasks matching the selected filter.
            </div>
        `;
        return;
    }

    const groups = ["Overdue", "Today", "Upcoming", "Completed"];
    let html = "";

    groups.forEach(grp => {
        const groupTasks = tasks.filter(t => {
            if (grp === "Completed") return t.completed;
            return (t.group === grp || (!t.group && grp === 'Upcoming')) && !t.completed;
        });

        if (groupTasks.length > 0) {
            html += `
                <div class="client-portal-card">
                    <div class="client-portal-card-header" style="background:#F8FAFC;">
                        <h3 class="client-portal-card-title">
                            <span class="client-portal-badge ${grp === 'Overdue' ? 'red' : grp === 'Today' ? 'amber' : grp === 'Completed' ? 'green' : 'blue'}">${grp}</span>
                            <span>${groupTasks.length} ${groupTasks.length === 1 ? 'Task' : 'Tasks'}</span>
                        </h3>
                    </div>
                    <div class="client-portal-card-body" style="padding:0;">
                        ${groupTasks.map(t => {
                            const badgeColor = t.priority === 'Urgent' ? 'red' : (t.priority === 'High' ? 'amber' : 'gray');
                            const assocLabel = t.association_label || 'Related';
                            const assocName  = t.association_name || t.deal || 'General Task';
                            return `
                            <div class="client-portal-task-item">
                                <div class="client-portal-task-left">
                                    <input type="checkbox" class="client-portal-task-checkbox" ${t.completed ? 'checked' : ''} disabled title="Status managed by NexFlow team" style="cursor:default;opacity:0.85;">
                                    <div>
                                        <div class="client-portal-task-title ${t.completed ? 'completed' : ''}">
                                            <span style="font-size:11px;font-weight:700;color:#2563EB;background:#EFF6FF;padding:2px 6px;border-radius:4px;margin-right:6px;">${t.id}</span>
                                            ${t.title}
                                        </div>
                                        <div class="client-portal-task-meta">
                                            <span>${assocLabel}: <strong>${assocName}</strong></span>
                                            <span>•</span>
                                            <span>Due ${t.dueDate || 'No due date'}</span>
                                            <span>•</span>
                                            <span class="client-portal-badge ${badgeColor}">${t.priority}</span>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-xs" onclick="openTaskModal('${t.id}')">Details</button>
                            </div>
                            `;
                        }).join("")}
                    </div>
                </div>
            `;
        }
    });

    container.innerHTML = html;
}

function openTaskModal(taskId) {
    const task = clientTasksList.find(t => t.id === taskId);
    if (!task) return;

    const statusBadge = task.completed
        ? '<span class="client-portal-badge green">Completed</span>'
        : (task.group === 'Overdue'
            ? '<span class="client-portal-badge red">Overdue</span>'
            : (task.group === 'Today'
                ? '<span class="client-portal-badge amber">Due Today</span>'
                : '<span class="client-portal-badge blue">Upcoming</span>'));

    const assocLabel = (task.association_label || 'RELATED ENTITY').toUpperCase();
    const assocName  = task.association_name || task.deal || 'General Task';

    const html = `
        <div class="client-portal-modal-header">
            <h3 class="client-portal-modal-title">Task Details: ${task.id} — ${task.title}</h3>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="client-portal-modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <div style="font-size:11px;color:#64748B;font-weight:600;">TASK ID</div>
                    <div style="font-size:13.5px;font-weight:700;color:#2563EB;margin-top:2px;">${task.id}</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#64748B;font-weight:600;">TASK STATUS</div>
                    <div style="margin-top:2px;">
                        ${statusBadge}
                    </div>
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <div style="font-size:11px;color:#64748B;font-weight:600;">${assocLabel}</div>
                <div style="font-size:13.5px;font-weight:600;color:#0F172A;margin-top:2px;">${assocName}</div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <div style="font-size:11px;color:#64748B;font-weight:600;">DUE DATE</div>
                    <div style="font-size:13px;color:#334155;margin-top:2px;">${task.dueDate || 'No due date'}</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#64748B;font-weight:600;">PRIORITY</div>
                    <div style="font-size:13px;color:#334155;margin-top:2px;">${task.priority}</div>
                </div>
            </div>

            <div style="margin-bottom:10px;">
                <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:4px;">DESCRIPTION &amp; INSTRUCTIONS</div>
                <p style="font-size:13px;color:#475569;line-height:1.5;margin:0;background:#F8FAFC;padding:10px;border-radius:6px;border:1px solid #E2E8F0;">
                    ${task.description || task.desc || 'No specific instructions provided.'}
                </p>
            </div>
        </div>
        <div class="client-portal-modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.closeModal();if(window.clientPortal && window.clientPortal.openQuickMessageModal)window.clientPortal.openQuickMessageModal('Question regarding ' + '${task.id}');">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Ask Team About this Task
            </button>
        </div>
    `;
    window.clientPortal.openModal(html);
}

document.addEventListener("DOMContentLoaded", () => renderClientTasks());
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>

