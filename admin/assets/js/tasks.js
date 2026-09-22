/**
 * NexFlow CRM Task Management Controller JavaScript
 * Database-backed with MySQL API (admin/api/tasks.php).
 * Handles search, tabs, sorting, list/board view, bulk actions, pagination,
 * grouped task lists, native HTML5 Kanban drag-and-drop, interactive completion with Undo toast,
 * WAI-ARIA Task Details drawer (Details, Activity, Notes), Create/Edit task drawer,
 * filter panel, note composer & live relational foreign keys.
 */

(function() {
    const VIEW_MODE_KEY = "NexFlow_tasks_view_mode";
    const DENSITY_STORAGE_KEY = "NexFlow.tasks.density";

    const currentUserId = window.CURRENT_USER_ID || 1;
    const currentUserName = window.CURRENT_USER_NAME || "User";

    let allTasks = [];
    let filteredTasks = [];
    let referenceOptions = { users: [], companies: [], contacts: [], deals: [] };
    let currentTab = "My";
    let filterType = "All";
    let filterPriority = "All";
    let filterStatus = "All";
    let filterAssignee = "All";
    let filterCompany = "";
    let filterContact = "";
    let searchQuery = "";
    let sortBy = "due-asc";
    let viewMode = "list"; // 'list' or 'board'
    let currentDensity = loadDensityState();
    let currentPage = 1;
    let rowsPerPage = 8;
    let selectedTaskIds = new Set();
    let collapsedGroups = new Set();

    let activeTaskForDetails = null;
    let activeTabName = "details";
    let lastFocusedElement = null;
    let activeOpenMenuId = null;
    let activeRowMenuTaskId = null;
    let lastFocusedRowMenuBtn = null;
    let lastCompletedTaskBackup = null;
    let taskNoteSearchQuery = "";

    function loadDensityState() {
        try {
            const saved = localStorage.getItem(DENSITY_STORAGE_KEY);
            if (saved && ["comfortable", "compact"].includes(saved)) {
                return saved;
            }
        } catch (e) {}
        return "comfortable";
    }

    function applyDensityUI() {
        const pageEl = document.querySelector(".tasks-page") || document.body;
        if (currentDensity === "compact") {
            pageEl.classList.add("compact-density");
        } else {
            pageEl.classList.remove("compact-density");
        }

        const textEl = document.getElementById("densityItemText");
        if (textEl) {
            textEl.textContent = (currentDensity === "comfortable") ? "Compact Density" : "Comfortable Density";
        }
    }

    function positionToolbarMenu() {
        const btn = document.getElementById("btnTasksToolbarMenu");
        const menu = document.getElementById("tasksToolbarDropdown");
        if (!btn || !menu) return;

        const rect = btn.getBoundingClientRect();
        const menuWidth = menu.offsetWidth || 240;

        let left = rect.right - menuWidth;
        let top = rect.bottom + 4;

        if (left < 10) left = 10;
        if (left + menuWidth > window.innerWidth - 10) {
            left = window.innerWidth - menuWidth - 10;
        }

        menu.style.top = `${top}px`;
        menu.style.left = `${left}px`;
    }

    function updateToolbarMenuActionStates() {
        const selectedCount = selectedTaskIds.size;
        const markCompleteBtn = document.getElementById("btnMarkSelectedComplete");
        const changeStatusBtn = document.getElementById("btnChangeSelectedStatus");
        const reassignBtn = document.getElementById("btnReassignSelected");

        if (markCompleteBtn) markCompleteBtn.disabled = (selectedCount === 0);
        if (changeStatusBtn) changeStatusBtn.disabled = (selectedCount === 0);
        if (reassignBtn) reassignBtn.disabled = (selectedCount === 0);
    }

    function positionRowMenu(btnElem) {
        const menu = document.getElementById("taskRowMenuDropdown");
        if (!menu || !btnElem) return;

        const rect = btnElem.getBoundingClientRect();
        const menuWidth = 190;
        const menuHeight = menu.offsetHeight || 220;

        let left = rect.right - menuWidth;
        let top = rect.bottom + 4;

        if (left < 10) left = 10;
        if (left + menuWidth > window.innerWidth - 10) {
            left = window.innerWidth - menuWidth - 10;
        }

        if (top + menuHeight > window.innerHeight - 10) {
            top = rect.top - menuHeight - 4;
            if (top < 10) top = 10;
        }

        menu.style.top = `${top}px`;
        menu.style.left = `${left}px`;
    }

    function closeTaskRowMenuInternal() {
        const menu = document.getElementById("taskRowMenuDropdown");
        if (menu) {
            menu.classList.remove("show");
            menu.style.display = "none";
        }
        if (lastFocusedRowMenuBtn) {
            lastFocusedRowMenuBtn.setAttribute("aria-expanded", "false");
        }
        activeRowMenuTaskId = null;
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return "";
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    // -------------------------------------------------------------
    // API OPERATIONS
    // -------------------------------------------------------------

    // Load active Reference Options (Users, Companies, Contacts, Deals)
    async function loadReferenceOptions() {
        try {
            const res = await fetch("api/tasks.php?action=reference_options");
            const data = await res.json();
            if (data && data.success && data.data) {
                referenceOptions = data.data;
                populateReferenceDropdowns();
            }
        } catch (e) {
            console.error("Error loading reference options:", e);
        }
    }

    function populateReferenceDropdowns() {
        // 1. Company dropdown in Create Drawer
        const compSelect = document.getElementById("addTaskCompany");
        if (compSelect) {
            compSelect.innerHTML = '<option value="">Select Company (Optional)</option>' +
                (referenceOptions.companies || []).map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
        }

        // 2. Contact dropdown in Create Drawer
        const contSelect = document.getElementById("addTaskContact");
        if (contSelect) {
            contSelect.innerHTML = '<option value="">Select Contact (Optional)</option>' +
                (referenceOptions.contacts || []).map(ct => `<option value="${ct.id}" data-company-id="${ct.company_id || ''}">${escapeHtml(ct.name)}</option>`).join("");
        }

        // 3. Deal dropdown in Create Drawer
        const dealSelect = document.getElementById("addTaskDeal");
        if (dealSelect) {
            dealSelect.innerHTML = '<option value="">Select Deal (Optional)</option>' +
                (referenceOptions.deals || []).map(d => `<option value="${d.id}">${escapeHtml(d.name)}${d.deal_code ? ' (' + escapeHtml(d.deal_code) + ')' : ''}</option>`).join("");
        }

        // 4. Assignee in Create Drawer
        const assignSelect = document.getElementById("addTaskAssignee");
        if (assignSelect) {
            assignSelect.innerHTML = '<option value="">Select Owner</option>' +
                (referenceOptions.users || []).map(u => `<option value="${u.id}">${escapeHtml(u.name || u.display_name)}</option>`).join("");
            // Default select current user
            assignSelect.value = currentUserId;
        }

        // 5. Toolbar Assignee Filter
        const tbAssignSelect = document.getElementById("tasksAssigneeSelect");
        if (tbAssignSelect) {
            tbAssignSelect.innerHTML = '<option value="All">All Assignees</option>' +
                (referenceOptions.users || []).map(u => `<option value="${u.id}">${escapeHtml(u.name || u.display_name)}</option>`).join("");
        }

        // 6. Drawer Filter Assignee
        const drAssignSelect = document.getElementById("filterAssigneeDrawerSelect");
        if (drAssignSelect) {
            drAssignSelect.innerHTML = '<option value="All">All Owners</option>' +
                (referenceOptions.users || []).map(u => `<option value="${u.id}">${escapeHtml(u.name || u.display_name)}</option>`).join("");
        }

        // 7. Bulk Modal Assignee
        const modalAssignSelect = document.getElementById("modalTaskAssigneeSelect");
        if (modalAssignSelect) {
            modalAssignSelect.innerHTML = '<option value="">Select Assignee</option>' +
                (referenceOptions.users || []).map(u => `<option value="${u.id}">${escapeHtml(u.name || u.display_name)}</option>`).join("");
        }

        // Auto-link Company when selecting Contact
        if (contSelect && compSelect) {
            contSelect.addEventListener("change", function() {
                const opt = contSelect.options[contSelect.selectedIndex];
                const compId = opt ? opt.getAttribute("data-company-id") : null;
                if (compId && !compSelect.value) {
                    compSelect.value = compId;
                }
            });
        }
    }

    // Load tasks from API
    async function loadTasksFromApi(callback) {
        try {
            const res = await fetch("api/tasks.php?action=list");
            const data = await res.json();
            if (data && data.success && data.data && Array.isArray(data.data.tasks)) {
                allTasks = data.data.tasks;
            } else {
                allTasks = [];
            }
        } catch (e) {
            console.error("Failed to load tasks from API:", e);
        }

        // Also fetch fresh summary KPI counters
        fetchSummaryKpis();

        applyFilterAndSort();
        if (typeof callback === "function") callback();
    }

    async function fetchSummaryKpis() {
        try {
            const res = await fetch("api/tasks.php?action=summary");
            const data = await res.json();
            if (data && data.success && data.data) {
                const kpis = data.data;
                const totalVal = document.getElementById("summaryTotalTasks");
                const myVal = document.getElementById("summaryMyTasks");
                const dueTodayVal = document.getElementById("summaryDueToday");
                const overdueVal = document.getElementById("summaryOverdue");
                const upcomingVal = document.getElementById("summaryUpcoming");
                const completedVal = document.getElementById("summaryCompleted");

                if (totalVal) totalVal.textContent = kpis.total_tasks;
                if (myVal) myVal.textContent = kpis.my_tasks;
                if (dueTodayVal) dueTodayVal.textContent = kpis.due_today;
                if (overdueVal) overdueVal.textContent = kpis.overdue;
                if (upcomingVal) upcomingVal.textContent = kpis.upcoming;
                if (completedVal) completedVal.textContent = kpis.completed;

                // Synchronize sidebar Tasks count badge
                const incompleteTasks = (kpis.total_tasks !== undefined && kpis.completed !== undefined)
                    ? Math.max(0, parseInt(kpis.total_tasks, 10) - parseInt(kpis.completed, 10))
                    : (parseInt(kpis.overdue, 10) + parseInt(kpis.upcoming, 10) + parseInt(kpis.due_today, 10));

                if (typeof window.updateSidebarBadges === "function") {
                    window.updateSidebarBadges({ tasks: incompleteTasks });
                }
                if (typeof window.refreshSidebarBadges === "function") {
                    window.refreshSidebarBadges();
                }
            }
        } catch (e) {
            console.error("Failed to fetch summary KPIs:", e);
        }
    }

    function initTasksPage() {
        applyDensityUI();

        const savedViewMode = localStorage.getItem(VIEW_MODE_KEY);
        if (savedViewMode === "list" || savedViewMode === "board") {
            viewMode = savedViewMode;
            const btnList = document.getElementById("btnViewList");
            const btnBoard = document.getElementById("btnViewBoard");
            if (btnList) btnList.classList.toggle("active", viewMode === "list");
            if (btnBoard) btnBoard.classList.toggle("active", viewMode === "board");
        }

        setupEventListeners();
        loadReferenceOptions();
        loadTasksFromApi();
    }

    window.initTasksPage = initTasksPage;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function() { initTasksPage(); });
    } else {
        initTasksPage();
    }

    function setupEventListeners() {
        const root = document.querySelector(".tasks-page");
        if (!root) return;

        // Search Input
        const searchInput = document.getElementById("tasksSearchInput");
        const clearSearchBtn = document.getElementById("tasksClearSearchBtn");
        if (searchInput) {
            searchInput.addEventListener("input", function() {
                searchQuery = searchInput.value.trim().toLowerCase();
                if (clearSearchBtn) {
                    clearSearchBtn.style.display = searchQuery ? "block" : "none";
                }
                currentPage = 1;
                applyFilterAndSort();
            });
        }

        // Sort Select
        const sortSelect = document.getElementById("tasksSortSelect");
        if (sortSelect) {
            sortSelect.addEventListener("change", function() {
                sortBy = sortSelect.value;
                applyFilterAndSort();
            });
        }

        // Toolbar Assignee Select
        const assigneeSelect = document.getElementById("tasksAssigneeSelect");
        if (assigneeSelect) {
            assigneeSelect.addEventListener("change", function() {
                filterAssignee = assigneeSelect.value;
                const drawerAssignee = document.getElementById("filterAssigneeDrawerSelect");
                if (drawerAssignee) drawerAssignee.value = filterAssignee;
                currentPage = 1;
                applyFilterAndSort();
            });
        }

        // Create / Edit Task Form Submit
        const addForm = document.getElementById("createTaskForm");
        if (addForm) {
            addForm.addEventListener("submit", function(e) {
                e.preventDefault();
                saveTaskFormSubmit();
            });
        }

        // Event Delegation on Root
        root.addEventListener("click", function(e) {
            // Task View Details
            const trigger = e.target.closest(".task-view-trigger");
            if (trigger) {
                e.preventDefault();
                const taskId = trigger.dataset.taskId || trigger.getAttribute("data-task-id");
                window.tasksApp.openDetails(taskId, trigger);
                return;
            }

            // More Action Menu Buttons
            const menuToggleBtn = e.target.closest(".tasks-menu-toggle-btn");
            if (menuToggleBtn) {
                e.stopPropagation();
                const taskId = menuToggleBtn.dataset.taskId;
                window.tasksApp.toggleActionMenu(taskId, menuToggleBtn);
                return;
            }

            // Dropdown Menu Items
            const menuItem = e.target.closest(".tasks-item-menu-item");
            if (menuItem) {
                e.stopPropagation();
                const taskId = menuItem.dataset.taskId;
                const action = menuItem.dataset.action;
                window.tasksApp.executeTaskAction(taskId, action);
                window.tasksApp.closeAllMenus();
                return;
            }

            window.tasksApp.closeAllMenus();
        });

        // Window Resize & Scroll
        window.addEventListener("resize", function() {
            const menu = document.getElementById("tasksToolbarDropdown");
            if (menu && menu.classList.contains("show")) {
                positionToolbarMenu();
            }
            if (activeRowMenuTaskId && lastFocusedRowMenuBtn) {
                positionRowMenu(lastFocusedRowMenuBtn);
            }
        });

        window.addEventListener("scroll", function() {
            if (activeRowMenuTaskId) {
                closeTaskRowMenuInternal();
            }
        }, true);

        // Global Outside Click
        document.addEventListener("click", function(e) {
            const rowMenu = document.getElementById("taskRowMenuDropdown");
            if (rowMenu && rowMenu.classList.contains("show")) {
                const isTrigger = e.target.closest(".tasks-menu-toggle-btn");
                if (!rowMenu.contains(e.target) && !isTrigger) {
                    closeTaskRowMenuInternal();
                }
            }

            const popover = document.getElementById("tasksFilterPopover");
            const btn = document.getElementById("btnTaskFilter");
            if (popover && popover.classList.contains("show")) {
                if (!popover.contains(e.target) && (!btn || !btn.contains(e.target))) {
                    window.tasksApp.closeFilterPopover();
                }
            }
        });

        // Keyboard Shortcut ESC
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                const createDrawer = document.getElementById("tasksCreateDrawer");
                if (createDrawer && createDrawer.classList.contains("show")) {
                    window.tasksApp.closeCreateDrawer();
                    return;
                }
                const detailsDrawer = document.getElementById("taskDetailsDrawer");
                if (detailsDrawer && detailsDrawer.classList.contains("show")) {
                    window.tasksApp.closeDetails();
                    return;
                }
                const popover = document.getElementById("tasksFilterPopover");
                if (popover && popover.classList.contains("show")) {
                    window.tasksApp.closeFilterPopover();
                    return;
                }
                window.tasksApp.closeAllMenus();
            }
        });
    }

    // -------------------------------------------------------------
    // FILTERING & SORTING
    // -------------------------------------------------------------

    function applyFilterAndSort() {
        filteredTasks = allTasks.filter(t => {
            let matchesTab = true;
            if (currentTab === "My") {
                matchesTab = (t.assigned_to == currentUserId || (t.assignee && t.assignee.toLowerCase() === currentUserName.toLowerCase()));
            } else if (currentTab === "Overdue") {
                matchesTab = (t.group === "Overdue" && !t.done);
            } else if (currentTab === "Today") {
                matchesTab = (t.group === "Today" && !t.done);
            } else if (currentTab === "Upcoming") {
                matchesTab = (t.group === "Upcoming" && !t.done);
            } else if (currentTab === "Completed") {
                matchesTab = (t.done || t.status === "Completed");
            }

            const matchesType = (filterType === "All" || t.type === filterType);
            const matchesPriority = (filterPriority === "All" || t.priority === filterPriority);
            const matchesStatus = (filterStatus === "All" || t.status === filterStatus);
            
            let matchesAssignee = true;
            if (filterAssignee !== "All" && filterAssignee !== "") {
                if (!isNaN(filterAssignee)) {
                    matchesAssignee = (t.assigned_to == filterAssignee);
                } else {
                    matchesAssignee = (t.assignee && t.assignee.toLowerCase() === filterAssignee.toLowerCase());
                }
            }

            const matchesCompany = !filterCompany || (t.company && t.company.toLowerCase().includes(filterCompany));
            const matchesContact = !filterContact || (t.contact && t.contact.toLowerCase().includes(filterContact));

            const matchesQuery = !searchQuery || (
                (t.title && t.title.toLowerCase().includes(searchQuery)) ||
                (t.contact && t.contact.toLowerCase().includes(searchQuery)) ||
                (t.company && t.company.toLowerCase().includes(searchQuery)) ||
                (t.deal && t.deal.toLowerCase().includes(searchQuery)) ||
                (t.type && t.type.toLowerCase().includes(searchQuery)) ||
                (t.priority && t.priority.toLowerCase().includes(searchQuery)) ||
                (t.assignee && t.assignee.toLowerCase().includes(searchQuery)) ||
                (t.description && t.description.toLowerCase().includes(searchQuery))
            );

            return matchesTab && matchesType && matchesPriority && matchesStatus && matchesAssignee && matchesCompany && matchesContact && matchesQuery;
        });

        filteredTasks.sort((a, b) => {
            if (sortBy === "due-asc") return (a.done === b.done) ? 0 : a.done ? 1 : -1;
            if (sortBy === "due-desc") return (a.done === b.done) ? 0 : a.done ? -1 : 1;
            if (sortBy === "priority-desc") {
                const map = { Urgent: 4, High: 3, Medium: 2, Low: 1 };
                return (map[b.priority] || 0) - (map[a.priority] || 0);
            }
            if (sortBy === "alpha-asc") return (a.title || "").localeCompare(b.title || "");
            if (sortBy === "created-desc") return b.id - a.id;
            return 0;
        });

        updateHeaderCounts();
        renderActiveChips();
        renderTasks();
    }

    function updateHeaderCounts() {
        const totalBadge = document.getElementById("tasksTotalCountBadge");
        if (totalBadge) totalBadge.textContent = `${filteredTasks.length.toLocaleString()} task${filteredTasks.length === 1 ? '' : 's'}`;

        const myTasksVal = document.getElementById("summaryMyTasks");
        if (myTasksVal) myTasksVal.textContent = allTasks.filter(t => t.assigned_to == currentUserId || (t.assignee && t.assignee.toLowerCase() === currentUserName.toLowerCase())).length;

        const totalVal = document.getElementById("summaryTotalTasks");
        if (totalVal) totalVal.textContent = allTasks.length;

        const dueTodayVal = document.getElementById("summaryDueToday");
        if (dueTodayVal) dueTodayVal.textContent = allTasks.filter(t => t.group === "Today" && !t.done).length;

        const overdueVal = document.getElementById("summaryOverdue");
        if (overdueVal) overdueVal.textContent = allTasks.filter(t => t.group === "Overdue" && !t.done).length;

        const upcomingVal = document.getElementById("summaryUpcoming");
        if (upcomingVal) upcomingVal.textContent = allTasks.filter(t => t.group === "Upcoming" && !t.done).length;

        const completedVal = document.getElementById("summaryCompleted");
        if (completedVal) completedVal.textContent = allTasks.filter(t => t.done || t.status === "Completed").length;

        // Highlight active summary card
        document.querySelectorAll(".projects-summary-card").forEach(card => {
            const kpi = card.getAttribute("data-summary");
            if (kpi === currentTab) {
                card.classList.add("active");
            } else {
                card.classList.remove("active");
            }
        });
    }

    function renderActiveChips() {
        const container = document.getElementById("tasksActiveChips");
        if (!container) return;

        const chips = [];
        if (filterType !== "All") chips.push({ type: "type", label: `Type: ${filterType}` });
        if (filterPriority !== "All") chips.push({ type: "priority", label: `Priority: ${filterPriority}` });
        if (filterStatus !== "All") chips.push({ type: "status", label: `Status: ${filterStatus}` });
        if (filterAssignee !== "All" && filterAssignee !== "") {
            const u = (referenceOptions.users || []).find(user => user.id == filterAssignee);
            chips.push({ type: "assignee", label: `Owner: ${u ? u.name : filterAssignee}` });
        }
        if (filterCompany) chips.push({ type: "company", label: `Company: ${filterCompany}` });
        if (filterContact) chips.push({ type: "contact", label: `Contact: ${filterContact}` });

        if (chips.length === 0) {
            container.innerHTML = "";
            return;
        }

        container.innerHTML = `
            ${chips.map(c => `
                <div class="tasks-chip">
                    <span>${escapeHtml(c.label)}</span>
                    <button type="button" class="tasks-chip-close" onclick="window.tasksApp.removeFilterChip('${c.type}')">&times;</button>
                </div>
            `).join("")}
            <button type="button" class="btn-clear-filter-text" onclick="window.tasksApp.resetFilters()">Clear all filters</button>
        `;
    }

    // -------------------------------------------------------------
    // RENDERING
    // -------------------------------------------------------------

    function renderTasks() {
        const listView = document.getElementById("tasksListView");
        const boardView = document.getElementById("tasksBoardView");

        if (viewMode === "board") {
            if (listView) listView.style.display = "none";
            if (boardView) boardView.style.display = "flex";
            renderKanbanBoard();
        } else {
            if (boardView) boardView.style.display = "none";
            if (listView) listView.style.display = "block";
            renderGroupedList();
        }

        renderPagination();
        updateBulkToolbarState();
    }

    function renderGroupedList() {
        const container = document.getElementById("tasksListView");
        if (!container) return;

        if (filteredTasks.length === 0) {
            container.innerHTML = `
                <div class="tasks-empty-state" style="text-align: center; padding: 60px 20px; background: white; border: 1px solid var(--border-card); border-radius: var(--radius-lg); margin-top: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background-color: #F3F4F6; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto; color: var(--text-muted);">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h3 style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin: 0 0 6px 0;">No tasks found</h3>
                    <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 16px 0;">No tasks match your current filter or tab criteria.</p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.tasksApp.openCreateDrawer()">+ Create Task</button>
                </div>
            `;
            return;
        }

        const pagedTasks = getPagedTasks();
        const groups = [
            { key: "Overdue", label: "Overdue", color: "#DC2626", bg: "#FEF2F2", tasks: [] },
            { key: "Today", label: "Due Today", color: "#0284C7", bg: "#F0F9FF", tasks: [] },
            { key: "Upcoming", label: "Upcoming", color: "#D97706", bg: "#FEF3C7", tasks: [] },
            { key: "Completed", label: "Completed", color: "#059669", bg: "#ECFDF5", tasks: [] }
        ];

        pagedTasks.forEach(t => {
            const grp = groups.find(g => g.key === t.group) || groups[2];
            grp.tasks.push(t);
        });

        const activeGroups = groups.filter(g => g.tasks.length > 0);

        container.innerHTML = activeGroups.map(g => {
            const isCollapsed = collapsedGroups.has(g.key);
            return `
                <div class="tasks-group" data-group="${g.key}">
                    <div class="tasks-group-header" onclick="window.tasksApp.toggleGroupCollapse('${g.key}')">
                        <svg class="tasks-group-chevron ${isCollapsed ? 'collapsed' : ''}" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        <span class="tasks-group-title" style="color: ${g.color};">${g.label}</span>
                        <span class="tasks-group-count" style="background-color: ${g.bg}; color: ${g.color};">${g.tasks.length}</span>
                    </div>
                    <div class="tasks-group-items" style="${isCollapsed ? 'display: none;' : ''}">
                        ${g.tasks.map(t => renderTaskRowItem(t)).join("")}
                    </div>
                </div>
            `;
        }).join("");
    }

    function renderTaskRowItem(t) {
        const prioritySlug = (t.priority || "Medium").toLowerCase();
        const statusSlug = (t.status || "Not Started").toLowerCase().replace(/\s+/g, '-');
        const isSelected = selectedTaskIds.has(t.id);
        const codeDisplay = t.task_code || `TASK-${String(t.id).padStart(3, '0')}`;

        return `
            <div class="tasks-item ${t.done ? 'completed' : ''}" data-id="${t.id}">
                <div class="tasks-item-left">
                    <input type="checkbox" class="tasks-item-cb" ${t.done ? 'checked' : ''} onchange="window.tasksApp.toggleTaskCompletion(${t.id}, this.checked)" title="Mark Task Complete">
                    <div class="tasks-item-type-icon">${t.typeIcon || '📋'}</div>
                    <div class="tasks-item-content">
                        <div class="tasks-item-title task-view-trigger" data-task-id="${t.id}">
                            <span style="font-size:11px;font-weight:700;color:var(--primary);background:#EFF6FF;padding:2px 6px;border-radius:4px;margin-right:6px;">${codeDisplay}</span>
                            ${escapeHtml(t.title)}
                        </div>
                        <div class="tasks-item-meta">
                            <span>${escapeHtml(t.company || 'No Company')} • ${escapeHtml(t.contact || 'No Contact')}</span>
                            ${t.deal ? `<span>• Deal: <strong style="color: var(--text-heading);">${escapeHtml(t.deal)}</strong></span>` : ''}
                            <span>• Due: <strong>${escapeHtml(t.dueDate || 'No Due Date')}</strong></span>
                            ${t.clientVisible ? `<span style="color:#059669;font-weight:600;">• Client Visible</span>` : `<span style="color:#64748B;">• Internal Only</span>`}
                        </div>
                    </div>
                </div>
                <div class="tasks-item-right">
                    <span class="tasks-priority ${prioritySlug}">${escapeHtml(t.priority)}</span>
                    <span class="tasks-status ${statusSlug}">${escapeHtml(t.status)}</span>
                    <div class="avatar avatar-xs" style="background-color: ${t.assigneeColor || '#7C3AED'};" title="Assigned to ${escapeHtml(t.assignee)}">${escapeHtml(t.assigneeInitials || '??')}</div>
                    <button type="button" class="btn btn-ghost btn-xs tasks-menu-toggle-btn" data-task-id="${t.id}" title="Task Actions" style="padding: 4px; color: var(--text-muted);">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                </div>
            </div>
        `;
    }

    function renderKanbanBoard() {
        const container = document.getElementById("tasksBoardView");
        if (!container) return;

        const columns = [
            { id: "Not Started", title: "To Do", tasks: [] },
            { id: "In Progress", title: "In Progress", tasks: [] },
            { id: "Waiting", title: "Waiting", tasks: [] },
            { id: "Completed", title: "Completed", tasks: [] }
        ];

        filteredTasks.forEach(t => {
            const st = t.done ? "Completed" : (t.status || "Not Started");
            const col = columns.find(c => c.id === st) || columns[0];
            col.tasks.push(t);
        });

        container.innerHTML = columns.map(col => `
            <div class="tasks-column" data-status="${col.id}">
                <div class="tasks-column-header">
                    <div class="tasks-column-title">
                        ${escapeHtml(col.title)}
                        <span class="tasks-tab-count">${col.tasks.length}</span>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs" title="Add Task to ${col.title}" onclick="window.tasksApp.openCreateDrawer('${col.id}')">+</button>
                </div>
                <div class="tasks-column-body" data-status="${col.id}" ondragover="window.tasksApp.handleDragOver(event)" ondragleave="window.tasksApp.handleDragLeave(event)" ondrop="window.tasksApp.handleDrop(event, '${col.id}')">
                    ${col.tasks.length === 0 ? '<div style="font-size: 11px; color: var(--text-muted); text-align: center; padding: 20px 0;">No tasks</div>' : col.tasks.map(t => renderKanbanCard(t)).join("")}
                </div>
            </div>
        `).join("");
    }

    function renderKanbanCard(t) {
        const prioritySlug = (t.priority || "Medium").toLowerCase();
        const isOverdue = t.group === "Overdue" && !t.done;
        const curStatus = t.done ? "Completed" : (t.status || "Not Started");
        const codeDisplay = t.task_code || `TASK-${String(t.id).padStart(3, '0')}`;

        return `
            <div class="tasks-card" draggable="true" ondragstart="window.tasksApp.handleDragStart(event, '${t.id}')">
                <div class="tasks-card-top">
                    <span style="font-size: 12px;">${t.typeIcon || '📋'} <span style="font-size: 11px; font-weight: 500; color: var(--text-muted);">${escapeHtml(t.type)}</span></span>
                    <span class="tasks-priority ${prioritySlug}">${escapeHtml(t.priority)}</span>
                </div>
                <div class="tasks-card-title task-view-trigger" data-task-id="${t.id}">${escapeHtml(t.title)}</div>
                <div style="font-size: 11px; color: var(--text-muted); overflow-wrap: anywhere;">${escapeHtml(t.company || 'No Company')} • ${escapeHtml(t.contact || 'No Contact')}</div>
                <div class="tasks-card-meta">
                    <span class="${isOverdue ? 'tasks-card-overdue' : ''}">${isOverdue ? '⚠️ Overdue' : escapeHtml(t.dueDate || '')}</span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <select class="input-control input-sm tasks-card-status-select" title="Change status" style="font-size: 10.5px; height: 22px; padding: 0 4px; border-radius: 4px;" onchange="window.tasksApp.changeTaskStatus(${t.id}, this.value)">
                            <option value="Not Started" ${curStatus === 'Not Started' ? 'selected' : ''}>To Do</option>
                            <option value="In Progress" ${curStatus === 'In Progress' ? 'selected' : ''}>In Progress</option>
                            <option value="Waiting" ${curStatus === 'Waiting' ? 'selected' : ''}>Waiting</option>
                            <option value="Completed" ${curStatus === 'Completed' ? 'selected' : ''}>Completed</option>
                        </select>
                        <div class="avatar avatar-xs" style="background-color: ${t.assigneeColor || '#7C3AED'}; flex-shrink: 0;" title="${escapeHtml(t.assignee)}">${escapeHtml(t.assigneeInitials || '??')}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function getPagedTasks() {
        const start = (currentPage - 1) * rowsPerPage;
        return filteredTasks.slice(start, start + rowsPerPage);
    }

    function getPaginationPages(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if (current >= total - 3) return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        return [1, '...', current - 1, current, current + 1, '...', total];
    }

    function renderPagination() {
        const rangeEl = document.getElementById("pagingRange");
        const totalEl = document.getElementById("pagingTotal");
        const container = document.getElementById("paginationControls");
        if (!container) return;

        const totalItems = filteredTasks.length;
        const totalPages = Math.max(1, Math.ceil(totalItems / rowsPerPage));

        if (currentPage > totalPages) currentPage = totalPages;

        const startItem = totalItems === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
        const endItem = Math.min(currentPage * rowsPerPage, totalItems);

        if (rangeEl) rangeEl.textContent = `${startItem}–${endItem}`;
        if (totalEl) totalEl.textContent = totalItems.toLocaleString();

        const pages = getPaginationPages(currentPage, totalPages);

        container.innerHTML = `
            <button type="button" class="btn btn-secondary btn-xs" ${currentPage === 1 ? 'disabled' : ''} onclick="window.tasksApp.changePage(-1)">Previous</button>
            ${pages.map(p => {
                if (p === '...') return `<span class="pagination-dots" style="padding: 0 4px; color: var(--text-muted); font-size: 12px;">...</span>`;
                return `<button type="button" class="pagination-btn ${p === currentPage ? 'active' : ''}" onclick="window.tasksApp.goToPage(${p})">${p}</button>`;
            }).join("")}
            <button type="button" class="btn btn-secondary btn-xs" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.tasksApp.changePage(1)">Next</button>
        `;
    }

    function updateBulkToolbarState() {
        const toolbar = document.getElementById("tasksBulkToolbar");
        const countText = document.getElementById("tasksSelectedCountText");
        const count = selectedTaskIds.size;

        if (toolbar) {
            toolbar.style.display = (count > 0) ? "flex" : "none";
        }
        if (countText) {
            countText.textContent = count;
        }
    }

    function restoreKeyboardFocus() {
        if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
            lastFocusedElement.focus();
        }
    }

    // -------------------------------------------------------------
    // -------------------------------------------------------------
    // DETAILS DRAWER - UNIFIED DETAILS VIEW (REAL DB FIELDS ONLY)
    // -------------------------------------------------------------

    function formatDateTime(dateStr) {
        if (!dateStr || dateStr === '0000-00-00 00:00:00') return 'N/A';
        try {
            const d = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ', ' +
                   d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        } catch (e) {
            return dateStr;
        }
    }

    function renderTabPanelContent(tabName, container, t) {
        const codeDisplay = t.task_code || `TASK-${String(t.id).padStart(3, '0')}`;
        const priorityClass = (t.priority || "Medium").toLowerCase();
        const statusClass = (t.status || "Not Started").toLowerCase().replace(/\s+/g, '-');

        let contactDisplay = '<span style="color: var(--text-muted);">None</span>';
        if (t.contact) {
            let extra = '';
            if (t.contact_phone) extra = ` (${escapeHtml(t.contact_phone)})`;
            else if (t.contact_email) extra = ` (${escapeHtml(t.contact_email)})`;
            contactDisplay = `<span style="color: var(--primary); font-weight: 500;">${escapeHtml(t.contact)}${extra}</span>`;
        }

        let companyDisplay = t.company 
            ? `<span style="color: var(--primary); font-weight: 500;">${escapeHtml(t.company)}</span>` 
            : '<span style="color: var(--text-muted);">None</span>';

        let dealDisplay = t.deal 
            ? `<span style="font-weight: 500; color: var(--text-heading);">${escapeHtml(t.deal)}</span>` 
            : '<span style="color: var(--text-muted);">None</span>';

        let leadRow = '';
        if (t.related_lead) {
            leadRow = `<div class="task-view-info-row"><span class="contacts-info-label">Related Lead</span> <span class="contacts-info-val">${escapeHtml(t.related_lead)}</span></div>`;
        }
        let projectRow = '';
        if (t.related_project) {
            projectRow = `<div class="task-view-info-row"><span class="contacts-info-label">Related Project</span> <span class="contacts-info-val">${escapeHtml(t.related_project)}</span></div>`;
        }

        container.innerHTML = `
            <div class="task-view-info-card">
                <div class="task-view-info-title">Task Overview</div>
                <div class="task-view-info-row"><span class="contacts-info-label">Task ID</span> <span class="contacts-info-val" style="font-weight: 700; color: var(--primary);">${escapeHtml(codeDisplay)}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Task Title</span> <span class="contacts-info-val" style="font-weight: 600; color: var(--text-heading);">${escapeHtml(t.title || 'Untitled Task')}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Status</span> <span class="contacts-info-val"><span class="tasks-status ${statusClass}">${escapeHtml(t.status || 'Not Started')}</span></span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Priority</span> <span class="contacts-info-val"><span class="tasks-priority ${priorityClass}">${escapeHtml(t.priority || 'Medium')}</span></span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Task Type</span> <span class="contacts-info-val">${escapeHtml(t.typeIcon || '📋')} ${escapeHtml(t.type || 'General Task')}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Client Visibility</span> <span class="contacts-info-val">${t.clientVisible ? '<span style="color:#059669;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><span style="width:7px;height:7px;border-radius:50%;background:#10B981;display:inline-block;"></span> Client Visible</span>' : '<span style="color:#64748B;font-weight:500;">Internal Admin Only</span>'}</span></div>
            </div>

            <div class="task-view-info-card">
                <div class="task-view-info-title">Description</div>
                <p style="font-size: 12.5px; color: var(--text-body); line-height: 1.5; margin: 0; white-space: pre-wrap; word-break: break-word;">${t.description ? escapeHtml(t.description) : '<span style="color: var(--text-muted); font-style: italic;">No description provided.</span>'}</p>
            </div>

            <div class="task-view-info-card">
                <div class="task-view-info-title">Related Associations</div>
                <div class="task-view-info-row"><span class="contacts-info-label">Related Contact</span> <span class="contacts-info-val">${contactDisplay}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Related Company</span> <span class="contacts-info-val">${companyDisplay}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Related Deal</span> <span class="contacts-info-val">${dealDisplay}</span></div>
                ${leadRow}
                ${projectRow}
            </div>

            <div class="task-view-info-card">
                <div class="task-view-info-title">Schedule & Assignment</div>
                <div class="task-view-info-row"><span class="contacts-info-label">Due Date & Time</span> <span class="contacts-info-val">${escapeHtml(t.dueDate || 'No due date')}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Assigned Owner</span> <span class="contacts-info-val" style="font-weight: 500;">${escapeHtml(t.assignee || 'Unassigned')}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Created At</span> <span class="contacts-info-val">${escapeHtml(formatDateTime(t.created_at))}</span></div>
                <div class="task-view-info-row"><span class="contacts-info-label">Updated At</span> <span class="contacts-info-val">${escapeHtml(formatDateTime(t.updated_at))}</span></div>
            </div>
        `;
    }

    // -------------------------------------------------------------
    // FORM SUBMIT (CREATE / EDIT)
    // -------------------------------------------------------------

    async function saveTaskFormSubmit() {
        const editId = document.getElementById("editTaskId").value;
        const title = document.getElementById("addTaskTitle").value.trim();
        if (!title) {
            showTasksToast("Please provide a task title.");
            return;
        }

        const type = document.getElementById("addTaskType").value;
        const priority = document.getElementById("addTaskPriority").value;
        const desc = document.getElementById("addTaskDesc").value.trim();
        const contactId = document.getElementById("addTaskContact").value || null;
        const companyId = document.getElementById("addTaskCompany").value || null;
        const dealId = document.getElementById("addTaskDeal").value || null;
        const dueDate = document.getElementById("addTaskDueDate").value;
        const dueTime = document.getElementById("addTaskDueTime").value || "09:00";
        const assigneeId = document.getElementById("addTaskAssignee").value || currentUserId;
        const clientVisible = document.getElementById("addTaskClientVisible") ? (document.getElementById("addTaskClientVisible").checked ? 1 : 0) : 0;

        const submitBtn = document.getElementById("btnSaveTaskSubmit");
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";
        }

        const payload = {
            action: editId ? "update" : "create",
            id: editId ? parseInt(editId, 10) : undefined,
            title: title,
            type: type,
            priority: priority,
            description: desc,
            contact_id: contactId,
            company_id: companyId,
            deal_id: dealId,
            due_date: dueDate,
            due_time: dueTime,
            assigned_to: assigneeId,
            client_visible: clientVisible
        };

        try {
            const res = await fetch("api/tasks.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data && data.success) {
                showTasksToast(data.message || (editId ? "Task updated." : "Task created."));
                window.tasksApp.closeCreateDrawer();
                document.getElementById("createTaskForm").reset();
                // Reload list and summary KPIs from database
                await loadTasksFromApi();
                if (editId && activeTaskForDetails && activeTaskForDetails.id == editId) {
                    window.tasksApp.openDetails(editId);
                }
            } else {
                showTasksToast(data.message || "Failed to save task.");
            }
        } catch (e) {
            console.error("Save task error:", e);
            showTasksToast("Network or server error while saving task.");
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = "Save Changes";
            }
        }
    }

    // -------------------------------------------------------------
    // PUBLIC API (window.tasksApp)
    // -------------------------------------------------------------

    window.tasksApp = {
        exportCSV: function() {
            window.location.href = "api/tasks.php?action=export_csv";
        },

        openCreateDrawer: function(prefilledStatus) {
            lastFocusedElement = document.activeElement;
            const form = document.getElementById("createTaskForm");
            if (form) form.reset();

            document.getElementById("editTaskId").value = "";
            document.getElementById("createTaskModalTitle").textContent = "Create Task";
            document.getElementById("btnSaveTaskSubmit").textContent = "Save Changes";

            // Set default date to today
            const todayStr = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById("addTaskDueDate");
            if (dateInput) dateInput.value = todayStr;

            const timeInput = document.getElementById("addTaskDueTime");
            if (timeInput) timeInput.value = "09:00";

            const assigneeSelect = document.getElementById("addTaskAssignee");
            if (assigneeSelect) assigneeSelect.value = currentUserId;

            const clientVis = document.getElementById("addTaskClientVisible");
            if (clientVis) clientVis.checked = true;

            const drawer = document.getElementById("tasksCreateDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const panel = drawer.querySelector(".task-form-panel");
                if (panel) panel.setAttribute("aria-hidden", "false");
                const firstInput = document.getElementById("addTaskTitle");
                if (firstInput) firstInput.focus();
            }

            document.body.style.overflow = "hidden";
        },

        openEditFromDetails: function(taskIdParam) {
            const t = taskIdParam ? allTasks.find(item => item.id == taskIdParam) : activeTaskForDetails;
            if (!t) return;

            document.getElementById("editTaskId").value = t.id;
            const codeDisplay = t.task_code || `TASK-${String(t.id).padStart(3, '0')}`;
            document.getElementById("createTaskModalTitle").textContent = `Edit Task (${codeDisplay})`;
            document.getElementById("btnSaveTaskSubmit").textContent = "Save Changes";

            document.getElementById("addTaskTitle").value = t.title || "";
            document.getElementById("addTaskType").value = t.type || "General Task";
            document.getElementById("addTaskPriority").value = t.priority || "Medium";
            document.getElementById("addTaskDesc").value = t.description || "";

            // Ensure options exist if dynamically assigned
            if (t.company_id && !document.querySelector(`#addTaskCompany option[value="${t.company_id}"]`)) {
                const opt = document.createElement("option");
                opt.value = t.company_id;
                opt.textContent = t.company || `Company #${t.company_id}`;
                document.getElementById("addTaskCompany").appendChild(opt);
            }
            if (t.contact_id && !document.querySelector(`#addTaskContact option[value="${t.contact_id}"]`)) {
                const opt = document.createElement("option");
                opt.value = t.contact_id;
                opt.textContent = t.contact || `Contact #${t.contact_id}`;
                document.getElementById("addTaskContact").appendChild(opt);
            }
            if (t.deal_id && !document.querySelector(`#addTaskDeal option[value="${t.deal_id}"]`)) {
                const opt = document.createElement("option");
                opt.value = t.deal_id;
                opt.textContent = t.deal || `Deal #${t.deal_id}`;
                document.getElementById("addTaskDeal").appendChild(opt);
            }
            if (t.assigned_to && !document.querySelector(`#addTaskAssignee option[value="${t.assigned_to}"]`)) {
                const opt = document.createElement("option");
                opt.value = t.assigned_to;
                opt.textContent = t.assignee || `User #${t.assigned_to}`;
                document.getElementById("addTaskAssignee").appendChild(opt);
            }

            document.getElementById("addTaskContact").value = t.contact_id || "";
            document.getElementById("addTaskCompany").value = t.company_id || "";
            document.getElementById("addTaskDeal").value = t.deal_id || "";

            document.getElementById("addTaskDueDate").value = t.raw_due_date || "";
            document.getElementById("addTaskDueTime").value = t.raw_due_time || "09:00";

            document.getElementById("addTaskAssignee").value = t.assigned_to || currentUserId;
            if (document.getElementById("addTaskClientVisible")) {
                document.getElementById("addTaskClientVisible").checked = !!t.clientVisible;
            }

            const drawer = document.getElementById("tasksCreateDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const panel = drawer.querySelector(".task-form-panel");
                if (panel) panel.setAttribute("aria-hidden", "false");
                const firstInput = document.getElementById("addTaskTitle");
                if (firstInput) firstInput.focus();
            }

            document.body.style.overflow = "hidden";
        },

        closeCreateDrawer: function() {
            const drawer = document.getElementById("tasksCreateDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
                const panel = drawer.querySelector(".task-form-panel");
                if (panel) panel.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        openDetails: function(id, triggerElement) {
            if (triggerElement) {
                lastFocusedElement = triggerElement;
            } else {
                lastFocusedElement = document.activeElement;
            }

            const t = allTasks.find(item => item.id == id);
            if (!t) {
                showTasksToast("Unable to find task details.");
                return;
            }

            activeTaskForDetails = t;

            const drawer = document.getElementById("taskDetailsDrawer");
            const breadcrumbTitle = document.getElementById("taskBreadcrumbTitle");
            const titleEl = document.getElementById("tdTitle");
            const priorityEl = document.getElementById("tdPriority");
            const statusEl = document.getElementById("tdStatus");
            const metaEl = document.getElementById("tdMeta");
            const toggleBtn = document.getElementById("tdBtnToggleComplete");
            const statusSelect = document.getElementById("tdStatusSelect");

            const codeDisplay = t.task_code || `TASK-${String(t.id).padStart(3, '0')}`;
            if (breadcrumbTitle) breadcrumbTitle.textContent = `${codeDisplay} - ${t.title}`;
            if (titleEl) titleEl.textContent = t.title;
            if (priorityEl) {
                priorityEl.textContent = `${t.priority} Priority`;
                priorityEl.className = `tasks-priority ${(t.priority || "Medium").toLowerCase()}`;
            }
            if (statusEl) {
                statusEl.textContent = t.status;
                statusEl.className = `tasks-status ${(t.status || "Not Started").toLowerCase().replace(/\s+/g, '-')}`;
            }
            if (metaEl) metaEl.textContent = `Due: ${t.dueDate || 'None'} • Assigned to ${t.assignee || 'Unassigned'}`;
            if (toggleBtn) {
                const isDone = (t.done || t.status === "Completed");
                toggleBtn.textContent = isDone ? "Mark Incomplete" : "Mark Complete";
            }
            if (statusSelect) {
                statusSelect.value = t.status_raw || (t.status === "Completed" ? "completed" : "pending");
            }

            const container = document.getElementById("taskTabContent");
            if (container) {
                renderTabPanelContent("details", container, t);
            }

            // Fresh data fetch to ensure created_at, updated_at and relations are loaded
            fetch(`api/tasks.php?action=get&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && data.data && data.data.task) {
                        const fresh = data.data.task;
                        if (activeTaskForDetails && activeTaskForDetails.id == id) {
                            activeTaskForDetails = Object.assign({}, activeTaskForDetails, fresh);
                            if (breadcrumbTitle) breadcrumbTitle.textContent = `${fresh.task_code || codeDisplay} - ${fresh.title}`;
                            if (titleEl) titleEl.textContent = fresh.title;
                            if (priorityEl) {
                                priorityEl.textContent = `${fresh.priority} Priority`;
                                priorityEl.className = `tasks-priority ${(fresh.priority || "Medium").toLowerCase()}`;
                            }
                            if (statusEl) {
                                statusEl.textContent = fresh.status;
                                statusEl.className = `tasks-status ${(fresh.status || "Not Started").toLowerCase().replace(/\s+/g, '-')}`;
                            }
                            if (metaEl) metaEl.textContent = `Due: ${fresh.dueDate || 'None'} • Assigned to ${fresh.assignee || 'Unassigned'}`;
                            if (toggleBtn) {
                                const isDone = (fresh.done || fresh.status === "Completed");
                                toggleBtn.textContent = isDone ? "Mark Incomplete" : "Mark Complete";
                            }
                            if (statusSelect) {
                                statusSelect.value = fresh.status_raw || (fresh.status === "Completed" ? "completed" : "pending");
                            }
                            if (container) {
                                renderTabPanelContent("details", container, activeTaskForDetails);
                            }
                        }
                    }
                })
                .catch(() => {});

            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const panel = drawer.querySelector(".task-view-panel");
                if (panel) panel.setAttribute("aria-hidden", "false");
                const firstFocus = drawer.querySelector("button, input, select, textarea, [tabindex]:not([-1])");
                if (firstFocus) firstFocus.focus();
            }

            document.body.style.overflow = "hidden";
        },

        closeDetails: function() {
            const drawer = document.getElementById("taskDetailsDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
                const panel = drawer.querySelector(".task-view-panel");
                if (panel) panel.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        switchDetailsTab: function(tabName) {
            const container = document.getElementById("taskTabContent");
            if (container && activeTaskForDetails) {
                renderTabPanelContent("details", container, activeTaskForDetails);
            }
        },

        toggleDetailsComplete: async function() {
            if (!activeTaskForDetails) return;
            const taskId = activeTaskForDetails.id;
            try {
                const res = await fetch("api/tasks.php?action=toggle_complete", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: taskId })
                });
                const data = await res.json();
                if (data && data.success) {
                    showTasksToast(data.message || "Task updated successfully.", "success");
                    await loadTasksFromApi();
                    window.tasksApp.openDetails(taskId);
                } else {
                    showTasksToast((data && data.message) ? data.message : "Failed to update task.", "error");
                }
            } catch (e) {
                showTasksToast("Network error updating task completion.", "error");
            }
        },

        changeDetailsStatus: async function(newStatus) {
            if (!activeTaskForDetails || !newStatus) return;
            const taskId = activeTaskForDetails.id;
            try {
                const res = await fetch("api/tasks.php?action=change_status", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: taskId, status: newStatus })
                });
                const data = await res.json();
                if (data && data.success) {
                    showTasksToast(data.message || "Task status updated.", "success");
                    await loadTasksFromApi();
                    window.tasksApp.openDetails(taskId);
                } else {
                    showTasksToast((data && data.message) ? data.message : "Failed to change task status.", "error");
                }
            } catch (e) {
                showTasksToast("Network error updating task status.", "error");
            }
        },

        deleteCurrentTask: async function() {
            if (!activeTaskForDetails) return;
            const taskId = activeTaskForDetails.id;
            const taskTitle = activeTaskForDetails.title || `TASK-${taskId}`;

            if (!confirm(`Are you sure you want to permanently delete task "${taskTitle}"? This action cannot be undone.`)) {
                return;
            }

            try {
                const res = await fetch("api/tasks.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action: "delete", id: taskId })
                });
                const data = await res.json();
                if (data && data.success) {
                    selectedTaskIds.delete(taskId);
                    window.tasksApp.closeDetails();
                    await loadTasksFromApi();
                    showTasksToast(data.message || "Task permanently deleted.", "success");
                } else {
                    showTasksToast((data && data.message) ? data.message : "Failed to delete task.", "error");
                }
            } catch (e) {
                showTasksToast("Network error deleting task.", "error");
            }
        },

        toggleFilterPopover: function(e) {
            if (e && e.stopPropagation) e.stopPropagation();
            const popover = document.getElementById("tasksFilterPopover");
            if (popover) popover.classList.toggle("show");
        },

        closeFilterPopover: function() {
            const popover = document.getElementById("tasksFilterPopover");
            if (popover) popover.classList.remove("show");
        },

        applyFilterDrawer: function() {
            filterType = document.getElementById("filterTypeSelect").value;
            filterPriority = document.getElementById("filterPrioritySelect").value;
            filterStatus = document.getElementById("filterStatusSelect").value;
            filterAssignee = document.getElementById("filterAssigneeDrawerSelect").value;
            filterCompany = document.getElementById("filterCompanyInput").value.trim().toLowerCase();
            filterContact = document.getElementById("filterContactInput").value.trim().toLowerCase();

            window.tasksApp.closeFilterPopover();
            currentPage = 1;
            applyFilterAndSort();
        },

        resetFilters: function() {
            filterType = "All";
            filterPriority = "All";
            filterStatus = "All";
            filterAssignee = "All";
            filterCompany = "";
            filterContact = "";
            searchQuery = "";

            const searchInput = document.getElementById("tasksSearchInput");
            const clearSearchBtn = document.getElementById("tasksClearSearchBtn");
            if (searchInput) searchInput.value = "";
            if (clearSearchBtn) clearSearchBtn.style.display = "none";

            if (document.getElementById("filterTypeSelect")) document.getElementById("filterTypeSelect").value = "All";
            if (document.getElementById("filterPrioritySelect")) document.getElementById("filterPrioritySelect").value = "All";
            if (document.getElementById("filterStatusSelect")) document.getElementById("filterStatusSelect").value = "All";
            if (document.getElementById("filterAssigneeDrawerSelect")) document.getElementById("filterAssigneeDrawerSelect").value = "All";
            if (document.getElementById("filterCompanyInput")) document.getElementById("filterCompanyInput").value = "";
            if (document.getElementById("filterContactInput")) document.getElementById("filterContactInput").value = "";
            if (document.getElementById("tasksAssigneeSelect")) document.getElementById("tasksAssigneeSelect").value = "All";

            window.tasksApp.closeFilterPopover();
            currentPage = 1;
            applyFilterAndSort();
        },

        removeFilterChip: function(type) {
            if (type === "type") filterType = "All";
            if (type === "priority") filterPriority = "All";
            if (type === "status") filterStatus = "All";
            if (type === "assignee") {
                filterAssignee = "All";
                if (document.getElementById("tasksAssigneeSelect")) document.getElementById("tasksAssigneeSelect").value = "All";
            }
            if (type === "company") filterCompany = "";
            if (type === "contact") filterContact = "";

            applyFilterAndSort();
        },

        toggleTaskCompletion: async function(id, isDone) {
            const task = allTasks.find(t => t.id == id);
            if (!task) return;

            lastCompletedTaskBackup = { id: task.id, prevDone: task.done, prevStatus: task.status, prevGroup: task.group };
            task.done = isDone;
            task.status = isDone ? "Completed" : "In Progress";
            applyFilterAndSort();

            try {
                const res = await fetch("api/tasks.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action: "toggle_complete", id: id })
                });
                const data = await res.json();
                if (data && data.success) {
                    fetchSummaryKpis();
                    showTasksUndoToast(`Task marked ${isDone ? 'complete' : 'incomplete'}.`, async () => {
                        if (lastCompletedTaskBackup && lastCompletedTaskBackup.id == id) {
                            await fetch("api/tasks.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({ action: "toggle_complete", id: id })
                            });
                            await loadTasksFromApi();
                            showTasksToast("Action undone.");
                        }
                    });
                }
            } catch (e) {
                console.error("Toggle complete error:", e);
                // Revert
                task.done = lastCompletedTaskBackup.prevDone;
                task.status = lastCompletedTaskBackup.prevStatus;
                applyFilterAndSort();
                showTasksToast("Failed to update task status.");
            }
        },

        handleDragStart: function(e, taskId) {
            e.dataTransfer.setData("text/plain", taskId);
            e.target.classList.add("dragging");
        },

        handleDragOver: function(e) {
            e.preventDefault();
            e.currentTarget.classList.add("drag-over");
        },

        handleDragLeave: function(e) {
            e.currentTarget.classList.remove("drag-over");
        },

        handleDrop: async function(e, targetStatus) {
            e.preventDefault();
            e.currentTarget.classList.remove("drag-over");
            const taskId = e.dataTransfer.getData("text/plain");
            window.tasksApp.changeTaskStatus(taskId, targetStatus);
        },

        changeTaskStatus: async function(taskId, targetStatus) {
            const task = allTasks.find(t => t.id == taskId);
            if (!task) return;

            const prevStatus = task.status;
            task.status = targetStatus;
            task.done = (targetStatus === "Completed");
            applyFilterAndSort();

            try {
                const res = await fetch("api/tasks.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action: "change_status", id: taskId, status: targetStatus })
                });
                const data = await res.json();
                if (data && data.success) {
                    fetchSummaryKpis();
                    showTasksToast(`Task status updated to '${targetStatus}'.`);
                } else {
                    task.status = prevStatus;
                    task.done = (prevStatus === "Completed");
                    applyFilterAndSort();
                    showTasksToast(data.message || "Failed to update status.");
                }
            } catch (e) {
                task.status = prevStatus;
                task.done = (prevStatus === "Completed");
                applyFilterAndSort();
                showTasksToast("Network error updating status.");
            }
        },

        executeBulkAction: function(actionName) {
            const count = selectedTaskIds.size;
            if (count === 0) return;

            if (actionName === "complete") {
                this.submitBulkAction("complete");
            } else if (actionName === "status") {
                const countEl = document.getElementById("changeStatusCountText");
                if (countEl) countEl.textContent = count;
                this.openTaskModal("tasksChangeStatusModal");
            } else if (actionName === "assignee") {
                const countEl = document.getElementById("reassignCountText");
                if (countEl) countEl.textContent = count;
                this.openTaskModal("tasksReassignModal");
            } else if (actionName === "delete") {
                if (confirm(`Are you sure you want to delete ${count} selected tasks? This action cannot be undone.`)) {
                    this.submitBulkAction("delete");
                }
            } else {
                showTasksToast(`Bulk action '${actionName}' executed on ${count} tasks.`);
            }
        },

        submitBulkAction: async function(actionType, extraData = {}) {
            const ids = Array.from(selectedTaskIds);
            if (ids.length === 0) return;

            const payload = {
                action: "bulk",
                bulk_action: actionType,
                ids: ids,
                ...extraData
            };

            try {
                const res = await fetch("api/tasks.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data && data.success) {
                    selectedTaskIds.clear();
                    updateBulkToolbarState();
                    await loadTasksFromApi();
                    showTasksToast(data.message || "Bulk action completed.");
                } else {
                    showTasksToast(data.message || "Failed to execute bulk action.");
                }
            } catch (e) {
                showTasksToast("Network error executing bulk action.");
            }
        },

        submitChangeSelectedStatus: function() {
            const select = document.getElementById("modalTaskStatusSelect");
            if (!select) return;
            const newStatus = select.value;
            this.closeTaskModal("tasksChangeStatusModal");
            this.submitBulkAction("status", { new_status: newStatus });
        },

        submitReassignSelected: function() {
            const select = document.getElementById("modalTaskAssigneeSelect");
            if (!select) return;
            const newAssigneeId = select.value;
            if (!newAssigneeId) {
                showTasksToast("Please select an assignee.");
                return;
            }
            this.closeTaskModal("tasksReassignModal");
            this.submitBulkAction("assignee", { new_assignee: newAssigneeId });
        },

        deleteTask: function(taskId) {
            const task = allTasks.find(t => t.id == taskId);
            if (!task) return;

            if (confirm(`Are you sure you want to delete '${task.title}'? This action cannot be undone.`)) {
                fetch("api/tasks.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action: "delete", id: taskId })
                }).then(res => res.json()).then(data => {
                    if (data && data.success) {
                        selectedTaskIds.delete(taskId);
                        window.tasksApp.closeDetails();
                        loadTasksFromApi();
                        showTasksToast(data.message || "Task deleted.");
                    } else {
                        showTasksToast(data.message || "Failed to delete task.");
                    }
                }).catch(() => {
                    showTasksToast("Network error deleting task.");
                });
            }
        },

        duplicateTask: function(taskId) {
            const task = allTasks.find(t => t.id == taskId);
            if (!task) return;

            fetch("api/tasks.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action: "create",
                    title: `${task.title} (Copy)`,
                    type: task.type,
                    priority: task.priority,
                    description: task.description,
                    contact_id: task.contact_id,
                    company_id: task.company_id,
                    deal_id: task.deal_id,
                    due_date: task.raw_due_date,
                    due_time: task.raw_due_time || "09:00",
                    assigned_to: task.assigned_to,
                    client_visible: task.clientVisible ? 1 : 0
                })
            }).then(res => res.json()).then(data => {
                if (data && data.success) {
                    loadTasksFromApi();
                    showTasksToast("Task duplicated successfully.");
                } else {
                    showTasksToast("Failed to duplicate task.");
                }
            });
        },

        executeTaskAction: function(taskId, action) {
            if (action === "view-details") {
                this.openDetails(taskId);
            } else if (action === "edit") {
                this.openEditFromDetails(taskId);
            } else if (action === "complete") {
                const task = allTasks.find(t => t.id == taskId);
                if (task) this.toggleTaskCompletion(taskId, !task.done);
            } else if (action === "reschedule") {
                this.openEditFromDetails(taskId);
                setTimeout(() => {
                    const dueInput = document.getElementById("addTaskDueDate");
                    if (dueInput) dueInput.focus();
                }, 100);
            } else if (action === "change-assignee") {
                this.openEditFromDetails(taskId);
                setTimeout(() => {
                    const assInput = document.getElementById("addTaskAssignee");
                    if (assInput) assInput.focus();
                }, 100);
            } else if (action === "duplicate") {
                this.duplicateTask(taskId);
            } else if (action === "delete") {
                this.deleteTask(taskId);
            }
        },

        toggleActionMenu: function(taskId, btnElem, e) {
            if (e) e.stopPropagation();

            const dropdown = document.getElementById("taskRowMenuDropdown");
            if (!dropdown) return;

            if (activeRowMenuTaskId == taskId && dropdown.classList.contains("show")) {
                closeTaskRowMenuInternal();
                if (btnElem && typeof btnElem.focus === "function") btnElem.focus();
                return;
            }

            this.closeAllMenus();
            activeRowMenuTaskId = taskId;
            lastFocusedRowMenuBtn = btnElem;

            const task = allTasks.find(t => t.id == taskId);
            if (!task) return;

            dropdown.innerHTML = `
                <button type="button" class="tasks-item-menu-item" role="menuitem" onclick="window.tasksApp.onTaskRowAction('${taskId}', 'view-details', event)">👁️ View Details</button>
                <button type="button" class="tasks-item-menu-item" role="menuitem" onclick="window.tasksApp.onTaskRowAction('${taskId}', 'edit', event)">✏️ Edit Task</button>
                <button type="button" class="tasks-item-menu-item" role="menuitem" onclick="window.tasksApp.onTaskRowAction('${taskId}', 'complete', event)">${task.done ? '🔄 Mark Incomplete' : '✅ Mark Complete'}</button>
                <button type="button" class="tasks-item-menu-item" role="menuitem" onclick="window.tasksApp.onTaskRowAction('${taskId}', 'reschedule', event)">📅 Reschedule</button>
                <button type="button" class="tasks-item-menu-item" role="menuitem" onclick="window.tasksApp.onTaskRowAction('${taskId}', 'change-assignee', event)">👤 Change Assignee</button>
                <button type="button" class="tasks-item-menu-item" role="menuitem" onclick="window.tasksApp.onTaskRowAction('${taskId}', 'duplicate', event)">📋 Duplicate Task</button>
                <button type="button" class="tasks-item-menu-item danger" role="menuitem" onclick="window.tasksApp.onTaskRowAction('${taskId}', 'delete', event)">🗑️ Delete Task</button>
            `;

            dropdown.style.display = "block";
            positionRowMenu(btnElem);
            dropdown.classList.add("show");

            if (btnElem) btnElem.setAttribute("aria-expanded", "true");
        },

        onTaskRowAction: function(taskId, action, e) {
            if (e) e.stopPropagation();
            const btn = lastFocusedRowMenuBtn;
            closeTaskRowMenuInternal();
            this.executeTaskAction(taskId, action);
            if (btn && typeof btn.focus === "function") btn.focus();
        },

        closeAllMenus: function() {
            closeTaskRowMenuInternal();
            const toolbarMenu = document.getElementById("tasksToolbarDropdown");
            if (toolbarMenu) {
                toolbarMenu.classList.remove("show");
                toolbarMenu.style.display = "none";
            }
        },

        filterBySummary: function(summaryKey) {
            currentTab = summaryKey || "All";
            currentPage = 1;
            applyFilterAndSort();
        },

        setViewMode: function(mode) {
            viewMode = mode;
            try { localStorage.setItem(VIEW_MODE_KEY, mode); } catch (e) {}
            document.getElementById("btnViewList").classList.toggle("active", mode === "list");
            document.getElementById("btnViewBoard").classList.toggle("active", mode === "board");
            renderTasks();
        },

        clearSearch: function() {
            searchQuery = "";
            const searchInput = document.getElementById("tasksSearchInput");
            const clearSearchBtn = document.getElementById("tasksClearSearchBtn");
            if (searchInput) searchInput.value = "";
            if (clearSearchBtn) clearSearchBtn.style.display = "none";
            currentPage = 1;
            applyFilterAndSort();
        },

        goToPage: function(p) {
            currentPage = p;
            renderTasks();
        },

        changePage: function(delta) {
            currentPage += delta;
            renderTasks();
        },

        toggleGroupCollapse: function(groupName) {
            if (collapsedGroups.has(groupName)) {
                collapsedGroups.delete(groupName);
            } else {
                collapsedGroups.add(groupName);
            }
            renderGroupedList();
        },

        openTaskModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.add("show");
            modal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
            const firstFocus = modal.querySelector("button, select, input, [tabindex]:not([-1])");
            if (firstFocus) firstFocus.focus();
        },

        closeTaskModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove("show");
                modal.setAttribute("aria-hidden", "true");
            }
            const drawer = document.getElementById("taskDetailsDrawer");
            if (drawer && drawer.classList.contains("show")) {
                document.body.style.overflow = "hidden";
            } else {
                document.body.style.overflow = "";
            }
        },

        executeToolbarAction: function(action) {
            this.closeAllMenus();

            if (action === "refresh") {
                loadTasksFromApi(() => showTasksToast("Tasks refreshed."));
            } else if (action === "selectAllVisible") {
                selectedTaskIds.clear();
                filteredTasks.forEach(t => selectedTaskIds.add(t.id));
                updateBulkToolbarState();
                renderTasks();
            } else if (action === "clearSelection") {
                selectedTaskIds.clear();
                updateBulkToolbarState();
                renderTasks();
            } else if (action === "toggleGroupCollapse") {
                const allGroups = ["Overdue", "Today", "Upcoming", "Completed"];
                const areAllCollapsed = allGroups.every(g => collapsedGroups.has(g));
                if (areAllCollapsed) {
                    collapsedGroups.clear();
                } else {
                    allGroups.forEach(g => collapsedGroups.add(g));
                }
                renderTasks();
            } else if (action === "markSelectedComplete") {
                this.executeBulkAction("complete");
            } else if (action === "changeSelectedStatus") {
                this.executeBulkAction("status");
            } else if (action === "reassignSelected") {
                this.executeBulkAction("assignee");
            } else if (action === "toggleDensity") {
                currentDensity = (currentDensity === "comfortable") ? "compact" : "comfortable";
                try { localStorage.setItem(DENSITY_STORAGE_KEY, currentDensity); } catch (e) {}
                applyDensityUI();
            } else if (action === "resetTaskView") {
                this.resetFilters();
            } else if (action === "printList") {
                window.print();
            } else if (action === "exportCSV") {
                this.exportCSV();
            }
        },

        onTaskNoteSearchInput: function(val) {
            taskNoteSearchQuery = val || "";
            if (activeTaskForDetails) {
                loadTaskNotes(activeTaskForDetails.id);
            }
        },

        toggleTaskNoteComposer: function(show) {
            const comp = document.getElementById("taskNoteComposer");
            if (!comp) return;
            const isVisible = (comp.style.display !== "none");
            const shouldShow = (typeof show === "boolean") ? show : !isVisible;
            comp.style.display = shouldShow ? "block" : "none";
            if (shouldShow) {
                const ta = document.getElementById("taskNewNoteTextarea");
                if (ta) {
                    ta.value = "";
                    ta.focus();
                }
            }
        },

        saveTaskNote: async function(taskId) {
            const ta = document.getElementById("taskNewNoteTextarea");
            if (!ta) return;
            const content = ta.value.trim();
            if (!content) {
                showTasksToast("Note content cannot be empty.");
                return;
            }

            try {
                const res = await fetch("api/tasks.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action: "add_note", task_id: taskId, content: content })
                });
                const data = await res.json();
                if (data && data.success) {
                    this.toggleTaskNoteComposer(false);
                    loadTaskNotes(taskId);
                    showTasksToast("Note saved.");
                } else {
                    showTasksToast(data.message || "Failed to save note.");
                }
            } catch (e) {
                showTasksToast("Network error saving note.");
            }
        }
    };

    function showTasksUndoToast(msg, onUndoCallback) {
        const toast = document.createElement("div");
        toast.style.cssText = "position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background-color: #101828; color: #ffffff; padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 15px rgba(0,0,0,0.2); z-index: 200; display: flex; align-items: center; gap: 12px; transition: opacity 0.3s ease;";
        toast.innerHTML = `
            <span>${escapeHtml(msg)}</span>
            <button type="button" style="background: none; border: none; color: #60A5FA; font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;">Undo</button>
        `;
        const undoBtn = toast.querySelector("button");
        undoBtn.addEventListener("click", () => {
            if (typeof onUndoCallback === "function") onUndoCallback();
            toast.remove();
        });

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    window.showTasksToast = function(msg) {
        const toast = document.createElement("div");
        toast.style.cssText = "position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background-color: #101828; color: #ffffff; padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 15px rgba(0,0,0,0.2); z-index: 200; pointer-events: none; transition: opacity 0.3s ease;";
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    };
})();