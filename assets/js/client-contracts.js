/**
 * NexFlow CRM — Client Portal Contracts Controller
 * Live database integration for client-contracts.php.
 * Manages live contracts retrieval, search, status/type filtering, sorting,
 * summary KPIs, drawers, and modal interactions with live database records.
 */

(function () {
    'use strict';

    let currentContracts = Array.isArray(window.INITIAL_CONTRACTS) ? window.INITIAL_CONTRACTS : [];
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
            case "Active":
            case "Signed": return "green";
            case "Pending Signature":
            case "Changes Requested":
            case "Expiring Soon": return "amber";
            case "Sent":
            case "Viewed":
            case "Revised": return "blue";
            case "Draft": return "gray";
            case "Expired":
            case "Declined":
            case "Terminated": return "red";
            default: return "blue";
        }
    }

    function updateKpiCounters(kpis) {
        if (!kpis) return;
        const totalEl = document.getElementById("kpiTotalContracts");
        const activeEl = document.getElementById("kpiActiveContracts");
        const pendingEl = document.getElementById("kpiPendingContracts");
        const expiringEl = document.getElementById("kpiExpiringContracts");

        if (totalEl && kpis.total !== undefined) totalEl.textContent = kpis.total;
        if (activeEl && kpis.active !== undefined) activeEl.textContent = kpis.active;
        if (pendingEl && kpis.pending !== undefined) pendingEl.textContent = kpis.pending;
        if (expiringEl && kpis.expiring !== undefined) expiringEl.textContent = kpis.expiring;
    }

    function refreshKPIs() {
        fetch('api/client-contracts.php?action=summary', {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data && res.data.kpi) {
                updateKpiCounters(res.data.kpi);
            }
        })
        .catch(err => {
            console.error('Failed to refresh contract KPIs:', err);
        });
    }

    function renderContractsTable(contracts) {
        const tbody = document.getElementById("clientContractsTbody");
        const emptyState = document.getElementById("clientContractsEmptyState");
        const tableCard = document.getElementById("clientContractsTableCard");
        if (!tbody) return;

        if (!contracts || contracts.length === 0) {
            if (tableCard) tableCard.style.display = "none";
            if (emptyState) emptyState.style.display = "block";
            tbody.innerHTML = "";
            return;
        }

        if (emptyState) emptyState.style.display = "none";
        if (tableCard) tableCard.style.display = "block";

        tbody.innerHTML = contracts.map(c => {
            const displayStatus = c.display_status || c.status || 'Active';
            const statusClass = getStatusBadgeClass(displayStatus);
            const isPendingSig = c.is_actionable || (displayStatus === "Pending Signature" || displayStatus === "Sent" || displayStatus === "Viewed" || displayStatus === "Changes Requested");
            const daysRemaining = (c.days_remaining !== null && c.days_remaining !== undefined) ? parseInt(c.days_remaining, 10) : null;
            const formattedVal = c.formatted_value || ('$' + Number(c.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            const contractNumber = escapeHtml(c.contract_number || ('CON-' + c.id));
            const title = escapeHtml(c.title || 'Contract Agreement');
            const type = escapeHtml(c.type || 'Standard Agreement');
            const refNumber = c.reference_number ? ' • Ref: ' + escapeHtml(c.reference_number) : '';
            const startDate = escapeHtml(c.start_date_formatted || c.start_date || '—');
            const endDate = escapeHtml(c.end_date_formatted || c.end_date || '—');

            let daysBadge = '';
            if ((displayStatus === "Active" || displayStatus === "Signed" || displayStatus === "Expiring Soon") && daysRemaining !== null) {
                const badgeClass = daysRemaining <= 30 ? 'warning' : '';
                const badgeText = daysRemaining <= 0 ? 'Expires today' : `${daysRemaining} days remaining`;
                daysBadge = `
                    <div style="margin-top:2px;">
                        <span class="client-contract-days-badge ${badgeClass}">${badgeText}</span>
                    </div>
                `;
            }

            return `
                <tr>
                    <td>
                        <span class="client-contract-id" style="font-size:11.5px; font-weight:700; color:var(--primary, #2563EB);">${contractNumber}</span>
                        <div style="font-weight:600;color:#0F172A;font-size:13.5px;margin-top:2px;">${title}</div>
                        <div style="font-size:11.5px;color:#64748B;">${type}${refNumber}</div>
                    </td>
                    <td style="font-weight:700;color:#0F172A;font-size:14px;">${formattedVal}</td>
                    <td>
                        <span class="client-portal-badge ${statusClass}">${escapeHtml(displayStatus)}</span>
                    </td>
                    <td style="font-size:12.5px;color:#334155;">${startDate}</td>
                    <td style="font-size:12.5px;color:#334155;">
                        ${endDate}
                        ${daysBadge}
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:nowrap;">
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientContracts.openContractDrawer('${c.id}', this)">
                                View Contract
                            </button>
                            ${isPendingSig ? `
                                <button type="button" class="btn btn-secondary btn-xs" style="color:#B45309; border-color:#FDE68A; background:#FFFBEB;" onclick="window.clientContracts.openRequestChangesModal('${c.id}')">
                                    Request Changes
                                </button>
                                <button type="button" class="btn btn-primary btn-xs" onclick="window.clientContracts.openSignContractModal('${c.id}')">
                                    Sign Contract
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join("");
    }

    function fetchContracts() {
        const searchInput = document.getElementById("contractsSearchInput");
        const statusSelect = document.getElementById("contractsStatusFilter");
        const typeSelect = document.getElementById("contractsTypeFilter");
        const sortSelect = document.getElementById("contractsSortSelect");

        const q = searchInput ? searchInput.value.trim() : "";
        const status = statusSelect ? statusSelect.value : "All";
        const type = typeSelect ? typeSelect.value : "All";
        const sort = sortSelect ? sortSelect.value : "newest";

        const params = new URLSearchParams({
            action: 'list',
            q: q,
            status: status,
            type: type,
            sort: sort
        });

        isFetching = true;
        fetch('api/client-contracts.php?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            isFetching = false;
            if (res.success && res.data) {
                currentContracts = res.data.contracts || [];
                renderContractsTable(currentContracts);
            } else {
                console.warn('Could not load contracts:', res.message);
                renderContractsTable([]);
            }
        })
        .catch(err => {
            isFetching = false;
            console.error('Error fetching contracts:', err);
        });
    }

    window.renderClientContracts = function () {
        if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
        }
        searchDebounceTimer = setTimeout(() => {
            fetchContracts();
        }, 150);
    };

    function showClientToast(msg, type = "success") {
        if (window.clientPortal && typeof window.clientPortal.showToast === "function") {
            window.clientPortal.showToast(msg, type);
            return;
        }
        if (typeof showAlertModal === "function") {
            showAlertModal({ title: "Notification", message: msg, type: "info" });
            return;
        }
        alert(msg);
    }

    function restorePageInteraction() {
        document.body.style.overflow = "";
        document.body.style.pointerEvents = "";
    }

    // Public Controller Object
    window.clientContracts = {
        activeContractId: null,
        activeTab: "overview",
        pendingModalContractId: null,
        activeContractData: null,

        openContractDrawer: function (contractId, triggerBtn) {
            const found = currentContracts.find(c => String(c.id) === String(contractId) || c.contract_number === contractId);
            this.activeContractId = contractId;
            this.activeTab = "overview";
            this.activeContractData = found || null;

            const drawer = document.getElementById("clientContractDrawer");
            const title = document.getElementById("ccdContractTitle");
            const badge = document.getElementById("ccdContractStatusBadge");

            if (found) {
                const displayStatus = found.display_status || found.status;
                if (title) title.textContent = `${found.title} (${found.contract_number || found.id})`;
                if (badge) {
                    badge.textContent = displayStatus;
                    badge.className = "client-portal-badge " + getStatusBadgeClass(displayStatus);
                }
                this.switchContractDrawerTab("overview");
            }

            // Fetch detailed record from server
            fetch(`api/client-contracts.php?action=get&id=${encodeURIComponent(contractId)}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.contract) {
                    this.activeContractData = res.data.contract;
                    const contract = this.activeContractData;
                    const displayStatus = contract.display_status || contract.status;

                    if (title) title.textContent = `${contract.title} (${contract.contract_number || contract.id})`;
                    if (badge) {
                        badge.textContent = displayStatus;
                        badge.className = "client-portal-badge " + getStatusBadgeClass(displayStatus);
                    }
                    this.switchContractDrawerTab(this.activeTab);
                }
            })
            .catch(err => {
                console.error('Error fetching contract details:', err);
            });

            if (drawer) {
                drawer.style.display = "block";
                drawer.classList.add("show");
                document.body.style.overflow = "hidden";
            }
        },

        closeContractDrawer: function () {
            const drawer = document.getElementById("clientContractDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.style.display = "none";
            }
            restorePageInteraction();
        },

        switchContractDrawerTab: function (tabName) {
            this.activeTab = tabName;
            document.querySelectorAll("#clientContractDrawer .client-portal-drawer-tab").forEach(tab => {
                const isActive = tab.getAttribute("data-tab") === tabName;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            const contract = this.activeContractData || currentContracts.find(c => String(c.id) === String(this.activeContractId));
            const body = document.getElementById("clientContractDrawerBody");
            if (!body || !contract) return;

            const displayStatus = contract.display_status || contract.status;
            const isPendingSig = contract.is_actionable || (displayStatus === "Pending Signature" || displayStatus === "Sent" || displayStatus === "Viewed" || displayStatus === "Changes Requested");
            const daysRemaining = (contract.days_remaining !== null && contract.days_remaining !== undefined) ? parseInt(contract.days_remaining, 10) : null;
            const formattedVal = contract.formatted_value || ('$' + Number(contract.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));

            if (tabName === "overview") {
                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">CONTRACT VALUE</div>
                            <div style="font-size:16px;font-weight:700;color:#0F172A;margin-top:2px;">${escapeHtml(formattedVal)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">START DATE</div>
                            <div style="font-size:13px;font-weight:700;color:#0F172A;margin-top:4px;">${escapeHtml(contract.start_date_formatted || contract.start_date || '—')}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">END DATE</div>
                            <div style="font-size:13px;font-weight:700;color:#0F172A;margin-top:4px;">${escapeHtml(contract.end_date_formatted || contract.end_date || '—')}</div>
                        </div>
                    </div>

                    ${contract.change_request_text ? `
                        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:14px 16px;margin-bottom:20px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <div style="font-size:13px;font-weight:700;color:#B45309;">YOUR CHANGE REQUEST</div>
                                <span class="client-portal-badge amber" style="font-size:10px;">Submitted</span>
                            </div>
                            <p style="font-size:12.5px;color:#92400E;margin:0 0 8px 0;line-height:1.5;">"${escapeHtml(contract.change_request_text)}"</p>
                            <div style="font-size:11px;color:#B45309;">
                                ${contract.change_request_date ? 'Submitted on: ' + escapeHtml(contract.change_request_date) : ''}
                                ${contract.change_request_contact ? ' • By: ' + escapeHtml(contract.change_request_contact) : ''}
                            </div>
                        </div>
                    ` : ''}

                    ${(displayStatus === "Active" || displayStatus === "Signed" || displayStatus === "Expiring Soon") && daysRemaining !== null ? `
                        <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:12.5px;color:#1D4ED8;display:flex;align-items:center;justify-content:space-between;">
                            <span>Active Contract • Term ends on ${escapeHtml(contract.end_date_formatted || contract.end_date)}</span>
                            <span style="font-weight:700;">${daysRemaining <= 0 ? 'Expires today' : daysRemaining + ' Days Remaining'}</span>
                        </div>
                    ` : ''}

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Agreement Overview</h4>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;">${escapeHtml(contract.overview || 'Standard contract terms and deliverables apply.')}</p>
                    </div>

                    <div class="client-contract-parties-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;background:#F8FAFC;padding:14px;border-radius:8px;border:1px solid #E2E8F0;">
                        <div>
                            <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:4px;">CLIENT ORGANIZATION</div>
                            <div style="font-size:13px;font-weight:700;color:#0F172A;">${escapeHtml(contract.company_name || 'Your Organization')}</div>
                            <div style="font-size:12px;color:#475569;margin-top:2px;">Representative: ${escapeHtml(contract.contact_name || 'Client Representative')}</div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:4px;">SERVICE PROVIDER</div>
                            <div style="font-size:13px;font-weight:700;color:#0F172A;">NexFlow CRM Solutions</div>
                            <div style="font-size:12px;color:#475569;margin-top:2px;">Account Lead: ${escapeHtml(contract.owner_name || 'Account Lead')}</div>
                        </div>
                    </div>

                    ${isPendingSig ? `
                        <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;padding:14px 16px;margin-top:20px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#92400E;">Signature Required</div>
                                <div style="font-size:12px;color:#78350F;">Please review the contract terms and digitally sign below or request changes.</div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#B45309;border-color:#FDE68A;background:#FFF;" onclick="window.clientContracts.openRequestChangesModal('${contract.id}')">Request Changes</button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientContracts.openSignContractModal('${contract.id}')">Sign Contract</button>
                            </div>
                        </div>
                    ` : ''}
                `;
            } else if (tabName === "terms") {
                body.innerHTML = `
                    <div class="client-contract-terms-box" style="display:flex;flex-direction:column;gap:12px;">
                        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 14px;">
                            <div style="font-weight:700;color:#0F172A;font-size:13px;margin-bottom:4px;">Scope of Work &amp; Terms</div>
                            <p style="margin:0;font-size:12.5px;color:#475569;line-height:1.5;white-space:pre-wrap;">${escapeHtml(contract.terms || contract.overview || 'Standard scope of work and agreed terms apply.')}</p>
                        </div>
                        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 14px;">
                            <div style="font-weight:700;color:#0F172A;font-size:13px;margin-bottom:4px;">Payment Terms</div>
                            <p style="margin:0;font-size:12.5px;color:#475569;line-height:1.5;">${escapeHtml(contract.payment_terms || 'Payment terms as per agreed quotation schedule.')}</p>
                        </div>
                    </div>
                `;
            } else if (tabName === "document") {
                const filesListHtml = (contract.files && contract.files.length > 0)
                    ? contract.files.map(f => `
                        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <svg width="28" height="28" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <div>
                                    <div style="font-weight:700;color:#0F172A;font-size:13.5px;">${escapeHtml(f.file_name)}</div>
                                    <div style="font-size:11.5px;color:#64748B;">Official Contract Attachment • ${(f.file_size ? (f.file_size / 1024).toFixed(1) + ' KB' : 'Document')}</div>
                                </div>
                            </div>
                            <a href="api/client-contracts.php?action=download_file&contract_id=${contract.id}&file_id=${f.id}" class="btn btn-secondary btn-xs" style="text-decoration:none;" download>
                                Download
                            </a>
                        </div>
                    `).join("")
                    : `
                        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <svg width="28" height="28" fill="none" stroke="#64748B" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <div>
                                    <div style="font-weight:700;color:#0F172A;font-size:13.5px;">${escapeHtml(contract.contract_number || 'Contract')}_Agreement.pdf</div>
                                    <div style="font-size:11.5px;color:#64748B;">Executed Contract Agreement • Digital Record</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.print()">
                                Print / Save
                            </button>
                        </div>
                    `;

                body.innerHTML = `
                    ${filesListHtml}

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;">
                        <div>
                            <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:6px;">PROVIDER SIGNATURE</div>
                            <div style="font-size:13px;font-weight:600;color:#059669;display:flex;align-items:center;gap:4px;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Signed (${escapeHtml(contract.owner_name || 'Account Lead')})
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:6px;">CLIENT SIGNATURE</div>
                            ${contract.is_client_signed ? `
                                <div style="font-size:13px;font-weight:600;color:#059669;display:flex;align-items:center;gap:4px;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    Signed (${escapeHtml(contract.contact_name || 'Client')}${contract.signed_date ? ' on ' + escapeHtml(contract.signed_date) : ''})
                                </div>
                            ` : `
                                <div style="font-size:13px;font-weight:600;color:#D97706;display:flex;align-items:center;gap:4px;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    Pending Signature
                                </div>
                            `}
                        </div>
                    </div>
                `;
            } else if (tabName === "timeline") {
                const timeline = (contract.timeline && contract.timeline.length > 0) ? contract.timeline : [];
                if (timeline.length === 0) {
                    if (contract.created_at) {
                        timeline.push({
                            title: 'Contract Created',
                            text: 'Contract created',
                            date: contract.created_at,
                            user: contract.owner_name || 'Account Lead'
                        });
                    }
                    if (contract.status === 'Sent' || contract.signature_status === 'Sent') {
                        timeline.push({
                            title: 'Contract Sent',
                            text: 'Contract sent to client',
                            date: contract.updated_at || contract.created_at,
                            user: contract.owner_name || 'Account Lead'
                        });
                    }
                    if (contract.change_request_date) {
                        timeline.push({
                            title: 'Change Request Submitted',
                            text: `Change request submitted: "${contract.change_request_text}"`,
                            date: contract.change_request_date,
                            user: contract.change_request_contact || 'Client'
                        });
                    }
                    if (contract.is_client_signed) {
                        timeline.push({
                            title: 'Contract Signed',
                            text: 'Contract signed by client',
                            date: contract.signed_date || contract.updated_at,
                            user: contract.contact_name || 'Client'
                        });
                    }
                }

                if (timeline.length === 0) {
                    timeline.push({
                        title: 'Contract Recorded',
                        text: 'Contract recorded in system',
                        date: contract.created_at || 'Recently',
                        user: 'System'
                    });
                }

                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:14px;position:relative;padding-left:18px;">
                        ${timeline.map(t => `
                            <div style="position:relative;">
                                <div style="position:absolute;left:-18px;top:4px;width:8px;height:8px;border-radius:50%;background:#2563EB;"></div>
                                <div style="font-weight:600;color:#0F172A;font-size:12.5px;">${escapeHtml(t.title || t.text)}</div>
                                <div style="font-size:12px;color:#475569;margin-top:2px;">${escapeHtml(t.text)}</div>
                                <div style="font-size:11px;color:#64748B;margin-top:2px;">${escapeHtml(t.date)} ${t.user ? '• ' + escapeHtml(t.user) : ''}</div>
                            </div>
                        `).join("")}
                    </div>
                `;
            }
        },

        // Request Changes Modal Handlers
        openRequestChangesModal: function (contractId) {
            this.pendingModalContractId = contractId || this.activeContractId;
            const modal = document.getElementById("requestChangesModal");
            const txt = document.getElementById("rcTextarea");
            if (txt) txt.value = "";
            if (modal) {
                modal.style.display = "flex";
                modal.classList.add("show");
            }
        },

        closeRequestChangesModal: function () {
            const modal = document.getElementById("requestChangesModal");
            if (modal) {
                modal.classList.remove("show");
                modal.style.display = "none";
            }
            restorePageInteraction();
        },

        submitRequestChanges: function () {
            const contractId = this.pendingModalContractId || this.activeContractId;
            const txt = document.getElementById("rcTextarea");
            const msg = txt ? txt.value.trim() : "";

            if (!contractId) {
                showClientToast("No contract selected.", "error");
                return;
            }
            if (!msg) {
                showClientToast("Please describe the changes you would like to request.", "warning");
                if (txt) txt.focus();
                return;
            }

            const csrfToken = window.clientPortalCsrf || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'request_changes');
            formData.append('id', contractId);
            formData.append('message', msg);
            formData.append('csrf_token', csrfToken);

            fetch('api/client-contracts.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showClientToast(res.message || "Change request submitted successfully!", "success");
                    this.closeRequestChangesModal();
                    if (this.activeContractId && String(this.activeContractId) === String(contractId)) {
                        this.openContractDrawer(contractId);
                    }
                    fetchContracts();
                    refreshKPIs();
                } else {
                    showClientToast(res.message || "Failed to submit change request.", "error");
                }
            })
            .catch(err => {
                console.error("Change request error:", err);
                showClientToast("Server error while submitting change request.", "error");
            });
        },

        // Sign Contract Modal Handlers
        openSignContractModal: function (contractId) {
            this.pendingModalContractId = contractId || this.activeContractId;
            const modal = document.getElementById("signContractModal");
            const lblId = document.getElementById("scModalId");
            const signatoryInput = document.getElementById("scSignatoryInput");
            const consentCheckbox = document.getElementById("scConsentCheckbox");
            const found = currentContracts.find(c => String(c.id) === String(this.pendingModalContractId));
            if (lblId) lblId.textContent = found ? (found.contract_number || found.id) : this.pendingModalContractId;

            if (consentCheckbox) {
                consentCheckbox.checked = false;
            }
            if (signatoryInput) {
                signatoryInput.value = window.clientContactName || (found && found.contact_name) || signatoryInput.value || '';
            }

            if (modal) {
                modal.style.display = "flex";
                modal.classList.add("show");
            }
        },

        closeSignContractModal: function () {
            const modal = document.getElementById("signContractModal");
            const consentCheckbox = document.getElementById("scConsentCheckbox");
            if (consentCheckbox) {
                consentCheckbox.checked = false;
            }
            if (modal) {
                modal.classList.remove("show");
                modal.style.display = "none";
            }
            restorePageInteraction();
        },

        confirmSignContract: function () {
            const contractId = this.pendingModalContractId || this.activeContractId;
            const signatoryInput = document.getElementById("scSignatoryInput");
            const consentCheckbox = document.getElementById("scConsentCheckbox");

            if (!contractId) {
                showClientToast("No contract selected.", "error");
                return;
            }

            if (consentCheckbox && !consentCheckbox.checked) {
                showClientToast("Please confirm agreement before signing.", "warning");
                return;
            }

            const signatoryName = signatoryInput ? signatoryInput.value.trim() : "";
            const csrfToken = window.clientPortalCsrf || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'sign');
            formData.append('id', contractId);
            formData.append('signatory_name', signatoryName);
            formData.append('csrf_token', csrfToken);

            fetch('api/client-contracts.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showClientToast(res.message || "Contract signed successfully!", "success");
                    this.closeSignContractModal();
                    if (this.activeContractId && String(this.activeContractId) === String(contractId)) {
                        this.openContractDrawer(contractId);
                    }
                    fetchContracts();
                    refreshKPIs();
                } else {
                    showClientToast(res.message || "Failed to sign contract.", "error");
                }
            })
            .catch(err => {
                console.error("Contract sign error:", err);
                showClientToast("Server error while signing contract.", "error");
            });
        },

        // Decline Contract Modal Handlers
        openDeclineContractModal: function (contractId) {
            this.pendingModalContractId = contractId || this.activeContractId;
            const modal = document.getElementById("declineContractModal");
            if (modal) {
                modal.style.display = "flex";
                modal.classList.add("show");
            }
        },

        closeDeclineContractModal: function () {
            const modal = document.getElementById("declineContractModal");
            if (modal) {
                modal.classList.remove("show");
                modal.style.display = "none";
            }
            restorePageInteraction();
        },

        confirmDeclineContract: function () {
            const contractId = this.pendingModalContractId || this.activeContractId;
            if (!contractId) {
                showClientToast("No contract selected.", "error");
                return;
            }

            const csrfToken = window.clientPortalCsrf || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'decline');
            formData.append('id', contractId);
            formData.append('csrf_token', csrfToken);

            fetch('api/client-contracts.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showClientToast(res.message || "Contract declined.", "success");
                    this.closeDeclineContractModal();
                    if (this.activeContractId && String(this.activeContractId) === String(contractId)) {
                        this.closeContractDrawer();
                    }
                    fetchContracts();
                    refreshKPIs();
                } else {
                    showClientToast(res.message || "Failed to decline contract.", "error");
                }
            })
            .catch(err => {
                console.error("Contract decline error:", err);
                showClientToast("Server error while declining contract.", "error");
            });
        }
    };

    // Keyboard Escape handling
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            window.clientContracts.closeRequestChangesModal();
            window.clientContracts.closeSignContractModal();
            window.clientContracts.closeDeclineContractModal();
            window.clientContracts.closeContractDrawer();
            restorePageInteraction();
        }
    });

    // Auto-init on page load
    document.addEventListener("DOMContentLoaded", function () {
        if (window.INITIAL_CONTRACT_KPIS) {
            updateKpiCounters(window.INITIAL_CONTRACT_KPIS);
        } else {
            refreshKPIs();
        }

        if (Array.isArray(window.INITIAL_CONTRACTS) && window.INITIAL_CONTRACTS.length > 0) {
            renderContractsTable(window.INITIAL_CONTRACTS);
        } else {
            fetchContracts();
        }
    });
})();