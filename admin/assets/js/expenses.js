/**
 * NexFlow CRM — Expenses Management JavaScript Controller
 * Production-ready CRM Expense Management Module backed by MySQL REST API.
 * Manages expense records, dynamic KPI summary calculations, multi-criteria filtering,
 * card interactivity, column customization, detail drawer, custom NexFlow CRUD modals,
 * CSV import validation, receipt file upload/download, and filtered CSV export.
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'NexFlow_expenses_data';
    const COLUMNS_KEY = 'NexFlow_expenses_columns';

    // Purge legacy mock data from localStorage if present
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {}

    // Default Column Visibility & Ordering Configuration (UI preference only)
    const DEFAULT_EXPENSE_COLUMNS = [
        { id: 'expense', label: 'Expense Name & Merchant', locked: true, visible: true },
        { id: 'category', label: 'Category', locked: false, visible: true },
        { id: 'amount', label: 'Amount', locked: false, visible: true },
        { id: 'expense_date', label: 'Expense Date', locked: false, visible: true },
        { id: 'company', label: 'Company / Client', locked: false, visible: true },
        { id: 'project', label: 'Related Project', locked: false, visible: true },
        { id: 'owner', label: 'Owner', locked: false, visible: true },
        { id: 'billingType', label: 'Billing Type', locked: false, visible: true },
        { id: 'invoiceStatus', label: 'Invoice Status', locked: false, visible: true },
        { id: 'reimbursementStatus', label: 'Reimbursement', locked: false, visible: true }
    ];

    // State Variables
    let expenses = [];
    let visibleColumns = loadColumnSettings();
    let selectedIds = new Set();
    let currentSummaryFilter = 'All'; // All, Billable, NonBillable, PendingInvoice, Invoiced
    let currentSearch = '';
    let currentOwnerFilter = 'All';
    let currentSort = 'date-desc';
    let activeExpenseId = null;
    let activeExpenseData = null;

    // Reference options cache (populated from database)
    let cachedUsers = [];
    let cachedCompanies = [];
    let cachedProjects = [];
    let orgCurrency = 'USD';

    // Advanced Popover Filter State
    let filterBillingType = 'All';
    let filterInvoiceStatus = 'All';
    let filterReimbursementStatus = 'All';
    let filterCategory = 'All';
    let filterDatePreset = 'All';
    let filterMinAmount = null;
    let filterMaxAmount = null;
    let filterCustomStart = '';
    let filterCustomEnd = '';

    let currentExpPage = 1;
    const EXP_PER_PAGE = 8;
    let totalRecordsCount = 0;
    let totalPagesCount = 1;

    // Centralized API Client Helper
    async function expenseApi(action, params = {}, options = {}) {
        let url = 'api/expenses.php?action=' + encodeURIComponent(action);
        const method = (options.method || 'GET').toUpperCase();

        try {
            if (method === 'GET') {
                const query = new URLSearchParams();
                for (const [k, v] of Object.entries(params)) {
                    if (v !== null && v !== undefined && v !== '' && v !== 'All') {
                        query.append(k, v);
                    }
                }
                const qStr = query.toString();
                if (qStr) url += '&' + qStr;
                const res = await fetch(url, { credentials: 'same-origin' });
                return await res.json();
            } else {
                let body;
                let headers = {};
                if (params instanceof FormData) {
                    body = params;
                } else {
                    headers['Content-Type'] = 'application/json';
                    body = JSON.stringify(params);
                }
                const res = await fetch(url, {
                    method: method,
                    headers: headers,
                    body: body,
                    credentials: 'same-origin'
                });
                return await res.json();
            }
        } catch (err) {
            console.error(`Expenses API error [${action}]:`, err);
            return { success: false, message: 'Network error or server unavailable.' };
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function (m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[m];
        });
    }

    function loadColumnSettings() {
        try {
            const saved = localStorage.getItem(COLUMNS_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    const validIds = new Set(DEFAULT_EXPENSE_COLUMNS.map(c => c.id));
                    const loaded = [];
                    parsed.forEach(c => {
                        if (c && c.id && validIds.has(c.id)) {
                            const def = DEFAULT_EXPENSE_COLUMNS.find(d => d.id === c.id);
                            loaded.push({
                                id: c.id,
                                label: def ? def.label : c.label,
                                locked: def ? def.locked : false,
                                visible: c.visible !== false
                            });
                            validIds.delete(c.id);
                        }
                    });
                    DEFAULT_EXPENSE_COLUMNS.forEach(def => {
                        if (validIds.has(def.id)) {
                            loaded.push({ ...def });
                        }
                    });
                    return loaded;
                }
            }
        } catch (e) {
            console.warn('Could not load column settings:', e);
        }
        return JSON.parse(JSON.stringify(DEFAULT_EXPENSE_COLUMNS));
    }

    function saveColumnSettings() {
        try {
            localStorage.setItem(COLUMNS_KEY, JSON.stringify(visibleColumns));
        } catch (e) {
            console.warn('Could not save column settings:', e);
        }
    }

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

    // Dynamic KPI Calculations & Summary Cards Update via API
    async function updateKPIs() {
        const res = await expenseApi('kpis');
        if (!res || !res.success || !res.data) return;

        const data = res.data;
        const fmt = val => '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const kpiTotal = document.getElementById('kpiTotalExpenses');
        const kpiBillable = document.getElementById('kpiBillable');
        const kpiNonBillable = document.getElementById('kpiNonBillable');
        const kpiNotInvoiced = document.getElementById('kpiNotInvoiced');
        const kpiInvoiced = document.getElementById('kpiInvoiced');
        const badgeCount = document.getElementById('expensesTotalCountBadge');

        if (kpiTotal) kpiTotal.textContent = fmt(data.total_expenses?.amount);
        if (kpiBillable) kpiBillable.textContent = fmt(data.billable?.amount);
        if (kpiNonBillable) kpiNonBillable.textContent = fmt(data.non_billable?.amount);
        if (kpiNotInvoiced) kpiNotInvoiced.textContent = fmt(data.pending_invoice?.amount);
        if (kpiInvoiced) kpiInvoiced.textContent = fmt(data.invoiced?.amount);

        const totalCnt = Number(data.total_expenses?.count || 0);
        if (badgeCount) badgeCount.textContent = `${totalCnt} expense${totalCnt === 1 ? '' : 's'}`;
    }

    // Load active users, companies, projects from DB
    async function loadReferenceOptions() {
        const res = await expenseApi('reference_options');
        if (res && res.success && res.data) {
            cachedUsers = res.data.users || [];
            cachedCompanies = res.data.companies || [];
            cachedProjects = res.data.projects || [];
            orgCurrency = res.data.org_currency || 'USD';
            populateOwnerDropdownOptions();
        }
    }

    function populateOwnerDropdownOptions() {
        const dropdown = document.getElementById('expOwnerDropdown');
        if (!dropdown) return;
        const currentVal = dropdown.value || 'All';

        let html = '<option value="All">All Owners</option>';
        cachedUsers.forEach(u => {
            const name = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email;
            html += `<option value="${escapeHtml(name)}" ${name === currentVal ? 'selected' : ''}>${escapeHtml(name)}</option>`;
        });

        dropdown.innerHTML = html;
    }

    // Public Controller Window Binding
    window.expensesApp = {
        init: async function () {
            await loadReferenceOptions();
            await updateKPIs();
            this.renderColumnsPopover();
            await this.renderTable();
            this.setupEventListeners();
        },

        getActiveExpenseId: function() {
            return activeExpenseId;
        },

        setupEventListeners: function () {
            document.addEventListener('click', function (e) {
                // Close row dropdown menu on outside click
                if (!e.target.closest('#expensesRowDropdown') && !e.target.closest('.exp-row-menu-btn')) {
                    const menu = document.getElementById('expensesRowDropdown');
                    if (menu) menu.classList.remove('show');
                }
                // Close columns popover on outside click
                if (!e.target.closest('#columnsPopover') && !e.target.closest('[onclick*="toggleColumnsPopover"]')) {
                    const pop = document.getElementById('columnsPopover');
                    if (pop) pop.classList.remove('show');
                }
                // Close filter popover on outside click
                if (!e.target.closest('#filterPopover') && !e.target.closest('[onclick*="toggleFilterPopover"]')) {
                    const pop = document.getElementById('filterPopover');
                    if (pop) pop.classList.remove('show');
                }
                // Close drawer more dropdown on outside click
                if (!e.target.closest('#drawerMoreMenu') && !e.target.closest('.btn-drawer-more')) {
                    const menu = document.getElementById('drawerMoreMenu');
                    if (menu) menu.classList.remove('show');
                }
                // Close modal on backdrop click
                const modal = document.getElementById('expActionModal');
                if (modal && e.target === modal) {
                    window.expensesApp.closeModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    window.expensesApp.closeModal();
                    window.expensesApp.closeDrawer();
                    const cp = document.getElementById('columnsPopover');
                    if (cp) cp.classList.remove('show');
                    const fp = document.getElementById('filterPopover');
                    if (fp) fp.classList.remove('show');
                }
            });
        },

        goToPage: function (p) {
            currentExpPage = p;
            this.renderTable();
        },

        handleSummaryCardClick: function (filterKey) {
            currentSummaryFilter = filterKey;
            currentExpPage = 1;
            this.renderTable();
        },

        handleSearch: function (query) {
            currentSearch = query.trim().toLowerCase();
            currentExpPage = 1;
            this.renderTable();
        },

        handleOwnerFilter: function (owner) {
            currentOwnerFilter = owner;
            currentExpPage = 1;
            this.renderTable();
        },

        handleSort: function (sortVal) {
            currentSort = sortVal;
            currentExpPage = 1;
            this.renderTable();
        },

        toggleColumnsPopover: function (e) {
            e.stopPropagation();
            const pop = document.getElementById('columnsPopover');
            if (!pop) return;
            const isVisible = pop.classList.contains('show');
            // Close other popovers
            document.querySelectorAll('.expenses-popover').forEach(p => p.classList.remove('show'));
            if (!isVisible) {
                this.renderColumnsPopover();
                pop.classList.add('show');
            }
        },

        closeColumnsPopover: function () {
            const pop = document.getElementById('columnsPopover');
            if (pop) pop.classList.remove('show');
        },

        renderColumnsPopover: function () {
            const listEl = document.getElementById('columnsListContainer');
            if (!listEl) return;

            let html = '';
            visibleColumns.forEach((col, idx) => {
                const isChecked = col.visible !== false ? 'checked' : '';
                const isLocked = col.locked ? 'disabled' : '';

                html += `
                    <div class="col-item ${col.locked ? 'locked' : ''}" data-col-id="${col.id}" draggable="${!col.locked}">
                        <div class="col-item-left">
                            <span class="col-drag-handle ${col.locked ? 'disabled' : ''}" title="Reorder">⋮⋮</span>
                            <label class="col-checkbox-label">
                                <input type="checkbox" class="col-toggle-cb" data-col-id="${col.id}" ${isChecked} ${isLocked} onchange="window.expensesApp.toggleColumnVisibility('${col.id}', this.checked)">
                                <span>${col.label}</span>
                            </label>
                        </div>
                        <div class="col-item-actions">
                            ${!col.locked && idx > 1 ? `
                            <button type="button" class="btn-col-order" title="Move Up" onclick="window.expensesApp.moveColumnUp('${col.id}')">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
                            </button>` : ''}
                            ${!col.locked && idx < visibleColumns.length - 1 ? `
                            <button type="button" class="btn-col-order" title="Move Down" onclick="window.expensesApp.moveColumnDown('${col.id}')">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>` : ''}
                            ${col.locked ? '<span class="badge-locked" title="Required column">Required</span>' : ''}
                        </div>
                    </div>
                `;
            });

            listEl.innerHTML = html;
            this.setupColumnDragAndDrop();
        },

        moveColumnUp: function (columnId) {
            const idx = visibleColumns.findIndex(c => c.id === columnId);
            if (idx <= 1) return; // Keep locked column at index 0
            const temp = visibleColumns[idx];
            visibleColumns[idx] = visibleColumns[idx - 1];
            visibleColumns[idx - 1] = temp;
            saveColumnSettings();
            this.renderColumnsPopover();
            this.renderTable();
        },

        moveColumnDown: function (columnId) {
            const idx = visibleColumns.findIndex(c => c.id === columnId);
            if (idx < 0 || idx >= visibleColumns.length - 1) return;
            const temp = visibleColumns[idx];
            visibleColumns[idx] = visibleColumns[idx + 1];
            visibleColumns[idx + 1] = temp;
            saveColumnSettings();
            this.renderColumnsPopover();
            this.renderTable();
        },

        setupColumnDragAndDrop: function () {
            const container = document.getElementById('columnsListContainer');
            if (!container) return;

            let dragSrcEl = null;

            const items = container.querySelectorAll('.col-item[draggable="true"]');
            items.forEach(item => {
                item.addEventListener('dragstart', function (e) {
                    dragSrcEl = this;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', this.getAttribute('data-col-id'));
                    this.classList.add('dragging');
                });

                item.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    return false;
                });

                item.addEventListener('dragenter', function () {
                    this.classList.add('drag-over');
                });

                item.addEventListener('dragleave', function () {
                    this.classList.remove('drag-over');
                });

                item.addEventListener('drop', function (e) {
                    e.stopPropagation();
                    this.classList.remove('drag-over');
                    const targetColId = this.getAttribute('data-col-id');
                    const srcColId = dragSrcEl ? dragSrcEl.getAttribute('data-col-id') : e.dataTransfer.getData('text/plain');

                    if (srcColId !== targetColId) {
                        const srcIdx = visibleColumns.findIndex(c => c.id === srcColId);
                        const targetIdx = visibleColumns.findIndex(c => c.id === targetColId);

                        if (srcIdx > 0 && targetIdx > 0) {
                            const [movedItem] = visibleColumns.splice(srcIdx, 1);
                            visibleColumns.splice(targetIdx, 0, movedItem);
                            saveColumnSettings();
                            window.expensesApp.renderColumnsPopover();
                            window.expensesApp.renderTable();
                        }
                    }
                    return false;
                });

                item.addEventListener('dragend', function () {
                    this.classList.remove('dragging');
                    items.forEach(i => i.classList.remove('drag-over'));
                });
            });
        },

        toggleColumnVisibility: function (columnId, isChecked) {
            const col = visibleColumns.find(c => c.id === columnId);
            if (!col || col.locked) return;
            col.visible = isChecked;
            saveColumnSettings();
            this.renderTable();
        },

        showAllColumns: function () {
            visibleColumns.forEach(c => c.visible = true);
            saveColumnSettings();
            this.renderColumnsPopover();
            this.renderTable();
        },

        hideOptionalColumns: function () {
            visibleColumns.forEach(c => {
                if (!c.locked) c.visible = false;
            });
            saveColumnSettings();
            this.renderColumnsPopover();
            this.renderTable();
        },

        resetColumns: function () {
            visibleColumns = JSON.parse(JSON.stringify(DEFAULT_EXPENSE_COLUMNS));
            saveColumnSettings();
            this.renderColumnsPopover();
            this.renderTable();
        },

        toggleFilterPopover: function (e) {
            e.stopPropagation();
            const pop = document.getElementById('filterPopover');
            if (!pop) return;
            const isVisible = pop.classList.contains('show');
            document.querySelectorAll('.expenses-popover').forEach(p => p.classList.remove('show'));
            if (!isVisible) pop.classList.add('show');
        },

        closeFilterPopover: function () {
            const pop = document.getElementById('filterPopover');
            if (pop) pop.classList.remove('show');
        },

        applyFilters: function () {
            filterBillingType = document.getElementById('filterBillingType')?.value || 'All';
            filterInvoiceStatus = document.getElementById('filterInvoiceStatus')?.value || 'All';
            filterReimbursementStatus = document.getElementById('filterReimburseStatus')?.value || 'All';
            filterCategory = document.getElementById('filterCategory')?.value || 'All';
            filterDatePreset = document.getElementById('filterDateRange')?.value || 'All';
            filterMinAmount = parseFloat(document.getElementById('filterMinAmount')?.value) || null;
            filterMaxAmount = parseFloat(document.getElementById('filterMaxAmount')?.value) || null;
            filterCustomStart = document.getElementById('customDateStart')?.value || '';
            filterCustomEnd = document.getElementById('customDateEnd')?.value || '';

            this.closeFilterPopover();
            this.updateActiveFilterNotice();
            currentExpPage = 1;
            this.renderTable();
        },

        clearFilters: function () {
            filterBillingType = 'All';
            filterInvoiceStatus = 'All';
            filterReimbursementStatus = 'All';
            filterCategory = 'All';
            filterDatePreset = 'All';
            filterMinAmount = null;
            filterMaxAmount = null;
            filterCustomStart = '';
            filterCustomEnd = '';

            const elBilling = document.getElementById('filterBillingType');
            const elInvoice = document.getElementById('filterInvoiceStatus');
            const elReimburse = document.getElementById('filterReimburseStatus');
            const elCategory = document.getElementById('filterCategory');
            const elDate = document.getElementById('filterDateRange');
            const elMin = document.getElementById('filterMinAmount');
            const elMax = document.getElementById('filterMaxAmount');

            if (elBilling) elBilling.value = 'All';
            if (elInvoice) elInvoice.value = 'All';
            if (elReimburse) elReimburse.value = 'All';
            if (elCategory) elCategory.value = 'All';
            if (elDate) elDate.value = 'All';
            if (elMin) elMin.value = '';
            if (elMax) elMax.value = '';

            currentSummaryFilter = 'All';
            currentSearch = '';
            const searchBox = document.querySelector('.expenses-search-input');
            if (searchBox) searchBox.value = '';

            currentOwnerFilter = 'All';
            const ownerDd = document.getElementById('expOwnerDropdown');
            if (ownerDd) ownerDd.value = 'All';

            this.closeFilterPopover();
            this.updateActiveFilterNotice();
            currentExpPage = 1;
            this.renderTable();
        },

        updateActiveFilterNotice: function () {
            const badge = document.getElementById('activeFilterCountBadge');
            let count = 0;
            if (filterBillingType !== 'All') count++;
            if (filterInvoiceStatus !== 'All') count++;
            if (filterReimbursementStatus !== 'All') count++;
            if (filterCategory !== 'All') count++;
            if (filterDatePreset !== 'All') count++;
            if (filterMinAmount !== null) count++;
            if (filterMaxAmount !== null) count++;

            if (badge) {
                if (count > 0) {
                    badge.style.display = 'inline-flex';
                    badge.textContent = count;
                } else {
                    badge.style.display = 'none';
                }
            }
        },

        // Render Table from Live API Data
        renderTable: async function () {
            const thead = document.getElementById('expensesTableHead');
            const tbody = document.getElementById('expensesTableBody');
            if (!tbody || !thead) return;

            const activeCols = visibleColumns.filter(c => c.visible !== false);

            // 1. Build Header Row based on visibleColumns array order
            let headHtml = '<tr id="expensesTableHeadRow">';
            visibleColumns.forEach(col => {
                const isHiddenStyle = col.visible === false ? 'display:none;' : '';
                headHtml += `<th data-column="${col.id}" style="${isHiddenStyle}">${col.label}</th>`;
            });
            headHtml += '<th data-column="actions" style="text-align:right;width:100px;">Actions</th>';
            headHtml += '</tr>';
            thead.innerHTML = headHtml;

            // 2. Fetch data from backend API
            const params = {
                search: currentSearch,
                category: filterCategory,
                billing_type: filterBillingType,
                invoice_status: filterInvoiceStatus,
                reimbursement_status: filterReimbursementStatus,
                summary_filter: currentSummaryFilter,
                owner: currentOwnerFilter,
                date_range: filterDatePreset.toLowerCase(),
                start_date: filterCustomStart,
                end_date: filterCustomEnd,
                min_amount: filterMinAmount,
                max_amount: filterMaxAmount,
                sort: currentSort,
                page: currentExpPage,
                page_size: EXP_PER_PAGE
            };

            const res = await expenseApi('list', params);
            if (!res || !res.success || !res.data) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${activeCols.length + 1}" style="text-align:center;padding:32px;color:var(--text-muted);">
                            Failed to load expenses from server.
                        </td>
                    </tr>
                `;
                return;
            }

            expenses = res.data.items || [];
            totalRecordsCount = res.data.total || 0;
            totalPagesCount = res.data.total_pages || 1;
            currentExpPage = res.data.page || 1;

            const startIndex = (currentExpPage - 1) * EXP_PER_PAGE;
            const pagingRange = document.getElementById("pagingRange");
            const pagingTotal = document.getElementById("pagingTotal");
            const controls = document.getElementById("paginationControls");

            if (pagingRange) pagingRange.textContent = totalRecordsCount === 0 ? "0" : `${startIndex + 1}–${Math.min(startIndex + expenses.length, totalRecordsCount)}`;
            if (pagingTotal) pagingTotal.textContent = totalRecordsCount;

            const container = document.querySelector('.pagination-container');
            if (container) {
                container.style.display = totalRecordsCount > EXP_PER_PAGE ? 'flex' : 'none';
            }

            if (controls) {
                if (totalRecordsCount === 0) {
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
                        <button class="pagination-btn" id="prevPageBtn" ${currentExpPage === 1 ? 'disabled' : ''} onclick="window.expensesApp.goToPage(${currentExpPage - 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                    `;

                    const pageNumbers = getPaginationPages(currentExpPage, totalPagesCount);
                    pageNumbers.forEach(p => {
                        if (p === '...') {
                            html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
                        } else {
                            const isActive = (p === currentExpPage);
                            html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.expensesApp.goToPage(${p})">${p}</button>`;
                        }
                    });

                    html += `
                        <button class="pagination-btn" id="nextPageBtn" ${currentExpPage === totalPagesCount ? 'disabled' : ''} onclick="window.expensesApp.goToPage(${currentExpPage + 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `;
                    controls.innerHTML = html;
                }
            }

            // 4. Render Rows or Empty State
            const visibleColCount = activeCols.length + 1; // + actions

            if (expenses.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${visibleColCount}" style="text-align:center;padding:48px 16px;background:#F8FAFC;">
                            <div style="width:40px;height:40px;border-radius:50%;background:#F1F5F9;color:var(--text-muted);display:flex;align-items:center;justify-content:center;margin:0 auto 12px auto;">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </div>
                            <div style="font-size:15px;font-weight:600;color:var(--text-heading);">No expenses found</div>
                            <p style="font-size:13px;color:var(--text-muted);margin:4px 0 14px 0;">No expenses match your current search or filters.</p>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.clearFilters()">Clear Filters</button>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = expenses.map(e => {
                const billableBadge = e.billable ? '<span class="badge-exp badge-billable">Billable</span>' : '<span class="badge-exp badge-nonbillable">Non-Billable</span>';
                
                const invStatusBadge = e.billingStatus === 'Invoiced' ? '<span class="badge-exp badge-invoiced">Invoiced</span>' :
                                       e.billingStatus === 'Not Invoiced' ? '<span class="badge-exp badge-notinvoiced">Not Invoiced</span>' :
                                       '<span class="badge-exp badge-nonbillable">N/A</span>';

                const reimbBadge = e.reimbursementStatus === 'Reimbursed' ? '<span class="badge-exp badge-reimbursed">Reimbursed</span>' :
                                   e.reimbursementStatus === 'Pending' ? '<span class="badge-exp badge-notinvoiced">Pending</span>' :
                                   '<span style="color:var(--text-muted);font-size:12px;">—</span>';

                const initials = e.owner ? e.owner.split(' ').map(n=>n[0]).join('') : 'EM';

                let rowHtml = `<tr data-exp-id="${e.id}">`;

                visibleColumns.forEach(col => {
                    const isHiddenStyle = col.visible === false ? 'display:none;' : '';

                    if (col.id === 'expense') {
                        rowHtml += `
                            <td data-column="expense" style="${isHiddenStyle}">
                                <div style="font-weight:600;color:var(--text-heading);font-size:14px;cursor:pointer;" onclick="window.expensesApp.openDrawer(${e.id})">
                                    ${escapeHtml(e.title)}
                                </div>
                                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                                    ${escapeHtml(e.merchant)}${e.refNumber ? ` • ${escapeHtml(e.refNumber)}` : ''}
                                </div>
                            </td>
                        `;
                    } else if (col.id === 'category') {
                        rowHtml += `<td data-column="category" style="${isHiddenStyle}"><span class="badge-category">${escapeHtml(e.category)}</span></td>`;
                    } else if (col.id === 'amount') {
                        rowHtml += `<td data-column="amount" style="${isHiddenStyle}font-weight:600;color:var(--text-heading);font-size:14px;">$${Number(e.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>`;
                    } else if (col.id === 'expense_date') {
                        rowHtml += `<td data-column="expense_date" style="${isHiddenStyle}font-size:13px;color:var(--text-heading);">${e.date}</td>`;
                    } else if (col.id === 'company') {
                        rowHtml += `
                            <td data-column="company" style="${isHiddenStyle}">
                                <div style="font-weight:600;color:var(--text-heading);font-size:13.5px;">${escapeHtml(e.company)}</div>
                                ${e.project && e.project !== 'General' ? `<div style="font-size:12px;color:var(--text-muted);">${escapeHtml(e.project)}</div>` : ''}
                            </td>
                        `;
                    } else if (col.id === 'project') {
                        rowHtml += `<td data-column="project" style="${isHiddenStyle}font-size:13px;color:var(--text-secondary);">${escapeHtml(e.project || 'General')}</td>`;
                    } else if (col.id === 'owner') {
                        rowHtml += `
                            <td data-column="owner" style="${isHiddenStyle}">
                                <div style="display:flex;align-items:center;gap:7px;">
                                    <div class="avatar avatar-xs" style="background-color:#0284C7;font-size:9.5px;font-weight:600;width:22px;height:22px;border-radius:50%;color:#FFF;display:flex;align-items:center;justify-content:center;">${escapeHtml(initials)}</div>
                                    <span style="font-size:13px;color:var(--text-heading);">${escapeHtml(e.owner)}</span>
                                </div>
                            </td>
                        `;
                    } else if (col.id === 'billingType') {
                        rowHtml += `<td data-column="billingType" style="${isHiddenStyle}">${billableBadge}</td>`;
                    } else if (col.id === 'invoiceStatus') {
                        rowHtml += `<td data-column="invoiceStatus" style="${isHiddenStyle}">${invStatusBadge}</td>`;
                    } else if (col.id === 'reimbursementStatus') {
                        rowHtml += `<td data-column="reimbursementStatus" style="${isHiddenStyle}">${reimbBadge}</td>`;
                    }
                });

                // Actions Column
                rowHtml += `
                    <td data-column="actions" style="text-align:right;white-space:nowrap;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.expensesApp.openDrawer(${e.id})">Details</button>
                        <button type="button" class="btn btn-ghost btn-xs exp-row-menu-btn" style="padding:0 4px;" onclick="window.expensesApp.openRowMenu(event, ${e.id})">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        </button>
                    </td>
                `;

                rowHtml += '</tr>';
                return rowHtml;
            }).join('');
        },

        toggleSelection: function (id, isChecked) {
            if (isChecked) selectedIds.add(id);
            else selectedIds.delete(id);
        },

        toggleSelectAll: function (isChecked) {
            expenses.forEach(e => {
                if (isChecked) selectedIds.add(e.id);
                else selectedIds.delete(e.id);
            });
            this.renderTable();
        },

        openRowMenu: function (e, expId) {
            e.stopPropagation();
            const exp = expenses.find(x => x.id === expId);
            if (!exp) return;

            let menu = document.getElementById('expensesRowDropdown');
            if (!menu) {
                menu = document.createElement('div');
                menu.id = 'expensesRowDropdown';
                menu.className = 'expenses-dropdown-menu';
                document.body.appendChild(menu);
            }

            const isBillable = exp.billable;
            const isInvoiced = (exp.billingStatus === 'Invoiced');
            const isReimbursed = (exp.reimbursementStatus === 'Reimbursed');

            menu.innerHTML = `
                <button class="dropdown-item" onclick="window.expensesApp.openDrawer(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    View Details
                </button>
                <button class="dropdown-item" onclick="window.expensesApp.openEditModal(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Expense
                </button>
                <button class="dropdown-item" onclick="window.expensesApp.duplicateExpense(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Duplicate Expense
                </button>
                <button class="dropdown-item" onclick="window.expensesApp.toggleBillable(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    ${isBillable ? 'Mark Non-Billable' : 'Mark Billable'}
                </button>
                ${isBillable ? `
                <button class="dropdown-item" onclick="window.expensesApp.toggleInvoiced(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>
                    ${isInvoiced ? 'Mark as Not Invoiced' : 'Attach to Client Invoice'}
                </button>` : ''}
                <button class="dropdown-item" onclick="window.expensesApp.toggleReimbursed(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    ${isReimbursed ? 'Mark as Pending Reimburse' : 'Mark as Reimbursed'}
                </button>
                <div class="dropdown-divider"></div>
                <button class="dropdown-item danger" onclick="window.expensesApp.openDeleteModal(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                    Delete Expense
                </button>
            `;

            const btnRect = e.currentTarget.getBoundingClientRect();
            menu.style.top = (btnRect.bottom + window.scrollY + 4) + 'px';
            menu.style.left = (btnRect.right + window.scrollX - 180) + 'px';
            menu.classList.add('show');
        },

        // Details Drawer Panel
        openDrawer: async function (expId) {
            activeExpenseId = expId;

            const res = await expenseApi('get', { id: expId });
            if (!res || !res.success || !res.data) {
                this.showToast('Could not load expense details.', 'warning');
                return;
            }

            activeExpenseData = res.data;
            const exp = activeExpenseData;

            const drawer = document.getElementById('expensesDrawer');
            const title = document.getElementById('expDrawerTitle');
            const subtitle = document.getElementById('expDrawerSubtitle');
            const badge = document.getElementById('expDrawerBadge');

            if (title) title.textContent = exp.title;
            if (subtitle) subtitle.textContent = `Expenses / ${exp.company} • ${exp.refNumber || exp.id}`;

            if (badge) {
                let badgeClass, badgeLabel;
                if (exp.billingStatus === 'Invoiced') {
                    badgeClass = 'badge-exp badge-invoiced'; badgeLabel = '● Invoiced';
                } else if (exp.billingStatus === 'Not Invoiced') {
                    badgeClass = 'badge-exp badge-notinvoiced'; badgeLabel = '● Not Invoiced';
                } else if (exp.reimbursementStatus === 'Reimbursed') {
                    badgeClass = 'badge-exp badge-reimbursed'; badgeLabel = '● Reimbursed';
                } else {
                    badgeClass = 'badge-exp badge-nonbillable'; badgeLabel = '● Non-Billable';
                }
                badge.className = badgeClass;
                badge.textContent = badgeLabel;
            }

            document.querySelectorAll('.drawer-tab').forEach(t => t.classList.remove('active'));
            const overviewTab = document.querySelector('#expDrawerTabsContainer .drawer-tab[data-tab="overview"]');
            if (overviewTab) overviewTab.classList.add('active');

            this.renderDrawerTab('overview', exp);

            if (drawer) {
                drawer.style.display = 'flex';
                drawer.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        },

        closeDrawer: function () {
            const drawer = document.getElementById('expensesDrawer');
            if (drawer) {
                drawer.classList.remove('show');
                drawer.style.display = 'none';
                document.body.style.overflow = '';
            }
            const menu = document.getElementById('drawerMoreMenu');
            if (menu) menu.classList.remove('show');
        },

        toggleDrawerMoreMenu: function (e) {
            e.stopPropagation();
            const menu = document.getElementById('drawerMoreMenu');
            if (!menu || !activeExpenseId || !activeExpenseData) return;

            const exp = activeExpenseData;

            menu.innerHTML = `
                <button class="dropdown-item" onclick="window.expensesApp.openEditModal(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Expense
                </button>
                <button class="dropdown-item" onclick="window.expensesApp.duplicateExpense(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Duplicate Expense
                </button>
                <button class="dropdown-item" onclick="window.expensesApp.toggleBillable(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    ${exp.billable ? 'Mark as Non-Billable' : 'Mark as Billable'}
                </button>
                ${exp.billable ? `
                <button class="dropdown-item" onclick="window.expensesApp.toggleInvoiced(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>
                    ${exp.billingStatus === 'Invoiced' ? 'Mark as Not Invoiced' : 'Mark as Invoiced'}
                </button>` : ''}
                <button class="dropdown-item" onclick="window.expensesApp.toggleReimbursed(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    ${exp.reimbursementStatus === 'Reimbursed' ? 'Mark as Pending Reimburse' : 'Mark as Reimbursed'}
                </button>
                <div class="dropdown-divider"></div>
                <button class="dropdown-item danger" onclick="window.expensesApp.openDeleteModal(${exp.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                    Delete Expense
                </button>
            `;
            menu.classList.toggle('show');
        },

        switchDrawerTab: function (tabName) {
            document.querySelectorAll('.drawer-tab').forEach(t => {
                t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
            });
            if (activeExpenseData) {
                this.renderDrawerTab(tabName, activeExpenseData);
            }
        },

        renderDrawerTab: function (tabName, exp) {
            const body = document.getElementById('expDrawerBody');
            if (!body || !exp) return;

            const fmt = v => '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (tabName === 'overview') {
                const billableBtnLabel = exp.billable ? 'Mark Non-Billable' : 'Mark Billable';

                body.innerHTML = `
                    <div class="exp-quick-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.openEditModal(${exp.id})">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Edit
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.toggleBillable(${exp.id})">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            ${billableBtnLabel}
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.openAddNoteModal(${exp.id})">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Add Note
                        </button>
                    </div>

                    <div class="exp-kpi-grid">
                        <div class="exp-kpi-card">
                            <div class="exp-kpi-label">Amount</div>
                            <div class="exp-kpi-value">${fmt(exp.amount)}</div>
                        </div>
                        <div class="exp-kpi-card">
                            <div class="exp-kpi-label">Category</div>
                            <div class="exp-kpi-category">${escapeHtml(exp.category)}</div>
                        </div>
                    </div>

                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Expense Details</div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Expense Date</span>
                            <span class="drawer-info-val">${exp.date}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Merchant / Vendor</span>
                            <span class="drawer-info-val">${escapeHtml(exp.merchant || '—')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Customer / Company</span>
                            <span class="drawer-info-val">${escapeHtml(exp.company)}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Related Project</span>
                            <span class="drawer-info-val">${escapeHtml(exp.project || 'General')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Owner / Submitter</span>
                            <span class="drawer-info-val">${escapeHtml(exp.owner)}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Billing Type</span>
                            <span class="drawer-info-val">${exp.billable ? 'Billable to Client' : 'Non-Billable Internal'}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Invoice Status</span>
                            <span class="drawer-info-val">${escapeHtml(exp.billingStatus)}${exp.invoiceId ? ' (' + escapeHtml(exp.invoiceId) + ')' : ''}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Reimbursement</span>
                            <span class="drawer-info-val">${escapeHtml(exp.reimbursementStatus || 'N/A')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Payment Method</span>
                            <span class="drawer-info-val">${escapeHtml(exp.paymentMethod || 'Corporate Card')}</span>
                        </div>
                        <div class="drawer-detail-row">
                            <span class="drawer-info-label">Reference Number</span>
                            <span class="drawer-info-val monospace">${escapeHtml(exp.refNumber || exp.id)}</span>
                        </div>
                    </div>

                    ${exp.description ? `
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Description &amp; Notes</div>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;white-space:pre-wrap;">${escapeHtml(exp.description)}</p>
                    </div>` : ''}
                `;

            } else if (tabName === 'receipt') {
                if (exp.receipt) {
                    const isPdf = exp.receipt.toLowerCase().endsWith('.pdf');
                    const fileTypeLabel = isPdf ? 'PDF' : exp.receipt.split('.').pop().toUpperCase();

                    body.innerHTML = `
                        <div class="drawer-info-card">
                            <div class="drawer-card-title">Receipt Details</div>
                            <div class="drawer-detail-row">
                                <span class="drawer-info-label">Receipt File</span>
                                <span class="drawer-info-val monospace">${escapeHtml(exp.receipt)}</span>
                            </div>
                            <div class="drawer-detail-row">
                                <span class="drawer-info-label">Merchant</span>
                                <span class="drawer-info-val">${escapeHtml(exp.merchant || '—')}</span>
                            </div>
                            <div class="drawer-detail-row">
                                <span class="drawer-info-label">Expense Amount</span>
                                <span class="drawer-info-val">${fmt(exp.amount)}</span>
                            </div>
                            <div class="drawer-detail-row">
                                <span class="drawer-info-label">Expense Date</span>
                                <span class="drawer-info-val">${exp.date}</span>
                            </div>
                            <div class="drawer-detail-row">
                                <span class="drawer-info-label">Reference</span>
                                <span class="drawer-info-val monospace">${escapeHtml(exp.refNumber || exp.id)}</span>
                            </div>
                        </div>

                        <div class="exp-receipt-file-card">
                            <div class="exp-receipt-icon">
                                <svg width="22" height="22" fill="none" stroke="#2563EB" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div class="exp-receipt-info">
                                <div class="exp-receipt-filename">${escapeHtml(exp.receipt)}</div>
                                <div class="exp-receipt-subtext">${fileTypeLabel} &bull; Attached Proof</div>
                            </div>
                            <div class="exp-receipt-actions">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.previewReceipt(${exp.id})">View</button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="window.expensesApp.downloadReceipt(${exp.id})">Download</button>
                            </div>
                        </div>
                    `;
                } else {
                    body.innerHTML = `
                        <div class="exp-empty-state">
                            <div class="exp-empty-icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            </div>
                            <p class="exp-empty-title">No receipt attached</p>
                            <p class="exp-empty-desc">No proof of purchase file was uploaded for this expense.</p>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.openEditModal(${exp.id})">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Upload Receipt
                            </button>
                        </div>
                    `;
                }

            } else if (tabName === 'activity') {
                const activities = exp.activity || [];
                const notes = exp.notes || [];

                if (activities.length === 0 && notes.length === 0) {
                    body.innerHTML = `
                        <div class="exp-empty-state">
                            <div class="exp-empty-icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <p class="exp-empty-title">No activity yet</p>
                            <p class="exp-empty-desc">Expense updates and notes will appear here.</p>
                            <button type="button" class="btn btn-primary btn-sm" style="margin-top:12px;" onclick="window.expensesApp.openAddNoteModal(${exp.id})">
                                Add Note
                            </button>
                        </div>
                    `;
                } else {
                    const getActivityType = (text) => {
                        const t = (text || '').toLowerCase();
                        if (t.includes('created') || t.includes('recorded')) return 'Expense Created';
                        if (t.includes('receipt') || t.includes('attached')) return 'Receipt Added';
                        if (t.includes('reimburs')) return 'Reimbursement Updated';
                        if (t.includes('invoice') || t.includes('linked')) return 'Invoice Updated';
                        if (t.includes('billable')) return 'Billing Status Changed';
                        if (t.includes('import')) return 'Expense Imported';
                        if (t.includes('duplicat')) return 'Expense Duplicated';
                        if (t.includes('note')) return 'Note Added';
                        return 'Activity';
                    };

                    const timelineItems = activities.map(a => `
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-title">${getActivityType(a.text)}</span>
                                    <span class="timeline-time">${a.date}</span>
                                </div>
                                <div class="timeline-desc">${escapeHtml(a.text)}</div>
                                <div class="timeline-meta">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    ${escapeHtml(a.user)}
                                </div>
                            </div>
                        </div>
                    `).join('');

                    body.innerHTML = `
                        <div style="margin-bottom:12px;text-align:right;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.openAddNoteModal(${exp.id})">
                                + Add Note
                            </button>
                        </div>
                        <div class="timeline-list">
                            ${timelineItems}
                        </div>
                    `;
                }
            }
        },

        openAddNoteModal: function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp) return;

            const html = `
                <div class="expenses-modal-header">
                    <h3 class="expenses-modal-title">Add Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.expensesApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="expenses-modal-body">
                    <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px;">Note for <strong>${escapeHtml(exp.title)}</strong></div>
                    <div>
                        <label class="form-label">Note Content *</label>
                        <textarea class="input-control" id="expNoteContent" style="height:100px;padding:10px 12px;resize:none;" placeholder="Write a note about this expense..." required></textarea>
                    </div>
                </div>
                <div class="expenses-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.expensesApp.submitAddNote(${exp.id})">Save Note</button>
                </div>
            `;
            this.openModal(html);
        },

        submitAddNote: async function (expId) {
            const content = document.getElementById('expNoteContent')?.value?.trim();
            if (!content) return;

            const res = await expenseApi('add_note', { id: expId, content }, { method: 'POST' });
            if (res && res.success) {
                this.closeModal();
                this.showToast('Note added to expense activity.', 'success');
                if (activeExpenseId === expId) {
                    await this.openDrawer(expId);
                    this.switchDrawerTab('activity');
                }
            } else {
                this.showToast(res.message || 'Failed to save note.', 'warning');
            }
        },

        toggleInvoiced: async function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp) return;

            if (exp.billingStatus === 'Invoiced') {
                const res = await expenseApi('link_invoice', { id: expId, invoice_status: 'Not Invoiced' }, { method: 'POST' });
                if (res && res.success) {
                    this.showToast('Expense marked as Not Invoiced.', 'info');
                    await updateKPIs();
                    await this.renderTable();
                    if (activeExpenseId === expId) await this.openDrawer(expId);
                }
            } else {
                this.openLinkInvoiceModal(expId);
            }
        },

        toggleReimbursed: async function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp) return;

            const newStatus = (exp.reimbursementStatus === 'Reimbursed') ? 'Pending' : 'Reimbursed';
            const res = await expenseApi('update_reimbursement', { id: expId, reimbursement_status: newStatus }, { method: 'POST' });
            if (res && res.success) {
                this.showToast(`Reimbursement status: ${newStatus}.`, 'info');
                await this.renderTable();
                if (activeExpenseId === expId) await this.openDrawer(expId);
            } else {
                this.showToast(res.message || 'Failed to update reimbursement status.', 'warning');
            }
        },

        toggleBillable: async function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp) return;

            const newType = exp.billable ? 'Non-Billable' : 'Billable';
            const res = await expenseApi('update', { id: expId, billing_type: newType }, { method: 'POST' });
            if (res && res.success) {
                this.showToast(`Expense marked as ${newType}.`, 'info');
                await updateKPIs();
                await this.renderTable();
                if (activeExpenseId === expId) await this.openDrawer(expId);
            } else {
                this.showToast(res.message || 'Failed to update billing type.', 'warning');
            }
        },

        // Top-Level Modal Injector
        openModal: function (htmlContent) {
            const overlay = document.getElementById('expActionModal');
            const card = document.getElementById('expActionModalCard');
            if (!overlay || !card) return;

            card.innerHTML = htmlContent;
            overlay.style.display = 'flex';
            overlay.classList.add('show');
        },

        closeModal: function () {
            const overlay = document.getElementById('expActionModal');
            if (overlay) {
                overlay.classList.remove('show');
                overlay.style.display = 'none';
            }
        },

        // Record Expense Modal
        openRecordModal: function () {
            const today = new Date().toISOString().split('T')[0];

            let ownerOptionsHtml = '';
            cachedUsers.forEach(u => {
                const name = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email;
                ownerOptionsHtml += `<option value="${u.id}">${escapeHtml(name)}</option>`;
            });

            let companyDatalist = '<datalist id="companiesListOptions">';
            cachedCompanies.forEach(c => { companyDatalist += `<option value="${escapeHtml(c.name)}">`; });
            companyDatalist += '</datalist>';

            let projectDatalist = '<datalist id="projectsListOptions">';
            cachedProjects.forEach(p => { projectDatalist += `<option value="${escapeHtml(p.name)}">`; });
            projectDatalist += '</datalist>';

            const html = `
                <div class="expenses-modal-header">
                    <h3 class="expenses-modal-title">Record Expense</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.expensesApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.expensesApp.submitRecordExpense(event)">
                    <div class="expenses-modal-body">
                        <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Expense Name *</label>
                                <input type="text" class="input-control" id="newExpTitle" placeholder="e.g. Client Dinner or Software License" required>
                            </div>
                            <div>
                                <label class="form-label">Category *</label>
                                <select class="input-control" id="newExpCategory" required>
                                    <option value="Travel">Travel</option>
                                    <option value="Meals">Meals</option>
                                    <option value="Software">Software</option>
                                    <option value="Telephone">Telephone</option>
                                    <option value="Office Supplies">Office Supplies</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Automobile">Automobile</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Amount ($) *</label>
                                <input type="number" step="0.01" min="0.01" class="input-control" id="newExpAmount" placeholder="150.00" required>
                            </div>
                            <div>
                                <label class="form-label">Expense Date *</label>
                                <input type="date" class="input-control" id="newExpDate" value="${today}" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Company / Client</label>
                                <input type="text" class="input-control" id="newExpCompany" placeholder="e.g. Acme Corp" list="companiesListOptions">
                                ${companyDatalist}
                            </div>
                            <div>
                                <label class="form-label">Related Project</label>
                                <input type="text" class="input-control" id="newExpProject" placeholder="e.g. Enterprise Expansion" list="projectsListOptions">
                                ${projectDatalist}
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Billing Type *</label>
                                <select class="input-control" id="newExpBillingType">
                                    <option value="Billable">Billable</option>
                                    <option value="Non-Billable">Non-Billable</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Invoice Status</label>
                                <select class="input-control" id="newExpInvoiceStatus">
                                    <option value="Not Invoiced">Not Invoiced</option>
                                    <option value="Invoiced">Invoiced</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Reimbursement</label>
                                <select class="input-control" id="newExpReimburseStatus">
                                    <option value="N/A">N/A</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Reimbursed">Reimbursed</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Merchant / Vendor</label>
                                <input type="text" class="input-control" id="newExpMerchant" placeholder="e.g. Delta Air Lines">
                            </div>
                            <div>
                                <label class="form-label">Owner *</label>
                                <select class="input-control" id="newExpOwner" required>
                                    ${ownerOptionsHtml}
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Payment Method</label>
                                <select class="input-control" id="newExpPaymentMethod">
                                    <option>Corporate Visa •••• 4242</option>
                                    <option>Corporate Mastercard •••• 8812</option>
                                    <option>Corporate ACH Bank Wire</option>
                                    <option>Personal Reimbursement</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Reference Number</label>
                                <input type="text" class="input-control" id="newExpRefNumber" placeholder="Leave empty for auto EXP-YYYY-XXXX">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Receipt Upload (PDF, PNG, JPG - max 10MB)</label>
                            <input type="file" class="input-control" id="newExpReceiptFile" accept=".pdf,.png,.jpg,.jpeg,.webp">
                        </div>
                        <div>
                            <label class="form-label">Description / Internal Notes</label>
                            <textarea class="input-control" id="newExpDesc" rows="3" style="height:70px;resize:none;" placeholder="Purpose of spending..."></textarea>
                        </div>
                    </div>
                    <div class="expenses-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitNewExp">Save Expense</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitRecordExpense: async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitNewExp');
            if (btn) btn.disabled = true;

            const title = document.getElementById('newExpTitle').value.trim();
            const category = document.getElementById('newExpCategory').value;
            const amount = parseFloat(document.getElementById('newExpAmount').value) || 0;
            const date = document.getElementById('newExpDate').value;
            const company = document.getElementById('newExpCompany').value.trim();
            const project = document.getElementById('newExpProject').value.trim();
            const billingType = document.getElementById('newExpBillingType').value;
            const invoiceStatus = document.getElementById('newExpInvoiceStatus').value;
            const reimbursementStatus = document.getElementById('newExpReimburseStatus').value;
            const merchant = document.getElementById('newExpMerchant').value.trim();
            const ownerId = document.getElementById('newExpOwner').value;
            const paymentMethod = document.getElementById('newExpPaymentMethod').value;
            const refNumber = document.getElementById('newExpRefNumber').value.trim();
            const desc = document.getElementById('newExpDesc').value.trim();
            const receiptInput = document.getElementById('newExpReceiptFile');

            if (!title || amount <= 0 || !date) {
                if (btn) btn.disabled = false;
                return;
            }

            const formData = new FormData();
            formData.append('title', title);
            formData.append('category', category);
            formData.append('amount', amount);
            formData.append('expense_date', date);
            formData.append('company', company);
            formData.append('project', project);
            formData.append('billing_type', billingType);
            formData.append('invoice_status', invoiceStatus);
            formData.append('reimbursement_status', reimbursementStatus);
            formData.append('merchant', merchant);
            formData.append('owner_id', ownerId);
            formData.append('payment_method', paymentMethod);
            if (refNumber) formData.append('reference_number', refNumber);
            formData.append('description', desc);

            if (receiptInput && receiptInput.files && receiptInput.files[0]) {
                formData.append('receipt_file', receiptInput.files[0]);
            }

            const res = await expenseApi('create', formData, { method: 'POST' });
            if (btn) btn.disabled = false;

            if (res && res.success) {
                this.closeModal();
                await updateKPIs();
                await this.renderTable();
                this.showToast(`Expense "${title}" recorded successfully.`, 'success');
            } else {
                this.showToast(res.message || 'Failed to record expense.', 'warning');
            }
        },

        // Edit Expense Modal
        openEditModal: async function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp) return;

            let ownerOptionsHtml = '';
            cachedUsers.forEach(u => {
                const name = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email;
                const isSel = (exp.owner_id && u.id === exp.owner_id) || (exp.owner && name === exp.owner);
                ownerOptionsHtml += `<option value="${u.id}" ${isSel ? 'selected' : ''}>${escapeHtml(name)}</option>`;
            });

            let companyDatalist = '<datalist id="editCompaniesList">';
            cachedCompanies.forEach(c => { companyDatalist += `<option value="${escapeHtml(c.name)}">`; });
            companyDatalist += '</datalist>';

            let projectDatalist = '<datalist id="editProjectsList">';
            cachedProjects.forEach(p => { projectDatalist += `<option value="${escapeHtml(p.name)}">`; });
            projectDatalist += '</datalist>';

            const html = `
                <div class="expenses-modal-header">
                    <h3 class="expenses-modal-title">Edit Expense: ${escapeHtml(exp.title)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.expensesApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.expensesApp.submitEditExpense(event, ${exp.id})">
                    <div class="expenses-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Expense Name *</label>
                            <input type="text" class="input-control" id="editExpTitle" value="${escapeHtml(exp.title)}" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Amount ($) *</label>
                                <input type="number" step="0.01" min="0.01" class="input-control" id="editExpAmount" value="${exp.amount}" required>
                            </div>
                            <div>
                                <label class="form-label">Category *</label>
                                <select class="input-control" id="editExpCategory">
                                    <option ${exp.category==='Travel'?'selected':''}>Travel</option>
                                    <option ${exp.category==='Meals'?'selected':''}>Meals</option>
                                    <option ${exp.category==='Software'?'selected':''}>Software</option>
                                    <option ${exp.category==='Telephone'?'selected':''}>Telephone</option>
                                    <option ${exp.category==='Office Supplies'?'selected':''}>Office Supplies</option>
                                    <option ${exp.category==='Marketing'?'selected':''}>Marketing</option>
                                    <option ${exp.category==='Automobile'?'selected':''}>Automobile</option>
                                    <option ${exp.category==='Equipment'?'selected':''}>Equipment</option>
                                    <option ${exp.category==='Other'?'selected':''}>Other</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Expense Date *</label>
                                <input type="date" class="input-control" id="editExpDate" value="${exp.date}" required>
                            </div>
                            <div>
                                <label class="form-label">Merchant / Vendor</label>
                                <input type="text" class="input-control" id="editExpMerchant" value="${escapeHtml(exp.merchant || '')}">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Company / Client</label>
                                <input type="text" class="input-control" id="editExpCompany" value="${escapeHtml(exp.company || '')}" list="editCompaniesList">
                                ${companyDatalist}
                            </div>
                            <div>
                                <label class="form-label">Related Project</label>
                                <input type="text" class="input-control" id="editExpProject" value="${escapeHtml(exp.project || '')}" list="editProjectsList">
                                ${projectDatalist}
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Billing Type</label>
                                <select class="input-control" id="editExpBillingType">
                                    <option value="Billable" ${exp.billable ? 'selected' : ''}>Billable</option>
                                    <option value="Non-Billable" ${!exp.billable ? 'selected' : ''}>Non-Billable</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Invoice Status</label>
                                <select class="input-control" id="editExpInvoiceStatus">
                                    <option value="Not Invoiced" ${exp.billingStatus==='Not Invoiced'?'selected':''}>Not Invoiced</option>
                                    <option value="Invoiced" ${exp.billingStatus==='Invoiced'?'selected':''}>Invoiced</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Reimbursement</label>
                                <select class="input-control" id="editExpReimburseStatus">
                                    <option value="N/A" ${exp.reimbursementStatus==='N/A'?'selected':''}>N/A</option>
                                    <option value="Pending" ${exp.reimbursementStatus==='Pending'?'selected':''}>Pending</option>
                                    <option value="Reimbursed" ${exp.reimbursementStatus==='Reimbursed'?'selected':''}>Reimbursed</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Owner</label>
                                <select class="input-control" id="editExpOwner">
                                    ${ownerOptionsHtml}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Payment Method</label>
                                <input type="text" class="input-control" id="editExpPaymentMethod" value="${escapeHtml(exp.paymentMethod || '')}">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Replace Receipt File (Optional)</label>
                            <input type="file" class="input-control" id="editExpReceiptFile" accept=".pdf,.png,.jpg,.jpeg,.webp">
                            ${exp.receipt ? `<div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Current: ${escapeHtml(exp.receipt)}</div>` : ''}
                        </div>
                        <div>
                            <label class="form-label">Description / Internal Notes</label>
                            <textarea class="input-control" id="editExpDesc" rows="3" style="height:70px;resize:none;">${escapeHtml(exp.description || '')}</textarea>
                        </div>
                    </div>
                    <div class="expenses-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitEditExp">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditExpense: async function (e, expId) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitEditExp');
            if (btn) btn.disabled = true;

            const formData = new FormData();
            formData.append('id', expId);
            formData.append('title', document.getElementById('editExpTitle').value.trim());
            formData.append('amount', parseFloat(document.getElementById('editExpAmount').value) || 0);
            formData.append('category', document.getElementById('editExpCategory').value);
            formData.append('expense_date', document.getElementById('editExpDate').value);
            formData.append('merchant', document.getElementById('editExpMerchant').value.trim());
            formData.append('company', document.getElementById('editExpCompany').value.trim());
            formData.append('project', document.getElementById('editExpProject').value.trim());
            formData.append('billing_type', document.getElementById('editExpBillingType').value);
            formData.append('invoice_status', document.getElementById('editExpInvoiceStatus').value);
            formData.append('reimbursement_status', document.getElementById('editExpReimburseStatus').value);
            formData.append('owner_id', document.getElementById('editExpOwner').value);
            formData.append('payment_method', document.getElementById('editExpPaymentMethod').value.trim());
            formData.append('description', document.getElementById('editExpDesc').value.trim());

            const fileInput = document.getElementById('editExpReceiptFile');
            if (fileInput && fileInput.files && fileInput.files[0]) {
                formData.append('receipt_file', fileInput.files[0]);
            }

            const res = await expenseApi('update', formData, { method: 'POST' });
            if (btn) btn.disabled = false;

            if (res && res.success) {
                this.closeModal();
                await updateKPIs();
                await this.renderTable();
                if (activeExpenseId === expId) await this.openDrawer(expId);
                this.showToast('Expense updated successfully.', 'success');
            } else {
                this.showToast(res.message || 'Failed to update expense.', 'warning');
            }
        },

        // Custom Delete Expense Modal (Zero confirm())
        openDeleteModal: function (expId) {
            const exp = expenses.find(x => x.id === expId) || activeExpenseData;
            if (!exp) return;

            const html = `
                <div class="expenses-modal-header">
                    <h3 class="expenses-modal-title">Delete Expense?</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.expensesApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="expenses-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;margin:0;">
                        Are you sure you want to delete expense <strong>"${escapeHtml(exp.title)}"</strong> (${escapeHtml(exp.refNumber || '')})?<br>
                        This will permanently remove the record and any attached receipts.
                    </p>
                </div>
                <div class="expenses-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" style="background:#DC2626;border-color:#DC2626;" onclick="window.expensesApp.confirmDelete(${exp.id})">Delete Expense</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmDelete: async function (expId) {
            const res = await expenseApi('delete', { id: expId }, { method: 'POST' });
            if (res && res.success) {
                selectedIds.delete(expId);
                this.closeModal();
                if (activeExpenseId === expId) this.closeDrawer();
                await updateKPIs();
                await this.renderTable();
                this.showToast('Expense deleted.', 'info');
            } else {
                this.showToast(res.message || 'Failed to delete expense.', 'warning');
            }
        },

        duplicateExpense: async function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp) return;

            const copyPayload = {
                title: `${exp.title} (Copy)`,
                category: exp.category,
                amount: exp.amount,
                expense_date: exp.date,
                merchant: exp.merchant,
                company: exp.company,
                project: exp.project,
                billing_type: exp.billing_type || (exp.billable ? 'Billable' : 'Non-Billable'),
                invoice_status: 'Not Invoiced',
                reimbursement_status: exp.reimbursementStatus || 'N/A',
                payment_method: exp.paymentMethod,
                description: exp.description
            };

            const res = await expenseApi('create', copyPayload, { method: 'POST' });
            if (res && res.success) {
                await updateKPIs();
                await this.renderTable();
                this.showToast(`Duplicated expense as "${copyPayload.title}".`, 'info');
            } else {
                this.showToast(res.message || 'Failed to duplicate expense.', 'warning');
            }
        },

        openLinkInvoiceModal: function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp) return;

            const html = `
                <div class="expenses-modal-header">
                    <h3 class="expenses-modal-title">Link Expense to Invoice</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.expensesApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="expenses-modal-body">
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Client Company</label>
                        <input type="text" class="input-control" value="${escapeHtml(exp.company)}" readonly style="background:#F8FAFC;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Client Invoice Reference # *</label>
                        <input type="text" class="input-control" id="linkInvoiceRefInput" placeholder="e.g. INV-2026-081" value="${escapeHtml(exp.invoiceId || '')}" required>
                    </div>
                </div>
                <div class="expenses-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.expensesApp.confirmLinkInvoice(${exp.id})">Attach to Invoice</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmLinkInvoice: async function (expId) {
            const inv = document.getElementById('linkInvoiceRefInput')?.value?.trim();
            if (!inv) return;

            const res = await expenseApi('link_invoice', { id: expId, invoice_status: 'Invoiced', invoice_reference: inv }, { method: 'POST' });
            if (res && res.success) {
                this.closeModal();
                await updateKPIs();
                await this.renderTable();
                if (activeExpenseId === expId) await this.openDrawer(expId);
                this.showToast(`Expense linked to invoice ${inv}.`, 'success');
            } else {
                this.showToast(res.message || 'Failed to link invoice.', 'warning');
            }
        },

        markReimbursed: async function (expId) {
            const res = await expenseApi('update_reimbursement', { id: expId, reimbursement_status: 'Reimbursed' }, { method: 'POST' });
            if (res && res.success) {
                await this.renderTable();
                if (activeExpenseId === expId) await this.openDrawer(expId);
                this.showToast('Expense marked as Reimbursed.', 'success');
            }
        },

        previewReceipt: function (expId) {
            const exp = activeExpenseData && activeExpenseData.id === expId ? activeExpenseData : expenses.find(x => x.id === expId);
            if (!exp || !exp.receipt) return;

            const html = `
                <div class="expenses-modal-header">
                    <h3 class="expenses-modal-title">Receipt Proof: ${escapeHtml(exp.merchant)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.expensesApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="expenses-modal-body">
                    <div style="background:#F8FAFC;padding:24px;border:1px solid var(--border-divider);border-radius:8px;text-align:center;">
                        <svg width="48" height="48" fill="none" stroke="var(--primary)" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px auto;display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <h4 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 4px 0;">${escapeHtml(exp.receipt)}</h4>
                        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">Amount: $${Number(exp.amount).toFixed(2)} • Date: ${exp.date}</div>
                        <span class="badge-exp badge-invoiced">Verified Attachment</span>
                    </div>
                </div>
                <div class="expenses-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeModal()">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.expensesApp.downloadReceipt(${exp.id})">Download Receipt</button>
                </div>
            `;
            this.openModal(html);
        },

        downloadReceipt: function (expId) {
            window.location.href = `api/expenses.php?action=download_receipt&id=${encodeURIComponent(expId)}`;
        },

        // Import Expenses Modal
        openImportModal: function () {
            const html = `
                <div class="expenses-modal-header">
                    <h3 class="expenses-modal-title">Import Expenses</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.expensesApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="expenses-modal-body">
                    <div style="border:2px dashed var(--border-card);padding:24px;border-radius:8px;text-align:center;margin-bottom:14px;background:#F8FAFC;">
                        <input type="file" id="csvFileInput" accept=".csv" style="display:none;" onchange="window.expensesApp.handleCSVFile(this)">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('csvFileInput').click()">Choose File</button>
                        <div style="font-size:12.5px;color:var(--text-secondary);margin-top:8px;" id="csvFileName">Select a CSV file containing expense records</div>
                    </div>
                    <div style="font-size:12.5px;color:var(--text-secondary);line-height:1.5;">
                        Required headers: <code>Title, Category, Amount, Date, Company, Owner</code><br>
                        Accepted formats: <code>.csv</code> (UTF-8 formatted).
                    </div>
                    <div style="margin-top:14px;text-align:right;">
                        <button type="button" class="btn-clear-filter-text" style="font-size:12px;color:var(--primary);font-weight:600;background:none;border:none;cursor:pointer;" onclick="window.expensesApp.downloadCSVTemplate()">
                            Download CSV Template
                        </button>
                    </div>
                </div>
                <div class="expenses-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.expensesApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnConfirmImport" onclick="window.expensesApp.confirmImportCSV()">Import Expenses</button>
                </div>
            `;
            this.openModal(html);
        },

        handleCSVFile: function (input) {
            if (input.files && input.files[0]) {
                const fn = document.getElementById('csvFileName');
                if (fn) fn.textContent = `Selected: ${input.files[0].name}`;
            }
        },

        downloadCSVTemplate: function () {
            const template = 'Title,Category,Amount,Date,Company,Project,Merchant,Owner,BillingType\n"Client Onsite Scoping","Travel",1450.00,"' + new Date().toISOString().split('T')[0] + '","Acme Corp","Enterprise Expansion","Delta Air Lines","","Billable"';
            const blob = new Blob([template], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('href', url);
            a.setAttribute('download', 'NexFlow_expenses_template.csv');
            a.click();
            window.URL.revokeObjectURL(url);
            this.showToast('CSV template downloaded.', 'info');
        },

        confirmImportCSV: async function () {
            const fileInput = document.getElementById('csvFileInput');
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                this.showToast('Please select a CSV file first.', 'warning');
                return;
            }

            const btn = document.getElementById('btnConfirmImport');
            if (btn) btn.disabled = true;

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            const res = await expenseApi('import_csv', formData, { method: 'POST' });
            if (btn) btn.disabled = false;

            if (res && res.success) {
                this.closeModal();
                await updateKPIs();
                await this.renderTable();
                this.showToast(`Imported ${res.data?.imported_count || 0} expense records successfully.`, 'success');
            } else {
                this.showToast(res.message || 'CSV Import failed.', 'warning');
            }
        },

        // Filtered Export CSV via API
        exportCSV: function () {
            window.location.href = 'api/expenses.php?action=export_csv';
        },

        showToast: function (msg, type = 'info') {
            const container = document.getElementById('expensesToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `expenses-toast ${type}`;
            toast.innerHTML = `<span>${escapeHtml(msg)}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.25s ease';
                setTimeout(() => toast.remove(), 250);
            }, 3200);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.expensesApp.init());
    } else {
        window.expensesApp.init();
    }
})();
