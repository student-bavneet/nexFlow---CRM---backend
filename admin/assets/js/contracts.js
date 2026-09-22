/**
 * NexFlow CRM — Contracts Management JavaScript Controller
 * 
 * Database-backed controller for admin/contracts.php.
 * Connects directly to admin/api/contracts.php for multi-tenant MySQL storage,
 * SQL-aggregated KPIs, dynamic Chart.js analytics, drawer tabs (Overview,
 * Parties, Timeline, Renewals, Files, Notes), modal forms, and CSV export.
 */

(function () {
    'use strict';

    const API_URL = 'api/contracts.php';

    let contracts = [];
    let referenceOptions = {
        companies: [],
        contacts: [],
        users: [],
        currency: 'USD ($)',
        next_contract_number: 'CON-001',
        contract_types: [],
        statuses: []
    };

    let summaryData = null;
    let selectedIds = new Set();
    let currentFilterTab = 'All';
    let currentSearch = '';
    let currentSort = 'name-asc';
    let currentSavedView = 'all';
    let activeContractId = null;
    let activeContractData = null;
    let pendingSendContractId = null;
    let chartByTypeInstance = null;
    let chartByValueInstance = null;
    let currentPage = 1;
    const pageSize = 8;
    let totalItems = 0;
    let totalPages = 1;

    let cntNotesList = [];
    let cntNoteSearchQuery = '';

    // Multi-currency formatter using live organization currency
    function formatMoney(amount) {
        const num = parseFloat(amount) || 0;
        const curr = referenceOptions.currency || 'USD ($)';
        const symbol = curr.includes('₹') ? '₹' : (curr.includes('€') ? '€' : (curr.includes('£') ? '£' : '$'));
        return symbol + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function apiRequest(endpoint, options = {}) {
        try {
            const csrfToken = window.adminCsrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const headers = {
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
                ...(options.headers || {})
            };
            const resp = await fetch(endpoint, {
                ...options,
                headers
            });
            const data = await resp.json();
            return data;
        } catch (err) {
            console.error('Contracts API Error:', err);
            return { success: false, message: 'Network or server communication error.' };
        }
    }

    window.contractsApp = {
        init: async function () {
            await this.loadReferenceOptions();
            await this.loadSummary();
            await this.renderTable();
            this.setupEventListeners();
        },

        loadReferenceOptions: async function () {
            const res = await apiRequest(`${API_URL}?action=reference_options`);
            if (res && res.success && res.data) {
                referenceOptions = res.data;
            }
        },

        loadSummary: async function () {
            const res = await apiRequest(`${API_URL}?action=summary`);
            if (res && res.success && res.data) {
                summaryData = res.data;
                this.updateKPIs(res.data.kpi, res.data.currency);
                this.updateTabCounts(res.data.tab_counts);
                this.renderCharts(res.data.charts);
            }
        },

        updateKPIs: function (kpi, curr) {
            if (!kpi) return;
            const kpiActive = document.getElementById('kpiActiveContracts');
            const kpiExpiring = document.getElementById('kpiExpiringSoon');
            const kpiDraft = document.getElementById('kpiDraftContracts');
            const kpiPending = document.getElementById('kpiPendingSignature');
            const kpiTotalVal = document.getElementById('kpiTotalContractValue');
            const badgeCount = document.getElementById('contractsTotalCountBadge');

            if (kpiActive) kpiActive.textContent = kpi.active_contracts || 0;
            if (kpiExpiring) kpiExpiring.textContent = kpi.expiring_soon || 0;
            if (kpiDraft) kpiDraft.textContent = kpi.draft_agreements || 0;
            if (kpiPending) kpiPending.textContent = kpi.pending_signature || 0;
            if (kpiTotalVal) kpiTotalVal.textContent = formatMoney(kpi.total_contract_value || 0);
            if (badgeCount) badgeCount.textContent = `${kpi.total_count || 0} contracts`;
        },

        updateTabCounts: function (tabCounts) {
            if (!tabCounts) return;
            const map = {
                'optCntStatus_All': `All Statuses (${tabCounts['All'] || 0})`,
                'optCntStatus_Draft': `Draft (${tabCounts['Draft'] || 0})`,
                'optCntStatus_Sent': `Sent (${tabCounts['Sent'] || 0})`,
                'optCntStatus_Viewed': `Viewed (${tabCounts['Viewed'] || 0})`,
                'optCntStatus_PendingSignature': `Pending Signature (${tabCounts['Pending Signature'] || 0})`,
                'optCntStatus_ChangesRequested': `Changes Requested (${tabCounts['Changes Requested'] || 0})`,
                'optCntStatus_Signed': `Signed (${tabCounts['Signed'] || 0})`,
                'optCntStatus_Active': `Active (${tabCounts['Active'] || 0})`,
                'optCntStatus_ExpiringSoon': `Expiring Soon (${tabCounts['Expiring Soon'] || 0})`,
                'optCntStatus_Expired': `Expired (${tabCounts['Expired'] || 0})`,
                'optCntStatus_Declined': `Declined (${tabCounts['Declined'] || 0})`,
            };

            for (const [id, text] of Object.entries(map)) {
                const opt = document.getElementById(id);
                if (opt) opt.textContent = text;
            }
        },

        renderCharts: function (chartData) {
            if (typeof Chart === 'undefined') return;

            const labels = (chartData && chartData.labels) ? chartData.labels : ['MSA', 'SOW', 'SLA', 'NDA', 'License'];
            const typeCounts = (chartData && chartData.type_counts) ? chartData.type_counts : [0, 0, 0, 0, 0];
            const typeValues = (chartData && chartData.type_values) ? chartData.type_values : [0, 0, 0, 0, 0];

            const ctx1 = document.getElementById('contractsTypeChart');
            if (ctx1) {
                if (chartByTypeInstance) chartByTypeInstance.destroy();
                chartByTypeInstance = new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Contracts',
                            data: typeCounts,
                            backgroundColor: '#2563EB',
                            borderRadius: 4,
                            barThickness: 24
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1, color: '#64748B' }, grid: { color: '#F1F5F9' } },
                            x: { ticks: { color: '#64748B' }, grid: { display: false } }
                        }
                    }
                });
            }

            const ctx2 = document.getElementById('contractsValueChart');
            if (ctx2) {
                if (chartByValueInstance) chartByValueInstance.destroy();
                chartByValueInstance = new Chart(ctx2, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Value',
                            data: typeValues,
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5, 150, 105, 0.1)',
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#059669',
                            pointRadius: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { color: '#64748B' }, grid: { color: '#F1F5F9' } },
                            x: { ticks: { color: '#64748B' }, grid: { display: false } }
                        }
                    }
                });
            }
        },

        setupEventListeners: function () {
            document.addEventListener('click', function (e) {
                if (!e.target.closest('#contractsRowDropdown') && !e.target.closest('.cnt-row-menu-btn')) {
                    const menu = document.getElementById('contractsRowDropdown');
                    if (menu) menu.classList.remove('show');
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    window.contractsApp.closeSendModal();
                    window.contractsApp.closeModal();
                    window.contractsApp.closeDrawer();
                }
            });

            const editBtn = document.getElementById('cntDrawerEditBtn');
            if (editBtn) {
                editBtn.onclick = function (e) {
                    if (e) e.stopPropagation();
                    window.contractsApp.openEditModal(activeContractId);
                };
            }

            const shareBtn = document.getElementById('cntDrawerShareBtn');
            if (shareBtn) {
                shareBtn.onclick = function (e) {
                    if (e) e.stopPropagation();
                    if (window.NexFlowShare && typeof window.NexFlowShare.openContractShare === 'function') {
                        window.NexFlowShare.openContractShare(activeContractId, this);
                    }
                };
            }
        },

        goToPage: function (p) {
            currentPage = p;
            this.renderTable();
        },

        filterByStatus: function (statusVal) {
            currentFilterTab = statusVal || 'All';
            currentPage = 1;
            const selectEl = document.getElementById('cntStatusSelect');
            if (selectEl && selectEl.value !== currentFilterTab) {
                selectEl.value = currentFilterTab;
            }
            this.renderTable();
        },

        filterByTab: function (tabName) {
            this.filterByStatus(tabName);
        },

        filterByKPI: function (statusCategory) {
            this.filterByStatus(statusCategory);
        },

        handleSearch: function (query) {
            currentSearch = (query || '').trim();
            currentPage = 1;
            this.renderTable();
        },

        handleSort: function (sortVal) {
            currentSort = sortVal;
            currentPage = 1;
            this.renderTable();
        },

        handleSavedView: function (viewKey) {
            currentSavedView = viewKey;
            currentPage = 1;
            if (viewKey === 'all') this.filterByStatus('All');
            else if (viewKey === 'active') this.filterByStatus('Active');
            else if (viewKey === 'expiring') this.filterByStatus('Expiring Soon');
            else if (viewKey === 'high_val') this.filterByStatus('All');

            this.showToast(`Switched to view: "${viewKey.replace('_', ' ').toUpperCase()}"`, 'info');
        },

        renderTable: async function () {
            const tbody = document.getElementById('contractsTableBody');
            if (!tbody) return;

            const popoverType = document.getElementById('filterCntType')?.value || 'All';
            const popoverStatus = document.getElementById('filterCntStatus')?.value || 'All';

            let statusParam = currentFilterTab;
            if (popoverStatus !== 'All') {
                statusParam = popoverStatus;
            }

            const params = new URLSearchParams({
                action: 'list',
                page: currentPage,
                per_page: pageSize,
                status: statusParam,
                type: popoverType,
                saved_view: currentSavedView,
                search: currentSearch,
                sort: currentSort
            });

            const res = await apiRequest(`${API_URL}?${params.toString()}`);
            if (!res || !res.success) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:36px;color:#DC2626;">Failed to load contracts.</td></tr>`;
                return;
            }

            contracts = res.data.items || [];
            totalItems = res.data.pagination.total;
            totalPages = res.data.pagination.total_pages;
            currentPage = res.data.pagination.page;

            const startIndex = totalItems > 0 ? (currentPage - 1) * pageSize : 0;
            const pagingRange = document.getElementById('pagingRange');
            const pagingTotal = document.getElementById('pagingTotal');
            const controls = document.getElementById('paginationControls');

            if (pagingRange) pagingRange.textContent = totalItems > 0 ? `${startIndex + 1}–${Math.min(startIndex + pageSize, totalItems)}` : '0';
            if (pagingTotal) pagingTotal.textContent = totalItems;

            const container = document.querySelector('.pagination-container');
            if (container) {
                container.style.display = totalItems > pageSize ? 'flex' : (totalItems > 0 ? 'flex' : 'none');
            }

            if (controls) {
                if (totalItems === 0) {
                    controls.innerHTML = '';
                } else {
                    let html = `
                        <button type="button" class="pagination-btn" id="prevPageBtn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.contractsApp.goToPage(${currentPage - 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                    `;
                    for (let p = 1; p <= totalPages; p++) {
                        html += `<button type="button" class="pagination-btn ${p === currentPage ? 'active' : ''}" onclick="window.contractsApp.goToPage(${p})">${p}</button>`;
                    }
                    html += `
                        <button type="button" class="pagination-btn" id="nextPageBtn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.contractsApp.goToPage(${currentPage + 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `;
                    controls.innerHTML = html;
                }
            }

            if (contracts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" style="text-align:center;padding:48px 20px;color:var(--text-secondary);">
                            <div style="font-weight:600;font-size:15px;color:var(--text-heading);margin-bottom:6px;">No contracts found</div>
                            <p style="margin:0;font-size:13px;">No customer agreements recorded yet. Click "New Contract" to get started.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = contracts.map(c => {
                const computed = c.display_status || c.status;
                const sigLabel = c.signature_status || 'Not Sent';

                const statusBadgeStyle = 
                    computed === 'Active' || computed === 'Signed' ? 'background:#ECFDF5;color:#047857;' :
                    computed === 'Changes Requested' ? 'background:#FFFBEB;color:#B45309;border:1px solid #FDE68A;' :
                    computed === 'Pending Signature' ? 'background:#FFF7ED;color:#C2410C;' :
                    computed === 'Sent' || computed === 'Viewed' || computed === 'Revised' ? 'background:#EFF6FF;color:#1D4ED8;' :
                    computed === 'Draft' ? 'background:#F1F5F9;color:#475569;' :
                    'background:#FEF2F2;color:#DC2626;';

                const sigBadgeStyle = sigLabel === 'Signed' ? 'background:#ECFDF5;color:#047857;' :
                                      sigLabel === 'Sent' ? 'background:#EFF6FF;color:#1D4ED8;' :
                                      sigLabel === 'Declined' ? 'background:#FEF2F2;color:#DC2626;' : 'background:#F1F5F9;color:#475569;';

                const companyDisplay = c.resolved_company_name || c.company_name || '—';
                const contactDisplay = c.resolved_contact_name || c.contact_name || '—';
                const ownerDisplay   = c.owner_name || 'Unassigned';
                const initials = ownerDisplay.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

                return `
                    <tr data-cnt-id="${c.id}">
                        <td>
                            <span style="font-size:11px;font-weight:700;color:var(--primary);">${escapeHtml(c.contract_number)}</span>
                            <div style="font-weight:600;color:var(--text-heading);font-size:13.5px;cursor:pointer;margin-top:2px;" onclick="window.contractsApp.openDrawer('${c.id}')">
                                ${escapeHtml(c.title)}
                            </div>
                            <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px;">
                                ${escapeHtml(c.reference_number || c.contract_number)}
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;color:var(--text-heading);">${escapeHtml(companyDisplay)}</div>
                            <div style="font-size:11px;color:var(--text-secondary);">${escapeHtml(contactDisplay)}</div>
                        </td>
                        <td>
                            <span style="font-size:12px;font-weight:500;color:#334155;">${escapeHtml(c.type)}</span>
                        </td>
                        <td style="font-weight:700;color:var(--text-heading);">
                            ${formatMoney(c.value)}
                        </td>
                        <td>${c.start_date || '—'}</td>
                        <td style="${computed === 'Expiring Soon' ? 'color:#C2410C;font-weight:600;' : ''}">${c.end_date || '—'}</td>
                        <td>
                            <span class="badge-contract" style="display:inline-block;padding:3px 8px;border-radius:12px;font-size:11.5px;font-weight:600;${statusBadgeStyle}">● ${computed}</span>
                        </td>
                        <td>
                            <span class="badge-sig" style="display:inline-block;padding:3px 8px;border-radius:12px;font-size:11.5px;font-weight:600;${sigBadgeStyle}">${sigLabel}</span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="avatar avatar-xs" style="background-color:#7C3AED;font-size:9px;color:#FFF;">${initials}</div>
                                <span>${escapeHtml(ownerDisplay)}</span>
                            </div>
                        </td>
                        <td style="font-size:11.5px;color:var(--text-secondary);">${(c.updated_at || c.created_at || '').substring(0, 10)}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.contractsApp.openDrawer('${c.id}')">View</button>
                                ${c.status === 'Draft' ? `
                                    <button type="button" class="btn btn-primary btn-xs" onclick="window.contractsApp.openSendModal('${c.id}')">Send</button>
                                ` : ''}
                                ${c.status === 'Changes Requested' ? `
                                    <button type="button" class="btn btn-secondary btn-xs" style="color:#B45309;border-color:#FDE68A;background:#FFFBEB;" onclick="window.contractsApp.openEditModal('${c.id}')">Revise</button>
                                    <button type="button" class="btn btn-primary btn-xs" onclick="window.contractsApp.openSendModal('${c.id}')">Send Revised</button>
                                ` : ''}
                                ${c.status === 'Revised' ? `
                                    <button type="button" class="btn btn-primary btn-xs" onclick="window.contractsApp.openSendModal('${c.id}')">Send Revised</button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        },

        openSendModal: async function (contractId) {
            let cnt = contracts.find(c => String(c.id) === String(contractId));
            if (!cnt) {
                const res = await apiRequest(`${API_URL}?action=get&id=${contractId}`);
                if (res && res.success && res.data) cnt = res.data.contract;
            }
            if (!cnt) return;

            pendingSendContractId = cnt.id;
            const modal = document.getElementById('adminSendContractModal');
            const titleEl = document.getElementById('ascModalTitle');
            const subEl = document.getElementById('ascModalSub');
            const cntTitle = document.getElementById('ascCntTitle');
            const cntComp = document.getElementById('ascCntCompany');
            const cntVal = document.getElementById('ascCntValue');
            const btnConfirm = document.getElementById('btnConfirmSendContract');

            const isRevised = (cnt.status === 'Changes Requested' || cnt.status === 'Revised');

            if (titleEl) titleEl.textContent = isRevised ? 'Send Revised Contract?' : 'Send Contract?';
            if (subEl) subEl.textContent = isRevised ? 'Are you sure you want to send the revised contract to the client?' : 'Are you sure you want to send this contract to the client?';
            if (btnConfirm) btnConfirm.textContent = isRevised ? 'Send Revised Contract' : 'Send Contract';

            if (cntTitle) cntTitle.textContent = `${cnt.title} (${cnt.contract_number})`;
            if (cntComp) cntComp.textContent = cnt.resolved_company_name || cnt.company_name || '—';
            if (cntVal) cntVal.textContent = formatMoney(cnt.value);

            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('show');
            }
        },

        closeSendModal: function () {
            const modal = document.getElementById('adminSendContractModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }
            document.body.style.overflow = '';
        },

        confirmSendContract: async function () {
            if (!pendingSendContractId) return;
            const res = await apiRequest(`${API_URL}?action=send`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: pendingSendContractId })
            });

            if (res && res.success) {
                this.closeSendModal();
                this.showToast(res.message || 'Contract sent successfully.', 'success');
                await this.loadSummary();
                await this.renderTable();
                if (activeContractId && String(activeContractId) === String(pendingSendContractId)) {
                    this.openDrawer(pendingSendContractId);
                }
            } else {
                this.showToast(res ? res.message : 'Failed to send contract.', 'danger');
            }
        },

        // Details Drawer
        openDrawer: async function (contractId) {
            activeContractId = contractId;
            const drawer = document.getElementById('contractsDrawer');
            if (!drawer) return;

            drawer.classList.add('show');
            document.body.style.overflow = 'hidden';

            const res = await apiRequest(`${API_URL}?action=get&id=${contractId}`);
            if (!res || !res.success) {
                this.showToast('Could not load contract details.', 'danger');
                this.closeDrawer();
                return;
            }

            activeContractData = res.data;
            const cnt = res.data.contract;

            const sub = document.getElementById('cntDrawerSubtitle');
            const title = document.getElementById('cntDrawerTitle');
            const badge = document.getElementById('cntDrawerBadge');

            if (sub) sub.textContent = `${cnt.resolved_company_name || cnt.company_name || '—'} • ${cnt.contract_number}`;
            if (title) title.textContent = cnt.title;
            if (badge) {
                const compStatus = cnt.display_status || cnt.status;
                badge.textContent = `● ${compStatus}`;
                badge.className = `badge-contract badge-${compStatus.toLowerCase().replace(/\s+/g, '-')}`;
            }

            this.switchDrawerTab('overview');
        },

        closeDrawer: function () {
            const drawer = document.getElementById('contractsDrawer');
            if (drawer) drawer.classList.remove('show');
            document.body.style.overflow = '';
            activeContractId = null;
            activeContractData = null;
            const moreMenu = document.getElementById('cntDrawerMoreMenu');
            if (moreMenu) moreMenu.style.display = 'none';
        },

        toggleDrawerMoreMenu: function (e) {
            if (e && e.stopPropagation) e.stopPropagation();
            const menu = document.getElementById('cntDrawerMoreMenu');
            if (!menu || !activeContractData) return;

            const cnt = activeContractData.contract;
            menu.innerHTML = `
                <button type="button" class="dropdown-item" onclick="window.contractsApp.duplicateContract(${cnt.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Duplicate Contract
                </button>
                <button type="button" class="dropdown-item danger" onclick="window.contractsApp.openDeleteModal(${cnt.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete Contract
                </button>
            `;
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        },

        duplicateContract: async function (cntId) {
            const menu = document.getElementById('cntDrawerMoreMenu');
            if (menu) menu.style.display = 'none';

            const res = await apiRequest(`${API_URL}?action=duplicate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: cntId })
            });

            if (res && res.success) {
                this.showToast(res.message || 'Contract duplicated.', 'success');
                await this.loadSummary();
                await this.renderTable();
                if (res.data && res.data.id) {
                    this.openDrawer(res.data.id);
                }
            } else {
                this.showToast(res ? res.message : 'Duplication failed.', 'danger');
            }
        },

        openDeleteModal: function (cntId) {
            const menu = document.getElementById('cntDrawerMoreMenu');
            if (menu) menu.style.display = 'none';
            if (!activeContractData) return;

            const cnt = activeContractData.contract;
            const html = `
                <div class="contracts-modal-header">
                    <h3 class="contracts-modal-title">Delete Contract</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contractsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="contracts-modal-body">
                    <p style="font-size:13.5px;color:#475569;margin:0 0 12px 0;">Are you sure you want to delete contract <strong>${escapeHtml(cnt.contract_number)} (${escapeHtml(cnt.title)})</strong>? This action cannot be undone.</p>
                </div>
                <div class="contracts-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="window.contractsApp.confirmDelete(${cnt.id})">Delete Contract</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmDelete: async function (cntId) {
            const res = await apiRequest(`${API_URL}?action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: cntId })
            });

            if (res && res.success) {
                this.closeModal();
                this.closeDrawer();
                this.showToast(res.message || 'Contract deleted.', 'warning');
                await this.loadSummary();
                await this.renderTable();
            } else {
                this.showToast(res ? res.message : 'Failed to delete contract.', 'danger');
            }
        },

        switchDrawerTab: function (tabName) {
            document.querySelectorAll('.contracts-drawer-tab').forEach(t => {
                t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
            });

            const body = document.getElementById('cntDrawerBody');
            if (!body || !activeContractData) return;

            const cnt = activeContractData.contract;
            const diffDays = cnt.days_remaining;
            const formattedVal = formatMoney(cnt.value);
            const compStatus = cnt.display_status || cnt.status;

            if (tabName === 'overview') {
                body.innerHTML = `
                    ${cnt.status === 'Changes Requested' ? `
                        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px;margin-bottom:18px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <div style="font-size:12px;font-weight:700;color:#B45309;letter-spacing:0.05em;text-transform:uppercase;">CLIENT CHANGE REQUEST</div>
                                <span class="badge-contract" style="background:#FDE68A;color:#92400E;font-size:10px;padding:2px 6px;">Open</span>
                            </div>
                            <p style="font-size:12.5px;color:#92400E;margin:0 0 10px 0;line-height:1.5;">"${escapeHtml(cnt.change_request_text || 'Please review contract terms.')}"</p>
                            <div style="font-size:11px;color:#B45309;margin-bottom:12px;">
                                Requested by: <strong>${escapeHtml(cnt.change_request_contact || cnt.resolved_contact_name || 'Client')}</strong> • ${(cnt.change_request_date || '').substring(0, 10) || 'Recently'}
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="button" class="btn btn-secondary btn-xs" style="background:#FFF;border-color:#FDE68A;color:#B45309;" onclick="window.contractsApp.openEditModal(${cnt.id})">Revise Contract</button>
                                <button type="button" class="btn btn-primary btn-xs" onclick="window.contractsApp.openSendModal(${cnt.id})">Send Revised Contract</button>
                            </div>
                        </div>
                    ` : ''}

                    <!-- Summary Mini Cards -->
                    <div class="exp-kpi-grid">
                        <div class="exp-kpi-card">
                            <div class="exp-kpi-label">Contract Value</div>
                            <div class="exp-kpi-value">${formattedVal}</div>
                        </div>
                        <div class="exp-kpi-card">
                            <div class="exp-kpi-label">Days Remaining</div>
                            <div class="exp-kpi-value" style="color:${diffDays === null ? '#64748B' : (diffDays < 0 ? '#DC2626' : (diffDays <= 30 ? '#C2410C' : '#059669'))}">
                                ${diffDays === null ? '—' : (diffDays > 0 ? diffDays + ' Days' : (diffDays < 0 ? 'Expired (' + Math.abs(diffDays) + 'd ago)' : 'Expires Today'))}
                            </div>
                        </div>
                    </div>

                    <!-- Contract Details Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Contract Details</div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Contract ID</span>
                            <span class="drawer-info-val monospace">${escapeHtml(cnt.contract_number)}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Contract Type</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.type)}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Effective Period</span>
                            <span class="drawer-info-val">${cnt.start_date || '—'} to ${cnt.end_date || '—'}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">NexFlow Account Owner</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.owner_name || 'Unassigned')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Status</span>
                            <span class="drawer-info-val">${compStatus}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Contract Reference</span>
                            <span class="drawer-info-val monospace">${escapeHtml(cnt.reference_number || cnt.contract_number)}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Company</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.resolved_company_name || cnt.company_name || '—')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Primary Contact</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.resolved_contact_name || cnt.contact_name || '—')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Renewal Type</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.renewal_type || 'Auto-renew')}</span>
                        </div>
                    </div>

                    <!-- Agreement Overview Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Agreement Overview</div>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;white-space:pre-wrap;">${escapeHtml(cnt.overview || 'Standard master services agreement and deliverables.')}</p>
                    </div>

                    <!-- Key Terms & SLAs Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Key Terms &amp; SLAs</div>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;white-space:pre-wrap;">${escapeHtml(cnt.terms || cnt.payment_terms || 'Standard terms apply.')}</p>
                    </div>
                `;

            } else if (tabName === 'parties') {
                body.innerHTML = `
                    <!-- Customer / Client Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Customer / Client</div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Company / Organization</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.resolved_company_name || cnt.company_name || '—')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Primary Contact</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.resolved_contact_name || cnt.contact_name || '—')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Role</span>
                            <span class="drawer-info-val">Customer Contact / Signatory</span>
                        </div>
                        ${cnt.client_email || cnt.contact_email ? `
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Email</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.client_email || cnt.contact_email)}</span>
                        </div>` : ''}
                    </div>

                    <!-- Internal NexFlow Owner Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Internal NexFlow Owner</div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Owner Name</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.owner_name || 'Unassigned')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Role</span>
                            <span class="drawer-info-val">Account Lead</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Company</span>
                            <span class="drawer-info-val">NexFlow CRM</span>
                        </div>
                    </div>

                    <!-- Signature Participants Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Signature Participants</div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Customer Signatory</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.resolved_contact_name || cnt.contact_name || 'Client Contact')} (${cnt.is_client_signed ? 'Signed' : (cnt.status === 'Pending Signature' ? 'Pending Signature' : 'Not Signed')})</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Internal Owner</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.owner_name || 'Owner')} (${cnt.is_client_signed ? 'Signed &amp; Authorized' : 'Pending Client Signature'})</span>
                        </div>
                    </div>
                `;

            } else if (tabName === 'timeline') {
                const activities = activeContractData.timeline || [];

                if (activities.length === 0) {
                    body.innerHTML = `
                        <div class="exp-empty-state">
                            <div class="exp-empty-icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <p class="exp-empty-title">No activity recorded yet</p>
                            <p class="exp-empty-desc">Contract creations, status updates, client actions, and notes will appear here.</p>
                        </div>
                    `;
                } else {
                    const timelineItems = activities.map(a => `
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-title">${escapeHtml(a.title || 'Contract Activity')}</span>
                                    <span class="timeline-time">${(a.created_at || '').substring(0, 16)}</span>
                                </div>
                                <div class="timeline-desc">${escapeHtml(a.description || '')}</div>
                            </div>
                        </div>
                    `).join('');

                    body.innerHTML = `
                        <div class="timeline-list">
                            ${timelineItems}
                        </div>
                    `;
                }

            } else if (tabName === 'renewals') {
                body.innerHTML = `
                    <!-- Renewal Schedule Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Renewal Schedule</div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Renewal Method</span>
                            <span class="drawer-info-val">${escapeHtml(cnt.renewal_type || 'Auto-renew')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Renewal Notice Period</span>
                            <span class="drawer-info-val">${cnt.notice_days !== undefined ? cnt.notice_days : 30} Days Prior</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Next Renewal Date</span>
                            <span class="drawer-info-val">${cnt.next_renewal_date || cnt.end_date || '—'}</span>
                        </div>
                    </div>

                    <!-- Current Contract Period Summary Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Contract Period Summary</div>
                        <div class="exp-kpi-grid" style="margin-bottom:0;">
                            <div class="exp-kpi-card" style="padding:12px;">
                                <div class="exp-kpi-label">Current Contract</div>
                                <div style="font-size:13.5px;font-weight:700;color:#0F172A;margin-top:4px;">${compStatus}</div>
                            </div>
                            <div class="exp-kpi-card" style="padding:12px;">
                                <div class="exp-kpi-label">Start Date</div>
                                <div style="font-size:13.5px;font-weight:700;color:#0F172A;margin-top:4px;">${cnt.start_date || '—'}</div>
                            </div>
                            <div class="exp-kpi-card" style="padding:12px;">
                                <div class="exp-kpi-label">End Date</div>
                                <div style="font-size:13.5px;font-weight:700;color:#0F172A;margin-top:4px;">${cnt.end_date || '—'}</div>
                            </div>
                            <div class="exp-kpi-card" style="padding:12px;">
                                <div class="exp-kpi-label">Next Renewal</div>
                                <div style="font-size:13.5px;font-weight:700;color:#2563EB;margin-top:4px;">${cnt.next_renewal_date || cnt.end_date || '—'}</div>
                            </div>
                        </div>
                    </div>
                `;

            } else if (tabName === 'files') {
                const fileList = activeContractData.files || [];

                body.innerHTML = `
                    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
                        <input type="file" id="cntFileUploadInput" style="display:none;" onchange="window.contractsApp.handleFileUpload(event, ${cnt.id})">
                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('cntFileUploadInput').click()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload Document
                        </button>
                    </div>
                    ${fileList.length === 0 ? `
                        <div class="exp-empty-state" style="text-align:center;padding:36px 16px;">
                            <div class="exp-empty-icon" style="width:44px;height:44px;border-radius:50%;background:#F1F5F9;color:#94A3B8;display:flex;align-items:center;justify-content:center;margin:0 auto 12px auto;">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <p class="exp-empty-title" style="font-size:14px;font-weight:600;color:#0F172A;margin-bottom:4px;">No files attached</p>
                            <p class="exp-empty-desc" style="font-size:12.5px;color:#94A3B8;margin:0;">Upload the executed PDF or agreement documents.</p>
                        </div>
                    ` : `
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            ${fileList.map(f => {
                                const ext = (f.file_name.split('.').pop() || 'FILE').toUpperCase();
                                return `
                                    <div class="exp-receipt-file-card">
                                        <div class="exp-receipt-icon">
                                            <svg width="22" height="22" fill="none" stroke="#2563EB" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        </div>
                                        <div class="exp-receipt-info">
                                            <div class="exp-receipt-filename">${escapeHtml(f.file_name)}</div>
                                            <div class="exp-receipt-subtext">${ext} &bull; ${f.file_size || ''}</div>
                                        </div>
                                        <div class="exp-receipt-actions">
                                            <a href="${API_URL}?action=download_file&file_id=${f.id}&contract_id=${cnt.id}" class="btn btn-primary btn-sm" download>Download</a>
                                            <button type="button" class="btn btn-ghost btn-sm" style="color:#DC2626;" onclick="window.contractsApp.deleteFile(${f.id}, ${cnt.id})">Delete</button>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `}
                `;

            } else if (tabName === 'notes') {
                cntNotesList = activeContractData.notes || [];

                body.innerHTML = `
                    <!-- Search & Add Note Toolbar -->
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                        <div class="input-field-container" style="position:relative;flex:1;min-width:200px;">
                            <svg width="14" height="14" fill="none" stroke="#94A3B8" stroke-width="2" viewBox="0 0 24 24" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="search" class="input-control input-sm" id="cntNoteSearchInput" placeholder="Search notes..." value="${escapeHtml(cntNoteSearchQuery)}" style="padding-left:32px;width:100%;" oninput="window.contractsApp.onNoteSearchInput(this.value)">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.contractsApp.openAddNoteModal()">
                            + Add Note
                        </button>
                    </div>

                    <div id="cntNotesResults">
                        ${this.renderNotesListHtml()}
                    </div>
                `;
            }
        },

        renderNotesListHtml: function () {
            const query = cntNoteSearchQuery.toLowerCase().trim();
            const filtered = cntNotesList.filter(n => !query || (n.content && n.content.toLowerCase().includes(query)) || (n.author_name && n.author_name.toLowerCase().includes(query)));

            const pinned = filtered.filter(n => n.is_pinned == 1);
            const recent = filtered.filter(n => n.is_pinned != 1);

            if (filtered.length === 0) {
                if (query) {
                    return `
                        <div class="exp-empty-state" style="text-align:center;padding:32px 16px;">
                            <p class="exp-empty-title" style="font-size:14px;font-weight:600;color:#0F172A;margin-bottom:4px;">No notes found</p>
                            <p class="exp-empty-desc" style="font-size:12.5px;color:#94A3B8;margin:0;">Try a different search term.</p>
                        </div>
                    `;
                }
                return `
                    <div class="exp-empty-state" style="text-align:center;padding:48px 20px;">
                        <div class="exp-empty-icon" style="width:44px;height:44px;border-radius:50%;background:#F1F5F9;color:#94A3B8;display:flex;align-items:center;justify-content:center;margin:0 auto 12px auto;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <p class="exp-empty-title" style="font-size:15px;font-weight:600;color:#0F172A;margin:0 0 6px 0;">No notes yet</p>
                        <p class="exp-empty-desc" style="font-size:13px;color:#94A3B8;margin:0;line-height:1.5;">Add an internal note to keep track of important contract details.</p>
                    </div>
                `;
            }

            let html = '';
            if (pinned.length > 0) {
                html += `
                    <div class="task-group-title" style="color:#2563EB;margin-top:0;">📌 Pinned Notes</div>
                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                        ${pinned.map(n => this.renderNoteCardHtml(n)).join('')}
                    </div>
                `;
            }
            if (recent.length > 0) {
                html += `
                    <div class="task-group-title" style="${pinned.length > 0 ? '' : 'margin-top:0;'}">Recent Notes</div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${recent.map(n => this.renderNoteCardHtml(n)).join('')}
                    </div>
                `;
            }
            return html;
        },

        renderNoteCardHtml: function (n) {
            return `
                <div class="note-card ${n.is_pinned == 1 ? 'pinned' : ''}">
                    <div class="note-card-header">
                        <span class="note-author">${escapeHtml(n.author_name || 'Team Member')}</span>
                        <span class="note-date">${(n.created_at || '').substring(0, 16)}</span>
                    </div>
                    <div class="note-content">${escapeHtml(n.content)}</div>
                    <div class="note-actions">
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:${n.is_pinned == 1 ? '#2563EB' : '#94A3B8'};" title="${n.is_pinned == 1 ? 'Unpin' : 'Pin'}" onclick="window.contractsApp.toggleNotePin(${n.id})">
                            📌 ${n.is_pinned == 1 ? 'Pinned' : 'Pin'}
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:#DC2626;" title="Delete" onclick="window.contractsApp.deleteNote(${n.id})">Delete</button>
                    </div>
                </div>
            `;
        },

        onNoteSearchInput: function (val) {
            cntNoteSearchQuery = val;
            const resultsEl = document.getElementById('cntNotesResults');
            if (resultsEl) {
                resultsEl.innerHTML = this.renderNotesListHtml();
            }
        },

        openAddNoteModal: function () {
            if (!activeContractId) return;
            const html = `
                <div class="contracts-modal-header">
                    <h3 class="contracts-modal-title">Add Internal Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contractsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contractsApp.submitNoteForm(event)">
                    <div class="contracts-modal-body">
                        <div style="margin-bottom:14px;">
                            <label class="form-label">Note Content *</label>
                            <textarea class="input-control" id="cntModalNoteContent" rows="4" style="height:96px;resize:none;font-size:13px;" placeholder="Add internal note on contract terms, renewals, SLAs..." required></textarea>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" id="cntModalNotePin" style="width:16px;height:16px;cursor:pointer;">
                            <label for="cntModalNotePin" style="font-size:13px;color:#0F172A;cursor:pointer;user-select:none;">Pin note to top</label>
                        </div>
                    </div>
                    <div class="contracts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Note</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitNoteForm: async function (e) {
            e.preventDefault();
            if (!activeContractId) return;

            const content = document.getElementById('cntModalNoteContent').value.trim();
            const isPinned = document.getElementById('cntModalNotePin').checked ? 1 : 0;

            if (!content) return;

            const res = await apiRequest(`${API_URL}?action=add_note`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    contract_id: activeContractId,
                    content: content,
                    is_pinned: isPinned
                })
            });

            if (res && res.success) {
                this.closeModal();
                this.showToast('Note saved successfully.', 'success');
                // Reload contract details to refresh notes
                await this.openDrawer(activeContractId);
                this.switchDrawerTab('notes');
            } else {
                this.showToast(res ? res.message : 'Failed to save note.', 'danger');
            }
        },

        toggleNotePin: async function (noteId) {
            const res = await apiRequest(`${API_URL}?action=toggle_pin_note`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId })
            });

            if (res && res.success) {
                await this.openDrawer(activeContractId);
                this.switchDrawerTab('notes');
            } else {
                this.showToast(res ? res.message : 'Failed to update pin.', 'danger');
            }
        },

        deleteNote: async function (noteId) {
            if (!confirm('Are you sure you want to delete this note?')) return;
            const res = await apiRequest(`${API_URL}?action=delete_note`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId })
            });

            if (res && res.success) {
                this.showToast('Note deleted.', 'warning');
                await this.openDrawer(activeContractId);
                this.switchDrawerTab('notes');
            } else {
                this.showToast(res ? res.message : 'Failed to delete note.', 'danger');
            }
        },

        handleFileUpload: async function (e, contractId) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('contract_id', contractId);
            formData.append('file', file);

            const res = await apiRequest(`${API_URL}?action=upload_file`, {
                method: 'POST',
                body: formData
            });

            if (res && res.success) {
                this.showToast('File uploaded successfully.', 'success');
                await this.openDrawer(contractId);
                this.switchDrawerTab('files');
            } else {
                this.showToast(res ? res.message : 'Failed to upload file.', 'danger');
            }
            e.target.value = '';
        },

        deleteFile: async function (fileId, contractId) {
            if (!confirm('Are you sure you want to delete this document?')) return;
            const res = await apiRequest(`${API_URL}?action=delete_file`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file_id: fileId })
            });

            if (res && res.success) {
                this.showToast('File deleted.', 'warning');
                await this.openDrawer(contractId);
                this.switchDrawerTab('files');
            } else {
                this.showToast(res ? res.message : 'Failed to delete file.', 'danger');
            }
        },

        openModal: function (html) {
            const modal = document.getElementById('cntActionModal');
            const card = document.getElementById('cntActionModalCard');
            if (modal && card) {
                card.innerHTML = html;
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        },

        closeModal: function () {
            const modal = document.getElementById('cntActionModal');
            if (modal) modal.classList.remove('show');
            document.body.style.overflow = '';
        },

        openNewContractModal: function () {
            const today = new Date().toISOString().split('T')[0];
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            const nextYearStr = nextYear.toISOString().split('T')[0];

            const companies = referenceOptions.companies || [];
            const contacts = referenceOptions.contacts || [];
            const users = referenceOptions.users || [];
            const types = referenceOptions.contract_types || [
                'Master Services Agreement (MSA)',
                'Statement of Work (SOW)',
                'Service Level Agreement (SLA)',
                'Non-Disclosure Agreement (NDA)',
                'Software License Agreement',
                'Development Agreement'
            ];

            const html = `
                <div class="contracts-modal-header" style="padding:16px 20px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 class="contracts-modal-title" style="font-size:16px;font-weight:700;color:#0F172A;margin:0;">Create New Contract</h3>
                        <p style="font-size:12px;color:#64748B;margin:2px 0 0 0;">Fill in the contract details and terms.</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contractsApp.closeModal()" aria-label="Close modal">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="cntCreateForm" onsubmit="window.contractsApp.submitNewContract(event)">
                    <div class="contracts-modal-body" style="padding:20px;max-height:75vh;overflow-y:auto;">
                        <!-- BASIC INFORMATION -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">BASIC INFORMATION</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Contract Name *</label>
                                <input type="text" class="input-control input-sm" id="newCntTitle" placeholder="e.g. Enterprise MSA Agreement" required style="width:100%;">
                            </div>
                            <div>
                                <label class="form-label">Contract ID (Read-only)</label>
                                <input type="text" class="input-control input-sm" id="newCntId" value="${escapeHtml(referenceOptions.next_contract_number || 'CON-001')}" readonly disabled style="width:100%;background:#F1F5F9;color:#64748B;cursor:not-allowed;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Contract Reference</label>
                                <input type="text" class="input-control input-sm" id="newCntRef" placeholder="e.g. SLA-2026-MAINT" style="width:100%;">
                            </div>
                            <div>
                                <label class="form-label">Company / Client *</label>
                                <input type="text" class="input-control input-sm" id="newCntCompany" list="newCntCompanyList" placeholder="Select or enter company..." required style="width:100%;">
                                <datalist id="newCntCompanyList">
                                    ${companies.map(c => `<option value="${escapeHtml(c.name)}">`).join('')}
                                </datalist>
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label class="form-label">Primary Contact</label>
                            <input type="text" class="input-control input-sm" id="newCntContact" list="newCntContactList" placeholder="Select or enter contact..." style="width:100%;">
                            <datalist id="newCntContactList">
                                ${contacts.map(ct => `<option value="${escapeHtml(ct.first_name + ' ' + (ct.last_name || ''))}">`).join('')}
                            </datalist>
                        </div>

                        <!-- CONTRACT DETAILS -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">CONTRACT DETAILS</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Contract Type *</label>
                                <select class="input-control input-sm" id="newCntType" required style="width:100%;">
                                    ${types.map(t => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('')}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Contract Value *</label>
                                <input type="number" step="0.01" min="0" class="input-control input-sm" id="newCntValue" placeholder="0.00" required style="width:100%;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="input-control input-sm" id="newCntStartDate" value="${today}" required style="width:100%;">
                            </div>
                            <div>
                                <label class="form-label">End Date *</label>
                                <input type="date" class="input-control input-sm" id="newCntEndDate" value="${nextYearStr}" required style="width:100%;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                            <div>
                                <label class="form-label">Status *</label>
                                <select class="input-control input-sm" id="newCntStatus" required style="width:100%;">
                                    <option value="Draft" selected>Draft</option>
                                    <option value="Sent">Sent</option>
                                    <option value="Viewed">Viewed</option>
                                    <option value="Pending Signature">Pending Signature</option>
                                    <option value="Changes Requested">Changes Requested</option>
                                    <option value="Revised">Revised</option>
                                    <option value="Signed">Signed</option>
                                    <option value="Active">Active</option>
                                    <option value="Expiring Soon">Expiring Soon</option>
                                    <option value="Expired">Expired</option>
                                    <option value="Declined">Declined</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">NexFlow Account Owner</label>
                                <select class="input-control input-sm" id="newCntOwner" style="width:100%;">
                                    <option value="">Unassigned</option>
                                    ${users.map(u => `<option value="${u.id}">${escapeHtml(u.first_name + ' ' + u.last_name)}</option>`).join('')}
                                </select>
                            </div>
                        </div>

                        <!-- PAYMENT & RENEWAL -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">PAYMENT &amp; RENEWAL</div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Payment Terms</label>
                            <input type="text" class="input-control input-sm" id="newCntPaymentTerms" placeholder="e.g. 50% advance, Net 30 days" style="width:100%;">
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                            <div>
                                <label class="form-label">Renewal Type</label>
                                <select class="input-control input-sm" id="newCntRenewalType" style="width:100%;">
                                    <option value="Auto-renew" selected>Auto-renew</option>
                                    <option value="Manual Renewal">Manual Renewal</option>
                                    <option value="No Renewal">No Renewal</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Renewal Notice Period (Days)</label>
                                <input type="number" min="0" class="input-control input-sm" id="newCntNoticeDays" value="30" style="width:100%;">
                            </div>
                        </div>

                        <!-- RENEWAL & TERMS -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">RENEWAL &amp; TERMS</div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Agreement Overview</label>
                            <textarea class="input-control" id="newCntDesc" rows="3" style="height:65px;resize:vertical;width:100%;" placeholder="Summary of agreement terms..."></textarea>
                        </div>

                        <div style="margin-bottom:4px;">
                            <label class="form-label">Key Terms &amp; SLAs</label>
                            <textarea class="input-control" id="newCntTerms" rows="3" style="height:65px;resize:vertical;width:100%;" placeholder="Specific SLAs, guarantees, or payment milestones..."></textarea>
                        </div>
                    </div>
                    <div class="contracts-modal-footer" style="padding:14px 20px;border-top:1px solid #E5E7EB;display:flex;align-items:center;justify-content:flex-end;gap:10px;background:#F8FAFC;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Create Contract</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitNewContract: async function (e) {
            e.preventDefault();
            const title = document.getElementById('newCntTitle').value.trim();
            const refNumber = document.getElementById('newCntRef')?.value.trim() || '';
            const companyName = document.getElementById('newCntCompany').value.trim();
            const contactName = document.getElementById('newCntContact')?.value.trim() || '';
            const type = document.getElementById('newCntType').value;
            const value = parseFloat(document.getElementById('newCntValue').value) || 0;
            const startDate = document.getElementById('newCntStartDate').value;
            const endDate = document.getElementById('newCntEndDate').value;
            const status = document.getElementById('newCntStatus')?.value || 'Draft';
            const ownerId = document.getElementById('newCntOwner')?.value ? parseInt(document.getElementById('newCntOwner').value) : null;
            const paymentTerms = document.getElementById('newCntPaymentTerms')?.value.trim() || '';
            const renewalType = document.getElementById('newCntRenewalType')?.value || 'Auto-renew';
            const noticeDays = parseInt(document.getElementById('newCntNoticeDays')?.value) || 30;
            const overview = document.getElementById('newCntDesc')?.value.trim() || '';
            const terms = document.getElementById('newCntTerms')?.value.trim() || '';

            if (!title || !companyName) return;

            // Match company_id if exists in referenceOptions
            const compObj = (referenceOptions.companies || []).find(c => c.name.toLowerCase() === companyName.toLowerCase());
            const companyId = compObj ? compObj.id : null;

            // Match contact_id if exists
            const contObj = (referenceOptions.contacts || []).find(c => (c.first_name + ' ' + (c.last_name || '')).trim().toLowerCase() === contactName.toLowerCase());
            const contactId = contObj ? contObj.id : null;

            const res = await apiRequest(`${API_URL}?action=create`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title: title,
                    reference_number: refNumber,
                    company_name: companyName,
                    company_id: companyId,
                    contact_name: contactName,
                    contact_id: contactId,
                    type: type,
                    value: value,
                    start_date: startDate,
                    end_date: endDate,
                    status: status,
                    owner_id: ownerId,
                    payment_terms: paymentTerms,
                    renewal_type: renewalType,
                    notice_days: noticeDays,
                    overview: overview,
                    terms: terms
                })
            });

            if (res && res.success) {
                this.closeModal();
                this.showToast(res.message || 'Contract created successfully as Draft.', 'success');
                await this.loadReferenceOptions();
                await this.loadSummary();
                await this.renderTable();
            } else {
                this.showToast(res ? res.message : 'Failed to create contract.', 'danger');
            }
        },

        openEditModal: async function (cntId) {
            const targetId = cntId || activeContractId;
            if (!targetId) return;

            const res = await apiRequest(`${API_URL}?action=get&id=${targetId}`);
            if (!res || !res.success || !res.data) {
                this.showToast('Could not load contract for editing.', 'danger');
                return;
            }

            const cnt = res.data.contract;
            const companies = referenceOptions.companies || [];
            const contacts = referenceOptions.contacts || [];
            const users = referenceOptions.users || [];
            const types = referenceOptions.contract_types || [
                'Master Services Agreement (MSA)',
                'Statement of Work (SOW)',
                'Service Level Agreement (SLA)',
                'Non-Disclosure Agreement (NDA)',
                'Software License Agreement',
                'Development Agreement'
            ];

            const html = `
                <div class="contracts-modal-header" style="padding:16px 20px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 class="contracts-modal-title" style="font-size:16px;font-weight:700;color:#0F172A;margin:0;">Edit Contract</h3>
                        <p style="font-size:12px;color:#64748B;margin:2px 0 0 0;">Update contract information and terms.</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contractsApp.closeModal()" aria-label="Close modal">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="cntEditForm" onsubmit="window.contractsApp.submitEditContract(event, ${cnt.id})">
                    <div class="contracts-modal-body" style="padding:20px;max-height:75vh;overflow-y:auto;">
                        <!-- BASIC INFORMATION -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">BASIC INFORMATION</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Contract Name *</label>
                                <input type="text" class="input-control input-sm" id="editCntTitle" value="${escapeHtml(cnt.title)}" required style="width:100%;">
                            </div>
                            <div>
                                <label class="form-label">Contract ID (Read-only)</label>
                                <input type="text" class="input-control input-sm" id="editCntId" value="${escapeHtml(cnt.contract_number)}" readonly disabled style="width:100%;background:#F1F5F9;color:#64748B;cursor:not-allowed;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Contract Reference</label>
                                <input type="text" class="input-control input-sm" id="editCntRef" value="${escapeHtml(cnt.reference_number || '')}" placeholder="e.g. SLA-2026-MAINT" style="width:100%;">
                            </div>
                            <div>
                                <label class="form-label">Company / Client *</label>
                                <input type="text" class="input-control input-sm" id="editCntCompany" list="editCntCompanyList" value="${escapeHtml(cnt.resolved_company_name || cnt.company_name || '')}" required style="width:100%;">
                                <datalist id="editCntCompanyList">
                                    ${companies.map(c => `<option value="${escapeHtml(c.name)}">`).join('')}
                                </datalist>
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label class="form-label">Primary Contact</label>
                            <input type="text" class="input-control input-sm" id="editCntContact" list="editCntContactList" value="${escapeHtml(cnt.resolved_contact_name || cnt.contact_name || '')}" style="width:100%;">
                            <datalist id="editCntContactList">
                                ${contacts.map(ct => `<option value="${escapeHtml(ct.first_name + ' ' + (ct.last_name || ''))}">`).join('')}
                            </datalist>
                        </div>

                        <!-- CONTRACT DETAILS -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">CONTRACT DETAILS</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Contract Type *</label>
                                <select class="input-control input-sm" id="editCntType" required style="width:100%;">
                                    ${types.map(t => `<option value="${escapeHtml(t)}" ${cnt.type === t ? 'selected' : ''}>${escapeHtml(t)}</option>`).join('')}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Contract Value *</label>
                                <input type="number" step="0.01" min="0" class="input-control input-sm" id="editCntValue" value="${cnt.value || 0}" required style="width:100%;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="input-control input-sm" id="editCntStartDate" value="${cnt.start_date || ''}" required style="width:100%;">
                            </div>
                            <div>
                                <label class="form-label">End Date *</label>
                                <input type="date" class="input-control input-sm" id="editCntEndDate" value="${cnt.end_date || ''}" required style="width:100%;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                            <div>
                                <label class="form-label">Status *</label>
                                <select class="input-control input-sm" id="editCntStatus" required style="width:100%;">
                                    <option value="Draft" ${cnt.status === 'Draft' ? 'selected' : ''}>Draft</option>
                                    <option value="Sent" ${cnt.status === 'Sent' ? 'selected' : ''}>Sent</option>
                                    <option value="Viewed" ${cnt.status === 'Viewed' ? 'selected' : ''}>Viewed</option>
                                    <option value="Pending Signature" ${cnt.status === 'Pending Signature' ? 'selected' : ''}>Pending Signature</option>
                                    <option value="Changes Requested" ${cnt.status === 'Changes Requested' ? 'selected' : ''}>Changes Requested</option>
                                    <option value="Revised" ${cnt.status === 'Revised' ? 'selected' : ''}>Revised</option>
                                    <option value="Signed" ${cnt.status === 'Signed' ? 'selected' : ''}>Signed</option>
                                    <option value="Active" ${cnt.status === 'Active' ? 'selected' : ''}>Active</option>
                                    <option value="Expiring Soon" ${cnt.status === 'Expiring Soon' ? 'selected' : ''}>Expiring Soon</option>
                                    <option value="Expired" ${cnt.status === 'Expired' ? 'selected' : ''}>Expired</option>
                                    <option value="Declined" ${cnt.status === 'Declined' ? 'selected' : ''}>Declined</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">NexFlow Account Owner</label>
                                <select class="input-control input-sm" id="editCntOwner" style="width:100%;">
                                    <option value="">Unassigned</option>
                                    ${users.map(u => `<option value="${u.id}" ${cnt.owner_id == u.id ? 'selected' : ''}>${escapeHtml(u.first_name + ' ' + u.last_name)}</option>`).join('')}
                                </select>
                            </div>
                        </div>

                        <!-- PAYMENT & RENEWAL -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">PAYMENT &amp; RENEWAL</div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Payment Terms</label>
                            <input type="text" class="input-control input-sm" id="editCntPaymentTerms" value="${escapeHtml(cnt.payment_terms || '')}" placeholder="e.g. 50% advance, Net 30 days" style="width:100%;">
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                            <div>
                                <label class="form-label">Renewal Type</label>
                                <select class="input-control input-sm" id="editCntRenewalType" style="width:100%;">
                                    <option value="Auto-renew" ${cnt.renewal_type === 'Auto-renew' ? 'selected' : ''}>Auto-renew</option>
                                    <option value="Manual Renewal" ${cnt.renewal_type === 'Manual Renewal' ? 'selected' : ''}>Manual Renewal</option>
                                    <option value="No Renewal" ${cnt.renewal_type === 'No Renewal' ? 'selected' : ''}>No Renewal</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Renewal Notice Period (Days)</label>
                                <input type="number" min="0" class="input-control input-sm" id="editCntNoticeDays" value="${cnt.notice_days !== undefined ? cnt.notice_days : 30}" style="width:100%;">
                            </div>
                        </div>

                        <!-- RENEWAL & TERMS -->
                        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">RENEWAL &amp; TERMS</div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Agreement Overview</label>
                            <textarea class="input-control" id="editCntDesc" rows="3" style="height:65px;resize:vertical;width:100%;" placeholder="Summary of agreement terms...">${escapeHtml(cnt.overview || '')}</textarea>
                        </div>

                        <div style="margin-bottom:4px;">
                            <label class="form-label">Key Terms &amp; SLAs</label>
                            <textarea class="input-control" id="editCntTerms" rows="3" style="height:65px;resize:vertical;width:100%;" placeholder="Specific SLAs, guarantees, or payment milestones...">${escapeHtml(cnt.terms || '')}</textarea>
                        </div>
                    </div>
                    <div class="contracts-modal-footer" style="padding:14px 20px;border-top:1px solid #E5E7EB;display:flex;align-items:center;justify-content:flex-end;gap:10px;background:#F8FAFC;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contractsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditContract: async function (e, cntId) {
            e.preventDefault();

            const title = document.getElementById('editCntTitle').value.trim();
            const refNumber = document.getElementById('editCntRef')?.value.trim() || '';
            const companyName = document.getElementById('editCntCompany')?.value.trim() || '';
            const contactName = document.getElementById('editCntContact')?.value.trim() || '';
            const type = document.getElementById('editCntType')?.value || '';
            const value = parseFloat(document.getElementById('editCntValue')?.value) || 0;
            const startDate = document.getElementById('editCntStartDate')?.value || '';
            const endDate = document.getElementById('editCntEndDate')?.value || '';
            const status = document.getElementById('editCntStatus')?.value || '';
            const ownerId = document.getElementById('editCntOwner')?.value ? parseInt(document.getElementById('editCntOwner').value) : null;
            const paymentTerms = document.getElementById('editCntPaymentTerms')?.value.trim() || '';
            const renewalType = document.getElementById('editCntRenewalType')?.value || 'Auto-renew';
            const noticeDays = parseInt(document.getElementById('editCntNoticeDays')?.value) || 30;
            const overview = document.getElementById('editCntDesc')?.value.trim() || '';
            const terms = document.getElementById('editCntTerms')?.value.trim() || '';

            // Match company_id if exists in referenceOptions
            const compObj = (referenceOptions.companies || []).find(c => c.name.toLowerCase() === companyName.toLowerCase());
            const companyId = compObj ? compObj.id : null;

            const contObj = (referenceOptions.contacts || []).find(c => (c.first_name + ' ' + (c.last_name || '')).trim().toLowerCase() === contactName.toLowerCase());
            const contactId = contObj ? contObj.id : null;

            const res = await apiRequest(`${API_URL}?action=update`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: cntId,
                    title: title,
                    reference_number: refNumber,
                    company_name: companyName,
                    company_id: companyId,
                    contact_name: contactName,
                    contact_id: contactId,
                    type: type,
                    value: value,
                    start_date: startDate,
                    end_date: endDate,
                    status: status,
                    owner_id: ownerId,
                    payment_terms: paymentTerms,
                    renewal_type: renewalType,
                    notice_days: noticeDays,
                    overview: overview,
                    terms: terms
                })
            });

            if (res && res.success) {
                this.closeModal();
                this.showToast(res.message || 'Contract updated successfully.', 'success');
                await this.loadSummary();
                await this.renderTable();
                if (activeContractId && String(activeContractId) === String(cntId)) {
                    this.openDrawer(cntId);
                }
            } else {
                this.showToast(res ? res.message : 'Failed to update contract.', 'danger');
            }
        },

        exportCSV: function () {
            const popoverType = document.getElementById('filterCntType')?.value || 'All';
            const popoverStatus = document.getElementById('filterCntStatus')?.value || 'All';
            let statusParam = currentFilterTab !== 'All' ? currentFilterTab : popoverStatus;

            const params = new URLSearchParams({
                action: 'export_csv',
                status: statusParam,
                type: popoverType,
                search: currentSearch,
                sort: currentSort
            });

            window.location.href = `${API_URL}?${params.toString()}`;
            this.showToast('Exporting contracts to CSV...', 'info');
        },

        toggleFilterPopover: function (e) {
            if (e && e.stopPropagation) e.stopPropagation();
            const popover = document.getElementById("contractsFilterPopover");
            if (popover) popover.classList.toggle("show");
        },

        closeFilterPopover: function () {
            const popover = document.getElementById("contractsFilterPopover");
            if (popover) popover.classList.remove("show");
        },

        applyFilters: function () {
            this.closeFilterPopover();
            currentPage = 1;
            this.renderTable();
        },

        clearFilters: function () {
            if (document.getElementById("filterCntType")) document.getElementById("filterCntType").value = "All";
            if (document.getElementById("filterCntStatus")) document.getElementById("filterCntStatus").value = "All";
            this.closeFilterPopover();
            currentPage = 1;
            this.renderTable();
        },

        showToast: function (msg, type = 'info') {
            const container = document.getElementById('contractsToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `contracts-toast ${type}`;
            toast.style.cssText = 'background:#0F172A; color:#FFF; padding:10px 16px; border-radius:6px; font-size:13px; margin-top:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1); display:flex; align-items:center; gap:8px;';
            toast.innerHTML = `<span>${escapeHtml(msg)}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    };

    document.addEventListener('DOMContentLoaded', () => window.contractsApp.init());
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        window.contractsApp.init();
    }
})();
