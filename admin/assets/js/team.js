/**
 * NexFlow CRM - Team Management JavaScript Controller
 * Real PHP + MySQL Database-backed controller.
 * Handles member listings, grid/list view, dynamic KPI summary, search & filtering,
 * member profile drawer, live performance calculations, workload capacity & reassignments,
 * role permissions matrix, member notes CRUD, and real database lead assignments.
 */

(function () {
    let allMembers = [];
    let filteredMembers = [];
    let allRoles = [];
    let currentSummary = null;

    let activeTab = "members";
    let viewMode = "list"; // "list" or "grid"
    let currentDrawerMemberId = null;
    let currentDrawerTab = "overview";
    let selectedMemberIds = new Set();
    let teamNoteSearchQuery = "";
    let isAddNoteFormOpen = false;
    let availableLeadsForAssignment = [];
    let selectedLeadForAssignment = null;

    // Filter State
    let searchFilter = {
        query: "",
        dept: "all",
        role: "all",
        avail: "all",
        sort: "name"
    };

    function initTeamPage(forceReinit) {
        const root = document.querySelector(".team-page");
        if (!root) return;

        if (window._teamPageInitialized && !forceReinit) return;
        window._teamPageInitialized = true;

        setupEventListeners();
        loadTeamData();
    }

    window.initTeamPage = initTeamPage;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { initTeamPage(); });
    } else {
        initTeamPage();
    }

    // ==========================================
    // DATA LOADING (REAL API VIA PHP + MYSQL)
    // ==========================================
    function loadTeamData(callback) {
        fetch('api/team.php?action=bootstrap', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(res => {
            if (res && res.success && res.data) {
                allMembers = res.data.members || [];
                allRoles = res.data.roles || [];
                currentSummary = res.data.summary || {};

                updateSummaryCards(currentSummary);
                applyFilterAndSort();
                renderActiveTab();

                if (typeof callback === 'function') callback();
            } else {
                console.error('Failed to load team data:', res.message);
            }
        })
        .catch(err => {
            console.error('Error fetching team data from backend:', err);
        });
    }

    function initMembersData() {
        loadTeamData();
    }

    function updateSummaryCards(s) {
        if (!s) return;
        const elTotal = document.getElementById("summaryTotalMembers");
        const elActive = document.getElementById("summaryActiveNow");
        const elAdmins = document.getElementById("summaryAdmins");
        const elPipe = document.getElementById("summaryPipelineVal");
        const elTarget = document.getElementById("summaryTargetAchieved");
        const elBadge = document.getElementById("teamHeaderTotalBadge");

        if (elTotal) elTotal.textContent = s.totalMembers;
        if (elActive) elActive.textContent = s.activeNow;
        if (elAdmins) elAdmins.textContent = s.adminsManagers;
        if (elPipe) elPipe.textContent = '$' + (Number(s.teamPipeline) || 0).toLocaleString();
        if (elTarget) elTarget.textContent = (s.targetAchievedPct || 0) + '%';
        if (elBadge) elBadge.textContent = s.totalMembers + ' members';

        // Performance summary cards
        const elRev = document.getElementById("perfTotalRevenue");
        const elQuota = document.getElementById("perfTeamQuota");
        const elWin = document.getElementById("perfAvgWinRate");
        const elCycle = document.getElementById("perfAvgSalesCycle");
        const elAchievedSub = document.getElementById("perfQuotaAchievedSub");

        if (elRev) elRev.textContent = '$' + (Number(s.totalRevenue) || 0).toLocaleString();
        if (elQuota) elQuota.textContent = '$' + (Number(s.teamQuota) || 0).toLocaleString();
        if (elWin) elWin.textContent = s.avgWinRate || '0.0%';
        if (elCycle) elCycle.textContent = s.avgSalesCycle || '26 days';
        if (elAchievedSub) elAchievedSub.textContent = (s.targetAchievedPct || 0) + '% achieved overall';
    }

    function setupEventListeners() {
        // Export dropdown backdrop
        document.addEventListener("click", function (e) {
            const exportWrapper = document.getElementById("teamExportDropdownWrapper");
            const btnExport = document.getElementById("btnTeamExportDropdown");
            if (exportWrapper && btnExport) {
                if (btnExport.contains(e.target)) {
                    exportWrapper.classList.toggle("open");
                } else if (!exportWrapper.contains(e.target)) {
                    exportWrapper.classList.remove("open");
                }
            }
        });

        // Global Esc Key for drawers
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                window.teamApp.closeMemberDrawer();
                window.teamApp.closeInviteDrawer();
                window.teamApp.closeReassignDrawer();
                window.teamApp.closeAssignLeadModal();
            }
        });

        // Invite / Edit form submit listener
        const inviteForm = document.getElementById("inviteMemberForm");
        if (inviteForm) {
            inviteForm.addEventListener("submit", function (e) {
                e.preventDefault();
                saveInviteMemberForm();
            });
        }

        // Reassign work form submit listener
        const reassignForm = document.getElementById("reassignWorkForm");
        if (reassignForm) {
            reassignForm.addEventListener("submit", function (e) {
                e.preventDefault();
                submitReassignForm();
            });
        }

        // Search inputs
        const searchInput = document.getElementById("teamSearchInput");
        if (searchInput) {
            searchInput.addEventListener("input", function () {
                window.teamApp.handleSearchFilter();
            });
        }

        const deptSelect = document.getElementById("teamDeptSelect");
        if (deptSelect) {
            deptSelect.addEventListener("change", function () {
                window.teamApp.handleSearchFilter();
            });
        }

        const roleSelect = document.getElementById("teamRoleSelect");
        if (roleSelect) {
            roleSelect.addEventListener("change", function () {
                window.teamApp.handleSearchFilter();
            });
        }

        const availSelect = document.getElementById("teamAvailSelect");
        if (availSelect) {
            availSelect.addEventListener("change", function () {
                window.teamApp.handleSearchFilter();
            });
        }

        const sortSelect = document.getElementById("teamSortSelect");
        if (sortSelect) {
            sortSelect.addEventListener("change", function () {
                window.teamApp.handleSearchFilter();
            });
        }
    }

    // ==========================================
    // FILTERING & SORTING LOGIC
    // ==========================================
    function applyFilterAndSort() {
        filteredMembers = allMembers.filter(m => {
            // Search Query
            if (searchFilter.query) {
                const q = searchFilter.query.toLowerCase();
                const matchName = (m.name || "").toLowerCase().includes(q);
                const matchEmail = (m.email || "").toLowerCase().includes(q);
                const matchRole = (m.role || "").toLowerCase().includes(q);
                const matchDept = (m.department || "").toLowerCase().includes(q);
                if (!matchName && !matchEmail && !matchRole && !matchDept) return false;
            }

            // Department Filter
            if (searchFilter.dept !== "all" && m.department !== searchFilter.dept) {
                return false;
            }

            // Role Filter
            if (searchFilter.role !== "all" && m.role !== searchFilter.role) {
                return false;
            }

            // Availability Filter
            if (searchFilter.avail !== "all" && m.availability !== searchFilter.avail) {
                return false;
            }

            return true;
        });

        // Sorting
        filteredMembers.sort((a, b) => {
            if (searchFilter.sort === "name") {
                return (a.name || "").localeCompare(b.name || "");
            } else if (searchFilter.sort === "name-desc") {
                return (b.name || "").localeCompare(a.name || "");
            } else if (searchFilter.sort === "leads") {
                return (b.assignedLeads || 0) - (a.assignedLeads || 0);
            } else if (searchFilter.sort === "revenue") {
                return (b.revenue || 0) - (a.revenue || 0);
            } else if (searchFilter.sort === "workload") {
                return (b.workloadLevel || 0) - (a.workloadLevel || 0);
            }
            return 0;
        });

        renderActiveFilterChips();
        renderMembersListAndGrid();
    }

    function renderActiveFilterChips() {
        const chipsContainer = document.getElementById("teamChipsContainer");
        const chipsRow = document.getElementById("teamActiveChipsRow");
        if (!chipsContainer || !chipsRow) return;

        const activeChips = [];
        if (searchFilter.query) {
            activeChips.push({ key: "query", label: `Search: "${searchFilter.query}"` });
        }
        if (searchFilter.dept !== "all") {
            activeChips.push({ key: "dept", label: `Dept: ${searchFilter.dept}` });
        }
        if (searchFilter.role !== "all") {
            activeChips.push({ key: "role", label: `Role: ${searchFilter.role}` });
        }
        if (searchFilter.avail !== "all") {
            activeChips.push({ key: "avail", label: `Status: ${searchFilter.avail}` });
        }

        chipsRow.style.display = activeChips.length > 0 ? "flex" : "none";
        chipsContainer.innerHTML = activeChips.map(c => `
            <span class="team-chip">
                ${escapeHtml(c.label)}
                <button type="button" class="team-chip-remove" onclick="window.teamApp.removeFilterChip('${c.key}')">✕</button>
            </span>
        `).join("");
    }

    function renderMembersListAndGrid() {
        const tableBody = document.getElementById("teamMembersTableBody");
        const cardsGrid = document.getElementById("teamMembersCardsGrid");
        const emptyState = document.getElementById("teamMembersEmptyState");

        if (filteredMembers.length === 0) {
            if (tableBody) tableBody.innerHTML = "";
            if (cardsGrid) cardsGrid.innerHTML = "";
            if (emptyState) emptyState.style.display = "block";
            return;
        } else {
            if (emptyState) emptyState.style.display = "none";
        }

        // 1. Render Table Rows (List View)
        if (tableBody) {
            tableBody.innerHTML = filteredMembers.map(m => {
                const targetPct = m.targetCompletion || 0;
                const progressColor = targetPct >= 100 ? "#10B981" : targetPct >= 90 ? "#0284C7" : "#F59E0B";

                return `
                    <tr data-member-id="${m.id}">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px; cursor:pointer;" onclick="window.teamApp.openMemberDrawer('${m.id}')">
                                <div class="avatar avatar-md" style="background-color:${m.avatarBg || '#7C3AED'}; flex-shrink:0;">${escapeHtml(m.avatar)}</div>
                                <div style="min-width:0;">
                                    <div style="font-weight:700; color:var(--text-heading); font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(m.name)}</div>
                                    <div style="font-size:11px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(m.email)}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="team-role-badge" style="background-color:${getRoleBadgeBg(m.role)}; color:${getRoleBadgeColor(m.role)};">${escapeHtml(m.role)}</span>
                            <div style="font-size:10.5px; color:var(--text-muted); margin-top:2px;">${escapeHtml(m.department)}</div>
                            ${getAccessBadgeHtml(m)}
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:5px; font-size:12px;">
                                <span class="team-status-dot ${m.availability}"></span>
                                <span>${escapeHtml(m.availability)}</span>
                            </div>
                        </td>
                        <td>
                            <strong style="font-size:13px;">${m.assignedLeads}</strong>
                            <div style="font-size:10.5px; color:var(--text-muted);">${m.newLeads} new</div>
                        </td>
                        <td>
                            <strong style="font-size:13px;">${m.openDeals}</strong>
                            <div style="font-size:10.5px; color:var(--text-muted);">${escapeHtml(m.highestStage || 'Active')}</div>
                        </td>
                        <td><strong>$${(m.pipelineValue || 0).toLocaleString()}</strong></td>
                        <td style="font-weight:700; color:var(--primary);">$${(m.revenue || 0).toLocaleString()}</td>
                        <td style="min-width: 120px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; font-size:11px; font-weight:600; margin-bottom:3px;">
                                <span>${targetPct}%</span>
                                <span style="color:var(--text-muted); font-weight:normal;">$${((m.target || 0)/1000)}k target</span>
                            </div>
                            <div class="team-progress-bg">
                                <div class="team-progress-fill" style="width:${Math.min(targetPct, 100)}%; background-color:${progressColor};"></div>
                            </div>
                        </td>
                        <td>
                            <span class="team-workload-badge ${m.workload}">${escapeHtml(m.workload)}</span>
                        </td>
                        <td style="font-size:11.5px; color:var(--text-muted);">${escapeHtml(m.lastActive)}</td>
                        <td style="text-align:right;">
                            <div style="display:flex; align-items:center; justify-content:flex-end; gap:4px;">
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.openMemberDrawer('${m.id}')">View</button>
                                <button type="button" class="btn btn-ghost btn-xs" onclick="window.teamApp.openInviteDrawer('${m.id}')" title="Edit Member">✏️</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
        }

        // 2. Render Cards (Grid View)
        if (cardsGrid) {
            cardsGrid.innerHTML = filteredMembers.map(m => {
                const targetPct = m.targetCompletion || 0;
                return `
                    <div class="team-member-card">
                        <div class="team-card-top">
                            <div class="avatar avatar-lg" style="background-color:${m.avatarBg || '#7C3AED'}; flex-shrink:0; cursor:pointer;" onclick="window.teamApp.openMemberDrawer('${m.id}')">${escapeHtml(m.avatar)}</div>
                            <div class="team-card-info">
                                <div class="team-card-name" style="cursor:pointer;" onclick="window.teamApp.openMemberDrawer('${m.id}')">${escapeHtml(m.name)}</div>
                                <div class="team-card-email">${escapeHtml(m.email)}</div>
                                <div style="display:flex; align-items:center; gap:6px; margin-top:4px;">
                                    <span class="team-role-badge" style="background-color:${getRoleBadgeBg(m.role)}; color:${getRoleBadgeColor(m.role)}; font-size:10px;">${escapeHtml(m.role)}</span>
                                    <span class="team-status-dot ${m.availability}" title="${m.availability}"></span>
                                </div>
                            </div>
                        </div>

                        <div class="team-card-metrics">
                            <div>
                                <div class="team-card-metric-val">${m.assignedLeads}</div>
                                <div class="team-card-metric-lbl">Leads</div>
                            </div>
                            <div>
                                <div class="team-card-metric-val">${m.openDeals}</div>
                                <div class="team-card-metric-lbl">Deals</div>
                            </div>
                            <div>
                                <div class="team-card-metric-val" style="color:var(--primary);">$${Math.round((m.revenue || 0)/1000)}k</div>
                                <div class="team-card-metric-lbl">Revenue</div>
                            </div>
                        </div>

                        <div style="font-size:11px;">
                            <div style="display:flex; justify-content:space-between; font-weight:600; margin-bottom:3px;">
                                <span>Target Completion</span>
                                <span>${targetPct}%</span>
                            </div>
                            <div class="team-progress-bg">
                                <div class="team-progress-fill" style="width:${Math.min(targetPct, 100)}%; background-color:${targetPct>=100?'#10B981':'#F59E0B'};"></div>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:auto; padding-top:8px; border-top:1px solid var(--border-divider);">
                            <span class="team-workload-badge ${m.workload}">${escapeHtml(m.workload)} Workload</span>
                            <div style="display:flex; gap:4px;">
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.openMemberDrawer('${m.id}')">View</button>
                                <button type="button" class="btn btn-ghost btn-xs" onclick="window.teamApp.openInviteDrawer('${m.id}')">Edit</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join("");
        }
    }

    function renderActiveTab() {
        document.querySelectorAll(".team-tab-btn").forEach(btn => {
            btn.classList.toggle("active", btn.dataset.tab === activeTab);
            btn.setAttribute("aria-selected", btn.dataset.tab === activeTab ? "true" : "false");
        });

        document.querySelectorAll(".team-tab-pane").forEach(pane => {
            pane.classList.toggle("active", pane.id === `pane-${activeTab}`);
        });

        if (activeTab === "roles") renderRolesTab();
        else if (activeTab === "workload") renderWorkloadTab();
        else if (activeTab === "performance") renderPerformanceTab();
    }

    // ==========================================
    // TAB 2: ROLES & PERMISSIONS RENDER
    // ==========================================
    function renderRolesTab() {
        const grid = document.getElementById("teamRoleCardsGrid");
        if (grid) {
            grid.innerHTML = allRoles.map(r => `
                <div class="team-role-card">
                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                            <span class="team-role-badge" style="background-color:${r.badgeBg}; color:${r.badgeColor}; font-size:12px;">${escapeHtml(r.title)}</span>
                            <span style="font-size:11px; color:var(--text-muted); font-weight:600;">${r.membersCount} members</span>
                        </div>
                        <p style="font-size:12px; color:var(--text-secondary); margin:0 0 12px; line-height:1.4;">${escapeHtml(r.description)}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-xs" style="width:100%;" onclick="showTeamToast('Viewing ${escapeHtml(r.title)} permissions...')">View Role Permissions</button>
                </div>
            `).join("");
        }

        const matrixBody = document.getElementById("teamPermissionMatrixBody");
        if (matrixBody) {
            const modules = [
                { id: "dashboard", name: "Dashboard Analytics" },
                { id: "leads", name: "Leads Management" },
                { id: "pipeline", name: "Sales Pipeline & Deals" },
                { id: "contacts", name: "Contacts Directory" },
                { id: "companies", name: "Company Accounts" },
                { id: "tasks", name: "Tasks & To-Dos" },
                { id: "calendar", name: "Sales Calendar" },
                { id: "inbox", name: "Unified Inbox" },
                { id: "reports", name: "Reports & Analytics" },
                { id: "team", name: "Team Management" },
                { id: "settings", name: "System Settings" }
            ];

            matrixBody.innerHTML = modules.map(m => `
                <tr>
                    <td><strong>${escapeHtml(m.name)}</strong></td>
                    ${allRoles.map(r => {
                        let perm = "View Only";
                        const rTitle = (r.title || "").toLowerCase();
                        if (rTitle.includes("admin") || rTitle.includes("super")) perm = "Full Access";
                        else if (rTitle.includes("manager")) {
                            perm = (m.id === 'settings') ? "View Only" : "Full Access";
                        } else if (rTitle.includes("rep") || rTitle.includes("executive")) {
                            perm = (['leads', 'pipeline', 'tasks', 'contacts', 'inbox'].includes(m.id)) ? "View and Edit" : (m.id === 'settings' ? 'No Access' : 'View Only');
                        } else if (rTitle.includes("operations")) {
                            perm = (['dashboard', 'reports'].includes(m.id)) ? "Full Access" : "View and Edit";
                        } else if (rTitle.includes("viewer")) {
                            perm = (m.id === 'inbox' || m.id === 'settings') ? "No Access" : "View Only";
                        }

                        const permClass = perm === "Full Access" ? "color:#047857; font-weight:600;" :
                                          perm === "View and Edit" ? "color:#0284C7; font-weight:600;" :
                                          perm === "View Own" ? "color:#B45309;" :
                                          perm === "View Only" ? "color:#64748B;" : "color:#991B1B;";
                        return `<td><span style="font-size:11.5px; ${permClass}">${escapeHtml(perm)}</span></td>`;
                    }).join("")}
                </tr>
            `).join("");
        }
    }

    // ==========================================
    // TAB 3: WORKLOAD RENDER
    // ==========================================
    function renderWorkloadTab() {
        const sGrid = document.getElementById("teamWorkloadSummaryGrid");
        if (sGrid) {
            const low = allMembers.filter(m => m.workload === "Low").length;
            const bal = allMembers.filter(m => m.workload === "Balanced").length;
            const high = allMembers.filter(m => m.workload === "High").length;
            const over = allMembers.filter(m => m.workload === "Overloaded").length;

            sGrid.innerHTML = `
                <div class="team-summary-card">
                    <div class="team-summary-label">Available Capacity</div>
                    <div class="team-summary-val" style="color:#047857;">${low}</div>
                    <div class="team-summary-sub">Under 50% utilization</div>
                </div>
                <div class="team-summary-card">
                    <div class="team-summary-label">Balanced Workload</div>
                    <div class="team-summary-val" style="color:#0284C7;">${bal}</div>
                    <div class="team-summary-sub">50% - 80% optimal capacity</div>
                </div>
                <div class="team-summary-card">
                    <div class="team-summary-label">High Workload</div>
                    <div class="team-summary-val" style="color:#B45309;">${high}</div>
                    <div class="team-summary-sub">80% - 90% capacity</div>
                </div>
                <div class="team-summary-card">
                    <div class="team-summary-label">Overloaded Members</div>
                    <div class="team-summary-val" style="color:#B91C1C;">${over}</div>
                    <div class="team-summary-sub">Above 90% capacity</div>
                </div>
            `;
        }

        const wBody = document.getElementById("teamWorkloadTableBody");
        if (wBody) {
            wBody.innerHTML = allMembers.map(m => {
                const lvl = m.workloadLevel || 50;
                const pColor = lvl > 90 ? "#EF4444" : lvl > 80 ? "#F59E0B" : "#10B981";

                return `
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="avatar avatar-sm" style="background-color:${m.avatarBg}">${escapeHtml(m.avatar)}</div>
                                <div>
                                    <strong>${escapeHtml(m.name)}</strong>
                                    <div style="font-size:10.5px; color:var(--text-muted);">${escapeHtml(m.role)}</div>
                                </div>
                            </div>
                        </td>
                        <td>${m.assignedLeads} leads</td>
                        <td>${m.openDeals} deals</td>
                        <td>${m.tasksCount} tasks</td>
                        <td>${m.meetingsCount} meetings</td>
                        <td style="min-width:120px;">
                            <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:600; margin-bottom:2px;">
                                <span>${lvl}%</span>
                            </div>
                            <div class="team-progress-bg">
                                <div class="team-progress-fill" style="width:${lvl}%; background-color:${pColor};"></div>
                            </div>
                        </td>
                        <td><span class="team-workload-badge ${m.workload}">${escapeHtml(m.workload)}</span></td>
                        <td style="text-align:right;">
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.openReassignDrawer('${m.id}')">Reassign Work</button>
                        </td>
                    </tr>
                `;
            }).join("");
        }
    }

    // ==========================================
    // TAB 4: PERFORMANCE RENDER
    // ==========================================
    function renderPerformanceTab() {
        const lBody = document.getElementById("teamPerformanceLeaderboardBody");
        if (lBody) {
            const sorted = [...allMembers].sort((a, b) => (b.revenue || 0) - (a.revenue || 0));
            lBody.innerHTML = sorted.map((m, idx) => `
                <tr>
                    <td><strong>#${idx + 1}</strong></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div class="avatar avatar-sm" style="background-color:${m.avatarBg}">${escapeHtml(m.avatar)}</div>
                            <strong>${escapeHtml(m.name)}</strong>
                        </div>
                    </td>
                    <td>${escapeHtml(m.role)}</td>
                    <td style="font-weight:700; color:var(--primary);">$${(m.revenue || 0).toLocaleString()}</td>
                    <td>$${(m.target || 0).toLocaleString()}</td>
                    <td><strong>${m.targetCompletion}%</strong></td>
                    <td>${m.dealsWon || 0} won</td>
                    <td>${escapeHtml(m.winRate)}</td>
                    <td>${(m.tasksCount || 0) + (m.meetingsCount || 0)} activities</td>
                </tr>
            `).join("");
        }
    }

    // ==========================================
    // MEMBER DETAILS DRAWER CONTROLLER
    // ==========================================
    function renderTeamNoteCardHtml(memberId, n) {
        const noteId = n.id || '';
        const author = n.author_name || n.author || 'Author';
        const date = n.created_at ? formatRelativeTimeJs(n.created_at) : (n.date || 'Just now');
        const content = n.content || n.text || '';
        const isPinned = !!n.is_pinned;

        return `
            <div class="note-card ${isPinned ? 'pinned' : ''}">
                <div class="note-card-header">
                    <span class="note-author">${escapeHtml(author)}</span>
                    <span class="note-date">${escapeHtml(date)}</span>
                </div>
                <div class="note-content">${escapeHtml(content)}</div>
                <div class="note-actions">
                    <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:${isPinned ? 'var(--primary, #2563EB)' : 'var(--text-muted, #64748B)'};" title="${isPinned ? 'Unpin note' : 'Pin note'}" onclick="window.teamApp.toggleMemberNotePin('${memberId}', '${noteId}')">
                        📌 ${isPinned ? 'Pinned' : 'Pin'}
                    </button>
                    <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:var(--text-secondary, #475569);" title="Edit note" onclick="window.teamApp.editMemberNote('${memberId}', '${noteId}')">Edit</button>
                    <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:#F04438;" title="Delete note" onclick="window.teamApp.deleteMemberNote('${memberId}', '${noteId}')">Delete</button>
                </div>
            </div>
        `;
    }

    function renderMemberNotesTabHtml(m) {
        const notes = m._notes || [];
        const query = (teamNoteSearchQuery || "").toLowerCase().trim();
        const filtered = notes.filter(n => {
            const text = (n.content || n.text || "").toLowerCase();
            const author = (n.author_name || n.author || "").toLowerCase();
            return !query || text.includes(query) || author.includes(query);
        });

        const pinned = filtered.filter(n => !!n.is_pinned);
        const recent = filtered.filter(n => !n.is_pinned);

        let html = `
            <!-- Search & Add Note Toolbar -->
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;">
                <div style="position:relative;flex:1;">
                    <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm" id="teamMemberNoteSearchInput" placeholder="Search notes..." style="padding-left:32px;width:100%;" value="${escapeHtml(teamNoteSearchQuery)}" oninput="window.teamApp.onMemberNoteSearch(this.value)">
                </div>
                <button type="button" class="btn btn-primary btn-xs" onclick="window.teamApp.toggleAddNoteForm(true)">
                    + Add Note
                </button>
            </div>

            <!-- Add Note Inline Form Card -->
            <div id="teamAddNoteCard" style="display:${isAddNoteFormOpen ? 'block' : 'none'};background:#FFFFFF;border:1px solid var(--border-card);border-radius:8px;padding:12px 14px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-weight:600;font-size:12.5px;color:var(--text-heading);margin-bottom:8px;">Add New Note</div>
                <textarea class="input-control" id="drawerNoteInput" style="height:70px;font-size:12.5px;resize:none;margin-bottom:10px;" placeholder="Write a note about ${escapeHtml(m.name)}..."></textarea>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-secondary);cursor:pointer;user-select:none;">
                        <input type="checkbox" id="drawerNotePinCheckbox"> 📌 Pin this note
                    </label>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.toggleAddNoteForm(false)">Cancel</button>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.teamApp.addMemberNote('${m.id}')">Save Note</button>
                    </div>
                </div>
            </div>

            <div id="teamNotesResultsList">
        `;

        if (filtered.length === 0) {
            html += `
                <div style="text-align:center;padding:32px 16px;color:var(--text-muted);font-size:13px;">
                    ${query ? 'No notes found matching your search.' : 'No notes found. Click <strong>"+ Add Note"</strong> to record a note for this team member.'}
                </div>
            `;
        } else {
            if (pinned.length > 0) {
                html += `
                    <div class="team-note-section-title" style="color:var(--primary, #2563EB);">📌 PINNED NOTES</div>
                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                        ${pinned.map(n => renderTeamNoteCardHtml(m.id, n)).join('')}
                    </div>
                `;
            }

            if (recent.length > 0) {
                html += `
                    <div class="team-note-section-title" style="color:var(--text-muted, #94A3B8);margin-top:${pinned.length > 0 ? '8px' : '0'};">RECENT NOTES</div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${recent.map(n => renderTeamNoteCardHtml(m.id, n)).join('')}
                    </div>
                `;
            }
        }

        html += `</div>`;
        return html;
    }

    function renderMemberDrawerContent(m) {
        // 1. Profile Top Card
        const cardEl = document.getElementById("drawerProfileCard");
        if (cardEl) {
            cardEl.innerHTML = `
                <div class="avatar avatar-lg" style="background-color:${m.avatarBg || '#7C3AED'}; margin: 0 auto 8px;" aria-hidden="true">${escapeHtml(m.avatar)}</div>
                <h4 style="font-size:16px; font-weight:700; color:var(--text-heading); margin:0 0 2px;">${escapeHtml(m.name)}</h4>
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px;">${escapeHtml(m.role)} • ${escapeHtml(m.department)}</div>
                <div style="display:flex; align-items:center; justify-content:center; gap:8px; font-size:12px;">
                    <span class="team-status-dot ${m.availability}"></span>
                    <span>${escapeHtml(m.availability)}</span>
                    <span style="color:var(--text-muted)">• ${escapeHtml(m.location)}</span>
                </div>
            `;
        }

        renderDrawerTabPane(m);
    }

    function renderDrawerTabPane(m) {
        // Panes
        document.querySelectorAll(".team-drawer-pane").forEach(pane => pane.classList.remove("active"));
        const activePane = document.getElementById(`dpane-${currentDrawerTab}`);
        if (activePane) activePane.classList.add("active");

        if (currentDrawerTab === "overview") {
            if (activePane) {
                activePane.innerHTML = `
                    <div style="font-size:11px; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.04em;">Personal Information</div>
                    <div class="team-detail-row"><span class="team-detail-label">Email</span><span class="team-detail-val">${escapeHtml(m.email)}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Phone</span><span class="team-detail-val">${escapeHtml(m.phone || 'N/A')}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Location</span><span class="team-detail-val">${escapeHtml(m.location)}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Timezone</span><span class="team-detail-val">${escapeHtml(m.timezone)}</span></div>

                    <div style="font-size:11px; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.04em; margin-top:10px;">Team Information</div>
                    <div class="team-detail-row"><span class="team-detail-label">Role</span><span class="team-detail-val">${escapeHtml(m.role)}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Department</span><span class="team-detail-val">${escapeHtml(m.department)}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Reports To</span><span class="team-detail-val">${escapeHtml(m.manager)}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Joined Date</span><span class="team-detail-val">${escapeHtml(m.joinedDate)}</span></div>

                    <div style="font-size:11px; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.04em; margin-top:10px;">Sales Summary</div>
                    <div class="team-detail-row"><span class="team-detail-label">Assigned Leads</span><span class="team-detail-val">${m.assignedLeads}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Open Deals</span><span class="team-detail-val">${m.openDeals}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Pipeline Value</span><span class="team-detail-val">$${(m.pipelineValue || 0).toLocaleString()}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Closed Revenue</span><span class="team-detail-val" style="color:var(--primary);">$${(m.revenue || 0).toLocaleString()}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Target Completion</span><span class="team-detail-val">${m.targetCompletion}%</span></div>
                `;
            }
        } else if (currentDrawerTab === "performance") {
            if (activePane) {
                activePane.innerHTML = `
                    <div class="team-notice-small">💡 Performance metrics derived dynamically from MySQL CRM records.</div>
                    <div class="team-detail-row"><span class="team-detail-label">Closed Won Revenue</span><span class="team-detail-val" style="color:#10B981">$${(m.revenue || 0).toLocaleString()}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Quota Target</span><span class="team-detail-val">$${(m.target || 0).toLocaleString()}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Target Completion</span><span class="team-detail-val">${m.targetCompletion}%</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Win Rate</span><span class="team-detail-val">${escapeHtml(m.winRate)}</span></div>
                    <div class="team-detail-row"><span class="team-detail-label">Avg Response Time</span><span class="team-detail-val">${escapeHtml(m.responseTime)}</span></div>
                `;
            }
        } else if (currentDrawerTab === "assignments") {
            if (activePane) {
                const leads = m._leads || [];
                const deals = m._deals || [];
                let dynamicRows = '';
                leads.forEach(l => {
                    dynamicRows += `<div class="team-detail-row"><span class="team-detail-label">👤 ${escapeHtml(l.name)} (${escapeHtml(l.company || 'Lead')})</span><span class="team-detail-val">${escapeHtml(l.status || 'Active')}</span></div>`;
                });
                deals.forEach(d => {
                    dynamicRows += `<div class="team-detail-row"><span class="team-detail-label">💼 ${escapeHtml(d.name)} (${escapeHtml(d.company || 'Deal')})</span><span class="team-detail-val">$${(Number(d.value) || 0).toLocaleString()}</span></div>`;
                });

                if (leads.length === 0 && deals.length === 0) {
                    dynamicRows = `<div style="text-align:center;padding:24px 10px;color:var(--text-muted);font-size:12.5px;">No assigned records found for this member.<br><button type="button" class="btn btn-secondary btn-xs" style="margin-top:10px;" onclick="window.teamApp.openAssignLeadModal()">+ Assign Lead</button></div>`;
                }

                activePane.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span style="font-size:11px; font-weight:700; color:var(--primary); text-transform:uppercase;">Assigned Records</span>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.teamApp.openAssignLeadModal()">+ Assign Lead</button>
                    </div>
                    ${dynamicRows}
                `;
            }
        } else if (currentDrawerTab === "activity") {
            if (activePane) {
                const acts = m._activities || [];
                let actHtml = '';
                if (acts.length === 0) {
                    actHtml = `<div style="text-align:center;padding:32px 16px;color:var(--text-muted);font-size:12.5px;">No activity history recorded for this member yet.</div>`;
                } else {
                    actHtml = `<div class="team-timeline">` + acts.map(a => `
                        <div class="team-timeline-item">
                            <span class="team-timeline-dot"></span>
                            <strong>${escapeHtml(a.title)}</strong> ${escapeHtml(a.description || '')}
                            <div style="font-size:10.5px; color:var(--text-muted);">${formatRelativeTimeJs(a.created_at)}</div>
                        </div>
                    `).join('') + `</div>`;
                }
                activePane.innerHTML = actHtml;
            }
        } else if (currentDrawerTab === "notes") {
            if (activePane) {
                activePane.innerHTML = renderMemberNotesTabHtml(m);
            }
        }
    }

    // ==========================================
    // INVITE / EDIT FORM ACTIONS (DATABASE)
    // ==========================================
    function saveInviteMemberForm() {
        const editId = document.getElementById("inviteEditMemberId").value;
        const firstName = document.getElementById("inviteFirstName").value.trim();
        const lastName = document.getElementById("inviteLastName").value.trim();
        const email = document.getElementById("inviteEmail").value.trim();
        const phone = document.getElementById("invitePhone").value.trim();
        const jobTitle = document.getElementById("inviteJobTitle").value.trim();
        const dept = document.getElementById("inviteDepartment").value;
        const location = document.getElementById("inviteLocation").value.trim() || "San Francisco, CA";
        const role = document.getElementById("inviteRole").value;
        const manager = document.getElementById("inviteManager").value;
        const avail = document.getElementById("inviteAvailability").value;

        if (!firstName || !lastName || !email || !jobTitle) {
            showTeamToast("Please fill in all required fields.");
            return;
        }

        const btnSubmit = document.getElementById("btnSubmitInviteForm");
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = editId ? "Saving..." : "Adding...";
        }

        const payload = {
            action: editId ? 'update_member' : 'invite_member',
            id: editId || '',
            first_name: firstName,
            last_name: lastName,
            email: email,
            phone: phone,
            job_title: jobTitle,
            department: dept,
            location: location,
            role: role,
            manager: manager,
            availability: avail,
            full_access: window.teamApp.isFormFullAccess ? 1 : 0,
            permissions: window.teamApp.currentFormPermissions
        };

        fetch('api/team.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.textContent = editId ? "Save Changes" : "Add Member";
            }

            if (res.success) {
                showTeamToast(res.message || (editId ? "Member updated." : "Member invited."));
                window.teamApp.closeInviteDrawer();

                // Also notify save-permissions for backward compatibility
                const targetUid = editId || res.data?.id;
                if (targetUid) {
                    try {
                        fetch('save-permissions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                user_id: targetUid,
                                full_access: window.teamApp.isFormFullAccess,
                                permissions: window.teamApp.currentFormPermissions
                            })
                        });
                        localStorage.setItem(`lf_perm_${targetUid}`, JSON.stringify({
                            full_access: window.teamApp.isFormFullAccess,
                            permissions: window.teamApp.currentFormPermissions
                        }));
                    } catch(e) {}
                }

                loadTeamData();
            } else {
                showTeamToast(res.message || "Failed to save member.");
            }
        })
        .catch(err => {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.textContent = editId ? "Save Changes" : "Add Member";
            }
            showTeamToast("Server error saving member.");
        });
    }

    function submitReassignForm() {
        const src = document.getElementById("reassignSourceRep").value;
        const tgt = document.getElementById("reassignTargetRep").value;
        const type = document.getElementById("reassignRecordType").value;

        if (!src || !tgt || src === tgt) {
            showTeamToast("Please select different source and destination members.");
            return;
        }

        fetch('api/team.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reassign_work',
                source_id: src,
                target_id: tgt,
                record_type: type
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showTeamToast(res.message || "Workload reassigned.");
                window.teamApp.closeReassignDrawer();
                loadTeamData();
            } else {
                showTeamToast(res.message || "Failed to reassign work.");
            }
        })
        .catch(() => {
            showTeamToast("Server error reassigning work.");
        });
    }

    // ==========================================
    // PUBLIC API FOR WINDOW.TEAMAPP
    // ==========================================
    window.teamApp = {
        switchTab: function (tabId, btnElem) {
            activeTab = tabId;
            renderActiveTab();
        },

        setViewMode: function (mode) {
            viewMode = mode;
            const btnList = document.getElementById("btnViewList");
            const btnGrid = document.getElementById("btnViewGrid");
            if (btnList) btnList.classList.toggle("active", mode === "list");
            if (btnGrid) btnGrid.classList.toggle("active", mode === "grid");

            const elList = document.getElementById("teamListView");
            const elGrid = document.getElementById("teamGridView");

            if (elList) elList.style.display = mode === "list" ? "block" : "none";
            if (elGrid) elGrid.style.display = mode === "grid" ? "grid" : "none";
        },

        handleSearchFilter: function () {
            searchFilter.query = document.getElementById("teamSearchInput").value.trim();
            searchFilter.dept = document.getElementById("teamDeptSelect").value;
            searchFilter.role = document.getElementById("teamRoleSelect").value;
            searchFilter.avail = document.getElementById("teamAvailSelect").value;
            searchFilter.sort = document.getElementById("teamSortSelect").value;

            applyFilterAndSort();
        },

        resetFilters: function () {
            searchFilter = { query: "", dept: "all", role: "all", avail: "all", sort: "name" };

            const sInput = document.getElementById("teamSearchInput");
            const dSelect = document.getElementById("teamDeptSelect");
            const rSelect = document.getElementById("teamRoleSelect");
            const aSelect = document.getElementById("teamAvailSelect");
            const sortSelect = document.getElementById("teamSortSelect");

            if (sInput) sInput.value = "";
            if (dSelect) dSelect.value = "all";
            if (rSelect) rSelect.value = "all";
            if (aSelect) aSelect.value = "all";
            if (sortSelect) sortSelect.value = "name";

            applyFilterAndSort();
            showTeamToast("Filters reset.");
        },

        removeFilterChip: function (key) {
            if (key === "query") { searchFilter.query = ""; const el = document.getElementById("teamSearchInput"); if (el) el.value = ""; }
            else if (key === "dept") { searchFilter.dept = "all"; const el = document.getElementById("teamDeptSelect"); if (el) el.value = "all"; }
            else if (key === "role") { searchFilter.role = "all"; const el = document.getElementById("teamRoleSelect"); if (el) el.value = "all"; }
            else if (key === "avail") { searchFilter.avail = "all"; const el = document.getElementById("teamAvailSelect"); if (el) el.value = "all"; }

            applyFilterAndSort();
        },

        toggleSelectAll: function (checked) {
            selectedMemberIds.clear();
            if (checked) {
                filteredMembers.forEach(m => selectedMemberIds.add(m.id));
            }
            renderMembersListAndGrid();
            updateBulkToolbar();
        },

        toggleMemberSelection: function (id, checked) {
            if (checked) selectedMemberIds.add(id);
            else selectedMemberIds.delete(id);
            updateBulkToolbar();
        },

        bulkDeactivate: function () {
            if (selectedMemberIds.size === 0) return;
            const ids = Array.from(selectedMemberIds);

            fetch('api/team.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'bulk_deactivate',
                    member_ids: ids
                })
            })
            .then(r => r.json())
            .then(res => {
                selectedMemberIds.clear();
                updateBulkToolbar();
                showTeamToast(res.message || "Members deactivated.");
                loadTeamData();
            });
        },

        openMemberDrawer: function (id) {
            currentDrawerMemberId = id;
            currentDrawerTab = "overview";
            teamNoteSearchQuery = "";
            isAddNoteFormOpen = false;

            const drawer = document.getElementById("teamMemberDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                document.body.style.overflow = "hidden";
            }

            const m = allMembers.find(item => String(item.id) === String(id));
            if (m) {
                const elBread = document.getElementById("drawerMemberBreadcrumb");
                if (elBread) elBread.textContent = m.name;

                const btnEdit = document.getElementById("btnEditCurrentMember");
                if (btnEdit) btnEdit.onclick = function () { window.teamApp.openInviteDrawer(id); };

                renderMemberDrawerContent(m);
            }

            // Fetch live rich member details
            fetch('api/team.php?action=member&id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(res => {
                if (res && res.success && res.data) {
                    const fresh = res.data.profile;
                    fresh._leads = res.data.leads || [];
                    fresh._deals = res.data.deals || [];
                    fresh._activities = res.data.activities || [];
                    fresh._notes = res.data.notes || [];

                    const idx = allMembers.findIndex(item => String(item.id) === String(id));
                    if (idx >= 0) allMembers[idx] = Object.assign(allMembers[idx], fresh);

                    renderMemberDrawerContent(fresh);
                }
            })
            .catch(e => console.error('Error fetching member details:', e));
        },

        closeMemberDrawer: function () {
            const drawer = document.getElementById("teamMemberDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }
            currentDrawerMemberId = null;
        },

        switchDrawerTab: function (tabId, btnElem) {
            currentDrawerTab = tabId;
            document.querySelectorAll(".team-drawer-tab-btn").forEach(btn => {
                btn.classList.toggle("active", btn.dataset.dtab === tabId);
            });

            const m = allMembers.find(item => String(item.id) === String(currentDrawerMemberId));
            if (m) renderDrawerTabPane(m);
        },

        onMemberNoteSearch: function (query) {
            teamNoteSearchQuery = query;
            const m = allMembers.find(item => String(item.id) === String(currentDrawerMemberId));
            if (m) {
                const container = document.getElementById("dpane-notes");
                if (container) container.innerHTML = renderMemberNotesTabHtml(m);
            }
        },

        toggleAddNoteForm: function (isOpen) {
            isAddNoteFormOpen = isOpen;
            const card = document.getElementById("teamAddNoteCard");
            if (card) card.style.display = isOpen ? "block" : "none";
            if (isOpen) {
                const input = document.getElementById("drawerNoteInput");
                if (input) input.focus();
            }
        },

        addMemberNote: function (memberId) {
            const contentEl = document.getElementById("drawerNoteInput");
            const pinEl = document.getElementById("drawerNotePinCheckbox");
            if (!contentEl) return;
            const content = contentEl.value.trim();
            if (!content) {
                showTeamToast("Please write a note before saving.");
                return;
            }

            fetch('api/team.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_note',
                    member_id: memberId,
                    content: content,
                    is_pinned: pinEl && pinEl.checked ? 1 : 0
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showTeamToast("Note saved.");
                    contentEl.value = "";
                    if (pinEl) pinEl.checked = false;
                    isAddNoteFormOpen = false;
                    window.teamApp.openMemberDrawer(memberId);
                } else {
                    showTeamToast(res.message || "Failed to save note.");
                }
            });
        },

        editMemberNote: function (memberId, noteId) {
            const m = allMembers.find(item => String(item.id) === String(memberId));
            if (!m || !m._notes) return;
            const note = m._notes.find(n => String(n.id) === String(noteId));
            if (!note) return;

            const newContent = prompt("Edit note content:", note.content || note.text || "");
            if (newContent === null) return;
            const trimmed = newContent.trim();
            if (!trimmed) return;

            fetch('api/team.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_note',
                    note_id: noteId,
                    content: trimmed
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showTeamToast("Note updated.");
                    window.teamApp.openMemberDrawer(memberId);
                }
            });
        },

        toggleMemberNotePin: function (memberId, noteId) {
            fetch('api/team.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_note_pin',
                    note_id: noteId
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    window.teamApp.openMemberDrawer(memberId);
                }
            });
        },

        deleteMemberNote: function (memberId, noteId) {
            if (!confirm("Are you sure you want to delete this note?")) return;

            fetch('api/team.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_note',
                    note_id: noteId
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showTeamToast("Note deleted.");
                    window.teamApp.openMemberDrawer(memberId);
                }
            });
        },

        openInviteDrawer: function (editId) {
            const form = document.getElementById("inviteMemberForm");
            if (form) form.reset();

            const titleEl = document.getElementById("inviteModalTitle");
            const btnSubmit = document.getElementById("btnSubmitInviteForm");
            const editHidden = document.getElementById("inviteEditMemberId");

            // Populate managers list dynamically from members
            const mgrSelect = document.getElementById("inviteManager");
            if (mgrSelect) {
                mgrSelect.innerHTML = `<option value="">Executive Director</option>` + allMembers.map(m => `
                    <option value="${m.id}">${escapeHtml(m.name)} (${escapeHtml(m.role)})</option>
                `).join('');
            }

            if (editId) {
                const m = allMembers.find(item => String(item.id) === String(editId));
                if (m) {
                    if (titleEl) titleEl.textContent = "Edit Team Member";
                    if (btnSubmit) btnSubmit.textContent = "Save Changes";
                    if (editHidden) editHidden.value = editId;

                    document.getElementById("inviteFirstName").value = m.firstName || m.name.split(" ")[0];
                    document.getElementById("inviteLastName").value = m.lastName || m.name.split(" ")[1] || "";
                    document.getElementById("inviteEmail").value = m.email;
                    document.getElementById("invitePhone").value = m.phone;
                    document.getElementById("inviteJobTitle").value = m.role;
                    document.getElementById("inviteDepartment").value = m.department;
                    document.getElementById("inviteLocation").value = m.location;
                    document.getElementById("inviteRole").value = m.role;
                    if (mgrSelect && m.reportsToId) mgrSelect.value = String(m.reportsToId);
                    document.getElementById("inviteAvailability").value = m.availability;

                    window.teamApp.initPermissionSection(editId, m.role);
                }
            } else {
                if (titleEl) titleEl.textContent = "Invite Team Member";
                if (btnSubmit) btnSubmit.textContent = "Add Member";
                if (editHidden) editHidden.value = "";

                window.teamApp.initPermissionSection(null, document.getElementById("inviteRole").value);
            }

            const drawer = document.getElementById("teamInviteDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                document.body.style.overflow = "hidden";
            }
        },

        closeInviteDrawer: function () {
            const drawer = document.getElementById("teamInviteDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }
        },

        updateInviteRolePreview: function (roleTitle) {
            if (!this.isCustomPermissionEdited) {
                this.applyRolePermissionsDefault(roleTitle);
            }
        },

        // =================================================================
        // PERMISSIONS & ACCESS CONTROL MANAGEMENT
        // =================================================================
        permissionModules: [
            {
                category: "CORE / CRM",
                items: [
                    { id: "dashboard", name: "Dashboard", actions: ["view"] },
                    { id: "leads", name: "Leads", actions: ["view", "create", "edit", "delete", "export"] },
                    { id: "pipeline", name: "Sales Pipeline", actions: ["view", "create", "edit", "delete"] },
                    { id: "contacts", name: "Contacts", actions: ["view", "create", "edit", "delete", "export"] },
                    { id: "companies", name: "Companies", actions: ["view", "create", "edit", "delete", "export"] },
                    { id: "subscriptions", name: "Subscriptions", actions: ["view", "create", "edit", "delete", "export"] },
                    { id: "expenses", name: "Expenses", actions: ["view", "create", "edit", "delete", "export"] },
                    { id: "invoices", name: "Invoices", actions: ["view", "create", "edit", "delete", "download", "send", "export"] },
                    { id: "contracts", name: "Contracts", actions: ["view", "create", "edit", "delete", "download", "send", "export"] },
                    { id: "estimate-requests", name: "Estimate Requests", actions: ["view", "create", "edit", "delete", "download", "send", "export"] },
                    { id: "tasks", name: "Tasks", actions: ["view", "create", "edit", "delete"] },
                    { id: "calendar", name: "Calendar", actions: ["view", "create", "edit", "delete"] }
                ]
            },
            {
                category: "COMMUNICATION / WORKSPACE",
                items: [
                    { id: "inbox", name: "Messages / Inbox", actions: ["view", "create", "send"] },
                    { id: "reports", name: "Reports & Analytics", actions: ["view", "export"] }
                ]
            },
            {
                category: "ACCOUNT / ADMIN",
                items: [
                    { id: "team", name: "Team Management", actions: ["view", "create", "edit", "delete", "manage"] },
                    { id: "settings", name: "Settings", actions: ["view", "edit"] },
                    { id: "help", name: "Help & Support", actions: ["view"] }
                ]
            }
        ],

        currentFormPermissions: {},
        isFormFullAccess: false,
        isCustomPermissionEdited: false,
        permSearchQuery: "",

        initPermissionSection: function (memberId, roleName) {
            this.permSearchQuery = "";
            const searchInput = document.getElementById("permSearchInput");
            if (searchInput) searchInput.value = "";

            this.isCustomPermissionEdited = false;

            if (memberId) {
                const storedPerms = localStorage.getItem(`lf_perm_${memberId}`);
                if (storedPerms) {
                    try {
                        const parsed = JSON.parse(storedPerms);
                        this.isFormFullAccess = !!parsed.full_access;
                        this.currentFormPermissions = parsed.permissions || {};
                        this.isCustomPermissionEdited = true;
                    } catch (e) {
                        this.applyRolePermissionsDefault(roleName || 'Sales Representative');
                    }
                } else if (String(memberId) === '1' || String(memberId) === 'TM-001') {
                    this.isFormFullAccess = true;
                    this.selectAllPermissions();
                } else {
                    this.applyRolePermissionsDefault(roleName || 'Sales Representative');
                }
            } else {
                this.applyRolePermissionsDefault(roleName || 'Sales Representative');
            }

            this.renderPermissionsList();
        },

        applyRolePermissionsDefault: function (roleName) {
            const roleClean = (roleName || '').toLowerCase();
            this.currentFormPermissions = {};

            if (roleClean.includes('admin') || roleClean.includes('super')) {
                this.isFormFullAccess = true;
                this.permissionModules.forEach(cat => {
                    cat.items.forEach(mod => {
                        this.currentFormPermissions[mod.id] = [...mod.actions];
                    });
                });
            } else if (roleClean.includes('manager')) {
                this.isFormFullAccess = false;
                this.permissionModules.forEach(cat => {
                    cat.items.forEach(mod => {
                        if (['team', 'settings'].includes(mod.id)) {
                            this.currentFormPermissions[mod.id] = ['view'];
                        } else {
                            this.currentFormPermissions[mod.id] = mod.actions.filter(a => a !== 'manage');
                        }
                    });
                });
            } else {
                this.isFormFullAccess = false;
                this.currentFormPermissions = {
                    'dashboard': ['view'],
                    'leads': ['view', 'create', 'edit'],
                    'pipeline': ['view', 'create', 'edit'],
                    'contacts': ['view', 'create', 'edit'],
                    'companies': ['view'],
                    'tasks': ['view', 'create', 'edit'],
                    'calendar': ['view', 'create'],
                    'inbox': ['view', 'send'],
                    'help': ['view']
                };
            }

            this.renderPermissionsList();
        },

        renderPermissionsList: function () {
            const container = document.getElementById("permListContainer");
            if (!container) return;

            let html = "";
            const query = (this.permSearchQuery || "").toLowerCase().trim();

            this.permissionModules.forEach(cat => {
                const matchingItems = cat.items.filter(item => !query || item.name.toLowerCase().includes(query) || item.id.includes(query));
                if (matchingItems.length === 0) return;

                html += `<div class="perm-category-group">`;
                html += `<div class="perm-category-header">${escapeHtml(cat.category)}</div>`;

                matchingItems.forEach(mod => {
                    const activeActions = this.currentFormPermissions[mod.id] || [];
                    const isModuleOn = activeActions.length > 0 && activeActions.includes("view");

                    html += `
                        <div class="perm-module-card ${isModuleOn ? 'on' : 'off'}">
                            <div class="perm-module-top">
                                <span class="perm-module-name">${escapeHtml(mod.name)}</span>
                                <label class="perm-toggle-switch" title="Toggle module access">
                                    <input type="checkbox" ${isModuleOn ? 'checked' : ''} onchange="window.teamApp.toggleModuleSwitch('${mod.id}', this.checked)">
                                    <span class="perm-slider"></span>
                                </label>
                            </div>
                            <div class="perm-actions-row">
                    `;

                    mod.actions.forEach(act => {
                        const isChecked = activeActions.includes(act);
                        const labelText = act === 'manage' ? 'Manage Permissions' : act.charAt(0).toUpperCase() + act.slice(1);

                        html += `
                            <label class="perm-action-label">
                                <input type="checkbox" ${isChecked ? 'checked' : ''} ${!isModuleOn && act !== 'view' ? 'disabled' : ''} onchange="window.teamApp.toggleActionCheckbox('${mod.id}', '${act}', this.checked)">
                                <span>${escapeHtml(labelText)}</span>
                            </label>
                        `;
                    });

                    html += `
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
            });

            if (!html) {
                html = `<div style="text-align:center; padding:20px; font-size:12px; color:var(--text-muted);">No permissions match "${escapeHtml(query)}"</div>`;
            }

            container.innerHTML = html;
            this.updatePermissionSummaryBadge();
        },

        updatePermissionSummaryBadge: function () {
            const badge = document.getElementById("permSummaryBadge");
            const btnFull = document.getElementById("btnToggleFullAccess");

            let activeModules = 0;
            let totalPerms = 0;

            Object.keys(this.currentFormPermissions).forEach(modId => {
                const actions = this.currentFormPermissions[modId];
                if (Array.isArray(actions) && actions.length > 0) {
                    activeModules++;
                    totalPerms += actions.length;
                }
            });

            if (badge) {
                if (this.isFormFullAccess) {
                    badge.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Full Access Enabled`;
                    badge.style.backgroundColor = "var(--primary-subtle, #EFF6FF)";
                    badge.style.color = "var(--primary, #2563EB)";
                } else {
                    badge.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> ${activeModules} Modules • ${totalPerms} Permissions Enabled`;
                    badge.style.backgroundColor = "var(--bg-card, #F8FAFC)";
                    badge.style.color = "var(--text-secondary, #475569)";
                }
            }

            if (btnFull) {
                btnFull.classList.toggle("active", this.isFormFullAccess);
                btnFull.innerHTML = this.isFormFullAccess ?
                    `<svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg> Full Access Enabled` :
                    `<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Enable Full Access`;
            }
        },

        toggleModuleSwitch: function (modId, enabled) {
            this.isCustomPermissionEdited = true;
            this.isFormFullAccess = false;

            let modDef = null;
            this.permissionModules.forEach(cat => {
                const found = cat.items.find(i => i.id === modId);
                if (found) modDef = found;
            });

            if (!modDef) return;

            if (enabled) {
                this.currentFormPermissions[modId] = [...modDef.actions];
            } else {
                delete this.currentFormPermissions[modId];
            }

            this.renderPermissionsList();
        },

        toggleActionCheckbox: function (modId, action, checked) {
            this.isCustomPermissionEdited = true;
            this.isFormFullAccess = false;

            if (!this.currentFormPermissions[modId]) {
                this.currentFormPermissions[modId] = [];
            }

            if (checked) {
                if (!this.currentFormPermissions[modId].includes(action)) {
                    this.currentFormPermissions[modId].push(action);
                }
                if (action !== "view" && !this.currentFormPermissions[modId].includes("view")) {
                    this.currentFormPermissions[modId].push("view");
                }
            } else {
                this.currentFormPermissions[modId] = this.currentFormPermissions[modId].filter(a => a !== action);
                if (action === "view") {
                    delete this.currentFormPermissions[modId];
                }
            }

            this.renderPermissionsList();
        },

        toggleFullAccess: function () {
            this.isCustomPermissionEdited = true;
            this.isFormFullAccess = !this.isFormFullAccess;

            if (this.isFormFullAccess) {
                this.selectAllPermissions();
            } else {
                this.clearAllPermissions();
            }
        },

        selectAllPermissions: function () {
            this.isCustomPermissionEdited = true;
            this.currentFormPermissions = {};
            this.permissionModules.forEach(cat => {
                cat.items.forEach(mod => {
                    this.currentFormPermissions[mod.id] = [...mod.actions];
                });
            });
            this.renderPermissionsList();
        },

        clearAllPermissions: function () {
            this.isCustomPermissionEdited = true;
            this.isFormFullAccess = false;
            this.currentFormPermissions = {};
            this.renderPermissionsList();
        },

        filterPermissions: function (query) {
            this.permSearchQuery = query;
            this.renderPermissionsList();
        },

        // ==========================================
        // REASSIGN WORKLOAD DRAWER
        // ==========================================
        openReassignDrawer: function (sourceMemberId) {
            const drawer = document.getElementById("teamReassignDrawer");
            const srcSelect = document.getElementById("reassignSourceRep");
            const tgtSelect = document.getElementById("reassignTargetRep");

            if (srcSelect && tgtSelect) {
                srcSelect.innerHTML = allMembers.map(m => `
                    <option value="${m.id}" ${String(m.id) === String(sourceMemberId) ? 'selected' : ''}>${escapeHtml(m.name)} (${escapeHtml(m.role)})</option>
                `).join("");

                tgtSelect.innerHTML = allMembers.map(m => `
                    <option value="${m.id}" ${String(m.id) !== String(sourceMemberId) ? 'selected' : ''}>${escapeHtml(m.name)} (${escapeHtml(m.role)})</option>
                `).join("");
            }

            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                document.body.style.overflow = "hidden";
            }
        },

        closeReassignDrawer: function () {
            const drawer = document.getElementById("teamReassignDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }
        },

        // ==========================================
        // ASSIGN LEAD MODAL
        // ==========================================
        openAssignLeadModal: function () {
            const modal = document.getElementById("teamAssignLeadModal");
            if (!modal) return;

            const m = allMembers.find(member => String(member.id) === String(currentDrawerMemberId));
            if (!m) return;

            const summaryDiv = document.getElementById("assignLeadMemberSummary");
            if (summaryDiv) {
                summaryDiv.innerHTML = `
                    <div class="team-avatar" style="width:40px; height:40px; font-size:14px; background-color:var(--primary); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                        ${escapeHtml(m.avatar)}
                    </div>
                    <div>
                        <div style="font-weight:600; font-size:14px; color:var(--text-heading);">${escapeHtml(m.name)}</div>
                        <div style="font-size:12px; color:var(--text-secondary);">${escapeHtml(m.role)} &bull; ${escapeHtml(m.department)}</div>
                        <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;">
                            <span class="team-status-dot ${m.availability}"></span>
                            ${escapeHtml(m.availability)}
                        </div>
                    </div>
                `;
            }

            const sInput = document.getElementById("assignLeadSearchInput");
            if (sInput) sInput.value = "";
            selectedLeadForAssignment = null;
            const btnSub = document.getElementById("btnSubmitAssignLead");
            if (btnSub) btnSub.disabled = true;

            // Fetch live leads for assignment
            fetch('api/team.php?action=leads_for_assignment')
            .then(r => r.json())
            .then(res => {
                if (res && res.success) {
                    availableLeadsForAssignment = res.data.leads || [];
                    window.teamApp.filterAssignLeads();
                }
            });

            modal.classList.add("is-open");
            if (sInput) sInput.focus();
        },

        closeAssignLeadModal: function () {
            const modal = document.getElementById("teamAssignLeadModal");
            if (modal) modal.classList.remove("is-open");
            selectedLeadForAssignment = null;
        },

        filterAssignLeads: function () {
            const query = (document.getElementById("assignLeadSearchInput")?.value || "").toLowerCase();
            const container = document.getElementById("assignLeadListContainer");
            if (!container) return;

            let html = "";
            let found = 0;
            availableLeadsForAssignment.forEach(lead => {
                const nameMatch = (lead.name || "").toLowerCase().includes(query);
                const compMatch = (lead.company || "").toLowerCase().includes(query);
                const emailMatch = (lead.email || "").toLowerCase().includes(query);

                if (!query || nameMatch || compMatch || emailMatch) {
                    found++;
                    const isAlreadyAssigned = String(lead.assigned_to) === String(currentDrawerMemberId);
                    const isSelected = String(selectedLeadForAssignment) === String(lead.id);
                    let style = isSelected ? "background-color: #EFF6FF;" : (isAlreadyAssigned ? "opacity:0.6; cursor:not-allowed; background-color:#F8FAFC;" : "cursor:pointer;");

                    html += `
                        <div class="assign-lead-card ${isSelected ? 'selected' : ''}" style="${style}; display:flex; align-items:center; gap:12px; padding:12px; border-bottom:1px solid var(--border-card);" 
                             onclick="${isAlreadyAssigned ? '' : `window.teamApp.selectAssignLead('${lead.id}')`}">
                            <div class="team-avatar" style="width:32px; height:32px; font-size:12px; border-radius:50%; background:#E0F2FE; color:#0369A1; display:flex; align-items:center; justify-content:center;">
                                ${(lead.name || 'L').substr(0, 2).toUpperCase()}
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:600; font-size:13px; color:var(--text-heading); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(lead.name)}</div>
                                <div style="font-size:12px; color:var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(lead.company || 'No company')} &bull; ${escapeHtml(lead.email || '')}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:12px; font-weight:600; color:var(--text-heading);">$${(Number(lead.value) || 0).toLocaleString()}</div>
                                <div style="font-size:11px; color:var(--primary);">${escapeHtml(lead.status)}</div>
                                ${isAlreadyAssigned ? '<div style="font-size:10px; color:#EF4444; margin-top:2px;">Already assigned</div>' : (lead.assigned_to ? `<div style="font-size:10px; color:var(--text-muted); margin-top:2px;">Assigned</div>` : '')}
                            </div>
                        </div>
                    `;
                }
            });

            if (found === 0) {
                html = `<div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">No leads found in database.</div>`;
            }
            container.innerHTML = html;
        },

        selectAssignLead: function (id) {
            selectedLeadForAssignment = id;
            window.teamApp.filterAssignLeads();
            const btn = document.getElementById("btnSubmitAssignLead");
            if (btn) btn.disabled = false;
        },

        submitAssignLead: function () {
            if (!selectedLeadForAssignment || !currentDrawerMemberId) return;
            const noteVal = document.getElementById("assignLeadNote")?.value || "";

            fetch('api/team.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'assign_lead',
                    member_id: currentDrawerMemberId,
                    lead_id: selectedLeadForAssignment,
                    note: noteVal
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showTeamToast(res.message || "Lead assigned.");
                    window.teamApp.closeAssignLeadModal();
                    loadTeamData(() => {
                        window.teamApp.openMemberDrawer(currentDrawerMemberId);
                    });
                } else {
                    showTeamToast(res.message || "Failed to assign lead.");
                }
            });
        },

        exportCSV: function () {
            const dropdown = document.getElementById("teamExportDropdownWrapper");
            if (dropdown) dropdown.classList.remove("open");
            window.location.href = 'api/team.php?action=export_csv';
        },

        printPDF: function () {
            const dropdown = document.getElementById("teamExportDropdownWrapper");
            if (dropdown) dropdown.classList.remove("open");
            window.print();
        }
    };

    function updateBulkToolbar() {
        const toolbar = document.getElementById("teamBulkToolbar");
        const countEl = document.getElementById("teamBulkSelectedCount");
        const count = selectedMemberIds.size;

        if (countEl) countEl.textContent = `${count} selected`;
        if (toolbar) toolbar.classList.toggle("show", count > 0);
    }

    function getRoleBadgeBg(role) {
        if (!role) return "#E0F2FE";
        if (role.includes("Manager")) return "#F3E8FF";
        if (role.includes("Senior")) return "#E0F2FE";
        if (role.includes("Executive")) return "#FEF3C7";
        if (role.includes("Operations")) return "#FCE7F3";
        if (role.includes("Admin") || role.includes("super")) return "#FEE2E2";
        return "#E0F2FE";
    }

    function getRoleBadgeColor(role) {
        if (!role) return "#0369A1";
        if (role.includes("Manager")) return "#6B21A8";
        if (role.includes("Senior")) return "#075985";
        if (role.includes("Executive")) return "#92400E";
        if (role.includes("Operations")) return "#9D174D";
        if (role.includes("Admin") || role.includes("super")) return "#991B1B";
        return "#0369A1";
    }

    function getAccessBadgeHtml(m) {
        if (!m || !m.id) return '';
        const stored = localStorage.getItem(`lf_perm_${m.id}`);
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                if (parsed.full_access) {
                    return `<span class="badge" style="background:#2563EB;color:#FFFFFF;font-size:9.5px;font-weight:600;padding:1px 6px;border-radius:10px;margin-top:3px;display:inline-block;">Full Access</span>`;
                }
                const count = Object.keys(parsed.permissions || {}).length;
                return `<span class="badge" style="background:#E0F2FE;color:#0369A1;font-size:9.5px;font-weight:600;padding:1px 6px;border-radius:10px;margin-top:3px;display:inline-block;">${count} Modules</span>`;
            } catch (e) {}
        }
        if (m.role.includes('Executive') || m.role.includes('Admin') || m.role.includes('super')) {
            return `<span class="badge" style="background:#2563EB;color:#FFFFFF;font-size:9.5px;font-weight:600;padding:1px 6px;border-radius:10px;margin-top:3px;display:inline-block;">Full Access</span>`;
        }
        if (m.role.includes('Manager')) {
            return `<span class="badge" style="background:#E0F2FE;color:#0369A1;font-size:9.5px;font-weight:600;padding:1px 6px;border-radius:10px;margin-top:3px;display:inline-block;">16 Modules</span>`;
        }
        return `<span class="badge" style="background:#F1F5F9;color:#475569;font-size:9.5px;font-weight:600;padding:1px 6px;border-radius:10px;margin-top:3px;display:inline-block;">8 Modules</span>`;
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function formatRelativeTimeJs(dateStr) {
        if (!dateStr) return 'Just now';
        const d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    window.showTeamToast = function (msg) {
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
