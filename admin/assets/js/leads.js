/**
 * NexFlow CRM - Leads Page & Enhanced Lead Details Drawer JavaScript
 * Manages search, status tab filtering, row selection, Add Lead modal,
 * comprehensive 5-tab Lead Details Drawer, and Single Child Modal Manager.
 */

let activeDrawerLeadId = null;
let activeDrawerTab = "overview";
let activityFilter = "All";
let taskFilter = "All";
let noteSearchQuery = "";

// Single Child Modal Manager State
let activeLeadModalType = null; // null | "meeting" | "task" | "note" | "edit" | "convert" | "status" | "owner"
let lastModalTriggerElement = null;
let editingDrawerNoteId = null;
let pendingDeleteType = null;
let pendingDeleteTargetId = null;

const LEAD_MODAL_MAP = {
    meeting: "scheduleMeetingModal",
    task: "addDrawerTaskModal",
    note: "addDrawerNoteModal",
    edit: "editLeadModal",
    convert: "convertContactModal",
    status: "quickStatusModal",
    owner: "quickOwnerModal"
};

// Organization leads store from MySQL backend
const LEADS_API = "api/leads.php";
let leadsStore = Array.isArray(window.INITIAL_LEADS_DATA) ? [...window.INITIAL_LEADS_DATA] : [];
window.leadsStore = leadsStore;
const leadTasksStore = {};
const leadNotesStore = {};
const leadDealsStore = {};
const leadActivitiesStore = {};
const leadMeetingsStore = {};

// Advanced Filter Manager State
const STORAGE_LEAD_FILTERS_KEY = "NexFlow.leads.filters.v1";

const defaultLeadFilters = {
    leadState: "active",
    statuses: [],
    assignees: [],
    scoreMin: 0,
    scoreMax: 100,
    valueMin: null,
    valueMax: null,
    sources: [],
    lastActivity: "any"
};

let activeLeadFilters = loadStoredLeadFilters();
let draftLeadFilters = JSON.parse(JSON.stringify(activeLeadFilters));

function loadStoredLeadFilters() {
    try {
        const stored = localStorage.getItem(STORAGE_LEAD_FILTERS_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            return {
                leadState: (parsed.leadState === 'archived' || parsed.leadState === 'all') ? parsed.leadState : "active",
                statuses: Array.isArray(parsed.statuses) ? parsed.statuses : [],
                assignees: Array.isArray(parsed.assignees) ? parsed.assignees : [],
                scoreMin: typeof parsed.scoreMin === 'number' ? parsed.scoreMin : 0,
                scoreMax: typeof parsed.scoreMax === 'number' ? parsed.scoreMax : 100,
                valueMin: (typeof parsed.valueMin === 'number' && !isNaN(parsed.valueMin)) ? parsed.valueMin : null,
                valueMax: (typeof parsed.valueMax === 'number' && !isNaN(parsed.valueMax)) ? parsed.valueMax : null,
                sources: Array.isArray(parsed.sources) ? parsed.sources : [],
                lastActivity: typeof parsed.lastActivity === 'string' ? parsed.lastActivity : "any"
            };
        }
    } catch (e) {
        console.warn("Unable to load lead filters from localStorage:", e);
    }
    return JSON.parse(JSON.stringify(defaultLeadFilters));
}

function saveActiveLeadFilters() {
    try {
        localStorage.setItem(STORAGE_LEAD_FILTERS_KEY, JSON.stringify(activeLeadFilters));
    } catch (e) {
        console.warn("Unable to save lead filters to localStorage:", e);
    }
}

function parseDaysAgo(activityStr) {
    if (!activityStr) return 0;
    const str = activityStr.toLowerCase().trim();
    if (str.includes("just now") || str.includes("today") || str.includes("h ago") || str.includes("m ago")) return 0;
    if (str.includes("yesterday")) return 1;
    const dMatch = str.match(/(\d+)\s*d\s*ago/);
    if (dMatch) return parseInt(dMatch[1], 10);
    const wMatch = str.match(/(\d+)\s*w\s*ago/);
    if (wMatch) return parseInt(wMatch[1], 10) * 7;
    const mMatch = str.match(/(\d+)\s*m\s*ago/);
    if (mMatch) return parseInt(mMatch[1], 10) * 30;
    return 0;
}

function toggleLeadFilterPopover(e) {
    if (e) e.stopPropagation();
    const popover = document.getElementById("leadsFilterPopover");
    const btn = document.getElementById("btnLeadFilter");
    if (!popover) return;

    const isOpen = popover.classList.contains("show");
    if (isOpen) {
        closeLeadFilterPopover();
    } else {
        draftLeadFilters = JSON.parse(JSON.stringify(activeLeadFilters));
        populateDynamicFilterOptions();
        syncFilterUiFromDraft();
        popover.classList.add("show");
        if (btn) btn.setAttribute("aria-expanded", "true");
    }
}

function closeLeadFilterPopover() {
    const popover = document.getElementById("leadsFilterPopover");
    const btn = document.getElementById("btnLeadFilter");
    if (popover) popover.classList.remove("show");
    if (btn) {
        btn.setAttribute("aria-expanded", "false");
        btn.focus();
    }
}

function populateDynamicFilterOptions() {
    const rows = document.querySelectorAll("#leadsTbody tr[data-id]");
    const assigneesSet = new Set();
    const sourcesSet = new Set();

    rows.forEach(r => {
        const a = r.getAttribute("data-assignee");
        const s = r.getAttribute("data-source");
        if (a) assigneesSet.add(a);
        if (s) sourcesSet.add(s);
    });

    const assigneeContainer = document.getElementById("filterAssigneeOptions");
    if (assigneeContainer) {
        assigneeContainer.innerHTML = Array.from(assigneesSet).sort().map(a => `
            <label class="leads-filter-checkbox-item">
                <input type="checkbox" value="${escapeHtml(a)}" class="filter-assignee-cb"> ${escapeHtml(a)}
            </label>
        `).join('');
    }

    const sourceContainer = document.getElementById("filterSourceOptions");
    if (sourceContainer) {
        sourceContainer.innerHTML = Array.from(sourcesSet).sort().map(s => `
            <label class="leads-filter-checkbox-item">
                <input type="checkbox" value="${escapeHtml(s)}" class="filter-source-cb"> ${escapeHtml(s)}
            </label>
        `).join('');
    }
}

function syncFilterUiFromDraft() {
    document.querySelectorAll(".filter-archive-radio").forEach(r => {
        r.checked = (r.value === (draftLeadFilters.leadState || "active"));
    });

    document.querySelectorAll(".filter-status-cb").forEach(cb => {
        cb.checked = draftLeadFilters.statuses.includes(cb.value);
    });

    document.querySelectorAll(".filter-assignee-cb").forEach(cb => {
        cb.checked = draftLeadFilters.assignees.includes(cb.value);
    });

    const scoreMinEl = document.getElementById("filterScoreMin");
    const scoreMaxEl = document.getElementById("filterScoreMax");
    if (scoreMinEl) scoreMinEl.value = draftLeadFilters.scoreMin;
    if (scoreMaxEl) scoreMaxEl.value = draftLeadFilters.scoreMax;

    document.querySelectorAll(".filter-score-chip").forEach(chip => {
        const min = parseInt(chip.getAttribute("data-min"), 10);
        const max = parseInt(chip.getAttribute("data-max"), 10);
        if (draftLeadFilters.scoreMin === min && draftLeadFilters.scoreMax === max) {
            chip.classList.add("active");
        } else {
            chip.classList.remove("active");
        }
    });

    const valMinEl = document.getElementById("filterValueMin");
    const valMaxEl = document.getElementById("filterValueMax");
    if (valMinEl) valMinEl.value = (draftLeadFilters.valueMin !== null) ? draftLeadFilters.valueMin : "";
    if (valMaxEl) valMaxEl.value = (draftLeadFilters.valueMax !== null) ? draftLeadFilters.valueMax : "";

    document.querySelectorAll(".filter-source-cb").forEach(cb => {
        cb.checked = draftLeadFilters.sources.includes(cb.value);
    });

    const actEl = document.getElementById("filterLastActivity");
    if (actEl) actEl.value = draftLeadFilters.lastActivity || "any";

    const scoreErr = document.getElementById("filterScoreError");
    const valErr = document.getElementById("filterValueError");
    if (scoreErr) scoreErr.style.display = "none";
    if (valErr) valErr.style.display = "none";
}

function applyScoreQuickChip(min, max, btnElement) {
    const scoreMinEl = document.getElementById("filterScoreMin");
    const scoreMaxEl = document.getElementById("filterScoreMax");
    if (scoreMinEl) scoreMinEl.value = min;
    if (scoreMaxEl) scoreMaxEl.value = max;

    document.querySelectorAll(".filter-score-chip").forEach(c => c.classList.remove("active"));
    if (btnElement) btnElement.classList.add("active");
}

function readDraftFromUi() {
    const stateRadio = document.querySelector('.filter-archive-radio:checked');
    const leadState = stateRadio ? stateRadio.value : "active";

    const statuses = Array.from(document.querySelectorAll(".filter-status-cb:checked")).map(cb => cb.value);
    const assignees = Array.from(document.querySelectorAll(".filter-assignee-cb:checked")).map(cb => cb.value);
    const sources = Array.from(document.querySelectorAll(".filter-source-cb:checked")).map(cb => cb.value);

    const scoreMinEl = document.getElementById("filterScoreMin");
    const scoreMaxEl = document.getElementById("filterScoreMax");
    const sMin = scoreMinEl && scoreMinEl.value !== "" ? parseInt(scoreMinEl.value, 10) : 0;
    const sMax = scoreMaxEl && scoreMaxEl.value !== "" ? parseInt(scoreMaxEl.value, 10) : 100;

    const valMinEl = document.getElementById("filterValueMin");
    const valMaxEl = document.getElementById("filterValueMax");
    const vMin = valMinEl && valMinEl.value !== "" ? parseFloat(valMinEl.value) : null;
    const vMax = valMaxEl && valMaxEl.value !== "" ? parseFloat(valMaxEl.value) : null;

    const actEl = document.getElementById("filterLastActivity");
    const lastActivity = actEl ? actEl.value : "any";

    return {
        leadState,
        statuses,
        assignees,
        scoreMin: isNaN(sMin) ? 0 : sMin,
        scoreMax: isNaN(sMax) ? 100 : sMax,
        valueMin: (vMin !== null && !isNaN(vMin)) ? vMin : null,
        valueMax: (vMax !== null && !isNaN(vMax)) ? vMax : null,
        sources,
        lastActivity
    };
}

function applyLeadFilters() {
    const draft = readDraftFromUi();

    const scoreErr = document.getElementById("filterScoreError");
    const valErr = document.getElementById("filterValueError");
    let hasError = false;

    if (draft.scoreMin > draft.scoreMax) {
        if (scoreErr) scoreErr.style.display = "block";
        hasError = true;
    } else if (scoreErr) {
        scoreErr.style.display = "none";
    }

    if (draft.valueMin !== null && draft.valueMax !== null && draft.valueMin > draft.valueMax) {
        if (valErr) valErr.style.display = "block";
        hasError = true;
    } else if (valErr) {
        valErr.style.display = "none";
    }

    if (hasError) return;

    const prevState = activeLeadFilters.leadState || "active";
    activeLeadFilters = JSON.parse(JSON.stringify(draft));
    saveActiveLeadFilters();
    closeLeadFilterPopover();
    updateFilterButtonBadge();

    if (activeLeadFilters.leadState !== prevState) {
        loadLeadsByArchiveState(activeLeadFilters.leadState);
    } else if (typeof window.filterTableRows === "function") {
        window.filterTableRows(true);
    }
}

function clearDraftLeadFilters() {
    draftLeadFilters = JSON.parse(JSON.stringify(defaultLeadFilters));
    syncFilterUiFromDraft();
}

function clearAdvancedLeadFilters() {
    const prevState = activeLeadFilters.leadState || "active";
    activeLeadFilters = JSON.parse(JSON.stringify(defaultLeadFilters));
    draftLeadFilters = JSON.parse(JSON.stringify(defaultLeadFilters));
    saveActiveLeadFilters();
    syncFilterUiFromDraft();
    updateFilterButtonBadge();
    if (activeLeadFilters.leadState !== prevState) {
        loadLeadsByArchiveState(activeLeadFilters.leadState);
    } else if (typeof window.filterTableRows === "function") {
        window.filterTableRows(true);
    }
}

function countActiveFilterGroups() {
    let count = 0;
    if (activeLeadFilters.leadState && activeLeadFilters.leadState !== "active") count++;
    if (activeLeadFilters.statuses.length > 0) count++;
    if (activeLeadFilters.assignees.length > 0) count++;
    if (activeLeadFilters.scoreMin > 0 || activeLeadFilters.scoreMax < 100) count++;
    if (activeLeadFilters.valueMin !== null || activeLeadFilters.valueMax !== null) count++;
    if (activeLeadFilters.sources.length > 0) count++;
    if (activeLeadFilters.lastActivity !== "any") count++;
    return count;
}

