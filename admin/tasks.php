<?php
// Tasks & To-Dos Management Page
$page_title = "Tasks";
$current_page = "tasks";
$page_script = "tasks.js";

include __DIR__ . '/includes/header.php';
requirePermission('tasks');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$currentUser = nexflow_current_user();
$organizationId = (int)($currentUser['organization_id'] ?? 1);
$currentUserId  = (int)($currentUser['id'] ?? 1);
$currentUserName = $currentUser['name'] ?? $currentUser['display_name'] ?? 'User';

$pdo = nexflow_db();

// Real initial counts from MySQL
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ?");
$stmt->execute([$organizationId]);
$totalTasksCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND assigned_to = ?");
$stmt->execute([$organizationId, $currentUserId]);
$myTasksCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND DATE(due_date) = CURRENT_DATE()");
$stmt->execute([$organizationId]);
$dueTodayCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND due_date < NOW() AND status != 'completed'");
$stmt->execute([$organizationId]);
$overdueCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND DATE(due_date) > CURRENT_DATE() AND status != 'completed'");
$stmt->execute([$organizationId]);
$upcomingCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND status = 'completed'");
$stmt->execute([$organizationId]);
$completedCount = (int)$stmt->fetchColumn();
?>

<script>
    window.CURRENT_USER_ID = <?php echo json_encode($currentUserId); ?>;
    window.CURRENT_USER_NAME = <?php echo json_encode($currentUserName); ?>;
</script>

