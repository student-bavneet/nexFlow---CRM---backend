/**
 * NexFlow CRM — Client Portal Proposals Controller
 * Production controller connected to api/client-proposals.php.
 * Handles live search, status filtering, sorting, drawers, and modal workflows.
 * Zero mock data or localStorage dependencies.
 */

(function () {
    "use strict";

    let cachedProposals = [];
    let activeProposal = null;
    let searchDebounceTimer = null;

    function getCsrfToken() {
        if (window.clientPortalCsrf) return window.clientPortalCsrf;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") : "";
    }

    function getStatusBadgeClass(status) {
        switch (status) {
            case "Accepted": return "green";
            case "Pending":
            case "Viewed":
            case "Sent": return "blue";
            case "Changes Requested": return "amber";
            case "Draft": return "gray";
            case "Declined":
            case "Expired": return "red";
            default: return "amber";
        }
    }

    function formatCurrency(amount, sym) {
        sym = sym || "$";
        return sym + Number(amount || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Refresh KPI Stat Cards from server
    function refreshKPIs() {
        fetch("api/client-proposals.php?action=summary")
            .then(res => res.json())
            .then(json => {
                if (json.success && json.data && json.data.kpi) {
                    const k = json.data.kpi;
                    const totalEl = document.getElementById("kpiTotalProposals");
                    const pendingEl = document.getElementById("kpiPendingProposals");
                    const acceptedEl = document.getElementById("kpiAcceptedProposals");
                    const declinedEl = document.getElementById("kpiDeclinedProposals");

                    if (totalEl) totalEl.textContent = k.total;
                    if (pendingEl) pendingEl.textContent = k.pending;
                    if (acceptedEl) acceptedEl.textContent = k.accepted;
                    if (declinedEl) declinedEl.textContent = k.declined;

                    // Update sidebar badge if present
                    const navChip = document.getElementById("navProposalsCount");
                    if (navChip) {
                        navChip.textContent = k.total;
                    }
                }
            })
            .catch(err => console.error("Error refreshing proposal KPIs:", err));
    }

    // Render Proposals Table & List via live API
    window.renderClientProposals = function () {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(fetchProposals, 150);
    };

    function fetchProposals() {
        const query = (document.getElementById("proposalsSearchInput")?.value || "").trim();
        const statusFilter = document.getElementById("proposalsStatusFilter")?.value || "All";
        const sortBy = document.getElementById("proposalsSortSelect")?.value || "newest";

        const url = `api/client-proposals.php?action=list&q=${encodeURIComponent(query)}&status=${encodeURIComponent(statusFilter)}&sort=${encodeURIComponent(sortBy)}`;

        fetch(url)
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    console.error("Failed to load proposals:", json.message);
                    return;
                }
                cachedProposals = json.data?.items || [];
                renderTable(cachedProposals);
            })
            .catch(err => console.error("Error fetching proposals:", err));
    }

    function renderTable(proposals) {
        const tbody = document.getElementById("clientProposalsTbody");
        const emptyState = document.getElementById("clientProposalsEmptyState");
        const tableCard = document.getElementById("clientProposalsTableCard");
        if (!tbody) return;

        if (!proposals || proposals.length === 0) {
            if (tableCard) tableCard.style.display = "none";
            if (emptyState) emptyState.style.display = "block";
            tbody.innerHTML = "";
            return;
        }

        if (emptyState) emptyState.style.display = "none";
        if (tableCard) tableCard.style.display = "block";

        tbody.innerHTML = proposals.map(p => {
            const status = p.display_status || p.status;
            const statusClass = getStatusBadgeClass(status);
            const isActionable = p.is_actionable;
            const pid = p.id;

            return `
                <tr>
                    <td class="cell-proposal-info">
                        <span class="client-proposal-id">${escapeHtml(p.proposal_number)}</span>
                        <div style="font-weight:600;color:#0F172A;font-size:13.5px;margin-top:2px;">${escapeHtml(p.title)}</div>
                        <div style="font-size:11.5px;color:#64748B;">
                            ${p.project_name ? 'Project: ' + escapeHtml(p.project_name) + ' • ' : ''}
                            ${p.deal_name ? 'Deal: ' + escapeHtml(p.deal_name) : ''}
                        </div>
                    </td>
                    <td class="cell-amount" style="font-weight:700;color:#0F172A;font-size:14px;">${escapeHtml(p.formatted_total)}</td>
                    <td class="cell-status">
                        <span class="client-portal-badge ${statusClass}">${escapeHtml(status)}</span>
                    </td>
                    <td class="cell-issue-date" style="font-size:12.5px;color:#334155;">${escapeHtml(p.issue_date || '')}</td>
                    <td class="cell-expiry-date" style="font-size:12.5px;color:#334155;">${escapeHtml(p.expiry_date || '')}</td>
                    <td class="cell-actions" style="text-align:right;">
                        <div class="proposal-actions">
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientProposals.openProposalDrawer(${pid}, this)">
                                View Proposal
                            </button>
                            ${isActionable ? `
                                <button type="button" class="btn btn-primary btn-xs" onclick="window.clientProposals.promptAccept(${pid})">
                                    Accept
                                </button>
                                <button type="button" class="btn btn-secondary btn-xs btn-request-changes" onclick="window.clientProposals.promptRequestChanges(${pid})">
                                    Request Changes
                                </button>
                                <button type="button" class="btn btn-secondary btn-xs btn-decline" onclick="window.clientProposals.promptDecline(${pid})">
                                    Decline
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join("");
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

    // Public Controller
    window.clientProposals = {
        activeProposalId: null,
        activeTab: "overview",
        lastFocusedBtn: null,

        openProposalDrawer: function (proposalId, triggerBtn) {
            this.lastFocusedBtn = triggerBtn || document.activeElement;
            this.activeProposalId = proposalId;
            this.activeTab = "overview";

            fetch(`api/client-proposals.php?action=get&id=${encodeURIComponent(proposalId)}`)
                .then(res => res.json())
                .then(json => {
                    if (!json.success || !json.data) {
                        alert(json.message || "Unable to load proposal details.");
                        return;
                    }

                    activeProposal = json.data;

                    // If status was Sent, trigger auto-mark viewed
                    if (activeProposal.status === "Sent") {
                        fetch("api/client-proposals.php?action=mark_viewed", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                                "X-CSRF-Token": getCsrfToken()
                            },
                            body: `id=${encodeURIComponent(proposalId)}&csrf_token=${encodeURIComponent(getCsrfToken())}`
                        }).then(r => r.json()).then(res => {
                            if (res.success) {
                                activeProposal.status = "Viewed";
                                activeProposal.display_status = "Viewed";
                                refreshKPIs();
                                fetchProposals();
                            }
                        }).catch(e => console.error(e));
                    }

                    const drawer = document.getElementById("clientProposalDrawer");
                    const title = document.getElementById("cpdProposalTitle");
                    const badge = document.getElementById("cpdProposalStatusBadge");

                    const curStatus = activeProposal.display_status || activeProposal.status;
                    if (title) title.textContent = `${activeProposal.title} (${activeProposal.proposal_number})`;
                    if (badge) {
                        badge.textContent = curStatus;
                        badge.className = "client-portal-badge " + getStatusBadgeClass(curStatus);
                    }

                    window.clientProposals.switchProposalDrawerTab("overview");

                    if (drawer) {
                        drawer.style.display = "block";
                        drawer.classList.add("show");
                        document.body.style.overflow = "hidden";
                        const firstTab = drawer.querySelector(".client-portal-drawer-tab");
                        if (firstTab) firstTab.focus();
                    }
                })
                .catch(err => console.error("Error loading proposal details:", err));
        },

        closeProposalDrawer: function () {
            const drawer = document.getElementById("clientProposalDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.style.display = "none";
                document.body.style.overflow = "";
            }
            if (this.lastFocusedBtn && typeof this.lastFocusedBtn.focus === "function") {
                this.lastFocusedBtn.focus();
            }
        },

        switchProposalDrawerTab: function (tabName) {
            this.activeTab = tabName;
            document.querySelectorAll("#clientProposalDrawer .client-portal-drawer-tab").forEach(tab => {
                const isActive = tab.getAttribute("data-tab") === tabName;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            const body = document.getElementById("clientProposalDrawerBody");
            if (!body || !activeProposal) return;

            const p = activeProposal;
            const curStatus = p.display_status || p.status;
            const isActionable = p.is_actionable;
            const sym = p.currency_symbol || "$";

            if (tabName === "overview") {
                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">PROPOSAL AMOUNT</div>
                            <div style="font-size:18px;font-weight:700;color:#0F172A;margin-top:2px;">${escapeHtml(p.formatted_total)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">EXPIRY DATE</div>
                            <div style="font-size:14px;font-weight:700;color:#0F172A;margin-top:4px;">${escapeHtml(p.expiry_date || 'N/A')}</div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Proposal Scope Summary</h4>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;">${escapeHtml(p.scope || 'No scope summary provided.')}</p>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">RELATED PROJECT</h4>
                            <div style="font-size:13px;color:#0F172A;font-weight:600;">${escapeHtml(p.project_name || 'None')}</div>
                        </div>
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">RELATED DEAL</h4>
                            <div style="font-size:13px;color:#0F172A;font-weight:600;">${escapeHtml(p.deal_name || 'None')}</div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">CLIENT</h4>
                            <div style="font-size:13px;color:#334155;">${escapeHtml(p.company_name || 'Client Workspace')}</div>
                        </div>
                        <div>
                            <h4 style="font-size:11px;color:#64748B;font-weight:600;">PREPARED BY</h4>
                            <div style="font-size:13px;color:#334155;">${escapeHtml(p.prepared_by_name || 'Account Lead')}</div>
                        </div>
                    </div>

                    ${p.change_request_message ? `
                        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:14px 16px;margin-top:16px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <div style="font-size:13px;font-weight:700;color:#B45309;">Submitted Change Request (${escapeHtml(p.change_request_type || 'General')})</div>
                                <span class="client-portal-badge amber" style="font-size:10px;">Pending Review</span>
                            </div>
                            <p style="font-size:12.5px;color:#92400E;margin:0;line-height:1.5;">${escapeHtml(p.change_request_message)}</p>
                        </div>
                    ` : ''}

                    ${isActionable ? `
                        <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:14px 16px;margin-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#1E40AF;">${curStatus === "Changes Requested" ? "Change Request Under Review" : "Awaiting Client Approval"}</div>
                                <div style="font-size:12px;color:#1E3A8A;">${curStatus === "Changes Requested" ? "Your change request has been submitted to the account team." : "Review the deliverables and accept, request changes, or decline below."}</div>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientProposals.promptAccept(${p.id})">Accept Proposal</button>
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#D97706;border-color:#FCD34D;background:#FEF3C7;" onclick="window.clientProposals.promptRequestChanges(${p.id})">Request Changes</button>
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#B91C1C;" onclick="window.clientProposals.promptDecline(${p.id})">Decline</button>
                            </div>
                        </div>
                    ` : ''}
                `;
            } else if (tabName === "items") {
                const deliverables = p.deliverables || [];
                const subtotal = Number(p.subtotal || 0);
                const discount = Number(p.discount || 0);
                const tax = Number(p.tax || 0);
                const taxRate = Number(p.tax_rate || 0);
                const total = Number(p.total || 0);

                body.innerHTML = `
                    <div style="border:1px solid #E2E8F0;border-radius:8px;overflow:hidden;margin-bottom:16px;">
                        <table class="client-portal-table">
                            <thead>
                                <tr>
                                    <th>Deliverable Item</th>
                                    <th>Qty</th>
                                    <th style="text-align:right;">Rate</th>
                                    <th style="text-align:right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${deliverables.length > 0 ? deliverables.map(item => `
                                    <tr>
                                        <td>
                                            <div style="font-weight:600;color:#0F172A;font-size:13px;">${escapeHtml(item.name)}</div>
                                            ${item.description ? `<div style="font-size:11.5px;color:#64748B;">${escapeHtml(item.description)}</div>` : ''}
                                        </td>
                                        <td style="font-size:12.5px;color:#334155;">${item.qty}</td>
                                        <td style="text-align:right;font-size:12.5px;color:#334155;">${formatCurrency(item.rate, sym)}</td>
                                        <td style="text-align:right;font-weight:600;color:#0F172A;font-size:13px;">${formatCurrency(item.amount, sym)}</td>
                                    </tr>
                                `).join("") : `
                                    <tr>
                                        <td colspan="4" style="text-align:center;padding:24px;color:#64748B;">No deliverable items recorded for this proposal.</td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>

                    <div class="client-proposal-pricing-box">
                        <div class="client-proposal-summary-row">
                            <span>Subtotal</span>
                            <span>${formatCurrency(subtotal, sym)}</span>
                        </div>
                        ${discount > 0 ? `
                            <div class="client-proposal-summary-row">
                                <span>Discount</span>
                                <span style="color:#059669;">-${formatCurrency(discount, sym)}</span>
                            </div>
                        ` : ''}
                        <div class="client-proposal-summary-row">
                            <span>Tax (${taxRate}% GST/VAT)</span>
                            <span>+${formatCurrency(tax, sym)}</span>
                        </div>
                        <div class="client-proposal-summary-row total">
                            <span>Total Payable Amount</span>
                            <span style="color:#2563EB;">${formatCurrency(total, sym)}</span>
                        </div>
                    </div>
                `;
            } else if (tabName === "terms") {
                body.innerHTML = `
                    <div class="client-proposal-terms-box" style="margin-bottom:16px;">
                        <div style="font-weight:700;font-size:13px;margin-bottom:6px;color:#0F172A;">Payment Terms</div>
                        <p style="margin:0;line-height:1.5;color:#475569;font-size:13px;">${escapeHtml(p.payment_terms || 'Standard payment terms apply upon contract execution.')}</p>
                    </div>

                    <div class="client-proposal-terms-box">
                        <div style="font-weight:700;font-size:13px;margin-bottom:6px;color:#0F172A;">Terms &amp; Conditions</div>
                        <p style="margin:0;line-height:1.5;color:#475569;font-size:13px;">${escapeHtml(p.terms || 'This proposal is valid until the stated expiry date. Scope adjustments require mutual written agreement.')}</p>
                    </div>
                `;
            } else if (tabName === "timeline") {
                const timeline = p.timeline || [];
                body.innerHTML = `
                    <div class="client-proposal-timeline">
                        ${timeline.length > 0 ? timeline.map(t => `
                            <div class="client-proposal-timeline-item">
                                <div class="client-proposal-timeline-dot"></div>
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

        promptAccept: function (proposalId) {
            const p = activeProposal && activeProposal.id === proposalId 
                ? activeProposal 
                : cachedProposals.find(item => item.id === proposalId);
            if (!p) return;

            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Accept Proposal</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="client-portal-modal-body">
                    <p style="font-size:13.5px;color:#334155;margin:0 0 12px 0;">Are you sure you want to accept proposal <strong>${escapeHtml(p.title)}</strong> (${escapeHtml(p.proposal_number)}) for <strong>${escapeHtml(p.formatted_total)}</strong>?</p>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px;font-size:12.5px;color:#64748B;">
                        By accepting this proposal, your confirmation will be recorded securely and your account manager (${escapeHtml(p.prepared_by_name || 'Account Lead')}) will be notified to proceed with kickoff.
                    </div>
                </div>
                <div class="client-portal-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnConfirmAccept" onclick="window.clientProposals.confirmAccept(${proposalId})">Accept Proposal</button>
                </div>
            `;
            if (window.clientPortal && typeof window.clientPortal.openModal === "function") {
                window.clientPortal.openModal(html);
            }
        },

        confirmAccept: function (proposalId) {
            const btn = document.getElementById("btnConfirmAccept");
            if (btn) {
                btn.disabled = true;
                btn.textContent = "Accepting...";
            }

            fetch("api/client-proposals.php?action=accept", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": getCsrfToken()
                },
                body: `id=${encodeURIComponent(proposalId)}&csrf_token=${encodeURIComponent(getCsrfToken())}`
            })
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    alert(json.message || "Failed to accept proposal.");
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = "Accept Proposal";
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
                fetchProposals();

                if (activeProposal && activeProposal.id === proposalId) {
                    activeProposal.status = "Accepted";
                    activeProposal.display_status = "Accepted";
                    activeProposal.is_actionable = false;
                    const badge = document.getElementById("cpdProposalStatusBadge");
                    if (badge) {
                        badge.textContent = "Accepted";
                        badge.className = "client-portal-badge green";
                    }
                    window.clientProposals.switchProposalDrawerTab(window.clientProposals.activeTab || "overview");
                }
            })
            .catch(err => {
                console.error("Error accepting proposal:", err);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Accept Proposal";
                }
            });
        },

        promptDecline: function (proposalId) {
            const p = activeProposal && activeProposal.id === proposalId 
                ? activeProposal 
                : cachedProposals.find(item => item.id === proposalId);
            if (!p) return;

            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Decline Proposal</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="client-portal-modal-body">
                    <p style="font-size:13.5px;color:#334155;margin:0 0 12px 0;">Are you sure you want to decline proposal <strong>${escapeHtml(p.title)}</strong> (${escapeHtml(p.proposal_number)})?</p>
                    <div style="margin-bottom:8px;">
                        <label class="client-login-label">Reason for declining (optional)</label>
                        <textarea class="input-control" id="declineReasonInput" rows="3" style="height:70px;resize:none;" placeholder="e.g. Budget constraints or requested scope revisions..."></textarea>
                    </div>
                </div>
                <div class="client-portal-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnConfirmDecline" style="background-color:#DC2626;border-color:#DC2626;" onclick="window.clientProposals.confirmDecline(${proposalId})">Decline Proposal</button>
                </div>
            `;
            if (window.clientPortal && typeof window.clientPortal.openModal === "function") {
                window.clientPortal.openModal(html);
            }
        },

        confirmDecline: function (proposalId) {
            const reason = (document.getElementById("declineReasonInput")?.value || "").trim();
            const btn = document.getElementById("btnConfirmDecline");
            if (btn) {
                btn.disabled = true;
                btn.textContent = "Declining...";
            }

            fetch("api/client-proposals.php?action=decline", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": getCsrfToken()
                },
                body: `id=${encodeURIComponent(proposalId)}&reason=${encodeURIComponent(reason)}&csrf_token=${encodeURIComponent(getCsrfToken())}`
            })
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    alert(json.message || "Failed to decline proposal.");
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = "Decline Proposal";
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
                fetchProposals();

                if (activeProposal && activeProposal.id === proposalId) {
                    activeProposal.status = "Declined";
                    activeProposal.display_status = "Declined";
                    activeProposal.is_actionable = false;
                    const badge = document.getElementById("cpdProposalStatusBadge");
                    if (badge) {
                        badge.textContent = "Declined";
                        badge.className = "client-portal-badge red";
                    }
                    window.clientProposals.switchProposalDrawerTab(window.clientProposals.activeTab || "overview");
                }
            })
            .catch(err => {
                console.error("Error declining proposal:", err);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Decline Proposal";
                }
            });
        },

        promptRequestChanges: function (proposalId) {
            const p = activeProposal && activeProposal.id === proposalId 
                ? activeProposal 
                : cachedProposals.find(item => item.id === proposalId);
            if (!p) return;

            const html = `
                <div class="client-portal-modal-header">
                    <div>
                        <h3 class="client-portal-modal-title">Request Changes</h3>
                        <p style="font-size:12px;color:#64748B;margin:2px 0 0 0;">Tell us what modifications you need for this proposal.</p>
                    </div>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()" aria-label="Close modal">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="client-portal-modal-body">
                    <div id="changeRequestError" style="display:none;background:#FEE2E2;border:1px solid #FCA5A5;color:#DC2626;padding:10px 14px;border-radius:6px;font-size:12.5px;margin-bottom:14px;font-weight:600;">
                        Please describe the changes you would like to request.
                    </div>

                    <div style="margin-bottom:14px;">
                        <label class="client-login-label" for="crChangeType">Change Type</label>
                        <select class="input-control" id="crChangeType">
                            <option value="Pricing">Pricing</option>
                            <option value="Payment Terms">Payment Terms</option>
                            <option value="Deliverables">Deliverables</option>
                            <option value="Scope of Work" selected>Scope of Work</option>
                            <option value="Timeline">Timeline</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label class="client-login-label" for="crMessage">Message / Change Request *</label>
                        <textarea class="input-control" id="crMessage" rows="4" style="height:100px;resize:none;padding:10px 12px;" placeholder="Describe the changes you'd like us to make..."></textarea>
                    </div>

                    <div>
                        <label class="client-login-label" for="crPriority">Priority (Optional)</label>
                        <select class="input-control" id="crPriority">
                            <option value="Normal" selected>Normal</option>
                            <option value="Important">Important</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="client-portal-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnConfirmCR" style="background-color:#D97706;border-color:#D97706;" onclick="window.clientProposals.confirmRequestChanges(${proposalId})">Submit Change Request</button>
                </div>
            `;
            if (window.clientPortal && typeof window.clientPortal.openModal === "function") {
                window.clientPortal.openModal(html);
                setTimeout(() => {
                    const msgEl = document.getElementById("crMessage");
                    if (msgEl) msgEl.focus();
                }, 100);
            }
        },

        confirmRequestChanges: function (proposalId) {
            const msgInput = document.getElementById("crMessage");
            const typeInput = document.getElementById("crChangeType");
            const priorityInput = document.getElementById("crPriority");
            const errorDiv = document.getElementById("changeRequestError");
            const btn = document.getElementById("btnConfirmCR");

            const message = msgInput ? msgInput.value.trim() : "";
            const changeType = typeInput ? typeInput.value : "Scope of Work";
            const priority = priorityInput ? priorityInput.value : "Normal";

            if (!message) {
                if (errorDiv) {
                    errorDiv.style.display = "block";
                    errorDiv.textContent = "Please describe the changes you would like to request.";
                }
                if (msgInput) msgInput.focus();
                return;
            }

            if (btn) {
                btn.disabled = true;
                btn.textContent = "Submitting...";
            }

            const body = `id=${encodeURIComponent(proposalId)}&message=${encodeURIComponent(message)}&change_type=${encodeURIComponent(changeType)}&priority=${encodeURIComponent(priority)}&csrf_token=${encodeURIComponent(getCsrfToken())}`;

            fetch("api/client-proposals.php?action=request_changes", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": getCsrfToken()
                },
                body: body
            })
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    if (errorDiv) {
                        errorDiv.style.display = "block";
                        errorDiv.textContent = json.message || "Failed to submit change request.";
                    } else {
                        alert(json.message);
                    }
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = "Submit Change Request";
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
                fetchProposals();

                if (activeProposal && activeProposal.id === proposalId) {
                    activeProposal.status = "Changes Requested";
                    activeProposal.display_status = "Changes Requested";
                    activeProposal.change_request_type = changeType;
                    activeProposal.change_request_message = message;
                    const badge = document.getElementById("cpdProposalStatusBadge");
                    if (badge) {
                        badge.textContent = "Changes Requested";
                        badge.className = "client-portal-badge amber";
                    }
                    window.clientProposals.switchProposalDrawerTab(window.clientProposals.activeTab || "overview");
                }
            })
            .catch(err => {
                console.error("Error requesting changes:", err);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Submit Change Request";
                }
            });
        },

        printCurrentProposal: function () {
            if (!activeProposal) return;
            const p = activeProposal;
            const sym = p.currency_symbol || (p.currency ? (p.currency.match(/\(([^)]+)\)/)?.[1] || "$") : "$");
            const items = p.items || p.deliverables || [];

            let itemsHtml = "";
            if (items.length > 0) {
                itemsHtml = items.map(it => `
                    <tr>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #E2E8F0;">
                            <strong>${escapeHtml(it.name || "")}</strong>
                            ${it.description ? `<div style="font-size: 12px; color: #64748B; margin-top: 3px;">${escapeHtml(it.description)}</div>` : ""}
                        </td>
                        <td style="padding: 10px 12px; text-align: center; border-bottom: 1px solid #E2E8F0;">${escapeHtml(it.qty || 1)}</td>
                        <td style="padding: 10px 12px; text-align: right; border-bottom: 1px solid #E2E8F0;">${formatCurrency(it.rate || 0, sym)}</td>
                        <td style="padding: 10px 12px; text-align: right; border-bottom: 1px solid #E2E8F0; font-weight: 600;">${formatCurrency(it.amount || 0, sym)}</td>
                    </tr>
                `).join("");
            } else {
                itemsHtml = `<tr><td colspan="4" style="padding: 12px; text-align: center; color: #94A3B8;">No itemized deliverables listed.</td></tr>`;
            }

            const printContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proposal - ${escapeHtml(p.proposal_number || "")}</title>
    <style>
        @media print {
            body { margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0F172A; }
            .no-print { display: none !important; }
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0F172A; max-width: 800px; margin: 20px auto; padding: 20px; line-height: 1.5; font-size: 13.5px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #E2E8F0; padding-bottom: 16px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: 700; margin: 0 0 4px 0; color: #0F172A; }
        .prop-num { font-size: 14px; font-weight: 600; color: #2563EB; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .box { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; }
        .box h4 { margin: 0 0 8px 0; font-size: 12px; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th { background: #F1F5F9; padding: 10px 12px; text-align: left; font-size: 12.5px; color: #475569; border-bottom: 1px solid #CBD5E1; }
        .summary-wrap { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .summary-box { width: 280px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; }
        .summary-line { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; color: #475569; }
        .summary-line.total { border-top: 1px dashed #CBD5E1; padding-top: 8px; margin-top: 8px; font-size: 15px; font-weight: 700; color: #0F172A; }
        .footer-note { font-size: 12px; color: #94A3B8; border-top: 1px solid #E2E8F0; padding-top: 16px; margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="prop-num">${escapeHtml(p.proposal_number || "")}</div>
            <h1 class="title">${escapeHtml(p.title || "")}</h1>
            <div style="font-size: 12.5px; color: #64748B;">Status: <strong>${escapeHtml(p.display_status || p.status || "")}</strong></div>
        </div>
        <div style="text-align: right; font-size: 12.5px; color: #475569;">
            <div>Issue Date: <strong>${escapeHtml(p.issue_date || "—")}</strong></div>
            <div>Valid Until: <strong>${escapeHtml(p.expiry_date || "—")}</strong></div>
        </div>
    </div>

    <div class="grid-2">
        <div class="box">
            <h4>Client Information</h4>
            <div style="font-weight: 600; font-size: 14px;">${escapeHtml(p.company_name || p.client_name || p.client || "Client")}</div>
            ${p.contact_name ? `<div>${escapeHtml(p.contact_name)}</div>` : ""}
            ${p.client_email ? `<div style="color: #64748B;">${escapeHtml(p.client_email)}</div>` : ""}
        </div>
        <div class="box">
            <h4>Project &amp; Deal Context</h4>
            ${p.project_name ? `<div>Project: <strong>${escapeHtml(p.project_name)}</strong></div>` : ""}
            ${p.deal_name ? `<div>Deal: <strong>${escapeHtml(p.deal_name)}</strong></div>` : ""}
            ${p.prepared_by_name ? `<div>Prepared By: <strong>${escapeHtml(p.prepared_by_name)}</strong></div>` : ""}
        </div>
    </div>

    ${p.scope ? `
    <div style="margin-bottom: 24px;">
        <h4 style="margin: 0 0 8px 0; font-size: 13px; text-transform: uppercase; color: #64748B;">Scope of Work</h4>
        <div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; white-space: pre-line; color: #334155;">${escapeHtml(p.scope)}</div>
    </div>
    ` : ""}

    <table class="table">
        <thead>
            <tr>
                <th style="text-align: left;">Deliverable &amp; Description</th>
                <th style="text-align: center; width: 70px;">Qty</th>
                <th style="text-align: right; width: 110px;">Rate</th>
                <th style="text-align: right; width: 120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            ${itemsHtml}
        </tbody>
    </table>

    <div class="summary-wrap">
        <div class="summary-box">
            <div class="summary-line">
                <span>Subtotal</span>
                <strong>${formatCurrency(p.subtotal || 0, sym)}</strong>
            </div>
            ${Number(p.discount) > 0 ? `
            <div class="summary-line" style="color: #10B981;">
                <span>Discount</span>
                <span>-${formatCurrency(p.discount, sym)}</span>
            </div>` : ""}
            <div class="summary-line">
                <span>Tax (${Number(p.tax_rate || 18)}%)</span>
                <span>${formatCurrency(p.tax || 0, sym)}</span>
            </div>
            <div class="summary-line total">
                <span>Total Amount</span>
                <span style="color: #2563EB;">${formatCurrency(p.total || 0, sym)}</span>
            </div>
        </div>
    </div>

    ${p.payment_terms || p.terms ? `
    <div style="border-top: 1px solid #E2E8F0; padding-top: 16px; margin-top: 20px; font-size: 12.5px; color: #475569;">
        ${p.payment_terms ? `<div style="margin-bottom: 10px;"><strong>Payment Terms:</strong> ${escapeHtml(p.payment_terms)}</div>` : ""}
        ${p.terms ? `<div><strong>Terms &amp; Conditions:</strong> ${escapeHtml(p.terms)}</div>` : ""}
    </div>
    ` : ""}

    <div class="footer-note">
        This document was generated from the Client Portal. For inquiries, please contact your account representative.
    </div>
</body>
</html>`;

            const printWindow = window.open("", "_blank");
            if (printWindow) {
                printWindow.document.write(printContent);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => {
                    printWindow.print();
                }, 300);
            }
        }
    };

    // Keyboard ESC handling
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            const modal = document.getElementById("clientModalOverlay");
            if (modal && (modal.classList.contains("show") || modal.style.display === "flex")) {
                window.clientPortal.closeModal();
                e.stopPropagation();
                return;
            }
            const drawer = document.getElementById("clientProposalDrawer");
            if (drawer && (drawer.classList.contains("show") || drawer.style.display === "block")) {
                window.clientProposals.closeProposalDrawer();
                e.stopPropagation();
            }
        }
    });

    // Auto-init on page load
    document.addEventListener("DOMContentLoaded", function () {
        refreshKPIs();
        fetchProposals();
    });
})();