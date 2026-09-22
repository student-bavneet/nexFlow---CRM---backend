/**
 * NexFlow CRM — Invoices Management Controller
 * Database-backed implementation connecting to admin/api/invoices.php.
 * Handles live SQL data loading, line items calculations, payment recording,
 * view drawer preview, server-side CSV export, and filtering.
 */

(function () {
    'use strict';

    const API_URL = 'api/invoices.php';

    // Module State
    let invoices = [];
    let referenceOptions = {
        companies: [],
        contacts: [],
        projects: [],
        deals: [],
        users: [],
        currency: 'USD ($)',
        next_invoice_number: 'INV-001'
    };
    let orgCurrency = 'USD ($)';
    let selectedIds = new Set();
    let currentStatusFilter = 'All';
    let currentCustomerFilter = 'All';
    let currentOwnerFilter = 'All';
    let currentSavedView = 'all';
    let currentSearch = '';
    let currentSort = 'newest';
    let activeInvoiceId = null;
    let editingInvoiceId = null;
    let formLineItems = [];
    let currentPage = 1;
    const pageSize = 8;

    function formatMoney(amt, curr) {
        const val = Number(amt || 0);
        const code = curr || orgCurrency || 'USD ($)';
        let symbol = '$';
        if (code.includes('₹') || code.includes('INR')) symbol = '₹';
        else if (code.includes('€') || code.includes('EUR')) symbol = '€';
        else if (code.includes('£') || code.includes('GBP')) symbol = '£';
        else if (code.includes('$') || code.includes('USD')) symbol = '$';

        if (symbol === '₹') {
            return symbol + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        return symbol + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

    // Public Controller Object
    window.invoicesApp = {
        get activeInvoiceId() {
            return activeInvoiceId;
        },

        init: function () {
            this.setupEventListeners();
            this.loadReferenceOptions().then(() => {
                this.updateKPIs();
                this.renderTable();
            });
        },

        setupEventListeners: function () {
            document.addEventListener('click', function (e) {
                if (!e.target.closest('#invoicesRowDropdown') && !e.target.closest('.inv-row-menu-btn')) {
                    const menu = document.getElementById('invoicesRowDropdown');
                    if (menu) menu.classList.remove('show');
                }
                if (!e.target.closest('#invoicesFilterPopover') && !e.target.closest('#btnInvoiceFilter')) {
                    const pop = document.getElementById('invoicesFilterPopover');
                    if (pop) pop.classList.remove('show');
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('invoicesActionModal');
                    if (modal && (modal.classList.contains('show') || modal.style.display === 'flex')) {
                        window.invoicesApp.closeModal();
                        e.stopPropagation();
                        return;
                    }
                    const formDrawer = document.getElementById('invoiceFormDrawer');
                    if (formDrawer && (formDrawer.classList.contains('show') || formDrawer.style.display === 'block')) {
                        window.invoicesApp.closeFormDrawer();
                        e.stopPropagation();
                        return;
                    }
                    const viewDrawer = document.getElementById('invoiceViewDrawer');
                    if (viewDrawer && (viewDrawer.classList.contains('show') || viewDrawer.style.display === 'block')) {
                        window.invoicesApp.closeViewDrawer();
                        e.stopPropagation();
                        return;
                    }
                }
            });
        },

        loadReferenceOptions: function () {
            return fetch(`${API_URL}?action=reference_options`)
                .then(r => r.json())
                .then(res => {
                    if (res && res.success && res.data) {
                        referenceOptions = res.data;
                        orgCurrency = referenceOptions.currency || 'USD ($)';
                        this.populateFilterDropdowns();
                        this.populateDatalists();
                    }
                })
                .catch(err => {
                    console.error('Failed to load invoice reference options:', err);
                });
        },

        populateFilterDropdowns: function () {
            const custSelect = document.getElementById('filterCustomer');
            if (custSelect) {
                const curVal = custSelect.value;
                custSelect.innerHTML = '<option value="All">All Customers</option>';
                (referenceOptions.companies || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    custSelect.appendChild(opt);
                });
                if (curVal) custSelect.value = curVal;
            }

            const ownerSelect = document.getElementById('filterOwner');
            if (ownerSelect) {
                const curVal = ownerSelect.value;
                ownerSelect.innerHTML = '<option value="All">All Owners</option>';
                (referenceOptions.users || []).forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.textContent = `${u.first_name || ''} ${u.last_name || ''}`.trim();
                    ownerSelect.appendChild(opt);
                });
                if (curVal) ownerSelect.value = curVal;
            }
        },

        populateDatalists: function () {
            const compList = document.getElementById('invoiceCompanyList');
            if (compList) {
                compList.innerHTML = (referenceOptions.companies || []).map(c => 
                    `<option value="${c.name}" data-id="${c.id}">${c.name}</option>`
                ).join('');
            }

            const contList = document.getElementById('invoiceContactList');
            if (contList) {
                contList.innerHTML = (referenceOptions.contacts || []).map(ct => {
                    const name = `${ct.first_name || ''} ${ct.last_name || ''}`.trim();
                    return `<option value="${name}" data-id="${ct.id}" data-company-id="${ct.company_id || ''}">${name}</option>`;
                }).join('');
            }

            const dealList = document.getElementById('invoiceDealProjectList');
            if (dealList) {
                let html = '';
                (referenceOptions.projects || []).forEach(p => {
                    html += `<option value="${p.name}" data-type="project" data-id="${p.id}">Project: ${p.name}</option>`;
                });
                (referenceOptions.deals || []).forEach(d => {
                    html += `<option value="${d.title}" data-type="deal" data-id="${d.id}">Deal: ${d.title}</option>`;
                });
                dealList.innerHTML = html;
            }
        },

        updateKPIs: function () {
            fetch(`${API_URL}?action=summary`)
                .then(r => r.json())
                .then(res => {
                    if (res && res.success && res.data) {
                        const data = res.data;
                        orgCurrency = data.currency || orgCurrency;

                        const elTot = document.getElementById('kpiTotalInvoiced');
                        const elPaid = document.getElementById('kpiPaid');
                        const elOut = document.getElementById('kpiOutstanding');
                        const elOvd = document.getElementById('kpiOverdue');
                        const elDraft = document.getElementById('kpiDraftPending');
                        const elBadge = document.getElementById('invoicesTotalBadge');

                        if (elTot) elTot.textContent = formatMoney(data.total_invoiced, orgCurrency);
                        if (elPaid) elPaid.textContent = formatMoney(data.total_paid, orgCurrency);
                        if (elOut) elOut.textContent = formatMoney(data.total_outstanding, orgCurrency);
                        if (elOvd) elOvd.textContent = formatMoney(data.total_overdue, orgCurrency);
                        if (elDraft) elDraft.textContent = `${data.count_draft_pending} Draft`;
                        if (elBadge) elBadge.textContent = `${data.count_total} invoice${data.count_total !== 1 ? 's' : ''}`;

                        this.updateStatusCounts(data.status_counts || {});
                    }
                })
                .catch(err => {
                    console.error('Failed to load invoice summary KPIs:', err);
                });
        },

        updateStatusCounts: function (counts) {
            const statuses = ['All', 'Draft', 'Sent', 'Viewed', 'Pending', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled'];
            statuses.forEach(st => {
                const key = st.replace(/\s+/g, '');
                const count = counts[st] !== undefined ? counts[st] : 0;
                const label = (st === 'All') ? 'All Invoices' : st;

                const elOpt = document.getElementById(`optStatus_${key}`);
                if (elOpt) {
                    elOpt.textContent = `${label} (${count})`;
                }
            });
        },

        goToPage: function (p) {
            currentPage = p;
            this.renderTable();
        },

        filterByStatus: function (status) {
            currentStatusFilter = status || 'All';
            currentPage = 1;
            const selectEl = document.getElementById('invoiceStatusSelect');
            if (selectEl && selectEl.value !== currentStatusFilter) {
                selectEl.value = currentStatusFilter;
            }
            this.renderTable();
        },

        handleSearch: function (val) {
            currentSearch = (val || '').toLowerCase().trim();
            currentPage = 1;
            this.renderTable();
        },

        handleSort: function (val) {
            currentSort = val;
            currentPage = 1;
            this.renderTable();
        },

        handleSavedView: function (val) {
            currentSavedView = val;
            currentPage = 1;
            this.renderTable();
        },

        toggleFilterPopover: function (e) {
            if (e) e.stopPropagation();
            const pop = document.getElementById('invoicesFilterPopover');
            if (pop) pop.classList.toggle('show');
        },

        closeFilterPopover: function () {
            const pop = document.getElementById('invoicesFilterPopover');
            if (pop) pop.classList.remove('show');
        },

        applyFilters: function () {
            const cSelect = document.getElementById('filterCustomer');
            const oSelect = document.getElementById('filterOwner');
            currentCustomerFilter = cSelect ? cSelect.value : 'All';
            currentOwnerFilter = oSelect ? oSelect.value : 'All';
            currentPage = 1;

            this.closeFilterPopover();
            this.renderTable();
            this.showToast('Invoice filters applied.', 'info');
        },

        clearFilters: function () {
            const cSelect = document.getElementById('filterCustomer');
            const oSelect = document.getElementById('filterOwner');
            if (cSelect) cSelect.value = 'All';
            if (oSelect) oSelect.value = 'All';
            currentCustomerFilter = 'All';
            currentOwnerFilter = 'All';
            currentSearch = '';
            const searchInput = document.getElementById('invoiceSearchInput');
            if (searchInput) searchInput.value = '';
            currentPage = 1;

            this.closeFilterPopover();
            this.renderTable();
            this.showToast('Filters reset.', 'info');
        },

        renderTable: function () {
            const tbody = document.getElementById('invoicesTbody');
            if (!tbody) return;

            const params = new URLSearchParams({
                action: 'list',
                page: currentPage,
                per_page: pageSize,
                search: currentSearch,
                status: currentStatusFilter,
                customer: currentCustomerFilter,
                owner: currentOwnerFilter,
                sort: currentSort,
                saved_view: currentSavedView
            });

            fetch(`${API_URL}?${params.toString()}`)
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success) {
                        tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:24px;color:#DC2626;">Failed to load invoices.</td></tr>`;
                        return;
                    }

                    const items = res.data.items || [];
                    const pagination = res.data.pagination || { total: 0, page: 1, per_page: pageSize, total_pages: 1 };
                    invoices = items;

                    const totalItems = pagination.total;
                    const totalPages = pagination.total_pages;
                    currentPage = pagination.page;

                    const startIndex = totalItems > 0 ? (currentPage - 1) * pageSize : 0;
                    const pagingRange = document.getElementById('pagingRange');
                    const pagingTotal = document.getElementById('pagingTotal');
                    const controls = document.getElementById('paginationControls');

                    if (pagingRange) pagingRange.textContent = totalItems > 0 ? `${startIndex + 1}–${Math.min(startIndex + pageSize, totalItems)}` : '0';
                    if (pagingTotal) pagingTotal.textContent = totalItems;

                    const container = document.querySelector('.pagination-container');
                    if (container) {
                        container.style.display = totalItems > pageSize ? 'flex' : 'none';
                    }

                    if (controls) {
                        if (totalItems === 0) {
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
                                <button class="pagination-btn" id="prevPageBtn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.invoicesApp.goToPage(${currentPage - 1})">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                                </button>
                            `;

                            const pageNumbers = getPaginationPages(currentPage, totalPages);
                            pageNumbers.forEach(p => {
                                if (p === '...') {
                                    html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
                                } else {
                                    const isActive = (p === currentPage);
                                    html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.invoicesApp.goToPage(${p})">${p}</button>`;
                                }
                            });

                            html += `
                                <button class="pagination-btn" id="nextPageBtn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.invoicesApp.goToPage(${currentPage + 1})">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                                </button>
                            `;
                            controls.innerHTML = html;
                        }
                    }

                    // Empty State Handling
                    if (items.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="11" style="text-align:center;padding:48px 20px;">
                                    <div style="max-width:360px;margin:0 auto;display:flex;flex-direction:column;align-items:center;gap:10px;">
                                        <div style="width:48px;height:48px;border-radius:50%;background:#F1F5F9;color:#64748B;display:flex;align-items:center;justify-content:center;">
                                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16l3-2 3 2 3-2 3 2 4-2.5V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/></svg>
                                        </div>
                                        <h4 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">No invoices found</h4>
                                        <p style="font-size:13px;color:var(--text-secondary);margin:0;">Try adjusting your search terms or clearing active filters.</p>
                                        <div style="display:flex;gap:8px;margin-top:4px;">
                                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.invoicesApp.clearFilters()">Clear Filters</button>
                                            <button type="button" class="btn btn-primary btn-xs" onclick="window.invoicesApp.openCreateDrawer()">+ Create Invoice</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        `;
                        return;
                    }

                    // Render Data Rows
                    tbody.innerHTML = items.map(inv => {
                        const statusClass = inv.status.toLowerCase().replace(/\s+/g, '-');
                        const badgeClass = `inv-${statusClass}`;
                        const tot = Number(inv.total || 0);
                        const pd = Number(inv.amountPaid !== undefined ? inv.amountPaid : (inv.paid || 0));
                        const bal = Number(inv.balanceDue !== undefined ? inv.balanceDue : (inv.balance || 0));

                        return `
                            <tr data-id="${inv.id}">
                                <td>
                                    <div style="font-weight:700;color:var(--primary);cursor:pointer;" onclick="window.invoicesApp.openViewDrawer('${inv.id}')">
                                        ${inv.number || inv.id}
                                    </div>
                                    <div style="font-size:11.5px;color:var(--text-secondary);">${inv.title || ''}</div>
                                </td>
                                <td>
                                    <div style="font-weight:600;color:var(--text-heading);">${inv.customer || '—'}</div>
                                    <div style="font-size:11.5px;color:var(--text-secondary);">${inv.contact || '—'}</div>
                                </td>
                                <td>
                                    <div style="font-size:12.5px;color:var(--text-body);">${inv.deal || inv.project || '—'}</div>
                                </td>
                                <td style="font-size:12px;color:var(--text-secondary);">${inv.issueDate || '—'}</td>
                                <td style="font-size:12px;color:var(--text-secondary);">${inv.dueDate || '—'}</td>
                                <td style="font-weight:700;color:var(--text-heading);">${formatMoney(tot, inv.currency)}</td>
                                <td style="font-weight:600;color:#047857;">${formatMoney(pd, inv.currency)}</td>
                                <td style="font-weight:700;color:${bal > 0 ? '#DC2626' : 'var(--text-muted)'};">${formatMoney(bal, inv.currency)}</td>
                                <td>
                                    <span class="badge-inv ${badgeClass}">● ${inv.status}</span>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <div class="avatar avatar-xs" style="background-color:${inv.ownerColor || '#2563EB'};font-size:9px;color:#FFF;">${inv.ownerInitials || 'US'}</div>
                                        <span style="font-size:12px;color:var(--text-secondary);">${inv.owner || 'Unassigned'}</span>
                                    </div>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.invoicesApp.openViewDrawer('${inv.id}')">View</button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                })
                .catch(err => {
                    console.error('Failed to load invoices list:', err);
                    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:24px;color:#DC2626;">Error connecting to invoices service.</td></tr>`;
                });
        },

        openViewDrawer: function (invId) {
            if (!invId) return;
            activeInvoiceId = invId;

            const drawer = document.getElementById('invoiceViewDrawer');
            const title = document.getElementById('viewInvTitle');
            const body = document.getElementById('viewInvBody');

            if (title) title.textContent = 'Loading Invoice...';
            if (body) body.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-secondary);">Loading invoice details...</div>';

            if (drawer) {
                drawer.style.display = 'block';
                drawer.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            fetch(`${API_URL}?action=get&id=${encodeURIComponent(invId)}`)
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success || !res.data) {
                        if (body) body.innerHTML = '<div style="padding:32px;text-align:center;color:#DC2626;">Failed to load invoice details.</div>';
                        return;
                    }

                    const inv = res.data;
                    activeInvoiceId = inv.id;

                    if (title) title.textContent = `${inv.number || inv.id} — ${inv.customer || inv.billedTo || 'Invoice'}`;

                    if (body) {
                        const statusClass = inv.status.toLowerCase().replace(/\s+/g, '-');
                        const tot = Number(inv.total || inv.amount || 0);
                        const pd = Number(inv.amountPaid !== undefined ? inv.amountPaid : (inv.paid || 0));
                        const bal = Number(inv.balanceDue !== undefined ? inv.balanceDue : (tot - pd));
                        const invCurr = inv.currency || orgCurrency;

                        body.innerHTML = `
                            <div class="invoice-preview-sheet">
                                <div class="inv-preview-header">
                                    <div class="inv-preview-brand">
                                        <div style="width:34px;height:34px;border-radius:8px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;">
                                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                        </div>
                                        <div>
                                            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">NexFlow CRM</h3>
                                            <span style="font-size:11px;color:var(--text-secondary);">Enterprise Invoicing</span>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:20px;font-weight:800;color:var(--text-heading);letter-spacing:-0.02em;">INVOICE</div>
                                        <div style="font-size:13px;font-weight:700;color:var(--primary);margin-top:2px;">${inv.number || inv.id}</div>
                                        <div style="margin-top:4px;"><span class="badge-inv inv-${statusClass}">● ${inv.status}</span></div>
                                    </div>
                                </div>

                                <div class="inv-preview-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                                    <div>
                                        <span style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">FROM:</span>
                                        <div style="font-weight:700;color:var(--text-heading);margin-top:2px;">NexFlow CRM Solutions</div>
                                        <div style="color:var(--text-secondary);font-size:12px;margin-top:2px;">Enterprise Management Cloud<br>billing@NexFlowcrm.com</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">BILLED TO:</span>
                                        <div style="font-weight:700;color:var(--text-heading);margin-top:2px;">${inv.customer || inv.billedTo || 'Customer'}</div>
                                        <div style="color:var(--text-secondary);font-size:12px;margin-top:2px;">
                                            ${inv.contact ? `Attn: ${inv.contact}<br>` : ''}
                                            ${inv.contactEmail ? `${inv.contactEmail}<br>` : ''}
                                            ${inv.project ? `Project: ${inv.project}<br>` : ''}
                                            ${inv.deal ? `Deal: ${inv.deal}` : ''}
                                        </div>
                                    </div>
                                </div>

                                ${inv.contract ? `
                                    <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#1E40AF;">
                                        <strong>Linked Contract:</strong> ${inv.contract}
                                    </div>
                                ` : ''}

                                <div style="display:flex;justify-content:space-between;background:#F8FAFC;padding:10px 14px;border-radius:6px;border:1px solid var(--border-divider);font-size:12px;margin-bottom:18px;">
                                    <div><strong>Issue Date:</strong> ${inv.issueDate || inv.invoiceDate || '—'}</div>
                                    <div><strong>Due Date:</strong> ${inv.dueDate || '—'}</div>
                                    <div><strong>Terms:</strong> ${inv.paymentTerms || 'Net 30'}</div>
                                    <div><strong>Currency:</strong> ${invCurr}</div>
                                </div>

                                <table class="invoice-items-table" style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:12.5px;">
                                    <thead>
                                        <tr style="background:#F8FAFC;border-bottom:1px solid var(--border-divider);">
                                            <th style="padding:8px 10px;text-align:left;">ITEM &amp; DESCRIPTION</th>
                                            <th style="padding:8px;text-align:center;width:60px;">QTY</th>
                                            <th style="padding:8px;text-align:right;width:110px;">UNIT PRICE</th>
                                            <th style="padding:8px 10px;text-align:right;width:120px;">TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${(inv.items || []).map(item => `
                                            <tr style="border-bottom:1px solid var(--border-divider);">
                                                <td style="padding:10px;">
                                                    <div style="font-weight:600;color:var(--text-heading);">${item.title || item.name}</div>
                                                    <div style="font-size:11.5px;color:var(--text-secondary);">${item.desc || ''}</div>
                                                </td>
                                                <td style="padding:10px;text-align:center;">${item.qty}</td>
                                                <td style="padding:10px;text-align:right;">${formatMoney(item.price || item.rate, invCurr)}</td>
                                                <td style="padding:10px;text-align:right;font-weight:600;">${formatMoney(item.total || item.amount, invCurr)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>

                                <div style="display:flex;justify-content:flex-end;">
                                    <div style="width:280px;display:flex;flex-direction:column;gap:6px;font-size:13px;">
                                        <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
                                            <span>Subtotal:</span>
                                            <span>${formatMoney(inv.subtotal || tot, invCurr)}</span>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
                                            <span>Discount:</span>
                                            <span>-${formatMoney(inv.discountTotal || inv.discount || 0, invCurr)}</span>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
                                            <span>Tax${inv.taxRate ? ` (${inv.taxRate}%)` : (inv.tax_rate ? ` (${inv.tax_rate}%)` : '')}:</span>
                                            <span>+${formatMoney(inv.taxTotal || inv.tax || 0, invCurr)}</span>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;font-weight:700;color:var(--text-heading);border-top:1px solid var(--border-divider);padding-top:6px;font-size:14px;">
                                            <span>Grand Total:</span>
                                            <span>${formatMoney(tot, invCurr)}</span>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;color:#047857;font-weight:600;">
                                            <span>Amount Paid:</span>
                                            <span>${formatMoney(pd, invCurr)}</span>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;font-weight:700;color:${bal > 0 ? '#DC2626' : '#047857'};">
                                            <span>Balance Due:</span>
                                            <span>${formatMoney(bal, invCurr)}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment History -->
                                <div style="margin-top:24px;border-top:1px solid var(--border-divider);padding-top:16px;">
                                    <h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px 0;">Payment Transactions</h4>
                                    ${(inv.payments && inv.payments.length > 0) ? `
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            ${inv.payments.map(p => {
                                                const pMethod = escapeHtml(p.method || p.payment_method || 'Bank Transfer');
                                                const pDate = escapeHtml(p.date || p.payment_date || '');
                                                const transRef = (p.ref !== null && p.ref !== undefined && String(p.ref).trim() !== '')
                                                    ? String(p.ref).trim()
                                                    : ((p.transaction_reference !== null && p.transaction_reference !== undefined && String(p.transaction_reference).trim() !== '')
                                                        ? String(p.transaction_reference).trim()
                                                        : '');
                                                return `
                                                <div style="display:flex;justify-content:space-between;align-items:center;background:#F8FAFC;padding:10px 12px;border-radius:6px;border:1px solid var(--border-divider);font-size:12.5px;">
                                                    <div>
                                                        <strong style="color:var(--text-heading);">${pMethod}</strong> • ${pDate}
                                                        ${transRef ? `<div style="font-size:11px;color:var(--text-secondary);">Ref: ${escapeHtml(transRef)}</div>` : ''}
                                                    </div>
                                                    <div style="font-weight:700;color:#047857;">+${formatMoney(p.amount, invCurr)}</div>
                                                </div>
                                                `;
                                            }).join('')}
                                        </div>
                                    ` : `
                                        <div style="font-size:12px;color:var(--text-secondary);font-style:italic;">No payment transactions recorded for this invoice yet.</div>
                                    `}
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(err => {
                    console.error('Failed to load invoice details:', err);
                    if (body) body.innerHTML = '<div style="padding:32px;text-align:center;color:#DC2626;">Error retrieving invoice details.</div>';
                });
        },

        closeViewDrawer: function () {
            const drawer = document.getElementById('invoiceViewDrawer');
            if (drawer) {
                drawer.classList.remove('show');
                drawer.style.display = 'none';
                document.body.style.overflow = '';
            }
        },

        openCreateDrawer: function () {
            editingInvoiceId = null;
            formLineItems = [
                { title: '', desc: '', qty: 1, price: 0, total: 0 }
            ];

            const title = document.getElementById('formInvDrawerTitle');
            if (title) title.textContent = ' Create New Invoice';

            const formNum = document.getElementById('formInvNumber');
            const formCust = document.getElementById('formInvCustomer');
            const formCont = document.getElementById('formInvContact');
            const formDeal = document.getElementById('formInvDeal');
            const formIssue = document.getElementById('formInvIssueDate');
            const formDue = document.getElementById('formInvDueDate');
            const formCurr = document.getElementById('formInvCurrency');

            const today = new Date().toISOString().split('T')[0];
            const in30Days = new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0];

            if (formNum) formNum.value = referenceOptions.next_invoice_number || 'INV-001';
            if (formCust) formCust.value = '';
            if (formCont) formCont.value = '';
            if (formDeal) formDeal.value = '';
            if (formIssue) formIssue.value = today;
            if (formDue) formDue.value = in30Days;

            if (formCurr) {
                formCurr.innerHTML = `<option value="${orgCurrency}" selected>${orgCurrency}</option>`;
            }

            const elDiscount = document.getElementById('formDiscountInput');
            const elTax = document.getElementById('formTaxInput');
            if (elDiscount) elDiscount.value = '0';
            if (elTax) elTax.value = '0';

            const custNote = document.getElementById('formInvCustomerNote');
            const intNote = document.getElementById('formInvInternalNote');
            if (custNote) custNote.value = '';
            if (intNote) intNote.value = '';

            this.renderFormLineItems();

            const drawer = document.getElementById('invoiceFormDrawer');
            if (drawer) {
                drawer.style.display = 'block';
                drawer.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        },

        closeFormDrawer: function () {
            const drawer = document.getElementById('invoiceFormDrawer');
            if (drawer) {
                drawer.classList.remove('show');
                drawer.style.display = 'none';
                document.body.style.overflow = '';
            }
        },

        addFormLineItem: function () {
            formLineItems.push({ title: '', desc: '', qty: 1, price: 0, total: 0 });
            this.renderFormLineItems();
        },

        removeFormLineItem: function (idx) {
            if (formLineItems.length <= 1) {
                this.showToast('Invoice must have at least one line item.', 'warning');
                return;
            }
            formLineItems.splice(idx, 1);
            this.renderFormLineItems();
        },

        renderFormLineItems: function () {
            const tbody = document.getElementById('formItemsTbody');
            if (!tbody) return;

            tbody.innerHTML = formLineItems.map((item, idx) => {
                const q = item.qty !== undefined && item.qty !== null ? item.qty : 1;
                const p = item.price !== undefined && item.price !== null ? item.price : 0;
                const lineAmount = Math.round((Number(q || 0) * Number(p || 0)) * 100) / 100;
                return `
                <tr style="border-bottom:1px solid var(--border-divider);">
                    <td style="padding:6px 8px;">
                        <input type="text" class="input-control input-sm" value="${escapeHtml(item.title || '')}" oninput="window.invoicesApp.updateFormLineItem(${idx}, 'title', this.value)" placeholder="Item Description">
                    </td>
                    <td style="padding:6px 4px;text-align:center;">
                        <input type="number" step="any" min="0" class="input-control input-sm" style="text-align:center;" value="${q}" oninput="window.invoicesApp.updateFormLineItem(${idx}, 'qty', this.value)">
                    </td>
                    <td style="padding:6px 4px;text-align:right;">
                        <input type="number" step="any" min="0" class="input-control input-sm" style="text-align:right;" value="${p}" oninput="window.invoicesApp.updateFormLineItem(${idx}, 'price', this.value)">
                    </td>
                    <td style="padding:6px 8px;text-align:right;font-weight:600;" id="formItemAmount_${idx}">
                        ${formatMoney(lineAmount, orgCurrency)}
                    </td>
                    <td style="padding:6px;text-align:center;">
                        <button type="button" class="btn btn-ghost btn-xs" style="color:#DC2626;" onclick="window.invoicesApp.removeFormLineItem(${idx})">×</button>
                    </td>
                </tr>
            `;
            }).join('');

            this.recalculateFormTotals();
        },

        updateFormLineItem: function (idx, field, val) {
            if (formLineItems[idx]) {
                if (field === 'qty') {
                    formLineItems[idx].qty = val === '' ? 0 : (parseFloat(val) || 0);
                } else if (field === 'price') {
                    formLineItems[idx].price = val === '' ? 0 : (parseFloat(val) || 0);
                } else {
                    formLineItems[idx][field] = val;
                }

                const q = formLineItems[idx].qty || 0;
                const r = formLineItems[idx].price || 0;
                const lineAmount = Math.round((q * r) * 100) / 100;
                formLineItems[idx].total = lineAmount;

                const amountCell = document.getElementById(`formItemAmount_${idx}`);
                if (amountCell) {
                    amountCell.textContent = formatMoney(lineAmount, orgCurrency);
                }
            }
            this.recalculateFormTotals();
        },

        recalculateFormTotals: function () {
            const subtotal = formLineItems.reduce((sum, item) => {
                const q = item.qty || 0;
                const r = item.price || 0;
                return sum + (Math.round((q * r) * 100) / 100);
            }, 0);

            const discount = Math.max(0, parseFloat(document.getElementById('formDiscountInput')?.value) || 0);
            const taxRate = Math.max(0, parseFloat(document.getElementById('formTaxInput')?.value) || 0);

            const taxableAmount = Math.max(0, Math.round((subtotal - discount) * 100) / 100);
            const taxAmount = Math.round(taxableAmount * (taxRate / 100) * 100) / 100;
            const grandTotal = Math.max(0, Math.round((taxableAmount + taxAmount) * 100) / 100);

            const elSub = document.getElementById('formSubtotalVal');
            const elGrand = document.getElementById('formGrandTotalVal');
            const elTaxVal = document.getElementById('formTaxVal');

            if (elSub) elSub.textContent = formatMoney(subtotal, orgCurrency);
            if (elTaxVal) elTaxVal.textContent = (taxAmount > 0 ? '+' : '') + formatMoney(taxAmount, orgCurrency);
            if (elGrand) elGrand.textContent = formatMoney(grandTotal, orgCurrency);
        },

        saveInvoiceFromDrawer: function () {
            const num = document.getElementById('formInvNumber')?.value.trim();
            const cust = document.getElementById('formInvCustomer')?.value.trim();
            const contact = document.getElementById('formInvContact')?.value.trim();
            const deal = document.getElementById('formInvDeal')?.value.trim();
            const issue = document.getElementById('formInvIssueDate')?.value;
            const due = document.getElementById('formInvDueDate')?.value;
            const terms = document.getElementById('formInvTerms')?.value || 'Net 30';
            const curr = document.getElementById('formInvCurrency')?.value || orgCurrency;
            const discount = Math.max(0, parseFloat(document.getElementById('formDiscountInput')?.value) || 0);
            const taxRate = Math.max(0, parseFloat(document.getElementById('formTaxInput')?.value) || 0);
            const custNote = document.getElementById('formInvCustomerNote')?.value.trim();
            const intNote = document.getElementById('formInvInternalNote')?.value.trim();

            if (!num || !cust) {
                this.showToast('Please enter required invoice fields (Invoice Number and Customer).', 'warning');
                return;
            }

            const validItems = formLineItems.filter(i => (i.title && i.title.trim()) || (i.price && i.price > 0));
            if (validItems.length === 0) {
                this.showToast('Please add at least one line item with a description or rate.', 'warning');
                return;
            }

            // Match company from referenceOptions
            let matchedCompanyId = null;
            const foundComp = (referenceOptions.companies || []).find(c => 
                c.name.toLowerCase() === cust.toLowerCase() || String(c.id) === cust
            );
            if (foundComp) {
                matchedCompanyId = foundComp.id;
            }

            // Match contact
            let matchedContactId = null;
            if (contact) {
                const foundCont = (referenceOptions.contacts || []).find(ct => {
                    const ctName = `${ct.first_name || ''} ${ct.last_name || ''}`.trim().toLowerCase();
                    return ctName === contact.toLowerCase() || String(ct.id) === contact;
                });
                if (foundCont) matchedContactId = foundCont.id;
            }

            // Match project / deal
            let matchedProjectId = null;
            let matchedDealId = null;
            if (deal) {
                const foundP = (referenceOptions.projects || []).find(p => p.name.toLowerCase() === deal.toLowerCase());
                if (foundP) matchedProjectId = foundP.id;
                const foundD = (referenceOptions.deals || []).find(d => d.title.toLowerCase() === deal.toLowerCase());
                if (foundD) matchedDealId = foundD.id;
            }

            const payload = {
                action: 'create',
                invoice_number: num,
                company_id: matchedCompanyId,
                customer: cust,
                contact_id: matchedContactId,
                contact: contact,
                project_id: matchedProjectId,
                deal_id: matchedDealId,
                deal: deal,
                issue_date: issue,
                due_date: due,
                payment_terms: terms,
                currency: curr,
                discount: discount,
                tax: taxRate,
                tax_rate: taxRate,
                customer_notes: custNote,
                internal_notes: intNote,
                items: validItems.map(i => ({
                    title: i.title || 'Deliverable',
                    desc: i.desc || '',
                    qty: i.qty !== undefined && i.qty !== null ? Number(i.qty) : 0,
                    price: i.price !== undefined && i.price !== null ? Number(i.price) : 0
                }))
            };

            const btn = document.getElementById('btnSaveInvoice');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Saving...';
            }

            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Save Invoice';
                }
                if (res && res.success) {
                    this.closeFormDrawer();
                    this.updateKPIs();
                    this.renderTable();
                    this.loadReferenceOptions();
                    this.showToast(res.message || `Invoice ${num} created successfully.`, 'success');
                } else {
                    this.showToast(res ? res.message : 'Failed to create invoice.', 'warning');
                }
            })
            .catch(err => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Save Invoice';
                }
                console.error('Error creating invoice:', err);
                this.showToast('Network error while creating invoice.', 'warning');
            });
        },

        openRecordPaymentModal: function (invId) {
            const targetId = invId || activeInvoiceId;
            if (!targetId) return;

            fetch(`${API_URL}?action=get&id=${encodeURIComponent(targetId)}`)
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success || !res.data) {
                        this.showToast('Could not load invoice for payment.', 'warning');
                        return;
                    }
                    const inv = res.data;
                    const bal = inv.balanceDue !== undefined ? inv.balanceDue : 0;
                    const invCurr = inv.currency || orgCurrency;
                    const invNumber = inv.number || inv.id || 'INV';
                    const customerName = inv.customer || inv.billedTo || 'Unknown Client';

                    const modal = document.getElementById('invoicesActionModal');
                    const card = document.getElementById('invoicesActionModalCard');

                    if (card) {
                        card.innerHTML = `
                            <div class="invoices-modal-header">
                                <h3 class="invoices-modal-title">Record Payment: ${escapeHtml(invNumber)}</h3>
                                <button type="button" class="modal-close-btn" onclick="window.invoicesApp.closeModal()" aria-label="Close modal">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>

                            <form id="recordPaymentForm" onsubmit="event.preventDefault(); window.invoicesApp.submitRecordPayment('${escapeHtml(String(inv.id))}');" style="display:flex;flex-direction:column;flex:1 1 auto;overflow:hidden;margin:0;">
                                <div class="invoices-modal-body">
                                    <!-- Compact Invoice Summary Card -->
                                    <div class="invoices-payment-summary">
                                        <div class="invoices-payment-summary-grid">
                                            <div>
                                                <div class="invoices-payment-summary-label">Invoice</div>
                                                <div class="invoices-payment-summary-val" title="${escapeHtml(invNumber)}">${escapeHtml(invNumber)}</div>
                                            </div>
                                            <div>
                                                <div class="invoices-payment-summary-label">Customer</div>
                                                <div class="invoices-payment-summary-val" title="${escapeHtml(customerName)}">${escapeHtml(customerName)}</div>
                                            </div>
                                        </div>
                                        <div class="invoices-payment-summary-footer">
                                            <span class="invoices-payment-summary-label" style="margin-bottom:0;">Outstanding Balance</span>
                                            <span class="invoices-payment-balance-val">${formatMoney(bal, invCurr)}</span>
                                        </div>
                                    </div>

                                    <!-- Form Fields -->
                                    <div class="form-group">
                                        <label class="form-label" for="payAmountInput">Payment Amount *</label>
                                        <input type="number" step="any" min="0.01" max="${bal}" class="input-control" id="payAmountInput" value="${bal}" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="payMethodSelect">Payment Method</label>
                                        <select class="input-control" id="payMethodSelect">
                                            <option value="Bank Transfer" selected>Bank Transfer</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Wire Transfer">Wire Transfer</option>
                                            <option value="Cheque">Cheque</option>
                                        </select>
                                    </div>

                                    <div class="form-group" style="margin-bottom:4px;">
                                        <label class="form-label" for="payRefInput">Reference / Transaction ID</label>
                                        <input type="text" class="input-control" id="payRefInput" placeholder="e.g. TRF-994012" value="" autocomplete="off">
                                    </div>
                                </div>

                                <div class="invoices-modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.invoicesApp.closeModal()">Cancel</button>
                                    <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitPayment">Submit Payment</button>
                                </div>
                            </form>
                        `;
                    }

                    if (modal) {
                        modal.style.display = 'flex';
                        modal.classList.add('show');
                    }
                })
                .catch(err => {
                    console.error('Error opening payment modal:', err);
                    this.showToast('Failed to load invoice payment details.', 'warning');
                });
        },

        submitRecordPayment: function (invId) {
            const amt = parseFloat(document.getElementById('payAmountInput')?.value) || 0;
            const method = document.getElementById('payMethodSelect')?.value || 'Bank Transfer';
            const ref = document.getElementById('payRefInput')?.value.trim();

            if (amt <= 0) {
                this.showToast('Please enter a valid payment amount greater than zero.', 'warning');
                return;
            }

            const btn = document.getElementById('btnSubmitPayment');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Processing...';
            }

            const payload = {
                action: 'record_payment',
                invoice_id: invId,
                amount: amt,
                payment_method: method,
                transaction_reference: ref
            };

            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Submit Payment';
                }
                if (res && res.success) {
                    this.closeModal();
                    this.updateKPIs();
                    this.renderTable();
                    if (activeInvoiceId == invId) {
                        this.openViewDrawer(invId);
                    }
                    this.showToast(res.message || `Payment of ${amt} recorded successfully.`, 'success');
                } else {
                    this.showToast(res ? res.message : 'Failed to record payment.', 'warning');
                }
            })
            .catch(err => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Submit Payment';
                }
                console.error('Error submitting payment:', err);
                this.showToast('Network error while recording payment.', 'warning');
            });
        },

        closeModal: function () {
            const modal = document.getElementById('invoicesActionModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }
        },

        downloadPDF: function (invId) {
            const targetId = invId || activeInvoiceId;
            if (!targetId) {
                this.showToast('No active invoice selected.', 'warning');
                return;
            }
            // Trigger clean browser print preview of the invoice
            window.print();
        },

        exportCSV: function () {
            const params = new URLSearchParams({
                action: 'export_csv',
                search: currentSearch,
                status: currentStatusFilter,
                customer: currentCustomerFilter,
                owner: currentOwnerFilter,
                sort: currentSort
            });
            window.location.href = `${API_URL}?${params.toString()}`;
            this.showToast('Exporting invoices to CSV...', 'info');
        },

        showToast: function (msg, type = 'info') {
            const container = document.getElementById('invoicesToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `invoices-toast ${type}`;
            toast.style.cssText = 'background:#0F172A; color:#FFF; padding:10px 16px; border-radius:6px; font-size:13px; box-shadow:0 4px 6px rgba(0,0,0,0.1);';
            toast.innerHTML = `<span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.25s ease';
                setTimeout(() => toast.remove(), 250);
            }, 3000);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.invoicesApp.init());
    } else {
        window.invoicesApp.init();
    }
})();