<main class="main-content tasks-page">
    <div class="page-container">
        <!-- Page Header -->
        <div class="projects-header">
            <div class="projects-header-title-area">
                <div class="projects-title-row">
                    <h1 class="projects-header-title">Tasks</h1>
                    <span class="projects-total-badge" id="tasksTotalCountBadge"><?php echo $totalTasksCount; ?> tasks</span>
                </div>
                <p class="projects-header-subtitle">Manage tasks, deadlines, assignments, priorities, and progress.</p>
            </div>
            <div class="projects-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.tasksApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.tasksApp.openCreateDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Add Task
                </button>
            </div>
        </div>

        <!-- 6-Card KPI Summary Grid -->
        <div class="projects-summary-bar">
            <div class="projects-summary-card active" data-summary="My" onclick="window.tasksApp.filterBySummary('My')">
                <div class="projects-summary-icon" style="background-color: #EEF2FF; color: #4F46E5;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryMyTasks"><?php echo $myTasksCount; ?></span>
                    <span class="projects-summary-lbl">My Tasks</span>
                </div>
            </div>

            <div class="projects-summary-card" data-summary="All" onclick="window.tasksApp.filterBySummary('All')">
                <div class="projects-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryTotalTasks"><?php echo $totalTasksCount; ?></span>
                    <span class="projects-summary-lbl">Total Tasks</span>
                </div>
            </div>

            <div class="projects-summary-card" data-summary="Today" onclick="window.tasksApp.filterBySummary('Today')">
                <div class="projects-summary-icon" style="background-color: #F0F9FF; color: #0284C7;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryDueToday"><?php echo $dueTodayCount; ?></span>
                    <span class="projects-summary-lbl">Due Today</span>
                </div>
            </div>

            <div class="projects-summary-card" data-summary="Overdue" onclick="window.tasksApp.filterBySummary('Overdue')">
                <div class="projects-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryOverdue"><?php echo $overdueCount; ?></span>
                    <span class="projects-summary-lbl">Overdue</span>
                </div>
            </div>

            <div class="projects-summary-card" data-summary="Upcoming" onclick="window.tasksApp.filterBySummary('Upcoming')">
                <div class="projects-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryUpcoming"><?php echo $upcomingCount; ?></span>
                    <span class="projects-summary-lbl">Upcoming</span>
                </div>
            </div>

            <div class="projects-summary-card" data-summary="Completed" onclick="window.tasksApp.filterBySummary('Completed')">
                <div class="projects-summary-icon" style="background-color: #ECFDF5; color: #059669;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="projects-summary-info">
                    <span class="projects-summary-val" id="summaryCompleted"><?php echo $completedCount; ?></span>
                    <span class="projects-summary-lbl">Completed</span>
                </div>
            </div>
        </div>

        <!-- Control Bar & Toolbar -->
        <div class="tasks-toolbar">
            <div class="tasks-toolbar-row1">
                <div class="tasks-controls-left">
                    <div style="position: relative; flex: 1 1 200px; max-width: 300px;">
                        <svg width="15" height="15" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="input-control input-sm" id="tasksSearchInput" placeholder="Search tasks..." style="padding-left: 32px; padding-right: 28px; width: 100%;">
                        <button type="button" id="tasksClearSearchBtn" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 14px; color: var(--text-muted); cursor: pointer; display: none; padding: 2px 6px;" onclick="window.tasksApp.clearSearch()">&times;</button>
                    </div>

                    <div style="position: relative;">
                        <button type="button" class="btn btn-secondary btn-sm" id="btnTaskFilter" onclick="window.tasksApp.toggleFilterPopover(event)">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            <span>Filter</span>
                        </button>

                        <!-- Tasks Filter Popover Panel -->
                        <div class="tasks-filter-popover" id="tasksFilterPopover" onclick="event.stopPropagation()">
                            <div class="tasks-filter-header">
                                <div class="tasks-filter-title">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                    <span>Filter Tasks</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <button type="button" class="btn-clear-filter-text" onclick="window.tasksApp.resetFilters()">Clear all</button>
                                    <button type="button" class="filter-popover-close" onclick="window.tasksApp.closeFilterPopover()" aria-label="Close filter panel">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="tasks-filter-body">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Task Type</label>
                                    <select class="input-control input-sm" id="filterTypeSelect">
                                        <option value="All">All Types</option>
                                        <option value="Call">Call</option>
                                        <option value="Email">Email</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="Meeting">Meeting</option>
                                        <option value="Follow-up">Follow-up</option>
                                        <option value="Proposal">Proposal</option>
                                        <option value="General Task">General Task</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Priority</label>
                                    <select class="input-control input-sm" id="filterPrioritySelect">
                                        <option value="All">All Priorities</option>
                                        <option value="Urgent">Urgent</option>
                                        <option value="High">High</option>
                                        <option value="Medium">Medium</option>
                                        <option value="Low">Low</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Status</label>
                                    <select class="input-control input-sm" id="filterStatusSelect">
                                        <option value="All">All Statuses</option>
                                        <option value="Not Started">Not Started</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Waiting">Waiting</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Assigned Owner</label>
                                    <select class="input-control input-sm" id="filterAssigneeDrawerSelect">
                                        <option value="All">All Owners</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Related Company</label>
                                    <input type="text" class="input-control input-sm" id="filterCompanyInput" placeholder="e.g., Acme Corp">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Related Contact</label>
                                    <input type="text" class="input-control input-sm" id="filterContactInput" placeholder="e.g., Marcus Thompson">
                                </div>
                            </div>
                            <div class="tasks-filter-footer">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="window.tasksApp.closeFilterPopover()">Cancel</button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="window.tasksApp.applyFilterDrawer()">Apply Filters</button>
                            </div>
                        </div>
                    </div>

                    <select class="input-control input-sm" id="tasksSortSelect" style="width: 170px;">
                        <option value="due-asc">Due Date: Earliest</option>
                        <option value="due-desc">Due Date: Latest</option>
                        <option value="priority-desc">Priority: Highest</option>
                        <option value="created-desc">Recently Created</option>
                        <option value="alpha-asc">Alphabetical</option>
                    </select>

                    <select class="input-control input-sm" id="tasksAssigneeSelect" style="width: 140px;">
                        <option value="All">All Assignees</option>
                    </select>
                </div>

                <div class="tasks-controls-right" style="display: flex; align-items: center; gap: 10px;">
                    <div class="tasks-view-toggle">
                        <button type="button" class="tasks-view-btn active" id="btnViewList" title="Grouped List View" onclick="window.tasksApp.setViewMode('list')">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        </button>
                        <button type="button" class="tasks-view-btn" id="btnViewBoard" title="Kanban Board View" onclick="window.tasksApp.setViewMode('board')">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Removable Active Filter Chips -->
            <div class="tasks-active-chips" id="tasksActiveChips">
                <!-- Populated dynamically by JS -->
            </div>
        </div>

        <!-- Bulk Selection Toolbar -->
        <div class="tasks-bulk-toolbar" id="tasksBulkToolbar">
            <span class="tasks-bulk-text"><span id="tasksSelectedCountText">0</span> tasks selected</span>
            <div style="display: flex; align-items: center; gap: 6px; margin-left: auto; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.tasksApp.executeBulkAction('complete')">Mark Complete</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.tasksApp.executeBulkAction('status')">Change Status</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.tasksApp.executeBulkAction('assignee')">Change Assignee</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.tasksApp.executeBulkAction('priority')">Change Priority</button>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.tasksApp.executeBulkAction('reschedule')">Reschedule</button>
                <button type="button" class="btn btn-secondary btn-xs" style="color: #F04438;" onclick="window.tasksApp.executeBulkAction('delete')">Delete</button>
            </div>
        </div> 

        <!-- Main Grouped List View Container -->
        <div class="tasks-list-container" id="tasksListView">
            <!-- Populated dynamically by JS -->
        </div>

        <!-- Kanban Board View Container -->
        <div class="tasks-board" id="tasksBoardView" style="display: none;">
            <!-- Populated dynamically by JS -->
        </div>

        <!-- Pagination Bar -->
        <div class="pagination-container">
            <div>Showing <span style="font-weight: 600; color: var(--text-body);" id="pagingRange">1–8</span> of <span style="font-weight: 600; color: var(--text-body);" id="pagingTotal">0</span></div>
            <div class="pagination-controls" id="paginationControls">
                <!-- Populated dynamically by JS -->
            </div>
        </div>
    </div>