function updateFilterButtonBadge() {
    const btn = document.getElementById("btnLeadFilter");
    const badge = document.getElementById("leadsFilterBadge");
    const activeCount = countActiveFilterGroups();

    if (btn) {
        if (activeCount > 0) {
            btn.classList.add("active");
            btn.setAttribute("aria-label", `Filter leads, ${activeCount} active filters`);
        } else {
            btn.classList.remove("active");
            btn.setAttribute("aria-label", "Filter leads");
        }
    }

    if (badge) {
        if (activeCount > 0) {
            badge.textContent = activeCount;
            badge.style.display = "inline-flex";
        } else {
            badge.style.display = "none";
        }
    }
}

function createLeadRow(lead) {
    const newId = lead.id;
    const name = lead.name || "";
    const email = lead.email || "";
    const company = lead.company || "";
    const status = lead.status || "New";
    const value = lead.value || 0;
    const source = lead.source || "";
    const assignee = lead.assignee || "Unassigned";
    const assigneeInitials = lead.assigneeInitials || (name ? name.split(" ").map(p => p[0]).join("").toUpperCase().substring(0, 2) : "U");
    const assigneeColor = lead.assigneeColor || "#059669";
    const score = (typeof lead.score === "number") ? lead.score : (parseInt(lead.score, 10) || 50);
    const bar_color = (score >= 80) ? '#12B76A' : ((score >= 55) ? '#F79009' : '#F04438');
    const lastActivity = lead.lastActivity || "Just now";
    const isArchived = lead.isArchived ? "1" : "0";

    const tr = document.createElement("tr");
    tr.setAttribute("data-id", newId);
    tr.setAttribute("data-status", status);
    tr.setAttribute("data-name", name);
    tr.setAttribute("data-company", company);
    tr.setAttribute("data-score", score);
    tr.setAttribute("data-value", value);
    tr.setAttribute("data-source", source);
    tr.setAttribute("data-assignee", assignee);
    tr.setAttribute("data-last-activity", lastActivity);
    tr.setAttribute("data-archived", isArchived);

    const statusSlug = status.toLowerCase();
    const parts = name.split(" ");
    const leadInitials = (parts[0] ? parts[0][0] : "") + (parts[1] ? parts[1][0] : "");

    tr.innerHTML = `
        <td>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="avatar avatar-sm" style="background-color: ${assigneeColor};">${escapeHtml(leadInitials.toUpperCase() || 'L')}</div>
                <div>
                    <div class="lead-name-clickable" style="font-weight: 600; color: var(--text-heading); cursor: pointer;" onclick="openLeadDrawer('${newId}')">${escapeHtml(name)}</div>
                    <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(email)}</div>
                </div>
            </div>
        </td>
        <td style="color: var(--text-body); font-weight: 500;">${escapeHtml(company)}</td>
        <td>
            <span class="status-badge status-${statusSlug}">
                <span class="status-dot"></span>${escapeHtml(status)}
            </span>
            ${lead.isArchived ? '<span class="status-badge" style="margin-left: 4px; background: rgba(100, 116, 139, 0.12); color: #64748B; font-size: 11px;">Archived</span>' : ''}
        </td>
        <td>
            <div class="score-bar-wrapper">
                <div class="score-bar-track">
                    <div class="score-bar-fill" style="width: ${score}%; background-color: ${bar_color};"></div>
                </div>
                <span class="score-bar-text">${score}</span>
            </div>
        </td>
        <td style="font-weight: 600; color: var(--text-body); font-variant-numeric: tabular-nums;">$${Number(value).toLocaleString()}</td>
        <td><span style="font-size: 12px; color: var(--text-secondary); background-color: var(--border-divider); padding: 2px 8px; border-radius: var(--radius-sm);">${escapeHtml(source)}</span></td>
        <td>
            <div style="display: flex; align-items: center; gap: 6px;">
                <div class="avatar avatar-xs" style="background-color: ${assigneeColor};">${escapeHtml(assigneeInitials)}</div>
                <span style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(assignee)}</span>
            </div>
        </td>
        <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">${escapeHtml(lastActivity)}</td>
        <td style="white-space: nowrap; text-align: center;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                <button class="btn btn-ghost btn-xs" title="Call Lead" style="padding: 4px; color: #12B76A;" onclick="window.leadComm.openCallChoice('${newId}')">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                </button>
                <button class="btn btn-ghost btn-xs" title="WhatsApp Message" style="padding: 4px; color: #25D366;" onclick="window.leadComm.openWhatsApp('${newId}')">
                    <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                </button>
                <button class="btn btn-ghost btn-xs" title="Send Email" style="padding: 4px; color: #2563EB;" onclick="window.leadComm.openEmail('${newId}')">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </button>
            </div>
        </td>
    `;
    return tr;
}

