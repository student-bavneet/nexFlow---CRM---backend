/**
 * NexFlow CRM — Admin Projects Controller JavaScript
 * Real MySQL Backend Integration via admin/api/projects.php
 * 100% Preservation of existing UI/UX, modals, drawers, columns, and layouts.
 * Strictly zero mock data, zero demo records, zero localStorage business data.
 */

(function () {
    const COLUMNS_KEY = "NexFlow_projects_columns_v1";

    let projects = [];
    let users = [];
    let companies = [];
    let availableCompanyDeals = [];
    let isFetchingDeals = false;
    let currentCompanyForDeals = "";
    let highlightedDealIndex = -1;
    let highlightedClientIndex = -1;
    let permissions = { can_create: true, can_edit: true, can_delete: true, can_export: true };
    let activeKpi = "total";
    let currentFilterStatus = "All";
    let currentFilterPriority = "All";
    let currentFilterManager = "All";
    let searchQuery = "";
    let activeProjectForDetails = null;
    let activeDrawerData = null;
    let currentProjectPage = 1;
    const itemsPerPage = 8;

    // Column Customization state
    const defaultColumnOrder = ['project', 'client', 'manager', 'status', 'priority', 'startDate', 'dueDate', 'progress', 'budget', 'actions'];
    const columnLabels = {
        project: "Project ID & Name",
        client: "Client / Company",
        manager: "Project Manager",
        status: "Status",
        priority: "Priority",
        startDate: "Start Date",
        dueDate: "Due Date",
        progress: "Progress",
        budget: "Budget",
        actions: "Actions"
    };

    let columnVisibility = {
        project: true, client: true, manager: true, status: true, priority: true,
        startDate: true, dueDate: true, progress: true, budget: true, actions: true
    };
    let columnOrder = [...defaultColumnOrder];

    // --------------------------------------------------
    // 1. BOOTSTRAP DATA FROM MYSQL
    // --------------------------------------------------
    function fetchProjectsBootstrap(callback) {
        fetch("api/projects.php?action=bootstrap")
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    projects = res.data.projects || [];
                    users = res.data.users || [];
                    companies = res.data.companies || [];
                    permissions = res.data.permissions || permissions;

                    // Update KPIs
                    const kpis = res.data.kpis || {};
                    updateKPIDisplay(kpis);

                    // Update manager dropdown options if needed
                    populateManagerDropdowns(users);

                    renderProjectsUI();

                    if (typeof callback === "function") callback();
                } else {
                    showToast(res.message || "Failed to load projects.");
                }
            })
            .catch(err => {
                console.error("Projects bootstrap error:", err);
                showToast("Network error loading projects.");
            });
    }

    function updateKPIDisplay(kpis) {
        const total = kpis.total || 0;
        const active = kpis.active || 0;
        const dueSoon = kpis.due_soon || 0;
        const completed = kpis.completed || 0;
        const overdue = kpis.overdue || 0;

        const elTotal = document.getElementById("kpiTotalProjects");
        const elActive = document.getElementById("kpiActiveProjects");
        const elDueSoon = document.getElementById("kpiDueSoonProjects");
        const elCompleted = document.getElementById("kpiCompletedProjects");
        const elOverdue = document.getElementById("kpiOverdueProjects");
        const elBadge = document.getElementById("projectsTotalCountBadge");

        if (elTotal) elTotal.textContent = total;
        if (elActive) elActive.textContent = active;
        if (elDueSoon) elDueSoon.textContent = dueSoon;
        if (elCompleted) elCompleted.textContent = completed;
        if (elOverdue) elOverdue.textContent = overdue;
        if (elBadge) elBadge.textContent = `${total} projects`;
    }

    function populateManagerDropdowns(userList) {
        const filterSelect = document.getElementById("projectsManagerFilter");
        const formSelect = document.getElementById("fieldProjectManager");

        if (filterSelect && userList.length > 0) {
            const currentVal = filterSelect.value;
            filterSelect.innerHTML = `<option value="All">All Managers</option>` +
                userList.map(u => `<option value="${escapeHtml(u.name)}">${escapeHtml(u.name)}</option>`).join("");
            filterSelect.value = currentVal;
        }

        if (formSelect && userList.length > 0) {
            const currentVal = formSelect.value;
            formSelect.innerHTML = `<option value="">Select Manager (Optional)</option>` +
                userList.map(u => `<option value="${escapeHtml(u.name)}" data-id="${u.id}">${escapeHtml(u.name)}</option>`).join("");
            formSelect.value = currentVal;
        }
    }

    // --------------------------------------------------
    // 2. COLUMN PREFERENCES (PRESERVED IN LOCALSTORAGE)
    // --------------------------------------------------
    function loadColumnsConfig() {
        try {
            const saved = localStorage.getItem(COLUMNS_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed.visibility) columnVisibility = { ...columnVisibility, ...parsed.visibility };
                if (Array.isArray(parsed.order) && parsed.order.length > 0) columnOrder = parsed.order;
            }
        } catch (e) {
            console.warn("Could not read columns config:", e);
        }
    }

    function saveColumnsConfig() {
        try {
            localStorage.setItem(COLUMNS_KEY, JSON.stringify({
                visibility: columnVisibility,
                order: columnOrder
            }));
        } catch (e) {
            console.warn("Could not save columns config:", e);
        }
    }

    // --------------------------------------------------
    // 3. FILTERING & SEARCH
    // --------------------------------------------------
    function getLocalDateString(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function isProjectActive(p) {
        const s = (p.status || "").toLowerCase();
        return s === "planning" || s === "in progress" || p.status === "Planning" || p.status === "In Progress";
    }

    function isProjectDueSoon(p) {
        if (p.status === "Completed" || !p.dueDate) return false;
        const now = new Date();
        const todayStr = getLocalDateString(now);
        const future14 = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 14);
        const future14Str = getLocalDateString(future14);
        return p.dueDate >= todayStr && p.dueDate <= future14Str;
    }

    function isProjectOverdue(p) {
        if (p.status === "Overdue") return true;
        if (p.status === "Completed" || !p.dueDate) return false;
        const now = new Date();
        const todayStr = getLocalDateString(now);
        return p.dueDate < todayStr;
    }

    function isProjectCompleted(p) {
        return p.status === "Completed";
    }

    function getFilteredProjects() {
        return projects.filter(p => {
            const q = searchQuery.toLowerCase().trim();
            const matchesSearch = !q ||
                (p.project_code && p.project_code.toLowerCase().includes(q)) ||
                (p.title && p.title.toLowerCase().includes(q)) ||
                (p.client && p.client.toLowerCase().includes(q)) ||
                (p.manager && p.manager.toLowerCase().includes(q));

            let matchesStatus = true;
            if (activeKpi) {
                if (activeKpi === "total") {
                    matchesStatus = true;
                } else if (activeKpi === "active") {
                    matchesStatus = isProjectActive(p);
                } else if (activeKpi === "due_soon") {
                    matchesStatus = isProjectDueSoon(p);
                } else if (activeKpi === "completed") {
                    matchesStatus = isProjectCompleted(p);
                } else if (activeKpi === "overdue") {
                    matchesStatus = isProjectOverdue(p);
                }
            } else {
                if (currentFilterStatus === "All") {
                    matchesStatus = true;
                } else if (currentFilterStatus === "Active") {
                    matchesStatus = isProjectActive(p);
                } else {
                    matchesStatus = (p.status === currentFilterStatus);
                }
            }

            const matchesPriority = (currentFilterPriority === "All") || p.priority === currentFilterPriority;
            const matchesManager = (currentFilterManager === "All") || p.manager === currentFilterManager;

            return matchesSearch && matchesStatus && matchesPriority && matchesManager;
        });
    }

    function formatINR(val) {
        if (val === null || val === undefined || isNaN(val)) return "₹0";
        return "₹" + Number(val).toLocaleString("en-IN", { maximumFractionDigits: 2 });
    }

    function getStatusBadgeHtml(status) {
        const s = status || "Planning";
        const slug = s.toLowerCase().replace(/\s+/g, '-');
        return `<span class="projects-badge ${slug}"><span class="projects-badge-dot"></span>${escapeHtml(s)}</span>`;
    }

    function getPriorityBadgeHtml(priority) {
        const p = priority || "Medium";
        const slug = p.toLowerCase();
        return `<span class="projects-priority-badge ${slug}">${escapeHtml(p)}</span>`;
    }

    function getProgressBarHtml(progress) {
        const p = parseInt(progress, 10) || 0;
        let color = "#2563EB";
        if (p === 100) color = "#047857";
        else if (p < 30) color = "#D97706";

        return `
            <div class="projects-progress-wrapper">
                <div class="projects-progress-track">
                    <div class="projects-progress-fill" style="width: ${p}%; background-color: ${color};"></div>
                </div>
                <span class="projects-progress-text">${p}%</span>
            </div>
        `;
    }

    // --------------------------------------------------
    // 4. TABLE RENDERING
    // --------------------------------------------------
    function renderTableHeader() {
        const thead = document.getElementById("projectsThead");
        if (!thead) return;

        let thCells = "";
        columnOrder.forEach(col => {
            if (!columnVisibility[col]) return;

            let alignStyle = "";
            let widthStyle = "";
            if (col === "progress") widthStyle = "width: 140px;";
            if (col === "budget") { alignStyle = "text-align: right;"; widthStyle = "width: 120px;"; }
            if (col === "actions") { alignStyle = "text-align: right;"; widthStyle = "width: 130px;"; }

            thCells += `<th style="${alignStyle} ${widthStyle}">${escapeHtml(columnLabels[col])}</th>`;
        });

        thead.innerHTML = `<tr>${thCells}</tr>`;
    }

    function renderTableBody() {
        const tbody = document.getElementById("projectsTbody");
        if (!tbody) return;

        const filtered = getFilteredProjects();
        const totalItems = filtered.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

        if (currentProjectPage > totalPages) currentProjectPage = totalPages;
        if (currentProjectPage < 1) currentProjectPage = 1;

        const startIndex = (currentProjectPage - 1) * itemsPerPage;
        const pageItems = filtered.slice(startIndex, startIndex + itemsPerPage);

        // Update paging indicators
        const rangeEl = document.getElementById("pagingRange");
        const totalEl = document.getElementById("pagingTotal");
        if (totalEl) totalEl.textContent = totalItems;
        if (rangeEl) {
            if (totalItems === 0) {
                rangeEl.textContent = "0–0";
            } else {
                const end = Math.min(startIndex + itemsPerPage, totalItems);
                rangeEl.textContent = `${startIndex + 1}–${end}`;
            }
        }

        if (pageItems.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align:center;padding:48px 20px;color:var(--text-muted);">
                        <div style="font-size:14px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">No projects found</div>
                        <div style="font-size:12.5px;">There are currently no projects matching your criteria.</div>
                    </td>
                </tr>
            `;
            renderProjectPaginationControls(0, 1);
            return;
        }

        let rowsHtml = "";
        pageItems.forEach(p => {
            let cellsHtml = "";
            columnOrder.forEach(col => {
                if (!columnVisibility[col]) return;

                switch (col) {
                    case "project":
                        cellsHtml += `
                            <td>
                                <div class="project-title-cell" onclick="window.projectsApp.openDetails(${p.id})">
                                    <span class="project-code-label">${escapeHtml(p.project_code)}</span>
                                    <span class="project-name-heading">${escapeHtml(p.title)}</span>
                                </div>
                            </td>
                        `;
                        break;
                    case "client":
                        cellsHtml += `<td><span style="font-weight:500;color:var(--text-heading);">${escapeHtml(p.client || '—')}</span></td>`;
                        break;
                    case "manager":
                        cellsHtml += `
                            <td>
                                <div class="project-manager-cell">
                                    <div class="project-manager-avatar" style="background-color: ${p.managerColor || '#2563EB'};">
                                        ${escapeHtml(p.managerInitials || 'SC')}
                                    </div>
                                    <span class="project-manager-name">${escapeHtml(p.manager || 'Unassigned')}</span>
                                </div>
                            </td>
                        `;
                        break;
                    case "status":
                        cellsHtml += `<td>${getStatusBadgeHtml(p.status)}</td>`;
                        break;
                    case "priority":
                        cellsHtml += `<td>${getPriorityBadgeHtml(p.priority)}</td>`;
                        break;
                    case "startDate":
                        cellsHtml += `<td style="font-size:12.5px;color:var(--text-body);">${escapeHtml(p.startDate || '—')}</td>`;
                        break;
                    case "dueDate":
                        cellsHtml += `<td style="font-size:12.5px;color:var(--text-body);font-weight:500;">${escapeHtml(p.dueDate || '—')}</td>`;
                        break;
                    case "progress":
                        cellsHtml += `<td>${getProgressBarHtml(p.progress)}</td>`;
                        break;
                    case "budget":
                        cellsHtml += `<td style="text-align:right;font-weight:600;color:var(--text-heading);font-size:13px;">${p.budget !== null ? formatINR(p.budget) : '—'}</td>`;
                        break;
                    case "actions":
                        cellsHtml += `
                            <td style="text-align:right;">
                                <div class="projects-actions-cell" style="justify-content: flex-end;">
                                    <button type="button" class="btn btn-secondary btn-xs" title="View Details" onclick="window.projectsApp.openDetails(${p.id})">View</button>
                                    <button type="button" class="btn btn-secondary btn-xs" title="Edit Project" onclick="window.projectsApp.openEditModal(${p.id})">Edit</button>
                                    <button type="button" class="btn-action-icon text-danger" title="Delete Project" onclick="window.projectsApp.deleteProject(${p.id})">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        `;
                        break;
                }
            });

            rowsHtml += `<tr>${cellsHtml}</tr>`;
        });

        tbody.innerHTML = rowsHtml;
        renderProjectPaginationControls(totalItems, totalPages);
    }

    function renderProjectPaginationControls(totalItems, totalPages) {
        const controls = document.getElementById("paginationControls");
        if (!controls) return;

        if (totalItems === 0) {
            controls.innerHTML = `
                <button class="pagination-btn" id="prevPageBtn" disabled>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="pagination-btn" id="nextPageBtn" disabled>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            `;
            return;
        }

        let html = `
            <button class="pagination-btn" id="prevPageBtn" ${currentProjectPage === 1 ? 'disabled' : ''} onclick="window.projectsApp.goToPage(${currentProjectPage - 1})">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        `;

        const pageNumbers = getPaginationPages(currentProjectPage, totalPages);
        pageNumbers.forEach(p => {
            if (p === '...') {
                html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
            } else {
                const isActive = (p === currentProjectPage);
                html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.projectsApp.goToPage(${p})">${p}</button>`;
            }
        });

        html += `
            <button class="pagination-btn" id="nextPageBtn" ${currentProjectPage === totalPages ? 'disabled' : ''} onclick="window.projectsApp.goToPage(${currentProjectPage + 1})">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        `;

        controls.innerHTML = html;
    }

    function getPaginationPages(current, total) {
        if (total <= 7) {
            const pages = [];
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }
        if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if (current >= total - 3) return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        return [1, '...', current - 1, current, current + 1, '...', total];
    }

    function renderProjectsUI() {
        renderTableHeader();
        renderTableBody();
    }

    // --------------------------------------------------
    // 5. DRAWER DETAILS & TABS
    // --------------------------------------------------
    function switchDetailsTab(tabName) {
        document.querySelectorAll(".projects-drawer-tab").forEach(tab => {
            const active = tab.dataset.tab === tabName;
            tab.classList.toggle("active", active);
        });

        const body = document.getElementById("projectsDrawerBody");
        if (!body || !activeDrawerData) return;

        const p = activeDrawerData.project;
        const tasks = activeDrawerData.tasks || [];
        const milestones = activeDrawerData.milestones || [];
        const files = activeDrawerData.files || [];
        const activity = activeDrawerData.activity || [];

        if (tabName === "overview") {
            body.innerHTML = `
                <div class="projects-spec-card">
                    <div class="projects-spec-title">Project Specifications</div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Project ID</span> <span class="projects-spec-value" style="color:var(--primary, #2563EB);font-weight:700;">${escapeHtml(p.project_code)}</span></div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Client / Organization</span> <span class="projects-spec-value">${escapeHtml(p.client || '—')}</span></div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Project Manager</span> <span class="projects-spec-value">${escapeHtml(p.manager || 'Unassigned')}</span></div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Start Date</span> <span class="projects-spec-value">${escapeHtml(p.startDate || '—')}</span></div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Target Due Date</span> <span class="projects-spec-value">${escapeHtml(p.dueDate || '—')}</span></div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Approved Budget</span> <span class="projects-spec-value" style="font-weight:700;">${p.budget !== null ? formatINR(p.budget) : '—'}</span></div>
                </div>

                <div class="projects-spec-card">
                    <div class="projects-spec-title">Description & Scope</div>
                    <p style="font-size:13px;color:var(--text-heading, #1E293B);line-height:1.6;margin:0;white-space:pre-wrap;">${escapeHtml(p.description || 'No detailed scope description provided.')}</p>
                </div>

                <div class="projects-spec-card">
                    <div class="projects-spec-title">Linked CRM Records</div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Related Deal</span> ${p.deal ? `<span class="projects-spec-value" style="color:var(--primary, #2563EB);font-weight:500;">${escapeHtml(p.deal)}</span>` : `<span class="projects-spec-value" style="color:var(--text-secondary, #64748B);font-weight:400;">None Linked</span>`}</div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Related Contract</span> ${p.contract ? `<span class="projects-spec-value" style="color:var(--primary, #2563EB);font-weight:500;">${escapeHtml(p.contract)}</span>` : `<span class="projects-spec-value" style="color:var(--text-secondary, #64748B);font-weight:400;">None Linked</span>`}</div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Related Proposal</span> ${p.proposal ? `<span class="projects-spec-value" style="color:var(--primary, #2563EB);font-weight:500;">${escapeHtml(p.proposal)}</span>` : `<span class="projects-spec-value" style="color:var(--text-secondary, #64748B);font-weight:400;">None Linked</span>`}</div>
                    <div class="projects-spec-row"><span class="projects-spec-label">Primary Invoice</span> ${p.invoice ? `<span class="projects-spec-value" style="color:var(--primary, #2563EB);font-weight:500;">${escapeHtml(p.invoice)}</span>` : `<span class="projects-spec-value" style="color:var(--text-secondary, #64748B);font-weight:400;">None Linked</span>`}</div>
                </div>
            `;
        } else if (tabName === "tasks") {
            let tasksListHtml = "";
            if (tasks.length === 0) {
                tasksListHtml = `<div style="font-size:12.5px;color:var(--text-secondary, #64748B);padding:12px 0;">No tasks created yet for this project.</div>`;
            } else {
                tasksListHtml = tasks.map(t => {
                    const isCompleted = t.status === 'completed';
                    return `
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <input type="checkbox" ${isCompleted ? 'checked' : ''} onchange="window.projectsApp.toggleTaskStatus(${t.id}, this.checked)" style="accent-color:#2563EB;cursor:pointer;width:15px;height:15px;">
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--text-heading, #0F172A);${isCompleted ? 'text-decoration:line-through;opacity:0.6;' : ''}">${escapeHtml(t.title)}</div>
                                    <div style="font-size:11.5px;color:var(--text-secondary, #64748B);margin-top:2px;">Due: ${escapeHtml(t.due_date ? t.due_date.substring(0, 10) : 'None')} • Assigned: ${escapeHtml(t.assignee_name || 'Unassigned')}</div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="projects-badge ${isCompleted ? 'completed' : 'in-progress'}"><span class="projects-badge-dot"></span>${isCompleted ? 'Completed' : 'Pending'}</span>
                                <button type="button" class="btn-action-icon text-danger" title="Delete Task" onclick="window.projectsApp.deleteTask(${t.id})">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                    `;
                }).join("");
            }

            body.innerHTML = `
                <div class="projects-spec-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <div class="projects-spec-title" style="margin:0;">Project Tasks Checklist</div>
                    </div>

                    <!-- Add Task Input -->
                    <form onsubmit="event.preventDefault(); window.projectsApp.submitNewTask();" style="display:flex;gap:8px;margin-bottom:14px;">
                        <input type="text" class="input-control input-sm" id="newDrawerTaskTitle" required placeholder="New task title..." style="flex:1;">
                        <input type="date" class="input-control input-sm" id="newDrawerTaskDue" style="width:130px;">
                        <button type="submit" class="btn btn-primary btn-sm">Add</button>
                    </form>

                    <div style="display:flex;flex-direction:column;gap:8px;">
                        ${tasksListHtml}
                    </div>
                </div>
            `;
        } else if (tabName === "milestones") {
            let milestonesHtml = "";
            if (milestones.length === 0) {
                milestonesHtml = `<div style="font-size:12.5px;color:var(--text-secondary, #64748B);padding:12px 0;">No milestones created yet for this project.</div>`;
            } else {
                milestonesHtml = milestones.map((m, idx) => {
                    const isCompleted = m.status === 'Completed';
                    const isInProgress = m.status === 'In Progress';
                    return `
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:24px;height:24px;border-radius:50%;background-color:${isCompleted ? '#ECFDF5' : (isInProgress ? '#EFF6FF' : '#F1F5F9')};color:${isCompleted ? '#047857' : (isInProgress ? '#2563EB' : '#64748B')};border:1px solid ${isCompleted ? '#A7F3D0' : (isInProgress ? '#BFDBFE' : '#E2E8F0')};display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">
                                    ${isCompleted ? '✓' : (idx + 1)}
                                </div>
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--text-heading, #0F172A);">${escapeHtml(m.name)}</div>
                                    <div style="font-size:11.5px;color:var(--text-secondary, #64748B);margin-top:2px;">Status: ${escapeHtml(m.status)}</div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <select class="input-control input-xs" style="width:110px;height:28px;font-size:11px;" onchange="window.projectsApp.updateMilestoneStatus(${m.id}, this.value)">
                                    <option value="Pending" ${m.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="In Progress" ${m.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="Completed" ${m.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                </select>
                                <button type="button" class="btn-action-icon text-danger" title="Delete Milestone" onclick="window.projectsApp.deleteMilestone(${m.id})">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                    `;
                }).join("");
            }

            body.innerHTML = `
                <div class="projects-spec-card">
                    <div class="projects-spec-title" style="margin-bottom:12px;">Project Milestones Track</div>

                    <!-- Add Milestone Input -->
                    <form onsubmit="event.preventDefault(); window.projectsApp.submitNewMilestone();" style="display:flex;gap:8px;margin-bottom:14px;">
                        <input type="text" class="input-control input-sm" id="newDrawerMilestoneName" required placeholder="New milestone phase name..." style="flex:1;">
                        <button type="submit" class="btn btn-primary btn-sm">Add Milestone</button>
                    </form>

                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${milestonesHtml}
                    </div>
                </div>
            `;
        } else if (tabName === "files") {
            let filesHtml = "";
            if (files.length === 0) {
                filesHtml = `<div style="font-size:12.5px;color:var(--text-secondary, #64748B);padding:12px 0;">No document attachments uploaded yet.</div>`;
            } else {
                filesHtml = files.map(f => `
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <svg width="20" height="20" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <div>
                                <div style="font-size:12.5px;font-weight:600;color:var(--text-heading, #0F172A);">${escapeHtml(f.file_name)}</div>
                                <div style="font-size:11px;color:var(--text-secondary, #64748B);margin-top:2px;">${escapeHtml(f.file_size)} • ${escapeHtml(f.created_at ? f.created_at.substring(0, 10) : '')}</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <a href="/nexFlow/${escapeHtml(f.file_path)}" target="_blank" download class="btn btn-secondary btn-xs" style="text-decoration:none;">Download</a>
                            <button type="button" class="btn-action-icon text-danger" title="Delete File" onclick="window.projectsApp.deleteProjectFile(${f.id}, '${f.source || 'project_file'}')">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </div>
                `).join("");
            }

            body.innerHTML = `
                <div class="projects-spec-card">
                    <div class="projects-spec-title" style="margin-bottom:12px;">Project Document Attachments</div>

                    <!-- Upload File Input -->
                    <div style="margin-bottom:14px;display:flex;gap:8px;align-items:center;">
                        <input type="file" id="drawerProjectFileInput" class="input-control input-sm" style="flex:1;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.projectsApp.uploadProjectFile()">Upload</button>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px;">
                        ${filesHtml}
                    </div>
                </div>
            `;
        } else if (tabName === "activity") {
            let actHtml = "";
            if (activity.length === 0) {
                actHtml = `<div style="font-size:12.5px;color:var(--text-secondary, #64748B);padding:12px 0;">No audit activity recorded yet.</div>`;
            } else {
                actHtml = activity.map(act => `
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <div style="width:8px;height:8px;border-radius:50%;background:#2563EB;margin-top:5px;flex-shrink:0;"></div>
                        <div>
                            <div style="font-size:12.5px;color:var(--text-heading, #0F172A);font-weight:500;">${escapeHtml(act.title || act.activity_type)}: ${escapeHtml(act.description || '')}</div>
                            <div style="font-size:11px;color:var(--text-secondary, #64748B);margin-top:2px;">${escapeHtml(act.author_name ? act.author_name + ' • ' : '')}${escapeHtml(act.created_at || '')}</div>
                        </div>
                    </div>
                `).join("");
            }

            body.innerHTML = `
                <div class="projects-spec-card">
                    <div class="projects-spec-title" style="margin-bottom:12px;">Audit & Activity Log</div>

                    <!-- Log Quick Note/Activity -->
                    <form onsubmit="event.preventDefault(); window.projectsApp.logProjectNote();" style="display:flex;gap:8px;margin-bottom:14px;">
                        <input type="text" class="input-control input-sm" id="newDrawerActivityText" required placeholder="Log an update or note..." style="flex:1;">
                        <button type="submit" class="btn btn-secondary btn-sm">Log Note</button>
                    </form>

                    <div style="display:flex;flex-direction:column;gap:12px;margin-top:8px;">
                        ${actHtml}
                    </div>
                </div>
            `;
        }
    }

    // --------------------------------------------------
    // 5.5 CLIENT & DEAL AUTOCOMPLETE CONTROLLER
    // --------------------------------------------------
    function fetchDealsForCompany(companyName, callback) {
        companyName = (companyName || "").trim();
        if (!companyName) {
            availableCompanyDeals = [];
            currentCompanyForDeals = "";
            if (typeof callback === "function") callback([]);
            return;
        }

        if (currentCompanyForDeals.toLowerCase() === companyName.toLowerCase() && availableCompanyDeals.length > 0) {
            if (typeof callback === "function") callback(availableCompanyDeals);
            return;
        }

        isFetchingDeals = true;
        currentCompanyForDeals = companyName;

        fetch(`api/projects.php?action=get_company_deals&company=${encodeURIComponent(companyName)}`)
            .then(r => r.json())
            .then(res => {
                isFetchingDeals = false;
                if (res.success && Array.isArray(res.data?.deals)) {
                    availableCompanyDeals = res.data.deals;
                } else {
                    availableCompanyDeals = [];
                }
                if (typeof callback === "function") callback(availableCompanyDeals);
            })
            .catch(err => {
                console.error("Error fetching deals for company:", err);
                isFetchingDeals = false;
                availableCompanyDeals = [];
                if (typeof callback === "function") callback([]);
            });
    }

    function setupProjectFormAutocomplete() {
        const clientInput = document.getElementById("fieldProjectClient");
        const clientDropdown = document.getElementById("projectClientDropdown");
        const dealSearchInput = document.getElementById("fieldProjectDealSearch");
        const dealHiddenInput = document.getElementById("fieldProjectDeal");
        const dealClearBtn = document.getElementById("btnClearProjectDeal");
        const dealDropdown = document.getElementById("projectDealDropdown");

        if (!clientInput || !dealSearchInput) return;

        // 1. Client autocomplete
        function closeClientDropdown() {
            if (clientDropdown) {
                clientDropdown.style.display = "none";
                clientDropdown.innerHTML = "";
                highlightedClientIndex = -1;
            }
        }

        function renderClientSuggestions(q) {
            if (!clientDropdown) return;
            const query = (q || "").toLowerCase().trim();
            const filtered = query
                ? companies.filter(c => c.name && c.name.toLowerCase().includes(query))
                : companies.slice(0, 8);

            if (filtered.length === 0) {
                closeClientDropdown();
                return;
            }

            clientDropdown.innerHTML = filtered.map((c, i) => `
                <div class="nexflow-autocomplete-item" data-name="${escapeHtml(c.name)}" data-index="${i}">
                    <div class="item-primary">${escapeHtml(c.name)}</div>
                </div>
            `).join("");

            clientDropdown.style.display = "block";
            highlightedClientIndex = -1;
        }

        clientInput.addEventListener("focus", function () {
            renderClientSuggestions(this.value);
        });

        clientInput.addEventListener("input", function () {
            renderClientSuggestions(this.value);
            onClientChange(this.value);
        });

        clientInput.addEventListener("keydown", function (e) {
            if (!clientDropdown || clientDropdown.style.display === "none") return;
            const items = clientDropdown.querySelectorAll(".nexflow-autocomplete-item");
            if (items.length === 0) return;

            if (e.key === "ArrowDown") {
                e.preventDefault();
                highlightedClientIndex = (highlightedClientIndex + 1) % items.length;
                updateClientHighlight(items);
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                highlightedClientIndex = (highlightedClientIndex - 1 + items.length) % items.length;
                updateClientHighlight(items);
            } else if (e.key === "Enter") {
                if (highlightedClientIndex >= 0 && highlightedClientIndex < items.length) {
                    e.preventDefault();
                    const name = items[highlightedClientIndex].getAttribute("data-name");
                    clientInput.value = name;
                    closeClientDropdown();
                    onClientChange(name);
                }
            } else if (e.key === "Escape") {
                closeClientDropdown();
            }
        });

        function updateClientHighlight(items) {
            items.forEach((item, idx) => {
                if (idx === highlightedClientIndex) {
                    item.classList.add("active");
                    item.scrollIntoView({ block: "nearest" });
                } else {
                    item.classList.remove("active");
                }
            });
        }

        if (clientDropdown) {
            clientDropdown.addEventListener("click", function (e) {
                const item = e.target.closest(".nexflow-autocomplete-item");
                if (!item) return;
                const name = item.getAttribute("data-name");
                if (name) {
                    clientInput.value = name;
                    closeClientDropdown();
                    onClientChange(name);
                }
            });
        }

        function onClientChange(clientName) {
            const comp = (clientName || "").trim();
            // Check if currently selected deal still belongs to this company
            if (dealHiddenInput && dealHiddenInput.value) {
                const currentDeal = availableCompanyDeals.find(d => String(d.id) === String(dealHiddenInput.value));
                if (!currentDeal || currentDeal.company.toLowerCase() !== comp.toLowerCase()) {
                    clearDeal();
                }
            }
            fetchDealsForCompany(comp);
        }

        // 2. Deal autocomplete
        function closeDealDropdown() {
            if (dealDropdown) {
                dealDropdown.style.display = "none";
                dealDropdown.innerHTML = "";
                highlightedDealIndex = -1;
            }
        }

        function renderDealSuggestions(filterText = "") {
            if (!dealDropdown) return;
            const company = clientInput.value.trim();
            if (!company) {
                dealDropdown.innerHTML = `<div style="padding:10px 12px;font-size:12.5px;color:var(--text-muted,#64748B);">Please enter a Client / Company first</div>`;
                dealDropdown.style.display = "block";
                return;
            }

            if (isFetchingDeals) {
                dealDropdown.innerHTML = `<div style="padding:10px 12px;font-size:12.5px;color:var(--text-muted,#64748B);">Loading deals for ${escapeHtml(company)}...</div>`;
                dealDropdown.style.display = "block";
                return;
            }

            if (availableCompanyDeals.length === 0) {
                dealDropdown.innerHTML = `<div style="padding:10px 12px;font-size:12.5px;color:var(--text-muted,#64748B);">No deals found for ${escapeHtml(company)}</div>`;
                dealDropdown.style.display = "block";
                return;
            }

            const q = (filterText || "").toLowerCase().trim();
            const filtered = q
                ? availableCompanyDeals.filter(d => d.name.toLowerCase().includes(q) || (d.deal_code && d.deal_code.toLowerCase().includes(q)))
                : availableCompanyDeals;

            if (filtered.length === 0) {
                dealDropdown.innerHTML = `<div style="padding:10px 12px;font-size:12.5px;color:var(--text-muted,#64748B);">No matching deals found</div>`;
                dealDropdown.style.display = "block";
                return;
            }

            dealDropdown.innerHTML = filtered.map((d, i) => {
                const metaParts = [];
                if (d.deal_code) metaParts.push(escapeHtml(d.deal_code));
                if (d.value !== null && d.value !== undefined) metaParts.push(formatINR(d.value));
                if (d.stage) metaParts.push(escapeHtml(d.stage));
                const metaStr = metaParts.join(" • ");

                return `
                    <div class="nexflow-autocomplete-item" data-deal-id="${d.id}" data-index="${i}">
                        <div class="item-primary">${escapeHtml(d.name)}</div>
                        ${metaStr ? `<div class="item-secondary">${metaStr}</div>` : ''}
                    </div>
                `;
            }).join("");

            dealDropdown.style.display = "block";
            highlightedDealIndex = -1;
        }

        dealSearchInput.addEventListener("focus", function () {
            const company = clientInput.value.trim();
            if (company && currentCompanyForDeals.toLowerCase() !== company.toLowerCase()) {
                fetchDealsForCompany(company, () => renderDealSuggestions(this.value));
            } else {
                renderDealSuggestions(this.value);
            }
        });

        dealSearchInput.addEventListener("click", function () {
            if (!dealDropdown || dealDropdown.style.display === "none") {
                const company = clientInput.value.trim();
                if (company && currentCompanyForDeals.toLowerCase() !== company.toLowerCase()) {
                    fetchDealsForCompany(company, () => renderDealSuggestions(this.value));
                } else {
                    renderDealSuggestions(this.value);
                }
            }
        });

        dealSearchInput.addEventListener("input", function () {
            renderDealSuggestions(this.value);
        });

        dealSearchInput.addEventListener("keydown", function (e) {
            if (!dealDropdown || dealDropdown.style.display === "none") return;
            const items = dealDropdown.querySelectorAll(".nexflow-autocomplete-item");
            if (items.length === 0) return;

            if (e.key === "ArrowDown") {
                e.preventDefault();
                highlightedDealIndex = (highlightedDealIndex + 1) % items.length;
                updateDealHighlight(items);
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                highlightedDealIndex = (highlightedDealIndex - 1 + items.length) % items.length;
                updateDealHighlight(items);
            } else if (e.key === "Enter") {
                if (highlightedDealIndex >= 0 && highlightedDealIndex < items.length) {
                    e.preventDefault();
                    const dealId = parseInt(items[highlightedDealIndex].getAttribute("data-deal-id"), 10);
                    const deal = availableCompanyDeals.find(d => d.id === dealId);
                    if (deal) selectDeal(deal);
                }
            } else if (e.key === "Escape") {
                closeDealDropdown();
            }
        });

        function updateDealHighlight(items) {
            items.forEach((item, idx) => {
                if (idx === highlightedDealIndex) {
                    item.classList.add("active");
                    item.scrollIntoView({ block: "nearest" });
                } else {
                    item.classList.remove("active");
                }
            });
        }

        if (dealDropdown) {
            dealDropdown.addEventListener("click", function (e) {
                const item = e.target.closest(".nexflow-autocomplete-item");
                if (!item) return;
                const dealId = parseInt(item.getAttribute("data-deal-id"), 10);
                const deal = availableCompanyDeals.find(d => d.id === dealId);
                if (deal) selectDeal(deal);
            });
        }

        function selectDeal(d) {
            dealHiddenInput.value = d.id;
            dealSearchInput.value = d.display || `${d.name} (${d.deal_code})`;
            if (dealClearBtn) dealClearBtn.style.display = "block";
            closeDealDropdown();
        }

        function clearDeal() {
            dealHiddenInput.value = "";
            dealSearchInput.value = "";
            if (dealClearBtn) dealClearBtn.style.display = "none";
            closeDealDropdown();
        }

        if (dealClearBtn) {
            dealClearBtn.addEventListener("click", function (e) {
                e.stopPropagation();
                clearDeal();
            });
        }

        // Global click-outside to close dropdowns
        document.addEventListener("click", function (e) {
            if (clientDropdown && clientDropdown.style.display !== "none") {
                const clientWrap = clientInput.closest("div");
                if (clientWrap && !clientWrap.contains(e.target)) {
                    closeClientDropdown();
                }
            }

            if (dealDropdown && dealDropdown.style.display !== "none") {
                const dealWrap = document.getElementById("projectDealGroup");
                if (dealWrap && !dealWrap.contains(e.target)) {
                    closeDealDropdown();
                }
            }
        });
    }

    // --------------------------------------------------
    // 6. PUBLIC CONTROLLER INTERFACE
    // --------------------------------------------------
    window.projectsApp = {
        init: function () {
            loadColumnsConfig();
            fetchProjectsBootstrap();
            setupProjectFormAutocomplete();
        },

        goToPage: function (p) {
            currentProjectPage = p;
            renderProjectsUI();
        },

        filterByKPI: function (kpiType) {
            let key = (kpiType || "").toLowerCase().replace(/\s+/g, '_');
            if (key === "all" || key === "total" || key === "total_projects") key = "total";
            else if (key === "active" || key === "active_projects" || key === "in_progress") key = "active";
            else if (key === "due_soon" || key === "planning") key = "due_soon";
            else if (key === "completed") key = "completed";
            else if (key === "overdue") key = "overdue";
            else key = "total";

            activeKpi = key;
            currentFilterStatus = "All";
            const select = document.getElementById("projectsStatusFilter");
            if (select) select.value = "All";

            currentProjectPage = 1;

            // Synchronize active KPI card highlight (only one active at a time)
            document.querySelectorAll(".projects-summary-card").forEach(c => c.classList.remove("active"));
            if (activeKpi === "total") document.getElementById("kpiCardAll")?.classList.add("active");
            else if (activeKpi === "active") document.getElementById("kpiCardInProgress")?.classList.add("active");
            else if (activeKpi === "due_soon") document.getElementById("kpiCardDueSoon")?.classList.add("active");
            else if (activeKpi === "completed") document.getElementById("kpiCardCompleted")?.classList.add("active");
            else if (activeKpi === "overdue") document.getElementById("kpiCardOverdue")?.classList.add("active");

            renderTableBody();
        },

        onSearchInput: function (val) {
            searchQuery = val || "";
            currentProjectPage = 1;
            const clearBtn = document.getElementById("projectsClearSearchBtn");
            if (clearBtn) clearBtn.style.display = searchQuery ? "block" : "none";
            renderTableBody();
        },

        clearSearch: function () {
            searchQuery = "";
            currentProjectPage = 1;
            const input = document.getElementById("projectsSearchInput");
            const clearBtn = document.getElementById("projectsClearSearchBtn");
            if (input) input.value = "";
            if (clearBtn) clearBtn.style.display = "none";
            renderTableBody();
        },

        onStatusFilterChange: function () {
            currentFilterStatus = document.getElementById("projectsStatusFilter")?.value || "All";
            activeKpi = null; // Manual status filter MUST clear KPI filter

            // Clear active KPI selection
            document.querySelectorAll(".projects-summary-card").forEach(c => c.classList.remove("active"));

            currentProjectPage = 1;
            renderTableBody();
        },

        onFilterChange: function () {
            const select = document.getElementById("projectsStatusFilter");
            if (select && select.value !== currentFilterStatus) {
                currentFilterStatus = select.value;
                activeKpi = null;
                document.querySelectorAll(".projects-summary-card").forEach(c => c.classList.remove("active"));
            }

            currentFilterPriority = document.getElementById("projectsPriorityFilter")?.value || "All";
            currentFilterManager = document.getElementById("projectsManagerFilter")?.value || "All";
            currentProjectPage = 1;
            renderTableBody();
        },

        openDetails: function (id) {
            fetch(`api/projects.php?action=project_drawer&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        activeDrawerData = res.data;
                        const p = res.data.project;
                        activeProjectForDetails = p;

                        document.getElementById("cpdTitle").textContent = `${p.project_code} — ${p.title}`;
                        document.getElementById("cpdStatusBadge").innerHTML = getStatusBadgeHtml(p.status);
                        document.getElementById("cpdPriorityBadge").innerHTML = getPriorityBadgeHtml(p.priority);

                        switchDetailsTab("overview");

                        const drawer = document.getElementById("projectsDetailsDrawer");
                        if (drawer) drawer.classList.add("show");
                        document.body.style.overflow = "hidden";
                    } else {
                        showToast(res.message || "Could not load project details.");
                    }
                })
                .catch(err => {
                    console.error("Open details error:", err);
                    showToast("Failed to fetch project details.");
                });
        },

        closeDetails: function () {
            const drawer = document.getElementById("projectsDetailsDrawer");
            if (drawer) drawer.classList.remove("show");
            document.body.style.overflow = "";
        },

        switchDetailsTab: switchDetailsTab,

        openCreateModal: function () {
            const form = document.getElementById("projectForm");
            if (form) form.reset();
            document.getElementById("editProjectId").value = "";
            document.getElementById("projectModalTitle").textContent = "Create Project";
            document.getElementById("btnSaveProjectSubmit").textContent = "Save Project";

            // Clear values explicitly
            document.getElementById("fieldProjectStartDate").value = "";
            document.getElementById("fieldProjectDueDate").value = "";
            document.getElementById("fieldProjectBudget").value = "";
            document.getElementById("fieldProjectProgress").value = "";

            // Clear deal and autocomplete fields
            const dealSearch = document.getElementById("fieldProjectDealSearch");
            const dealHidden = document.getElementById("fieldProjectDeal");
            const dealClear = document.getElementById("btnClearProjectDeal");
            const dealDrop = document.getElementById("projectDealDropdown");
            const clientDrop = document.getElementById("projectClientDropdown");

            if (dealSearch) dealSearch.value = "";
            if (dealHidden) dealHidden.value = "";
            if (dealClear) dealClear.style.display = "none";
            if (dealDrop) dealDrop.style.display = "none";
            if (clientDrop) clientDrop.style.display = "none";
            availableCompanyDeals = [];
            currentCompanyForDeals = "";

            const drawer = document.getElementById("projectFormDrawer");
            if (drawer) drawer.classList.add("show");
            document.body.style.overflow = "hidden";
        },

        openEditModal: function (id) {
            const p = projects.find(item => item.id === id);
            if (!p) return;

            document.getElementById("editProjectId").value = p.id;
            document.getElementById("projectModalTitle").textContent = `Edit Project (${p.project_code})`;
            document.getElementById("btnSaveProjectSubmit").textContent = "Update Project";

            document.getElementById("fieldProjectTitle").value = p.title;
            document.getElementById("fieldProjectClient").value = p.client;
            
            // Set manager
            const mgrSelect = document.getElementById("fieldProjectManager");
            if (mgrSelect) {
                if (p.manager_id) {
                    mgrSelect.value = p.manager;
                } else {
                    mgrSelect.value = "";
                }
            }

            // Preselect Deal if present
            const dealSearch = document.getElementById("fieldProjectDealSearch");
            const dealHidden = document.getElementById("fieldProjectDeal");
            const dealClear = document.getElementById("btnClearProjectDeal");
            const dealDrop = document.getElementById("projectDealDropdown");
            const clientDrop = document.getElementById("projectClientDropdown");

            if (dealDrop) dealDrop.style.display = "none";
            if (clientDrop) clientDrop.style.display = "none";

            if (p.deal_id && p.deal) {
                if (dealSearch) dealSearch.value = p.deal;
                if (dealHidden) dealHidden.value = p.deal_id;
                if (dealClear) dealClear.style.display = "block";
            } else {
                if (dealSearch) dealSearch.value = "";
                if (dealHidden) dealHidden.value = "";
                if (dealClear) dealClear.style.display = "none";
            }

            // Pre-fetch deals for the client in background
            if (p.client) {
                fetchDealsForCompany(p.client);
            } else {
                availableCompanyDeals = [];
                currentCompanyForDeals = "";
            }

            document.getElementById("fieldProjectStatus").value = p.status || "Planning";
            document.getElementById("fieldProjectPriority").value = p.priority || "Medium";
            document.getElementById("fieldProjectStartDate").value = p.startDate || "";
            document.getElementById("fieldProjectDueDate").value = p.dueDate || "";
            document.getElementById("fieldProjectBudget").value = p.budget !== null ? p.budget : "";
            document.getElementById("fieldProjectProgress").value = p.progress !== null ? p.progress : "";
            document.getElementById("fieldProjectDesc").value = p.description || "";

            const drawer = document.getElementById("projectFormDrawer");
            if (drawer) drawer.classList.add("show");
            document.body.style.overflow = "hidden";
        },

        closeFormDrawer: function () {
            const drawer = document.getElementById("projectFormDrawer");
            if (drawer) drawer.classList.remove("show");
            document.body.style.overflow = "";
        },

        saveProjectSubmit: function () {
            const editId = document.getElementById("editProjectId").value;
            const title = document.getElementById("fieldProjectTitle").value.trim();
            const client = document.getElementById("fieldProjectClient").value.trim();
            const dealIdVal = document.getElementById("fieldProjectDeal")?.value;
            
            const mgrSelect = document.getElementById("fieldProjectManager");
            const selectedOpt = mgrSelect ? mgrSelect.options[mgrSelect.selectedIndex] : null;
            const managerId = selectedOpt ? selectedOpt.getAttribute("data-id") : null;

            const status = document.getElementById("fieldProjectStatus").value;
            const priority = document.getElementById("fieldProjectPriority").value;
            const startDate = document.getElementById("fieldProjectStartDate").value.trim();
            const dueDate = document.getElementById("fieldProjectDueDate").value.trim();
            const budgetVal = document.getElementById("fieldProjectBudget").value;
            const progressVal = document.getElementById("fieldProjectProgress").value;
            const desc = document.getElementById("fieldProjectDesc").value.trim();

            if (!title || !client) {
                alert("Please enter both Project Name and Client.");
                return;
            }

            const payload = {
                id: editId || undefined,
                name: title,
                client_name: client,
                manager_id: managerId ? parseInt(managerId, 10) : null,
                deal_id: dealIdVal ? parseInt(dealIdVal, 10) : null,
                status: status || null,
                priority: priority || null,
                start_date: startDate || null,
                due_date: dueDate || null,
                budget: budgetVal !== "" ? parseFloat(budgetVal) : null,
                progress: progressVal !== "" ? parseInt(progressVal, 10) : null,
                description: desc || null
            };

            const action = editId ? "update_project" : "create_project";

            fetch(`api/projects.php?action=${action}`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message || (editId ? "Project updated." : "Project created."));
                    window.projectsApp.closeFormDrawer();
                    fetchProjectsBootstrap();
                } else {
                    alert(res.message || "Failed to save project.");
                }
            })
            .catch(err => {
                console.error("Save project error:", err);
                showToast("Network error saving project.");
            });
        },

        deleteProject: function (id) {
            if (!confirm("Are you sure you want to delete this project and all linked tasks, milestones, and files?")) {
                return;
            }

            fetch("api/projects.php?action=delete_project", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message || "Project deleted.");
                    if (activeProjectForDetails && activeProjectForDetails.id === id) {
                        window.projectsApp.closeDetails();
                    }
                    fetchProjectsBootstrap();
                } else {
                    alert(res.message || "Failed to delete project.");
                }
            })
            .catch(err => {
                console.error("Delete project error:", err);
                showToast("Network error deleting project.");
            });
        },

        // Task operations
        submitNewTask: function () {
            if (!activeProjectForDetails) return;
            const titleInput = document.getElementById("newDrawerTaskTitle");
            const dueInput = document.getElementById("newDrawerTaskDue");
            const title = titleInput?.value.trim();
            const due = dueInput?.value.trim();

            if (!title) return;

            fetch("api/projects.php?action=create_task", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    project_id: activeProjectForDetails.id,
                    title: title,
                    due_date: due || null
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast("Task added.");
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                } else {
                    showToast(res.message || "Failed to add task.");
                }
            });
        },

        toggleTaskStatus: function (taskId, completed) {
            fetch("api/projects.php?action=update_task_status", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    task_id: taskId,
                    completed: completed
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                }
            });
        },

        deleteTask: function (taskId) {
            fetch("api/projects.php?action=delete_task", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ task_id: taskId })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast("Task deleted.");
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                }
            });
        },

        // Milestone operations
        submitNewMilestone: function () {
            if (!activeProjectForDetails) return;
            const nameInput = document.getElementById("newDrawerMilestoneName");
            const name = nameInput?.value.trim();
            if (!name) return;

            fetch("api/projects.php?action=create_milestone", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    project_id: activeProjectForDetails.id,
                    name: name
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast("Milestone added.");
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                } else {
                    showToast(res.message || "Failed to add milestone.");
                }
            });
        },

        updateMilestoneStatus: function (msId, status) {
            fetch("api/projects.php?action=update_milestone_status", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    milestone_id: msId,
                    status: status
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                }
            });
        },

        deleteMilestone: function (msId) {
            fetch("api/projects.php?action=delete_milestone", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ milestone_id: msId })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast("Milestone deleted.");
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                }
            });
        },

        // File operations
        uploadProjectFile: function () {
            if (!activeProjectForDetails) return;
            const fileInput = document.getElementById("drawerProjectFileInput");
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showToast("Please select a file to upload.");
                return;
            }

            const formData = new FormData();
            formData.append("project_id", activeProjectForDetails.id);
            formData.append("file", fileInput.files[0]);

            fetch("api/projects.php?action=upload_file", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast("File uploaded successfully.");
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                } else {
                    alert(res.message || "File upload failed.");
                }
            })
            .catch(err => {
                console.error("Upload error:", err);
                showToast("Upload failed.");
            });
        },

        deleteProjectFile: function (fileId, source = 'project_file') {
            if (!confirm("Are you sure you want to delete this file attachment?")) return;

            fetch("api/projects.php?action=delete_file", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ file_id: fileId, source: source })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast("File deleted.");
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                }
            });
        },

        // Note logging
        logProjectNote: function () {
            if (!activeProjectForDetails) return;
            const noteInput = document.getElementById("newDrawerActivityText");
            const text = noteInput?.value.trim();
            if (!text) return;

            fetch("api/projects.php?action=log_activity", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    project_id: activeProjectForDetails.id,
                    text: text
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast("Note logged.");
                    window.projectsApp.openDetails(activeProjectForDetails.id);
                }
            });
        },

        exportCSV: function () {
            window.location.href = "api/projects.php?action=export_csv";
        },

        showToast: function (msg) {
            showToast(msg);
        },

        // Customize Columns menu
        toggleCustomizeColumnsMenu: function (e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById("projectsColumnsDropdownMenu");
            if (!menu) return;
            const isVisible = menu.classList.contains("show");
            if (!isVisible) {
                renderCustomizeColumnsList();
                menu.classList.add("show");
            } else {
                menu.classList.remove("show");
            }
        },

        selectAllColumns: function () {
            columnOrder.forEach(col => columnVisibility[col] = true);
            saveColumnsConfig();
            renderCustomizeColumnsList();
            renderProjectsUI();
        },

        hideOptionalColumns: function () {
            columnOrder.forEach(col => {
                if (col !== 'project' && col !== 'actions') columnVisibility[col] = false;
            });
            saveColumnsConfig();
            renderCustomizeColumnsList();
            renderProjectsUI();
        },

        resetColumnsToDefault: function () {
            columnOrder = [...defaultColumnOrder];
            defaultColumnOrder.forEach(col => columnVisibility[col] = true);
            saveColumnsConfig();
            renderCustomizeColumnsList();
            renderProjectsUI();
        }
    };

    function renderCustomizeColumnsList() {
        const list = document.getElementById("projectsColumnsList");
        if (!list) return;

        let html = "";
        columnOrder.forEach((col, index) => {
            const isChecked = columnVisibility[col] ? "checked" : "";
            const isMandatory = (col === "project" || col === "actions");
            html += `
                <div class="table-column-item" data-col="${col}">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="colToggle_${col}" ${isChecked} ${isMandatory ? 'disabled' : ''} onchange="window.projectsApp.toggleColumnVisibility('${col}', this.checked)">
                        <label for="colToggle_${col}" style="font-size:12.5px;color:var(--text-heading);cursor:pointer;margin:0;">${escapeHtml(columnLabels[col])}</label>
                    </div>
                    <div style="display:flex;gap:4px;">
                        <button type="button" class="btn-col-order" ${index === 0 ? 'disabled' : ''} onclick="window.projectsApp.moveColumnOrder(${index}, -1)">▲</button>
                        <button type="button" class="btn-col-order" ${index === columnOrder.length - 1 ? 'disabled' : ''} onclick="window.projectsApp.moveColumnOrder(${index}, 1)">▼</button>
                    </div>
                </div>
            `;
        });
        list.innerHTML = html;
    }

    window.projectsApp.toggleColumnVisibility = function (col, isVisible) {
        columnVisibility[col] = isVisible;
        saveColumnsConfig();
        renderProjectsUI();
    };

    window.projectsApp.moveColumnOrder = function (index, dir) {
        const target = index + dir;
        if (target < 0 || target >= columnOrder.length) return;
        const temp = columnOrder[index];
        columnOrder[index] = columnOrder[target];
        columnOrder[target] = temp;
        saveColumnsConfig();
        renderCustomizeColumnsList();
        renderProjectsUI();
    };

    // Close dropdown when clicking outside
    document.addEventListener("click", function (e) {
        const menu = document.getElementById("projectsColumnsDropdownMenu");
        if (menu && menu.classList.contains("show") && !e.target.closest(".table-columns-dropdown-wrapper")) {
            menu.classList.remove("show");
        }
    });

    // Toast utility
    function showToast(msg) {
        let container = document.querySelector(".toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "toast-container";
            container.style.cssText = "position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;";
            document.body.appendChild(container);
        }
        const toast = document.createElement("div");
        toast.style.cssText = "background:#101828;color:#FFF;padding:10px 16px;border-radius:6px;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:fadeIn 0.2s;";
        toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transition = "opacity 0.25s";
            setTimeout(() => toast.remove(), 250);
        }, 2500);
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Initialize on DOM load
    document.addEventListener("DOMContentLoaded", function () {
        window.projectsApp.init();
    });
})();
