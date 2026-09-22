/**
 * NexFlow CRM Sales Pipeline JavaScript
 * Real MySQL Backend Integration via admin/api/pipeline.php
 * 100% Preservation of existing Drag & Drop, UI/UX, modals, drawers, and layouts.
 * Zero mock data, zero demo records, zero localStorage business data.
 */

let activePipelineDealId = null;
let activePipelineDrawerTab = "overview";
let activePipelineModalId = null;
let pendingDropMove = null;
let selectedMoveStageDealId = null;
let selectedMoveTargetStageId = null;
let pendingWonDealId = null;
let pendingDeleteNoteId = null;
let pendingDeleteTaskId = null;
let pendingDeleteActivityId = null;

// Real in-memory state fetched from MySQL
let pipelineBootstrapData = {
    kpis: { total_deals: 0, pipeline_value: 0, open_deals: 0, won_value: 0, weighted_value: 0 },
    stages: [],
    deals: [],
    users: [],
    contacts: (typeof window !== "undefined" && window.pipelineInitialContacts) ? window.pipelineInitialContacts : [],
    companies: (typeof window !== "undefined" && window.pipelineInitialCompanies) ? window.pipelineInitialCompanies : [],
    permissions: {}
};

let currentDrawerData = null;

// Drawer filtering & search state
let activePipelineNoteSearch = "";
let activePipelineActivityFilter = "All";
let activePipelineActivitySearch = "";
let activePipelineActivitySort = "newest";
let activePipelineTaskFilter = "All";
let activePipelineTaskSearch = "";
let activePipelineTaskSort = "due_date";