function loadLeadsByArchiveState(state) {
    fetch(`${LEADS_API}?action=list&archive_status=${encodeURIComponent(state)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.data || !Array.isArray(data.data.leads)) {
                return;
            }
            leadsStore = data.data.leads;
            window.leadsStore = leadsStore;
            const tableBody = document.getElementById("leadsTbody");
            if (!tableBody) return;
            tableBody.innerHTML = "";
            if (leadsStore.length === 0) {
                const emptyTr = document.createElement("tr");
                emptyTr.id = "emptyLeadsRow";
                emptyTr.innerHTML = `
                    <td colspan="9" style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color: var(--text-muted); opacity: 0.6;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <div style="font-weight: 600; font-size: 15px; color: var(--text-heading);">No ${state === 'archived' ? 'archived ' : ''}leads found</div>
                            <div style="font-size: 13px; max-width: 320px;">${state === 'archived' ? 'Archived leads will appear here.' : 'Get started by adding your sales leads.'}</div>
                        </div>
                    </td>
                `;
                tableBody.appendChild(emptyTr);
            } else {
                leadsStore.forEach(ld => {
                    tableBody.appendChild(createLeadRow(ld));
                });
            }
            populateDynamicFilterOptions();
            if (typeof window.filterTableRows === "function") {
                window.filterTableRows(true);
            }
            if (typeof window.updateLeadKPICounts === "function") {
                window.updateLeadKPICounts();
            }
        })
        .catch(err => {
            console.error("Error loading leads by archive state:", err);
        });
}
window.createLeadRow = createLeadRow;
window.loadLeadsByArchiveState = loadLeadsByArchiveState;

document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("leadSearchInput");
    const kpiCards = document.querySelectorAll(".lead-kpi-card");
    const tableBody = document.getElementById("leadsTbody");
    const selectAllCb = document.getElementById("selectAllCheckbox");
    const bulkActions = document.getElementById("bulkActions");
    const selectedCountText = document.getElementById("selectedCountText");
    const addLeadForm = document.getElementById("addLeadForm");

    let currentStatusFilter = "All";

    let currentLeadPage = 1;
    const LEADS_PER_PAGE = 8;

    // Summary Card Status Filtering
    kpiCards.forEach(card => {
        card.addEventListener("click", function () {
            currentStatusFilter = this.getAttribute("data-status") || "All";
            filterTableRows(true);
        });
    });

    window.filterLeadsByCard = function (status) {
        currentStatusFilter = status;
        filterTableRows(true);
    };

    function updateLeadKPICounts() {
        if (!tableBody) return;
        const allRows = Array.from(tableBody.querySelectorAll("tr[data-id]"));
        const activeRows = allRows.filter(r => r.getAttribute("data-archived") !== "1");
        const counts = { All: activeRows.length, New: 0, Contacted: 0, Qualified: 0, Proposal: 0, Won: 0, Lost: 0 };
        activeRows.forEach(row => {
            const st = row.getAttribute("data-status");
            if (st && counts.hasOwnProperty(st)) {
                counts[st]++;
            }
        });

        const totalBadge = document.getElementById("leadsTotalCountBadge");
        if (totalBadge) {
            const stateFilter = activeLeadFilters.leadState || "active";
            if (stateFilter === "archived") {
                const archivedRows = allRows.filter(r => r.getAttribute("data-archived") === "1");
                totalBadge.textContent = `${archivedRows.length} archived`;
            } else if (stateFilter === "all") {
                totalBadge.textContent = `${allRows.length} leads`;
            } else {
                totalBadge.textContent = `${counts.All} leads`;
            }
        }

        const totalEl = document.getElementById("kpiLeadTotal");
        const newEl = document.getElementById("kpiLeadNew");
        const contactedEl = document.getElementById("kpiLeadContacted");
        const qualifiedEl = document.getElementById("kpiLeadQualified");
        const wonEl = document.getElementById("kpiLeadWon");
        const lostEl = document.getElementById("kpiLeadLost");

        if (totalEl) totalEl.textContent = counts.All;
        if (newEl) newEl.textContent = counts.New;
        if (contactedEl) contactedEl.textContent = counts.Contacted;
        if (qualifiedEl) qualifiedEl.textContent = counts.Qualified;
        if (wonEl) wonEl.textContent = counts.Won;
        if (lostEl) lostEl.textContent = counts.Lost;

        // Synchronize sidebar Leads count badge
        if (typeof window.updateSidebarBadges === "function") {
            window.updateSidebarBadges({ leads: counts.All });
        }
        if (typeof window.refreshSidebarBadges === "function") {
            window.refreshSidebarBadges();
        }
    }

    window.updateLeadKPICounts = updateLeadKPICounts;

    // Live Search Input
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            filterTableRows(true);
        });
    }

    function filterTableRows(resetPage = false) {
        if (resetPage) {
            currentLeadPage = 1;
        }

        const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const rows = Array.from(tableBody.querySelectorAll("tr[data-id]"));
        const matchingRows = [];

        rows.forEach(row => {
            const status = row.getAttribute("data-status") || "";
            const name = (row.getAttribute("data-name") || "").toLowerCase();
            const company = (row.getAttribute("data-company") || "").toLowerCase();
            const score = parseInt(row.getAttribute("data-score") || "75", 10);
            const value = parseFloat(row.getAttribute("data-value") || "0");
            const source = row.getAttribute("data-source") || "";
            const assignee = row.getAttribute("data-assignee") || "";
            const lastActivity = row.getAttribute("data-last-activity") || "";

            // 0. Lead Archive State Filter
            const isArchived = (row.getAttribute("data-archived") === "1");
            let matchesArchive = true;
            const stateFilter = activeLeadFilters.leadState || "active";
            if (stateFilter === "active") {
                matchesArchive = !isArchived;
            } else if (stateFilter === "archived") {
                matchesArchive = isArchived;
            }

            // 1. External Status Chip Priority
            let matchesStatus = true;
            if (currentStatusFilter !== "All") {
                matchesStatus = (status === currentStatusFilter);
            } else if (activeLeadFilters.statuses.length > 0) {
                matchesStatus = activeLeadFilters.statuses.includes(status);
            }

            // 2. Search Query
            const matchesQuery = (!query || name.includes(query) || company.includes(query));

            // 3. Assignee
            const matchesAssignee = (activeLeadFilters.assignees.length === 0 || activeLeadFilters.assignees.includes(assignee));

            // 4. Score
            const matchesScore = (score >= activeLeadFilters.scoreMin && score <= activeLeadFilters.scoreMax);

            // 5. Deal Value
            const matchesValueMin = (activeLeadFilters.valueMin === null || isNaN(activeLeadFilters.valueMin) || value >= activeLeadFilters.valueMin);
            const matchesValueMax = (activeLeadFilters.valueMax === null || isNaN(activeLeadFilters.valueMax) || value <= activeLeadFilters.valueMax);
            const matchesValue = matchesValueMin && matchesValueMax;

            // 6. Source
            const matchesSource = (activeLeadFilters.sources.length === 0 || activeLeadFilters.sources.includes(source));

            // 7. Last Activity
            let matchesActivity = true;
            if (activeLeadFilters.lastActivity !== "any") {
                const daysAgo = parseDaysAgo(lastActivity);
                if (activeLeadFilters.lastActivity === "today") matchesActivity = (daysAgo <= 0);
                else if (activeLeadFilters.lastActivity === "7days") matchesActivity = (daysAgo <= 7);
                else if (activeLeadFilters.lastActivity === "30days") matchesActivity = (daysAgo <= 30);
                else if (activeLeadFilters.lastActivity === "over30days") matchesActivity = (daysAgo > 30);
            }

            const isMatch = matchesStatus && matchesQuery && matchesAssignee && matchesScore && matchesValue && matchesSource && matchesActivity;

            if (isMatch) {
                matchingRows.push(row);
            } else {
                row.style.display = "none";
            }
        });

        const totalMatches = matchingRows.length;
        const totalPages = Math.ceil(totalMatches / LEADS_PER_PAGE) || 1;

        if (currentLeadPage > totalPages) {
            currentLeadPage = totalPages;
        }
        if (currentLeadPage < 1) {
            currentLeadPage = 1;
        }

        const startIndex = (currentLeadPage - 1) * LEADS_PER_PAGE;
        const endIndex = currentLeadPage * LEADS_PER_PAGE;

        matchingRows.forEach((row, idx) => {
            if (idx >= startIndex && idx < endIndex) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });

        // Empty Result State Management
        let emptyRow = document.getElementById("noFilterResultsRow");
        const emptyLeadsRow = document.getElementById("emptyLeadsRow");

        if (rows.length === 0) {
            // No leads in table at all: keep emptyLeadsRow visible, hide noFilterResultsRow
            if (emptyLeadsRow) emptyLeadsRow.style.display = "";
            if (emptyRow) emptyRow.style.display = "none";
        } else if (totalMatches === 0) {
            // Leads exist in table, but none matched current filters: hide emptyLeadsRow, show noFilterResultsRow
            if (emptyLeadsRow) emptyLeadsRow.style.display = "none";
            if (!emptyRow) {
                emptyRow = document.createElement("tr");
                emptyRow.id = "noFilterResultsRow";
                emptyRow.innerHTML = `
                    <td colspan="11" style="text-align: center; padding: 40px 16px;">
                        <div style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin-bottom: 4px;">No leads match these filters</div>
                        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">Try adjusting or clearing your active filters.</div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearAdvancedLeadFilters()">Clear Filters</button>
                    </td>
                `;
                tableBody.appendChild(emptyRow);
            } else {
                emptyRow.style.display = "";
            }
        } else {
            // Matches exist: hide both empty states
            if (emptyLeadsRow) emptyLeadsRow.style.display = "none";
            if (emptyRow) emptyRow.style.display = "none";
        }

        const pagingRange = document.getElementById("pagingRange");
        const pagingTotal = document.getElementById("pagingTotal");
        if (pagingRange) {
            if (totalMatches === 0) {
                pagingRange.textContent = "0";
            } else {
                pagingRange.textContent = `${startIndex + 1}–${Math.min(endIndex, totalMatches)}`;
            }
        }
        if (pagingTotal) {
            pagingTotal.textContent = totalMatches;
        }

        const container = document.querySelector('.card .pagination-container') || document.querySelector('.pagination-container');
        if (container) {
            container.style.display = totalMatches > LEADS_PER_PAGE ? 'flex' : 'none';
        }

        renderLeadPaginationControls(totalMatches, totalPages);
        updateFilterButtonBadge();
        updateLeadKPICounts();
    }

    function renderLeadPaginationControls(totalMatches, totalPages) {
        const controls = document.getElementById("paginationControls");
        if (!controls) return;

        if (totalMatches === 0) {
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
            <button class="pagination-btn" id="prevPageBtn" ${currentLeadPage === 1 ? 'disabled' : ''}>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        `;

        const pageNumbers = getPaginationPages(currentLeadPage, totalPages);
        pageNumbers.forEach(p => {
            if (p === '...') {
                html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
            } else {
                const isActive = (p === currentLeadPage);
                html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.changeLeadPage(${p})">${p}</button>`;
            }
        });

        html += `
            <button class="pagination-btn" id="nextPageBtn" ${currentLeadPage === totalPages ? 'disabled' : ''}>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        `;

        controls.innerHTML = html;

        const prevBtn = document.getElementById("prevPageBtn");
        const nextBtn = document.getElementById("nextPageBtn");
        if (prevBtn) prevBtn.onclick = () => window.changeLeadPage(currentLeadPage - 1);
        if (nextBtn) nextBtn.onclick = () => window.changeLeadPage(currentLeadPage + 1);
    }

    window.changeLeadPage = function(newPage) {
        currentLeadPage = newPage;
        filterTableRows(false);
        const tableContainer = document.querySelector(".table-responsive") || document.querySelector("table");
        if (tableContainer && typeof tableContainer.scrollIntoView === "function") {
            tableContainer.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
    };

    function getPaginationPages(current, total) {
        if (total <= 7) {
            const pages = [];
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }
        if (current <= 4) {
            return [1, 2, 3, 4, 5, '...', total];
        }
        if (current >= total - 3) {
            return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        }
        return [1, '...', current - 1, current, current + 1, '...', total];
    }

    window.filterTableRows = filterTableRows;
    populateDynamicFilterOptions();
    filterTableRows(true);

    // Checkbox Selection
    if (selectAllCb) {
        selectAllCb.addEventListener("click", function () {
            const isChecked = this.classList.contains("checked");
            const visibleRows = Array.from(tableBody.querySelectorAll("tr[data-id]")).filter(r => r.style.display !== "none");
            
            if (isChecked) {
                this.classList.remove("checked");
                visibleRows.forEach(r => {
                    r.classList.remove("selected");
                    r.querySelector(".row-checkbox")?.classList.remove("checked");
                });
            } else {
                this.classList.add("checked");
                visibleRows.forEach(r => {
                    r.classList.add("selected");
                    r.querySelector(".row-checkbox")?.classList.add("checked");
                });
            }
            updateBulkActionsState();
        });
    }

    if (tableBody) {
        tableBody.addEventListener("click", function (e) {
            const cb = e.target.closest(".row-checkbox");
            if (cb) {
                const row = cb.closest("tr");
                cb.classList.toggle("checked");
                row.classList.toggle("selected");
                updateBulkActionsState();
            }
        });
    }

    function updateBulkActionsState() {
        const selectedRows = tableBody.querySelectorAll("tr.selected");
        const count = selectedRows.length;
        if (count > 0) {
            bulkActions.style.display = "flex";
            selectedCountText.textContent = count;
        } else {
            bulkActions.style.display = "none";
            if (selectAllCb) selectAllCb.classList.remove("checked");
        }
    }

    // Add Lead Form Submission
    if (addLeadForm) {
        addLeadForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const submitBtn = addLeadForm.querySelector("button[type='submit']");
            if (submitBtn) submitBtn.disabled = true;

            const formData = new FormData(addLeadForm);
            formData.append("action", "create_lead");

            fetch(LEADS_API, {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) submitBtn.disabled = false;
                const lead = (data.data && data.data.lead) ? data.data.lead : data.lead;
                if (!data.success || !lead) {
                    showToast(data.message || "Failed to create lead.", "error");
                    return;
                }

                const newId = lead.id;
                const name = lead.name;
                const email = lead.email;
                const company = lead.company;
                const status = lead.status;
                const value = lead.value || 0;
                const source = lead.source;
                const assignee = lead.assignee;
                const assigneeInitials = lead.assigneeInitials || "U";
                const assigneeColor = lead.assigneeColor || "#059669";
                const score = lead.score || 50;
                const bar_color = (score >= 80) ? '#12B76A' : ((score >= 55) ? '#F79009' : '#F04438');
                const lastActivity = lead.lastActivity || "Just now";

                // Remove empty row if present
                const emptyRow = document.getElementById("emptyLeadsRow");
                if (emptyRow) emptyRow.remove();

                const tr = createLeadRow(lead);
                tableBody.insertBefore(tr, tableBody.firstChild);
                leadsStore.unshift(lead);
                window.leadsStore = leadsStore;

                closeAddLeadModal();
                addLeadForm.reset();
                filterTableRows(true);
                updateLeadKPICounts();
                showToast("New lead created successfully.");
            })
            .catch(err => {
                if (submitBtn) submitBtn.disabled = false;
                showToast("Network error creating lead.", "error");
            });
        });
    }

    // Modal Submit Event Listeners (Registered Once)
    setupModalForms();

    // Attach Backdrop Click Event Listeners to Child Modals (Registered Once)
    document.querySelectorAll(".lead-action-modal").forEach(modal => {
        modal.addEventListener("click", function (e) {
            if (e.target === this) {
                closeActiveLeadChildModal();
            }
        });
    });

    // Close Header Dropdown when clicking outside
    document.addEventListener("click", function (e) {
        const menu = document.getElementById("drawerHeaderMenu");
        const btn = document.getElementById("drawerHeaderActionsBtn");
        if (menu && menu.classList.contains("show")) {
            if (!menu.contains(e.target) && !btn.contains(e.target)) {
                menu.classList.remove("show");
            }
        }
    });

    // Close Drawer when clicking drawer backdrop
    const drawerOverlay = document.getElementById("leadDrawer");
    if (drawerOverlay) {
        drawerOverlay.addEventListener("click", function (e) {
            if (e.target === drawerOverlay && !activeLeadModalType) {
                closeLeadDrawer();
            }
        });
    }

    // Global Keydown Handler: Escape key closes active child modal first, then drawer
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            if (activeLeadModalType) {
                e.preventDefault();
                e.stopPropagation();
                closeActiveLeadChildModal();
            } else if (drawerOverlay && drawerOverlay.classList.contains("show")) {
                e.preventDefault();
                closeLeadDrawer();
            }
        }
    });
});

// Row Menu Toggle
function toggleRowMenu(e, id) {
    e.stopPropagation();
    document.querySelectorAll(".row-menu.show").forEach(m => {
        if (m.id !== `menu-${id}`) m.classList.remove("show");
    });
    const menu = document.getElementById(`menu-${id}`);
    if (menu) menu.classList.toggle("show");
}

function deleteLeadRow(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    showConfirmModal({
        title: "Delete Lead",
        message: "Are you sure you want to delete this lead? This action cannot be undone.",
        type: "danger",
        confirmText: "Delete Lead",
        onConfirm: function() {
            fetch(LEADS_API, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ action: "delete_lead", id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message || "Failed to delete lead.", "error");
                    return;
                }
                row.remove();
                leadsStore = leadsStore.filter(l => String(l.id) !== String(id));
                window.leadsStore = leadsStore;
                if (typeof window.filterTableRows === "function") {
                    window.filterTableRows(false);
                }
                if (typeof window.updateLeadKPICounts === "function") {
                    window.updateLeadKPICounts();
                }
                showToast("Lead deleted successfully.");
            })
            .catch(() => {
                showToast("Network error deleting lead.", "error");
            });
        }
    });
}

function deleteSelectedLeads() {
    const selectedRows = Array.from(document.querySelectorAll("#leadsTbody tr.selected"));
    if (selectedRows.length > 0) {
        const count = selectedRows.length;
        const ids = selectedRows.map(r => r.getAttribute("data-id")).filter(Boolean);
        showConfirmModal({
            title: "Delete Selected Leads",
            message: `Are you sure you want to delete ${count} selected lead(s)? This action cannot be undone.`,
            type: "danger",
            confirmText: "Delete Leads",
            onConfirm: function() {
                fetch(LEADS_API, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action: "bulk_delete", ids: ids })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        showToast(data.message || "Failed to delete leads.", "error");
                        return;
                    }
                    selectedRows.forEach(r => r.remove());
                    leadsStore = leadsStore.filter(l => !ids.includes(String(l.id)));
                    window.leadsStore = leadsStore;
                    const bulkActions = document.getElementById("bulkActions");
                    if (bulkActions) bulkActions.style.display = "none";
                    if (typeof window.filterTableRows === "function") {
                        window.filterTableRows(false);
                    }
                    if (typeof window.updateLeadKPICounts === "function") {
                        window.updateLeadKPICounts();
                    }
                    showToast(`${data.data?.deletedCount || count} lead(s) deleted.`);
                })
                .catch(() => {
                    showToast("Network error deleting selected leads.", "error");
                });
            }
        });
    }
}

// ==========================================
// SINGLE CHILD MODAL MANAGER LOGIC
// ==========================================

function openLeadChildModal(type, triggerElement) {
    // 1. If another child modal is open, close it cleanly first
    if (activeLeadModalType && activeLeadModalType !== type) {
        closeActiveLeadChildModal(false);
    }

    const modalId = LEAD_MODAL_MAP[type] || type;
    const modal = document.getElementById(modalId);
    if (!modal) return;

    // Save trigger element for restoring focus
    if (triggerElement) {
        lastModalTriggerElement = triggerElement;
    } else if (document.activeElement) {
        lastModalTriggerElement = document.activeElement;
    }

    activeLeadModalType = type;

    // Ensure all other child modals are hidden
    document.querySelectorAll(".lead-action-modal").forEach(m => {
        if (m.id !== modalId) {
            m.classList.remove("show", "is-open");
            m.setAttribute("aria-hidden", "true");
        }
    });

    // Populate prefilled data for target modal
    populateChildModalData(type);

    // Show target child modal
    modal.classList.add("show", "is-open");
    modal.setAttribute("aria-hidden", "false");

    // Lock drawer body scroll while modal is active
    const drawerBody = document.getElementById("drawerLeadBody");
    if (drawerBody) drawerBody.style.overflow = "hidden";
    document.body.style.overflow = "hidden";

    // Focus first interactive control inside modal
    setTimeout(() => {
        const firstInput = modal.querySelector("input:not([readonly]):not([type='hidden']), select, textarea, button.btn-primary");
        if (firstInput) firstInput.focus();
    }, 50);
}

