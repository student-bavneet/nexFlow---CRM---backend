/**
 * NexFlow CRM — Client Portal Deals Controller
 * Multi-tenant, multi-company contact isolated script for client-deals.php.
 * All deal data is sourced strictly from MySQL via window.__CLIENT_DEALS_DATA__ and api/client-deals.php.
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

    // In-memory deal store initialized from server-rendered payload
    let liveDeals = [];
    if (window.__CLIENT_DEALS_DATA__ && Array.isArray(window.__CLIENT_DEALS_DATA__.deals)) {
        liveDeals = window.__CLIENT_DEALS_DATA__.deals;
    }

    // In-memory cache for full deal detail objects (tasks, documents)
    const dealDetailsCache = {};

    function getDealsData() {
        return liveDeals;
    }

    function getStageBadgeClass(stage) {
        const s = (stage || "").toLowerCase();
        if (s.includes("won") || s.includes("active")) return "green";
        if (s.includes("proposal") || s.includes("review")) return "blue";
        return "amber";
    }

    // Render Deals Table Body
    window.renderClientDeals = function () {
        const deals = getDealsData();
        const tbody = document.getElementById("clientDealsTbody");
        if (!tbody) return;

        const query = (document.getElementById("dealsSearchInput")?.value || "").toLowerCase().trim();
        const statusFilter = document.getElementById("dealsStatusFilter")?.value || "All";
        const sortBy = document.getElementById("dealsSortSelect")?.value || "value-desc";

        let filtered = deals.filter(d => {
            const nameStr = (d.name || "").toLowerCase();
            const codeStr = (d.deal_code || "").toLowerCase();
            const descStr = (d.description || "").toLowerCase();
            const stageStr = (d.stage || "").toLowerCase();
            const ownerStr = (d.owner || "").toLowerCase();

            const matchesQuery = !query ||
                nameStr.includes(query) ||
                codeStr.includes(query) ||
                descStr.includes(query) ||
                stageStr.includes(query) ||
                ownerStr.includes(query);

            let matchesStatus = true;
            if (statusFilter === "In Progress") {
                matchesStatus = (d.status === "open") || ["prospect", "qualified", "proposal", "negotiation"].includes(stageStr);
            } else if (statusFilter === "Under Review") {
                matchesStatus = ["proposal", "negotiation", "under review"].includes(stageStr);
            } else if (statusFilter === "Active") {
                matchesStatus = (d.status === "won") || stageStr.includes("won") || stageStr.includes("active");
            }

            return matchesQuery && matchesStatus;
        });

        // Sorting
        if (sortBy === "value-desc") {
            filtered.sort((a, b) => (b.value || 0) - (a.value || 0));
        } else if (sortBy === "value-asc") {
            filtered.sort((a, b) => (a.value || 0) - (b.value || 0));
        } else if (sortBy === "date-asc") {
            filtered.sort((a, b) => new Date(a.rawCloseDate || a.closeDate) - new Date(b.rawCloseDate || b.closeDate));
        }

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:#64748B;">No matching contracts found.</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(d => {
            const stageBadgeClass = getStageBadgeClass(d.stage);
            const probInt = Math.round(d.probability || 0);
            const probColor = probInt === 100 ? "#10B981" : "#2563EB";
            const ownerInitials = d.ownerInitials || (d.owner ? d.owner.split(" ").map(n => n[0]).join("") : "AE");
            const descSnippet = (d.description || "").slice(0, 70);
            const hasMoreDesc = (d.description || "").length > 70;

            return `
                <tr>
                    <td>
                        <div style="font-weight:600;color:#0F172A;font-size:13.5px;">${escapeHtml(d.name)}</div>
                        <div style="font-size:11.5px;color:#64748B;margin-top:2px;">${escapeHtml(descSnippet)}${hasMoreDesc ? '...' : ''}</div>
                    </td>
                    <td style="font-weight:700;color:#0F172A;font-size:14px;">${escapeHtml(d.formattedValue)}</td>
                    <td>
                        <span class="client-portal-badge ${stageBadgeClass}">${escapeHtml(d.stage)}</span>
                    </td>
                    <td style="width:130px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;height:6px;background:#E2E8F0;border-radius:3px;overflow:hidden;">
                                <div style="width:${Math.min(100, Math.max(0, probInt))}%;height:100%;background:${probColor};"></div>
                            </div>
                            <span style="font-size:11.5px;font-weight:600;color:#64748B;">${probInt}%</span>
                        </div>
                    </td>
                    <td style="font-size:13px;color:#334155;">${escapeHtml(d.closeDate)}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div class="client-portal-avatar" style="background-color:#7C3AED;width:22px;height:22px;font-size:9px;">${escapeHtml(ownerInitials)}</div>
                            <span style="font-size:12.5px;color:#334155;">${escapeHtml(d.owner)}</span>
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientDeals.openDealDrawer(${parseInt(d.id, 10)}, this)">
                            View Details
                        </button>
                    </td>
                </tr>
            `;
        }).join("");
    };

    // Public Controller for Deals
    window.clientDeals = {
        activeDealId: null,
        activeTab: "overview",
        lastFocusedBtn: null,

        openDealDrawer: function (dealId, triggerBtn) {
            this.lastFocusedBtn = triggerBtn || document.activeElement;
            const numericId = parseInt(dealId, 10);
            const deals = getDealsData();
            const deal = deals.find(d => parseInt(d.id, 10) === numericId);
            if (!deal) return;

            this.activeDealId = numericId;
            this.activeTab = "overview";

            const drawer = document.getElementById("clientDealDrawer");
            const title = document.getElementById("clientDealDrawerTitle");
            const badge = document.getElementById("cddStageBadge");
            const sendMsgBtn = document.getElementById("btnDrawerSendMessage");

            if (title) title.textContent = `${deal.name} (${deal.deal_code || ('DL-' + deal.id)})`;
            if (badge) {
                badge.textContent = deal.stage;
                const st = (deal.stage || "").toLowerCase();
                badge.className = "client-portal-drawer-badge " + (
                    st.includes("won") ? "stage-won" :
                    st.includes("proposal") ? "stage-proposal" :
                    st.includes("qualified") ? "stage-qualified" : "stage-discovery"
                );
            }

            if (sendMsgBtn) {
                const ownerSlug = (deal.owner || "account-lead").toLowerCase().replace(/\s+/g, '-');
                sendMsgBtn.setAttribute("href", `client-messages.php?contact=${encodeURIComponent(ownerSlug)}`);
            }

            // Immediately render the overview tab using in-memory data
            this.renderDrawerTabContent("overview", deal);

            if (drawer) {
                drawer.style.display = "block";
                drawer.classList.add("show");
                document.body.style.overflow = "hidden";
                const firstTab = drawer.querySelector(".client-portal-drawer-tab");
                if (firstTab) firstTab.focus();
            }

            // Fetch live authorized sub-data (tasks, documents) from api/client-deals.php
            this.fetchDealDetails(numericId);
        },

        fetchDealDetails: function (dealId) {
            const self = this;
            fetch(`api/client-deals.php?action=detail&id=${encodeURIComponent(dealId)}`, { credentials: "same-origin" })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(json => {
                    if (json.success && json.data) {
                        dealDetailsCache[dealId] = json.data;
                        if (self.activeDealId === dealId) {
                            self.renderDrawerTabContent(self.activeTab, json.data);
                        }
                    }
                })
                .catch(err => {
                    console.warn("Could not load deal sub-data:", err);
                });
        },

        closeDealDrawer: function () {
            const drawer = document.getElementById("clientDealDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.style.display = "none";
                document.body.style.overflow = "";
            }
            if (this.lastFocusedBtn && typeof this.lastFocusedBtn.focus === "function") {
                this.lastFocusedBtn.focus();
            }
        },

        switchDealDrawerTab: function (tabName) {
            this.activeTab = tabName;
            document.querySelectorAll("#clientDealDrawer .client-portal-drawer-tab").forEach(tab => {
                const isActive = tab.getAttribute("data-tab") === tabName;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            const dealId = this.activeDealId;
            const fullDetail = dealDetailsCache[dealId];
            if (fullDetail) {
                this.renderDrawerTabContent(tabName, fullDetail);
            } else {
                const deals = getDealsData();
                const deal = deals.find(d => parseInt(d.id, 10) === dealId);
                if (deal) {
                    this.renderDrawerTabContent(tabName, deal);
                }
            }
        },

        renderDrawerTabContent: function (tabName, deal) {
            const body = document.getElementById("clientDealDrawerBody");
            if (!body || !deal) return;

            const initials = deal.ownerInitials || (deal.owner ? deal.owner.split(" ").map(n => n[0]).join("") : "AE");

            if (tabName === "overview") {
                // Pipeline 4-step progress logic
                const st = (deal.stage || "").toLowerCase();
                let s0 = "future", s1 = "future", s2 = "future", s3 = "future";
                if (st.includes("prospect") || st.includes("discovery")) {
                    s0 = "current";
                } else if (st.includes("qualified")) {
                    s0 = "done"; s1 = "current";
                } else if (st.includes("proposal")) {
                    s0 = "done"; s1 = "done"; s2 = "current";
                } else if (st.includes("negotiat")) {
                    s0 = "done"; s1 = "done"; s2 = "done"; s3 = "current";
                } else if (st.includes("won") || st.includes("active") || st.includes("closed")) {
                    s0 = "done"; s1 = "done"; s2 = "done"; s3 = "done";
                } else {
                    s0 = "done"; s1 = "current";
                }

                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">ESTIMATED VALUE</div>
                            <div style="font-size:18px;font-weight:700;color:#0F172A;margin-top:2px;">${escapeHtml(deal.formattedValue)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">EXPECTED CLOSE</div>
                            <div style="font-size:14px;font-weight:700;color:#0F172A;margin-top:4px;">${escapeHtml(deal.closeDate)}</div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:8px;">Stage Progress (${escapeHtml(deal.stage)})</h4>
                        <div class="client-portal-deal-progress" style="margin:0;">
                            <div class="client-portal-pipeline-track">
                                <div class="client-portal-pipeline-step ${s0}" title="Discovery: ${s0}"></div>
                                <div class="client-portal-pipeline-step ${s1}" title="Qualified: ${s1}"></div>
                                <div class="client-portal-pipeline-step ${s2}" title="Proposal: ${s2}"></div>
                                <div class="client-portal-pipeline-step ${s3}" title="Final SLA: ${s3}"></div>
                            </div>
                            <div class="client-portal-pipeline-labels">
                                <span style="${s0==='done'?'color:#047857;font-weight:600;':s0==='current'?'color:#2563EB;font-weight:700;':''}">Discovery</span>
                                <span style="${s1==='done'?'color:#047857;font-weight:600;':s1==='current'?'color:#2563EB;font-weight:700;':''}">Qualified</span>
                                <span style="${s2==='done'?'color:#047857;font-weight:600;':s2==='current'?'color:#2563EB;font-weight:700;':''}">Proposal</span>
                                <span style="${s3==='done'?'color:#047857;font-weight:600;':s3==='current'?'color:#2563EB;font-weight:700;':''}">Final SLA</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Scope Description</h4>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;">${escapeHtml(deal.description || "No scope description provided for this deal.")}</p>
                    </div>

                    <div>
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">NexFlow Account Lead</h4>
                        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
                            <div class="client-portal-avatar" style="background-color:#7C3AED;">${escapeHtml(initials)}</div>
                            <div style="flex:1;">
                                <div style="font-size:13.5px;font-weight:600;color:#0F172A;">${escapeHtml(deal.owner)}</div>
                                <div style="font-size:11.5px;color:#64748B;">${escapeHtml(deal.ownerTitle || "NexFlow Account Lead")}</div>
                            </div>
                            <a href="client-messages.php?contact=${encodeURIComponent(deal.owner.toLowerCase().replace(/\s+/g, '-'))}" class="btn btn-secondary btn-xs">Direct Message</a>
                        </div>
                    </div>
                `;
            } else if (tabName === "activity") {
                // Mandatory Rule: Do NOT expose internal team_activities.
                // Clean empty state.
                body.innerHTML = `
                    <div style="text-align:center;padding:36px 16px;color:#64748B;">
                        <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px auto;display:block;">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">No recent activity recorded for this deal.</div>
                        <div style="font-size:12.5px;color:#64748B;">All key milestones and updates will appear here as discussions progress.</div>
                    </div>
                `;
            } else if (tabName === "tasks") {
                const tasks = deal.tasks || [];
                if (tasks.length === 0) {
                    body.innerHTML = '<p style="font-size:13px;color:#64748B;text-align:center;padding:24px 0;">No active tasks for this contract milestone.</p>';
                    return;
                }

                // Strictly read-only task list in Phase 4 (checkboxes disabled, no mutation API)
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
            } else if (tabName === "documents") {
                const docs = deal.documents || [];
                if (docs.length === 0) {
                    body.innerHTML = `
                        <div style="text-align:center;padding:36px 16px;color:#64748B;">
                            <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px auto;display:block;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">No shared files for this deal.</div>
                            <div style="font-size:12.5px;color:#64748B;">Shared contracts, proposals, and specifications will appear here.</div>
                        </div>
                    `;
                    return;
                }

                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${docs.map(doc => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="client-portal-doc-icon" style="width:34px;height:34px;border-radius:6px;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                    </div>
                                    <div>
                                        <div style="font-size:13px;font-weight:600;color:#0F172A;">${escapeHtml(doc.name)}</div>
                                        <div style="font-size:11px;color:#64748B;">${escapeHtml(doc.type)} • ${escapeHtml(doc.size)}</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientPortal.showToast('Downloading: ' + '${escapeHtml(doc.name)}', 'info')">Download</button>
                            </div>
                        `).join("")}
                    </div>
                `;
            } else if (tabName === "comments") {
                body.innerHTML = `
                    <div style="text-align:center;padding:36px 16px;color:#64748B;">
                        <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px auto;display:block;">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">Questions or Notes for Your Account Team</div>
                        <p style="font-size:12.5px;color:#64748B;margin:0 0 16px 0;line-height:1.5;">Have questions regarding this deal scope, pricing, or timeline? Send a direct message to your assigned NexFlow account lead.</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Deal: ' + '${escapeHtml(deal.name)}')">Contact Account Lead</button>
                    </div>
                `;
            }
        }
    };

    // Forward legacy clientPortal deal drawer hooks
    if (window.clientPortal) {
        window.clientPortal.openDealDrawer = function (dealId, triggerBtn) {
            window.clientDeals.openDealDrawer(dealId, triggerBtn);
        };
        window.clientPortal.switchDealDrawerTab = function (tabName) {
            window.clientDeals.switchDealDrawerTab(tabName);
        };
        window.clientPortal.closeDealDrawer = function () {
            window.clientDeals.closeDealDrawer();
        };
    }

    // Auto-render on DOMContentLoaded
    document.addEventListener("DOMContentLoaded", function () {
        window.renderClientDeals();
    });
})();