</main>

<!-- Drawer 1: Task Details Drawer (460px wide) -->
<div class="task-view-overlay" id="taskDetailsDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="taskHeaderTitle">
    <div class="task-view-panel" aria-hidden="true">
        <div class="task-view-header">
            <div>
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">Tasks / <span id="taskBreadcrumbTitle">Task Details</span></span>
                <h3 style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin: 1px 0 0;" id="taskHeaderTitle">Task Details</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 4px;">
                <button type="button" class="btn btn-ghost btn-xs" title="Edit Task" onclick="window.tasksApp.openEditFromDetails()" style="padding: 4px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button type="button" class="btn btn-ghost btn-xs" title="Delete Task" onclick="window.tasksApp.deleteCurrentTask()" style="padding: 4px; color: #DC2626;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <button type="button" class="btn btn-ghost btn-xs" title="Close Drawer (Esc)" onclick="window.tasksApp.closeDetails()" style="padding: 4px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div class="task-view-body">
            <div id="taskSummaryBlock" style="background-color: #F9FAFB; border: 1px solid var(--border-card); border-radius: var(--radius-lg); padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <span class="tasks-priority high" id="tdPriority">High Priority</span>
                    <span class="tasks-status in-progress" id="tdStatus">In Progress</span>
                </div>
                <h2 style="font-size: 15px; font-weight: 700; color: var(--text-heading); margin: 0 0 6px;" id="tdTitle">Task Details</h2>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 12px;" id="tdMeta">Due: None • Assigned to Unassigned</div>
                
                <!-- Direct Drawer Actions -->
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; border-top: 1px solid var(--border-divider, #E5E7EB); padding-top: 10px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-xs" id="tdBtnToggleComplete" onclick="window.tasksApp.toggleDetailsComplete()">
                            Mark Complete
                        </button>
                        <select id="tdStatusSelect" class="input-control input-sm" style="font-size: 11.5px; height: 28px; width: auto; padding: 2px 8px;" onchange="window.tasksApp.changeDetailsStatus(this.value)">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="waiting">Waiting</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-xs" title="Edit Task" onclick="window.tasksApp.openEditFromDetails()">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Edit
                        </button>
                        <button type="button" class="btn btn-secondary btn-xs" title="Delete Task" style="color: #DC2626;" onclick="window.tasksApp.deleteCurrentTask()">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div id="taskTabContent" class="company-view-tab-content">
                <!-- Populated dynamically by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Drawer 2: + Create / Edit Task Form Drawer (460px wide) -->
<div class="task-form-overlay" id="tasksCreateDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="createTaskModalTitle">
    <div class="task-form-panel" aria-hidden="true">
        <div class="task-form-header">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--text-heading); margin: 0;" id="createTaskModalTitle">Create Task</h3>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.tasksApp.closeCreateDrawer()" style="padding: 4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="createTaskForm" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
            <input type="hidden" id="editTaskId" value="">
            <div class="task-form-body">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Task Information</div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Task Title *</label>
                    <input type="text" class="input-control input-sm" id="addTaskTitle" required placeholder="e.g., Follow up with Marcus Thompson">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Task Type</label>
                        <select class="input-control input-sm" id="addTaskType">
                            <option value="Call">Call</option>
                            <option value="Email">Email</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Follow-up">Follow-up</option>
                            <option value="Proposal">Proposal</option>
                            <option value="General Task" selected>General Task</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Priority</label>
                        <select class="input-control input-sm" id="addTaskPriority">
                            <option value="Urgent">Urgent</option>
                            <option value="High" selected>High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Description</label>
                    <textarea class="input-control" id="addTaskDesc" style="height: 60px; font-size: 12px; resize: none;" placeholder="Task details and instructions..."></textarea>
                </div>

                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Associations</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Related Contact</label>
                        <select class="input-control input-sm" id="addTaskContact">
                            <option value="">Select Contact (Optional)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Related Company</label>
                        <select class="input-control input-sm" id="addTaskCompany">
                            <option value="">Select Company (Optional)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Related Deal (Optional)</label>
                    <select class="input-control input-sm" id="addTaskDeal">
                        <option value="">Select Deal (Optional)</option>
                    </select>
                </div>

                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Schedule & Assignment</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Due Date & Time *</label>
                        <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 6px;">
                            <input type="date" class="input-control input-sm" id="addTaskDueDate" required>
                            <input type="time" class="input-control input-sm" id="addTaskDueTime" value="09:00">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Assigned Owner</label>
                        <select class="input-control input-sm" id="addTaskAssignee">
                            <option value="">Select Owner</option>
                        </select>
                    </div>
                </div>
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 12px; margin-bottom: 8px;">Client Portal Settings</div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #F8FAFC; border: 1px solid var(--border-divider); border-radius: var(--radius-md); margin-bottom: 12px;">
                    <input type="checkbox" id="addTaskClientVisible" class="input-checkbox" style="width: 16px; height: 16px; cursor: pointer;">
                    <div>
                        <label for="addTaskClientVisible" style="font-size: 12.5px; font-weight: 600; color: var(--text-heading); cursor: pointer; margin: 0;">Client Visible (Display in Client Portal)</label>
                        <div style="font-size: 11px; color: var(--text-muted);">Enable to make this task visible in the Client Portal checklist.</div>
                    </div>
                </div>
            </div>
            <div class="task-form-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.tasksApp.closeCreateDrawer()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnSaveTaskSubmit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Tasks Page Level Toolbar Actions Dropdown Menu -->