function closeActiveLeadChildModal(restoreFocus = true) {
    if (!activeLeadModalType) {
        document.querySelectorAll(".lead-action-modal.show, .lead-action-modal.is-open").forEach(m => {
            m.classList.remove("show", "is-open");
            m.setAttribute("aria-hidden", "true");
        });
        return;
    }

    const modalId = LEAD_MODAL_MAP[activeLeadModalType] || activeLeadModalType;
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove("show", "is-open");
        modal.setAttribute("aria-hidden", "true");
    }

    activeLeadModalType = null;

    // Restore drawer body scroll
    const drawerBody = document.getElementById("drawerLeadBody");
    if (drawerBody) drawerBody.style.overflow = "auto";

    // Keep main page scroll locked if drawer is open
    const drawer = document.getElementById("leadDrawer");
    if (drawer && drawer.classList.contains("show")) {
        document.body.style.overflow = "hidden";
    } else {
        document.body.style.overflow = "";
    }

    // Restore focus
    if (restoreFocus && lastModalTriggerElement && typeof lastModalTriggerElement.focus === "function") {
        lastModalTriggerElement.focus();
        lastModalTriggerElement = null;
    }
}

function populateChildModalData(type) {
    const lead = getLeadById(activeDrawerLeadId);
    if (!lead) return;

    if (type === "meeting") {
        const titleEl = document.getElementById("meetingTitle");
        const nameEl = document.getElementById("meetingLeadName");
        const dateEl = document.getElementById("meetingDate");
        if (nameEl) nameEl.value = lead.name;
        if (titleEl && (!titleEl.value || titleEl.value.includes("Discovery Call"))) {
            titleEl.value = `Discovery Call with ${lead.name}`;
        }
        if (dateEl && !dateEl.value) {
            dateEl.value = new Date().toISOString().split('T')[0];
        }
    } else if (type === "task") {
        const linkedEl = document.getElementById("taskLinkedLeadInput");
        const dueDateEl = document.getElementById("taskDueDateInput");
        if (linkedEl) linkedEl.value = lead.name;
        if (dueDateEl && !dueDateEl.value) {
            dueDateEl.value = new Date().toISOString().split('T')[0];
        }
    } else if (type === "note") {
        const banner = document.getElementById("noteLeadNameBanner");
        const titleEl = document.getElementById("addDrawerNoteTitle");
        const submitBtn = document.getElementById("noteSubmitBtn");
        const contentEl = document.getElementById("noteContentInput");
        const pinEl = document.getElementById("notePinCheckbox");

        if (editingDrawerNoteId) {
            const notes = leadNotesStore[activeDrawerLeadId] || [];
            const note = notes.find(n => n.id === editingDrawerNoteId);
            if (titleEl) titleEl.textContent = "Edit Note";
            if (banner) banner.innerHTML = `Editing note for <strong>${escapeHtml(lead.name)}</strong>`;
            if (contentEl) contentEl.value = note ? note.content : "";
            if (pinEl) pinEl.checked = note ? !!note.pinned : false;
            if (submitBtn) submitBtn.textContent = "Save Changes";
        } else {
            if (titleEl) titleEl.textContent = "Add Note";
            if (banner) banner.innerHTML = `Note for <strong>${escapeHtml(lead.name)}</strong>`;
            if (contentEl) contentEl.value = "";
            if (pinEl) pinEl.checked = false;
            if (submitBtn) submitBtn.textContent = "Save Note";
        }
    } else if (type === "edit") {
        document.getElementById("editLeadName").value = lead.name || "";
        document.getElementById("editLeadJobTitle").value = lead.jobTitle || lead.job_title || "";
        document.getElementById("editLeadCompany").value = lead.company || "";
        document.getElementById("editLeadEmail").value = lead.email || "";
        document.getElementById("editLeadPhone").value = lead.phone || "";
        document.getElementById("editLeadWhatsapp").value = lead.whatsapp || "";
        document.getElementById("editLeadLocation").value = lead.location || "";
        document.getElementById("editLeadStatus").value = lead.status || "New";
        document.getElementById("editLeadScore").value = (lead.score !== undefined && lead.score !== null) ? lead.score : 50;
        document.getElementById("editLeadValue").value = (lead.value !== undefined && lead.value !== null) ? lead.value : 0;
        document.getElementById("editLeadSource").value = lead.source || "";
        const ownerEl = document.getElementById("editLeadOwner");
        if (ownerEl) {
            ownerEl.value = (lead.assigneeId !== undefined && lead.assigneeId !== null) ? lead.assigneeId : (lead.assigned_to || "");
        }
    } else if (type === "convert") {
        const nameEl = document.getElementById("convertLeadName");
        if (nameEl) nameEl.textContent = lead.name;
    } else if (type === "status") {
        const statusEl = document.getElementById("quickStatusSelect");
        if (statusEl) statusEl.value = lead.status;
    } else if (type === "owner") {
        const ownerEl = document.getElementById("quickOwnerSelect");
        if (ownerEl) {
            ownerEl.value = (lead.assigneeId !== undefined && lead.assigneeId !== null) ? lead.assigneeId : (lead.assigned_to || "");
        }
    }
}

// Wrapper Modal Functions matching inline onclick calls
function openScheduleMeetingModal(trigger) { openLeadChildModal("meeting", trigger); }
function closeScheduleMeetingModal() { closeActiveLeadChildModal(); }

function openAddTaskModal(trigger) { openLeadChildModal("task", trigger); }
function openAddDrawerTaskModal(trigger) { openLeadChildModal("task", trigger); }
function closeAddDrawerTaskModal() { closeActiveLeadChildModal(); }

function openAddNoteModal(trigger) { editingDrawerNoteId = null; openLeadChildModal("note", trigger); }
function openAddDrawerNoteModal(trigger) { editingDrawerNoteId = null; openLeadChildModal("note", trigger); }
function openEditDrawerNoteModal(noteId, trigger) {
    const notes = leadNotesStore[activeDrawerLeadId] || [];
    const note = notes.find(n => n.id === noteId);
    if (!note) return;
    editingDrawerNoteId = noteId;
    openLeadChildModal("note", trigger);
}
function closeAddDrawerNoteModal() { editingDrawerNoteId = null; closeActiveLeadChildModal(); }

function openEditLeadModal(id, trigger) {
    if (id) activeDrawerLeadId = id;
    if (!activeDrawerLeadId) return;

    let lead = getLeadById(activeDrawerLeadId);
    if (!lead) {
        fetch(`${LEADS_API}?action=lead_drawer&id=${encodeURIComponent(activeDrawerLeadId)}`)
            .then(r => r.json())
            .then(res => {
                const data = (res && res.data) ? res.data : res;
                if (res.success && data && data.lead) {
                    const l = data.lead;
                    const idx = leadsStore.findIndex(x => String(x.id) === String(l.id));
                    if (idx !== -1) leadsStore[idx] = l;
                    else leadsStore.unshift(l);
                    window.leadsStore = leadsStore;
                    openLeadChildModal("edit", trigger);
                }
            })
            .catch(() => {
                openLeadChildModal("edit", trigger);
            });
        return;
    }
    openLeadChildModal("edit", trigger);
}
function closeEditLeadModal() { closeActiveLeadChildModal(); }

function openConvertContactModal(trigger) { openLeadChildModal("convert", trigger); }
function closeConvertContactModal() { closeActiveLeadChildModal(); }

function openQuickStatusModal(trigger) { openLeadChildModal("status", trigger); }
function closeQuickStatusModal() { closeActiveLeadChildModal(); }

function openQuickOwnerModal(trigger) { openLeadChildModal("owner", trigger); }
function closeQuickOwnerModal() { closeActiveLeadChildModal(); }

// ==========================================
// LEAD DETAILS DRAWER RENDERER & TAB LOGIC
// ==========================================

function openLeadDrawer(id) {
    if (window.leadComm && typeof window.leadComm.closeAllLayers === "function") {
        window.leadComm.closeAllLayers();
    }

    activeDrawerLeadId = id;
    const drawer = document.getElementById("leadDrawer");
    const breadcrumb = document.getElementById("drawerBreadcrumb");
    const title = document.getElementById("drawerLeadTitle");

    let lead = getLeadById(id);
    if (lead) {
        if (breadcrumb) breadcrumb.textContent = `Leads / ${lead.name}`;
        if (title) title.textContent = "Lead Details";
        updateDrawerArchiveBtn(Boolean(lead.isArchived));
    } else {
        if (breadcrumb) breadcrumb.textContent = "Leads / Loading...";
        if (title) title.textContent = "Lead Details";
    }

    initLeadSubStores(id, lead);

    activeDrawerTab = "overview";
    updateTabHighlight("overview");

    const body = document.getElementById("drawerLeadBody");
    if (lead) {
        renderDrawerTabContent();
    } else if (body) {
        body.innerHTML = `
            <div style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                <div class="loading-spinner" style="margin: 0 auto 12px; width: 28px; height: 28px; border: 3px solid var(--border-divider); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                <p style="font-size: 13px;">Loading lead details...</p>
            </div>
        `;
    }

    if (drawer) drawer.classList.add("show");
    document.body.style.overflow = "hidden";

    // Fetch rich live details from database API
    fetch(`${LEADS_API}?action=lead_drawer&id=${encodeURIComponent(id)}`)
        .then(r => r.json())
        .then(res => {
            const data = (res && res.data) ? res.data : res;
            if (res.success && data && data.lead) {
                const updatedLead = data.lead;
                const idx = leadsStore.findIndex(l => String(l.id) === String(id));
                if (idx !== -1) {
                    leadsStore[idx] = updatedLead;
                } else {
                    leadsStore.unshift(updatedLead);
                }
                window.leadsStore = leadsStore;

                leadDealsStore[id] = data.deals || [];
                leadTasksStore[id] = data.tasks || [];
                leadNotesStore[id] = data.notes || [];
                leadActivitiesStore[id] = data.activities || [];
                leadMeetingsStore[id] = data.meetings || [];

                if (activeDrawerLeadId === id) {
                    if (breadcrumb) breadcrumb.textContent = `Leads / ${updatedLead.name}`;
                    updateDrawerArchiveBtn(Boolean(updatedLead.isArchived));
                    renderDrawerTabContent();
                }
            }
        })
        .catch(err => {
            console.error("Failed to load lead drawer details:", err);
        });
}

function closeLeadDrawer() {
    // If a child modal is active, close child modal first
    if (activeLeadModalType) {
        closeActiveLeadChildModal();
    }
    const drawer = document.getElementById("leadDrawer");
    if (drawer) drawer.classList.remove("show");
    const menu = document.getElementById("drawerHeaderMenu");
    if (menu) menu.classList.remove("show");
    document.body.style.overflow = "";
}

function toggleDrawerHeaderMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById("drawerHeaderMenu");
    if (menu) menu.classList.toggle("show");
}

function switchDrawerTab(tab) {
    activeDrawerTab = tab;
    updateTabHighlight(tab);
    renderDrawerTabContent();
}

function updateTabHighlight(tab) {
    const tabs = document.querySelectorAll("#drawerTabsContainer .drawer-tab");
    tabs.forEach(t => {
        if (t.getAttribute("data-tab") === tab) {
            t.classList.add("active");
        } else {
            t.classList.remove("active");
        }
    });
}

