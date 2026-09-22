/**
 * NexFlow CRM — Client Portal Estimates Controller
 * Production controller connected to api/client-estimates.php.
 * Handles live search, status filtering, sorting, drawers, and modal workflows.
 * Zero mock data or client-side storage dependencies.
 */

(function () {
    "use strict";

    let cachedEstimates = [];
    let activeEstimate = null;
    let searchDebounceTimer = null;

    function getCsrfToken() {
        if (window.clientPortalCsrf) return window.clientPortalCsrf;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") : "";
    }

    function getStatusBadgeClass(status) {
        switch (status) {
            case "Accepted":
            case "Converted": return "green";
            case "Pending Review":
            case "Quotation Issued":
            case "Approved":
            case "New": return "blue";
            case "In Review":
            case "More Info Needed": return "amber";
            case "Declined":
            case "Rejected":
            case "Expired": return "red";
            default: return "amber";
        }
    }

    
    function formatTimeline(value) {
        if (value === null || value === undefined) {
            return "Not specified";
        }

        if (Array.isArray(value)) {
            if (value.length === 0) return "Not specified";
            const parts = [];
            for (const item of value) {
                if (item && typeof item === "object") {
                    if (item.text || item.activity_type) {
                        continue;
                    }
                    const label = item.label || item.phase || item.name || item.title || "";
                    const duration = item.duration || item.days || item.value || item.time || "";
                    if (label && duration) {
                        parts.push(`${label}: ${duration}`);
                    } else if (duration) {
                        parts.push(String(duration));
                    } else if (label) {
                        parts.push(String(label));
                    }
                } else if (item !== null && item !== undefined) {
                    const s = String(item).trim();
                    if (s && s !== "[object Object]" && s !== "undefined" && s !== "null") {
                        parts.push(s);
                    }
                }
            }
            return parts.length > 0 ? parts.join(" • ") : "Not specified";
        }

        if (typeof value === "object") {
            const parts = [];
            for (const [k, v] of Object.entries(value)) {
                if (v && typeof v === "object") {
                    const sub = formatTimeline(v);
                    if (sub !== "Not specified") parts.push(`${k}: ${sub}`);
                } else if (v !== null && v !== undefined) {
                    const s = String(v).trim();
                    if (s && s !== "[object Object]" && s !== "undefined" && s !== "null") {
                        parts.push(`${k}: ${s}`);
                    }
                }
            }
            return parts.length > 0 ? parts.join(" • ") : "Not specified";
        }

        const str = String(value).trim();
        if (!str || str === "[object Object]" || str === "undefined" || str === "null") {
            return "Not specified";
        }

        if (str.startsWith("[") || str.startsWith("{")) {
            try {
                const parsed = JSON.parse(str);
                return formatTimeline(parsed);
            } catch (e) {}
        }

        return str;
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

    // Refresh KPI Stat Cards from server
    function refreshKPIs() {
        fetch("api/client-estimates.php?action=summary")
            .then(res => res.json())
            .then(json => {
                if (json.success && json.data && json.data.kpi) {
                    const k = json.data.kpi;
                    const totalEl = document.getElementById("kpiTotalEstimates");
                    const pendingEl = document.getElementById("kpiPendingEstimates");
                    const acceptedEl = document.getElementById("kpiAcceptedEstimates");
                    const expiredEl = document.getElementById("kpiExpiredEstimates");

                    if (totalEl) totalEl.textContent = k.total;
                    if (pendingEl) pendingEl.textContent = k.pending;
                    if (acceptedEl) acceptedEl.textContent = k.accepted;
                    if (expiredEl) expiredEl.textContent = k.declined;

                    // Update sidebar badge
                    const navChip = document.getElementById("navEstimatesCount");
                    if (navChip) {
                        navChip.textContent = k.total;
                    }
                }
            })
            .catch(err => console.error("Error refreshing estimate KPIs:", err));
    }

    // Render Estimates Table via live API
    window.renderClientEstimates = function () {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(fetchEstimates, 150);
    };

    function fetchEstimates() {
        const query = (document.getElementById("estimatesSearchInput")?.value || "").trim();
        const statusFilter = document.getElementById("estimatesStatusFilter")?.value || "All";
        const sortBy = document.getElementById("estimatesSortSelect")?.value || "newest";

        const url = `api/client-estimates.php?action=list&q=${encodeURIComponent(query)}&status=${encodeURIComponent(statusFilter)}&sort=${encodeURIComponent(sortBy)}`;

        fetch(url)
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    console.error("Failed to load estimates:", json.message);
                    return;
                }
                cachedEstimates = json.data?.items || [];
                renderTable(cachedEstimates);
            })
            .catch(err => console.error("Error fetching estimates:", err));
    }

    function renderTable(estimates) {
        const tbody = document.getElementById("clientEstimatesTbody");
        const emptyState = document.getElementById("clientEstimatesEmptyState");
        const tableCard = document.getElementById("clientEstimatesTableCard");
        if (!tbody) return;

        if (!estimates || estimates.length === 0) {
            if (tableCard) tableCard.style.display = "none";
            if (emptyState) emptyState.style.display = "block";
            tbody.innerHTML = "";
            return;
        }

        if (emptyState) emptyState.style.display = "none";
        if (tableCard) tableCard.style.display = "block";

        tbody.innerHTML = estimates.map(e => {
            const status = e.display_status || e.status;
            const statusClass = e.badge_class || getStatusBadgeClass(status);
            const isActionable = e.is_actionable;
            const eid = e.id;

            return `
                <tr>
                    <td>
                        <span class="client-estimate-id">${escapeHtml(e.request_code)}</span>
                        <div style="font-weight:600;color:#0F172A;font-size:13.5px;margin-top:2px;">${escapeHtml(e.subject)}</div>
                        <div style="font-size:11.5px;color:#64748B;">
                            ${e.project_name ? 'Project: ' + escapeHtml(e.project_name) + ' • ' : ''}
                            ${e.deal_name ? 'Deal: ' + escapeHtml(e.deal_name) : ''}
                        </div>
                    </td>
                    <td style="font-weight:700;color:#0F172A;font-size:14px;">${escapeHtml(e.formatted_amount)}</td>
                    <td>
                        <span class="client-portal-badge ${statusClass}">${escapeHtml(status)}</span>
                    </td>
                    <td style="font-size:12.5px;color:#334155;">${escapeHtml(e.issue_date || '')}</td>
                    <td style="font-size:12.5px;color:#334155;">${escapeHtml(e.expiry_date || '')}</td>
                    <td style="text-align:right;">
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientEstimates.openEstimateDrawer(${eid}, this)">
                                View Estimate
                            </button>
                            ${isActionable ? `
                                <button type="button" class="btn btn-primary btn-xs" onclick="window.clientEstimates.promptAccept(${eid})">
                                    Accept
                                </button>
                                <button type="button" class="btn btn-secondary btn-xs" style="color:#B91C1C;" onclick="window.clientEstimates.promptDecline(${eid})">
                                    Decline
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join("");
    }

    // Public Controller
    window.clientEstimates = {
        activeEstimateId: null,
        activeTab: "overview",
        lastFocusedBtn: null,

        openEstimateDrawer: function (estimateId, triggerBtn) {
            this.lastFocusedBtn = triggerBtn || document.activeElement;
            this.activeEstimateId = estimateId;
            this.activeTab = "overview";

            fetch(`api/client-estimates.php?action=get&id=${encodeURIComponent(estimateId)}`)
                .then(res => res.json())
                .then(json => {
                    if (!json.success || !json.data) {
                        alert(json.message || "Unable to load estimate details.");
                        return;
                    }

                    activeEstimate = json.data;

                    const drawer = document.getElementById("clientEstimateDrawer");
                    const title = document.getElementById("cedEstimateTitle");
                    const badge = document.getElementById("cedEstimateStatusBadge");

                    const curStatus = activeEstimate.display_status || activeEstimate.status;
                    const statusClass = activeEstimate.badge_class || getStatusBadgeClass(curStatus);

                    if (title) title.textContent = `${activeEstimate.subject} (${activeEstimate.request_code})`;
                    if (badge) {
                        badge.textContent = curStatus;
                        badge.className = "client-portal-badge " + statusClass;
                    }

                    window.clientEstimates.switchEstimateDrawerTab("overview");

                    if (drawer) {
                        drawer.style.display = "block";
                        drawer.classList.add("show");
                        document.body.style.overflow = "hidden";
                        const firstTab = drawer.querySelector(".client-portal-drawer-tab");
                        if (firstTab) firstTab.focus();
                    }
                })
                .catch(err => console.error("Error loading estimate details:", err));
        },

        closeEstimateDrawer: function () {
            const drawer = document.getElementById("clientEstimateDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.style.display = "none";
                document.body.style.overflow = "";
            }
            if (this.lastFocusedBtn && typeof this.lastFocusedBtn.focus === "function") {
                this.lastFocusedBtn.focus();
            }
        },

        switchEstimateDrawerTab: function (tabName) {
            this.activeTab = tabName;
            document.querySelectorAll("#clientEstimateDrawer .client-portal-drawer-tab").forEach(tab => {
                const isActive = tab.getAttribute("data-tab") === tabName;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            const body = document.getElementById("clientEstimateDrawerBody");
            if (!body || !activeEstimate) return;

            const e = activeEstimate;
            const curStatus = e.display_status || e.status;
            const isActionable = e.is_actionable;

            if (tabName === "overview") {
                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">ESTIMATE AMOUNT</div>
                            <div style="font-size:18px;font-weight:700;color:#0F172A;margin-top:2px;">${escapeHtml(e.formatted_amount)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">TIMELINE / DELIVERY</div>
                            <div style="font-size:14px;font-weight:700;color:#0F172A;margin-top:4px;">${escapeHtml(formatTimeline(e.delivery_timeline || e.formatted_timeline || e.timeline))}</div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Quotation Summary</h4>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;">${escapeHtml(e.description || 'No description provided.')}</p>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">RELATED PROJECT</h4>
                            <div style="font-size:13px;color:#0F172A;font-weight:600;">${escapeHtml(e.project_name || 'None')}</div>
                        </div>
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">RELATED DEAL</h4>
                            <div style="font-size:13px;color:#0F172A;font-weight:600;">${escapeHtml(e.deal_name || 'None')}</div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">CLIENT</h4>
                            <div style="font-size:13px;color:#334155;">${escapeHtml(e.company_name || 'Client Workspace')}</div>
                        </div>
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">PREPARED BY</h4>
                            <div style="font-size:13px;color:#334155;">${escapeHtml(e.owner_name || 'Account Lead')}</div>
                        </div>
                    </div>

                    ${isActionable ? `
                        <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:14px 16px;margin-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#1E40AF;">Quotation Pending Approval</div>
                                <div style="font-size:12px;color:#1E3A8A;">Review the quoted scope and rates and accept or decline below.</div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientEstimates.promptAccept(${e.id})">Accept Estimate</button>
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#B91C1C;" onclick="window.clientEstimates.promptDecline(${e.id})">Decline</button>
                            </div>
                        </div>
                    ` : ''}
                `;
            } else if (tabName === "items") {
                // Display real pricing and services without fabricating fake line items
                body.innerHTML = `
                    <div style="border:1px solid #E2E8F0;border-radius:8px;padding:16px;margin-bottom:16px;background:#FFFFFF;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:8px;">Requested Services &amp; Quoted Scope</h4>
                        <div style="font-size:13px;color:#334155;line-height:1.6;margin-bottom:14px;">
                            <strong>Services:</strong> ${escapeHtml(e.services || 'General Services')}
                        </div>
                        ${e.requirements ? `
                            <div style="font-size:12.5px;color:#475569;line-height:1.55;background:#F8FAFC;padding:12px;border-radius:6px;border:1px solid #E2E8F0;">
                                <strong>Requirements:</strong><br>${escapeHtml(e.requirements)}
                            </div>
                        ` : ''}
                    </div>

                    <div class="client-estimate-pricing-box">
                        <div class="client-estimate-summary-row total">
                            <span>Quoted Amount / Estimated Budget</span>
                            <span style="color:#2563EB;">${escapeHtml(e.formatted_amount)}</span>
                        </div>
                        <div style="font-size:11.5px;color:#64748B;margin-top:8px;">
                            Timeline: ${escapeHtml(formatTimeline(e.delivery_timeline || e.formatted_timeline || e.timeline))} • Preferred Start: ${escapeHtml(e.preferred_start_date || 'TBD')}
                        </div>
                    </div>
                `;
            } else if (tabName === "terms") {
                // Show real requirements or clean empty state without inventing fake legal terms
                body.innerHTML = `
                    <div class="client-estimate-terms-box">
                        <div style="font-weight:700;font-size:13px;margin-bottom:6px;color:#0F172A;">Terms &amp; Conditions</div>
                        <p style="margin:0;line-height:1.5;color:#475569;font-size:13px;">
                            ${e.requirements ? escapeHtml(e.requirements) : 'Standard pricing terms apply upon quotation acceptance. Any revisions outside the agreed services may be requoted.'}
                        </p>
                    </div>
                `;
            } else if (tabName === "timeline") {
                const timeline = e.activities || e.activity_history || e.timelineLog || (Array.isArray(e.timeline) ? e.timeline : []);
                body.innerHTML = `
                    <div class="client-estimate-timeline">
                        ${timeline.length > 0 ? timeline.map(t => `
                            <div class="client-estimate-timeline-item">
                                <div class="client-estimate-timeline-dot"></div>
                                <div>
                                    <div style="font-weight:600;color:#0F172A;font-size:12.5px;">${escapeHtml(t.text || t.title)}</div>
                                    <div style="font-size:11px;color:#64748B;">${escapeHtml(t.date || '')}</div>
                                </div>
                            </div>
                        `).join("") : `
                            <div style="color:#64748B;font-size:12.5px;padding:12px 0;">No activity history recorded yet.</div>
                        `}
                    </div>
                `;
            }
        },

        promptAccept: function (estimateId) {
            const e = activeEstimate && activeEstimate.id === estimateId 
                ? activeEstimate 
                : cachedEstimates.find(item => item.id === estimateId);
            if (!e) return;

            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Accept Estimate</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="client-portal-modal-body">
                    <p style="font-size:13.5px;color:#334155;margin:0 0 12px 0;">Are you sure you want to accept estimate <strong>${escapeHtml(e.subject)}</strong> (${escapeHtml(e.request_code)}) for <strong>${escapeHtml(e.formatted_amount)}</strong>?</p>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px;font-size:12.5px;color:#64748B;">
                        Accepting this estimate confirms approval of the quoted rates and scope. Your account lead (${escapeHtml(e.owner_name || 'Account Lead')}) will proceed with kickoff.
                    </div>
                </div>
                <div class="client-portal-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnConfirmAcceptEst" onclick="window.clientEstimates.confirmAccept(${estimateId})">Accept Estimate</button>
                </div>
            `;
            if (window.clientPortal && typeof window.clientPortal.openModal === "function") {
                window.clientPortal.openModal(html);
            }
        },

        confirmAccept: function (estimateId) {
            const btn = document.getElementById("btnConfirmAcceptEst");
            if (btn) {
                btn.disabled = true;
                btn.textContent = "Accepting...";
            }

            fetch("api/client-estimates.php?action=accept", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": getCsrfToken()
                },
                body: `id=${encodeURIComponent(estimateId)}&csrf_token=${encodeURIComponent(getCsrfToken())}`
            })
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    alert(json.message || "Failed to accept estimate.");
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = "Accept Estimate";
                    }
                    return;
                }

                if (window.clientPortal && typeof window.clientPortal.closeModal === "function") {
                    window.clientPortal.closeModal();
                }
                if (window.clientPortal && typeof window.clientPortal.showToast === "function") {
                    window.clientPortal.showToast(json.message, "success");
                }

                refreshKPIs();
                fetchEstimates();

                if (activeEstimate && activeEstimate.id === estimateId) {
                    activeEstimate.status = "Converted";
                    activeEstimate.display_status = "Accepted";
                    activeEstimate.is_actionable = false;
                    const badge = document.getElementById("cedEstimateStatusBadge");
                    if (badge) {
                        badge.textContent = "Accepted";
                        badge.className = "client-portal-badge green";
                    }
                    window.clientEstimates.switchEstimateDrawerTab(window.clientEstimates.activeTab || "overview");
                }
            })
            .catch(err => {
                console.error("Error accepting estimate:", err);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Accept Estimate";
                }
            });
        },

        promptDecline: function (estimateId) {
            const e = activeEstimate && activeEstimate.id === estimateId 
                ? activeEstimate 
                : cachedEstimates.find(item => item.id === estimateId);
            if (!e) return;

            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Decline Estimate</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="client-portal-modal-body">
                    <p style="font-size:13.5px;color:#334155;margin:0 0 12px 0;">Are you sure you want to decline estimate <strong>${escapeHtml(e.subject)}</strong> (${escapeHtml(e.request_code)})?</p>
                    <div style="margin-bottom:8px;">
                        <label class="client-login-label">Reason for declining (optional)</label>
                        <textarea class="input-control" id="declineEstimateReasonInput" rows="3" style="height:70px;resize:none;" placeholder="e.g. Rate revision required or scope adjustments needed..."></textarea>
                    </div>
                </div>
                <div class="client-portal-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnConfirmDeclineEst" style="background-color:#DC2626;border-color:#DC2626;" onclick="window.clientEstimates.confirmDecline(${estimateId})">Decline Estimate</button>
                </div>
            `;
            if (window.clientPortal && typeof window.clientPortal.openModal === "function") {
                window.clientPortal.openModal(html);
            }
        },

        confirmDecline: function (estimateId) {
            const reason = (document.getElementById("declineEstimateReasonInput")?.value || "").trim();
            const btn = document.getElementById("btnConfirmDeclineEst");
            if (btn) {
                btn.disabled = true;
                btn.textContent = "Declining...";
            }

            fetch("api/client-estimates.php?action=decline", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": getCsrfToken()
                },
                body: `id=${encodeURIComponent(estimateId)}&reason=${encodeURIComponent(reason)}&csrf_token=${encodeURIComponent(getCsrfToken())}`
            })
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    alert(json.message || "Failed to decline estimate.");
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = "Decline Estimate";
                    }
                    return;
                }

                if (window.clientPortal && typeof window.clientPortal.closeModal === "function") {
                    window.clientPortal.closeModal();
                }
                if (window.clientPortal && typeof window.clientPortal.showToast === "function") {
                    window.clientPortal.showToast(json.message, "info");
                }

                refreshKPIs();
                fetchEstimates();

                if (activeEstimate && activeEstimate.id === estimateId) {
                    activeEstimate.status = "Rejected";
                    activeEstimate.display_status = "Declined";
                    activeEstimate.is_actionable = false;
                    const badge = document.getElementById("cedEstimateStatusBadge");
                    if (badge) {
                        badge.textContent = "Declined";
                        badge.className = "client-portal-badge red";
                    }
                    window.clientEstimates.switchEstimateDrawerTab(window.clientEstimates.activeTab || "overview");
                }
            })
            .catch(err => {
                console.error("Error declining estimate:", err);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Decline Estimate";
                }
            });
        }
    };

    // Keyboard Escape handling
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            const modal = document.getElementById("clientModalOverlay");
            if (modal && (modal.classList.contains("show") || modal.style.display === "flex")) {
                window.clientPortal.closeModal();
                e.stopPropagation();
                return;
            }
            const drawer = document.getElementById("clientEstimateDrawer");
            if (drawer && (drawer.classList.contains("show") || drawer.style.display === "block")) {
                window.clientEstimates.closeEstimateDrawer();
                e.stopPropagation();
            }
        }
    });

    // Auto-init on page load
    document.addEventListener("DOMContentLoaded", function () {
        refreshKPIs();
        fetchEstimates();
    });
})();