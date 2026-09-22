/**
 * NexFlow CRM — Client Portal Invoices Controller
 * Live database integration for client-invoices.php.
 * Manages live invoice retrieval, search, status/payment filtering, sorting,
 * summary KPIs, slide-out drawer, line items, payments history, and 'mark_viewed'.
 */

(function () {
    'use strict';

    let currentInvoices = Array.isArray(window.INITIAL_INVOICES) ? [...window.INITIAL_INVOICES] : [];
    let isFetching = false;
    let searchDebounceTimer = null;

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getStatusBadgeClass(status) {
        switch (status) {
            case "Paid": return "green";
            case "Partially Paid":
            case "Pending":
            case "Viewed":
            case "Sent": return "blue";
            case "Draft": return "gray";
            case "Overdue":
            case "Cancelled": return "red";
            default: return "blue";
        }
    }

    function getPaymentBadgeClass(pStatus) {
        switch (pStatus) {
            case "Paid": return "green";
            case "Partially Paid": return "amber";
            case "Unpaid": return "blue";
            case "Overdue": return "red";
            default: return "gray";
        }
    }

    function formatMoney(amount, symbol) {
        const sym = symbol || window.CURRENCY_SYMBOL || '$';
        const val = Number(amount || 0);
        return sym + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateKpiCounters(kpis) {
        if (!kpis) return;
        const sym = window.CURRENCY_SYMBOL || '$';
        const totalEl = document.getElementById("kpiTotalInvoices");
        const outstandingEl = document.getElementById("kpiOutstandingInvoices");
        const paidEl = document.getElementById("kpiPaidInvoices");
        const overdueEl = document.getElementById("kpiOverdueInvoices");

        if (totalEl && kpis.total !== undefined) totalEl.textContent = kpis.total;
        if (outstandingEl && kpis.outstanding !== undefined) outstandingEl.textContent = formatMoney(kpis.outstanding, sym);
        if (paidEl && kpis.paid !== undefined) paidEl.textContent = formatMoney(kpis.paid, sym);
        if (overdueEl && kpis.overdue !== undefined) overdueEl.textContent = formatMoney(kpis.overdue, sym);
    }

    function refreshKPIs() {
        fetch('api/client-invoices.php?action=summary', {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data && res.data.kpis) {
                if (res.data.currencySymbol) {
                    window.CURRENCY_SYMBOL = res.data.currencySymbol;
                }
                updateKpiCounters(res.data.kpis);
            }
        })
        .catch(err => console.warn("Failed to refresh invoice KPIs:", err));
    }

    function fetchInvoices() {
        if (isFetching) return;
        isFetching = true;

        const query = (document.getElementById("invoicesSearchInput")?.value || "").trim();
        const statusFilter = document.getElementById("invoicesStatusFilter")?.value || "All";
        const paymentFilter = document.getElementById("invoicesPaymentFilter")?.value || "All";
        const sortBy = document.getElementById("invoicesSortSelect")?.value || "newest";

        const params = new URLSearchParams({
            action: 'list',
            q: query,
            status: statusFilter,
            payment: paymentFilter,
            sort: sortBy
        });

        fetch(`api/client-invoices.php?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            isFetching = false;
            if (res.success && res.data && Array.isArray(res.data.items)) {
                currentInvoices = res.data.items;
                if (res.data.currencySymbol) {
                    window.CURRENCY_SYMBOL = res.data.currencySymbol;
                }
                renderInvoicesTable();
            } else {
                renderInvoicesTable();
            }
        })
        .catch(err => {
            isFetching = false;
            console.error("Failed to fetch invoices:", err);
            renderInvoicesTable();
        });
    }

    function renderInvoicesTable() {
        const tbody = document.getElementById("clientInvoicesTbody");
        const emptyState = document.getElementById("clientInvoicesEmptyState");
        const tableCard = document.getElementById("clientInvoicesTableCard");
        if (!tbody) return;

        if (!currentInvoices || currentInvoices.length === 0) {
            if (tableCard) tableCard.style.display = "none";
            if (emptyState) emptyState.style.display = "block";
            tbody.innerHTML = '';
            return;
        }

        if (emptyState) emptyState.style.display = "none";
        if (tableCard) tableCard.style.display = "block";

        tbody.innerHTML = currentInvoices.map(inv => {
            const statusClass = getStatusBadgeClass(inv.status);
            const paymentClass = getPaymentBadgeClass(inv.paymentStatus || inv.paymentState);
            const sym = inv.currencySymbol || window.CURRENCY_SYMBOL || '$';
            const tot = Number(inv.total || inv.amount || 0);
            const pd  = Number(inv.paid !== undefined ? inv.paid : (inv.amountPaid || 0));
            const bal = Number(inv.balance !== undefined ? inv.balance : (inv.balanceDue || (tot - pd)));
            const daysOverdue = Number(inv.daysOverdue || 0);

            let actionButtons = `
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientInvoices.openInvoiceDrawer('${escapeHtml(inv.id)}', this)">
                    View Invoice
                </button>
            `;

            if (bal > 0) {
                if (inv.status === 'Overdue' || inv.paymentStatus === 'Overdue') {
                    actionButtons += `
                        <button type="button" class="btn btn-primary btn-xs" style="background-color:#DC2626;border-color:#DC2626;" onclick="window.clientInvoices.openPayModal('${escapeHtml(inv.id)}', 'Pay Now')">
                            Pay Now
                        </button>
                    `;
                } else if (pd > 0) {
                    actionButtons += `
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.clientInvoices.openPayModal('${escapeHtml(inv.id)}', 'Pay Balance')">
                            Pay Balance
                        </button>
                    `;
                } else {
                    actionButtons += `
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.clientInvoices.openPayModal('${escapeHtml(inv.id)}', 'Pay Invoice')">
                            Pay Invoice
                        </button>
                    `;
                }
            }

            return `
                <tr>
                    <td>
                        <span class="client-invoice-number">${escapeHtml(inv.number || inv.id)}</span>
                        <div style="font-weight:600;color:#0F172A;font-size:13.5px;margin-top:2px;">${escapeHtml(inv.title)}</div>
                        <div style="font-size:11.5px;color:#64748B;">
                            ${inv.project ? `Project: ${escapeHtml(inv.project)}` : ''}
                            ${inv.project && inv.deal ? ' • ' : ''}
                            ${inv.deal ? `Deal: ${escapeHtml(inv.deal)}` : ''}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:#0F172A;font-size:14px;">${formatMoney(tot, sym)}</div>
                        <div style="font-size:11.5px;color:#64748B;">Paid: ${formatMoney(pd, sym)} • Bal: ${formatMoney(bal, sym)}</div>
                    </td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:3px;align-items:flex-start;">
                            <span class="client-portal-badge ${statusClass}">${escapeHtml(inv.status)}</span>
                            <span class="client-portal-badge ${paymentClass}" style="font-size:10px;padding:1px 6px;">${escapeHtml(inv.paymentStatus || inv.paymentState || 'Unpaid')}</span>
                        </div>
                    </td>
                    <td style="font-size:12.5px;color:#334155;">${escapeHtml(inv.invoiceDate || inv.issueDate || '—')}</td>
                    <td style="font-size:12.5px;color:#334155;">
                        ${escapeHtml(inv.dueDate || '—')}
                        ${daysOverdue > 0 ? `
                            <div style="margin-top:2px;font-size:10.5px;font-weight:700;color:#B91C1C;">${daysOverdue} days overdue</div>
                        ` : ''}
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                            ${actionButtons}
                        </div>
                    </td>
                </tr>
            `;
        }).join("");
    }

    window.renderClientInvoices = function () {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(fetchInvoices, 200);
    };

    function showClientToast(msg, type = "info") {
        if (window.clientPortal && typeof window.clientPortal.showToast === "function") {
            window.clientPortal.showToast(msg, type);
            return;
        }
        alert(msg);
    }

    function restorePageInteraction() {
        document.body.style.overflow = "";
        document.body.style.pointerEvents = "";
    }

    // Public Invoices Controller
    window.clientInvoices = {
        activeInvoiceId: null,
        activeInvoice: null,
        activeTab: "overview",
        lastFocusedBtn: null,

        openInvoiceDrawer: function (invoiceId, triggerBtn) {
            this.lastFocusedBtn = triggerBtn || document.activeElement;
            this.activeInvoiceId = invoiceId;
            this.activeTab = "overview";

            const drawer = document.getElementById("clientInvoiceDrawer");
            const title = document.getElementById("cidInvoiceTitle");
            const badge = document.getElementById("cidInvoiceStatusBadge");
            const body = document.getElementById("clientInvoiceDrawerBody");

            // Look up cached basic item
            const found = currentInvoices.find(i => String(i.id) === String(invoiceId) || String(i.number) === String(invoiceId));
            if (title) title.textContent = found ? `${found.title} (${found.number})` : `Invoice Details`;
            if (badge && found) {
                badge.textContent = found.status;
                badge.className = "client-portal-badge " + getStatusBadgeClass(found.status);
            }

            if (body) {
                body.innerHTML = `<div style="text-align:center;padding:40px;color:#64748B;">Loading invoice details...</div>`;
            }

            if (drawer) {
                drawer.style.display = "block";
                drawer.classList.add("show");
                document.body.style.overflow = "hidden";
            }

            // Fetch live deep details from API
            fetch(`api/client-invoices.php?action=get&id=${encodeURIComponent(invoiceId)}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    this.activeInvoice = res.data;
                    if (title) title.textContent = `${res.data.title} (${res.data.number})`;
                    if (badge) {
                        badge.textContent = res.data.status;
                        badge.className = "client-portal-badge " + getStatusBadgeClass(res.data.status);
                    }

                    // CORRECTION 1: Auto-transition 'Sent' -> 'Viewed'
                    if (res.data.db_status === 'Sent' || res.data.status === 'Sent') {
                        const csrfToken = window.clientPortalCsrf || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const fd = new FormData();
                        fd.append('action', 'mark_viewed');
                        fd.append('id', res.data.id);
                        fd.append('csrf_token', csrfToken);

                        fetch('api/client-invoices.php', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-Token': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: fd
                        })
                        .then(r => r.json())
                        .then(r => {
                            if (r.success) {
                                this.activeInvoice.status = 'Viewed';
                                this.activeInvoice.db_status = 'Viewed';
                                if (badge) {
                                    badge.textContent = 'Viewed';
                                    badge.className = 'client-portal-badge ' + getStatusBadgeClass('Viewed');
                                }
                                // Update local cache
                                if (found) found.status = 'Viewed';
                                renderInvoicesTable();
                                refreshKPIs();
                            }
                        })
                        .catch(err => console.warn("Could not mark invoice viewed:", err));
                    }

                    this.renderDrawerTab(this.activeTab);
                } else {
                    if (body) body.innerHTML = `<div style="text-align:center;padding:40px;color:#DC2626;">${escapeHtml(res.message || 'Invoice details not available.')}</div>`;
                }
            })
            .catch(err => {
                console.error("Drawer load error:", err);
                if (body) body.innerHTML = `<div style="text-align:center;padding:40px;color:#DC2626;">Failed to load invoice details. Please refresh and try again.</div>`;
            });
        },

        closeInvoiceDrawer: function () {
            const drawer = document.getElementById("clientInvoiceDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.style.display = "none";
                document.body.style.overflow = "";
            }
            if (this.lastFocusedBtn && typeof this.lastFocusedBtn.focus === "function") {
                this.lastFocusedBtn.focus();
            }
            restorePageInteraction();
        },

        switchInvoiceDrawerTab: function (tabName) {
            this.activeTab = tabName;
            document.querySelectorAll("#clientInvoiceDrawer .client-portal-drawer-tab").forEach(tab => {
                const isActive = tab.getAttribute("data-tab") === tabName;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });
            this.renderDrawerTab(tabName);
        },

        renderDrawerTab: function (tabName) {
            const invoice = this.activeInvoice;
            const body = document.getElementById("clientInvoiceDrawerBody");
            if (!body || !invoice) return;

            const sym = invoice.currencySymbol || window.CURRENCY_SYMBOL || '$';
            const tot = Number(invoice.total || invoice.amount || 0);
            const pd  = Number(invoice.paid !== undefined ? invoice.paid : (invoice.amountPaid || 0));
            const bal = Number(invoice.balance !== undefined ? invoice.balance : (invoice.balanceDue || (tot - pd)));

            if (tabName === "overview") {
                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">INVOICE TOTAL</div>
                            <div style="font-size:16px;font-weight:700;color:#0F172A;margin-top:2px;">${formatMoney(tot, sym)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">PAID AMOUNT</div>
                            <div style="font-size:15px;font-weight:700;color:#059669;margin-top:4px;">${formatMoney(pd, sym)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">BALANCE DUE</div>
                            <div style="font-size:15px;font-weight:700;color:${bal > 0 ? '#DC2626' : '#0F172A'};margin-top:4px;">${formatMoney(bal, sym)}</div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;background:#F8FAFC;padding:14px;border-radius:8px;border:1px solid #E2E8F0;display:flex;flex-direction:column;gap:8px;font-size:12.5px;">
                        ${invoice.project ? `
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:#64748B;">Project:</span>
                                <strong style="color:#0F172A;">${escapeHtml(invoice.project)}</strong>
                            </div>
                        ` : ''}
                        ${invoice.deal ? `
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:#64748B;">Deal:</span>
                                <strong style="color:#0F172A;">${escapeHtml(invoice.deal)}</strong>
                            </div>
                        ` : ''}
                        ${invoice.contract ? `
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:#64748B;">Contract:</span>
                                <strong style="color:#2563EB;">${escapeHtml(invoice.contract)}</strong>
                            </div>
                        ` : ''}
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:#64748B;">Payment Terms:</span>
                            <strong style="color:#0F172A;">${escapeHtml(invoice.paymentTerms || 'Net 30')}</strong>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div>
                            <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:2px;">INVOICE DATE</div>
                            <div style="font-size:13px;font-weight:600;color:#0F172A;">${escapeHtml(invoice.invoiceDate || invoice.issueDate || '—')}</div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:2px;">DUE DATE</div>
                            <div style="font-size:13px;font-weight:600;color:#0F172A;">${escapeHtml(invoice.dueDate || '—')}</div>
                        </div>
                    </div>

                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:20px;">
                        <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:4px;">BILLED TO</div>
                        <div style="font-size:13.5px;font-weight:700;color:#0F172A;">${escapeHtml(invoice.billedTo || invoice.customer)}</div>
                        ${invoice.contactName ? `<div style="font-size:12px;color:#475569;margin-top:2px;">Attention: ${escapeHtml(invoice.contactName)}</div>` : ''}
                    </div>

                    ${invoice.customer_notes ? `
                        <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:20px;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:4px;">CUSTOMER NOTES</div>
                            <div style="font-size:12.5px;color:#334155;line-height:1.5;">${escapeHtml(invoice.customer_notes)}</div>
                        </div>
                    ` : ''}
                `;
            } else if (tabName === "items") {
                const items = invoice.items || [];
                body.innerHTML = `
                    <div style="border:1px solid #E2E8F0;border-radius:8px;overflow:hidden;margin-bottom:16px;">
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                            <thead style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                                <tr>
                                    <th style="padding:10px;text-align:left;">Item</th>
                                    <th style="padding:10px;text-align:center;">Qty</th>
                                    <th style="padding:10px;text-align:right;">Rate</th>
                                    <th style="padding:10px;text-align:right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.length > 0 ? items.map(it => `
                                    <tr style="border-bottom:1px solid #E2E8F0;">
                                        <td style="padding:10px;">
                                            <div style="font-weight:600;color:#0F172A;">${escapeHtml(it.name || it.title)}</div>
                                            ${it.desc ? `<div style="font-size:11.5px;color:#64748B;">${escapeHtml(it.desc)}</div>` : ''}
                                        </td>
                                        <td style="padding:10px;text-align:center;">${Number(it.qty || 1)}</td>
                                        <td style="padding:10px;text-align:right;">${formatMoney(it.rate || it.price, sym)}</td>
                                        <td style="padding:10px;text-align:right;font-weight:600;">${formatMoney(it.amount || it.total, sym)}</td>
                                    </tr>
                                `).join("") : `
                                    <tr>
                                        <td colspan="4" style="padding:16px;text-align:center;color:#64748B;">No itemized deliverables listed.</td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;justify-content:flex-end;">
                        <div style="width:240px;display:flex;flex-direction:column;gap:6px;font-size:12.5px;">
                            <div style="display:flex;justify-content:space-between;color:#64748B;">
                                <span>Subtotal:</span>
                                <span>${formatMoney(invoice.subtotal || tot, sym)}</span>
                            </div>
                            ${Number(invoice.discount || 0) > 0 ? `
                                <div style="display:flex;justify-content:space-between;color:#64748B;">
                                    <span>Discount:</span>
                                    <span>-${formatMoney(invoice.discount, sym)}</span>
                                </div>
                            ` : ''}
                            ${Number(invoice.tax || 0) > 0 ? `
                                <div style="display:flex;justify-content:space-between;color:#64748B;">
                                    <span>Tax:</span>
                                    <span>+${formatMoney(invoice.tax, sym)}</span>
                                </div>
                            ` : ''}
                            <div style="display:flex;justify-content:space-between;font-weight:700;color:#0F172A;border-top:1px solid #E2E8F0;padding-top:6px;">
                                <span>Total:</span>
                                <span>${formatMoney(tot, sym)}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else if (tabName === "payments") {
                const payments = invoice.payments || [];
                body.innerHTML = `
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;color:#0F172A;text-transform:uppercase;margin-bottom:10px;">Payment Summary</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12.5px;">
                            <div>
                                <span style="color:#64748B;">Invoice Total:</span>
                                <div style="font-weight:700;color:#0F172A;font-size:14px;margin-top:2px;">${formatMoney(tot, sym)}</div>
                            </div>
                            <div>
                                <span style="color:#64748B;">Paid Amount:</span>
                                <div style="font-weight:700;color:#059669;font-size:14px;margin-top:2px;">${formatMoney(pd, sym)}</div>
                            </div>
                            <div>
                                <span style="color:#64748B;">Balance Due:</span>
                                <div style="font-weight:700;color:${bal > 0 ? '#DC2626' : '#0F172A'};font-size:14px;margin-top:2px;">${formatMoney(bal, sym)}</div>
                            </div>
                            <div>
                                <span style="color:#64748B;">Payment Status:</span>
                                <div style="margin-top:2px;"><span class="client-portal-badge ${getPaymentBadgeClass(invoice.paymentStatus || invoice.status)}">${escapeHtml(invoice.paymentStatus || invoice.status)}</span></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12px;font-weight:700;color:#0F172A;text-transform:uppercase;margin-bottom:10px;">Payment History</div>
                        ${payments.length > 0 ? `
                            <div style="display:flex;flex-direction:column;gap:10px;">
                                ${payments.map(p => `
                                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;font-size:12.5px;">
                                        <div>
                                            <div style="font-weight:700;color:#0F172A;">${escapeHtml(p.payment_number || p.number || 'Payment Receipt')}</div>
                                            <div style="font-size:11px;color:#64748B;margin-top:2px;">
                                                Received on ${escapeHtml(p.date || p.payment_date)} • ${escapeHtml(p.method || p.payment_method || 'Bank Transfer')}
                                                ${p.transaction_reference ? ` • Ref: ${escapeHtml(p.transaction_reference)}` : ''}
                                            </div>
                                        </div>
                                        <div style="font-weight:700;color:#059669;font-size:14px;">${formatMoney(p.amount, sym)}</div>
                                    </div>
                                `).join("")}
                            </div>
                        ` : `
                            <div style="font-size:12.5px;color:#64748B;font-style:italic;padding:12px;background:#F8FAFC;border-radius:8px;border:1px solid #E2E8F0;">
                                No payments have been recorded for this invoice yet.
                            </div>
                        `}
                    </div>
                `;
            } else if (tabName === "document") {
                const timeline = invoice.timeline || [
                    { date: invoice.invoiceDate || invoice.issueDate || '—', text: 'Invoice created' }
                ];

                body.innerHTML = `
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:36px;height:36px;border-radius:8px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <div>
                                <div style="font-weight:700;color:#0F172A;font-size:13.5px;">${escapeHtml(invoice.number || invoice.id)}.pdf</div>
                                <div style="font-size:11.5px;color:#64748B;">Official Invoice PDF Record</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.print()">
                                Print
                            </button>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12px;font-weight:700;color:#0F172A;text-transform:uppercase;margin-bottom:12px;">Document History &amp; Activity</div>
                        <div style="display:flex;flex-direction:column;gap:14px;position:relative;padding-left:18px;">
                            ${timeline.map(t => `
                                <div style="position:relative;">
                                    <div style="position:absolute;left:-18px;top:4px;width:8px;height:8px;border-radius:50%;background:#2563EB;"></div>
                                    <div style="font-weight:600;color:#0F172A;font-size:12.5px;">${escapeHtml(t.text)}</div>
                                    <div style="font-size:11px;color:#64748B;margin-top:2px;">Date: ${escapeHtml(t.date)}</div>
                                </div>
                            `).join("")}
                        </div>
                    </div>
                `;
            }
        },

        openPayModal: function (invoiceId, btnLabel) {
            if (window.clientPortal && typeof window.clientPortal.openQuickMessageModal === 'function') {
                window.clientPortal.openQuickMessageModal(`Billing Inquiry: Invoice ${invoiceId}`);
            } else {
                showClientToast(`Please contact your account manager regarding payment for invoice ${invoiceId}.`, 'info');
            }
        }
    };

    // Keyboard Escape handling
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            window.clientInvoices.closeInvoiceDrawer();
            restorePageInteraction();
        }
    });

    // Auto-init on page load
    document.addEventListener("DOMContentLoaded", function () {
        if (window.INITIAL_INVOICE_KPIS) {
            updateKpiCounters(window.INITIAL_INVOICE_KPIS);
        }
        renderInvoicesTable();
    });
})();