function renderDrawerTabContent() {
    const body = document.getElementById("drawerLeadBody");
    if (!body) return;

    const lead = getLeadById(activeDrawerLeadId);
    if (!lead) return;

    updateDrawerArchiveBtn(Boolean(lead.isArchived));

    let html = "";

    if (activeDrawerTab === "overview") {
        const nameParts = (lead.name || "Lead").trim().split(/\s+/);
        const initials = nameParts.length >= 2 ? (nameParts[0][0] + nameParts[1][0]).toUpperCase() : (nameParts[0][0] || "L").toUpperCase();
        const statusSlug = (lead.status || "new").toLowerCase();
        const score = (lead.score !== undefined && lead.score !== null) ? Number(lead.score) : 0;
        const scoreColor = (score >= 80) ? '#12B76A' : ((score >= 55) ? '#F79009' : '#F04438');

        html += `
            <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
                <div class="avatar avatar-lg" style="background-color: ${lead.assigneeColor || '#7C3AED'}; font-size: 16px; width: 46px; height: 46px;">${initials}</div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                        <h4 style="font-size: 17px; font-weight: 700; color: var(--text-heading); font-family: var(--font-family);">${escapeHtml(lead.name)}</h4>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="status-badge status-${statusSlug}">
                                <span class="status-dot"></span>${escapeHtml(lead.status || 'New')}
                            </span>
                            ${lead.isArchived ? '<span class="status-badge" style="background: rgba(100, 116, 139, 0.12); color: #64748B;"><span class="status-dot" style="background: #64748B;"></span>Archived</span>' : ''}
                        </div>
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-top: 2px;">
                        ${lead.jobTitle ? `${escapeHtml(lead.jobTitle)} • ` : ''}<strong>${escapeHtml(lead.company || '')}</strong>
                    </p>
                    <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        <span>📍 ${lead.location ? escapeHtml(lead.location) : 'No location specified'}</span>
                        <span>👤 Owner: <strong>${escapeHtml(lead.assignee || 'Unassigned')}</strong></span>
                    </div>
                </div>
            </div>

            <!-- 4 KPI Cards -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 18px;">
                <div style="background-color: #F9FAFB; border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 10px 12px;">
                    <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Deal Value</span>
                    <p style="font-size: 15px; font-weight: 700; color: var(--text-heading); margin-top: 2px;">$${(Number(lead.value) || 0).toLocaleString()}</p>
                </div>
                <div style="background-color: #F9FAFB; border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 10px 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Lead Score</span>
                        <span style="font-size: 13px; font-weight: 700; color: ${scoreColor};">${score}/100</span>
                    </div>
                    <div class="score-bar-track" style="width: 100%; height: 5px; margin-top: 6px; background-color: #EAECF0;">
                        <div class="score-bar-fill" style="width: ${score}%; background-color: ${scoreColor}; height: 100%;"></div>
                    </div>
                </div>
                <div style="background-color: #F9FAFB; border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 10px 12px;">
                    <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Source</span>
                    <p style="font-size: 13px; font-weight: 600; color: var(--text-heading); margin-top: 2px;">${escapeHtml(lead.source || 'Website')}</p>
                </div>
                <div style="background-color: #F9FAFB; border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 10px 12px;">
                    <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Last Activity</span>
                    <p style="font-size: 13px; font-weight: 600; color: var(--text-heading); margin-top: 2px;">${escapeHtml(lead.lastActivity || 'Never')}</p>
                </div>
            </div>

            <!-- Primary Action Buttons (3 Equal-Width Compact Outlined Buttons) -->
            <div class="lead-primary-actions">
                <button class="btn btn-secondary btn-sm" onclick="openScheduleMeetingModal(this)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Schedule Meeting
                </button>
                <button class="btn btn-secondary btn-sm" onclick="openAddDrawerTaskModal(this)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                    Add Task
                </button>
                <button class="btn btn-secondary btn-sm" onclick="openAddDrawerNoteModal(this)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Add Note
                </button>
            </div>
        `;
        html += renderOverviewTab(lead);
    } else if (activeDrawerTab === "activity") {
        html += renderActivityTab(lead);
    } else if (activeDrawerTab === "deals") {
        html += renderDealsTab(lead);
    } else if (activeDrawerTab === "tasks") {
        html += renderTasksTab(lead);
    } else if (activeDrawerTab === "notes") {
        html += renderNotesTab(lead);
    }

    body.innerHTML = html;
}

// ------------------------------------------
// TAB 1: OVERVIEW
// ------------------------------------------
function renderOverviewTab(lead) {
    const email = lead.email || "";
    const phone = lead.phone || "";
    const whatsapp = lead.whatsapp || "";
    const location = lead.location || "";
    const assignee = lead.assignee || "Unassigned";
    const source = lead.source || "—";
    const score = (lead.score !== undefined && lead.score !== null) ? Number(lead.score) : 0;
    const value = (lead.value !== undefined && lead.value !== null) ? Number(lead.value) : 0;
    const createdAt = lead.createdAt || "—";
    const lastActivity = lead.lastActivity || "Never";

    return `
        <!-- Contact Information Card -->
        <div class="drawer-info-card">
            <div class="drawer-card-title">Contact Information</div>
            <div class="drawer-info-grid">
                <div class="drawer-info-row full-width">
                    <span class="drawer-info-label">Work Email</span>
                    <div class="drawer-info-val">
                        <span>${email ? escapeHtml(email) : '<span style="color:var(--text-muted);">—</span>'}</span>
                        ${email ? `
                        <button class="drawer-copy-btn" title="Copy Email" onclick="copyToClipboard('${escapeHtml(email)}', 'Email address')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        </button>` : ''}
                    </div>
                </div>

                <div class="drawer-info-row">
                    <span class="drawer-info-label">Phone Number</span>
                    <div class="drawer-info-val">
                        <span>${phone ? escapeHtml(phone) : '<span style="color:var(--text-muted);">—</span>'}</span>
                        ${phone ? `
                        <button class="drawer-copy-btn" title="Copy Phone" onclick="copyToClipboard('${escapeHtml(phone)}', 'Phone number')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        </button>` : ''}
                    </div>
                </div>

                <div class="drawer-info-row">
                    <span class="drawer-info-label">WhatsApp</span>
                    <div class="drawer-info-val">
                        <span>${whatsapp ? escapeHtml(whatsapp) : '<span style="color:var(--text-muted);">—</span>'}</span>
                        ${whatsapp ? `
                        <button class="drawer-copy-btn" title="Copy WhatsApp" onclick="copyToClipboard('${escapeHtml(whatsapp)}', 'WhatsApp number')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        </button>` : ''}
                    </div>
                </div>

                <div class="drawer-info-row">
                    <span class="drawer-info-label">Location</span>
                    <div class="drawer-info-val">${location ? escapeHtml(location) : '<span style="color:var(--text-muted);">—</span>'}</div>
                </div>

                <div class="drawer-info-row">
                    <span class="drawer-info-label">Preferred Channel</span>
                    <div class="drawer-info-val">
                        <span class="status-badge" style="background:#EFF4FF;color:#2563EB;">Email & Calls</span>
                    </div>
                </div>

                <div class="drawer-info-row full-width">
                    <span class="drawer-info-label">Lead Reference</span>
                    <div class="drawer-info-val" style="font-family:monospace;font-weight:600;color:var(--text-heading);">
                        ${escapeHtml(lead.lead_code || ('LD-' + String(lead.id).padStart(4, '0')))}
                    </div>
                </div>
            </div>
        </div>

        <!-- CRM Details Card -->
        <div class="drawer-info-card" style="margin-bottom:0;">
            <div class="drawer-card-title">CRM Details</div>
            <div class="drawer-info-grid">
                <div class="drawer-info-row">
                    <span class="drawer-info-label">Lead Owner</span>
                    <div class="drawer-info-val">${escapeHtml(assignee)}</div>
                </div>
                <div class="drawer-info-row">
                    <span class="drawer-info-label">Lead Source</span>
                    <div class="drawer-info-val">${escapeHtml(source)}</div>
                </div>
                <div class="drawer-info-row">
                    <span class="drawer-info-label">Status</span>
                    <div class="drawer-info-val">${escapeHtml(lead.status || 'New')}</div>
                </div>
                <div class="drawer-info-row">
                    <span class="drawer-info-label">Lead Score</span>
                    <div class="drawer-info-val">${score} / 100</div>
                </div>
                <div class="drawer-info-row">
                    <span class="drawer-info-label">Estimated Value</span>
                    <div class="drawer-info-val">$${value.toLocaleString()}</div>
                </div>
                <div class="drawer-info-row">
                    <span class="drawer-info-label">Created Date</span>
                    <div class="drawer-info-val">${escapeHtml(createdAt)}</div>
                </div>
                <div class="drawer-info-row full-width">
                    <span class="drawer-info-label">Last Activity</span>
                    <div class="drawer-info-val">${escapeHtml(lastActivity)}</div>
                </div>
            </div>
        </div>
    `;
}

// ------------------------------------------
// TAB 2: ACTIVITY
// ------------------------------------------
function renderActivityTab(lead) {
    const rawActs = leadActivitiesStore[lead.id] || [];
    const activities = rawActs.map(a => {
        let badge = "Activity";
        let type = "Status Changes";
        const t = (a.type || "").toLowerCase();
        if (t.includes("call")) { badge = "Call"; type = "Calls"; }
        else if (t.includes("email")) { badge = "Email"; type = "Emails"; }
        else if (t.includes("status")) { badge = "Status Change"; type = "Status Changes"; }
        else if (t.includes("meet")) { badge = "Meeting"; type = "Meetings"; }
        else if (t.includes("deal") || t.includes("convert")) { badge = "Deal"; type = "Deals"; }
        else if (t.includes("whatsapp")) { badge = "WhatsApp"; type = "WhatsApp"; }
        else if (t.includes("note")) { badge = "Note"; type = "Notes"; }
        else if (t.includes("task")) { badge = "Task"; type = "Tasks"; }
        return {
            type,
            title: a.title,
            desc: a.desc,
            time: a.time || a.createdAt,
            badge,
            actor: a.actor || lead.assignee || 'User'
        };
    });

    const filtered = (activityFilter === "All") ? activities : activities.filter(a => a.type === activityFilter || a.badge === activityFilter);
    const filterChips = ["All", "Calls", "Emails", "WhatsApp", "Meetings", "Status Changes"];

    return `
        <!-- Filter Chips -->
        <div class="timeline-filter-bar">
            ${filterChips.map(f => `
                <button class="timeline-chip ${activityFilter === f ? 'active' : ''}" onclick="setActivityFilter('${f}')">
                    ${f}
                </button>
            `).join('')}
        </div>

        <!-- Vertical Timeline -->
        <div class="timeline-list">
            ${filtered.length > 0 ? filtered.map(a => `
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-title">${escapeHtml(a.title)}</span>
                            <span class="timeline-time">${escapeHtml(a.time)}</span>
                        </div>
                        <div class="timeline-desc">${escapeHtml(a.desc)}</div>
                        <div class="timeline-meta">
                            <span class="status-badge" style="background:#F2F4F7;color:var(--text-secondary);font-size:10.5px;padding:1px 6px;">${escapeHtml(a.badge)}</span>
                            <span>By ${escapeHtml(a.actor)}</span>
                        </div>
                    </div>
                </div>
            `).join('') : '<p style="font-size:13px;color:var(--text-muted);padding:12px 0;">No activities recorded yet for this lead.</p>'}
        </div>
    `;
}

function setActivityFilter(filterName) {
    activityFilter = filterName;
    renderDrawerTabContent();
}

// ------------------------------------------
// TAB 3: DEALS
// ------------------------------------------
function renderDealsTab(lead) {
    const deals = leadDealsStore[lead.id] || [];
    let openValue = 0;
    let wonValue = 0;
    deals.forEach(d => {
        if (d.status === 'won') wonValue += (d.value || 0);
        else if (d.status === 'open') openValue += (d.value || 0);
    });

    return `
        <!-- Summary Cards -->
        <div class="deal-kpi-grid">
            <div class="deal-kpi-card">
                <span style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Total Deals</span>
                <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin-top:2px;">${deals.length}</p>
            </div>
            <div class="deal-kpi-card">
                <span style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Open Value</span>
                <p style="font-size:15px;font-weight:700;color:var(--primary);margin-top:2px;">$${openValue.toLocaleString()}</p>
            </div>
            <div class="deal-kpi-card">
                <span style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Won Value</span>
                <p style="font-size:15px;font-weight:700;color:#12B76A;margin-top:2px;">$${wonValue.toLocaleString()}</p>
            </div>
        </div>

        ${deals.length > 0 ? deals.map(d => {
            const dealId = d.id;
            const dealName = d.name || `${lead.company} Deal`;
            const val = d.value || 0;
            const stage = d.stage || "Prospect";
            const prob = (d.status === 'won') ? 100 : ((d.status === 'lost') ? 0 : 60);

            return `
                <div class="deal-card" id="card-${dealId}" style="margin-bottom:14px;">
                    <div class="deal-card-header">
                        <span class="deal-card-name">${escapeHtml(dealName)}</span>
                        <span class="status-badge status-${stage.toLowerCase()}">
                            <span class="status-dot"></span>${escapeHtml(stage)}
                        </span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12.5px;margin:10px 0;">
                        <div>
                            <span style="color:var(--text-muted);">Deal Value:</span>
                            <strong style="color:var(--text-heading);">$${val.toLocaleString()}</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted);">Status:</span>
                            <strong style="color:var(--text-heading); text-transform: capitalize;">${escapeHtml(d.status || 'open')}</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted);">Closing Date:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(d.closeDate || 'TBD')}</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted);">Owner:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(d.assignee || lead.assignee || 'Unassigned')}</strong>
                        </div>
                    </div>

                    <div style="margin: 12px 0 14px;">
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:4px;">
                            <span>Pipeline Stage</span>
                            <span>${escapeHtml(stage)}</span>
                        </div>
                        <div class="score-bar-track" style="width:100%;height:6px;background-color:#EAECF0;">
                            <div class="score-bar-fill" style="width:${prob}%;background-color:var(--primary);height:100%;"></div>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;">
                        <button type="button" class="btn btn-secondary btn-xs lead-deal-view-button" aria-expanded="false" aria-controls="deal-details-${dealId}" onclick="toggleLeadDealDetails(event, '${dealId}')">
                            <span>View Deal</span>
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="deal-btn-chevron"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                    </div>

                    <div class="lead-deal-expanded-details" id="deal-details-${dealId}" aria-hidden="true" style="display: none;">
                        <div style="border-top: 1px solid var(--border-divider); margin: 14px 0 12px;"></div>
                        <div class="deal-expanded-section-title">Deal Overview</div>
                        <div class="deal-expanded-grid">
                            <div class="deal-expanded-row">
                                <span class="deal-expanded-label">Deal Name</span>
                                <span class="deal-expanded-val">${escapeHtml(dealName)}</span>
                            </div>
                            <div class="deal-expanded-row">
                                <span class="deal-expanded-label">Company</span>
                                <span class="deal-expanded-val">${escapeHtml(lead.company)}</span>
                            </div>
                            <div class="deal-expanded-row">
                                <span class="deal-expanded-label">Associated Lead</span>
                                <span class="deal-expanded-val">${escapeHtml(lead.name)}</span>
                            </div>
                            <div class="deal-expanded-row">
                                <span class="deal-expanded-label">Stage</span>
                                <span class="deal-expanded-val"><span class="status-badge status-${stage.toLowerCase()}" style="padding:1px 8px;font-size:11px;"><span class="status-dot"></span>${escapeHtml(stage)}</span></span>
                            </div>
                            <div class="deal-expanded-row">
                                <span class="deal-expanded-label">Deal Value</span>
                                <span class="deal-expanded-val" style="font-weight:700;color:var(--text-heading);">$${val.toLocaleString()}</span>
                            </div>
                            <div class="deal-expanded-row">
                                <span class="deal-expanded-label">Owner</span>
                                <span class="deal-expanded-val">${escapeHtml(d.assignee || lead.assignee || 'Unassigned')}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('') : `
            <div style="text-align:center;padding:36px 16px;color:var(--text-muted);font-size:13px;background:#F9FAFB;border:1px dashed var(--border-divider);border-radius:var(--radius-md);">
                <p style="margin-bottom:12px;">No active deals linked to this lead yet.</p>
                <button type="button" class="btn btn-primary btn-sm" onclick="openConvertContactModal(this)">
                    Convert Lead to Deal
                </button>
            </div>
        `}
    `;
}

function toggleLeadDealDetails(e, dealId) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    const panel = document.getElementById(`deal-details-${dealId}`);
    const btn = e ? e.currentTarget : document.querySelector(`[aria-controls="deal-details-${dealId}"]`);
    if (!panel || !btn) return;

    const isCurrentlyOpen = panel.classList.contains("is-expanded") || panel.style.display !== "none";

    document.querySelectorAll(".lead-deal-expanded-details").forEach(p => {
        if (p.id !== `deal-details-${dealId}`) {
            p.classList.remove("is-expanded");
            p.style.display = "none";
            p.setAttribute("aria-hidden", "true");
            const otherBtn = document.querySelector(`[aria-controls="${p.id}"]`);
            if (otherBtn) {
                otherBtn.setAttribute("aria-expanded", "false");
                otherBtn.innerHTML = `
                    <span>View Deal</span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="deal-btn-chevron"><polyline points="6 9 12 15 18 9"/></svg>
                `;
            }
        }
    });

    if (isCurrentlyOpen) {
        panel.classList.remove("is-expanded");
        panel.style.display = "none";
        panel.setAttribute("aria-hidden", "true");
        btn.setAttribute("aria-expanded", "false");
        btn.innerHTML = `
            <span>View Deal</span>
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="deal-btn-chevron"><polyline points="6 9 12 15 18 9"/></svg>
        `;
    } else {
        panel.style.display = "block";
        panel.classList.add("is-expanded");
        panel.setAttribute("aria-hidden", "false");
        btn.setAttribute("aria-expanded", "true");
        btn.innerHTML = `
            <span>Hide Details</span>
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="deal-btn-chevron"><polyline points="18 15 12 9 6 15"/></svg>
        `;

        setTimeout(() => {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 50);
    }
}

// ------------------------------------------
// TAB 4: TASKS
// ------------------------------------------
function renderTasksTab(lead) {
    const tasks = leadTasksStore[lead.id] || [];

    const filtered = tasks.filter(t => {
        if (taskFilter === "Open") return !t.completed;
        if (taskFilter === "Completed") return t.completed;
        return true;
    });

    const overdue = filtered.filter(t => t.group === "overdue");
    const today = filtered.filter(t => t.group === "today");
    const upcoming = filtered.filter(t => t.group === "upcoming");

    return `
        <!-- Toolbar -->
        <div class="drawer-tasks-toolbar">
            <div style="display:flex;gap:6px;">
                ${['All', 'Open', 'Completed'].map(f => `
                    <button class="timeline-chip ${taskFilter === f ? 'active' : ''}" onclick="setTaskFilter('${f}')">
                        ${f}
                    </button>
                `).join('')}
            </div>
            <button class="btn btn-primary btn-xs" onclick="openAddDrawerTaskModal(this)">
                + Add Task
            </button>
        </div>

        ${overdue.length > 0 ? `
            <div class="task-group-title" style="color:#F04438;">Overdue</div>
            ${overdue.map(t => renderTaskItemHtml(t)).join('')}
        ` : ''}

        ${today.length > 0 ? `
            <div class="task-group-title">Today</div>
            ${today.map(t => renderTaskItemHtml(t)).join('')}
        ` : ''}

        ${upcoming.length > 0 ? `
            <div class="task-group-title">Upcoming</div>
            ${upcoming.map(t => renderTaskItemHtml(t)).join('')}
        ` : ''}

        ${filtered.length === 0 ? `
            <div style="text-align:center;padding:32px 16px;color:var(--text-muted);font-size:13px;">
                No tasks found. Click <strong>"+ Add Task"</strong> to create a new task.
            </div>
        ` : ''}
    `;
}

function renderTaskItemHtml(t) {
    const isCompleted = t.completed;
    return `
        <div class="task-item ${isCompleted ? 'completed' : ''}">
            <div class="task-checkbox-custom" onclick="toggleTaskCompletion('${t.id}')">
                ${isCompleted ? '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>' : ''}
            </div>
            <div style="flex:1;">
                <div class="task-title" style="font-size:13px;font-weight:600;color:var(--text-heading);">${t.title}</div>
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <span>📅 Due: ${t.dueDate}</span>
                    <span>Assignee: ${t.assignee}</span>
                    <span class="status-badge" style="font-size:10px;padding:0 6px;background:${t.priority === 'High' ? '#FEF2F2' : '#F2F4F7'};color:${t.priority === 'High' ? '#F04438' : 'var(--text-secondary)'};">${t.priority}</span>
                </div>
            </div>
            <button class="btn btn-ghost btn-xs" style="padding:2px 6px;color:#F04438;" title="Delete task" onclick="deleteDrawerTask('${t.id}')">
                ✕
            </button>
        </div>
    `;
}

function setTaskFilter(filterName) {
    taskFilter = filterName;
    renderDrawerTabContent();
}

function toggleTaskCompletion(taskId) {
    const tasks = leadTasksStore[activeDrawerLeadId] || [];
    const task = tasks.find(t => String(t.id) === String(taskId));
    if (!task) return;

    const newCompleted = !task.completed;
    fetch(LEADS_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "toggle_task_status",
            task_id: taskId,
            completed: newCompleted ? "1" : "0"
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || "Failed to update task status.", "error");
            return;
        }
        task.completed = newCompleted;
        task.status = newCompleted ? "completed" : "pending";
        showToast(task.completed ? "Task marked as completed." : "Task marked as open.");
        renderDrawerTabContent();
    })
    .catch(() => {
        showToast("Network error updating task status.", "error");
    });
}

