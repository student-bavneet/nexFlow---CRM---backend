/**
 * NexFlow CRM — Client Portal Projects Controller
 * Multi-tenant, multi-company contact isolated script for client-projects.php.
 * All project data is sourced strictly from MySQL via window.__CLIENT_PROJECTS_DATA__ and api/client-projects.php.
 */

(function () {
    // Escape HTML strings for secure template rendering
    function escapeHtml(str) {
        if (str === null || str === undefined) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // In-memory project store initialized from server-rendered payload
    let liveProjects = [];
    if (window.__CLIENT_PROJECTS_DATA__ && Array.isArray(window.__CLIENT_PROJECTS_DATA__.projects)) {
        liveProjects = window.__CLIENT_PROJECTS_DATA__.projects;
    }

    // In-memory cache for full project detail objects (milestones, tasks, files)
    const projectDetailsCache = {};

    function getProjectsData() {
        return liveProjects;
    }

    // Helper functions for badge colors (strictly preserving existing UI design)
    function getStatusBadgeClass(status) {
        switch (status) {
            case "Completed": return "green";
            case "In Progress": return "blue";
            case "Planning": return "purple";
            case "On Hold": return "amber";
            case "Cancelled": return "red";
            default: return "gray";
        }
    }

    function getPriorityBadgeClass(priority) {
        switch (priority) {
            case "Urgent": return "red";
            case "High": return "amber";
            case "Medium": return "blue";
            default: return "gray";
        }
    }

    // Render KPI Stat Cards
    function renderProjectKPIs() {
        if (window.__CLIENT_PROJECTS_DATA__ && window.__CLIENT_PROJECTS_DATA__.kpis) {
            const k = window.__CLIENT_PROJECTS_DATA__.kpis;
            const totalEl = document.getElementById("kpiTotalProjects");
            const activeEl = document.getElementById("kpiActiveProjects");
            const completedEl = document.getElementById("kpiCompletedProjects");
            const upcomingEl = document.getElementById("kpiUpcomingDeadlines");

            if (totalEl) totalEl.textContent = k.total ?? 0;
            if (activeEl) activeEl.textContent = k.active ?? 0;
            if (completedEl) completedEl.textContent = k.completed ?? 0;
            if (upcomingEl) upcomingEl.textContent = k.upcoming ?? 0;
            return;
        }

        const projects = getProjectsData();
        const totalCount = projects.length;
        const activeCount = projects.filter(p => p.status === "In Progress" || p.status === "Planning").length;
        const completedCount = projects.filter(p => p.status === "Completed").length;
        const upcomingCount = projects.filter(p => p.status !== "Completed" && p.status !== "Cancelled").length;

        const totalEl = document.getElementById("kpiTotalProjects");
        const activeEl = document.getElementById("kpiActiveProjects");
        const completedEl = document.getElementById("kpiCompletedProjects");
        const upcomingEl = document.getElementById("kpiUpcomingDeadlines");

        if (totalEl) totalEl.textContent = totalCount;
        if (activeEl) activeEl.textContent = activeCount;
        if (completedEl) completedEl.textContent = completedCount;
        if (upcomingEl) upcomingEl.textContent = upcomingCount;
    }

    // Render Projects Grid / List
    window.renderClientProjects = function () {
        const projects = getProjectsData();
        const container = document.getElementById("clientProjectsGrid");
        const emptyState = document.getElementById("clientProjectsEmptyState");
        if (!container) return;

        const query = (document.getElementById("projectsSearchInput")?.value || "").toLowerCase().trim();
        const statusFilter = document.getElementById("projectsStatusFilter")?.value || "All";
        const sortBy = document.getElementById("projectsSortSelect")?.value || "newest";

        let filtered = projects.filter(p => {
            const nameStr = (p.name || "").toLowerCase();
            const codeStr = (p.projectCode || "").toLowerCase();
            const mgrStr = (p.manager || "").toLowerCase();
            const clientStr = (p.client || "").toLowerCase();

            const matchesSearch = !query || 
                nameStr.includes(query) || 
                codeStr.includes(query) || 
                mgrStr.includes(query) || 
                clientStr.includes(query);

            const matchesStatus = (statusFilter === "All") || p.status === statusFilter;
            return matchesSearch && matchesStatus;
        });

        // Sorting
        if (sortBy === "newest") {
            filtered.sort((a, b) => new Date(b.rawStartDate || b.startDate) - new Date(a.rawStartDate || a.startDate));
        } else if (sortBy === "oldest") {
            filtered.sort((a, b) => new Date(a.rawStartDate || a.startDate) - new Date(b.rawStartDate || b.startDate));
        } else if (sortBy === "deadline") {
            filtered.sort((a, b) => new Date(a.rawDeadline || a.deadline) - new Date(b.rawDeadline || b.deadline));
        } else if (sortBy === "progress") {
            filtered.sort((a, b) => (b.progress || 0) - (a.progress || 0));
        } else if (sortBy === "name") {
            filtered.sort((a, b) => (a.name || "").localeCompare(b.name || ""));
        }

        if (filtered.length === 0) {
            container.style.display = "none";
            if (emptyState) emptyState.style.display = "block";
            return;
        }

        if (emptyState) emptyState.style.display = "none";
        container.style.display = "grid";

        container.innerHTML = filtered.map(p => {
            const statusClass = getStatusBadgeClass(p.status);
            const isCompleted = p.status === "Completed";
            const pIdNumeric = parseInt(p.id, 10);

            return `
                <div class="client-project-card">
                    <div>
                        <div class="client-project-card-header">
                            <div>
                                <span class="client-project-id">${escapeHtml(p.projectCode)}</span>
                                <h3 class="client-project-title" style="margin-top:6px;">${escapeHtml(p.name)}</h3>
                            </div>
                            <span class="client-portal-badge ${statusClass}">${escapeHtml(p.status)}</span>
                        </div>
                        
                        <p class="client-project-desc">${escapeHtml(p.description)}</p>

                        <div class="client-project-meta-grid">
                            <div class="client-project-meta-item">
                                <span class="client-project-meta-label">Client</span>
                                <span class="client-project-meta-val">${escapeHtml(p.client)}</span>
                            </div>
                            <div class="client-project-meta-item">
                                <span class="client-project-meta-label">Manager</span>
                                <span class="client-project-meta-val">${escapeHtml(p.manager)}</span>
                            </div>
                            <div class="client-project-meta-item">
                                <span class="client-project-meta-label">Start Date</span>
                                <span class="client-project-meta-val">${escapeHtml(p.startDate)}</span>
                            </div>
                            <div class="client-project-meta-item">
                                <span class="client-project-meta-label">Deadline</span>
                                <span class="client-project-meta-val">${escapeHtml(p.deadline)}</span>
                            </div>
                        </div>

                        <div class="client-project-progress-box">
                            <div class="client-project-progress-header">
                                <span class="client-project-progress-label">Overall Progress</span>
                                <span class="client-project-progress-pct">${escapeHtml(p.progress)}%</span>
                            </div>
                            <div class="client-project-progress-track">
                                <div class="client-project-progress-fill ${isCompleted ? 'completed' : ''}" style="width:${Math.min(100, Math.max(0, p.progress))}%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="client-project-card-footer">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:11.5px;color:#64748B;">Budget:</span>
                            <span style="font-size:13px;font-weight:700;color:#0F172A;">${escapeHtml(p.budget)}</span>
                        </div>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientProjects.openProjectDrawer(${pIdNumeric}, this)">
                            View Project
                        </button>
                    </div>
                </div>
            `;
        }).join("");
    };

    // Public Controller Object
    window.clientProjects = {
        activeProjectId: null,
        activeTab: "overview",
        lastFocusedBtn: null,

        openProjectDrawer: function (projectId, triggerBtn) {
            this.lastFocusedBtn = triggerBtn || document.activeElement;
            const numericId = parseInt(projectId, 10);
            const projects = getProjectsData();
            const project = projects.find(p => parseInt(p.id, 10) === numericId);
            if (!project) return;

            this.activeProjectId = numericId;
            this.activeTab = "overview";

            const drawer = document.getElementById("clientProjectDrawer");
            const title = document.getElementById("cpdProjectTitle");
            const badge = document.getElementById("cpdStatusBadge");
            const priorityBadge = document.getElementById("cpdPriorityBadge");

            if (title) title.textContent = `${project.name} (${project.projectCode || ('PRJ-' + project.id)})`;
            if (badge) {
                badge.textContent = project.status;
                badge.className = "client-portal-badge " + getStatusBadgeClass(project.status);
            }
            if (priorityBadge) {
                priorityBadge.textContent = (project.priority || "Medium") + " Priority";
                priorityBadge.className = "client-portal-badge " + getPriorityBadgeClass(project.priority);
            }

            // Immediately render the overview tab using in-memory data
            this.renderDrawerTabContent("overview", project);

            if (drawer) {
                drawer.style.display = "block";
                drawer.classList.add("show");
                document.body.style.overflow = "hidden";
                const firstTab = drawer.querySelector(".client-portal-drawer-tab");
                if (firstTab) firstTab.focus();
            }

            // Fetch live authorized sub-data (milestones, tasks, files) from api/client-projects.php
            this.fetchProjectDetails(numericId);
        },

        fetchProjectDetails: function (projectId) {
            const self = this;
            fetch(`api/client-projects.php?action=detail&id=${encodeURIComponent(projectId)}`, { credentials: "same-origin" })
                .then(res => {
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}`);
                    }
                    return res.json();
                })
                .then(json => {
                    if (json.success && json.data) {
                        projectDetailsCache[projectId] = json.data;
                        if (self.activeProjectId === projectId) {
                            self.renderDrawerTabContent(self.activeTab, json.data);
                        }
                    }
                })
                .catch(err => {
                    console.warn("Could not load project sub-data:", err);
                });
        },

        closeProjectDrawer: function () {
            const drawer = document.getElementById("clientProjectDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.style.display = "none";
                document.body.style.overflow = "";
            }
            if (this.lastFocusedBtn && typeof this.lastFocusedBtn.focus === "function") {
                this.lastFocusedBtn.focus();
            }
        },

        switchProjectDrawerTab: function (tabName) {
            this.activeTab = tabName;
            document.querySelectorAll("#clientProjectDrawer .client-portal-drawer-tab").forEach(tab => {
                const isActive = tab.getAttribute("data-tab") === tabName;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            const projectId = this.activeProjectId;
            const fullDetail = projectDetailsCache[projectId];
            if (fullDetail) {
                this.renderDrawerTabContent(tabName, fullDetail);
            } else {
                const projects = getProjectsData();
                const project = projects.find(p => parseInt(p.id, 10) === projectId);
                if (project) {
                    this.renderDrawerTabContent(tabName, project);
                }
            }
        },

        renderDrawerTabContent: function (tabName, project) {
            const body = document.getElementById("clientProjectDrawerBody");
            if (!body || !project) return;

            const statusClass = getStatusBadgeClass(project.status);
            const isCompleted = project.status === "Completed";
            const initials = project.managerInitials || (project.manager ? project.manager.split(" ").map(n=>n[0]).join("") : "PM");

            if (tabName === "overview") {
                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">PROJECT BUDGET</div>
                            <div style="font-size:18px;font-weight:700;color:#0F172A;margin-top:2px;">${escapeHtml(project.budget)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">TARGET DEADLINE</div>
                            <div style="font-size:14px;font-weight:700;color:#0F172A;margin-top:4px;">${escapeHtml(project.deadline)}</div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:8px;">Project Progress (${escapeHtml(project.progress)}%)</h4>
                        <div class="client-project-progress-track" style="height:10px;">
                            <div class="client-project-progress-fill ${isCompleted ? 'completed' : ''}" style="width:${Math.min(100, Math.max(0, project.progress))}%;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Project Scope &amp; Description</h4>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;">${escapeHtml(project.description || "No project description provided.")}</p>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Client Organization</h4>
                            <div style="font-size:13px;color:#334155;font-weight:600;">${escapeHtml(project.client)}</div>
                        </div>
                        <div>
                            <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Start Date</h4>
                            <div style="font-size:13px;color:#334155;">${escapeHtml(project.startDate)}</div>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Assigned Project Manager</h4>
                        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
                            <div class="client-portal-avatar" style="background-color:#2563EB;">${escapeHtml(initials)}</div>
                            <div style="flex:1;">
                                <div style="font-size:13.5px;font-weight:600;color:#0F172A;">${escapeHtml(project.manager)}</div>
                                <div style="font-size:11.5px;color:#64748B;">NexFlow Project Lead</div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientPortal.openQuickMessageModal('Project: ' + '${escapeHtml(project.name)}')">Message Manager</button>
                        </div>
                    </div>
                `;
            } else if (tabName === "milestones") {
                const milestones = project.milestones || [];
                if (milestones.length === 0) {
                    body.innerHTML = `
                        <div style="text-align:center;padding:36px 16px;color:#64748B;">
                            <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px auto;display:block;">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">No milestones defined for this project.</div>
                            <div style="font-size:12.5px;color:#64748B;">Key milestones and deliverables will be published by your project manager.</div>
                        </div>
                    `;
                    return;
                }

                body.innerHTML = `
                    <div class="client-project-milestones-list">
                        ${milestones.map((m, idx) => `
                            <div class="client-project-milestone-item">
                                <div class="client-project-milestone-info">
                                    <div class="client-project-milestone-icon ${escapeHtml(m.status.toLowerCase().replace(/\s+/g, '-'))}">
                                        ${m.status === 'Completed' ? '✓' : (idx + 1)}
                                    </div>
                                    <span class="client-project-milestone-title">${escapeHtml(m.title)}</span>
                                </div>
                                <span class="client-portal-badge ${getStatusBadgeClass(m.status)}">${escapeHtml(m.status)}</span>
                            </div>
                        `).join("")}
                    </div>
                `;
            } else if (tabName === "tasks") {
                const tasks = project.tasks || [];
                if (tasks.length === 0) {
                    body.innerHTML = `
                        <div style="text-align:center;padding:36px 16px;color:#64748B;">
                            <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px auto;display:block;">
                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">No tasks associated with this project.</div>
                            <div style="font-size:12.5px;color:#64748B;">Client-visible tasks and action items will be displayed here.</div>
                        </div>
                    `;
                    return;
                }

                // Strictly read-only task list in Phase 3 (checkboxes disabled, no mutation API)
                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        ${tasks.map(t => `
                            <div class="client-portal-task-item" style="border:1px solid #E2E8F0;border-radius:6px;padding:10px 14px;background:#FFFFFF;">
                                <div class="client-portal-task-left">
                                    <input type="checkbox" class="client-portal-task-checkbox" ${t.completed ? 'checked' : ''} disabled style="cursor:default;" aria-label="${escapeHtml(t.title)}">
                                    <div>
                                        <div class="client-portal-task-title ${t.completed ? 'completed' : ''}" style="${t.completed ? 'text-decoration:line-through;color:#94A3B8;' : ''}">${escapeHtml(t.title)}</div>
                                        <div class="client-portal-task-meta">Due ${escapeHtml(t.dueDate)} • Priority: ${escapeHtml(t.priority)}</div>
                                    </div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                `;
            } else if (tabName === "files") {
                const files = project.files || [];
                if (files.length === 0) {
                    body.innerHTML = `
                        <div style="text-align:center;padding:36px 16px;color:#64748B;">
                            <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px auto;display:block;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">No shared files for this project.</div>
                            <div style="font-size:12.5px;color:#64748B;">Project documentation, assets, and deliverables will be shared here.</div>
                        </div>
                    `;
                    return;
                }

                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${files.map(f => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="client-portal-doc-icon" style="width:34px;height:34px;border-radius:6px;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                    </div>
                                    <div>
                                        <div style="font-size:13px;font-weight:600;color:#0F172A;">${escapeHtml(f.name)}</div>
                                        <div style="font-size:11px;color:#64748B;">${escapeHtml(f.type)} • ${escapeHtml(f.size)} • ${escapeHtml(f.date)}</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientPortal.showToast('Downloading project file: ' + '${escapeHtml(f.name)}', 'info')">Download</button>
                            </div>
                        `).join("")}
                    </div>
                `;
            } else if (tabName === "activity") {
                // Hardening Requirement 3: Do NOT expose internal Admin CRM team_activities.
                // Render exact-design empty state "No recent project activity recorded."
                const activity = project.activity || [];
                if (activity.length === 0) {
                    body.innerHTML = `
                        <div style="text-align:center;padding:36px 16px;color:#64748B;">
                            <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px auto;display:block;">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">No recent project activity recorded.</div>
                            <div style="font-size:12.5px;color:#64748B;">All updates and status transitions will appear here as work progresses.</div>
                        </div>
                    `;
                    return;
                }

                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        ${activity.map(a => `
                            <div style="display:flex;gap:12px;font-size:13px;align-items:flex-start;">
                                <div style="width:8px;height:8px;border-radius:50%;background:#2563EB;margin-top:6px;flex-shrink:0;"></div>
                                <div style="flex:1;">
                                    <div style="font-weight:600;color:#0F172A;">${escapeHtml(a.user)} <span style="font-size:11px;font-weight:400;color:#64748B;">• ${escapeHtml(a.date)}</span></div>
                                    <div style="color:#475569;margin-top:3px;line-height:1.45;">${escapeHtml(a.text)}</div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                `;
            }
        }
    };

    // Keyboard Escape handling
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            const drawer = document.getElementById("clientProjectDrawer");
            if (drawer && (drawer.classList.contains("show") || drawer.style.display === "block")) {
                window.clientProjects.closeProjectDrawer();
                e.stopPropagation();
            }
        }
    });

    // Auto-init on DOMContentLoaded
    document.addEventListener("DOMContentLoaded", function () {
        renderProjectKPIs();
        window.renderClientProjects();
    });
})();