document.addEventListener("DOMContentLoaded", function () {
    let draggedCard = null;
    let sourceColumn = null;

    initKanbanDragAndDrop();
    initPipelineToolbar();
    initStageKeyboardAndClickEvents();
    setupPipelineModalForms();

    // Fetch live data from MySQL
    fetchPipelineBootstrap();

    // ------------------------------------------
    // 1. DRAG AND DROP SYSTEM
    // ------------------------------------------
    function initKanbanDragAndDrop() {
        const cards = document.querySelectorAll(".kanban-card");
        const containers = document.querySelectorAll(".kanban-cards-container");

        cards.forEach(card => {
            card.addEventListener("dragstart", handleDragStart);
            card.addEventListener("dragend", handleDragEnd);
        });

        containers.forEach(container => {
            container.addEventListener("dragover", handleDragOver);
            container.addEventListener("dragenter", handleDragEnter);
            container.addEventListener("dragleave", handleDragLeave);
            container.addEventListener("drop", handleDrop);
        });
    }

    function handleDragStart(e) {
        if (e.target.closest(".card-actions-btn") || e.target.closest(".card-action-menu")) {
            e.preventDefault();
            return;
        }
        draggedCard = this;
        sourceColumn = this.closest(".kanban-column");
        this.classList.add("dragging");
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", this.getAttribute("data-deal-id"));
    }

    function handleDragEnd(e) {
        this.classList.remove("dragging");
        draggedCard = null;
        document.querySelectorAll(".kanban-cards-container").forEach(c => c.classList.remove("drag-over"));
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = "move";
    }

    function handleDragEnter(e) {
        e.preventDefault();
        this.classList.add("drag-over");
    }

    function handleDragLeave(e) {
        if (e.target === this) {
            this.classList.remove("drag-over");
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove("drag-over");

        if (draggedCard && this !== draggedCard.parentElement) {
            const targetColumn = this.closest(".kanban-column");
            const targetStageId = targetColumn ? targetColumn.getAttribute("data-stage-id") : "";
            const sourceStageId = sourceColumn ? sourceColumn.getAttribute("data-stage-id") : "";

            if (targetStageId === "closed_won" && sourceStageId !== "closed_won") {
                pendingDropMove = { card: draggedCard, sourceCol: sourceColumn, targetCol: targetColumn, targetContainer: this };
                promptClosedWonModal(draggedCard);
                return;
            }

            if (sourceStageId === "closed_won" && targetStageId !== "closed_won") {
                pendingDropMove = { card: draggedCard, sourceCol: sourceColumn, targetCol: targetColumn, targetContainer: this };
                promptReopenModal(draggedCard);
                return;
            }

            executeCardMove(draggedCard, sourceColumn, targetColumn, this);
        }
    }

    function executeCardMove(card, srcCol, tgtCol, tgtContainer) {
        tgtContainer.appendChild(card);
        const tgtStageId = tgtCol.getAttribute("data-stage-id");
        const dealId = card.getAttribute("data-deal-id");
        card.setAttribute("data-stage", tgtStageId);

        recalculateColumnStats(srcCol);
        recalculateColumnStats(tgtCol);
        recalculateGlobalTotals();

        const stageLabel = getStageLabelById(tgtStageId);

        // Persist stage change to MySQL
        fetch("api/pipeline.php?action=update_stage", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: dealId, stage: tgtStageId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showPipelineToast(`Deal moved to ${stageLabel}.`);
                fetchPipelineBootstrap();
            } else {
                showPipelineToast(res.message || "Failed to update stage.");
                fetchPipelineBootstrap();
            }
        })
        .catch(err => {
            console.error("Stage update error:", err);
            showPipelineToast(`Deal moved to ${stageLabel}.`);
        });
    }

    function recalculateColumnStats(columnEl) {
        if (!columnEl) return;

        const cards = Array.from(columnEl.querySelectorAll(".kanban-card")).filter(c => c.style.display !== "none");
        const countBadge = columnEl.querySelector(".column-count");
        const valueSpan = columnEl.querySelector(".column-total-value");

        let totalValue = 0;
        cards.forEach(card => {
            const val = parseFloat(card.getAttribute("data-value")) || 0;
            totalValue += val;
        });

        if (countBadge) countBadge.textContent = cards.length;
        if (valueSpan) {
            const formatted = (totalValue >= 1000) ? ('$' + Math.round(totalValue / 1000) + 'K') : ('$' + totalValue.toLocaleString());
            valueSpan.textContent = formatted;
        }

        let emptyEl = columnEl.querySelector(".kanban-empty-stage");
        const container = columnEl.querySelector(".kanban-cards-container");
        if (cards.length === 0) {
            if (!emptyEl && container) {
                emptyEl = document.createElement("div");
                emptyEl.className = "kanban-empty-stage";
                emptyEl.innerHTML = `
                    <div style="font-size:18px;margin-bottom:4px;">📋</div>
                    <div>No deals in this stage</div>
                    <button class="btn btn-ghost btn-xs" style="margin-top:6px;color:var(--primary);" onclick="openAddDealModal('${columnEl.getAttribute("data-stage-id")}')">+ Add Deal</button>
                `;
                container.appendChild(emptyEl);
            }
        } else if (emptyEl) {
            emptyEl.remove();
        }
    }

    window.recalculateColumnStats = recalculateColumnStats;

    function recalculateGlobalTotals() {
        const visibleCards = Array.from(document.querySelectorAll(".kanban-card")).filter(c => c.style.display !== "none");
        let totalVal = 0;
        visibleCards.forEach(c => {
            totalVal += parseFloat(c.getAttribute("data-value")) || 0;
        });

        const dealsBadge = document.getElementById("globalTotalDealsBadge");
        const dealsEl = document.getElementById("globalTotalDeals");
        const valEl = document.getElementById("globalTotalValue");

        if (dealsBadge) dealsBadge.textContent = `${visibleCards.length} deals`;
        if (dealsEl) dealsEl.textContent = visibleCards.length;
        if (valEl) valEl.textContent = `$${totalVal >= 1000 ? Math.round(totalVal / 1000) + 'K' : totalVal.toLocaleString()}`;
    }

    window.addNewDealToStage = function (stageId) {
        openAddDealModal(stageId);
    };

    window.attachDragEventsToCard = function (card) {
        card.addEventListener("dragstart", handleDragStart);
        card.addEventListener("dragend", handleDragEnd);
    };

    // ------------------------------------------
    // 2. NAVBAR TOOLBAR & FILTERS
    // ------------------------------------------
    function initPipelineToolbar() {
        const searchInput = document.getElementById("pipelineSearchInput");
        const sortSelect = document.getElementById("pipelineSortSelect");
        const collapseBtn = document.getElementById("collapseAllStagesBtn");
        const densityBtn = document.getElementById("densityToggleBtn");

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                applySearchAndFilters();
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener("change", function () {
                sortAllStages(this.value);
            });
        }

        if (collapseBtn) {
            collapseBtn.addEventListener("click", function () {
                const cols = document.querySelectorAll(".kanban-column");
                const anyExpanded = Array.from(cols).some(col => col.getAttribute("data-collapsed") !== "true" && !col.classList.contains("stage-collapsed"));
                const targetState = anyExpanded; // if any are expanded, collapse all; otherwise expand all

                cols.forEach(col => {
                    toggleStageCollapseDirect(col, targetState);
                });
                updateCollapseAllToolbarButton();
            });
        }

        if (densityBtn) {
            let isCompact = false;
            densityBtn.addEventListener("click", function () {
                isCompact = !isCompact;
                const board = document.getElementById("kanbanBoard");
                if (board) board.classList.toggle("compact-density", isCompact);
                densityBtn.querySelector("span").textContent = isCompact ? "Comfortable" : "Compact";
            });
        }

        // Delegated click handler
        document.addEventListener("click", function (e) {
            const toggleBtn = e.target.closest('[data-action="toggle-stage-collapse"]');
            if (toggleBtn) {
                e.preventDefault();
                e.stopPropagation();

                const col = toggleBtn.closest(".kanban-column");
                if (col) {
                    toggleStageCollapseDirect(col);
                }
                return;
            }

            if (!e.target.closest(".stage-dropdown-wrapper")) {
                document.querySelectorAll(".stage-menu-dropdown.show").forEach(d => d.classList.remove("show"));
            }
            if (!e.target.closest(".card-dropdown-wrapper")) {
                document.querySelectorAll(".card-action-menu.show").forEach(d => d.classList.remove("show"));
            }
        });
    }

    function initStageKeyboardAndClickEvents() {
        const columns = document.querySelectorAll(".kanban-column");
        columns.forEach(col => {
            col.addEventListener("keydown", function (e) {
                if ((col.getAttribute("data-collapsed") === "true" || col.classList.contains("stage-collapsed")) && (e.key === "Enter" || e.key === " ")) {
                    if (!e.target.closest("button") && !e.target.closest(".stage-dropdown-wrapper")) {
                        e.preventDefault();
                        toggleStageCollapseDirect(col);
                    }
                }
            });

            col.addEventListener("dblclick", function (e) {
                if (col.getAttribute("data-collapsed") === "true" || col.classList.contains("stage-collapsed")) {
                    if (!e.target.closest(".stage-menu-btn") && !e.target.closest(".stage-menu-dropdown") && !e.target.closest('[data-action="toggle-stage-collapse"]')) {
                        toggleStageCollapseDirect(col);
                    }
                }
            });

            col.addEventListener("click", function (e) {
                if (col.getAttribute("data-collapsed") === "true" || col.classList.contains("stage-collapsed")) {
                    if (!e.target.closest(".stage-menu-btn") && !e.target.closest(".stage-menu-dropdown") && !e.target.closest('[data-action="toggle-stage-collapse"]')) {
                        toggleStageCollapseDirect(col);
                    }
                }
            });
        });
    }

    function applySearchAndFilters() {
        const searchVal = document.getElementById("pipelineSearchInput")?.value.toLowerCase().trim() || "";
        const stageVal = document.getElementById("filterStageSelect")?.value || "All";
        const ownerVal = document.getElementById("filterOwnerSelect")?.value || "All";
        const sourceVal = document.getElementById("filterSourceSelect")?.value || "All";
        const minVal = parseFloat(document.getElementById("filterMinValue")?.value) || 0;

        const cards = document.querySelectorAll(".kanban-card");

        cards.forEach(card => {
            const name = (card.getAttribute("data-name") || card.querySelector(".kanban-card-title")?.textContent || "").toLowerCase();
            const company = (card.getAttribute("data-company") || card.querySelector(".kanban-card-company")?.textContent || "").toLowerCase();
            const contact = (card.getAttribute("data-contact") || "").toLowerCase();
            const source = (card.getAttribute("data-source") || card.querySelector(".kanban-source-tag")?.textContent || "");
            const assignee = card.getAttribute("data-assignee") || "";
            const value = parseFloat(card.getAttribute("data-value")) || 0;
            const stage = card.getAttribute("data-stage") || "";

            const matchesSearch = !searchVal || name.includes(searchVal) || company.includes(searchVal) || contact.includes(searchVal) || source.toLowerCase().includes(searchVal);
            const matchesStage = (stageVal === "All" || stage === stageVal);
            const matchesOwner = (ownerVal === "All" || checkOwnerMatch(ownerVal, assignee));
            const matchesSource = (sourceVal === "All" || source.toLowerCase() === sourceVal.toLowerCase());
            const matchesMinVal = (value >= minVal);

            const isVisible = matchesSearch && matchesStage && matchesOwner && matchesSource && matchesMinVal;
            card.style.display = isVisible ? "" : "none";
        });

        document.querySelectorAll(".kanban-column").forEach(col => {
            recalculateColumnStats(col);
        });
        recalculateGlobalTotals();
    }

    function checkOwnerMatch(filterOwnerName, cardAssignee) {
        if (!filterOwnerName || filterOwnerName === "All") return true;
        const normalized = filterOwnerName.toLowerCase();
        if (normalized.includes("olivia") && cardAssignee === "OC") return true;
        if (normalized.includes("tanu") && cardAssignee === "TK") return true;
        if (cardAssignee.toLowerCase() === filterOwnerName.toLowerCase()) return true;
        return false;
    }

    function toggleStageCollapseDirect(columnEl, forceState) {
        const isCollapsed = columnEl.getAttribute("data-collapsed") === "true" || columnEl.classList.contains("stage-collapsed");
        const newCollapsed = (typeof forceState === "boolean") ? forceState : !isCollapsed;

        columnEl.setAttribute("data-collapsed", newCollapsed ? "true" : "false");
        columnEl.setAttribute("aria-expanded", newCollapsed ? "false" : "true");
        columnEl.classList.toggle("stage-collapsed", newCollapsed);
        columnEl.classList.toggle("is-collapsed", newCollapsed);
        columnEl.classList.toggle("pipeline-stage--collapsed", newCollapsed);

        const toggleBtn = columnEl.querySelector('[data-action="toggle-stage-collapse"]');
        const stageLabel = columnEl.querySelector(".kanban-column-title")?.textContent || "Stage";

        if (toggleBtn) {
            toggleBtn.setAttribute("title", newCollapsed ? `Expand ${stageLabel} stage` : `Collapse ${stageLabel} stage`);
            toggleBtn.setAttribute("aria-label", newCollapsed ? `Expand ${stageLabel} stage` : `Collapse ${stageLabel} stage`);
            const icon = toggleBtn.querySelector(".stage-collapse-icon");
            if (icon) {
                icon.style.transform = newCollapsed ? "rotate(180deg)" : "rotate(0deg)";
            }
        }

        updateCollapseAllToolbarButton();
    }

    function updateCollapseAllToolbarButton() {
        const collapseBtn = document.getElementById("collapseAllStagesBtn");
        if (!collapseBtn) return;
        const cols = document.querySelectorAll(".kanban-column");
        const allCollapsed = Array.from(cols).every(col => col.getAttribute("data-collapsed") === "true" || col.classList.contains("stage-collapsed"));
        collapseBtn.querySelector("span").textContent = allCollapsed ? "Expand All" : "Collapse All";
    }

    function sortAllStages(sortBy) {
        document.querySelectorAll(".kanban-column").forEach(col => {
            sortStageDeals(col, sortBy);
        });
    }

    function sortStageDeals(columnEl, sortBy) {
        const container = columnEl.querySelector(".kanban-cards-container");
        if (!container) return;

        const cards = Array.from(container.querySelectorAll(".kanban-card"));
        if (cards.length <= 1) return;

        cards.sort((a, b) => {
            const valA = parseFloat(a.getAttribute("data-value")) || 0;
            const valB = parseFloat(b.getAttribute("data-value")) || 0;
            const nameA = (a.getAttribute("data-name") || "").toLowerCase();
            const nameB = (b.getAttribute("data-name") || "").toLowerCase();
            const dateA = a.getAttribute("data-close-date") || "";
            const dateB = b.getAttribute("data-close-date") || "";
            const origA = parseInt(a.getAttribute("data-original-index")) || 0;
            const origB = parseInt(b.getAttribute("data-original-index")) || 0;

            switch (sortBy) {
                case "val_high": return valB - valA;
                case "val_low": return valA - valB;
                case "name_az": return nameA.localeCompare(nameB);
                case "name_za": return nameB.localeCompare(nameA);
                case "date_earliest": return (dateA || "9999").localeCompare(dateB || "9999");
                case "date_latest": return (dateB || "0000").localeCompare(dateA || "0000");
                default: return origA - origB;
            }
        });

        cards.forEach(card => container.appendChild(card));
    }

    window.openMoveToStageSubmenu = function (dealId, triggerBtn) {
        selectedMoveStageDealId = dealId;
        const card = document.querySelector(`.kanban-card[data-deal-id="${dealId}"]`);
        if (!card) return;

        const currentStageId = card.getAttribute("data-stage") || "prospect";
        const dealName = card.getAttribute("data-name") || "Deal";
        const dealCompany = card.getAttribute("data-company") || "";
        const dealValue = parseFloat(card.getAttribute("data-value")) || 0;

        const summaryEl = document.getElementById("moveStageDealSummary");
        if (summaryEl) {
            summaryEl.innerHTML = `
                <div style="background:#F8FAFC;border:1px solid var(--border-card);border-radius:var(--radius-md);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-weight:600;font-size:13.5px;color:var(--text-heading);">${escapeHtml(dealName)}</div>
                        <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">${escapeHtml(dealCompany)}</div>
                    </div>
                    <div style="font-weight:700;font-size:14px;color:var(--primary);">$${dealValue.toLocaleString()}</div>
                </div>
            `;
        }

        selectMoveStageOption(currentStageId);
        openPipelineModal("moveStageModal");
    };

    function selectMoveStageOption(stageId) {
        selectedMoveTargetStageId = stageId;
        const card = document.querySelector(`.kanban-card[data-deal-id="${selectedMoveStageDealId}"]`);
        const currentStageId = card ? card.getAttribute("data-stage") : "prospect";

        document.querySelectorAll(".move-stage-option-card").forEach(opt => {
            const optStage = opt.getAttribute("data-stage-id");
            const radio = opt.querySelector(".move-stage-radio");
            if (optStage === stageId) {
                opt.classList.add("selected");
                if (radio) radio.checked = true;
            } else {
                opt.classList.remove("selected");
                if (radio) radio.checked = false;
            }
        });

        const transitionText = document.getElementById("moveStageTransitionText");
        if (transitionText) {
            const fromLabel = getStageLabelById(currentStageId);
            const toLabel = getStageLabelById(stageId);
            transitionText.textContent = (currentStageId === stageId) ? `${fromLabel} (Current Stage)` : `${fromLabel} → ${toLabel}`;
        }

        const confirmBtn = document.getElementById("btnConfirmMoveStage");
        if (confirmBtn) {
            if (currentStageId === stageId) {
                confirmBtn.disabled = true;
                confirmBtn.textContent = "Already in this Stage";
                confirmBtn.classList.remove("btn-primary");
                confirmBtn.classList.add("btn-secondary");
            } else {
                confirmBtn.disabled = false;
                confirmBtn.textContent = "Move Deal";
                confirmBtn.classList.remove("btn-secondary");
                confirmBtn.classList.add("btn-primary");
            }
        }
    }
    window.selectMoveStageOption = selectMoveStageOption;

    window.confirmMoveDealToStage = function () {
        if (!selectedMoveStageDealId || !selectedMoveTargetStageId) return;

        const dealId = selectedMoveStageDealId;
        const targetStageId = selectedMoveTargetStageId;

        closePipelineModal("moveStageModal");

        fetch("api/pipeline.php?action=update_stage", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: dealId, stage: targetStageId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showPipelineToast(res.message || "Deal moved successfully.");
                fetchPipelineBootstrap();
                if (document.getElementById("pipelineDealDrawer")?.classList.contains("show") && activePipelineDealId == dealId) {
                    openDealDetailsDrawer(dealId);
                }
            } else {
                showPipelineToast(res.message || "Failed to move deal.");
            }
        });
    };

    function promptClosedWonModal(card) {
        pendingWonDealId = card.getAttribute("data-deal-id");
        const name = card.getAttribute("data-name") || "Deal";
        const val = parseFloat(card.getAttribute("data-value")) || 0;

        const nameEl = document.getElementById("wonModalDealName");
        const valEl = document.getElementById("wonModalDealValue");
        const dateEl = document.getElementById("wonModalCloseDate");

        if (nameEl) nameEl.textContent = name;
        if (valEl) valEl.textContent = `$${val.toLocaleString()}`;
        if (dateEl) dateEl.value = new Date().toISOString().split("T")[0];

        openPipelineModal("closedWonConfirmModal");
    }

    window.confirmClosedWonMove = function () {
        const dealId = pendingWonDealId || (pendingDropMove ? pendingDropMove.card.getAttribute("data-deal-id") : activePipelineDealId);
        const closeDate = document.getElementById("wonModalCloseDate")?.value || new Date().toISOString().split("T")[0];
        const note = document.getElementById("wonModalNote")?.value || "";

        closePipelineModal("closedWonConfirmModal");

        fetch("api/pipeline.php?action=update_stage", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: dealId, stage: "closed_won", close_date: closeDate, note: note })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showPipelineToast("Deal marked as won.");
                fetchPipelineBootstrap();
                if (document.getElementById("pipelineDealDrawer")?.classList.contains("show") && activePipelineDealId == dealId) {
                    openDealDetailsDrawer(dealId);
                }
            } else {
                showPipelineToast(res.message || "Failed to mark deal as won.");
            }
        });
    };

    window.cancelClosedWonMove = function () {
        closePipelineModal("closedWonConfirmModal");
        pendingDropMove = null;
        pendingWonDealId = null;
    };

    function promptReopenModal(card) {
        const dealId = card.getAttribute("data-deal-id");
        const name = card.getAttribute("data-name") || "this deal";
        const nameEl = document.getElementById("reopenModalDealName");
        if (nameEl) nameEl.textContent = name;
        activePipelineDealId = dealId;
        openPipelineModal("reopenDealConfirmModal");
    }

    window.confirmReopenDealMove = function () {
        const dealId = activePipelineDealId || (pendingDropMove ? pendingDropMove.card.getAttribute("data-deal-id") : null);
        const targetStage = pendingDropMove ? (pendingDropMove.targetCol.getAttribute("data-stage-id") || "proposal") : "proposal";

        closePipelineModal("reopenDealConfirmModal");

        fetch("api/pipeline.php?action=update_stage", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: dealId, stage: targetStage })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showPipelineToast("Deal reopened.");
                fetchPipelineBootstrap();
            } else {
                showPipelineToast(res.message || "Failed to reopen deal.");
            }
        });
    };

    window.cancelReopenDealMove = function () {
        closePipelineModal("reopenDealConfirmModal");
        pendingDropMove = null;
    };

    window.markDealAsLost = function (dealId) {
        const proceed = () => {
            fetch("api/pipeline.php?action=update_stage", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id: dealId, stage: "closed_lost" })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showPipelineToast("Deal marked as lost.");
                    fetchPipelineBootstrap();
                    if (document.getElementById("pipelineDealDrawer")?.classList.contains("show") && activePipelineDealId == dealId) {
                        openDealDetailsDrawer(dealId);
                    }
                } else {
                    showPipelineToast(res.message || "Failed to mark deal as lost.");
                }
            });
        };

        if (typeof window.showConfirmModal === "function") {
            window.showConfirmModal({
                title: "Mark Deal as Lost?",
                message: "Are you sure you want to mark this deal as Lost?",
                confirmText: "Mark as Lost",
                type: "danger",
                onConfirm: proceed
            });
        } else {
            proceed();
        }
    };

    let pendingDeleteDealId = null;

    window.promptDeletePipelineDeal = function (dealId) {
        const targetId = dealId || activePipelineDealId;
        if (!targetId) return;

        pendingDeleteDealId = targetId;

        let dealName = "this deal";
        if (currentDrawerData && currentDrawerData.deal && String(currentDrawerData.deal.id) === String(targetId)) {
            dealName = currentDrawerData.deal.name;
        } else {
            const card = document.querySelector(`.kanban-card[data-deal-id="${targetId}"]`);
            if (card) {
                dealName = card.getAttribute("data-name") || "this deal";
            }
        }

        const nameEl = document.getElementById("deleteDealModalName");
        if (nameEl) nameEl.textContent = dealName;

        openPipelineModal("deleteDealConfirmModal");
    };

    window.confirmDeletePipelineDeal = function () {
        if (!pendingDeleteDealId) return;

        const targetId = pendingDeleteDealId;
        closePipelineModal("deleteDealConfirmModal");
        pendingDeleteDealId = null;

        fetch("api/pipeline.php?action=delete_deal", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: targetId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showPipelineToast(res.message || "Deal deleted successfully.");
                if (document.getElementById("editDealModal")?.classList.contains("show")) {
                    closePipelineModal("editDealModal");
                }
                closePipelineDealDrawer();
                fetchPipelineBootstrap();
            } else {
                showPipelineToast(res.message || "Failed to delete deal.");
            }
        })
        .catch(err => {
            console.error("Delete deal error:", err);
            showPipelineToast("Failed to delete deal.");
        });
    };

    window.deletePipelineDeal = function (dealId) {
        promptDeletePipelineDeal(dealId);
    };

    window.duplicatePipelineDeal = function (dealId) {
        const card = document.querySelector(`.kanban-card[data-deal-id="${dealId}"]`);
        if (!card) return;

        const name = (card.getAttribute("data-name") || "Deal") + " (Copy)";
        const company = card.getAttribute("data-company") || "";
        const value = parseFloat(card.getAttribute("data-value")) || 0;
        const stage = card.getAttribute("data-stage") || "prospect";
        const prob = card.getAttribute("data-prob") || 50;
        const source = card.getAttribute("data-source") || "Direct";
        const closeDate = card.getAttribute("data-close-date") || new Date().toISOString().split("T")[0];

        fetch("api/pipeline.php?action=create_deal", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                name,
                company,
                value,
                stage,
                probability: prob,
                source,
                close_date: closeDate
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showPipelineToast("Deal duplicated successfully.");
                fetchPipelineBootstrap();
            } else {
                showPipelineToast(res.message || "Failed to duplicate deal.");
            }
        });
    };

    // ------------------------------------------
    // 3. ADD DEAL & EDIT DEAL MODAL HANDLERS
    // ------------------------------------------
    window.openAddDealModal = function (stageId) {
        const select = document.getElementById("addDealStage");
        if (select && stageId) {
            select.value = stageId;
        }
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById("addDealCloseDate");
        if (dateInput) dateInput.value = today;

        const cHidden = document.getElementById("addDealContactId");
        if (cHidden) cHidden.value = "";
        const compHidden = document.getElementById("addDealCompanyId");
        if (compHidden) compHidden.value = "";

        openPipelineModal("addDealModal");
    };

    window.openEditDealModal = function (dealId) {
        activePipelineDealId = dealId;

        // Fetch latest data for this deal
        fetch(`api/pipeline.php?action=deal_drawer&id=${dealId}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.deal) {
                    const d = res.data.deal;
                    if (document.getElementById("editDealName")) document.getElementById("editDealName").value = d.name || "";
                    if (document.getElementById("editDealContact")) {
                        document.getElementById("editDealContact").value = d.contact_name || "";
                        document.getElementById("editDealContact").dataset.contactId = d.contact_id || "";
                        if (document.getElementById("editDealContactId")) {
                            document.getElementById("editDealContactId").value = d.contact_id || "";
                        }
                    }
                    if (document.getElementById("editDealCompany")) {
                        document.getElementById("editDealCompany").value = d.company || "";
                        const allComps = (pipelineBootstrapData?.companies && pipelineBootstrapData.companies.length > 0)
                            ? pipelineBootstrapData.companies
                            : (window.pipelineInitialCompanies || []);
                        const foundComp = allComps.find(c => (c.name || "").toLowerCase() === (d.company || "").toLowerCase());
                        if (foundComp) {
                            document.getElementById("editDealCompany").dataset.companyId = foundComp.id;
                            if (document.getElementById("editDealCompanyId")) {
                                document.getElementById("editDealCompanyId").value = foundComp.id;
                            }
                        } else {
                            delete document.getElementById("editDealCompany").dataset.companyId;
                            if (document.getElementById("editDealCompanyId")) {
                                document.getElementById("editDealCompanyId").value = "";
                            }
                        }
                    }
                    if (document.getElementById("editDealValue")) document.getElementById("editDealValue").value = d.value || 0;
                    if (document.getElementById("editDealStage")) document.getElementById("editDealStage").value = d.stage || "prospect";
                    if (document.getElementById("editDealProb")) document.getElementById("editDealProb").value = d.probability !== null ? d.probability : 50;
                    if (document.getElementById("editDealCloseDate")) document.getElementById("editDealCloseDate").value = d.iso_close_date || "";
                    if (document.getElementById("editDealSource")) document.getElementById("editDealSource").value = d.source || "Website";
                    if (document.getElementById("editDealOwner")) document.getElementById("editDealOwner").value = d.assigned_to || "";
                    if (document.getElementById("editDealPriority")) document.getElementById("editDealPriority").value = d.priority || "Medium";
                    if (document.getElementById("editDealProduct")) document.getElementById("editDealProduct").value = d.product || "";
                    if (document.getElementById("editDealDesc")) document.getElementById("editDealDesc").value = d.description || "";

                    openPipelineModal("editDealModal");
                } else {
                    showPipelineToast("Could not load deal details for editing.");
                }
            });
    };

    window.openEditDealModalFromDrawer = function () {
        if (activePipelineDealId) {
            openEditDealModal(activePipelineDealId);
        }
    };

    function setupPipelineCustomAutocomplete() {
        function getContactsList() {
            return (pipelineBootstrapData?.contacts && pipelineBootstrapData.contacts.length > 0)
                ? pipelineBootstrapData.contacts
                : (window.pipelineInitialContacts || []);
        }

        function getCompaniesList() {
            const raw = (pipelineBootstrapData?.companies && pipelineBootstrapData.companies.length > 0)
                ? pipelineBootstrapData.companies
                : (window.pipelineInitialCompanies || []);
            const map = new Map();
            raw.forEach(c => {
                if (c && c.id && c.name) {
                    map.set(Number(c.id), { id: Number(c.id), name: c.name.trim() });
                }
            });
            return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
        }

        // 1. Contact Autocomplete Setup
        function initContactAutocomplete(contactInputId, contactHiddenId, dropdownId, companyInputId, companyHiddenId, isEdit) {
            const input = document.getElementById(contactInputId);
            const hidden = document.getElementById(contactHiddenId);
            const dropdown = document.getElementById(dropdownId);
            const compInput = document.getElementById(companyInputId);
            const compHidden = document.getElementById(companyHiddenId);
            if (!input || !dropdown) return;

            let activeIndex = -1;
            let currentItems = [];

            function close() {
                dropdown.style.display = "none";
                dropdown.innerHTML = "";
                activeIndex = -1;
                currentItems = [];
            }

            function updateHighlight() {
                const domItems = dropdown.querySelectorAll(".nexflow-autocomplete-item");
                domItems.forEach((el, idx) => {
                    if (idx === activeIndex) {
                        el.classList.add("active");
                        el.scrollIntoView({ block: "nearest" });
                    } else {
                        el.classList.remove("active");
                    }
                });
            }

            function selectContact(c) {
                input.value = c.name;
                input.dataset.contactId = c.id;
                if (hidden) hidden.value = c.id;

                const comps = Array.isArray(c.companies) ? c.companies : [];

                // Rule 1 & Rule 2:
                // • If selected Contact belongs to exactly ONE company:
                //   → In Add Deal: company may be auto-filled.
                //   → In Edit Deal: only auto-fill if Company field was empty; existing company must NOT be silently changed!
                // • If Contact belongs to MULTIPLE companies:
                //   → Do NOT automatically choose a company.
                //   → Preserve existing Company field.
                //   → User must explicitly select correct Company.
                if (compInput) {
                    const existingComp = compInput.value.trim();
                    if (comps.length === 1) {
                        if (!isEdit) {
                            compInput.value = comps[0].name;
                            compInput.dataset.companyId = comps[0].id;
                            if (compHidden) compHidden.value = comps[0].id;
                        } else {
                            if (!existingComp) {
                                compInput.value = comps[0].name;
                                compInput.dataset.companyId = comps[0].id;
                                if (compHidden) compHidden.value = comps[0].id;
                            }
                        }
                    }
                    // If comps.length > 1 or 0: preserve existing Company field untouched
                }

                close();
            }

            function renderDropdown(filterText) {
                const q = (filterText || "").trim().toLowerCase();
                const allContacts = getContactsList();

                // Rule 4: Phone number is a search key, not a unique identifier.
                // If multiple contacts have the same phone number, show ALL matching contacts. Never merge.
                currentItems = allContacts.filter(c => {
                    if (!q) return true;
                    if (c.name && c.name.toLowerCase().includes(q)) return true;
                    if (c.phone && c.phone.toLowerCase().includes(q)) return true;
                    if (c.email && c.email.toLowerCase().includes(q)) return true;
                    if (Array.isArray(c.companies)) {
                        for (let comp of c.companies) {
                            if (comp.name && comp.name.toLowerCase().includes(q)) return true;
                        }
                    }
                    return false;
                });

                dropdown.innerHTML = "";
                activeIndex = -1;

                if (currentItems.length === 0) {
                    const empty = document.createElement("div");
                    empty.className = "nexflow-autocomplete-empty";
                    empty.textContent = "No matching contacts found";
                    dropdown.appendChild(empty);
                    dropdown.style.display = "block";
                    return;
                }

                const toRender = currentItems.slice(0, 30);
                toRender.forEach((c, idx) => {
                    const item = document.createElement("div");
                    item.className = "nexflow-autocomplete-item";
                    item.setAttribute("data-index", idx);
                    item.setAttribute("data-id", c.id);

                    // Primary line: Contact Name
                    const primary = document.createElement("div");
                    primary.className = "item-primary";
                    primary.textContent = c.name;

                    // Secondary line: Associated Companies from contact_companies
                    const secondary = document.createElement("div");
                    secondary.className = "item-secondary";

                    const comps = Array.isArray(c.companies) ? c.companies : [];
                    let subText = "";
                    if (comps.length === 1) {
                        subText = comps[0].name;
                    } else if (comps.length > 1) {
                        subText = comps.map(x => x.name).join(", ");
                    } else {
                        subText = "No associated company";
                    }

                    if (q && c.phone && c.phone.toLowerCase().includes(q)) {
                        subText += ` • ${c.phone}`;
                    }

                    secondary.textContent = subText;
                    item.appendChild(primary);
                    item.appendChild(secondary);

                    item.addEventListener("mousedown", function (e) {
                        e.preventDefault();
                        selectContact(c);
                    });

                    dropdown.appendChild(item);
                });

                dropdown.style.display = "block";
            }

            input.addEventListener("focus", function () {
                renderDropdown(this.value);
            });

            input.addEventListener("input", function () {
                const val = this.value.trim().toLowerCase();
                const allContacts = getContactsList();
                const exact = allContacts.filter(c => c.name.toLowerCase() === val);
                if (exact.length === 1) {
                    this.dataset.contactId = exact[0].id;
                    if (hidden) hidden.value = exact[0].id;
                } else {
                    delete this.dataset.contactId;
                    if (hidden) hidden.value = "";
                }
                renderDropdown(this.value);
            });

            input.addEventListener("keydown", function (e) {
                if (dropdown.style.display === "none" || currentItems.length === 0) {
                    return;
                }
                const maxIndex = Math.min(currentItems.length, 30);
                if (e.key === "ArrowDown") {
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % maxIndex;
                    updateHighlight();
                } else if (e.key === "ArrowUp") {
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + maxIndex) % maxIndex;
                    updateHighlight();
                } else if (e.key === "Enter") {
                    if (activeIndex >= 0 && activeIndex < maxIndex) {
                        e.preventDefault();
                        e.stopPropagation();
                        selectContact(currentItems[activeIndex]);
                    }
                } else if (e.key === "Escape" || e.key === "Tab") {
                    close();
                }
            });

            document.addEventListener("mousedown", function (e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    close();
                }
            });
        }

        // 2. Company Autocomplete Setup
        function initCompanyAutocomplete(companyInputId, companyHiddenId, dropdownId) {
            const input = document.getElementById(companyInputId);
            const hidden = document.getElementById(companyHiddenId);
            const dropdown = document.getElementById(dropdownId);
            if (!input || !dropdown) return;

            let activeIndex = -1;
            let currentItems = [];

            function close() {
                dropdown.style.display = "none";
                dropdown.innerHTML = "";
                activeIndex = -1;
                currentItems = [];
            }

            function updateHighlight() {
                const domItems = dropdown.querySelectorAll(".nexflow-autocomplete-item");
                domItems.forEach((el, idx) => {
                    if (idx === activeIndex) {
                        el.classList.add("active");
                        el.scrollIntoView({ block: "nearest" });
                    } else {
                        el.classList.remove("active");
                    }
                });
            }

            function selectCompany(comp) {
                input.value = comp.name;
                input.dataset.companyId = comp.id;
                if (hidden) hidden.value = comp.id;
                close();
            }

            function renderDropdown(filterText) {
                const q = (filterText || "").trim().toLowerCase();
                const allCompanies = getCompaniesList();

                currentItems = allCompanies.filter(comp => {
                    if (!q) return true;
                    return comp.name.toLowerCase().includes(q);
                });

                dropdown.innerHTML = "";
                activeIndex = -1;

                if (currentItems.length === 0) {
                    const empty = document.createElement("div");
                    empty.className = "nexflow-autocomplete-empty";
                    empty.textContent = "No matching companies found";
                    dropdown.appendChild(empty);
                    dropdown.style.display = "block";
                    return;
                }

                const toRender = currentItems.slice(0, 30);
                toRender.forEach((comp, idx) => {
                    const item = document.createElement("div");
                    item.className = "nexflow-autocomplete-item single-line";
                    item.setAttribute("data-index", idx);
                    item.setAttribute("data-id", comp.id);

                    const primary = document.createElement("div");
                    primary.className = "item-primary";
                    primary.textContent = comp.name;
                    item.appendChild(primary);

                    item.addEventListener("mousedown", function (e) {
                        e.preventDefault();
                        selectCompany(comp);
                    });

                    dropdown.appendChild(item);
                });

                dropdown.style.display = "block";
            }

            input.addEventListener("focus", function () {
                renderDropdown(this.value);
            });

            input.addEventListener("input", function () {
                const val = this.value.trim().toLowerCase();
                const allCompanies = getCompaniesList();
                const exact = allCompanies.filter(c => c.name.toLowerCase() === val);
                if (exact.length === 1) {
                    this.dataset.companyId = exact[0].id;
                    if (hidden) hidden.value = exact[0].id;
                } else {
                    delete this.dataset.companyId;
                    if (hidden) hidden.value = "";
                }
                renderDropdown(this.value);
            });

            input.addEventListener("keydown", function (e) {
                if (dropdown.style.display === "none" || currentItems.length === 0) {
                    return;
                }
                const maxIndex = Math.min(currentItems.length, 30);
                if (e.key === "ArrowDown") {
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % maxIndex;
                    updateHighlight();
                } else if (e.key === "ArrowUp") {
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + maxIndex) % maxIndex;
                    updateHighlight();
                } else if (e.key === "Enter") {
                    if (activeIndex >= 0 && activeIndex < maxIndex) {
                        e.preventDefault();
                        e.stopPropagation();
                        selectCompany(currentItems[activeIndex]);
                    }
                } else if (e.key === "Escape" || e.key === "Tab") {
                    close();
                }
            });

            document.addEventListener("mousedown", function (e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    close();
                }
            });
        }

        // Initialize Add Deal Autocomplete
        initContactAutocomplete("addDealContact", "addDealContactId", "addDealContactDropdown", "addDealCompany", "addDealCompanyId", false);
        initCompanyAutocomplete("addDealCompany", "addDealCompanyId", "addDealCompanyDropdown");

        // Initialize Edit Deal Autocomplete
        initContactAutocomplete("editDealContact", "editDealContactId", "editDealContactDropdown", "editDealCompany", "editDealCompanyId", true);
        initCompanyAutocomplete("editDealCompany", "editDealCompanyId", "editDealCompanyDropdown");
    }

    function setupPipelineModalForms() {
        setupPipelineCustomAutocomplete();

        // Add Deal Form Submit
        const addForm = document.getElementById("addDealForm");
        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                const name = document.getElementById("addDealName").value.trim();
                const contactInput = document.getElementById("addDealContact");
                const contactHidden = document.getElementById("addDealContactId");
                const contact = contactInput?.value.trim() || "";
                let contactId = contactHidden?.value ? parseInt(contactHidden.value) : (contactInput?.dataset.contactId ? parseInt(contactInput.dataset.contactId) : null);
                if (!contactId && contact) {
                    const allContacts = (pipelineBootstrapData?.contacts || window.pipelineInitialContacts || []);
                    const matched = allContacts.find(c => c.name.toLowerCase() === contact.toLowerCase());
                    if (matched) contactId = matched.id;
                }

                const companyInput = document.getElementById("addDealCompany");
                const companyHidden = document.getElementById("addDealCompanyId");
                const company = companyInput?.value.trim() || "";
                let companyId = companyHidden?.value ? parseInt(companyHidden.value) : (companyInput?.dataset.companyId ? parseInt(companyInput.dataset.companyId) : null);
                if (!companyId && company) {
                    const allComps = (pipelineBootstrapData?.companies || window.pipelineInitialCompanies || []);
                    const matched = allComps.find(c => c.name.toLowerCase() === company.toLowerCase());
                    if (matched) companyId = matched.id;
                }

                const value = parseFloat(document.getElementById("addDealValue")?.value) || 0;
                const stage = document.getElementById("addDealStage")?.value || "prospect";
                const prob = document.getElementById("addDealProb")?.value || 50;
                const closeDate = document.getElementById("addDealCloseDate")?.value || "";
                const source = document.getElementById("addDealSource")?.value || "";
                const owner = document.getElementById("addDealOwner")?.value || "";
                const priority = document.getElementById("addDealPriority")?.value || "Medium";
                const product = document.getElementById("addDealProduct")?.value.trim() || "";
                const desc = document.getElementById("addDealDesc")?.value.trim() || "";

                fetch("api/pipeline.php?action=create_deal", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        name,
                        contact_id: contactId,
                        contact_name: contact,
                        company,
                        company_id: companyId,
                        value,
                        stage,
                        probability: prob,
                        close_date: closeDate,
                        source,
                        assigned_to: owner,
                        priority,
                        product,
                        description: desc
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closePipelineModal("addDealModal");
                        addForm.reset();
                        if (contactHidden) contactHidden.value = "";
                        if (companyHidden) companyHidden.value = "";
                        delete contactInput.dataset.contactId;
                        delete companyInput.dataset.companyId;
                        showPipelineToast("Deal created successfully.");
                        fetchPipelineBootstrap();
                    } else {
                        showPipelineToast(res.message || "Failed to create deal.");
                    }
                })
                .catch(err => {
                    console.error("Create deal error:", err);
                    showPipelineToast("Network error creating deal.");
                });
            });
        }

        // Edit Deal Form Submit
        const editForm = document.getElementById("editDealForm");
        if (editForm) {
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                if (!activePipelineDealId) return;

                const name = document.getElementById("editDealName").value.trim();
                const contactInput = document.getElementById("editDealContact");
                const contactHidden = document.getElementById("editDealContactId");
                const contact = contactInput?.value.trim() || "";
                let contactId = contactHidden?.value ? parseInt(contactHidden.value) : (contactInput?.dataset.contactId ? parseInt(contactInput.dataset.contactId) : null);
                if (!contactId && contact) {
                    const allContacts = (pipelineBootstrapData?.contacts || window.pipelineInitialContacts || []);
                    const matched = allContacts.find(c => c.name.toLowerCase() === contact.toLowerCase());
                    if (matched) contactId = matched.id;
                }

                const companyInput = document.getElementById("editDealCompany");
                const companyHidden = document.getElementById("editDealCompanyId");
                const company = companyInput?.value.trim() || "";
                let companyId = companyHidden?.value ? parseInt(companyHidden.value) : (companyInput?.dataset.companyId ? parseInt(companyInput.dataset.companyId) : null);
                if (!companyId && company) {
                    const allComps = (pipelineBootstrapData?.companies || window.pipelineInitialCompanies || []);
                    const matched = allComps.find(c => c.name.toLowerCase() === company.toLowerCase());
                    if (matched) companyId = matched.id;
                }

                const value = parseFloat(document.getElementById("editDealValue")?.value) || 0;
                const stage = document.getElementById("editDealStage")?.value || "prospect";
                const prob = document.getElementById("editDealProb")?.value || 50;
                const closeDate = document.getElementById("editDealCloseDate")?.value || "";
                const source = document.getElementById("editDealSource")?.value || "";
                const owner = document.getElementById("editDealOwner")?.value || "";
                const priority = document.getElementById("editDealPriority")?.value || "Medium";
                const product = document.getElementById("editDealProduct")?.value.trim() || "";
                const desc = document.getElementById("editDealDesc")?.value.trim() || "";

                fetch("api/pipeline.php?action=update_deal", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        id: activePipelineDealId,
                        name,
                        contact_id: contactId,
                        contact_name: contact,
                        company,
                        company_id: companyId,
                        value,
                        stage,
                        probability: prob,
                        close_date: closeDate,
                        source,
                        assigned_to: owner,
                        priority,
                        product,
                        description: desc
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closePipelineModal("editDealModal");
                        showPipelineToast("Deal updated successfully.");
                        fetchPipelineBootstrap();
                        if (document.getElementById("pipelineDealDrawer")?.classList.contains("show")) {
                            openDealDetailsDrawer(activePipelineDealId);
                        }
                    } else {
                        showPipelineToast(res.message || "Failed to update deal.");
                    }
                })
                .catch(err => {
                    console.error("Update deal error:", err);
                    showPipelineToast("Network error updating deal.");
                });
            });
        }

        // Task Form Submit
        const taskForm = document.getElementById("addPipelineTaskForm");
        if (taskForm) {
            taskForm.addEventListener("submit", function (e) {
                e.preventDefault();
                executeSavePipelineTask(e);
            });
        }

        // Note Form Submit
        const noteForm = document.getElementById("addPipelineNoteForm");
        if (noteForm) {
            noteForm.addEventListener("submit", function (e) {
                e.preventDefault();
                executeSavePipelineNote(e);
            });
        }

        // Log Activity Form Submit
        const actForm = document.getElementById("logDealActivityForm");
        if (actForm) {
            actForm.addEventListener("submit", function (e) {
                e.preventDefault();
                executeLogDealActivity(e);
            });
        }
    }

    // ------------------------------------------
    // 4. BOOTSTRAP DATA & BOARD SYNC
    // ------------------------------------------
    function fetchPipelineBootstrap() {
        fetch("api/pipeline.php?action=bootstrap")
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    pipelineBootstrapData = res.data;
                    syncPipelineUI(res.data);
                }
            })
            .catch(err => {
                console.error("Pipeline bootstrap fetch failed:", err);
            });
    }
    window.fetchPipelineBootstrap = fetchPipelineBootstrap;

    function syncPipelineUI(data) {
        // 1. Update 5 KPI summary cards
        const kpis = data.kpis || {};
        const totalDealsEl = document.getElementById("globalTotalDeals");
        const totalDealsBadge = document.getElementById("globalTotalDealsBadge");
        const pipelineValEl = document.getElementById("globalTotalValue");
        const openDealsEl = document.getElementById("globalOpenDeals");
        const wonValueEl = document.getElementById("globalWonValue");
        const weightedValEl = document.getElementById("globalWeightedValue");

        if (totalDealsEl) totalDealsEl.textContent = kpis.total_deals || 0;
        if (totalDealsBadge) totalDealsBadge.textContent = `${kpis.total_deals || 0} deals`;
        if (pipelineValEl) {
            const pv = kpis.pipeline_value || 0;
            pipelineValEl.textContent = pv >= 1000 ? ('$' + (pv / 1000).toFixed(1) + 'K') : ('$' + pv.toLocaleString());
        }
        if (openDealsEl) openDealsEl.textContent = kpis.open_deals || 0;
        if (wonValueEl) {
            const wv = kpis.won_value || 0;
            wonValueEl.textContent = wv >= 1000 ? ('$' + (wv / 1000).toFixed(1) + 'K') : ('$' + wv.toLocaleString());
        }
        if (weightedValEl) {
            const wtv = kpis.weighted_value || 0;
            weightedValEl.textContent = wtv >= 1000 ? ('$' + (wtv / 1000).toFixed(1) + 'K') : ('$' + wtv.toLocaleString());
        }

        // 2. Populate board columns if stages exist
        const board = document.getElementById("kanbanBoard");
        if (!board) return;

        const stages = data.stages || [];
        if (stages.length === 0) {
            board.innerHTML = `
                <div style="text-align: center; width: 100%; padding: 60px 20px; color: var(--text-muted);">
                    <div style="font-size: 32px; margin-bottom: 8px;">📊</div>
                    <div style="font-weight: 600; font-size: 16px; color: var(--text-heading);">No pipeline stages configured</div>
                    <div style="font-size: 13px; margin-top: 4px;">Please configure pipeline stages in Settings.</div>
                </div>
            `;
            return;
        }

        // Sync each stage column
        stages.forEach(stg => {
            let col = board.querySelector(`.kanban-column[data-stage-id="${stg.id}"]`);
            if (!col) return;

            const container = col.querySelector(".kanban-cards-container");
            if (!container) return;

            container.innerHTML = "";
            const deals = stg.deals || [];

            deals.forEach((deal, idx) => {
                const card = document.createElement("div");
                card.className = "kanban-card";
                card.draggable = true;
                card.setAttribute("data-deal-id", deal.id);
                card.setAttribute("data-original-index", idx);
                card.setAttribute("data-value", deal.value);
                card.setAttribute("data-name", deal.name);
                card.setAttribute("data-contact", deal.contact_name || "");
                card.setAttribute("data-company", deal.company);
                card.setAttribute("data-source", deal.source);
                card.setAttribute("data-assignee", deal.initials);
                card.setAttribute("data-stage", stg.id);
                card.setAttribute("data-prob", deal.probability);
                card.setAttribute("data-close-date", deal.iso_close_date);
                card.setAttribute("data-close-date-text", deal.close_date);
                card.setAttribute("tabindex", "0");
                card.setAttribute("onclick", `openDealDetailsDrawer('${deal.id}')`);

                card.innerHTML = `
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px;">
                        <div style="flex: 1; min-width: 0; padding-right: 6px;">
                            <div class="kanban-card-title">${escapeHtml(deal.name)}</div>
                            <div class="kanban-card-company">${escapeHtml(deal.company)}</div>
                        </div>
                    </div>
                    <div class="card-sub-meta">
                        <span>📅 ${escapeHtml(deal.close_date || 'No date')}</span>
                        <span class="card-prob-badge">${deal.probability}%</span>
                    </div>
                    <div class="kanban-card-footer" style="margin-top: 8px;">
                        <div class="kanban-card-value">$${Number(deal.value).toLocaleString()}</div>
                        <div class="kanban-card-meta">
                            <span class="kanban-source-tag">${escapeHtml(deal.source || 'Direct')}</span>
                            <div class="avatar avatar-xs" style="background-color: ${deal.avatar_color};" title="${escapeHtml(deal.owner_name)}">
                                ${escapeHtml(deal.initials)}
                            </div>
                        </div>
                    </div>
                `;

                window.attachDragEventsToCard(card);
                container.appendChild(card);
            });

            recalculateColumnStats(col);
        });
    }

    // ------------------------------------------
    // 5. DEAL DETAILS DRAWER & TABS
    // ------------------------------------------
    window.openDealDetailsDrawer = function (dealId) {
        activePipelineDealId = dealId;
        const drawer = document.getElementById("pipelineDealDrawer");
        if (drawer) drawer.classList.add("show");
        document.body.style.overflow = "hidden";

        // Show loading state in drawer body
        const body = document.getElementById("pipelineDrawerBody");
        if (body) {
            body.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <div class="spinner-border spinner-border-sm" role="status" style="margin-bottom: 8px;"></div>
                    <div>Loading deal details…</div>
                </div>
            `;
        }

        fetch(`api/pipeline.php?action=deal_drawer&id=${dealId}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    currentDrawerData = res.data;
                    const deal = currentDrawerData.deal;
                    const breadcrumb = document.getElementById("dealDrawerBreadcrumb");
                    const title = document.getElementById("dealDrawerTitle");
                    if (breadcrumb) breadcrumb.textContent = `Sales Pipeline / ${deal.name}`;
                    if (title) title.textContent = "Deal Details";

                    activePipelineDrawerTab = "overview";
                    updatePipelineTabHighlight("overview");
                    renderPipelineDrawerTab();
                } else {
                    showPipelineToast(res.message || "Failed to load deal details.");
                    closePipelineDealDrawer();
                }
            })
            .catch(err => {
                console.error("Deal drawer load error:", err);
                showPipelineToast("Failed to load deal details.");
            });
    };

    window.closePipelineDealDrawer = function () {
        const drawer = document.getElementById("pipelineDealDrawer");
        if (drawer) drawer.classList.remove("show");
        document.body.style.overflow = "";
    };

    window.switchPipelineDrawerTab = function (tabName) {
        activePipelineDrawerTab = tabName;
        updatePipelineTabHighlight(tabName);
        renderPipelineDrawerTab();
    };

    function updatePipelineTabHighlight(tabName) {
        const tabs = document.querySelectorAll("#pipelineDealDrawer .drawer-tab");
        tabs.forEach(t => {
            const isCurrent = (t.getAttribute("data-tab") === tabName);
            if (isCurrent) {
                t.classList.add("active");
                t.setAttribute("aria-selected", "true");
            } else {
                t.classList.remove("active");
                t.setAttribute("aria-selected", "false");
            }
        });
    }

    function renderPipelineDrawerTab() {
        const body = document.getElementById("pipelineDrawerBody");
        if (!body || !currentDrawerData) return;

        const deal = currentDrawerData.deal;
        const weightedVal = Math.round(deal.value * (deal.probability || 0) / 100);
        const stageLabel = deal.stage_name || getStageLabelById(deal.stage);

        let html = "";

        if (activePipelineDrawerTab === "overview") {
            html = `
                <!-- 1. Deal Identity Card -->
                <div class="deal-identity-card">
                    <div class="deal-identity-main">
                        <div class="avatar avatar-lg deal-avatar" style="background-color: #7C3AED;">
                            ${escapeHtml(deal.initials)}
                        </div>
                        <div class="deal-identity-meta">
                            <div class="deal-title-row">
                                <h4 class="deal-contact-name">${escapeHtml(deal.name)}</h4>
                            </div>
                            <div class="deal-company-row">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                                <span>${escapeHtml(deal.company || 'No Company')}</span>
                            </div>
                            <div class="deal-secondary-meta">
                                <span>Owner: <strong>${escapeHtml(deal.owner_name || 'Unassigned')}</strong></span>
                                <span class="meta-dot">•</span>
                                <span>Source: <strong>${escapeHtml(deal.source || 'Direct')}</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="deal-identity-side">
                        <span class="status-badge status-${deal.stage.replace('_','')}">
                            <span class="status-dot"></span>${escapeHtml(stageLabel)}
                        </span>
                        <span class="deal-close-tag">Close: ${escapeHtml(deal.close_date || 'No Date')}</span>
                    </div>
                </div>

                <!-- 2. KPI Summary Cards (2x2 Grid) -->
                <div class="deal-kpi-summary-grid">
                    <div class="kpi-card-item">
                        <span class="kpi-label">Deal Value</span>
                        <div class="kpi-value">$${Number(deal.value).toLocaleString()}</div>
                    </div>
                    <div class="kpi-card-item">
                        <span class="kpi-label">Weighted Value</span>
                        <div class="kpi-value text-primary">$${Number(weightedVal).toLocaleString()}</div>
                    </div>
                    <div class="kpi-card-item">
                        <div class="kpi-label-row">
                            <span class="kpi-label">Probability</span>
                            <span class="kpi-badge-prob">${deal.probability}%</span>
                        </div>
                        <div class="kpi-progress-bar">
                            <div class="kpi-progress-fill" style="width: ${deal.probability}%;"></div>
                        </div>
                    </div>
                    <div class="kpi-card-item">
                        <span class="kpi-label">Expected Close Date</span>
                        <div class="kpi-value-date">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>${escapeHtml(deal.close_date || 'Not set')}</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Pipeline Stage Progress Tracker -->
                <div class="drawer-info-card">
                    <div class="drawer-card-title">Pipeline Stage Progress</div>
                    <div class="pipeline-stage-progress-bar">
                        ${(pipelineBootstrapData.stages || []).map((s, idx) => {
                            const stagesArr = (pipelineBootstrapData.stages || []).map(x => x.id);
                            const currentIdx = stagesArr.indexOf(deal.stage);
                            let stateClass = "upcoming";
                            if (idx < currentIdx) stateClass = "completed";
                            if (idx === currentIdx) stateClass = "current";

                            return `
                                <div class="pipeline-stage-step ${stateClass}">
                                    <span class="pipeline-stage-dot">${idx < currentIdx ? '✓' : idx + 1}</span>
                                    <span class="pipeline-stage-name">${escapeHtml(s.label)}</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>

                <!-- 4. Deal Information Card -->
                <div class="drawer-info-card">
                    <div class="drawer-card-title">Deal Information</div>
                    <div class="drawer-info-grid">
                        <div class="drawer-info-row"><span class="drawer-info-label">Deal Code</span><div class="drawer-info-val">${escapeHtml(deal.deal_code)}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Deal Name</span><div class="drawer-info-val">${escapeHtml(deal.name)}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Company</span><div class="drawer-info-val">${escapeHtml(deal.company || 'N/A')}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Associated Contact</span><div class="drawer-info-val">${escapeHtml(deal.contact_name || 'N/A')}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Lead Source</span><div class="drawer-info-val">${escapeHtml(deal.source || 'N/A')}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Owner</span><div class="drawer-info-val">${escapeHtml(deal.owner_name || 'Unassigned')}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Product or Service</span><div class="drawer-info-val">${escapeHtml(deal.product || 'N/A')}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Priority</span><div class="drawer-info-val">${escapeHtml(deal.priority || 'Medium')}</div></div>
                        <div class="drawer-info-row"><span class="drawer-info-label">Probability</span><div class="drawer-info-val">${deal.probability}%</div></div>
                    </div>
                </div>

                <!-- 5. Description -->
                <div class="drawer-info-card" style="margin-bottom:0;">
                    <div class="drawer-card-title">Description</div>
                    <p class="drawer-description-text">
                        ${escapeHtml(deal.description || 'No description provided for this deal.')}
                    </p>
                </div>
            `;
        } else if (activePipelineDrawerTab === "activity") {
            html = renderPipelineActivityTabHtml();
        } else if (activePipelineDrawerTab === "tasks") {
            html = renderPipelineTasksTabHtml();
        } else if (activePipelineDrawerTab === "notes") {
            html = renderPipelineNotesTabHtml();
        }

        body.innerHTML = html;
    }

    // ------------------------------------------
    // 6. NOTES SYSTEM (REAL MYSQL)
    // ------------------------------------------
    function renderPipelineNotesTabHtml() {
        const notes = currentDrawerData?.notes || [];
        const search = activePipelineNoteSearch.toLowerCase().trim();

        const filtered = notes.filter(n => {
            if (!search) return true;
            return (n.content || "").toLowerCase().includes(search) || (n.title || "").toLowerCase().includes(search);
        });

        return `
            <div class="task-tab-header">
                <div>
                    <h4 class="activity-tab-title" style="margin:0;">NOTES</h4>
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Keep track of meeting notes, call logs & internal context.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddPipelineNoteModal()">+ Add Note</button>
            </div>

            <div class="activity-search-input-wrap" style="margin-bottom: 16px; width: 100%;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="pipelineNoteSearchInput" class="input-control input-sm activity-search-input" placeholder="Search notes…" value="${escapeHtml(activePipelineNoteSearch)}" oninput="setPipelineNoteSearch(this.value)">
            </div>

            <div id="pipelineNotesContainer">
                ${filtered.length === 0 ? `
                    <div style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                        <div style="font-size: 24px; margin-bottom: 6px;">📝</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-heading);">No notes found</div>
                        <div style="font-size: 12px; margin-top: 4px;">${search ? 'Try a different search term.' : 'Click "+ Add Note" to create a note for this deal.'}</div>
                    </div>
                ` : filtered.map(n => `
                    <div class="note-card-item ${n.is_pinned ? 'is-pinned' : ''}" style="border: 1px solid var(--border-card); border-radius: 8px; padding: 14px; margin-bottom: 10px; background: #FFF;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="status-badge" style="background:#EFF6FF;color:#2563EB;font-size:11px;padding:2px 8px;border-radius:12px;">${escapeHtml(n.title || 'General')}</span>
                                <span style="font-size: 12px; color: var(--text-muted);">${escapeHtml(n.created_at || '')}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-ghost btn-xs" style="color:${n.is_pinned ? '#F59E0B' : 'var(--text-muted)'};" onclick="togglePipelineNotePin(${n.id})" title="${n.is_pinned ? 'Unpin note' : 'Pin note'}">
                                    📌
                                </button>
                                <button type="button" class="btn btn-ghost btn-xs" style="color: #EF4444;" onclick="promptDeletePipelineNote(${n.id})" title="Delete note">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                        <div style="font-size: 13px; color: var(--text-body); line-height: 1.5; white-space: pre-wrap;">${escapeHtml(n.content)}</div>
                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 8px;">By ${escapeHtml(n.author_name || 'Admin')}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    window.setPipelineNoteSearch = function (val) {
        activePipelineNoteSearch = val;
        renderPipelineDrawerTab();
    };

    window.openAddPipelineNoteModal = function () {
        const dealName = currentDrawerData?.deal?.name || "Deal";
        const contextEl = document.getElementById("pipelineNoteDealContext");
        if (contextEl) contextEl.value = dealName;
        const contentEl = document.getElementById("pipelineNoteContent");
        if (contentEl) contentEl.value = "";

        openPipelineModal("addPipelineNoteModal");
    };

    function executeSavePipelineNote(e) {
        e.preventDefault();
        const content = document.getElementById("pipelineNoteContent")?.value.trim();
        const type = document.getElementById("pipelineNoteType")?.value || "General";
        if (!activePipelineDealId || !content) return;

        fetch("api/pipeline.php?action=create_note", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ deal_id: activePipelineDealId, content, type })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closePipelineModal("addPipelineNoteModal");
                showPipelineToast("Note saved to deal.");
                openDealDetailsDrawer(activePipelineDealId);
            } else {
                showPipelineToast(res.message || "Failed to save note.");
            }
        });
    }

    window.togglePipelineNotePin = function (noteId) {
        fetch("api/pipeline.php?action=toggle_note_pin", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ note_id: noteId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                openDealDetailsDrawer(activePipelineDealId);
            }
        });
    };

    window.promptDeletePipelineNote = function (noteId) {
        pendingDeleteNoteId = noteId;
        openPipelineModal("deletePipelineNoteConfirmModal");
    };

    window.confirmDeletePipelineNote = function () {
        if (!pendingDeleteNoteId) return;

        fetch("api/pipeline.php?action=delete_note", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ note_id: pendingDeleteNoteId })
        })
        .then(r => r.json())
        .then(res => {
            closePipelineModal("deletePipelineNoteConfirmModal");
            pendingDeleteNoteId = null;
            if (res.success) {
                showPipelineToast("Note deleted.");
                openDealDetailsDrawer(activePipelineDealId);
            } else {
                showPipelineToast(res.message || "Failed to delete note.");
            }
        });
    };

    // ------------------------------------------
    // 7. ACTIVITIES SYSTEM (REAL MYSQL)
    // ------------------------------------------
    function renderPipelineActivityTabHtml() {
        const activities = currentDrawerData?.activities || [];
        const search = activePipelineActivitySearch.toLowerCase().trim();

        const filtered = activities.filter(a => {
            if (activePipelineActivityFilter !== "All" && a.activity_type !== activePipelineActivityFilter) return false;
            if (search) {
                return (a.title || "").toLowerCase().includes(search) || (a.description || "").toLowerCase().includes(search);
            }
            return true;
        });

        return `
            <div class="task-tab-header">
                <div>
                    <h4 class="activity-tab-title" style="margin:0;">ACTIVITY TIMELINE</h4>
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Calls, meetings, emails and pipeline stage transitions.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openLogDealActivityModal()">+ Log Activity</button>
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                <div class="activity-search-input-wrap" style="flex: 1;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm activity-search-input" placeholder="Search activities…" value="${escapeHtml(activePipelineActivitySearch)}" oninput="setPipelineActivitySearch(this.value)">
                </div>
                <select class="input-control input-sm" style="width: 140px;" onchange="setPipelineActivityFilter(this.value)">
                    <option value="All" ${activePipelineActivityFilter === 'All' ? 'selected' : ''}>All Types</option>
                    <option value="Call" ${activePipelineActivityFilter === 'Call' ? 'selected' : ''}>Call</option>
                    <option value="Email" ${activePipelineActivityFilter === 'Email' ? 'selected' : ''}>Email</option>
                    <option value="Meeting" ${activePipelineActivityFilter === 'Meeting' ? 'selected' : ''}>Meeting</option>
                    <option value="Stage Change" ${activePipelineActivityFilter === 'Stage Change' ? 'selected' : ''}>Stage Change</option>
                    <option value="Note" ${activePipelineActivityFilter === 'Note' ? 'selected' : ''}>Note</option>
                </select>
            </div>

            <div id="pipelineActivitiesContainer">
                ${filtered.length === 0 ? `
                    <div style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                        <div style="font-size: 24px; margin-bottom: 6px;">🕒</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-heading);">No activities logged yet</div>
                        <div style="font-size: 12px; margin-top: 4px;">Click "+ Log Activity" to record a call, note, or update.</div>
                    </div>
                ` : filtered.map(a => `
                    <div class="activity-timeline-item" style="display: flex; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border-card);">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;">
                            ${getActivityIcon(a.activity_type)}
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-weight: 600; font-size: 13.5px; color: var(--text-heading);">${escapeHtml(a.title)}</span>
                                <span style="font-size: 11.5px; color: var(--text-muted);">${escapeHtml(a.created_at || '')}</span>
                            </div>
                            ${a.description ? `<div style="font-size: 12.5px; color: var(--text-body); margin-top: 4px; line-height: 1.4;">${escapeHtml(a.description)}</div>` : ''}
                            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">Logged by ${escapeHtml(a.author_name || 'Admin')}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function getActivityIcon(type) {
        switch (type) {
            case "Call": return "📞";
            case "Email": return "✉️";
            case "Meeting": return "📅";
            case "Stage Change": return "🔄";
            case "Deal Created": return "✨";
            default: return "📝";
        }
    }

    window.setPipelineActivitySearch = function (val) {
        activePipelineActivitySearch = val;
        renderPipelineDrawerTab();
    };

    window.setPipelineActivityFilter = function (val) {
        activePipelineActivityFilter = val;
        renderPipelineDrawerTab();
    };

    window.openLogDealActivityModal = function () {
        const today = new Date().toISOString().split("T")[0];
        const dateEl = document.getElementById("logActivityDate");
        if (dateEl) dateEl.value = today;

        const timeEl = document.getElementById("logActivityTime");
        if (timeEl && !timeEl.value) {
            const now = new Date();
            timeEl.value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        }

        // Reset Performed By to currently authenticated user
        const userSelect = document.getElementById("logActivityUser");
        const currentUid = pipelineBootstrapData?.current_user?.id || window.pipelineInitialCurrentUser?.id;
        if (userSelect && currentUid) {
            userSelect.value = String(currentUid);
        }

        // Clear title and description for new activity
        const titleEl = document.getElementById("logActivityTitleInput");
        if (titleEl) titleEl.value = "";
        const descEl = document.getElementById("logActivityDesc");
        if (descEl) descEl.value = "";

        openPipelineModal("logDealActivityModal");
    };

    function executeLogDealActivity(e) {
        e.preventDefault();
        const type = document.getElementById("logActivityType")?.value || "Note";
        const title = document.getElementById("logActivityTitleInput")?.value.trim();
        const desc = document.getElementById("logActivityDesc")?.value.trim() || "";
        const userEl = document.getElementById("logActivityUser");
        const performedBy = userEl ? parseInt(userEl.value) : null;
        const date = document.getElementById("logActivityDate")?.value || "";
        const time = document.getElementById("logActivityTime")?.value || "";

        if (!activePipelineDealId || !title) return;

        fetch("api/pipeline.php?action=log_activity", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                deal_id: activePipelineDealId,
                type,
                title,
                description: desc,
                performed_by: performedBy,
                user_id: performedBy,
                date,
                time
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closePipelineModal("logDealActivityModal");
                showPipelineToast("Activity logged successfully.");
                openDealDetailsDrawer(activePipelineDealId);
            } else {
                showPipelineToast(res.message || "Failed to log activity.");
            }
        });
    }

    // ------------------------------------------
    // 7b. SCHEDULE DEAL MEETING (CALENDAR)
    // ------------------------------------------
    window.openScheduleDealMeetingModal = function () {
        if (!currentDrawerData || !currentDrawerData.deal) return;
        const deal = currentDrawerData.deal;

        const dealIdEl = document.getElementById("dealMeetingDealId");
        if (dealIdEl) dealIdEl.value = deal.id;

        const titleEl = document.getElementById("dealMeetingTitle");
        if (titleEl) titleEl.value = `Meeting: ${deal.name}`;

        const today = new Date();
        const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        const dateEl = document.getElementById("dealMeetingDate");
        if (dateEl) {
            dateEl.value = (deal.iso_close_date && deal.iso_close_date >= todayStr) ? deal.iso_close_date : todayStr;
        }

        const startEl = document.getElementById("dealMeetingStartTime");
        if (startEl) startEl.value = "10:00";

        const endEl = document.getElementById("dealMeetingEndTime");
        if (endEl) endEl.value = "10:30";

        const userSelect = document.getElementById("dealMeetingUser");
        if (userSelect) {
            if (deal.assigned_to) {
                userSelect.value = String(deal.assigned_to);
            } else if (window.pipelineInitialCurrentUser?.id) {
                userSelect.value = String(window.pipelineInitialCurrentUser.id);
            }
        }

        const contactDisp = document.getElementById("dealMeetingContactDisplay");
        if (contactDisp) contactDisp.value = deal.contact_name || "None";
        const contactIdEl = document.getElementById("dealMeetingContactId");
        if (contactIdEl) contactIdEl.value = deal.contact_id || "";

        const compDisp = document.getElementById("dealMeetingCompanyDisplay");
        if (compDisp) compDisp.value = deal.company || "None";
        const compIdEl = document.getElementById("dealMeetingCompanyId");
        if (compIdEl) compIdEl.value = deal.company_id || "";

        const locEl = document.getElementById("dealMeetingLocation");
        if (locEl) locEl.value = "";

        const descEl = document.getElementById("dealMeetingDesc");
        if (descEl) descEl.value = "";

        openPipelineModal("scheduleDealMeetingModal");
    };

    window.executeScheduleDealMeeting = function (e) {
        if (e) e.preventDefault();
        const dealId = document.getElementById("dealMeetingDealId")?.value;
        const title = document.getElementById("dealMeetingTitle")?.value.trim();
        const date = document.getElementById("dealMeetingDate")?.value;
        const startTime = document.getElementById("dealMeetingStartTime")?.value;
        const endTime = document.getElementById("dealMeetingEndTime")?.value;
        const userEl = document.getElementById("dealMeetingUser");
        const userId = userEl ? parseInt(userEl.value, 10) : null;
        const contactIdEl = document.getElementById("dealMeetingContactId");
        const contactId = contactIdEl && contactIdEl.value ? parseInt(contactIdEl.value, 10) : null;
        const companyIdEl = document.getElementById("dealMeetingCompanyId");
        const companyId = companyIdEl && companyIdEl.value ? parseInt(companyIdEl.value, 10) : null;
        const location = document.getElementById("dealMeetingLocation")?.value.trim() || "";
        const desc = document.getElementById("dealMeetingDesc")?.value.trim() || "";

        if (!dealId || !title || !date) {
            showPipelineToast("Please enter meeting title and date.");
            return;
        }

        if (!startTime || !endTime) {
            showPipelineToast("Start time and end time are required.");
            return;
        }

        // Strict validation: End Time > Start Time
        const sMin = parseInt(startTime.split(':')[0], 10) * 60 + parseInt(startTime.split(':')[1], 10);
        const eMin = parseInt(endTime.split(':')[0], 10) * 60 + parseInt(endTime.split(':')[1], 10);
        if (eMin <= sMin) {
            showPipelineToast("End time must be later than start time.");
            return;
        }

        const submitBtn = document.getElementById("btnSubmitDealMeeting");
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Scheduling...";
        }

        fetch("api/pipeline.php?action=create_meeting", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                deal_id: parseInt(dealId, 10),
                title: title,
                date: date,
                start_time: startTime,
                end_time: endTime,
                user_id: userId,
                contact_id: contactId,
                company_id: companyId,
                location: location,
                description: desc
            })
        })
        .then(r => r.json())
        .then(res => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = "Schedule Meeting";
            }
            if (res.success) {
                closePipelineModal("scheduleDealMeetingModal");
                showPipelineToast("Meeting scheduled successfully.");
                if (activePipelineDealId) {
                    openDealDetailsDrawer(activePipelineDealId);
                }
            } else {
                showPipelineToast(res.message || "Failed to schedule meeting.");
            }
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = "Schedule Meeting";
            }
            console.error("Schedule meeting error:", err);
            showPipelineToast("Network error while scheduling meeting.");
        });
    };

    // ------------------------------------------
    // 8. TASKS SYSTEM (REAL MYSQL)
    // ------------------------------------------
    function renderPipelineTasksTabHtml() {
        const tasks = currentDrawerData?.tasks || [];
        const search = activePipelineTaskSearch.toLowerCase().trim();

        const filtered = tasks.filter(t => {
            if (activePipelineTaskFilter === "open" && t.status === "completed") return false;
            if (activePipelineTaskFilter === "completed" && t.status !== "completed") return false;
            if (search) {
                return (t.title || "").toLowerCase().includes(search) || (t.description || "").toLowerCase().includes(search);
            }
            return true;
        });

        return `
            <div class="task-tab-header">
                <div>
                    <h4 class="activity-tab-title" style="margin:0;">TASKS</h4>
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Upcoming follow-ups and action items for this deal.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddPipelineTaskModal()">+ Add Task</button>
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                <div class="activity-search-input-wrap" style="flex: 1;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm activity-search-input" placeholder="Search tasks…" value="${escapeHtml(activePipelineTaskSearch)}" oninput="setPipelineTaskSearch(this.value)">
                </div>
                <select class="input-control input-sm" style="width: 130px;" onchange="setPipelineTaskFilter(this.value)">
                    <option value="All" ${activePipelineTaskFilter === 'All' ? 'selected' : ''}>All Tasks</option>
                    <option value="open" ${activePipelineTaskFilter === 'open' ? 'selected' : ''}>Open</option>
                    <option value="completed" ${activePipelineTaskFilter === 'completed' ? 'selected' : ''}>Completed</option>
                </select>
            </div>

            <div id="pipelineTasksContainer">
                ${filtered.length === 0 ? `
                    <div style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                        <div style="font-size: 24px; margin-bottom: 6px;">📋</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-heading);">No tasks found</div>
                        <div style="font-size: 12px; margin-top: 4px;">Click "+ Add Task" to create a task for this deal.</div>
                    </div>
                ` : filtered.map(t => {
                    const isDone = t.status === "completed";
                    return `
                        <div class="task-card-item" style="border: 1px solid var(--border-card); border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #FFF; display: flex; align-items: flex-start; gap: 10px;">
                            <input type="checkbox" ${isDone ? 'checked' : ''} style="margin-top: 3px; cursor: pointer;" onchange="togglePipelineTaskComplete(${t.id}, this.checked)">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 600; font-size: 13.5px; color: ${isDone ? 'var(--text-muted)' : 'var(--text-heading)'}; text-decoration: ${isDone ? 'line-through' : 'none'};">
                                    ${escapeHtml(t.title)}
                                </div>
                                ${t.description ? `<div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">${escapeHtml(t.description)}</div>` : ''}
                                <div style="display: flex; align-items: center; gap: 10px; margin-top: 6px; font-size: 11.5px; color: var(--text-muted);">
                                    <span>📅 Due: ${escapeHtml(t.due_date ? t.due_date.split(' ')[0] : 'No date')}</span>
                                    <span class="status-badge" style="text-transform: capitalize; padding: 1px 6px; font-size: 10.5px;">${escapeHtml(t.priority || 'Medium')}</span>
                                    ${t.assignee_name ? `<span>Assignee: ${escapeHtml(t.assignee_name)}</span>` : ''}
                                </div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-xs" style="color: #EF4444;" onclick="promptDeletePipelineTask(${t.id})" title="Delete task">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    window.setPipelineTaskSearch = function (val) {
        activePipelineTaskSearch = val;
        renderPipelineDrawerTab();
    };

    window.setPipelineTaskFilter = function (val) {
        activePipelineTaskFilter = val;
        renderPipelineDrawerTab();
    };

    window.openAddPipelineTaskModal = function () {
        const dealName = currentDrawerData?.deal?.name || "Deal";
        const contextEl = document.getElementById("pipelineTaskDealContext");
        if (contextEl) contextEl.value = dealName;
        const today = new Date().toISOString().split("T")[0];
        const dateEl = document.getElementById("pipelineTaskDate");
        if (dateEl) dateEl.value = today;

        openPipelineModal("addPipelineTaskModal");
    };

    function executeSavePipelineTask(e) {
        e.preventDefault();
        const title = document.getElementById("pipelineTaskTitle")?.value.trim();
        const desc = document.getElementById("pipelineTaskDesc")?.value.trim() || "";
        const dueDate = document.getElementById("pipelineTaskDate")?.value || "";
        const dueTime = document.getElementById("pipelineTaskTime")?.value || "";
        const priority = document.getElementById("pipelineTaskPriority")?.value || "Medium";
        const assignee = document.getElementById("pipelineTaskAssignee")?.value || "";

        if (!activePipelineDealId || !title) return;

        fetch("api/pipeline.php?action=create_task", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                deal_id: activePipelineDealId,
                title,
                description: desc,
                due_date: dueDate,
                due_time: dueTime,
                priority,
                assigned_to: assignee
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closePipelineModal("addPipelineTaskModal");
                showPipelineToast("Task added to deal.");
                openDealDetailsDrawer(activePipelineDealId);
            } else {
                showPipelineToast(res.message || "Failed to add task.");
            }
        });
    }

    window.togglePipelineTaskComplete = function (taskId, isChecked) {
        fetch("api/pipeline.php?action=update_task_status", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ task_id: taskId, completed: isChecked })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showPipelineToast(isChecked ? "Task marked complete." : "Task reopened.");
                openDealDetailsDrawer(activePipelineDealId);
            }
        });
    };

    window.promptDeletePipelineTask = function (taskId) {
        pendingDeleteTaskId = taskId;
        openPipelineModal("deletePipelineTaskConfirmModal");
    };

    window.confirmDeletePipelineTask = function () {
        if (!pendingDeleteTaskId) return;

        fetch("api/pipeline.php?action=delete_task", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ task_id: pendingDeleteTaskId })
        })
        .then(r => r.json())
        .then(res => {
            closePipelineModal("deletePipelineTaskConfirmModal");
            pendingDeleteTaskId = null;
            if (res.success) {
                showPipelineToast("Task deleted.");
                openDealDetailsDrawer(activePipelineDealId);
            } else {
                showPipelineToast(res.message || "Failed to delete task.");
            }
        });
    };

    // ------------------------------------------
    // 9. FILTER PANEL POPOVER
    // ------------------------------------------
    window.togglePipelineFilterPanel = function (e) {
        e.stopPropagation();
        const popover = document.getElementById("pipelineFilterPopover");
        if (popover) {
            popover.classList.toggle("show");
        }
    };

    window.closePipelineFilterPanel = function () {
        const popover = document.getElementById("pipelineFilterPopover");
        if (popover) popover.classList.remove("show");
    };

    window.applyPipelineFilters = function () {
        applySearchAndFilters();
        closePipelineFilterPanel();

        // Update filter badge
        let activeCount = 0;
        if (document.getElementById("filterStageSelect")?.value !== "All") activeCount++;
        if (document.getElementById("filterOwnerSelect")?.value !== "All") activeCount++;
        if (document.getElementById("filterSourceSelect")?.value !== "All") activeCount++;
        if (parseFloat(document.getElementById("filterMinValue")?.value) > 0) activeCount++;

        const badge = document.getElementById("activeFilterBadge");
        if (badge) {
            if (activeCount > 0) {
                badge.style.display = "inline-block";
                badge.textContent = activeCount;
            } else {
                badge.style.display = "none";
            }
        }
    };

    window.clearPipelineFilters = function () {
        if (document.getElementById("filterStageSelect")) document.getElementById("filterStageSelect").value = "All";
        if (document.getElementById("filterOwnerSelect")) document.getElementById("filterOwnerSelect").value = "All";
        if (document.getElementById("filterSourceSelect")) document.getElementById("filterSourceSelect").value = "All";
        if (document.getElementById("filterMinValue")) document.getElementById("filterMinValue").value = "";

        applyPipelineFilters();
    };

    // ------------------------------------------
    // 10. MODAL UTILITIES & TOASTS
    // ------------------------------------------
    window.openPipelineModal = function (modalId) {
        if (activePipelineModalId && activePipelineModalId !== modalId) {
            closePipelineModal(activePipelineModalId);
        }

        const modal = document.getElementById(modalId);
        if (!modal) return;

        activePipelineModalId = modalId;
        modal.classList.add("show", "is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    };

    window.closePipelineModal = function (modalId) {
        const modal = document.getElementById(modalId || activePipelineModalId);
        if (modal) {
            modal.classList.remove("show", "is-open");
            modal.setAttribute("aria-hidden", "true");
        }
        if (modalId === activePipelineModalId || !modalId) {
            activePipelineModalId = null;
        }

        // Hide any open autocomplete dropdowns
        document.querySelectorAll(".nexflow-autocomplete-dropdown").forEach(el => {
            el.style.display = "none";
            el.innerHTML = "";
        });

        const drawer = document.getElementById("pipelineDealDrawer");
        if (drawer && drawer.classList.contains("show")) {
            document.body.style.overflow = "hidden";
        } else {
            document.body.style.overflow = "";
        }
    };

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            if (activePipelineModalId) {
                e.preventDefault();
                closePipelineModal(activePipelineModalId);
            } else if (document.getElementById("pipelineDealDrawer")?.classList.contains("show")) {
                e.preventDefault();
                closePipelineDealDrawer();
            }
        }
    });

    window.showPipelineToast = function (message) {
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
    };

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function getStageLabelById(id) {
        if (!id) return "";
        const stages = pipelineBootstrapData?.stages || [];
        const found = stages.find(s => s.id === id);
        if (found) return found.label;

        if (id === "prospect") return "Prospect";
        if (id === "qualified") return "Qualified";
        if (id === "proposal") return "Proposal";
        if (id === "negotiation") return "Negotiation";
        if (id === "closed_won" || id === "won") return "Closed Won";
        if (id === "closed_lost" || id === "lost") return "Closed Lost";
        return id;
    }
    window.getStageLabelById = getStageLabelById;
});