function openLeadDeleteConfirmModal() {
    const modal = document.getElementById("leadDeleteConfirmModal");
    if (modal) {
        modal.classList.add("show", "is-open");
        modal.setAttribute("aria-hidden", "false");
    }
}

function closeLeadDeleteConfirmModal() {
    const modal = document.getElementById("leadDeleteConfirmModal");
    if (modal) {
        modal.classList.remove("show", "is-open");
        modal.setAttribute("aria-hidden", "true");
    }
    pendingDeleteType = null;
    pendingDeleteTargetId = null;
}

function deleteDrawerTask(taskId) {
    pendingDeleteType = "task";
    pendingDeleteTargetId = taskId;

    const titleEl = document.getElementById("leadDeleteConfirmTitle");
    const msgEl = document.getElementById("leadDeleteConfirmMessage");
    if (titleEl) titleEl.textContent = "Delete Task";
    if (msgEl) msgEl.textContent = "Are you sure you want to delete this task?";

    openLeadDeleteConfirmModal();
}

// ------------------------------------------
// TAB 5: NOTES
// ------------------------------------------
function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function renderNotesListHtml(lead) {
    const notes = leadNotesStore[lead.id] || [];
    const query = noteSearchQuery.toLowerCase().trim();
    const filtered = notes.filter(n => !query || (n.content && n.content.toLowerCase().includes(query)) || (n.author && n.author.toLowerCase().includes(query)));

    const pinned = filtered.filter(n => n.pinned);
    const recent = filtered.filter(n => !n.pinned);

    if (filtered.length === 0) {
        return `
            <div style="text-align:center;padding:32px 16px;color:var(--text-muted);font-size:13px;">
                ${query ? 'No notes found. Try a different search term.' : 'No notes found. Click <strong>"+ Add Note"</strong> to record a meeting summary or quick note.'}
            </div>
        `;
    }

    let html = '';
    if (pinned.length > 0) {
        html += `
            <div class="task-group-title" style="color:var(--primary);">📌 Pinned Notes</div>
            ${pinned.map(n => renderNoteCardHtml(n)).join('')}
        `;
    }
    if (recent.length > 0) {
        html += `
            <div class="task-group-title">Recent Notes</div>
            ${recent.map(n => renderNoteCardHtml(n)).join('')}
        `;
    }
    return html;
}

function renderNotesTab(lead) {
    return `
        <!-- Search & Add Note Toolbar -->
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;">
            <div class="input-field-container" style="flex:1;">
                <span class="input-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="search" class="input-control input-sm has-icon" id="noteSearchInput" placeholder="Search notes..." value="${escapeHtml(noteSearchQuery)}" oninput="onNoteSearchInput(this.value)">
            </div>
            <button type="button" class="btn btn-primary btn-xs" onclick="openAddDrawerNoteModal(this)">
                + Add Note
            </button>
        </div>

        <div id="leadNotesResults">
            ${renderNotesListHtml(lead)}
        </div>
    `;
}

function renderNotesResultsOnly() {
    const resultsEl = document.getElementById("leadNotesResults");
    const lead = getLeadById(activeDrawerLeadId);
    if (resultsEl && lead) {
        resultsEl.innerHTML = renderNotesListHtml(lead);
    } else {
        renderDrawerTabContent();
    }
}

function renderNoteCardHtml(n) {
    return `
        <div class="note-card ${n.pinned ? 'pinned' : ''}">
            <div class="note-card-header">
                <span class="note-author">${escapeHtml(n.author)}</span>
                <span class="note-date">${escapeHtml(n.date)}</span>
            </div>
            <div class="note-content">${escapeHtml(n.content)}</div>
            <div class="note-actions">
                <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:${n.pinned ? 'var(--primary)' : 'var(--text-muted)'};" title="${n.pinned ? 'Unpin' : 'Pin'}" onclick="toggleNotePin('${n.id}')">
                    📌 ${n.pinned ? 'Pinned' : 'Pin'}
                </button>
                <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;" title="Edit" onclick="openEditDrawerNoteModal('${n.id}', this)">Edit</button>
                <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:#F04438;" title="Delete" onclick="deleteDrawerNote('${n.id}')">Delete</button>
            </div>
        </div>
    `;
}

function onNoteSearchInput(val) {
    noteSearchQuery = val;
    renderNotesResultsOnly();
}

function toggleNotePin(noteId) {
    const notes = leadNotesStore[activeDrawerLeadId] || [];
    const note = notes.find(n => String(n.id) === String(noteId));
    if (!note) return;

    const newPinned = !note.pinned;
    fetch(LEADS_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "toggle_note_pin",
            note_id: noteId,
            pinned: newPinned ? "1" : "0"
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || "Failed to update note pin.", "error");
            return;
        }
        note.pinned = newPinned;
        showToast(note.pinned ? "Note pinned to top." : "Note unpinned.");
        renderNotesResultsOnly();
    })
    .catch(() => {
        showToast("Network error updating note pin.", "error");
    });
}

function deleteDrawerNote(noteId) {
    pendingDeleteType = "note";
    pendingDeleteTargetId = noteId;

    const titleEl = document.getElementById("leadDeleteConfirmTitle");
    const msgEl = document.getElementById("leadDeleteConfirmMessage");
    if (titleEl) titleEl.textContent = "Delete Note";
    if (msgEl) msgEl.textContent = "Are you sure you want to delete this note?";

    openLeadDeleteConfirmModal();
}