<div class="tasks-toolbar-dropdown" id="tasksToolbarDropdown" role="menu" aria-label="Toolbar Actions">
    <button type="button" class="tasks-toolbar-item" role="menuitem" onclick="window.tasksApp.executeToolbarAction('refresh')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
        Refresh Tasks
    </button>
    <button type="button" class="tasks-toolbar-item" role="menuitem" onclick="window.tasksApp.executeToolbarAction('selectAllVisible')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Select All Visible
    </button>
    <button type="button" class="tasks-toolbar-item" role="menuitem" onclick="window.tasksApp.executeToolbarAction('clearSelection')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
        Clear Selection
    </button>

    <div class="tasks-toolbar-divider"></div>

    <button type="button" class="tasks-toolbar-item" id="btnToggleGroupCollapseItem" role="menuitem" onclick="window.tasksApp.executeToolbarAction('toggleGroupCollapse')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        <span id="groupCollapseText">Collapse All Groups</span>
    </button>
    <button type="button" class="tasks-toolbar-item" id="btnMarkSelectedComplete" role="menuitem" onclick="window.tasksApp.executeToolbarAction('markSelectedComplete')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Mark Selected Complete
    </button>
    <button type="button" class="tasks-toolbar-item" id="btnChangeSelectedStatus" role="menuitem" onclick="window.tasksApp.executeToolbarAction('changeSelectedStatus')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Change Selected Status
    </button>
    <button type="button" class="tasks-toolbar-item" id="btnReassignSelected" role="menuitem" onclick="window.tasksApp.executeToolbarAction('reassignSelected')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Reassign Selected
    </button>

    <div class="tasks-toolbar-divider"></div>

    <button type="button" class="tasks-toolbar-item" id="btnToggleDensityItem" role="menuitem" onclick="window.tasksApp.executeToolbarAction('toggleDensity')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
        <span id="densityItemText">Compact Density</span>
    </button>
    <button type="button" class="tasks-toolbar-item" role="menuitem" onclick="window.tasksApp.executeToolbarAction('resetTaskView')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Reset Task View
    </button>

    <div class="tasks-toolbar-divider"></div>

    <button type="button" class="tasks-toolbar-item" role="menuitem" onclick="window.tasksApp.executeToolbarAction('printList')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print Current Task List
    </button>
    <button type="button" class="tasks-toolbar-item" role="menuitem" onclick="window.tasksApp.executeToolbarAction('exportCSV')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Visible Tasks
    </button>
