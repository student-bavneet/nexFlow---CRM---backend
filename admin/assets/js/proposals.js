/**
 * NexFlow CRM — Admin Proposals Controller
 * Database-backed controller for admin/proposals.php.
 * Connects directly to admin/api/proposals.php for multi-tenant MySQL storage,
 * SQL-aggregated KPIs, table rendering with pagination & filters,
 * drawer management, polymorphic activities, send workflow, duplicate, delete, and CSV export.
 */

(function () {
    'use strict';

    const API_URL = 'api/proposals.php';

    let referenceOptions = {
        companies: [],
        contacts: [],
        deals: [],
        projects: [],
        users: [],
        currency: 'USD ($)',
        next_proposal_number: 'PROP-001',
        statuses: ['Draft', 'Sent', 'Viewed', 'Changes Requested', 'Accepted', 'Declined', 'Expired'],
        default_tax_rate: 18
    };

    let summaryData = null;
    let proposalsList = [];
    let activeProposalData = null;
    let pendingSendProposal = null;

    // Multi-currency formatter using live organization currency or proposal-specific currency
    function formatMoney(amount, customCurrency = null) {
        const num = parseFloat(amount) || 0;
        const curr = customCurrency || (activeProposalData && activeProposalData.currency) || referenceOptions.currency || 'USD ($)';
        let symbol = '$';
        if (curr.includes('₹')) symbol = '₹';
        else if (curr.includes('€')) symbol = '€';
        else if (curr.includes('£')) symbol = '£';
        else if (curr.includes('$')) symbol = '$';
        else {
            const m = curr.match(/\(([^)]+)\)/);
            if (m) symbol = m[1];
        }
        return symbol + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getCurrencySymbol(customCurrency = null) {
        const curr = customCurrency || (activeProposalData && activeProposalData.currency) || referenceOptions.currency || 'USD ($)';
        if (curr.includes('₹')) return '₹';
        if (curr.includes('€')) return '€';
        if (curr.includes('£')) return '£';
        if (curr.includes('$')) return '$';
        const m = curr.match(/\(([^)]+)\)/);
        return m ? m[1] : '$';
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function restorePageInteraction() {
        document.body.style.overflow = '';
    }

    async function apiRequest(endpoint, options = {}) {
        try {
            const headers = {
                'Accept': 'application/json',
                ...(options.headers || {})
            };
            const csrfToken = window.adminCsrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                headers['X-CSRF-Token'] = csrfToken;
            }
            const resp = await fetch(endpoint, {
                ...options,
                headers
            });
            const data = await resp.json();
            return data;
        } catch (err) {
            console.error('Proposals API Error:', err);
            return { success: false, message: 'Network or server communication error.' };
        }
    }

    // Helper functions
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

    function showToast(title, msg = "", type = "info") {
        let container = document.getElementById("toastContainer");
        if (!container) {
            container = document.createElement("div");
            container.id = "toastContainer";
            container.style.cssText = "position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px;";
            document.body.appendChild(container);
        }
        const toast = document.createElement("div");
        toast.className = "toast";
        toast.style.cssText = `background:#0F172A; color:#FFF; padding:12px 18px; border-radius:8px; font-size:13px; box-shadow:0 10px 25px rgba(0,0,0,0.15); display:flex; flex-direction:column; gap:2px; opacity:1; transition:opacity 0.25s ease; border-left:4px solid ${type === 'success' ? '#10B981' : type === 'warning' ? '#F59E0B' : '#2563EB'}; min-width:260px;`;
        toast.innerHTML = `
            <strong style="font-size:13px; color:#FFF;">${escapeHtml(title)}</strong>
            ${msg ? `<span style="font-size:12px; color:#94A3B8;">${escapeHtml(msg)}</span>` : ''}
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 250);
        }, 3200);
    }

    // Main Controller Object
    window.adminProposals = {
        activeKpi: "All",
        activeStatusDropdown: "All",
        activeProposalId: null,
        activeViewTab: "overview",
        pendingSendId: null,
        pendingDeleteId: null,
        currentPage: 1,
        pageSize: 8,
        totalItems: 0,
        totalPages: 1,

        init: async function () {
            await this.loadReferenceOptions();
            await this.loadSummary();
            await this.render();
            this.setupGlobalEvents();
        },

        loadReferenceOptions: async function () {
            const res = await apiRequest(`${API_URL}?action=reference_options`);
            if (res && res.success && res.data) {
                referenceOptions = res.data;
                this.populateFormSelects();
                this.updateCurrencyLabels();
            }
        },

        populateFormSelects: function () {
            // Populate Client Select (#pfClientSelect)
            const clientSelect = document.getElementById("pfClientSelect");
            if (clientSelect) {
                const currentVal = clientSelect.value;
                let html = '<option value="">Select client...</option>';

                // Add Companies
                if (referenceOptions.companies && referenceOptions.companies.length > 0) {
                    html += '<optgroup label="Companies">';
                    referenceOptions.companies.forEach(c => {
                        html += `<option value="${escapeHtml(c.name)}" data-id="${c.id}" data-type="company">${escapeHtml(c.name)}</option>`;
                    });
                    html += '</optgroup>';
                }

                // Add Contacts
                if (referenceOptions.contacts && referenceOptions.contacts.length > 0) {
                    html += '<optgroup label="Contacts">';
                    referenceOptions.contacts.forEach(ct => {
                        const displayName = ct.company_name ? `${ct.name} (${ct.company_name})` : ct.name;
                        html += `<option value="${escapeHtml(ct.name)}" data-id="${ct.id}" data-type="contact" data-company-id="${ct.company_id || ''}" data-email="${escapeHtml(ct.email || '')}" data-company="${escapeHtml(ct.company_name || '')}">${escapeHtml(displayName)}</option>`;
                    });
                    html += '</optgroup>';
                }

                clientSelect.innerHTML = html;
                if (currentVal) clientSelect.value = currentVal;
            }

            // Populate Deal Select (#pfDealSelect)
            const dealSelect = document.getElementById("pfDealSelect");
            if (dealSelect) {
                const currentVal = dealSelect.value;
                let html = '<option value="">Select related deal (optional)...</option>';
                if (referenceOptions.deals && referenceOptions.deals.length > 0) {
                    referenceOptions.deals.forEach(d => {
                        html += `<option value="${escapeHtml(d.name)}" data-id="${d.id}">${escapeHtml(d.name)}</option>`;
                    });
                }
                dealSelect.innerHTML = html;
                if (currentVal) dealSelect.value = currentVal;
            }

            // Populate Project Select (#pfProjectSelect)
            const projectSelect = document.getElementById("pfProjectSelect");
            if (projectSelect) {
                const currentVal = projectSelect.value;
                let html = '<option value="">Select related project (optional)...</option>';
                if (referenceOptions.projects && referenceOptions.projects.length > 0) {
                    referenceOptions.projects.forEach(p => {
                        const code = p.project_code ? `[${p.project_code}] ` : '';
                        html += `<option value="${p.id}" data-id="${p.id}" data-deal-id="${p.deal_id || ''}">${escapeHtml(code + p.name)}</option>`;
                    });
                }
                projectSelect.innerHTML = html;
                if (currentVal) projectSelect.value = currentVal;
            }

            // Populate Prepared By Select (#pfPreparedBySelect)
            const prepSelect = document.getElementById("pfPreparedBySelect");
            if (prepSelect) {
                const currentVal = prepSelect.value;
                let html = '<option value="">Select team member...</option>';
                if (referenceOptions.users && referenceOptions.users.length > 0) {
                    referenceOptions.users.forEach(u => {
                        html += `<option value="${u.id}">${escapeHtml(u.name)}</option>`;
                    });
                }
                prepSelect.innerHTML = html;
                if (currentVal) prepSelect.value = currentVal;
            }

            // Populate Filter Prepared By Select (#filterPreparedBy)
            const filterPrepSelect = document.getElementById("filterPreparedBy");
            if (filterPrepSelect) {
                const currentVal = filterPrepSelect.value;
                let html = '<option value="all">All Team Members</option>';
                if (referenceOptions.users && referenceOptions.users.length > 0) {
                    referenceOptions.users.forEach(u => {
                        html += `<option value="${u.id}">${escapeHtml(u.name)}</option>`;
                    });
                }
                filterPrepSelect.innerHTML = html;
                if (currentVal) filterPrepSelect.value = currentVal;
            }
        },

        updateCurrencyLabels: function () {
            const sym = getCurrencySymbol();
            document.querySelectorAll(".currency-symbol-label").forEach(el => {
                el.textContent = sym;
            });
            // Update deliverables table rate/amount column headers if needed
            const table = document.getElementById("pfDeliverablesTable");
            if (table) {
                const ths = table.querySelectorAll("th");
                if (ths[2]) ths[2].innerHTML = `Rate (<span class="currency-symbol-label">${sym}</span>)`;
                if (ths[3]) ths[3].innerHTML = `Amount (<span class="currency-symbol-label">${sym}</span>)`;
            }
        },

        loadSummary: async function () {
            const res = await apiRequest(`${API_URL}?action=summary`);
            if (res && res.success && res.data) {
                summaryData = res.data;
                const kpi = res.data.kpi || {};

                const elTotal = document.getElementById("kpiTotalProposals");
                const elDraft = document.getElementById("kpiDraftProposals");
                const elSent = document.getElementById("kpiSentProposals");
                const elAccepted = document.getElementById("kpiAcceptedProposals");
                const elDeclined = document.getElementById("kpiDeclinedProposals");
                const badge = document.getElementById("proposalsTotalBadge");

                if (elTotal) elTotal.textContent = kpi.total_count || 0;
                if (elDraft) elDraft.textContent = kpi.draft_proposals || 0;
                if (elSent) elSent.textContent = kpi.sent_viewed_proposals || 0;
                if (elAccepted) elAccepted.textContent = kpi.accepted_proposals || 0;
                if (elDeclined) elDeclined.textContent = kpi.declined_expired || 0;
                if (badge) badge.textContent = `${kpi.total_count || 0} proposals`;
            }
        },

        selectTab: function (status) {
            this.onStatusDropdownChange(status);
        },

        goToPage: function (p) {
            this.currentPage = p;
            this.render();
        },

        selectKpi: function (kpiKey) {
            this.activeKpi = kpiKey || "All";
            this.currentPage = 1;

            const dropdown = document.getElementById("proposalsStatusSelect");
            if (dropdown) {
                if (kpiKey === "All") dropdown.value = "All";
                else if (kpiKey === "Draft") dropdown.value = "Draft";
                else if (kpiKey === "Accepted") dropdown.value = "Accepted";
                else dropdown.value = "All";
            }
            if (kpiKey === "All" || kpiKey === "Draft" || kpiKey === "Accepted") {
                this.activeStatusDropdown = dropdown ? dropdown.value : "All";
            } else {
                this.activeStatusDropdown = "All";
            }

            this.highlightActiveKpiCard();
            this.render();
        },

        onStatusDropdownChange: function (statusVal) {
            this.activeStatusDropdown = statusVal || "All";
            this.currentPage = 1;

            if (statusVal === "All") this.activeKpi = "All";
            else if (statusVal === "Draft") this.activeKpi = "Draft";
            else if (statusVal === "Accepted") this.activeKpi = "Accepted";
            else this.activeKpi = null;

            this.highlightActiveKpiCard();
            this.render();
        },

        highlightActiveKpiCard: function () {
            document.querySelectorAll(".projects-summary-card").forEach(card => {
                const kpi = card.getAttribute("data-kpi");
                if (this.activeKpi && kpi === this.activeKpi) {
                    card.classList.add("active");
                } else {
                    card.classList.remove("active");
                }
            });
        },

        render: async function () {
            const tbody = document.getElementById("adminProposalsTbody");
            const emptyState = document.getElementById("adminProposalsEmptyState");
            const tableCard = document.getElementById("adminProposalsTableCard");
            if (!tbody) return;

            const query = (document.getElementById("proposalSearchInput")?.value || "").trim();
            const sortBy = document.getElementById("proposalsSortSelect")?.value || "newest";

            const checkedStatuses = Array.from(document.querySelectorAll(".filter-status-cb:checked")).map(cb => cb.value);
            const preparedByVal = document.getElementById("filterPreparedBy")?.value || "all";
            const minAmt = document.getElementById("filterAmountMin")?.value || "";
            const maxAmt = document.getElementById("filterAmountMax")?.value || "";

            // Build API Query URL
            const params = new URLSearchParams();
            params.append('action', 'list');
            params.append('page', this.currentPage);
            params.append('per_page', this.pageSize);
            if (query) params.append('search', query);
            if (sortBy) params.append('sort', sortBy);
            if (this.activeKpi && this.activeKpi !== 'All') {
                params.append('kpi', this.activeKpi);
            } else if (this.activeStatusDropdown && this.activeStatusDropdown !== 'All') {
                params.append('status', this.activeStatusDropdown);
            }
            if (checkedStatuses.length > 0) {
                params.append('checked_statuses', checkedStatuses.join(','));
            }
            if (preparedByVal && preparedByVal !== 'all') {
                params.append('prepared_by', preparedByVal);
            }
            if (minAmt !== '') params.append('amount_min', minAmt);
            if (maxAmt !== '') params.append('amount_max', maxAmt);

            const res = await apiRequest(`${API_URL}?${params.toString()}`);
            if (!res || !res.success) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:20px; color:#F04438;">Error loading proposals: ${escapeHtml(res?.message || 'Server error')}</td></tr>`;
                return;
            }

            proposalsList = res.data.proposals || res.data.items || [];
            const pagination = res.data.pagination || {};
            this.totalItems = typeof pagination.total_items !== 'undefined'
                ? pagination.total_items
                : (typeof pagination.total !== 'undefined' ? pagination.total : proposalsList.length);
            this.totalPages = pagination.total_pages || 1;
            this.currentPage = pagination.page || 1;

            const pagingRange = document.getElementById("pagingRange");
            const pagingTotal = document.getElementById("pagingTotal");
            const controls = document.getElementById("paginationControls");

            const startIndex = (this.currentPage - 1) * this.pageSize;
            if (pagingRange) pagingRange.textContent = (!this.totalItems || this.totalItems === 0) ? "0" : `${startIndex + 1}–${Math.min(startIndex + this.pageSize, this.totalItems)}`;
            if (pagingTotal) pagingTotal.textContent = this.totalItems || 0;

            const container = document.querySelector('.pagination-container');
            if (container) {
                container.style.display = this.totalItems > this.pageSize ? 'flex' : 'none';
            }

            if (controls) {
                if (!this.totalItems || this.totalItems === 0) {
                    controls.innerHTML = `
                        <button class="pagination-btn" id="prevPageBtn" disabled>
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <button class="pagination-btn" id="nextPageBtn" disabled>
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `;
                } else {
                    let html = `
                        <button class="pagination-btn" id="prevPageBtn" ${this.currentPage === 1 ? 'disabled' : ''} onclick="window.adminProposals.goToPage(${this.currentPage - 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                    `;

                    const pageNumbers = getPaginationPages(this.currentPage, this.totalPages);
                    pageNumbers.forEach(p => {
                        if (p === '...') {
                            html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
                        } else {
                            const isActive = (p === this.currentPage);
                            html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.adminProposals.goToPage(${p})">${p}</button>`;
                        }
                    });

                    html += `
                        <button class="pagination-btn" id="nextPageBtn" ${this.currentPage === this.totalPages ? 'disabled' : ''} onclick="window.adminProposals.goToPage(${this.currentPage + 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `;
                    controls.innerHTML = html;
                }
            }

            if (!this.totalItems || this.totalItems === 0 || proposalsList.length === 0) {
                if (tableCard) tableCard.style.display = "none";
                if (emptyState) emptyState.style.display = "block";
                return;
            }

            if (emptyState) emptyState.style.display = "none";
            if (tableCard) tableCard.style.display = "block";

            tbody.innerHTML = proposalsList.map(p => {
                const displayStatus = p.display_status || p.status;
                const badgeClass = getStatusBadgeClass(displayStatus);
                const isDraftOrPending = (p.status === "Draft" || p.status === "Changes Requested" || p.status === "Expired");
                const propNum = escapeHtml(p.proposal_number || `PROP-${p.id}`);
                const clientName = escapeHtml(p.company_name || p.contact_name || p.client || '—');
                const dealName = escapeHtml(p.deal_name || p.deal || '—');
                const preparedName = escapeHtml(p.prepared_by_name || p.preparedBy || '—');
                const formattedAmt = p.formatted_total || p.formattedTotal || formatMoney(p.total || p.amount || 0);

                return `
                    <tr data-id="${p.id}">
                        <td class="cell-proposal-info" data-column-id="proposal">
                            <div class="proposal-title-clickable" style="font-weight: 600; color: var(--text-heading); cursor: pointer;" onclick="window.adminProposals.openViewDrawer('${p.id}')">
                                ${escapeHtml(p.title)}
                            </div>
                            <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;">${propNum}</div>
                        </td>
                        <td data-column-id="client" style="font-weight: 500; color: var(--text-heading);">${clientName}</td>
                        <td data-column-id="deal" style="font-size: 12.5px; color: var(--text-secondary);">${dealName}</td>
                        <td class="cell-amount" data-column-id="amount" style="font-weight: 700; color: var(--text-heading);">${formattedAmt}</td>
                        <td class="cell-status" data-column-id="status">
                            <span class="client-portal-badge ${badgeClass}">${escapeHtml(displayStatus)}</span>
                        </td>
                        <td class="cell-issue-date" data-column-id="issueDate" style="font-size: 12.5px; color: var(--text-secondary);">${p.issue_date || p.issueDate || '—'}</td>
                        <td class="cell-expiry-date" data-column-id="expiryDate" style="font-size: 12.5px; color: var(--text-secondary);">${p.expiry_date || p.expiryDate || '—'}</td>
                        <td data-column-id="preparedBy" style="font-size: 12.5px; color: var(--text-secondary);">${preparedName}</td>
                        <td class="cell-actions" data-column-id="actions" style="text-align: right;">
                            <div class="proposal-actions">
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.adminProposals.openViewDrawer('${p.id}')">View</button>
                                ${isDraftOrPending ? `
                                    <button type="button" class="btn btn-primary btn-xs" onclick="window.adminProposals.promptSend('${p.id}')">Send</button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
        },

        // Filter Popover
        toggleFilterPopover: function (e) {
            if (e) e.stopPropagation();
            const popover = document.getElementById("proposalsFilterPopover");
            if (popover) {
                const isShowing = popover.style.display === "block";
                popover.style.display = isShowing ? "none" : "block";
            }
        },

        closeFilterPopover: function () {
            const popover = document.getElementById("proposalsFilterPopover");
            if (popover) popover.style.display = "none";
        },

        applyPopoverFilters: function () {
            this.closeFilterPopover();
            this.currentPage = 1;
            this.updateFilterBadge();
            this.render();
            showToast("Filters Applied", "Proposal filter parameters updated.", "info");
        },

        updateFilterBadge: function () {
            const checkedStatuses = Array.from(document.querySelectorAll(".filter-status-cb:checked")).map(cb => cb.value);
            const prep = document.getElementById("filterPreparedBy")?.value || "all";
            const min = document.getElementById("filterAmountMin")?.value || "";
            const max = document.getElementById("filterAmountMax")?.value || "";

            let count = checkedStatuses.length;
            if (prep !== "all") count++;
            if (min !== "") count++;
            if (max !== "") count++;

            const badge = document.getElementById("proposalsFilterBadge");
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = "inline-block";
                } else {
                    badge.style.display = "none";
                }
            }
        },

        clearFilters: function () {
            const searchInput = document.getElementById("proposalSearchInput");
            if (searchInput) searchInput.value = "";
            const statusSelect = document.getElementById("proposalsStatusSelect");
            if (statusSelect) statusSelect.value = "All";
            document.querySelectorAll(".filter-status-cb").forEach(cb => cb.checked = false);
            const prepSelect = document.getElementById("filterPreparedBy");
            if (prepSelect) prepSelect.value = "all";
            const minAmt = document.getElementById("filterAmountMin");
            if (minAmt) minAmt.value = "";
            const maxAmt = document.getElementById("filterAmountMax");
            if (maxAmt) maxAmt.value = "";

            this.activeKpi = "All";
            this.activeStatusDropdown = "All";
            this.currentPage = 1;
            this.highlightActiveKpiCard();
            this.updateFilterBadge();
            this.closeFilterPopover();
            this.render();
            showToast("Filters Cleared", "All search and status filters cleared.", "info");
        },

        // Create / Edit Drawer
        openCreateDrawer: async function () {
            this.activeProposalId = null;
            document.getElementById("formDrawerTitle").textContent = "Create Proposal";
            document.getElementById("formDrawerSubtitle").textContent = "Create a proposal for a client deal.";
            document.getElementById("btnSubmitProposalForm").textContent = "Create Proposal";
            document.getElementById("pfProposalId").value = "";

            // Reset fields
            document.getElementById("pfTitleInput").value = "";
            document.getElementById("pfScopeInput").value = "";
            const projSelect = document.getElementById("pfProjectSelect");
            if (projSelect) projSelect.selectedIndex = 0;
            document.getElementById("pfPaymentTermsInput").value = "";
            document.getElementById("pfTermsInput").value = "";
            document.getElementById("pfDiscountInput").value = "0";

            const today = new Date();
            const todayStr = today.toISOString().split("T")[0];
            const futureDate = new Date();
            futureDate.setDate(futureDate.getDate() + 30);
            const futureStr = futureDate.toISOString().split("T")[0];
            document.getElementById("pfIssueDate").value = todayStr;
            document.getElementById("pfExpiryDate").value = futureStr;

            // Reset Client / Deal / Prepared By selects
            const clientSelect = document.getElementById("pfClientSelect");
            if (clientSelect) clientSelect.selectedIndex = 0;
            const dealSelect = document.getElementById("pfDealSelect");
            if (dealSelect) dealSelect.selectedIndex = 0;
            const prepSelect = document.getElementById("pfPreparedBySelect");
            if (prepSelect) prepSelect.selectedIndex = 0;

            // Reset deliverables table with 1 empty row
            const tbody = document.getElementById("pfDeliverablesTbody");
            tbody.innerHTML = "";
            this.addDeliverableRow({ name: "", desc: "", qty: 1, rate: 0 });

            this.recalculateTotals();

            const overlay = document.getElementById("proposalFormDrawer");
            if (overlay) {
                overlay.style.display = "flex";
                setTimeout(() => overlay.classList.add("show"), 10);
            }
        },

        openEditDrawer: async function (id) {
            const res = await apiRequest(`${API_URL}?action=get&id=${encodeURIComponent(id)}`);
            if (!res || !res.success || !res.data) {
                showToast("Error", res?.message || "Proposal not found.", "warning");
                return;
            }

            const proposal = res.data.proposal || res.data;
            this.activeProposalId = proposal.id;
            const propNum = proposal.proposal_number || `PROP-${proposal.id}`;

            document.getElementById("formDrawerTitle").textContent = `Edit Proposal ${propNum}`;
            document.getElementById("formDrawerSubtitle").textContent = "Modify proposal scope, deliverables, or pricing schedule.";
            document.getElementById("btnSubmitProposalForm").textContent = (proposal.status === "Changes Requested") ? "Send Revised Proposal" : "Save Changes";
            document.getElementById("pfProposalId").value = proposal.id;

            // Select matching client
            const clientSelect = document.getElementById("pfClientSelect");
            if (clientSelect) {
                const targetName = proposal.company_name || proposal.contact_name || proposal.client || '';
                let found = false;
                for (let i = 0; i < clientSelect.options.length; i++) {
                    if (clientSelect.options[i].value === targetName ||
                        (proposal.company_id && clientSelect.options[i].getAttribute('data-id') == proposal.company_id && clientSelect.options[i].getAttribute('data-type') === 'company')) {
                        clientSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found && targetName) {
                    clientSelect.value = targetName;
                }
            }

            // Select matching deal
            const dealSelect = document.getElementById("pfDealSelect");
            if (dealSelect) {
                let found = false;
                for (let i = 0; i < dealSelect.options.length; i++) {
                    if (dealSelect.options[i].value === proposal.deal_name ||
                        (proposal.deal_id && dealSelect.options[i].getAttribute('data-id') == proposal.deal_id)) {
                        dealSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found) dealSelect.value = proposal.deal_name || "";
            }

            const editProjSelect = document.getElementById("pfProjectSelect");
            if (editProjSelect) {
                editProjSelect.value = proposal.project_id || "";
            }

            // Prepared by
            const prepSelect = document.getElementById("pfPreparedBySelect");
            if (prepSelect && proposal.prepared_by) {
                prepSelect.value = proposal.prepared_by;
            }

            document.getElementById("pfTitleInput").value = proposal.title || "";
            document.getElementById("pfIssueDate").value = proposal.issue_date || proposal.issueDate || "";
            document.getElementById("pfExpiryDate").value = proposal.expiry_date || proposal.expiryDate || "";
            document.getElementById("pfScopeInput").value = proposal.scope || "";
            document.getElementById("pfPaymentTermsInput").value = proposal.payment_terms || proposal.paymentTerms || "";
            document.getElementById("pfTermsInput").value = proposal.terms || "";
            document.getElementById("pfDiscountInput").value = proposal.discount || 0;

            const tbody = document.getElementById("pfDeliverablesTbody");
            tbody.innerHTML = "";
            const items = proposal.deliverables || proposal.items || res.data.deliverables || [];
            if (items.length > 0) {
                items.forEach(d => this.addDeliverableRow(d));
            } else {
                this.addDeliverableRow({ name: "", desc: "", qty: 1, rate: 0 });
            }

            this.recalculateTotals();

            const overlay = document.getElementById("proposalFormDrawer");
            if (overlay) {
                overlay.style.display = "flex";
                setTimeout(() => overlay.classList.add("show"), 10);
            }
        },

        closeFormDrawer: function () {
            const overlay = document.getElementById("proposalFormDrawer");
            if (overlay) {
                overlay.classList.remove("show");
                setTimeout(() => {
                    overlay.style.display = "none";
                    restorePageInteraction();
                }, 200);
            } else {
                restorePageInteraction();
            }
        },

        addDeliverableRow: function (item = {}) {
            const tbody = document.getElementById("pfDeliverablesTbody");
            if (!tbody) return;

            const name = item.name || "";
            const desc = item.desc || item.description || "";
            const qty = item.qty || 1;
            const rate = item.rate || 0;
            const amount = qty * rate;

            const tr = document.createElement("tr");
            tr.className = "pf-deliv-row";
            tr.innerHTML = `
                <td style="padding: 6px 8px; vertical-align: middle;">
                    <input type="text" class="input-control input-sm pf-d-name" value="${escapeHtml(name)}" placeholder="Deliverable Item Name" style="margin-bottom: 4px; font-weight:600;" required>
                    <input type="text" class="input-control input-sm pf-d-desc" value="${escapeHtml(desc)}" placeholder="Brief scope description..." style="font-size: 11.5px;">
                </td>
                <td style="padding: 6px; text-align: center; vertical-align: middle;">
                    <input type="number" class="input-control input-sm pf-d-qty" value="${qty}" min="0.01" step="any" style="text-align: center; padding: 2px 4px;" oninput="window.adminProposals.recalculateTotals()">
                </td>
                <td style="padding: 6px; text-align: right; vertical-align: middle;">
                    <input type="number" class="input-control input-sm pf-d-rate" value="${rate}" min="0" step="any" style="text-align: right; padding: 2px 6px;" oninput="window.adminProposals.recalculateTotals()">
                </td>
                <td style="padding: 6px 8px; text-align: right; font-weight: 600; color: var(--text-heading); vertical-align: middle;" class="pf-d-amount">
                    ${formatMoney(amount)}
                </td>
                <td style="padding: 6px 8px; text-align: center; vertical-align: middle; width: 44px;">
                    <button type="button" class="btn-deliv-remove" onclick="window.adminProposals.removeDeliverableRow(this)" title="Remove Deliverable" aria-label="Remove Deliverable">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            this.recalculateTotals();
        },

        removeDeliverableRow: function (btn) {
            const row = btn.closest("tr");
            if (row) row.remove();
            this.recalculateTotals();
        },

        recalculateTotals: function () {
            let subtotal = 0;
            document.querySelectorAll("#pfDeliverablesTbody .pf-deliv-row").forEach(tr => {
                const qty = parseFloat(tr.querySelector(".pf-d-qty")?.value || 0);
                const rate = parseFloat(tr.querySelector(".pf-d-rate")?.value || 0);
                const amount = Math.round(qty * rate * 100) / 100;
                const amtEl = tr.querySelector(".pf-d-amount");
                if (amtEl) amtEl.textContent = formatMoney(amount);
                subtotal += amount;
            });

            subtotal = Math.round(subtotal * 100) / 100;
            const discount = Math.max(0, parseFloat(document.getElementById("pfDiscountInput")?.value || 0));
            const taxable = Math.max(0, Math.round((subtotal - discount) * 100) / 100);
            const tax = Math.round(taxable * 0.18 * 100) / 100;
            const total = Math.round((taxable + tax) * 100) / 100;

            const subEl = document.getElementById("pfSubtotalText");
            const taxEl = document.getElementById("pfTaxText");
            const totalEl = document.getElementById("pfTotalPayableText");

            if (subEl) subEl.textContent = formatMoney(subtotal);
            if (taxEl) taxEl.textContent = "+" + formatMoney(tax);
            if (totalEl) totalEl.textContent = formatMoney(total);
        },

        saveForm: function (e) {
            if (e) e.preventDefault();
            this.persistFormSubmission("Draft");
        },

        saveFormDraft: function () {
            this.persistFormSubmission("Draft");
        },

        persistFormSubmission: async function (targetStatus) {
            const id = document.getElementById("pfProposalId").value;
            const title = document.getElementById("pfTitleInput").value.trim();
            if (!title) {
                showToast("Validation Error", "Please enter a proposal title.", "warning");
                return;
            }

            const clientSelect = document.getElementById("pfClientSelect");
            const clientSelectedOption = clientSelect?.options[clientSelect.selectedIndex];
            const clientVal = clientSelect?.value || "";
            const clientType = clientSelectedOption?.getAttribute('data-type');
            const clientId = clientSelectedOption?.getAttribute('data-id');

            let resolvedCompanyId = clientType === 'company' && clientId ? parseInt(clientId, 10) : undefined;
            if (!resolvedCompanyId && clientType === 'contact') {
                const optCompId = clientSelectedOption?.getAttribute('data-company-id');
                if (optCompId) resolvedCompanyId = parseInt(optCompId, 10);
            }

            const dealSelect = document.getElementById("pfDealSelect");
            const dealSelectedOption = dealSelect?.options[dealSelect.selectedIndex];
            const dealVal = dealSelect?.value || "";
            const dealId = dealSelectedOption?.getAttribute('data-id');

            const projSelect = document.getElementById("pfProjectSelect");
            const selectedProjOption = projSelect?.options[projSelect.selectedIndex];
            const projId = projSelect?.value;
            const projName = projId && selectedProjOption ? selectedProjOption.textContent.trim() : undefined;

            const deliverables = [];
            document.querySelectorAll("#pfDeliverablesTbody .pf-deliv-row").forEach(tr => {
                const name = tr.querySelector(".pf-d-name")?.value.trim();
                const desc = tr.querySelector(".pf-d-desc")?.value.trim();
                const qty = parseFloat(tr.querySelector(".pf-d-qty")?.value || 1);
                const rate = parseFloat(tr.querySelector(".pf-d-rate")?.value || 0);
                if (name) {
                    deliverables.push({ name, description: desc, qty, rate });
                }
            });

            const discount = Math.max(0, parseFloat(document.getElementById("pfDiscountInput")?.value || 0));
            const isNew = !id;

            const payload = {
                id: id ? parseInt(id, 10) : undefined,
                title: title,
                company_name: clientType === 'company' ? clientVal : (clientSelectedOption?.getAttribute('data-company') || clientVal),
                company_id: resolvedCompanyId,
                contact_name: clientType === 'contact' ? clientVal : undefined,
                contact_id: clientType === 'contact' && clientId ? parseInt(clientId, 10) : undefined,
                client_email: clientSelectedOption?.getAttribute('data-email') || undefined,
                deal_name: dealVal || undefined,
                deal_id: dealId ? parseInt(dealId, 10) : undefined,
                project_name: projName,
                project_id: projId ? parseInt(projId, 10) : undefined,
                prepared_by: document.getElementById("pfPreparedBySelect").value ? parseInt(document.getElementById("pfPreparedBySelect").value, 10) : undefined,
                issue_date: document.getElementById("pfIssueDate").value,
                expiry_date: document.getElementById("pfExpiryDate").value,
                scope: document.getElementById("pfScopeInput").value.trim(),
                deliverables: deliverables,
                discount: discount,
                payment_terms: document.getElementById("pfPaymentTermsInput").value.trim(),
                terms: document.getElementById("pfTermsInput").value.trim(),
                status: isNew ? targetStatus : undefined
            };

            const action = isNew ? 'create' : 'update';
            const btnSubmit = document.getElementById("btnSubmitProposalForm");
            if (btnSubmit) btnSubmit.disabled = true;

            const res = await apiRequest(`${API_URL}?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (btnSubmit) btnSubmit.disabled = false;

            if (!res || !res.success) {
                showToast("Save Failed", res?.message || "Could not save proposal.", "warning");
                return;
            }

            this.closeFormDrawer();
            await this.loadSummary();
            await this.render();

            const savedNum = res.data?.proposal_number || (isNew ? 'New proposal' : `Proposal #${id}`);
            showToast("Proposal Saved", `${savedNum} saved successfully.`, "success");
        },

        // View Proposal Drawer
        openViewDrawer: async function (id) {
            const res = await apiRequest(`${API_URL}?action=get&id=${encodeURIComponent(id)}`);
            if (!res || !res.success || !res.data) {
                showToast("Error", res?.message || "Could not load proposal details.", "warning");
                return;
            }

            activeProposalData = res.data.proposal || res.data;
            this.activeProposalId = activeProposalData.id;
            const propNum = activeProposalData.proposal_number || `PROP-${activeProposalData.id}`;
            const displayStatus = activeProposalData.display_status || activeProposalData.status;

            const breadcrumb = document.getElementById("vdBreadcrumb");
            const title = document.getElementById("vdTitle");
            const badge = document.getElementById("vdStatusBadge");
            const btnPrimary = document.getElementById("btnVdAction");

            if (breadcrumb) breadcrumb.textContent = `Proposals / ${propNum}`;
            if (title) title.textContent = activeProposalData.title;
            if (badge) {
                badge.textContent = displayStatus;
                badge.className = "client-portal-badge " + getStatusBadgeClass(displayStatus);
            }

            if (btnPrimary) {
                if (activeProposalData.status === "Draft" || activeProposalData.status === "Changes Requested" || activeProposalData.status === "Expired") {
                    btnPrimary.textContent = "Send Proposal";
                    btnPrimary.onclick = () => window.adminProposals.promptSend(activeProposalData.id);
                } else {
                    btnPrimary.textContent = "Edit Proposal";
                    btnPrimary.onclick = () => window.adminProposals.editCurrentProposal();
                }
            }

            this.switchViewTab(this.activeViewTab || "overview");

            const overlay = document.getElementById("proposalViewDrawer");
            if (overlay) {
                overlay.style.display = "flex";
                setTimeout(() => overlay.classList.add("show"), 10);
            }
        },

        closeViewDrawer: function () {
            const overlay = document.getElementById("proposalViewDrawer");
            if (overlay) {
                overlay.classList.remove("show");
                setTimeout(() => {
                    overlay.style.display = "none";
                    restorePageInteraction();
                }, 200);
            } else {
                restorePageInteraction();
            }
        },

        switchViewTab: function (tabName) {
            this.activeViewTab = tabName;
            document.querySelectorAll("#proposalViewDrawer .drawer-tab").forEach(tab => {
                const isActive = (tab.getAttribute("data-tab") === tabName);
                tab.classList.toggle("active", isActive);
            });

            const proposal = activeProposalData;
            const body = document.getElementById("vdBody");
            if (!body || !proposal) return;

            const formattedTotal = proposal.formatted_total || proposal.formattedTotal || formatMoney(proposal.total || proposal.amount || 0, proposal.currency);
            const clientName = escapeHtml(proposal.company_name || proposal.contact_name || proposal.client || '—');
            const prepName = escapeHtml(proposal.prepared_by_name || proposal.preparedBy || '—');
            const dealName = escapeHtml(proposal.deal_name || proposal.deal || 'None');
            const projName = escapeHtml(proposal.project_name || proposal.project || 'None');

            if (tabName === "overview") {
                body.innerHTML = `
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                        <div style="background:var(--bg-hover); padding:12px 14px; border-radius:var(--radius-md); border:1px solid var(--border-card);">
                            <div style="font-size:11px; color:var(--text-muted); font-weight:600;">PROPOSAL AMOUNT</div>
                            <div style="font-size:18px; font-weight:700; color:var(--text-heading); margin-top:2px;">${formattedTotal}</div>
                        </div>
                        <div style="background:var(--bg-hover); padding:12px 14px; border-radius:var(--radius-md); border:1px solid var(--border-card);">
                            <div style="font-size:11px; color:var(--text-muted); font-weight:600;">EXPIRY DATE</div>
                            <div style="font-size:14px; font-weight:700; color:var(--text-heading); margin-top:4px;">${proposal.expiry_date || proposal.expiryDate || '—'}</div>
                        </div>
                    </div>

                    ${proposal.changeRequest ? `
                        <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:var(--radius-md); padding:14px 16px; margin-bottom:20px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <div style="font-size:13px; font-weight:700; color:#B45309;">CLIENT CHANGE REQUEST</div>
                                <span class="client-portal-badge amber" style="font-size:10px;">${escapeHtml(proposal.changeRequest.type || 'Scope')}</span>
                            </div>
                            <p style="font-size:12.5px; color:#92400E; margin:0 0 10px 0; line-height:1.5;">"${escapeHtml(proposal.changeRequest.message)}"</p>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:11px; color:#B45309;">Requested by: ${escapeHtml(proposal.changeRequest.requestedBy || 'Client')} • ${escapeHtml(proposal.changeRequest.date || 'Recently')}</span>
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.adminProposals.editCurrentProposal()">Edit Proposal</button>
                            </div>
                        </div>
                    ` : ''}

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px; font-weight:700; color:var(--text-heading); margin-bottom:6px;">Proposal Scope Summary</h4>
                        <p style="font-size:13px; color:var(--text-secondary); line-height:1.55; margin:0; white-space: pre-line;">${escapeHtml(proposal.scope || 'No scope details recorded.')}</p>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:11px; color:var(--text-muted); font-weight:600; margin:0 0 4px 0;">CLIENT</h4>
                            <div style="font-size:13px; color:var(--text-heading); font-weight:600;">${clientName}</div>
                        </div>
                        <div>
                            <h4 style="font-size:11px; color:var(--text-muted); font-weight:600; margin:0 0 4px 0;">PREPARED BY</h4>
                            <div style="font-size:13px; color:var(--text-heading); font-weight:600;">${prepName}</div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:11px; color:var(--text-muted); font-weight:600; margin:0 0 4px 0;">RELATED DEAL</h4>
                            <div style="font-size:13px; color:var(--text-secondary);">${dealName}</div>
                        </div>
                        <div>
                            <h4 style="font-size:11px; color:var(--text-muted); font-weight:600; margin:0 0 4px 0;">RELATED PROJECT</h4>
                            <div style="font-size:13px; color:var(--text-secondary);">${projName}</div>
                        </div>
                    </div>
                `;
            } else if (tabName === "items") {
                const deliverables = proposal.deliverables || proposal.items || [];
                body.innerHTML = `
                    <div style="border:1px solid var(--border-card); border-radius:var(--radius-md); overflow:hidden; margin-bottom:16px;">
                        <table class="crm-table" style="font-size:12.5px;">
                            <thead>
                                <tr>
                                    <th>Deliverable Item</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th style="text-align:right;">Rate</th>
                                    <th style="text-align:right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${deliverables.length === 0 ? `<tr><td colspan="4" style="text-align:center; padding:12px; color:var(--text-muted);">No deliverables added.</td></tr>` : ''}
                                ${deliverables.map(item => `
                                    <tr>
                                        <td>
                                            <div style="font-weight:600; color:var(--text-heading);">${escapeHtml(item.name)}</div>
                                            <div style="font-size:11.5px; color:var(--text-muted);">${escapeHtml(item.desc || item.description || '')}</div>
                                        </td>
                                        <td style="text-align:center;">${item.qty}</td>
                                        <td style="text-align:right;">${formatMoney(item.rate, proposal.currency)}</td>
                                        <td style="text-align:right; font-weight:600; color:var(--text-heading);">${formatMoney(item.amount, proposal.currency)}</td>
                                    </tr>
                                `).join("")}
                            </tbody>
                        </table>
                    </div>

                    <div style="background:var(--bg-hover); border:1px solid var(--border-card); border-radius:var(--radius-md); padding:14px 16px;">
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; color:var(--text-secondary); margin-bottom:6px;">
                            <span>Subtotal</span>
                            <span>${formatMoney(proposal.subtotal || proposal.amount || 0, proposal.currency)}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; color:var(--text-secondary); margin-bottom:6px;">
                            <span>Discount</span>
                            <span style="color:#059669;">-${formatMoney(proposal.discount || 0, proposal.currency)}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; color:var(--text-secondary); margin-bottom:8px;">
                            <span>Tax (${proposal.tax_rate ? parseFloat(proposal.tax_rate) + '%' : '18%'} GST)</span>
                            <span>+${formatMoney(proposal.tax || 0, proposal.currency)}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:700; color:var(--text-heading); border-top:1px dashed var(--border-card); padding-top:8px;">
                            <span>Total Payable Amount</span>
                            <span style="color:var(--primary);">${formattedTotal}</span>
                        </div>
                    </div>
                `;
            } else if (tabName === "terms") {
                const paymentTermsText = proposal.payment_terms || proposal.paymentTerms;
                const termsText = proposal.terms;
                body.innerHTML = `
                    <div style="margin-bottom:16px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:var(--radius-md); padding:14px 16px;">
                        <div style="font-weight:700; font-size:13px; color:#B45309; margin-bottom:6px;">Payment Terms</div>
                        <p style="margin:0; font-size:12.5px; color:#92400E; line-height:1.5; white-space: pre-line;">${escapeHtml(paymentTermsText || 'Standard Payment Terms apply.')}</p>
                    </div>

                    <div style="background:var(--bg-hover); border:1px solid var(--border-card); border-radius:var(--radius-md); padding:14px 16px;">
                        <div style="font-weight:700; font-size:13px; color:var(--text-heading); margin-bottom:6px;">Terms &amp; Conditions</div>
                        <p style="margin:0; font-size:12.5px; color:var(--text-secondary); line-height:1.5; white-space: pre-line;">${escapeHtml(termsText || 'Standard SLA terms apply.')}</p>
                    </div>
                `;
            } else if (tabName === "activity") {
                const timeline = proposal.timeline || [];
                body.innerHTML = `
                    <div class="admin-timeline">
                        ${timeline.length === 0 ? `<div style="text-align:center; padding:20px; color:var(--text-muted); font-size:13px;">No activity logged yet.</div>` : ''}
                        ${timeline.map(t => `
                            <div class="admin-timeline-item">
                                <div class="admin-timeline-dot"></div>
                                <div>
                                    <div style="font-weight:600; color:var(--text-heading); font-size:13px;">${escapeHtml(t.text || t.title || t.description || 'Activity')}</div>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">${escapeHtml(t.date || t.created_at || 'Recently')}</div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                `;
            }
        },

        editCurrentProposal: function () {
            const id = this.activeProposalId;
            this.closeViewDrawer();
            setTimeout(() => this.openEditDrawer(id), 220);
        },

        openShareModal: function () {
            const proposal = activeProposalData;
            if (!proposal) return;

            const tEl = document.getElementById("shareModalProposalTitle");
            const uEl = document.getElementById("shareProposalUrlInput");
            if (tEl) tEl.textContent = proposal.title;
            const propNum = proposal.proposal_number || `PROP-${proposal.id}`;
            if (uEl) uEl.value = `${window.location.origin}${window.location.pathname.replace('admin/proposals.php', '')}client-proposals.php?prop=${propNum}`;

            const modal = document.getElementById("shareProposalModal");
            if (modal) {
                modal.style.display = "flex";
                modal.classList.add("show");
            }
        },

        closeShareModal: function () {
            const modal = document.getElementById("shareProposalModal");
            if (modal) {
                modal.classList.remove("show");
                modal.style.display = "none";
            }
            restorePageInteraction();
        },

        copyShareLink: function () {
            const uEl = document.getElementById("shareProposalUrlInput");
            if (uEl) {
                uEl.select();
                try {
                    navigator.clipboard.writeText(uEl.value);
                } catch (e) {
                    document.execCommand("copy");
                }
            }
            this.closeShareModal();
            showToast("Proposal Link Copied", "The proposal link has been copied to your clipboard.", "success");
        },

        toggleMoreMenu: function (e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById("proposalMoreMenu");
            if (menu) {
                const isShowing = menu.style.display === "block";
                menu.style.display = isShowing ? "none" : "block";
            }
        },

        closeMoreMenu: function () {
            const menu = document.getElementById("proposalMoreMenu");
            if (menu) menu.style.display = "none";
        },

        duplicateCurrentProposal: async function () {
            if (!this.activeProposalId) return;

            const res = await apiRequest(`${API_URL}?action=duplicate&id=${encodeURIComponent(this.activeProposalId)}`, {
                method: 'POST'
            });

            if (!res || !res.success || !res.data) {
                showToast("Duplicate Failed", res?.message || "Could not duplicate proposal.", "warning");
                return;
            }

            const newId = res.data.id;
            const newNum = res.data.proposal_number;

            this.closeViewDrawer();
            await this.loadSummary();
            await this.render();
            showToast("Proposal Duplicated", `Created new draft ${newNum}.`, "success");
            setTimeout(() => this.openEditDrawer(newId), 250);
        },

        triggerViewPrimaryAction: function () {
            if (!activeProposalData) return;
            if (activeProposalData.status === "Draft" || activeProposalData.status === "Changes Requested" || activeProposalData.status === "Expired") {
                this.promptSend(activeProposalData.id);
            } else {
                this.editCurrentProposal();
            }
        },

        // Send Proposal Modal & Action
        promptSend: async function (id) {
            let proposal = (activeProposalData && activeProposalData.id == id) ? activeProposalData : proposalsList.find(p => p.id == id);
            if (!proposal) {
                const res = await apiRequest(`${API_URL}?action=get&id=${encodeURIComponent(id)}`);
                if (res && res.success && res.data) {
                    proposal = res.data.proposal || res.data;
                }
            }
            if (!proposal) return;

            this.pendingSendId = proposal.id;
            const isChangesRequested = (proposal.status === "Changes Requested");

            const headerTitleEl = document.getElementById("spModalHeaderTitle");
            const headerSubEl = document.getElementById("spModalHeaderSub");
            const confirmBtnEl = document.getElementById("btnConfirmSendProposal");

            const tEl = document.getElementById("spModalTitle");
            const cEl = document.getElementById("spModalClient");
            const aEl = document.getElementById("spModalAmount");
            const eEl = document.getElementById("spModalExpiry");

            if (headerTitleEl) headerTitleEl.textContent = isChangesRequested ? "Send Revised Proposal" : "Send Proposal";
            if (headerSubEl) headerSubEl.textContent = isChangesRequested ? "You're about to send the revised proposal to the client." : "You're about to send this proposal to the client.";
            if (confirmBtnEl) confirmBtnEl.textContent = isChangesRequested ? "Send Revised Proposal" : "Send Proposal";

            if (tEl) tEl.textContent = proposal.title;
            if (cEl) cEl.textContent = proposal.company_name || proposal.contact_name || proposal.client || '—';
            if (aEl) aEl.textContent = proposal.formatted_total || proposal.formattedTotal || formatMoney(proposal.total || proposal.amount || 0, proposal.currency);
            if (eEl) eEl.textContent = proposal.expiry_date || proposal.expiryDate || '—';

            const modal = document.getElementById("sendProposalModal");
            if (modal) {
                modal.style.display = "flex";
                modal.classList.add("show");
            }
        },

        closeSendModal: function () {
            const modal = document.getElementById("sendProposalModal");
            if (modal) {
                modal.classList.remove("show");
                modal.style.display = "none";
            }
            restorePageInteraction();
        },

        confirmSend: async function () {
            if (!this.pendingSendId) return;

            const res = await apiRequest(`${API_URL}?action=send&id=${encodeURIComponent(this.pendingSendId)}`, {
                method: 'POST'
            });

            if (!res || !res.success) {
                showToast("Send Failed", res?.message || "Could not send proposal.", "warning");
                return;
            }

            this.closeSendModal();
            showToast("Proposal Sent", "The proposal has been sent to the client.", "success");

            await this.loadSummary();
            await this.render();

            if (this.activeProposalId === this.pendingSendId) {
                await this.openViewDrawer(this.pendingSendId);
            }
        },

        // Delete Proposal Modal & Action
        promptDeleteCurrent: function () {
            if (!this.activeProposalId) return;
            this.pendingDeleteId = this.activeProposalId;

            const modal = document.getElementById("deleteProposalModal");
            if (modal) {
                modal.style.display = "flex";
                modal.classList.add("show");
            }
        },

        closeDeleteModal: function () {
            const modal = document.getElementById("deleteProposalModal");
            if (modal) {
                modal.classList.remove("show");
                modal.style.display = "none";
            }
            restorePageInteraction();
        },

        confirmDelete: async function () {
            const id = this.pendingDeleteId || this.activeProposalId;
            if (!id) return;

            const res = await apiRequest(`${API_URL}?action=delete&id=${encodeURIComponent(id)}`, {
                method: 'POST'
            });

            if (!res || !res.success) {
                showToast("Delete Failed", res?.message || "Could not delete proposal.", "warning");
                return;
            }

            this.closeDeleteModal();
            this.closeViewDrawer();
            await this.loadSummary();
            await this.render();
            showToast("Proposal Deleted", "Proposal has been deleted successfully.", "success");
        },

        exportProposals: function () {
            window.location.href = `${API_URL}?action=export`;
        },

        setupGlobalEvents: function () {
            document.addEventListener("click", function (e) {
                if (!e.target.closest("#btnProposalFilter") && !e.target.closest("#proposalsFilterPopover")) {
                    const popover = document.getElementById("proposalsFilterPopover");
                    if (popover) popover.style.display = "none";
                }
                if (!e.target.closest("#btnProposalMoreActions") && !e.target.closest("#proposalMoreMenu")) {
                    const menu = document.getElementById("proposalMoreMenu");
                    if (menu) menu.style.display = "none";
                }
            });

            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") {
                    window.adminProposals.closeFormDrawer();
                    window.adminProposals.closeViewDrawer();
                    window.adminProposals.closeFilterPopover();
                    window.adminProposals.closeSendModal();
                    window.adminProposals.closeDeleteModal();
                    window.adminProposals.closeShareModal();
                    window.adminProposals.closeMoreMenu();
                    restorePageInteraction();
                }
            });
        }
    };

    // Auto Init on DOMContentLoaded
    document.addEventListener("DOMContentLoaded", function () {
        window.adminProposals.init();
    });
})();