function executeLeadItemDeletion() {
    if (pendingDeleteType === "task" && pendingDeleteTargetId && activeDrawerLeadId) {
        const taskId = pendingDeleteTargetId;
        fetch(LEADS_API, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "delete_task",
                task_id: taskId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || "Failed to delete task.", "error");
                return;
            }
            leadTasksStore[activeDrawerLeadId] = (leadTasksStore[activeDrawerLeadId] || []).filter(t => String(t.id) !== String(taskId));
            showToast("Task deleted.");
            renderDrawerTabContent();
        })
        .catch(() => {
            showToast("Network error deleting task.", "error");
        });
    } else if (pendingDeleteType === "note" && pendingDeleteTargetId && activeDrawerLeadId) {
        const noteId = pendingDeleteTargetId;
        fetch(LEADS_API, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "delete_note",
                note_id: noteId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || "Failed to delete note.", "error");
                return;
            }
            leadNotesStore[activeDrawerLeadId] = (leadNotesStore[activeDrawerLeadId] || []).filter(n => String(n.id) !== String(noteId));
            showToast("Note deleted.");
            renderNotesResultsOnly();
        })
        .catch(() => {
            showToast("Network error deleting note.", "error");
        });
    }
    closeLeadDeleteConfirmModal();
}

// Helper: Initialize empty stores for a lead (populated via lead_drawer API)
function initLeadSubStores(id, lead) {
    if (!leadTasksStore[id]) leadTasksStore[id] = [];
    if (!leadNotesStore[id]) leadNotesStore[id] = [];
    if (!leadDealsStore[id]) leadDealsStore[id] = [];
    if (!leadActivitiesStore[id]) leadActivitiesStore[id] = [];
    if (!leadMeetingsStore[id]) leadMeetingsStore[id] = [];
}

// Copy to Clipboard Toast Helper
function copyToClipboard(text, label) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(`Copied ${label || 'text'} to clipboard.`);
        }).catch(() => {
            showToast(`Copied ${label || 'text'} to clipboard.`);
        });
    } else {
        showToast(`Copied ${label || 'text'} to clipboard.`);
    }
}