</div>

<!-- Change Selected Status Modal -->
<div class="modal-overlay tasks-action-modal" id="tasksChangeStatusModal" role="dialog" aria-modal="true" aria-labelledby="tasksChangeStatusTitle" aria-hidden="true">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="modal-icon-badge" style="background-color: var(--primary-light); color: var(--primary); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </div>
                <div>
                    <h3 class="modal-title" id="tasksChangeStatusTitle">Change Task Status</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 1px;">Update status for <span id="changeStatusCountText">0</span> selected task(s)</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="window.tasksApp.closeTaskModal('tasksChangeStatusModal')" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="modal-body" style="padding: 18px 20px;">
            <label class="form-label" style="margin-bottom: 8px; font-weight: 600;">Select New Status</label>
            <select class="input-control" id="modalTaskStatusSelect">
                <option value="Not Started" selected>Not Started (To Do)</option>
                <option value="In Progress">In Progress</option>
                <option value="Waiting">Waiting</option>
                <option value="Completed">Completed</option>
            </select>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="window.tasksApp.closeTaskModal('tasksChangeStatusModal')">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" onclick="window.tasksApp.submitChangeSelectedStatus()">Apply Status</button>
        </div>
    </div>
</div>

<!-- Reassign Selected Tasks Modal -->
<div class="modal-overlay tasks-action-modal" id="tasksReassignModal" role="dialog" aria-modal="true" aria-labelledby="tasksReassignTitle" aria-hidden="true">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="modal-icon-badge" style="background-color: var(--primary-light); color: var(--primary); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                    <h3 class="modal-title" id="tasksReassignTitle">Reassign Selected Tasks</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 1px;">Assign <span id="reassignCountText">0</span> selected task(s) to a team member</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="window.tasksApp.closeTaskModal('tasksReassignModal')" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="modal-body" style="padding: 18px 20px;">
            <label class="form-label" style="margin-bottom: 8px; font-weight: 600;">Select Assignee</label>
            <select class="input-control" id="modalTaskAssigneeSelect">
                <option value="">Select Assignee</option>
            </select>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-md" onclick="window.tasksApp.closeTaskModal('tasksReassignModal')">Cancel</button>
            <button type="button" class="btn btn-primary btn-md" onclick="window.tasksApp.submitReassignSelected()">Reassign Tasks</button>
        </div>
    </div>
</div>

<!-- Top-level Floating Task Row Action Menu Dropdown Container -->
<div class="tasks-row-menu-dropdown" id="taskRowMenuDropdown" role="menu" aria-label="Task Row Actions"></div>

<?php include __DIR__ . '/includes/footer.php'; ?>