// Global Toast System Helper
function showToast(message, type = "success") {
    let container = document.querySelector(".toast-container");
    if (!container) {
        container = document.createElement("div");
        container.className = "toast-container";
        container.style.cssText = "position:fixed;top:20px;right:20px;z-index:1200;display:flex;flex-direction:column;gap:8px;pointer-events:none;";
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = "toast-message";
    toast.style.cssText = "pointer-events:auto;background:#101828;color:#FFF;font-size:13px;font-weight:500;padding:10px 16px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:flex;align-items:center;gap:8px;animation:toastIn 0.2s ease-out;";
    toast.innerHTML = `<svg width="16" height="16" fill="none" stroke="#12B76A" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> <span>${message}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = "0";
        toast.style.transition = "opacity 0.25s ease";
        setTimeout(() => toast.remove(), 250);
    }, 2800);
}

function getLeadById(id) {
    if (!id) return null;
    const sId = String(id);
    if (typeof leadsStore !== "undefined" && Array.isArray(leadsStore) && leadsStore.length > 0) {
        const found = leadsStore.find(l => String(l.id) === sId);
        if (found) return found;
    }
    if (window.leadsStore && Array.isArray(window.leadsStore) && window.leadsStore.length > 0) {
        const found = window.leadsStore.find(l => String(l.id) === sId);
        if (found) return found;
    }
    if (window.INITIAL_LEADS_DATA && Array.isArray(window.INITIAL_LEADS_DATA) && window.INITIAL_LEADS_DATA.length > 0) {
        const found = window.INITIAL_LEADS_DATA.find(l => String(l.id) === sId);
        if (found) return found;
    }
    return null;
}

// ==========================================
// MODAL LOGIC & FORM SUBMISSIONS
// ==========================================

function setupModalForms() {
    // Schedule Meeting Form
    const scheduleForm = document.getElementById("scheduleMeetingForm");
    if (scheduleForm) {
        scheduleForm.addEventListener("submit", function (e) {
            e.preventDefault();
            if (!activeDrawerLeadId) return;

            const title = document.getElementById("meetingTitle").value.trim();
            const date = document.getElementById("meetingDate").value;
            const time = document.getElementById("meetingTime").value || "10:00";
            const duration = document.getElementById("meetingDuration").value;
            const meetingType = document.getElementById("meetingType").value;
            const assignee = document.getElementById("meetingAssignee").value;
            const desc = document.getElementById("meetingDesc").value.trim();

            const submitBtn = scheduleForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            fetch(LEADS_API, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "schedule_meeting",
                    lead_id: activeDrawerLeadId,
                    title: title,
                    date: date,
                    start_time: time,
                    duration: duration,
                    meeting_type: meetingType,
                    assigned_to: assignee,
                    description: desc
                })
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) submitBtn.disabled = false;
                if (!data.success) {
                    showToast(data.message || "Failed to schedule meeting.", "error");
                    return;
                }
                const meeting = (data.data && data.data.meeting) ? data.data.meeting : data.meeting;
                if (meeting) {
                    if (!leadMeetingsStore[activeDrawerLeadId]) leadMeetingsStore[activeDrawerLeadId] = [];
                    leadMeetingsStore[activeDrawerLeadId].unshift(meeting);
                }

                const act = (data.data && data.data.activity) ? data.data.activity : data.activity;
                if (act) {
                    if (!leadActivitiesStore[activeDrawerLeadId]) leadActivitiesStore[activeDrawerLeadId] = [];
                    leadActivitiesStore[activeDrawerLeadId].unshift(act);
                }

                closeActiveLeadChildModal();
                showToast("Meeting scheduled successfully.");
                renderDrawerTabContent();
            })
            .catch(err => {
                if (submitBtn) submitBtn.disabled = false;
                showToast("Network error scheduling meeting.", "error");
            });
        });
    }

    // Add Drawer Task Form
    const addTaskForm = document.getElementById("addDrawerTaskForm");
    if (addTaskForm) {
        addTaskForm.addEventListener("submit", function (e) {
            e.preventDefault();
            if (!activeDrawerLeadId) return;

            const title = document.getElementById("taskTitleInput").value.trim();
            const type = document.getElementById("taskTypeInput").value;
            const priority = document.getElementById("taskPriorityInput").value;
            const dueDate = document.getElementById("taskDueDateInput").value;
            const dueTime = document.getElementById("taskDueTimeInput") ? document.getElementById("taskDueTimeInput").value : "17:00";
            const assignee = document.getElementById("taskAssigneeInput").value;

            if (!title) return;

            const submitBtn = addTaskForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            const fullTitle = `[${type}] ${title}`;
            let dueDateTime = dueDate;
            if (dueDate && dueTime) {
                dueDateTime = `${dueDate} ${dueTime}:00`;
            }

            fetch(LEADS_API, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "create_task",
                    lead_id: activeDrawerLeadId,
                    title: fullTitle,
                    due_date: dueDateTime,
                    priority: priority.toLowerCase(),
                    assigned_to: assignee
                })
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) submitBtn.disabled = false;
                if (!data.success) {
                    showToast(data.message || "Failed to create task.", "error");
                    return;
                }
                const task = (data.data && data.data.task) ? data.data.task : data.task;
                if (task) {
                    if (!leadTasksStore[activeDrawerLeadId]) leadTasksStore[activeDrawerLeadId] = [];
                    leadTasksStore[activeDrawerLeadId].unshift(task);
                }

                const act = (data.data && data.data.activity) ? data.data.activity : data.activity;
                if (act) {
                    if (!leadActivitiesStore[activeDrawerLeadId]) leadActivitiesStore[activeDrawerLeadId] = [];
                    leadActivitiesStore[activeDrawerLeadId].unshift(act);
                }

                addTaskForm.reset();
                closeActiveLeadChildModal();
                showToast("Task created successfully.");
                renderDrawerTabContent();
            })
            .catch(err => {
                if (submitBtn) submitBtn.disabled = false;
                showToast("Network error creating task.", "error");
            });
        });
    }

    // Add / Edit Drawer Note Form
    const addNoteForm = document.getElementById("addDrawerNoteForm");
    if (addNoteForm) {
        addNoteForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const contentEl = document.getElementById("noteContentInput");
            const content = contentEl ? contentEl.value.trim() : "";
            const pinnedEl = document.getElementById("notePinCheckbox");
            const pinned = pinnedEl ? pinnedEl.checked : false;

            if (!activeDrawerLeadId || !content) return;

            const submitBtn = document.getElementById("noteSubmitBtn");
            if (submitBtn) submitBtn.disabled = true;

            if (editingDrawerNoteId) {
                fetch(LEADS_API, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({
                        action: "update_note",
                        note_id: editingDrawerNoteId,
                        content: content,
                        pinned: pinned ? "1" : "0"
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (submitBtn) submitBtn.disabled = false;
                    if (!data.success) {
                        showToast(data.message || "Failed to update note.", "error");
                        return;
                    }
                    const notes = leadNotesStore[activeDrawerLeadId] || [];
                    const note = notes.find(n => String(n.id) === String(editingDrawerNoteId));
                    if (note) {
                        note.content = content;
                        note.pinned = pinned;
                    }
                    editingDrawerNoteId = null;
                    closeActiveLeadChildModal();
                    showToast("Note updated successfully.");
                    renderNotesResultsOnly();
                })
                .catch(err => {
                    if (submitBtn) submitBtn.disabled = false;
                    showToast("Network error updating note.", "error");
                });
            } else {
                fetch(LEADS_API, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({
                        action: "create_note",
                        lead_id: activeDrawerLeadId,
                        content: content,
                        pinned: pinned ? "1" : "0"
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (submitBtn) submitBtn.disabled = false;
                    if (!data.success) {
                        showToast(data.message || "Failed to save note.", "error");
                        return;
                    }
                    const note = (data.data && data.data.note) ? data.data.note : data.note;
                    if (note) {
                        if (!leadNotesStore[activeDrawerLeadId]) leadNotesStore[activeDrawerLeadId] = [];
                        leadNotesStore[activeDrawerLeadId].unshift(note);
                    }

                    const act = (data.data && data.data.activity) ? data.data.activity : data.activity;
                    if (act) {
                        if (!leadActivitiesStore[activeDrawerLeadId]) leadActivitiesStore[activeDrawerLeadId] = [];
                        leadActivitiesStore[activeDrawerLeadId].unshift(act);
                    }

                    contentEl.value = "";
                    if (pinnedEl) pinnedEl.checked = false;
                    closeActiveLeadChildModal();
                    showToast("Note saved successfully.");
                    renderNotesResultsOnly();
                })
                .catch(err => {
                    if (submitBtn) submitBtn.disabled = false;
                    showToast("Network error saving note.", "error");
                });
            }
        });
    }

    // Edit Lead Form
    const editForm = document.getElementById("editLeadForm");
    if (editForm) {
        editForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const lead = getLeadById(activeDrawerLeadId);
            if (!lead) return;

            const name = document.getElementById("editLeadName").value.trim();
            const jobTitle = document.getElementById("editLeadJobTitle").value.trim();
            const company = document.getElementById("editLeadCompany").value.trim();
            const email = document.getElementById("editLeadEmail").value.trim();
            const phone = document.getElementById("editLeadPhone").value.trim();
            const whatsapp = document.getElementById("editLeadWhatsapp").value.trim();
            const location = document.getElementById("editLeadLocation").value.trim();
            const status = document.getElementById("editLeadStatus").value;
            const score = document.getElementById("editLeadScore").value;
            const value = document.getElementById("editLeadValue").value;
            const source = document.getElementById("editLeadSource").value.trim();
            const owner = document.getElementById("editLeadOwner").value;

            const submitBtn = editForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            fetch(LEADS_API, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "update_lead",
                    id: activeDrawerLeadId,
                    name: name,
                    job_title: jobTitle,
                    company: company,
                    email: email,
                    phone: phone,
                    whatsapp: whatsapp,
                    location: location,
                    status: status,
                    score: score,
                    value: value,
                    source: source,
                    assigned_to: owner
                })
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) submitBtn.disabled = false;
                if (!data.success) {
                    showToast(data.message || "Failed to update lead.", "error");
                    return;
                }

                const updated = (data.data && data.data.lead) ? data.data.lead : data.lead;
                if (!updated) {
                    showToast("Lead updated successfully.");
                    closeActiveLeadChildModal();
                    return;
                }

                const idx = leadsStore.findIndex(l => String(l.id) === String(activeDrawerLeadId));
                if (idx !== -1) {
                    leadsStore[idx] = updated;
                } else {
                    leadsStore.unshift(updated);
                }
                window.leadsStore = leadsStore;

                // Update table row
                const row = document.querySelector(`tr[data-id="${updated.id}"]`);
                if (row) {
                    row.setAttribute("data-name", updated.name);
                    row.setAttribute("data-company", updated.company);
                    row.setAttribute("data-status", updated.status);
                    row.setAttribute("data-score", updated.score);
                    row.setAttribute("data-value", updated.value);
                    row.setAttribute("data-source", updated.source);
                    row.setAttribute("data-assignee", updated.assignee);
                    
                    const nameEl = row.querySelector(".lead-name-clickable");
                    if (nameEl) nameEl.textContent = updated.name;
                    
                    const cells = row.querySelectorAll("td");
                    if (cells[1]) cells[1].textContent = updated.company;
                    
                    const statusBadge = row.querySelector(".status-badge");
                    if (statusBadge) {
                        statusBadge.className = `status-badge status-${(updated.status || '').toLowerCase()}`;
                        statusBadge.innerHTML = `<span class="status-dot"></span>${escapeHtml(updated.status)}`;
                    }
                    
                    const scoreFill = row.querySelector(".score-bar-fill");
                    const scoreText = row.querySelector(".score-bar-text");
                    if (scoreFill) {
                        const scoreNum = updated.score || 0;
                        const barColor = (scoreNum >= 80) ? '#12B76A' : ((scoreNum >= 55) ? '#F79009' : '#F04438');
                        scoreFill.style.width = `${scoreNum}%`;
                        scoreFill.style.backgroundColor = barColor;
                    }
                    if (scoreText) scoreText.textContent = updated.score;
                    
                    if (cells[4]) cells[4].textContent = `$${(Number(updated.value) || 0).toLocaleString()}`;
                    
                    if (cells[5]) cells[5].innerHTML = `<span style="font-size: 12px; color: var(--text-secondary); background-color: var(--border-divider); padding: 2px 8px; border-radius: var(--radius-sm);">${escapeHtml(updated.source || '')}</span>`;
                    
                    if (cells[6]) {
                        cells[6].innerHTML = `
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div class="avatar avatar-xs" style="background-color: ${updated.assigneeColor || '#7C3AED'};">
                                    ${escapeHtml(updated.assigneeInitials || 'U')}
                                </div>
                                <span style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(updated.assignee || 'Unassigned')}</span>
                            </div>
                        `;
                    }
                }

                closeActiveLeadChildModal();
                showToast("Lead updated successfully.");

                const breadcrumb = document.getElementById("drawerBreadcrumb");
                if (breadcrumb) breadcrumb.textContent = `Leads / ${updated.name}`;
                renderDrawerTabContent();
                if (typeof window.updateLeadKPICounts === "function") {
                    window.updateLeadKPICounts();
                }
            })
            .catch(err => {
                if (submitBtn) submitBtn.disabled = false;
                showToast("Network error updating lead.", "error");
            });
        });
    }
}

function confirmConvertContact() {
    if (!activeDrawerLeadId) return;

    fetch(LEADS_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "convert_lead",
            lead_id: activeDrawerLeadId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || "Failed to convert lead.", "error");
            return;
        }

        const lead = getLeadById(activeDrawerLeadId);
        if (lead) {
            lead.status = "Qualified";
        }

        const dealObj = data.deal || (data.data && data.data.deal);
        if (dealObj) {
            if (!leadDealsStore[activeDrawerLeadId]) leadDealsStore[activeDrawerLeadId] = [];
            leadDealsStore[activeDrawerLeadId].unshift(dealObj);
        }

        const actObj = data.activity || (data.data && data.activity);
        if (actObj) {
            if (!leadActivitiesStore[activeDrawerLeadId]) leadActivitiesStore[activeDrawerLeadId] = [];
            leadActivitiesStore[activeDrawerLeadId].unshift(actObj);
        }

        const row = document.querySelector(`tr[data-id="${activeDrawerLeadId}"]`);
        if (row) {
            row.setAttribute("data-status", "Qualified");
            const statusBadge = row.querySelector(".status-badge");
            if (statusBadge) {
                statusBadge.className = "status-badge status-qualified";
                statusBadge.innerHTML = '<span class="status-dot"></span>Qualified';
            }
        }

        closeActiveLeadChildModal();
        showToast("Lead converted to contact and deal created.");
        renderDrawerTabContent();
        if (typeof window.updateLeadKPICounts === "function") {
            window.updateLeadKPICounts();
        }
    })
    .catch(() => {
        showToast("Network error converting lead.", "error");
    });
}

function saveQuickStatus() {
    if (!activeDrawerLeadId) return;
    const newStatus = document.getElementById("quickStatusSelect").value;

    fetch(LEADS_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "update_status",
            lead_id: activeDrawerLeadId,
            status: newStatus
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || "Failed to update status.", "error");
            return;
        }

        const lead = getLeadById(activeDrawerLeadId);
        if (lead) {
            lead.status = newStatus;
        }

        const actObj = data.activity || (data.data && data.data.activity);
        if (actObj) {
            if (!leadActivitiesStore[activeDrawerLeadId]) leadActivitiesStore[activeDrawerLeadId] = [];
            leadActivitiesStore[activeDrawerLeadId].unshift(actObj);
        }

        const row = document.querySelector(`tr[data-id="${activeDrawerLeadId}"]`);
        if (row) {
            row.setAttribute("data-status", newStatus);
            const statusBadge = row.querySelector(".status-badge");
            if (statusBadge) {
                statusBadge.className = `status-badge status-${newStatus.toLowerCase()}`;
                statusBadge.innerHTML = `<span class="status-dot"></span>${escapeHtml(newStatus)}`;
            }
        }

        closeActiveLeadChildModal();
        showToast(`Lead status updated to ${newStatus}.`);
        renderDrawerTabContent();
        if (typeof window.updateLeadKPICounts === "function") {
            window.updateLeadKPICounts();
        }
    })
    .catch(() => {
        showToast("Network error updating status.", "error");
    });
}

function saveQuickOwner() {
    if (!activeDrawerLeadId) return;
    const ownerSelect = document.getElementById("quickOwnerSelect");
    const newOwnerId = ownerSelect ? ownerSelect.value : "";
    const newOwnerName = ownerSelect && ownerSelect.selectedIndex !== -1 ? ownerSelect.options[ownerSelect.selectedIndex].text : "";

    fetch(LEADS_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "update_owner",
            lead_id: activeDrawerLeadId,
            assigned_to: newOwnerId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || "Failed to update owner.", "error");
            return;
        }

        const lead = getLeadById(activeDrawerLeadId);
        if (lead) {
            lead.assignee = newOwnerName;
            lead.assigneeId = newOwnerId ? parseInt(newOwnerId) : null;
            lead.assigned_to = newOwnerId ? parseInt(newOwnerId) : null;
        }

        const actObj = data.activity || (data.data && data.data.activity);
        if (actObj) {
            if (!leadActivitiesStore[activeDrawerLeadId]) leadActivitiesStore[activeDrawerLeadId] = [];
            leadActivitiesStore[activeDrawerLeadId].unshift(actObj);
        }

        const row = document.querySelector(`tr[data-id="${activeDrawerLeadId}"]`);
        if (row) {
            row.setAttribute("data-assignee", newOwnerName);
            const cells = row.querySelectorAll("td");
            if (cells[6]) {
                const initials = newOwnerName ? newOwnerName.split(" ").map(p => p[0]).join("").toUpperCase().substring(0, 2) : "U";
                cells[6].innerHTML = `
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div class="avatar avatar-xs" style="background-color: #7C3AED;">
                            ${escapeHtml(initials)}
                        </div>
                        <span style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(newOwnerName || 'Unassigned')}</span>
                    </div>
                `;
            }
        }

        closeActiveLeadChildModal();
        showToast(`Lead owner updated to ${newOwnerName}.`);
        renderDrawerTabContent();
    })
    .catch(() => {
        showToast("Network error updating owner.", "error");
    });
}

function updateDrawerArchiveBtn(isArchived) {
    const btn = document.getElementById("drawerArchiveBtn");
    const textEl = document.getElementById("drawerArchiveBtnText");
    if (!textEl) return;
    if (isArchived) {
        textEl.textContent = "Unarchive Lead";
        if (btn) btn.setAttribute("title", "Restore this lead to active leads");
    } else {
        textEl.textContent = "Archive Lead";
        if (btn) btn.setAttribute("title", "Archive this lead");
    }
}

function toggleArchiveCurrentLead() {
    if (!activeDrawerLeadId) return;
    const lead = getLeadById(activeDrawerLeadId);
    if (lead && lead.isArchived) {
        unarchiveCurrentLead(activeDrawerLeadId);
    } else {
        archiveCurrentLead(activeDrawerLeadId);
    }
}

function archiveCurrentLead(leadId) {
    const id = leadId || activeDrawerLeadId;
    if (!id) return;

    fetch(LEADS_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "archive_lead",
            lead_id: id
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || "Failed to archive lead.", "error");
            return;
        }

        const lead = getLeadById(id);
        if (lead) {
            lead.isArchived = true;
        }

        const actObj = data.activity || (data.data && data.data.activity);
        if (actObj) {
            if (!leadActivitiesStore[id]) leadActivitiesStore[id] = [];
            leadActivitiesStore[id].unshift(actObj);
        }

        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
            row.setAttribute("data-archived", "1");
        }

        updateDrawerArchiveBtn(true);
        showToast("Lead archived successfully.");
        renderDrawerTabContent();

        if (typeof window.filterTableRows === "function") {
            window.filterTableRows(false);
        }
        if (typeof window.updateLeadKPICounts === "function") {
            window.updateLeadKPICounts();
        }
    })
    .catch(() => {
        showToast("Network error archiving lead.", "error");
    });
}

function unarchiveCurrentLead(leadId) {
    const id = leadId || activeDrawerLeadId;
    if (!id) return;

    fetch(LEADS_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "unarchive_lead",
            lead_id: id
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || "Failed to unarchive lead.", "error");
            return;
        }

        const lead = getLeadById(id);
        if (lead) {
            lead.isArchived = false;
        }

        const actObj = data.activity || (data.data && data.data.activity);
        if (actObj) {
            if (!leadActivitiesStore[id]) leadActivitiesStore[id] = [];
            leadActivitiesStore[id].unshift(actObj);
        }

        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
            row.setAttribute("data-archived", "0");
        }

        updateDrawerArchiveBtn(false);
        showToast("Lead restored from archive.");
        renderDrawerTabContent();

        if (typeof window.filterTableRows === "function") {
            window.filterTableRows(false);
        }
        if (typeof window.updateLeadKPICounts === "function") {
            window.updateLeadKPICounts();
        }
    })
    .catch(() => {
        showToast("Network error unarchiving lead.", "error");
    });
}

window.updateDrawerArchiveBtn = updateDrawerArchiveBtn;
window.toggleArchiveCurrentLead = toggleArchiveCurrentLead;
window.archiveCurrentLead = archiveCurrentLead;
window.unarchiveCurrentLead = unarchiveCurrentLead;

function exportLeadsCsv() {
    const state = (activeLeadFilters && activeLeadFilters.leadState) ? activeLeadFilters.leadState : 'active';
    window.location.href = `${LEADS_API}?action=export_csv&archive_status=${encodeURIComponent(state)}`;
}

function deleteCurrentLeadFromDrawer() {
    showConfirmModal({
        title: "Delete Lead",
        message: "Are you sure you want to delete this lead? This action cannot be undone.",
        type: "danger",
        confirmText: "Delete Lead",
        onConfirm: function() {
            deleteLeadRow(activeDrawerLeadId);
            closeLeadDrawer();
        }
    });
}

function openAddLeadModal() {
    const modal = document.getElementById("addLeadModal");
    if (modal) modal.classList.add("show");
}

function closeAddLeadModal() {
    const modal = document.getElementById("addLeadModal");
    if (modal) modal.classList.remove("show");
}

// Global Filter Popover & Delete Confirmation Modal Click & Escape Key Listeners
document.addEventListener("click", function (e) {
    const popover = document.getElementById("leadsFilterPopover");
    const btn = document.getElementById("btnLeadFilter");
    if (popover && popover.classList.contains("show")) {
        if (!popover.contains(e.target) && !btn.contains(e.target)) {
            closeLeadFilterPopover();
        }
    }

    const deleteModal = document.getElementById("leadDeleteConfirmModal");
    if (deleteModal && deleteModal.classList.contains("show")) {
        const content = deleteModal.querySelector(".modal-content");
        if (content && !content.contains(e.target) && e.target === deleteModal) {
            closeLeadDeleteConfirmModal();
        }
    }
});

document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
        const deleteModal = document.getElementById("leadDeleteConfirmModal");
        if (deleteModal && deleteModal.classList.contains("show")) {
            closeLeadDeleteConfirmModal();
            return;
        }

        const popover = document.getElementById("leadsFilterPopover");
        if (popover && popover.classList.contains("show")) {
            closeLeadFilterPopover();
            const btn = document.getElementById("btnLeadFilter");
            if (btn) btn.focus();
        }
    }
});

